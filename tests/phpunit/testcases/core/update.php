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
	 * @covers ::bbp_create_initial_content
	 */
	public function test_bbp_create_initial_content() {
		bbp_create_initial_content();

		$forum_id = 3;
		$topic_id = 4;
		$reply_id = 5;

		$this->assertSame( 'General', bbp_get_forum_title( $forum_id ) );
		$this->assertSame( 'General chit-chat', bbp_get_forum_content( $forum_id ) );
		$this->assertSame( 'open', bbp_get_forum_status( $forum_id ) );
		$this->assertTrue( bbp_is_forum_public( $forum_id ) );

		$this->assertSame( $forum_id, bbp_get_topic_forum_id( $topic_id ) );
		$this->assertSame( 'Hello World!', bbp_get_topic_title( $topic_id ) );
		remove_all_filters( 'bbp_get_topic_content' );
		$topic_content = "I am the first topic in your new forums.";
		$this->assertSame( $topic_content, bbp_get_topic_content( $topic_id ) );
		$this->assertSame( 'publish', bbp_get_topic_status( $topic_id ) );
		$this->assertTrue( bbp_is_topic_published( $topic_id ) );

		$this->assertSame( $forum_id, bbp_get_reply_forum_id( $reply_id ) );
		$this->assertSame( 'Reply To: Hello World!', bbp_get_reply_title( $reply_id ) );
		$this->assertSame( $reply_id, bbp_get_reply_title_fallback( $reply_id ) );
		remove_all_filters( 'bbp_get_reply_content' );
		$reply_content = "Oh, and this is what a reply looks like.";
		$this->assertSame( $reply_content, bbp_get_reply_content( $reply_id ) );
		$this->assertSame( 'publish', bbp_get_reply_status( $reply_id ) );
		$this->assertTrue( bbp_is_reply_published( $reply_id ) );
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
