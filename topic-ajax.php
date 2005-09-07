<?php
require('bb-config.php');

if ( !$bb_current_user )
	die('-1');
define('DOING_AJAX', true);

function grab_results() {
	global $ajax_results;
	$ajax_results = @ unserialize(func_get_arg(0));
	if ( false === $ajax_results )
		$ajax_results = func_get_arg(0);
	return;
}

function get_out_now() { exit; }
bb_add_action('bb_shutdown', 'get_out_now', -1);

switch ( $_POST['action'] ) :
case 'tag-add' :
	bb_add_action('bb_tag_added', 'grab_results');
	bb_add_action('bb_already_tagged', 'grab_results');
	$topic_id = (int) @$_POST['id'];
	$tag      =       @$_POST['tag'];
	if ( !bb_current_user_can('edit_tag_by_on', $bb_current_user->ID, $topic_id) )
		die('-1');

	$topic = get_topic ( $topic_id );
	if ( !$topic )
		die('0');

	$tag = rawurldecode($tag);
	if ( add_topic_tag( $topic_id, $tag ) ) {
		$new_tag = get_tag( $ajax_results['tag_id'] );
		header('Content-type: text/xml');
		$new_tag->raw_tag = htmlspecialchars(bb_specialchars($new_tag->raw_tag));
		die("<?xml version='1.0' standalone='yes'?><tag><id>$new_tag->tag_id</id><user>{$ajax_results['user_id']}</user><raw>$new_tag->raw_tag</raw><cooked>$new_tag->tag</cooked></tag>");
	} else {
		die('0');
	}
	break;

case 'tag-remove' :
	bb_add_action('bb_tag_removed', 'grab_results');
	$tag_id   = (int) @$_POST['tag'];
	$user_id  = (int) @$_POST['user'];
	$topic_id = (int) @$_POST['topic'];

	if ( !bb_current_user_can('edit_tag_by_on', $user_id, $topic_id) )
		die('-1');

	$tag   = get_tag( $tag_id );
	$user  = bb_get_user( $user_id );
	$topic = get_topic ( $topic_id );
	if ( !$tag || !$topic )
		die('0');
	if ( remove_topic_tag( $tag_id, $user_id, $topic_id ) ) {
		header('Content-type: text/xml');
		die("<?xml version='1.0' standalone='yes'?><tag><id>{$ajax_results['tag_id']}</id><user>{$ajax_results['user_id']}</user></tag>");
	} else {
		die('0');
	}
	break;
case 'favorite-add' :
	$topic_id = (int) @$_POST['topic_id'];
	$user_id  = (int) @$_POST['user_id'];

	if ( !bb_current_user_can('edit_favorites') )
		die('-1');

	$topic = get_topic( $topic_id );
	$user = bb_get_user( $user_id );
	if ( !$topic || !$user )
		die('0');

	if ( bb_add_user_favorite( $user_id, $topic_id ) )
		die('1');
	else	die('0');
	break;

case 'favorite-remove' :
	$topic_id = (int) @$_POST['topic_id'];
	$user_id  = (int) @$_POST['user_id'];

	if ( !bb_current_user_can('edit_favorites') )
		die('-1');

	$topic = get_topic( $topic_id );
	$user = bb_get_user( $user_id );
	if ( !$topic || !$user )
		die('0');

	if ( bb_remove_user_favorite( $user_id, $topic_id ) )
		die('1');
	else	die('0');
	break;

case 'topic-resolve' :
	$topic_id = (int) @$_POST['id'];
	$resolved = @$_POST['resolved'];

	if ( !bb_current_user_can( 'edit_topic', $topic_id ) )
		die('-1');

	$topic = get_topic( $topic_id );
	if ( !$topic )
		die('0');

	if ( bb_resolve_topic( $topic_id, $resolved ) )
		die('1');
	else	die('0');
	break;

case 'post-delete' :
	$post_id = (int) $_POST['id'];
	$page = (int) $_POST['page'];
	$last_mod = (int) $_POST['last_mod'];
	if ( !bb_current_user_can('manage_posts') )
		die('-1');

	$bb_post = bb_get_post ( $post_id );

	if ( !$bb_post )
		die('0');

	$topic = get_topic( $bb_post->topic_id );

	if ( bb_delete_post( $post_id, 1 ) ) :
		if ( $last_mod < strtotime($topic->topic_time . ' +0000') ) :
			bb_ajax_thread( $topic->topic_id, $page );
		else :
			die('1');
		endif;
	else :	die('0');
	endif;
	break;

case 'post-add' :
	$topic_id = (int) $_POST['topic_id'];
	$page = (int) $_POST['page'];
	$last_mod = (int) $_POST['last_mod'];
	$need_thread = false;
	if ( !bb_current_user_can('write_posts') )
		die('-1');
	if ( !$topic = get_topic( $topic_id ) )
		die('0');
	if ( !topic_is_open( $topic_id ) )
		die('-2');
	if ( isset($bb_current_user->data->last_posted) && time() < $bb_current_user->data->last_posted + 30 && !bb_current_user_can('throttle') )
		die('-3');

	if ( $last_mod < strtotime($topic->topic_time . ' +0000') )
		$need_thread = true;

	if ( !$post_id = bb_new_post( $topic_id, rawurldecode($_POST['post_content']) ) )
		die('0');

	$bb_post = bb_get_post( $post_id );

	$new_page = get_page_number( $bb_post->post_position );

	if ( !$need_thread ) :
		header('Content-type: text/xml');
		echo "<?xml version='1.0' standalone='yes'?><post><id>$post_id</id><templated><![CDATA[";
		bb_post_template();
		echo ']]></templated>';
		if ( $page != $new_page ) echo "<link><![CDATA[Your post has been posted to the <a href='" . bb_specialchars( get_post_link( $bb_post->post_id ) ) . "'>next page</a> in this topic.]]></link>";
		echo '</post>';
		exit;
	else :
		bb_ajax_thread( $bb_post->topic_id, $page, $new_page );
	endif;
	break;

endswitch;

function bb_ajax_thread( $topic_id, $page ) {
	global $bb_post;
	$topic_id = (int) $topic_id;
	$page = (int) $page;
	$new_page = $page;

	if ( !$thread = get_thread( $topic_id, $page ) )
		die('0');

	if ( 2 < func_num_args() )
		$new_page = func_get_arg(2);

	header('Content-type: text/xml');
	echo "<?xml version='1.0' standalone='yes'?><thread><id>$topic_id</id><page>$page</page>";

	foreach ( $thread as $bb_post ) :
		echo "<post><id>$bb_post->post_id</id><templated><![CDATA[";
		bb_post_template();
		echo ']]></templated></post>';
	endforeach;

	if ( $new_page != $page ) echo "<link><![CDATA[Your post has been posted to <a href='" . get_topic_link( $topic_id, $new_page ) . "'>page $new_page</a> in this topic.]]></link>";
	echo '</thread>';
	exit;
}
?>
