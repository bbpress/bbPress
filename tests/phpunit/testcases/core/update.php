<?php
/**
 * Tests for the core update functions.
 *
 * @group core
 * @group update
 */
class BBP_Tests_Core_Update extends BBP_UnitTestCase {

	/**
	 * @covers ::bbp_is_install
	 * @todo   Implement test_bbp_is_install().
	 */
	public function test_bbp_is_install() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_is_update
	 * @todo   Implement test_bbp_is_update().
	 */
	public function test_bbp_is_update() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_is_activation
	 * @todo   Implement test_bbp_is_activation().
	 */
	public function test_bbp_is_activation() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_is_deactivation
	 * @todo   Implement test_bbp_is_deactivation().
	 */
	public function test_bbp_is_deactivation() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_version_bump
	 * @todo   Implement test_bbp_version_bump().
	 */
	public function test_bbp_version_bump() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_setup_updater
	 * @todo   Implement test_bbp_setup_updater().
	 */
	public function test_bbp_setup_updater() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @group canonical
	 * @covers ::bbp_create_initial_content
	 */
	public function test_bbp_create_initial_content() {

		$category_id = $this->factory->forum->create( array(
			'forum_meta' => array(
				'_bbp_forum_type' => 'category',
				'_bbp_status'     => 'open',
			),
		) );

		bbp_create_initial_content( array( 'forum_parent' => $category_id ) );

		$forum_id = bbp_forum_query_subforum_ids( $category_id );
		$forum_id = (int) $forum_id[0];
		$topic_id = bbp_get_forum_last_topic_id( $forum_id );
		$reply_id = bbp_get_forum_last_reply_id( $forum_id );

		// Forum post
		$this->assertSame( 'General', bbp_get_forum_title( $forum_id ) );
		$this->assertSame( 'General chit-chat', bbp_get_forum_content( $forum_id ) );
		$this->assertSame( 'open', bbp_get_forum_status( $forum_id ) );
		$this->assertTrue( bbp_is_forum_public( $forum_id ) );
		$this->assertSame( $category_id, bbp_get_forum_parent_id( $forum_id ) );

		// Topic post
		$this->assertSame( $forum_id, bbp_get_topic_forum_id( $topic_id ) );
		$this->assertSame( 'Hello World!', bbp_get_topic_title( $topic_id ) );
		remove_all_filters( 'bbp_get_topic_content' );
		$topic_content = "I am the first topic in your new forums.";
		$this->assertSame( $topic_content, bbp_get_topic_content( $topic_id ) );
		$this->assertSame( 'publish', bbp_get_topic_status( $topic_id ) );
		$this->assertTrue( bbp_is_topic_published( $topic_id ) );

		// Reply post
		$this->assertSame( $forum_id, bbp_get_reply_forum_id( $reply_id ) );
		$this->assertSame( 'Reply To: Hello World!', bbp_get_reply_title( $reply_id ) );
		$this->assertSame( $reply_id, bbp_get_reply_title_fallback( $reply_id ) );
		remove_all_filters( 'bbp_get_reply_content' );
		$reply_content = "Oh, and this is what a reply looks like.";
		$this->assertSame( $reply_content, bbp_get_reply_content( $reply_id ) );
		$this->assertSame( 'publish', bbp_get_reply_status( $reply_id ) );
		$this->assertTrue( bbp_is_reply_published( $reply_id ) );

		// Category meta
		$this->assertSame( 1, bbp_get_forum_subforum_count( $category_id, true ) );
		$this->assertSame( 0, bbp_get_forum_topic_count( $category_id, false, true ) );
		$this->assertSame( 0, bbp_get_forum_topic_count_hidden( $category_id, true ) );
		$this->assertSame( 0, bbp_get_forum_reply_count( $category_id, false, true ) );
		$this->assertSame( 1, bbp_get_forum_topic_count( $category_id, true, true ) );
		$this->assertSame( 1, bbp_get_forum_reply_count( $category_id, true, true ) );
		$this->assertSame( 0, bbp_get_forum_post_count( $category_id, false, true ) );
		$this->assertSame( 2, bbp_get_forum_post_count( $category_id, true, true ) );
		$this->assertSame( $topic_id, bbp_get_forum_last_topic_id( $category_id ) );
		$this->assertSame( 'Hello World!', bbp_get_forum_last_topic_title( $category_id ) );
		$this->assertSame( $reply_id, bbp_get_forum_last_reply_id( $category_id ) );
		$this->assertSame( 'Reply To: Hello World!', bbp_get_forum_last_reply_title( $category_id ) );
		$this->assertSame( $reply_id, bbp_get_forum_last_active_id( $category_id ) );
		$this->assertSame( '1 day, 16 hours ago', bbp_get_forum_last_active_time( $category_id ) );

		// Forum meta
		$this->assertSame( 0, bbp_get_forum_subforum_count( $forum_id, true ) );
		$this->assertSame( 1, bbp_get_forum_topic_count( $forum_id, false, true ) );
		$this->assertSame( 0, bbp_get_forum_topic_count_hidden( $forum_id, true ) );
		$this->assertSame( 1, bbp_get_forum_reply_count( $forum_id, false, true ) );
		$this->assertSame( 1, bbp_get_forum_topic_count( $forum_id, true, true ) );
		$this->assertSame( 1, bbp_get_forum_reply_count( $forum_id, true, true ) );
		$this->assertSame( 2, bbp_get_forum_post_count( $forum_id, false, true ) );
		$this->assertSame( 2, bbp_get_forum_post_count( $forum_id, true, true ) );
		$this->assertSame( $topic_id, bbp_get_forum_last_topic_id( $forum_id ) );
		$this->assertSame( 'Hello World!', bbp_get_forum_last_topic_title( $forum_id ) );
		$this->assertSame( $reply_id, bbp_get_forum_last_reply_id( $forum_id ) );
		$this->assertSame( 'Reply To: Hello World!', bbp_get_forum_last_reply_title( $forum_id ) );
		$this->assertSame( $reply_id, bbp_get_forum_last_active_id( $forum_id ) );
		$this->assertSame( '1 day, 16 hours ago', bbp_get_forum_last_active_time( $forum_id ) );

		// Topic meta
		$this->assertSame( '127.0.0.1', bbp_current_author_ip( $topic_id ) );
		$this->assertSame( $forum_id, bbp_get_topic_forum_id( $topic_id ) );
		$this->assertSame( 1, bbp_get_topic_voice_count( $topic_id, true ) );
		$this->assertSame( 1, bbp_get_topic_reply_count( $topic_id, true ) );
		$this->assertSame( 0, bbp_get_topic_reply_count_hidden( $topic_id, true ) );
		$this->assertSame( $reply_id, bbp_get_topic_last_reply_id( $topic_id ) );
		$this->assertSame( $reply_id, bbp_get_topic_last_active_id( $topic_id ) );
		$this->assertSame( '1 day, 16 hours ago', bbp_get_topic_last_active_time( $topic_id ) );

		// Reply Meta
		$this->assertSame( '127.0.0.1', bbp_current_author_ip( $reply_id ) );
		$this->assertSame( $forum_id, bbp_get_reply_forum_id( $reply_id ) );
		$this->assertSame( $topic_id, bbp_get_reply_topic_id( $reply_id ) );
	}

	/**
	 * @covers ::bbp_version_updater
	 * @todo   Implement test_bbp_version_updater().
	 */
	public function test_bbp_version_updater() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_add_activation_redirect
	 * @todo   Implement test_bbp_add_activation_redirect().
	 */
	public function test_bbp_add_activation_redirect() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_make_current_user_keymaster
	 * @todo   Implement test_bbp_make_current_user_keymaster().
	 */
	public function test_bbp_make_current_user_keymaster() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}
}
