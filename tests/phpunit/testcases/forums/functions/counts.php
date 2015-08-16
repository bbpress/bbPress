<?php

/**
 * Tests for the forum component count functions.
 *
 * @group forums
 * @group functions
 * @group counts
 */
class BBP_Tests_Forums_Functions_Counts extends BBP_UnitTestCase {

	/**
	 * Generic function to test the forum counts with a new topic
	 */
	public function test_bbp_forum_new_topic_counts() {
		$f = $this->factory->forum->create();
		$t1 = $this->factory->topic->create( array(
			'post_parent' => $f,
			'post_author' => bbp_get_current_user_id(),
			'topic_meta' => array(
				'forum_id' => $f,
			),
		) );
		$u = $this->factory->user->create();

		// Cheating here, but we need $_SERVER['SERVER_NAME'] to be set.
		$this->setUp_wp_mail( false );

		// Simulate the 'bbp_new_topic' action.
		do_action( 'bbp_new_topic', $t1, $f, false, bbp_get_current_user_id(), $t1 );

		// Reverse our changes.
		$this->tearDown_wp_mail( false );

		$count = bbp_get_forum_topic_count( $f, true, true );
		$this->assertSame( 1, $count );

		$count = bbp_get_forum_topic_count_hidden( $f, true, true );
		$this->assertSame( 0, $count );

		$t2 = $this->factory->topic->create( array(
			'post_parent' => $f,
			'post_author' => $u,
			'topic_meta' => array(
				'forum_id' => $f,
			),
		) );

		// Cheating here, but we need $_SERVER['SERVER_NAME'] to be set.
		$this->setUp_wp_mail( false );

		// Simulate the 'bbp_new_topic' action.
		do_action( 'bbp_new_topic', $t2, $f, false, $u , $t2 );

		// Reverse our changes.
		$this->tearDown_wp_mail( false );

		$count = bbp_get_forum_topic_count( $f, true, true );
		$this->assertSame( 2, $count );

		$count = bbp_get_forum_topic_count_hidden( $f, true, true );
		$this->assertSame( 0, $count );
	}

	/**
	 * Generic function to test the forum counts on a trashed/untrashed topic
	 */
	public function test_bbp_forum_trashed_untrashed_topic_counts() {
		$f = $this->factory->forum->create();
		$t = $this->factory->topic->create_many( 3, array(
			'post_parent' => $f,
			'topic_meta' => array(
				'forum_id' => $f,
			),
		) );
		$r1 = $this->factory->reply->create_many( 2, array(
			'post_parent' => $t[1],
			'reply_meta' => array(
				'forum_id' => $f,
				'topic_id' => $t[1],
			),
		) );
		$r2 = $this->factory->reply->create_many( 2, array(
			'post_parent' => $t[2],
			'reply_meta' => array(
				'forum_id' => $f,
				'topic_id' => $t[2],
			),
		) );

		$count = bbp_update_forum_topic_count( $f );
		$this->assertSame( 3, $count );

		$count = bbp_update_forum_topic_count_hidden( $f );
		$this->assertSame( 0, $count );

		$count = bbp_update_forum_reply_count( $f );
		$this->assertSame( 4, $count );

		// ToDo: Update this to use bbp_trash_topic().
		wp_trash_post( $t[2] );

		$count = bbp_get_forum_topic_count( $f, true, true );
		$this->assertSame( 2, $count );

		$count = bbp_get_forum_topic_count_hidden( $f, true, true );
		$this->assertSame( 1, $count );

		$count = bbp_get_forum_reply_count( $f, true, true );
		$this->assertSame( 2, $count );

		// ToDo: Update this to use bbp_untrash_topic().
		wp_untrash_post( $t[2] );

		$count = bbp_get_forum_topic_count( $f, true, true );
		$this->assertSame( 3, $count );

		$count = bbp_get_forum_topic_count_hidden( $f, true, true );
		$this->assertSame( 0, $count );

		$count = bbp_get_forum_reply_count( $f, true, true );
		$this->assertSame( 4, $count );
	}

	/**
	 * Generic function to test the forum counts on a spammed/unspammed topic
	 */
	public function test_bbp_forum_spammed_unspammed_topic_counts() {
		$f = $this->factory->forum->create();
		$t = $this->factory->topic->create_many( 3, array(
			'post_parent' => $f,
			'topic_meta' => array(
				'forum_id' => $f,
			),
		) );
		$r1 = $this->factory->reply->create_many( 2, array(
			'post_parent' => $t[1],
			'reply_meta' => array(
				'forum_id' => $f,
				'topic_id' => $t[1],
			),
		) );
		$r2 = $this->factory->reply->create_many( 2, array(
			'post_parent' => $t[2],
			'reply_meta' => array(
				'forum_id' => $f,
				'topic_id' => $t[2],
			),
		) );

		$count = bbp_update_forum_topic_count( $f );
		$this->assertSame( 3, $count );

		$count = bbp_update_forum_topic_count_hidden( $f );
		$this->assertSame( 0, $count );

		$count = bbp_update_forum_reply_count( $f );
		$this->assertSame( 4, $count );

		bbp_spam_topic( $t[2] );

		$count = bbp_get_forum_topic_count( $f, true, true );
		$this->assertSame( 2, $count );

		$count = bbp_get_forum_topic_count_hidden( $f, true, true );
		$this->assertSame( 1, $count );

		$count = bbp_get_forum_reply_count( $f, true, true );
		$this->assertSame( 2, $count );

		bbp_unspam_topic( $t[2] );

		$count = bbp_get_forum_topic_count( $f, true, true );
		$this->assertSame( 3, $count );

		$count = bbp_get_forum_topic_count_hidden( $f, true, true );
		$this->assertSame( 0, $count );

		$count = bbp_get_forum_reply_count( $f, true, true );
		$this->assertSame( 4, $count );
	}

	/**
	 * Generic function to test the forum counts on a approved/unapproved topic
	 */
	public function test_bbp_forum_approved_unapproved_topic_counts() {
		$f = $this->factory->forum->create();
		$t = $this->factory->topic->create_many( 3, array(
			'post_parent' => $f,
			'topic_meta' => array(
				'forum_id' => $f,
			),
		) );
		$r1 = $this->factory->reply->create_many( 2, array(
			'post_parent' => $t[1],
			'reply_meta' => array(
				'forum_id' => $f,
				'topic_id' => $t[1],
			),
		) );
		$r2 = $this->factory->reply->create_many( 2, array(
			'post_parent' => $t[2],
			'reply_meta' => array(
				'forum_id' => $f,
				'topic_id' => $t[2],
			),
		) );

		$count = bbp_update_forum_topic_count( $f );
		$this->assertSame( 3, $count );

		$count = bbp_update_forum_topic_count_hidden( $f );
		$this->assertSame( 0, $count );

		$count = bbp_update_forum_reply_count( $f );
		$this->assertSame( 4, $count );

		bbp_unapprove_topic( $t[2] );

		$count = bbp_get_forum_topic_count( $f, true, true );
		$this->assertSame( 2, $count );

		$count = bbp_get_forum_topic_count_hidden( $f, true, true );
		$this->assertSame( 1, $count );

		$count = bbp_get_forum_reply_count( $f, true, true );
		$this->assertSame( 2, $count );

		bbp_approve_topic( $t[2] );

		$count = bbp_get_forum_topic_count( $f, true, true );
		$this->assertSame( 3, $count );

		$count = bbp_get_forum_topic_count_hidden( $f, true, true );
		$this->assertSame( 0, $count );

		$count = bbp_get_forum_reply_count( $f, true, true );
		$this->assertSame( 4, $count );
	}

	/**
	 * @covers ::bbp_bump_forum_topic_count
	 */
	public function test_bbp_bump_forum_topic_count() {
		$f = $this->factory->forum->create();

		$count = bbp_get_forum_topic_count( $f );
		$this->assertSame( '0', $count );

		bbp_bump_forum_topic_count( $f );

		$count = bbp_get_forum_topic_count( $f );
		$this->assertSame( '1', $count );
	}

	/**
	 * @covers ::bbp_bump_forum_topic_count_hidden
	 */
	public function test_bbp_bump_forum_topic_count_hidden() {
		$f = $this->factory->forum->create();

		$count = bbp_get_forum_topic_count_hidden( $f );
		$this->assertSame( '0', $count );

		bbp_bump_forum_topic_count_hidden( $f );

		$count = bbp_get_forum_topic_count_hidden( $f );
		$this->assertSame( '1', $count );
	}

	/**
	 * @covers ::bbp_bump_forum_reply_count
	 */
	public function test_bbp_bump_forum_reply_count() {
		$f = $this->factory->forum->create();

		$count = bbp_get_forum_reply_count( $f );
		$this->assertSame( '0', $count );

		bbp_bump_forum_reply_count( $f );

		$count = bbp_get_forum_reply_count( $f );
		$this->assertSame( '1', $count );
	}

	/**
	 * @covers ::bbp_update_forum_subforum_count
	 */
	public function test_bbp_update_forum_subforum_count() {
		$f1 = $this->factory->forum->create();

		$f2 = $this->factory->forum->create_many( 3, array(
			'post_parent' => $f1,
		) );

		$count = bbp_get_forum_subforum_count( $f1, true );
		$this->assertSame( 0, $count );

		bbp_update_forum_subforum_count( $f1 );

		$count = bbp_get_forum_subforum_count( $f1, true );
		$this->assertSame( 3, $count );
	}

	/**
	 * @covers ::bbp_update_forum_topic_count
	 */
	public function test_bbp_update_forum_topic_count() {
		// Create a top level forum f1
		$f1 = $this->factory->forum->create();

		bbp_normalize_forum( $f1 );

		$count = bbp_get_forum_topic_count( $f1 );
		$this->assertSame( '0', $count );

		// Create 3 topics in f1
		$t = $this->factory->topic->create_many( 3, array(
			'post_parent' => $f1,
		) );

		bbp_update_forum_topic_count( $f1 );

		$count = bbp_get_forum_topic_count( $f1 );
		$this->assertSame( '3', $count );

		// Create a new sub forum of f1
		$f2 = $this->factory->forum->create( array(
			'post_parent' => $f1,
		) );

		// Create another sub forum of f1
		$f3 = $this->factory->forum->create( array(
			'post_parent' => $f1,
		) );

		bbp_update_forum_topic_count( $f1 );
		bbp_update_forum_topic_count( $f2 );
		bbp_update_forum_topic_count( $f3 );

		$count = bbp_get_forum_topic_count( $f1 );
		$this->assertSame( '3', $count );

		$count = bbp_get_forum_topic_count( $f2 );
		$this->assertSame( '0', $count );

		$count = bbp_get_forum_topic_count( $f3 );
		$this->assertSame( '0', $count );

		// Create some topics in forum f2
		$this->factory->topic->create_many( 4, array(
			'post_parent' => $f2,
		) );

		bbp_update_forum_topic_count( $f1 );
		bbp_update_forum_topic_count( $f2 );
		bbp_update_forum_topic_count( $f3 );

		$count = bbp_get_forum_topic_count( $f1 );
		$this->assertSame( '7', $count );

		$count = bbp_get_forum_topic_count( $f2 );
		$this->assertSame( '4', $count );

		$count = bbp_get_forum_topic_count( $f3 );
		$this->assertSame( '0', $count );

		// Create some topics in forum f3
		$this->factory->topic->create_many( 5, array(
			'post_parent' => $f3,
		) );

		bbp_update_forum_topic_count( $f1 );
		bbp_update_forum_topic_count( $f2 );
		bbp_update_forum_topic_count( $f3 );

		$count = bbp_get_forum_topic_count( $f1 );
		$this->assertSame( '12', $count );

		$count = bbp_get_forum_topic_count( $f2 );
		$this->assertSame( '4', $count );

		$count = bbp_get_forum_topic_count( $f3 );
		$this->assertSame( '5', $count );
	}

	/**
	 * @covers ::bbp_update_forum_topic_count_hidden
	 */
	public function test_bbp_update_forum_topic_count_hidden() {
		$f = $this->factory->forum->create();

		$count = bbp_get_forum_topic_count( $f, false, true );
		$this->assertSame( 0, $count );

		$t = $this->factory->topic->create_many( 3, array(
			'post_parent' => $f,
			'topic_meta' => array(
				'forum_id' => $f,
			),
		) );

		bbp_update_forum_topic_count_hidden( $f );

		$count = bbp_get_forum_topic_count_hidden( $f, true );
		$this->assertSame( 0, $count );;

		bbp_spam_topic( $t[2] );

		bbp_update_forum_topic_count_hidden( $f );

		$count = bbp_get_forum_topic_count_hidden( $f, true );
		$this->assertSame( 1, $count );;

		bbp_unapprove_topic( $t[0] );

		bbp_update_forum_topic_count_hidden( $f );

		$count = bbp_get_forum_topic_count_hidden( $f, true );
		$this->assertSame( 2, $count );
	}

	/**
	 * @covers ::bbp_update_forum_reply_count
	 */
	public function test_bbp_update_forum_reply_count() {
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

		$count = bbp_get_forum_reply_count( $f1, false, true );
		$this->assertSame( 0, $count );

		$count = bbp_update_forum_reply_count( $f1 );
		$this->assertSame( 0, $count );

		$this->factory->reply->create_many( 3, array(
			'post_parent' => $t1,
			'reply_meta' => array(
				'forum_id' => $f1,
				'topic_id' => $t1,
			),
		) );

		$count = bbp_update_forum_reply_count( $f1 );
		$this->assertSame( 3, $count );

		$this->factory->reply->create_many( 3, array(
			'post_parent' => $t2,
			'reply_meta' => array(
				'forum_id' => $f2,
				'topic_id' => $t2,
			),
		) );

		$count = bbp_update_forum_reply_count( $f1 );
		$this->assertSame( 6, $count );

		$count = bbp_update_forum_reply_count( $f2 );
		$this->assertSame( 3, $count );
	}
}
