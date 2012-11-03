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
 * Get the primary bbPress capabilities
 *
 * @since bbPress (r4163)
 *
 * @return array of primary capabilities
 */
function bbp_get_primary_capabilities() {
	return apply_filters( 'bbp_get_primary_capabilities', array(

		// Current caps
		'spectate',
		'participate',
		'moderate',
		'throttle',
		'view_trash',

		// Legacy caps
		'banned',
		'blocked',
		'bozo'
	) );
}

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
 * Remove all bbPress capabilities for a given user
 *
 * @since bbPress (r4221)
 *
 * @param int $user_id
 * @return boolean True on success, false on failure
 */
function bbp_remove_user_caps( $user_id = 0 ) {

	// Bail if no user was passed
	if ( empty( $user_id ) )
		return false;

	// Load up the user
	$user = new WP_User( $user_id );

	// Remove all caps
	foreach ( bbp_get_capability_groups() as $group )
		foreach ( bbp_get_capabilities_for_group( $group ) as $capability )
			$user->remove_cap( $capability );

	// Success
	return true;
}

/**
 * Remove all bbPress capabilities for a given user
 *
 * @since bbPress (r4221)
 *
 * @param int $user_id
 * @return boolean True on success, false on failure
 */
function bbp_reset_user_caps( $user_id = 0 ) {

	// Bail if no user was passed
	if ( empty( $user_id ) )
		return false;

	// Bail if current user cannot edit this user
	if ( ! current_user_can( 'edit_user', $user_id ) )
		return false;

	// Remove all caps for user
	bbp_remove_user_caps( $user_id );

	// Load up the user
	$user = new WP_User( $user_id );

	// User has no role so bail
	if ( ! isset( $user->roles ) )
		return false;

	// Use first user role
	$caps = bbp_get_caps_for_role( array_shift( $user->roles ) );

	// Add caps for the first role
	foreach ( $caps as $cap => $value )
		$user->add_cap( $cap, $value );

	// Success
	return true;
}

/**
 * Save all bbPress capabilities for a given user
 *
 * @since bbPress (r4221)
 *
 * @param type $user_id
 * @return boolean
 */
function bbp_save_user_caps( $user_id = 0 ) {

	// Bail if no user was passed
	if ( empty( $user_id ) )
		return false;

	// Bail if current user cannot edit this user
	if ( ! current_user_can( 'edit_user', $user_id ) )
		return false;

	// Load up the user
	$user = new WP_User( $user_id );

	// Loop through capability groups
	foreach ( bbp_get_capability_groups() as $group ) {
		foreach ( bbp_get_capabilities_for_group( $group ) as $capability ) {

			// Maybe add cap
			if ( ! empty( $_POST['_bbp_' . $capability] ) && ! $user->has_cap( $capability ) ) {
				$user->add_cap( $capability, true );

			// Maybe remove cap
			} elseif ( empty( $_POST['_bbp_' . $capability] ) && $user->has_cap( $capability ) ) {
				$user->add_cap( $capability, false );
			}
		}
	}

	// Success
	return true;
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
function bbp_profile_update_capabilities( $user_id = 0 ) {

	// Bail if no user ID was passed
	if ( empty( $user_id ) )
		return;

	// Bail if advanced capability editor is off
	if ( ! empty( $_POST['bbp-forums-role'] ) ) {

		// Fromus role we want the user to have
		$new_role    = sanitize_text_field( $_POST['bbp-forums-role'] );
		$forums_role = bbp_get_user_role( $user_id );

		// Set the new forums role
		if ( $new_role != $forums_role ) {
			bbp_set_user_role( $user_id, $new_role );
		}

		// Remove any interim form user capabilities
		bbp_remove_user_caps( $user_id );
	}

	// Save additional capabilities
	if ( bbp_use_advanced_capability_editor() ) {

		// Either reset caps for role
		if ( ! empty( $_POST['bbp-default-caps'] ) ) {
			bbp_reset_user_caps( $user_id );

		// Or set caps individually
		} else {
			bbp_save_user_caps( $user_id );
		}
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

	// Bail if forum is not global
	if ( ! bbp_allow_global_access() )
		return;

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

	// Remove any interim bbPress caps
	bbp_remove_user_caps( $user_id );

	// Assign the default role to the current user
	bbpress()->current_user->add_role( bbp_get_default_role() );
}
