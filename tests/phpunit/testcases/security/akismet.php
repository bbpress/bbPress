<?php

/**
 * Regression tests for data sent to Akismet and saved with forum posts.
 *
 * @group security
 */
class BBP_Tests_Security_Akismet extends BBP_UnitTestCase {

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

}
