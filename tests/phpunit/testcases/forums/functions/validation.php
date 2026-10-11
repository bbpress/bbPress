<?php

/**
 * Tests for the forum component validation functions.
 *
 * @group forums
 * @group functions
 * @group validation
 */
class BBP_Tests_Forums_Functions_Validation extends BBP_UnitTestCase {

	protected function validate_forum_title( $forum_title = '', $empty_error_code = 'bbp_forum_title' ) {
		$old_errors        = bbpress()->errors;
		bbpress()->errors = new WP_Error();

		try {
			bbp_validate_forum_title( $forum_title, $empty_error_code );
			return bbpress()->errors;
		} finally {
			bbpress()->errors = $old_errors;
		}
	}

	/**
	 * @covers ::bbp_validate_forum_title
	 */
	public function test_bbp_validate_forum_title_accepts_valid_title() {
		$errors = $this->validate_forum_title( 'Valid forum title' );

		$this->assertSame( array(), $errors->get_error_codes() );
	}

	/**
	 * @covers ::bbp_validate_forum_title
	 */
	public function test_bbp_validate_forum_title_rejects_empty_title() {
		$errors = $this->validate_forum_title();

		$this->assertSame( array( 'bbp_forum_title' ), $errors->get_error_codes() );
		$this->assertSame( '<strong>Error</strong>: Your forum needs a title.', $errors->get_error_message( 'bbp_forum_title' ) );
	}

	/**
	 * @covers ::bbp_validate_forum_title
	 */
	public function test_bbp_validate_forum_title_accepts_empty_error_code() {
		$errors = $this->validate_forum_title( '', 'bbp_edit_forum_title' );

		$this->assertSame( array( 'bbp_edit_forum_title' ), $errors->get_error_codes() );
		$this->assertSame( '<strong>Error</strong>: Your forum needs a title.', $errors->get_error_message( 'bbp_edit_forum_title' ) );
	}

	/**
	 * @covers ::bbp_validate_forum_title
	 */
	public function test_bbp_validate_forum_title_rejects_long_title() {
		$errors = $this->validate_forum_title( str_repeat( 'a', bbp_get_title_max_length() + 1 ) );

		$this->assertSame( array( 'bbp_forum_title' ), $errors->get_error_codes() );
		$this->assertSame( '<strong>Error</strong>: Your title is too long.', $errors->get_error_message( 'bbp_forum_title' ) );
	}

	/**
	 * @covers ::bbp_validate_forum_title
	 */
	public function test_bbp_validate_forum_title_preserves_existing_errors() {
		$old_errors        = bbpress()->errors;
		bbpress()->errors = new WP_Error();
		bbp_add_error( 'existing_error', 'Existing error.' );

		try {
			bbp_validate_forum_title();
			$this->assertSame( array( 'existing_error', 'bbp_forum_title' ), bbpress()->errors->get_error_codes() );
		} finally {
			bbpress()->errors = $old_errors;
		}
	}
}
