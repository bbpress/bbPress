<?php
require_once('./bb-load.php');

bb_repermalink();

switch ( $view ) :
case 'no-replies' :
	add_filter( 'get_latest_topics_where', 'no_replies' );
	$topics = get_latest_topics( 0, $page );
	break;
case 'untagged' :
	add_filter( 'get_latest_topics_where', 'untagged' );
	add_filter( 'get_sticky_topics_where', 'untagged' );
	$topics = get_latest_topics( 0, $page );
	$stickies = get_sticky_topics( 0, $page );
	break;	
case 'unresolved' :
	add_filter( 'get_latest_topics_where', 'unresolved' );
	$topics = get_latest_topics( 0, $page );
	break;
default :
	do_action( 'bb_custom_view', $view );
endswitch;

do_action( 'bb_view.php', '' );
if (file_exists( BBPATH . 'my-templates/view.php' ))
	require( BBPATH . 'my-templates/view.php' );
else	require( BBPATH . 'bb-templates/view.php' );
?>
