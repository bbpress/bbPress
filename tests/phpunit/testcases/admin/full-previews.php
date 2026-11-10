<?php

/**
 * Exception used to capture the end of a full-preview AJAX request.
 */
class BBP_Tests_Admin_Full_Previews_Ajax_Exit extends Exception {
}

/**
 * Whole-operation previews must not depend on the Run batch size or checkpoint.
 *
 * @group tools
 * @group tool-batches
 */
class BBP_Tests_Admin_Full_Previews extends BBP_UnitTestCase {

	private $size = 2;
	private $registered_default = false;

	public function setUp(): void {
		parent::setUp();
		if ( ! function_exists( 'bbp_admin' ) ) {
			require_once BBP_PLUGIN_DIR . 'includes/admin/actions.php';
		}
		if ( false === has_action( 'bbp_register_repair_tools', 'bbp_register_default_repair_tools' ) ) {
			add_action( 'bbp_register_repair_tools', 'bbp_register_default_repair_tools' );
			$this->registered_default = true;
		}
		bbp_admin();
		add_filter( 'bbp_admin_repair_tool_batch_size', array( $this, 'batch_size' ) );
	}

	public function tearDown(): void {
		remove_filter( 'bbp_admin_repair_tool_batch_size', array( $this, 'batch_size' ) );
		if ( $this->registered_default ) {
			remove_action( 'bbp_register_repair_tools', 'bbp_register_default_repair_tools' );
		}
		parent::tearDown();
	}

	public function batch_size() {
		return $this->size;
	}

	public function test_freshness_query_objects_are_accepted_without_writes() {
		$topics = $this->factory->topic->create_many( 2 );
		update_post_meta( $topics[0], '_bbp_last_active_time', '2020-01-01 00:00:00' );
		update_post_meta( $topics[1], '_bbp_last_active_time', '2021-01-01 00:00:00' );
		$before = $this->snapshot();
		$worker = new BBP_Admin_Forum_Freshness( true );
		$this->assertLessThan( 0, $worker->compare_topic_times( (object) array( 'ID' => $topics[1] ), (object) array( 'ID' => $topics[0] ) ) );
		$this->assertSame( $before, $this->snapshot() );
	}

	public function test_full_mention_preview_preserves_unrelated_post_caches() {
		$post = $this->factory->post->create();
		$this->factory->topic->create();
		$cached_post = get_post( $post );
		$cached_meta = array( '_bbp_preview' => array( 'preserved' ) );
		wp_cache_set( $post, $cached_post, 'posts' );
		wp_cache_set( $post, $cached_meta, 'post_meta' );
		$before = $this->snapshot();
		$result = bbp_admin_repair_user_mentions_process( 1, false, true );
		$this->assertSame( 0, $result[0] );
		$this->assertTrue( $result['complete'] );
		$this->assertEquals( $cached_post, wp_cache_get( $post, 'posts' ) );
		$this->assertSame( $cached_meta, wp_cache_get( $post, 'post_meta' ) );
		$this->assertSame( $before, $this->snapshot() );
	}

	public function test_reply_preview_releases_each_read_windows_post_caches() {
		$topic   = $this->factory->topic->create();
		$replies = $this->factory->reply->create_many( 4, array( 'post_parent' => $topic ) );
		$checks  = 0;
		$check = function ( $topic_id, $reply_id ) use ( $replies, &$checks ) {
			if ( (int) $reply_id === $replies[2] ) {
				++$checks;
				$this->assertFalse( wp_cache_get( $replies[0], 'posts' ) );
				$this->assertFalse( wp_cache_get( $replies[0], 'post_meta' ) );
			}
			return $topic_id;
		};
		$before = $this->snapshot();
		add_filter( 'bbp_get_reply_topic_id', $check, 10, 2 );
		try {
			$result = bbp_admin_repair_reply_menu_order_batch( true );
		} finally {
			remove_filter( 'bbp_get_reply_topic_id', $check, 10 );
		}
		$this->assertSame( 0, $result['code'] );
		$this->assertTrue( $result['complete'] );
		$this->assertGreaterThan( 0, $checks );
		$this->assertSame( $before, $this->snapshot() );
	}

	public function test_reply_preview_preserves_native_sql_status_comparisons() {
		$db      = bbp_db();
		$topic   = $this->factory->topic->create();
		$replies = $this->factory->reply->create_many( 2, array( 'post_parent' => $topic ) );
		$db->update( $db->posts, array( 'post_status' => 'DRAFT', 'menu_order' => 0 ), array( 'ID' => $replies[0] ) );
		$db->update( $db->posts, array( 'menu_order' => 1 ), array( 'ID' => $replies[1] ) );
		update_post_meta( $topic, '_bbp_reply_count', 2 );
		$before = $this->snapshot();
		$result = bbp_admin_repair_reply_menu_order_batch( true );
		$this->assertSame( 0, $result['code'] );
		$this->assertTrue( $result['complete'] );
		$this->assertSame( 0, $result['positions'] );
		$this->assertSame( $before, $this->snapshot() );
	}

	public function test_reply_preview_runs_the_native_position_plan_for_every_reply() {
		$topic   = $this->factory->topic->create();
		$replies = $this->factory->reply->create_many( 4, array( 'post_parent' => $topic ) );
		$queries = 0;
		$capture = static function ( $query ) use ( &$queries ) {
			if ( false !== strpos( $query, 'COUNT(*) AS reply_position' ) ) {
				++$queries;
			}
			return $query;
		};
		$before = $this->snapshot();
		add_filter( 'query', $capture );
		try {
			$result = bbp_admin_repair_reply_menu_order_batch( true );
		} finally {
			remove_filter( 'query', $capture );
		}
		$this->assertSame( 0, $result['code'] );
		$this->assertTrue( $result['complete'] );
		$this->assertSame( count( $replies ), $queries );
		$this->assertSame( $before, $this->snapshot() );
	}

	public function test_closed_topic_browser_preview_matches_the_full_preview() {
		$topics = $this->factory->topic->create_many( 5, array( 'post_status' => bbp_get_closed_status_id() ) );
		foreach ( $topics as $topic_id ) {
			delete_post_meta( $topic_id, '_bbp_status' );
		}
		$before   = $this->snapshot();
		$expected = bbp_admin_repair_closed_topics_dry_run();
		$requests = 0;

		bbp_admin_repair_tool_execution_context( 'dry-run', 'closed-topic-preview' );
		try {
			for ( $i = 0; $i < 100; ++$i ) {
				$result = bbp_admin_repair_closed_topics_dry_run();
				++$requests;
				$this->assertSame( 0, $result[0] );
				if ( $result['complete'] ) {
					break;
				}
			}
		} finally {
			bbp_admin_repair_tool_execution_context( 'run', '' );
		}

		$this->assertTrue( $result['complete'] );
		$this->assertGreaterThan( 1, $requests );
		$this->assertSame( $expected, $result );
		$this->assertSame( $before, $this->snapshot() );
	}

	public function workers() {
		return array(
			array( 'bbp-sync-topic-meta', 'bbp_admin_repair_post_reference_batch', array( 'topic' ) ),
			array( 'bbp-sync-forum-meta', 'bbp_admin_repair_post_reference_batch', array( 'forum' ) ),
			array( 'bbp-topic-replies', 'bbp_admin_repair_topic_reply_count_batch', array( false ) ),
			array( 'bbp-topic-hidden-replies', 'bbp_admin_repair_topic_reply_count_batch', array( true ) ),
			array( 'bbp-topic-engagements', 'bbp_admin_repair_topic_voice_count_batch', array() ),
			array( 'bbp-forum-topics', 'bbp_admin_repair_forum_count_batch', array( 'topics' ) ),
			array( 'bbp-forum-replies', 'bbp_admin_repair_forum_count_batch', array( 'replies' ) ),
			array( 'bbp-forum-hidden-replies', 'bbp_admin_repair_forum_count_batch', array( 'hidden-replies' ) ),
			array( 'bbp-forum-subforums', 'bbp_admin_repair_forum_subforum_count_batch', array() ),
			array( 'bbp-topic-tags', 'bbp_admin_repair_topic_tag_count_batch', array() ),
			array( 'bbp-user-topics', 'bbp_admin_repair_user_post_count_batch', array() ),
			array( 'bbp-user-replies', 'bbp_admin_repair_user_post_count_batch', array() ),
			array( 'bbp-user-favorites', 'bbp_admin_repair_user_meta_batch', array( 'favorites' ) ),
			array( 'bbp-user-topic-subscriptions', 'bbp_admin_repair_user_meta_batch', array( 'topic-subscriptions' ) ),
			array( 'bbp-user-forum-subscriptions', 'bbp_admin_repair_user_meta_batch', array( 'forum-subscriptions' ) ),
			array( 'bbp-user-role-map', 'bbp_admin_repair_user_roles_batch', array() ),
			array( 'bbp-sync-all-topics-forums', 'bbp_admin_repair_freshness_batch', array() ),
			array( 'bbp-sync-all-topics-sticky', 'bbp_admin_repair_sticky_batch', array() ),
			array( 'bbp-sync-forum-visibility', 'bbp_admin_repair_forum_visibility_batch', array() ),
			array( 'bbp-sync-all-reply-positions', 'bbp_admin_repair_reply_menu_order_batch', array() ),
			array( 'bbp-user-topic-engagements-move', 'bbp_admin_upgrade_user_engagements_batch', array() ),
			array( 'bbp-user-favorites-move', 'bbp_admin_upgrade_user_meta_batch', array() ),
			array( 'bbp-user-topic-subscriptions-move', 'bbp_admin_upgrade_user_meta_batch', array() ),
			array( 'bbp-user-forum-subscriptions-move', 'bbp_admin_upgrade_user_meta_batch', array() ),
			array( 'bbp-user-favorites-delete', 'bbp_admin_upgrade_remove_user_meta_batch', array( 'favorites' ) ),
			array( 'bbp-user-topic-subscriptions-delete', 'bbp_admin_upgrade_remove_user_meta_batch', array( 'topic-subscriptions' ) ),
			array( 'bbp-user-forum-subscriptions-delete', 'bbp_admin_upgrade_remove_user_meta_batch', array( 'forum-subscriptions' ) ),
		);
	}

	public function test_every_default_tool_has_browser_preview_coverage() {
		$tools = bbp_admin()->tools;
		bbp_register_default_repair_tools();
		$covered = array_column( $this->workers(), 0 );
		$covered = array_merge(
			$covered,
			array(
				'bbp-sync-closed-topics',
				'bbp-mentions',
				'bbp-user-mentions',
				'bbp-group-forums',
			)
		);
		$registered = array_keys( bbp_get_admin_repair_tools() );
		bbp_admin()->tools = $tools;
		sort( $covered );
		sort( $registered );

		$this->assertSame( $registered, $covered );
	}

	private function fixture() {
		$db     = bbp_db();
		$forums = $this->factory->forum->create_many( 4 );
		$users  = $this->factory->user->create_many( 5, array( 'role' => 'subscriber' ) );
		$topics = array();
		foreach ( $users as $user ) {
			$topic = $this->factory->topic->create( array( 'post_parent' => $forums[0], 'post_author' => $user ) );
			$topics[] = $topic;
			$this->factory->reply->create_many( 3, array( 'post_parent' => $topic, 'post_author' => $user ) );
			foreach ( array( 'favorites', 'topic-subscriptions', 'forum-subscriptions' ) as $type ) {
				$config = bbp_admin_get_user_relationship_config( $type );
				$list   = implode( ',', array_merge( $topics, array( 999999 ) ) );
				add_user_meta( $user, $config['source_key'], $list );
				add_user_meta( $user, $config['source_key'], $list );
			}
			foreach ( array( '_bbp_forum_id', '_bbp_topic_id', '_bbp_reply_count', '_bbp_voice_count', '_bbp_last_reply_id', '_bbp_last_active_id' ) as $key ) {
				update_post_meta( $topic, $key, 999999 );
				add_post_meta( $topic, strtoupper( $key ), 999999 );
			}
			add_post_meta( $topic, '_bbp_engagement', 999999 );
			$term = wp_insert_term( wp_generate_uuid4(), bbp_get_topic_tag_tax_id() );
			$db->update( $db->term_taxonomy, array( 'count' => 99 ), array( 'term_taxonomy_id' => $term['term_taxonomy_id'] ) );
		}
		foreach ( $forums as $forum ) {
			update_post_meta( $forum, '_bbp_sticky_topics', array_merge( $topics, array( 999999 ) ) );
		}
		update_option( '_bbp_private_forums', array( 999999 ) );
		update_option( '_bbp_hidden_forums', array( 999999 ) );
		update_option( '_bbp_engagements_strategy', 'meta' );
	}

	private function snapshot() {
		$db     = bbp_db();
		$result = array();
		foreach ( array( 'posts' => 'ID', 'postmeta' => 'meta_id', 'users' => 'ID', 'usermeta' => 'umeta_id', 'options' => 'option_id', 'term_taxonomy' => 'term_taxonomy_id' ) as $table => $column ) {
			$result[ $table ] = $db->get_results( "SELECT * FROM {$db->$table} ORDER BY {$column}", ARRAY_A );
		}
		return $result;
	}

	private function arguments( $tool_id, $callback, $args ) {
		if ( 'bbp-topic-tags' === $tool_id ) {
			$args = array( get_taxonomy( bbp_get_topic_tag_tax_id() ), 'bbp_update_topic_tag_count' );
		} elseif ( 'bbp_admin_repair_user_post_count_batch' === $callback ) {
			$reply = 'bbp-user-replies' === $tool_id;
			$args = array( $tool_id, $reply ? bbp_get_reply_post_type() : bbp_get_topic_post_type(), $reply ? bbp_get_public_reply_statuses() : bbp_get_public_topic_statuses(), bbp_db()->prefix . ( $reply ? '_bbp_reply_count' : '_bbp_topic_count' ) );
		} elseif ( 'bbp_admin_upgrade_user_meta_batch' === $callback ) {
			$config = bbp_admin_get_user_relationship_config( substr( $tool_id, 9, -5 ) );
			$args   = array( $tool_id, $config['source_key'], $config['destination_key'] );
		}
		return $args;
	}

	/** @dataProvider workers */
	public function test_full_totals_are_independent_of_batch_size_and_saved_run_state( $tool_id, $callback, $args ) {
		$this->fixture();
		$args = $this->arguments( $tool_id, $callback, $args );
		$args[] = true;
		$option = bbp_admin_repair_tool_batch_option_name( $tool_id );
		$totals = null;
		foreach ( array( 1, 2, 1000 ) as $size ) {
			$this->size = $size;
			// A preview neither resumes nor repairs even malformed Run progress.
			update_option( $option, array( 'cursor' => 'invalid', 'upper_id' => 0 ), false );
			$before = $this->snapshot();
			$writes = array();
			$capture = static function ( $query ) use ( &$writes ) {
				if ( preg_match( '/^\s*(INSERT|UPDATE|DELETE|REPLACE|ALTER|CREATE|DROP|TRUNCATE)\b/i', $query ) ) {
					$writes[] = $query;
				}
				return $query;
			};
			add_filter( 'query', $capture );
			try {
				$result = call_user_func_array( $callback, $args );
			} finally {
				remove_filter( 'query', $capture );
			}
			$this->assertSame( array(), $writes );
			$this->assertSame( $before, $this->snapshot() );
			$this->assertSame( 0, $result['code'] );
			$this->assertTrue( $result['complete'] );
			$this->assertFalse( $result['restarted'] );
			$this->assertGreaterThan( 0, $result['batches'] );
			unset( $result['batches'] );
			if ( null === $totals ) {
				$totals = $result;
			} else {
				$this->assertSame( $totals, $result );
			}
		}
	}
	/** @dataProvider workers */
	public function test_full_preview_totals_match_a_completed_run( $tool_id, $callback, $args ) {
		$this->fixture();
		$this->size = 2;
		$args = $this->arguments( $tool_id, $callback, $args );
		$preview_args = array_merge( $args, array( true ) );
		$before = $this->snapshot();
		$preview = call_user_func_array( $callback, $preview_args );
		$this->assertSame( $before, $this->snapshot() );
		$this->assertSame( 0, $preview['code'] );
		$this->assertTrue( $preview['complete'] );
		$totals = array();
		foreach ( $preview as $key => $value ) {
			if ( is_int( $value ) && ! in_array( $key, array( 'code', 'batches' ), true ) ) {
				$totals[ $key ] = 0;
			}
		}
		$actual_batches = 0;
		for ( $batch = 0; $batch < 1000; ++$batch ) {
			$run = call_user_func_array( $callback, $args );
			++$actual_batches;
			$this->assertSame( 0, $run['code'] );
			foreach ( $totals as $key => $value ) {
				$totals[ $key ] += $run[ $key ];
			}
			if ( $run['complete'] ) {
				break;
			}
		}
		$this->assertTrue( $run['complete'] );
		$this->assertSame( $actual_batches, $preview['batches'] );
		foreach ( $totals as $key => $value ) {
			$this->assertSame( $preview[ $key ], $value, $key );
		}
	}

	/** @dataProvider workers */
	public function test_browser_preview_matches_the_full_preview_across_bounded_requests( $tool_id, $callback, $args ) {
		$this->fixture();
		$this->size = 2;
		$args       = $this->arguments( $tool_id, $callback, $args );
		$args[]     = true;
		$expected   = call_user_func_array( $callback, $args );
		$before     = $this->snapshot();
		$requests   = 0;

		bbp_admin_repair_tool_execution_context( 'dry-run', 'browser-' . $tool_id );
		try {
			for ( $i = 0; $i < 1000; ++$i ) {
				$result = call_user_func_array( $callback, $args );
				++$requests;
				$this->assertSame( 0, $result['code'] );
				if ( $result['complete'] ) {
					break;
				}
			}
		} finally {
			bbp_admin_repair_tool_execution_context( 'run', '' );
		}

		$this->assertTrue( $result['complete'] );
		$this->assertGreaterThan( 1, $requests );
		$this->assertGreaterThanOrEqual( $result['batches'], $requests );
		$this->assertSame( $expected, $result );
		$this->assertSame( $before, $this->snapshot() );
	}

	/** @dataProvider workers */
	public function test_every_browser_preview_uses_bounded_ajax_requests( $tool_id, $callback, $args ) {
		$this->fixture();
		$old_user = get_current_user_id();
		$user_id  = $this->factory->user->create( array( 'role' => 'administrator' ) );
		bbp_set_user_role( $user_id, bbp_get_keymaster_role() );
		$this->set_current_user( $user_id );
		$this->size = 2;
		$args       = $this->arguments( $tool_id, $callback, $args );
		$args[]     = true;
		$before     = $this->snapshot();
		$expected   = call_user_func_array( $callback, $args );
		$old_tools  = bbp_admin()->tools;
		$requests   = 0;
		$state      = false;

		bbp_admin()->tools = array();
		bbp_register_default_repair_tools();
		$tools = bbp_get_admin_repair_tools();
		$this->assertArrayHasKey( $tool_id, $tools );
		$tool  = $tools[ $tool_id ];
		$state = bbp_admin_repair_tool_save_run_state( $tool['type'], array( $tool_id ), '', 'running', false, 'dry-run' );
		$this->assertIsArray( $state );

		try {
			for ( $i = 0; $i < 1000; ++$i ) {
				$response = $this->ajax( $tool['type'], $tool_id, $state['id'] );
				++$requests;
				$this->assertTrue( $response['success'], wp_json_encode( $response ) );
				if ( $response['data']['complete'] ) {
					break;
				}
			}
		} finally {
			if ( $state && bbp_admin_repair_tool_get_run_state( $tool['type'], 'dry-run' ) ) {
				bbp_admin_repair_tool_discard_run( $tool['type'], $state['id'], 'dry-run' );
			}
			bbp_admin()->tools = $old_tools;
			$this->set_current_user( $old_user );
		}

		$this->assertTrue( $response['data']['complete'] );
		$this->assertGreaterThan( 1, $requests );
		$this->assertSame( $expected['batches'], $response['data']['batches'] );
		$this->assertStringContainsString( 'Dry run complete! No changes were made.', $response['data']['message'] );
		$this->assertSame( $before, $this->snapshot() );
	}

	/**
	 * Call the tools AJAX handler while capturing its JSON response.
	 */
	private function ajax( $type, $tool, $run_id ) {
		$old_post    = $_POST;
		$old_request = $_REQUEST;
		$old_method  = isset( $_SERVER['REQUEST_METHOD'] ) ? $_SERVER['REQUEST_METHOD'] : null;
		$old_tools   = bbp_admin()->tools;
		$body        = '';
		$level       = ob_get_level();
		$die_handler = function() {
			return function() {
				throw new BBP_Tests_Admin_Full_Previews_Ajax_Exit();
			};
		};

		$_POST = array(
			'action'     => 'bbp_tools_process',
			'nonce'      => wp_create_nonce( 'bbpress-tools-process' ),
			'type'       => $type,
			'toolAction' => 'dry-run',
			'tool'       => $tool,
			'run'        => $run_id
		);
		$_REQUEST                  = $_POST;
		$_SERVER['REQUEST_METHOD'] = 'POST';
		bbp_admin()->tools         = array();
		add_filter( 'wp_doing_ajax', '__return_true' );
		add_filter( 'wp_die_ajax_handler', $die_handler );
		ob_start();

		try {
			bbp_admin_repair_ajax_handler();
			$this->fail( 'Expected the AJAX response to terminate.' );
		} catch ( BBP_Tests_Admin_Full_Previews_Ajax_Exit $error ) {
			$body = ob_get_clean();
		} finally {
			while ( ob_get_level() > $level ) {
				ob_end_clean();
			}
			remove_filter( 'wp_die_ajax_handler', $die_handler );
			remove_filter( 'wp_doing_ajax', '__return_true' );
			$_POST             = $old_post;
			$_REQUEST          = $old_request;
			bbp_admin()->tools = $old_tools;

			if ( null === $old_method ) {
				unset( $_SERVER['REQUEST_METHOD'] );
			} else {
				$_SERVER['REQUEST_METHOD'] = $old_method;
			}
		}

		return json_decode( $body, true );
	}

}
