<?php
require_once('bb-config.php');

bb_repermalink();

$user = bb_get_user( $user_id );

if ( !$user )
	die('User not found.');

$user->user_url = get_user_link( $user_id );

if ( !isset( $_GET['updated'] ) )
	$updated = false;
else
	$updated = true;

$posts = $bbdb->get_results("SELECT *, MAX(post_time) as post_time FROM $bbdb->posts WHERE poster_id = $user_id AND post_status = 0 GROUP BY topic_id ORDER BY post_time DESC LIMIT 25");
$threads = $bbdb->get_results("SELECT * FROM $bbdb->topics WHERE topic_poster = $user_id AND topic_status = 0 ORDER BY topic_start_time DESC LIMIT 25");
if ( $threads )
	foreach ( $threads as $topic )
		$topic_cache[$topic->topic_id] = $topic;

// Cache topics from posts
if ( $posts ) :
	foreach ($posts as $post)
		$topics[] = $post->topic_id;
	$topic_ids = join(',', $topics);
	$topics = $bbdb->get_results("SELECT * FROM $bbdb->topics WHERE topic_id IN ($topic_ids)");
	foreach ($topics as $topic)
		$topic_cache[$topic->topic_id] = $topic;
endif;

bb_remove_filter('post_time', 'bb_offset_time');
bb_add_filter('post_time', 'strtotime');
bb_add_filter('post_time', 'bb_since');

require('bb-templates/profile.php');

?>
