<?php

/**
 * Tests for user roles, account state, and object moderators.
 *
 * @group users
 * @group capabilities
 */
class BBP_Tests_Users_Functions_Capabilities extends BBP_UnitTestCase {

	/**
	 * @covers ::bbp_is_valid_role
	 */
	public function test_only_registered_forum_roles_are_valid() {
		$this->assertTrue( bbp_is_valid_role( bbp_get_participant_role() ) );
		$this->assertTrue( bbp_is_valid_role( bbp_get_moderator_role() ) );
		$this->assertFalse( bbp_is_valid_role( 'subscriber' ) );
		$this->assertFalse( bbp_is_valid_role( array( bbp_get_participant_role() ) ) );
		$this->assertFalse( bbp_is_valid_role() );
	}

	/**
	 * @covers ::bbp_set_user_role
	 * @covers ::bbp_get_user_role
	 * @covers ::bbp_get_user_blog_role
	 */
	public function test_setting_forum_role_preserves_wordpress_role() {
		$user_id = $this->factory->user->create( array( 'role' => 'subscriber' ) );
		$role    = bbp_get_user_role( $user_id );
		if ( $role ) {
			get_userdata( $user_id )->remove_role( $role );
		}
		$this->assertFalse( bbp_get_user_role( $user_id ) );
		$this->assertSame( 'subscriber', bbp_get_user_blog_role( $user_id ) );

		$this->assertSame( bbp_get_participant_role(), bbp_set_user_role( $user_id, bbp_get_participant_role() ) );
		$this->assertSame( bbp_get_participant_role(), bbp_get_user_role( $user_id ) );
		$this->assertSame( 'subscriber', bbp_get_user_blog_role( $user_id ) );
		$this->assertFalse( bbp_set_user_role( $user_id, bbp_get_participant_role() ) );

		$this->assertSame( bbp_get_moderator_role(), bbp_set_user_role( $user_id, bbp_get_moderator_role() ) );
		$this->assertSame( bbp_get_moderator_role(), bbp_get_user_role( $user_id ) );
		$this->assertFalse( in_array( bbp_get_participant_role(), get_userdata( $user_id )->roles, true ) );
		$this->assertSame( 'subscriber', bbp_get_user_blog_role( $user_id ) );
	}

	/**
	 * @covers ::bbp_set_user_role
	 */
	public function test_setting_invalid_role_does_not_change_user_roles() {
		$user_id = $this->factory->user->create( array( 'role' => 'subscriber' ) );
		bbp_set_user_role( $user_id, bbp_get_participant_role() );
		$roles = get_userdata( $user_id )->roles;

		bbp_set_user_role( $user_id, 'not-a-forum-role' );
		$this->assertSame( $roles, get_userdata( $user_id )->roles );
		$this->assertFalse( bbp_set_user_role( PHP_INT_MAX, bbp_get_moderator_role() ) );
	}

	/**
	 * @ticket BBP3262
	 * @covers ::bbp_update_user_role
	 */
	public function test_update_user_role_validates_public_arguments() {
		$user_id = $this->factory->user->create( array( 'role' => 'subscriber' ) );
		$user    = get_userdata( $user_id );
		$role    = bbp_get_user_role( $user_id );

		$this->assertFalse( bbp_update_user_role( false, bbp_get_blocked_role() ) );
		$this->assertFalse( bbp_update_user_role( $user, '' ) );
		$this->assertFalse( bbp_update_user_role( $user, 'not-a-forum-role' ) );
		$this->assertFalse( bbp_update_user_role( $user, array( bbp_get_blocked_role() ) ) );
		$this->assertSame( $role, bbp_get_user_role( $user_id ) );

		$this->assertTrue( bbp_update_user_role( $user, bbp_get_blocked_role() ) );
		$this->assertSame( bbp_get_blocked_role(), bbp_get_user_role( $user_id ) );
		$this->assertTrue( bbp_update_user_role( $user, false ) );
		$this->assertFalse( bbp_get_user_role( $user_id ) );
	}

	/**
	 * @ticket BBP3262
	 * @covers ::bbp_set_user_role
	 * @covers ::bbp_remove_user_role
	 */
	public function test_role_transition_action_includes_no_role_and_ignores_noops() {
		$user_id = $this->factory->user->create( array( 'role' => 'subscriber' ) );
		$role    = bbp_get_user_role( $user_id );
		if ( $role ) {
			get_userdata( $user_id )->remove_role( $role );
		}

		$transitions = array();
		$on_transition = function ( $new_role, $old_role, $user ) use ( $user_id, &$transitions ) {
			$this->assertSame( $user_id, $user->ID );
			$this->assertSame( $new_role, bbp_get_user_role( $user_id ) );
			$transitions[] = array( $old_role, $new_role );
		};

		add_action( 'bbp_transition_user_role', $on_transition, 10, 3 );

		try {
			$this->assertFalse( bbp_get_user_role( $user_id ) );
			$this->assertFalse( bbp_set_user_role( $user_id, '' ) );
			$this->assertFalse( bbp_remove_user_role( $user_id ) );
			bbp_set_user_role( $user_id );
			bbp_set_user_role( $user_id, bbp_get_participant_role() );
			$this->assertFalse( bbp_set_user_role( $user_id, '' ) );
			$this->assertSame( bbp_get_participant_role(), bbp_get_user_role( $user_id ) );
			bbp_set_user_role( $user_id, 'not-a-forum-role' );
			bbp_set_user_role( $user_id, bbp_get_participant_role() );
			bbp_set_user_role( $user_id, bbp_get_blocked_role() );
			bbp_set_user_role( $user_id, bbp_get_blocked_role() );
			$this->assertTrue( bbp_remove_user_role( $user_id ) );
			$this->assertFalse( bbp_get_user_role( $user_id ) );
			$this->assertSame( 'subscriber', bbp_get_user_blog_role( $user_id ) );
			$this->assertFalse( bbp_remove_user_role( $user_id ) );
		} finally {
			remove_action( 'bbp_transition_user_role', $on_transition );
		}

		$this->assertSame(
			array(
				array( false, bbp_get_participant_role() ),
				array( bbp_get_participant_role(), bbp_get_blocked_role() ),
				array( bbp_get_blocked_role(), false ),
			),
			$transitions
		);
	}

	/**
	 * @ticket BBP3262
	 * @covers ::bbp_remove_user_role
	 */
	public function test_no_role_removes_every_forum_role() {
		$user_id = $this->factory->user->create( array( 'role' => 'subscriber' ) );
		$user    = get_userdata( $user_id );

		$user->add_role( bbp_get_participant_role() );
		$user->add_role( bbp_get_moderator_role() );
		$this->assertSame( bbp_get_participant_role(), bbp_get_user_role( $user_id ) );

		$this->assertTrue( bbp_remove_user_role( $user_id ) );
		$this->assertFalse( bbp_get_user_role( $user_id ) );
		$this->assertSame( 'subscriber', bbp_get_user_blog_role( $user_id ) );
		$this->assertSame( array(), array_intersect( get_userdata( $user_id )->roles, array_keys( bbp_get_dynamic_roles() ) ) );
	}

	/**
	 * @ticket BBP3262
	 * @covers ::bbp_set_user_role
	 */
	public function test_blocked_role_removes_other_forum_roles() {
		$user_id = $this->factory->user->create( array( 'role' => 'subscriber' ) );
		$user    = get_userdata( $user_id );

		$user->add_role( bbp_get_participant_role() );
		$user->add_role( bbp_get_moderator_role() );
		bbp_set_user_role( $user_id, bbp_get_blocked_role() );
		$this->assertSame( array( bbp_get_blocked_role() ), array_values( array_intersect( get_userdata( $user_id )->roles, array_keys( bbp_get_dynamic_roles() ) ) ) );

		$user->add_role( bbp_get_participant_role() );
		$this->assertFalse( bbp_set_user_role( $user_id, bbp_get_blocked_role() ) );
		$this->assertSame( array( bbp_get_blocked_role() ), array_values( array_intersect( get_userdata( $user_id )->roles, array_keys( bbp_get_dynamic_roles() ) ) ) );
	}

	/**
	 * @ticket BBP3262
	 * @covers ::bbp_get_user_block_actions
	 */
	public function test_user_block_action_registry_exposes_builtin_callbacks() {
		$actions = bbp_get_user_block_actions();

		$this->assertSame( 'bbp_destroy_user_sessions', $actions['destroy_sessions']['callback'] );
		$this->assertSame( is_multisite() ? 'manage_network_users' : 'manage_options', $actions['destroy_sessions']['capability'] );
		$this->assertSame( 'bbp_block_user_on_sites', $actions['block_sites']['callback'] );
		$this->assertSame( 'manage_network_users', $actions['block_sites']['capability'] );
		$this->assertTrue( $actions['block_sites']['multisite'] );
		$this->assertSame( 'bbp_mark_user_as_spam', $actions['mark_spam']['callback'] );
		$this->assertSame( 'manage_network_users', $actions['mark_spam']['capability'] );
		$this->assertTrue( $actions['mark_spam']['multisite'] );
	}

	/**
	 * @ticket BBP3262
	 * @covers ::bbp_is_user_block_action_available
	 * @covers ::bbp_run_user_block_actions
	 */
	public function test_plugins_can_replace_all_builtin_block_actions() {
		$user_id = $this->factory->user->create();
		$called  = array();
		$filter  = function () use ( &$called ) {
			$actions = array();

			foreach ( array( 'first', 'second', 'third', 'fourth' ) as $action_id ) {
				$actions[ $action_id ] = array(
					'name'     => ucfirst( $action_id ),
					'callback' => function () use ( &$called, $action_id ) {
						$called[] = $action_id;
					},
				);
			}

			return $actions;
		};

		update_option( '_bbp_user_block_actions', array( 'destroy_sessions', 'first', 'second', 'third', 'fourth' ) );
		add_filter( 'bbp_get_user_block_actions', $filter );

		try {
			$this->assertCount( 4, bbp_get_user_block_actions() );
			bbp_run_user_block_actions( bbp_get_blocked_role(), false, get_userdata( $user_id ) );
			$this->assertSame( array( 'first', 'second', 'third', 'fourth' ), $called );
		} finally {
			remove_filter( 'bbp_get_user_block_actions', $filter );
			delete_option( '_bbp_user_block_actions' );
		}
	}

	/**
	 * @ticket BBP3262
	 * @covers ::bbp_is_user_block_action_available
	 * @covers ::bbp_run_user_block_actions
	 * @group multisite
	 */
	public function test_network_block_action_requires_network_administrator() {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'This test requires multisite.' );
		}

		$old_user  = get_current_user_id();
		$admin_id  = $this->factory->user->create( array( 'role' => 'administrator' ) );
		$target_id = $this->factory->user->create();
		$sessions  = WP_Session_Tokens::get_instance( $target_id );

		$sessions->create( time() + HOUR_IN_SECONDS );
		update_option( '_bbp_user_block_actions', array( 'destroy_sessions' ) );
		wp_set_current_user( $admin_id );

		try {
			$this->assertTrue( current_user_can( 'manage_options' ) );
			$this->assertFalse( current_user_can( 'manage_network_users' ) );
			bbp_run_user_block_actions( bbp_get_blocked_role(), false, get_userdata( $target_id ) );
			$this->assertCount( 1, $sessions->get_all() );

			grant_super_admin( $admin_id );
			$this->assertTrue( current_user_can( 'manage_network_users' ) );
			bbp_run_user_block_actions( bbp_get_blocked_role(), false, get_userdata( $target_id ) );
			$this->assertSame( array(), $sessions->get_all() );
		} finally {
			revoke_super_admin( $admin_id );
			wp_set_current_user( $old_user );
			delete_option( '_bbp_user_block_actions' );
		}
	}

	/**
	 * @ticket BBP3262
	 * @covers ::bbp_destroy_user_sessions
	 */
	public function test_destroy_user_sessions_invalidates_every_token() {
		$user_id  = $this->factory->user->create();
		$sessions = WP_Session_Tokens::get_instance( $user_id );

		$sessions->create( time() + HOUR_IN_SECONDS );
		$sessions->create( time() + HOUR_IN_SECONDS );
		$this->assertCount( 2, $sessions->get_all() );

		$this->assertTrue( bbp_destroy_user_sessions( $user_id ) );
		$this->assertSame( array(), $sessions->get_all() );
		$this->assertFalse( bbp_destroy_user_sessions( PHP_INT_MAX ) );
	}

	/**
	 * @ticket BBP3262
	 * @covers ::bbp_block_user_on_sites
	 * @covers ::bbp_run_user_block_actions
	 * @covers ::bbp_set_user_role_on_sites
	 * @group multisite
	 */
	public function test_block_user_on_sites_propagates_role_without_rerunning_actions() {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'This test requires multisite.' );
		}

		$user_id = $this->factory->user->create( array( 'role' => 'subscriber' ) );
		$site_id = $this->factory->blog->create();
		$actions = 0;
		$observe = function () use ( &$actions ) {
			++$actions;
		};

		add_user_to_blog( $site_id, $user_id, 'subscriber' );
		bbp_set_user_role( $user_id, bbp_get_participant_role() );
		switch_to_blog( $site_id );
		bbp_set_user_role( $user_id, bbp_get_participant_role() );
		restore_current_blog();
		update_option( '_bbp_user_block_actions', array( 'block_sites' ) );
		add_action( 'bbp_user_block_action', $observe );
		$old_user = get_current_user_id();
		$admin_id = $this->factory->user->create( array( 'role' => 'administrator' ) );
		grant_super_admin( $admin_id );
		wp_set_current_user( $admin_id );

		try {
			bbp_set_user_role( $user_id, bbp_get_blocked_role() );
			$this->assertSame( bbp_get_blocked_role(), bbp_get_user_role( $user_id ) );
			$this->assertSame( bbp_get_blocked_role(), bbp_get_user_default_role_override( $user_id ) );

			switch_to_blog( $site_id );
			$this->assertSame( bbp_get_blocked_role(), bbp_get_user_role( $user_id ) );
			restore_current_blog();
		} finally {
			remove_action( 'bbp_user_block_action', $observe );
			wp_set_current_user( $old_user );
			revoke_super_admin( $admin_id );
			delete_option( '_bbp_user_block_actions' );
		}

		$this->assertSame( 1, $actions );
	}

	/**
	 * @ticket BBP3262
	 * @covers ::bbp_block_user_on_sites
	 * @covers ::bbp_get_user_default_role_override
	 * @covers ::bbp_set_current_user_default_role
	 * @group multisite
	 */
	public function test_global_block_sets_default_role_only_when_nonmember_visits_site() {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'This test requires multisite.' );
		}

		$user_id     = $this->factory->user->create( array( 'role' => 'subscriber' ) );
		$site_id     = $this->factory->blog->create();
		$member_site = $this->factory->blog->create();
		$old_user    = get_current_user_id();

		add_user_to_blog( $member_site, $user_id, 'subscriber' );
		switch_to_blog( $member_site );
		bbp_remove_user_role( $user_id );
		restore_current_blog();

		$this->assertFalse( is_user_member_of_blog( $user_id, $site_id ) );
		$this->assertFalse( bbp_get_user_default_role_override( $user_id ) );
		$results = bbp_block_user_on_sites( $user_id );
		$this->assertArrayNotHasKey( $site_id, $results );
		$this->assertArrayNotHasKey( $member_site, $results );
		$this->assertSame( bbp_get_blocked_role(), bbp_get_user_default_role_override( $user_id ) );
		$this->assertFalse( is_user_member_of_blog( $user_id, $site_id ) );

		switch_to_blog( $member_site );
		try {
			$this->assertFalse( bbp_get_user_role( $user_id ) );
		} finally {
			restore_current_blog();
		}

		switch_to_blog( $site_id );
		try {
			update_option( '_bbp_allow_global_access', 1 );
			wp_set_current_user( $user_id );
			$this->assertSame( bbp_get_blocked_role(), bbp_get_user_role( $user_id ) );
			$this->assertFalse( current_user_can( 'spectate' ) );
			$this->assertTrue( is_user_member_of_blog( $user_id, $site_id ) );
		} finally {
			wp_set_current_user( $old_user );
			restore_current_blog();
		}
	}

	/**
	 * @ticket BBP3262
	 * @covers ::bbp_get_user_default_role_override
	 * @covers ::bbp_set_current_user_default_role
	 * @group multisite
	 */
	public function test_valid_default_role_override_precedes_role_map_and_invalid_override_does_not() {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'This test requires multisite.' );
		}

		$user_id  = $this->factory->user->create( array( 'role' => 'subscriber' ) );
		$other_id = $this->factory->user->create( array( 'role' => 'subscriber' ) );
		$site_id  = $this->factory->blog->create();
		$old_user = get_current_user_id();

		update_user_meta( $user_id, '_bbp_default_role', 'administrator' );
		$this->assertFalse( bbp_get_user_default_role_override( $user_id ) );
		update_user_meta( $user_id, '_bbp_default_role', bbp_get_spectator_role() );
		$this->assertSame( bbp_get_spectator_role(), bbp_get_user_default_role_override( $user_id ) );
		update_user_meta( $other_id, '_bbp_default_role', 'administrator' );

		switch_to_blog( $site_id );
		try {
			update_option( '_bbp_allow_global_access', 1 );
			wp_set_current_user( $user_id );
			$this->assertSame( bbp_get_spectator_role(), bbp_get_user_role( $user_id ) );
			wp_set_current_user( $other_id );
			$this->assertSame( bbp_get_default_role(), bbp_get_user_role( $other_id ) );
		} finally {
			wp_set_current_user( $old_user );
			restore_current_blog();
		}
	}

	/**
	 * @ticket BBP3262
	 * @covers ::bbp_set_current_user_default_role
	 * @group multisite
	 */
	public function test_default_role_override_respects_disabled_global_access() {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'This test requires multisite.' );
		}

		$user_id  = $this->factory->user->create( array( 'role' => 'subscriber' ) );
		$site_id  = $this->factory->blog->create();
		$old_user = get_current_user_id();

		update_user_meta( $user_id, '_bbp_default_role', bbp_get_blocked_role() );

		switch_to_blog( $site_id );
		try {
			update_option( '_bbp_allow_global_access', 0 );
			wp_set_current_user( $user_id );
			$this->assertFalse( current_user_can( 'spectate' ) );
			$this->assertFalse( bbp_get_user_role( $user_id ) );
			$this->assertFalse( is_user_member_of_blog( $user_id, $site_id ) );
		} finally {
			wp_set_current_user( $old_user );
			restore_current_blog();
		}
	}

	/**
	 * @ticket BBP3262
	 * @covers ::bbp_clear_user_default_role_on_unblock
	 * @group multisite
	 */
	public function test_network_admin_unblock_clears_override_and_other_blocked_roles_only() {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'This test requires multisite.' );
		}

		$user_id  = $this->factory->user->create( array( 'role' => 'subscriber' ) );
		$admin_id = $this->factory->user->create( array( 'role' => 'administrator' ) );
		$blocked_site = $this->factory->blog->create();
		$staff_site   = $this->factory->blog->create();
		$old_user = get_current_user_id();

		add_user_to_blog( $blocked_site, $user_id, 'subscriber' );
		add_user_to_blog( $staff_site, $user_id, 'subscriber' );
		switch_to_blog( $blocked_site );
		bbp_set_user_role( $user_id, bbp_get_blocked_role() );
		restore_current_blog();
		switch_to_blog( $staff_site );
		bbp_set_user_role( $user_id, bbp_get_moderator_role() );
		restore_current_blog();

		update_user_meta( $user_id, '_bbp_default_role', bbp_get_blocked_role() );
		bbp_set_user_role( $user_id, bbp_get_blocked_role() );
		wp_set_current_user( $admin_id );
		bbp_set_user_role( $user_id, bbp_get_participant_role() );
		$this->assertSame( bbp_get_blocked_role(), bbp_get_user_default_role_override( $user_id ) );
		switch_to_blog( $blocked_site );
		$this->assertSame( bbp_get_blocked_role(), bbp_get_user_role( $user_id ) );
		restore_current_blog();

		grant_super_admin( $admin_id );
		wp_set_current_user( $admin_id );
		try {
			bbp_set_user_role( $user_id, bbp_get_blocked_role() );
			bbp_remove_user_role( $user_id );
			$this->assertFalse( bbp_get_user_default_role_override( $user_id ) );
			$this->assertSame( '', get_user_meta( $user_id, '_bbp_default_role', true ) );
			switch_to_blog( $blocked_site );
			try {
				$this->assertFalse( bbp_get_user_role( $user_id ) );
				wp_set_current_user( $user_id );
				$this->assertSame( bbp_get_default_role(), bbp_get_user_role( $user_id ) );
			} finally {
				wp_set_current_user( $admin_id );
				restore_current_blog();
			}
			switch_to_blog( $staff_site );
			$this->assertSame( bbp_get_moderator_role(), bbp_get_user_role( $user_id ) );
			restore_current_blog();

			update_user_meta( $user_id, '_bbp_default_role', bbp_get_spectator_role() );
			bbp_set_user_role( $user_id, bbp_get_blocked_role() );
			bbp_set_user_role( $user_id, bbp_get_participant_role() );
			$this->assertSame( bbp_get_spectator_role(), bbp_get_user_default_role_override( $user_id ) );
		} finally {
			wp_set_current_user( $old_user );
			revoke_super_admin( $admin_id );
		}
	}

	/**
	 * @ticket BBP3262
	 * @covers ::bbp_mark_user_as_spam
	 * @covers ::bbp_unspam_user_after_unblock
	 * @covers ::bbp_clear_user_block_spam_marker
	 * @group multisite
	 */
	public function test_network_admin_unblock_unspams_only_account_marked_by_block_action() {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'This test requires multisite.' );
		}

		$user_id       = $this->factory->user->create( array( 'role' => 'subscriber' ) );
		$pre_spam_id   = $this->factory->user->create( array( 'role' => 'subscriber' ) );
		$admin_id      = $this->factory->user->create( array( 'role' => 'administrator' ) );
		$old_user      = get_current_user_id();
		$ham_actions   = 0;
		$observe_ham   = function () use ( &$ham_actions ) {
			++$ham_actions;
		};

		wp_update_user( array( 'ID' => $pre_spam_id, 'spam' => '1' ) );
		update_option( '_bbp_user_block_actions', array( 'mark_spam' ) );
		grant_super_admin( $admin_id );
		wp_set_current_user( $admin_id );
		add_action( 'make_ham_user', $observe_ham );

		try {
			bbp_set_user_role( $user_id, bbp_get_blocked_role() );
			$this->assertSame( '1', get_userdata( $user_id )->spam );
			$this->assertSame( '1', get_user_meta( $user_id, '_bbp_block_action_spammed_user', true ) );
			revoke_super_admin( $admin_id );
			wp_set_current_user( $admin_id );
			bbp_set_user_role( $user_id, bbp_get_participant_role() );
			$this->assertSame( '1', get_userdata( $user_id )->spam );
			$this->assertSame( '1', get_user_meta( $user_id, '_bbp_block_action_spammed_user', true ) );
			$this->assertSame( 0, $ham_actions );

			grant_super_admin( $admin_id );
			wp_set_current_user( $admin_id );
			bbp_set_user_role( $user_id, bbp_get_blocked_role() );
			bbp_set_user_role( $user_id, bbp_get_participant_role() );
			$this->assertSame( '0', get_userdata( $user_id )->spam );
			$this->assertSame( '', get_user_meta( $user_id, '_bbp_block_action_spammed_user', true ) );
			$this->assertSame( 1, $ham_actions );

			bbp_set_user_role( $pre_spam_id, bbp_get_blocked_role() );
			$this->assertSame( '', get_user_meta( $pre_spam_id, '_bbp_block_action_spammed_user', true ) );
			bbp_set_user_role( $pre_spam_id, bbp_get_participant_role() );
			$this->assertSame( '1', get_userdata( $pre_spam_id )->spam );
			$this->assertSame( 1, $ham_actions );
		} finally {
			remove_action( 'make_ham_user', $observe_ham );
			delete_option( '_bbp_user_block_actions' );
			wp_set_current_user( $old_user );
			revoke_super_admin( $admin_id );
		}
	}

	/**
	 * @ticket BBP3262
	 * @covers ::bbp_run_user_block_actions
	 * @covers ::bbp_clear_user_default_role_on_unblock
	 * @covers ::bbp_unspam_user_after_unblock
	 * @group multisite
	 */
	public function test_unblock_reverses_selected_cross_site_role_and_spam_actions_together() {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'This test requires multisite.' );
		}

		$user_id  = $this->factory->user->create( array( 'role' => 'subscriber' ) );
		$admin_id = $this->factory->user->create( array( 'role' => 'administrator' ) );
		$site_id  = $this->factory->blog->create();
		$old_user = get_current_user_id();

		add_user_to_blog( $site_id, $user_id, 'subscriber' );
		switch_to_blog( $site_id );
		bbp_set_user_role( $user_id, bbp_get_participant_role() );
		restore_current_blog();

		update_option( '_bbp_user_block_actions', array( 'block_sites', 'mark_spam' ) );
		grant_super_admin( $admin_id );
		wp_set_current_user( $admin_id );

		try {
			bbp_set_user_role( $user_id, bbp_get_blocked_role() );
			$this->assertSame( bbp_get_blocked_role(), bbp_get_user_default_role_override( $user_id ) );
			$this->assertSame( '1', get_userdata( $user_id )->spam );
			switch_to_blog( $site_id );
			$this->assertSame( bbp_get_blocked_role(), bbp_get_user_role( $user_id ) );
			restore_current_blog();

			bbp_set_user_role( $user_id, bbp_get_participant_role() );
			$this->assertFalse( bbp_get_user_default_role_override( $user_id ) );
			$this->assertSame( '0', get_userdata( $user_id )->spam );
			switch_to_blog( $site_id );
			$this->assertFalse( bbp_get_user_role( $user_id ) );
			restore_current_blog();
		} finally {
			delete_option( '_bbp_user_block_actions' );
			wp_set_current_user( $old_user );
			revoke_super_admin( $admin_id );
		}
	}

	/**
	 * @ticket BBP3262
	 * @covers ::bbp_set_user_role_on_sites
	 * @group multisite
	 */
	public function test_set_user_role_on_sites_can_clear_forum_role() {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'This test requires multisite.' );
		}

		$user_id     = $this->factory->user->create( array( 'role' => 'subscriber' ) );
		$site_id     = $this->factory->blog->create();
		$transitions = array();
		$observe     = function ( $new_role, $old_role, $user ) use ( $user_id, &$transitions ) {
			if ( $user_id === $user->ID ) {
				$transitions[] = array( get_current_blog_id(), $old_role, $new_role );
			}
		};

		add_user_to_blog( $site_id, $user_id, 'subscriber' );
		bbp_set_user_role( $user_id, bbp_get_participant_role() );
		switch_to_blog( $site_id );
		bbp_set_user_role( $user_id, bbp_get_participant_role() );
		$site_caps = get_userdata( $user_id )->caps;
		unset( $site_caps[ bbp_get_participant_role() ] );
		restore_current_blog();
		add_action( 'bbp_transition_user_role', $observe, 10, 3 );

		try {
			$this->assertSame( array(), bbp_set_user_role_on_sites( $user_id ) );
			$this->assertSame( array( $site_id => true ), bbp_set_user_role_on_sites( $user_id, '', array( $site_id ) ) );

			switch_to_blog( $site_id );
			try {
				$this->assertFalse( bbp_get_user_role( $user_id ) );
				$this->assertSame( $site_caps, get_userdata( $user_id )->caps );
			} finally {
				restore_current_blog();
			}
			$this->assertSame( bbp_get_participant_role(), bbp_get_user_role( $user_id ) );
		} finally {
			remove_action( 'bbp_transition_user_role', $observe );
		}

		$this->assertSame( array( array( $site_id, bbp_get_participant_role(), false ) ), $transitions );
	}

	/**
	 * @ticket BBP3262
	 * @covers ::bbp_set_user_role_on_sites
	 * @group multisite
	 */
	public function test_explicit_empty_site_list_does_not_default_to_user_sites() {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'This test requires multisite.' );
		}

		$user_id = $this->factory->user->create( array( 'role' => 'subscriber' ) );
		$site_id = $this->factory->blog->create();

		add_user_to_blog( $site_id, $user_id, 'subscriber' );
		switch_to_blog( $site_id );
		bbp_set_user_role( $user_id, bbp_get_participant_role() );
		restore_current_blog();

		$this->assertSame( array(), bbp_set_user_role_on_sites( $user_id, bbp_get_blocked_role(), array() ) );

		switch_to_blog( $site_id );
		try {
			$this->assertSame( bbp_get_participant_role(), bbp_get_user_role( $user_id ) );
		} finally {
			restore_current_blog();
		}
	}

	/**
	 * @ticket BBP3262
	 * @covers ::bbp_mark_user_as_spam
	 * @group multisite
	 */
	public function test_mark_user_as_spam_updates_network_account_once() {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'This test requires multisite.' );
		}

		$user_id = $this->factory->user->create();
		$called  = 0;
		$observe = function () use ( &$called ) {
			++$called;
		};

		add_action( 'make_spam_user', $observe );

		try {
			$this->assertTrue( bbp_mark_user_as_spam( $user_id ) );
			$this->assertSame( '1', get_userdata( $user_id )->spam );
			$this->assertTrue( bbp_mark_user_as_spam( $user_id ) );
		} finally {
			remove_action( 'make_spam_user', $observe );
		}

		$this->assertSame( 1, $called );
	}

	/**
	 * @covers ::bbp_get_user_role_map
	 */
	public function test_wordpress_role_map_defaults_to_keymaster_for_administrators() {
		$map = bbp_get_user_role_map();
		$this->assertSame( bbp_get_keymaster_role(), $map['administrator'] );
		$this->assertSame( bbp_get_default_role(), $map['subscriber'] );
		$this->assertSame( bbp_get_default_role(), $map['editor'] );
	}

	/**
	 * @covers ::bbp_is_user_spammer
	 * @covers ::bbp_is_user_deleted
	 * @covers ::bbp_is_user_active
	 * @covers ::bbp_is_user_inactive
	 */
	public function test_user_activity_distinguishes_valid_missing_and_filtered_users() {
		$user_id = $this->factory->user->create();
		$this->assertFalse( bbp_is_user_spammer( $user_id ) );
		$this->assertFalse( bbp_is_user_deleted( $user_id ) );
		$this->assertTrue( bbp_is_user_active( $user_id ) );
		$this->assertFalse( bbp_is_user_inactive( $user_id ) );
		$this->assertFalse( bbp_is_user_active( PHP_INT_MAX ) );
		$this->assertTrue( bbp_is_user_deleted( PHP_INT_MAX ) );

		add_filter( 'bbp_core_is_user_spammer', '__return_true' );
		try {
			$this->assertFalse( bbp_is_user_active( $user_id ) );
			$this->assertTrue( bbp_is_user_inactive( $user_id ) );
		} finally {
			remove_filter( 'bbp_core_is_user_spammer', '__return_true' );
		}
	}

	/**
	 * @covers ::bbp_is_user_keymaster
	 */
	public function test_keymaster_check_uses_forum_role_and_filter() {
		$user_id = $this->factory->user->create();
		$this->assertFalse( bbp_is_user_keymaster( $user_id ) );
		bbp_set_user_role( $user_id, bbp_get_keymaster_role() );
		$this->assertTrue( bbp_is_user_keymaster( $user_id ) );

		add_filter( 'bbp_is_user_keymaster', '__return_false' );
		try {
			$this->assertFalse( bbp_is_user_keymaster( $user_id ) );
		} finally {
			remove_filter( 'bbp_is_user_keymaster', '__return_false' );
		}
	}

	/**
	 * @covers ::bbp_user_has_profile
	 */
	public function test_profile_visibility_rejects_missing_and_inactive_users_except_for_keymaster() {
		$user_id      = $this->factory->user->create();
		$keymaster_id = $this->factory->user->create();
		bbp_set_user_role( $keymaster_id, bbp_get_keymaster_role() );
		$this->assertTrue( bbp_user_has_profile( $user_id ) );
		$this->assertFalse( bbp_user_has_profile( PHP_INT_MAX ) );

		$this->set_current_user( $user_id );
		add_filter( 'bbp_core_is_user_spammer', '__return_true' );
		try {
			$this->assertFalse( bbp_user_has_profile( $user_id ) );
			$this->set_current_user( $keymaster_id );
			$this->assertTrue( bbp_user_has_profile( $user_id ) );
		} finally {
			remove_filter( 'bbp_core_is_user_spammer', '__return_true' );
		}
	}

	/**
	 * @covers ::bbp_add_moderator
	 * @covers ::bbp_remove_moderator
	 * @covers ::bbp_get_moderator_ids
	 * @covers ::bbp_get_moderators
	 */
	public function test_object_moderators_are_added_listed_and_removed() {
		$forum_id     = $this->factory->forum->create();
		$moderator_id = $this->factory->user->create();
		$this->assertTrue( bbp_add_moderator( $forum_id, $moderator_id ) );
		$this->assertContains( $moderator_id, bbp_get_moderator_ids( $forum_id ) );
		$this->assertContains( $moderator_id, wp_list_pluck( bbp_get_moderators( $forum_id ), 'ID' ) );

		$this->assertTrue( bbp_remove_moderator( $forum_id, $moderator_id ) );
		$this->assertNotContains( $moderator_id, bbp_get_moderator_ids( $forum_id ) );
		$this->assertSame( array(), bbp_get_moderators( $forum_id ) );
	}

	/**
	 * @covers ::bbp_get_moderators
	 */
	public function test_global_moderators_have_moderator_role() {
		$moderator_id = $this->factory->user->create();
		$other_id     = $this->factory->user->create();
		bbp_set_user_role( $moderator_id, bbp_get_moderator_role() );

		$ids = wp_list_pluck( bbp_get_moderators(), 'ID' );
		$this->assertContains( $moderator_id, $ids );
		$this->assertNotContains( $other_id, $ids );
	}
}
