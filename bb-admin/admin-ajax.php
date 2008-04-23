<?php
require_once('../bb-load.php');

if ( !class_exists( 'WP_Ajax_Response' ) )
	require( BACKPRESS_PATH . 'class.wp-ajax-response.php' );

require_once(BB_PATH . 'bb-admin/admin-functions.php');

if ( !$bb_current_id = bb_get_current_user_info( 'id' ) )
	die('-1');

define('DOING_AJAX', true);

function bb_grab_results() {
	global $ajax_results;
	$ajax_results = @ unserialize(func_get_arg(0));
	if ( false === $ajax_results )
		$ajax_results = func_get_args();
	return;
}

$id = (int) @$_POST['id'];

switch ( $action = $_POST['action'] ) :
case 'add-tag' : // $id is topic_id
	if ( !bb_current_user_can('edit_tag_by_on', $bb_current_id, $id) )
		die('-1');

	bb_check_ajax_referer( "add-tag_$id" );

	global $tag, $topic;
	add_action('bb_tag_added', 'bb_grab_results', 10, 3);
	add_action('bb_already_tagged', 'bb_grab_results', 10, 3);
	$tag_name = @$_POST['tag'];
	$tag_name = stripslashes( $tag_name );

	$topic = get_topic( $id );
	if ( !$topic )
		die('0');

	$tag_name = rawurldecode($tag_name);
	$x = new WP_Ajax_Response();
	foreach ( bb_add_topic_tags( $id, $tag_name ) as $tag_id ) {
		if ( !is_numeric($tag_id) || !$tag = bb_get_tag( $tag_id, bb_get_current_user_info( 'id' ), $topic->topic_id ) )
			if ( !$tag = bb_get_tag( $tag_id ) )
				continue;
		$tag_id_val = $tag->tag_id . '_' . $tag->user_id;
		$tag->raw_tag = attribute_escape( $tag->raw_tag );
		$x->add( array(
			'what' => 'tag',
			'id' => $tag_id_val,
			'data' => "<li id='tag-$tag_id_val'><a href='" . bb_get_tag_link() . "' rel='tag'>$tag->raw_tag</a> " . bb_get_tag_remove_link() . '</li>' 
		) );
	}
	$x->send();
	break;

case 'delete-tag' :
	list($tag_id, $user_id) = explode('_', $_POST['id']);
	$tag_id   = (int) $tag_id;
	$user_id  = (int) $user_id;
	$topic_id = (int) $_POST['topic_id'];

	if ( !bb_current_user_can('edit_tag_by_on', $user_id, $topic_id) )
		die('-1');

	bb_check_ajax_referer( "remove-tag_$tag_id|$topic_id" );

	add_action('bb_rpe_tag_removed', 'bb_grab_results', 10, 3);

	$tag   = bb_get_tag( $tag_id );
	$user  = bb_get_user( $user_id );
	$topic = get_topic ( $topic_id );
	if ( !$tag || !$topic )
		die('0');
	if ( bb_remove_topic_tag( $tag_id, $user_id, $topic_id ) )
		die('1');
	break;

case 'dim-favorite' :
	$topic_id = (int) @$_POST['topic_id'];
	$user_id  = (int) @$_POST['user_id'];

	$topic = get_topic( $topic_id );
	$user = bb_get_user( $user_id );
	if ( !$topic || !$user )
		die('0');

	if ( !bb_current_user_can( 'edit_favorites_of', $user->ID ) )
		die('-1');

	bb_check_ajax_referer( "toggle-favorite_$topic_id" );

	$is_fav = is_user_favorite( $user_id, $topic_id );

	if ( 1 == $is_fav ) {
		if ( bb_remove_user_favorite( $user_id, $topic_id ) )
			die('1');
	} elseif ( false === $is_fav ) {
		if ( bb_add_user_favorite( $user_id, $topic_id ) )
			die('1');
	}
	break;

case 'delete-post' : // $id is post_id
	if ( !bb_current_user_can( 'delete_post', $id ) )
		die('-1');

	bb_check_ajax_referer( "delete-post_$id" );

	$page = (int) $_POST['page'];
	$last_mod = (int) $_POST['last_mod'];

	$bb_post = bb_get_post( $id );

	if ( !$bb_post )
		die('0');

	$topic = get_topic( $bb_post->topic_id );

	if ( bb_delete_post( $id, 1 ) )
		die('1');
	break;
/*
case 'add-post' : // Can put last_modified stuff back in later
	bb_check_ajax_referer( $action );
	$error = false;
	$post_id = 0;
	$topic_id = (int) $_POST['topic_id'];
	$last_mod = (int) $_POST['last_mod'];
	if ( !$post_content = trim($_POST['post_content']) )
		$error = new WP_Error( 'no-content', __('You need to actually submit some content!') );
	if ( !bb_current_user_can( 'write_post', $topic_id ) )
		die('-1');
	if ( !$topic = get_topic( $topic_id ) )
		die('0');
	if ( !topic_is_open( $topic_id ) )
		$error = new WP_Error( 'topic-closed', __('This topic is closed.') );
	if ( $throttle_time = bb_get_option( 'throttle_time' ) )
		if ( isset($bb_current_user->data->last_posted) && time() < $bb_current_user->data->last_posted + $throttle_time && !bb_current_user_can('throttle') )
			$error = new WP_Error( 'throttle-limit', sprintf( __('Slow down!  You can only post every %d seconds.'), $throttle_time );

	if ( !$error ) :
		if ( !$post_id = bb_new_post( $topic_id, rawurldecode($_POST['post_content']) ) )
			die('0');

		$bb_post = bb_get_post( $post_id );

		$new_page = get_page_number( $bb_post->post_position );

		ob_start();
			echo "<li id='post-$post_id'>";
			bb_post_template();
			echo '</li>';
		$data = ob_get_contents();
		ob_end_clean();
	endif;
	$x = new WP_Ajax_Response( array(
		'what' => 'post',
		'id' => $post_id,
		'data' => is_wp_error($error) ? $error : $data
	) );
	$x->send();
	break;
*/
case 'add-forum' :
	if ( !bb_current_user_can( 'manage_forums' ) )
		die('-1');

	bb_check_ajax_referer( $action );

	if ( !$forum_id = bb_new_forum( $_POST ) )
		die('0');

	global $forums_count;
	$forums_count = 2; // Hack

	$x = new WP_Ajax_Response( array(
		'what' => 'forum',
		'id' => $forum_id,
		'data' => bb_forum_row( $forum_id, false, true )
	) );
	$x->send();
	break;

case 'order-forums' :
	if ( !bb_current_user_can( 'manage_forums' ) )
		die('-1');

	bb_check_ajax_referer( $action );

	if ( !is_array($_POST['order']) )
		die('0');

	global $bbdb;

	$forums = array();

	get_forums(); // cache

	foreach ( $_POST['order'] as $pos => $forum_id ) :
		$forum = $bbdb->escape_deep( get_object_vars( get_forum( $forum_id ) ) );
		$forum['forum_order'] = $pos;
		$forums[(int) $forum_id] = $forum;
	endforeach;

	foreach ( $_POST['root'] as $root => $ids )
		foreach ( $ids as $forum_id )
			$forums[(int) $forum_id]['forum_parent'] = (int) $root;

	foreach ( $forums as $forum )
		bb_update_forum( $forum );

	die('1');
	break;

default :
	do_action( 'bb_ajax_' . $_POST['action'] );
	break;
endswitch;

die('0');
?>
