<?php
require('admin-action.php');

if ( bb_current_user_can('edit_deleted') && 'all' == $_GET['view'] ) {
	add_filter('get_topic_where', 'no_where');
	add_filter('get_thread_post_ids_where', 'no_where');
}

if ( !bb_current_user_can('manage_topics') ) {
	header('Location: ' . bb_get_option('uri') );
	exit();
}

$topic_id = (int) $_GET['id'];
$topic    =  get_topic ( $topic_id );

if ( !$topic )
	die(__('There is a problem with that topic, pardner.'));

bb_delete_topic( $topic->topic_id );

if ( 0 == $topic->topic_status )
	$sendto = get_forum_link( $topic->forum_id );
else
	$sendto = get_topic_link( $topic_id );
	
header( "Location: $sendto" );
exit;

?>
