<?php

/**
 * bbPress Capabilities.
 *
 * The functions in this file are used primarily as convenient wrappers for
 * capability output in user profiles. This includes mapping capabilities and
 * groups to human readable strings,
 *
 * @package bbPress
 * @subpackage Capabilities
 */

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

/** Mapping *******************************************************************/

/**
 * Returns an array of capabilities based on the role that is being requested.
 *
 * @since 2.0.0 bbPress (r2994)
 *
 * @todo Map all of these and deprecate.
 *
 * @param string $role Optional. Defaults to The role to load caps for.
 *
 * @return array<string, bool> Capabilities for $role.
 */
function bbp_get_caps_for_role( $role = '' ) {

	// Which role are we looking for?
	switch ( $role ) {

		// Keymaster
		case bbp_get_keymaster_role() :
			$caps = array(

				// Keymasters only
				'keep_gate'             => true,

				// Primary caps
				'spectate'              => true,
				'participate'           => true,
				'moderate'              => true,
				'throttle'              => true,
				'view_trash'            => true,
				'assign_moderators'     => true,

				// Forum caps
				'publish_forums'        => true,
				'edit_forums'           => true,
				'edit_others_forums'    => true,
				'delete_forums'         => true,
				'delete_others_forums'  => true,
				'read_private_forums'   => true,
				'read_hidden_forums'    => true,

				// Topic caps
				'publish_topics'        => true,
				'edit_topics'           => true,
				'edit_others_topics'    => true,
				'delete_topics'         => true,
				'delete_others_topics'  => true,
				'read_private_topics'   => true,

				// Reply caps
				'publish_replies'       => true,
				'edit_replies'          => true,
				'edit_others_replies'   => true,
				'delete_replies'        => true,
				'delete_others_replies' => true,
				'read_private_replies'  => true,

				// Topic tag caps
				'manage_topic_tags'     => true,
				'edit_topic_tags'       => true,
				'delete_topic_tags'     => true,
				'assign_topic_tags'     => true
			);

			break;

		// Moderator
		case bbp_get_moderator_role() :
			$caps = array(

				// Primary caps
				'spectate'              => true,
				'participate'           => true,
				'moderate'              => true,
				'throttle'              => true,
				'view_trash'            => true,
				'assign_moderators'     => true,

				// Forum caps
				'publish_forums'        => true,
				'edit_forums'           => true,
				'read_private_forums'   => true,
				'read_hidden_forums'    => true,

				// Topic caps
				'publish_topics'        => true,
				'edit_topics'           => true,
				'edit_others_topics'    => true,
				'delete_topics'         => true,
				'delete_others_topics'  => true,
				'read_private_topics'   => true,

				// Reply caps
				'publish_replies'       => true,
				'edit_replies'          => true,
				'edit_others_replies'   => true,
				'delete_replies'        => true,
				'delete_others_replies' => true,
				'read_private_replies'  => true,

				// Topic tag caps
				'manage_topic_tags'     => true,
				'edit_topic_tags'       => true,
				'delete_topic_tags'     => true,
				'assign_topic_tags'     => true,
			);

			break;

		// Spectators can only read
		case bbp_get_spectator_role()   :
			$caps = array(

				// Primary caps
				'spectate'              => true,
			);

			break;

		// Explicitly blocked
		case bbp_get_blocked_role() :
			$caps = array(

				// Primary caps
				'spectate'              => false,
				'participate'           => false,
				'moderate'              => false,
				'throttle'              => false,
				'view_trash'            => false,

				// Forum caps
				'publish_forums'        => false,
				'edit_forums'           => false,
				'edit_others_forums'    => false,
				'delete_forums'         => false,
				'delete_others_forums'  => false,
				'read_private_forums'   => false,
				'read_hidden_forums'    => false,

				// Topic caps
				'publish_topics'        => false,
				'edit_topics'           => false,
				'edit_others_topics'    => false,
				'delete_topics'         => false,
				'delete_others_topics'  => false,
				'read_private_topics'   => false,

				// Reply caps
				'publish_replies'       => false,
				'edit_replies'          => false,
				'edit_others_replies'   => false,
				'delete_replies'        => false,
				'delete_others_replies' => false,
				'read_private_replies'  => false,

				// Topic tag caps
				'manage_topic_tags'     => false,
				'edit_topic_tags'       => false,
				'delete_topic_tags'     => false,
				'assign_topic_tags'     => false,
			);

			break;

		// Participant/Default
		case bbp_get_participant_role() :
		default :
			$caps = array(

				// Primary caps
				'spectate'              => true,
				'participate'           => true,

				// Forum caps
				'read_private_forums'   => true,

				// Topic caps
				'publish_topics'        => true,
				'edit_topics'           => true,

				// Reply caps
				'publish_replies'       => true,
				'edit_replies'          => true,

				// Topic tag caps
				'assign_topic_tags'     => true,
			);

			break;
	}

	/**
	 * Filters the capabilities assigned to a forum role.
	 *
	 * @since 2.0.0 bbPress (r2994)
	 *
	 * @param array<string, bool> $caps Capabilities for $role.
	 * @param string $role Defaults to The role to load caps for.
	 */
	return apply_filters( 'bbp_get_caps_for_role', $caps, $role );
}

/**
 * Adds capabilities to WordPress user roles.
 *
 * @since 2.0.0 bbPress (r2608)
 *
 * @return void
 */
function bbp_add_caps() {

	// Loop through available roles and add caps
	$roles = bbp_get_wp_roles();
	if ( $roles instanceof WP_Roles ) {
		foreach ( $roles->role_objects as $role ) {
			foreach ( bbp_get_caps_for_role( $role->name ) as $cap => $value ) {
				$role->add_cap( $cap, $value );
			}
		}
	}

	do_action( 'bbp_add_caps' );
}

/**
 * Removes capabilities from WordPress user roles.
 *
 * @since 2.0.0 bbPress (r2608)
 *
 * @return void
 */
function bbp_remove_caps() {

	// Loop through available roles and remove caps
	$roles = bbp_get_wp_roles();
	if ( $roles instanceof WP_Roles ) {
		foreach ( $roles->role_objects as $role ) {
			foreach ( array_keys( bbp_get_caps_for_role( $role->name ) ) as $cap ) {
				$role->remove_cap( $cap );
			}
		}
	}

	do_action( 'bbp_remove_caps' );
}

/**
 * Get the available roles, minus the dynamic roles that come with bbPress.
 *
 * @since 2.4.0 bbPress (r5064)
 *
 * @return array<string, array<string, mixed>>|false
 */
function bbp_get_blog_roles() {

	// Get WordPress's roles (returns $wp_roles global)
	$wp_roles = bbp_get_wp_roles();

	// Apply the WordPress 'editable_roles' filter to let plugins ride along.
	//
	// We use this internally via bbp_filter_blog_editable_roles() to remove
	// any custom bbPress roles that are added to the global.
	$the_roles = isset( $wp_roles->roles ) ? $wp_roles->roles : false;
	$all_roles = apply_filters( 'editable_roles', $the_roles );

	/**
	 * Filters the available roles, minus the dynamic roles that come with bbPress.
	 *
	 * @since 2.4.0 bbPress (r5064)
	 *
	 * @param array<string, array<string, mixed>>|false $all_roles The available roles, minus the dynamic roles that come with bbpress.
	 * @param WP_Roles|null $wp_roles WordPress roles object, or null when unavailable.
	 */
	return apply_filters( 'bbp_get_blog_roles', $all_roles, $wp_roles );
}

/** Forum Roles ***************************************************************/

/**
 * Add the bbPress roles to the $wp_roles global.
 *
 * We do this to avoid adding these values to the database.
 *
 * Note: bbPress is purposely assertive here, overwriting any keys & values
 * that may already exist in the $wp_roles array.
 *
 * @since 2.2.0 bbPress (r4290)
 *
 * @param WP_Roles|null $wp_roles The array of WP_Role objects that was initialized.
 *
 * @return WP_Roles|null The main $wp_roles global, or null for invalid input.
 */
function bbp_add_forums_roles( $wp_roles = null ) {

	// Maybe initialize WP_Roles just-in-time, but
	if ( empty( $wp_roles ) && ! doing_action( 'wp_roles_init' ) ) {
		$wp_roles = wp_roles();
	}

	// Bail if unexpected param type
	if ( ! ( $wp_roles instanceof WP_Roles ) ) {
		return null;
	}

	// Get the dynamic roles
	$bbp_roles = bbp_get_dynamic_roles();

	// Loop through dynamic roles and add them to the $wp_roles array
	foreach ( $bbp_roles as $role_id => $details ) {
		$wp_roles->roles[ $role_id ]        = $details;
		$wp_roles->role_objects[ $role_id ] = new WP_Role( $role_id, $details['capabilities'] );
		$wp_roles->role_names[ $role_id ]   = $details['name'];
	}

	// Return the modified $wp_roles array
	return $wp_roles;
}

/**
 * Helper function to add filter to option_wp_user_roles.
 *
 * @since 2.2.0 bbPress (r4364)
 * @deprecated 2.6.0 bbPress (r6105)
 *
 * @see _bbp_reinit_dynamic_roles()
 *
 * @return void
 */
function bbp_filter_user_roles_option() {
	$role_key = bbp_db()->prefix . 'user_roles';

	add_filter( 'option_' . $role_key, '_bbp_reinit_dynamic_roles' );
}

/**
 * This is necessary because in a few places (noted below) WordPress initializes
 * a blog's roles directly from the database option. When this happens, the
 * $wp_roles global gets flushed, causing a user to magically lose any
 * dynamically assigned roles or capabilities when $current_user in refreshed.
 *
 * Because dynamic multiple roles is a new concept in WordPress, we work around
 * it here for now, knowing that improvements will come to WordPress core later.
 *
 * Also note that if using the $wp_user_roles global non-database approach,
 * bbPress does not have an intercept point to add its dynamic roles.
 *
 * @see bbp_switch_to_site()
 * @see bbp_restore_current_site()
 * @see WP_Roles::_init()
 *
 * @since 2.2.0 bbPress (r4364)
 * @deprecated 2.6.0 bbPress (r6105)
 *
 * @internal Used by bbPress to reinitialize dynamic roles on blog switch.
 *
 * @param array<string, array<string, mixed>> $roles Existing roles keyed by role ID.
 * @return array<string, array<string, mixed>> Combined database and dynamic bbPress roles.
 */
function _bbp_reinit_dynamic_roles( $roles = array() ) {
	foreach ( bbp_get_dynamic_roles() as $role_id => $details ) {
		$roles[ $role_id ] = $details;
	}
	return $roles;
}

/**
 * Fetch a filtered list of forum roles that the current user is
 * allowed to have.
 *
 * Simple function who's main purpose is to allow filtering of the
 * list of forum roles so that plugins can remove inappropriate ones depending
 * on the situation or user making edits.
 *
 * Specifically because without filtering, anyone with the edit_users
 * capability can edit others to be administrators, even if they are
 * only editors or authors. This filter allows admins to delegate
 * user management.
 *
 * @since 2.2.0 bbPress (r4352)
 * @since 2.6.0 bbPress (r6118) Use bbpress()->roles
 *
 * @return array<string, array{name: string, capabilities: array<string, bool>}>
 */
function bbp_get_dynamic_roles() {

	// Defaults
	$to_array = array();
	$roles    = bbpress()->roles;

	// Convert WP_Roles objects to arrays
	foreach ( $roles as $role_id => $wp_role ) {
		$to_array[ $role_id ] = (array) $wp_role;
	}

	/**
	 * Filters the registered dynamic forum roles.
	 *
	 * @since 2.2.0 bbPress (r4352)
	 *
	 * @param array<string, array{name: string, capabilities: array<string, bool>}> $to_array The registered dynamic forum roles.
	 * @param array $roles Registered roles.
	 */
	return (array) apply_filters( 'bbp_get_dynamic_roles', $to_array, $roles );
}

/**
 * Gets a translated role name from a role ID.
 *
 * @since 2.3.0 bbPress (r4793)
 * @since 2.6.0 bbPress (r6118) Use bbp_translate_user_role()
 *
 * @param string $role_id Role identifier.
 * @return string Translated role name.
 */
function bbp_get_dynamic_role_name( $role_id = '' ) {
	$roles = bbp_get_dynamic_roles();
	$role  = isset( $roles[ $role_id ] )
		? bbp_translate_user_role( $roles[ $role_id ]['name'] )
		: '';

	/**
	 * Filters a translated role name from a role ID.
	 *
	 * @since 2.3.0 bbPress (r4793)
	 *
	 * @param string $role Translated role name.
	 * @param string $role_id Role identifier.
	 * @param array $roles Registered roles.
	 */
	return apply_filters( 'bbp_get_dynamic_role_name', $role, $role_id, $roles );
}

/**
 * Removes the bbPress roles from the editable roles array.
 *
 * This used to use array_diff_assoc() but it randomly broke before 2.2 release.
 * Need to research what happened, and if there's a way to speed this up.
 *
 * @since 2.2.0 bbPress (r4304)
 *
 * @param array<string, array<string, mixed>> $all_roles All registered roles.
 * @return array<string, array<string, mixed>>
 */
function bbp_filter_blog_editable_roles( $all_roles = array() ) {

	// Loop through bbPress roles
	foreach ( array_keys( bbp_get_dynamic_roles() ) as $bbp_role ) {

		// Loop through WordPress roles
		foreach ( array_keys( $all_roles ) as $wp_role ) {

			// If keys match, unset
			if ( $wp_role === $bbp_role ) {
				unset( $all_roles[ $wp_role ] );
			}
		}
	}

	return $all_roles;
}

/**
 * The keymaster role for bbPress users.
 *
 * @since 2.2.0 bbPress (r4290)
 *
 * @return string
 */
function bbp_get_keymaster_role() {

	/**
	 * Filters the keymaster role ID.
	 *
	 * @since 2.2.0 bbPress (r4290)
	 *
	 * @param string $value The keymaster role id.
	 */
	return apply_filters( 'bbp_get_keymaster_role', 'bbp_keymaster' );
}

/**
 * The moderator role for bbPress users.
 *
 * @since 2.0.0 bbPress (r3413)
 *
 * @return string
 */
function bbp_get_moderator_role() {

	/**
	 * Filters the moderator role ID.
	 *
	 * @since 2.0.0 bbPress (r3413)
	 *
	 * @param string $value The moderator role id.
	 */
	return apply_filters( 'bbp_get_moderator_role', 'bbp_moderator' );
}

/**
 * The participant role for registered user that can participate in forums.
 *
 * @since 2.0.0 bbPress (r3413)
 *
 * @return string
 */
function bbp_get_participant_role() {

	/**
	 * Filters the participant role ID.
	 *
	 * @since 2.0.0 bbPress (r3413)
	 *
	 * @param string $value The participant role id.
	 */
	return apply_filters( 'bbp_get_participant_role', 'bbp_participant' );
}

/**
 * The spectator role is for registered users without any capabilities.
 *
 * @since 2.2.0 bbPress (r4290)
 *
 * @return string
 */
function bbp_get_spectator_role() {

	/**
	 * Filters the spectator role ID.
	 *
	 * @since 2.2.0 bbPress (r4290)
	 *
	 * @param string $value The spectator role id.
	 */
	return apply_filters( 'bbp_get_spectator_role', 'bbp_spectator' );
}

/**
 * The blocked role is for registered users that cannot spectate or participate.
 *
 * @since 2.2.0 bbPress (r4290)
 *
 * @return string
 */
function bbp_get_blocked_role() {

	/**
	 * Filters the blocked role ID.
	 *
	 * @since 2.2.0 bbPress (r4290)
	 *
	 * @param string $value The blocked role id.
	 */
	return apply_filters( 'bbp_get_blocked_role', 'bbp_blocked' );
}

/**
 * Adds bbPress-specific user roles.
 *
 * @since 2.0.0 bbPress (r2741)
 *
 * @deprecated 2.2.0 bbPress (r4164)
 *
 * @return void
 */
function bbp_add_roles() {
	_doing_it_wrong( 'bbp_add_roles', esc_html__( 'Editable forum roles no longer exist.', 'bbpress' ), '2.2' );
}

/**
 * Removes bbPress-specific user roles from the `wp_user_roles` array.
 *
 * This is currently only used when updating, uninstalling, or resetting bbPress.
 *
 * @see bbp_admin_reset_handler()
 * @see bbp_do_uninstall()
 * @see bbp_version_updater()
 *
 * @since 2.0.0 bbPress (r2741)
 *
 * @return void
 */
function bbp_remove_roles() {

	// Remove the bbPress roles
	foreach ( array_keys( bbp_get_dynamic_roles() ) as $bbp_role ) {
		remove_role( $bbp_role );
	}

	// Some early adopters may have a deprecated visitor role. It was later
	// replaced by the Spectator role.
	remove_role( 'bbp_visitor' );
}
