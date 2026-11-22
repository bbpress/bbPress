<?php

/**
 * bbPress User Engagements Post Type.
 *
 * @package bbPress
 * @subpackage Users
 */

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

/**
 * Engagement type for forums and topics.
 *
 * @since 2.7.0 bbPress (r8306)
 */
class BBP_User_Engagements_Type_Post extends BBP_User_Engagements_Type {

	/**
	 * Clean up subscriptions when an opted-in post is deleted.
	 *
	 * @since 2.7.0 bbPress (r8390)
	 * @return void
	 */
	public function register_cleanup() {
		add_action( 'before_delete_post', array( $this, 'cleanup_deleted_post' ) );
	}

	/**
	 * Stop cleaning up posts when this type is replaced or deregistered.
	 *
	 * @since 2.7.0 bbPress (r8390)
	 * @return void
	 */
	public function unregister_cleanup() {
		remove_action( 'before_delete_post', array( $this, 'cleanup_deleted_post' ) );
	}

	/**
	 * Remove subscriptions to an opted-in post before it is deleted.
	 *
	 * Forums and topics already use their established deletion actions.
	 * Other post types are cleaned only while they are opted in, avoiding a
	 * subscriber lookup for every ordinary post deletion.
	 *
	 * @since 2.7.0 bbPress (r8390)
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	public function cleanup_deleted_post( $post_id = 0 ) {
		$post_type = get_post_type( $post_id );
		$type      = $post_type ? get_post_type_object( $post_type ) : null;

		if ( ( $type instanceof WP_Post_Type )
			&& ! empty( $type->bbp_subscriptions )
			&& ! in_array( $post_type, array( bbp_get_forum_post_type(), bbp_get_topic_post_type() ), true ) ) {
			bbp_remove_object_from_all_users( $post_id, '_bbp_subscription', 'post' );
		}
	}

	/**
	 * Check whether a post supports an engagement.
	 *
	 * Topics in a forum may be favorited. Registered post types may be
	 * subscribed to when they opt in with the `bbp_subscriptions` registration
	 * argument and their subtype is enabled.
	 *
	 * @since 2.7.0 bbPress (r8306)
	 * @since 2.7.0 bbPress (r8330) Support opted-in post types and subtype settings.
	 *
	 * @param string $engagement Favorite or subscription.
	 * @param int    $object_id  Post ID.
	 * @return bool Whether the post supports the engagement.
	 */
	public function supports( $engagement = '', $object_id = 0 ) {
		$post      = get_post( $object_id );
		$post_type = ! empty( $post )
			? get_post_type_object( $post->post_type )
			: false;

		if ( empty( $post ) || ! ( $post_type instanceof WP_Post_Type ) ) {
			return false;
		}

		if ( bbp_get_topic_post_type() === $post->post_type ) {
			$forum_id = bbp_get_topic_forum_id( $post->ID );

			if ( empty( $forum_id ) || ! bbp_is_forum( $forum_id ) ) {
				return false;
			}

			if ( 'favorite' === $engagement ) {
				return true;
			}
		}

		return ( 'subscription' === $engagement )
			&& ! empty( $post_type->bbp_subscriptions )
			&& bbp_is_subscription_subtype_active( 'post', $post->post_type );
	}

	/**
	 * Check whether the current user can read a post.
	 *
	 * Reading a topic also requires reading its forum.
	 *
	 * @since 2.7.0 bbPress (r8306)
	 *
	 * @param int $object_id Post ID.
	 * @return bool Whether the current user can read the post.
	 */
	public function current_user_can_read( $object_id = 0 ) {
		$post = get_post( $object_id );

		if ( empty( $post ) ) {
			return false;
		}

		if ( bbp_get_topic_post_type() === $post->post_type ) {
			return current_user_can( 'read_topic', $post->ID )
				&& current_user_can( 'read_forum', bbp_get_topic_forum_id( $post->ID ) );
		}

		if ( bbp_get_forum_post_type() === $post->post_type ) {
			return current_user_can( 'read_forum', $post->ID );
		}

		if ( bbp_get_reply_post_type() === $post->post_type ) {
			return current_user_can( 'read_reply', $post->ID )
				&& current_user_can( 'read_forum', bbp_get_reply_forum_id( $post->ID ) );
		}

		return is_post_publicly_viewable( $post )
			|| current_user_can( 'read_post', $post->ID );
	}

	/**
	 * Check whether post queries support `tax_query`.
	 *
	 * @since 2.7.0 bbPress (r8318)
	 *
	 * @return bool True, because WP_Query supports `tax_query`.
	 */
	public function supports_tax_query() {
		return true;
	}

	/**
	 * Get query arguments that limit results to specific posts.
	 *
	 * @since 2.7.0 bbPress (r8318)
	 *
	 * @param array<int, int> $object_ids Post IDs.
	 * @return array<string, mixed> Query arguments.
	 */
	public function get_query_args( $object_ids = array() ) {
		return array(
			'post__in' => $object_ids
		);
	}

	/**
	 * Get query arguments that limit results to a post type.
	 *
	 * @since 2.7.0 bbPress (r8335)
	 *
	 * @param string $object_subtype Post type name.
	 * @return array<string, mixed> Query arguments.
	 */
	public function get_subtype_query_args( $object_subtype = '' ) {
		$object_subtype = is_string( $object_subtype )
			? sanitize_key( $object_subtype )
			: '';

		return ! empty( $object_subtype )
			? array( 'post_type' => $object_subtype )
			: array();
	}

	/**
	 * Get the URL of a post.
	 *
	 * @since 2.7.0 bbPress (r8306)
	 *
	 * @param int $object_id Post ID.
	 * @return string|false Post URL, or false if the post does not exist.
	 */
	public function get_url( $object_id = 0 ) {
		return get_permalink( $object_id );
	}
}
