<?php
require('admin.php');
nocache_headers();

if ( !bb_current_user_can('manage_tags') )
	bb_die(__('You are not allowed to manage tags.'));

$old_id = (int) $_POST['id' ];
$tag = $_POST['tag'];

bb_check_admin_referer( 'merge-tag_' . $old_id );

if ( ! $tag = get_tag_by_name( $tag ) )
	bb_die(__('Tag specified not found.'));

if ( ! get_tag( $old_id ) )
	bb_die(__('Tag to be merged not found.'));

if ( $merged = merge_tags( $old_id, $tag->tag_id ) ) {
	printf(__("Number of topics from which the old tag was removed: %d <br />\n"),  $merged['old_count']);
    printf(__("Number of topics to which the new tag was added: %d <br />\n"),$merged['diff_count']);
	printf(__("Number of rows deleted from tags table:%d <br />\n"),$merged['destroyed']['tags']);
	printf(__('<a href="%s">New Tag</a>'), get_tag_link());
} else {
   die(printf(__("Something odd happened when attempting to merge those tags.<br />\n<a href=\"%s\">Try Again?</a>"), $_SERVER['HTTP_REFERER']));
}
?>
