<?php

/**
 * Tests for the reply status template functions.
 *
 * @group replies
 * @group template
 * @group status
 */
class BBP_Tests_Repliess_Template_Status extends BBP_UnitTestCase {
	protected $old_current_user = 0;

	public function setUp() {
		parent::setUp();
		$this->old_current_user = get_current_user_id();
		$this->set_current_user( $this->factory->user->create( array( 'role' => 'administrator' ) ) );
		$this->keymaster_id = get_current_user_id();
		bbp_set_user_role( $this->keymaster_id, bbp_get_keymaster_role() );
	}

	public function tearDown() {
		parent::tearDown();
		$this->set_current_user( $this->old_current_user );
	}

	/**
	 * @covers ::bbp_reply_status
	 * @covers ::bbp_get_reply_status
	 * @todo   Implement test_bbp_get_reply_status().
	 */
	public function test_bbp_get_reply_status() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);

	}

	/**
	 * @covers ::bbp_is_reply_published
	 */
	public function test_bbp_is_reply_published() {
		$forum_id = $this->factory->forum->create();

		$topic_id = $this->factory->topic->create( array(
			'post_parent' => $forum_id,
			'topic_meta' => array(
				'forum_id' => $forum_id,
			),
		) );

		$reply_id = $this->factory->reply->create( array(
			'post_parent' => $topic_id,
			'reply_meta' => array(
				'forum_id' => $forum_id,
				'topic_id' => $topic_id,
			),
		) );

		$r = $this->factory->reply->create( array(
			'post_parent' => $topic_id,
			'reply_meta' => array(
				'forum_id'              => $forum_id,
				'topic_id'              => $topic_id,
			)
		) );

		$reply_published = bbp_is_reply_published( $r );
		$this->assertTrue( $reply_published );
		$reply_published = bbp_is_reply_published( $reply_id );
		$this->assertTrue( $reply_published );
	}

	/**
	 * @covers ::bbp_is_reply_spam
	 */
	public function test_bbp_is_reply_spam() {
		$forum_id = $this->factory->forum->create();

		$topic_id = $this->factory->topic->create( array(
			'post_parent' => $forum_id,
			'topic_meta' => array(
				'forum_id' => $forum_id,
			),
		) );

		$reply_id = $this->factory->reply->create( array(
			'post_parent' => $topic_id,
			'reply_meta' => array(
				'forum_id' => $forum_id,
				'topic_id' => $topic_id,
			),
		) );

		$r = $this->factory->reply->create( array(
			'post_parent' => $topic_id,
			'reply_meta' => array(
				'forum_id'              => $forum_id,
				'topic_id'              => $topic_id,
			)
		) );

		bbp_spam_reply( $r );

		$reply_spam = bbp_is_reply_spam( $r );
		$this->assertTrue( $reply_spam );

		bbp_unspam_reply( $r );

		$reply_spam = bbp_is_reply_spam( $r );
		$this->assertFalse( $reply_spam );
	}

	/**
	 * @covers ::bbp_is_reply_trash
	 * @todo   Implement test_bbp_is_reply_trash().
	 */
	public function test_bbp_is_reply_trash() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_is_reply_pending
	 */
	public function test_bbp_is_reply_pending() {
		$forum_id = $this->factory->forum->create();

		$topic_id = $this->factory->topic->create( array(
			'post_parent' => $forum_id,
			'topic_meta' => array(
				'forum_id' => $forum_id,
			),
		) );

		$reply_id = $this->factory->reply->create( array(
			'post_parent' => $topic_id,
			'reply_meta' => array(
				'forum_id' => $forum_id,
				'topic_id' => $topic_id,
			),
		) );

		$r = $this->factory->reply->create( array(
			'post_parent' => $topic_id,
			'reply_meta' => array(
				'forum_id'              => $forum_id,
				'topic_id'              => $topic_id,
			)
		) );

		bbp_unapprove_reply( $r );

		$reply_pending = bbp_is_reply_pending( $r );
		$this->assertTrue( $reply_pending );

		bbp_approve_reply( $r );

		$reply_pending = bbp_is_reply_pending( $r );
		$this->assertFalse( $reply_pending );
	}

	/**
	 * @covers ::bbp_is_reply_private
	 */
	public function test_bbp_is_reply_private() {
		$forum_id = $this->factory->forum->create();

		$topic_id = $this->factory->topic->create( array(
			'post_parent' => $forum_id,
			'topic_meta' => array(
				'forum_id' => $forum_id,
			),
		) );

		$reply_id = $this->factory->reply->create( array(
			'post_parent' => $topic_id,
			'reply_meta' => array(
				'forum_id' => $forum_id,
				'topic_id' => $topic_id,
			),
		) );

		$reply_private = bbp_is_reply_private( $reply_id );
		$this->assertFalse( $reply_private );

		$r = $this->factory->reply->create( array(
			'post_parent' => $topic_id,
			'post_status' => bbp_get_private_status_id(),
			'reply_meta' => array(
				'forum_id'              => $forum_id,
				'topic_id'              => $topic_id,
			)
		) );

		$reply_private = bbp_is_reply_private( $r );
		$this->assertTrue( $reply_private );
	}

	/**
	 * @covers ::bbp_is_reply_anonymous
	 * @todo   Implement test_bbp_is_reply_anonymous().
	 */
	public function test_bbp_is_reply_anonymous() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}
}
