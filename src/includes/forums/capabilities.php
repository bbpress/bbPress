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
 * @since 2.0.0 bbPress (r2593)
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
 * @since 2.2.0 bbPress (r4242)
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
				} elseif ( ! is_admin() && ( (int) $user_id === (int) $_post->post_author ) ) {
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
	}

	return apply_filters( 'bbp_map_forum_meta_caps', $caps, $cap, $user_id, $args );
}

/**
 * Get array of forum IDs that a user can moderate
 *
 * @since 2.6.0 bbPress (r5834)
 *
 * @param int $user_id User id.
 *
 * @return boolean|array Return false on error or empty, or array of forum ids
 */
function bbp_get_moderator_forum_ids( $user_id = 0 ) {
	$user_id = bbp_get_user_id( $user_id );
	$forums  = new WP_Query( array(
		'fields'        => 'ids',
		'post_type'     => bbp_get_forum_post_type(),
		'nopaging'      => true,
		'no_found_rows' => true,
		'meta_query'    => array( array(
			'key'     => '_bbp_moderator_id',
			'value'   => $user_id,
			'compare' => 'NUMERIC'
		) )
	) );

	// Filter & return
	return (array) apply_filters( 'bbp_get_moderator_forum_ids', $forums->posts, $user_id, $forums );
}

/**
 * Can a user moderate a forum?
 *
 * @since 2.6.0 bbPress (r5834)
 *
 * @param int $user_id User id.
 * @param int $forum_id Forum id.
 * @uses bbp_get_user_id()
 * @uses bbp_get_forum_id()
 * @uses bbp_get_moderator_forum_ids()
 * @uses apply_filters() Calls 'bbp_is_user_forum_moderator' with the forums
 *
 * @return bool Return true if user is moderator of forum
 */
function bbp_is_user_forum_moderator( $user_id = 0, $forum_id = 0 ) {

	// Assume user cannot moderate the forum.
	$retval    = false;

	// Validate user ID - fallback to current user if no ID passed.
	$user_id   = bbp_get_user_id( $user_id, false, ! empty( $user_id ) );
	$forum_id  = bbp_get_forum_id( $forum_id );
	$forum_ids = array();

	// Only check if per-forum moderation is enabled
	if ( bbp_allow_forum_mods() ) {

		// Get forums the user can moderate.
		$forum_ids = bbp_get_moderator_forum_ids( $user_id );

		// Is this forum ID in the users array of forum IDs?
		if ( ! empty( $forum_ids ) ) {
			$retval = in_array( $forum_id, $forum_ids );
		}
	}

	return (bool) apply_filters( 'bbp_is_user_forum_moderator', $retval, $user_id, $forum_id, $forum_ids );
}
