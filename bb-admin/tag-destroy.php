<?php
require('../bb-config.php');

nocache_headers();

if ( $current_user->user_type < 2 )
	die('You need to be logged in as a developer to destroy a tag.');

$tag_id = (int) $_POST['id' ];

$old_tag = get_tag( $tag_id );
if ( !$old_tag )
	die('Tag not found.');

if ( $destroyed = destroy_tag( $tag_id ) )
	header('Location: ' . $bb->path );
else
	die("Something odd happened when attempting to destroy that tag.  Error code: $destroyed.  <a href=\"" . $_SERVER['HTTP_REFERER'] . '">Try Again</a>');
?>
<?php
require('../bb-config.php');

nocache_headers();

if ( $current_user->user_type < 2 )
	die('You need to be logged in as a developer to destroy a tag.');

$tag_id = (int) $_POST['id' ];

$old_tag = get_tag( $tag_id );
if ( !$old_tag )
	die('Tag not found.');

if ( $destroyed = destroy_tag( $tag_id ) )
	header('Location: ' . $bb->path );
else
	die("Something odd happened when attempting to destroy that tag.  Error code: $destroyed.  <a href=\"" . $_SERVER['HTTP_REFERER'] . '">Try Again</a>');
?>
<?php
require('../bb-config.php');

nocache_headers();

if ( $current_user->user_type < 2 )
	die('You need to be logged in as a developer to destroy a tag.');

$tag_id = (int) $_POST['id' ];

$old_tag = get_tag( $tag_id );
if ( !$old_tag )
	die('Tag not found.');

if ( $destroyed = destroy_tag( $tag_id ) )
	header('Location: ' . $bb->path );
else
	die("Something odd happened when attempting to destroy that tag.  Error code: $destroyed.  <a href=\"" . $_SERVER['HTTP_REFERER'] . '">Try Again</a>');
?>
