<?php

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
}
