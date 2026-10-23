<?php

/**
 * bbPress User Capabilities.
 *
 * Used to map user capabilities to WordPress's existing capabilities.
 *
 * @package bbPress
 * @subpackage Capabilities
 */

/**
 * Maps primary capabilities.
 *
 * @since 2.2.0 bbPress (r4244)
 * @since 2.6.19 bbPress (r7689) Limit super moderator edits to site members and protect peer roles.
 * @since 2.6.20 bbPress (r7891) Treat network users as forum users when global access is enabled.
 *
 * @param array  $caps Capabilities for meta capability.
 * @param string $cap Capability name.
 * @param int    $user_id User id.
 * @param array  $args Arguments.
 *
 * @return array Actual capabilities for meta capability.
 */
function bbp_map_primary_meta_caps( $caps = array(), $cap = '', $user_id = 0, $args = array() ) {

	// What capability is being checked?
	switch ( $cap ) {
		case 'spectate' :

			// Do not allow inactive users.
			if ( bbp_is_user_inactive( $user_id ) ) {
				$caps = array( 'do_not_allow' );

			// Default to the current cap.
			} else {
				$caps = array( $cap );
			}
			break;

		case 'participate' :

			// Do not allow inactive users.
			if ( bbp_is_user_inactive( $user_id ) ) {
				$caps = array( 'do_not_allow' );

			// Default to the current cap.
			} else {
				$caps = array( $cap );
			}
			break;

		case 'moderate' :

			// Do not allow inactive users.
			if ( bbp_is_user_inactive( $user_id ) ) {
				$caps = array( 'do_not_allow' );

			// Keymasters can always moderate.
			} elseif ( bbp_is_user_keymaster( $user_id ) ) {
				$caps = array( 'spectate' );

			// Check if user can moderate forum.
			} elseif ( bbp_allow_forum_mods() ) {
				$caps = array( $cap );

				// Bail if no post to check.
				if ( empty( $args[0] ) ) {
					break;
				}

				// Get the post.
				$_post = get_post( $args[0] );
				if ( empty( $_post ) ) {
					break;
				}

				// Get forum ID for specific type of post.
				switch ( $_post->post_type ) {

					// Forum.
					case bbp_get_forum_post_type() :
						$forum_id = bbp_get_forum_id( $_post->ID );
						break;

					// Topic.
					case bbp_get_topic_post_type() :
						$forum_id = bbp_get_topic_forum_id( $_post->ID );
						break;

					// Reply.
					case bbp_get_reply_post_type() :
						$forum_id = bbp_get_reply_forum_id( $_post->ID );
						break;

					// Any other post type defaults to 0.
					default :
						$forum_id = 0;
						break;
				}

				// Bail if no forum ID.
				if ( empty( $forum_id ) ) {
					break;
				}

				// User is mod of this forum
				if ( bbp_is_object_of_user( $forum_id, $user_id, '_bbp_moderator_id' ) ) {
					$caps = array( 'spectate' );
				}
			}

			break;

		/** Super Moderators **************************************************/

		case 'edit_user'    :
		case 'promote_user' :
			$wp_query = bbp_get_wp_query();

			// Moderators can edit users if super moderators is enabled.
			if ( bbp_allow_super_mods() && ! is_admin() && ! empty( $wp_query->bbp_is_single_user ) && ( true === $wp_query->bbp_is_single_user ) ) {

				// Get the user ID
				$_user_id = ! empty( $args[0] )
					? (int) $args[0]
					: bbp_get_displayed_user_id();

				// Users can always edit themselves, so only map for others.
				if ( ! empty( $_user_id ) && ( $_user_id !== $user_id ) ) {

					// Limit edits to forum users and protect staff roles from peers.
					if (
						get_userdata( $_user_id )
						&& ( ! is_multisite() || bbp_allow_global_access() || is_user_member_of_blog( $_user_id ) )
						&& ! bbp_is_user_keymaster( $_user_id )
						&& ! user_can( $_user_id, 'manage_options' )
						&& ! is_super_admin( $_user_id )
						&& ( ( 'edit_user' === $cap ) || ! user_can( $_user_id, 'moderate' ) )
					) {
						$caps = array( 'moderate' );
					}
				}
			}

			break;
	}

	// Filter & return
	return (array) apply_filters( 'bbp_map_primary_meta_caps', $caps, $cap, $user_id, $args );
}

/**
 * Set a user's role in the forums.
 *
 * @since 2.2.0 bbPress (r4289)
 * @since 2.7.0 bbPress (r8131) Emit transitions and keep Blocked exclusive.
 *
 * @param int    $user_id  User id.
 * @param string $new_role Forum role to assign.
 *
 * @return mixed False if no change. String of new role if changed.
 */
function bbp_set_user_role( $user_id = 0, $new_role = '' ) {

	// Validate user id
	$user_id = bbp_get_user_id( $user_id, false, false );
	$user    = get_userdata( $user_id );

	// Only assign valid roles; an empty role never removes one.
	if ( ! empty( $user ) && ( '' !== $new_role ) && bbp_is_valid_role( $new_role ) ) {
		$new_role = bbp_update_user_role( $user, $new_role ) ? $new_role : false;

	// A missing user or an empty role is a no-op.
	} elseif ( empty( $user ) || ( '' === $new_role ) ) {
		$new_role = false;
	}

	// Filter & return
	return apply_filters( 'bbp_set_user_role', $new_role, $user_id, $user );
}

/**
 * Remove a user's forum role without changing their WordPress roles.
 *
 * @since 2.7.0 bbPress (r8131)
 *
 * @param int $user_id User ID.
 * @return bool Whether a forum role was removed.
 */
function bbp_remove_user_role( $user_id = 0 ) {
	$user_id = bbp_get_user_id( $user_id, false, false );
	$user    = get_userdata( $user_id );

	return ! empty( $user ) && bbp_update_user_role( $user, false );
}

/**
 * Apply a forum role change and announce an actual transition.
 *
 * This shared helper keeps Blocked exclusive, including when a user already
 * has that role alongside another forum role. Callers must pass a WP_User and
 * either a valid forum role or false for no role.
 *
 * @since 2.7.0 bbPress (r8131)
 *
 * @param WP_User     $user     User whose forum role is changing.
 * @param string|bool $new_role New forum role, or false to remove all forum roles.
 * @return bool Whether the user's forum role changed.
 */
function bbp_update_user_role( $user, $new_role ) {
	if (
		! ( $user instanceof WP_User )
		|| empty( $user->ID )
		|| ! get_userdata( $user->ID )
		|| ( false !== $new_role && ( ! is_string( $new_role ) || ( '' === $new_role ) || ! bbp_is_valid_role( $new_role ) ) )
	) {
		return false;
	}

	$old_role      = bbp_get_user_role( $user->ID );
	$blocked_role  = bbp_get_blocked_role();
	$forum_roles   = array_intersect( $user->roles, array_keys( bbp_get_dynamic_roles() ) );
	$remove_roles  = ( false === $new_role ) || ( $blocked_role === $new_role );

	// A repeated Blocked assignment still removes any other forum roles.
	if ( $old_role === $new_role ) {
		if ( $blocked_role === $new_role ) {
			foreach ( $forum_roles as $forum_role ) {
				if ( $blocked_role !== $forum_role ) {
					$user->remove_role( $forum_role );
				}
			}
		}

		return false;
	}

	// No role and Blocked remove every other forum role.
	if ( $remove_roles ) {
		foreach ( $forum_roles as $forum_role ) {
			$user->remove_role( $forum_role );
		}
	} elseif ( ! empty( $old_role ) ) {
		$user->remove_role( $old_role );
	}

	if ( false !== $new_role ) {
		$user->add_role( $new_role );
	}

	/**
	 * Fires after a user's forum role changes, including to or from no role.
	 *
	 * This action covers changes made through bbp_set_user_role() and
	 * bbp_remove_user_role() on the current site. No role is represented by
	 * false in either argument.
	 *
	 * @since 2.7.0 bbPress (r8131)
	 *
	 * @param string|bool $new_role New forum role, or false for no role.
	 * @param string|bool $old_role Previous forum role, or false for no role.
	 * @param WP_User     $user     User object.
	 */
	do_action( 'bbp_transition_user_role', $new_role, $old_role, $user );

	return true;
}

/**
 * Return the actions available when a user is blocked from the forums.
 *
 * Plugins may add site-specific policies by filtering this registry. Store the
 * action ID in the setting, not its callback.
 *
 * @since 2.7.0 bbPress (r8131)
 *
 * @return array Block action details keyed by action ID.
 */
function bbp_get_user_block_actions() {
	$actions = array(
		'destroy_sessions' => array(
			'name'       => __( 'Sign the user out everywhere', 'bbpress' ),
			'callback'   => 'bbp_destroy_user_sessions',
			'capability' => is_multisite() ? 'manage_network_users' : 'manage_options',
		),
		'block_sites' => array(
			'name'       => __( 'Block the user on every other site', 'bbpress' ),
			'callback'   => 'bbp_block_user_on_sites',
			'capability' => 'manage_network_users',
			'multisite'  => true,
		),
		'mark_spam' => array(
			'name'       => __( 'Globally mark the user as a spammer', 'bbpress' ),
			'callback'   => 'bbp_mark_user_as_spam',
			'capability' => 'manage_network_users',
			'multisite'  => true,
		),
	);

	// Filter & return
	return (array) apply_filters( 'bbp_get_user_block_actions', $actions );
}

/**
 * Check whether a registered blocking action can run for the current user.
 *
 * Plugins may omit the capability key for actions without an actor restriction.
 *
 * @since 2.7.0 bbPress (r8131)
 *
 * @param array $action           Registered action details.
 * @param bool  $check_capability Whether to check the current user's capability.
 * @return bool Whether the action is available.
 */
function bbp_is_user_block_action_available( $action, $check_capability = true ) {
	if (
		! is_array( $action )
		|| empty( $action['callback'] )
		|| ! is_callable( $action['callback'] )
		|| ( ! empty( $action['multisite'] ) && ! is_multisite() )
	) {
		return false;
	}

	if ( $check_capability && ! empty( $action['capability'] ) ) {
		return is_string( $action['capability'] ) && current_user_can( $action['capability'] );
	}

	return true;
}

/**
 * Run the selected actions after a user receives the blocked forum role.
 *
 * @since 2.7.0 bbPress (r8131)
 *
 * @param string|bool $new_role New forum role.
 * @param string|bool $old_role Previous forum role.
 * @param WP_User     $user     User object.
 */
function bbp_run_user_block_actions( $new_role, $old_role, $user ) {
	static $running = array();

	if ( bbp_get_blocked_role() !== $new_role || ! ( $user instanceof WP_User ) ) {
		return;
	}

	$user_id = bbp_get_user_id( $user->ID, false, false );

	if ( empty( $user_id ) || isset( $running[ $user_id ] ) ) {
		return;
	}

	$running[ $user_id ] = true;
	$actions = bbp_get_user_block_actions();

	try {
		foreach ( bbp_get_user_block_action_ids() as $action_id ) {
			if ( ! is_string( $action_id ) || ! isset( $actions[ $action_id ] ) || ! bbp_is_user_block_action_available( $actions[ $action_id ] ) ) {
				continue;
			}

			call_user_func( $actions[ $action_id ]['callback'], $user_id, $old_role, $user );
			do_action( 'bbp_user_block_action', $action_id, $user_id, $old_role, $user );
		}
	} finally {
		unset( $running[ $user_id ] );
	}
}

/**
 * Destroy all WordPress login sessions for a user.
 *
 * WordPress session tokens are shared across a multisite network, so this logs
 * the user out of every site in the installation.
 *
 * @since 2.7.0 bbPress (r8131)
 *
 * @param int $user_id User ID.
 * @return bool Whether sessions were destroyed.
 */
function bbp_destroy_user_sessions( $user_id ) {
	$user_id = bbp_get_user_id( $user_id, false, false );

	if ( empty( $user_id ) || ! get_userdata( $user_id ) ) {
		return false;
	}

	WP_Session_Tokens::get_instance( $user_id )->destroy_all();

	return true;
}

/**
 * Return a user's default forum role override, if it is a valid forum role.
 *
 * @since 2.7.0 bbPress (r8131)
 *
 * @param int $user_id User ID.
 * @return string|bool Forum role, or false if no valid override exists.
 */
function bbp_get_user_default_role_override( $user_id = 0 ) {
	$user_id = bbp_get_user_id( $user_id, false, false );
	$role    = empty( $user_id ) ? false : get_user_meta( $user_id, '_bbp_default_role', true );

	return bbp_is_valid_role( $role ) ? $role : false;
}

/**
 * Clear a Blocked default role override and other Blocked forum roles.
 *
 * Other default role overrides and non-Blocked roles remain untouched. Only a
 * network administrator can undo an installation-wide forum block.
 *
 * @since 2.7.0 bbPress (r8131)
 *
 * @param string|bool $new_role New forum role.
 * @param string|bool $old_role Previous forum role.
 * @param WP_User     $user     User object.
 */
function bbp_clear_user_default_role_on_unblock( $new_role, $old_role, $user ) {
	if (
		! is_multisite()
		|| bbp_get_blocked_role() !== $old_role
		|| bbp_get_blocked_role() === $new_role
		|| ! ( $user instanceof WP_User )
		|| ! current_user_can( 'manage_network_users' )
	) {
		return;
	}

	if ( bbp_get_blocked_role() !== bbp_get_user_default_role_override( $user->ID ) ) {
		return;
	}

	delete_user_meta( $user->ID, '_bbp_default_role', bbp_get_blocked_role() );
	bbp_set_user_role_on_sites( $user->ID, '', null, true, bbp_get_blocked_role() );
}

/**
 * Block existing forum roles and future default roles across multisite.
 *
 * A single user-meta override supplies the default role on future site visits.
 * Existing roles are changed only on sites where the user already has one.
 *
 * @since 2.7.0 bbPress (r8131)
 *
 * @param int $user_id User ID.
 * @return array Results keyed by site ID.
 */
function bbp_block_user_on_sites( $user_id ) {
	$user_id = bbp_get_user_id( $user_id, false, false );

	if ( ! is_multisite() || empty( $user_id ) || ! get_userdata( $user_id ) ) {
		return array();
	}

	update_user_meta( $user_id, '_bbp_default_role', bbp_get_blocked_role() );

	return bbp_set_user_role_on_sites( $user_id, bbp_get_blocked_role(), null, true );
}

/**
 * Mark a WordPress multisite user as spam.
 *
 * WordPress fires the make_spam_user action after the account is updated.
 * bbPress uses this action to mark the user's topics and replies as spam.
 *
 * @since 2.7.0 bbPress (r8131)
 *
 * @param int $user_id User ID.
 * @return bool Whether the user was marked as spam.
 */
function bbp_mark_user_as_spam( $user_id ) {
	$user_id = bbp_get_user_id( $user_id, false, false );
	$user    = get_userdata( $user_id );

	if ( ! is_multisite() || empty( $user ) || is_super_admin( $user_id ) ) {
		return false;
	}

	if ( ! empty( $user->spam ) ) {
		return true;
	}

	$result = wp_update_user(
		array(
			'ID'   => $user_id,
			'spam' => '1',
		)
	);

	if ( is_wp_error( $result ) ) {
		return false;
	}

	// Only undo a spam designation introduced by this blocking action.
	update_user_meta( $user_id, '_bbp_block_action_spammed_user', 1 );

	return true;
}

/**
 * Clear the blocking action's spam marker when a user is unspammed elsewhere.
 *
 * @since 2.7.0 bbPress (r8131)
 *
 * @param int $user_id User ID.
 */
function bbp_clear_user_block_spam_marker( $user_id ) {
	delete_user_meta( $user_id, '_bbp_block_action_spammed_user' );
}

/**
 * Undo a spam designation introduced by the blocking action on unblock.
 *
 * This uses WordPress's normal ham transition, including bbPress's existing
 * content restoration behavior. A pre-existing spam designation is untouched.
 *
 * @since 2.7.0 bbPress (r8131)
 *
 * @param string|bool $new_role New forum role.
 * @param string|bool $old_role Previous forum role.
 * @param WP_User     $user     User object.
 */
function bbp_unspam_user_after_unblock( $new_role, $old_role, $user ) {
	if (
		! is_multisite()
		|| bbp_get_blocked_role() !== $old_role
		|| bbp_get_blocked_role() === $new_role
		|| ! ( $user instanceof WP_User )
		|| ! current_user_can( 'manage_network_users' )
		|| ! get_user_meta( $user->ID, '_bbp_block_action_spammed_user', true )
	) {
		return;
	}

	$current_user = get_userdata( $user->ID );
	if ( empty( $current_user->spam ) ) {
		bbp_clear_user_block_spam_marker( $user->ID );
		return;
	}

	wp_update_user(
		array(
			'ID'   => $user->ID,
			'spam' => '0',
		)
	);
}

/**
 * Set a user's forum role on each selected site in a multisite network.
 *
 * The default site list contains sites where the user is already a member.
 * Built-in cross-site block and unblock actions change only matching existing
 * forum roles.
 * Integrations may filter the list for their own site policy.
 * Recursive calls for the same user and role are ignored, allowing this helper
 * to be called safely from a blocked user action.
 *
 * @since 2.7.0 bbPress (r8131)
 *
 * @param int         $user_id             User ID.
 * @param string|bool $new_role            New forum role. Pass an empty string to remove it.
 * @param array|null  $site_ids            Optional. Site IDs. Null defaults to the user's sites.
 * @param bool        $only_existing_roles Optional. Skip sites without an existing forum role.
 * @param string|bool $only_from_role      Optional. Change only sites with this forum role.
 * @return array Results keyed by site ID.
 */
function bbp_set_user_role_on_sites( $user_id = 0, $new_role = false, $site_ids = null, $only_existing_roles = false, $only_from_role = false ) {
	static $running = array();

	$user_id     = bbp_get_user_id( $user_id, false, false );
	$key         = $user_id . ':' . $new_role;
	$retval      = array();
	$remove_role = ( '' === $new_role );

	if ( ! is_multisite() || empty( $user_id ) || ( ! $remove_role && ! bbp_is_valid_role( $new_role ) ) || isset( $running[ $key ] ) ) {
		return $retval;
	}

	if ( null === $site_ids ) {
		$site_ids = array_keys( get_blogs_of_user( $user_id ) );
	}

	$site_ids = (array) apply_filters( 'bbp_set_user_role_site_ids', $site_ids, $user_id, $new_role );
	$site_ids = array_unique( array_map( 'intval', $site_ids ) );

	foreach ( $site_ids as $index => $site_id ) {
		if ( 0 >= $site_id ) {
			unset( $site_ids[ $index ] );
		}
	}

	$running[ $key ] = true;

	try {
		foreach ( $site_ids as $site_id ) {
			if ( ! get_site( $site_id ) ) {
				$retval[ $site_id ] = false;
				continue;
			}

			$switched = get_current_blog_id() !== $site_id;
			if ( $switched ) {
				bbp_switch_to_site( $site_id );
			}

			try {
				$existing_role = bbp_get_user_role( $user_id );
				if ( ( $only_existing_roles && ! $existing_role ) || ( false !== $only_from_role && $only_from_role !== $existing_role ) ) {
					continue;
				}

				$retval[ $site_id ] = $remove_role
					? bbp_remove_user_role( $user_id )
					: bbp_set_user_role( $user_id, $new_role );
			} finally {
				if ( $switched ) {
					bbp_restore_current_site();
				}
			}
		}
	} finally {
		unset( $running[ $key ] );
	}

	return $retval;
}

/**
 * Return a user's forums role.
 *
 * @since 2.1.0 bbPress (r3860)
 *
 * @param int $user_id User id.
 *
 * @return string
 */
function bbp_get_user_role( $user_id = 0 ) {

	// Validate user id
	$user_id = bbp_get_user_id( $user_id );
	$user    = get_userdata( $user_id );
	$role    = false;

	// User has roles so look for a bbPress one
	if ( ! empty( $user->roles ) ) {

		// Look for a bbPress role
		$roles = array_intersect(
			array_values( $user->roles ),
			array_keys( bbp_get_dynamic_roles() )
		);

		// If there's a role in the array, use the first one. This isn't very
		// smart, but since roles aren't exactly hierarchical, and bbPress
		// does not yet have a UI for multiple user roles, it's fine for now.
		if ( ! empty( $roles ) ) {
			$role = array_shift( $roles );
		}
	}

	// Filter & return
	return apply_filters( 'bbp_get_user_role', $role, $user_id, $user );
}

/**
 * Return a user's blog role.
 *
 * @since 2.3.0 bbPress (r4447)
 *
 * @param int $user_id User id.
 *
 * @return string
 */
function bbp_get_user_blog_role( $user_id = 0 ) {

	// Validate user id
	$user_id = bbp_get_user_id( $user_id );
	$user    = get_userdata( $user_id );
	$role    = false;

	// User has roles so lets
	if ( ! empty( $user->roles ) ) {

		// Look for a non bbPress role
		$roles = array_intersect(
			array_values( $user->roles ),
			array_keys( bbp_get_blog_roles() )
		);

		// If there's a role in the array, use the first one. This isn't very
		// smart, but since roles aren't exactly hierarchical, and WordPress
		// does not yet have a UI for multiple user roles, it's fine for now.
		if ( ! empty( $roles ) ) {
			$role = array_shift( $roles );
		}
	}

	// Filter & return
	return apply_filters( 'bbp_get_user_blog_role', $role, $user_id, $user );
}

/**
 * Helper function hooked to 'bbp_profile_update' action to save or
 * update user roles and capabilities.
 *
 * @since 2.2.0 bbPress (r4330)
 *
 * @param int $user_id User id.
 */
function bbp_profile_update_role( $user_id = 0 ) {

	// Bail if no user ID
	$user_id = bbp_get_user_id( $user_id, false, false );
	if ( empty( $user_id ) ) {
		return;
	}

	// Bail if not a user profile form post request
	if ( ! bbp_is_user_profile_form_post_request( $user_id ) ) {
		return;
	}

	// Bail if trying to set their own role
	if ( bbp_is_user_home_edit() ) {
		return;
	}

	// Bail if the current user cannot edit the forum role.
	if ( ! bbp_current_user_can_edit_user_field( 'forum_role', $user_id ) ) {
		return;
	}

	// Bail if current user cannot promote the passing user
	if ( ! current_user_can( 'promote_user', $user_id ) ) {
		return;
	}

	// Bail if no forum role field was submitted.
	if ( ! isset( $_POST['bbp-forums-role'] ) || ! is_string( $_POST['bbp-forums-role'] ) ) {
		return;
	}

	// Forums role we want the user to have.
	$new_role = sanitize_key( wp_unslash( $_POST['bbp-forums-role'] ) );

	// Do not remove a current role that this editor cannot assign.
	$editable_roles = bbp_get_user_editable_forum_roles( $user_id );
	$current_role   = bbp_get_user_role( $user_id );

	if (
		( ( '' !== $new_role ) && ! array_key_exists( $new_role, $editable_roles ) )
		|| ( ( '' === $new_role ) && ! empty( $current_role ) && ! array_key_exists( $current_role, $editable_roles ) )
	) {
		return;
	}

	// Set or remove the new forums role.
	if ( '' === $new_role ) {
		bbp_remove_user_role( $user_id );
	} else {
		bbp_set_user_role( $user_id, $new_role );
	}
}

/**
 * Return the forum roles the current user may assign to another user.
 *
 * @since 2.6.17 bbPress (r7513)
 * @since 2.6.19 bbPress (r7685) Allow site administrators to assign staff roles.
 *
 * @param int $user_id User being edited. Defaults to the displayed user.
 * @return array Filtered array of editable forum roles.
 */
function bbp_get_user_editable_forum_roles( $user_id = 0 ) {
	$user_id = bbp_get_user_id( $user_id, false, false );
	$roles   = bbp_get_dynamic_roles();

	// Only keymasters and site administrators may assign staff roles.
	if ( ! bbp_is_user_keymaster() && ! current_user_can( 'manage_options' ) ) {
		unset(
			$roles[ bbp_get_keymaster_role() ],
			$roles[ bbp_get_moderator_role() ]
		);
	}

	// Filter & return.
	return (array) apply_filters( 'bbp_get_user_editable_forum_roles', $roles, $user_id, bbp_get_current_user_id() );
}

/**
 * Return whether the current user may edit a user-profile field.
 *
 * @since 2.6.17 bbPress (r7513)
 * @since 2.6.20 bbPress (r7891) Limit non-member edits to the forum role on multisite.
 *
 * @param string $field   Profile field group: profile, email, password,
 *                        site_role, or forum_role.
 * @param int    $user_id User being edited. Defaults to the displayed user.
 * @return bool Whether the field may be edited.
 */
function bbp_current_user_can_edit_user_field( $field = 'profile', $user_id = 0 ) {
	$user_id         = bbp_get_user_id( $user_id, false, false );
	$current_user_id = bbp_get_current_user_id();

	// Default to the general profile-edit capability.
	$retval = current_user_can( 'edit_user', $user_id );

	// Apply narrower defaults to sensitive field groups.
	switch ( $field ) {
		case 'email':
		case 'password':
			$retval = ! empty( $user_id )
				&& ! empty( $current_user_id )
				&& $retval
				&& ( ( $user_id === $current_user_id )
					|| bbp_is_user_keymaster( $current_user_id )
					|| current_user_can( 'manage_options' ) );
			break;

		case 'site_role':
			$retval = current_user_can( 'promote_users' )
				&& current_user_can( 'promote_user', $user_id );
			break;

		case 'forum_role':
			$retval = current_user_can( 'promote_user', $user_id );
			break;
	}

	// Profile fields are shared across the network, so limit non-members to their forum role.
	if (
		( 'forum_role' !== $field )
		&& is_multisite()
		&& ! empty( $user_id )
		&& ( $user_id !== $current_user_id )
		&& ! is_user_member_of_blog( $user_id )
		&& ! current_user_can( 'manage_network_users' )
	) {
		$retval = false;
	}

	// Filter & return.
	return (bool) apply_filters( 'bbp_current_user_can_edit_user_field', $retval, $field, $user_id, $current_user_id );
}

/**
 * Check if a role ID is valid.
 *
 * This helper function accepts a role ID as a string, and compares it against
 * the array of registered dynamic roles.
 *
 * Use this function anytime you are manually attempting to set a user role
 * without using the bbp_set_user_role() function, or if you need to halt
 * additional processing during role validation.
 *
 * @since 2.6.5 bbPress (r7086)
 *
 * @param string $role A well-formed (string) role ID to validate.
 *
 * @return bool True if role is valid. False if role is not valid.
 */
function bbp_is_valid_role( $role = '' ) {

	// Default return value
	$retval = false;

	// Skip if no role to check
	if ( ! empty( $role ) && is_string( $role ) ) {

		// Get the dynamic role IDs
		$roles = array_keys( bbp_get_dynamic_roles() );

		// Skip if no known role IDs
		if ( ! empty( $roles ) ) {

			// Is role in dynamic roles array?
			$retval = in_array( $role, $roles, true );
		}
	}

	// Filter & return
	return (bool) apply_filters( 'bbp_is_valid_role', $retval, $role );
}

/**
 * Add the default role to the current user if needed.
 *
 * This function will bail if the user is marked as spam or deleted.
 *
 * @since 2.2.0 bbPress (r4185)
 *
 * @return If not logged in, already assigned a forum role, or inactive.
 */
function bbp_set_current_user_default_role() {

	/** Sanity ****************************************************************/

	// Bail if deactivating bbPress
	if ( bbp_is_deactivation() ) {
		return;
	}

	// Catch all, to prevent premature user initialization
	if ( ! did_action( 'set_current_user' ) ) {
		return;
	}

	// Bail if not logged in or already a member of this site
	if ( ! is_user_logged_in() ) {
		return;
	}

	// Get the current user ID
	$user_id = bbp_get_current_user_id();

	// Bail if user already has a forums role
	if ( bbp_get_user_role( $user_id ) ) {
		return;
	}

	// Bail if user is marked as spam or is deleted
	if ( bbp_is_user_inactive( $user_id ) ) {
		return;
	}

	/** Ready *****************************************************************/

	// Load up bbPress once
	$bbp         = bbpress();

	// Get whether or not to add a role to the user account
	$add_to_site = bbp_allow_global_access();

	/** Forum Role ************************************************************/

	// A valid user override takes precedence over the site's role map.
	$new_role = bbp_get_user_default_role_override( $user_id );
	if ( false === $new_role ) {
		$user_role = bbp_get_user_blog_role( $user_id );
		$role_map  = bbp_get_user_role_map();
		$new_role  = empty( $user_role ) || ! isset( $role_map[ $user_role ] )
			? bbp_get_default_role()
			: $role_map[ $user_role ];
	}

	// A filtered empty role must not be interpreted as an explicit removal.
	if ( ! bbp_is_valid_role( $new_role ) ) {
		return;
	}

	/** Add or Map ************************************************************/

	// Add the user to the site
	if ( true === $add_to_site ) {
		bbp_set_user_role( $user_id, $new_role );

	// Don't add the user, but still give them the correct caps dynamically
	} else {
		$bbp->current_user->caps[ $new_role ] = true;
		$bbp->current_user->get_role_caps();
	}
}

/**
 * Return a map of WordPress roles to bbPress roles. Used to automatically grant
 * appropriate bbPress roles to WordPress users that wouldn't already have a
 * role in the forums. Also guarantees WordPress admins get the Keymaster role.
 *
 * @since 2.2.0 bbPress (r4335)
 *
 * @return array Filtered array of WordPress roles to bbPress roles.
 */
function bbp_get_user_role_map() {

	// Get the default role once here
	$default_role = bbp_get_default_role();

	// Filter & return
	return (array) apply_filters(
		'bbp_get_user_role_map',
		array(
			'administrator' => bbp_get_keymaster_role(),
			'editor'        => $default_role,
			'author'        => $default_role,
			'contributor'   => $default_role,
			'subscriber'    => $default_role
		)
	);
}

/** User Status ***************************************************************/

/**
 * Checks if the user has been marked as a spammer.
 *
 * @since 2.0.0 bbPress (r3356)
 *
 * @param int $user_id int The ID for the user.
 * @return bool True if spammer, False if not.
 */
function bbp_is_user_spammer( $user_id = 0 ) {

	// Default to current user
	if ( empty( $user_id ) && is_user_logged_in() ) {
		$user_id = bbp_get_current_user_id();
	}

	// No user to check
	if ( empty( $user_id ) ) {
		return false;
	}

	// Assume user is not spam
	$is_spammer = false;

	// Get user data
	$user = get_userdata( $user_id );

	// No user found
	if ( empty( $user ) ) {
		$is_spammer = false;

	// Check if spam
	} elseif ( ! empty( $user->spam ) ) {
		$is_spammer = true;
	}

	// Filter & return
	return (bool) apply_filters( 'bbp_core_is_user_spammer', $is_spammer );
}

/**
 * Mark a users topics and replies as spam when the user is marked as spam.
 *
 * @since 2.0.0 bbPress (r3410)
 *
 * @param int $user_id Optional. User ID to spam. Defaults to displayed user.
 *
 * @return bool If no user ID passed.
 */
function bbp_make_spam_user( $user_id = 0 ) {

	// Use displayed user if it's not yourself
	if ( empty( $user_id ) && bbp_is_single_user() && ! bbp_is_user_home() ) {
		$user_id = bbp_get_displayed_user_id();
	}

	// Bail if no user ID
	if ( empty( $user_id ) ) {
		return false;
	}

	// Bail if user ID is keymaster
	if ( bbp_is_user_keymaster( $user_id ) ) {
		return false;
	}

	// Arm the torpedos
	$bbp_db = bbp_db();

	// Get the blog IDs of the user to mark as spam
	$blogs = get_blogs_of_user( $user_id, true );

	// If user has no blogs, they are a guest on this site
	if ( empty( $blogs ) ) {
		$blogs[ $bbp_db->blogid ] = array();
	}

	// Process replies before topics so topic status helpers run last
	$post_types = array( bbp_get_reply_post_type(), bbp_get_topic_post_type() );

	// Get array of statuses to mark as spam
	$post_statuses = bbp_get_public_topic_statuses();
	$post_statuses = "'" . implode( "', '", $post_statuses ) . "'";

	// Loop through blogs and remove their posts
	foreach ( (array) array_keys( $blogs ) as $blog_id ) {

		// Switch to the site ID
		bbp_switch_to_site( $blog_id );

		foreach ( $post_types as $post_type ) {

			// Get posts of this type
			$query = $bbp_db->prepare( "SELECT ID FROM {$bbp_db->posts} WHERE post_author = %d AND post_status IN ( {$post_statuses} ) AND post_type = %s", $user_id, $post_type );
			$posts = $bbp_db->get_col( $query );

			// Loop through posts and spam them
			if ( ! empty( $posts ) ) {
				foreach ( $posts as $post_id ) {

					// The routines for topics and replies are different, so use the
					// correct one based on the post type
					switch ( $post_type ) {

						case bbp_get_topic_post_type() :
							bbp_spam_topic( $post_id );
							break;

						case bbp_get_reply_post_type() :
							bbp_spam_reply( $post_id );
							break;
					}
				}
			}
		}

		// Switch back to current site
		bbp_restore_current_site();
	}

	// Delete user options
	bbp_delete_user_options( $user_id );

	// Success
	return true;
}

/**
 * Mark a users topics and replies as spam when the user is marked as spam.
 *
 * @since 2.0.0 bbPress (r3410)
 *
 * @param int $user_id Optional. User ID to unspam. Defaults to displayed user.
 *
 * @return bool If no user ID passed.
 */
function bbp_make_ham_user( $user_id = 0 ) {

	// Use displayed user if it's not yourself
	if ( empty( $user_id ) && bbp_is_single_user() && ! bbp_is_user_home() ) {
		$user_id = bbp_get_displayed_user_id();
	}

	// Bail if no user ID
	if ( empty( $user_id ) ) {
		return false;
	}

	// Bail if user ID is keymaster
	if ( bbp_is_user_keymaster( $user_id ) ) {
		return false;
	}

	// Arm the torpedos
	$bbp_db = bbp_db();

	// Get the blog IDs of the user to mark as spam
	$blogs = get_blogs_of_user( $user_id, true );

	// If user has no blogs, they are a guest on this site
	if ( empty( $blogs ) ) {
		$blogs[ $bbp_db->blogid ] = array();
	}

	// Process replies before topics so topic status helpers run last
	$post_types = array( bbp_get_reply_post_type(), bbp_get_topic_post_type() );

	// Get array of statuses to unmark as spam
	$post_statuses = array( bbp_get_spam_status_id() );
	$post_statuses = "'" . implode( "', '", $post_statuses ) . "'";

	// Loop through blogs and remove their posts
	foreach ( (array) array_keys( $blogs ) as $blog_id ) {

		// Switch to the site ID
		bbp_switch_to_site( $blog_id );

		foreach ( $post_types as $post_type ) {

			// Get posts of this type
			$query = $bbp_db->prepare( "SELECT ID FROM {$bbp_db->posts} WHERE post_author = %d AND post_status IN ( {$post_statuses} ) AND post_type = %s", $user_id, $post_type );
			$posts = $bbp_db->get_col( $query );

			// Loop through posts and unspam them
			if ( ! empty( $posts ) ) {
				foreach ( $posts as $post_id ) {

					// The routines for topics and replies are different, so use the
					// correct one based on the post type
					switch ( $post_type ) {

						case bbp_get_topic_post_type() :
							bbp_unspam_topic( $post_id );
							break;

						case bbp_get_reply_post_type() :
							bbp_unspam_reply( $post_id );
							break;
					}
				}
			}
		}

		// Switch back to current site
		bbp_restore_current_site();
	}

	// Update topic & reply counts
	bbp_update_user_topic_count( $user_id, bbp_get_user_topic_count_raw( $user_id ) );
	bbp_update_user_reply_count( $user_id, bbp_get_user_reply_count_raw( $user_id ) );

	// Update last posted (to now)
	bbp_update_user_last_posted( $user_id );

	// Success
	return true;
}

/**
 * Checks if the user has been marked as deleted.
 *
 * @since 2.0.0 bbPress (r3356)
 *
 * @param int $user_id int The ID for the user.
 * @return bool True if deleted, False if not.
 */
function bbp_is_user_deleted( $user_id = 0 ) {

	// Default to current user
	if ( empty( $user_id ) && is_user_logged_in() ) {
		$user_id = bbp_get_current_user_id();
	}

	// No user to check
	if ( empty( $user_id ) ) {
		return false;
	}

	// Assume user is not deleted
	$is_deleted = false;

	// Get user data
	$user = get_userdata( $user_id );

	// No user found
	if ( empty( $user ) ) {
		$is_deleted = true;

	// Check if deleted
	} elseif ( ! empty( $user->deleted ) ) {
		$is_deleted = true;
	}

	// Filter & return
	return (bool) apply_filters( 'bbp_core_is_user_deleted', $is_deleted );
}

/**
 * Checks if user is active.
 *
 * @since 2.0.0 bbPress (r3504)
 *
 * @param int $user_id The user ID to check.
 * @return bool True if public, false if not.
 */
function bbp_is_user_active( $user_id = 0 ) {

	// No user to check
	$user_id = bbp_get_user_id( $user_id, false, true );
	if ( empty( $user_id ) ) {
		return false;
	}

	// Check spam
	if ( bbp_is_user_spammer( $user_id ) ) {
		return false;
	}

	// Check deleted
	if ( bbp_is_user_deleted( $user_id ) ) {
		return false;
	}

	// Assume true if not spam or deleted
	return true;
}

/**
 * Checks if user is not active.
 *
 * @since 2.0.0 bbPress (r3504)
 *
 * @param int $user_id The user ID to check. Defaults to current user ID.
 * @return bool True if inactive, false if active.
 */
function bbp_is_user_inactive( $user_id = 0 ) {
	return ! bbp_is_user_active( $user_id );
}

/**
 * Checks if user is a keymaster.
 *
 * @since 2.3.0 bbPress (r4783)
 *
 * @param int $user_id The user ID to check. Defaults to current user ID.
 * @return bool True if keymaster, false if not.
 */
function bbp_is_user_keymaster( $user_id = 0 ) {
	$_user_id = bbp_get_user_id( $user_id, false, true );
	$retval   = user_can( $_user_id, 'keep_gate' );

	// Filter & return
	return (bool) apply_filters( 'bbp_is_user_keymaster', $retval, $_user_id, $user_id );
}

/**
 * Does a user have a profile for the current site.
 *
 * @since 2.2.0 bbPress (r4363)
 *
 * @param int $user_id User ID to check.
 *
 * @return bool Whether or not the user has a profile on this blog_id.
 */
function bbp_user_has_profile( $user_id = 0 ) {

	// Assume every user has a profile
	$retval  = true;

	// Validate user ID, default to displayed or current user
	$user_id = bbp_get_user_id( $user_id, true, true );

	// Try to get this user's data
	$user    = get_userdata( $user_id );

	// No user found, return false
	if ( empty( $user ) ) {
		$retval = false;

	// User is inactive, and current user is not a keymaster
	} elseif ( ! bbp_is_user_keymaster() && bbp_is_user_inactive( $user->ID ) ) {
		$retval = false;
	}

	// Filter & return
	return (bool) apply_filters( 'bbp_show_user_profile', $retval, $user_id );
}

/** Moderators ****************************************************************/

/**
 * Add a moderator to an object.
 *
 * @since 2.6.0 bbPress (r6056)
 *
 * @param int    $object_id   Traditionally a post ID.
 * @param int    $user_id     User ID.
 * @param string $object_type Type of meta (post,term,user,comment).
 *
 * @return bool
 */
function bbp_add_moderator( $object_id = 0, $user_id = 0, $object_type = 'post' ) {
	return bbp_add_user_to_object( $object_id, $user_id, '_bbp_moderator_id', $object_type );
}

/**
 * Remove a moderator user ID from an object.
 *
 * @since 2.6.0 bbPress (r6056)
 *
 * @param int    $object_id   Traditionally a post ID.
 * @param int    $user_id     User ID.
 * @param string $object_type Type of meta (post,term,user,comment).
 *
 * @return bool
 */
function bbp_remove_moderator( $object_id = 0, $user_id = 0, $object_type = 'post' ) {
	return bbp_remove_user_from_object( $object_id, $user_id, '_bbp_moderator_id', $object_type );
}

/**
 * Get user IDs of moderators for an object.
 *
 * @since 2.6.0 bbPress (r6056)
 *
 * @param int    $object_id   Traditionally a post ID.
 * @param string $object_type Type of meta (post,term,user,comment).
 *
 * @return array
 */
function bbp_get_moderator_ids( $object_id = 0, $object_type = 'post' ) {
	return bbp_get_users_for_object( $object_id, '_bbp_moderator_id', $object_type );
}

/**
 * Get moderators for a specific object ID. Will return global moderators when
 * object ID is empty.
 *
 * @since 2.6.0 bbPress (r6056)
 *
 * @param int    $object_id   Traditionally a post ID.
 * @param string $object_type Type of meta (post,term,user,comment).
 *
 * @return array
 */
function bbp_get_moderators( $object_id = 0, $object_type = 'post' ) {

	// Get global moderators
	if ( empty( $object_id ) ) {
		$users = get_users(
			array(
				'role__in' => bbp_get_moderator_role(),
			)
		);

	// Get object moderators
	} else {
		$user_ids = bbp_get_moderator_ids( $object_id, $object_type );
		$users    = ! empty( $user_ids )
			? get_users( array( 'include' => $user_ids ) )
			: array();
	}

	// Filter & return
	return (array) apply_filters( 'bbp_get_moderators', $users, $object_id, $object_type );
}
