<?php
require_once('bb-config.php');

$user_id = (int) $_GET['id'];

$user = bb_get_user( $user_id );

if ( !$user )
	die('User not found.');

$ts = strtotime( $user->user_regdate );

$posts = $bbdb->get_results("SELECT * FROM $bbdb->posts WHERE poster_id = $user_id GROUP BY topic_id ORDER BY post_time DESC LIMIT 25");
$threads = $bbdb->get_results("SELECT * FROM $bbdb->topics WHERE topic_poster = $user_id ORDER BY topic_time DESC LIMIT 25");

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