<?php

/**
 * bbp_is_anonymous ()
 *
 * Return true if anonymous is allowed and user is not logged in.
 * Return false if anonymous is not allowed or user is logged in
 *
 * @return bool
 */
function bbp_is_anonymous () {
	if ( !is_user_logged_in() && bbp_allow_anonymous() )
		$is_anonymous = true;
	else
		$is_anonymous = false;

	return apply_filters( 'bbp_is_anonymous', $is_anonymous );
}

/**
 * bbp_allow_anonymous ()
 *
 * Returns true|false if anonymous topic creation and replies are allowed
 *
 * @since bbPress (r2596)
 * @return bool
 */
function bbp_allow_anonymous () {
	return apply_filters( 'bbp_allow_anonymous', get_option( '_bbp_allow_anonymous', false ) );
}

/** START - Favorites *********************************************************/

/**
 * bbp_get_topic_favoriters ()
 *
 * Get the users who have made the topic favorite
 *
 * @package bbPress
 * @subpackage Users
 * @since bbPress (r2658)
 *
 * @param int $topic_id Topic ID
 * @return array|bool Results if the topic has any favoriters, otherwise false
 */
function bbp_get_topic_favoriters ( $topic_id = 0 ) {
	if ( empty( $topic_id ) )
		return;

	global $wpdb;

	// Get the users who have favorited the topic
	$users = $wpdb->get_col( "SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key = '_bbp_favorites' and FIND_IN_SET('{$topic_id}', meta_value) > 0" );
	$users = apply_filters( 'bbp_get_topic_favoriters', $users, $topic_id );

	if ( !empty( $users ) )
		return $users;

	return false;
}

/**
 * bbp_get_user_favorites ()
 *
 * Get a user's favorite topics
 *
 * @package bbPress
 * @subpackage Users
 * @since bbPress (r2652)
 *
 * @uses bbp_get_user_favorites_topic_ids ()
 *
 * @param int $user_id User ID
 * @return array|bool Results if user has favorites, otherwise false
 */
function bbp_get_user_favorites ( $user_id = 0 ) {
	if ( !$user_id = bbp_get_user_id( $user_id ) )
		return false;

	// If user has favorites, load them
	if ( $favorites = bbp_get_user_favorites_topic_ids( $user_id ) ) {
		$query = bbp_has_topics( array( 'post__in' => $favorites ) );
		return apply_filters( 'bbp_get_user_favorites', $query, $user_id );
	}

	return false;
}

	/**
	 * bbp_get_user_favorites_topic_ids ()
	 *
	 * Get a user's favorite topics' IDs
	 *
	 * @package bbPress
	 * @subpackage Users
	 * @since bbPress (r2652)
	 *
	 * @param int $user_id User ID
	 * @return array|bool Results if user has favorites, otherwise false
	 */
	function bbp_get_user_favorites_topic_ids ( $user_id = 0 ) {
		if ( !$user_id = bbp_get_user_id( $user_id ) )
			return false;

		$favorites = (string) get_user_meta( $user_id, '_bbp_favorites', true );
		$favorites = (array) explode( ',', $favorites );
		$favorites = array_filter( $favorites );

		return apply_filters( 'bbp_get_user_favorites_topic_ids', $favorites, $user_id );
	}

/**
 * bbp_is_user_favorite ()
 *
 * Check if a topic is in user's favorites or not
 *
 * @package bbPress
 * @subpackage Users
 * @since bbPress (r2652)
 *
 * @param int $user_id User ID
 * @param int $topic_id Topic ID
 * @return bool True if the topic is in user's favorites, otherwise false
 */
function bbp_is_user_favorite ( $user_id = 0, $topic_id = 0 ) {
	global $post, $bbp;

	if ( !$user_id = bbp_get_user_id( $user_id, true ) )
		return false;

	$favorites = bbp_get_user_favorites_topic_ids( $user_id );

	if ( !empty( $topic_id ) ) {
		$post = get_post( $topic_id );
		$topic_id = $post->ID;
	} elseif ( !$topic_id = bbp_get_topic_id() ) {
		if ( empty( $post ) )
			return false;

		$topic_id = $post->ID;
	}

	if ( empty( $favorites ) || empty( $topic_id ) )
		return false;

	if ( isset( $favorites ) )
		return apply_filters( 'bbp_is_user_favorite', (bool) in_array( $topic_id, $favorites ), $user_id, $topic_id, $favorites );

	return false;
}

/**
 * bbp_add_user_favorite ()
 *
 * Add a topic to user's favorites
 *
 * @package bbPress
 * @subpackage Users
 * @since bbPress (r2652)
 *
 * @param int $user_id User ID
 * @param int $topic_id Topic ID
 * @return bool True
 */
function bbp_add_user_favorite ( $user_id = 0, $topic_id = 0 ) {
	if ( empty( $user_id ) || empty( $topic_id ) )
		return false;

	$favorites = (array) bbp_get_user_favorites_topic_ids( $user_id );

	if ( !$topic = get_post( $topic_id ) )
		return false;

	if ( !in_array( $topic_id, $favorites ) ) {
		$favorites[] = $topic_id;
		$favorites   = array_filter( $favorites );
		$favorites   = (string) implode( ',', $favorites );
		update_user_meta( $user_id, '_bbp_favorites', $favorites );
	}

	do_action( 'bbp_add_user_favorite', $user_id, $topic_id );

	return true;
}

/**
 * bbp_remove_user_favorite ()
 *
 * Remove a topic from user's favorites
 *
 * @package bbPress
 * @subpackage Users
 * @since bbPress (r2652)
 *
 * @param int $user_id User ID
 * @param int $topic_id Topic ID
 * @return bool True if the topic was removed from user's favorites, otherwise false
 */
function bbp_remove_user_favorite ( $user_id, $topic_id ) {
	if ( empty( $user_id ) || empty( $topic_id ) )
		return false;

	if ( !$favorites = (array) bbp_get_user_favorites_topic_ids( $user_id ) )
		return false;

	if ( is_int( $pos = array_search( $topic_id, $favorites ) ) ) {
		array_splice( $favorites, $pos, 1 );
		$favorites = array_filter( $favorites );

		if ( !empty( $favorites ) ) {
			$favorites = implode( ',', $favorites );
			update_user_meta( $user_id, '_bbp_favorites', $favorites );
		} else {
			delete_user_meta( $user_id, '_bbp_favorites' );
		}
	}

	do_action( 'bbp_remove_user_favorite', $user_id, $topic_id );

	return true;
}

/** END - Favorites ***********************************************************/

/** START - Subscriptions *****************************************************/

/**
 * bbp_get_topic_subscribers ()
 *
 * Get the users who have subscribed to the topic
 *
 * @package bbPress
 * @subpackage Users
 * @since bbPress (r2668)
 *
 * @param int $topic_id Topic ID
 * @return array|bool Results if the topic has any subscribers, otherwise false
 */
function bbp_get_topic_subscribers ( $topic_id = 0 ) {
	if ( empty( $topic_id ) )
		return;

	global $wpdb;

	// Get the users who have favorited the topic
	$users = $wpdb->get_col( "SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key = '_bbp_subscriptions' and FIND_IN_SET('{$topic_id}', meta_value) > 0" );
	$users = apply_filters( 'bbp_get_topic_subscribers', $users );

	if ( !empty( $users ) )
		return $users;

	return false;
}

/**
 * bbp_get_user_subscriptions ()
 *
 * Get a user's subscribed topics
 *
 * @package bbPress
 * @subpackage Users
 * @since bbPress (r2668)
 *
 * @uses bbp_get_user_subscribed_topic_ids ()
 *
 * @param int $user_id User ID
 * @return array|bool Results if user has subscriptions, otherwise false
 */
function bbp_get_user_subscriptions ( $user_id = 0 ) {

	// Default to the displayed user
	if ( !$user_id = bbp_get_user_id( $user_id ) )
		return false;

	// If user has subscriptions, load them
	if ( $subscriptions = bbp_get_user_subscribed_topic_ids( $user_id ) ) {
		$query = bbp_has_topics( array( 'post__in' => $subscriptions ) );
		return apply_filters( 'bbp_get_user_subscriptions', $query, $user_id );
	}

	return false;
}

	/**
	 * bbp_get_user_subscribed_topic_ids ()
	 *
	 * Get a user's subscribed topics' IDs
	 *
	 * @package bbPress
	 * @subpackage Users
	 * @since bbPress (r2668)
	 *
	 * @param int $user_id User ID
	 * @return array|bool Results if user has subscriptions, otherwise false
	 */
	function bbp_get_user_subscribed_topic_ids ( $user_id = 0 ) {
		if ( !$user_id = bbp_get_user_id( $user_id ) )
			return false;

		$subscriptions = (string) get_user_meta( $user_id, '_bbp_subscriptions', true );
		$subscriptions = (array) explode( ',', $subscriptions );
		$subscriptions = array_filter( $subscriptions );

		return apply_filters( 'bbp_get_user_subscribed_topic_ids', $subscriptions );
	}

/**
 * bbp_is_user_subscribed ()
 *
 * Check if a topic is in user's subscription list or not
 *
 * @package bbPress
 * @subpackage Users
 * @since bbPress (r2668)
 *
 * @param int $user_id User ID
 * @param int $topic_id Topic ID
 * @return bool True if the topic is in user's subscriptions, otherwise false
 */
function bbp_is_user_subscribed ( $user_id = 0, $topic_id = 0 ) {
	global $bbp, $post;

	if ( !$user_id = bbp_get_user_id( $user_id ) )
		return false;

	$subscriptions = bbp_get_user_subscribed_topic_ids( $user_id );

	if ( !empty( $topic_id ) ) {
		$post     = get_post( $topic_id );
		$topic_id = $post->ID;
	} elseif ( !$topic_id = bbp_get_topic_id() ) {
		if ( empty( $post ) )
			return false;

		$topic_id = $post->ID;
	}

	if ( empty( $subscriptions ) || empty( $topic_id ) )
		return false;

	if ( isset( $subscriptions ) )
		return apply_filters( 'bbp_is_user_subscribed', (bool) in_array( $topic_id, $subscriptions ), $user_id, $topic_id, $subscriptions );

	return false;
}

/**
 * bbp_add_user_subscription ()
 *
 * Add a topic to user's subscriptions
 *
 * @package bbPress
 * @subpackage Users
 * @since bbPress (r2668)
 *
 * @param int $user_id User ID
 * @param int $topic_id Topic ID
 * @return bool True
 */
function bbp_add_user_subscription ( $user_id = 0, $topic_id = 0 ) {
	if ( empty( $user_id ) || empty( $topic_id ) )
		return false;

	$subscriptions = (array) bbp_get_user_subscribed_topic_ids( $user_id );

	if ( !$topic = get_post( $topic_id ) )
		return false;

	if ( !in_array( $topic_id, $subscriptions ) ) {
		$subscriptions[] = $topic_id;
		$subscriptions   = array_filter( $subscriptions );
		$subscriptions   = (string) implode( ',', $subscriptions );
		update_user_meta( $user_id, '_bbp_subscriptions', $subscriptions );
	}

	do_action( 'bbp_add_user_subscription', $user_id, $topic_id );

	return true;
}

/**
 * bbp_remove_user_subscription ()
 *
 * Remove a topic from user's subscriptions
 *
 * @package bbPress
 * @subpackage Users
 * @since bbPress (r2668)
 *
 * @param int $user_id User ID
 * @param int $topic_id Topic ID
 * @return bool True if the topic was removed from user's subscriptions, otherwise false
 */
function bbp_remove_user_subscription ( $user_id, $topic_id ) {
	if ( empty( $user_id ) || empty( $topic_id ) )
		return false;

	$subscriptions = (array) bbp_get_user_subscribed_topic_ids( $user_id );

	if ( empty( $subscriptions ) )
		return false;

	if ( is_int( $pos = array_search( $topic_id, $subscriptions ) ) ) {
		array_splice( $subscriptions, $pos, 1 );
		$subscriptions = array_filter( $subscriptions );

		if ( !empty( $subscriptions ) ) {
			$subscriptions = implode( ',', $subscriptions );
			update_user_meta( $user_id, '_bbp_subscriptions', $subscriptions );
		} else {
			delete_user_meta( $user_id, '_bbp_subscriptions' );
		}
	}

	do_action( 'bbp_remove_user_subscription', $user_id, $topic_id );

	return true;
}

/** END - Subscriptions *******************************************************/

/**
 * bbp_get_user_topics_started ()
 *
 * Get the topics that a user created
 *
 * @package bbPress
 * @subpackage Users
 * @since bbPress (r2660)
 *
 * @param int $user_id User ID
 * @return array|bool Results if user has favorites, otherwise false
 */
function bbp_get_user_topics_started ( $user_id = 0 ) {
	if ( !$user_id = bbp_get_user_id( $user_id ) )
		return false;

	if ( $query = bbp_has_topics( array( 'author' => $user_id, 'posts_per_page' => -1 ) ) )
		return $query;

	return false;
}

?>
