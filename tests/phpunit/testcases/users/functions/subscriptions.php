<?php

/**
 * Tests for the user component subscription functions.
 *
 * @group users
 * @group functions
 * @group subscriptions
 */
class BBP_Tests_Users_Functions_Subscriptions extends BBP_UnitTestCase {

	/**
	 * @covers ::bbp_get_forum_subscribers
	 */
	public function test_bbp_get_forum_subscribers() {
		$u = $this->factory->user->create_many( 3 );
		$f = $this->factory->forum->create_many( 2 );

		// Add forum subscriptions.
		bbp_add_user_forum_subscription( $u[0], $f[0] );
		bbp_add_user_forum_subscription( $u[1], $f[0] );
		bbp_add_user_forum_subscription( $u[2], $f[0] );

		$subscribers = bbp_get_forum_subscribers( $f[0] );

		$this->assertEqualSets( array( $u[0], $u[1], $u[2] ), $subscribers );

		// Add forum subscriptions.
		bbp_add_user_forum_subscription( $u[0], $f[1] );
		bbp_add_user_forum_subscription( $u[2], $f[1] );

		$subscribers = bbp_get_forum_subscribers( $f[1] );

		$this->assertEqualSets( array( $u[0], $u[2] ), $subscribers );
	}

	/**
	 * @covers ::bbp_get_topic_subscribers
	 */
	public function test_bbp_get_topic_subscribers() {
		$u = $this->factory->user->create_many( 3 );
		$t = $this->factory->topic->create_many( 2 );

		// Add topic subscriptions.
		bbp_add_user_topic_subscription( $u[0], $t[0] );
		bbp_add_user_topic_subscription( $u[1], $t[0] );
		bbp_add_user_topic_subscription( $u[2], $t[0] );

		$subscribers = bbp_get_topic_subscribers( $t[0] );

		$this->assertEqualSets( array( $u[0], $u[1], $u[2] ), $subscribers );

		// Add topic subscriptions.
		bbp_add_user_topic_subscription( $u[0], $t[1] );
		bbp_add_user_topic_subscription( $u[2], $t[1] );

		$subscribers = bbp_get_topic_subscribers( $t[1] );

		$this->assertEqualSets( array( $u[0], $u[2] ), $subscribers );
	}

	/**
	 * @covers ::bbp_get_user_subscriptions
	 * @expectedDeprecated bbp_get_user_subscriptions
	 */
	public function test_bbp_get_user_subscriptions() {
		$u = $this->factory->user->create();
		$t = $this->factory->topic->create_many( 3 );

		// Add topic subscriptions.
		bbp_add_user_topic_subscription( $u, $t[0] );
		bbp_add_user_topic_subscription( $u, $t[1] );
		bbp_add_user_topic_subscription( $u, $t[2] );

		$expected = bbp_has_topics( array( 'post__in' => array( $t[0], $t[1], $t[2] ) ) );
		$subscriptions = bbp_get_user_subscriptions( $u );

		$this->assertEquals( $expected, $subscriptions );

		// Remove topic subscription.
		bbp_remove_user_topic_subscription( $u, $t[1] );

		$expected = bbp_has_topics( array( 'post__in' => array( $t[0], $t[2] ) ) );
		$subscriptions = bbp_get_user_subscriptions( $u );

		$this->assertEquals( $expected, $subscriptions );
	}

	/**
	 * @covers ::bbp_get_user_topic_subscriptions
	 */
	public function test_bbp_get_user_topic_subscriptions() {
		$u = $this->factory->user->create();
		$t = $this->factory->topic->create_many( 3 );

		// Add topic subscriptions.
		bbp_add_user_topic_subscription( $u, $t[0] );
		bbp_add_user_topic_subscription( $u, $t[1] );
		bbp_add_user_topic_subscription( $u, $t[2] );

		$expected = bbp_has_topics( array( 'post__in' => array( $t[0], $t[1], $t[2] ) ) );
		$subscriptions = bbp_get_user_topic_subscriptions( $u );

		$this->assertEquals( $expected, $subscriptions );

		// Remove topic subscription.
		bbp_remove_user_topic_subscription( $u, $t[1] );

		$expected = bbp_has_topics( array( 'post__in' => array( $t[0], $t[2] ) ) );
		$subscriptions = bbp_get_user_topic_subscriptions( $u );

		$this->assertEquals( $expected, $subscriptions );
	}

	/**
	 * @covers ::bbp_get_user_forum_subscriptions
	 */
	public function test_bbp_get_user_forum_subscriptions() {
		$u = $this->factory->user->create();
		$f = $this->factory->forum->create_many( 3 );

		// Add forum subscriptions.
		bbp_add_user_forum_subscription( $u, $f[0] );
		bbp_add_user_forum_subscription( $u, $f[1] );
		bbp_add_user_forum_subscription( $u, $f[2] );

		$expected = bbp_has_forums( array( 'post__in' => array( $f[0], $f[1], $f[2] ) ) );
		$subscriptions = bbp_get_user_forum_subscriptions( $u );

		$this->assertEquals( $expected, $subscriptions );

		// Remove forum subscription.
		bbp_remove_user_forum_subscription( $u, $f[1] );

		$expected = bbp_has_forums( array( 'post__in' => array( $f[0], $f[2] ) ) );
		$subscriptions = bbp_get_user_forum_subscriptions( $u );

		$this->assertEquals( $expected, $subscriptions );
	}

	/**
	 * @covers ::bbp_get_user_subscribed_forum_ids
	 */
	public function test_bbp_get_user_subscribed_forum_ids() {
		$u = $this->factory->user->create();
		$f = $this->factory->forum->create_many( 3 );

		// Add forum subscriptions.
		bbp_add_user_forum_subscription( $u, $f[0] );
		bbp_add_user_forum_subscription( $u, $f[1] );
		bbp_add_user_forum_subscription( $u, $f[2] );

		$subscriptions = bbp_get_user_subscribed_forum_ids( $u );

		$this->assertEqualSets( array( $f[0], $f[1], $f[2] ), $subscriptions );

		// Remove forum subscription.
		bbp_remove_user_forum_subscription( $u, $f[1] );

		$subscriptions = bbp_get_user_subscribed_forum_ids( $u );

		$this->assertEqualSets( array( $f[0], $f[2] ), $subscriptions );
	}

	/**
	 * @covers ::bbp_get_user_subscribed_topic_ids
	 */
	public function test_bbp_get_user_subscribed_topic_ids() {
		$u = $this->factory->user->create();
		$t = $this->factory->topic->create_many( 3 );

		// Add topic subscriptions.
		bbp_add_user_topic_subscription( $u, $t[0] );
		bbp_add_user_topic_subscription( $u, $t[1] );
		bbp_add_user_topic_subscription( $u, $t[2] );

		$subscriptions = bbp_get_user_subscribed_topic_ids( $u );

		$this->assertEqualSets( array( $t[0], $t[1], $t[2] ), $subscriptions );

		// Remove topic subscription.
		bbp_remove_user_topic_subscription( $u, $t[1] );

		$subscriptions = bbp_get_user_subscribed_topic_ids( $u );

		$this->assertEqualSets( array( $t[0], $t[2] ), $subscriptions );
	}

	/**
	 * @covers ::bbp_is_user_subscribed
	 */
	public function test_bbp_is_user_subscribed() {
		$u = $this->factory->user->create();
		$f = $this->factory->forum->create_many( 2 );
		$t = $this->factory->topic->create_many( 2 );

		// Add forum subscription.
		bbp_add_user_forum_subscription( $u, $f[0] );

		$this->assertTrue( bbp_is_user_subscribed( $u, $f[0] ) );
		$this->assertFalse( bbp_is_user_subscribed( $u, $f[1] ) );

		// Add topic subscription.
		bbp_add_user_topic_subscription( $u, $t[0] );

		$this->assertTrue( bbp_is_user_subscribed( $u, $t[0] ) );
		$this->assertFalse( bbp_is_user_subscribed( $u, $t[1] ) );
	}

	/**
	 * @covers ::bbp_is_user_subscribed_to_forum
	 */
	public function test_bbp_is_user_subscribed_to_forum() {
		$u = $this->factory->user->create();
		$f = $this->factory->forum->create_many( 2 );

		// Add forum subscription.
		bbp_add_user_forum_subscription( $u, $f[0] );

		$this->assertTrue( bbp_is_user_subscribed_to_forum( $u, $f[0] ) );
		$this->assertFalse( bbp_is_user_subscribed_to_forum( $u, $f[1] ) );
	}

	/**
	 * @covers ::bbp_is_user_subscribed_to_topic
	 */
	public function test_bbp_is_user_subscribed_to_topic() {
		$u = $this->factory->user->create();
		$t = $this->factory->topic->create_many( 2 );

		// Add topic subscription.
		bbp_add_user_topic_subscription( $u, $t[0] );

		$this->assertTrue( bbp_is_user_subscribed_to_topic( $u, $t[0] ) );
		$this->assertFalse( bbp_is_user_subscribed_to_topic( $u, $t[1] ) );
	}

	/**
	 * @covers ::bbp_add_user_subscription
	 */
	public function test_bbp_add_user_subscription() {
		$u = $this->factory->user->create();
		$f = $this->factory->forum->create();
		$t = $this->factory->topic->create( array(
			'post_parent' => $f,
			'topic_meta' => array(
				'forum_id' => $f,
			),
		) );

		// Add forum subscription.
		bbp_add_user_subscription( $u, $f );

		$this->assertTrue( bbp_is_user_subscribed_to_forum( $u, $f ) );

		// Add topic subscription.
		bbp_add_user_subscription( $u, $t );

		$this->assertTrue( bbp_is_user_subscribed_to_topic( $u, $t ) );
	}

	/**
	 * @covers ::bbp_add_user_forum_subscription
	 */
	public function test_bbp_add_user_forum_subscription() {
		$u = $this->factory->user->create();
		$f = $this->factory->forum->create();

		// Add forum subscription.
		bbp_add_user_forum_subscription( $u, $f );

		$this->assertTrue( bbp_is_user_subscribed_to_forum( $u, $f ) );
	}

	/**
	 * @covers ::bbp_add_user_topic_subscription
	 */
	public function test_bbp_add_user_topic_subscription() {
		$u = $this->factory->user->create();
		$t = $this->factory->topic->create();

		// Add forum subscription.
		bbp_add_user_topic_subscription( $u, $t );

		$this->assertTrue( bbp_is_user_subscribed_to_topic( $u, $t ) );
	}

	/**
	 * @covers ::bbp_remove_user_subscription
	 */
	public function test_bbp_remove_user_subscription() {
		$u = $this->factory->user->create();
		$f = $this->factory->forum->create();
		$t = $this->factory->topic->create( array(
			'post_parent' => $f,
			'topic_meta' => array(
				'forum_id' => $f,
			),
		) );

		// Add forum subscription.
		bbp_add_user_subscription( $u, $f );

		$this->assertTrue( bbp_is_user_subscribed_to_forum( $u, $f ) );

		// Remove forum subscription.
		bbp_remove_user_subscription( $u, $f );

		$this->assertFalse( bbp_is_user_subscribed_to_forum( $u, $f ) );

		// Add topic subscription.
		bbp_add_user_subscription( $u, $t );

		$this->assertTrue( bbp_is_user_subscribed_to_topic( $u, $t ) );

		// Remove topic subscription.
		bbp_remove_user_subscription( $u, $t );

		$this->assertFalse( bbp_is_user_subscribed_to_topic( $u, $t ) );
	}

	/**
	 * @covers ::bbp_remove_user_forum_subscription
	 */
	public function test_bbp_remove_user_forum_subscription() {
		$u = $this->factory->user->create();
		$f = $this->factory->forum->create();

		// Add forum subscription.
		bbp_add_user_forum_subscription( $u, $f );

		$this->assertTrue( bbp_is_user_subscribed_to_forum( $u, $f ) );

		// Remove forum subscription.
		bbp_remove_user_forum_subscription( $u, $f );

		$this->assertFalse( bbp_is_user_subscribed_to_forum( $u, $f ) );
	}

	/**
	 * @covers ::bbp_remove_user_topic_subscription
	 */
	public function test_bbp_remove_user_topic_subscription() {
		$u = $this->factory->user->create();
		$t = $this->factory->topic->create();

		// Add forum subscription.
		bbp_add_user_topic_subscription( $u, $t );

		$this->assertTrue( bbp_is_user_subscribed_to_topic( $u, $t ) );

		// Remove topic subscription.
		bbp_remove_user_topic_subscription( $u, $t );

		$this->assertFalse( bbp_is_user_subscribed_to_topic( $u, $t ) );
	}

	/**
	 * @covers ::bbp_forum_subscriptions_handler
	 * @ticket 3706
	 */
	public function test_bbp_forum_subscriptions_handler() {
		$old_get     = $_GET;
		$old_request = $_REQUEST;
		$old_server  = $_SERVER;
		$old_user    = get_current_user_id();
		$old_errors  = bbpress()->errors;
		$home_url    = wp_parse_url( home_url( '/' ) );
		$user_id     = $this->factory->user->create( array( 'role' => 'administrator' ) );
		$forum_id    = $this->factory->forum->create();

		bbp_set_user_role( $user_id, bbp_get_keymaster_role() );
		$this->set_current_user( $user_id );
		$_SERVER['HTTP_HOST']   = $home_url['host'] . ( isset( $home_url['port'] ) ? ':' . $home_url['port'] : '' );
		$_SERVER['REQUEST_URI'] = $home_url['path'];
		bbpress()->errors       = new WP_Error();

		try {
			$_GET['object_id'] = $forum_id;
			$this->assertTrue( bbp_current_user_can_toggle_engagement( $forum_id, 'post', 'subscription', 'add' ) );
			$_REQUEST['_wpnonce'] = 'invalid';
			$this->assertFalse( bbp_forum_subscriptions_handler( 'bbp_subscribe' ) );
			$this->assertSame( 'bbp_subscription_object_id', bbpress()->errors->get_error_code() );
			$this->assertFalse( bbp_is_user_subscribed_to_forum( $user_id, $forum_id ) );

			bbpress()->errors->remove( 'bbp_subscription_object_id' );
			$_REQUEST['_wpnonce']   = wp_create_nonce( 'toggle-subscription_' . $forum_id );
			$_REQUEST['redirect_to'] = home_url( '/forum-subscription-return/' );
			$redirect                = $this->catch_bbp_redirect( function () {
				bbp_forum_subscriptions_handler( 'bbp_subscribe' );
			} );
			$this->assertSame( home_url( '/forum-subscription-return/' ), $redirect );
			$this->assertTrue( bbp_is_user_subscribed_to_forum( $user_id, $forum_id ) );

			$redirect = $this->catch_bbp_redirect( function () {
				bbp_forum_subscriptions_handler( 'bbp_unsubscribe' );
			} );
			$this->assertSame( home_url( '/forum-subscription-return/' ), $redirect );
			$this->assertFalse( bbp_is_user_subscribed_to_forum( $user_id, $forum_id ) );
		} finally {
			bbpress()->errors = $old_errors;
			$_GET             = $old_get;
			$_REQUEST         = $old_request;
			$_SERVER          = $old_server;
			$this->set_current_user( $old_user );
		}
	}

	/**
	 * @covers ::bbp_subscriptions_handler
	 * @ticket 3706
	 */
	public function test_bbp_subscriptions_handler() {
		$old_get     = $_GET;
		$old_request = $_REQUEST;
		$old_server  = $_SERVER;
		$old_user    = get_current_user_id();
		$old_errors  = bbpress()->errors;
		$home_url    = wp_parse_url( home_url( '/' ) );
		$user_id     = $this->factory->user->create( array( 'role' => 'administrator' ) );
		$forum_id    = $this->factory->forum->create();
		$topic_id    = $this->factory->topic->create( array(
			'post_parent' => $forum_id,
			'topic_meta'  => array( 'forum_id' => $forum_id ),
		) );

		bbp_set_user_role( $user_id, bbp_get_keymaster_role() );
		$this->set_current_user( $user_id );
		$_SERVER['HTTP_HOST']   = $home_url['host'] . ( isset( $home_url['port'] ) ? ':' . $home_url['port'] : '' );
		$_SERVER['REQUEST_URI'] = $home_url['path'];
		bbpress()->errors       = new WP_Error();

		try {
			unset( $_GET['object_id'] );
			$this->assertFalse( bbp_subscriptions_handler( 'bbp_subscribe' ) );
			$this->assertFalse( bbp_has_errors() );

			$_GET['object_id'] = $topic_id;
			$this->assertTrue( bbp_current_user_can_toggle_engagement( $topic_id, 'post', 'subscription', 'add' ) );
			$this->assertFalse( bbp_subscriptions_handler( 'unsupported' ) );
			$this->assertFalse( bbp_has_errors() );

			$_REQUEST['_wpnonce'] = 'invalid';
			$this->assertFalse( bbp_subscriptions_handler( 'bbp_subscribe' ) );
			$this->assertSame( 'bbp_subscription_object_id', bbpress()->errors->get_error_code() );
			$this->assertFalse( bbp_is_user_subscribed_to_topic( $user_id, $topic_id ) );

			bbpress()->errors->remove( 'bbp_subscription_object_id' );
			$_REQUEST['_wpnonce'] = wp_create_nonce( 'toggle-subscription_post_' . $topic_id );
			$redirect              = $this->catch_bbp_redirect( function () {
				bbp_subscriptions_handler( 'bbp_subscribe' );
			} );
			$this->assertSame( get_permalink( $topic_id ), $redirect );
			$this->assertTrue( bbp_is_user_subscribed_to_topic( $user_id, $topic_id ) );

			$redirect = $this->catch_bbp_redirect( function () {
				bbp_subscriptions_handler( 'bbp_unsubscribe' );
			} );
			$this->assertSame( get_permalink( $topic_id ), $redirect );
			$this->assertFalse( bbp_is_user_subscribed_to_topic( $user_id, $topic_id ) );
		} finally {
			bbpress()->errors = $old_errors;
			$_GET             = $old_get;
			$_REQUEST         = $old_request;
			$_SERVER          = $old_server;
			$this->set_current_user( $old_user );
		}
	}
}
