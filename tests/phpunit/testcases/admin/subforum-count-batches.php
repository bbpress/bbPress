<?php

/**
 * Tests for resumable direct subforum recounts.
 *
 * @group tools
 * @group tool-batches
 */
class BBP_Tests_Admin_Subforum_Count_Batches extends BBP_UnitTestCase {

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

	private function forums( $number = 5 ) {
		$ids = $this->factory->forum->create_many( $number );
		foreach ( $ids as $id ) {
			update_post_meta( $id, '_bbp_forum_subforum_count', 99 );
		}
		return $ids;
	}

	private function option() {
		return bbp_admin_repair_tool_batch_option_name( 'bbp-forum-subforums' );
	}

	private function snapshot() {
		$db = bbp_db();
		return array(
			hash( 'sha256', serialize( $db->get_results( "SELECT * FROM {$db->postmeta} ORDER BY meta_id", ARRAY_A ) ) ),
			hash( 'sha256', serialize( $db->get_results( "SELECT * FROM {$db->options} ORDER BY option_id", ARRAY_A ) ) )
		);
	}

	private function meta_ids() {
		return bbp_db()->get_col( "SELECT meta_id FROM " . bbp_db()->postmeta . " ORDER BY meta_id" );
	}

	private function assert_counts( $ids ) {
		foreach ( $ids as $id ) {
			$this->assertSame( array( '0' ), get_post_meta( $id, '_bbp_forum_subforum_count', false ) );
		}
	}

	private function finish() {
		for ( $i = 0; $i < 40; ++$i ) {
			$result = bbp_admin_repair_tool_normalize_result( bbp_admin_repair_forum_subforum_count() );
			$this->assertTrue( $result['success'], $result['message'] );
			if ( $result['complete'] ) {
				return;
			}
		}
		$this->fail( 'The subforum recount did not finish.' );
	}

	public function test_previews_match_bounded_metadata_repairs_and_preserve_correct_rows() {
		$ids = $this->forums();
		delete_post_meta( $ids[0], '_bbp_forum_subforum_count' );
		$keep = add_post_meta( $ids[0], '_bbp_forum_subforum_count', 0 );
		add_post_meta( $ids[0], '_bbp_forum_subforum_count', 99 );
		$calls  = array();
		$filter = static function ( $count, $id ) use ( &$calls ) {
			$calls[] = array( $count, $id );
			return 123;
		};
		add_filter( 'bbp_update_forum_subforum_count', $filter, 10, 2 );
		try {
			for ( $batch = 0; $batch < 20; ++$batch ) {
				$before         = $this->snapshot();
				$previous_calls = $calls;
				$preview        = bbp_admin_repair_forum_subforum_count_batch( true );
				$this->assertSame( 0, $preview['code'] );
				$this->assertSame( $before, $this->snapshot() );
				$this->assertSame( $previous_calls, $calls );
				$before_ids = $this->meta_ids();
				$result     = $this->assertMetadataWritesBounded( 'post', 2, 'bbp_admin_repair_forum_subforum_count_batch' );
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
			$this->assert_counts( $ids );
			$this->assertCount( 5, $calls );
			foreach ( $calls as $call ) {
				$this->assertSame( 0, $call[0] );
			}
			$this->assertFalse( get_option( $this->option(), false ) );
			$before = $this->snapshot();
			$this->finish();
			$this->assertSame( $before, $this->snapshot() );
			$this->assertCount( 5, $calls );
		} finally {
			remove_filter( 'bbp_update_forum_subforum_count', $filter, 10 );
		}
	}

	public function boundary_sizes() {
		return array( array( 1 ), array( 1000 ) );
	}

	/**
	 * @dataProvider boundary_sizes
	 */
	public function test_duplicate_heavy_forums_resume_within_boundary_write_limits( $size ) {
		$id = $this->forums( 1 )[0];
		delete_post_meta( $id, '_bbp_forum_subforum_count' );
		for ( $i = 0; $i <= $size; ++$i ) {
			add_post_meta( $id, '_bbp_forum_subforum_count', 99 );
		}
		$limit = static function () use ( $size ) {
			return $size;
		};
		add_filter( 'bbp_admin_repair_tool_batch_size', $limit, 20 );
		try {
			for ( $batch = 0; $batch < 5; ++$batch ) {
				$before  = $this->snapshot();
				$preview = bbp_admin_repair_forum_subforum_count_batch( true );
				$this->assertSame( $before, $this->snapshot() );
				$before_ids = $this->meta_ids();
				$result     = $this->assertMetadataWritesBounded( 'post', $size, 'bbp_admin_repair_forum_subforum_count_batch' );
				$after_ids  = $this->meta_ids();
				$this->assertSame( 0, $result['code'] );
				$this->assertTrue( $preview['complete'] );
			$this->assertSame( 0, $preview['code'] );
				$this->assertLessThanOrEqual( $size, count( array_diff( $before_ids, $after_ids ) ) + count( array_diff( $after_ids, $before_ids ) ) );
				if ( 0 === $batch ) {
					$this->assertFalse( $result['complete'] );
					$this->assertSame( $id, get_option( $this->option() )['cursor'] );
					$this->assertCount( 2, get_post_meta( $id, '_bbp_forum_subforum_count', false ) );
				}
				if ( $result['complete'] ) {
					break;
				}
			}
			$this->assertTrue( $result['complete'] );
			$this->assert_counts( array( $id ) );
		} finally {
			remove_filter( 'bbp_admin_repair_tool_batch_size', $limit, 20 );
		}
	}

	public function test_partial_add_failures_preserve_old_counts_and_retry_automatically() {
		$ids = $this->forums( 3 );
		delete_post_meta( $ids[0], '_bbp_forum_subforum_count' );
		$fail = static function ( $check, $id, $key ) use ( $ids ) {
			return ( $ids[1] === $id ) && ( '_bbp_forum_subforum_count' === $key ) ? false : $check;
		};
		add_filter( 'add_post_metadata', $fail, 10, 3 );
		try {
			$result = bbp_admin_repair_forum_subforum_count();
			$this->assertSame( 6, $result[0] );
			$this->assertFalse( $result['success'] );
			$this->assertFalse( $result['complete'] );
			$this->assertSame( 0, get_option( $this->option() )['cursor'] );
			$this->assertSame( array( '0' ), get_post_meta( $ids[0], '_bbp_forum_subforum_count', false ) );
			$this->assertSame( array( '99' ), get_post_meta( $ids[1], '_bbp_forum_subforum_count', false ) );
			$this->assertFalse( bbp_admin_repair_tool_run_automatically( 'bbp-forum-subforums', 'bbp_admin_repair_forum_subforum_count' ) );
			$this->assertContains( 'bbp-forum-subforums', bbp_get_pending_upgrades() );
		} finally {
			remove_filter( 'add_post_metadata', $fail, 10 );
		}
		$this->assertTrue( bbp_admin_repair_tool_run_automatically( 'bbp-forum-subforums', 'bbp_admin_repair_forum_subforum_count' ) );
		$this->assertNotContains( 'bbp-forum-subforums', bbp_get_pending_upgrades() );
		$this->assert_counts( $ids );
	}

	public function test_partial_cleanup_failures_preserve_the_canonical_row() {
		$id = $this->forums( 1 )[0];
		delete_post_meta( $id, '_bbp_forum_subforum_count' );
		$keep   = add_post_meta( $id, '_bbp_forum_subforum_count', 0 );
		$first  = add_post_meta( $id, '_bbp_forum_subforum_count', 99 );
		$second = add_post_meta( $id, '_bbp_forum_subforum_count', 98 );
		$fail   = static function ( $check, $meta_id ) use ( $first ) {
			return ( (int) $meta_id === $first ) ? false : $check;
		};
		add_filter( 'delete_post_metadata_by_mid', $fail, 10, 2 );
		try {
			$result = bbp_admin_repair_forum_subforum_count();
			$this->assertSame( 1, $result[0] );
			$this->assertFalse( $result['success'] );
			$this->assertFalse( $result['complete'] );
			$this->assertSame( 0, get_option( $this->option() )['cursor'] );
			$this->assertFalse( get_metadata_by_mid( 'post', $second ) );
			$this->assertNotFalse( get_metadata_by_mid( 'post', $keep ) );
		} finally {
			remove_filter( 'delete_post_metadata_by_mid', $fail, 10 );
		}
		$this->finish();
		$this->assert_counts( array( $id ) );
		$this->assertNotFalse( get_metadata_by_mid( 'post', $keep ) );
	}

	public function test_failed_checkpoint_saves_leave_counts_safe_to_replay() {
		$ids    = $this->forums();
		$option = $this->option();
		$fail   = static function ( $value, $old ) {
			return $old;
		};
		$before = $this->snapshot();
		add_filter( 'pre_update_option_' . $option, $fail, 10, 2 );
		try {
			$result = bbp_admin_repair_forum_subforum_count();
			$this->assertSame( 6, $result[0] );
			$this->assertFalse( $result['success'] );
			$this->assertSame( $before, $this->snapshot() );
		} finally {
			remove_filter( 'pre_update_option_' . $option, $fail, 10 );
		}
		$this->assertFalse( bbp_admin_repair_forum_subforum_count()['complete'] );
		$state = get_option( $option );
		add_filter( 'pre_update_option_' . $option, $fail, 10, 2 );
		try {
			$result = bbp_admin_repair_forum_subforum_count();
			$this->assertSame( 6, $result[0] );
			$this->assertFalse( $result['success'] );
			$this->assertSame( $state, get_option( $option ) );
		} finally {
			remove_filter( 'pre_update_option_' . $option, $fail, 10 );
		}
		$this->finish();
		$this->assert_counts( $ids );
	}

	public function test_failed_final_checkpoint_deletes_stay_pending() {
		$db  = bbp_db();
		$ids = $this->forums( 3 );
		$this->assertFalse( bbp_admin_repair_forum_subforum_count()['complete'] );
		$this->assertFalse( bbp_admin_repair_forum_subforum_count()['complete'] );
		$option = $this->option();
		$state  = get_option( $option );
		$fail   = static function ( $query ) use ( $db, $option ) {
			return ( 0 === strpos( $query, 'DELETE FROM' ) ) && ( false !== strpos( $query, $db->options ) ) && ( false !== strpos( $query, $option ) )
				? 'DELETE FROM bbp_missing_subforum_options'
				: $query;
		};
		$suppress = $db->suppress_errors( true );
		add_filter( 'query', $fail );
		try {
			$this->assertFalse( bbp_admin_repair_tool_run_automatically( 'bbp-forum-subforums', 'bbp_admin_repair_forum_subforum_count' ) );
			$this->assertContains( 'bbp-forum-subforums', bbp_get_pending_upgrades() );
			$this->assertSame( $state, get_option( $option ) );
		} finally {
			remove_filter( 'query', $fail );
			$db->suppress_errors( $suppress );
		}
		$this->assertTrue( bbp_admin_repair_tool_run_automatically( 'bbp-forum-subforums', 'bbp_admin_repair_forum_subforum_count' ) );
		$this->assertNotContains( 'bbp-forum-subforums', bbp_get_pending_upgrades() );
		$this->assertFalse( get_option( $option, false ) );
		$this->assert_counts( $ids );
	}

	public function test_automatic_batch_budgets_remain_resumable() {
		$ids = $this->forums();
		add_filter( 'bbp_admin_repair_tool_automatic_batch_limit', '__return_true' );
		try {
			$this->assertFalse( bbp_admin_repair_tool_run_automatically( 'bbp-forum-subforums', 'bbp_admin_repair_forum_subforum_count' ) );
			$this->assertContains( 'bbp-forum-subforums', bbp_get_pending_upgrades() );
			$this->assertSame( array( '99' ), get_post_meta( $ids[1], '_bbp_forum_subforum_count', false ) );
		} finally {
			remove_filter( 'bbp_admin_repair_tool_automatic_batch_limit', '__return_true' );
		}
		$this->assertTrue( bbp_admin_repair_tool_run_automatically( 'bbp-forum-subforums', 'bbp_admin_repair_forum_subforum_count' ) );
		$this->assert_counts( $ids );
	}

	public function removed_page_rows() {
		return array( 'lookahead' => array( array( 2 ) ), 'selected rows' => array( array( 0, 1 ) ) );
	}

	/**
	 * @dataProvider removed_page_rows
	 */
	public function test_disappearing_page_rows_do_not_skip_later_forums( $indexes ) {
		$db  = bbp_db();
		$ids = $this->forums();
		foreach ( array_slice( $ids, 0, 2 ) as $id ) {
			update_post_meta( $id, '_bbp_forum_subforum_count', 0 );
		}
		$removed = false;
		$filter  = static function ( $query ) use ( $db, $ids, $indexes, &$removed ) {
			if ( ! $removed && ( 0 === strpos( $query, 'SELECT `forums`.`ID`, COUNT( `children`.`ID` )' ) ) ) {
				$removed = true;
				foreach ( $indexes as $index ) {
					$db->delete( $db->posts, array( 'ID' => $ids[ $index ] ) );
				}
			}
			return $query;
		};
		add_filter( 'query', $filter );
		try {
			$result = bbp_admin_repair_forum_subforum_count();
			$this->assertFalse( $result['complete'] );
		} finally {
			remove_filter( 'query', $filter );
		}
		$this->finish();
		$deleted = array_map( static function ( $index ) use ( $ids ) {
			return $ids[ $index ];
		}, $indexes );
		$this->assert_counts( array_diff( $ids, $deleted ) );
		$this->assertFalse( get_option( $this->option(), false ) );
	}

	public function test_new_forums_wait_for_the_next_run() {
		$ids = $this->forums( 3 );
		$this->assertFalse( bbp_admin_repair_forum_subforum_count()['complete'] );
		$new = $this->forums( 1 )[0];
		$this->finish();
		$this->assertSame( array( '99' ), get_post_meta( $new, '_bbp_forum_subforum_count', false ) );
		$this->finish();
		$this->assert_counts( array_merge( $ids, array( $new ) ) );
	}

	public function test_a_clean_final_batch_reports_completion() {
		$ids = $this->forums( 3 );
		update_post_meta( $ids[2], '_bbp_forum_subforum_count', 0 );
		$this->assertFalse( bbp_admin_repair_forum_subforum_count()['complete'] );
		$this->assertFalse( bbp_admin_repair_forum_subforum_count()['complete'] );
		$last = bbp_admin_repair_forum_subforum_count();
		$this->assertSame( 0, $last[0] );
		$this->assertTrue( $last['complete'] );
		$this->assertStringContainsString( 'Complete!', $last[1] );
		$this->assertStringContainsString( 'This batch:', $last[1] );
		$this->assertStringContainsString( '0 forums.', $last[1] );
	}

	public function test_status_readers_preserve_all_and_bounded_id_scopes() {
		$ids    = $this->forums( 3 );
		$before = $this->snapshot();
		$this->assertSame( array( 'code' => 0, 'forum_ids' => $ids, 'repair_count' => 3 ), bbp_admin_repair_forum_subforum_count_get_status() );
		$this->assertSame( array( $ids[1] ), bbp_admin_repair_forum_subforum_count_get_status( array( $ids[1] ) )['forum_ids'] );
		$this->assertSame( array( 'code' => 0, 'forum_ids' => array(), 'repair_count' => 0 ), bbp_admin_repair_forum_subforum_count_get_status( array() ) );
		$this->assertSame( $before, $this->snapshot() );
	}

	public function test_run_refreshes_stale_metadata_caches_without_rewriting_correct_counts() {
		$id = $this->forums( 1 )[0];
		update_post_meta( $id, '_bbp_forum_subforum_count', 0 );
		get_post_meta( $id );
		$cached                            = wp_cache_get( $id, 'post_meta' );
		$cached['_bbp_forum_subforum_count'] = array( '99' );
		wp_cache_set( $id, $cached, 'post_meta' );
		$before = $this->snapshot();
		$this->assertSame( 1, bbp_admin_repair_forum_subforum_count_dry_run()[0] );
		$this->assertSame( '99', get_post_meta( $id, '_bbp_forum_subforum_count', true ) );
		$this->assertSame( 1, bbp_admin_repair_forum_subforum_count()[0] );
		$this->assertSame( '0', get_post_meta( $id, '_bbp_forum_subforum_count', true ) );
		$this->assertSame( $before, $this->snapshot() );
	}

	public function malformed_checkpoints() {
		return array( array( 'cursor', -1 ), array( 'cursor', '0' ), array( 'cursor', 'past-bound' ), array( 'upper_id', null ), array( 'post_type', array() ) );
	}

	/**
	 * @dataProvider malformed_checkpoints
	 */
	public function test_bad_progress_restarts_without_preview_writes( $field, $value ) {
		$ids = $this->forums();
		$this->assertFalse( bbp_admin_repair_forum_subforum_count()['complete'] );
		$state           = get_option( $this->option() );
		$state[ $field ] = 'past-bound' === $value ? $state['upper_id'] + 1 : $value;
		update_option( $this->option(), $state, false );
		$before = $this->snapshot();
		$this->assertStringNotContainsString( 'Saved progress is invalid.', bbp_admin_repair_forum_subforum_count_dry_run()[1] );
		$this->assertSame( $before, $this->snapshot() );
		$this->assertStringContainsString( 'Saved progress was invalid.', bbp_admin_repair_forum_subforum_count()[1] );
		$this->finish();
		$this->assert_counts( $ids );
	}

	public function test_countable_status_filters_and_unsupported_forums_keep_their_scope() {
		$db     = bbp_db();
		$parent = $this->factory->forum->create();
		foreach ( array( 'publish', 'private', 'hidden', 'draft' ) as $status ) {
			$child = $this->factory->forum->create( array( 'post_parent' => $parent, 'post_status' => $status ) );
			// Forum insertion publicizes draft fixtures
			$db->update( $db->posts, array( 'post_status' => $status ), array( 'ID' => $child ) );
			wp_cache_delete( $child, 'posts' );
		}
		$this->factory->post->create( array( 'post_parent' => $parent, 'post_status' => 'private' ) );
		$unknown = $this->factory->forum->create();
		$db->update( $db->posts, array( 'post_status' => 'bbp-unknown' ), array( 'ID' => $unknown ) );
		update_post_meta( $unknown, '_bbp_forum_subforum_count', 77 );
		update_post_meta( $parent, '_bbp_forum_subforum_count', 99 );
		$filter = static function () {
			return array( 'PRIVATE', 'draft' );
		};
		add_filter( 'bbp_get_countable_forum_statuses', $filter );
		try {
			$this->finish();
			$this->assertSame( array( '2' ), get_post_meta( $parent, '_bbp_forum_subforum_count', false ) );
			$this->assertSame( array( '77' ), get_post_meta( $unknown, '_bbp_forum_subforum_count', false ) );
			add_filter( 'bbp_get_countable_forum_statuses', '__return_empty_array', 20 );
			$this->finish();
			$this->assertSame( array( '0' ), get_post_meta( $parent, '_bbp_forum_subforum_count', false ) );
		} finally {
			remove_filter( 'bbp_get_countable_forum_statuses', '__return_empty_array', 20 );
			remove_filter( 'bbp_get_countable_forum_statuses', $filter );
		}
	}

	public function test_read_errors_preserve_counts_and_saved_progress() {
		$db    = bbp_db();
		$ids   = $this->forums( 3 );
		$cases = array(
			"SELECT MAX(ID) FROM {$db->posts}" => 3,
			"SELECT ID FROM {$db->posts} WHERE post_type" => 5,
			'SELECT `forums`.`ID`, COUNT( `children`.`ID` )' => 3,
			"SELECT meta_id FROM {$db->postmeta}" => 4
		);
		foreach ( $cases as $prefix => $code ) {
			if ( 4 === $code ) {
				update_option( $this->option(), array( 'cursor' => 0, 'upper_id' => max( $ids ), 'post_type' => bbp_get_forum_post_type() ), false );
			}
			$fail = static function ( $query ) use ( $prefix ) {
				return ( 0 === strpos( $query, $prefix ) || ( 0 === strpos( $prefix, 'SELECT meta_id' ) && 0 === strpos( $query, 'SELECT post_id AS object_id, COUNT(*) AS row_count' ) ) ) ? 'SELECT ID FROM bbp_missing_subforum_source' : $query;
			};
			$before   = $this->snapshot();
			$suppress = $db->suppress_errors( true );
			add_filter( 'query', $fail );
			try {
				foreach ( array( 'bbp_admin_repair_forum_subforum_count_dry_run', 'bbp_admin_repair_forum_subforum_count' ) as $callback ) {
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
		$this->assert_counts( $ids );
	}

	public function test_current_blog_counts_and_checkpoints_stay_isolated() {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'Requires multisite.' );
		}
		$ids = $this->forums( 3 );
		$this->assertFalse( bbp_admin_repair_forum_subforum_count()['complete'] );
		$state   = get_option( $this->option() );
		$blog_id = self::factory()->blog->create();
		switch_to_blog( $blog_id );
		try {
			$other = $this->forums( 3 );
			$this->assertTrue( bbp_admin_repair_tool_run_automatically( 'bbp-forum-subforums', 'bbp_admin_repair_forum_subforum_count' ) );
			$this->assert_counts( $other );
			$this->assertFalse( get_option( $this->option(), false ) );
		} finally {
			restore_current_blog();
		}
		$this->assertSame( $state, get_option( $this->option() ) );
		$this->assertSame( array( '99' ), get_post_meta( $ids[1], '_bbp_forum_subforum_count', false ) );
		$this->finish();
		$this->assert_counts( $ids );
	}
}
