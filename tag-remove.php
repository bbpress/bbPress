<?php
require('./bb-load.php');

bb_auth();

$tag_id = (int) @$_GET['tag'];
$user_id = (int) @$_GET['user'];
$topic_id = (int) @$_GET['topic'];

bb_check_admin_referer( 'remove-tag_' . $tag_id . '|' . $topic_id );

$tag    =  get_tag ( $tag_id );
$topic	=  get_topic ( $topic_id );
$user	=  bb_get_user( $user_id );

if ( !$tag || !$topic )
	die(__('The dude does not abide.'));

if ( remove_topic_tag( $tag_id, $user_id, $topic_id ) )
	header( 'Location: ' . $_SERVER['HTTP_REFERER'] );
else
	die(__('The tag was not removed.  You cannot remove a tag from a closed topic.'));
exit;
?>
