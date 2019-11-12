<?php

/**
 * bbPress Updater
 *
 * @package bbPress
 * @subpackage Core
 */

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

/**
 * If there is no raw DB version, this is the first installation
 *
 * @since 2.1.0 bbPress (r3764)
 *
 * @return bool True if update, False if not
 */
function bbp_is_install() {
	return ! bbp_get_db_version_raw();
}

/**
 * Compare the bbPress version to the DB version to determine if updating
 *
 * @since 2.0.0 bbPress (r3421)
 *
 * @return bool True if update, False if not
 */
function bbp_is_update() {
	$raw    = (int) bbp_get_db_version_raw();
	$cur    = (int) bbp_get_db_version();
	$retval = (bool) ( $raw < $cur );
	return $retval;
}

/**
 * Determine if bbPress is being activated
 *
 * Note that this function currently is not used in bbPress core and is here
 * for third party plugins to use to check for bbPress activation.
 *
 * @since 2.0.0 bbPress (r3421)
 *
 * @return bool True if activating bbPress, false if not
 */
function bbp_is_activation( $basename = '' ) {
	global $pagenow;

	$bbp    = bbpress();
	$action = false;

	// Bail if not in admin/plugins
	if ( ! ( is_admin() && ( 'plugins.php' === $pagenow ) ) ) {
		return false;
	}

	if ( ! empty( $_REQUEST['action'] ) && ( '-1' !== $_REQUEST['action'] ) ) {
		$action = $_REQUEST['action'];
	} elseif ( ! empty( $_REQUEST['action2'] ) && ( '-1' !== $_REQUEST['action2'] ) ) {
		$action = $_REQUEST['action2'];
	}

	// Bail if not activating
	if ( empty( $action ) || ! in_array( $action, array( 'activate', 'activate-selected', true ) ) ) {
		return false;
	}

	// The plugin(s) being activated
	if ( $action === 'activate' ) {
		$plugins = isset( $_GET['plugin'] ) ? array( $_GET['plugin'] ) : array();
	} else {
		$plugins = isset( $_POST['checked'] ) ? (array) $_POST['checked'] : array();
	}

	// Set basename if empty
	if ( empty( $basename ) && ! empty( $bbp->basename ) ) {
		$basename = $bbp->basename;
	}

	// Bail if no basename
	if ( empty( $basename ) ) {
		return false;
	}

	// Is bbPress being activated?
	return in_array( $basename, $plugins, true );
}

/**
 * Determine if bbPress is being deactivated
 *
 * @since 2.0.0 bbPress (r3421)
 *
 * @return bool True if deactivating bbPress, false if not
 */
function bbp_is_deactivation( $basename = '' ) {
	global $pagenow;

	$bbp    = bbpress();
	$action = false;

	// Bail if not in admin/plugins
	if ( ! ( is_admin() && ( 'plugins.php' === $pagenow ) ) ) {
		return false;
	}

	if ( ! empty( $_REQUEST['action'] ) && ( '-1' !== $_REQUEST['action'] ) ) {
		$action = $_REQUEST['action'];
	} elseif ( ! empty( $_REQUEST['action2'] ) && ( '-1' !== $_REQUEST['action2'] ) ) {
		$action = $_REQUEST['action2'];
	}

	// Bail if not deactivating
	if ( empty( $action ) || ! in_array( $action, array( 'deactivate', 'deactivate-selected' ), true ) ) {
		return false;
	}

	// The plugin(s) being deactivated
	if ( $action === 'deactivate' ) {
		$plugins = isset( $_GET['plugin'] ) ? array( $_GET['plugin'] ) : array();
	} else {
		$plugins = isset( $_POST['checked'] ) ? (array) $_POST['checked'] : array();
	}

	// Set basename if empty
	if ( empty( $basename ) && ! empty( $bbp->basename ) ) {
		$basename = $bbp->basename;
	}

	// Bail if no basename
	if ( empty( $basename ) ) {
		return false;
	}

	// Is bbPress being deactivated?
	return in_array( $basename, $plugins, true );
}

/**
 * Update the DB to the latest version
 *
 * @since 2.0.0 bbPress (r3421)
 */
function bbp_version_bump() {
	update_option( '_bbp_db_version', bbp_get_db_version() );
}

/**
 * Setup the bbPress updater
 *
 * @since 2.0.0 bbPress (r3419)
 */
function bbp_setup_updater() {

	// Bail if no update needed
	if ( ! bbp_is_update() ) {
		return;
	}

	// Call the automated updater
	bbp_version_updater();
}

/**
 * Runs when a new site is created in a multisite network, and bbPress is active
 * on that site (hooked to `bbp_new_site`)
 *
 * @since 2.6.0 bbPress (r6779)
 */
function bbp_setup_new_site( $site_id = 0 ) {

	// Look for initial content
	$created = is_multisite()
		? get_blog_option( $site_id, '_bbp_flag_initial_content', false )
		: get_option( '_bbp_flag_initial_content', false );

	// Maybe create the initial content
	if ( ! empty( $created ) ) {
		bbp_create_initial_content();

		// Flag initial content as created
		is_multisite()
			? update_blog_option( $site_id, '_bbp_flag_initial_content', true )
			: update_option( '_bbp_flag_initial_content', true );
	}
}

/**
 * Create a default forum, topic, and reply
 *
 * @since 2.1.0 bbPress (r3767)
 *
 * @param array $args Array of arguments to override default values
 */
function bbp_create_initial_content( $args = array() ) {

	// Cannot use bbp_get_current_user_id() during activation process
	$user_id = get_current_user_id();

	// Parse arguments against default values
	$r = bbp_parse_args( $args, array(
		'forum_author'  => $user_id,
		'forum_parent'  => 0,
		'forum_status'  => 'publish',
		'forum_title'   => esc_html__( 'General',            'bbpress' ),
		'forum_content' => esc_html__( 'General Discussion', 'bbpress' ),

		'topic_author'  => $user_id,
		'topic_title'   => esc_html__( 'Hello World!',                                  'bbpress' ),
		'topic_content' => esc_html__( 'This is the very first topic in these forums.', 'bbpress' ),

		'reply_author'  => $user_id,
		'reply_content' => esc_html__( 'And this is the very first reply.', 'bbpress' ),
	), 'create_initial_content' );

	// Use the same time for each post
	$current_time = time();
	$forum_time   = date( 'Y-m-d H:i:s', $current_time - 60 * 60 * 80 );
	$topic_time   = date( 'Y-m-d H:i:s', $current_time - 60 * 60 * 60 );
	$reply_time   = date( 'Y-m-d H:i:s', $current_time - 60 * 60 * 40 );

	// Create the initial forum
	$forum_id = bbp_insert_forum( array(
		'post_author'  => $r['forum_author'],
		'post_parent'  => $r['forum_parent'],
		'post_status'  => $r['forum_status'],
		'post_title'   => $r['forum_title'],
		'post_content' => $r['forum_content'],
		'post_date'    => $forum_time
	) );

	// Create the initial topic
	$topic_id = bbp_insert_topic(
		array(
			'post_author'  => $r['topic_author'],
			'post_parent'  => $forum_id,
			'post_title'   => $r['topic_title'],
			'post_content' => $r['topic_content'],
			'post_date'    => $topic_time,
		),
		array(
			'forum_id'     => $forum_id
		)
	);

	// Create the initial reply
	$reply_id = bbp_insert_reply(
		array(
			'post_author'  => $r['reply_author'],
			'post_parent'  => $topic_id,
			'post_content' => $r['reply_content'],
			'post_date'    => $reply_time
		),
		array(
			'forum_id'     => $forum_id,
			'topic_id'     => $topic_id
		)
	);

	return array(
		'forum_id' => $forum_id,
		'topic_id' => $topic_id,
		'reply_id' => $reply_id
	);
}

/**
 * The version updater looks at what the current database version is, and
 * runs whatever other code is needed.
 *
 * This is most-often used when the data schema changes, but should also be used
 * to correct issues with bbPress meta-data silently on software update.
 *
 * @since 2.2.0 bbPress (r4104)
 */
function bbp_version_updater() {

	// Get the raw database version
	$raw_db_version = (int) bbp_get_db_version_raw();

	// Only run updater if previous installation exists
	if ( ! empty( $raw_db_version ) ) {

		/** 2.0 Branch ********************************************************/

		// 2.0, 2.0.1, 2.0.2, 2.0.3
		if ( $raw_db_version < 200 ) {
			// No changes
		}

		/** 2.1 Branch ********************************************************/

		// 2.1, 2.1.1
		if ( $raw_db_version < 211 ) {

			/**
			 * Repair private and hidden forum data
			 *
			 * @link https://bbpress.trac.wordpress.org/ticket/1891
			 */
			bbp_admin_repair_forum_visibility();
		}

		/** 2.2 Branch ********************************************************/

		// 2.2.x
		if ( $raw_db_version < 220 ) {

			// Remove any old bbPress roles
			bbp_remove_roles();

			// Remove capabilities
			bbp_remove_caps();
		}

		/** 2.3 Branch ********************************************************/

		// 2.3.x
		if ( $raw_db_version < 230 ) {
			// No changes
		}

		/** 2.4 Branch ********************************************************/

		// 2.4.x
		if ( $raw_db_version < 240 ) {
			// No changes
		}

		/** 2.5 Branch ********************************************************/

		// 2.5.x
		if ( $raw_db_version < 250 ) {
			// No changes
		}

		/** 2.6 Branch ********************************************************/

		// Smaller installs run the upgrades directly
		if ( ! bbp_is_large_install() ) {

			/**
			 * Upgrade user favorites and subscriptions
			 */
			if ( $raw_db_version < 261 ) {
				bbp_admin_upgrade_user_favorites();
				bbp_admin_upgrade_user_topic_subscriptions();
				bbp_admin_upgrade_user_forum_subscriptions();
			}

			/**
			 * Upgrade user engagements
			 */
			if ( $raw_db_version < 262 ) {
				bbp_admin_upgrade_user_engagements();
			}

			/**
			 * Repair forum hidden reply count
			 */
			if ( $raw_db_version < 263 ) {
				bbp_admin_repair_forum_hidden_reply_count();
			}

		// Large installs require manual intervention
		} else {

			/**
			 * Upgrade user favorites and subscriptions
			 */
			if ( $raw_db_version < 261 ) {
				bbp_add_pending_upgrade( 'bbp-user-favorites-move' );
				bbp_add_pending_upgrade( 'bbp-user-topic-subscriptions-move' );
				bbp_add_pending_upgrade( 'bbp-user-forum-subscriptions-move' );

				// Set strategy to pre-2.6 on large network
				update_option( '_bbp_engagements_strategy', 'user' );
			}

			/**
			 * Upgrade user engagements
			 */
			if ( $raw_db_version < 262 ) {
				bbp_add_pending_upgrade( 'bbp-user-topic-engagements-move' );

				// Set strategy to pre-2.6 on large network
				update_option( '_bbp_engagements_strategy', 'user' );
			}

			/**
			 * Upgrade user engagements
			 */
			if ( $raw_db_version < 263 ) {
				bbp_add_pending_upgrade( 'bbp-forum-hidden-replies' );
			}
		}
	}

	/** All done! *************************************************************/

	// Bump the version
	bbp_version_bump();

	// Delete rewrite rules to force a flush
	bbp_delete_rewrite_rules();
}

/**
 * Redirect user to the "What's New" page on activation
 *
 * @since 2.2.0 bbPress (r4389)
 *
 * @internal Used internally to redirect bbPress to the about page on activation
 *
 * @return If network admin or bulk activation
 */
function bbp_add_activation_redirect() {

	// Bail if activating from network, or bulk
	if ( is_network_admin() || isset( $_GET['activate-multi'] ) ) {
		return;
	}

	// Add the redirect trigger
	update_user_option( get_current_user_id(), '_bbp_activation_redirect', true );
}

/**
 * Redirect user to "What's New" page on activation
 *
 * @since 2.2.0 bbPress (r4389)
 *
 * @internal Used internally to redirect bbPress to the about page on activation
 *
 * @return If no transient, or in network admin, or is bulk activation
 */
function bbp_do_activation_redirect() {

	// Bail if no redirect trigger
	if ( ! get_user_option( '_bbp_activation_redirect' ) ) {
		return;
	}

	// Delete the redirect trigger
	delete_user_option( get_current_user_id(), '_bbp_activation_redirect' );

	// Bail if activating from network, or bulk
	if ( is_network_admin() || isset( $_GET['activate-multi'] ) ) {
		return;
	}

	// Bail if the current user cannot see the about page
	if ( ! current_user_can( 'bbp_about_page' ) ) {
		return;
	}

	// Redirect to bbPress about page
	bbp_redirect( add_query_arg( array( 'page' => 'bbp-about' ), admin_url( 'index.php' ) ) );
}

/**
 * Hooked to the 'bbp_activate' action, this helper function automatically makes
 * the current user a Key Master in the forums if they just activated bbPress,
 * regardless of the bbp_allow_global_access() setting.
 *
 * @since 2.4.0 bbPress (r4910)
 *
 * @internal Used to internally make the current user a keymaster on activation
 *
 * @return If user can't activate plugins or is already a keymaster
 */
function bbp_make_current_user_keymaster() {

	// Catch all, to prevent premature user initialization
	if ( ! did_action( 'set_current_user' ) ) {
		return;
	}

	// Bail if not logged in or already a member of this site
	if ( ! is_user_logged_in() ) {
		return;
	}

	// Bail if the current user can't activate plugins since previous pageload
	if ( ! current_user_can( 'activate_plugins' ) ) {
		return;
	}

	// Cannot use bbp_get_current_user_id() during activation process
	$user_id = get_current_user_id();

	// Get the current blog ID, to know if they should be promoted here
	$blog_id = get_current_blog_id();

	// Bail if user is not actually a member of this site
	if ( ! is_user_member_of_blog( $user_id, $blog_id ) ) {
		return;
	}

	// Bail if the current user already has a forum role to prevent
	// unexpected role and capability escalation.
	if ( bbp_get_user_role( $user_id ) ) {
		return;
	}

	// Make the current user a keymaster
	bbp_set_user_role( $user_id, bbp_get_keymaster_role() );

	// Reload the current user so caps apply immediately
	wp_get_current_user();
}

/** Pending Upgrades **********************************************************/

/**
 * Return the number of pending upgrades
 *
 * @since 2.6.0 bbPress (r6895)
 *
 * @param string $type Type of pending upgrades (upgrade|repair|empty)
 *
 * @return int
 */
function bbp_get_pending_upgrade_count( $type = '' ) {
	return count( (array) bbp_get_pending_upgrades( $type ) );
}

/**
 * Return an array of pending upgrades
 *
 * @since 2.6.0 bbPress (r6895)
 *
 * @param string $type Type of pending upgrades (upgrade|repair|empty)
 *
 * @return array
 */
function bbp_get_pending_upgrades( $type = '' ) {

	// Get the pending upgrades
	$retval = (array) get_option( '_bbp_db_pending_upgrades', array() );

	// Looking for a specific type?
	if ( ! empty( $type ) ) {
		$tools   = bbp_get_admin_repair_tools( $type );
		$plucked = array_keys( wp_list_pluck( $tools, 'type' ) );
		$retval  = array_intersect( $retval, $plucked );
	}

	return (array) $retval;
}

/**
 * Add an upgrade ID to pending upgrades array
 *
 * @since 2.6.0 bbPress (r6895)
 *
 * @param string $upgrade_id
 */
function bbp_add_pending_upgrade( $upgrade_id = '' ) {

	// Get the pending upgrades option
	$pending = bbp_get_pending_upgrades();

	// Maybe add upgrade ID to end of pending array
	if ( false === array_search( $upgrade_id, $pending, true ) ) {
		array_push( $pending, $upgrade_id );
	}

	// Update and return
	return update_option( '_bbp_db_pending_upgrades', $pending );
}

/**
 * Add an upgrade ID to pending upgrades array
 *
 * @since 2.6.0 bbPress (r6895)
 *
 * @param string $upgrade_id
 */
function bbp_remove_pending_upgrade( $upgrade_id = '' ) {

	// Get the pending upgrades option
	$pending = bbp_get_pending_upgrades();

	// Look for this upgrade ID
	$index = array_search( $upgrade_id, $pending, true );

	// Maybe remove upgrade ID from pending array
	if ( false !== $index ) {
		unset( $pending[ $index ] );
	}

	// Update and return
	return update_option( '_bbp_db_pending_upgrades', $pending );
}

/**
 * Delete all pending upgrades
 *
 * @since 2.6.0 bbPress (r6895)
 */
function bbp_clear_pending_upgrades() {
	return delete_option( '_bbp_db_pending_upgrades' );
}

/**
 * Maybe append an upgrade count to a string
 *
 * @since 2.6.0 bbPress (r6896)
 *
 * @param string $string Text to append count to
 * @param string $type   Type of pending upgrades (upgrade|repair|empty)
 *
 * @return string
 */
function bbp_maybe_append_pending_upgrade_count( $string = '', $type = '' ) {

	// Look for an upgrade count
	$count = bbp_get_pending_upgrade_count( $type );

	// Append the count to the string
	if ( ! empty( $count ) ) {
		$suffix = ' <span class="awaiting-mod count-' . absint( $count ) . '"><span class="pending-count">' . bbp_number_format( $count ) . '</span></span>';
		$string = "{$string}{$suffix}";
	}

	// Return the string, maybe with a count
	return $string;
}
