<?php

/**
 * Tests for how engagement types are stored and queried by each strategy.
 *
 * @group users
 * @group engagements
 */
class BBP_Tests_Users_Functions_Engagement_Storage extends BBP_UnitTestCase {

	/**
	 * The strategy active before each test.
	 *
	 * @var object
	 */
	protected $strategy = null;

	public function setUp(): void {
		parent::setUp();

		$this->strategy = bbpress()->engagements;
	}

	public function tearDown(): void {
		bbpress()->engagements = $this->strategy;

		remove_filter( 'bbp_get_engagement_storage_key', array( $this, 'filter_storage_key' ), 10 );
		remove_filter( 'bbp_get_user_object_query', array( $this, 'filter_query_args' ), 10 );

		// Restore the default term engagement type.
		bbp_register_engagement_type(
			'term',
			array(
				'class' => 'BBP_User_Engagements_Type_Term'
			)
		);

		parent::tearDown();
	}

	/**
	 * Store term subscriptions under the key WordPress.org uses.
	 *
	 * @param string $key       Storage key.
	 * @param string $meta_type Relationship type.
	 * @return string
	 */
	public function filter_storage_key( $key = '', $meta_type = 'post' ) {
		return ( 'term' === $meta_type ) && ( '_bbp_subscription' === $key )
			? '_bbp_term_subscription'
			: $key;
	}

	/**
	 * Add an unrelated argument to an engagement query.
	 *
	 * @param array $args Query arguments.
	 * @return array
	 */
	public function filter_query_args( $args = array() ) {
		$args['number'] = 1;

		return $args;
	}

	/**
	 * Strategies to run each storage test against, with and without a
	 * filtered storage key.
	 *
	 * @return array
	 */
	public function data_strategies_and_filter() {
		$retval = array();

		foreach ( $this->data_strategies() as $name => $args ) {
			$retval[ $name ]               = array( $args[0], false );
			$retval[ "{$name} (filtered)" ] = array( $args[0], true );
		}

		return $retval;
	}

	/**
	 * Strategies to run each storage test against.
	 *
	 * @return array
	 */
	public function data_strategies() {
		return array(
			'meta' => array( 'BBP_User_Engagements_Meta' ),
			'term' => array( 'BBP_User_Engagements_Term' ),
			'user' => array( 'BBP_User_Engagements_User' )
		);
	}

	/**
	 * @ticket BBP3785
	 *
	 * @dataProvider data_strategies_and_filter
	 *
	 * @covers ::bbp_add_user_to_object
	 * @covers ::bbp_remove_user_from_object
	 * @covers ::bbp_get_users_for_object
	 */
	public function test_types_do_not_share_engagements( $class_name, $filtered ) {
		bbpress()->engagements = new $class_name();

		if ( true === $filtered ) {
			add_filter( 'bbp_get_engagement_storage_key', array( $this, 'filter_storage_key' ), 10, 2 );
		}

		$topic_id   = $this->factory->topic->create();
		$topic_user = $this->factory->user->create();
		$term_user  = $this->factory->user->create();

		// Same ID, different types
		$this->assertTrue( bbp_add_user_to_object( $topic_id, $topic_user, '_bbp_subscription', 'post' ) );
		$this->assertTrue( bbp_add_user_to_object( $topic_id, $term_user, '_bbp_subscription', 'term' ) );

		$this->assertSame( array( $topic_user ), bbp_get_users_for_object( $topic_id, '_bbp_subscription', 'post' ) );
		$this->assertSame( array( $term_user ), bbp_get_users_for_object( $topic_id, '_bbp_subscription', 'term' ) );

		// Removing one leaves the other
		$this->assertTrue( bbp_remove_user_from_object( $topic_id, $term_user, '_bbp_subscription', 'term' ) );
		$this->assertSame( array(), bbp_get_users_for_object( $topic_id, '_bbp_subscription', 'term' ) );
		$this->assertSame( array( $topic_user ), bbp_get_users_for_object( $topic_id, '_bbp_subscription', 'post' ) );
	}

	/**
	 * @ticket BBP3785
	 *
	 * @covers BBP_User_Engagements_Meta::add_user_to_object
	 */
	public function test_meta_strategy_stores_terms_in_term_meta() {
		bbpress()->engagements = new BBP_User_Engagements_Meta();

		$term_id = $this->factory->term->create( array( 'taxonomy' => bbp_get_topic_tag_tax_id() ) );
		$user_id = $this->factory->user->create();

		bbp_add_user_to_object( $term_id, $user_id, '_bbp_subscription', 'term' );

		$this->assertSame( array( (string) $user_id ), get_term_meta( $term_id, '_bbp_subscription' ) );
		$this->assertSame( array(), get_post_meta( $term_id, '_bbp_subscription' ) );
	}

	/**
	 * @ticket BBP3785
	 *
	 * @covers BBP_User_Engagements_Term::add_user_to_object
	 */
	public function test_term_strategy_stores_terms_in_their_own_taxonomy() {
		bbpress()->engagements = new BBP_User_Engagements_Term();

		$term_id = $this->factory->term->create( array( 'taxonomy' => bbp_get_topic_tag_tax_id() ) );
		$user_id = $this->factory->user->create();

		bbp_add_user_to_object( $term_id, $user_id, '_bbp_subscription', 'term' );

		$user_term = get_term_by( 'slug', "_bbp_subscription_user_id_{$user_id}", '_bbp_subscription_term' );

		$this->assertNotEmpty( $user_term );
		$this->assertSame( array( (string) $term_id ), get_objects_in_term( $user_term->term_id, '_bbp_subscription_term' ) );
		$this->assertFalse( get_term_by( 'slug', "_bbp_subscription_user_id_{$user_id}", '_bbp_subscription_post' ) );
	}

	/**
	 * @ticket BBP3785
	 *
	 * @covers BBP_User_Engagements_User::add_user_to_object
	 */
	public function test_user_strategy_stores_terms_under_a_typed_option() {
		bbpress()->engagements = new BBP_User_Engagements_User();

		$term_id = $this->factory->term->create( array( 'taxonomy' => bbp_get_topic_tag_tax_id() ) );
		$user_id = $this->factory->user->create();

		bbp_add_user_to_object( $term_id, $user_id, '_bbp_subscription', 'term' );

		$this->assertSame( (string) $term_id, get_user_option( '_bbp_subscriptions_term', $user_id ) );
		$this->assertFalse( get_user_option( '_bbp_subscriptions', $user_id ) );
		$this->assertFalse( get_user_option( '_bbp_forum_subscriptions', $user_id ) );
	}

	/**
	 * @ticket BBP3785
	 *
	 * @covers BBP_User_Engagements_Base::get_storage_key
	 */
	public function test_storage_key_filter_changes_where_terms_are_stored() {
		bbpress()->engagements = new BBP_User_Engagements_Meta();

		$term_id  = $this->factory->term->create( array( 'taxonomy' => bbp_get_topic_tag_tax_id() ) );
		$topic_id = $this->factory->topic->create();
		$user_id  = $this->factory->user->create();

		add_filter( 'bbp_get_engagement_storage_key', array( $this, 'filter_storage_key' ), 10, 2 );

		try {
			bbp_add_user_to_object( $term_id, $user_id, '_bbp_subscription', 'term' );
			bbp_add_user_to_object( $topic_id, $user_id, '_bbp_subscription', 'post' );

			$this->assertSame( array( (string) $user_id ), get_term_meta( $term_id, '_bbp_term_subscription' ) );
			$this->assertSame( array(), get_term_meta( $term_id, '_bbp_subscription' ) );
			$this->assertSame( array( (string) $user_id ), get_post_meta( $topic_id, '_bbp_subscription' ) );
			$this->assertSame( array( $user_id ), bbp_get_users_for_object( $term_id, '_bbp_subscription', 'term' ) );
		} finally {
			remove_filter( 'bbp_get_engagement_storage_key', array( $this, 'filter_storage_key' ), 10 );
		}
	}

	/**
	 * @ticket BBP3785
	 *
	 * @covers BBP_User_Engagements_Base::get_storage_key
	 */
	public function test_storage_key_filter_changes_term_strategy_taxonomy() {
		bbpress()->engagements = new BBP_User_Engagements_Term();

		$term_id = $this->factory->term->create( array( 'taxonomy' => bbp_get_topic_tag_tax_id() ) );
		$user_id = $this->factory->user->create();

		add_filter( 'bbp_get_engagement_storage_key', array( $this, 'filter_storage_key' ), 10, 2 );

		try {
			bbp_add_user_to_object( $term_id, $user_id, '_bbp_subscription', 'term' );

			$user_term = get_term_by( 'slug', "_bbp_term_subscription_user_id_{$user_id}", '_bbp_term_subscription_term' );

			$this->assertNotEmpty( $user_term );
			$this->assertSame( array( (string) $term_id ), get_objects_in_term( $user_term->term_id, '_bbp_term_subscription_term' ) );
			$this->assertFalse( get_term_by( 'slug', "_bbp_subscription_user_id_{$user_id}", '_bbp_subscription_term' ) );
		} finally {
			remove_filter( 'bbp_get_engagement_storage_key', array( $this, 'filter_storage_key' ), 10 );
		}
	}

	/**
	 * @ticket BBP3785
	 *
	 * @covers BBP_User_Engagements_Base::get_storage_key
	 */
	public function test_storage_key_filter_changes_user_strategy_option() {
		bbpress()->engagements = new BBP_User_Engagements_User();

		$term_id = $this->factory->term->create( array( 'taxonomy' => bbp_get_topic_tag_tax_id() ) );
		$user_id = $this->factory->user->create();

		add_filter( 'bbp_get_engagement_storage_key', array( $this, 'filter_storage_key' ), 10, 2 );

		try {
			bbp_add_user_to_object( $term_id, $user_id, '_bbp_subscription', 'term' );

			$this->assertSame( (string) $term_id, get_user_option( '_bbp_term_subscriptions_term', $user_id ) );
			$this->assertFalse( get_user_option( '_bbp_subscriptions_term', $user_id ) );
		} finally {
			remove_filter( 'bbp_get_engagement_storage_key', array( $this, 'filter_storage_key' ), 10 );
		}
	}

	/**
	 * @ticket BBP3785
	 *
	 * @dataProvider data_strategies_without_meta
	 *
	 * @covers ::bbp_get_user_object_query
	 */
	public function test_registered_type_without_query_arguments_queries_nothing( $class_name ) {
		bbpress()->engagements = new $class_name();

		bbp_deregister_engagement_type( 'term' );
		bbp_register_engagement_type( 'term' );

		$taxonomy = bbp_get_topic_tag_tax_id();
		$this->factory->term->create_many( 3, array( 'taxonomy' => $taxonomy ) );

		add_filter( 'bbp_get_user_object_query', array( $this, 'filter_query_args' ) );

		$query_args = bbp_get_user_object_query( 1, 'tag_subscriptions', '_bbp_subscription', 'term' );
		$terms      = ! empty( $query_args )
			? get_terms(
				array_merge(
					array(
						'taxonomy'   => $taxonomy,
						'hide_empty' => false,
						'fields'     => 'ids'
					),
					$query_args
				)
			)
			: array();

		$this->assertSame( array(), $query_args );
		$this->assertSame( array(), $terms );
	}

	/**
	 * @ticket BBP3785
	 *
	 * @dataProvider data_strategies_without_meta
	 *
	 * @covers ::bbp_get_user_engagements
	 */
	public function test_user_engagements_guard_an_empty_query( $class_name ) {
		bbpress()->engagements = new $class_name();

		$post_type = bbp_get_engagement_type( 'post' );
		$user_id   = $this->factory->user->create();
		$topic_id  = $this->factory->topic->create();

		bbp_add_user_to_object( $topic_id, $user_id, '_bbp_engagement' );
		bbp_deregister_engagement_type( 'post' );
		bbp_register_engagement_type( 'post' );

		try {
			$this->assertSame( array(), bbp_get_user_engagements( $user_id ) );
		} finally {
			bbp_deregister_engagement_type( 'post' );
			bbp_register_engagement_type( 'post', $post_type->args );
		}
	}

	/**
	 * Strategies that need a registered type to supply object query arguments.
	 *
	 * @return array
	 */
	public function data_strategies_without_meta() {
		return array(
			'term' => array( 'BBP_User_Engagements_Term' ),
			'user' => array( 'BBP_User_Engagements_User' )
		);
	}

	/**
	 * @ticket BBP3785
	 *
	 * @dataProvider data_strategies_and_filter
	 *
	 * @covers ::bbp_get_user_object_query
	 */
	public function test_user_object_query_works_with_get_terms( $class_name, $filtered ) {
		bbpress()->engagements = new $class_name();

		if ( true === $filtered ) {
			add_filter( 'bbp_get_engagement_storage_key', array( $this, 'filter_storage_key' ), 10, 2 );
		}

		$tax_id   = bbp_get_topic_tag_tax_id();
		$term_ids = $this->factory->term->create_many( 3, array( 'taxonomy' => $tax_id ) );
		$user_id  = $this->factory->user->create();
		$other_id = $this->factory->user->create();

		bbp_add_user_to_object( $term_ids[0], $user_id, '_bbp_subscription', 'term' );
		bbp_add_user_to_object( $term_ids[2], $user_id, '_bbp_subscription', 'term' );

		$query = array(
			'taxonomy'   => $tax_id,
			'hide_empty' => false,
			'fields'     => 'ids'
		);

		$subscribed = get_terms( array_merge( $query, bbp_get_user_object_query( $user_id, 'tag_subscriptions', '_bbp_subscription', 'term' ) ) );
		$none       = get_terms( array_merge( $query, bbp_get_user_object_query( $other_id, 'tag_subscriptions', '_bbp_subscription', 'term' ) ) );

		$this->assertEqualSets( array( $term_ids[0], $term_ids[2] ), $subscribed );
		$this->assertSame( array(), $none );
	}

	/**
	 * @ticket BBP3785
	 * @ticket BBP2989
	 *
	 * @dataProvider data_strategies
	 *
	 * @covers ::bbp_add_user_subscription
	 * @covers ::bbp_remove_user_subscription
	 * @covers ::bbp_is_user_subscribed
	 */
	public function test_topic_tag_subscriptions( $class_name ) {
		bbpress()->engagements = new $class_name();

		$user_id = $this->factory->user->create( array( 'role' => bbp_get_participant_role() ) );
		$term_id = $this->factory->term->create( array( 'taxonomy' => bbp_get_topic_tag_tax_id() ) );

		$this->set_current_user( $user_id );

		// Topic tags can be subscribed to.
		$this->assertTrue( bbp_current_user_can_toggle_engagement( $term_id, 'term', 'subscription', 'add' ) );
		$this->assertTrue( bbp_add_user_subscription( $user_id, $term_id, 'term' ) );
		$this->assertTrue( bbp_is_user_subscribed( $user_id, $term_id, 'term' ) );
		$this->assertSame( array( $user_id ), bbp_get_users_for_object( $term_id, '_bbp_subscription', 'term' ) );

		// A post with the same ID is not subscribed.
		$this->assertFalse( bbp_is_user_subscribed( $user_id, $term_id, 'post' ) );

		// Topic tags cannot be favorited.
		$this->assertFalse( bbp_current_user_can_toggle_engagement( $term_id, 'term', 'favorite', 'add' ) );

		// Subscriptions can be removed.
		$this->assertTrue( bbp_current_user_can_toggle_engagement( $term_id, 'term', 'subscription', 'remove' ) );
		$this->assertTrue( bbp_remove_user_subscription( $user_id, $term_id, 'term' ) );
		$this->assertFalse( bbp_is_user_subscribed( $user_id, $term_id, 'term' ) );
		$this->assertSame( array(), bbp_get_users_for_object( $term_id, '_bbp_subscription', 'term' ) );
	}

	/**
	 * @ticket BBP3785
	 *
	 * @covers BBP_User_Engagements_Type_Post::supports_tax_query
	 * @covers BBP_User_Engagements_Type_Post::get_query_args
	 */
	public function test_post_type_query_arguments() {
		$type = bbp_get_engagement_type( 'post' );

		$this->assertTrue( $type->supports_tax_query() );
		$this->assertSame( array( 'post__in' => array( 1, 2 ) ), $type->get_query_args( array( 1, 2 ) ) );
	}
}
