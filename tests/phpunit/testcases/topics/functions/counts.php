<?php

/**
 * Tests for the topic component count functions.
 *
 * @group topics
 * @group functions
 * @group counts
 */
class BBP_Tests_Topics_Functions_Counts extends BBP_UnitTestCase {

	/**
	 * Generic function to test the topics counts with a new reply
	 */
	public function test_bbp_topic_new_reply_counts() {
		$f = $this->factory->forum->create();
		$t = $this->factory->topic->create( array(
			'post_parent' => $f,
			'post_author' => bbp_get_current_user_id(),
			'topic_meta' => array(
				'forum_id' => $f,
			),
		) );
		$r1 = $this->factory->reply->create( array(
			'post_parent' => $t,
			'post_author' => bbp_get_current_user_id(),
			'reply_meta' => array(
				'forum_id' => $f,
				'topic_id' => $t,
			),
		) );
		$u = $this->factory->user->create();

		// Cheating here, but we need $_SERVER['SERVER_NAME'] to be set.
		$this->setUp_wp_mail( false );

		// Simulate the 'bbp_new_reply' action.
		do_action( 'bbp_new_reply', $r1, $t, $f, false, bbp_get_current_user_id() );

		// Reverse our changes.
		$this->tearDown_wp_mail( false );

		$count = bbp_get_topic_reply_count( $t, true );
		$this->assertSame( 1, $count );

		$count = bbp_get_topic_reply_count_hidden( $t, true );
		$this->assertSame( 0, $count );

		$count = bbp_get_topic_voice_count( $t, true );
		$this->assertSame( 1, $count );

		$r2 = $this->factory->reply->create( array(
			'post_parent' => $t,
			'post_author' => $u,
			'reply_meta' => array(
				'forum_id' => $f,
				'topic_id' => $t,
			),
		) );

		// Cheating here, but we need $_SERVER['SERVER_NAME'] to be set.
		$this->setUp_wp_mail( false );

		// Simulate the 'bbp_new_topic' action.
		do_action( 'bbp_new_reply', $r2, $t, $f, false, $u );

		// Reverse our changes.
		$this->tearDown_wp_mail( false );

		$count = bbp_get_topic_reply_count( $t, true );
		$this->assertSame( 2, $count );

		$count = bbp_get_topic_reply_count_hidden( $t, true );
		$this->assertSame( 0, $count );

		$count = bbp_get_topic_voice_count( $t, true );
		$this->assertSame( 2, $count );
	}

	/**
	 * Generic function to test the topic counts on a deleted reply
	 */
	public function test_bbp_topic_deleted_reply_counts() {
		$f = $this->factory->forum->create();
		$t = $this->factory->topic->create( array(
			'post_parent' => $f,
			'post_author' => bbp_get_current_user_id(),
			'topic_meta' => array(
				'forum_id' => $f,
			),
		) );
		$r1 = $this->factory->reply->create( array(
			'post_parent' => $t,
			'post_author' => bbp_get_current_user_id(),
			'reply_meta' => array(
				'forum_id' => $f,
				'topic_id' => $t,
			),
		) );
		$u = $this->factory->user->create();

		$count = bbp_update_topic_reply_count( $t );
		$this->assertSame( 1, $count );

		$count = bbp_update_topic_reply_count_hidden( $t );
		$this->assertSame( 0, $count );

		$count = bbp_update_topic_voice_count( $t );
		$this->assertSame( 1, $count );

		$r2 = $this->factory->reply->create( array(
			'post_parent' => $t,
			'post_author' => $u,
			'reply_meta' => array(
				'forum_id' => $f,
				'topic_id' => $t,
			),
		) );

		$count = bbp_update_topic_reply_count( $t );
		$this->assertSame( 2, $count );

		$count = bbp_update_topic_reply_count_hidden( $t );
		$this->assertSame( 0, $count );

		$count = bbp_update_topic_voice_count( $t );
		$this->assertSame( 2, $count );

		// ToDo: Update this to use bbp_delete_reply().
		bbp_clean_post_cache( $t );
		wp_delete_post( $r2, true );

		$count = bbp_get_topic_reply_count( $t, true );
		$this->assertSame( 1, $count );

		$count = bbp_get_topic_reply_count_hidden( $t, true );
		$this->assertSame( 0, $count );

		$count = bbp_get_topic_voice_count( $t, true );
		$this->assertSame( 1, $count );
	}

	/**
	 * Generic function to test the topic counts on a trashed/untrashed reply
	 */
	public function test_bbp_topic_trashed_untrashed_reply_counts() {
		$u = $this->factory->user->create();
		$f = $this->factory->forum->create();
		$t = $this->factory->topic->create( array(
			'post_parent' => $f,
			'topic_meta' => array(
				'forum_id' => $f,
			),
		) );
		$r = $this->factory->reply->create_many( 2, array(
			'post_parent' => $t,
			'reply_meta' => array(
				'forum_id' => $f,
				'topic_id' => $t,
			),
		) );
		$r3 = $this->factory->reply->create( array(
			'post_parent' => $t,
			'post_author' => $u,
			'reply_meta' => array(
				'forum_id' => $f,
				'topic_id' => $t,
			),
		) );

		$count = bbp_update_topic_reply_count( $t );
		$this->assertSame( 3, $count );

		$count = bbp_update_topic_reply_count_hidden( $t );
		$this->assertSame( 0, $count );

		$count = bbp_update_topic_voice_count( $t );
		$this->assertSame( 2, $count );

		// ToDo: Update this to use bbp_trash_reply().
		wp_trash_post( $r3 );

		$count = bbp_get_topic_reply_count( $t, true );
		$this->assertSame( 2, $count );

		$count = bbp_get_topic_reply_count_hidden( $t, true );
		$this->assertSame( 1, $count );

		$count = bbp_get_topic_voice_count( $t, true );
		$this->assertSame( 1, $count );

		// ToDo: Update this to use bbp_untrash_reply().
		wp_untrash_post( $r3 );

		$count = bbp_get_topic_reply_count( $t, true );
		$this->assertSame( 3, $count );

		$count = bbp_get_topic_reply_count_hidden( $t, true );
		$this->assertSame( 0, $count );

		$count = bbp_get_topic_voice_count( $t, true );
		$this->assertSame( 2, $count );
	}

	/**
	 * Generic function to test the topic counts on a spammed/unspammed reply
	 */
	public function test_bbp_topic_spammed_unspammed_reply_counts() {
		$u = $this->factory->user->create();
		$f = $this->factory->forum->create();
		$t = $this->factory->topic->create( array(
			'post_parent' => $f,
			'topic_meta' => array(
				'forum_id' => $f,
			),
		) );
		$r = $this->factory->reply->create_many( 2, array(
			'post_parent' => $t,
			'reply_meta' => array(
				'forum_id' => $f,
				'topic_id' => $t,
			),
		) );
		$r3 = $this->factory->reply->create( array(
			'post_parent' => $t,
			'post_author' => $u,
			'reply_meta' => array(
				'forum_id' => $f,
				'topic_id' => $t,
			),
		) );

		$count = bbp_update_topic_reply_count( $t );
		$this->assertSame( 3, $count );

		$count = bbp_update_topic_reply_count_hidden( $t );
		$this->assertSame( 0, $count );

		$count = bbp_update_topic_voice_count( $t );
		$this->assertSame( 2, $count );

		bbp_spam_reply( $r3 );

		$count = bbp_get_topic_reply_count( $t, true );
		$this->assertSame( 2, $count );

		$count = bbp_get_topic_reply_count_hidden( $t, true );
		$this->assertSame( 1, $count );

		$count = bbp_get_topic_voice_count( $t, true );
		$this->assertSame( 1, $count );

		bbp_unspam_reply( $r3 );

		$count = bbp_get_topic_reply_count( $t, true );
		$this->assertSame( 3, $count );

		$count = bbp_get_topic_reply_count_hidden( $t, true );
		$this->assertSame( 0, $count );

		$count = bbp_get_topic_voice_count( $t, true );
		$this->assertSame( 2, $count );
	}

	/**
	 * Generic function to test the topic counts on a approved/unapproved reply
	 */
	public function test_bbp_topic_approved_unapproved_reply_counts() {
		$u = $this->factory->user->create();
		$f = $this->factory->forum->create();
		$t = $this->factory->topic->create( array(
			'post_parent' => $f,
			'topic_meta' => array(
				'forum_id' => $f,
			),
		) );
		$r = $this->factory->reply->create_many( 2, array(
			'post_parent' => $t,
			'reply_meta' => array(
				'forum_id' => $f,
				'topic_id' => $t,
			),
		) );
		$r3 = $this->factory->reply->create( array(
			'post_parent' => $t,
			'post_author' => $u,
			'reply_meta' => array(
				'forum_id' => $f,
				'topic_id' => $t,
			),
		) );

		$count = bbp_update_topic_reply_count( $t );
		$this->assertSame( 3, $count );

		$count = bbp_update_topic_reply_count_hidden( $t );
		$this->assertSame( 0, $count );

		$count = bbp_update_topic_voice_count( $t );
		$this->assertSame( 2, $count );

		bbp_unapprove_reply( $r3 );

		$count = bbp_get_topic_reply_count( $t, true );
		$this->assertSame( 2, $count );

		$count = bbp_get_topic_reply_count_hidden( $t, true );
		$this->assertSame( 1, $count );

		$count = bbp_get_topic_voice_count( $t, true );
		$this->assertSame( 1, $count );

		bbp_approve_reply( $r3 );

		$count = bbp_get_topic_reply_count( $t, true );
		$this->assertSame( 3, $count );

		$count = bbp_get_topic_reply_count_hidden( $t, true );
		$this->assertSame( 0, $count );

		$count = bbp_get_topic_voice_count( $t, true );
		$this->assertSame( 2, $count );
	}

	/**
	 * @covers ::bbp_bump_topic_reply_count
	 */
	public function test_bbp_bump_topic_reply_count() {
		$t = $this->factory->topic->create();

		$count = bbp_get_topic_reply_count( $t );
		$this->assertSame( '0', $count );

		$count = bbp_bump_topic_reply_count( $t );
		$this->assertSame( 1, $count );

		$count = bbp_bump_topic_reply_count( $t, 3 );

		$count = bbp_get_topic_reply_count( $t );
		$this->assertSame( '4', $count );
	}

	/**
	 * @covers ::bbp_bump_topic_reply_count_hidden
	 */
	public function test_bbp_bump_topic_reply_count_hidden() {
		$t = $this->factory->topic->create();

		$count = bbp_get_topic_reply_count_hidden( $t );
		$this->assertSame( '0', $count );

		$count = bbp_bump_topic_reply_count_hidden( $t );
		$this->assertSame( 1, $count );

		bbp_bump_topic_reply_count_hidden( $t, 3 );

		$count = bbp_get_topic_reply_count_hidden( $t );
		$this->assertSame( '4', $count );
	}

	/**
	 * @covers ::bbp_update_topic_reply_count
	 */
	public function test_bbp_update_topic_reply_count() {
		// Create a forum
		$f = $this->factory->forum->create();

		// Create a topic
		$t = $this->factory->topic->create( array(
			'post_parent' => $f,
			'topic_meta' => array(
				'forum_id' => $f,
			)
		) );

		// Start with zero
		$count = bbp_get_topic_reply_count( $t );
		$this->assertSame( '0', $count );

		// Create 3 replies
		$r1 = $this->factory->reply->create_many( 3, array(
			'post_parent' => $t,
			'reply_meta' => array(
				'forum_id' => $f,
				'topic_id' => $t,
			)
		) );

		$count = bbp_get_topic_reply_count( $t );
		$this->assertSame( '3', $count );

		bbp_update_topic_reply_count( $t );

		$count = bbp_get_topic_reply_count( $t );
		$this->assertSame( '3', $count );

		// Create another reply
		$r2 = $this->factory->reply->create( array(
			'post_parent' => $t,
			'reply_meta' => array(
				'forum_id' => $f,
				'topic_id' => $t,
			)
		) );

		// Test update using reply id
		bbp_update_topic_reply_count( $r2 );
		$count = bbp_get_topic_reply_count( $t );
		$this->assertSame( '4', $count );

		// Spam a reply
		bbp_spam_reply( $r2 );

		bbp_update_topic_reply_count( $t );
		$count = bbp_get_topic_reply_count( $t );
		$this->assertSame( '3', $count );

		// Set the reply count manually
		bbp_update_topic_reply_count( $t, 7 );
		$count = bbp_get_topic_reply_count( $t );
		$this->assertSame( '7', $count );
	}

	/**
	 * @covers ::bbp_update_topic_reply_count_hidden
	 */
	public function test_bbp_update_topic_reply_count_hidden() {
		// Create a forum
		$f = $this->factory->forum->create();

		// Create a topic
		$t = $this->factory->topic->create( array(
			'post_parent' => $f,
			'topic_meta' => array(
				'forum_id' => $f,
			)
		) );

		// Start with zero
		$count = bbp_get_topic_reply_count_hidden( $t );
		$this->assertSame( '0', $count );

		$r = $this->factory->reply->create_many( 3, array(
			'post_parent' => $t,
			'reply_meta' => array(
				'forum_id' => $f,
				'topic_id' => $t,
			)
		) );

		bbp_update_topic_reply_count_hidden( $t );
		$count = bbp_get_topic_reply_count_hidden( $t );
		$this->assertSame( '0', $count );

		bbp_spam_reply( $r[2] );

		bbp_update_topic_reply_count_hidden( $t );
		$count = bbp_get_topic_reply_count_hidden( $t );
		$this->assertSame( '1', $count );

		bbp_unapprove_reply( $r[0] );

		bbp_update_topic_reply_count_hidden( $t );
		$count = bbp_get_topic_reply_count_hidden( $t );
		$this->assertSame( '2', $count );
	}

	/**
	 * @covers ::bbp_update_topic_voice_count
	 */
	public function test_bbp_update_topic_voice_count() {
		$u = $this->factory->user->create_many( 2 );
		$t = $this->factory->topic->create();

		$count = bbp_get_topic_voice_count( $t );
		$this->assertSame( '1', $count );

		$r = $this->factory->reply->create( array(
			'post_author' => $u[0],
			'post_parent' => $t,
		) );

		bbp_update_topic_voice_count( $t );
		$count = bbp_get_topic_voice_count( $t );
		$this->assertSame( '2', $count );

		$count = bbp_update_topic_voice_count( $t );
		$this->assertSame( 2, $count );

		$r = $this->factory->reply->create( array(
			'post_author' => $u[1],
			'post_parent' => $t,
		) );

		bbp_update_topic_voice_count( $t );
		$count = bbp_get_topic_voice_count( $t );
		$this->assertSame( '3', $count );
	}

	/**
	 * @covers ::bbp_update_topic_anonymous_reply_count
	 * @todo   Implement test_bbp_update_topic_anonymous_reply_count().
	 */
	public function test_bbp_update_topic_anonymous_reply_count() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}
}
