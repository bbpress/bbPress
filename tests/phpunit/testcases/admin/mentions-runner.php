<?php

/**
 * Mentions integration with the shared upgrade runner.
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
		foreach ( array( '_bbp_mention_backfill', '_bbp_db_pending_upgrades', '_bbp_tools_run_repair', '_bbp_tools_run_upgrade', bbp_admin_repair_tool_batch_option_name( 'bbp-user-mentions' ) ) as $name ) {
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

	private function status() {
		list( $read, $checkpoint ) = bbp_admin_repair_tool_get_checkpoint( 'bbp-user-mentions' );
		$this->assertTrue( $read );
		if ( $checkpoint ) {
			return array(
				'cursor'    => $checkpoint['cursor'],
				'upper'     => $checkpoint['upper_id'],
				'processed' => $checkpoint['processed'],
				'complete'  => false
			);
		}
		$completed = get_option( '_bbp_mention_backfill', array() );
		return is_array( $completed ) ? $completed : array();
	}

	private function finish_worker() {
		for ( $attempt = 0; $attempt < 100; ++$attempt ) {
			$result = bbp_admin_upgrade_user_mentions_batch();
			$this->assertSame( 0, $result['code'] );
			if ( $result['complete'] ) {
				return $this->status();
			}
		}
		$this->fail( 'Mention upgrade did not finish.' );
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

	private function request( $type, $tool, $run_id, $action = 'run' ) {
		$_POST = $_REQUEST = array( 'type' => $type, 'toolAction' => $action, 'tool' => $tool, 'run' => $run_id, 'nonce' => wp_create_nonce( 'bbpress-tools-process' ) );
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
		return array( array( 'upgrade', 'bbp-user-mentions' ) );
	}

	public function test_mentions_are_registered_only_as_an_upgrade() {
		bbp_register_repair_tools();
		$this->assertArrayNotHasKey( 'bbp-mentions', bbp_get_admin_repair_tools( 'repair' ) );
		$this->assertArrayHasKey( 'bbp-user-mentions', bbp_get_admin_repair_tools( 'upgrade' ) );
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
		$this->assertSame( array(), $this->status() );
		$this->assertContains( 'bbp-user-mentions', bbp_get_pending_upgrades() );
		$run = bbp_admin_repair_tool_get_run_state( $type );
		$this->assertSame( array( $tool, $tail ), $run['tools'] );
		$first = array( 'processed' => 0, 'complete' => false );
		for ( $i = 0; $i < 10 && ! $first['complete']; ++$i ) {
			$response = $this->request( $type, $tool, $run['id'] );
			$this->assertTrue( $response['success'] );
			$next = $this->status();
			$this->assertLessThanOrEqual( 3, $next['processed'] - $first['processed'] );
			$first = $next;
		}
		$this->assertTrue( $first['complete'] );
		$this->assertSame( 0, $this->tail_calls );
		$run = bbp_admin_repair_tool_get_run_state( $type );
		$this->assertSame( array( $tail ), $run['tools'] );
		$this->begin( $type, array( $tail ), 'run', $run['id'] );
		$this->assertSame( 0, $this->tail_calls );
		$resumed = bbp_admin_repair_tool_get_run_state( $type );
		$this->assertNotSame( $run['id'], $resumed['id'] );
		$response = $this->request( $type, $tail, $resumed['id'] );
		$this->assertTrue( $response['success'] );
		$this->assertSame( 1, $this->tail_calls );
		$this->assertFalse( bbp_admin_repair_tool_get_run_state( $type ) );
		$this->assertNotContains( 'bbp-user-mentions', bbp_get_pending_upgrades() );
		$this->assertSame( array( $reader ), bbp_get_users_for_object( end( $posts ), '_bbp_mention' ) );
		bbp_shutdown();
		$this->assertSame( array(), $this->mail );
	}

	/** @dataProvider tool_types */
	public function test_dry_run_remains_read_only_and_uses_shared_notice_format( $type, $tool ) {
		$posts = $this->historical_posts();
		bbp_add_pending_upgrade( 'bbp-user-mentions' );
		$this->begin( $type, array( $tool ), 'dry-run' );
		$this->assertTrue( wp_script_is( 'bbp-admin-tools-js', 'enqueued' ) );
		$this->assertFalse( get_option( '_bbp_mention_backfill' ) );
		$this->assertFalse( bbp_admin_repair_tool_get_run_state( $type ) );
		$run = bbp_admin_repair_tool_get_run_state( $type, 'dry-run' );
		$this->assertSame( array( $tool ), $run['tools'] );
		for ( $i = 0; $i < 100; ++$i ) {
			$response = $this->request( $type, $tool, $run['id'], 'dry-run' );
			$this->assertTrue( $response['success'] );
			if ( $response['data']['complete'] ) {
				break;
			}
		}
		$this->assertTrue( $response['data']['complete'] );
		$this->assertGreaterThan( 0, $i );
		$this->assertFalse( bbp_admin_repair_tool_get_run_state( $type, 'dry-run' ) );
		$this->assertContains( 'bbp-user-mentions', bbp_get_pending_upgrades() );
		$this->assertStringContainsString( 'Dry run complete! No changes were made.', $response['data']['message'] );
		$this->assertStringContainsString( 'Estimated batches for a full Run:', $response['data']['message'] );
		foreach ( $posts as $post_id ) {
			$this->assertSame( array(), bbp_get_users_for_object( $post_id, '_bbp_mention' ) );
		}
		$this->assertSame( array(), $this->mail );
	}

	public function test_malformed_dry_run_totals_restart_without_losing_aggregate_counts() {
		$this->historical_posts();
		$fields   = array( 'posts', 'scanned', 'added', 'removed', 'baselines', 'batches' );
		$expected = bbp_admin_upgrade_user_mentions_batch( true );

		bbp_admin_repair_tool_execution_context( 'dry-run', 'malformed-mention-preview' );
		$option = bbp_admin_repair_tool_batch_option_name( 'bbp-user-mentions' );
		try {
			$result = bbp_admin_upgrade_user_mentions_batch( true );
			$this->assertFalse( $result['complete'] );
			$state = get_option( $option );
			$state['_preview_state']['totals']['posts'] = -1;
			update_option( $option, $state, false );

			for ( $i = 0; $i < 100; ++$i ) {
				$result = bbp_admin_upgrade_user_mentions_batch( true );
				if ( 0 === $i ) {
					$this->assertTrue( $result['restarted'] );
				}
				if ( $result['complete'] ) {
					break;
				}
			}
		} finally {
			bbp_admin_repair_tool_execution_context( 'run', '' );
			delete_option( $option );
		}

		$this->assertTrue( $result['complete'] );
		foreach ( $fields as $field ) {
			$this->assertSame( $expected[ $field ], $result[ $field ] );
		}
	}

	public function test_upgrade_callback_requires_upgrade_permission() {
		$this->set_current_user( 0 );
		$result = bbp_admin_upgrade_user_mentions_process( true );
		$this->assertSame( BBP_Admin_Batch_Worker::RESULT_FAILED, $result[0] );
		$this->assertWPError( $result[1] );
		$this->assertFalse( $result['success'] );
		$this->assertFalse( $result['complete'] );
	}

	public function test_backfill_errors_pause_the_shared_run_and_retain_pending_upgrade() {
		$progress = array( 'cursor' => 0, 'upper' => 100, 'processed' => 0, 'complete' => false, 'token' => 'owner', 'expires' => time() + 300 );
		update_option( '_bbp_mention_backfill', $progress );
		bbp_add_pending_upgrade( 'bbp-user-mentions' );
		$run = bbp_admin_repair_tool_save_run_state( 'upgrade', array( 'bbp-user-mentions' ) );
		$response = $this->request( 'upgrade', 'bbp-user-mentions', $run['id'] );
		$this->assertFalse( $response['success'] );
		$this->assertSame( $progress, $this->status() );
		$run = bbp_admin_repair_tool_get_run_state( 'upgrade' );
		$this->assertSame( 'error', $run['status'] );
		$this->assertContains( 'bbp-user-mentions', bbp_get_pending_upgrades() );
	}

	/**
	 * @ticket 3058
	 * @covers ::bbp_admin_upgrade_user_mentions
	 */
	public function test_cli_continuation_does_not_restart_a_completed_checkpoint() {
		$this->historical_posts( 1 );
		$completed = $this->finish_worker();
		$this->assertTrue( $completed['complete'] );
		$this->historical_posts( 1 );
		$_GET = array();

		bbp_admin_repair_tool_execution_context( 'run', 'cli-mention-continuation', true );
		try {
			$result = bbp_admin_upgrade_user_mentions();
		} finally {
			bbp_admin_repair_tool_execution_context( 'run', '' );
		}

		$this->assertTrue( $result['complete'] );
		$this->assertSame( $completed, $this->status() );
	}

	public static function resume_methods() {
		return array( 'AJAX continuation' => array( true ), 'page resume' => array( false ) );
	}

	/** @dataProvider resume_methods */
	public function test_pause_and_cli_completion_do_not_restart_an_active_scan( $ajax ) {
		$this->historical_posts();
		$this->begin( 'upgrade', array( 'bbp-user-mentions' ) );
		$run = bbp_admin_repair_tool_get_run_state( 'upgrade' );
		$response = $this->request( 'upgrade', 'bbp-user-mentions', $run['id'] );
		$this->assertTrue( $response['success'] );
		$progress = $this->status();
		bbp_admin_repair_tool_update_run_status( 'upgrade', $run['id'], 'paused' );
		$response = $this->request( 'upgrade', 'bbp-user-mentions', $run['id'] );
		$this->assertFalse( $response['success'] );
		$this->assertSame( $progress, $this->status() );
		$completed = $this->finish_worker();
		$this->assertTrue( $completed['complete'] );
		$new = $this->historical_posts( 1 );
		if ( $ajax ) {
			bbp_admin_repair_tool_update_run_status( 'upgrade', $run['id'], 'running' );
			$response = $this->request( 'upgrade', 'bbp-user-mentions', $run['id'] );
			$this->assertTrue( $response['success'] );
		} else {
			$this->begin( 'upgrade', array( 'bbp-user-mentions' ), 'run', $run['id'] );
			$resumed = bbp_admin_repair_tool_get_run_state( 'upgrade' );
			$this->assertNotSame( $run['id'], $resumed['id'] );
			$response = $this->request( 'upgrade', 'bbp-user-mentions', $resumed['id'] );
			$this->assertTrue( $response['success'] );
		}
		$this->assertSame( $completed, $this->status() );
		$this->assertFalse( bbp_admin_repair_tool_get_run_state( 'upgrade' ) );

		// A later explicit Run starts a fresh pass with a new upper post ID.
		$this->begin( 'upgrade', array( 'bbp-user-mentions' ) );
		$run = bbp_admin_repair_tool_get_run_state( 'upgrade' );
		$response = $this->request( 'upgrade', 'bbp-user-mentions', $run['id'] );
		$this->assertTrue( $response['success'] );
		$restarted = $this->status();
		$this->assertSame( $new[0], $restarted['upper'] );
		$this->assertFalse( $restarted['complete'] );
		$this->assertSame( array(), $this->mail );
	}
}
