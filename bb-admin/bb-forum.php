<?php
require_once('admin.php');

if ( !bb_current_user_can('manage_forums') )
	bb_die(__("You don't have the authority to mess with the forums."));

if ( !isset($_POST['action']) )
	wp_redirect( bb_get_option( 'uri' ) . 'bb-admin/content-forums.php' );

$sent_from = wp_get_referer();

switch ( $_POST['action'] ) :
case 'add' :
	if ( !isset($_POST['forum']) || '' === $_POST['forum'] )
		bb_die(__('Bad forum name.  Go back and try again.'));

	bb_check_admin_referer( 'add-forum' );

	$forum_name = $_POST['forum'];
	$forum_desc = $_POST['forum-desc'];
	$forum_order = ( '' === $_POST['forum-order'] ) ? 0 : (int) $_POST['forum-order'];
	if ( false !== bb_new_forum( $forum_name, $forum_desc, $forum_order ) ) :
		wp_redirect( $sent_from );
		exit;
	else :
		bb_die(__('The forum was not added'));
	endif;
	break;
case 'update' :
	bb_check_admin_referer( 'update-forums' );

	if ( !$forums = get_forums() )
		bb_die(__('No forums to update!'));
	foreach ( $forums as $forum ) :
		if ( isset($_POST['name-' . $forum->forum_id]) && '' !== $_POST['name-' . $forum->forum_id] )
			bb_update_forum( $forum->forum_id, $_POST['name-' . $forum->forum_id], $_POST['desc-' . $forum->forum_id], $_POST['order-' . $forum->forum_id]);
	endforeach;
	wp_redirect( $sent_from );
	exit;
	break;
case 'delete' :
	bb_check_admin_referer( 'delete-forums' );

	$forum_id = (int) $_POST['forum_id'];
	$move_topics_forum = (int) $_POST['move_topics_forum'];

	if ( !bb_current_user_can( 'delete_forum', $forum_id ) )
		bb_die(__("You don't have the authority to kill off the forums."));

	if ( isset($_POST['move_topics']) && $_POST['move_topics'] != 'delete' )
		bb_move_forum_topics( $forum_id, $move_topics_forum );

	if ( !bb_delete_forum( $forum_id ) )
		bb_die( __('Error occured while trying to delete forum') );

	foreach ( array('action', 'id') as $arg )
		$sent_from = remove_query_arg( $arg, $sent_from );
	wp_redirect( add_query_arg( 'message', 'deleted', $sent_from ) );
	exit;
	break;
endswitch;
?>
