<?php

/**
 * Tests for bbPress forum functions.
 *
 * @group forums
 * @group functions
 * @group forum
 */
class BBP_Tests_Forums_Functions_Forum extends BBP_UnitTestCase {

	/**
	 * @group canonical
	 * @covers ::bbp_insert_forum
	 */
	public function test_bbp_insert_forum() {

		$c = $this->factory->forum->create( array(
			'post_title' => 'Category 1',
			'post_content' => 'Content of Category 1',
			'forum_meta' => array(
				'forum_type' => 'category',
				'status'     => 'open',
			),
		) );

		$f = $this->factory->forum->create( array(
			'post_title' => 'Forum 1',
			'post_content' => 'Content of Forum 1',
			'post_parent' => $c,
			'forum_meta' => array(
				'forum_id'   => $c,
				'forum_type' => 'forum',
				'status'     => 'open',
			),
		) );

		$now = time();
		$post_date = date( 'Y-m-d H:i:s', $now - 60 * 60 * 100 );

		$t = $this->factory->topic->create( array(
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

		// Get the category.
		$category = bbp_get_forum( $c );

		// Get the forum.
		$forum = bbp_get_forum( $f );

		// Category post.
		$this->assertSame( 'Category 1', bbp_get_forum_title( $c ) );
		$this->assertSame( 'Content of Category 1', bbp_get_forum_content( $c ) );
		$this->assertSame( 'open', bbp_get_forum_status( $c ) );
		$this->assertSame( 'category', bbp_get_forum_type( $c ) );
		$this->assertTrue( bbp_is_forum( $c ) );
		$this->assertTrue( bbp_is_forum_category( $c ) );
		$this->assertTrue( bbp_is_forum_open( $c ) );
		$this->assertTrue( bbp_is_forum_public( $c ) );
		$this->assertFalse( bbp_is_forum_closed( $c ) );
		$this->assertFalse( bbp_is_forum_hidden( $c ) );
		$this->assertFalse( bbp_is_forum_private( $c ) );
		$this->assertSame( 0, bbp_get_forum_parent_id( $c ) );
		$this->assertEquals( 'http://' . WP_TESTS_DOMAIN . '/?forum=' . $category->post_name, $category->guid );

		// Forum post.
		$this->assertSame( 'Forum 1', bbp_get_forum_title( $f ) );
		$this->assertSame( 'Content of Forum 1', bbp_get_forum_content( $f ) );
		$this->assertSame( 'open', bbp_get_forum_status( $f ) );
		$this->assertSame( 'forum', bbp_get_forum_type( $f ) );
		$this->assertTrue( bbp_is_forum( $f ) );
		$this->assertTrue( bbp_is_forum_open( $f ) );
		$this->assertTrue( bbp_is_forum_public( $f ) );
		$this->assertFalse( bbp_is_forum_closed( $f ) );
		$this->assertFalse( bbp_is_forum_hidden( $f ) );
		$this->assertFalse( bbp_is_forum_private( $f ) );
		$this->assertSame( $c, bbp_get_forum_parent_id( $f ) );
		$this->assertEquals( 'http://' . WP_TESTS_DOMAIN . '/?forum=' . $category->post_name . '/' . $forum->post_name, $forum->guid );

		// Category meta.
		$this->assertSame( 1, bbp_get_forum_subforum_count( $c, true ) );
		$this->assertSame( 0, bbp_get_forum_topic_count( $c, false, true ) );
		$this->assertSame( 1, bbp_get_forum_topic_count( $c, true, true ) );
		$this->assertSame( 0, bbp_get_forum_topic_count_hidden( $c, true ) );
		$this->assertSame( 0, bbp_get_forum_reply_count( $c, false, true ) );
		$this->assertSame( 1, bbp_get_forum_reply_count( $c, true, true ) );
		$this->assertSame( 0, bbp_get_forum_post_count( $c, false, true ) );
		$this->assertSame( 2, bbp_get_forum_post_count( $c, true, true ) );
		$this->assertSame( $t, bbp_get_forum_last_topic_id( $c ) );
		$this->assertSame( $r, bbp_get_forum_last_reply_id( $c ) );
		$this->assertSame( $r, bbp_get_forum_last_active_id( $c ) );
		$this->assertSame( '4 days, 4 hours ago', bbp_get_forum_last_active_time( $c ) );

		// Forum meta.
		$this->assertSame( 0, bbp_get_forum_subforum_count( $f, true ) );
		$this->assertSame( 1, bbp_get_forum_topic_count( $f, false, true ) );
		$this->assertSame( 1, bbp_get_forum_topic_count( $f, true, true ) );
		$this->assertSame( 0, bbp_get_forum_topic_count_hidden( $f, true ) );
		$this->assertSame( 1, bbp_get_forum_reply_count( $f, false, true ) );
		$this->assertSame( 1, bbp_get_forum_reply_count( $f, true, true ) );

		$this->assertSame( 2, bbp_get_forum_post_count( $f, false, true ) );
		$this->assertSame( 2, bbp_get_forum_post_count( $f, true, true ) );
		$this->assertSame( $t, bbp_get_forum_last_topic_id( $f ) );
		$this->assertSame( $r, bbp_get_forum_last_reply_id( $f ) );
		$this->assertSame( $r, bbp_get_forum_last_active_id( $f ) );
		$this->assertSame( '4 days, 4 hours ago', bbp_get_forum_last_active_time( $f ) );
	}

	/**
	 * @covers ::bbp_new_forum_handler
	 * @todo   Implement test_bbp_new_forum_handler().
	 */
	public function test_bbp_new_forum_handler() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_edit_forum_handler
	 * @todo   Implement test_bbp_edit_forum_handler().
	 */
	public function test_bbp_edit_forum_handler() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_save_forum_extras
	 * @todo   Implement test_bbp_save_forum_extras().
	 */
	public function test_bbp_save_forum_extras() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_remove_forum_from_all_subscriptions
	 * @todo   Implement test_bbp_remove_forum_from_all_subscriptions().
	 */
	public function test_bbp_remove_forum_from_all_subscriptions() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_update_forum
	 * @todo   Implement test_bbp_update_forum().
	 */
	public function test_bbp_update_forum() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_check_forum_edit
	 * @todo   Implement test_bbp_check_forum_edit().
	 */
	public function test_bbp_check_forum_edit() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}
	/**
	 * @covers ::bbp_delete_forum_topics
	 * @todo   Implement test_bbp_delete_forum_topics().
	 */
	public function test_bbp_delete_forum_topics() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_trash_forum_topics
	 * @todo   Implement test_bbp_trash_forum_topics().
	 */
	public function test_bbp_trash_forum_topics() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_untrash_forum_topics
	 * @todo   Implement test_bbp_untrash_forum_topics().
	 */
	public function test_bbp_untrash_forum_topics() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_delete_forum
	 * @todo   Implement test_bbp_delete_forum().
	 */
	public function test_bbp_delete_forum() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_trash_forum
	 * @todo   Implement test_bbp_trash_forum().
	 */
	public function test_bbp_trash_forum() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_untrash_forum
	 * @todo   Implement test_bbp_untrash_forum().
	 */
	public function test_bbp_untrash_forum() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_deleted_forum
	 * @todo   Implement test_bbp_deleted_forum().
	 */
	public function test_bbp_deleted_forum() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_trashed_forum
	 * @todo   Implement test_bbp_trashed_forum().
	 */
	public function test_bbp_trashed_forum() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_untrashed_forum
	 * @todo   Implement test_bbp_untrashed_forum().
	 */
	public function test_bbp_untrashed_forum() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}
}
