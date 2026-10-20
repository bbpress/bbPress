<?php

/**
 * Tests for admin settings choice validation.
 *
 * @group settings
 */
class BBP_Tests_Admin_Settings_Sanitization extends BBP_UnitTestCase {

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
