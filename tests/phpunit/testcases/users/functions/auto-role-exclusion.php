<?php

/**
 * Tests for the automatic forum role assignment exclusion.
 *
 * @group users
 * @group capabilities
 * @ticket BBP3762
 */
class BBP_Tests_Users_Functions_Auto_Role_Exclusion extends BBP_UnitTestCase {

	private $old_post;
	private $old_request;
	private $old_request_method;
	private $old_displayed_user;

	public function setUp(): void {
		parent::setUp();
		$this->old_post           = $_POST;
		$this->old_request        = $_REQUEST;
		$this->old_request_method = isset( $_SERVER['REQUEST_METHOD'] ) ? $_SERVER['REQUEST_METHOD'] : null;
		$this->old_displayed_user = bbpress()->displayed_user;
	}

	public function tearDown(): void {
		$_POST    = $this->old_post;
		$_REQUEST = $this->old_request;

		if ( null === $this->old_request_method ) {
			unset( $_SERVER['REQUEST_METHOD'] );
		} else {
			$_SERVER['REQUEST_METHOD'] = $this->old_request_method;
		}
		bbpress()->displayed_user = $this->old_displayed_user;
		set_current_screen( 'front' );

		parent::tearDown();
	}

	private function revisit_as( $user_id ) {
		wp_set_current_user( 0 );
		wp_set_current_user( $user_id );
	}

	private function set_profile_request( $user_id, $checked = true, $scope = 'site' ) {
		$_SERVER['REQUEST_METHOD'] = 'POST';
		$_POST = $_REQUEST = array(
			'_wpnonce'                      => wp_create_nonce( 'update-user_' . $user_id ),
			'bbp-auto-role-exclusion-present' => '1',
			'bbp-auto-role-exclusion-scope'   => $scope,
		);

		if ( $checked ) {
			$_POST['bbp-auto-role-exclusion'] = $_REQUEST['bbp-auto-role-exclusion'] = '1';
		}
	}

	/**
	 * @covers ::bbp_get_user_auto_role_exclusion_scope
	 * @covers ::bbp_is_user_auto_role_excluded
	 * @covers ::bbp_set_current_user_default_role
	 */
	public function test_exclusion_prevents_stored_role_and_restores_mapping_when_cleared() {
		$user_id = $this->factory->user->create( array( 'role' => 'subscriber' ) );
		update_option( '_bbp_allow_global_access', 1 );
		bbp_remove_user_role( $user_id );

		update_user_option( $user_id, '_bbp_auto_role_exclusion', '1', false );
		$this->revisit_as( $user_id );
		$this->assertTrue( bbp_is_user_auto_role_excluded( $user_id ) );
		$this->assertFalse( bbp_get_user_role( $user_id ) );
		$this->assertFalse( current_user_can( 'spectate' ) );
		$this->assertFalse( current_user_can( 'participate' ) );

		delete_user_option( $user_id, '_bbp_auto_role_exclusion', false );
		$this->revisit_as( $user_id );
		$this->assertFalse( bbp_is_user_auto_role_excluded( $user_id ) );
		$this->assertSame( bbp_get_participant_role(), bbp_get_user_role( $user_id ) );
		$this->assertTrue( current_user_can( 'spectate' ) );
		$this->assertTrue( current_user_can( 'participate' ) );
	}

	/**
	 * @covers ::bbp_get_user_auto_role_exclusion_scope
	 * @covers ::bbp_is_user_auto_role_excluded
	 */
	public function test_exclusion_getters_use_user_option_and_bbp_filters() {
		$user_id = $this->factory->user->create( array( 'role' => 'subscriber' ) );
		$option_filter = function ( $value, $option, $user ) use ( $user_id ) {
			return ( ( $user_id === $user->ID ) && ( '_bbp_auto_role_exclusion' === $option ) )
				? '1'
				: $value;
		};
		$all_sites_option_filter = function ( $value, $option, $user ) use ( $user_id ) {
			return ( ( $user_id === $user->ID ) && ( '_bbp_auto_role_exclusion_all_sites' === $option ) )
				? '1'
				: $value;
		};
		$scope_filter = function ( $scope, $filtered_user_id ) use ( $user_id ) {
			return ( $user_id === $filtered_user_id ) ? false : $scope;
		};
		$excluded_filter = function ( $excluded, $filtered_user_id ) use ( $user_id ) {
			return ( $user_id === $filtered_user_id ) ? true : $excluded;
		};

		$this->assertFalse( bbp_get_user_auto_role_exclusion_scope( $user_id ) );
		$this->assertFalse( bbp_is_user_auto_role_excluded( $user_id ) );

		add_filter( 'get_user_option__bbp_auto_role_exclusion', $option_filter, 10, 3 );

		try {
			$this->assertSame( 'site', bbp_get_user_auto_role_exclusion_scope( $user_id ) );
			$this->assertTrue( bbp_is_user_auto_role_excluded( $user_id ) );

			if ( is_multisite() ) {
				add_filter( 'get_user_option__bbp_auto_role_exclusion_all_sites', $all_sites_option_filter, 10, 3 );
				$this->assertSame( 'all_sites', bbp_get_user_auto_role_exclusion_scope( $user_id ) );
				$this->assertTrue( bbp_is_user_auto_role_excluded( $user_id ) );
				remove_filter( 'get_user_option__bbp_auto_role_exclusion_all_sites', $all_sites_option_filter, 10 );
			}

			add_filter( 'bbp_get_user_auto_role_exclusion_scope', $scope_filter, 10, 2 );
			$this->assertFalse( bbp_get_user_auto_role_exclusion_scope( $user_id ) );
			$this->assertFalse( bbp_is_user_auto_role_excluded( $user_id ) );

			add_filter( 'bbp_is_user_auto_role_excluded', $excluded_filter, 10, 2 );
			$this->assertTrue( bbp_is_user_auto_role_excluded( $user_id ) );
		} finally {
			remove_filter( 'get_user_option__bbp_auto_role_exclusion', $option_filter, 10 );
			remove_filter( 'get_user_option__bbp_auto_role_exclusion_all_sites', $all_sites_option_filter, 10 );
			remove_filter( 'bbp_get_user_auto_role_exclusion_scope', $scope_filter, 10 );
			remove_filter( 'bbp_is_user_auto_role_excluded', $excluded_filter, 10 );
		}
	}

	/**
	 * @covers ::bbp_set_current_user_default_role
	 */
	public function test_exclusion_prevents_request_only_role_capabilities_for_map_and_fallback() {
		$user_id = $this->factory->user->create( array( 'role' => 'subscriber' ) );
		$map     = function () {
			return array( 'subscriber' => bbp_get_spectator_role() );
		};
		update_option( '_bbp_allow_global_access', 0 );
		bbp_remove_user_role( $user_id );
		add_filter( 'bbp_get_user_role_map', $map );

		try {
			$this->revisit_as( $user_id );
			$this->assertFalse( bbp_get_user_role( $user_id ) );
			$this->assertTrue( current_user_can( 'spectate' ) );
			$this->assertFalse( current_user_can( 'participate' ) );

			update_user_option( $user_id, '_bbp_auto_role_exclusion', '1', false );
			$this->revisit_as( $user_id );
			$this->assertFalse( bbp_get_user_role( $user_id ) );
			$this->assertFalse( current_user_can( 'spectate' ) );
			$this->assertFalse( current_user_can( 'participate' ) );

			delete_user_option( $user_id, '_bbp_auto_role_exclusion', false );
			$this->revisit_as( $user_id );
			$this->assertTrue( current_user_can( 'spectate' ) );
			$this->assertFalse( current_user_can( 'participate' ) );
		} finally {
			remove_filter( 'bbp_get_user_role_map', $map );
		}

		// A role absent from the map falls back to the site's default role.
		$empty_map = function () {
			return array();
		};
		add_filter( 'bbp_get_user_role_map', $empty_map );

		try {
			$this->revisit_as( $user_id );
			$this->assertTrue( current_user_can( 'spectate' ) );
			$this->assertTrue( current_user_can( 'participate' ) );

			update_user_option( $user_id, '_bbp_auto_role_exclusion', '1', false );
			$this->revisit_as( $user_id );
			$this->assertFalse( current_user_can( 'spectate' ) );
			$this->assertFalse( current_user_can( 'participate' ) );
		} finally {
			remove_filter( 'bbp_get_user_role_map', $empty_map );
		}
	}

	/**
	 * @covers ::bbp_set_current_user_default_role
	 */
	public function test_explicit_role_and_blocked_role_remain_effective() {
		$user_id = $this->factory->user->create( array( 'role' => 'subscriber' ) );
		update_option( '_bbp_allow_global_access', 1 );
		update_user_option( $user_id, '_bbp_auto_role_exclusion', '1', false );

		bbp_set_user_role( $user_id, bbp_get_participant_role() );
		$this->revisit_as( $user_id );
		$this->assertSame( bbp_get_participant_role(), bbp_get_user_role( $user_id ) );
		$this->assertTrue( current_user_can( 'participate' ) );

		bbp_set_user_role( $user_id, bbp_get_blocked_role() );
		$this->revisit_as( $user_id );
		$this->assertSame( bbp_get_blocked_role(), bbp_get_user_role( $user_id ) );
		$this->assertFalse( current_user_can( 'spectate' ) );
		$this->assertFalse( current_user_can( 'participate' ) );

		bbp_remove_user_role( $user_id );
		$this->revisit_as( $user_id );
		$this->assertFalse( bbp_get_user_role( $user_id ) );
		$this->assertFalse( current_user_can( 'spectate' ) );

		update_user_meta( $user_id, '_bbp_default_role', bbp_get_spectator_role() );
		$this->revisit_as( $user_id );
		$this->assertFalse( bbp_get_user_role( $user_id ) );
		$this->assertFalse( current_user_can( 'spectate' ) );

		update_user_meta( $user_id, '_bbp_default_role', bbp_get_blocked_role() );
		$this->revisit_as( $user_id );
		$this->assertSame( bbp_get_blocked_role(), bbp_get_user_role( $user_id ) );
		$this->assertFalse( current_user_can( 'spectate' ) );
		$this->assertFalse( current_user_can( 'participate' ) );
	}

	/**
	 * @covers ::bbp_profile_update_auto_role_exclusion
	 */
	public function test_only_an_administrator_with_a_valid_profile_request_can_change_exclusion() {
		$admin_id  = $this->factory->user->create( array( 'role' => 'administrator' ) );
		$editor_id = $this->factory->user->create( array( 'role' => 'editor' ) );
		$target_id = $this->factory->user->create( array( 'role' => 'subscriber' ) );

		bbp_set_user_role( $target_id, bbp_get_moderator_role() );
		$this->revisit_as( $admin_id );
		set_current_screen( 'user-edit.php' );
		$this->set_profile_request( $target_id );
		bbp_profile_update_auto_role_exclusion( $target_id );
		$this->assertTrue( bbp_is_user_auto_role_excluded( $target_id ) );
		$this->assertSame( bbp_get_moderator_role(), bbp_get_user_role( $target_id ) );

		$this->revisit_as( $editor_id );
		$this->set_profile_request( $target_id, false );
		bbp_profile_update_auto_role_exclusion( $target_id );
		$this->assertTrue( bbp_is_user_auto_role_excluded( $target_id ) );

		$this->revisit_as( $admin_id );
		$this->set_profile_request( $target_id, false );
		$_REQUEST['_wpnonce'] = $_POST['_wpnonce'] = 'invalid';
		bbp_profile_update_auto_role_exclusion( $target_id );
		$this->assertTrue( bbp_is_user_auto_role_excluded( $target_id ) );

		$this->set_profile_request( $target_id, false );
		unset( $_POST['bbp-auto-role-exclusion-present'] );
		bbp_profile_update_auto_role_exclusion( $target_id );
		$this->assertTrue( bbp_is_user_auto_role_excluded( $target_id ) );

		$this->set_profile_request( $target_id, false );
		bbp_profile_update_auto_role_exclusion( $target_id );
		$this->assertFalse( bbp_is_user_auto_role_excluded( $target_id ) );
		$this->assertSame( bbp_get_moderator_role(), bbp_get_user_role( $target_id ) );
	}

	/**
	 * @covers ::bbp_profile_update_auto_role_exclusion
	 */
	public function test_wordpress_profile_update_saves_the_exclusion() {
		$admin_id  = $this->factory->user->create( array( 'role' => 'administrator' ) );
		$target_id = $this->factory->user->create( array( 'role' => 'subscriber' ) );
		$role      = bbp_get_user_role( $target_id );

		$this->revisit_as( $admin_id );
		set_current_screen( 'user-edit.php' );
		$this->set_profile_request( $target_id );
		$this->assertSame( $target_id, wp_update_user( array( 'ID' => $target_id ) ) );
		$this->assertTrue( bbp_is_user_auto_role_excluded( $target_id ) );
		$this->assertSame( $role, bbp_get_user_role( $target_id ) );

		$this->set_profile_request( $target_id, false );
		$this->assertSame( $target_id, wp_update_user( array( 'ID' => $target_id ) ) );
		$this->assertFalse( bbp_is_user_auto_role_excluded( $target_id ) );
		$this->assertSame( $role, bbp_get_user_role( $target_id ) );
	}

	/**
	 * @covers ::bbp_edit_user_handler
	 * @covers ::bbp_profile_update_auto_role_exclusion
	 */
	public function test_front_end_administrator_can_save_the_exclusion() {
		$admin_id     = $this->factory->user->create( array( 'role' => 'administrator' ) );
		$target_id    = $this->factory->user->create( array( 'role' => 'subscriber' ) );
		$target       = get_userdata( $target_id );
		$wp_query     = bbp_get_wp_query();
		$old_errors    = bbpress()->errors;
		$redirect      = '';
		$stop_redirect = function ( $location ) use ( &$redirect ) {
			$redirect = $location;
			throw new RuntimeException( 'Profile redirect.' );
		};

		if ( is_multisite() ) {
			grant_super_admin( $admin_id );
		}

		$was_single_user      = $wp_query->bbp_is_single_user;
		$was_single_user_edit = $wp_query->bbp_is_single_user_edit;
		$was_single_user_home = $wp_query->bbp_is_single_user_home;

		$this->revisit_as( $admin_id );
		$wp_query->bbp_is_single_user      = true;
		$wp_query->bbp_is_single_user_edit = true;
		$wp_query->bbp_is_single_user_home = false;
		bbpress()->displayed_user          = $target;
		bbpress()->errors                  = new WP_Error();
		set_current_screen( 'front' );
		add_filter( 'wp_redirect', $stop_redirect );

		try {
			foreach ( array( true, false ) as $checked ) {
				$this->set_profile_request( $target_id, $checked );
				$_POST['email']        = $target->user_email;
				$_POST['nickname']     = $target->nickname;
				$_POST['display_name'] = $target->display_name;
				$_REQUEST              = $_POST;
				$redirect              = '';

				try {
					bbp_edit_user_handler( 'bbp-update-user' );
					$this->fail(
						sprintf(
							'A successful front-end profile update should redirect. Errors: %s',
							implode( ', ', bbpress()->errors->get_error_codes() )
						)
					);
				} catch ( RuntimeException $exception ) {
					if ( 'Profile redirect.' !== $exception->getMessage() ) {
						throw $exception;
					}
				}

				$this->assertSame( $checked, bbp_is_user_auto_role_excluded( $target_id ) );
				$this->assertStringContainsString( 'updated=true', $redirect );
			}
		} finally {
			remove_filter( 'wp_redirect', $stop_redirect );
			$wp_query->bbp_is_single_user      = $was_single_user;
			$wp_query->bbp_is_single_user_edit = $was_single_user_edit;
			$wp_query->bbp_is_single_user_home = $was_single_user_home;
			bbpress()->errors                  = $old_errors;
			if ( is_multisite() ) {
				revoke_super_admin( $admin_id );
			}
		}
	}

	/**
	 * @covers ::bbp_edit_user_auto_role_exclusion
	 * @covers BBP_Users_Admin::secondary_role_display
	 */
	public function test_profile_controls_explain_and_show_the_stored_exclusion() {
		require_once BBP_PLUGIN_DIR . 'includes/admin/users.php';

		$admin_id  = $this->factory->user->create( array( 'role' => 'administrator' ) );
		$target_id = $this->factory->user->create( array( 'role' => 'subscriber' ) );
		if ( is_multisite() ) {
			grant_super_admin( $admin_id );
		}
		update_user_option( $target_id, '_bbp_auto_role_exclusion', '1', false );
		$this->revisit_as( $admin_id );

		ob_start();
		BBP_Users_Admin::secondary_role_display( get_userdata( $target_id ) );
		$admin_html = ob_get_clean();
		$this->assertStringContainsString( 'No role removes the assigned role but does not prevent automatic assignment.', $admin_html );
		$this->assertStringContainsString( 'Automatic Role', $admin_html );
		$this->assertStringContainsString( 'Skip automatic role assignment for this user', $admin_html );
		$this->assertStringContainsString( 'Existing and assigned forum roles are unaffected.', $admin_html );
		$this->assertStringContainsString( 'name="bbp-auto-role-exclusion-present"', $admin_html );
		$this->assertStringContainsString( 'id="bbp-auto-role-exclusion" value="1"', $admin_html );
		$this->assertStringContainsString( "checked='checked'", $admin_html );

		if ( is_multisite() ) {
			$this->assertStringContainsString( 'Skip automatic role assignment for this user on:', $admin_html );
			$this->assertStringContainsString( 'name="bbp-auto-role-exclusion-scope"', $admin_html );
			$this->assertStringContainsString( 'This site', $admin_html );
			$this->assertStringContainsString( 'All sites', $admin_html );
		} else {
			$this->assertStringNotContainsString( 'name="bbp-auto-role-exclusion-scope"', $admin_html );
		}

		$wp_query                             = bbp_get_wp_query();
		$was_single_user                      = $wp_query->bbp_is_single_user;
		$was_single_user_edit                 = $wp_query->bbp_is_single_user_edit;
		$was_single_user_home                 = $wp_query->bbp_is_single_user_home;
		$wp_query->bbp_is_single_user         = true;
		$wp_query->bbp_is_single_user_edit    = true;
		$wp_query->bbp_is_single_user_home    = false;
		bbpress()->displayed_user             = get_userdata( $target_id );
		set_current_screen( 'front' );

		try {
			ob_start();
			bbp_edit_user_auto_role_exclusion();
			$front_html = ob_get_clean();
			$this->assertStringContainsString( 'name="bbp-auto-role-exclusion-present"', $front_html );
			$this->assertStringContainsString( 'id="bbp-auto-role-exclusion" value="1"', $front_html );
			$this->assertStringContainsString( "checked='checked'", $front_html );
		} finally {
			$wp_query->bbp_is_single_user      = $was_single_user;
			$wp_query->bbp_is_single_user_edit = $was_single_user_edit;
			$wp_query->bbp_is_single_user_home = $was_single_user_home;
			if ( is_multisite() ) {
				revoke_super_admin( $admin_id );
			}
		}
	}

	/**
	 * @covers ::bbp_get_user_auto_role_exclusion_scope
	 * @covers ::bbp_is_user_auto_role_excluded
	 * @covers ::bbp_set_current_user_default_role
	 * @group multisite
	 */
	public function test_exclusion_is_site_specific_and_installation_wide_block_still_applies() {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'Requires multisite.' );
		}

		$user_id  = $this->factory->user->create( array( 'role' => 'subscriber' ) );
		$site_id  = get_current_blog_id();
		$other_id = $this->factory->blog->create();

		bbp_remove_user_role( $user_id );
		update_user_option( $user_id, '_bbp_auto_role_exclusion', '1', false );
		$this->assertTrue( bbp_is_user_auto_role_excluded( $user_id ) );

		switch_to_blog( $other_id );
		try {
			$this->assertFalse( bbp_is_user_auto_role_excluded( $user_id ) );
			update_option( '_bbp_allow_global_access', 1 );
			$this->revisit_as( $user_id );
			$this->assertSame( bbp_get_participant_role(), bbp_get_user_role( $user_id ) );
		} finally {
			restore_current_blog();
		}

		$this->assertSame( $site_id, get_current_blog_id() );
		$this->assertTrue( bbp_is_user_auto_role_excluded( $user_id ) );
		$this->revisit_as( $user_id );
		$this->assertFalse( bbp_get_user_role( $user_id ) );

		update_user_meta( $user_id, '_bbp_default_role', bbp_get_blocked_role() );
		$this->revisit_as( $user_id );
		$this->assertSame( bbp_get_blocked_role(), bbp_get_user_role( $user_id ) );
		$this->assertFalse( current_user_can( 'spectate' ) );
	}

	/**
	 * @covers ::bbp_get_user_auto_role_exclusion_scope
	 * @covers ::bbp_profile_update_auto_role_exclusion
	 * @group multisite
	 */
	public function test_all_sites_exclusion_overrides_and_preserves_site_exclusions() {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'Requires multisite.' );
		}

		$admin_id = $this->factory->user->create( array( 'role' => 'administrator' ) );
		$user_id  = $this->factory->user->create( array( 'role' => 'subscriber' ) );
		$site_id  = get_current_blog_id();
		$other_id = $this->factory->blog->create();
		$third_id = $this->factory->blog->create();
		$other_network_id = $this->factory->network->create(
			array(
				'domain' => 'other.example.org',
				'path'   => '/',
			)
		);
		$other_network_site_id = $this->factory->blog->create(
			array(
				'domain'     => 'other.example.org',
				'path'       => '/',
				'network_id' => $other_network_id,
			)
		);

		update_user_option( $user_id, '_bbp_auto_role_exclusion', '1', false );

		switch_to_blog( $other_id );
		update_user_option( $user_id, '_bbp_auto_role_exclusion', '1', false );
		restore_current_blog();

		grant_super_admin( $admin_id );
		$this->revisit_as( $admin_id );
		set_current_screen( 'user-edit.php' );

		try {
			$this->set_profile_request( $user_id, true, 'all_sites' );
			bbp_profile_update_auto_role_exclusion( $user_id );
			$this->assertSame( 'all_sites', bbp_get_user_auto_role_exclusion_scope( $user_id ) );

			switch_to_blog( $other_id );
			$this->assertSame( 'all_sites', bbp_get_user_auto_role_exclusion_scope( $user_id ) );
			restore_current_blog();

			switch_to_blog( $other_network_site_id );
			$this->assertSame( 'all_sites', bbp_get_user_auto_role_exclusion_scope( $user_id ) );
			restore_current_blog();

			switch_to_blog( $third_id );
			try {
				$this->assertSame( 'all_sites', bbp_get_user_auto_role_exclusion_scope( $user_id ) );
				update_option( '_bbp_allow_global_access', 1 );
				bbp_remove_user_role( $user_id );
				$this->revisit_as( $user_id );
				$this->assertFalse( bbp_get_user_role( $user_id ) );
				$this->assertFalse( current_user_can( 'participate' ) );
			} finally {
				restore_current_blog();
			}

			$this->revisit_as( $admin_id );

			$this->set_profile_request( $user_id, true, 'site' );
			bbp_profile_update_auto_role_exclusion( $user_id );
			$this->assertSame( 'site', bbp_get_user_auto_role_exclusion_scope( $user_id ) );
			$this->assertFalse( get_user_option( '_bbp_auto_role_exclusion_all_sites', $user_id ) );

			$this->set_profile_request( $user_id, true, 'all_sites' );
			bbp_profile_update_auto_role_exclusion( $user_id );
			// Unchecking clears the effective exclusion regardless of the selected scope.
			$this->set_profile_request( $user_id, false, 'site' );
			bbp_profile_update_auto_role_exclusion( $user_id );
			$this->assertFalse( bbp_get_user_auto_role_exclusion_scope( $user_id ) );

			switch_to_blog( $other_id );
			$this->assertSame( 'site', bbp_get_user_auto_role_exclusion_scope( $user_id ) );
			restore_current_blog();

			switch_to_blog( $third_id );
			$this->assertFalse( bbp_get_user_auto_role_exclusion_scope( $user_id ) );
			restore_current_blog();

			switch_to_blog( $other_network_site_id );
			$this->assertFalse( bbp_get_user_auto_role_exclusion_scope( $user_id ) );
			restore_current_blog();
		} finally {
			while ( get_current_blog_id() !== $site_id ) {
				restore_current_blog();
			}
			revoke_super_admin( $admin_id );
		}
	}

	/**
	 * @covers ::bbp_profile_update_auto_role_exclusion
	 * @group multisite
	 */
	public function test_site_administrator_cannot_change_all_sites_exclusion() {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'Requires multisite.' );
		}

		$admin_id = $this->factory->user->create( array( 'role' => 'administrator' ) );
		$user_id  = $this->factory->user->create( array( 'role' => 'subscriber' ) );
		update_user_option( $user_id, '_bbp_auto_role_exclusion_all_sites', '1', true );

		$this->revisit_as( $admin_id );
		set_current_screen( 'user-edit.php' );
		$this->assertTrue( bbp_current_user_can_edit_user_field( 'forum_role', $user_id ) );
		$this->set_profile_request( $user_id, false, 'site' );
		bbp_profile_update_auto_role_exclusion( $user_id );

		$this->assertSame( 'all_sites', bbp_get_user_auto_role_exclusion_scope( $user_id ) );
		$this->assertSame( '1', get_user_option( '_bbp_auto_role_exclusion_all_sites', $user_id ) );

		ob_start();
		bbp_user_auto_role_exclusion_form( $user_id );
		$admin_html = ob_get_clean();
		$this->assertStringContainsString( 'Skip automatic role assignment for this user', $admin_html );
		$this->assertStringContainsString( 'Set for all sites by a network administrator.', $admin_html );
		$this->assertStringContainsString( "disabled='disabled'", $admin_html );
		$this->assertStringNotContainsString( 'name="bbp-auto-role-exclusion-present"', $admin_html );
		$this->assertStringNotContainsString( 'name="bbp-auto-role-exclusion-scope"', $admin_html );
	}
}
