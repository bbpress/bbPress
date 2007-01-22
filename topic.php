<?php
require_once('./bb-load.php');
$topic_id = 0;

if ( bb_current_user_can('browse_deleted') && 'all' == @$_GET['view'] ) {
	add_filter('get_topic_where', 'no_where');
	add_filter('get_thread_where', 'no_where');
	add_filter('get_thread_post_ids', 'no_where');
	add_filter('post_edit_uri', 'make_link_view_all');
}

bb_repermalink();

if ( !$topic )
	bb_die(__('Topic not found.'));

$bb_db_override = false;
do_action( 'bb_topic.php_pre_db', $topic_id );

if ( !$bb_db_override ) :
	$posts = get_thread( $topic_id, $page );
	$forum = get_forum ( $topic->forum_id );

	$tags  = get_topic_tags ( $topic_id );
	if ( $bb_current_user && $tags ) {
		$user_tags  = get_user_tags  ( $topic_id, $bb_current_user->ID );
		$other_tags = get_other_tags ( $topic_id, $bb_current_user->ID );
	} elseif ( is_array($tags) ) {
		$user_tags  = false;
		$other_tags = get_public_tags( $topic_id );
	} else {
		$user_tags  = false;
		$other_tags = false;
	}

	$list_start = ($page - 1) * bb_get_option('page_topics') + 1;

	post_author_cache($posts);
endif;

do_action( 'bb_topic.php', $topic_id );

bb_load_template( 'topic.php', array('bb_db_override', 'user_tags', 'other_tags', 'list_start') );

?>
