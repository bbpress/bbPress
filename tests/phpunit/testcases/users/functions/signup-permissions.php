<?php

/**
 * Tests for forum role assignment during user registration.
 *
 * @group users
 * @group capabilities
 */
class BBP_Tests_Users_Functions_Signup_Permissions extends BBP_UnitTestCase {

	protected $old_post;
	protected $old_errors;
	protected $old_screen;
	protected $granted_super_admin;

	public function setUp(): void {
		parent::setUp();
		$this->old_post   = $_POST;
		$this->old_errors = bbpress()->errors;
		$this->old_screen = array_key_exists( 'current_screen', $GLOBALS )
			? $GLOBALS['current_screen']
			: null;

		bbpress()->errors = new WP_Error();
	}

	public function tearDown(): void {
		$_POST            = $this->old_post;
		bbpress()->errors = $this->old_errors;

		if ( null === $this->old_screen ) {
			unset( $GLOBALS['current_screen'] );
		} else {
			$GLOBALS['current_screen'] = $this->old_screen;
		}

		if ( $this->granted_super_admin ) {
			revoke_super_admin( $this->granted_super_admin );
			$this->granted_super_admin = 0;
		}
		parent::tearDown();
	}

	/**
	 * @covers ::bbp_set_current_user_default_role
	 */
	public function test_administrator_without_forum_role_maps_to_keymaster() {
		$admin_id = $this->factory->user->create( array( 'role' => 'administrator' ) );
		$user     = get_userdata( $admin_id );
		$role     = bbp_get_user_role( $admin_id );
		if ( $role ) {
			$user->remove_role( $role );
		}
		$this->assertFalse( bbp_get_user_role( $admin_id ) );
		$this->set_current_user( $admin_id );

		bbp_set_current_user_default_role();
		$this->assertTrue( bbp_is_user_keymaster() );
	}

	/**
	 * @covers ::bbp_validate_registration_role
	 * @covers ::bbp_user_add_role_on_register
	 */
	public function test_non_keymaster_cannot_assign_keymaster_during_registration() {
		$creator_id = $this->factory->user->create( array( 'role' => 'administrator' ) );
		$target_id  = $this->factory->user->create();

		bbp_set_user_role( $creator_id, bbp_get_participant_role() );
		$this->set_current_user( $creator_id );
		set_current_screen( 'user-new' );

		if ( ! is_multisite() ) {
			$this->assertTrue( current_user_can( 'create_users' ) );
		} else {
			$this->assertTrue( current_user_can( 'promote_users' ) );
		}
		$this->assertFalse( bbp_is_user_keymaster() );

		$_POST['bbp-forums-role'] = bbp_get_keymaster_role();
		bbp_user_add_role_on_register( $target_id );
		$this->assertSame( bbp_get_default_role(), bbp_get_user_role( $target_id ) );

		$_POST['bbp-forums-role'] = bbp_get_moderator_role();
		bbp_user_add_role_on_register( $target_id );
		$this->assertSame( bbp_get_moderator_role(), bbp_get_user_role( $target_id ) );
	}

	/**
	 * @covers ::bbp_validate_registration_role
	 * @covers ::bbp_user_add_role_on_register
	 */
	public function test_keymaster_can_assign_staff_role_during_registration() {
		$creator_id = $this->factory->user->create( array( 'role' => 'administrator' ) );
		$target_id  = $this->factory->user->create();

		if ( is_multisite() ) {
			grant_super_admin( $creator_id );
			$this->granted_super_admin = $creator_id;
		}

		bbp_set_user_role( $creator_id, bbp_get_keymaster_role() );
		$this->set_current_user( $creator_id );
		set_current_screen( 'user-new' );

		$_POST['bbp-forums-role'] = bbp_get_moderator_role();
		bbp_user_add_role_on_register( $target_id );
		$this->assertSame( bbp_get_moderator_role(), bbp_get_user_role( $target_id ) );
	}

	/**
	 * @ticket BBP3262
	 * @covers ::bbp_user_add_role_on_register
	 * @covers ::bbp_user_add_role_on_activate
	 */
	public function test_filtered_empty_signup_role_does_not_remove_existing_forum_role() {
		$user_id = $this->factory->user->create( array( 'role' => 'subscriber' ) );
		$empty   = function () {
			return '';
		};

		bbp_set_user_role( $user_id, bbp_get_moderator_role() );
		add_filter( 'bbp_validate_signup_role', $empty );

		try {
			bbp_user_add_role_on_register( $user_id );
			bbp_user_add_role_on_activate( $user_id, '', array( 'bbp_new_role' => bbp_get_participant_role() ) );
			$this->assertSame( bbp_get_moderator_role(), bbp_get_user_role( $user_id ) );
		} finally {
			remove_filter( 'bbp_validate_signup_role', $empty );
		}
	}
}
