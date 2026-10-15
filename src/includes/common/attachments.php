<?php

/**
 * bbPress Attachment Functions.
 *
 * Attachments are not enabled by bbPress by default. Support for plugins that
 * enable them is best-effort and ongoing, but is not guaranteed.
 *
 * @package bbPress
 * @subpackage Common
 */

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

/**
 * Return an attachment's bbPress parent.
 *
 * @since 2.6.20 bbPress (r8011)
 *
 * @param int|WP_Post $attachment Attachment ID or post object.
 * @return WP_Post|false Parent post object, or false when the attachment does
 *                       not have a bbPress parent.
 */
function bbp_get_attachment_parent( $attachment = 0 ) {
	if ( empty( $attachment ) ) {
		return false;
	}

	$attachment = get_post( $attachment );

	if ( empty( $attachment ) || ( 'attachment' !== $attachment->post_type ) || empty( $attachment->post_parent ) ) {
		return false;
	}

	$parent = get_post( $attachment->post_parent );

	if ( empty( $parent ) || ! bbp_is_custom_post_type( $parent ) ) {
		return false;
	}

	return $parent;
}

/**
 * Check whether the current user can view an attachment with a bbPress parent.
 *
 * @since 2.6.20 bbPress (r8011)
 *
 * @param int|WP_Post $attachment Attachment ID or post object.
 * @return bool Whether the attachment can be viewed.
 */
function bbp_user_can_view_attachment( $attachment = 0 ) {
	$parent = bbp_get_attachment_parent( $attachment );

	if ( empty( $parent ) || bbp_get_password_required_id( $parent->ID ) ) {
		return false;
	}

	$controller = new BBP_REST_Attachment_Parent_Controller( $parent->post_type );

	return $controller->check_read_permission( $parent );
}

/**
 * Make inherited attachments private before permanently deleting their parent.
 *
 * WordPress treats an inherited attachment with a missing parent as public. Keep
 * retained bbPress attachments private when their forum, topic, or reply is
 * permanently deleted.
 *
 * @since 2.6.20 bbPress (r8016)
 *
 * @param int $post_id Parent post ID.
 */
function bbp_make_post_attachments_private( $post_id = 0 ) {
	$attachment_ids = bbp_get_child_ids_by_status( $post_id, 'attachment', 'inherit' );

	foreach ( $attachment_ids as $attachment_id ) {
		wp_update_post(
			array(
				'ID'          => $attachment_id,
				'post_status' => 'private',
			)
		);
	}
}

/**
 * Return the attachment ID from WordPress's normalized query variables.
 *
 * @since 2.6.20 bbPress (r8011)
 *
 * @return int Attachment ID, or zero when none was requested.
 */
function bbp_get_attachment_request_id() {
	$attachment_id = max(
		absint( get_query_var( 'p' ) ),
		absint( get_query_var( 'page_id' ) ),
		absint( get_query_var( 'attachment_id' ) )
	);

	$wp_query = bbp_get_wp_query();
	$post     = null;

	if ( $wp_query instanceof WP_Query ) {
		$post = ( $wp_query->queried_object instanceof WP_Post )
			? $wp_query->queried_object
			: $wp_query->post;
	}

	// Invalid pagination may redirect using the resolved attachment object.
	if ( $post instanceof WP_Post && ( 'attachment' === $post->post_type ) && ( empty( $attachment_id ) || get_query_var( 'page' ) ) ) {
		$attachment_id = $post->ID;
	}

	return (int) $attachment_id;
}

/**
 * Prevent an attachment page from exposing media attached to restricted
 * bbPress content.
 *
 * @since 2.6.20 bbPress (r8011)
 */
function bbp_forum_enforce_attachments() {

	// Bail if this is not an attachment page
	if ( ! is_attachment() ) {
		return;
	}

	$wp_query   = bbp_get_wp_query();
	$attachment = ! empty( $wp_query->post ) ? $wp_query->post : null;
	$parent     = bbp_get_attachment_parent( $attachment );

	// Attachments inherit bbPress visibility from their parent
	if ( $parent && ! bbp_user_can_view_attachment( $attachment ) ) {
		bbp_set_404( $wp_query );
	}
}

/**
 * Prevent object-by-ID oEmbed requests from disclosing restricted attachments.
 *
 * @since 2.6.20 bbPress (r8011)
 *
 * @param int $post_id Resolved post ID.
 * @return int Resolved post ID, or zero when access is denied.
 */
function bbp_filter_oembed_request_attachment_id( $post_id = 0 ) {
	$att_parent = bbp_get_attachment_parent( $post_id );

	if ( $att_parent && ! bbp_user_can_embed_post( $att_parent->ID ) ) {
		return 0;
	}

	return $post_id;
}

/**
 * Prevent canonical redirects from exposing restricted attachment URLs.
 *
 * @since 2.6.20 bbPress (r8011)
 *
 * @param string $redirect_url Proposed redirect URL.
 * @param string $requested_url Requested URL.
 * @return string|false Redirect URL, or false when access is denied.
 */
function bbp_do_not_redirect_restricted_attachments( $redirect_url = '', $requested_url = '' ) {
	if ( empty( $requested_url ) ) {
		return $redirect_url;
	}

	$attachment_id = bbp_get_attachment_request_id();
	$att_parent    = bbp_get_attachment_parent( $attachment_id );

	if ( $att_parent && ! bbp_user_can_view_attachment( $attachment_id ) ) {
		return false;
	}

	return $redirect_url;
}

/**
 * Use the bbPress controller for attachments when Core's is unchanged.
 *
 * @since 2.6.17 bbPress (r7482)
 */
function bbp_register_rest_attachment_controller() {
	$post_type = get_post_type_object( 'attachment' );

	if ( ! empty( $post_type ) && ( 'WP_REST_Attachments_Controller' === $post_type->rest_controller_class ) ) {
		$post_type->rest_controller_class = 'BBP_REST_Attachments_Controller';
	}
}

/**
 * REST API controller for bbPress attachment parents.
 *
 * bbPress post types may be attachment parents even when their own REST routes
 * are disabled.
 *
 * @since 2.6.20 bbPress (r8011)
 */
class BBP_REST_Attachment_Parent_Controller extends BBP_REST_Posts_Controller {

	/**
	 * Checks if a post type is allowed for attachment permission checks.
	 *
	 * @since 2.6.20 bbPress (r8011)
	 *
	 * @param WP_Post_Type|string $post_type Post type object or name.
	 * @return bool Whether the post type is allowed.
	 */
	protected function check_is_post_type_allowed( $post_type ) {
		if ( ! is_object( $post_type ) ) {
			$post_type = get_post_type_object( $post_type );
		}

		$types = array( bbp_get_forum_post_type(), bbp_get_topic_post_type(), bbp_get_reply_post_type() );

		if ( ! empty( $post_type ) && in_array( $post_type->name, $types, true ) ) {
			return true;
		}

		return parent::check_is_post_type_allowed( $post_type );
	}
}

/**
 * REST API controller for attachments to bbPress post types.
 *
 * @since 2.6.17 bbPress (r7482)
 */
class BBP_REST_Attachments_Controller extends WP_REST_Attachments_Controller {

	/**
	 * Checks if an attachment can be read.
	 *
	 * @since 2.6.17 bbPress (r7482)
	 *
	 * @param WP_Post $post Attachment post object.
	 * @return bool Whether the attachment can be read.
	 */
	public function check_read_permission( $post ) {
		$post_type = get_post_type_object( $post->post_type );
		if ( ! $this->check_is_post_type_allowed( $post_type ) ) {
			return false;
		}

		// Inherit bbPress permissions from the attachment parent
		if ( 'inherit' === $post->post_status ) {
			$parent = bbp_get_attachment_parent( $post );

			if ( $parent ) {
				return bbp_user_can_view_attachment( $post );
			}
		}

		return parent::check_read_permission( $post );
	}
}
