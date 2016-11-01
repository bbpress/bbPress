<?php

/**
 * Tests for the user component functions.
 *
 * @group users
 * @group functions
 */
 class BBP_Tests_Users_Functions extends BBP_UnitTestCase {

	/**
	 * @covers ::bbp_redirect_login
	 * @todo   Implement test_bbp_redirect_login().
	 */
	public function test_bbp_redirect_login() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_is_anonymous
	 * @todo   Implement test_bbp_is_anonymous().
	 */
	public function test_bbp_is_anonymous() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_current_anonymous_user_data
	 * @todo   Implement test_bbp_current_anonymous_user_data().
	 */
	public function test_bbp_current_anonymous_user_data() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_get_current_anonymous_user_data
	 * @todo   Implement test_bbp_get_current_anonymous_user_data().
	 */
	public function test_bbp_get_current_anonymous_user_data() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_set_current_anonymous_user_data
	 * @todo   Implement test_bbp_set_current_anonymous_user_data().
	 */
	public function test_bbp_set_current_anonymous_user_data() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_current_author_ip
	 * @todo   Implement test_bbp_current_author_ip().
	 */
	public function test_bbp_current_author_ip() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_current_author_ua
	 * @todo   Implement test_bbp_current_author_ua().
	 */
	public function test_bbp_current_author_ua() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_add_user_to_object
	 */
	public function test_bbp_add_user_to_object() {
		$u = $this->factory->user->create_many( 3 );
		$t = $this->factory->topic->create();

		// Add object terms.
		foreach ( $u as $k => $v ) {
			bbp_add_user_to_object( $t, $v, '_bbp_moderator' );
		}

		$r = get_metadata( 'post', $t, '_bbp_moderator', false );

		$this->assertCount( 3, $r );
	}

	/**
	 * @covers ::bbp_remove_user_from_object
	 */
	public function test_bbp_remove_user_from_object() {
		$u = $this->factory->user->create();
		$t = $this->factory->topic->create();

		// Add object terms.
		add_metadata( 'post', $t, '_bbp_moderator', $u, false );

		$r = get_metadata( 'post', $t, '_bbp_moderator', false );

		$this->assertCount( 1, $r );

		$r = bbp_remove_user_from_object( $t, $u, '_bbp_moderator' );

		$this->assertTrue( $r );

		$r = get_metadata( 'post', $t, '_bbp_moderator', false );

		$this->assertCount( 0, $r );
	}

	/**
	 * @covers ::bbp_is_object_of_user
	 */
	public function test_bbp_is_object_of_user() {
		$u = $this->factory->user->create();
		$t = $this->factory->topic->create();

		$r = bbp_is_object_of_user( $t, $u, '_bbp_moderator' );

		$this->assertFalse( $r );

		// Add user id.
		add_metadata( 'post', $t, '_bbp_moderator', $u, false );

		$r = bbp_is_object_of_user( $t, $u, '_bbp_moderator' );

		$this->assertTrue( $r );
	}

 	/**
	 * @covers ::bbp_edit_user_handler
	 * @todo   Implement test_bbp_edit_user_handler().
	 */
	public function test_bbp_edit_user_handler() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_user_email_change_handler
	 * @todo   Implement test_bbp_user_email_change_handler().
	 */
	public function test_bbp_user_email_change_handler() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_edit_user_email_send_notification
	 * @todo   Implement test_bbp_edit_user_email_send_notification().
	 */
	public function test_bbp_edit_user_email_send_notification() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_user_edit_after
	 * @todo   Implement test_bbp_user_edit_after().
	 */
	public function test_bbp_user_edit_after() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_check_user_edit
	 * @todo   Implement test_bbp_check_user_edit().
	 */
	public function test_bbp_check_user_edit() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_forum_enforce_blocked
	 * @todo   Implement test_bbp_forum_enforce_blocked().
	 */
	public function test_bbp_forum_enforce_blocked() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_sanitize_displayed_user_field
	 * @todo   Implement test_bbp_sanitize_displayed_user_field().
	 */
	public function test_bbp_sanitize_displayed_user_field() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_user_maybe_convert_pass
	 * @todo   Implement test_bbp_user_maybe_convert_pass().
	 */
	public function test_bbp_user_maybe_convert_pass() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}
}
