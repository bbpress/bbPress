<?php

/**
 * bbPress XML-RPC Functions.
 *
 * @package bbPress
 * @subpackage Common
 */

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

/**
 * Validate an XML-RPC edit against bbPress posting rules.
 *
 * @since 2.6.16 bbPress
 *
 * @param string $method XML-RPC method name.
 * @param array  $args   XML-RPC method arguments.
 */
function bbp_validate_xmlrpc_post( $method = '', $args = array() ) {
	global $bbp_xmlrpc_error_post_id;

	$bbp_xmlrpc_error_post_id = 0;

	$is_restore = ( 'wp.restoreRevision' === $method );

	// Validate an edit or resolve its revision back to a bbPress post
	if ( 'wp.editPost' === $method ) {
		if ( empty( $args[3] ) || empty( $args[4] ) || ! is_array( $args[4] ) ) {
			return;
		}

		$post_id   = (int) $args[3];
		$post_data = $args[4];
	} elseif ( $is_restore && ! empty( $args[3] ) ) {
		$revision_id = (int) $args[3];
		$revision    = wp_get_post_revision( $revision_id );

		if ( empty( $revision ) ) {
			return;
		}

		$post_id   = (int) $revision->post_parent;
		$post_data = array(
			'post_title'   => $revision->post_title,
			'post_content' => $revision->post_content,
		);
	} else {
		return;
	}

	$post = get_post( $post_id );

	if ( empty( $post ) ) {
		return;
	}

	$post_type = $post->post_type;

	if ( ! in_array( $post_type, array( bbp_get_forum_post_type(), bbp_get_topic_post_type(), bbp_get_reply_post_type() ), true ) ) {
		return;
	}

	$post_parent = isset( $post_data['post_parent'] )
		? (int) $post_data['post_parent']
		: (int) $post->post_parent;
	$parent_changed = ( $post_parent !== (int) $post->post_parent );
	$status_changed = isset( $post_data['post_status'] ) && ( $post_data['post_status'] !== $post->post_status );
	$order_changed  = isset( $post_data['menu_order'] ) && ( (int) $post_data['menu_order'] !== (int) $post->menu_order );
	$invalid        = false;

	// Validate forum structure and visibility changes
	if ( bbp_get_forum_post_type() === $post_type ) {
		if ( ! $is_restore && $parent_changed && ! current_user_can( 'assign_moderators' ) ) {
			$invalid = true;
		}

		if ( ! $is_restore && $status_changed && ! current_user_can( 'manage_forum_attributes', $post_id ) ) {
			$invalid = true;
		}

		if ( ! $is_restore && $order_changed && ! current_user_can( 'assign_moderators' ) ) {
			$invalid = true;
		}

		if ( $invalid ) {
			$bbp_xmlrpc_error_post_id = $post_id;
		}

		return;
	}

	// Structural changes require bbPress lifecycle handlers to keep related metadata and counts synchronized
	if ( ! $is_restore && ( $parent_changed || $status_changed || $order_changed ) ) {
		$invalid = true;
	}

	// Validate topic forum access
	if ( bbp_get_topic_post_type() === $post_type ) {
		if ( empty( $post_parent ) || bbp_is_forum_category( $post_parent ) ) {
			$invalid = true;
		} elseif ( ! current_user_can( 'edit_forum', $post_parent ) && bbp_is_forum_closed( $post_parent ) ) {
			$invalid = true;
		} elseif ( ! current_user_can( 'read_forum', $post_parent ) ) {
			$invalid = true;
		}
	}

	// Validate reply topic and forum access
	if ( bbp_get_reply_post_type() === $post_type ) {
		$topic_id = bbp_get_reply_topic_id( $post_id );
		$forum_id = bbp_get_topic_forum_id( $topic_id );

		if ( ! current_user_can( 'read_topic', $topic_id ) || ! current_user_can( 'read_forum', $forum_id ) ) {
			$invalid = true;
		}
	}

	$title   = isset( $post_data['post_title'] ) ? wp_unslash( $post_data['post_title'] ) : $post->post_title;
	$content = isset( $post_data['post_content'] ) ? wp_unslash( $post_data['post_content'] ) : $post->post_content;

	if ( empty( $content ) || ( ( bbp_get_topic_post_type() === $post_type ) && empty( $title ) ) || bbp_is_title_too_long( $title ) ) {
		$invalid = true;
	}

	if ( ! bbp_check_for_moderation( array(), (int) $post->post_author, $title, $content, true ) ) {
		$invalid = true;
	}

	// Revision restoration cannot change the post status to pending
	if ( $is_restore && ! bbp_check_for_moderation( array(), (int) $post->post_author, $title, $content ) ) {
		$invalid = true;
	}

	if ( $invalid ) {
		$bbp_xmlrpc_error_post_id = $post_id;
	}
}

/**
 * Deny a post capability when an XML-RPC edit failed bbPress validation.
 *
 * @since 2.6.16 bbPress
 *
 * @param array  $caps    Required capabilities.
 * @param string $cap     Requested capability.
 * @param int    $user_id User ID.
 * @param array  $args    Capability arguments.
 * @return array Required capabilities.
 */
function bbp_map_xmlrpc_meta_caps( $caps = array(), $cap = '', $user_id = 0, $args = array() ) {
	global $bbp_xmlrpc_error_post_id;

	$edit_caps = array( 'edit_post', 'edit_topic', 'edit_reply' );

	if ( ! empty( $bbp_xmlrpc_error_post_id ) && in_array( $cap, $edit_caps, true ) && ! empty( $args[0] ) && ( (int) $args[0] === $bbp_xmlrpc_error_post_id ) ) {
		$caps[] = 'do_not_allow';
	}

	return $caps;
}

/**
 * Apply bbPress moderation to XML-RPC post data.
 *
 * @since 2.6.16 bbPress
 *
 * @param array $post_data Parsed post data.
 * @return array Parsed post data.
 */
function bbp_xmlrpc_wp_insert_post_data( $post_data = array() ) {
	$post_id   = ! empty( $post_data['ID'] ) ? (int) $post_data['ID'] : 0;
	$post_type = isset( $post_data['post_type'] ) ? $post_data['post_type'] : '';

	if ( empty( $post_id ) || ! in_array( $post_type, array( bbp_get_topic_post_type(), bbp_get_reply_post_type() ), true ) ) {
		return $post_data;
	}

	$title     = isset( $post_data['post_title'] ) ? wp_unslash( $post_data['post_title'] ) : '';
	$content   = isset( $post_data['post_content'] ) ? wp_unslash( $post_data['post_content'] ) : '';
	$author_id = isset( $post_data['post_author'] ) ? (int) $post_data['post_author'] : 0;
	$is_public = ( bbp_get_topic_post_type() === $post_type )
		? bbp_is_topic_public( $post_id )
		: bbp_is_reply_public( $post_id );

	if ( $is_public && ! bbp_check_for_moderation( array(), $author_id, $title, $content ) ) {
		$post_data['post_status'] = bbp_get_pending_status_id();
	}

	return $post_data;
}
