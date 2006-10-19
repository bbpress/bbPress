<?php
require('./bb-load.php');

bb_auth();

if ( !$bb_current_user )
	bb_die(__('You need to be logged in to add a tag.'));

$topic_id = (int) @$_POST['id' ];
$resolved =       @$_POST['resolved'];

$topic = get_topic ( $topic_id );
if ( !$topic )
	bb_die(__('Topic not found.'));

if ( !bb_current_user_can( 'edit_topic', $topic_id ) )
	bb_die(__("You must be either the original poster or a moderator to change a topic's resolution status."));

bb_check_admin_referer( 'resolve-topic_' . $topic_id );

if ( bb_resolve_topic( $topic_id, $resolved ) )
	wp_redirect( get_topic_link( $topic_id ) );
else
	bb_die(__('Invalid resolution status.'));
?>
