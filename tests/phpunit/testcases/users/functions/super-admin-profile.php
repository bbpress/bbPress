<?php

/**
 * Tests for multisite network role changes from user profiles.
 *
 * @group users
 * @group capabilities
 */
class BBP_Tests_Users_Functions_Super_Admin_Profile extends BBP_UnitTestCase {

	private $actor_id;
	private $target_id;

	public function setUp(): void {
		parent::setUp();

		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'Network roles require multisite.' );
		}

		$this->actor_id  = $this->factory->user->create( array( 'role' => 'administrator' ) );
		$this->target_id = $this->factory->user->create( array( 'role' => 'administrator' ) );
		grant_super_admin( $this->actor_id );
		wp_set_current_user( $this->actor_id );
	}

	public function tearDown(): void {
		if ( is_multisite() ) {
			if ( $this->actor_id ) {
				revoke_super_admin( $this->actor_id );
			}

			if ( $this->target_id ) {
				revoke_super_admin( $this->target_id );
			}
		}

		parent::tearDown();
	}

	/**
	 * @covers ::bbp_maybe_update_user_super_admin
	 */
	public function test_missing_network_role_fields_preserve_super_admin() {
		grant_super_admin( $this->target_id );
		$this->assertTrue( current_user_can( 'promote_user', $this->target_id ) );

		bbp_maybe_update_user_super_admin( $this->target_id, array( 'nickname' => 'Updated nickname' ) );
		$this->assertTrue( is_super_admin( $this->target_id ) );
	}

	/**
	 * @covers ::bbp_maybe_update_user_super_admin
	 */
	public function test_unchecked_rendered_field_revokes_super_admin() {
		grant_super_admin( $this->target_id );
		$this->assertTrue( is_super_admin( $this->target_id ) );

		bbp_maybe_update_user_super_admin( $this->target_id, array( 'bbp_super_admin_present' => '1' ) );
		$this->assertFalse( is_super_admin( $this->target_id ) );
	}

	/**
	 * @covers ::bbp_maybe_update_user_super_admin
	 */
	public function test_checked_legacy_field_grants_super_admin() {
		$this->assertFalse( is_super_admin( $this->target_id ) );

		bbp_maybe_update_user_super_admin( $this->target_id, array( 'super_admin' => 'on' ) );
		$this->assertTrue( is_super_admin( $this->target_id ) );
	}

	/**
	 * @covers ::bbp_maybe_update_user_super_admin
	 */
	public function test_site_admin_cannot_change_network_role() {
		grant_super_admin( $this->target_id );
		revoke_super_admin( $this->actor_id );
		wp_set_current_user( $this->actor_id );
		$this->assertFalse( current_user_can( 'manage_network_options' ) );

		bbp_maybe_update_user_super_admin( $this->target_id, array( 'bbp_super_admin_present' => '1' ) );
		$this->assertTrue( is_super_admin( $this->target_id ) );
	}
}
