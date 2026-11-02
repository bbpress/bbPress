<?php

/**
 * @group users
 * @group mentions
 * @group tools
 * @ticket BBP2773
 */
class BBP_Tests_Users_Functions_Mentions_Backfill extends BBP_UnitTestCase {
	private $recipient;
	private $forum;
	private $mail = array();

	public function setUp(): void {
		parent::setUp();
		$this->recipient = $this->factory->user->create( array( 'role' => bbp_get_participant_role(), 'user_nicename' => 'backfill-reader' ) );
		update_user_meta( $this->recipient, '_bbp_mention_email', 'yes' );
		$this->forum = $this->factory->forum->create();
		delete_option( '_bbp_mention_backfill' );
		bbpress()->mention_notification_queue = array();
		bbpress()->mention_subscription_recipients = array();
		add_filter( 'pre_wp_mail', array( $this, 'capture_mail' ), 10, 2 );
	}

	public function tearDown(): void {
		remove_filter( 'pre_wp_mail', array( $this, 'capture_mail' ), 10 );
		bbpress()->mention_notification_queue = array();
		bbpress()->mention_subscription_recipients = array();
		delete_option( '_bbp_mention_backfill' );
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

	private function finish( $size = 100 ) {
		for ( $attempt = 0; $attempt < 100; ++$attempt ) {
			$state = bbp_backfill_mentions( $size );
			$this->assertNotWPError( $state );
			if ( $state['complete'] ) {
				return $state;
			}
		}
		$this->fail( 'Mention backfill did not finish.' );
	}

	public function test_batches_resume_across_id_gaps_and_skip_other_post_types() {
		$one = $this->historical_post();
		$gap = $this->factory->post->create();
		wp_delete_post( $gap, true );
		$page = $this->factory->post->create( array( 'post_type' => 'page', 'post_content' => '@backfill-reader' ) );
		$two = $this->historical_post( array( 'post_type' => bbp_get_reply_post_type(), 'post_parent' => $one ) );
		$state = bbp_backfill_mentions( 1 );
		$this->assertNotWPError( $state );
		$this->assertFalse( $state['complete'] );
		$this->assertSame( $two, $state['upper'] );
		$this->assertSame( array(), bbp_get_users_for_object( $one, '_bbp_mention' ) );
		$state = $this->finish( 1 );
		$this->assertSame( $two, $state['cursor'] );
		$this->assertSame( 2, $state['processed'] );
		$this->assertSame( array( $this->recipient ), bbp_get_users_for_object( $one, '_bbp_mention' ) );
		$this->assertSame( array( $this->recipient ), bbp_get_users_for_object( $two, '_bbp_mention' ) );
		$this->assertSame( array(), bbp_get_users_for_object( $page, '_bbp_mention' ) );
		$this->assertArrayNotHasKey( 'token', $state );
		$this->assertSame( $state, bbp_backfill_mentions() );
	}

	public function test_fixed_upper_id_excludes_new_posts_until_restart() {
		$old = $this->historical_post();
		$state = bbp_backfill_mentions( 1 );
		$new = $this->historical_post();
		$state = $this->finish();
		$this->assertSame( $old, $state['upper'] );
		$this->assertSame( array(), bbp_get_users_for_object( $new, '_bbp_mention' ) );
		$this->assertNotWPError( bbp_backfill_mentions( 100, true ) );
		$state = $this->finish();
		$this->assertSame( $new, $state['upper'] );
		$this->assertSame( 2, $state['processed'] );
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
		update_user_meta( $new, '_bbp_mention_email', 'yes' );
		wp_update_post( array( 'ID' => $topic, 'post_content' => '@backfill-reader @new-backfill-reader' ) );
		bbp_shutdown();
		$this->assertCount( 1, $this->mail );
		$this->assertSame( get_userdata( $new )->user_email, $this->mail[0]['to'] );
	}

	public function test_backfill_preserves_live_unpublished_notification_recipients() {
		foreach ( array( bbp_get_topic_post_type(), bbp_get_reply_post_type() ) as $type ) {
			$parent = ( bbp_get_reply_post_type() === $type ) ? $this->historical_post( array( 'post_content' => '' ) ) : $this->forum;
			$post = wp_insert_post( array( 'post_type' => $type, 'post_parent' => $parent, 'post_status' => bbp_get_pending_status_id(), 'post_content' => '@backfill-reader' ) );
			if ( bbp_get_reply_post_type() === $type ) {
				bbp_update_reply_topic_id( $post, $parent );
				bbp_update_reply_forum_id( $post, $this->forum );
			} else {
				bbp_update_topic_forum_id( $post, $this->forum );
			}
			$record = array( 'handled' => array() );
			$this->assertSame( $record, get_post_meta( $post, '_bbp_mention_notification', true ) );
			$preview = bbp_backfill_mentions( 100, true, true );
			$this->assertNotWPError( $preview );
			$this->assertSame( 0, $preview['baselines'] );
			$this->assertNotWPError( bbp_backfill_mentions( 100, true ) );
			$this->assertSame( $record, get_post_meta( $post, '_bbp_mention_notification', true ) );
			$this->assertSame( array(), $this->mail );
			wp_update_post( array( 'ID' => $post, 'post_status' => bbp_get_public_status_id() ) );
			bbp_shutdown();
			$this->assertCount( 1, $this->mail );
			$this->assertSame( get_userdata( $this->recipient )->user_email, $this->mail[0]['to'] );
			$this->assertTrue( bbp_backfill_post_mentions( $post ) );
			wp_update_post( array( 'ID' => $post, 'post_content' => 'Again @backfill-reader' ) );
			bbp_shutdown();
			$this->assertCount( 1, $this->mail );
			$this->mail = array();
		}
	}

	public function test_concurrent_initialization_cannot_replace_another_workers_lease() {
		$topic = $this->historical_post();
		$winner = array( 'cursor' => 0, 'upper' => $topic, 'processed' => 0, 'complete' => false, 'token' => 'other-worker', 'expires' => time() + 300 );
		$interleaved = false;
		$compete = static function( $query ) use ( &$interleaved, $winner ) {
			if ( ! $interleaved && ( 0 === strpos( $query, 'INSERT' ) ) && ( false !== strpos( $query, '_bbp_mention_backfill' ) ) ) {
				$interleaved = true;
				add_option( '_bbp_mention_backfill', $winner, '', false );
			}
			return $query;
		};
		add_filter( 'query', $compete );
		try {
			$result = bbp_backfill_mentions();
		} finally {
			remove_filter( 'query', $compete );
		}
		$this->assertTrue( $interleaved );
		$this->assertWPError( $result );
		$this->assertSame( $winner, bbp_get_mention_backfill_status() );
		$this->assertSame( array(), bbp_get_users_for_object( $topic, '_bbp_mention' ) );
		$this->assertFalse( metadata_exists( 'post', $topic, '_bbp_mention_notification' ) );
	}

	public function test_reloading_admin_run_resumes_an_incomplete_pass() {
		if ( ! function_exists( 'bbp_admin' ) ) {
			require_once BBP_PLUGIN_DIR . 'includes/admin/actions.php';
		}
		bbp_admin();
		$original_get = $_GET;
		$original_user = get_current_user_id();
		$admin = $this->factory->user->create( array( 'role' => 'administrator' ) );
		bbp_set_user_role( $admin, bbp_get_keymaster_role() );
		$this->set_current_user( $admin );
		$this->historical_post();
		$this->finish();
		$this->factory->post->create_many( 205 );
		$_GET = array( 'page' => 'bbp-repair', 'action' => 'run' );
		try {
			$first = bbp_admin_repair_user_mentions();
			$before = bbp_get_mention_backfill_status();
			$this->assertSame( 0, $first[0] );
			$this->assertFalse( $before['complete'] );
			$second = bbp_admin_repair_user_mentions();
			$after = bbp_get_mention_backfill_status();
			$this->assertSame( 0, $second[0] );
			$this->assertGreaterThan( $before['cursor'], $after['cursor'] );
			$this->assertSame( $before['upper'], $after['upper'] );
		} finally {
			$_GET = $original_get;
			$this->set_current_user( $original_user );
		}
	}

	public function test_repair_preserves_live_queue_and_previously_handled_recipients() {
		$topic = $this->historical_post();
		$other = $this->factory->user->create();
		bbp_claim_mention_notification_recipients( $topic, array( $other ) );
		$queue = array( get_current_blog_id() => array( 123456 => array( $other ) ) );
		bbpress()->mention_notification_queue = $queue;
		$this->finish();
		$this->assertSame( $queue, bbpress()->mention_notification_queue );
		$state = get_post_meta( $topic, '_bbp_mention_notification', true );
		$this->assertEqualSets( array( $other, $this->recipient ), $state['handled'] );
		$this->assertNotWPError( bbp_backfill_mentions( 100, true ) );
		$this->assertSame( $state, get_post_meta( $topic, '_bbp_mention_notification', true ) );
	}

	public function test_posts_without_mentions_need_no_notification_record() {
		$topic = $this->historical_post( array( 'post_content' => 'No mentions.' ) );
		$this->finish();
		$this->assertFalse( metadata_exists( 'post', $topic, '_bbp_mention_notification' ) );
	}

	public function test_all_statuses_are_indexed_without_email() {
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
			$result = bbp_backfill_mentions();
			$this->assertWPError( $result );
			$this->assertSame( 'bbp_mention_backfill_post', $result->get_error_code() );
			$state = bbp_get_mention_backfill_status();
			$this->assertLessThan( $topic, $state['cursor'] );
			$this->assertArrayNotHasKey( 'token', $state );
		} finally {
			remove_filter( 'add_post_metadata', $fail, 10 );
		}
		$this->finish();
		$this->assertSame( array( $this->recipient ), bbp_get_users_for_object( $topic, '_bbp_mention' ) );
	}

	public function test_notification_baseline_failure_does_not_advance_checkpoint() {
		$topic = $this->historical_post();
		$fail = static function( $check, $id, $key ) {
			return ( '_bbp_mention_notification' === $key ) ? false : $check;
		};
		add_filter( 'add_post_metadata', $fail, 10, 3 );
		try {
			$this->assertWPError( bbp_backfill_mentions() );
			$state = bbp_get_mention_backfill_status();
			$this->assertLessThan( $topic, $state['cursor'] );
		} finally {
			remove_filter( 'add_post_metadata', $fail, 10 );
		}
		$this->finish();
		bbp_shutdown();
		$this->assertSame( array(), $this->mail );
	}

	public function test_active_worker_is_excluded_and_expired_worker_can_resume() {
		$this->historical_post();
		$state = bbp_backfill_mentions( 1 );
		$locked = array_merge( $state, array( 'token' => 'interrupted', 'expires' => time() + 300 ) );
		$this->assertTrue( bbp_save_mention_backfill_status( $locked, $state ) );
		$result = bbp_backfill_mentions( 100, true );
		$this->assertWPError( $result );
		$this->assertSame( 'bbp_mention_backfill_busy', $result->get_error_code() );
		$expired = $locked;
		$expired['expires'] = time() - 1;
		$this->assertTrue( bbp_save_mention_backfill_status( $expired, $locked ) );
		$this->finish();
		$this->assertFalse( bbp_save_mention_backfill_status( $state, $expired ) );
	}

	public function test_exception_releases_lease_without_skipping_post() {
		$topic = $this->historical_post();
		$fail = static function( $check, $id, $key ) {
			if ( '_bbp_mention' === $key ) {
				throw new RuntimeException( 'Interrupted relationship write.' );
			}
			return $check;
		};
		add_filter( 'add_post_metadata', $fail, 10, 3 );
		try {
			bbp_backfill_mentions();
			$this->fail( 'Expected the write to be interrupted.' );
		} catch ( RuntimeException $exception ) {
			$state = bbp_get_mention_backfill_status();
			$this->assertLessThan( $topic, $state['cursor'] );
			$this->assertArrayNotHasKey( 'token', $state );
		} finally {
			remove_filter( 'add_post_metadata', $fail, 10 );
		}
		$this->finish();
	}

	public function test_progress_is_not_autoloaded_and_invalid_batch_sizes_do_not_start() {
		foreach ( array( -1, 0, 1001 ) as $size ) {
			$this->assertWPError( bbp_backfill_mentions( $size ) );
		}
		$this->assertSame( array(), bbp_get_mention_backfill_status() );
		$this->finish();
		$autoloaded = wp_load_alloptions();
		$this->assertArrayNotHasKey( '_bbp_mention_backfill', $autoloaded );
	}

	public function test_multisite_progress_and_notification_records_are_site_local() {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'Multisite only.' );
		}
		bbp_add_pending_upgrade( 'bbp-user-mentions' );
		$this->historical_post();
		$one = bbp_backfill_mentions( 1 );
		$site = $this->factory->blog->create();
		switch_to_blog( $site );
		try {
			$this->assertSame( array(), bbp_get_mention_backfill_status() );
			bbp_add_pending_upgrade( 'bbp-user-mentions' );
			$topic = $this->historical_post();
			$this->finish();
			$this->assertNotContains( 'bbp-user-mentions', bbp_get_pending_upgrades() );
			$this->assertSame( array( 'handled' => array( $this->recipient ) ), get_post_meta( $topic, '_bbp_mention_notification', true ) );
		} finally {
			restore_current_blog();
		}
		$this->assertSame( $one, bbp_get_mention_backfill_status() );
		$this->assertContains( 'bbp-user-mentions', bbp_get_pending_upgrades() );
		$this->finish();
		$this->assertNotContains( 'bbp-user-mentions', bbp_get_pending_upgrades() );
	}

	public function test_repair_uses_relationship_adapters_and_removes_stale_mentions() {
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

	public function test_checkpoint_write_failure_does_not_skip_processed_post_on_resume() {
		$topic = $this->historical_post();
		bbp_backfill_mentions( 1 );
		$failed = false;
		$fail = static function( $query ) use ( &$failed, $topic ) {
			if ( ! $failed && ( 0 === strpos( $query, 'UPDATE' ) ) && ( false !== strpos( $query, '_bbp_mention_backfill' ) ) && ( false !== strpos( stripslashes( $query ), 's:6:"cursor";i:' . $topic . ';' ) ) ) {
				$failed = true;
				return 'DO 0';
			}
			return $query;
		};
		add_filter( 'query', $fail );
		try {
			$this->assertWPError( bbp_backfill_mentions() );
			$this->assertTrue( $failed );
			$state = bbp_get_mention_backfill_status();
			$this->assertLessThan( $topic, $state['cursor'] );
		} finally {
			remove_filter( 'query', $fail );
		}
		$state = $this->finish();
		$this->assertSame( 1, $state['processed'] );
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
			$state = bbp_backfill_mentions( 1 );
		} finally {
			remove_filter( 'query', $collect );
		}
		$this->assertContains( 'SELECT ID, post_type FROM ' . bbp_db()->posts . ' WHERE ID > 0 AND ID <= ' . $state['upper'] . ' ORDER BY ID ASC LIMIT 1', $queries );
		$this->assertStringNotContainsString( ' OFFSET ', implode( "\n", $queries ) );
		$this->assertStringNotContainsString( 'COUNT(', implode( "\n", $queries ) );
	}

	public function test_repair_tool_resumes_shared_progress_and_checks_permission() {
		$this->historical_post();
		bbp_backfill_mentions( 1 );
		if ( ! function_exists( 'bbp_admin' ) ) {
			require_once BBP_PLUGIN_DIR . 'includes/admin/actions.php';
		}
		bbp_admin();
		$original_get = $_GET;
		$original_user = get_current_user_id();
		$_GET = array( 'page' => 'bbp-repair' );
		try {
			$this->set_current_user( 0 );
			$result = bbp_admin_repair_user_mentions();
			$this->assertWPError( $result[1] );
			$admin = $this->factory->user->create( array( 'role' => 'administrator' ) );
			bbp_set_user_role( $admin, bbp_get_keymaster_role() );
			$this->set_current_user( $admin );
			$result = bbp_admin_repair_user_mentions();
			$this->assertSame( 0, $result[0] );
			$this->assertStringContainsString( 'Mention scan complete', $result[1] );
			$state = bbp_get_mention_backfill_status();
			$this->assertSame( 1, $state['processed'] );
			$this->assertTrue( $state['complete'] );
		} finally {
			$_GET = $original_get;
			$this->set_current_user( $original_user );
		}
	}

	public function test_repair_handler_preserves_unrelated_cache_entries_for_backfill() {
		$this->historical_post();
		if ( ! function_exists( 'bbp_admin' ) ) {
			require_once BBP_PLUGIN_DIR . 'includes/admin/actions.php';
		}
		bbp_admin();
		$original_get = $_GET;
		$original_request = $_REQUEST;
		$original_notices = bbp_admin()->notices;
		$original_method = isset( $_SERVER['REQUEST_METHOD'] ) ? $_SERVER['REQUEST_METHOD'] : null;
		$original_user = get_current_user_id();
		$original_tools = bbp_admin()->tools;
		$admin = $this->factory->user->create( array( 'role' => 'administrator' ) );
		bbp_set_user_role( $admin, bbp_get_keymaster_role() );
		$this->set_current_user( $admin );
		bbp_register_default_repair_tools();
		$_GET = array( 'page' => 'bbp-repair', 'action' => 'run', 'checked' => array( 'bbp-mentions' ), '_wpnonce' => wp_create_nonce( 'bbpress-do-counts' ) );
		$_REQUEST = $_GET;
		$_SERVER['REQUEST_METHOD'] = 'GET';
		try {
			wp_cache_set( 'preserve', 'cached value', 'mention-backfill-test' );
			bbp_admin_repair_handler();
			$this->assertSame( 'cached value', wp_cache_get( 'preserve', 'mention-backfill-test' ) );
			$state = bbp_get_mention_backfill_status();
			$this->assertTrue( $state['complete'] );

			// Existing repair tools still flush before and after their callback.
			bbp_register_repair_tool( array( 'id' => 'test-flush', 'title' => 'Test flush', 'priority' => 999, 'callback' => static function() { return array( 0, 'Done' ); } ) );
			$_GET['checked'] = array( 'test-flush' );
			bbp_admin_repair_handler();
			$this->assertFalse( wp_cache_get( 'preserve', 'mention-backfill-test' ) );
		} finally {
			$_GET = $original_get;
			$_REQUEST = $original_request;
			bbp_admin()->notices = $original_notices;
			bbp_admin()->tools = $original_tools;
			$this->set_current_user( $original_user );
			if ( is_null( $original_method ) ) {
				unset( $_SERVER['REQUEST_METHOD'] );
			} else {
				$_SERVER['REQUEST_METHOD'] = $original_method;
			}
		}
	}

	public function test_upgrade_tool_is_listed_for_27_and_uses_upgrade_links() {
		if ( ! function_exists( 'bbp_admin' ) ) {
			require_once BBP_PLUGIN_DIR . 'includes/admin/actions.php';
		}
		$admin = bbp_admin();
		$old_tools = $admin->tools;
		$old_get = $_GET;
		$_GET = array( 'page' => 'bbp-upgrade', 'version' => '2.7.0' );
		try {
			bbp_register_default_repair_tools();
			$tools = bbp_get_admin_repair_tools( 'upgrade' );
			$this->assertArrayHasKey( 'bbp-user-mentions', $tools );
			$this->assertSame( '2.7.0', $tools['bbp-user-mentions']['version'] );
			$this->assertSame( 'medium', $tools['bbp-user-mentions']['overhead'] );
			$this->assertFalse( $tools['bbp-user-mentions']['flush_cache'] );
			$this->assertStringContainsString( 'page=bbp-upgrade', bbp_get_admin_repair_tool_run_url( $tools['bbp-user-mentions'] + array( 'id' => 'bbp-user-mentions' ) ) );
			ob_start();
			bbp_admin_upgrade_page();
			$html = ob_get_clean();
			$this->assertStringContainsString( 'Upgrade user mentions', $html );
			$this->assertStringContainsString( '2.7.0', $html );
		} finally {
			$admin->tools = $old_tools;
			$_GET = $old_get;
		}
	}

	public function test_upgrade_handler_keeps_partial_and_failed_batches_pending_until_shared_backfill_finishes() {
		$topic = $this->historical_post();
		$this->factory->post->create_many( 105 );
		bbp_add_pending_upgrade( 'bbp-user-mentions' );
		bbp_add_pending_upgrade( 'unrelated-upgrade' );
		if ( ! function_exists( 'bbp_admin' ) ) {
			require_once BBP_PLUGIN_DIR . 'includes/admin/actions.php';
		}
		$admin = bbp_admin();
		$old_tools = $admin->tools;
		$old_notices = $admin->notices;
		$old_get = $_GET;
		$old_request = $_REQUEST;
		$old_method = isset( $_SERVER['REQUEST_METHOD'] ) ? $_SERVER['REQUEST_METHOD'] : null;
		$user = $this->factory->user->create( array( 'role' => 'administrator' ) );
		bbp_set_user_role( $user, bbp_get_keymaster_role() );
		$this->set_current_user( $user );
		bbp_register_default_repair_tools();
		$_GET = array( 'page' => 'bbp-upgrade', 'action' => 'run', 'checked' => array( 'bbp-user-mentions' ), '_wpnonce' => wp_create_nonce( 'bbpress-do-counts' ) );
		$_REQUEST = $_GET;
		$_SERVER['REQUEST_METHOD'] = 'GET';
		try {
			bbp_admin_repair_handler();
			$state = bbp_get_mention_backfill_status();
			$this->assertFalse( $state['complete'] );
			$this->assertContains( 'bbp-user-mentions', bbp_get_pending_upgrades() );
			$busy = $state;
			$busy['expires'] = time() + 300;
			$this->assertTrue( bbp_save_mention_backfill_status( $busy, $state ) );
			bbp_admin_repair_handler();
			$this->assertContains( 'bbp-user-mentions', bbp_get_pending_upgrades() );
			$this->assertTrue( bbp_save_mention_backfill_status( $state, $busy ) );

			// A repair callback can finish an upgrade's saved progress.
			$result = bbp_admin_repair_user_mentions();
			$this->assertTrue( $result['complete'] );
			$this->assertNotContains( 'bbp-user-mentions', bbp_get_pending_upgrades() );
			$this->assertContains( 'unrelated-upgrade', bbp_get_pending_upgrades() );
			$this->assertSame( array( $this->recipient ), bbp_get_users_for_object( $topic, '_bbp_mention' ) );
			$this->assertEmpty( bbpress()->mention_notification_queue );
			bbp_send_mention_notifications();
			$this->assertCount( 0, $this->mail );

			// The same API used by WP-CLI clears pending state for completed work.
			bbp_add_pending_upgrade( 'bbp-user-mentions' );
			$completed = bbp_backfill_mentions();
			$this->assertTrue( $completed['complete'] );
			$this->assertNotContains( 'bbp-user-mentions', bbp_get_pending_upgrades() );
		} finally {
			$admin->tools = $old_tools;
			$admin->notices = $old_notices;
			$_GET = $old_get;
			$_REQUEST = $old_request;
			if ( is_null( $old_method ) ) {
				unset( $_SERVER['REQUEST_METHOD'] );
			} else {
				$_SERVER['REQUEST_METHOD'] = $old_method;
			}
		}
	}

	public function test_upgrade_callback_checks_upgrade_permission_and_returns_batch_status() {
		$this->historical_post();
		$this->factory->post->create_many( 105 );
		if ( ! function_exists( 'bbp_admin' ) ) {
			require_once BBP_PLUGIN_DIR . 'includes/admin/actions.php';
		}
		bbp_admin();
		$this->set_current_user( 0 );
		$result = bbp_admin_upgrade_user_mentions();
		$this->assertWPError( $result[1] );
		$this->assertFalse( $result['complete'] );
		$this->assertFalse( $result['success'] );
		$this->assertSame( array(), bbp_get_mention_backfill_status() );
		$user = $this->factory->user->create( array( 'role' => 'administrator' ) );
		bbp_set_user_role( $user, bbp_get_keymaster_role() );
		$this->set_current_user( $user );
		$old_get = $_GET;
		$_GET = array( 'page' => 'bbp-upgrade' );
		try {
			$result = bbp_admin_upgrade_user_mentions();
			$this->assertSame( 0, $result[0] );
			$this->assertFalse( $result['complete'] );
			$this->assertStringContainsString( 'Mention scan in progress', $result[1] );
			$this->assertTrue( bbp_admin_repair_tool_normalize_result( $result )['success'] );
		} finally {
			$_GET = $old_get;
		}
	}

	public function test_dry_run_reports_changes_without_writing_or_sending_mail() {
		$topic = $this->historical_post();
		$stale = $this->factory->user->create();
		bbp_add_user_to_object( $topic, $stale, '_bbp_mention' );
		bbp_add_pending_upgrade( 'bbp-user-mentions' );
		$pending = bbp_get_pending_upgrades();
		$queue = array( get_current_blog_id() => array( 123456 => array( $stale ) ) );
		bbpress()->mention_notification_queue = $queue;
		bbpress()->mention_subscription_recipients = $queue;
		$queries = array();
		$collect = static function( $query ) use ( &$queries ) {
			$queries[] = $query;
			return $query;
		};
		add_filter( 'query', $collect );
		try {
			$preview = bbp_backfill_mentions( 100, false, true );
		} finally {
			remove_filter( 'query', $collect );
		}
		$this->assertNotWPError( $preview );
		$this->assertSame( 1, $preview['posts'] );
		$this->assertSame( 1, $preview['added'] );
		$this->assertSame( 1, $preview['removed'] );
		$this->assertSame( 1, $preview['baselines'] );
		foreach ( $queries as $query ) {
			$this->assertDoesNotMatchRegularExpression( '/^\s*(INSERT|UPDATE|DELETE|REPLACE|ALTER|CREATE|DROP)\b/i', $query );
		}
		$this->assertFalse( get_option( '_bbp_mention_backfill' ) );
		$this->assertSame( array( $stale ), bbp_get_users_for_object( $topic, '_bbp_mention' ) );
		$this->assertFalse( metadata_exists( 'post', $topic, '_bbp_mention_notification' ) );
		$this->assertSame( $pending, bbp_get_pending_upgrades() );
		$this->assertSame( $queue, bbpress()->mention_notification_queue );
		$this->assertSame( $queue, bbpress()->mention_subscription_recipients );
		$this->assertSame( array(), $this->mail );
	}

	public function test_dry_run_resumes_the_same_bounded_range_without_advancing_it() {
		$topic = $this->historical_post();
		$state = bbp_backfill_mentions( 1 );
		$later = $this->historical_post();
		$queries = array();
		$collect = static function( $query ) use ( &$queries ) {
			$queries[] = $query;
			return $query;
		};
		add_filter( 'query', $collect );
		try {
			$preview = bbp_backfill_mentions( 1, false, true );
		} finally {
			remove_filter( 'query', $collect );
		}
		$this->assertSame( 1, $preview['scanned'] );
		$this->assertSame( $state['cursor'], $preview['start'] );
		$this->assertSame( $topic, $preview['cursor'] );
		$this->assertSame( $topic, $preview['upper'] );
		$this->assertFalse( $preview['more'] );
		$this->assertSame( $state, bbp_get_mention_backfill_status() );
		$this->assertSame( $preview, bbp_backfill_mentions( 1, false, true ) );
		$this->assertContains( 'SELECT ID, post_type FROM ' . bbp_db()->posts . ' WHERE ID > ' . $state['cursor'] . ' AND ID <= ' . $state['upper'] . ' ORDER BY ID ASC LIMIT 1', $queries );
		$this->assertStringNotContainsString( ' OFFSET ', implode( "\n", $queries ) );
		$this->assertStringNotContainsString( 'COUNT(', implode( "\n", $queries ) );
		$this->assertSame( array(), bbp_get_users_for_object( $later, '_bbp_mention' ) );

		$state = $this->finish();
		bbp_add_pending_upgrade( 'bbp-user-mentions' );
		$preview = bbp_backfill_mentions( 1, false, true );
		$this->assertSame( 0, $preview['scanned'] );
		$this->assertContains( 'bbp-user-mentions', bbp_get_pending_upgrades() );
		$preview = bbp_backfill_mentions( 100, true, true );
		$this->assertSame( 0, $preview['start'] );
		$this->assertSame( $later, $preview['upper'] );
		$this->assertSame( 1, $preview['added'] );
		$this->assertSame( 1, $preview['baselines'] );
		$this->assertSame( $state, bbp_get_mention_backfill_status() );
		$this->assertContains( 'bbp-user-mentions', bbp_get_pending_upgrades() );
	}

	public function test_dry_run_checks_for_more_posts_without_counting_the_history() {
		$topic = $this->historical_post();
		$queries = array();
		$collect = static function( $query ) use ( &$queries ) {
			$queries[] = $query;
			return $query;
		};
		add_filter( 'query', $collect );
		try {
			$preview = bbp_backfill_mentions( 1, false, true );
		} finally {
			remove_filter( 'query', $collect );
		}
		$this->assertTrue( $preview['more'] );
		$this->assertSame( 1, $preview['scanned'] );
		$this->assertFalse( get_option( '_bbp_mention_backfill' ) );
		$this->assertContains( 'SELECT ID, post_type FROM ' . bbp_db()->posts . ' WHERE ID > ' . $preview['cursor'] . ' AND ID <= ' . $topic . ' ORDER BY ID ASC LIMIT 1', $queries );
		foreach ( $queries as $query ) {
			$this->assertDoesNotMatchRegularExpression( '/COUNT\\s*\\(/i', $query );
		}
	}

	public function test_dry_run_handles_a_deleted_upper_boundary() {
		$topic = $this->historical_post();
		$last  = $this->historical_post();
		$state = bbp_backfill_mentions( 1 );
		wp_delete_post( $last, true );
		$preview = bbp_backfill_mentions( 100, false, true );

		$this->assertSame( $topic, $preview['cursor'] );
		$this->assertSame( $last, $preview['upper'] );
		$this->assertFalse( $preview['more'] );
		$this->assertSame( $state, bbp_get_mention_backfill_status() );
	}

	public function test_dry_run_uses_each_relationship_adapter_and_preserves_handled_ids() {
		$old = bbpress()->engagements;
		try {
			foreach ( array( 'BBP_User_Engagements_Meta', 'BBP_User_Engagements_Term', 'BBP_User_Engagements_User' ) as $class ) {
				bbpress()->engagements = new $class();
				$topic = $this->historical_post();
				$stale = $this->factory->user->create();
				bbp_add_user_to_object( $topic, $stale, '_bbp_mention' );
				$record = array( 'handled' => array( $stale, $this->recipient ) );
				update_post_meta( $topic, '_bbp_mention_notification', $record );
				$state = array( 'cursor' => $topic - 1, 'upper' => $topic, 'processed' => 0, 'complete' => false );
				update_option( '_bbp_mention_backfill', $state );
				$preview = bbp_backfill_mentions( 1, false, true );
				$this->assertSame( 1, $preview['added'], $class );
				$this->assertSame( 1, $preview['removed'], $class );
				$this->assertSame( 0, $preview['baselines'], $class );
				$this->assertSame( $record, get_post_meta( $topic, '_bbp_mention_notification', true ), $class );
				$this->assertSame( array( $stale ), bbp_get_users_for_object( $topic, '_bbp_mention' ), $class );
				$this->assertSame( $state, bbp_get_mention_backfill_status(), $class );
			}
		} finally {
			bbpress()->engagements = $old;
		}
	}

	public function test_dry_run_errors_do_not_repair_records_or_acquire_a_lease() {
		$topic = $this->historical_post();
		foreach ( array( 0, 1001 ) as $size ) {
			$this->assertWPError( bbp_backfill_mentions( $size, false, true ) );
		}
		update_post_meta( $topic, '_bbp_mention_notification', 'invalid' );
		$result = bbp_backfill_mentions( 100, false, true );
		$this->assertWPError( $result );
		$this->assertSame( 'bbp_mention_backfill_post', $result->get_error_code() );
		$this->assertSame( 'invalid', get_post_meta( $topic, '_bbp_mention_notification', true ) );
		$this->assertSame( array(), bbp_get_mention_backfill_status() );
		$this->assertSame( array(), bbp_get_users_for_object( $topic, '_bbp_mention' ) );
		$state = array( 'cursor' => 0, 'upper' => $topic, 'processed' => 0, 'complete' => false, 'token' => 'live-worker', 'expires' => time() + 300 );
		update_option( '_bbp_mention_backfill', $state );
		$result = bbp_backfill_mentions( 100, true, true );
		$this->assertWPError( $result );
		$this->assertSame( 'bbp_mention_backfill_busy', $result->get_error_code() );
		$this->assertSame( $state, bbp_get_mention_backfill_status() );
		update_option( '_bbp_mention_backfill', 'invalid' );
		$this->assertWPError( bbp_backfill_mentions( 100, false, true ) );
		$this->assertSame( 'invalid', bbp_get_mention_backfill_status() );
	}

	public function test_dry_run_query_failure_leaves_progress_untouched() {
		$topic = $this->historical_post();
		$state = bbp_backfill_mentions( 1 );
		$fail = static function( $query ) {
			return ( 0 === strpos( $query, 'SELECT ID, post_type FROM ' ) ) ? 'SELECT * FROM bbp_missing_dry_run_table' : $query;
		};
		$suppressed = bbp_db()->suppress_errors();
		add_filter( 'query', $fail );
		try {
			$result = bbp_backfill_mentions( 100, false, true );
			$this->assertWPError( $result );
			$this->assertSame( 'bbp_mention_backfill_query', $result->get_error_code() );
		} finally {
			remove_filter( 'query', $fail );
			bbp_db()->suppress_errors( $suppressed );
		}
		$this->assertSame( $state, bbp_get_mention_backfill_status() );
		$this->assertSame( array(), bbp_get_users_for_object( $topic, '_bbp_mention' ) );
	}

	public function test_both_admin_dry_run_actions_preserve_progress_pending_upgrades_and_caches() {
		$topic = $this->historical_post();
		bbp_add_pending_upgrade( 'bbp-user-mentions' );
		if ( ! function_exists( 'bbp_admin' ) ) {
			require_once BBP_PLUGIN_DIR . 'includes/admin/actions.php';
		}
		$admin = bbp_admin();
		$old_tools = $admin->tools;
		$old_notices = $admin->notices;
		$old_get = $_GET;
		$old_request = $_REQUEST;
		$old_method = isset( $_SERVER['REQUEST_METHOD'] ) ? $_SERVER['REQUEST_METHOD'] : null;
		$user = $this->factory->user->create( array( 'role' => 'administrator' ) );
		bbp_set_user_role( $user, bbp_get_keymaster_role() );
		$this->set_current_user( $user );
		bbp_register_default_repair_tools();
		$_SERVER['REQUEST_METHOD'] = 'GET';
		try {
			foreach ( array( 'repair' => 'bbp-mentions', 'upgrade' => 'bbp-user-mentions' ) as $type => $id ) {
				$_GET = array( 'page' => 'bbp-' . $type, 'action' => 'dry-run', 'checked' => array( $id ), '_wpnonce' => wp_create_nonce( 'bbpress-do-counts' ) );
				$_REQUEST = $_GET;
				$tool = $admin->tools[ $id ] + array( 'id' => $id );
				$this->assertSame( 'bbp_admin_' . $type . '_user_mentions_dry_run', bbp_get_admin_repair_tool_callback( $tool, 'dry-run' ) );
				$url = bbp_get_admin_repair_tool_action_url( $tool, 'dry-run' );
				$this->assertStringContainsString( 'page=bbp-' . $type, $url );
				$this->assertStringContainsString( 'action=dry-run', $url );
				wp_cache_set( 'preserve', 'cached value', 'mention-backfill-test' );
				$before = bbp_get_mention_backfill_status();
				bbp_admin_repair_handler();
				$this->assertSame( $before, bbp_get_mention_backfill_status() );
				$this->assertSame( 'cached value', wp_cache_get( 'preserve', 'mention-backfill-test' ) );
				$this->assertContains( 'bbp-user-mentions', bbp_get_pending_upgrades() );
				$this->assertSame( array(), bbp_get_users_for_object( $topic, '_bbp_mention' ) );
				$this->assertFalse( metadata_exists( 'post', $topic, '_bbp_mention_notification' ) );
				$this->assertEmpty( bbpress()->mention_notification_queue );
				$callback = bbp_get_admin_repair_tool_callback( $tool, 'dry-run' );
				$result = call_user_func( $callback );
				$this->assertSame( 0, $result[0] );
				$this->assertStringContainsString( 'Mentions to add: 1.', $result[1] );
				$this->assertStringContainsString( 'This preview reaches the end', $result[1] );
				$this->set_current_user( 0 );
				$result = call_user_func( $callback );
				$this->assertWPError( $result[1] );
				$this->set_current_user( $user );
			}
			$this->assertSame( array(), $this->mail );
		} finally {
			$admin->tools = $old_tools;
			$admin->notices = $old_notices;
			$_GET = $old_get;
			$_REQUEST = $old_request;
			if ( is_null( $old_method ) ) {
				unset( $_SERVER['REQUEST_METHOD'] );
			} else {
				$_SERVER['REQUEST_METHOD'] = $old_method;
			}
		}
	}
}
