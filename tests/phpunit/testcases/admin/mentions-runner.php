<?php

/**
 * Mentions integration with the shared repair and upgrade runner.
 *
 * @group admin
 * @group mentions
 * @group tools
 * @ticket BBP2773
 */
class BBP_Tests_Admin_Mentions_Runner extends BBP_UnitTestCase {
	private $globals;
	private $user;
	private $scripts;
	private $tools;
	private $notices;
	private $options = array();
	private $mail = array();
	private $registered_default = false;
	private $tail_calls = 0;

	public function setUp(): void {
		parent::setUp();
		require_once bbpress()->includes_dir . 'admin/actions.php';
		bbp_admin();
		$this->globals = array( $_GET, $_POST, $_REQUEST, isset( $_SERVER['REQUEST_METHOD'] ) ? $_SERVER['REQUEST_METHOD'] : null );
		$this->user    = get_current_user_id();
		$this->scripts = wp_scripts();
		$this->tools   = bbp_admin()->tools;
		$this->notices = bbp_admin()->notices;
		foreach ( array( '_bbp_mention_backfill', '_bbp_db_pending_upgrades', '_bbp_tools_run_repair', '_bbp_tools_run_upgrade', '_bbp_tools_run_status_repair', '_bbp_tools_run_status_upgrade' ) as $name ) {
			$this->options[ $name ] = get_option( $name, null );
			delete_option( $name );
		}
		if ( false === has_action( 'bbp_register_repair_tools', 'bbp_register_default_repair_tools' ) ) {
			add_action( 'bbp_register_repair_tools', 'bbp_register_default_repair_tools' );
			$this->registered_default = true;
		}
		$GLOBALS['wp_scripts'] = new WP_Scripts();
		bbp_admin()->register_admin_scripts();
		bbp_admin()->notices = array();
		$user = $this->factory->user->create( array( 'role' => 'administrator' ) );
		bbp_set_user_role( $user, bbp_get_keymaster_role() );
		$this->set_current_user( $user );
		add_filter( 'pre_wp_mail', array( $this, 'capture_mail' ), 10, 2 );
		add_filter( 'bbp_admin_repair_tool_batch_size', array( $this, 'batch_size' ) );
		add_action( 'bbp_register_repair_tools', array( $this, 'register_tail' ) );
	}

	public function tearDown(): void {
		remove_filter( 'pre_wp_mail', array( $this, 'capture_mail' ), 10 );
		remove_filter( 'bbp_admin_repair_tool_batch_size', array( $this, 'batch_size' ) );
		remove_action( 'bbp_register_repair_tools', array( $this, 'register_tail' ) );
		if ( $this->registered_default ) {
			remove_action( 'bbp_register_repair_tools', 'bbp_register_default_repair_tools' );
		}
		list( $_GET, $_POST, $_REQUEST, $_SERVER['REQUEST_METHOD'] ) = $this->globals;
		if ( null === $_SERVER['REQUEST_METHOD'] ) {
			unset( $_SERVER['REQUEST_METHOD'] );
		}
		$this->set_current_user( $this->user );
		$GLOBALS['wp_scripts'] = $this->scripts;
		bbp_admin()->tools   = $this->tools;
		bbp_admin()->notices = $this->notices;
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

	public function batch_size() {
		return 3;
	}

	public function register_tail() {
		foreach ( array( 'repair', 'upgrade' ) as $type ) {
			bbp_register_repair_tool( array(
				'id'          => 'mentions-tail-' . $type,
				'type'        => $type,
				'title'       => 'Next tool',
				'priority'    => 999,
				'flush_cache' => false,
				'callback'    => array( $this, 'run_tail' )
			) );
		}
	}

	public function run_tail() {
		++$this->tail_calls;
		return array( 0, 'Done.' );
	}

	private function historical_posts( $count = 7 ) {
		remove_action( 'wp_after_insert_post', 'bbp_save_post_mentions', 20 );
		try {
			return $this->factory->post->create_many( $count, array( 'post_type' => bbp_get_topic_post_type(), 'post_status' => 'publish', 'post_content' => '@runner-reader' ) );
		} finally {
			add_action( 'wp_after_insert_post', 'bbp_save_post_mentions', 20, 4 );
		}
	}

	private function begin( $type, $ids, $action = 'run', $run_id = '' ) {
		bbp_register_repair_tools();
		$_GET = array( 'page' => 'bbp-' . $type, 'action' => $action, 'checked' => $ids, '_wpnonce' => wp_create_nonce( 'bbpress-do-counts' ) );
		if ( $run_id ) {
			$_GET['bbp-tools-run'] = $run_id;
		}
		$_REQUEST = $_GET;
		$_SERVER['REQUEST_METHOD'] = 'GET';
		bbp_admin_repair_handler();
	}

	private function request( $type, $tool, $run_id ) {
		$_POST = $_REQUEST = array( 'type' => $type, 'tool' => $tool, 'run' => $run_id, 'nonce' => wp_create_nonce( 'bbpress-tools-process' ) );
		$_SERVER['REQUEST_METHOD'] = 'POST';
		$handler = static function() {
			return static function( $message ) {
				throw new WPDieException( (string) $message );
			};
		};
		add_filter( 'wp_die_ajax_handler', $handler );
		add_filter( 'wp_doing_ajax', '__return_true' );
		ob_start();
		try {
			bbp_admin_repair_ajax_handler();
		} catch ( WPDieException $error ) {
			return json_decode( ob_get_contents(), true );
		} finally {
			ob_end_clean();
			remove_filter( 'wp_die_ajax_handler', $handler );
			remove_filter( 'wp_doing_ajax', '__return_true' );
		}
	}

	public static function tool_types() {
		return array( array( 'repair', 'bbp-mentions' ), array( 'upgrade', 'bbp-user-mentions' ) );
	}

	/** @dataProvider tool_types */
	public function test_mentions_use_shared_batches_and_preserve_bulk_order( $type, $tool ) {
		$reader = $this->factory->user->create( array( 'user_nicename' => 'runner-reader' ) );
		$posts  = $this->historical_posts();
		$tail   = 'mentions-tail-' . $type;
		bbp_add_pending_upgrade( 'bbp-user-mentions' );
		wp_cache_set( 'keep', 'cached', 'mention-runner' );
		$this->begin( $type, array( $tool, $tail ) );
		$this->assertTrue( wp_script_is( 'bbp-admin-tools-js', 'enqueued' ) );
		$this->assertFalse( wp_script_is( 'bbp-admin-mentions-js', 'registered' ) );
		$this->assertSame( 'cached', wp_cache_get( 'keep', 'mention-runner' ) );
		$first = bbp_get_mention_backfill_status();
		$this->assertLessThanOrEqual( 3, $first['processed'] );
		$this->assertFalse( $first['complete'] );
		$this->assertContains( 'bbp-user-mentions', bbp_get_pending_upgrades() );
		$run = bbp_admin_repair_tool_get_run_state( $type );
		$this->assertSame( array( $tool, $tail ), $run['tools'] );
		for ( $i = 0; $i < 10 && ! $first['complete']; ++$i ) {
			$response = $this->request( $type, $tool, $run['id'] );
			$this->assertTrue( $response['success'] );
			$next = bbp_get_mention_backfill_status();
			$this->assertLessThanOrEqual( 3, $next['processed'] - $first['processed'] );
			$first = $next;
		}
		$this->assertTrue( $first['complete'] );
		$this->assertSame( 0, $this->tail_calls );
		$run = bbp_admin_repair_tool_get_run_state( $type );
		$this->assertSame( array( $tail ), $run['tools'] );
		$this->begin( $type, array( $tail ), 'run', $run['id'] );
		$this->assertSame( 1, $this->tail_calls );
		$this->assertFalse( bbp_admin_repair_tool_get_run_state( $type ) );
		$this->assertNotContains( 'bbp-user-mentions', bbp_get_pending_upgrades() );
		$this->assertSame( array( $reader ), bbp_get_users_for_object( end( $posts ), '_bbp_mention' ) );
		bbp_shutdown();
		$this->assertSame( array(), $this->mail );
	}

	/** @dataProvider tool_types */
	public function test_dry_run_remains_read_only_and_uses_shared_notice_format( $type, $tool ) {
		$this->historical_posts();
		bbp_add_pending_upgrade( 'bbp-user-mentions' );
		$this->begin( $type, array( $tool ), 'dry-run' );
		$this->assertFalse( wp_script_is( 'bbp-admin-tools-js', 'enqueued' ) );
		$this->assertFalse( get_option( '_bbp_mention_backfill' ) );
		$this->assertFalse( bbp_admin_repair_tool_get_run_state( $type ) );
		$this->assertContains( 'bbp-user-mentions', bbp_get_pending_upgrades() );
		$notice = end( bbp_admin()->notices );
		$this->assertStringContainsString( 'Dry run complete! No changes were made.', $notice );
		$this->assertStringContainsString( 'More posts remain.', $notice );
		$this->assertStringContainsString( '<br>', $notice );
		$this->assertSame( array(), $this->mail );
	}

	public function test_backfill_errors_pause_the_shared_run_and_retain_pending_upgrade() {
		$progress = array( 'cursor' => 0, 'upper' => 100, 'processed' => 0, 'complete' => false, 'token' => 'owner', 'expires' => time() + 300 );
		update_option( '_bbp_mention_backfill', $progress );
		bbp_add_pending_upgrade( 'bbp-user-mentions' );
		$run = bbp_admin_repair_tool_save_run_state( 'upgrade', array( 'bbp-user-mentions' ) );
		$response = $this->request( 'upgrade', 'bbp-user-mentions', $run['id'] );
		$this->assertFalse( $response['success'] );
		$this->assertSame( $progress, bbp_get_mention_backfill_status() );
		$run = bbp_admin_repair_tool_get_run_state( 'upgrade' );
		$this->assertSame( 'error', $run['status'] );
		$this->assertContains( 'bbp-user-mentions', bbp_get_pending_upgrades() );
	}

	public static function resume_methods() {
		return array( 'AJAX continuation' => array( true ), 'page resume' => array( false ) );
	}

	/** @dataProvider resume_methods */
	public function test_pause_and_cli_completion_do_not_restart_an_active_scan( $ajax ) {
		$this->historical_posts();
		$this->begin( 'repair', array( 'bbp-mentions' ) );
		$run = bbp_admin_repair_tool_get_run_state( 'repair' );
		$progress = bbp_get_mention_backfill_status();
		bbp_admin_repair_tool_update_run_status( 'repair', $run['id'], 'paused' );
		$response = $this->request( 'repair', 'bbp-mentions', $run['id'] );
		$this->assertFalse( $response['success'] );
		$this->assertSame( $progress, bbp_get_mention_backfill_status() );
		$completed = bbp_backfill_mentions( 100 );
		$this->assertTrue( $completed['complete'] );
		$new = $this->historical_posts( 1 );
		if ( $ajax ) {
			bbp_admin_repair_tool_update_run_status( 'repair', $run['id'], 'running' );
			$response = $this->request( 'repair', 'bbp-mentions', $run['id'] );
			$this->assertTrue( $response['success'] );
		} else {
			$this->begin( 'repair', array( 'bbp-mentions' ), 'run', $run['id'] );
		}
		$this->assertSame( $completed, bbp_get_mention_backfill_status() );
		$this->assertFalse( bbp_admin_repair_tool_get_run_state( 'repair' ) );

		// A later explicit Run starts a fresh pass with a new upper post ID.
		$this->begin( 'repair', array( 'bbp-mentions' ) );
		$restarted = bbp_get_mention_backfill_status();
		$this->assertSame( $new[0], $restarted['upper'] );
		$this->assertFalse( $restarted['complete'] );
		$this->assertSame( array(), $this->mail );
	}
}
