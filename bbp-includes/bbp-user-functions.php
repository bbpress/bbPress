<?php

/**
 * bbPress User Functions
 *
 * @package bbPress
 * @subpackage Functions
 */

// Exit if accessed directly
if ( !defined( 'ABSPATH' ) ) exit;

/**
 * Redirect back to $url when attempting to use the login page
 *
 * @since bbPress (r2815)
 *
 * @param string $url The url
 * @param string $raw_url Raw url
 * @param object $user User object
 * @uses is_wp_error() To check if the user param is a {@link WP_Error}
 * @uses admin_url() To get the admin url
 * @uses home_url() To get the home url
 * @uses esc_url() To escape the url
 * @uses wp_safe_redirect() To redirect
 */
function bbp_redirect_login( $url = '', $raw_url = '', $user = '' ) {

	// Raw redirect_to was passed, so use it
	if ( !empty( $raw_url ) )
		$url = $raw_url;

	// $url was manually set in wp-login.php to redirect to admin
	elseif ( admin_url() == $url )
		$url = home_url();

	// $url is empty
	elseif ( empty( $url ) )
		$url = home_url();

	return apply_filters( 'bbp_redirect_login', $url, $raw_url, $user );
}

/**
 * Is an anonymous topic/reply being made?
 *
 * @since bbPres (r2688)
 *
 * @uses is_user_logged_in() Is the user logged in?
 * @uses bbp_allow_anonymous() Is anonymous posting allowed?
 * @uses apply_filters() Calls 'bbp_is_anonymous' with the return value
 * @return bool True if anonymous is allowed and user is not logged in, false if
 *               anonymous is not allowed or user is logged in
 */
function bbp_is_anonymous() {
	if ( !is_user_logged_in() && bbp_allow_anonymous() )
		$is_anonymous = true;
	else
		$is_anonymous = false;

	return apply_filters( 'bbp_is_anonymous', $is_anonymous );
}

/**
 * Echoes the values for current poster (uses WP comment cookies)
 *
 * @since bbPress (r2734)
 *
 * @param string $key Which value to echo?
 * @uses bbp_get_current_anonymous_user_data() To get the current anonymous user
 *                                              data
 */
function bbp_current_anonymous_user_data( $key = '' ) {
	echo bbp_get_current_anonymous_user_data( $key );
}

	/**
	 * Get the cookies for current poster (uses WP comment cookies).
	 *
	 * @since bbPress (r2734)
	 *
	 * @param string $key Optional. Which value to get? If not given, then
	 *                     an array is returned.
	 * @uses sanitize_comment_cookies() To sanitize the current poster data
	 * @uses wp_get_current_commenter() To get the current poster data	 *
	 * @return string|array Cookie(s) for current poster
	 */
	function bbp_get_current_anonymous_user_data( $key = '' ) {
		$cookie_names = array(
			'name'    => 'comment_author',
			'email'   => 'comment_author_email',
			'website' => 'comment_author_url',

			// Here just for the sake of them, use the above ones
			'comment_author'       => 'comment_author',
			'comment_author_email' => 'comment_author_email',
			'comment_author_url'   => 'comment_author_url',
		);

		sanitize_comment_cookies();

		$bbp_current_poster = wp_get_current_commenter();

		if ( !empty( $key ) && in_array( $key, array_keys( $cookie_names ) ) )
			return $bbp_current_poster[$cookie_names[$key]];

		return $bbp_current_poster;
	}

/**
 * Set the cookies for current poster (uses WP comment cookies)
 *
 * @since bbPress (r2734)
 *
 * @param array $anonymous_data With keys 'bbp_anonymous_name',
 *                               'bbp_anonymous_email', 'bbp_anonymous_website'.
 *                               Should be sanitized (see
 *                               {@link bbp_filter_anonymous_post_data()} for
 *                               sanitization)
 * @uses apply_filters() Calls 'comment_cookie_lifetime' for cookie lifetime.
 *                        Defaults to 30000000.
 */
function bbp_set_current_anonymous_user_data( $anonymous_data = array() ) {
	if ( empty( $anonymous_data ) || !is_array( $anonymous_data ) )
		return;

	$comment_cookie_lifetime = apply_filters( 'comment_cookie_lifetime', 30000000 );

	setcookie( 'comment_author_'       . COOKIEHASH, $anonymous_data['bbp_anonymous_name'],    time() + $comment_cookie_lifetime, COOKIEPATH, COOKIE_DOMAIN );
	setcookie( 'comment_author_email_' . COOKIEHASH, $anonymous_data['bbp_anonymous_email'],   time() + $comment_cookie_lifetime, COOKIEPATH, COOKIE_DOMAIN );
	setcookie( 'comment_author_url_'   . COOKIEHASH, $anonymous_data['bbp_anonymous_website'], time() + $comment_cookie_lifetime, COOKIEPATH, COOKIE_DOMAIN );
}

/**
 * Get the poster IP address
 *
 * @since bbPress (r3120)
 *
 * @return string
 */
function bbp_current_author_ip() {
	$retval = preg_replace( '/[^0-9a-fA-F:., ]/', '', $_SERVER['REMOTE_ADDR'] );

	return apply_filters( 'bbp_current_author_ip', $retval );
}

/** Favorites *****************************************************************/

/**
 * Get the users who have made the topic favorite
 *
 * @since bbPress (r2658)
 *
 * @param int $topic_id Optional. Topic id
 * @uses wpdb::get_col() To execute our query and get the column back
 * @uses apply_filters() Calls 'bbp_get_topic_favoriters' with the users and
 *                        topic id
 * @return array|bool Results if the topic has any favoriters, otherwise false
 */
function bbp_get_topic_favoriters( $topic_id = 0 ) {
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
 * Get a user's favorite topics
 *
 * @since bbPress (r2652)
 *
 * @param int $user_id Optional. User id
 * @uses bbp_get_user_favorites_topic_ids() To get the user's favorites
 * @uses bbp_has_topics() To get the topics
 * @uses apply_filters() Calls 'bbp_get_user_favorites' with the topic query and
 *                        user id
 * @return array|bool Results if user has favorites, otherwise false
 */
function bbp_get_user_favorites( $user_id = 0 ) {
	if ( !$user_id = bbp_get_user_id( $user_id ) )
		return false;

	// If user has favorites, load them
	if ( $favorites = bbp_get_user_favorites_topic_ids( $user_id ) ) {

		// Setup the topics query
		$topics_query = bbp_has_topics( array( 'post__in' => $favorites ) );

		return apply_filters( 'bbp_get_user_favorites', $topics_query, $user_id );
	}

	return false;
}

	/**
	 * Get a user's favorite topics' ids
	 *
	 * @since bbPress (r2652)
	 *
	 * @param int $user_id Optional. User id
	 * @uses bbp_get_user_id() To get the user id
	 * @uses get_user_meta() To get the user favorites
	 * @uses apply_filters() Calls 'bbp_get_user_favorites_topic_ids' with
	 *                        the favorites and user id
	 * @return array|bool Results if user has favorites, otherwise false
	 */
	function bbp_get_user_favorites_topic_ids( $user_id = 0 ) {
		if ( !$user_id = bbp_get_user_id( $user_id ) )
			return false;

		$favorites = (string) get_user_meta( $user_id, '_bbp_favorites', true );
		$favorites = (array) explode( ',', $favorites );
		$favorites = array_filter( $favorites );

		return apply_filters( 'bbp_get_user_favorites_topic_ids', $favorites, $user_id );
	}

/**
 * Check if a topic is in user's favorites or not
 *
 * @since bbPress (r2652)
 *
 * @param int $user_id Optional. User id
 * @param int $topic_id Optional. Topic id
 * @uses bbp_get_user_id() To get the user id
 * @uses bbp_get_user_favorites_topic_ids() To get the user favorites
 * @uses bbp_get_topic() To get the topic
 * @uses bbp_get_topic_id() To get the topic id
 * @uses apply_filters() Calls 'bbp_is_user_favorite' with the bool, user id,
 *                        topic id and favorites
 * @return bool True if the topic is in user's favorites, otherwise false
 */
function bbp_is_user_favorite( $user_id = 0, $topic_id = 0 ) {
	global $post;

	if ( !$user_id = bbp_get_user_id( $user_id, true, true ) )
		return false;

	$favorites = bbp_get_user_favorites_topic_ids( $user_id );

	if ( !empty( $topic_id ) ) {
		$topic    = bbp_get_topic( $topic_id );
		$topic_id = !empty( $topic ) ? $topic->ID : 0;
	} elseif ( !$topic_id = bbp_get_topic_id() ) {
		if ( empty( $post ) )
			return false;

		$topic_id = $post->ID;
	}

	if ( empty( $favorites ) || empty( $topic_id ) )
		return false;

	return apply_filters( 'bbp_is_user_favorite', (bool) in_array( $topic_id, $favorites ), $user_id, $topic_id, $favorites );
}

/**
 * Add a topic to user's favorites
 *
 * @since bbPress (r2652)
 *
 * @param int $user_id Optional. User id
 * @param int $topic_id Optional. Topic id
 * @uses bbp_get_user_favorites_topic_ids() To get the user favorites
 * @uses update_user_meta() To update the user favorites
 * @uses do_action() Calls 'bbp_add_user_favorite' with the user id and topic id
 * @return bool Always true
 */
function bbp_add_user_favorite( $user_id = 0, $topic_id = 0 ) {
	if ( empty( $user_id ) || empty( $topic_id ) )
		return false;

	$favorites = (array) bbp_get_user_favorites_topic_ids( $user_id );

	if ( !$topic = bbp_get_topic( $topic_id ) )
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
 * Remove a topic from user's favorites
 *
 * @since bbPress (r2652)
 *
 * @param int $user_id Optional. User id
 * @param int $topic_id Optional. Topic id
 * @uses bbp_get_user_favorites_topic_ids() To get the user favorites
 * @uses update_user_meta() To update the user favorites
 * @uses delete_user_meta() To delete the user favorites meta
 * @uses do_action() Calls 'bbp_remove_user_favorite' with the user & topic id
 * @return bool True if the topic was removed from user's favorites, otherwise
 *               false
 */
function bbp_remove_user_favorite( $user_id, $topic_id ) {
	if ( empty( $user_id ) || empty( $topic_id ) )
		return false;

	if ( !$favorites = (array) bbp_get_user_favorites_topic_ids( $user_id ) )
		return false;

	if ( is_numeric( $pos = array_search( $topic_id, $favorites ) ) ) {
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

/**
 * Handles the front end adding and removing of favorite topics
 *
 * @uses bbp_get_user_id() To get the user id
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
 * @uses wp_redirect() To redirect to the url
 */
function bbp_favorites_handler() {

	if ( !bbp_is_favorites_active() )
		return false;

	// Bail if not a GET action
	if ( 'GET' !== strtoupper( $_SERVER['REQUEST_METHOD'] ) )
		return;

	// Bail if required GET actions aren't passed
	if ( empty( $_GET['topic_id'] ) || empty( $_GET['action'] ) )
		return;

	// Setup possible get actions
	$possible_actions = array(
		'bbp_favorite_add',
		'bbp_favorite_remove',
	);

	// Bail if actions aren't meant for this function
	if ( !in_array( $_GET['action'], $possible_actions ) )
		return;

	// What action is taking place?
	$action  = $_GET['action'];

	// Get user_id
	$user_id = bbp_get_user_id( 0, true, true );

	// Check current user's ability to edit the user
	if ( !current_user_can( 'edit_user', $user_id ) )
		bbp_add_error( 'bbp_favorite_permissions', __( '<strong>ERROR</strong>: You don\'t have the permission to edit favorites of that user!', 'bbpress' ) );

	// Load favorite info
	if ( !$topic_id = intval( $_GET['topic_id'] ) )
		bbp_add_error( 'bbp_favorite_topic_id', __( '<strong>ERROR</strong>: No topic was found! Which topic are you marking/unmarking as favorite?', 'bbpress' ) );

	$is_favorite = bbp_is_user_favorite( $user_id, $topic_id );
	$success     = false;

	// Handle insertion into posts table
	if ( !empty( $topic_id ) && !empty( $user_id ) && ( !bbp_has_errors() ) ) {

		if ( $is_favorite && 'bbp_favorite_remove' == $action ) {
			$success = bbp_remove_user_favorite( $user_id, $topic_id );
		} elseif ( !$is_favorite && 'bbp_favorite_add' == $action ) {
			$success = bbp_add_user_favorite( $user_id, $topic_id );
		}

		// Do additional favorites actions
		do_action( 'bbp_favorites_handler', $success, $user_id, $topic_id, $action );

		// Check for missing reply_id or error
		if ( true == $success ) {

			// Redirect back to new reply
			if ( bbp_is_favorites() ) {
				$redirect = bbp_get_favorites_permalink( $user_id );
			} elseif ( bbp_is_single_user() ) {
				$redirect = bbp_get_user_profile_url();
			} elseif ( is_singular( bbp_get_topic_post_type() ) ) {
				$redirect = bbp_get_topic_permalink( $topic_id );
			} elseif ( is_single() || is_page() ) {
				$redirect = get_permalink();
			}

			wp_redirect( $redirect );

			// For good measure
			exit();

		// Handle errors
		} else {
			if ( $is_favorite && 'bbp_favorite_remove' == $action ) {
				bbp_add_error( 'bbp_favorite_remove', __( '<strong>ERROR</strong>: There was a problem removing that topic from favorites!', 'bbpress' ) );
			} elseif ( !$is_favorite && 'bbp_favorite_add' == $action ) {
				bbp_add_error( 'bbp_favorite_add',    __( '<strong>ERROR</strong>: There was a problem favoriting that topic!', 'bbpress' ) );
			}
		}
	}
}

/** Subscriptions *************************************************************/

/**
 * Get the users who have subscribed to the topic
 *
 * @since bbPress (r2668)
 *
 * @param int $topic_id Optional. Topic id
 * @uses wpdb::get_col() To execute our query and get the column back
 * @uses apply_filters() Calls 'bbp_get_topic_subscribers' with the subscribers
 * @return array|bool Results if the topic has any subscribers, otherwise false
 */
function bbp_get_topic_subscribers( $topic_id = 0 ) {
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
 * Get a user's subscribed topics
 *
 * @since bbPress (r2668)
 *
 * @param int $user_id Optional. User id
 * @uses bbp_get_user_subscribed_topic_ids() To get the user's subscriptions
 * @uses bbp_has_topics() To get the topics
 * @uses apply_filters() Calls 'bbp_get_user_subscriptions' with the topic query
 *                        and user id
 * @return array|bool Results if user has subscriptions, otherwise false
 */
function bbp_get_user_subscriptions( $user_id = 0 ) {

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
	 * Get a user's subscribed topics' ids
	 *
	 * @since bbPress (r2668)
	 *
	 * @param int $user_id Optional. User id
	 * @uses bbp_get_user_id() To get the user id
	 * @uses get_user_meta() To get the user's subscriptions
	 * @uses apply_filters() Calls 'bbp_get_user_subscribed_topic_ids' with
	 *                        the subscriptions and user id
	 * @return array|bool Results if user has subscriptions, otherwise false
	 */
	function bbp_get_user_subscribed_topic_ids( $user_id = 0 ) {
		if ( !$user_id = bbp_get_user_id( $user_id ) )
			return false;

		$subscriptions = (string) get_user_meta( $user_id, '_bbp_subscriptions', true );
		$subscriptions = (array) explode( ',', $subscriptions );
		$subscriptions = array_filter( $subscriptions );

		return apply_filters( 'bbp_get_user_subscribed_topic_ids', $subscriptions, $user_id );
	}

/**
 * Check if a topic is in user's subscription list or not
 *
 * @since bbPress (r2668)
 *
 * @param int $user_id Optional. User id
 * @param int $topic_id Optional. Topic id
 * @uses bbp_get_user_id() To get the user id
 * @uses bbp_get_user_subscribed_topic_ids() To get the user's subscriptions
 * @uses bbp_get_topic() To get the topic
 * @uses bbp_get_topic_id() To get the topic id
 * @uses apply_filters() Calls 'bbp_is_user_subscribed' with the bool, user id,
 *                        topic id and subsriptions
 * @return bool True if the topic is in user's subscriptions, otherwise false
 */
function bbp_is_user_subscribed( $user_id = 0, $topic_id = 0 ) {
	global $post;

	if ( !$user_id = bbp_get_user_id( $user_id, true, true ) )
		return false;

	$subscriptions = bbp_get_user_subscribed_topic_ids( $user_id );

	if ( !empty( $topic_id ) ) {
		$topic     = bbp_get_topic( $topic_id );
		$topic_id = !empty( $topic ) ? $topic->ID : 0;
	} elseif ( !$topic_id = bbp_get_topic_id() ) {
		if ( empty( $post ) )
			return false;

		$topic_id = $post->ID;
	}

	if ( empty( $subscriptions ) || empty( $topic_id ) )
		return false;

	return apply_filters( 'bbp_is_user_subscribed', (bool) in_array( $topic_id, $subscriptions ), $user_id, $topic_id, $subscriptions );
}

/**
 * Add a topic to user's subscriptions
 *
 * @since bbPress (r2668)
 *
 * @param int $user_id Optional. User id
 * @param int $topic_id Optional. Topic id
 * @uses bbp_get_user_subscribed_topic_ids() To get the user's subscriptions
 * @uses bbp_get_topic() To get the topic
 * @uses update_user_meta() To update the user's subscriptions
 * @uses do_action() Calls 'bbp_add_user_subscription' with the user & topic id
 * @return bool Always true
 */
function bbp_add_user_subscription( $user_id = 0, $topic_id = 0 ) {
	if ( empty( $user_id ) || empty( $topic_id ) )
		return false;

	$subscriptions = (array) bbp_get_user_subscribed_topic_ids( $user_id );

	if ( !$topic = bbp_get_topic( $topic_id ) )
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
 * Remove a topic from user's subscriptions
 *
 * @since bbPress (r2668)
 *
 * @param int $user_id Optional. User id
 * @param int $topic_id Optional. Topic id
 * @uses bbp_get_user_subscribed_topic_ids() To get the user's subscriptions
 * @uses update_user_meta() To update the user's subscriptions
 * @uses delete_user_meta() To delete the user's subscriptions meta
 * @uses do_action() Calls 'bbp_remove_user_subscription' with the user id and
 *                    topic id
 * @return bool True if the topic was removed from user's subscriptions,
 *               otherwise false
 */
function bbp_remove_user_subscription( $user_id, $topic_id ) {
	if ( empty( $user_id ) || empty( $topic_id ) )
		return false;

	$subscriptions = (array) bbp_get_user_subscribed_topic_ids( $user_id );

	if ( empty( $subscriptions ) )
		return false;

	if ( is_numeric( $pos = array_search( $topic_id, $subscriptions ) ) ) {
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

/**
 * Handles the front end subscribing and unsubscribing topics
 *
 * @uses bbp_is_subscriptions_active() To check if the subscriptions are active
 * @uses bbp_get_user_id() To get the user id
 * @uses current_user_can() To check if the current user can edit the user
 * @uses bbPress:errors:add() To log the error messages
 * @uses bbp_is_user_subscribed() To check if the topic is in user's
 *                                 subscriptions
 * @uses bbp_remove_user_subscription() To remove the user subscription
 * @uses bbp_add_user_subscription() To add the user subscription
 * @uses do_action() Calls 'bbp_subscriptions_handler' with success, user id,
 *                    topic id and action
 * @uses bbp_is_subscription() To check if it's the subscription page
 * @uses bbp_get_subscription_link() To get the subscription page link
 * @uses bbp_get_topic_permalink() To get the topic permalink
 * @uses wp_redirect() To redirect to the url
 */
function bbp_subscriptions_handler() {

	if ( !bbp_is_subscriptions_active() )
		return false;

	// Bail if not a GET action
	if ( 'GET' !== strtoupper( $_SERVER['REQUEST_METHOD'] ) )
		return;

	// Bail if required GET actions aren't passed
	if ( empty( $_GET['topic_id'] ) || empty( $_GET['action'] ) )
		return;

	// Setup possible get actions
	$possible_actions = array(
		'bbp_subscribe',
		'bbp_unsubscribe',
	);

	// Bail if actions aren't meant for this function
	if ( !in_array( $_GET['action'], $possible_actions ) )
		return;

	// What action is taking place?
	$action  = $_GET['action'];

	// Get user_id
	$user_id = bbp_get_user_id( 0, true, true );

	// Check current user's ability to edit the user
	if ( !current_user_can( 'edit_user', $user_id ) )
		bbp_add_error( 'bbp_subscription_permissions', __( '<strong>ERROR</strong>: You don\'t have the permission to edit favorites of that user!', 'bbpress' ) );

	// Load subscription info
	if ( !$topic_id = intval( $_GET['topic_id'] ) )
		bbp_add_error( 'bbp_subscription_topic_id', __( '<strong>ERROR</strong>: No topic was found! Which topic are you subscribing/unsubscribing to?', 'bbpress' ) );

	if ( !bbp_has_errors() ) {

		$is_subscription = bbp_is_user_subscribed( $user_id, $topic_id );
		$success         = false;

		if ( $is_subscription && 'bbp_unsubscribe' == $action ) {
			$success = bbp_remove_user_subscription( $user_id, $topic_id );
		} elseif ( !$is_subscription && 'bbp_subscribe' == $action ) {
			$success = bbp_add_user_subscription( $user_id, $topic_id );
		}

		// Do additional subscriptions actions
		do_action( 'bbp_subscriptions_handler', $success, $user_id, $topic_id, $action );

		// Check for missing reply_id or error
		if ( true == $success ) {

			// Redirect back to new reply
			if ( bbp_is_subscriptions() ) {
				$redirect = bbp_get_subscriptions_permalink( $user_id );
			} elseif( bbp_is_single_user() ) {
				$redirect = bbp_get_user_profile_url();
			} elseif ( is_singular( bbp_get_topic_post_type() ) ) {
				$redirect = bbp_get_topic_permalink( $topic_id );
			} elseif ( is_single() || is_page() ) {
				$redirect = get_permalink();
			}

			wp_redirect( $redirect );

			// For good measure
			exit();

		// Handle errors
		} else {
			if ( $is_subscription && 'bbp_unsubscribe' == $action ) {
				bbp_add_error( 'bbp_unsubscribe', __( '<strong>ERROR</strong>: There was a problem unsubscribing from that topic!', 'bbpress' ) );
			} elseif ( !$is_subscription && 'bbp_subscribe' == $action ) {
				bbp_add_error( 'bbp_subscribe',    __( '<strong>ERROR</strong>: There was a problem subscribing to that topic!', 'bbpress' ) );
			}
		}
	}
}

/** Edit **********************************************************************/

/**
 * Handles the front end user editing
 *
 * @uses is_multisite() To check if it's a multisite
 * @uses bbp_is_user_home() To check if the user is at home (the display page
 *                           is the one of the logged in user)
 * @uses get_option() To get the displayed user's new email id option
 * @uses wpdb::prepare() To sanitize our sql query
 * @uses wpdb::get_var() To execute our query and get back the variable
 * @uses wpdb::query() To execute our query
 * @uses wp_update_user() To update the user
 * @uses delete_option() To delete the displayed user's email id option
 * @uses bbp_get_user_profile_edit_url() To get the edit profile url
 * @uses wp_redirect() To redirect to the url
 * @uses check_admin_referer() To verify the nonce and check the referer
 * @uses current_user_can() To check if the current user can edit the user
 * @uses do_action() Calls 'personal_options_update' or
 *                   'edit_user_options_update' (based on if it's the user home)
 *                   with the displayed user id
 * @uses edit_user() To edit the user based on the post data
 * @uses get_userdata() To get the user data
 * @uses is_email() To check if the string is an email id or not
 * @uses wpdb::get_blog_prefix() To get the blog prefix
 * @uses is_network_admin() To check if the user is the network admin
 * @uses is_super_admin() To check if the user is super admin
 * @uses revoke_super_admin() To revoke super admin priviledges
 * @uses grant_super_admin() To grant super admin priviledges
 * @uses is_wp_error() To check if the value retrieved is a {@link WP_Error}
 */
function bbp_edit_user_handler() {

	// Bail if not a POST action
	if ( 'POST' !== strtoupper( $_SERVER['REQUEST_METHOD'] ) )
		return;

	// Bail if action is not 'bbp-update-user'
	if ( empty( $_POST['action'] ) || ( 'bbp-update-user' !== $_POST['action'] ) )
		return;

	// Get the displayed user ID
	$user_id = bbp_get_displayed_user_id();

	global $wpdb;

	// Execute confirmed email change. See send_confirmation_on_profile_email().
	if ( is_multisite() && bbp_is_user_home() && isset( $_GET['newuseremail'] ) ) {

		$new_email = get_option( $user_id . '_new_email' );

		if ( $new_email['hash'] == $_GET['newuseremail'] ) {
			$user->ID         = $user_id;
			$user->user_email = esc_html( trim( $new_email['newemail'] ) );

			if ( $wpdb->get_var( $wpdb->prepare( "SELECT user_login FROM {$wpdb->signups} WHERE user_login = %s", bbp_get_displayed_user_field( 'user_login' ) ) ) )
				$wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->signups} SET user_email = %s WHERE user_login = %s", $user->user_email, bbp_get_displayed_user_field( 'user_login' ) ) );

			wp_update_user( get_object_vars( $user ) );
			delete_option( $user_id . '_new_email' );

			wp_redirect( add_query_arg( array( 'updated' => 'true' ), bbp_get_user_profile_edit_url( $user_id ) ) );
			exit;
		}

	} elseif ( is_multisite() && bbp_is_user_home() && !empty( $_GET['dismiss'] ) && ( $user_id . '_new_email' == $_GET['dismiss'] ) ) {

		delete_option( $user_id . '_new_email' );
		wp_redirect( add_query_arg( array( 'updated' => 'true' ), bbp_get_user_profile_edit_url( $user_id ) ) );
		exit;

	}

	check_admin_referer( 'update-user_' . $user_id );

	if ( !current_user_can( 'edit_user', $user_id ) )
		wp_die( __( 'What are you doing here? You do not have the permission to edit this user.', 'bbpress' ) );

	// Do action based on who's profile you're editing
	$edit_action = bbp_is_user_home() ? 'personal_options_update' : 'edit_user_profile_update';
	do_action( $edit_action, $user_id );

	// Multisite handles the trouble for us ;)
	if ( !is_multisite() ) {
		$edit_user = edit_user( $user_id );

	// Single site means we need to do some manual labor
	} else {
		$user = get_userdata( $user_id );

		// Update the email address in signups, if present.
		if ( $user->user_login && isset( $_POST['email'] ) && is_email( $_POST['email'] ) && $wpdb->get_var( $wpdb->prepare( "SELECT user_login FROM {$wpdb->signups} WHERE user_login = %s", $user->user_login ) ) ) {
			$wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->signups} SET user_email = %s WHERE user_login = %s", $_POST['email'], $user_login ) );
		}

		// WPMU must delete the user from the current blog if WP added him after editing.
		$delete_role = false;
		$blog_prefix = $wpdb->get_blog_prefix();

		if ( $user_id != $user_id ) {
			$cap = $wpdb->get_var( "SELECT meta_value FROM {$wpdb->usermeta} WHERE user_id = '{$user_id}' AND meta_key = '{$blog_prefix}capabilities' AND meta_value = 'a:0:{}'" );
			if ( !is_network_admin() && null == $cap && $_POST['role'] == '' ) {
				$_POST['role'] = 'contributor';
				$delete_role   = true;
			}
		}

		$edit_user = edit_user( $user_id );

		// stops users being added to current blog when they are edited
		if ( $delete_role ) {
			delete_user_meta( $user_id, $blog_prefix . 'capabilities' );
		}

		if ( is_multisite() && is_network_admin() & !bbp_is_user_home() && current_user_can( 'manage_network_options' ) && !isset( $super_admins ) && empty( $_POST['super_admin'] ) == is_super_admin( $user_id ) ) {
			empty( $_POST['super_admin'] ) ? revoke_super_admin( $user_id ) : grant_super_admin( $user_id );
		}
	}

	// Error(s) editng the user, so copy them into the global
	if ( is_wp_error( $edit_user ) ) {
		global $bbp;
		$bbp->errors = $edit_user;

	// Successful edit to redirect
	} elseif ( is_integer( $edit_user ) ) {
		$redirect = add_query_arg( array( 'updated' => 'true' ), bbp_get_user_profile_edit_url( $edit_user ) );

		wp_redirect( $redirect );
		exit;
	}
}

/** END - Edit User ***********************************************************/

/**
 * Get the topics that a user created
 *
 * @since bbPress (r2660)
 *
 * @param int $user_id Optional. User id
 * @uses bbp_get_user_id() To get the topic id
 * @uses bbp_has_topics() To get the topics created by the user
 * @return array|bool Results if the user has created topics, otherwise false
 */
function bbp_get_user_topics_started( $user_id = 0 ) {
	if ( !$user_id = bbp_get_user_id( $user_id ) )
		return false;

	// Query defaults
	$default_query = array(
		'author'         => $user_id,
		'show_stickies'  => false,
		'order'          => 'DESC',
	);

	// Get the topics
	if ( $query = bbp_has_topics( $default_query ) )
		return $query;

	return false;
}

/**
 * Get the total number of users on the forums
 *
 * @since bbPress (r2769)
 *
 * @uses wp_cache_get() Check if query is in cache
 * @uses wpdb::get_var() To execute our query and get the var back
 * @uses wp_cache_set() Set the query in the cache
 * @uses apply_filters() Calls 'bbp_get_total_users' with number of users
 * @return int Total number of users
 */
function bbp_get_total_users() {
	global $wpdb;

	if ( $bbp_total_users = wp_cache_get( 'bbp_total_users', 'bbpress' ) )
		return $bbp_total_users;

	$bbp_total_users = $wpdb->get_var( "SELECT COUNT(ID) FROM {$wpdb->users} USE INDEX (PRIMARY);" );

	wp_cache_set( 'bbp_total_users', $bbp_total_users, 'bbpress' );

	return apply_filters( 'bbp_get_total_users', (int) $bbp_total_users );
}

/** User Status ***************************************************************/

/**
 * Checks if the user has been marked as a spammer.
 *
 * @since bbPress (r3355)
 *
 * @param int $user_id int The ID for the user.
 * @return bool True if spammer, False if not.
 */
function bbp_is_user_spammer( $user_id = 0 ) {

	// Default to current user
	if ( empty( $user_id ) && is_user_logged_in() )
		$user_id = bbp_get_current_user_id();

	// No user to check
	if ( empty( $user_id ) )
		return false;

	// Assume user is not spam
	$is_spammer = false;

	// Get user data
	$user = get_userdata( $user_id );

	// No user found
	if ( empty( $user ) ) {
		$is_spammer = false;
		
	// User found
	} else {

		// Check if spam
		if ( !empty( $user->spam ) )
			$is_spammer = true;

		if ( 'spam' == $user->user_status )
			$is_spammer = true;
	}

	return apply_filters( 'bp_core_is_user_spammer', (bool) $is_spammer );
}

/**
 * Mark a users topics and replies as spam when the user is marked as spam
 *
 * @since bbPress (r3405)
 *
 * @global WPDB $wpdb
 * @param int $user_id Optional. User ID to spam. Defaults to displayed user.

 * @uses bbp_is_single_user()
 * @uses bbp_is_user_home()
 * @uses bbp_get_displayed_user_field()
 * @uses is_super_admin()
 * @uses get_blogs_of_user()
 * @uses bbp_get_topic_post_type()
 * @uses bbp_get_reply_post_type()
 * @uses switch_to_blog()
 * @uses get_post_type()
 * @uses bbp_spam_topic()
 * @uses bbp_spam_reply()
 * @uses restore_current_blog()
 * 
 * @return If no user ID passed
 */
function bbp_make_spam_user( $user_id = 0 ) {

	// Use displayed user if it's not yourself
	if ( empty( $user_id ) && bbp_is_single_user() && !bbp_is_user_home() )
		$user_id = bbp_get_displayed_user_id();

	// Bail if no user ID
	if ( empty( $user_id ) )
		return;

	// Bail if user ID is super admin
	if ( is_super_admin( $user_id ) )
		return;

	// Arm the torpedos
	global $wpdb;

	// Get the blog IDs of the user to mark as spam
	$blogs = get_blogs_of_user( $user_id, true );

	// If user has no blogs, they are a guest on this site
	if ( empty( $blogs ) )
		$blogs[$wpdb->blogid] = array();

	// Make array of post types to mark as spam
	$post_types = array( bbp_get_topic_post_type(), bbp_get_reply_post_type() );
	$post_types = "'" . implode( "', '", $post_types ) . "'";

	// Loop through blogs and remove their posts
	foreach ( (array) $blogs as $blog_id => $details ) {

		// Switch to the blog ID
		switch_to_blog( $blog_id );

		// Get topics and replies
		$posts = $wpdb->get_col( "SELECT ID FROM {$wpdb->posts} WHERE post_author = {$user_id} AND post_status = 'publish' AND post_type IN ({$post_types})" );

		// Loop through posts and spam them
		if ( !empty( $posts ) ) {
			foreach ( $posts as $post_id ) {
				
				// The routines for topics ang replies are different, so use the
				// correct one based on the post type
				switch ( get_post_type( $post_id ) ) {

					case bbp_get_topic_post_type() :
						bbp_spam_topic( $post_id );
						break;

					case bbp_get_reply_post_type() :
						bbp_spam_reply( $post_id );
						break;
				}
			}
		}

		// Switch back to current blog
		restore_current_blog();
	}
}

/**
 * Mark a users topics and replies as spam when the user is marked as spam
 *
 * @since bbPress (r3405)
 *
 * @global WPDB $wpdb
 * @param int $user_id Optional. User ID to unspam. Defaults to displayed user.
 *
 * @uses bbp_is_single_user()
 * @uses bbp_is_user_home()
 * @uses bbp_get_displayed_user_field()
 * @uses is_super_admin()
 * @uses get_blogs_of_user()
 * @uses bbp_get_topic_post_type()
 * @uses bbp_get_reply_post_type()
 * @uses switch_to_blog()
 * @uses get_post_type()
 * @uses bbp_unspam_topic()
 * @uses bbp_unspam_reply()
 * @uses restore_current_blog()
 * 
 * @return If no user ID passed
 */
function bbp_make_ham_user( $user_id = 0 ) {

	// Use displayed user if it's not yourself
	if ( empty( $user_id ) && bbp_is_single_user() && !bbp_is_user_home() )
		$user_id = bbp_get_displayed_user_field();

	// Bail if no user ID
	if ( empty( $user_id ) )
		return;

	// Bail if user ID is super admin
	if ( is_super_admin( $user_id ) )
		return;

	// Arm the torpedos
	global $wpdb, $bbp;

	// Get the blog IDs of the user to mark as spam
	$blogs = get_blogs_of_user( $user_id, true );

	// If user has no blogs, they are a guest on this site
	if ( empty( $blogs ) )
		$blogs[$wpdb->blogid] = array();

	// Make array of post types to mark as spam
	$post_types = array( bbp_get_topic_post_type(), bbp_get_reply_post_type() );
	$post_types = "'" . implode( "', '", $post_types ) . "'";

	// Loop through blogs and remove their posts
	foreach ( (array) $blogs as $blog_id => $details ) {

		// Switch to the blog ID
		switch_to_blog( $blog_id );

		// Get topics and replies
		$posts = $wpdb->get_col( "SELECT ID FROM {$wpdb->posts} WHERE post_author = {$user_id} AND post_status = '{$bbp->spam_status_id}' AND post_type IN ({$post_types})" );

		// Loop through posts and spam them
		if ( !empty( $posts ) ) {
			foreach ( $posts as $post_id ) {
				
				// The routines for topics ang replies are different, so use the
				// correct one based on the post type
				switch ( get_post_type( $post_id ) ) {

					case bbp_get_topic_post_type() :
						bbp_unspam_topic( $post_id );
						break;

					case bbp_get_reply_post_type() :
						bbp_unspam_reply( $post_id );
						break;
				}
			}
		}

		// Switch back to current blog
		restore_current_blog();
	}
}

/**
 * Checks if the user has been marked as deleted.
 *
 * @since bbPress (r3355)
 *
 * @param int $user_id int The ID for the user.
 * @return bool True if deleted, False if not.
 */
function bbp_is_user_deleted( $user_id = 0 ) {

	// No user to check
	if ( empty( $user_id ) )
		return false;

	// Assume user is not deleted
	$is_deleted = false;

	// Get user data
	$user = get_userdata( $user_id );

	// No user found
	if ( empty( $user ) ) {
		$is_deleted = true;
		
	// User found
	} else {

		// Check if deleted
		if ( !empty( $user->deleted ) )
			$is_deleted = true;

		if ( 'deleted' == $user->user_status )
			$is_deleted = true;

	}

	return apply_filters( 'bp_core_is_user_deleted', (bool) $is_deleted );
}

?>
