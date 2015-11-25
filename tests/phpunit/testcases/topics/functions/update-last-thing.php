<?php
/**
 * Tests for the `bbp_update_topic_last_*()` functions.
 *
 * @group topics
 * @group functions
 * @group update_last_thing
 */

class BBP_Tests_Topics_Functions_Update_Topic_Last_Thing extends BBP_UnitTestCase {

	/**
	 * @covers ::bbp_update_topic_last_active_id
	 */
	public function test_bbp_update_topic_last_active_id() {
		$f = $this->factory->forum->create();
		$t = $this->factory->topic->create( array(
			'post_parent' => $f,
			'topic_meta' => array(
				'forum_id' => $f,
			),
		) );
		$r1 = $this->factory->reply->create( array(
			'post_parent' => $t,
			'reply_meta' => array(
				'forum_id' => $f,
				'topic_id' => $t,
			),
		) );

		$id = bbp_update_topic_last_active_id( $t, $r1 );
		$this->assertSame( $r1, $id );

		$id = bbp_get_topic_last_active_id( $t );
		$this->assertSame( $r1, $id );

		$r2 = $this->factory->reply->create_many( 2, array(
			'post_parent' => $t,
			'reply_meta' => array(
				'forum_id' => $f,
				'topic_id' => $t,
			),
		) );

		bbp_update_topic_last_active_id( $t, $r2[1] );
		$id = bbp_get_topic_last_active_id( $t );
		$this->assertSame( $r2[1], $id );
	}

	/**
	 * @covers ::bbp_update_topic_last_active_time
	 */
	public function test_bbp_update_topic_last_active_time() {
		$f = $this->factory->forum->create();
		$t = $this->factory->topic->create( array(
			'post_parent' => $f,
			'topic_meta' => array(
				'forum_id' => $f,
			),
		) );
		$r1 = $this->factory->reply->create( array(
			'post_parent' => $t,
			'reply_meta' => array(
				'forum_id' => $f,
				'topic_id' => $t,
			),
		) );

		$r1_time_raw       = get_post_field( 'post_date', $r1 );
		$r1_time_formatted = bbp_get_time_since( bbp_convert_date( $r1_time_raw ) );

		$time = bbp_update_topic_last_active_time( $t, $r1_time_raw );
		$this->assertSame( $r1_time_raw, $time );

		$time = bbp_get_topic_last_active_time( $t );
		$this->assertSame( $r1_time_formatted, $time );

		$r2 = $this->factory->reply->create_many( 2, array(
			'post_parent' => $t,
			'reply_meta' => array(
				'forum_id' => $f,
				'topic_id' => $t,
			),
		) );

		$r2_time_raw       = get_post_field( 'post_date', $r2[1] );
		$r2_time_formatted = bbp_get_time_since( bbp_convert_date( $r2_time_raw ) );

		bbp_update_topic_last_active_time( $t );
		$time = bbp_get_topic_last_active_time( $t );
		$this->assertSame( $r2_time_formatted, $time );
	}

	/**
	 * @covers ::bbp_update_topic_last_reply_id
	 */
	public function test_bbp_update_topic_last_reply_id() {
		$f = $this->factory->forum->create();
		$t = $this->factory->topic->create( array(
			'post_parent' => $f,
			'topic_meta' => array(
				'forum_id' => $f,
			),
		) );
		$r1 = $this->factory->reply->create( array(
			'post_parent' => $t,
			'reply_meta' => array(
				'forum_id' => $f,
				'topic_id' => $t,
			),
		) );

		$id = bbp_update_topic_last_reply_id( $t, $r1 );
		$this->assertSame( $r1, $id );

		$id = bbp_get_topic_last_reply_id( $t );
		$this->assertSame( $r1, $id );

		$r2 = $this->factory->reply->create_many( 2, array(
			'post_parent' => $t,
			'reply_meta' => array(
				'forum_id' => $f,
				'topic_id' => $t,
			),
		) );

		bbp_update_topic_last_reply_id( $t, $r2[1] );
		$id = bbp_get_topic_last_reply_id( $t );
		$this->assertSame( $r2[1], $id );
	}
}
