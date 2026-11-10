<?php

/**
 * bbPress User Subscriptions.
 *
 * @package bbPress
 * @subpackage Users
 */

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

/** Subscriptions *************************************************************/

/**
 * Get the users who have subscribed.
 *
 * @since 2.6.0 bbPress (r6544)
 *
 * @param int $object_id Optional. ID of object (forum, topic, or something else).
 */
function bbp_get_subscribers( $object_id = 0, $type = 'post' ) {
	$users = bbp_get_users_for_object( $object_id, '_bbp_subscription', $type );

	// Filter & return
	return (array) apply_filters( 'bbp_get_subscribers', $users, $object_id, $type );
}

/**
 * Get a user's subscribed topics.
 *
 * @since 2.5.0 bbPress (r5156)
 * @since 2.6.0 bbPress (r6619) Signature changed to accept an array of arguments
 *
 * @param array $args Optional. Arguments to pass into bbp_has_topics().
 *
 * @return array Array of topics if user has topic subscriptions, otherwise empty array.
 */
function bbp_get_user_topic_subscriptions( $args = array() ) {
	$r     = bbp_get_user_object_query( $args, 'topic_subscriptions', '_bbp_subscription' );
	$query = ! empty( $r )
		? bbp_has_topics( $r )
		: array();

	// Filter & return
	return apply_filters( 'bbp_get_user_topic_subscriptions', $query, 0, $r, $args );
}

/**
 * Get a user's subscribed forums.
 *
 * @since 2.5.0 bbPress (r5156)
 * @since 2.6.0 bbPress (r6619) Signature changed to accept an array of arguments
 *
 * @param array $args Optional. Arguments to pass into bbp_has_forums().
 *
 * @return array Array of forums if user has forum subscriptions, otherwise empty array.
 */
function bbp_get_user_forum_subscriptions( $args = array() ) {
	$r     = bbp_get_user_object_query( $args, 'forum_subscriptions', '_bbp_subscription' );
	$query = ! empty( $r )
		? bbp_has_forums( $r )
		: array();

	// Filter & return
	return apply_filters( 'bbp_get_user_forum_subscriptions', $query, 0, $r, $args );
}

/**
 * Get a user's subscribed terms in a taxonomy.
 *
 * @since 2.7.0 bbPress (r8335)
 *
 * @param array $args Optional. Arguments to pass into bbp_has_terms(). The
 *                    taxonomy argument defaults to topic tags.
 * @return bool Whether the user has subscribed terms in the taxonomy.
 */
function bbp_get_user_term_subscriptions( $args = array() ) {
	$taxonomy = is_array( $args ) && ! empty( $args['taxonomy'] )
		? sanitize_key( $args['taxonomy'] )
		: bbp_get_topic_tag_tax_id();

	// Query only when there is a valid taxonomy
	$r = taxonomy_exists( $taxonomy )
		? bbp_get_user_object_query( $args, 'term_subscriptions', '_bbp_subscription', 'term', $taxonomy )
		: array();

	$query = bbp_has_terms( $r );

	// Filter & return
	return (bool) apply_filters( 'bbp_get_user_term_subscriptions', $query, 0, $r, $args );
}

/**
 * Get topic taxonomies that have active subscriptions.
 *
 * @since 2.7.0 bbPress (r8335)
 *
 * @return array Taxonomy names.
 */
function bbp_get_topic_subscription_taxonomies() {
	$taxonomies = array();

	foreach ( get_object_taxonomies( bbp_get_topic_post_type(), 'objects' ) as $taxonomy ) {
		if ( bbp_is_subscription_subtype_active( 'term', $taxonomy->name ) ) {
			$taxonomies[] = $taxonomy->name;
		}
	}

	// Filter & return
	return (array) apply_filters( 'bbp_get_topic_subscription_taxonomies', $taxonomies );
}

/**
 * Get user IDs subscribed to terms assigned to a topic.
 *
 * @since 2.7.0 bbPress (r8335)
 *
 * @param int $topic_id Optional. Topic ID.
 * @return array User IDs.
 */
function bbp_get_subscribers_for_topic_terms( $topic_id = 0 ) {
	$topic_id   = bbp_get_topic_id( $topic_id );
	$taxonomies = bbp_get_topic_subscription_taxonomies();
	$term_ids   = ! empty( $topic_id ) && ! empty( $taxonomies )
		? wp_get_object_terms( $topic_id, $taxonomies, array( 'fields' => 'ids' ) )
		: array();
	$term_ids   = ! is_wp_error( $term_ids )
		? wp_parse_id_list( $term_ids )
		: array();
	$user_ids   = array();

	foreach ( $term_ids as $term_id ) {
		$user_ids = array_merge( $user_ids, bbp_get_subscribers( $term_id, 'term' ) );
	}

	$user_ids = wp_parse_id_list( $user_ids );

	// Filter & return
	return (array) apply_filters( 'bbp_get_subscribers_for_topic_terms', $user_ids, $topic_id, $term_ids, $taxonomies );
}

/**
 * Check if an object (forum or topic) is in user's subscription list or not.
 *
 * @since 2.0.0 bbPress (r2668)
 *
 * @param int $user_id Optional. User id.
 * @param int $object_id Optional. Object id.
 *
 * @return bool True if the object (forum or topic) is in user's subscriptions, otherwise false.
 */
function bbp_is_user_subscribed( $user_id = 0, $object_id = 0, $type = 'post' ) {
	$retval = bbp_is_object_of_user( $object_id, $user_id, '_bbp_subscription', $type );

	// Filter & return
	return (bool) apply_filters( 'bbp_is_user_subscribed', $retval, $user_id, $object_id, $type );
}

/**
 * Add a user subscription.
 *
 * @since 2.0.0 bbPress (r2668)
 * @since 2.6.0 bbPress (r6544) Added $type parameter
 *
 * @param int    $user_id   Optional. User id.
 * @param int    $object_id Optional. Object id.
 * @param string $type      Optional. Type of object being subscribed to.
 *
 * @return bool True if the object was added to user subscriptions, otherwise false.
 */
function bbp_add_user_subscription( $user_id = 0, $object_id = 0, $type = 'post' ) {

	// Bail if not enough info
	if ( empty( $user_id ) || empty( $object_id ) ) {
		return false;
	}

	// Bail if already subscribed
	if ( bbp_is_user_subscribed( $user_id, $object_id, $type ) ) {
		return false;
	}

	// Bail if add fails
	if ( ! bbp_add_user_to_object( $object_id, $user_id, '_bbp_subscription', $type ) ) {
		return false;
	}

	do_action( 'bbp_add_user_subscription', $user_id, $object_id, $type );

	return true;
}

/**
 * Remove a user subscription.
 *
 * @since 2.0.0 bbPress (r2668)
 * @since 2.6.0 bbPress (r6544) Added $type parameter
 *
 * @param int    $user_id   Optional. User id.
 * @param int    $object_id Optional. Object id.
 * @param string $type      Optional. Type of object being subscribed to.
 *
 * @return bool True if the object was removed from user subscriptions, otherwise false.
 */
function bbp_remove_user_subscription( $user_id = 0, $object_id = 0, $type = 'post' ) {

	// Bail if not enough info
	if ( empty( $user_id ) || empty( $object_id ) ) {
		return false;
	}

	// Bail if not subscribed
	if ( ! bbp_is_user_subscribed( $user_id, $object_id, $type ) ) {
		return false;
	}

	// Bail if remove fails
	if ( ! bbp_remove_user_from_object( $object_id, $user_id, '_bbp_subscription', $type ) ) {
		return false;
	}

	do_action( 'bbp_remove_user_subscription', $user_id, $object_id, $type );

	return true;
}

/**
 * Apply a topic form's subscription choice for its author.
 *
 * @since 2.6.20 bbPress (r7765)
 *
 * @param int $author_id Topic or reply author ID.
 * @param int $topic_id Topic ID.
 */
function bbp_update_user_topic_subscription( $author_id = 0, $topic_id = 0 ) {

	// Bail if topic subscriptions are disabled or the form has no valid target
	if ( ! bbp_is_subscription_subtype_active( 'post', bbp_get_topic_post_type() ) || empty( $author_id ) || empty( $topic_id ) ) {
		return;
	}

	$subscribed = bbp_is_user_subscribed( $author_id, $topic_id );
	$subscheck  = ! empty( $_POST['bbp_topic_subscription'] ) && ( 'bbp_subscribe' === $_POST['bbp_topic_subscription'] );

	if ( $subscribed && ! $subscheck ) {
		bbp_remove_user_subscription( $author_id, $topic_id );
	} elseif ( ! $subscribed && $subscheck ) {
		bbp_add_user_subscription( $author_id, $topic_id );
	}
}

/**
 * Apply a topic form's subscription choice after saving the topic.
 *
 * @since 2.6.20 bbPress (r7765)
 *
 * @param int   $topic_id Topic ID.
 * @param int   $forum_id Forum ID.
 * @param array $anonymous_data Anonymous author data.
 * @param int   $author_id Topic author ID.
 */
function bbp_update_topic_subscription( $topic_id = 0, $forum_id = 0, $anonymous_data = array(), $author_id = 0 ) {
	bbp_update_user_topic_subscription( $author_id, $topic_id );
}

/**
 * Apply a reply form's subscription choice after saving the reply.
 *
 * @since 2.6.20 bbPress (r7765)
 *
 * @param int   $reply_id Reply ID.
 * @param int   $topic_id Topic ID.
 * @param int   $forum_id Forum ID.
 * @param array $anonymous_data Anonymous author data.
 * @param int   $author_id Reply author ID.
 */
function bbp_update_reply_subscription( $reply_id = 0, $topic_id = 0, $forum_id = 0, $anonymous_data = array(), $author_id = 0 ) {
	bbp_update_user_topic_subscription( $author_id, $topic_id );
}

/**
 * Handles the front end toggling of user subscriptions.
 *
 * @since 2.0.0 bbPress (r2668)
 * @since 2.6.0 bbPress (r6544)
 * @since 2.6.20 bbPress (r8024) Use the displayed user on subscriptions views.
 * @since 2.7.0 bbPress (r8306) Use the engagement type for the fallback redirect.
 * @since 2.7.0 bbPress (r8323) Require a type-scoped nonce for non-posts.
 *
 * @param string $action The requested action to compare this function to.
 */
function bbp_subscriptions_handler( $action = '' ) {

	// Default
	$success = false;

	// Bail if subscriptions not active
	if ( ! bbp_is_subscriptions_active() ) {
		return $success;
	}

	// Bail if no object ID is passed
	if ( empty( $_GET['object_id'] ) ) {
		return $success;
	}

	// Setup possible get actions
	$possible_actions = array(
		'bbp_subscribe',
		'bbp_unsubscribe'
	);

	// Bail if actions aren't meant for this function
	if ( ! in_array( $action, $possible_actions, true ) ) {
		return $success;
	}

	// Get required data
	$user_id     = bbp_is_subscriptions()
		? bbp_get_displayed_user_id()
		: bbp_get_current_user_id();
	$object_id   = absint( $_GET['object_id'] );
	$object_type = 'post';
	if ( ! empty( $_GET['object_type'] ) ) {
		$object_type = is_string( $_GET['object_type'] ) ? sanitize_key( $_GET['object_type'] ) : '';
	}
	$toggle_action = ( 'bbp_unsubscribe' === $action ) ? 'remove' : 'add';

	// Check for empty topic
	if ( empty( $object_id ) || ! bbp_current_user_can_toggle_engagement( $object_id, $object_type, 'subscription', $toggle_action ) ) {
		bbp_add_error( 'bbp_subscription_object_id', __( '<strong>Error</strong>: Not found. What are you subscribing/unsubscribing to?', 'bbpress' ) );

	// Check nonce
	} elseif ( ! bbp_verify_nonce_request( 'toggle-subscription_' . $object_type . '_' . $object_id ) && ( ( 'post' !== $object_type ) || ! bbp_verify_nonce_request( 'toggle-subscription_' . $object_id ) ) ) {
		bbp_add_error( 'bbp_subscription_object_id', __( '<strong>Error</strong>: Are you sure you wanted to do that?', 'bbpress' ) );

	// Check current user's ability to edit the user
	} elseif ( ! bbp_current_user_can_manage_engagements( 'subscription', $user_id ) ) {
		bbp_add_error( 'bbp_subscription_permission', __( '<strong>Error</strong>: You do not have permission to edit subscriptions of that user.', 'bbpress' ) );
	}

	// Bail if we have errors
	if ( bbp_has_errors() ) {
		return $success;
	}

	/** No errors *************************************************************/

	if ( 'bbp_unsubscribe' === $action ) {
		$success = bbp_remove_user_subscription( $user_id, $object_id, $object_type );
	} elseif ( 'bbp_subscribe' === $action ) {
		$success = bbp_add_user_subscription( $user_id, $object_id, $object_type );
	}

	// Do additional subscriptions actions
	do_action( 'bbp_subscriptions_handler', $success, $user_id, $object_id, $action, $object_type );

	// Success!
	if ( true === $success ) {

		// Redirect back from whence we came
		if ( ! empty( $_REQUEST['redirect_to'] ) ) {
			$redirect = $_REQUEST['redirect_to']; // Validated later
		} elseif ( bbp_is_subscriptions() ) {
			$redirect = bbp_get_subscriptions_permalink( $user_id );
		} elseif ( bbp_is_single_user() ) {
			$redirect = bbp_get_user_profile_url();
		} elseif ( is_singular( bbp_get_topic_post_type() ) ) {
			$redirect = bbp_get_topic_permalink( $object_id );
		} elseif ( is_singular( bbp_get_forum_post_type() ) ) {
			$redirect = bbp_get_forum_permalink( $object_id );
		} elseif ( is_single() || is_page() ) {
			$redirect = get_permalink();
		} else {
			$redirect = bbp_get_engagement_type( $object_type )->get_url( $object_id );
		}

		bbp_redirect( $redirect );

	// Fail! Handle errors
	} elseif ( 'bbp_unsubscribe' === $action ) {
		bbp_add_error( 'bbp_unsubscribe', __( '<strong>Error</strong>: There was a problem unsubscribing.', 'bbpress' ) );
	} elseif ( 'bbp_subscribe' === $action ) {
		bbp_add_error( 'bbp_subscribe',   __( '<strong>Error</strong>: There was a problem subscribing.', 'bbpress' ) );
	}

	return (bool) $success;
}

/**
 * Get a user's subscribed forum ids.
 *
 * @since 2.5.0 bbPress (r5156)
 *
 * @param int $user_id Optional. User id.
 *
 * @return array Return array of subscribed forum ids, or empty array.
 */
function bbp_get_user_subscribed_forum_ids( $user_id = 0 ) {
	return bbp_get_user_object_ids(
		array(
			'user_id'     => $user_id,
			'rel_key'     => '_bbp_subscription',
			'object_type' => bbp_get_forum_post_type(),
			'filter'      => 'user_subscribed_forum_ids'
		)
	);
}

/**
 * Get a user's subscribed topic ids.
 *
 * @since 2.0.0 bbPress (r2668)
 *
 * @param int $user_id Optional. User id.
 *
 * @return array Return array of subscribed topic ids, or empty array.
 */
function bbp_get_user_subscribed_topic_ids( $user_id = 0 ) {
	return bbp_get_user_object_ids(
		array(
			'user_id' => $user_id,
			'rel_key' => '_bbp_subscription',
			'filter'  => 'user_subscribed_topic_ids'
		)
	);
}

/** Deprecated ****************************************************************/

/**
 * Get a user's subscribed topics.
 *
 * @since 2.0.0 bbPress (r2668)
 * @deprecated 2.5.0 bbPress (r5156)
 *
 * @param int $user_id Optional. User id.
 *
 * @return array|bool Results if user has subscriptions, otherwise false.
 */
function bbp_get_user_subscriptions( $user_id = 0 ) {
	_deprecated_function( __FUNCTION__, '2.5', 'bbp_get_user_topic_subscriptions()' );
	$query = bbp_get_user_topic_subscriptions( $user_id );

	// Filter & return
	return apply_filters( 'bbp_get_user_subscriptions', $query, $user_id );
}

/**
 * Get the users who have subscribed to the forum.
 *
 * @since 2.5.0 bbPress (r5156)
 * @deprecated 2.6.0 bbPress (r6543)
 *
 * @param int $forum_id Optional. forum id.
 *
 * @return array|bool Results if the forum has any subscribers, otherwise false.
 */
function bbp_get_forum_subscribers( $forum_id = 0 ) {
	$users = bbp_get_users_for_object( $forum_id, '_bbp_subscription' );

	// Filter & return
	return (array) apply_filters( 'bbp_get_forum_subscribers', $users, $forum_id );
}

/**
 * Get the users who have subscribed to the topic.
 *
 * @since 2.0.0 bbPress (r2668)
 * @deprecated 2.6.0 bbPress (r6543)
 *
 * @param int $topic_id Optional. Topic id.
 *
 * @return array|bool Results if the topic has any subscribers, otherwise false.
 */
function bbp_get_topic_subscribers( $topic_id = 0 ) {
	$users = bbp_get_users_for_object( $topic_id, '_bbp_subscription' );

	// Filter & return
	return (array) apply_filters( 'bbp_get_topic_subscribers', $users, $topic_id );
}

/**
 * Check if a forum is in user's subscription list or not.
 *
 * @since 2.5.0 bbPress (r5156)
 * @deprecated 2.6.0 bbPress (r6543)
 *
 * @param int $user_id  Optional. User id.
 * @param int $forum_id Optional. Forum id.
 *
 * @return bool True if the forum is in user's subscriptions, otherwise false.
 */
function bbp_is_user_subscribed_to_forum( $user_id = 0, $forum_id = 0 ) {
	return bbp_is_user_subscribed( $user_id, $forum_id );
}

/**
 * Check if a topic is in user's subscription list or not.
 *
 * @since 2.5.0 bbPress (r5156)
 * @deprecated 2.6.0 bbPress (r6543) Use bbp_is_user_subscribed()
 *
 * @param int $user_id Optional. User id.
 * @param int $topic_id Optional. Topic id.
 * @return bool True if the topic is in user's subscriptions, otherwise false.
 */
function bbp_is_user_subscribed_to_topic( $user_id = 0, $topic_id = 0 ) {
	return bbp_is_user_subscribed( $user_id, $topic_id );
}

/**
 * Remove a forum from user's subscriptions.
 *
 * @since 2.5.0 bbPress (r5156)
 * @deprecated 2.6.0 bbPress (r6543)
 *
 * @param int $user_id Optional. User id.
 * @param int $forum_id Optional. forum id.
 * @return bool True if the forum was removed from user's subscriptions,
 *               otherwise false.
 */
function bbp_remove_user_forum_subscription( $user_id = 0, $forum_id = 0 ) {
	return bbp_remove_user_subscription( $user_id, $forum_id );
}

/**
 * Remove a topic from user's subscriptions.
 *
 * @since 2.5.0 bbPress (r5156)
 * @deprecated 2.6.0 bbPress (r6543)
 *
 * @param int $user_id Optional. User id.
 * @param int $topic_id Optional. Topic id.
 * @return bool True if the topic was removed from user's subscriptions,
 *               otherwise false.
 */
function bbp_remove_user_topic_subscription( $user_id = 0, $topic_id = 0 ) {
	return bbp_remove_user_subscription( $user_id, $topic_id );
}

/**
 * Add a forum to user's subscriptions.
 *
 * @since 2.5.0 bbPress (r5156)
 * @deprecated 2.6.0 bbPress (r6543)
 *
 * @param int $user_id Optional. User id.
 * @param int $forum_id Optional. forum id.
 * @return bool Always true.
 */
function bbp_add_user_forum_subscription( $user_id = 0, $forum_id = 0 ) {
	return bbp_add_user_subscription( $user_id, $forum_id );
}

/**
 * Add a topic to user's subscriptions.
 *
 * Note that both the User and Topic should be verified to exist before using
 * this function. Originally both were validated, but because this function is
 * frequently used within a loop, those verifications were moved upstream to
 * improve performance on topics with many engaged users.
 *
 * @since 2.5.0 bbPress (r5156)
 * @deprecated 2.6.0 bbPress (r6543)
 *
 * @param int $user_id Optional. User id.
 * @param int $topic_id Optional. Topic id.
 * @return bool Always true.
 */
function bbp_add_user_topic_subscription( $user_id = 0, $topic_id = 0 ) {
	return bbp_add_user_subscription( $user_id, $topic_id );
}

/**
 * Handles the front end toggling of forum subscriptions.
 *
 * @since 2.5.0 bbPress (r5156)
 * @deprecated 2.6.0 bbPress (r6543)
 */
function bbp_forum_subscriptions_handler( $action = '' ) {
	return bbp_subscriptions_handler( $action );
}
