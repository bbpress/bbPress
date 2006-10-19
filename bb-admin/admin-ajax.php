<?php
require_once('../bb-load.php');

bb_check_ajax_referer();

if ( !$bb_current_user )
	die('-1');
define('DOING_AJAX', true);

function grab_results() {
	global $ajax_results;
	$ajax_results = @ unserialize(func_get_arg(0));
	if ( false === $ajax_results )
		$ajax_results = func_get_args();
	return;
}

function get_out_now() { exit; }
add_action('bb_shutdown', 'get_out_now', -1);

switch ( $_POST['action'] ) :
case 'add-tag' :
	global $tag, $topic;
	add_action('bb_tag_added', 'grab_results', 10, 3);
	add_action('bb_already_tagged', 'grab_results', 10, 3);
	$topic_id = (int) @$_POST['id'];
	$tag_name =       @$_POST['tag'];
	if ( !bb_current_user_can('edit_tag_by_on', $bb_current_user->ID, $topic_id) )
		die('-1');

	$topic = get_topic( $topic_id );
	if ( !$topic )
		die('0');

	$tag_name = rawurldecode($tag_name);
	if ( add_topic_tag( $topic_id, $tag_name ) ) {
		$tag = get_tag( $ajax_results[0] );
		$tag_id_val = $tag->tag_id . '_' . $ajax_results[1];
		$tag->raw_tag = wp_specialchars($tag->raw_tag, 1);
		$tag->user_id = $bb_current_user->ID;
		$tag->topic_id = $topic_id;
		$x = new WP_Ajax_Response( array(
			'what' => 'tag',
			'id' => $tag_id_val,
			'data' => "<li id='tag-$tag_id_val'><a href='" . get_tag_link() . "' rel='tag'>$tag->raw_tag</a> " . get_tag_remove_link() . '</li>' 
		) );
		$x->send();
	}
	break;

case 'delete-tag' :
	add_action('bb_rpe_tag_removed', 'grab_results', 10, 3);
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
	if ( remove_topic_tag( $tag_id, $user_id, $topic_id ) )
		die('1');
	break;

case 'dim-favorite' :
	$topic_id = (int) @$_POST['topic_id'];
	$user_id  = (int) @$_POST['user_id'];

	if ( !bb_current_user_can('edit_favorites') )
		die('-1');

	$topic = get_topic( $topic_id );
	$user = bb_get_user( $user_id );
	if ( !$topic || !$user )
		die('0');

	$is_fav = is_user_favorite( $user_id, $topic_id );

	if ( 1 == $is_fav ) {
		if ( bb_remove_user_favorite( $user_id, $topic_id ) )
			die('1');
	} elseif ( 0 === $is_fav ) {
		if ( bb_add_user_favorite( $user_id, $topic_id ) )
			die('1');
	}
	break;

case 'update-resolution' :
	$topic_id = (int) @$_POST['topic_id'];
	$resolved = @$_POST['resolved'];

	if ( !bb_current_user_can( 'edit_topic', $topic_id ) )
		die('-1');

	$topic = get_topic( $topic_id );
	if ( !$topic )
		die('0');

	if ( bb_resolve_topic( $topic_id, $resolved ) ) {
		$topic->topic_resolved = $resolved;
		ob_start();
			echo '<li id="resolution-flipper">' . __('This topic is') . ' ';
			topic_resolved();
			echo '</li>';
		$data = ob_get_contents();
		ob_end_clean();
		$x = new WP_Ajax_Response( array(
			'what' => 'resolution',
			'id' => 'flipper',
			'data' => $data
		) );
		$x->send();
	}
	break;

case 'delete-post' :
	$post_id = (int) $_POST['id'];
	$page = (int) $_POST['page'];
	$last_mod = (int) $_POST['last_mod'];
	if ( !bb_current_user_can('manage_posts') )
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

endswitch;

die('0');
?>
