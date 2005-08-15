<?php

require_once('bb-config.php');

$forum_id = 0;

bb_repermalink();

if ( !$forum )
	die('Forum not found.');

$bb_db_override = false;
bb_do_action( 'bb_forum.php_pre_db', $forum_id );

if ( !$bb_db_override ) :
	$topics   = get_latest_topics( $forum_id, $page );
	$stickies = get_sticky_topics( $forum_id );
endif;

bb_do_action( 'bb_forum.php', $forum_id );

if (file_exists( BBPATH . 'my-templates/forum.php' ))
	require( BBPATH . 'my-templates/forum.php' );
else	require( BBPATH . 'bb-templates/forum.php' );

?>
