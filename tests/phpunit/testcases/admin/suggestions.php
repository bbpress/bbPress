<?php

/**
 * Administration suggestion callbacks.
 *
 * @group admin
 */
class BBP_Tests_Admin_Suggestions extends BBP_UnitTestCase {

	private $request = array();

	public function setUp(): void {
		parent::setUp();

		$this->request = $_REQUEST;

		$user_id = $this->factory->user->create( array( 'role' => 'administrator' ) );
		bbp_set_user_role( $user_id, bbp_get_keymaster_role() );
		$this->set_current_user( $user_id );
	}

	public function tearDown(): void {
		$_REQUEST = $this->request;

		parent::tearDown();
	}

	/**
	 * @covers BBP_Admin::suggest_topic
	 * @ticket 3363
	 */
	public function test_suggest_topic_uses_ajax_die_handler_after_output() {
		$forum_id = $this->factory->forum->create();
		$topic_id = $this->factory->topic->create(
			array(
				'post_parent' => $forum_id,
				'post_title'  => 'Needle Topic'
			)
		);

		$_REQUEST = array(
			'q'           => 'Needle',
			'_ajax_nonce' => wp_create_nonce( 'bbp_suggest_topic_nonce' )
		);

		$output = $this->capture_ajax_output( array( bbp_setup_admin(), 'suggest_topic' ) );

		$this->assertSame( $topic_id . " - Needle Topic\n", $output );
	}

	/**
	 * @covers BBP_Admin::suggest_user
	 * @ticket 3363
	 */
	public function test_suggest_user_uses_ajax_die_handler_after_output() {
		$user_id = $this->factory->user->create(
			array(
				'user_login'    => 'needle-user',
				'user_nicename' => 'needle-user'
			)
		);

		$_REQUEST = array(
			'q'           => 'needle',
			'_ajax_nonce' => wp_create_nonce( 'bbp_suggest_user_nonce' )
		);

		$output = $this->capture_ajax_output( array( bbp_setup_admin(), 'suggest_user' ) );

		$this->assertSame( $user_id . " - needle-user\n", $output );
	}

	private function capture_ajax_output( $callback ) {
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
			call_user_func( $callback );
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

		return $output;
	}
}
