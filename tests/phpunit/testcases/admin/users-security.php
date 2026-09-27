<?php

/**
 * Bulk forum role assignment permissions.
 *
 * @group admin
 * @group users
 */
class BBP_Tests_Admin_Users_Security extends BBP_UnitTestCase {

	private $request;
	private $denied_target_id = 0;

	public function setUp(): void {
		parent::setUp();

		$this->request = $_REQUEST;

		require_once BBP_PLUGIN_DIR . 'includes/admin/users.php';
	}

	public function tearDown(): void {
		$_REQUEST = $this->request;
		remove_filter( 'map_meta_cap', array( $this, 'deny_target_promotion' ), 10 );
		remove_filter( 'bbp_get_user_editable_forum_roles', array( $this, 'deny_target_roles' ), 10 );

		parent::tearDown();
	}

	/**
	 * @covers BBP_Users_Admin::user_role_bulk_change
	 */
	public function test_bulk_role_change_respects_target_promotion_capability() {
		$admin_id  = $this->factory->user->create( array( 'role' => 'administrator' ) );
		$target_id = $this->factory->user->create( array( 'role' => 'subscriber' ) );

		bbp_set_user_role( $admin_id, bbp_get_participant_role() );
		bbp_set_user_role( $target_id, bbp_get_participant_role() );
		$this->set_current_user( $admin_id );

		$this->assertTrue( current_user_can( 'promote_users' ) );
		$this->denied_target_id = $target_id;
		add_filter( 'map_meta_cap', array( $this, 'deny_target_promotion' ), 10, 4 );
		$this->assertFalse( current_user_can( 'promote_user', $target_id ) );

		$this->set_bulk_request( $target_id, bbp_get_spectator_role() );
		( new BBP_Users_Admin() )->user_role_bulk_change();

		$this->assertSame( bbp_get_participant_role(), bbp_get_user_role( $target_id ) );
	}

	/**
	 * @covers BBP_Users_Admin::user_role_bulk_change
	 */
	public function test_bulk_role_change_does_not_attach_network_user_to_site() {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'Requires multisite.' );
		}

		$admin_id  = $this->factory->user->create( array( 'role' => 'administrator' ) );
		$target_id = $this->factory->user->create( array( 'role' => 'subscriber' ) );

		bbp_set_user_role( $admin_id, bbp_get_participant_role() );
		remove_user_from_blog( $target_id, get_current_blog_id() );
		$this->set_current_user( $admin_id );

		$this->assertFalse( is_user_member_of_blog( $target_id ) );
		$this->set_bulk_request( $target_id, bbp_get_participant_role() );
		( new BBP_Users_Admin() )->user_role_bulk_change();

		$this->assertFalse( is_user_member_of_blog( $target_id ) );
	}

	/**
	 * @covers BBP_Users_Admin::user_role_bulk_change
	 */
	public function test_bulk_role_change_respects_target_role_allowlist() {
		$admin_id  = $this->factory->user->create( array( 'role' => 'administrator' ) );
		$target_id = $this->factory->user->create( array( 'role' => 'subscriber' ) );

		bbp_set_user_role( $admin_id, bbp_get_participant_role() );
		bbp_set_user_role( $target_id, bbp_get_participant_role() );
		$this->set_current_user( $admin_id );

		$this->denied_target_id = $target_id;
		add_filter( 'bbp_get_user_editable_forum_roles', array( $this, 'deny_target_roles' ), 10, 2 );
		$this->set_bulk_request( $target_id, bbp_get_spectator_role() );
		( new BBP_Users_Admin() )->user_role_bulk_change();

		$this->assertSame( bbp_get_participant_role(), bbp_get_user_role( $target_id ) );
	}

	/**
	 * @covers BBP_Users_Admin::user_role_bulk_change
	 */
	public function test_bulk_role_change_keeps_allowed_role_changes_working() {
		$admin_id  = $this->factory->user->create( array( 'role' => 'administrator' ) );
		$target_id = $this->factory->user->create( array( 'role' => 'subscriber' ) );

		bbp_set_user_role( $admin_id, bbp_get_participant_role() );
		bbp_set_user_role( $target_id, bbp_get_participant_role() );
		$this->set_current_user( $admin_id );
		$this->set_bulk_request( $target_id, bbp_get_spectator_role() );
		( new BBP_Users_Admin() )->user_role_bulk_change();

		$this->assertSame( bbp_get_spectator_role(), bbp_get_user_role( $target_id ) );
	}

	/**
	 * @covers BBP_Users_Admin::user_role_bulk_change
	 */
	public function test_site_administrator_can_bulk_assign_staff_roles() {
		$admin_id     = $this->factory->user->create( array( 'role' => 'administrator' ) );
		$moderator_id = $this->factory->user->create( array( 'role' => 'subscriber' ) );
		$keymaster_id = $this->factory->user->create( array( 'role' => 'subscriber' ) );

		bbp_set_user_role( $admin_id, bbp_get_participant_role() );
		$this->set_current_user( $admin_id );

		$this->assertTrue( current_user_can( 'manage_options' ) );
		$this->assertFalse( bbp_is_user_keymaster() );
		$this->assertArrayHasKey( bbp_get_moderator_role(), bbp_get_user_editable_forum_roles( $moderator_id ) );
		$this->assertArrayHasKey( bbp_get_keymaster_role(), bbp_get_user_editable_forum_roles( $keymaster_id ) );
		if ( ! is_multisite() ) {
			ob_start();
			BBP_Users_Admin::secondary_role_display( get_userdata( $keymaster_id ) );
			$profile = ob_get_clean();
			$this->assertStringContainsString( 'value="' . bbp_get_keymaster_role() . '"', $profile );
		}
		ob_start();
		BBP_Users_Admin::user_role_bulk_dropdown( 'top' );
		$dropdown = ob_get_clean();
		$this->assertStringContainsString( 'value="' . bbp_get_keymaster_role() . '"', $dropdown );
		$this->assertStringContainsString( 'value="' . bbp_get_moderator_role() . '"', $dropdown );

		$this->set_bulk_request( $moderator_id, bbp_get_moderator_role() );
		( new BBP_Users_Admin() )->user_role_bulk_change();
		$this->set_bulk_request( $keymaster_id, bbp_get_keymaster_role() );
		( new BBP_Users_Admin() )->user_role_bulk_change();

		$this->assertSame( bbp_get_moderator_role(), bbp_get_user_role( $moderator_id ) );
		$this->assertSame( bbp_get_keymaster_role(), bbp_get_user_role( $keymaster_id ) );
	}

	/**
	 * @covers BBP_Users_Admin::user_role_bulk_change
	 */
	public function test_non_site_administrator_cannot_bulk_assign_staff_role() {
		$admin_id  = $this->factory->user->create( array( 'role' => 'administrator' ) );
		$target_id = $this->factory->user->create( array( 'role' => 'subscriber' ) );

		bbp_set_user_role( $admin_id, bbp_get_participant_role() );
		get_userdata( $admin_id )->add_cap( 'manage_options', false );
		$this->set_current_user( $admin_id );

		$this->assertTrue( current_user_can( 'promote_users' ) );
		$this->assertFalse( current_user_can( 'manage_options' ) );
		if ( ! is_multisite() ) {
			ob_start();
			BBP_Users_Admin::secondary_role_display( get_userdata( $target_id ) );
			$profile = ob_get_clean();
			$this->assertStringNotContainsString( 'value="' . bbp_get_moderator_role() . '"', $profile );
		}
		ob_start();
		BBP_Users_Admin::user_role_bulk_dropdown( 'top' );
		$dropdown = ob_get_clean();
		$this->assertStringNotContainsString( 'value="' . bbp_get_moderator_role() . '"', $dropdown );
		$this->set_bulk_request( $target_id, bbp_get_moderator_role() );
		( new BBP_Users_Admin() )->user_role_bulk_change();

		$this->assertNotSame( bbp_get_moderator_role(), bbp_get_user_role( $target_id ) );

		bbp_set_user_role( $target_id, bbp_get_keymaster_role() );
		$this->set_bulk_request( $target_id, bbp_get_spectator_role() );
		( new BBP_Users_Admin() )->user_role_bulk_change();

		$this->assertSame( bbp_get_keymaster_role(), bbp_get_user_role( $target_id ) );
	}

	public function deny_target_promotion( $caps, $cap, $user_id, $args ) {
		if ( ( 'promote_user' === $cap ) && ! empty( $args[0] ) && ( $this->denied_target_id === (int) $args[0] ) ) {
			return array( 'do_not_allow' );
		}

		return $caps;
	}

	public function deny_target_roles( $roles, $user_id ) {
		return ( $this->denied_target_id === (int) $user_id ) ? array() : $roles;
	}

	private function set_bulk_request( $user_id, $role ) {
		$_REQUEST = array(
			'users'                => array( $user_id ),
			'bbp-new-role'         => $role,
			'bbp-change-role'      => 'Change',
			'bbp-bulk-users-nonce' => wp_create_nonce( 'bbp-bulk-users' ),
		);
	}
}
