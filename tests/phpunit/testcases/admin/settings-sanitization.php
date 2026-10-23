<?php

/**
 * Tests for admin settings choice validation.
 *
 * @group settings
 */
class BBP_Tests_Admin_Settings_Sanitization extends BBP_UnitTestCase {

	/**
	 * @ticket BBP3262
	 * @covers ::bbp_admin_sanitize_user_block_actions
	 */
	public function test_user_block_actions_accept_registered_callable_choices_only() {
		require_once BBP_PLUGIN_DIR . 'includes/admin/settings.php';
		$old_user = get_current_user_id();
		$admin_id = $this->factory->user->create( array( 'role' => 'administrator' ) );

		if ( is_multisite() ) {
			grant_super_admin( $admin_id );
		}

		wp_set_current_user( $admin_id );

		$filter = function ( $actions ) {
			$actions['custom'] = array(
				'name'        => 'Custom',
				'callback'    => '__return_true',
				'description' => '',
			);
			$actions['invalid'] = array(
				'name'        => 'Invalid',
				'callback'    => 'bbp_missing_callback',
				'description' => '',
			);

			return $actions;
		};

		add_filter( 'bbp_get_user_block_actions', $filter );

		try {
			$expected = is_multisite()
				? array( 'destroy_sessions', 'block_sites', 'mark_spam', 'custom' )
				: array( 'destroy_sessions', 'custom' );
			$this->assertSame(
				$expected,
				bbp_admin_sanitize_user_block_actions( array( 'destroy_sessions', 'block_sites', 'unknown', 'mark_spam', 'custom', 'invalid', 'custom' ) )
			);
			$this->assertSame( array(), bbp_admin_sanitize_user_block_actions( 'destroy_sessions' ) );
		} finally {
			remove_filter( 'bbp_get_user_block_actions', $filter );
			wp_set_current_user( $old_user );
			if ( is_multisite() ) {
				revoke_super_admin( $admin_id );
			}
		}
	}

	/**
	 * @ticket BBP3262
	 * @covers ::bbp_admin_sanitize_user_block_actions
	 */
	public function test_user_block_actions_preserve_selected_choices_hidden_by_capability() {
		require_once BBP_PLUGIN_DIR . 'includes/admin/settings.php';
		$old_user = get_current_user_id();
		$admin_id = $this->factory->user->create( array( 'role' => 'administrator' ) );
		$include_available = false;
		$filter   = function () use ( &$include_available ) {
			$actions = array(
				'restricted' => array(
					'name'       => 'Restricted',
					'callback'   => '__return_true',
					'capability' => 'do_not_allow',
				),
			);

			if ( $include_available ) {
				$actions['available'] = array(
					'name'     => 'Available',
					'callback' => '__return_true',
				);
			}

			return $actions;
		};

		wp_set_current_user( $admin_id );
		$this->assertTrue( current_user_can( 'manage_options' ) );
		update_option( '_bbp_user_block_actions', array( 'restricted' ) );
		add_filter( 'bbp_get_user_block_actions', $filter );

		try {
			$this->assertSame( array( 'restricted' ), bbp_admin_sanitize_user_block_actions( null ) );
			$this->assertSame( array( 'restricted' ), bbp_admin_sanitize_user_block_actions( array( 'restricted' ) ) );
			$include_available = true;
			update_option( '_bbp_user_block_actions', array( 'restricted', 'available' ) );
			$this->assertSame( array( 'restricted' ), bbp_admin_sanitize_user_block_actions( array( '' ) ) );
			$this->assertSame( array( 'available', 'restricted' ), bbp_admin_sanitize_user_block_actions( array( 'available' ) ) );
			delete_option( '_bbp_user_block_actions' );
			$this->assertSame( array(), bbp_admin_sanitize_user_block_actions( array( 'restricted' ) ) );
		} finally {
			remove_filter( 'bbp_get_user_block_actions', $filter );
			delete_option( '_bbp_user_block_actions' );
			wp_set_current_user( $old_user );
		}
	}

	/**
	 * @ticket BBP3262
	 * @covers ::bbp_admin_sanitize_user_block_actions
	 */
	public function test_user_block_actions_allow_registry_to_remove_every_choice() {
		require_once BBP_PLUGIN_DIR . 'includes/admin/settings.php';
		$filter = function () {
			return array();
		};

		update_option( '_bbp_user_block_actions', array( 'destroy_sessions' ) );
		add_filter( 'bbp_get_user_block_actions', $filter );

		try {
			$this->assertSame( array(), bbp_admin_sanitize_user_block_actions( null ) );
			$this->assertSame( array(), bbp_admin_sanitize_user_block_actions( array( 'destroy_sessions' ) ) );
		} finally {
			remove_filter( 'bbp_get_user_block_actions', $filter );
			delete_option( '_bbp_user_block_actions' );
		}
	}

	/**
	 * @ticket BBP3262
	 * @covers ::bbp_admin_sanitize_user_block_actions
	 * @covers ::bbp_admin_is_user_block_action_visible
	 */
	public function test_user_block_actions_do_not_save_hidden_or_filter_injected_choices() {
		require_once BBP_PLUGIN_DIR . 'includes/admin/settings.php';
		$old_user = get_current_user_id();
		$admin_id = $this->factory->user->create( array( 'role' => 'administrator' ) );
		$registry = function () {
			return array(
				'nameless' => array( 'callback' => '__return_true' ),
				'injected' => array( 'name' => 'Injected', 'callback' => '__return_true' ),
			);
		};
		$selected = function ( $action_ids ) {
			$action_ids[] = 'injected';
			return $action_ids;
		};

		wp_set_current_user( $admin_id );
		update_option( '_bbp_user_block_actions', array( 'nameless' ) );
		add_filter( 'bbp_get_user_block_actions', $registry );
		add_filter( 'bbp_get_user_block_action_ids', $selected );

		try {
			$this->assertSame( array( 'nameless' ), bbp_admin_sanitize_user_block_actions( array( 'nameless' ) ) );
			$this->assertSame( array( 'nameless' ), bbp_admin_sanitize_user_block_actions( array( '' ) ) );
			delete_option( '_bbp_user_block_actions' );
			$this->assertSame( array(), bbp_admin_sanitize_user_block_actions( array( '' ) ) );
		} finally {
			remove_filter( 'bbp_get_user_block_action_ids', $selected );
			remove_filter( 'bbp_get_user_block_actions', $registry );
			delete_option( '_bbp_user_block_actions' );
			wp_set_current_user( $old_user );
		}
	}

	/**
	 * @ticket BBP3349
	 * @covers ::bbp_admin_sanitize_moderation_notification_recipient_types
	 */
	public function test_moderation_notification_recipient_types_accept_available_choices_only() {
		require_once BBP_PLUGIN_DIR . 'includes/admin/settings.php';

		$fields = bbp_admin_get_settings_fields();
		$this->assertSame( 'bbp_admin_sanitize_moderation_notification_recipient_types', $fields['bbp_settings_moderation_notifications']['_bbp_moderation_notification_recipient_types']['sanitize_callback'] );
		$this->assertSame( array(), bbp_admin_sanitize_moderation_notification_recipient_types( 'keymasters' ) );
		$this->assertSame(
			array( 'keymasters', 'forum_moderators' ),
			bbp_admin_sanitize_moderation_notification_recipient_types( array( 'keymasters', 'unknown', 'forum_moderators', 'keymasters' ) )
		);
	}

	/**
	 * @ticket BBP3352
	 * @covers ::bbp_admin_sanitize_moderation_links
	 */
	public function test_moderation_link_limit_accepts_positive_integers_or_inheritance() {
		require_once BBP_PLUGIN_DIR . 'includes/admin/settings.php';

		$fields = bbp_admin_get_settings_fields();
		$this->assertSame( 'bbp_admin_sanitize_moderation_links', $fields['bbp_settings_users']['_bbp_moderation_links']['sanitize_callback'] );
		$this->assertFalse( bbp_admin_sanitize_moderation_links( '' ) );
		$this->assertFalse( bbp_admin_sanitize_moderation_links( null ) );
		$this->assertFalse( bbp_admin_sanitize_moderation_links( array( 3 ) ) );
		$this->assertSame( 7, bbp_admin_sanitize_moderation_links( '7' ) );
		$this->assertSame( 1, bbp_admin_sanitize_moderation_links( '-3' ) );
		$this->assertSame( 1, bbp_admin_sanitize_moderation_links( '0' ) );
	}

	public function test_default_role_accepts_registered_choices_only() {
		require_once BBP_PLUGIN_DIR . 'includes/admin/settings.php';

		$fields = bbp_admin_get_settings_fields();
		$this->assertSame( 'bbp_admin_sanitize_default_role', $fields['bbp_settings_users']['_bbp_default_role']['sanitize_callback'] );

		update_option( '_bbp_default_role', bbp_get_participant_role() );
		$this->assertSame( bbp_get_participant_role(), bbp_admin_sanitize_default_role( 'administrator' ) );
		$this->assertSame( bbp_get_participant_role(), bbp_admin_sanitize_default_role( array( 'bbp_keymaster' ) ) );
		update_option( '_bbp_default_role', bbp_get_moderator_role() );
		$this->assertSame( bbp_get_moderator_role(), bbp_admin_sanitize_default_role( 'unknown' ) );

		$role = 'bbp_custom_role';
		$add_role = function ( $roles ) use ( $role ) {
			$roles[ $role ] = array( 'name' => 'Custom Role', 'capabilities' => array() );
			return $roles;
		};
		add_filter( 'bbp_get_dynamic_roles', $add_role );
		$this->assertSame( $role, bbp_admin_sanitize_default_role( $role ) );
		remove_filter( 'bbp_get_dynamic_roles', $add_role );
	}

	public function test_theme_package_accepts_registered_choices_only() {
		require_once BBP_PLUGIN_DIR . 'includes/admin/settings.php';

		$fields = bbp_admin_get_settings_fields();
		$this->assertSame( 'bbp_admin_sanitize_theme_package_id', $fields['bbp_settings_theme_compat']['_bbp_theme_package_id']['sanitize_callback'] );

		update_option( '_bbp_theme_package_id', 'default' );
		$this->assertSame( 'default', bbp_admin_sanitize_theme_package_id( 'unknown' ) );
		$this->assertSame( 'default', bbp_admin_sanitize_theme_package_id( array( 'default' ) ) );

		$package = "custom'package";
		bbp_register_theme_package( array( 'id' => $package, 'name' => 'Custom Package' ) );
		$this->assertSame( $package, bbp_admin_sanitize_theme_package_id( $package ) );
		update_option( '_bbp_theme_package_id', $package );
		$this->assertSame( $package, bbp_admin_sanitize_theme_package_id( 'unknown' ) );
		unset( bbpress()->theme_compat->packages[ $package ] );
	}
}
