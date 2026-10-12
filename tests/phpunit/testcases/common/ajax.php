<?php

class BBP_Tests_Common_Ajax_Die_Exception extends Exception {
	public $args;

	public function __construct( $message, $title, $args ) {
		parent::__construct( (string) $message );
		$this->args = $args;
	}
}

/**
 * Tests for common AJAX functions.
 */
class BBP_Tests_Common_Ajax extends BBP_UnitTestCase {
	private $old_get;
	private $old_post;
	private $old_request;
	private $old_wp_request;

	public function setUp(): void {
		parent::setUp();

		global $wp;

		$this->old_get        = $_GET;
		$this->old_post       = $_POST;
		$this->old_request    = $_REQUEST;
		$this->old_wp_request = $wp->request;
	}

	public function tearDown(): void {
		global $wp;

		$_GET        = $this->old_get;
		$_POST       = $this->old_post;
		$_REQUEST    = $this->old_request;
		$wp->request = $this->old_wp_request;

		parent::tearDown();
	}

	/**
	 * @covers ::bbp_get_ajax_url
	 */
	public function test_get_ajax_url_uses_current_request_and_filter() {
		global $wp;

		$wp->request = 'forums/example';
		$expected    = add_query_arg( 'bbp-ajax', 'true', home_url( '/forums/example/' ) );

		$this->assertSame( $expected, bbp_get_ajax_url() );

		$filter = function( $url ) use ( $expected ) {
			$this->assertSame( $expected, $url );
			return $url . '#filtered';
		};
		add_filter( 'bbp_get_ajax_url', $filter );

		try {
			$this->assertSame( $expected . '#filtered', bbp_get_ajax_url() );
		} finally {
			remove_filter( 'bbp_get_ajax_url', $filter );
		}
	}

	/**
	 * @covers ::bbp_get_ajax_url
	 */
	public function test_get_ajax_url_uses_home_for_empty_request() {
		global $wp;

		$wp->request = '';

		$this->assertSame(
			add_query_arg( 'bbp-ajax', 'true', home_url( '/' ) ),
			bbp_get_ajax_url()
		);
	}

	/**
	 * @covers ::bbp_ajax_url
	 * @covers ::bbp_get_ajax_url
	 */
	public function test_ajax_url_outputs_escaped_url() {
		$url      = 'http://example.org/?first=1&second="quoted"';
		$expected = esc_url( $url );
		$filter   = function() use ( $url ) {
			return $url;
		};
		add_filter( 'bbp_get_ajax_url', $filter );

		try {
			$this->assertNotSame( $url, $expected );
			$this->expectOutputString( $expected );
			bbp_ajax_url();
		} finally {
			remove_filter( 'bbp_get_ajax_url', $filter );
		}
	}

	/**
	 * @covers ::bbp_is_ajax
	 * @dataProvider ajax_request_provider
	 */
	public function test_is_ajax_checks_transport_flag_and_action( $get, $post, $request, $expected ) {
		$_GET     = $get;
		$_POST    = $post;
		$_REQUEST = $request;

		$this->assertSame( $expected, bbp_is_ajax() );
	}

	public static function ajax_request_provider() {
		return array(
			'no request data'          => array( array(), array(), array(), false ),
			'action without flag'      => array( array(), array(), array( 'action' => 'subscribe' ), false ),
			'GET flag without action'  => array( array( 'bbp-ajax' => 'true' ), array(), array(), false ),
			'POST flag without action' => array( array(), array( 'bbp-ajax' => 'true' ), array(), false ),
			'GET request'              => array( array( 'bbp-ajax' => 'true', 'action' => 'subscribe' ), array(), array( 'bbp-ajax' => 'true', 'action' => 'subscribe' ), true ),
			'POST request'             => array( array(), array( 'bbp-ajax' => 'true', 'action' => 'subscribe' ), array( 'bbp-ajax' => 'true', 'action' => 'subscribe' ), true ),
			'empty transport flag is present' => array( array( 'bbp-ajax' => '' ), array(), array( 'action' => 'subscribe' ), true ),
			'request-only flag'        => array( array(), array(), array( 'bbp-ajax' => 'true', 'action' => 'subscribe' ), false ),
			'empty action'             => array( array( 'bbp-ajax' => 'true' ), array(), array( 'action' => '' ), false ),
			'zero action is empty'     => array( array( 'bbp-ajax' => 'true' ), array(), array( 'action' => '0' ), false ),
		);
	}

	/**
	 * @covers ::bbp_do_ajax
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_do_ajax_ignores_non_ajax_request() {
		$_GET     = array();
		$_POST    = array();
		$_REQUEST = array( 'action' => 'test_ignored' );
		$called   = false;
		$callback = function() use ( &$called ) {
			$called = true;
		};
		$handler = $this->get_die_handler_filter();
		add_action( 'bbp_ajax_test_ignored', $callback );
		add_filter( 'wp_die_ajax_handler', $handler );

		try {
			$this->assertNull( bbp_do_ajax() );
			$this->assertFalse( $called );
		} catch ( BBP_Tests_Common_Ajax_Die_Exception $error ) {
			$this->fail( 'A non-AJAX request unexpectedly terminated.' );
		} finally {
			remove_action( 'bbp_ajax_test_ignored', $callback );
			remove_filter( 'wp_die_ajax_handler', $handler );
		}
	}

	/**
	 * @covers ::bbp_do_ajax
	 * @covers ::bbp_is_ajax
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_do_ajax_rejects_unregistered_action() {
		$_GET     = array( 'bbp-ajax' => 'true' );
		$_POST    = array();
		$_REQUEST = array( 'action' => 'missing-action' );
		$status        = null;
		$handler       = $this->get_die_handler_filter();
		$status_filter = function( $status_header, $code ) use ( &$status ) {
			$status = $code;
			return $status_header;
		};
		add_filter( 'status_header', $status_filter, 10, 2 );
		add_filter( 'wp_die_ajax_handler', $handler );

		try {
			$this->without_header_warnings( 'bbp_do_ajax' );
			$this->fail( 'Expected an unregistered AJAX action to terminate the request.' );
		} catch ( BBP_Tests_Common_Ajax_Die_Exception $error ) {
			$this->assertSame( '0', $error->getMessage() );
			$this->assertSame( 400, $error->args['response'] );
			$this->assertNull( $status );
			$this->assertTrue( wp_doing_ajax() );
		} finally {
			remove_filter( 'status_header', $status_filter, 10 );
			remove_filter( 'wp_die_ajax_handler', $handler );
		}
	}

	/**
	 * @covers ::bbp_do_ajax
	 * @covers ::bbp_is_ajax
	 * @dataProvider invalid_request_action_provider
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_do_ajax_rejects_action_that_sanitizes_to_empty( $action ) {
		$_GET     = array( 'bbp-ajax' => 'true' );
		$_POST    = array();
		$_REQUEST = array( 'action' => $action );
		$handler  = $this->get_die_handler_filter();
		add_filter( 'wp_die_ajax_handler', $handler );

		try {
			$this->without_header_warnings( 'bbp_do_ajax' );
			$this->fail( 'Expected an empty sanitized action to terminate the request.' );
		} catch ( BBP_Tests_Common_Ajax_Die_Exception $error ) {
			$this->assertSame( '0', $error->getMessage() );
			$this->assertSame( 400, $error->args['response'] );
		} finally {
			remove_filter( 'wp_die_ajax_handler', $handler );
		}
	}

	public static function invalid_request_action_provider() {
		return array(
			'punctuation' => array( '!!!' ),
			'array'       => array( array( 'invalid' ) ),
		);
	}

	/**
	 * @covers ::bbp_do_ajax
	 * @covers ::bbp_is_ajax
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_do_ajax_sanitizes_request_action() {
		$_GET     = array( 'bbp-ajax' => 'true' );
		$_POST    = array();
		$_REQUEST = array( 'action' => 'Test Dispatch!' );
		$called   = false;
		$callback = function() use ( &$called ) {
			$called = true;
		};
		$handler  = $this->get_die_handler_filter();

		add_action( 'bbp_ajax_testdispatch', $callback );
		add_filter( 'wp_die_ajax_handler', $handler );

		try {
			$this->without_header_warnings( 'bbp_do_ajax' );
			$this->fail( 'Expected a completed AJAX action to terminate the request.' );
		} catch ( BBP_Tests_Common_Ajax_Die_Exception $error ) {
			$this->assertSame( '0', $error->getMessage() );
			$this->assertTrue( $called );
		} finally {
			remove_action( 'bbp_ajax_testdispatch', $callback );
			remove_filter( 'wp_die_ajax_handler', $handler );
		}
	}

	/**
	 * @covers ::bbp_do_ajax
	 * @covers ::bbp_is_ajax
	 * @covers ::bbp_set_200
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_do_ajax_dispatches_registered_action_and_sets_success_status() {
		$_GET     = array();
		$_POST    = array( 'bbp-ajax' => 'true' );
		$_REQUEST = array( 'action' => 'test-dispatch' );
		$called   = 0;
		$status   = null;
		$callback = function() use ( &$called, &$status ) {
			$this->assertSame( 200, $status );
			++$called;
		};
		$status_filter = function( $status_header, $code ) use ( &$status ) {
			$status = $code;
			return $status_header;
		};
		$handler       = $this->get_die_handler_filter();

		add_action( 'bbp_ajax_test-dispatch', $callback );
		add_filter( 'status_header', $status_filter, 10, 2 );
		add_filter( 'wp_die_ajax_handler', $handler );

		try {
			$this->without_header_warnings( 'bbp_do_ajax' );
			$this->fail( 'Expected a completed AJAX action to terminate the request.' );
		} catch ( BBP_Tests_Common_Ajax_Die_Exception $error ) {
			$this->assertSame( '0', $error->getMessage() );
			$this->assertSame( 1, $called );
			$this->assertSame( 200, $status );
			$this->assertTrue( wp_doing_ajax() );
		} finally {
			remove_action( 'bbp_ajax_test-dispatch', $callback );
			remove_filter( 'status_header', $status_filter, 10 );
			remove_filter( 'wp_die_ajax_handler', $handler );
		}
	}

	/**
	 * @covers ::bbp_do_ajax
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_do_ajax_prefers_explicit_action_argument() {
		$_GET              = array( 'bbp-ajax' => 'true' );
		$_POST             = array();
		$_REQUEST          = array( 'action' => 'request-action' );
		$called            = array();
		$request_callback  = function() use ( &$called ) {
			$called[] = 'request';
		};
		$explicit_callback = function() use ( &$called ) {
			$called[] = 'explicit';
		};
		$handler           = $this->get_die_handler_filter();

		add_action( 'bbp_ajax_request-action', $request_callback );
		add_action( 'bbp_ajax_explicit-action', $explicit_callback );
		add_filter( 'wp_die_ajax_handler', $handler );

		try {
			$this->without_header_warnings(
				function() {
					bbp_do_ajax( 'explicit-action' );
				}
			);
			$this->fail( 'Expected a completed AJAX action to terminate the request.' );
		} catch ( BBP_Tests_Common_Ajax_Die_Exception $error ) {
			$this->assertSame( array( 'explicit' ), $called );
		} finally {
			remove_action( 'bbp_ajax_request-action', $request_callback );
			remove_action( 'bbp_ajax_explicit-action', $explicit_callback );
			remove_filter( 'wp_die_ajax_handler', $handler );
		}
	}

	/**
	 * @covers ::bbp_ajax_response
	 * @ticket 3363
	 */
	public function test_ajax_response_uses_ajax_die_handler_after_output() {
		$handler = $this->get_die_handler_filter();
		add_filter( 'wp_doing_ajax', '__return_true' );
		add_filter( 'wp_die_ajax_handler', $handler );

		$level = ob_get_level();
		ob_start();

		try {
			$this->without_header_warnings(
				function() {
					bbp_ajax_response( true, 'Content', -1, array( 'extra' => 'value' ) );
				}
			);
			$this->fail( 'Expected the AJAX response to terminate.' );
		} catch ( BBP_Tests_Common_Ajax_Die_Exception $error ) {
			$output = ob_get_clean();
			$this->assertSame( '', $error->getMessage() );
			$this->assertSame(
				'{"success":true,"status":200,"content":"Content","extra":"value"}',
				$output
			);
		} finally {
			while ( ob_get_level() > $level ) {
				ob_end_clean();
			}

			remove_filter( 'wp_doing_ajax', '__return_true' );
			remove_filter( 'wp_die_ajax_handler', $handler );
		}
	}

	private function get_die_handler_filter() {
		return function() {
			return function( $message, $title, $args ) {
				throw new BBP_Tests_Common_Ajax_Die_Exception( $message, $title, $args );
			};
		};
	}

	private function without_header_warnings( $callback ) {
		set_error_handler(
			function( $error_level, $message ) {
				return ( E_WARNING === $error_level ) && ( false !== strpos( $message, 'Cannot modify header information' ) );
			},
			E_WARNING
		);

		try {
			return call_user_func( $callback );
		} finally {
			restore_error_handler();
		}
	}
}
