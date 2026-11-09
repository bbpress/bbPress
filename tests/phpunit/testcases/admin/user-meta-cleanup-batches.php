<?php

/**
 * Resumable cleanup of migrated legacy user data.
 *
 * @group tools
 * @group tool-batches
 */
class BBP_Tests_Admin_User_Meta_Cleanup_Batches extends BBP_UnitTestCase {

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
			array( 'favorites', 'favorites', 'bbp_admin_upgrade_remove_favorites_from_usermeta' ),
			array( 'topic-subscriptions', 'subscriptions', 'bbp_admin_upgrade_remove_topic_subscriptions_from_usermeta' ),
			array( 'forum-subscriptions', 'forum_subscriptions', 'bbp_admin_upgrade_remove_forum_subscriptions_from_usermeta' )
		);
	}

	public function boundaries() {
		return $this->cases( array( 1, 1000 ) );
	}

	public function failed_deletes() {
		return $this->cases( array( false, true ) );
	}

	public function checkpoint_failures() {
		return $this->cases( array( 'initial', 'progress', 'final' ) );
	}

	public function migration_guards() {
		return $this->cases( array( 'pending', 'checkpoint', 'lock' ) );
	}

	public function active_storage() {
		return $this->cases( array( false, true ) );
	}

	public function read_failures() {
		return $this->cases( array( 'selection', 'verification' ) );
	}

	private function cases( $values ) {
		$cases = array();
		foreach ( $this->tools() as $args ) {
			foreach ( $values as $value ) {
				$cases[] = array_merge( $args, array( $value ) );
			}
		}
		return $cases;
	}

	private function tool_id( $type ) {
		return 'bbp-user-' . $type . '-delete';
	}

	private function option( $type ) {
		return bbp_admin_repair_tool_batch_option_name( $this->tool_id( $type ) );
	}

	private function key( $suffix ) {
		return bbp_db()->prefix . '_bbp_' . $suffix;
	}

	private function fixture( $suffix, $count = 3 ) {
		$user = $this->factory->user->create();
		$rows = array();
		for ( $i = 0; $i < $count; ++$i ) {
			$rows[] = add_user_meta( $user, $this->key( $suffix ), '1,2' );
		}
		return array( $user, $rows );
	}

	private function snapshot() {
		$db = bbp_db();
		return array(
			$db->get_results( "SELECT * FROM {$db->usermeta} ORDER BY umeta_id", ARRAY_A ),
			$db->get_results( "SELECT * FROM {$db->options} ORDER BY option_id", ARRAY_A )
		);
	}

	private function finish( $type ) {
		for ( $i = 0; $i < 10; ++$i ) {
			$batch = bbp_admin_upgrade_remove_user_meta_batch( $type );
			$this->assertSame( 0, $batch['code'] );
			if ( $batch['complete'] ) {
				$this->assertFalse( get_option( $this->option( $type ), false ) );
				return;
			}
		}
		$this->fail( 'Legacy cleanup did not finish.' );
	}

	/** @dataProvider boundaries */
	public function test_bounded_deletions_match_read_only_previews( $type, $suffix, $callback, $size ) {
		list( $user, $rows ) = $this->fixture( $suffix );
		$key = $this->key( $suffix );
		$other = add_user_meta( $user, '_bbp_unrelated_cleanup', 'keep' );
		get_user_meta( $user );
		$this->size = $size;
		$deleted = 0;
		$deletion = function ( $ids ) use ( &$deleted, $rows ) {
			$this->assertCount( 1, $ids );
			++$deleted;
			$this->assertContains( (int) $ids[0], $rows );
		};
		add_action( 'delete_user_meta', $deletion );
		try {
			do {
				$before = $this->snapshot();
				$deleted = 0;
				$preview = bbp_admin_upgrade_remove_user_meta_batch( $type, true );
				$this->assertSame( $before, $this->snapshot() );
				$this->assertSame( 0, $deleted );
				$batch = bbp_admin_upgrade_remove_user_meta_batch( $type );
				$this->assertSame( $preview, $batch );
				$this->assertSame( 0, $batch['code'] );
				$this->assertLessThanOrEqual( $size, $deleted );
				$this->assertSame( $batch['removed'], $deleted );
			} while ( ! $batch['complete'] );
		} finally {
			remove_action( 'delete_user_meta', $deletion );
		}
		$this->assertSame( array(), get_user_meta( $user, $key, false ) );
		$this->assertSame( 'keep', get_metadata_by_mid( 'user', $other )->meta_value );
		$this->assertSame( 1, call_user_func( $callback )[0] );
		bbp_register_default_repair_tools();
		$this->assertFalse( bbp_admin()->tools[ $this->tool_id( $type ) ]['flush_cache'] );
	}

	/** @dataProvider failed_deletes */
	public function test_failed_or_falsely_successful_deletion_is_retryable( $type, $suffix, $callback, $reported ) {
		list( $user, $rows ) = $this->fixture( $suffix );
		$this->size = 1000;
		$fail = static function ( $check, $id ) use ( $rows, $reported ) { return $rows[1] === $id ? $reported : $check; };
		add_filter( 'delete_user_metadata_by_mid', $fail, 10, 2 );
		try {
			$batch = bbp_admin_upgrade_remove_user_meta_batch( $type );
			$this->assertSame( 3, $batch['code'] );
			$this->assertFalse( $batch['complete'] );
			$this->assertSame( 1, $batch['removed'] );
			$this->assertFalse( get_metadata_by_mid( 'user', $rows[0] ) );
			$this->assertNotFalse( get_metadata_by_mid( 'user', $rows[1] ) );
			$this->assertNotFalse( get_metadata_by_mid( 'user', $rows[2] ) );
			$this->assertFalse( bbp_admin_repair_tool_run_automatically( $this->tool_id( $type ), $callback ) );
			$this->assertContains( $this->tool_id( $type ), bbp_get_pending_upgrades() );
		} finally {
			remove_filter( 'delete_user_metadata_by_mid', $fail, 10 );
		}
		$this->assertTrue( bbp_admin_repair_tool_run_automatically( $this->tool_id( $type ), $callback ) );
		$this->assertNotContains( $this->tool_id( $type ), bbp_get_pending_upgrades() );
		$this->assertSame( array(), get_user_meta( $user, $this->key( $suffix ), false ) );
	}

	/** @dataProvider checkpoint_failures */
	public function test_checkpoint_failures_cannot_falsely_complete_or_lose_remaining_rows( $type, $suffix, $callback, $phase ) {
		$db = bbp_db();
		list( $user, $rows ) = $this->fixture( $suffix );
		$option = $this->option( $type );
		if ( 'final' === $phase ) {
			$this->size = 1000;
		}
		$save = static function ( $value, $old ) use ( $phase ) {
			return 'initial' === $phase || ( is_array( $value ) && $value['cursor'] ) ? $old : $value;
		};
		$delete = static function ( $query ) use ( $db, $option ) {
			return 0 === strpos( $query, 'DELETE FROM' ) && false !== strpos( $query, $db->options ) && false !== strpos( $query, "'{$option}'" ) ? 'DELETE FROM bbp_missing_cleanup_options' : $query;
		};
		$old_suppression = $db->suppress_errors( true );
		if ( 'final' === $phase ) {
			add_filter( 'query', $delete );
		} else {
			add_filter( 'pre_update_option_' . $option, $save, 10, 2 );
		}
		try {
			$result = call_user_func( $callback );
			$this->assertSame( 6, $result[0] );
			$this->assertFalse( $result['success'] );
			$this->assertFalse( $result['complete'] );
			$this->assertCount( 'initial' === $phase ? 3 : ( 'progress' === $phase ? 2 : 0 ), get_user_meta( $user, $this->key( $suffix ), false ) );
		} finally {
			remove_filter( 'query', $delete );
			remove_filter( 'pre_update_option_' . $option, $save, 10 );
			$db->suppress_errors( $old_suppression );
		}
		$this->finish( $type );
		$this->assertSame( array(), get_user_meta( $user, $this->key( $suffix ), false ) );
	}

	/** @dataProvider migration_guards */
	public function test_unfinished_or_active_migrations_block_every_cleanup_batch( $type, $suffix, $callback, $guard ) {
		list( $user, $rows ) = $this->fixture( $suffix );
		bbp_admin_upgrade_remove_user_meta_batch( $type );
		$migration = 'bbp-user-' . $type . '-move';
		$option = bbp_admin_repair_tool_batch_option_name( $migration );
		$lock = false;
		if ( 'pending' === $guard ) {
			bbp_add_pending_upgrade( $migration );
		} elseif ( 'checkpoint' === $guard ) {
			update_option( $option, array( 'cursor' => 0, 'upper_id' => 1 ), false );
		} else {
			$lock = bbp_admin_repair_tool_lock( $migration );
		}
		$before = $this->snapshot();
		try {
			if ( 'lock' !== $guard ) {
				$this->assertFalse( call_user_func( $callback . '_dry_run' )['success'] );
			}
			$this->assertSame( $before, $this->snapshot() );
			$this->assertSame( 4, call_user_func( $callback )[0] );
			$this->assertSame( $before, $this->snapshot() );
		} finally {
			if ( $lock ) {
				bbp_admin_repair_tool_unlock( $lock );
			}
			delete_option( $option );
			bbp_remove_pending_upgrade( $migration );
		}
		$this->finish( $type );
	}

	/** @dataProvider tools */
	public function test_migration_state_is_refreshed_after_acquiring_its_lock( $type, $suffix, $callback ) {
		$db = bbp_db();
		$this->fixture( $suffix );
		$migration = 'bbp-user-' . $type . '-move';
		$option = bbp_admin_repair_tool_batch_option_name( $migration );
		$lock_name = '_bbp_tool_lock_' . md5( $migration );
		get_option( $option, false );
		$changed = false;
		$filter = static function ( $query ) use ( $db, $lock_name, $option, &$changed ) {
			if ( ! $changed && false !== strpos( $query, 'INSERT IGNORE' ) && false !== strpos( $query, $lock_name ) ) {
				$changed = true;
				$db->insert( $db->options, array( 'option_name' => $option, 'option_value' => maybe_serialize( array( 'cursor' => 0, 'upper_id' => 1 ) ), 'autoload' => 'no' ) );
			}
			return $query;
		};
		add_filter( 'query', $filter );
		$before = $db->get_results( "SELECT * FROM {$db->usermeta} ORDER BY umeta_id", ARRAY_A );
		try {
			$this->assertSame( 4, call_user_func( $callback )[0] );
			$this->assertTrue( $changed );
			$this->assertSame( $before, $db->get_results( "SELECT * FROM {$db->usermeta} ORDER BY umeta_id", ARRAY_A ) );
		} finally {
			remove_filter( 'query', $filter );
		}
	}

	/** @dataProvider tools */
	public function test_changed_keys_deleted_sources_and_new_higher_rows_are_scoped_safely( $type, $suffix, $callback ) {
		list( $user, $rows ) = $this->fixture( $suffix, 4 );
		bbp_admin_upgrade_remove_user_meta_batch( $type );
		update_metadata_by_mid( 'user', $rows[1], 'keep', '_bbp_cleanup_rekeyed' );
		delete_metadata_by_mid( 'user', $rows[2] );
		$new = add_user_meta( $user, $this->key( $suffix ), 'new' );
		$this->finish( $type );
		$this->assertSame( 'keep', get_metadata_by_mid( 'user', $rows[1] )->meta_value );
		$this->assertFalse( get_metadata_by_mid( 'user', $rows[3] ) );
		$this->assertNotFalse( get_metadata_by_mid( 'user', $new ) );
		$this->finish( $type );
		$this->assertFalse( get_metadata_by_mid( 'user', $new ) );
	}

	/** @dataProvider tools */
	public function test_malformed_checkpoints_restart_without_changing_preview_state( $type, $suffix, $callback ) {
		$this->fixture( $suffix );
		$option = $this->option( $type );
		update_option( $option, array( 'cursor' => 9999, 'upper_id' => 1, 'key' => $this->key( $suffix ) ), false );
		$before = $this->snapshot();
		$preview = bbp_admin_upgrade_remove_user_meta_batch( $type, true );
		$this->assertTrue( $preview['restarted'] );
		$this->assertSame( $before, $this->snapshot() );
		$this->assertSame( $preview, bbp_admin_upgrade_remove_user_meta_batch( $type ) );
		$this->finish( $type );
	}

	/** @dataProvider tools */
	public function test_automatic_budget_exhaustion_keeps_cleanup_pending_and_resumes( $type, $suffix, $callback ) {
		list( $user, $rows ) = $this->fixture( $suffix );
		add_filter( 'bbp_admin_repair_tool_automatic_batch_limit', '__return_true' );
		try {
			$this->assertFalse( bbp_admin_repair_tool_run_automatically( $this->tool_id( $type ), $callback ) );
			$this->assertCount( 2, get_user_meta( $user, $this->key( $suffix ), false ) );
			$this->assertContains( $this->tool_id( $type ), bbp_get_pending_upgrades() );
		} finally {
			remove_filter( 'bbp_admin_repair_tool_automatic_batch_limit', '__return_true' );
		}
		$this->assertTrue( bbp_admin_repair_tool_run_automatically( $this->tool_id( $type ), $callback ) );
		$this->assertNotContains( $this->tool_id( $type ), bbp_get_pending_upgrades() );
		$this->assertSame( array(), get_user_meta( $user, $this->key( $suffix ), false ) );
	}

	/** @dataProvider tools */
	public function test_dry_run_labels_partial_counts_as_one_batch( $type, $suffix, $callback ) {
		$this->fixture( $suffix );
		$result = call_user_func( $callback . '_dry_run' );
		$this->assertSame( 0, $result[0] );
		$this->assertFalse( $result['complete'] );
		$this->assertStringContainsString( "This batch:\n1 ", $result[1] );
	}

	/** @dataProvider active_storage */
	public function test_active_user_storage_blocks_initial_and_resumed_cleanup( $type, $suffix, $callback, $resumed ) {
		list( $user ) = $this->fixture( $suffix );
		if ( $resumed ) {
			$this->assertFalse( bbp_admin_upgrade_remove_user_meta_batch( $type )['complete'] );
		}
		update_option( '_bbp_engagements_strategy', 'user' );
		$before = $this->snapshot();
		foreach ( array( $callback . '_dry_run', $callback ) as $action ) {
			$result = call_user_func( $action );
			$this->assertSame( 4, $result[0] );
			$this->assertFalse( $result['success'] );
			$this->assertStringContainsString( 'data is in use', $result[1] );
			$this->assertSame( $before, $this->snapshot() );
		}
		$this->assertFalse( bbp_admin_repair_tool_run_automatically( $this->tool_id( $type ), $callback ) );
		$this->assertCount( $resumed ? 2 : 3, get_user_meta( $user, $this->key( $suffix ), false ) );
		$this->assertContains( $this->tool_id( $type ), bbp_get_pending_upgrades() );
		update_option( '_bbp_engagements_strategy', 'meta' );
		$this->assertTrue( bbp_admin_repair_tool_run_automatically( $this->tool_id( $type ), $callback ) );
		$this->assertSame( array(), get_user_meta( $user, $this->key( $suffix ), false ) );
	}

	public function test_cleanup_honors_the_filtered_storage_strategy() {
		list( $user ) = $this->fixture( 'favorites' );
		$filter = static function () { return 'user'; };
		add_filter( 'bbp_engagements_strategy', $filter );
		try {
			$this->assertSame( 4, bbp_admin_upgrade_remove_user_meta_batch()['code'] );
			$this->assertSame( 4, bbp_admin_upgrade_remove_user_meta_batch( 'favorites', true )['code'] );
			$this->assertCount( 3, get_user_meta( $user, $this->key( 'favorites' ), false ) );
		} finally {
			remove_filter( 'bbp_engagements_strategy', $filter );
		}
	}

	public function test_storage_strategy_is_refreshed_after_acquiring_the_migration_lock() {
		$db = bbp_db();
		list( $user ) = $this->fixture( 'favorites' );
		delete_option( '_bbp_engagements_strategy' );
		add_option( '_bbp_engagements_strategy', 'meta', '', false );
		$this->assertSame( 'meta', bbp_engagements_strategy() );
		$lock = '_bbp_tool_lock_' . md5( 'bbp-user-favorites-move' );
		$changed = false;
		$filter = static function ( $query ) use ( $db, $lock, &$changed ) {
			if ( ! $changed && false !== strpos( $query, 'INSERT IGNORE' ) && false !== strpos( $query, $lock ) ) {
				$changed = true;
				$db->update( $db->options, array( 'option_value' => 'user' ), array( 'option_name' => '_bbp_engagements_strategy' ) );
			}
			return $query;
		};
		add_filter( 'query', $filter );
		try {
			$this->assertSame( 4, bbp_admin_upgrade_remove_user_meta_batch()['code'] );
			$this->assertTrue( $changed );
			$this->assertCount( 3, get_user_meta( $user, $this->key( 'favorites' ), false ) );
		} finally {
			remove_filter( 'query', $filter );
		}
	}

	/** @dataProvider read_failures */
	public function test_selection_and_verification_read_failures_never_report_completion( $type, $suffix, $callback, $point ) {
		$db = bbp_db();
		list( $user ) = $this->fixture( $suffix );
		$prefix = 'selection' === $point ? 'SELECT umeta_id, user_id FROM ' : 'SELECT umeta_id FROM ';
		$fail = static function ( $query ) use ( $db, $prefix ) {
			return 0 === strpos( $query, $prefix . $db->usermeta ) ? 'SELECT * FROM bbp_missing_cleanup_read' : $query;
		};
		$old = $db->suppress_errors( true );
		add_filter( 'query', $fail );
		try {
			$result = call_user_func( $callback );
			$this->assertSame( 2, $result[0] );
			$this->assertFalse( $result['success'] );
			$this->assertFalse( $result['complete'] );
		} finally {
			remove_filter( 'query', $fail );
			$db->suppress_errors( $old );
		}
		$this->finish( $type );
		$this->assertSame( array(), get_user_meta( $user, $this->key( $suffix ), false ) );
	}

	public function test_multisite_cleanup_only_removes_the_current_sites_legacy_data() {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'Multisite only.' );
		}
		list( $user ) = $this->fixture( 'favorites', 1 );
		$original_key = $this->key( 'favorites' );
		$blog = $this->factory->blog->create();
		switch_to_blog( $blog );
		try {
			$other_key = $this->key( 'favorites' );
			add_user_meta( $user, $other_key, '3,4' );
			$this->finish( 'favorites' );
			$this->assertSame( array(), get_user_meta( $user, $other_key, false ) );
			$this->assertSame( '1,2', get_user_meta( $user, $original_key, true ) );
		} finally {
			restore_current_blog();
		}
	}
}
