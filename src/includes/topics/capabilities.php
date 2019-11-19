<?php

/**
 * bbPress Topic Capabilites
 *
 * Used to map topic capabilities to WordPress's existing capabilities.
 *
 * @package bbPress
 * @subpackage Capabilities
 */

/**
 * Return topic capabilities
 *
 * @since 2.0.0 bbPress (r2593)
 *
 * @return array Topic capabilities
 */
function bbp_get_topic_caps() {

	// Filter & return
	return (array) apply_filters( 'bbp_get_topic_caps', array(
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
 * Return topic tag capabilities
 *
 * @since 2.0.0 bbPress (r2593)
 *
 *
 * @return array Topic tag capabilities
 */
function bbp_get_topic_tag_caps() {

	// Filter & return
	return (array) apply_filters( 'bbp_get_topic_tag_caps', array(
		'manage_terms' => 'manage_topic_tags',
		'edit_terms'   => 'edit_topic_tags',
		'delete_terms' => 'delete_topic_tags',
		'assign_terms' => 'assign_topic_tags'
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
function bbp_map_topic_meta_caps( $caps = array(), $cap = '', $user_id = 0, $args = array() ) {

	// What capability is being checked?
	switch ( $cap ) {

		/** Reading ***********************************************************/

		case 'read_topic' :

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

					// Get caps for post type object
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

		case 'publish_topics'  :

			// Moderators can always publish
			if ( user_can( $user_id, 'moderate' ) ) {
				$caps = array( 'moderate' );
			}

			break;

		/** Editing ***********************************************************/

		// Used primarily in wp-admin
		case 'edit_topics'        :
		case 'edit_others_topics' :

			// Moderators can always edit
			if ( user_can( $user_id, 'moderate' ) ) {
				$caps = array( $cap );

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
		case 'edit_topic' :

			// Bail if no post ID
			if ( empty( $args[0] ) ) {
				break;
			}

			// Get the post.
			$_post = get_post( $args[0] );
			if ( ! empty( $_post ) ) {

				// Get caps for post type object
				$post_type = get_post_type_object( $_post->post_type );

				// Add 'do_not_allow' cap if user is spam or deleted
				if ( bbp_is_user_inactive( $user_id ) ) {
					$caps = array( 'do_not_allow' );

				// Moderators can always edit forum content
				} elseif ( user_can( $user_id, 'moderate', $_post->ID ) ) {
					$caps = array( 'spectate' );

				// User is author so allow edit if not in admin, unless it's past edit lock time
				} elseif ( ! is_admin() && ( (int) $user_id === (int) $_post->post_author ) ) {

					// If editing...
					if ( bbp_is_topic_edit() ) {

						// Only allow if not past the edit-lock period
						$caps = ! bbp_past_edit_lock( $_post->post_date_gmt )
							? array( $post_type->cap->edit_posts )
							: array( 'do_not_allow' );

					// Otherwise...
					} else {
						$caps = array( $post_type->cap->edit_posts );
					}

				// Unknown, so map to edit_others_posts
				} else {
					$caps = array( $post_type->cap->edit_others_posts );
				}
			}

			break;

		/** Deleting **********************************************************/

		case 'delete_topic' :

			// Bail if no post ID
			if ( empty( $args[0] ) ) {
				break;
			}

			// Get the post.
			$_post = get_post( $args[0] );
			if ( ! empty( $_post ) ) {

				// Get caps for post type object
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
		case 'delete_topics'         :
		case 'delete_others_topics'  :

			// Moderators can always delete
			if ( user_can( $user_id, 'moderate' ) ) {
				$caps = array( $cap );
			}

			break;

		/** Admin *************************************************************/

		case 'bbp_topics_admin' :
			$caps = array( 'edit_topics' );
			break;
	}

	// Filter & return
	return (array) apply_filters( 'bbp_map_topic_meta_caps', $caps, $cap, $user_id, $args );
}

/**
 * Maps topic tag capabilities
 *
 * @since 2.2.0 bbPress (r4242)
 *
 * @param array $caps Capabilities for meta capability
 * @param string $cap Capability name
 * @param int $user_id User id
 * @param array $args Arguments
 *
 * @return array Actual capabilities for meta capability
 */
function bbp_map_topic_tag_meta_caps( $caps, $cap, $user_id, $args ) {

	// What capability is being checked?
	switch ( $cap ) {

		/** Assignment ********************************************************/

		case 'assign_topic_tags' :

			// Get post
			$post_id = ! empty( $args[0] )
				? get_post( $args[0] )->ID
				: 0;

			// Add 'do_not_allow' cap if user is spam or deleted
			if ( bbp_is_user_inactive( $user_id ) ) {
				$caps = array( 'do_not_allow' );

			// Moderators can always assign
			} elseif ( user_can( $user_id, 'moderate', $post_id ) ) {
				$caps = array( 'moderate' );

			// Do not allow if topic tags are disabled
			} elseif ( ! bbp_allow_topic_tags() ) {
				$caps = array( 'do_not_allow' );
			}

			break;

		/** Management ********************************************************/

		case 'manage_topic_tags' :

			// Moderators can always edit
			if ( user_can( $user_id, 'moderate' ) ) {
				$caps = array( 'moderate' );
			}

			break;

		/** Editing ***********************************************************/

		case 'edit_topic_tags' :

			// Moderators can always edit
			if ( user_can( $user_id, 'moderate' ) ) {
				$caps = array( 'moderate' );
			}

			break;

		case 'edit_topic_tag' :

			// Get the term
			$_tag = get_term( $args[0], bbp_get_topic_tag_tax_id() );
			if ( ! empty( $_tag ) ) {

				// Add 'do_not_allow' cap if user is spam or deleted
				if ( bbp_is_user_inactive( $user_id ) ) {
					$caps = array( 'do_not_allow' );

				// Moderators can always edit topic tags
				} elseif ( user_can( $user_id, 'moderate', $_tag->term_id ) ) {
					$caps = array( 'spectate' );

				// Fallback to edit_terms.
				} else {
					$taxonomy = get_taxonomy( bbp_get_topic_tag_tax_id() );
					$caps     = array( $taxonomy->cap->edit_terms );
				}
			}

			break;

		/** Deleting **********************************************************/

		case 'delete_topic_tags' :

			// Moderators can always edit
			if ( user_can( $user_id, 'moderate' ) ) {
				$caps = array( 'moderate' );
			}

			break;

		case 'delete_topic_tag' :

			// Get the term
			$_tag = get_term( $args[0], bbp_get_topic_tag_tax_id() );
			if ( ! empty( $_tag ) ) {

				// Add 'do_not_allow' cap if user is spam or deleted
				if ( bbp_is_user_inactive( $user_id ) ) {
					$caps = array( 'do_not_allow' );

				// Moderators can always delete topic tags
				} elseif ( user_can( $user_id, 'moderate', $_tag->term_id ) ) {
					$caps = array( 'spectate' );

				// Fallback to delete_terms.
				} else {
					$taxonomy = get_taxonomy( $_tag->post_type );
					$caps     = array( $taxonomy->cap->delete_terms );
				}
			}

			break;

		/** Admin *************************************************************/

		case 'bbp_topic_tags_admin' :

			// Moderators can always edit
			if ( user_can( $user_id, 'moderate' ) ) {
				$caps = array( 'moderate' );
			}

			break;
	}

	// Filter & return
	return (array) apply_filters( 'bbp_map_topic_tag_meta_caps', $caps, $cap, $user_id, $args );
}
