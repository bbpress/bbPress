<?php
require('bb-config.php');

require_once( BBPATH . 'bb-includes/registration-functions.php');

nocache_headers();

if ( !$current_user )
	die('You need to be logged in to edit your profile.');

$updated = false;

if ($_POST) :
	
	$url       = bb_fix_link( $_POST['url'] );
	$url       = bb_specialchars( $url                , 1);
	$location  = bb_specialchars( $_POST['location']  , 1);
	$interests = bb_specialchars( $_POST['interests'] , 1);
	$updated   = true;

	bb_update_user( $current_user->ID, $url, $location, $interests );
	
	if ( !empty( $_POST['pass1'] ) && $_POST['pass1'] == $_POST['pass2'] ) :
		bb_update_user_password ( $current_user->ID, $_POST['pass1'] );
		bb_cookie( $bb->passcookie, md5( md5( $_POST['pass1'] ) ) ); // One week
	endif;
	$sendto = bb_add_query_arg( 'updated', 'true', get_user_profile_link( $current_user->ID ) );
	header("Location: $sendto");
	exit();	

endif;

require( BBPATH . 'bb-templates/profile-edit.php');
?>
