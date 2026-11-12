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

	/**
	 * Whether Block Template content existed before the test.
	 *
	 * @var bool
	 */
	private $had_block_template_content;

	/**
	 * Block Template content from before the test.
	 *
	 * @var string|null
	 */
	private $original_block_template_content;

	/**
	 * Whether a Block Template ID existed before the test.
	 *
	 * @var bool
	 */
	private $had_block_template_id;

	/**
	 * Block Template ID from before the test.
	 *
	 * @var string|null
	 */
	private $original_block_template_id;

	/**
	 * Whether a theme compatibility hierarchy existed before the test.
	 *
	 * @var bool
	 */
	private $had_theme_compat_templates;

	/**
	 * Theme compatibility hierarchy from before the test.
	 *
	 * @var array|null
	 */
	private $original_theme_compat_templates;

	public function setUp(): void {
		parent::setUp();

		$this->original_theme                        = get_stylesheet();
		$this->original_theme_directories            = $GLOBALS['wp_theme_directories'];
		$this->theme_root                            = BBP_TESTS_DIR . '/data/themes';
		$this->had_block_template_content            = isset( $GLOBALS['_wp_current_template_content'] );
		$this->original_block_template_content       = $this->had_block_template_content
			? $GLOBALS['_wp_current_template_content']
			: null;
		$this->had_block_template_id                 = isset( $GLOBALS['_wp_current_template_id'] );
		$this->original_block_template_id            = $this->had_block_template_id
			? $GLOBALS['_wp_current_template_id']
			: null;
		$this->had_theme_compat_templates            = property_exists( bbpress()->theme_compat, 'templates' );
		$this->original_theme_compat_templates       = $this->had_theme_compat_templates
			? bbpress()->theme_compat->templates
			: null;

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

		if ( $this->had_block_template_content ) {
			$GLOBALS['_wp_current_template_content'] = $this->original_block_template_content;
		} else {
			unset( $GLOBALS['_wp_current_template_content'] );
		}

		if ( $this->had_block_template_id ) {
			$GLOBALS['_wp_current_template_id'] = $this->original_block_template_id;
		} else {
			unset( $GLOBALS['_wp_current_template_id'] );
		}

		if ( $this->had_theme_compat_templates ) {
			bbp_set_theme_compat_templates( $this->original_theme_compat_templates );
		} else {
			unset( bbpress()->theme_compat->templates );
		}

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
	 * @covers ::bbp_get_theme_compat_template_hierarchy
	 * @ticket 3487
	 */
	public function test_theme_compat_template_hierarchy_is_filterable() {
		$filter = function() {
			return array( 'custom-bbpress.php' );
		};

		add_filter( 'bbp_get_theme_compat_template_hierarchy', $filter );

		try {
			$this->assertSame( array( 'custom-bbpress.php' ), bbp_get_theme_compat_template_hierarchy() );
		} finally {
			remove_filter( 'bbp_get_theme_compat_template_hierarchy', $filter );
		}
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
	 * @covers ::bbp_get_theme_compat_excerpt
	 * @ticket 3784
	 */
	public function test_theme_compat_excerpt_accepts_one_filter_argument() {
		bbp_theme_compat_reset_post( array( 'post_content' => 'Generated bbPress content' ) );

		$this->assertSame( 'Existing excerpt', apply_filters( 'get_the_excerpt', 'Existing excerpt' ) );
		$this->assertSame( '', bbp_get_theme_compat_excerpt( '' ) );
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
	 * @covers ::bbp_get_theme_canvas_template
	 * @covers ::bbp_template_include_theme_compat
	 * @ticket 3487
	 */
	public function test_block_theme_compat_uses_post_content_template() {
		global $_wp_current_template_content;

		$original_query  = $GLOBALS['wp_query'];
		$original_post   = isset( $GLOBALS['post'] ) ? $GLOBALS['post'] : null;
		$content         = '<div class="bbpress">Generated bbPress content</div>';
		$archive_content = 'Original archive topic content';
		$archive_post_id = $this->factory->post->create( array( 'post_content' => $archive_content ) );

		remove_theme_support( 'block-templates' );
		switch_theme( 'bbp-block-theme' );
		wp_enable_block_templates();

		try {
			$GLOBALS['wp_query'] = new WP_Query( array( 'p' => $archive_post_id ) );
			$GLOBALS['wp_query']->the_post();

			locate_block_template( '', 'archive', array( 'archive.php', 'index.php' ) );
			$this->assertStringContainsString( 'wp:post-excerpt', $_wp_current_template_content );

			bbp_theme_compat_reset_post( array( 'post_content' => $content ) );
			$template = bbp_template_include_theme_compat( '/original-template.php' );
			$html     = get_the_block_template_html();

			$this->assertSame( ABSPATH . WPINC . '/template-canvas.php', $template );
			$this->assertStringContainsString( 'wp:post-content', $_wp_current_template_content );
			$this->assertStringNotContainsString( 'wp:post-excerpt', $_wp_current_template_content );
			$this->assertStringContainsString( $content, $html );
			$this->assertStringNotContainsString( $archive_content, $html );
		} finally {
			bbp_restore_all_filters( 'the_content' );
			bbp_set_theme_compat_active( false );
			$GLOBALS['wp_query'] = $original_query;
			$GLOBALS['post']     = $original_post;

			if ( ( $original_query instanceof WP_Query ) && ( $original_post instanceof WP_Post ) ) {
				$original_query->setup_postdata( $original_post );
			}
		}
	}

	/**
	 * @covers ::bbp_get_theme_canvas_template
	 * @ticket 3487
	 */
	public function test_block_theme_compat_preserves_query_specific_template() {
		global $_wp_current_template_content;

		remove_theme_support( 'block-templates' );
		switch_theme( 'bbp-block-theme' );
		wp_enable_block_templates();

		bbp_set_theme_compat_templates( array( 'single-forum.php' ) );

		$this->assertSame( ABSPATH . WPINC . '/template-canvas.php', $this->get_theme_compat_template() );
		$this->assertStringContainsString( 'wp:bbpress/single-forum', $_wp_current_template_content );
		$this->assertStringNotContainsString( 'wp:post-content', $_wp_current_template_content );
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
	 * @covers ::bbp_remove_adjacent_posts
	 */
	public function test_remove_adjacent_posts_disables_wordpress_post_navigation() {
		$forum_id   = $this->factory->forum->create();
		$previous   = $this->factory->topic->create( array( 'post_parent' => $forum_id, 'post_date' => '2020-01-01 12:00:00', 'topic_meta' => array( 'forum_id' => $forum_id ) ) );
		$current_id = $this->factory->topic->create( array( 'post_parent' => $forum_id, 'post_date' => '2021-01-01 12:00:00', 'topic_meta' => array( 'forum_id' => $forum_id ) ) );
		$next       = $this->factory->topic->create( array( 'post_parent' => $forum_id, 'post_date' => '2022-01-01 12:00:00', 'topic_meta' => array( 'forum_id' => $forum_id ) ) );
		$override   = function() {
			return 'overridden link';
		};

		$this->go_to_without_reregistering_blocks( get_permalink( $current_id ) );
		try {
			$this->assertTrue( is_bbpress() );
			bbp_template_redirect();
			$this->assertSame( '', get_previous_post() );
			$this->assertSame( '', get_next_post() );

			bbp_template_include_theme_compat( '/original-template.php' );
			$this->assertSame( bbp_get_empty_datetime(), $GLOBALS['post']->post_date );
			$this->assertSame( '', get_previous_post() );
			$this->assertSame( '', get_next_post() );
			$this->assertSame( '', get_previous_post_link() );
			$this->assertSame( '', get_next_post_link() );
			$this->assertNull( get_adjacent_post_rel_link( '%title', false, '', true ) );
			$this->assertNull( get_adjacent_post_rel_link( '%title', false, '', false ) );
			$this->assertStringNotContainsString( get_permalink( $previous ), do_blocks( '<!-- wp:post-navigation-link {"type":"previous","showTitle":true} /-->' ) );
			$this->assertStringNotContainsString( get_permalink( $next ), do_blocks( '<!-- wp:post-navigation-link {"type":"next","showTitle":true} /-->' ) );

			add_filter( 'previous_post_link', $override, 20 );
			$this->assertSame( 'overridden link', get_previous_post_link() );
			remove_filter( 'previous_post_link', $override, 20 );
		} finally {
			remove_filter( 'get_next_post_where', 'bbp_filter_adjacent_post_where', 10 );
			remove_filter( 'get_previous_post_where', 'bbp_filter_adjacent_post_where', 10 );
			remove_filter( 'next_post_link', '__return_empty_string', 10 );
			remove_filter( 'previous_post_link', '__return_empty_string', 10 );
			remove_filter( 'next_post_rel_link', '__return_empty_string', 10 );
			remove_filter( 'previous_post_rel_link', '__return_empty_string', 10 );
			bbp_set_theme_compat_active( false );
			$this->go_to_without_reregistering_blocks( '/' );
		}
	}

	/**
	 * @covers ::bbp_remove_adjacent_posts
	 */
	public function test_remove_adjacent_posts_preserves_other_post_navigation() {
		$previous = self::factory()->post->create( array( 'post_date' => '2020-01-01 12:00:00' ) );
		$current  = self::factory()->post->create( array( 'post_date' => '2021-01-01 12:00:00' ) );

		$this->go_to_without_reregistering_blocks( get_permalink( $current ) );
		try {
			$this->assertFalse( is_bbpress() );
			bbp_remove_adjacent_posts();
			$this->assertStringContainsString( get_permalink( $previous ), get_previous_post_link() );
			$this->assertStringContainsString( get_permalink( $previous ), get_adjacent_post_rel_link() );
			$this->assertStringContainsString( get_permalink( $previous ), do_blocks( '<!-- wp:post-navigation-link {"type":"previous","showTitle":true} /-->' ) );
		} finally {
			$this->go_to_without_reregistering_blocks( '/' );
		}
	}

	/**
	 * Go to a URL without registering bbPress blocks again.
	 *
	 * @param string $url URL to visit.
	 */
	private function go_to_without_reregistering_blocks( $url ) {
		remove_action( 'bbp_register', 'bbp_register_blocks', 10 );
		try {
			$this->go_to( $url );
		} finally {
			add_action( 'bbp_register', 'bbp_register_blocks', 10 );
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
