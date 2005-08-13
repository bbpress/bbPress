<?php
require('admin-header.php');

if ( current_user_can('edit_deleted') && 'deleted' == $_GET['view'] ) {
	bb_add_filter('get_topic_where', 'no_where');
	bb_add_filter('bb_delete_post', 'topics_replied_on_undelete_post');
}

$post_id = (int) $_GET['id'];
$post    =  get_post ( $post_id );

if ( !$post )
	die('There is a problem with that post, pardner.');

if ( !current_user_can( 'edit_post', $post_id ) ) {
	header('Location: ' . bb_get_option('uri') );
	exit();
}

bb_delete_post( $post_id );

$topic = get_topic( $post->topic_id );

if ( $topic->topic_posts == 1 )
	$sendto = get_forum_link( $topic->forum_id );
else
	$sendto = $_SERVER['HTTP_REFERER'];

header( "Location: $sendto" );
exit;

?>
