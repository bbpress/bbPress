<?php

/**
 * Tests for the `bbp_*_form_topic_*_()` functions.
 *
 * @group topics
 * @group template
 * @group forms
 */
class BBP_Tests_Topics_Template_Forms extends BBP_UnitTestCase {

	/**
	 * Topic tag name used by the topic-tag form test.
	 *
	 * @var string
	 */
	protected $topic_tag_name = '';

	private function with_topic_edit( $topic_id, $callback ) {
		$old_post = isset( $GLOBALS['post'] ) ? $GLOBALS['post'] : null;
		$old_topic_id = bbpress()->current_topic_id;
		$GLOBALS['post'] = get_post( $topic_id );
		bbpress()->current_topic_id = $topic_id;
		setup_postdata( $GLOBALS['post'] );
		add_filter( 'bbp_is_topic_edit', '__return_true' );

		try {
			$callback();
		} finally {
			remove_filter( 'bbp_is_topic_edit', '__return_true' );
			bbpress()->current_topic_id = $old_topic_id;
			$GLOBALS['post'] = $old_post;
			wp_reset_postdata();
		}
	}

	private function with_topic_post( $action, $values, $callback ) {
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
	 * Filters the topic tag name used by the topic-tag form test.
	 *
	 * @return string
	 */
	public function filter_topic_tag_name() {
		return $this->topic_tag_name;
	}

	/**
	 * @coversNothing
	 * @group bbp_xss
	 */
	public function test_topic_tag_name_is_not_included_in_javascript_confirmations() {
		$user_id  = $this->factory->user->create( array( 'role' => 'administrator' ) );
		$old_user = get_current_user_id();

		bbp_set_user_role( $user_id, bbp_get_keymaster_role() );
		$this->set_current_user( $user_id );
		$this->topic_tag_name = 'qa&#092;&#039;+alert(document.domain))//';
		add_filter( 'bbp_get_topic_tag_name', array( $this, 'filter_topic_tag_name' ) );

		ob_start();
		require bbpress()->themes_dir . 'default/bbpress/form-topic-tag.php';
		$output = ob_get_clean();

		remove_filter( 'bbp_get_topic_tag_name', array( $this, 'filter_topic_tag_name' ) );
		$this->set_current_user( $old_user );

		preg_match_all( '/onclick="([^"]+)"/', $output, $matches );

		$this->assertCount( 2, $matches[1] );
		$this->assertStringNotContainsString( $this->topic_tag_name, implode( '', $matches[1] ) );
		$this->assertStringNotContainsString( 'alert(document.domain)', implode( '', $matches[1] ) );
		$this->assertStringContainsString( 'merge this tag', $matches[1][0] );
		$this->assertStringContainsString( 'delete this tag', $matches[1][1] );
	}

	/**
	 * @coversNothing
	 * @group bbp_xss
	 */
	public function test_topic_split_destination_title_is_escaped() {
		$forum_id = $this->factory->forum->create();
		$topic_id = $this->factory->topic->create(
			array(
				'post_parent' => $forum_id,
				'post_title'  => '<code>" autofocus onfocus="alert(1)</code>',
				'topic_meta'  => array(
					'forum_id' => $forum_id,
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
		add_filter( 'bbp_is_topic_edit', '__return_true' );
		$_GET['reply_id'] = 0;
		$GLOBALS['post'] = get_post( $topic_id );
		setup_postdata( $GLOBALS['post'] );

		ob_start();
		require bbpress()->themes_dir . 'default/bbpress/form-topic-split.php';
		$output = ob_get_clean();

		$GLOBALS['post'] = $old_post;
		wp_reset_postdata();
		remove_filter( 'bbp_is_topic_edit', '__return_true' );
		if ( null === $old_reply_id ) {
			unset( $_GET['reply_id'] );
		} else {
			$_GET['reply_id'] = $old_reply_id;
		}
		bbpress()->current_topic_id = 0;
		$this->set_current_user( $old_user );

		$this->assertStringContainsString( 'value="Split: &lt;code&gt;&quot; autofocus onfocus=&quot;alert(1)&lt;/code&gt;"', $output );
		$this->assertStringNotContainsString( 'value="Split: <code>" autofocus', $output );
	}

	/**
	 * @covers ::bbp_form_topic_type_dropdown
	 * @covers ::bbp_get_form_topic_type_dropdown
	 */
	public function test_bbp_get_form_topic_type_dropdown() {
		$args = array( 'select_id' => 'custom_topic_type', 'selected' => 'stick' );
		$html = bbp_get_form_topic_type_dropdown( $args );
		$this->assertStringContainsString( 'name="custom_topic_type"', $html );
		$this->assertMatchesRegularExpression( '/<option value="stick" selected=/', $html );
		$this->assertStringContainsString( '<option value="unstick"', $html );
		$this->expectOutputString( $html );
		bbp_form_topic_type_dropdown( $args );
	}

	/**
	 * @covers ::bbp_form_topic_status_dropdown
	 * @covers ::bbp_get_form_topic_status_dropdown
	 */
	public function test_bbp_get_form_topic_status_dropdown() {
		$args = array( 'select_id' => 'custom_topic_status', 'selected' => bbp_get_closed_status_id() );
		$html = bbp_get_form_topic_status_dropdown( $args );
		$this->assertStringContainsString( 'name="custom_topic_status"', $html );
		$this->assertMatchesRegularExpression( '/<option value="closed" selected=/', $html );
		$this->assertStringContainsString( '<option value="publish"', $html );
		$this->expectOutputString( $html );
		bbp_form_topic_status_dropdown( $args );
	}

	/**
	 * @covers ::bbp_form_topic_title
	 * @covers ::bbp_get_form_topic_title
	 */
	public function test_bbp_get_form_topic_title() {
		$this->assertSame( '', bbp_get_form_topic_title() );
		$topic_id = $this->factory->topic->create( array( 'post_title' => 'Saved title' ) );
		$this->with_topic_edit( $topic_id, function() {
			$this->assertSame( 'Saved title', bbp_get_form_topic_title() );
		} );
		$this->with_topic_post( 'bbp-new-topic', array( 'bbp_topic_title' => wp_slash( 'Posted "title"' ) ), function() {
			$this->assertSame( 'Posted &quot;title&quot;', bbp_get_form_topic_title() );
			$this->expectOutputString( 'Posted &quot;title&quot;' );
			bbp_form_topic_title();
		} );
	}

	/**
	 * @covers ::bbp_form_topic_content
	 * @covers ::bbp_get_form_topic_content
	 */
	public function test_bbp_get_form_topic_content() {
		$this->assertSame( '', bbp_get_form_topic_content() );
		$topic_id = $this->factory->topic->create( array( 'post_content' => 'Saved content' ) );
		$this->with_topic_edit( $topic_id, function() {
			$this->assertSame( 'Saved content', bbp_get_form_topic_content() );
		} );
		$this->with_topic_post( 'bbp-new-topic', array( 'bbp_topic_content' => wp_slash( 'Posted "content"' ) ), function() {
			$this->assertSame( 'Posted &quot;content&quot;', bbp_get_form_topic_content() );
			$this->expectOutputString( 'Posted &quot;content&quot;' );
			bbp_form_topic_content();
		} );
	}

	/**
	 * @covers ::bbp_form_topic_tags
	 * @covers ::bbp_get_form_topic_tags
	 */
	public function test_bbp_get_form_topic_tags() {
		$this->assertSame( '', bbp_get_form_topic_tags() );
		$this->with_topic_post( 'bbp-new-topic', array( 'bbp_topic_tags' => wp_slash( 'alpha, beta' ) ), function() {
			$this->assertSame( 'alpha, beta', bbp_get_form_topic_tags() );
			$this->expectOutputString( 'alpha, beta' );
			bbp_form_topic_tags();
		} );
	}

	/**
	 * @covers ::bbp_form_topic_forum
	 * @covers ::bbp_get_form_topic_forum
	 */
	public function test_bbp_get_form_topic_forum() {
		$this->assertSame( 0, bbp_get_form_topic_forum() );
		$forum_id = $this->factory->forum->create();
		$this->with_topic_post( 'bbp-new-topic', array( 'bbp_forum_id' => (string) $forum_id ), function() use ( $forum_id ) {
			$this->assertSame( $forum_id, bbp_get_form_topic_forum() );
			$this->expectOutputString( (string) $forum_id );
			bbp_form_topic_forum();
		} );
	}

	/**
	 * @covers ::bbp_form_topic_subscribed
	 * @covers ::bbp_get_form_topic_subscribed
	 */
	public function test_bbp_get_form_topic_subscribed() {
		$this->assertSame( '', bbp_get_form_topic_subscribed() );
		$this->with_topic_post( 'bbp-new-topic', array( 'bbp_topic_subscription' => '1' ), function() {
			$this->assertSame( ' checked=\'checked\'', bbp_get_form_topic_subscribed() );
			$this->expectOutputString( bbp_get_form_topic_subscribed() );
			bbp_form_topic_subscribed();
		} );
	}

	/**
	 * @covers ::bbp_form_topic_log_edit
	 * @covers ::bbp_get_form_topic_log_edit
	 */
	public function test_bbp_get_form_topic_log_edit() {
		$this->assertSame( ' checked=\'checked\'', bbp_get_form_topic_log_edit() );
		$this->with_topic_post( 'bbp-new-topic', array( 'bbp_log_topic_edit' => '0' ), function() {
			$this->assertSame( '', bbp_get_form_topic_log_edit() );
			$this->expectOutputString( '' );
			bbp_form_topic_log_edit();
		} );
	}

	/**
	 * @covers ::bbp_form_topic_edit_reason
	 * @covers ::bbp_get_form_topic_edit_reason
	 */
	public function test_bbp_get_form_topic_edit_reason() {
		$this->assertSame( '', bbp_get_form_topic_edit_reason() );
		$this->with_topic_post( 'bbp-new-topic', array( 'bbp_topic_edit_reason' => wp_slash( 'Fixed "details"' ) ), function() {
			$this->assertSame( 'Fixed &quot;details&quot;', bbp_get_form_topic_edit_reason() );
			$this->expectOutputString( 'Fixed &quot;details&quot;' );
			bbp_form_topic_edit_reason();
		} );
	}

	/**
	 * @covers ::bbp_is_topic_form_post_request
	 */
	public function test_bbp_is_topic_form_post_request() {
		$this->assertFalse( bbp_is_topic_form_post_request() );
		$this->with_topic_post( 'bbp-new-topic', array(), function() {
			$this->assertTrue( bbp_is_topic_form_post_request() );
			$nonce = $_REQUEST['_wpnonce'];
			$_REQUEST['_wpnonce'] = 'invalid';
			$this->assertFalse( bbp_is_topic_form_post_request() );
			$_REQUEST['_wpnonce'] = $nonce;
			$_SERVER['REQUEST_METHOD'] = 'GET';
			$this->assertFalse( bbp_is_topic_form_post_request() );
		} );
		$topic_id = $this->factory->topic->create();
		$this->with_topic_edit( $topic_id, function() use ( $topic_id ) {
			$this->with_topic_post( 'bbp-edit-topic_' . $topic_id, array(), function() {
				$this->assertTrue( bbp_is_topic_form_post_request() );
			} );
		} );
	}
}
