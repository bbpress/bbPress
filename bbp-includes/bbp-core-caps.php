<?php

/**
 * bbPress Capabilites
 *
 * @package bbPress
 * @subpackage Capabilities
 */

// Exit if accessed directly
if ( !defined( 'ABSPATH' ) ) exit;

/**
 * Adds bbPress-specific user roles.
 *
 * This is called on plugin activation.
 *
 * @since bbPress (r2741)
 *
 * @uses get_option() To get the default role
 * @uses get_role() To get the default role object
 * @uses add_role() To add our own roles
 * @uses do_action() Calls 'bbp_add_roles'
 */
function bbp_add_roles() {

	// Get new role names
	$moderator_role   = bbp_get_moderator_role();
	$participant_role = bbp_get_participant_role();

	// Add the Moderator role and add the default role caps.
	// Mod caps are added by the bbp_add_caps() function
	$default = get_role( $participant_role );

	// Moderators are default role + forum moderating caps in bbp_add_caps()
	add_role( $moderator_role,   'Forum Moderator',   $default->capabilities );

	// Forum Subscribers are auto added to sites with global forums
	add_role( $participant_role, 'Forum Participant', array( 'read' )        );

	do_action( 'bbp_add_roles' );
}

/**
 * Adds capabilities to WordPress user roles.
 *
 * This is called on plugin activation.
 *
 * @since bbPress (r2608)
 *
 * @uses get_role() To get the administrator, default and moderator roles
 * @uses WP_Role::add_cap() To add various capabilities
 * @uses do_action() Calls 'bbp_add_caps'
 */
function bbp_add_caps() {
	global $wp_roles;

	// Loop through available roles
	foreach( $wp_roles->roles as $role => $details ) {

		// Load this role
		$this_role = get_role( $role );

		// Loop through caps for this role and remove them
		foreach ( bbp_get_caps_for_role( $role ) as $cap ) {
			$this_role->add_cap( $cap );
		}
	}

	do_action( 'bbp_add_caps' );
}

/**
 * Removes capabilities from WordPress user roles.
 *
 * This is called on plugin deactivation.
 *
 * @since bbPress (r2608)
 *
 * @uses get_role() To get the administrator and default roles
 * @uses WP_Role::remove_cap() To remove various capabilities
 * @uses do_action() Calls 'bbp_remove_caps'
 */
function bbp_remove_caps() {
	global $wp_roles;

	// Loop through available roles
	foreach( $wp_roles->roles as $role => $details ) {

		// Load this role
		$this_role = get_role( $role );

		// Loop through caps for this role and remove them
		foreach ( bbp_get_caps_for_role( $role ) as $cap ) {
			$this_role->remove_cap( $cap );
		}
	}

	do_action( 'bbp_remove_caps' );
}

/**
 * Removes bbPress-specific user roles.
 *
 * This is called on plugin deactivation.
 *
 * @since bbPress (r2741)
 *
 * @uses remove_role() To remove our roles
 * @uses do_action() Calls 'bbp_remove_roles'
 */
function bbp_remove_roles() {

	// Get new role names
	$moderator_role   = bbp_get_moderator_role();
	$participant_role = bbp_get_participant_role();

	// Remove the Moderator role
	remove_role( $moderator_role );

	// Remove the Moderator role
	remove_role( $participant_role );

	do_action( 'bbp_remove_roles' );
}

/**
 * Maps forum/topic/reply caps to built in WordPress caps
 *
 * @since bbPress (r2593)
 *
 * @param array $caps Capabilities for meta capability
 * @param string $cap Capability name
 * @param int $user_id User id
 * @param mixed $args Arguments
 * @uses get_post() To get the post
 * @uses get_post_type_object() To get the post type object
 * @uses apply_filters() Calls 'bbp_map_meta_caps' with caps, cap, user id and
 *                        args
 * @return array Actual capabilities for meta capability
 */
function bbp_map_meta_caps( $caps, $cap, $user_id, $args ) {

	switch ( $cap ) {

		// Reading
		case 'read_forum' :
		case 'read_topic' :
		case 'read_reply' :

			if ( $post = get_post( $args[0] ) ) {
				$caps      = array();
				$post_type = get_post_type_object( $post->post_type );

				if ( 'private' != $post->post_status )
					$caps[] = 'read';
				elseif ( (int) $user_id == (int) $post->post_author )
					$caps[] = 'read';
				else
					$caps[] = $post_type->cap->read_private_posts;
			}

			break;

		// Editing
		case 'edit_forums' :
		case 'edit_topics' :
		case 'edit_replies' :

			// Add do_not_allow cap if user is spam or deleted
			if ( bbp_is_user_spammer( $user_id ) || bbp_is_user_deleted( $user_id ) )
				$caps = array( 'do_not_allow' );

			break;

		case 'edit_forum' :
		case 'edit_topic' :
		case 'edit_reply' :

			if ( $post = get_post( $args[0] ) ) {
				$caps      = array();
				$post_type = get_post_type_object( $post->post_type );

				// Add 'do_not_allow' cap if user is spam or deleted
				if ( bbp_is_user_spammer( $user_id ) || bbp_is_user_deleted( $user_id ) )
					$caps[] = 'do_not_allow';

				// Map to edit_posts
				elseif ( (int) $user_id == (int) $post->post_author )
					$caps[] = $post_type->cap->edit_posts;

				// Map to edit_others_posts
				else
					$caps[] = $post_type->cap->edit_others_posts;
			}

			break;

		// Deleting
		case 'delete_forum' :

			if ( $post = get_post( $args[0] ) ) {
				$caps      = array();
				$post_type = get_post_type_object( $post->post_type );

				// Add 'do_not_allow' cap if user is spam or deleted
				if ( bbp_is_user_spammer( $user_id ) || bbp_is_user_deleted( $user_id ) )
					$caps[] = 'do_not_allow';

				// Map to delete_posts
				elseif ( (int) $user_id == (int) $post->post_author )
					$caps[] = $post_type->cap->delete_posts;

				// Map to delete_others_posts
				else
					$caps[] = $post_type->cap->delete_others_posts;
			}

			break;

		case 'delete_topic' :
		case 'delete_reply' :

			if ( $post = get_post( $args[0] ) ) {
				$caps      = array();
				$post_type = get_post_type_object( $post->post_type );

				// Add 'do_not_allow' cap if user is spam or deleted
				if ( bbp_is_user_spammer( $user_id ) || bbp_is_user_deleted( $user_id ) )
					$caps[] = 'do_not_allow';

				// Map to delete_others_posts
				else
					$caps[]    = $post_type->cap->delete_others_posts;
			}

			break;
	}

	return apply_filters( 'bbp_map_meta_caps', $caps, $cap, $user_id, $args );
}

/**
 * Return forum capabilities
 *
 * @since bbPress (r2593)
 *
 * @uses apply_filters() Calls 'bbp_get_forum_caps' with the capabilities
 * @return array Forum capabilities
 */
function bbp_get_forum_caps() {

	// Forum meta caps
	$caps = array (
		'delete_posts'        => 'delete_forums',
		'delete_others_posts' => 'delete_others_forums'
	);

	return apply_filters( 'bbp_get_forum_caps', $caps );
}

/**
 * Return topic capabilities
 *
 * @since bbPress (r2593)
 *
 * @uses apply_filters() Calls 'bbp_get_topic_caps' with the capabilities
 * @return array Topic capabilities
 */
function bbp_get_topic_caps() {

	// Topic meta caps
	$caps = array (
		'delete_posts'        => 'delete_topics',
		'delete_others_posts' => 'delete_others_topics'
	);

	return apply_filters( 'bbp_get_topic_caps', $caps );
}

/**
 * Return reply capabilities
 *
 * @since bbPress (r2593)
 *
 * @uses apply_filters() Calls 'bbp_get_reply_caps' with the capabilities
 * @return array Reply capabilities
 */
function bbp_get_reply_caps () {

	// Reply meta caps
	$caps = array (
		'edit_posts'          => 'edit_replies',
		'edit_others_posts'   => 'edit_others_replies',
		'publish_posts'       => 'publish_replies',
		'read_private_posts'  => 'read_private_replies',
		'delete_posts'        => 'delete_replies',
		'delete_others_posts' => 'delete_others_replies'
	);

	return apply_filters( 'bbp_get_reply_caps', $caps );
}

/**
 * Return topic tag capabilities
 *
 * @since bbPress (r2593)
 *
 * @uses apply_filters() Calls 'bbp_get_topic_tag_caps' with the capabilities
 * @return array Topic tag capabilities
 */
function bbp_get_topic_tag_caps () {

	// Topic tag meta caps
	$caps = array (
		'manage_terms' => 'manage_topic_tags',
		'edit_terms'   => 'edit_topic_tags',
		'delete_terms' => 'delete_topic_tags',
		'assign_terms' => 'assign_topic_tags'
	);

	return apply_filters( 'bbp_get_topic_tag_caps', $caps );
}


/**
 * Returns an array of capabilities based on the role that is being requested.
 *
 * @since bbPress (r2994)
 *
 * @param string $role Optional. Defaults to The role to load caps for
 * @uses apply_filters() Allow return value to be filtered
 *
 * @return array Capabilities for $role
 */
function bbp_get_caps_for_role( $role = '' ) {

	// Get new role names
	$moderator_role   = bbp_get_moderator_role();
	$participant_role = bbp_get_participant_role();

	// Which role are we looking for?
	switch ( $role ) {

		// Administrator
		case 'administrator' :

			$caps = array(

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
				'assign_topic_tags',

				// Misc
				'moderate',
				'throttle',
				'view_trash'
			);

			break;

		// Moderator
		case $moderator_role :

			$caps = array(

				// Forum caps
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
				'assign_topic_tags',

				// Misc
				'moderate',
				'throttle',
				'view_trash',
			);

			break;

		// WordPress Core Roles
		case 'editor'          :
		case 'author'          :
		case 'contributor'     :
		case 'subscriber'      :

		// bbPress Participant Role
		case $participant_role :
		default                :

			$caps = array(

				// Topic caps
				'publish_topics',
				'edit_topics',

				// Reply caps
				'publish_replies',
				'edit_replies',

				// Topic tag caps
				'assign_topic_tags',

			);

			break;
	}

	return apply_filters( 'bbp_get_caps_for_role', $caps, $role );
}

/**
 * Give a user the default 'Forum Participant' role when creating a topic/reply
 * on a site they do not have a role or capability on.
 *
 * @since bbPress (r3410)
 *
 * @global bbPress $bbp
 *
 * @uses is_multisite()
 * @uses bbp_allow_global_access()
 * @uses bbp_is_user_deleted()
 * @uses bbp_is_user_spammer()
 * @uses is_user_logged_in()
 * @uses current_user_can()
 * @uses WP_User::set_role()
 *
 * @return If user is not spam/deleted or is already capable
 */
function bbp_global_access_auto_role() {

	// Bail if not multisite or forum is not global
	if ( !is_multisite() || !bbp_allow_global_access() )
		return;

	// Bail if user is marked as spam or is deleted
	if ( bbp_is_user_deleted() || bbp_is_user_spammer() )
		return;

	// Bail if user is not logged in
	if ( !is_user_logged_in() )
		return;

	// Give the user the 'Forum Participant' role
	if ( current_user_can( 'bbp_masked' ) ) {
		global $bbp;

		// Get the default role
		$default_role = bbp_get_participant_role();

		// Set the current users default role
		$bbp->current_user->set_role( $default_role );
	}
}

/**
 * The participant role for registered users without roles
 *
 * This is primarily for multisite compatibility when users without roles on
 * sites that have global forums enabled want to create topics and replies
 *
 * @since bbPress (r3410)
 *
 * @param string $role
 * @uses apply_filters()
 * @return string
 */
function bbp_get_participant_role() {

	// Hardcoded participant role
	$role = 'bbp_participant';

	// Allow override
	return apply_filters( 'bbp_get_participant_role', $role );
}

/**
 * The moderator role for bbPress users
 *
 * @since bbPress (r3410)
 *
 * @param string $role
 * @uses apply_filters()
 * @return string
 */
function bbp_get_moderator_role() {

	// Hardcoded moderated user role
	$role = 'bbp_moderator';

	// Allow override
	return apply_filters( 'bbp_get_moderator_role', $role );
}

/**
 * Add the default role and mapped bbPress caps to the current user if needed
 *
 * This function will bail if the forum is not global in a multisite
 * installation of WordPress, or if the user is marked as spam or deleted.
 *
 * @since bbPress (r3380)
 *
 * @uses is_multisite()
 * @uses bbp_allow_global_access()
 * @uses bbp_is_user_deleted()
 * @uses bbp_is_user_spammer()
 * @uses is_user_logged_in()
 * @uses current_user_can()
 * @uses get_option()
 * @uses bbp_get_caps_for_role()
 *
 * @global bbPress $bbp
 * @return If not multisite, not global, or user is deleted/spammed
 */
function bbp_global_access_role_mask() {

	// Bail if not multisite or forum is not global
	if ( !is_multisite() || !bbp_allow_global_access() )
		return;

	// Bail if user is marked as spam or is deleted
	if ( bbp_is_user_deleted() || bbp_is_user_spammer() )
		return;

	// Normal user is logged in but has no caps
	if ( is_user_logged_in() && !current_user_can( 'read' ) ) {

		// Assign user the minimal participant role to map caps to
		$default_role  = bbp_get_participant_role();

		// Get bbPress caps for the default role
		$caps_for_role = bbp_get_caps_for_role( $default_role );

		// Set all caps to true
		foreach ( $caps_for_role as $cap ) {
			$mapped_meta_caps[$cap] = true;
		}

		// Add 'read' cap just in case
		$mapped_meta_caps['read']       = true;
		$mapped_meta_caps['bbp_masked'] = true;

		// Allow global access caps to be manipulated
		$mapped_meta_caps = apply_filters( 'bbp_global_access_mapped_meta_caps', $mapped_meta_caps );

		// Assign the role and mapped caps to the current user
		global $bbp;
		$bbp->current_user->roles[0] = $default_role;
		$bbp->current_user->caps     = $mapped_meta_caps;
		$bbp->current_user->allcaps  = $mapped_meta_caps;
	}
}

?>
