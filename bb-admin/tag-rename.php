<?php
require('../bb-config.php');

nocache_headers();

if ( $current_user->user_type < 2 )
	die('You need to be logged in as a developer to rename a tag.');

$tag_id = (int) $_POST['id' ];
$tag    =       $_POST['tag'];

$old_tag = get_tag( $tag_id );
if ( !$old_tag )
	die('Tag not found.');

if ( $tag = rename_tag( $tag_id, $tag ) )
	header('Location: ' . get_tag_link() );
else
	die('There already exists a tag by that name or the name is invalid.  <a href="' . $_SERVER['HTTP_REFERER'] . '">Try Again</a>');
?>
