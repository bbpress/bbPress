<?php
require('bb-config.php');

require_once( BBPATH . 'bb-includes/registration-functions.php');

// Never cache
header('Expires: Wed, 11 Jan 1984 05:00:00 GMT');
header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
header('Cache-Control: no-cache, must-revalidate');
header('Pragma: no-cache');

if ( !$current_user )
	die('You need to be logged in to edit your profile.');

$updated = false;

if ($_POST) :
	
	$website   = bb_fix_link( $_POST['website'] );
	$website   = bb_specialchars( $website            , 1);
	$location  = bb_specialchars( $_POST['location']  , 1);
	$interests = bb_specialchars( $_POST['interests'] , 1);
	$updated   = true;

	bb_update_user( $current_user->user_id, $website, $location, $interests );
	
	if ( !empty( $_POST['pass1'] ) && $_POST['pass1'] == $_POST['pass2'] ) :
		bb_update_user_password ( $current_user->user_id, $_POST['pass1'] );
		setcookie('bb_pass_'. BBHASH, md5( md5( $_POST['pass1'] ) ), time() + 604800, bb_get_option('path') ); // One week
	endif;
	$sendto = bb_add_query_arg( 'updated', 'true', user_profile_link( $current_user->user_id ) );
	header("Location: $sendto");
	exit();	

endif;

require( BBPATH . 'bb-templates/profile-edit.php');
?>