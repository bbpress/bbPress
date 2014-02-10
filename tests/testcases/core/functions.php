<?php

/**
 * Tests to test that that testing framework is testing tests. Meta, huh?
 *
 * @package wordpress-plugins-tests
 */
class BBP_bbPress_Tests extends WP_UnitTestCase  {

	/**
	 * Ensure that the plugin has been installed and activated.
	 */
	function test_plugin_activated() {
		$this->assertTrue( is_plugin_active( 'bbpress/bbpress.php' ) );
	}
}
