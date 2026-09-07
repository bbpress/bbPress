<?php

/**
 * Tests for the `bbp_*_reply_*()` template functions.
 *
 * @group replies
 * @group template
 * @group links
 */
class BBP_Tests_Replies_Template_Links extends BBP_UnitTestCase {

	/**
	 * @covers ::bbp_reply_to_link
	 * @covers ::bbp_get_reply_to_link
	 * @todo   Implement test_bbp_get_reply_to_link().
	 */
	public function test_bbp_get_reply_to_link() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_cancel_reply_to_link
	 * @covers ::bbp_get_cancel_reply_to_link
	 * @todo   Implement test_bbp_get_cancel_reply_to_link().
	 */
	public function test_bbp_get_cancel_reply_to_link() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_reply_admin_links
	 * @covers ::bbp_get_reply_admin_links
	 */
	public function test_bbp_get_reply_admin_links() {
		$old_user_id = get_current_user_id();
		$user_id     = $this->factory->user->create( array( 'role' => 'administrator' ) );
		$forum_id    = $this->factory->forum->create();
		$topic_id    = $this->factory->topic->create( array( 'post_parent' => $forum_id ) );
		$reply_id    = $this->factory->reply->create( array( 'post_parent' => $topic_id ) );

		bbp_set_user_role( $user_id, bbp_get_keymaster_role() );
		$this->set_current_user( $user_id );
		wp_trash_post( $reply_id );

		$links = bbp_get_reply_admin_links( array( 'id' => $reply_id ) );

		$this->set_current_user( $old_user_id );
		$this->assertStringContainsString( 'bbp-reply-spam-link', $links );
	}

	/**
	 * @covers ::bbp_reply_edit_link
	 * @covers ::bbp_get_reply_edit_link
	 * @todo   Implement test_bbp_get_reply_edit_link().
	 */
	public function test_bbp_get_reply_edit_link() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_reply_edit_url
	 * @covers ::bbp_get_reply_edit_url
	 * @todo   Implement test_bbp_get_reply_edit_url().
	 */
	public function test_bbp_get_reply_edit_url() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_reply_trash_link
	 * @covers ::bbp_get_reply_trash_link
	 * @todo   Implement test_bbp_get_reply_trash_link().
	 */
	public function test_bbp_get_reply_trash_link() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_reply_spam_link
	 * @covers ::bbp_get_reply_spam_link
	 * @todo   Implement test_bbp_get_reply_spam_link().
	 */
	public function test_bbp_get_reply_spam_link() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_reply_move_link
	 * @covers ::bbp_get_reply_move_link
	 * @todo   Implement test_bbp_get_reply_move_link().
	 */
	public function test_bbp_get_reply_move_link() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_topic_split_link
	 * @covers ::bbp_get_topic_split_link
	 * @todo   Implement test_bbp_get_topic_split_link().
	 */
	public function test_bbp_get_topic_split_link() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_reply_approve_link
	 * @covers ::bbp_get_reply_approve_link
	 * @todo   Implement test_bbp_get_reply_approve_link().
	 */
	public function test_bbp_get_reply_approve_link() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_topic_pagination_links
	 * @covers ::bbp_get_topic_pagination_links
	 * @todo   Implement test_bbp_get_topic_pagination_links().
	 */
	public function test_bbp_get_topic_pagination_links() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}
}
