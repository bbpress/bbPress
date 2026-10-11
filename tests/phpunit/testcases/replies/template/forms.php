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

	private function with_reply_post( $action, $values, $callback ) {
		$old_method = $_SERVER['REQUEST_METHOD'];
		$old_post = $_POST;
		$old_request = $_REQUEST;
		$post_action = preg_replace( '/_[0-9]+$/', '', $action );
		$_SERVER['REQUEST_METHOD'] = 'POST';
		$_POST = array_merge( $values, array( 'action' => $post_action ) );
		$_REQUEST = array_merge( $old_request, $values, array( 'action' => $post_action, '_wpnonce' => wp_create_nonce( $action ) ) );

		try {
			$callback();
		} finally {
			$_SERVER['REQUEST_METHOD'] = $old_method;
			$_POST = $old_post;
			$_REQUEST = $old_request;
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
	 */
	public function test_bbp_get_form_reply_content() {
		$this->assertSame( '', bbp_get_form_reply_content() );
		$topic_id = $this->factory->topic->create();
		$reply_id = $this->factory->reply->create( array( 'post_parent' => $topic_id, 'post_content' => 'Saved reply' ) );
		$this->with_reply_edit( $reply_id, function() {
			$this->assertSame( 'Saved reply', bbp_get_form_reply_content() );
		} );
		$this->with_reply_post( 'bbp-new-reply', array( 'bbp_reply_content' => wp_slash( 'Posted "reply"' ) ), function() {
			$this->assertSame( 'Posted &quot;reply&quot;', bbp_get_form_reply_content() );
			$this->expectOutputString( 'Posted &quot;reply&quot;' );
			bbp_form_reply_content();
		} );
	}

	/**
	 * @covers ::bbp_form_reply_to
	 * @covers ::bbp_get_form_reply_to
	 */
	public function test_bbp_get_form_reply_to() {
		$this->assertSame( 0, bbp_get_form_reply_to() );
		$forum_id = $this->factory->forum->create();
		$topic_id = $this->factory->topic->create( array( 'post_parent' => $forum_id ) );
		$reply_id = $this->factory->reply->create( array( 'post_parent' => $topic_id ) );
		$old_request = $_REQUEST;
		try {
			$_REQUEST['bbp_reply_to'] = (string) $reply_id;
			$this->assertSame( $reply_id, bbp_get_form_reply_to() );
			$this->expectOutputString( (string) $reply_id );
			bbp_form_reply_to();
		} finally {
			$_REQUEST = $old_request;
		}
	}

	/**
	 * @covers ::bbp_reply_to_dropdown
	 * @covers ::bbp_get_reply_to_dropdown
	 */
	public function test_bbp_get_reply_to_dropdown() {
		$forum_id = $this->factory->forum->create();
		$topic_id = $this->factory->topic->create( array( 'post_parent' => $forum_id ) );
		$reply_id = $this->factory->reply->create( array( 'post_parent' => $topic_id ) );
		$child_id = $this->factory->reply->create( array( 'post_parent' => $topic_id ) );
		bbp_update_reply_to( $child_id, $reply_id );
		$html = bbp_get_reply_to_dropdown( $child_id );
		$this->assertStringContainsString( 'name="bbp_reply_to"', $html );
		$this->assertStringContainsString( 'value="' . $reply_id . '"', $html );
		$this->assertMatchesRegularExpression( '/value="' . $child_id . '"[^>]*disabled=/', $html );
		$this->expectOutputString( $html );
		bbp_reply_to_dropdown( $child_id );
	}

	/**
	 * @covers ::bbp_form_reply_log_edit
	 * @covers ::bbp_get_form_reply_log_edit
	 */
	public function test_bbp_get_form_reply_log_edit() {
		$this->assertSame( ' checked=\'checked\'', bbp_get_form_reply_log_edit() );
		$this->with_reply_post( 'bbp-new-reply', array( 'bbp_log_reply_edit' => '0' ), function() {
			$this->assertSame( '', bbp_get_form_reply_log_edit() );
			$this->expectOutputString( '' );
			bbp_form_reply_log_edit();
		} );
	}

	/**
	 * @covers ::bbp_form_reply_edit_reason
	 * @covers ::bbp_get_form_reply_edit_reason
	 */
	public function test_bbp_get_form_reply_edit_reason() {
		$this->assertSame( '', bbp_get_form_reply_edit_reason() );
		$this->with_reply_post( 'bbp-new-reply', array( 'bbp_reply_edit_reason' => wp_slash( 'Fixed "details"' ) ), function() {
			$this->assertSame( 'Fixed &quot;details&quot;', bbp_get_form_reply_edit_reason() );
			$this->expectOutputString( 'Fixed &quot;details&quot;' );
			bbp_form_reply_edit_reason();
		} );
	}

	/**
	 * @covers ::bbp_form_reply_status_dropdown
	 * @covers ::bbp_get_form_reply_status_dropdown
	 */
	public function test_bbp_get_form_reply_status_dropdown() {
		$args = array( 'select_id' => 'custom_reply_status', 'selected' => bbp_get_pending_status_id() );
		$html = bbp_get_form_reply_status_dropdown( $args );
		$this->assertStringContainsString( 'name="custom_reply_status"', $html );
		$this->assertMatchesRegularExpression( '/<option value="pending" selected=/', $html );
		$this->assertStringContainsString( '<option value="publish"', $html );
		$this->expectOutputString( $html );
		bbp_form_reply_status_dropdown( $args );
	}

	/**
	 * @covers ::bbp_is_reply_form_post_request
	 */
	public function test_bbp_is_reply_form_post_request() {
		$this->assertFalse( bbp_is_reply_form_post_request() );
		$this->with_reply_post( 'bbp-new-reply', array(), function() {
			$this->assertTrue( bbp_is_reply_form_post_request() );
			$nonce = $_REQUEST['_wpnonce'];
			$_REQUEST['_wpnonce'] = 'invalid';
			$this->assertFalse( bbp_is_reply_form_post_request() );
			$_REQUEST['_wpnonce'] = $nonce;
			$_SERVER['REQUEST_METHOD'] = 'GET';
			$this->assertFalse( bbp_is_reply_form_post_request() );
		} );
		$forum_id = $this->factory->forum->create();
		$topic_id = $this->factory->topic->create(
			array(
				'post_parent' => $forum_id,
				'topic_meta'  => array( 'forum_id' => $forum_id ),
			)
		);
		$reply_id = $this->factory->reply->create(
			array(
				'post_parent' => $topic_id,
				'reply_meta'  => array( 'forum_id' => $forum_id, 'topic_id' => $topic_id ),
			)
		);
		$this->with_reply_edit( $reply_id, function() use ( $reply_id ) {
			$this->with_reply_post( 'bbp-edit-reply_' . $reply_id, array(), function() {
				$this->assertTrue( bbp_is_reply_form_post_request() );
			} );
		} );
	}
}
