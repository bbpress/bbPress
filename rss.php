<?php
require('bb-config.php');

$topic_id = (int) $_GET['topic'];

if ( $topic_id ) {
	$topic = get_topic ( $topic_id );
	if ( !$topic )
		die();
	$posts = get_thread( $topic_id, 0, 1 );
	$title = bb_get_option('name') . ' Thread: ' . get_topic_title();
} else {
	$posts = get_latest_posts( 35 );
	$title = bb_get_option('name') . ': Last 35 Posts';
}

bb_add_filter('post_text', 'htmlspecialchars');

require( BBPATH . 'bb-templates/rss2.php');

?>