<?php

/**
 * Tests for favorite and subscription toggle permissions.
 *
 * @group users
 * @group engagements
 */
class BBP_Tests_Users_Functions_Engagement_Permissions extends BBP_UnitTestCase {

	protected $old_get;
	protected $old_request;
	protected $old_server;
	protected $old_errors;
	protected $allow_super_mods;
	protected $old_is_single_user;
	protected $old_screen;

	public function setUp(): void {
		parent::setUp();

		$this->old_get            = $_GET;
		$this->old_request        = $_REQUEST;
		$this->old_server         = $_SERVER;
		$this->old_errors         = bbpress()->errors;
		$this->allow_super_mods    = get_option( '_bbp_allow_super_mods', null );
		$this->old_is_single_user = isset( bbp_get_wp_query()->bbp_is_single_user )
			? bbp_get_wp_query()->bbp_is_single_user
			: null;
		$this->old_screen         = array_key_exists( 'current_screen', $GLOBALS )
			? $GLOBALS['current_screen']
			: null;
		$home_url                 = wp_parse_url( home_url( '/' ) );

		bbpress()->errors       = new WP_Error();
		$_SERVER['HTTP_HOST']   = $home_url['host'] . ( isset( $home_url['port'] ) ? ':' . $home_url['port'] : '' );
		$_SERVER['REQUEST_URI'] = $home_url['path'];
		set_current_screen( 'front' );
	}

	public function tearDown(): void {
		if ( null === $this->allow_super_mods ) {
			delete_option( '_bbp_allow_super_mods' );
		} else {
			update_option( '_bbp_allow_super_mods', $this->allow_super_mods );
		}

		if ( null === $this->old_is_single_user ) {
			unset( bbp_get_wp_query()->bbp_is_single_user );
		} else {
			bbp_get_wp_query()->bbp_is_single_user = $this->old_is_single_user;
		}

		$_GET              = $this->old_get;
		$_REQUEST          = $this->old_request;
		$_SERVER           = $this->old_server;
		bbpress()->errors = $this->old_errors;

		if ( null === $this->old_screen ) {
			unset( $GLOBALS['current_screen'] );
		} else {
			$GLOBALS['current_screen'] = $this->old_screen;
		}

		parent::tearDown();
	}

	private function set_keymaster( $user_id ) {
		bbp_set_user_role( $user_id, bbp_get_keymaster_role() );
		update_option( '_bbp_allow_super_mods', 1 );
		bbp_get_wp_query()->bbp_is_single_user = true;
		$this->set_current_user( $user_id );
	}

	private function submit_toggle( $action ) {
		$redirected = false;
		$interrupt_redirect = function() use ( &$redirected ) {
			$redirected = true;
			throw new RuntimeException( 'Engagement redirect.' );
		};
		add_filter( 'wp_redirect', $interrupt_redirect );

		try {
			if ( in_array( $action, array( 'bbp_subscribe', 'bbp_unsubscribe' ), true ) ) {
				bbp_subscriptions_handler( $action );
			} else {
				bbp_favorites_handler( $action );
			}
		} catch ( RuntimeException $exception ) {
			if ( 'Engagement redirect.' !== $exception->getMessage() ) {
				throw $exception;
			}
		} finally {
			remove_filter( 'wp_redirect', $interrupt_redirect );
		}

		return $redirected;
	}

	/**
	 * @covers ::bbp_current_user_can_manage_engagements
	 */
	public function test_current_user_can_manage_engagements() {
		$user_id       = $this->factory->user->create( array( 'role' => bbp_get_participant_role() ) );
		$other_id      = $this->factory->user->create( array( 'role' => bbp_get_participant_role() ) );
		$old_displayed = bbpress()->displayed_user;
		$filtered_type = '';
		$capture_type  = function( $retval, $engagement ) use ( &$filtered_type ) {
			$filtered_type = $engagement;
			return $retval;
		};

		bbpress()->displayed_user = get_userdata( $user_id );

		try {
			$this->set_current_user( 0 );
			$this->assertFalse( bbp_current_user_can_manage_engagements( 'favorite' ) );

			$this->set_current_user( $user_id );
			$this->assertTrue( bbp_current_user_can_manage_engagements( 'favorite' ) );
			$this->assertTrue( bbp_current_user_can_manage_engagements( 'subscription' ) );
			$this->assertFalse( bbp_current_user_can_manage_engagements( 'invalid' ) );
			$this->assertFalse( bbp_current_user_can_manage_engagements( 'favorite', $other_id ) );
			$this->assertFalse( bbp_current_user_can_manage_engagements() );

			add_filter( 'bbp_is_subscriptions', '__return_true' );
			add_filter( 'bbp_current_user_can_manage_engagements', $capture_type, 10, 2 );
			$this->assertTrue( bbp_current_user_can_manage_engagements() );
			$this->assertSame( 'subscription', $filtered_type );
			remove_filter( 'bbp_current_user_can_manage_engagements', $capture_type, 10 );
			remove_filter( 'bbp_is_subscriptions', '__return_true' );

			add_filter( 'bbp_current_user_can_manage_engagements', '__return_true' );
			$this->assertTrue( bbp_current_user_can_manage_engagements( 'favorite', $other_id ) );
		} finally {
			remove_filter( 'bbp_current_user_can_manage_engagements', $capture_type, 10 );
			remove_filter( 'bbp_current_user_can_manage_engagements', '__return_true' );
			remove_filter( 'bbp_is_subscriptions', '__return_true' );
			bbpress()->displayed_user = $old_displayed;
		}
	}

	/**
	 * @covers ::bbp_subscriptions_handler
	 */
	public function test_subscription_request_cannot_write_to_user_meta() {
		$user_id   = $this->factory->user->create( array( 'role' => bbp_get_participant_role() ) );
		$target_id = $this->factory->user->create();

		$this->set_current_user( $user_id );
		$_GET['object_id']     = $target_id;
		$_GET['object_type']   = 'user';
		$_REQUEST['_wpnonce'] = wp_create_nonce( 'toggle-subscription_' . $target_id );

		$this->assertFalse( $this->submit_toggle( 'bbp_subscribe' ) );
		$this->assertFalse( metadata_exists( 'user', $target_id, '_bbp_subscription' ) );
	}

	/**
	 * @covers ::bbp_subscriptions_handler
	 */
	public function test_subscription_request_cannot_register_a_term_taxonomy() {
		$user_id      = $this->factory->user->create( array( 'role' => bbp_get_participant_role() ) );
		$old_strategy = bbpress()->engagements;
		$term         = wp_insert_term( 'Engagement target', 'category' );

		$this->set_current_user( $user_id );
		bbpress()->engagements  = new BBP_User_Engagements_Term();
		$_GET['object_id']     = $term['term_id'];
		$_GET['object_type']   = 'term';
		$_REQUEST['_wpnonce'] = wp_create_nonce( 'toggle-subscription_' . $term['term_id'] );

		try {
			$this->assertFalse( $this->submit_toggle( 'bbp_subscribe' ) );
			$this->assertFalse( taxonomy_exists( '_bbp_subscription_term' ) );
		} finally {
			bbpress()->engagements = $old_strategy;
		}
	}

	/**
	 * @covers ::bbp_subscriptions_handler
	 */
	public function test_subscription_request_rejects_array_object_type() {
		$user_id  = $this->factory->user->create( array( 'role' => bbp_get_participant_role() ) );
		$forum_id = $this->factory->forum->create();

		$this->set_current_user( $user_id );
		$_GET['object_id']     = $forum_id;
		$_GET['object_type']   = array( 'post' );
		$_REQUEST['_wpnonce'] = wp_create_nonce( 'toggle-subscription_post_' . $forum_id );

		$this->assertFalse( $this->submit_toggle( 'bbp_subscribe' ) );
		$this->assertFalse( bbp_is_user_subscribed( $user_id, $forum_id ) );
	}

	/**
	 * @covers ::bbp_current_user_can_toggle_engagement
	 */
	public function test_only_readable_topics_and_forums_can_be_toggled() {
		$user_id       = $this->factory->user->create( array( 'role' => bbp_get_participant_role() ) );
		$forum_id      = $this->factory->forum->create();
		$topic_id      = $this->factory->topic->create( array( 'post_parent' => $forum_id, 'topic_meta' => array( 'forum_id' => $forum_id ) ) );
		$hidden_forum  = $this->factory->forum->create( array( 'post_status' => bbp_get_hidden_status_id() ) );
		$hidden_topic  = $this->factory->topic->create( array( 'post_parent' => $hidden_forum, 'topic_meta' => array( 'forum_id' => $hidden_forum ) ) );
		$private_topic = $this->factory->topic->create( array( 'post_parent' => $forum_id, 'post_status' => bbp_get_private_status_id(), 'topic_meta' => array( 'forum_id' => $forum_id ) ) );
		$post_id       = $this->factory->post->create();

		$this->set_current_user( $user_id );

		$this->assertTrue( bbp_current_user_can_toggle_engagement( $topic_id, 'post', 'favorite' ) );
		$this->assertTrue( bbp_current_user_can_toggle_engagement( $topic_id ) );
		$this->assertTrue( bbp_current_user_can_toggle_engagement( $forum_id ) );
		$this->assertFalse( bbp_current_user_can_toggle_engagement( $forum_id, 'post', 'favorite' ) );
		$this->assertFalse( bbp_current_user_can_toggle_engagement( $forum_id, 'user' ) );
		$this->assertFalse( bbp_current_user_can_toggle_engagement( $topic_id, 'term', 'favorite' ) );
		$this->assertFalse( bbp_current_user_can_toggle_engagement( $topic_id, 'post', 'favorite', 'invalid' ) );
		$this->assertFalse( bbp_current_user_can_toggle_engagement( $topic_id, 'post', 'favorite', 'remove', $this->factory->user->create() ) );
		$this->assertFalse( bbp_current_user_can_toggle_engagement( $post_id ) );
		$this->assertFalse( bbp_current_user_can_toggle_engagement( $hidden_forum ) );
		$this->assertFalse( bbp_current_user_can_toggle_engagement( $hidden_topic, 'post', 'favorite' ) );
		$this->assertFalse( bbp_current_user_can_toggle_engagement( $hidden_topic ) );
		$this->assertFalse( bbp_current_user_can_toggle_engagement( $private_topic ) );
		$this->assertFalse( bbp_get_user_subscribe_link( array( 'object_id' => $hidden_forum ) ) );
		$this->assertFalse( bbp_get_user_favorites_link( array( 'object_id' => $hidden_topic ) ) );

		bbp_add_moderator( $hidden_forum, $user_id );
		$this->assertTrue( bbp_current_user_can_toggle_engagement( $hidden_forum ) );
		$this->assertTrue( bbp_current_user_can_toggle_engagement( $hidden_topic ) );
	}

	/**
	 * @covers ::bbp_get_user_subscribe_link
	 */
	public function test_subscription_link_uses_established_nonce_for_theme_package_compatibility() {
		$user_id  = $this->factory->user->create( array( 'role' => bbp_get_participant_role() ) );
		$forum_id = $this->factory->forum->create();
		$topic_id = $this->factory->topic->create( array( 'post_parent' => $forum_id, 'topic_meta' => array( 'forum_id' => $forum_id ) ) );

		$this->set_current_user( $user_id );

		foreach ( array( $forum_id, $topic_id ) as $object_id ) {
			$nonce = wp_create_nonce( 'toggle-subscription_' . $object_id );
			$link  = bbp_get_user_subscribe_link( array( 'object_id' => $object_id ) );

			$this->assertStringContainsString( 'data-bbp-object-type="post"', $link );
			$this->assertStringContainsString( 'data-bbp-nonce="' . $nonce . '"', $link );
			$this->assertStringContainsString( '_wpnonce=' . $nonce, html_entity_decode( $link ) );
		}
		$this->assertStringContainsString( 'id="subscription-toggle"', $link );
	}

	/**
	 * @covers ::bbp_favorites_handler
	 * @covers ::bbp_subscriptions_handler
	 * @covers ::bbp_get_topic_favorite_link
	 * @covers ::bbp_get_topic_subscription_link
	 * @covers ::bbp_get_forum_subscription_link
	 * @dataProvider displayed_user_engagements
	 */
	public function test_keymaster_removes_displayed_user_engagement( $engagement, $object_type ) {
		$keymaster_id   = $this->factory->user->create( array( 'role' => 'subscriber' ) );
		$participant_id = $this->factory->user->create( array( 'role' => bbp_get_participant_role() ) );
		$forum_id       = $this->factory->forum->create();
		$object_id      = ( 'topic' === $object_type )
			? $this->factory->topic->create( array( 'post_parent' => $forum_id, 'topic_meta' => array( 'forum_id' => $forum_id ) ) )
			: $forum_id;
		$old_displayed  = bbpress()->displayed_user;
		$profile_filter = ( 'favorite' === $engagement ) ? 'bbp_is_favorites' : 'bbp_is_subscriptions';

		if ( 'favorite' === $engagement ) {
			bbp_add_user_favorite( $participant_id, $object_id );
		} else {
			bbp_add_user_subscription( $participant_id, $object_id );
		}

		$this->set_keymaster( $keymaster_id );
		bbpress()->displayed_user = get_userdata( $participant_id );
		add_filter( $profile_filter, '__return_true' );

		try {
			if ( 'favorite' === $engagement ) {
				$link   = bbp_get_topic_favorite_link( array( 'object_id' => $object_id ) );
				$action = 'bbp_favorite_remove';
				$nonce  = 'toggle-favorite_' . $object_id;
			} elseif ( 'topic' === $object_type ) {
				$link   = bbp_get_topic_subscription_link( array( 'object_id' => $object_id ) );
				$action = 'bbp_unsubscribe';
				$nonce  = 'toggle-subscription_post_' . $object_id;
			} else {
				$link   = bbp_get_forum_subscription_link( array( 'object_id' => $object_id ) );
				$action = 'bbp_unsubscribe';
				$nonce  = 'toggle-subscription_post_' . $object_id;
			}

			$this->assertStringContainsString( ( 'favorite' === $engagement ) ? 'Unfavorite' : 'Unsubscribe', $link );
			$this->assertStringNotContainsString( 'id="' . $engagement . '-toggle"', $link );

			$_GET['object_id']     = $object_id;
			$_GET['object_type']   = 'post';
			$_REQUEST['_wpnonce'] = wp_create_nonce( $nonce );
			$this->assertTrue( $this->submit_toggle( $action ) );

			if ( 'favorite' === $engagement ) {
				$this->assertFalse( bbp_is_user_favorite( $participant_id, $object_id ) );
				$this->assertFalse( bbp_is_user_favorite( $keymaster_id, $object_id ) );
			} else {
				$this->assertFalse( bbp_is_user_subscribed( $participant_id, $object_id ) );
				$this->assertFalse( bbp_is_user_subscribed( $keymaster_id, $object_id ) );
			}
		} finally {
			remove_filter( $profile_filter, '__return_true' );
			bbpress()->displayed_user = $old_displayed;
		}
	}

	public function displayed_user_engagements() {
		return array(
			array( 'favorite',     'topic' ),
			array( 'subscription', 'topic' ),
			array( 'subscription', 'forum' )
		);
	}

	/**
	 * @covers ::bbp_get_topic_favorite_link
	 * @covers ::bbp_get_topic_subscription_link
	 * @covers ::bbp_get_forum_subscription_link
	 */
	public function test_engagement_links_on_other_profile_views_use_current_user() {
		$keymaster_id   = $this->factory->user->create( array( 'role' => 'subscriber' ) );
		$participant_id = $this->factory->user->create( array( 'role' => bbp_get_participant_role() ) );
		$forum_id       = $this->factory->forum->create();
		$topic_id       = $this->factory->topic->create( array( 'post_parent' => $forum_id, 'topic_meta' => array( 'forum_id' => $forum_id ) ) );
		$old_displayed  = bbpress()->displayed_user;

		$this->set_keymaster( $keymaster_id );
		bbp_add_user_subscription( $participant_id, $forum_id );
		bbp_add_user_subscription( $participant_id, $topic_id );
		bbp_add_user_favorite( $participant_id, $topic_id );
		bbpress()->displayed_user = get_userdata( $participant_id );

		try {
			$favorite_link          = bbp_get_topic_favorite_link( array( 'object_id' => $topic_id ) );
			$topic_subscription_link = bbp_get_topic_subscription_link( array( 'object_id' => $topic_id ) );
			$forum_subscription_link = bbp_get_forum_subscription_link( array( 'object_id' => $forum_id ) );

			$this->assertStringContainsString( '>Favorite<', $favorite_link );
			$this->assertStringContainsString( '>Subscribe<', $topic_subscription_link );
			$this->assertStringContainsString( '>Subscribe<', $forum_subscription_link );
			$this->assertStringContainsString( 'id="favorite-toggle"', $favorite_link );
			$this->assertStringContainsString( 'id="subscription-toggle"', $topic_subscription_link );
			$this->assertStringContainsString( 'id="subscription-toggle"', $forum_subscription_link );
		} finally {
			bbpress()->displayed_user = $old_displayed;
		}
	}

	/**
	 * @coversNothing
	 */
	public function test_keymaster_sees_displayed_user_engagement_actions() {
		$keymaster_id    = $this->factory->user->create( array( 'role' => 'subscriber' ) );
		$participant_id  = $this->factory->user->create( array( 'role' => bbp_get_participant_role() ) );
		$forum_id        = $this->factory->forum->create();
		$topic_id        = $this->factory->topic->create( array( 'post_parent' => $forum_id, 'topic_meta' => array( 'forum_id' => $forum_id ) ) );
		$old_displayed   = bbpress()->displayed_user;
		$old_forum_query = bbpress()->forum_query;
		$old_topic_query = bbpress()->topic_query;

		$this->set_keymaster( $keymaster_id );
		bbp_add_user_subscription( $participant_id, $forum_id );
		bbp_add_user_subscription( $participant_id, $topic_id );
		bbp_add_user_favorite( $participant_id, $topic_id );
		bbpress()->displayed_user           = get_userdata( $participant_id );
		bbpress()->forum_query              = new WP_Query();
		bbpress()->forum_query->in_the_loop = true;
		bbpress()->forum_query->post        = get_post( $forum_id );
		bbpress()->topic_query              = new WP_Query();
		bbpress()->topic_query->in_the_loop = true;
		bbpress()->topic_query->post        = get_post( $topic_id );
		add_filter( 'bbp_is_subscriptions', '__return_true' );

		try {
			ob_start();
			include BBP_PLUGIN_DIR . 'templates/default/bbpress/loop-single-forum.php';
			$forum_action = ob_get_clean();
			ob_start();
			include BBP_PLUGIN_DIR . 'templates/default/bbpress/loop-single-topic.php';
			$topic_subscription_action = ob_get_clean();

			remove_filter( 'bbp_is_subscriptions', '__return_true' );
			add_filter( 'bbp_is_favorites', '__return_true' );
			ob_start();
			include BBP_PLUGIN_DIR . 'templates/default/bbpress/loop-single-topic.php';
			$topic_favorite_action = ob_get_clean();

			$this->assertStringContainsString( '&times;', $forum_action );
			$this->assertStringContainsString( '&times;', $topic_subscription_action );
			$this->assertStringContainsString( '&times;', $topic_favorite_action );
			$this->assertStringNotContainsString( 'id="subscription-toggle"', $forum_action . $topic_subscription_action );
			$this->assertStringNotContainsString( 'id="favorite-toggle"', $topic_favorite_action );
		} finally {
			remove_filter( 'bbp_is_subscriptions', '__return_true' );
			remove_filter( 'bbp_is_favorites', '__return_true' );
			bbpress()->displayed_user = $old_displayed;
			bbpress()->forum_query    = $old_forum_query;
			bbpress()->topic_query    = $old_topic_query;
		}
	}

	/**
	 * @covers ::bbp_subscriptions_handler
	 */
	public function test_participant_cannot_remove_displayed_user_subscription() {
		$participant_id = $this->factory->user->create( array( 'role' => bbp_get_participant_role() ) );
		$target_id      = $this->factory->user->create( array( 'role' => bbp_get_participant_role() ) );
		$forum_id       = $this->factory->forum->create();
		$old_displayed  = bbpress()->displayed_user;

		bbp_add_user_subscription( $target_id, $forum_id );
		$this->set_current_user( $participant_id );
		bbpress()->displayed_user = get_userdata( $target_id );
		add_filter( 'bbp_is_subscriptions', '__return_true' );
		$_GET['object_id']     = $forum_id;
		$_GET['object_type']   = 'post';
		$_REQUEST['_wpnonce'] = wp_create_nonce( 'toggle-subscription_post_' . $forum_id );

		try {
			$this->assertFalse( $this->submit_toggle( 'bbp_unsubscribe' ) );
			$this->assertTrue( bbp_is_user_subscribed( $target_id, $forum_id ) );
		} finally {
			remove_filter( 'bbp_is_subscriptions', '__return_true' );
			bbpress()->displayed_user = $old_displayed;
		}
	}

	/**
	 * @covers ::bbp_favorites_handler
	 */
	public function test_participant_cannot_remove_displayed_user_favorite() {
		$participant_id = $this->factory->user->create( array( 'role' => bbp_get_participant_role() ) );
		$target_id      = $this->factory->user->create( array( 'role' => bbp_get_participant_role() ) );
		$forum_id       = $this->factory->forum->create();
		$topic_id       = $this->factory->topic->create( array( 'post_parent' => $forum_id, 'topic_meta' => array( 'forum_id' => $forum_id ) ) );
		$old_displayed  = bbpress()->displayed_user;

		bbp_add_user_favorite( $target_id, $topic_id );
		$this->set_current_user( $participant_id );
		bbpress()->displayed_user = get_userdata( $target_id );
		add_filter( 'bbp_is_favorites', '__return_true' );
		$_GET['object_id']     = $topic_id;
		$_REQUEST['_wpnonce'] = wp_create_nonce( 'toggle-favorite_' . $topic_id );

		try {
			$this->assertFalse( $this->submit_toggle( 'bbp_favorite_remove' ) );
			$this->assertTrue( bbp_is_user_favorite( $target_id, $topic_id ) );
		} finally {
			remove_filter( 'bbp_is_favorites', '__return_true' );
			bbpress()->displayed_user = $old_displayed;
		}
	}

	/**
	 * @covers ::bbp_subscriptions_handler
	 * @dataProvider subscription_nonce_actions
	 */
	public function test_subscription_accepts_unprefixed_and_post_scoped_nonce( $nonce_prefix ) {
		$user_id  = $this->factory->user->create( array( 'role' => bbp_get_participant_role() ) );
		$forum_id = $this->factory->forum->create();

		$this->set_current_user( $user_id );
		$_GET['object_id']     = $forum_id;
		$_GET['object_type']   = 'post';
		$_REQUEST['_wpnonce'] = wp_create_nonce( $nonce_prefix . $forum_id );

		$this->assertTrue( $this->submit_toggle( 'bbp_subscribe' ) );
		$this->assertTrue( bbp_is_user_subscribed( $user_id, $forum_id ) );
	}

	public function subscription_nonce_actions() {
		return array(
			array( 'toggle-subscription_post_' ),
			array( 'toggle-subscription_' )
		);
	}

	/**
	 * @covers ::bbp_favorites_handler
	 */
	public function test_favorite_request_rejects_topic_in_unreadable_forum() {
		$user_id  = $this->factory->user->create( array( 'role' => bbp_get_participant_role() ) );
		$forum_id = $this->factory->forum->create( array( 'post_status' => bbp_get_hidden_status_id() ) );
		$topic_id = $this->factory->topic->create( array( 'post_parent' => $forum_id, 'topic_meta' => array( 'forum_id' => $forum_id ) ) );

		$this->set_current_user( $user_id );
		$_GET['object_id']     = $topic_id;
		$_REQUEST['_wpnonce'] = wp_create_nonce( 'toggle-favorite_' . $topic_id );

		$this->assertFalse( $this->submit_toggle( 'bbp_favorite_add' ) );
		$this->assertFalse( bbp_is_user_favorite( $user_id, $topic_id ) );
	}

	/**
	 * @covers ::bbp_current_user_can_toggle_engagement
	 * @covers ::bbp_subscriptions_handler
	 * @covers ::bbp_favorites_handler
	 */
	public function test_existing_engagements_can_be_removed_after_forum_becomes_unreadable() {
		$user_id  = $this->factory->user->create( array( 'role' => bbp_get_participant_role() ) );
		$forum_id = $this->factory->forum->create();
		$topic_id = $this->factory->topic->create( array( 'post_parent' => $forum_id, 'topic_meta' => array( 'forum_id' => $forum_id ) ) );

		$this->set_current_user( $user_id );
		$this->assertTrue( bbp_add_user_subscription( $user_id, $forum_id ) );
		$this->assertTrue( bbp_add_user_favorite( $user_id, $topic_id ) );
		wp_update_post( array( 'ID' => $forum_id, 'post_status' => bbp_get_hidden_status_id() ) );

		$this->assertFalse( bbp_current_user_can_toggle_engagement( $forum_id ) );
		$this->assertFalse( bbp_current_user_can_toggle_engagement( $topic_id, 'post', 'favorite' ) );
		$this->assertTrue( bbp_current_user_can_toggle_engagement( $forum_id, 'post', 'subscription', 'remove' ) );
		$this->assertTrue( bbp_current_user_can_toggle_engagement( $topic_id, 'post', 'favorite', 'remove' ) );
		$this->assertNotFalse( bbp_get_user_subscribe_link( array( 'object_id' => $forum_id ) ) );
		$this->assertNotFalse( bbp_get_user_favorites_link( array( 'object_id' => $topic_id ) ) );

		$_GET['object_id']     = $forum_id;
		$_GET['object_type']   = 'post';
		$_REQUEST['_wpnonce'] = wp_create_nonce( 'toggle-subscription_post_' . $forum_id );
		$this->assertTrue( $this->submit_toggle( 'bbp_unsubscribe' ) );
		$this->assertFalse( bbp_is_user_subscribed( $user_id, $forum_id ) );

		$_GET['object_id']     = $topic_id;
		$_REQUEST['_wpnonce'] = wp_create_nonce( 'toggle-favorite_' . $topic_id );
		$this->assertTrue( $this->submit_toggle( 'bbp_favorite_remove' ) );
		$this->assertFalse( bbp_is_user_favorite( $user_id, $topic_id ) );
	}
}
