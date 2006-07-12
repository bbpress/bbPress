<?php
require_once('./bb-load.php');

if ( isset($_GET['username']) ) :
	$user = bb_get_user_by_name( $_GET['username'] );
	if ( !$user )
		die(__('Username not found.'));
	header('Location: ' . get_user_profile_link( $user->ID ) );
	exit;
endif;

bb_repermalink(); // The magic happens here.
$user = bb_get_user( $user_id );

if ( !$user )
	die(__('User not found.'));

if ( $self ) {
	if ( strpos($self, '.php') !== false )
		require($self);
	else
		require( BBPATH . 'profile-base.php' );
	return;
}

$reg_time = strtotime( $user->user_registered );
$profile_info_keys = get_profile_info_keys();

if ( !isset( $_GET['updated'] ) )
	$updated = false;
else
	$updated = true;

bb_do_action( 'bb_profile.php_pre_db', $user_id );
$posts = get_recent_user_replies( $user_id );
$threads = get_recent_user_threads( $user_id );

bb_remove_filter('bb_post_time', 'bb_offset_time');
bb_add_filter('bb_post_time', 'strtotime');
bb_add_filter('bb_post_time', 'bb_since');

bb_do_action( 'bb_profile.php', $user_id );

if (file_exists( BBPATH . 'my-templates/profile.php' ))
	require( BBPATH . 'my-templates/profile.php' );
else	require( BBPATH . 'bb-templates/profile.php' );
?>
