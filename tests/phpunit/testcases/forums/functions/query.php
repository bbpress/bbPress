<?php

/**
 * Tests for the forum component query functions.
 *
 * @group forums
 * @group functions
 * @group query
 */
class BBP_Tests_Forums_Functions_Query extends BBP_UnitTestCase {

	/**
	 * @covers ::bbp_exclude_forum_ids
	 */
	public function test_bbp_exclude_forum_ids() {
		$hidden_id  = $this->factory->forum->create();
		$private_id = $this->factory->forum->create();
		bbp_hide_forum( $hidden_id );
		bbp_privatize_forum( $private_id );
		$this->set_current_user( 0 );

		$excluded = bbp_exclude_forum_ids( 'array' );
		$this->assertEqualSets( array( $hidden_id, $private_id ), $excluded );
		$this->assertEqualSets( $excluded, wp_parse_id_list( bbp_exclude_forum_ids() ) );
		$this->assertSame( array(
			'key'     => '_bbp_forum_id',
			'value'   => $excluded,
			'type'    => 'NUMERIC',
			'compare' => 'NOT IN',
		), bbp_exclude_forum_ids( 'meta_query' ) );

		$keymaster_id = $this->factory->user->create();
		bbp_set_user_role( $keymaster_id, bbp_get_keymaster_role() );
		$this->set_current_user( $keymaster_id );
		$this->assertSame( array(), bbp_exclude_forum_ids( 'array' ) );
		$this->assertSame( '', bbp_exclude_forum_ids() );
		$this->assertSame( array(), bbp_exclude_forum_ids( 'meta_query' ) );
	}

	/**
	 * @covers ::bbp_forum_query_topic_ids
	 */
	public function test_bbp_forum_query_topic_ids() {
		$f = $this->factory->forum->create();

		$t1 = $this->factory->topic->create( array(
			'post_parent' => $f,
			'topic_meta' => array(
				'forum_id' => $f,
			),
		) );

		$t2 = $this->factory->topic->create( array(
			'post_parent' => $f,
			'topic_meta' => array(
				'forum_id' => $f,
			),
		) );

		$t3 = $this->factory->topic->create( array(
			'post_parent' => $f,
			'topic_meta' => array(
				'forum_id' => $f,
			),
		) );

		$this->assertEqualSets( array( $t1, $t2, $t3 ), bbp_forum_query_topic_ids( $f ) );
	}

	/**
	 * @covers ::bbp_forum_query_subforum_ids
	 */
	public function test_bbp_forum_query_subforum_ids() {
		$f1 = $this->factory->forum->create();

		$f2 = $this->factory->forum->create( array(
			'post_parent' => $f1,
		) );

		$f3 = $this->factory->forum->create( array(
			'post_parent' => $f1,
		) );

		$this->assertEqualSets( array( $f2, $f3 ), bbp_forum_query_subforum_ids( $f1 ) );
	}

	/**
	 * @covers ::bbp_forum_query_subforum_ids
	 * @ticket BBP3678
	 */
	public function test_bbp_forum_query_subforum_ids_excludes_uncountable_statuses() {
		$this->assertSame( array(), bbp_forum_query_subforum_ids( 0 ) );

		$parent_id = $this->factory->forum->create();
		$public_id = $this->factory->forum->create( array(
			'post_parent' => $parent_id,
		) );
		$hidden_id = $this->factory->forum->create( array(
			'post_parent' => $parent_id,
			'post_status' => bbp_get_hidden_status_id(),
		) );
		$private_id = $this->factory->forum->create( array(
			'post_parent' => $parent_id,
			'post_status' => bbp_get_private_status_id(),
		) );
		$trash_id = $this->factory->forum->create( array(
			'post_parent' => $parent_id,
		) );
		wp_update_post( array(
			'ID'          => $trash_id,
			'post_status' => bbp_get_trash_status_id(),
		) );

		$this->assertEqualSets( array( $public_id, $private_id, $hidden_id ), bbp_forum_query_subforum_ids( $parent_id ) );
	}

	/**
	 * @covers ::bbp_forum_query_last_reply_id
	 */
	public function test_bbp_forum_query_last_reply_id() {
		$c = $this->factory->forum->create( array(
			'forum_meta' => array(
				'forum_type' => 'category',
				'status'     => 'open',
			),
		) );

		$f = $this->factory->forum->create( array(
			'post_parent' => $c,
			'forum_meta' => array(
				'forum_id'   => $c,
				'forum_type' => 'forum',
				'status'     => 'open',
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
		$query_last_reply_f = bbp_forum_query_last_reply_id( $f );
		$this->assertSame( $query_last_reply_f, bbp_get_forum_last_reply_id( $f ) );

		// Get the categories last reply id.
		$query_last_reply_c = bbp_forum_query_last_reply_id( $c );
		$this->assertSame( $query_last_reply_c, bbp_get_forum_last_reply_id( $c ) );

		$this->factory->reply->create( array(
			'post_parent' => $t,
			'reply_meta' => array(
				'forum_id' => $f,
				'topic_id' => $t,
			),
		) );

		// Get the forums last reply id.
		$query_last_reply_f = bbp_forum_query_last_reply_id( $f );
		$this->assertSame( $query_last_reply_f, bbp_get_forum_last_reply_id( $f ) );

		// Get the categories last reply id.
		$query_last_reply_c = bbp_forum_query_last_reply_id( $c );
		$this->assertSame( $query_last_reply_c, bbp_get_forum_last_reply_id( $c ) );
	}

	/**
	 * @covers ::bbp_forum_query_last_activity_id
	 */
	public function test_bbp_forum_query_last_activity_id_checks_beyond_fifty_topics() {
		$forum_id = $this->factory->forum->create();
		$topic_ids = $this->factory->topic->create_many(
			51,
			array(
				'post_parent' => $forum_id,
				'topic_meta'  => array( 'forum_id' => $forum_id ),
			)
		);
		$reply_id = $this->factory->reply->create(
			array(
				'post_parent' => reset( $topic_ids ),
				'reply_meta'  => array(
					'forum_id' => $forum_id,
					'topic_id' => reset( $topic_ids ),
				),
			)
		);

		// Simulate legacy topic metadata that a fixed-size approximation can omit
		delete_post_meta( reset( $topic_ids ), '_bbp_last_active_time' );

		$this->assertSame( $reply_id, bbp_forum_query_last_activity_id( $forum_id ) );
	}

	/**
	 * @covers ::bbp_forum_query_last_activity_id
	 */
	public function test_bbp_forum_query_last_activity_id_without_public_topics() {
		$forum_id = $this->factory->forum->create();

		$this->assertSame( 0, bbp_forum_query_last_activity_id( $forum_id ) );

		$this->factory->topic->create( array(
			'post_parent' => $forum_id,
			'post_status' => bbp_get_pending_status_id(),
			'topic_meta'  => array( 'forum_id' => $forum_id ),
		) );

		$this->assertSame( 0, bbp_forum_query_last_activity_id( $forum_id ) );
	}

	/**
	 * @covers ::bbp_forum_query_last_activity_id
	 */
	public function test_bbp_forum_query_last_activity_id_uses_latest_public_topic() {
		$forum_id = $this->factory->forum->create();
		$this->factory->topic->create( array(
			'post_parent' => $forum_id,
			'topic_meta'  => array( 'forum_id' => $forum_id ),
		) );
		$closed_topic_id = $this->factory->topic->create( array(
			'post_parent' => $forum_id,
			'post_status' => bbp_get_closed_status_id(),
			'topic_meta'  => array( 'forum_id' => $forum_id ),
		) );
		$this->factory->topic->create( array(
			'post_parent' => $forum_id,
			'post_status' => bbp_get_pending_status_id(),
			'topic_meta'  => array( 'forum_id' => $forum_id ),
		) );
		$this->factory->post->create( array(
			'post_parent' => $forum_id,
		) );

		$this->assertSame( $closed_topic_id, bbp_forum_query_last_activity_id( $forum_id ) );
	}

	/**
	 * @covers ::bbp_forum_query_last_activity_id
	 */
	public function test_bbp_forum_query_last_activity_id_preserves_filtered_topic_ids() {
		$forum_id = $this->factory->forum->create();
		$topic_id = $this->factory->topic->create(
			array(
				'post_parent' => $forum_id,
				'topic_meta'  => array( 'forum_id' => $forum_id ),
			)
		);
		$reply_id = $this->factory->reply->create(
			array(
				'post_parent' => $topic_id,
				'reply_meta'  => array(
					'forum_id' => $forum_id,
					'topic_id' => $topic_id,
				),
			)
		);
		$this->factory->topic->create(
			array(
				'post_parent' => $forum_id,
				'topic_meta'  => array( 'forum_id' => $forum_id ),
			)
		);

		$filter = function() use ( $topic_id ) {
			return array( $topic_id );
		};
		add_filter( 'bbp_forum_query_topic_ids', $filter );

		$activity_id = bbp_forum_query_last_activity_id( $forum_id );

		remove_filter( 'bbp_forum_query_topic_ids', $filter );

		$this->assertSame( $reply_id, $activity_id );
	}

	/**
	 * @covers ::bbp_forum_query_last_activity_id
	 */
	public function test_bbp_forum_query_last_activity_id_reports_query_failure() {
		global $wpdb;

		$forum_id = $this->factory->forum->create();
		$this->assertSame( 0, bbp_forum_query_last_activity_id( $forum_id ) );

		$seen     = null;
		$filter   = function( $activity_id ) use ( &$seen ) {
			$seen = $activity_id;
			return $activity_id;
		};
		$fail_query = function( $query ) use ( $wpdb ) {
			if ( false !== strpos( $query, 'AS reply_id' ) && false !== strpos( $query, 'AS topic_id' ) ) {
				$query = str_replace( $wpdb->posts, $wpdb->posts . '_missing', $query );
			}

			return $query;
		};
		$suppress_errors = $wpdb->suppress_errors( true );
		add_filter( 'query', $fail_query );
		add_filter( 'bbp_forum_query_last_activity_id', $filter );

		try {
			$this->assertFalse( bbp_forum_query_last_activity_id( $forum_id ) );
		} finally {
			remove_filter( 'bbp_forum_query_last_activity_id', $filter );
			remove_filter( 'query', $fail_query );
			$wpdb->suppress_errors( $suppress_errors );
		}

		$this->assertFalse( $seen );
	}
}
