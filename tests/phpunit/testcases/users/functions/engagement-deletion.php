<?php

/**
 * Tests for engagement cleanup when an object is deleted.
 *
 * @group users
 * @group engagements
 */
class BBP_Tests_Users_Functions_Engagement_Deletion extends BBP_UnitTestCase {
	private $previous_strategy;
	private $registered_taxonomies;

	public function setUp(): void {
		parent::setUp();
		$this->previous_strategy     = bbpress()->engagements;
		$this->registered_taxonomies = get_taxonomies();
	}

	public function tearDown(): void {
		bbpress()->engagements = $this->previous_strategy;
		remove_filter( 'bbp_get_engagement_storage_key', array( $this, 'filter_storage_key' ), 10 );
		if ( post_type_exists( 'bbp_cleanup_test' ) ) {
			unregister_post_type( 'bbp_cleanup_test' );
		}

		foreach ( array_diff( get_taxonomies(), $this->registered_taxonomies ) as $taxonomy ) {
			unregister_taxonomy( $taxonomy );
		}

		parent::tearDown();
	}

	public function filter_storage_key( $key, $type ) {
		return ( '_bbp_subscription' === $key )
			? '_bbp_cleanup_subscription'
			: $key;
	}

	public static function strategies() {
		return array(
			'meta' => array( 'BBP_User_Engagements_Meta' ),
			'term' => array( 'BBP_User_Engagements_Term' ),
			'user' => array( 'BBP_User_Engagements_User' )
		);
	}

	/**
	 * @ticket BBP3785
	 *
	 * @covers ::bbp_register_engagement_type
	 * @covers ::bbp_deregister_engagement_type
	 */
	public function test_replacing_and_deregistering_types_removes_cleanup_hooks() {
		$callbacks = array(
			'post' => array( 'before_delete_post', 'cleanup_deleted_post' ),
			'term' => array( 'delete_term', 'cleanup_deleted_term' )
		);

		foreach ( $callbacks as $name => $callback ) {
			$old_type = bbp_get_engagement_type( $name );
			$new_type = bbp_register_engagement_type( $name, $old_type->args );

			try {
				$this->assertFalse( has_action( $callback[0], array( $old_type, $callback[1] ) ) );
				$this->assertSame( 10, has_action( $callback[0], array( $new_type, $callback[1] ) ) );
				$this->assertTrue( bbp_deregister_engagement_type( $name ) );
				$this->assertFalse( has_action( $callback[0], array( $new_type, $callback[1] ) ) );
			} finally {
				bbp_deregister_engagement_type( $name );
				bbpress()->engagement_types[ $name ] = $old_type;
				$old_type->register_cleanup();
			}
		}
	}

	/**
	 * @ticket BBP3785
	 */
	public function test_deleting_storage_term_does_not_run_object_cleanup() {
		bbpress()->engagements = new BBP_User_Engagements_Term();

		$user_id = $this->factory->user->create();
		$term_id = $this->factory->term->create( array( 'taxonomy' => bbp_get_topic_tag_tax_id() ) );
		$this->assertTrue( bbp_add_user_to_object( $term_id, $user_id, '_bbp_subscription', 'term' ) );

		$storage_taxonomy = get_taxonomy( '_bbp_subscription_term' );
		$this->assertInstanceOf( 'WP_Taxonomy', $storage_taxonomy );
		$this->assertTrue( $storage_taxonomy->bbp_engagement_storage );

		$cleanup_calls = 0;
		$track_cleanup = function ( $result ) use ( &$cleanup_calls ) {
			++$cleanup_calls;
			return $result;
		};
		add_filter( 'bbp_remove_object_from_all_users', $track_cleanup );

		try {
			$this->assertTrue( bbp_remove_user_from_all_objects( $user_id, '_bbp_subscription', 'term' ) );
			$this->assertSame( 0, $cleanup_calls );
		} finally {
			remove_filter( 'bbp_remove_object_from_all_users', $track_cleanup );
		}
	}

	/**
	 * @ticket BBP3785
	 * @dataProvider strategies
	 */
	public function test_deleted_term_has_no_subscribers( $strategy ) {
		$this->assert_deleted_object_has_no_subscribers( $strategy, 'term', false );
	}

	/**
	 * @ticket BBP3785
	 * @dataProvider strategies
	 */
	public function test_deleted_term_has_no_subscribers_with_filtered_key( $strategy ) {
		$this->assert_deleted_object_has_no_subscribers( $strategy, 'term', true );
	}

	/**
	 * @ticket BBP3785
	 * @dataProvider strategies
	 */
	public function test_deleted_custom_post_has_no_subscribers( $strategy ) {
		$this->assert_deleted_object_has_no_subscribers( $strategy, 'post', false );
	}

	/**
	 * @ticket BBP3785
	 * @dataProvider strategies
	 */
	public function test_deleted_custom_post_has_no_subscribers_with_filtered_key( $strategy ) {
		$this->assert_deleted_object_has_no_subscribers( $strategy, 'post', true );
	}

	private function assert_deleted_object_has_no_subscribers( $strategy, $type, $filtered ) {
		bbpress()->engagements = new $strategy();

		if ( $filtered ) {
			add_filter( 'bbp_get_engagement_storage_key', array( $this, 'filter_storage_key' ), 10, 2 );
		}

		$user_id = $this->factory->user->create();

		if ( 'term' === $type ) {
			$taxonomy  = bbp_get_topic_tag_tax_id();
			$object_id = $this->factory->term->create( array( 'taxonomy' => $taxonomy ) );
		} else {
			register_post_type( 'bbp_cleanup_test', array( 'public' => true, 'bbp_subscriptions' => true ) );
			$object_id = $this->factory->post->create( array( 'post_type' => 'bbp_cleanup_test' ) );
		}

		$this->assertTrue( bbp_add_user_to_object( $object_id, $user_id, '_bbp_subscription', $type ) );
		$this->assertSame( array( $user_id ), bbp_get_users_for_object( $object_id, '_bbp_subscription', $type ) );

		$deleted = ( 'term' === $type )
			? wp_delete_term( $object_id, $taxonomy )
			: wp_delete_post( $object_id, true );
		$this->assertNotFalse( $deleted );
		$this->assertSame( array(), bbp_get_users_for_object( $object_id, '_bbp_subscription', $type ) );
	}
}
