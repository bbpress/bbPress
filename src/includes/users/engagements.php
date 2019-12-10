<?php

/**
 * bbPress User Engagement Functions
 *
 * @package bbPress
 * @subpackage Engagements
 */

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

/** User Relationships ********************************************************/

/**
 * Add a user id to an object
 *
 * @since 2.6.0 bbPress (r6109)
 *
 * @param int    $object_id The object id
 * @param int    $user_id   The user id
 * @param string $rel_key   The relationship key
 * @param string $rel_type  The relationship type (usually 'post')
 * @param bool   $unique    Whether meta key should be unique to the object
 *
 * @return bool Returns true on success, false on failure
 */
function bbp_add_user_to_object( $object_id = 0, $user_id = 0, $rel_key = '', $rel_type = 'post', $unique = false ) {
	$object_id = absint( $object_id );
	$user_id   = absint( $user_id );
	$retval    = bbp_user_engagements_interface( $rel_key, $rel_type )->add_user_to_object( $object_id, $user_id, $rel_key, $rel_type, $unique );

	// Filter & return
	return (bool) apply_filters( 'bbp_add_user_to_object', $retval, $object_id, $user_id, $rel_key, $rel_type, $unique );
}

/**
 * Remove a user id from an object
 *
 * @since 2.6.0 bbPress (r6109)
 *
 * @param int    $object_id The object id
 * @param int    $user_id   The user id
 * @param string $rel_key   The relationship key
 * @param string $rel_type  The relationship type (usually 'post')
 *
 * @return bool Returns true on success, false on failure
 */
function bbp_remove_user_from_object( $object_id = 0, $user_id = 0, $rel_key = '', $rel_type = 'post' ) {
	$retval = bbp_user_engagements_interface( $rel_key, $rel_type )->remove_user_from_object( $object_id, $user_id, $rel_key, $rel_type );

	// Filter & return
	return (bool) apply_filters( 'bbp_remove_user_from_object', $retval, $object_id, $user_id, $rel_key, $rel_type );
}

/**
 * Remove a user id from all objects
 *
 * @since 2.6.0 bbPress (r6109)
 *
 * @param int    $user_id  The user id
 * @param string $rel_key  The relationship key
 * @param string $rel_type The relationship type (usually 'post')
 *
 * @return bool Returns true on success, false on failure
 */
function bbp_remove_user_from_all_objects( $user_id = 0, $rel_key = '', $rel_type = 'post' ) {
	$user_id = absint( $user_id );
	$retval  = bbp_user_engagements_interface( $rel_key, $rel_type )->remove_user_from_all_objects( $user_id, $rel_key, $rel_type );

	// Filter & return
	return (bool) apply_filters( 'bbp_remove_user_from_all_objects', $retval, $user_id, $rel_key, $rel_type );
}

/**
 * Remove an object from all users
 *
 * @since 2.6.0 bbPress (r6109)
 *
 * @param int    $object_id The object id
 * @param int    $user_id   The user id
 * @param string $rel_key   The relationship key
 * @param string $rel_type  The relationship type (usually 'post')
 *
 * @return bool Returns true on success, false on failure
 */
function bbp_remove_object_from_all_users( $object_id = 0, $rel_key = '', $rel_type = 'post' ) {
	$object_id = absint( $object_id );
	$retval    = bbp_user_engagements_interface( $rel_key, $rel_type )->remove_object_from_all_users( $object_id, $rel_key, $rel_type );

	// Filter & return
	return (bool) apply_filters( 'bbp_remove_object_from_all_users', $retval, $object_id, $rel_key, $rel_type );
}

/**
 * Remove all users from all objects
 *
 * @since 2.6.0 bbPress (r6109)
 *
 * @param string $rel_key  The relationship key
 * @param string $rel_type The relationship type (usually 'post')
 *
 * @return bool Returns true on success, false on failure
 */
function bbp_remove_all_users_from_all_objects( $rel_key = '', $rel_type = 'post' ) {
	$retval = bbp_user_engagements_interface( $rel_key, $rel_type )->remove_all_users_from_all_objects( $rel_key, $rel_type );

	// Filter & return
	return (bool) apply_filters( 'bbp_remove_all_users_from_all_objects', $retval, $rel_key, $rel_type );
}

/**
 * Get users of an object
 *
 * @since 2.6.0 bbPress (r6109)
 *
 * @param int    $object_id The object id
 * @param string $rel_key   The key used to index this relationship
 * @param string $rel_type  The type of meta to look in
 *
 * @return array Returns ids of users
 */
function bbp_get_users_for_object( $object_id = 0, $rel_key = '', $rel_type = 'post' ) {
	$object_id = absint( $object_id );
	$retval    = bbp_user_engagements_interface( $rel_key, $rel_type )->get_users_for_object( $object_id, $rel_key, $rel_type );

	// Filter & return
	return (array) apply_filters( 'bbp_get_users_for_object', $retval, $object_id, $rel_key, $rel_type );
}

/**
 * Check if an object has a specific user
 *
 * @since 2.6.0 bbPress (r6109)
 *
 * @param int    $object_id The object id
 * @param int    $user_id   The user id
 * @param string $rel_key   The relationship key
 * @param string $rel_type  The relationship type (usually 'post')
 *
 * @return bool Returns true if object has a user, false if not
 */
function bbp_is_object_of_user( $object_id = 0, $user_id = 0, $rel_key = '', $rel_type = 'post' ) {
	$object_id = absint( $object_id );
	$user_id   = absint( $user_id );
	$user_ids  = bbp_get_users_for_object( $object_id, $rel_key, $rel_type );
	$retval    = is_numeric( array_search( $user_id, $user_ids, true ) );

	// Filter & return
	return (bool) apply_filters( 'bbp_is_object_of_user', $retval, $object_id, $user_id, $rel_key, $rel_type );
}

/**
 * Get the query part responsible for JOINing objects to user IDs
 *
 * @since 2.6.0 bbPress (r6747)
 *
 * @param array  $args      Default query arguments
 * @param string $context   Additional context
 * @param string $rel_key   The relationship key
 * @param string $rel_type  The relationship type (usually 'post')
 *
 * @return array
 */
function bbp_get_user_object_query( $args = array(), $context = '', $rel_key = '', $rel_type = 'post' ) {
	$retval = bbp_user_engagements_interface( $rel_key, $rel_type )->get_query( $args, "get_user_{$context}", $rel_key, $rel_type );

	// Filter & return
	return (array) apply_filters( 'bbp_get_user_object_query', $retval, $args, $context, $rel_key, $rel_type );
}

/** Engagements ***************************************************************/

/**
 * Get the users who have engaged in a topic
 *
 * @since 2.6.0 bbPress (r6320)
 *
 * @param int $topic_id Optional. Topic id
 *
 * @return array|bool Results if the topic has any engagements, otherwise false
 */
function bbp_get_topic_engagements( $topic_id = 0 ) {
	$topic_id = bbp_get_topic_id( $topic_id );
	$users    = bbp_get_users_for_object( $topic_id, '_bbp_engagement' );

	// Filter & return
	return (array) apply_filters( 'bbp_get_topic_engagements', $users, $topic_id );
}

/**
 * Return the users who have engaged in a topic, directly with a database query
 *
 * See: https://bbpress.trac.wordpress.org/ticket/3083
 *
 * @since 2.6.0 bbPress (r6522)
 *
 * @param int $topic_id
 *
 * @return array
 */
function bbp_get_topic_engagements_raw( $topic_id = 0 ) {

	// Default variables
	$topic_id = bbp_get_topic_id( $topic_id );
	$bbp_db   = bbp_db();
	$statii   = "'" . implode( "', '", bbp_get_public_topic_statuses() ) . "'";

	// A cool UNION query!
	$sql = "
SELECT DISTINCT( post_author ) FROM (
	SELECT post_author FROM {$bbp_db->posts}
		WHERE ( ID = %d AND post_status IN ({$statii}) AND post_type = %s )
UNION
	SELECT post_author FROM {$bbp_db->posts}
		WHERE ( post_parent = %d AND post_status = %s AND post_type = %s )
) as u1";

	// Prepare & get results
	$query   = $bbp_db->prepare( $sql, $topic_id, bbp_get_topic_post_type(), $topic_id, bbp_get_public_status_id(), bbp_get_reply_post_type() );
	$results = $bbp_db->get_col( $query );

	// Parse results into voices
	$engagements = ! is_wp_error( $results )
		? wp_parse_id_list( array_filter( $results ) )
		: array();

	// Filter & return
	return (array) apply_filters( 'bbp_get_topic_engagements_raw', $engagements, $topic_id );
}

/**
 * Get a user's topic engagements
 *
 * @since 2.6.0 bbPress (r6320)
 * @since 2.6.0 bbPress (r6618) Signature changed to accept an array of arguments
 *
 * @param array $args Optional. Arguments to pass into bbp_has_replies()
 *
 * @return bool True if user has engaged, otherwise false
 */
function bbp_get_user_engagements( $args = array() ) {
	$r     = bbp_get_user_object_query( $args, 'engagements', '_bbp_engagement' );
	$query = bbp_has_topics( $r );

	// Filter & return
	return apply_filters( 'bbp_get_user_engagements', $query, 0, $r, $args );
}

/**
 * Check if a user is engaged in a topic or not
 *
 * @since 2.6.0 bbPress (r6320)
 *
 * @param int $user_id Optional. User id
 * @param int $topic_id Optional. Topic id
 *
 * @return bool True if the topic is in user's engagements, otherwise false
 */
function bbp_is_user_engaged( $user_id = 0, $topic_id = 0 ) {
	$user_id  = bbp_get_user_id( $user_id, true, true );
	$topic_id = bbp_get_topic_id( $topic_id );
	$retval   = bbp_is_object_of_user( $topic_id, $user_id, '_bbp_engagement' );

	// Filter & return
	return (bool) apply_filters( 'bbp_is_user_engaged', $retval, $user_id, $topic_id );
}

/**
 * Add a topic to user's engagements
 *
 * Note that both the User and Topic should be verified to exist before using
 * this function. Originally both were validated, but because this function is
 * frequently used within a loop, those verifications were moved upstream to
 * improve performance on topics with many engaged users.
 *
 * @since 2.6.0 bbPress (r6320)
 *
 * @param int $user_id Optional. User id
 * @param int $topic_id Optional. Topic id
 *
 * @return bool Always true
 */
function bbp_add_user_engagement( $user_id = 0, $topic_id = 0 ) {

	// Bail if not enough info
	if ( empty( $user_id ) || empty( $topic_id ) ) {
		return false;
	}

	// Bail if already a engaged
	if ( bbp_is_user_engaged( $user_id, $topic_id ) ) {
		return false;
	}

	// Bail if add fails
	if ( ! bbp_add_user_to_object( $topic_id, $user_id, '_bbp_engagement' ) ) {
		return false;
	}

	do_action( 'bbp_add_user_engagement', $user_id, $topic_id );

	return true;
}

/**
 * Remove a topic from user's engagements
 *
 * @since 2.6.0 bbPress (r6320)
 *
 * @param int $user_id Optional. User id
 * @param int $topic_id Optional. Topic id
 *
 * @return bool True if the topic was removed from user's engagements, otherwise
 *               false
 */
function bbp_remove_user_engagement( $user_id = 0, $topic_id = 0 ) {

	// Bail if not enough info
	if ( empty( $user_id ) || empty( $topic_id ) ) {
		return false;
	}

	// Bail if not already engaged
	if ( ! bbp_is_user_engaged( $user_id, $topic_id ) ) {
		return false;
	}

	// Bail if remove fails
	if ( ! bbp_remove_user_from_object( $topic_id, $user_id, '_bbp_engagement' ) ) {
		return false;
	}

	do_action( 'bbp_remove_user_engagement', $user_id, $topic_id );

	return true;
}

/**
 * Recalculate all of the users who have engaged in a topic.
 *
 * This happens when permanently deleting a reply, because that reply author may
 * have authored other replies to that same topic, or the topic itself.
 *
 * You may need to do this manually on heavily active forums where engagement
 * count accuracy is important.
 *
 * @since 2.6.0 bbPress (r6522)
 *
 * @param int  $topic_id
 * @param bool $force
 *
 * @return boolean True if any engagements are added, false otherwise
 */
function bbp_recalculate_topic_engagements( $topic_id = 0, $force = false ) {

	// Default return value
	$retval = false;

	// Check post type
	$topic_id = bbp_is_reply( $topic_id )
		? bbp_get_reply_topic_id( $topic_id )
		: bbp_get_topic_id( $topic_id );

	// Bail if no topic ID
	if ( empty( $topic_id ) ) {
		return $retval;
	}

	// Query for engagements
	$old_engagements = bbp_get_topic_engagements( $topic_id );
	$new_engagements = bbp_get_topic_engagements_raw( $topic_id );

	// Sort arrays
	sort( $old_engagements, SORT_NUMERIC );
	sort( $new_engagements, SORT_NUMERIC );

	// Only recalculate on change
	if ( ( true === $force ) || ( $old_engagements !== $new_engagements ) ) {

		// Delete all engagements
		bbp_remove_object_from_all_users( $topic_id, '_bbp_engagement' );

		// Update the voice count for this topic id
		foreach ( $new_engagements as $user_id ) {
			$retval = bbp_add_user_engagement( $user_id, $topic_id );
		}
	}

	// Filter & return
	return (bool) apply_filters( 'bbp_recalculate_user_engagements', $retval, $topic_id );
}

/**
 * Update the engagements of a topic.
 *
 * Hooked to 'bbp_new_topic' and 'bbp_new_reply', this gets the post author and
 * if not anonymous, passes it into bbp_add_user_engagement().
 *
 * @since 2.6.0 bbPress (r6526)
 *
 * @param int $topic_id
 */
function bbp_update_topic_engagements( $topic_id = 0 ) {

	// Is a reply
	if ( bbp_is_reply( $topic_id ) ) {

		// Bail if reply isn't published
		if ( ! bbp_is_reply_published( $topic_id ) ) {
			return;
		}

		$author_id = bbp_get_reply_author_id( $topic_id );
		$topic_id  = bbp_get_reply_topic_id( $topic_id );

	// Is a topic
	} elseif ( bbp_is_topic( $topic_id ) ) {

		// Bail if topic isn't published
		if ( ! bbp_is_topic_published( $topic_id ) ) {
			return;
		}

		$author_id = bbp_get_topic_author_id( $topic_id );
		$topic_id  = bbp_get_topic_id( $topic_id );

	// Is unknown
	} else {
		return;
	}

	// Bail if topic is not public
	if ( ! bbp_is_topic_public( $topic_id ) ) {
		return;
	}

	// Return whether engagement was added
	return bbp_add_user_engagement( $author_id, $topic_id );
}

/** Favorites *****************************************************************/

/**
 * Get the users who have made the topic favorite
 *
 * @since 2.0.0 bbPress (r2658)
 *
 * @param int $topic_id Optional. Topic id
 *
 * @return array|bool Results if the topic has any favoriters, otherwise false
 */
function bbp_get_topic_favoriters( $topic_id = 0 ) {
	$topic_id = bbp_get_topic_id( $topic_id );
	$users    = bbp_get_users_for_object( $topic_id, '_bbp_favorite' );

	// Filter & return
	return (array) apply_filters( 'bbp_get_topic_favoriters', $users, $topic_id );
}

/**
 * Get a user's favorite topics
 *
 * @since 2.0.0 bbPress (r2652)
 * @since 2.6.0 bbPress (r6618) Signature changed to accept an array of arguments
 *
 * @param array $args Optional. Arguments to pass into bbp_has_topics()
 *
 * @return array Array of topics if user has favorites, otherwise empty array
 */
function bbp_get_user_favorites( $args = array() ) {
	$r     = bbp_get_user_object_query( $args, 'favorites', '_bbp_favorite' );
	$query = ! empty( $r )
		? bbp_has_topics( $r )
		: array();

	// Filter & return
	return apply_filters( 'bbp_get_user_favorites', $query, 0, $r, $args );
}

/**
 * Check if a topic is in user's favorites or not
 *
 * @since 2.0.0 bbPress (r2652)
 *
 * @param int $user_id Optional. User id
 * @param int $topic_id Optional. Topic id
 *
 * @return bool True if the topic is in user's favorites, otherwise false
 */
function bbp_is_user_favorite( $user_id = 0, $topic_id = 0 ) {
	$retval = bbp_is_object_of_user( $topic_id, $user_id, '_bbp_favorite' );

	// Filter & return
	return (bool) apply_filters( 'bbp_is_user_favorite', $retval, $user_id, $topic_id );
}

/**
 * Add a topic to user's favorites
 *
 * Note that both the User and Topic should be verified to exist before using
 * this function. Originally both were validated, but because this function is
 * frequently used within a loop, those verifications were moved upstream to
 * improve performance on topics with many engaged users.
 *
 * @since 2.0.0 bbPress (r2652)
 *
 * @param int $user_id Optional. User id
 * @param int $topic_id Optional. Topic id
 *
 * @return bool True if the topic was added to user's favorites, otherwise false
 */
function bbp_add_user_favorite( $user_id = 0, $topic_id = 0 ) {
	$user_id  = bbp_get_user_id( $user_id, false, false );
	$topic_id = bbp_get_topic_id( $topic_id );

	// Bail if not enough info
	if ( empty( $user_id ) || empty( $topic_id ) ) {
		return false;
	}

	// Bail if already a favorite
	if ( bbp_is_user_favorite( $user_id, $topic_id ) ) {
		return false;
	}

	// Bail if add fails
	if ( ! bbp_add_user_to_object( $topic_id, $user_id, '_bbp_favorite' ) ) {
		return false;
	}

	do_action( 'bbp_add_user_favorite', $user_id, $topic_id );

	return true;
}

/**
 * Remove a topic from user's favorites
 *
 * @since 2.0.0 bbPress (r2652)
 *
 * @param int $user_id Optional. User id
 * @param int $topic_id Optional. Topic id
 *
 * @return bool True if the topic was removed from user's favorites, otherwise false
 */
function bbp_remove_user_favorite( $user_id, $topic_id ) {
	$user_id  = bbp_get_user_id( $user_id, false, false );
	$topic_id = bbp_get_topic_id( $topic_id );

	// Bail if not enough info
	if ( empty( $user_id ) || empty( $topic_id ) ) {
		return false;
	}

	// Bail if not already a favorite
	if ( ! bbp_is_user_favorite( $user_id, $topic_id ) ) {
		return false;
	}

	// Bail if remove fails
	if ( ! bbp_remove_user_from_object( $topic_id, $user_id, '_bbp_favorite' ) ) {
		return false;
	}

	do_action( 'bbp_remove_user_favorite', $user_id, $topic_id );

	return true;
}

/**
 * Handles the front end adding and removing of favorite topics
 *
 * @param string $action The requested action to compare this function to
 */
function bbp_favorites_handler( $action = '' ) {

	// Default
	$success = false;

	// Bail if favorites not active
	if ( ! bbp_is_favorites_active() ) {
		return $success;
	}

	// Bail if no topic ID is passed
	if ( empty( $_GET['object_id'] ) ) {
		return $success;
	}

	// Setup possible get actions
	$possible_actions = array(
		'bbp_favorite_add',
		'bbp_favorite_remove',
	);

	// Bail if actions aren't meant for this function
	if ( ! in_array( $action, $possible_actions, true ) ) {
		return $success;
	}

	// What action is taking place?
	$topic_id = bbp_get_topic_id( $_GET['object_id'] );
	$user_id  = bbp_get_user_id( 0, true, true );

	// Check for empty topic
	if ( empty( $topic_id ) ) {
		bbp_add_error( 'bbp_favorite_topic_id', __( '<strong>ERROR</strong>: No topic was found. Which topic are you marking/unmarking as favorite?', 'bbpress' ) );

	// Check nonce
	} elseif ( ! bbp_verify_nonce_request( 'toggle-favorite_' . $topic_id ) ) {
		bbp_add_error( 'bbp_favorite_nonce', __( '<strong>ERROR</strong>: Are you sure you wanted to do that?', 'bbpress' ) );

	// Check current user's ability to edit the user
	} elseif ( ! current_user_can( 'edit_user', $user_id ) ) {
		bbp_add_error( 'bbp_favorite_permission', __( '<strong>ERROR</strong>: You do not have permission to edit favorites for that user.', 'bbpress' ) );
	}

	// Bail if errors
	if ( bbp_has_errors() ) {
		return $success;
	}

	/** No errors *************************************************************/

	if ( 'bbp_favorite_remove' === $action ) {
		$success = bbp_remove_user_favorite( $user_id, $topic_id );
	} elseif ( 'bbp_favorite_add' === $action ) {
		$success = bbp_add_user_favorite( $user_id, $topic_id );
	}

	// Do additional favorites actions
	do_action( 'bbp_favorites_handler', $success, $user_id, $topic_id, $action );

	// Success!
	if ( true === $success ) {

		// Redirect back from whence we came
		if ( ! empty( $_REQUEST['redirect_to'] ) ) {
			$redirect = $_REQUEST['redirect_to']; // Validated later
		} elseif ( bbp_is_favorites() ) {
			$redirect = bbp_get_favorites_permalink( $user_id, true );
		} elseif ( bbp_is_single_user() ) {
			$redirect = bbp_get_user_profile_url();
		} elseif ( is_singular( bbp_get_topic_post_type() ) ) {
			$redirect = bbp_get_topic_permalink( $topic_id );
		} elseif ( is_single() || is_page() ) {
			$redirect = get_permalink();
		} else {
			$redirect = get_permalink( $topic_id );
		}

		bbp_redirect( $redirect );

	// Fail! Handle errors
	} elseif ( 'bbp_favorite_remove' === $action ) {
		bbp_add_error( 'bbp_favorite_remove', __( '<strong>ERROR</strong>: There was a problem removing that topic from favorites.', 'bbpress' ) );
	} elseif ( 'bbp_favorite_add' === $action ) {
		bbp_add_error( 'bbp_favorite_add',    __( '<strong>ERROR</strong>: There was a problem favoriting that topic.', 'bbpress' ) );
	}

	return (bool) $success;
}

/** Subscriptions *************************************************************/

/**
 * Get the users who have subscribed
 *
 * @since 2.6.0 bbPress (r5156)
 *
 * @param int $object_id Optional. ID of object (forum, topic, or something else)
 */
function bbp_get_subscribers( $object_id = 0, $type = 'post' ) {
	$users = bbp_get_users_for_object( $object_id, '_bbp_subscription', $type );

	// Filter & return
	return (array) apply_filters( 'bbp_get_subscribers', $users, $object_id, $type );
}

/**
 * Get a user's subscribed topics
 *
 * @since 2.0.0 bbPress (r2668)
 * @since 2.6.0 bbPress (r6618) Signature changed to accept an array of arguments
 *
 * @param array $args Optional. Arguments to pass into bbp_has_topics()
 *
 * @return array Array of topics if user has topic subscriptions, otherwise empty array
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
 * Get a user's subscribed forums
 *
 * @since 2.5.0 bbPress (r5156)
 * @since 2.6.0 bbPress (r6618) Signature changed to accept an array of arguments
 *
 * @param array $args Optional. Arguments to pass into bbp_has_forums()
 *
 * @return array Array of forums if user has forum subscriptions, otherwise empty array
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
 * Check if an object (forum or topic) is in user's subscription list or not
 *
 * @since 2.5.0 bbPress (r5156)
 *
 * @param int $user_id Optional. User id
 * @param int $object_id Optional. Object id
 *
 * @return bool True if the object (forum or topic) is in user's subscriptions, otherwise false
 */
function bbp_is_user_subscribed( $user_id = 0, $object_id = 0, $type = 'post' ) {
	$retval = bbp_is_object_of_user( $object_id, $user_id, '_bbp_subscription', $type );

	// Filter & return
	return (bool) apply_filters( 'bbp_is_user_subscribed', $retval, $user_id, $object_id, $type );
}

/**
 * Add a user subscription
 *
 * @since 2.5.0 bbPress (r5156)
 * @since 2.6.0 bbPress (r6544) Added $type parameter
 *
 * @param int    $user_id   Optional. User id
 * @param int    $object_id Optional. Object id
 * @param string $type      Optional. Type of object being subscribed to
 *
 * @return bool True if the object was added to user subscriptions, otherwise false
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
 * Remove a user subscription
 *
 * @since 2.5.0 bbPress (r5156)
 * @since 2.6.0 bbPress (r6544) Added $type parameter
 *
 * @param int    $user_id   Optional. User id
 * @param int    $object_id Optional. Object id
 * @param string $type      Optional. Type of object being subscribed to
 *
 * @return bool True if the object was removed from user subscriptions, otherwise false
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
 * Handles the front end toggling of user subscriptions
 *
 * @since 2.0.0 bbPress (r2790)
 * @since 2.6.l bbPress (r6543)
 *
 * @param string $action The requested action to compare this function to
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
	$user_id     = bbp_get_current_user_id();
	$object_id   = absint( $_GET['object_id'] );
	$object_type = ! empty( $_GET['object_type'] )
		? sanitize_key( $_GET['object_type'] )
		: 'post';

	// Check for empty topic
	if ( empty( $object_id ) ) {
		bbp_add_error( 'bbp_subscription_object_id', __( '<strong>ERROR</strong>: Not found. What are you subscribing/unsubscribing to?', 'bbpress' ) );

	// Check nonce
	} elseif ( ! bbp_verify_nonce_request( 'toggle-subscription_' . $object_id ) ) {
		bbp_add_error( 'bbp_subscription_object_id', __( '<strong>ERROR</strong>: Are you sure you wanted to do that?', 'bbpress' ) );

	// Check current user's ability to edit the user
	} elseif ( ! current_user_can( 'edit_user', $user_id ) ) {
		bbp_add_error( 'bbp_subscription_permission', __( '<strong>ERROR</strong>: You do not have permission to edit subscriptions of that user.', 'bbpress' ) );
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
			$redirect = get_permalink( $object_id );
		}

		bbp_redirect( $redirect );

	// Fail! Handle errors
	} elseif ( 'bbp_unsubscribe' === $action ) {
		bbp_add_error( 'bbp_unsubscribe', __( '<strong>ERROR</strong>: There was a problem unsubscribing.', 'bbpress' ) );
	} elseif ( 'bbp_subscribe' === $action ) {
		bbp_add_error( 'bbp_subscribe',   __( '<strong>ERROR</strong>: There was a problem subscribing.', 'bbpress' ) );
	}

	return (bool) $success;
}

/** Query Helpers *************************************************************/

/**
 * These functions are no longer used in bbPress due to general performance
 * concerns on large installations. They are provided here for convenience and
 * backwards compatibility only.
 */

/**
 * Get a user's object IDs
 *
 * For the most part, you should not need to use this function, and may even
 * want to come up with a more efficient way to get IDs on your own. Nevertheless,
 * it is available here for your convenience, using the most efficient query
 * parameters available inside of the various query APIs.
 *
 * @since 2.6.0 bbPress (r6606)
 *
 * @param int    $user_id   The user id
 * @param string $rel_key   The relationship key
 * @param string $rel_type  The relationship type (usually 'post')
 * @param array  $args      The arguments to override defaults
 *
 * @return array|bool Results if user has objects, otherwise null
 */
function bbp_get_user_object_ids( $args = array() ) {
	$object_ids = $defaults = array();

	// Parse arguments
	$r = bbp_parse_args( $args, array(
		'user_id'     => 0,
		'object_type' => bbp_get_topic_post_type(),
		'rel_key'     => '',
		'rel_type'    => 'post',
		'filter'      => 'user_object_ids',
		'args'        => array()
	), 'get_user_object_ids' );

	// Sanitize arguments
	$r['user_id']     = bbp_get_user_id( $r['user_id'] );
	$r['rel_key']     = sanitize_key( $r['rel_key'] );
	$r['rel_type']    = sanitize_key( $r['rel_type'] );
	$r['object_type'] = sanitize_key( $r['object_type'] );
	$r['filter']      = sanitize_key( $r['filter'] );

	// Defaults
	if ( 'post' === $r['rel_type'] ) {
		$defaults = array(
			'fields'         => 'ids',
			'post_type'      => $r['object_type'],
			'posts_per_page' => -1,
			'meta_query'     => array( array(
				'key'     => $r['rel_key'],
				'value'   => $r['user_id'],
				'compare' => 'NUMERIC'
			),

			// Performance
			'nopaging'               => true,
			'suppress_filters'       => true,
			'update_post_term_cache' => false,
			'update_post_meta_cache' => false,
			'ignore_sticky_posts'    => true,
			'no_found_rows'          => true
		) );
	}

	// Parse arguments
	$query_args = bbp_parse_args( $r['args'], $defaults, "get_{$r['filter']}_args" );

	// Queries
	if ( 'post' === $r['rel_type'] ) {
		$query      = new WP_Query( $query_args );
		$object_ids = $query->posts;
	}

	// Filter & return
	return (array) apply_filters( "bbp_get_{$r['filter']}", $object_ids, $r, $args );
}

/**
 * Get array of forum IDs that a user can moderate
 *
 * @since 2.6.0 bbPress (r5834)
 *
 * @param int $user_id User id.
 *
 * @return array Return array of forum ids, or empty array
 */
function bbp_get_moderator_forum_ids( $user_id = 0 ) {
	return bbp_get_user_object_ids( array(
		'user_id'     => $user_id,
		'rel_key'     => '_bbp_moderator_id',
		'object_type' => bbp_get_forum_post_type(),
		'filter'      => 'moderator_forum_ids'
	) );
}

/**
 * Get a user's engaged topic ids
 *
 * @since 2.6.0 bbPress (r6320)
 *
 * @param int $user_id Optional. User id
 *
 * @return array Return array of topic ids, or empty array
 */
function bbp_get_user_engaged_topic_ids( $user_id = 0 ) {
	return bbp_get_user_object_ids( array(
		'user_id' => $user_id,
		'rel_key' => '_bbp_engagement',
		'filter'  => 'user_engaged_topic_ids'
	) );
}

/**
 * Get a user's favorite topic ids
 *
 * @since 2.0.0 bbPress (r2652)
 *
 * @param int $user_id Optional. User id
 *
 * @return array Return array of favorite topic ids, or empty array
 */
function bbp_get_user_favorites_topic_ids( $user_id = 0 ) {
	return bbp_get_user_object_ids( array(
		'user_id' => $user_id,
		'rel_key' => '_bbp_favorite',
		'filter'  => 'user_favorites_topic_ids'
	) );
}

/**
 * Get a user's subscribed forum ids
 *
 * @since 2.5.0 bbPress (r5156)
 *
 * @param int $user_id Optional. User id
 *
 * @return array Return array of subscribed forum ids, or empty array
 */
function bbp_get_user_subscribed_forum_ids( $user_id = 0 ) {
	return bbp_get_user_object_ids( array(
		'user_id'     => $user_id,
		'rel_key'     => '_bbp_subscription',
		'object_type' => bbp_get_forum_post_type(),
		'filter'      => 'user_subscribed_forum_ids'
	) );
}

/**
 * Get a user's subscribed topic ids
 *
 * @since 2.0.0 bbPress (r2668)
 *
 * @param int $user_id Optional. User id
 *
 * @return array Return array of subscribed topic ids, or empty array
 */
function bbp_get_user_subscribed_topic_ids( $user_id = 0 ) {
	return bbp_get_user_object_ids( array(
		'user_id' => $user_id,
		'rel_key' => '_bbp_subscription',
		'filter'  => 'user_subscribed_topic_ids'
	) );
}

/** Deprecated ****************************************************************/

/**
 * Get a user's subscribed topics
 *
 * @since 2.0.0 bbPress (r2668)
 * @deprecated 2.5.0 bbPress (r5156)
 *
 * @param int $user_id Optional. User id
 *
 * @return array|bool Results if user has subscriptions, otherwise false
 */
function bbp_get_user_subscriptions( $user_id = 0 ) {
	_deprecated_function( __FUNCTION__, '2.5', 'bbp_get_user_topic_subscriptions()' );
	$query = bbp_get_user_topic_subscriptions( $user_id );

	// Filter & return
	return apply_filters( 'bbp_get_user_subscriptions', $query, $user_id );
}

/**
 * Get the users who have subscribed to the forum
 *
 * @since 2.5.0 bbPress (r5156)
 * @deprecated 2.6.0 bbPress (r6543)
 *
 * @param int $forum_id Optional. forum id
 *
 * @return array|bool Results if the forum has any subscribers, otherwise false
 */
function bbp_get_forum_subscribers( $forum_id = 0 ) {
	$users = bbp_get_users_for_object( $forum_id, '_bbp_subscription' );

	// Filter & return
	return (array) apply_filters( 'bbp_get_forum_subscribers', $users, $forum_id );
}

/**
 * Get the users who have subscribed to the topic
 *
 * @since 2.0.0 bbPress (r2668)
 * @deprecated 2.6.0 bbPress (r6543)
 *
 * @param int $topic_id Optional. Topic id
 *
 * @return array|bool Results if the topic has any subscribers, otherwise false
 */
function bbp_get_topic_subscribers( $topic_id = 0 ) {
	$users = bbp_get_users_for_object( $topic_id, '_bbp_subscription' );

	// Filter & return
	return (array) apply_filters( 'bbp_get_topic_subscribers', $users, $topic_id );
}

/**
 * Check if a forum is in user's subscription list or not
 *
 * @since 2.5.0 bbPress (r5156)
 * @deprecated 2.6.0 bbPress (r6543)
 *
 * @param int $user_id  Optional. User id
 * @param int $forum_id Optional. Forum id
 *
 * @return bool True if the forum is in user's subscriptions, otherwise false
 */
function bbp_is_user_subscribed_to_forum( $user_id = 0, $forum_id = 0 ) {
	return bbp_is_user_subscribed( $user_id, $forum_id );
}

/**
 * Check if a topic is in user's subscription list or not
 *
 * @since 2.5.0 bbPress (r5156)
 * @deprecated 2.6.0 bbPress (r6543) Use bbp_is_user_subscribed()
 *
 * @param int $user_id Optional. User id
 * @param int $topic_id Optional. Topic id
 * @return bool True if the topic is in user's subscriptions, otherwise false
 */
function bbp_is_user_subscribed_to_topic( $user_id = 0, $topic_id = 0 ) {
	return bbp_is_user_subscribed( $user_id, $topic_id );
}

/**
 * Remove a forum from user's subscriptions
 *
 * @since 2.5.0 bbPress (r5156)
 * @deprecated 2.6.0 bbPress (r6543)
 *
 * @param int $user_id Optional. User id
 * @param int $forum_id Optional. forum id
 * @return bool True if the forum was removed from user's subscriptions,
 *               otherwise false
 */
function bbp_remove_user_forum_subscription( $user_id = 0, $forum_id = 0 ) {
	return bbp_remove_user_subscription( $user_id, $forum_id );
}

/**
 * Remove a topic from user's subscriptions
 *
 * @since 2.5.0 bbPress (r5156)
 * @deprecated 2.6.0 bbPress (r6543)
 *
 * @param int $user_id Optional. User id
 * @param int $topic_id Optional. Topic id
 * @return bool True if the topic was removed from user's subscriptions,
 *               otherwise false
 */
function bbp_remove_user_topic_subscription( $user_id = 0, $topic_id = 0 ) {
	return bbp_remove_user_subscription( $user_id, $topic_id );
}

/**
 * Add a forum to user's subscriptions
 *
 * @since 2.5.0 bbPress (r5156)
 * @deprecated 2.6.0 bbPress (r6543)
 *
 * @param int $user_id Optional. User id
 * @param int $forum_id Optional. forum id
 * @return bool Always true
 */
function bbp_add_user_forum_subscription( $user_id = 0, $forum_id = 0 ) {
	return bbp_add_user_subscription( $user_id, $forum_id );
}

/**
 * Add a topic to user's subscriptions
 *
 * Note that both the User and Topic should be verified to exist before using
 * this function. Originally both were validated, but because this function is
 * frequently used within a loop, those verifications were moved upstream to
 * improve performance on topics with many engaged users.
 *
 * @since 2.0.0 bbPress (r2668)
 * @deprecated 2.6.0 bbPress (r6543)
 *
 * @param int $user_id Optional. User id
 * @param int $topic_id Optional. Topic id
 * @return bool Always true
 */
function bbp_add_user_topic_subscription( $user_id = 0, $topic_id = 0 ) {
	return bbp_add_user_subscription( $user_id, $topic_id );
}

/**
 * Handles the front end toggling of forum subscriptions
 *
 * @since 2.5.0 bbPress (r5156)
 * @deprecated 2.6.0 bbPress (r6543)
 */
function bbp_forum_subscriptions_handler( $action = '' ) {
	return bbp_subscriptions_handler( $action );
}
