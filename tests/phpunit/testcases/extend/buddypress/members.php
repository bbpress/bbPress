<?php

/**
 * BuddyPress Member Forum tests.
 *
 * @group extend
 * @group buddypress
 * @group members
 */
class BBP_Tests_Extend_BuddyPress_Members extends BBP_UnitTestCase {

	protected $displayed_user;
	protected $current_action;
	protected $members;
	protected $old_get;
	protected $old_request;
	protected $old_server;
	protected $old_errors;
	protected $network_user_managers = array();

	public function setUp(): void {
		parent::setUp();

		$this->displayed_user = isset( buddypress()->displayed_user )
			? buddypress()->displayed_user
			: null;
		$this->current_action = buddypress()->current_action;
		$this->members        = ( new ReflectionClass( 'BBP_BuddyPress_Members' ) )->newInstanceWithoutConstructor();
		$this->old_get        = $_GET;
		$this->old_request    = $_REQUEST;
		$this->old_server     = $_SERVER;
		$this->old_errors     = bbpress()->errors;
		$home_url             = wp_parse_url( home_url( '/' ) );

		bbpress()->errors       = new WP_Error();
		$_SERVER['HTTP_HOST']   = $home_url['host'];
		$_SERVER['REQUEST_URI'] = $home_url['path'];

		if ( isset( $home_url['port'] ) ) {
			$_SERVER['HTTP_HOST']  .= ':' . $home_url['port'];
			$_SERVER['SERVER_PORT'] = $home_url['port'];
		}
	}

	public function tearDown(): void {
		foreach ( $this->network_user_managers as $user_id ) {
			get_userdata( $user_id )->remove_cap( 'manage_network_users' );
		}
		$this->network_user_managers = array();

		if ( null === $this->displayed_user ) {
			unset( buddypress()->displayed_user );
		} else {
			buddypress()->displayed_user = $this->displayed_user;
		}

		buddypress()->current_action = $this->current_action;
		$this->clear_user_forum_query_vars();
		$this->set_permalink_structure();
		$_GET              = $this->old_get;
		$_REQUEST          = $this->old_request;
		$_SERVER           = $this->old_server;
		bbpress()->errors  = $this->old_errors;

		parent::tearDown();
	}

	/**
	 * @dataProvider profile_sections
	 * @covers ::BBP_BuddyPress_Members::set_member_forum_query_vars
	 */
	public function test_sets_query_var_for_another_members_profile( $slug_callback, $query_var ) {
		$displayed_user_id = $this->factory->user->create();
		$logged_in_user_id = $this->factory->user->create();

		$this->set_current_user( $logged_in_user_id );
		buddypress()->displayed_user = (object) array( 'id' => $displayed_user_id );
		buddypress()->current_action = call_user_func( $slug_callback );

		$this->assertFalse( bp_is_my_profile() );
		$this->assertSame( $logged_in_user_id, bp_loggedin_user_id() );
		$this->members->set_member_forum_query_vars();

		$this->assertTrue( bbp_get_wp_query()->$query_var );
	}

	/**
	 * @covers ::BBP_BuddyPress_Members::set_member_forum_query_vars
	 * @covers ::bbp_get_topics_pagination_base
	 */
	public function test_anonymous_topics_pagination_uses_displayed_members_section() {
		$this->set_anonymous_member_section( 'bbp_get_topic_archive_slug' );
		$this->set_permalink_structure( '/%postname%/' );

		$topics_url = function () {
			return 'https://example.org/members/target/forums/topics/';
		};

		add_filter( 'bbp_pre_get_user_topics_created_url', $topics_url, 99 );
		try {
			$this->members->set_member_forum_query_vars();

			$this->assertSame(
				'https://example.org/members/target/forums/topics/page/%#%/',
				bbp_get_topics_pagination_base()
			);
		} finally {
			remove_filter( 'bbp_pre_get_user_topics_created_url', $topics_url, 99 );
		}
	}

	/**
	 * @covers ::BBP_BuddyPress_Members::set_member_forum_query_vars
	 * @covers ::bbp_get_replies_pagination_base
	 */
	public function test_anonymous_replies_pagination_uses_displayed_members_section() {
		$this->set_anonymous_member_section( 'bbp_get_reply_archive_slug' );
		$this->set_permalink_structure( '/%postname%/' );

		$replies_url = function () {
			return 'https://example.org/members/target/forums/replies/';
		};

		add_filter( 'bbp_pre_get_user_replies_created_url', $replies_url, 99 );
		try {
			$this->members->set_member_forum_query_vars();

			$this->assertSame(
				'https://example.org/members/target/forums/replies/page/%#%/',
				bbp_get_replies_pagination_base()
			);
		} finally {
			remove_filter( 'bbp_pre_get_user_replies_created_url', $replies_url, 99 );
		}
	}

	/**
	 * @covers ::BBP_BuddyPress_Members::set_member_forum_query_vars
	 */
	public function test_ignores_non_user_pages() {
		unset( buddypress()->displayed_user );
		buddypress()->current_action = bbp_get_topic_archive_slug();

		$this->members->set_member_forum_query_vars();

		$this->assertFalse( isset( bbp_get_wp_query()->bbp_is_single_user_topics ) );
	}

	/**
	 * @covers ::BBP_BuddyPress_Members::setup_actions
	 */
	public function test_member_engagement_handler_is_registered_on_buddypress_actions() {
		$members = bbpress()->extend->buddypress->members;

		$this->assertSame( 1, has_action( 'bp_actions', array( $members, 'engagements_handler' ) ) );
		$this->assertFalse( has_action( 'bp_actions', 'bbp_favorites_handler' ) );
		$this->assertFalse( has_action( 'bp_actions', 'bbp_subscriptions_handler' ) );
	}

	/**
	 * @covers ::BBP_BuddyPress_Members::engagements_handler
	 * @covers ::bbp_subscriptions_handler
	 */
	public function test_member_subscription_can_be_removed_from_buddypress_profile() {
		$user_id  = $this->factory->user->create( array( 'role' => bbp_get_participant_role() ) );
		$forum_id = $this->factory->forum->create();

		$this->set_current_user( $user_id );
		buddypress()->displayed_user = (object) array( 'id' => $user_id );
		buddypress()->current_action = bbp_get_user_subscriptions_slug();
		$this->assertTrue( bbp_add_user_subscription( $user_id, $forum_id ) );

		$_GET['action']        = 'bbp_unsubscribe';
		$_GET['object_id']     = $forum_id;
		$_GET['object_type']   = 'post';
		$_REQUEST['_wpnonce'] = wp_create_nonce( 'toggle-subscription_post_' . $forum_id );

		$this->submit_member_engagement();

		$this->assertFalse( bbp_is_user_subscribed( $user_id, $forum_id ) );
	}

	/**
	 * @covers ::BBP_BuddyPress_Members::engagements_handler
	 * @covers ::bbp_favorites_handler
	 */
	public function test_member_favorite_can_be_removed_from_buddypress_profile() {
		$user_id  = $this->factory->user->create( array( 'role' => bbp_get_participant_role() ) );
		$forum_id = $this->factory->forum->create();
		$topic_id = $this->factory->topic->create( array(
			'post_parent' => $forum_id,
			'topic_meta'  => array( 'forum_id' => $forum_id ),
		) );

		$this->set_current_user( $user_id );
		buddypress()->displayed_user = (object) array( 'id' => $user_id );
		buddypress()->current_action = bbp_get_user_favorites_slug();
		$this->assertTrue( bbp_add_user_favorite( $user_id, $topic_id ) );

		$_GET['action']        = 'bbp_favorite_remove';
		$_GET['object_id']     = $topic_id;
		$_REQUEST['_wpnonce'] = wp_create_nonce( 'toggle-favorite_' . $topic_id );

		$this->submit_member_engagement();

		$this->assertFalse( bbp_is_user_favorite( $user_id, $topic_id ) );
	}

	/**
	 * @covers ::BBP_BuddyPress_Members::engagements_handler
	 * @covers ::bbp_subscriptions_handler
	 */
	public function test_keymaster_removes_displayed_members_subscription_from_buddypress_profile() {
		$keymaster_id = $this->factory->user->create( array( 'role' => 'administrator' ) );
		$member_id    = $this->factory->user->create( array( 'role' => bbp_get_participant_role() ) );
		$forum_id     = $this->factory->forum->create();

		bbp_set_user_role( $keymaster_id, bbp_get_keymaster_role() );
		$this->grant_manage_network_users_on_multisite( $keymaster_id );
		$this->set_current_user( $keymaster_id );
		buddypress()->displayed_user = (object) array( 'id' => $member_id );
		buddypress()->current_action = bbp_get_user_subscriptions_slug();
		$this->assertTrue( bbp_add_user_subscription( $keymaster_id, $forum_id ) );
		$this->assertTrue( bbp_add_user_subscription( $member_id, $forum_id ) );

		$_GET['action']        = 'bbp_unsubscribe';
		$_GET['object_id']     = $forum_id;
		$_GET['object_type']   = 'post';
		$_REQUEST['_wpnonce'] = wp_create_nonce( 'toggle-subscription_post_' . $forum_id );

		$this->submit_member_engagement();

		$this->assertTrue( bbp_is_user_subscribed( $keymaster_id, $forum_id ) );
		$this->assertFalse( bbp_is_user_subscribed( $member_id, $forum_id ) );
	}

	/**
	 * @covers ::BBP_BuddyPress_Members::engagements_handler
	 * @covers ::bbp_favorites_handler
	 */
	public function test_keymaster_removes_displayed_members_favorite_from_buddypress_profile() {
		$keymaster_id = $this->factory->user->create( array( 'role' => 'administrator' ) );
		$member_id    = $this->factory->user->create( array( 'role' => bbp_get_participant_role() ) );
		$forum_id     = $this->factory->forum->create();
		$topic_id     = $this->factory->topic->create( array(
			'post_parent' => $forum_id,
			'topic_meta'  => array( 'forum_id' => $forum_id ),
		) );

		bbp_set_user_role( $keymaster_id, bbp_get_keymaster_role() );
		$this->grant_manage_network_users_on_multisite( $keymaster_id );
		$this->set_current_user( $keymaster_id );
		buddypress()->displayed_user = (object) array( 'id' => $member_id );
		buddypress()->current_action = bbp_get_user_favorites_slug();
		$this->assertTrue( bbp_add_user_favorite( $keymaster_id, $topic_id ) );
		$this->assertTrue( bbp_add_user_favorite( $member_id, $topic_id ) );

		$_GET['action']        = 'bbp_favorite_remove';
		$_GET['object_id']     = $topic_id;
		$_REQUEST['_wpnonce'] = wp_create_nonce( 'toggle-favorite_' . $topic_id );

		$this->submit_member_engagement();

		$this->assertTrue( bbp_is_user_favorite( $keymaster_id, $topic_id ) );
		$this->assertFalse( bbp_is_user_favorite( $member_id, $topic_id ) );
	}

	/**
	 * @covers ::BBP_BuddyPress_Members::engagements_handler
	 */
	public function test_member_engagement_handler_rejects_array_action() {
		$_GET['action'] = array( 'bbp_unsubscribe' );

		$this->assertNull( bbpress()->extend->buddypress->members->engagements_handler() );
		$this->assertFalse( bbp_has_errors() );
	}

	public function profile_sections() {
		return array(
			'topics'        => array( 'bbp_get_topic_archive_slug',       'bbp_is_single_user_topics'      ),
			'replies'       => array( 'bbp_get_reply_archive_slug',       'bbp_is_single_user_replies'     ),
			'engagements'   => array( 'bbp_get_user_engagements_slug',    'bbp_is_single_user_engagements' ),
			'favorites'     => array( 'bbp_get_user_favorites_slug',      'bbp_is_single_user_favs'        ),
			'subscriptions' => array( 'bbp_get_user_subscriptions_slug',  'bbp_is_single_user_subs'        ),
		);
	}

	private function set_anonymous_member_section( $slug_callback ) {
		$this->set_current_user( 0 );
		buddypress()->displayed_user = (object) array( 'id' => $this->factory->user->create() );
		buddypress()->current_action = call_user_func( $slug_callback );
	}

	private function clear_user_forum_query_vars() {
		foreach ( array(
			'bbp_is_single_user_topics',
			'bbp_is_single_user_replies',
			'bbp_is_single_user_engagements',
			'bbp_is_single_user_favs',
			'bbp_is_single_user_subs',
		) as $query_var ) {
			unset( bbp_get_wp_query()->$query_var );
		}
	}

	private function grant_manage_network_users_on_multisite( $user_id ) {
		if ( is_multisite() ) {
			get_userdata( $user_id )->add_cap( 'manage_network_users' );
			$this->network_user_managers[] = $user_id;
		}
	}

	private function submit_member_engagement() {
		$redirected        = false;
		$interrupt_redirect = function() use ( &$redirected ) {
			$redirected = true;
			throw new RuntimeException( 'Engagement redirect.' );
		};
		add_filter( 'wp_redirect', $interrupt_redirect );

		try {
			bbpress()->extend->buddypress->members->engagements_handler();
		} catch ( RuntimeException $exception ) {
			if ( 'Engagement redirect.' !== $exception->getMessage() ) {
				throw $exception;
			}
		} finally {
			remove_filter( 'wp_redirect', $interrupt_redirect );
		}

		$this->assertTrue( $redirected, implode( '; ', bbpress()->errors->get_error_messages() ) );
	}
}
