<?php
require('admin.php');

nocache_headers();
if ( !bb_current_user_can('manage_tags') )
	bb_die(__('You are not allowed to manage tags.'));

$tag_id = (int) $_POST['id' ];

bb_check_admin_referer( 'destroy-tag_' . $tag_id );

$old_tag = bb_get_tag( $tag_id );
if ( !$old_tag )
	bb_die(__('Tag not found.'));

if ( $destroyed = bb_destroy_tag( $tag_id ) ) {
	printf(__("Rows deleted from tags table: %d <br />\n"), $destroyed['tags']);
	printf(__("Rows deleted from tagged table: %d <br />\n"), $destroyed['tagged']);
	printf(__('<a href="%s">Home</a>'), bb_get_option( 'uri' ));
} else {
   die(printf(__("Something odd happened when attempting to destroy that tag.<br />\n<a href=\"%s\">Try Again?</a>"), wp_get_referer()));
}
?>
