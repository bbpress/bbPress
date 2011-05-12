<?php

/**
 * bbPress Options
 *
 * @package bbPress
 * @subpackage Options
 */

// Redirect if accessed directly
if ( !defined( 'ABSPATH' ) ) exit;

/**
 * Add default options
 *
 * Hooked to bbp_activate, it is only called once when bbPress is activated.
 * This is non-destructive, so existing settings will not be overridden.
 *
 * @uses add_option() Adds default options
 * @uses do_action() Calls 'bbp_add_options'
 */
function bbp_add_options() {

	// Default options
	$options = array (

		/** Database **********************************************************/

		// DB version
		'_bbp_db_version'           => '110',

		/** Settings **********************************************************/

		// Lock post editing after 5 minutes
		'_bbp_edit_lock'            => '5',

		// Throttle post time to 10 seconds
		'_bbp_throttle_time'        => '10',

		// Favorites
		'_bbp_enable_favorites'     => true,

		// Subscriptions
		'_bbp_enable_subscriptions' => true,

		// Allow anonymous posting
		'_bbp_allow_anonymous'      => false,

		// Topics per page
		'_bbp_topics_per_page'      => '15',

		// Replies per page
		'_bbp_replies_per_page'     => '15',

		// Forums per page
		'_bbp_forums_per_page'      => '50',

		/** Slugs *************************************************************/

		// Root slug
		'_bbp_root_slug'            => 'forums',

		// Use root before slugs
		'_bbp_include_root'         => true,

		// User profile slug
		'_bbp_user_slug'            => 'users',

		// View slug
		'_bbp_view_slug'            => 'view',

		// Forum slug
		'_bbp_forum_slug'           => 'forum',

		// Topic slug
		'_bbp_topic_slug'           => 'topic',

		// Reply slug
		'_bbp_reply_slug'           => 'reply',

		// Topic tag slug
		'_bbp_topic_tag_slug'       => 'tag',

		/** Topics ************************************************************/

		// Super stickies
		'_bbp_super_sticky_topics'  => '',

		/** Forums ************************************************************/

		// Private forums
		'_bbp_private_forums'       => '',

		// Hidden forums
		'_bbp_hidden_forums'        => '',
	);

	// Add default options
	foreach ( $options as $key => $value )
		add_option( $key, $value );

	// Allow previously activated plugins to append their own options.
	// This is an extremely rare use-case.
	do_action( 'bbp_add_options' );
}

/** Active? *******************************************************************/

/**
 * Checks if favorites feature is enabled.
 *
 * @since bbPress (r2658)
 *
 * @uses get_option() To get the favorites option
 * @return bool Is favorites enabled or not
 */
function bbp_is_favorites_active() {
	return apply_filters( 'bbp_is_favorites_active', (bool) get_option( '_bbp_enable_favorites', true ) );
}

/**
 * Checks if subscription feature is enabled.
 *
 * @since bbPress (r2658)
 *
 * @uses get_option() To get the subscriptions option
 * @return bool Is subscription enabled or not
 */
function bbp_is_subscriptions_active() {
	return apply_filters( 'bbp_is_subscriptions_active', (bool) get_option( '_bbp_enable_subscriptions' ) );
}

/**
 * Is the anonymous posting allowed?
 *
 * @since bbPress (r2659)
 *
 * @uses get_option() To get the allow anonymous option
 * @return bool Is anonymous posting allowed?
 */
function bbp_allow_anonymous() {
	return apply_filters( 'bbp_allow_anonymous', get_option( '_bbp_allow_anonymous', false ) );
}

?>
