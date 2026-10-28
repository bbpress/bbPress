<?php

/**
 * Tests for form isolation when composing bbPress shortcodes.
 *
 * @group common
 * @group shortcodes
 */
class BBP_Tests_Common_Shortcode_Form_Isolation extends BBP_UnitTestCase {

	private $template_loader;
	private $old_errors;
	private $old_post;
	private $old_request;
	private $old_request_method;

	public function setUp(): void {
		parent::setUp();

		$this->old_errors         = bbpress()->errors;
		$this->old_post           = $_POST;
		$this->old_request        = $_REQUEST;
		$this->old_request_method = isset( $_SERVER['REQUEST_METHOD'] ) ? $_SERVER['REQUEST_METHOD'] : null;
		bbpress()->errors         = new WP_Error();

		$this->template_loader = function( $located, $template_name, $template_names, $template_locations, $load ) {
			if ( $load && $located ) {
				load_template( $located, false );
			}
		};
		add_action( 'bbp_locate_template', $this->template_loader, 10, 5 );
	}

	public function tearDown(): void {
		remove_action( 'bbp_locate_template', $this->template_loader, 10 );
		$this->set_current_user( 0 );
		bbpress()->errors = $this->old_errors;
		$_POST            = $this->old_post;
		$_REQUEST         = $this->old_request;

		if ( null === $this->old_request_method ) {
			unset( $_SERVER['REQUEST_METHOD'] );
		} else {
			$_SERVER['REQUEST_METHOD'] = $this->old_request_method;
		}

		parent::tearDown();
	}

	/**
	 * @dataProvider shortcode_order_provider
	 */
	public function test_failed_topic_submission_shows_its_error_only_in_topic_form( $content, $error_segment ) {
		$forum_id = $this->factory->forum->create();
		$this->factory->topic->create(
			array(
				'post_parent' => $forum_id,
				'topic_meta'  => array( 'forum_id' => $forum_id ),
			)
		);
		$user_id = $this->factory->user->create();
		bbp_set_user_role( $user_id, bbp_get_keymaster_role() );
		$this->set_current_user( $user_id );

		$_POST                    = array( 'action' => 'bbp-new-topic' );
		$_REQUEST                 = $_POST;
		$_SERVER['REQUEST_METHOD'] = 'POST';
		bbp_post_request();

		$this->assertSame( array( 'bbp_new_topic_nonce' ), bbpress()->errors->get_error_codes() );

		$output   = do_shortcode( sprintf( $content, $forum_id ) );
		$segments = explode( '<!-- split -->', $output );

		$this->assertSame( 1, substr_count( $output, 'Are you sure you wanted to do that?' ) );
		$this->assertStringContainsString( 'Are you sure you wanted to do that?', $segments[ $error_segment ] );
		$this->assertStringNotContainsString( 'Are you sure you wanted to do that?', $segments[ 1 - $error_segment ] );
	}

	public function shortcode_order_provider() {
		return array(
			'forum then form' => array( '[bbp-single-forum id="%d"]<!-- split -->[bbp-forum-form]', 0 ),
			'form then forum' => array( '[bbp-forum-form]<!-- split -->[bbp-single-forum id="%d"]', 1 ),
		);
	}

	public function test_topic_post_does_not_trigger_forum_nonce_verification_during_render() {
		$forum_id = $this->factory->forum->create();
		$user_id  = $this->factory->user->create();
		$checks   = array();
		bbp_set_user_role( $user_id, bbp_get_keymaster_role() );
		$this->set_current_user( $user_id );

		$_POST                    = array( 'action' => 'bbp-new-topic' );
		$_REQUEST                 = $_POST;
		$_SERVER['REQUEST_METHOD'] = 'POST';
		$listener = function( $action, $result ) use ( &$checks ) {
			$checks[] = array( $action, $result );
		};
		add_action( 'bbp_verify_nonce_request', $listener, 10, 2 );

		try {
			do_shortcode( '[bbp-single-forum id="' . $forum_id . '"][bbp-forum-form]' );
		} finally {
			remove_action( 'bbp_verify_nonce_request', $listener, 10 );
		}

		foreach ( $checks as $check ) {
			$this->assertStringNotContainsString( 'bbp-new-forum', $check[0] );
			$this->assertStringNotContainsString( 'bbp-edit-forum', $check[0] );
		}
	}

	/**
	 * @dataProvider form_post_request_provider
	 */
	public function test_form_post_request_accepts_matching_action( $callback, $action, $object_type ) {
		$object_id = 0;
		$filter    = null;

		if ( ! empty( $object_type ) ) {
			if ( 'reply' === $object_type ) {
				$forum_id = $this->factory->forum->create();
				$topic_id = $this->factory->topic->create(
					array(
						'post_parent' => $forum_id,
						'topic_meta'  => array( 'forum_id' => $forum_id ),
					)
				);
				$object_id = $this->factory->reply->create(
					array(
						'post_parent' => $topic_id,
						'reply_meta'  => array( 'forum_id' => $forum_id, 'topic_id' => $topic_id ),
					)
				);
			} else {
				$object_id = $this->factory->{$object_type}->create();
			}
			$filter    = function() use ( $object_id ) {
				return $object_id;
			};
			add_filter( "bbp_get_{$object_type}_id", $filter );
		}

		$nonce_action = $action . ( $object_id ? '_' . $object_id : '' );
		$_POST = array(
			'action'   => $action,
			'_wpnonce' => wp_create_nonce( $nonce_action ),
		);
		$_REQUEST                 = $_POST;
		$_SERVER['REQUEST_METHOD'] = 'POST';

		try {
			$this->assertTrue( call_user_func( $callback ) );
		} finally {
			if ( ! empty( $filter ) ) {
				remove_filter( "bbp_get_{$object_type}_id", $filter );
			}
		}
	}

	public function form_post_request_provider() {
		return array(
			'new forum'  => array( 'bbp_is_forum_form_post_request', 'bbp-new-forum', null ),
			'edit forum' => array( 'bbp_is_forum_form_post_request', 'bbp-edit-forum', 'forum' ),
			'new topic'  => array( 'bbp_is_topic_form_post_request', 'bbp-new-topic', null ),
			'edit topic' => array( 'bbp_is_topic_form_post_request', 'bbp-edit-topic', 'topic' ),
			'new reply'  => array( 'bbp_is_reply_form_post_request', 'bbp-new-reply', null ),
			'edit reply' => array( 'bbp_is_reply_form_post_request', 'bbp-edit-reply', 'reply' ),
		);
	}

	/**
	 * @dataProvider mismatched_form_post_request_provider
	 */
	public function test_form_post_request_ignores_other_actions( $callback, $action ) {
		$checks = array();
		$_POST  = array( 'action' => $action );
		$_REQUEST                 = $_POST;
		$_SERVER['REQUEST_METHOD'] = 'POST';
		$listener = function( $nonce_action, $result ) use ( &$checks ) {
			$checks[] = array( $nonce_action, $result );
		};
		add_action( 'bbp_verify_nonce_request', $listener, 10, 2 );

		try {
			$this->assertFalse( call_user_func( $callback ) );
		} finally {
			remove_action( 'bbp_verify_nonce_request', $listener, 10 );
		}

		$this->assertSame( array(), $checks );
	}

	public function mismatched_form_post_request_provider() {
		return array(
			'forum helper on topic action' => array( 'bbp_is_forum_form_post_request', 'bbp-new-topic' ),
			'topic helper on reply action' => array( 'bbp_is_topic_form_post_request', 'bbp-new-reply' ),
			'reply helper on forum action' => array( 'bbp_is_reply_form_post_request', 'bbp-new-forum' ),
		);
	}

	public function test_get_post_action_sanitizes_post_value() {
		$_POST = array( 'action' => 'bbp-New_Topic!' );

		$this->assertSame( 'bbp-new_topic', bbp_get_post_action() );
	}

	public function test_get_post_action_rejects_array_value() {
		$_POST = array( 'action' => array( 'bbp-new-topic' ) );

		$this->assertSame( '', bbp_get_post_action() );
	}

	public function test_get_post_action_does_not_read_request_value() {
		$_POST    = array();
		$_REQUEST = array( 'action' => 'bbp-new-topic' );

		$this->assertSame( '', bbp_get_post_action() );
	}

	/**
	 * @dataProvider post_action_provider
	 */
	public function test_is_post_action_matches_expected_actions( $posted_action, $expected_action, $expected ) {
		$_POST                    = array( 'action' => $posted_action );
		$_SERVER['REQUEST_METHOD'] = 'POST';

		$this->assertSame( $expected, bbp_is_post_action( $expected_action ) );
	}

	public function post_action_provider() {
		return array(
			'single action'      => array( 'bbp-new-forum', 'bbp-new-forum', true ),
			'action array'       => array( 'bbp-edit-topic', array( 'bbp-new-topic', 'bbp-edit-topic' ), true ),
			'mismatched action'  => array( 'bbp-new-topic', 'bbp-new-forum', false ),
			'other bbPress form' => array( 'bbp-update-user', 'bbp-update-user', true ),
		);
	}

	public function test_is_post_action_rejects_get_request() {
		$_POST                    = array( 'action' => 'bbp-new-topic' );
		$_SERVER['REQUEST_METHOD'] = 'GET';

		$this->assertFalse( bbp_is_post_action( 'bbp-new-topic' ) );
	}

	public function test_is_post_action_rejects_empty_action() {
		$_POST                    = array();
		$_SERVER['REQUEST_METHOD'] = 'POST';

		$this->assertFalse( bbp_is_post_action() );
	}

	/**
	 * @covers ::bbp_get_template_notice_context
	 *
	 * @dataProvider notice_context_provider
	 */
	public function test_get_template_notice_context_matches_post_action( $action, $matching_context ) {
		$_POST                    = array( 'action' => $action );
		$_SERVER['REQUEST_METHOD'] = 'POST';

		$this->assertSame( $matching_context, bbp_get_template_notice_context() );
	}

	public function test_get_template_notice_context_rejects_get_request() {
		$_POST                    = array( 'action' => 'bbp-new-topic' );
		$_SERVER['REQUEST_METHOD'] = 'GET';

		$this->assertSame( '', bbp_get_template_notice_context() );
	}

	public function test_get_template_notice_context_returns_empty_for_unknown_action() {
		$_POST                    = array( 'action' => 'bbp-update-user' );
		$_SERVER['REQUEST_METHOD'] = 'POST';

		$this->assertSame( '', bbp_get_template_notice_context() );
	}

	public function test_get_template_notice_context_is_filterable() {
		$_POST                    = array( 'action' => 'plugin-new-topic' );
		$_SERVER['REQUEST_METHOD'] = 'POST';
		$filter = function( $context, $action ) {
			return ( 'plugin-new-topic' === $action ) ? 'topic' : $context;
		};
		add_filter( 'bbp_get_template_notice_context', $filter, 10, 2 );

		try {
			$this->assertSame( 'topic', bbp_get_template_notice_context() );
		} finally {
			remove_filter( 'bbp_get_template_notice_context', $filter, 10 );
		}
	}

	/**
	 * @covers ::bbp_is_template_notice_context
	 */
	public function test_is_template_notice_context_matches_expected_contexts() {
		$_POST                    = array( 'action' => 'bbp-new-topic' );
		$_SERVER['REQUEST_METHOD'] = 'POST';

		$this->assertTrue( bbp_is_template_notice_context() );
		$this->assertTrue( bbp_is_template_notice_context( 'topic' ) );
		$this->assertTrue( bbp_is_template_notice_context( array( 'forum', 'topic' ) ) );
		$this->assertFalse( bbp_is_template_notice_context( 'forum' ) );
	}

	public function test_is_template_notice_context_preserves_unknown_action_output() {
		$_POST                    = array( 'action' => 'bbp-update-user' );
		$_SERVER['REQUEST_METHOD'] = 'POST';

		$this->assertTrue( bbp_is_template_notice_context( 'forum' ) );
	}

	public function test_is_template_notice_context_preserves_non_post_output() {
		$_POST                    = array( 'action' => 'bbp-new-topic' );
		$_SERVER['REQUEST_METHOD'] = 'GET';

		$this->assertTrue( bbp_is_template_notice_context( 'forum' ) );
	}

	/**
	 * @dataProvider notice_context_provider
	 */
	public function test_template_notices_only_render_in_matching_form_context( $action, $matching_context ) {
		bbp_add_error( 'form_error', 'Form isolation sentinel' );
		$_POST                    = array( 'action' => $action );
		$_SERVER['REQUEST_METHOD'] = 'POST';

		foreach ( array( 'forum', 'topic', 'reply' ) as $context ) {
			ob_start();
			bbp_template_notices( $context );
			$output = ob_get_clean();

			if ( $matching_context === $context ) {
				$this->assertStringContainsString( 'Form isolation sentinel', $output );
			} else {
				$this->assertStringNotContainsString( 'Form isolation sentinel', $output );
			}
		}
	}

	public function notice_context_provider() {
		return array(
			'new forum action'  => array( 'bbp-new-forum', 'forum' ),
			'edit forum action' => array( 'bbp-edit-forum', 'forum' ),
			'new topic action'  => array( 'bbp-new-topic', 'topic' ),
			'edit topic action' => array( 'bbp-edit-topic', 'topic' ),
			'new reply action'  => array( 'bbp-new-reply', 'reply' ),
			'edit reply action' => array( 'bbp-edit-reply', 'reply' ),
		);
	}

	public function test_template_notices_without_context_preserve_legacy_output() {
		bbp_add_error( 'legacy_error', 'Legacy template sentinel' );
		$_POST                    = array( 'action' => 'bbp-new-topic' );
		$_SERVER['REQUEST_METHOD'] = 'POST';

		ob_start();
		bbp_template_notices();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'Legacy template sentinel', $output );
	}

	public function test_template_notices_preserve_unknown_action_output() {
		bbp_add_error( 'other_error', 'Other action sentinel' );
		$_POST                    = array( 'action' => 'bbp-update-user' );
		$_SERVER['REQUEST_METHOD'] = 'POST';

		ob_start();
		bbp_template_notices( 'forum' );
		$output = ob_get_clean();

		$this->assertStringContainsString( 'Other action sentinel', $output );
	}
}
