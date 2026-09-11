<?php

/**
 * bbPress REST API.
 *
 * @package bbPress
 * @subpackage REST
 */

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

/**
 * REST API controller for bbPress post types.
 *
 * @since 2.7.0
 */
class BBP_REST_Posts_Controller extends WP_REST_Posts_Controller {

	/**
	 * Checks if a post can be updated.
	 *
	 * @since 2.7.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return true|WP_Error True if the request has access to update the item, WP_Error object otherwise.
	 */
	public function update_item_permissions_check( $request ) {
		$retval = parent::update_item_permissions_check( $request );

		if ( is_wp_error( $retval ) ) {
			return $retval;
		}

		$forum_id = isset( $request['id'] ) ? bbp_get_forum_id( $request['id'] ) : 0;
		$forum    = bbp_get_forum( $forum_id );

		if ( empty( $forum ) ) {
			return $retval;
		}

		$parent_changed = $request->has_param( 'parent' ) && ( (int) $request['parent'] !== (int) $forum->post_parent );
		$status_changed = $request->has_param( 'status' ) && ( $request['status'] !== $forum->post_status );
		$order_changed  = $request->has_param( 'menu_order' ) && ( (int) $request['menu_order'] !== (int) $forum->menu_order );

		if ( ( $parent_changed || $order_changed ) && ! current_user_can( 'assign_moderators' ) ) {
			return new WP_Error(
				'bbp_rest_cannot_edit_forum_structure',
				esc_html__( 'You are not allowed to change this forum structure.', 'bbpress' ),
				array( 'status' => rest_authorization_required_code() )
			);
		}

		if ( $status_changed && ! current_user_can( 'manage_forum_attributes', $forum_id ) ) {
			return new WP_Error(
				'bbp_rest_cannot_edit_forum_visibility',
				esc_html__( 'You are not allowed to change this forum visibility.', 'bbpress' ),
				array( 'status' => rest_authorization_required_code() )
			);
		}

		return $retval;
	}

	/**
	 * Checks if a post type is allowed for permission checks.
	 *
	 * bbPress posts may be attachment parents even when their own REST routes
	 * are disabled.
	 *
	 * @since 2.7.0
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

	/**
	 * Checks if a post can be read.
	 *
	 * @since 2.7.0
	 *
	 * @param WP_Post $post Post object.
	 * @return bool Whether the post can be read.
	 */
	public function check_read_permission( $post ) {
		$post_type = get_post_type_object( $post->post_type );
		if ( ! $this->check_is_post_type_allowed( $post_type ) ) {
			return false;
		}

		$can_read = parent::check_read_permission( $post );

		// Get the forum ID for this post
		switch ( $post->post_type ) {
			case bbp_get_forum_post_type() :
				$forum_id = $post->ID;
				break;

			case bbp_get_topic_post_type() :
				$forum_id = bbp_get_topic_forum_id( $post->ID );
				break;

			case bbp_get_reply_post_type() :
				$forum_id = bbp_get_reply_forum_id( $post->ID );
				break;

			default :
				$forum_id = 0;
				break;
		}

		// Check access to restricted forums and their ancestors
		$restricted = ! empty( $forum_id ) && bbp_is_forum_restricted( $forum_id, true );
		$moderator  = $restricted && is_user_logged_in() && bbp_is_user_forum_moderator( bbp_get_current_user_id(), $forum_id );

		// Allow filtered moderators to read the restricted forum object
		$moderator_can_read = $moderator
			&& ( bbp_get_forum_post_type() === $post->post_type )
			&& in_array( $post->post_status, array( bbp_get_private_status_id(), bbp_get_hidden_status_id() ), true );

		if ( ( ! $can_read && ! $moderator_can_read ) || ( $restricted && ! $moderator && ! current_user_can( 'read_forum', $forum_id ) ) ) {
			return false;
		}

		return true;
	}
}

/**
 * REST API controller for attachments to bbPress post types.
 *
 * @since 2.7.0
 */
class BBP_REST_Attachments_Controller extends WP_REST_Attachments_Controller {

	/**
	 * Checks if an attachment can be read.
	 *
	 * @since 2.7.0
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
		if ( ( 'inherit' === $post->post_status ) && ! empty( $post->post_parent ) ) {
			$parent = get_post( $post->post_parent );
			$types  = array( bbp_get_forum_post_type(), bbp_get_topic_post_type(), bbp_get_reply_post_type() );

			if ( ! empty( $parent ) && in_array( $parent->post_type, $types, true ) ) {
				$controller = new BBP_REST_Posts_Controller( $parent->post_type );

				return $controller->check_read_permission( $parent );
			}
		}

		return parent::check_read_permission( $post );
	}
}

/**
 * Use the bbPress controller for attachments when Core's is unchanged.
 *
 * @since 2.7.0
 */
function bbp_register_rest_attachment_controller() {
	$post_type = get_post_type_object( 'attachment' );

	if ( ! empty( $post_type ) && ( 'WP_REST_Attachments_Controller' === $post_type->rest_controller_class ) ) {
		$post_type->rest_controller_class = 'BBP_REST_Attachments_Controller';
	}
}
