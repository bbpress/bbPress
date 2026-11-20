<?php

/**
 * Forum moderator commands for WP-CLI.
 *
 * @package bbPress
 */

defined( 'ABSPATH' ) || exit;

/**
 * Manage forum moderator assignments.
 *
 * @since 2.7.0 bbPress (r8552)
 */
class BBP_Moderator_CLI_Command extends BBP_CLI_Command {

	/**
	 * Assign a user to moderate a forum.
	 *
	 * ## OPTIONS
	 *
	 * <forum-id>
	 * : Forum ID.
	 *
	 * <user>
	 * : User ID or login.
	 *
	 * @since 2.7.0 bbPress (r8552)
	 *
	 * @param array<int, string>    $args       Positional WP-CLI arguments.
	 * @param array<string, mixed> $assoc_args Named WP-CLI arguments.
	 * @return void
	 */
	public function add( $args, $assoc_args ) {
		$forum_id = $this->post_id( $args[0], bbp_get_forum_post_type() );
		$this->require_cap( 'manage_forum_attributes', $forum_id );
		$user_id = $this->user_id( $args[1] );
		if ( ! bbp_allow_forum_mods() ) {
			WP_CLI::error( __( 'Forum moderator assignments are disabled.', 'bbpress' ) );
		}
		if ( bbp_is_object_of_user( $forum_id, $user_id, '_bbp_moderator_id' ) ) {
			WP_CLI::error( __( 'The user already moderates this forum.', 'bbpress' ) );
		}

		if ( ! bbp_add_moderator( $forum_id, $user_id ) ) {
			WP_CLI::error( __( 'The moderator could not be assigned.', 'bbpress' ) );
		}
		WP_CLI::success( __( 'Moderator assigned.', 'bbpress' ) );
	}

	/**
	 * Remove a user's forum moderator assignment.
	 *
	 * ## OPTIONS
	 *
	 * <forum-id>
	 * : Forum ID.
	 *
	 * <user>
	 * : User ID or login.
	 *
	 * @since 2.7.0 bbPress (r8552)
	 *
	 * @param array<int, string>    $args       Positional WP-CLI arguments.
	 * @param array<string, mixed> $assoc_args Named WP-CLI arguments.
	 * @return void
	 */
	public function remove( $args, $assoc_args ) {
		$forum_id = $this->post_id( $args[0], bbp_get_forum_post_type() );
		$this->require_cap( 'manage_forum_attributes', $forum_id );
		$user_id = $this->user_id( $args[1] );

		if ( ! bbp_remove_moderator( $forum_id, $user_id ) ) {
			WP_CLI::error( __( 'The moderator assignment could not be removed, or does not exist.', 'bbpress' ) );
		}
		WP_CLI::success( __( 'Moderator assignment removed.', 'bbpress' ) );
	}

	/**
	 * List the moderator user IDs for a forum.
	 *
	 * ## OPTIONS
	 *
	 * <forum-id>
	 * : Forum ID.
	 *
	 * @subcommand list
	 *
	 * @since 2.7.0 bbPress (r8552)
	 *
	 * @param array<int, string>    $args       Positional WP-CLI arguments.
	 * @param array<string, mixed> $assoc_args Named WP-CLI arguments.
	 * @return void
	 */
	public function list_users( $args, $assoc_args ) {
		$forum_id = $this->post_id( $args[0], bbp_get_forum_post_type() );
		$this->require_cap( 'manage_forum_attributes', $forum_id );
		$this->print_ids( bbp_get_moderator_ids( $forum_id ) );
	}
}
