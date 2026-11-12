<?php

/**
 * Shared topic and parent-forum reference batching.
 *
 * @group tools
 * @group tool-batches
 */
class BBP_Tests_Admin_Post_Reference_Batches extends BBP_UnitTestCase {

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

	public function references() {
		return array( 'topic' => array( 'topic' ), 'forum' => array( 'forum' ) );
	}

	public function boundary_sizes() {
		return array( array( 'topic', 1 ), array( 'topic', 1000 ), array( 'forum', 1 ), array( 'forum', 1000 ) );
	}

	private function key( $reference ) {
		return '_bbp_' . $reference . '_id';
	}

	private function option( $reference ) {
		return bbp_admin_repair_tool_batch_option_name( 'bbp-sync-' . $reference . '-meta' );
	}

	private function objects( $reference, $number = 3 ) {
		$ids = $this->factory->{$reference}->create_many( $number );
		foreach ( $ids as $id ) {
			update_post_meta( $id, $this->key( $reference ), 'invalid' );
		}
		return $ids;
	}

	private function value( $reference, $id ) {
		return ( 'forum' === $reference ) ? '0' : (string) $id;
	}

	private function assert_values( $reference, $ids ) {
		foreach ( $ids as $id ) {
			$this->assertSame( array( $this->value( $reference, $id ) ), get_post_meta( $id, $this->key( $reference ), false ) );
		}
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

	private function batch( $reference, $dry_run = false ) {
		return call_user_func( 'bbp_admin_repair_' . $reference . '_meta_batch', $dry_run );
	}

	private function finish( $reference ) {
		$this->assertTrue( bbp_admin_repair_tool_run_automatically( 'bbp-sync-' . $reference . '-meta', 'bbp_admin_repair_' . $reference . '_meta' ) );
		$this->assertFalse( get_option( $this->option( $reference ), false ) );
	}

	/**
	 * @dataProvider references
	 */
	public function test_browser_dry_run_uses_bounded_requests_and_aggregate_totals( $reference ) {
		$ids      = $this->objects( $reference );
		$before   = $this->snapshot();
		$run_id   = wp_generate_uuid4();
		$requests = 0;
		$result   = array();
		$option   = '';

		bbp_admin_repair_tool_execution_context( 'dry-run', $run_id );
		try {
			$option = $this->option( $reference );
			for ( $requests = 1; $requests < 30; ++$requests ) {
				$result = $this->batch( $reference, true );
				$this->assertSame( array_slice( $before, 0, 2 ), array_slice( $this->snapshot(), 0, 2 ) );
				$this->assertSame( $requests, $result['batches'] );
				if ( $result['complete'] ) {
					break;
				}
			}
		} finally {
			bbp_admin_repair_tool_execution_context( 'run', '' );
		}

		$field = ( 'forum' === $reference ) ? 'forums' : 'topics';
		$this->assertGreaterThan( 1, $requests );
		$this->assertTrue( $result['complete'] );
		$this->assertSame( count( $ids ), $result[ $field ] );
		$this->assertFalse( get_option( $option, false ) );
		foreach ( $ids as $id ) {
			$this->assertSame( array( 'invalid' ), get_post_meta( $id, $this->key( $reference ), false ) );
		}
	}

	/**
	 * @dataProvider boundary_sizes
	 */
	public function test_variant_only_and_duplicate_rows_have_bounded_preview_and_run_parity( $reference, $size ) {
		$id    = $this->objects( $reference, 1 )[0];
		$key   = $this->key( $reference );
		$value = $this->value( $reference, $id );
		delete_post_meta( $id, $key );
		add_post_meta( $id, strtoupper( $key ), $value );
		for ( $i = 0; $i < $size; ++$i ) {
			add_post_meta( $id, $key, '0' . $value );
		}
		$limit = static function () use ( $size ) {
			return $size;
		};
		add_filter( 'bbp_admin_repair_tool_batch_size', $limit, 20 );
		try {
			for ( $batch = 0; $batch < 6; ++$batch ) {
				$before  = $this->snapshot();
				$preview = $this->batch( $reference, true );
				$this->assertSame( $before, $this->snapshot() );
				$before_ids = $this->meta_ids();
				$result     = $this->batch( $reference );
				$after_ids  = $this->meta_ids();
				$this->assertSame( 0, $result['code'] );
				$this->assertTrue( $preview['complete'] );
				$this->assertSame( 0, $preview['code'] );
				$this->assertLessThanOrEqual( $size, count( array_diff( $before_ids, $after_ids ) ) + count( array_diff( $after_ids, $before_ids ) ) );
				if ( 0 === $batch ) {
					$this->assertFalse( $result['complete'] );
					$this->assertSame( $id, get_option( $this->option( $reference ) )['cursor'] );
				}
				if ( $result['complete'] ) {
					break;
				}
			}
			$this->assertTrue( $result['complete'] );
			$this->assert_values( $reference, array( $id ) );
		} finally {
			remove_filter( 'bbp_admin_repair_tool_batch_size', $limit, 20 );
		}
	}

	/**
	 * @dataProvider references
	 */
	public function test_partial_add_failure_preserves_old_rows_and_automatic_retries( $reference ) {
		$ids = $this->objects( $reference );
		$key = $this->key( $reference );
		delete_post_meta( $ids[0], $key );
		$fail = static function ( $check, $id, $meta_key ) use ( $ids, $key ) {
			return ( $id === $ids[1] ) && ( $key === $meta_key ) ? false : $check;
		};
		add_filter( 'add_post_metadata', $fail, 10, 3 );
		try {
			$result = $this->batch( $reference );
			$this->assertSame( BBP_Admin_Batch_Worker::RESULT_FAILED, $result['code'] );
			$this->assertSame( 'metadata-write', $result['error'] );
			$this->assertFalse( $result['complete'] );
			$this->assertSame( 0, get_option( $this->option( $reference ) )['cursor'] );
			$this->assert_values( $reference, array( $ids[0] ) );
			$this->assertSame( array( 'invalid' ), get_post_meta( $ids[1], $key, false ) );
			$this->assertFalse( bbp_admin_repair_tool_run_automatically( 'bbp-sync-' . $reference . '-meta', 'bbp_admin_repair_' . $reference . '_meta' ) );
			$this->assertContains( 'bbp-sync-' . $reference . '-meta', bbp_get_pending_upgrades() );
		} finally {
			remove_filter( 'add_post_metadata', $fail, 10 );
		}
		$this->finish( $reference );
		$this->assert_values( $reference, $ids );
		$this->assertNotContains( 'bbp-sync-' . $reference . '-meta', bbp_get_pending_upgrades() );
	}

	/**
	 * @dataProvider references
	 */
	public function test_initial_and_progress_checkpoint_failures_are_replay_safe( $reference ) {
		$ids    = $this->objects( $reference );
		$option = $this->option( $reference );
		$fail   = static function ( $value, $old ) {
			return $old;
		};
		$before = $this->snapshot();
		add_filter( 'pre_update_option_' . $option, $fail, 10, 2 );
		try {
			$result = $this->batch( $reference );
			$this->assertSame( BBP_Admin_Batch_Worker::RESULT_FAILED, $result['code'] );
			$this->assertSame( 'progress', $result['error'] );
			$this->assertSame( $before, $this->snapshot() );
		} finally {
			remove_filter( 'pre_update_option_' . $option, $fail, 10 );
		}
		$this->assertFalse( $this->batch( $reference )['complete'] );
		$state = get_option( $option );
		add_filter( 'pre_update_option_' . $option, $fail, 10, 2 );
		try {
			$result = $this->batch( $reference );
			$this->assertSame( BBP_Admin_Batch_Worker::RESULT_FAILED, $result['code'] );
			$this->assertSame( 'progress', $result['error'] );
			$this->assertSame( $state, get_option( $option ) );
		} finally {
			remove_filter( 'pre_update_option_' . $option, $fail, 10 );
		}
		$this->finish( $reference );
		$this->assert_values( $reference, $ids );
	}

	/**
	 * @dataProvider references
	 */
	public function test_failed_final_checkpoint_deletion_stays_pending( $reference ) {
		$db     = bbp_db();
		$ids    = $this->objects( $reference, 1 );
		$option = $this->option( $reference );
		$fail   = static function ( $query ) use ( $db, $option ) {
			return ( 0 === strpos( $query, 'DELETE FROM' ) ) && ( false !== strpos( $query, $db->options ) ) && ( false !== strpos( $query, $option ) ) ? 'DELETE FROM bbp_missing_reference_options' : $query;
		};
		$suppress = $db->suppress_errors( true );
		add_filter( 'query', $fail );
		try {
			$this->assertFalse( bbp_admin_repair_tool_run_automatically( 'bbp-sync-' . $reference . '-meta', 'bbp_admin_repair_' . $reference . '_meta' ) );
			$this->assertContains( 'bbp-sync-' . $reference . '-meta', bbp_get_pending_upgrades() );
			$this->assertSame( 0, get_option( $option )['cursor'] );
			$this->assert_values( $reference, $ids );
		} finally {
			remove_filter( 'query', $fail );
			$db->suppress_errors( $suppress );
		}
		$this->finish( $reference );
		$this->assertNotContains( 'bbp-sync-' . $reference . '-meta', bbp_get_pending_upgrades() );
	}

	public function test_parent_forum_results_match_legacy_queries_including_zero_and_orphan_rows() {
		$db    = bbp_db();
		$root  = $this->factory->forum->create();
		$child = $this->factory->forum->create( array( 'post_parent' => $root ) );
		$topic = $this->factory->topic->create( array( 'post_parent' => $child ) );
		$reply = $this->factory->reply->create( array( 'post_parent' => $topic ) );
		$zero  = $this->factory->topic->create( array( 'post_parent' => 0 ) );
		$this->factory->reply->create( array( 'post_parent' => $zero ) );
		$missing_parent = $this->factory->topic->create( array( 'post_parent' => 999999 ) );
		$this->factory->reply->create( array( 'post_parent' => $missing_parent ) );
		$orphan = $this->factory->reply->create( array( 'post_parent' => $root ) );
		$other  = $this->factory->post->create();
		$db->update( $db->posts, array( 'post_type' => 'FORUM', 'post_status' => 'bbp-unknown' ), array( 'ID' => $root ) );
		$db->update( $db->posts, array( 'post_status' => 'trash' ), array( 'ID' => $topic ) );
		$db->update( $db->posts, array( 'post_status' => 'pending' ), array( 'ID' => $reply ) );
		$keep = (int) $db->get_var( $db->prepare( "SELECT meta_id FROM {$db->postmeta} WHERE post_id = %d AND BINARY meta_key = '_bbp_forum_id' AND BINARY meta_value = '0' ORDER BY meta_id LIMIT 1", $root ) );
		$this->assertGreaterThan( 0, $keep );
		foreach ( array( $root, $child, $topic, $reply, $orphan, $other, 1000000, 0 ) as $id ) {
			$db->insert( $db->postmeta, array( 'post_id' => $id, 'meta_key' => '_BBP_FORUM_ID', 'meta_value' => '00' ) );
		}
		$expected = $db->get_results( $db->prepare(
			"SELECT ID AS post_id, post_parent AS forum_id FROM {$db->posts} WHERE post_type IN (%s, %s)
			UNION ALL SELECT reply.ID AS post_id, topic.post_parent AS forum_id FROM {$db->posts} AS reply
			INNER JOIN {$db->posts} AS topic ON reply.post_parent = topic.ID
			WHERE reply.post_type = %s AND topic.post_type = %s ORDER BY post_id",
			bbp_get_forum_post_type(), bbp_get_topic_post_type(), bbp_get_reply_post_type(), bbp_get_topic_post_type()
		), ARRAY_A );
		$other_keys = $db->get_results( "SELECT * FROM {$db->postmeta} WHERE meta_key <> '_bbp_forum_id' ORDER BY meta_id", ARRAY_A );
		for ( $batch = 0; $batch < 40; ++$batch ) {
			$before  = $this->snapshot();
			$preview = $this->batch( 'forum', true );
			$this->assertSame( $before, $this->snapshot() );
			$before_ids = $this->meta_ids();
			$result     = $this->batch( 'forum' );
			$after_ids  = $this->meta_ids();
			$this->assertTrue( $preview['complete'] );
			$this->assertSame( 0, $preview['code'] );
			$this->assertSame( 0, $result['code'] );
			$this->assertLessThanOrEqual( 2, count( array_diff( $before_ids, $after_ids ) ) + count( array_diff( $after_ids, $before_ids ) ) );
			$this->assertNotFalse( get_metadata_by_mid( 'post', $keep ) );
			if ( $result['complete'] ) {
				break;
			}
		}
		$this->assertTrue( $result['complete'] );
		$this->assertSame( $expected, $db->get_results( "SELECT post_id, meta_value AS forum_id FROM {$db->postmeta} WHERE meta_key = '_bbp_forum_id' ORDER BY post_id", ARRAY_A ) );
		$this->assertSame( $other_keys, $db->get_results( "SELECT * FROM {$db->postmeta} WHERE meta_key <> '_bbp_forum_id' ORDER BY meta_id", ARRAY_A ) );
	}

	public function test_reference_tools_keep_separate_compatible_checkpoints() {
		$forums = $this->objects( 'forum' );
		$topics = $this->objects( 'topic' );
		$this->assertFalse( $this->batch( 'forum' )['complete'] );
		$forum_state = get_option( $this->option( 'forum' ) );
		$result      = $this->batch( 'topic' );
		$this->assertFalse( $result['complete'] );
		$this->assertSame( array( 'code', 'error', 'complete', 'restarted', 'batches', 'topics', 'replies', 'stale', 'initial' ), array_keys( $result ) );
		$topic_state = get_option( $this->option( 'topic' ) );
		$this->assertSame( array( bbp_get_topic_post_type(), bbp_get_reply_post_type() ), $topic_state['post_types'] );
		$this->finish( 'forum' );
		$this->assertSame( $topic_state, get_option( $this->option( 'topic' ) ) );
		$this->assert_values( 'forum', $forums );
		$this->finish( 'topic' );
		$this->assert_values( 'topic', $topics );
		$this->assertNotSame( $forum_state, $topic_state );
	}

	public function test_context_changes_restart_safely_and_automatic_budget_exhaustion_can_resume() {
		$forums = $this->objects( 'forum' );
		$this->assertFalse( $this->batch( 'forum' )['complete'] );
		$option              = $this->option( 'forum' );
		$state               = get_option( $option );
		$state['post_types'] = array( 'old' );
		update_option( $option, $state, false );
		$before = $this->snapshot();
		$this->assertFalse( $this->batch( 'forum', true )['restarted'] );
		$this->assertSame( $before, $this->snapshot() );
		add_filter( 'bbp_admin_repair_tool_automatic_batch_limit', '__return_true' );
		try {
			$this->assertFalse( bbp_admin_repair_tool_run_automatically( 'bbp-sync-forum-meta', 'bbp_admin_repair_forum_meta' ) );
			$this->assertContains( 'bbp-sync-forum-meta', bbp_get_pending_upgrades() );
		} finally {
			remove_filter( 'bbp_admin_repair_tool_automatic_batch_limit', '__return_true' );
		}
		$this->finish( 'forum' );
		$this->assert_values( 'forum', $forums );
		$this->assertNotContains( 'bbp-sync-forum-meta', bbp_get_pending_upgrades() );
		$before = $this->snapshot();
		$result = bbp_admin_repair_post_reference_batch( 'unknown' );
		$this->assertSame( BBP_Admin_Batch_Worker::RESULT_FAILED, $result['code'] );
		$this->assertSame( 'callback', $result['error'] );
		$this->assertFalse( $result['complete'] );
		$this->assertSame( $before, $this->snapshot() );
	}

	public function test_parent_forum_progress_is_isolated_between_sites() {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'Requires multisite.' );
		}
		$forums = $this->objects( 'forum' );
		$this->assertFalse( $this->batch( 'forum' )['complete'] );
		$state   = get_option( $this->option( 'forum' ) );
		$blog_id = self::factory()->blog->create();
		switch_to_blog( $blog_id );
		try {
			$other = $this->objects( 'forum' );
			$this->finish( 'forum' );
			$this->assert_values( 'forum', $other );
		} finally {
			restore_current_blog();
		}
		$this->assertSame( $state, get_option( $this->option( 'forum' ) ) );
		$this->finish( 'forum' );
		$this->assert_values( 'forum', $forums );
	}
}
