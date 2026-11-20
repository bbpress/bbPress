<?php

/**
 * Engagement commands for WP-CLI.
 *
 * @package bbPress
 */

defined( 'ABSPATH' ) || exit;

/**
 * List topic engagements. Rebuild them with wp bbp tool run bbp-topic-engagements.
 *
 * @since 2.7.0 bbPress (r8552)
 */
class BBP_Engagement_CLI_Command extends BBP_CLI_Command {

	/**
	 * List a user's engaged topic IDs.
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
		$this->require_cap( 'moderate' );
		$this->print_ids( bbp_get_user_engaged_topic_ids( $this->user_id( $args[0] ) ) );
	}

	/**
	 * List user IDs engaged in a topic.
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
		$this->print_ids( bbp_get_topic_engagements( $topic_id ) );
	}
}
