<?php
require_once('bb-config.php');

$q = trim( $_GET['q'] );
$likeit = preg_replace('/\s+/', '%', $q);

if ( !empty( $q ) ) :

$topics = $bbdb->get_results("SELECT *, UNIX_TIMESTAMP(post_time) AS posttime FROM $bbdb->topics JOIN $bbdb->posts ON topic_last_post_id =
post_id WHERE LOWER(topic_title) LIKE ('%$likeit%') ORDER BY post_time DESC LIMIT 5");

$recent = $bbdb->get_results("SELECT *, UNIX_TIMESTAMP(post_time) AS posttime, post_id FROM $bbdb->posts JOIN $bbdb->topics ON
topic_last_post_id = post_id WHERE LOWER(post_text) LIKE ('%$likeit%') ORDER BY post_time DESC LIMIT 5");

$relevant = $bbdb->get_results("SELECT $bbdb->posts.forum_id, $bbdb->posts.topic_id, post_text, topic_title, UNIX_TIMESTAMP(post_time)
AS posttime, post_id FROM $bbdb->posts RIGHT JOIN $bbdb->topics ON topic_last_post_id = post_id
WHERE MATCH(post_text) AGAINST ('$q') LIMIT 5");

bb_do_action('do_search', $q);

// Cache topics
foreach ($topics as $topic)
	$topic_ids[] = $topic->topic_id;
foreach ($recent as $post)
	$topic_ids[] = $post->topic_id;
foreach ($relevant as $post)
	$topic_ids[] = $post->topic_id;
$topic_ids = join(',', $topic_ids);
$topics = $bbdb->get_results("SELECT * FROM $bbdb->topics WHERE topic_id IN ($topic_ids)");
foreach ($topics as $topic) :
	$topic_cache[$topic->topic_id] = $topic;
endforeach;

endif;

require('bb-templates/search.php');

?>