<?php
require_once('bb-config.php');

$user_id = (int) $_GET['id'];
if ( !$user_id )
	$user_id = intval( get_path() );

$user = bb_get_user( $user_id );

if ( !$user )
	die('User not found.');

$user->user_website = get_user_link( $user_id );

if ( !isset( $_GET['updated'] ) )
	$updated = false;
else
	$updated = true;

$ts = strtotime( $user->user_regdate );

$posts = $bbdb->get_results("SELECT * FROM $bbdb->posts WHERE poster_id = $user_id AND post_status = 0 GROUP BY topic_id ORDER BY post_time DESC LIMIT 25");
$threads = $bbdb->get_results("SELECT * FROM $bbdb->topics WHERE topic_poster = $user_id AND topic_status = 0 ORDER BY topic_time DESC LIMIT 25");

// Cache topic names
if ( $posts ) :
	foreach ($posts as $post)
		$topics[] = $post->topic_id;
endif;
if ( $threads ) :
	foreach ($threads as $thread)
		$topics[] = $thread->topic_id;
endif;

if ( $posts || $threads ) :
	$topic_ids = join(',', $topics);
	$topics = $bbdb->get_results("SELECT * FROM $bbdb->topics WHERE topic_id IN ($topic_ids)");
	foreach ($topics as $topic)
		$topic_cache[$topic->topic_id] = $topic;
endif;

bb_add_filter('post_time', 'strtotime');
bb_add_filter('post_time', 'bb_since');

require('bb-templates/profile.php');

?>