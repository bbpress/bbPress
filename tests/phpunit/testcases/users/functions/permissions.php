<?php

/**
 * Tests for user profile permissions.
 *
 * @group users
 * @group capabilities
 */
class BBP_Tests_Users_Functions_Permissions extends BBP_UnitTestCase {

	private $allow_super_mods;
	private $allow_global_access;
	private $request_method;

	public function setUp(): void {
		parent::setUp();

		$this->allow_super_mods    = get_option( '_bbp_allow_super_mods', null );
		$this->allow_global_access = get_option( '_bbp_allow_global_access', null );
		$this->request_method      = isset( $_SERVER['REQUEST_METHOD'] )
			? $_SERVER['REQUEST_METHOD']
			: null;
	}

	public function tearDown(): void {
		remove_filter( 'bbp_is_single_user', '__return_true' );
		remove_filter( 'bbp_allow_global_access', '__return_false' );
		remove_filter( 'bbp_current_user_can_edit_user_field', '__return_true' );
		remove_filter( 'bbp_current_user_can_edit_user_field', array( $this, 'deny_profile_field' ), 10 );
		remove_filter( 'bbp_get_user_editable_forum_roles', array( $this, 'allow_all_forum_roles' ), 10 );
		remove_filter( 'bbp_map_primary_meta_caps', array( $this, 'remap_edit_user_capability' ), 10 );
		remove_filter( 'user_contactmethods', array( $this, 'add_test_contact_method' ), 10 );

		if ( null === $this->allow_super_mods ) {
			delete_option( '_bbp_allow_super_mods' );
		} else {
			update_option( '_bbp_allow_super_mods', $this->allow_super_mods );
		}

		if ( null === $this->allow_global_access ) {
			delete_option( '_bbp_allow_global_access' );
		} else {
			update_option( '_bbp_allow_global_access', $this->allow_global_access );
		}

		if ( null === $this->request_method ) {
			unset( $_SERVER['REQUEST_METHOD'] );
		} else {
			$_SERVER['REQUEST_METHOD'] = $this->request_method;
		}

		set_current_screen( 'front' );

		$wp_query                          = bbp_get_wp_query();
		$wp_query->bbp_is_single_user      = false;
		$wp_query->bbp_is_single_user_edit = false;
		$wp_query->bbp_is_single_user_home = false;
		$wp_query->bbp_is_single_user_profile = false;
		$wp_query->bbp_is_single_user_subs = false;

		$_POST    = array();
		$_REQUEST = array();

		parent::tearDown();
	}

	/**
	 * @covers ::bbp_map_primary_meta_caps
	 */
	public function test_super_moderator_capabilities_are_scoped_to_the_profile_editor() {
		$moderator_id = $this->factory->user->create();
		$target_id    = $this->factory->user->create( array( 'role' => 'subscriber' ) );

		bbp_set_user_role( $moderator_id, bbp_get_moderator_role() );
		update_option( '_bbp_allow_super_mods', 1 );
		wp_set_current_user( $moderator_id );

		$this->assertFalse( current_user_can( 'edit_user', $target_id ) );
		$this->assertFalse( current_user_can( 'promote_user', $target_id ) );
		$this->assertFalse( current_user_can( 'edit_users' ) );
		$this->assertFalse( current_user_can( 'promote_users' ) );

		$this->set_profile_editor( $target_id );

		$this->assertTrue( current_user_can( 'edit_user', $target_id ) );
		$this->assertTrue( current_user_can( 'promote_user', $target_id ) );
		$this->assertTrue( bbp_current_user_can_edit_user_field( 'profile', $target_id ) );
		$this->assertFalse( current_user_can( 'edit_users' ) );
		$this->assertFalse( current_user_can( 'promote_users' ) );

		set_current_screen( 'user-edit.php' );

		$this->assertFalse( current_user_can( 'edit_user', $target_id ) );
		$this->assertFalse( current_user_can( 'promote_user', $target_id ) );
	}

	public function test_super_moderator_cannot_demote_another_moderator() {
		$moderator_id = $this->factory->user->create();
		$target_id    = $this->factory->user->create();

		bbp_set_user_role( $moderator_id, bbp_get_moderator_role() );
		bbp_set_user_role( $target_id, bbp_get_moderator_role() );
		update_option( '_bbp_allow_super_mods', 1 );
		wp_set_current_user( $moderator_id );
		$this->set_profile_editor( $target_id );
		$this->set_profile_request( $target_id, bbp_get_participant_role() );

		$this->assertTrue( current_user_can( 'edit_user', $target_id ) );
		$this->assertFalse( current_user_can( 'promote_user', $target_id ) );
		bbp_profile_update_role( $target_id );
		$this->assertSame( bbp_get_moderator_role(), bbp_get_user_role( $target_id ) );
	}

	public function test_super_moderator_cannot_edit_nonmember_when_global_access_is_disabled() {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'Current-site membership requires multisite.' );
		}

		$moderator_id = $this->factory->user->create();
		$target_id    = $this->factory->user->create();
		$site_id      = get_current_blog_id();

		bbp_set_user_role( $moderator_id, bbp_get_moderator_role() );
		update_option( '_bbp_allow_super_mods', 1 );
		update_option( '_bbp_allow_global_access', 0 );
		wp_set_current_user( $moderator_id );
		$this->set_profile_editor( $target_id );

		$this->assertTrue( current_user_can( 'edit_user', $target_id ) );
		remove_user_from_blog( $target_id, $site_id );
		$this->assertFalse( is_user_member_of_blog( $target_id, $site_id ) );
		$this->assertFalse( current_user_can( 'edit_user', $target_id ) );
		$this->assertFalse( current_user_can( 'promote_user', $target_id ) );
		$this->assertFalse( bbp_current_user_can_edit_user_field( 'profile', $target_id ) );
		$this->assertFalse( bbp_current_user_can_edit_user_field( 'forum_role', $target_id ) );
	}

	public function test_super_moderator_can_edit_nonmember_forum_role_when_global_access_is_enabled() {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'Current-site membership requires multisite.' );
		}

		$moderator_id = $this->factory->user->create();
		$target_id    = $this->factory->user->create();
		$site_id      = get_current_blog_id();

		bbp_set_user_role( $moderator_id, bbp_get_moderator_role() );
		update_option( '_bbp_allow_super_mods', 1 );
		update_option( '_bbp_allow_global_access', 1 );
		remove_user_from_blog( $target_id, $site_id );
		wp_set_current_user( $moderator_id );
		$this->set_profile_editor( $target_id );

		$this->assertFalse( is_user_member_of_blog( $target_id, $site_id ) );
		$this->assertTrue( current_user_can( 'edit_user', $target_id ) );
		$this->assertTrue( current_user_can( 'promote_user', $target_id ) );
		$this->assertFalse( bbp_current_user_can_edit_user_field( 'profile', $target_id ) );
		$this->assertFalse( bbp_current_user_can_edit_user_field( 'email', $target_id ) );
		$this->assertFalse( bbp_current_user_can_edit_user_field( 'password', $target_id ) );
		$this->assertFalse( bbp_current_user_can_edit_user_field( 'site_role', $target_id ) );
		$this->assertTrue( bbp_current_user_can_edit_user_field( 'forum_role', $target_id ) );

		$roles = bbp_get_user_editable_forum_roles( $target_id );

		$this->assertArrayHasKey( bbp_get_participant_role(), $roles );
		$this->assertArrayHasKey( bbp_get_blocked_role(), $roles );
		$this->assertArrayNotHasKey( bbp_get_moderator_role(), $roles );
		$this->assertArrayNotHasKey( bbp_get_keymaster_role(), $roles );
	}

	public function test_super_moderator_cannot_edit_nonexistent_user_with_global_access() {
		$moderator_id = $this->factory->user->create();
		$target_id    = PHP_INT_MAX;

		bbp_set_user_role( $moderator_id, bbp_get_moderator_role() );
		update_option( '_bbp_allow_super_mods', 1 );
		update_option( '_bbp_allow_global_access', 1 );
		wp_set_current_user( $moderator_id );
		$this->set_profile_editor( $target_id );

		$this->assertFalse( get_userdata( $target_id ) );
		$this->assertFalse( current_user_can( 'edit_user', $target_id ) );
		$this->assertFalse( current_user_can( 'promote_user', $target_id ) );
		$this->assertFalse( bbp_current_user_can_edit_user_field( 'profile', $target_id ) );
		$this->assertFalse( bbp_current_user_can_edit_user_field( 'forum_role', $target_id ) );
	}

	public function test_super_moderator_cannot_edit_nonmember_when_global_access_is_filtered_off() {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'Current-site membership requires multisite.' );
		}

		$moderator_id = $this->factory->user->create();
		$target_id    = $this->factory->user->create();
		$site_id      = get_current_blog_id();

		bbp_set_user_role( $moderator_id, bbp_get_moderator_role() );
		remove_user_from_blog( $target_id, $site_id );
		update_option( '_bbp_allow_super_mods', 1 );
		update_option( '_bbp_allow_global_access', 1 );
		add_filter( 'bbp_allow_global_access', '__return_false' );
		wp_set_current_user( $moderator_id );
		$this->set_profile_editor( $target_id );

		$this->assertFalse( bbp_allow_global_access() );
		$this->assertFalse( current_user_can( 'edit_user', $target_id ) );
		$this->assertFalse( current_user_can( 'promote_user', $target_id ) );
		$this->assertFalse( bbp_current_user_can_edit_user_field( 'forum_role', $target_id ) );
	}

	public function test_super_moderator_can_edit_nonmember_forum_role_for_another_site_administrator() {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'Current-site membership requires multisite.' );
		}

		$moderator_id = $this->factory->user->create();
		$target_id    = $this->factory->user->create();
		$site_id      = get_current_blog_id();
		$other_site   = $this->factory->blog->create();

		bbp_set_user_role( $moderator_id, bbp_get_moderator_role() );
		add_user_to_blog( $other_site, $target_id, 'administrator' );
		remove_user_from_blog( $target_id, $site_id );
		update_option( '_bbp_allow_super_mods', 1 );
		update_option( '_bbp_allow_global_access', 1 );
		wp_set_current_user( $moderator_id );
		$this->set_profile_editor( $target_id );

		$this->assertFalse( is_user_member_of_blog( $target_id, $site_id ) );
		$this->assertTrue( is_user_member_of_blog( $target_id, $other_site ) );
		$this->assertTrue( current_user_can( 'edit_user', $target_id ) );
		$this->assertTrue( current_user_can( 'promote_user', $target_id ) );
		$this->assertFalse( bbp_current_user_can_edit_user_field( 'profile', $target_id ) );
		$this->assertTrue( bbp_current_user_can_edit_user_field( 'forum_role', $target_id ) );
	}

	public function test_super_moderator_can_edit_site_member_when_global_access_is_disabled() {
		$moderator_id = $this->factory->user->create();
		$target_id    = $this->factory->user->create( array( 'role' => 'subscriber' ) );

		bbp_set_user_role( $moderator_id, bbp_get_moderator_role() );
		update_option( '_bbp_allow_super_mods', 1 );
		update_option( '_bbp_allow_global_access', 0 );
		wp_set_current_user( $moderator_id );
		$this->set_profile_editor( $target_id );

		$this->assertTrue( current_user_can( 'edit_user', $target_id ) );
		$this->assertTrue( current_user_can( 'promote_user', $target_id ) );
		$this->assertTrue( bbp_current_user_can_edit_user_field( 'profile', $target_id ) );
		$this->assertFalse( bbp_current_user_can_edit_user_field( 'email', $target_id ) );
		$this->assertFalse( bbp_current_user_can_edit_user_field( 'password', $target_id ) );
		$this->assertFalse( bbp_current_user_can_edit_user_field( 'site_role', $target_id ) );
		$this->assertTrue( bbp_current_user_can_edit_user_field( 'forum_role', $target_id ) );
	}

	public function test_super_moderator_cannot_edit_nonmember_super_admin_with_global_access() {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'Current-site membership requires multisite.' );
		}

		$moderator_id = $this->factory->user->create();
		$target_id    = $this->factory->user->create();
		$site_id      = get_current_blog_id();

		bbp_set_user_role( $moderator_id, bbp_get_moderator_role() );
		grant_super_admin( $target_id );
		remove_user_from_blog( $target_id, $site_id );
		update_option( '_bbp_allow_super_mods', 1 );
		update_option( '_bbp_allow_global_access', 1 );
		wp_set_current_user( $moderator_id );
		$this->set_profile_editor( $target_id );

		$this->assertTrue( is_super_admin( $target_id ) );
		$this->assertFalse( is_user_member_of_blog( $target_id, $site_id ) );
		$this->assertFalse( current_user_can( 'edit_user', $target_id ) );
		$this->assertFalse( current_user_can( 'promote_user', $target_id ) );
		$this->assertFalse( bbp_current_user_can_edit_user_field( 'forum_role', $target_id ) );

		revoke_super_admin( $target_id );
	}

	public function test_network_admin_can_edit_nonmember_profile_fields() {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'Current-site membership requires multisite.' );
		}

		$manager_id = $this->factory->user->create();
		$target_id  = $this->factory->user->create();
		$site_id    = get_current_blog_id();

		grant_super_admin( $manager_id );
		remove_user_from_blog( $target_id, $site_id );
		update_option( '_bbp_allow_global_access', 0 );
		wp_set_current_user( $manager_id );
		$this->set_profile_editor( $target_id );

		$this->assertFalse( is_user_member_of_blog( $target_id, $site_id ) );
		$this->assertTrue( current_user_can( 'manage_network_users' ) );
		$this->assertTrue( current_user_can( 'edit_user', $target_id ) );
		$this->assertTrue( bbp_current_user_can_edit_user_field( 'profile', $target_id ) );
		$this->assertTrue( bbp_current_user_can_edit_user_field( 'email', $target_id ) );
		$this->assertTrue( bbp_current_user_can_edit_user_field( 'password', $target_id ) );
		$this->assertTrue( bbp_current_user_can_edit_user_field( 'site_role', $target_id ) );
		$this->assertTrue( bbp_current_user_can_edit_user_field( 'forum_role', $target_id ) );

		revoke_super_admin( $manager_id );
	}

	public function test_super_moderator_capabilities_apply_on_profile_views() {
		$moderator_id = $this->factory->user->create();
		$keymaster_id = $this->factory->user->create();
		$admin_id     = $this->factory->user->create( array( 'role' => 'administrator' ) );
		$target_id    = $this->factory->user->create( array( 'role' => 'subscriber' ) );

		bbp_set_user_role( $moderator_id, bbp_get_moderator_role() );
		bbp_set_user_role( $keymaster_id, bbp_get_keymaster_role() );
		update_option( '_bbp_allow_super_mods', 1 );
		wp_set_current_user( $moderator_id );

		$this->set_profile_view( $target_id );
		bbp_get_wp_query()->bbp_is_single_user_profile = true;

		// Profile templates ask for these to decide whether to link to the editor.
		$this->assertTrue( bbp_is_single_user_profile() );
		$this->assertFalse( bbp_is_user_home() );
		$this->assertTrue( current_user_can( 'edit_user', $target_id ) );
		$this->assertTrue( current_user_can( 'promote_user', $target_id ) );
		$this->assertFalse( current_user_can( 'edit_users' ) );
		$this->assertFalse( current_user_can( 'promote_users' ) );

		// Protected targets stay protected.
		$this->assertFalse( current_user_can( 'edit_user', $keymaster_id ) );
		$this->assertFalse( current_user_can( 'edit_user', $admin_id ) );

		if ( is_multisite() ) {
			$super_admin_id = $this->factory->user->create();
			grant_super_admin( $super_admin_id );
			$this->assertFalse( current_user_can( 'edit_user', $super_admin_id ) );
			revoke_super_admin( $super_admin_id );
		}

		set_current_screen( 'user-edit.php' );

		$this->assertFalse( current_user_can( 'edit_user', $target_id ) );
		$this->assertFalse( current_user_can( 'promote_user', $target_id ) );
	}

	public function test_super_moderator_capabilities_apply_on_subscription_views() {
		$moderator_id = $this->factory->user->create();
		$target_id    = $this->factory->user->create( array( 'role' => 'subscriber' ) );

		bbp_set_user_role( $moderator_id, bbp_get_moderator_role() );
		update_option( '_bbp_allow_super_mods', 1 );
		wp_set_current_user( $moderator_id );

		$this->set_profile_view( $target_id );
		bbp_get_wp_query()->bbp_is_single_user_subs = true;

		$this->assertTrue( bbp_is_subscriptions() );
		$this->assertFalse( bbp_is_user_home() );
		$this->assertTrue( current_user_can( 'edit_user', $target_id ) );
		$this->assertTrue( bbp_current_user_can_edit_user_field( 'profile', $target_id ) );
	}

	public function test_super_moderator_capabilities_ignore_filtered_non_bbp_user_contexts() {
		$moderator_id = $this->factory->user->create();
		$target_id    = $this->factory->user->create( array( 'role' => 'subscriber' ) );

		bbp_set_user_role( $moderator_id, bbp_get_moderator_role() );
		update_option( '_bbp_allow_super_mods', 1 );
		wp_set_current_user( $moderator_id );
		add_filter( 'bbp_is_single_user', '__return_true' );

		$this->assertTrue( bbp_is_single_user() );
		$this->assertEmpty( bbp_get_wp_query()->bbp_is_single_user );
		$this->assertFalse( current_user_can( 'edit_user', $target_id ) );
		$this->assertFalse( current_user_can( 'promote_user', $target_id ) );
	}

	/**
	 * @covers ::bbp_current_user_can_edit_user_field
	 * @covers ::bbp_get_user_editable_forum_roles
	 */
	public function test_super_moderator_profile_field_defaults() {
		$moderator_id = $this->factory->user->create();
		$target_id    = $this->factory->user->create( array( 'role' => 'subscriber' ) );

		bbp_set_user_role( $moderator_id, bbp_get_moderator_role() );
		update_option( '_bbp_allow_super_mods', 1 );
		wp_set_current_user( $moderator_id );
		$this->set_profile_editor( $target_id );

		$this->assertTrue( bbp_current_user_can_edit_user_field( 'profile', $target_id ) );
		$this->assertFalse( bbp_current_user_can_edit_user_field( 'email', $target_id ) );
		$this->assertTrue( bbp_current_user_can_edit_user_field( 'forum_role', $target_id ) );
		$this->assertFalse( bbp_current_user_can_edit_user_field( 'password', $target_id ) );
		$this->assertFalse( bbp_current_user_can_edit_user_field( 'site_role', $target_id ) );
		$this->assertTrue( bbp_current_user_can_edit_user_field( 'email', $moderator_id ) );
		$this->assertTrue( bbp_current_user_can_edit_user_field( 'password', $moderator_id ) );

		$roles = bbp_get_user_editable_forum_roles( $target_id );

		$this->assertArrayHasKey( bbp_get_participant_role(), $roles );
		$this->assertArrayNotHasKey( bbp_get_moderator_role(), $roles );
		$this->assertArrayNotHasKey( bbp_get_keymaster_role(), $roles );
	}

	public function test_password_policy_respects_protected_targets_and_valid_users() {
		$moderator_id = $this->factory->user->create();
		$keymaster_id = $this->factory->user->create();
		$admin_id     = $this->factory->user->create( array( 'role' => 'administrator' ) );

		bbp_set_user_role( $moderator_id, bbp_get_moderator_role() );
		bbp_set_user_role( $keymaster_id, bbp_get_keymaster_role() );
		update_option( '_bbp_allow_super_mods', 1 );
		wp_set_current_user( $moderator_id );

		$this->set_profile_editor( $keymaster_id );
		$this->assertFalse( bbp_current_user_can_edit_user_field( 'password', $keymaster_id ) );

		$this->set_profile_editor( $admin_id );
		$this->assertFalse( bbp_current_user_can_edit_user_field( 'password', $admin_id ) );

		wp_set_current_user( 0 );
		$this->assertFalse( bbp_current_user_can_edit_user_field( 'password', 0 ) );
	}

	public function test_password_field_filter_is_scoped_to_bbp_profile_editor() {
		$moderator_id = $this->factory->user->create();
		$target_id    = $this->factory->user->create( array( 'role' => 'subscriber' ) );
		$target       = get_userdata( $target_id );

		bbp_set_user_role( $moderator_id, bbp_get_moderator_role() );
		update_option( '_bbp_allow_super_mods', 1 );
		wp_set_current_user( $moderator_id );
		$this->set_profile_editor( $target_id );

		$this->assertFalse( bbp_filter_user_edit_password_fields( true, $target ) );

		set_current_screen( 'user-edit.php' );

		$this->assertTrue( bbp_filter_user_edit_password_fields( true, $target ) );
	}

	public function test_wp_admin_user_permissions_remain_native() {
		$moderator_id = $this->factory->user->create();
		$keymaster_id = $this->factory->user->create();
		$admin_id     = $this->factory->user->create( array( 'role' => 'administrator' ) );
		$target_id    = $this->factory->user->create( array( 'role' => 'subscriber' ) );
		$target       = get_userdata( $target_id );

		bbp_set_user_role( $moderator_id, bbp_get_moderator_role() );
		bbp_set_user_role( $keymaster_id, bbp_get_keymaster_role() );
		update_option( '_bbp_allow_super_mods', 1 );
		set_current_screen( 'user-edit.php' );
		wp_set_current_user( $moderator_id );

		$this->assertFalse( current_user_can( 'edit_user', $target_id ) );
		$this->assertFalse( current_user_can( 'promote_user', $target_id ) );
		$this->assertFalse( current_user_can( 'edit_users' ) );
		$this->assertFalse( current_user_can( 'list_users' ) );
		$this->assertTrue( bbp_filter_user_edit_password_fields( true, $target ) );

		wp_set_current_user( $keymaster_id );

		$this->assertFalse( current_user_can( 'edit_user', $target_id ) );
		$this->assertFalse( current_user_can( 'promote_user', $target_id ) );
		$this->assertFalse( current_user_can( 'edit_users' ) );
		$this->assertFalse( current_user_can( 'list_users' ) );
		$this->assertTrue( bbp_filter_user_edit_password_fields( true, $target ) );

		wp_set_current_user( $admin_id );

		if ( is_multisite() ) {
			$this->assertFalse( current_user_can( 'edit_user', $target_id ) );
			$this->assertTrue( current_user_can( 'promote_user', $target_id ) );
			$this->assertFalse( current_user_can( 'edit_users' ) );
			$this->assertTrue( current_user_can( 'list_users' ) );
		} else {
			$this->assertTrue( current_user_can( 'edit_user', $target_id ) );
			$this->assertTrue( current_user_can( 'promote_user', $target_id ) );
			$this->assertTrue( current_user_can( 'edit_users' ) );
			$this->assertTrue( current_user_can( 'list_users' ) );
		}
		$this->assertTrue( bbp_filter_user_edit_password_fields( true, $target ) );
	}

	public function test_profile_field_and_forum_role_policies_are_filterable() {
		$moderator_id = $this->factory->user->create();
		$target_id    = $this->factory->user->create( array( 'role' => 'subscriber' ) );

		bbp_set_user_role( $moderator_id, bbp_get_moderator_role() );
		update_option( '_bbp_allow_super_mods', 1 );
		wp_set_current_user( $moderator_id );
		$this->set_profile_editor( $target_id );

		add_filter( 'bbp_current_user_can_edit_user_field', '__return_true' );
		add_filter( 'bbp_get_user_editable_forum_roles', array( $this, 'allow_all_forum_roles' ), 10, 1 );

		$this->assertTrue( bbp_current_user_can_edit_user_field( 'password', $target_id ) );
		$this->assertTrue( bbp_current_user_can_edit_user_field( 'email', $target_id ) );
		$this->assertTrue( bbp_current_user_can_edit_user_field( 'site_role', $target_id ) );
		$this->assertArrayHasKey( bbp_get_keymaster_role(), bbp_get_user_editable_forum_roles( $target_id ) );
	}

	/**
	 * @covers ::bbp_filter_user_edit_post_data
	 */
	public function test_user_edit_post_data_enforces_field_permissions() {
		$moderator_id = $this->factory->user->create();
		$target_id    = $this->factory->user->create(
			array(
				'role'       => 'subscriber',
				'user_email' => 'existing@example.org',
			)
		);

		bbp_set_user_role( $moderator_id, bbp_get_moderator_role() );
		update_option( '_bbp_allow_super_mods', 1 );
		wp_set_current_user( $moderator_id );
		$this->set_profile_editor( $target_id );

		$data = bbp_filter_user_edit_post_data(
			array(
				'first_name' => 'Updated',
				'email'      => 'updated@example.org',
				'pass1'      => 'example password',
				'pass2'      => 'example password',
				'role'       => 'administrator',
			),
			$target_id
		);

		$this->assertSame( 'Updated', $data['first_name'] );
		$this->assertSame( 'existing@example.org', $data['email'] );
		$this->assertArrayNotHasKey( 'pass1', $data );
		$this->assertArrayNotHasKey( 'pass2', $data );
		$this->assertArrayNotHasKey( 'role', $data );
	}

	/**
	 * @covers ::bbp_filter_user_edit_post_data
	 */
	public function test_nonmember_user_edit_post_data_protects_shared_profile_fields() {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'Current-site membership requires multisite.' );
		}

		$moderator_id = $this->factory->user->create();
		$target_id    = $this->factory->user->create(
			array(
				'first_name'   => 'Original',
				'last_name'    => 'Person',
				'nickname'     => 'original-nickname',
				'display_name' => 'Original Person',
				'user_email'   => 'existing-nonmember@example.org',
				'user_url'     => 'https://example.org/original',
				'description'  => 'Original biography',
			)
		);
		$site_id      = get_current_blog_id();

		bbp_set_user_role( $moderator_id, bbp_get_moderator_role() );
		remove_user_from_blog( $target_id, $site_id );
		update_option( '_bbp_allow_super_mods', 1 );
		update_option( '_bbp_allow_global_access', 1 );
		wp_set_current_user( $moderator_id );
		$this->set_profile_editor( $target_id );
		add_filter( 'user_contactmethods', array( $this, 'add_test_contact_method' ), 10, 1 );

		$data = bbp_filter_user_edit_post_data(
			array(
				'first_name'     => 'Changed',
				'last_name'      => 'Changed',
				'nickname'       => 'changed-nickname',
				'display_name'   => 'Changed Person',
				'email'          => 'changed-nonmember@example.org',
				'url'            => 'https://example.org/changed',
				'description'    => 'Changed biography',
				'locale'         => 'de_DE',
				'test_contact'   => 'changed-contact',
				'pass1'          => 'example password',
				'pass2'          => 'example password',
				'role'           => 'administrator',
				'bbp-forums-role' => bbp_get_blocked_role(),
			),
			$target_id
		);

		$this->assertSame( 'original-nickname', $data['nickname'] );
		$this->assertSame( 'existing-nonmember@example.org', $data['email'] );
		$this->assertSame( bbp_get_blocked_role(), $data['bbp-forums-role'] );
		$this->assertArrayNotHasKey( 'first_name', $data );
		$this->assertArrayNotHasKey( 'last_name', $data );
		$this->assertArrayNotHasKey( 'display_name', $data );
		$this->assertArrayNotHasKey( 'url', $data );
		$this->assertArrayNotHasKey( 'description', $data );
		$this->assertArrayNotHasKey( 'locale', $data );
		$this->assertArrayNotHasKey( 'test_contact', $data );
		$this->assertArrayNotHasKey( 'pass1', $data );
		$this->assertArrayNotHasKey( 'pass2', $data );
		$this->assertArrayNotHasKey( 'role', $data );
	}

	public function test_super_moderator_can_block_nonmember_with_global_access() {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'Current-site membership requires multisite.' );
		}

		$moderator_id = $this->factory->user->create();
		$target_id    = $this->factory->user->create();
		$site_id      = get_current_blog_id();

		bbp_set_user_role( $moderator_id, bbp_get_moderator_role() );
		remove_user_from_blog( $target_id, $site_id );
		update_option( '_bbp_allow_super_mods', 1 );
		update_option( '_bbp_allow_global_access', 1 );
		wp_set_current_user( $moderator_id );
		$this->set_profile_editor( $target_id );
		$this->set_profile_request( $target_id, bbp_get_blocked_role() );

		$this->assertFalse( bbp_get_user_role( $target_id ) );
		$this->assertTrue( current_user_can( 'promote_user', $target_id ) );
		bbp_profile_update_role( $target_id );
		$this->assertSame( bbp_get_blocked_role(), bbp_get_user_role( $target_id ) );
		$this->assertTrue( is_user_member_of_blog( $target_id, $site_id ) );
		$this->assertTrue( bbp_current_user_can_edit_user_field( 'profile', $target_id ) );
		$this->assertFalse( bbp_current_user_can_edit_user_field( 'email', $target_id ) );
		$this->assertTrue( bbp_current_user_can_edit_user_field( 'forum_role', $target_id ) );
	}

	public function test_super_moderator_cannot_assign_staff_role_to_nonmember() {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'Current-site membership requires multisite.' );
		}

		$moderator_id = $this->factory->user->create();
		$target_id    = $this->factory->user->create();
		$site_id      = get_current_blog_id();

		bbp_set_user_role( $moderator_id, bbp_get_moderator_role() );
		remove_user_from_blog( $target_id, $site_id );
		update_option( '_bbp_allow_super_mods', 1 );
		update_option( '_bbp_allow_global_access', 1 );
		wp_set_current_user( $moderator_id );
		$this->set_profile_editor( $target_id );
		$this->set_profile_request( $target_id, bbp_get_moderator_role() );

		$this->assertTrue( current_user_can( 'promote_user', $target_id ) );
		bbp_profile_update_role( $target_id );
		$this->assertFalse( bbp_get_user_role( $target_id ) );
		$this->assertFalse( is_user_member_of_blog( $target_id, $site_id ) );
	}

	public function test_edit_user_updates_nonmember_forum_role_without_shared_profile_changes() {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'Current-site membership requires multisite.' );
		}

		$moderator_id = $this->factory->user->create();
		$target_id    = $this->factory->user->create(
			array(
				'first_name'   => 'Original',
				'nickname'     => 'original-nickname',
				'display_name' => 'Original Person',
				'user_email'   => 'edit-nonmember@example.org',
			)
		);
		$site_id      = get_current_blog_id();

		bbp_set_user_role( $moderator_id, bbp_get_moderator_role() );
		remove_user_from_blog( $target_id, $site_id );
		update_option( '_bbp_allow_super_mods', 1 );
		update_option( '_bbp_allow_global_access', 1 );
		wp_set_current_user( $moderator_id );
		$this->set_profile_editor( $target_id );
		$this->set_profile_request( $target_id, bbp_get_blocked_role() );

		$_POST['first_name']   = 'Changed';
		$_POST['nickname']     = 'changed-nickname';
		$_POST['display_name'] = 'Changed Person';
		$_POST['email']        = 'changed-edit-nonmember@example.org';
		$_POST                 = bbp_filter_user_edit_post_data( $_POST, $target_id );
		$_REQUEST              = $_POST;

		$result = edit_user( $target_id );
		$user   = get_userdata( $target_id );

		$this->assertSame( $target_id, $result );
		$this->assertSame( 'Original', $user->first_name );
		$this->assertSame( 'original-nickname', $user->nickname );
		$this->assertSame( 'Original Person', $user->display_name );
		$this->assertSame( 'edit-nonmember@example.org', $user->user_email );
		$this->assertSame( bbp_get_blocked_role(), bbp_get_user_role( $target_id ) );
	}

	/**
	 * @covers ::bbp_filter_user_edit_post_data
	 */
	public function test_edit_user_uses_login_when_nonmember_nickname_is_empty() {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'Current-site membership requires multisite.' );
		}

		$moderator_id = $this->factory->user->create();
		$target_id    = $this->factory->user->create(
			array(
				'user_login' => 'nickname-fallback-user',
				'user_email' => 'nickname-fallback@example.org',
			)
		);
		$site_id      = get_current_blog_id();

		bbp_set_user_role( $moderator_id, bbp_get_moderator_role() );
		remove_user_from_blog( $target_id, $site_id );
		update_user_meta( $target_id, 'nickname', '' );
		update_option( '_bbp_allow_super_mods', 1 );
		update_option( '_bbp_allow_global_access', 1 );
		wp_set_current_user( $moderator_id );
		$this->set_profile_editor( $target_id );
		$this->set_profile_request( $target_id, bbp_get_blocked_role() );

		$_POST['nickname'] = 'changed-nickname';
		$_POST             = bbp_filter_user_edit_post_data( $_POST, $target_id );
		$_REQUEST          = $_POST;

		$this->assertSame( 'nickname-fallback-user', $_POST['nickname'] );
		$this->assertSame( $target_id, edit_user( $target_id ) );
		$this->assertSame( 'nickname-fallback-user', get_userdata( $target_id )->nickname );
		$this->assertSame( bbp_get_blocked_role(), bbp_get_user_role( $target_id ) );
	}

	/**
	 * @covers ::bbp_filter_user_edit_post_data
	 */
	public function test_user_edit_post_data_handles_nonexistent_user() {
		$data = bbp_filter_user_edit_post_data(
			array(
				'nickname' => 'changed-nickname',
				'email'    => 'changed@example.org',
			),
			PHP_INT_MAX
		);

		$this->assertArrayNotHasKey( 'nickname', $data );
		$this->assertSame( '', $data['email'] );
	}

	/**
	 * @covers ::bbp_edit_user_handler
	 */
	public function test_edit_user_handler_filters_nonmember_fields_before_update_hooks() {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'Current-site membership requires multisite.' );
		}

		$moderator_id = $this->factory->user->create();
		$target_id    = $this->factory->user->create(
			array(
				'first_name' => 'Original',
				'nickname'   => 'original-nickname',
				'user_email' => 'handler-nonmember@example.org',
			)
		);
		$site_id      = get_current_blog_id();
		$old_errors   = bbpress()->errors;
		$filtered     = null;
		$inspect      = function() use ( &$filtered ) {
			$filtered = $_POST;
			bbp_add_error( 'bbp_test_stop_update', 'Stop after inspecting filtered profile data.' );
		};

		bbp_set_user_role( $moderator_id, bbp_get_moderator_role() );
		remove_user_from_blog( $target_id, $site_id );
		update_option( '_bbp_allow_super_mods', 1 );
		update_option( '_bbp_allow_global_access', 1 );
		wp_set_current_user( $moderator_id );
		$this->set_profile_editor( $target_id );
		$this->set_profile_request( $target_id, bbp_get_blocked_role() );

		$_POST['first_name'] = 'Changed';
		$_POST['nickname']   = 'changed-nickname';
		$_POST['email']      = 'changed-handler@example.org';
		$_REQUEST            = $_POST;
		bbpress()->errors    = new WP_Error();
		add_action( 'edit_user_profile_update', $inspect, PHP_INT_MAX );

		try {
			bbp_edit_user_handler( 'bbp-update-user' );
		} finally {
			remove_action( 'edit_user_profile_update', $inspect, PHP_INT_MAX );
			bbpress()->errors = $old_errors;
		}

		$this->assertIsArray( $filtered );
		$this->assertArrayNotHasKey( 'first_name', $filtered );
		$this->assertSame( 'original-nickname', $filtered['nickname'] );
		$this->assertSame( 'handler-nonmember@example.org', $filtered['email'] );
		$this->assertSame( bbp_get_blocked_role(), $filtered['bbp-forums-role'] );
		$this->assertFalse( bbp_get_user_role( $target_id ) );
	}

	/**
	 * @covers ::bbp_filter_user_edit_post_data
	 */
	public function test_user_edit_post_data_removes_contact_methods_with_profile_fields() {
		$moderator_id = $this->factory->user->create();
		$target_id    = $this->factory->user->create( array( 'role' => 'subscriber' ) );

		bbp_set_user_role( $moderator_id, bbp_get_moderator_role() );
		update_option( '_bbp_allow_super_mods', 1 );
		wp_set_current_user( $moderator_id );
		$this->set_profile_editor( $target_id );

		add_filter( 'bbp_current_user_can_edit_user_field', array( $this, 'deny_profile_field' ), 10, 2 );
		add_filter( 'user_contactmethods', array( $this, 'add_test_contact_method' ), 10, 1 );

		$data = bbp_filter_user_edit_post_data(
			array(
				'first_name'   => 'Updated',
				'test_contact' => 'updated-contact',
			),
			$target_id
		);

		$this->assertArrayNotHasKey( 'first_name', $data );
		$this->assertArrayNotHasKey( 'test_contact', $data );
	}

	/**
	 * @covers ::bbp_user_email_change_requires_confirmation
	 */
	public function test_email_confirmation_policy_distinguishes_self_and_moderator_edits() {
		$moderator_id = $this->factory->user->create();
		$target_id    = $this->factory->user->create( array( 'role' => 'subscriber' ) );

		wp_set_current_user( $moderator_id );

		$this->assertTrue( bbp_user_email_change_requires_confirmation( $moderator_id ) );
		$this->assertFalse( bbp_user_email_change_requires_confirmation( $target_id ) );
	}

	public function test_super_moderator_capability_is_remappable() {
		$moderator_id = $this->factory->user->create();
		$target_id    = $this->factory->user->create( array( 'role' => 'subscriber' ) );

		bbp_set_user_role( $moderator_id, bbp_get_moderator_role() );
		update_option( '_bbp_allow_super_mods', 1 );
		wp_set_current_user( $moderator_id );
		$this->set_profile_editor( $target_id );

		add_filter( 'bbp_map_primary_meta_caps', array( $this, 'remap_edit_user_capability' ), 10, 2 );

		$this->assertFalse( current_user_can( 'edit_user', $target_id ) );

		get_userdata( $moderator_id )->add_cap( 'manage_forum_users' );
		wp_set_current_user( 0 );
		wp_set_current_user( $moderator_id );

		$this->assertTrue( current_user_can( 'edit_user', $target_id ) );
	}

	/**
	 * @covers ::bbp_profile_update_role
	 */
	public function test_forum_role_handler_rejects_and_filters_staff_roles() {
		$moderator_id = $this->factory->user->create();
		$target_id    = $this->factory->user->create( array( 'role' => 'subscriber' ) );

		bbp_set_user_role( $moderator_id, bbp_get_moderator_role() );
		bbp_set_user_role( $target_id, bbp_get_participant_role() );
		update_option( '_bbp_allow_super_mods', 1 );
		wp_set_current_user( $moderator_id );
		$this->set_profile_editor( $target_id );
		$this->set_profile_request( $target_id, bbp_get_moderator_role() );

		bbp_profile_update_role( $target_id );

		$this->assertSame( bbp_get_participant_role(), bbp_get_user_role( $target_id ) );

		add_filter( 'bbp_get_user_editable_forum_roles', array( $this, 'allow_all_forum_roles' ), 10, 1 );
		bbp_profile_update_role( $target_id );

		$this->assertSame( bbp_get_moderator_role(), bbp_get_user_role( $target_id ) );
	}

	/**
	 * @ticket BBP3262
	 * @covers ::bbp_profile_update_role
	 */
	public function test_forum_role_handler_can_clear_role_without_changing_site_role() {
		$admin_id  = $this->factory->user->create( array( 'role' => 'administrator' ) );
		$target_id = $this->factory->user->create( array( 'role' => 'subscriber' ) );

		bbp_set_user_role( $target_id, bbp_get_participant_role() );
		wp_set_current_user( $admin_id );
		$this->set_profile_editor( $target_id );
		$this->set_profile_request( $target_id, '' );

		bbp_profile_update_role( $target_id );

		$this->assertFalse( bbp_get_user_role( $target_id ) );
		$this->assertSame( 'subscriber', bbp_get_user_blog_role( $target_id ) );
	}

	/**
	 * @ticket BBP3262
	 * @covers ::bbp_profile_update_role
	 */
	public function test_forum_role_handler_cannot_clear_a_role_hidden_from_the_editor() {
		$admin_id  = $this->factory->user->create( array( 'role' => 'administrator' ) );
		$target_id = $this->factory->user->create( array( 'role' => 'subscriber' ) );
		$hide_role = function ( $roles ) {
			unset( $roles[ bbp_get_moderator_role() ] );
			return $roles;
		};

		bbp_set_user_role( $target_id, bbp_get_moderator_role() );
		wp_set_current_user( $admin_id );
		$this->set_profile_editor( $target_id );
		$this->set_profile_request( $target_id, '' );
		add_filter( 'bbp_get_user_editable_forum_roles', $hide_role );

		try {
			bbp_profile_update_role( $target_id );
			$this->assertSame( bbp_get_moderator_role(), bbp_get_user_role( $target_id ) );
		} finally {
			remove_filter( 'bbp_get_user_editable_forum_roles', $hide_role );
		}

		bbp_profile_update_role( $target_id );
		$this->assertFalse( bbp_get_user_role( $target_id ) );
	}

	/**
	 * @ticket BBP3262
	 * @covers ::bbp_edit_user_forums_role
	 */
	public function test_forum_role_selector_keeps_an_unassignable_current_role_selected() {
		$admin_id  = $this->factory->user->create( array( 'role' => 'administrator' ) );
		$target_id = $this->factory->user->create( array( 'role' => 'subscriber' ) );
		$hide_role = function ( $roles ) {
			unset( $roles[ bbp_get_moderator_role() ] );
			return $roles;
		};

		bbp_set_user_role( $target_id, bbp_get_moderator_role() );
		wp_set_current_user( $admin_id );
		$this->set_profile_editor( $target_id );
		add_filter( 'bbp_get_user_editable_forum_roles', $hide_role );

		try {
			ob_start();
			bbp_edit_user_forums_role();
			$html = ob_get_clean();
			$this->assertStringContainsString( 'value="' . bbp_get_moderator_role() . '" selected="selected"', $html );
		} finally {
			remove_filter( 'bbp_get_user_editable_forum_roles', $hide_role );
		}
	}

	public function allow_all_forum_roles() {
		return bbp_get_dynamic_roles();
	}

	public function deny_profile_field( $retval, $field ) {
		return ( 'profile' === $field )
			? false
			: $retval;
	}

	public function add_test_contact_method( $contact_methods ) {
		$contact_methods['test_contact'] = 'Test Contact';

		return $contact_methods;
	}

	public function remap_edit_user_capability( $caps, $cap ) {
		if ( 'edit_user' === $cap ) {
			$caps = array( 'manage_forum_users' );
		}

		return $caps;
	}

	private function set_profile_editor( $user_id ) {
		$this->set_profile_view( $user_id );

		$wp_query                          = bbp_get_wp_query();
		$wp_query->bbp_is_single_user_edit = true;
	}

	private function set_profile_view( $user_id ) {
		$wp_query                          = bbp_get_wp_query();
		$wp_query->bbp_is_single_user      = true;
		bbpress()->displayed_user          = get_userdata( $user_id );
	}

	private function set_profile_request( $user_id, $forum_role ) {
		$_SERVER['REQUEST_METHOD'] = 'POST';
		$_POST = $_REQUEST = array(
			'_wpnonce'        => wp_create_nonce( 'update-user_' . $user_id ),
			'bbp-forums-role' => $forum_role,
		);
	}
}
