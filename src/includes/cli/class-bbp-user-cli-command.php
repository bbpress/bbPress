<?php

/**
 * User commands for WP-CLI.
 *
 * @package bbPress
 */

defined( 'ABSPATH' ) || exit;

/**
 * Manage bbPress user roles and spam status.
 *
 * @since 2.7.0 bbPress (r8552)
 */
class BBP_User_CLI_Command extends BBP_CLI_Command {

	/**
	 * Set a user's bbPress forum role.
	 *
	 * ## OPTIONS
	 *
	 * <user>
	 * : User ID or login.
	 *
	 * <role>
	 * : A registered bbPress forum role, such as bbp_participant.
	 *
	 * @subcommand set-role
	 *
	 * @since 2.7.0 bbPress (r8552)
	 *
	 * @param array<int, string>    $args       Positional WP-CLI arguments.
	 * @param array<string, mixed> $assoc_args Named WP-CLI arguments.
	 * @return void
	 */
	public function set_role( $args, $assoc_args ) {
		$user_id = $this->user_id( $args[0] );
		$role    = $args[1];
		$this->require_cap( 'promote_user', $user_id );

		if ( ! bbp_current_user_can_edit_user_field( 'forum_role', $user_id ) || ! bbp_is_valid_role( $role ) || ! array_key_exists( $role, bbp_get_user_editable_forum_roles( $user_id ) ) ) {
			WP_CLI::error( __( 'The requested forum role cannot be assigned to this user.', 'bbpress' ) );
		}
		$already_set = bbp_get_user_role( $user_id ) === $role;
		if ( false === bbp_set_user_role( $user_id, $role ) ) {
			if ( $already_set ) {
				WP_CLI::success( __( 'Forum role is already set.', 'bbpress' ) );
				return;
			}
			WP_CLI::error( __( 'The forum role could not be set.', 'bbpress' ) );
		}
		WP_CLI::success( __( 'Forum role set.', 'bbpress' ) );
	}

	/**
	 * Mark a user's forum posts as spam across their sites.
	 *
	 * ## OPTIONS
	 *
	 * <user>
	 * : User ID or login.
	 *
	 * [--yes]
	 * : Skip the confirmation prompt.
	 *
	 * @since 2.7.0 bbPress (r8552)
	 *
	 * @param array<int, string>    $args       Positional WP-CLI arguments.
	 * @param array<string, mixed> $assoc_args Named WP-CLI arguments.
	 * @return void
	 */
	public function spam( $args, $assoc_args ) {
		$this->change_spam( $args[0], $assoc_args, 'bbp_make_spam_user', __( 'Forum posts marked as spam.', 'bbpress' ) );
	}

	/**
	 * Restore a user's forum posts from spam across their sites.
	 *
	 * ## OPTIONS
	 *
	 * <user>
	 * : User ID or login.
	 *
	 * [--yes]
	 * : Skip the confirmation prompt.
	 *
	 * @since 2.7.0 bbPress (r8552)
	 *
	 * @param array<int, string>    $args       Positional WP-CLI arguments.
	 * @param array<string, mixed> $assoc_args Named WP-CLI arguments.
	 * @return void
	 */
	public function ham( $args, $assoc_args ) {
		$this->change_spam( $args[0], $assoc_args, 'bbp_make_ham_user', __( 'Forum posts restored from spam.', 'bbpress' ) );
	}

	/**
	 * Apply a user spam transition after permission and confirmation checks.
	 *
	 * @since 2.7.0 bbPress (r8552)
	 * @access private
	 *
	 * @param int|string           $value      User ID or login supplied by WP-CLI.
	 * @param array<string, mixed> $assoc_args Named WP-CLI arguments.
	 * @param string               $callback   bbPress user spam transition function.
	 * @param string               $success    Translated success message.
	 * @return void
	 */
	private function change_spam( $value, $assoc_args, $callback, $success ) {
		$user_id = $this->user_id( $value );
		$this->require_cap( 'moderate' );
		$this->require_cap( 'edit_user', $user_id );
		if ( is_multisite() ) {
			$this->require_cap( 'manage_network_users' );
		}
		WP_CLI::confirm( __( 'This may change all of the user\'s forum posts across their sites. Continue?', 'bbpress' ), $assoc_args );

		if ( ! is_callable( $callback ) || ! call_user_func( $callback, $user_id ) ) {
			WP_CLI::error( __( 'The forum posts could not be changed.', 'bbpress' ) );
		}
		WP_CLI::success( $success );
	}
}
