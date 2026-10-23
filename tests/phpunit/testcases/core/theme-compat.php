<?php

/**
 * @group core
 * @group theme_compat
 */
class BBP_Tests_Core_Theme_Compat extends BBP_UnitTestCase {

	/**
	 * Original active theme stylesheet.
	 *
	 * @var string
	 */
	private $original_theme;

	/**
	 * Original registered theme directories.
	 *
	 * @var array
	 */
	private $original_theme_directories;

	/**
	 * Test theme root.
	 *
	 * @var string
	 */
	private $theme_root;

	public function setUp(): void {
		parent::setUp();

		$this->original_theme             = get_stylesheet();
		$this->original_theme_directories = $GLOBALS['wp_theme_directories'];
		$this->theme_root                 = BBP_TESTS_DIR . '/data/themes';

		register_theme_directory( $this->theme_root );
		wp_clean_themes_cache();
	}

	public function tearDown(): void {
		bbp_restore_all_filters( 'the_content' );
		bbp_set_theme_compat_active( false );
		bbp_set_template_included( false );
		remove_theme_support( 'block-templates' );

		switch_theme( $this->original_theme );
		$GLOBALS['wp_theme_directories'] = $this->original_theme_directories;
		wp_clean_themes_cache();

		parent::tearDown();
	}

	/**
	 * @covers ::bbp_remove_all_filters
	 * @covers ::bbp_restore_all_filters
	 */
	public function test_theme_compat_filter_backup_and_restore_preserves_priority_order() {
		$tag      = 'bbp_test_theme_compat_filters';
		$first    = function( $value ) {
			return $value . 'first-';
		};
		$second   = function( $value ) {
			return $value . 'second';
		};
		$priority = 20;

		add_filter( $tag, $first, 10 );
		add_filter( $tag, $second, $priority );

		try {
			$this->assertTrue( bbp_remove_all_filters( $tag, 10 ) );
			$this->assertFalse( has_filter( $tag, $first ) );
			$this->assertSame( $priority, has_filter( $tag, $second ) );

			$this->assertTrue( bbp_restore_all_filters( $tag, 10 ) );
			$this->assertSame( 10, has_filter( $tag, $first ) );
			$this->assertSame( $priority, has_filter( $tag, $second ) );
			$this->assertSame( 'first-second', apply_filters( $tag, '' ) );
			$this->assertTrue( bbp_restore_all_filters( $tag ) );
			$this->assertSame( 'first-second', apply_filters( $tag, '' ) );
		} finally {
			remove_all_filters( $tag );
		}
	}

	/**
	 * @covers BBP_Theme_Compat
	 */
	public function test_theme_compat_package_exposes_registered_properties() {
		$theme = new BBP_Theme_Compat(
			array(
				'id'      => 'test',
				'name'    => 'Test Theme',
				'version' => '1.0',
				'dir'     => '/tmp/test-theme',
				'url'     => 'https://example.org/test-theme',
			)
		);

		$this->assertSame( 'test', $theme->id );
		$this->assertSame( 'Test Theme', $theme->name );
		$this->assertSame( '/tmp/test-theme', $theme->get_dir() );
		$this->assertSame( '', $theme->missing );

		$theme->version = '2.0';
		$this->assertSame( '2.0', $theme->version );
	}

	/**
	 * @covers ::bbp_get_current_template_pack
	 * @covers ::bbp_get_theme_compat_id
	 * @covers ::bbp_get_theme_compat_name
	 * @covers ::bbp_get_theme_compat_version
	 * @covers ::bbp_get_theme_compat_dir
	 * @covers ::bbp_get_theme_compat_url
	 */
	public function test_theme_compat_package_accessors_and_filter() {
		$bbp            = bbpress();
		$original_theme = isset( $bbp->theme_compat->theme ) ? $bbp->theme_compat->theme : null;
		$theme          = new BBP_Theme_Compat(
			array(
				'id'      => 'test',
				'name'    => 'Test Theme',
				'version' => '1.0',
				'dir'     => '/tmp/test-theme',
				'url'     => 'https://example.org/test-theme',
			)
		);
		$filter         = function() {
			return 'filtered';
		};

		try {
			unset( $bbp->theme_compat->theme );
			$this->assertInstanceOf( 'BBP_Theme_Compat', bbp_get_current_template_pack() );

			$bbp->theme_compat->theme = $theme;
			$this->assertSame( $theme, bbp_get_current_template_pack() );
			$this->assertSame( 'test', bbp_get_theme_compat_id() );
			$this->assertSame( 'Test Theme', bbp_get_theme_compat_name() );
			$this->assertSame( '1.0', bbp_get_theme_compat_version() );
			$this->assertSame( '/tmp/test-theme', bbp_get_theme_compat_dir() );
			$this->assertSame( 'https://example.org/test-theme', bbp_get_theme_compat_url() );

			add_filter( 'bbp_get_theme_compat_id', $filter );
			$this->assertSame( 'filtered', bbp_get_theme_compat_id() );
		} finally {
			remove_filter( 'bbp_get_theme_compat_id', $filter );
			$bbp->theme_compat->theme = $original_theme;
		}
	}

	/**
	 * @covers ::bbp_register_theme_package
	 * @covers ::bbp_setup_theme_compat
	 */
	public function test_theme_packages_register_override_and_activate() {
		$bbp               = bbpress();
		$original_packages = $bbp->theme_compat->packages;
		$original_theme    = isset( $bbp->theme_compat->theme ) ? $bbp->theme_compat->theme : null;
		$first             = new BBP_Theme_Compat( array( 'id' => 'test', 'dir' => '/tmp/first' ) );
		$second            = new BBP_Theme_Compat( array( 'id' => 'test', 'dir' => '/tmp/second' ) );

		try {
			unset( $bbp->theme_compat->theme );
			$bbp->theme_compat->packages = array();

			bbp_register_theme_package( 'invalid' );
			$this->assertSame( array(), $bbp->theme_compat->packages );

			bbp_register_theme_package( $first );
			bbp_register_theme_package( $second, false );
			$this->assertSame( $first, $bbp->theme_compat->packages['test'] );

			bbp_register_theme_package( $second );
			$this->assertSame( $second, $bbp->theme_compat->packages['test'] );

			bbp_setup_theme_compat( 'missing' );
			$this->assertFalse( isset( $bbp->theme_compat->theme ) );

			bbp_setup_theme_compat( 'test' );
			$this->assertSame( $second, $bbp->theme_compat->theme );
			$this->assertSame( 10, has_filter( 'bbp_template_stack', array( $second, 'get_dir' ) ) );

			bbp_setup_theme_compat( 'missing' );
			$this->assertSame( $second, $bbp->theme_compat->theme );
		} finally {
			bbp_deregister_template_stack( array( $second, 'get_dir' ) );
			$bbp->theme_compat->packages = $original_packages;
			$bbp->theme_compat->theme    = $original_theme;
		}
	}

	/**
	 * @covers ::bbp_is_theme_compat_active
	 * @covers ::bbp_set_theme_compat_active
	 * @covers ::bbp_set_theme_compat_templates
	 * @covers ::bbp_set_theme_compat_template
	 * @covers ::bbp_set_theme_compat_original_template
	 * @covers ::bbp_is_theme_compat_original_template
	 */
	public function test_theme_compat_state_helpers() {
		$compat                = bbpress()->theme_compat;
		$had_templates         = property_exists( $compat, 'templates' );
		$had_template          = property_exists( $compat, 'template' );
		$had_original_template = property_exists( $compat, 'original_template' );
		$original_templates    = $had_templates ? $compat->templates : null;
		$original_template     = $had_template ? $compat->template : null;
		$original_original     = $had_original_template ? $compat->original_template : null;

		try {
			$this->assertFalse( bbp_set_theme_compat_active( false ) );
			$this->assertFalse( bbp_is_theme_compat_active() );
			$this->assertTrue( bbp_set_theme_compat_active() );
			$this->assertTrue( bbp_is_theme_compat_active() );

			$this->assertSame( array( 'single.php', 'index.php' ), bbp_set_theme_compat_templates( array( 'single.php', 'index.php' ) ) );
			$this->assertSame( 'single.php', bbp_set_theme_compat_template( 'single.php' ) );
			$this->assertSame( '/themes/original.php', bbp_set_theme_compat_original_template( '/themes/original.php' ) );
			$this->assertTrue( bbp_is_theme_compat_original_template( '/themes/original.php' ) );
			$this->assertFalse( bbp_is_theme_compat_original_template( '/themes/other.php' ) );
			bbp_set_theme_compat_original_template( '' );
			$this->assertFalse( bbp_is_theme_compat_original_template( '' ) );
		} finally {
			if ( $had_templates ) {
				bbp_set_theme_compat_templates( $original_templates );
			} else {
				unset( $compat->templates );
			}

			if ( $had_template ) {
				bbp_set_theme_compat_template( $original_template );
			} else {
				unset( $compat->template );
			}

			if ( $had_original_template ) {
				bbp_set_theme_compat_original_template( $original_original );
			} else {
				unset( $compat->original_template );
			}
		}
	}

	/**
	 * @covers ::bbp_do_not_redirect_edits
	 * @covers ::bbp_do_not_redirect_paginations
	 */
	public function test_theme_compat_redirect_helpers_only_cancel_matching_pretty_urls() {
		$wp_query       = bbp_get_wp_query();
		$original_query = $wp_query->query;
		$redirect       = 'https://example.org/canonical';

		add_filter( 'bbp_pretty_urls', '__return_false' );
		add_filter( 'bbp_is_edit', '__return_true' );
		$this->assertSame( $redirect, bbp_do_not_redirect_edits( $redirect ) );
		remove_filter( 'bbp_pretty_urls', '__return_false' );

		add_filter( 'bbp_pretty_urls', '__return_true' );
		try {
			$this->assertSame( '', bbp_do_not_redirect_edits( $redirect ) );
			remove_filter( 'bbp_is_edit', '__return_true' );
			$this->assertSame( $redirect, bbp_do_not_redirect_edits( $redirect ) );

			$wp_query->query['paged'] = 1;
			add_filter( 'bbp_is_single_topic', '__return_true' );
			$this->assertSame( $redirect, bbp_do_not_redirect_paginations( $redirect ) );

			$wp_query->query['paged'] = 2;
			$this->assertSame( '', bbp_do_not_redirect_paginations( $redirect ) );
			remove_filter( 'bbp_is_single_topic', '__return_true' );
			$this->assertSame( $redirect, bbp_do_not_redirect_paginations( $redirect ) );

			foreach ( array( 'bbp_is_single_forum', 'bbp_is_single_reply' ) as $filter ) {
				add_filter( $filter, '__return_true' );
				$this->assertSame( '', bbp_do_not_redirect_paginations( $redirect ) );
				remove_filter( $filter, '__return_true' );
			}
		} finally {
			remove_filter( 'bbp_pretty_urls', '__return_true' );
			remove_filter( 'bbp_is_edit', '__return_true' );
			remove_filter( 'bbp_is_single_topic', '__return_true' );
			remove_filter( 'bbp_is_single_forum', '__return_true' );
			remove_filter( 'bbp_is_single_reply', '__return_true' );
			$wp_query->query = $original_query;
		}
	}

	/**
	 * @covers ::bbp_remove_all_filters
	 * @covers ::bbp_restore_all_filters
	 */
	public function test_theme_compat_whole_filter_backup_and_restore() {
		$tag      = 'bbp_test_theme_compat_whole_filters';
		$first    = function( $value ) {
			return $value . 'first-';
		};
		$second   = function( $value ) {
			return $value . 'second';
		};
		$priority = 20;

		add_filter( $tag, $first, 10 );
		add_filter( $tag, $second, $priority );

		try {
			$this->assertTrue( bbp_remove_all_filters( $tag ) );
			$this->assertFalse( has_filter( $tag ) );
			$this->assertTrue( bbp_restore_all_filters( $tag ) );
			$this->assertSame( 10, has_filter( $tag, $first ) );
			$this->assertSame( $priority, has_filter( $tag, $second ) );
			$this->assertSame( 'first-second', apply_filters( $tag, '' ) );
		} finally {
			remove_all_filters( $tag );
		}
	}

	/**
	 * @covers ::bbp_force_comment_status
	 * @covers ::bbp_remove_adjacent_posts
	 */
	public function test_theme_compat_wordpress_integration_helpers() {
		$post_id  = self::factory()->post->create();
		$forum_id = $this->factory->forum->create();

		$this->assertTrue( bbp_force_comment_status( true, $post_id ) );
		$this->assertFalse( bbp_force_comment_status( true, $forum_id ) );

		$filter = function( $open, $original, $filtered_post_id, $post_type ) use ( $forum_id ) {
			$this->assertFalse( $open );
			$this->assertTrue( $original );
			$this->assertSame( $forum_id, $filtered_post_id );
			$this->assertSame( bbp_get_forum_post_type(), $post_type );

			return true;
		};

		add_filter( 'bbp_force_comment_status', $filter, 10, 4 );
		$this->assertTrue( bbp_force_comment_status( true, $forum_id ) );
		remove_filter( 'bbp_force_comment_status', $filter, 10 );

		add_action( 'wp_head', 'adjacent_posts_rel_link_wp_head', 10 );
		add_filter( 'is_bbpress', '__return_false' );
		bbp_remove_adjacent_posts();
		$this->assertSame( 10, has_action( 'wp_head', 'adjacent_posts_rel_link_wp_head' ) );
		remove_filter( 'is_bbpress', '__return_false' );

		add_filter( 'is_bbpress', '__return_true' );
		bbp_remove_adjacent_posts();
		$this->assertFalse( has_action( 'wp_head', 'adjacent_posts_rel_link_wp_head' ) );
		remove_filter( 'is_bbpress', '__return_true' );
	}

	/**
	 * @ticket 3431
	 */
	public function test_theme_compat_reset_post_uses_content_for_requested_excerpt() {
		$content = '<div class="bbpress">Generated bbPress content</div>';
		$original_query = $GLOBALS['wp_query'];
		$original_post  = isset( $GLOBALS['post'] ) ? $GLOBALS['post'] : null;

		$GLOBALS['wp_query'] = new WP_Query();
		$GLOBALS['wp_query']->post = get_post( $this->factory->post->create( array( 'post_excerpt' => '' ) ) );

		try {
			bbp_theme_compat_reset_post(
				array(
					'post_content' => $content,
				)
			);

			$this->assertSame( '', $GLOBALS['post']->post_excerpt );
			$this->assertFalse( has_excerpt() );
			$this->assertSame( $content, get_the_excerpt() );

			bbp_set_theme_compat_active( false );
			$this->assertSame( 'Generated bbPress content', get_the_excerpt() );
		} finally {
			$GLOBALS['wp_query'] = $original_query;
			$GLOBALS['post']     = $original_post;
		}
	}

	/**
	 * @ticket 3431
	 */
	public function test_theme_compat_excerpt_does_not_replace_other_posts() {
		$other_id = $this->factory->post->create(
			array(
				'post_content' => '<p>Other post content</p>',
				'post_excerpt' => '',
			)
		);

		bbp_theme_compat_reset_post(
			array(
				'post_content' => 'Generated bbPress content',
			)
		);

		$this->assertSame( 'Other post content', get_the_excerpt( $other_id ) );
	}

	/**
	 * @ticket 3431
	 */
	public function test_theme_compat_excerpt_does_not_replace_swapped_query_post() {
		$original_query = $GLOBALS['wp_query'];
		$original_post  = isset( $GLOBALS['post'] ) ? $GLOBALS['post'] : null;
		$other_post     = get_post( $this->factory->post->create(
			array(
				'post_content' => '<p>Other post content</p>',
				'post_excerpt' => '',
			)
		) );

		$GLOBALS['wp_query'] = new WP_Query();

		try {
			bbp_theme_compat_reset_post(
				array(
					'post_content' => '<div class="bbpress">Generated bbPress content</div>',
				)
			);

			$GLOBALS['wp_query']       = new WP_Query();
			$GLOBALS['wp_query']->post = $other_post;
			$GLOBALS['post']           = $other_post;

			$this->assertSame( 'Other post content', get_the_excerpt() );
		} finally {
			$GLOBALS['wp_query'] = $original_query;
			$GLOBALS['post']     = $original_post;
		}
	}

	/**
	 * @ticket 3431
	 */
	public function test_theme_compat_reset_post_preserves_explicit_excerpt() {
		bbp_theme_compat_reset_post(
			array(
				'post_content' => 'Generated bbPress content',
				'post_excerpt' => 'Explicit excerpt',
			)
		);

		$this->assertSame( 'Explicit excerpt', get_the_excerpt() );
	}

	/**
	 * @ticket 3487
	 * @dataProvider get_theme_compat_template_cases
	 *
	 * @param string $theme                   Theme stylesheet.
	 * @param bool   $supports_block_templates Whether the theme supports block templates.
	 * @param bool   $is_block_theme           Whether the theme is a Block Theme.
	 * @param string $expected_template        Expected template path, relative to the test theme root.
	 */
	public function test_theme_compat_template_selection( $theme, $supports_block_templates, $is_block_theme, $expected_template ) {
		remove_theme_support( 'block-templates' );
		switch_theme( $theme );
		wp_enable_block_templates();

		$this->assertSame( $supports_block_templates, current_theme_supports( 'block-templates' ) );
		$this->assertSame( $is_block_theme, wp_is_block_theme() );
		$this->assertSame(
			':canvas' === $expected_template
				? ABSPATH . WPINC . '/template-canvas.php'
				: $this->theme_root . '/' . $expected_template,
			$this->get_theme_compat_template()
		);
	}

	/**
	 * A root page's shortcode must run even when a bbPress post is ambient.
	 *
	 * @dataProvider get_root_page_content_cases
	 *
	 * @param string $post_type      Ambient bbPress post type.
	 * @param string $archive_filter Archive condition to enable.
	 */
	public function test_root_page_content_runs_shortcodes( $post_type, $archive_filter ) {
		$old_permalinks  = get_option( 'permalink_structure' );
		$old_post        = isset( $GLOBALS['post'] ) ? $GLOBALS['post'] : null;
		$old_query_post  = isset( $GLOBALS['wp_query']->post ) ? $GLOBALS['wp_query']->post : null;
		$old_query_posts = isset( $GLOBALS['wp_query']->posts ) ? $GLOBALS['wp_query']->posts : array();
		$root_slug       = ( bbp_get_forum_post_type() === $post_type ) ? bbp_get_root_slug() : bbp_get_topic_archive_slug();
		$page_id         = $this->factory->post->create(
			array(
				'post_type'    => 'page',
				'post_name'    => $root_slug,
				'post_content' => '[bbp-stats] [bbp_content_probe]',
			)
		);
		$ambient_id      = $this->factory->post->create( array( 'post_type' => $post_type ) );
		$render          = function( $output ) {
			return $output . 'root-page-bbpress-ran';
		};

		update_option( 'permalink_structure', '/%postname%/' );
		add_filter( $archive_filter, '__return_true' );
		add_filter( 'bbp_display_shortcode', $render );
		add_shortcode( 'bbp_content_probe', function() {
			return sprintf( 'root-page-shortcode-ran-%d-%d', get_the_ID(), $GLOBALS['id'] );
		} );

		try {
			$GLOBALS['post'] = get_post( $ambient_id );
			$GLOBALS['wp_query']->post  = $GLOBALS['post'];
			$GLOBALS['wp_query']->posts = array( $GLOBALS['post'] );
			$GLOBALS['wp_query']->setup_postdata( $GLOBALS['post'] );
			$content = bbp_get_theme_compat_page_content( get_post( $page_id ) );

			$this->assertSame( $ambient_id, $GLOBALS['post']->ID );
			$this->assertSame( $ambient_id, $GLOBALS['wp_query']->post->ID );
			$this->assertSame( $ambient_id, $GLOBALS['id'] );
			$this->assertStringContainsString( 'root-page-bbpress-ran', $content );
			$this->assertStringContainsString( "root-page-shortcode-ran-{$page_id}-{$page_id}", $content );

			bbp_template_include_theme_compat( '/original-template.php' );

			$this->assertSame( $page_id, $GLOBALS['post']->ID );
			$this->assertStringContainsString( "root-page-shortcode-ran-{$page_id}-{$page_id}", $GLOBALS['post']->post_content );
		} finally {
			remove_shortcode( 'bbp_content_probe' );
			remove_filter( 'bbp_display_shortcode', $render );
			remove_filter( $archive_filter, '__return_true' );
			update_option( 'permalink_structure', $old_permalinks );
			$GLOBALS['post'] = $old_post;
			$GLOBALS['wp_query']->post  = $old_query_post;
			$GLOBALS['wp_query']->posts = $old_query_posts;
		}
	}

	/**
	 * Root page cases for forum and topic archives.
	 *
	 * @return array[] Root page cases.
	 */
	public function get_root_page_content_cases() {
		return array(
			'forum archive' => array( bbp_get_forum_post_type(), 'bbp_is_forum_archive' ),
			'topic archive' => array( bbp_get_topic_post_type(), 'bbp_is_topic_archive' ),
		);
	}

	/**
	 * A root page's bbPress block and following shortcode must both run.
	 *
	 * @dataProvider get_root_page_content_cases
	 *
	 * @param string $post_type      Ambient bbPress post type.
	 * @param string $archive_filter Archive condition to enable.
	 */
	public function test_root_page_content_runs_bbpress_blocks( $post_type, $archive_filter ) {
		$old_permalinks  = get_option( 'permalink_structure' );
		$old_post        = isset( $GLOBALS['post'] ) ? $GLOBALS['post'] : null;
		$old_query_post  = isset( $GLOBALS['wp_query']->post ) ? $GLOBALS['wp_query']->post : null;
		$old_query_posts = isset( $GLOBALS['wp_query']->posts ) ? $GLOBALS['wp_query']->posts : array();
		$root_slug       = ( bbp_get_forum_post_type() === $post_type ) ? bbp_get_root_slug() : bbp_get_topic_archive_slug();
		$page_id         = $this->factory->post->create(
			array(
				'post_type'    => 'page',
				'post_name'    => $root_slug,
				'post_content' => '<!-- wp:bbpress/stats /--> [bbp_content_probe]',
			)
		);
		$ambient_id      = $this->factory->post->create( array( 'post_type' => $post_type ) );
		$render          = function( $output ) {
			return $output . 'root-block-bbpress-ran';
		};

		update_option( 'permalink_structure', '/%postname%/' );
		add_filter( $archive_filter, '__return_true' );
		add_filter( 'bbp_display_shortcode', $render );
		add_shortcode( 'bbp_content_probe', function() {
			return sprintf( 'root-block-shortcode-ran-%d-%d', get_the_ID(), $GLOBALS['id'] );
		} );

		try {
			$GLOBALS['post'] = get_post( $ambient_id );
			$GLOBALS['wp_query']->post  = $GLOBALS['post'];
			$GLOBALS['wp_query']->posts = array( $GLOBALS['post'] );
			$GLOBALS['wp_query']->setup_postdata( $GLOBALS['post'] );
			$content = bbp_get_theme_compat_page_content( get_post( $page_id ) );

			$this->assertSame( $ambient_id, $GLOBALS['post']->ID );
			$this->assertSame( $ambient_id, $GLOBALS['wp_query']->post->ID );
			$this->assertSame( $ambient_id, $GLOBALS['id'] );
			$this->assertStringContainsString( 'root-block-bbpress-ran', $content );
			$this->assertStringContainsString( "root-block-shortcode-ran-{$page_id}-{$page_id}", $content );
		} finally {
			remove_shortcode( 'bbp_content_probe' );
			remove_filter( 'bbp_display_shortcode', $render );
			remove_filter( $archive_filter, '__return_true' );
			update_option( 'permalink_structure', $old_permalinks );
			$GLOBALS['post'] = $old_post;
			$GLOBALS['wp_query']->post  = $old_query_post;
			$GLOBALS['wp_query']->posts = $old_query_posts;
		}
	}

	/**
	 * Root page filtering must restore post data when a content filter throws.
	 */
	public function test_root_page_content_restores_post_data_after_exception() {
		$page_id    = $this->factory->post->create( array( 'post_type' => 'page' ) );
		$ambient_id = $this->factory->forum->create();
		$old_post   = isset( $GLOBALS['post'] ) ? $GLOBALS['post'] : null;
		$old_query  = $GLOBALS['wp_query']->post;
		$throw      = function() {
			throw new RuntimeException( 'Stop filtering root page content.' );
		};

		$GLOBALS['post']          = get_post( $ambient_id );
		$GLOBALS['wp_query']->post = $GLOBALS['post'];
		$GLOBALS['wp_query']->setup_postdata( $GLOBALS['post'] );
		add_filter( 'the_content', $throw, 1 );

		try {
			bbp_get_theme_compat_page_content( get_post( $page_id ) );
			$this->fail( 'The content filter did not throw.' );
		} catch ( RuntimeException $exception ) {
			$this->assertSame( 'Stop filtering root page content.', $exception->getMessage() );
			$this->assertSame( $ambient_id, $GLOBALS['post']->ID );
			$this->assertSame( $ambient_id, $GLOBALS['wp_query']->post->ID );
			$this->assertSame( $ambient_id, $GLOBALS['id'] );
		} finally {
			remove_filter( 'the_content', $throw, 1 );
			$GLOBALS['post']          = $old_post;
			$GLOBALS['wp_query']->post = $old_query;
		}
	}

	/**
	 * Theme compatibility template selection cases.
	 *
	 * @return array[] Test cases.
	 */
	public function get_theme_compat_template_cases() {
		return array(
			'classic theme' => array(
				'theme'                    => 'bbp-classic-theme',
				'supports_block_templates' => false,
				'is_block_theme'           => false,
				'expected_template'        => 'bbp-classic-theme/index.php',
			),
			'classic theme with theme.json' => array(
				'theme'                    => 'bbp-classic-hybrid-theme',
				'supports_block_templates' => true,
				'is_block_theme'           => false,
				'expected_template'        => 'bbp-classic-hybrid-theme/index.php',
			),
			'classic theme with theme.json and bbpress.php' => array(
				'theme'                    => 'bbp-classic-theme-json',
				'supports_block_templates' => true,
				'is_block_theme'           => false,
				'expected_template'        => 'bbp-classic-theme-json/bbpress.php',
			),
			'classic child theme' => array(
				'theme'                    => 'bbp-classic-child-theme',
				'supports_block_templates' => true,
				'is_block_theme'           => false,
				'expected_template'        => 'bbp-classic-theme-json/bbpress.php',
			),
			'block theme' => array(
				'theme'                    => 'bbp-block-theme',
				'supports_block_templates' => true,
				'is_block_theme'           => true,
				'expected_template'        => ':canvas',
			),
			'block child theme' => array(
				'theme'                    => 'bbp-block-child-theme',
				'supports_block_templates' => true,
				'is_block_theme'           => true,
				'expected_template'        => ':canvas',
			),
		);
	}

	/**
	 * Run the theme compatibility template selection.
	 *
	 * @return string Selected template path.
	 */
	private function get_theme_compat_template() {
		bbp_set_theme_compat_active( true );
		bbp_set_template_included( false );

		$template = bbp_template_include_theme_compat( '/original-template.php' );

		bbp_restore_all_filters( 'the_content' );

		return $template;
	}
}
