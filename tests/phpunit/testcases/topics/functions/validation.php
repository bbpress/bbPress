<?php

/**
 * Tests for the topic component validation functions.
 *
 * @group topics
 * @group functions
 * @group validation
 */
class BBP_Tests_Topics_Functions_Validation extends BBP_UnitTestCase {

	protected function validate_topic_title( $topic_title = '', $empty_error_code = 'bbp_topic_title' ) {
		$old_errors        = bbpress()->errors;
		bbpress()->errors = new WP_Error();

		try {
			bbp_validate_topic_title( $topic_title, $empty_error_code );
			return bbpress()->errors;
		} finally {
			bbpress()->errors = $old_errors;
		}
	}

	/**
	 * @covers ::bbp_validate_topic_title
	 */
	public function test_bbp_validate_topic_title_accepts_valid_title() {
		$errors = $this->validate_topic_title( 'Valid topic title' );

		$this->assertSame( array(), $errors->get_error_codes() );
	}

	/**
	 * @covers ::bbp_validate_topic_title
	 */
	public function test_bbp_validate_topic_title_rejects_empty_title() {
		$errors = $this->validate_topic_title();

		$this->assertSame( array( 'bbp_topic_title' ), $errors->get_error_codes() );
		$this->assertSame( '<strong>Error</strong>: Your topic needs a title.', $errors->get_error_message( 'bbp_topic_title' ) );
	}

	/**
	 * @covers ::bbp_validate_topic_title
	 */
	public function test_bbp_validate_topic_title_accepts_empty_error_code() {
		$errors = $this->validate_topic_title( '', 'bbp_edit_topic_title' );

		$this->assertSame( array( 'bbp_edit_topic_title' ), $errors->get_error_codes() );
		$this->assertSame( '<strong>Error</strong>: Your topic needs a title.', $errors->get_error_message( 'bbp_edit_topic_title' ) );
	}

	/**
	 * @covers ::bbp_validate_topic_title
	 */
	public function test_bbp_validate_topic_title_rejects_long_title() {
		$errors = $this->validate_topic_title( str_repeat( 'a', bbp_get_title_max_length() + 1 ) );

		$this->assertSame( array( 'bbp_topic_title' ), $errors->get_error_codes() );
		$this->assertSame( '<strong>Error</strong>: Your title is too long.', $errors->get_error_message( 'bbp_topic_title' ) );
	}

	/**
	 * @covers ::bbp_validate_topic_title
	 */
	public function test_bbp_validate_topic_title_preserves_existing_errors() {
		$old_errors        = bbpress()->errors;
		bbpress()->errors = new WP_Error();
		bbp_add_error( 'existing_error', 'Existing error.' );

		try {
			bbp_validate_topic_title();
			$this->assertSame( array( 'existing_error', 'bbp_topic_title' ), bbpress()->errors->get_error_codes() );
		} finally {
			bbpress()->errors = $old_errors;
		}
	}
}
