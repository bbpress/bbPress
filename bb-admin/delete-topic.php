<?php
require('admin-header.php');

if ( current_user_can('edit_deleted') && 'deleted' == $_GET['view'] ) {
	bb_add_filter('get_topic_where', 'no_where');
	bb_add_filter('get_thread_post_ids_where', 'no_where');
}

$topic_id = (int) $_GET['id'];
$topic    =  get_topic ( $topic_id );

if ( !$topic )
	die('There is a problem with that topic, pardner.');

if ( !current_user_can('manage_topics') ) {
	header('Location: ' . bb_get_option('uri') );
	exit();
}

bb_delete_topic( $topic->topic_id );

if ( 0 == $topic->topic_status )
	$sendto = get_forum_link( $topic->forum_id );
else
	$sendto = get_topic_link( $topic_id );
	
header( "Location: $sendto" );
exit;

?>
