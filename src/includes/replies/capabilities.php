<?php

/**
 * bbPress Reply Capabilites
 *
 * Used to map reply capabilities to WordPress's existing capabilities.
 *
 * @package bbPress
 * @subpackage Capabilities
 */

/**
 * Return reply capabilities
 *
 * @since 2.0.0 bbPress (r2593)
 *
 * @return array Reply capabilities
 */
function bbp_get_reply_caps() {

	// Filter & return
	return (array) apply_filters( 'bbp_get_reply_caps', array(
		'edit_posts'          => 'edit_replies',
		'edit_others_posts'   => 'edit_others_replies',
		'publish_posts'       => 'publish_replies',
		'read_private_posts'  => 'read_private_replies',
		'delete_posts'        => 'delete_replies',
		'delete_others_posts' => 'delete_others_replies'
	) );
}

/**
 * Maps topic capabilities
 *
 * @since 2.2.0 bbPress (r4242)
 *
 * @param array  $caps    Capabilities for meta capability.
 * @param string $cap     Capability name.
 * @param int    $user_id User id.
 * @param array  $args    Arguments.
 *
 * @return array Actual capabilities for meta capability
 */
function bbp_map_reply_meta_caps( $caps = array(), $cap = '', $user_id = 0, $args = array() ) {

	// What capability is being checked?
	switch ( $cap ) {

		/** Reading ***********************************************************/

		case 'read_reply' :

			// User cannot spectate
			if ( ! user_can( $user_id, 'spectate' ) ) {
				$caps = array( 'do_not_allow' );

			// Do some post ID based logic
			} else {

				// Bail if no post ID
				if ( empty( $args[0] ) ) {
					break;
				}

				// Get the post.
				$_post = get_post( $args[0] );
				if ( ! empty( $_post ) ) {

					// Get post type object
					$post_type = get_post_type_object( $_post->post_type );

					// Post is public
					if ( bbp_get_public_status_id() === $_post->post_status ) {
						$caps = array( 'spectate' );

					// User is author so allow read
					} elseif ( (int) $user_id === (int) $_post->post_author ) {
						$caps = array( 'spectate' );

					// Moderators can always edit forum content
					} elseif ( user_can( $user_id, 'moderate', $_post->ID ) ) {
						$caps = array( 'spectate' );

					// Unknown so map to private posts
					} else {
						$caps = array( $post_type->cap->read_private_posts );
					}
				}
			}

			break;

		/** Publishing ********************************************************/

		case 'publish_replies' :

			// Moderators can always publish
			if ( user_can( $user_id, 'moderate' ) ) {
				$caps = array( 'moderate' );
			}

			break;

		/** Editing ***********************************************************/

		// Used primarily in wp-admin
		case 'edit_replies'        :
		case 'edit_others_replies' :

			// Moderators can always edit
			if ( user_can( $user_id, 'moderate' ) ) {
				$caps = array( 'moderate' );

			// Otherwise, check forum
			} else {
				$forum_id = bbp_get_forum_id();

				// Moderators can always edit forum content
				if ( user_can( $user_id, 'moderate', $forum_id ) ) {
					$caps = array( 'spectate' );

				// Fallback to do_not_allow
				} else {
					$caps = array( 'do_not_allow' );
				}
			}

			break;

		// Used everywhere
		case 'edit_reply' :

			// Bail if no post ID
			if ( empty( $args[0] ) ) {
				break;
			}

			// Get the post.
			$_post = get_post( $args[0] );
			if ( ! empty( $_post ) ) {

				// Get post type object
				$post_type = get_post_type_object( $_post->post_type );

				// Add 'do_not_allow' cap if user is spam or deleted
				if ( bbp_is_user_inactive( $user_id ) ) {
					$caps = array( 'do_not_allow' );

				// Moderators can always edit forum content
				} elseif ( user_can( $user_id, 'moderate', $_post->ID ) ) {
					$caps = array( 'spectate' );

				// Allow author or mod to edit if not in admin, unless past edit lock time
				} elseif ( ! is_admin() && ( (int) $user_id === (int) $_post->post_author ) ) {

					// If editing...
					if ( bbp_is_reply_edit() ) {

						// Only allow if not past the edit-lock period
						$caps = ! bbp_past_edit_lock( $_post->post_date_gmt )
							? array( $post_type->cap->edit_posts )
							: array( 'do_not_allow' );

					// Otherwise...
					} else {
						$caps = array( $post_type->cap->edit_posts );
					}

				// Fallback to edit_others_posts.
				} else {
					$caps = array( $post_type->cap->edit_others_posts );
				}
			}

			break;

		/** Deleting **********************************************************/

		case 'delete_reply' :

			// Bail if no post ID
			if ( empty( $args[0] ) ) {
				break;
			}

			// Get the post
			$_post = get_post( $args[0] );
			if ( ! empty( $_post ) ) {

				// Get post type object
				$post_type = get_post_type_object( $_post->post_type );

				// Add 'do_not_allow' cap if user is spam or deleted
				if ( bbp_is_user_inactive( $user_id ) ) {
					$caps = array( 'do_not_allow' );

				// Moderators can always edit forum content
				} elseif ( user_can( $user_id, 'moderate', $_post->ID ) ) {
					$caps = array( 'spectate' );

				// User is author so allow delete if not in admin
				} elseif ( ! is_admin() && ( (int) $user_id === (int) $_post->post_author ) ) {
					$caps = array( $post_type->cap->delete_posts );

				// Unknown so map to delete_others_posts
				} else {
					$caps = array( $post_type->cap->delete_others_posts );
				}
			}

			break;

		// Moderation override
		case 'delete_replies'        :
		case 'delete_others_replies' :

			// Moderators can always delete
			if ( user_can( $user_id, 'moderate' ) ) {
				$caps = array( 'moderate' );
			}

			break;

		/** Admin *************************************************************/

		case 'bbp_replies_admin' :
			$caps = array( 'edit_replies' );
			break;
	}

	// Filter & return
	return (array) apply_filters( 'bbp_map_reply_meta_caps', $caps, $cap, $user_id, $args );
}
