<?php

/**
 * Tests the user engagements profile template.
 *
 * @group users
 * @group engagements
 * @group template
 * @ticket 3774
 */
class BBP_Tests_Users_Template_Engagements extends BBP_UnitTestCase {
	private $old_topic_query;
	private $old_displayed_user;
	private $old_current_user;

	public function setUp(): void {
		parent::setUp();
		$this->old_topic_query    = bbpress()->topic_query;
		$this->old_displayed_user = bbpress()->displayed_user;
		$this->old_current_user   = get_current_user_id();

		bbp_register_template_stack( array( $this, 'template_directory' ), -100 );
		add_action( 'bbp_locate_template', array( $this, 'load_template' ), 10, 6 );
		update_option( '_bbp_topics_per_page', 2 );
	}

	public function tearDown(): void {
		remove_action( 'bbp_locate_template', array( $this, 'load_template' ), 10 );
		bbp_deregister_template_stack( array( $this, 'template_directory' ), -100 );
		bbpress()->topic_query    = $this->old_topic_query;
		bbpress()->displayed_user = $this->old_displayed_user;
		$this->set_current_user( $this->old_current_user );
		parent::tearDown();
	}

	public function template_directory() {
		return BBP_PLUGIN_DIR . 'templates/default';
	}

	public function load_template( $located, $name, $names, $locations, $load, $require_once ) {
		// The WordPress test bootstrap disables normal theme loading.
		if ( $load && $located && ( ! defined( 'WP_USE_THEMES' ) || ! WP_USE_THEMES ) ) {
			load_template( $located, $require_once );
		}
	}

	/**
	 * @dataProvider engagement_pages
	 * @covers ::bbp_get_user_engagements
	 */
	public function test_profile_renders_engagements_once( $topic_count, $page ) {
		$user_id  = $this->factory->user->create( array( 'role' => bbp_get_participant_role() ) );
		$forum_id = $this->factory->forum->create();
		$topics   = array();
		$this->set_current_user( $user_id );

		for ( $i = 0; $i < $topic_count; ++$i ) {
			$topic_id = $this->factory->topic->create( array( 'post_parent' => $forum_id ) );
			bbp_add_user_engagement( $user_id, $topic_id );
			bbp_update_topic_last_active_time( $topic_id, sprintf( '2020-01-%02d 00:00:00', $i + 1 ) );
			$topics[] = $topic_id;
		}

		bbPress::add_rewrite_tags();
		bbPress::add_rewrite_rules();
		WP_UnitTestCase::go_to( add_query_arg( 'paged', $page, bbp_get_user_engagements_url( $user_id ) ) );
		$this->assertTrue( bbp_is_single_user_engagements() );
		$before = did_action( 'bbp_template_before_user_engagements' );
		$after  = did_action( 'bbp_template_after_user_engagements' );

		ob_start();
		try {
			bbp_get_template_part( 'content', 'single-user' );
			$html = ob_get_contents();
		} finally {
			ob_end_clean();
		}

		$this->assertSame( 1, substr_count( $html, 'id="bbp-user-engagements"' ) );
		$this->assertSame( 1, substr_count( $html, '<h2 class="entry-title">Topics Engaged In</h2>' ) );
		$this->assertSame( 1, did_action( 'bbp_template_before_user_engagements' ) - $before );
		$this->assertSame( 1, did_action( 'bbp_template_after_user_engagements' ) - $after );

		preg_match_all( '/<ul id="bbp-topic-(\d+)"/', $html, $matches );
		$expected = array_slice( array_reverse( $topics ), ( $page - 1 ) * 2, 2 );
		$this->assertSame( $expected, array_map( 'intval', $matches[1] ) );
		$this->assertSame( $topic_count, (int) bbpress()->topic_query->found_posts );

		if ( $topic_count ) {
			$this->assertSame( 2, substr_count( $html, 'class="bbp-pagination"' ) );
			$this->assertLessThan( strpos( $html, '<ul id="bbp-topic-' ), strpos( $html, 'class="bbp-pagination"' ) );
			$this->assertGreaterThan( strrpos( $html, '<ul id="bbp-topic-' ), strrpos( $html, 'class="bbp-pagination"' ) );
			$this->assertSame( 0, substr_count( $html, 'bbp-template-notice' ) );
		} else {
			$this->assertSame( 0, substr_count( $html, 'class="bbp-pagination"' ) );
			$this->assertSame( 1, substr_count( $html, 'bbp-template-notice' ) );
		}

		if ( $topic_count > 2 ) {
			$this->assertSame( 2, substr_count( $html, 'class="page-numbers current">' . $page . '</span>' ) );
		}
	}

	public static function engagement_pages() {
		return array(
			'empty history'        => array( 0, 1 ),
			'single topic'         => array( 1, 1 ),
			'paginated first page' => array( 3, 1 ),
			'paginated last page'  => array( 3, 2 ),
		);
	}
}
