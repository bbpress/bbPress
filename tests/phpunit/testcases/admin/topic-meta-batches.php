<?php

/**
 * Tests for resumable topic-reference repair and shared metadata cleanup.
 *
 * @group tools
 * @group tool-batches
 */
class BBP_Tests_Admin_Topic_Meta_Batches extends BBP_UnitTestCase {

	public function setUp(): void {
		parent::setUp();
		if ( ! function_exists( 'bbp_admin' ) ) {
			require_once BBP_PLUGIN_DIR . 'includes/admin/actions.php';
		}
		bbp_admin();
		require_once BBP_PLUGIN_DIR . 'includes/admin/tools/common.php';
		require_once BBP_PLUGIN_DIR . 'includes/admin/tools/repair.php';
		add_filter( 'bbp_admin_repair_tool_batch_size', array( $this, 'batch_size' ) );
	}

	public function tearDown(): void {
		remove_filter( 'bbp_admin_repair_tool_batch_size', array( $this, 'batch_size' ) );
		parent::tearDown();
	}

	public function batch_size() {
		return 2;
	}

	private function topics( $number = 5 ) {
		$ids = $this->factory->topic->create_many( $number );
		foreach ( $ids as $id ) {
			update_post_meta( $id, '_bbp_topic_id', 99 );
		}
		return $ids;
	}

	private function option() {
		return bbp_admin_repair_tool_batch_option_name( 'bbp-sync-topic-meta' );
	}

	private function snapshot() {
		$db = bbp_db();
		return array(
			hash( 'sha256', serialize( $db->get_results( "SELECT * FROM {$db->posts} ORDER BY ID", ARRAY_A ) ) ),
			hash( 'sha256', serialize( $db->get_results( "SELECT * FROM {$db->postmeta} ORDER BY meta_id", ARRAY_A ) ) ),
			hash( 'sha256', serialize( $db->get_results( "SELECT * FROM {$db->options} ORDER BY option_id", ARRAY_A ) ) )
		);
	}

	private function meta_ids() {
		return bbp_db()->get_col( 'SELECT meta_id FROM ' . bbp_db()->postmeta . ' ORDER BY meta_id' );
	}

	private function assert_topics( $ids ) {
		foreach ( $ids as $id ) {
			$this->assertSame( array( (string) $id ), get_post_meta( $id, '_bbp_topic_id', false ) );
		}
	}

	private function finish() {
		for ( $batch = 0; $batch < 40; ++$batch ) {
			$result = bbp_admin_repair_tool_normalize_result( bbp_admin_repair_topic_meta() );
			$this->assertTrue( $result['success'], $result['message'] );
			if ( $result['complete'] ) {
				return;
			}
		}
		$this->fail( 'Topic-reference repair did not finish.' );
	}

	public function test_previews_match_bounded_repairs_and_preserve_canonical_rows() {
		$ids  = $this->topics();
		$keep = add_post_meta( $ids[0], '_bbp_topic_id', $ids[0] );
		for ( $batch = 0; $batch < 30; ++$batch ) {
			$before  = $this->snapshot();
			$preview = bbp_admin_repair_topic_meta_batch( true );
			$this->assertSame( $before, $this->snapshot() );
			$before_ids = $this->meta_ids();
			$result     = bbp_admin_repair_topic_meta_batch();
			$after_ids  = $this->meta_ids();
			$this->assertSame( 0, $result['code'] );
			$this->assertTrue( $preview['complete'] );
			$this->assertSame( 0, $preview['code'] );
			$this->assertLessThanOrEqual( 2, count( array_diff( $before_ids, $after_ids ) ) + count( array_diff( $after_ids, $before_ids ) ) );
			$this->assertNotFalse( get_metadata_by_mid( 'post', $keep ) );
			if ( $result['complete'] ) {
				break;
			}
		}
		$this->assertTrue( $result['complete'] );
		$this->assert_topics( $ids );
		$this->assertFalse( get_option( $this->option(), false ) );
		$before = $this->snapshot();
		$this->finish();
		$this->assertSame( $before, $this->snapshot() );
	}

	public function test_browser_dry_run_uses_bounded_requests_and_aggregate_totals() {
		$ids            = $this->topics();
		$before         = $this->snapshot();
		$run_id         = wp_generate_uuid4();
		$requests       = 0;
		$result         = array();
		$preview_option = '';

		bbp_admin_repair_tool_execution_context( 'dry-run', $run_id );
		try {
			$preview_option = bbp_admin_repair_tool_batch_option_name( 'bbp-sync-topic-meta' );
			for ( $requests = 1; $requests < 30; ++$requests ) {
				$result = bbp_admin_repair_topic_meta_batch( true );
				$this->assertSame( array_slice( $before, 0, 2 ), array_slice( $this->snapshot(), 0, 2 ) );
				$this->assertSame( $requests, $result['batches'] );
				if ( $result['complete'] ) {
					break;
				}
			}
		} finally {
			bbp_admin_repair_tool_execution_context( 'run', '' );
		}

		$this->assertGreaterThan( 1, $requests );
		$this->assertTrue( $result['complete'] );
		$this->assertSame( count( $ids ), $result['topics'] );
		$this->assertFalse( get_option( $preview_option, false ) );
	}

	public function boundary_sizes() {
		return array( array( 1 ), array( 1000 ) );
	}

	/**
	 * @dataProvider boundary_sizes
	 */
	public function test_duplicate_cleanup_stays_on_the_same_object_within_write_limits( $size ) {
		$id = $this->topics( 1 )[0];
		update_post_meta( $id, '_bbp_topic_id', 0 );
		for ( $i = 0; $i < $size; ++$i ) {
			add_post_meta( $id, '_BBP_TOPIC_ID', '0' . $id );
		}
		$limit = static function () use ( $size ) {
			return $size;
		};
		add_filter( 'bbp_admin_repair_tool_batch_size', $limit, 20 );
		try {
			for ( $batch = 0; $batch < 5; ++$batch ) {
				$before  = $this->snapshot();
				$preview = bbp_admin_repair_topic_meta_batch( true );
				$this->assertSame( $before, $this->snapshot() );
				$before_ids = $this->meta_ids();
				$result     = bbp_admin_repair_topic_meta_batch();
				$after_ids  = $this->meta_ids();
				$this->assertTrue( $preview['complete'] );
			$this->assertSame( 0, $preview['code'] );
				$this->assertSame( 0, $result['code'] );
				$this->assertLessThanOrEqual( $size, count( array_diff( $before_ids, $after_ids ) ) + count( array_diff( $after_ids, $before_ids ) ) );
				if ( 0 === $batch ) {
					$this->assertFalse( $result['complete'] );
					$this->assertSame( $id, get_option( $this->option() )['cursor'] );
				}
				if ( $result['complete'] ) {
					break;
				}
			}
			$this->assertTrue( $result['complete'] );
			$this->assert_topics( array( $id ) );
		} finally {
			remove_filter( 'bbp_admin_repair_tool_batch_size', $limit, 20 );
		}
	}

	public function test_results_match_legacy_relationship_queries_and_remove_only_orphaned_references() {
		$db           = bbp_db();
		$topic        = $this->factory->topic->create();
		$reply        = $this->factory->reply->create( array( 'post_parent' => $topic ) );
		$forum        = $this->factory->forum->create();
		$orphan       = $this->factory->reply->create( array( 'post_parent' => $forum ) );
		$wrong_parent = $this->factory->reply->create( array( 'post_parent' => $reply ) );
		$other        = $this->factory->post->create();
		$db->update( $db->posts, array( 'post_type' => 'TOPIC', 'post_status' => 'bbp-unknown' ), array( 'ID' => $topic ) );
		$db->update( $db->posts, array( 'post_status' => 'trash' ), array( 'ID' => $reply ) );
		delete_post_meta( $topic, '_bbp_topic_id' );
		$keep      = add_post_meta( $topic, '_bbp_topic_id', $topic );
		$unrelated = add_post_meta( $other, '_bbp_other_reference', 77 );
		foreach ( array( $topic, $reply, $orphan, $wrong_parent, $other, 999999, 0 ) as $id ) {
			$db->insert( $db->postmeta, array( 'post_id' => $id, 'meta_key' => '_BBP_TOPIC_ID', 'meta_value' => '00' ) );
		}
		$expected = $db->get_results( $db->prepare(
			"SELECT ID AS post_id, ID AS topic_id FROM {$db->posts} WHERE post_type = %s
			UNION ALL SELECT reply.ID AS post_id, topic.ID AS topic_id FROM {$db->posts} AS reply
			INNER JOIN {$db->posts} AS topic ON reply.post_parent = topic.ID
			WHERE reply.post_type = %s AND topic.post_type = %s ORDER BY post_id",
			bbp_get_topic_post_type(), bbp_get_reply_post_type(), bbp_get_topic_post_type()
		), ARRAY_A );
		$this->finish();
		$actual = $db->get_results( "SELECT post_id, meta_value AS topic_id FROM {$db->postmeta} WHERE meta_key = '_bbp_topic_id' ORDER BY post_id", ARRAY_A );
		$this->assertSame( $expected, $actual );
		$this->assertNotFalse( get_metadata_by_mid( 'post', $keep ) );
		$this->assertNotFalse( get_metadata_by_mid( 'post', $unrelated ) );
		$this->assertSame( 0, (int) $db->get_var( "SELECT COUNT(*) FROM {$db->postmeta} WHERE BINARY meta_key = '_BBP_TOPIC_ID'" ) );
	}

	public function test_partial_add_failure_is_replay_safe_and_can_finish_automatically() {
		$ids = $this->topics( 3 );
		delete_post_meta( $ids[0], '_bbp_topic_id' );
		$fail = static function ( $check, $id, $key ) use ( $ids ) {
			return ( $id === $ids[1] ) && ( '_bbp_topic_id' === $key ) ? false : $check;
		};
		add_filter( 'add_post_metadata', $fail, 10, 3 );
		try {
			$result = bbp_admin_repair_topic_meta();
			$this->assertSame( 5, $result[0] );
			$this->assertFalse( $result['success'] );
			$this->assertFalse( $result['complete'] );
			$this->assertSame( 0, get_option( $this->option() )['cursor'] );
			$this->assert_topics( array( $ids[0] ) );
			$this->assertSame( array( '99' ), get_post_meta( $ids[1], '_bbp_topic_id', false ) );
			$this->assertFalse( bbp_admin_repair_tool_run_automatically( 'bbp-sync-topic-meta', 'bbp_admin_repair_topic_meta' ) );
			$this->assertContains( 'bbp-sync-topic-meta', bbp_get_pending_upgrades() );
		} finally {
			remove_filter( 'add_post_metadata', $fail, 10 );
		}
		$this->assertTrue( bbp_admin_repair_tool_run_automatically( 'bbp-sync-topic-meta', 'bbp_admin_repair_topic_meta' ) );
		$this->assertNotContains( 'bbp-sync-topic-meta', bbp_get_pending_upgrades() );
		$this->assert_topics( $ids );
	}

	public function test_partial_delete_failure_preserves_the_canonical_row() {
		$id = $this->topics( 1 )[0];
		delete_post_meta( $id, '_bbp_topic_id' );
		$keep   = add_post_meta( $id, '_bbp_topic_id', $id );
		$first  = add_post_meta( $id, '_bbp_topic_id', 98 );
		$second = add_post_meta( $id, '_bbp_topic_id', 99 );
		$fail   = static function ( $check, $mid ) use ( $first ) {
			return ( (int) $mid === $first ) ? false : $check;
		};
		add_filter( 'delete_post_metadata_by_mid', $fail, 10, 2 );
		try {
			$result = bbp_admin_repair_topic_meta();
			$this->assertSame( 4, $result[0] );
			$this->assertFalse( $result['success'] );
			$this->assertFalse( get_metadata_by_mid( 'post', $second ) );
			$this->assertNotFalse( get_metadata_by_mid( 'post', $keep ) );
			$this->assertSame( 0, get_option( $this->option() )['cursor'] );
		} finally {
			remove_filter( 'delete_post_metadata_by_mid', $fail, 10 );
		}
		$this->finish();
		$this->assert_topics( array( $id ) );
	}

	public function test_failed_initial_and_progress_saves_are_safe_to_retry() {
		$ids  = $this->topics();
		$fail = static function ( $value, $old ) {
			return $old;
		};
		$before = $this->snapshot();
		add_filter( 'pre_update_option_' . $this->option(), $fail, 10, 2 );
		try {
			$result = bbp_admin_repair_topic_meta();
			$this->assertSame( 7, $result[0] );
			$this->assertFalse( $result['success'] );
			$this->assertSame( $before, $this->snapshot() );
		} finally {
			remove_filter( 'pre_update_option_' . $this->option(), $fail, 10 );
		}
		$this->assertFalse( bbp_admin_repair_topic_meta()['complete'] );
		$state = get_option( $this->option() );
		add_filter( 'pre_update_option_' . $this->option(), $fail, 10, 2 );
		try {
			$result = bbp_admin_repair_topic_meta();
			$this->assertSame( 7, $result[0] );
			$this->assertFalse( $result['success'] );
			$this->assertSame( $state, get_option( $this->option() ) );
		} finally {
			remove_filter( 'pre_update_option_' . $this->option(), $fail, 10 );
		}
		$this->finish();
		$this->assert_topics( $ids );
	}

	public function test_failed_final_delete_stays_pending_until_replayed() {
		$db     = bbp_db();
		$ids    = $this->topics( 1 );
		$option = $this->option();
		$fail   = static function ( $query ) use ( $db, $option ) {
			return ( 0 === strpos( $query, 'DELETE FROM' ) ) && ( false !== strpos( $query, $db->options ) ) && ( false !== strpos( $query, $option ) ) ? 'DELETE FROM bbp_missing_reference_options' : $query;
		};
		$suppress = $db->suppress_errors( true );
		add_filter( 'query', $fail );
		try {
			$this->assertFalse( bbp_admin_repair_tool_run_automatically( 'bbp-sync-topic-meta', 'bbp_admin_repair_topic_meta' ) );
			$this->assertContains( 'bbp-sync-topic-meta', bbp_get_pending_upgrades() );
			$this->assertSame( 0, get_option( $option )['cursor'] );
			$this->assert_topics( $ids );
		} finally {
			remove_filter( 'query', $fail );
			$db->suppress_errors( $suppress );
		}
		$this->assertTrue( bbp_admin_repair_tool_run_automatically( 'bbp-sync-topic-meta', 'bbp_admin_repair_topic_meta' ) );
		$this->assertFalse( get_option( $option, false ) );
		$this->assertNotContains( 'bbp-sync-topic-meta', bbp_get_pending_upgrades() );
	}

	public function test_automatic_budget_exhaustion_preserves_progress() {
		$ids = $this->topics();
		add_filter( 'bbp_admin_repair_tool_automatic_batch_limit', '__return_true' );
		try {
			$this->assertFalse( bbp_admin_repair_tool_run_automatically( 'bbp-sync-topic-meta', 'bbp_admin_repair_topic_meta' ) );
			$this->assertContains( 'bbp-sync-topic-meta', bbp_get_pending_upgrades() );
			$this->assertSame( array( '99' ), get_post_meta( $ids[1], '_bbp_topic_id', false ) );
		} finally {
			remove_filter( 'bbp_admin_repair_tool_automatic_batch_limit', '__return_true' );
		}
		$this->assertTrue( bbp_admin_repair_tool_run_automatically( 'bbp-sync-topic-meta', 'bbp_admin_repair_topic_meta' ) );
		$this->assert_topics( $ids );
	}

	public function test_verification_mismatch_keeps_progress_replayable() {
		$db      = bbp_db();
		$id      = $this->topics( 1 )[0];
		$corrupt = static function ( $mid, $object_id, $key ) use ( $db, $id ) {
			if ( ( $object_id === $id ) && ( '_bbp_topic_id' === $key ) ) {
				$db->update( $db->postmeta, array( 'meta_value' => 999 ), array( 'meta_id' => $mid ) );
			}
		};
		add_action( 'added_post_meta', $corrupt, 10, 3 );
		try {
			$result = bbp_admin_repair_topic_meta();
			$this->assertSame( 7, $result[0] );
			$this->assertFalse( $result['success'] );
			$this->assertFalse( $result['complete'] );
			$this->assertSame( 0, get_option( $this->option() )['cursor'] );
		} finally {
			remove_action( 'added_post_meta', $corrupt, 10 );
		}
		$this->finish();
		$this->assert_topics( array( $id ) );
	}

	public function malformed_checkpoints() {
		return array( array( 'cursor', -1 ), array( 'cursor', '0' ), array( 'cursor', 'past-bound' ), array( 'upper_id', null ), array( 'post_types', array( 'other' ) ) );
	}

	/**
	 * @dataProvider malformed_checkpoints
	 */
	public function test_invalid_progress_restarts_without_preview_writes( $field, $value ) {
		$ids = $this->topics();
		$this->assertFalse( bbp_admin_repair_topic_meta()['complete'] );
		$state           = get_option( $this->option() );
		$state[ $field ] = 'past-bound' === $value ? $state['upper_id'] + 1 : $value;
		update_option( $this->option(), $state, false );
		$before = $this->snapshot();
		$this->assertStringNotContainsString( 'Saved progress is invalid.', bbp_admin_repair_topic_meta_dry_run()[1] );
		$this->assertSame( $before, $this->snapshot() );
		$this->assertStringContainsString( 'Saved progress was invalid.', bbp_admin_repair_topic_meta()[1] );
		$this->finish();
		$this->assert_topics( $ids );
	}

	public function test_new_topics_wait_for_the_next_run_and_clean_final_batches_report_completion() {
		$ids = $this->topics( 3 );
		update_post_meta( $ids[2], '_bbp_topic_id', $ids[2] );
		$this->assertFalse( bbp_admin_repair_topic_meta()['complete'] );
		$new = $this->topics( 1 )[0];
		$this->assertFalse( bbp_admin_repair_topic_meta()['complete'] );
		$result = bbp_admin_repair_topic_meta();
		$this->assertSame( 0, $result[0] );
		$this->assertTrue( $result['complete'] );
		$this->assertStringContainsString( 'Complete!', $result[1] );
		$this->assertSame( array( '99' ), get_post_meta( $new, '_bbp_topic_id', false ) );
		$this->finish();
		$this->assert_topics( array_merge( $ids, array( $new ) ) );
	}

	public function test_correct_references_refresh_stale_caches_only_on_run() {
		$id = $this->topics( 1 )[0];
		update_post_meta( $id, '_bbp_topic_id', $id );
		get_post_meta( $id );
		$cached                  = wp_cache_get( $id, 'post_meta' );
		$cached['_bbp_topic_id'] = array( '99' );
		wp_cache_set( $id, $cached, 'post_meta' );
		$before = $this->snapshot();
		$this->assertSame( 1, bbp_admin_repair_topic_meta_dry_run()[0] );
		$this->assertSame( '99', get_post_meta( $id, '_bbp_topic_id', true ) );
		$this->assertSame( 1, bbp_admin_repair_topic_meta()[0] );
		$this->assertSame( (string) $id, get_post_meta( $id, '_bbp_topic_id', true ) );
		$this->assertSame( $before, $this->snapshot() );
	}

	public function test_source_and_metadata_read_failures_leave_data_and_progress_unchanged() {
		$db    = bbp_db();
		$ids   = $this->topics( 3 );
		$cases = array(
			"SELECT MAX(ID) FROM {$db->posts}" => 1,
			"SELECT MAX(post_id) FROM {$db->postmeta}" => 3,
			"(SELECT ID FROM {$db->posts} WHERE post_type IN" => 7,
			'SELECT posts.ID, CASE' => 2,
			"SELECT meta_id FROM {$db->postmeta}" => 3
		);
		foreach ( $cases as $prefix => $code ) {
			if ( 0 === strpos( $prefix, 'SELECT meta_id' ) ) {
				update_option( $this->option(), array( 'cursor' => 0, 'upper_id' => max( $ids ), 'post_types' => array( bbp_get_topic_post_type(), bbp_get_reply_post_type() ) ), false );
			}
			$fail = static function ( $query ) use ( $prefix ) {
				return ( 0 === strpos( $query, $prefix ) || ( 0 === strpos( $prefix, 'SELECT meta_id' ) && 0 === strpos( $query, 'SELECT post_id AS object_id, COUNT(*) AS row_count' ) ) ) ? 'SELECT ID FROM bbp_missing_reference_source' : $query;
			};
			$before   = $this->snapshot();
			$suppress = $db->suppress_errors( true );
			add_filter( 'query', $fail );
			try {
				foreach ( array( 'bbp_admin_repair_topic_meta_dry_run', 'bbp_admin_repair_topic_meta' ) as $callback ) {
					$result = call_user_func( $callback );
					$this->assertSame( $code, $result[0] );
					$this->assertFalse( $result['success'] );
					$this->assertFalse( $result['complete'] );
					$this->assertSame( $before, $this->snapshot() );
				}
			} finally {
				remove_filter( 'query', $fail );
				$db->suppress_errors( $suppress );
			}
		}
		$this->finish();
		$this->assert_topics( $ids );
	}

	public function disappearing_rows() {
		return array( array( array( 2 ) ), array( array( 0, 1 ) ) );
	}

	/**
	 * @dataProvider disappearing_rows
	 */
	public function test_disappearing_posts_leave_later_references_reachable( $indexes ) {
		$db      = bbp_db();
		$ids     = $this->topics();
		$removed = false;
		$filter  = static function ( $query ) use ( $db, $ids, $indexes, &$removed ) {
			if ( ! $removed && ( 0 === strpos( $query, 'SELECT posts.ID, CASE' ) ) ) {
				$removed = true;
				foreach ( $indexes as $index ) {
					$db->delete( $db->posts, array( 'ID' => $ids[ $index ] ) );
				}
			}
			return $query;
		};
		add_filter( 'query', $filter );
		try {
			$this->assertFalse( bbp_admin_repair_topic_meta()['complete'] );
		} finally {
			remove_filter( 'query', $filter );
		}
		$this->finish();
		foreach ( $ids as $index => $id ) {
			$this->assertSame( in_array( $index, $indexes, true ) ? array() : array( (string) $id ), get_post_meta( $id, '_bbp_topic_id', false ) );
		}
	}

	public function test_site_specific_references_and_progress_remain_isolated() {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'Requires multisite.' );
		}
		$ids = $this->topics( 3 );
		$this->assertFalse( bbp_admin_repair_topic_meta()['complete'] );
		$state   = get_option( $this->option() );
		$blog_id = self::factory()->blog->create();
		switch_to_blog( $blog_id );
		try {
			$other = $this->topics( 3 );
			$this->assertTrue( bbp_admin_repair_tool_run_automatically( 'bbp-sync-topic-meta', 'bbp_admin_repair_topic_meta' ) );
			$this->assert_topics( $other );
			$this->assertFalse( get_option( $this->option(), false ) );
		} finally {
			restore_current_blog();
		}
		$this->assertSame( $state, get_option( $this->option() ) );
		$this->finish();
		$this->assert_topics( $ids );
	}
}
