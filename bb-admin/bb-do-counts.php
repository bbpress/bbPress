<?php

require('../bb-config.php');
header('Content-type: text/plain');

$topics = $bbdb->get_results("SELECT topic_id, COUNT(post_id) AS t_count FROM $bbdb->posts GROUP BY topic_id");

foreach ($topics as $topic) :
	$bbdb->query("UPDATE $bbdb->topics SET topic_posts = $topic->t_count WHERE topic_id = $topic->topic_id");
endforeach;

unset($topics);

$forums = $bbdb->get_results("SELECT forum_id, COUNT(topic_id) AS topic_count, SUM(topic_posts) AS post_count FROM $bbdb->topics GROUP BY forum_id");

foreach ($forums as $forum) :
	$bbdb->query("UPDATE $bbdb->forums SET topics = $forum->topic_count, posts = $forum->post_count WHERE forum_id = $forum->forum_id");
endforeach;

echo "$wpdb->num_queries queries. " . timer_stop() . 'seconds'; ?>
?>