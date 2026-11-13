<?php

/**
 * Exception used to capture the end of a tools AJAX request.
 */
class BBP_Tests_Admin_Tools_Runner_Ajax_Exit extends Exception {
}

/**
 * Tests for resumable admin repair and upgrade tools.
 *
 * @group admin
 * @group tools
 */
class BBP_Tests_Admin_Tools_Runner extends BBP_UnitTestCase {

	private $old_get;
	private $old_post;
	private $old_request;
	private $old_request_method;
	private $old_tools;
	private $old_notices;
	private $old_pending;
	private $old_repair_run;
	private $old_upgrade_run;
	private $old_repair_dry_run;
	private $old_upgrade_dry_run;
	private $old_user;
	private $old_scripts;
	private $registered_default = false;
	private $registered = false;
	private $registered_legacy = false;
	private $registration_calls = 0;
	private $calls = array();

	public function setUp(): void {
		parent::setUp();

		require_once BBP_PLUGIN_DIR . 'includes/admin/actions.php';
		require_once BBP_PLUGIN_DIR . 'includes/admin/tools/common.php';
		require_once BBP_PLUGIN_DIR . 'includes/admin/tools.php';

		if ( false === has_action( 'bbp_register_repair_tools', 'bbp_register_default_repair_tools' ) ) {
			add_action( 'bbp_register_repair_tools', 'bbp_register_default_repair_tools' );
			$this->registered_default = true;
		}

		bbp_admin();

		$this->old_get                = $_GET;
		$this->old_post               = $_POST;
		$this->old_request            = $_REQUEST;
		$this->old_tools              = bbp_admin()->tools;
		$this->old_notices            = bbp_admin()->notices;
		$this->old_pending            = get_option( '_bbp_db_pending_upgrades', null );
		$this->old_repair_run         = get_option( '_bbp_tools_run_repair', null );
		$this->old_upgrade_run        = get_option( '_bbp_tools_run_upgrade', null );
		$this->old_repair_dry_run     = get_option( '_bbp_tools_dry_run_repair', null );
		$this->old_upgrade_dry_run    = get_option( '_bbp_tools_dry_run_upgrade', null );
		$this->old_user               = get_current_user_id();
		$this->old_scripts            = clone wp_scripts();
		$this->old_request_method     = isset( $_SERVER['REQUEST_METHOD'] )
			? $_SERVER['REQUEST_METHOD']
			: null;

		$user_id = $this->factory->user->create( array( 'role' => 'administrator' ) );
		bbp_set_user_role( $user_id, bbp_get_keymaster_role() );
		$this->set_current_user( $user_id );

		bbp_admin()->tools   = array();
		bbp_admin()->notices = array();
		bbp_admin()->register_admin_scripts();
	}

	public function tearDown(): void {
		if ( $this->registered ) {
			remove_action( 'bbp_register_repair_tools', array( $this, 'register_tools' ) );
		}
		if ( $this->registered_default ) {
			remove_action( 'bbp_register_repair_tools', 'bbp_register_default_repair_tools' );
		}
		if ( $this->registered_legacy ) {
			remove_action( 'bbp_register_repair_tools', array( $this, 'register_legacy_tool' ), 5 );
		}

		$_GET                  = $this->old_get;
		$_POST                 = $this->old_post;
		$_REQUEST              = $this->old_request;
		bbp_admin()->tools     = $this->old_tools;
		bbp_admin()->notices   = $this->old_notices;
		$GLOBALS['wp_scripts'] = $this->old_scripts;
		$this->set_current_user( $this->old_user );

		if ( is_null( $this->old_request_method ) ) {
			unset( $_SERVER['REQUEST_METHOD'] );
		} else {
			$_SERVER['REQUEST_METHOD'] = $this->old_request_method;
		}

		if ( null === $this->old_pending ) {
			delete_option( '_bbp_db_pending_upgrades' );
		} else {
			update_option( '_bbp_db_pending_upgrades', $this->old_pending );
		}
		if ( null === $this->old_repair_run ) {
			delete_option( '_bbp_tools_run_repair' );
		} else {
			update_option( '_bbp_tools_run_repair', $this->old_repair_run );
		}
		if ( null === $this->old_upgrade_run ) {
			delete_option( '_bbp_tools_run_upgrade' );
		} else {
			update_option( '_bbp_tools_run_upgrade', $this->old_upgrade_run );
		}
		if ( null === $this->old_repair_dry_run ) {
			delete_option( '_bbp_tools_dry_run_repair' );
		} else {
			update_option( '_bbp_tools_dry_run_repair', $this->old_repair_dry_run );
		}
		if ( null === $this->old_upgrade_dry_run ) {
			delete_option( '_bbp_tools_dry_run_upgrade' );
		} else {
			update_option( '_bbp_tools_dry_run_upgrade', $this->old_upgrade_dry_run );
		}

		parent::tearDown();
	}

	/**
	 * @ticket 3777
	 * @covers ::bbp_admin_repair_tool_normalize_result
	 * @covers ::bbp_admin_repair_tool_result_message
	 * @covers ::bbp_admin_repair_tool_result_detail
	 * @covers ::bbp_admin_repair_tool_notice_message
	 */
	public function test_result_contract_preserves_legacy_callbacks_and_allows_batches() {
		$this->assertSame(
			array( 'success' => true, 'complete' => true, 'message' => 'Complete.', 'batches' => 0 ),
			bbp_admin_repair_tool_normalize_result( array( 0, 'Complete.' ) )
		);
		$this->assertSame(
			array( 'success' => true, 'complete' => true, 'message' => 'Nothing to do.', 'batches' => 0 ),
			bbp_admin_repair_tool_normalize_result( array( 1, 'Nothing to do.' ) )
		);
		$this->assertSame(
			array( 'success' => true, 'complete' => false, 'message' => 'More.', 'batches' => 0 ),
			bbp_admin_repair_tool_normalize_result( array( 0, 'More.', 'complete' => false ) )
		);
		$this->assertSame(
			array( 'success' => false, 'complete' => false, 'message' => 'Stopped.', 'batches' => 0 ),
			bbp_admin_repair_tool_normalize_result( array( 0, 'Stopped.', 'success' => false, 'complete' => false ) )
		);
		$this->assertFalse( bbp_admin_repair_tool_normalize_result( array( 0 ) ) );
		$this->assertSame( 'Error.', bbp_admin_repair_tool_result_message( new WP_Error( 'test', 'Error.' ) ) );
		$this->assertSame(
			'<strong>Upgrade user topic favorites</strong>',
			bbp_admin_repair_tool_notice_message(
				array( 'title' => 'Upgrade user topic favorites' ),
				'Upgrading user favorites&hellip;'
			)
		);
		$this->assertSame(
			'<strong>Upgrade user topic favorites</strong><br>0 favorite relationships to upgrade.',
			bbp_admin_repair_tool_notice_message(
				array( 'title' => 'Upgrade user topic favorites' ),
				'Upgrading user favorites&hellip; 0 favorite relationships to upgrade.'
			)
		);
	}

	/**
	 * @ticket 3777
	 * @covers ::bbp_admin_repair_ajax_handler
	 */
	public function test_run_and_dry_run_use_the_same_explicit_success_contract() {
		$tool_id = 'bbp-test-numeric-code';
		$callback = static function () {
			return array( 2, 'Complete without an explicit failure.' );
		};
		$register = static function () use ( $tool_id, $callback ) {
			bbp_register_repair_tool(
				array(
					'id'        => $tool_id,
					'type'      => 'upgrade',
					'title'     => 'Test numeric code',
					'priority'  => 10,
					'callbacks' => array(
						'run'     => $callback,
						'dry-run' => $callback,
					)
				)
			);
		};
		add_action( 'bbp_register_repair_tools', $register );
		$register();

		try {
			$nonce = wp_create_nonce( 'bbpress-tools-process' );
			$run   = bbp_admin_repair_tool_save_run_state( 'upgrade', array( $tool_id ) );
			$response = $this->ajax( 'upgrade', $tool_id, $nonce, 'POST', $run['id'] );
			$this->assertTrue( $response['success'], wp_json_encode( $response ) );

			$preview = bbp_admin_repair_tool_save_run_state( 'upgrade', array( $tool_id ), '', 'running', false, 'dry-run' );
			$response = $this->ajax( 'upgrade', $tool_id, $nonce, 'POST', $preview['id'], 'dry-run' );
			$this->assertTrue( $response['success'], wp_json_encode( $response ) );
		} finally {
			remove_action( 'bbp_register_repair_tools', $register );
		}
	}

	/**
	 * @ticket 3777
	 * @covers ::bbp_register_repair_tools
	 */
	public function test_repair_tools_include_action_registrations() {
		$this->add_registration_action();
		bbp_register_repair_tools();

		$this->assertArrayHasKey( 'bbp-sync-topic-meta', bbp_admin()->tools );
		$this->assertArrayHasKey( 'bbp-test-batch-one', bbp_admin()->tools );
		$this->assertArrayHasKey( 'bbp-test-batch-two', bbp_admin()->tools );
		$this->assertFalse( bbp_admin_repair_tool_flushes_cache( bbp_admin()->tools['bbp-test-batch-one'] ) );
	}

	/**
	 * @ticket 3777
	 * @covers ::bbp_admin_repair_handler
	 */
	public function test_legacy_nothing_to_do_result_clears_pending_and_continues_bulk_run() {
		$register = function() {
			bbp_register_repair_tool(
				array(
					'id'          => 'bbp-test-noop',
					'type'        => 'upgrade',
					'title'       => 'Test no-op',
					'priority'    => 10,
					'flush_cache' => false,
					'callback'    => function () {
						$this->calls[] = 'noop';
						return array( 1, 'Nothing to upgrade.' );
					}
				)
			);
			bbp_register_repair_tool(
				array(
					'id'          => 'bbp-test-after-noop',
					'type'        => 'upgrade',
					'title'       => 'Test after no-op',
					'priority'    => 11,
					'flush_cache' => false,
					'callback'    => function () {
						$this->calls[] = 'after-noop';
						return array( 0, 'Complete.' );
					}
				)
			);
		};
		add_action( 'bbp_register_repair_tools', $register );
		$register();

		update_option( '_bbp_db_pending_upgrades', array( 'bbp-test-noop', 'bbp-test-after-noop' ) );
		$_GET = array(
			'page'     => 'bbp-upgrade',
			'action'   => 'run',
			'checked'  => array( 'bbp-test-noop', 'bbp-test-after-noop' ),
			'_wpnonce' => wp_create_nonce( 'bbpress-do-counts' )
		);
		$_REQUEST                  = $_GET;
		$_SERVER['REQUEST_METHOD'] = 'GET';

		try {
			bbp_admin_repair_handler();
			$settings = $this->runner_settings();
			$this->assertSame( array(), $this->calls );
			$this->assertTrue( $this->ajax( 'upgrade', 'bbp-test-noop', $settings['nonce'], 'POST', $settings['run'] )['success'] );
			$this->assertTrue( $this->ajax( 'upgrade', 'bbp-test-after-noop', $settings['nonce'], 'POST', $settings['run'] )['success'] );
		} finally {
			remove_action( 'bbp_register_repair_tools', $register );
		}

		$this->assertSame( array( 'noop', 'after-noop' ), $this->calls );
		$this->assertSame( array(), bbp_get_pending_upgrades() );
	}

	/**
	 * @ticket 3777
	 * @covers ::bbp_admin_repair_tool_lock
	 * @covers ::bbp_admin_repair_tool_unlock
	 */
	public function test_repair_tool_lock_prevents_concurrent_batches_and_recovers_when_stale() {
		$lock = bbp_admin_repair_tool_lock( 'bbp-test-batch-one' );
		$this->assertIsArray( $lock );
		$this->assertSame( $lock['value'], get_option( $lock['name'] ) );
		$this->assertFalse( bbp_admin_repair_tool_lock( 'bbp-test-batch-one' ) );

		update_option( $lock['name'], ( time() - ( 6 * MINUTE_IN_SECONDS ) ) . '|stale' );
		$recovered = bbp_admin_repair_tool_lock( 'bbp-test-batch-one' );
		$this->assertSame( $lock['name'], $recovered['name'] );
		$this->assertNotSame( $lock['value'], $recovered['value'] );

		bbp_admin_repair_tool_unlock( $lock );
		$this->assertSame( $recovered['value'], get_option( $recovered['name'] ) );

		bbp_admin_repair_tool_unlock( $recovered );
		$this->assertFalse( get_option( $recovered['name'] ) );
	}

	/**
	 * @ticket 3777
	 * @covers ::bbp_admin_repair_tool_run_state_option_name
	 * @covers ::bbp_admin_repair_tool_get_run_state
	 * @covers ::bbp_admin_repair_tool_save_run_state
	 * @covers ::bbp_admin_repair_tool_update_run_status
	 * @covers ::bbp_admin_repair_tool_delete_run_state
	 */
	public function test_repair_tool_run_state_preserves_queue_ownership_and_status() {
		$state = bbp_admin_repair_tool_save_run_state(
			'upgrade',
			array( 'BBP-TEST-BATCH-ONE', 'bbp-test-batch-one', '', 'bbp-test-batch-two' )
		);

		$this->assertNotEmpty( $state['id'] );
		$this->assertSame( 'running', $state['status'] );
		$this->assertSame( array( 'bbp-test-batch-one', 'bbp-test-batch-two' ), $state['tools'] );
		$this->assertSame( array( 'id', 'tools', 'status', 'updated' ), array_keys( $state ) );
		$this->assertSame( array( 'id', 'tools', 'status', 'started', 'updated' ), array_keys( get_option( '_bbp_tools_run_upgrade' ) ) );
		$this->assertSame( $state, bbp_admin_repair_tool_get_run_state( 'upgrade' ) );
		$this->assertFalse( bbp_admin_repair_tool_save_run_state( 'upgrade', array( 'bbp-test-batch-two' ) ) );
		$this->assertFalse( bbp_admin_repair_tool_save_run_state( 'upgrade', array( 'bbp-test-batch-two' ), $state['id'], 'running', true ) );
		$this->assertSame( $state, bbp_admin_repair_tool_get_run_state( 'upgrade' ) );
		$this->assertFalse( bbp_admin_repair_tool_update_run_status( 'upgrade', 'different-run', 'paused' ) );

		$paused = bbp_admin_repair_tool_update_run_status( 'upgrade', $state['id'], 'paused' );
		$this->assertSame( $state['id'], $paused['id'] );
		$this->assertSame( 'paused', $paused['status'] );
		$this->assertFalse( bbp_admin_repair_tool_delete_run_state( 'upgrade', 'different-run' ) );
		$this->assertTrue( bbp_admin_repair_tool_delete_run_state( 'upgrade', $state['id'] ) );
		$this->assertFalse( bbp_admin_repair_tool_get_run_state( 'upgrade' ) );
	}

	/**
	 * @ticket 3777
	 * @covers ::bbp_admin_repair_tool_save_run_state
	 * @covers ::bbp_admin_repair_tool_update_run_status
	 */
	public function test_pause_status_cannot_restore_an_older_queue_or_run_owner() {
		$state = bbp_admin_repair_tool_save_run_state(
			'upgrade',
			array( 'bbp-test-batch-one', 'bbp-test-batch-two' )
		);

		$progressed = bbp_admin_repair_tool_save_run_state( 'upgrade', array( 'bbp-test-batch-two' ), $state['id'], null );
		$paused     = bbp_admin_repair_tool_update_run_status( 'upgrade', $state['id'], 'paused' );

		$this->assertSame( array( 'bbp-test-batch-two' ), $progressed['tools'] );
		$this->assertSame( array( 'bbp-test-batch-two' ), $paused['tools'] );
		$this->assertSame( array( 'bbp-test-batch-two' ), get_option( '_bbp_tools_run_upgrade' )['tools'] );
		$this->assertSame( 'paused', get_option( '_bbp_tools_run_upgrade' )['status'] );

		$this->assertTrue( bbp_admin_repair_tool_delete_run_state( 'upgrade', $state['id'] ) );
		$this->assertFalse( bbp_admin_repair_tool_update_run_status( 'upgrade', $state['id'], 'running' ) );
		$this->assertFalse( bbp_admin_repair_tool_save_run_state( 'upgrade', array( 'bbp-test-batch-one' ), $state['id'] ) );
		$this->assertFalse( bbp_admin_repair_tool_get_run_state( 'upgrade' ) );
	}

	/**
	 * @ticket 3777
	 * @covers ::bbp_admin_repair_tool_get_run_state
	 * @covers ::bbp_admin_repair_tool_save_run_state
	 * @covers ::bbp_admin_repair_tool_update_run_status
	 * @covers ::bbp_admin_repair_tool_delete_run_state
	 */
	public function test_run_state_reads_changes_from_an_overlapping_request() {
		$db    = bbp_db();
		$state = bbp_admin_repair_tool_save_run_state( 'upgrade', array( 'bbp-test-batch-one', 'bbp-test-batch-two' ) );
		$new   = $state;

		$new['id']     = wp_generate_uuid4();
		$new['tools']  = array( 'bbp-test-batch-two' );
		$new['status'] = 'paused';

		// Simulate another request changing the database after our cached read
		$this->assertSame( 1, $db->update( $db->options, array( 'option_value' => maybe_serialize( $new ) ), array( 'option_name' => '_bbp_tools_run_upgrade' ) ) );
		$current = bbp_admin_repair_tool_get_run_state( 'upgrade' );
		$this->assertSame( $new['id'], $current['id'] );
		$this->assertSame( $new['tools'], $current['tools'] );
		$this->assertSame( 'paused', $current['status'] );
		$this->assertFalse( bbp_admin_repair_tool_save_run_state( 'upgrade', $state['tools'], $state['id'] ) );
		$this->assertFalse( bbp_admin_repair_tool_update_run_status( 'upgrade', $state['id'], 'running' ) );
		$this->assertFalse( bbp_admin_repair_tool_delete_run_state( 'upgrade', $state['id'] ) );
		$this->assertSame( $current, bbp_admin_repair_tool_get_run_state( 'upgrade' ) );
	}

	public function test_queue_progress_preserves_an_earlier_pause() {
		$state = bbp_admin_repair_tool_save_run_state( 'upgrade', array( 'bbp-test-batch-one', 'bbp-test-batch-two' ) );
		$this->assertSame( 'paused', bbp_admin_repair_tool_update_run_status( 'upgrade', $state['id'], 'paused' )['status'] );
		$next = bbp_admin_repair_tool_save_run_state( 'upgrade', array( 'bbp-test-batch-two' ), $state['id'], null );
		$this->assertSame( 'paused', $next['status'] );
		$this->assertSame( array( 'bbp-test-batch-two' ), $next['tools'] );
		$this->assertSame( $next, bbp_admin_repair_tool_get_run_state( 'upgrade' ) );
	}

	public function test_bulk_queue_and_tool_checkpoints_remain_independent() {
		$repair  = bbp_admin_repair_tool_save_run_state( 'repair', array( 'bbp-one', 'bbp-two' ) );
		$upgrade = bbp_admin_repair_tool_save_run_state( 'upgrade', array( 'bbp-three' ) );
		$this->assertSame( 'paused', bbp_admin_repair_tool_update_run_status( 'repair', $repair['id'], 'paused' )['status'] );
		$this->assertSame( $upgrade, bbp_admin_repair_tool_get_run_state( 'upgrade' ) );
		$one = array( 'cursor' => 0, 'upper_id' => 10 );
		$two = array( 'cursor' => 2, 'upper_id' => 20 );
		$this->assertTrue( bbp_admin_repair_tool_save_checkpoint( 'bbp-one', $one ) );
		$this->assertTrue( bbp_admin_repair_tool_save_checkpoint( 'bbp-two', $two ) );
		$first  = bbp_admin_repair_tool_lock( 'bbp-one' );
		$second = bbp_admin_repair_tool_lock( 'bbp-two' );
		try {
			$this->assertIsArray( $first );
			$this->assertIsArray( $second );
			$this->assertFalse( bbp_admin_repair_tool_lock( 'bbp-one' ) );
			$this->assertTrue( bbp_admin_repair_tool_save_checkpoint( 'bbp-one', $one, true ) );
			$this->assertSame( array( true, $two ), bbp_admin_repair_tool_get_checkpoint( 'bbp-two' ) );
			$this->assertTrue( bbp_admin_repair_tool_delete_run_state( 'upgrade', $upgrade['id'] ) );
			$this->assertSame( 'paused', bbp_admin_repair_tool_get_run_state( 'repair' )['status'] );
		} finally {
			bbp_admin_repair_tool_unlock( $first );
			bbp_admin_repair_tool_unlock( $second );
		}
	}

	public function filtered_ajax_queues() {
		return array( array( false, false ), array( true, false ), array( true, true ) );
	}

	/** @dataProvider filtered_ajax_queues */
	public function test_ajax_preserves_queued_work_hidden_by_an_option_view_filter( $complete, $hide_all ) {
		$this->add_registration_action();
		$tools = array( 'bbp-test-batch-one', 'bbp-test-batch-two', 'bbp-user-favorites-move' );
		$state = bbp_admin_repair_tool_save_run_state( 'upgrade', $tools );
		if ( $complete ) {
			$this->calls = array( 'one', 'one' );
		}
		$hide = static function ( $value ) use ( $hide_all ) {
			$value['tools'] = $hide_all ? array_slice( $value['tools'], 0, 1 ) : array_values( array_diff( $value['tools'], array( 'bbp-test-batch-two' ) ) );
			return $value;
		};
		add_filter( 'option__bbp_tools_run_upgrade', $hide );
		try {
			$response = $this->ajax( 'upgrade', $tools[0], wp_create_nonce( 'bbpress-tools-process' ), 'POST', $state['id'] );
		} finally {
			remove_filter( 'option__bbp_tools_run_upgrade', $hide );
		}
		$this->assertTrue( $response['success'] );
		$this->assertSame( $complete, $response['data']['complete'] );
		list( $read, $stored ) = bbp_admin_repair_tool_read_run_state( 'upgrade' );
		$this->assertTrue( $read );
		$this->assertSame( $complete ? array_slice( $tools, 1 ) : $tools, $stored['tools'] );
	}

	public function test_failed_legacy_ajax_callback_preserves_cache_and_bulk_queue() {
		$this->add_registration_action();
		$tools = array( 'bbp-test-batch-one', 'bbp-test-batch-two' );
		$state = bbp_admin_repair_tool_save_run_state( 'upgrade', $tools );
		update_option( '_bbp_db_pending_upgrades', $tools );
		$override = function ( $registered, $type ) {
			if ( 'upgrade' === $type ) {
				$registered['bbp-test-batch-one']['callbacks']['run'] = array( $this, 'run_failed_legacy_tool' );
				$registered['bbp-test-batch-one']['flush_cache']      = true;
			}
			return $registered;
		};
		wp_cache_set( 'failed-legacy-marker', 'preserved', 'bbpress-tests' );
		add_filter( 'bbp_get_admin_repair_tools', $override, 10, 2 );
		try {
			$response = $this->ajax( 'upgrade', $tools[0], wp_create_nonce( 'bbpress-tools-process' ), 'POST', $state['id'] );
		} finally {
			remove_filter( 'bbp_get_admin_repair_tools', $override, 10 );
		}
		$this->assertFalse( $response['success'] );
		$this->assertSame( array( 'failed-legacy' ), $this->calls );
		$this->assertSame( 'preserved', wp_cache_get( 'failed-legacy-marker', 'bbpress-tests' ) );
		list( $read, $stored ) = bbp_admin_repair_tool_read_run_state( 'upgrade' );
		$this->assertTrue( $read );
		$this->assertSame( $state['id'], $stored['id'] );
		$this->assertSame( $tools, $stored['tools'] );
		$this->assertSame( 'error', $stored['status'] );
		$this->assertSame( $tools, bbp_get_pending_upgrades() );
	}

	public function test_failed_ajax_batch_preserves_unrelated_cached_data_and_checkpoint() {
		add_filter( 'bbp_admin_repair_tool_batch_size', '__return_true' );
		$topics = $this->factory->topic->create_many( 2, array( 'post_status' => bbp_get_closed_status_id() ) );
		foreach ( $topics as $topic ) {
			delete_post_meta( $topic, '_bbp_status' );
		}
		try {
			$this->assertFalse( bbp_admin_repair_closed_topics()['complete'] );
			$checkpoint = bbp_admin_repair_tool_get_checkpoint( 'bbp-sync-closed-topics' );
			$state = bbp_admin_repair_tool_save_run_state( 'repair', array( 'bbp-sync-closed-topics' ) );
			wp_cache_set( 'failed-batch-marker', 'preserved', 'bbpress-tests' );
			add_filter( 'update_post_metadata', '__return_true' );
			try {
				$response = $this->ajax( 'repair', 'bbp-sync-closed-topics', wp_create_nonce( 'bbpress-tools-process' ), 'POST', $state['id'] );
			} finally {
				remove_filter( 'update_post_metadata', '__return_true' );
			}
			$this->assertFalse( $response['success'] );
			$this->assertSame( 'preserved', wp_cache_get( 'failed-batch-marker', 'bbpress-tests' ) );
			$this->assertSame( $checkpoint, bbp_admin_repair_tool_get_checkpoint( 'bbp-sync-closed-topics' ) );
		} finally {
			remove_filter( 'bbp_admin_repair_tool_batch_size', '__return_true' );
		}
	}

	public function test_status_mutation_preserves_work_hidden_by_an_option_view_filter() {
		$state = bbp_admin_repair_tool_save_run_state( 'upgrade', array( 'bbp-test-batch-one', 'bbp-test-batch-two' ) );
		$hide  = static function ( $value ) {
			$value['tools'] = array( 'bbp-test-batch-two' );
			return $value;
		};
		add_filter( 'option__bbp_tools_run_upgrade', $hide );
		try {
			$this->assertSame( array( 'bbp-test-batch-two' ), bbp_admin_repair_tool_get_run_state( 'upgrade' )['tools'] );
			$saved = bbp_admin_repair_tool_update_run_status( 'upgrade', $state['id'], 'paused' );
			$this->assertSame( $state['tools'], $saved['tools'] );
			list( $read, $stored ) = bbp_get_raw_option( '_bbp_tools_run_upgrade' );
			$this->assertTrue( $read );
			$this->assertSame( $state['tools'], $stored['tools'] );
			$this->assertSame( 'paused', $stored['status'] );
		} finally {
			remove_filter( 'option__bbp_tools_run_upgrade', $hide );
		}
		$this->assertSame( $saved, bbp_admin_repair_tool_get_run_state( 'upgrade' ) );
	}

	/**
	 * @ticket 3777
	 * @covers ::bbp_admin_repair_tool_update_run_status
	 */
	public function test_status_change_respects_the_queue_checkpoint_lock() {
		$state = bbp_admin_repair_tool_save_run_state( 'upgrade', array( 'bbp-test-batch-one' ) );
		$lock  = bbp_admin_repair_tool_lock( 'bbp-upgrade-tool-run-state' );
		$this->assertIsArray( $lock );

		try {
			$this->assertFalse( bbp_admin_repair_tool_update_run_status( 'upgrade', $state['id'], 'paused' ) );
			$this->assertSame( 'running', bbp_admin_repair_tool_get_run_state( 'upgrade' )['status'] );
		} finally {
			bbp_admin_repair_tool_unlock( $lock );
		}

		$this->assertSame( 'paused', bbp_admin_repair_tool_update_run_status( 'upgrade', $state['id'], 'paused' )['status'] );
	}

	/**
	 * @ticket 3777
	 * @covers ::bbp_admin_repair_tool_lock_run_state
	 */
	public function test_run_and_dry_run_state_locks_are_independent() {
		$run_lock     = bbp_admin_repair_tool_lock_run_state( 'upgrade' );
		$preview_lock = bbp_admin_repair_tool_lock_run_state( 'upgrade', 'dry-run' );

		try {
			$this->assertIsArray( $run_lock );
			$this->assertIsArray( $preview_lock );
			$this->assertNotSame( $run_lock['name'], $preview_lock['name'] );
		} finally {
			bbp_admin_repair_tool_unlock( $preview_lock );
			bbp_admin_repair_tool_unlock( $run_lock );
		}
	}

	/**
	 * @ticket 3777
	 * @covers ::bbp_admin_repair_tool_lock_run_state
	 * @covers ::bbp_admin_repair_tool_update_run_status
	 */
	public function test_status_change_retries_a_busy_queue_checkpoint_lock() {
		$state    = bbp_admin_repair_tool_save_run_state( 'upgrade', array( 'bbp-test-batch-one' ) );
		$lock     = bbp_admin_repair_tool_lock( 'bbp-upgrade-tool-run-state' );
		$attempts = 0;
		$filter   = static function ( $query ) use ( $lock, &$attempts ) {
			if ( ( false !== strpos( $query, 'INSERT IGNORE' ) ) && ( false !== strpos( $query, $lock['name'] ) ) ) {
				++$attempts;
				if ( 3 === $attempts ) {
					bbp_admin_repair_tool_unlock( $lock );
				}
			}

			return $query;
		};
		add_filter( 'query', $filter );

		try {
			$this->assertSame( 'paused', bbp_admin_repair_tool_update_run_status( 'upgrade', $state['id'], 'paused' )['status'] );
			$this->assertSame( 3, $attempts );
		} finally {
			remove_filter( 'query', $filter );
			bbp_admin_repair_tool_unlock( $lock );
		}
	}

	/**
	 * @ticket 3777
	 * @covers ::bbp_admin_repair_tool_save_run_state
	 * @covers ::bbp_admin_repair_tool_update_run_status
	 */
	public function test_checkpoint_and_status_changes_report_unsaved_updates() {
		$state  = bbp_admin_repair_tool_save_run_state( 'upgrade', array( 'bbp-test-batch-one', 'bbp-test-batch-two' ) );
		$filter = static function ( $value, $old_value ) {
			return $old_value;
		};
		add_filter( 'pre_update_option__bbp_tools_run_upgrade', $filter, 10, 2 );

		try {
			$this->assertFalse( bbp_admin_repair_tool_save_run_state( 'upgrade', array( 'bbp-test-batch-two' ), $state['id'], null ) );
			$this->assertFalse( bbp_admin_repair_tool_update_run_status( 'upgrade', $state['id'], 'paused' ) );
			$this->assertSame( $state, bbp_admin_repair_tool_get_run_state( 'upgrade' ) );
		} finally {
			remove_filter( 'pre_update_option__bbp_tools_run_upgrade', $filter, 10 );
		}
	}

	/**
	 * @ticket 3777
	 * @covers ::bbp_admin_repair_ajax_handler
	 * @dataProvider run_changes_before_callback_lock
	 */
	public function test_ajax_rechecks_run_state_after_acquiring_the_callback_lock( $change, $expected_code ) {
		$this->add_registration_action();
		$state   = bbp_admin_repair_tool_save_run_state( 'upgrade', array( 'bbp-test-batch-one' ) );
		$changed = false;
		$filter  = static function ( $query ) use ( $change, $state, &$changed ) {
			$lock_name = '_bbp_tool_lock_' . md5( 'bbp-test-batch-one' );
			if ( ! $changed && ( false !== strpos( $query, 'INSERT IGNORE' ) ) && ( false !== strpos( $query, $lock_name ) ) ) {
				$changed = true;
				if ( 'pause' === $change ) {
					bbp_admin_repair_tool_update_run_status( 'upgrade', $state['id'], 'paused' );
				} else {
					bbp_admin_repair_tool_rotate_run_state( 'upgrade', $state['tools'], $state['id'] );
				}
			}

			return $query;
		};
		add_filter( 'query', $filter );

		try {
			$response = $this->ajax( 'upgrade', 'bbp-test-batch-one', wp_create_nonce( 'bbpress-tools-process' ), 'POST', $state['id'] );
		} finally {
			remove_filter( 'query', $filter );
		}

		$this->assertTrue( $changed );
		$this->assertFalse( $response['success'] );
		$this->assertSame( $expected_code, $response['data']['code'] );
		$this->assertSame( array(), $this->calls );
		$this->assertFalse( get_option( '_bbp_tool_lock_' . md5( 'bbp-test-batch-one' ), false ) );
	}

	/**
	 * Changes that can arrive between the first state read and the callback lock.
	 */
	public static function run_changes_before_callback_lock() {
		return array(
			'paused run'   => array( 'pause', 'paused' ),
			'replaced run' => array( 'rotate', 'stale_run' ),
		);
	}

	/**
	 * @ticket 3777
	 * @covers ::bbp_admin_tools_repair_upgrade_notices
	 */
	public function test_repair_tool_resume_notice_distinguishes_paused_and_active_runs() {
		$_GET     = array( 'page' => 'bbp-upgrade' );
		$_REQUEST = $_GET;
		$state    = bbp_admin_repair_tool_save_run_state( 'upgrade', array( 'bbp-test-batch-one' ), '', 'paused' );

		bbp_admin_tools_repair_upgrade_notices();
		$this->assertStringContainsString( 'A previous forum tool run did not finish.', end( bbp_admin()->notices ) );
		$this->assertStringContainsString( 'bbp-tools-run=' . $state['id'], end( bbp_admin()->notices ) );
		$this->assertStringContainsString( '>Resume</a>', end( bbp_admin()->notices ) );
		$this->assertStringContainsString( '>Discard</a>', end( bbp_admin()->notices ) );
		$this->assertTrue( wp_script_is( 'bbp-admin-tools-js', 'enqueued' ) );
		$data = wp_scripts()->get_data( 'bbp-admin-tools-js', 'data' );
		$this->assertSame( 1, preg_match( '/var bbpToolsRecovery = (.+);/', $data, $matches ) );
		$settings = json_decode( $matches[1], true );
		$this->assertSame( 'upgrade', $settings['type'] );
		$this->assertSame( $state['id'], $settings['runs'][0]['id'] );
		$this->assertSame( 'run', $settings['runs'][0]['action'] );
		$this->assertStringContainsString( 'bbp-tools-discard=' . $state['id'], $settings['runs'][0]['url'] );
		$this->assertSame( 1, wp_verify_nonce( $settings['statusNonce'], 'bbpress-tools-run-status' ) );

		bbp_admin()->notices = array();
		bbp_admin_repair_tool_update_run_status( 'upgrade', $state['id'], 'running' );
		bbp_admin_tools_repair_upgrade_notices();
		$this->assertStringContainsString( 'is still active', end( bbp_admin()->notices ) );
		$this->assertStringNotContainsString( '>Check status</a>', end( bbp_admin()->notices ) );
		$this->assertStringNotContainsString( '>Stop</a>', end( bbp_admin()->notices ) );
		$this->assertStringNotContainsString( '>Resume</a>', end( bbp_admin()->notices ) );
	}

	/**
	 * @ticket 3777
	 * @covers ::bbp_admin_repair_tool_discard_url
	 * @covers ::bbp_admin_tools_repair_upgrade_notices
	 */
	public function test_runner_stop_url_discards_the_dry_run_and_its_preview_state() {
		$_GET  = array( 'page' => 'bbp-upgrade' );
		$state = bbp_admin_repair_tool_save_run_state( 'upgrade', array( 'bbp-test-batch-one' ), '', 'running', false, 'dry-run' );

		bbp_admin_repair_tool_execution_context( 'dry-run', $state['id'] );
		$option = bbp_admin_repair_tool_batch_option_name( 'bbp-test-batch-one' );
		$this->assertTrue( bbp_admin_repair_tool_save_option( $option, array( 'cursor' => 2, 'upper_id' => 5 ) ) );
		bbp_admin_repair_tool_execution_context( 'run', '' );

		parse_str( wp_parse_url( bbp_admin_repair_tool_discard_url( 'upgrade', $state['id'], 'dry-run' ), PHP_URL_QUERY ), $_GET );
		$_REQUEST = $_GET;
		bbp_admin_tools_repair_upgrade_notices();

		$this->assertFalse( bbp_admin_repair_tool_get_run_state( 'upgrade', 'dry-run' ) );
		$this->assertFalse( get_option( $option, false ) );
		$this->assertStringContainsString( 'was discarded', end( bbp_admin()->notices ) );
	}

	/**
	 * @ticket 3777
	 * @covers ::bbp_admin_repair_handler
	 */
	public function test_resume_rotates_run_ownership_before_continuing() {
		$this->add_registration_action();
		$this->register_tools();
		$old_state = bbp_admin_repair_tool_save_run_state( 'upgrade', array( 'bbp-test-batch-one' ), '', 'paused' );

		$_GET = array(
			'page'          => 'bbp-upgrade',
			'action'        => 'run',
			'checked'       => array( 'bbp-test-batch-one' ),
			'bbp-tools-run' => $old_state['id'],
			'_wpnonce'      => wp_create_nonce( 'bbpress-do-counts' )
		);
		$_REQUEST                  = $_GET;
		$_SERVER['REQUEST_METHOD'] = 'GET';

		bbp_admin_repair_handler();
		$new_state = bbp_admin_repair_tool_get_run_state( 'upgrade' );

		$this->assertNotSame( $old_state['id'], $new_state['id'] );
		$this->assertSame( 'running', $new_state['status'] );
		$this->assertSame( array(), $this->calls );

		$stale = $this->ajax( 'upgrade', 'bbp-test-batch-one', wp_create_nonce( 'bbpress-tools-process' ), 'POST', $old_state['id'] );
		$this->assertFalse( $stale['success'] );
		$this->assertSame( 'stale_run', $stale['data']['code'] );
		$this->assertTrue( $this->ajax( 'upgrade', 'bbp-test-batch-one', wp_create_nonce( 'bbpress-tools-process' ), 'POST', $new_state['id'] )['success'] );
		$this->assertSame( array( 'one' ), $this->calls );
	}

	/**
	 * @ticket 3777
	 * @covers ::bbp_admin_repair_handler
	 */
	public function test_unavailable_resume_stays_recoverable_until_discarded() {
		$state = bbp_admin_repair_tool_save_run_state( 'upgrade', array( 'bbp-test-unavailable' ), '', 'paused' );

		$_GET = array(
			'page'          => 'bbp-upgrade',
			'action'        => 'run',
			'checked'       => array( 'bbp-test-unavailable' ),
			'bbp-tools-run' => $state['id'],
			'_wpnonce'      => wp_create_nonce( 'bbpress-do-counts' )
		);
		$_REQUEST                  = $_GET;
		$_SERVER['REQUEST_METHOD'] = 'GET';

		bbp_admin_repair_handler();

		$this->assertSame( 'error', bbp_admin_repair_tool_get_run_state( 'upgrade' )['status'] );
		$this->assertStringContainsString( 'no longer available', end( bbp_admin()->notices ) );

		bbp_admin()->notices = array();
		$_GET                = array( 'page' => 'bbp-upgrade' );
		$_REQUEST            = $_GET;
		bbp_admin_tools_repair_upgrade_notices();
		$this->assertStringContainsString( '>Discard</a>', end( bbp_admin()->notices ) );
	}

	/**
	 * @ticket 3777
	 * @covers ::bbp_admin_repair_handler
	 */
	public function test_new_run_does_not_replace_a_recent_active_run() {
		$this->add_registration_action();
		$this->register_tools();
		$state = bbp_admin_repair_tool_save_run_state( 'upgrade', array( 'bbp-test-batch-one' ) );

		$_GET = array(
			'page'     => 'bbp-upgrade',
			'action'   => 'run',
			'checked'  => array( 'bbp-test-batch-one' ),
			'_wpnonce' => wp_create_nonce( 'bbpress-do-counts' )
		);
		$_REQUEST                  = $_GET;
		$_SERVER['REQUEST_METHOD'] = 'GET';

		bbp_admin_repair_handler();

		$this->assertSame( array(), $this->calls );
		$this->assertSame( $state, bbp_admin_repair_tool_get_run_state( 'upgrade' ) );
		$this->assertStringContainsString( 'is still active', end( bbp_admin()->notices ) );
	}

	/**
	 * @ticket 3777
	 * @covers ::bbp_admin_repair_handler
	 */
	public function test_new_dry_run_does_not_replace_unfinished_preview_state() {
		$this->add_registration_action();
		$this->register_tools();
		$state = bbp_admin_repair_tool_save_run_state( 'upgrade', array( 'bbp-test-batch-one' ), '', 'paused', false, 'dry-run' );

		bbp_admin_repair_tool_execution_context( 'dry-run', $state['id'] );
		$option = bbp_admin_repair_tool_batch_option_name( 'bbp-test-batch-one' );
		$this->assertTrue( bbp_admin_repair_tool_save_option( $option, array( 'cursor' => 2, 'upper_id' => 5 ) ) );
		bbp_admin_repair_tool_execution_context( 'run', '' );

		$_GET = array(
			'page'     => 'bbp-upgrade',
			'action'   => 'dry-run',
			'checked'  => array( 'bbp-test-batch-one' ),
			'_wpnonce' => wp_create_nonce( 'bbpress-do-counts' )
		);
		$_REQUEST                  = $_GET;
		$_SERVER['REQUEST_METHOD'] = 'GET';

		bbp_admin_repair_handler();

		$this->assertSame( $state, bbp_admin_repair_tool_get_run_state( 'upgrade', 'dry-run' ) );
		$this->assertSame( array( 'cursor' => 2, 'upper_id' => 5 ), get_option( $option ) );
		$this->assertStringContainsString( 'did not finish. Resume or discard it before starting another.', end( bbp_admin()->notices ) );
	}

	/**
	 * @ticket 3777
	 * @covers ::bbp_admin_repair_handler
	 */
	public function test_one_off_lock_contention_is_retried_by_the_ajax_runner() {
		$this->add_registration_action();
		$this->register_tools();
		$lock = bbp_admin_repair_tool_lock( 'bbp-test-batch-one' );

		$_GET = array(
			'page'     => 'bbp-upgrade',
			'action'   => 'run',
			'checked'  => array( 'bbp-test-batch-one' ),
			'_wpnonce' => wp_create_nonce( 'bbpress-do-counts' )
		);
		$_REQUEST                  = $_GET;
		$_SERVER['REQUEST_METHOD'] = 'GET';

		try {
			bbp_admin_repair_handler();
			$settings = $this->runner_settings();
			$response = $this->ajax( 'upgrade', 'bbp-test-batch-one', $settings['nonce'], 'POST', $settings['run'] );
		} finally {
			bbp_admin_repair_tool_unlock( $lock );
		}

		$this->assertSame( array(), $this->calls );
		$this->assertSame( 'running', bbp_admin_repair_tool_get_run_state( 'upgrade' )['status'] );
		$this->assertFalse( $response['success'] );
		$this->assertSame( 'locked', $response['data']['code'] );
	}

	/**
	 * @ticket 3777
	 * @covers ::bbp_admin_repair_handler
	 */
	public function test_bulk_lock_contention_stops_and_retains_the_ordered_queue() {
		$this->add_registration_action();
		$this->register_tools();
		$this->register_legacy_tool();
		$lock = bbp_admin_repair_tool_lock( 'bbp-test-batch-one' );

		$_GET = array(
			'page'     => 'bbp-upgrade',
			'action'   => 'run',
			'checked'  => array( 'bbp-test-batch-one', 'bbp-test-batch-two' ),
			'_wpnonce' => wp_create_nonce( 'bbpress-do-counts' )
		);
		$_REQUEST                  = $_GET;
		$_SERVER['REQUEST_METHOD'] = 'GET';

		try {
			bbp_admin_repair_handler();
			$settings = $this->runner_settings();
			$response = $this->ajax( 'upgrade', 'bbp-test-batch-one', $settings['nonce'], 'POST', $settings['run'] );
		} finally {
			bbp_admin_repair_tool_unlock( $lock );
		}

		$state = bbp_admin_repair_tool_get_run_state( 'upgrade' );
		$this->assertSame( array(), $this->calls );
		$this->assertSame( array( 'bbp-test-batch-one', 'bbp-test-batch-two' ), $state['tools'] );
		$this->assertSame( 'running', $state['status'] );
		$this->assertFalse( $response['success'] );
		$this->assertSame( 'locked', $response['data']['code'] );
	}

	/**
	 * @ticket 3777
	 * @covers ::bbp_admin_repair_handler
	 */
	public function test_incomplete_run_flushes_cache_after_saving_its_checkpoint() {
		$cache_key = 'bbp-test-batch-cache';
		bbp_register_repair_tool(
			array(
				'id'       => 'bbp-test-cache-flush',
				'type'     => 'repair',
				'title'    => 'Test cache flush',
				'priority' => 10,
				'callback' => function () use ( $cache_key ) {
					wp_cache_set( $cache_key, 'stale', 'bbpress-tests' );
					return array( 0, 'Checkpoint saved.', 'complete' => false );
				}
			)
		);

		$_GET = array(
			'page'     => 'bbp-repair',
			'action'   => 'run',
			'checked'  => array( 'bbp-test-cache-flush' ),
			'_wpnonce' => wp_create_nonce( 'bbpress-do-counts' )
		);
		$_REQUEST                  = $_GET;
		$_SERVER['REQUEST_METHOD'] = 'GET';

		bbp_admin_repair_handler();

		$this->assertFalse( wp_cache_get( $cache_key, 'bbpress-tests' ) );
	}

	/**
	 * @ticket 3777
	 * @covers ::bbp_admin_repair_handler
	 */
	public function test_runner_start_failure_reports_an_error_without_running_callbacks() {
		$this->register_tools();
		$this->register_legacy_tool();
		wp_dequeue_script( 'bbp-admin-tools-js' );
		$blocked = false;
		$filter  = static function ( $query ) use ( &$blocked ) {
			if ( ! $blocked && 0 === strpos( $query, 'INSERT INTO' ) && false !== strpos( $query, '_bbp_tools_run_upgrade' ) ) {
				$blocked = true;
				return 'SELECT 0';
			}

			return $query;
		};
		add_filter( 'query', $filter );
		$_GET = array(
			'page'     => 'bbp-upgrade',
			'action'   => 'run',
			'checked'  => array( 'bbp-test-batch-one', 'bbp-test-batch-two' ),
			'_wpnonce' => wp_create_nonce( 'bbpress-do-counts' )
		);
		$_REQUEST                  = $_GET;
		$_SERVER['REQUEST_METHOD'] = 'GET';

		try {
			bbp_admin_repair_handler();
		} finally {
			remove_filter( 'query', $filter );
		}

		$this->assertSame( array(), $this->calls );
		$this->assertFalse( bbp_admin_repair_tool_get_run_state( 'upgrade' ) );
		$this->assertStringContainsString( 'could not be started', end( bbp_admin()->notices ) );
		$this->assertStringContainsString( 'notice error', end( bbp_admin()->notices ) );
		$this->assertFalse( wp_script_is( 'bbp-admin-tools-js', 'enqueued' ) );
	}

	/**
	 * @ticket 3777
	 * @covers ::bbp_admin_repair_handler
	 */
	public function test_legacy_bulk_failures_stop_the_ajax_queue() {
		$this->add_registration_action();
		$this->register_tools();
		$this->register_legacy_tool();
		$tools = array( 'bbp-test-batch-one', 'bbp-test-batch-two' );
		$fail  = static function ( $registered, $type ) use ( $tools ) {
			if ( 'upgrade' === $type ) {
				foreach ( $tools as $tool_id ) {
					$registered[ $tool_id ]['callbacks']['run'] = static function () {
						return array( 0, 'Failed.', 'success' => false );
					};
				}
			}
			return $registered;
		};
		add_filter( 'bbp_get_admin_repair_tools', $fail, 10, 2 );
		update_option( '_bbp_db_pending_upgrades', $tools );
		$_GET = array(
			'page'     => 'bbp-upgrade',
			'action'   => 'run',
			'checked'  => $tools,
			'_wpnonce' => wp_create_nonce( 'bbpress-do-counts' )
		);
		$_REQUEST                  = $_GET;
		$_SERVER['REQUEST_METHOD'] = 'GET';

		try {
			bbp_admin_repair_handler();
			$settings = $this->runner_settings();
			$response = $this->ajax( 'upgrade', $tools[0], $settings['nonce'], 'POST', $settings['run'] );
		} finally {
			remove_filter( 'bbp_get_admin_repair_tools', $fail, 10 );
		}

		$this->assertSame( $tools, bbp_get_pending_upgrades() );
		$this->assertFalse( $response['success'] );
		$this->assertSame( 'error', bbp_admin_repair_tool_get_run_state( 'upgrade' )['status'] );
		$this->assertTrue( wp_script_is( 'bbp-admin-tools-js', 'enqueued' ) );
	}

	/**
	 * @ticket 3777
	 * @covers ::bbp_admin_repair_ajax_handler
	 */
	public function test_ajax_does_not_clear_pending_tools_when_the_queue_save_fails() {
		$this->add_registration_action();
		$tools = array( 'bbp-test-batch-one', 'bbp-test-batch-two' );
		$state = bbp_admin_repair_tool_save_run_state( 'upgrade', $tools );
		update_option( '_bbp_db_pending_upgrades', $tools );
		$block_save = static function ( $value, $old_value ) {
			return $old_value;
		};
		$override = function ( $registered, $type ) {
			if ( 'upgrade' === $type ) {
				$registered['bbp-test-batch-one']['callbacks']['run'] = array( $this, 'run_override_tool' );
			}

			return $registered;
		};
		add_filter( 'pre_update_option__bbp_tools_run_upgrade', $block_save, 10, 2 );
		add_filter( 'bbp_get_admin_repair_tools', $override, 10, 2 );

		try {
			$response = $this->ajax( 'upgrade', 'bbp-test-batch-one', wp_create_nonce( 'bbpress-tools-process' ), 'POST', $state['id'] );
		} finally {
			remove_filter( 'pre_update_option__bbp_tools_run_upgrade', $block_save, 10 );
			remove_filter( 'bbp_get_admin_repair_tools', $override, 10 );
		}

		$this->assertFalse( $response['success'] );
		$this->assertStringContainsString( 'Progress could not be saved.', $response['data']['message'] );
		$this->assertSame( $tools, bbp_get_pending_upgrades() );
		$this->assertSame( $tools, bbp_admin_repair_tool_get_run_state( 'upgrade' )['tools'] );
	}

	/**
	 * @ticket 3777
	 * @covers ::bbp_admin_repair_tool_save_queue_progress
	 */
	public function test_queue_progress_retries_one_transient_update_failure() {
		$state    = bbp_admin_repair_tool_save_run_state( 'upgrade', array( 'bbp-test-batch-one', 'bbp-test-batch-two' ) );
		$attempts = 0;
		$fail_once = static function ( $value, $old_value ) use ( &$attempts ) {
			++$attempts;
			return 1 === $attempts ? $old_value : $value;
		};
		add_filter( 'pre_update_option__bbp_tools_run_upgrade', $fail_once, 10, 2 );

		try {
			$this->assertTrue( bbp_admin_repair_tool_save_queue_progress( 'upgrade', $state, $state['id'], 'run', true ) );
		} finally {
			remove_filter( 'pre_update_option__bbp_tools_run_upgrade', $fail_once, 10 );
		}

		$this->assertSame( 2, $attempts );
		$this->assertSame( array( 'bbp-test-batch-two' ), bbp_admin_repair_tool_get_run_state( 'upgrade' )['tools'] );
	}

	/**
	 * @ticket 3777
	 * @covers ::bbp_admin_repair_tool_save_queue_progress
	 */
	public function test_final_queue_progress_retries_one_transient_delete_failure() {
		$state   = bbp_admin_repair_tool_save_run_state( 'upgrade', array( 'bbp-test-batch-one' ) );
		$blocked = false;
		$fail_once = static function ( $query ) use ( &$blocked ) {
			if ( ! $blocked && 0 === strpos( $query, 'DELETE ' ) && false !== strpos( $query, '_bbp_tools_run_upgrade' ) ) {
				$blocked = true;
				return 'SELECT 0';
			}
			return $query;
		};
		add_filter( 'query', $fail_once );

		try {
			$this->assertTrue( bbp_admin_repair_tool_save_queue_progress( 'upgrade', $state, $state['id'], 'run', true ) );
		} finally {
			remove_filter( 'query', $fail_once );
		}

		$this->assertTrue( $blocked );
		$this->assertFalse( bbp_admin_repair_tool_get_run_state( 'upgrade' ) );
	}

	/**
	 * @ticket 3777
	 * @covers ::bbp_admin_repair_handler
	 * @covers ::bbp_admin_repair_ajax_handler
	 * @covers ::bbp_admin_repair_tool_enqueue_runner
	 */
	public function test_incomplete_bulk_run_continues_in_order_and_clears_only_completed_tools() {
		$this->add_registration_action();
		$this->register_tools();
		$this->register_legacy_tool();
		update_option( '_bbp_db_pending_upgrades', array( 'bbp-test-batch-one', 'bbp-test-batch-two' ) );

		$_GET = array(
			'page'     => 'bbp-upgrade',
			'action'   => 'run',
			'checked'  => array( 'bbp-test-batch-one', 'bbp-test-batch-two' ),
			'_wpnonce' => wp_create_nonce( 'bbpress-do-counts' )
		);
		$_REQUEST                  = $_GET;
		$_SERVER['REQUEST_METHOD'] = 'GET';

		bbp_admin_repair_handler();

		$this->assertSame( array(), $this->calls );
		$this->assertSame( array( 'bbp-test-batch-one', 'bbp-test-batch-two' ), bbp_get_pending_upgrades() );
		$this->assertTrue( wp_script_is( 'bbp-admin-tools-js', 'enqueued' ) );
		$this->assertStringContainsString( 'bbp-tools-runner', end( bbp_admin()->notices ) );
		$this->assertStringContainsString( 'Continue', end( bbp_admin()->notices ) );
		$this->assertStringContainsString( '<strong>Test batch one</strong> <span class="bbp-tools-runner"></span>', end( bbp_admin()->notices ) );

		$settings = $this->runner_settings();
		$this->assertSame( 'upgrade', $settings['type'] );
		$this->assertSame( array( 'bbp-test-batch-one', 'bbp-test-batch-two' ), $settings['tools'] );
		$this->assertNotEmpty( $settings['run'] );
		parse_str( wp_parse_url( $settings['stopUrl'], PHP_URL_QUERY ), $stop_query );
		$this->assertSame( $settings['run'], $stop_query['bbp-tools-discard'] );
		$this->assertSame( 'run', $stop_query['bbp-tools-discard-action'] );
		$this->assertSame( 1, wp_verify_nonce( $stop_query['_wpnonce'], 'bbpress-tools-discard-' . $settings['run'] ) );
		$this->assertSame( array( 'bbp-test-batch-one', 'bbp-test-batch-two' ), bbp_admin_repair_tool_get_run_state( 'upgrade' )['tools'] );
		$this->assertStringNotContainsString( '&amp;', $settings['completeUrl'] );
		parse_str( wp_parse_url( $settings['completeUrl'], PHP_URL_QUERY ), $complete_query );
		$this->assertSame( 'bbp-test-batch-two', $complete_query['bbp-tools-complete'] );
		$this->assertSame( 1, wp_verify_nonce( $complete_query['_wpnonce'], 'bbpress-tools-complete' ) );

		$cache_key = 'bbp-test-ajax-batch-cache';
		wp_cache_set( $cache_key, 'stale', 'bbpress-tests' );
		$flush_override = function ( $tools, $type ) {
			if ( 'upgrade' === $type ) {
				$tools['bbp-test-batch-one']['flush_cache'] = true;
			}

			return $tools;
		};
		add_filter( 'bbp_get_admin_repair_tools', $flush_override, 10, 2 );
		try {
			$response = $this->ajax( 'upgrade', 'bbp-test-batch-one', $settings['nonce'], 'POST', $settings['run'] );
		} finally {
			remove_filter( 'bbp_get_admin_repair_tools', $flush_override, 10 );
		}
		$this->assertTrue( $response['success'] );
		$this->assertFalse( $response['data']['complete'] );
		$this->assertSame( 'Test batch one', $response['data']['title'] );
		$this->assertSame( 'First tool needs another batch.', $response['data']['message'] );
		$this->assertSame( 'stale', wp_cache_get( $cache_key, 'bbpress-tests' ) );
		$this->assertSame( array( 'bbp-test-batch-one', 'bbp-test-batch-two' ), bbp_get_pending_upgrades() );

		add_filter( 'bbp_get_admin_repair_tools', $flush_override, 10, 2 );
		try {
			$response = $this->ajax( 'upgrade', 'bbp-test-batch-one', $settings['nonce'], 'POST', $settings['run'] );
		} finally {
			remove_filter( 'bbp_get_admin_repair_tools', $flush_override, 10 );
		}
		$this->assertTrue( $response['success'] );
		$this->assertFalse( $response['data']['complete'] );
		$this->assertSame( array( 'bbp-test-batch-one', 'bbp-test-batch-two' ), bbp_admin_repair_tool_get_run_state( 'upgrade' )['tools'] );

		add_filter( 'bbp_get_admin_repair_tools', $flush_override, 10, 2 );
		try {
			$response = $this->ajax( 'upgrade', 'bbp-test-batch-one', $settings['nonce'], 'POST', $settings['run'] );
		} finally {
			remove_filter( 'bbp_get_admin_repair_tools', $flush_override, 10 );
		}
		$this->assertTrue( $response['success'] );
		$this->assertTrue( $response['data']['complete'] );
		$this->assertFalse( wp_cache_get( $cache_key, 'bbpress-tests' ) );
		$this->assertSame( array( 'bbp-test-batch-two' ), array_values( bbp_get_pending_upgrades() ) );
		$this->assertSame( array( 'bbp-test-batch-two' ), bbp_admin_repair_tool_get_run_state( 'upgrade' )['tools'] );

		$response = $this->ajax( 'upgrade', 'bbp-test-batch-two', $settings['nonce'], 'POST', $settings['run'] );
		$this->assertTrue( $response['success'] );
		$this->assertTrue( $response['data']['complete'] );

		$this->assertSame( array(), bbp_get_pending_upgrades() );
		$this->assertFalse( bbp_admin_repair_tool_get_run_state( 'upgrade' ) );
		$this->assertSame( array( 'one', 'one', 'one', 'two' ), $this->calls );
	}

	public function test_builtin_migrations_continue_through_the_bulk_and_ajax_handlers() {
		require_once BBP_PLUGIN_DIR . 'includes/admin/tools/upgrade.php';
		$tools = array(
			'bbp-user-favorites-move' => array( '_bbp_favorites', '_bbp_favorite' ),
			'bbp-user-topic-subscriptions-move' => array( '_bbp_subscriptions', '_bbp_subscription' ),
			'bbp-user-forum-subscriptions-move' => array( '_bbp_forum_subscriptions', '_bbp_subscription' ),
			'bbp-user-topic-engagements-move' => array( '', '_bbp_engagement' ),
		);
		$ids     = array_keys( $tools );
		$user_id = $this->factory->user->create();
		$posts   = array();
		foreach ( $tools as $tool_id => $keys ) {
			if ( empty( $keys[0] ) ) {
				$posts[ $tool_id ] = $this->factory->topic->create_many( 3, array( 'post_author' => $user_id ) );
				foreach ( $posts[ $tool_id ] as $post_id ) {
					delete_post_meta( $post_id, $keys[1] );
				}
			} else {
				$posts[ $tool_id ] = $this->factory->post->create_many( 3 );
				add_user_meta( $user_id, bbp_db()->prefix . $keys[0], implode( ',', $posts[ $tool_id ] ) );
			}
		}
		update_option( '_bbp_db_pending_upgrades', $ids );
		$batch_size = static function () {
			return 2;
		};
		add_filter( 'bbp_admin_repair_tool_batch_size', $batch_size );
		bbp_register_default_repair_tools();
		$_GET = array(
			'page'     => 'bbp-upgrade',
			'action'   => 'run',
			'checked'  => $ids,
			'_wpnonce' => wp_create_nonce( 'bbpress-do-counts' )
		);
		$_REQUEST                  = $_GET;
		$_SERVER['REQUEST_METHOD'] = 'GET';
		bbp_admin_repair_handler();
		$state  = bbp_admin_repair_tool_get_run_state( 'upgrade' );
		$run_id = $state['id'];
		$this->assertSame( $ids, $state['tools'] );

		try {
			foreach ( $ids as $index => $tool_id ) {
				$this->assertContains( $tool_id, bbp_get_pending_upgrades() );
				$this->assertSame( array(), get_post_meta( $posts[ $tool_id ][2], $tools[ $tool_id ][1], false ) );

				for ( $request = 0; $request < 20; ++$request ) {
					$response = $this->ajax( 'upgrade', $tool_id, wp_create_nonce( 'bbpress-tools-process' ), 'POST', $run_id );
					$this->assertTrue( $response['success'] );
					if ( $response['data']['complete'] ) {
						break;
					}
				}
				$this->assertTrue( $response['data']['complete'] );
				$this->assertNotContains( $tool_id, bbp_get_pending_upgrades() );
				$this->assertFalse( get_option( bbp_admin_repair_tool_batch_option_name( $tool_id ), false ) );
				$remaining = array_slice( $ids, $index + 1 );
				$state     = bbp_admin_repair_tool_get_run_state( 'upgrade' );
				$this->assertSame( $remaining, $state ? $state['tools'] : array() );
			}
		} finally {
			remove_filter( 'bbp_admin_repair_tool_batch_size', $batch_size );
		}

		$this->assertSame( array(), bbp_get_pending_upgrades() );
		$this->assertFalse( bbp_admin_repair_tool_get_run_state( 'upgrade' ) );
		foreach ( $tools as $tool_id => $keys ) {
			foreach ( $posts[ $tool_id ] as $post_id ) {
				$this->assertSame( array( (string) $user_id ), get_post_meta( $post_id, $keys[1], false ) );
			}
		}
	}

	public function test_user_count_repairs_continue_sequentially_through_bulk_and_ajax() {
		require_once BBP_PLUGIN_DIR . 'includes/admin/tools/repair.php';
		$tools = array(
			'bbp-user-topics'  => bbp_db()->prefix . '_bbp_topic_count',
			'bbp-user-replies' => bbp_db()->prefix . '_bbp_reply_count',
		);
		$ids   = array_keys( $tools );
		$users = $this->factory->user->create_many( 5 );
		foreach ( $users as $user_id ) {
			$topic_id = $this->factory->topic->create( array( 'post_author' => $user_id ) );
			$this->factory->reply->create( array( 'post_parent' => $topic_id, 'post_author' => $user_id ) );
			foreach ( $tools as $key ) {
				delete_user_meta( $user_id, $key );
			}
		}
		update_option( '_bbp_db_pending_upgrades', $ids );
		$size = static function () {
			return 2;
		};
		add_filter( 'bbp_admin_repair_tool_batch_size', $size );
		bbp_register_default_repair_tools();
		$run_id = '';
		try {
			foreach ( $ids as $index => $tool_id ) {
				$_GET = array(
					'page'          => 'bbp-repair',
					'action'        => 'run',
					'checked'       => array_slice( $ids, $index ),
					'bbp-tools-run' => $run_id,
					'_wpnonce'      => wp_create_nonce( 'bbpress-do-counts' ),
				);
				$_REQUEST                  = $_GET;
				$_SERVER['REQUEST_METHOD'] = 'GET';
				bbp_admin_repair_handler();
				$state  = bbp_admin_repair_tool_get_run_state( 'repair' );
				$run_id = $state['id'];
				$this->assertSame( array_slice( $ids, $index ), $state['tools'] );
				$this->assertSame( array(), get_user_meta( $users[2], $tools[ $tool_id ], false ) );
				for ( $batch = 0; $batch < 5; ++$batch ) {
					$response = $this->ajax( 'repair', $tool_id, wp_create_nonce( 'bbpress-tools-process' ), 'POST', $run_id );
					$this->assertTrue( $response['success'] );
					if ( $response['data']['complete'] ) {
						break;
					}
				}
				$this->assertTrue( $response['data']['complete'] );
				$this->assertNotContains( $tool_id, bbp_get_pending_upgrades() );
			}
		} finally {
			remove_filter( 'bbp_admin_repair_tool_batch_size', $size );
		}
		$this->assertFalse( bbp_admin_repair_tool_get_run_state( 'repair' ) );
		foreach ( $users as $user_id ) {
			foreach ( $tools as $key ) {
				$this->assertSame( array( '1' ), get_user_meta( $user_id, $key, false ) );
			}
		}
	}

	public function test_forum_and_topic_reference_batches_run_sequentially_through_bulk_and_ajax() {
		require_once BBP_PLUGIN_DIR . 'includes/admin/tools/repair.php';
		$forums  = $this->factory->forum->create_many( 3 );
		$topics  = array();
		$replies = array();
		foreach ( $forums as $forum_id ) {
			$topic_id             = $this->factory->topic->create( array( 'post_parent' => $forum_id ) );
			$reply_id             = $this->factory->reply->create( array( 'post_parent' => $topic_id ) );
			$topics[ $topic_id ]  = $forum_id;
			$replies[ $reply_id ] = $topic_id;
		}
		foreach ( array_merge( $forums, array_keys( $topics ), array_keys( $replies ) ) as $id ) {
			update_post_meta( $id, '_bbp_forum_id', 'invalid' );
		}
		foreach ( array_merge( array_keys( $topics ), array_keys( $replies ) ) as $id ) {
			update_post_meta( $id, '_bbp_topic_id', 'invalid' );
		}
		$ids  = array( 'bbp-sync-forum-meta', 'bbp-sync-topic-meta' );
		$size = static function () {
			return 2;
		};
		add_filter( 'bbp_admin_repair_tool_batch_size', $size );
		bbp_register_default_repair_tools();
		$run_id = '';
		try {
			foreach ( $ids as $index => $tool_id ) {
				$_GET = array(
					'page'          => 'bbp-repair',
					'action'        => 'run',
					'checked'       => array_slice( $ids, $index ),
					'bbp-tools-run' => $run_id,
					'_wpnonce'      => wp_create_nonce( 'bbpress-do-counts' )
				);
				$_REQUEST                  = $_GET;
				$_SERVER['REQUEST_METHOD'] = 'GET';
				bbp_admin_repair_handler();
				$state  = bbp_admin_repair_tool_get_run_state( 'repair' );
				$run_id = $state['id'];
				$this->assertSame( array_slice( $ids, $index ), $state['tools'] );
				if ( 0 === $index ) {
					$this->assertSame( 'invalid', get_post_meta( $forums[1], '_bbp_forum_id', true ) );
					$this->assertSame( 'invalid', get_post_meta( key( $topics ), '_bbp_topic_id', true ) );
				}
				for ( $batch = 0; $batch < 20; ++$batch ) {
					$response = $this->ajax( 'repair', $tool_id, wp_create_nonce( 'bbpress-tools-process' ), 'POST', $run_id );
					$this->assertTrue( $response['success'] );
					if ( $response['data']['complete'] ) {
						break;
					}
				}
				$this->assertTrue( $response['data']['complete'] );
				$this->assertFalse( get_option( bbp_admin_repair_tool_batch_option_name( $tool_id ), false ) );
				if ( 0 === $index ) {
					$this->assertSame( 'invalid', get_post_meta( key( $topics ), '_bbp_topic_id', true ) );
				}
			}
		} finally {
			remove_filter( 'bbp_admin_repair_tool_batch_size', $size );
		}
		foreach ( $forums as $id ) {
			$this->assertSame( array( '0' ), get_post_meta( $id, '_bbp_forum_id', false ) );
		}
		foreach ( $topics as $id => $forum_id ) {
			$this->assertSame( array( (string) $forum_id ), get_post_meta( $id, '_bbp_forum_id', false ) );
			$this->assertSame( array( (string) $id ), get_post_meta( $id, '_bbp_topic_id', false ) );
		}
		foreach ( $replies as $id => $topic_id ) {
			$this->assertSame( array( (string) $topics[ $topic_id ] ), get_post_meta( $id, '_bbp_forum_id', false ) );
			$this->assertSame( array( (string) $topic_id ), get_post_meta( $id, '_bbp_topic_id', false ) );
		}
		$this->assertFalse( bbp_admin_repair_tool_get_run_state( 'repair' ) );
	}

	public function topic_repair_batch_tools() {
		return array(
			array( 'bbp-sync-topic-meta', '_bbp_topic_id' ),
			array( 'bbp-topic-engagements', '_bbp_voice_count' )
		);
	}

	/** @dataProvider topic_repair_batch_tools */
	public function test_topic_repair_batches_finish_before_the_next_bulk_tool( $first_tool, $key ) {
		require_once BBP_PLUGIN_DIR . 'includes/admin/tools/repair.php';
		$topics = $this->factory->topic->create_many( 3 );
		$forums = $this->factory->forum->create_many( 3 );
		foreach ( $topics as $id ) {
			update_post_meta( $id, $key, 0 );
		}
		foreach ( $forums as $id ) {
			update_post_meta( $id, '_bbp_forum_subforum_count', 99 );
		}
		$ids  = array( $first_tool, 'bbp-forum-subforums' );
		$size = static function () {
			return 2;
		};
		add_filter( 'bbp_admin_repair_tool_batch_size', $size );
		bbp_register_default_repair_tools();
		$run_id = '';
		try {
			foreach ( $ids as $index => $tool_id ) {
				$_GET = array(
					'page'          => 'bbp-repair',
					'action'        => 'run',
					'checked'       => array_slice( $ids, $index ),
					'bbp-tools-run' => $run_id,
					'_wpnonce'      => wp_create_nonce( 'bbpress-do-counts' )
				);
				$_REQUEST                  = $_GET;
				$_SERVER['REQUEST_METHOD'] = 'GET';
				bbp_admin_repair_handler();
				$state  = bbp_admin_repair_tool_get_run_state( 'repair' );
				$run_id = $state['id'];
				$this->assertSame( array_slice( $ids, $index ), $state['tools'] );
				if ( 0 === $index ) {
					$this->assertSame( '0', get_post_meta( $topics[1], $key, true ) );
					$this->assertSame( '99', get_post_meta( $forums[0], '_bbp_forum_subforum_count', true ) );
				}
				for ( $batch = 0; $batch < 12; ++$batch ) {
					$response = $this->ajax( 'repair', $tool_id, wp_create_nonce( 'bbpress-tools-process' ), 'POST', $run_id );
					$this->assertTrue( $response['success'] );
					if ( $response['data']['complete'] ) {
						break;
					}
				}
				$this->assertTrue( $response['data']['complete'] );
				$this->assertFalse( get_option( bbp_admin_repair_tool_batch_option_name( $tool_id ), false ) );
			}
		} finally {
			remove_filter( 'bbp_admin_repair_tool_batch_size', $size );
		}
		foreach ( $topics as $id ) {
			$expected = ( '_bbp_topic_id' === $key ) ? (string) $id : '1';
			$this->assertSame( array( $expected ), get_post_meta( $id, $key, false ) );
		}
		foreach ( $forums as $id ) {
			$this->assertSame( '0', get_post_meta( $id, '_bbp_forum_subforum_count', true ) );
		}
		$this->assertFalse( bbp_admin_repair_tool_get_run_state( 'repair' ) );
	}

	public function test_subforum_recounts_finish_before_the_next_bulk_tool() {
		require_once BBP_PLUGIN_DIR . 'includes/admin/tools/repair.php';
		$db     = bbp_db();
		$forums = $this->factory->forum->create_many( 5 );
		foreach ( $forums as $id ) {
			update_post_meta( $id, '_bbp_forum_subforum_count', 99 );
		}
		$tags = array();
		for ( $i = 0; $i < 3; ++$i ) {
			$term   = wp_insert_term( 'Subforum batch tag ' . $i, bbp_get_topic_tag_tax_id() );
			$tags[] = (int) $term['term_taxonomy_id'];
			$db->update( $db->term_taxonomy, array( 'count' => 99 ), array( 'term_taxonomy_id' => $term['term_taxonomy_id'] ) );
		}
		$ids  = array( 'bbp-forum-subforums', 'bbp-topic-tags' );
		$size = static function () {
			return 2;
		};
		add_filter( 'bbp_admin_repair_tool_batch_size', $size );
		bbp_register_default_repair_tools();
		$run_id = '';
		try {
			foreach ( $ids as $index => $tool_id ) {
				$_GET = array(
					'page'          => 'bbp-repair',
					'action'        => 'run',
					'checked'       => array_slice( $ids, $index ),
					'bbp-tools-run' => $run_id,
					'_wpnonce'      => wp_create_nonce( 'bbpress-do-counts' )
				);
				$_REQUEST                  = $_GET;
				$_SERVER['REQUEST_METHOD'] = 'GET';
				bbp_admin_repair_handler();
				$state  = bbp_admin_repair_tool_get_run_state( 'repair' );
				$run_id = $state['id'];
				$this->assertSame( array_slice( $ids, $index ), $state['tools'] );
				if ( 0 === $index ) {
					$this->assertSame( '99', get_post_meta( $forums[1], '_bbp_forum_subforum_count', true ) );
					$this->assertSame( '99', $db->get_var( $db->prepare( "SELECT count FROM {$db->term_taxonomy} WHERE term_taxonomy_id = %d", $tags[0] ) ) );
				}
				for ( $batch = 0; $batch < 10; ++$batch ) {
					$response = $this->ajax( 'repair', $tool_id, wp_create_nonce( 'bbpress-tools-process' ), 'POST', $run_id );
					$this->assertTrue( $response['success'] );
					if ( $response['data']['complete'] ) {
						break;
					}
				}
				$this->assertTrue( $response['data']['complete'] );
				$this->assertFalse( get_option( bbp_admin_repair_tool_batch_option_name( $tool_id ), false ) );
			}
		} finally {
			remove_filter( 'bbp_admin_repair_tool_batch_size', $size );
		}
		$this->assertFalse( bbp_admin_repair_tool_get_run_state( 'repair' ) );
		foreach ( $forums as $id ) {
			$this->assertSame( array( '0' ), get_post_meta( $id, '_bbp_forum_subforum_count', false ) );
		}
		foreach ( $tags as $id ) {
			$this->assertSame( '0', $db->get_var( $db->prepare( "SELECT count FROM {$db->term_taxonomy} WHERE term_taxonomy_id = %d", $id ) ) );
		}
	}

	public function test_tag_recounts_continue_through_bulk_and_ajax_before_the_next_tool() {
		require_once BBP_PLUGIN_DIR . 'includes/admin/tools/repair.php';
		$db       = bbp_db();
		$taxonomy = bbp_get_topic_tag_tax_id();
		$topics   = $this->factory->topic->create_many( 5 );
		$tags     = array();
		foreach ( $topics as $index => $topic_id ) {
			$term   = wp_insert_term( 'Batch tag ' . $index, $taxonomy );
			$tags[] = (int) $term['term_taxonomy_id'];
			wp_set_object_terms( $topic_id, array( $term['term_id'] ), $taxonomy );
			$db->update( $db->term_taxonomy, array( 'count' => 99 ), array( 'term_taxonomy_id' => $term['term_taxonomy_id'] ) );
			delete_post_meta( $topic_id, '_bbp_reply_count' );
		}
		$ids  = array( 'bbp-topic-tags', 'bbp-topic-replies' );
		$size = static function () {
			return 2;
		};
		add_filter( 'bbp_admin_repair_tool_batch_size', $size );
		bbp_register_default_repair_tools();
		$run_id = '';
		try {
			foreach ( $ids as $index => $tool_id ) {
				$_GET = array(
					'page'          => 'bbp-repair',
					'action'        => 'run',
					'checked'       => array_slice( $ids, $index ),
					'bbp-tools-run' => $run_id,
					'_wpnonce'      => wp_create_nonce( 'bbpress-do-counts' )
				);
				$_REQUEST                  = $_GET;
				$_SERVER['REQUEST_METHOD'] = 'GET';
				bbp_admin_repair_handler();
				$state  = bbp_admin_repair_tool_get_run_state( 'repair' );
				$run_id = $state['id'];
				$this->assertSame( array_slice( $ids, $index ), $state['tools'] );
				for ( $batch = 0; $batch < 5; ++$batch ) {
					$response = $this->ajax( 'repair', $tool_id, wp_create_nonce( 'bbpress-tools-process' ), 'POST', $run_id );
					$this->assertTrue( $response['success'] );
					if ( $response['data']['complete'] ) {
						break;
					}
				}
				$this->assertTrue( $response['data']['complete'] );
				$this->assertFalse( get_option( bbp_admin_repair_tool_batch_option_name( $tool_id ), false ) );
			}
		} finally {
			remove_filter( 'bbp_admin_repair_tool_batch_size', $size );
		}
		$this->assertFalse( bbp_admin_repair_tool_get_run_state( 'repair' ) );
		foreach ( $tags as $id ) {
			$this->assertSame( '1', $db->get_var( $db->prepare( "SELECT count FROM {$db->term_taxonomy} WHERE term_taxonomy_id = %d", $id ) ) );
		}
		foreach ( $topics as $topic_id ) {
			$this->assertSame( array( '0' ), get_post_meta( $topic_id, '_bbp_reply_count', false ) );
		}
	}

	public function test_topic_reply_recounts_continue_sequentially_through_bulk_and_ajax() {
		require_once BBP_PLUGIN_DIR . 'includes/admin/tools/repair.php';
		$tools = array(
			'bbp-topic-replies' => '_bbp_reply_count',
			'bbp-topic-hidden-replies' => '_bbp_reply_count_hidden'
		);
		$ids    = array_keys( $tools );
		$topics = $this->factory->topic->create_many( 5 );
		foreach ( $topics as $topic_id ) {
			$this->factory->reply->create( array( 'post_parent' => $topic_id ) );
			$this->factory->reply->create( array( 'post_parent' => $topic_id, 'post_status' => bbp_get_spam_status_id() ) );
			foreach ( $tools as $key ) {
				delete_post_meta( $topic_id, $key );
			}
		}
		$size = static function () {
			return 2;
		};
		add_filter( 'bbp_admin_repair_tool_batch_size', $size );
		bbp_register_default_repair_tools();
		$run_id = '';
		try {
			foreach ( $ids as $index => $tool_id ) {
				$_GET = array(
					'page'          => 'bbp-repair',
					'action'        => 'run',
					'checked'       => array_slice( $ids, $index ),
					'bbp-tools-run' => $run_id,
					'_wpnonce'      => wp_create_nonce( 'bbpress-do-counts' )
				);
				$_REQUEST                  = $_GET;
				$_SERVER['REQUEST_METHOD'] = 'GET';
				bbp_admin_repair_handler();
				$state  = bbp_admin_repair_tool_get_run_state( 'repair' );
				$run_id = $state['id'];
				$this->assertSame( array_slice( $ids, $index ), $state['tools'] );
				$this->assertSame( array(), get_post_meta( $topics[2], $tools[ $tool_id ], false ) );
				for ( $batch = 0; $batch < 5; ++$batch ) {
					$response = $this->ajax( 'repair', $tool_id, wp_create_nonce( 'bbpress-tools-process' ), 'POST', $run_id );
					$this->assertTrue( $response['success'] );
					if ( $response['data']['complete'] ) {
						break;
					}
				}
				$this->assertTrue( $response['data']['complete'] );
				$this->assertFalse( get_option( bbp_admin_repair_tool_batch_option_name( $tool_id ), false ) );
			}
		} finally {
			remove_filter( 'bbp_admin_repair_tool_batch_size', $size );
		}
		$this->assertFalse( bbp_admin_repair_tool_get_run_state( 'repair' ) );
		foreach ( $topics as $topic_id ) {
			foreach ( $tools as $key ) {
				$this->assertSame( array( '1' ), get_post_meta( $topic_id, $key, false ) );
			}
		}
	}

	public function test_forum_recounts_continue_sequentially_through_bulk_and_ajax() {
		require_once BBP_PLUGIN_DIR . 'includes/admin/tools/repair.php';
		$tools = array(
			'bbp-forum-topics' => '_bbp_topic_count',
			'bbp-forum-replies' => '_bbp_reply_count',
			'bbp-forum-hidden-replies' => '_bbp_reply_count_hidden'
		);
		$ids = array_keys( $tools );
		$forums = $this->factory->forum->create_many( 3 );
		foreach ( $forums as $forum ) {
			$topic = $this->factory->topic->create( array( 'post_parent' => $forum ) );
			$this->factory->reply->create( array( 'post_parent' => $topic ) );
			$this->factory->reply->create( array( 'post_parent' => $topic, 'post_status' => bbp_get_spam_status_id() ) );
			foreach ( $tools as $key ) {
				delete_post_meta( $forum, $key );
			}
		}
		$size = static function () { return 2; };
		add_filter( 'bbp_admin_repair_tool_batch_size', $size );
		bbp_register_default_repair_tools();
		$run_id = '';
		try {
			foreach ( $ids as $index => $tool_id ) {
				$_GET = array(
					'page' => 'bbp-repair',
					'action' => 'run',
					'checked' => array_slice( $ids, $index ),
					'bbp-tools-run' => $run_id,
					'_wpnonce' => wp_create_nonce( 'bbpress-do-counts' )
				);
				$_REQUEST = $_GET;
				$_SERVER['REQUEST_METHOD'] = 'GET';
				bbp_admin_repair_handler();
				$state = bbp_admin_repair_tool_get_run_state( 'repair' );
				$run_id = $state['id'];
				$this->assertSame( array_slice( $ids, $index ), $state['tools'] );
				$this->assertSame( array(), get_post_meta( $forums[2], $tools[ $tool_id ], false ) );
				for ( $batch = 0; $batch < 80; ++$batch ) {
					$response = $this->ajax( 'repair', $tool_id, wp_create_nonce( 'bbpress-tools-process' ), 'POST', $run_id );
					$this->assertTrue( $response['success'] );
					if ( $response['data']['complete'] ) {
						break;
					}
				}
				$this->assertTrue( $response['data']['complete'] );
				$this->assertFalse( get_option( bbp_admin_repair_tool_batch_option_name( $tool_id ), false ) );
			}
		} finally {
			remove_filter( 'bbp_admin_repair_tool_batch_size', $size );
		}
		$this->assertFalse( bbp_admin_repair_tool_get_run_state( 'repair' ) );
		foreach ( $forums as $forum ) {
			foreach ( $tools as $key ) {
				$this->assertSame( array( '1' ), get_post_meta( $forum, $key, false ) );
			}
		}
	}

	public function test_legacy_list_repairs_continue_sequentially_through_bulk_and_ajax() {
		require_once BBP_PLUGIN_DIR . 'includes/admin/tools/repair.php';
		$forum = $this->factory->forum->create();
		$topic = $this->factory->topic->create();
		$user = $this->factory->user->create();
		$tools = array(
			'bbp-user-favorites' => array( 'favorites', $topic ),
			'bbp-user-topic-subscriptions' => array( 'subscriptions', $topic ),
			'bbp-user-forum-subscriptions' => array( 'forum_subscriptions', $forum )
		);
		foreach ( $tools as $data ) {
			add_user_meta( $user, bbp_db()->prefix . '_bbp_' . $data[0], $data[1] . ',999999' );
		}
		$size = static function () { return 1; };
		add_filter( 'bbp_admin_repair_tool_batch_size', $size );
		bbp_register_default_repair_tools();
		$ids = array_keys( $tools );
		$run_id = '';
		try {
			foreach ( $ids as $index => $id ) {
				$_GET = array(
					'page' => 'bbp-repair',
					'action' => 'run',
					'checked' => array_slice( $ids, $index ),
					'bbp-tools-run' => $run_id,
					'_wpnonce' => wp_create_nonce( 'bbpress-do-counts' )
				);
				$_REQUEST = $_GET;
				$_SERVER['REQUEST_METHOD'] = 'GET';
				bbp_admin_repair_handler();
				$state = bbp_admin_repair_tool_get_run_state( 'repair' );
				$run_id = $state['id'];
				$this->assertSame( array_slice( $ids, $index ), $state['tools'] );
				$key = bbp_db()->prefix . '_bbp_' . $tools[ $id ][0];
				$this->assertSame( $tools[ $id ][1] . ',999999', get_user_meta( $user, $key, true ) );
				for ( $batch = 0; $batch < 20; ++$batch ) {
					$response = $this->ajax( 'repair', $id, wp_create_nonce( 'bbpress-tools-process' ), 'POST', $run_id );
					$this->assertTrue( $response['success'] );
					if ( $response['data']['complete'] ) {
						break;
					}
				}
				$this->assertTrue( $response['data']['complete'] );
				$this->assertFalse( get_option( bbp_admin_repair_tool_batch_option_name( $id ), false ) );
				$this->assertSame( (string) $tools[ $id ][1], get_user_meta( $user, $key, true ) );
			}
		} finally {
			remove_filter( 'bbp_admin_repair_tool_batch_size', $size );
		}
		$this->assertFalse( bbp_admin_repair_tool_get_run_state( 'repair' ) );
	}

	/**
	 * @ticket 3777
	 */
	public function test_legacy_list_repairs_clear_cached_user_strategy_relationships() {
		update_option( '_bbp_engagements_strategy', 'user' );
		bbp_register_default_repair_tools();
		$strategy = new BBP_User_Engagements_User();
		$tools    = array(
			'bbp-user-favorites'           => array( 'favorites', 'topic', '_bbp_favorite' ),
			'bbp-user-topic-subscriptions' => array( 'subscriptions', 'topic', '_bbp_subscription' ),
			'bbp-user-forum-subscriptions' => array( 'forum_subscriptions', 'forum', '_bbp_subscription' )
		);
		foreach ( $tools as $id => $args ) {
			list( $suffix, $post_type, $meta_key ) = $args;
			$user  = $this->factory->user->create();
			$valid = $this->factory->{$post_type}->create();
			$draft = $this->factory->post->create( array( 'post_type' => $post_type, 'post_status' => 'draft' ) );
			$key   = bbp_db()->prefix . '_bbp_' . $suffix;
			add_user_meta( $user, $key, $valid . ',' . $draft );
			$this->assertSame( array( $user ), $strategy->get_users_for_object( $draft, $meta_key ) );
			$_GET = array(
				'page'     => 'bbp-repair',
				'action'   => 'run',
				'checked'  => array( $id ),
				'_wpnonce' => wp_create_nonce( 'bbpress-do-counts' )
			);
			$_REQUEST                  = $_GET;
			$_SERVER['REQUEST_METHOD'] = 'GET';
			bbp_admin_repair_handler();
			$state = bbp_admin_repair_tool_get_run_state( 'repair' );
			for ( $request = 0; $request < 20; ++$request ) {
				$response = $this->ajax( 'repair', $id, wp_create_nonce( 'bbpress-tools-process' ), 'POST', $state['id'] );
				$this->assertTrue( $response['success'] );
				if ( $response['data']['complete'] ) {
					break;
				}
			}
			$this->assertTrue( $response['data']['complete'] );
			$this->assertSame( (string) $valid, get_user_meta( $user, $key, true ) );
			$this->assertSame( array(), $strategy->get_users_for_object( $draft, $meta_key ) );
		}
	}

	/**
	 * @ticket 3777
	 * @covers ::bbp_admin_repair_handler
	 * @covers ::bbp_admin_repair_ajax_handler
	 */
	public function test_bulk_legacy_cleanup_advances_after_each_bounded_tool_finishes() {
		$user  = $this->factory->user->create();
		$tools = array(
			'bbp-user-favorites-delete'           => 'favorites',
			'bbp-user-topic-subscriptions-delete' => 'subscriptions',
			'bbp-user-forum-subscriptions-delete' => 'forum_subscriptions'
		);
		foreach ( $tools as $suffix ) {
			add_user_meta( $user, bbp_db()->prefix . '_bbp_' . $suffix, '1,2' );
			add_user_meta( $user, bbp_db()->prefix . '_bbp_' . $suffix, '3,4' );
		}
		$size = static function () { return 1; };
		add_filter( 'bbp_admin_repair_tool_batch_size', $size );
		bbp_register_default_repair_tools();
		$ids = array_keys( $tools );
		$_GET = array(
			'page'     => 'bbp-upgrade',
			'action'   => 'run',
			'checked'  => $ids,
			'_wpnonce' => wp_create_nonce( 'bbpress-do-counts' )
		);
		$_REQUEST                  = $_GET;
		$_SERVER['REQUEST_METHOD'] = 'GET';
		bbp_admin_repair_handler();
		$state  = bbp_admin_repair_tool_get_run_state( 'upgrade' );
		$run_id = $state['id'];
		$this->assertSame( $ids, $state['tools'] );
		try {
			foreach ( $ids as $index => $id ) {
				$key = bbp_db()->prefix . '_bbp_' . $tools[ $id ];
				$this->assertSame( array( '1,2', '3,4' ), get_user_meta( $user, $key, false ) );
				for ( $request = 0; $request < 20; ++$request ) {
					$response = $this->ajax( 'upgrade', $id, wp_create_nonce( 'bbpress-tools-process' ), 'POST', $run_id );
					$this->assertTrue( $response['success'] );
					if ( $response['data']['complete'] ) {
						break;
					}
				}
				$this->assertTrue( $response['data']['complete'] );
				$this->assertFalse( get_option( bbp_admin_repair_tool_batch_option_name( $id ), false ) );
				$this->assertSame( array(), get_user_meta( $user, $key, false ) );
				$remaining = array_slice( $ids, $index + 1 );
				$state     = bbp_admin_repair_tool_get_run_state( 'upgrade' );
				$this->assertSame( $remaining, $state ? $state['tools'] : array() );
			}
		} finally {
			remove_filter( 'bbp_admin_repair_tool_batch_size', $size );
		}
		$this->assertFalse( bbp_admin_repair_tool_get_run_state( 'upgrade' ) );
	}

	/**
	 * @ticket 3777
	 * @covers ::bbp_admin_repair_handler
	 * @covers ::bbp_admin_repair_ajax_handler
	 */
	public function test_batched_role_remapping_completes_before_the_next_bulk_tool() {
		$ids = $this->factory->user->create_many( 3, array( 'role' => 'subscriber' ) );
		foreach ( $ids as $id ) {
			bbp_set_user_role( $id, bbp_get_spectator_role() );
		}
		$this->assert_native_repair_finishes_before_next_tool( 'bbp-user-role-map', 25 );
		foreach ( $ids as $id ) {
			$this->assertSame( bbp_get_default_role(), bbp_get_user_role( $id ) );
		}
	}

	public function test_batched_freshness_completes_before_the_next_bulk_tool() {
		$forum = $this->factory->forum->create();
		$topic = $this->factory->topic->create( array( 'post_parent' => $forum ) );
		$reply = $this->factory->reply->create( array( 'post_parent' => $topic ) );
		$this->assert_native_repair_finishes_before_next_tool( 'bbp-sync-all-topics-forums', 100 );
		$this->assertSame( (string) $reply, get_post_meta( $topic, '_bbp_last_active_id', true ) );
		$this->assertSame( (string) $reply, get_post_meta( $forum, '_bbp_last_active_id', true ) );
	}

	public function test_batched_sticky_assignments_complete_before_the_next_bulk_tool() {
		$first = $this->factory->forum->create();
		$other = $this->factory->forum->create();
		$topic = $this->factory->topic->create( array( 'post_parent' => $first ) );
		update_post_meta( $other, '_bbp_sticky_topics', array( $topic, $topic, $topic ) );
		$this->assert_native_repair_finishes_before_next_tool( 'bbp-sync-all-topics-sticky', 30 );
		$this->assertSame( '', get_post_meta( $other, '_bbp_sticky_topics', true ) );
	}

	public function test_batched_visibility_completes_before_the_next_bulk_tool() {
		$private = $this->factory->forum->create_many( 3, array( 'post_status' => bbp_get_private_status_id() ) );
		$hidden  = $this->factory->forum->create_many( 2, array( 'post_status' => bbp_get_hidden_status_id() ) );
		update_option( '_bbp_private_forums', array() );
		update_option( '_bbp_hidden_forums', array() );
		$this->assert_native_repair_finishes_before_next_tool( 'bbp-sync-forum-visibility', 30 );
		$this->assertSame( $private, get_option( '_bbp_private_forums' ) );
		$this->assertSame( $hidden, get_option( '_bbp_hidden_forums' ) );
	}

	public function test_batched_reply_positions_complete_before_the_next_bulk_tool() {
		$topic = $this->factory->topic->create();
		$ids = $this->factory->reply->create_many( 3, array( 'post_parent' => $topic, 'menu_order' => 7 ) );
		$this->assert_native_repair_finishes_before_next_tool( 'bbp-sync-all-reply-positions', 40 );
		foreach ( $ids as $index => $id ) {
			$this->assertSame( $index + 1, (int) get_post_field( 'menu_order', $id ) );
		}
	}

	private function assert_native_repair_finishes_before_next_tool( $tool_id, $limit ) {
		$size = static function () { return 1; };
		$next = 0;
		bbp_register_default_repair_tools();
		$register = static function () use ( &$next ) {
			bbp_register_repair_tool( array(
				'id' => 'bbp-test-after-native-batch',
				'type' => 'repair',
				'title' => 'After native batching',
				'priority' => 105,
				'callbacks' => array( 'run' => static function () use ( &$next ) { ++$next; return array( 0, 'Complete.' ); } )
			) );
		};
		add_action( 'bbp_register_repair_tools', $register, 11 );
		$register();
		add_filter( 'bbp_admin_repair_tool_batch_size', $size );
		$_GET = array(
			'page'     => 'bbp-repair',
			'action'   => 'run',
			'checked'  => array( $tool_id, 'bbp-test-after-native-batch' ),
			'_wpnonce' => wp_create_nonce( 'bbpress-do-counts' )
		);
		$_REQUEST                  = $_GET;
		$_SERVER['REQUEST_METHOD'] = 'GET';
		try {
			bbp_admin_repair_handler();
			$state = bbp_admin_repair_tool_get_run_state( 'repair' );
			$this->assertSame( 0, $next );
			for ( $i = 0; $i < $limit; ++$i ) {
				$response = $this->ajax( 'repair', $tool_id, wp_create_nonce( 'bbpress-tools-process' ), 'POST', $state['id'] );
				$this->assertTrue( $response['success'] );
				$this->assertSame( 0, $next );
				if ( $response['data']['complete'] ) {
					break;
				}
			}
			$this->assertTrue( $response['data']['complete'] );
			$this->assertSame( array( 'bbp-test-after-native-batch' ), bbp_admin_repair_tool_get_run_state( 'repair' )['tools'] );

			$after = bbp_admin_repair_tool_get_run_state( 'repair' );
			$response = $this->ajax( 'repair', 'bbp-test-after-native-batch', wp_create_nonce( 'bbpress-tools-process' ), 'POST', $after['id'] );
			$this->assertTrue( $response['success'] );
			$this->assertSame( 1, $next );
			$this->assertFalse( bbp_admin_repair_tool_get_run_state( 'repair' ) );
		} finally {
			remove_filter( 'bbp_admin_repair_tool_batch_size', $size );
			remove_action( 'bbp_register_repair_tools', $register, 11 );
		}
	}

	public function test_failed_first_migration_batch_cannot_be_followed_by_destructive_bulk_cleanup() {
		require_once BBP_PLUGIN_DIR . 'includes/admin/tools/upgrade.php';
		$user_id = $this->factory->user->create();
		$posts   = $this->factory->topic->create_many( 3 );
		$key     = bbp_db()->prefix . '_bbp_favorites';
		add_user_meta( $user_id, $key, implode( ',', $posts ) );
		bbp_register_default_repair_tools();
		$fail = static function ( $check, $post_id, $meta_key ) use ( $posts ) {
			return ( ( $posts[1] === $post_id ) && ( '_bbp_favorite' === $meta_key ) ) ? false : $check;
		};
		add_filter( 'add_post_metadata', $fail, 10, 3 );
		$_GET = array(
			'page'     => 'bbp-upgrade',
			'action'   => 'run',
			'checked'  => array( 'bbp-user-favorites-move', 'bbp-user-favorites-delete' ),
			'_wpnonce' => wp_create_nonce( 'bbpress-do-counts' )
		);
		$_REQUEST                  = $_GET;
		$_SERVER['REQUEST_METHOD'] = 'GET';
		try {
			bbp_admin_repair_handler();
			$settings = $this->runner_settings();
			$response = $this->ajax( 'upgrade', 'bbp-user-favorites-move', $settings['nonce'], 'POST', $settings['run'] );
		} finally {
			remove_filter( 'add_post_metadata', $fail, 10 );
		}
		$this->assertFalse( $response['success'] );
		$this->assertSame( 'error', bbp_admin_repair_tool_get_run_state( 'upgrade' )['status'] );
		$this->assertSame( implode( ',', $posts ), get_user_meta( $user_id, $key, true ) );
		$this->assertSame( array(), get_post_meta( $posts[1], '_bbp_favorite', false ) );
		$this->assertTrue( bbp_admin_repair_tool_run_automatically( 'bbp-user-favorites-move', 'bbp_admin_upgrade_user_favorites' ) );
		$this->assertSame( 0, bbp_admin_upgrade_remove_favorites_from_usermeta()[0] );
		foreach ( $posts as $post_id ) {
			$this->assertSame( array( (string) $user_id ), get_post_meta( $post_id, '_bbp_favorite', false ) );
		}
	}

	/**
	 * @ticket 3777
	 * @covers ::bbp_admin_repair_handler
	 * @covers ::bbp_admin_repair_ajax_handler
	 */
	public function test_completed_batched_tool_can_be_run_again_from_the_beginning() {
		$this->add_registration_action();
		$this->register_tools();

		$_GET = array(
			'page'     => 'bbp-upgrade',
			'action'   => 'run',
			'checked'  => array( 'bbp-test-batch-one' ),
			'_wpnonce' => wp_create_nonce( 'bbpress-do-counts' )
		);
		$_REQUEST                  = $_GET;
		$_SERVER['REQUEST_METHOD'] = 'GET';

		bbp_admin_repair_handler();
		$first = bbp_admin_repair_tool_get_run_state( 'upgrade' );
		$nonce = wp_create_nonce( 'bbpress-tools-process' );
		$this->ajax( 'upgrade', 'bbp-test-batch-one', $nonce, 'POST', $first['id'] );
		$this->ajax( 'upgrade', 'bbp-test-batch-one', $nonce, 'POST', $first['id'] );
		$this->ajax( 'upgrade', 'bbp-test-batch-one', $nonce, 'POST', $first['id'] );

		$this->assertSame( array( 'one', 'one', 'one' ), $this->calls );
		$this->assertFalse( bbp_admin_repair_tool_get_run_state( 'upgrade' ) );

		$this->calls         = array();
		bbp_admin()->notices = array();
		bbp_admin_repair_handler();
		$second = bbp_admin_repair_tool_get_run_state( 'upgrade' );

		$this->assertNotSame( $first['id'], $second['id'] );
		$this->ajax( 'upgrade', 'bbp-test-batch-one', $nonce, 'POST', $second['id'] );
		$this->ajax( 'upgrade', 'bbp-test-batch-one', $nonce, 'POST', $second['id'] );
		$this->ajax( 'upgrade', 'bbp-test-batch-one', $nonce, 'POST', $second['id'] );
		$this->assertSame( array( 'one', 'one', 'one' ), $this->calls );
		$this->assertFalse( bbp_admin_repair_tool_get_run_state( 'upgrade' ) );
	}

	/**
	 * @ticket 3777
	 * @covers ::bbp_admin_repair_ajax_handler
	 */
	public function test_ajax_runner_rejects_invalid_requests_without_running_a_tool() {
		$this->add_registration_action();
		update_option( '_bbp_db_pending_upgrades', array( 'bbp-test-batch-one' ) );
		$nonce = wp_create_nonce( 'bbpress-tools-process' );
		$state = bbp_admin_repair_tool_save_run_state( 'upgrade', array( 'bbp-test-batch-one' ) );

		$responses = array(
			$this->ajax( 'upgrade', 'bbp-test-batch-one', $nonce, 'GET', $state['id'] ),
			$this->ajax( 'invalid', 'bbp-test-batch-one', $nonce, 'POST', $state['id'] ),
			$this->ajax( 'upgrade', 'bbp-test-batch-one', 'invalid', 'POST', $state['id'] ),
			$this->ajax( 'upgrade', 'missing-tool', $nonce, 'POST', $state['id'] )
		);
		$lock        = bbp_admin_repair_tool_lock( 'bbp-test-batch-one' );
		$responses[] = $this->ajax( 'upgrade', 'bbp-test-batch-one', $nonce, 'POST', $state['id'] );
		bbp_admin_repair_tool_unlock( $lock );

		$administrator = get_current_user_id();
		$subscriber    = $this->factory->user->create( array( 'role' => 'subscriber' ) );
		$this->set_current_user( $subscriber );
		$responses[] = $this->ajax( 'upgrade', 'bbp-test-batch-one', wp_create_nonce( 'bbpress-tools-process' ), 'POST', $state['id'] );
		$this->set_current_user( $administrator );

		foreach ( $responses as $response ) {
			$this->assertFalse( $response['success'] );
			$this->assertArrayHasKey( 'message', $response['data'] );
		}

		$this->assertSame( array(), $this->calls );
		$this->assertSame( array( 'bbp-test-batch-one' ), bbp_get_pending_upgrades() );
	}

	/**
	 * @ticket 3777
	 * @covers ::bbp_admin_repair_ajax_handler
	 */
	public function test_ajax_runner_rejects_stale_run_ownership() {
		$this->add_registration_action();
		$state    = bbp_admin_repair_tool_save_run_state( 'upgrade', array( 'bbp-test-batch-one' ) );
		$response = $this->ajax( 'upgrade', 'bbp-test-batch-one', wp_create_nonce( 'bbpress-tools-process' ), 'POST', 'different-run' );

		$this->assertFalse( $response['success'] );
		$this->assertSame( 'stale_run', $response['data']['code'] );
		$this->assertSame( array(), $this->calls );
		$this->assertSame( $state, bbp_admin_repair_tool_get_run_state( 'upgrade' ) );
	}

	/**
	 * @ticket 3777
	 * @covers ::bbp_admin_repair_tool_run_status_ajax_handler
	 */
	public function test_ajax_run_status_requires_ownership_and_persists_pause() {
		$state = bbp_admin_repair_tool_save_run_state( 'upgrade', array( 'bbp-test-batch-one' ) );
		$nonce = wp_create_nonce( 'bbpress-tools-run-status' );

		$stale = $this->ajax_status( 'upgrade', 'different-run', 'paused', $nonce );
		$this->assertFalse( $stale['success'] );
		$this->assertSame( 'running', bbp_admin_repair_tool_get_run_state( 'upgrade' )['status'] );

		$paused = $this->ajax_status( 'upgrade', $state['id'], 'paused', $nonce );
		$this->assertTrue( $paused['success'] );
		$this->assertSame( 'paused', bbp_admin_repair_tool_get_run_state( 'upgrade' )['status'] );
	}

	/**
	 * @ticket 3777
	 * @covers ::bbp_admin_repair_tool_run_status_ajax_handler
	 * @covers ::bbp_admin_repair_tool_discard_run
	 */
	public function test_ajax_discard_deletes_run_and_dry_run_preview_state() {
		$nonce = wp_create_nonce( 'bbpress-tools-run-status' );
		$run   = bbp_admin_repair_tool_save_run_state( 'upgrade', array( 'bbp-test-batch-one' ) );

		$response = $this->ajax_status( 'upgrade', $run['id'], 'discarded', $nonce );
		$this->assertTrue( $response['success'] );
		$this->assertFalse( bbp_admin_repair_tool_get_run_state( 'upgrade' ) );

		$preview = bbp_admin_repair_tool_save_run_state( 'upgrade', array( 'bbp-test-batch-one' ), '', 'paused', false, 'dry-run' );
		bbp_admin_repair_tool_execution_context( 'dry-run', $preview['id'] );
		$option = bbp_admin_repair_tool_batch_option_name( 'bbp-test-batch-one' );
		$this->assertTrue( bbp_admin_repair_tool_save_option( $option, array( 'cursor' => 2, 'upper_id' => 5 ) ) );
		bbp_admin_repair_tool_execution_context( 'run', '' );

		$response = $this->ajax_status( 'upgrade', $preview['id'], 'discarded', $nonce, 'dry-run' );
		$this->assertTrue( $response['success'] );
		$this->assertFalse( bbp_admin_repair_tool_get_run_state( 'upgrade', 'dry-run' ) );
		$this->assertFalse( get_option( $option, false ) );
	}

	/**
	 * @ticket 3777
	 * @covers ::bbp_admin_repair_tool_discard_run
	 * @covers ::bbp_admin_repair_tool_delete_batch_state
	 */
	public function test_discarded_run_deletes_its_batch_state_without_deleting_a_preview() {
		$tool    = 'bbp-test-batch-one';
		$run     = bbp_admin_repair_tool_save_run_state( 'upgrade', array( $tool ) );
		$preview = bbp_admin_repair_tool_save_run_state( 'upgrade', array( $tool ), '', 'paused', false, 'dry-run' );

		$this->assertTrue( bbp_admin_repair_tool_save_checkpoint( $tool, array( 'cursor' => 5, 'upper_id' => 10 ) ) );
		$run_option = bbp_admin_repair_tool_batch_option_name( $tool );
		$run_scratch = $run_option . '_scratch';
		$this->assertTrue( bbp_admin_repair_tool_save_option( $run_scratch, array( 'value' => 'run' ) ) );

		bbp_admin_repair_tool_execution_context( 'dry-run', $preview['id'] );
		$preview_option = bbp_admin_repair_tool_batch_option_name( $tool );
		$this->assertTrue( bbp_admin_repair_tool_save_option( $preview_option, array( 'cursor' => 2, 'upper_id' => 10 ) ) );
		bbp_admin_repair_tool_execution_context( 'run', '' );

		$this->assertTrue( bbp_admin_repair_tool_discard_run( 'upgrade', $run['id'] ) );
		$this->assertFalse( bbp_admin_repair_tool_get_run_state( 'upgrade' ) );
		$this->assertFalse( get_option( $run_option, false ) );
		$this->assertFalse( get_option( $run_scratch, false ) );
		$this->assertSame( array( 'cursor' => 2, 'upper_id' => 10 ), get_option( $preview_option ) );
		$this->assertSame( $preview, bbp_admin_repair_tool_get_run_state( 'upgrade', 'dry-run' ) );

		$this->assertTrue( bbp_admin_repair_tool_discard_run( 'upgrade', $preview['id'], 'dry-run' ) );
	}

	/**
	 * @ticket 3777
	 * @covers ::bbp_admin_repair_tool_discard_run
	 * @covers ::bbp_admin_repair_tool_delete_batch_state
	 */
	public function test_discard_waits_for_an_in_flight_batch_before_deleting_state() {
		$tool  = 'bbp-test-batch-one';
		$state = bbp_admin_repair_tool_save_run_state( 'upgrade', array( $tool ), '', 'running', false, 'dry-run' );

		bbp_admin_repair_tool_execution_context( 'dry-run', $state['id'] );
		$option = bbp_admin_repair_tool_batch_option_name( $tool );
		$this->assertTrue( bbp_admin_repair_tool_save_option( $option, array( 'cursor' => 2, 'upper_id' => 10 ) ) );
		bbp_admin_repair_tool_execution_context( 'run', '' );

		$lock = bbp_admin_repair_tool_lock( $tool );
		try {
			$this->assertFalse( bbp_admin_repair_tool_discard_run( 'upgrade', $state['id'], 'dry-run' ) );
			$this->assertSame( $state, bbp_admin_repair_tool_get_run_state( 'upgrade', 'dry-run' ) );
			$this->assertSame( array( 'cursor' => 2, 'upper_id' => 10 ), get_option( $option ) );
		} finally {
			bbp_admin_repair_tool_unlock( $lock );
		}

		$this->assertTrue( bbp_admin_repair_tool_discard_run( 'upgrade', $state['id'], 'dry-run' ) );
		$this->assertFalse( bbp_admin_repair_tool_get_run_state( 'upgrade', 'dry-run' ) );
		$this->assertFalse( get_option( $option, false ) );
	}

	/**
	 * @ticket 3777
	 * @covers ::bbp_admin_repair_ajax_handler
	 */
	public function test_ajax_runner_preserves_action_keyed_callback_overrides() {
		$this->add_registration_action();
		update_option( '_bbp_db_pending_upgrades', array( 'bbp-test-batch-one' ) );
		$state    = bbp_admin_repair_tool_save_run_state( 'upgrade', array( 'bbp-test-batch-one' ) );
		$override = function ( $tools, $type ) {
			if ( 'upgrade' === $type ) {
				$tools['bbp-test-batch-one']['callbacks']['run'] = array( $this, 'run_override_tool' );
			}

			return $tools;
		};
		add_filter( 'bbp_get_admin_repair_tools', $override, 10, 2 );

		try {
			$response = $this->ajax( 'upgrade', 'bbp-test-batch-one', wp_create_nonce( 'bbpress-tools-process' ), 'POST', $state['id'] );
		} finally {
			remove_filter( 'bbp_get_admin_repair_tools', $override, 10 );
		}

		$this->assertTrue( $response['success'] );
		$this->assertTrue( $response['data']['complete'] );
		$this->assertSame( array( 'override' ), $this->calls );
		$this->assertSame( array(), bbp_get_pending_upgrades() );
	}

	/**
	 * @ticket 3777
	 * @covers ::bbp_admin_repair_handler
	 */
	public function test_dry_run_starts_the_action_specific_browser_runner() {
		$run_called = false;
		bbp_register_repair_tool(
			array(
				'id'        => 'bbp-test-dry-run',
				'type'      => 'repair',
				'title'     => 'Test dry run',
				'priority'  => 10,
				'callbacks' => array(
					'run'     => function () use ( &$run_called ) {
						$run_called = true;
						return array( 0, 'Run complete.' );
					},
					'dry-run' => function () {
						return array( 0, 'Preview complete.', 'complete' => false );
					}
				)
			)
		);

		update_option( '_bbp_db_pending_upgrades', array( 'bbp-test-dry-run' ) );
		$_GET = array(
			'page'     => 'bbp-repair',
			'action'   => 'dry-run',
			'checked'  => array( 'bbp-test-dry-run' ),
			'_wpnonce' => wp_create_nonce( 'bbpress-do-counts' )
		);
		$_REQUEST                  = $_GET;
		$_SERVER['REQUEST_METHOD'] = 'GET';

		bbp_admin_repair_handler();

		$this->assertFalse( $run_called );
		$this->assertTrue( wp_script_is( 'bbp-admin-tools-js', 'enqueued' ) );
		$this->assertStringContainsString( 'bbp-tools-runner', end( bbp_admin()->notices ) );
		$settings = $this->runner_settings();
		$this->assertSame( 'dry-run', $settings['action'] );
		$this->assertSame( array( 'bbp-test-dry-run' ), $settings['tools'] );
		parse_str( wp_parse_url( $settings['stopUrl'], PHP_URL_QUERY ), $stop_query );
		$this->assertSame( $settings['run'], $stop_query['bbp-tools-discard'] );
		$this->assertSame( 'dry-run', $stop_query['bbp-tools-discard-action'] );
		$this->assertSame( 1, wp_verify_nonce( $stop_query['_wpnonce'], 'bbpress-tools-discard-dry-run-' . $settings['run'] ) );
		$state = bbp_admin_repair_tool_get_run_state( 'repair', 'dry-run' );
		$this->assertSame( $settings['run'], $state['id'] );
		$this->assertSame( array( 'bbp-test-dry-run' ), $state['tools'] );
		$this->assertSame( array( 'bbp-test-dry-run' ), bbp_get_pending_upgrades() );
	}

	/**
	 * @ticket 3777
	 * @covers ::bbp_admin_repair_ajax_handler
	 */
	public function test_dry_run_ajax_continues_bounded_batches_without_clearing_pending_upgrades() {
		$this->add_registration_action();
		update_option( '_bbp_db_pending_upgrades', array( 'bbp-test-batch-one' ) );
		$_GET['page'] = 'bbp-upgrade';
		$state = bbp_admin_repair_tool_enqueue_runner( 'upgrade', array( 'bbp-test-batch-one' ), '', 'dry-run' );
		$nonce = wp_create_nonce( 'bbpress-tools-process' );

		for ( $request = 1; $request <= 3; ++$request ) {
			$response = $this->ajax( 'upgrade', 'bbp-test-batch-one', $nonce, 'POST', $state['id'], 'dry-run' );
			$this->assertTrue( $response['success'] );
			$this->assertSame( 3 === $request, $response['data']['complete'] );
			$this->assertSame( $request, $response['data']['batches'] );
			if ( $request < 3 ) {
				$this->assertStringContainsString( 'Dry run in progress. No changes have been made.', $response['data']['message'] );
				$this->assertSame( $state['id'], bbp_admin_repair_tool_get_run_state( 'upgrade', 'dry-run' )['id'] );
			}
		}

		$this->assertStringContainsString( 'Dry run complete! No changes were made.', $response['data']['message'] );
		$this->assertStringContainsString( 'Estimated batches for a full Run: 3.', $response['data']['message'] );
		$this->assertFalse( bbp_admin_repair_tool_get_run_state( 'upgrade', 'dry-run' ) );
		$this->assertSame( array( 'bbp-test-batch-one' ), bbp_get_pending_upgrades() );
		$this->assertSame( array( 'dry-run', 'dry-run', 'dry-run' ), $this->calls );
	}

	/**
	 * @ticket 3777
	 * @covers ::bbp_admin_repair_handler
	 * @covers ::bbp_admin_repair_ajax_handler
	 */
	public function test_native_freshness_dry_run_uses_multiple_ajax_requests_without_writes() {
		$forum = $this->factory->forum->create();
		$ids   = array( $forum );
		foreach ( $this->factory->topic->create_many( 3, array( 'post_parent' => $forum ) ) as $topic_id ) {
			$ids[] = $topic_id;
			$ids[] = $this->factory->reply->create( array( 'post_parent' => $topic_id ) );
		}
		$before = array();
		foreach ( $ids as $id ) {
			$before[ $id ] = get_post_meta( $id );
		}

		$size = static function () {
			return 1;
		};
		add_filter( 'bbp_admin_repair_tool_batch_size', $size );
		bbp_register_default_repair_tools();
		$_GET = array(
			'page'     => 'bbp-repair',
			'action'   => 'dry-run',
			'checked'  => array( 'bbp-sync-all-topics-forums' ),
			'_wpnonce' => wp_create_nonce( 'bbpress-do-counts' ),
		);
		$_REQUEST                  = $_GET;
		$_SERVER['REQUEST_METHOD'] = 'GET';

		try {
			bbp_admin_repair_handler();
			$state = bbp_admin_repair_tool_get_run_state( 'repair', 'dry-run' );
			$this->assertNotFalse( $state );
			for ( $request = 1; $request < 300; ++$request ) {
				$response = $this->ajax( 'repair', 'bbp-sync-all-topics-forums', wp_create_nonce( 'bbpress-tools-process' ), 'POST', $state['id'], 'dry-run' );
				$this->assertTrue( $response['success'] );
				if ( $response['data']['complete'] ) {
					break;
				}
			}
		} finally {
			remove_filter( 'bbp_admin_repair_tool_batch_size', $size );
		}

		$this->assertTrue( $response['data']['complete'] );
		$this->assertGreaterThan( 1, $request );
		$this->assertStringContainsString( 'Dry run complete! No changes were made.', $response['data']['message'] );
		$this->assertFalse( bbp_admin_repair_tool_get_run_state( 'repair', 'dry-run' ) );
		foreach ( $ids as $id ) {
			$this->assertSame( $before[ $id ], get_post_meta( $id ) );
		}
	}

	/**
	 * @ticket 3777
	 * @covers ::bbp_admin_repair_handler
	 */
	public function test_resumed_dry_run_rotates_ownership_without_moving_its_preview_checkpoint() {
		$this->add_registration_action();
		bbp_register_repair_tools();
		$tools = array( 'bbp-test-batch-one' );
		$state = bbp_admin_repair_tool_save_run_state( 'upgrade', $tools, '', 'paused', false, 'dry-run' );

		bbp_admin_repair_tool_execution_context( 'dry-run', $state['id'] );
		$old_option = bbp_admin_repair_tool_batch_option_name( $tools[0] );
		$this->assertTrue( bbp_admin_repair_tool_save_option( $old_option, array( 'cursor' => 2, 'upper_id' => 5 ) ) );
		bbp_admin_repair_tool_execution_context( 'run', '' );

		$_GET = array(
			'page'          => 'bbp-upgrade',
			'action'        => 'dry-run',
			'checked'       => $tools,
			'bbp-tools-run' => $state['id'],
			'_wpnonce'      => wp_create_nonce( 'bbpress-do-counts' ),
		);
		$_REQUEST                  = $_GET;
		$_SERVER['REQUEST_METHOD'] = 'GET';
		bbp_admin_repair_handler();

		$settings = $this->runner_settings();
		$this->assertSame( 'dry-run', $settings['action'] );
		$this->assertNotSame( $state['id'], $settings['run'] );
		$this->assertSame( array( 'cursor' => 2, 'upper_id' => 5 ), get_option( $old_option ) );
		list( $read, $stored ) = bbp_admin_repair_tool_read_run_state( 'upgrade', false, 'dry-run' );
		$this->assertTrue( $read );
		$this->assertSame( $state['id'], $stored['preview_id'] );

		bbp_admin_repair_tool_execution_context( 'dry-run', $settings['run'] );
		$new_option = bbp_admin_repair_tool_batch_option_name( $tools[0] );
		bbp_admin_repair_tool_execution_context( 'run', '' );
		$this->assertNotSame( $old_option, $new_option );
		$this->assertFalse( get_option( $new_option, false ) );
		$this->assertTrue( bbp_admin_repair_tool_delete_batch_state( $tools, $state['id'], 'dry-run' ) );
		$this->assertTrue( bbp_admin_repair_tool_delete_run_state( 'upgrade', $settings['run'], 'dry-run' ) );
	}

	/**
	 * @ticket 3777
	 * @covers ::bbp_admin_tools_repair_upgrade_notices
	 */
	public function test_completion_notice_requires_the_page_capability_and_nonce() {
		$this->add_registration_action();
		bbp_register_repair_tools();
		$this->assertSame( 1, $this->registration_calls );
		$_GET['page'] = 'bbp-upgrade';
		bbp_admin_repair_tool_enqueue_runner( 'upgrade', array( 'bbp-test-batch-one' ) );
		$settings = $this->runner_settings();
		parse_str( wp_parse_url( $settings['completeUrl'], PHP_URL_QUERY ), $complete_query );
		$this->assertSame( 'bbp-test-batch-one', $complete_query['bbp-tools-complete'] );

		$_GET = array(
			'page'               => 'bbp-upgrade',
			'bbp-tools-complete' => 'bbp-test-batch-one',
			'_wpnonce'           => wp_create_nonce( 'bbpress-tools-complete' )
		);

		bbp_admin_tools_repair_upgrade_notices();
		$this->assertStringContainsString( '<strong>Test batch one</strong><br>Completed successfully.', end( bbp_admin()->notices ) );

		bbp_admin()->notices = array();
		$_GET['_wpnonce']    = 'invalid';
		bbp_admin_tools_repair_upgrade_notices();
		$this->assertSame( array(), bbp_admin()->notices );

		$subscriber = $this->factory->user->create( array( 'role' => 'subscriber' ) );
		$this->set_current_user( $subscriber );
		$_GET['_wpnonce'] = wp_create_nonce( 'bbpress-tools-complete' );
		bbp_admin_tools_repair_upgrade_notices();
		$this->assertSame( array(), bbp_admin()->notices );
		$this->assertSame( 1, $this->registration_calls );
	}

	/**
	 * Register the test tools on the shared registration action.
	 */
	public function register_tools() {
		++$this->registration_calls;

		bbp_register_repair_tool(
			array(
				'id'          => 'bbp-test-batch-one',
				'type'        => 'upgrade',
				'title'       => 'Test batch one',
				'priority'    => 10,
				'flush_cache' => false,
				'callbacks'   => array(
					'run'     => array( $this, 'run_first_tool' ),
					'dry-run' => array( $this, 'dry_run_first_tool' ),
				)
			)
		);
	}

	/**
	 * Register a tool through the legacy page-load hook.
	 */
	public function register_legacy_tool() {
		bbp_register_repair_tool(
			array(
				'id'          => 'bbp-test-batch-two',
				'type'        => 'upgrade',
				'title'       => 'Test batch two',
				'priority'    => 11,
				'flush_cache' => false,
				'callback'    => array( $this, 'run_second_tool' )
			)
		);
	}

	/**
	 * Return an incomplete result for two batches, then complete.
	 */
	public function run_first_tool() {
		$this->calls[] = 'one';
		$count         = count( array_keys( $this->calls, 'one', true ) );

		return array(
			0,
			( $count < 3 ) ? 'First tool needs another batch.' : 'First tool complete.',
			'complete' => ( $count >= 3 )
		);
	}

	/**
	 * Complete a Dry Run after three bounded requests.
	 */
	public function dry_run_first_tool() {
		$this->calls[] = 'dry-run';
		$count         = count( $this->calls );

		return array(
			0,
			'Preview batch complete.',
			'complete' => ( $count >= 3 ),
			'batches'  => $count,
		);
	}

	/**
	 * Complete the second tool in one batch.
	 */
	public function run_second_tool() {
		$this->calls[] = 'two';

		return array( 0, 'Second tool complete.' );
	}

	/**
	 * Fail without opting into the batched callback result format.
	 */
	public function run_failed_legacy_tool() {
		$this->calls[] = 'failed-legacy';

		return array( 2, 'Legacy tool failed.', 'success' => false );
	}

	/**
	 * Complete an overridden Run callback.
	 */
	public function run_override_tool() {
		$this->calls[] = 'override';

		return array( 0, 'Overridden tool complete.' );
	}

	/**
	 * Attach test tool registration once.
	 */
	private function add_registration_action() {
		add_action( 'bbp_register_repair_tools', array( $this, 'register_tools' ) );
		add_action( 'bbp_register_repair_tools', array( $this, 'register_legacy_tool' ), 5 );
		$this->registered        = true;
		$this->registered_legacy = true;
	}

	/**
	 * Return the localized browser runner settings.
	 */
	private function runner_settings() {
		$data = wp_scripts()->get_data( 'bbp-admin-tools-js', 'data' );
		$this->assertSame( 1, preg_match( '/var bbpToolsRunner = (.+);/', $data, $matches ) );

		return json_decode( $matches[1], true );
	}

	/**
	 * Call the AJAX handler while capturing its JSON response.
	 */
	private function ajax( $type, $tool, $nonce, $method = 'POST', $run_id = '', $action = 'run' ) {
		$old_post    = $_POST;
		$old_request = $_REQUEST;
		$old_method  = isset( $_SERVER['REQUEST_METHOD'] ) ? $_SERVER['REQUEST_METHOD'] : null;
		$old_tools   = bbp_admin()->tools;
		$body        = '';
		$level       = ob_get_level();
		$die_handler = function() {
			return function() {
				throw new BBP_Tests_Admin_Tools_Runner_Ajax_Exit();
			};
		};

		$_POST = array(
			'action'     => 'bbp_tools_process',
			'nonce'      => $nonce,
			'type'       => $type,
			'toolAction' => $action,
			'tool'       => $tool,
			'run'        => $run_id
		);
		$_REQUEST                  = $_POST;
		$_SERVER['REQUEST_METHOD'] = $method;
		bbp_admin()->tools         = array();
		add_filter( 'wp_doing_ajax', '__return_true' );
		add_filter( 'wp_die_ajax_handler', $die_handler );
		ob_start();

		try {
			bbp_admin_repair_ajax_handler();
			$this->fail( 'Expected the AJAX response to terminate.' );
		} catch ( BBP_Tests_Admin_Tools_Runner_Ajax_Exit $error ) {
			$body = ob_get_clean();
		} finally {
			while ( ob_get_level() > $level ) {
				ob_end_clean();
			}
			remove_filter( 'wp_die_ajax_handler', $die_handler );
			remove_filter( 'wp_doing_ajax', '__return_true' );
			$_POST             = $old_post;
			$_REQUEST          = $old_request;
			bbp_admin()->tools = $old_tools;

			if ( null === $old_method ) {
				unset( $_SERVER['REQUEST_METHOD'] );
			} else {
				$_SERVER['REQUEST_METHOD'] = $old_method;
			}
		}

		return json_decode( $body, true );
	}

	/**
	 * Call the run-status AJAX handler while capturing its JSON response.
	 */
	private function ajax_status( $type, $run_id, $status, $nonce, $action = 'run' ) {
		$old_post    = $_POST;
		$old_request = $_REQUEST;
		$old_method  = isset( $_SERVER['REQUEST_METHOD'] ) ? $_SERVER['REQUEST_METHOD'] : null;
		$body        = '';
		$level       = ob_get_level();
		$die_handler = function() {
			return function() {
				throw new BBP_Tests_Admin_Tools_Runner_Ajax_Exit();
			};
		};

		$_POST = array(
			'action'     => 'bbp_tools_run_status',
			'nonce'      => $nonce,
			'type'       => $type,
			'run'        => $run_id,
			'status'     => $status,
			'toolAction' => $action
		);
		$_REQUEST                  = $_POST;
		$_SERVER['REQUEST_METHOD'] = 'POST';
		add_filter( 'wp_doing_ajax', '__return_true' );
		add_filter( 'wp_die_ajax_handler', $die_handler );
		ob_start();

		try {
			bbp_admin_repair_tool_run_status_ajax_handler();
			$this->fail( 'Expected the AJAX response to terminate.' );
		} catch ( BBP_Tests_Admin_Tools_Runner_Ajax_Exit $error ) {
			$body = ob_get_clean();
		} finally {
			while ( ob_get_level() > $level ) {
				ob_end_clean();
			}
			remove_filter( 'wp_die_ajax_handler', $die_handler );
			remove_filter( 'wp_doing_ajax', '__return_true' );
			$_POST    = $old_post;
			$_REQUEST = $old_request;

			if ( null === $old_method ) {
				unset( $_SERVER['REQUEST_METHOD'] );
			} else {
				$_SERVER['REQUEST_METHOD'] = $old_method;
			}
		}

		return json_decode( $body, true );
	}
}
