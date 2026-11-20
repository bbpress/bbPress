<?php

/**
 * Reply commands for WP-CLI.
 *
 * @package bbPress
 */

defined( 'ABSPATH' ) || exit;

/**
 * Manage bbPress replies.
 *
 * @since 2.7.0 bbPress (r8552)
 */
class BBP_Reply_CLI_Command extends BBP_CLI_Command {

	/**
	 * Create a reply with its bbPress metadata and counts.
	 *
	 * ## OPTIONS
	 *
	 * --topic=<topic-id>
	 * : Parent topic ID.
	 *
	 * --content=<content>
	 * : Reply content.
	 *
	 * [--author=<user>]
	 * : Author ID or login. Defaults to the selected --user.
	 *
	 * [--porcelain]
	 * : Print only the new reply ID.
	 *
	 * @since 2.7.0 bbPress (r8552)
	 *
	 * @param array<int, string>    $args       Positional WP-CLI arguments.
	 * @param array<string, mixed> $assoc_args Named WP-CLI arguments.
	 * @return void
	 */
	public function create( $args, $assoc_args ) {
		$this->require_cap( 'moderate' );
		$this->require_cap( 'create_replies' );
		$this->require_cap( 'publish_replies' );
		$topic_id = $this->post_id( $assoc_args['topic'], bbp_get_topic_post_type() );
		$forum_id = bbp_get_topic_forum_id( $topic_id );
		$content  = trim( $assoc_args['content'] );

		if ( '' === $content ) {
			WP_CLI::error( __( 'Provide reply content.', 'bbpress' ) );
		}
		if ( ! $forum_id || ! bbp_is_topic_public( $topic_id ) || ! bbp_is_topic_open( $topic_id ) || ! bbp_is_forum_open( $forum_id ) || ! current_user_can( 'read_forum', $forum_id ) ) {
			WP_CLI::error( __( 'The topic or its forum is closed or unavailable to the selected user.', 'bbpress' ) );
		}

		$author = $this->author_id( $assoc_args, 'replies' );
		$id     = bbp_insert_reply(
			array(
				'post_parent'  => $topic_id,
				'post_content' => wp_slash( $content ),
				'post_author'  => $author
			),
			array(
				'forum_id' => $forum_id,
				'topic_id' => $topic_id
			)
		);

		if ( ! $id ) {
			WP_CLI::error( __( 'The reply could not be created.', 'bbpress' ) );
		}

		if ( \WP_CLI\Utils\get_flag_value( $assoc_args, 'porcelain', false ) ) {
			WP_CLI::line( $id );
		} else {
			/* translators: %d: reply ID. */
			WP_CLI::success( sprintf( __( 'Reply %d created.', 'bbpress' ), $id ) );
		}
	}

	/**
	 * Mark a reply as spam.
	 *
	 * ## OPTIONS
	 *
	 * <reply-id>
	 * : Reply ID.
	 *
	 * @since 2.7.0 bbPress (r8552)
	 *
	 * @param array<int, string>    $args       Positional WP-CLI arguments.
	 * @param array<string, mixed> $assoc_args Named WP-CLI arguments.
	 * @return void
	 */
	public function spam( $args, $assoc_args ) {
		/* translators: %d: reply ID. */
		$this->change( $args[0], bbp_get_reply_post_type(), 'moderate', 'bbp_spam_reply', __( 'Reply %d marked as spam.', 'bbpress' ) );
	}

	/**
	 * Restore a reply marked as spam.
	 *
	 * ## OPTIONS
	 *
	 * <reply-id>
	 * : Reply ID.
	 *
	 * @since 2.7.0 bbPress (r8552)
	 *
	 * @param array<int, string>    $args       Positional WP-CLI arguments.
	 * @param array<string, mixed> $assoc_args Named WP-CLI arguments.
	 * @return void
	 */
	public function ham( $args, $assoc_args ) {
		/* translators: %d: reply ID. */
		$this->change( $args[0], bbp_get_reply_post_type(), 'moderate', 'bbp_unspam_reply', __( 'Reply %d restored from spam.', 'bbpress' ) );
	}

	/**
	 * Approve a reply.
	 *
	 * ## OPTIONS
	 *
	 * <reply-id>
	 * : Reply ID.
	 *
	 * @since 2.7.0 bbPress (r8552)
	 *
	 * @param array<int, string>    $args       Positional WP-CLI arguments.
	 * @param array<string, mixed> $assoc_args Named WP-CLI arguments.
	 * @return void
	 */
	public function approve( $args, $assoc_args ) {
		/* translators: %d: reply ID. */
		$this->change( $args[0], bbp_get_reply_post_type(), 'moderate', 'bbp_approve_reply', __( 'Reply %d approved.', 'bbpress' ), 'bbp_is_reply_pending' );
	}

	/**
	 * Unapprove a reply.
	 *
	 * ## OPTIONS
	 *
	 * <reply-id>
	 * : Reply ID.
	 *
	 * @since 2.7.0 bbPress (r8552)
	 *
	 * @param array<int, string>    $args       Positional WP-CLI arguments.
	 * @param array<string, mixed> $assoc_args Named WP-CLI arguments.
	 * @return void
	 */
	public function unapprove( $args, $assoc_args ) {
		/* translators: %d: reply ID. */
		$this->change( $args[0], bbp_get_reply_post_type(), 'moderate', 'bbp_unapprove_reply', __( 'Reply %d unapproved.', 'bbpress' ), 'bbp_is_reply_public' );
	}
}
