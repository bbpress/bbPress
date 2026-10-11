<?php

/**
 * Tests for the `bbp_*_form_forum_*_()` functions.
 *
 * @group forums
 * @group template
 * @group forms
 */
class BBP_Tests_Forums_Template_Forms extends BBP_UnitTestCase {

	private function with_forum_edit( $forum_id, $callback ) {
		$old_post = isset( $GLOBALS['post'] ) ? $GLOBALS['post'] : null;
		$old_forum_id = bbpress()->current_forum_id;
		$GLOBALS['post'] = get_post( $forum_id );
		bbpress()->current_forum_id = $forum_id;
		setup_postdata( $GLOBALS['post'] );
		add_filter( 'bbp_is_forum_edit', '__return_true' );

		try {
			$callback();
		} finally {
			remove_filter( 'bbp_is_forum_edit', '__return_true' );
			bbpress()->current_forum_id = $old_forum_id;
			$GLOBALS['post'] = $old_post;
			wp_reset_postdata();
		}
	}

	private function with_forum_post( $action, $values, $callback ) {
		$old_method  = $_SERVER['REQUEST_METHOD'];
		$old_post    = $_POST;
		$old_request = $_REQUEST;
		$post_action = preg_replace( '/_[0-9]+$/', '', $action );
		$_SERVER['REQUEST_METHOD'] = 'POST';
		$_POST = array_merge( $values, array( 'action' => $post_action ) );
		$_REQUEST = array_merge( $old_request, $values, array( 'action' => $post_action, '_wpnonce' => wp_create_nonce( $action ) ) );

		try {
			$callback();
		} finally {
			$_SERVER['REQUEST_METHOD'] = $old_method;
			$_POST    = $old_post;
			$_REQUEST = $old_request;
		}
	}

	/**
	 * @covers ::bbp_form_forum_title
	 * @covers ::bbp_get_form_forum_title
	 * @group  bbp_xss
	 */
	public function test_bbp_get_form_forum_title() {
		$forum_id = $this->factory->forum->create(
			array(
				'post_title' => 'Forum " autofocus onfocus="alert(1)',
			)
		);
		$old_post = isset( $GLOBALS['post'] ) ? $GLOBALS['post'] : null;

		add_filter( 'bbp_is_forum_edit', '__return_true' );
		$GLOBALS['post'] = get_post( $forum_id );
		setup_postdata( $GLOBALS['post'] );

		$this->assertSame( 'Forum &quot; autofocus onfocus=&quot;alert(1)', bbp_get_form_forum_title() );

		$GLOBALS['post'] = $old_post;
		wp_reset_postdata();
		remove_filter( 'bbp_is_forum_edit', '__return_true' );
	}

	/**
	 * @covers ::bbp_form_forum_title
	 * @covers ::bbp_get_form_forum_title
	 */
	public function test_bbp_get_form_forum_title_from_post() {
		$this->assertSame( '', bbp_get_form_forum_title() );

		$this->with_forum_post( 'bbp-new-forum', array( 'bbp_forum_title' => 'Posted forum' ), function() {
			$this->assertSame( 'Posted forum', bbp_get_form_forum_title() );
			$this->expectOutputString( 'Posted forum' );
			bbp_form_forum_title();
		} );
	}

	/**
	 * @covers ::bbp_form_forum_content
	 * @covers ::bbp_get_form_forum_content
	 */
	public function test_bbp_get_form_forum_content() {
		$this->assertSame( '', bbp_get_form_forum_content() );

		$forum_id = $this->factory->forum->create( array( 'post_content' => 'Saved forum content' ) );
		$this->with_forum_edit( $forum_id, function() {
			$this->assertSame( 'Saved forum content', bbp_get_form_forum_content() );
		} );

		$this->with_forum_post( 'bbp-new-forum', array( 'bbp_forum_content' => 'Submitted \\"content\\"' ), function() {
			$this->assertSame( 'Submitted &quot;content&quot;', bbp_get_form_forum_content() );
			$this->expectOutputString( 'Submitted &quot;content&quot;' );
			bbp_form_forum_content();
		} );
	}

	/**
	 * @covers ::bbp_form_forum_moderators
	 * @covers ::bbp_get_form_forum_moderators
	 */
	public function test_bbp_get_form_forum_moderators() {
		$this->assertSame( '', bbp_get_form_forum_moderators() );

		$user_id  = $this->factory->user->create();
		$forum_id = $this->factory->forum->create();
		bbp_add_moderator( $forum_id, $user_id );
		$this->with_forum_edit( $forum_id, function() use ( $user_id, $forum_id ) {
			$this->assertSame( $forum_id, get_the_ID() );
			$this->assertContains( $user_id, bbp_get_moderator_ids( $forum_id ) );
			$this->assertSame( get_userdata( $user_id )->user_nicename, bbp_get_form_forum_moderators() );
		} );

		$this->with_forum_post( 'bbp-new-forum', array( 'bbp_moderators' => 'posted-moderator' ), function() {
			$this->assertSame( 'posted-moderator', bbp_get_form_forum_moderators() );
			$this->expectOutputString( 'posted-moderator' );
			bbp_form_forum_moderators();
		} );
	}

	/**
	 * @covers ::bbp_form_forum_parent
	 * @covers ::bbp_get_form_forum_parent
	 */
	public function test_bbp_get_form_forum_parent() {
		$this->assertSame( 0, bbp_get_form_forum_parent() );

		$parent_id = $this->factory->forum->create();
		$forum_id = $this->factory->forum->create( array( 'post_parent' => $parent_id ) );
		$this->with_forum_edit( $forum_id, function() use ( $parent_id ) {
			$this->assertSame( $parent_id, bbp_get_form_forum_parent() );
		} );

		$this->with_forum_post( 'bbp-new-forum', array( 'bbp_forum_id' => (string) $parent_id ), function() use ( $parent_id ) {
			$this->assertSame( $parent_id, bbp_get_form_forum_parent() );
			$this->expectOutputString( (string) $parent_id );
			bbp_form_forum_parent();
		} );
	}

	/**
	 * @covers ::bbp_form_forum_type
	 * @covers ::bbp_get_form_forum_type
	 */
	public function test_bbp_get_form_forum_type() {
		$this->assertSame( 'forum', bbp_get_form_forum_type() );

		$forum_id = $this->factory->forum->create();
		bbp_categorize_forum( $forum_id );
		$this->with_forum_edit( $forum_id, function() {
			$this->assertSame( 'category', bbp_get_form_forum_type() );
		} );

		$this->with_forum_post( 'bbp-new-forum', array( 'bbp_forum_type' => 'CATEGORY' ), function() {
			$this->assertSame( 'category', bbp_get_form_forum_type() );
			$this->expectOutputString( 'category' );
			bbp_form_forum_type();
		} );
	}

	/**
	 * @covers ::bbp_form_forum_visibility
	 * @covers ::bbp_get_form_forum_visibility
	 */
	public function test_bbp_get_form_forum_visibility() {
		$this->assertSame( bbp_get_public_status_id(), bbp_get_form_forum_visibility() );

		$forum_id = $this->factory->forum->create();
		bbp_hide_forum( $forum_id );
		$this->with_forum_edit( $forum_id, function() {
			$this->assertSame( bbp_get_hidden_status_id(), bbp_get_form_forum_visibility() );
		} );

		$this->with_forum_post( 'bbp-new-forum', array( 'bbp_forum_visibility' => 'PRIVATE' ), function() {
			$this->assertSame( bbp_get_private_status_id(), bbp_get_form_forum_visibility() );
			$this->expectOutputString( bbp_get_private_status_id() );
			bbp_form_forum_visibility();
		} );
	}

	/**
	 * @covers ::bbp_form_forum_subscribed
	 * @covers ::bbp_get_form_forum_subscribed
	 */
	public function test_bbp_get_form_forum_subscribed() {
		$this->assertSame( '', bbp_get_form_forum_subscribed() );

		$user_id  = $this->factory->user->create();
		$forum_id = $this->factory->forum->create( array( 'post_author' => $user_id ) );
		bbp_add_user_forum_subscription( $user_id, $forum_id );
		$this->with_forum_edit( $forum_id, function() {
			$this->assertSame( checked( true, true, false ), bbp_get_form_forum_subscribed() );
		} );

		$old_user = get_current_user_id();
		$old_forum_id = bbpress()->current_forum_id;
		$this->set_current_user( $user_id );
		bbpress()->current_forum_id = $forum_id;
		add_filter( 'bbp_is_single_forum', '__return_true' );
		try {
			$this->assertSame( checked( true, true, false ), bbp_get_form_forum_subscribed() );
		} finally {
			remove_filter( 'bbp_is_single_forum', '__return_true' );
			bbpress()->current_forum_id = $old_forum_id;
			$this->set_current_user( $old_user );
		}

		$this->with_forum_post( 'bbp-new-forum', array( 'bbp_forum_subscription' => '1' ), function() {
			$checked = checked( true, true, false );
			$this->assertSame( $checked, bbp_get_form_forum_subscribed() );
			$this->expectOutputString( $checked );
			bbp_form_forum_subscribed();
		} );
	}

	/**
	 * @covers ::bbp_form_forum_type_dropdown
	 * @covers ::bbp_get_form_forum_type_dropdown
	 */
	public function test_bbp_get_form_forum_type_dropdown() {
		$args = array( 'select_id' => 'custom_forum_type', 'selected' => 'category' );
		$html = bbp_get_form_forum_type_dropdown( $args );
		$this->assertStringContainsString( 'name="custom_forum_type"', $html );
		$this->assertStringContainsString( 'id="custom_forum_type_select"', $html );
		$this->assertMatchesRegularExpression( '/<option value="category" selected=/', $html );
		$this->assertStringContainsString( '<option value="forum"', $html );
		$this->expectOutputString( $html );
		bbp_form_forum_type_dropdown( $args );
	}

	/**
	 * @covers ::bbp_get_form_forum_type_dropdown
	 */
	public function test_bbp_get_form_forum_type_dropdown_selects_forum_by_default() {
		$this->assertMatchesRegularExpression(
			'/<option value="forum" selected=/',
			bbp_get_form_forum_type_dropdown()
		);
	}

	/**
	 * @covers ::bbp_form_forum_status_dropdown
	 * @covers ::bbp_get_form_forum_status_dropdown
	 */
	public function test_bbp_get_form_forum_status_dropdown() {
		$args = array( 'select_id' => 'custom_forum_status', 'selected' => 'closed' );
		$html = bbp_get_form_forum_status_dropdown( $args );
		$this->assertStringContainsString( 'name="custom_forum_status"', $html );
		$this->assertMatchesRegularExpression( '/<option value="closed" selected=/', $html );
		$this->assertStringContainsString( '<option value="open"', $html );
		$this->expectOutputString( $html );
		bbp_form_forum_status_dropdown( $args );
	}

	/**
	 * @covers ::bbp_form_forum_visibility_dropdown
	 * @covers ::bbp_get_form_forum_visibility_dropdown
	 */
	public function test_bbp_get_form_forum_visibility_dropdown() {
		$args = array( 'select_id' => 'custom_forum_visibility', 'selected' => bbp_get_private_status_id() );
		$html = bbp_get_form_forum_visibility_dropdown( $args );
		$this->assertStringContainsString( 'name="custom_forum_visibility"', $html );
		$this->assertMatchesRegularExpression( '/<option value="private" selected=/', $html );
		$this->assertStringContainsString( '<option value="publish"', $html );
		$this->assertStringContainsString( '<option value="hidden"', $html );
		$this->expectOutputString( $html );
		bbp_form_forum_visibility_dropdown( $args );
	}

	/**
	 * @covers ::bbp_get_form_forum_visibility_dropdown
	 */
	public function test_bbp_get_form_forum_visibility_dropdown_has_distinct_filter() {
		$filter = function( $html ) {
			return 'filtered visibility';
		};
		add_filter( 'bbp_get_form_forum_visibility_dropdown', $filter );
		try {
			$this->assertSame( 'filtered visibility', bbp_get_form_forum_visibility_dropdown() );
		} finally {
			remove_filter( 'bbp_get_form_forum_visibility_dropdown', $filter );
		}
	}

	/**
	 * @covers ::bbp_get_form_forum_visibility_dropdown
	 */
	public function test_bbp_get_form_forum_visibility_dropdown_preserves_type_filter() {
		$filter = function( $html, $parsed_args, $args ) {
			$this->assertSame( 'bbp_forum_visibility', $parsed_args['select_id'] );
			$this->assertSame( array(), $args );
			return 'filtered by type hook';
		};
		add_filter( 'bbp_get_form_forum_type_dropdown', $filter, 10, 3 );
		try {
			$this->assertSame( 'filtered by type hook', bbp_get_form_forum_visibility_dropdown() );
		} finally {
			remove_filter( 'bbp_get_form_forum_type_dropdown', $filter );
		}
	}

	/**
	 * @covers ::bbp_is_forum_form_post_request
	 */
	public function test_bbp_is_forum_form_post_request() {
		$this->assertFalse( bbp_is_forum_form_post_request() );

		$this->with_forum_post( 'bbp-new-forum', array(), function() {
			$this->assertTrue( bbp_is_forum_form_post_request() );

			$nonce = $_REQUEST['_wpnonce'];
			$_REQUEST['_wpnonce'] = 'invalid';
			$this->assertFalse( bbp_is_forum_form_post_request() );
			$_REQUEST['_wpnonce'] = $nonce;

			$_SERVER['REQUEST_METHOD'] = 'GET';
			$this->assertFalse( bbp_is_forum_form_post_request() );
		} );

		$forum_id = $this->factory->forum->create();
		$this->with_forum_edit( $forum_id, function() use ( $forum_id ) {
			$this->with_forum_post( 'bbp-edit-forum_' . $forum_id, array(), function() {
				$this->assertTrue( bbp_is_forum_form_post_request() );
			} );
		} );
	}
}
