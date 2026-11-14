<?php

/**
 * Resumable legacy favorites and subscription repairs.
 *
 * @group tools
 * @group tool-batches
 */
class BBP_Tests_Admin_User_List_Batches extends BBP_UnitTestCase {

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

	public function tools() {
		return array(
			array( 'favorites', 'favorites', 'topic', 'bbp_admin_repair_user_favorites' ),
			array( 'topic-subscriptions', 'subscriptions', 'topic', 'bbp_admin_repair_user_topic_subscriptions' ),
			array( 'forum-subscriptions', 'forum_subscriptions', 'forum', 'bbp_admin_repair_user_forum_subscriptions' )
		);
	}

	public function boundaries() {
		$result = array();
		foreach ( $this->tools() as $args ) {
			foreach ( array( 1, 1000 ) as $size ) {
				$result[] = array_merge( $args, array( $size ) );
			}
		}
		return $result;
	}

	public function failures() {
		$result = array();
		foreach ( $this->tools() as $args ) {
			foreach ( array( false, true ) as $delete ) {
				foreach ( array( false, true ) as $reported ) {
					$result[] = array_merge( $args, array( $delete, $reported ) );
				}
			}
		}
		return $result;
	}

	private function option( $type ) {
		return bbp_admin_repair_tool_batch_option_name( 'bbp-user-' . $type );
	}

	private function key( $suffix ) {
		return bbp_db()->prefix . '_bbp_' . $suffix;
	}

	private function fixture( $suffix, $post ) {
		$users = $this->factory->user->create_many( 3 );
		$live  = $this->factory->{$post}->create_many( 3 );
		$key   = $this->key( $suffix );
		$raw   = implode( ',', array_merge( array( $live[1], $live[0], $live[0] ), range( 900000, 900009 ) ) );
		$rows  = array(
			add_user_meta( $users[0], $key, $raw ),
			add_user_meta( $users[1], $key, '999999' ),
			add_user_meta( $users[2], $key, $live[2] )
		);
		return array( $users, $live, $rows, $raw );
	}

	private function snapshot() {
		$db = bbp_db();
		return array(
			$db->get_results( "SELECT * FROM {$db->usermeta} ORDER BY umeta_id", ARRAY_A ),
			$db->get_results( "SELECT * FROM {$db->options} ORDER BY option_id", ARRAY_A )
		);
	}

	private function finish( $type ) {
		for ( $i = 0; $i < 250; ++$i ) {
			$result = bbp_admin_repair_user_meta_batch( $type );
			$this->assertSame( 0, $result['code'] );
			if ( $result['complete'] ) {
				$this->assertFalse( get_option( $this->option( $type ), false ) );
				return $result;
			}
		}
		$this->fail( 'The legacy list repair did not finish.' );
	}

	private function assert_values( $data, $suffix ) {
		list( $users, $live, $rows ) = $data;
		$key = $this->key( $suffix );
		$this->assertSame( array( $live[0] . ',' . $live[1] ), get_user_meta( $users[0], $key, false ) );
		$this->assertSame( array(), get_user_meta( $users[1], $key, false ) );
		$this->assertSame( array( (string) $live[2] ), get_user_meta( $users[2], $key, false ) );
		$this->assertNotFalse( get_metadata_by_mid( 'user', $rows[0] ) );
		$this->assertNotFalse( get_metadata_by_mid( 'user', $rows[2] ) );
	}

	/** @dataProvider boundaries */
	public function test_bounded_checks_and_writes_match_read_only_previews( $type, $suffix, $post, $callback, $size ) {
		$db   = bbp_db();
		$data = $this->fixture( $suffix, $post );
		$this->size = $size;
		$writes = 0;
		$check  = function ( $query ) use ( $db, $size, &$writes ) {
			if ( preg_match( '/^(UPDATE|DELETE FROM)\s+`?' . preg_quote( $db->usermeta, '/' ) . '`?\s/', $query ) ) {
				++$writes;
				$this->assertStringContainsString( 'umeta_id', $query );
			}
			if ( preg_match( '/AND ID IN \(([^)]+)\)/', $query, $matches ) ) {
				$this->assertLessThanOrEqual( $size, count( explode( ',', $matches[1] ) ) );
			}
			return $query;
		};
		add_filter( 'query', $check );
		try {
			for ( $i = 0; $i < 250; ++$i ) {
				$before  = $this->snapshot();
				$writes  = 0;
				$preview = bbp_admin_repair_user_meta_batch( $type, true );
				$this->assertSame( $before, $this->snapshot() );
				$this->assertSame( 0, $writes );
				$result = bbp_admin_repair_user_meta_batch( $type );
				$this->assertSame( 0, $result['code'] );
				$this->assertTrue( $preview['complete'] );
			$this->assertSame( 0, $preview['code'] );
				$this->assertLessThanOrEqual( $size, $writes );
				if ( $result['complete'] ) {
					break;
				}
			}
		} finally {
			remove_filter( 'query', $check );
		}
		$this->assertTrue( $result['complete'] );
		$this->assert_values( $data, $suffix );
		$before = $this->snapshot();
		$this->finish( $type );
		$this->assertSame( $before, $this->snapshot() );
	}

	/** @dataProvider tools */
	public function test_all_empty_lists_remain_untouched_after_a_bounded_probe( $type, $suffix, $post, $callback ) {
		$this->factory->{$post}->create();
		$user = $this->factory->user->create();
		add_user_meta( $user, $this->key( $suffix ), '999999,999998' );
		$before = $this->snapshot();
		$first  = bbp_admin_repair_user_meta_batch( $type, true );
		$this->assertTrue( $first['complete'] );
		$this->assertSame( $before, $this->snapshot() );
		$result = $this->finish( $type );
		$this->assertTrue( $result['empty'] );
		$this->assertSame( $before, $this->snapshot() );
	}

	/** @dataProvider failures */
	public function test_failed_or_falsely_reported_writes_remain_pending_and_retry_safely( $type, $suffix, $post, $callback, $delete, $reported ) {
		$data   = $this->fixture( $suffix, $post );
		$target = $data[2][ $delete ? 1 : 0 ];
		$hook   = $delete ? 'delete_user_metadata_by_mid' : 'update_user_metadata_by_mid';
		$this->size = 1000;
		$fail = static function ( $check, $id ) use ( $target, $reported ) { return $target === $id ? $reported : $check; };
		add_filter( $hook, $fail, 10, 2 );
		try {
			$result = bbp_admin_repair_user_meta_batch( $type );
			$this->assertSame( BBP_Admin_Batch_Worker::RESULT_FAILED, $result['code'] );
			$this->assertSame( 'metadata-write', $result['error'] );
			$this->assertFalse( $result['complete'] );
			$this->assertNotFalse( get_metadata_by_mid( 'user', $target ) );
			$this->assertFalse( bbp_admin_repair_tool_run_automatically( 'bbp-user-' . $type, $callback ) );
			$this->assertContains( 'bbp-user-' . $type, bbp_get_pending_upgrades() );
		} finally {
			remove_filter( $hook, $fail, 10 );
		}
		$this->assertTrue( bbp_admin_repair_tool_run_automatically( 'bbp-user-' . $type, $callback ) );
		$this->assertNotContains( 'bbp-user-' . $type, bbp_get_pending_upgrades() );
		$this->assert_values( $data, $suffix );
	}

	/** @dataProvider tools */
	public function test_lost_checkpoint_after_an_in_place_update_cannot_lose_the_list( $type, $suffix, $post, $callback ) {
		$data = $this->fixture( $suffix, $post );
		for ( $i = 0; $i < 100; ++$i ) {
			bbp_admin_repair_user_meta_batch( $type );
			$state = get_option( $this->option( $type ) );
			if ( $state['ready'] ) {
				break;
			}
		}
		$this->assertTrue( $state['ready'] );
		$fail = static function ( $value, $old ) { return $old; };
		add_filter( 'pre_update_option_' . $this->option( $type ), $fail, 10, 2 );
		try {
			$result = bbp_admin_repair_user_meta_batch( $type );
			$this->assertSame( BBP_Admin_Batch_Worker::RESULT_FAILED, $result['code'] );
			$this->assertSame( 'progress', $result['error'] );
			$this->assertSame( $state, get_option( $this->option( $type ) ) );
			$this->assertSame( $data[1][0] . ',' . $data[1][1], get_metadata_by_mid( 'user', $data[2][0] )->meta_value );
		} finally {
			remove_filter( 'pre_update_option_' . $this->option( $type ), $fail, 10 );
		}
		$this->finish( $type );
		$this->assert_values( $data, $suffix );
	}

	/** @dataProvider tools */
	public function test_failed_final_checkpoint_deletion_is_replay_safe( $type, $suffix, $post, $callback ) {
		$db     = bbp_db();
		$data   = $this->fixture( $suffix, $post );
		$option = $this->option( $type );
		$this->size = 1000;
		$fail = static function ( $query ) use ( $db, $option ) {
			return 0 === strpos( $query, 'DELETE FROM' ) && false !== strpos( $query, $db->options ) && false !== strpos( $query, "'{$option}'" ) ? 'DELETE FROM bbp_missing_user_list_options' : $query;
		};
		$suppress = $db->suppress_errors( true );
		add_filter( 'query', $fail );
		try {
			$this->assertFalse( bbp_admin_repair_tool_run_automatically( 'bbp-user-' . $type, $callback ) );
			$this->assertNotFalse( get_option( $option, false ) );
		} finally {
			remove_filter( 'query', $fail );
			$db->suppress_errors( $suppress );
		}
		$this->assert_values( $data, $suffix );
		$before = bbp_db()->get_results( 'SELECT * FROM ' . bbp_db()->usermeta . ' ORDER BY umeta_id', ARRAY_A );
		$this->assertTrue( bbp_admin_repair_tool_run_automatically( 'bbp-user-' . $type, $callback ) );
		$this->assertSame( $before, bbp_db()->get_results( 'SELECT * FROM ' . bbp_db()->usermeta . ' ORDER BY umeta_id', ARRAY_A ) );
	}

	/** @dataProvider tools */
	public function test_source_read_failures_preserve_progress_and_retry_safely( $type, $suffix, $post, $callback ) {
		$db     = bbp_db();
		$data   = $this->fixture( $suffix, $post );
		$option = $this->option( $type );
		$this->assertFalse( bbp_admin_repair_user_meta_batch( $type )['complete'] );
		$state    = get_option( $option );
		$before   = $this->snapshot();
		$usermeta = $db->usermeta;
		$suppress = $db->suppress_errors( true );
		try {
			$db->usermeta = 'bbp_missing_user_list_meta';
			foreach ( array( true, false ) as $dry_run ) {
				$result = bbp_admin_repair_user_meta_batch( $type, $dry_run );
				$this->assertSame( BBP_Admin_Batch_Worker::RESULT_FAILED, $result['code'] );
				$this->assertSame( 'metadata-read', $result['error'] );
				$this->assertFalse( $result['complete'] );
			}
		} finally {
			$db->usermeta = $usermeta;
			$db->suppress_errors( $suppress );
		}
		$this->assertSame( $state, get_option( $option ) );
		$this->assertSame( $before, $this->snapshot() );
		$this->finish( $type );
		$this->assert_values( $data, $suffix );
	}

	/** @dataProvider tools */
	public function test_changed_source_and_higher_metadata_ids_remain_safe( $type, $suffix, $post, $callback ) {
		$data = $this->fixture( $suffix, $post );
		bbp_admin_repair_user_meta_batch( $type );
		bbp_admin_repair_user_meta_batch( $type );
		update_metadata_by_mid( 'user', $data[2][0], (string) $data[1][1] );
		$extra = add_user_meta( $data[0][0], $this->key( $suffix ), '999999' );
		$this->finish( $type );
		$this->assertSame( (string) $data[1][1], get_metadata_by_mid( 'user', $data[2][0] )->meta_value );
		$this->assertNotFalse( get_metadata_by_mid( 'user', $extra ) );
		$this->finish( $type );
		$this->assertFalse( get_metadata_by_mid( 'user', $extra ) );
	}

	/** @dataProvider tools */
	public function test_malformed_row_progress_restarts_without_preview_writes( $type, $suffix, $post, $callback ) {
		$data = $this->fixture( $suffix, $post );
		bbp_admin_repair_user_meta_batch( $type );
		bbp_admin_repair_user_meta_batch( $type );
		$state           = get_option( $this->option( $type ) );
		$state['offset'] = 999999;
		update_option( $this->option( $type ), $state, false );
		$before = $this->snapshot();
		$this->assertFalse( bbp_admin_repair_user_meta_batch( $type, true )['restarted'] );
		$this->assertSame( $before, $this->snapshot() );
		$this->finish( $type );
		$this->assert_values( $data, $suffix );
	}

	public function test_malformed_preview_state_restarts_and_preserves_full_totals() {
		$this->fixture( 'favorites', 'topic' );
		$expected = bbp_admin_repair_user_meta_batch( 'favorites', true );
		$before   = $this->snapshot();

		bbp_admin_repair_tool_execution_context( 'dry-run', 'malformed-user-list-preview' );
		$option = $this->option( 'favorites' );
		try {
			$result = bbp_admin_repair_user_meta_batch( 'favorites', true );
			$this->assertFalse( $result['complete'] );

			$state = get_option( $option );
			$state['_preview_state']['totals']['repaired'] = -1;
			update_option( $option, $state, false );
			$result = bbp_admin_repair_user_meta_batch( 'favorites', true );
			$this->assertTrue( $result['restarted'] );

			$state = get_option( $option );
			$state['_preview_state']['totals']['initial'] = 'yes';
			update_option( $option, $state, false );
			$result = bbp_admin_repair_user_meta_batch( 'favorites', true );
			$this->assertTrue( $result['restarted'] );

			for ( $i = 0; $i < 100; ++$i ) {
				$result = bbp_admin_repair_user_meta_batch( 'favorites', true );
				if ( $result['complete'] ) {
					break;
				}
			}
		} finally {
			bbp_admin_repair_tool_execution_context( 'run', '' );
		}

		$this->assertTrue( $result['complete'] );
		$this->assertSame( $expected, $result );
		$this->assertSame( $before, $this->snapshot() );
		$this->assertFalse( get_option( $option, false ) );
	}

	public function test_browser_preview_uses_bounded_scratch_and_fixed_size_checkpoints() {
		$this->fixture( 'favorites', 'topic' );
		$before = $this->snapshot();

		bbp_admin_repair_tool_execution_context( 'dry-run', 'bounded-user-list-preview' );
		$option = $this->option( 'favorites' );
		$prefix = bbp_admin_repair_tool_scratch_namespace( 'bbp-user-favorites' );
		try {
			for ( $i = 0; $i < 100; ++$i ) {
				$result = bbp_admin_repair_user_meta_batch( 'favorites', true );
				$state  = get_option( $option, false );
				if ( is_array( $state ) ) {
					$this->assertArrayNotHasKey( 'kept', $state );
				}
				$rows = bbp_db()->get_results( bbp_db()->prepare( "SELECT option_name, option_value FROM " . bbp_db()->options . " WHERE option_name LIKE %s", bbp_db()->esc_like( $prefix ) . '%' ) );
				foreach ( $rows as $row ) {
					$chunk = maybe_unserialize( $row->option_value );
					$this->assertLessThanOrEqual( 191, strlen( $row->option_name ) );
					$this->assertSame( array( 'offset', 'next', 'ids' ), array_keys( $chunk ) );
					$this->assertLessThanOrEqual( $this->size, count( $chunk['ids'] ) );
				}
				if ( $result['complete'] ) {
					break;
				}
			}
		} finally {
			bbp_admin_repair_tool_execution_context( 'run', '' );
		}

		$this->assertTrue( $result['complete'] );
		$this->assertFalse( get_option( $option, false ) );
		$this->assertSame( 0, (int) bbp_db()->get_var( bbp_db()->prepare( "SELECT COUNT(*) FROM " . bbp_db()->options . " WHERE option_name LIKE %s", bbp_db()->esc_like( $prefix ) . '%' ) ) );
		$this->assertSame( $before, $this->snapshot() );
	}

	public function test_missing_scratch_chunk_restarts_only_the_current_user_list() {
		$data = $this->fixture( 'favorites', 'topic' );
		for ( $i = 0; $i < 100; ++$i ) {
			bbp_admin_repair_user_meta_batch( 'favorites' );
			$state = get_option( $this->option( 'favorites' ) );
			if ( $state['ready'] ) {
				break;
			}
		}
		$this->assertTrue( $state['ready'] );
		$collection = 'user_' . md5( $state['cursor'] . '|' . $state['row_hash'] );
		$option     = bbp_admin_repair_tool_scratch_option_name( 'bbp-user-favorites', $state['token'], $collection, 0 );
		$this->assertTrue( bbp_admin_repair_tool_save_option( $option, null, true ) );

		$result = bbp_admin_repair_user_meta_batch( 'favorites' );
		$this->assertSame( 0, $result['code'] );
		$this->assertTrue( $result['restarted'] );
		$restarted = get_option( $this->option( 'favorites' ) );
		$this->assertSame( $state['cursor'], $restarted['cursor'] );
		$this->assertTrue( $restarted['scratch_retry'] );

		$this->finish( 'favorites' );
		$this->assert_values( $data, 'favorites' );
	}

	public function test_empty_preview_batch_estimate_matches_full_run_requests() {
		$this->factory->topic->create();
		$user = $this->factory->user->create();
		add_user_meta( $user, $this->key( 'favorites' ), '999999,999998' );
		$preview = bbp_admin_repair_user_meta_batch( 'favorites', true );
		$requests = 0;
		for ( $i = 0; $i < 100; ++$i ) {
			$result = bbp_admin_repair_user_meta_batch( 'favorites' );
			++$requests;
			if ( $result['complete'] ) {
				break;
			}
		}

		$this->assertTrue( $result['complete'] );
		$this->assertSame( $requests, $preview['batches'] );
	}

	/** @dataProvider tools */
	public function test_automatic_budget_exhaustion_retains_progress_until_retry( $type, $suffix, $post, $callback ) {
		$data = $this->fixture( $suffix, $post );
		add_filter( 'bbp_admin_repair_tool_automatic_batch_limit', '__return_true' );
		try {
			$this->assertFalse( bbp_admin_repair_tool_run_automatically( 'bbp-user-' . $type, $callback ) );
			$this->assertNotFalse( get_option( $this->option( $type ), false ) );
			$this->assertContains( 'bbp-user-' . $type, bbp_get_pending_upgrades() );
		} finally {
			remove_filter( 'bbp_admin_repair_tool_automatic_batch_limit', '__return_true' );
		}
		$this->assertTrue( bbp_admin_repair_tool_run_automatically( 'bbp-user-' . $type, $callback ) );
		$this->assert_values( $data, $suffix );
	}

	/** @dataProvider tools */
	public function test_initial_checkpoint_failure_never_changes_source_data( $type, $suffix, $post, $callback ) {
		$this->fixture( $suffix, $post );
		$before = $this->snapshot();
		$fail   = static function ( $value, $old ) { return $old; };
		add_filter( 'pre_update_option_' . $this->option( $type ), $fail, 10, 2 );
		try {
			$result = bbp_admin_repair_user_meta_batch( $type );
			$this->assertSame( BBP_Admin_Batch_Worker::RESULT_FAILED, $result['code'] );
			$this->assertSame( 'progress', $result['error'] );
			$this->assertSame( $before, $this->snapshot() );
		} finally {
			remove_filter( 'pre_update_option_' . $this->option( $type ), $fail, 10 );
		}
		$this->finish( $type );
	}

	/** @dataProvider tools */
	public function test_deleted_active_rows_do_not_skip_later_rows( $type, $suffix, $post, $callback ) {
		$data = $this->fixture( $suffix, $post );
		bbp_admin_repair_user_meta_batch( $type );
		bbp_admin_repair_user_meta_batch( $type );
		delete_metadata_by_mid( 'user', $data[2][0] );
		$this->finish( $type );
		$this->assertFalse( get_metadata_by_mid( 'user', $data[2][1] ) );
		$this->assertSame( (string) $data[1][2], get_metadata_by_mid( 'user', $data[2][2] )->meta_value );
	}

	/** @dataProvider tools */
	public function test_canonical_tokens_duplicate_rows_and_orphaned_users_keep_their_semantics( $type, $suffix, $post, $callback ) {
		$db   = bbp_db();
		$live = $this->factory->{$post}->create_many( 3 );
		$key  = $this->key( $suffix );
		$user = $this->factory->user->create();
		$db->update( $db->posts, array( 'post_status' => 'PUBLISH' ), array( 'ID' => $live[1] ) );
		$raw    = implode( ',', array( $live[1], $live[0], $live[0], '0' . $live[2], ' ' . $live[2], $live[2] . 'foo' ) );
		$row    = add_user_meta( $user, strtoupper( $key ), $raw );
		$keep   = add_user_meta( $user, $key, $live[0] );
		$orphan = add_user_meta( 999999, $key, $raw );
		$zero   = (int) $db->insert( $db->usermeta, array( 'user_id' => 0, 'meta_key' => $key, 'meta_value' => $raw ) );
		$this->assertSame( 1, $zero );
		$zero = (int) $db->insert_id;
		$this->finish( $type );
		$this->assertSame( $live[0] . ',' . $live[1], get_metadata_by_mid( 'user', $row )->meta_value );
		$this->assertSame( $key, get_metadata_by_mid( 'user', $row )->meta_key );
		$this->assertNotFalse( get_metadata_by_mid( 'user', $keep ) );
		$this->assertSame( $live[0] . ',' . $live[1], get_metadata_by_mid( 'user', $orphan )->meta_value );
		$this->assertSame( $live[0] . ',' . $live[1], get_metadata_by_mid( 'user', $zero )->meta_value );
	}

	/** @dataProvider tools */
	public function test_null_empty_and_serialized_values_are_handled_without_losing_live_lists( $type, $suffix, $post, $callback ) {
		$db   = bbp_db();
		$data = $this->fixture( $suffix, $post );
		$key  = $this->key( $suffix );
		$rows = array(
			add_user_meta( $data[0][0], $key, '' ),
			add_user_meta( $data[0][0], $key, array( 'invalid' ) )
		);
		$db->insert( $db->usermeta, array( 'user_id' => $data[0][0], 'meta_key' => $key, 'meta_value' => null ) );
		$rows[] = (int) $db->insert_id;
		$this->finish( $type );
		$this->assert_values( $data, $suffix );
		foreach ( $rows as $id ) {
			$this->assertFalse( get_metadata_by_mid( 'user', $id ) );
		}
	}

	/** @dataProvider tools */
	public function test_lists_and_progress_are_isolated_per_site( $type, $suffix, $post, $callback ) {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'Requires multisite.' );
		}
		$data = $this->fixture( $suffix, $post );
		bbp_admin_repair_user_meta_batch( $type );
		$state = get_option( $this->option( $type ) );
		$blog  = $this->factory->blog->create();
		switch_to_blog( $blog );
		try {
			$this->assertFalse( get_option( $this->option( $type ), false ) );
			$other = $this->fixture( $suffix, $post );
			$this->finish( $type );
			$this->assert_values( $other, $suffix );
		} finally {
			restore_current_blog();
		}
		$this->assertSame( $state, get_option( $this->option( $type ) ) );
		$this->finish( $type );
		$this->assert_values( $data, $suffix );
	}
}
