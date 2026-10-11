<?php

/**
 * Tests for the reply component validation functions.
 *
 * @group replies
 * @group functions
 * @group validation
 */
class BBP_Tests_Replies_Functions_Validation extends BBP_UnitTestCase {

	protected function validate_reply_title( $reply_title = '' ) {
		$old_errors        = bbpress()->errors;
		bbpress()->errors = new WP_Error();

		try {
			bbp_validate_reply_title( $reply_title );
			return bbpress()->errors;
		} finally {
			bbpress()->errors = $old_errors;
		}
	}

	/**
	 * @covers ::bbp_validate_reply_title
	 */
	public function test_bbp_validate_reply_title_accepts_valid_title() {
		$errors = $this->validate_reply_title( 'Valid reply title' );

		$this->assertSame( array(), $errors->get_error_codes() );
	}

	/**
	 * @covers ::bbp_validate_reply_title
	 */
	public function test_bbp_validate_reply_title_accepts_empty_title() {
		$errors = $this->validate_reply_title();

		$this->assertSame( array(), $errors->get_error_codes() );
	}

	/**
	 * @covers ::bbp_validate_reply_title
	 */
	public function test_bbp_validate_reply_title_rejects_long_title() {
		$errors = $this->validate_reply_title( str_repeat( 'a', bbp_get_title_max_length() + 1 ) );

		$this->assertSame( array( 'bbp_reply_title' ), $errors->get_error_codes() );
		$this->assertSame( '<strong>Error</strong>: Your title is too long.', $errors->get_error_message( 'bbp_reply_title' ) );
	}

	/**
	 * @covers ::bbp_validate_reply_title
	 */
	public function test_bbp_validate_reply_title_preserves_existing_errors() {
		$old_errors        = bbpress()->errors;
		bbpress()->errors = new WP_Error();
		bbp_add_error( 'existing_error', 'Existing error.' );

		try {
			bbp_validate_reply_title( str_repeat( 'a', bbp_get_title_max_length() + 1 ) );
			$this->assertSame( array( 'existing_error', 'bbp_reply_title' ), bbpress()->errors->get_error_codes() );
		} finally {
			bbpress()->errors = $old_errors;
		}
	}
}
