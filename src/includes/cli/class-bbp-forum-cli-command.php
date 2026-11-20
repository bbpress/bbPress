<?php

/**
 * Forum commands for WP-CLI.
 *
 * @package bbPress
 */

defined( 'ABSPATH' ) || exit;

/**
 * Manage bbPress forums.
 *
 * @since 2.7.0 bbPress (r8552)
 */
class BBP_Forum_CLI_Command extends BBP_CLI_Command {

	/**
	 * Create a forum with its bbPress metadata and counts.
	 *
	 * ## OPTIONS
	 *
	 * --title=<title>
	 * : Forum title.
	 *
	 * [--content=<content>]
	 * : Forum description.
	 *
	 * [--parent=<forum-id>]
	 * : Parent forum ID.
	 *
	 * [--author=<user>]
	 * : Author ID or login. Defaults to the selected --user.
	 *
	 * [--status=<status>]
	 * : open or closed. Defaults to open.
	 *
	 * [--porcelain]
	 * : Print only the new forum ID.
	 *
	 * @since 2.7.0 bbPress (r8552)
	 *
	 * @param array<int, string>    $args       Positional WP-CLI arguments.
	 * @param array<string, mixed> $assoc_args Named WP-CLI arguments.
	 * @return void
	 */
	public function create( $args, $assoc_args ) {
		$this->require_cap( 'create_forums' );
		$this->require_cap( 'publish_forums' );
		$title  = isset( $assoc_args['title'] ) ? trim( $assoc_args['title'] ) : '';
		$status = isset( $assoc_args['status'] ) ? $assoc_args['status'] : 'open';

		if ( '' === $title || ! in_array( $status, array( 'open', 'closed' ), true ) ) {
			WP_CLI::error( __( 'Provide a title and choose open or closed for --status.', 'bbpress' ) );
		}

		$parent = isset( $assoc_args['parent'] )
			? $this->post_id( $assoc_args['parent'], bbp_get_forum_post_type() )
			: 0;
		$author = $this->author_id( $assoc_args, 'forums' );
		$id     = bbp_insert_forum(
			array(
				'post_parent'  => $parent,
				'post_title'   => wp_slash( $title ),
				'post_content' => isset( $assoc_args['content'] ) ? wp_slash( $assoc_args['content'] ) : '',
				'post_author'  => $author
			),
			array( 'status' => $status )
		);

		if ( ! $id ) {
			WP_CLI::error( __( 'The forum could not be created.', 'bbpress' ) );
		}

		if ( \WP_CLI\Utils\get_flag_value( $assoc_args, 'porcelain', false ) ) {
			WP_CLI::line( $id );
		} else {
			/* translators: %d: forum ID. */
			WP_CLI::success( sprintf( __( 'Forum %d created.', 'bbpress' ), $id ) );
		}
	}

	/**
	 * Open a forum.
	 *
	 * ## OPTIONS
	 *
	 * <forum-id>
	 * : Forum ID.
	 *
	 * @since 2.7.0 bbPress (r8552)
	 *
	 * @param array<int, string>    $args       Positional WP-CLI arguments.
	 * @param array<string, mixed> $assoc_args Named WP-CLI arguments.
	 * @return void
	 */
	public function open( $args, $assoc_args ) {
		/* translators: %d: forum ID. */
		$this->change( $args[0], bbp_get_forum_post_type(), 'manage_forum_attributes', 'bbp_open_forum', __( 'Forum %d opened.', 'bbpress' ) );
	}

	/**
	 * Close a forum.
	 *
	 * ## OPTIONS
	 *
	 * <forum-id>
	 * : Forum ID.
	 *
	 * @since 2.7.0 bbPress (r8552)
	 *
	 * @param array<int, string>    $args       Positional WP-CLI arguments.
	 * @param array<string, mixed> $assoc_args Named WP-CLI arguments.
	 * @return void
	 */
	public function close( $args, $assoc_args ) {
		/* translators: %d: forum ID. */
		$this->change( $args[0], bbp_get_forum_post_type(), 'manage_forum_attributes', 'bbp_close_forum', __( 'Forum %d closed.', 'bbpress' ) );
	}
}
