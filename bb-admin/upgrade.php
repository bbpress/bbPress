<?php
require('../bb-config.php');
header ('content-type: text/plain');
set_time_limit(600);
// Uncomment to use. Best to run one at a time

/* Add _topics.topic_resolved column
$bbdb->query("ALTER TABLE $bbdb->topics ADD topic_resolved VARCHAR(15) DEFAULT 'no' NOT NULL AFTER topic_status");
echo "Done with adding topic_resolved column\n";
flush();
*/

/* Populate _topics.topic_start_time: June 3rd, 2005
$topics = $bbdb->get_results("SELECT topic_id FROM $bbdb->topics");
if ($topics) {
	foreach($topics as $topic) {
		$start_time = $bbdb->get_var("SELECT post_time FROM $bbdb->posts RIGHT JOIN $bbdb->topics ON ( $bbdb->posts.topic_id = $bbdb->topics.topic_id )
 WHERE $bbdb->topics.topic_id = '$topic->topic_id' ORDER BY post_time ASC LIMIT 1");
		echo '.';
		$bbdb->query("UPDATE $bbdb->topics SET topic_start_time = '$start_time' WHERE topic_id = '$topic->topic_id'");
	}
}
unset($topics);
echo "Done with adding topic_start_time...\n";
flush();
*/

/* Add _topics.topic_start_time column
$bbdb->query("ALTER TABLE $bbdb->topics ADD topic_start_time DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00' AFTER topic_last_poster_name");
echo "Done with adding topic_start_time column\n";
flush();
*/

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
		$post_text = bb_apply_filters('pre_post', $post_text);
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