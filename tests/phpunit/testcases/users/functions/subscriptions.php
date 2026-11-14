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
	 * Engagement storage strategies.
	 *
	 * @return array
	 */
	public function data_strategies() {
		return array(
			'meta' => array( 'BBP_User_Engagements_Meta' ),
			'term' => array( 'BBP_User_Engagements_Term' ),
			'user' => array( 'BBP_User_Engagements_User' )
		);
	}

	/**
	 * @ticket BBP2989
	 *
	 * @dataProvider data_strategies
	 *
	 * @covers ::bbp_get_user_term_subscriptions
	 */
	public function test_bbp_get_user_term_subscriptions( $class_name ) {
		$strategy       = bbpress()->engagements;
		$displayed_user = bbpress()->displayed_user;
		$term_query     = bbpress()->term_query;
		$taxonomy       = 'bbp_test_user_subs';

		bbpress()->engagements = new $class_name();

		register_taxonomy(
			$taxonomy,
			bbp_get_topic_post_type(),
			array(
				'public'            => true,
				'bbp_subscriptions' => true
			)
		);

		try {
			$user_id  = $this->factory->user->create();
			$term_ids = $this->factory->term->create_many(
				3,
				array( 'taxonomy' => bbp_get_topic_tag_tax_id() )
			);
			$custom_id = $this->factory->term->create( array( 'taxonomy' => $taxonomy ) );

			bbpress()->displayed_user = get_userdata( $user_id );

			bbp_add_user_subscription( $user_id, $term_ids[0], 'term' );
			bbp_add_user_subscription( $user_id, $term_ids[2], 'term' );
			bbp_add_user_subscription( $user_id, $custom_id, 'term' );

			$this->assertTrue( bbp_get_user_term_subscriptions( $user_id ) );
			$this->assertEqualSets( array( $term_ids[0], $term_ids[2] ), wp_list_pluck( bbpress()->term_query->terms, 'term_id' ) );
			$this->assertTrue( bbp_get_user_term_subscriptions( array( 'taxonomy' => $taxonomy ) ) );
			$this->assertSame( array( $custom_id ), wp_list_pluck( bbpress()->term_query->terms, 'term_id' ) );

			bbp_remove_user_subscription( $user_id, $term_ids[0], 'term' );

			$this->assertTrue( bbp_get_user_term_subscriptions( $user_id ) );
			$this->assertSame( array( $term_ids[2] ), wp_list_pluck( bbpress()->term_query->terms, 'term_id' ) );

			$this->assertFalse( bbp_get_user_term_subscriptions( $this->factory->user->create() ) );
			$this->assertSame( array(), bbpress()->term_query->terms );
		} finally {
			bbpress()->engagements    = $strategy;
			bbpress()->displayed_user = $displayed_user;
			bbpress()->term_query     = $term_query;
			unregister_taxonomy( $taxonomy );
		}
	}

	/**
	 * @ticket BBP2989
	 *
	 * @covers ::bbp_get_topic_subscription_taxonomies
	 * @covers ::bbp_get_topic_term_subscription_contexts
	 * @covers ::bbp_get_subscribers_for_topic_terms
	 */
	public function test_bbp_get_subscribers_for_topic_terms_in_active_taxonomies() {
		$taxonomy = 'bbp_test_topic_subscriptions';
		register_taxonomy(
			$taxonomy,
			bbp_get_topic_post_type(),
			array(
				'public'            => true,
				'bbp_subscriptions' => true
			)
		);

		try {
			$topic_id     = $this->factory->topic->create();
			$tag_id       = $this->factory->term->create( array( 'taxonomy' => bbp_get_topic_tag_tax_id() ) );
			$custom_id    = $this->factory->term->create( array( 'taxonomy' => $taxonomy ) );
			$shared_user  = $this->factory->user->create();
			$custom_user  = $this->factory->user->create();

			wp_set_object_terms( $topic_id, $tag_id, bbp_get_topic_tag_tax_id() );
			wp_set_object_terms( $topic_id, $custom_id, $taxonomy );
			bbp_add_user_subscription( $shared_user, $tag_id, 'term' );
			bbp_add_user_subscription( $shared_user, $custom_id, 'term' );
			bbp_add_user_subscription( $custom_user, $custom_id, 'term' );

			update_option(
				'_bbp_enable_subscriptions',
				array(
					'term:' . bbp_get_topic_tag_tax_id(),
					'term:' . $taxonomy
				)
			);

			$this->assertEqualSets( array( bbp_get_topic_tag_tax_id(), $taxonomy ), bbp_get_topic_subscription_taxonomies() );
			$this->assertEqualSets( array( $shared_user, $custom_user ), bbp_get_subscribers_for_topic_terms( $topic_id ) );
			$contexts = bbp_get_topic_term_subscription_contexts( $topic_id );
			$this->assertContains( $contexts[ $shared_user ]['object_id'], array( $tag_id, $custom_id ) );
			$this->assertSame( 'term', $contexts[ $shared_user ]['object_type'] );
			$this->assertSame(
				array(
					'object_id'   => $custom_id,
					'object_type' => 'term'
				),
				$contexts[ $custom_user ]
			);

			update_option( '_bbp_enable_subscriptions', array( 'term:' . bbp_get_topic_tag_tax_id() ) );

			$this->assertSame( array( bbp_get_topic_tag_tax_id() ), bbp_get_topic_subscription_taxonomies() );
			$this->assertSame( array( $shared_user ), bbp_get_subscribers_for_topic_terms( $topic_id ) );
		} finally {
			unregister_taxonomy( $taxonomy );
		}
	}

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
	 * @ticket 3229
	 *
	 * @covers ::bbp_get_subscription_unsubscribe_token
	 * @covers ::bbp_validate_subscription_unsubscribe_token
	 * @covers ::bbp_get_subscription_unsubscribe_url
	 */
	public function test_subscription_unsubscribe_token_and_url() {
		$user_id  = $this->factory->user->create();
		$forum_id = $this->factory->forum->create();

		$this->assertTrue( bbp_add_user_forum_subscription( $user_id, $forum_id ) );

		$token = bbp_get_subscription_unsubscribe_token( $user_id, $forum_id );
		$this->assertSame( $user_id, bbp_validate_subscription_unsubscribe_token( $token, $forum_id ) );
		$this->assertFalse( bbp_validate_subscription_unsubscribe_token( $token, $forum_id + 1 ) );
		$this->assertFalse( bbp_validate_subscription_unsubscribe_token( $token . 'changed', $forum_id ) );

		$expired = bbp_get_subscription_unsubscribe_token( $user_id, $forum_id, 'post', time() - 1 );
		$this->assertFalse( bbp_validate_subscription_unsubscribe_token( $expired, $forum_id ) );

		add_filter( 'bbp_pretty_urls', '__return_true' );
		$url = bbp_get_subscription_unsubscribe_url( $user_id, $forum_id );
		remove_filter( 'bbp_pretty_urls', '__return_true' );

		$query = array();
		wp_parse_str( wp_parse_url( $url, PHP_URL_QUERY ), $query );

		$this->assertSame( 'bbp_email_unsubscribe', $query['action'] );
		$this->assertSame( (string) $forum_id, $query['object_id'] );
		$this->assertSame( 'post', $query['object_type'] );
		$this->assertSame( '/' . bbp_get_subscription_unsubscribe_slug(), untrailingslashit( wp_parse_url( $url, PHP_URL_PATH ) ) );
		$this->assertSame( $user_id, bbp_validate_subscription_unsubscribe_token( $query['bbp_subscription_token'], $forum_id ) );
		$this->assertFalse( bbp_validate_subscription_unsubscribe_token( $query['bbp_subscription_token'], $forum_id, 'term' ) );

		bbPress::add_rewrite_tags();
		bbPress::add_rewrite_rules();
		$rewrite_rule = bbp_get_subscription_unsubscribe_slug() . '/?$';
		$this->assertSame( 'index.php?' . bbp_get_subscription_unsubscribe_rewrite_id() . '=1', bbp_rewrite()->extra_rules_top[ $rewrite_rule ] );

		add_filter( 'bbp_pretty_urls', '__return_false' );
		$plain_url = bbp_get_subscription_unsubscribe_url( $user_id, $forum_id );
		remove_filter( 'bbp_pretty_urls', '__return_false' );
		$plain_query = array();
		wp_parse_str( wp_parse_url( $plain_url, PHP_URL_QUERY ), $plain_query );
		$this->assertSame( '1', $plain_query[ bbp_get_subscription_unsubscribe_rewrite_id() ] );
	}

	/**
	 * @ticket 3229
	 *
	 * @covers ::bbp_subscription_unsubscribe_handler
	 */
	public function test_subscription_unsubscribe_handler_confirms_get_and_processes_post() {
		$old_get    = $_GET;
		$old_post   = $_POST;
		$old_server = $_SERVER;
		$old_user   = get_current_user_id();
		$old_status = get_query_var( 'bbp_subscription_unsubscribe' );
		$old_route  = get_query_var( bbp_get_subscription_unsubscribe_rewrite_id() );
		$user_id    = $this->factory->user->create();
		$forum_id   = $this->factory->forum->create();
		$query      = array();

		$this->assertTrue( bbp_add_user_forum_subscription( $user_id, $forum_id ) );
		$url = bbp_get_subscription_unsubscribe_url( $user_id, $forum_id );
		wp_parse_str( wp_parse_url( $url, PHP_URL_QUERY ), $query );

		try {
			$this->set_current_user( 0 );
			$_GET                      = array();
			$_POST                     = array();
			$_SERVER['REQUEST_METHOD'] = 'GET';
			set_query_var( bbp_get_subscription_unsubscribe_rewrite_id(), '1' );

			bbp_subscription_unsubscribe_handler();
			$this->assertTrue( bbp_is_subscription_unsubscribe( 'invalid' ) );
			$this->assertTrue( bbp_is_user_subscribed_to_forum( $user_id, $forum_id ) );

			$_GET = $query;

			bbp_subscription_unsubscribe_handler();
			$this->assertTrue( bbp_is_subscription_unsubscribe( 'confirm' ) );
			$this->assertTrue( bbp_is_user_subscribed_to_forum( $user_id, $forum_id ) );

			$_SERVER['REQUEST_METHOD'] = 'POST';
			bbp_subscription_unsubscribe_handler();
			$this->assertTrue( bbp_is_subscription_unsubscribe( 'invalid' ) );
			$this->assertTrue( bbp_is_user_subscribed_to_forum( $user_id, $forum_id ) );

			$_POST['List-Unsubscribe'] = 'One-Click';

			bbp_subscription_unsubscribe_handler();
			$this->assertTrue( bbp_is_subscription_unsubscribe( 'success' ) );
			$this->assertFalse( bbp_is_user_subscribed_to_forum( $user_id, $forum_id ) );

			// Repeating the request is intentionally idempotent.
			bbp_subscription_unsubscribe_handler();
			$this->assertTrue( bbp_is_subscription_unsubscribe( 'success' ) );
		} finally {
			$_GET    = $old_get;
			$_POST   = $old_post;
			$_SERVER = $old_server;
			set_query_var( 'bbp_subscription_unsubscribe', $old_status );
			set_query_var( bbp_get_subscription_unsubscribe_rewrite_id(), $old_route );
			$this->set_current_user( $old_user );
		}
	}

	/**
	 * @ticket 3229
	 *
	 * @covers ::bbp_subscription_unsubscribe_handler
	 */
	public function test_subscription_unsubscribe_handler_processes_term_subscription() {
		$old_get    = $_GET;
		$old_post   = $_POST;
		$old_server = $_SERVER;
		$old_status = get_query_var( 'bbp_subscription_unsubscribe' );
		$old_route  = get_query_var( bbp_get_subscription_unsubscribe_rewrite_id() );
		$user_id    = $this->factory->user->create();
		$term_id    = $this->factory->term->create( array( 'taxonomy' => bbp_get_topic_tag_tax_id() ) );
		$query      = array();

		$this->assertTrue( bbp_add_user_subscription( $user_id, $term_id, 'term' ) );
		$url = bbp_get_subscription_unsubscribe_url( $user_id, $term_id, 'term' );
		wp_parse_str( wp_parse_url( $url, PHP_URL_QUERY ), $query );

		try {
			$_GET                      = $query;
			$_POST                     = array( 'List-Unsubscribe' => 'One-Click' );
			$_SERVER['REQUEST_METHOD'] = 'POST';
			set_query_var( bbp_get_subscription_unsubscribe_rewrite_id(), '1' );

			bbp_subscription_unsubscribe_handler();
			$this->assertTrue( bbp_is_subscription_unsubscribe( 'success' ) );
			$this->assertFalse( bbp_is_user_subscribed( $user_id, $term_id, 'term' ) );
		} finally {
			$_GET    = $old_get;
			$_POST   = $old_post;
			$_SERVER = $old_server;
			set_query_var( 'bbp_subscription_unsubscribe', $old_status );
			set_query_var( bbp_get_subscription_unsubscribe_rewrite_id(), $old_route );
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
