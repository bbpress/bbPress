<?php

/**
 * Tests for topic views.
 *
 * @group core
 * @group views
 */
class BBP_Tests_Core_Views extends BBP_UnitTestCase {

	private $template_loader;

	public function setUp(): void {
		parent::setUp();

		// The PHPUnit bootstrap leaves WP_USE_THEMES off; load located templates.
		$this->template_loader = function( $located, $template_name, $template_names, $template_locations, $load ) {
			if ( $load && $located ) {
				load_template( $located, false );
			}
		};
		add_action( 'bbp_locate_template', $this->template_loader, 10, 5 );
	}

	public function tearDown(): void {
		remove_action( 'bbp_locate_template', $this->template_loader, 10 );

		parent::tearDown();
	}

	/**
	 * @covers ::bbp_get_view_title
	 */
	public function test_bbp_get_view_title_returns_false_for_unregistered_view() {
		$this->assertSame( 'Topics with no replies', bbp_get_view_title( 'no-replies' ) );
		$this->assertFalse( bbp_get_view_title( 'not-a-view' ) );
	}

	/**
	 * @covers ::bbp_view_query
	 */
	public function test_bbp_view_query_returns_false_for_unregistered_view() {
		$this->factory->topic->create();

		$this->assertFalse( bbp_view_query( 'not-a-view' ) );
	}

	/**
	 * @covers BBP_Shortcodes::display_view
	 */
	public function test_single_view_shortcode_with_unregistered_view() {
		$this->factory->topic->create( array( 'post_title' => 'View audit sentinel 4471' ) );

		// A registered view renders the topic, so the template loader is working.
		$this->assertStringContainsString( 'View audit sentinel 4471', do_shortcode( '[bbp-single-view id="no-replies"]' ) );

		$this->assertStringNotContainsString( 'View audit sentinel 4471', do_shortcode( '[bbp-single-view id="not-a-view"]' ) );
	}
}
