<?php
require('../bb-load.php');

nocache_headers();

if ( !bb_current_user_can('manage_tags') )
	die(__('You are not allowed to manage tags.'));

$tag_id = (int) $_POST['id' ];
$tag    =       $_POST['tag'];

$old_tag = get_tag( $tag_id );
if ( !$old_tag )
	die(__('Tag not found.'));

if ( $tag = rename_tag( $tag_id, $tag ) )
	header('Location: ' . get_tag_link() );
else
	die(printf(__('There already exists a tag by that name or the name is invalid. <a href="%s">Try Again</a>'), $_SERVER['HTTP_REFERER']));
?>
