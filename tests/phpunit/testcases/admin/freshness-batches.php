<?php

/**
 * Resumable topic and forum freshness, including verified hierarchy retries.
 *
 * @group tools
 * @group tool-batches
 */
class BBP_Tests_Admin_Freshness_Batches extends BBP_UnitTestCase {

	private $size = 1;

	public function setUp(): void {
		parent::setUp();
		if ( ! function_exists( 'bbp_admin' ) ) {
			require_once BBP_PLUGIN_DIR . 'includes/admin/actions.php';
		}
		bbp_admin();
		add_filter( 'bbp_admin_repair_tool_batch_size', array( $this, 'batch_size' ) );
	}

	public function tearDown(): void {
		remove_filter( 'bbp_admin_repair_tool_batch_size', array( $this, 'batch_size' ) );
		parent::tearDown();
	}

	public function batch_size() {
		return $this->size;
	}

	public function sizes() {
		return array( array( 1 ), array( 1000 ) );
	}

	public function write_failures() {
		return array( array( false ), array( true ) );
	}

	public function child_lists() {
		return array( array( false ), array( true ) );
	}

	public function malformed_nodes() {
		return array( array( 'topics', 'values' ), array( 'topics', 'type' ), array( 'count', 'values' ), array( 'count', 'type' ) );
	}

	public function checkpoint_failures() {
		return array( array( 'initial' ), array( 'progress' ), array( 'final' ) );
	}

	private function option() {
		return bbp_admin_repair_tool_batch_option_name( 'bbp-sync-all-topics-forums' );
	}

	private function snapshot() {
		$db = bbp_db();
		return array(
			$db->get_results( "SELECT * FROM {$db->postmeta} ORDER BY meta_id", ARRAY_A ),
			$db->get_results( "SELECT * FROM {$db->options} ORDER BY option_id", ARRAY_A )
		);
	}

	private function fixture() {
		$parent = $this->factory->forum->create();
		$forum = $this->factory->forum->create( array( 'post_parent' => $parent ) );
		$topic = $this->factory->topic->create( array( 'post_parent' => $forum ) );
		$reply = $this->factory->reply->create( array( 'post_parent' => $topic ) );
		foreach ( array( $parent, $forum, $topic ) as $id ) {
			foreach ( array( '_bbp_last_topic_id', '_bbp_last_reply_id', '_bbp_last_active_id', '_bbp_last_active_time' ) as $key ) {
				update_post_meta( $id, $key, '_bbp_last_active_time' === $key ? '2000-01-01 00:00:00' : 999999 );
			}
		}
		return array( $parent, $forum, $topic, $reply );
	}

	private function finish() {
		for ( $i = 0; $i < 1000; ++$i ) {
			$result = bbp_admin_repair_freshness_batch();
			$this->assertSame( 0, $result['code'] );
			if ( $result['complete'] ) {
				$this->assertFalse( get_option( $this->option(), false ) );
				$db = bbp_db();
				$this->assertSame( array(), $db->get_col( $db->prepare( "SELECT option_name FROM {$db->options} WHERE option_name LIKE %s", $db->esc_like( $this->option() . '_tree_' ) . '%' ) ) );
				return;
			}
		}
		$this->fail( 'Freshness repair did not finish.' );
	}

	private function advance_to_topic( $topic_id, $key = null ) {
		for ( $i = 0; $i < 1000; ++$i ) {
			$result = bbp_admin_repair_freshness_batch();
			$this->assertSame( 0, $result['code'] );
			$state = get_option( $this->option() );
			if ( is_array( $state ) && 'topics' === $state['phase'] && $state['current'] === $topic_id
				&& ( null === $key || $key === $state['topic_node']['key'] ) ) {
				return $state;
			}
		}
		$this->fail( 'Freshness repair did not reach the requested topic state.' );
	}

	private function assert_freshness( $ids ) {
		list( $parent, $forum, $topic, $reply ) = $ids;
		$this->assertSame( array(), get_post_meta( $topic, '_bbp_last_topic_id', false ) );
		foreach ( array( $parent, $forum ) as $id ) {
			$this->assertSame( array( (string) $topic ), get_post_meta( $id, '_bbp_last_topic_id', false ) );
		}
		foreach ( array( $parent, $forum, $topic ) as $id ) {
			$this->assertSame( array( (string) $reply ), get_post_meta( $id, '_bbp_last_reply_id', false ) );
			$this->assertSame( array( (string) $reply ), get_post_meta( $id, '_bbp_last_active_id', false ) );
			$this->assertSame( array( get_post_field( 'post_date', $reply ) ), get_post_meta( $id, '_bbp_last_active_time', false ) );
		}
	}

	/** @dataProvider sizes */
	public function test_previews_match_bounded_writes_and_completed_reruns_preserve_rows( $size ) {
		$db = bbp_db();
		$ids = $this->fixture();
		$this->size = $size;
		delete_post_meta( $ids[2], '_bbp_last_reply_id' );
		$keep = add_post_meta( $ids[2], '_bbp_last_reply_id', $ids[3] );
		for ( $i = 0; $i < 9; ++$i ) {
			add_post_meta( $ids[2], '_BBP_LAST_REPLY_ID', 99 );
		}
		for ( $i = 0; $i < 1000; ++$i ) {
			$before = $this->snapshot();
			$preview = bbp_admin_repair_freshness_batch( true );
			$this->assertSame( $before, $this->snapshot() );
			$before_ids = $db->get_col( "SELECT meta_id FROM {$db->postmeta}" );
			$result = bbp_admin_repair_freshness_batch();
			$after_ids = $db->get_col( "SELECT meta_id FROM {$db->postmeta}" );
			$this->assertSame( 0, $result['code'] );
			$this->assertSame( 1, $preview['topics'] );
			$this->assertSame( 2, $preview['forums'] );
			$this->assertTrue( $preview['complete'] );
			$this->assertLessThanOrEqual( $size, count( array_diff( $before_ids, $after_ids ) ) + count( array_diff( $after_ids, $before_ids ) ) );
			$this->assertNotFalse( get_metadata_by_mid( 'post', $keep ) );
			if ( $result['complete'] ) {
				break;
			}
		}
		$this->assertTrue( $result['complete'] );
		$this->assert_freshness( $ids );
		$before = $this->snapshot();
		$this->finish();
		$this->assertSame( $before, $this->snapshot() );
	}

	public function test_full_preview_calculates_topics_and_forums_without_intermediate_writes() {
		$ids = $this->fixture();
		$this->size = 1000;
		$preview = bbp_admin_repair_freshness_batch( true );
		$this->assertSame( 1, $preview['topics'] );
		$this->assertSame( 2, $preview['forums'] );
		$this->assertTrue( $preview['complete'] );
		$run = bbp_admin_repair_freshness_batch();
		$this->assertSame( $preview['topics'], $run['topics'] );
		$this->assertSame( 'count', get_option( $this->option() )['phase'] );
		$this->assertSame( '999999', get_post_meta( $ids[0], '_bbp_last_topic_id', true ) );
		$preview = bbp_admin_repair_freshness_batch( true );
		$this->assertSame( 2, $preview['forums'] );
		$this->assertTrue( $preview['complete'] );
		$this->finish();
		$this->assert_freshness( $ids );
	}

	public function test_browser_preview_uses_bounded_requests_and_aggregate_totals() {
		$this->fixture();
		$this->size = 2;
		$before     = $this->snapshot();
		$requests   = 0;

		bbp_admin_repair_tool_execution_context( 'dry-run', 'freshness-preview' );
		try {
			for ( $i = 0; $i < 1000; ++$i ) {
				$result = bbp_admin_repair_freshness_batch( true );
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
		$this->assertSame( 1, $result['topics'] );
		$this->assertSame( 2, $result['forums'] );
		// Browser-only preview cleanup is not part of the normal Run estimate.
		$this->assertGreaterThanOrEqual( $result['batches'], $requests );
		$this->assertSame( $before, $this->snapshot() );
	}

	public function test_orphan_cleanup_is_bounded_and_preserves_other_metadata() {
		$db = bbp_db();
		$post = $this->factory->post->create();
		foreach ( array( 0, $post, $post + 1000 ) as $id ) {
			for ( $i = 0; $i < 7; ++$i ) {
				$db->insert( $db->postmeta, array( 'post_id' => $id, 'meta_key' => '_bbp_last_active_time', 'meta_value' => 'stale' ) );
			}
		}
		$keep = add_post_meta( $post, 'unrelated_freshness', 'preserved' );
		$this->finish();
		$this->assertSame( '0', $db->get_var( "SELECT COUNT(*) FROM {$db->postmeta} WHERE meta_key = '_bbp_last_active_time'" ) );
		$this->assertNotFalse( get_metadata_by_mid( 'post', $keep ) );
	}

	public function test_orphan_candidates_consume_work_even_when_they_are_valid() {
		$db    = bbp_db();
		$forum = $this->factory->forum->create();
		$rows  = array();
		for ( $i = 0; $i < 200; ++$i ) {
			$rows[] = $db->prepare( '(%d, %s, %s)', $forum, '_bbp_last_active_time', '2000-01-01 00:00:00' );
			$rows[] = $db->prepare( '(%d, %s, %s)', $forum, 'unrelated_freshness', 'preserved' );
		}
		$db->query( "INSERT INTO {$db->postmeta} (post_id, meta_key, meta_value) VALUES " . implode( ', ', $rows ) );
		$db->insert( $db->postmeta, array( 'post_id' => 0, 'meta_key' => '_BBP_LAST_ACTIVE_TIME', 'meta_value' => 'orphan' ) );
		$orphan     = (int) $db->insert_id;
		$this->size = 8;
		$before     = $this->snapshot();
		$preview    = bbp_admin_repair_freshness_batch( true );
		$this->assertSame( $before, $this->snapshot() );
		$run   = bbp_admin_repair_freshness_batch();
		$state = get_option( $this->option() );
		$this->assertSame( 0, $run['code'] );
		$this->assertSame( 1, $preview['stale'] );
		$this->assertSame( 0, $run['stale'] );
		$this->assertSame( 'orphans', $state['phase'] );
		$this->assertGreaterThan( 0, $state['orphan_cursor'] );
		$this->assertLessThanOrEqual( $this->size, (int) $db->get_var( $db->prepare( "SELECT COUNT(*) FROM {$db->postmeta} WHERE meta_id <= %d", $state['orphan_cursor'] ) ) );
		$this->assertNotFalse( get_metadata_by_mid( 'post', $orphan ) );
		$this->size = 1000;
		$this->finish();
		$this->assertFalse( get_metadata_by_mid( 'post', $orphan ) );
		$this->assertSame( '200', $db->get_var( "SELECT COUNT(*) FROM {$db->postmeta} WHERE meta_key = 'unrelated_freshness'" ) );
	}

	public function test_orphan_deletion_is_safe_to_replay_after_checkpoint_failure() {
		$db = bbp_db();
		$db->insert( $db->postmeta, array( 'post_id' => 0, 'meta_key' => '_bbp_last_active_time', 'meta_value' => 'orphan' ) );
		$orphan     = (int) $db->insert_id;
		$this->size = 1000;
		$fail       = static function ( $value, $old ) { return is_array( $old ) ? $old : $value; };
		add_filter( 'pre_update_option_' . $this->option(), $fail, 10, 2 );
		try {
			$result = bbp_admin_repair_freshness_batch();
			$this->assertSame( 7, $result['code'] );
			$this->assertFalse( $result['complete'] );
			$this->assertFalse( get_metadata_by_mid( 'post', $orphan ) );
			$this->assertSame( 0, get_option( $this->option() )['orphan_cursor'] );
		} finally {
			remove_filter( 'pre_update_option_' . $this->option(), $fail, 10 );
		}
		$this->assertTrue( bbp_admin_repair_tool_run_automatically( 'bbp-sync-all-topics-forums', 'bbp_admin_repair_freshness' ) );
		$this->assertFalse( get_option( $this->option(), false ) );
		$this->assertFalse( get_metadata_by_mid( 'post', $orphan ) );
	}

	public function test_unrelated_metadata_does_not_delay_an_empty_automatic_run() {
		$db   = bbp_db();
		$post = $this->factory->post->create();
		for ( $i = 0; $i < 120; ++$i ) {
			add_post_meta( $post, 'unrelated_freshness', 'preserved' );
		}
		$before  = $this->snapshot();
		$preview = bbp_admin_repair_freshness_dry_run();
		$this->assertSame( $before, $this->snapshot() );
		$this->assertSame( 1, $preview[0] );
		$this->assertTrue( $preview['complete'] );
		$this->assertTrue( bbp_admin_repair_tool_run_automatically( 'bbp-sync-all-topics-forums', 'bbp_admin_repair_freshness' ) );
		$this->assertSame( '120', $db->get_var( "SELECT COUNT(*) FROM {$db->postmeta} WHERE meta_key = 'unrelated_freshness'" ) );
	}

	public function test_orphan_windows_do_not_load_large_unrelated_values() {
		$db     = bbp_db();
		$forum  = $this->factory->forum->create();
		$before = (int) $db->get_var( "SELECT MAX(meta_id) FROM {$db->postmeta}" );
		$value  = str_repeat( 'x', 262144 );
		for ( $i = 0; $i < 24; ++$i ) {
			$db->insert( $db->postmeta, array( 'post_id' => $forum, 'meta_key' => 'unrelated_large_value', 'meta_value' => $value ) );
		}
		unset( $value );
		for ( $i = 0; $i < 100; ++$i ) {
			bbp_admin_repair_freshness_batch();
			$state = get_option( $this->option() );
			if ( 'orphans' === $state['phase'] && $state['orphan_cursor'] >= $before ) {
				break;
			}
		}
		$this->assertSame( 'orphans', $state['phase'] );
		$this->assertSame( $before, $state['orphan_cursor'] );
		$this->size = 8;
		$start      = (int) $db->get_row( "SHOW SESSION STATUS LIKE 'Bytes_sent'" )->Value;
		$result     = bbp_admin_repair_freshness_batch();
		$end        = (int) $db->get_row( "SHOW SESSION STATUS LIKE 'Bytes_sent'" )->Value;
		$this->assertSame( 0, $result['code'] );
		$this->assertLessThan( 1048576, $end - $start, 'Candidate inspection must not transfer unused LONGTEXT values.' );
		$this->assertGreaterThan( $before, get_option( $this->option() )['orphan_cursor'] );
		$this->size = 1000;
		$this->finish();
		$this->assertSame( '24', $db->get_var( "SELECT COUNT(*) FROM {$db->postmeta} WHERE meta_key = 'unrelated_large_value'" ) );
	}

	public function test_completed_topics_do_not_accumulate_scratch_options() {
		$db         = bbp_db();
		$forum      = $this->factory->forum->create();
		$topics     = $this->factory->topic->create_many( 20, array( 'post_parent' => $forum ) );
		$this->size = 1000;
		$result     = bbp_admin_repair_freshness_batch();
		$this->assertSame( 0, $result['code'] );
		$this->assertSame( count( $topics ), $result['topics'] );
		$this->assertSame( 'count', get_option( $this->option() )['phase'] );
		$this->assertSame( array(), $db->get_col( $db->prepare( "SELECT option_name FROM {$db->options} WHERE option_name LIKE %s", $db->esc_like( $this->option() . '_tree_' ) . '%' ) ) );
		$this->finish();
	}

	public function test_completed_topic_converted_to_forum_is_recalculated_in_the_same_run() {
		$ids        = $this->fixture();
		$this->size = 1000;
		$result     = bbp_admin_repair_freshness_batch();
		$this->assertSame( 0, $result['code'] );
		$this->assertSame( 'count', get_option( $this->option() )['phase'] );
		bbp_db()->update( bbp_db()->posts, array( 'post_type' => bbp_get_forum_post_type() ), array( 'ID' => $ids[2] ) );
		clean_post_cache( $ids[2] );
		update_post_meta( $ids[2], '_bbp_forum_id', $ids[1] );
		$this->finish();
		foreach ( array( '_bbp_last_topic_id', '_bbp_last_reply_id', '_bbp_last_active_id' ) as $key ) {
			$this->assertSame( '0', get_post_meta( $ids[2], $key, true ), $key );
		}
		$this->assertFalse( metadata_exists( 'post', $ids[2], '_bbp_last_active_time' ) );
		$this->assertSame( (string) $ids[1], get_post_meta( $ids[2], '_bbp_forum_id', true ) );
	}

	public function test_inflight_topic_checkpoint_failure_replays_without_scratch_or_metadata_churn() {
		$ids = $this->fixture();
		add_post_meta( $ids[2], '_bbp_last_reply_id', $ids[3] );
		$state = $this->advance_to_topic( $ids[2], 1 );
		$this->assertSame( 1, $state['topic_node']['key'] );
		$this->assertSame( $ids[2], $state['topic_node']['id'] );
		$fail = static function ( $value, $old ) { return $old; };
		add_filter( 'pre_update_option_' . $this->option(), $fail, 10, 2 );
		try {
			$result = bbp_admin_repair_freshness_batch();
			$this->assertSame( 7, $result['code'] );
			$this->assertSame( $state, get_option( $this->option() ) );
			$this->assertSame( array( (string) $ids[3] ), get_post_meta( $ids[2], '_bbp_last_reply_id', false ) );
		} finally {
			remove_filter( 'pre_update_option_' . $this->option(), $fail, 10 );
		}
		$this->finish();
		$this->assert_freshness( $ids );
	}

	/** @dataProvider write_failures */
	public function test_failed_and_falsely_successful_adds_never_report_completion( $reported ) {
		$ids = $this->fixture();
		delete_post_meta( $ids[2], '_bbp_last_reply_id' );
		$fail = static function ( $check, $id, $key ) use ( $ids, $reported ) {
			return $ids[2] === $id && '_bbp_last_reply_id' === $key ? $reported : $check;
		};
		$this->size = 1000;
		add_filter( 'add_post_metadata', $fail, 10, 3 );
		try {
			$result = bbp_admin_repair_freshness();
			$this->assertSame( 8, $result[0] );
			$this->assertFalse( $result['success'] );
			$this->assertFalse( $result['complete'] );
			$this->assertNotFalse( get_option( $this->option(), false ) );
		} finally {
			remove_filter( 'add_post_metadata', $fail, 10 );
		}
		$this->finish();
		$this->assert_freshness( $ids );
	}

	public function test_automatic_budget_retains_progress_then_completes_on_retry() {
		$ids = $this->fixture();
		$limit = static function () { return 1; };
		add_filter( 'bbp_admin_repair_tool_automatic_batch_limit', $limit );
		try {
			$this->assertFalse( bbp_admin_repair_tool_run_automatically( 'bbp-sync-all-topics-forums', 'bbp_admin_repair_freshness' ) );
			$this->assertContains( 'bbp-sync-all-topics-forums', bbp_get_pending_upgrades() );
			$this->assertNotFalse( get_option( $this->option(), false ) );
		} finally {
			remove_filter( 'bbp_admin_repair_tool_automatic_batch_limit', $limit );
		}
		$this->assertTrue( bbp_admin_repair_tool_run_automatically( 'bbp-sync-all-topics-forums', 'bbp_admin_repair_freshness' ) );
		$this->assertNotContains( 'bbp-sync-all-topics-forums', bbp_get_pending_upgrades() );
		$this->assert_freshness( $ids );
	}

	/** @dataProvider checkpoint_failures */
	public function test_checkpoint_failures_preserve_retryable_work( $point ) {
		$db = bbp_db();
		$ids = $this->fixture();
		$this->size = 1000;
		$option = $this->option();
		$before = $this->snapshot();
		$save = static function ( $value, $old ) use ( $point ) {
			return 'initial' === $point || is_array( $old ) ? $old : $value;
		};
		$delete = static function ( $query ) use ( $db, $option ) {
			return 0 === strpos( $query, 'DELETE FROM' ) && false !== strpos( $query, $db->options ) && false !== strpos( $query, "'{$option}'" ) ? 'DELETE FROM bbp_missing_freshness_checkpoint' : $query;
		};
		$suppression = $db->suppress_errors( true );
		if ( 'final' === $point ) {
			add_filter( 'query', $delete );
		} else {
			add_filter( 'pre_update_option_' . $option, $save, 10, 2 );
		}
		try {
			for ( $i = 0; $i < 20; ++$i ) {
				$result = bbp_admin_repair_freshness();
				if ( isset( $result['success'] ) && ! $result['success'] ) {
					break;
				}
			}
			$this->assertSame( 7, $result[0] );
			$this->assertFalse( $result['complete'] );
			if ( 'initial' === $point ) {
				$this->assertSame( $before, $this->snapshot() );
			} else {
				$this->assertNotFalse( get_option( $option, false ) );
			}
		} finally {
			remove_filter( 'query', $delete );
			remove_filter( 'pre_update_option_' . $option, $save, 10 );
			$db->suppress_errors( $suppression );
		}
		$this->finish();
		$this->assert_freshness( $ids );
	}

	public function test_failed_child_merge_does_not_acknowledge_the_unread_activity() {
		$db = bbp_db();
		$ids = $this->fixture();
		$fail = static function ( $query ) use ( $db ) {
			return 0 === strpos( $query, "SELECT post_id, meta_key, meta_value FROM {$db->postmeta}" ) ? 'SELECT * FROM bbp_missing_freshness_child' : $query;
		};
		$suppression = $db->suppress_errors( true );
		add_filter( 'query', $fail );
		try {
			for ( $i = 0; $i < 200; ++$i ) {
				$result = bbp_admin_repair_freshness_batch();
				if ( $result['code'] ) {
					break;
				}
			}
			$this->assertSame( 5, $result['code'] );
			$this->assertFalse( $result['complete'] );
			$state = get_option( $this->option() );
			$node = get_option( $this->option() . '_tree_' . $state['token'] . '_' . $state['current'] );
			$this->assertGreaterThan( 0, $node['await'] );
		} finally {
			remove_filter( 'query', $fail );
			$db->suppress_errors( $suppression );
		}
		$this->finish();
		$this->assert_freshness( $ids );
	}

	public function test_deleted_inflight_topics_restart_without_recreating_their_metadata() {
		$ids = $this->fixture();
		$state = $this->advance_to_topic( $ids[2] );
		$this->assertSame( $ids[2], $state['current'] );
		wp_delete_post( $ids[2], true );
		$result = bbp_admin_repair_freshness_batch();
		$this->assertTrue( $result['restarted'] );
		$this->finish();
		$this->assertSame( array(), get_post_meta( $ids[2] ) );
	}

	public function test_higher_topic_and_forum_ids_wait_for_a_new_run() {
		$ids = $this->fixture();
		bbp_admin_repair_freshness_batch();
		$forum = $this->factory->forum->create();
		$topic = $this->factory->topic->create( array( 'post_parent' => $forum ) );
		update_post_meta( $topic, '_bbp_last_reply_id', 999999 );
		$this->finish();
		$this->assertSame( '999999', get_post_meta( $topic, '_bbp_last_reply_id', true ) );
		$this->assert_freshness( $ids );
		$this->finish();
		$this->assertSame( '0', get_post_meta( $topic, '_bbp_last_reply_id', true ) );
		$this->assertSame( (string) $topic, get_post_meta( $forum, '_bbp_last_topic_id', true ) );
	}

	public function test_forum_hooks_and_custom_child_lists_keep_their_existing_results() {
		$ids = $this->fixture();
		$this->size = 1000;
		$child_filter = static function ( $children, $id ) use ( $ids ) { return $ids[0] === $id ? array( $ids[1] ) : array(); };
		$last_topic = static function ( $topic, $id ) use ( $ids ) { return $ids[1] === $id ? 0 : $topic; };
		$last_reply = static function ( $reply, $id ) use ( $ids ) { return $ids[1] === $id ? 0 : $reply; };
		add_filter( 'bbp_forum_query_subforum_ids', $child_filter, 10, 2 );
		add_filter( 'bbp_update_forum_last_topic_id', $last_topic, 10, 2 );
		add_filter( 'bbp_update_forum_last_reply_id', $last_reply, 10, 2 );
		try {
			$this->finish();
			$this->assertSame( '0', get_post_meta( $ids[0], '_bbp_last_topic_id', true ) );
			$this->assertSame( '0', get_post_meta( $ids[0], '_bbp_last_reply_id', true ) );
			$this->assertSame( (string) $ids[3], get_post_meta( $ids[0], '_bbp_last_active_id', true ) );
			$this->assertSame( (string) $ids[2], get_post_meta( $ids[1], '_bbp_last_topic_id', true ) );
		} finally {
			remove_filter( 'bbp_forum_query_subforum_ids', $child_filter, 10 );
			remove_filter( 'bbp_update_forum_last_topic_id', $last_topic, 10 );
			remove_filter( 'bbp_update_forum_last_reply_id', $last_reply, 10 );
		}
	}

	/** @dataProvider child_lists */
	public function test_branching_freshness_matches_the_public_updaters_with_raw_topic_dates( $custom ) {
		$db      = bbp_db();
		$parent  = $this->factory->forum->create();
		$first   = $this->factory->forum->create( array( 'post_parent' => $parent ) );
		$second  = $this->factory->forum->create( array( 'post_parent' => $parent ) );
		$topic_a = $this->factory->topic->create( array( 'post_parent' => $first ) );
		$topic_b = $this->factory->topic->create( array( 'post_parent' => $second ) );
		$reply_a = $this->factory->reply->create( array( 'post_parent' => $topic_a ) );
		$reply_b = $this->factory->reply->create( array( 'post_parent' => $topic_b ) );
		foreach ( array( $topic_a, $reply_a, $topic_b, $reply_b ) as $id ) {
			$db->update( $db->posts, array( 'post_date' => in_array( $id, array( $topic_a, $reply_a ), true ) ? '2024-01-03 12:00:00' : '2024-01-02 12:00:00' ), array( 'ID' => $id ) );
			clean_post_cache( $id );
		}
		$metadata = static function ( $value, $id, $key ) use ( $topic_a, $topic_b ) {
			return '_bbp_last_active_time' === $key && in_array( $id, array( $topic_a, $topic_b ), true ) ? '' : $value;
		};
		$display = static function ( $date, $id ) use ( $topic_a, $topic_b ) {
			return $topic_a === $id ? '2024-01-01 12:00:00' : ( $topic_b === $id ? '2024-01-04 12:00:00' : $date );
		};
		$children = static function ( $ids, $id ) use ( $parent, $first, $second ) {
			return $parent === $id ? array( $first, $second ) : $ids;
		};
		add_filter( 'get_post_metadata', $metadata, 10, 3 );
		add_filter( 'post_date', $display, 10, 2 );
		if ( $custom ) {
			add_filter( 'bbp_forum_query_subforum_ids', $children, 10, 2 );
		}
		try {
			$this->finish();
			$before = get_post_meta( $parent );
			$this->assertSame( array( (string) $topic_a ), $before['_bbp_last_topic_id'] );
			$this->assertSame( array( (string) ( $custom ? $reply_b : $reply_a ) ), $before['_bbp_last_reply_id'] );
			bbp_update_forum_last_topic_id( $parent );
			bbp_update_forum_last_reply_id( $parent );
			bbp_update_forum_last_active_id( $parent );
			bbp_update_forum_last_active_time( $parent );
			$this->assertSame( $before, get_post_meta( $parent ) );
		} finally {
			remove_filter( 'bbp_forum_query_subforum_ids', $children, 10 );
			remove_filter( 'get_post_metadata', $metadata, 10 );
			remove_filter( 'post_date', $display, 10 );
		}
	}

	public function test_empty_forum_activity_filter_receives_the_existing_empty_string() {
		$forum = $this->factory->forum->create();
		$times = array();
		$filter = static function ( $time, $id ) use ( $forum, &$times ) {
			if ( $forum === $id ) {
				$times[] = $time;
			}
			return $time;
		};
		add_filter( 'bbp_update_forum_last_active', $filter, 10, 2 );
		try {
			$this->finish();
			$this->assertSame( array( '' ), $times );
		} finally {
			remove_filter( 'bbp_update_forum_last_active', $filter, 10 );
		}
	}

	/** @dataProvider malformed_nodes */
	public function test_malformed_saved_nodes_restart_without_preview_writes( $phase, $field ) {
		$ids = $this->fixture();
		for ( $i = 0; $i < 200; ++$i ) {
			bbp_admin_repair_freshness_batch();
			$state = get_option( $this->option() );
			if ( $phase === $state['phase'] && $state['current'] ) {
				break;
			}
		}
		$this->assertSame( $phase, $state['phase'] );
		$this->assertGreaterThan( 0, $state['current'] );
		$name = $this->option() . '_tree_' . $state['token'] . '_' . $state['current'];
		$node = 'topics' === $phase ? $state['topic_node'] : get_option( $name );
		if ( 'values' === $field ) {
			$node['values'] = array( 'invalid' => 10 );
		} else {
			$node['source']['type'] = 'unrelated';
		}
		if ( 'topics' === $phase ) {
			$state['topic_node'] = $node;
			update_option( $this->option(), $state );
		} else {
			update_option( $name, $node );
		}
		$before = $this->snapshot();
		$preview = bbp_admin_repair_freshness_batch( true );
		$this->assertFalse( $preview['restarted'] );
		$this->assertSame( $before, $this->snapshot() );
		$this->assertTrue( bbp_admin_repair_freshness_batch()['restarted'] );
		$this->finish();
		$this->assert_freshness( $ids );
	}

	public function test_cycles_fail_safely_and_can_be_retried_after_reparenting() {
		$db = bbp_db();
		$ids = $this->fixture();
		$db->update( $db->posts, array( 'post_parent' => $ids[1] ), array( 'ID' => $ids[0] ) );
		$this->size = 1000;
		bbp_admin_repair_freshness_batch();
		$result = bbp_admin_repair_freshness_batch();
		$this->assertSame( 6, $result['code'] );
		$this->assertFalse( $result['complete'] );
		$db->update( $db->posts, array( 'post_parent' => 0 ), array( 'ID' => $ids[0] ) );
		clean_post_cache( $ids[0] );
		$this->finish();
		$this->assert_freshness( $ids );
	}

	public function test_topic_dates_and_forum_status_rules_match_the_existing_repair() {
		$db = bbp_db();
		$forum = $this->factory->forum->create();
		$topic = $this->factory->topic->create( array( 'post_parent' => $forum ) );
		$older = $this->factory->reply->create( array( 'post_parent' => $topic ) );
		$newer = $this->factory->reply->create( array( 'post_parent' => $topic ) );
		$db->update( $db->posts, array( 'post_date' => '2024-01-03 12:00:00' ), array( 'ID' => $older ) );
		$db->update( $db->posts, array( 'post_date' => '2024-01-02 12:00:00' ), array( 'ID' => $newer ) );
		$closed = $this->factory->topic->create( array( 'post_parent' => $forum, 'post_status' => bbp_get_closed_status_id() ) );
		$reply = $this->factory->reply->create( array( 'post_parent' => $closed, 'post_status' => bbp_get_public_status_id() ) );
		$db->update( $db->posts, array( 'post_date' => '2024-01-04 12:00:00' ), array( 'ID' => $reply ) );
		foreach ( array( $older, $newer, $reply ) as $id ) {
			clean_post_cache( $id );
		}
		$this->finish();
		$this->assertSame( (string) $newer, get_post_meta( $topic, '_bbp_last_reply_id', true ) );
		$this->assertSame( '2024-01-03 12:00:00', get_post_meta( $topic, '_bbp_last_active_time', true ) );
		$this->assertSame( (string) $topic, get_post_meta( $forum, '_bbp_last_topic_id', true ) );
		$this->assertSame( (string) $reply, get_post_meta( $forum, '_bbp_last_active_id', true ) );
		$this->assertSame( '2024-01-04 12:00:00', get_post_meta( $forum, '_bbp_last_active_time', true ) );
	}

	public function test_auto_draft_forums_retain_only_their_direct_topic_reference() {
		$forum = $this->factory->forum->create( array( 'post_status' => 'auto-draft' ) );
		$topic = $this->factory->topic->create( array( 'post_parent' => $forum ) );
		// The insertion helper publicizes forums, so set the stored status afterward
		bbp_db()->update( bbp_db()->posts, array( 'post_status' => 'auto-draft' ), array( 'ID' => $forum ) );
		clean_post_cache( $forum );
		$this->assertSame( 'auto-draft', get_post_status( $forum ) );
		$this->finish();
		$this->assertSame( (string) $topic, get_post_meta( $forum, '_bbp_last_topic_id', true ) );
		foreach ( array( '_bbp_last_reply_id', '_bbp_last_active_id', '_bbp_last_active_time' ) as $key ) {
			$this->assertFalse( metadata_exists( 'post', $forum, $key ) );
		}
	}

	/** @dataProvider write_failures */
	public function test_orphan_removal_verifies_failed_and_falsely_successful_hooks( $reported ) {
		$post = $this->factory->post->create();
		$meta = add_post_meta( $post, '_bbp_last_active_time', 'stale' );
		$this->size = 1000;
		$fail = static function ( $check, $id ) use ( $meta, $reported ) { return $meta === $id ? $reported : $check; };
		add_filter( 'delete_post_metadata_by_mid', $fail, 10, 2 );
		try {
			$result = bbp_admin_repair_freshness();
			$this->assertSame( 1, $result[0] );
			$this->assertFalse( $result['success'] );
			$this->assertFalse( $result['complete'] );
			$this->assertNotFalse( get_metadata_by_mid( 'post', $meta ) );
		} finally {
			remove_filter( 'delete_post_metadata_by_mid', $fail, 10 );
		}
		$this->finish();
		$this->assertFalse( metadata_exists( 'post', $post, '_bbp_last_active_time' ) );
	}
}
