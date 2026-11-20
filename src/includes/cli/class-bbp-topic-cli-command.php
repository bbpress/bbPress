<?php

/**
 * Topic commands for WP-CLI.
 *
 * @package bbPress
 */

defined( 'ABSPATH' ) || exit;

/**
 * Manage bbPress topics.
 *
 * @since 2.7.0 bbPress (r8552)
 */
class BBP_Topic_CLI_Command extends BBP_CLI_Command {

	/**
	 * Create a topic with its bbPress metadata and counts.
	 *
	 * ## OPTIONS
	 *
	 * --forum=<forum-id>
	 * : Parent forum ID.
	 *
	 * --title=<title>
	 * : Topic title.
	 *
	 * --content=<content>
	 * : Topic content.
	 *
	 * [--author=<user>]
	 * : Author ID or login. Defaults to the selected --user.
	 *
	 * [--porcelain]
	 * : Print only the new topic ID.
	 *
	 * @since 2.7.0 bbPress (r8552)
	 *
	 * @param array<int, string>    $args       Positional WP-CLI arguments.
	 * @param array<string, mixed> $assoc_args Named WP-CLI arguments.
	 * @return void
	 */
	public function create( $args, $assoc_args ) {
		$this->require_cap( 'moderate' );
		$this->require_cap( 'create_topics' );
		$this->require_cap( 'publish_topics' );
		$forum_id = $this->post_id( $assoc_args['forum'], bbp_get_forum_post_type() );
		$title    = trim( $assoc_args['title'] );
		$content  = trim( $assoc_args['content'] );

		if ( '' === $title || '' === $content ) {
			WP_CLI::error( __( 'Provide a topic title and content.', 'bbpress' ) );
		}
		if ( bbp_is_forum_category( $forum_id ) ) {
			WP_CLI::error( __( 'Topics cannot be created in a category forum.', 'bbpress' ) );
		}
		if ( ! bbp_is_forum_open( $forum_id ) || ! current_user_can( 'read_forum', $forum_id ) ) {
			WP_CLI::error( __( 'The forum is closed or unavailable to the selected user.', 'bbpress' ) );
		}

		$author = $this->author_id( $assoc_args, 'topics' );
		$id     = bbp_insert_topic(
			array(
				'post_parent'  => $forum_id,
				'post_title'   => wp_slash( $title ),
				'post_content' => wp_slash( $content ),
				'post_author'  => $author
			),
			array( 'forum_id' => $forum_id )
		);

		if ( ! $id ) {
			WP_CLI::error( __( 'The topic could not be created.', 'bbpress' ) );
		}

		if ( \WP_CLI\Utils\get_flag_value( $assoc_args, 'porcelain', false ) ) {
			WP_CLI::line( $id );
		} else {
			/* translators: %d: topic ID. */
			WP_CLI::success( sprintf( __( 'Topic %d created.', 'bbpress' ), $id ) );
		}
	}

	/**
	 * Close a topic.
	 *
	 * ## OPTIONS
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
	public function close( $args, $assoc_args ) {
		/* translators: %d: topic ID. */
		$this->change( $args[0], bbp_get_topic_post_type(), 'moderate', 'bbp_close_topic', __( 'Topic %d closed.', 'bbpress' ), 'bbp_is_topic_published' );
	}

	/**
	 * Open a closed topic.
	 *
	 * ## OPTIONS
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
	public function open( $args, $assoc_args ) {
		/* translators: %d: topic ID. */
		$this->change( $args[0], bbp_get_topic_post_type(), 'moderate', 'bbp_open_topic', __( 'Topic %d opened.', 'bbpress' ) );
	}

	/**
	 * Mark a topic as spam.
	 *
	 * ## OPTIONS
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
	public function spam( $args, $assoc_args ) {
		/* translators: %d: topic ID. */
		$this->change( $args[0], bbp_get_topic_post_type(), 'moderate', 'bbp_spam_topic', __( 'Topic %d marked as spam.', 'bbpress' ) );
	}

	/**
	 * Restore a topic marked as spam.
	 *
	 * ## OPTIONS
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
	public function ham( $args, $assoc_args ) {
		/* translators: %d: topic ID. */
		$this->change( $args[0], bbp_get_topic_post_type(), 'moderate', 'bbp_unspam_topic', __( 'Topic %d restored from spam.', 'bbpress' ) );
	}

	/**
	 * Approve a topic.
	 *
	 * ## OPTIONS
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
	public function approve( $args, $assoc_args ) {
		/* translators: %d: topic ID. */
		$this->change( $args[0], bbp_get_topic_post_type(), 'moderate', 'bbp_approve_topic', __( 'Topic %d approved.', 'bbpress' ), 'bbp_is_topic_pending' );
	}

	/**
	 * Unapprove a topic.
	 *
	 * ## OPTIONS
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
	public function unapprove( $args, $assoc_args ) {
		/* translators: %d: topic ID. */
		$this->change( $args[0], bbp_get_topic_post_type(), 'moderate', 'bbp_unapprove_topic', __( 'Topic %d unapproved.', 'bbpress' ) );
	}

	/**
	 * Stick a topic in its forum, or on the front page with --super.
	 *
	 * ## OPTIONS
	 *
	 * <topic-id>
	 * : Topic ID.
	 *
	 * [--super]
	 * : Make the topic super sticky.
	 *
	 * @since 2.7.0 bbPress (r8552)
	 *
	 * @param array<int, string>    $args       Positional WP-CLI arguments.
	 * @param array<string, mixed> $assoc_args Named WP-CLI arguments.
	 * @return void
	 */
	public function stick( $args, $assoc_args ) {
		$id = $this->post_id( $args[0], bbp_get_topic_post_type() );
		$this->require_cap( 'moderate', $id );
		if ( ! bbp_is_topic_public( $id ) ) {
			WP_CLI::error( __( 'Only a public topic can be stuck.', 'bbpress' ) );
		}
		if ( bbp_is_topic_sticky( $id ) ) {
			WP_CLI::error( __( 'The topic is already stuck. Unstick it before changing its scope.', 'bbpress' ) );
		}
		$super = (bool) \WP_CLI\Utils\get_flag_value( $assoc_args, 'super', false );
		if ( $super ) {
			$this->require_cap( 'moderate' );
		}

		if ( ! bbp_stick_topic( $id, $super ) ) {
			WP_CLI::error( __( 'The topic could not be stuck.', 'bbpress' ) );
		}
		/* translators: %d: topic ID. */
		WP_CLI::success( sprintf( __( 'Topic %d stuck.', 'bbpress' ), $id ) );
	}

	/**
	 * Unstick a topic.
	 *
	 * ## OPTIONS
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
	public function unstick( $args, $assoc_args ) {
		$id = $this->post_id( $args[0], bbp_get_topic_post_type() );
		if ( bbp_is_topic_super_sticky( $id ) ) {
			$this->require_cap( 'moderate' );
		}
		/* translators: %d: topic ID. */
		$this->change( $id, bbp_get_topic_post_type(), 'moderate', 'bbp_unstick_topic', __( 'Topic %d unstuck.', 'bbpress' ), 'bbp_is_topic_sticky' );
	}
}
