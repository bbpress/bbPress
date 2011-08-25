<?php

/**
 * bbPress Options
 *
 * @package bbPress
 * @subpackage Options
 */

// Exit if accessed directly
if ( !defined( 'ABSPATH' ) ) exit;

/**
 * Get the default site options and their values
 *
 * @since bbPress (r3421)
 *
 * @return array Filtered option names and values
 */
function bbp_get_default_options() {

	// Default options
	$options = array (

		/** DB Version ********************************************************/

		'_bbp_db_version'           => '155',

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

		// Users from all sites can post
		'_bbp_allow_global_access'  => false,

		/** Per Page **********************************************************/

		// Topics per page
		'_bbp_topics_per_page'      => '15',

		// Replies per page
		'_bbp_replies_per_page'     => '15',

		// Forums per page
		'_bbp_forums_per_page'      => '50',

		// Topics per RSS page
		'_bbp_topics_per_rss_page'  => '25',

		// Replies per RSS page
		'_bbp_replies_per_rss_page' => '25',

		/** Page For **********************************************************/

		// Page for forums
		'_bbp_page_for_forums'      => '0',

		// Page for forums
		'_bbp_page_for_topics'      => '0',

		// Page for login
		'_bbp_page_for_login'       => '0',

		// Page for register
		'_bbp_page_for_register'    => '0',

		// Page for lost-pass
		'_bbp_page_for_lost_pass'   => '0',

		/** Archive Slugs *****************************************************/

		// Forum archive slug
		'_bbp_root_slug'            => 'forums',

		// Topic archive slug
		'_bbp_topic_archive_slug'   => 'topics',

		/** Single Slugs ******************************************************/

		// Include Forum archive before single slugs
		'_bbp_include_root'         => true,

		// Forum slug
		'_bbp_forum_slug'           => 'forum',

		// Topic slug
		'_bbp_topic_slug'           => 'topic',

		// Reply slug
		'_bbp_reply_slug'           => 'reply',

		// Topic tag slug
		'_bbp_topic_tag_slug'       => 'topic-tag',

		/** Other Slugs *******************************************************/

		// User profile slug
		'_bbp_user_slug'            => 'users',

		// View slug
		'_bbp_view_slug'            => 'view',

		/** Topics ************************************************************/

		// Title Max Length
		'_bbp_title_max_length'     => '80',

		// Super stickies
		'_bbp_super_sticky_topics'  => '',

		/** Forums ************************************************************/

		// Private forums
		'_bbp_private_forums'       => '',

		// Hidden forums
		'_bbp_hidden_forums'        => '',
	);

	return apply_filters( 'bbp_get_default_options', $options );
}

/**
 * Add default options
 *
 * Hooked to bbp_activate, it is only called once when bbPress is activated.
 * This is non-destructive, so existing settings will not be overridden.
 *
 * @uses bbp_get_default_options() To get default options
 * @uses add_option() Adds default options
 * @uses do_action() Calls 'bbp_add_options'
 */
function bbp_add_options() {

	// Get the default options and values
	$options = bbp_get_default_options();

	// Add default options
	foreach ( $options as $key => $value )
		add_option( $key, $value );

	// Allow previously activated plugins to append their own options.
	do_action( 'bbp_add_options' );
}
/**
 * Delete default options
 *
 * Hooked to bbp_uninstall, it is only called once when bbPress is uninstalled.
 * This is destructive, so existing settings will be destroyed.
 *
 * @uses bbp_get_default_options() To get default options
 * @uses delete_option() Removes default options
 * @uses do_action() Calls 'bbp_delete_options'
 */
function bbp_delete_options() {

	// Get the default options and values
	$options = bbp_get_default_options();

	// Add default options
	foreach ( $options as $key => $value )
		delete_option( $key );

	// Allow previously activated plugins to append their own options.
	do_action( 'bbp_delete_options' );
}

/**
 * Add filters to each bbPress option and allow them to be overloaded from
 * inside the $bbp->options array.
 * 
 * @since bbPress (r3451)
 *
 * @uses bbp_get_default_options() To get default options
 * @uses add_filter() To add filters to 'pre_option_{$key}'
 * @uses do_action() Calls 'bbp_add_option_filters'
 */
function bbp_setup_option_filters() {
	
	// Get the default options and values
	$options = bbp_get_default_options();

	// Add filters to each bbPress option
	foreach ( $options as $key => $value )
		add_filter( 'pre_option_' . $key, 'bbp_pre_get_option' );

	// Allow previously activated plugins to append their own options.
	do_action( 'bbp_setup_option_filters' );
}

/**
 * Filter default options and allow them to be overloaded from inside the
 * $bbp->options array.
 *
 * @since bbPress (r3451)
 *
 * @global bbPress $bbp
 * @param bool $value
 * @return mixed false if not overloaded, mixed if set
 */
function bbp_pre_get_option( $value = false ) {
	global $bbp;

	// Get the name of the current filter so we can manipulate it
	$filter = current_filter();

	// Remove the filter prefix
	$option = str_replace( 'pre_option_', '', $filter );

	// Check the options global for preset value
	if ( !empty( $bbp->options[$option] ) )
		$value = $bbp->options[$option];

	// Always return a value, even if false
	return $value;
}

/** Active? *******************************************************************/

/**
 * Checks if favorites feature is enabled.
 *
 * @since bbPress (r2658)
 *
 * @param $default bool Optional.Default value
 *
 * @uses get_option() To get the favorites option
 * @return bool Is favorites enabled or not
 */
function bbp_is_favorites_active( $default = true ) {
	return apply_filters( 'bbp_is_favorites_active', (bool) get_option( '_bbp_enable_favorites', $default ) );
}

/**
 * Checks if subscription feature is enabled.
 *
 * @since bbPress (r2658)
 *
 * @param $default bool Optional.Default value
 *
 * @uses get_option() To get the subscriptions option
 * @return bool Is subscription enabled or not
 */
function bbp_is_subscriptions_active( $default = true ) {
	return apply_filters( 'bbp_is_subscriptions_active', (bool) get_option( '_bbp_enable_subscriptions', $default ) );
}

/**
 * Are topic and reply revisions allowed
 *
 * @since bbPress (r3412)
 *
 * @param $default bool Optional. Default value
 *
 * @uses get_option() To get the allow revisions
 * @return bool Are revisions allowed?
 */
function bbp_allow_revisions( $default = true ) {
	return apply_filters( 'bbp_allow_revisions', (bool) get_option( '_bbp_allow_revisions', $default ) );
}

/**
 * Is the anonymous posting allowed?
 *
 * @since bbPress (r2659)
 *
 * @param $default bool Optional. Default value
 *
 * @uses get_option() To get the allow anonymous option
 * @return bool Is anonymous posting allowed?
 */
function bbp_allow_anonymous( $default = false ) {
	return apply_filters( 'bbp_allow_anonymous', (bool) get_option( '_bbp_allow_anonymous', $default ) );
}

/**
 * Is this forum available to all users on all sites in this installation?
 *
 * @since bbPress (r3378)
 *
 * @param $default bool Optional. Default value
 *
 * @uses get_option() To get the global access option
 * @return bool Is global access allowed?
 */
function bbp_allow_global_access( $default = false ) {
	return apply_filters( 'bbp_allow_global_access', (bool) get_option( '_bbp_allow_global_access', $default ) );
}

/**
 * Output the maximum length of a title
 *
 * @since bbPress (r3246)
 *
 * @param $default bool Optional. Default value
 */
function bbp_title_max_length( $default = '80' ) {
	echo bbp_get_title_max_length( $default );
}
	/**
	 * Return the maximum length of a title
	 *
	 * @since bbPress (r3246)
	 *
	 * @param $default bool Optional. Default value
	 *
	 * @uses get_option() To get the maximum title length
	 * @return int Is anonymous posting allowed?
	 */
	function bbp_get_title_max_length( $default = '80' ) {
		return apply_filters( 'bbp_get_title_max_length', (int) get_option( '_bbp_title_max_length', $default ) );
	}

?>
