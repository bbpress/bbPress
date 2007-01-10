<?php
require_once('./bb-load.php');

bb_repermalink();

switch ( $view ) :
case 'no-replies' :
	add_filter( 'get_latest_topics_where', 'no_replies' );
	$topics = get_latest_topics( 0, $page );
	$view_count = bb_count_last_query();
	break;
case 'untagged' :
	add_filter( 'get_latest_topics_where', 'untagged' );
	add_filter( 'get_sticky_topics_where', 'untagged' );
	$topics = get_latest_topics( 0, $page );
	$view_count  = bb_count_last_query();
	$stickies = get_sticky_topics( 0, $page );
	$view_count = max($view_count, bb_count_last_query());
	break;	
default :
	do_action( 'bb_custom_view', $view, $page );
endswitch;

do_action( 'bb_view.php', '' );
if (file_exists( BBPATH . 'my-templates/view.php' ))
	require( BBPATH . 'my-templates/view.php' );
else	require( BBPATH . 'bb-templates/view.php' );
?>
