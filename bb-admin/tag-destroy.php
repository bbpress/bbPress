<?php
require('admin.php');

nocache_headers();
if ( !bb_current_user_can('manage_tags') )
	die(__('You are not allowed to manage tags.'));

$tag_id = (int) $_POST['id' ];

$old_tag = get_tag( $tag_id );
if ( !$old_tag )
	die(__('Tag not found.'));

if ( $destroyed = destroy_tag( $tag_id ) ) {
	printf(__("Rows deleted from tags table: %d <br />\n"), $destroyed['tags']);
	printf(__("Rows deleted from tagged table: %d <br />\n"), $destroyed['tagged']);
	printf(__('<a href="%s">Home</a>'), $bb->path);
} else {
   die(printf(__("Something odd happened when attempting to destroy that tag.<br />\n<a href=\"%s\">Try Again?</a>"), $_SERVER['HTTP_REFERER']));
}
?>
