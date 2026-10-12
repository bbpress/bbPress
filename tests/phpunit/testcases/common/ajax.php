<?php

/**
 * Common AJAX helpers.
 *
 * @group common
 */
class BBP_Tests_Common_Ajax extends BBP_UnitTestCase {

	/**
	 * @covers ::bbp_ajax_response
	 * @ticket 3363
	 */
	public function test_response_uses_ajax_die_handler_after_output() {
		$wp_die_ajax_handler = function() {
			return function() {
				throw new Exception( 'AJAX response complete' );
			};
		};

		add_filter( 'wp_doing_ajax', '__return_true' );
		add_filter( 'wp_die_ajax_handler', $wp_die_ajax_handler );

		$buffer_level = ob_get_level();
		$output       = '';
		ob_start();

		try {
			bbp_ajax_response( true, 'Content', -1, array( 'extra' => 'value' ) );
			$this->fail( 'Expected the AJAX response to terminate.' );
		} catch ( Exception $error ) {
			$output = ob_get_clean();
			$this->assertSame( 'AJAX response complete', $error->getMessage() );
		} finally {
			while ( ob_get_level() > $buffer_level ) {
				ob_end_clean();
			}

			remove_filter( 'wp_doing_ajax', '__return_true' );
			remove_filter( 'wp_die_ajax_handler', $wp_die_ajax_handler );
		}

		$this->assertSame(
			'{"success":true,"status":200,"content":"Content","extra":"value"}',
			$output
		);
	}
}
