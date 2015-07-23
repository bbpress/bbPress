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
