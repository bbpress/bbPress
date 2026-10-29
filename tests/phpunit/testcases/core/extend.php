<?php

/**
 * Tests for core extension loading.
 *
 * @group core
 * @group extend
 * @ticket 3706
 */
class BBP_Tests_Core_Extend extends BBP_UnitTestCase {

	/**
	 * @covers ::bbp_setup_akismet
	 */
	public function test_setup_akismet_stops_when_akismet_is_unavailable() {
		if ( defined( 'AKISMET_VERSION' ) ) {
			$this->markTestSkipped( 'Akismet is already loaded.' );
		}

		unset( bbpress()->extend->akismet );
		bbp_setup_akismet();

		$this->assertFalse( isset( bbpress()->extend->akismet ) );
	}

	/**
	 * @coversNothing
	 */
	public function test_akismet_setup_is_registered_on_bbp_ready() {
		$this->assertSame( 2, has_action( 'bbp_ready', 'bbp_setup_akismet' ) );
	}

	/**
	 * @covers ::bbp_setup_buddypress
	 * @covers ::buddypress
	 */
	public function test_setup_buddypress_provides_the_legacy_helper_and_stops_without_buddypress() {
		if ( defined( 'BP_VERSION' ) ) {
			$this->markTestSkipped( 'BuddyPress is already loaded.' );
		}

		$had_bp        = array_key_exists( 'bp', $GLOBALS );
		$old_bp        = $had_bp ? $GLOBALS['bp'] : null;
		$had_component = property_exists( bbpress()->extend, 'buddypress' );
		$old_component = $had_component ? bbpress()->extend->buddypress : null;

		unset( $GLOBALS['bp'], bbpress()->extend->buddypress );
		try {
			bbp_setup_buddypress();

			$this->assertTrue( function_exists( 'buddypress' ) );
			$this->assertFalse( buddypress() );
			$this->assertFalse( isset( bbpress()->extend->buddypress ) );
		} finally {
			$this->restore_buddypress_state( $had_bp, $old_bp, $had_component, $old_component );
		}
	}

	/**
	 * @covers ::bbp_setup_buddypress
	 */
	public function test_setup_buddypress_stops_during_maintenance_mode() {
		if ( defined( 'BP_VERSION' ) ) {
			$this->markTestSkipped( 'BuddyPress is already loaded.' );
		}

		$had_bp        = array_key_exists( 'bp', $GLOBALS );
		$old_bp        = $had_bp ? $GLOBALS['bp'] : null;
		$had_component = property_exists( bbpress()->extend, 'buddypress' );
		$old_component = $had_component ? bbpress()->extend->buddypress : null;

		$GLOBALS['bp'] = (object) array( 'maintenance_mode' => true );
		unset( bbpress()->extend->buddypress );
		try {
			bbp_setup_buddypress();

			$this->assertSame( $GLOBALS['bp'], buddypress() );
			$this->assertFalse( isset( bbpress()->extend->buddypress ) );
		} finally {
			$this->restore_buddypress_state( $had_bp, $old_bp, $had_component, $old_component );
		}
	}

	/**
	 * Restore BuddyPress globals and the bbPress extension property.
	 *
	 * @param bool  $had_bp        Whether the BuddyPress global existed.
	 * @param mixed $old_bp        Original BuddyPress global.
	 * @param bool  $had_component Whether the bbPress component existed.
	 * @param mixed $old_component Original bbPress component.
	 */
	private function restore_buddypress_state( $had_bp, $old_bp, $had_component, $old_component ) {
		if ( $had_bp ) {
			$GLOBALS['bp'] = $old_bp;
		} else {
			unset( $GLOBALS['bp'] );
		}

		if ( $had_component ) {
			bbpress()->extend->buddypress = $old_component;
		} else {
			unset( bbpress()->extend->buddypress );
		}
	}
}
