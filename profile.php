<?php
require_once('bb-config.php');

$user_id = (int) $_GET['id'];

$user = bb_get_user( $user_id );

if ( !$user )
	die('User not found.');

$ts = strtotime( $user->user_regdate );

/*
$posts = $bbdb->get_results("SELECT DISTINCT $bbdb->posts.topic_id, $bbdb->posts.forum_id, topic_title, MAX(post_time) AS m, UNIX_TIMESTAMP(MAX(post_time))
AS posted FROM $bbdb->posts, $bbdb->topics WHERE $bbdb->posts.poster_id=$user->user_id AND
$bbdb->posts.topic_id=$bbdb->topics.topic_id GROUP BY $bbdb->posts.topic_id ORDER BY m desc LIMIT 25");
*/

$posts = $bbdb->get_results("SELECT * FROM $bbdb->posts WHERE poster_id = $user_id GROUP BY topic_id ORDER BY post_time DESC LIMIT 25");
$threads = $bbdb->get_results("SELECT * FROM $bbdb->topics WHERE topic_poster = $user_id ORDER BY topic_time DESC LIMIT 25");

// Cache topic names
foreach ($posts as $post) :
	$topics[] = $post->topic_id;
endforeach;
foreach ($threads as $thread) :
	$topics[] = $thread->topic_id;
endforeach;
$topic_ids = join(',', $topics);
$topics = $bbdb->get_results("SELECT * FROM $bbdb->topics WHERE topic_id IN ($topic_ids)");
foreach ($topics as $topic) :
	$topic_cache[$topic->topic_id] = $topic;
endforeach;

bb_add_filter('post_time', 'strtotime');
bb_add_filter('post_time', 'bb_since');

require('bb-templates/profile.php');

?>