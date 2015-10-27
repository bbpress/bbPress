<?php

/**
 * Tests for the topic component functions.
 *
 * @group topics
 * @group functions
 * @group topic
 */
class BBP_Tests_Topics_Functions_Topic extends BBP_UnitTestCase {

	/**
	 * @group canonical
	 * @covers ::bbp_insert_topic
	 */
	public function test_bbp_insert_topic() {

		$f = $this->factory->forum->create();

		$now = time();
		$post_date = date( 'Y-m-d H:i:s', $now - 60 * 60 * 100 );

		$t = $this->factory->topic->create( array(
			'post_title' => 'Topic 1',
			'post_content' => 'Content for Topic 1',
			'post_parent' => $f,
			'post_date' => $post_date,
			'topic_meta' => array(
				'forum_id' => $f,
			),
		) );

		$r = $this->factory->reply->create( array(
			'post_parent' => $t,
			'post_date' => $post_date,
			'reply_meta' => array(
				'forum_id' => $f,
				'topic_id' => $t,
			),
		) );

		// Get the topic.
		$topic = bbp_get_topic( $t );

		remove_all_filters( 'bbp_get_topic_content' );

		// Topic post.
		$this->assertSame( 'Topic 1', bbp_get_topic_title( $t ) );
		$this->assertSame( 'Content for Topic 1', bbp_get_topic_content( $t ) );
		$this->assertSame( 'publish', bbp_get_topic_status( $t ) );
		$this->assertSame( $f, wp_get_post_parent_id( $t ) );
		$this->assertEquals( 'http://' . WP_TESTS_DOMAIN . '/?topic=' . $topic->post_name, $topic->guid );

		// Topic meta.
		$this->assertSame( $f, bbp_get_topic_forum_id( $t ) );
		$this->assertSame( 1, bbp_get_topic_reply_count( $t, true ) );
		$this->assertSame( 0, bbp_get_topic_reply_count_hidden( $t, true ) );
		$this->assertSame( 1, bbp_get_topic_voice_count( $t, true ) );
		$this->assertSame( $r, bbp_get_topic_last_reply_id( $t ) );
		$this->assertSame( $r, bbp_get_topic_last_active_id( $t ) );
		$this->assertSame( '4 days, 4 hours ago', bbp_get_topic_last_active_time( $t ) );
	}

	/**
	 * @covers ::bbp_new_topic_handler
	 * @todo   Implement test_bbp_new_topic_handler().
	 */
	public function test_bbp_new_topic_handler() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_edit_topic_handler
	 * @todo   Implement test_bbp_edit_topic_handler().
	 */
	public function test_bbp_edit_topic_handler() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_update_topic
	 * @todo   Implement test_bbp_update_topic().
	 */
	public function test_bbp_update_topic() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_update_topic_walker
	 * @todo   Implement test_bbp_update_topic_walker().
	 */
	public function test_bbp_update_topic_walker() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_move_topic_handler
	 */
	public function test_bbp_move_topic_handler() {
		$old_current_user = 0;
		$this->old_current_user = get_current_user_id();
		$this->set_current_user( $this->factory->user->create( array( 'role' => 'administrator' ) ) );
		$this->keymaster_id = get_current_user_id();
		bbp_set_user_role( $this->keymaster_id, bbp_get_keymaster_role() );

		$old_forum_id = $this->factory->forum->create();
		$topic_id = $this->factory->topic->create( array(
			'post_parent' => $old_forum_id,
			'topic_meta' => array(
				'forum_id' => $old_forum_id,
			),
		) );

		$reply_id = $this->factory->reply->create( array(
			'post_parent' => $topic_id,
			'reply_meta' => array(
				'forum_id' => $old_forum_id,
				'topic_id' => $topic_id,
			),
		) );

		// Topic post parent
		$topic_parent = wp_get_post_parent_id( $topic_id );
		$this->assertSame( $old_forum_id, $topic_parent );

		// Forum meta
		$this->assertSame( 1, bbp_get_forum_topic_count( $old_forum_id, true, true ) );
		$this->assertSame( 1, bbp_get_forum_reply_count( $old_forum_id, true, true ) );
		$this->assertSame( $topic_id, bbp_get_forum_last_topic_id( $old_forum_id ) );
		$this->assertSame( $reply_id, bbp_get_forum_last_reply_id( $old_forum_id ) );
		$this->assertSame( $reply_id, bbp_get_forum_last_active_id( $old_forum_id ) );

		// Topic meta
		$this->assertSame( $old_forum_id, bbp_get_topic_forum_id( $topic_id ) );
		$this->assertSame( 1, bbp_get_topic_voice_count( $topic_id, true ) );
		$this->assertSame( 1, bbp_get_topic_reply_count( $topic_id, true ) );
		$this->assertSame( $reply_id, bbp_get_topic_last_reply_id( $topic_id ) );
		$this->assertSame( $reply_id, bbp_get_topic_last_active_id( $topic_id ) );

		// Reply Meta
		$this->assertSame( $old_forum_id, bbp_get_reply_forum_id( $reply_id ) );
		$this->assertSame( $topic_id, bbp_get_reply_topic_id( $reply_id ) );

		// Create a new forum
		$new_forum_id = $this->factory->forum->create();

		// Move the topic into the new forum
		bbp_move_topic_handler( $topic_id, $old_forum_id, $new_forum_id );

		// Topic post parent
		$topic_parent = wp_get_post_parent_id( $topic_id );
		$this->assertSame( $new_forum_id, $topic_parent );

		// Forum meta
		$this->assertSame( 1, bbp_get_forum_topic_count( $new_forum_id, true, true ) );
		$this->assertSame( 1, bbp_get_forum_reply_count( $new_forum_id, true, true ) );
		$this->assertSame( $topic_id, bbp_get_forum_last_topic_id( $new_forum_id ) );
		$this->assertSame( $reply_id, bbp_get_forum_last_reply_id( $new_forum_id ) );
		$this->assertSame( $reply_id, bbp_get_forum_last_active_id( $new_forum_id ) );

		// Topic meta
		$this->assertSame( $new_forum_id, bbp_get_topic_forum_id( $topic_id ) );
		$this->assertSame( 1, bbp_get_topic_voice_count( $topic_id, true ) );
		$this->assertSame( 1, bbp_get_topic_reply_count( $topic_id, true ) );
		$this->assertSame( $reply_id, bbp_get_topic_last_reply_id( $topic_id ) );
		$this->assertSame( $reply_id, bbp_get_topic_last_active_id( $topic_id ) );

		// Reply Meta
		$this->assertSame( $new_forum_id, bbp_get_reply_forum_id( $reply_id ) );
		$this->assertSame( $topic_id, bbp_get_reply_topic_id( $reply_id ) );

		// Retore the user
		$this->set_current_user( $this->old_current_user );
	}

	/**
	 * @covers ::bbp_merge_topic_handler
	 * @todo   Implement test_bbp_merge_topic_handler().
	 */
	public function test_bbp_merge_topic_handler() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_merge_topic_count
	 * @todo   Implement test_bbp_merge_topic_count().
	 */
	public function test_bbp_merge_topic_count() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_split_topic_handler
	 * @todo   Implement test_bbp_split_topic_handler().
	 */
	public function test_bbp_split_topic_handler() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_split_topic_count
	 * @todo   Implement test_bbp_split_topic_count().
	 */
	public function test_bbp_split_topic_count() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_get_topic_statuses
	 * @todo   Implement test_bbp_get_topic_statuses().
	 */
	public function test_bbp_get_topic_statuses() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_get_topic_types
	 * @todo   Implement test_bbp_get_topic_types().
	 */
	public function test_bbp_get_topic_types() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_get_stickies
	 * @todo   Implement test_bbp_get_stickies().
	 */
	public function test_bbp_get_stickies() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_get_super_stickies
	 * @todo   Implement test_bbp_get_super_stickies().
	 */
	public function test_bbp_get_super_stickies() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_toggle_topic_handler
	 * @todo   Implement test_bbp_toggle_topic_handler().
	 */
	public function test_bbp_toggle_topic_handler() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_remove_topic_from_all_favorites
	 * @todo   Implement test_bbp_remove_topic_from_all_favorites().
	 */
	public function test_bbp_remove_topic_from_all_favorites() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_remove_topic_from_all_subscriptions
	 * @todo   Implement test_bbp_remove_topic_from_all_subscriptions().
	 */
	public function test_bbp_remove_topic_from_all_subscriptions() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_update_topic_forum_id
	 */
	public function test_bbp_update_topic_forum_id() {
		$f = $this->factory->forum->create();
		$t = $this->factory->topic->create( array(
			'post_parent' => $f,
			'topic_meta' => array(
				'forum_id' => $f,
			),
		) );

		$forum_id = bbp_get_topic_forum_id( $t );
		$this->assertSame( $f, $forum_id );

		$topic_parent = wp_get_post_parent_id( $t );
		$this->assertSame( $f, $topic_parent );

		$this->assertTrue( delete_post_meta_by_key( '_bbp_forum_id' ) );

		bbp_update_topic_forum_id( $t, $f );

		$forum_id = bbp_get_topic_forum_id( $t );
		$this->assertSame( $f, $forum_id );
	}

	/**
	 * @covers ::bbp_update_topic_topic_id
	 * @todo   Implement test_bbp_update_topic_topic_id().
	 */
	public function test_bbp_update_topic_topic_id() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_update_topic_revision_log
	 * @todo   Implement test_bbp_update_topic_revision_log().
	 */
	public function test_bbp_update_topic_revision_log() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_delete_topic
	 * @todo   Implement test_bbp_delete_topic().
	 */
	public function test_bbp_delete_topic() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_delete_topic_replies
	 */
	public function test_bbp_delete_topic_replies() {
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

		$this->assertSame( 2, bbp_get_topic_reply_count( $t, true ) );

		bbp_delete_topic_replies( $t );

		$count = count( bbp_get_all_child_ids( $t, bbp_get_reply_post_type() ) );
		$this->assertSame( 0, ( $count ) );
	}

	/**
	 * @covers ::bbp_trash_topic
	 * @todo   Implement test_bbp_trash_topic().
	 */
	public function test_bbp_trash_topic() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_trash_topic_replies
	 * @todo   Implement test_bbp_trash_topic_replies().
	 */
	public function test_bbp_trash_topic_replies() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_untrash_topic
	 * @todo   Implement test_bbp_untrash_topic().
	 */
	public function test_bbp_untrash_topic() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_untrash_topic_replies
	 * @todo   Implement test_bbp_untrash_topic_replies().
	 */
	public function test_bbp_untrash_topic_replies() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_deleted_topic
	 * @todo   Implement test_bbp_deleted_topic().
	 */
	public function test_bbp_deleted_topic() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_trashed_topic
	 * @todo   Implement test_bbp_trashed_topic().
	 */
	public function test_bbp_trashed_topic() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_untrashed_topic
	 * @todo   Implement test_bbp_untrashed_topic().
	 */
	public function test_bbp_untrashed_topic() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_get_topics_per_page
	 * @todo   Implement test_bbp_get_topics_per_page().
	 */
	public function test_bbp_get_topics_per_page() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_get_topics_per_rss_page
	 * @todo   Implement test_bbp_get_topics_per_rss_page().
	 */
	public function test_bbp_get_topics_per_rss_page() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_topic_content_autoembed
	 * @todo   Implement test_bbp_topic_content_autoembed().
	 */
	public function test_bbp_topic_content_autoembed() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_display_topics_feed_rss2
	 * @todo   Implement test_bbp_display_topics_feed_rss2().
	 */
	public function test_bbp_display_topics_feed_rss2() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_check_topic_edit
	 * @todo   Implement test_bbp_check_topic_edit().
	 */
	public function test_bbp_check_topic_edit() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}
}
