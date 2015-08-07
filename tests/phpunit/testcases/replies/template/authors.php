<?php

/**
 * Tests for the `bbp_*_form_reply_author_*()` template functions.
 *
 * @group replies
 * @group template
 * @group authors
 */
class BBP_Tests_Replies_Template_Authors extends BBP_UnitTestCase {

	/**
	 * @covers ::bbp_reply_author
	 * @covers ::bbp_get_reply_author
	 * @todo   Implement test_bbp_get_reply_author().
	 */
	public function test_bbp_get_reply_author() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_reply_author_id
	 * @covers ::bbp_get_reply_author_id
	 */
	public function test_bbp_get_reply_author_id() {
		$u = $this->factory->user->create();
		$t = $this->factory->topic->create();
		$r = $this->factory->reply->create( array(
			'post_parent' => $t,
			'post_author' => $u,
			'reply_meta' => array(
				'topic_id' => $t,
			),
		) );

		$reply = bbp_get_reply_author_id( $r );
		$this->assertSame( $u, $reply );
	}

	/**
	 * @covers ::bbp_reply_author_display_name
	 * @covers ::bbp_get_reply_author_display_name
	 */
	public function test_bbp_get_reply_author_display_name() {
		$u = $this->factory->user->create( array(
			'display_name' => 'Barry B. Benson',
		) );
		$t = $this->factory->topic->create();
		$r = $this->factory->reply->create( array(
			'post_parent' => $t,
			'post_author' => $u,
			'reply_meta' => array(
				'topic_id' => $t,
			),
		) );

		$reply = bbp_get_reply_author_display_name( $r );
		$this->assertSame( 'Barry B. Benson', $reply );
	}

	/**
	 * @covers ::bbp_reply_author_avatar
	 * @covers ::bbp_get_reply_author_avatar
	 * @todo   Implement test_bbp_get_reply_author_avatar().
	 */
	public function test_bbp_get_reply_author_avatar() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_reply_author_link
	 * @covers ::bbp_get_reply_author_link
	 * @todo   Implement test_bbp_get_reply_author_link().
	 */
	public function test_bbp_get_reply_author_link() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_reply_author_url
	 * @covers ::bbp_get_reply_author_url
	 * @todo   Implement test_bbp_get_reply_author_url().
	 */
	public function test_bbp_get_reply_author_url() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_reply_author_email
	 * @covers ::bbp_get_reply_author_email
	 * @todo   Implement test_bbp_get_reply_author_email().
	 */
	public function test_bbp_get_reply_author_email() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_reply_author_role
	 * @covers ::bbp_get_reply_author_role
	 * @todo   Implement test_bbp_get_reply_author_role().
	 */
	public function test_bbp_get_reply_author_role() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}
}
