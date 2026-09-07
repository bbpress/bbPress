<?php

/**
 * Tests for the reply component functions.
 *
 * @group replies
 * @group functions
 * @group reply
 */
class BBP_Tests_Replies_Functions_Reply extends BBP_UnitTestCase {

	/**
	 * @group canonical
	 * @covers ::bbp_insert_reply
	 */
	public function test_bbp_insert_reply() {

		$f = $this->factory->forum->create();

		$t = $this->factory->topic->create( array(
			'post_parent' => $f,
			'topic_meta' => array(
				'forum_id' => $f,
			),
		) );

		$r = $this->factory->reply->create( array(
			'post_title' => 'Reply To: Topic 1',
			'post_content' => 'Content of reply to Topic 1',
			'post_parent' => $t,
			'reply_meta' => array(
				'forum_id' => $f,
				'topic_id' => $t,
			),
		) );

		// Get the reply.
		$reply = bbp_get_reply( $r );

		remove_all_filters( 'bbp_get_reply_content' );

		// Reply post.
		$this->assertSame( 'Reply To: Topic 1', bbp_get_reply_title( $r ) );
		$this->assertSame( 'Content of reply to Topic 1', bbp_get_reply_content( $r ) );
		$this->assertSame( 'publish', bbp_get_reply_status( $r ) );
		$this->assertSame( $t, wp_get_post_parent_id( $r ) );
		$this->assertEquals( 'http://' . WP_TESTS_DOMAIN . '/?reply=' . $reply->post_name, $reply->guid );

		// Reply meta.
		$this->assertSame( $f, bbp_get_reply_forum_id( $r ) );
		$this->assertSame( $t, bbp_get_reply_topic_id( $r ) );
	}

	/**
	 * @covers ::bbp_new_reply_handler
	 * @todo   Implement test_bbp_new_reply_handler().
	 */
	public function test_bbp_new_reply_handler() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_edit_reply_handler
	 * @todo   Implement test_bbp_edit_reply_handler().
	 */
	public function test_bbp_edit_reply_handler() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_update_reply
	 * @todo   Implement test_bbp_update_reply().
	 */
	public function test_bbp_update_reply() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_update_reply_walker
	 */
	public function test_bbp_update_reply_walker() {
		$forum_id = $this->factory->forum->create();
		$topic_id = $this->factory->topic->create( array(
			'post_parent' => $forum_id,
			'topic_meta'  => array( 'forum_id' => $forum_id ),
		) );
		$reply_id = wp_insert_post( array(
			'post_parent' => $topic_id,
			'post_status' => bbp_get_public_status_id(),
			'post_type'   => bbp_get_reply_post_type(),
			'post_title'  => 'Reply walker',
		) );
		$active_time = get_post_field( 'post_date', $reply_id );

		bbp_update_reply_walker( $reply_id, $active_time, $forum_id, $topic_id, false );

		$this->assertSame( 1, bbp_get_topic_reply_count( $topic_id, true ) );
		$this->assertSame( 0, bbp_get_topic_reply_count_hidden( $topic_id, true ) );
		$this->assertSame( 1, bbp_get_forum_reply_count( $forum_id, false, true ) );
		$this->assertSame( 0, bbp_get_forum_reply_count_hidden( $forum_id, false, true ) );
		$this->assertSame( $reply_id, bbp_get_topic_last_reply_id( $topic_id ) );
		$this->assertSame( $reply_id, bbp_get_forum_last_reply_id( $forum_id ) );
	}

	/**
	 * @covers ::bbp_update_reply_forum_id
	 */
	public function test_bbp_update_reply_forum_id() {
		bbp_create_initial_content();

		$forum_id = 36;
		$topic_id = 37;
		$reply_id = 38;

		bbp_update_reply_forum_id( $reply_id, $forum_id);

		$reply_forum_id = bbp_get_reply_forum_id( $reply_id );
		$this->assertSame( 36, $reply_forum_id );
	}

	/**
	 * @covers ::bbp_update_reply_topic_id
	 */
	public function test_bbp_update_reply_topic_id() {
		bbp_create_initial_content();

		$forum_id = 36;
		$topic_id = 37;
		$reply_id = 38;

		bbp_update_reply_topic_id( $reply_id, $topic_id);

		$reply_topic_id = bbp_get_reply_topic_id( $reply_id );
		$this->assertSame( 37, $reply_topic_id );
	}

	/**
	 * @covers ::bbp_update_reply_to
	 * @todo   Implement test_bbp_update_reply_to().
	 */
	public function test_bbp_update_reply_to() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_get_reply_ancestors
	 * @todo   Implement test_bbp_get_reply_ancestors().
	 */
	public function test_bbp_get_reply_ancestors() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_update_reply_revision_log
	 * @todo   Implement test_bbp_update_reply_revision_log().
	 */
	public function test_bbp_update_reply_revision_log() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_move_reply_handler
	 * @todo   Implement test_bbp_move_reply_handler().
	 */
	public function test_bbp_move_reply_handler() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_move_reply_count
	 * @todo   Implement test_bbp_move_reply_count().
	 */
	public function test_bbp_move_reply_count() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_toggle_reply_handler
	 * @todo   Implement test_bbp_toggle_reply_handler().
	 */
	public function test_bbp_toggle_reply_handler() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_delete_reply
	 * @todo   Implement test_bbp_delete_reply().
	 */
	public function test_bbp_delete_reply() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_trash_reply
	 * @todo   Implement test_bbp_trash_reply().
	 */
	public function test_bbp_trash_reply() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_untrash_reply
	 * @todo   Implement test_bbp_untrash_reply().
	 */
	public function test_bbp_untrash_reply() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_deleted_reply
	 * @todo   Implement test_bbp_deleted_reply().
	 */
	public function test_bbp_deleted_reply() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_trashed_reply
	 * @todo   Implement test_bbp_trashed_reply().
	 */
	public function test_bbp_trashed_reply() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_untrashed_reply
	 * @todo   Implement test_bbp_untrashed_reply().
	 */
	public function test_bbp_untrashed_reply() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_get_replies_per_page
	 * @todo   Implement test_bbp_get_replies_per_page().
	 */
	public function test_bbp_get_replies_per_page() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_get_replies_per_rss_page
	 * @todo   Implement test_bbp_get_replies_per_rss_page().
	 */
	public function test_bbp_get_replies_per_rss_page() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_reply_content_autoembed
	 * @todo   Implement test_bbp_reply_content_autoembed().
	 */
	public function test_bbp_reply_content_autoembed() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::_bbp_has_replies_where
	 * @todo   Implement test_bbp_has_replies_where().
	 */
	public function test_bbp_has_replies_where() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_display_replies_feed_rss2
	 * @todo   Implement test_bbp_display_replies_feed_rss2().
	 */
	public function test_bbp_display_replies_feed_rss2() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_check_reply_edit
	 * @todo   Implement test_bbp_check_reply_edit().
	 */
	public function test_bbp_check_reply_edit() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_update_reply_position
	 * @todo   Implement test_bbp_update_reply_position().
	 */
	public function test_bbp_update_reply_position() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_get_reply_position_raw
	 * @todo   Implement test_bbp_get_reply_position_raw().
	 */
	public function test_bbp_get_reply_position_raw() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_list_replies
	 * @todo   Implement test_bbp_list_replies().
	 */
	public function test_bbp_list_replies() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_validate_reply_to
	 * @todo   Implement test_bbp_validate_reply_to().
	 */
	public function test_bbp_validate_reply_to() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}
}
