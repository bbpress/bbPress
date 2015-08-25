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

		$this->assertEquals( array( $u[0], $u[1], $u[2] ), $subscribers );

		// Add forum subscriptions.
		bbp_add_user_forum_subscription( $u[0], $f[1] );
		bbp_add_user_forum_subscription( $u[2], $f[1] );

		$subscribers = bbp_get_forum_subscribers( $f[1] );

		$this->assertEquals( array( $u[0], $u[2] ), $subscribers );
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

		$this->assertEquals( array( $u[0], $u[1], $u[2] ), $subscribers );

		// Add topic subscriptions.
		bbp_add_user_topic_subscription( $u[0], $t[1] );
		bbp_add_user_topic_subscription( $u[2], $t[1] );

		$subscribers = bbp_get_topic_subscribers( $t[1] );

		$this->assertEquals( array( $u[0], $u[2] ), $subscribers );
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

		$this->assertEquals( array( $f[0], $f[1], $f[2] ), $subscriptions );

		// Remove forum subscription.
		bbp_remove_user_forum_subscription( $u, $f[1] );

		$subscriptions = bbp_get_user_subscribed_forum_ids( $u );

		$this->assertEquals( array( $f[0], $f[2] ), $subscriptions );
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

		$this->assertEquals( array( $t[0], $t[1], $t[2] ), $subscriptions );

		// Remove topic subscription.
		bbp_remove_user_topic_subscription( $u, $t[1] );

		$subscriptions = bbp_get_user_subscribed_topic_ids( $u );

		$this->assertEquals( array( $t[0], $t[2] ), $subscriptions );
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
	 * @todo   Implement test_bbp_forum_subscriptions_handler().
	 */
	public function test_bbp_forum_subscriptions_handler() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_subscriptions_handler
	 * @todo   Implement test_bbp_subscriptions_handler().
	 */
	public function test_bbp_subscriptions_handler() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}
}
