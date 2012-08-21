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
 * Get the capability groups
 *
 * @since bbPress (r4163)
 *
 * @return array of groups
 */
function bbp_get_capability_groups() {
	return apply_filters( 'bbp_get_capability_groups', array(
		'general',
		'forums',
		'topics',
		'replies',
		'topic_tags'
	) );
}

/**
 * Get capabilities for the group
 *
 * @since bbPress (r4163)
 *
 * @param string $group
 * @return array of capabilities
 */
function bbp_get_capabilities_for_group( $group = '' ) {
	switch ( $group ) {
		case 'general'    :
			return bbp_get_general_capabilities();
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
 * Get the general forum capabilities
 *
 * @since bbPress (r4163)
 *
 * @return array of general capabilities
 */
function bbp_get_general_capabilities() {
	return apply_filters( 'bbp_get_general_capabilities', array( 
		'moderate',
		'throttle',
		'view_trash',
		'bozo',
		'blocked'
	) );
}

/**
 * Get the forum post-type capabilities
 *
 * @since bbPress (r4163)
 *
 * @return array of forums capabilities
 */
function bbp_get_forums_capabilities() {
	return apply_filters( 'bbp_get_forums_capabilities', array( 
		'publish_forums',
		'edit_forums',
		'edit_others_forums',
		'delete_forums',
		'delete_others_forums',
		'read_private_forums',
		'read_hidden_forums'
	) );
}

/**
 * Get the topic post-type capabilities
 *
 * @since bbPress (r4163)
 *
 * @return array of topics capabilities
 */
function bbp_get_topics_capabilities() {
	return apply_filters( 'bbp_get_topics_capabilities', array( 
		'publish_topics',
		'edit_topics',
		'edit_others_topics',
		'delete_topics',
		'delete_others_topics',
		'read_private_topics'
	) );
}

/**
 * Get the topic-tag taxonomy capabilities
 *
 * @since bbPress (r4163)
 *
 * @return array of topic-tag capabilities
 */
function bbp_get_topic_tags_capabilities() {
	return apply_filters( 'bbp_get_topic_tags_capabilities', array( 
		'manage_topic_tags',
		'edit_topic_tags',
		'delete_topic_tags',
		'assign_topic_tags'
	) );
}

/**
 * Get the reply post-type capabilities
 *
 * @since bbPress (r4163)
 *
 * @return array of replies capabilities
 */
function bbp_get_replies_capabilities() {
	return apply_filters( 'bbp_get_replies_capabilities', array( 
		'publish_replies',
		'edit_replies',
		'edit_others_replies',
		'delete_replies',
		'delete_others_replies',
		'read_private_replies'
	) );
}

/** Output ********************************************************************/

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
			case 'general' :
				$retval = __( 'General capabilities', 'bbpress' );
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
			case 'moderate' :
				$retval = __( 'Moderate entire forum', 'bbpress' );
				break;
			case 'throttle' :
				$retval = __( 'Skip forum throttle check', 'bbpress' );
				break;
			case 'view_trash' :
				$retval = __( 'View items in forum trash', 'bbpress' );
				break;
			case 'bozo' :
				$retval = __( 'User is a forum bozo', 'bbpress' );
				break;
			case 'blocked' :
				$retval = __( 'User is blocked', 'bbpress' );
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

	// What capability is being checked?
	switch ( $cap ) {

		/** Reading ***********************************************************/

		case 'read_forum' :
		case 'read_topic' :
		case 'read_reply' :

			// Get the post
			$_post = get_post( $args[0] );
			if ( !empty( $_post ) ) {

				// Get caps for post type object
				$post_type = get_post_type_object( $_post->post_type );
				$caps      = array();

				// Post is public
				if ( bbp_get_public_status_id() == $_post->post_status ) {
					$caps[] = 'read';

				// User is author so allow read
				} elseif ( (int) $user_id == (int) $_post->post_author ) {
					$caps[] = 'read';

				// Unknown so map to private posts
				} else {
					$caps[] = $post_type->cap->read_private_posts;
				}
			}

			break;

		/** Publishing ********************************************************/

		case 'publish_forums'  :
		case 'publish_topics'  :
		case 'publish_replies' :

			// Add do_not_allow cap if user is spam or deleted
			if ( bbp_is_user_inactive( $user_id ) )
				$caps = array( 'do_not_allow' );

			break;

		/** Editing ***********************************************************/

		// Used primarily in wp-admin
		case 'edit_forums' :
		case 'edit_topics' :
		case 'edit_replies' :

			// Add do_not_allow cap if user is spam or deleted
			if ( bbp_is_user_inactive( $user_id ) )
				$caps = array( 'do_not_allow' );

			break;

		// Used everywhere
		case 'edit_forum' :
		case 'edit_topic' :
		case 'edit_reply' :

			// Get the post
			$_post = get_post( $args[0] );
			if ( !empty( $_post ) ) {

				// Get caps for post type object
				$post_type = get_post_type_object( $_post->post_type );
				$caps      = array();

				// Add 'do_not_allow' cap if user is spam or deleted
				if ( bbp_is_user_inactive( $user_id ) ) {
					$caps[] = 'do_not_allow';

				// User is author so allow edit
				} elseif ( (int) $user_id == (int) $_post->post_author ) {
					$caps[] = $post_type->cap->edit_posts;

				// Unknown, so map to edit_others_posts
				} else {
					$caps[] = $post_type->cap->edit_others_posts;
				}
			}

			break;

		/** Deleting **********************************************************/

		// Allow forum authors to delete forums (for BuddyPress groups, etc)
		case 'delete_forum' :

			// Get the post
			$_post = get_post( $args[0] );
			if ( !empty( $_post ) ) {

				// Get caps for post type object
				$post_type = get_post_type_object( $_post->post_type );
				$caps      = array();

				// Add 'do_not_allow' cap if user is spam or deleted
				if ( bbp_is_user_inactive( $user_id ) ) {
					$caps[] = 'do_not_allow';

				// User is author so allow to delete
				} elseif ( (int) $user_id == (int) $_post->post_author ) {
					$caps[] = $post_type->cap->delete_posts;

				// Unknown so map to delete_others_posts
				} else {
					$caps[] = $post_type->cap->delete_others_posts;
				}
			}

			break;

		case 'delete_topic' :
		case 'delete_reply' :

			// Get the post
			$_post = get_post( $args[0] );
			if ( !empty( $_post ) ) {

				// Get caps for post type object
				$post_type = get_post_type_object( $_post->post_type );
				$caps      = array();

				// Add 'do_not_allow' cap if user is spam or deleted
				if ( bbp_is_user_inactive( $user_id ) ) {
					$caps[] = 'do_not_allow';

				// Unknown so map to delete_others_posts
				} else {
					$caps[] = $post_type->cap->delete_others_posts;
				}
			}

			break;
	}

	return apply_filters( 'bbp_map_meta_caps', $caps, $cap, $user_id, $args );
}

/** Post Types and Taxonomies *************************************************/

/**
 * Return forum capabilities
 *
 * @since bbPress (r2593)
 *
 * @uses apply_filters() Calls 'bbp_get_forum_caps' with the capabilities
 * @return array Forum capabilities
 */
function bbp_get_forum_caps() {
	return apply_filters( 'bbp_get_forum_caps', array (
		'edit_posts'          => 'edit_forums',
		'edit_others_posts'   => 'edit_others_forums',
		'publish_posts'       => 'publish_forums',
		'read_private_posts'  => 'read_private_forums',
		'read_hidden_posts'   => 'read_hidden_forums',
		'delete_posts'        => 'delete_forums',
		'delete_others_posts' => 'delete_others_forums'
	) );
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
	return apply_filters( 'bbp_get_topic_caps', array (
		'edit_posts'          => 'edit_topics',
		'edit_others_posts'   => 'edit_others_topics',
		'publish_posts'       => 'publish_topics',
		'read_private_posts'  => 'read_private_topics',
		'read_hidden_posts'   => 'read_hidden_topics',
		'delete_posts'        => 'delete_topics',
		'delete_others_posts' => 'delete_others_topics'
	) );
}

/**
 * Return reply capabilities
 *
 * @since bbPress (r2593)
 *
 * @uses apply_filters() Calls 'bbp_get_reply_caps' with the capabilities
 * @return array Reply capabilities
 */
function bbp_get_reply_caps() {
	return apply_filters( 'bbp_get_reply_caps', array (
		'edit_posts'          => 'edit_replies',
		'edit_others_posts'   => 'edit_others_replies',
		'publish_posts'       => 'publish_replies',
		'read_private_posts'  => 'read_private_replies',
		'delete_posts'        => 'delete_replies',
		'delete_others_posts' => 'delete_others_replies'
	) );
}

/**
 * Return topic tag capabilities
 *
 * @since bbPress (r2593)
 *
 * @uses apply_filters() Calls 'bbp_get_topic_tag_caps' with the capabilities
 * @return array Topic tag capabilities
 */
function bbp_get_topic_tag_caps() {
	return apply_filters( 'bbp_get_topic_tag_caps', array (
		'manage_terms' => 'manage_topic_tags',
		'edit_terms'   => 'edit_topic_tags',
		'delete_terms' => 'delete_topic_tags',
		'assign_terms' => 'assign_topic_tags'
	) );
}

/** Roles *********************************************************************/

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

	// No role
	if ( empty( $role ) ) {
		$caps = array(
			'blocked'
		);

	// Which role are we looking for?
	} else {
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

			// Any other role
			case 'editor'      :
			case 'author'      :
			case 'contributor' :
			case 'subscriber'  :
			default            :
				$caps = array(

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
	}

	return apply_filters( 'bbp_get_caps_for_role', $caps, $role );
}

/**
 * Give a user the default role when creating a topic/reply on a site they do
 * not have a role on.
 *
 * @since bbPress (r3410)
 *
 * @uses bbp_allow_global_access()
 * @uses bbp_is_user_inactive()
 * @uses is_user_logged_in()
 * @uses current_user_can()
 * @uses WP_User::set_role()
 *
 * @return If user is not spam/deleted or is already capable
 */
function bbp_global_access_auto_role() {

	// Bail if forum is not global
	if ( ! bbp_allow_global_access() )
		return;

	// Bail if user is not active
	if ( bbp_is_user_inactive() )
		return;

	// Bail if user is not logged in
	if ( !is_user_logged_in() )
		return;

	// Give the user the default role
	if ( current_user_can( 'bbp_masked' ) ) {
		bbpress()->current_user->set_role( get_option( 'default_role' ) );
	}
}

/**
 * Add the default role and mapped bbPress caps to the current user if needed
 *
 * This function will bail if the forum is not global in a multisite
 * installation of WordPress, or if the user is marked as spam or deleted.
 *
 * @since bbPress (r3380)
 *
 * @uses bbp_allow_global_access()
 * @uses bbp_is_user_inactive()
 * @uses is_user_logged_in()
 * @uses current_user_can()
 * @uses get_option()
 * @uses bbp_get_caps_for_role()
 *
 * @return If not multisite, not global, or user is deleted/spammed
 */
function bbp_global_access_role_mask() {

	// Bail if forum is not global
	if ( ! bbp_allow_global_access() )
		return;

	// Bail if user is marked as spam or is deleted
	if ( bbp_is_user_inactive() )
		return;

	// Normal user is logged in but not a member of this site
	if ( is_user_logged_in() && ! is_user_member_of_blog() ) {

		// Assign user the default role to map caps to
		$default_role  = get_option( 'default_role' );

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
		$bbp = bbpress();
		$bbp->current_user->roles[0] = $default_role;
		$bbp->current_user->caps     = $mapped_meta_caps;
		$bbp->current_user->allcaps  = $mapped_meta_caps;
	}
}

/**
 * Can the current user see a specific UI element?
 *
 * Used when registering post-types and taxonomies to decide if 'show_ui' should
 * be set to true or false. Also used for fine-grained control over which admin
 * sections are visible under what conditions.
 *
 * This function is in bbp-core-caps.php rather than in /bbp-admin so that it
 * can be used during the bbp_register_post_types action.
 *
 * @since bbPress (r3944)
 *
 * @uses current_user_can() To check the 'moderate' capability
 * @uses bbp_get_forum_post_type()
 * @uses bbp_get_topic_post_type()
 * @uses bbp_get_reply_post_type()
 * @uses bbp_get_topic_tag_tax_id()
 * @uses is_plugin_active()
 * @uses is_super_admin()
 * @return bool Results of current_user_can( 'moderate' ) check.
 */
function bbp_current_user_can_see( $component = '' ) {

	// Define local variable
	$retval = false;

	// Which component are we checking UI visibility for?
	switch ( $component ) {

		/** Everywhere ********************************************************/

		case bbp_get_forum_post_type()   : // Forums
		case bbp_get_topic_post_type()   : // Topics
		case bbp_get_reply_post_type()   : // Replies
		case bbp_get_topic_tag_tax_id()  : // Topic-Tags
			$retval = current_user_can( 'moderate' );
			break;

		/** Admin Exclusive ***************************************************/

		case 'bbp_settings_buddypress'  : // BuddyPress Extension
			$retval = ( is_plugin_active( 'buddypress/bp-loader.php' ) && defined( 'BP_VERSION' ) ) && is_super_admin();
			break;

		case 'bbp_settings_akismet'     : // Akismet Extension
			$retval = ( is_plugin_active( 'akismet/akismet.php' ) && defined( 'AKISMET_VERSION' ) ) && is_super_admin();
			break;

		case 'bbp_tools_page'            : // Tools Page
		case 'bbp_tools_repair_page'     : // Tools - Repair Page
		case 'bbp_tools_import_page'     : // Tools - Import Page
		case 'bbp_tools_reset_page'      : // Tools - Reset Page
		case 'bbp_settings?page'         : // Settings Page
		case 'bbp_settings_main'         : // Settings - General
		case 'bbp_settings_theme_compat' : // Settings - Theme compat
		case 'bbp_settings_root_slugs'   : // Settings - Root slugs
		case 'bbp_settings_single_slugs' : // Settings - Single slugs
		case 'bbp_settings_per_page'     : // Settings - Single slugs
		case 'bbp_settings_per_page_rss' : // Settings - Single slugs
		default                          : // Anything else
			$retval = current_user_can( bbpress()->admin->minimum_capability );
			break;
	}

	return (bool) apply_filters( 'bbp_current_user_can_see', (bool) $retval, $component );
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
 * Adds capabilities to WordPress user roles.
 *
 * @since bbPress (r2608)
 * @deprecated since version 2.2
 */
function bbp_add_caps() {
	_doing_it_wrong( 'bbp_add_caps', __( 'Use mapped capabilities instead', 'bbpress' ), '2.2' );
}

/**
 * Removes capabilities from WordPress user roles.
 *
 * @since bbPress (r2608)
 * @deprecated since version 2.2
 */
function bbp_remove_caps() {
	_doing_it_wrong( 'bbp_remove_caps', __( 'Special forum roles no longer exist. Use mapped capabilities instead', 'bbpress' ), '2.2' );
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
