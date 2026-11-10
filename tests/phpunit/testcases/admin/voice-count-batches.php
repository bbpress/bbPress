<?php

/**
 * @group tools
 * @group tool-batches
 */
class BBP_Tests_Admin_Voice_Count_Batches extends BBP_UnitTestCase {

	public function setUp(): void {
		parent::setUp();
		if ( ! function_exists( 'bbp_admin' ) ) {
			require_once BBP_PLUGIN_DIR . 'includes/admin/actions.php';
		}
		bbp_admin();
		require_once BBP_PLUGIN_DIR . 'includes/admin/tools/common.php';
		require_once BBP_PLUGIN_DIR . 'includes/admin/tools/repair.php';
		require_once BBP_PLUGIN_DIR . 'includes/admin/tools/upgrade.php';
		add_filter( 'bbp_admin_repair_tool_batch_size', array( $this, 'batch_size' ) );
	}

	public function tearDown(): void {
		remove_filter( 'bbp_admin_repair_tool_batch_size', array( $this, 'batch_size' ) );
		parent::tearDown();
	}

	public function batch_size() {
		return 2;
	}

	private function option() {
		return bbp_admin_repair_tool_batch_option_name( 'bbp-topic-engagements' );
	}

	private function topics( $number = 3 ) {
		$author = $this->factory->user->create();
		$ids    = $this->factory->topic->create_many( $number, array( 'post_author' => $author ) );
		foreach ( $ids as $id ) {
			delete_post_meta( $id, '_bbp_engagement' );
			add_post_meta( $id, '_bbp_engagement', 'invalid' );
			update_post_meta( $id, '_bbp_voice_count', 'invalid' );
		}
		return array( $ids, $author );
	}

	private function snapshot() {
		$db = bbp_db();
		return array(
			$db->get_results( "SELECT * FROM {$db->postmeta} ORDER BY meta_id", ARRAY_A ),
			$db->get_results( "SELECT * FROM {$db->options} ORDER BY option_id", ARRAY_A )
		);
	}

	private function finish() {
		$this->assertTrue( bbp_admin_repair_tool_run_automatically( 'bbp-topic-engagements', 'bbp_admin_repair_topic_voice_count' ) );
		$this->assertFalse( get_option( $this->option(), false ) );
		$this->assertNotContains( 'bbp-topic-engagements', bbp_get_pending_upgrades() );
	}

	private function assert_topics( $ids, $author ) {
		foreach ( $ids as $id ) {
			$this->assertSame( array( (string) $author ), get_post_meta( $id, '_bbp_engagement', false ) );
			$this->assertSame( array( '1' ), get_post_meta( $id, '_bbp_voice_count', false ) );
		}
	}

	public function sizes() {
		return array( array( 1 ), array( 1000 ) );
	}

	/**
	 * @dataProvider sizes
	 */
	public function test_both_phases_are_bounded_read_only_and_repeat_safe( $size ) {
		list( $ids, $author ) = $this->topics();
		$keep = add_post_meta( $ids[0], '_bbp_engagement', $author );
		for ( $i = 0; $i < $size; ++$i ) {
			add_post_meta( $ids[0], '_BBP_VOICE_COUNT', '01' );
		}
		$limit = static function () use ( $size ) {
			return $size;
		};
		add_filter( 'bbp_admin_repair_tool_batch_size', $limit, 20 );
		try {
			for ( $batch = 0; $batch < 40; ++$batch ) {
				$before  = $this->snapshot();
				$preview = bbp_admin_repair_topic_voice_count_batch( true );
				$this->assertSame( $before, $this->snapshot() );
				$before_ids = bbp_db()->get_col( 'SELECT meta_id FROM ' . bbp_db()->postmeta );
				$result     = bbp_admin_repair_topic_voice_count_batch();
				$after_ids  = bbp_db()->get_col( 'SELECT meta_id FROM ' . bbp_db()->postmeta );
				$this->assertTrue( $preview['complete'] );
			$this->assertSame( 0, $preview['code'] );
				$this->assertSame( 0, $result['code'] );
				$this->assertLessThanOrEqual( $size, count( array_diff( $before_ids, $after_ids ) ) + count( array_diff( $after_ids, $before_ids ) ) );
				$this->assertNotFalse( get_metadata_by_mid( 'post', $keep ) );
				if ( $result['complete'] ) {
					break;
				}
			}
			$this->assertTrue( $result['complete'] );
			$this->assert_topics( $ids, $author );
			$this->finish();
			$before = $this->snapshot();
			$this->finish();
			$this->assertSame( $before, $this->snapshot() );
		} finally {
			remove_filter( 'bbp_admin_repair_tool_batch_size', $limit, 20 );
		}
	}

	public function test_filtered_statuses_anonymous_authors_and_cleanup_scope_match_legacy_rules() {
		$db     = bbp_db();
		$author = $this->factory->user->create();
		$topic  = $this->factory->topic->create( array( 'post_status' => bbp_get_pending_status_id() ) );
		$reply  = $this->factory->reply->create( array( 'post_parent' => $topic, 'post_author' => $author, 'post_status' => bbp_get_pending_status_id() ) );
		$this->factory->reply->create( array( 'post_parent' => $topic, 'post_author' => 0, 'post_status' => bbp_get_pending_status_id() ) );
		$db->update( $db->posts, array( 'post_type' => 'TOPIC' ), array( 'ID' => $topic ) );
		$db->update( $db->postmeta, array( 'meta_key' => '_BBP_TOPIC_ID' ), array( 'post_id' => $reply, 'meta_key' => '_bbp_topic_id' ) );
		$other   = $this->factory->post->create();
		$keep    = add_post_meta( $other, '_bbp_engagement', 'invalid' );
		$missing = $db->insert( $db->postmeta, array( 'post_id' => 999999, 'meta_key' => '_bbp_engagement', 'meta_value' => 'invalid' ) );
		$this->assertSame( 1, $missing );
		$orphan  = (int) $db->insert_id;
		$pending = static function () {
			return array( bbp_get_pending_status_id() );
		};
		add_filter( 'bbp_get_public_topic_statuses', '__return_empty_array' );
		add_filter( 'bbp_get_public_reply_statuses', $pending );
		try {
			$this->finish();
			$this->assert_topics( array( $topic ), $author );
			$this->assertNotFalse( get_metadata_by_mid( 'post', $keep ) );
			$this->assertNotFalse( get_metadata_by_mid( 'post', $orphan ) );
		} finally {
			remove_filter( 'bbp_get_public_topic_statuses', '__return_empty_array' );
			remove_filter( 'bbp_get_public_reply_statuses', $pending );
		}
	}

	public function test_partial_add_failure_and_budget_exhaustion_remain_pending_and_replay_safe() {
		list( $ids, $author ) = $this->topics();
		$fail = static function ( $check, $id, $key ) use ( $ids ) {
			return ( $id === $ids[1] ) && ( '_bbp_engagement' === $key ) ? false : $check;
		};
		add_filter( 'add_post_metadata', $fail, 10, 3 );
		try {
			$this->assertFalse( bbp_admin_repair_tool_run_automatically( 'bbp-topic-engagements', 'bbp_admin_repair_topic_voice_count' ) );
			$this->assertContains( 'bbp-topic-engagements', bbp_get_pending_upgrades() );
			$this->assertContains( (string) $author, get_post_meta( $ids[0], '_bbp_engagement', false ) );
		} finally {
			remove_filter( 'add_post_metadata', $fail, 10 );
		}
		add_filter( 'bbp_admin_repair_tool_automatic_batch_limit', '__return_true' );
		try {
			$this->assertFalse( bbp_admin_repair_tool_run_automatically( 'bbp-topic-engagements', 'bbp_admin_repair_topic_voice_count' ) );
			$this->assertContains( 'bbp-topic-engagements', bbp_get_pending_upgrades() );
		} finally {
			remove_filter( 'bbp_admin_repair_tool_automatic_batch_limit', '__return_true' );
		}
		$this->finish();
		$this->assert_topics( $ids, $author );
	}

	public function test_initial_progress_and_final_checkpoint_failures_can_resume() {
		list( $ids, $author ) = $this->topics();
		$option = $this->option();
		$fail   = static function ( $value, $old ) {
			return $old;
		};
		$before = $this->snapshot();
		add_filter( 'pre_update_option_' . $option, $fail, 10, 2 );
		try {
			$this->assertSame( 6, bbp_admin_repair_topic_voice_count_batch()['code'] );
			$this->assertSame( $before, $this->snapshot() );
		} finally {
			remove_filter( 'pre_update_option_' . $option, $fail, 10 );
		}
		$this->assertFalse( bbp_admin_repair_topic_voice_count_batch()['complete'] );
		$state = get_option( $option );
		add_filter( 'pre_update_option_' . $option, $fail, 10, 2 );
		try {
			$this->assertSame( 6, bbp_admin_repair_topic_voice_count_batch()['code'] );
			$this->assertSame( $state, get_option( $option ) );
		} finally {
			remove_filter( 'pre_update_option_' . $option, $fail, 10 );
		}
		$db          = bbp_db();
		$fail_delete = static function ( $query ) use ( $db, $option ) {
			return ( 0 === strpos( $query, 'DELETE FROM' ) ) && ( false !== strpos( $query, $db->options ) ) && ( false !== strpos( $query, $option ) ) ? 'DELETE FROM bbp_missing_voice_options' : $query;
		};
		$suppressed = $db->suppress_errors( true );
		add_filter( 'query', $fail_delete );
		try {
			$this->assertFalse( bbp_admin_repair_tool_run_automatically( 'bbp-topic-engagements', 'bbp_admin_repair_topic_voice_count' ) );
			$this->assertContains( 'bbp-topic-engagements', bbp_get_pending_upgrades() );
			$this->assertNotFalse( get_option( $option, false ) );
		} finally {
			remove_filter( 'query', $fail_delete );
			$db->suppress_errors( $suppressed );
		}
		$this->finish();
		$this->assert_topics( $ids, $author );
	}

	public function invalid_states() {
		return array( array( 'cursor', -1 ), array( 'cursor', '0' ), array( 'topic_upper', null ), array( 'phase', 'unknown' ), array( 'context', array() ) );
	}

	/**
	 * @dataProvider invalid_states
	 */
	public function test_invalid_checkpoints_restart_without_preview_writes( $field, $value ) {
		list( $ids, $author ) = $this->topics();
		bbp_admin_repair_topic_voice_count_batch();
		$state           = get_option( $this->option() );
		$state[ $field ] = $value;
		update_option( $this->option(), $state, false );
		$before = $this->snapshot();
		$this->assertFalse( bbp_admin_repair_topic_voice_count_batch( true )['restarted'] );
		$this->assertSame( $before, $this->snapshot() );
		$this->finish();
		$this->assert_topics( $ids, $author );
	}

	public function test_reported_write_success_is_verified_for_repairs_and_existing_upgrades() {
		list( $ids, $author ) = $this->topics( 1 );
		$fail = static function ( $check, $id, $key ) {
			return ( '_bbp_engagement' === $key ) ? true : $check;
		};
		add_filter( 'add_post_metadata', $fail, 10, 3 );
		try {
			$this->assertSame( 5, bbp_admin_repair_topic_voice_count_batch()['code'] );
			$this->assertSame( 0, get_option( $this->option() )['cursor'] );
			$this->assertSame( 5, bbp_admin_upgrade_user_engagements_batch()['code'] );
		} finally {
			remove_filter( 'add_post_metadata', $fail, 10 );
		}
		$this->finish();
		$this->assert_topics( $ids, $author );
	}

	public function test_voice_count_read_and_write_failures_retain_progress_for_retries() {
		list( $ids, $author ) = $this->topics();
		for ( $batch = 0; $batch < 20; ++$batch ) {
			bbp_admin_repair_topic_voice_count_batch();
			$state = get_option( $this->option() );
			if ( 'voices' === $state['phase'] ) {
				break;
			}
		}
		$this->assertSame( 'voices', $state['phase'] );
		$db = bbp_db();
		$fail = static function ( $query ) {
			return ( 0 === strpos( $query, 'SELECT relations.post_id, COUNT(DISTINCT relations.user_id)' ) ) ? 'SELECT * FROM bbp_missing_voice_source' : $query;
		};
		$suppressed = $db->suppress_errors( true );
		add_filter( 'query', $fail );
		try {
			$before = $this->snapshot();
			$this->assertSame( 3, bbp_admin_repair_topic_voice_count_batch( true )['code'] );
			$this->assertSame( $before, $this->snapshot() );
			$this->assertSame( 3, bbp_admin_repair_topic_voice_count_batch()['code'] );
			$this->assertSame( $state, get_option( $this->option() ) );
		} finally {
			remove_filter( 'query', $fail );
			$db->suppress_errors( $suppressed );
		}
		$fail_write = static function ( $check, $id, $key ) {
			return ( '_bbp_voice_count' === $key ) ? false : $check;
		};
		add_filter( 'add_post_metadata', $fail_write, 10, 3 );
		try {
			$this->assertFalse( bbp_admin_repair_tool_run_automatically( 'bbp-topic-engagements', 'bbp_admin_repair_topic_voice_count' ) );
			$this->assertContains( 'bbp-topic-engagements', bbp_get_pending_upgrades() );
			$this->assertSame( $state, get_option( $this->option() ) );
		} finally {
			remove_filter( 'add_post_metadata', $fail_write, 10 );
		}
		$this->finish();
		$this->assert_topics( $ids, $author );
	}

	public function test_reported_delete_success_is_verified_for_repairs_and_upgrades() {
		list( $ids, $author ) = $this->topics( 1 );
		$this->finish();
		$stale = add_post_meta( $ids[0], '_bbp_engagement', 'invalid' );
		add_filter( 'delete_post_metadata_by_mid', '__return_true' );
		try {
			$this->assertFalse( bbp_admin_repair_tool_run_automatically( 'bbp-topic-engagements', 'bbp_admin_repair_topic_voice_count' ) );
			$this->assertFalse( bbp_admin_repair_tool_run_automatically( 'bbp-user-topic-engagements-move', 'bbp_admin_upgrade_user_engagements' ) );
			$this->assertNotFalse( get_metadata_by_mid( 'post', $stale ) );
		} finally {
			remove_filter( 'delete_post_metadata_by_mid', '__return_true' );
		}
		$this->finish();
		$this->assertTrue( bbp_admin_repair_tool_run_automatically( 'bbp-user-topic-engagements-move', 'bbp_admin_upgrade_user_engagements' ) );
		$this->assert_topics( $ids, $author );
	}

	public function test_progress_is_isolated_between_sites() {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'Requires multisite.' );
		}
		list( $ids, $author ) = $this->topics();
		bbp_admin_repair_topic_voice_count_batch();
		$state = get_option( $this->option() );
		$blog  = self::factory()->blog->create();
		switch_to_blog( $blog );
		try {
			list( $other, $other_author ) = $this->topics();
			$this->finish();
			$this->assert_topics( $other, $other_author );
		} finally {
			restore_current_blog();
		}
		$this->assertSame( $state, get_option( $this->option() ) );
		$this->finish();
		$this->assert_topics( $ids, $author );
	}
}
