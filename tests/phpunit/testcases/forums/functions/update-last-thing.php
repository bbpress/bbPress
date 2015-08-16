<?php
/**
 * Tests for the `bbp_update_forum_last_*()` functions.
 *
 * @group forums
 * @group functions
 * @group update_last_thing
 */

class BBP_Tests_Forums_Functions_Update_Forum_Last_Thing extends BBP_UnitTestCase {

	/**
	 * @covers ::bbp_update_forum_last_topic_id
	 */
	public function test_bbp_update_forum_last_topic_id() {
		$f1 = $this->factory->forum->create();
		$f2 = $this->factory->forum->create( array(
			'post_parent' => $f1,
		) );

		$t1 = $this->factory->topic->create( array(
			'post_parent' => $f1,
			'topic_meta' => array(
				'forum_id' => $f1,
			),
		) );

		$id = bbp_update_forum_last_topic_id( $f1, $t1 );
		$this->assertSame( $t1, $id );

		$id = bbp_get_forum_last_topic_id( $f1 );
		$this->assertSame( $t1, $id );

		$t2 = $this->factory->topic->create( array(
			'post_parent' => $f2,
			'topic_meta' => array(
				'forum_id' => $f2,
			),
		) );

		bbp_update_forum_last_topic_id( $f2 );
		$id = bbp_get_forum_last_topic_id( $f2 );
		$this->assertSame( $t2, $id );

		bbp_update_forum_last_topic_id( $f1 );
		$id = bbp_get_forum_last_topic_id( $f1 );
		$this->assertSame( $t2, $id );
	}

	/**
	 * @covers ::bbp_update_forum_last_reply_id
	 */
	public function test_bbp_update_forum_last_reply_id() {
		$f1 = $this->factory->forum->create();
		$f2 = $this->factory->forum->create( array(
			'post_parent' => $f1,
		) );

		$t1 = $this->factory->topic->create( array(
			'post_parent' => $f1,
			'topic_meta' => array(
				'forum_id' => $f1,
			),
		) );
		$t2 = $this->factory->topic->create( array(
			'post_parent' => $f2,
			'topic_meta' => array(
				'forum_id' => $f2,
			),
		) );

		$r1 = $this->factory->reply->create( array(
			'post_parent' => $t1,
			'reply_meta' => array(
				'forum_id' => $f1,
				'topic_id' => $t1,
			),
		) );

		$id = bbp_update_forum_last_reply_id( $f1, $r1 );
		$this->assertSame( $r1, $id );

		$id = bbp_get_forum_last_reply_id( $f1 );
		$this->assertSame( $r1, $id );

		$r2 = $this->factory->reply->create( array(
			'post_parent' => $t2,
			'reply_meta' => array(
				'forum_id' => $f2,
				'topic_id' => $t2,
			),
		) );

		bbp_update_forum_last_reply_id( $f2 );
		$id = bbp_get_forum_last_reply_id( $f2 );
		$this->assertSame( $r2, $id );

		bbp_update_forum_last_reply_id( $f1 );
		$id = bbp_get_forum_last_reply_id( $f1 );
		$this->assertSame( $r2, $id );
	}

	/**
	 * @covers ::bbp_update_forum_last_active_id
	 */
	public function test_bbp_update_forum_last_active_id() {
		$f1 = $this->factory->forum->create();
		$f2 = $this->factory->forum->create( array(
			'post_parent' => $f1,
		) );

		$t1 = $this->factory->topic->create( array(
			'post_parent' => $f1,
			'topic_meta' => array(
				'forum_id' => $f1,
			),
		) );
		$r1 = $this->factory->reply->create( array(
			'post_parent' => $t1,
			'reply_meta' => array(
				'forum_id' => $f1,
				'topic_id' => $t1,
			),
		) );

		$id = bbp_update_forum_last_active_id( $f1, $r1 );
		$this->assertSame( $r1, $id );

		$id = bbp_get_forum_last_active_id( $f1 );
		$this->assertSame( $r1, $id );

		$t2 = $this->factory->topic->create( array(
			'post_parent' => $f2,
			'topic_meta' => array(
				'forum_id' => $f2,
			),
		) );
		$r2 = $this->factory->reply->create( array(
			'post_parent' => $t2,
			'reply_meta' => array(
				'forum_id' => $f2,
				'topic_id' => $t2,
			),
		) );

		bbp_update_forum_last_active_id( $f2 );
		$id = bbp_get_forum_last_active_id( $f2 );
		$this->assertSame( $r2, $id );

		bbp_update_forum_last_active_id( $f1 );
		$id = bbp_get_forum_last_active_id( $f1 );
		$this->assertSame( $r2, $id );
	}

	/**
	 * @covers ::bbp_update_forum_last_active_time
	 */
	public function test_bbp_update_forum_last_active_time() {
		$f1 = $this->factory->forum->create();
		$f2 = $this->factory->forum->create( array(
			'post_parent' => $f1,
		) );

		$t1 = $this->factory->topic->create( array(
			'post_parent' => $f1,
			'topic_meta' => array(
				'forum_id' => $f1,
			),
		) );
		$r1 = $this->factory->reply->create( array(
			'post_parent' => $t1,
			'reply_meta' => array(
				'forum_id' => $f1,
				'topic_id' => $t1,
			),
		) );

		$r1_time_raw       = get_post_field( 'post_date', $r1 );
		$r1_time_formatted = bbp_get_time_since( bbp_convert_date( $r1_time_raw ) );

		$time = bbp_update_forum_last_active_time( $f1, $r1_time_raw );
		$this->assertSame( $r1_time_raw, $time );

		$time = bbp_get_forum_last_active_time( $f1 );
		$this->assertSame( $r1_time_formatted, $time );

		$t2 = $this->factory->topic->create( array(
			'post_parent' => $f2,
			'topic_meta' => array(
				'forum_id' => $f2,
			),
		) );
		$r2 = $this->factory->reply->create( array(
			'post_parent' => $t2,
			'reply_meta' => array(
				'forum_id' => $f2,
				'topic_id' => $t2,
			),
		) );

		$r2_time_raw       = get_post_field( 'post_date', $r2 );
		$r2_time_formatted = bbp_get_time_since( bbp_convert_date( $r2_time_raw ) );

		bbp_update_forum_last_active_time( $f2 );
		$time = bbp_get_forum_last_active_time( $f2 );
		$this->assertSame( $r2_time_formatted, $time );

		bbp_update_forum_last_active_time( $f1 );
		$time = bbp_get_forum_last_active_time( $f1 );
		$this->assertSame( $r2_time_formatted, $time );
	}
}
