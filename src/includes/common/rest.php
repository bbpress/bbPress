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
	 * Apply bbPress's strict block list before creating a topic or reply.
	 *
	 * @since 2.6.19
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return true|WP_Error True if the request has access, WP_Error otherwise.
	 */
	public function create_item_permissions_check( $request ) {
		$retval = parent::create_item_permissions_check( $request );

		if ( is_wp_error( $retval ) || ! $retval || ! in_array( $this->post_type, array( bbp_get_topic_post_type(), bbp_get_reply_post_type() ), true ) ) {
			return $retval;
		}

		if ( ! bbp_check_for_moderation( array(), bbp_get_current_user_id(), $this->get_moderation_title( $request, null ), $this->get_moderation_content( $request, null ), true ) ) {
			return new WP_Error(
				'bbp_rest_disallowed_content',
				esc_html__( 'This forum content cannot be created at this time.', 'bbpress' ),
				array( 'status' => 400 )
			);
		}

		return $retval;
	}

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

		$post = isset( $request['id'] ) ? get_post( $request['id'] ) : null;
		if ( ! empty( $post ) && ! $this->check_read_permission( $post ) ) {
			return new WP_Error(
				'bbp_rest_cannot_edit_forum_content',
				esc_html__( 'You are not allowed to edit this forum content.', 'bbpress' ),
				array( 'status' => rest_authorization_required_code() )
			);
		}

		// REST requests do not use the front-end edit query flags that enforce
		// bbPress's edit lock in the topic and reply capability mappings.
		if ( ! empty( $post ) && $this->is_forum_content( $post ) ) {
			$can_moderate = current_user_can( 'moderate', $post->ID );

			if ( ! $can_moderate ) {
				// Only moderators may change status or move the edit window.
				if ( $request->has_param( 'status' ) && ( $request['status'] !== $post->post_status ) ) {
					return new WP_Error(
						'bbp_rest_cannot_change_status',
						esc_html__( 'You are not allowed to change this forum content status.', 'bbpress' ),
						array( 'status' => rest_authorization_required_code() )
					);
				}

				if ( $this->is_post_date_changed( $request, $post ) ) {
					return new WP_Error(
						'bbp_rest_cannot_change_date',
						esc_html__( 'You are not allowed to change this forum content date.', 'bbpress' ),
						array( 'status' => rest_authorization_required_code() )
					);
				}

				// Pending posts may have a zero GMT date even when they are recent.
				$post_date_gmt = ( '0000-00-00 00:00:00' === $post->post_date_gmt )
					? get_gmt_from_date( $post->post_date )
					: $post->post_date_gmt;

				if ( ( bbp_get_current_user_id() === (int) $post->post_author ) && bbp_past_edit_lock( $post_date_gmt ) ) {
					return new WP_Error(
						'bbp_rest_edit_lock',
						esc_html__( 'You can no longer edit this forum content.', 'bbpress' ),
						array( 'status' => rest_authorization_required_code() )
					);
				}
			}

			if ( ! bbp_check_for_moderation( array(), (int) $post->post_author, $this->get_moderation_title( $request, $post ), $this->get_moderation_content( $request, $post ), true ) ) {
				return new WP_Error(
					'bbp_rest_disallowed_content',
					esc_html__( 'This forum content cannot be edited at this time.', 'bbpress' ),
					array( 'status' => 400 )
				);
			}
		}

		$forum_id = ! empty( $post ) ? bbp_get_forum_id( $post->ID ) : 0;
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
	 * Apply bbPress moderation to REST edits before WordPress saves the post.
	 *
	 * @since 2.6.19
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response or error from WordPress.
	 */
	public function update_item( $request ) {
		$post = isset( $request['id'] ) ? get_post( $request['id'] ) : null;

		if ( ! empty( $post ) && $this->is_forum_content( $post ) && in_array( $post->post_status, bbp_get_public_topic_statuses(), true ) ) {
			$title   = $this->get_moderation_title( $request, $post );
			$content = $this->get_moderation_content( $request, $post );

			if ( ! bbp_check_for_moderation( array(), (int) $post->post_author, $title, $content ) ) {
				$request->set_param( 'status', bbp_get_pending_status_id() );
			}
		}

		return parent::update_item( $request );
	}

	/**
	 * Whether a post is a topic or reply.
	 *
	 * @since 2.6.19
	 *
	 * @param WP_Post $post Post to check.
	 * @return bool Whether this is forum content.
	 */
	private function is_forum_content( $post ) {
		return in_array( $post->post_type, array( bbp_get_topic_post_type(), bbp_get_reply_post_type() ), true );
	}

	/**
	 * Whether a REST request changes a topic or reply publication date.
	 *
	 * @since 2.6.19
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @param WP_Post         $post    Existing post.
	 * @return bool Whether the date would change.
	 */
	private function is_post_date_changed( $request, $post ) {
		$post_date_gmt = ( '0000-00-00 00:00:00' === $post->post_date_gmt )
			? get_gmt_from_date( $post->post_date )
			: $post->post_date_gmt;

		foreach ( array(
			'date'     => false,
			'date_gmt' => true,
		) as $field => $is_gmt ) {
			if ( ! $request->has_param( $field ) ) {
				continue;
			}

			$dates = is_string( $request[ $field ] ) ? rest_get_date_with_gmt( $request[ $field ], $is_gmt ) : false;

			if ( empty( $dates ) || ( $post->post_date !== $dates[0] ) || ( $post_date_gmt !== $dates[1] ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Get the title that bbPress moderation should check.
	 *
	 * @since 2.6.19
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @param WP_Post|null    $post    Existing post, or null when creating.
	 * @return string Title to check.
	 */
	private function get_moderation_title( $request, $post ) {
		if ( ! $request->has_param( 'title' ) ) {
			return empty( $post ) ? '' : $post->post_title;
		}

		$title = $request['title'];

		return is_string( $title )
			? $title
			: ( ! empty( $title['raw'] ) ? $title['raw'] : ( empty( $post ) ? '' : $post->post_title ) );
	}

	/**
	 * Get the content that bbPress moderation should check.
	 *
	 * @since 2.6.19
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @param WP_Post|null    $post    Existing post, or null when creating.
	 * @return string Content to check.
	 */
	private function get_moderation_content( $request, $post ) {
		if ( ! $request->has_param( 'content' ) ) {
			return empty( $post ) ? '' : $post->post_content;
		}

		$content = $request['content'];

		return is_string( $content )
			? $content
			: ( isset( $content['raw'] ) ? $content['raw'] : ( empty( $post ) ? '' : $post->post_content ) );
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
		$user_id  = bbp_get_current_user_id();

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
		$moderator  = $restricted && is_user_logged_in() && bbp_is_user_forum_moderator( $user_id, $forum_id );

		// Allow filtered moderators to read the restricted forum object
		$moderator_can_read = $moderator
			&& ( bbp_get_forum_post_type() === $post->post_type )
			&& in_array( $post->post_status, array( bbp_get_private_status_id(), bbp_get_hidden_status_id() ), true );

		if ( ! $can_read && ! $moderator_can_read ) {
			return false;
		}

		return ! bbp_is_forum_restricted_for_user( $forum_id, $user_id );
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
