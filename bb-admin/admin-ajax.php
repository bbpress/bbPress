<?php
require_once('../bb-load.php');
require_once(BBPATH . 'bb-admin/admin-functions.php');
bb_check_ajax_referer();

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

function bb_get_out_now() { exit; }
add_action('bb_shutdown', 'bb_get_out_now', -1);

switch ( $_POST['action'] ) :
case 'add-tag' :
	global $tag, $topic;
	add_action('bb_tag_added', 'bb_grab_results', 10, 3);
	add_action('bb_already_tagged', 'bb_grab_results', 10, 3);
	$topic_id = (int) @$_POST['id'];
	$tag_name =       @$_POST['tag'];
	if ( !bb_current_user_can('edit_tag_by_on', $bb_current_id, $topic_id) )
		die('-1');

	$topic = get_topic( $topic_id );
	if ( !$topic )
		die('0');

	$tag_name = rawurldecode($tag_name);
	$x = new WP_Ajax_Response();
	foreach ( add_topic_tags( $topic_id, $tag_name ) as $tag_id ) {
		if ( !is_numeric($tag_id) || !$tag = get_tag( $tag_id, bb_get_current_user_info( 'id' ), $topic->topic_id ) )
			continue;
		$tag_id_val = $tag->tag_id . '_' . $bb_current_id;
		$tag->raw_tag = attribute_escape( $tag->raw_tag );
		$x->add( array(
			'what' => 'tag',
			'id' => $tag_id_val,
			'data' => "<li id='tag-$tag_id_val'><a href='" . bb_get_tag_link() . "' rel='tag'>$tag->raw_tag</a> " . get_tag_remove_link() . '</li>' 
		) );
	}
	$x->send();
	break;

case 'delete-tag' :
	add_action('bb_rpe_tag_removed', 'bb_grab_results', 10, 3);
	list($tag_id, $user_id) = explode('_', $_POST['id']);
	$tag_id   = (int) $tag_id;
	$user_id  = (int) $user_id;
	$topic_id = (int) $_POST['topic_id'];

	if ( !bb_current_user_can('edit_tag_by_on', $user_id, $topic_id) )
		die('-1');

	$tag   = get_tag( $tag_id );
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

	$is_fav = is_user_favorite( $user_id, $topic_id );

	if ( 1 == $is_fav ) {
		if ( bb_remove_user_favorite( $user_id, $topic_id ) )
			die('1');
	} elseif ( 0 === $is_fav ) {
		if ( bb_add_user_favorite( $user_id, $topic_id ) )
			die('1');
	}
	break;

case 'delete-post' :
	$post_id = (int) $_POST['id'];
	$page = (int) $_POST['page'];
	$last_mod = (int) $_POST['last_mod'];

	if ( !bb_current_user_can( 'delete_post', $post_id ) )
		die('-1');

	$bb_post = bb_get_post ( $post_id );

	if ( !$bb_post )
		die('0');

	$topic = get_topic( $bb_post->topic_id );

	if ( bb_delete_post( $post_id, 1 ) )
		die('1');
	break;

case 'add-post' : // Can put last_modified stuff back in later
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
	if ( isset($bb_current_user->data->last_posted) && time() < $bb_current_user->data->last_posted + 30 && !bb_current_user_can('throttle') )
		$error = new WP_Error( 'throttle-limit', __('Slow down!  You can only post every 30 seconds.') );

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

case 'add-forum' :
	if ( !bb_current_user_can( 'manage_forums' ) )
		die('-1');

	if ( !$forum_id = bb_new_forum( $_POST ) )
		die('0');

	global $forums_count;
	$forums_count = 2; // Hack

	$x = new WP_Ajax_Response( array(
		'what' => 'forum',
		'id' => $forum_id,
		'data' => bb_forum_row( $forum_id, false )
	) );
	$x->send();
	break;

case 'order-forums' :
	if ( !bb_current_user_can( 'manage_forums' ) )
		die('-1');

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
	die('0');
	break;
endswitch;
?>
