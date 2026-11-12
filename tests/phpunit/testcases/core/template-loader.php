<?php

/**
 * Tests for the core template loader.
 *
 * @group core
 * @group template_loader
 * @ticket 3706
 */
class BBP_Tests_Core_Template_Loader extends BBP_UnitTestCase {

	/**
	 * Original theme compatibility template candidates.
	 *
	 * @var array
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
		bbp_set_template_included( false );
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

		bbp_set_template_included( false );

		parent::tearDown();
	}

	/**
	 * @covers ::bbp_set_template_included
	 * @covers ::bbp_is_template_included
	 */
	public function test_template_included_state() {
		bbp_set_template_included( false );
		$this->assertFalse( bbp_is_template_included() );

		$this->assertSame( '/theme/template.php', bbp_set_template_included( '/theme/template.php' ) );
		$this->assertTrue( bbp_is_template_included() );

		bbp_set_template_included( false );
	}

	/**
	 * @covers ::bbp_template_include_theme_supports
	 */
	public function test_template_include_preserves_original_when_no_checker_matches() {
		$filtered  = 0;
		$templates = function() {
			return array(
				'bbp_missing_checker' => 'bbp_get_theme_canvas_template',
				'__return_false'      => 'bbp_get_theme_canvas_template',
			);
		};
		$filter    = function( $retval, $original ) use ( &$filtered ) {
			++$filtered;
			$this->assertSame( '/original.php', $original );
			$this->assertSame( '/original.php', $retval );

			return $retval;
		};

		add_filter( 'bbp_get_template_include_templates', $templates );
		add_filter( 'bbp_template_include_theme_supports', $filter, 10, 2 );
		try {
			$this->assertSame( '/original.php', bbp_template_include_theme_supports( '/original.php' ) );
			$this->assertFalse( bbp_is_template_included() );
			$this->assertSame( 1, $filtered );
		} finally {
			remove_filter( 'bbp_template_include_theme_supports', $filter, 10 );
			remove_filter( 'bbp_get_template_include_templates', $templates );
		}
	}

	/**
	 * @covers ::bbp_template_include_theme_supports
	 */
	public function test_template_include_preserves_original_when_getter_is_missing() {
		$templates = function() {
			return array( '__return_true' => 'bbp_missing_template_getter' );
		};

		add_filter( 'bbp_get_template_include_templates', $templates );
		try {
			$this->assertSame( '/original.php', bbp_template_include_theme_supports( '/original.php' ) );
			$this->assertFalse( bbp_is_template_included() );
		} finally {
			remove_filter( 'bbp_get_template_include_templates', $templates );
		}
	}

	/**
	 * @covers ::bbp_template_include_theme_supports
	 */
	public function test_template_include_continues_after_missing_getter() {
		$templates = function() {
			return array(
				'__return_true' => 'bbp_missing_template_getter',
				'wp_doing_ajax' => 'bbp_get_theme_canvas_template',
			);
		};
		$canvas    = function() {
			return '/theme/canvas.php';
		};

		add_filter( 'wp_doing_ajax', '__return_true' );
		add_filter( 'bbp_get_template_include_templates', $templates );
		add_filter( 'bbp_get_theme_canvas_template', $canvas );
		try {
			$this->assertSame( '/theme/canvas.php', bbp_template_include_theme_supports( '/original.php' ) );
			$this->assertTrue( bbp_is_template_included() );
		} finally {
			remove_filter( 'bbp_get_theme_canvas_template', $canvas );
			remove_filter( 'bbp_get_template_include_templates', $templates );
			remove_filter( 'wp_doing_ajax', '__return_true' );
		}
	}

	/**
	 * @covers ::bbp_template_include_theme_supports
	 */
	public function test_template_include_uses_first_matching_template() {
		$canvas_calls = 0;
		$compat_calls = 0;
		$templates    = function() {
			return array(
				'__return_true' => 'bbp_get_theme_canvas_template',
				'wp_doing_ajax' => 'bbp_get_theme_compat_template',
			);
		};
		$canvas       = function() use ( &$canvas_calls ) {
			++$canvas_calls;

			return '/theme/first.php';
		};
		$compat       = function() use ( &$compat_calls ) {
			++$compat_calls;

			return '/theme/second.php';
		};

		add_filter( 'wp_doing_ajax', '__return_true' );
		add_filter( 'bbp_get_template_include_templates', $templates );
		add_filter( 'bbp_get_theme_canvas_template', $canvas );
		add_filter( 'bbp_bbpress_template', $compat );
		try {
			$this->assertSame( '/theme/first.php', bbp_template_include_theme_supports( '/original.php' ) );
			$this->assertSame( 1, $canvas_calls );
			$this->assertSame( 0, $compat_calls );
		} finally {
			remove_filter( 'bbp_bbpress_template', $compat );
			remove_filter( 'bbp_get_theme_canvas_template', $canvas );
			remove_filter( 'bbp_get_template_include_templates', $templates );
			remove_filter( 'wp_doing_ajax', '__return_true' );
		}
	}

	/**
	 * @covers ::bbp_load_theme_functions
	 */
	public function test_load_theme_functions_locates_the_custom_functions_file() {
		global $pagenow;

		$old_pagenow = $pagenow;
		$located     = array();
		$capture     = function( $file, $template_name, $template_names, $locations, $load ) use ( &$located ) {
			$located = array( $template_name, $template_names, $load );
		};

		$pagenow = 'index.php';
		add_action( 'bbp_locate_template', $capture, 10, 5 );
		try {
			bbp_load_theme_functions();
			$this->assertSame( array( 'bbpress-functions.php', 'bbpress-functions.php', true ), $located );
		} finally {
			remove_action( 'bbp_locate_template', $capture, 10 );
			$pagenow = $old_pagenow;
		}
	}

	/**
	 * @covers ::bbp_load_theme_functions
	 */
	public function test_load_theme_functions_stops_during_deactivation() {
		global $pagenow;

		$old_screen  = isset( $GLOBALS['current_screen'] ) ? $GLOBALS['current_screen'] : null;
		$old_pagenow = $pagenow;
		$old_request = $_REQUEST;
		$old_get     = $_GET;
		$located     = 0;
		$capture     = function() use ( &$located ) {
			++$located;
		};

		set_current_screen( 'plugins' );
		$pagenow            = 'plugins.php';
		$_REQUEST['action'] = 'deactivate';
		$_GET['plugin']     = bbpress()->basename;
		add_action( 'bbp_locate_template', $capture );
		try {
			$this->assertTrue( bbp_is_deactivation() );
			bbp_load_theme_functions();
			$this->assertSame( 0, $located );
		} finally {
			remove_action( 'bbp_locate_template', $capture );
			$GLOBALS['current_screen'] = $old_screen;
			$pagenow                   = $old_pagenow;
			$_REQUEST                  = $old_request;
			$_GET                      = $old_get;
		}
	}

	/**
	 * @dataProvider template_getter_cases
	 *
	 * @covers ::bbp_get_search_template
	 * @covers ::bbp_get_single_forum_template
	 * @covers ::bbp_get_forum_archive_template
	 * @covers ::bbp_get_forum_edit_template
	 * @covers ::bbp_get_single_topic_template
	 * @covers ::bbp_get_topic_archive_template
	 * @covers ::bbp_get_topic_edit_template
	 * @covers ::bbp_get_topic_split_template
	 * @covers ::bbp_get_topic_merge_template
	 * @covers ::bbp_get_single_reply_template
	 * @covers ::bbp_get_reply_edit_template
	 * @covers ::bbp_get_reply_move_template
	 * @covers ::bbp_get_theme_compat_template
	 *
	 * @param string $getter    Template getter callback.
	 * @param string $hook      Query template hook segment.
	 * @param array  $templates Expected template candidates.
	 */
	public function test_template_getter_candidates( $getter, $hook, $templates ) {
		$this->assert_template_candidates( $getter, $hook, $templates );
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
	 * Template getter cases.
	 *
	 * @return array[] Template getter cases.
	 */
	public function template_getter_cases() {
		$forum = bbp_get_forum_post_type();
		$topic = bbp_get_topic_post_type();
		$reply = bbp_get_reply_post_type();

		return array(
			'search' => array( 'bbp_get_search_template', 'singlesearch', array( 'page-forum-search.php', 'forum-search.php' ) ),
			'single forum' => array( 'bbp_get_single_forum_template', 'singleforum', array( "single-{$forum}.php" ) ),
			'forum archive' => array( 'bbp_get_forum_archive_template', 'forumarchive', array( "archive-{$forum}.php" ) ),
			'forum edit' => array( 'bbp_get_forum_edit_template', 'forumedit', array( "single-{$forum}-edit.php" ) ),
			'single topic' => array( 'bbp_get_single_topic_template', 'singletopic', array( "single-{$topic}.php" ) ),
			'topic archive' => array( 'bbp_get_topic_archive_template', 'topicarchive', array( "archive-{$topic}.php" ) ),
			'topic edit' => array( 'bbp_get_topic_edit_template', 'topicedit', array( "single-{$topic}-edit.php" ) ),
			'topic split' => array( 'bbp_get_topic_split_template', 'topicsplit', array( "single-{$topic}-split.php" ) ),
			'topic merge' => array( 'bbp_get_topic_merge_template', 'topicmerge', array( "single-{$topic}-merge.php" ) ),
			'single reply' => array( 'bbp_get_single_reply_template', 'singlereply', array( "single-{$reply}.php" ) ),
			'reply edit' => array( 'bbp_get_reply_edit_template', 'replyedit', array( "single-{$reply}-edit.php" ) ),
			'reply move' => array( 'bbp_get_reply_move_template', 'replymove', array( "single-{$reply}-move.php" ) ),
			'theme compatibility' => array(
				'bbp_get_theme_compat_template',
				'bbpress',
				array( 'plugin-bbpress.php', 'bbpress.php', 'forums.php', 'forum.php', 'generic.php', 'page.php', 'single.php', 'singular.php', 'index.php' ),
			),
		);
	}

	/**
	 * @covers ::bbp_get_single_user_template
	 * @covers ::bbp_get_single_user_edit_template
	 * @covers ::bbp_get_favorites_template
	 * @covers ::bbp_get_subscriptions_template
	 */
	public function test_user_template_getter_candidates() {
		$user_id       = $this->factory->user->create( array( 'user_nicename' => 'template-user' ) );
		$nicename      = get_userdata( $user_id )->user_nicename;
		$old_displayed = bbpress()->displayed_user;

		bbpress()->displayed_user = get_userdata( $user_id );
		try {
			$this->assert_template_candidates(
				'bbp_get_single_user_template',
				'profile',
				array( "single-user-{$nicename}.php", "single-user-{$user_id}.php", 'single-user.php', 'user.php' )
			);
			$this->assert_template_candidates(
				'bbp_get_single_user_edit_template',
				'profileedit',
				array( "single-user-edit-{$nicename}.php", "single-user-edit-{$user_id}.php", 'single-user-edit.php', 'user-edit.php', 'user.php' )
			);
			$this->assert_template_candidates(
				'bbp_get_favorites_template',
				'favorites',
				array( "single-user-favorites-{$nicename}.php", "single-user-favorites-{$user_id}.php", "favorites-{$nicename}.php", "favorites-{$user_id}.php", 'favorites.php', 'user.php' )
			);
			$this->assert_template_candidates(
				'bbp_get_subscriptions_template',
				'subscriptions',
				array( "single-user-subscriptions-{$nicename}.php", "single-user-subscriptions-{$user_id}.php", "subscriptions-{$nicename}.php", "subscriptions-{$user_id}.php", 'subscriptions.php', 'user.php' )
			);
		} finally {
			bbpress()->displayed_user = $old_displayed;
		}
	}

	/**
	 * @covers ::bbp_get_single_view_template
	 */
	public function test_view_template_getter_candidates() {
		$old_view = bbpress()->current_view_id;
		bbpress()->current_view_id = 'popular';

		try {
			$this->assert_template_candidates(
				'bbp_get_single_view_template',
				'singleview',
				array( 'single-view-popular.php', 'view-popular.php', 'single-view.php', 'view.php' )
			);
		} finally {
			bbpress()->current_view_id = $old_view;
		}
	}

	/**
	 * @covers ::bbp_get_topic_tag_template
	 * @covers ::bbp_get_topic_tag_edit_template
	 */
	public function test_topic_tag_template_getter_candidates() {
		$slug = function() {
			return 'support';
		};
		$tax  = function() {
			return 'custom-topic-tag';
		};

		add_filter( 'bbp_get_topic_tag_slug', $slug );
		add_filter( 'bbp_get_topic_tag_tax_id', $tax );
		try {
			$this->assert_template_candidates(
				'bbp_get_topic_tag_template',
				'topictag',
				array( 'taxonomy-support.php', 'taxonomy-custom-topic-tag.php' )
			);
			$this->assert_template_candidates(
				'bbp_get_topic_tag_edit_template',
				'topictagedit',
				array( 'taxonomy-support-edit.php', 'taxonomy-custom-topic-tag-edit.php' )
			);
		} finally {
			remove_filter( 'bbp_get_topic_tag_tax_id', $tax );
			remove_filter( 'bbp_get_topic_tag_slug', $slug );
		}
	}

	/**
	 * @covers ::bbp_get_theme_canvas_template
	 */
	public function test_theme_canvas_template_is_filterable() {
		$filter = function( $template ) {
			$this->assertSame( ABSPATH . WPINC . '/template-canvas.php', $template );

			return '/theme/filtered-canvas.php';
		};

		add_filter( 'bbp_get_theme_canvas_template', $filter );
		try {
			$this->assertSame( '/theme/filtered-canvas.php', bbp_get_theme_canvas_template() );
		} finally {
			remove_filter( 'bbp_get_theme_canvas_template', $filter );
		}
	}

	/**
	 * @covers ::bbp_get_template_include_templates
	 */
	public function test_template_include_map_order_and_filter() {
		$expected = array(
			'bbp_is_single_user_edit' => 'bbp_get_single_user_edit_template',
			'bbp_is_favorites'        => 'bbp_get_favorites_template',
			'bbp_is_subscriptions'    => 'bbp_get_subscriptions_template',
			'bbp_is_single_user'      => 'bbp_get_single_user_template',
			'bbp_is_single_view'      => 'bbp_get_single_view_template',
			'bbp_is_search'           => 'bbp_get_search_template',
			'bbp_is_forum_edit'       => 'bbp_get_forum_edit_template',
			'bbp_is_single_forum'     => 'bbp_get_single_forum_template',
			'bbp_is_forum_archive'    => 'bbp_get_forum_archive_template',
			'bbp_is_topic_merge'      => 'bbp_get_topic_merge_template',
			'bbp_is_topic_split'      => 'bbp_get_topic_split_template',
			'bbp_is_topic_edit'       => 'bbp_get_topic_edit_template',
			'bbp_is_single_topic'     => 'bbp_get_single_topic_template',
			'bbp_is_topic_archive'    => 'bbp_get_topic_archive_template',
			'bbp_is_reply_move'       => 'bbp_get_reply_move_template',
			'bbp_is_reply_edit'       => 'bbp_get_reply_edit_template',
			'bbp_is_single_reply'     => 'bbp_get_single_reply_template',
			'bbp_is_topic_tag_edit'   => 'bbp_get_topic_tag_edit_template',
			'bbp_is_topic_tag'        => 'bbp_get_topic_tag_template',
		);
		$filter = function( $templates ) {
			$templates['bbp_is_custom'] = 'bbp_get_custom_template';

			return $templates;
		};

		add_filter( 'bbp_get_template_include_templates', $filter );
		try {
			$templates = bbp_get_template_include_templates();
			$this->assertSame( $expected, array_slice( $templates, 0, -1, true ) );
			$this->assertSame( 'bbp_get_custom_template', $templates['bbp_is_custom'] );
		} finally {
			remove_filter( 'bbp_get_template_include_templates', $filter );
		}
	}

	/**
	 * @covers ::bbp_get_theme_compat_templates
	 */
	public function test_deprecated_theme_compat_getter_returns_singular_template() {
		$filter = function() {
			return '/theme/compat.php';
		};

		add_filter( 'bbp_bbpress_template', $filter );
		try {
			$this->assertSame( '/theme/compat.php', bbp_get_theme_compat_templates() );
		} finally {
			remove_filter( 'bbp_bbpress_template', $filter );
		}
	}

	/**
	 * Assert the candidates passed by a template getter.
	 *
	 * @param string $getter    Template getter callback.
	 * @param string $hook      Query template hook segment.
	 * @param array  $expected  Expected template candidates.
	 */
	private function assert_template_candidates( $getter, $hook, $expected ) {
		$actual  = array();
		$capture = function( $templates ) use ( &$actual ) {
			$actual = $templates;

			return $templates;
		};
		$filter  = function() {

			return '/captured-template.php';
		};

		add_filter( "bbp_get_{$hook}_template", $capture );
		add_filter( "bbp_{$hook}_template", $filter );
		try {
			$this->assertSame( '/captured-template.php', call_user_func( $getter ) );
			$this->assertSame( $expected, $actual );
		} finally {
			remove_filter( "bbp_{$hook}_template", $filter );
			remove_filter( "bbp_get_{$hook}_template", $capture );
		}
	}
}
