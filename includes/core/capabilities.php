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

			// Misc
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

		// Administrator
		case 'administrator' :
			$caps = array(

				// Primary caps
				'participate',
				'moderate',
				'throttle',
				'view_trash',

				// Forum caps
				'publish_forums',
				'edit_forums',
				'edit_others_forums',
				'delete_forums',
				'delete_others_forums',
				'read_private_forums',
				'read_hidden_forums',

				// Topic caps
				'publish_topics',
				'edit_topics',
				'edit_others_topics',
				'delete_topics',
				'delete_others_topics',
				'read_private_topics',

				// Reply caps
				'publish_replies',
				'edit_replies',
				'edit_others_replies',
				'delete_replies',
				'delete_others_replies',
				'read_private_replies',

				// Topic tag caps
				'manage_topic_tags',
				'edit_topic_tags',
				'delete_topic_tags',
				'assign_topic_tags'
			);

			break;

		// Any other role
		default :
			$caps = array(

				// Primary caps
				'participate',

				// Forum caps
				'read_private_forums',

				// Topic caps
				'publish_topics',
				'edit_topics',

				// Reply caps
				'publish_replies',
				'edit_replies',

				// Topic tag caps
				'assign_topic_tags'
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
	global $wp_roles;

	// Load roles if not set
	if ( ! isset( $wp_roles ) )
		$wp_roles = new WP_Roles();

	// Loop through available roles and add caps
	foreach( $wp_roles->role_objects as $role ) {
		foreach ( bbp_get_caps_for_role( $role->name ) as $cap ) {
			$role->add_cap( $cap );
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
	global $wp_roles;

	// Load roles if not set
	if ( ! isset( $wp_roles ) )
		$wp_roles = new WP_Roles();

	// Loop through available roles and remove caps
	foreach( $wp_roles->role_objects as $role ) {
		foreach ( bbp_get_caps_for_role( $role->name ) as $cap ) {
			$role->remove_cap( $cap );
		}
	}

	do_action( 'bbp_remove_caps' );
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

/**
 * The anonymous role for unregistered users
 *
 * @since bbPress (r3860)
 *
 * @deprecated since version 2.2
 */
function bbp_get_anonymous_role() {
	_doing_it_wrong( 'bbp_get_anonymous_role', __( 'Special forum roles no longer exist. Use mapped capabilities instead', 'bbpress' ), '2.2' );
}

/**
 * The participant role for registered users without roles
 *
 * @since bbPress (r3410)
 *
 * @deprecated since version 2.2
 */
function bbp_get_participant_role() {
	_doing_it_wrong( 'bbp_get_participant_role', __( 'Special forum roles no longer exist. Use mapped capabilities instead', 'bbpress' ), '2.2' );
}

/**
 * The moderator role for bbPress users
 *
 * @since bbPress (r3410)
 *
 * @deprecated since version 2.2
 */
function bbp_get_moderator_role() {
	_doing_it_wrong( 'bbp_get_moderator_role', __( 'Special forum roles no longer exist. Use mapped capabilities instead', 'bbpress' ), '2.2' );
}
