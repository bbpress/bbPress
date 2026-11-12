<?php

/**
 * Tests for built-in tools that use resumable batches.
 *
 * @group tools
 * @group tool-batches
 */
class BBP_Tests_Admin_Tool_Batches extends BBP_UnitTestCase {

	public function setUp(): void {
		parent::setUp();

		if ( ! function_exists( 'bbp_admin' ) ) {
			require_once BBP_PLUGIN_DIR . 'includes/admin/actions.php';
		}
		bbp_admin();
		require_once BBP_PLUGIN_DIR . 'includes/admin/tools/common.php';
		require_once BBP_PLUGIN_DIR . 'includes/admin/tools/repair.php';
		require_once BBP_PLUGIN_DIR . 'includes/admin/tools/upgrade.php';

		remove_action( 'wpmu_new_blog', 'bbp_new_site', 10 );
		add_filter( 'bbp_admin_repair_tool_batch_size', array( $this, 'batch_size' ) );
	}

	public function tearDown(): void {
		remove_filter( 'bbp_admin_repair_tool_batch_size', array( $this, 'batch_size' ) );
		add_action( 'wpmu_new_blog', 'bbp_new_site', 10, 6 );
		parent::tearDown();
	}

	public function batch_size() {
		return 2;
	}

	public function upgrade_tools() {
		return array(
			'favorites' => array( 'bbp-user-favorites-move', '_bbp_favorites', '_bbp_favorite', 'bbp_admin_upgrade_user_favorites' ),
			'topic subscriptions' => array( 'bbp-user-topic-subscriptions-move', '_bbp_subscriptions', '_bbp_subscription', 'bbp_admin_upgrade_user_topic_subscriptions' ),
			'forum subscriptions' => array( 'bbp-user-forum-subscriptions-move', '_bbp_forum_subscriptions', '_bbp_subscription', 'bbp_admin_upgrade_user_forum_subscriptions' ),
		);
	}

	public function user_count_tools() {
		return array(
			'topics'  => array( 'bbp-user-topics', 'topic', '_bbp_topic_count', 'bbp_admin_repair_user_topic_count' ),
			'replies' => array( 'bbp-user-replies', 'reply', '_bbp_reply_count', 'bbp_admin_repair_user_reply_count' ),
		);
	}

	private function user_count_posts( $type, $user_id, $number = 1, $status = 'publish' ) {
		return $this->factory->post->create_many( $number, array(
			'post_type'   => ( 'topic' === $type ) ? bbp_get_topic_post_type() : bbp_get_reply_post_type(),
			'post_author' => $user_id,
			'post_status' => $status,
		) );
	}

	private function user_count_batch( $tool_id, $type, $key, $dry_run = false ) {
		return bbp_admin_repair_user_post_count_batch(
			$tool_id,
			( 'topic' === $type ) ? bbp_get_topic_post_type() : bbp_get_reply_post_type(),
			( 'topic' === $type ) ? bbp_get_public_topic_statuses() : bbp_get_public_reply_statuses(),
			$key,
			$dry_run
		);
	}

	private function stored_user_counts( $key ) {
		$db = bbp_db();
		return $db->get_results( $db->prepare( "SELECT user_id, meta_value FROM {$db->usermeta} WHERE meta_key = %s ORDER BY user_id, umeta_id", $key ), ARRAY_A );
	}

	private function expected_user_counts( $type ) {
		$db       = bbp_db();
		$statuses = ( 'topic' === $type ) ? bbp_get_public_topic_statuses() : bbp_get_public_reply_statuses();
		$args     = array_merge( array( ( 'topic' === $type ) ? bbp_get_topic_post_type() : bbp_get_reply_post_type() ), $statuses );
		$holders  = implode( ', ', array_fill( 0, count( $statuses ), '%s' ) );
		return $db->get_results( $db->prepare( "SELECT post_author AS user_id, COUNT(*) AS meta_value FROM {$db->posts} WHERE post_type = %s AND post_status IN ({$holders}) AND post_author > 0 GROUP BY post_author ORDER BY post_author", $args ), ARRAY_A );
	}

	/**
	 * @dataProvider user_count_tools
	 */
	public function test_user_count_batches_are_bounded_read_only_and_match_the_original_recount( $tool_id, $type, $suffix, $callback ) {
		$db    = bbp_db();
		$key   = $db->prefix . $suffix;
		$users = $this->factory->user->create_many( 5 );
		foreach ( $users as $user_id ) {
			$this->user_count_posts( $type, $user_id, 2 );
			$this->user_count_posts( $type, $user_id, 1, bbp_get_spam_status_id() );
			update_user_meta( $user_id, $key, 99 );
		}
		delete_user_meta( $users[0], $key );
		$valid_id = add_user_meta( $users[0], $key, 2 );
		add_user_meta( $users[1], $key, '02' );
		add_user_meta( $users[2], strtoupper( $key ), 2 );
		add_user_meta( $users[3], $key . ' ', 2 );
		add_user_meta( $users[4], 'another_site' . $suffix, 77 );
		$db->insert( $db->usermeta, array( 'user_id' => 0, 'meta_key' => $key, 'meta_value' => 99 ) );
		$this->user_count_posts( $type, 999999, 1 );
		$expected  = $this->expected_user_counts( $type );
		$mutations = 0;
		$added     = static function ( $mid, $user_id, $meta_key ) use ( $key, &$mutations ) {
			if ( $key === $meta_key ) {
				++$mutations;
			}
		};
		$deleted = static function ( $mids, $user_id, $meta_key ) use ( $suffix, &$mutations ) {
			if ( false !== stripos( $meta_key, $suffix ) ) {
				$mutations += count( (array) $mids );
			}
		};
		add_action( 'added_user_meta', $added, 10, 3 );
		add_action( 'deleted_user_meta', $deleted, 10, 3 );
		try {
			for ( $i = 0; $i < 30; ++$i ) {
				$before  = $this->stored_data();
				$preview = $this->user_count_batch( $tool_id, $type, $key, true );
				$this->assertSame( 0, $preview['code'] );
				$this->assertSame( $before, $this->stored_data() );
				$mutations = 0;
				$result = $this->user_count_batch( $tool_id, $type, $key );
				$this->assertSame( 0, $result['code'] );
				$this->assertGreaterThanOrEqual( $result['repaired'], $preview['repaired'] );
				$this->assertTrue( $preview['complete'] );
				$this->assertLessThanOrEqual( 2, $mutations );
				$this->assertNotFalse( get_metadata_by_mid( 'user', $valid_id ) );
				if ( $result['complete'] ) {
					break;
				}
			}
		} finally {
			remove_action( 'added_user_meta', $added, 10 );
			remove_action( 'deleted_user_meta', $deleted, 10 );
		}
		$this->assertTrue( $result['complete'] );
		$this->assertSame( $expected, $this->stored_user_counts( $key ) );
		$this->assertSame( '77', get_user_meta( $users[4], 'another_site' . $suffix, true ) );
		$before = $this->stored_data();
		$this->finish( $callback );
		$this->assertSame( $before, $this->stored_data() );
	}

	/**
	 * @dataProvider user_count_tools
	 */
	public function test_large_duplicate_count_sets_resume_at_the_same_user( $tool_id, $type, $suffix, $callback ) {
		$key     = bbp_db()->prefix . $suffix;
		$user_id = $this->factory->user->create();
		$this->user_count_posts( $type, $user_id );
		delete_user_meta( $user_id, $key );
		$valid_id = add_user_meta( $user_id, $key, 1 );
		for ( $i = 0; $i < 9; ++$i ) {
			add_user_meta( $user_id, $key, 99 );
		}
		$result = call_user_func( $callback );
		$this->assertSame( 0, $result[0] );
		$this->assertFalse( $result['complete'] );
		$this->assertCount( 8, get_user_meta( $user_id, $key, false ) );
		$this->assertSame( $user_id, get_option( bbp_admin_repair_tool_batch_option_name( $tool_id ) )['cursor'] );
		$this->finish( $callback );
		$this->assertSame( array( '1' ), get_user_meta( $user_id, $key, false ) );
		$this->assertNotFalse( get_metadata_by_mid( 'user', $valid_id ) );
	}

	/**
	 * @dataProvider user_count_tools
	 */
	public function test_new_count_authors_wait_for_the_next_run( $tool_id, $type, $suffix, $callback ) {
		$key   = bbp_db()->prefix . $suffix;
		$users = $this->factory->user->create_many( 3 );
		foreach ( $users as $user_id ) {
			$this->user_count_posts( $type, $user_id );
			delete_user_meta( $user_id, $key );
		}
		$this->assertFalse( call_user_func( $callback )['complete'] );
		$new_user = $this->factory->user->create();
		$this->user_count_posts( $type, $new_user );
		delete_user_meta( $new_user, $key );
		$this->finish( $callback );
		$this->assertSame( array(), get_user_meta( $new_user, $key, false ) );
		$this->finish( $callback );
		$this->assertSame( array( '1' ), get_user_meta( $new_user, $key, false ) );
	}

	public function malformed_user_count_checkpoints() {
		$cases = array();
		foreach ( $this->user_count_tools() as $name => $tool ) {
			foreach ( $this->checkpoint_corruptions() as $case => $change ) {
				$cases[ $name . ': ' . $case ] = array_merge( $tool, $change );
			}
		}
		return $cases;
	}

	/**
	 * @dataProvider malformed_user_count_checkpoints
	 */
	public function test_malformed_user_count_progress_restarts_without_preview_writes( $tool_id, $type, $suffix, $callback, $field, $value ) {
		$users = $this->factory->user->create_many( 4 );
		foreach ( $users as $user_id ) {
			$this->user_count_posts( $type, $user_id );
		}
		$option = bbp_admin_repair_tool_batch_option_name( $tool_id );
		$this->assertFalse( call_user_func( $callback )['complete'] );
		$this->corrupt_checkpoint( $option, $field, $value );
		$new_user = $this->factory->user->create();
		$this->user_count_posts( $type, $new_user );
		$before  = $this->stored_data();
		$preview = call_user_func( $callback . '_dry_run' );
		$this->assertStringNotContainsString( 'Saved progress is invalid.', $preview[1] );
		$this->assertSame( $before, $this->stored_data() );
		$result = call_user_func( $callback );
		$this->assertStringContainsString( 'Saved progress was invalid.', $result[1] );
		$this->finish( $callback );
		$this->assertSame( $this->expected_user_counts( $type ), $this->stored_user_counts( bbp_db()->prefix . $suffix ) );
	}

	private function finish( $callback ) {
		for ( $i = 0; $i < 30; ++$i ) {
			$result = bbp_admin_repair_tool_normalize_result( call_user_func( $callback ) );
			$this->assertTrue( $result['success'], $result['message'] );
			if ( $result['complete'] ) {
				return;
			}
		}
		$this->fail( 'The bounded tool did not finish.' );
	}

	private function missing_user_counts( $type, $key, $number = 5 ) {
		$users = $this->factory->user->create_many( $number );
		foreach ( $users as $user_id ) {
			$this->user_count_posts( $type, $user_id );
			delete_user_meta( $user_id, $key );
		}
		return $users;
	}

	/**
	 * @dataProvider user_count_tools
	 */
	public function test_user_count_write_failures_preserve_progress_and_retry_without_duplicates( $tool_id, $type, $suffix, $callback ) {
		$key   = bbp_db()->prefix . $suffix;
		$users = $this->missing_user_counts( $type, $key, 3 );
		$fail  = static function ( $check, $user_id, $meta_key ) use ( $key, $users ) {
			return ( $key === $meta_key ) && ( $users[1] === $user_id ) ? false : $check;
		};
		add_filter( 'add_user_metadata', $fail, 10, 3 );
		try {
			$result = call_user_func( $callback );
			$this->assertSame( BBP_Admin_Batch_Worker::RESULT_FAILED, $result[0] );
			$this->assertSame( 'metadata-write', $result['error'] );
			$this->assertFalse( $result['success'] );
			$this->assertFalse( $result['complete'] );
			$this->assertSame( 0, get_option( bbp_admin_repair_tool_batch_option_name( $tool_id ) )['cursor'] );
			$this->assertSame( array( '1' ), get_user_meta( $users[0], $key, false ) );
			$this->assertFalse( bbp_admin_repair_tool_run_automatically( $tool_id, $callback ) );
			$this->assertContains( $tool_id, bbp_get_pending_upgrades() );
		} finally {
			remove_filter( 'add_user_metadata', $fail, 10 );
		}
		$this->assertTrue( bbp_admin_repair_tool_run_automatically( $tool_id, $callback ) );
		$this->assertNotContains( $tool_id, bbp_get_pending_upgrades() );
		$this->assertSame( $this->expected_user_counts( $type ), $this->stored_user_counts( $key ) );
	}

	/**
	 * @dataProvider user_count_tools
	 */
	public function test_user_count_partial_cleanup_failures_keep_a_correct_row_for_retry( $tool_id, $type, $suffix, $callback ) {
		$key     = bbp_db()->prefix . $suffix;
		$user_id = $this->missing_user_counts( $type, $key, 1 )[0];
		$keep_id = add_user_meta( $user_id, $key, 1 );
		$old_ids = array();
		for ( $i = 0; $i < 3; ++$i ) {
			$old_ids[] = add_user_meta( $user_id, $key, 99 );
		}
		$fail = static function ( $delete, $meta_id ) use ( $old_ids ) {
			return ( (int) $meta_id === $old_ids[1] ) ? false : $delete;
		};
		add_filter( 'delete_user_metadata_by_mid', $fail, 10, 2 );
		try {
			$result = call_user_func( $callback );
			$this->assertSame( BBP_Admin_Batch_Worker::RESULT_FAILED, $result[0] );
			$this->assertSame( 'metadata-write', $result['error'] );
			$this->assertFalse( $result['success'] );
			$this->assertSame( 0, get_option( bbp_admin_repair_tool_batch_option_name( $tool_id ) )['cursor'] );
			$this->assertNotFalse( get_metadata_by_mid( 'user', $keep_id ) );
			$this->assertFalse( get_metadata_by_mid( 'user', $old_ids[2] ) );
		} finally {
			remove_filter( 'delete_user_metadata_by_mid', $fail, 10 );
		}
		$this->finish( $callback );
		$this->assertSame( array( '1' ), get_user_meta( $user_id, $key, false ) );
		$this->assertNotFalse( get_metadata_by_mid( 'user', $keep_id ) );
	}

	/**
	 * @dataProvider user_count_tools
	 */
	public function test_initial_user_count_checkpoint_failures_write_no_counts( $tool_id, $type, $suffix, $callback ) {
		$key    = bbp_db()->prefix . $suffix;
		$this->missing_user_counts( $type, $key, 3 );
		$option = bbp_admin_repair_tool_batch_option_name( $tool_id );
		$before = $this->stored_data();
		$fail   = static function ( $value, $old ) {
			return $old;
		};
		add_filter( 'pre_update_option_' . $option, $fail, 10, 2 );
		try {
			$result = call_user_func( $callback );
			$this->assertSame( BBP_Admin_Batch_Worker::RESULT_FAILED, $result[0] );
			$this->assertSame( 'progress', $result['error'] );
			$this->assertFalse( $result['success'] );
			$this->assertSame( $before, $this->stored_data() );
		} finally {
			remove_filter( 'pre_update_option_' . $option, $fail, 10 );
		}
		$this->finish( $callback );
		$this->assertSame( $this->expected_user_counts( $type ), $this->stored_user_counts( $key ) );
	}

	/**
	 * @dataProvider user_count_tools
	 */
	public function test_user_count_progress_save_failures_replay_safely( $tool_id, $type, $suffix, $callback ) {
		$key    = bbp_db()->prefix . $suffix;
		$this->missing_user_counts( $type, $key );
		$option = bbp_admin_repair_tool_batch_option_name( $tool_id );
		$this->assertFalse( call_user_func( $callback )['complete'] );
		$state = get_option( $option );
		$fail  = static function ( $value, $old ) {
			return $old;
		};
		add_filter( 'pre_update_option_' . $option, $fail, 10, 2 );
		try {
			$result = call_user_func( $callback );
			$this->assertSame( BBP_Admin_Batch_Worker::RESULT_FAILED, $result[0] );
			$this->assertSame( 'progress', $result['error'] );
			$this->assertFalse( $result['success'] );
			$this->assertSame( $state, get_option( $option ) );
		} finally {
			remove_filter( 'pre_update_option_' . $option, $fail, 10 );
		}
		$this->finish( $callback );
		$this->assertSame( $this->expected_user_counts( $type ), $this->stored_user_counts( $key ) );
	}

	/**
	 * @dataProvider user_count_tools
	 */
	public function test_user_count_final_checkpoint_delete_failures_remain_resumable( $tool_id, $type, $suffix, $callback ) {
		$db     = bbp_db();
		$key    = $db->prefix . $suffix;
		$this->missing_user_counts( $type, $key, 3 );
		$option = bbp_admin_repair_tool_batch_option_name( $tool_id );
		$this->assertFalse( call_user_func( $callback )['complete'] );
		$state = get_option( $option );
		$fail  = static function ( $query ) use ( $db, $option ) {
			return ( 0 === strpos( $query, 'DELETE FROM' ) ) && ( false !== strpos( $query, $db->options ) ) && ( false !== strpos( $query, $option ) )
				? 'DELETE FROM bbp_missing_count_options'
				: $query;
		};
		$suppress = $db->suppress_errors( true );
		add_filter( 'query', $fail );
		try {
			$this->assertFalse( bbp_admin_repair_tool_run_automatically( $tool_id, $callback ) );
			$this->assertContains( $tool_id, bbp_get_pending_upgrades() );
			$this->assertSame( $state, get_option( $option ) );
		} finally {
			remove_filter( 'query', $fail );
			$db->suppress_errors( $suppress );
		}
		$this->assertTrue( bbp_admin_repair_tool_run_automatically( $tool_id, $callback ) );
		$this->assertNotContains( $tool_id, bbp_get_pending_upgrades() );
		$this->assertSame( $this->expected_user_counts( $type ), $this->stored_user_counts( $key ) );
	}

	/**
	 * @dataProvider user_count_tools
	 */
	public function test_user_counts_complete_automatically_and_remain_resumable_after_budget_exhaustion( $tool_id, $type, $suffix, $callback ) {
		$key = bbp_db()->prefix . $suffix;
		$this->missing_user_counts( $type, $key );
		add_filter( 'bbp_admin_repair_tool_automatic_batch_limit', '__return_true' );
		try {
			$this->assertFalse( bbp_admin_repair_tool_run_automatically( $tool_id, $callback ) );
			$this->assertContains( $tool_id, bbp_get_pending_upgrades() );
			$this->assertCount( 2, $this->stored_user_counts( $key ) );
		} finally {
			remove_filter( 'bbp_admin_repair_tool_automatic_batch_limit', '__return_true' );
		}
		$this->assertTrue( bbp_admin_repair_tool_run_automatically( $tool_id, $callback ) );
		$this->assertNotContains( $tool_id, bbp_get_pending_upgrades() );
		$this->assertFalse( get_option( bbp_admin_repair_tool_batch_option_name( $tool_id ), false ) );
		$this->assertSame( $this->expected_user_counts( $type ), $this->stored_user_counts( $key ) );
	}

	/**
	 * @dataProvider user_count_tools
	 */
	public function test_user_count_read_failures_preserve_progress_and_preview_data( $tool_id, $type, $suffix, $callback ) {
		$db  = bbp_db();
		$key = $db->prefix . $suffix;
		$this->missing_user_counts( $type, $key, 3 );
		$this->assertFalse( call_user_func( $callback )['complete'] );
		$before = $this->stored_data();
		$fail = static function ( $query ) use ( $db ) {
			return ( 0 === strpos( $query, "SELECT COUNT(*) FROM {$db->posts}" ) )
				? 'SELECT ID FROM bbp_missing_user_count_posts'
				: $query;
		};
		$suppress = $db->suppress_errors( true );
		add_filter( 'query', $fail );
		try {
			foreach ( array( $callback, $callback . '_dry_run' ) as $call ) {
				$result = call_user_func( $call );
				$this->assertSame( BBP_Admin_Batch_Worker::RESULT_FAILED, $result[0] );
				$this->assertSame( 'source-read', $result['error'] );
				$this->assertFalse( $result['success'] );
				$this->assertFalse( $result['complete'] );
				$this->assertSame( $before, $this->stored_data() );
			}
		} finally {
			remove_filter( 'query', $fail );
			$db->suppress_errors( $suppress );
		}
		$this->finish( $callback );
		$this->assertSame( $this->expected_user_counts( $type ), $this->stored_user_counts( $key ) );
	}

	/**
	 * @dataProvider user_count_tools
	 */
	public function test_user_count_status_filters_use_database_collation( $tool_id, $type, $suffix, $callback ) {
		$key     = bbp_db()->prefix . $suffix;
		$user_id = $this->factory->user->create();
		$this->user_count_posts( $type, $user_id );
		$this->user_count_posts( $type, $user_id, 1, bbp_get_closed_status_id() );
		$this->user_count_posts( $type, $user_id, 1, bbp_get_spam_status_id() );
		delete_user_meta( $user_id, $key );
		$filter = static function () {
			return array( 'PuBLish', 'CLOSED' );
		};
		$hook = 'bbp_get_public_' . $type . '_statuses';
		add_filter( $hook, $filter );
		try {
			$before = $this->stored_data();
			$this->assertSame( 0, call_user_func( $callback . '_dry_run' )[0] );
			$this->assertSame( $before, $this->stored_data() );
			$this->finish( $callback );
			$this->assertSame( array( '2' ), get_user_meta( $user_id, $key, false ) );
		} finally {
			remove_filter( $hook, $filter );
		}
	}

	/**
	 * @dataProvider user_count_tools
	 */
	public function test_user_counts_stay_within_the_current_multisite_blog( $tool_id, $type, $suffix, $callback ) {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'Requires multisite tables.' );
		}
		$db       = bbp_db();
		$user_id  = $this->factory->user->create();
		$main_key = $db->prefix . $suffix;
		$this->user_count_posts( $type, $user_id, 3 );
		update_user_meta( $user_id, $main_key, 77 );
		$blog_id = $this->factory->blog->create();
		switch_to_blog( $blog_id );
		try {
			$key = bbp_db()->prefix . $suffix;
			$this->assertNotSame( $main_key, $key );
			$this->user_count_posts( $type, $user_id );
			update_user_meta( $user_id, $key, 99 );
			$this->finish( $callback );
			$this->assertSame( array( '1' ), get_user_meta( $user_id, $key, false ) );
			$this->assertSame( array( '77' ), get_user_meta( $user_id, $main_key, false ) );
		} finally {
			restore_current_blog();
		}
	}

	/**
	 * @dataProvider user_count_tools
	 */
	public function test_clean_user_count_preview_batches_do_not_claim_later_users_are_correct( $tool_id, $type, $suffix, $callback ) {
		$key   = bbp_db()->prefix . $suffix;
		$users = $this->missing_user_counts( $type, $key, 4 );
		foreach ( $users as $user_id ) {
			update_user_meta( $user_id, $key, 1 );
		}
		update_user_meta( $users[3], $key, 99 );
		$before  = $this->stored_data();
		$preview = call_user_func( $callback . '_dry_run' );
		$this->assertSame( 0, $preview[0] );
		$this->assertTrue( $preview['complete'] );
		$this->assertStringNotContainsString( 'This batch:', $preview[1] );
		$this->assertStringContainsString( 'repaired for 1 user.', $preview[1] );
		$this->assertStringNotContainsString( '0 user ' . $type . ' counts to repair.', $preview[1] );
		$this->assertSame( $before, $this->stored_data() );
		$this->assertFalse( call_user_func( $callback )['complete'] );
		$preview = call_user_func( $callback . '_dry_run' );
		$this->assertStringContainsString( 'repaired for 1 user.', $preview[1] );
		$this->finish( $callback );
		$this->assertSame( $this->expected_user_counts( $type ), $this->stored_user_counts( $key ) );
	}

	public function user_count_boundary_sizes() {
		$cases = array();
		foreach ( $this->user_count_tools() as $name => $tool ) {
			foreach ( array( 1, 1000 ) as $size ) {
				$cases[ $name . ': ' . $size ] = array_merge( $tool, array( $size ) );
			}
		}
		return $cases;
	}

	/**
	 * @dataProvider user_count_boundary_sizes
	 */
	public function test_user_count_boundary_batch_sizes_limit_writes_and_finish_duplicate_cleanup( $tool_id, $type, $suffix, $callback, $size ) {
		$db      = bbp_db();
		$key     = $db->prefix . $suffix;
		$user_id = $this->missing_user_counts( $type, $key, 1 )[0];
		for ( $i = 0; $i <= $size; ++$i ) {
			add_user_meta( $user_id, $key, 99 );
		}
		$limit = static function () use ( $size ) {
			return $size;
		};
		add_filter( 'bbp_admin_repair_tool_batch_size', $limit, 20 );
		try {
			for ( $batch = 0; $batch < 5; ++$batch ) {
				$sql        = $db->prepare( "SELECT umeta_id FROM {$db->usermeta} WHERE user_id = %d AND meta_key = %s ORDER BY umeta_id", $user_id, $key );
				$before_ids = $db->get_col( $sql );
				$before     = $this->stored_data();
				$preview    = $this->user_count_batch( $tool_id, $type, $key, true );
				$this->assertSame( $before, $this->stored_data() );
				$result = $this->assertMetadataWritesBounded( 'user', $size, function () use ( $tool_id, $type, $key ) {
					return $this->user_count_batch( $tool_id, $type, $key );
				} );
				$this->assertSame( 0, $result['code'] );
				$this->assertGreaterThanOrEqual( $result['repaired'], $preview['repaired'] );
				$this->assertTrue( $preview['complete'] );
				$after_ids = $db->get_col( $sql );
				$mutations = count( array_diff( $before_ids, $after_ids ) ) + count( array_diff( $after_ids, $before_ids ) );
				$this->assertLessThanOrEqual( $size, $mutations );
				if ( 0 === $batch ) {
					$this->assertFalse( $result['complete'] );
					$this->assertCount( 2, $after_ids );
					$this->assertSame( $user_id, get_option( bbp_admin_repair_tool_batch_option_name( $tool_id ) )['cursor'] );
				}
				if ( $result['complete'] ) {
					break;
				}
			}
			$this->assertTrue( $result['complete'] );
			$this->assertSame( array( '1' ), get_user_meta( $user_id, $key, false ) );
			$this->assertFalse( get_option( bbp_admin_repair_tool_batch_option_name( $tool_id ), false ) );
		} finally {
			remove_filter( 'bbp_admin_repair_tool_batch_size', $limit, 20 );
		}
	}

	private function stored_data() {
		$db     = bbp_db();
		$stored = array();
		foreach ( array( 'postmeta' => 'meta_id', 'usermeta' => 'umeta_id', 'options' => 'option_id' ) as $table => $id ) {
			$rows             = $db->get_results( "SELECT * FROM {$db->$table} ORDER BY {$id}", ARRAY_A );
			$stored[ $table ] = hash( 'sha256', serialize( $rows ) );
		}
		return $stored;
	}

	/**
	 * @dataProvider upgrade_tools
	 */
	public function test_large_user_lists_are_bounded_and_previews_do_not_advance_them( $tool_id, $old_key, $new_key, $callback ) {
		$db      = bbp_db();
		$user_id = $this->factory->user->create();
		$posts   = $this->factory->post->create_many( 5 );
		$option  = bbp_admin_repair_tool_batch_option_name( $tool_id );

		add_user_meta( $user_id, $db->prefix . $old_key, implode( ',', $posts ) . ',invalid,' . $posts[0] );
		add_user_meta( $user_id, $db->prefix . $old_key, implode( ',', $posts ) );
		bbp_add_pending_upgrade( $tool_id );
		$before  = $this->stored_data();
		$preview = call_user_func( $callback . '_dry_run' );
		$this->assertTrue( $preview['complete'] );
		$this->assertStringNotContainsString( 'in this batch', $preview[1] );
		$this->assertSame( $before, $this->stored_data() );

		$result = call_user_func( $callback );
		$this->assertFalse( $result['complete'] );
		$this->assertSame( 2, get_option( $option )['offset'] );
		$this->assertSame( array(), get_post_meta( $posts[2], $new_key, false ) );

		$before = $this->stored_data();
		call_user_func( $callback . '_dry_run' );
		$this->assertSame( $before, $this->stored_data() );

		$previous_count = 2;
		for ( $i = 0; $i < 10; ++$i ) {
			$result = call_user_func( $callback );
			$count  = (int) $db->get_var( $db->prepare( "SELECT COUNT(*) FROM {$db->postmeta} WHERE meta_key = %s AND meta_value = %d", $new_key, $user_id ) );
			$this->assertLessThanOrEqual( 2, $count - $previous_count );
			$previous_count = $count;
			if ( $result['complete'] ) {
				break;
			}
		}
		$this->assertTrue( $result['complete'] );
		$this->assertFalse( get_option( $option, false ) );
		foreach ( $posts as $post_id ) {
			$this->assertSame( array( (string) $user_id ), get_post_meta( $post_id, $new_key, false ) );
		}

		// A complete rerun remains harmless even with duplicate source rows
		$this->finish( $callback );
		$this->assertSame( 5, (int) $db->get_var( $db->prepare( "SELECT COUNT(*) FROM {$db->postmeta} WHERE meta_key = %s AND meta_value = %d", $new_key, $user_id ) ) );
	}

	/**
	 * @dataProvider upgrade_tools
	 */
	public function test_empty_source_rows_consume_the_batch_budget( $tool_id, $old_key, $new_key, $callback ) {
		$user_id = $this->factory->user->create();
		$post_id = $this->factory->post->create();
		$key     = bbp_db()->prefix . $old_key;
		for ( $i = 0; $i < 3; ++$i ) {
			add_user_meta( $user_id, $key, 'invalid,0' );
		}
		add_user_meta( $user_id, $key, $post_id );
		$this->assertFalse( call_user_func( $callback )['complete'] );
		$this->assertSame( array(), get_post_meta( $post_id, $new_key, false ) );
		$this->finish( $callback );
		$this->assertSame( array( (string) $user_id ), get_post_meta( $post_id, $new_key, false ) );
	}

	/**
	 * @dataProvider upgrade_tools
	 */
	public function test_new_source_rows_wait_for_the_next_run( $tool_id, $old_key, $new_key, $callback ) {
		$user_id = $this->factory->user->create();
		$posts   = $this->factory->post->create_many( 4 );
		$key     = bbp_db()->prefix . $old_key;
		add_user_meta( $user_id, $key, implode( ',', array_slice( $posts, 0, 3 ) ) );
		$this->assertFalse( call_user_func( $callback )['complete'] );
		add_user_meta( $user_id, $key, $posts[3] );

		$this->finish( $callback );
		$this->assertSame( array(), get_post_meta( $posts[3], $new_key, false ) );
		$this->finish( $callback );
		$this->assertSame( array( (string) $user_id ), get_post_meta( $posts[3], $new_key, false ) );
	}

	/**
	 * @dataProvider upgrade_tools
	 */
	public function test_changed_source_lists_restart_the_current_row( $tool_id, $old_key, $new_key, $callback ) {
		$user_id = $this->factory->user->create();
		$posts   = $this->factory->post->create_many( 5 );
		$key     = bbp_db()->prefix . $old_key;
		add_user_meta( $user_id, $key, implode( ',', $posts ) );
		call_user_func( $callback );
		update_user_meta( $user_id, $key, implode( ',', array_reverse( $posts ) ) );

		$this->finish( $callback );
		foreach ( $posts as $post_id ) {
			$this->assertSame( array( (string) $user_id ), get_post_meta( $post_id, $new_key, false ) );
		}
	}

	/**
	 * @dataProvider upgrade_tools
	 */
	public function test_legacy_cleanup_cannot_remove_an_incomplete_migrations_source( $tool_id, $old_key, $new_key, $callback ) {
		$user_id = $this->factory->user->create();
		$posts   = $this->factory->post->create_many( 5 );
		$key     = bbp_db()->prefix . $old_key;
		$cleanup = str_replace( 'bbp_admin_upgrade_user_', 'bbp_admin_upgrade_remove_', $callback ) . '_from_usermeta';
		add_user_meta( $user_id, $key, implode( ',', $posts ) );
		$this->assertFalse( call_user_func( $callback )['complete'] );
		$before = $this->stored_data();
		$preview = bbp_admin_repair_tool_normalize_result( call_user_func( $cleanup . '_dry_run' ) );
		$result  = bbp_admin_repair_tool_normalize_result( call_user_func( $cleanup ) );
		$this->assertSame( $before, $this->stored_data() );
		$this->assertFalse( $preview['success'] );
		$this->assertFalse( $result['success'] );

		$this->finish( $callback );
		$this->assertSame( 0, call_user_func( $cleanup )[0] );
		$this->assertSame( array(), get_user_meta( $user_id, $key, false ) );
		foreach ( $posts as $post_id ) {
			$this->assertSame( array( (string) $user_id ), get_post_meta( $post_id, $new_key, false ) );
		}
	}

	/**
	 * @dataProvider upgrade_tools
	 */
	public function test_failed_relationship_writes_can_be_retried_without_duplicates( $tool_id, $old_key, $new_key, $callback ) {
		$user_id = $this->factory->user->create();
		$posts   = $this->factory->post->create_many( 4 );
		$option  = bbp_admin_repair_tool_batch_option_name( $tool_id );
		add_user_meta( $user_id, bbp_db()->prefix . $old_key, implode( ',', $posts ) );
		$fail = static function ( $check, $post_id, $meta_key ) use ( $posts, $new_key ) {
			return ( ( $posts[1] === $post_id ) && ( $new_key === $meta_key ) ) ? false : $check;
		};
		add_filter( 'add_post_metadata', $fail, 10, 3 );
		try {
			$result = call_user_func( $callback );
			$this->assertFalse( $result['success'] );
			$this->assertSame( 0, get_option( $option )['cursor'] );
			$this->assertSame( 0, get_option( $option )['offset'] );
			$this->assertSame( array( (string) $user_id ), get_post_meta( $posts[0], $new_key, false ) );
			$cleanup = str_replace( 'bbp_admin_upgrade_user_', 'bbp_admin_upgrade_remove_', $callback ) . '_from_usermeta';
			$before  = $this->stored_data();
			$this->assertFalse( call_user_func( $cleanup . '_dry_run' )['success'] );
			$this->assertFalse( call_user_func( $cleanup )['success'] );
			$this->assertSame( $before, $this->stored_data() );
		} finally {
			remove_filter( 'add_post_metadata', $fail, 10 );
		}
		$this->finish( $callback );
		foreach ( $posts as $post_id ) {
			$this->assertSame( array( (string) $user_id ), get_post_meta( $post_id, $new_key, false ) );
		}
	}

	/**
	 * @dataProvider upgrade_tools
	 */
	public function test_pending_migrations_block_cleanup_without_a_checkpoint( $tool_id, $old_key, $new_key, $callback ) {
		$user_id = $this->factory->user->create();
		$post_id = $this->factory->post->create();
		$key     = bbp_db()->prefix . $old_key;
		$cleanup = str_replace( 'bbp_admin_upgrade_user_', 'bbp_admin_upgrade_remove_', $callback ) . '_from_usermeta';
		add_user_meta( $user_id, $key, $post_id );
		bbp_add_pending_upgrade( $tool_id );
		$this->assertFalse( get_option( bbp_admin_repair_tool_batch_option_name( $tool_id ), false ) );
		$before = $this->stored_data();
		$this->assertFalse( call_user_func( $cleanup . '_dry_run' )['success'] );
		$this->assertFalse( call_user_func( $cleanup )['success'] );
		$this->assertSame( $before, $this->stored_data() );
		$this->assertTrue( bbp_admin_repair_tool_run_automatically( $tool_id, $callback ) );
		$this->assertSame( 0, call_user_func( $cleanup )[0] );
		$this->assertSame( array( (string) $user_id ), get_post_meta( $post_id, $new_key, false ) );
	}

	/**
	 * @dataProvider upgrade_tools
	 */
	public function test_cleanup_cannot_delete_source_while_its_migration_holds_the_lock( $tool_id, $old_key, $new_key, $callback ) {
		$user_id = $this->factory->user->create();
		$post_id = $this->factory->post->create();
		$key     = bbp_db()->prefix . $old_key;
		$cleanup = str_replace( 'bbp_admin_upgrade_user_', 'bbp_admin_upgrade_remove_', $callback ) . '_from_usermeta';
		add_user_meta( $user_id, $key, $post_id );
		$lock   = bbp_admin_repair_tool_lock( $tool_id );
		$before = $this->stored_data();
		try {
			$this->assertFalse( call_user_func( $cleanup )['success'] );
			$this->assertSame( $before, $this->stored_data() );
			$this->assertSame( $lock['value'], get_option( $lock['name'] ) );
		} finally {
			bbp_admin_repair_tool_unlock( $lock );
		}
		$this->assertTrue( bbp_admin_repair_tool_run_automatically( $tool_id, $callback ) );
		$this->assertSame( 0, call_user_func( $cleanup )[0] );
		$this->assertSame( array( (string) $user_id ), get_post_meta( $post_id, $new_key, false ) );
	}

	/**
	 * @dataProvider upgrade_tools
	 */
	public function test_failed_initial_checkpoint_saves_write_no_relationships_and_keep_cleanup_blocked( $tool_id, $old_key, $new_key, $callback ) {
		$user_id = $this->factory->user->create();
		$post_id = $this->factory->post->create();
		$option  = bbp_admin_repair_tool_batch_option_name( $tool_id );
		$cleanup = str_replace( 'bbp_admin_upgrade_user_', 'bbp_admin_upgrade_remove_', $callback ) . '_from_usermeta';
		add_user_meta( $user_id, bbp_db()->prefix . $old_key, $post_id );
		$fail = static function ( $value, $old_value ) {
			return $old_value;
		};
		add_filter( 'pre_update_option_' . $option, $fail, 10, 2 );
		try {
			$result = call_user_func( $callback );
			$this->assertSame( 5, $result[0] );
			$this->assertFalse( $result['success'] );
			$this->assertSame( array(), get_post_meta( $post_id, $new_key, false ) );
			$this->assertContains( $tool_id, bbp_get_pending_upgrades() );
			$before = $this->stored_data();
			$this->assertFalse( call_user_func( $cleanup . '_dry_run' )['success'] );
			$this->assertFalse( call_user_func( $cleanup )['success'] );
			$this->assertSame( $before, $this->stored_data() );
		} finally {
			remove_filter( 'pre_update_option_' . $option, $fail, 10 );
		}
		$this->assertTrue( bbp_admin_repair_tool_run_automatically( $tool_id, $callback ) );
		$this->assertSame( 0, call_user_func( $cleanup )[0] );
		$this->assertSame( array( (string) $user_id ), get_post_meta( $post_id, $new_key, false ) );
	}

	/**
	 * @dataProvider upgrade_tools
	 */
	public function test_failed_progress_saves_do_not_skip_relationships( $tool_id, $old_key, $new_key, $callback ) {
		$user_id = $this->factory->user->create();
		$posts   = $this->factory->post->create_many( 5 );
		$option  = bbp_admin_repair_tool_batch_option_name( $tool_id );
		add_user_meta( $user_id, bbp_db()->prefix . $old_key, implode( ',', $posts ) );
		call_user_func( $callback );
		$before = get_option( $option );
		$fail   = static function ( $value, $old_value ) {
			return $old_value;
		};
		add_filter( 'pre_update_option_' . $option, $fail, 10, 2 );
		try {
			$result = call_user_func( $callback );
			$this->assertSame( 5, $result[0] );
			$this->assertFalse( $result['success'] );
			$this->assertSame( $before, get_option( $option ) );
		} finally {
			remove_filter( 'pre_update_option_' . $option, $fail, 10 );
		}
		$this->finish( $callback );
		foreach ( $posts as $post_id ) {
			$this->assertSame( array( (string) $user_id ), get_post_meta( $post_id, $new_key, false ) );
		}
	}

	private function closed_topics( $count ) {
		$posts = $this->factory->topic->create_many( $count, array( 'post_status' => bbp_get_closed_status_id() ) );
		foreach ( $posts as $post_id ) {
			delete_post_meta( $post_id, '_bbp_status' );
		}
		return $posts;
	}

	private function checkpoint_corruptions( $user_meta = false ) {
		$cases = array(
			'not an array'       => array( 'state', 'broken' ),
			'missing cursor'     => array( 'cursor', null ),
			'missing bound'      => array( 'upper_id', null ),
			'string cursor'      => array( 'cursor', '0' ),
			'string bound'       => array( 'upper_id', '9999999' ),
			'negative cursor'    => array( 'cursor', -1 ),
			'negative bound'     => array( 'upper_id', -1 ),
			'cursor past bound'  => array( 'cursor', 'past-bound' ),
		);
		if ( $user_meta ) {
			$cases += array(
				'missing offset'       => array( 'offset', null ),
				'missing fingerprint'  => array( 'row_hash', null ),
				'string offset'        => array( 'offset', '2' ),
				'negative offset'      => array( 'offset', -1 ),
				'impossible offset'    => array( 'offset', 'past-bound' ),
				'offset at list end'   => array( 'offset', 5 ),
				'invalid fingerprint'  => array( 'row_hash', 'invalid' ),
				'empty fingerprint'    => array( 'row_hash', '' ),
				'offset without row'   => array( 'cursor', 0 ),
				'fingerprint at start' => array( 'offset', 0 ),
			);
		}
		return $cases;
	}

	public function malformed_upgrade_checkpoints() {
		$cases = array();
		foreach ( $this->upgrade_tools() as $name => $tool ) {
			foreach ( $this->checkpoint_corruptions( true ) as $case => $change ) {
				$cases[ $name . ': ' . $case ] = array_merge( $tool, $change );
			}
		}
		return $cases;
	}

	public function malformed_checkpoints() {
		return $this->checkpoint_corruptions();
	}

	private function corrupt_checkpoint( $option, $field, $value, $list_length = 0 ) {
		$state = get_option( $option );
		if ( 'state' === $field ) {
			$state = $value;
		} elseif ( 'past-bound' === $value ) {
			$state[ $field ] = ( 'offset' === $field ? $list_length : $state['upper_id'] ) + 1;
		} elseif ( null === $value ) {
			unset( $state[ $field ] );
		} else {
			$state[ $field ] = $value;
		}
		update_option( $option, $state, false );
	}

	/**
	 * @dataProvider malformed_upgrade_checkpoints
	 */
	public function test_malformed_upgrade_checkpoints_restart_safely_and_preview_without_writes( $tool_id, $old_key, $new_key, $callback, $field, $value ) {
		$user_id = $this->factory->user->create();
		$posts   = $this->factory->post->create_many( 5 );
		$option  = bbp_admin_repair_tool_batch_option_name( $tool_id );
		add_user_meta( $user_id, bbp_db()->prefix . $old_key, implode( ',', $posts ) );
		call_user_func( $callback );
		$this->corrupt_checkpoint( $option, $field, $value, count( $posts ) );
		$before  = $this->stored_data();
		$preview = call_user_func( $callback . '_dry_run' );
		$this->assertSame( 0, $preview[0] );
		$this->assertStringNotContainsString( 'Saved progress is invalid. Previewing from the beginning.', $preview[1] );
		$this->assertSame( $before, $this->stored_data() );
		$result = call_user_func( $callback );
		$this->assertSame( 0, $result[0] );
		$this->assertStringContainsString( 'Saved progress was invalid. Restarting from the beginning.', $result[1] );
		$this->assertFalse( $result['complete'] );
		$this->assertSame( array(), get_post_meta( $posts[2], $new_key, false ) );
		$this->finish( $callback );
		$this->assertFalse( get_option( $option, false ) );
		foreach ( $posts as $post_id ) {
			$this->assertSame( array( (string) $user_id ), get_post_meta( $post_id, $new_key, false ) );
		}
	}

	/**
	 * @dataProvider malformed_checkpoints
	 */
	public function test_malformed_closed_topic_checkpoints_restart_safely_and_preview_without_writes( $field, $value ) {
		$posts  = $this->closed_topics( 5 );
		$option = bbp_admin_repair_tool_batch_option_name( 'bbp-sync-closed-topics' );
		bbp_admin_repair_closed_topics();
		$this->corrupt_checkpoint( $option, $field, $value );
		$before  = $this->stored_data();
		$preview = bbp_admin_repair_closed_topics_dry_run();
		$this->assertSame( 0, $preview[0] );
		$this->assertStringNotContainsString( 'Saved progress is invalid. Previewing from the beginning.', $preview[1] );
		$this->assertSame( $before, $this->stored_data() );
		$result = bbp_admin_repair_closed_topics();
		$this->assertSame( 0, $result[0] );
		$this->assertStringContainsString( 'Saved progress was invalid. Restarting from the beginning.', $result[1] );
		$this->assertFalse( $result['complete'] );
		$this->assertSame( '', get_post_meta( $posts[2], '_bbp_status', true ) );
		$this->finish( 'bbp_admin_repair_closed_topics' );
		$this->assertFalse( get_option( $option, false ) );
		foreach ( $posts as $post_id ) {
			$this->assertSame( 'publish', get_post_meta( $post_id, '_bbp_status', true ) );
		}
	}

	/**
	 * @dataProvider upgrade_tools
	 */
	public function test_a_changed_shorter_list_is_normal_and_does_not_restart_the_whole_upgrade( $tool_id, $old_key, $new_key, $callback ) {
		$user_id = $this->factory->user->create();
		$posts   = $this->factory->post->create_many( 5 );
		$key     = bbp_db()->prefix . $old_key;
		add_user_meta( $user_id, $key, implode( ',', $posts ) );
		call_user_func( $callback );
		update_user_meta( $user_id, $key, $posts[4] );
		$preview = call_user_func( $callback . '_dry_run' );
		$this->assertStringNotContainsString( 'Saved progress is invalid', $preview[1] );
		$result = call_user_func( $callback );
		$this->assertTrue( $result['complete'] );
		$this->assertStringNotContainsString( 'Saved progress was invalid', $result[1] );
		$this->assertSame( array( (string) $user_id ), get_post_meta( $posts[4], $new_key, false ) );
	}

	/**
	 * @dataProvider upgrade_tools
	 */
	public function test_a_deleted_source_row_is_normal_and_does_not_skip_the_next_list( $tool_id, $old_key, $new_key, $callback ) {
		$user_id = $this->factory->user->create();
		$posts   = $this->factory->post->create_many( 5 );
		$key     = bbp_db()->prefix . $old_key;
		$row_id  = add_user_meta( $user_id, $key, implode( ',', array_slice( $posts, 0, 3 ) ) );
		add_user_meta( $user_id, $key, implode( ',', array_slice( $posts, 3 ) ) );
		call_user_func( $callback );
		delete_metadata_by_mid( 'user', $row_id );
		$result = call_user_func( $callback );
		$this->assertTrue( $result['complete'] );
		$this->assertStringNotContainsString( 'Saved progress was invalid', $result[1] );
		$this->assertSame( array(), get_post_meta( $posts[2], $new_key, false ) );
		$this->assertSame( array( (string) $user_id ), get_post_meta( $posts[3], $new_key, false ) );
		$this->assertSame( array( (string) $user_id ), get_post_meta( $posts[4], $new_key, false ) );
	}

	public function test_closed_topics_are_bounded_preserve_statuses_and_preview_the_next_batch() {
		$posts  = $this->closed_topics( 5 );
		$option = bbp_admin_repair_tool_batch_option_name( 'bbp-sync-closed-topics' );
		update_post_meta( $posts[0], '_bbp_status', 'private' );
		$before = $this->stored_data();
		$this->assertTrue( bbp_admin_repair_closed_topics_dry_run()['complete'] );
		$this->assertSame( $before, $this->stored_data() );
		$this->assertFalse( bbp_admin_repair_closed_topics()['complete'] );
		$this->assertSame( 'private', get_post_meta( $posts[0], '_bbp_status', true ) );
		$this->assertSame( 'publish', get_post_meta( $posts[1], '_bbp_status', true ) );
		$this->assertSame( '', get_post_meta( $posts[2], '_bbp_status', true ) );

		$late   = $this->closed_topics( 1 );
		$before = $this->stored_data();
		$this->assertStringContainsString( '4 closed topics would be repaired', bbp_admin_repair_closed_topics_dry_run()[1] );
		$this->assertSame( $before, $this->stored_data() );
		$this->finish( 'bbp_admin_repair_closed_topics' );
		$this->assertFalse( get_option( $option, false ) );
		$this->assertSame( '', get_post_meta( $late[0], '_bbp_status', true ) );
		$this->finish( 'bbp_admin_repair_closed_topics' );
		$this->assertSame( 'publish', get_post_meta( $late[0], '_bbp_status', true ) );
		$this->assertSame( 'private', get_post_meta( $posts[0], '_bbp_status', true ) );
	}

	public function test_closed_topic_write_failures_keep_the_checkpoint_for_a_safe_retry() {
		$posts  = $this->closed_topics( 5 );
		$option = bbp_admin_repair_tool_batch_option_name( 'bbp-sync-closed-topics' );
		bbp_admin_repair_closed_topics();
		$before = get_option( $option );
		$fail   = static function ( $check, $post_id, $meta_key ) use ( $posts ) {
			return ( ( $posts[3] === $post_id ) && ( '_bbp_status' === $meta_key ) ) ? false : $check;
		};
		add_filter( 'update_post_metadata', $fail, 10, 3 );
		try {
			$this->assertFalse( bbp_admin_repair_closed_topics()['success'] );
			$this->assertSame( $before, get_option( $option ) );
			$this->assertSame( 'publish', get_post_meta( $posts[2], '_bbp_status', true ) );
		} finally {
			remove_filter( 'update_post_metadata', $fail, 10 );
		}
		$this->finish( 'bbp_admin_repair_closed_topics' );
		foreach ( $posts as $post_id ) {
			$this->assertSame( array( 'publish' ), get_post_meta( $post_id, '_bbp_status', false ) );
		}
	}

	public function test_closed_topic_read_failures_do_not_advance_progress() {
		$this->closed_topics( 5 );
		bbp_admin_repair_closed_topics();
		$db       = bbp_db();
		$option   = bbp_admin_repair_tool_batch_option_name( 'bbp-sync-closed-topics' );
		$before   = get_option( $option );
		$postmeta = $db->postmeta;
		$suppress = $db->suppress_errors( true );
		try {
			$db->postmeta = 'bbp_missing_closed_topic_postmeta';
			$this->assertFalse( bbp_admin_repair_closed_topics_dry_run()['success'] );
			$this->assertFalse( bbp_admin_repair_closed_topics()['success'] );
			$this->assertSame( $before, get_option( $option ) );
		} finally {
			$db->postmeta = $postmeta;
			$db->suppress_errors( $suppress );
		}
		$this->finish( 'bbp_admin_repair_closed_topics' );
	}

	public function test_deleting_a_processed_topic_does_not_make_its_checkpoint_malformed() {
		$posts = $this->closed_topics( 5 );
		bbp_admin_repair_closed_topics();
		wp_delete_post( $posts[1], true );
		$result = bbp_admin_repair_closed_topics();
		$this->assertStringNotContainsString( 'Saved progress was invalid', $result[1] );
		$this->assertSame( 'publish', get_post_meta( $posts[3], '_bbp_status', true ) );
		$this->assertSame( '', get_post_meta( $posts[4], '_bbp_status', true ) );
		$this->finish( 'bbp_admin_repair_closed_topics' );
	}

	public function test_translated_restart_notices_preserve_literal_percent_signs_and_placeholders() {
		$user_id = $this->factory->user->create();
		$posts   = $this->factory->post->create_many( 5 );
		$option  = bbp_admin_repair_tool_batch_option_name( 'bbp-user-favorites-move' );
		add_user_meta( $user_id, bbp_db()->prefix . '_bbp_favorites', implode( ',', $posts ) );
		bbp_admin_upgrade_user_favorites();
		update_option( $option, 'broken', false );
		$translations = array(
			'Saved progress is invalid. Previewing from the beginning.' => 'Saved progress is invalid (100%). Previewing from the beginning: %s.',
			'Saved progress was invalid. Restarting from the beginning.' => 'Saved progress was invalid (100%). Restarting from the beginning: %s.',
		);
		$translate = static function ( $translated, $text, $domain ) use ( $translations ) {
			return ( 'bbpress' === $domain ) && isset( $translations[ $text ] ) ? $translations[ $text ] : $translated;
		};
		add_filter( 'gettext', $translate, 10, 3 );
		try {
			$before  = $this->stored_data();
			$preview = bbp_admin_upgrade_user_favorites_dry_run();
			$this->assertSame( 0, $preview[0] );
			$this->assertStringNotContainsString( $translations['Saved progress is invalid. Previewing from the beginning.'], $preview[1] );
			$this->assertSame( $before, $this->stored_data() );
			$result = bbp_admin_upgrade_user_favorites();
			$this->assertSame( 0, $result[0] );
			$this->assertStringContainsString( $translations['Saved progress was invalid. Restarting from the beginning.'], $result[1] );
			$this->assertStringContainsString( '0 favorite relationships added', $result[1] );
		} finally {
			remove_filter( 'gettext', $translate, 10 );
		}
		$this->finish( 'bbp_admin_upgrade_user_favorites' );
		foreach ( $posts as $post_id ) {
			$this->assertSame( array( (string) $user_id ), get_post_meta( $post_id, '_bbp_favorite', false ) );
		}
	}

	public function test_small_site_updater_completes_all_four_real_migrations_automatically() {
		$this->check_small_site_updater( false );
	}

	public function test_small_site_budget_exhaustion_keeps_real_migrations_pending_and_resumable() {
		$this->check_small_site_updater( true );
	}

	public function test_small_site_updater_recovers_malformed_checkpoints_automatically() {
		$this->check_small_site_updater( false, true );
	}

	public function test_small_site_budget_exhaustion_recovers_malformed_checkpoints_and_stays_resumable() {
		$this->check_small_site_updater( true, true );
	}

	private function check_small_site_updater( $exhaust, $malformed = false ) {
		$user_id = $this->factory->user->create();
		$tools   = $this->upgrade_tools();
		$posts   = array();
		foreach ( $tools as $name => $tool ) {
			$posts[ $name ] = ( 'forum subscriptions' === $name )
				? $this->factory->forum->create_many( 5 )
				: $this->factory->topic->create_many( 5 );
			add_user_meta( $user_id, bbp_db()->prefix . $tool[1], implode( ',', $posts[ $name ] ) );
			if ( $malformed ) {
				update_option( bbp_admin_repair_tool_batch_option_name( $tool[0] ), 'broken', false );
			}
		}
		$tools['engagements'] = array( 'bbp-user-topic-engagements-move', '', '_bbp_engagement', 'bbp_admin_upgrade_user_engagements' );
		$posts['engagements'] = $this->factory->topic->create_many( 5, array( 'post_author' => $user_id ) );
		foreach ( $posts['engagements'] as $post_id ) {
			delete_post_meta( $post_id, '_bbp_engagement' );
		}
		if ( $malformed ) {
			update_option( bbp_admin_repair_tool_batch_option_name( $tools['engagements'][0] ), 'broken', false );
		}
		update_option( '_bbp_db_version', 260 );
		delete_option( '_bbp_db_pending_upgrades' );
		add_filter( 'bbp_is_large_install', '__return_false' );
		if ( $exhaust ) {
			add_filter( 'bbp_admin_repair_tool_automatic_batch_limit', '__return_true' );
		}
		try {
			bbp_version_updater();
		} finally {
			remove_filter( 'bbp_is_large_install', '__return_false' );
			remove_filter( 'bbp_admin_repair_tool_automatic_batch_limit', '__return_true' );
		}
		$this->assertSame( (int) bbp_get_db_version(), (int) bbp_get_db_version_raw() );
		foreach ( $tools as $name => $tool ) {
			$option = bbp_admin_repair_tool_batch_option_name( $tool[0] );
			if ( $exhaust ) {
				$this->assertContains( $tool[0], bbp_get_pending_upgrades() );
				$this->assertIsArray( get_option( $option ) );
				$this->assertTrue( bbp_admin_repair_tool_run_automatically( $tool[0], $tool[3] ) );
			}
			$this->assertNotContains( $tool[0], bbp_get_pending_upgrades() );
			$this->assertFalse( get_option( $option, false ) );
			foreach ( $posts[ $name ] as $post_id ) {
				$this->assertSame( array( (string) $user_id ), get_post_meta( $post_id, $tool[2], false ) );
			}
		}
	}

	private function engagement_rows() {
		$db = bbp_db();
		return $db->get_results( "SELECT post_id, meta_value FROM {$db->postmeta} WHERE meta_key = '_bbp_engagement' ORDER BY post_id, meta_value", ARRAY_A );
	}

	private function expected_engagement_rows() {
		$db = bbp_db();
		return $db->get_results( $db->prepare(
			"SELECT topics.ID AS post_id, posts.post_author AS meta_value
			FROM {$db->posts} AS posts
			INNER JOIN {$db->postmeta} AS postmeta ON posts.ID = postmeta.post_id AND postmeta.meta_key = '_bbp_topic_id'
			INNER JOIN {$db->posts} AS topics ON topics.ID = postmeta.meta_value AND topics.post_type = %s
			WHERE posts.post_type IN (%s, %s) AND posts.post_status IN (%s, %s)
			GROUP BY topics.ID, posts.post_author ORDER BY post_id, meta_value",
			bbp_get_topic_post_type(), bbp_get_topic_post_type(), bbp_get_reply_post_type(), bbp_get_public_status_id(), bbp_get_closed_status_id()
		), ARRAY_A );
	}

	public function test_engagement_batches_match_the_original_rebuild_and_preserve_valid_rows() {
		$db       = bbp_db();
		$users    = $this->factory->user->create_many( 3 );
		$topic_id = $this->factory->topic->create( array( 'post_author' => $users[0] ) );
		$other    = $this->factory->topic->create( array( 'post_author' => $users[2], 'post_status' => bbp_get_closed_status_id() ) );
		$reply_id = $this->factory->reply->create( array( 'post_parent' => $topic_id, 'post_author' => $users[1] ) );
		$this->factory->reply->create_many( 5, array( 'post_parent' => $topic_id, 'post_author' => $users[1] ) );
		$this->factory->reply->create( array( 'post_parent' => $topic_id, 'post_author' => 0 ) );
		$this->factory->reply->create( array( 'post_parent' => $topic_id, 'post_author' => $users[2], 'post_status' => bbp_get_spam_status_id() ) );
		$this->factory->reply->create( array( 'post_parent' => $topic_id, 'post_author' => $users[2], 'post_status' => bbp_get_trash_status_id() ) );
		add_post_meta( $reply_id, '_bbp_topic_id', $topic_id );
		add_post_meta( 999999, '_bbp_topic_id', $topic_id );
		$post_id = $this->factory->post->create();
		add_post_meta( $post_id, '_bbp_topic_id', $topic_id );

		// Keep an existing valid row, while rebuilding missing rows and cleaning bad ones
		delete_post_meta( $topic_id, '_bbp_engagement' );
		delete_post_meta( $other, '_bbp_engagement' );
		$valid_id = add_post_meta( $topic_id, '_bbp_engagement', $users[0] );
		add_post_meta( $topic_id, '_bbp_engagement', $users[0] );
		add_post_meta( $topic_id, '_bbp_engagement', '0' . $users[0] );
		add_post_meta( $topic_id, '_bbp_engagement', 999999 );
		add_post_meta( $post_id, '_bbp_engagement', $users[0] );
		add_post_meta( 999999, '_bbp_engagement', $users[0] );
		$expected = $this->expected_engagement_rows();
		$option   = bbp_admin_repair_tool_batch_option_name( 'bbp-user-topic-engagements-move' );
		$upper    = (int) $db->get_var( "SELECT MAX(meta_id) FROM {$db->postmeta} WHERE meta_key IN ('_bbp_topic_id', '_bbp_engagement')" );
		$ids      = array_map( 'intval', $db->get_col( "SELECT meta_id FROM {$db->postmeta} WHERE meta_key IN ('_bbp_topic_id', '_bbp_engagement') ORDER BY meta_id" ) );
		$cursor   = 0;

		for ( $i = 0; $i < 30; ++$i ) {
			$before  = $this->stored_data();
			$preview = bbp_admin_upgrade_user_engagements_dry_run();
			$this->assertSame( 0, $preview[0] );
			$predicted = bbp_admin_upgrade_user_engagements_batch( true );
			$this->assertSame( $before, $this->stored_data() );
			$batch = bbp_admin_upgrade_user_engagements_batch();
			$this->assertSame( 0, $batch['code'] );
			$this->assertGreaterThanOrEqual( $batch['added'], $predicted['added'] );
			$this->assertGreaterThanOrEqual( $batch['removed'], $predicted['removed'] );
			$this->assertTrue( $predicted['complete'] );
			$this->assertLessThanOrEqual( 2, $batch['added'] + $batch['removed'] );
			$this->assertNotFalse( get_metadata_by_mid( 'post', $valid_id ) );
			if ( $batch['complete'] ) {
				break;
			}
			$state = get_option( $option );
			$this->assertSame( $upper, $state['upper_id'] );
			$scanned = array_filter( $ids, static function ( $id ) use ( $state, $cursor ) {
				return ( $id > $cursor ) && ( $id <= $state['cursor'] );
			} );
			$this->assertCount( 2, $scanned );
			$cursor = $state['cursor'];
		}
		$this->assertTrue( $batch['complete'] );
		$this->assertFalse( get_option( $option, false ) );
		$this->assertSame( $expected, $this->engagement_rows() );
		$this->assertSame( array_map( 'strval', array( $users[0], $users[1], 0 ) ), get_post_meta( $topic_id, '_bbp_engagement', false ) );

		$this->finish( 'bbp_admin_upgrade_user_engagements' );
		$this->assertSame( $expected, $this->engagement_rows() );
		$this->assertNotFalse( get_metadata_by_mid( 'post', $valid_id ) );
	}

	public function test_engagement_key_variants_preserve_source_rows_and_create_canonical_engagements() {
		$users    = $this->factory->user->create_many( 2 );
		$topic_id = $this->factory->topic->create( array( 'post_author' => $users[0] ) );
		$reply_id = $this->factory->reply->create( array( 'post_parent' => $topic_id, 'post_author' => $users[1] ) );
		delete_post_meta( $reply_id, '_bbp_topic_id' );
		delete_post_meta( $topic_id, '_bbp_engagement' );
		$sources = array(
			add_post_meta( $reply_id, '_BBP_TOPIC_ID', $topic_id ),
			add_post_meta( $reply_id, '_bbp_topic_id ', $topic_id ),
		);
		add_post_meta( $topic_id, '_BBP_ENGAGEMENT', $users[0] );
		add_post_meta( $topic_id, '_bbp_engagement ', $users[1] );
		$expected = $this->expected_engagement_rows();
		$before   = $this->stored_data();
		bbp_admin_upgrade_user_engagements_dry_run();
		$this->assertSame( $before, $this->stored_data() );
		$this->finish( 'bbp_admin_upgrade_user_engagements' );
		foreach ( $sources as $meta_id ) {
			$this->assertNotFalse( get_metadata_by_mid( 'post', $meta_id ) );
		}
		$this->assertSame( $expected, $this->engagement_rows() );
		$this->assertEqualSets( array_map( 'strval', $users ), get_post_meta( $topic_id, '_bbp_engagement', false ) );
		$this->finish( 'bbp_admin_upgrade_user_engagements' );
		$this->assertSame( $expected, $this->engagement_rows() );
	}

	public function test_new_engagement_source_rows_wait_for_the_next_run() {
		$user_id = $this->factory->user->create();
		$topics  = $this->factory->topic->create_many( 3, array( 'post_author' => $user_id ) );
		foreach ( $topics as $topic_id ) {
			delete_post_meta( $topic_id, '_bbp_engagement' );
		}
		$this->assertFalse( bbp_admin_upgrade_user_engagements()['complete'] );
		$new_topic = $this->factory->topic->create( array( 'post_author' => $user_id ) );
		delete_post_meta( $new_topic, '_bbp_engagement' );
		$this->finish( 'bbp_admin_upgrade_user_engagements' );
		$this->assertSame( array(), get_post_meta( $new_topic, '_bbp_engagement', false ) );
		$this->finish( 'bbp_admin_upgrade_user_engagements' );
		$this->assertSame( array( (string) $user_id ), get_post_meta( $new_topic, '_bbp_engagement', false ) );
	}

	/**
	 * @dataProvider malformed_checkpoints
	 */
	public function test_malformed_engagement_checkpoints_restart_without_preview_writes( $field, $value ) {
		$topics = $this->factory->topic->create_many( 4 );
		foreach ( $topics as $topic_id ) {
			delete_post_meta( $topic_id, '_bbp_engagement' );
		}
		$option = bbp_admin_repair_tool_batch_option_name( 'bbp-user-topic-engagements-move' );
		$this->assertFalse( bbp_admin_upgrade_user_engagements()['complete'] );
		$this->corrupt_checkpoint( $option, $field, $value );
		$new_topic = $this->factory->topic->create();
		delete_post_meta( $new_topic, '_bbp_engagement' );
		$before  = $this->stored_data();
		$preview = bbp_admin_upgrade_user_engagements_dry_run();
		$this->assertSame( 0, $preview[0] );
		$this->assertStringNotContainsString( 'Saved progress is invalid.', $preview[1] );
		$this->assertSame( $before, $this->stored_data() );
		$result = bbp_admin_upgrade_user_engagements();
		$this->assertSame( 0, $result[0] );
		$this->assertStringContainsString( 'Saved progress was invalid.', $result[1] );
		$this->finish( 'bbp_admin_upgrade_user_engagements' );
		$this->assertSame( $this->expected_engagement_rows(), $this->engagement_rows() );
	}

	public function test_engagement_write_failures_keep_progress_and_retry_without_duplicates() {
		$user_id = $this->factory->user->create();
		$topics  = $this->factory->topic->create_many( 3, array( 'post_author' => $user_id ) );
		foreach ( $topics as $topic_id ) {
			delete_post_meta( $topic_id, '_bbp_engagement' );
		}
		$fail = static function ( $check, $post_id, $key ) use ( $topics ) {
			return ( $topics[1] === $post_id ) && ( '_bbp_engagement' === $key ) ? false : $check;
		};
		add_filter( 'add_post_metadata', $fail, 10, 3 );
		try {
			$result = bbp_admin_upgrade_user_engagements();
			$this->assertSame( 5, $result[0] );
			$this->assertFalse( $result['success'] );
			$this->assertFalse( $result['complete'] );
			$this->assertSame( 0, get_option( bbp_admin_repair_tool_batch_option_name( 'bbp-user-topic-engagements-move' ) )['cursor'] );
			$this->assertSame( array( (string) $user_id ), get_post_meta( $topics[0], '_bbp_engagement', false ) );
		} finally {
			remove_filter( 'add_post_metadata', $fail, 10 );
		}
		$this->finish( 'bbp_admin_upgrade_user_engagements' );
		$this->assertSame( $this->expected_engagement_rows(), $this->engagement_rows() );
	}

	public function test_engagement_initial_checkpoint_failures_write_nothing_and_remain_pending() {
		$this->factory->topic->create_many( 3 );
		$tool_id = 'bbp-user-topic-engagements-move';
		$option  = bbp_admin_repair_tool_batch_option_name( $tool_id );
		$fail    = static function ( $value, $old ) {
			return $old;
		};
		$before = $this->engagement_rows();
		add_filter( 'pre_update_option_' . $option, $fail, 10, 2 );
		try {
			$result = bbp_admin_upgrade_user_engagements();
			$this->assertSame( 6, $result[0] );
			$this->assertFalse( $result['success'] );
			$this->assertContains( $tool_id, bbp_get_pending_upgrades() );
			$this->assertSame( $before, $this->engagement_rows() );
		} finally {
			remove_filter( 'pre_update_option_' . $option, $fail, 10 );
		}
		$this->assertTrue( bbp_admin_repair_tool_run_automatically( $tool_id, 'bbp_admin_upgrade_user_engagements' ) );
		$this->assertNotContains( $tool_id, bbp_get_pending_upgrades() );
	}

	public function test_engagement_progress_save_failures_can_be_retried_safely() {
		$topics = $this->factory->topic->create_many( 5 );
		foreach ( $topics as $topic_id ) {
			delete_post_meta( $topic_id, '_bbp_engagement' );
		}
		$option = bbp_admin_repair_tool_batch_option_name( 'bbp-user-topic-engagements-move' );
		$this->assertFalse( bbp_admin_upgrade_user_engagements()['complete'] );
		$state = get_option( $option );
		$fail  = static function ( $value, $old ) {
			return $old;
		};
		add_filter( 'pre_update_option_' . $option, $fail, 10, 2 );
		try {
			$result = bbp_admin_upgrade_user_engagements();
			$this->assertSame( 6, $result[0] );
			$this->assertFalse( $result['success'] );
			$this->assertFalse( $result['complete'] );
			$this->assertSame( $state, get_option( $option ) );
		} finally {
			remove_filter( 'pre_update_option_' . $option, $fail, 10 );
		}
		$this->finish( 'bbp_admin_upgrade_user_engagements' );
		$this->assertSame( $this->expected_engagement_rows(), $this->engagement_rows() );
	}

	public function test_engagement_final_checkpoint_delete_failures_remain_pending_and_retry_safely() {
		$db      = bbp_db();
		$tool_id = 'bbp-user-topic-engagements-move';
		$option  = bbp_admin_repair_tool_batch_option_name( $tool_id );
		$topics  = $this->factory->topic->create_many( 3 );
		foreach ( $topics as $topic_id ) {
			delete_post_meta( $topic_id, '_bbp_engagement' );
		}
		$this->assertFalse( bbp_admin_upgrade_user_engagements()['complete'] );
		$state = get_option( $option );
		$fail  = static function ( $query ) use ( $db, $option ) {
			return ( 0 === strpos( $query, 'DELETE FROM' ) ) && ( false !== strpos( $query, $db->options ) ) && ( false !== strpos( $query, $option ) )
				? 'DELETE FROM bbp_missing_checkpoint_options'
				: $query;
		};
		$suppress = $db->suppress_errors( true );
		add_filter( 'query', $fail );
		try {
			$result = bbp_admin_upgrade_user_engagements();
			$this->assertSame( 6, $result[0] );
			$this->assertFalse( $result['success'] );
			$this->assertFalse( $result['complete'] );
			$this->assertSame( $state, get_option( $option ) );
			$this->assertSame( $this->expected_engagement_rows(), $this->engagement_rows() );
			$this->assertFalse( bbp_admin_repair_tool_run_automatically( $tool_id, 'bbp_admin_upgrade_user_engagements' ) );
			$this->assertContains( $tool_id, bbp_get_pending_upgrades() );
			$this->assertSame( $state, get_option( $option ) );
		} finally {
			remove_filter( 'query', $fail );
			$db->suppress_errors( $suppress );
		}
		$this->assertTrue( bbp_admin_repair_tool_run_automatically( $tool_id, 'bbp_admin_upgrade_user_engagements' ) );
		$this->assertNotContains( $tool_id, bbp_get_pending_upgrades() );
		$this->assertFalse( get_option( $option, false ) );
		$this->assertSame( $this->expected_engagement_rows(), $this->engagement_rows() );
	}

	public function test_engagement_partial_cleanup_failures_can_be_retried_without_recreating_removed_rows() {
		$post_id = $this->factory->post->create();
		$meta    = array();
		for ( $i = 0; $i < 3; ++$i ) {
			$meta[] = add_post_meta( $post_id, '_bbp_engagement', 999999 );
		}
		$fail = static function ( $delete, $meta_id ) use ( $meta ) {
			return ( $meta[1] === (int) $meta_id ) ? false : $delete;
		};
		add_filter( 'delete_post_metadata_by_mid', $fail, 10, 2 );
		try {
			$result = bbp_admin_upgrade_user_engagements();
			$this->assertSame( 4, $result[0] );
			$this->assertFalse( $result['success'] );
			$this->assertFalse( $result['complete'] );
			$this->assertSame( 0, get_option( bbp_admin_repair_tool_batch_option_name( 'bbp-user-topic-engagements-move' ) )['cursor'] );
			$this->assertFalse( get_metadata_by_mid( 'post', $meta[0] ) );
			$this->assertNotFalse( get_metadata_by_mid( 'post', $meta[1] ) );
		} finally {
			remove_filter( 'delete_post_metadata_by_mid', $fail, 10 );
		}
		$this->finish( 'bbp_admin_upgrade_user_engagements' );
		$this->assertSame( array(), get_post_meta( $post_id, '_bbp_engagement', false ) );
		$this->assertSame( $this->expected_engagement_rows(), $this->engagement_rows() );
	}

	public function test_engagement_read_failures_preserve_saved_progress_and_preview_data() {
		$db     = bbp_db();
		$topics = $this->factory->topic->create_many( 3 );
		foreach ( $topics as $topic_id ) {
			delete_post_meta( $topic_id, '_bbp_engagement' );
		}
		$this->assertFalse( bbp_admin_upgrade_user_engagements()['complete'] );
		$before = $this->stored_data();
		$fail   = static function ( $query ) {
			return ( 0 === strpos( $query, 'SELECT topics.ID AS post_id, posts.post_author AS user_id' ) )
				? "SELECT ID FROM bbp_missing_engagement_posts"
				: $query;
		};
		$suppress = $db->suppress_errors( true );
		add_filter( 'query', $fail );
		try {
			foreach ( array( 'bbp_admin_upgrade_user_engagements', 'bbp_admin_upgrade_user_engagements_dry_run' ) as $callback ) {
				$result = call_user_func( $callback );
				$this->assertSame( 3, $result[0] );
				$this->assertFalse( $result['success'] );
				$this->assertFalse( $result['complete'] );
				$this->assertSame( $before, $this->stored_data() );
			}
		} finally {
			remove_filter( 'query', $fail );
			$db->suppress_errors( $suppress );
		}
		$this->finish( 'bbp_admin_upgrade_user_engagements' );
		$this->assertSame( $this->expected_engagement_rows(), $this->engagement_rows() );
	}

	public function test_small_site_engagement_write_failures_remain_pending_and_resumable() {
		$tool_id  = 'bbp-user-topic-engagements-move';
		$user_id  = $this->factory->user->create();
		$topic_id = $this->factory->topic->create( array( 'post_author' => $user_id ) );
		delete_post_meta( $topic_id, '_bbp_engagement' );
		$fail = static function ( $check, $post_id, $key ) {
			return ( '_bbp_engagement' === $key ) ? false : $check;
		};
		update_option( '_bbp_db_version', 261 );
		delete_option( '_bbp_db_pending_upgrades' );
		add_filter( 'bbp_is_large_install', '__return_false' );
		add_filter( 'add_post_metadata', $fail, 10, 3 );
		try {
			bbp_version_updater();
		} finally {
			remove_filter( 'bbp_is_large_install', '__return_false' );
			remove_filter( 'add_post_metadata', $fail, 10 );
		}
		$this->assertContains( $tool_id, bbp_get_pending_upgrades() );
		$this->assertSame( 0, get_option( bbp_admin_repair_tool_batch_option_name( $tool_id ) )['cursor'] );
		$this->assertTrue( bbp_admin_repair_tool_run_automatically( $tool_id, 'bbp_admin_upgrade_user_engagements' ) );
		$this->assertNotContains( $tool_id, bbp_get_pending_upgrades() );
		$this->assertSame( array( (string) $user_id ), get_post_meta( $topic_id, '_bbp_engagement', false ) );
	}
}
