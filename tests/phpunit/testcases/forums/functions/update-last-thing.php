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
	 * @ticket 3637
	 * @covers ::bbp_update_forum_last_topic_id
	 */
	public function test_bbp_update_forum_last_topic_id_uses_newest_subforum_topic() {
		$parent_id = $this->factory->forum->create();
		$this->factory->forum->create_many( 2, array(
			'post_parent' => $parent_id,
		) );

		$children = bbp_forum_query_subforum_ids( $parent_id );

		$new_topic_id = $this->factory->topic->create( array(
			'post_parent' => $children[0],
			'post_date'   => '2025-01-02 00:00:00',
			'topic_meta'  => array(
				'forum_id' => $children[0],
			),
		) );
		$this->factory->topic->create( array(
			'post_parent' => $children[1],
			'post_date'   => '2025-01-01 00:00:00',
			'topic_meta'  => array(
				'forum_id' => $children[1],
			),
		) );

		$this->assertSame( $new_topic_id, bbp_update_forum_last_topic_id( $parent_id ) );
		$this->assertSame( $new_topic_id, bbp_get_forum_last_topic_id( $parent_id ) );
	}

	/**
	 * @ticket 3637
	 * @covers ::bbp_update_forum_last_topic_id
	 */
	public function test_bbp_update_forum_last_topic_id_compares_parent_and_subforum_activity() {
		$parent_id = $this->factory->forum->create();
		$child_id  = $this->factory->forum->create( array(
			'post_parent' => $parent_id,
		) );
		$topic_id  = $this->factory->topic->create( array(
			'post_parent' => $child_id,
			'post_date'   => '2025-01-02 00:00:00',
			'topic_meta'  => array(
				'forum_id' => $child_id,
			),
		) );
		$this->factory->topic->create( array(
			'post_parent' => $parent_id,
			'post_date'   => '2025-01-01 00:00:00',
			'topic_meta'  => array(
				'forum_id' => $parent_id,
			),
		) );

		$this->assertSame( $topic_id, bbp_update_forum_last_topic_id( $parent_id ) );
	}

	/**
	 * @ticket 3637
	 * @covers ::bbp_update_forum_last_topic_id
	 */
	public function test_bbp_update_forum_last_topic_id_stops_at_subforum_cycles() {
		$parent_id = $this->factory->forum->create();
		$child_id  = $this->factory->forum->create( array(
			'post_parent' => $parent_id,
		) );
		$topic_id  = $this->factory->topic->create( array(
			'post_parent' => $child_id,
			'topic_meta'  => array(
				'forum_id' => $child_id,
			),
		) );
		$callback  = function ( $subforum_ids, $forum_id ) use ( $parent_id, $child_id ) {
			if ( $child_id === $forum_id ) {
				$subforum_ids[] = $parent_id;
			}

			return $subforum_ids;
		};

		add_filter( 'bbp_forum_query_subforum_ids', $callback, 10, 2 );

		try {
			$this->assertSame( $topic_id, bbp_update_forum_last_topic_id( $parent_id ) );
		} finally {
			remove_filter( 'bbp_forum_query_subforum_ids', $callback, 10 );
		}
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

		$r1_time_raw = get_post_field( 'post_date', $r1 );

		$time = bbp_update_forum_last_active_time( $f1, $r1_time_raw );
		$this->assertSame( $r1_time_raw, $time );
		$this->assertSame( $r1_time_raw, get_post_meta( $f1, '_bbp_last_active_time', true ) );

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

		$r2_time_raw = get_post_field( 'post_date', $r2 );

		$time = bbp_update_forum_last_active_time( $f2 );
		$this->assertSame( $r2_time_raw, $time );
		$this->assertSame( $r2_time_raw, get_post_meta( $f2, '_bbp_last_active_time', true ) );

		$time = bbp_update_forum_last_active_time( $f1 );
		$this->assertSame( $r2_time_raw, $time );
		$this->assertSame( $r2_time_raw, get_post_meta( $f1, '_bbp_last_active_time', true ) );
	}
}
