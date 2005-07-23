<?php

require_once('bb-config.php');

$forum_id = $page = 0;

bb_repermalink();

if ( isset( $_GET['page'] ) )
	$page = (int) abs( $_GET['page'] );

if ( !$forum )
	die('Forum not found.');

$topics   = get_latest_topics( $forum_id, $page );
$stickies = get_sticky_topics( $forum_id );

bb_do_action( 'bb_forum.php', $forum_id );

include('bb-templates/forum.php');

?>
