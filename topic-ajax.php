<?php
require('bb-config.php');

if ( !$bb_current_user )
	die('-1');

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
	$topic_id = (int) @$_POST['id' ];
	$tag      =       @$_POST['tag'];

	$topic = get_topic ( $topic_id );
	if ( !$topic )
		die('0');

	$tag = rawurldecode($tag);
	if ( add_topic_tag( $topic_id, $tag ) ) {
		$new_tag = get_tag( $ajax_results['tag_id'] );
		header('Content-type: text/xml');
		$new_tag->raw_tag = bb_specialchars($new_tag->raw_tag);
		die("<tag><id>$new_tag->tag_id</id><user>{$ajax_results['user_id']}</user><raw>$new_tag->raw_tag</raw><cooked>$new_tag->tag</cooked></tag>");
	} else {
		die('0');
	}
	break;

case 'tag-remove' :
	bb_add_action('bb_tag_removed', 'grab_results');
	$tag_id   = (int) @$_POST['tag'];
	$user_id  = (int) @$_POST['user'];
	$topic_id = (int) @$_POST['topic' ];

	$tag   = get_tag( $tag_id );
	$user  = bb_get_user( $user_id );
	$topic = get_topic ( $topic_id );
	if ( !$tag || !$topic )
		die('0');
	if ( remove_topic_tag( $tag_id, $user_id, $topic_id ) ) {
		header('Content-type: text/xml');
		die("<tag><id>{$ajax_results['tag_id']}</id><user>{$ajax_results['user_id']}</user></tag>");
	} else {
		die('0');
	}
	break;
endswitch;
?>
