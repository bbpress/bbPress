<?php

require_once('bb-config.php');

$forum_id = $page = 0;

$forum_id = (int) $_GET['id'];

if ( isset( $_GET['page'] ) )
	$page = (int) $_GET['page'];

$forum = get_forum( $forum_id );

$topics = get_latest_topics( $forum_id, $page );

include('bb-templates/forum.php');

?>