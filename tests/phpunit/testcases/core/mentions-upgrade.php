<?php

/**
 * @group core
 * @group update
 * @group mentions
 * @ticket BBP2773
 */
class BBP_Tests_Core_Mentions_Upgrade extends BBP_UnitTestCase {
	private $options = array();
	private $user;
	private $recipient;
	private $posts;
	private $mail = array();
	private $scans = array();
	private $batch_size = 1;

	public function setUp(): void {
		parent::setUp();
		$this->user = get_current_user_id();
		$this->set_current_user( 0 );
		foreach ( array( '_bbp_db_version', '_bbp_db_pending_upgrades', '_bbp_mention_backfill' ) as $name ) {
			$this->options[ $name ] = get_option( $name, null );
			delete_option( $name );
		}
		update_option( '_bbp_db_version', 264 );
		$this->recipient = $this->factory->user->create( array( 'role' => bbp_get_participant_role(), 'user_nicename' => 'auto-mention-reader' ) );
		update_user_meta( $this->recipient, '_bbp_mention_email', 'yes' );
		$forum = $this->factory->forum->create();
		remove_action( 'wp_after_insert_post', 'bbp_save_post_mentions', 20 );
		try {
			$topic = $this->factory->topic->create( array( 'post_parent' => $forum, 'post_content' => '@auto-mention-reader' ) );
			$reply = $this->factory->reply->create( array( 'post_parent' => $topic, 'post_content' => '@auto-mention-reader', 'reply_meta' => array( 'topic_id' => $topic, 'forum_id' => $forum ) ) );
			$other = $this->factory->topic->create( array( 'post_parent' => $forum, 'post_content' => '@auto-mention-reader' ) );
			$this->posts = array( $topic, $reply, $other );
		} finally {
			add_action( 'wp_after_insert_post', 'bbp_save_post_mentions', 20, 4 );
		}
		bbpress()->mention_notification_queue = array();
		bbpress()->mention_subscription_recipients = array();
		add_filter( 'bbp_is_large_install', '__return_false' );
		add_filter( 'bbp_admin_repair_tool_batch_size', array( $this, 'batch_size' ), 10, 2 );
		add_filter( 'query', array( $this, 'record_scan' ) );
		add_filter( 'pre_wp_mail', array( $this, 'capture_mail' ), 10, 2 );
	}

	public function tearDown(): void {
		remove_filter( 'bbp_is_large_install', '__return_false' );
		remove_filter( 'bbp_admin_repair_tool_batch_size', array( $this, 'batch_size' ), 10 );
		remove_filter( 'query', array( $this, 'record_scan' ) );
		remove_filter( 'pre_wp_mail', array( $this, 'capture_mail' ), 10 );
		foreach ( $this->options as $name => $value ) {
			if ( null === $value ) {
				delete_option( $name );
			} else {
				update_option( $name, $value );
			}
		}
		bbpress()->mention_notification_queue = array();
		bbpress()->mention_subscription_recipients = array();
		$this->set_current_user( $this->user );
		parent::tearDown();
	}

	public function batch_size( $size, $tool_id ) {
		return ( 'bbp-user-mentions' === $tool_id ) ? $this->batch_size : $size;
	}

	public function record_scan( $sql ) {
		if ( 0 === strpos( $sql, 'SELECT ID, post_type FROM ' . bbp_db()->posts ) ) {
			$this->scans[] = $sql;
		}
		return $sql;
	}

	public function capture_mail( $return, $atts ) {
		$this->mail[] = $atts;
		return true;
	}

	private function assert_completed_without_email() {
		$state = bbp_get_mention_backfill_status();
		$this->assertTrue( $state['complete'] );
		$this->assertSame( 3, $state['processed'] );
		$this->assertNotContains( 'bbp-user-mentions', bbp_get_pending_upgrades() );
		foreach ( $this->posts as $post_id ) {
			$this->assertSame( array( $this->recipient ), bbp_get_users_for_object( $post_id, '_bbp_mention' ) );
			$this->assertSame( array( 'handled' => array( $this->recipient ) ), get_post_meta( $post_id, '_bbp_mention_notification', true ) );
			wp_update_post( array( 'ID' => $post_id, 'post_content' => 'Still @auto-mention-reader' ) );
		}
		bbp_shutdown();
		$this->assertSame( array(), $this->mail );
		$this->assertTrue( bbp_is_user_mention_email_enabled( $this->recipient ) );
	}

	private function resume_in_tools() {
		$admin = $this->factory->user->create( array( 'role' => 'administrator' ) );
		bbp_set_user_role( $admin, bbp_get_keymaster_role() );
		$this->set_current_user( $admin );
		for ( $batch = 0; $batch < 20; ++$batch ) {
			$result = bbp_admin_upgrade_user_mentions();
			$this->assertSame( 0, $result[0] );
			if ( $result['complete'] ) {
				return;
			}
		}
		$this->fail( 'The manual upgrade did not finish the saved automatic scan.' );
	}

	public function test_version_update_completes_small_site_batches_without_email() {
		bbp_version_updater();
		$this->assertGreaterThan( 1, count( $this->scans ) );
		$this->assertSame( 270, (int) bbp_get_db_version_raw() );
		$this->assert_completed_without_email();
		$this->scans = array();
		$state = bbp_get_mention_backfill_status();
		bbp_version_updater();
		$result = bbp_admin_upgrade_user_mentions_automatically();
		$this->assertTrue( $result['complete'] );
		$this->assertSame( array(), $this->scans );
		$this->assertSame( $state, bbp_get_mention_backfill_status() );
	}

	/**
	 * @dataProvider automatic_limits
	 */
	public function test_automatic_limits_leave_saved_progress_pending_for_the_tools( $hook, $limit ) {
		$callback = static function() use ( $limit ) { return $limit; };
		add_filter( $hook, $callback );
		try {
			bbp_version_updater();
		} finally {
			remove_filter( $hook, $callback );
		}
		$state = bbp_get_mention_backfill_status();
		$this->assertCount( 1, $this->scans );
		$this->assertFalse( $state['complete'] );
		$this->assertGreaterThan( 0, $state['cursor'] );
		$this->assertLessThan( $state['upper'], $state['cursor'] );
		$this->assertArrayNotHasKey( 'token', $state );
		$this->assertContains( 'bbp-user-mentions', bbp_get_pending_upgrades() );
		$this->assertSame( 270, (int) bbp_get_db_version_raw() );
		$this->scans = array();
		$this->resume_in_tools();
		$this->assertStringContainsString( 'WHERE ID > ' . $state['cursor'] . ' ', $this->scans[0] );
		$this->assert_completed_without_email();
	}

	public static function automatic_limits() {
		return array(
			'batch limit' => array( 'bbp_admin_repair_tool_automatic_batch_limit', 1 ),
			'time budget' => array( 'bbp_admin_repair_tool_automatic_time_limit', 0.000000001 ),
		);
	}

	public function test_failed_automatic_write_stays_pending_and_retries_the_same_post() {
		$this->batch_size = 100;
		$fail = static function( $check, $post_id, $key ) {
			return ( '_bbp_mention' === $key ) ? false : $check;
		};
		add_filter( 'add_post_metadata', $fail, 10, 3 );
		try {
			bbp_version_updater();
		} finally {
			remove_filter( 'add_post_metadata', $fail, 10 );
		}
		$state = bbp_get_mention_backfill_status();
		$this->assertFalse( $state['complete'] );
		$this->assertCount( 1, $this->scans );
		$this->assertLessThan( $this->posts[0], $state['cursor'] );
		$this->assertArrayNotHasKey( 'token', $state );
		$this->assertContains( 'bbp-user-mentions', bbp_get_pending_upgrades() );
		$this->scans = array();
		$this->resume_in_tools();
		$this->assertStringContainsString( 'WHERE ID > ' . $state['cursor'] . ' ', $this->scans[0] );
		$this->assert_completed_without_email();
	}

	public function test_another_worker_keeps_the_automatic_upgrade_pending() {
		$state = array( 'cursor' => 0, 'upper' => max( $this->posts ), 'processed' => 0, 'complete' => false, 'token' => 'other-worker', 'expires' => time() + 300 );
		update_option( '_bbp_mention_backfill', $state, false );
		bbp_version_updater();
		$this->assertSame( $state, bbp_get_mention_backfill_status() );
		$this->assertSame( array(), $this->scans );
		$this->assertContains( 'bbp-user-mentions', bbp_get_pending_upgrades() );
		$this->assertSame( 270, (int) bbp_get_db_version_raw() );
	}
}
