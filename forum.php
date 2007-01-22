<?php

require_once('./bb-load.php');

$forum_id = 0;

bb_repermalink();

if ( !$forum )
	bb_die(__('Forum not found.'));

$bb_db_override = false;
do_action( 'bb_forum.php_pre_db', $forum_id );

if ( !$bb_db_override ) :
	$topics   = get_latest_topics( $forum_id, $page );
	$stickies = get_sticky_topics( $forum_id, $page );
endif;

do_action( 'bb_forum.php', $forum_id );

bb_load_template( 'forum.php', array('bb_db_override', 'stickies') );

?>
