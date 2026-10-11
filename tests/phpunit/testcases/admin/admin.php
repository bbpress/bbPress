<?php

/**
 * Tests for the main administration class.
 *
 * @group admin
 */
class BBP_Tests_Admin_Admin extends BBP_UnitTestCase {

	private $get;
	private $request;
	private $notices;
	private $pending_upgrades;
	private $had_pending_upgrades;
	private $screen;
	private $had_screen;
	private $menu;
	private $had_menu;
	private $typenow;
	private $had_typenow;
	private $taxnow;
	private $had_taxnow;

	public function setUp(): void {
		parent::setUp();

		if ( ! function_exists( 'bbp_admin' ) ) {
			require_once BBP_PLUGIN_DIR . 'includes/admin/actions.php';
		}
		bbp_admin();
		$setup_actions = new ReflectionMethod( 'BBP_Admin', 'setup_actions' );
		if ( PHP_VERSION_ID < 80100 ) {
			$setup_actions->setAccessible( true );
		}
		$setup_actions->invoke( bbp_admin() );

		$this->get                  = $_GET;
		$this->request              = $_REQUEST;
		$this->notices              = bbp_admin()->notices;
		$this->pending_upgrades     = get_option( '_bbp_db_pending_upgrades', false );
		$this->had_pending_upgrades = false !== $this->pending_upgrades;
		$this->had_screen           = array_key_exists( 'current_screen', $GLOBALS );
		$this->screen               = $this->had_screen ? $GLOBALS['current_screen'] : null;
		$this->had_menu             = array_key_exists( 'menu', $GLOBALS );
		$this->menu                 = $this->had_menu ? $GLOBALS['menu'] : null;
		$this->had_typenow          = array_key_exists( 'typenow', $GLOBALS );
		$this->typenow              = $this->had_typenow ? $GLOBALS['typenow'] : null;
		$this->had_taxnow           = array_key_exists( 'taxnow', $GLOBALS );
		$this->taxnow               = $this->had_taxnow ? $GLOBALS['taxnow'] : null;
	}

	public function tearDown(): void {
		$_GET                = $this->get;
		$_REQUEST            = $this->request;
		bbp_admin()->notices = $this->notices;
		if ( $this->had_pending_upgrades ) {
			update_option( '_bbp_db_pending_upgrades', $this->pending_upgrades );
		} else {
			delete_option( '_bbp_db_pending_upgrades' );
		}
		if ( $this->had_screen ) {
			$GLOBALS['current_screen'] = $this->screen;
		} else {
			unset( $GLOBALS['current_screen'] );
		}
		if ( $this->had_menu ) {
			$GLOBALS['menu'] = $this->menu;
		} else {
			unset( $GLOBALS['menu'] );
		}
		if ( $this->had_typenow ) {
			$GLOBALS['typenow'] = $this->typenow;
		} else {
			unset( $GLOBALS['typenow'] );
		}
		if ( $this->had_taxnow ) {
			$GLOBALS['taxnow'] = $this->taxnow;
		} else {
			unset( $GLOBALS['taxnow'] );
		}

		parent::tearDown();
	}

	private function capture( $callback, ...$arguments ) {
		$level = ob_get_level();
		ob_start();

		try {
			call_user_func_array( $callback, $arguments );

			return ob_get_clean();
		} catch ( Throwable $throwable ) {
			while ( ob_get_level() > $level ) {
				ob_end_clean();
			}

			throw $throwable;
		}
	}

	private function create_administrator() {
		$user_id = $this->factory->user->create( array( 'role' => 'administrator' ) );
		$user = get_userdata( $user_id );
		$user->add_cap( bbp_admin()->minimum_capability );
		$this->set_current_user( $user_id );

		return $user_id;
	}

	private function assert_wp_die( $callback, $expected_message = null ) {
		try {
			call_user_func( $callback );
			$this->fail( 'Expected the request to be rejected.' );
		} catch ( WPDieException $error ) {
			$this->assertInstanceOf( 'WPDieException', $error );
			if ( ! is_null( $expected_message ) ) {
				$this->assertSame( $expected_message, $error->getMessage() );
			}
		}
	}

	/**
	 * @covers BBP_Admin::__construct
	 * @ticket 3706
	 */
	public function test_admin_singleton_has_expected_paths_and_hooks() {
		$admin = bbp_admin();

		$this->assertSame( BBP_PLUGIN_DIR . 'includes/admin/', $admin->admin_dir );
		$this->assertStringEndsWith( '/includes/admin/', $admin->admin_url );
		$this->assertStringEndsWith( '/includes/admin/assets/css/', $admin->css_url );
		$this->assertStringEndsWith( '/includes/admin/assets/js/', $admin->js_url );
		$this->assertSame( 10, has_action( 'bbp_admin_menu', array( $admin, 'admin_menus' ) ) );
		$this->assertSame( 10, has_action( 'bbp_admin_head', array( $admin, 'admin_head' ) ) );
		$this->assertSame( 10, has_action( 'admin_enqueue_scripts', array( $admin, 'enqueue_styles' ) ) );
		$this->assertSame( 10, has_action( 'admin_enqueue_scripts', array( $admin, 'enqueue_scripts' ) ) );
		$this->assertSame( 10, has_action( 'bbp_admin_init', array( $admin, 'setup_notices' ) ) );
		$this->assertSame( 10, has_action( 'bbp_admin_init', array( $admin, 'hide_notices' ) ) );
		$this->assertSame( 10, has_action( 'bbp_admin_init', array( $admin, 'add_upgrade_count' ) ) );
		$this->assertSame( 10, has_action( 'bbp_register_admin_styles', array( $admin, 'register_admin_styles' ) ) );
		$this->assertSame( 10, has_action( 'bbp_register_admin_scripts', array( $admin, 'register_admin_scripts' ) ) );
		$this->assertSame( 10, has_action( 'bbp_register_admin_settings', array( $admin, 'register_admin_settings' ) ) );
		$this->assertSame( 10, has_action( 'bbp_admin_notices', array( $admin, 'output_notices' ) ) );
		$this->assertSame( 10, has_action( 'wp_ajax_bbp_suggest_topic', array( $admin, 'suggest_topic' ) ) );
		$this->assertSame( 10, has_action( 'wp_ajax_bbp_suggest_user', array( $admin, 'suggest_user' ) ) );
		$this->assertSame( 10, has_filter( 'plugin_action_links', array( $admin, 'modify_plugin_action_links' ) ) );
		$this->assertSame( 10, has_filter( 'bbp_map_meta_caps', array( $admin, 'map_settings_meta_caps' ) ) );
		$this->assertSame( 10, has_filter( 'option_page_capability_bbpress', array( $admin, 'option_page_capability_bbpress' ) ) );
		$this->assertSame( 10, has_filter( 'display_post_states', array( $admin, 'display_post_states' ) ) );
		$this->assertSame( 10, has_action( 'network_admin_menu', array( $admin, 'network_admin_menus' ) ) );
		$map_key = _wp_filter_build_unique_id( 'bbp_map_meta_caps', array( $admin, 'map_settings_meta_caps' ), 10 );
		$state_key = _wp_filter_build_unique_id( 'display_post_states', array( $admin, 'display_post_states' ), 10 );
		$this->assertSame( 4, $GLOBALS['wp_filter']['bbp_map_meta_caps']->callbacks[10][ $map_key ]['accepted_args'] );
		$this->assertSame( 2, $GLOBALS['wp_filter']['display_post_states']->callbacks[10][ $state_key ]['accepted_args'] );
	}

	/**
	 * @covers BBP_Admin::add_notice
	 * @covers BBP_Admin::output_notices
	 * @ticket 3706
	 */
	public function test_notices_accept_strings_and_errors_escape_markup_and_render() {
		$admin          = bbp_admin();
		$admin->notices = 'invalid';
		$this->assertFalse( $admin->add_notice( array( 'invalid' ) ) );
		$this->assertFalse( $admin->add_notice( new WP_Error() ) );

		$admin->add_notice( '<span class="bbp-test">Safe</span><script>Unsafe</script>', 'notice-bbpress', false );
		$this->assertCount( 1, $admin->notices );
		$this->assertStringContainsString( 'class="notice notice-bbpress"', $admin->notices[0] );
		$this->assertStringContainsString( '<span class="bbp-test">Safe</span>', $admin->notices[0] );
		$this->assertStringNotContainsString( '<script>', $admin->notices[0] );
		$this->assertStringNotContainsString( 'is-dismissible', $admin->notices[0] );

		$admin->add_notice( new WP_Error( 'first', 'First error' ) );
		$this->assertStringContainsString( 'is-error is-dismissible', $admin->notices[1] );
		$errors = new WP_Error( 'first', 'First error' );
		$errors->add( 'second', 'Second error' );
		$admin->add_notice( $errors );
		$this->assertStringContainsString( '<ul>', $admin->notices[2] );
		$this->assertStringContainsString( '<li>Second error</li>', $admin->notices[2] );
		$this->assertSame( implode( '', $admin->notices ), $this->capture( array( $admin, 'output_notices' ) ) );
	}

	/**
	 * @covers BBP_Admin::setup_notices
	 * @covers BBP_Admin::hide_notices
	 * @ticket 3706
	 */
	public function test_pending_upgrade_notice_can_be_displayed_and_dismissed() {
		$admin = bbp_admin();
		$this->create_administrator();
		update_option( '_bbp_db_pending_upgrades', array( 'bbp-test-upgrade' ) );
		$_GET = array();
		$admin->notices = 'invalid';
		$admin->setup_notices();
		$this->assertCount( 1, $admin->notices );
		$this->assertStringContainsString( 'manual database upgrade', $admin->notices[0] );
		$this->assertStringContainsString( 'notice-bbpress', $admin->notices[0] );

		$_GET = array(
			'bbp-hide-notice' => 'bbp-skip-upgrades',
			'_wpnonce'        => wp_create_nonce( 'bbp-hide-notice' ),
		);
		$_REQUEST = $_GET;
		$admin->hide_notices();
		$this->assertSame( array(), bbp_get_pending_upgrades() );

		update_option( '_bbp_db_pending_upgrades', array( 'bbp-test-upgrade' ) );
		$_GET = array( 'page' => 'bbp-upgrade' );
		$admin->notices = array();
		$admin->setup_notices();
		$this->assertSame( array(), $admin->notices );
	}

	/**
	 * @covers BBP_Admin::setup_notices
	 * @covers BBP_Admin::hide_notices
	 * @ticket 3706
	 */
	public function test_pending_upgrade_notices_require_capability_and_valid_nonce() {
		$admin    = bbp_admin();
		$user_id  = $this->factory->user->create( array( 'role' => 'subscriber' ) );
		$this->set_current_user( $user_id );
		update_option( '_bbp_db_pending_upgrades', array( 'bbp-test-upgrade' ) );
		$admin->notices = array();
		$_GET = array();
		$admin->setup_notices();
		$this->assertSame( array(), $admin->notices );

		$_GET = array(
			'bbp-hide-notice' => 'bbp-skip-upgrades',
			'_wpnonce'        => wp_create_nonce( 'bbp-hide-notice' ),
		);
		$_REQUEST = $_GET;
		$admin->hide_notices();
		$this->assertSame( array( 'bbp-test-upgrade' ), bbp_get_pending_upgrades() );

		$this->create_administrator();
		$_GET['_wpnonce'] = 'invalid';
		$_REQUEST = $_GET;
		$this->assert_wp_die( array( $admin, 'hide_notices' ) );
		$this->assertSame( array( 'bbp-test-upgrade' ), bbp_get_pending_upgrades() );

		$_GET = array(
			'bbp-hide-notice' => 'unknown',
			'_wpnonce'        => wp_create_nonce( 'bbp-hide-notice' ),
		);
		$_REQUEST = $_GET;
		$admin->hide_notices();
		$this->assertSame( array( 'bbp-test-upgrade' ), bbp_get_pending_upgrades() );
	}

	/**
	 * @covers BBP_Admin::add_upgrade_count
	 * @ticket 3706
	 */
	public function test_upgrade_count_is_added_only_to_tools_menu() {
		$GLOBALS['menu'] = array();
		bbp_admin()->add_upgrade_count();
		$this->assertSame( array(), $GLOBALS['menu'] );

		$GLOBALS['menu'] = array(
			array( 'Dashboard', 'read', 'index.php' ),
			array( 'Tools', 'manage_options', 'tools.php' ),
		);
		delete_option( '_bbp_db_pending_upgrades' );
		bbp_admin()->add_upgrade_count();
		$this->assertSame( 'Tools', $GLOBALS['menu'][1][0] );

		update_option( '_bbp_db_pending_upgrades', array( 'one', 'two' ) );
		bbp_admin()->add_upgrade_count();

		$this->assertSame( 'Dashboard', $GLOBALS['menu'][0][0] );
		$this->assertSame( array( 'manage_options', 'tools.php' ), array_slice( $GLOBALS['menu'][1], 1 ) );
		$this->assertStringContainsString( 'awaiting-mod count-2', $GLOBALS['menu'][1][0] );
		$this->assertStringContainsString( 'pending-count">2</span>', $GLOBALS['menu'][1][0] );
	}

	/**
	 * @covers BBP_Admin::admin_menus
	 * @covers BBP_Admin::admin_head
	 * @ticket 3480
	 * @ticket 3706
	 */
	public function test_admin_menus_register_and_hide_grouped_pages() {
		$globals = array( 'submenu', 'admin_page_hooks', '_registered_pages', '_parent_pages' );
		$before  = array();
		foreach ( $globals as $global ) {
			$before[ $global ] = array_key_exists( $global, $GLOBALS )
				? $GLOBALS[ $global ]
				: null;
		}
		$GLOBALS['submenu'] = array();

		try {
			delete_option( '_bbp_db_pending_upgrades' );
			$this->create_administrator();
			bbp_admin()->admin_menus();
			$tools = wp_list_pluck( $GLOBALS['submenu']['tools.php'], 2 );
			$this->assertSame( 2, array_count_values( $tools )['bbp-repair'] );
			$this->assertContains( 'bbp-upgrade', $tools );
			$this->assertContains( 'bbp-converter', $tools );
			$this->assertContains( 'bbp-reset', $tools );
			$this->assertContains( 'bbpress', wp_list_pluck( $GLOBALS['submenu']['options-general.php'], 2 ) );
			$this->assertContains( 'bbp-about', wp_list_pluck( $GLOBALS['submenu']['index.php'], 2 ) );
			$this->assertContains( 'bbp-credits', wp_list_pluck( $GLOBALS['submenu']['index.php'], 2 ) );
			$about = wp_list_filter( $GLOBALS['submenu']['index.php'], array( 2 => 'bbp-about' ) );
			$about = reset( $about );
			$this->assertSame( 'Welcome to bbPress', $about[0] );
			$this->assertSame( 'Welcome to bbPress', $about[3] );
			$credits = wp_list_filter( $GLOBALS['submenu']['index.php'], array( 2 => 'bbp-credits' ) );
			$credits = reset( $credits );
			$this->assertSame( 'Credits', $credits[0] );
			$this->assertSame( 'Welcome to bbPress', $credits[3] );
			$upgrade_hook = get_plugin_page_hookname( 'bbp-upgrade', 'tools.php' );
			$this->assertSame( 10, has_action( 'admin_head-' . $upgrade_hook, 'bbp_tools_modify_menu_highlight' ) );

			bbp_admin()->admin_head();
			$tools = wp_list_pluck( $GLOBALS['submenu']['tools.php'], 2 );
			$this->assertSame( 1, array_count_values( $tools )['bbp-repair'] );
			$repair = wp_list_filter( $GLOBALS['submenu']['tools.php'], array( 2 => 'bbp-repair' ) );
			$this->assertSame( 'Forums', reset( $repair )[0] );
			$this->assertNotContains( 'bbp-upgrade', $tools );
			$this->assertNotContains( 'bbp-converter', $tools );
			$this->assertNotContains( 'bbp-reset', $tools );
			$this->assertNotContains( 'bbp-about', wp_list_pluck( $GLOBALS['submenu']['index.php'], 2 ) );
			$this->assertNotContains( 'bbp-credits', wp_list_pluck( $GLOBALS['submenu']['index.php'], 2 ) );
		} finally {
			foreach ( $globals as $global ) {
				if ( is_null( $before[ $global ] ) ) {
					unset( $GLOBALS[ $global ] );
				} else {
					$GLOBALS[ $global ] = $before[ $global ];
				}
			}
		}
	}

	/**
	 * @covers BBP_Admin::network_admin_menus
	 * @ticket 3706
	 */
	public function test_network_admin_menu_requires_network_activation() {
		$globals = array( 'submenu', 'admin_page_hooks', '_registered_pages', '_parent_pages' );
		$before  = array();
		$active  = is_multisite() ? get_site_option( 'active_sitewide_plugins', array() ) : array();
		$user_id = $this->create_administrator();
		foreach ( $globals as $global ) {
			$before[ $global ] = isset( $GLOBALS[ $global ] ) ? $GLOBALS[ $global ] : null;
		}

		try {
			$GLOBALS['submenu'] = array();
			if ( is_multisite() ) {
				$inactive = $active;
				unset( $inactive[ bbpress()->basename ] );
				update_site_option( 'active_sitewide_plugins', $inactive );
			}
			bbp_admin()->network_admin_menus();
			$this->assertArrayNotHasKey( 'upgrade.php', $GLOBALS['submenu'] );

			if ( is_multisite() ) {
				update_site_option( 'active_sitewide_plugins', array( bbpress()->basename => time() ) );
				bbp_admin()->network_admin_menus();
				$this->assertArrayNotHasKey( 'upgrade.php', $GLOBALS['submenu'] );

				$this->grant_super_admin( $user_id );
				bbp_admin()->network_admin_menus();
				$this->assertContains( 'bbpress-update', wp_list_pluck( $GLOBALS['submenu']['upgrade.php'], 2 ) );
				bbp_admin()->admin_menus();
				$this->assertContains( 'bbp-update', wp_list_pluck( $GLOBALS['submenu']['index.php'], 2 ) );
			}
		} finally {
			if ( is_multisite() ) {
				$this->restore_admins();
				update_site_option( 'active_sitewide_plugins', $active );
			}
			foreach ( $globals as $global ) {
				if ( is_null( $before[ $global ] ) ) {
					unset( $GLOBALS[ $global ] );
				} else {
					$GLOBALS[ $global ] = $before[ $global ];
				}
			}
		}
	}

	/**
	 * @covers BBP_Admin::register_admin_settings
	 * @ticket 3706
	 */
	public function test_admin_settings_register_only_for_capable_users() {
		$globals = array( 'wp_settings_sections', 'wp_settings_fields', 'wp_registered_settings', 'new_allowed_options', 'new_whitelist_options' );
		$before  = array();
		foreach ( $globals as $global ) {
			$before[ $global ]  = isset( $GLOBALS[ $global ] ) ? $GLOBALS[ $global ] : null;
			$GLOBALS[ $global ] = array();
		}

		try {
			$this->set_current_user( $this->factory->user->create( array( 'role' => 'subscriber' ) ) );
			BBP_Admin::register_admin_settings();
			$this->assertSame( array(), $GLOBALS['wp_settings_sections'] );
			$this->assertSame( array(), $GLOBALS['wp_settings_fields'] );
			$this->assertSame( array(), $GLOBALS['new_allowed_options'] );

			foreach ( $globals as $global ) {
				$GLOBALS[ $global ] = array();
			}
			$user = get_userdata( $this->factory->user->create( array( 'role' => 'subscriber' ) ) );
			$user->add_cap( bbp_admin()->minimum_capability );
			$this->set_current_user( $user->ID );
			BBP_Admin::register_admin_settings();
			$this->assertArrayHasKey( 'bbp_settings_features', $GLOBALS['wp_settings_sections']['bbpress'] );
			$this->assertArrayNotHasKey( '_bbp_allow_super_mods', $GLOBALS['wp_settings_fields']['bbpress']['bbp_settings_features'] );
			$this->assertNotContains( '_bbp_allow_super_mods', $GLOBALS['new_allowed_options']['bbpress'] );

			foreach ( $globals as $global ) {
				$GLOBALS[ $global ] = array();
			}
			$this->create_administrator();
			BBP_Admin::register_admin_settings();
			$this->assertArrayHasKey( 'bbp_settings_status', $GLOBALS['wp_settings_sections']['bbpress'] );
			$this->assertArrayHasKey( '_bbp_forums_status', $GLOBALS['wp_settings_fields']['bbpress']['bbp_settings_status'] );
			$this->assertContains( '_bbp_forums_status', $GLOBALS['new_allowed_options']['bbpress'] );
			$this->assertArrayHasKey( 'bbp_converter_connection', $GLOBALS['wp_settings_sections']['converter'] );
		} finally {
			foreach ( $globals as $global ) {
				if ( is_null( $before[ $global ] ) ) {
					unset( $GLOBALS[ $global ] );
				} else {
					$GLOBALS[ $global ] = $before[ $global ];
				}
			}
		}
	}

	/**
	 * @covers BBP_Admin::register_admin_settings
	 * @ticket 3706
	 */
	public function test_admin_settings_handle_empty_sections_and_deep_integration() {
		$globals = array( 'wp_settings_sections', 'wp_settings_fields', 'wp_registered_settings', 'new_allowed_options', 'new_whitelist_options' );
		$before  = array();
		foreach ( $globals as $global ) {
			$before[ $global ]  = isset( $GLOBALS[ $global ] ) ? $GLOBALS[ $global ] : null;
			$GLOBALS[ $global ] = array();
		}
		$empty = function () {
			return array();
		};
		$deep = function () {
			return 'deep';
		};

		try {
			add_filter( 'bbp_admin_get_settings_sections', $empty );
			$this->assertFalse( BBP_Admin::register_admin_settings() );
			remove_filter( 'bbp_admin_get_settings_sections', $empty );

			$this->create_administrator();
			add_filter( 'bbp_settings_integration', $deep );
			BBP_Admin::register_admin_settings();
			$this->assertArrayHasKey( 'bbp_settings_status', $GLOBALS['wp_settings_sections']['discussion'] );
			$this->assertArrayHasKey( 'bbp_settings_per_page', $GLOBALS['wp_settings_sections']['reading'] );
			$this->assertArrayHasKey( 'bbp_settings_root_slugs', $GLOBALS['wp_settings_sections']['permalink'] );
			$this->assertArrayHasKey( 'bbp_converter_connection', $GLOBALS['wp_settings_sections']['converter'] );
		} finally {
			remove_filter( 'bbp_admin_get_settings_sections', $empty );
			remove_filter( 'bbp_settings_integration', $deep );
			foreach ( $globals as $global ) {
				if ( is_null( $before[ $global ] ) ) {
					unset( $GLOBALS[ $global ] );
				} else {
					$GLOBALS[ $global ] = $before[ $global ];
				}
			}
		}
	}

	/**
	 * @covers BBP_Admin::modify_plugin_action_links
	 * @covers BBP_Admin::option_page_capability_bbpress
	 * @ticket 3706
	 */
	public function test_plugin_action_links_and_settings_capability() {
		$admin = bbp_admin();
		$links = array( 'deactivate' => 'Deactivate' );
		$this->assertSame( $links, $admin->modify_plugin_action_links( $links, 'another/plugin.php' ) );
		$this->assertSame( $admin->minimum_capability, $admin->option_page_capability_bbpress() );

		$user_id = $this->factory->user->create( array( 'role' => 'subscriber' ) );
		$this->set_current_user( $user_id );
		$this->assertSame( $links, $admin->modify_plugin_action_links( $links, plugin_basename( bbpress()->basename ) ) );

		$this->create_administrator();
		$links = $admin->modify_plugin_action_links( $links, plugin_basename( bbpress()->basename ) );
		$this->assertSame( array( 'deactivate', 'settings', 'about' ), array_keys( $links ) );
		$this->assertStringContainsString( admin_url( 'options-general.php?page=bbpress' ), $links['settings'] );
		$this->assertStringContainsString( admin_url( 'index.php?page=bbp-about' ), $links['about'] );
	}

	/**
	 * @covers BBP_Admin::register_admin_styles
	 * @covers BBP_Admin::register_admin_scripts
	 * @covers BBP_Admin::enqueue_styles
	 * @covers BBP_Admin::enqueue_scripts
	 * @ticket 3706
	 */
	public function test_admin_assets_register_and_enqueue_for_relevant_screens() {
		$admin          = bbp_admin();
		$scripts        = clone wp_scripts();
		$styles         = clone wp_styles();
		$style_handles  = array( 'bbp-admin-css', 'bbp-admin-blocks' );
		$script_handles = array( 'bbp-admin-common-js', 'bbp-admin-topics-js', 'bbp-admin-replies-js', 'bbp-converter', 'bbp-admin-blocks', 'bbp-admin-badge-js' );
		foreach ( $style_handles as $handle ) {
			wp_deregister_style( $handle );
		}
		foreach ( $script_handles as $handle ) {
			wp_deregister_script( $handle );
		}

		try {
			$admin->register_admin_styles();
			$admin->register_admin_scripts();
			$this->assertTrue( wp_style_is( 'bbp-admin-css', 'registered' ) );
			$this->assertTrue( wp_style_is( 'bbp-admin-blocks', 'registered' ) );
			$this->assertTrue( wp_script_is( 'bbp-admin-common-js', 'registered' ) );
			$this->assertTrue( wp_script_is( 'bbp-admin-blocks', 'registered' ) );
			$this->assertContains( 'suggest', wp_scripts()->registered['bbp-admin-common-js']->deps );
			$this->assertTrue( wp_scripts()->registered['bbp-admin-blocks']->extra['group'] > 0 );

			set_current_screen( bbp_get_topic_post_type() );
			$admin->enqueue_styles();
			$admin->enqueue_scripts();
			$this->assertTrue( wp_style_is( 'bbp-admin-css', 'enqueued' ) );
			$this->assertTrue( wp_script_is( 'suggest', 'enqueued' ) );
			$this->assertTrue( wp_script_is( 'bbp-admin-common-js', 'enqueued' ) );
			$this->assertTrue( wp_script_is( 'bbp-admin-topics-js', 'enqueued' ) );
			$this->assertFalse( wp_script_is( 'bbp-admin-replies-js', 'enqueued' ) );

			wp_dequeue_script( 'bbp-admin-common-js' );
			wp_dequeue_script( 'bbp-admin-topics-js' );
			set_current_screen( bbp_get_reply_post_type() );
			$admin->enqueue_scripts();
			$this->assertTrue( wp_script_is( 'bbp-admin-common-js', 'enqueued' ) );
			$this->assertTrue( wp_script_is( 'bbp-admin-replies-js', 'enqueued' ) );

			wp_dequeue_script( 'bbp-admin-common-js' );
			wp_dequeue_script( 'bbp-admin-replies-js' );
			set_current_screen( 'post' );
			$admin->enqueue_scripts();
			$this->assertFalse( wp_script_is( 'bbp-admin-common-js', 'enqueued' ) );

			set_current_screen( 'dashboard_page_bbp-credits' );
			$admin->enqueue_scripts();
			$this->assertTrue( wp_script_is( 'bbp-admin-badge-js', 'enqueued' ) );
		} finally {
			$GLOBALS['wp_scripts'] = $scripts;
			$GLOBALS['wp_styles']  = $styles;
		}
	}

	/**
	 * @covers BBP_Admin::display_post_states
	 * @ticket 3706
	 *
	 * @dataProvider post_state_provider
	 */
	public function test_display_post_states_identifies_shortcodes_and_archive_slugs( $content, $slug, $expected, $shortcode = '' ) {
		if ( '__forum_root__' === $slug ) {
			$slug = bbp_get_root_slug();
		} elseif ( '__topic_root__' === $slug ) {
			$slug = bbp_get_topic_archive_slug();
		}
		if ( ! empty( $shortcode ) ) {
			$this->assertTrue( shortcode_exists( $shortcode ) );
		}
		$post_id = $this->factory->post->create(
			array(
				'post_content' => $content,
				'post_name'    => $slug,
			)
		);
		$states = bbp_admin()->display_post_states( array( 'Existing' ), get_post( $post_id ) );
		$expected_states = is_null( $expected )
			? array( 'Existing' )
			: array( 'Existing', $expected );

		$this->assertSame( $expected_states, $states );
	}

	public static function post_state_provider() {
		return array(
			'forum shortcode'       => array( '[bbp-forum-index]', 'page', 'Forum Archive', 'bbp-forum-index' ),
			'topic shortcode'       => array( '[bbp-topic-index]', 'page', 'Topic Archive', 'bbp-topic-index' ),
			'topic tags shortcode'  => array( '[bbp-topic-tags]', 'page', 'Topic Tags', 'bbp-topic-tags' ),
			'view shortcode'        => array( '[bbp-single-view]', 'page', 'Topic View', 'bbp-single-view' ),
			'search shortcode'      => array( '[bbp-search]', 'page', 'Forum Search', 'bbp-search' ),
			'login shortcode'       => array( '[bbp-login]', 'page', 'Forum Login', 'bbp-login' ),
			'register shortcode'    => array( '[bbp-register]', 'page', 'Forum Registration', 'bbp-register' ),
			'lost pass shortcode'   => array( '[bbp-lost-pass]', 'page', 'Forum Lost Password', 'bbp-lost-pass' ),
			'statistics shortcode'  => array( '[bbp-stats]', 'page', 'Forum Statistics', 'bbp-stats' ),
			'shortcode precedence'  => array( '[bbp-search][bbp-login]', 'page', 'Forum Search', 'bbp-search' ),
			'shortcode before slug' => array( '[bbp-login]', '__forum_root__', 'Forum Login', 'bbp-login' ),
			'forum archive slug'    => array( '', '__forum_root__', 'Forum Archive' ),
			'topic archive slug'    => array( '', '__topic_root__', 'Topic Archive' ),
			'no matching state'     => array( '', 'ordinary-page', null ),
		);
	}

	/**
	 * @covers BBP_Admin::map_settings_meta_caps
	 * @ticket 3706
	 */
	public function test_settings_meta_capability_mapping() {
		$minimum = bbp_admin()->minimum_capability;
		$capabilities = array(
			'bbp_about_page',
			'bbp_tools_page',
			'bbp_tools_repair_page',
			'bbp_tools_upgrade_page',
			'bbp_tools_import_page',
			'bbp_tools_reset_page',
			'bbp_settings_page',
			'bbp_converter_connection',
			'bbp_converter_options',
			'bbp_settings_status',
			'bbp_settings_users',
			'bbp_settings_features',
			'bbp_settings_theme_compat',
			'bbp_settings_root_slugs',
			'bbp_settings_single_slugs',
			'bbp_settings_user_slugs',
			'bbp_settings_per_page',
			'bbp_settings_per_rss_page',
		);
		foreach ( $capabilities as $capability ) {
			$this->assertSame( array( $minimum ), BBP_Admin::map_settings_meta_caps( array(), $capability ) );
		}
		$import_users = is_multisite()
			? array( 'bbp_tools_import_users' )
			: array( $minimum );
		$this->assertSame( $import_users, BBP_Admin::map_settings_meta_caps( array(), 'bbp_tools_import_users' ) );
		$this->assertSame( array( 'do_not_allow' ), BBP_Admin::map_settings_meta_caps( array(), 'bbp_settings_buddypress' ) );
		$this->assertSame( array( 'do_not_allow' ), BBP_Admin::map_settings_meta_caps( array(), 'bbp_settings_akismet' ) );
		$this->assertSame( array( 'original' ), BBP_Admin::map_settings_meta_caps( array( 'original' ), 'unrelated' ) );

		$filter = function () {
			return array( 'filtered' );
		};
		add_filter( 'bbp_map_settings_meta_caps', $filter );
		try {
			$this->assertSame( array( 'filtered' ), BBP_Admin::map_settings_meta_caps( array(), 'bbp_tools_page' ) );
		} finally {
			remove_filter( 'bbp_map_settings_meta_caps', $filter );
		}
	}

	/**
	 * @covers BBP_Admin::suggest_topic
	 * @covers BBP_Admin::suggest_user
	 * @ticket 3706
	 */
	public function test_ajax_suggestions_reject_invalid_requests() {
		$admin = bbp_admin();
		$handler = function () {
			return function ( $message ) {
				throw new WPDieException( (string) $message );
			};
		};
		add_filter( 'wp_doing_ajax', '__return_true' );
		add_filter( 'wp_die_handler', $handler );
		add_filter( 'wp_die_ajax_handler', $handler );
		try {
			$_REQUEST = array();
			$this->assert_wp_die( array( $admin, 'suggest_topic' ), '' );
			$this->assert_wp_die( array( $admin, 'suggest_user' ), '' );

			$user_id = $this->factory->user->create( array( 'role' => 'subscriber' ) );
			$this->set_current_user( $user_id );
			$_REQUEST = array(
				'q'           => 'example',
				'_ajax_nonce' => wp_create_nonce( 'bbp_suggest_topic_nonce' ),
			);
			$this->assert_wp_die( array( $admin, 'suggest_topic' ), '' );
			$_REQUEST['_ajax_nonce'] = wp_create_nonce( 'bbp_suggest_user_nonce' );
			$this->assert_wp_die( array( $admin, 'suggest_user' ), '' );

			$this->create_administrator();
			bbp_set_user_role( bbp_get_current_user_id(), bbp_get_keymaster_role() );
			$_REQUEST = array( 'q' => 'example', '_ajax_nonce' => 'invalid' );
			$this->assert_wp_die( array( $admin, 'suggest_topic' ), '-1' );
			$this->assert_wp_die( array( $admin, 'suggest_user' ), '-1' );

			$_REQUEST = array(
				'q'           => '@',
				'_ajax_nonce' => wp_create_nonce( 'bbp_suggest_user_nonce' ),
			);
			$this->assert_wp_die( array( $admin, 'suggest_user' ), '' );
		} finally {
			remove_filter( 'wp_doing_ajax', '__return_true' );
			remove_filter( 'wp_die_handler', $handler );
			remove_filter( 'wp_die_ajax_handler', $handler );
		}
	}

	/**
	 * @covers BBP_Admin::update_screen
	 * @covers BBP_Admin::network_update_screen
	 * @ticket 3706
	 */
	public function test_update_screens_render_actions_and_reject_invalid_nonces() {
		$_GET    = array();
		$site    = $this->capture( array( 'BBP_Admin', 'update_screen' ) );
		$network = $this->capture( array( 'BBP_Admin', 'network_update_screen' ) );
		$this->assertStringContainsString( 'action=bbp-update', $site );
		$this->assertStringContainsString( 'action=bbpress-update', $network );
		$this->assertSame( 1, preg_match( '/_wpnonce=([a-z0-9]+)/', $site, $site_nonce ) );
		$this->assertSame( 1, wp_verify_nonce( $site_nonce[1], 'bbp-update' ) );
		$this->assertSame( 1, preg_match( '/_wpnonce=([a-z0-9]+)/', $network, $network_nonce ) );
		$this->assertSame( 1, wp_verify_nonce( $network_nonce[1], 'bbpress-update' ) );

		$_GET = array(
			'action'   => 'bbp-update',
			'_wpnonce' => 'invalid',
		);
		$_REQUEST = $_GET;
		$this->assert_wp_die(
			function () {
				$this->capture( array( 'BBP_Admin', 'update_screen' ) );
			},
			'The link you followed has expired.'
		);

		$_GET = array(
			'action'   => 'bbpress-update',
			'_wpnonce' => 'invalid',
		);
		$_REQUEST = $_GET;
		$this->assert_wp_die(
			function () {
				$this->capture( array( 'BBP_Admin', 'network_update_screen' ) );
			},
			'The link you followed has expired.'
		);

		$db_version   = get_option( '_bbp_db_version', false );
		$rewrite_rules = get_option( 'rewrite_rules', false );
		try {
			update_option( '_bbp_db_version', bbp_get_db_version() );
			$_GET = array(
				'action'   => 'bbp-update',
				'_wpnonce' => wp_create_nonce( 'bbp-update' ),
			);
			$_REQUEST = $_GET;
			$this->assertStringContainsString( 'All done!', $this->capture( array( 'BBP_Admin', 'update_screen' ) ) );

			if ( is_multisite() ) {
				$_GET = array(
					'action'   => 'bbpress-update',
					'n'        => PHP_INT_MAX,
					'_wpnonce' => wp_create_nonce( 'bbpress-update' ),
				);
				$_REQUEST = $_GET;
				$this->assertStringContainsString( 'All done!', $this->capture( array( 'BBP_Admin', 'network_update_screen' ) ) );
			}
		} finally {
			false === $db_version
				? delete_option( '_bbp_db_version' )
				: update_option( '_bbp_db_version', $db_version );
			false === $rewrite_rules
				? delete_option( 'rewrite_rules' )
				: update_option( 'rewrite_rules', $rewrite_rules );
		}
	}

	/**
	 * @covers BBP_Admin::about_screen
	 * @covers BBP_Admin::credits_screen
	 * @ticket 3706
	 */
	public function test_about_and_credits_screens_render_expected_sections() {
		$about   = $this->capture( array( bbp_admin(), 'about_screen' ) );
		$credits = $this->capture( array( bbp_admin(), 'credits_screen' ) );
		$this->assertStringContainsString( 'class="changelog"', $about );
		$this->assertStringContainsString( 'page=bbp-about', $about );
		$this->assertStringNotContainsString( 'wp-people-group-project-leaders', $about );
		$this->assertStringContainsString( 'wp-people-group-project-leaders', $credits );
		$this->assertStringContainsString( 'wp-people-group-contributing-developers', $credits );
		$this->assertStringContainsString( 'page=bbp-credits', $credits );
		$this->assertStringNotContainsString( 'class="changelog"', $credits );
	}
}
