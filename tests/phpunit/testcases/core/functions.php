<?php

/**
 * Tests for core functions.
 *
 * @group core
 * @group functions
 */
class BBP_Tests_Core_Functions extends BBP_UnitTestCase {

	private $errors;
	private $get;
	private $post;
	private $request_method;
	private $views;

	public function setUp(): void {
		parent::setUp();

		$this->errors         = bbpress()->errors;
		$this->get            = $_GET;
		$this->post           = $_POST;
		$this->request_method = isset( $_SERVER['REQUEST_METHOD'] )
			? $_SERVER['REQUEST_METHOD']
			: null;
		$this->views          = bbpress()->views;

		bbpress()->errors = new WP_Error();
		bbpress()->views  = array();
	}

	public function tearDown(): void {
		bbpress()->errors = $this->errors;
		bbpress()->views  = $this->views;
		$_GET             = $this->get;
		$_POST            = $this->post;

		if ( null === $this->request_method ) {
			unset( $_SERVER['REQUEST_METHOD'] );
		} else {
			$_SERVER['REQUEST_METHOD'] = $this->request_method;
		}

		parent::tearDown();
	}

	/**
	 * Ensure that the bbPress function exists.
	 */
	public function test_bbpress_exists() {
		$this->assertTrue( function_exists( 'bbpress' ) );
	}

	/**
	 * Ensure that bbPress has been installed and activated.
	 */
	public function test_plugin_activated() {
		$this->assertTrue( is_plugin_active( 'bbpress/bbpress.php' ) );
	}

	/**
	 * @covers ::bbp_redirect
	 * @ticket 3706
	 */
	public function test_redirect_uses_local_url_and_status_with_forum_fallback() {
		$received = null;
		$filter   = static function( $location, $status ) use ( &$received ) {
			$received = compact( 'location', 'status' );

			throw new UnexpectedValueException( 'Stop before exit.' );
		};

		add_filter( 'wp_redirect', $filter, 10, 2 );
		try {
			foreach (
				array(
					array( home_url( '/destination/' ), 307, home_url( '/destination/' ) ),
					array( '', 302, bbp_get_forums_url() ),
				) as $case
			) {
				try {
					bbp_redirect( $case[0], $case[1] );
					$this->fail( 'The redirect did not reach the WordPress redirect filter.' );
				} catch ( UnexpectedValueException $exception ) {
					$this->assertSame( 'Stop before exit.', $exception->getMessage() );
					$this->assertSame( $case[2], $received['location'] );
					$this->assertSame( $case[1], $received['status'] );
				}
			}
		} finally {
			remove_filter( 'wp_redirect', $filter, 10 );
		}
	}

	/**
	 * @covers ::bbp_version
	 * @covers ::bbp_get_version
	 * @covers ::bbp_db_version
	 * @covers ::bbp_get_db_version
	 * @covers ::bbp_db_version_raw
	 * @covers ::bbp_get_db_version_raw
	 */
	public function test_version_helpers_return_and_output_runtime_values() {
		$this->expectOutputString(
			bbp_get_version()
			. bbp_get_db_version()
			. bbp_get_db_version_raw()
		);

		bbp_version();
		bbp_db_version();
		bbp_db_version_raw();

		$this->assertSame( bbpress()->version, bbp_get_version() );
		$this->assertSame( bbpress()->db_version, bbp_get_db_version() );
		$this->assertSame( get_option( '_bbp_db_version', '' ), bbp_get_db_version_raw() );
	}

	/**
	 * @covers ::bbp_asset_version
	 * @covers ::bbp_get_asset_version
	 * @covers ::bbp_doing_script_debug
	 */
	public function test_asset_version_reflects_script_debug_state() {
		if ( bbp_doing_script_debug() ) {
			$this->expectOutputRegex( '/^\d+$/' );
			$this->assertMatchesRegularExpression( '/^\d+$/', bbp_get_asset_version() );
		} else {
			$this->expectOutputString( bbp_get_version() );
			$this->assertSame( bbp_get_version(), bbp_get_asset_version() );
		}

		bbp_asset_version();
	}

	/**
	 * @covers ::bbp_update_forum_id
	 * @covers ::bbp_update_topic_id
	 * @covers ::bbp_update_reply_id
	 * @covers ::bbp_update_reply_to_id
	 */
	public function test_post_meta_id_helpers_filter_cast_store_and_return_values() {
		$post_id = self::factory()->post->create();
		$cases   = array(
			array( 'bbp_update_forum_id', '_bbp_forum_id' ),
			array( 'bbp_update_topic_id', '_bbp_topic_id' ),
			array( 'bbp_update_reply_id', '_bbp_reply_id' ),
			array( 'bbp_update_reply_to_id', '_bbp_reply_to' ),
		);

		foreach ( $cases as $case ) {
			$function = $case[0];
			$meta_key = $case[1];
			$filter   = function( $object_id, $filtered_post_id ) use ( $post_id ) {
				$this->assertSame( $post_id, $filtered_post_id );

				return '42';
			};

			add_filter( $function, $filter, 10, 2 );
			try {
				$this->assertSame( 42, $function( $post_id, 7 ) );
				$this->assertSame( 42, (int) get_post_meta( $post_id, $meta_key, true ) );
			} finally {
				remove_filter( $function, $filter, 10 );
			}
		}
	}

	/**
	 * @covers ::bbp_get_views
	 * @covers ::bbp_register_view
	 * @covers ::bbp_deregister_view
	 */
	public function test_view_registration_sanitizes_defaults_and_deregisters_views() {
		$view = bbp_register_view( 'Recent Topics', '<b>Recent</b>', 'posts_per_page=3', false );

		$this->assertSame( '&lt;b&gt;Recent&lt;/b&gt;', $view['title'] );
		$this->assertSame( '3', $view['query']['posts_per_page'] );
		$this->assertFalse( $view['query']['show_stickies'] );
		$this->assertFalse( $view['feed'] );
		$this->assertSame( $view, bbp_get_views()['recent-topics'] );
		$this->assertTrue( bbp_deregister_view( 'Recent Topics' ) );
		$this->assertFalse( bbp_deregister_view( 'Recent Topics' ) );
	}

	/**
	 * @covers ::bbp_register_view
	 */
	public function test_view_registration_rejects_invalid_or_unauthorized_views() {
		wp_set_current_user( 0 );

		$this->assertFalse( bbp_register_view( '', 'Title' ) );
		$this->assertFalse( bbp_register_view( 'view', '' ) );
		$this->assertFalse( bbp_register_view( 'private', 'Private', array(), true, 'manage_options' ) );
		$this->assertSame( array(), bbp_get_views() );
	}

	/**
	 * @covers ::bbp_view_query
	 * @covers ::bbp_get_view_query_args
	 */
	public function test_view_query_uses_registered_arguments_and_accepts_overrides() {
		$topic_id = $this->factory->topic->create(
			array( 'post_status' => bbp_get_public_status_id() )
		);

		bbp_register_view(
			'unit-test-view',
			'Unit Test View',
			array(
				'post_type'      => bbp_get_topic_post_type(),
				'post__in'       => array( $topic_id ),
				'posts_per_page' => 1,
			)
		);

		$this->assertSame( array(), bbp_get_view_query_args( 'missing-view' ) );
		$this->assertSame( 1, bbp_get_view_query_args( 'unit-test-view' )['posts_per_page'] );
		$this->assertTrue( bbp_view_query( 'unit-test-view' ) );
		$this->assertFalse( bbp_view_query( 'missing-view' ) );
		$this->assertFalse( bbp_view_query( 'unit-test-view', array( 'post_status' => 'draft' ) ) );
	}

	/**
	 * @covers ::bbp_view_query
	 */
	public function test_registered_view_with_empty_filtered_arguments_is_preserved() {
		$this->factory->topic->create(
			array( 'post_status' => bbp_get_public_status_id() )
		);

		bbp_register_view( 'all-topics', 'All Topics' );
		$filter = function( $args, $view ) {
			return ( 'all-topics' === $view )
				? array()
				: $args;
		};
		add_filter( 'bbp_get_view_query_args', $filter, 10, 2 );

		try {
			// Registered views may intentionally rely on the default topic query.
			$this->assertTrue( bbp_view_query( 'all-topics' ) );
		} finally {
			remove_filter( 'bbp_get_view_query_args', $filter, 10 );
		}
	}

	/**
	 * @covers ::bbp_get_view_query_args
	 */
	public function test_view_query_arguments_are_filterable() {
		$filter = function( $args, $view ) {
			$this->assertSame( 'missing-view', $view );
			$args['filtered'] = true;

			return $args;
		};

		add_filter( 'bbp_get_view_query_args', $filter, 10, 2 );
		try {
			$this->assertSame( array( 'filtered' => true ), bbp_get_view_query_args( 'missing-view' ) );
		} finally {
			remove_filter( 'bbp_get_view_query_args', $filter, 10 );
		}
	}

	/**
	 * @covers ::bbp_view_query
	 */
	public function test_view_query_preserves_filtered_arguments_for_virtual_views() {
		$topic_id = $this->factory->topic->create(
			array( 'post_status' => bbp_get_public_status_id() )
		);
		$filter   = function( $args, $view ) use ( $topic_id ) {
			if ( 'virtual-view' === $view ) {
				$args = array(
					'post_type' => bbp_get_topic_post_type(),
					'post__in'  => array( $topic_id ),
				);
			}

			return $args;
		};

		add_filter( 'bbp_get_view_query_args', $filter, 10, 2 );
		try {
			$this->assertTrue( bbp_view_query( 'virtual-view' ) );
		} finally {
			remove_filter( 'bbp_get_view_query_args', $filter, 10 );
		}
	}

	/**
	 * @covers ::bbp_add_error
	 * @covers ::bbp_has_errors
	 */
	public function test_error_helpers_store_data_and_filter_queue_state() {
		$this->assertFalse( bbp_has_errors() );

		bbp_add_error( 'unit_test', 'Test message', 'Test data' );

		$this->assertTrue( bbp_has_errors() );
		$this->assertSame( 'Test message', bbpress()->errors->get_error_message( 'unit_test' ) );
		$this->assertSame( 'Test data', bbpress()->errors->get_error_data( 'unit_test' ) );

		$filter = function( $has_errors, $errors ) {
			$this->assertTrue( $has_errors );
			$this->assertSame( bbpress()->errors, $errors );

			return false;
		};
		add_filter( 'bbp_has_errors', $filter, 10, 2 );

		try {
			$this->assertFalse( bbp_has_errors() );
		} finally {
			remove_filter( 'bbp_has_errors', $filter, 10 );
		}
	}

	/**
	 * @covers ::bbp_find_mentions_pattern
	 * @covers ::bbp_find_mentions
	 */
	public function test_find_mentions_returns_unique_usernames_and_supports_filters() {
		$this->assertSame( array( 'alice', 'bob' ), bbp_find_mentions( '@alice met @bob and @alice again.' ) );
		$this->assertFalse( bbp_find_mentions( 'No mentions here.' ) );

		$filter = function() {
			return '/#([a-z]+)/';
		};
		add_filter( 'bbp_find_mentions_pattern', $filter );

		try {
			$this->assertSame( array( 'topic' ), bbp_find_mentions( 'A #topic.' ) );
		} finally {
			remove_filter( 'bbp_find_mentions_pattern', $filter );
		}
	}

	/**
	 * @covers ::bbp_mention_filter
	 */
	public function test_mention_filter_links_active_users_and_preserves_unknown_users() {
		$user_id = self::factory()->user->create( array( 'user_login' => 'mention-user' ) );
		$content = bbp_mention_filter( 'Hello @mention-user and @missing-user.' );

		$this->assertStringContainsString( bbp_get_user_profile_url( $user_id ), $content );
		$this->assertStringContainsString( '>@mention-user</a>', $content );
		$this->assertStringContainsString( '@missing-user', $content );
	}

	/**
	 * @covers ::bbp_get_public_status_id
	 * @covers ::bbp_get_pending_status_id
	 * @covers ::bbp_get_private_status_id
	 * @covers ::bbp_get_hidden_status_id
	 * @covers ::bbp_get_closed_status_id
	 * @covers ::bbp_get_spam_status_id
	 * @covers ::bbp_get_trash_status_id
	 * @covers ::bbp_get_orphan_status_id
	 */
	public function test_status_id_helpers_return_runtime_ids() {
		$this->assertSame( bbpress()->public_status_id, bbp_get_public_status_id() );
		$this->assertSame( bbpress()->pending_status_id, bbp_get_pending_status_id() );
		$this->assertSame( bbpress()->private_status_id, bbp_get_private_status_id() );
		$this->assertSame( bbpress()->hidden_status_id, bbp_get_hidden_status_id() );
		$this->assertSame( bbpress()->closed_status_id, bbp_get_closed_status_id() );
		$this->assertSame( bbpress()->spam_status_id, bbp_get_spam_status_id() );
		$this->assertSame( bbpress()->trash_status_id, bbp_get_trash_status_id() );
		$this->assertSame( bbpress()->orphan_status_id, bbp_get_orphan_status_id() );
	}

	/**
	 * @covers ::bbp_get_user_rewrite_id
	 * @covers ::bbp_get_edit_rewrite_id
	 * @covers ::bbp_get_search_rewrite_id
	 * @covers ::bbp_get_user_topics_rewrite_id
	 * @covers ::bbp_get_user_replies_rewrite_id
	 * @covers ::bbp_get_user_favorites_rewrite_id
	 * @covers ::bbp_get_user_subscriptions_rewrite_id
	 * @covers ::bbp_get_user_engagements_rewrite_id
	 * @covers ::bbp_get_view_rewrite_id
	 * @covers ::bbp_get_paged_rewrite_id
	 */
	public function test_rewrite_id_helpers_return_runtime_ids() {
		$this->assertSame( bbpress()->user_id, bbp_get_user_rewrite_id() );
		$this->assertSame( bbpress()->edit_id, bbp_get_edit_rewrite_id() );
		$this->assertSame( bbpress()->search_id, bbp_get_search_rewrite_id() );
		$this->assertSame( bbpress()->tops_id, bbp_get_user_topics_rewrite_id() );
		$this->assertSame( bbpress()->reps_id, bbp_get_user_replies_rewrite_id() );
		$this->assertSame( bbpress()->favs_id, bbp_get_user_favorites_rewrite_id() );
		$this->assertSame( bbpress()->subs_id, bbp_get_user_subscriptions_rewrite_id() );
		$this->assertSame( bbpress()->engagements_id, bbp_get_user_engagements_rewrite_id() );
		$this->assertSame( bbpress()->view_id, bbp_get_view_rewrite_id() );
		$this->assertSame( bbpress()->paged_id, bbp_get_paged_rewrite_id() );
	}

	/**
	 * @covers ::bbp_delete_rewrite_rules
	 */
	public function test_delete_rewrite_rules_removes_the_stored_rules() {
		update_option( 'rewrite_rules', array( 'unit-test' => 'index.php?unit-test=1' ) );

		bbp_delete_rewrite_rules();

		$this->assertFalse( get_option( 'rewrite_rules', false ) );
	}

	/**
	 * @covers ::bbp_is_post_request
	 * @covers ::bbp_is_get_request
	 */
	public function test_request_helpers_are_case_insensitive_and_mutually_exclusive() {
		$_SERVER['REQUEST_METHOD'] = 'post';
		$this->assertTrue( bbp_is_post_request() );
		$this->assertFalse( bbp_is_get_request() );

		$_SERVER['REQUEST_METHOD'] = 'GET';
		$this->assertFalse( bbp_is_post_request() );
		$this->assertTrue( bbp_is_get_request() );

		$_SERVER['REQUEST_METHOD'] = 'PATCH';
		$this->assertFalse( bbp_is_post_request() );
		$this->assertFalse( bbp_is_get_request() );
	}

	/**
	 * @covers ::bbp_is_post_request
	 * @covers ::bbp_is_get_request
	 */
	public function test_request_helpers_return_false_when_the_method_is_unavailable() {
		unset( $_SERVER['REQUEST_METHOD'] );

		$this->assertFalse( bbp_is_post_request() );
		$this->assertFalse( bbp_is_get_request() );
	}

	/**
	 * @covers ::bbp_get_post_action
	 * @covers ::bbp_is_post_action
	 */
	public function test_post_action_helpers_sanitize_and_match_actions() {
		$_SERVER['REQUEST_METHOD'] = 'POST';
		$_POST['action']           = 'EDIT-TOPIC!';

		$this->assertSame( 'edit-topic', bbp_get_post_action() );
		$this->assertTrue( bbp_is_post_action( 'edit-topic' ) );
		$this->assertTrue( bbp_is_post_action( array( 'other', 'edit-topic' ) ) );
		$this->assertFalse( bbp_is_post_action( 'other' ) );
		$this->assertFalse( bbp_is_post_action( true ) );
		$this->assertFalse( bbp_is_post_action() );

		$_SERVER['REQUEST_METHOD'] = 'GET';
		$this->assertFalse( bbp_is_post_action( 'edit-topic' ) );

		$_SERVER['REQUEST_METHOD'] = 'POST';
		unset( $_POST['action'] );
		$this->assertSame( '', bbp_get_post_action() );
		$this->assertFalse( bbp_is_post_action( '' ) );

		$_POST['action'] = array( 'edit-topic' );
		$this->assertSame( '', bbp_get_post_action() );
		$this->assertFalse( bbp_is_post_action( 'edit-topic' ) );

		$_POST['action'] = '!!!';
		$this->assertSame( '', bbp_get_post_action() );
		$this->assertFalse( bbp_is_post_action( '' ) );
	}

	/**
	 * @covers ::bbp_is_get_action
	 * @ticket BBP3740
	 */
	public function test_get_action_helper_sanitizes_and_matches_actions() {
		$_SERVER['REQUEST_METHOD'] = 'GET';
		$_GET['action']            = 'EDIT-TOPIC!';

		$this->assertTrue( bbp_is_get_action( 'edit-topic' ) );
		$this->assertTrue( bbp_is_get_action( array( 'other', 'edit-topic' ) ) );
		$this->assertFalse( bbp_is_get_action( 'other' ) );
		$this->assertFalse( bbp_is_get_action( true ) );
		$this->assertFalse( bbp_is_get_action() );

		$_SERVER['REQUEST_METHOD'] = 'POST';
		$this->assertTrue( bbp_is_get_action( 'edit-topic' ) );

		$_SERVER['REQUEST_METHOD'] = 'GET';
		unset( $_GET['action'] );
		$this->assertFalse( bbp_is_get_action( '' ) );

		$_GET['action'] = array( 'edit-topic' );
		$this->assertFalse( bbp_is_get_action( 'edit-topic' ) );

		$_GET['action'] = '!!!';
		$this->assertFalse( bbp_is_get_action( '' ) );
	}

	/**
	 * @covers ::bbp_doing_script_debug
	 * @covers ::bbp_doing_autosave
	 */
	public function test_global_state_helpers_match_their_constants() {
		$this->assertSame( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG, bbp_doing_script_debug() );
		$this->assertSame( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE, bbp_doing_autosave() );
	}
}
