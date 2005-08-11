<?php
require('admin-header.php');

if ( 0 < $current_user->user_type && 'deleted' == $_GET['view'] ) {
	bb_add_filter('get_topic_where', 'no_where');
	bb_add_filter('bb_delete_post', 'topics_replied_on_undelete_post');
}

$post_id = (int) $_GET['id'];
$post    =  get_post ( $post_id );

if ( !$post )
	die('There is a problem with that post, pardner.');

bb_delete_post( $post_id );

$topic = get_topic( $post->topic_id );

if ( $topic->topic_posts == 1 )
	$sendto = get_forum_link( $topic->forum_id );
else
	$sendto = $_SERVER['HTTP_REFERER'];

header( "Location: $sendto" );
exit;

?>
