<?php
require('admin-action.php');

if ( bb_current_user_can('edit_deleted') && 'all' == $_GET['view'] ) {
	bb_add_filter('get_topic_where', 'no_where');
	bb_add_filter('bb_delete_post', 'topics_replied_on_undelete_post');
}

if ( !bb_current_user_can('manage_posts') ) {
	header('Location: ' . bb_get_option('uri') );
	exit();
}

$post_id = (int) $_GET['id'];
$status  = (int) $_GET['status'];
$bb_post = bb_get_post ( $post_id );

if ( !$bb_post )
	die(__('There is a problem with that post, pardner.'));

bb_delete_post( $post_id, $status );

$topic = get_topic( $bb_post->topic_id );

if ( $topic->topic_posts == 1 )
	$sendto = get_forum_link( $topic->forum_id );
else
	$sendto = $_SERVER['HTTP_REFERER'];

header( "Location: $sendto" );
exit;

?>
