<?php

/**
 * Favorite commands for WP-CLI.
 *
 * @package bbPress
 */

defined( 'ABSPATH' ) || exit;

/**
 * Manage topic favorites.
 *
 * @since 2.7.0 bbPress (r8552)
 */
class BBP_Favorite_CLI_Command extends BBP_CLI_Command {

	/**
	 * Add a topic to a user's favorites.
	 *
	 * ## OPTIONS
	 *
	 * <user>
	 * : User ID or login.
	 *
	 * <topic-id>
	 * : Topic ID.
	 *
	 * @since 2.7.0 bbPress (r8552)
	 *
	 * @param array<int, string>    $args       Positional WP-CLI arguments.
	 * @param array<string, mixed> $assoc_args Named WP-CLI arguments.
	 * @return void
	 */
	public function add( $args, $assoc_args ) {
		$user_id  = $this->user_id( $args[0] );
		$topic_id = $this->post_id( $args[1], bbp_get_topic_post_type() );
		if ( ! bbp_current_user_can_toggle_engagement( $topic_id, 'post', 'favorite', 'add', $user_id ) ) {
			WP_CLI::error( __( 'You are not allowed to add this favorite.', 'bbpress' ) );
		}

		if ( ! bbp_add_user_favorite( $user_id, $topic_id ) ) {
			WP_CLI::error( __( 'The favorite could not be added, or already exists.', 'bbpress' ) );
		}
		WP_CLI::success( __( 'Favorite added.', 'bbpress' ) );
	}

	/**
	 * Remove a topic from a user's favorites.
	 *
	 * ## OPTIONS
	 *
	 * <user>
	 * : User ID or login.
	 *
	 * <topic-id>
	 * : Topic ID.
	 *
	 * @since 2.7.0 bbPress (r8552)
	 *
	 * @param array<int, string>    $args       Positional WP-CLI arguments.
	 * @param array<string, mixed> $assoc_args Named WP-CLI arguments.
	 * @return void
	 */
	public function remove( $args, $assoc_args ) {
		$user_id  = $this->user_id( $args[0] );
		$topic_id = $this->post_id( $args[1], bbp_get_topic_post_type() );
		if ( ! bbp_current_user_can_toggle_engagement( $topic_id, 'post', 'favorite', 'remove', $user_id ) ) {
			WP_CLI::error( __( 'You are not allowed to remove this favorite.', 'bbpress' ) );
		}

		if ( ! bbp_remove_user_favorite( $user_id, $topic_id ) ) {
			WP_CLI::error( __( 'The favorite could not be removed, or does not exist.', 'bbpress' ) );
		}
		WP_CLI::success( __( 'Favorite removed.', 'bbpress' ) );
	}

	/**
	 * List a user's favorite topic IDs.
	 *
	 * ## OPTIONS
	 *
	 * <user>
	 * : User ID or login.
	 *
	 * @subcommand list
	 *
	 * @since 2.7.0 bbPress (r8552)
	 *
	 * @param array<int, string>    $args       Positional WP-CLI arguments.
	 * @param array<string, mixed> $assoc_args Named WP-CLI arguments.
	 * @return void
	 */
	public function list_topics( $args, $assoc_args ) {
		$user_id = $this->user_id( $args[0] );
		if ( ! bbp_current_user_can_manage_engagements( 'favorite', $user_id ) ) {
			WP_CLI::error( __( 'You are not allowed to list these favorites.', 'bbpress' ) );
		}
		$ids = bbp_get_user_favorites_topic_ids( $user_id );
		$this->print_ids( $ids );
	}

	/**
	 * List the user IDs that favorited a topic.
	 *
	 * ## OPTIONS
	 *
	 * <topic-id>
	 * : Topic ID.
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
		$topic_id = $this->post_id( $args[0], bbp_get_topic_post_type() );
		$this->print_ids( bbp_get_topic_favoriters( $topic_id ) );
	}
}
