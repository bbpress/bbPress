<?php
require_once('bb-config.php');

$q = trim( $_GET['q'] );
$likeit = preg_replace('/\s+/', '%', $q);

if ( !empty( $q ) ) :

if ( strlen( preg_replace('/[^a-z0-9]/i', '', $q) ) > 2 )
	$users = $bbdb->get_results("SELECT * FROM $bbdb->users WHERE username LIKE ('%$likeit%')");

$titles = $bbdb->get_results("SELECT * FROM $bbdb->topics JOIN $bbdb->posts ON topic_last_post_id = post_id WHERE LOWER(topic_title) LIKE ('%$likeit%') AND topic_status = 0 ORDER BY post_time DESC LIMIT 5");

$recent = $bbdb->get_results("SELECT * FROM $bbdb->posts JOIN $bbdb->topics ON
topic_last_post_id = post_id WHERE LOWER(post_text) LIKE ('%$likeit%') AND post_status = 0 ORDER BY post_time DESC LIMIT 5");

$relevant = $bbdb->get_results("SELECT $bbdb->posts.forum_id, $bbdb->posts.topic_id, post_text, topic_title, UNIX_TIMESTAMP(post_time)
AS posttime, post_id FROM $bbdb->posts RIGHT JOIN $bbdb->topics ON $bbdb->posts.topic_id = $bbdb->topics.topic_id
WHERE MATCH(post_text) AGAINST ('$q') AND post_status = 0 LIMIT 5");

bb_do_action('do_search', $q);

// Cache topics
if ( $titles ) :
	foreach ($titles as $topic)
		$topic_ids[] = $topic->topic_id;
endif;

if ( $recent) :
	foreach ($recent as $post)
		$topic_ids[] = $post->topic_id;
endif;

if ( $relevant ) :
	foreach ($relevant as $post)
		$topic_ids[] = $post->topic_id;
endif;

if ( $titles || $recent || $relevant ) :
	$topic_ids = join(',', $topic_ids);
	$topics = $bbdb->get_results("SELECT * FROM $bbdb->topics WHERE topic_id IN ($topic_ids)");
	foreach ($topics as $topic) :
		$topic_cache[$topic->topic_id] = $topic;
	endforeach;
endif;

endif;

$q = stripslashes( $q );

require('bb-templates/search.php');

?>