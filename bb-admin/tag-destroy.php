<?php
require('../bb-config.php');

nocache_headers();

if ( $current_user->user_type < 2 )
	die('You need to be logged in as a developer to destroy a tag.');

$tag_id = (int) $_POST['id' ];

$old_tag = get_tag( $tag_id );
if ( !$old_tag )
	die('Tag not found.');

if ( $destroyed = destroy_tag( $tag_id ) ) {
	echo 'Rows deleted from tags table: ' . $destroyed['tags'] . "<br />\n";
	echo 'Rows deleted from tagged table: ' . $destroyed['tagged'] . "<br />\n";
	echo '<a href="'. $bb->path . '">Home</a>';
} else {
	var_dump($destroyed);
	die("<br />Something odd happened when attempting to destroy that tag.  See the above Error codes.<br />\n<a href=\"" . $_SERVER['HTTP_REFERER'] . '">Try Again?</a>');
}
?>
