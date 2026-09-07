<?php

/**
 * Tests for the user component count functions.
 *
 * @group users
 * @group functions
 * @group counts
 */
class BBP_Tests_Users_Functions_Counts extends BBP_UnitTestCase {

	/**
	 * @covers ::bbp_update_user_reply_count
	 */
	function test_bbp_update_user_topic_count() {
		$u = $this->factory->user->create();
		$int_value = 3;

		bbp_update_user_topic_count( $u, $int_value );

		$count = bbp_get_user_topic_count( $u, true );
		$this->assertSame( $int_value, $count );
	}

	/**
	 * @covers ::bbp_update_user_reply_count
	 */
	function test_bbp_update_user_reply_count() {
		$u = $this->factory->user->create();
		$int_value = 3;

		bbp_update_user_reply_count( $u, $int_value );

		$count = bbp_get_user_reply_count( $u, true );
		$this->assertSame( $int_value, $count );
	}

	/**
	 * @covers ::bbp_user_topic_count
	 * @covers ::bbp_get_user_topic_count
	 */
	function test_bbp_get_user_topic_count() {
		$u = $this->factory->user->create();
		$int_value = 3;
		$formatted_value = bbp_number_format( $int_value );

		bbp_update_user_topic_count( $u, $int_value );

		$this->expectOutputString( $formatted_value );
		bbp_user_topic_count( $u );

		$count = bbp_get_user_topic_count( $u, false );
		$this->assertSame( $formatted_value, $count );

		$count = bbp_get_user_topic_count( $u, true );
		$this->assertSame( (int) $int_value, $count );
	}

	/**
	 * @covers ::bbp_user_reply_count
	 * @covers ::bbp_get_user_reply_count
	 */
	function test_bbp_get_user_reply_count() {
		$u = $this->factory->user->create();
		$int_value = 3;
		$formatted_value = bbp_number_format( $int_value );

		bbp_update_user_reply_count( $u, $int_value );

		$this->expectOutputString( $formatted_value );
		bbp_user_reply_count( $u );

		$count = bbp_get_user_reply_count( $u, false );
		$this->assertSame( $formatted_value, $count );

		$count = bbp_get_user_reply_count( $u, true );
		$this->assertSame( (int) $int_value, $count );
	}

	/**
	 * @covers ::bbp_user_post_count
	 * @covers ::bbp_get_user_post_count
	 */
	function test_bbp_get_user_post_count() {
		$u = $this->factory->user->create();
		$int_value = 3;
		$integer = true;

		// Add reply count
		bbp_update_user_reply_count( $u, $int_value );

		// Count
		$count = bbp_get_user_post_count( $u, $integer );
		$this->assertSame( $int_value, $count );

		// Add topic count
		bbp_update_user_topic_count( $u, $int_value );
		$double_value = $int_value * 2;

		// Count + Count
		$double_count = bbp_get_user_post_count( $u, true );
		$this->assertSame( $double_value, $double_count );

		// Output
		$double_formatted_value = bbp_number_format( $double_value );
		$this->expectOutputString( $double_formatted_value );
		bbp_user_post_count( $u );
	}

	/**
	 * @covers ::bbp_get_user_topics_started
	 */
	public function test_bbp_get_user_topics_started() {
		$u = $this->factory->user->create();

		$has_topics = bbp_get_user_topics_started( $u );
		$this->assertFalse( $has_topics );

		$t = $this->factory->topic->create_many( 3, array(
			'post_author' => $u,
		) );

		bbp_update_topic( array(
			'topic_id' => $t,
		) );

		$has_topics = bbp_get_user_topics_started( $u );
		$this->assertTrue( $has_topics );
	}

	/**
	 * @covers ::bbp_get_user_replies_created
	 */
	public function test_bbp_get_user_replies_created() {
		$u = $this->factory->user->create();

		$t = $this->factory->topic->create();

		$has_replies = bbp_get_user_replies_created( $u );
		$this->assertFalse( $has_replies );

		$r = $this->factory->reply->create_many( 3, array(
			'post_parent' => $t,
			'post_author' => $u,
			'reply_meta' => array(
				'topic_id' => $t,
			),
		) );

		$has_replies = bbp_get_user_replies_created( $u );
		$this->assertTrue( $has_replies );
	}

	/**
	 * @covers ::bbp_get_total_users
	 */
	public function test_bbp_get_total_users() {
		$this->factory->user->create_many( 3 );

		$users = (int) bbp_get_total_users();

		// 15 + 1, the + 1 is the default admin user
		$this->assertSame( 4, $users );
	}

	/**
	 * @covers ::bbp_get_user_topic_count_raw
	 * @ticket BBP3645
	 */
	public function test_bbp_get_user_topic_count_raw() {
		$u = $this->factory->user->create();

		$t = $this->factory->topic->create_many( 3, array(
			'post_author' => $u,
		) );

		$count = bbp_get_user_topic_count_raw( $u );
		$this->assertSame( 3, $count );
		$this->assertMatchesRegularExpression( '/WHERE post_type = .+\s+AND post_status IN .+\s+AND post_author =/s', bbp_db()->last_query );

		$t = $this->factory->topic->create_many( 3, array(
			'post_author' => $u,
		) );

		$count = bbp_get_user_topic_count_raw( $u );
		$this->assertSame( 6, $count );

		$t = $this->factory->topic->create( array(
			'post_author' => $u,
		) );

		bbp_close_topic( $t );

		$count = bbp_get_user_topic_count_raw( $u );
		$this->assertSame( 7, $count );
	}

	/**
	 * @covers ::bbp_get_user_reply_count_raw
	 * @ticket BBP3645
	 */
	public function test_bbp_get_user_reply_count_raw() {
		$u = $this->factory->user->create();

		$t = $this->factory->topic->create();

		$r = $this->factory->reply->create_many( 3, array(
			'post_parent' => $t,
			'post_author' => $u,
			'reply_meta' => array(
				'topic_id' => $t,
			),
		) );

		$count = bbp_get_user_reply_count_raw( $u );
		$this->assertSame( 3, $count );
		$this->assertMatchesRegularExpression( '/WHERE post_type = .+\s+AND post_status IN .+\s+AND post_author =/s', bbp_db()->last_query );

		$r = $this->factory->reply->create_many( 3, array(
			'post_parent' => $t,
			'post_author' => $u,
			'reply_meta' => array(
				'topic_id' => $t,
			),
		) );

		$count = bbp_get_user_reply_count_raw( $u );
		$this->assertSame( 6, $count );
	}

	/**
	 * @covers ::bbp_bump_user_topic_count
	 */
	public function test_bbp_bump_user_topic_count() {
		$u = $this->factory->user->create();
		$int_value = 3;
		$integer = true;

		bbp_update_user_topic_count( $u, $int_value );

		$count = bbp_get_user_topic_count( $u, $integer );
		$this->assertSame( $int_value, $count );

		bbp_bump_user_topic_count( $u );

		$count = bbp_get_user_topic_count( $u, $integer );
		$this->assertSame( $int_value + 1, $count );
	}

	/**
	 * @covers ::bbp_bump_user_reply_count
	 */
	public function test_bbp_bump_user_reply_count() {
		$u = $this->factory->user->create();
		$int_value = 3;
		$integer = true;

		bbp_update_user_reply_count( $u, $int_value );

		$count = bbp_get_user_reply_count( $u, $integer );
		$this->assertSame( $int_value, $count );

		bbp_bump_user_reply_count( $u );

		$count = bbp_get_user_reply_count( $u, $integer );
		$this->assertSame( $int_value + 1, $count );
	}

	/**
	 * @covers ::bbp_bump_user_topic_count
	 * @covers ::bbp_bump_user_reply_count
	 */
	public function test_bump_user_count_treats_zero_as_a_stored_count() {
		$user_id = $this->factory->user->create();
		$queries = 0;
		$count_query = function ( $count ) use ( &$queries ) {
			$queries++;
			return $count;
		};

		bbp_update_user_topic_count( $user_id, 0 );
		bbp_update_user_reply_count( $user_id, 0 );
		add_filter( 'bbp_get_user_topic_count_raw', $count_query );
		add_filter( 'bbp_get_user_reply_count_raw', $count_query );

		try {
			bbp_bump_user_topic_count( $user_id );
			bbp_bump_user_reply_count( $user_id );
		} finally {
			remove_filter( 'bbp_get_user_topic_count_raw', $count_query );
			remove_filter( 'bbp_get_user_reply_count_raw', $count_query );
		}

		$this->assertSame( 1, bbp_get_user_topic_count( $user_id, true ) );
		$this->assertSame( 1, bbp_get_user_reply_count( $user_id, true ) );
		$this->assertSame( 0, $queries );
	}

	/**
	 * @covers ::bbp_update_counts_on_transition_post_status
	 * @covers ::bbp_bump_user_topic_count
	 * @covers ::bbp_bump_user_reply_count
	 * @ticket BBP3429
	 */
	public function test_new_post_transition_does_not_double_count_missing_user_counts() {
		$user_id = $this->factory->user->create();
		wp_insert_post( array(
			'post_type'   => bbp_get_topic_post_type(),
			'post_status' => bbp_get_public_status_id(),
			'post_author' => $user_id,
			'post_title'  => 'First topic',
		) );
		wp_insert_post( array(
			'post_type'   => bbp_get_reply_post_type(),
			'post_status' => bbp_get_public_status_id(),
			'post_author' => $user_id,
			'post_title'  => 'First reply',
		) );

		$this->assertSame( 1, bbp_get_user_topic_count( $user_id, true ) );
		$this->assertSame( 1, bbp_get_user_reply_count( $user_id, true ) );
	}

	/**
	 * @covers ::bbp_bump_user_topic_count
	 * @covers ::bbp_bump_user_reply_count
	 */
	public function test_status_change_rebuilds_missing_user_counts() {
		$user_id    = $this->factory->user->create();
		$topic_bump = array();
		$reply_bump = array();
		$save_topic_bump = function ( $count, $bump_user_id, $difference, $old_count ) use ( &$topic_bump ) {
			$topic_bump = array( $count, $bump_user_id, $difference, $old_count );
			return $count;
		};
		$save_reply_bump = function ( $count, $bump_user_id, $difference, $old_count ) use ( &$reply_bump ) {
			$reply_bump = array( $count, $bump_user_id, $difference, $old_count );
			return $count;
		};
		$topic_id = wp_insert_post( array(
			'post_type'   => bbp_get_topic_post_type(),
			'post_status' => bbp_get_public_status_id(),
			'post_author' => $user_id,
			'post_title'  => 'Topic with a missing count',
		) );
		$reply_id = wp_insert_post( array(
			'post_type'   => bbp_get_reply_post_type(),
			'post_status' => bbp_get_public_status_id(),
			'post_author' => $user_id,
			'post_title'  => 'Reply with a missing count',
		) );

		delete_user_option( $user_id, '_bbp_topic_count' );
		delete_user_option( $user_id, '_bbp_reply_count' );
		add_filter( 'bbp_bump_user_topic_count', $save_topic_bump, 10, 4 );
		add_filter( 'bbp_bump_user_reply_count', $save_reply_bump, 10, 4 );

		try {
			bbp_spam_topic( $topic_id );
			bbp_spam_reply( $reply_id );
		} finally {
			remove_filter( 'bbp_bump_user_topic_count', $save_topic_bump, 10 );
			remove_filter( 'bbp_bump_user_reply_count', $save_reply_bump, 10 );
		}

		$this->assertSame( 0, bbp_get_user_topic_count( $user_id, true ) );
		$this->assertSame( 0, bbp_get_user_reply_count( $user_id, true ) );
		$this->assertSame( array( 0, $user_id, -1, 1 ), $topic_bump );
		$this->assertSame( array( 0, $user_id, -1, 1 ), $reply_bump );
	}

	/**
	 * @covers ::bbp_decrease_user_topic_count
	 * @covers ::bbp_decrease_user_reply_count
	 */
	public function test_deleting_posts_updates_user_counts() {
		$user_id = $this->factory->user->create();

		foreach ( array( 'topic', 'reply' ) as $type ) {
			$post_ids = array();
			foreach ( array( 'publish', 'publish', 'pending' ) as $status ) {
				$post_ids[] = wp_insert_post( array(
					'post_type'   => $type,
					'post_status' => $status,
					'post_author' => $user_id,
					'post_title'  => 'Counted contribution',
				) );
			}

			$get = 'bbp_get_user_' . $type . '_count';
			$set = 'bbp_update_user_' . $type . '_count';
			$set( $user_id, 2 );

			wp_delete_post( $post_ids[0], true );
			$this->assertSame( 1, $get( $user_id, true ) );

			wp_delete_post( $post_ids[2], true );
			$this->assertSame( 1, $get( $user_id, true ) );
		}
	}

	/**
	 * @covers ::bbp_increase_user_topic_count
	 */
	public function test_bbp_increase_user_topic_count() {
		$u = $this->factory->user->create();
		$int_value = 3;
		$integer = true;

		bbp_update_user_topic_count( $u, $int_value );

		$count = bbp_get_user_topic_count( $u, $integer );
		$this->assertSame( $int_value, $count );

		$t = $this->factory->topic->create( array(
			'post_author' => $u,
		) );

		bbp_update_user_topic_count( $u, $int_value );
		bbp_increase_user_topic_count( $t );

		$count = bbp_get_user_topic_count( $u, $integer );
		$this->assertSame( $int_value + 1, $count );
	}

	/**
	 * @covers ::bbp_increase_user_reply_count
	 */
	public function test_bbp_increase_user_reply_count() {
		$u = $this->factory->user->create();
		$int_value = 3;
		$integer = true;

		bbp_update_user_reply_count( $u, $int_value );

		$count = bbp_get_user_reply_count( $u, $integer );
		$this->assertSame( $int_value, $count );

		$t = $this->factory->topic->create();

		$r = $this->factory->reply->create( array(
			'post_parent' => $t,
			'post_author' => $u,
			'reply_meta' => array(
				'topic_id' => $t,
			),
		) );

		bbp_update_user_reply_count( $u, $int_value );
		bbp_increase_user_reply_count( $r );

		$count = bbp_get_user_reply_count( $u, $integer );
		$this->assertSame( $int_value + 1, $count );
	}

	/**
	 * @covers ::bbp_decrease_user_topic_count
	 */
	public function test_bbp_decrease_user_topic_count() {
		$u = $this->factory->user->create();
		$int_value = 3;
		$integer = true;

		bbp_update_user_topic_count( $u, $int_value );

		$count = bbp_get_user_topic_count( $u, $integer );
		$this->assertSame( $int_value, $count );

		$t = $this->factory->topic->create( array(
			'post_author' => $u,
		) );

		bbp_update_user_topic_count( $u, $int_value );

		// Minus 1
		bbp_decrease_user_topic_count( $t );

		$count = bbp_get_user_topic_count( $u, $integer );
		$this->assertSame( $int_value - 1, $count );

		// Minus 2
		bbp_decrease_user_topic_count( $t );

		$count = bbp_get_user_topic_count( $u, $integer );
		$this->assertSame( $int_value - 2, $count );
	}

	/**
	 * @covers ::bbp_decrease_user_reply_count
	 */
	public function test_bbp_decrease_user_reply_count() {
		$u = $this->factory->user->create();
		$int_value = 3;
		$integer = true;

		bbp_update_user_reply_count( $u, $int_value );

		$count = bbp_get_user_reply_count( $u, $integer );
		$this->assertSame( $int_value, $count );

		$t = $this->factory->topic->create();

		$r = $this->factory->reply->create( array(
			'post_parent' => $t,
			'post_author' => $u,
			'reply_meta' => array(
				'topic_id' => $t,
			),
		) );

		bbp_update_user_reply_count( $u, $int_value );

		// Minus 1
		bbp_decrease_user_reply_count( $r );

		$count = bbp_get_user_reply_count( $u, $integer );
		$this->assertSame( $int_value - 1, $count );

		// Minus 2
		bbp_decrease_user_reply_count( $r );

		$count = bbp_get_user_reply_count( $u, $integer );
		$this->assertSame( $int_value - 2, $count );
	}
}
