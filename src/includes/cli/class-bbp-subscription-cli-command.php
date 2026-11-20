<?php

/**
 * Subscription commands for WP-CLI.
 *
 * @package bbPress
 */

defined( 'ABSPATH' ) || exit;

/**
 * Manage forum and topic subscriptions.
 *
 * @since 2.7.0 bbPress (r8552)
 */
class BBP_Subscription_CLI_Command extends BBP_CLI_Command {

	/**
	 * Subscribe a user to a forum or topic.
	 *
	 * ## OPTIONS
	 *
	 * <user>
	 * : User ID or login.
	 *
	 * <object-id>
	 * : Forum or topic ID.
	 *
	 * @since 2.7.0 bbPress (r8552)
	 *
	 * @param array<int, string>    $args       Positional WP-CLI arguments.
	 * @param array<string, mixed> $assoc_args Named WP-CLI arguments.
	 * @return void
	 */
	public function add( $args, $assoc_args ) {
		$user_id   = $this->user_id( $args[0] );
		$object_id = $this->subscribable_id( $args[1] );
		if ( ! bbp_current_user_can_toggle_engagement( $object_id, 'post', 'subscription', 'add', $user_id ) ) {
			WP_CLI::error( __( 'You are not allowed to add this subscription.', 'bbpress' ) );
		}

		if ( ! bbp_add_user_subscription( $user_id, $object_id ) ) {
			WP_CLI::error( __( 'The subscription could not be added, or already exists.', 'bbpress' ) );
		}
		WP_CLI::success( __( 'Subscription added.', 'bbpress' ) );
	}

	/**
	 * Unsubscribe a user from a forum or topic.
	 *
	 * ## OPTIONS
	 *
	 * <user>
	 * : User ID or login.
	 *
	 * <object-id>
	 * : Forum or topic ID.
	 *
	 * @since 2.7.0 bbPress (r8552)
	 *
	 * @param array<int, string>    $args       Positional WP-CLI arguments.
	 * @param array<string, mixed> $assoc_args Named WP-CLI arguments.
	 * @return void
	 */
	public function remove( $args, $assoc_args ) {
		$user_id   = $this->user_id( $args[0] );
		$object_id = $this->subscribable_id( $args[1] );
		if ( ! bbp_current_user_can_toggle_engagement( $object_id, 'post', 'subscription', 'remove', $user_id ) ) {
			WP_CLI::error( __( 'You are not allowed to remove this subscription.', 'bbpress' ) );
		}

		if ( ! bbp_remove_user_subscription( $user_id, $object_id ) ) {
			WP_CLI::error( __( 'The subscription could not be removed, or does not exist.', 'bbpress' ) );
		}
		WP_CLI::success( __( 'Subscription removed.', 'bbpress' ) );
	}

	/**
	 * List a user's subscribed forum or topic IDs.
	 *
	 * ## OPTIONS
	 *
	 * <user>
	 * : User ID or login.
	 *
	 * [--kind=<kind>]
	 * : forum or topic. Defaults to topic.
	 *
	 * @subcommand list
	 *
	 * @since 2.7.0 bbPress (r8552)
	 *
	 * @param array<int, string>    $args       Positional WP-CLI arguments.
	 * @param array<string, mixed> $assoc_args Named WP-CLI arguments.
	 * @return void
	 */
	public function list_objects( $args, $assoc_args ) {
		$user_id = $this->user_id( $args[0] );
		if ( ! bbp_current_user_can_manage_engagements( 'subscription', $user_id ) ) {
			WP_CLI::error( __( 'You are not allowed to list these subscriptions.', 'bbpress' ) );
		}
		$kind    = isset( $assoc_args['kind'] ) ? $assoc_args['kind'] : 'topic';

		if ( 'forum' === $kind ) {
			$ids = bbp_get_user_subscribed_forum_ids( $user_id );
		} elseif ( 'topic' === $kind ) {
			$ids = bbp_get_user_subscribed_topic_ids( $user_id );
		} else {
			WP_CLI::error( __( 'Choose forum or topic for --kind.', 'bbpress' ) );
		}

		$this->print_ids( $ids );
	}

	/**
	 * List the user IDs subscribed to a forum or topic.
	 *
	 * ## OPTIONS
	 *
	 * <object-id>
	 * : Forum or topic ID.
	 *
	 * @subcommand list-users
	 *
	 * @since 2.7.0 bbPress (r8552)
	 *
	 * @param array<int, string>    $args       Positional WP-CLI arguments.
	 * @param array<string, mixed> $assoc_args Named WP-CLI arguments.
	 * @return void
	 */
	public function list_users( $args, $assoc_args ) {
		$this->require_cap( 'moderate' );
		$object_id = $this->subscribable_id( $args[0] );
		$this->print_ids( bbp_get_subscribers( $object_id ) );
	}

	/**
	 * Reject missing objects and post types that do not support subscriptions.
	 *
	 * @since 2.7.0 bbPress (r8552)
	 * @access private
	 *
	 * @param int|string $value Post ID supplied by WP-CLI.
	 * @return int Valid subscribable post ID.
	 */
	private function subscribable_id( $value ) {
		$id = absint( $value );
		if ( ! ctype_digit( (string) $value ) || ! $id || ! bbp_is_subscribable( $id, 'post' ) ) {
			WP_CLI::error( __( 'The requested post does not support subscriptions.', 'bbpress' ) );
		}

		return $id;
	}
}
