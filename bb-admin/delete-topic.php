<?php
require('admin-header.php');

$topic_id = (int) $_GET['id'];
$topic    =  get_topic ( $topic_id );
if ( $current_user->user_type < 2 ) {
	header('Location: ' . bb_get_option('uri') );
	die();
}

if ( !$topic )
	die('There is a problem with that topic, pardner.');

$post_ids = get_thread_post_ids( $topic_id );
foreach ( $post_ids as $post_id )
	bb_delete_post( $post_id );

$sendto = get_forum_link( $topic->forum_id );

header( "Location: $sendto" );
exit;

?>