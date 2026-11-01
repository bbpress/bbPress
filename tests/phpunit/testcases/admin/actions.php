<?php

/**
 * Tests for admin action wrappers and helpers.
 *
 * @group admin
 */
class BBP_Tests_Admin_Actions extends BBP_UnitTestCase {

	public function setUp(): void {
		parent::setUp();

		if ( ! function_exists( 'bbp_admin' ) ) {
			require_once BBP_PLUGIN_DIR . 'includes/admin/actions.php';
		}
	}

	/**
	 * @covers ::bbp_admin
	 */
	public function test_bbp_admin_returns_the_admin_singleton() {
		$this->assertInstanceOf( 'BBP_Admin', bbp_admin() );
		$this->assertSame( bbp_admin(), bbp_admin() );
	}

	/**
	 * @covers ::bbp_filter_column_headers
	 */
	public function test_column_headers_remain_unchanged_by_default() {
		$columns = array(
			'bbp_forum_topic_count' => 'Topic count',
			'custom'                => 'Custom',
		);

		$this->assertSame( $columns, bbp_filter_column_headers( $columns ) );
	}

	/**
	 * @covers ::bbp_filter_column_headers
	 */
	public function test_column_header_icons_are_opt_in_and_preserve_other_columns() {
		$filter  = '__return_true';
		$columns = array(
			'bbp_forum_topic_count' => 'Forum topics',
			'bbp_forum_reply_count' => 'Forum replies',
			'bbp_topic_forum'       => 'Topic forum',
			'bbp_topic_reply_count' => 'Topic replies',
			'bbp_reply_forum'       => 'Reply forum',
			'bbp_reply_topic'       => 'Reply topic',
			'custom'                => 'Custom',
		);

		add_filter( 'bbp_filter_column_headers', $filter );

		try {
			$filtered = bbp_filter_column_headers( $columns );
			$this->assertSame( array_keys( $columns ), array_keys( $filtered ) );
			$this->assertStringContainsString( '<span class="screen-reader-text">Topics</span>', $filtered['bbp_forum_topic_count'] );
			$this->assertStringContainsString( '<span class="screen-reader-text">Replies</span>', $filtered['bbp_forum_reply_count'] );
			$this->assertStringContainsString( '<span class="screen-reader-text">Forum</span>', $filtered['bbp_topic_forum'] );
			$this->assertStringContainsString( '<span class="screen-reader-text">Replies</span>', $filtered['bbp_topic_reply_count'] );
			$this->assertStringContainsString( '<span class="screen-reader-text">Forum</span>', $filtered['bbp_reply_forum'] );
			$this->assertStringContainsString( '<span class="screen-reader-text">Topic</span>', $filtered['bbp_reply_topic'] );
			$this->assertSame( 'Custom', $filtered['custom'] );
		} finally {
			remove_filter( 'bbp_filter_column_headers', $filter );
		}
	}

	/**
	 * @covers ::bbp_filter_sample_permalink
	 */
	public function test_sample_permalink_decodes_forum_links_only_in_admin() {
		$old_post   = isset( $GLOBALS['post'] ) ? $GLOBALS['post'] : null;
		$old_screen = isset( $GLOBALS['current_screen'] ) ? $GLOBALS['current_screen'] : null;
		$forum_id   = $this->factory->forum->create();
		$post_type  = 'bbp_test_type';
		$link       = 'http://example.org/forums/%e6%97%a5%e6%9c%ac%e8%aa%9e/';
		register_post_type( $post_type, array( 'public' => true ) );
		$post_id = $this->factory->post->create( array( 'post_type' => $post_type ) );

		try {
			$GLOBALS['post']           = get_post( $forum_id );
			$GLOBALS['current_screen'] = WP_Screen::get( 'front' );
			$this->assertSame( $link, bbp_filter_sample_permalink( $link, get_post( $forum_id ), false, true ) );

			$GLOBALS['current_screen'] = WP_Screen::get( 'post' );
			$this->assertSame( urldecode( $link ), bbp_filter_sample_permalink( $link, get_post( $forum_id ), false, true ) );
			$this->assertSame( $link, bbp_filter_sample_permalink( $link, get_post( $forum_id ), false, false ) );

			$GLOBALS['post'] = get_post( $post_id );
			$this->assertSame( $link, bbp_filter_sample_permalink( $link, get_post( $post_id ), false, true ) );
		} finally {
			unregister_post_type( $post_type );
			$GLOBALS['post']           = $old_post;
			$GLOBALS['current_screen'] = $old_screen;
		}
	}

	/**
	 * @covers ::bbp_admin_init
	 * @covers ::bbp_admin_menu
	 * @covers ::bbp_admin_head
	 * @covers ::bbp_admin_notices
	 * @covers ::bbp_register_importers
	 * @covers ::bbp_register_admin_scripts
	 * @covers ::bbp_register_admin_settings
	 * @covers ::bbp_admin_tool_box
	 * @covers ::bbp_admin_load_tools_repair_upgrade
	 *
	 * @dataProvider admin_action_provider
	 */
	public function test_admin_action_wrappers( $function, $hook ) {
		$called   = 0;
		$listener = function () use ( &$called ) {
			$called++;
		};

		remove_all_actions( $hook );
		add_action( $hook, $listener, 999 );

		try {
			call_user_func( $function );
			$this->assertSame( 1, $called );
		} finally {
			remove_action( $hook, $listener, 999 );
		}
	}

	public static function admin_action_provider() {
		return array(
			'admin init'         => array( 'bbp_admin_init', 'bbp_admin_init' ),
			'admin menu'         => array( 'bbp_admin_menu', 'bbp_admin_menu' ),
			'admin head'         => array( 'bbp_admin_head', 'bbp_admin_head' ),
			'admin notices'      => array( 'bbp_admin_notices', 'bbp_admin_notices' ),
			'importers'          => array( 'bbp_register_importers', 'bbp_register_importers' ),
			'admin scripts'      => array( 'bbp_register_admin_scripts', 'bbp_register_admin_scripts' ),
			'admin settings'     => array( 'bbp_register_admin_settings', 'bbp_register_admin_settings' ),
			'admin tool box'     => array( 'bbp_admin_tool_box', 'bbp_admin_tool_box' ),
			'repair upgrade'     => array( 'bbp_admin_load_tools_repair_upgrade', 'bbp_admin_load_tools_repair_upgrade' ),
		);
	}

	/**
	 * @covers ::bbp_register_admin_styles
	 */
	public function test_register_admin_styles_runs_legacy_and_current_actions() {
		$called  = array();
		$legacy  = function () use ( &$called ) {
			$called[] = 'legacy';
		};
		$current = function () use ( &$called ) {
			$called[] = 'current';
		};

		remove_all_actions( 'bbp_register_admin_style' );
		remove_all_actions( 'bbp_register_admin_styles' );
		add_action( 'bbp_register_admin_style', $legacy, 999 );
		add_action( 'bbp_register_admin_styles', $current, 999 );

		try {
			bbp_register_admin_styles();
			$this->assertSame( array( 'legacy', 'current' ), $called );
		} finally {
			remove_action( 'bbp_register_admin_style', $legacy, 999 );
			remove_action( 'bbp_register_admin_styles', $current, 999 );
		}
	}

	/**
	 * @covers ::bbp_current_screen
	 */
	public function test_current_screen_action_receives_the_screen() {
		$received = null;
		$screen   = WP_Screen::get( 'tools_page_bbp-repair' );
		$listener = function ( $current_screen ) use ( &$received ) {
			$received = $current_screen;
		};

		remove_all_actions( 'bbp_current_screen' );
		add_action( 'bbp_current_screen', $listener, 999 );

		try {
			bbp_current_screen( $screen );
			$this->assertSame( $screen, $received );
		} finally {
			remove_action( 'bbp_current_screen', $listener, 999 );
		}
	}

	/**
	 * @covers ::bbp_new_site
	 */
	public function test_new_site_stops_when_bbpress_is_not_network_active() {
		$called   = 0;
		$listener = function () use ( &$called ) {
			$called++;
		};

		add_action( 'bbp_new_site', $listener, 999 );

		try {
			bbp_new_site( get_current_blog_id(), 0, '', '', 0, array() );
			$this->assertSame( 0, $called );
		} finally {
			remove_action( 'bbp_new_site', $listener, 999 );
		}
	}

	/**
	 * @covers ::bbp_new_site
	 */
	public function test_new_site_fires_its_action_on_the_switched_site() {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'This test requires multisite.' );
		}

		$old_site_id = get_current_blog_id();
		$active      = get_site_option( 'active_sitewide_plugins', array() );
		remove_action( 'wpmu_new_blog', 'bbp_new_site', 10 );
		$site_id     = self::factory()->blog->create();
		$received    = null;
		$listener    = function ( $blog_id ) use ( &$received ) {
			$received = array( $blog_id, get_current_blog_id() );
		};

		$network_plugins                        = $active;
		$network_plugins[ bbpress()->basename ] = time();
		update_site_option( 'active_sitewide_plugins', $network_plugins );
		remove_all_actions( 'bbp_new_site' );
		add_action( 'bbp_new_site', $listener, 999, 6 );

		try {
			bbp_new_site( $site_id, 1, 'example.org', '/site/', 1, array( 'public' => 1 ) );
			$this->assertSame( array( $site_id, $site_id ), $received );
			$this->assertSame( $old_site_id, get_current_blog_id() );
		} finally {
			remove_action( 'bbp_new_site', $listener, 999 );
			update_site_option( 'active_sitewide_plugins', $active );

			while ( ms_is_switched() ) {
				restore_current_blog();
			}
		}
	}
}
