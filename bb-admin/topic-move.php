<?php
require_once('admin-header.php');

$topic_id = $_REQUEST['topic_id'];
$forum_id = $_REQUEST['forum_id'];

if ( !is_numeric($topic_id) || !is_numeric($forum_id) )
	die('Neither cast ye for pearls ye swine.');

$topic = get_topic( $topic_id );
$forum = get_forum( $forum_id );

if ( !$topic || !$forum )
	die('Your topic or forum caused all manner of confusion');

bb_move_topic( $topic_id, $forum_id );

header('Location: ' . $_SERVER['HTTP_REFERER']);
exit;
?>
