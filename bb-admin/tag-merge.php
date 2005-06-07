<?php
require('../bb-config.php');

nocache_headers();

if ( $current_user->user_type < 2 )
	die('You need to be logged in as a developer to merge tags.');

$old_id = (int) $_POST['id' ];
$tag = $_POST['tag'];
if ( ! $tag = get_tag_by_name( $tag ) )
	die('Tag specified not found.');

if ( ! get_tag( $old_id ) )
	die('Tag to be merged not found.');

if ( $merged = merge_tags( $old_id, $tag->tag_id ) ) {
	echo 'Number of topics from which the old tag was removed: ' . $merged['old_count'] . "<br />\n";
	echo 'Number of topics to which the new tag was added: ' . $merged['diff_count'] . "<br />\n";
	echo 'Number of rows deleted from tags table: ' . $merged['destroyed']['tags'] ."<br />\n";
	echo '<a href="' . get_tag_link() . '">New Tag</a>';
} else {
	die("Something odd happened when attempting to merge those tags.<br />\n<a href=\"" . $_SERVER['HTTP_REFERER'] . '">Try Again?</a>');
}
?>
