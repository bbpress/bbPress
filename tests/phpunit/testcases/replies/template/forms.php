<?php

/**
 * Tests for the `bbp_*_form_reply_*_()` functions.
 *
 * @group replies
 * @group template
 * @group forms
 */
class BBP_Tests_Replies_Template_Forms extends BBP_UnitTestCase {

	private function with_reply_edit( $reply_id, $callback ) {
		$old_post = isset( $GLOBALS['post'] ) ? $GLOBALS['post'] : null;
		$old_reply_id = bbpress()->current_reply_id;
		$GLOBALS['post'] = get_post( $reply_id );
		bbpress()->current_reply_id = $reply_id;
		setup_postdata( $GLOBALS['post'] );
		add_filter( 'bbp_is_reply_edit', '__return_true' );

		try {
			$callback();
		} finally {
			remove_filter( 'bbp_is_reply_edit', '__return_true' );
			bbpress()->current_reply_id = $old_reply_id;
			$GLOBALS['post'] = $old_post;
			wp_reset_postdata();
		}
	}

	/**
	 * @coversNothing
	 * @group bbp_xss
	 */
	public function test_reply_move_destination_title_is_escaped() {
		$forum_id = $this->factory->forum->create();
		$topic_id = $this->factory->topic->create(
			array(
				'post_parent' => $forum_id,
				'topic_meta'  => array(
					'forum_id' => $forum_id,
				),
			)
		);
		$reply_id = $this->factory->reply->create(
			array(
				'post_parent' => $topic_id,
				'post_title'  => '<code>" autofocus onfocus="alert(1)</code>',
				'reply_meta'  => array(
					'forum_id' => $forum_id,
					'topic_id' => $topic_id,
				),
			)
		);
		$user_id  = $this->factory->user->create( array( 'role' => 'administrator' ) );
		$old_user = get_current_user_id();
		$old_post = isset( $GLOBALS['post'] ) ? $GLOBALS['post'] : null;
		$old_reply_id = isset( $_GET['reply_id'] ) ? $_GET['reply_id'] : null;

		bbp_set_user_role( $user_id, bbp_get_keymaster_role() );
		$this->set_current_user( $user_id );
		bbpress()->current_topic_id = $topic_id;
		bbpress()->current_reply_id = $reply_id;
		add_filter( 'bbp_is_reply_edit', '__return_true' );
		$_GET['reply_id'] = $reply_id;
		$GLOBALS['post'] = get_post( $reply_id );
		setup_postdata( $GLOBALS['post'] );

		ob_start();
		require bbpress()->themes_dir . 'default/bbpress/form-reply-move.php';
		$output = ob_get_clean();

		$GLOBALS['post'] = $old_post;
		wp_reset_postdata();
		remove_filter( 'bbp_is_reply_edit', '__return_true' );
		if ( null === $old_reply_id ) {
			unset( $_GET['reply_id'] );
		} else {
			$_GET['reply_id'] = $old_reply_id;
		}
		bbpress()->current_topic_id = 0;
		bbpress()->current_reply_id = 0;
		$this->set_current_user( $old_user );

		$this->assertStringContainsString( 'value="Moved: &lt;code&gt;&quot; autofocus onfocus=&quot;alert(1)&lt;/code&gt;"', $output );
		$this->assertStringNotContainsString( 'value="Moved: <code>" autofocus', $output );
	}

	/**
	 * @covers ::bbp_form_reply_content
	 * @covers ::bbp_get_form_reply_content
	 * @todo   Implement test_bbp_get_form_reply_content().
	 */
	public function test_bbp_get_form_reply_content() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_current_user_can_access_create_reply_form
	 * @covers ::bbp_get_form_reply_content
	 */
	public function test_reply_edit_form_honors_new_ancestor_password() {
		$author_id    = $this->factory->user->create( array( 'role' => bbp_get_participant_role() ) );
		$moderator_id = $this->factory->user->create();
		$forum_id     = $this->factory->forum->create();
		$topic_id     = $this->factory->topic->create( array( 'post_parent' => $forum_id, 'topic_meta' => array( 'forum_id' => $forum_id ) ) );
		$reply_id     = $this->factory->reply->create(
			array( 'post_author' => $author_id, 'post_parent' => $topic_id, 'post_content' => 'Saved reply', 'reply_meta' => array( 'forum_id' => $forum_id, 'topic_id' => $topic_id ) )
		);
		$old_user     = get_current_user_id();
		$cookie       = 'wp-postpass_' . COOKIEHASH;
		$old_cookie   = isset( $_COOKIE[ $cookie ] ) ? $_COOKIE[ $cookie ] : null;
		$password     = wp_generate_password( 24, false );
		bbp_set_user_role( $moderator_id, bbp_get_moderator_role() );

		try {
			$this->with_reply_edit( $reply_id, function() use ( $author_id, $moderator_id, $forum_id, $password, $cookie ) {
				$this->set_current_user( $author_id );
				$this->assertTrue( bbp_current_user_can_access_create_reply_form() );
				$this->assertSame( 'Saved reply', bbp_get_form_reply_content() );

				wp_update_post( array( 'ID' => $forum_id, 'post_password' => $password ) );
				unset( $_COOKIE[ $cookie ] );
				$this->assertFalse( bbp_current_user_can_access_create_reply_form() );
				$this->assertSame( '', bbp_get_form_reply_content() );

				require_once ABSPATH . WPINC . '/class-phpass.php';
				$hasher = new PasswordHash( 8, true );
				$_COOKIE[ $cookie ] = $hasher->HashPassword( $password );
				$this->assertTrue( bbp_current_user_can_access_create_reply_form() );
				$this->assertSame( 'Saved reply', bbp_get_form_reply_content() );

				unset( $_COOKIE[ $cookie ] );
				$this->set_current_user( $moderator_id );
				$this->assertTrue( bbp_current_user_can_access_create_reply_form() );
				$this->assertSame( 'Saved reply', bbp_get_form_reply_content() );
			} );
		} finally {
			$this->set_current_user( $old_user );
			if ( null === $old_cookie ) {
				unset( $_COOKIE[ $cookie ] );
			} else {
				$_COOKIE[ $cookie ] = $old_cookie;
			}
		}
	}

	/**
	 * @covers ::bbp_form_reply_to
	 * @covers ::bbp_get_form_reply_to
	 * @todo   Implement test_bbp_get_form_reply_to().
	 */
	public function test_bbp_get_form_reply_to() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_reply_to_dropdown
	 * @covers ::bbp_get_reply_to_dropdown
	 * @todo   Implement test_bbp_get_reply_to_dropdown().
	 */
	public function test_bbp_get_reply_to_dropdown() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @ticket BBP3724
	 * @covers ::bbp_get_reply_to_dropdown
	 */
	public function test_reply_to_dropdown_previews_destination_topic() {
		$forum_id             = $this->factory->forum->create();
		$source_topic_id      = $this->factory->topic->create( array( 'post_parent' => $forum_id ) );
		$destination_topic_id = $this->factory->topic->create( array( 'post_parent' => $forum_id, 'post_title' => 'Destination Topic' ) );
		$source_reply_id      = $this->factory->reply->create( array( 'post_parent' => $source_topic_id ) );
		$destination_reply_id = $this->factory->reply->create( array( 'post_parent' => $destination_topic_id ) );
		$reply_id             = $this->factory->reply->create( array( 'post_parent' => $source_topic_id ) );

		bbp_update_reply_to( $reply_id, $source_reply_id );

		$html = bbp_get_reply_to_dropdown( $reply_id, $destination_topic_id );

		$this->assertStringContainsString( 'value="" class="level-0">' . $destination_topic_id . ' - Destination Topic', $html );
		$this->assertStringContainsString( 'value="' . $destination_reply_id . '"', $html );
		$this->assertStringNotContainsString( 'value="' . $source_reply_id . '"', $html );
	}

	/**
	 * @covers ::bbp_form_reply_log_edit
	 * @covers ::bbp_get_form_reply_log_edit
	 * @todo   Implement test_bbp_get_form_reply_log_edit().
	 */
	public function test_bbp_get_form_reply_log_edit() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_form_reply_edit_reason
	 * @covers ::bbp_get_form_reply_edit_reason
	 * @todo   Implement test_bbp_get_form_reply_edit_reason().
	 */
	public function test_bbp_get_form_reply_edit_reason() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_form_reply_status_dropdown
	 * @covers ::bbp_get_form_reply_status_dropdown
	 * @todo   Implement test_bbp_get_form_reply_status_dropdown().
	 */
	public function test_bbp_get_form_reply_status_dropdown() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_is_reply_form_post_request
	 * @todo   Implement test_bbp_is_reply_form_post_request().
	 */
	public function test_bbp_is_reply_form_post_request() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}
}
