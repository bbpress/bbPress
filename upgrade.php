<?php
require('bb-config.php');
header ('content-type: text/plain');

$topics = $bbdb->get_results("SELECT topic_id FROM $bbdb->topics");
if ($topics) {
	foreach($topics as $topic) {
		$poster = $bbdb->get_row("SELECT poster_id, poster_name FROM $bbdb->posts WHERE topic_id = $topic->topic_id ORDER BY post_time DESC LIMIT 1");
		echo "$topic->topic_id - $poster->poster_name\n";
		$bbdb->query("UPDATE $bbdb->topics SET topic_last_poster = '$poster->poster_id', topic_last_poster_name = '$poster->poster_name' WHERE topic_id = '$topic->topic_id'");
	}
}

/* // uncomment to deslash old junk
function deslash($content) {
    // Note: \\\ inside a regex denotes a single backslash.

    // Replace one or more backslashes followed by a single quote with
    // a single quote.
    $content = preg_replace("/\\\+'/", "'", $content);

    // Replace one or more backslashes followed by a double quote with
    // a double quote.
    $content = preg_replace('/\\\+"/', '"', $content);

    // Replace one or more backslashes with one backslash.
    $content = preg_replace("/\\\+/", "\\", $content);

    return $content;
}

$posts = $bbdb->get_results("SELECT post_id, post_text FROM $bbdb->posts");
if ($posts) {
	foreach($posts as $post) {
		echo $post->post_id . ' ';
		$post_text = addslashes(deslash($post->post_text));
		$bbdb->query("UPDATE $bbdb->posts SET post_text = '$post_text' WHERE post_id = '$post->post_id'");
	}
}

$topics = $bbdb->get_results("SELECT topic_id, topic_title FROM $bbdb->topics");
if ($topics) {
	foreach($topics as $topic) {
		$topic_title = addslashes(deslash($topic->topic_title));
		$bbdb->query("UPDATE $bbdb->topics SET topic_title = '$topic_title' WHERE topic_id = '$topic->topic_id'");
	}
}
*/

?>