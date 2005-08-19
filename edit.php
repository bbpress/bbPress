<?php
require('bb-config.php');

if ( bb_current_user_can('edit_deleted') && 'deleted' == $_GET['view'] ) {
	bb_add_filter('bb_is_first_where', 'no_where');
}

$post_id = (int) $_GET['id'];

$post  = bb_get_post( $post_id );

if ( !$post || !bb_current_user_can( 'edit_post', $post_id ) ) {
	header('Location: ' . bb_get_option('uri') );
	die();
}

$topic = get_topic( $post->topic_id );

if ( bb_is_first( $post->post_id ) && bb_current_user_can( 'edit_topic', $topic->topic_id ) ) 
	$topic_title = $topic->topic_title;
else 
	$topic_title = false;

if (file_exists( BBPATH . 'my-templates/edit-post.php' ))
	require( BBPATH . 'my-templates/edit-post.php' );
else	require( BBPATH . 'bb-templates/edit-post.php' );

?>
