<?php

/**
 * Tests for core sub-action and filter wrappers.
 *
 * @group core
 * @group actions
 */
class BBP_Tests_Core_Sub_Actions extends BBP_UnitTestCase {

	private $get;
	private $post;
	private $request_method;

	public function setUp(): void {
		parent::setUp();

		$this->get            = $_GET;
		$this->post           = $_POST;
		$this->request_method = isset( $_SERVER['REQUEST_METHOD'] )
			? $_SERVER['REQUEST_METHOD']
			: null;
	}

	public function tearDown(): void {
		$_GET  = $this->get;
		$_POST = $this->post;

		if ( null === $this->request_method ) {
			unset( $_SERVER['REQUEST_METHOD'] );
		} else {
			$_SERVER['REQUEST_METHOD'] = $this->request_method;
		}

		parent::tearDown();
	}

	/**
	 * Assert that a wrapper dispatches its matching action with all arguments.
	 *
	 * @param string $function Wrapper function name.
	 * @param string $hook     Action hook name.
	 * @param array  $args     Wrapper arguments.
	 */
	private function assert_action_wrapper( $function, $hook, $args = array() ) {
		global $wp_actions, $wp_filter;

		$had_filter = isset( $wp_filter[ $hook ] );
		$filter     = $had_filter ? $wp_filter[ $hook ] : null;
		$had_count  = isset( $wp_actions[ $hook ] );
		$count      = $had_count ? $wp_actions[ $hook ] : null;
		$received   = null;

		$wp_filter[ $hook ] = new WP_Hook();
		add_action(
			$hook,
			function() use ( &$received ) {
				$received = func_get_args();
			},
			10,
			empty( $args ) ? 0 : PHP_INT_MAX
		);

		try {
			call_user_func_array( $function, $args );
		} finally {
			if ( $had_filter ) {
				$wp_filter[ $hook ] = $filter;
			} else {
				unset( $wp_filter[ $hook ] );
			}

			if ( $had_count ) {
				$wp_actions[ $hook ] = $count;
			} else {
				unset( $wp_actions[ $hook ] );
			}
		}

		$this->assertSame( $args, $received );
	}

	/**
	 * @covers ::bbp_activation
	 * @covers ::bbp_deactivation
	 * @covers ::bbp_uninstall
	 * @covers ::bbp_loaded
	 * @covers ::bbp_constants
	 * @covers ::bbp_boot_strap_globals
	 * @covers ::bbp_includes
	 * @covers ::bbp_setup_globals
	 * @covers ::bbp_register
	 * @covers ::bbp_init
	 * @covers ::bbp_rest_api_init
	 * @covers ::bbp_widgets_init
	 * @covers ::bbp_setup_current_user
	 * @covers ::bbp_setup_engagements
	 * @covers ::bbp_load_textdomain
	 * @covers ::bbp_register_post_types
	 * @covers ::bbp_register_post_statuses
	 * @covers ::bbp_register_taxonomies
	 * @covers ::bbp_register_views
	 * @covers ::bbp_register_shortcodes
	 * @covers ::bbp_register_blocks
	 * @covers ::bbp_register_meta
	 * @covers ::bbp_enqueue_scripts
	 * @covers ::bbp_add_rewrite_tags
	 * @covers ::bbp_add_rewrite_rules
	 * @covers ::bbp_add_permastructs
	 * @covers ::bbp_login_form_login
	 * @covers ::bbp_ready
	 * @covers ::bbp_template_redirect
	 * @covers ::bbp_register_theme_packages
	 * @covers ::bbp_setup_theme
	 * @covers ::bbp_after_setup_theme
	 * @dataProvider action_wrapper_provider
	 */
	public function test_action_wrappers_dispatch_matching_hooks( $function ) {
		$this->assert_action_wrapper( $function, $function );
	}

	public static function action_wrapper_provider() {
		$functions = array(
			'bbp_activation',
			'bbp_deactivation',
			'bbp_uninstall',
			'bbp_loaded',
			'bbp_constants',
			'bbp_boot_strap_globals',
			'bbp_includes',
			'bbp_setup_globals',
			'bbp_register',
			'bbp_init',
			'bbp_rest_api_init',
			'bbp_widgets_init',
			'bbp_setup_current_user',
			'bbp_setup_engagements',
			'bbp_load_textdomain',
			'bbp_register_post_types',
			'bbp_register_post_statuses',
			'bbp_register_taxonomies',
			'bbp_register_views',
			'bbp_register_shortcodes',
			'bbp_register_blocks',
			'bbp_register_meta',
			'bbp_enqueue_scripts',
			'bbp_add_rewrite_tags',
			'bbp_add_rewrite_rules',
			'bbp_add_permastructs',
			'bbp_login_form_login',
			'bbp_ready',
			'bbp_template_redirect',
			'bbp_register_theme_packages',
			'bbp_setup_theme',
			'bbp_after_setup_theme',
		);

		$data = array();
		foreach ( $functions as $function ) {
			$data[ $function ] = array( $function );
		}

		return $data;
	}

	/**
	 * @covers ::bbp_xmlrpc_call
	 * @covers ::bbp_roles_init
	 * @covers ::bbp_profile_update
	 * @covers ::bbp_user_register
	 * @covers ::bbp_generate_rewrite_rules
	 * @dataProvider argument_action_wrapper_provider
	 */
	public function test_action_wrappers_forward_arguments( $function, $args ) {
		$this->assert_action_wrapper( $function, $function, $args );
	}

	public static function argument_action_wrapper_provider() {
		$server        = new stdClass();
		$roles         = new stdClass();
		$old_user_data = array( 'display_name' => 'Old Name' );
		$rewrite       = new stdClass();

		return array(
			'XML-RPC call'           => array( 'bbp_xmlrpc_call', array( 'wp.editPost', array( 7, 11 ), $server ) ),
			'roles init'             => array( 'bbp_roles_init', array( $roles ) ),
			'profile update'         => array( 'bbp_profile_update', array( 17, $old_user_data ) ),
			'user register'          => array( 'bbp_user_register', array( 23 ) ),
			'generate rewrite rules' => array( 'bbp_generate_rewrite_rules', array( $rewrite ) ),
		);
	}

	/**
	 * @covers ::bbp_post_request
	 */
	public function test_post_request_dispatches_sanitized_dynamic_and_static_actions() {
		$_SERVER['REQUEST_METHOD'] = 'POST';
		$_POST['action']           = 'Edit-Topic!';
		$calls                     = array();
		$static_action             = '';
		$dynamic                   = function() use ( &$calls ) {
			$calls[] = 'dynamic';
		};
		$static                    = function( $action ) use ( &$calls, &$static_action ) {
			$calls[]       = 'static';
			$static_action = $action;
		};

		add_action( 'bbp_post_request_edit-topic', $dynamic );
		add_action( 'bbp_post_request', $static );
		try {
			bbp_post_request();
		} finally {
			remove_action( 'bbp_post_request_edit-topic', $dynamic );
			remove_action( 'bbp_post_request', $static );
		}

		$this->assertSame( array( 'dynamic', 'static' ), $calls );
		$this->assertSame( 'edit-topic', $static_action );
	}

	/**
	 * @covers ::bbp_post_request
	 */
	public function test_post_request_ignores_wrong_methods_and_invalid_actions() {
		$calls    = 0;
		$callback = function() use ( &$calls ) {
			++$calls;
		};

		add_action( 'bbp_post_request', $callback );
		try {
			$_SERVER['REQUEST_METHOD'] = 'GET';
			$_POST['action']           = 'test';
			bbp_post_request();

			$_SERVER['REQUEST_METHOD'] = 'POST';
			unset( $_POST['action'] );
			bbp_post_request();

			$_POST['action'] = array( 'test' );
			bbp_post_request();

			$_POST['action'] = '!!!';
			bbp_post_request();
		} finally {
			remove_action( 'bbp_post_request', $callback );
		}

		$this->assertSame( 0, $calls );
	}

	/**
	 * @covers ::bbp_get_request
	 */
	public function test_get_request_dispatches_sanitized_dynamic_and_static_actions() {
		$_SERVER['REQUEST_METHOD'] = 'GET';
		$_GET['action']            = 'View-Topic!';
		$calls                     = array();
		$static_action             = '';
		$dynamic                   = function() use ( &$calls ) {
			$calls[] = 'dynamic';
		};
		$static                    = function( $action ) use ( &$calls, &$static_action ) {
			$calls[]       = 'static';
			$static_action = $action;
		};

		add_action( 'bbp_get_request_view-topic', $dynamic );
		add_action( 'bbp_get_request', $static );
		try {
			bbp_get_request();
		} finally {
			remove_action( 'bbp_get_request_view-topic', $dynamic );
			remove_action( 'bbp_get_request', $static );
		}

		$this->assertSame( array( 'dynamic', 'static' ), $calls );
		$this->assertSame( 'view-topic', $static_action );
	}

	/**
	 * @covers ::bbp_get_request
	 */
	public function test_get_request_ignores_wrong_methods_and_invalid_actions() {
		$calls    = 0;
		$callback = function() use ( &$calls ) {
			++$calls;
		};

		add_action( 'bbp_get_request', $callback );
		try {
			$_SERVER['REQUEST_METHOD'] = 'POST';
			$_GET['action']            = 'test';
			bbp_get_request();

			$_SERVER['REQUEST_METHOD'] = 'GET';
			unset( $_GET['action'] );
			bbp_get_request();

			$_GET['action'] = array( 'test' );
			bbp_get_request();

			$_GET['action'] = '!!!';
			bbp_get_request();
		} finally {
			remove_action( 'bbp_get_request', $callback );
		}

		$this->assertSame( 0, $calls );
	}

	/**
	 * @covers ::bbp_transition_post_status
	 */
	public function test_transition_post_status_only_dispatches_for_bbp_posts() {
		$topic_id = $this->factory->topic->create();
		$post_id  = self::factory()->post->create();
		$calls    = array();
		$callback = function( $new_status, $old_status, $post ) use ( &$calls ) {
			$calls[] = array( $new_status, $old_status, $post->ID );
		};

		add_action( 'bbp_transition_post_status', $callback, 999, 3 );
		try {
			bbp_transition_post_status( 'publish', 'draft', get_post( $post_id ) );
			bbp_transition_post_status( 'closed', 'publish', get_post( $topic_id ) );
		} finally {
			remove_action( 'bbp_transition_post_status', $callback, 999 );
		}

		$this->assertSame( array( array( 'closed', 'publish', $topic_id ) ), $calls );
	}

	/**
	 * @covers ::bbp_post_updated
	 */
	public function test_post_updated_dispatches_when_either_version_is_a_bbp_post() {
		$topic_id = $this->factory->topic->create();
		$post_id  = self::factory()->post->create();
		$topic    = get_post( $topic_id );
		$post     = get_post( $post_id );
		$calls    = array();
		$callback = function( $updated_id, $after, $before ) use ( &$calls ) {
			$calls[] = array( $updated_id, $after->post_type, $before->post_type );
		};

		add_action( 'bbp_post_updated', $callback, 999, 3 );
		try {
			bbp_post_updated( $post_id, $post, $post );
			bbp_post_updated( $topic_id, $topic, $post );
			bbp_post_updated( $topic_id, $post, $topic );
		} finally {
			remove_action( 'bbp_post_updated', $callback, 999 );
		}

		$this->assertSame(
			array(
				array( $topic_id, bbp_get_topic_post_type(), 'post' ),
				array( $topic_id, 'post', bbp_get_topic_post_type() ),
			),
			$calls
		);
	}

	/**
	 * @covers ::bbp_plugin_locale
	 * @covers ::bbp_request
	 * @covers ::bbp_template_include
	 * @covers ::bbp_allowed_themes
	 * @covers ::bbp_map_meta_caps
	 * @covers ::bbp_redirect_canonical
	 */
	public function test_filter_wrappers_forward_values_and_arguments() {
		$filters = array(
			'bbp_plugin_locale'     => function( $locale, $domain ) {
				return $domain . '-' . $locale;
			},
			'bbp_request'           => function( $query_vars ) {
				$query_vars['bbp'] = true;
				return $query_vars;
			},
			'bbp_template_include'  => function( $template ) {
				return $template . '.bbp';
			},
			'bbp_allowed_themes'    => function() {
				return 'theme';
			},
			'bbp_map_meta_caps'      => function( $caps, $cap, $user_id, $args ) {
				return array( reset( $caps ), $cap, $user_id, reset( $args ) );
			},
			'bbp_redirect_canonical' => function( $redirect_url, $requested_url ) {
				return $redirect_url . '?from=' . rawurlencode( $requested_url );
			},
		);

		foreach ( $filters as $hook => $filter ) {
			add_filter( $hook, $filter, 999, 4 );
		}

		try {
			$this->assertSame( 'bbpress-en_US', bbp_plugin_locale( 'en_US', 'bbpress' ) );
			$this->assertSame( array( 'p' => 1, 'bbp' => true ), bbp_request( array( 'p' => 1 ) ) );
			$this->assertSame( 'index.php.bbp', bbp_template_include( 'index.php' ) );
			$this->assertSame( array( 'theme' ), bbp_allowed_themes( array() ) );
			$this->assertSame( array( 'read', 'bbp_tests_unmapped_cap', 7, 42 ), bbp_map_meta_caps( array( 'read' ), 'bbp_tests_unmapped_cap', 7, array( 42 ) ) );
			$this->assertSame( 'https://example.org/topic?from=https%3A%2F%2Fexample.org%2Fold', bbp_redirect_canonical( 'https://example.org/topic', 'https://example.org/old' ) );
		} finally {
			foreach ( $filters as $hook => $filter ) {
				remove_filter( $hook, $filter, 999 );
			}
		}
	}

	/**
	 * @covers ::bbp_allowed_themes
	 * @covers ::bbp_map_meta_caps
	 * @covers ::bbp_redirect_canonical
	 */
	public function test_filter_wrappers_cast_filtered_return_values() {
		$themes_filter   = function() {
			return null;
		};
		$caps_filter     = function() {
			return null;
		};
		$redirect_filter = function() {
			return false;
		};

		add_filter( 'bbp_allowed_themes', $themes_filter, PHP_INT_MAX );
		add_filter( 'bbp_map_meta_caps', $caps_filter, PHP_INT_MAX );
		add_filter( 'bbp_redirect_canonical', $redirect_filter, PHP_INT_MAX );

		try {
			$this->assertSame( array(), bbp_allowed_themes( array( 'theme' ) ) );
			$this->assertSame( array(), bbp_map_meta_caps( array( 'read' ), 'bbp_tests_unmapped_cap', 7 ) );
			$this->assertSame( '', bbp_redirect_canonical( 'https://example.org/topic', 'https://example.org/old' ) );
		} finally {
			remove_filter( 'bbp_allowed_themes', $themes_filter, PHP_INT_MAX );
			remove_filter( 'bbp_map_meta_caps', $caps_filter, PHP_INT_MAX );
			remove_filter( 'bbp_redirect_canonical', $redirect_filter, PHP_INT_MAX );
		}
	}

	/**
	 * @covers ::bbp_mail
	 */
	public function test_mail_filter_only_runs_for_bbp_messages() {
		$filtered = 0;
		$filter   = function( $args ) use ( &$filtered ) {
			++$filtered;
			$args['subject'] = 'Filtered';

			return $args;
		};
		$args     = array(
			'to'          => 'test@example.org',
			'subject'     => 'Original',
			'message'     => 'Message',
			'headers'     => array( 'Content-Type: text/plain' ),
			'attachments' => array(),
		);

		add_filter( 'bbp_mail', $filter );
		try {
			$missing_headers = $args;
			unset( $missing_headers['headers'] );
			$this->assertSame( $missing_headers, bbp_mail( $missing_headers ) );

			$string_headers            = $args;
			$string_headers['headers'] = bbp_get_email_header();
			$this->assertSame( $string_headers, bbp_mail( $string_headers ) );

			$this->assertSame( $args, bbp_mail( $args ) );

			$bbp_args              = $args;
			$bbp_args['headers'][] = bbp_get_email_header();
			$this->assertSame( 'Filtered', bbp_mail( $bbp_args )['subject'] );
		} finally {
			remove_filter( 'bbp_mail', $filter );
		}

		$this->assertSame( 1, $filtered );
	}

	/**
	 * @covers ::bbp_mail
	 */
	public function test_mail_casts_filtered_values_to_an_array() {
		$filter = function() {
			return null;
		};
		$args   = array(
			'headers' => array( bbp_get_email_header() ),
		);

		add_filter( 'bbp_mail', $filter, PHP_INT_MAX );
		try {
			$this->assertSame( array(), bbp_mail( $args ) );
		} finally {
			remove_filter( 'bbp_mail', $filter, PHP_INT_MAX );
		}
	}

	/**
	 * @covers ::bbp_user_can_embed_post
	 * @covers ::bbp_filter_oembed_request_post_id
	 * @covers ::bbp_do_not_redirect_restricted_posts
	 */
	public function test_oembed_helpers_block_restricted_bbp_posts_and_leave_other_posts_alone() {
		$forum_id = $this->factory->forum->create();
		$topic_id = $this->factory->topic->create(
			array(
				'post_parent' => $forum_id,
				'topic_meta'  => array( 'forum_id' => $forum_id ),
			)
		);
		$reply_id = $this->factory->reply->create(
			array(
				'post_parent' => $topic_id,
				'reply_meta'  => array(
					'forum_id' => $forum_id,
					'topic_id' => $topic_id,
				),
			)
		);
		$post_id        = self::factory()->post->create();
		$keymaster_id   = self::factory()->user->create();
		$participant_id = self::factory()->user->create();
		bbp_set_user_role( $keymaster_id, bbp_get_keymaster_role() );
		bbp_set_user_role( $participant_id, bbp_get_participant_role() );
		$url            = home_url( '/?p=' . $topic_id );
		$redirect       = 'https://example.org/topic';

		$this->set_current_user( 0 );
		$this->assertTrue( bbp_user_can_embed_post( $forum_id ) );
		$this->assertTrue( bbp_user_can_embed_post( $topic_id ) );
		$this->assertTrue( bbp_user_can_embed_post( $reply_id ) );
		$this->assertSame( $topic_id, bbp_filter_oembed_request_post_id( $topic_id ) );
		$this->assertSame( $post_id, bbp_filter_oembed_request_post_id( $post_id ) );
		$this->assertSame( $redirect, bbp_do_not_redirect_restricted_posts( $redirect, home_url( '/' ) ) );
		$this->assertSame( $redirect, bbp_do_not_redirect_restricted_posts( $redirect, $url ) );

		bbp_hide_forum( $forum_id, bbp_get_public_status_id() );

		$this->assertFalse( bbp_user_can_embed_post( $forum_id ) );
		$this->assertFalse( bbp_user_can_embed_post( $topic_id ) );
		$this->assertFalse( bbp_user_can_embed_post( $reply_id ) );
		$this->assertSame( 0, bbp_filter_oembed_request_post_id( $topic_id ) );
		$this->assertFalse( bbp_do_not_redirect_restricted_posts( $redirect, $url ) );
		$this->assertSame( $redirect, bbp_do_not_redirect_restricted_posts( $redirect, home_url( '/?p=' . $post_id ) ) );

		$this->set_current_user( $participant_id );
		$this->assertFalse( bbp_user_can_embed_post( $forum_id ) );
		$this->assertFalse( bbp_user_can_embed_post( $topic_id ) );
		$this->assertFalse( bbp_user_can_embed_post( $reply_id ) );

		$this->set_current_user( $keymaster_id );
		$this->assertFalse( bbp_user_can_embed_post( $forum_id ) );
		$this->assertTrue( bbp_user_can_embed_post( $topic_id ) );
		$this->assertTrue( bbp_user_can_embed_post( $reply_id ) );

		$this->set_current_user( 0 );
		bbp_privatize_forum( $forum_id, bbp_get_hidden_status_id() );
		$this->assertFalse( bbp_user_can_embed_post( $forum_id ) );
		$this->assertFalse( bbp_user_can_embed_post( $topic_id ) );
		$this->assertFalse( bbp_user_can_embed_post( $reply_id ) );

		$this->set_current_user( $participant_id );
		$this->assertFalse( bbp_user_can_embed_post( $forum_id ) );
		$this->assertTrue( bbp_user_can_embed_post( $topic_id ) );
		$this->assertTrue( bbp_user_can_embed_post( $reply_id ) );

		$this->set_current_user( $keymaster_id );
		$this->assertFalse( bbp_user_can_embed_post( $forum_id ) );
		$this->assertTrue( bbp_user_can_embed_post( $topic_id ) );
		$this->assertTrue( bbp_user_can_embed_post( $reply_id ) );

		$this->set_current_user( 0 );
		bbp_publicize_forum( $forum_id, bbp_get_private_status_id() );
		wp_update_post(
			array(
				'ID'          => $topic_id,
				'post_status' => bbp_get_pending_status_id(),
			)
		);
		$this->assertFalse( bbp_user_can_embed_post( $reply_id ) );

		$orphan_topic_id = $this->factory->topic->create(
			array(
				'post_parent' => 0,
				'topic_meta'  => array( 'forum_id' => 0 ),
			)
		);
		$this->assertFalse( bbp_user_can_embed_post( $orphan_topic_id ) );

		$password_topic_id = $this->factory->topic->create(
			array(
				'post_parent'   => $forum_id,
				'post_password' => 'secret',
				'topic_meta'    => array( 'forum_id' => $forum_id ),
			)
		);
		$this->assertFalse( bbp_user_can_embed_post( $password_topic_id ) );
	}

	/**
	 * @dataProvider canonical_redirect_post_id_provider
	 *
	 * @covers ::bbp_get_canonical_redirect_post_id
	 */
	public function test_get_canonical_redirect_post_id( $path, $expected ) {
		$this->assertSame( $expected, bbp_get_canonical_redirect_post_id( home_url( $path ) ) );
	}

	public static function canonical_redirect_post_id_provider() {
		return array(
			'no query'                      => array( '/', 0 ),
			'p'                             => array( '/?p=10', 10 ),
			'page_id'                       => array( '/?page_id=20', 20 ),
			'attachment_id'                 => array( '/?attachment_id=30', 30 ),
			'subpost_id'                    => array( '/?subpost_id=40', 40 ),
			'page_id replaces p'            => array( '/?p=40&page_id=20', 20 ),
			'attachment_id replaces p'      => array( '/?p=40&attachment_id=20', 20 ),
			'subpost replaces attachment'   => array( '/?attachment_id=40&subpost_id=20', 20 ),
			'attachment greater than page'  => array( '/?p=40&page_id=20&attachment_id=30', 30 ),
			'page greater than attachment'  => array( '/?p=20&page_id=40&attachment_id=30', 40 ),
			'non-scalar p'                  => array( '/?p%5B%5D=10', 0 ),
			'negative p'                    => array( '/?p=-10', 0 ),
			'non-scalar page preserves p'   => array( '/?p=10&page_id%5B%5D=20', 10 ),
			'non-scalar subpost is ignored' => array( '/?attachment_id=30&subpost_id%5B%5D=20', 30 ),
		);
	}

	/**
	 * @covers ::bbp_do_not_redirect_restricted_posts
	 */
	public function test_restricted_post_redirects_use_canonical_post_id() {
		$forum_id        = $this->factory->forum->create();
		$topic_id        = $this->factory->topic->create(
			array(
				'post_parent' => $forum_id,
				'topic_meta'  => array( 'forum_id' => $forum_id ),
			)
		);
		$reply_id        = $this->factory->reply->create(
			array(
				'post_parent' => $topic_id,
				'reply_meta'  => array(
					'forum_id' => $forum_id,
					'topic_id' => $topic_id,
				),
			)
		);
		$post_id         = self::factory()->post->create();
		$keymaster_id    = self::factory()->user->create();
		$redirect        = 'https://example.org/canonical';

		bbp_hide_forum( $forum_id, bbp_get_public_status_id() );
		bbp_set_user_role( $keymaster_id, bbp_get_keymaster_role() );

		$this->set_current_user( 0 );
		foreach ( array( 'p', 'page_id', 'attachment_id', 'subpost_id' ) as $query_var ) {
			$this->assertFalse( bbp_do_not_redirect_restricted_posts( $redirect, home_url( '/?' . $query_var . '=' . $topic_id ) ), $query_var . ' must not disclose a restricted topic permalink.' );
			$this->assertFalse( bbp_do_not_redirect_restricted_posts( $redirect, home_url( '/?' . $query_var . '=' . $reply_id ) ), $query_var . ' must not disclose a restricted reply permalink.' );
			$this->assertSame( $redirect, bbp_do_not_redirect_restricted_posts( $redirect, home_url( '/?' . $query_var . '=' . $post_id ) ), $query_var . ' must preserve redirects for non-bbPress posts.' );
		}
		$this->assertSame( $redirect, bbp_do_not_redirect_restricted_posts( $redirect, home_url( '/' ) ) );

		$this->set_current_user( $keymaster_id );
		foreach ( array( 'p', 'page_id', 'attachment_id', 'subpost_id' ) as $query_var ) {
			$this->assertSame( $redirect, bbp_do_not_redirect_restricted_posts( $redirect, home_url( '/?' . $query_var . '=' . $topic_id ) ), $query_var . ' must preserve an authorized topic redirect.' );
			$this->assertSame( $redirect, bbp_do_not_redirect_restricted_posts( $redirect, home_url( '/?' . $query_var . '=' . $reply_id ) ), $query_var . ' must preserve an authorized reply redirect.' );
		}
	}
}
