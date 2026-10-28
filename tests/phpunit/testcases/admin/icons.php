<?php

/**
 * Tests for bbPress admin icons.
 *
 * @group admin
 */
class BBP_Tests_Admin_Icons extends BBP_UnitTestCase {

	public function setUp(): void {
		parent::setUp();

		require_once BBP_PLUGIN_DIR . 'includes/admin/icons.php';
	}

	/**
	 * @covers ::bbp_use_svg_admin_icons
	 * @covers ::bbp_admin_icon_body_class
	 */
	public function test_svg_icon_support_follows_wordpress_version_and_api_availability() {
		global $wp_version;

		$old_version = $wp_version;
		$wp_version = '7.1.9';

		try {
			$this->assertFalse( bbp_use_svg_admin_icons() );
			$this->assertSame( 'wp-admin', bbp_admin_icon_body_class( 'wp-admin' ) );

			$wp_version = '7.2-alpha';
			$supported  = function_exists( 'wp_is_valid_icon_name' )
				&& function_exists( 'wp_register_icon_collection' )
				&& function_exists( 'wp_register_icon' )
				&& function_exists( 'wp_get_icon' );

			$this->assertSame( $supported, bbp_use_svg_admin_icons() );
			$this->assertSame( $supported ? 'wp-admin bbp-svg-icons' : 'wp-admin', bbp_admin_icon_body_class( 'wp-admin' ) );
		} finally {
			$wp_version = $old_version;
		}
	}

	/**
	 * @covers ::bbp_register_admin_icons
	 */
	public function test_admin_icons_are_registered_when_supported() {
		if ( ! bbp_use_svg_admin_icons() ) {
			$this->markTestSkipped( 'The WordPress Icons API is not available.' );
		}

		$icons          = array( 'forum', 'topic', 'reply', 'logo' );
		$had_collection = WP_Icon_Collections_Registry::get_instance()->is_registered( 'bbpress' );
		$had_icons      = array();
		foreach ( $icons as $icon ) {
			$had_icons[ $icon ] = WP_Icons_Registry::get_instance()->is_registered( 'bbpress/' . $icon );
		}

		try {
			bbp_register_admin_icons();

			foreach ( $icons as $icon ) {
				$this->assertStringContainsString( '<svg', wp_get_icon( 'bbpress/' . $icon ) );
			}
		} finally {
			foreach ( $had_icons as $icon => $registered ) {
				if ( ! $registered ) {
					wp_unregister_icon( 'bbpress/' . $icon );
				}
			}

			if ( ! $had_collection ) {
				wp_unregister_icon_collection( 'bbpress' );
			}
		}
	}
}
