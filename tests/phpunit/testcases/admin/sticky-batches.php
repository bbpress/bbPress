<?php

/**
 * Resumable sticky assignments, metadata cleanup and automatic execution.
 *
 * @group tools
 * @group tool-batches
 */
class BBP_Tests_Admin_Sticky_Batches extends BBP_UnitTestCase {

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

	public function deletion_failures() {
		return array( array( false ), array( true ) );
	}

	public function malformed_states() {
		return array( array( 'cursor', 'bad' ), array( 'kept', false ), array( 'ready', 'true' ), array( 'source_hash', false ), array( 'offset', -1 ), array( 'current', 'past-bound' ), array( 'length', -1 ), array( 'changed', 8 ) );
	}

	private function option() {
		return bbp_admin_repair_tool_batch_option_name( 'bbp-sync-all-topics-sticky' );
	}

	private function snapshot() {
		$db = bbp_db();
		return array(
			$db->get_results( "SELECT * FROM {$db->postmeta} ORDER BY meta_id", ARRAY_A ),
			$db->get_results( "SELECT * FROM {$db->options} ORDER BY option_id", ARRAY_A )
		);
	}

	private function fixture( $count = 4 ) {
		$first  = $this->factory->forum->create();
		$second = $this->factory->forum->create();
		$topic  = $this->factory->topic->create( array( 'post_parent' => $first ) );
		$super  = $this->factory->topic->create( array( 'post_parent' => $first ) );
		update_option( '_bbp_super_sticky_topics', array( $super ) );
		update_post_meta( $second, '_bbp_sticky_topics', array_merge( array_fill( 0, $count, $topic ), array( (string) $super ) ) );
		return array( $first, $second, $topic, $super );
	}

	private function finish() {
		$changed = 0;
		for ( $i = 0; $i < 200; ++$i ) {
			$result = bbp_admin_repair_sticky_batch();
			$this->assertSame( 0, $result['code'] );
			$changed += $result['repaired'];
			if ( $result['complete'] ) {
				$this->assertFalse( get_option( $this->option(), false ) );
				return $changed;
			}
		}
		$this->fail( 'Sticky repair did not finish.' );
	}

	/** @dataProvider sizes */
	public function test_previews_match_bounded_writes_and_repeat_runs_preserve_canonical_rows( $size ) {
		list( $first, $forum, $topic, $super ) = $this->fixture();
		$keep = add_post_meta( $forum, '_bbp_sticky_topics', array( (string) $super ) );
		for ( $i = 0; $i < 6; ++$i ) {
			add_post_meta( $forum, '_BBP_STICKY_TOPICS', 'duplicate' );
		}
		$this->size = $size;
		$changed    = 0;
		$db         = bbp_db();
		for ( $i = 0; $i < 100; ++$i ) {
			$before  = $this->snapshot();
			$preview = bbp_admin_repair_sticky_batch( true );
			$this->assertSame( $before, $this->snapshot() );
			$run     = bbp_admin_repair_sticky_batch();
			$after   = $this->snapshot();
			$this->assertSame( 0, $run['code'] );
			$this->assertTrue( $preview['complete'] );
			$this->assertGreaterThanOrEqual( $run['repaired'], $preview['repaired'] );
			$this->assertLessThanOrEqual( $size, count( array_diff( array_column( $before[0], 'meta_id' ), array_column( $after[0], 'meta_id' ) ) ) );
			$changed += $run['repaired'];
			if ( $run['complete'] ) {
				break;
			}
		}
		$this->assertTrue( $run['complete'] );
		$this->assertSame( 4, $changed );
		$this->assertSame( array( array( (string) $super ) ), get_post_meta( $forum, '_bbp_sticky_topics', false ) );
		$this->assertSame( array( (string) $keep ), $db->get_col( $db->prepare( "SELECT meta_id FROM {$db->postmeta} WHERE post_id = %d AND meta_key = %s", $forum, '_bbp_sticky_topics' ) ) );
		$before = $this->snapshot()[0];
		$this->assertSame( 0, $this->finish() );
		$this->assertSame( $before, $this->snapshot()[0] );
	}

	public function test_long_lists_resume_within_one_forum() {
		list( $first, $forum, $topic, $super ) = $this->fixture( 30 );
		bbp_admin_repair_sticky_batch();
		bbp_admin_repair_sticky_batch();
		$state = get_option( $this->option() );
		$this->assertSame( $forum, $state['current'] );
		$this->assertSame( 1, $state['offset'] );
		$this->assertFalse( $state['ready'] );
		$this->assertCount( 31, get_post_meta( $forum, '_bbp_sticky_topics', true ) );
		$this->assertSame( 30, $this->finish() );
		$this->assertSame( array( (string) $super ), get_post_meta( $forum, '_bbp_sticky_topics', true ) );
	}

	/** @dataProvider malformed_states */
	public function test_malformed_checkpoints_restart_without_preview_writes( $field, $value ) {
		$this->fixture();
		bbp_admin_repair_sticky_batch();
		$state           = get_option( $this->option() );
		$state[ $field ] = 'past-bound' === $value ? $state['upper_id'] + 1 : $value;
		update_option( $this->option(), $state );
		$before  = $this->snapshot();
		$preview = bbp_admin_repair_sticky_batch( true );
		$this->assertFalse( $preview['restarted'] );
		$this->assertSame( $before, $this->snapshot() );
		$this->assertSame( 0, bbp_admin_repair_sticky_batch()['code'] );
		$this->finish();
	}

	public function test_changed_source_restarts_the_current_list_and_preserves_new_assignments() {
		list( $first, $forum, $topic, $super ) = $this->fixture( 20 );
		bbp_admin_repair_sticky_batch();
		bbp_admin_repair_sticky_batch();
		$valid = $this->factory->topic->create( array( 'post_parent' => $forum ) );
		update_post_meta( $forum, '_bbp_sticky_topics', array( $valid, $super ) );
		$result = bbp_admin_repair_sticky_batch();
		$this->assertTrue( $result['restarted'] );
		$this->finish();
		$this->assertSame( array( $valid, $super ), get_post_meta( $forum, '_bbp_sticky_topics', true ) );
	}

	public function test_filtered_super_stickies_and_topic_parents_are_preserved() {
		list( $first, $forum, $topic, $super ) = $this->fixture();
		$filter = static function ( $parent, $id ) use ( $topic, $forum ) { return $id === $topic ? $forum : $parent; };
		add_filter( 'bbp_get_topic_forum_id', $filter, 10, 2 );
		try {
			$before = get_post_meta( $forum, '_bbp_sticky_topics', true );
			$this->assertSame( 0, $this->finish() );
			$this->assertSame( $before, get_post_meta( $forum, '_bbp_sticky_topics', true ) );
		} finally {
			remove_filter( 'bbp_get_topic_forum_id', $filter, 10 );
		}
	}

	/** @dataProvider deletion_failures */
	public function test_rejected_or_falsely_reported_metadata_deletes_do_not_acknowledge_the_forum( $reported ) {
		list( $first, $forum, $topic, $super ) = $this->fixture( 1 );
		$filter = static function () use ( $reported ) { return $reported; };
		add_filter( 'delete_post_metadata_by_mid', $filter );
		try {
			for ( $i = 0; $i < 20; ++$i ) {
				$result = bbp_admin_repair_sticky_batch();
				if ( $result['code'] ) {
					break;
				}
			}
			$this->assertNotSame( 0, $result['code'] );
			$this->assertFalse( $result['complete'] );
			$this->assertSame( $forum, get_option( $this->option() )['current'] );
		} finally {
			remove_filter( 'delete_post_metadata_by_mid', $filter );
		}
		$this->finish();
		$this->assertSame( array( array( (string) $super ) ), get_post_meta( $forum, '_bbp_sticky_topics', false ) );
	}

	public function test_checkpoint_failure_after_a_write_is_safe_to_replay_automatically() {
		list( $first, $forum, $topic, $super ) = $this->fixture( 1 );
		$this->factory->forum->create();
		for ( $i = 0; $i < 10; ++$i ) {
			bbp_admin_repair_sticky_batch();
			$state = get_option( $this->option() );
			if ( $state['current'] === $forum && $state['ready'] ) {
				break;
			}
		}
		$this->assertTrue( $state['ready'] );
		$this->assertSame( 0, bbp_admin_repair_sticky_batch()['code'] );
		$fail = static function ( $value, $old ) { return $old; };
		add_filter( 'pre_update_option_' . $this->option(), $fail, 10, 2 );
		try {
			$this->assertSame( 6, bbp_admin_repair_sticky_batch()['code'] );
		} finally {
			remove_filter( 'pre_update_option_' . $this->option(), $fail, 10 );
		}
		$this->assertTrue( bbp_admin_repair_tool_run_automatically( 'bbp-sync-all-topics-sticky', 'bbp_admin_repair_sticky' ) );
		$this->assertSame( array( array( (string) $super ) ), get_post_meta( $forum, '_bbp_sticky_topics', false ) );
		$this->assertFalse( get_option( $this->option(), false ) );
	}

	public function test_automatic_budget_exhaustion_preserves_work_for_retry() {
		list( $first, $forum, $topic, $super ) = $this->fixture( 8 );
		$limit = static function () { return 1; };
		add_filter( 'bbp_admin_repair_tool_automatic_batch_limit', $limit );
		try {
			$this->assertFalse( bbp_admin_repair_tool_run_automatically( 'bbp-sync-all-topics-sticky', 'bbp_admin_repair_sticky' ) );
			$this->assertContains( 'bbp-sync-all-topics-sticky', bbp_get_pending_upgrades() );
		} finally {
			remove_filter( 'bbp_admin_repair_tool_automatic_batch_limit', $limit );
		}
		$this->assertTrue( bbp_admin_repair_tool_run_automatically( 'bbp-sync-all-topics-sticky', 'bbp_admin_repair_sticky' ) );
		$this->assertNotContains( 'bbp-sync-all-topics-sticky', bbp_get_pending_upgrades() );
		$this->assertSame( array( (string) $super ), get_post_meta( $forum, '_bbp_sticky_topics', true ) );
	}
	public function test_current_forum_retyped_between_batches_does_not_block_later_forums() {
		list( $first, $forum, $topic, $super ) = $this->fixture( 8 );
		$later = $this->factory->forum->create();
		update_post_meta( $later, '_bbp_sticky_topics', array( $topic ) );
		bbp_admin_repair_sticky_batch();
		bbp_admin_repair_sticky_batch();
		bbp_db()->update( bbp_db()->posts, array( 'post_type' => 'page' ), array( 'ID' => $forum ) );
		$this->finish();
		$this->assertSame( '', get_post_meta( $later, '_bbp_sticky_topics', true ) );
		$this->assertCount( 9, get_post_meta( $forum, '_bbp_sticky_topics', true ) );
	}

	public function test_forums_created_after_the_initial_upper_bound_wait_for_the_next_run() {
		$this->fixture();
		bbp_admin_repair_sticky_batch();
		$later = $this->factory->forum->create();
		update_post_meta( $later, '_bbp_sticky_topics', 'malformed' );
		$this->finish();
		$this->assertSame( 'malformed', get_post_meta( $later, '_bbp_sticky_topics', true ) );
		$this->finish();
		$this->assertSame( '', get_post_meta( $later, '_bbp_sticky_topics', true ) );
	}

	public function test_a_changed_super_sticky_configuration_restarts_saved_classification() {
		list( $first, $forum, $topic, $super ) = $this->fixture();
		bbp_admin_repair_sticky_batch();
		bbp_admin_repair_sticky_batch();
		update_option( '_bbp_super_sticky_topics', array( $topic, $super ) );
		$this->assertTrue( bbp_admin_repair_sticky_batch()['restarted'] );
		$this->finish();
		$this->assertCount( 5, get_post_meta( $forum, '_bbp_sticky_topics', true ) );
	}

	public function test_initial_checkpoint_rejection_does_not_write_sticky_metadata() {
		$this->fixture();
		$before = $this->snapshot()[0];
		$fail = static function ( $value, $old ) { return $old; };
		add_filter( 'pre_update_option_' . $this->option(), $fail, 10, 2 );
		try {
			$this->assertSame( 6, bbp_admin_repair_sticky_batch()['code'] );
			$this->assertSame( $before, $this->snapshot()[0] );
		} finally {
			remove_filter( 'pre_update_option_' . $this->option(), $fail, 10 );
		}
		$this->finish();
	}

	/** @dataProvider deletion_failures */
	public function test_rejected_or_falsely_reported_additions_preserve_the_original_list( $reported ) {
		list( $first, $forum, $topic, $super ) = $this->fixture( 1 );
		$this->size = 1000;
		$before = $this->snapshot()[0];
		$fail = static function ( $check, $id, $key ) use ( $reported ) {
			return '_bbp_sticky_topics' === $key ? $reported : $check;
		};
		add_filter( 'add_post_metadata', $fail, 10, 3 );
		try {
			$result = bbp_admin_repair_sticky_batch();
			$this->assertNotSame( 0, $result['code'] );
			$this->assertFalse( $result['complete'] );
			$this->assertSame( $before, $this->snapshot()[0] );
		} finally {
			remove_filter( 'add_post_metadata', $fail, 10 );
		}
		$this->finish();
		$this->assertSame( array( (string) $super ), get_post_meta( $forum, '_bbp_sticky_topics', true ) );
	}

	public function test_a_failed_metadata_read_keeps_the_current_forum_reachable() {
		list( $first, $forum, $topic, $super ) = $this->fixture();
		bbp_admin_repair_sticky_batch();
		$db = bbp_db();
		$old = $db->suppress_errors( true );
		$fail = static function ( $sql ) use ( $db, $forum ) {
			return false !== strpos( $sql, "FROM {$db->postmeta} WHERE post_id IN ({$forum})" ) ? 'SELECT * FROM bbp_missing_sticky_metadata' : $sql;
		};
		add_filter( 'query', $fail );
		try {
			$result = bbp_admin_repair_sticky_batch();
			$this->assertSame( 2, $result['code'] );
			$this->assertFalse( $result['complete'] );
		} finally {
			remove_filter( 'query', $fail );
			$db->suppress_errors( $old );
		}
		$this->finish();
		$this->assertSame( array( (string) $super ), get_post_meta( $forum, '_bbp_sticky_topics', true ) );
	}

	public function test_no_forums_is_a_successful_automatic_noop() {
		$this->assertTrue( bbp_admin_repair_tool_run_automatically( 'bbp-sync-all-topics-sticky', 'bbp_admin_repair_sticky' ) );
		$this->assertFalse( get_option( $this->option(), false ) );
	}

}
