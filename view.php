<?php
require_once('config.php');

bb_repermalink();

switch ( $view ) :
case 'no-replies' :
	bb_add_filter( 'get_latest_topics_where', 'no_replies' );
	$topics = get_latest_topics( 0, $page );
	break;
case 'untagged' :
	bb_add_filter( 'get_latest_topics_where', 'untagged' );
	bb_add_filter( 'get_sticky_topics_where', 'untagged' );
	$topics = get_latest_topics( 0, $page );
	$stickies = get_sticky_topics( 0, $page );
	break;	
case 'unresolved' :
	bb_add_filter( 'get_latest_topics_where', 'unresolved' );
	$topics = get_latest_topics( 0, $page );
	break;
default :
	bb_do_action( 'bb_custom_view', $view );
endswitch;

bb_do_action( 'bb_view.php', '' );
if (file_exists( BBPATH . 'my-templates/view.php' ))
	require( BBPATH . 'my-templates/view.php' );
else	require( BBPATH . 'bb-templates/view.php' );
?>
