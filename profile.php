<?php
require_once('bb-config.php');

if ( isset($_GET['username']) ) :
	$user = bb_get_user_by_name( $_GET['username'] );
	if ( !$user )
		die('Username not found.');
	header('Location: ' . get_user_profile_link( $user->ID ) );
	exit;
endif;

$page = (int) $_GET['page'];

bb_repermalink(); // The magic happens here.

$user = bb_get_user( $user_id );

if ( !$user )
	die('User not found.');

if ( $self ) {
	if ( strpos($self, 'bb-plugins') === false )
		require($self);
	else
		require('bb-templates/profile-base.php');
	return;
}

$reg_time = strtotime( $user->user_registered );
$profile_info_keys = get_profile_info_keys();

if ( !isset( $_GET['updated'] ) )
	$updated = false;
else
	$updated = true;
$posts = get_recent_user_replies( $user_id );
$threads = get_recent_user_threads( $user_id );

bb_remove_filter('post_time', 'bb_offset_time');
bb_add_filter('post_time', 'strtotime');
bb_add_filter('post_time', 'bb_since');

require('bb-templates/profile.php');

?>
