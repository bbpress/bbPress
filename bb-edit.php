<?php
require('bb-config.php');

nocache_headers();

if ( current_user_can('edit_deleted') && 'deleted' == $_GET['view'] ) {
	bb_add_filter('bb_is_first_where', 'no_where');
}

$post_id = (int) $_POST['post_id'];

$post  = get_post( $post_id );

if ( !$post ) {
	header('Location: ' . bb_get_option('uri') );
	die();
}

if ( !current_user_can( 'edit_post', $post_id ) )
	die('Sorry, post is too old.');

if ( bb_is_first( $post->post_id ) && current_user_can( 'edit_topic', $post->topic_id ) )
	bb_update_topic( $_POST['topic'], $post->topic_id);

bb_update_post( $_POST['post_content'], $post_id);

if ($post_id)
	header('Location: ' . get_post_link($post_id) );
else
	header('Location: ' . bb_get_option('uri') );
?>
