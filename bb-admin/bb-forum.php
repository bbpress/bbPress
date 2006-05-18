<?php
require_once('../bb-load.php');

if ( !bb_current_user_can('manage_forums') )
	die("You don't have the authority to mess with the forums.");

if ( !isset($_POST['action']) )
	die('What am I supposed to do with that?');

$sent_from = $_SERVER['HTTP_REFERER'];

switch ( $_POST['action'] ) :
case 'add' :
	if ( !isset($_POST['forum']) || '' === $_POST['forum'] )
		die('Bad forum name.  Go back and try again.');
	$forum_name = $_POST['forum'];
	$forum_desc = $_POST['forum-desc'];
	$forum_order = ( '' === $_POST['forum-order'] ) ? 0 : (int) $_POST['forum-order'];
	if ( false !== bb_new_forum( $forum_name, $forum_desc, $forum_order ) ) :
		header("Location: $sent_from");
		exit;
	else :
		die('The forum was not added');
	endif;
	break;
case 'update' :
	if ( !$forums = get_forums() )
		die('No forums to update!');
	foreach ( $forums as $forum ) :
		if ( isset($_POST['name-' . $forum->forum_id]) && '' !== $_POST['name-' . $forum->forum_id] )
			bb_update_forum( $forum->forum_id, $_POST['name-' . $forum->forum_id], $_POST['desc-' . $forum->forum_id], $_POST['order-' . $forum->forum_id]);
	endforeach;
	header("Location: $sent_from");
	exit;
	break;
endswitch;
?>
