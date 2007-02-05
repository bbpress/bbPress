<?php
require_once('./bb-load.php');

if ( !is_bb_profile() ) {
	$sendto = get_profile_tab_link( bb_get_current_user_info( 'id' ), 'edit' );
	wp_redirect( $sendto );
}

do_action($self . '_pre_head', '');


if ( is_callable($self) )
	bb_load_template( 'profile-base.php', array('self') );

exit();
?>
