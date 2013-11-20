<?php

/**
 * Format the BuddyBar/Toolbar notifications
 *
 * @since bbPress (r5155)
 *
 * @package bbPress
 *
 * @param string $action The kind of notification being rendered
 * @param int $item_id The primary item id
 * @param int $secondary_item_id The secondary item id
 * @param int $total_items The total number of messaging-related notifications waiting for the user
 * @param string $format 'string' for BuddyBar-compatible notifications; 'array' for WP Toolbar
 */
function bbp_format_buddypress_notifications( $action, $item_id, $secondary_item_id, $total_items, $format = 'string' ) {

	// New reply notifications
	if ( 'bbp_new_reply' === $action ) {
		$topic_link  = add_query_arg( array( 'bbp_mark_read' => 1 ), bbp_get_reply_url( $item_id ) );
		$topic_title = bbp_get_topic_title( bbp_get_reply_topic_id( $item_id ) );
		$title_attr  = __( 'Topic Replies', 'bbpress' );

		if ( (int) $total_items > 1 ) {
			$text   = sprintf( __( 'You have %d new replies', 'bbpress' ), (int) $total_items );
			$filter = 'bbp_multiple_new_subscription_notification';
		} else {
			if ( !empty( $secondary_item_id ) ) {
				$text = sprintf( __( 'You have %d new reply to %s from %s', 'bbpress' ), (int) $total_items, $topic_title, bp_core_get_user_displayname( $secondary_item_id ) );
			} else {
				$text = sprintf( __( 'You have %d new reply to %s',         'bbpress' ), (int) $total_items, $topic_title );
			}
			$filter = 'bbp_single_new_subscription_notification';
		}

		// WordPress Toolbar
		if ( 'string' === $format ) {
			$return = apply_filters( $filter, '<a href="' . esc_url( $topic_link ) . '" title="' . esc_attr( $title_attr ) . '">' . esc_html( $text ) . '</a>', (int) $total_items, $text, $topic_link );

		// Deprecated BuddyBar
		} else {
			$return = apply_filters( $filter, array(
				'text' => $text,
				'link' => $topic_link
			), $topic_link, (int) $total_items, $text, $topic_title );
		}

		do_action( 'bbp_format_buddypress_notifications', $action, $item_id, $secondary_item_id, $total_items );

		return $return;
	}
}
add_filter( 'bp_notifications_get_notifications_for_user', 'bbp_format_buddypress_notifications', 10, 5 );

/**
 * Hooked into the new reply function, this notification action is responsible
 * for notifying topic and hierarchical reply authors of topic replies.
 *
 * @since bbPress (r5156)
 *
 * @param int $reply_id
 * @param int $topic_id
 * @param int $forum_id (not used)
 * @param array $anonymous_data (not used)
 * @param int $author_id
 * @param bool $is_edit Used to bail if this gets hooked to an edit action
 * @param int $reply_to
 */
function bbp_buddypress_add_notification( $reply_id = 0, $topic_id = 0, $forum_id = 0, $anonymous_data = false, $author_id = 0, $is_edit = false, $reply_to = 0 ) {

	// Bail if somehow this is hooked to an edit action
	if ( !empty( $is_edit ) ) {
		return;
	}

	// Get autohr information
	$topic_author_id   = bbp_get_topic_author_id( $topic_id );
	$secondary_item_id = $author_id;

	// Hierarchical replies
	if ( !empty( $reply_to ) ) {
		$reply_to_item_id = bbp_get_topic_author_id( $reply_to );
	}

	// Get some reply information
	$date_notified    = get_post( $reply_id )->post_date;
	$item_id          = $topic_id;
	$component_name   = 'forums';
	$component_action = 'bbp_new_reply';

	// Notify the topic author if not the current reply author
	if ( $author_id !== $topic_author_id ) {
		bp_core_add_notification( $item_id, $topic_author_id, $component_name, $component_action, $secondary_item_id, $date_notified );
	}

	// Notify the immediate reply author if not the current reply author
	if ( !empty( $reply_to ) && ( $author_id !== $reply_to_item_id ) ) {
		bp_core_add_notification( $item_id, $topic_author_id, $component_name, $component_action, $reply_to_item_id, $date_notified );
	}
}
add_action( 'bbp_new_reply', 'bbp_buddypress_add_notification', 10, 7 );

/**
 * Mark notifications as read when reading a topic
 *
 * @since bbPress (r5155)
 *
 * @return If not trying to mark a notification as read
 */
function bbp_buddypress_mark_notifications() {

	// Bail if not marking a notification as read
	if ( empty( $_GET['bbp_mark_read'] ) ) {
		return;
	}

	// Bail if not a single topic
	if ( ! bbp_is_single_topic() ) {
		return;
	}

	// Attempt to clear notifications for the current user from this topic
	bp_core_mark_notifications_by_item_id( bp_loggedin_user_id(), bbp_get_topic_id(), 'forums', 'bbp_new_reply' );
}
add_action( 'bbp_template_redirect', 'bbp_buddypress_mark_notifications' );
