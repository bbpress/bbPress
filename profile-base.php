<?php
require_once('bb-config.php');

if ( !is_bb_profile() ) {
	$sendto = get_profile_tab_link( $current_user->ID, 'edit' );
	header("Location: $sendto");
}

$self_template = $self();

if ( function_exists($self_template) )
	if (file_exists( BBPATH . 'my-templates/profile-base.php' ))
		require( BBPATH . 'my-templates/profile-base.php' );
	else	require( BBPATH . 'bb-templates/profile-base.php' );
exit();
?>
