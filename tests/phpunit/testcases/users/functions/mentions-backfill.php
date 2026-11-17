<?php

/**
 * Historical mention upgrade worker coverage.
 *
 * @group users
 * @group mentions
 * @group tools
 * @ticket BBP2773
 */
class BBP_Tests_Users_Functions_Mentions_Backfill extends BBP_UnitTestCase {
	private $recipient;
	private $forum;
	private $mail = array();
	private $options = array();

	public function setUp(): void {
		parent::setUp();
		require_once bbpress()->includes_dir . 'admin/actions.php';
		bbp_setup_admin();
		$this->recipient = $this->factory->user->create( array( 'role' => bbp_get_participant_role(), 'user_nicename' => 'backfill-reader' ) );
		bbp_update_user_notification_preferences( $this->recipient, array( 'mentions' => true ) );
		$this->forum = $this->factory->forum->create();
		foreach ( array( '_bbp_mention_backfill', '_bbp_db_pending_upgrades', bbp_admin_repair_tool_batch_option_name( 'bbp-user-mentions' ) ) as $name ) {
			$this->options[ $name ] = get_option( $name, null );
			delete_option( $name );
		}
		bbpress()->mention_notification_queue = array();
		bbpress()->mention_subscription_recipients = array();
		add_filter( 'pre_wp_mail', array( $this, 'capture_mail' ), 10, 2 );
	}

	public function tearDown(): void {
		remove_filter( 'pre_wp_mail', array( $this, 'capture_mail' ), 10 );
		bbpress()->mention_notification_queue = array();
		bbpress()->mention_subscription_recipients = array();
		foreach ( $this->options as $name => $value ) {
			if ( null === $value ) {
				delete_option( $name );
			} else {
				update_option( $name, $value );
			}
		}
		parent::tearDown();
	}

	public function capture_mail( $return, $atts ) {
		$this->mail[] = $atts;
		return true;
	}

	private function historical_post( $args = array() ) {
		remove_action( 'wp_after_insert_post', 'bbp_save_post_mentions', 20 );
		try {
			return $this->factory->topic->create( array_merge( array(
				'post_parent'  => $this->forum,
				'post_content' => '@backfill-reader',
			), $args ) );
		} finally {
			add_action( 'wp_after_insert_post', 'bbp_save_post_mentions', 20, 4 );
		}
	}

	private function batch( $size = 100, $dry_run = false ) {
		$filter = static function() use ( $size ) { return $size; };
		add_filter( 'bbp_admin_repair_tool_batch_size', $filter );
		try {
			return bbp_admin_upgrade_user_mentions_batch( $dry_run );
		} finally {
			remove_filter( 'bbp_admin_repair_tool_batch_size', $filter );
		}
	}

	private function checkpoint() {
		list( $read, $state ) = bbp_admin_repair_tool_get_checkpoint( 'bbp-user-mentions' );
		$this->assertTrue( $read );
		return $state;
	}

	private function finish( $size = 100 ) {
		for ( $attempt = 0; $attempt < 100; ++$attempt ) {
			$result = $this->batch( $size );
			$this->assertSame( 0, $result['code'] );
			if ( $result['complete'] ) {
				return $result;
			}
		}
		$this->fail( 'Mention upgrade did not finish.' );
	}

	public function test_batches_resume_across_id_gaps_and_skip_other_post_types() {
		$one = $this->historical_post();
		$gap = $this->factory->post->create();
		wp_delete_post( $gap, true );
		$page = $this->factory->post->create( array( 'post_type' => 'page', 'post_content' => '@backfill-reader' ) );
		$two = $this->historical_post( array( 'post_type' => bbp_get_reply_post_type(), 'post_parent' => $one ) );
		$first = $this->batch( 1 );
		$this->assertFalse( $first['complete'] );
		$this->assertSame( $two, $this->checkpoint()['upper_id'] );
		$this->assertSame( array(), bbp_get_users_for_object( $one, '_bbp_mention' ) );
		$done = $this->finish( 1 );
		$this->assertSame( 2, $done['processed'] );
		$this->assertFalse( $this->checkpoint() );
		$this->assertSame( array( $this->recipient ), bbp_get_users_for_object( $one, '_bbp_mention' ) );
		$this->assertSame( array( $this->recipient ), bbp_get_users_for_object( $two, '_bbp_mention' ) );
		$this->assertSame( array(), bbp_get_users_for_object( $page, '_bbp_mention' ) );
	}

	public function test_fixed_upper_id_excludes_new_posts_until_a_new_run() {
		$old = $this->historical_post();
		$this->batch( 1 );
		$new = $this->historical_post();
		$this->finish();
		$this->assertSame( $old, get_option( '_bbp_mention_backfill' )['upper'] );
		$this->assertSame( array(), bbp_get_users_for_object( $new, '_bbp_mention' ) );
		$this->finish();
		$this->assertSame( $new, get_option( '_bbp_mention_backfill' )['upper'] );
		$this->assertSame( array( $this->recipient ), bbp_get_users_for_object( $new, '_bbp_mention' ) );
	}

	public function test_historical_mentions_remain_silent_on_approval_and_reintroduction() {
		$topic = $this->historical_post( array( 'post_status' => bbp_get_pending_status_id() ) );
		$this->finish();
		$this->assertSame( array( 'handled' => array( $this->recipient ) ), get_post_meta( $topic, '_bbp_mention_notification', true ) );
		wp_update_post( array( 'ID' => $topic, 'post_status' => bbp_get_public_status_id() ) );
		wp_update_post( array( 'ID' => $topic, 'post_content' => 'Removed.' ) );
		wp_update_post( array( 'ID' => $topic, 'post_content' => '@backfill-reader' ) );
		bbp_shutdown();
		$this->assertSame( array(), $this->mail );

		$new = $this->factory->user->create( array( 'role' => bbp_get_participant_role(), 'user_nicename' => 'new-backfill-reader' ) );
		bbp_update_user_notification_preferences( $new, array( 'mentions' => true ) );
		wp_update_post( array( 'ID' => $topic, 'post_content' => '@backfill-reader @new-backfill-reader' ) );
		bbp_shutdown();
		$this->assertCount( 1, $this->mail );
		$this->assertSame( get_userdata( $new )->user_email, $this->mail[0]['to'] );
	}

	public function test_unpublished_notification_recipients_remain_available_on_approval() {
		foreach ( array( bbp_get_topic_post_type(), bbp_get_reply_post_type() ) as $type ) {
			$parent = ( bbp_get_reply_post_type() === $type ) ? $this->historical_post( array( 'post_content' => '' ) ) : $this->forum;
			$post = wp_insert_post( array( 'post_type' => $type, 'post_parent' => $parent, 'post_status' => bbp_get_pending_status_id(), 'post_content' => '@backfill-reader' ) );
			if ( bbp_get_reply_post_type() === $type ) {
				bbp_update_reply_topic_id( $post, $parent );
				bbp_update_reply_forum_id( $post, $this->forum );
			} else {
				bbp_update_topic_forum_id( $post, $this->forum );
			}
			$this->assertSame( array( 'handled' => array() ), get_post_meta( $post, '_bbp_mention_notification', true ) );
			$this->finish();
			$this->assertSame( array( 'handled' => array() ), get_post_meta( $post, '_bbp_mention_notification', true ) );
			wp_update_post( array( 'ID' => $post, 'post_status' => bbp_get_public_status_id() ) );
			bbp_shutdown();
			$this->assertCount( 1, $this->mail );
			$this->mail = array();
		}
	}

	public function test_existing_notification_history_and_live_queue_are_preserved() {
		$topic = $this->historical_post();
		$other = $this->factory->user->create();
		bbp_claim_mention_notification_recipients( $topic, array( $other ) );
		$queue = array( get_current_blog_id() => array( 123456 => array( $other ) ) );
		bbpress()->mention_notification_queue = $queue;
		$this->finish();
		$this->assertSame( $queue, bbpress()->mention_notification_queue );
		$state = get_post_meta( $topic, '_bbp_mention_notification', true );
		$this->assertEqualSets( array( $other, $this->recipient ), $state['handled'] );
		$this->finish();
		$this->assertSame( $state, get_post_meta( $topic, '_bbp_mention_notification', true ) );
	}

	public function test_posts_without_mentions_need_no_notification_record() {
		$topic = $this->historical_post( array( 'post_content' => 'No mentions.' ) );
		$this->finish();
		$this->assertFalse( metadata_exists( 'post', $topic, '_bbp_mention_notification' ) );
	}

	public function test_all_statuses_are_indexed_without_email() {
		$posts = array();
		foreach ( array( 'publish', 'pending', 'draft', 'spam', 'trash', 'closed' ) as $status ) {
			$posts[] = $this->historical_post( array( 'post_status' => $status ) );
		}
		$this->finish();
		foreach ( $posts as $post ) {
			$this->assertSame( array( $this->recipient ), bbp_get_users_for_object( $post, '_bbp_mention' ) );
		}
		bbp_shutdown();
		$this->assertSame( array(), $this->mail );
	}

	public function test_relationship_failure_retries_the_failed_post() {
		$topic = $this->historical_post();
		$fail = static function( $check, $id, $key ) use ( $topic ) {
			return ( $id === $topic ) && ( '_bbp_mention' === $key ) ? false : $check;
		};
		add_filter( 'add_post_metadata', $fail, 10, 3 );
		try {
			$result = $this->batch();
			$this->assertSame( BBP_Admin_Batch_Worker::RESULT_FAILED, $result['code'] );
			$this->assertSame( 'bbp_mention_backfill_post', $result['message']->get_error_code() );
			$this->assertLessThan( $topic, $this->checkpoint()['cursor'] );
		} finally {
			remove_filter( 'add_post_metadata', $fail, 10 );
		}
		$this->finish();
		$this->assertSame( array( $this->recipient ), bbp_get_users_for_object( $topic, '_bbp_mention' ) );
	}

	public function test_notification_baseline_failure_retries_the_failed_post() {
		$topic = $this->historical_post();
		$fail = static function( $check, $id, $key ) {
			return ( '_bbp_mention_notification' === $key ) ? false : $check;
		};
		add_filter( 'add_post_metadata', $fail, 10, 3 );
		try {
			$this->assertSame( BBP_Admin_Batch_Worker::RESULT_FAILED, $this->batch()['code'] );
			$this->assertLessThan( $topic, $this->checkpoint()['cursor'] );
		} finally {
			remove_filter( 'add_post_metadata', $fail, 10 );
		}
		$this->finish();
		bbp_shutdown();
		$this->assertSame( array(), $this->mail );
	}

	public function test_exception_leaves_the_last_saved_checkpoint_for_retry() {
		$topic = $this->historical_post();
		$fail = static function( $check, $id, $key ) {
			if ( '_bbp_mention' === $key ) {
				throw new RuntimeException( 'Interrupted relationship write.' );
			}
			return $check;
		};
		add_filter( 'add_post_metadata', $fail, 10, 3 );
		try {
			$this->batch();
			$this->fail( 'Expected the write to be interrupted.' );
		} catch ( RuntimeException $exception ) {
			$this->assertLessThan( $topic, $this->checkpoint()['cursor'] );
		} finally {
			remove_filter( 'add_post_metadata', $fail, 10 );
		}
		$this->finish();
	}

	public function test_legacy_partial_progress_moves_to_the_shared_checkpoint() {
		$topic = $this->historical_post();
		update_option( '_bbp_mention_backfill', array( 'cursor' => $topic - 1, 'upper' => $topic, 'processed' => 0, 'complete' => false ), false );
		$result = $this->batch( 1 );
		$this->assertTrue( $result['complete'] );
		$this->assertSame( 1, $result['processed'] );
		$this->assertFalse( $this->checkpoint() );
		$this->assertTrue( get_option( '_bbp_mention_backfill' )['complete'] );
	}

	public function test_active_legacy_worker_is_not_taken_over() {
		$topic = $this->historical_post();
		$legacy = array( 'cursor' => 0, 'upper' => $topic, 'processed' => 0, 'complete' => false, 'expires' => time() + 300 );
		update_option( '_bbp_mention_backfill', $legacy, false );
		$result = $this->batch();
		$this->assertSame( BBP_Admin_Batch_Worker::RESULT_FAILED, $result['code'] );
		$this->assertFalse( $this->checkpoint() );
		$this->assertSame( array(), bbp_get_users_for_object( $topic, '_bbp_mention' ) );
		$legacy['expires'] = time() - 1;
		update_option( '_bbp_mention_backfill', $legacy, false );
		$this->finish();
	}

	public function test_invalid_shared_checkpoint_restarts_safely_and_is_not_autoloaded() {
		$this->historical_post();
		$name = bbp_admin_repair_tool_batch_option_name( 'bbp-user-mentions' );
		update_option( $name, array( 'cursor' => 99, 'upper_id' => 1, 'processed' => 0 ), false );
		$result = $this->batch();
		$this->assertTrue( $result['restarted'] );
		$this->assertTrue( $result['complete'] );
		$this->assertArrayNotHasKey( $name, wp_load_alloptions() );
		$this->assertArrayNotHasKey( '_bbp_mention_backfill', wp_load_alloptions() );
	}

	public function test_multisite_progress_and_notification_records_are_site_local() {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'Multisite only.' );
		}
		$this->historical_post();
		$one = $this->batch( 1 );
		$site = $this->factory->blog->create();
		switch_to_blog( $site );
		try {
			$this->assertFalse( $this->checkpoint() );
			$topic = $this->historical_post();
			$this->finish();
			$this->assertSame( array( 'handled' => array( $this->recipient ) ), get_post_meta( $topic, '_bbp_mention_notification', true ) );
		} finally {
			restore_current_blog();
		}
		$this->assertSame( $one['processed'], $this->checkpoint()['processed'] );
		$this->finish();
	}

	public function test_relationship_adapters_remove_stale_mentions() {
		$old = bbpress()->engagements;
		try {
			foreach ( array( 'BBP_User_Engagements_Meta', 'BBP_User_Engagements_Term', 'BBP_User_Engagements_User' ) as $class ) {
				bbpress()->engagements = new $class();
				$topic = $this->historical_post();
				$this->assertTrue( bbp_backfill_post_mentions( $topic ), $class );
				$this->assertSame( array( $this->recipient ), bbp_get_users_for_object( $topic, '_bbp_mention' ), $class );
				bbp_db()->update( bbp_db()->posts, array( 'post_content' => 'Removed.' ), array( 'ID' => $topic ) );
				$this->assertTrue( bbp_backfill_post_mentions( $topic ), $class );
				$this->assertSame( array(), bbp_get_users_for_object( $topic, '_bbp_mention' ), $class );
				$this->assertSame( array( 'handled' => array( $this->recipient ) ), get_post_meta( $topic, '_bbp_mention_notification', true ), $class );
			}
		} finally {
			bbpress()->engagements = $old;
		}
	}

	public function test_checkpoint_write_failure_retries_without_email() {
		$topic = $this->historical_post();
		$name = bbp_admin_repair_tool_batch_option_name( 'bbp-user-mentions' );
		$failed = false;
		$fail = static function( $value, $previous ) use ( &$failed, $topic ) {
			if ( ! $failed && is_array( $value ) && isset( $value['cursor'] ) && $value['cursor'] === $topic ) {
				$failed = true;
				return $previous;
			}
			return $value;
		};
		add_filter( 'pre_update_option_' . $name, $fail, 10, 2 );
		try {
			$result = $this->batch();
			$this->assertSame( BBP_Admin_Batch_Worker::RESULT_FAILED, $result['code'] );
			$this->assertTrue( $failed );
			$this->assertLessThan( $topic, $this->checkpoint()['cursor'] );
		} finally {
			remove_filter( 'pre_update_option_' . $name, $fail, 10 );
		}
		$this->finish();
		bbp_shutdown();
		$this->assertSame( array(), $this->mail );
	}

	public function test_batch_query_uses_a_bounded_primary_key_range() {
		$this->historical_post();
		$queries = array();
		$collect = static function( $query ) use ( &$queries ) {
			$queries[] = $query;
			return $query;
		};
		add_filter( 'query', $collect );
		try {
			$this->batch( 1 );
		} finally {
			remove_filter( 'query', $collect );
		}
		$upper = $this->checkpoint()['upper_id'];
		$this->assertContains( 'SELECT ID, post_type FROM ' . bbp_db()->posts . ' WHERE ID > 0 AND ID <= ' . $upper . ' ORDER BY ID ASC LIMIT 1', $queries );
		$this->assertStringNotContainsString( ' OFFSET ', implode( "\n", $queries ) );
		$this->assertStringNotContainsString( 'COUNT(', implode( "\n", $queries ) );
	}

	public function test_deleted_upper_boundary_finishes_without_scanning_new_posts() {
		$topic = $this->historical_post();
		$upper = $this->historical_post();
		$this->batch( 1 );
		wp_delete_post( $upper, true );
		$preview = bbp_preview_mention_backfill( array( 'cursor' => $topic, 'upper' => $upper, 'complete' => false ), 100 );
		$this->assertSame( $topic, $preview['cursor'] );
		$this->assertFalse( $preview['more'] );
		$done = $this->finish();
		$this->assertTrue( $done['complete'] );
		$this->assertSame( $upper, get_option( '_bbp_mention_backfill' )['upper'] );
	}

	public function test_dry_run_reports_changes_without_writing_or_sending_mail() {
		$topic = $this->historical_post();
		$stale = $this->factory->user->create();
		bbp_add_user_to_object( $topic, $stale, '_bbp_mention' );
		bbp_add_pending_upgrade( 'bbp-user-mentions' );
		$pending = bbp_get_pending_upgrades();
		$queries = array();
		$collect = static function( $query ) use ( &$queries ) { $queries[] = $query; return $query; };
		add_filter( 'query', $collect );
		try {
			$preview = $this->batch( 100, true );
		} finally {
			remove_filter( 'query', $collect );
		}
		$this->assertSame( 0, $preview['code'] );
		$this->assertTrue( $preview['complete'] );
		$this->assertSame( 1, $preview['posts'] );
		$this->assertSame( 1, $preview['added'] );
		$this->assertSame( 1, $preview['removed'] );
		$this->assertSame( 1, $preview['baselines'] );
		foreach ( $queries as $query ) {
			$this->assertDoesNotMatchRegularExpression( '/^\s*(INSERT|UPDATE|DELETE|REPLACE|ALTER|CREATE|DROP)\b/i', $query );
		}
		$this->assertFalse( $this->checkpoint() );
		$this->assertFalse( get_option( '_bbp_mention_backfill' ) );
		$this->assertSame( array( $stale ), bbp_get_users_for_object( $topic, '_bbp_mention' ) );
		$this->assertSame( $pending, bbp_get_pending_upgrades() );
		$this->assertSame( array(), $this->mail );
	}

	public function test_dry_run_uses_relationship_adapters_without_changing_handled_ids() {
		$old = bbpress()->engagements;
		try {
			foreach ( array( 'BBP_User_Engagements_Meta', 'BBP_User_Engagements_Term', 'BBP_User_Engagements_User' ) as $class ) {
				bbpress()->engagements = new $class();
				$topic = $this->historical_post();
				$stale = $this->factory->user->create();
				bbp_add_user_to_object( $topic, $stale, '_bbp_mention' );
				$record = array( 'handled' => array( $stale, $this->recipient ) );
				update_post_meta( $topic, '_bbp_mention_notification', $record );
				$preview = bbp_preview_mention_backfill( array( 'cursor' => $topic - 1, 'upper' => $topic, 'complete' => false ), 1 );
				$this->assertSame( 1, $preview['added'], $class );
				$this->assertSame( 1, $preview['removed'], $class );
				$this->assertSame( 0, $preview['baselines'], $class );
				$this->assertSame( $record, get_post_meta( $topic, '_bbp_mention_notification', true ), $class );
				$this->assertSame( array( $stale ), bbp_get_users_for_object( $topic, '_bbp_mention' ), $class );
			}
		} finally {
			bbpress()->engagements = $old;
		}
	}

	public function test_async_dry_run_resumes_its_own_checkpoint_without_touching_run() {
		$this->historical_post();
		$this->factory->post->create_many( 3 );
		bbp_admin_repair_tool_execution_context( 'dry-run', 'mention-preview-test' );
		try {
			$first = $this->batch( 1, true );
			$this->assertFalse( $first['complete'] );
			list( $read, $saved ) = bbp_admin_repair_tool_get_checkpoint( 'bbp-user-mentions', true );
			$this->assertTrue( $read );
			$this->assertSame( $first['scanned'], $saved['_preview_state']['totals']['scanned'] );
			$this->assertFalse( get_option( '_bbp_tool_batch_' . md5( 'bbp-user-mentions' ), false ) );
			for ( $i = 0; $i < 30 && ! $first['complete']; ++$i ) {
				$first = $this->batch( 1, true );
				$this->assertSame( 0, $first['code'] );
			}
			$this->assertTrue( $first['complete'] );
			$this->assertFalse( bbp_admin_repair_tool_get_checkpoint( 'bbp-user-mentions', true )[1] );
		} finally {
			bbp_admin_repair_tool_execution_context( 'run', '' );
		}
		$this->assertFalse( get_option( '_bbp_mention_backfill' ) );
	}

	public function test_dry_run_invalid_notification_record_is_read_only() {
		$topic = $this->historical_post();
		update_post_meta( $topic, '_bbp_mention_notification', 'invalid' );
		$result = $this->batch( 100, true );
		$this->assertSame( BBP_Admin_Batch_Worker::RESULT_FAILED, $result['code'] );
		$this->assertSame( 'bbp_mention_backfill_post', $result['message']->get_error_code() );
		$this->assertSame( 'invalid', get_post_meta( $topic, '_bbp_mention_notification', true ) );
		$this->assertFalse( $this->checkpoint() );
		$this->assertSame( array(), bbp_get_users_for_object( $topic, '_bbp_mention' ) );
	}

	public function test_dry_run_source_failure_leaves_run_checkpoint_untouched() {
		$topic = $this->historical_post();
		$this->batch( 1 );
		$state = $this->checkpoint();
		$fail = static function( $query ) {
			return ( 0 === strpos( $query, 'SELECT ID, post_type FROM ' ) ) ? 'SELECT * FROM bbp_missing_dry_run_table' : $query;
		};
		$suppressed = bbp_db()->suppress_errors();
		add_filter( 'query', $fail );
		try {
			$result = $this->batch( 100, true );
			$this->assertSame( BBP_Admin_Batch_Worker::RESULT_FAILED, $result['code'] );
			$this->assertSame( 'bbp_mention_backfill_query', $result['message']->get_error_code() );
		} finally {
			remove_filter( 'query', $fail );
			bbp_db()->suppress_errors( $suppressed );
		}
		$this->assertSame( $state, $this->checkpoint() );
		$this->assertSame( array(), bbp_get_users_for_object( $topic, '_bbp_mention' ) );
	}
}
