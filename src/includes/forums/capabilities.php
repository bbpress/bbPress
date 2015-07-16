<?php

/**
 * bbPress Forum Capabilites
 *
 * Used to map forum capabilities to WordPress's existing capabilities.
 *
 * @package bbPress
 * @subpackage Capabilities
 */

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
 * Maps forum capabilities
 *
 * @since bbPress (r4242)
 *
 * @param array $caps Capabilities for meta capability
 * @param string $cap Capability name
 * @param int $user_id User id
 * @param array $args Arguments
 * @uses get_post() To get the post
 * @uses get_post_type_object() To get the post type object
 * @uses apply_filters() Filter capability map results
 * @return array Actual capabilities for meta capability
 */
function bbp_map_forum_meta_caps( $caps = array(), $cap = '', $user_id = 0, $args = array() ) {

	// What capability is being checked?
	switch ( $cap ) {

		/** Reading ***********************************************************/

		case 'read_private_forums' :
		case 'read_hidden_forums'  :

			// Moderators can always read private/hidden forums
			if ( user_can( $user_id, 'moderate' ) ) {
				$caps = array( 'moderate' );
			}

			break;

		case 'read_forum' :

			// User cannot spectate
			if ( ! user_can( $user_id, 'spectate' ) ) {
				$caps = array( 'do_not_allow' );

			// Do some post ID based logic
			} else {

				// Get the post
				$_post = get_post( $args[0] );
				if ( ! empty( $_post ) ) {

					// Get caps for post type object
					$post_type = get_post_type_object( $_post->post_type );

					// Post is public
					if ( bbp_get_public_status_id() === $_post->post_status ) {
						$caps = array( 'spectate' );

					// User is author so allow read
					} elseif ( (int) $user_id === (int) $_post->post_author ) {
						$caps = array( 'spectate' );

					// Unknown so map to private posts
					} else {
						$caps = array( $post_type->cap->read_private_posts );
					}
				}
			}

			break;

		/** Publishing ********************************************************/

		case 'publish_forums'  :

			// Moderators can always edit
			if ( user_can( $user_id, 'moderate' ) ) {
				$caps = array( 'moderate' );
			}

			break;

		/** Editing ***********************************************************/

		// Used primarily in wp-admin
		case 'edit_forums'         :
		case 'edit_others_forums'  :

			// Moderators can always edit
			if ( user_can( $user_id, 'keep_gate' ) ) {
				$caps = array( 'keep_gate' );

			// Otherwise, block
			} else {
				$caps = array( 'do_not_allow' );
			}

			break;

		// Used everywhere
		case 'edit_forum' :

			// Get the post
			$_post = get_post( $args[0] );
			if ( ! empty( $_post ) ) {

				// Get caps for post type object
				$post_type = get_post_type_object( $_post->post_type );
				$caps      = array();

				// Add 'do_not_allow' cap if user is spam or deleted
				if ( bbp_is_user_inactive( $user_id ) ) {
					$caps[] = 'do_not_allow';

				// User is author so allow edit if not in admin
				} elseif ( !is_admin() && ( (int) $user_id === (int) $_post->post_author ) ) {
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
			if ( ! empty( $_post ) ) {

				// Get caps for post type object
				$post_type = get_post_type_object( $_post->post_type );
				$caps      = array();

				// Add 'do_not_allow' cap if user is spam or deleted
				if ( bbp_is_user_inactive( $user_id ) ) {
					$caps[] = 'do_not_allow';

				// User is author so allow to delete
				} elseif ( (int) $user_id === (int) $_post->post_author ) {
					$caps[] = $post_type->cap->delete_posts;

				// Unknown so map to delete_others_posts
				} else {
					$caps[] = $post_type->cap->delete_others_posts;
				}
			}

			break;

		/** Admin *************************************************************/

		// Forum admin area.
		case 'bbp_forums_admin' :
			$caps = array( 'keep_gate' );
			break;

		// Forum moderator admin area.
		case 'bbp_forum_mods_admin' :
			$caps = array( 'keep_gate' );
			break;
	}

	return apply_filters( 'bbp_map_forum_meta_caps', $caps, $cap, $user_id, $args );
}

/**
 * Return forum moderator capabilities
 *
 * @since bbPress (r5834)
 *
 * @uses apply_filters() Calls 'bbp_get_forum_mod_caps' with the capabilities
 *
 * @return array Forum mod capabilities.
 */
function bbp_get_forum_mod_caps() {
	return apply_filters( 'bbp_get_forum_mod_caps', array(
		'manage_terms' => 'keep_gate',
		'edit_terms'   => 'keep_gate',
		'delete_terms' => 'keep_gate',
		'assign_terms' => 'keep_gate',
	) );
}

/**
 * Maps forum moderator capabilities
 *
 * @since bbPress (r5834)
 *
 * @param array  $caps Capabilities for meta capability.
 * @param string $cap Capability name.
 * @param int    $user_id User id.
 * @param mixed  $args Arguments.
 * @uses apply_filters() Filter capabilities map results.
 *
 * @return array Actual capabilities for meta capability.
 */
function bbp_map_forum_mod_meta_caps( $caps, $cap, $user_id, $args ) {

	// What capability is being checked?
	switch ( $cap ) {
		case 'manage_forum_mods'    :
		case 'edit_forum_mods'      :
		case 'delete_forum_mods'    :
		case 'assign_forum_mods'    :
		case 'bbp_forum_mods_admin' :

			// Key Masters can always edit.
			if ( user_can( $user_id, 'keep_gate' ) ) {
				$caps = array( 'keep_gate' );
			}
	}

	return apply_filters( 'bbp_map_forum_mod_meta_caps', $caps, $cap, $user_id, $args );
}

/**
 * Get moderators of a forum
 *
 * @since bbPress (r5834)
 *
 * @param int $forum_id Forum id.
 * @uses bbp_get_forum_id() To get the forum id
 * @uses bbp_is_forum() To make sure it is a forum
 * @uses bbp_get_forum_mod_tax_id() To get the forum moderator taxonomy
 * @uses bbp_get_forum_mods() To get the forum's moderator terms
 * @uses bbp_get_term_taxonomy_user_id() To convert terms to user ids
 *
 * @return boolean|array Return false on error or empty, or array of user ids
 */
function bbp_get_forum_moderator_ids( $forum_id = 0 ) {

	// Bail if no forum ID.
	$forum_id = bbp_get_forum_id( $forum_id );
	if ( empty( $forum_id ) ) {
		return false;
	}

	// Bail if forum does not exist.
	if ( ! bbp_is_forum( $forum_id ) ) {
		return false;
	}

	// Get forum taxonomy terms.
	$terms = bbp_get_forum_mods( $forum_id );

	// Bail if no terms found.
	if ( empty( $terms ) ) {
		return false;
	}

	// Setup default values
	$term_ids      = wp_parse_id_list( $terms );
	$taxonomy      = bbp_get_forum_mod_tax_id();
	$moderator_ids = array();

	// Convert term ids to user ids.
	foreach ( $term_ids as $term_id ) {
		$moderator_ids[] = bbp_get_term_taxonomy_user_id( $term_id, $taxonomy );
	}

	// Remove empties
	$retval = wp_parse_id_list( array_filter( $moderator_ids ) );

	// Filter & return
	return apply_filters( 'bbp_get_forum_moderator_ids', $retval, $forum_id );
}

/**
 * Get forums of a moderator
 *
 * @since bbPress (r5834)
 *
 * @param int $user_id User id.
 * @uses get_userdata() To get the user object
 * @uses bbp_get_forum_mod_tax_id() To get the forum moderator taxonomy
 * @uses bbp_get_user_taxonomy_term_id() To get the user taxonomy term id
 * @uses get_term_by() To get the term id
 * @uses get_objects_in_term() Get the forums the user moderates
 * @uses is_wp_error() To check for errors
 * @uses bbp_is_forum() To make sure the objects are forums
 *
 * @return boolean|array Return false on error or empty, or array of forum ids
 */
function bbp_get_moderator_forum_ids( $user_id = 0 ) {

	// Bail if no user ID.
	$user_id = bbp_get_user_id( $user_id );
	if ( empty( $user_id ) ) {
		return false;
	}

	// Bail if user does not exist.
	$user = get_userdata( $user_id );
	if ( empty( $user ) ) {
		return false;
	}

	// Convert user id to term id.
	$taxonomy = bbp_get_forum_mod_tax_id();
	$term_id  = bbp_get_user_taxonomy_term_id( $user_id, $taxonomy );

	// Get moderator forums.
	$forums   = get_objects_in_term( $term_id, $taxonomy );

	// Forums found.
	if ( empty( $forums ) || is_wp_error( $forums ) ) {
		return false;
	}

	// Make sure the ids returned are forums.
	$forum_ids = array();
	foreach ( $forums as $forum_id ) {
		if ( bbp_is_forum( $forum_id ) ) {
			$forum_ids[] = $forum_id;
		}
	}

	// Remove empties
	$retval = wp_parse_id_list( array_filter( $forum_ids ) );

	// Filter & return
	return apply_filters( 'bbp_get_moderator_forum_ids', $retval, $user_id );
}

/**
 * Can a user moderate a forum?
 *
 * @since bbPress (r5834)
 *
 * @param int $user_id User id.
 * @param int $forum_id Forum id.
 * @uses bbp_get_user_id()
 * @uses bbp_get_forum_id()
 * @uses bbp_get_moderator_forum_ids()
 * @uses apply_filters() Calls 'bbp_is_user_forum_mod' with the forums
 *
 * @return bool Return true if user is moderator of forum
 */
function bbp_is_user_forum_mod( $user_id = 0, $forum_id = 0 ) {

	// Assume user cannot moderate the forum.
	$retval    = false;

	// Validate user ID - fallback to current user if no ID passed.
	$user_id   = bbp_get_user_id( $user_id, false, ! empty( $user_id ) );
	$forum_id  = bbp_get_forum_id( $forum_id );

	// Get forums the user can moderate.
	$forum_ids = bbp_get_moderator_forum_ids( $user_id );

	// Is this forum ID in the users array of forum IDs?
	if ( ! empty( $forum_ids ) ) {
		$retval = in_array( $forum_id, $forum_ids );
	}

	return (bool) apply_filters( 'bbp_is_user_forum_mod', $retval, $user_id, $forum_id, $forum_ids );
}
