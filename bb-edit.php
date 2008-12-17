<?php
require('./bb-load.php');

bb_auth('logged_in');

$post_id = (int) $_POST['post_id'];

$bb_post  = bb_get_post( $post_id );

if ( !$bb_post ) {
	wp_redirect( bb_get_uri(null, null, BB_URI_CONTEXT_HEADER) );
	die();
}

if ( !bb_current_user_can( 'edit_post', $post_id ) )
	bb_die(__('Sorry, post is too old.'));

bb_check_admin_referer( 'edit-post_' . $post_id );

if ( 0 != $bb_post->post_status && 'all' == $_GET['view'] ) // We're trying to edit a deleted post
	add_filter('bb_is_first_where', 'no_where');

if ( bb_is_first( $bb_post->post_id ) && bb_current_user_can( 'edit_topic', $bb_post->topic_id ) ) {
	bb_insert_topic( array(
		'topic_title' => stripslashes( $_POST['topic'] ),
		'topic_id' => $bb_post->topic_id
	) );
}

bb_insert_post( array(
	'post_text' => stripslashes( $_POST['post_content'] ),
	'post_id' => $post_id,
	'topic_id' => $bb_post->topic_id
) );

if ( $post_id ) {
	if ( $_REQUEST['view'] === 'all' ) {
		add_filter( 'get_post_link', 'bb_make_link_view_all' );
	}
	$post_link = get_post_link( $post_id );
	wp_redirect( $post_link );
} else {
	wp_redirect( bb_get_uri(null, null, BB_URI_CONTEXT_HEADER) );
}
?>
