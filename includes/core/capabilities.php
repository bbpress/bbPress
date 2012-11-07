<?php

/**
 * bbPress Capabilites
 *
 * The functions in this file are used primarily as convenient wrappers for
 * capability output in user profiles. This includes mapping capabilities and
 * groups to human readable strings,
 *
 * @package bbPress
 * @subpackage Capabilities
 */

// Exit if accessed directly
if ( !defined( 'ABSPATH' ) ) exit;

/** Mapping *******************************************************************/

/**
 * Returns an array of capabilities based on the role that is being requested.
 *
 * @since bbPress (r2994)
 *
 * @todo Map all of these and deprecate
 *
 * @param string $role Optional. Defaults to The role to load caps for
 * @uses apply_filters() Allow return value to be filtered
 *
 * @return array Capabilities for $role
 */
function bbp_get_caps_for_role( $role = '' ) {

	// Which role are we looking for?
	switch ( $role ) {

		// Keymaster
		case bbp_get_keymaster_role() :
			$caps = array(

				// Primary caps
				'spectate'              => true,
				'participate'           => true,
				'moderate'              => true,
				'throttle'              => true,
				'view_trash'            => true,

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
				'view_trash'            => false,

				// Forum caps
				'publish_forums'        => true,
				'edit_forums'           => true,
				'edit_others_forums'    => false,
				'delete_forums'         => false,
				'delete_others_forums'  => false,
				'read_private_forums'   => true,
				'read_hidden_forums'    => false,

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
		case bbp_get_anonymous_role()   :
		case bbp_get_participant_role() :
		default :
			$caps = array(

				// Primary caps
				'spectate'              => true,
				'participate'           => true,
				'moderate'              => false,
				'throttle'              => false,
				'view_trash'            => false,

				// Forum caps
				'publish_forums'        => false,
				'edit_forums'           => false,
				'edit_others_forums'    => false,
				'delete_forums'         => false,
				'delete_others_forums'  => false,
				'read_private_forums'   => true,
				'read_hidden_forums'    => false,

				// Topic caps
				'publish_topics'        => true,
				'edit_topics'           => true,
				'edit_others_topics'    => false,
				'delete_topics'         => false,
				'delete_others_topics'  => false,
				'read_private_topics'   => false,

				// Reply caps
				'publish_replies'       => true,
				'edit_replies'          => true,
				'edit_others_replies'   => false,
				'delete_replies'        => false,
				'delete_others_replies' => false,
				'read_private_replies'  => false,

				// Topic tag caps
				'manage_topic_tags'     => false,
				'edit_topic_tags'       => false,
				'delete_topic_tags'     => false,
				'assign_topic_tags'     => true,
			);

			break;
	}

	return apply_filters( 'bbp_get_caps_for_role', $caps, $role );
}

/**
 * Adds capabilities to WordPress user roles.
 *
 * @since bbPress (r2608)
 */
function bbp_add_caps() {

	// Loop through available roles and add caps
	foreach( bbp_get_wp_roles()->role_objects as $role ) {
		foreach ( bbp_get_caps_for_role( $role->name ) as $cap => $value ) {
			$role->add_cap( $cap, $value );
		}
	}

	do_action( 'bbp_add_caps' );
}

/**
 * Removes capabilities from WordPress user roles.
 *
 * @since bbPress (r2608)
 */
function bbp_remove_caps() {

	// Loop through available roles and remove caps
	foreach( bbp_get_wp_roles()->role_objects as $role ) {
		foreach ( array_keys( bbp_get_caps_for_role( $role->name ) ) as $cap ) {
			$role->remove_cap( $cap );
		}
	}

	do_action( 'bbp_remove_caps' );
}

/**
 * Get the $wp_roles global without needing to declare it everywhere
 *
 * @since bbPress (r4293)
 *
 * @global WP_Roles $wp_roles
 * @return WP_Roles
 */
function bbp_get_wp_roles() {
	global $wp_roles;

	// Load roles if not set
	if ( ! isset( $wp_roles ) )
		$wp_roles = new WP_Roles();

	return $wp_roles;
}

/** Forum Roles ***************************************************************/

/**
 * Add the bbPress roles to the $wp_roles global.
 *
 * We do this to avoid adding these values to the database.
 *
 * @since bbPress (r4290)
 */
function bbp_add_forums_roles() {
	$wp_roles = bbp_get_wp_roles();

	foreach( bbp_get_dynamic_roles() as $role_id => $details ) {
		$wp_roles->roles[$role_id]        = $details;
		$wp_roles->role_objects[$role_id] = new WP_Role( $details['name'], $details['capabilities'] );
		$wp_roles->role_names[$role_id]   = $details['name'];
	}
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
 * @since bbPress (r4284)
 *
 * @return array
 */
function bbp_get_dynamic_roles() {
	return (array) apply_filters( 'bbp_get_dynamic_roles', array(

		// Keymaster
		bbp_get_keymaster_role() => array(
			'name'         => __( 'Keymaster', 'bbpress' ),
			'capabilities' => bbp_get_caps_for_role( bbp_get_keymaster_role() )
		),

		// Moderator
		bbp_get_moderator_role() => array(
			'name'         => __( 'Moderator', 'bbpress' ),
			'capabilities' => bbp_get_caps_for_role( bbp_get_moderator_role() )
		),

		// Participant
		bbp_get_participant_role() => array(
			'name'         => __( 'Participant', 'bbpress' ),
			'capabilities' => bbp_get_caps_for_role( bbp_get_participant_role() )
		),

		// Spectator
		bbp_get_spectator_role() => array(
			'name'         => __( 'Spectator', 'bbpress' ),
			'capabilities' => bbp_get_caps_for_role( bbp_get_spectator_role() )
		),

		// Anonymous
		bbp_get_anonymous_role() => array(
			'name'         => __( 'Anonymous', 'bbpress' ),
			'capabilities' => bbp_get_caps_for_role( bbp_get_anonymous_role() )
		),

		// Blocked
		bbp_get_blocked_role() => array(
			'name'         => __( 'Blocked', 'bbpress' ),
			'capabilities' => bbp_get_caps_for_role( bbp_get_blocked_role() )
		)
	) );
}

/**
 * Removes the bbPress roles from the editable roles array
 *
 * @since bbPress (r4303)
 *
 * @param array $all_roles All registered roles
 * @return array 
 */
function bbp_filter_blog_editable_roles( $all_roles = array() ) {
	return array_diff_assoc( $all_roles, bbp_get_dynamic_roles() );
}

/**
 * The keymaster role for bbPress users
 *
 * @since bbPress (r4284)
 *
 * @uses apply_filters() Allow override of hardcoded keymaster role
 * @return string
 */
function bbp_get_keymaster_role() {
	return apply_filters( 'bbp_get_keymaster_role', 'bbp_keymaster' );
}

/**
 * The moderator role for bbPress users
 *
 * @since bbPress (r3410)
 *
 * @uses apply_filters() Allow override of hardcoded moderator role
 * @return string
 */
function bbp_get_moderator_role() {
	return apply_filters( 'bbp_get_moderator_role', 'bbp_moderator' );
}

/**
 * The participant role for registered user that can participate in forums
 *
 * @since bbPress (r3410)
 *
 * @uses apply_filters() Allow override of hardcoded participant role
 * @return string
 */
function bbp_get_participant_role() {
	return apply_filters( 'bbp_get_participant_role', 'bbp_participant' );
}

/**
 * The spectator role is for registered users without any capabilities
 *
 * @since bbPress (r3860)
 *
 * @uses apply_filters() Allow override of hardcoded spectator role
 * @return string
 */
function bbp_get_spectator_role() {
	return apply_filters( 'bbp_get_spectator_role', 'bbp_spectator' );
}

/**
 * The anonymous role for any user without a forum role
 *
 * @since bbPress (r3860)
 *
 * @uses apply_filters() Allow override of hardcoded anonymous role
 * @return string
 */
function bbp_get_anonymous_role() {
	return apply_filters( 'bbp_get_anonymous_role', 'bbp_anonymous' );
}

/**
 * The blocked role is for registered users that cannot spectate or participate
 *
 * @since bbPress (r4284)
 *
 * @uses apply_filters() Allow override of hardcoded blocked role
 * @return string
 */
function bbp_get_blocked_role() {
	return apply_filters( 'bbp_get_blocked_role', 'bbp_blocked' );
}

/** Deprecated ****************************************************************/

/**
 * Adds bbPress-specific user roles.
 *
 * @since bbPress (r2741)
 * @deprecated since version 2.2
 */
function bbp_add_roles() {
	_doing_it_wrong( 'bbp_add_roles', __( 'Special forum roles no longer exist. Use mapped capabilities instead', 'bbpress' ), '2.2' );
}

/**
 * Removes bbPress-specific user roles.
 *
 * @since bbPress (r2741)
 * @deprecated since version 2.2
 */
function bbp_remove_roles() {
	_doing_it_wrong( 'bbp_remove_roles', __( 'Special forum roles no longer exist. Use mapped capabilities instead', 'bbpress' ), '2.2' );
}
