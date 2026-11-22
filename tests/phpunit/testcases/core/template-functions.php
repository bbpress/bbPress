<?php

/**
 * Tests for core template functions.
 *
 * @group core
 * @group template_functions
 * @ticket 3706
 */
class BBP_Tests_Core_Template_Functions extends BBP_UnitTestCase {

	/**
	 * Original theme compatibility template candidates.
	 *
	 * @var mixed
	 */
	private $original_templates;

	/**
	 * Whether template candidates were originally set.
	 *
	 * @var bool
	 */
	private $had_original_templates;

	/**
	 * Original located theme compatibility template.
	 *
	 * @var mixed
	 */
	private $original_template;

	/**
	 * Whether a located template was originally set.
	 *
	 * @var bool
	 */
	private $had_original_template;

	public function setUp(): void {
		parent::setUp();

		$this->had_original_templates = property_exists( bbpress()->theme_compat, 'templates' );
		$this->had_original_template  = property_exists( bbpress()->theme_compat, 'template' );
		$this->original_templates     = $this->had_original_templates ? bbpress()->theme_compat->templates : null;
		$this->original_template      = $this->had_original_template ? bbpress()->theme_compat->template : null;
	}

	public function tearDown(): void {
		if ( $this->had_original_templates ) {
			bbpress()->theme_compat->templates = $this->original_templates;
		} else {
			unset( bbpress()->theme_compat->templates );
		}

		if ( $this->had_original_template ) {
			bbpress()->theme_compat->template = $this->original_template;
		} else {
			unset( bbpress()->theme_compat->template );
		}

		parent::tearDown();
	}

	/**
	 * @covers ::bbp_get_template_part
	 */
	public function test_get_template_part_builds_and_filters_candidates() {
		$action_args = array();
		$filter_args = array();
		$locate_args = array();
		$action      = function( $slug, $name ) use ( &$action_args ) {
			$action_args = array( $slug, $name );
		};
		$filter      = function( $templates, $slug, $name ) use ( &$filter_args ) {
			$filter_args = array( $templates, $slug, $name );
			$templates[] = 'custom.php';

			return $templates;
		};
		$locate      = function( $located, $template_name, $templates, $locations, $load, $require_once ) use ( &$locate_args ) {
			$locate_args = array( $templates, $load, $require_once );
		};

		add_action( 'get_template_part_probe', $action, 10, 2 );
		add_filter( 'bbp_get_template_part', $filter, 10, 3 );
		add_action( 'bbp_locate_template', $locate, 10, 6 );
		try {
			$this->assertFalse( bbp_get_template_part( 'probe', 'alpha' ) );
			$this->assertSame( array( 'probe', 'alpha' ), $action_args );
			$this->assertSame( array( array( 'probe-alpha.php', 'probe.php' ), 'probe', 'alpha' ), $filter_args );
			$this->assertSame( array( array( 'probe-alpha.php', 'probe.php', 'custom.php' ), true, false ), $locate_args );
		} finally {
			remove_action( 'bbp_locate_template', $locate, 10 );
			remove_filter( 'bbp_get_template_part', $filter, 10 );
			remove_action( 'get_template_part_probe', $action, 10 );
		}
	}

	/**
	 * @covers ::bbp_get_template_part
	 */
	public function test_get_template_part_omits_name_candidate_when_name_is_null() {
		$templates = array();
		$filter    = function( $candidates ) use ( &$templates ) {
			$templates = $candidates;

			return $candidates;
		};

		add_filter( 'bbp_get_template_part', $filter );
		try {
			bbp_get_template_part( 'probe' );
			$this->assertSame( array( 'probe.php' ), $templates );
		} finally {
			remove_filter( 'bbp_get_template_part', $filter );
		}
	}

	/**
	 * @covers ::bbp_locate_template
	 */
	public function test_locate_template_uses_stack_and_candidate_priority() {
		$first_dir  = BBP_TESTS_DIR . '/data/themes/bbp-classic-theme';
		$second_dir = BBP_TESTS_DIR . '/data/themes/bbp-classic-theme-json';
		$first      = function() use ( $first_dir ) {
			return $first_dir;
		};
		$second     = function() use ( $second_dir ) {
			return $second_dir;
		};
		$empty      = '__return_empty_string';
		$action     = array();
		$capture    = function( $located, $template_name, $templates, $locations, $load, $require_once ) use ( &$action ) {
			$action = array( $located, $template_name, $templates, $locations, $load, $require_once );
		};

		bbp_register_template_stack( $empty, -100 );
		bbp_register_template_stack( $first, -90 );
		bbp_register_template_stack( $second, -80 );
		add_action( 'bbp_locate_template', $capture, 10, 6 );
		try {
			$located = bbp_locate_template( array( '', '/index.php' ), false, false );
			$this->assertSame( $first_dir . '/index.php', $located );
			$this->assertSame( $located, $action[0] );
			$this->assertSame( 'index.php', $action[1] );
			$this->assertSame( array( '', '/index.php' ), $action[2] );
			$this->assertSame(
				array( $first_dir . '/bbpress', $first_dir . '/forums', $first_dir ),
				array_slice( $action[3], 0, 3 )
			);
			$this->assertFalse( $action[4] );
			$this->assertFalse( $action[5] );
		} finally {
			remove_action( 'bbp_locate_template', $capture, 10 );
			bbp_deregister_template_stack( $second, -80 );
			bbp_deregister_template_stack( $first, -90 );
			bbp_deregister_template_stack( $empty, -100 );
		}
	}

	/**
	 * @covers ::bbp_locate_template
	 * @ticket BBP3794
	 */
	public function test_locate_template_honors_wp_using_themes_filter() {
		$template_dir = BBP_TESTS_DIR . '/data/themes/bbp-classic-theme';
		$stack        = function() use ( $template_dir ) {
			return $template_dir;
		};

		bbp_register_template_stack( $stack, -100 );
		add_filter( 'wp_using_themes', '__return_true' );

		try {
			$this->assertSame( $template_dir . '/theme-filter.php', bbp_locate_template( 'theme-filter.php', true, false ) );
			$this->assertTrue( $GLOBALS['bbp_test_theme_template_loaded'] );
		} finally {
			remove_filter( 'wp_using_themes', '__return_true' );
			bbp_deregister_template_stack( $stack, -100 );
			unset( $GLOBALS['bbp_test_theme_template_loaded'] );
		}
	}

	/**
	 * @covers ::bbp_locate_enqueueable
	 */
	public function test_locate_enqueueable_prefers_the_expected_asset_variant() {
		$callback = $this->register_fixture_template_stack();
		$suffix   = bbp_doing_script_debug() ? '/assets/probe.css' : '/assets/probe.min.css';
		try {
			$this->assertStringEndsWith( $suffix, bbp_locate_enqueueable( 'assets/probe.css' ) );
			$this->assertStringEndsWith( $suffix, bbp_locate_enqueueable( 'assets/probe.min.css' ) );
			$this->assertStringEndsWith( str_replace( '.css', '.js', $suffix ), bbp_locate_enqueueable( 'assets/probe.js' ) );
			$this->assertFalse( bbp_locate_enqueueable() );
		} finally {
			bbp_deregister_template_stack( $callback, -100 );
		}
	}

	/**
	 * @covers ::bbp_urlize_enqueueable
	 */
	public function test_urlize_enqueueable_converts_content_paths_and_windows_slashes() {
		$path = WP_CONTENT_DIR . '/plugins/example/asset.css';
		$this->assertSame( content_url( '/plugins/example/asset.css' ), bbp_urlize_enqueueable( $path ) );

		$windows_path = str_replace( '/', '\\', $path );
		$this->assertSame( content_url( '/plugins/example/asset.css' ), bbp_urlize_enqueueable( $windows_path ) );
	}

	/**
	 * @covers ::bbp_enqueue_style
	 */
	public function test_enqueue_style_registers_the_located_asset() {
		$callback = $this->register_fixture_template_stack();
		$handle   = 'bbp-test-probe-style';
		$suffix   = bbp_doing_script_debug() ? '/assets/probe.css' : '/assets/probe.min.css';

		try {
			$url = bbp_enqueue_style( $handle, 'assets/probe.css', array( 'dashicons' ), '1.2.3', 'screen' );
			$this->assertStringEndsWith( $suffix, $url );
			$this->assertTrue( wp_style_is( $handle, 'registered' ) );
			$this->assertTrue( wp_style_is( $handle, 'enqueued' ) );
			$this->assertSame( array( 'dashicons' ), wp_styles()->registered[ $handle ]->deps );
			$this->assertSame( '1.2.3', wp_styles()->registered[ $handle ]->ver );
			$this->assertSame( 'screen', wp_styles()->registered[ $handle ]->args );
		} finally {
			wp_dequeue_style( $handle );
			wp_deregister_style( $handle );
			bbp_deregister_template_stack( $callback, -100 );
		}
	}

	/**
	 * @covers ::bbp_enqueue_script
	 */
	public function test_enqueue_script_registers_the_located_asset_with_default_version() {
		$callback = $this->register_fixture_template_stack();
		$handle   = 'bbp-test-probe-script';
		$suffix   = bbp_doing_script_debug() ? '/assets/probe.js' : '/assets/probe.min.js';
		$before   = time();

		try {
			$url = bbp_enqueue_script( $handle, 'assets/probe.js', array( 'jquery' ), false, true );
			$after = time();
			$this->assertStringEndsWith( $suffix, $url );
			$this->assertTrue( wp_script_is( $handle, 'registered' ) );
			$this->assertTrue( wp_script_is( $handle, 'enqueued' ) );
			$this->assertSame( array( 'jquery' ), wp_scripts()->registered[ $handle ]->deps );
			if ( bbp_doing_script_debug() ) {
				$this->assertGreaterThanOrEqual( $before, (int) wp_scripts()->registered[ $handle ]->ver );
				$this->assertLessThanOrEqual( $after, (int) wp_scripts()->registered[ $handle ]->ver );
			} else {
				$this->assertSame( bbp_get_version(), wp_scripts()->registered[ $handle ]->ver );
			}
			$this->assertSame( 1, wp_scripts()->registered[ $handle ]->extra['group'] );
		} finally {
			wp_dequeue_script( $handle );
			wp_deregister_script( $handle );
			bbp_deregister_template_stack( $callback, -100 );
		}
	}

	/**
	 * @covers ::bbp_enqueue_style
	 * @covers ::bbp_enqueue_script
	 */
	public function test_enqueue_helpers_reject_empty_handles_and_dependencies() {
		$callback = $this->register_fixture_template_stack();
		$style    = 'bbp-test-clean-deps-style';
		$script   = 'bbp-test-clean-deps-script';

		try {
			$this->assertFalse( bbp_enqueue_style( '', 'assets/probe.css' ) );
			$this->assertFalse( bbp_enqueue_script( '', 'assets/probe.js' ) );
			$this->assertIsString( bbp_enqueue_style( $style, 'assets/probe.css', array( '', 'dashicons' ) ) );
			$this->assertIsString( bbp_enqueue_script( $script, 'assets/probe.js', array( '', 'jquery' ) ) );
			$this->assertSame( array( 'dashicons' ), wp_styles()->registered[ $style ]->deps );
			$this->assertSame( array( 'jquery' ), wp_scripts()->registered[ $script ]->deps );
		} finally {
			wp_dequeue_style( $style );
			wp_deregister_style( $style );
			wp_dequeue_script( $script );
			wp_deregister_script( $script );
			bbp_deregister_template_stack( $callback, -100 );
		}
	}

	/**
	 * @covers ::bbp_enqueue_style
	 * @covers ::bbp_enqueue_script
	 */
	public function test_enqueue_helpers_return_false_for_missing_assets() {
		$this->assertFalse( bbp_enqueue_style( 'bbp-missing-style', 'missing.css' ) );
		$this->assertFalse( wp_style_is( 'bbp-missing-style', 'registered' ) );
		$this->assertFalse( bbp_enqueue_script( 'bbp-missing-script', 'missing.js' ) );
		$this->assertFalse( wp_script_is( 'bbp-missing-script', 'registered' ) );
	}

	/**
	 * @covers ::bbp_register_template_stack
	 * @covers ::bbp_deregister_template_stack
	 */
	public function test_register_and_deregister_template_stack_validate_callbacks_and_priorities() {
		$callback = function() {
			return '/template/location';
		};

		$this->assertFalse( bbp_register_template_stack() );
		$this->assertFalse( bbp_register_template_stack( 'bbp_missing_callback' ) );
		$this->assertTrue( bbp_register_template_stack( $callback, 7 ) );
		$this->assertSame( 7, has_filter( 'bbp_template_stack', $callback ) );
		$this->assertFalse( bbp_deregister_template_stack( 'bbp_missing_callback' ) );
		$this->assertTrue( bbp_deregister_template_stack( $callback, 7 ) );
		$this->assertFalse( has_filter( 'bbp_template_stack', $callback ) );
	}

	/**
	 * @covers ::bbp_get_template_stack
	 */
	public function test_get_template_stack_filters_empty_and_duplicate_locations() {
		$first = function() {
			return '/template/first';
		};
		$dupe  = function() {
			return '/template/first';
		};
		$empty = '__return_empty_string';
		$seen  = array();
		$filter = function( $stack ) use ( &$seen ) {
			$seen = $stack;
			$stack[] = '/template/filtered';

			return $stack;
		};

		bbp_register_template_stack( $empty, -100 );
		bbp_register_template_stack( $first, -90 );
		bbp_register_template_stack( $dupe, -80 );
		add_filter( 'bbp_get_template_stack', $filter, 9 );
		try {
			$stack = bbp_get_template_stack();
			$this->assertSame( $seen, array_unique( array_filter( $seen ) ) );
			$this->assertSame( 1, count( array_keys( $seen, '/template/first', true ) ) );
			$this->assertSame( '/template/filtered', end( $stack ) );
		} finally {
			remove_filter( 'bbp_get_template_stack', $filter, 9 );
			bbp_deregister_template_stack( $dupe, -80 );
			bbp_deregister_template_stack( $first, -90 );
			bbp_deregister_template_stack( $empty, -100 );
		}
	}

	/**
	 * @covers ::bbp_buffer_template_part
	 */
	public function test_buffer_template_part_returns_and_optionally_displays_output() {
		$output = function( $slug, $name ) {
			echo "{$slug}-{$name}";
		};

		add_action( 'get_template_part_probe', $output, 10, 2 );
		try {
			$this->assertSame( 'probe-alpha', bbp_buffer_template_part( 'probe', 'alpha', false ) );
			$this->expectOutputString( 'probe-beta' );
			$this->assertSame( 'probe-beta', bbp_buffer_template_part( 'probe', 'beta', true ) );
		} finally {
			remove_action( 'get_template_part_probe', $output, 10 );
		}
	}

	/**
	 * @covers ::bbp_buffer_template_part
	 * @ticket BBP3717
	 *
	 * @requires PHP 8.0
	 */
	public function test_buffer_template_part_preserves_named_arguments() {
		$output = function( $slug, $name ) {
			echo "{$slug}-{$name}";
		};

		add_action( 'get_template_part_probe', $output, 10, 2 );
		try {
			$this->assertSame(
				'probe-named',
				call_user_func_array(
					'bbp_buffer_template_part',
					array(
						'slug' => 'probe',
						'name' => 'named',
						'echo' => false,
					)
				)
			);
		} finally {
			remove_action( 'get_template_part_probe', $output, 10 );
		}
	}

	/**
	 * @covers ::bbp_get_query_template
	 */
	public function test_get_query_template_sanitizes_type_filters_candidates_and_stashes_state() {
		$candidates = function( $templates ) {
			$this->assertSame( array( 'customtype.php' ), $templates );

			return array( 'first.php', 'second.php' );
		};
		$result     = function( $template, $templates ) {
			$this->assertFalse( $template );
			$this->assertSame( array( 'first.php', 'second.php' ), $templates );

			return '/theme/final.php';
		};

		add_filter( 'bbp_get_customtype_template', $candidates );
		add_filter( 'bbp_customtype_template', $result, 10, 2 );
		try {
			$this->assertSame( '/theme/final.php', bbp_get_query_template( 'custom_type!' ) );
			$this->assertSame( array( 'first.php', 'second.php' ), bbpress()->theme_compat->templates );
			$this->assertFalse( bbpress()->theme_compat->template );
		} finally {
			remove_filter( 'bbp_customtype_template', $result, 10 );
			remove_filter( 'bbp_get_customtype_template', $candidates );
		}
	}

	/**
	 * @covers ::bbp_get_template_locations
	 */
	public function test_get_template_locations_is_filterable_with_templates_context() {
		$filter = function( $locations, $templates ) {
			$this->assertSame( array( 'bbpress', 'forums', '' ), $locations );
			$this->assertSame( array( 'single-forum.php' ), $templates );
			$locations[] = 'custom';

			return $locations;
		};

		add_filter( 'bbp_get_template_locations', $filter, 10, 2 );
		try {
			$this->assertSame( array( 'bbpress', 'forums', '', 'custom' ), bbp_get_template_locations( array( 'single-forum.php' ) ) );
		} finally {
			remove_filter( 'bbp_get_template_locations', $filter, 10 );
		}
	}

	/**
	 * @covers ::bbp_add_template_stack_locations
	 */
	public function test_add_template_stack_locations_combines_filters_and_deduplicates_paths() {
		$locations = function() {
			return array( 'bbpress', '', 'bbpress' );
		};
		$filter    = function( $paths, $stacks ) {
			$this->assertSame( array( '/one/bbpress', '/one', '/two/bbpress', '/two' ), array_values( $paths ) );
			$this->assertSame( array( '/one', '/two' ), $stacks );

			return $paths;
		};

		add_filter( 'bbp_get_template_locations', $locations );
		add_filter( 'bbp_add_template_stack_locations', $filter, 10, 2 );
		try {
			$this->assertSame(
				array( '/one/bbpress', '/one', '/two/bbpress', '/two' ),
				array_values( bbp_add_template_stack_locations( array( '/one', '/two' ) ) )
			);
		} finally {
			remove_filter( 'bbp_add_template_stack_locations', $filter, 10 );
			remove_filter( 'bbp_get_template_locations', $locations );
		}
	}

	/**
	 * Register the test fixture directory in the template stack.
	 *
	 * @return Closure Registered callback.
	 */
	private function register_fixture_template_stack() {
		$directory = BBP_TESTS_DIR . '/data/templates';
		$callback  = function() use ( $directory ) {
			return $directory;
		};

		bbp_register_template_stack( $callback, -100 );

		return $callback;
	}
}
