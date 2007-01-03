<?php
require('admin.php');

nocache_headers();

if ( !bb_current_user_can('manage_tags') )
	bb_die(__('You are not allowed to manage tags.'));

$tag_id = (int) $_POST['id' ];
$tag    =       $_POST['tag'];

bb_check_admin_referer( 'rename-tag_' . $tag_id );

$old_tag = get_tag( $tag_id );
if ( !$old_tag )
	bb_die(__('Tag not found.'));

if ( $tag = rename_tag( $tag_id, $tag ) )
	wp_redirect( get_tag_link() );
else
	die(printf(__('There already exists a tag by that name or the name is invalid. <a href="%s">Try Again</a>'), wp_get_referer()));
?>
