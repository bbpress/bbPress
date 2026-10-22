<?php

/**
 * Regression tests for data sent to Akismet and saved with forum posts.
 *
 * @group security
 */
class BBP_Tests_Security_Akismet extends BBP_UnitTestCase {

	public function test_akismet_never_retries_over_http_after_https_errors() {
		require_once BBP_PLUGIN_DIR . 'includes/extend/akismet.php';

		$previous = get_option( 'akismet_ssl_disabled', null );
		$disabled = time();
		$requests = array();
		$intercept = function( $pre, $args, $url ) use ( &$requests ) {
			$requests[] = array( $url, $args );
			return new WP_Error( 'test_https_failure' );
		};

		update_option( 'akismet_ssl_disabled', $disabled );
		add_filter( 'pre_http_request', $intercept, 10, 3 );

		try {
			$reflection = new ReflectionClass( 'BBP_Akismet' );
			$akismet    = $reflection->newInstanceWithoutConstructor();
			$method     = $reflection->getMethod( 'get_response' );
			if ( PHP_VERSION_ID < 80100 ) {
				$method->setAccessible( true );
			}

			$result = $method->invoke( $akismet, 'example.invalid/1.1/comment-check', array(
				'body'        => 'Private forum submission',
				'redirection' => 5,
				'sslverify'   => false,
			) );

			$this->assertSame( array( '', '' ), $result );
			$this->assertCount( 2, $requests );
			foreach ( $requests as $request ) {
				$this->assertSame( 'https://example.invalid/1.1/comment-check', $request[0] );
				$this->assertSame( 'Private forum submission', $request[1]['body'] );
				$this->assertSame( 0, $request[1]['redirection'] );
				$this->assertTrue( $request[1]['sslverify'] );
			}
			$this->assertSame( (string) $disabled, (string) get_option( 'akismet_ssl_disabled' ) );
		} finally {
			remove_filter( 'pre_http_request', $intercept, 10 );
			if ( null === $previous ) {
				delete_option( 'akismet_ssl_disabled' );
			} else {
				update_option( 'akismet_ssl_disabled', $previous );
			}
		}
	}

	public function test_akismet_accepts_a_successful_https_retry() {
		require_once BBP_PLUGIN_DIR . 'includes/extend/akismet.php';

		$requests  = array();
		$intercept = function( $pre, $args, $url ) use ( &$requests ) {
			$requests[] = $url;
			return ( 1 === count( $requests ) )
				? new WP_Error( 'test_https_failure' )
				: array( 'headers' => array( 'x-test' => 'ok' ), 'body' => 'false' );
		};
		add_filter( 'pre_http_request', $intercept, 10, 3 );

		try {
			$reflection = new ReflectionClass( 'BBP_Akismet' );
			$akismet    = $reflection->newInstanceWithoutConstructor();
			$method     = $reflection->getMethod( 'get_response' );
			if ( PHP_VERSION_ID < 80100 ) {
				$method->setAccessible( true );
			}

			$this->assertSame(
				array( array( 'x-test' => 'ok' ), 'false' ),
				$method->invoke( $akismet, 'example.invalid/1.1/comment-check', array( 'body' => 'Forum submission' ) )
			);
			$this->assertSame( array(
				'https://example.invalid/1.1/comment-check',
				'https://example.invalid/1.1/comment-check',
			), $requests );
		} finally {
			remove_filter( 'pre_http_request', $intercept, 10 );
		}
	}

	public function test_akismet_receives_unformatted_author_post_count() {
		require_once BBP_PLUGIN_DIR . 'includes/extend/akismet.php';

		$user_id  = $this->factory->user->create();
		$outbound = array();
		$bypass   = function( $skip, $payload ) use ( &$outbound ) {
			$outbound = $payload;
			return true;
		};

		add_filter( 'bbp_bypass_check_for_spam', $bypass, 10, 2 );

		try {
			$reflection = new ReflectionClass( 'BBP_Akismet' );
			$akismet    = $reflection->newInstanceWithoutConstructor();

			foreach ( array( 999, 1000, 1309, 12045 ) as $count ) {
				bbp_update_user_topic_count( $user_id, 309 );
				bbp_update_user_reply_count( $user_id, $count - 309 );

				$akismet->check_post( array(
					'post_author'  => $user_id,
					'post_parent'  => 0,
					'post_title'   => 'Topic',
					'post_content' => 'Body',
					'post_type'    => bbp_get_topic_post_type(),
				) );

				$this->assertSame( $count, $outbound['comment_total'] );
			}
		} finally {
			remove_filter( 'bbp_bypass_check_for_spam', $bypass, 10 );
		}
	}

	public function test_akismet_preserves_plugin_request_fields_without_credential_values() {
		require_once BBP_PLUGIN_DIR . 'includes/extend/akismet.php';

		$user_id = $this->factory->user->create( array(
			'display_name' => 'Account Author',
			'user_email'   => 'author@example.org',
		) );
		$old_post   = $_POST;
		$old_server = $_SERVER;
		$forum_id   = $this->factory->forum->create();
		$topic_id   = $this->factory->topic->create( array( 'post_author' => $user_id, 'post_parent' => $forum_id ) );
		$outbound   = array();
		$bypass     = function( $skip, $payload ) use ( &$outbound ) {
			$outbound = $payload;
			return true;
		};
		$this->set_current_user( $user_id );

		try {
			$_POST['bbp_anonymous_name']  = 'Spoofed Author';
			$_POST['bbp_anonymous_email'] = 'spoofed@example.org';
			$_POST['plugin_honeypot']     = 'plugin-signal';
			$_SERVER['HTTP_AUTHORIZATION'] = 'Bearer server-secret';
			$_SERVER['HTTP_ACCEPT_LANGUAGE'] = 'en-US';
			$_SERVER['HTTP_X_PLUGIN_SIGNAL'] = 'custom-signal';
			$_SERVER['HTTP_X_AUTHORS'] = 'plugin-authors';
			$_SERVER['HTTP_COOKIE2'] = 'session-cookie';
			$_SERVER['DB_PASSWORD'] = 'environment-secret';
			$_SERVER['STRIPE_PRIVATE_KEY'] = 'private-key';
			$_SERVER['AWS_ACCESS_KEY_ID'] = 'access-key';
			add_filter( 'bbp_bypass_check_for_spam', $bypass, 10, 2 );

			$reflection = new ReflectionClass( 'BBP_Akismet' );
			$akismet    = $reflection->newInstanceWithoutConstructor();
			$result     = $akismet->check_post( array(
				'post_author'  => $user_id,
				'post_parent'  => $forum_id,
				'post_title'   => 'Topic',
				'post_content' => 'Body',
				'post_type'    => bbp_get_topic_post_type(),
			) );
			$submitted = $result['bbp_post_as_submitted'];

			$this->assertSame( 'Account Author', $submitted['comment_author'] );
			$this->assertSame( 'author@example.org', $submitted['comment_author_email'] );
			$this->assertSame( 'plugin-signal', $submitted['POST_plugin_honeypot'] );
			$this->assertSame( '', $submitted['HTTP_AUTHORIZATION'] );
			$this->assertSame( 'en-US', $submitted['HTTP_ACCEPT_LANGUAGE'] );
			$this->assertSame( 'custom-signal', $submitted['HTTP_X_PLUGIN_SIGNAL'] );
			$this->assertSame( 'Account Author', $outbound['comment_author'] );
			$this->assertSame( 'plugin-signal', $outbound['POST_plugin_honeypot'] );
			$this->assertSame( 'en-US', $outbound['HTTP_ACCEPT_LANGUAGE'] );
			$this->assertSame( 'custom-signal', $outbound['HTTP_X_PLUGIN_SIGNAL'] );
			$this->assertSame( 'plugin-authors', $outbound['HTTP_X_AUTHORS'] );
			$this->assertSame( '', $outbound['HTTP_AUTHORIZATION'] );
			$this->assertSame( '', $outbound['HTTP_COOKIE2'] );
			$this->assertSame( '', $outbound['DB_PASSWORD'] );
			$this->assertSame( '', $outbound['STRIPE_PRIVATE_KEY'] );
			$this->assertSame( '', $outbound['AWS_ACCESS_KEY_ID'] );

			$akismet->update_post_meta( $topic_id, get_post( $topic_id ) );
			$stored = get_post_meta( $topic_id, '_bbp_akismet_as_submitted', true );
			$this->assertSame( 'Account Author', $stored['comment_author'] );
			$this->assertSame( '', $stored['HTTP_AUTHORIZATION'] );
			$this->assertSame( 'plugin-signal', $stored['POST_plugin_honeypot'] );
			$this->assertSame( 'en-US', $stored['HTTP_ACCEPT_LANGUAGE'] );
			$this->assertSame( 'custom-signal', $stored['HTTP_X_PLUGIN_SIGNAL'] );

			// Reports retain the original fields and current request context.
			$_POST['moderator_action_nonce'] = 'current-action';
			$outbound = array();
			$method = $reflection->getMethod( 'maybe_spam' );
			if ( PHP_VERSION_ID < 80100 ) {
				$method->setAccessible( true );
			}
			$method->invoke( $akismet, $stored, 'submit', 'spam' );
			$this->assertSame( 'plugin-signal', $outbound['POST_plugin_honeypot'] );
			$this->assertSame( 'current-action', $outbound['POST_moderator_action_nonce'] );
			$this->assertSame( 'custom-signal', $outbound['HTTP_X_PLUGIN_SIGNAL'] );

			unset( $_POST['bbp_anonymous_name'], $_POST['bbp_anonymous_email'] );
			bbpress()->errors = new WP_Error();
			$akismet->check_post( array(
				'post_author'  => $user_id,
				'post_parent'  => $forum_id,
				'post_title'   => 'Topic',
				'post_content' => 'Body',
				'post_type'    => bbp_get_topic_post_type(),
			) );
			$this->assertFalse( bbp_has_errors() );
		} finally {
			remove_filter( 'bbp_bypass_check_for_spam', $bypass, 10 );
			$_POST   = $old_post;
			$_SERVER = $old_server;
		}
	}

	public function test_anonymous_akismet_author_remains_available() {
		require_once BBP_PLUGIN_DIR . 'includes/extend/akismet.php';
		$old_post = $_POST;
		$this->set_current_user( 0 );
		bbpress()->errors = new WP_Error();
		add_filter( 'bbp_bypass_check_for_spam', '__return_true' );

		try {
			$_POST['bbp_anonymous_name']  = 'Guest Author';
			$_POST['bbp_anonymous_email'] = 'guest@example.org';
			$reflection = new ReflectionClass( 'BBP_Akismet' );
			$akismet    = $reflection->newInstanceWithoutConstructor();
			$result     = $akismet->check_post( array(
				'post_author'  => 0,
				'post_parent'  => 0,
				'post_title'   => 'Guest topic',
				'post_content' => 'Body',
				'post_type'    => bbp_get_topic_post_type(),
			) );

			$this->assertSame( 'Guest Author', $result['bbp_post_as_submitted']['comment_author'] );
			$this->assertSame( 'guest@example.org', $result['bbp_post_as_submitted']['comment_author_email'] );
			$this->assertSame( 0, $result['bbp_post_as_submitted']['comment_total'] );
		} finally {
			remove_filter( 'bbp_bypass_check_for_spam', '__return_true' );
			$_POST = $old_post;
		}
	}

	public function test_akismet_server_key_filter_can_override_the_default_decision() {
		require_once BBP_PLUGIN_DIR . 'includes/extend/akismet.php';

		$old_server = $_SERVER;
		$outbound   = array();
		$bypass     = function( $skip, $payload ) use ( &$outbound ) {
			$outbound = $payload;
			return true;
		};
		$filter = function( $sensitive, $key ) {
			if ( 'HTTP_X_PLUGIN_SIGNAL' === $key ) {
				return true;
			}

			if ( 'HTTP_X_PLUGIN_NONCE' === $key ) {
				return false;
			}

			return $sensitive;
		};

		try {
			$_SERVER['HTTP_X_PLUGIN_SIGNAL'] = 'ignored-signal';
			$_SERVER['HTTP_X_PLUGIN_NONCE']  = 'allowed-signal';
			add_filter( 'bbp_akismet_is_sensitive_server_key', $filter, 10, 2 );
			add_filter( 'bbp_bypass_check_for_spam', $bypass, 10, 2 );

			$reflection = new ReflectionClass( 'BBP_Akismet' );
			$akismet    = $reflection->newInstanceWithoutConstructor();
			$method     = $reflection->getMethod( 'maybe_spam' );
			if ( PHP_VERSION_ID < 80100 ) {
				$method->setAccessible( true );
			}
			$method->invoke( $akismet, array(), 'check' );

			$this->assertSame( '', $outbound['HTTP_X_PLUGIN_SIGNAL'] );
			$this->assertSame( 'allowed-signal', $outbound['HTTP_X_PLUGIN_NONCE'] );
		} finally {
			remove_filter( 'bbp_akismet_is_sensitive_server_key', $filter, 10 );
			remove_filter( 'bbp_bypass_check_for_spam', $bypass, 10 );
			$_SERVER = $old_server;
		}
	}

	public function test_akismet_marks_edited_topics_and_replies_as_rechecks() {
		foreach ( array( bbp_get_topic_post_type(), bbp_get_reply_post_type() ) as $post_type ) {
			$payload = $this->get_akismet_check_payload( $post_type, true );

			$this->assertSame( 'edit', $payload['recheck_reason'] );
		}
	}

	public function test_akismet_does_not_mark_new_topics_and_replies_as_rechecks() {
		foreach ( array( bbp_get_topic_post_type(), bbp_get_reply_post_type() ) as $post_type ) {
			$payload = $this->get_akismet_check_payload( $post_type, false );

			$this->assertArrayNotHasKey( 'recheck_reason', $payload );
		}
	}

	private function get_akismet_check_payload( $post_type, $is_edit ) {
		require_once BBP_PLUGIN_DIR . 'includes/extend/akismet.php';

		$user_id  = $this->factory->user->create();
		$outbound = array();
		$bypass   = function( $skip, $payload ) use ( &$outbound ) {
			$outbound = $payload;
			return true;
		};
		$post_data = array(
			'post_author'  => $user_id,
			'post_parent'  => 0,
			'post_title'   => 'Forum post',
			'post_content' => 'Body',
			'post_type'    => $post_type,
		);

		if ( $is_edit ) {
			$post_data['ID'] = 123;
		}

		$this->set_current_user( $user_id );
		add_filter( 'bbp_bypass_check_for_spam', $bypass, 10, 2 );

		try {
			$reflection = new ReflectionClass( 'BBP_Akismet' );
			$akismet    = $reflection->newInstanceWithoutConstructor();
			$akismet->check_post( $post_data );
		} finally {
			remove_filter( 'bbp_bypass_check_for_spam', $bypass, 10 );
		}

		return $outbound;
	}

}
