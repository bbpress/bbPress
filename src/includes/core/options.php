<?php

/**
 * bbPress Options.
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
 * @return array<string, mixed> Filtered option names and values.
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

			'_bbp_flag_initial_content'   => 1,         // Flag to create initial content

			/** Features **********************************************************/

			'_bbp_enable_favorites'       => 1,         // Enable favorites
			'_bbp_enable_subscriptions'   => 1,         // Enable subscriptions
			'_bbp_enable_engagements'     => 1,         // Enable engagements
			'_bbp_allow_content_edit'     => 1,         // Allow content edit
			'_bbp_allow_content_throttle' => 1,         // Allow content throttle
			'_bbp_allow_moderation_links' => 1,         // Allow link moderation
			'_bbp_allow_revisions'        => 1,         // Allow revisions
			'_bbp_allow_topic_tags'       => 1,         // Allow topic tagging
			'_bbp_allow_search'           => 1,         // Allow forum-wide search
			'_bbp_allow_forum_mods'       => 1,         // Allow per-forum moderation
			'_bbp_allow_global_access'    => 1,         // Save, grant, or skip automatic forum roles
			'_bbp_allow_anonymous'        => 0,         // Allow anonymous posting
			'_bbp_allow_super_mods'       => 0,         // Allow mods to edit users
			'_bbp_allow_threaded_replies' => 0,         // Allow threaded replies
			'_bbp_use_wp_editor'          => 1,         // Use the WordPress editor if available
			'_bbp_use_autoembed'          => 0,         // Use oEmbed in topics and replies

			/** Settings **********************************************************/

			'_bbp_forums_status'          => 'open',    // Site-wide forum content status
			'_bbp_moderation_links'       => false,     // Inherit WordPress comment link limit
			'_bbp_default_role'           => $role,     // Default forums role
			'_bbp_user_block_actions'     => array(),   // Actions to run when blocking a user
			'_bbp_edit_lock'              => 5,         // Lock post editing after 5 minutes
			'_bbp_throttle_time'          => 10,        // Throttle post time to 10 seconds
			'_bbp_thread_replies_depth'   => 2,         // Thread replies depth
			'_bbp_theme_package_id'       => 'default', // The ID for the current theme package
			'_bbp_settings_integration'   => 'basic',   // How to integrate into wp-admin
			'_bbp_engagements_strategy'   => 'meta',    // How to interact with engagements
			'_bbp_moderation_notification_recipient_types' => array(), // Who receives moderation notifications

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
			'_bbp_user_mentions_slug'   => 'mentions',      // User mentions slug
			'_bbp_user_favs_slug'       => 'favorites',     // User favorites slug
			'_bbp_user_subs_slug'       => 'subscriptions', // User subscriptions slug
			'_bbp_topic_archive_slug'   => 'topics',        // Topic archive slug
			'_bbp_reply_archive_slug'   => 'replies',       // Reply archive slug

			/** Other Slugs *******************************************************/

			'_bbp_view_slug'            => 'view',        // View slug
			'_bbp_search_slug'          => 'search',      // Search slug
			'_bbp_unsubscribe_slug'     => 'unsubscribe', // Subscription unsubscribe slug

			/** Topics ************************************************************/

			'_bbp_title_max_length'     => 80,          // Title Max Length
			'_bbp_super_sticky_topics'  => '',          // Super stickies

			/** Topic Tags ********************************************************/

			'_bbp_topic_tag_edit_scope'   => 'all_participants', // Who can change existing topic tags

			/** Forums ************************************************************/

			'_bbp_private_forums'           => '',      // Private forums
			'_bbp_hidden_forums'            => '',      // Hidden forums
			'_bbp_forum_visibility_version' => '',      // Forum visibility source version

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

	/**
	 * Filters the default site options and their values.
	 *
	 * @since 2.0.0 bbPress (r3421)
	 *
	 * @param array<string, mixed> $options Filtered option names and values.
	 */
	return (array) apply_filters( 'bbp_get_default_options', $options );
}

/**
 * Add default options.
 *
 * Hooked to bbp_activate, it is only called once when bbPress is activated.
 * This is non-destructive, so existing settings will not be overridden.
 *
 * @since 2.0.0 bbPress (r2786)
 *
 * @return void
 */
function bbp_add_options() {

	// Add default options
	foreach ( bbp_get_default_options() as $key => $value ) {
		add_option( $key, $value );
	}

	// Allow previously activated plugins to append their own options.
	/**
	 * Fires when bbPress adds default options.
	 *
	 * @since 2.0.0 bbPress (r2786)
	 */
	do_action( 'bbp_add_options' );
}

/**
 * Delete default options.
 *
 * Hooked to bbp_uninstall, it is only called once when bbPress is uninstalled.
 * This is destructive, so existing settings will be destroyed.
 *
 * @since 2.0.0 bbPress (r3421)
 * @since 2.7.0 bbPress (r8252) Delete temporary forum tool run state.
 * @since 2.7.0 bbPress (r8253) Delete built-in tool batch checkpoints.
 * @since 2.7.0 bbPress (r8284) Delete temporary forum hierarchy nodes.
 * @since 2.7.0 bbPress (r8303) Clean up the shared batch namespace.
 * @since 2.7.0 bbPress (r8350) Delete Dry Run, lock, and mention backfill state.
 *
 * @return void
 */
function bbp_delete_options() {

	// Add default options
	foreach ( array_keys( bbp_get_default_options() ) as $key ) {
		delete_option( $key );
	}

	// Delete non-default temporary options
	delete_option( '_bbp_tools_run_repair' );
	delete_option( '_bbp_tools_run_upgrade' );
	delete_option( '_bbp_tools_dry_run_repair' );
	delete_option( '_bbp_tools_dry_run_upgrade' );
	delete_option( '_bbp_pending_upgrades_lock' );
	delete_option( '_bbp_mention_backfill' );
	delete_option( '_bbp_slug_translation_prompt' );

	// Delete batch checkpoints and scratch data without loading admin tools
	bbp_delete_options_with_prefix( '_bbp_tool_batch_' );
	bbp_delete_options_with_prefix( '_bbp_tool_lock_' );

	// Allow previously activated plugins to append their own options.
	/**
	 * Fires when bbPress deletes its options.
	 *
	 * @since 2.0.0 bbPress (r3421)
	 */
	do_action( 'bbp_delete_options' );
}

/**
 * Delete non-autoloaded options in one internal namespace.
 *
 * @since 2.7.0 bbPress (r8350)
 * @access private
 *
 * @param string $prefix Exact option-name prefix.
 * @return bool Whether every discovered option was deleted.
 */
function bbp_delete_options_with_prefix( $prefix = '' ) {
	$bbp_db = bbp_db();
	$prefix = (string) $prefix;
	if ( empty( $prefix ) ) {
		return false;
	}

	$pattern = $bbp_db->esc_like( $prefix ) . '%';
	do {
		$names = $bbp_db->get_col( $bbp_db->prepare( "SELECT option_name FROM {$bbp_db->options} WHERE option_name LIKE %s ORDER BY option_name LIMIT 100", $pattern ) );
		if ( ! is_array( $names ) || in_array( null, $names, true ) || ! empty( $bbp_db->last_error ) ) {
			return false;
		}
		foreach ( $names as $name ) {
			if ( ! delete_option( $name ) ) {
				return false;
			}
		}
		if ( ! empty( $names ) ) {
			$notoptions = wp_cache_get( 'notoptions', 'options' );
			if ( is_array( $notoptions ) ) {
				wp_cache_set( 'notoptions', array_diff_key( $notoptions, array_flip( $names ) ), 'options' );
			}
		}
		$count = count( $names );
	} while ( 100 === $count );

	return true;
}

/**
 * Add filters to each bbPress option and allow them to be overloaded from
 * inside the $bbp->options array.
 *
 * @since 2.0.0 bbPress (r3452)
 *
 * @return void
 */
function bbp_setup_option_filters() {

	// Add filters to each bbPress option
	foreach ( array_keys( bbp_get_default_options() ) as $key ) {
		add_filter( 'pre_option_'     . $key, 'bbp_filter_pre_get_option', 10, 2 );
		add_filter( 'default_option_' . $key, 'bbp_filter_default_option', 10, 3 );
	}

	// Allow previously activated plugins to append their own options.
	/**
	 * Fires when bbPress sets up option filters.
	 *
	 * @since 2.0.0 bbPress (r3452)
	 */
	do_action( 'bbp_setup_option_filters' );
}

/**
 * Filter pre options and maybe overloaded from the $bbp->options array.
 *
 * This function should not be called directly.
 *
 * @since 2.6.0 bbPress (r6544)
 * @access private
 *
 * @param bool   $value  Default value false.
 * @param string $option Name of the option.
 *
 * @return mixed false if not overloaded, mixed if set.
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
 * @since 2.6.0 bbPress (r6544)
 * @access private
 *
 * @param mixed  $default        Default option value.
 * @param string $option         Option name.
 * @param bool   $passed_default Whether the default was explicitly passed.
 * @return mixed Default or configured option value.
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
 * @since 2.6.0 bbPress (r6544)
 *
 * @return mixed Intercepted result, or null when option loading is skipped or finished.
 */
function bbp_pre_load_options() {

	// Bail if using object cache or installing
	if ( wp_using_ext_object_cache() || wp_installing() ) {
		return;
	}

	// Maybe intercept

	/**
	 * Filters how bbPress options are preloaded.
	 *
	 * @since 2.6.0 bbPress (r6762)
	 *
	 * @param string $value How bbPress options are preloaded.
	 */
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

/** Forum Status **************************************************************/

/**
 * Return the available site-wide forum statuses.
 *
 * @since 2.6.20 bbPress (r7851)
 *
 * @return array<string, string> Status labels keyed by status ID.
 */
function bbp_get_forums_statuses() {
	$statuses = array(
		'open'   => _x( 'Open',   'Site-wide forum status', 'bbpress' ),
		'closed' => _x( 'Closed', 'Site-wide forum status', 'bbpress' ),
		'frozen' => _x( 'Frozen', 'Site-wide forum status', 'bbpress' )
	);

	/**
	 * Filters the available site-wide forum status labels.
	 *
	 * @since 2.6.20 bbPress (r7851)
	 *
	 * @param array<string, string> $statuses The available site-wide forum status labels.
	 */
	return (array) apply_filters( 'bbp_get_forums_statuses', $statuses );
}

/**
 * Get the site-wide forums status.
 *
 * @since 2.6.20 bbPress (r7851)
 *
 * @return string Valid status key, or open when the saved value is invalid.
 */
function bbp_get_forums_status() {
	$status   = get_option( '_bbp_forums_status', 'open' );
	$statuses = bbp_get_forums_statuses();

	return is_string( $status ) && isset( $statuses[ $status ] )
		? $status
		: 'open';
}

/**
 * Check the site-wide forums status.
 *
 * @since 2.6.20 bbPress (r7851)
 *
 * @param string $status Status to check.
 * @return bool Whether the current status matches.
 */
function bbp_is_forums_status( $status ) {
	return is_string( $status ) && ( bbp_get_forums_status() === $status );
}

/**
 * Check whether a user is a Keymaster or site-wide Moderator.
 *
 * Read the assigned capability directly so scoped BuddyPress and forum
 * moderation cannot grant a site-wide exception.
 *
 * @since 2.6.20 bbPress (r7851)
 *
 * @param int $user_id User ID.
 * @return bool Whether the user has a site-wide moderation role or capability.
 */
function bbp_user_is_keymaster_or_moderator( $user_id ) {
	$user = get_userdata( (int) $user_id );

	return $user && ( bbp_is_user_keymaster( $user->ID ) || ! empty( $user->allcaps['moderate'] ) );
}

/**
 * Check whether the site-wide status allows a user to post anywhere.
 *
 * This policy supplements the existing post-type and per-forum permissions.
 *
 * @since 2.6.20 bbPress (r7851)
 *
 * @param int $user_id User ID.
 * @return bool Whether the site-wide status allows posting in all forums.
 */
function bbp_user_can_post_in_forums( $user_id ) {
	if ( bbp_is_forums_status( 'open' ) ) {
		$retval = true;
	} elseif ( bbp_is_forums_status( 'frozen' ) ) {
		$retval = false;
	} else {
		$retval = bbp_user_is_keymaster_or_moderator( $user_id );
	}

	/**
	 * Filters whether a user can post given the site-wide forum status.
	 *
	 * @since 2.6.20 bbPress (r7851)
	 *
	 * @param bool $retval Whether a user can post given the site-wide forum status.
	 * @param int $user_id User id.
	 * @param string $status Site-wide forum status.
	 */
	return (bool) apply_filters( 'bbp_user_can_post_in_forums', $retval, $user_id, bbp_get_forums_status() );
}

/**
 * Check whether the site-wide status allows the current user to post anywhere.
 *
 * @since 2.6.20 bbPress (r7851)
 *
 * @return bool Whether the site-wide status allows posting in all forums.
 */
function bbp_current_user_can_post_in_forums() {
	return bbp_user_can_post_in_forums( get_current_user_id() );
}

/**
 * Check whether the site-wide status permits a user to edit forums, topics, and replies.
 *
 * @since 2.6.20 bbPress (r7851)
 *
 * @param int $user_id User ID.
 * @return bool Whether the site-wide status permits the user to edit.
 */
function bbp_user_can_edit_in_forums( $user_id ) {
	if ( ! bbp_is_forums_status( 'frozen' ) ) {
		$retval = true;
	} else {
		$retval = bbp_user_is_keymaster_or_moderator( $user_id );
	}

	/**
	 * Filters whether a user can edit posts given the site-wide forum status.
	 *
	 * @since 2.6.20 bbPress (r7851)
	 *
	 * @param bool $retval Whether a user can edit posts given the site-wide forum status.
	 * @param int $user_id User id.
	 * @param string $status Site-wide forum status.
	 */
	return (bool) apply_filters( 'bbp_user_can_edit_in_forums', $retval, $user_id, bbp_get_forums_status() );
}

/** Moderation ****************************************************************/

/**
 * Get the link threshold that holds forum content for moderation.
 *
 * Inherit the WordPress comment moderation limit until a forum-specific value
 * is saved.
 *
 * @since 2.7.0 bbPress (r7974)
 *
 * @return int Forum moderation link limit.
 */
function bbp_get_moderation_link_limit() {
	$limit = get_option( '_bbp_moderation_links', false );

	if ( false === $limit || '' === $limit ) {
		$limit = get_option( 'comment_max_links', 0 );
	}

	$limit = bbp_parse_int( $limit );

	/**
	 * Filters the number of links allowed before moderation.
	 *
	 * @since 2.7.0 bbPress (r7974)
	 *
	 * @param int $limit The number of links allowed before moderation.
	 */
	$limit = apply_filters( 'bbp_get_moderation_link_limit', $limit );
	return bbp_parse_int( $limit );
}

/**
 * Checks if favorites feature is enabled.
 *
 * @since 2.0.0 bbPress (r2680)
 *
 * @param bool|int $default Optional. Default value true.
 * @return bool Is favorites enabled or not.
 */
function bbp_is_favorites_active( $default = 1 ) {

	/**
	 * Filters whether favorites feature is enabled.
	 *
	 * @since 2.0.0 bbPress (r2812)
	 *
	 * @param bool $value Whether favorites feature is enabled.
	 */
	return (bool) apply_filters( 'bbp_is_favorites_active', (bool) get_option( '_bbp_enable_favorites', $default ) );
}

/**
 * Checks if any subscription feature is enabled.
 *
 * @since 2.0.0 bbPress (r2668)
 * @since 2.7.0 bbPress (r8330) Support per-subtype settings.
 *
 * @param mixed $default Optional. Default subscription value.
 * @return bool Whether any subscriptions are enabled.
 */
function bbp_is_subscriptions_active( $default = 1 ) {
	$value  = get_option( '_bbp_enable_subscriptions', $default );
	$active = is_array( $value )
		? ! empty( bbp_get_subscription_subtypes( $default ) )
		: (bool) $value;

	/**
	 * Filters whether any subscription feature is enabled.
	 *
	 * @since 2.0.0 bbPress (r2812)
	 *
	 * @param bool $active Whether any subscriptions are enabled.
	 */
	return (bool) apply_filters( 'bbp_is_subscriptions_active', $active );
}

/**
 * Get labels for registered object subtypes that support subscriptions.
 *
 * Post types and taxonomies opt in with the `bbp_subscriptions` registration
 * argument.
 *
 * @since 2.7.0 bbPress (r8330)
 *
 * @return array<string, string> Subscription subtype labels keyed by object type and subtype.
 */
function bbp_get_subscription_subtype_labels() {
	$labels = array();

	// Registered post types
	foreach ( get_post_types( array(), 'objects' ) as $post_type ) {
		if ( ! empty( $post_type->bbp_subscriptions ) ) {
			$labels[ 'post:' . $post_type->name ] = $post_type->labels->name;
		}
	}

	// Registered taxonomies
	foreach ( get_taxonomies( array(), 'objects' ) as $taxonomy ) {
		if ( ! empty( $taxonomy->bbp_subscriptions ) ) {
			$labels[ 'term:' . $taxonomy->name ] = $taxonomy->labels->name;
		}
	}

	/**
	 * Filters the labels for registered object subtypes that support subscriptions.
	 *
	 * @since 2.7.0 bbPress (r8330)
	 *
	 * @param array<string, string> $labels Subscription subtype labels keyed by object type and subtype.
	 */
	return (array) apply_filters( 'bbp_get_subscription_subtype_labels', $labels );
}

/**
 * Get enabled subscription object subtypes.
 *
 * The old scalar option enables or disables every registered subtype. An array
 * enables only the selected subtypes.
 *
 * @since 2.7.0 bbPress (r8330)
 *
 * @param mixed $default Optional. Default subscription value.
 * @return array<int, string> Subscription subtype IDs.
 */
function bbp_get_subscription_subtypes( $default = 1 ) {
	$subtypes  = get_option( '_bbp_enable_subscriptions', $default );
	$available = bbp_get_subscription_subtype_labels();

	if ( is_array( $subtypes ) ) {
		$subtypes = array_filter( $subtypes, 'is_string' );
		$subtypes = array_values( array_unique( array_filter( array_map( 'sanitize_text_field', $subtypes ) ) ) );
		$subtypes = array_values( array_intersect( $subtypes, array_keys( $available ) ) );
	} else {
		$subtypes = ! empty( $subtypes )
			? array_keys( $available )
			: array();
	}

	/**
	 * Filters the enabled subscription object subtypes.
	 *
	 * @since 2.7.0 bbPress (r8330)
	 *
	 * @param array<int, string> $subtypes Subscription subtype IDs.
	 */
	return (array) apply_filters( 'bbp_get_subscription_subtypes', $subtypes );
}

/**
 * Check whether subscriptions are enabled for an object subtype.
 *
 * The post type or taxonomy must be registered before this check runs.
 *
 * @since 2.7.0 bbPress (r8330)
 *
 * @param string $object_type    Engagement object type.
 * @param string $object_subtype Registered post type or taxonomy name.
 * @param mixed  $default        Optional. Default subscription value.
 * @return bool Whether subscriptions are enabled for the object subtype.
 */
function bbp_is_subscription_subtype_active( $object_type = '', $object_subtype = '', $default = 1 ) {
	$object_type    = sanitize_key( $object_type );
	$object_subtype = sanitize_key( $object_subtype );
	$subtype_id     = $object_type . ':' . $object_subtype;
	$value          = get_option( '_bbp_enable_subscriptions', $default );
	$enabled        = bbp_is_subscriptions_active( $default );

	// A filter can enable every registered subtype for a disabled scalar option.
	if ( $enabled && ! is_array( $value ) && empty( $value ) ) {
		$subtypes = array_keys( bbp_get_subscription_subtype_labels() );
		/** This filter is documented in src/includes/core/options.php. */
		$subtypes = (array) apply_filters( 'bbp_get_subscription_subtypes', $subtypes );
	} else {
		$subtypes = bbp_get_subscription_subtypes( $default );
	}

	$active = ! empty( $object_type )
		&& ! empty( $object_subtype )
		&& $enabled
		&& in_array( $subtype_id, $subtypes, true );

	/**
	 * Filters whether subscriptions are enabled for an object subtype.
	 *
	 * @since 2.7.0 bbPress (r8330)
	 *
	 * @param bool $active Whether subscriptions are enabled for the object subtype.
	 * @param string $object_type Engagement object type.
	 * @param string $object_subtype Registered post type or taxonomy name.
	 */
	return (bool) apply_filters( 'bbp_is_subscription_subtype_active', $active, $object_type, $object_subtype );
}

/**
 * Checks if engagements feature is enabled.
 *
 * @since 2.6.0 bbPress (r6321)
 *
 * @param bool|int $default Optional. Default value true.
 * @return bool Is engagements enabled or not.
 */
function bbp_is_engagements_active( $default = 1 ) {

	/**
	 * Filters whether engagements feature is enabled.
	 *
	 * @since 2.6.0 bbPress (r6321)
	 *
	 * @param bool $value Whether engagements feature is enabled.
	 */
	return (bool) apply_filters( 'bbp_is_engagements_active', (bool) get_option( '_bbp_enable_engagements', $default ) );
}

/**
 * Get available moderation notification recipient type labels.
 *
 * @since 2.7.0 bbPress (r8089)
 *
 * @return array<string, string> Recipient type labels keyed by type ID.
 */
function bbp_get_moderation_notification_recipient_type_labels() {
	$labels = array(
		'keymasters'       => esc_html__( 'Keymasters', 'bbpress' ),
		'moderators'       => esc_html__( 'Moderators', 'bbpress' ),
		'forum_moderators' => esc_html__( 'Per-forum Moderators', 'bbpress' )
	);

	/**
	 * Filters the available moderation notification recipient type labels.
	 *
	 * @since 2.7.0 bbPress (r8089)
	 *
	 * @param array<string, string> $labels Recipient type labels keyed by type ID.
	 */
	return (array) apply_filters( 'bbp_get_moderation_notification_recipient_type_labels', $labels );
}

/**
 * Get enabled moderation notification recipient types.
 *
 * @since 2.7.0 bbPress (r8089)
 *
 * @param array<int, string> $default Optional. Default recipient types.
 * @return array<int, string> Recipient type IDs.
 */
function bbp_get_moderation_notification_recipient_types( $default = array() ) {
	$types     = get_option( '_bbp_moderation_notification_recipient_types', $default );
	$types     = is_array( $types )
		? array_values( array_unique( array_filter( array_map( 'sanitize_key', $types ) ) ) )
		: array();
	$available = bbp_get_moderation_notification_recipient_type_labels();
	$types     = array_values( array_intersect( $types, array_keys( $available ) ) );

	/**
	 * Filters the enabled moderation notification recipient types.
	 *
	 * @since 2.7.0 bbPress (r8089)
	 *
	 * @param array<int, string> $types Recipient type IDs.
	 */
	return (array) apply_filters( 'bbp_get_moderation_notification_recipient_types', $types );
}

/**
 * Are moderation notifications enabled?
 *
 * @since 2.7.0 bbPress (r8089)
 *
 * @return bool Whether moderation notifications are enabled.
 */
function bbp_is_moderation_notifications_active() {
	$active = ! empty( bbp_get_moderation_notification_recipient_types() );

	/**
	 * Filters whether moderation notifications are enabled.
	 *
	 * @since 2.7.0 bbPress (r8089)
	 *
	 * @param bool $active Whether moderation notifications are enabled.
	 */
	return (bool) apply_filters( 'bbp_is_moderation_notifications_active', $active );
}

/**
 * Is content editing available when posting new topics & replies?
 *
 * @since 2.6.0 bbPress (r6442)
 *
 * @param bool|int $default Optional. Default value true.
 * @return bool Is content editing allowed?
 */
function bbp_allow_content_edit( $default = 1 ) {

	/**
	 * Filters whether content editing is available when posting new topics and replies.
	 *
	 * @since 2.6.0 bbPress (r6442)
	 *
	 * @param bool $value Whether content editing is available when posting new topics and replies.
	 */
	return (bool) apply_filters( 'bbp_allow_content_edit', (bool) get_option( '_bbp_allow_content_edit', $default ) );
}

/**
 * Is content throttling engaged when posting new topics & replies?
 *
 * @since 2.6.0 bbPress (r6442)
 *
 * @param bool|int $default Optional. Default value true.
 * @return bool Is content throttling allowed?
 */
function bbp_allow_content_throttle( $default = 1 ) {

	/**
	 * Filters whether content throttling is engaged when posting new topics and replies.
	 *
	 * @since 2.6.0 bbPress (r6442)
	 *
	 * @param bool $value Whether content throttling is engaged when posting new topics and replies.
	 */
	return (bool) apply_filters( 'bbp_allow_content_throttle', (bool) get_option( '_bbp_allow_content_throttle', $default ) );
}

/**
 * Is link moderation enabled for new topics and replies?
 *
 * @since 2.7.0 bbPress (r7974)
 *
 * @param bool|int $default Optional. Default value true.
 * @return bool Whether link moderation is enabled.
 */
function bbp_allow_moderation_links( $default = 1 ) {

	/**
	 * Filters whether link moderation is enabled for new topics and replies.
	 *
	 * @since 2.7.0 bbPress (r7974)
	 *
	 * @param bool $value Whether link moderation is enabled.
	 */
	return (bool) apply_filters( 'bbp_allow_moderation_links', (bool) get_option( '_bbp_allow_moderation_links', $default ) );
}

/**
 * Are topic tags allowed.
 *
 * @since 2.2.0 bbPress (r4098)
 *
 * @param bool|int $default Optional. Default value true.
 * @return bool Are tags allowed?
 */
function bbp_allow_topic_tags( $default = 1 ) {

	/**
	 * Filters whether topic tags are allowed.
	 *
	 * @since 2.2.0 bbPress (r4098)
	 *
	 * @param bool $value Whether topic tags are allowed.
	 */
	return (bool) apply_filters( 'bbp_allow_topic_tags', (bool) get_option( '_bbp_allow_topic_tags', $default ) );
}

/**
 * Who may change existing topic tags, in addition to moderators.
 *
 * @since 2.7.0 bbPress (r8615)
 *
 * @param string $default Optional. Default value 'all_participants'.
 * @return string Valid topic tag edit scope.
 */
function bbp_topic_tag_edit_scope( $default = 'all_participants' ) {
	$scopes = array( 'all_participants', 'authors_only' );
	$scope  = get_option( '_bbp_topic_tag_edit_scope', $default );

	// Fall back to the longstanding behavior for an invalid stored value
	if ( ! in_array( $scope, $scopes, true ) ) {
		$scope = 'all_participants';
	}

	// Filter the scope

	/**
	 * Filters the editing scope for topic tags.
	 *
	 * @since 2.7.0 bbPress (r8615)
	 *
	 * @param string $scope The editing scope for topic tags.
	 * @param string $default Default.
	 */
	$scope = apply_filters( 'bbp_topic_tag_edit_scope', $scope, $default );

	// Return a valid scope
	return in_array( $scope, $scopes, true )
		? $scope
		: 'all_participants';
}

/**
 * Are per-forum moderators allowed.
 *
 * @since 2.6.0 bbPress (r5834)
 *
 * @param bool|int $default Optional. Default value true.
 *
 * @return bool Are per-forum moderators allowed?
 */
function bbp_allow_forum_mods( $default = 1 ) {

	/**
	 * Filters whether per-forum moderators are allowed.
	 *
	 * @since 2.6.0 bbPress (r5834)
	 *
	 * @param bool $value Whether per-forum moderators are allowed.
	 */
	return (bool) apply_filters( 'bbp_allow_forum_mods', (bool) get_option( '_bbp_allow_forum_mods', $default ) );
}

/**
 * Are moderators allowed to edit users.
 *
 * @since 2.6.0 bbPress (r6563)
 *
 * @param bool|int $default Optional. Default value false.
 *
 * @return bool Are moderators allowed to edit users?
 */
function bbp_allow_super_mods( $default = 0 ) {

	/**
	 * Filters whether moderators are allowed to edit users.
	 *
	 * @since 2.6.0 bbPress (r6563)
	 *
	 * @param bool $value Whether moderators are allowed to edit users.
	 */
	return (bool) apply_filters( 'bbp_allow_super_mods', (bool) get_option( '_bbp_allow_super_mods', $default ) );
}

/**
 * Is forum-wide searching allowed.
 *
 * @since 2.4.0 bbPress (r4971)
 *
 * @param bool|int $default Optional. Default value true.
 * @return bool Is forum-wide searching allowed?
 */
function bbp_allow_search( $default = 1 ) {

	/**
	 * Filters whether forum search is enabled.
	 *
	 * @since 2.4.0 bbPress (r4971)
	 *
	 * @param bool $value Whether forum search is enabled.
	 */
	return (bool) apply_filters( 'bbp_allow_search', (bool) get_option( '_bbp_allow_search', $default ) );
}

/**
 * Are threaded replies allowed.
 *
 * @since 2.4.0 bbPress (r4963)
 *
 * @param bool|int $default Optional. Default value false.
 * @return bool Are threaded replies allowed?
 */
function bbp_allow_threaded_replies( $default = 0 ) {

	// Apply the legacy filter first for backward compatibility
	/**
	 * Filters the legacy threaded-replies setting.
	 *
	 * @since 2.4.0 bbPress (r4963)
	 *
	 * @param bool $allow Whether threaded replies are allowed.
	 */
	$allow = (bool) apply_filters( '_bbp_allow_threaded_replies', (bool) get_option( '_bbp_allow_threaded_replies', $default ) );

	/**
	 * Filters whether threaded replies are allowed.
	 *
	 * @since 2.6.20 bbPress (r7830)
	 *
	 * @param bool $allow Whether threaded replies are allowed.
	 */
	return (bool) apply_filters( 'bbp_allow_threaded_replies', $allow );
}

/**
 * Maximum reply thread depth.
 *
 * @since 2.4.0 bbPress (r4944)
 *
 * @param int $default Thread replies depth.
 * @return int Thread replies depth.
 */
function bbp_thread_replies_depth( $default = 2 ) {
	$depth = bbp_parse_int( get_option( '_bbp_thread_replies_depth', $default ) );

	/**
	 * Filters the thread replies depth.
	 *
	 * @since 2.4.0 bbPress (r4944)
	 *
	 * @param int $value Thread replies depth.
	 */
	$depth = apply_filters( 'bbp_thread_replies_depth', $depth );
	return bbp_parse_int( $depth );
}

/**
 * Are topic and reply revisions allowed.
 *
 * @since 2.0.0 bbPress (r3416)
 *
 * @param bool|int $default Optional. Default value true.
 * @return bool Are revisions allowed?
 */
function bbp_allow_revisions( $default = 1 ) {

	/**
	 * Filters whether topic and reply revisions are allowed.
	 *
	 * @since 2.0.0 bbPress (r3416)
	 *
	 * @param bool $value Whether topic and reply revisions are allowed.
	 */
	return (bool) apply_filters( 'bbp_allow_revisions', (bool) get_option( '_bbp_allow_revisions', $default ) );
}

/**
 * Is the anonymous posting allowed?
 *
 * @since 2.0.0 bbPress (r2617)
 *
 * @param bool|int $default Optional. Default value false.
 * @return bool Is anonymous posting allowed?
 */
function bbp_allow_anonymous( $default = 0 ) {

	/**
	 * Filters whether anonymous posting is allowed.
	 *
	 * @since 2.0.0 bbPress (r2617)
	 *
	 * @param bool $value Whether anonymous posting is allowed.
	 */
	return apply_filters( 'bbp_allow_anonymous', (bool) get_option( '_bbp_allow_anonymous', $default ) );
}

/**
 * Is automatic forum role assignment disabled by the Do nothing choice?
 *
 * @since 2.7.0 bbPress (r8585)
 *
 * @param mixed $setting Optional. Global-access option value. Defaults to the saved option.
 * @return bool Whether automatic forum role assignment is disabled.
 */
function bbp_is_auto_role_disabled( $setting = null ) {
	if ( null === $setting ) {
		$setting = get_option( '_bbp_allow_global_access', 1 );
	}

	return ( 2 === bbp_parse_int( $setting ) );
}

/**
 * Is this forum available to all users on all sites in this installation?
 *
 * @since 2.0.0 bbPress (r3378)
 *
 * @param bool|int $default Optional. Default value true.
 * @return bool Is global access allowed?
 */
function bbp_allow_global_access( $default = 1 ) {

	/**
	 * Filters whether this forum is available to all users on all sites in this installation.
	 *
	 * @since 2.0.0 bbPress (r3378)
	 *
	 * @param bool $allowed Whether this forum is available to all users on all sites in this installation.
	 */
	$setting = get_option( '_bbp_allow_global_access', $default );
	$allowed = ! bbp_is_auto_role_disabled( $setting ) && (bool) $setting;

	/**
	 * Filters whether users have global access to forums.
	 *
	 * @since 2.0.0 bbPress (r3378)
	 *
	 * @param bool $allowed Whether users have global access to forums.
	 */
	return (bool) apply_filters( 'bbp_allow_global_access', $allowed );
}

/**
 * Return how forum roles are automatically given to users without one.
 *
 * The existing option values 1 and 0 retain their save and dynamic meanings.
 * Value 2 skips automatic forum roles without changing the legacy values.
 *
 * @since 2.7.0 bbPress (r8585)
 *
 * @return string "save", "dynamic", or "none".
 */
function bbp_get_auto_role_mode() {
	if ( bbp_is_auto_role_disabled() ) {
		return 'none';
	}

	return bbp_allow_global_access() ? 'save' : 'dynamic';
}

/**
 * Get the default forums role (issued to users on their first visit to the
 * forums) by bbp_set_current_user_default_role() and repair tools.
 *
 * @since 2.2.0 bbPress (r4294)
 *
 * @param string $default Optional. Default value 'bbp_participant'.
 * @return string The default forums user role.
 */
function bbp_get_default_role( $default = 'bbp_participant' ) {

	/**
	 * Filters the default forum role for new users.
	 *
	 * @since 2.2.0 bbPress (r4294)
	 *
	 * @param string $value The default forums user role.
	 */
	return apply_filters( 'bbp_get_default_role', get_option( '_bbp_default_role', $default ) );
}

/**
 * Get the actions to run when a user receives the blocked forum role.
 *
 * @since 2.7.0 bbPress (r8131)
 *
 * @param array<array-key, mixed> $default Optional. Default action IDs.
 * @return array<array-key, mixed> Selected action IDs.
 */
function bbp_get_user_block_action_ids( $default = array() ) {
	$actions = get_option( '_bbp_user_block_actions', $default );

	/**
	 * Filters the actions to run when a user receives the blocked forum role.
	 *
	 * @since 2.7.0 bbPress (r8131)
	 *
	 * @param array<array-key, mixed> $actions Selected action IDs.
	 */
	return (array) apply_filters( 'bbp_get_user_block_action_ids', (array) $actions );
}

/**
 * Use the WordPress editor if available.
 *
 * @since 2.1.0 bbPress (r3586)
 *
 * @param bool|int $default Optional. Default value true.
 * @return bool Use WP editor?
 */
function bbp_use_wp_editor( $default = 1 ) {

	/**
	 * Filters whether to use the WordPress editor.
	 *
	 * @since 2.1.0 bbPress (r3586)
	 *
	 * @param bool $value Use WP editor?
	 */
	return (bool) apply_filters( 'bbp_use_wp_editor', (bool) get_option( '_bbp_use_wp_editor', $default ) );
}

/**
 * Use WordPress's oEmbed API.
 *
 * @since 2.1.0 bbPress (r3753)
 *
 * @param bool|int $default Optional. Default value true.
 * @return bool Use oEmbed?
 */
function bbp_use_autoembed( $default = 1 ) {

	/**
	 * Filters whether to embed supported media automatically.
	 *
	 * @since 2.1.0 bbPress (r3753)
	 *
	 * @param bool $value Use oEmbed?
	 */
	return (bool) apply_filters( 'bbp_use_autoembed', (bool) get_option( '_bbp_use_autoembed', $default ) );
}

/**
 * Get the current theme package ID.
 *
 * @since 2.1.0 bbPress (r3830)
 *
 * @param string $default Optional. Default value 'default'.
 * @return string ID of the theme-package.
 */
function bbp_get_theme_package_id( $default = 'default' ) {

	/**
	 * Filters the current theme package ID.
	 *
	 * @since 2.1.0 bbPress (r3830)
	 *
	 * @param string $value ID of the theme-package.
	 */
	return apply_filters( 'bbp_get_theme_package_id', get_option( '_bbp_theme_package_id', $default ) );
}

/**
 * Output the maximum length of a title.
 *
 * @since 2.0.0 bbPress (r3246)
 *
 * @param int $default Optional. Default value 80.
 * @return void
 */
function bbp_title_max_length( $default = 80 ) {
	echo bbp_get_title_max_length( $default );
}

/**
 * Return the maximum length of a title.
 *
 * @since 2.0.0 bbPress (r3246)
 *
 * @param int $default Optional. Default value 80.
 * @return int Maximum title length.
 */
function bbp_get_title_max_length( $default = 80 ) {
	$length = bbp_parse_int( get_option( '_bbp_title_max_length', $default ) );

	/**
	 * Filters the maximum length of a title.
	 *
	 * @since 2.0.0 bbPress (r3246)
	 *
	 * @param int $value Maximum title length.
	 */
	$length = apply_filters( 'bbp_get_title_max_length', $length );
	return bbp_parse_int( $length );
}

/**
 * Output the number of minutes a topic or reply can be edited after it's
 * published. Used by `bbp_past_edit_lock()`.
 *
 * @since 2.6.0 bbPress (r6869)
 *
 * @param int $default Optional. Default value 5.
 * @return void
 */
function bbp_edit_lock( $default = 5 ) {
	echo bbp_get_edit_lock( $default );
}

/**
 * Return the number of minutes a topic or reply can be edited after it's
 * published. Used by `bbp_past_edit_lock()`.
 *
 * @since 2.6.0 bbPress (r6869)
 *
 * @param int $default Optional. Default value 5.
 * @return int Edit window in minutes.
 */
function bbp_get_edit_lock( $default = 5 ) {
	$lock = bbp_parse_int( get_option( '_bbp_edit_lock', $default ) );

	/**
	 * Filters the number of minutes a topic or reply can be edited after it's.
	 *
	 * @since 2.6.0 bbPress (r6869)
	 *
	 * @param int $value Edit window in minutes.
	 */
	$lock = apply_filters( 'bbp_get_edit_lock', $lock );
	return bbp_parse_int( $lock );
}

/**
 * Output the group forums root parent forum id.
 *
 * @since 2.1.0 bbPress (r3575)
 *
 * @param int $default Optional. Default value.
 * @return void
 */
function bbp_group_forums_root_id( $default = 0 ) {
	echo bbp_get_group_forums_root_id( $default );
}

/**
 * Return the group forums root parent forum id.
 *
 * @since 2.1.0 bbPress (r3575)
 *
 * @param int $default Optional. Default value 0.
 * @return int The post ID for the root forum.
 */
function bbp_get_group_forums_root_id( $default = 0 ) {
	$root_id = bbp_parse_int( get_option( '_bbp_group_forums_root_id', $default ) );

	/**
	 * Filters the group forums root parent forum id.
	 *
	 * @since 2.1.0 bbPress (r3575)
	 *
	 * @param int $value The post ID for the root forum.
	 */
	$root_id = apply_filters( 'bbp_get_group_forums_root_id', $root_id );
	return bbp_parse_int( $root_id );
}

/**
 * Checks if BuddyPress Group Forums are enabled.
 *
 * @since 2.1.0 bbPress (r3575)
 *
 * @param bool|int $default Optional. Default value true.
 * @return bool Is group forums enabled or not.
 */
function bbp_is_group_forums_active( $default = 1 ) {

	/**
	 * Filters whether BuddyPress group forums are enabled.
	 *
	 * @since 2.1.0 bbPress (r3575)
	 *
	 * @param bool $value Whether BuddyPress group forums are enabled.
	 */
	return (bool) apply_filters( 'bbp_is_group_forums_active', (bool) get_option( '_bbp_enable_group_forums', $default ) );
}

/**
 * Checks if Akismet is enabled.
 *
 * @since 2.1.0 bbPress (r3575)
 *
 * @param bool|int $default Optional. Default value true.
 * @return bool Is Akismet enabled or not.
 */
function bbp_is_akismet_active( $default = 1 ) {

	/**
	 * Filters whether akismet is enabled.
	 *
	 * @since 2.1.0 bbPress (r3575)
	 *
	 * @param bool $value Whether akismet is enabled.
	 */
	return (bool) apply_filters( 'bbp_is_akismet_active', (bool) get_option( '_bbp_enable_akismet', $default ) );
}

/**
 * Integrate settings into existing WordPress pages.
 *
 * There are 3 possible modes:
 * - 'basic'   Traditional admin integration
 * - 'compact' One "bbPress" top-level admin menu
 * - 'deep'    Deeply integrate with the WordPress admin interface
 *
 * @since 2.4.0 bbPress (r4932)
 *
 * @param string $default Optional. Default value 'basic'.
 * @return string How to integrate settings.
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

	/**
	 * Filters the settings integration mode.
	 *
	 * @since 2.4.0 bbPress (r4932)
	 *
	 * @param string $integration How to integrate settings.
	 * @param string $default Default value 'basic'.
	 */
	return apply_filters( 'bbp_settings_integration', $integration, $default );
}

/**
 * Check whether an engagements strategy is valid.
 *
 * @since 2.6.20 bbPress (r8008)
 * @since 2.7.0 bbPress (r8306) Disallow engagement type classes.
 *
 * @param string $strategy Engagements strategy to check.
 * @return bool Whether the strategy class exists and is not the base class or
 *              an engagement type.
 */
function bbp_is_valid_engagements_strategy( $strategy ) {
	if ( ! is_string( $strategy ) ) {
		return false;
	}

	$class_name         = 'BBP_User_Engagements_' . ucwords( $strategy );
	$disallowed_classes = array(
		strtolower( 'BBP_User_Engagements_Base' ),
	);

	return class_exists( $class_name )
		&& ! in_array( strtolower( $class_name ), $disallowed_classes, true )
		&& ! is_a( $class_name, 'BBP_User_Engagements_Type', true );
}

/**
 * How to interact with engagements.
 *
 * There are 3 bundled strategies:
 * - 'meta' 2.6 and higher. Uses multiple postmeta keys.
 * - 'user' Pre-2.6. Uses comma-separated string of IDs in usermeta.
 * - 'term' Alternate. Uses taxonomy term relationships.
 *
 * @since 2.6.0 bbPress (r6876)
 * @since 2.6.20 bbPress (r8008) Validates stored and filtered strategy values.
 *
 * @param string $default Optional. Default value 'meta'.
 * @return string Valid engagements strategy, or 'meta' if invalid.
 */
function bbp_engagements_strategy( $default = 'meta' ) {

	// Get the option value
	$integration = get_option( '_bbp_engagements_strategy', $default );

	// Fallback to 'meta' if invalid
	if ( ! bbp_is_valid_engagements_strategy( $integration ) ) {
		$integration = 'meta';
	}

	// Filter the integration

	/**
	 * Filters the user engagement storage strategy.
	 *
	 * @since 2.6.0 bbPress (r6876)
	 *
	 * @param string $integration The user engagement storage strategy.
	 * @param string $default Default.
	 */
	$integration = apply_filters( 'bbp_engagements_strategy', $integration, $default );

	// Return a valid integration
	return bbp_is_valid_engagements_strategy( $integration )
		? $integration
		: 'meta';
}

/** Slugs *********************************************************************/

/**
 * Return the root slug.
 *
 * @since 2.1.0 bbPress (r3760)
 *
 * @param string $default Optional. Default value 'forums'.
 * @return string
 */
function bbp_get_root_slug( $default = 'forums' ) {

	/**
	 * Filters the root slug.
	 *
	 * @since 2.1.0 bbPress (r3760)
	 *
	 * @param string $value The root slug.
	 */
	return apply_filters( 'bbp_get_root_slug', get_option( '_bbp_root_slug', $default ) );
}

/**
 * Are we including the root slug in front of forum pages?
 *
 * @since 2.1.0 bbPress (r3760)
 *
 * @param bool|int $default Optional. Default value true.
 * @return bool
 */
function bbp_include_root_slug( $default = 1 ) {

	/**
	 * Filters whether to include the forum root slug.
	 *
	 * @since 2.1.0 bbPress (r3760)
	 *
	 * @param bool $value Whether to include the forum root slug.
	 */
	return (bool) apply_filters( 'bbp_include_root_slug', (bool) get_option( '_bbp_include_root', $default ) );
}

/**
 * What to show on root, forums or topics?
 *
 * @since 2.4.0 bbPress (r4932)
 *
 * @param string $default Optional. Default value 'forums'.
 * @return string
 */
function bbp_show_on_root( $default = 'forums' ) {

	/**
	 * Filters what appears at the forum root.
	 *
	 * @since 2.4.0 bbPress (r4932)
	 *
	 * @param string $value What appears at the forum root.
	 */
	return apply_filters( 'bbp_show_on_root', get_option( '_bbp_show_on_root', $default ) );
}

/**
 * Maybe return the root slug, based on whether or not it's included in the url.
 *
 * @since 2.1.0 bbPress (r3760)
 *
 * @return string Root slug when included, or an empty string.
 */
function bbp_maybe_get_root_slug() {
	$slug   = bbp_get_root_slug();
	$retval = ( ! empty( $slug ) && bbp_include_root_slug() )
		? trailingslashit( $slug )
		: '';

	/**
	 * Filters the root slug when included.
	 *
	 * @since 2.1.0 bbPress (r3760)
	 *
	 * @param string $retval Root slug when included, or an empty string.
	 */
	return apply_filters( 'bbp_maybe_get_root_slug', $retval );
}

/**
 * Return the single forum slug.
 *
 * @since 2.1.0 bbPress (r3760)
 *
 * @param string $default Optional. Default value 'forum'.
 * @return string
 */
function bbp_get_forum_slug( $default = 'forum' ) {

	/**
	 * Filters the single forum slug.
	 *
	 * @since 2.6.0 bbPress (r5304)
	 *
	 * @param string $value The single forum slug.
	 */
	return apply_filters( 'bbp_get_forum_slug', bbp_maybe_get_root_slug() . get_option( '_bbp_forum_slug', $default ) );
}

/**
 * Return the topic archive slug.
 *
 * @since 2.1.0 bbPress (r3760)
 *
 * @param string $default Optional. Default value 'topics'.
 * @return string
 */
function bbp_get_topic_archive_slug( $default = 'topics' ) {

	/**
	 * Filters the topic archive slug.
	 *
	 * @since 2.1.0 bbPress (r3760)
	 *
	 * @param string $value The topic archive slug.
	 */
	return apply_filters( 'bbp_get_topic_archive_slug', get_option( '_bbp_topic_archive_slug', $default ) );
}

/**
 * Return the reply archive slug.
 *
 * @since 2.4.0 bbPress (r4926)
 *
 * @param string $default Optional. Default value 'replies'.
 * @return string
 */
function bbp_get_reply_archive_slug( $default = 'replies' ) {

	/**
	 * Filters the reply archive slug.
	 *
	 * @since 2.4.0 bbPress (r4926)
	 *
	 * @param string $value The reply archive slug.
	 */
	return apply_filters( 'bbp_get_reply_archive_slug', get_option( '_bbp_reply_archive_slug', $default ) );
}

/**
 * Return the single topic slug.
 *
 * @since 2.1.0 bbPress (r3760)
 *
 * @param string $default Optional. Default value 'topic'.
 * @return string
 */
function bbp_get_topic_slug( $default = 'topic' ) {

	/**
	 * Filters the single topic slug.
	 *
	 * @since 2.1.0 bbPress (r3760)
	 *
	 * @param string $value The single topic slug.
	 */
	return apply_filters( 'bbp_get_topic_slug', bbp_maybe_get_root_slug() . get_option( '_bbp_topic_slug', $default ) );
}

/**
 * Return the topic-tag taxonomy slug.
 *
 * @since 2.1.0 bbPress (r3762)
 *
 * @param string $default Optional. Default value 'topic-tag'.
 * @return string
 */
function bbp_get_topic_tag_tax_slug( $default = 'topic-tag' ) {

	/**
	 * Filters the topic-tag taxonomy slug.
	 *
	 * @since 2.1.0 bbPress (r3762)
	 *
	 * @param string $value The topic-tag taxonomy slug.
	 */
	return apply_filters( 'bbp_get_topic_tag_tax_slug', bbp_maybe_get_root_slug() . get_option( '_bbp_topic_tag_slug', $default ) );
}

/**
 * Return the single reply slug (used mostly for editing).
 *
 * @since 2.1.0 bbPress (r3760)
 *
 * @param string $default Optional. Default value 'reply'.
 * @return string
 */
function bbp_get_reply_slug( $default = 'reply' ) {

	/**
	 * Filters the single reply slug (used mostly for editing).
	 *
	 * @since 2.1.0 bbPress (r3760)
	 *
	 * @param string $value The single reply slug (used mostly for editing).
	 */
	return apply_filters( 'bbp_get_reply_slug', bbp_maybe_get_root_slug() . get_option( '_bbp_reply_slug', $default ) );
}

/**
 * Return the single user slug.
 *
 * @since 2.1.0 bbPress (r3760)
 *
 * @param string $default Optional. Default value 'users'.
 * @return string
 */
function bbp_get_user_slug( $default = 'users' ) {

	/**
	 * Filters the single user slug.
	 *
	 * @since 2.1.0 bbPress (r3760)
	 *
	 * @param string $value The single user slug.
	 */
	return apply_filters( 'bbp_get_user_slug', bbp_maybe_get_root_slug() . get_option( '_bbp_user_slug', $default ) );
}

/**
 * Return the single user favorites slug.
 *
 * @since 2.2.0 bbPress (r4188)
 *
 * @param string $default Optional. Default value 'favorites'.
 * @return string
 */
function bbp_get_user_favorites_slug( $default = 'favorites' ) {

	/**
	 * Filters the single user favorites slug.
	 *
	 * @since 2.2.0 bbPress (r4188)
	 *
	 * @param string $value The single user favorites slug.
	 */
	return apply_filters( 'bbp_get_user_favorites_slug', get_option( '_bbp_user_favs_slug', $default ) );
}

/**
 * Return the single user subscriptions slug.
 *
 * @since 2.2.0 bbPress (r4188)
 *
 * @param string $default Optional. Default value 'subscriptions'.
 * @return string
 */
function bbp_get_user_subscriptions_slug( $default = 'subscriptions' ) {

	/**
	 * Filters the single user subscriptions slug.
	 *
	 * @since 2.2.0 bbPress (r4188)
	 *
	 * @param string $value The single user subscriptions slug.
	 */
	return apply_filters( 'bbp_get_user_subscriptions_slug', get_option( '_bbp_user_subs_slug', $default ) );
}

/**
 * Return the single user engagements slug.
 *
 * @since 2.6.0 bbPress (r6320)
 *
 * @param string $default Optional. Default value 'engagements'.
 * @return string
 */
function bbp_get_user_engagements_slug( $default = 'engagements' ) {

	/**
	 * Filters the single user engagements slug.
	 *
	 * @since 2.6.0 bbPress (r6320)
	 *
	 * @param string $value The single user engagements slug.
	 */
	return apply_filters( 'bbp_get_user_engagements_slug', get_option( '_bbp_user_engs_slug', $default ) );
}

/**
 * Return the user mentions slug.
 *
 * @since 2.7.0 bbPress (r8264)
 *
 * @param string $default Default slug.
 * @return string
 */
function bbp_get_user_mentions_slug( $default = 'mentions' ) {

	/**
	 * Filters the user mentions URL slug.
	 *
	 * @since 2.7.0 bbPress (r8264)
	 *
	 * @param string $value The user mentions URL slug.
	 */
	return apply_filters( 'bbp_get_user_mentions_slug', get_option( '_bbp_user_mentions_slug', $default ) );
}

/**
 * Return the topic view slug.
 *
 * @since 2.1.0 bbPress (r3760)
 *
 * @param string $default Optional. Default value 'view'.
 * @return string
 */
function bbp_get_view_slug( $default = 'view' ) {

	/**
	 * Filters the topic view slug.
	 *
	 * @since 2.1.0 bbPress (r3760)
	 *
	 * @param string $value The topic view slug.
	 */
	return apply_filters( 'bbp_get_view_slug', bbp_maybe_get_root_slug() . get_option( '_bbp_view_slug', $default ) );
}

/**
 * Return the route root slugs.
 *
 * @since 2.6.21 bbPress (r8065)
 *
 * @return array<int, string> Route root slugs.
 */
function bbp_get_route_root_slugs() {
	$slugs = array(
		bbp_get_topic_slug(),
		bbp_get_topic_tag_tax_slug(),
		bbp_get_reply_slug(),
		bbp_get_user_slug(),
		bbp_get_view_slug(),
		bbp_get_subscription_unsubscribe_slug(),
	);

	/**
	 * Filters the route root slugs.
	 *
	 * @since 2.6.21 bbPress (r8064)
	 *
	 * @param array<int, string> $slugs Route root slugs.
	 */
	return (array) apply_filters( 'bbp_get_route_root_slugs', $slugs );
}

/**
 * Return the search slug.
 *
 * @since 2.3.0 bbPress (r4579)
 *
 * @param string $default Optional. Default value 'search'.
 * @return string
 */
function bbp_get_search_slug( $default = 'search' ) {

	/**
	 * Filters the search slug.
	 *
	 * @since 2.3.0 bbPress (r4579)
	 *
	 * @param string $value The search slug.
	 */
	return apply_filters( 'bbp_get_search_slug', bbp_maybe_get_root_slug() . get_option( '_bbp_search_slug', $default ) );
}

/**
 * Return the subscription unsubscribe slug.
 *
 * @since 2.7.0 bbPress (r8349)
 *
 * @param string $default Optional. Default value 'unsubscribe'.
 * @return string
 */
function bbp_get_subscription_unsubscribe_slug( $default = 'unsubscribe' ) {

	/**
	 * Filters the subscription unsubscribe slug.
	 *
	 * @since 2.7.0 bbPress (r8349)
	 *
	 * @param string $value The subscription unsubscribe slug.
	 */
	return apply_filters( 'bbp_get_subscription_unsubscribe_slug', bbp_maybe_get_root_slug() . get_option( '_bbp_unsubscribe_slug', $default ) );
}

/**
 * Return the edit slug.
 *
 * @since 2.6.2 bbPress (r6967)
 *
 * @param string $default Optional. Default value 'edit'.
 * @return string
 */
function bbp_get_edit_slug( $default = 'edit' ) {

	/**
	 * Filters the edit slug.
	 *
	 * @since 2.6.2 bbPress (r6966)
	 *
	 * @param string $value The edit slug.
	 */
	return apply_filters( 'bbp_get_edit_slug', get_option( '_bbp_edit_slug', $default ) );
}

/** Legacy ********************************************************************/

/**
 * Checks if there is a previous BuddyPress Forum configuration.
 *
 * @since 2.1.0 bbPress (r3791)
 *
 * @param string $default Optional. Default empty string.
 * @return string The location of the bb-config.php file, if any.
 */
function bbp_get_config_location( $default = '' ) {

	/**
	 * Filters whether there is a previous BuddyPress Forum configuration.
	 *
	 * @since 2.1.0 bbPress (r3791)
	 *
	 * @param string $value The location of the bb-config.php file, if any.
	 */
	return apply_filters( 'bbp_get_config_location', get_option( 'bb-config-location', $default ) );
}

/**
 * Read stored option data without filters or caches, retaining read failures.
 *
 * @since 2.7.0 bbPress (r8315)
 * @access private
 *
 * @param string $option  Option name.
 * @param mixed  $default Default when the row is absent.
 * @return array{bool, mixed, bool} Read success, decoded value and row existence.
 */
function bbp_get_raw_option( $option, $default = false ) {
	$db  = bbp_db();
	$row = $db->get_row( $db->prepare( "SELECT `option_value` FROM `{$db->options}` WHERE `option_name` = %s LIMIT 1", $option ) );
	if ( ! empty( $db->last_error ) ) {
		return array( false, $default, false );
	}
	return array( true, $row ? maybe_unserialize( $row->option_value ) : $default, is_object( $row ) );
}

/**
 * Claim a short-lived, owner-checked option lock shared by core and admin tools.
 *
 * @since 2.7.0 bbPress (r8315)
 * @access private
 *
 * @param string $name Lock option name.
 * @return array{name: string, value: string}|false Lock ownership, or false when unavailable.
 */
function bbp_acquire_option_lock( $name ) {
	global $wpdb;

	if ( empty( $name ) ) {
		return false;
	}

	$now   = time();
	$value = $now . '|' . wp_generate_uuid4();

	foreach ( array( 0, 1 ) as $attempt ) {
		/*
		 * Match WordPress Core locks. Transients cannot atomically claim a key
		 * and may use a non-database object-cache backend between requests.
		 */
		$created = $wpdb->query(
			$wpdb->prepare(
				"INSERT IGNORE INTO `{$wpdb->options}` (`option_name`, `option_value`, `autoload`) VALUES (%s, %s, 'no') /* LOCK */",
				$name,
				$value
			)
		);

		if ( 1 === $created ) {
			wp_cache_delete( $name, 'options' );
			wp_cache_delete( 'notoptions', 'options' );

			return array(
				'name'  => $name,
				'value' => $value
			);
		}

		$stored = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT `option_value` FROM `{$wpdb->options}` WHERE `option_name` = %s LIMIT 1",
				$name
			)
		);

		// Only the first attempt may replace the exact stale value it observed
		if ( ( 0 !== $attempt ) || is_null( $stored ) || ( (int) $stored >= ( $now - ( 5 * MINUTE_IN_SECONDS ) ) ) ) {
			break;
		}

		bbp_release_option_lock(
			array(
				'name'  => $name,
				'value' => $stored
			)
		);
	}

	return false;
}

/**
 * Refresh an option lock while preserving its exact owner token.
 *
 * @since 2.7.0 bbPress (r8359)
 * @access private
 *
 * @param array{name: string, value: string} $lock Lock ownership, updated with the refreshed value.
 * @return bool Whether this caller still owns the lock.
 */
function bbp_refresh_option_lock( &$lock ) {
	global $wpdb;

	if ( empty( $lock['name'] ) || empty( $lock['value'] ) ) {
		return false;
	}
	$parts = explode( '|', $lock['value'], 2 );
	if ( 2 !== count( $parts ) || empty( $parts[1] ) ) {
		return false;
	}
	$value = time() . '|' . $parts[1];
	if ( $value === $lock['value'] ) {
		$stored = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT `option_value` FROM `{$wpdb->options}` WHERE `option_name` = %s LIMIT 1",
				$lock['name']
			)
		);
		return empty( $wpdb->last_error ) && $stored === $lock['value'];
	}
	$updated = $wpdb->query(
		$wpdb->prepare(
			"UPDATE `{$wpdb->options}` SET `option_value` = %s WHERE `option_name` = %s AND `option_value` = %s /* LOCK */",
			$value,
			$lock['name'],
			$lock['value']
		)
	);
	if ( 1 !== $updated ) {
		return false;
	}

	$lock['value'] = $value;
	wp_cache_delete( $lock['name'], 'options' );
	wp_cache_delete( 'notoptions', 'options' );

	return true;
}

/**
 * Release only the exact option-lock owner, including expired owners.
 *
 * @since 2.7.0 bbPress (r8315)
 * @access private
 *
 * @param array{name?: string, value?: string} $lock Lock ownership.
 * @return void
 */
function bbp_release_option_lock( $lock = array() ) {
	global $wpdb;

	if ( ! empty( $lock['name'] ) && ! empty( $lock['value'] ) ) {
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM `{$wpdb->options}` WHERE `option_name` = %s AND `option_value` = %s",
				$lock['name'],
				$lock['value']
			)
		);
		wp_cache_delete( $lock['name'], 'options' );
		wp_cache_delete( 'notoptions', 'options' );
	}
}
