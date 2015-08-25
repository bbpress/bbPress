<?php

/**
 * Tests for the topic component statuses and types functions.
 *
 * @group topics
 * @group functions
 * @group status
 */
class BBP_Tests_Topics_Functions_Status extends BBP_UnitTestCase {

	/**
	 * @covers ::bbp_get_topic_statuses
	 * @todo   Implement test_bbp_get_topic_statuses().
	 */
	public function test_bbp_get_topic_statuses() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_get_topic_types
	 * @todo   Implement test_bbp_get_topic_types().
	 */
	public function test_bbp_get_topic_types() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_get_stickies
	 * @todo   Implement test_bbp_get_stickies().
	 */
	public function test_bbp_get_stickies() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_get_super_stickies
	 * @todo   Implement test_bbp_get_super_stickies().
	 */
	public function test_bbp_get_super_stickies() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_close_topic
	 * @todo   Implement test_bbp_close_topic().
	 */
	public function test_bbp_close_topic() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_open_topic
	 * @todo   Implement test_bbp_open_topic().
	 */
	public function test_bbp_open_topic() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_spam_topic
	 */
	public function test_bbp_spam_topic() {
		$f = $this->factory->forum->create();

		$now = time();
		$post_date_topic = date( 'Y-m-d H:i:s', $now - 60 * 60 * 100 );
		$post_date_reply = date( 'Y-m-d H:i:s', $now - 60 * 60 * 80 );

		$topic_time = '4 days, 4 hours ago';
		$reply_time = '3 days, 8 hours ago';

		$t = $this->factory->topic->create( array(
			'post_parent' => $f,
			'post_date' => $post_date_topic,
			'topic_meta' => array(
				'forum_id' => $f,
			),
		) );
		$r = $this->factory->reply->create_many( 2, array(
			'post_parent' => $t,
			'post_date' => $post_date_reply,
			'reply_meta' => array(
				'forum_id' => $f,
				'topic_id' => $t,
			),
		) );

		bbp_spam_topic( $t );

		$count = bbp_get_forum_topic_count( $f, false, true );
		$this->assertSame( 0, $count );

		$count = bbp_get_forum_topic_count_hidden( $f, true );
		$this->assertSame( 1, $count );

		$count = bbp_get_forum_reply_count( $f, false, true );
		$this->assertSame( 0, $count );

		$last_topic_id = bbp_get_forum_last_topic_id( $f );
		$this->assertSame( $t, $last_topic_id );

		$last_reply_id = bbp_get_forum_last_reply_id( $f );
		$this->assertSame( $t, $last_reply_id );

		$last_active_id = bbp_get_forum_last_active_id( $f );
		$this->assertSame( $t, $last_active_id );

		$last_active_time = bbp_get_forum_last_active_time( $f );
		$this->assertSame( $topic_time, $last_active_time );

		$count = bbp_get_topic_reply_count( $t, true, true );
		$this->assertSame( 0, $count );

		$count = bbp_get_topic_reply_count_hidden( $t, true, true );
		$this->assertSame( 2, $count );

		// ToDo: Result should be 0 when a topic has no replies
	//	$last_reply_id = bbp_get_topic_last_reply_id( $t );
	//	$this->assertSame( $t, $last_reply_id );

		$last_active_id = bbp_get_topic_last_active_id( $t );
		$this->assertSame( $t, $last_active_id );

		$last_active_time = bbp_get_topic_last_active_time( $t );
		$this->assertSame( $topic_time, $last_active_time );

		$topic_spam_status = get_post_status( $t );
		$this->assertSame( bbp_get_spam_status_id(), $topic_spam_status );

		$topic_meta_pre_spammed_replies = get_post_meta( $t, '_bbp_pre_spammed_replies', true );
		$this->assertEquals( array( $r[1], $r[0] ), $topic_meta_pre_spammed_replies );

		$topic_spam_meta_status = get_post_meta( $t, '_bbp_spam_meta_status', true );
		$this->assertSame( bbp_get_public_status_id(), $topic_spam_meta_status );
	}

	/**
	 * @covers ::bbp_spam_topic_replies
	 */
	public function test_bbp_spam_topic_replies() {
		$f = $this->factory->forum->create();

		$now = time();
		$post_date_topic = date( 'Y-m-d H:i:s', $now - 60 * 60 * 100 );
		$post_date_reply = date( 'Y-m-d H:i:s', $now - 60 * 60 * 80 );

		$topic_time = '4 days, 4 hours ago';
		$reply_time = '3 days, 8 hours ago';

		$t = $this->factory->topic->create( array(
			'post_parent' => $f,
			'post_date' => $post_date_topic,
			'topic_meta' => array(
				'forum_id' => $f,
			),
		) );
		$r = $this->factory->reply->create_many( 2, array(
			'post_parent' => $t,
			'post_date' => $post_date_reply,
			'reply_meta' => array(
				'forum_id' => $f,
				'topic_id' => $t,
			),
		) );

		bbp_spam_topic_replies( $t );

		$count = bbp_get_forum_reply_count( $f, false, true );
		$this->assertSame( 0, $count );

		$last_reply_id = bbp_get_forum_last_reply_id( $f );
		$this->assertSame( $t, $last_reply_id );

		$last_active_id = bbp_get_forum_last_active_id( $f );
		$this->assertSame( $t, $last_active_id );

		// ToDo: Result should be topic time when a topic has no replies
	//	$last_active_time = bbp_get_forum_last_active_time( $f );
	//	$this->assertSame( $topic_time, $last_active_time );

		$count = bbp_get_topic_reply_count( $t, true, true );
		$this->assertSame( 0, $count );

		$count = bbp_get_topic_reply_count_hidden( $t, true, true );
		$this->assertSame( 2, $count );

		// ToDo: Result should be 0 when a topic has no replies
	//	$last_reply_id = bbp_get_topic_last_reply_id( $t );
	//	$this->assertSame( $t, $last_reply_id );

		$last_active_id = bbp_get_topic_last_active_id( $t );
		$this->assertSame( $t, $last_active_id );

		$last_active_time = bbp_get_topic_last_active_time( $t );
		$this->assertSame( $topic_time, $last_active_time );

		$topic_meta_pre_spammed_replies = get_post_meta( $t, '_bbp_pre_spammed_replies', true );
		$this->assertEquals( array( $r[1], $r[0] ), $topic_meta_pre_spammed_replies );

		foreach ( $r as $reply ) {
			$reply_status = get_post_status( $reply );
			$this->assertSame( bbp_get_trash_status_id(), $reply_status );

			$reply_meta_status = get_post_meta( $reply, '_wp_trash_meta_status', true );
			$this->assertSame( bbp_get_public_status_id(), $reply_meta_status );
		}
	}

	/**
	 * @covers ::bbp_unspam_topic
	 */
	public function test_bbp_unspam_topic() {
		$f = $this->factory->forum->create();

		$now = time();
		$post_date_topic = date( 'Y-m-d H:i:s', $now - 60 * 60 * 100 );
		$post_date_reply = date( 'Y-m-d H:i:s', $now - 60 * 60 * 80 );

		$topic_time = '4 days, 4 hours ago';
		$reply_time = '3 days, 8 hours ago';

		$t = $this->factory->topic->create( array(
			'post_parent' => $f,
			'post_date' => $post_date_topic,
			'topic_meta' => array(
				'forum_id' => $f,
			),
		) );
		$r = $this->factory->reply->create_many( 2, array(
			'post_parent' => $t,
			'post_date' => $post_date_reply,
			'reply_meta' => array(
				'forum_id' => $f,
				'topic_id' => $t,
			),
		) );

		bbp_spam_topic( $t );

		bbp_unspam_topic( $t );

		$topic_status = get_post_status( $t );
		$this->assertSame( bbp_get_public_status_id(), $topic_status );

		$this->assertEquals( '', get_post_meta( $t, '_bbp_pre_spammed_replies', true ) );
		$this->assertEquals( array(), get_post_meta( $t, '_bbp_pre_spammed_replies', false ) );

		$this->assertEquals( '', get_post_meta( $t, '_bbp_spam_meta_status', true ) );
		$this->assertEquals( array(), get_post_meta( $t, '_bbp_spam_meta_status', false ) );

		$count = bbp_get_forum_topic_count( $f, false, true );
		$this->assertSame( 1, $count );

		$count = bbp_get_forum_topic_count_hidden( $f, true );
		$this->assertSame( 0, $count );

		$count = bbp_get_forum_reply_count( $f, false, true );
		$this->assertSame( 2, $count );

		$last_topic_id = bbp_get_forum_last_topic_id( $f );
		$this->assertSame( $t, $last_topic_id );

		$last_reply_id = bbp_get_forum_last_reply_id( $f );
		$this->assertSame( $r[1], $last_reply_id );

		$last_active_id = bbp_get_forum_last_active_id( $f );
		$this->assertSame( $r[1], $last_active_id );

		$last_active_time = bbp_get_forum_last_active_time( $f );
		$this->assertSame( $reply_time, $last_active_time );

		$count = bbp_get_topic_reply_count( $t, true, true );
		$this->assertSame( 2, $count );

		$count = bbp_get_topic_reply_count_hidden( $t, true, true );
		$this->assertSame( 0, $count );

		$last_reply_id = bbp_get_topic_last_reply_id( $t );
		$this->assertSame( $r[1], $last_reply_id );

		$last_active_id = bbp_get_topic_last_active_id( $t );
		$this->assertSame( $r[1], $last_active_id );

		$last_active_time = bbp_get_topic_last_active_time( $t );
		$this->assertSame( $reply_time, $last_active_time );
	}

	/**
	 * @covers ::bbp_unspam_topic_replies
	 */
	public function test_bbp_unspam_topic_replies() {
		$f = $this->factory->forum->create();

		$now = time();
		$post_date_topic = date( 'Y-m-d H:i:s', $now - 60 * 60 * 100 );
		$post_date_reply = date( 'Y-m-d H:i:s', $now - 60 * 60 * 80 );

		$topic_time = '4 days, 4 hours ago';
		$reply_time = '3 days, 8 hours ago';

		$t = $this->factory->topic->create( array(
			'post_parent' => $f,
			'post_date' => $post_date_topic,
			'topic_meta' => array(
				'forum_id' => $f,
			),
		) );
		$r = $this->factory->reply->create_many( 2, array(
			'post_parent' => $t,
			'post_date' => $post_date_reply,
			'reply_meta' => array(
				'forum_id' => $f,
				'topic_id' => $t,
			),
		) );

		bbp_spam_topic_replies( $t );

		bbp_unspam_topic_replies( $t );

		$this->assertEquals( '', get_post_meta( $t, '_bbp_pre_spammed_replies', true ) );
		$this->assertEquals( array(), get_post_meta( $t, '_bbp_pre_spammed_replies', false ) );

		foreach ( $r as $reply ) {
			$reply_status = get_post_status( $reply );
			$this->assertSame( bbp_get_public_status_id(), $reply_status );

			$this->assertEquals( '', get_post_meta( $reply, '_wp_trash_meta_status', true ) );
			$this->assertEquals( array(), get_post_meta( $reply, '_wp_trash_meta_status', false ) );
		}

		$count = bbp_get_forum_reply_count( $f, false, true );
		$this->assertSame( 2, $count );

		$last_reply_id = bbp_get_forum_last_reply_id( $f );
		$this->assertSame( $r[1], $last_reply_id );

		$last_active_id = bbp_get_forum_last_active_id( $f );
		$this->assertSame( $r[1], $last_active_id );

		$last_active_time = bbp_get_forum_last_active_time( $f );
		$this->assertSame( $reply_time, $last_active_time );

		$count = bbp_get_topic_reply_count( $t, true, true );
		$this->assertSame( 2, $count );

		$count = bbp_get_topic_reply_count_hidden( $t, true, true );
		$this->assertSame( 0, $count );

		$last_reply_id = bbp_get_topic_last_reply_id( $t );
		$this->assertSame( $r[1], $last_reply_id );

		$last_active_id = bbp_get_topic_last_active_id( $t );
		$this->assertSame( $r[1], $last_active_id );

		$last_active_time = bbp_get_topic_last_active_time( $t );
		$this->assertSame( $reply_time, $last_active_time );
	}

	/**
	 * @covers ::bbp_stick_topic
	 * @todo   Implement test_bbp_stick_topic().
	 */
	public function test_bbp_stick_topic() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_approve_topic
	 * @todo   Implement test_bbp_approve_topic().
	 */
	public function test_bbp_approve_topic() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_unapprove_topic
	 * @todo   Implement test_bbp_unapprove_topic().
	 */
	public function test_bbp_unapprove_topic() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_unstick_topic
	 * @todo   Implement test_bbp_unstick_topic().
	 */
	public function test_bbp_unstick_topic() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}
}
