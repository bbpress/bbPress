<?php
require('../bb-config.php');

nocache_headers();

if ( !current_user_can('manage_tags') )
	die('You are not allowed to manage tags.');

$tag_id = (int) $_POST['id' ];

$old_tag = get_tag( $tag_id );
if ( !$old_tag )
	die('Tag not found.');

if ( $destroyed = destroy_tag( $tag_id ) ) {
	echo 'Rows deleted from tags table: ' . $destroyed['tags'] . "<br />\n";
	echo 'Rows deleted from tagged table: ' . $destroyed['tagged'] . "<br />\n";
	echo '<a href="'. $bb->path . '">Home</a>';
} else {
	die("Something odd happened when attempting to destroy that tag.<br />\n<a href=\"" . $_SERVER['HTTP_REFERER'] . '">Try Again?</a>');
}
?>
