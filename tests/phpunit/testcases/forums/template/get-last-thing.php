<?php

/**
 * Tests for the `bbp_get_forum_last_*()` template functions.
 *
 * @group forums
 * @group template
 * @group get_last_thing
 */
class BBP_Tests_Forums_Template_Forum_Last_Thing extends BBP_UnitTestCase {

	/**
	 * @covers ::bbp_forum_last_active_id
	 * @covers ::bbp_get_forum_last_active_id
	 */
	public function test_bbp_get_forum_last_active_id() {
		$f = $this->factory->forum->create();

		$last_id = bbp_get_forum_last_active_id( $f );
		$this->assertSame( 0, $last_id );

		bbp_update_forum_last_active_id( $f );

		$last_id = bbp_get_forum_last_active_id( $f );
		$this->assertSame( 0, $last_id );

		$t = $this->factory->topic->create( array(
			'post_parent' => $f,
			'topic_meta' => array(
				'forum_id' => $f,
			),
		) );

		bbp_update_forum_last_active_id( $f );

		$last_id = bbp_get_forum_last_active_id( $f );
		$this->assertSame( $t, $last_id );

		$r = $this->factory->reply->create( array(
			'post_parent' => $t,
			'reply_meta' => array(
				'forum_id' => $f,
				'topic_id' => $t,
			),
		) );

		bbp_update_forum_last_active_id( $f );

		$last_id = bbp_get_forum_last_active_id( $f );
		$this->assertSame( $r, $last_id );
	}

	/**
	 * @covers ::bbp_forum_last_active_id
	 * @covers ::bbp_get_forum_last_active_id
	 */
	public function test_bbp_get_forum_last_active_id_with_pending_reply() {
		$u = $this->factory->user->create_many( 2 );
		$f = $this->factory->forum->create();

		$last_id = bbp_get_forum_last_active_id( $f );
		$this->assertSame( 0, $last_id );

		bbp_update_forum_last_active_id( $f );

		$last_id = bbp_get_forum_last_active_id( $f );
		$this->assertSame( 0, $last_id );

		$t = $this->factory->topic->create( array(
			'post_parent' => $f,
			'topic_meta' => array(
				'forum_id' => $f,
			),
		) );

		bbp_update_forum_last_active_id( $f );

		$last_id = bbp_get_forum_last_active_id( $f );
		$this->assertSame( $t, $last_id );

		$r1 = $this->factory->reply->create( array(
			'post_parent' => $t,
			'reply_meta' => array(
				'forum_id' => $f,
				'topic_id' => $t,
			),
		) );

		bbp_update_forum_last_active_id( $f );

		$last_id = bbp_get_forum_last_active_id( $f );
		$this->assertSame( $r1, $last_id );

		$r2 = $this->factory->reply->create( array(
			'post_parent' => $t,
			'post_author' => $u[1],
			'post_status' => bbp_get_pending_status_id(),
			'reply_meta' => array(
				'forum_id' => $f,
				'topic_id' => $t,
			)
		) );

		$last_id = bbp_get_forum_last_active_id( $f );
		$this->assertSame( $r1, $last_id );

		bbp_approve_reply( $r2 );

		$last_id = bbp_get_forum_last_active_id( $f );
		$this->assertSame( $r2, $last_id );
	}

	/**
	 * @covers ::bbp_forum_last_active_time
	 * @covers ::bbp_get_forum_last_active_time
	 */
	public function test_bbp_get_forum_last_active_time() {
		$f = $this->factory->forum->create();

		$now = time();
		$post_date = date( 'Y-m-d H:i:s', $now - 60*60*100 );

		$last_time = bbp_get_forum_last_active_time( $f );
		$this->assertSame( '', $last_time );

		$t = $this->factory->topic->create( array(
			'post_parent' => $f,
			'post_date' => $post_date,
			'topic_meta' => array(
				'forum_id' => $f,
			),
		) );

		bbp_update_forum_last_active_time( $f );
		$last_time = bbp_get_forum_last_active_time( $f );
		$this->assertSame( '4 days, 4 hours ago', $last_time );

		$this->factory->reply->create( array(
			'post_parent' => $t,
			'post_date' => $post_date,
			'reply_meta' => array(
				'forum_id' => $f,
				'topic_id' => $t,
			),
		) );

		bbp_update_forum_last_active_time( $f );

		$last_time = bbp_get_forum_last_active_time( $f );
		$this->assertSame( '4 days, 4 hours ago', $last_time );
	}

	/**
	 * @covers ::bbp_forum_last_topic_id
	 * @covers ::bbp_get_forum_last_topic_id
	 */
	public function test_bbp_get_forum_last_topic_id() {
		$f = $this->factory->forum->create();

		$last_id = bbp_get_forum_last_topic_id( $f );
		$this->assertSame( 0, $last_id );

		bbp_update_forum_last_topic_id( $f );

		$last_id = bbp_get_forum_last_topic_id( $f );
		$this->assertSame( 0, $last_id );

		$t = $this->factory->topic->create( array(
			'post_parent' => $f,
			'topic_meta' => array(
				'forum_id' => $f,
			),
		) );

		bbp_update_forum_last_topic_id( $f );

		$last_id = bbp_get_forum_last_topic_id( $f );
		$this->assertSame( $t, $last_id );
	}

	/**
	 * @covers ::bbp_forum_last_topic_title
	 * @covers ::bbp_get_forum_last_topic_title
	 */
	public function test_bbp_get_forum_last_topic_title() {
		$f = $this->factory->forum->create();

		$t = $this->factory->topic->create( array(
			'post_parent' => $f,
			'topic_meta' => array(
				'forum_id' => $f,
			),
		) );

		$this->factory->reply->create( array(
			'post_parent' => $t,
			'reply_meta' => array(
				'forum_id' => $f,
				'topic_id' => $t,
			),
		) );

		$forum = bbp_get_forum_last_topic_title( $f );
		$this->assertSame( 'Topic 1', $forum );
	}

	/**
	 * @covers ::bbp_forum_last_topic_permalink
	 * @covers ::bbp_get_forum_last_topic_permalink
	 * @todo   Implement test_bbp_get_forum_last_topic_permalink().
	 */
	public function test_bbp_get_forum_last_topic_permalink() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_get_forum_last_topic_author_id
	 */
	public function test_bbp_get_forum_last_topic_author_id() {
		$u = $this->factory->user->create();

		$f = $this->factory->forum->create( array(
			'post_author' => $u,
		) );

		$t = $this->factory->topic->create( array(
			'post_parent' => $f,
			'post_author' => $u,
			'topic_meta' => array(
				'forum_id' => $f,
			),
		) );

		$this->factory->reply->create( array(
			'post_parent' => $t,
			'post_author' => $u,
			'reply_meta' => array(
				'forum_id' => $f,
				'topic_id' => $t,
			),
		) );

		$forum = bbp_get_forum_last_topic_author_id( $f );
		$this->assertSame( $u, $forum );
	}

	/**
	 * @covers ::bbp_forum_last_topic_author_link
	 * @covers ::bbp_get_forum_last_topic_author_link
	 * @todo   Implement test_bbp_get_forum_last_topic_author_link().
	 */
	public function test_bbp_get_forum_last_topic_author_link() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_forum_last_reply_id
	 * @covers ::bbp_get_forum_last_reply_id
	 */
	public function test_bbp_get_forum_last_reply_id() {
		$f = $this->factory->forum->create();

		$t = $this->factory->topic->create( array(
			'post_parent' => $f,
			'topic_meta' => array(
				'forum_id' => $f,
			),
		) );

		$this->factory->reply->create( array(
			'post_parent' => $t,
			'reply_meta' => array(
				'forum_id' => $f,
				'topic_id' => $t,
			),
		) );

		$last_reply_id = bbp_get_forum_last_reply_id( $f );

		$this->assertSame( $last_reply_id, bbp_forum_query_last_reply_id( $f ) );

		bbp_get_forum_last_reply_id( $f );

		$this->factory->reply->create( array(
			'post_parent' => $t,
			'reply_meta' => array(
				'forum_id' => $f,
				'topic_id' => $t,
			),
		) );

		$last_reply_id = bbp_get_forum_last_reply_id( $f );

		bbp_get_forum_last_reply_id( $f );
		$this->assertSame( $last_reply_id, bbp_forum_query_last_reply_id( $f ) );

	}

	/**
	 * @covers ::bbp_forum_last_reply_title
	 * @covers ::bbp_get_forum_last_reply_title
	 */
	public function test_bbp_get_forum_last_reply_title() {
		$f = $this->factory->forum->create();

		$t = $this->factory->topic->create( array(
			'post_parent' => $f,
			'topic_meta' => array(
				'forum_id' => $f,
			),
		) );

		$this->factory->reply->create( array(
			'post_parent' => $t,
			'reply_meta' => array(
				'forum_id' => $f,
				'topic_id' => $t,
			),
		) );

		$forum = bbp_get_forum_last_reply_title( $f );
		$this->assertSame( 'Reply To: Topic 1', $forum );
	}

	/**
	 * @covers ::bbp_forum_last_reply_permalink
	 * @covers ::bbp_get_forum_last_reply_permalink
	 * @todo   Implement test_bbp_get_forum_last_reply_permalink().
	 */
	public function test_bbp_get_forum_last_reply_permalink() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_forum_last_reply_url
	 * @covers ::bbp_get_forum_last_reply_url
	 * @todo   Implement test_bbp_get_forum_last_reply_url().
	 */
	public function test_bbp_get_forum_last_reply_url() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_forum_last_reply_author_id
	 * @covers ::bbp_get_forum_last_reply_author_id
	 */
	public function test_bbp_get_forum_last_reply_author_id() {
		$u = $this->factory->user->create();

		$f = $this->factory->forum->create( array(
			'post_author' => $u,
		) );

		$t = $this->factory->topic->create( array(
			'post_parent' => $f,
			'post_author' => $u,
			'topic_meta' => array(
				'forum_id' => $f,
			),
		) );

		$r = $this->factory->reply->create( array(
			'post_parent' => $t,
			'post_author' => $u,
			'reply_meta' => array(
				'forum_id' => $f,
				'topic_id' => $t,
			),
		) );

		$last_reply_id = bbp_get_topic_last_active_id( $f );
		$this->assertSame( $r, $last_reply_id );

		$forum = bbp_get_forum_last_reply_author_id( $f );
		$this->assertSame( $u, $forum );
	}

	/**
	 * @covers ::bbp_forum_last_reply_author_link
	 * @covers ::bbp_get_forum_last_reply_author_link
	 * @todo   Implement test_bbp_get_forum_last_reply_author_link().
	 */
	public function test_bbp_get_forum_last_reply_author_link() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_forum_last_topic_id
	 * @covers ::bbp_get_forum_last_topic_id
	 * @covers ::bbp_forum_last_reply_id
	 * @covers ::bbp_get_forum_last_reply_id
	 * @covers ::bbp_topic_last_reply_id
	 * @covers ::bbp_get_topic_last_reply_id
	 */
	public function test_bbp_get_forum_and_topic_last_topic_id_and_last_reply_id() {

		$f = $this->factory->forum->create();

		// Get the forums last topic id _bbp_last_topic_id
		$this->assertSame( 0, bbp_get_forum_last_topic_id( $f ) );

		$t = $this->factory->topic->create( array(
			'post_parent' => $f,
			'topic_meta' => array(
				'forum_id' => $f,
			)
		) );

		// Get the forums last topic id _bbp_last_topic_id
		$this->assertSame( $t, bbp_get_forum_last_topic_id( $f ) );

		// Get the topics last reply id _bbp_last_reply_id
		$this->assertSame( 0, bbp_get_topic_last_reply_id( $t ) );

		// Create another reply
		$r = $this->factory->reply->create( array(
			'post_parent' => $t,
			'reply_meta' => array(
				'forum_id' => $f,
				'topic_id' => $t,
			)
		) );

		// Get the topics last reply id _bbp_last_reply_id
		$this->assertSame( $r, bbp_get_topic_last_reply_id( $t ) );
	}
}
