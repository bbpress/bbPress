<?php

/**
 * bbPress User Capabilites
 *
 * Used to map user capabilities to WordPress's existing capabilities.
 *
 * @package bbPress
 * @subpackage Capabilities
 */

/**
 * Maps primary capabilities
 *
 * @since bbPress (r4242)
 *
 * @param array $caps Capabilities for meta capability
 * @param string $cap Capability name
 * @param int $user_id User id
 * @param mixed $args Arguments
 * @uses apply_filters() Filter mapped results
 * @return array Actual capabilities for meta capability
 */
function bbp_map_primary_meta_caps( $caps, $cap, $user_id, $args ) {

	// What capability is being checked?
	switch ( $cap ) {
		case 'spectate'    :
		case 'participate' :
		case 'moderate'    :

			// Do not allow inactive users
			if ( bbp_is_user_inactive( $user_id ) ) {
				$caps = array( 'do_not_allow' );

			// Moderators are always participants
			} else {
				$caps = array( $cap );
			}

			break;
	}

	return apply_filters( 'bbp_map_primary_meta_caps', $caps, $cap, $user_id, $args );
}

/**
 * Return a user's main role
 *
 * @since bbPress (r3860)
 *
 * @param int $user_id
 * @uses bbp_get_user_id() To get the user id
 * @uses get_userdata() To get the user data
 * @uses apply_filters() Calls 'bbp_set_user_role' with the role and user id
 * @return string
 */
function bbp_set_user_role( $user_id = 0, $new_role = '' ) {

	// Validate user id
	$user_id = bbp_get_user_id( $user_id, false, false );
	$user    = get_userdata( $user_id );

	// User exists
	if ( !empty( $user ) ) {

		// Get users forum role
		$role = bbp_get_user_role( $user_id );

		// User already has this role so no new role is set
		if ( $new_role == $role ) {
			$new_role = false;

		// Users role is different than the new role
		} else {

			// Remove the old role
			if ( ! empty( $role ) ) {
				$user->remove_role( $role );
			}

			// Add the new role
			$user->add_role( $new_role );
		}

	// User does don exist so return false
	} else {
		$new_role = false;
	}

	return apply_filters( 'bbp_set_user_role', $new_role, $user_id, $user );
}

/**
 * Return a user's main role
 *
 * @since bbPress (r3860)
 *
 * @param int $user_id
 * @uses bbp_get_user_id() To get the user id
 * @uses get_userdata() To get the user data
 * @uses apply_filters() Calls 'bbp_get_user_role' with the role and user id
 * @return string
 */
function bbp_get_user_role( $user_id = 0 ) {

	// Validate user id
	$user_id = bbp_get_user_id( $user_id, false, false );
	$user    = get_userdata( $user_id );
	$role    = false;

	// User has roles so lets
	if ( ! empty( $user->roles ) ) {
		$roles = array_intersect( array_values( $user->roles ), array_keys( bbp_get_editable_roles() ) );

		// If there's a role in the array, use the first one
		if ( !empty( $roles ) ) {
			$role = array_shift( array_values( $roles ) );
		}
	}

	return apply_filters( 'bbp_get_user_role', $role, $user_id, $user );
}

/**
 * Helper function hooked to 'bbp_edit_user_profile_update' action to save or
 * update user roles and capabilities.
 *
 * @since bbPress (r4235)
 *
 * @param int $user_id
 * @uses bbp_reset_user_caps() to reset caps
 * @usse bbp_save_user_caps() to save caps
 */
function bbp_profile_update_role( $user_id = 0 ) {

	// Bail if no user ID was passed
	if ( empty( $user_id ) )
		return;

	// Bail if no role
	if ( ! isset( $_POST['bbp-forums-role'] ) )
		return;

	// Fromus role we want the user to have
	$new_role    = sanitize_text_field( $_POST['bbp-forums-role'] );
	$forums_role = bbp_get_user_role( $user_id );

	// Set the new forums role
	if ( $new_role != $forums_role ) {
		bbp_set_user_role( $user_id, $new_role );
	}
}

/**
 * Add the default role to the current user if needed
 *
 * This function will bail if the forum is not global in a multisite
 * installation of WordPress, or if the user is marked as spam or deleted.
 *
 * @since bbPress (r3380)
 *
 * @uses bbp_allow_global_access()
 * @uses bbp_is_user_inactive()
 * @uses is_user_logged_in()
 * @uses is_user_member_of_blog()
 * @uses get_option()
 *
 * @return If not multisite, not global, or user is deleted/spammed
 */
function bbp_set_current_user_default_role() {

	/** Sanity ****************************************************************/

	// Bail if not logged in or already a member of this site
	if ( ! is_user_logged_in() )
		return;

	// Get the current user ID
	$user_id = bbp_get_current_user_id();

	// Bail if user already has a forums role
	if ( bbp_get_user_role( $user_id ) )
		return;

	// Bail if user is marked as spam or is deleted
	if ( bbp_is_user_inactive( $user_id ) )
		return;

	/** Ready *****************************************************************/

	// Load up bbPress once
	$bbp         = bbpress();

	// Get whether or not to add a role to the user account
	$add_to_site = bbp_allow_global_access();

	// Get the current user's WordPress role. Set to empty string if none found.
	$user_role   = isset( $bbp->current_user->roles ) ? array_shift( $bbp->current_user->roles ) : '';

	// Loop through the role map, and grant the proper bbPress role
	foreach ( (array) bbp_get_user_role_map() as $wp_role => $bbp_role ) {

		// User's role matches a possible WordPress role (including none at all)
		if ( $user_role == $wp_role ) {

			// Add role to user account, making them a user of this site
			if ( true == $add_to_site ) {
				$bbp->current_user->add_role( $bbp_role );

			// Dynamically assign capabilities, making them "anonymous"
			} else {
				$bbp->current_user->caps[$bbp_role] = true;
				$bbp->current_user->get_role_caps();
			}

			break;
		}
	}
}

/**
 * Return a map of WordPress roles to bbPress roles. Used to automatically grant
 * appropriate bbPress roles to WordPress users that wouldn't already have a
 * role in the forums. Also guarantees WordPress admins get the Keymaster role.
 *
 * @since bbPress (r4334)
 *
 * @return array Filtered array of WordPress roles to bbPress roles
 */
function bbp_get_user_role_map() {

	// Get the default role once here
	$default_role = bbp_get_default_role();

	// Return filtered results, forcing admins to keymasters.
	return (array) apply_filters( 'bbp_get_user_role_map', array (
		'administrator' => bbp_get_keymaster_role(),
		'editor'        => $default_role,
		'author'        => $default_role,
		'contributor'   => $default_role,
		'subscriber'    => $default_role,
		''              => bbp_get_anonymous_role()
	) );
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

		if ( 1 == $user->user_status )
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
 * @uses get_current_blog_id()
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
	$post_types  = array( bbp_get_topic_post_type(), bbp_get_reply_post_type() );
	$post_types  = "'" . implode( "', '", $post_types ) . "'";
	$status      = bbp_get_public_status_id();

	// Loop through blogs and remove their posts
	foreach ( (array) array_keys( $blogs ) as $blog_id ) {

		// Switch to the blog ID
		switch_to_blog( $blog_id );

		// Get topics and replies
		$posts = $wpdb->get_col( "SELECT ID FROM {$wpdb->posts} WHERE post_author = {$user_id} AND post_status = '{$status}' AND post_type IN ({$post_types})" );

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
	global $wpdb;

	// Get the blog IDs of the user to mark as spam
	$blogs = get_blogs_of_user( $user_id, true );

	// If user has no blogs, they are a guest on this site
	if ( empty( $blogs ) )
		$blogs[$wpdb->blogid] = array();

	// Make array of post types to mark as spam
	$post_types = array( bbp_get_topic_post_type(), bbp_get_reply_post_type() );
	$post_types = "'" . implode( "', '", $post_types ) . "'";
	$status     = bbp_get_spam_status_id();

	// Loop through blogs and remove their posts
	foreach ( (array) array_keys( $blogs ) as $blog_id ) {

		// Switch to the blog ID
		switch_to_blog( $blog_id );

		// Get topics and replies
		$posts = $wpdb->get_col( "SELECT ID FROM {$wpdb->posts} WHERE post_author = {$user_id} AND post_status = '{$status}' AND post_type IN ({$post_types})" );

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

	// Default to current user
	if ( empty( $user_id ) && is_user_logged_in() )
		$user_id = bbp_get_current_user_id();

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

		if ( 2 == $user->user_status )
			$is_deleted = true;

	}

	return apply_filters( 'bp_core_is_user_deleted', (bool) $is_deleted );
}

/**
 * Checks if user is active
 *
 * @since bbPress (r3502)
 *
 * @uses is_user_logged_in() To check if user is logged in
 * @uses bbp_get_displayed_user_id() To get current user ID
 * @uses bbp_is_user_spammer() To check if user is spammer
 * @uses bbp_is_user_deleted() To check if user is deleted
 *
 * @param int $user_id The user ID to check
 * @return bool True if public, false if not
 */
function bbp_is_user_active( $user_id = 0 ) {

	// Default to current user
	if ( empty( $user_id ) && is_user_logged_in() )
		$user_id = bbp_get_current_user_id();

	// No user to check
	if ( empty( $user_id ) )
		return false;

	// Check spam
	if ( bbp_is_user_spammer( $user_id ) )
		return false;

	// Check deleted
	if ( bbp_is_user_deleted( $user_id ) )
		return false;

	// Assume true if not spam or deleted
	return true;
}

/**
 * Checks if user is not active.
 *
 * @since bbPress (r3502)
 *
 * @uses is_user_logged_in() To check if user is logged in
 * @uses bbp_get_displayed_user_id() To get current user ID
 * @uses bbp_is_user_active() To check if user is active
 *
 * @param int $user_id The user ID to check. Defaults to current user ID
 * @return bool True if inactive, false if active
 */
function bbp_is_user_inactive( $user_id = 0 ) {

	// Default to current user
	if ( empty( $user_id ) && is_user_logged_in() )
		$user_id = bbp_get_current_user_id();

	// No user to check
	if ( empty( $user_id ) )
		return false;

	// Return the inverse of active
	return !bbp_is_user_active( $user_id );
}
