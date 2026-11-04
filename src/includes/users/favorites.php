<?php

/**
 * bbPress User Favorites.
 *
 * @package bbPress
 * @subpackage Users
 */

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

/** Favorites *****************************************************************/

/**
 * Get the users who have made the topic favorite.
 *
 * @since 2.0.0 bbPress (r2668)
 *
 * @param int $topic_id Optional. Topic id.
 *
 * @return array|bool Results if the topic has any favoriters, otherwise false.
 */
function bbp_get_topic_favoriters( $topic_id = 0 ) {
	$topic_id = bbp_get_topic_id( $topic_id );
	$users    = bbp_get_users_for_object( $topic_id, '_bbp_favorite' );

	// Filter & return
	return (array) apply_filters( 'bbp_get_topic_favoriters', $users, $topic_id );
}

/**
 * Get a user's favorite topics.
 *
 * @since 2.0.0 bbPress (r2652)
 * @since 2.6.0 bbPress (r6619) Signature changed to accept an array of arguments
 *
 * @param array $args Optional. Arguments to pass into bbp_has_topics().
 *
 * @return array Array of topics if user has favorites, otherwise empty array.
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
 * Check if a topic is in user's favorites or not.
 *
 * @since 2.0.0 bbPress (r2652)
 *
 * @param int $user_id Optional. User id.
 * @param int $topic_id Optional. Topic id.
 *
 * @return bool True if the topic is in user's favorites, otherwise false.
 */
function bbp_is_user_favorite( $user_id = 0, $topic_id = 0 ) {
	$retval = bbp_is_object_of_user( $topic_id, $user_id, '_bbp_favorite' );

	// Filter & return
	return (bool) apply_filters( 'bbp_is_user_favorite', $retval, $user_id, $topic_id );
}

/**
 * Add a topic to user's favorites.
 *
 * Note that both the User and Topic should be verified to exist before using
 * this function. Originally both were validated, but because this function is
 * frequently used within a loop, those verifications were moved upstream to
 * improve performance on topics with many engaged users.
 *
 * @since 2.0.0 bbPress (r2652)
 *
 * @param int $user_id Optional. User id.
 * @param int $topic_id Optional. Topic id.
 *
 * @return bool True if the topic was added to user's favorites, otherwise false.
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
 * Remove a topic from user's favorites.
 *
 * @since 2.0.0 bbPress (r2652)
 *
 * @param int $user_id Optional. User id.
 * @param int $topic_id Optional. Topic id.
 *
 * @return bool True if the topic was removed from user's favorites, otherwise false.
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
 * Handles the front end adding and removing of favorite topics.
 *
 * @since 2.0.0 bbPress (r2660)
 * @since 2.6.20 bbPress (r8024) Use the displayed user on favorites views.
 *
 * @param string $action The requested action to compare this function to.
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
	$topic_id     = bbp_get_topic_id( $_GET['object_id'] );
	$user_id      = bbp_is_favorites()
		? bbp_get_displayed_user_id()
		: bbp_get_current_user_id();
	$toggle_action = ( 'bbp_favorite_remove' === $action ) ? 'remove' : 'add';

	// Check for empty topic
	if ( empty( $topic_id ) || ! bbp_current_user_can_toggle_engagement( $topic_id, 'post', 'favorite', $toggle_action ) ) {
		bbp_add_error( 'bbp_favorite_topic_id', __( '<strong>Error</strong>: No topic was found. Which topic are you marking/unmarking as favorite?', 'bbpress' ) );

	// Check nonce
	} elseif ( ! bbp_verify_nonce_request( 'toggle-favorite_' . $topic_id ) ) {
		bbp_add_error( 'bbp_favorite_nonce', __( '<strong>Error</strong>: Are you sure you wanted to do that?', 'bbpress' ) );

	// Check current user's ability to edit the user
	} elseif ( ! bbp_current_user_can_manage_engagements( 'favorite', $user_id ) ) {
		bbp_add_error( 'bbp_favorite_permission', __( '<strong>Error</strong>: You do not have permission to edit favorites for that user.', 'bbpress' ) );
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
		bbp_add_error( 'bbp_favorite_remove', __( '<strong>Error</strong>: There was a problem removing that topic from favorites.', 'bbpress' ) );
	} elseif ( 'bbp_favorite_add' === $action ) {
		bbp_add_error( 'bbp_favorite_add',    __( '<strong>Error</strong>: There was a problem favoriting that topic.', 'bbpress' ) );
	}

	return (bool) $success;
}

/**
 * Get a user's favorite topic ids.
 *
 * @since 2.0.0 bbPress (r2652)
 *
 * @param int $user_id Optional. User id.
 *
 * @return array Return array of favorite topic ids, or empty array.
 */
function bbp_get_user_favorites_topic_ids( $user_id = 0 ) {
	return bbp_get_user_object_ids(
		array(
			'user_id' => $user_id,
			'rel_key' => '_bbp_favorite',
			'filter'  => 'user_favorites_topic_ids'
		)
	);
}
