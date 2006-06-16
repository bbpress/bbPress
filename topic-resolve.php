<?php
require('./bb-load.php');

nocache_headers();

if ( !$bb_current_user )
	die(__('You need to be logged in to add a tag.'));

$topic_id = (int) @$_POST['id' ];
$resolved =       @$_POST['resolved'];

$topic = get_topic ( $topic_id );
if ( !$topic )
	die(__('Topic not found.'));

if ( !bb_current_user_can( 'edit_topic', $topic_id ) )
	die(__("You must be either the original poster or a moderator to change a topic's resolution status."));

if ( bb_resolve_topic( $topic_id, $resolved ) )
	header('Location: ' . get_topic_link( $topic_id ) );
else
	die(__('That is not the sound of one hand clapping.'));
?>
