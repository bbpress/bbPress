<?php

/**
 * Database reset command for WP-CLI.
 *
 * @package bbPress
 */

defined( 'ABSPATH' ) || exit;

/**
 * Reset bbPress data on the selected site.
 *
 * @since 2.7.0 bbPress (r8552)
 */
class BBP_Reset_CLI_Command extends BBP_CLI_Command {

	/**
	 * Remove all forums, topics, replies, topic tags, settings, and forum user data.
	 *
	 * Back up the database first. On multisite, use --url to select the site.
	 * This operation cannot be undone.
	 *
	 * ## OPTIONS
	 *
	 * [--delete-imported-users]
	 * : Also delete previously imported users. Unavailable on multisite.
	 *
	 * [--yes]
	 * : Confirm the reset without prompting.
	 *
	 * ## EXAMPLES
	 *
	 *     wp bbp reset --user=admin
	 *     wp bbp reset --user=admin --yes
	 *
	 * @since 2.7.0 bbPress (r8552)
	 *
	 * @param array<int, string>    $args       Positional WP-CLI arguments.
	 * @param array<string, mixed> $assoc_args Named WP-CLI arguments.
	 * @return void
	 */
	public function __invoke( $args, $assoc_args ) {
		if ( ! bbp_is_user_keymaster() ) {
			WP_CLI::error( __( 'Only a forum keymaster can reset bbPress. Use --user with a keymaster account.', 'bbpress' ) );
		}

		$delete_imported_users = \WP_CLI\Utils\get_flag_value( $assoc_args, 'delete-imported-users', false );
		if ( $delete_imported_users && is_multisite() ) {
			WP_CLI::error( __( 'Imported users cannot be deleted by the bbPress reset on multisite.', 'bbpress' ) );
		}

		WP_CLI::confirm( __( 'Reset all bbPress data on this site? This cannot be undone. Back up the database first.', 'bbpress' ), $assoc_args );

		require_once __DIR__ . '/../admin/tools/reset.php';
		$result = bbp_admin_reset_database( $delete_imported_users, false );
		foreach ( $result['messages'] as $message ) {
			WP_CLI::log( html_entity_decode( wp_strip_all_tags( $message ), ENT_QUOTES, get_bloginfo( 'charset' ) ) );
		}

		if ( ! $result['success'] ) {
			WP_CLI::error( __( 'The bbPress reset finished with one or more failed operations.', 'bbpress' ) );
		}

		WP_CLI::success( __( 'bbPress reset completed.', 'bbpress' ) );
	}
}
