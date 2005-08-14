<?php
require('bb-config.php');

nocache_headers();

if ( !$current_user )
	die('You need to be logged in to add a tag.');

$topic_id = (int) @$_POST['id' ];
$resolved =       @$_POST['resolved'];

$topic = get_topic ( $topic_id );
if ( !$topic )
	die('Topic not found.');

if ( !current_user_can( 'edit_topic', $topic_id ) )
	die('You must be either the original poster or a moderator to change a topic\'s resolution status.');

if ( bb_resolve_topic( $topic_id, $resolved ) )
	header('Location: ' . get_topic_link( $topic_id ) );
else
	die('That is not the sound of one hand clapping.');
?>
