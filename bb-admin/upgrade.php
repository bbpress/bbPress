<?php
require('../bb-config.php');
header ('content-type: text/plain');
set_time_limit(600);
// Uncomment to use. Best to run one at a time

/*
$topics = $bbdb->get_results("SELECT topic_id FROM $bbdb->topics");
if ($topics) {
	foreach($topics as $topic) {
		$poster = $bbdb->get_row("SELECT poster_id, poster_name FROM $bbdb->posts WHERE topic_id = $topic->topic_id ORDER BY post_time DESC LIMIT 1");
		echo '.';
		$bbdb->query("UPDATE $bbdb->topics SET topic_last_poster = '$poster->poster_id', topic_last_poster_name = '$poster->poster_name' WHERE topic_id = '$topic->topic_id'");
	}
}
unset($topics);
echo "Done with adding people...";
flush();
*/
/*
$posts = $bbdb->get_results("SELECT post_id, post_text FROM $bbdb->posts");
if ($posts) {
	foreach($posts as $post) {
		echo '.'; flush();
		$post_text = addslashes(deslash($post->post_text));
		$post_text = apply_filters('pre_post', $post_text);
		$bbdb->query("UPDATE $bbdb->posts SET post_text = '$post_text' WHERE post_id = '$post->post_id'");
	}
}

unset($posts);
echo "Done with preformatting posts...";
*/
/*
$topics = $bbdb->get_results("SELECT topic_id, topic_title FROM $bbdb->topics");
if ($topics) {
	foreach($topics as $topic) {
		$topic_title = bb_specialchars(addslashes(deslash($topic->topic_title)));
		$bbdb->query("UPDATE $bbdb->topics SET topic_title = '$topic_title' WHERE topic_id = '$topic->topic_id'");
		echo '.';
	}
}
echo "Done with preformatting topics!";
flush();
*/
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

?>