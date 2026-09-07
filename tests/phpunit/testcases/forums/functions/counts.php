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
	 * @covers ::bbp_get_countable_forum_statuses
	 */
	public function test_bbp_get_countable_forum_statuses() {
		$include_private = function ( $statuses ) {
			$statuses[] = bbp_get_private_status_id();
			return $statuses;
		};

		add_filter( 'bbp_get_public_forum_statuses', $include_private );
		$statuses = bbp_get_countable_forum_statuses();
		remove_filter( 'bbp_get_public_forum_statuses', $include_private );

		$this->assertSame(
			array(
				bbp_get_public_status_id(),
				bbp_get_private_status_id(),
				bbp_get_hidden_status_id()
			),
			$statuses
		);
	}

	/**
	 * Generic function to test the forum counts with a new topic
	 *
	 * @covers ::bbp_update_counts_on_transition_post_status
	 */
	public function test_bbp_forum_new_topic_counts() {
		$f = $this->factory->forum->create();
		$this->factory->topic->create( array(
			'post_parent' => $f,
			'post_author' => bbp_get_current_user_id(),
			'topic_meta' => array(
				'forum_id' => $f,
			),
		) );
		$u = $this->factory->user->create();

		$count = bbp_get_forum_topic_count( $f, true, true );
		$this->assertSame( 1, $count );

		$count = bbp_get_forum_topic_count_hidden( $f, true, true );
		$this->assertSame( 0, $count );

		$this->factory->topic->create( array(
			'post_parent' => $f,
			'post_author' => $u,
			'topic_meta' => array(
				'forum_id' => $f,
			),
		) );

		$count = bbp_get_forum_topic_count( $f, true, true );
		$this->assertSame( 2, $count );

		$count = bbp_get_forum_topic_count_hidden( $f, true, true );
		$this->assertSame( 0, $count );
	}

	/**
	 * @covers ::bbp_update_counts_on_transition_post_status
	 */
	public function test_bbp_forum_new_pending_topic_counts() {
		$user_id  = $this->factory->user->create();
		$forum_id = $this->factory->forum->create();

		$this->factory->topic->create( array(
			'post_author' => $user_id,
			'post_parent' => $forum_id,
			'post_status' => bbp_get_pending_status_id(),
			'topic_meta'  => array( 'forum_id' => $forum_id ),
		) );

		$this->assertSame( 0, bbp_get_forum_topic_count( $forum_id, false, true ) );
		$this->assertSame( 1, bbp_get_forum_topic_count_hidden( $forum_id, false, true ) );
		$this->assertSame( 0, bbp_get_user_topic_count( $user_id, true ) );
	}

	/**
	 * Generic function to test the forum counts on a trashed/untrashed topic
	 */
	public function test_bbp_forum_trashed_untrashed_topic_counts() {
		$f = $this->factory->forum->create();
		$t = $this->factory->topic->create_many( 2, array(
			'post_parent' => $f,
			'topic_meta' => array(
				'forum_id' => $f,
			),
		) );
		$r1 = $this->factory->reply->create_many( 1, array(
			'post_parent' => $t[0],
			'reply_meta' => array(
				'forum_id' => $f,
				'topic_id' => $t[0],
			),
		) );
		$r2 = $this->factory->reply->create_many( 1, array(
			'post_parent' => $t[1],
			'reply_meta' => array(
				'forum_id' => $f,
				'topic_id' => $t[1],
			),
		) );

		$count = bbp_update_forum_topic_count( $f );
		$this->assertSame( 2, $count );

		$count = bbp_update_forum_topic_count_hidden( $f );
		$this->assertSame( 0, $count );

		$count = bbp_update_forum_reply_count( $f );
		$this->assertSame( 2, $count );

		// ToDo: Update this to use bbp_trash_topic().
		wp_trash_post( $t[1] );

		$count = bbp_get_forum_topic_count( $f, true, true );
		$this->assertSame( 1, $count );

		$count = bbp_get_forum_topic_count_hidden( $f, true, true );
		$this->assertSame( 1, $count );

		$count = bbp_get_forum_reply_count( $f, true, true );
		$this->assertSame( 1, $count );

		// ToDo: Update this to use bbp_untrash_topic().
		wp_untrash_post( $t[1] );

		$count = bbp_get_forum_topic_count( $f, true, true );
		$this->assertSame( 2, $count );

		$count = bbp_get_forum_topic_count_hidden( $f, true, true );
		$this->assertSame( 0, $count );

		$count = bbp_get_forum_reply_count( $f, true, true );
		$this->assertSame( 2, $count );
	}

	/**
	 * Generic function to test the forum counts on a spammed/unspammed topic
	 */
	public function test_bbp_forum_spammed_unspammed_topic_counts() {
		$f = $this->factory->forum->create();
		$t = $this->factory->topic->create_many( 2, array(
			'post_parent' => $f,
			'topic_meta' => array(
				'forum_id' => $f,
			),
		) );
		$r1 = $this->factory->reply->create_many( 1, array(
			'post_parent' => $t[0],
			'reply_meta' => array(
				'forum_id' => $f,
				'topic_id' => $t[0],
			),
		) );
		$r2 = $this->factory->reply->create_many( 1, array(
			'post_parent' => $t[1],
			'reply_meta' => array(
				'forum_id' => $f,
				'topic_id' => $t[1],
			),
		) );

		$count = bbp_update_forum_topic_count( $f );
		$this->assertSame( 2, $count );

		$count = bbp_update_forum_topic_count_hidden( $f );
		$this->assertSame( 0, $count );

		$count = bbp_update_forum_reply_count( $f );
		$this->assertSame( 2, $count );

		bbp_spam_topic( $t[1] );

		$count = bbp_get_forum_topic_count( $f, true, true );
		$this->assertSame( 1, $count );

		$count = bbp_get_forum_topic_count_hidden( $f, true, true );
		$this->assertSame( 1, $count );

		$count = bbp_get_forum_reply_count( $f, true, true );
		$this->assertSame( 1, $count );

		bbp_unspam_topic( $t[1] );

		$count = bbp_get_forum_topic_count( $f, true, true );
		$this->assertSame( 2, $count );

		$count = bbp_get_forum_topic_count_hidden( $f, true, true );
		$this->assertSame( 0, $count );

		$count = bbp_get_forum_reply_count( $f, true, true );
		$this->assertSame( 2, $count );
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
	 * @covers ::bbp_increase_forum_topic_count
	 */
	public function test_bbp_increase_forum_topic_count() {
		$f = $this->factory->forum->create();

		$count = bbp_get_forum_topic_count( $f );
		$this->assertSame( '0', $count );

		bbp_increase_forum_topic_count( $f );

		$count = bbp_get_forum_topic_count( $f );
		$this->assertSame( '1', $count );
	}

	/**
	 * @covers ::bbp_decrease_forum_topic_count
	 */
	public function test_bbp_decrease_forum_topic_count() {
		$f = $this->factory->forum->create();

		$count = bbp_get_forum_topic_count( $f );
		$this->assertSame( '0', $count );

		$t = $this->factory->topic->create_many( 2, array(
			'post_parent' => $f,
		) );

		bbp_update_forum_topic_count( $f );

		$count = bbp_get_forum_topic_count( $f );
		$this->assertSame( '2', $count );

		bbp_update_forum_topic_count( $f );

		bbp_decrease_forum_topic_count( $f );

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
	 * @covers ::bbp_increase_forum_topic_count_hidden
	 */
	public function test_bbp_increase_forum_topic_count_hidden() {
		$f = $this->factory->forum->create();

		$count = bbp_get_forum_topic_count_hidden( $f );
		$this->assertSame( '0', $count );

		bbp_increase_forum_topic_count_hidden( $f );

		$count = bbp_get_forum_topic_count_hidden( $f );
		$this->assertSame( '1', $count );
	}

	/**
	 * @covers ::bbp_decrease_forum_topic_count_hidden
	 */
	public function test_bbp_decrease_forum_topic_count_hidden() {
		$f = $this->factory->forum->create();

		$count = bbp_get_forum_topic_count_hidden( $f );
		$this->assertSame( '0', $count );

		$t = $this->factory->topic->create_many( 2, array(
			'post_parent' => $f,
			'post_status' => bbp_get_spam_status_id(),
			'topic_meta' => array(
				'forum_id' => $f,
				'spam_meta_status' => 'publish',
			)
		) );

		bbp_update_forum_topic_count_hidden( $f );

		$count = bbp_get_forum_topic_count_hidden( $f );
		$this->assertSame( '2', $count );

		bbp_decrease_forum_topic_count_hidden( $f );

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
	 * @covers ::bbp_increase_forum_reply_count
	 */
	public function test_bbp_increase_forum_reply_count() {
		$f = $this->factory->forum->create();

		$count = bbp_get_forum_reply_count( $f );
		$this->assertSame( '0', $count );

		bbp_increase_forum_reply_count( $f );

		$count = bbp_get_forum_reply_count( $f );
		$this->assertSame( '1', $count );
	}

	/**
	 * @covers ::bbp_decrease_forum_reply_count
	 */
	public function test_bbp_decrease_forum_reply_count() {
		$f = $this->factory->forum->create();

		$count = bbp_get_forum_reply_count( $f );
		$this->assertSame( '0', $count );

		$t = $this->factory->topic->create( array(
			'post_parent' => $f,
		) );

		$r = $this->factory->reply->create_many( 2, array(
			'post_parent' => $t,
		) );

		bbp_update_forum_reply_count( $f );

		$count = bbp_get_forum_reply_count( $f );
		$this->assertSame( '2', $count );

		bbp_decrease_forum_reply_count( $f );

		$count = bbp_get_forum_reply_count( $f );
		$this->assertSame( '1', $count );
	}

	/**
	 * @covers ::bbp_update_forum_subforum_count
	 * @ticket BBP2649
	 */
	public function test_bbp_update_forum_subforum_count() {
		$f1 = $this->factory->forum->create();

		$count = bbp_get_forum_subforum_count( $f1, true );
		$this->assertSame( 0, $count );

		$f2 = $this->factory->forum->create_many( 3, array(
			'post_parent' => $f1,
		) );

		$count = bbp_get_forum_subforum_count( $f1, true );
		$this->assertSame( 3, $count );

		bbp_update_forum_subforum_count( $f1, 10 );

		$count = bbp_get_forum_subforum_count( $f1, true );
		$this->assertSame( 10, $count );

		bbp_update_forum_subforum_count( $f1 );

		$count = bbp_get_forum_subforum_count( $f1, true );
		$this->assertSame( 3, $count );
	}

	/**
	 * @covers ::bbp_update_forum_subforum_count
	 * @ticket BBP2649
	 */
	public function test_bbp_update_forum_subforum_count_includes_supported_visibilities() {
		$forum_id = $this->factory->forum->create();

		foreach ( array( bbp_get_public_status_id(), bbp_get_private_status_id(), bbp_get_hidden_status_id() ) as $status ) {
			$subforum_id = $this->factory->forum->create( array(
				'post_parent' => $forum_id,
				'post_status' => $status,
			) );
		}

		// Count the canonical hierarchy once, even if relationship meta is duplicated
		add_post_meta( $subforum_id, '_bbp_forum_id', $forum_id );

		$trashed_id = $this->factory->forum->create( array( 'post_parent' => $forum_id ) );
		wp_trash_post( $trashed_id );

		bbp_update_forum_subforum_count( $forum_id );

		$this->assertSame( 3, bbp_get_forum_subforum_count( $forum_id, true ) );
	}

	/**
	 * @covers ::bbp_update_parent_forum_subforum_count
	 * @covers ::bbp_update_forum_subforum_count_on_transition_post_status
	 * @ticket BBP2649
	 */
	public function test_subforum_count_tracks_trash_restore_and_deletion() {
		$forum_id    = $this->factory->forum->create();
		$subforum_id = $this->factory->forum->create( array( 'post_parent' => $forum_id ) );

		$this->assertSame( 1, bbp_get_forum_subforum_count( $forum_id, true ) );

		wp_trash_post( $subforum_id );
		$this->assertSame( 0, bbp_get_forum_subforum_count( $forum_id, true ) );

		wp_untrash_post( $subforum_id );
		$this->assertSame( 1, bbp_get_forum_subforum_count( $forum_id, true ) );

		wp_update_post( array(
			'ID'          => $subforum_id,
			'post_status' => 'draft',
		) );
		$this->assertSame( 0, bbp_get_forum_subforum_count( $forum_id, true ) );

		wp_publish_post( $subforum_id );
		$this->assertSame( 1, bbp_get_forum_subforum_count( $forum_id, true ) );

		wp_delete_post( $subforum_id, true );
		$this->assertSame( 0, bbp_get_forum_subforum_count( $forum_id, true ) );
	}

	/**
	 * @covers ::bbp_reparent_forum_subforums
	 * @covers ::bbp_update_parent_forum_subforum_count
	 * @ticket BBP2649
	 */
	public function test_deleting_forum_reparents_child_forum_metadata_and_count() {
		$grandparent_id = $this->factory->forum->create();
		$parent_id      = $this->factory->forum->create( array( 'post_parent' => $grandparent_id ) );
		$child_id       = $this->factory->forum->create( array( 'post_parent' => $parent_id ) );

		wp_delete_post( $parent_id, true );

		$this->assertSame( $grandparent_id, (int) get_post_field( 'post_parent', $child_id ) );
		$this->assertSame( $grandparent_id, bbp_get_forum_parent_id( $child_id ) );
		$this->assertSame( 1, bbp_get_forum_subforum_count( $grandparent_id, true ) );
	}

	/**
	 * @covers ::bbp_update_forum_subforum_counts_on_post_updated
	 * @ticket BBP2649
	 */
	public function test_subforum_count_tracks_parent_changes() {
		$old_parent_id = $this->factory->forum->create();
		$new_parent_id = $this->factory->forum->create();
		$subforum_id   = $this->factory->forum->create( array( 'post_parent' => $old_parent_id ) );

		$this->assertSame( 1, bbp_get_forum_subforum_count( $old_parent_id, true ) );
		$this->assertSame( 0, bbp_get_forum_subforum_count( $new_parent_id, true ) );

		wp_update_post( array(
			'ID'          => $subforum_id,
			'post_parent' => $new_parent_id,
		) );

		$this->assertSame( 0, bbp_get_forum_subforum_count( $old_parent_id, true ) );
		$this->assertSame( 1, bbp_get_forum_subforum_count( $new_parent_id, true ) );
	}

	/**
	 * @covers ::bbp_update_forum_subforum_counts_on_post_updated
	 * @ticket BBP2649
	 */
	public function test_subforum_count_tracks_post_type_changes() {
		$parent_id   = $this->factory->forum->create();
		$subforum_id = $this->factory->forum->create( array( 'post_parent' => $parent_id ) );

		$this->assertSame( 1, bbp_get_forum_subforum_count( $parent_id, true ) );

		wp_update_post( array(
			'ID'        => $subforum_id,
			'post_type' => 'post',
		) );
		$this->assertSame( 0, bbp_get_forum_subforum_count( $parent_id, true ) );

		wp_update_post( array(
			'ID'        => $subforum_id,
			'post_type' => bbp_get_forum_post_type(),
		) );
		$this->assertSame( $parent_id, bbp_get_forum_parent_id( $subforum_id ) );
		$this->assertSame( 1, bbp_get_forum_subforum_count( $parent_id, true ) );
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

		$count = bbp_get_forum_topic_count_hidden( $f, true, true );
		$this->assertSame( 0, $count );

		bbp_spam_topic( $t[2] );

		bbp_update_forum_topic_count_hidden( $f );

		$count = bbp_get_forum_topic_count_hidden( $f, true, true );
		$this->assertSame( 1, $count );

		bbp_unapprove_topic( $t[0] );

		bbp_update_forum_topic_count_hidden( $f );

		$count = bbp_get_forum_topic_count_hidden( $f, true, true );
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
