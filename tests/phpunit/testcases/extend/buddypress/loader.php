<?php

/**
 * BuddyPress Extension Loader Tests.
 *
 * @group extend
 * @group buddypress
 */
class BBP_Tests_Extend_BuddyPress_Loader extends BBP_UnitTestCase {

	/**
	 * The BuddyPress component is initialized at the start of BuddyPress init.
	 *
	 * @ticket BBP3653
	 */
	public function test_component_setup_uses_buddypress_init_action() {
		$this->assertFalse( has_action( 'bp_include', 'bbp_setup_buddypress' ) );
		$this->assertSame( 0, has_action( 'bp_init', 'bbp_setup_buddypress' ) );
		$this->assertInstanceOf( 'BBP_Forums_Component', bbpress()->extend->buddypress );
	}
}
