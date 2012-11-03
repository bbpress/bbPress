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

/**
 * Whether or not to show advanced capability editing when editing a user.
 *
 * @since bbPress (r4290)
 *
 * @param bool $default
 * @return bool
 */
function bbp_use_advanced_capability_editor( $default = false ) {
	return (bool) apply_filters( 'bbp_use_advanced_capability_editor', $default );
}

/** Output ********************************************************************/

/**
 * Return the capability groups
 *
 * @since bbPress (r4163)
 *
 * @return array of groups
 */
function bbp_get_capability_groups() {
	return apply_filters( 'bbp_get_capability_groups', array(
		'primary',
		'forums',
		'topics',
		'replies',
		'topic_tags'
	) );
}

/**
 * Return capabilities for the group
 *
 * @since bbPress (r4163)
 *
 * @param string $group
 * @return array of capabilities
 */
function bbp_get_capabilities_for_group( $group = '' ) {
	switch ( $group ) {
		case 'primary'    :
			return bbp_get_primary_capabilities();
			break;
		case 'forums'     :
			return bbp_get_forums_capabilities();
			break;
		case 'topics'     :
			return bbp_get_topics_capabilities();
			break;
		case 'replies'    :
			return bbp_get_replies_capabilities();
			break;
		case 'topic_tags' :
			return bbp_get_topic_tags_capabilities();
			break;
		default :
			return array();
			break;
	}
}

/**
 * Output the human readable capability group title
 *
 * @since bbPress (r4163)
 *
 * @param string $group
 * @uses bbp_get_capability_group_title()
 */
function bbp_capability_group_title( $group = '' ) {
	echo bbp_get_capability_group_title( $group );
}
	/**
	 * Return the human readable capability group title
	 *
	 * @since bbPress (r4163)
	 *
	 * @param string $group
	 * @return string
	 */
	function bbp_get_capability_group_title( $group = '' ) {

		// Default return value to capability group
		$retval = $group;

		switch( $group ) {
			case 'primary' :
				$retval = __( 'Primary capabilities', 'bbpress' );
				break;
			case 'forums' :
				$retval = __( 'Forum capabilities', 'bbpress' );
				break;
			case 'topics' :
				$retval = __( 'Topic capabilites', 'bbpress' );
				break;
			case 'topic_tags' :
				$retval = __( 'Topic tag capabilities', 'bbpress' );
				break;
			case 'replies' :
				$retval = __( 'Reply capabilities', 'bbpress' );
				break;
		}

		return apply_filters( 'bbp_get_capability_group_title', $retval, $group );
	}

/**
 * Output the human readable capability title
 *
 * @since bbPress (r4163)
 *
 * @param string $group
 * @uses bbp_get_capability_title()
 */
function bbp_capability_title( $capability = '' ) {
	echo bbp_get_capability_title( $capability );
}
	/**
	 * Return the human readable capability title
	 *
	 * @since bbPress (r4163)
	 *
	 * @param string $capability
	 * @return string
	 */
	function bbp_get_capability_title( $capability = '' ) {

		// Default return value to capability
		$retval = $capability;

		switch( $capability ) {

			// Primary
			case 'spectate' :
				$retval = __( 'Spectate forum discussion', 'bbpress' );
				break;
			case 'participate' :
				$retval = __( 'Participate in forums', 'bbpress' );
				break;
			case 'moderate' :
				$retval = __( 'Moderate entire forum', 'bbpress' );
				break;
			case 'throttle' :
				$retval = __( 'Skip forum throttle check', 'bbpress' );
				break;
			case 'view_trash' :
				$retval = __( 'View items in forum trash', 'bbpress' );
				break;

			// Forum caps
			case 'read_forum' :
				$retval = __( 'View forum', 'bbpress' );
				break;
			case 'edit_forum' :
				$retval = __( 'Edit forum', 'bbpress' );
				break;
			case 'trash_forum' :
				$retval = __( 'Trash forum', 'bbpress' );
				break;
			case 'delete_forum' :
				$retval = __( 'Delete forum', 'bbpress' );
				break;
			case 'moderate_forum' :
				$retval = __( 'Moderate forum', 'bbpress' );
				break;
			case 'publish_forums' :
				$retval = __( 'Create forums', 'bbpress' );
				break;
			case 'edit_forums' :
				$retval = __( 'Edit their own forums', 'bbpress' );
				break;
			case 'edit_others_forums' :
				$retval = __( 'Edit all forums', 'bbpress' );
				break;
			case 'delete_forums' :
				$retval = __( 'Delete their own forums', 'bbpress' );
				break;
			case 'delete_others_forums' :
				$retval = __( 'Delete all forums', 'bbpress' );
				break;
			case 'read_private_forums' :
				$retval = __( 'View private forums', 'bbpress' );
				break;
			case 'read_hidden_forums' :
				$retval = __( 'View hidden forums', 'bbpress' );
				break;

			// Topic caps
			case 'read_topic' :
				$retval = __( 'View topic', 'bbpress' );
				break;
			case 'edit_topic' :
				$retval = __( 'Edit topic', 'bbpress' );
				break;
			case 'trash_topic' :
				$retval = __( 'Trash topic', 'bbpress' );
				break;
			case 'moderate_topic' :
				$retval = __( 'Moderate topic', 'bbpress' );
				break;
			case 'delete_topic' :
				$retval = __( 'Delete topic', 'bbpress' );
				break;
			case 'publish_topics' :
				$retval = __( 'Create topics', 'bbpress' );
				break;
			case 'edit_topics' :
				$retval = __( 'Edit their own topics', 'bbpress' );
				break;
			case 'edit_others_topics' :
				$retval = __( 'Edit others topics', 'bbpress' );
				break;
			case 'delete_topics' :
				$retval = __( 'Delete own topics', 'bbpress' );
				break;
			case 'delete_others_topics' :
				$retval = __( 'Delete others topics', 'bbpress' );
				break;
			case 'read_private_topics' :
				$retval = __( 'View private topics', 'bbpress' );
				break;

			// Reply caps
			case 'read_reply' :
				$retval = __( 'Read reply', 'bbpress' );
				break;
			case 'edit_reply' :
				$retval = __( 'Edit reply', 'bbpress' );
				break;
			case 'trash_reply' :
				$retval = __( 'Trash reply', 'bbpress' );
				break;
			case 'delete_reply' :
				$retval = __( 'Delete reply', 'bbpress' );
				break;
			case 'publish_replies' :
				$retval = __( 'Create replies', 'bbpress' );
				break;
			case 'edit_replies' :
				$retval = __( 'Edit own replies', 'bbpress' );
				break;
			case 'edit_others_replies' :
				$retval = __( 'Edit others replies', 'bbpress' );
				break;
			case 'delete_replies' :
				$retval = __( 'Delete own replies', 'bbpress' );
				break;
			case 'delete_others_replies' :
				$retval = __( 'Delete others replies', 'bbpress' );
				break;
			case 'read_private_replies' :
				$retval = __( 'View private replies', 'bbpress' );
				break;

			// Topic tag caps
			case 'manage_topic_tags' :
				$retval = __( 'Remove tags from topics', 'bbpress' );
				break;
			case 'edit_topic_tags' :
				$retval = __( 'Edit topic tags', 'bbpress' );
				break;
			case 'delete_topic_tags' :
				$retval = __( 'Delete topic tags', 'bbpress' );
				break;
			case 'assign_topic_tags' :
				$retval = __( 'Assign tags to topics', 'bbpress' );
				break;
		}

		return apply_filters( 'bbp_get_capability_title', $retval, $capability );
	}

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

	foreach( bbp_get_editable_roles() as $role_id => $details ) {
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
function bbp_get_editable_roles() {
	return (array) apply_filters( 'bbp_get_editable_roles', array(

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
	return array_diff_assoc( $all_roles, bbp_get_editable_roles() );
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
