<?php

/**
 * bbPress BuddyPress Notifications
 *
 * @package bbPress
 * @subpackage BuddyPress
 */

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

// Hooks
add_filter( 'bp_notifications_get_registered_components', 'bbp_filter_notifications_get_registered_components', 10 );
add_filter( 'bp_notifications_get_notifications_for_user', 'bbp_format_buddypress_notifications', 10, 8 );
add_action( 'bbp_new_reply', 'bbp_buddypress_add_notification', 10, 7 );
add_action( 'bbp_get_request', 'bbp_buddypress_mark_notifications', 1 );

/** BuddyPress Helpers ********************************************************/

/**
 * Filter registered notifications components, and add 'forums' to the queried
 * 'component_name' array.
 *
 * @since 2.6.0 bbPress (r5232)
 *
 * @see BP_Notifications_Notification::get()
 * @param array $component_names
 * @return array
 */
function bbp_filter_notifications_get_registered_components( $component_names = array() ) {

	// Force $component_names to be an array
	if ( ! is_array( $component_names ) ) {
		$component_names = array();
	}

	// Add 'forums' component to registered components array
	array_push( $component_names, bbp_get_component_name() );

	// Return component's with 'forums' appended
	return $component_names;
}

/**
 * Format the BuddyBar/Toolbar notifications
 *
 * @since 2.5.0 bbPress (r5155)
 *
 * @package bbPress
 *
 * @param string $content               Component action. Deprecated. Do not do checks against this! Use
 *                                      the 6th parameter instead - $component_action_name.
 * @param int    $item_id               Notification item ID.
 * @param int    $secondary_item_id     Notification secondary item ID.
 * @param int    $action_item_count     Number of notifications with the same action.
 * @param string $format                Format of return. Either 'string' or 'object'.
 * @param string $component_action_name Canonical notification action.
 * @param string $component_name        Notification component ID.
 * @param int    $id                    Notification ID.
 */
function bbp_format_buddypress_notifications( $content, $item_id, $secondary_item_id, $action_item_count, $format, $component_action_name, $component_name, $id ) {

	// Bail if not the notification action we are looking for
	if ( 0 !== strpos( $component_action_name, 'bbp_new_reply' ) ) {
		return $content;
	}

	// New reply notifications
	$topic_id    = bbp_get_reply_topic_id( $item_id );
	$topic_title = bbp_get_topic_title( $topic_id );
	$topic_link  = wp_nonce_url(
		add_query_arg(
			array(
				'action'   => 'bbp_mark_read',
				'topic_id' => $topic_id
			),
			bbp_get_reply_url( $item_id )
		),
		'bbp_mark_topic_' . $topic_id
	);

	// Cast to int
	$action_item_count = (int) $action_item_count;

	// Multiple
	if ( $action_item_count > 1 ) {
		$filter = 'bbp_multiple_new_subscription_notification';
		$text   = sprintf( esc_html__( 'You have %1$d new replies to %2$s', 'bbpress' ), $action_item_count, $topic_title );

	// Single
	} else {
		$filter = 'bbp_single_new_subscription_notification';
		$text   = ! empty( $secondary_item_id )
			? sprintf( esc_html__( 'You have %1$d new reply to %2$s from %3$s', 'bbpress' ), $action_item_count, $topic_title, bp_core_get_user_displayname( $secondary_item_id ) )
			: sprintf( esc_html__( 'You have %1$d new reply to %2$s',           'bbpress' ), $action_item_count, $topic_title );
	}

	// WordPress Toolbar
	if ( 'string' === $format ) {
		$return = apply_filters( $filter, '<a href="' . esc_url( $topic_link ) . '" title="' . esc_attr__( 'Topic Replies', 'bbpress' ) . '">' . esc_html( $text ) . '</a>', $action_item_count, $text, $topic_link );

	// Deprecated BuddyBar
	} else {
		$return = apply_filters( $filter, array(
			'text' => $text,
			'link' => $topic_link
		), $topic_link, $action_item_count, $text, $topic_title );
	}

	do_action( 'bbp_format_buddypress_notifications', $component_action_name, $item_id, $secondary_item_id, $action_item_count, $format, $component_action_name, $component_name, $id );

	return $return;
}

/**
 * Hooked into the new reply function, this notification action is responsible
 * for notifying topic and hierarchical reply authors of topic replies.
 *
 * @since 2.5.0 bbPress (r5156)
 *
 * @param int $reply_id
 * @param int $topic_id
 * @param int $forum_id (not used)
 * @param array $anonymous_data (not used)
 * @param int $author_id
 * @param bool $is_edit Used to bail if this gets hooked to an edit action
 * @param int $reply_to
 */
function bbp_buddypress_add_notification( $reply_id = 0, $topic_id = 0, $forum_id = 0, $anonymous_data = array(), $author_id = 0, $is_edit = false, $reply_to = 0 ) {

	// Bail if somehow this is hooked to an edit action
	if ( ! empty( $is_edit ) ) {
		return;
	}

	// Get author information
	$topic_author_id   = bbp_get_topic_author_id( $topic_id );
	$secondary_item_id = $author_id;

	// Hierarchical replies
	if ( ! empty( $reply_to ) ) {
		$reply_to_item_id = bbp_get_topic_author_id( $reply_to );
	}

	// Get some reply information
	$args = array(
		'user_id'          => $topic_author_id,
		'item_id'          => $reply_id,
		'component_name'   => bbp_get_component_name(),
		'component_action' => 'bbp_new_reply_' . $topic_id,
		'date_notified'    => get_post( $reply_id )->post_date,
	);

	// Notify the topic author if not the current reply author
	if ( $author_id !== $topic_author_id ) {
		$args['secondary_item_id'] = $secondary_item_id;

		bp_notifications_add_notification( $args );
	}

	// Notify the immediate reply author if not the current reply author
	if ( ! empty( $reply_to ) && ( $author_id !== $reply_to_item_id ) ) {
		$args['user_id']           = $reply_to_item_id;
		$args['secondary_item_id'] = $topic_author_id;

		bp_notifications_add_notification( $args );
	}
}

/**
 * Mark notifications as read when reading a topic
 *
 * @since 2.5.0 bbPress (r5155)
 *
 * @return If not trying to mark a notification as read
 */
function bbp_buddypress_mark_notifications( $action = '' ) {

	// Bail if no topic ID is passed
	if ( empty( $_GET['topic_id'] ) ) {
		return;
	}

	// Bail if action is not for this function
	if ( 'bbp_mark_read' !== $action ) {
		return;
	}

	// Get required data
	$user_id  = bp_loggedin_user_id();
	$topic_id = absint( $_GET['topic_id'] );

	// By default, Redirect to this topic ID
	$redirect_id = $topic_id;

	// Check nonce
	if ( ! bbp_verify_nonce_request( 'bbp_mark_topic_' . $topic_id ) ) {
		bbp_add_error( 'bbp_notification_topic_id', __( '<strong>Error</strong>: Are you sure you wanted to do that?', 'bbpress' ) );

	// Check current user's ability to edit the user
	} elseif ( ! current_user_can( 'edit_user', $user_id ) ) {
		bbp_add_error( 'bbp_notification_permission', __( '<strong>Error</strong>: You do not have permission to mark notifications for that user.', 'bbpress' ) );
	}

	// Bail if we have errors
	if ( ! bbp_has_errors() ) {

		// Get these once
		$post_type = bbp_get_reply_post_type();
		$component = bbp_get_component_name();

		// Attempt to clear notifications for this topic
		$marked    = bp_notifications_mark_notifications_by_type( $user_id, $component, 'bbp_new_reply_' . $topic_id );

		// Get all reply IDs for the topic
		$replies   = bbp_get_all_child_ids( $topic_id, $post_type );

		// If topic has replies
		if ( ! empty( $replies ) ) {

			// Loop through each reply and attempt to mark it
			foreach ( $replies as $reply_id ) {

				// Attempt to mark notification for this reply ID
				$marked = bp_notifications_mark_notifications_by_item_id( $user_id, $reply_id, $component, 'bbp_new_reply' );

				// If marked, redirect to this reply ID
				if ( ! empty( $marked ) ) {
					$redirect_id = $reply_id;
				}
			}
		}

		// Do additional subscriptions actions
		do_action( 'bbp_notifications_handler', $marked, $user_id, $topic_id, $action );
	}

	// Redirect to the topic
	$redirect = bbp_get_reply_url( $redirect_id );

	// Redirect
	bbp_redirect( $redirect );
}
