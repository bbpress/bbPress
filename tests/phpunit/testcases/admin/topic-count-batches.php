<?php

/**
 * Tests for resumable public and non-public topic reply recounts.
 *
 * @group tools
 * @group tool-batches
 */
class BBP_Tests_Admin_Topic_Count_Batches extends BBP_UnitTestCase {

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

	public function tools() {
		return array(
			'public' => array( false, 'bbp-topic-replies', '_bbp_reply_count', 'bbp_admin_repair_topic_reply_count' ),
			'hidden' => array( true, 'bbp-topic-hidden-replies', '_bbp_reply_count_hidden', 'bbp_admin_repair_topic_hidden_reply_count' ),
		);
	}

	private function topics( $hidden, $key, $number = 5 ) {
		$topics = $this->factory->topic->create_many( $number );
		foreach ( $topics as $topic_id ) {
			$this->factory->reply->create( array(
				'post_parent' => $topic_id,
				'post_status' => $hidden ? bbp_get_spam_status_id() : bbp_get_public_status_id()
			) );
			delete_post_meta( $topic_id, $key );
		}
		return $topics;
	}

	private function snapshot() {
		$db = bbp_db();
		return array(
			$db->get_results( "SELECT * FROM {$db->postmeta} ORDER BY meta_id", ARRAY_A ),
			$db->get_results( "SELECT * FROM {$db->options} ORDER BY option_id", ARRAY_A )
		);
	}

	private function count_ids( $topic_id, $key ) {
		$db = bbp_db();
		return $db->get_col( $db->prepare( "SELECT meta_id FROM {$db->postmeta} WHERE post_id = %d AND meta_key = %s ORDER BY meta_id", $topic_id, $key ) );
	}

	private function finish( $callback ) {
		for ( $batch = 0; $batch < 30; ++$batch ) {
			$result = bbp_admin_repair_tool_normalize_result( call_user_func( $callback ) );
			$this->assertTrue( $result['success'], $result['message'] );
			if ( $result['complete'] ) {
				return;
			}
		}
		$this->fail( 'The recount did not finish.' );
	}

	private function assert_counts( $topics, $key ) {
		foreach ( $topics as $topic_id ) {
			$this->assertSame( array( '1' ), get_post_meta( $topic_id, $key, false ) );
		}
	}

	/**
	 * @dataProvider tools
	 */
	public function test_batches_preserve_canonical_rows_and_preview_exact_bounded_writes( $hidden, $tool_id, $key, $callback ) {
		$db      = bbp_db();
		$topics  = $this->topics( $hidden, $key );
		$keep_id = add_post_meta( $topics[0], $key, 1 );
		foreach ( $topics as $topic_id ) {
			add_post_meta( $topic_id, $key, 99 );
			add_post_meta( $topic_id, strtoupper( $key ), '01' );
		}
		for ( $batch = 0; $batch < 30; ++$batch ) {
			$before  = $this->snapshot();
			$preview = bbp_admin_repair_topic_reply_count_batch( $hidden, true );
			$this->assertSame( 0, $preview['code'] );
			$this->assertSame( $before, $this->snapshot() );
			$before_ids = $db->get_col( "SELECT meta_id FROM {$db->postmeta}" );
			$result     = bbp_admin_repair_topic_reply_count_batch( $hidden );
			$after_ids  = $db->get_col( "SELECT meta_id FROM {$db->postmeta}" );
			$this->assertSame( 0, $result['code'] );
			$this->assertSame( $preview['repaired'], $result['repaired'] );
			$this->assertSame( $preview['complete'], $result['complete'] );
			$this->assertLessThanOrEqual( 2, count( array_diff( $before_ids, $after_ids ) ) + count( array_diff( $after_ids, $before_ids ) ) );
			$this->assertNotFalse( get_metadata_by_mid( 'post', $keep_id ) );
			if ( $result['complete'] ) {
				break;
			}
		}
		$this->assertTrue( $result['complete'] );
		$this->assert_counts( $topics, $key );
		$this->assertFalse( get_option( bbp_admin_repair_tool_batch_option_name( $tool_id ), false ) );
		$before = $this->snapshot();
		$this->finish( $callback );
		$this->assertSame( $before, $this->snapshot() );
	}

	/**
	 * @dataProvider tools
	 */
	public function test_results_match_legacy_grouped_sql_and_leave_other_metadata_alone( $hidden, $tool_id, $key, $callback ) {
		$db     = bbp_db();
		$topics = $this->topics( $hidden, $key, 4 );
		$db->update( $db->posts, array( 'post_status' => bbp_get_pending_status_id() ), array( 'ID' => $topics[1] ) );
		$db->update( $db->posts, array( 'post_status' => 'PUBLISH' ), array( 'ID' => $topics[2] ) );
		$custom = "custom'100%";
		$this->factory->reply->create( array( 'post_parent' => $topics[2], 'post_status' => $custom ) );
		$this->factory->post->create( array( 'post_parent' => $topics[0] ) );
		$empty_topic = $this->factory->topic->create();
		$other_post  = $this->factory->post->create();
		$other_id    = add_post_meta( $other_post, $key, 77 );
		$orphan_id   = add_post_meta( 999999, $key, 78 );
		foreach ( $topics as $topic_id ) {
			add_post_meta( $topic_id, strtoupper( $key ), '01' );
			add_post_meta( $topic_id, $key, 99 );
		}
		$status_filter = $hidden ? 'bbp_get_non_public_reply_statuses' : 'bbp_get_public_reply_statuses';
		$statuses      = static function ( $values ) use ( $custom ) {
			return array_merge( array_map( 'strtoupper', $values ), array( $custom ) );
		};
		add_filter( $status_filter, $statuses );
		try {
			$reply_statuses = $hidden ? bbp_get_non_public_reply_statuses() : bbp_get_public_reply_statuses();
			$reply_slots    = implode( ', ', array_fill( 0, count( $reply_statuses ), '%s' ) );
			if ( $hidden ) {
				$sql = "SELECT topics.ID, COUNT(replies.ID) AS count FROM {$db->posts} AS replies
					INNER JOIN {$db->posts} AS topics ON topics.ID = replies.post_parent AND topics.post_type = %s
					WHERE replies.post_type = %s AND replies.post_status IN ({$reply_slots}) GROUP BY topics.ID ORDER BY topics.ID";
				$args = array_merge( array( bbp_get_topic_post_type(), bbp_get_reply_post_type() ), $reply_statuses );
			} else {
				$topic_statuses = bbp_get_public_topic_statuses();
				$topic_slots    = implode( ', ', array_fill( 0, count( $topic_statuses ), '%s' ) );
				$sql            = "SELECT topics.ID, COUNT(replies.ID) AS count FROM {$db->posts} AS topics
					LEFT JOIN {$db->posts} AS replies ON replies.post_parent = topics.ID
						AND replies.post_status IN ({$reply_slots}) AND replies.post_type = %s
					WHERE topics.post_type = %s AND topics.post_status IN ({$topic_slots}) GROUP BY topics.ID ORDER BY topics.ID";
				$args = array_merge( $reply_statuses, array( bbp_get_reply_post_type(), bbp_get_topic_post_type() ), $topic_statuses );
			}
			$expected = $db->get_results( $db->prepare( $sql, $args ), ARRAY_A );
			$this->finish( $callback );
			$stored = $db->get_results( $db->prepare( "SELECT posts.ID, meta.meta_value AS count FROM {$db->posts} AS posts INNER JOIN {$db->postmeta} AS meta ON meta.post_id = posts.ID WHERE posts.post_type = %s AND meta.meta_key = %s ORDER BY posts.ID, meta.meta_id", bbp_get_topic_post_type(), $key ), ARRAY_A );
			$this->assertSame( $expected, $stored );
			$this->assertSame( $hidden ? array() : array( '0' ), get_post_meta( $empty_topic, $key, false ) );
		} finally {
			remove_filter( $status_filter, $statuses );
		}
		$this->assertNotFalse( get_metadata_by_mid( 'post', $other_id ) );
		$this->assertNotFalse( get_metadata_by_mid( 'post', $orphan_id ) );

		// Empty filtered status lists keep the old IN ('') behavior
		add_filter( $status_filter, '__return_empty_array' );
		if ( ! $hidden ) {
			add_filter( 'bbp_get_public_topic_statuses', '__return_empty_array' );
		}
		try {
			$this->finish( $callback );
			foreach ( array_merge( $topics, array( $empty_topic ) ) as $topic_id ) {
				$this->assertSame( array(), get_post_meta( $topic_id, $key, false ) );
			}
		} finally {
			remove_filter( $status_filter, '__return_empty_array' );
			remove_filter( 'bbp_get_public_topic_statuses', '__return_empty_array' );
		}
	}

	/**
	 * @dataProvider tools
	 */
	public function test_recounts_use_the_current_blog_tables_and_checkpoints( $hidden, $tool_id, $key, $callback ) {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'Requires multisite.' );
		}
		$topics = $this->topics( $hidden, $key, 3 );
		$this->assertFalse( call_user_func( $callback )['complete'] );
		$state   = get_option( bbp_admin_repair_tool_batch_option_name( $tool_id ) );
		$blog_id = self::factory()->blog->create();
		switch_to_blog( $blog_id );
		try {
			$other_topics = $this->topics( $hidden, $key, 3 );
			$this->assertTrue( bbp_admin_repair_tool_run_automatically( $tool_id, $callback ) );
			$this->assert_counts( $other_topics, $key );
			$this->assertFalse( get_option( bbp_admin_repair_tool_batch_option_name( $tool_id ), false ) );
		} finally {
			restore_current_blog();
		}
		$this->assertSame( $state, get_option( bbp_admin_repair_tool_batch_option_name( $tool_id ) ) );
		$this->assertSame( array(), get_post_meta( $topics[2], $key, false ) );
		$this->finish( $callback );
		$this->assert_counts( $topics, $key );
	}

	public function boundary_sizes() {
		$cases = array();
		foreach ( $this->tools() as $name => $tool ) {
			foreach ( array( 1, 1000 ) as $size ) {
				$cases[ $name . ': ' . $size ] = array_merge( $tool, array( $size ) );
			}
		}
		return $cases;
	}

	/**
	 * @dataProvider boundary_sizes
	 */
	public function test_duplicate_cleanup_remains_on_the_same_topic_at_boundary_sizes( $hidden, $tool_id, $key, $callback, $size ) {
		$topic_id = $this->topics( $hidden, $key, 1 )[0];
		for ( $i = 0; $i <= $size; ++$i ) {
			add_post_meta( $topic_id, $key, 99 );
		}
		$limit = static function () use ( $size ) {
			return $size;
		};
		add_filter( 'bbp_admin_repair_tool_batch_size', $limit, 20 );
		try {
			for ( $batch = 0; $batch < 5; ++$batch ) {
				$before  = $this->snapshot();
				$preview = bbp_admin_repair_topic_reply_count_batch( $hidden, true );
				$this->assertSame( $before, $this->snapshot() );
				$before_ids = $this->count_ids( $topic_id, $key );
				$result = $this->assertMetadataWritesBounded( 'post', $size, static function () use ( $hidden ) {
					return bbp_admin_repair_topic_reply_count_batch( $hidden );
				} );
				$after_ids  = $this->count_ids( $topic_id, $key );
				$this->assertSame( 0, $result['code'] );
				$this->assertSame( $preview, $result );
				$this->assertLessThanOrEqual( $size, count( array_diff( $before_ids, $after_ids ) ) + count( array_diff( $after_ids, $before_ids ) ) );
				if ( 0 === $batch ) {
					$this->assertFalse( $result['complete'] );
					$this->assertCount( 2, $after_ids );
					$this->assertSame( $topic_id, get_option( bbp_admin_repair_tool_batch_option_name( $tool_id ) )['cursor'] );
				}
				if ( $result['complete'] ) {
					break;
				}
			}
			$this->assertTrue( $result['complete'] );
			$this->assert_counts( array( $topic_id ), $key );
		} finally {
			remove_filter( 'bbp_admin_repair_tool_batch_size', $limit, 20 );
		}
	}

	/**
	 * @dataProvider tools
	 */
	public function test_add_failures_preserve_progress_and_automatic_retries( $hidden, $tool_id, $key, $callback ) {
		$topics = $this->topics( $hidden, $key, 3 );
		$fail   = static function ( $check, $post_id, $meta_key ) use ( $topics, $key ) {
			return ( $topics[1] === $post_id ) && ( $key === $meta_key ) ? false : $check;
		};
		add_filter( 'add_post_metadata', $fail, 10, 3 );
		try {
			$result = call_user_func( $callback );
			$this->assertSame( 4, $result[0] );
			$this->assertFalse( $result['success'] );
			$this->assertFalse( $result['complete'] );
			$this->assertSame( 0, get_option( bbp_admin_repair_tool_batch_option_name( $tool_id ) )['cursor'] );
			$this->assertSame( array( '1' ), get_post_meta( $topics[0], $key, false ) );
			$this->assertFalse( bbp_admin_repair_tool_run_automatically( $tool_id, $callback ) );
			$this->assertContains( $tool_id, bbp_get_pending_upgrades() );
		} finally {
			remove_filter( 'add_post_metadata', $fail, 10 );
		}
		$this->assertTrue( bbp_admin_repair_tool_run_automatically( $tool_id, $callback ) );
		$this->assertNotContains( $tool_id, bbp_get_pending_upgrades() );
		$this->assert_counts( $topics, $key );
	}

	/**
	 * @dataProvider tools
	 */
	public function test_partial_delete_failures_keep_a_canonical_row_and_replay_safely( $hidden, $tool_id, $key, $callback ) {
		$topic_id = $this->topics( $hidden, $key, 1 )[0];
		$keep_id  = add_post_meta( $topic_id, $key, 1 );
		$first    = add_post_meta( $topic_id, $key, 99 );
		$second   = add_post_meta( $topic_id, $key, 98 );
		$fail     = static function ( $check, $meta_id ) use ( $first ) {
			return ( (int) $meta_id === $first ) ? false : $check;
		};
		add_filter( 'delete_post_metadata_by_mid', $fail, 10, 2 );
		try {
			$result = call_user_func( $callback );
			$this->assertSame( 3, $result[0] );
			$this->assertFalse( $result['success'] );
			$this->assertFalse( $result['complete'] );
			$this->assertFalse( get_metadata_by_mid( 'post', $second ) );
			$this->assertNotFalse( get_metadata_by_mid( 'post', $keep_id ) );
			$this->assertSame( 0, get_option( bbp_admin_repair_tool_batch_option_name( $tool_id ) )['cursor'] );
		} finally {
			remove_filter( 'delete_post_metadata_by_mid', $fail, 10 );
		}
		$this->finish( $callback );
		$this->assert_counts( array( $topic_id ), $key );
		$this->assertNotFalse( get_metadata_by_mid( 'post', $keep_id ) );
	}

	/**
	 * @dataProvider tools
	 */
	public function test_checkpoint_write_failures_allow_safe_replays( $hidden, $tool_id, $key, $callback ) {
		$topics = $this->topics( $hidden, $key );
		$option = bbp_admin_repair_tool_batch_option_name( $tool_id );
		$fail   = static function ( $value, $old ) {
			return $old;
		};
		$before = $this->snapshot();
		add_filter( 'pre_update_option_' . $option, $fail, 10, 2 );
		try {
			$result = call_user_func( $callback );
			$this->assertSame( 6, $result[0] );
			$this->assertFalse( $result['success'] );
			$this->assertSame( $before, $this->snapshot() );
		} finally {
			remove_filter( 'pre_update_option_' . $option, $fail, 10 );
		}
		$this->assertFalse( call_user_func( $callback )['complete'] );
		$state = get_option( $option );
		add_filter( 'pre_update_option_' . $option, $fail, 10, 2 );
		try {
			$result = call_user_func( $callback );
			$this->assertSame( 6, $result[0] );
			$this->assertFalse( $result['success'] );
			$this->assertSame( $state, get_option( $option ) );
		} finally {
			remove_filter( 'pre_update_option_' . $option, $fail, 10 );
		}
		$this->finish( $callback );
		$this->assert_counts( $topics, $key );
	}

	/**
	 * @dataProvider tools
	 */
	public function test_final_checkpoint_delete_failures_leave_automatic_runs_pending( $hidden, $tool_id, $key, $callback ) {
		$db     = bbp_db();
		$topics = $this->topics( $hidden, $key, 3 );
		$option = bbp_admin_repair_tool_batch_option_name( $tool_id );
		$this->assertFalse( call_user_func( $callback )['complete'] );
		$state = get_option( $option );
		$fail  = static function ( $query ) use ( $db, $option ) {
			return ( 0 === strpos( $query, 'DELETE FROM' ) ) && ( false !== strpos( $query, $db->options ) ) && ( false !== strpos( $query, $option ) )
				? 'DELETE FROM bbp_missing_topic_count_options'
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
		$this->assert_counts( $topics, $key );
	}

	/**
	 * @dataProvider tools
	 */
	public function test_automatic_batch_budgets_preserve_resume_state( $hidden, $tool_id, $key, $callback ) {
		$topics = $this->topics( $hidden, $key );
		add_filter( 'bbp_admin_repair_tool_automatic_batch_limit', '__return_true' );
		try {
			$this->assertFalse( bbp_admin_repair_tool_run_automatically( $tool_id, $callback ) );
			$this->assertContains( $tool_id, bbp_get_pending_upgrades() );
			$this->assertSame( array(), get_post_meta( $topics[2], $key, false ) );
		} finally {
			remove_filter( 'bbp_admin_repair_tool_automatic_batch_limit', '__return_true' );
		}
		$this->assertTrue( bbp_admin_repair_tool_run_automatically( $tool_id, $callback ) );
		$this->assertNotContains( $tool_id, bbp_get_pending_upgrades() );
		$this->assertFalse( get_option( bbp_admin_repair_tool_batch_option_name( $tool_id ), false ) );
		$this->assert_counts( $topics, $key );
	}

	/**
	 * @dataProvider tools
	 */
	public function test_clean_incomplete_previews_do_not_hide_later_work( $hidden, $tool_id, $key, $callback ) {
		$topics = $this->topics( $hidden, $key, 3 );
		add_post_meta( $topics[0], $key, 1 );
		add_post_meta( $topics[1], $key, 1 );
		$before  = $this->snapshot();
		$preview = call_user_func( $callback . '_dry_run' );
		$this->assertSame( 0, $preview[0] );
		$this->assertFalse( $preview['complete'] );
		$this->assertStringContainsString( 'This batch:', $preview[1] );
		$this->assertStringContainsString( '0 topics.', $preview[1] );
		$this->assertSame( $before, $this->snapshot() );
		$this->assertFalse( call_user_func( $callback )['complete'] );
		$this->assertStringContainsString( '1 topic.', call_user_func( $callback . '_dry_run' )[1] );
		$this->finish( $callback );
		$this->assert_counts( $topics, $key );
	}

	/**
	 * @dataProvider tools
	 */
	public function test_new_topics_wait_for_the_next_run( $hidden, $tool_id, $key, $callback ) {
		$topics = $this->topics( $hidden, $key, 3 );
		$this->assertFalse( call_user_func( $callback )['complete'] );
		$new_topic = $this->topics( $hidden, $key, 1 )[0];
		$this->finish( $callback );
		$this->assertSame( array(), get_post_meta( $new_topic, $key, false ) );
		$this->finish( $callback );
		$this->assert_counts( array_merge( $topics, array( $new_topic ) ), $key );
	}

	public function malformed_checkpoints() {
		$cases = array();
		foreach ( $this->tools() as $name => $tool ) {
			foreach ( array( 'missing cursor' => array( 'cursor', null ), 'string cursor' => array( 'cursor', '1' ), 'negative cursor' => array( 'cursor', -1 ), 'inverted bounds' => array( 'cursor', 'past-bound' ), 'missing upper bound' => array( 'upper_id', null ), 'array upper bound' => array( 'upper_id', array() ) ) as $case => $change ) {
				$cases[ $name . ': ' . $case ] = array_merge( $tool, $change );
			}
		}
		return $cases;
	}

	/**
	 * @dataProvider malformed_checkpoints
	 */
	public function test_malformed_checkpoints_restart_without_preview_writes( $hidden, $tool_id, $key, $callback, $field, $value ) {
		$topics = $this->topics( $hidden, $key );
		$this->assertFalse( call_user_func( $callback )['complete'] );
		$option          = bbp_admin_repair_tool_batch_option_name( $tool_id );
		$state           = get_option( $option );
		$state[ $field ] = 'past-bound' === $value ? $state['upper_id'] + 1 : $value;
		update_option( $option, $state, false );
		$before  = $this->snapshot();
		$preview = call_user_func( $callback . '_dry_run' );
		$this->assertStringContainsString( 'Saved progress is invalid.', $preview[1] );
		$this->assertSame( $before, $this->snapshot() );
		$result = call_user_func( $callback );
		$this->assertStringContainsString( 'Saved progress was invalid.', $result[1] );
		$this->finish( $callback );
		$this->assert_counts( $topics, $key );
	}

	/**
	 * @dataProvider tools
	 */
	public function test_topic_selection_failures_stop_without_metadata_or_progress_changes( $hidden, $tool_id, $key, $callback ) {
		$db     = bbp_db();
		$topics = $this->topics( $hidden, $key, 3 );
		$option = bbp_admin_repair_tool_batch_option_name( $tool_id );
		$cases  = array( "SELECT MAX(ID) FROM {$db->posts}" => false, 'SELECT ID, post_status IN (' => true );
		foreach ( $cases as $prefix => $checkpoint ) {
			if ( $checkpoint ) {
				update_option( $option, array( 'cursor' => 0, 'upper_id' => max( $topics ) ), false );
			}
			$fail = static function ( $query ) use ( $prefix ) {
				return ( 0 === strpos( $query, $prefix ) ) ? 'SELECT ID FROM bbp_missing_topic_selection' : $query;
			};
			$before   = $this->snapshot();
			$suppress = $db->suppress_errors( true );
			add_filter( 'query', $fail );
			try {
				foreach ( array( $callback . '_dry_run', $callback ) as $call ) {
					$result = call_user_func( $call );
					$this->assertSame( 1, $result[0] );
					$this->assertFalse( $result['success'] );
					$this->assertFalse( $result['complete'] );
					$this->assertSame( $before, $this->snapshot() );
				}
			} finally {
				remove_filter( 'query', $fail );
				$db->suppress_errors( $suppress );
			}
		}
		$this->finish( $callback );
		$this->assert_counts( $topics, $key );
	}

	/**
	 * @dataProvider tools
	 */
	public function test_source_and_metadata_read_failures_preserve_data_and_progress( $hidden, $tool_id, $key, $callback ) {
		$db     = bbp_db();
		$topics = $this->topics( $hidden, $key, 3 );
		$this->assertFalse( call_user_func( $callback )['complete'] );
		foreach ( array( "SELECT COUNT(*) FROM {$db->posts}" => 1, "SELECT meta_id FROM {$db->postmeta}" => 2 ) as $prefix => $code ) {
			$fail = static function ( $query ) use ( $prefix ) {
				return ( 0 === strpos( $query, $prefix ) ) ? 'SELECT ID FROM bbp_missing_topic_count_data' : $query;
			};
			$before   = $this->snapshot();
			$suppress = $db->suppress_errors( true );
			add_filter( 'query', $fail );
			try {
				foreach ( array( $callback, $callback . '_dry_run' ) as $call ) {
					$result = call_user_func( $call );
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
		$this->finish( $callback );
		$this->assert_counts( $topics, $key );
	}
}
