<?php

/**
 * Bounded visibility scans, verified publication and automatic upgrades.
 *
 * @group tools
 * @group tool-batches
 */
class BBP_Tests_Admin_Forum_Visibility_Batches extends BBP_UnitTestCase {

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

	public function visibility_transitions() {
		return array( array( 'private', 'bbp_privatize_forum' ), array( 'hidden', 'bbp_hide_forum' ) );
	}

	public function publication_hooks() {
		return array( array( 'private', 'hidden', 'bbp_hide_forum' ), array( 'hidden', 'private', 'bbp_privatize_forum' ) );
	}

	public function membership_changes() {
		return array( array( 'status' ), array( 'enter' ), array( 'leave' ), array( 'delete' ) );
	}

	public function version_read_failures() {
		return array( array( 1 ), array( 2 ), array( 3 ), array( 4 ) );
	}

	public function partial_preview_sizes() {
		return array( array( 1 ), array( 5 ), array( 6 ) );
	}

	public function malformed() {
		return array( array( 'phase', -1 ), array( 'phase', 5 ), array( 'cursor', 'past-bound' ), array( 'values', false ), array( 'sources', false ), array( 'changed', 'yes' ), array( 'context', array() ) );
	}

	private function option() {
		return bbp_admin_repair_tool_batch_option_name( 'bbp-sync-forum-visibility' );
	}

	private function fixture() {
		$private = $this->factory->forum->create_many( 3, array( 'post_status' => bbp_get_private_status_id() ) );
		$hidden  = $this->factory->forum->create_many( 2, array( 'post_status' => bbp_get_hidden_status_id() ) );
		$this->factory->forum->create();
		$this->factory->post->create( array( 'post_status' => bbp_get_private_status_id() ) );
		update_option( '_bbp_private_forums', array( 999999 ) );
		update_option( '_bbp_hidden_forums', 'malformed' );
		return array( $private, $hidden );
	}

	private function snapshot() {
		$db = bbp_db();
		return $db->get_results( "SELECT * FROM {$db->options} ORDER BY option_id", ARRAY_A );
	}

	private function finish() {
		for ( $i = 0; $i < 100; ++$i ) {
			$result = bbp_admin_repair_forum_visibility_batch();
			$this->assertSame( 0, $result['code'] );
			if ( $result['complete'] ) {
				$this->assertFalse( get_option( $this->option(), false ) );
				return $result;
			}
		}
		$this->fail( 'Visibility repair did not finish.' );
	}

	private function legacy_upgrade() {
		global $wp_roles;

		// The 2.2 migration removes roles in memory as well as in the database
		$saved    = wp_roles();
		$user_id  = get_current_user_id();
		$wp_roles = clone $saved;
		foreach ( $wp_roles->role_objects as $key => $role ) {
			$wp_roles->role_objects[ $key ] = clone $role;
		}
		try {
			update_option( '_bbp_db_version', 210 );
			bbp_version_updater();
		} finally {
			$wp_roles = $saved;
			wp_set_current_user( 0 );
			wp_set_current_user( $user_id );
		}
	}

	/** @dataProvider sizes */
	public function test_previews_match_bounded_scans_and_writes_without_publishing_partial_lists( $size ) {
		list( $private, $hidden ) = $this->fixture();
		$this->size = $size;
		$writes = 0;
		$count = static function ( $option ) use ( &$writes ) {
			if ( in_array( $option, array( '_bbp_private_forums', '_bbp_hidden_forums' ), true ) ) {
				++$writes;
			}
		};
		$queries = array();
		$capture = static function ( $query ) use ( &$queries ) {
			if ( 0 === strpos( $query, 'SELECT ID FROM ' ) ) {
				$queries[] = $query;
			}
			return $query;
		};
		add_action( 'updated_option', $count );
		add_filter( 'query', $capture );
		$totals = array( 'private' => 0, 'hidden' => 0 );
		try {
			for ( $i = 0; $i < 100; ++$i ) {
				$before = $this->snapshot();
				$preview = bbp_admin_repair_forum_visibility_batch( true );
				$this->assertSame( $before, $this->snapshot() );
				$writes = 0;
				$run = bbp_admin_repair_forum_visibility_batch();
				$this->assertTrue( $preview['complete'] );
			$this->assertSame( 0, $preview['code'] );
				$this->assertSame( 0, $run['code'] );
				$this->assertLessThanOrEqual( $size, $writes );
				$this->assertLessThanOrEqual( $size, $run['checked'] );
				foreach ( $totals as $type => $total ) {
					$totals[ $type ] += $run[ $type ];
				}
				$state = get_option( $this->option(), false );
				if ( $state && $state['phase'] <= 2 ) {
					$this->assertSame( array( 999999 ), get_option( '_bbp_private_forums' ) );
					$this->assertSame( 'malformed', get_option( '_bbp_hidden_forums' ) );
				}
				if ( $run['complete'] ) {
					break;
				}
			}
		} finally {
			remove_action( 'updated_option', $count );
			remove_filter( 'query', $capture );
		}
		$this->assertTrue( $run['complete'] );
		$this->assertSame( array( 'private' => 4, 'hidden' => 2 ), $totals );
		$this->assertSame( $private, get_option( '_bbp_private_forums' ) );
		$this->assertSame( $hidden, get_option( '_bbp_hidden_forums' ) );
		$this->assertNotEmpty( $queries );
		foreach ( $queries as $query ) {
			$this->assertStringContainsString( 'LIMIT ', $query );
			$this->assertMatchesRegularExpression( '/LIMIT [1-9][0-9]*$/', $query );
		}
		$this->assertFalse( get_option( $this->option(), false ) );
		$this->size = 1000;
		$before = $this->snapshot();
		$result = bbp_admin_repair_forum_visibility();
		$this->assertSame( 1, $result[0] );
		$this->assertTrue( $result['complete'] );
		$this->assertSame( $before, $this->snapshot() );
		$this->assertSame( 1, bbp_admin_repair_forum_visibility_dry_run()[0] );
	}

	/** @dataProvider malformed */
	public function test_malformed_progress_restarts_safely_and_preview_never_saves_it( $field, $value ) {
		$this->fixture();
		bbp_admin_repair_forum_visibility_batch();
		$state = get_option( $this->option() );
		$state[ $field ] = 'past-bound' === $value ? $state['upper_id'] + 1 : $value;
		update_option( $this->option(), $state );
		$before = $this->snapshot();
		$this->assertFalse( bbp_admin_repair_forum_visibility_batch( true )['restarted'] );
		$this->assertSame( $before, $this->snapshot() );
		$this->assertTrue( bbp_admin_repair_forum_visibility_batch()['restarted'] );
		$this->finish();
	}

	public function test_source_option_changes_restart_the_staged_scan() {
		list( $private, $hidden ) = $this->fixture();
		bbp_admin_repair_forum_visibility_batch();
		update_option( '_bbp_private_forums', array() );
		$this->assertTrue( bbp_admin_repair_forum_visibility_batch()['restarted'] );
		$this->finish();
		$this->assertSame( $private, get_option( '_bbp_private_forums' ) );
		$this->assertSame( $hidden, get_option( '_bbp_hidden_forums' ) );
	}

	public function test_successful_checkpoint_deletions_do_not_reload_autoloaded_options() {
		$names = array();
		for ( $i = 0; $i < 3; ++$i ) {
			$names[] = '_bbp_visibility_delete_test_' . $i;
			add_option( $names[ $i ], array( 'scratch' => $i ), '', false );
		}
		wp_load_alloptions();
		get_option( 'bbp_visibility_unrelated_missing_option', false );
		$reads = 0;
		$db = bbp_db();
		$capture = static function ( $query ) use ( $db, &$reads ) {
			if ( 0 === strpos( $query, 'SELECT option_name, option_value FROM ' . $db->options . ' WHERE autoload' ) ) {
				++$reads;
			}
			return $query;
		};
		add_filter( 'query', $capture );
		try {
			foreach ( $names as $name ) {
				$this->assertTrue( bbp_admin_repair_tool_save_option( $name, array(), true ) );
			}
			$this->assertSame( 0, $reads );
			$this->assertIsArray( wp_cache_get( 'alloptions', 'options' ) );
			$this->assertArrayHasKey( 'bbp_visibility_unrelated_missing_option', wp_cache_get( 'notoptions', 'options' ) );
		} finally {
			remove_filter( 'query', $capture );
		}
	}

	/** @dataProvider visibility_transitions */
	public function test_native_visibility_changes_with_unchanged_option_arrays_restart_the_scan( $type, $callback ) {
		$earlier = $this->factory->forum->create();
		bbp_db()->update( bbp_db()->posts, array( 'post_modified_gmt' => '2000-01-01 00:00:00' ), array( 'ID' => $earlier ) );
		clean_post_cache( $earlier );
		$status = 'private' === $type ? bbp_get_private_status_id() : bbp_get_hidden_status_id();
		$private = $this->factory->forum->create_many( 2, array( 'post_status' => bbp_get_private_status_id() ) );
		$hidden = $this->factory->forum->create_many( 2, array( 'post_status' => bbp_get_hidden_status_id() ) );
		$option = '_bbp_' . $type . '_forums';
		update_option( '_bbp_private_forums', array() );
		update_option( '_bbp_hidden_forums', array() );
		update_option( $option, array( $earlier ) );
		for ( $i = 0; $i < 20; ++$i ) {
			bbp_admin_repair_forum_visibility_batch();
			if ( 2 === get_option( $this->option() )['phase'] ) {
				break;
			}
		}
		$this->assertSame( 2, get_option( $this->option() )['phase'] );
		$before = get_option( $option );
		call_user_func( $callback, $earlier, bbp_get_public_status_id() );
		$this->assertSame( $status, get_post_status( $earlier ) );
		$this->assertSame( $before, get_option( $option ) );
		$this->assertTrue( bbp_admin_repair_forum_visibility_batch()['restarted'] );
		$this->finish();
		$expected = array_merge( array( $earlier ), 'private' === $type ? $private : $hidden );
		$this->assertSame( $expected, get_option( $option ) );
	}

	/** @dataProvider membership_changes */
	public function test_wordpress_membership_changes_invalidate_staged_lists( $change ) {
		$earlier = 'enter' === $change
			? $this->factory->post->create( array( 'post_status' => bbp_get_private_status_id() ) )
			: $this->factory->forum->create( array( 'post_status' => bbp_get_private_status_id() ) );
		$this->factory->forum->create_many( 2, array( 'post_status' => bbp_get_private_status_id() ) );
		update_option( '_bbp_private_forums', array() );
		update_option( '_bbp_hidden_forums', array() );
		bbp_admin_repair_forum_visibility_batch();
		$version = get_option( '_bbp_forum_visibility_version', '' );
		switch ( $change ) {
			case 'status':
				wp_update_post( array( 'ID' => $earlier, 'post_status' => bbp_get_hidden_status_id() ) );
				break;
			case 'enter':
				wp_update_post( array( 'ID' => $earlier, 'post_type' => bbp_get_forum_post_type() ) );
				break;
			case 'leave':
				wp_update_post( array( 'ID' => $earlier, 'post_type' => 'post' ) );
				break;
			case 'delete':
				wp_delete_post( $earlier, true );
				break;
		}
		$this->assertNotSame( $version, get_option( '_bbp_forum_visibility_version', '' ) );
		$this->assertTrue( bbp_admin_repair_forum_visibility_batch()['restarted'] );
		$this->finish();
		$this->assertSame( 'enter' === $change, in_array( $earlier, get_option( '_bbp_private_forums' ), true ) );
		$this->assertSame( 'status' === $change, in_array( $earlier, get_option( '_bbp_hidden_forums' ), true ) );
	}

	public function test_title_edits_and_non_forum_changes_do_not_invalidate_the_scan() {
		$forum = $this->factory->forum->create();
		$version = get_option( '_bbp_forum_visibility_version', '' );
		wp_update_post( array( 'ID' => $forum, 'post_title' => 'Changed title' ) );
		$post = $this->factory->post->create();
		wp_update_post( array( 'ID' => $post, 'post_status' => 'private' ) );
		$this->factory->topic->create();
		$this->assertSame( $version, get_option( '_bbp_forum_visibility_version', '' ) );
	}

	/** @dataProvider publication_hooks */
	public function test_source_changes_during_publication_do_not_complete_with_stale_lists( $published, $target, $callback ) {
		$earlier = $this->factory->forum->create();
		$private = $this->factory->forum->create_many( 2, array( 'post_status' => bbp_get_private_status_id() ) );
		$hidden = $this->factory->forum->create_many( 2, array( 'post_status' => bbp_get_hidden_status_id() ) );
		update_option( '_bbp_private_forums', array() );
		update_option( '_bbp_hidden_forums', array() );
		update_option( '_bbp_' . $target . '_forums', array( $earlier ) );
		$this->size = 1000;
		$fired = false;
		$change = static function ( $option ) use ( $earlier, $published, $callback, &$fired ) {
			if ( ! $fired && '_bbp_' . $published . '_forums' === $option ) {
				$fired = true;
				call_user_func( $callback, $earlier, bbp_get_public_status_id() );
			}
		};
		add_action( 'updated_option', $change );
		try {
			$result = bbp_admin_repair_forum_visibility_batch();
			$this->assertTrue( $fired );
			$this->assertSame( 0, $result['code'] );
			$this->assertTrue( $result['restarted'] );
			$this->assertFalse( $result['complete'] );
			$this->assertContains( $earlier, get_option( '_bbp_' . $target . '_forums' ) );
		} finally {
			remove_action( 'updated_option', $change );
		}
		$this->finish();
		$actual = get_option( '_bbp_' . $target . '_forums' );
		sort( $actual );
		$this->assertSame( array_merge( array( $earlier ), 'private' === $target ? $private : $hidden ), $actual );
	}

	public function test_stale_missing_cache_does_not_change_existing_autoload_settings() {
		$this->fixture();
		$option = '_bbp_private_forums';
		$value = array( 123 );
		update_option( $option, array( 999998 ), false );
		$db = bbp_db();
		$autoload = $db->get_var( $db->prepare( "SELECT autoload FROM {$db->options} WHERE option_name = %s", $option ) );
		$missing = (array) wp_cache_get( 'notoptions', 'options' );
		$missing[ $option ] = true;
		wp_cache_set( 'notoptions', $missing, 'options' );
		wp_cache_delete( $option, 'options' );
		$this->assertTrue( bbp_admin_repair_update_option( $option, $value ) );
		$this->assertSame( $value, get_option( $option ) );
		$this->assertSame( $autoload, $db->get_var( $db->prepare( "SELECT autoload FROM {$db->options} WHERE option_name = %s", $option ) ) );
	}

	public function test_source_version_is_per_site() {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'Requires multisite.' );
		}
		$this->factory->forum->create();
		$version = get_option( '_bbp_forum_visibility_version', '' );
		$blog = $this->factory->blog->create();
		switch_to_blog( $blog );
		try {
			$this->factory->forum->create();
			$this->assertNotSame( $version, get_option( '_bbp_forum_visibility_version', '' ) );
		} finally {
			restore_current_blog();
		}
		$this->assertSame( $version, get_option( '_bbp_forum_visibility_version', '' ) );
	}

	/** @dataProvider version_read_failures */
	public function test_source_version_read_errors_never_report_completion( $point ) {
		$this->fixture();
		$this->size = 1000;
		$db = bbp_db();
		$old_suppression = $db->suppress_errors( true );
		$reads = 0;
		$fail = static function ( $query ) use ( $point, &$reads ) {
			if ( false !== strpos( $query, "`option_name` = '_bbp_forum_visibility_version'" ) && 0 === strpos( $query, 'SELECT ' ) ) {
				++$reads;
				if ( $point === $reads ) {
					return 'SELECT * FROM bbp_missing_visibility_version';
				}
			}
			return $query;
		};
		add_filter( 'query', $fail );
		try {
			$result = bbp_admin_repair_forum_visibility_batch();
			$this->assertSame( $point, $reads );
			$this->assertSame( BBP_Admin_Batch_Worker::RESULT_FAILED, $result['code'] );
			$this->assertSame( 'source-read', $result['error'] );
			$this->assertFalse( $result['complete'] );
		} finally {
			remove_filter( 'query', $fail );
			$db->suppress_errors( $old_suppression );
		}
		$this->finish();
	}

	public function test_failed_checkpoint_delete_recovers_from_a_preloaded_missing_cache_entry() {
		$option = $this->option();
		$value = array( 'saved' => 'progress' );
		add_option( $option, $value, '', false );
		$missing = (array) wp_cache_get( 'notoptions', 'options' );
		$missing[ $option ] = true;
		wp_cache_set( 'notoptions', $missing, 'options' );
		$db = bbp_db();
		$fired = false;
		$fail = static function ( $query ) use ( $db, $option, &$fired ) {
			if ( 0 === strpos( $query, "DELETE FROM `{$db->options}`" ) && false !== strpos( $query, $option ) ) {
				$fired = true;
				return 'SELECT 1';
			}
			return $query;
		};
		add_filter( 'query', $fail );
		try {
			$this->assertFalse( bbp_admin_repair_tool_save_option( $option, array(), true ) );
			$this->assertTrue( $fired );
			$this->assertSame( $value, get_option( $option ) );
		} finally {
			remove_filter( 'query', $fail );
		}
		$this->assertTrue( bbp_admin_repair_tool_save_option( $option, array(), true ) );
		$this->assertFalse( get_option( $option, false ) );
	}

	public function test_already_deleted_checkpoint_clears_a_stale_positive_cache_entry() {
		$option = $this->option();
		$value = array( 'saved' => 'progress' );
		add_option( $option, $value, '', false );
		$this->assertSame( $value, get_option( $option ) );
		$db = bbp_db();
		$db->delete( $db->options, array( 'option_name' => $option ) );
		$this->assertSame( $value, get_option( $option ) );
		$this->assertTrue( bbp_admin_repair_tool_save_option( $option, array(), true ) );
		$this->assertFalse( get_option( $option, false ) );
	}

	/** @dataProvider partial_preview_sizes */
	public function test_full_dry_run_counts_both_visibility_lists_at_every_batch_size( $size ) {
		$this->fixture();
		$this->size = $size;
		$before = $this->snapshot();
		$result = bbp_admin_repair_forum_visibility_dry_run();
		$this->assertTrue( $result['complete'] );
		$this->assertStringNotContainsString( 'Visibility counts are not complete yet.', $result[1] );
		$this->assertStringNotContainsString( '0 hidden forum visibility entries', $result[1] );
		$this->assertStringNotContainsString( '0 private forum visibility entries', $result[1] );
		if ( 6 === $size ) {
			$this->assertStringContainsString( '4 private forum visibility entries would be repaired.', $result[1] );
		}
		$this->assertSame( $before, $this->snapshot() );
	}

	public function test_unwritable_source_version_stops_publication_until_storage_recovers() {
		$this->fixture();
		delete_option( '_bbp_forum_visibility_version' );
		$reject = static function ( $new, $old ) { return $old; };
		add_filter( 'pre_update_option__bbp_forum_visibility_version', $reject, 10, 2 );
		try {
			$result = bbp_admin_repair_forum_visibility_batch();
			$this->assertSame( BBP_Admin_Batch_Worker::RESULT_FAILED, $result['code'] );
			$this->assertSame( 'metadata-write', $result['error'] );
			$this->assertFalse( $result['complete'] );
			$this->assertSame( array( 999999 ), get_option( '_bbp_private_forums' ) );
			$this->assertSame( 'malformed', get_option( '_bbp_hidden_forums' ) );
		} finally {
			remove_filter( 'pre_update_option__bbp_forum_visibility_version', $reject, 10 );
		}
		$this->assertTrue( bbp_admin_repair_tool_run_automatically( 'bbp-sync-forum-visibility', 'bbp_admin_repair_forum_visibility' ) );
	}

	public function test_failed_source_stamp_invalidates_the_old_scan_before_retry() {
		$earlier = $this->factory->forum->create();
		$this->factory->forum->create_many( 2, array( 'post_status' => bbp_get_private_status_id() ) );
		update_option( '_bbp_private_forums', array( $earlier ) );
		update_option( '_bbp_hidden_forums', array() );
		bbp_admin_repair_forum_visibility_batch();
		$reject = static function ( $new, $old ) { return $old; };
		add_filter( 'pre_update_option__bbp_forum_visibility_version', $reject, 10, 2 );
		try {
			bbp_privatize_forum( $earlier, bbp_get_public_status_id() );
			$result = bbp_admin_repair_forum_visibility_batch();
			$this->assertSame( BBP_Admin_Batch_Worker::RESULT_FAILED, $result['code'] );
			$this->assertSame( 'metadata-write', $result['error'] );
			$this->assertFalse( $result['complete'] );
		} finally {
			remove_filter( 'pre_update_option__bbp_forum_visibility_version', $reject, 10 );
		}
		$this->assertTrue( bbp_admin_repair_forum_visibility_batch()['restarted'] );
		$this->finish();
		$this->assertContains( $earlier, get_option( '_bbp_private_forums' ) );
	}

	public function test_rejected_checkpoint_saves_do_not_publish_partial_lists_and_retry_automatically() {
		list( $private, $hidden ) = $this->fixture();
		$reject = static function ( $new, $old ) { return $old; };
		add_filter( 'pre_update_option_' . $this->option(), $reject, 10, 2 );
		try {
			$result = bbp_admin_repair_forum_visibility_batch();
			$this->assertSame( BBP_Admin_Batch_Worker::RESULT_FAILED, $result['code'] );
			$this->assertSame( 'progress', $result['error'] );
			$this->assertFalse( $result['complete'] );
			$this->assertSame( array( 999999 ), get_option( '_bbp_private_forums' ) );
			$this->assertSame( 'malformed', get_option( '_bbp_hidden_forums' ) );
		} finally {
			remove_filter( 'pre_update_option_' . $this->option(), $reject, 10 );
		}
		$this->assertTrue( bbp_admin_repair_tool_run_automatically( 'bbp-sync-forum-visibility', 'bbp_admin_repair_forum_visibility' ) );
		$this->assertSame( $private, get_option( '_bbp_private_forums' ) );
		$this->assertSame( $hidden, get_option( '_bbp_hidden_forums' ) );
	}

	public function test_false_successful_visibility_writes_fail_verification_and_can_retry() {
		$this->fixture();
		$this->size = 1000;
		$db = bbp_db();
		$fired = false;
		$fail = static function ( $query ) use ( $db, &$fired ) {
			if ( 0 === strpos( $query, "UPDATE `{$db->options}`" ) && false !== strpos( $query, "'_bbp_private_forums'" ) ) {
				$fired = true;
				return 'SELECT 1';
			}
			return $query;
		};
		add_filter( 'query', $fail );
		try {
			$result = bbp_admin_repair_forum_visibility_batch();
			$this->assertSame( BBP_Admin_Batch_Worker::RESULT_FAILED, $result['code'] );
			$this->assertSame( 'metadata-write', $result['error'] );
			$this->assertTrue( $fired );
			$this->assertSame( array( 999999 ), get_option( '_bbp_private_forums' ) );
		} finally {
			remove_filter( 'query', $fail );
		}
		$this->finish();
	}

	public function test_verified_publication_survives_a_rejected_progress_save() {
		list( $private, $hidden ) = $this->fixture();
		for ( $i = 0; $i < 20; ++$i ) {
			bbp_admin_repair_forum_visibility_batch();
			if ( 2 === get_option( $this->option() )['phase'] ) {
				break;
			}
		}
		$this->assertSame( 2, get_option( $this->option() )['phase'] );
		$reject = static function ( $new, $old ) { return $old; };
		add_filter( 'pre_update_option_' . $this->option(), $reject, 10, 2 );
		try {
			$result = bbp_admin_repair_forum_visibility_batch();
			$this->assertSame( BBP_Admin_Batch_Worker::RESULT_FAILED, $result['code'] );
			$this->assertSame( 'progress', $result['error'] );
			$this->assertSame( $private, get_option( '_bbp_private_forums' ) );
			$this->assertSame( 'malformed', get_option( '_bbp_hidden_forums' ) );
		} finally {
			remove_filter( 'pre_update_option_' . $this->option(), $reject, 10 );
		}
		$retry = bbp_admin_repair_forum_visibility_batch();
		$this->assertSame( 0, $retry['code'] );
		$this->assertFalse( $retry['restarted'] );
		$this->assertSame( 0, $retry['private'] );
		$this->finish();
		$this->assertSame( $hidden, get_option( '_bbp_hidden_forums' ) );
	}

	public function test_final_checkpoint_deletion_is_verified_and_published_lists_survive_retry() {
		list( $private, $hidden ) = $this->fixture();
		for ( $i = 0; $i < 20; ++$i ) {
			bbp_admin_repair_forum_visibility_batch();
			if ( 3 === get_option( $this->option() )['phase'] ) {
				break;
			}
		}
		$this->assertSame( 3, get_option( $this->option() )['phase'] );
		$db = bbp_db();
		$option = $this->option();
		$fired = false;
		$fail = static function ( $query ) use ( $db, $option, &$fired ) {
			if ( 0 === strpos( $query, "DELETE FROM `{$db->options}`" ) && false !== strpos( $query, $option ) ) {
				$fired = true;
				return 'SELECT 1';
			}
			return $query;
		};
		add_filter( 'query', $fail );
		try {
			$result = bbp_admin_repair_forum_visibility_batch();
			$this->assertTrue( $fired );
			$this->assertSame( BBP_Admin_Batch_Worker::RESULT_FAILED, $result['code'] );
			$this->assertSame( 'progress', $result['error'] );
			$this->assertFalse( $result['complete'] );
			$this->assertSame( $private, get_option( '_bbp_private_forums' ) );
			$this->assertSame( $hidden, get_option( '_bbp_hidden_forums' ) );
		} finally {
			remove_filter( 'query', $fail );
		}
		$this->finish();
	}

	public function test_filtered_option_values_do_not_restart_our_own_verified_publication() {
		list( $private, $hidden ) = $this->fixture();
		$filter = static function ( $value ) { return array_merge( (array) $value, array( 999999 ) ); };
		add_filter( 'option__bbp_private_forums', $filter );
		try {
			for ( $i = 0; $i < 30; ++$i ) {
				$result = bbp_admin_repair_forum_visibility_batch();
				$this->assertSame( 0, $result['code'] );
				$this->assertFalse( $result['restarted'] );
				if ( $result['complete'] ) {
					break;
				}
			}
			$this->assertTrue( $result['complete'] );
			$this->assertTrue( bbp_admin_repair_option_matches( '_bbp_private_forums', $private ) );
		} finally {
			remove_filter( 'option__bbp_private_forums', $filter );
		}
		$this->assertSame( $private, get_option( '_bbp_private_forums' ) );
		$this->assertSame( $hidden, get_option( '_bbp_hidden_forums' ) );
	}

	/** @dataProvider sizes */
	public function test_legacy_version_updater_drains_visibility_batches( $size ) {
		list( $private, $hidden ) = $this->fixture();
		$this->size = $size;
		$this->legacy_upgrade();
		$this->assertSame( $private, get_option( '_bbp_private_forums' ) );
		$this->assertSame( $hidden, get_option( '_bbp_hidden_forums' ) );
		$this->assertFalse( get_option( $this->option(), false ) );
		$this->assertNotContains( 'bbp-sync-forum-visibility', bbp_get_pending_upgrades() );
	}

	public function test_legacy_automatic_budget_exhaustion_keeps_visibility_pending_until_retry() {
		$this->fixture();
		$limit = static function ( $value, $tool ) { return 'bbp-sync-forum-visibility' === $tool ? 1 : $value; };
		add_filter( 'bbp_admin_repair_tool_automatic_batch_limit', $limit, 10, 2 );
		try {
			$this->legacy_upgrade();
			$this->assertContains( 'bbp-sync-forum-visibility', bbp_get_pending_upgrades() );
			$this->assertNotFalse( get_option( $this->option(), false ) );
			$this->assertSame( array( 999999 ), get_option( '_bbp_private_forums' ) );
		} finally {
			remove_filter( 'bbp_admin_repair_tool_automatic_batch_limit', $limit, 10 );
		}
		$this->assertTrue( bbp_admin_repair_tool_run_automatically( 'bbp-sync-forum-visibility', 'bbp_admin_repair_forum_visibility' ) );
		$this->assertNotContains( 'bbp-sync-forum-visibility', bbp_get_pending_upgrades() );
	}
}
