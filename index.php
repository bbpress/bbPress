<?php

require_once('bb-config.php');

$bb_db_override = false;
bb_do_action( 'bb_index.php_pre_db', '' );

if ( !$bb_db_override ) :
	$forums = get_forums(); // Comment to hide forums
	$topics = get_latest_topics();
	$super_stickies = get_sticky_topics();
endif;

bb_do_action( 'bb_index.php', '' );

if (file_exists( BBPATH . 'my-templates/front-page.php' ))
	require( BBPATH . 'my-templates/front-page.php' );
else	require( BBPATH . 'bb-templates/front-page.php' );

?>
