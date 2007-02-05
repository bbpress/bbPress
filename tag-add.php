<?php
require('./bb-load.php');

bb_auth();

if ( !bb_is_user_logged_in() )
	bb_die(__('You need to be logged in to add a tag.'));

$topic_id = (int) @$_POST['id' ];
$tag      =       @$_POST['tag'];

bb_check_admin_referer( 'add-tag_' . $topic_id );

$topic = get_topic ( $topic_id );
if ( !$topic )
	bb_die(__('Topic not found.'));

if ( add_topic_tag( $topic_id, $tag ) )
	wp_redirect( get_topic_link( $topic_id ) );
else
	bb_die(__('The tag was not added.  Either the tag name was invalid or the topic is closed.'));
?>
