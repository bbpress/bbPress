<?php

/**
 * Tests for registered engagement types.
 *
 * @group users
 * @group engagements
 */
class BBP_Tests_Users_Functions_Engagement_Types extends BBP_UnitTestCase {

	public function tearDown(): void {
		bbp_deregister_engagement_type( 'bbp_test' );
		parent::tearDown();
	}

	/**
	 * Register the test engagement type.
	 *
	 * @return BBP_Tests_Engagements_Type
	 */
	protected function register_test_type() {
		return bbp_register_engagement_type(
			'bbp_test',
			array(
				'class' => 'BBP_Tests_Engagements_Type',
				'extra' => 'value'
			)
		);
	}

	/**
	 * @ticket BBP3785
	 *
	 * @covers ::bbp_get_engagement_type
	 */
	public function test_post_type_is_registered_by_default() {
		$type = bbp_get_engagement_type( 'post' );

		$this->assertInstanceOf( 'BBP_User_Engagements_Type_Post', $type );
		$this->assertSame( 'post', $type->name );
		$this->assertFalse( bbp_get_engagement_type( 'Post' ) );
		$this->assertFalse( bbp_get_engagement_type( 'term' ) );
		$this->assertFalse( bbp_get_engagement_type( array( 'post' ) ) );
		$this->assertFalse( bbp_get_engagement_type() );
	}

	/**
	 * @ticket BBP3785
	 *
	 * @covers ::bbp_register_engagement_type
	 * @covers ::bbp_deregister_engagement_type
	 * @covers BBP_User_Engagements_Type::__construct
	 */
	public function test_types_can_be_registered_and_deregistered() {
		$type = $this->register_test_type();

		$this->assertInstanceOf( 'BBP_Tests_Engagements_Type', $type );
		$this->assertSame( $type, bbp_get_engagement_type( 'bbp_test' ) );
		$this->assertSame( 'bbp_test', $type->name );
		$this->assertSame( 'value', $type->args['extra'] );
		$this->assertTrue( bbp_deregister_engagement_type( 'bbp_test' ) );
		$this->assertFalse( bbp_get_engagement_type( 'bbp_test' ) );
		$this->assertFalse( bbp_deregister_engagement_type( 'bbp_test' ) );
		$this->assertFalse( bbp_deregister_engagement_type( array( 'post' ) ) );
	}

	/**
	 * @ticket BBP3785
	 *
	 * @covers ::bbp_register_engagement_type
	 */
	public function test_types_default_to_the_base_class() {
		$this->set_current_user( $this->factory->user->create( array( 'role' => bbp_get_participant_role() ) ) );

		$type = bbp_register_engagement_type( 'bbp_test' );

		$this->assertFalse( bbp_current_user_can_toggle_engagement( 1, 'bbp_test', 'subscription', 'add' ) );
		$this->assertFalse( bbp_current_user_can_toggle_engagement( 1, 'bbp_test', 'subscription', 'remove' ) );

		$this->assertSame( 'BBP_User_Engagements_Type', get_class( $type ) );
		$this->assertNull( $type->supports( 'subscription', 1 ) );
		$this->assertNull( $type->current_user_can_read( 1 ) );
		$this->assertNull( $type->get_query_args( array( 1 ) ) );
		$this->assertNull( $type->get_url( 1 ) );
	}

	/**
	 * @ticket BBP3785
	 *
	 * @covers ::bbp_register_engagement_type
	 */
	public function test_invalid_types_are_not_registered() {
		$this->assertFalse( bbp_register_engagement_type( '' ) );
		$this->assertFalse( bbp_register_engagement_type( 'Not Sanitized' ) );
		$this->assertFalse( bbp_register_engagement_type( array( 'bbp_test' ) ) );
		$this->assertFalse( bbp_register_engagement_type( 'bbp_test', array( 'class' => 'stdClass' ) ) );
		$this->assertFalse( bbp_register_engagement_type( 'bbp_test', array( 'class' => 'BBP_Tests_Missing_Class' ) ) );
		$this->assertFalse( bbp_register_engagement_type( 'bbp_test', array( 'class' => array( 'BBP_User_Engagements_Type' ) ) ) );
		$this->assertFalse( bbp_get_engagement_type( 'bbp_test' ) );
		$this->assertFalse( bbp_get_engagement_type( 'Not Sanitized' ) );
	}

	/**
	 * @ticket BBP3785
	 *
	 * @covers ::bbp_get_engagement_type
	 */
	public function test_filtered_type_must_be_an_engagement_type() {
		add_filter( 'bbp_get_engagement_type', '__return_true' );
		$retval = bbp_get_engagement_type( 'post' );
		remove_filter( 'bbp_get_engagement_type', '__return_true' );

		$this->assertFalse( $retval );
	}

	/**
	 * @ticket BBP3785
	 *
	 * @covers ::bbp_current_user_can_toggle_engagement
	 */
	public function test_toggle_delegates_object_checks_to_the_registered_type() {
		$user_id = $this->factory->user->create( array( 'role' => bbp_get_participant_role() ) );

		$this->set_current_user( $user_id );

		$this->assertFalse( bbp_current_user_can_toggle_engagement( 123, 'bbp_test' ) );

		$type = $this->register_test_type();

		// Adding requires support and read access
		$this->assertFalse( bbp_current_user_can_toggle_engagement( 123, 'bbp_test', 'subscription', 'add' ) );
		$this->assertSame( array( array( 'supports', 'subscription', 123 ), array( 'current_user_can_read', 123 ) ), $type->calls );

		$type->readable = true;
		$this->assertTrue( bbp_current_user_can_toggle_engagement( 123, 'bbp_test', 'subscription', 'add' ) );

		// Removing requires support only
		$type->readable = false;
		$type->calls    = array();
		$this->assertTrue( bbp_current_user_can_toggle_engagement( 123, 'bbp_test', 'favorite', 'remove' ) );
		$this->assertSame( array( array( 'supports', 'favorite', 123 ) ), $type->calls );

		// Unsupported engagements fail without checking read access
		$type->supported = false;
		$type->readable  = true;
		$type->calls     = array();
		$this->assertFalse( bbp_current_user_can_toggle_engagement( 123, 'bbp_test', 'subscription', 'add' ) );
		$this->assertFalse( bbp_current_user_can_toggle_engagement( 123, 'bbp_test', 'subscription', 'remove' ) );
		$this->assertSame( array( array( 'supports', 'subscription', 123 ), array( 'supports', 'subscription', 123 ) ), $type->calls );

		// Shared checks run before the type is asked
		$type->supported = true;
		$type->calls     = array();
		$this->assertFalse( bbp_current_user_can_toggle_engagement( 123, 'bbp_test', 'invalid' ) );
		$this->assertFalse( bbp_current_user_can_toggle_engagement( 123, 'bbp_test', 'subscription', 'invalid' ) );
		$this->assertFalse( bbp_current_user_can_toggle_engagement( 123, 'bbp_test', 'subscription', 'add', $this->factory->user->create() ) );
		$this->assertSame( array(), $type->calls );
	}

	/**
	 * @ticket BBP3785
	 *
	 * @covers BBP_User_Engagements_Type_Post::get_url
	 */
	public function test_post_type_url_is_the_permalink() {
		$forum_id = $this->factory->forum->create();
		$type     = bbp_get_engagement_type( 'post' );

		$this->assertSame( get_permalink( $forum_id ), $type->get_url( $forum_id ) );
		$this->assertFalse( $type->get_url( $forum_id + 1000 ) );
	}
}

/**
 * Engagement type that records how it was asked.
 */
class BBP_Tests_Engagements_Type extends BBP_User_Engagements_Type {
	public $supported = true;
	public $readable  = false;
	public $calls     = array();

	public function supports( $engagement = '', $object_id = 0 ) {
		$this->calls[] = array( 'supports', $engagement, $object_id );

		return $this->supported;
	}

	public function current_user_can_read( $object_id = 0 ) {
		$this->calls[] = array( 'current_user_can_read', $object_id );

		return $this->readable;
	}
}
