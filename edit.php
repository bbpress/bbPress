<?php
require('bb-config.php');

$post_id = (int) $_GET['id'];

$post  = get_post( $post_id );

if ( !$post || !can_edit( $post->poster_id ) ) {
	header('Location: ' . bb_get_option('uri') );
	die();
}

$topic = get_topic( $post->topic_id );

if ( bb_is_first( $post->post_id ) ) 
	$topic_title = $topic->topic_title;
else 
	$topic_title = false;


require('bb-templates/edit-post.php');

?>