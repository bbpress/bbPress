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
	private $old_repair_run_status;
	private $old_upgrade_run_status;
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
		$this->old_repair_run_status  = get_option( '_bbp_tools_run_status_repair', null );
		$this->old_upgrade_run_status = get_option( '_bbp_tools_run_status_upgrade', null );
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
			remove_action( 'load-tools_page_bbp-upgrade', array( $this, 'register_legacy_tool' ), 5 );
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
		if ( null === $this->old_repair_run_status ) {
			delete_option( '_bbp_tools_run_status_repair' );
		} else {
			update_option( '_bbp_tools_run_status_repair', $this->old_repair_run_status );
		}
		if ( null === $this->old_upgrade_run_status ) {
			delete_option( '_bbp_tools_run_status_upgrade' );
		} else {
			update_option( '_bbp_tools_run_status_upgrade', $this->old_upgrade_run_status );
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
			array( 'success' => true, 'complete' => true, 'message' => 'Complete.' ),
			bbp_admin_repair_tool_normalize_result( array( 0, 'Complete.' ) )
		);
		$this->assertSame(
			array( 'success' => true, 'complete' => true, 'message' => 'Nothing to do.' ),
			bbp_admin_repair_tool_normalize_result( array( 1, 'Nothing to do.' ) )
		);
		$this->assertSame(
			array( 'success' => true, 'complete' => false, 'message' => 'More.' ),
			bbp_admin_repair_tool_normalize_result( array( 0, 'More.', 'complete' => false ) )
		);
		$this->assertSame(
			array( 'success' => false, 'complete' => false, 'message' => 'Stopped.' ),
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
	 * @covers ::bbp_register_repair_tools
	 */
	public function test_repair_tools_include_action_registrations() {
		$this->add_registration_action();
		bbp_register_repair_tools();

		$this->assertArrayHasKey( 'bbp-sync-topic-meta', bbp_admin()->tools );
		$this->assertArrayHasKey( 'bbp-test-batch-one', bbp_admin()->tools );
		$this->assertArrayNotHasKey( 'bbp-test-batch-two', bbp_admin()->tools );
		$this->assertFalse( bbp_admin_repair_tool_flushes_cache( bbp_admin()->tools['bbp-test-batch-one'] ) );
	}

	/**
	 * @ticket 3777
	 * @covers ::bbp_admin_repair_handler
	 */
	public function test_legacy_nothing_to_do_result_clears_pending_and_continues_bulk_run() {
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

		update_option( '_bbp_db_pending_upgrades', array( 'bbp-test-noop', 'bbp-test-after-noop' ) );
		$_GET = array(
			'action'   => 'run',
			'checked'  => array( 'bbp-test-noop', 'bbp-test-after-noop' ),
			'_wpnonce' => wp_create_nonce( 'bbpress-do-counts' )
		);
		$_REQUEST                  = $_GET;
		$_SERVER['REQUEST_METHOD'] = 'GET';

		bbp_admin_repair_handler();

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
	 * @covers ::bbp_admin_repair_tool_run_status_option_name
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
		$this->assertSame( 'paused', get_option( '_bbp_tools_run_status_upgrade' )['status'] );

		$this->assertTrue( bbp_admin_repair_tool_delete_run_state( 'upgrade', $state['id'] ) );
		$this->assertFalse( bbp_admin_repair_tool_update_run_status( 'upgrade', $state['id'], 'running' ) );
		$this->assertFalse( bbp_admin_repair_tool_save_run_state( 'upgrade', array( 'bbp-test-batch-one' ), $state['id'] ) );
		$this->assertFalse( bbp_admin_repair_tool_get_run_state( 'upgrade' ) );
		$this->assertFalse( get_option( '_bbp_tools_run_status_upgrade', false ) );
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

		$new['id']    = wp_generate_uuid4();
		$new['tools'] = array( 'bbp-test-batch-two' );

		// Simulate another request changing the database after our cached read
		$this->assertSame( 1, $db->update( $db->options, array( 'option_value' => maybe_serialize( $new ) ), array( 'option_name' => '_bbp_tools_run_upgrade' ) ) );
		$this->assertSame(
			1,
			$db->update(
				$db->options,
				array( 'option_value' => maybe_serialize( array( 'id' => $new['id'], 'status' => 'paused', 'updated' => time() ) ) ),
				array( 'option_name' => '_bbp_tools_run_status_upgrade' )
			)
		);

		$current = bbp_admin_repair_tool_get_run_state( 'upgrade' );
		$this->assertSame( $new['id'], $current['id'] );
		$this->assertSame( $new['tools'], $current['tools'] );
		$this->assertSame( 'paused', $current['status'] );
		$this->assertFalse( bbp_admin_repair_tool_save_run_state( 'upgrade', $state['tools'], $state['id'] ) );
		$this->assertFalse( bbp_admin_repair_tool_update_run_status( 'upgrade', $state['id'], 'running' ) );
		$this->assertFalse( bbp_admin_repair_tool_delete_run_state( 'upgrade', $state['id'] ) );
		$this->assertSame( $current, bbp_admin_repair_tool_get_run_state( 'upgrade' ) );
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
		add_filter( 'pre_update_option__bbp_tools_run_status_upgrade', $filter, 10, 2 );

		try {
			$this->assertFalse( bbp_admin_repair_tool_save_run_state( 'upgrade', array( 'bbp-test-batch-two' ), $state['id'], null ) );
			$this->assertFalse( bbp_admin_repair_tool_update_run_status( 'upgrade', $state['id'], 'paused' ) );
			$this->assertSame( $state, bbp_admin_repair_tool_get_run_state( 'upgrade' ) );
		} finally {
			remove_filter( 'pre_update_option__bbp_tools_run_upgrade', $filter, 10 );
			remove_filter( 'pre_update_option__bbp_tools_run_status_upgrade', $filter, 10 );
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

		bbp_admin()->notices = array();
		bbp_admin_repair_tool_update_run_status( 'upgrade', $state['id'], 'running' );
		bbp_admin_tools_repair_upgrade_notices();
		$this->assertStringContainsString( 'appears to be in progress', end( bbp_admin()->notices ) );
		$this->assertStringNotContainsString( '>Resume</a>', end( bbp_admin()->notices ) );
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
		$this->assertSame( array( 'one' ), $this->calls );

		$stale = $this->ajax( 'upgrade', 'bbp-test-batch-one', wp_create_nonce( 'bbpress-tools-process' ), 'POST', $old_state['id'] );
		$this->assertFalse( $stale['success'] );
		$this->assertSame( 'stale_run', $stale['data']['code'] );
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
		$this->assertStringContainsString( 'appears to be in progress', end( bbp_admin()->notices ) );
	}

	/**
	 * @ticket 3777
	 * @covers ::bbp_admin_repair_handler
	 */
	public function test_one_off_lock_contention_does_not_create_recovery_state() {
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
		} finally {
			bbp_admin_repair_tool_unlock( $lock );
		}

		$this->assertSame( array(), $this->calls );
		$this->assertFalse( bbp_admin_repair_tool_get_run_state( 'upgrade' ) );
		$this->assertStringContainsString( 'temporarily unavailable', end( bbp_admin()->notices ) );
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
		} finally {
			bbp_admin_repair_tool_unlock( $lock );
		}

		$state = bbp_admin_repair_tool_get_run_state( 'upgrade' );
		$this->assertSame( array(), $this->calls );
		$this->assertSame( array( 'bbp-test-batch-one', 'bbp-test-batch-two' ), $state['tools'] );
		$this->assertSame( 'error', $state['status'] );
		$this->assertStringContainsString( 'temporarily unavailable', end( bbp_admin()->notices ) );
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
	public function test_runner_start_failure_reports_an_error_and_retains_the_bulk_queue() {
		$this->register_tools();
		$this->register_legacy_tool();
		$blocked = false;
		$filter  = static function ( $value, $old_value ) use ( &$blocked ) {
			if ( ! $blocked ) {
				$blocked = true;
				return $old_value;
			}

			return $value;
		};
		add_filter( 'pre_update_option__bbp_tools_run_upgrade', $filter, 10, 2 );
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
			remove_filter( 'pre_update_option__bbp_tools_run_upgrade', $filter, 10 );
		}

		$state = bbp_admin_repair_tool_get_run_state( 'upgrade' );
		$this->assertSame( array( 'one' ), $this->calls );
		$this->assertSame( $_GET['checked'], $state['tools'] );
		$this->assertSame( 'error', $state['status'] );
		$this->assertStringContainsString( 'The tool run could not be saved.', end( bbp_admin()->notices ) );
		$this->assertStringContainsString( 'notice error', end( bbp_admin()->notices ) );
		$this->assertFalse( wp_script_is( 'bbp-admin-tools-js', 'enqueued' ) );
	}

	/**
	 * @ticket 3777
	 * @covers ::bbp_admin_repair_handler
	 */
	public function test_legacy_bulk_failures_do_not_create_a_batched_run() {
		$this->register_tools();
		$this->register_legacy_tool();
		$tools = array( 'bbp-test-batch-one', 'bbp-test-batch-two' );
		foreach ( $tools as $tool_id ) {
			bbp_admin()->tools[ $tool_id ]['callbacks']['run'] = static function () {
				return array( 0, 'Failed.', 'success' => false );
			};
		}
		update_option( '_bbp_db_pending_upgrades', $tools );
		$_GET = array(
			'page'     => 'bbp-upgrade',
			'action'   => 'run',
			'checked'  => $tools,
			'_wpnonce' => wp_create_nonce( 'bbpress-do-counts' )
		);
		$_REQUEST                  = $_GET;
		$_SERVER['REQUEST_METHOD'] = 'GET';

		bbp_admin_repair_handler();

		$this->assertSame( $tools, bbp_get_pending_upgrades() );
		$this->assertFalse( bbp_admin_repair_tool_get_run_state( 'upgrade' ) );
		$this->assertFalse( wp_script_is( 'bbp-admin-tools-js', 'enqueued' ) );
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

		$this->assertSame( array( 'one' ), $this->calls );
		$this->assertSame( array( 'bbp-test-batch-one', 'bbp-test-batch-two' ), bbp_get_pending_upgrades() );
		$this->assertTrue( wp_script_is( 'bbp-admin-tools-js', 'enqueued' ) );
		$this->assertStringContainsString( 'bbp-tools-runner', end( bbp_admin()->notices ) );
		$this->assertStringContainsString( 'Continue', end( bbp_admin()->notices ) );
		$this->assertStringContainsString( '<strong>Test batch one</strong><br>First tool needs another batch.', end( bbp_admin()->notices ) );

		$settings = $this->runner_settings();
		$this->assertSame( 'upgrade', $settings['type'] );
		$this->assertSame( array( 'bbp-test-batch-one' ), $settings['tools'] );
		$this->assertNotEmpty( $settings['run'] );
		$this->assertSame( array( 'bbp-test-batch-one', 'bbp-test-batch-two' ), bbp_admin_repair_tool_get_run_state( 'upgrade' )['tools'] );
		$this->assertStringNotContainsString( '&amp;', $settings['completeUrl'] );
		parse_str( wp_parse_url( $settings['completeUrl'], PHP_URL_QUERY ), $complete_query );
		$this->assertSame( 'run', $complete_query['action'] );
		$this->assertSame( array( 'bbp-test-batch-two' ), $complete_query['checked'] );
		$this->assertSame( $settings['run'], $complete_query['bbp-tools-run'] );
		$this->assertSame( 1, wp_verify_nonce( $complete_query['_wpnonce'], 'bbpress-do-counts' ) );

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
		$this->assertTrue( $response['data']['complete'] );
		$this->assertFalse( wp_cache_get( $cache_key, 'bbpress-tests' ) );
		$this->assertSame( array( 'bbp-test-batch-two' ), array_values( bbp_get_pending_upgrades() ) );
		$this->assertSame( array( 'bbp-test-batch-two' ), bbp_admin_repair_tool_get_run_state( 'upgrade' )['tools'] );

		bbp_admin()->tools         = array();
		$_GET                      = $complete_query;
		$_REQUEST                  = $_GET;
		$_SERVER['REQUEST_METHOD'] = 'GET';
		$this->register_legacy_tool();
		bbp_admin_repair_handler();

		$this->assertSame( array(), bbp_get_pending_upgrades() );
		$this->assertFalse( bbp_admin_repair_tool_get_run_state( 'upgrade' ) );
		$this->assertSame( array( 'one', 'one', 'one', 'two' ), $this->calls );
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

		$this->assertSame( array( 'one', 'one', 'one' ), $this->calls );
		$this->assertFalse( bbp_admin_repair_tool_get_run_state( 'upgrade' ) );

		$this->calls         = array();
		bbp_admin()->notices = array();
		bbp_admin_repair_handler();
		$second = bbp_admin_repair_tool_get_run_state( 'upgrade' );

		$this->assertNotSame( $first['id'], $second['id'] );
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
	public function test_incomplete_dry_run_never_starts_the_browser_runner() {
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
		$this->assertFalse( wp_script_is( 'bbp-admin-tools-js', 'enqueued' ) );
		$this->assertStringNotContainsString( 'bbp-tools-runner', end( bbp_admin()->notices ) );
		$this->assertSame( array( 'bbp-test-dry-run' ), bbp_get_pending_upgrades() );
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
				'callback'    => array( $this, 'run_first_tool' )
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
	 * Complete the second tool in one batch.
	 */
	public function run_second_tool() {
		$this->calls[] = 'two';

		return array( 0, 'Second tool complete.' );
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
		add_action( 'load-tools_page_bbp-upgrade', array( $this, 'register_legacy_tool' ), 5 );
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
	private function ajax( $type, $tool, $nonce, $method = 'POST', $run_id = '' ) {
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
			'action' => 'bbp_tools_process',
			'nonce'  => $nonce,
			'type'   => $type,
			'tool'   => $tool,
			'run'    => $run_id
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
	private function ajax_status( $type, $run_id, $status, $nonce ) {
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
			'action' => 'bbp_tools_run_status',
			'nonce'  => $nonce,
			'type'   => $type,
			'run'    => $run_id,
			'status' => $status
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
