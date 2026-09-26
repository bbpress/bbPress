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

	public function setUp(): void {
		parent::setUp();

		$this->old_get     = $_GET;
		$this->old_request = $_REQUEST;
		$this->old_server  = $_SERVER;
		$this->old_errors  = bbpress()->errors;
		$home_url          = wp_parse_url( home_url( '/' ) );

		bbpress()->errors       = new WP_Error();
		$_SERVER['HTTP_HOST']   = $home_url['host'];
		$_SERVER['REQUEST_URI'] = $home_url['path'];
	}

	public function tearDown(): void {
		$_GET              = $this->old_get;
		$_REQUEST          = $this->old_request;
		$_SERVER           = $this->old_server;
		bbpress()->errors = $this->old_errors;

		parent::tearDown();
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
	public function test_subscription_link_uses_post_scoped_nonce() {
		$user_id  = $this->factory->user->create( array( 'role' => bbp_get_participant_role() ) );
		$forum_id = $this->factory->forum->create();

		$this->set_current_user( $user_id );
		$link = bbp_get_user_subscribe_link( array( 'object_id' => $forum_id ) );

		$this->assertStringContainsString( 'data-bbp-object-type="post"', $link );
		$this->assertStringContainsString( 'data-bbp-nonce="' . wp_create_nonce( 'toggle-subscription_post_' . $forum_id ) . '"', $link );
	}

	/**
	 * @covers ::bbp_subscriptions_handler
	 * @dataProvider subscription_nonce_actions
	 */
	public function test_subscription_accepts_current_and_legacy_post_nonce( $nonce_prefix ) {
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
