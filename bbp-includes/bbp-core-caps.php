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

	// Add the Moderator role and add the default role caps. Mod caps are added by the bbp_add_caps () function
	$default = get_role( get_option( 'default_role' ) );

	// Moderators are default role + forum moderating caps in bbp_add_caps()
	add_role( 'bbp_moderator', __( 'Forum Moderator', 'bbpress' ), $default->capabilities );

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

	// Remove the Moderator role
	remove_role( 'bbp_moderator' );

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
		case 'edit_forum' :
		case 'edit_topic' :
		case 'edit_reply' :

			if ( $post = get_post( $args[0] ) ) {
				$caps      = array();
				$post_type = get_post_type_object( $post->post_type );

				if ( (int) $user_id == (int) $post->post_author )
					$caps[] = $post_type->cap->edit_posts;
				else
					$caps[] = $post_type->cap->edit_others_posts;
			}

			break;

		// Deleting
		case 'delete_forum' :

			if ( $post = get_post( $args[0] ) ) {
				$caps      = array();
				$post_type = get_post_type_object( $post->post_type );

				if ( (int) $user_id == (int) $post->post_author )
					$caps[] = $post_type->cap->delete_posts;
				else
					$caps[] = $post_type->cap->delete_others_posts;
			}

			break;

		case 'delete_topic' :
		case 'delete_reply' :

			if ( $post = get_post( $args[0] ) ) {
				$caps      = array();
				$post_type = get_post_type_object( $post->post_type );
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
		case 'bbp_moderator' :

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

		// Other specific roles
		case 'editor'      :
		case 'author'      :
		case 'contributor' :
		case 'subscriber'  :
		default            :

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

?>
