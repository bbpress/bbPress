<?php
require('bb-config.php');

// Never cache
header('Expires: Wed, 11 Jan 1984 05:00:00 GMT');
header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
header('Cache-Control: no-cache, must-revalidate');
header('Pragma: no-cache');

$post_id = (int) $_POST['post_id'];

$post  = get_post( $post_id );

if ( !$post || !can_edit( $post->poster_id ) ) {
	header('Location: ' . get_option('uri') );
	die();
}

if ( bb_is_first( $post->post_id ) )
	bb_update_topic( $_POST['topic'], $post->topic_id);

bb_update_post( $_POST['post_content'], $post_id);

if ($post_id)
	header('Location: ' . get_post_link($post_id) );
else
	header('Location: ' . get_option('uri') );
?>