<?php

/**
 * Tests to test that that testing framework is testing tests. Meta, huh?
 *
 * @package wordpress-plugins-tests
 */
class BBP_bbPress_Tests extends WP_UnitTestCase  {

	/**
	 * Ensure that bbPress function exists
	 */
	function test_bbpress_exists() {
		$this->assertTrue( function_exists( 'bbpress' ) );
	}

	/**
	 * Ensure that bbPress has been installed and activated.
	 */
	function test_plugin_activated() {
		$this->assertTrue( is_plugin_active( 'bbpress/bbpress.php' ) );
	}
}
