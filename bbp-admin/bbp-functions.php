<?php

function bbp_admin_notice( $message, $class = false ) {
	if ( is_string( $message ) ) {
		$message = '<p>' . $message . '</p>';
		$class = $class ? $class : 'updated';
	} elseif ( is_wp_error( $message ) ) {
		$errors = $message->get_error_messages();

		switch ( count( $errors ) ) {
			case 0:
				return false;
				break;

			case 1:
				$message = '<p>' . $errors[0] . '</p>';
				break;

			default:
				$message = '<ul>' . "\n\t" . '<li>' . join( '</li>' . "\n\t" . '<li>', $errors ) . '</li>' . "\n" . '</ul>';
				break;
		}

		$class = $class ? $class : 'error';
	} else {
		return false;
	}

	$message = '<div id="message" class="' . esc_attr( $class ) . '">' . $message . '</div>';
	$message = str_replace( "'", "\'", $message );
	$lambda  = create_function( '', "echo '$message';" );

	add_action( 'bbp_admin_notices', $lambda );

	return $lambda;
}

function bbp_recount_list () {
	$recount_list = array(
		5  => array( 'topic-replies',         __( 'Count replies to every topic',                'bbpress' ) ),
		6  => array( 'topic-voices',          __( 'Count voices of every topic',                 'bbpress' ) ),
		10 => array( 'topic-deleted-replies', __( 'Count deleted replies on every topic',        'bbpress' ) ),
		15 => array( 'forums',                __( 'Count topics and replies in every forum',     'bbpress' ) ),
		20 => array( 'topics-replied',        __( 'Count topics to which each user has replied', 'bbpress' ) ),
		25 => array( 'topic-tag-count',       __( 'Count tags for every topic',                  'bbpress' ) ),
		30 => array( 'tags-tag-count',        __( 'Count topics for every tag',                  'bbpress' ) ),
		35 => array( 'tags-delete-empty',     __( 'Delete tags with no topics',                  'bbpress' ) ),
		40 => array( 'clean-favorites',       __( 'Remove deleted topics from user favorites',   'bbpress' ) )
	);

	ksort( $recount_list );
	return apply_filters( 'bbp_recount_list', $recount_list );
}

function bbp_recount_topic_replies() {
	global $wpdb;

	$statement = __( 'Counting the number of replies in each topic&hellip; %s', 'bbpress' );
	$result    = __( 'Failed!', 'bbpress' );

	return array( 0, sprintf( $statement, $result ) );
}

function bbp_recount_topic_voices() {
	global $wpdb;

	$statement = __( 'Counting the number of voices in each topic&hellip; %s', 'bbpress' );
	$result    = __( 'Failed!', 'bbpress' );

	return array( 0, sprintf( $statement, $result ) );
}

function bbp_recount_topic_trashed_replies() {
	global $wpdb;

	$statement = __( 'Counting the number of deleted replies in each topic&hellip; %s', 'bbpress' );
	$result    = __( 'Failed!', 'bbpress' );

	return array( 0, sprintf( $statement, $result ) );
}

function bbp_recount_forum_topics() {
	global $wpdb;

	$statement = __( 'Counting the number of topics in each forum&hellip; %s', 'bbpress' );
	$result    = __( 'Failed!', 'bbpress' );

	return array( 0, sprintf( $statement, $result ) );
}

function bbp_recount_forum_replies() {
	global $wpdb;

	$statement = __( 'Counting the number of replies in each forum&hellip; %s', 'bbpress' );
	$result    = __( 'Failed!', 'bbpress' );

	return array( 0, sprintf( $statement, $result ) );
}

function bbp_recount_user_topics_replied() {
	global $wpdb;

	$statement = __( 'Counting the number of topics to which each user has replied&hellip; %s', 'bbpress' );
	$result    = __( 'Failed!', 'bbpress' );

	return array( 0, sprintf( $statement, $result ) );
}

// This function bypasses the taxonomy API
function bbp_recount_topic_tags() {
	global $wpdb;

	$statement = __( 'Counting the number of topic tags in each topic&hellip; %s', 'bbpress' );
	$result    = __( 'Failed!', 'bbpress' );

	return array( 0, sprintf( $statement, $result ) );
}

// This function bypasses the taxonomy API
function bbp_recount_tag_topics() {
	global $wpdb;

	$statement = __( 'Counting the number of topics in each topic tag&hellip; %s', 'bbpress' );
	$result    = __( 'Failed!', 'bbpress' );

	return array( 0, sprintf( $statement, $result ) );
}

// This function bypasses the taxonomy API
function bbp_recount_tag_delete_empty() {
	global $wpdb;

	$statement = __( 'Deleting topic tags with no topics&hellip; %s', 'bbpress' );
	$result    = __( 'Failed!', 'bbpress' );

	return array( 0, sprintf( $statement, $result ) );
}

function bbp_recount_clean_favorites() {
	global $wpdb;

	$statement = __( 'Removing deleted topics from user favorites&hellip; %s', 'bbpress' );
	$result    = __( 'Failed!', 'bbpress' );

	return array( 0, sprintf( $statement, $result ) );
}

?>
