<?php
require_once('./bb-load.php');

if ( !is_bb_profile() ) {
	$sendto = get_profile_tab_link( $bb_current_user->ID, 'edit' );
	header("Location: $sendto");
}

do_action($self . '_pre_head', '');

if ( function_exists($self) )
	if (file_exists( BBPATH . 'my-templates/profile-base.php' ))
		require( BBPATH . 'my-templates/profile-base.php' );
	else	require( BBPATH . 'bb-templates/profile-base.php' );
exit();
?>
