<?php

/**
 * Tests for shortcode registration.
 *
 * @group common
 * @group shortcodes
 */
class BBP_Tests_Common_Shortcode_Registration extends BBP_UnitTestCase {
	public function filter_shortcodes() {
		return array(
			''          => '__return_empty_string',
			0           => '__return_empty_string',
			'bbp-valid' => '__return_empty_string',
		);
	}

	/**
	 * @covers BBP_Shortcodes::add_shortcodes
	 * @ticket 3794
	 */
	public function test_empty_filtered_tags_are_skipped() {
		add_filter( 'bbp_shortcodes', array( $this, 'filter_shortcodes' ) );
		try {
			new BBP_Shortcodes();
			$this->assertFalse( shortcode_exists( '' ) );
			$this->assertTrue( shortcode_exists( '0' ) );
			$this->assertTrue( shortcode_exists( 'bbp-valid' ) );
		} finally {
			remove_filter( 'bbp_shortcodes', array( $this, 'filter_shortcodes' ) );
			remove_shortcode( '0' );
			remove_shortcode( 'bbp-valid' );
		}
	}
}
