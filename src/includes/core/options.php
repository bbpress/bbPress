<?php

/**
 * bbPress Options
 *
 * @package bbPress
 * @subpackage Core
 */

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

/**
 * Get the default site options and their values.
 *
 * These option
 *
 * @since 2.0.0 bbPress (r3421)
 *
 * @return array Filtered option names and values
 */
function bbp_get_default_options() {
	static $options = null;

	// Store these to avoid recreating them
	if ( is_null( $options ) ) {

		// Use the participant role for the default-default role
		$role    = bbp_get_participant_role();
		$options = array(

			/** DB Version ********************************************************/

			'_bbp_db_version'             => 0,         // Database version
			'_bbp_db_pending_upgrades'    => array(),   // Database upgrades pending

			/** Flags *************************************************************/

			'_bbp_flag_initial_content'   => 1,         // Flag to indicate initial content was created

			/** Features **********************************************************/

			'_bbp_enable_favorites'       => 1,         // Enable favorites
			'_bbp_enable_subscriptions'   => 1,         // Enable subscriptions
			'_bbp_enable_engagements'     => 1,         // Enable engagements
			'_bbp_allow_content_edit'     => 1,         // Allow content edit
			'_bbp_allow_content_throttle' => 1,         // Allow content throttle
			'_bbp_allow_revisions'        => 1,         // Allow revisions
			'_bbp_allow_topic_tags'       => 1,         // Allow topic tagging
			'_bbp_allow_search'           => 1,         // Allow forum-wide search
			'_bbp_allow_forum_mods'       => 1,         // Allow per-forum moderation
			'_bbp_allow_global_access'    => 1,         // Allow users from all sites to post
			'_bbp_allow_anonymous'        => 0,         // Allow anonymous posting
			'_bbp_allow_super_mods'       => 0,         // Allow mods to edit users
			'_bbp_allow_threaded_replies' => 0,         // Allow threaded replies
			'_bbp_use_wp_editor'          => 1,         // Use the WordPress editor if available
			'_bbp_use_autoembed'          => 0,         // Use oEmbed in topics and replies

			/** Settings **********************************************************/

			'_bbp_default_role'           => $role,     // Default forums role
			'_bbp_edit_lock'              => 5,         // Lock post editing after 5 minutes
			'_bbp_throttle_time'          => 10,        // Throttle post time to 10 seconds
			'_bbp_thread_replies_depth'   => 2,         // Thread replies depth
			'_bbp_theme_package_id'       => 'default', // The ID for the current theme package
			'_bbp_settings_integration'   => 'basic',   // How to integrate into wp-admin
			'_bbp_engagements_strategy'   => 'meta',    // How to interact with engagements

			/** Per Page **********************************************************/

			'_bbp_topics_per_page'      => 15,          // Topics per page
			'_bbp_replies_per_page'     => 15,          // Replies per page
			'_bbp_forums_per_page'      => 50,          // Forums per page
			'_bbp_topics_per_rss_page'  => 25,          // Topics per RSS page
			'_bbp_replies_per_rss_page' => 25,          // Replies per RSS page

			/** Page For **********************************************************/

			'_bbp_page_for_forums'      => 0,           // Page for forums
			'_bbp_page_for_topics'      => 0,           // Page for forums
			'_bbp_page_for_login'       => 0,           // Page for login
			'_bbp_page_for_register'    => 0,           // Page for register
			'_bbp_page_for_lost_pass'   => 0,           // Page for lost-pass

			/** Forum Root ********************************************************/

			'_bbp_root_slug'            => 'forums',    // Forum archive slug
			'_bbp_show_on_root'         => 'forums',    // What to show on root (forums|topics)
			'_bbp_include_root'         => 1,           // Include forum-archive before single slugs

			/** Single Slugs ******************************************************/

			'_bbp_forum_slug'           => 'forum',     // Forum slug
			'_bbp_topic_slug'           => 'topic',     // Topic slug
			'_bbp_reply_slug'           => 'reply',     // Reply slug
			'_bbp_topic_tag_slug'       => 'topic-tag', // Topic tag slug

			/** User Slugs ********************************************************/

			'_bbp_user_slug'            => 'users',         // User profile slug
			'_bbp_user_engs_slug'       => 'engagements',   // User engagements slug
			'_bbp_user_favs_slug'       => 'favorites',     // User favorites slug
			'_bbp_user_subs_slug'       => 'subscriptions', // User subscriptions slug
			'_bbp_topic_archive_slug'   => 'topics',        // Topic archive slug
			'_bbp_reply_archive_slug'   => 'replies',       // Reply archive slug

			/** Other Slugs *******************************************************/

			'_bbp_view_slug'            => 'view',      // View slug
			'_bbp_search_slug'          => 'search',    // Search slug

			/** Topics ************************************************************/

			'_bbp_title_max_length'     => 80,          // Title Max Length
			'_bbp_super_sticky_topics'  => '',          // Super stickies

			/** Forums ************************************************************/

			'_bbp_private_forums'       => '',          // Private forums
			'_bbp_hidden_forums'        => '',          // Hidden forums

			/** BuddyPress ********************************************************/

			'_bbp_enable_group_forums'  => 1,           // Enable BuddyPress Group Extension
			'_bbp_group_forums_root_id' => 0,           // Group Forums parent forum id

			/** Akismet ***********************************************************/

			'_bbp_enable_akismet'       => 1,           // Users from all sites can post

			/** Converter *********************************************************/

			// Connection
			'_bbp_converter_db_user'    => '',          // Database User
			'_bbp_converter_db_pass'    => '',          // Database Password
			'_bbp_converter_db_name'    => '',          // Database Name
			'_bbp_converter_db_port'    => 3306,        // Database Port
			'_bbp_converter_db_server'  => 'localhost', // Database Server/IP
			'_bbp_converter_db_prefix'  => '',          // Database table prefix

			// Options
			'_bbp_converter_rows'          => 100,      // Number of rows to query
			'_bbp_converter_delay_time'    => 2,        // Seconds to wait between queries
			'_bbp_converter_step'          => false,    // Current converter step
			'_bbp_converter_start'         => false,    // Step to start at
			'_bbp_converter_convert_users' => false,    // Whether to convert users
			'_bbp_converter_halt'          => false,    // Halt on errors
			'_bbp_converter_platform'      => false,    // Which platform to use
			'_bbp_converter_query'         => false     // Last query
		);
	}

	// Filter & return
	return (array) apply_filters( 'bbp_get_default_options', $options );
}

/**
 * Add default options
 *
 * Hooked to bbp_activate, it is only called once when bbPress is activated.
 * This is non-destructive, so existing settings will not be overridden.
 *
 * @since 2.0.0 bbPress (r3421)
 */
function bbp_add_options() {

	// Add default options
	foreach ( bbp_get_default_options() as $key => $value ) {
		add_option( $key, $value );
	}

	// Allow previously activated plugins to append their own options.
	do_action( 'bbp_add_options' );
}

/**
 * Delete default options
 *
 * Hooked to bbp_uninstall, it is only called once when bbPress is uninstalled.
 * This is destructive, so existing settings will be destroyed.
 *
 * @since 2.0.0 bbPress (r3421)
 */
function bbp_delete_options() {

	// Add default options
	foreach ( array_keys( bbp_get_default_options() ) as $key ) {
		delete_option( $key );
	}

	// Allow previously activated plugins to append their own options.
	do_action( 'bbp_delete_options' );
}

/**
 * Add filters to each bbPress option and allow them to be overloaded from
 * inside the $bbp->options array.
 *
 * @since 2.0.0 bbPress (r3451)
 */
function bbp_setup_option_filters() {

	// Add filters to each bbPress option
	foreach ( array_keys( bbp_get_default_options() ) as $key ) {
		add_filter( 'pre_option_'     . $key, 'bbp_filter_pre_get_option', 10, 2 );
		add_filter( 'default_option_' . $key, 'bbp_filter_default_option', 10, 3 );
	}

	// Allow previously activated plugins to append their own options.
	do_action( 'bbp_setup_option_filters' );
}

/**
 * Filter pre options and maybe overloaded from the $bbp->options array.
 *
 * This function should not be called directly.
 *
 * @since 2.0.0 bbPress (r3451)
 * @access private
 *
 * @param bool   $value  Default value false
 * @param string $option Name of the option
 *
 * @return mixed false if not overloaded, mixed if set
 */
function bbp_filter_pre_get_option( $value = false, $option = '' ) {

	// Check the options global for preset value
	if ( isset( bbpress()->options[ $option ] ) ) {
		$value = bbpress()->options[ $option ];
	}

	// Always return a value, even if false
	return $value;
}

/**
 * Filter default_options set them from inside the $bbp->options array.
 *
 * This function should not be called directly.
 *
 * @since 2.6.0 bbPress (r3451)
 * @access private
 *
 * @param bool $value Optional. Default value false
 * @return mixed false if not overloaded, mixed if set
 */
function bbp_filter_default_option( $default = false, $option = '', $passed_default = false ) {
	$options = bbp_get_default_options();

	// Maybe use the default value
	if ( isset( $options[ $option ] ) ) {

		// Try to use the passed default and fallback to assumed default
		$default = ( true === $passed_default )
			? $default
			: $options[ $option ];
	}

	// Always return a value, even if false
	return $default;
}

/**
 * Loads & caches bbPress options if a persistent cache is not being used.
 *
 * @since 2.6.0
 */
function bbp_pre_load_options() {

	// Bail if using object cache or installing
	if ( wp_using_ext_object_cache() || wp_installing() ) {
		return;
	}

	// Maybe intercept
	$strategy  = apply_filters( 'bbp_pre_load_options_strategy', 'notoptions' );
	$intercept = bbp_maybe_intercept( __FUNCTION__, $strategy );
	if ( bbp_is_intercepted( $intercept ) ) {
		return $intercept;
	}

	// Get variables
	$bbp         = bbpress();
	$bbp_options = bbp_get_default_options();
	$all_options = wp_load_alloptions();
	$not_options = (array) wp_cache_get( 'notoptions', 'options' );

	// Loop through all bbPress options to maybe cache their non-existence
	foreach ( $bbp_options as $option => $value ) {

		// Skip if already saved to database
		if ( isset( $all_options[ $option ] ) ) {
			continue;

		// Skip if overloaded
		} elseif ( isset( $bbp->options[ $option ] ) ) {
			continue;

		// Skip if already in cache
		} elseif ( wp_cache_get( $option, 'options' ) !== false ) {
			continue;

		// Needs caching to avoid database hit
		} else {

			// Store internally, for easier identification later
			$bbp->not_options[ $option ] = $value;

			// Cache to notoptions
			if ( 'notoptions' === $strategy ) {
				$not_options[ $option ] = true;
				wp_cache_set( 'notoptions', $not_options, 'options' );

			// Cache to option
			} elseif ( 'option' === $strategy ) {
				wp_cache_set( $option, $value, 'options' );
			}
		}
	}
}

/** Active? *******************************************************************/

/**
 * Checks if favorites feature is enabled.
 *
 * @since 2.0.0 bbPress (r2658)
 *
 * @param bool $default Optional.Default value true
 * @return bool Is favorites enabled or not
 */
function bbp_is_favorites_active( $default = 1 ) {

	// Filter & return
	return (bool) apply_filters( 'bbp_is_favorites_active', (bool) get_option( '_bbp_enable_favorites', $default ) );
}

/**
 * Checks if subscription feature is enabled.
 *
 * @since 2.0.0 bbPress (r2658)
 *
 * @param bool $default Optional.Default value true
 * @return bool Is subscription enabled or not
 */
function bbp_is_subscriptions_active( $default = 1 ) {

	// Filter & return
	return (bool) apply_filters( 'bbp_is_subscriptions_active', (bool) get_option( '_bbp_enable_subscriptions', $default ) );
}

/**
 * Checks if engagements feature is enabled.
 *
 * @since 2.6.0 bbPress (r6320)
 *
 * @param bool $default Optional.Default value true
 * @return bool Is engagements enabled or not
 */
function bbp_is_engagements_active( $default = 1 ) {

	// Filter & return
	return (bool) apply_filters( 'bbp_is_engagements_active', (bool) get_option( '_bbp_enable_engagements', $default ) );
}

/**
 * Is content editing available when posting new topics & replies?
 *
 * @since 2.6.0 bbPress (r6441)
 *
 * @param bool $default Optional. Default value false
 * @return bool Is content editing allowed?
 */
function bbp_allow_content_edit( $default = 1 ) {

	// Filter & return
	return (bool) apply_filters( 'bbp_allow_content_edit', (bool) get_option( '_bbp_allow_content_edit', $default ) );
}

/**
 * Is content throttling engaged when posting new topics & replies?
 *
 * @since 2.6.0 bbPress (r6441)
 *
 * @param bool $default Optional. Default value false
 * @return bool Is content throttling allowed?
 */
function bbp_allow_content_throttle( $default = 1 ) {

	// Filter & return
	return (bool) apply_filters( 'bbp_allow_content_throttle', (bool) get_option( '_bbp_allow_content_throttle', $default ) );
}

/**
 * Are topic tags allowed
 *
 * @since 2.2.0 bbPress (r4097)
 *
 * @param bool $default Optional. Default value true
 * @return bool Are tags allowed?
 */
function bbp_allow_topic_tags( $default = 1 ) {

	// Filter & return
	return (bool) apply_filters( 'bbp_allow_topic_tags', (bool) get_option( '_bbp_allow_topic_tags', $default ) );
}

/**
 * Are per-forum moderators allowed
 *
 * @since 2.6.0 bbPress (r5834)
 *
 * @param bool $default Optional. Default value true.
 *
 * @return bool Are per-forum moderators allowed?
 */
function bbp_allow_forum_mods( $default = 1 ) {

	// Filter & return
	return (bool) apply_filters( 'bbp_allow_forum_mods', (bool) get_option( '_bbp_allow_forum_mods', $default ) );
}

/**
 * Are moderators allowed to edit users
 *
 * @since 2.6.0 bbPress (r6562)
 *
 * @param bool $default Optional. Default value true.
 *
 * @return bool Are moderators allowed to edit users?
 */
function bbp_allow_super_mods( $default = 0 ) {

	// Filter & return
	return (bool) apply_filters( 'bbp_allow_super_mods', (bool) get_option( '_bbp_allow_super_mods', $default ) );
}

/**
 * Is forum-wide searching allowed
 *
 * @since 2.4.0 bbPress (r4970)
 *
 * @param bool $default Optional. Default value true
 * @return bool Is forum-wide searching allowed?
 */
function bbp_allow_search( $default = 1 ) {

	// Filter & return
	return (bool) apply_filters( 'bbp_allow_search', (bool) get_option( '_bbp_allow_search', $default ) );
}

/**
 * Are threaded replies allowed
 *
 * @since 2.4.0 bbPress (r4964)
 *
 * @param bool $default Optional. Default value false
 * @return bool Are threaded replies allowed?
 */
function bbp_allow_threaded_replies( $default = 0 ) {

	// Filter & return
	return (bool) apply_filters( '_bbp_allow_threaded_replies', (bool) get_option( '_bbp_allow_threaded_replies', $default ) );
}

/**
 * Maximum reply thread depth
 *
 * @since 2.4.0 bbPress (r4944)
 *
 * @param int $default Thread replies depth
 * @return int Thread replies depth
 */
function bbp_thread_replies_depth( $default = 2 ) {

	// Filter & return
	return (int) apply_filters( 'bbp_thread_replies_depth', (int) get_option( '_bbp_thread_replies_depth', $default ) );
}

/**
 * Are topic and reply revisions allowed
 *
 * @since 2.0.0 bbPress (r3412)
 *
 * @param bool $default Optional. Default value true
 * @return bool Are revisions allowed?
 */
function bbp_allow_revisions( $default = 1 ) {

	// Filter & return
	return (bool) apply_filters( 'bbp_allow_revisions', (bool) get_option( '_bbp_allow_revisions', $default ) );
}

/**
 * Is the anonymous posting allowed?
 *
 * @since 2.0.0 bbPress (r2659)
 *
 * @param bool $default Optional. Default value
 * @return bool Is anonymous posting allowed?
 */
function bbp_allow_anonymous( $default = 0 ) {

	// Filter & return
	return apply_filters( 'bbp_allow_anonymous', (bool) get_option( '_bbp_allow_anonymous', $default ) );
}

/**
 * Is this forum available to all users on all sites in this installation?
 *
 * @since 2.0.0 bbPress (r3378)
 *
 * @param bool $default Optional. Default value false
 * @return bool Is global access allowed?
 */
function bbp_allow_global_access( $default = 1 ) {

	// Filter & return
	return (bool) apply_filters( 'bbp_allow_global_access', (bool) get_option( '_bbp_allow_global_access', $default ) );
}

/**
 * Get the default forums role (issued to users on their first visit to the
 * forums) by bbp_set_current_user_default_role() and repair tools
 *
 * @since 2.2.0 bbPress (r4294)
 *
 * @param string $default Optional. Default value empty
 * @return string The default forums user role
 */
function bbp_get_default_role( $default = 'bbp_participant' ) {

	// Filter & return
	return apply_filters( 'bbp_get_default_role', get_option( '_bbp_default_role', $default ) );
}

/**
 * Use the WordPress editor if available
 *
 * @since 2.0.0 bbPress (r3386)
 *
 * @param bool $default Optional. Default value true
 * @return bool Use WP editor?
 */
function bbp_use_wp_editor( $default = 1 ) {

	// Filter & return
	return (bool) apply_filters( 'bbp_use_wp_editor', (bool) get_option( '_bbp_use_wp_editor', $default ) );
}

/**
 * Use WordPress's oEmbed API
 *
 * @since 2.1.0 bbPress (r3752)
 *
 * @param bool $default Optional. Default value true
 * @return bool Use oEmbed?
 */
function bbp_use_autoembed( $default = 1 ) {

	// Filter & return
	return (bool) apply_filters( 'bbp_use_autoembed', (bool) get_option( '_bbp_use_autoembed', $default ) );
}

/**
 * Get the current theme package ID
 *
 * @since 2.1.0 bbPress (r3829)
 *
 * @param string $default Optional. Default value 'default'
 * @return string ID of the theme-package
 */
function bbp_get_theme_package_id( $default = 'default' ) {

	// Filter & return
	return apply_filters( 'bbp_get_theme_package_id', get_option( '_bbp_theme_package_id', $default ) );
}

/**
 * Output the maximum length of a title
 *
 * @since 2.0.0 bbPress (r3246)
 *
 * @param bool $default Optional. Default value 80
 */
function bbp_title_max_length( $default = 80 ) {
	echo bbp_get_title_max_length( $default );
}
	/**
	 * Return the maximum length of a title
	 *
	 * @since 2.0.0 bbPress (r3246)
	 *
	 * @param bool $default Optional. Default value 80
	 * @return int Is anonymous posting allowed?
	 */
	function bbp_get_title_max_length( $default = 80 ) {

		// Filter & return
		return (int) apply_filters( 'bbp_get_title_max_length', (int) get_option( '_bbp_title_max_length', $default ) );
	}

/**
 * Output the number of minutes a topic or reply can be edited after it's
 * published. Used by `bbp_past_edit_lock()`.
 *
 * @since 2.6.0 bbPress (r6868)
 *
 * @param bool $default Optional. Default value 5
 */
function bbp_edit_lock( $default = 5 ) {
	echo bbp_get_edit_lock( $default );
}
	/**
	 * Return the number of minutes a topic or reply can be edited after it's
	 * published. Used by `bbp_past_edit_lock()`.
	 *
	 * @since 2.6.0 bbPress (r6868)
	 *
	 * @param bool $default Optional. Default value 5
	 * @return int Is anonymous posting allowed?
	 */
	function bbp_get_edit_lock( $default = 5 ) {

		// Filter & return
		return (int) apply_filters( 'bbp_get_edit_lock', (int) get_option( '_bbp_edit_lock', $default ) );
	}

/**
 * Output the group forums root parent forum id
 *
 * @since 2.1.0 bbPress (r3575)
 *
 * @param int $default Optional. Default value
 */
function bbp_group_forums_root_id( $default = 0 ) {
	echo bbp_get_group_forums_root_id( $default );
}
	/**
	 * Return the group forums root parent forum id
	 *
	 * @since 2.1.0 bbPress (r3575)
	 *
	 * @param bool $default Optional. Default value 0
	 * @return int The post ID for the root forum
	 */
	function bbp_get_group_forums_root_id( $default = 0 ) {

		// Filter & return
		return (int) apply_filters( 'bbp_get_group_forums_root_id', (int) get_option( '_bbp_group_forums_root_id', $default ) );
	}

/**
 * Checks if BuddyPress Group Forums are enabled
 *
 * @since 2.1.0 bbPress (r3575)
 *
 * @param bool $default Optional. Default value true
 * @return bool Is group forums enabled or not
 */
function bbp_is_group_forums_active( $default = 1 ) {

	// Filter & return
	return (bool) apply_filters( 'bbp_is_group_forums_active', (bool) get_option( '_bbp_enable_group_forums', $default ) );
}

/**
 * Checks if Akismet is enabled
 *
 * @since 2.1.0 bbPress (r3575)
 *
 * @param bool $default Optional. Default value true
 * @return bool Is Akismet enabled or not
 */
function bbp_is_akismet_active( $default = 1 ) {

	// Filter & return
	return (bool) apply_filters( 'bbp_is_akismet_active', (bool) get_option( '_bbp_enable_akismet', $default ) );
}

/**
 * Integrate settings into existing WordPress pages
 *
 * There are 3 possible modes:
 * - 'basic'   Traditional admin integration
 * - 'compact' One "bbPress" top-level admin menu
 * - 'deep'    Deeply integrate with the WordPress admin interface
 *
 * @since 2.4.0 bbPress (r4932)
 *
 * @param bool $default Optional. Default value false
 * @return string How to integrate settings
 */
function bbp_settings_integration( $default = 'basic' ) {

	// Get the option value
	$integration = get_option( '_bbp_settings_integration', $default );

	// Back-compat for deep/basic (pre-2.6)
	if ( is_numeric( $integration ) ) {
		$integration = ( 1 === (int) $integration )
			? 'deep'
			: 'basic';
	}

	// Fallback to 'basic' if invalid
	if ( ! in_array( $integration, array( 'basic', 'deep', 'compact' ), true ) ) {
		$integration = 'basic';
	}

	// Filter & return
	return apply_filters( 'bbp_settings_integration', $integration, $default );
}

/**
 * How to interact with engagements
 *
 * There are 3 possible strategies:
 * - 'meta' 2.6 and higher. Uses multiple postmeta keys.
 * - 'user' Pre-2.6. Uses comma-separated string of IDs in usermeta.
 * - 'term' Alternate. Uses taxonomy term relationships.
 *
 * @since 2.6.0 bbPress (r6875)
 *
 * @param bool $default Optional. Default value false
 * @return string How to interact with engagements
 */
function bbp_engagements_strategy( $default = 'meta' ) {

	// Get the option value
	$integration = get_option( '_bbp_engagements_strategy', $default );

	// Check that class exists, or fallback
	$class_name  = 'BBP_User_Engagements_' . ucwords( $integration );

	// Fallback to 'meta' if invalid
	if ( ! class_exists( $class_name ) ) {
		$integration = 'meta';
	}

	// Filter & return
	return apply_filters( 'bbp_engagements_strategy', $integration, $default );
}

/** Slugs *********************************************************************/

/**
 * Return the root slug
 *
 * @since 2.1.0 bbPress (r3759)
 *
 * @param string $default Optional. Default value 'forums'
 * @return string
 */
function bbp_get_root_slug( $default = 'forums' ) {

	// Filter & return
	return apply_filters( 'bbp_get_root_slug', get_option( '_bbp_root_slug', $default ) );
}

/**
 * Are we including the root slug in front of forum pages?
 *
 * @since 2.1.0 bbPress (r3759)
 *
 * @param bool $default Optional. Default value true
 * @return bool
 */
function bbp_include_root_slug( $default = 1 ) {

	// Filter & return
	return (bool) apply_filters( 'bbp_include_root_slug', (bool) get_option( '_bbp_include_root', $default ) );
}

/**
 * What to show on root, forums or topics?
 *
 * @since 2.4.0 bbPress (r4932)
 *
 * @param string $default Optional. Default value 'forums'
 * @return string
 */
function bbp_show_on_root( $default = 'forums' ) {

	// Filter & return
	return apply_filters( 'bbp_show_on_root', get_option( '_bbp_show_on_root', $default ) );
}

/**
 * Maybe return the root slug, based on whether or not it's included in the url
 *
 * @since 2.1.0 bbPress (r3759)
 *
 * @param string $default Optional. Default value 'forums'
 * @return string
 */
function bbp_maybe_get_root_slug() {
	$slug   = bbp_get_root_slug();
	$retval = ( ! empty( $slug ) && bbp_include_root_slug() )
		? trailingslashit( $slug )
		: '';

	// Filter & return
	return apply_filters( 'bbp_maybe_get_root_slug', $retval );
}

/**
 * Return the single forum slug
 *
 * @since 2.1.0 bbPress (r3759)
 *
 * @param string $default Optional. Default value 'forum'
 * @return string
 */
function bbp_get_forum_slug( $default = 'forum' ) {

	// Filter & return
	return apply_filters( 'bbp_get_forum_slug', bbp_maybe_get_root_slug() . get_option( '_bbp_forum_slug', $default ) );
}

/**
 * Return the topic archive slug
 *
 * @since 2.1.0 bbPress (r3759)
 *
 * @param string $default Optional. Default value 'topics'
 * @return string
 */
function bbp_get_topic_archive_slug( $default = 'topics' ) {

	// Filter & return
	return apply_filters( 'bbp_get_topic_archive_slug', get_option( '_bbp_topic_archive_slug', $default ) );
}

/**
 * Return the reply archive slug
 *
 * @since 2.4.0 bbPress (r4925)
 *
 * @param string $default Optional. Default value 'replies'
 * @return string
 */
function bbp_get_reply_archive_slug( $default = 'replies' ) {

	// Filter & return
	return apply_filters( 'bbp_get_reply_archive_slug', get_option( '_bbp_reply_archive_slug', $default ) );
}

/**
 * Return the single topic slug
 *
 * @since 2.1.0 bbPress (r3759)
 *
 * @param string $default Optional. Default value 'topic'
 * @return string
 */
function bbp_get_topic_slug( $default = 'topic' ) {

	// Filter & return
	return apply_filters( 'bbp_get_topic_slug', bbp_maybe_get_root_slug() . get_option( '_bbp_topic_slug', $default ) );
}

/**
 * Return the topic-tag taxonomy slug
 *
 * @since 2.1.0 bbPress (r3759)
 *
 * @param string $default Optional. Default value 'topic-tag'
 * @return string
 */
function bbp_get_topic_tag_tax_slug( $default = 'topic-tag' ) {

	// Filter & return
	return apply_filters( 'bbp_get_topic_tag_tax_slug', bbp_maybe_get_root_slug() . get_option( '_bbp_topic_tag_slug', $default ) );
}

/**
 * Return the single reply slug (used mostly for editing)
 *
 * @since 2.1.0 bbPress (r3759)
 *
 * @param string $default Optional. Default value 'reply'
 * @return string
 */
function bbp_get_reply_slug( $default = 'reply' ) {

	// Filter & return
	return apply_filters( 'bbp_get_reply_slug', bbp_maybe_get_root_slug() . get_option( '_bbp_reply_slug', $default ) );
}

/**
 * Return the single user slug
 *
 * @since 2.1.0 bbPress (r3759)
 *
 * @param string $default Optional. Default value 'users'
 * @return string
 */
function bbp_get_user_slug( $default = 'users' ) {

	// Filter & return
	return apply_filters( 'bbp_get_user_slug', bbp_maybe_get_root_slug() . get_option( '_bbp_user_slug', $default ) );
}

/**
 * Return the single user favorites slug
 *
 * @since 2.2.0 bbPress (r4187)
 *
 * @param string $default Optional. Default value 'favorites'
 * @return string
 */
function bbp_get_user_favorites_slug( $default = 'favorites' ) {

	// Filter & return
	return apply_filters( 'bbp_get_user_favorites_slug', get_option( '_bbp_user_favs_slug', $default ) );
}

/**
 * Return the single user subscriptions slug
 *
 * @since 2.2.0 bbPress (r4187)
 *
 * @param string $default Optional. Default value 'subscriptions'
 * @return string
 */
function bbp_get_user_subscriptions_slug( $default = 'subscriptions' ) {

	// Filter & return
	return apply_filters( 'bbp_get_user_subscriptions_slug', get_option( '_bbp_user_subs_slug', $default ) );
}

/**
 * Return the single user engagements slug
 *
 * @since 2.6.0 bbPress (r6320)
 *
 * @param string $default Optional. Default value 'engagements'
 * @return string
 */
function bbp_get_user_engagements_slug( $default = 'engagements' ) {

	// Filter & return
	return apply_filters( 'bbp_get_user_engagements_slug', get_option( '_bbp_user_engs_slug', $default ) );
}

/**
 * Return the topic view slug
 *
 * @since 2.1.0 bbPress (r3759)
 *
 * @param string $default Optional. Default value 'view'
 * @return string
 */
function bbp_get_view_slug( $default = 'view' ) {

	// Filter & return
	return apply_filters( 'bbp_get_view_slug', bbp_maybe_get_root_slug() . get_option( '_bbp_view_slug', $default ) );
}

/**
 * Return the search slug
 *
 * @since 2.3.0 bbPress (r4579)
 *
 * @param string $default Optional. Default value 'search'
 * @return string
 */
function bbp_get_search_slug( $default = 'search' ) {

	// Filter & return
	return apply_filters( 'bbp_get_search_slug', bbp_maybe_get_root_slug() . get_option( '_bbp_search_slug', $default ) );
}

/**
 * Return the edit slug
 *
 * @since 2.6.2 bbPress (r6965)
 *
 * @param string $default Optional. Default value 'edit'
 * @return string
 */
function bbp_get_edit_slug( $default = 'edit' ) {

	// Filter & return
	return apply_filters( 'bbp_get_edit_slug', get_option( '_bbp_edit_slug', $default ) );
}

/** Legacy ********************************************************************/

/**
 * Checks if there is a previous BuddyPress Forum configuration
 *
 * @since 2.1.0 bbPress (r3790)
 *
 * @param string $default Optional. Default empty string
 * @return string The location of the bb-config.php file, if any
 */
function bbp_get_config_location( $default = '' ) {

	// Filter & return
	return apply_filters( 'bbp_get_config_location', get_option( 'bb-config-location', $default ) );
}
