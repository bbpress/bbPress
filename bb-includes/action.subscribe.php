<?php

if ( !isset( $_GET['doit'] ) || 'bb-subscribe' != $_GET['doit'] ) // sanity check
	die;

if ( !isset( $_GET['topic_id'] ) )
	die( 'Missing topic ID' );

bb_auth( 'logged_in' );

$topic_id = (int) $_GET['topic_id'];

$topic = get_topic ( $topic_id );
if ( !$topic )
	bb_die(__('Topic not found.'));

bb_check_admin_referer( 'toggle-subscribe_' . $topic_id );

// Okay, we should be covered now

if ( 'add' == $_GET['and'] ) {
	$tt_ids = $wp_taxonomy_object->set_object_terms( $bb_current_user->ID, 'topic-' . $topic->topic_id, 'bb_subscribe', array( 'append' => true, 'user_id' => $bb_current_user->ID ) );
} elseif ( 'remove' == $_GET['and'] ) {
	// I hate this with the passion of a thousand suns
	$term_id = $bbdb->get_var( "SELECT term_id FROM $bbdb->terms WHERE slug = 'topic-$topic->topic_id'" );
	$term_taxonomy_id = $bbdb->get_var( "SELECT term_taxonomy_id FROM $bbdb->term_taxonomy WHERE term_id = $term_id AND taxonomy = 'bb_subscribe'" );
	$bbdb->query( "DELETE FROM $bbdb->term_relationships WHERE object_id = $bb_current_user->ID AND term_taxonomy_id = $term_taxonomy_id" );
	$bbdb->query( "DELETE FROM $bbdb->term_taxonomy WHERE term_id = $term_id AND taxonomy = 'bb_subscribe'" );
}

wp_redirect( get_topic_link( $topic_id, 0 ) );

exit;