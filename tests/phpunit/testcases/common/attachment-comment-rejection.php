<?php

/**
 * Comments on attachments to bbPress content.
 *
 * @group common
 */
class BBP_Tests_Common_Attachment_Comment_Rejection extends BBP_UnitTestCase {

	private function create_attachment( $parent_id ) {
		return wp_insert_attachment(
			array(
				'post_title'     => 'Attachment comment test',
				'post_status'    => 'inherit',
				'post_parent'    => $parent_id,
				'post_mime_type' => 'text/plain',
				'comment_status' => 'open',
			)
		);
	}

	private function create_topic_attachment( $forum_id ) {
		$topic_id = $this->factory->topic->create(
			array(
				'post_parent' => $forum_id,
				'topic_meta'  => array( 'forum_id' => $forum_id ),
			)
		);

		return $this->create_attachment( $topic_id );
	}

	private function submit_comment( $post_id ) {
		return wp_handle_comment_submission(
			array(
				'comment_post_ID' => $post_id,
				'author'          => 'Commenter',
				'email'           => 'commenter@example.org',
				'comment'         => 'Attachment comment',
			)
		);
	}

	public function approve_comment( $approved ) {
		return 1;
	}

	/**
	 * Core must reject an unreadable attachment before inserting a comment.
	 *
	 * @covers ::bbp_reject_restricted_attachment_comments
	 */
	public function test_core_submission_rejects_hidden_attachments() {
		$forum_id    = $this->factory->forum->create( array( 'post_status' => bbp_get_hidden_status_id() ) );
		$topic_id    = $this->factory->topic->create(
			array(
				'post_parent' => $forum_id,
				'topic_meta'  => array( 'forum_id' => $forum_id ),
			)
		);
		$reply_id    = $this->factory->reply->create( array( 'post_parent' => $topic_id ) );
		$attachments = array(
			$this->create_attachment( $forum_id ),
			$this->create_attachment( $topic_id ),
			$this->create_attachment( $reply_id ),
		);

		$this->set_current_user( 0 );

		foreach ( $attachments as $attachment_id ) {
			$this->assertTrue( comments_open( $attachment_id ) );
			$this->assertFalse( bbp_user_can_view_attachment( $attachment_id ) );

			$comment = $this->submit_comment( $attachment_id );

			$this->assertWPError( $comment );
			$this->assertSame( 'bbp_attachment_comment_forbidden', $comment->get_error_code() );
			$this->assertSame( 403, $comment->get_error_data() );
			$this->assertSame( 0, get_comments( array( 'post_id' => $attachment_id, 'count' => true, 'status' => 'all' ) ) );
		}
	}

	/**
	 * Later approval filters cannot replace an attachment permission error.
	 *
	 * @covers ::bbp_reject_restricted_attachment_comments
	 */
	public function test_later_approval_filter_cannot_override_denial() {
		$forum_id      = $this->factory->forum->create( array( 'post_status' => bbp_get_hidden_status_id() ) );
		$attachment_id = $this->create_topic_attachment( $forum_id );

		$this->set_current_user( 0 );
		add_filter( 'pre_comment_approved', array( $this, 'approve_comment' ), 20 );

		try {
			$comment = $this->submit_comment( $attachment_id );

			$this->assertWPError( $comment );
			$this->assertSame( 'bbp_attachment_comment_forbidden', $comment->get_error_code() );
			$this->assertSame( 0, get_comments( array( 'post_id' => $attachment_id, 'count' => true, 'status' => 'all' ) ) );
		} finally {
			remove_filter( 'pre_comment_approved', array( $this, 'approve_comment' ), 20 );
		}
	}

	/**
	 * Moderation statuses still insert comments, so they must also be rejected.
	 *
	 * @covers ::bbp_reject_restricted_attachment_comments
	 */
	public function test_rejects_moderated_comments_and_preserves_prior_errors() {
		$forum_id      = $this->factory->forum->create( array( 'post_status' => bbp_get_hidden_status_id() ) );
		$attachment_id = $this->create_topic_attachment( $forum_id );
		$commentdata   = array( 'comment_post_ID' => $attachment_id );
		$prior_error   = new WP_Error( 'prior_comment_error' );

		$this->set_current_user( 0 );
		$this->assertSame( $prior_error, apply_filters( 'pre_comment_approved', $prior_error, $commentdata ) );

		foreach ( array( 0, 'spam', 'trash' ) as $approved ) {
			$approval = apply_filters( 'pre_comment_approved', $approved, $commentdata );
			$this->assertWPError( $approval );
			$this->assertSame( 'bbp_attachment_comment_forbidden', $approval->get_error_code() );
		}
	}

	/**
	 * The same parent visibility rule covers nested and restricted content.
	 *
	 * @covers ::bbp_reject_restricted_attachment_comments
	 */
	public function test_rejects_all_inaccessible_bbpress_parents() {
		$hidden_id   = $this->factory->forum->create( array( 'post_status' => bbp_get_hidden_status_id() ) );
		$private_id  = $this->factory->forum->create( array( 'post_status' => bbp_get_private_status_id() ) );
		$password_id = $this->factory->forum->create( array( 'post_password' => 'forum-password' ) );
		$child_id    = $this->factory->forum->create( array( 'post_parent' => $hidden_id ) );
		$topic_id    = $this->factory->topic->create( array( 'post_parent' => $hidden_id ) );
		$reply_id    = $this->factory->reply->create( array( 'post_parent' => $topic_id ) );
		$attachments = array(
			$this->create_attachment( $hidden_id ),
			$this->create_topic_attachment( $hidden_id ),
			$this->create_attachment( $reply_id ),
			$this->create_topic_attachment( $private_id ),
			$this->create_topic_attachment( $password_id ),
			$this->create_topic_attachment( $child_id ),
		);
		$cookie_name = 'wp-postpass_' . COOKIEHASH;
		$old_cookie  = isset( $_COOKIE[ $cookie_name ] ) ? $_COOKIE[ $cookie_name ] : null;

		unset( $_COOKIE[ $cookie_name ] );
		$this->set_current_user( 0 );

		try {
			foreach ( $attachments as $attachment_id ) {
				$this->assertFalse( bbp_user_can_view_attachment( $attachment_id ) );
				$this->assertTrue( comments_open( $attachment_id ) );

				$approval = apply_filters( 'pre_comment_approved', 1, array( 'comment_post_ID' => $attachment_id ) );
				$this->assertWPError( $approval );
				$this->assertSame( 'bbp_attachment_comment_forbidden', $approval->get_error_code() );
			}
		} finally {
			if ( null === $old_cookie ) {
				unset( $_COOKIE[ $cookie_name ] );
			} else {
				$_COOKIE[ $cookie_name ] = $old_cookie;
			}
		}
	}

	/**
	 * Public bbPress attachments remain commentable.
	 *
	 * @covers ::bbp_reject_restricted_attachment_comments
	 */
	public function test_public_attachment_accepts_comment() {
		$forum_id      = $this->factory->forum->create();
		$attachment_id = $this->create_topic_attachment( $forum_id );

		$this->set_current_user( 0 );
		$this->assertTrue( comments_open( $attachment_id ) );
		$this->assertInstanceOf( 'WP_Comment', $this->submit_comment( $attachment_id ) );
	}

	/**
	 * Unrelated WordPress attachments remain commentable.
	 *
	 * @covers ::bbp_reject_restricted_attachment_comments
	 */
	public function test_unrelated_attachment_accepts_comment() {
		$attachment_id = $this->create_attachment( self::factory()->post->create() );

		$this->set_current_user( 0 );
		$this->assertTrue( comments_open( $attachment_id ) );
		$this->assertInstanceOf( 'WP_Comment', $this->submit_comment( $attachment_id ) );
	}

	/**
	 * Users who can view a hidden parent can still comment on its attachment.
	 *
	 * @covers ::bbp_reject_restricted_attachment_comments
	 */
	public function test_moderator_accepts_hidden_attachment_comment() {
		$forum_id      = $this->factory->forum->create( array( 'post_status' => bbp_get_hidden_status_id() ) );
		$attachment_id = $this->create_topic_attachment( $forum_id );
		$moderator_id  = self::factory()->user->create();

		bbp_set_user_role( $moderator_id, bbp_get_moderator_role() );
		$this->set_current_user( $moderator_id );
		$this->assertTrue( bbp_user_can_view_attachment( $attachment_id ) );
		$this->assertInstanceOf( 'WP_Comment', $this->submit_comment( $attachment_id ) );
	}

	/**
	 * A participant who can view a private forum can comment on its attachment.
	 *
	 * @covers ::bbp_reject_restricted_attachment_comments
	 */
	public function test_participant_accepts_private_attachment_comment() {
		$forum_id      = $this->factory->forum->create( array( 'post_status' => bbp_get_private_status_id() ) );
		$attachment_id = $this->create_topic_attachment( $forum_id );
		$user_id       = self::factory()->user->create();

		bbp_set_user_role( $user_id, bbp_get_participant_role() );
		$this->set_current_user( $user_id );
		$this->assertTrue( bbp_user_can_view_attachment( $attachment_id ) );
		$this->assertTrue( comments_open( $attachment_id ) );
		$this->assertInstanceOf( 'WP_Comment', $this->submit_comment( $attachment_id ) );
	}

	/**
	 * A valid forum password preserves the normal comment submission path.
	 *
	 * @covers ::bbp_reject_restricted_attachment_comments
	 */
	public function test_forum_password_allows_comment_submission() {
		$forum_id      = $this->factory->forum->create( array( 'post_password' => 'forum-password' ) );
		$attachment_id = $this->create_topic_attachment( $forum_id );
		$cookie_name   = 'wp-postpass_' . COOKIEHASH;
		$old_cookie    = isset( $_COOKIE[ $cookie_name ] ) ? $_COOKIE[ $cookie_name ] : null;

		$this->set_current_user( 0 );
		unset( $_COOKIE[ $cookie_name ] );

		try {
			$comment = $this->submit_comment( $attachment_id );
			$this->assertWPError( $comment );
			$this->assertSame( 'bbp_attachment_comment_forbidden', $comment->get_error_code() );

			require_once ABSPATH . WPINC . '/class-phpass.php';
			$hasher                  = new PasswordHash( 8, true );
			$_COOKIE[ $cookie_name ] = $hasher->HashPassword( 'forum-password' );

			$this->assertTrue( bbp_user_can_view_attachment( $attachment_id ) );
			$this->assertInstanceOf( 'WP_Comment', $this->submit_comment( $attachment_id ) );
		} finally {
			if ( null === $old_cookie ) {
				unset( $_COOKIE[ $cookie_name ] );
			} else {
				$_COOKIE[ $cookie_name ] = $old_cookie;
			}
		}
	}
}
