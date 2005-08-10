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

$bb_db_override = false;
bb_do_action( 'bb_rss.php_pre_db', '' );

if ( !$bb_db_override ) :
if ( $topic_id ) {
	if ( !$topic = get_topic ( $topic_id ) )
		die();
	$posts = get_thread( $topic_id, 0, 1 );
	$title = bb_get_option('name') . ' Thread: ' . get_topic_title();
} elseif ( $user_id ) {
	if ( !$user = bb_get_user( $user_id ) )
		die();
	if ( !$posts = get_user_favorites( $user->ID ) )
		die();
	$title = bb_get_option('name') . ' User Favorites: ' . $user->user_login;
} elseif ( $tag ) {
	if ( !$tag = get_tag_by_name($tag) )
		die();
	$posts = get_tagged_topic_posts( $tag->tag_id, 0 );
	$title = bb_get_option('name') . ' Tag: ' . get_tag_name();
} else {
	$posts = get_latest_posts( 35 );
	$title = bb_get_option('name') . ': Last 35 Posts';
}
endif;

bb_do_action( 'bb_rss.php', '' );

require_once( BBPATH . 'bb-includes/feed-functions.php');

bb_send_304( $posts[0]->post_time );

bb_add_filter('post_link', 'bb_specialchars');
bb_add_filter('post_text', 'htmlspecialchars');

if (file_exists( BBPATH . 'my-templates/rss2.php'))
	require( BBPATH . 'my-templates/rss2.php' );
else	require( BBPATH . 'bb-templates/rss2.php' );
?>
