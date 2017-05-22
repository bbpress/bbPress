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
 * Set a user id on an object
 *
 * @since 2.6.0 bbPress (r6109)
 *
 * @param int    $object_id The object id
 * @param int    $user_id   The user id
 * @param string $meta_key  The relationship key
 * @param string $meta_type The relationship type
 *
 * @uses add_post_meta() To set the term on the object
 * @uses apply_filters() Calls 'bbp_add_user_to_object' with the object id, user
 *                        id, and taxonomy
 * @return bool Returns true if the user taxonomy term is added to the object,
 *               otherwise false
 */
function bbp_add_user_to_object( $object_id = 0, $user_id = 0, $meta_key = '', $meta_type = 'post' ) {
	$retval = add_metadata( $meta_type, $object_id, $meta_key, $user_id, false );

	return (bool) apply_filters( 'bbp_add_user_to_object', (bool) $retval, $object_id, $user_id, $meta_key, $meta_type );
}

/**
 * Remove a user id from an object
 *
 * @since 2.6.0 bbPress (r6109)
 *
 * @param int    $object_id The post id
 * @param int    $user_id   The user id
 * @param string $meta_key  The relationship key
 * @param string $meta_type The relationship type
 *
 * @uses delete_post_meta() To remove the term from the object
 * @uses apply_filters() Calls 'bbp_remove_user_from_object' with the object
 *                        id, user id, and taxonomy
 * @return bool Returns true is the user taxonomy term is removed from the object,
 *               otherwise false
 */
function bbp_remove_user_from_object( $object_id = 0, $user_id = 0, $meta_key = '', $meta_type = 'post' ) {
	$retval = delete_metadata( $meta_type, $object_id, $meta_key, $user_id, false );

	return (bool) apply_filters( 'bbp_remove_user_from_object', (bool) $retval, $object_id, $user_id, $meta_key, $meta_type );
}

/**
 * Get user taxonomy terms for an object
 *
 * @since 2.6.0 bbPress (r6109)
 *
 * @param int    $object_id The object id
 * @param string $meta_key  The key used to index this relationship
 * @param string $meta_type The type of meta to look in
 *
 * @uses get_post_meta() To get the user taxonomy terms
 * @uses apply_filters() Calls 'bbp_get_users_for_object' with the user
 *                        taxonomy terms, object id, and taxonomy
 * @return array Returns the user taxonomy terms of the object
 */
function bbp_get_users_for_object( $object_id = 0, $meta_key = '', $meta_type = 'post' ) {
	$meta   = get_metadata( $meta_type, $object_id, $meta_key, false );
	$retval = wp_parse_id_list( $meta );

	return (array) apply_filters( 'bbp_get_users_for_object', (array) $retval, $object_id, $meta_key, $meta_type );
}

/**
 * Check if the user id is set on an object
 *
 * @since 2.6.0 bbPress (r6109)
 *
 * @param int    $object_id The object id
 * @param int    $user_id   The user id
 * @param string $meta_key  The relationship key
 * @param string $meta_type The relationship type
 *
 * @uses get_post_meta() To check if the user id is set on the object
 * @uses apply_filters() Calls 'bbp_is_object_of_user' with the object id,
 *                        user id, and taxonomy
 * @return bool Returns true if the user id is set on the object for the
 *               taxonomy, otherwise false
 */
function bbp_is_object_of_user( $object_id = 0, $user_id = 0, $meta_key = '', $meta_type = 'post' ) {
	$user_ids = bbp_get_users_for_object( $object_id, $meta_key, $meta_type );
	$retval   = is_numeric( array_search( $user_id, $user_ids, true ) );

	return (bool) apply_filters( 'bbp_is_object_of_user', (bool) $retval, $object_id, $user_id, $meta_key, $meta_type );
}

/** Engagements ***************************************************************/

/**
 * Get the users who have engaged in a topic
 *
 * @since 2.6.0 bbPress (r6320)
 *
 * @param int $topic_id Optional. Topic id
 * @uses bbp_get_users_for_object() To get user ids who engaged
 * @uses apply_filters() Calls 'bbp_get_topic_engagements' with the users and
 *                        topic id
 * @return array|bool Results if the topic has any engagements, otherwise false
 */
function bbp_get_topic_engagements( $topic_id = 0 ) {
	$topic_id = bbp_get_topic_id( $topic_id );
	$users    = bbp_get_users_for_object( $topic_id, '_bbp_engagement' );

	return (array) apply_filters( 'bbp_get_topic_engagements', $users, $topic_id );
}

/**
 * Get a user's topic engagements
 *
 * @since 2.6.0 bbPress (r6320)
 *
 * @param int $user_id Optional. User id
 * @uses bbp_has_topics() To get the topics
 * @uses apply_filters() Calls 'bbp_get_user_engagements' with the topic query and
 *                        user id
 * @return array|bool Results if user has engaged, otherwise false
 */
function bbp_get_user_engagements( $user_id = 0 ) {
	$user_id     = bbp_get_user_id( $user_id );
	$engagements = bbp_has_topics( array(
		'meta_query' => array(
			array(
				'key'     => '_bbp_engagement',
				'value'   => $user_id,
				'compare' => 'NUMERIC'
			)
		)
	) );

	return apply_filters( 'bbp_get_user_engagements', $engagements, $user_id );
}

/**
 * Get a user's engaged topic ids
 *
 * @since 2.6.0 bbPress (r6320)
 *
 * @param int $user_id Optional. User id
 * @uses bbp_get_user_id() To get the user id
 * @uses bbp_get_topic_post_type() To get the topic post type
 * @uses apply_filters() Calls 'bbp_get_user_engaged_topic_ids' with
 *                        the engaged topics and user id
 * @return array|bool Results if user has engaged, otherwise null
 */
function bbp_get_user_engaged_topic_ids( $user_id = 0 ) {
	$user_id     = bbp_get_user_id( $user_id );
	$engagements = new WP_Query( array(
		'fields'        => 'ids',
		'post_type'     => bbp_get_topic_post_type(),
		'nopaging'      => true,
		'no_found_rows' => true,
		'meta_query'    => array( array(
			'key'     => '_bbp_engagement',
			'value'   => $user_id,
			'compare' => 'NUMERIC'
		) )
	) );

	return (array) apply_filters( 'bbp_get_user_engaged_topic_ids', $engagements->posts, $user_id );
}

/**
 * Check if a user is engaged in a topic or not
 *
 * @since 2.6.0 bbPress (r6320)
 *
 * @param int $user_id Optional. User id
 * @param int $topic_id Optional. Topic id
 * @uses bbp_get_user_id() To get the user id
 * @uses bbp_get_topic_id() To get the topic id
 * @uses bbp_is_object_of_user() To check if the user has engaged
 * @uses apply_filters() Calls 'bbp_is_user_engaged' with the bool, user id,
 *                        topic id and engagements
 * @return bool True if the topic is in user's engagements, otherwise false
 */
function bbp_is_user_engaged( $user_id = 0, $topic_id = 0 ) {
	$user_id  = bbp_get_user_id( $user_id, true, true );
	$topic_id = bbp_get_topic_id( $topic_id );
	$retval   = bbp_is_object_of_user( $topic_id, $user_id, '_bbp_engagement' );

	return (bool) apply_filters( 'bbp_is_user_engaged', (bool) $retval, $user_id, $topic_id );
}

/**
 * Add a topic to user's engagements
 *
 * @since 2.6.0 bbPress (r6320)
 *
 * @param int $user_id Optional. User id
 * @param int $topic_id Optional. Topic id
 * @uses bbp_is_user_engaged() To check if the user is engaged in a topic
 * @uses do_action() Calls 'bbp_add_user_engagement' with the user id and topic id
 * @return bool Always true
 */
function bbp_add_user_engagement( $user_id = 0, $topic_id = 0 ) {

	// Bail if not enough info
	if ( empty( $user_id ) || empty( $topic_id ) ) {
		return false;
	}

	// Bail if no topic
	$topic = bbp_get_topic( $topic_id );
	if ( empty( $topic ) ) {
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
 * @uses bbp_is_user_engaged() To check if the user is engaged in a topic
 * @uses do_action() Calls 'bbp_remove_user_engagement' with the user & topic id
 * @return bool True if the topic was removed from user's engagements, otherwise
 *               false
 */
function bbp_remove_user_engagement( $user_id, $topic_id ) {

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

/** Favorites *****************************************************************/

/**
 * Get the users who have made the topic favorite
 *
 * @since 2.0.0 bbPress (r2658)
 *
 * @param int $topic_id Optional. Topic id
 * @uses bbp_get_users_for_object() To get user IDs who favorited
 * @uses apply_filters() Calls 'bbp_get_topic_favoriters' with the users and
 *                        topic id
 * @return array|bool Results if the topic has any favoriters, otherwise false
 */
function bbp_get_topic_favoriters( $topic_id = 0 ) {
	$topic_id = bbp_get_topic_id( $topic_id );
	$users    = bbp_get_users_for_object( $topic_id, '_bbp_favorite' );

	return (array) apply_filters( 'bbp_get_topic_favoriters', $users, $topic_id );
}

/**
 * Get a user's favorite topics
 *
 * @since 2.0.0 bbPress (r2652)
 *
 * @param int $user_id Optional. User id
 * @uses bbp_has_topics() To get the topics
 * @uses apply_filters() Calls 'bbp_get_user_favorites' with the topic query and
 *                        user id
 * @return array|bool Results if user has favorites, otherwise false
 */
function bbp_get_user_favorites( $user_id = 0 ) {
	$user_id = bbp_get_user_id( $user_id );
	$query   = bbp_has_topics( array(
		'meta_query' => array(
			array(
				'key'     => '_bbp_favorite',
				'value'   => $user_id,
				'compare' => 'NUMERIC'
			)
		)
	) );

	return apply_filters( 'bbp_get_user_favorites', $query, $user_id );
}

/**
 * Get a user's favorite topic ids
 *
 * @since 2.0.0 bbPress (r2652)
 *
 * @param int $user_id Optional. User id
 * @uses bbp_get_user_id() To get the user id
 * @uses bbp_get_topic_post_type() To get the topic post type
 * @uses apply_filters() Calls 'bbp_get_user_favorites_topic_ids' with
 *                        the favorites and user id
 * @return array|bool Results if user has favorites, otherwise null
 */
function bbp_get_user_favorites_topic_ids( $user_id = 0 ) {
	$user_id   = bbp_get_user_id( $user_id );
	$favorites = new WP_Query( array(
		'fields'        => 'ids',
		'post_type'     => bbp_get_topic_post_type(),
		'nopaging'      => true,
		'no_found_rows' => true,
		'meta_query'    => array( array(
			'key'     => '_bbp_favorite',
			'value'   => $user_id,
			'compare' => 'NUMERIC'
		) )
	) );

	return (array) apply_filters( 'bbp_get_user_favorites_topic_ids', $favorites->posts, $user_id );
}

/**
 * Check if a topic is in user's favorites or not
 *
 * @since 2.0.0 bbPress (r2652)
 *
 * @param int $user_id Optional. User id
 * @param int $topic_id Optional. Topic id
 * @uses bbp_get_user_id() To get the user id
 * @uses bbp_get_user_favorites_topic_ids() To get the user favorites
 * @uses bbp_get_topic() To get the topic
 * @uses bbp_get_topic_id() To get the topic id
 * @uses bbp_is_object_of_user() To check if the user has a favorite
 * @uses apply_filters() Calls 'bbp_is_user_favorite' with the bool, user id,
 *                        topic id and favorites
 * @return bool True if the topic is in user's favorites, otherwise false
 */
function bbp_is_user_favorite( $user_id = 0, $topic_id = 0 ) {
	$retval    = false;
	$user_id   = bbp_get_user_id( $user_id, true, true );
	$favorites = bbp_get_user_favorites_topic_ids( $user_id );

	if ( ! empty( $favorites ) ) {

		// Checking a specific topic id
		if ( ! empty( $topic_id ) ) {
			$topic    = bbp_get_topic( $topic_id );
			$topic_id = ! empty( $topic ) ? $topic->ID : 0;

		// Using the global topic id
		} elseif ( bbp_get_topic_id() ) {
			$topic_id = bbp_get_topic_id();

		// Use the current post id
		} elseif ( ! bbp_get_topic_id() ) {
			$topic_id = get_the_ID();
		}

		// Is topic_id in the user's favorites
		if ( ! empty( $topic_id ) ) {
			$retval = bbp_is_object_of_user( $topic_id, $user_id, '_bbp_favorite' );
		}
	}

	return (bool) apply_filters( 'bbp_is_user_favorite', (bool) $retval, $user_id, $topic_id, $favorites );
}

/**
 * Add a topic to user's favorites
 *
 * @since 2.0.0 bbPress (r2652)
 *
 * @param int $user_id Optional. User id
 * @param int $topic_id Optional. Topic id
 * @uses bbp_is_user_favorite() To check if the topic is a user favorite
 * @uses do_action() Calls 'bbp_add_user_favorite' with the user id and topic id
 * @return bool Always true
 */
function bbp_add_user_favorite( $user_id = 0, $topic_id = 0 ) {

	// Bail if not enough info
	if ( empty( $user_id ) || empty( $topic_id ) ) {
		return false;
	}

	// Bail if no topic
	$topic = bbp_get_topic( $topic_id );
	if ( empty( $topic ) ) {
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
 * @uses bbp_is_user_favorite() To check if the topic is a user favorite
 * @uses do_action() Calls 'bbp_remove_user_favorite' with the user & topic id
 * @return bool True if the topic was removed from user's favorites, otherwise
 *               false
 */
function bbp_remove_user_favorite( $user_id, $topic_id ) {

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
 * @uses bbp_get_user_id() To get the user id
 * @uses bbp_verify_nonce_request() To verify the nonce and check the request
 * @uses current_user_can() To check if the current user can edit the user
 * @uses bbPress:errors:add() To log the error messages
 * @uses bbp_is_user_favorite() To check if the topic is in user's favorites
 * @uses bbp_remove_user_favorite() To remove the user favorite
 * @uses bbp_add_user_favorite() To add the user favorite
 * @uses do_action() Calls 'bbp_favorites_handler' with success, user id, topic
 *                    id and action
 * @uses bbp_is_favorites() To check if it's the favorites page
 * @uses bbp_get_favorites_link() To get the favorites page link
 * @uses bbp_get_topic_permalink() To get the topic permalink
 * @uses bbp_redirect() To redirect to the url
 */
function bbp_favorites_handler( $action = '' ) {

	if ( ! bbp_is_favorites_active() ) {
		return false;
	}

	// Bail if no topic ID is passed
	if ( empty( $_GET['topic_id'] ) ) {
		return;
	}

	// Setup possible get actions
	$possible_actions = array(
		'bbp_favorite_add',
		'bbp_favorite_remove',
	);

	// Bail if actions aren't meant for this function
	if ( ! in_array( $action, $possible_actions, true ) ) {
		return;
	}

	// What action is taking place?
	$topic_id = intval( $_GET['topic_id'] );
	$user_id  = bbp_get_user_id( 0, true, true );

	// Check for empty topic
	if ( empty( $topic_id ) ) {
		bbp_add_error( 'bbp_favorite_topic_id', __( '<strong>ERROR</strong>: No topic was found. Which topic are you marking/unmarking as favorite?', 'bbpress' ) );

	// Check nonce
	} elseif ( ! bbp_verify_nonce_request( 'toggle-favorite_' . $topic_id ) ) {
		bbp_add_error( 'bbp_favorite_nonce', __( '<strong>ERROR</strong>: Are you sure you wanted to do that?', 'bbpress' ) );

	// Check current user's ability to edit the user
	} elseif ( ! current_user_can( 'edit_user', $user_id ) ) {
		bbp_add_error( 'bbp_favorite_permission', __( '<strong>ERROR</strong>: You do not have permission to edit favorites for that user!.', 'bbpress' ) );
	}

	// Bail if errors
	if ( bbp_has_errors() ) {
		return;
	}

	/** No errors *************************************************************/

	$is_favorite = bbp_is_user_favorite( $user_id, $topic_id );
	$success     = false;

	if ( true === $is_favorite && 'bbp_favorite_remove' === $action ) {
		$success = bbp_remove_user_favorite( $user_id, $topic_id );
	} elseif ( false === $is_favorite && 'bbp_favorite_add' === $action ) {
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
	} elseif ( true === $is_favorite && 'bbp_favorite_remove' === $action ) {
		bbp_add_error( 'bbp_favorite_remove', __( '<strong>ERROR</strong>: There was a problem removing that topic from favorites.', 'bbpress' ) );
	} elseif ( false === $is_favorite && 'bbp_favorite_add' === $action ) {
		bbp_add_error( 'bbp_favorite_add',    __( '<strong>ERROR</strong>: There was a problem favoriting that topic.', 'bbpress' ) );
	}
}

/** Subscriptions *************************************************************/

/**
 * Get the users who have subscribed to the forum
 *
 * @since 2.5.0 bbPress (r5156)
 *
 * @param int $forum_id Optional. forum id
 * @uses bbp_get_users_for_object() To get the forum subscribers
 * @uses apply_filters() Calls 'bbp_get_forum_subscribers' with the subscribers
 * @return array|bool Results if the forum has any subscribers, otherwise false
 */
function bbp_get_forum_subscribers( $forum_id = 0 ) {
	$forum_id = bbp_get_forum_id( $forum_id );
	$users    = bbp_get_users_for_object( $forum_id, '_bbp_subscription' );

	return (array) apply_filters( 'bbp_get_forum_subscribers', $users, $forum_id );
}

/**
 * Get the users who have subscribed to the topic
 *
 * @since 2.0.0 bbPress (r2668)
 *
 * @param int $topic_id Optional. Topic id
 * @uses bbp_get_users_for_object() To get the topic subscribers
 * @uses apply_filters() Calls 'bbp_get_topic_subscribers' with the subscribers
 * @return array|bool Results if the topic has any subscribers, otherwise false
 */
function bbp_get_topic_subscribers( $topic_id = 0 ) {
	$topic_id = bbp_get_topic_id( $topic_id );
	$users    = bbp_get_users_for_object( $topic_id, '_bbp_subscription' );

	return (array) apply_filters( 'bbp_get_topic_subscribers', $users, $topic_id );
}

/**
 * Get a user's subscribed topics
 *
 * @since 2.0.0 bbPress (r2668)
 *
 * @deprecated 2.5.0 bbPress (r5156)
 *
 * @param int $user_id Optional. User id
 * @uses bbp_get_user_topic_subscriptions() To get the user's subscriptions
 * @return array|bool Results if user has subscriptions, otherwise false
 */
function bbp_get_user_subscriptions( $user_id = 0 ) {
	_deprecated_function( __FUNCTION__, 2.5, 'bbp_get_user_topic_subscriptions()' );
	$query = bbp_get_user_topic_subscriptions( $user_id );
	return apply_filters( 'bbp_get_user_subscriptions', $query, $user_id );
}

/**
 * Get a user's subscribed topics
 *
 * @since 2.0.0 bbPress (r2668)
 *
 * @param int $user_id Optional. User id
 * @uses bbp_has_topics() To get the topics
 * @uses apply_filters() Calls 'bbp_get_user_subscriptions' with the topic query
 *                        and user id
 * @return array|bool Results if user has subscriptions, otherwise false
 */
function bbp_get_user_topic_subscriptions( $user_id = 0 ) {
	$user_id = bbp_get_user_id( $user_id );
	$query   = bbp_has_topics( array(
		'meta_query' => array(
			array(
				'key'     => '_bbp_subscription',
				'value'   => $user_id,
				'compare' => 'NUMERIC'
			)
		)
	) );

	return apply_filters( 'bbp_get_user_topic_subscriptions', $query, $user_id );
}

/**
 * Get a user's subscribed forums
 *
 * @since 2.5.0 bbPress (r5156)
 *
 * @param int $user_id Optional. User id
 * @uses bbp_has_forums() To get the forums
 * @uses apply_filters() Calls 'bbp_get_user_forum_subscriptions' with the forum
 *                        query and user id
 * @return array|bool Results if user has subscriptions, otherwise false
 */
function bbp_get_user_forum_subscriptions( $user_id = 0 ) {
	$user_id = bbp_get_user_id( $user_id );
	$query   = bbp_has_forums( array(
		'meta_query' => array(
			array(
				'key'     => '_bbp_subscription',
				'value'   => $user_id,
				'compare' => 'NUMERIC'
			)
		)
	) );

	return apply_filters( 'bbp_get_user_forum_subscriptions', $query, $user_id );
}

/**
 * Get a user's subscribed forum ids
 *
 * @since 2.5.0 bbPress (r5156)
 *
 * @param int $user_id Optional. User id
 * @uses bbp_get_user_id() To get the user id
 * @uses bbp_get_forum_post_type() To get the forum post type
 * @uses apply_filters() Calls 'bbp_get_user_subscribed_forum_ids' with
 *                        the subscriptions and user id
 * @return array|bool Results if user has subscriptions, otherwise null
 */
function bbp_get_user_subscribed_forum_ids( $user_id = 0 ) {
	$user_id       = bbp_get_user_id( $user_id );
	$subscriptions = new WP_Query( array(
		'fields'        => 'ids',
		'post_type'     => bbp_get_forum_post_type(),
		'nopaging'      => true,
		'no_found_rows' => true,
		'meta_query'    => array( array(
			'key'     => '_bbp_subscription',
			'value'   => $user_id,
			'compare' => 'NUMERIC'
		) )
	) );

	return (array) apply_filters( 'bbp_get_user_subscribed_forum_ids', $subscriptions->posts, $user_id );
}

/**
 * Get a user's subscribed topics' ids
 *
 * @since 2.0.0 bbPress (r2668)
 *
 * @param int $user_id Optional. User id
 * @uses bbp_get_user_id() To get the user id
 * @uses bbp_get_topic_post_type() To get the topic post type
 * @uses apply_filters() Calls 'bbp_get_user_subscribed_topic_ids' with
 *                        the subscriptions and user id
 * @return array|bool Results if user has subscriptions, otherwise null
 */
function bbp_get_user_subscribed_topic_ids( $user_id = 0 ) {
	$user_id       = bbp_get_user_id( $user_id );
	$subscriptions = new WP_Query( array(
		'fields'        => 'ids',
		'post_type'     => bbp_get_topic_post_type(),
		'nopaging'      => true,
		'no_found_rows' => true,
		'meta_query' => array( array(
			'key'     => '_bbp_subscription',
			'value'   => $user_id,
			'compare' => 'NUMERIC'
		) )
	) );

	return (array) apply_filters( 'bbp_get_user_subscribed_topic_ids', $subscriptions->posts, $user_id );
}

/**
 * Check if a topic or forum is in user's subscription list or not
 *
 * @since 2.5.0 bbPress (r5156)
 *
 * @param int $user_id Optional. User id
 * @param int $object_id Optional. Topic id
 * @uses get_post() To get the post object
 * @uses bbp_get_user_subscribed_forum_ids() To get the user's forum subscriptions
 * @uses bbp_get_user_subscribed_topic_ids() To get the user's topic subscriptions
 * @uses bbp_get_forum_post_type() To get the forum post type
 * @uses bbp_get_topic_post_type() To get the topic post type
 * @uses apply_filters() Calls 'bbp_is_user_subscribed' with the bool, user id,
 *                        forum/topic id and subsriptions
 * @return bool True if the forum or topic is in user's subscriptions, otherwise false
 */
function bbp_is_user_subscribed( $user_id = 0, $object_id = 0 ) {

	// Assume user is not subscribed
	$retval = false;

	// Setup ID's array
	$subscribed_ids = array();

	// User and object ID's are passed
	if ( ! empty( $user_id ) && ! empty( $object_id ) ) {

		// Get the post type
		$post_type = get_post_type( $object_id );

		// Post exists, so check the types
		if ( ! empty( $post_type ) ) {

			switch( $post_type ) {

				// Forum
				case bbp_get_forum_post_type() :
					$subscribed_ids = bbp_get_user_subscribed_forum_ids( $user_id );
					$retval         = bbp_is_user_subscribed_to_forum( $user_id, $object_id, $subscribed_ids );
					break;

				// Topic (default)
				case bbp_get_topic_post_type() :
				default :
					$subscribed_ids = bbp_get_user_subscribed_topic_ids( $user_id );
					$retval         = bbp_is_user_subscribed_to_topic( $user_id, $object_id, $subscribed_ids );
					break;
			}
		}
	}

	return (bool) apply_filters( 'bbp_is_user_subscribed', $retval, $user_id, $object_id, $subscribed_ids );
}

/**
 * Check if a forum is in user's subscription list or not
 *
 * @since 2.5.0 bbPress (r5156)
 *
 * @param int $user_id Optional. User id
 * @param int $forum_id Optional. Topic id
 * @param array $subscribed_ids Optional. Array of forum ID's to check
 * @uses bbp_get_user_id() To get the user id
 * @uses bbp_get_forum() To get the forum
 * @uses bbp_get_forum_id() To get the forum id
 * @uses bbp_is_object_of_user() To check if the user has a subscription
 * @uses apply_filters() Calls 'bbp_is_user_subscribed_to_forum' with the bool, user id,
 *                        forum id and subsriptions
 * @return bool True if the forum is in user's subscriptions, otherwise false
 */
function bbp_is_user_subscribed_to_forum( $user_id = 0, $forum_id = 0, $subscribed_ids = array() ) {

	// Assume user is not subscribed
	$retval = false;

	// Validate user
	$user_id = bbp_get_user_id( $user_id, true, true );
	if ( ! empty( $user_id ) ) {

		// Get subscription ID's if none passed
		if ( empty( $subscribed_ids ) ) {
			$subscribed_ids = bbp_get_user_subscribed_forum_ids( $user_id );
		}

		// User has forum subscriptions
		if ( ! empty( $subscribed_ids ) ) {

			// Checking a specific forum id
			if ( ! empty( $forum_id ) ) {
				$forum    = bbp_get_forum( $forum_id );
				$forum_id = ! empty( $forum ) ? $forum->ID : 0;

			// Using the global forum id
			} elseif ( bbp_get_forum_id() ) {
				$forum_id = bbp_get_forum_id();

			// Use the current post id
			} elseif ( ! bbp_get_forum_id() ) {
				$forum_id = get_the_ID();
			}

			// Is forum_id in the user's subscriptions
			if ( ! empty( $forum_id ) ) {
				$retval = bbp_is_object_of_user( $forum_id, $user_id, '_bbp_subscription' );
			}
		}
	}

	return (bool) apply_filters( 'bbp_is_user_subscribed_to_forum', (bool) $retval, $user_id, $forum_id, $subscribed_ids );
}

/**
 * Check if a topic is in user's subscription list or not
 *
 * @since 2.5.0 bbPress (r5156)
 *
 * @param int $user_id Optional. User id
 * @param int $topic_id Optional. Topic id
 * @param array $subscribed_ids Optional. Array of topic ID's to check
 * @uses bbp_get_user_id() To get the user id
 * @uses bbp_get_topic() To get the topic
 * @uses bbp_get_topic_id() To get the topic id
 * @uses bbp_is_object_of_user() To check if the user is subscribed
 * @uses apply_filters() Calls 'bbp_is_user_subscribed_to_topic' with the bool, user id,
 *                        topic id and subsriptions
 * @return bool True if the topic is in user's subscriptions, otherwise false
 */
function bbp_is_user_subscribed_to_topic( $user_id = 0, $topic_id = 0, $subscribed_ids = array() ) {

	// Assume user is not subscribed
	$retval = false;

	// Validate user
	$user_id = bbp_get_user_id( $user_id, true, true );
	if ( ! empty( $user_id ) ) {

		// Get subscription ID's if none passed
		if ( empty( $subscribed_ids ) ) {
			$subscribed_ids = bbp_get_user_subscribed_topic_ids( $user_id );
		}

		// User has topic subscriptions
		if ( ! empty( $subscribed_ids ) ) {

			// Checking a specific topic id
			if ( ! empty( $topic_id ) ) {
				$topic    = bbp_get_topic( $topic_id );
				$topic_id = ! empty( $topic ) ? $topic->ID : 0;

			// Using the global topic id
			} elseif ( bbp_get_topic_id() ) {
				$topic_id = bbp_get_topic_id();

			// Use the current post id
			} elseif ( ! bbp_get_topic_id() ) {
				$topic_id = get_the_ID();
			}

			// Is topic_id in the user's subscriptions
			if ( ! empty( $topic_id ) ) {
				$retval = bbp_is_object_of_user( $topic_id, $user_id, '_bbp_subscription' );
			}
		}
	}

	return (bool) apply_filters( 'bbp_is_user_subscribed_to_topic', (bool) $retval, $user_id, $topic_id, $subscribed_ids );
}

/**
 * Add a user subscription
 *
 * @since 2.5.0 bbPress (r5156)
 *
 * @param int $user_id Optional. User id
 * @param int $object_id Optional. Topic id
 * @uses get_post() To get the post object
 * @uses do_action() Calls 'bbp_add_user_subscription' with the user & object id
 * @return bool Always true
 */
function bbp_add_user_subscription( $user_id = 0, $object_id = 0 ) {

	// Bail if not enough info
	if ( empty( $user_id ) || empty( $object_id ) ) {
		return false;
	}

	// Get the post type
	$post_type = get_post_type( $object_id );
	if ( empty( $post_type ) ) {
		return false;
	}

	// Bail if already subscribed
	if ( bbp_is_user_subscribed( $user_id, $object_id ) ) {
		return false;
	}

	// Bail if add fails
	if ( ! bbp_add_user_to_object( $object_id, $user_id, '_bbp_subscription' ) ) {
		return false;
	}

	do_action( 'bbp_add_user_subscription', $user_id, $object_id, $post_type );

	return true;
}

/**
 * Add a forum to user's subscriptions
 *
 * @since 2.5.0 bbPress (r5156)
 *
 * @param int $user_id Optional. User id
 * @param int $forum_id Optional. forum id
 * @uses bbp_get_forum() To get the forum
 * @uses bbp_add_user_subscription() To add the user subscription
 * @uses do_action() Calls 'bbp_add_user_subscription' with the user & forum id
 * @return bool Always true
 */
function bbp_add_user_forum_subscription( $user_id = 0, $forum_id = 0 ) {

	// Bail if not enough info
	if ( empty( $user_id ) || empty( $forum_id ) ) {
		return false;
	}

	// Bail if no forum
	$forum = bbp_get_forum( $forum_id );
	if ( empty( $forum ) ) {
		return false;
	}

	// Bail if already subscribed
	if ( bbp_is_user_subscribed( $user_id, $forum_id ) ) {
		return false;
	}

	// Bail if add fails
	if ( ! bbp_add_user_subscription( $user_id, $forum_id ) ) {
		return false;
	}

	do_action( 'bbp_add_user_forum_subscription', $user_id, $forum_id );

	return true;
}

/**
 * Add a topic to user's subscriptions
 *
 * @since 2.0.0 bbPress (r2668)
 *
 * @param int $user_id Optional. User id
 * @param int $topic_id Optional. Topic id
 * @uses bbp_get_topic() To get the topic
 * @uses bbp_add_user_subscription() To add the subscription
 * @uses do_action() Calls 'bbp_add_user_subscription' with the user & topic id
 * @return bool Always true
 */
function bbp_add_user_topic_subscription( $user_id = 0, $topic_id = 0 ) {

	// Bail if not enough info
	if ( empty( $user_id ) || empty( $topic_id ) ) {
		return false;
	}

	// Bail if no topic
	$topic = bbp_get_topic( $topic_id );
	if ( empty( $topic ) ) {
		return false;
	}

	// Bail if already subscribed
	if ( bbp_is_user_subscribed_to_topic( $user_id, $topic_id ) ) {
		return false;
	}

	// Bail if add fails
	if ( ! bbp_add_user_subscription( $user_id, $topic_id ) ) {
		return false;
	}

	do_action( 'bbp_add_user_topic_subscription', $user_id, $topic_id );

	return true;
}

/**
 * Remove a user subscription
 *
 * @since 2.0.0 bbPress (r2668)
 *
 * @param int $user_id Optional. User id
 * @param int $object_id Optional. Topic id
 * @uses get_post() To get the post object
 * @uses bbp_is_user_subscribed() To check if the user is already subscribed
 * @uses do_action() Calls 'bbp_remove_user_subscription' with the user id and
 *                    topic id
 * @return bool True if the topic was removed from user's subscriptions,
 *               otherwise false
 */
function bbp_remove_user_subscription( $user_id = 0, $object_id = 0 ) {

	// Bail if not enough info
	if ( empty( $user_id ) || empty( $object_id ) ) {
		return false;
	}

	// Get post type
	$post_type = get_post_type( $object_id );
	if ( empty( $post_type ) ) {
		return false;
	}

	// Bail if not subscribed
	if ( ! bbp_is_user_subscribed( $user_id, $object_id ) ) {
		return false;
	}

	// Bail if remove fails
	if ( ! bbp_remove_user_from_object( $object_id, $user_id, '_bbp_subscription' ) ) {
		return false;
	}

	do_action( 'bbp_remove_user_subscription', $user_id, $object_id, $post_type );

	return true;
}

/**
 * Remove a forum from user's subscriptions
 *
 * @since 2.5.0 bbPress (r5156)
 *
 * @param int $user_id Optional. User id
 * @param int $forum_id Optional. forum id
 * @uses bbp_remove_user_subscription() To remove the subscription
 * @uses do_action() Calls 'bbp_remove_user_subscription' with the user id and
 *                    forum id
 * @return bool True if the forum was removed from user's subscriptions,
 *               otherwise false
 */
function bbp_remove_user_forum_subscription( $user_id = 0, $forum_id = 0 ) {

	// Bail if not enough info
	if ( empty( $user_id ) || empty( $forum_id ) ) {
		return false;
	}

	// Bail if remove fails
	if ( ! bbp_remove_user_subscription( $user_id, $forum_id ) ) {
		return false;
	}

	do_action( 'bbp_remove_user_forum_subscription', $user_id, $forum_id );

	return true;
}

/**
 * Remove a topic from user's subscriptions
 *
 * @since 2.5.0 bbPress (r5156)
 *
 * @param int $user_id Optional. User id
 * @param int $topic_id Optional. Topic id
 * @uses bbp_remove_user_subscription() To remove the subscription
 * @uses do_action() Calls 'bbp_remove_user_topic_subscription' with the user id and
 *                    topic id
 * @return bool True if the topic was removed from user's subscriptions,
 *               otherwise false
 */
function bbp_remove_user_topic_subscription( $user_id = 0, $topic_id = 0 ) {

	// Bail if not enough info
	if ( empty( $user_id ) || empty( $topic_id ) ) {
		return false;
	}

	// Bail if remove fails
	if ( ! bbp_remove_user_subscription( $user_id, $topic_id ) ) {
		return false;
	}

	do_action( 'bbp_remove_user_topic_subscription', $user_id, $topic_id );

	return true;
}

/**
 * Handles the front end subscribing and unsubscribing forums
 *
 * @since 2.5.0 bbPress (r5156)
 *
 * @param string $action The requested action to compare this function to
 * @uses bbp_is_subscriptions_active() To check if the subscriptions are active
 * @uses bbp_get_user_id() To get the user id
 * @uses bbp_verify_nonce_request() To verify the nonce and check the request
 * @uses current_user_can() To check if the current user can edit the user
 * @uses bbPress:errors:add() To log the error messages
 * @uses bbp_is_user_subscribed() To check if the forum is in user's
 *                                 subscriptions
 * @uses bbp_remove_user_subscription() To remove the user subscription
 * @uses bbp_add_user_subscription() To add the user subscription
 * @uses do_action() Calls 'bbp_subscriptions_handler' with success, user id,
 *                    forum id and action
 * @uses bbp_is_subscription() To check if it's the subscription page
 * @uses bbp_get_forum_permalink() To get the forum permalink
 * @uses bbp_redirect() To redirect to the url
 */
function bbp_forum_subscriptions_handler( $action = '' ) {

	if ( ! bbp_is_subscriptions_active() ) {
		return false;
	}

	// Bail if no forum ID is passed
	if ( empty( $_GET['forum_id'] ) ) {
		return;
	}

	// Setup possible get actions
	$possible_actions = array(
		'bbp_subscribe',
		'bbp_unsubscribe',
	);

	// Bail if actions aren't meant for this function
	if ( ! in_array( $action, $possible_actions, true ) ) {
		return;
	}

	// Get required data
	$user_id  = bbp_get_user_id( 0, true, true );
	$forum_id = intval( $_GET['forum_id'] );

	// Check for empty forum
	if ( empty( $forum_id ) ) {
		bbp_add_error( 'bbp_subscription_forum_id', __( '<strong>ERROR</strong>: No forum was found. Which forum are you subscribing/unsubscribing to?', 'bbpress' ) );

	// Check nonce
	} elseif ( ! bbp_verify_nonce_request( 'toggle-subscription_' . $forum_id ) ) {
		bbp_add_error( 'bbp_subscription_forum_id', __( '<strong>ERROR</strong>: Are you sure you wanted to do that?', 'bbpress' ) );

	// Check current user's ability to edit the user
	} elseif ( ! current_user_can( 'edit_user', $user_id ) ) {
		bbp_add_error( 'bbp_subscription_permission', __( '<strong>ERROR</strong>: You do not have permission to edit favorites of that user.', 'bbpress' ) );
	}

	// Bail if we have errors
	if ( bbp_has_errors() ) {
		return;
	}

	/** No errors *************************************************************/

	$is_subscription = bbp_is_user_subscribed( $user_id, $forum_id );
	$success         = false;

	if ( true === $is_subscription && 'bbp_unsubscribe' === $action ) {
		$success = bbp_remove_user_subscription( $user_id, $forum_id );
	} elseif ( false === $is_subscription && 'bbp_subscribe' === $action ) {
		$success = bbp_add_user_subscription( $user_id, $forum_id );
	}

	// Do additional subscriptions actions
	do_action( 'bbp_subscriptions_handler', $success, $user_id, $forum_id, $action );

	// Success!
	if ( true === $success ) {

		// Redirect back from whence we came
		if ( ! empty( $_REQUEST['redirect_to'] ) ) {
			$redirect = $_REQUEST['redirect_to']; // Validated later
		} elseif ( bbp_is_subscriptions() ) {
			$redirect = bbp_get_subscriptions_permalink( $user_id );
		} elseif ( bbp_is_single_user() ) {
			$redirect = bbp_get_user_profile_url();
		} elseif ( is_singular( bbp_get_forum_post_type() ) ) {
			$redirect = bbp_get_forum_permalink( $forum_id );
		} elseif ( is_single() || is_page() ) {
			$redirect = get_permalink();
		} else {
			$redirect = get_permalink( $forum_id );
		}

		bbp_redirect( $redirect );

	// Fail! Handle errors
	} elseif ( true === $is_subscription && 'bbp_unsubscribe' === $action ) {
		bbp_add_error( 'bbp_unsubscribe', __( '<strong>ERROR</strong>: There was a problem unsubscribing from that forum.', 'bbpress' ) );
	} elseif ( false === $is_subscription && 'bbp_subscribe' === $action ) {
		bbp_add_error( 'bbp_subscribe',    __( '<strong>ERROR</strong>: There was a problem subscribing to that forum.', 'bbpress' ) );
	}
}

/**
 * Handles the front end subscribing and unsubscribing topics
 *
 * @since 2.0.0 bbPress (r2790)
 *
 * @param string $action The requested action to compare this function to
 * @uses bbp_is_subscriptions_active() To check if the subscriptions are active
 * @uses bbp_get_user_id() To get the user id
 * @uses bbp_verify_nonce_request() To verify the nonce and check the request
 * @uses current_user_can() To check if the current user can edit the user
 * @uses bbPress:errors:add() To log the error messages
 * @uses bbp_is_user_subscribed() To check if the topic is in user's
 *                                 subscriptions
 * @uses bbp_remove_user_subscription() To remove the user subscription
 * @uses bbp_add_user_subscription() To add the user subscription
 * @uses do_action() Calls 'bbp_subscriptions_handler' with success, user id,
 *                    topic id and action
 * @uses bbp_is_subscription() To check if it's the subscription page
 * @uses bbp_get_topic_permalink() To get the topic permalink
 * @uses bbp_redirect() To redirect to the url
 */
function bbp_subscriptions_handler( $action = '' ) {

	if ( ! bbp_is_subscriptions_active() ) {
		return false;
	}

	// Bail if no topic ID is passed
	if ( empty( $_GET['topic_id'] ) ) {
		return;
	}

	// Setup possible get actions
	$possible_actions = array(
		'bbp_subscribe',
		'bbp_unsubscribe',
	);

	// Bail if actions aren't meant for this function
	if ( ! in_array( $action, $possible_actions, true ) ) {
		return;
	}

	// Get required data
	$user_id  = bbp_get_user_id( 0, true, true );
	$topic_id = intval( $_GET['topic_id'] );

	// Check for empty topic
	if ( empty( $topic_id ) ) {
		bbp_add_error( 'bbp_subscription_topic_id', __( '<strong>ERROR</strong>: No topic was found. Which topic are you subscribing/unsubscribing to?', 'bbpress' ) );

	// Check nonce
	} elseif ( ! bbp_verify_nonce_request( 'toggle-subscription_' . $topic_id ) ) {
		bbp_add_error( 'bbp_subscription_topic_id', __( '<strong>ERROR</strong>: Are you sure you wanted to do that?', 'bbpress' ) );

	// Check current user's ability to edit the user
	} elseif ( ! current_user_can( 'edit_user', $user_id ) ) {
		bbp_add_error( 'bbp_subscription_permission', __( '<strong>ERROR</strong>: You do not have permission to edit favorites of that user.', 'bbpress' ) );
	}

	// Bail if we have errors
	if ( bbp_has_errors() ) {
		return;
	}

	/** No errors *************************************************************/

	$is_subscription = bbp_is_user_subscribed( $user_id, $topic_id );
	$success         = false;

	if ( true === $is_subscription && 'bbp_unsubscribe' === $action ) {
		$success = bbp_remove_user_subscription( $user_id, $topic_id );
	} elseif ( false === $is_subscription && 'bbp_subscribe' === $action ) {
		$success = bbp_add_user_subscription( $user_id, $topic_id );
	}

	// Do additional subscriptions actions
	do_action( 'bbp_subscriptions_handler', $success, $user_id, $topic_id, $action );

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
			$redirect = bbp_get_topic_permalink( $topic_id );
		} elseif ( is_single() || is_page() ) {
			$redirect = get_permalink();
		} else {
			$redirect = get_permalink( $topic_id );
		}

		bbp_redirect( $redirect );

	// Fail! Handle errors
	} elseif ( true === $is_subscription && 'bbp_unsubscribe' === $action ) {
		bbp_add_error( 'bbp_unsubscribe', __( '<strong>ERROR</strong>: There was a problem unsubscribing from that topic.', 'bbpress' ) );
	} elseif ( false === $is_subscription && 'bbp_subscribe' === $action ) {
		bbp_add_error( 'bbp_subscribe',    __( '<strong>ERROR</strong>: There was a problem subscribing to that topic.', 'bbpress' ) );
	}
}
