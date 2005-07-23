<?php
require('bb-config.php');

$topic_id = (int) $_GET['topic'];
$user_id  = (int) $_GET['profile'];
$tag      = $_GET['tag'];

if ( !$topic_id )
	if ( 'topic' == get_path() )
		$topic_id = get_path(2);
if ( !$user_id )
	if ( 'profile' == get_path() )
		$user_id = get_path(2);
if ( !$tag )
	if ( 'tags' == get_path() )
		$tag = get_path(2);

$rss_override = false;
bb_do_action( 'bb_rss.php', '' );

if ( $topic_id ) {
	$topic = get_topic ( $topic_id );
	if ( !$topic )
		die();
	$posts = get_thread( $topic_id, 0, 1 );
	$title = bb_get_option('name') . ' Thread: ' . get_topic_title();
} elseif ( $user_id ) {
	$user = bb_get_user( $user_id );
	if ( !$user )
		die();
	$posts = get_user_favorites( $user->ID );
	if ( !$posts )
		die();
	$title = bb_get_option('name') . ' User Favorites: ' . $user->user_login;
} elseif ( $tag ) {
	$tag = get_tag_by_name($tag);
	if ( !$tag )
		die();
	$posts = get_tagged_topic_posts( $tag->tag_id, 0 );
	$title = bb_get_option('name') . ' Tag: ' . get_tag_name();
} elseif ( !$rss_override ) {
	$posts = get_latest_posts( 35 );
	$title = bb_get_option('name') . ': Last 35 Posts';
}

require_once( BBPATH . 'bb-includes/feed-functions.php');

bb_send_304( $posts[0]->post_time );

bb_add_filter('post_link', 'bb_specialchars');
bb_add_filter('post_text', 'htmlspecialchars');

require( BBPATH . 'bb-templates/rss2.php');

?>
