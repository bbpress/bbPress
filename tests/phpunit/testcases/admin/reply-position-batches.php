<?php

/**
 * Bounded reply positions, verified cleanup and automatic-runner compatibility.
 *
 * @group tools
 * @group tool-batches
 */
class BBP_Tests_Admin_Reply_Position_Batches extends BBP_UnitTestCase {

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

	public function malformed() {
		return array( array( 'phase', 3 ), array( 'cursor', -1 ), array( 'meta_cursor', 'past-bound' ), array( 'context', 'post' ), array( 'changed', 1 ) );
	}

	public function source_changes() {
		return array( array( 'delete' ), array( 'post_type' ), array( 'parent' ) );
	}

	public function read_failures() {
		return array( array( 'bounds', 1 ), array( 'metadata', 1 ), array( 'reply', 2 ), array( 'rank', 4 ), array( 'checkpoint', 6 ) );
	}

	private function option() {
		return bbp_admin_repair_tool_batch_option_name( 'bbp-sync-all-reply-positions' );
	}

	private function fixture( $self = false ) {
		$topic   = $this->factory->topic->create();
		$replies = $this->factory->reply->create_many( 3, array( 'post_parent' => $topic, 'menu_order' => 7 ) );
		update_post_meta( $topic, '_bbp_reply_count', 3 );
		if ( $self ) {
			update_post_meta( $replies[0], '_bbp_reply_to', $replies[0] );
			add_post_meta( $replies[0], '_bbp_reply_to', $replies[0] );
		}
		return array( $topic, $replies );
	}

	private function seed() {
		$db = bbp_db();
		$state = array(
			'cursor'      => 0,
			'upper_id'    => (int) $db->get_var( "SELECT MAX(ID) FROM {$db->posts}" ),
			'meta_cursor' => 0,
			'meta_upper'  => 0,
			'phase'       => 1,
			'context'     => bbp_get_reply_post_type(),
			'changed'     => false,
		);
		$this->assertTrue( bbp_admin_repair_tool_save_checkpoint( 'bbp-sync-all-reply-positions', $state ) );
		return $state;
	}

	private function snapshot() {
		$db = bbp_db();
		return array(
			$db->get_results( "SELECT ID, post_type, post_parent, menu_order FROM {$db->posts} ORDER BY ID", ARRAY_A ),
			$db->get_results( "SELECT meta_id, post_id, meta_key, meta_value FROM {$db->postmeta} ORDER BY meta_id", ARRAY_A ),
			$db->get_results( "SELECT option_name, option_value FROM {$db->options} ORDER BY option_name", ARRAY_A ),
		);
	}

	private function positions( $ids ) {
		$db = bbp_db();
		return array_map( 'intval', $db->get_col( "SELECT menu_order FROM {$db->posts} WHERE ID IN (" . implode( ',', array_map( 'intval', $ids ) ) . ') ORDER BY ID' ) );
	}

	private function finish() {
		for ( $i = 0; $i < 100; ++$i ) {
			$result = bbp_admin_repair_reply_menu_order_batch();
			$this->assertSame( 0, $result['code'] );
			if ( $result['complete'] ) {
				break;
			}
		}
		$this->assertTrue( $result['complete'] );
		$this->assertFalse( get_option( $this->option(), false ) );
	}

	/** @dataProvider sizes */
	public function test_next_batch_preview_matches_run_and_repeated_runs_are_safe( $size ) {
		list( $topic, $ids ) = $this->fixture( true );
		$this->size = $size;
		$relationships = $positions = 0;
		for ( $i = 0; $i < 100; ++$i ) {
			$before  = $this->snapshot();
			$preview = bbp_admin_repair_reply_menu_order_batch( true );
			$this->assertSame( $before, $this->snapshot() );
			$result = bbp_admin_repair_reply_menu_order_batch();
			$this->assertSame( 0, $result['code'] );
			foreach ( array( 'relationships', 'positions' ) as $key ) {
				$this->assertGreaterThanOrEqual( $result[ $key ], $preview[ $key ], $key );
			}
			$this->assertTrue( $preview['complete'] );
			$this->assertLessThanOrEqual( $size, $result['relationships'] + $result['positions'] );
			$relationships += $result['relationships'];
			$positions     += $result['positions'];
			if ( $result['complete'] ) {
				break;
			}
			$this->assertLessThan( 512, strlen( serialize( get_option( $this->option() ) ) ) );
		}
		$this->assertTrue( $result['complete'] );
		$this->assertSame( 2, $relationships );
		$this->assertSame( 3, $positions );
		$this->assertSame( array( 1, 2, 3 ), $this->positions( $ids ) );
		$this->assertFalse( metadata_exists( 'post', $ids[0], '_bbp_reply_to' ) );
		$this->assertFalse( get_option( $this->option(), false ) );
		$this->assertTrue( bbp_admin_repair_tool_run_automatically( 'bbp-sync-all-reply-positions', 'bbp_admin_repair_reply_menu_order' ) );
		$this->size = 1000;
		$this->assertSame( 1, bbp_admin_repair_reply_menu_order_dry_run()[0] );
	}

	public function test_browser_preview_uses_bounded_requests_and_aggregate_totals() {
		$this->fixture( true );
		$this->size = 2;
		$before     = $this->snapshot();
		$requests   = 0;

		bbp_admin_repair_tool_execution_context( 'dry-run', 'reply-position-preview' );
		try {
			for ( $i = 0; $i < 100; ++$i ) {
				$result = bbp_admin_repair_reply_menu_order_batch( true );
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
		$this->assertSame( 2, $result['relationships'] );
		$this->assertSame( 3, $result['positions'] );
		$this->assertSame( $requests, $result['batches'] );
		$this->assertSame( $before, $this->snapshot() );
	}

	public function test_incorrect_unique_positions_are_repaired_without_changing_other_post_types() {
		list( $topic, $ids ) = $this->fixture();
		$db = bbp_db();
		foreach ( $ids as $index => $id ) {
			$db->update( $db->posts, array( 'menu_order' => 20 + $index ), array( 'ID' => $id ) );
		}
		$post = $this->factory->post->create( array( 'post_parent' => $topic, 'menu_order' => 40 ) );
		$this->finish();
		$this->assertSame( array( 1, 2, 3 ), $this->positions( $ids ) );
		$this->assertSame( array( 40 ), $this->positions( array( $post ) ) );
	}

	public function test_default_positions_use_aggregates_without_loading_complete_child_lists() {
		$this->fixture();
		$this->size = 1000;
		$queries = array();
		$record = static function ( $query ) use ( &$queries ) { $queries[] = $query; return $query; };
		add_filter( 'query', $record );
		try {
			$this->finish();
		} finally {
			remove_filter( 'query', $record );
		}
		$this->assertNotEmpty( array_filter( $queries, static function ( $query ) { return 0 === strpos( $query, 'SELECT COUNT(*)' ); } ) );
		foreach ( $queries as $query ) {
			$this->assertFalse( 0 === strpos( $query, 'SELECT ID FROM ' ) && false !== strpos( $query, 'post_parent =' ) && false === strpos( $query, 'LIMIT' ), $query );
		}
	}

	public function test_interleaved_topics_count_each_prefix_once_per_batch() {
		$topics = $this->factory->topic->create_many( 2 );
		$ids = array();
		for ( $i = 0; $i < 8; ++$i ) {
			$ids[] = $this->factory->reply->create( array( 'post_parent' => $topics[ $i % 2 ], 'menu_order' => 99 ) );
		}
		$this->size = 1000;
		$prefixes = array();
		$record = static function ( $query ) use ( &$prefixes ) {
			if ( 0 === strpos( $query, 'SELECT COUNT(*) AS reply_position' ) ) {
				preg_match( '/post_parent = (\d+)/', $query, $parent );
				preg_match( '/AND ID > (\d+)/', $query, $lower );
				if ( empty( $lower[1] ) ) {
					$id = (int) $parent[1];
					$prefixes[ $id ] = isset( $prefixes[ $id ] ) ? $prefixes[ $id ] + 1 : 1;
				}
			}
			return $query;
		};
		add_filter( 'query', $record );
		try {
			$this->finish();
		} finally {
			remove_filter( 'query', $record );
		}
		$this->assertSame( array_fill_keys( $topics, 1 ), $prefixes );
		foreach ( $ids as $id ) {
			$this->assertSame( array( bbp_get_reply_position_raw( $id ) ), $this->positions( array( $id ) ) );
		}
	}

	public function test_orphan_positions_match_native_with_a_positive_count_filter() {
		$id = $this->factory->post->create( array( 'post_type' => bbp_get_reply_post_type(), 'post_parent' => 0, 'menu_order' => 9 ) );
		$positive = static function () { return 1; };
		add_filter( 'bbp_get_topic_reply_count', $positive );
		try {
			$this->assertSame( 0, bbp_get_reply_position_raw( $id, 0 ) );
			$this->finish();
			$this->assertSame( array( 0 ), $this->positions( array( $id ) ) );
		} finally {
			remove_filter( 'bbp_get_topic_reply_count', $positive );
		}
	}

	public function test_rank_totals_reset_after_an_earlier_reply_is_deleted() {
		list( $topic, $ids ) = $this->fixture();
		$this->size = 1000;
		$fired = false;
		$remove = static function ( $topic_id, $reply_id ) use ( &$fired, $ids ) {
			if ( ! $fired && $reply_id === $ids[1] ) {
				$fired = true;
				wp_delete_post( $ids[0], true );
			}
			return $topic_id;
		};
		add_filter( 'bbp_get_reply_topic_id', $remove, 10, 2 );
		try {
			$this->finish();
			$this->assertTrue( $fired );
			$this->assertSame( array( 1, 2 ), $this->positions( array_slice( $ids, 1 ) ) );
		} finally {
			remove_filter( 'bbp_get_reply_topic_id', $remove, 10 );
		}
	}

	public function test_false_success_inserting_a_new_checkpoint_is_detected_and_caches_recover() {
		$db = bbp_db();
		$option = $this->option();
		$reject = static function ( $query ) use ( $db, $option ) {
			return 0 === strpos( $query, 'INSERT INTO ' ) && false !== strpos( $query, $db->options ) && false !== strpos( $query, $option ) ? 'SELECT 1' : $query;
		};
		add_filter( 'query', $reject );
		try {
			$this->assertFalse( bbp_admin_repair_tool_save_option( $option, array( 'new' ) ) );
			$this->assertFalse( get_option( $option, false ) );
		} finally {
			remove_filter( 'query', $reject );
		}
		$this->assertTrue( bbp_admin_repair_tool_save_option( $option, array( 'new' ) ) );
	}

	public function test_source_changes_during_a_rank_query_stop_before_writing_a_stale_position() {
		list( $topic, $ids ) = $this->fixture();
		$this->size = 1000;
		$fired = false;
		$remove = static function ( $query ) use ( &$fired, $ids ) {
			if ( ! $fired && 0 === strpos( $query, 'SELECT COUNT(*) AS reply_position' ) && false !== strpos( $query, 'MAX(ID = ' . $ids[1] . ')' ) ) {
				$fired = true;
				wp_delete_post( $ids[0], true );
			}
			return $query;
		};
		add_filter( 'query', $remove );
		try {
			$result = bbp_admin_repair_reply_menu_order_batch();
			$this->assertTrue( $fired );
			$this->assertSame( 4, $result['code'] );
			$this->assertFalse( $result['complete'] );
			$this->assertSame( array( 7, 7 ), $this->positions( array_slice( $ids, 1 ) ) );
		} finally {
			remove_filter( 'query', $remove );
		}
		$this->finish();
		$this->assertSame( array( 1, 2 ), $this->positions( array_slice( $ids, 1 ) ) );
	}

	public function test_custom_child_ordering_matches_the_native_position_helper() {
		list( $topic, $ids ) = $this->fixture();
		$reverse = static function ( $children, $parent, $type ) use ( $topic ) {
			return $parent === $topic && $type === bbp_get_reply_post_type() ? array_reverse( $children ) : $children;
		};
		add_filter( 'bbp_get_all_child_ids', $reverse, 10, 3 );
		try {
			$this->assertSame( 3, bbp_get_reply_position_raw( $ids[0], $topic ) );
			$this->finish();
			$this->assertSame( array( 3, 2, 1 ), $this->positions( $ids ) );
		} finally {
			remove_filter( 'bbp_get_all_child_ids', $reverse, 10 );
		}
	}

	public function test_statuses_and_zero_reply_count_preserve_native_position_rules() {
		list( $topic, $ids ) = $this->fixture();
		foreach ( array( 'pending', 'spam', 'trash', 'draft', 'future' ) as $status ) {
			$ids[] = $this->factory->reply->create( array( 'post_parent' => $topic, 'post_status' => $status, 'post_date' => '2030-01-01 00:00:00', 'menu_order' => 7 ) );
		}
		update_post_meta( $topic, '_bbp_reply_count', 3 );
		$expected = array();
		foreach ( $ids as $id ) {
			$expected[] = bbp_get_reply_position_raw( $id, $topic );
		}
		$this->finish();
		$this->assertSame( $expected, $this->positions( $ids ) );
		update_post_meta( $topic, '_bbp_reply_count', 0 );
		$this->finish();
		$this->assertSame( array_fill( 0, count( $ids ), 0 ), $this->positions( $ids ) );
	}

	public function test_failed_progress_save_replays_the_first_reply_and_keeps_the_last_duplicate_reachable() {
		list( $topic, $ids ) = $this->fixture();
		$state = $this->seed();
		$reject = static function ( $new, $old ) { return $old; };
		add_filter( 'pre_update_option_' . $this->option(), $reject, 10, 2 );
		try {
			$result = bbp_admin_repair_reply_menu_order_batch();
			$this->assertSame( 6, $result['code'] );
			$this->assertFalse( $result['complete'] );
			$this->assertSame( array( 1, 7, 7 ), $this->positions( $ids ) );
			$this->assertSame( $state, get_option( $this->option() ) );
		} finally {
			remove_filter( 'pre_update_option_' . $this->option(), $reject, 10 );
		}
		$this->finish();
		$this->assertSame( array( 1, 2, 3 ), $this->positions( $ids ) );
	}

	public function test_status_and_post_type_classification_matches_native_database_collation() {
		list( $topic, $ids ) = $this->fixture();
		$db = bbp_db();
		$db->update( $db->posts, array( 'post_status' => 'DRAFT' ), array( 'ID' => $ids[2] ) );
		$db->update( $db->posts, array( 'post_type' => strtoupper( bbp_get_reply_post_type() ) ), array( 'ID' => $ids[1] ) );
		wp_cache_set( 'last_changed', microtime(), 'bbpress_posts' );
		$expected = array();
		foreach ( $ids as $id ) {
			$expected[] = bbp_get_reply_position_raw( $id, $topic );
		}
		$this->finish();
		$this->assertSame( $expected, $this->positions( $ids ) );
	}

	public function test_falsely_successful_position_write_does_not_advance_the_cursor() {
		list( $topic, $ids ) = $this->fixture();
		$state = $this->seed();
		$db = bbp_db();
		$reject = static function ( $query ) use ( $db ) {
			return 0 === strpos( $query, 'UPDATE ' ) && false !== strpos( $query, $db->posts ) && false !== strpos( $query, '`menu_order`' ) ? 'SELECT 1' : $query;
		};
		add_filter( 'query', $reject );
		try {
			$result = bbp_admin_repair_reply_menu_order_batch();
			$this->assertSame( 5, $result['code'] );
			$this->assertFalse( $result['complete'] );
			$this->assertSame( 0, get_option( $this->option() )['cursor'] );
			$this->assertSame( array( 7, 7, 7 ), $this->positions( $ids ) );
		} finally {
			remove_filter( 'query', $reject );
		}
		$this->finish();
		$this->assertSame( array( 1, 2, 3 ), $this->positions( $ids ) );
	}

	public function test_metadata_cleanup_uses_one_bounded_verified_deletion_at_a_time() {
		list( $topic, $ids ) = $this->fixture( true );
		$db = bbp_db();
		$first = (int) $db->get_var( $db->prepare( "SELECT MIN(meta_id) FROM {$db->postmeta} WHERE post_id = %d AND meta_key = '_bbp_reply_to' AND post_id = meta_value", $ids[0] ) );
		$reject = static function ( $query ) use ( $db ) {
			return 0 === strpos( $query, 'DELETE FROM ' ) && false !== strpos( $query, $db->postmeta ) ? 'SELECT 1' : $query;
		};
		$this->size = 1000;
		add_filter( 'query', $reject );
		try {
			$result = bbp_admin_repair_reply_menu_order_batch();
			$this->assertSame( 3, $result['code'] );
			$this->assertLessThan( $first, get_option( $this->option() )['meta_cursor'] );
			$this->assertSame( array( 7, 7, 7 ), $this->positions( $ids ) );
		} finally {
			remove_filter( 'query', $reject );
		}
		$this->size = 1;
		$this->finish();
		$this->assertFalse( metadata_exists( 'post', $ids[0], '_bbp_reply_to' ) );
	}

	public function test_shared_state_writer_detects_false_success_without_trusting_option_cache() {
		$option = $this->option();
		$this->assertTrue( bbp_admin_repair_tool_save_option( $option, array( 'old' ) ) );
		$db = bbp_db();
		$reject = static function ( $query ) use ( $db, $option ) {
			return 0 === strpos( $query, 'UPDATE ' ) && false !== strpos( $query, $db->options ) && false !== strpos( $query, $option ) ? 'SELECT 1' : $query;
		};
		add_filter( 'query', $reject );
		try {
			$this->assertFalse( bbp_admin_repair_tool_save_option( $option, array( 'new' ) ) );
			$this->assertSame( array( 'old' ), get_option( $option ) );
		} finally {
			remove_filter( 'query', $reject );
		}
		$this->assertTrue( bbp_admin_repair_tool_save_option( $option, array( 'new' ) ) );
	}

	/** @dataProvider malformed */
	public function test_malformed_checkpoints_restart_without_dry_run_writes( $key, $value ) {
		list( $topic, $ids ) = $this->fixture();
		$state = $this->seed();
		$state[ $key ] = 'past-bound' === $value ? $state['meta_upper'] + 1 : $value;
		update_option( $this->option(), $state, false );
		$before = $this->snapshot();
		$this->assertFalse( bbp_admin_repair_reply_menu_order_batch( true )['restarted'] );
		$this->assertSame( $before, $this->snapshot() );
		$this->assertTrue( bbp_admin_repair_reply_menu_order_batch()['restarted'] );
		$this->finish();
		$this->assertSame( array( 1, 2, 3 ), $this->positions( $ids ) );
	}

	public function test_new_rows_above_fixed_bounds_are_deferred_to_the_next_run() {
		list( $topic, $ids ) = $this->fixture();
		$this->seed();
		$late = $this->factory->reply->create( array( 'post_parent' => $topic, 'menu_order' => 99 ) );
		$this->finish();
		$this->assertSame( array( 99 ), $this->positions( array( $late ) ) );
		$this->finish();
		$this->assertSame( array( 4 ), $this->positions( array( $late ) ) );
	}

	/** @dataProvider source_changes */
	public function test_changed_or_deleted_replies_are_skipped_without_writing_unrelated_posts( $change ) {
		list( $topic, $ids ) = $this->fixture();
		$other = $this->factory->topic->create();
		$this->seed();
		$fired = false;
		$mutate = static function ( $topic_id, $reply_id ) use ( &$fired, $ids, $other, $change ) {
			if ( ! $fired && $reply_id === $ids[0] ) {
				$fired = true;
				if ( 'delete' === $change ) {
					wp_delete_post( $reply_id, true );
				} elseif ( 'post_type' === $change ) {
					set_post_type( $reply_id, 'post' );
				} else {
					wp_update_post( array( 'ID' => $reply_id, 'post_parent' => $other ) );
				}
			}
			return $topic_id;
		};
		add_filter( 'bbp_get_reply_topic_id', $mutate, 10, 2 );
		try {
			$result = bbp_admin_repair_reply_menu_order_batch();
			$this->assertTrue( $fired );
			$this->assertSame( 0, $result['code'] );
			$this->assertSame( 0, $result['positions'] );
			if ( 'delete' !== $change ) {
				$this->assertSame( array( 7 ), $this->positions( array( $ids[0] ) ) );
			}
		} finally {
			remove_filter( 'bbp_get_reply_topic_id', $mutate, 10 );
		}
		$this->finish();
	}

	/** @dataProvider read_failures */
	public function test_failed_reads_never_report_completion_or_lose_the_failed_reply( $point, $code ) {
		list( $topic, $ids ) = $this->fixture();
		if ( in_array( $point, array( 'reply', 'rank' ), true ) ) {
			$this->seed();
		}
		$option = $this->option();
		$reject = static function ( $query ) use ( $point, $option ) {
			$matches = array(
				'bounds'     => 0 === strpos( $query, 'SELECT (SELECT MAX(ID)' ),
				'metadata'   => 0 === strpos( $query, 'SELECT meta_id, post_id, (post_id' ),
				'reply'      => 0 === strpos( $query, 'SELECT ID, post_parent, post_status, menu_order' ),
				'rank'       => false !== strpos( $query, 'SELECT COUNT(*) AS reply_position' ),
				'checkpoint' => 0 === strpos( $query, 'SELECT ' ) && false !== strpos( $query, $option ),
			);
			return $matches[ $point ] ? 'SELECT * FROM bbp_missing_reply_positions' : $query;
		};
		$errors = bbp_db()->suppress_errors( true );
		add_filter( 'query', $reject );
		try {
			$result = bbp_admin_repair_reply_menu_order_batch();
			$this->assertSame( $code, $result['code'] );
			$this->assertFalse( $result['complete'] );
			$this->assertSame( array( 7, 7, 7 ), $this->positions( $ids ) );
		} finally {
			remove_filter( 'query', $reject );
			bbp_db()->suppress_errors( $errors );
		}
		$this->finish();
	}

	public function test_failed_final_checkpoint_deletion_is_pending_until_retry() {
		list( $topic, $ids ) = $this->fixture();
		$this->size = 1000;
		$this->seed();
		$db = bbp_db();
		$option = $this->option();
		$reject = static function ( $query ) use ( $db, $option ) {
			return 0 === strpos( $query, 'DELETE FROM ' ) && false !== strpos( $query, $db->options ) && false !== strpos( $query, $option ) ? 'SELECT 1' : $query;
		};
		add_filter( 'query', $reject );
		try {
			$result = bbp_admin_repair_reply_menu_order_batch();
			$this->assertSame( 6, $result['code'] );
			$this->assertFalse( $result['complete'] );
			$this->assertNotFalse( get_option( $option, false ) );
			$this->assertSame( array( 1, 2, 3 ), $this->positions( $ids ) );
		} finally {
			remove_filter( 'query', $reject );
		}
		$this->finish();
	}

	/** @dataProvider sizes */
	public function test_small_site_automatic_runner_finishes_every_batch( $size ) {
		list( $topic, $ids ) = $this->fixture( true );
		$this->size = $size;
		$this->assertTrue( bbp_admin_repair_tool_run_automatically( 'bbp-sync-all-reply-positions', 'bbp_admin_repair_reply_menu_order' ) );
		$this->assertSame( array( 1, 2, 3 ), $this->positions( $ids ) );
		$this->assertNotContains( 'bbp-sync-all-reply-positions', bbp_get_pending_upgrades() );
	}

	public function test_automatic_budget_exhaustion_keeps_the_tool_pending_until_retry() {
		list( $topic, $ids ) = $this->fixture( true );
		$limit = static function ( $value, $tool ) { return 'bbp-sync-all-reply-positions' === $tool ? 1 : $value; };
		add_filter( 'bbp_admin_repair_tool_automatic_batch_limit', $limit, 10, 2 );
		try {
			$this->assertFalse( bbp_admin_repair_tool_run_automatically( 'bbp-sync-all-reply-positions', 'bbp_admin_repair_reply_menu_order' ) );
			$this->assertContains( 'bbp-sync-all-reply-positions', bbp_get_pending_upgrades() );
		} finally {
			remove_filter( 'bbp_admin_repair_tool_automatic_batch_limit', $limit, 10 );
		}
		$this->assertTrue( bbp_admin_repair_tool_run_automatically( 'bbp-sync-all-reply-positions', 'bbp_admin_repair_reply_menu_order' ) );
		$this->assertSame( array( 1, 2, 3 ), $this->positions( $ids ) );
	}
}
