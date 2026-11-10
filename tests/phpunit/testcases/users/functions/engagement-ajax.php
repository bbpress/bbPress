<?php

/**
 * Stops an AJAX response so tests can inspect it.
 */
class BBP_Tests_Engagement_Ajax_Die_Exception extends Exception {}

/**
 * Tests for the default theme package's engagement AJAX handlers.
 *
 * @group users
 * @group engagements
 * @group ajax
 */
class BBP_Tests_Users_Functions_Engagement_Ajax extends BBP_UnitTestCase {

	protected $old_post;
	protected $user_id;

	public function setUp(): void {
		parent::setUp();

		$this->old_post = $_POST;
		$this->user_id  = $this->factory->user->create( array( 'role' => bbp_get_participant_role() ) );

		$this->set_current_user( $this->user_id );

		if ( ! class_exists( 'BBP_Default' ) ) {
			require_once bbpress()->themes_dir . 'default/bbpress-functions.php';
		}
	}

	public function tearDown(): void {
		$_POST = $this->old_post;

		parent::tearDown();
	}

	/**
	 * Run the subscription AJAX handler and return its response.
	 *
	 * @param int    $object_id    Object ID.
	 * @param string $object_type  Object type.
	 * @param string $nonce_action Nonce action.
	 * @return array Decoded response.
	 */
	protected function ajax_subscription( $object_id = 0, $object_type = 'post', $nonce_action = '' ) {
		$_POST = array(
			'id'    => $object_id,
			'type'  => $object_type,
			'nonce' => wp_create_nonce( $nonce_action )
		);

		$handler = function() {
			return function() {
				throw new BBP_Tests_Engagement_Ajax_Die_Exception();
			};
		};

		// Without a constructor, so no hooks are added.
		$theme = ( new ReflectionClass( 'BBP_Default' ) )->newInstanceWithoutConstructor();
		$level = ob_get_level();

		add_filter( 'wp_doing_ajax', '__return_true' );
		add_filter( 'wp_die_ajax_handler', $handler );
		set_error_handler(
			function( $error_level, $message ) {
				return ( E_WARNING === $error_level ) && ( false !== strpos( $message, 'Cannot modify header information' ) );
			},
			E_WARNING
		);
		ob_start();

		try {
			$theme->ajax_subscription();
			$this->fail( 'Expected the AJAX response to terminate.' );
		} catch ( BBP_Tests_Engagement_Ajax_Die_Exception $error ) {
			$output = ob_get_clean();
		} finally {
			while ( ob_get_level() > $level ) {
				ob_end_clean();
			}

			restore_error_handler();
			remove_filter( 'wp_doing_ajax', '__return_true' );
			remove_filter( 'wp_die_ajax_handler', $handler );
		}

		return json_decode( $output, true );
	}

	/**
	 * @ticket BBP3785
	 *
	 * @covers BBP_Default::ajax_subscription
	 */
	public function test_topic_tag_subscription_toggles_through_the_engagement_type() {
		$tag_id = $this->factory->term->create( array( 'taxonomy' => bbp_get_topic_tag_tax_id() ) );

		// Subscribe.
		$response = $this->ajax_subscription( $tag_id, 'term', 'toggle-subscription_term_' . $tag_id );

		$this->assertTrue( $response['success'] );
		$this->assertSame( 200, $response['status'] );
		$this->assertStringContainsString( 'data-bbp-object-type="term"', $response['content'] );
		$this->assertTrue( bbp_is_user_subscribed( $this->user_id, $tag_id, 'term' ) );
		$this->assertFalse( bbp_is_user_subscribed( $this->user_id, $tag_id, 'post' ) );

		// Unsubscribe.
		$response = $this->ajax_subscription( $tag_id, 'term', 'toggle-subscription_term_' . $tag_id );

		$this->assertTrue( $response['success'] );
		$this->assertFalse( bbp_is_user_subscribed( $this->user_id, $tag_id, 'term' ) );
	}

	/**
	 * @ticket BBP3785
	 *
	 * @covers BBP_Default::ajax_subscription
	 */
	public function test_topic_tag_subscription_requires_a_type_scoped_nonce() {
		$tag_id   = $this->factory->term->create( array( 'taxonomy' => bbp_get_topic_tag_tax_id() ) );
		$response = $this->ajax_subscription( $tag_id, 'term', 'toggle-subscription_' . $tag_id );

		$this->assertFalse( $response['success'] );
		$this->assertSame( 304, $response['status'] );
		$this->assertFalse( bbp_is_user_subscribed( $this->user_id, $tag_id, 'term' ) );
	}

	/**
	 * @ticket BBP3785
	 *
	 * @covers BBP_Default::ajax_subscription
	 */
	public function test_taxonomies_without_subscription_support_cannot_be_subscribed_to() {
		$cat_id   = $this->factory->term->create( array( 'taxonomy' => 'category' ) );
		$response = $this->ajax_subscription( $cat_id, 'term', 'toggle-subscription_term_' . $cat_id );

		$this->assertFalse( $response['success'] );
		$this->assertSame( 303, $response['status'] );
		$this->assertFalse( bbp_is_user_subscribed( $this->user_id, $cat_id, 'term' ) );
	}

	/**
	 * @ticket BBP3785
	 *
	 * @covers BBP_Default::ajax_subscription
	 */
	public function test_opted_in_taxonomy_subscription_toggles_through_the_engagement_type() {
		$taxonomy = 'bbp_test_subscriptions';

		register_taxonomy(
			$taxonomy,
			bbp_get_topic_post_type(),
			array(
				'public'            => true,
				'bbp_subscriptions' => true
			)
		);

		try {
			$term_id  = $this->factory->term->create( array( 'taxonomy' => $taxonomy ) );
			$response = $this->ajax_subscription( $term_id, 'term', 'toggle-subscription_term_' . $term_id );

			$this->assertTrue( $response['success'] );
			$this->assertTrue( bbp_is_user_subscribed( $this->user_id, $term_id, 'term' ) );

			$response = $this->ajax_subscription( $term_id, 'term', 'toggle-subscription_term_' . $term_id );

			$this->assertTrue( $response['success'] );
			$this->assertFalse( bbp_is_user_subscribed( $this->user_id, $term_id, 'term' ) );
		} finally {
			unregister_taxonomy( $taxonomy );
		}
	}

	/**
	 * @ticket BBP3785
	 *
	 * @dataProvider data_post_nonce_actions
	 *
	 * @covers BBP_Default::ajax_subscription
	 */
	public function test_forum_subscription_accepts_both_post_nonces( $nonce_prefix ) {
		$forum_id = $this->factory->forum->create();
		$response = $this->ajax_subscription( $forum_id, 'post', $nonce_prefix . $forum_id );

		$this->assertTrue( $response['success'] );
		$this->assertTrue( bbp_is_user_subscribed( $this->user_id, $forum_id ) );
	}

	/**
	 * Nonce actions accepted for posts.
	 *
	 * @return array
	 */
	public function data_post_nonce_actions() {
		return array(
			'unscoped' => array( 'toggle-subscription_' ),
			'scoped'   => array( 'toggle-subscription_post_' )
		);
	}
}
