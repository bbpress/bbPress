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
		$c = $this->factory->forum->create( array(
			'forum_meta' => array(
				'_bbp_forum_type' => 'category',
				'_bbp_status'     => 'open',
			),
		) );

		$f = $this->factory->forum->create( array(
			'post_parent' => $c,
			'forum_meta' => array(
				'forum_id'        => $c,
				'_bbp_forum_type' => 'forum',
				'_bbp_status'     => 'open',
			),
		) );

		// Get the forums last active id.
		$last_id = bbp_get_forum_last_active_id( $f );
		$this->assertSame( 0, $last_id );

		// Get the categories last active id.
		$last_id = bbp_get_forum_last_active_id( $c );
		$this->assertSame( 0, $last_id );

		bbp_update_forum_last_active_id( $f );

		// Get the forums last active id.
		$last_id = bbp_get_forum_last_active_id( $f );
		$this->assertSame( 0, $last_id );

		// Get the categories last active id.
		$last_id = bbp_get_forum_last_active_id( $c );
		$this->assertSame( 0, $last_id );

		$t = $this->factory->topic->create( array(
			'post_parent' => $f,
			'topic_meta' => array(
				'forum_id' => $f,
			),
		) );

		bbp_update_forum_last_active_id( $f );

		// Get the forums last active id.
		$last_id = bbp_get_forum_last_active_id( $f );
		$this->assertSame( $t, $last_id );

		// Get the categories last active id.
		$last_id = bbp_get_forum_last_active_id( $c );
		$this->assertSame( $t, $last_id );

		$r = $this->factory->reply->create( array(
			'post_parent' => $t,
			'reply_meta' => array(
				'forum_id' => $f,
				'topic_id' => $t,
			),
		) );

		bbp_update_forum_last_active_id( $f );

		// Get the forums last active id.
		$last_id = bbp_get_forum_last_active_id( $f );
		$this->assertSame( $r, $last_id );

		// Get the categories last active id.
		$last_id = bbp_get_forum_last_active_id( $c );
		$this->assertSame( $r, $last_id );
	}

	/**
	 * @covers ::bbp_forum_last_active_id
	 * @covers ::bbp_get_forum_last_active_id
	 */
	public function test_bbp_get_forum_last_active_id_with_pending_reply() {
		$u = $this->factory->user->create_many( 2 );

		$c = $this->factory->forum->create( array(
			'forum_meta' => array(
				'_bbp_forum_type' => 'category',
				'_bbp_status'     => 'open',
			),
		) );


		$f = $this->factory->forum->create( array(
			'post_parent' => $c,
			'forum_meta' => array(
				'forum_id'        => $c,
				'_bbp_forum_type' => 'forum',
				'_bbp_status'     => 'open',
			),
		) );

		// Get the forums last active id.
		$last_id = bbp_get_forum_last_active_id( $f );
		$this->assertSame( 0, $last_id );

		// Get the categories last active id.
		$last_id = bbp_get_forum_last_active_id( $c );
		$this->assertSame( 0, $last_id );

		bbp_update_forum_last_active_id( $f );

		// Get the forums last active id.
		$last_id = bbp_get_forum_last_active_id( $f );
		$this->assertSame( 0, $last_id );

		// Get the categories last active id.
		$last_id = bbp_get_forum_last_active_id( $c );
		$this->assertSame( 0, $last_id );

		$t = $this->factory->topic->create( array(
			'post_parent' => $f,
			'topic_meta' => array(
				'forum_id' => $f,
			),
		) );

		bbp_update_forum_last_active_id( $f );

		// Get the forums last active id.
		$last_id = bbp_get_forum_last_active_id( $f );
		$this->assertSame( $t, $last_id );

		// Get the categories last active id.
		$last_id = bbp_get_forum_last_active_id( $c );
		$this->assertSame( $t, $last_id );

		$r1 = $this->factory->reply->create( array(
			'post_parent' => $t,
			'reply_meta' => array(
				'forum_id' => $f,
				'topic_id' => $t,
			),
		) );

		bbp_update_forum_last_active_id( $f );

		// Get the forums last active id.
		$last_id = bbp_get_forum_last_active_id( $f );
		$this->assertSame( $r1, $last_id );

		// Get the categories last active id.
		$last_id = bbp_get_forum_last_active_id( $c );

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

		// Get the forums last active id.
		$last_id = bbp_get_forum_last_active_id( $f );
		$this->assertSame( $r1, $last_id );

		// Get the categories last active id.
		$last_id = bbp_get_forum_last_active_id( $c );
		$this->assertSame( $r1, $last_id );

		bbp_approve_reply( $r2 );

		// Get the forums last active id.
		$last_id = bbp_get_forum_last_active_id( $f );
		$this->assertSame( $r2, $last_id );

		// Get the categories last active id.
		$last_id = bbp_get_forum_last_active_id( $c );
		$this->assertSame( $r2, $last_id );
	}

	/**
	 * @covers ::bbp_forum_last_active_time
	 * @covers ::bbp_get_forum_last_active_time
	 */
	public function test_bbp_get_forum_last_active_time() {
		$c = $this->factory->forum->create( array(
			'forum_meta' => array(
				'_bbp_forum_type' => 'category',
				'_bbp_status'     => 'open',
			),
		) );


		$f = $this->factory->forum->create( array(
			'post_parent' => $c,
			'forum_meta' => array(
				'forum_id'        => $c,
				'_bbp_forum_type' => 'forum',
				'_bbp_status'     => 'open',
			),
		) );

		$now = time();
		$post_date = date( 'Y-m-d H:i:s', $now - 60 * 60 * 100 );

		// Get the forums last active time.
		$last_time = bbp_get_forum_last_active_time( $f );
		$this->assertSame( '', $last_time );

		// Get the categories last active time.
		$last_time = bbp_get_forum_last_active_time( $c );
		$this->assertSame( '', $last_time );

		$t = $this->factory->topic->create( array(
			'post_parent' => $f,
			'post_date' => $post_date,
			'topic_meta' => array(
				'forum_id' => $f,
			),
		) );

		bbp_update_forum_last_active_time( $f );

		// Get the forums last active time.
		$last_time = bbp_get_forum_last_active_time( $f );
		$this->assertSame( '4 days, 4 hours ago', $last_time );

		// Get the categories last active time.
		$last_time = bbp_get_forum_last_active_time( $c );
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

		// Get the forums last active time.
		$last_time = bbp_get_forum_last_active_time( $f );
		$this->assertSame( '4 days, 4 hours ago', $last_time );

		// Get the categories last active time.
		$last_time = bbp_get_forum_last_active_time( $c );
		$this->assertSame( '4 days, 4 hours ago', $last_time );
	}

	/**
	 * @covers ::bbp_forum_last_topic_id
	 * @covers ::bbp_get_forum_last_topic_id
	 */
	public function test_bbp_get_forum_last_topic_id() {
		$c = $this->factory->forum->create( array(
			'forum_meta' => array(
				'_bbp_forum_type' => 'category',
				'_bbp_status'     => 'open',
			),
		) );


		$f = $this->factory->forum->create( array(
			'post_parent' => $c,
			'forum_meta' => array(
				'forum_id'        => $c,
				'_bbp_forum_type' => 'forum',
				'_bbp_status'     => 'open',
			),
		) );

		// Get the forums last topic id.
		$last_id = bbp_get_forum_last_topic_id( $f );
		$this->assertSame( 0, $last_id );

		// Get the categories last topic id.
		$last_id = bbp_get_forum_last_topic_id( $c );
		$this->assertSame( 0, $last_id );

		bbp_update_forum_last_topic_id( $f );

		// Get the forums last topic id.
		$last_id = bbp_get_forum_last_topic_id( $f );
		$this->assertSame( 0, $last_id );

		// Get the categories last topic id.
		$last_id = bbp_get_forum_last_topic_id( $c );
		$this->assertSame( 0, $last_id );

		$t = $this->factory->topic->create( array(
			'post_parent' => $f,
			'topic_meta' => array(
				'forum_id' => $f,
			),
		) );

		bbp_update_forum_last_topic_id( $f );

		// Get the forums last topic id.
		$last_id = bbp_get_forum_last_topic_id( $f );
		$this->assertSame( $t, $last_id );

		// Get the categories last topic id.
		$last_id = bbp_get_forum_last_topic_id( $c );
		$this->assertSame( $t, $last_id );
	}

	/**
	 * @covers ::bbp_forum_last_topic_title
	 * @covers ::bbp_get_forum_last_topic_title
	 */
	public function test_bbp_get_forum_last_topic_title() {
		$c = $this->factory->forum->create( array(
			'forum_meta' => array(
				'_bbp_forum_type' => 'category',
				'_bbp_status'     => 'open',
			),
		) );


		$f = $this->factory->forum->create( array(
			'post_parent' => $c,
			'forum_meta' => array(
				'forum_id'        => $c,
				'_bbp_forum_type' => 'forum',
				'_bbp_status'     => 'open',
			),
		) );

		$t = $this->factory->topic->create( array(
			'post_title' => 'Topic 1',
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

		// Get the forums last topic title.
		$forum = bbp_get_forum_last_topic_title( $f );
		$this->assertSame( 'Topic 1', $forum );

		// Get the categories last topic title.
		$category = bbp_get_forum_last_topic_title( $c );
		$this->assertSame( 'Topic 1', $category );
	}

	/**
	 * @covers ::bbp_forum_last_topic_permalink
	 * @covers ::bbp_get_forum_last_topic_permalink
	 */
	public function test_bbp_get_forum_last_topic_permalink() {
		if ( is_multisite() ) {
			$this->markTestSkipped( 'Skipping URL tests in multisite for now.' );
		}

		$c = $this->factory->forum->create( array(
			'forum_meta' => array(
				'_bbp_forum_type' => 'category',
				'_bbp_status'     => 'open',
			),
		) );


		$f = $this->factory->forum->create( array(
			'post_parent' => $c,
			'forum_meta' => array(
				'forum_id'        => $c,
				'_bbp_forum_type' => 'forum',
				'_bbp_status'     => 'open',
			),
		) );

		$t = $this->factory->topic->create( array(
			'post_parent' => $f,
			'topic_meta' => array(
				'forum_id' => $f,
			)
		) );

		// Get the forums last topic permalink.
		$forum_last_topic_permalink = bbp_get_forum_last_topic_permalink( $f );
		$this->assertSame( bbp_get_topic_permalink( $t ), $forum_last_topic_permalink );

		// Get the categories last topic permalink.
		$forum_last_topic_permalink = bbp_get_forum_last_topic_permalink( $c );
		$this->assertSame( bbp_get_topic_permalink( $t ), $forum_last_topic_permalink );
	}

	/**
	 * @covers ::bbp_get_forum_last_topic_author_id
	 */
	public function test_bbp_get_forum_last_topic_author_id() {
		$u = $this->factory->user->create();

		$c = $this->factory->forum->create( array(
			'forum_meta' => array(
				'_bbp_forum_type' => 'category',
				'_bbp_status'     => 'open',
			),
		) );


		$f = $this->factory->forum->create( array(
			'post_author' => $u,
			'post_parent' => $c,
			'forum_meta' => array(
				'forum_id'        => $c,
				'_bbp_forum_type' => 'forum',
				'_bbp_status'     => 'open',
			),
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

		// Get the forums last author id.
		$forum = bbp_get_forum_last_topic_author_id( $f );
		$this->assertSame( $u, $forum );

		// Get the categories last author id.
		$forum = bbp_get_forum_last_topic_author_id( $c );
		$this->assertSame( $u, $forum );
	}

	/**
	 * @covers ::bbp_forum_last_topic_author_link
	 * @covers ::bbp_get_forum_last_topic_author_link	 */
	public function test_bbp_get_forum_last_topic_author_link() {
		if ( is_multisite() ) {
			$this->markTestSkipped( 'Skipping URL tests in multisite for now.' );
		}

		$u = $this->factory->user->create();

		$c = $this->factory->forum->create( array(
			'forum_meta' => array(
				'_bbp_forum_type' => 'category',
				'_bbp_status'     => 'open',
			),
		) );


		$f = $this->factory->forum->create( array(
			'post_parent' => $c,
			'forum_meta' => array(
				'forum_id'        => $c,
				'_bbp_forum_type' => 'forum',
				'_bbp_status'     => 'open',
			),
		) );

		$t = $this->factory->topic->create( array(
			'post_parent' => $f,
			'post_author' => $u,
			'topic_meta' => array(
				'forum_id' => $f,
			)
		) );

		// Get the forums last topic author link.
		$last_topic_author_link = bbp_get_forum_last_topic_author_link( $f );
		$this->assertSame( bbp_get_user_profile_link( $u ), $last_topic_author_link );

		// Get the categories last topic author link.
		$last_topic_author_link = bbp_get_forum_last_topic_author_link( $c );
		$this->assertSame( bbp_get_user_profile_link( $u ), $last_topic_author_link );
	}

	/**
	 * @covers ::bbp_forum_last_reply_id
	 * @covers ::bbp_get_forum_last_reply_id
	 */
	public function test_bbp_get_forum_last_reply_id() {
		$c = $this->factory->forum->create( array(
			'forum_meta' => array(
				'_bbp_forum_type' => 'category',
				'_bbp_status'     => 'open',
			),
		) );


		$f = $this->factory->forum->create( array(
			'post_parent' => $c,
			'forum_meta' => array(
				'forum_id'        => $c,
				'_bbp_forum_type' => 'forum',
				'_bbp_status'     => 'open',
			),
		) );

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

		// Get the forums last reply id.
		$last_reply_id_f = bbp_get_forum_last_reply_id( $f );
		$this->assertSame( $last_reply_id_f, bbp_forum_query_last_reply_id( $f ) );

		// Get the categories last reply id.
		$last_reply_id_c = bbp_get_forum_last_reply_id( $c );
		$this->assertSame( $last_reply_id_c, bbp_forum_query_last_reply_id( $c ) );

		$this->factory->reply->create( array(
			'post_parent' => $t,
			'reply_meta' => array(
				'forum_id' => $f,
				'topic_id' => $t,
			),
		) );

		// Get the forums last reply id.
		$last_reply_id_f = bbp_get_forum_last_reply_id( $f );
		$this->assertSame( $last_reply_id_f, bbp_forum_query_last_reply_id( $f ) );

		// Get the categories last reply id.
		$last_reply_id_c = bbp_get_forum_last_reply_id( $c );
		$this->assertSame( $last_reply_id_c, bbp_forum_query_last_reply_id( $c ) );
	}

	/**
	 * @covers ::bbp_forum_last_reply_title
	 * @covers ::bbp_get_forum_last_reply_title
	 */
	public function test_bbp_get_forum_last_reply_title() {
		$c = $this->factory->forum->create( array(
			'forum_meta' => array(
				'_bbp_forum_type' => 'category',
				'_bbp_status'     => 'open',
			),
		) );


		$f = $this->factory->forum->create( array(
			'post_parent' => $c,
			'forum_meta' => array(
				'forum_id'        => $c,
				'_bbp_forum_type' => 'forum',
				'_bbp_status'     => 'open',
			),
		) );

		$t = $this->factory->topic->create( array(
			'post_parent' => $f,
			'topic_meta' => array(
				'forum_id' => $f,
			),
		) );

		$this->factory->reply->create( array(
			'post_title' => 'Reply To: Topic 1',
			'post_parent' => $t,
			'reply_meta' => array(
				'forum_id' => $f,
				'topic_id' => $t,
			),
		) );

		// Get the forums last reply title.
		$forum = bbp_get_forum_last_reply_title( $f );
		$this->assertSame( 'Reply To: Topic 1', $forum );

		// Get the categories last reply title.
		$category = bbp_get_forum_last_reply_title( $c );
		$this->assertSame( 'Reply To: Topic 1', $category );
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

		$c = $this->factory->forum->create( array(
			'forum_meta' => array(
				'_bbp_forum_type' => 'category',
				'_bbp_status'     => 'open',
			),
		) );


		$f = $this->factory->forum->create( array(
			'post_author' => $u,
			'post_parent' => $c,
			'forum_meta' => array(
				'forum_id'        => $c,
				'_bbp_forum_type' => 'forum',
				'_bbp_status'     => 'open',
			),
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

		// Get the forums last reply author id.
		$forum = bbp_get_forum_last_reply_author_id( $f );
		$this->assertSame( $u, $forum );

		// Get the categories last reply author id.
		$category = bbp_get_forum_last_reply_author_id( $c );
		$this->assertSame( $u, $category );
	}

	/**
	 * @covers ::bbp_forum_last_reply_author_link
	 * @covers ::bbp_get_forum_last_reply_author_link
	 */
	public function test_bbp_get_forum_last_reply_author_link() {
		if ( is_multisite() ) {
			$this->markTestSkipped( 'Skipping URL tests in multisite for now.' );
		}

		$u = $this->factory->user->create();

		$c = $this->factory->forum->create( array(
			'forum_meta' => array(
				'_bbp_forum_type' => 'category',
				'_bbp_status'     => 'open',
			),
		) );


		$f = $this->factory->forum->create( array(
			'post_author' => $u,
			'post_parent' => $c,
			'forum_meta' => array(
				'forum_id'        => $c,
				'_bbp_forum_type' => 'forum',
				'_bbp_status'     => 'open',
			),
		) );

		$t = $this->factory->topic->create( array(
			'post_parent' => $f,
			'post_author' => $u,
			'topic_meta' => array(
				'forum_id' => $f,
			)
		) );

		$r = $this->factory->reply->create( array(
			'post_parent' => $t,
			'post_author' => $u,
			'reply_meta' => array(
				'forum_id' => $f,
				'topic_id' => $t,
			),
		) );

		// Get the forums last reply author link.
		$last_reply_author_link = bbp_get_forum_last_reply_author_link( $f );
		$this->assertSame( bbp_get_user_profile_link( $u ), $last_reply_author_link );

		// Get the categories last reply author link.
		$last_reply_author_link = bbp_get_forum_last_reply_author_link( $c );
		$this->assertSame( bbp_get_user_profile_link( $u ), $last_reply_author_link );
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
		$c = $this->factory->forum->create( array(
			'forum_meta' => array(
				'_bbp_forum_type' => 'category',
				'_bbp_status'     => 'open',
			),
		) );


		$f = $this->factory->forum->create( array(
			'post_parent' => $c,
			'forum_meta' => array(
				'forum_id'        => $c,
				'_bbp_forum_type' => 'forum',
				'_bbp_status'     => 'open',
			),
		) );

		// Get the forums last topic id _bbp_last_topic_id.
		$this->assertSame( 0, bbp_get_forum_last_topic_id( $f ) );

		// Get the category last topic id _bbp_last_topic_id.
		$this->assertSame( 0, bbp_get_forum_last_topic_id( $c ) );

		// Get the forums last reply id _bbp_last_reply_id.
		$this->assertSame( 0, bbp_get_forum_last_reply_id( $f ) );

		// Get the category last reply id _bbp_last_reply_id.
		$this->assertSame( 0, bbp_get_forum_last_reply_id( $c ) );

		$t = $this->factory->topic->create( array(
			'post_parent' => $f,
			'topic_meta' => array(
				'forum_id' => $f,
			)
		) );

		// Get the forums last topic id _bbp_last_topic_id.
		$this->assertSame( $t, bbp_get_forum_last_topic_id( $f ) );

		// Get the category last topic id _bbp_last_topic_id.
		$this->assertSame( $t, bbp_get_forum_last_topic_id( $c ) );

		// Create another reply.
		$r = $this->factory->reply->create( array(
			'post_parent' => $t,
			'reply_meta' => array(
				'forum_id' => $f,
				'topic_id' => $t,
			)
		) );

		// Get the forums last reply id _bbp_last_reply_id.
		$this->assertSame( $r, bbp_get_forum_last_reply_id( $f ) );

		// Get the category last reply id _bbp_last_reply_id.
		$this->assertSame( $r, bbp_get_forum_last_reply_id( $c ) );

		// Get the topics last reply id _bbp_last_reply_id.
		$this->assertSame( $r, bbp_get_topic_last_reply_id( $t ) );
	}
}
