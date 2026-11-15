<?php

/**
 * Tests for the default theme package's engagement assets.
 *
 * @group users
 * @group engagements
 */
class BBP_Tests_Users_Functions_Engagement_Assets extends BBP_UnitTestCase {

	protected $had_scripts;
	protected $scripts;

	public function setUp(): void {
		parent::setUp();

		$this->had_scripts     = isset( $GLOBALS['wp_scripts'] );
		$this->scripts         = $this->had_scripts
			? $GLOBALS['wp_scripts']
			: null;
		$GLOBALS['wp_scripts'] = new WP_Scripts();

		if ( ! class_exists( 'BBP_Default' ) ) {
			require_once bbpress()->themes_dir . 'default/bbpress-functions.php';
		}
	}

	public function tearDown(): void {
		if ( $this->had_scripts ) {
			$GLOBALS['wp_scripts'] = $this->scripts;
		} else {
			unset( $GLOBALS['wp_scripts'] );
		}

		parent::tearDown();
	}

	/**
	 * Load a front-end URL without registering the already-registered blocks.
	 *
	 * @param string $url URL to visit.
	 */
	private function go_to_engagement_archive( $url ) {
		$callback = array( bbpress(), 'register_blocks' );

		remove_action( 'bbp_register_blocks', $callback, 5 );
		try {
			$this->go_to( $url );
		} finally {
			add_action( 'bbp_register_blocks', $callback, 5 );
		}
	}

	/**
	 * @ticket BBP2989
	 *
	 * @covers BBP_Default::enqueue_scripts
	 * @covers BBP_Default::localize_topic_script
	 */
	public function test_topic_tag_archive_enqueues_and_localizes_engagement_script() {
		$term_id = $this->factory->term->create( array( 'taxonomy' => bbp_get_topic_tag_tax_id() ) );

		$this->go_to_engagement_archive( get_term_link( $term_id, bbp_get_topic_tag_tax_id() ) );

		$theme = ( new ReflectionClass( 'BBP_Default' ) )->newInstanceWithoutConstructor();
		$theme->enqueue_scripts();
		$theme->localize_topic_script();

		$this->assertTrue( wp_script_is( 'bbpress-engagements', 'enqueued' ) );

		$data = wp_scripts()->get_data( 'bbpress-engagements', 'data' );
		$this->assertStringContainsString( 'var bbpEngagementJS', $data );
		$this->assertStringContainsString( 'bbp_ajaxurl', $data );
	}

	/**
	 * @ticket BBP2989
	 *
	 * @covers BBP_Default::enqueue_scripts
	 */
	public function test_custom_subscription_taxonomy_archive_enqueues_engagement_script() {
		$taxonomy = 'bbp_test_asset_subscriptions';

		register_taxonomy(
			$taxonomy,
			bbp_get_topic_post_type(),
			array(
				'public'            => true,
				'bbp_subscriptions' => true
			)
		);

		try {
			$term_id = $this->factory->term->create( array( 'taxonomy' => $taxonomy ) );

			$this->go_to_engagement_archive( get_term_link( $term_id, $taxonomy ) );

			$theme = ( new ReflectionClass( 'BBP_Default' ) )->newInstanceWithoutConstructor();
			$theme->enqueue_scripts();

			$this->assertTrue( wp_script_is( 'bbpress-engagements', 'enqueued' ) );
		} finally {
			unregister_taxonomy( $taxonomy );
		}
	}

	/**
	 * @ticket BBP2989
	 *
	 * @covers BBP_Default::enqueue_scripts
	 */
	public function test_category_archive_does_not_enqueue_engagement_script() {
		$term_id = $this->factory->term->create( array( 'taxonomy' => 'category' ) );

		$this->go_to_engagement_archive( get_term_link( $term_id, 'category' ) );

		$theme = ( new ReflectionClass( 'BBP_Default' ) )->newInstanceWithoutConstructor();
		$theme->enqueue_scripts();

		$this->assertFalse( wp_script_is( 'bbpress-engagements', 'enqueued' ) );
	}

	/**
	 * @ticket BBP2989
	 *
	 * @covers BBP_Default::enqueue_scripts
	 */
	public function test_single_forum_enqueues_engagement_script() {
		$forum_id = $this->factory->forum->create();

		$this->go_to_engagement_archive( get_permalink( $forum_id ) );

		$theme = ( new ReflectionClass( 'BBP_Default' ) )->newInstanceWithoutConstructor();
		$theme->enqueue_scripts();

		$this->assertTrue( wp_script_is( 'bbpress-engagements', 'enqueued' ) );
	}
}
