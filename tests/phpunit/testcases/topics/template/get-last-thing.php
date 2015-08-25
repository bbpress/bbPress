<?php

/**
 * Tests for the `bbp_get_topic__last_*()` template functions.
 *
 * @group topics
 * @group template
 * @group get_last_thing
 */
class BBP_Tests_Topics_Template_Get_Topic_Last_Thing extends BBP_UnitTestCase {

	/**
	 * @covers ::bbp_topic_last_active_id
	 * @covers ::bbp_get_topic_last_active_id
	 */
	public function test_bbp_get_topic_last_active_id() {
		$f = $this->factory->forum->create();
		$t = $this->factory->topic->create( array(
			'post_parent' => $f,
			'topic_meta' => array(
				'forum_id' => $f,
			)
		) );

		$topic_last_active_id = bbp_get_topic_last_active_id( $t );
		$this->assertSame( $t, $topic_last_active_id );

		$r = $this->factory->reply->create( array(
			'post_parent' => $t,
			'reply_meta' => array(
				'forum_id' => $f,
				'topic_id' => $t,
			)
		) );

		$topic_last_active_id = bbp_get_topic_last_active_id( $t );
		$this->assertSame( $r, $topic_last_active_id );
	}

	/**
	 * @covers ::bbp_topic_last_active_time
	 * @covers ::bbp_get_topic_last_active_time
	 */
	public function test_bbp_get_topic_last_active_time() {
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

		// Output.
		$this->expectOutputString( $topic_time );
		bbp_topic_last_active_time( $t );

		// Topic time.
		$datetime = bbp_get_topic_last_active_time( $t );
		$this->assertSame( $topic_time, $datetime );

		$this->factory->reply->create( array(
			'post_parent' => $t,
			'post_date' => $post_date_reply,
			'reply_meta' => array(
				'forum_id' => $f,
				'topic_id' => $t,
			),
		) );

		// Reply time.
		$datetime = bbp_get_topic_last_active_time( $t );
		$this->assertSame( '3 days, 8 hours ago', $datetime );

	}

	/**
	 * @covers ::bbp_topic_last_reply_id
	 * @covers ::bbp_get_topic_last_reply_id
	 */
	public function test_bbp_get_topic_last_reply_id() {
		$f = $this->factory->forum->create();
		$t = $this->factory->topic->create( array(
			'post_parent' => $f,
			'topic_meta' => array(
				'forum_id' => $f,
			)
		) );

		$topic_last_reply_id = bbp_get_topic_last_reply_id( $t );
		$this->assertSame( 0, $topic_last_reply_id );

		$r = $this->factory->reply->create( array(
			'post_parent' => $t,
			'reply_meta' => array(
				'forum_id' => $f,
				'topic_id' => $t,
			)
		) );

		$topic_last_reply_id = bbp_get_topic_last_reply_id( $t );
		$this->assertSame( $r, $topic_last_reply_id );
	}

	/**
	 * @covers ::bbp_topic_last_reply_title
	 * @covers ::bbp_get_topic_last_reply_title
	 */
	public function test_bbp_get_topic_last_reply_title() {
		$f = $this->factory->forum->create();
		$t = $this->factory->topic->create( array(
			'post_parent' => $f,
			'topic_meta' => array(
				'forum_id' => $f,
			)
		) );

		$title = bbp_get_topic_last_reply_title( $t );
		$this->assertSame( '', $title );

		$this->factory->reply->create( array(
			'post_parent' => $t,
			'reply_meta' => array(
				'forum_id' => $f,
				'topic_id' => $t,
			)
		) );

		$title = bbp_get_topic_last_reply_title( $t );
		$this->assertSame( 'Reply To: ' . bbp_get_topic_title( $t ), $title );
	}

	/**
	 * @covers ::bbp_topic_last_reply_permalink
	 * @covers ::bbp_get_topic_last_reply_permalink
	 */
	public function test_bbp_get_topic_last_reply_permalink() {
		if ( is_multisite() ) {
			$this->markTestSkipped( 'Skipping URL tests in multiste for now.' );
		}

		$f = $this->factory->forum->create();

		$t = $this->factory->topic->create( array(
			'post_parent' => $f,
			'topic_meta' => array(
				'forum_id' => $f,
			)
		) );

		$topic_last_reply_permalink = bbp_get_topic_last_reply_permalink( $f );
		$this->assertSame( bbp_get_topic_permalink( $t ), $topic_last_reply_permalink );

		$r = $this->factory->reply->create( array(
			'post_parent' => $t,
			'reply_meta' => array(
				'forum_id' => $f,
				'topic_id' => $t,
			)
		) );

		$topic_last_reply_permalink = bbp_get_topic_last_reply_permalink( $f );
		$this->assertSame( bbp_get_reply_permalink( $r ), $topic_last_reply_permalink );
	}

	/**
	 * @covers ::bbp_topic_last_reply_url
	 * @covers ::bbp_get_topic_last_reply_url
	 */
	public function test_bbp_get_topic_last_reply_url() {
		if ( is_multisite() ) {
			$this->markTestSkipped( 'Skipping URL tests in multiste for now.' );
		}

		$f = $this->factory->forum->create();

		$t = $this->factory->topic->create( array(
			'post_parent' => $f,
			'topic_meta' => array(
				'forum_id' => $f,
			)
		) );

		$topic_last_reply_url = bbp_get_topic_last_reply_url( $t );
		$this->assertSame( get_permalink( $t ), $topic_last_reply_url );

		$r = $this->factory->reply->create( array(
			'post_parent' => $t,
			'reply_meta' => array(
				'forum_id' => $f,
				'topic_id' => $t,
			)
		) );

		$topic_last_reply_url = bbp_get_topic_last_reply_url( $t );
		$this->assertSame( bbp_get_reply_url( $r ), $topic_last_reply_url );
	}
}
