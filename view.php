<?php
require_once('bb-config.php');

bb_repermalink();

if ( isset($_GET['page']) )
	$page = (int) abs( $_GET['page'] );

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
case 'deleted' :
	if ( !current_user_can('browse_deleted') )
		die("Now how'd you get here?  And what did you think you'd being doing?"); //This should never happen.
	bb_add_filter( 'get_latest_topics_where', 'deleted_topics' );
	bb_add_filter( 'topic_link', 'make_link_deleted' );
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
