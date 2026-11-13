<?php

/**
 * Resumable hierarchy recounts, including interrupted parent/child work.
 *
 * @group tools
 * @group tool-batches
 */
class BBP_Tests_Admin_Forum_Count_Batches extends BBP_UnitTestCase {

	private $size = 1;

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
		return $this->size;
	}

	public function tools() {
		return array(
			array( 'topics', '_bbp_topic_count', '_bbp_total_topic_count', 'bbp_admin_repair_forum_topic_count' ),
			array( 'replies', '_bbp_reply_count', '_bbp_total_reply_count', 'bbp_admin_repair_forum_reply_count' ),
			array( 'hidden-replies', '_bbp_reply_count_hidden', '_bbp_total_reply_count_hidden', 'bbp_admin_repair_forum_hidden_reply_count' )
		);
	}

	private function option( $type ) {
		return bbp_admin_repair_tool_batch_option_name( 'bbp-forum-' . $type );
	}

	private function nodes( $type ) {
		$db = bbp_db();
		return $db->get_col( $db->prepare( "SELECT option_name FROM {$db->options} WHERE option_name LIKE %s ORDER BY option_name", $db->esc_like( $this->option( $type ) . '_tree_' ) . '%' ) );
	}

	private function snapshot() {
		$db = bbp_db();
		return array(
			$db->get_results( "SELECT * FROM {$db->postmeta} ORDER BY meta_id", ARRAY_A ),
			$db->get_results( "SELECT * FROM {$db->options} ORDER BY option_id", ARRAY_A )
		);
	}

	private function forums( $type, $key, $total, $number = 5 ) {
		$forums = array();
		$parent = 0;
		for ( $i = 0; $i < $number; ++$i ) {
			$forum = $this->factory->forum->create( array( 'post_parent' => $parent ) );
			$topic = $this->factory->topic->create( array( 'post_parent' => $forum ) );
			$this->factory->reply->create( array(
				'post_parent' => $topic,
				'post_status' => 'hidden-replies' === $type ? bbp_get_spam_status_id() : bbp_get_public_status_id()
			) );
			delete_post_meta( $forum, $key );
			delete_post_meta( $forum, $total );
			$forums[] = $parent = $forum;
		}
		return $forums;
	}

	private function finish( $type ) {
		for ( $i = 0; $i < 500; ++$i ) {
			$result = bbp_admin_repair_forum_count_batch( $type );
			$this->assertSame( 0, $result['code'] );
			if ( $result['complete'] ) {
				$this->assertFalse( get_option( $this->option( $type ), false ) );
				$this->assertSame( array(), $this->nodes( $type ) );
				return;
			}
		}
		$this->fail( 'Forum recount did not finish.' );
	}

	private function assert_counts( $forums, $key, $total ) {
		foreach ( $forums as $i => $forum ) {
			$this->assertSame( array( '1' ), get_post_meta( $forum, $key, false ) );
			$this->assertSame( array( (string) ( count( $forums ) - $i ) ), get_post_meta( $forum, $total, false ) );
		}
	}

	/** @dataProvider tools */
	public function test_one_item_batches_preview_exact_writes_through_deep_hierarchies( $type, $key, $total, $callback ) {
		$db     = bbp_db();
		$forums = $this->forums( $type, $key, $total, 10 );
		$keep   = add_post_meta( $forums[9], $key, 1 );
		for ( $i = 0; $i < 12; ++$i ) {
			add_post_meta( $forums[9], strtoupper( $key ), 99 );
		}
		for ( $i = 0; $i < 500; ++$i ) {
			$before  = $this->snapshot();
			$preview = bbp_admin_repair_forum_count_batch( $type, true );
			$this->assertSame( $before, $this->snapshot() );
			$before_ids = $db->get_col( "SELECT meta_id FROM {$db->postmeta}" );
			$result     = bbp_admin_repair_forum_count_batch( $type );
			$after_ids  = $db->get_col( "SELECT meta_id FROM {$db->postmeta}" );
			$this->assertSame( 0, $result['code'] );
			$this->assertGreaterThanOrEqual( $result['repaired'], $preview['repaired'] );
			$this->assertTrue( $preview['complete'] );
			$this->assertLessThanOrEqual( 1, count( array_diff( $before_ids, $after_ids ) ) + count( array_diff( $after_ids, $before_ids ) ) );
			$this->assertNotFalse( get_metadata_by_mid( 'post', $keep ) );
			if ( $result['complete'] ) {
				break;
			}
		}
		$this->assertTrue( $result['complete'] );
		$this->assert_counts( $forums, $key, $total );
		$this->assertSame( array(), $this->nodes( $type ) );
		$before = $this->snapshot();
		$this->finish( $type );
		$this->assertSame( $before, $this->snapshot() );
	}

	/** @dataProvider tools */
	public function test_large_batch_dry_run_simulates_existing_scratch_cleanup_without_writes( $type, $key, $total, $callback ) {
		$forums = $this->forums( $type, $key, $total );
		$stale  = $this->option( $type ) . '_tree_stale';
		add_option( $stale, array( 'old' => true ), '', false );
		$this->size = 1000;
		$before     = $this->snapshot();
		$preview    = bbp_admin_repair_forum_count_batch( $type, true );
		$this->assertTrue( $preview['complete'] );
		$this->assertSame( $before, $this->snapshot() );
		$result = bbp_admin_repair_forum_count_batch( $type );
		$this->assertGreaterThanOrEqual( $result['repaired'], $preview['repaired'] );
		$this->assertTrue( $result['complete'] );
		$this->assertFalse( get_option( $stale, false ) );
		$this->assertSame( array(), $this->nodes( $type ) );
		$this->assert_counts( $forums, $key, $total );
	}

	public function test_explicit_empty_hidden_reply_status_is_not_treated_as_all_statuses() {
		$forums = $this->forums( 'hidden-replies', '_bbp_reply_count_hidden', '_bbp_total_reply_count_hidden', 1 );
		$filter = static function () { return array( '' ); };
		add_filter( 'bbp_get_non_public_reply_statuses', $filter );
		try {
			$before  = $this->snapshot();
			$preview = bbp_admin_repair_forum_count_batch( 'hidden-replies', true );
			$this->assertTrue( $preview['complete'] );
			$this->assertGreaterThan( 0, $preview['repaired'] );
			$this->assertSame( $before, $this->snapshot() );
			$this->finish( 'hidden-replies' );
			$this->assertSame( array( '0' ), get_post_meta( $forums[0], '_bbp_reply_count_hidden', false ) );
			$this->assertSame( array( '0' ), get_post_meta( $forums[0], '_bbp_total_reply_count_hidden', false ) );
		} finally {
			remove_filter( 'bbp_get_non_public_reply_statuses', $filter );
		}
	}

	/** @dataProvider tools */
	public function test_failed_parent_acknowledgement_replays_without_double_counting( $type, $key, $total, $callback ) {
		$forums = $this->forums( $type, $key, $total, 3 );
		$prefix = $this->option( $type ) . '_tree_';
		$fail   = static function ( $value, $option, $old ) use ( $prefix ) {
			return 0 === strpos( $option, $prefix ) && is_array( $old ) && ! empty( $old['await'] ) && empty( $value['await'] ) ? $old : $value;
		};
		add_filter( 'pre_update_option', $fail, 10, 3 );
		try {
			for ( $i = 0; $i < 100; ++$i ) {
				$result = bbp_admin_repair_forum_count_batch( $type );
				if ( $result['code'] ) {
					break;
				}
			}
			$this->assertSame( 2, $result['code'] );
			$this->assertSame( 'progress', $result['error'] );
			$this->assertFalse( $result['complete'] );
			$this->assertNotFalse( get_option( $this->option( $type ), false ) );
		} finally {
			remove_filter( 'pre_update_option', $fail, 10 );
		}
		$this->finish( $type );
		$this->assert_counts( $forums, $key, $total );
	}

	/** @dataProvider tools */
	public function test_lost_main_checkpoint_does_not_reapply_acknowledged_children( $type, $key, $total, $callback ) {
		$forums = $this->forums( $type, $key, $total, 3 );
		bbp_admin_repair_forum_count_batch( $type );
		$fail = static function ( $value, $old ) { return $old; };
		add_filter( 'pre_update_option_' . $this->option( $type ), $fail, 10, 2 );
		try {
			$result = bbp_admin_repair_forum_count_batch( $type );
			$this->assertSame( 2, $result['code'] );
			$this->assertSame( 'progress', $result['error'] );
		} finally {
			remove_filter( 'pre_update_option_' . $this->option( $type ), $fail, 10 );
		}
		$this->finish( $type );
		$this->assert_counts( $forums, $key, $total );
	}

	/** @dataProvider tools */
	public function test_corrupt_child_progress_restarts_and_preview_stays_read_only( $type, $key, $total, $callback ) {
		$forums = $this->forums( $type, $key, $total, 3 );
		for ( $i = 0; $i < 20 && count( $this->nodes( $type ) ) < 2; ++$i ) {
			bbp_admin_repair_forum_count_batch( $type );
		}
		$state         = get_option( $this->option( $type ) );
		$name          = $this->option( $type ) . '_tree_' . $state['token'] . '_' . $state['current'];
		$node          = get_option( $name );
		$node['depth'] = 'invalid';
		update_option( $name, $node, false );
		$before  = $this->snapshot();
		$preview = bbp_admin_repair_forum_count_batch( $type, true );
		$this->assertFalse( $preview['restarted'] );
		$this->assertSame( $before, $this->snapshot() );
		$this->finish( $type );
		$this->assert_counts( $forums, $key, $total );
	}

	/** @dataProvider tools */
	public function test_corrupt_counted_values_cannot_turn_a_recount_into_cleanup( $type, $key, $total, $callback ) {
		$forums = $this->forums( $type, $key, $total, 2 );
		bbp_admin_repair_forum_count_batch( $type );
		bbp_admin_repair_forum_count_batch( $type );
		$state = get_option( $this->option( $type ) );
		$name = $this->option( $type ) . '_tree_' . $state['token'] . '_' . $state['current'];
		$node = get_option( $name );
		$node['values'][ $key ] = null;
		update_option( $name, $node, false );
		$before = $this->snapshot();
		$preview = bbp_admin_repair_forum_count_batch( $type, true );
		$this->assertFalse( $preview['restarted'] );
		$this->assertSame( $before, $this->snapshot() );
		$this->finish( $type );
		$this->assert_counts( $forums, $key, $total );
	}

	/** @dataProvider tools */
	public function test_failed_scratch_cleanup_keeps_automatic_upgrade_pending_until_retry( $type, $key, $total, $callback ) {
		$db         = bbp_db();
		$forums     = $this->forums( $type, $key, $total, 3 );
		$this->size = 1000;
		$prefix     = $this->option( $type ) . '_tree_';
		$fail       = static function ( $query ) use ( $db, $prefix ) {
			return 0 === strpos( $query, 'DELETE FROM' ) && false !== strpos( $query, $db->options ) && false !== strpos( $query, $prefix ) ? 'DELETE FROM bbp_missing_forum_options' : $query;
		};
		$suppress = $db->suppress_errors( true );
		add_filter( 'query', $fail );
		try {
			$this->assertFalse( bbp_admin_repair_tool_run_automatically( 'bbp-forum-' . $type, $callback ) );
			$this->assertContains( 'bbp-forum-' . $type, bbp_get_pending_upgrades() );
			$this->assertSame( 'cleanup', get_option( $this->option( $type ) )['phase'] );
		} finally {
			remove_filter( 'query', $fail );
			$db->suppress_errors( $suppress );
		}
		$this->assertTrue( bbp_admin_repair_tool_run_automatically( 'bbp-forum-' . $type, $callback ) );
		$this->assertNotContains( 'bbp-forum-' . $type, bbp_get_pending_upgrades() );
		$this->assertSame( array(), $this->nodes( $type ) );
		$this->assert_counts( $forums, $key, $total );
	}

	/** @dataProvider tools */
	public function test_small_site_automatic_runs_resume_after_their_request_budget( $type, $key, $total, $callback ) {
		$forums = $this->forums( $type, $key, $total, 3 );
		add_filter( 'bbp_admin_repair_tool_automatic_batch_limit', '__return_true' );
		try {
			$this->assertFalse( bbp_admin_repair_tool_run_automatically( 'bbp-forum-' . $type, $callback ) );
			$this->assertContains( 'bbp-forum-' . $type, bbp_get_pending_upgrades() );
		} finally {
			remove_filter( 'bbp_admin_repair_tool_automatic_batch_limit', '__return_true' );
		}
		$this->assertTrue( bbp_admin_repair_tool_run_automatically( 'bbp-forum-' . $type, $callback ) );
		$this->assertNotContains( 'bbp-forum-' . $type, bbp_get_pending_upgrades() );
		$this->assertSame( array(), $this->nodes( $type ) );
		$this->assert_counts( $forums, $key, $total );
	}

	/** @dataProvider tools */
	public function test_failed_upper_bound_read_does_not_save_an_empty_scan( $type, $key, $total, $callback ) {
		$db     = bbp_db();
		$forums = $this->forums( $type, $key, $total, 2 );
		$fail   = static function ( $query ) use ( $db ) {
			return false !== strpos( $query, "SELECT MAX(ID) FROM {$db->posts}" ) ? 'SELECT * FROM bbp_missing_forum_source' : $query;
		};
		$before   = $this->snapshot();
		$suppress = $db->suppress_errors( true );
		add_filter( 'query', $fail );
		try {
			bbp_admin_repair_tool_execution_context( 'dry-run', 'failed-forum-count-preview' );
			try {
				$preview = bbp_admin_repair_forum_count_batch( $type, true );
			} finally {
				bbp_admin_repair_tool_execution_context( 'run', '' );
			}
			$this->assertSame( 2, $preview['code'] );
			$this->assertSame( 'source-read', $preview['error'] );
			$this->assertFalse( get_option( $this->option( $type ), false ) );
			$this->assertSame( array(), $this->nodes( $type ) );

			$result = bbp_admin_repair_forum_count_batch( $type );
			$this->assertSame( 2, $result['code'] );
			$this->assertSame( 'source-read', $result['error'] );
			$this->assertSame( $before, $this->snapshot() );
		} finally {
			remove_filter( 'query', $fail );
			$db->suppress_errors( $suppress );
		}
		$this->finish( $type );
		$this->assert_counts( $forums, $key, $total );
	}

	public function test_core_small_site_updater_finishes_the_hidden_reply_hierarchy() {
		$forums = $this->forums( 'hidden-replies', '_bbp_reply_count_hidden', '_bbp_total_reply_count_hidden', 3 );
		$small  = static function () { return false; };
		update_option( '_bbp_db_version', 262 );
		add_filter( 'bbp_is_large_install', $small );
		try {
			bbp_version_updater();
			$this->assert_counts( $forums, '_bbp_reply_count_hidden', '_bbp_total_reply_count_hidden' );
			$this->assertNotContains( 'bbp-forum-hidden-replies', bbp_get_pending_upgrades() );
			$this->assertFalse( get_option( $this->option( 'hidden-replies' ), false ) );
			$this->assertSame( array(), $this->nodes( 'hidden-replies' ) );
		} finally {
			remove_filter( 'bbp_is_large_install', $small );
		}
	}

	/** @dataProvider tools */
	public function test_failed_final_checkpoint_deletion_resumes_after_nodes_are_gone( $type, $key, $total, $callback ) {
		$db         = bbp_db();
		$forums     = $this->forums( $type, $key, $total, 2 );
		$this->size = 1000;
		$option     = $this->option( $type );
		$fail       = static function ( $query ) use ( $db, $option ) {
			return 0 === strpos( $query, 'DELETE FROM' ) && false !== strpos( $query, $db->options ) && false !== strpos( $query, "'{$option}'" ) ? 'DELETE FROM bbp_missing_forum_options' : $query;
		};
		$suppress = $db->suppress_errors( true );
		add_filter( 'query', $fail );
		try {
			$result = bbp_admin_repair_forum_count_batch( $type );
			$this->assertSame( 2, $result['code'] );
			$this->assertSame( 'progress', $result['error'] );
			$this->assertFalse( $result['complete'] );
			$this->assertSame( array(), $this->nodes( $type ) );
			$this->assertSame( 'cleanup', get_option( $option )['phase'] );
		} finally {
			remove_filter( 'query', $fail );
			$db->suppress_errors( $suppress );
		}
		$this->finish( $type );
		$this->assert_counts( $forums, $key, $total );
	}

	/** @dataProvider tools */
	public function test_higher_forum_ids_wait_for_the_next_recount( $type, $key, $total, $callback ) {
		$forums = $this->forums( $type, $key, $total, 2 );
		bbp_admin_repair_forum_count_batch( $type );
		$added = $this->forums( $type, $key, $total, 1 );
		$this->finish( $type );
		$this->assertSame( array(), get_post_meta( $added[0], $key, false ) );
		$this->assert_counts( $forums, $key, $total );
		$this->finish( $type );
		$this->assertSame( array( '1' ), get_post_meta( $added[0], $key, false ) );
	}

	/** @dataProvider tools */
	public function test_progress_and_scratch_nodes_use_the_current_blog( $type, $key, $total, $callback ) {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'Requires multisite.' );
		}
		$forums = $this->forums( $type, $key, $total, 2 );
		bbp_admin_repair_forum_count_batch( $type );
		bbp_admin_repair_forum_count_batch( $type );
		$state = get_option( $this->option( $type ) );
		$nodes = $this->nodes( $type );
		$blog  = $this->factory->blog->create();
		switch_to_blog( $blog );
		try {
			$this->assertFalse( get_option( $this->option( $type ), false ) );
			$this->assertSame( array(), $this->nodes( $type ) );
			$other = $this->forums( $type, $key, $total, 2 );
			$this->finish( $type );
			$this->assert_counts( $other, $key, $total );
		} finally {
			restore_current_blog();
		}
		$this->assertSame( $state, get_option( $this->option( $type ) ) );
		$this->assertSame( $nodes, $this->nodes( $type ) );
		$this->finish( $type );
		$this->assert_counts( $forums, $key, $total );
	}

	/** @dataProvider tools */
	public function test_corrupt_restart_is_saved_before_removing_the_previous_tree( $type, $key, $total, $callback ) {
		$forums = array();
		for ( $i = 0; $i < 3; ++$i ) {
			$forums = array_merge( $forums, $this->forums( $type, $key, $total, 1 ) );
		}
		for ( $i = 0; $i < 50; ++$i ) {
			bbp_admin_repair_forum_count_batch( $type );
			$state = get_option( $this->option( $type ) );
			if ( 'count' === $state['phase'] && ! $state['current'] && $forums[0] === $state['cursor'] ) {
				break;
			}
		}
		$this->assertSame( $forums[0], $state['cursor'] );
		bbp_admin_repair_forum_count_batch( $type );
		$prefix = $this->option( $type ) . '_tree_' . $state['token'] . '_';
		$name   = $prefix . $forums[1];
		$node   = get_option( $name );
		$node['depth'] = 'invalid';
		update_option( $name, $node, false );
		update_option( $this->option( $type ), $state, false );
		$fail = static function ( $value, $old ) { return $old; };
		$this->size = 1000;
		add_filter( 'pre_update_option_' . $this->option( $type ), $fail, 10, 2 );
		try {
			$result = bbp_admin_repair_forum_count_batch( $type );
			$this->assertSame( 2, $result['code'] );
			$this->assertSame( 'progress', $result['error'] );
			$this->assertSame( $state, get_option( $this->option( $type ) ) );
			$this->assertNotFalse( get_option( $prefix . $forums[0], false ) );
		} finally {
			remove_filter( 'pre_update_option_' . $this->option( $type ), $fail, 10 );
		}
		$this->finish( $type );
		foreach ( $forums as $forum ) {
			$this->assertSame( array( '1' ), get_post_meta( $forum, $key, false ) );
			$this->assertSame( array( '1' ), get_post_meta( $forum, $total, false ) );
		}
	}

	/** @dataProvider tools */
	public function test_preview_discards_nodes_from_a_lost_prepare_checkpoint( $type, $key, $total, $callback ) {
		$forums = $this->forums( $type, $key, $total, 1 );
		$this->size = 1000;
		$fail = static function ( $value, $old ) {
			return is_array( $old ) && 'prepare' === $old['phase'] ? $old : $value;
		};
		add_filter( 'pre_update_option_' . $this->option( $type ), $fail, 10, 2 );
		try {
			$this->assertSame( 2, bbp_admin_repair_forum_count_batch( $type )['code'] );
			$this->assertSame( 'prepare', get_option( $this->option( $type ) )['phase'] );
			$this->assertNotEmpty( $this->nodes( $type ) );
		} finally {
			remove_filter( 'pre_update_option_' . $this->option( $type ), $fail, 10 );
		}
		update_post_meta( $forums[0], $key, 99 );
		$before  = $this->snapshot();
		$preview = bbp_admin_repair_forum_count_batch( $type, true );
		$this->assertSame( $before, $this->snapshot() );
		$result = bbp_admin_repair_forum_count_batch( $type );
		$this->assertSame( 1, $result['repaired'] );
		$this->assertSame( $result['repaired'], $preview['repaired'] );
		$this->assertSame( $result['complete'], $preview['complete'] );
		$this->assert_counts( $forums, $key, $total );
	}

	/** @dataProvider tools */
	public function test_prune_discards_an_unacknowledged_child_that_is_no_longer_countable( $type, $key, $total, $callback ) {
		$forums = $this->forums( $type, $key, $total, 2 );
		bbp_admin_repair_forum_count_batch( $type );
		bbp_admin_repair_forum_count_batch( $type );
		$state = get_option( $this->option( $type ) );
		$name  = $this->option( $type ) . '_tree_' . $state['token'] . '_' . $forums[0];
		$fail  = static function ( $value, $old ) { return ! empty( $value['await'] ) ? $old : $value; };
		add_filter( 'pre_update_option_' . $name, $fail, 10, 2 );
		try {
			$this->assertSame( 2, bbp_admin_repair_forum_count_batch( $type )['code'] );
		} finally {
			remove_filter( 'pre_update_option_' . $name, $fail, 10 );
		}
		$db = bbp_db();
		$db->update( $db->posts, array( 'post_status' => 'draft' ), array( 'ID' => $forums[1] ) );
		$this->finish( $type );
		$this->assertSame( array(), get_post_meta( $forums[1], $key, false ) );
		$this->assertSame( array(), get_post_meta( $forums[1], $total, false ) );
		$this->assertSame( array( '1' ), get_post_meta( $forums[0], $key, false ) );
		$this->assertSame( array( '1' ), get_post_meta( $forums[0], $total, false ) );
	}

	/** @dataProvider tools */
	public function test_finished_runs_remove_temporary_names_from_the_negative_option_cache( $type, $key, $total, $callback ) {
		$this->forums( $type, $key, $total );
		$this->size = 1000;
		get_option( 'bbp_test_unrelated_missing_option', false );
		for ( $i = 0; $i < 3; ++$i ) {
			$this->finish( $type );
		}
		$names  = array_keys( (array) wp_cache_get( 'notoptions', 'options' ) );
		$prefix = $this->option( $type ) . '_tree_';
		$cached = array_filter( $names, static function ( $name ) use ( $prefix ) { return 0 === strpos( $name, $prefix ); } );
		$this->assertSame( array(), array_values( $cached ) );
		$this->assertContains( 'bbp_test_unrelated_missing_option', $names );
	}

	/** @dataProvider tools */
	public function test_filtered_duplicate_children_and_parent_ids_out_of_order( $type, $key, $total, $callback ) {
		$forums = $this->forums( $type, $key, $total, 2 );
		$db     = bbp_db();
		$db->update( $db->posts, array( 'post_parent' => $forums[1] ), array( 'ID' => $forums[0] ) );
		$db->update( $db->posts, array( 'post_parent' => 0 ), array( 'ID' => $forums[1] ) );
		$filter = static function ( $children, $id ) use ( $forums ) {
			return $forums[1] === $id ? array( 8 => $forums[0], 2 => $forums[0] ) : array();
		};
		add_filter( 'bbp_forum_query_subforum_ids', $filter, 10, 2 );
		try {
			$this->finish( $type );
			$this->assertSame( '1', get_post_meta( $forums[0], $total, true ) );
			$this->assertSame( '3', get_post_meta( $forums[1], $total, true ) );
		} finally {
			remove_filter( 'bbp_forum_query_subforum_ids', $filter, 10 );
		}
	}
	public function changed_child_sources() {
		$cases = array();
		foreach ( $this->tools() as $tool ) {
			foreach ( array( 'move', 'delete', 'status', 'direct' ) as $change ) {
				$cases[] = array_merge( $tool, array( $change ) );
			}
		}
		return $cases;
	}

	/** @dataProvider changed_child_sources */
	public function test_completed_child_is_rechecked_before_merging_or_writing( $type, $key, $total, $callback, $change ) {
		$forums      = $this->forums( $type, $key, $total, 2 );
		$destination = $this->forums( $type, $key, $total, 1 )[0];
		$child       = $forums[1];
		for ( $i = 0; $i < 150; ++$i ) {
			$result = bbp_admin_repair_forum_count_batch( $type );
			$this->assertSame( 0, $result['code'] );
			$state = get_option( $this->option( $type ) );
			if ( is_array( $state ) ) {
				$node = get_option( $this->option( $type ) . '_tree_' . $state['token'] . '_' . $child );
				if ( is_array( $node ) && 'done' === $node['phase'] ) {
					break;
				}
			}
		}
		$this->assertSame( 'done', $node['phase'] );
		if ( 'move' === $change ) {
			wp_update_post( array( 'ID' => $child, 'post_parent' => $destination ) );
		} elseif ( 'delete' === $change ) {
			wp_delete_post( $child, true );
		} elseif ( 'status' === $change ) {
			wp_update_post( array( 'ID' => $child, 'post_status' => 'draft' ) );
		} else {
			$topic = $this->factory->topic->create( array( 'post_parent' => $child ) );
			$this->factory->reply->create( array( 'post_parent' => $topic, 'post_status' => 'hidden-replies' === $type ? bbp_get_spam_status_id() : bbp_get_public_status_id() ) );
		}
		$restarted = false;
		for ( $i = 0; $i < 500; ++$i ) {
			$result = bbp_admin_repair_forum_count_batch( $type );
			$this->assertSame( 0, $result['code'] );
			$restarted = $restarted || $result['restarted'];
			if ( $result['complete'] ) {
				break;
			}
		}
		$this->assertSame( 'direct' !== $change, $restarted );
		$this->assertTrue( $result['complete'] );
		$this->assertSame( 'direct' === $change ? 3 : 1, (int) get_post_meta( $forums[0], $total, true ) );
		$this->assertSame( 'move' === $change ? 2 : 1, (int) get_post_meta( $destination, $total, true ) );
	}

	public function test_scratch_read_failure_preserves_the_generation_for_retry() {
		$this->forums( 'topics', '_bbp_topic_count', '_bbp_total_topic_count', 2 );
		for ( $i = 0; $i < 50; ++$i ) {
			$this->assertSame( 0, bbp_admin_repair_forum_count_batch( 'topics' )['code'] );
			$state = get_option( $this->option( 'topics' ) );
			if ( $state['current'] ) {
				break;
			}
		}
		$this->assertGreaterThan( 0, $state['current'] );
		$name   = $this->option( 'topics' ) . '_tree_' . $state['token'] . '_' . $state['current'];
		$reject = static function ( $sql ) use ( $name ) {
			return false !== strpos( $sql, 'SELECT `option_value`' ) && false !== strpos( $sql, $name ) ? 'SELECT option_value FROM bbp_missing_scratch_options' : $sql;
		};
		$suppressed = bbp_db()->suppress_errors( true );
		add_filter( 'query', $reject );
		try {
			$result = bbp_admin_repair_forum_count_batch( 'topics' );
			$this->assertSame( 2, $result['code'] );
			$this->assertSame( 'progress', $result['error'] );
			$this->assertFalse( $result['restarted'] );
		} finally {
			remove_filter( 'query', $reject );
			bbp_db()->suppress_errors( $suppressed );
		}
		$this->assertSame( $state['token'], get_option( $this->option( 'topics' ) )['token'] );
		$this->finish( 'topics' );
	}

	public function test_synchronous_preview_stops_after_repeated_corrupt_restarts() {
		$worker = new class( 'topics', true ) extends BBP_Admin_Forum_Counts {
			protected function step( $limit, &$complete ) {
				$this->corrupt = true;
				return 1;
			}
		};
		$result = $worker->run();

		$this->assertSame( BBP_Admin_Batch_Worker::RESULT_FAILED, $result['code'] );
		$this->assertSame( 'source-invalid', $result['error'] );
		$this->assertFalse( $result['complete'] );
		$this->assertTrue( $result['restarted'] );
	}

}
