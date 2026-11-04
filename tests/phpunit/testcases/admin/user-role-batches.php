<?php

/**
 * Bounded forum role remapping.
 *
 * @group tools
 * @group tool-batches
 */
class BBP_Tests_Admin_User_Role_Batches extends BBP_UnitTestCase {

	private $size = 1;
	private $role_order = array( 'subscriber' );
	private $targets = array();

	public function setUp(): void {
		parent::setUp();
		if ( ! function_exists( 'bbp_admin' ) ) {
			require_once BBP_PLUGIN_DIR . 'includes/admin/actions.php';
		}
		bbp_admin();
		add_filter( 'bbp_admin_repair_tool_batch_size', array( $this, 'batch_size' ) );
		add_filter( 'bbp_get_blog_roles', array( $this, 'blog_roles' ) );
		add_filter( 'bbp_get_user_role_map', array( $this, 'role_map' ) );
	}

	public function tearDown(): void {
		remove_filter( 'bbp_admin_repair_tool_batch_size', array( $this, 'batch_size' ) );
		remove_filter( 'bbp_get_blog_roles', array( $this, 'blog_roles' ) );
		remove_filter( 'bbp_get_user_role_map', array( $this, 'role_map' ) );
		parent::tearDown();
	}

	public function batch_size() {
		return $this->size;
	}

	public function blog_roles( $roles ) {
		$selected = array();
		foreach ( $this->role_order as $role ) {
			$selected[ $role ] = $roles[ $role ];
		}
		return $selected;
	}

	public function role_map( $map ) {
		return array_merge( $map, $this->targets );
	}

	public function boundaries() {
		return array( array( 1 ), array( 1000 ) );
	}

	public function failed_writes() {
		return array( array( false ), array( true ) );
	}

	public function checkpoint_failures() {
		return array( array( 'initial' ), array( 'progress' ), array( 'final' ) );
	}

	public function read_failures() {
		$cases = array();
		foreach ( array( 'upper', 'selection', 'user', 'capabilities', 'verification' ) as $point ) {
			$cases[] = array( $point, false );
			if ( 'verification' !== $point ) {
				$cases[] = array( $point, true );
			}
		}
		return $cases;
	}

	public function invalid_user_results() {
		return array( array( 'cursor' ), array( 'upper' ), array( 'order' ) );
	}

	public function duplicate_sources() {
		return array( array( false ), array( true ) );
	}

	private function option() {
		return bbp_admin_repair_tool_batch_option_name( 'bbp-user-role-map' );
	}

	private function fixture( $count = 3 ) {
		$ids = $this->factory->user->create_many( $count, array( 'role' => 'subscriber' ) );
		foreach ( $ids as $id ) {
			bbp_set_user_role( $id, bbp_get_spectator_role() );
			get_userdata( $id )->add_cap( 'bbp_test_preserved_cap' );
		}
		return $ids;
	}

	private function snapshot() {
		$db = bbp_db();
		return array(
			$db->get_results( "SELECT * FROM {$db->usermeta} ORDER BY umeta_id", ARRAY_A ),
			$db->get_results( "SELECT * FROM {$db->options} ORDER BY option_id", ARRAY_A )
		);
	}

	private function finish() {
		for ( $i = 0; $i < 100; ++$i ) {
			$batch = bbp_admin_repair_user_roles_batch();
			$this->assertSame( 0, $batch['code'] );
			if ( $batch['complete'] ) {
				$this->assertFalse( get_option( $this->option(), false ) );
				return;
			}
		}
		$this->fail( 'Role repair did not complete.' );
	}

	private function assert_roles( $ids ) {
		foreach ( $ids as $id ) {
			$this->assertSame( bbp_get_default_role(), bbp_get_user_role( $id ) );
			$this->assertContains( 'subscriber', get_userdata( $id )->roles );
			$this->assertTrue( get_userdata( $id )->has_cap( 'bbp_test_preserved_cap' ) );
		}
	}

	/** @dataProvider boundaries */
	public function test_previews_match_bounded_assignments_and_completed_reruns_are_noops( $size ) {
		$ids = $this->fixture();
		$this->size = $size;
		$calls = 0;
		$transitions = 0;
		$count = static function ( $role ) use ( &$calls ) { ++$calls; return $role; };
		$transition = static function () use ( &$transitions ) { ++$transitions; };
		$bound = function ( $query ) use ( $size ) {
			$this->assertLessThanOrEqual( $size + 1, $query->query_vars['number'] );
			$this->assertSame( 0, $query->query_vars['offset'] );
			$this->assertStringContainsString( bbp_db()->users . '.ID > ', $query->query_where );
		};
		add_filter( 'bbp_set_user_role', $count );
		add_action( 'bbp_transition_user_role', $transition );
		add_action( 'pre_user_query', $bound, 20 );
		try {
			for ( $i = 0; $i < 100; ++$i ) {
				$before = $this->snapshot();
				$calls = 0;
				$preview = bbp_admin_repair_user_roles_batch( true );
				$this->assertSame( $before, $this->snapshot() );
				$this->assertSame( 0, $calls );
				$batch = bbp_admin_repair_user_roles_batch();
				$this->assertSame( $preview, $batch );
				$this->assertLessThanOrEqual( $size, $calls );
				if ( $batch['complete'] ) {
					break;
				}
			}
			$this->assertTrue( $batch['complete'] );
			$this->assertSame( 3, $transitions );
			$this->assert_roles( $ids );
			$this->finish();
			$this->assertSame( 3, $transitions );
		} finally {
			remove_filter( 'bbp_set_user_role', $count );
			remove_action( 'bbp_transition_user_role', $transition );
			remove_action( 'pre_user_query', $bound, 20 );
		}
	}

	/** @dataProvider failed_writes */
	public function test_failed_or_falsely_successful_capability_writes_remain_retryable( $reported ) {
		$ids = $this->fixture();
		$this->size = 1000;
		$key = bbp_db()->get_blog_prefix() . 'capabilities';
		$fail = static function ( $check, $id, $meta_key ) use ( $ids, $key, $reported ) { return $ids[1] === $id && $key === $meta_key ? $reported : $check; };
		add_filter( 'update_user_metadata', $fail, 10, 3 );
		try {
			$result = bbp_admin_repair_user_roles();
			$this->assertSame( 3, $result[0] );
			$this->assertFalse( $result['success'] );
			$this->assertFalse( $result['complete'] );
			$this->assertSame( bbp_get_default_role(), bbp_get_user_role( $ids[0] ) );
			$this->assertSame( bbp_get_spectator_role(), bbp_get_user_role( $ids[1] ) );
			$this->assertFalse( bbp_admin_repair_tool_run_automatically( 'bbp-user-role-map', 'bbp_admin_repair_user_roles' ) );
			$this->assertContains( 'bbp-user-role-map', bbp_get_pending_upgrades() );
		} finally {
			remove_filter( 'update_user_metadata', $fail, 10 );
		}
		$this->assertTrue( bbp_admin_repair_tool_run_automatically( 'bbp-user-role-map', 'bbp_admin_repair_user_roles' ) );
		$this->assertNotContains( 'bbp-user-role-map', bbp_get_pending_upgrades() );
		$this->assert_roles( $ids );
	}

	/** @dataProvider checkpoint_failures */
	public function test_checkpoint_failures_do_not_skip_users_or_falsely_complete( $phase ) {
		$db = bbp_db();
		$ids = $this->fixture();
		$option = $this->option();
		if ( 'final' === $phase ) {
			$this->size = 1000;
		}
		$save = static function ( $value, $old ) use ( $phase ) { return 'initial' === $phase || $value['cursor'] ? $old : $value; };
		$delete = static function ( $query ) use ( $db, $option ) { return 0 === strpos( $query, 'DELETE FROM' ) && false !== strpos( $query, $db->options ) && false !== strpos( $query, "'{$option}'" ) ? 'DELETE FROM bbp_missing_role_options' : $query; };
		$suppression = $db->suppress_errors( true );
		if ( 'final' === $phase ) {
			add_filter( 'query', $delete );
		} else {
			add_filter( 'pre_update_option_' . $option, $save, 10, 2 );
		}
		try {
			$result = bbp_admin_repair_user_roles();
			$this->assertSame( 4, $result[0] );
			$this->assertFalse( $result['success'] );
			$this->assertFalse( $result['complete'] );
			$this->assertSame( 'initial' === $phase ? bbp_get_spectator_role() : bbp_get_default_role(), bbp_get_user_role( $ids[0] ) );
		} finally {
			remove_filter( 'pre_update_option_' . $option, $save, 10 );
			remove_filter( 'query', $delete );
			$db->suppress_errors( $suppression );
		}
		$this->finish();
		$this->assert_roles( $ids );
	}

	public function test_changed_or_deleted_users_and_higher_ids_do_not_skip_later_users() {
		$ids = $this->fixture();
		bbp_admin_repair_user_roles_batch();
		wp_delete_user( $ids[1] );
		$new = $this->fixture( 1 );
		$this->finish();
		$this->assert_roles( array( $ids[0], $ids[2] ) );
		$this->assertSame( bbp_get_spectator_role(), bbp_get_user_role( $new[0] ) );
		$this->finish();
		$this->assert_roles( $new );
	}

	public function test_changed_maps_and_malformed_phases_restart_without_preview_writes() {
		$ids = $this->fixture();
		bbp_admin_repair_user_roles_batch();
		$this->targets['subscriber'] = bbp_get_moderator_role();
		$before = $this->snapshot();
		$preview = bbp_admin_repair_user_roles_batch( true );
		$this->assertTrue( $preview['restarted'] );
		$this->assertSame( $before, $this->snapshot() );
		$this->assertSame( $preview, bbp_admin_repair_user_roles_batch() );
		$state = get_option( $this->option() );
		$state['phase'] = 99999;
		update_option( $this->option(), $state, false );
		$this->assertTrue( bbp_admin_repair_user_roles_batch( true )['restarted'] );
		$this->finish();
		foreach ( $ids as $id ) {
			$this->assertSame( bbp_get_moderator_role(), bbp_get_user_role( $id ) );
		}
	}

	public function test_multiple_blog_role_passes_preserve_filtered_order_and_blocked_exclusivity() {
		$this->role_order = array( 'editor', 'subscriber' );
		$this->targets['editor'] = bbp_get_blocked_role();
		$user_id = $this->factory->user->create( array( 'role' => 'editor' ) );
		$user = get_userdata( $user_id );
		$user->add_role( 'subscriber' );
		bbp_set_user_role( $user_id, bbp_get_moderator_role() );
		$this->size = 1000;
		$preview = bbp_admin_repair_user_roles_batch( true );
		$this->assertSame( 2, $preview['changed'] );
		$this->assertSame( $preview, bbp_admin_repair_user_roles_batch() );
		$this->assertSame( bbp_get_default_role(), bbp_get_user_role( $user_id ) );
		$this->role_order = array( 'subscriber', 'editor' );
		$this->finish();
		$this->assertSame( array( bbp_get_blocked_role() ), array_values( array_intersect( get_userdata( $user_id )->roles, array_keys( bbp_get_dynamic_roles() ) ) ) );
	}

	public function test_automatic_budget_exhaustion_retains_progress_and_finishes_on_retry() {
		$ids = $this->fixture();
		add_filter( 'bbp_admin_repair_tool_automatic_batch_limit', '__return_true' );
		try {
			$this->assertFalse( bbp_admin_repair_tool_run_automatically( 'bbp-user-role-map', 'bbp_admin_repair_user_roles' ) );
			$this->assertContains( 'bbp-user-role-map', bbp_get_pending_upgrades() );
			$this->assertSame( bbp_get_spectator_role(), bbp_get_user_role( $ids[1] ) );
		} finally {
			remove_filter( 'bbp_admin_repair_tool_automatic_batch_limit', '__return_true' );
		}
		$this->assertTrue( bbp_admin_repair_tool_run_automatically( 'bbp-user-role-map', 'bbp_admin_repair_user_roles' ) );
		$this->assertNotContains( 'bbp-user-role-map', bbp_get_pending_upgrades() );
		$this->assert_roles( $ids );
	}

	public function test_roleless_fallback_is_bounded_and_respects_multisite_membership() {
		$ids = $this->fixture( 4 );
		$this->role_order = array();
		$key = bbp_db()->get_blog_prefix() . 'capabilities';
		foreach ( $ids as $id ) {
			if ( is_multisite() ) {
				update_user_meta( $id, $key, array() );
			} else {
				delete_user_meta( $id, $key );
			}
		}
		$nonmember = $this->factory->user->create();
		delete_user_meta( $nonmember, $key );
		$this->finish();
		foreach ( $ids as $id ) {
			$this->assertSame( bbp_get_default_role(), bbp_get_user_role( $id ) );
		}
		$this->assertSame( is_multisite() ? false : bbp_get_default_role(), bbp_get_user_role( $nonmember ) );
	}

	public function test_user_query_bounds_do_not_leak_into_nested_queries() {
		$ids = $this->fixture();
		$nested = null;
		$read = static function () use ( $ids, &$nested ) {
			if ( null === $nested ) {
				$nested = false;
				$nested = get_users( array( 'include' => array( $ids[2] ), 'fields' => 'ID' ) );
			}
		};
		add_action( 'pre_user_query', $read, 20 );
		try {
			$this->assertSame( array( $ids[1] ), bbp_admin_repair_get_users_batch( array( 'role' => 'subscriber' ), $ids[0], $ids[1], 1 ) );
			$this->assertSame( array( $ids[2] ), array_map( 'intval', $nested ) );
		} finally {
			remove_action( 'pre_user_query', $read, 20 );
		}
	}

	/** @dataProvider duplicate_sources */
	public function test_duplicate_rows_consume_budget_without_skipping_users( $prefilled ) {
		$ids = $this->fixture();
		$this->size = 2;
		$key = bbp_db()->get_blog_prefix() . 'capabilities';
		$caps = get_user_meta( $ids[0], $key, true );
		$duplicates = static function () use ( $ids ) { return array_fill( 0, 3, $ids[0] ); };
		if ( $prefilled ) {
			add_filter( 'users_pre_query', $duplicates );
		} else {
			add_user_meta( $ids[0], $key, $caps );
			add_user_meta( $ids[0], $key, $caps );
		}
		try {
			$matched = 0;
			$this->assertSame( array( $ids[0] ), bbp_admin_repair_get_users_batch( array( 'role' => 'subscriber' ), 0, max( $ids ), 2, $matched ) );
			$this->assertSame( 3, $matched );
			$before = $this->snapshot();
			$preview = bbp_admin_repair_user_roles_batch( true );
			$this->assertSame( $before, $this->snapshot() );
			$this->assertSame( 1, $preview['changed'] );
			$this->assertFalse( $preview['complete'] );
			$this->assertSame( $preview, bbp_admin_repair_user_roles_batch() );
		} finally {
			remove_filter( 'users_pre_query', $duplicates );
		}
		$this->finish();
		$this->assert_roles( $ids );
	}

	public function test_global_user_query_paging_cannot_change_the_batch_window() {
		$ids = $this->fixture();
		$this->size = 2;
		$override = static function ( $query ) {
			$query->query_vars['number'] = 1;
			$query->query_vars['offset'] = 1;
			$query->query_vars['paged'] = 2;
			$query->query_vars['orderby'] = 'login';
			$query->query_vars['order'] = 'DESC';
		};
		add_action( 'pre_get_users', $override );
		try {
			$before = $this->snapshot();
			$preview = bbp_admin_repair_user_roles_batch( true );
			$this->assertSame( 2, $preview['changed'] );
			$this->assertFalse( $preview['complete'] );
			$this->assertSame( $before, $this->snapshot() );
			$this->assertSame( $preview, bbp_admin_repair_user_roles_batch() );
			$this->finish();
			$this->assert_roles( $ids );
		} finally {
			remove_action( 'pre_get_users', $override );
		}
	}

	/** @dataProvider invalid_user_results */
	public function test_short_circuited_queries_cannot_silently_skip_users( $bound ) {
		$ids = $this->fixture();
		bbp_admin_repair_user_roles_batch();
		$state = get_option( $this->option() );
		$rows = array( $ids[0] );
		if ( 'upper' === $bound ) {
			$rows = array( $this->factory->user->create( array( 'role' => 'subscriber' ) ) );
		} elseif ( 'order' === $bound ) {
			$rows = array( $ids[2], $ids[1] );
		}
		$prefill = static function ( $results, $query ) use ( $rows ) {
			return 'subscriber' === $query->query_vars['role'] ? $rows : $results;
		};
		$before = $this->snapshot();
		add_filter( 'users_pre_query', $prefill, 10, 2 );
		try {
			foreach ( array( true, false ) as $dry_run ) {
				$result = bbp_admin_repair_user_roles_process( $dry_run );
				$this->assertSame( 2, $result[0] );
				$this->assertFalse( $result['success'] );
				$this->assertFalse( $result['complete'] );
				$this->assertSame( $state, get_option( $this->option() ) );
				$this->assertSame( $before, $this->snapshot() );
			}
		} finally {
			remove_filter( 'users_pre_query', $prefill, 10 );
		}
		$this->finish();
		$this->assert_roles( $ids );
	}

	public function test_missing_role_map_fails_without_discarding_progress() {
		$ids = $this->fixture();
		bbp_admin_repair_user_roles_batch();
		$state = get_option( $this->option() );
		add_filter( 'bbp_get_user_role_map', '__return_empty_array', 20 );
		try {
			$result = bbp_admin_repair_user_roles_dry_run();
			$this->assertSame( 1, $result[0] );
			$this->assertFalse( $result['success'] );
			$this->assertFalse( $result['complete'] );
			$this->assertFalse( bbp_admin_repair_tool_run_automatically( 'bbp-user-role-map', 'bbp_admin_repair_user_roles' ) );
			$this->assertSame( $state, get_option( $this->option() ) );
			$this->assertContains( 'bbp-user-role-map', bbp_get_pending_upgrades() );
		} finally {
			remove_filter( 'bbp_get_user_role_map', '__return_empty_array', 20 );
		}
		$this->assertTrue( bbp_admin_repair_tool_run_automatically( 'bbp-user-role-map', 'bbp_admin_repair_user_roles' ) );
		$this->assertNotContains( 'bbp-user-role-map', bbp_get_pending_upgrades() );
		$this->assert_roles( $ids );
	}

	/** @dataProvider read_failures */
	public function test_read_failures_keep_the_scan_retryable( $point, $dry_run ) {
		$db = bbp_db();
		$ids = $this->fixture();
		foreach ( $ids as $id ) {
			clean_user_cache( $id );
			// WordPress versions before 6.2 leave user metadata cached here
			wp_cache_delete( $id, 'user_meta' );
		}
		$before = $this->snapshot();
		$fail = static function ( $query ) use ( $db, $point ) {
			$matches = 'upper' === $point && 0 === strpos( $query, 'SELECT MAX(ID) FROM ' . $db->users );
			$matches = $matches || ( 'selection' === $point && false !== strpos( $query, $db->users . '.ID > ' ) );
			$matches = $matches || ( 'user' === $point && 0 === strpos( $query, 'SELECT * FROM ' . $db->users . ' WHERE ID =' ) );
			$matches = $matches || ( 'capabilities' === $point && 0 === strpos( $query, 'SELECT user_id, meta_key, meta_value FROM ' . $db->usermeta ) );
			$matches = $matches || ( 'verification' === $point && 0 === strpos( $query, 'SELECT meta_value FROM ' . $db->usermeta ) );
			return $matches ? 'SELECT * FROM bbp_missing_role_read' : $query;
		};
		$suppress = $db->suppress_errors( true );
		add_filter( 'query', $fail );
		try {
			$result = bbp_admin_repair_user_roles_process( $dry_run );
			$this->assertSame( 2, $result[0] );
			$this->assertFalse( $result['success'] );
			$this->assertFalse( $result['complete'] );
			$this->assertFalse( wp_cache_get( $ids[0], 'user_meta' ) );
		} finally {
			remove_filter( 'query', $fail );
			$db->suppress_errors( $suppress );
		}
		if ( $dry_run ) {
			$this->assertSame( $before, $this->snapshot() );
		}
		$this->finish();
		$this->assert_roles( $ids );
	}

	public function test_partial_role_changes_are_repaired_after_an_add_failure() {
		$ids = $this->fixture( 1 );
		$key = bbp_db()->get_blog_prefix() . 'capabilities';
		$target = bbp_get_default_role();
		$fail = static function ( $check, $id, $meta_key, $caps ) use ( $ids, $key, $target ) {
			return $ids[0] === $id && $key === $meta_key && isset( $caps[ $target ] ) ? false : $check;
		};
		add_filter( 'update_user_metadata', $fail, 10, 4 );
		try {
			$this->assertSame( 3, bbp_admin_repair_user_roles()[0] );
			$this->assertFalse( bbp_get_user_role( $ids[0] ) );
			$this->assertContains( 'subscriber', get_userdata( $ids[0] )->roles );
		} finally {
			remove_filter( 'update_user_metadata', $fail, 10 );
		}
		$this->finish();
		$this->assert_roles( $ids );
	}

	public function test_multisite_mapping_does_not_change_another_sites_roles() {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'Multisite only.' );
		}
		$ids = $this->fixture( 1 );
		$original_key = bbp_db()->get_blog_prefix() . 'capabilities';
		$original = get_user_meta( $ids[0], $original_key, true );
		$blog = $this->factory->blog->create();
		add_user_to_blog( $blog, $ids[0], 'subscriber' );
		switch_to_blog( $blog );
		try {
			$this->finish();
			$this->assertSame( bbp_get_default_role(), bbp_get_user_role( $ids[0] ) );
			$this->assertSame( $original, get_user_meta( $ids[0], $original_key, true ) );
		} finally {
			restore_current_blog();
		}
	}
}
