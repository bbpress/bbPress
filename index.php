<?php

require_once('bb-config.php');

// Comment to hide forums
$forums = get_forums();

$topics = get_latest_topics();

bb_do_action( 'bb_index.php', '' );

if (file_exists( BBPATH . 'my-templates/front-page.php' ))
	require( BBPATH . 'my-templates/front-page.php' );
else	require( BBPATH . 'bb-templates/front-page.php' );

?>
