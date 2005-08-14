<?php
require_once('bb-config.php');

$topic_id = $page = 0;

if ( current_user_can('browse_deleted') && 'deleted' == @$_GET['view'] ) {
	bb_add_filter('get_topic_where', 'no_where');
	bb_add_filter('get_thread_where', 'no_where');
	bb_add_filter('get_thread_post_ids', 'no_where');
	bb_add_filter('post_edit_uri', 'make_link_deleted');
}

bb_repermalink();

if ( isset( $_GET['page'] ) )
	$page = (int) abs( $_GET['page'] );

if ( !$topic )
	die('Topic not found.');

$bb_db_override = false;
bb_do_action( 'bb_topic.php_pre_db', $topic_id );

if ( !$bb_db_override ) :
	$posts = get_thread( $topic_id, $page );
	$forum = get_forum ( $topic->forum_id );

	$tags  = get_topic_tags ( $topic_id );
	if ( $current_user && $tags ) {
		$user_tags  = get_user_tags  ( $topic_id, $current_user->ID );
		$other_tags = get_other_tags ( $topic_id, $current_user->ID );
	} elseif ( is_array($tags) ) {
		$user_tags  = false;
		$other_tags = get_public_tags( $topic_id );
	} else {
		$user_tags  = false;
		$other_tags = false;
	}

	$list_start = $page * bb_get_option('page_topics') + 1;

	post_author_cache($posts);
endif;

bb_do_action( 'bb_topic.php', $topic_id );

if (file_exists( BBPATH . 'my-templates/topic.php' ))
	require( BBPATH . 'my-templates/topic.php' );
else	require( BBPATH . 'bb-templates/topic.php' );
?>
