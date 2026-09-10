<?php

/**
 * Tests for the reply component functions.
 *
 * @group replies
 * @group functions
 * @group status
 */
class BBP_Tests_Replies_Functions_Status extends BBP_UnitTestCase {

	/**
	 * @covers ::bbp_get_reply_statuses
	 * @todo   Implement test_bbp_get_reply_statuses().
	 */
	public function test_bbp_get_reply_statuses() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_spam_reply
	 */
	public function test_bbp_spam_reply() {

		// Create a forum
		$f = $this->factory->forum->create();

		// Create a topic
		$t = $this->factory->topic->create( array(
			'post_parent' => $f,
			'topic_meta' => array(
				'forum_id' => $f,
			)
		) );

		// Create some replies
		$r = $this->factory->reply->create_many( 3, array(
			'post_parent' => $t,
			'reply_meta' => array(
				'forum_id' => $f,
				'topic_id' => $t,
			)
		) );

		bbp_spam_reply( $r[1] );

		$reply_post_status = bbp_get_reply_status( $r[1] );
		$this->assertSame( 'spam', $reply_post_status );

		$reply_spam_meta_status = get_post_meta( $r[1], '_bbp_spam_meta_status', true );
		$this->assertSame( 'publish', $reply_spam_meta_status );

		$topic_reply_count = bbp_get_topic_reply_count( $t );
		$this->assertSame( '2', $topic_reply_count );
	}

	/**
	 * @covers ::bbp_update_counts_on_transition_post_status
	 */
	public function test_status_transitions_update_reply_counts_once() {
		$user_id  = $this->factory->user->create();
		$forum_id = $this->factory->forum->create();
		$topic_id = $this->factory->topic->create( array(
			'post_parent' => $forum_id,
			'topic_meta'  => array( 'forum_id' => $forum_id ),
		) );
		$reply_ids = $this->factory->reply->create_many( 2, array(
			'post_author' => $user_id,
			'post_parent' => $topic_id,
			'reply_meta'  => array(
				'forum_id' => $forum_id,
				'topic_id' => $topic_id,
			),
		) );

		bbp_update_topic_reply_count( $topic_id );
		bbp_update_topic_reply_count_hidden( $topic_id );
		bbp_update_forum_reply_count( $forum_id );
		bbp_update_forum_reply_count_hidden( $forum_id );
		bbp_update_user_reply_count( $user_id, 2 );

		wp_trash_post( $reply_ids[0] );
		$this->assertSame( 1, bbp_get_topic_reply_count( $topic_id, true ) );
		$this->assertSame( 1, bbp_get_topic_reply_count_hidden( $topic_id, true ) );
		$this->assertSame( 1, bbp_get_forum_reply_count( $forum_id, false, true ) );
		$this->assertSame( 1, bbp_get_forum_reply_count_hidden( $forum_id, false, true ) );
		$this->assertSame( 1, bbp_get_user_reply_count( $user_id, true ) );

		bbp_spam_reply( $reply_ids[0] );
		$this->assertSame( 1, bbp_get_topic_reply_count( $topic_id, true ) );
		$this->assertSame( 1, bbp_get_topic_reply_count_hidden( $topic_id, true ) );
		$this->assertSame( 1, bbp_get_forum_reply_count( $forum_id, false, true ) );
		$this->assertSame( 1, bbp_get_forum_reply_count_hidden( $forum_id, false, true ) );
		$this->assertSame( 1, bbp_get_user_reply_count( $user_id, true ) );

		bbp_unspam_reply( $reply_ids[0] );
		wp_untrash_post( $reply_ids[0] );
		$this->assertSame( 2, bbp_get_topic_reply_count( $topic_id, true ) );
		$this->assertSame( 0, bbp_get_topic_reply_count_hidden( $topic_id, true ) );
		$this->assertSame( 2, bbp_get_forum_reply_count( $forum_id, false, true ) );
		$this->assertSame( 0, bbp_get_forum_reply_count_hidden( $forum_id, false, true ) );
		$this->assertSame( 2, bbp_get_user_reply_count( $user_id, true ) );

		bbp_unapprove_reply( $reply_ids[0] );
		$this->assertSame( 1, bbp_get_topic_reply_count( $topic_id, true ) );
		$this->assertSame( 1, bbp_get_topic_reply_count_hidden( $topic_id, true ) );
		$this->assertSame( 1, bbp_get_forum_reply_count( $forum_id, false, true ) );
		$this->assertSame( 1, bbp_get_forum_reply_count_hidden( $forum_id, false, true ) );
		$this->assertSame( 1, bbp_get_user_reply_count( $user_id, true ) );

		bbp_spam_reply( $reply_ids[0] );
		bbp_unspam_reply( $reply_ids[0] );
		$this->assertSame( 1, bbp_get_topic_reply_count( $topic_id, true ) );
		$this->assertSame( 1, bbp_get_topic_reply_count_hidden( $topic_id, true ) );
		$this->assertSame( 1, bbp_get_forum_reply_count( $forum_id, false, true ) );
		$this->assertSame( 1, bbp_get_forum_reply_count_hidden( $forum_id, false, true ) );
		$this->assertSame( 1, bbp_get_user_reply_count( $user_id, true ) );

		bbp_approve_reply( $reply_ids[0] );
		$this->assertSame( 2, bbp_get_topic_reply_count( $topic_id, true ) );
		$this->assertSame( 0, bbp_get_topic_reply_count_hidden( $topic_id, true ) );
		$this->assertSame( 2, bbp_get_forum_reply_count( $forum_id, false, true ) );
		$this->assertSame( 0, bbp_get_forum_reply_count_hidden( $forum_id, false, true ) );
		$this->assertSame( 2, bbp_get_user_reply_count( $user_id, true ) );
	}

	/**
	 * @covers ::bbp_update_counts_on_transition_post_status
	 * @ticket BBP3678
	 */
	public function test_custom_non_public_status_updates_reply_counts() {
		$user_id  = $this->factory->user->create();
		$forum_id = $this->factory->forum->create();
		$topic_id = $this->factory->topic->create( array(
			'post_parent' => $forum_id,
			'topic_meta'  => array( 'forum_id' => $forum_id ),
		) );
		$reply_id = $this->factory->reply->create( array(
			'post_author' => $user_id,
			'post_parent' => $topic_id,
			'reply_meta'  => array(
				'forum_id' => $forum_id,
				'topic_id' => $topic_id,
			),
		) );
		$add_archived = function( $statuses ) {
			$statuses[] = 'archived';

			return $statuses;
		};

		add_filter( 'bbp_get_non_public_reply_statuses', $add_archived );

		try {
			wp_update_post( array(
				'ID'          => $reply_id,
				'post_status' => 'archived',
			) );

			$this->assertSame( 0, bbp_get_topic_reply_count( $topic_id, true ) );
			$this->assertSame( 1, bbp_get_topic_reply_count_hidden( $topic_id, true ) );
			$this->assertSame( 0, bbp_get_forum_reply_count( $forum_id, false, true ) );
			$this->assertSame( 1, bbp_get_forum_reply_count_hidden( $forum_id, false, true ) );
			$this->assertSame( 0, bbp_get_user_reply_count( $user_id, true ) );

			wp_update_post( array(
				'ID'          => $reply_id,
				'post_status' => bbp_get_public_status_id(),
			) );

			$this->assertSame( 1, bbp_get_topic_reply_count( $topic_id, true ) );
			$this->assertSame( 0, bbp_get_topic_reply_count_hidden( $topic_id, true ) );
			$this->assertSame( 1, bbp_get_forum_reply_count( $forum_id, false, true ) );
			$this->assertSame( 0, bbp_get_forum_reply_count_hidden( $forum_id, false, true ) );
			$this->assertSame( 1, bbp_get_user_reply_count( $user_id, true ) );
		} finally {
			remove_filter( 'bbp_get_non_public_reply_statuses', $add_archived );
		}
	}

	/**
	 * @covers ::bbp_update_reply_walker
	 */
	public function test_deleting_hidden_reply_recounts_hidden_totals() {
		$forum_id = $this->factory->forum->create();
		$topic_id = $this->factory->topic->create( array(
			'post_parent' => $forum_id,
			'topic_meta'  => array( 'forum_id' => $forum_id ),
		) );
		$reply_id = $this->factory->reply->create( array(
			'post_parent' => $topic_id,
			'reply_meta'  => array(
				'forum_id' => $forum_id,
				'topic_id' => $topic_id,
			),
		) );

		bbp_spam_reply( $reply_id );
		wp_delete_post( $reply_id, true );

		$this->assertSame( 0, bbp_get_topic_reply_count_hidden( $topic_id, true ) );
		$this->assertSame( 0, bbp_get_forum_reply_count_hidden( $forum_id, false, true ) );
	}

	/**
	 * @covers ::bbp_unspam_reply
	 */
	public function test_bbp_unspam_reply() {

		// Create a forum
		$f = $this->factory->forum->create();

		// Create a topic
		$t = $this->factory->topic->create( array(
			'post_parent' => $f,
			'topic_meta' => array(
				'forum_id' => $f,
			)
		) );

		// Create some replies
		$r = $this->factory->reply->create_many( 3, array(
			'post_parent' => $t,
			'reply_meta' => array(
				'forum_id' => $f,
				'topic_id' => $t,
			)
		) );

		bbp_spam_reply( $r[1] );

		$reply_post_status = bbp_get_reply_status( $r[1] );
		$this->assertSame( 'spam', $reply_post_status );

		$reply_spam_meta_status = get_post_meta( $r[1], '_bbp_spam_meta_status', true );
		$this->assertSame( 'publish', $reply_spam_meta_status );

		$topic_reply_count = bbp_get_topic_reply_count( $t );
		$this->assertSame( '2', $topic_reply_count );

		bbp_unspam_reply( $r[1] );

		$reply_post_status = bbp_get_reply_status( $r[1] );
		$this->assertSame( 'publish', $reply_post_status );

		$reply_spam_meta_status = get_post_meta( $r[1], '_bbp_spam_meta_status', true );
		$this->assertSame( '', $reply_spam_meta_status );

		$topic_reply_count = bbp_get_topic_reply_count( $t );
		$this->assertSame( '3', $topic_reply_count );
	}

	/**
	 * @covers ::bbp_approve_reply
	 */
	public function test_bbp_approve_reply() {

		// Create a forum.
		$f = $this->factory->forum->create();

		// Create a topic.
		$t = $this->factory->topic->create( array(
			'post_parent' => $f,
			'topic_meta' => array(
				'forum_id' => $f,
			),
		) );

		// Create some replies.
		$r1 = $this->factory->reply->create( array(
			'post_parent' => $t,
			'reply_meta' => array(
				'forum_id' => $f,
				'topic_id' => $t,
			),
		) );

		$reply_post_status = bbp_get_reply_status( $r1 );
		$this->assertSame( 'publish', $reply_post_status );

		$topic_reply_count = bbp_get_topic_reply_count( $t );
		$this->assertSame( '1', $topic_reply_count );

		$r2 = $this->factory->reply->create( array(
			'post_parent' => $t,
			'post_status' => bbp_get_pending_status_id(),
			'reply_meta' => array(
				'forum_id' => $f,
				'topic_id' => $t,
			),
		) );

		$reply_post_status = bbp_get_reply_status( $r2 );
		$this->assertSame( 'pending', $reply_post_status );

		$topic_reply_count = bbp_get_topic_reply_count( $t );
		$this->assertSame( '1', $topic_reply_count );

		bbp_approve_reply( $r2 );

		$reply_post_status = bbp_get_reply_status( $r2 );
		$this->assertSame( 'publish', $reply_post_status );

		$topic_reply_count = bbp_get_topic_reply_count( $t );
		$this->assertSame( '2', $topic_reply_count );
	}

	/**
	 * @covers ::bbp_unapprove_reply
	 */
	public function test_bbp_unapprove_reply() {

		// Create a forum.
		$f = $this->factory->forum->create();

		// Create a topic.
		$t = $this->factory->topic->create( array(
			'post_parent' => $f,
			'topic_meta' => array(
				'forum_id' => $f,
			),
		) );

		// Create some replies.
		$r1 = $this->factory->reply->create( array(
			'post_parent' => $t,
			'reply_meta' => array(
				'forum_id' => $f,
				'topic_id' => $t,
			),
		) );

		$reply_post_status = bbp_get_reply_status( $r1 );
		$this->assertSame( 'publish', $reply_post_status );

		$topic_reply_count = bbp_get_topic_reply_count( $t );
		$this->assertSame( '1', $topic_reply_count );

		$r2 = $this->factory->reply->create( array(
			'post_parent' => $t,
			'reply_meta' => array(
				'forum_id' => $f,
				'topic_id' => $t,
			),
		) );

		$reply_post_status = bbp_get_reply_status( $r2 );
		$this->assertSame( 'publish', $reply_post_status );

		$topic_reply_count = bbp_get_topic_reply_count( $t );
		$this->assertSame( '2', $topic_reply_count );

		bbp_unapprove_reply( $r2 );

		$reply_post_status = bbp_get_reply_status( $r2 );
		$this->assertSame( 'pending', $reply_post_status );

		$topic_reply_count = bbp_get_topic_reply_count( $t );
		$this->assertSame( '1', $topic_reply_count );
	}
}
