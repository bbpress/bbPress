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
	 * @covers ::bbp_update_forum_last_reply_id
	 * @covers ::bbp_update_forum_last_active_id
	 */
	public function test_parent_forum_activity_uses_dates_across_children() {
		$db        = bbp_db();
		$parent    = $this->factory->forum->create();
		$first     = $this->factory->forum->create( array( 'post_parent' => $parent ) );
		$second    = $this->factory->forum->create( array( 'post_parent' => $parent ) );
		$topic_a   = $this->factory->topic->create( array( 'post_parent' => $first ) );
		$reply_a   = $this->factory->reply->create( array( 'post_parent' => $topic_a ) );
		$topic_b   = $this->factory->topic->create( array( 'post_parent' => $second ) );
		$reply_b   = $this->factory->reply->create( array( 'post_parent' => $topic_b ) );
		$dates     = array(
			$topic_a => '2024-01-01 12:00:00',
			$topic_b => '2024-01-01 12:00:00',
			$reply_a => '2024-01-04 12:00:00',
			$reply_b => '2024-01-03 12:00:00'
		);
		foreach ( $dates as $id => $date ) {
			$db->update( $db->posts, array( 'post_date' => $date ), array( 'ID' => $id ) );
			clean_post_cache( $id );
		}

		$children = static function ( $ids, $id ) use ( $parent, $first, $second ) {
			return $parent === $id ? array( $first, $second ) : $ids;
		};
		add_filter( 'bbp_forum_query_subforum_ids', $children, 10, 2 );
		try {
			$this->assertSame( $reply_a, bbp_update_forum_last_reply_id( $parent ) );
			$this->assertSame( $reply_a, bbp_update_forum_last_active_id( $parent ) );
			bbp_update_forum_last_active_time( $parent );
			$this->assertSame( '2024-01-04 12:00:00', get_post_meta( $parent, '_bbp_last_active_time', true ) );
		} finally {
			remove_filter( 'bbp_forum_query_subforum_ids', $children, 10 );
		}
		$this->assertSame( $reply_a, bbp_update_forum_last_reply_id( $parent ) );
		$this->assertSame( $reply_a, bbp_update_forum_last_active_id( $parent ) );
	}

	/**
	 * @covers ::bbp_update_forum_last_reply_id
	 * @covers ::bbp_update_forum_last_active_id
	 */
	public function test_forum_last_ids_survive_activity_query_failure() {
		global $wpdb;

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
		update_post_meta( $forum_id, '_bbp_last_reply_id', $reply_id );
		update_post_meta( $forum_id, '_bbp_last_active_id', $topic_id );

		$suppress_errors = $wpdb->suppress_errors( true );
		try {
			foreach ( array( 'activity', 'candidate' ) as $failure ) {
				$fail_query = static function( $query ) use ( $wpdb, $failure ) {
					$matches = ( 'activity' === $failure )
						? ( false !== strpos( $query, 'AS reply_id' ) && false !== strpos( $query, 'AS topic_id' ) )
						: false !== strpos( $query, 'SELECT ID, post_date' );
					if ( $matches ) {
						$query = str_replace( $wpdb->posts, $wpdb->posts . '_missing', $query );
					}
					return $query;
				};
				add_filter( 'query', $fail_query );
				try {
					$this->assertSame( $reply_id, bbp_update_forum_last_reply_id( $forum_id ) );
					$this->assertSame( $topic_id, bbp_update_forum_last_active_id( $forum_id ) );
				} finally {
					remove_filter( 'query', $fail_query );
				}
			}
		} finally {
			$wpdb->suppress_errors( $suppress_errors );
		}

		$this->assertSame( $reply_id, bbp_get_forum_last_reply_id( $forum_id ) );
		$this->assertSame( $topic_id, bbp_get_forum_last_active_id( $forum_id ) );
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

	/**
	 * @ticket BBP3794
	 * @covers ::bbp_update_forum_last_active_time
	 */
	public function test_empty_forum_does_not_use_the_global_post_date_for_freshness() {
		$forum_id = $this->factory->forum->create( array(
			'post_date' => '2020-01-01 00:00:00',
		) );
		$other_id = $this->factory->post->create( array(
			'post_date' => '2021-01-01 00:00:00',
		) );
		delete_post_meta( $forum_id, '_bbp_last_active_id' );
		delete_post_meta( $forum_id, '_bbp_last_active_time' );

		global $post;
		$previous_post = $post;
		$post          = get_post( $other_id );
		try {
			$this->assertSame( '', bbp_update_forum_last_active_time( $forum_id ) );
			$this->assertFalse( metadata_exists( 'post', $forum_id, '_bbp_last_active_time' ) );
			bbp_update_forum( array( 'forum_id' => $forum_id ) );
			$this->assertFalse( metadata_exists( 'post', $forum_id, '_bbp_last_active_time' ) );
		} finally {
			$post = $previous_post;
		}
	}
}
