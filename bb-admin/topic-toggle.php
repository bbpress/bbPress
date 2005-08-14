<?php
require('admin-header.php');

$topic_id = (int) $_GET['id'];
$topic    =  get_topic ( $topic_id );

if ( !$topic )
	die('There is a problem with that topic, pardner.');

if ( !current_user_can('manage_topics') ) {
	header('Location: ' . bb_get_option('uri') );
	exit();
}

if ( topic_is_open( $topic_id ) )
	bb_close_topic( $topic_id );
else
	bb_open_topic ( $topic_id );

header( 'Location: ' . $_SERVER['HTTP_REFERER'] );
exit;

?>
