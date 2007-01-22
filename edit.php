<?php
require('./bb-load.php');

bb_auth();

if ( bb_current_user_can('edit_deleted') && 'all' == $_GET['view'] ) {
	add_filter('bb_is_first_where', 'no_where');
}

$post_id = (int) $_GET['id'];

$bb_post  = bb_get_post( $post_id );

if ( !$bb_post || !bb_current_user_can( 'edit_post', $post_id ) ) {
	wp_redirect( bb_get_option( 'uri' ) );
	die();
}

$topic = get_topic( $bb_post->topic_id );

if ( bb_is_first( $bb_post->post_id ) && bb_current_user_can( 'edit_topic', $topic->topic_id ) ) 
	$topic_title = $topic->topic_title;
else 
	$topic_title = false;


bb_load_template( 'edit-post.php', array('topic_title') );

?>
