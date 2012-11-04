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

	// Load up bbPress
	$bbp = bbpress();

	// Get the current user's WordPress role. Set to empty string if none found.
	$user_role = isset( $bbp->current_user->roles ) ? array_shift( $bbp->current_user->roles ) : '';

	// Loop through the role map, and grant the proper bbPress role
	foreach ( (array) bbp_get_user_role_map() as $wp_role => $bbp_role ) {
		if ( $user_role == $wp_role ) {
			$bbp->current_user->add_role( $bbp_role );
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
	return (array) apply_filters( 'bbp_get_user_role_map', array (
		'administrator' => bbp_get_keymaster_role(),
		'editor'        => bbp_get_participant_role(),
		'author'        => bbp_get_participant_role(),
		'contributor'   => bbp_get_participant_role(),
		'subscriber'    => bbp_get_participant_role(),
		''              => bbp_get_participant_role()
	) );
}