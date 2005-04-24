<?php
require_once('bb-config.php');

$topic_id = $page = 0;

$topic_id = (int) $_GET['id'];
if ( !$topic_id )
	$topic_id = get_path();

if ( isset( $_GET['page'] ) )
	$page = (int) abs( $_GET['page'] );

$topic = get_topic ( $topic_id );
if ( !$topic )
	die('Topic not found.');
$posts = get_thread( $topic_id, $page );
$forum = get_forum ( $topic->forum_id );

$list_start = $page * bb_get_option('page_topics');
if ( !$list_start ) $list_start = 1;

post_author_cache($posts);

include('bb-templates/topic.php');

?>