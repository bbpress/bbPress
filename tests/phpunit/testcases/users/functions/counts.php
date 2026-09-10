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

		wp_update_user_counts();
		$users = (int) bbp_get_total_users();

		// Three users plus the default administrator
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
	 * @covers ::bbp_bump_user_topic_count
	 * @covers ::bbp_update_user_topic_count
	 * @ticket BBP3678
	 */
	public function test_bbp_bump_user_topic_count_preserves_an_interleaved_update() {
		$user_id     = $this->factory->user->create();
		$interleaved = false;
		$callback    = function( $count, $filtered_user_id ) use ( &$interleaved ) {
			if ( ! $interleaved ) {
				$interleaved = true;
				bbp_bump_user_topic_count( $filtered_user_id );
			}

			return $count;
		};

		bbp_update_user_topic_count( $user_id, 0 );
		add_filter( 'bbp_get_user_topic_count_int', $callback, 10, 2 );
		bbp_bump_user_topic_count( $user_id );
		remove_filter( 'bbp_get_user_topic_count_int', $callback, 10 );

		$this->assertSame( 2, bbp_get_user_topic_count( $user_id, true ) );
	}

	/**
	 * @covers ::bbp_bump_user_topic_count
	 * @covers ::bbp_update_user_topic_count
	 */
	public function test_bbp_bump_user_topic_count_preserves_count_filters() {
		$user_id      = $this->factory->user->create();
		$atomic_calls = 0;
		$bump         = function( $count ) {
			return $count + 4;
		};
		$update   = function( $count ) {
			return $count + 3;
		};
		$atomic = function( $check ) use ( &$atomic_calls ) {
			$atomic_calls++;
			return $check;
		};

		bbp_update_user_topic_count( $user_id, 5 );
		add_filter( 'bbp_bump_user_topic_count', $bump );
		add_filter( 'bbp_update_user_topic_count', $update );
		add_filter( 'bbp_pre_bump_count_meta', $atomic );
		bbp_bump_user_topic_count( $user_id );
		remove_filter( 'bbp_bump_user_topic_count', $bump, 10 );
		remove_filter( 'bbp_update_user_topic_count', $update, 10 );
		remove_filter( 'bbp_pre_bump_count_meta', $atomic, 10 );

		$this->assertSame( 13, bbp_get_user_topic_count( $user_id, true ) );
		$this->assertSame( 0, $atomic_calls );
	}

	/**
	 * @covers ::bbp_bump_user_reply_count
	 * @covers ::bbp_update_user_reply_count
	 */
	public function test_bbp_bump_user_reply_count_preserves_an_absolute_update_filter() {
		$user_id      = $this->factory->user->create();
		$atomic_calls = 0;
		$update       = function() {
			return 100;
		};
		$atomic = function( $check ) use ( &$atomic_calls ) {
			$atomic_calls++;
			return $check;
		};

		bbp_update_user_reply_count( $user_id, 5 );
		add_filter( 'bbp_update_user_reply_count', $update );
		add_filter( 'bbp_pre_bump_count_meta', $atomic );
		bbp_bump_user_reply_count( $user_id );
		remove_filter( 'bbp_update_user_reply_count', $update, 10 );
		remove_filter( 'bbp_pre_bump_count_meta', $atomic, 10 );

		$this->assertSame( 100, bbp_get_user_reply_count( $user_id, true ) );
		$this->assertSame( 0, $atomic_calls );
	}

	/**
	 * @covers ::bbp_bump_user_topic_count
	 * @covers ::bbp_update_user_topic_count
	 * @ticket BBP3678
	 */
	public function test_bbp_bump_user_topic_count_uses_the_current_site_option() {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'Requires multisite.' );
		}

		$user_id = $this->factory->user->create();
		$site_id = $this->factory->blog->create();

		bbp_update_user_topic_count( $user_id, 5 );
		switch_to_blog( $site_id );

		try {
			bbp_update_user_topic_count( $user_id, 7 );
			bbp_bump_user_topic_count( $user_id );
			$this->assertSame( 8, bbp_get_user_topic_count( $user_id, true ) );
			$this->assertSame( 8, (int) get_user_meta( $user_id, bbp_db()->get_blog_prefix() . '_bbp_topic_count', true ) );
		} finally {
			restore_current_blog();
		}

		$this->assertSame( 5, bbp_get_user_topic_count( $user_id, true ) );
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
	 * @covers ::bbp_update_counts_on_user_reassignment
	 * @ticket BBP3678
	 */
	public function test_deleting_user_with_reassignment_updates_counts_and_engagements() {
		$deleted_user_id  = $this->factory->user->create();
		$reassign_user_id = $this->factory->user->create();
		$forum_id         = $this->factory->forum->create();
		$topic_id         = $this->factory->topic->create( array(
			'post_author' => $deleted_user_id,
			'post_parent' => $forum_id,
			'topic_meta'  => array( 'forum_id' => $forum_id ),
		) );
		$deleted_user_reply_id = $this->factory->reply->create( array(
			'post_author' => $deleted_user_id,
			'post_parent' => $topic_id,
			'reply_meta'  => array(
				'forum_id' => $forum_id,
				'topic_id' => $topic_id,
			),
		) );
		$this->factory->reply->create( array(
			'post_author' => $reassign_user_id,
			'post_parent' => $topic_id,
			'reply_meta'  => array(
				'forum_id' => $forum_id,
				'topic_id' => $topic_id,
			),
		) );
		$reply_only_topic_id = $this->factory->topic->create( array(
			'post_author' => $reassign_user_id,
			'post_parent' => $forum_id,
			'topic_meta'  => array( 'forum_id' => $forum_id ),
		) );
		$this->factory->reply->create( array(
			'post_author' => $deleted_user_id,
			'post_parent' => $reply_only_topic_id,
			'reply_meta'  => array(
				'forum_id' => $forum_id,
				'topic_id' => $reply_only_topic_id,
			),
		) );

		$this->assertSame( 1, bbp_get_user_topic_count( $deleted_user_id, true ) );
		$this->assertSame( 2, bbp_get_user_reply_count( $deleted_user_id, true ) );
		$this->assertSame( 1, bbp_get_user_topic_count( $reassign_user_id, true ) );
		$this->assertSame( 1, bbp_get_user_reply_count( $reassign_user_id, true ) );
		$this->assertSame( 2, bbp_get_topic_voice_count( $topic_id, true ) );
		$this->assertSame( 2, bbp_get_topic_voice_count( $reply_only_topic_id, true ) );

		wp_delete_user( $deleted_user_id, $reassign_user_id );

		$this->assertSame( $reassign_user_id, (int) get_post_field( 'post_author', $topic_id ) );
		$this->assertSame( $reassign_user_id, (int) get_post_field( 'post_author', $deleted_user_reply_id ) );
		$this->assertSame( 2, bbp_get_user_topic_count( $reassign_user_id, true ) );
		$this->assertSame( 3, bbp_get_user_reply_count( $reassign_user_id, true ) );
		$this->assertSame( array( $reassign_user_id ), bbp_get_topic_engagements( $topic_id ) );
		$this->assertSame( array( $reassign_user_id ), bbp_get_topic_engagements( $reply_only_topic_id ) );
		$this->assertSame( 1, bbp_get_topic_voice_count( $topic_id, true ) );
		$this->assertSame( 1, bbp_get_topic_voice_count( $reply_only_topic_id, true ) );
	}

	/**
	 * @covers ::bbp_update_counts_on_user_reassignment
	 * @ticket BBP3678
	 */
	public function test_deleting_user_with_reassignment_updates_current_multisite_counts() {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'Requires multisite.' );
		}

		$deleted_user_id  = $this->factory->user->create();
		$reassign_user_id = $this->factory->user->create();
		$site_id          = $this->factory->blog->create();

		add_user_to_blog( $site_id, $deleted_user_id, 'subscriber' );
		add_user_to_blog( $site_id, $reassign_user_id, 'subscriber' );
		switch_to_blog( $site_id );

		try {
			$forum_id = $this->factory->forum->create();
			$this->factory->topic->create( array(
				'post_author' => $deleted_user_id,
				'post_parent' => $forum_id,
				'topic_meta'  => array( 'forum_id' => $forum_id ),
			) );

			wp_delete_user( $deleted_user_id, $reassign_user_id );

			$meta_key = bbp_db()->get_blog_prefix() . '_bbp_topic_count';
			$this->assertSame( 1, bbp_get_user_topic_count( $reassign_user_id, true ) );
			$this->assertSame( 1, (int) get_user_meta( $reassign_user_id, $meta_key, true ) );
		} finally {
			restore_current_blog();
		}
	}

	/**
	 * @covers ::bbp_make_spam_user
	 * @covers ::bbp_make_ham_user
	 * @covers ::bbp_update_counts_on_transition_post_status
	 * @ticket BBP3678
	 */
	public function test_bbp_make_spam_and_ham_user_updates_counts() {
		$user_id  = $this->factory->user->create();
		$forum_id = $this->factory->forum->create();
		$topic_id = $this->factory->topic->create( array(
			'post_author' => $user_id,
			'post_parent' => $forum_id,
			'topic_meta'  => array( 'forum_id' => $forum_id ),
		) );
		$this->factory->reply->create( array(
			'post_author' => $user_id,
			'post_parent' => $topic_id,
			'reply_meta'  => array(
				'forum_id' => $forum_id,
				'topic_id' => $topic_id,
			),
		) );

		$this->assertTrue( bbp_make_spam_user( $user_id ) );
		$this->assertSame( 0, bbp_get_user_topic_count( $user_id, true ) );
		$this->assertSame( 0, bbp_get_user_reply_count( $user_id, true ) );
		$this->assertSame( 0, bbp_get_forum_topic_count( $forum_id, false, true ) );
		$this->assertSame( 0, bbp_get_forum_reply_count( $forum_id, false, true ) );
		$this->assertSame( 1, bbp_get_forum_topic_count_hidden( $forum_id, false, true ) );
		$this->assertSame( 1, bbp_get_forum_reply_count_hidden( $forum_id, false, true ) );

		$this->assertTrue( bbp_make_ham_user( $user_id ) );
		$this->assertSame( 1, bbp_get_user_topic_count( $user_id, true ) );
		$this->assertSame( 1, bbp_get_user_reply_count( $user_id, true ) );
		$this->assertSame( 1, bbp_get_forum_topic_count( $forum_id, false, true ) );
		$this->assertSame( 1, bbp_get_forum_reply_count( $forum_id, false, true ) );
		$this->assertSame( 0, bbp_get_forum_topic_count_hidden( $forum_id, false, true ) );
		$this->assertSame( 0, bbp_get_forum_reply_count_hidden( $forum_id, false, true ) );
	}

	/**
	 * @covers ::bbp_update_counts_on_post_author_change
	 */
	public function test_changing_post_authors_updates_user_counts() {
		$old_user_id = $this->factory->user->create();
		$new_user_id = $this->factory->user->create();
		$forum_id    = $this->factory->forum->create();
		$topic_id    = $this->factory->topic->create( array(
			'post_author' => $old_user_id,
			'post_parent' => $forum_id,
			'topic_meta'  => array( 'forum_id' => $forum_id ),
		) );
		$reply_id = $this->factory->reply->create( array(
			'post_author' => $old_user_id,
			'post_parent' => $topic_id,
			'reply_meta'  => array(
				'forum_id' => $forum_id,
				'topic_id' => $topic_id,
			),
		) );

		wp_update_post( array( 'ID' => $topic_id, 'post_author' => $new_user_id ) );
		$this->assertSame( 2, bbp_get_topic_voice_count( $topic_id, true ) );

		wp_update_post( array( 'ID' => $reply_id, 'post_author' => $new_user_id ) );
		$this->assertSame( 1, bbp_get_topic_voice_count( $topic_id, true ) );

		$this->assertSame( 0, bbp_get_user_topic_count( $old_user_id, true ) );
		$this->assertSame( 0, bbp_get_user_reply_count( $old_user_id, true ) );
		$this->assertSame( 1, bbp_get_user_topic_count( $new_user_id, true ) );
		$this->assertSame( 1, bbp_get_user_reply_count( $new_user_id, true ) );
	}

	/**
	 * @covers ::bbp_update_counts_on_post_author_change
	 */
	public function test_changing_post_author_and_status_rebuilds_user_counts() {
		$old_user_id = $this->factory->user->create();
		$new_user_id = $this->factory->user->create();
		$forum_id    = $this->factory->forum->create();
		$topic_id    = $this->factory->topic->create( array(
			'post_author' => $old_user_id,
			'post_parent' => $forum_id,
			'topic_meta'  => array( 'forum_id' => $forum_id ),
		) );

		wp_update_post( array(
			'ID'          => $topic_id,
			'post_author' => $new_user_id,
			'post_status' => bbp_get_pending_status_id(),
		) );

		$this->assertSame( 0, bbp_get_user_topic_count( $old_user_id, true ) );
		$this->assertSame( 0, bbp_get_user_topic_count( $new_user_id, true ) );
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
