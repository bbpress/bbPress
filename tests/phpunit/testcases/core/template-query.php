<?php

/**
 * Tests for parsing bbPress query state.
 *
 * @group core
 * @group template_functions
 * @ticket 3706
 */
class BBP_Tests_Core_Template_Query extends BBP_UnitTestCase {

	/**
	 * Original main query.
	 *
	 * @var WP_Query
	 */
	private $original_wp_the_query;

	/**
	 * Original current query.
	 *
	 * @var WP_Query
	 */
	private $original_wp_query;

	/**
	 * Original displayed user.
	 *
	 * @var mixed
	 */
	private $original_displayed_user;

	public function setUp(): void {
		parent::setUp();

		$this->original_wp_the_query  = $GLOBALS['wp_the_query'];
		$this->original_wp_query      = $GLOBALS['wp_query'];
		$this->original_displayed_user = isset( bbpress()->displayed_user ) ? bbpress()->displayed_user : null;
	}

	public function tearDown(): void {
		$GLOBALS['wp_the_query'] = $this->original_wp_the_query;
		$GLOBALS['wp_query']     = $this->original_wp_query;

		if ( is_null( $this->original_displayed_user ) ) {
			unset( bbpress()->displayed_user );
		} else {
			bbpress()->displayed_user = $this->original_displayed_user;
		}

		parent::tearDown();
	}

	/**
	 * @covers ::bbp_parse_query
	 */
	public function test_parse_query_ignores_non_main_and_suppressed_queries() {
		$main  = $this->set_main_query();
		$query = new WP_Query();
		$query->set( bbp_get_view_rewrite_id(), 'missing-view' );

		bbp_parse_query( $query );
		$this->assertFalse( isset( $query->bbp_is_404 ) );

		$main->set( 'suppress_filters', true );
		$main->set( bbp_get_view_rewrite_id(), 'missing-view' );
		bbp_parse_query( $main );
		$this->assertFalse( isset( $main->bbp_is_404 ) );
	}

	/**
	 * @covers ::bbp_parse_query
	 */
	public function test_parse_query_sets_user_profile_state() {
		$user_id = $this->factory->user->create();
		$query   = $this->set_main_query();

		$this->set_current_user( $user_id );
		$query->is_404  = true;
		$query->is_home = true;
		$query->set( bbp_get_user_rewrite_id(), $user_id );

		bbp_parse_query( $query );
		$this->assertTrue( $query->bbp_is_single_user );
		$this->assertTrue( $query->bbp_is_single_user_profile );
		$this->assertTrue( $query->bbp_is_single_user_home );
		$this->assertFalse( $query->bbp_is_404 );
		$this->assertFalse( $query->is_404 );
		$this->assertFalse( $query->is_home );
		$this->assertSame( $user_id, $query->get( 'bbp_user_id' ) );
		$this->assertSame( $user_id, bbpress()->displayed_user->ID );
	}

	/**
	 * @covers ::bbp_parse_query
	 */
	public function test_parse_query_clears_singular_state_on_user_routes() {
		$user_id = $this->factory->user->create();
		$cases   = array(
			'page ID'   => array( 'page_id', PHP_INT_MAX ),
			'page name' => array( 'pagename', 'missing-page' ),
			'post ID'   => array( 'p', PHP_INT_MAX ),
			'post name' => array( 'name', 'missing-post' ),
		);

		foreach ( $cases as $case => $query_var ) {
			$query = $this->set_main_query();
			$query->parse_query(
				array(
					bbp_get_user_rewrite_id()         => $user_id,
					bbp_get_user_replies_rewrite_id() => 1,
					$query_var[0]                      => $query_var[1],
				)
			);

			$this->assertTrue( $query->bbp_is_single_user, $case );
			$this->assertTrue( $query->bbp_is_single_user_replies, $case );
			$this->assertFalse( $query->is_page, $case );
			$this->assertFalse( $query->is_single, $case );
			$this->assertFalse( $query->is_singular, $case );
		}
	}

	/**
	 * @covers ::bbp_parse_query
	 */
	public function test_parse_query_sets_404_for_unknown_user() {
		$query = $this->set_main_query();
		$query->set( bbp_get_user_rewrite_id(), PHP_INT_MAX );

		bbp_parse_query( $query );
		$this->assertTrue( $query->bbp_is_404 );
		$this->assertFalse( isset( $query->bbp_is_single_user ) );
	}

	/**
	 * @covers ::bbp_parse_query
	 */
	public function test_parse_query_sets_user_section_states() {
		$user_id  = $this->factory->user->create();
		$sections = array(
			bbp_get_user_favorites_rewrite_id()     => 'bbp_is_single_user_favs',
			bbp_get_user_subscriptions_rewrite_id() => 'bbp_is_single_user_subs',
			bbp_get_user_topics_rewrite_id()        => 'bbp_is_single_user_topics',
			bbp_get_user_replies_rewrite_id()       => 'bbp_is_single_user_replies',
			bbp_get_user_engagements_rewrite_id()   => 'bbp_is_single_user_engagements',
		);

		foreach ( $sections as $rewrite_id => $property ) {
			$query = $this->set_main_query();
			$query->set( bbp_get_user_rewrite_id(), $user_id );
			$query->set( $rewrite_id, 1 );
			bbp_parse_query( $query );

			$this->assertTrue( $query->{$property} );
			$this->assertTrue( $query->bbp_is_single_user );
		}
	}

	/**
	 * @covers ::bbp_parse_query
	 */
	public function test_parse_query_sets_user_edit_state() {
		$user_id = $this->factory->user->create();
		$query   = $this->set_main_query();

		$query->set( bbp_get_user_rewrite_id(), $user_id );
		$query->set( bbp_get_edit_rewrite_id(), 1 );

		bbp_parse_query( $query );
		$this->assertTrue( $query->bbp_is_single_user_edit );
		$this->assertTrue( $query->bbp_is_single_user );
		$this->assertTrue( $query->bbp_is_edit );
		$this->assertTrue( function_exists( 'edit_user' ) );
	}

	/**
	 * @covers ::bbp_parse_query
	 */
	public function test_parse_query_finds_user_by_slug_with_pretty_permalinks() {
		$user_id = $this->factory->user->create( array( 'user_nicename' => 'profile-slug' ) );
		$query   = $this->set_main_query();

		$this->set_permalink_structure( '/%postname%/' );
		$query->set( bbp_get_user_rewrite_id(), 'profile-slug' );
		bbp_parse_query( $query );

		$this->assertTrue( $query->bbp_is_single_user );
		$this->assertSame( $user_id, $query->get( 'bbp_user_id' ) );
	}

	/**
	 * @covers ::bbp_parse_query
	 */
	public function test_parse_query_sets_404_for_non_scalar_user() {
		$this->factory->user->create( array( 'user_nicename' => 'profile-slug' ) );
		$query = $this->set_main_query();

		$this->set_permalink_structure( '/%postname%/' );
		$query->set( bbp_get_user_rewrite_id(), array( 'profile-slug' ) );
		bbp_parse_query( $query );

		$this->assertTrue( $query->bbp_is_404 );
		$this->assertFalse( isset( $query->bbp_is_single_user ) );
	}

	/**
	 * @covers ::bbp_parse_query
	 */
	public function test_parse_query_sets_view_and_missing_view_states() {
		$view_id = 'bbp-unit-test-view';
		bbp_register_view( $view_id, 'Unit Test View', array( 'post_type' => bbp_get_topic_post_type() ) );

		try {
			$query = $this->set_main_query();
			$query->is_home = true;
			$query->set( bbp_get_view_rewrite_id(), $view_id );
			bbp_parse_query( $query );
			$this->assertTrue( $query->bbp_is_view );
			$this->assertFalse( $query->bbp_is_404 );
			$this->assertFalse( $query->is_home );

			$query = $this->set_main_query();
			$query->set( bbp_get_view_rewrite_id(), 'missing-view' );
			bbp_parse_query( $query );
			$this->assertTrue( $query->bbp_is_404 );
		} finally {
			bbp_deregister_view( $view_id );
		}
	}

	/**
	 * @covers ::bbp_parse_query
	 */
	public function test_parse_query_sets_search_state_and_terms() {
		$query = $this->set_main_query();
		$query->is_home = true;
		$query->set( bbp_get_search_rewrite_id(), 'search phrase' );

		bbp_parse_query( $query );
		$this->assertTrue( $query->bbp_is_search );
		$this->assertFalse( $query->bbp_is_404 );
		$this->assertFalse( $query->is_home );
		$this->assertSame( 'search phrase', $query->bbp_search_terms );
	}

	/**
	 * @covers ::bbp_parse_query
	 */
	public function test_parse_query_sets_post_edit_states() {
		$revision_priority = has_action( 'pre_post_update', 'wp_save_post_revision' );
		$post_types        = array(
			bbp_get_forum_post_type() => 'bbp_is_forum_edit',
			bbp_get_topic_post_type() => 'bbp_is_topic_edit',
			bbp_get_reply_post_type() => 'bbp_is_reply_edit',
		);

		try {
			foreach ( $post_types as $post_type => $property ) {
				$query = $this->set_main_query();
				$query->set( bbp_get_edit_rewrite_id(), 1 );
				$query->set( 'post_type', $post_type );
				bbp_parse_query( $query );

				$this->assertTrue( $query->{$property} );
				$this->assertTrue( $query->bbp_is_edit );
				$this->assertFalse( $query->bbp_is_404 );

				if ( false !== $revision_priority ) {
					add_action( 'pre_post_update', 'wp_save_post_revision', $revision_priority );
				}
			}
		} finally {
			if ( ( false !== $revision_priority ) && ! has_action( 'pre_post_update', 'wp_save_post_revision' ) ) {
				add_action( 'pre_post_update', 'wp_save_post_revision', $revision_priority );
			}
		}
	}

	/**
	 * @covers ::bbp_parse_query
	 */
	public function test_parse_query_sets_topic_tag_query_vars() {
		$query = $this->set_main_query();
		$query->set( 'term', 'sample-tag' );

		add_filter( 'bbp_is_topic_tag', '__return_true' );
		try {
			bbp_parse_query( $query );
			$this->assertSame( 'sample-tag', $query->get( 'bbp_topic_tag' ) );
			$this->assertSame( bbp_get_topic_post_type(), $query->get( 'post_type' ) );
			$this->assertSame( bbp_get_topics_per_page(), $query->get( 'posts_per_page' ) );
		} finally {
			remove_filter( 'bbp_is_topic_tag', '__return_true' );
		}
	}

	/**
	 * @covers ::bbp_parse_query
	 */
	public function test_parse_query_sets_topic_tag_edit_state() {
		$revision_priority = has_action( 'pre_post_update', 'wp_save_post_revision' );
		$query             = $this->set_main_query();
		$query->set( bbp_get_edit_rewrite_id(), 1 );

		add_filter( 'bbp_is_topic_tag', '__return_true' );
		try {
			bbp_parse_query( $query );
			$this->assertTrue( $query->bbp_is_topic_tag_edit );
			$this->assertTrue( $query->bbp_is_edit );
			$this->assertFalse( $query->bbp_is_404 );
		} finally {
			remove_filter( 'bbp_is_topic_tag', '__return_true' );
			if ( ( false !== $revision_priority ) && ! has_action( 'pre_post_update', 'wp_save_post_revision' ) ) {
				add_action( 'pre_post_update', 'wp_save_post_revision', $revision_priority );
			}
		}
	}

	/**
	 * Set and return a new main query.
	 *
	 * @return WP_Query Main query.
	 */
	private function set_main_query() {
		$query = new WP_Query();

		$GLOBALS['wp_the_query'] = $query;
		$GLOBALS['wp_query']     = $query;

		return $query;
	}
}
