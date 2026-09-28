<?php

/**
 * Tests for password handling in login forms.
 *
 * @group common
 * @group login
 */
class BBP_Tests_Common_Login_Password extends BBP_UnitTestCase {

	/**
	 * The template must not copy request data into its password input.
	 */
	public function test_login_template_does_not_render_requested_password() {
		$request_user_pass = isset( $_REQUEST['user_pass'] ) ? $_REQUEST['user_pass'] : null;
		$_REQUEST['user_pass'] = 'bbpress-secret-test-value';

		try {
			ob_start();
			include BBP_PLUGIN_DIR . 'templates/default/bbpress/form-user-login.php';
			$output = ob_get_clean();

			$this->assertStringNotContainsString( 'bbpress-secret-test-value', $output );
			$this->assertStringContainsString( 'type="password" name="pwd" value=""', $output );
		} finally {
			if ( null === $request_user_pass ) {
				unset( $_REQUEST['user_pass'] );
			} else {
				$_REQUEST['user_pass'] = $request_user_pass;
			}
		}
	}

	/**
	 * The login widget must not copy request data into its password input.
	 */
	public function test_login_widget_does_not_render_requested_password() {
		$request_user_pass = isset( $_REQUEST['user_pass'] ) ? $_REQUEST['user_pass'] : null;
		$_REQUEST['user_pass'] = 'bbpress-secret-test-value';
		wp_set_current_user( 0 );

		try {
			$widget = new BBP_Login_Widget();
			$args   = array(
				'before_widget' => '',
				'after_widget'  => '',
				'before_title'  => '',
				'after_title'   => '',
			);

			ob_start();
			$widget->widget( $args, array() );
			$output = ob_get_clean();

			$this->assertStringNotContainsString( 'bbpress-secret-test-value', $output );
			$this->assertStringContainsString( 'type="password" name="pwd" value=""', $output );
		} finally {
			if ( null === $request_user_pass ) {
				unset( $_REQUEST['user_pass'] );
			} else {
				$_REQUEST['user_pass'] = $request_user_pass;
			}
		}
	}
}
