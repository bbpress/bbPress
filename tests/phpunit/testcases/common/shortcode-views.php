<?php

/**
 * Tests for topic-view shortcodes.
 *
 * @group common
 * @group shortcodes
 * @group views
 */
class BBP_Tests_Common_Shortcode_Views extends BBP_UnitTestCase {

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
	 * @covers BBP_Shortcodes::display_view
	 * @ticket BBP3718
	 */
	public function test_single_view_shortcode_does_not_render_topics_for_unregistered_view() {
		$topic_id = $this->factory->topic->create(
			array( 'post_title' => 'View shortcode regression topic' )
		);
		bbp_register_view(
			'unit-test-view',
			'Unit Test View',
			array(
				'post_type'      => bbp_get_topic_post_type(),
				'post__in'       => array( $topic_id ),
				'posts_per_page' => 1,
			)
		);

		try {
			$registered_output = do_shortcode( '[bbp-single-view id="unit-test-view"]' );
			$missing_output    = do_shortcode( '[bbp-single-view id="missing-view"]' );

			$this->assertStringContainsString( 'View shortcode regression topic', $registered_output );
			$this->assertStringNotContainsString( 'View shortcode regression topic', $missing_output );
		} finally {
			bbp_deregister_view( 'unit-test-view' );
		}
	}
}
