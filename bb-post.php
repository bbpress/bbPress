<?php
require('bb-config.php');

nocache_headers();

if ( !$current_user )
	die('You need to be logged in to post.');

if ( isset($_POST['topic']) && $forum = (int) $_POST['forum_id'] ) {
	$topic = trim( $_POST['topic'] );
	$tags  = trim( $_POST['tags']  );

	if ('' == $topic)
		die('Please enter a topic title');

	$topic_id = bb_new_topic( $topic, $forum, $tags );
} elseif ( isset($_POST['topic_id'] ) ) {
	$topic_id = (int) $_POST['topic_id'];
}

if ( !topic_is_open( $topic_id ) )
	die('This topic has been closed');

$post_id = bb_new_post( $topic_id, $_POST['post_content'] );

if ($post_id)
	header('Location: ' . get_post_link($post_id) );
else
	header('Location: ' . bb_get_option('uri') );
exit;

?>