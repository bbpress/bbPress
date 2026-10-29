<?php

class BBP_Tests_Extend_BuddyPress_Functions_Redirect_Exception extends Exception {}

/**
 * BuddyPress integration function tests.
 *
 * @group extend
 * @group buddypress
 */
class BBP_Tests_Extend_BuddyPress_Functions extends BBP_UnitTestCase {

	private $component_id;
	private $displayed_user;
	private $old_user;

	public function setUp(): void {
		parent::setUp();

		$this->component_id   = bbpress()->extend->buddypress->id;
		$this->displayed_user = isset( buddypress()->displayed_user ) ? buddypress()->displayed_user : null;
		$this->old_user       = get_current_user_id();
	}

	public function tearDown(): void {
		bbpress()->extend->buddypress->id = $this->component_id;
		$this->set_current_user( $this->old_user );

		if ( null === $this->displayed_user ) {
			unset( buddypress()->displayed_user );
		} else {
			buddypress()->displayed_user = $this->displayed_user;
		}

		parent::tearDown();
	}

	/**
	 * @covers ::bbp_get_component_name
	 */
	public function test_component_name_uses_registered_id_default_and_filter() {
		bbpress()->extend->buddypress->id = 'community-forums';
		$this->assertSame( 'community-forums', bbp_get_component_name() );

		bbpress()->extend->buddypress->id = '';
		$this->assertSame( 'forums', bbp_get_component_name() );

		$filter = static function () {
			return 'filtered-forums';
		};
		add_filter( 'bbp_get_component_name', $filter );
		try {
			$this->assertSame( 'filtered-forums', bbp_get_component_name() );
		} finally {
			remove_filter( 'bbp_get_component_name', $filter );
		}
	}

	/**
	 * @covers ::bbp_filter_user_id
	 * @covers ::bbp_filter_is_single_user
	 * @covers ::bbp_filter_is_user_home
	 */
	public function test_user_filters_respect_explicit_and_buddypress_users() {
		$displayed_id = $this->factory->user->create();
		$logged_in_id = $this->factory->user->create();

		unset( buddypress()->displayed_user );
		$this->set_current_user( 0 );
		$this->assertFalse( bbp_filter_is_single_user() );
		$this->assertTrue( bbp_filter_is_single_user( true ) );
		$this->assertTrue( bbp_filter_is_user_home( true ) );

		buddypress()->displayed_user = (object) array( 'id' => $displayed_id );
		$this->set_current_user( $logged_in_id );

		$this->assertSame( 42, bbp_filter_user_id( 42, true, true ) );
		$this->assertSame( $displayed_id, bbp_filter_user_id( 0, true, true ) );
		$this->assertSame( $logged_in_id, bbp_filter_user_id( 0, false, true ) );
		$this->assertSame( 0, bbp_filter_user_id( 0, false, false ) );
		$this->assertTrue( bbp_filter_is_single_user() );
		$this->assertFalse( bbp_filter_is_user_home() );

		$this->set_current_user( $displayed_id );
		$this->assertTrue( bbp_filter_is_user_home() );
	}

	/**
	 * @covers ::bbp_member_forums_screen_topics
	 * @covers ::bbp_member_forums_screen_replies
	 * @covers ::bbp_member_forums_screen_engagements
	 * @covers ::bbp_member_forums_screen_favorites
	 * @covers ::bbp_member_forums_screen_subscriptions
	 *
	 * @dataProvider member_screen_provider
	 */
	public function test_member_screens_register_content_and_load_plugin_template( $callback, $content_callback, $filter_name ) {
		$old_post          = isset( $GLOBALS['post'] ) ? $GLOBALS['post'] : null;
		$queries_were_same = $GLOBALS['wp_query'] === $GLOBALS['wp_the_query'];
		$old_query         = clone $GLOBALS['wp_query'];
		$old_the_query     = $queries_were_same ? null : clone $GLOBALS['wp_the_query'];
		$old_theme_compat  = isset( buddypress()->theme_compat ) ? clone buddypress()->theme_compat : null;
		$templates         = null;
		$filter            = static function () {
			return 'members/single/bbpress-test';
		};
		$located          = static function ( $template, $filtered_templates ) use ( &$templates ) {
			$templates = $filtered_templates;
			return '';
		};

		add_filter( $filter_name, $filter );
		add_filter( 'bp_located_template', $located, PHP_INT_MAX, 2 );

		try {
			$callback();
			$this->assertSame( array( 'members/single/bbpress-test.php' ), $templates );
			$this->assertSame( 10, has_action( 'bp_template_content', $content_callback ) );
		} finally {
			remove_action( 'bp_template_content', $content_callback );
			remove_filter( $filter_name, $filter );
			remove_filter( 'bp_located_template', $located, PHP_INT_MAX );
			$GLOBALS['post']        = $old_post;
			$GLOBALS['wp_query']    = $old_query;
			$GLOBALS['wp_the_query'] = $queries_were_same ? $old_query : $old_the_query;

			if ( null === $old_theme_compat ) {
				unset( buddypress()->theme_compat );
			} else {
				buddypress()->theme_compat = $old_theme_compat;
			}
		}
	}

	public static function member_screen_provider() {
		return array(
			'topics'        => array( 'bbp_member_forums_screen_topics', 'bbp_member_forums_topics_content', 'bbp_member_forums_screen_topics' ),
			'replies'       => array( 'bbp_member_forums_screen_replies', 'bbp_member_forums_replies_content', 'bbp_member_forums_screen_replies' ),
			'engagements'   => array( 'bbp_member_forums_screen_engagements', 'bbp_member_forums_engagements_content', 'bbp_member_forums_screen_engagements' ),
			'favorites'     => array( 'bbp_member_forums_screen_favorites', 'bbp_member_forums_favorites_content', 'bbp_member_forums_screen_favorites' ),
			'subscriptions' => array( 'bbp_member_forums_screen_subscriptions', 'bbp_member_forums_subscriptions_content', 'bbp_member_forums_screen_subscriptions' ),
		);
	}

	/**
	 * @covers ::bbp_member_forums_topics_content
	 * @covers ::bbp_member_forums_replies_content
	 * @covers ::bbp_member_forums_engagements_content
	 * @covers ::bbp_member_forums_favorites_content
	 * @covers ::bbp_member_forums_subscriptions_content
	 *
	 * @dataProvider member_content_provider
	 */
	public function test_member_content_loads_expected_user_template( $callback, $name ) {
		$level  = ob_get_level();
		$loaded = null;
		$action = static function ( $slug, $template_name ) use ( &$loaded ) {
			$loaded = array( $slug, $template_name );
		};
		add_action( 'get_template_part_user', $action, 10, 2 );
		add_filter( 'bbp_get_template_part', '__return_empty_array' );

		try {
			ob_start();
			$callback();
			$output = ob_get_clean();

			$this->assertSame( array( 'user', $name ), $loaded );
			$this->assertStringContainsString( '<div id="bbpress-forums" class="bbpress-wrapper">', $output );
		} finally {
			while ( ob_get_level() > $level ) {
				ob_end_clean();
			}

			remove_action( 'get_template_part_user', $action, 10 );
			remove_filter( 'bbp_get_template_part', '__return_empty_array' );
		}
	}

	public static function member_content_provider() {
		return array(
			'topics'        => array( 'bbp_member_forums_topics_content', 'topics-created' ),
			'replies'       => array( 'bbp_member_forums_replies_content', 'replies-created' ),
			'engagements'   => array( 'bbp_member_forums_engagements_content', 'engagements' ),
			'favorites'     => array( 'bbp_member_forums_favorites_content', 'favorites' ),
			'subscriptions' => array( 'bbp_member_forums_subscriptions_content', 'subscriptions' ),
		);
	}

	/**
	 * Group forum root creation requires the request, capability, and nonce.
	 *
	 * @covers ::bbp_maybe_create_group_forum_root
	 * @ticket BBP3706
	 */
	public function test_group_forum_root_creation_validates_request_and_redirects() {
		$old_get        = $_GET;
		$old_root       = get_option( '_bbp_group_forums_root_id', false );
		$participant_id = $this->factory->user->create();
		$keymaster_id   = $this->factory->user->create( array( 'role' => 'administrator' ) );
		$redirect       = null;
		$stop_redirect  = static function ( $location, $status ) use ( &$redirect ) {
			$redirect = compact( 'location', 'status' );
			throw new BBP_Tests_Extend_BuddyPress_Functions_Redirect_Exception( 'Redirect intercepted.' );
		};

		bbp_set_user_role( $participant_id, bbp_get_participant_role() );
		bbp_set_user_role( $keymaster_id, bbp_get_keymaster_role() );
		delete_option( '_bbp_group_forums_root_id' );
		$forum_count = (int) wp_count_posts( bbp_get_forum_post_type() )->publish;

		try {
			$this->set_current_user( $keymaster_id );
			$_GET = array();
			bbp_maybe_create_group_forum_root();
			$this->assertSame( $forum_count, (int) wp_count_posts( bbp_get_forum_post_type() )->publish );

			$this->set_current_user( $participant_id );
			$_GET = array(
				'create'   => 'bbp-group-forum-root',
				'_wpnonce' => wp_create_nonce( '_bbp_group_forums_root_id' ),
			);
			bbp_maybe_create_group_forum_root();
			$this->assertSame( $forum_count, (int) wp_count_posts( bbp_get_forum_post_type() )->publish );

			$this->set_current_user( $keymaster_id );
			$_GET['_wpnonce'] = 'invalid';
			bbp_maybe_create_group_forum_root();
			$this->assertSame( $forum_count, (int) wp_count_posts( bbp_get_forum_post_type() )->publish );

			$_GET['_wpnonce'] = wp_create_nonce( '_bbp_group_forums_root_id' );
			add_filter( 'wp_redirect', $stop_redirect, 1, 2 );
			try {
				bbp_maybe_create_group_forum_root();
				$this->fail( 'Group forum root creation should redirect.' );
			} catch ( BBP_Tests_Extend_BuddyPress_Functions_Redirect_Exception $exception ) {
				$this->assertSame( 'Redirect intercepted.', $exception->getMessage() );
			} finally {
				remove_filter( 'wp_redirect', $stop_redirect, 1 );
			}

			$forum_id = (int) get_option( '_bbp_group_forums_root_id' );
			$this->assertGreaterThan( 0, $forum_id );
			$this->assertSame( $forum_count + 1, (int) wp_count_posts( bbp_get_forum_post_type() )->publish );
			$this->assertSame( 'Group Forums', bbp_get_forum_title( $forum_id ) );
			$this->assertSame( 'category', bbp_get_forum_type( $forum_id ) );
			$this->assertSame( 302, $redirect['status'] );

			$query = array();
			wp_parse_str( wp_parse_url( $redirect['location'], PHP_URL_QUERY ), $query );
			$this->assertSame( array( 'page' => 'bbpress', 'updated' => '1' ), $query );
		} finally {
			$_GET = $old_get;

			if ( false === $old_root ) {
				delete_option( '_bbp_group_forums_root_id' );
			} else {
				update_option( '_bbp_group_forums_root_id', $old_root );
			}
		}
	}

	/**
	 * @covers ::bbp_get_group_forum_ids
	 * @covers ::bbp_update_group_forum_ids
	 * @covers ::bbp_add_forum_id_to_group
	 * @covers ::bbp_remove_forum_id_from_group
	 */
	public function test_group_forum_relationship_helpers_normalize_and_update_ids() {
		$group_id  = $this->bp_factory->group->create();
		$forum_ids = $this->factory->forum->create_many( 3 );

		bbp_update_group_forum_ids( $group_id, array( $forum_ids[0], '0', (string) $forum_ids[1] ) );
		$this->assertSame( array( $forum_ids[0], $forum_ids[1] ), array_values( bbp_get_group_forum_ids( $group_id ) ) );

		bbp_add_forum_id_to_group( $group_id, $forum_ids[2] );
		bbp_add_forum_id_to_group( $group_id, $forum_ids[2] );
		$this->assertSame( $forum_ids, array_values( bbp_get_group_forum_ids( $group_id ) ) );

		bbp_remove_forum_id_from_group( $group_id, $forum_ids[1] );
		bbp_remove_forum_id_from_group( $group_id, $forum_ids[1] );
		$this->assertSame( array( $forum_ids[0], $forum_ids[2] ), array_values( bbp_get_group_forum_ids( $group_id ) ) );
	}

	/**
	 * @covers ::bbp_get_forum_group_ids
	 * @covers ::bbp_update_forum_group_ids
	 * @covers ::bbp_add_group_id_to_forum
	 * @covers ::bbp_remove_group_id_from_forum
	 * @covers ::bbp_is_forum_group_forum
	 */
	public function test_forum_group_relationship_helpers_normalize_and_update_ids() {
		$group_ids = $this->bp_factory->group->create_many( 3 );
		$forum_id  = $this->factory->forum->create();

		$this->assertFalse( bbp_is_forum_group_forum( $forum_id ) );

		bbp_update_forum_group_ids( $forum_id, array( $group_ids[0], '0', (string) $group_ids[1] ) );
		$this->assertSame( array( $group_ids[0], $group_ids[1] ), array_values( bbp_get_forum_group_ids( $forum_id ) ) );
		$this->assertTrue( bbp_is_forum_group_forum( $forum_id ) );

		bbp_add_group_id_to_forum( $forum_id, $group_ids[2] );
		bbp_add_group_id_to_forum( $forum_id, $group_ids[2] );
		$this->assertSame( $group_ids, array_values( bbp_get_forum_group_ids( $forum_id ) ) );

		bbp_remove_group_id_from_forum( $forum_id, $group_ids[1] );
		bbp_remove_group_id_from_forum( $forum_id, $group_ids[1] );
		$this->assertSame( array( $group_ids[0], $group_ids[2] ), array_values( bbp_get_forum_group_ids( $forum_id ) ) );

		bbp_remove_group_id_from_forum( $forum_id, $group_ids[0] );
		bbp_remove_group_id_from_forum( $forum_id, $group_ids[2] );
		$this->assertSame( array(), bbp_get_forum_group_ids( $forum_id ) );
		$this->assertFalse( bbp_is_forum_group_forum( $forum_id ) );
	}

	/**
	 * @covers ::bbp_get_group_forum_ids
	 * @covers ::bbp_update_group_forum_ids
	 * @covers ::bbp_get_forum_group_ids
	 */
	public function test_relationship_helpers_use_current_objects_and_filters() {
		$group_id             = $this->bp_factory->group->create();
		$forum_id             = $this->factory->forum->create();
		$old_group            = isset( buddypress()->groups->current_group ) ? buddypress()->groups->current_group : null;
		$old_current_forum_id = bbpress()->current_forum_id;
		$old_query_name       = bbp_get_query_name();
		$group_filter_args    = null;
		$forum_filter_args    = null;
		$group_filter         = static function ( $forum_ids, $filtered_group_id ) use ( &$group_filter_args ) {
			$group_filter_args = array( $forum_ids, $filtered_group_id );
			$forum_ids[] = 123;
			return $forum_ids;
		};
		$forum_filter         = static function ( $group_ids, $filtered_forum_id ) use ( &$forum_filter_args ) {
			$forum_filter_args = array( $group_ids, $filtered_forum_id );
			$group_ids[] = 456;
			return $group_ids;
		};

		buddypress()->groups->current_group = groups_get_group( $group_id );
		bbpress()->current_forum_id          = $forum_id;
		bbp_set_query_name( 'bbp_single_forum' );
		add_filter( 'bbp_get_group_forum_ids', $group_filter, 10, 2 );
		add_filter( 'bbp_get_forum_group_ids', $forum_filter, 10, 2 );

		try {
			bbp_update_group_forum_ids( 0, array( $forum_id ) );
			bbp_update_forum_group_ids( 0, array( $group_id ) );

			$this->assertSame( array( $forum_id, 123 ), bbp_get_group_forum_ids() );
			$this->assertSame( array( array( $forum_id ), $group_id ), $group_filter_args );
			$this->assertSame( array( $group_id, 456 ), bbp_get_forum_group_ids() );
			$this->assertSame( array( array( $group_id ), $forum_id ), $forum_filter_args );
		} finally {
			remove_filter( 'bbp_get_group_forum_ids', $group_filter, 10 );
			remove_filter( 'bbp_get_forum_group_ids', $forum_filter, 10 );
			bbpress()->current_forum_id = $old_current_forum_id;
			bbp_set_query_name( $old_query_name );

			if ( null === $old_group ) {
				unset( buddypress()->groups->current_group );
			} else {
				buddypress()->groups->current_group = $old_group;
			}
		}
	}

	/**
	 * @covers ::bbp_group_is_admin
	 * @covers ::bbp_group_is_mod
	 * @covers ::bbp_group_is_member
	 * @covers ::bbp_group_is_banned
	 * @covers ::bbp_group_is_creator
	 */
	public function test_group_member_status_helpers_reflect_current_user_membership() {
		$creator_id        = $this->factory->user->create();
		$moderator_id      = $this->factory->user->create();
		$member_id         = $this->factory->user->create();
		$banned_id         = $this->factory->user->create();
		$outsider_id       = $this->factory->user->create();
		$group_id          = $this->bp_factory->group->create( array( 'creator_id' => $creator_id ) );
		$group             = groups_get_group( $group_id );
		$old_group         = isset( buddypress()->groups->current_group ) ? buddypress()->groups->current_group : null;
		$old_component     = buddypress()->current_component;
		$old_item          = buddypress()->current_item;
		$old_single_item   = isset( buddypress()->is_single_item ) ? buddypress()->is_single_item : null;

		groups_join_group( $group_id, $moderator_id );
		groups_promote_member( $moderator_id, $group_id, 'mod', $creator_id );
		groups_join_group( $group_id, $member_id );
		groups_join_group( $group_id, $banned_id );
		groups_ban_member( $banned_id, $group_id, $creator_id );

		try {
			buddypress()->groups->current_group = $group;
			buddypress()->current_component     = 'groups';
			buddypress()->current_item          = $group->slug;
			buddypress()->is_single_item        = true;
			$this->assertTrue( bp_is_group() );

			$this->set_current_user( 0 );
			$this->reset_group_member_status_cache();
			$this->assertFalse( bbp_group_is_admin() );
			$this->assertFalse( bbp_group_is_mod() );
			$this->assertFalse( bbp_group_is_member() );
			$this->assertFalse( bbp_group_is_banned() );
			$this->assertFalse( bbp_group_is_creator() );

			$expectations = array(
				$creator_id   => array( true, false, true, false, true ),
				$moderator_id => array( false, true, true, false, false ),
				$member_id    => array( false, false, true, false, false ),
				$banned_id    => array( false, false, false, true, false ),
				$outsider_id  => array( false, false, false, false, false ),
			);

			foreach ( $expectations as $user_id => $expected ) {
				$this->set_current_user( $user_id );
				$this->reset_group_member_status_cache();

				$this->assertSame( $expected[0], bbp_group_is_admin() );
				$this->assertSame( $expected[1], bbp_group_is_mod() );
				$this->assertSame( $expected[2], bbp_group_is_member() );
				$this->assertSame( $expected[3], bbp_group_is_banned() );
				$this->assertSame( $expected[4], bbp_group_is_creator() );
			}
		} finally {
			$this->set_current_user( $this->old_user );
			$this->reset_group_member_status_cache();
			buddypress()->current_component = $old_component;
			buddypress()->current_item      = $old_item;

			if ( null === $old_group ) {
				unset( buddypress()->groups->current_group );
			} else {
				buddypress()->groups->current_group = $old_group;
			}

			if ( null === $old_single_item ) {
				unset( buddypress()->is_single_item );
			} else {
				buddypress()->is_single_item = $old_single_item;
			}
		}
	}

	private function reset_group_member_status_cache() {
		unset(
			bbpress()->current_user->is_group_admin,
			bbpress()->current_user->is_group_mod,
			bbpress()->current_user->is_group_member,
			bbpress()->current_user->is_group_banned,
			bbpress()->current_user->is_group_creator
		);
	}
}
