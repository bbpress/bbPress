<?php

/**
 * Tests for admin tool helper functions.
 *
 * @group admin
 * @group tools
 */
class BBP_Tests_Admin_Tools_Helpers extends BBP_UnitTestCase {

	private $old_get;
	private $old_request;
	private $old_tools;
	private $old_user;
	private $old_pending;
	private $old_request_method;
	private $old_notices;

	public function setUp(): void {
		parent::setUp();

		require_once BBP_PLUGIN_DIR . 'includes/admin/actions.php';
		require_once BBP_PLUGIN_DIR . 'includes/admin/tools/common.php';
		require_once BBP_PLUGIN_DIR . 'includes/admin/tools.php';

		bbp_admin();

		$this->old_get     = $_GET;
		$this->old_request = $_REQUEST;
		$this->old_tools   = bbp_admin()->tools;
		$this->old_user    = get_current_user_id();
		$this->old_pending = get_option( '_bbp_db_pending_upgrades', null );
		$this->old_notices = bbp_admin()->notices;
		$this->old_request_method = isset( $_SERVER['REQUEST_METHOD'] )
			? $_SERVER['REQUEST_METHOD']
			: null;

		$user_id = $this->factory->user->create( array( 'role' => 'administrator' ) );
		bbp_set_user_role( $user_id, bbp_get_keymaster_role() );
		$this->set_current_user( $user_id );
	}

	public function tearDown(): void {
		$_GET              = $this->old_get;
		$_REQUEST          = $this->old_request;
		bbp_admin()->tools = $this->old_tools;
		bbp_admin()->notices = $this->old_notices;
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

		parent::tearDown();
	}

	/**
	 * @covers ::bbp_admin_repair_tool_run_url
	 * @covers ::bbp_get_admin_repair_tool_page
	 * @covers ::bbp_get_admin_repair_tool_page_id
	 * @covers ::bbp_get_admin_repair_tool_page_url
	 * @covers ::bbp_get_admin_repair_tool_run_url
	 */
	public function test_repair_tool_page_and_run_url_helpers() {
		$_GET['page'] = 'bbp-repair';

		$this->assertSame( 'bbp-repair', bbp_get_admin_repair_tool_page() );
		$this->assertSame( 'repair', bbp_get_admin_repair_tool_page_id() );
		$this->assertSame( admin_url( 'tools.php?page=bbp-repair' ), bbp_get_admin_repair_tool_page_url() );
		$this->assertSame( admin_url( 'tools.php?page=bbp-upgrade' ), bbp_get_admin_repair_tool_page_url( array( 'page' => 'bbp-upgrade' ) ) );

		$component  = array( 'id' => 'test-repair', 'type' => 'repair' );
		$repair_url = bbp_get_admin_repair_tool_run_url( $component );
		$query      = array();
		parse_str( html_entity_decode( wp_parse_url( $repair_url, PHP_URL_QUERY ) ), $query );

		$this->assertSame( 'bbp-repair', $query['page'] );
		$this->assertSame( 'run', $query['action'] );
		$this->assertSame( array( 'test-repair' ), $query['checked'] );
		$this->assertArrayHasKey( '_wpnonce', $query );

		$url   = bbp_get_admin_repair_tool_run_url( array( 'id' => 'test-upgrade', 'type' => 'upgrade' ) );
		$query = array();
		parse_str( html_entity_decode( wp_parse_url( $url, PHP_URL_QUERY ) ), $query );

		$this->assertSame( 'bbp-upgrade', $query['page'] );
		$this->assertSame( array( 'test-upgrade' ), $query['checked'] );

		$url   = bbp_get_admin_repair_tool_action_url( $component, 'dry-run' );
		$query = array();
		parse_str( html_entity_decode( wp_parse_url( $url, PHP_URL_QUERY ) ), $query );

		$this->assertSame( 'dry-run', $query['action'] );

		ob_start();
		bbp_admin_repair_tool_action_url( $component, 'dry-run' );
		$this->assertSame( esc_url( $url ), ob_get_clean() );

		ob_start();
		bbp_admin_repair_tool_run_url( $component );
		$this->assertSame( esc_url( $repair_url ), ob_get_clean() );

		$filter = static function ( $filtered_url ) {
			return add_query_arg( 'legacy-filter', 'applied', $filtered_url );
		};
		add_filter( 'bbp_get_admin_repair_tool_run_url', $filter );

		try {
			$filtered_url = bbp_get_admin_repair_tool_run_url( $component );
			$this->assertStringContainsString( 'legacy-filter=applied', $filtered_url );
			$this->assertSame( $filtered_url, bbp_get_admin_repair_tool_action_url( $component, 'run' ) );

			ob_start();
			bbp_admin_repair_tool_run_url( $component );
			$this->assertSame( esc_url( $filtered_url ), ob_get_clean() );
		} finally {
			remove_filter( 'bbp_get_admin_repair_tool_run_url', $filter );
		}

		$_GET['page'] = 'tools';
		$this->assertSame( '', bbp_get_admin_repair_tool_page_id() );
	}

	/**
	 * @covers ::bbp_get_admin_repair_tools
	 * @covers ::bbp_register_default_repair_tools
	 * @covers ::bbp_register_repair_tool
	 */
	public function test_repair_tool_registration_and_type_filtering() {
		bbp_admin()->tools = array();

		bbp_register_repair_tool( array( 'id' => 'incomplete' ) );
		$this->assertSame( array(), bbp_get_admin_repair_tools() );

		bbp_register_repair_tool(
			array(
				'id'          => 'custom-repair',
				'type'        => 'repair',
				'title'       => 'Custom repair',
				'description' => 'Description',
				'callback'    => '__return_true',
				'priority'    => 10,
				'overhead'    => 'medium',
				'version'     => '2.7',
				'components'  => array( 'forum' ),
			)
		);

		$tools = bbp_get_admin_repair_tools();
		$this->assertSame( array( 'custom-repair' ), array_keys( $tools ) );
		$this->assertSame( 'medium', $tools['custom-repair']['overhead'] );
		$this->assertSame( array( 'run' => '__return_true' ), $tools['custom-repair']['callbacks'] );
		$this->assertSame( array( 'custom-repair' ), array_keys( bbp_get_admin_repair_tools( 'repair' ) ) );
		$this->assertSame( array(), bbp_get_admin_repair_tools( 'upgrade' ) );

		bbp_register_repair_tool(
			array(
				'id'        => 'custom-dry-run',
				'type'      => 'repair',
				'title'     => 'Custom dry run',
				'priority'  => 11,
				'callback'  => '__return_false',
				'callbacks' => array(
					'run'     => '__return_true',
					'dry-run' => '__return_empty_array',
				),
			)
		);

		$this->assertSame( '__return_true', bbp_admin()->tools['custom-dry-run']['callback'] );
		$this->assertSame(
			array(
				'run'     => '__return_true',
				'dry-run' => '__return_empty_array',
			),
			bbp_admin()->tools['custom-dry-run']['callbacks']
		);

		bbp_register_default_repair_tools();
		$this->assertArrayHasKey( 'bbp-sync-topic-meta', bbp_admin()->tools );
		$this->assertArrayHasKey( 'bbp-user-favorites-move', bbp_admin()->tools );
		$this->assertSame( 'repair', bbp_admin()->tools['bbp-sync-topic-meta']['type'] );
		$this->assertSame( 'upgrade', bbp_admin()->tools['bbp-user-favorites-move']['type'] );
		$this->assertSame( 'bbp_admin_repair_sticky', bbp_admin()->tools['bbp-sync-all-topics-sticky']['callbacks']['run'] );
		$this->assertSame( 'bbp_admin_repair_sticky_dry_run', bbp_admin()->tools['bbp-sync-all-topics-sticky']['callbacks']['dry-run'] );
		$this->assertSame( 'bbp_admin_repair_reply_menu_order', bbp_admin()->tools['bbp-sync-all-reply-positions']['callbacks']['run'] );
		$this->assertSame( 'bbp_admin_repair_reply_menu_order_dry_run', bbp_admin()->tools['bbp-sync-all-reply-positions']['callbacks']['dry-run'] );
		$this->assertSame( 'bbp_admin_repair_topic_voice_count', bbp_admin()->tools['bbp-topic-engagements']['callbacks']['run'] );
		$this->assertSame( 'bbp_admin_repair_topic_voice_count_dry_run', bbp_admin()->tools['bbp-topic-engagements']['callbacks']['dry-run'] );
		$this->assertSame( 'bbp_admin_repair_topic_reply_count', bbp_admin()->tools['bbp-topic-replies']['callbacks']['run'] );
		$this->assertSame( 'bbp_admin_repair_topic_reply_count_dry_run', bbp_admin()->tools['bbp-topic-replies']['callbacks']['dry-run'] );
		$this->assertSame( 'bbp_admin_repair_topic_hidden_reply_count', bbp_admin()->tools['bbp-topic-hidden-replies']['callbacks']['run'] );
		$this->assertSame( 'bbp_admin_repair_topic_hidden_reply_count_dry_run', bbp_admin()->tools['bbp-topic-hidden-replies']['callbacks']['dry-run'] );
		$this->assertSame( 'bbp_admin_repair_user_topic_count', bbp_admin()->tools['bbp-user-topics']['callbacks']['run'] );
		$this->assertSame( 'bbp_admin_repair_user_topic_count_dry_run', bbp_admin()->tools['bbp-user-topics']['callbacks']['dry-run'] );
		$this->assertSame( 'bbp_admin_repair_user_reply_count', bbp_admin()->tools['bbp-user-replies']['callbacks']['run'] );
		$this->assertSame( 'bbp_admin_repair_user_reply_count_dry_run', bbp_admin()->tools['bbp-user-replies']['callbacks']['dry-run'] );
		$this->assertSame( 'bbp_admin_repair_user_favorites', bbp_admin()->tools['bbp-user-favorites']['callbacks']['run'] );
		$this->assertSame( 'bbp_admin_repair_user_favorites_dry_run', bbp_admin()->tools['bbp-user-favorites']['callbacks']['dry-run'] );
		$this->assertSame( 'bbp_admin_repair_user_topic_subscriptions', bbp_admin()->tools['bbp-user-topic-subscriptions']['callbacks']['run'] );
		$this->assertSame( 'bbp_admin_repair_user_topic_subscriptions_dry_run', bbp_admin()->tools['bbp-user-topic-subscriptions']['callbacks']['dry-run'] );
		$this->assertSame( 'bbp_admin_repair_user_forum_subscriptions', bbp_admin()->tools['bbp-user-forum-subscriptions']['callbacks']['run'] );
		$this->assertSame( 'bbp_admin_repair_user_forum_subscriptions_dry_run', bbp_admin()->tools['bbp-user-forum-subscriptions']['callbacks']['dry-run'] );
		$this->assertSame( 'bbp_admin_repair_user_roles', bbp_admin()->tools['bbp-user-role-map']['callbacks']['run'] );
		$this->assertSame( 'bbp_admin_repair_user_roles_dry_run', bbp_admin()->tools['bbp-user-role-map']['callbacks']['dry-run'] );
		$this->assertSame( 'bbp_admin_upgrade_user_engagements', bbp_admin()->tools['bbp-user-topic-engagements-move']['callbacks']['run'] );
		$this->assertSame( 'bbp_admin_upgrade_user_engagements_dry_run', bbp_admin()->tools['bbp-user-topic-engagements-move']['callbacks']['dry-run'] );
		$this->assertSame( 'bbp_admin_upgrade_user_favorites', bbp_admin()->tools['bbp-user-favorites-move']['callbacks']['run'] );
		$this->assertSame( 'bbp_admin_upgrade_user_favorites_dry_run', bbp_admin()->tools['bbp-user-favorites-move']['callbacks']['dry-run'] );
		$this->assertSame( 'bbp_admin_upgrade_user_topic_subscriptions', bbp_admin()->tools['bbp-user-topic-subscriptions-move']['callbacks']['run'] );
		$this->assertSame( 'bbp_admin_upgrade_user_topic_subscriptions_dry_run', bbp_admin()->tools['bbp-user-topic-subscriptions-move']['callbacks']['dry-run'] );
		$this->assertSame( 'bbp_admin_repair_closed_topics', bbp_admin()->tools['bbp-sync-closed-topics']['callbacks']['run'] );
		$this->assertSame( 'bbp_admin_repair_closed_topics_dry_run', bbp_admin()->tools['bbp-sync-closed-topics']['callbacks']['dry-run'] );
	}

	/**
	 * @covers ::bbp_admin_repair_tool_action_label
	 * @covers ::bbp_get_admin_repair_tool_actions
	 * @covers ::bbp_get_admin_repair_tool_callback
	 * @covers ::bbp_get_admin_repair_tool_callbacks
	 */
	public function test_repair_tool_action_helpers() {
		$legacy = $this->tool();
		$dry_run = $this->tool(
			array(
				'callbacks' => array(
					'run'     => '__return_true',
					'dry-run' => '__return_empty_array',
				),
			)
		);

		$this->assertSame( array( 'run' => '__return_true' ), bbp_get_admin_repair_tool_callbacks( $legacy ) );
		$this->assertSame( '__return_empty_array', bbp_get_admin_repair_tool_callback( $dry_run, 'dry-run' ) );
		$this->assertFalse( bbp_get_admin_repair_tool_callback( $legacy, 'dry-run' ) );
		$this->assertSame( array( 'run', 'dry-run' ), bbp_get_admin_repair_tool_actions( array( $legacy, $dry_run ) ) );
		$this->assertSame( 'Run', bbp_admin_repair_tool_action_label( 'run' ) );
		$this->assertSame( 'Dry Run', bbp_admin_repair_tool_action_label( 'dry-run' ) );
		$this->assertSame( 'Custom Action', bbp_admin_repair_tool_action_label( 'custom-action' ) );

		$legacy_override                         = $dry_run;
		$legacy_override['_registered_callback'] = '__return_true';
		$legacy_override['callback']             = '__return_false';
		$this->assertSame( array( 'run' => '__return_false' ), bbp_get_admin_repair_tool_callbacks( $legacy_override ) );
		$this->assertFalse( bbp_get_admin_repair_tool_callback( $legacy_override, 'dry-run' ) );

		$callbacks_override                                = $dry_run;
		$callbacks_override['_registered_callback']        = '__return_true';
		$callbacks_override['callbacks']['run']             = '__return_false';
		$this->assertSame( '__return_false', bbp_get_admin_repair_tool_callback( $callbacks_override, 'run' ) );
		$this->assertSame( '__return_empty_array', bbp_get_admin_repair_tool_callback( $callbacks_override, 'dry-run' ) );
	}

	/**
	 * @covers ::bbp_admin_repair_handler
	 */
	public function test_repair_handler_routes_dry_run_without_running_or_clearing_pending_upgrade() {
		$run_called = false;
		$context    = array();
		$run        = function () use ( &$run_called ) {
			$run_called = true;
			return array( 0, 'Run complete.' );
		};
		$dry_run    = function ( $received ) use ( &$context ) {
			$context = $received;
			return array( 0, 'Dry run complete.' );
		};

		bbp_admin()->tools = array();
		bbp_register_repair_tool(
			array(
				'id'        => 'custom-dry-run',
				'type'      => 'repair',
				'title'     => 'Custom dry run',
				'priority'  => 10,
				'callbacks' => array(
					'run'     => $run,
					'dry-run' => $dry_run,
				),
			)
		);

		update_option( '_bbp_db_pending_upgrades', array( 'custom-dry-run' ) );
		$_GET = array(
			'action'   => 'dry-run',
			'checked'  => array( 'custom-dry-run' ),
			'_wpnonce' => wp_create_nonce( 'bbpress-do-counts' ),
		);
		$_REQUEST = $_GET;
		$_SERVER['REQUEST_METHOD'] = 'GET';

		bbp_admin_repair_handler();

		$this->assertFalse( $run_called );
		$this->assertSame( 'dry-run', $context['action'] );
		$this->assertSame( 'custom-dry-run', $context['tool']['id'] );
		$this->assertSame( array( 'custom-dry-run' ), bbp_get_pending_upgrades() );
	}

	/**
	 * @covers ::bbp_admin_repair_handler
	 */
	public function test_repair_handler_preserves_pending_upgrade_for_failed_run() {
		bbp_admin()->tools = array();
		bbp_register_repair_tool(
			array(
				'id'        => 'custom-run',
				'type'      => 'upgrade',
				'title'     => 'Custom run',
				'priority'  => 10,
				'callbacks' => array(
					'run' => function () {
						return array( 2, 'Run failed.', 'success' => false );
					},
				),
			)
		);

		update_option( '_bbp_db_pending_upgrades', array( 'custom-run' ) );
		$_GET = array(
			'action'   => 'run',
			'checked'  => array( 'custom-run' ),
			'_wpnonce' => wp_create_nonce( 'bbpress-do-counts' ),
		);
		$_REQUEST = $_GET;
		$_SERVER['REQUEST_METHOD'] = 'GET';

		bbp_admin_repair_handler();

		$this->assertSame( array( 'custom-run' ), bbp_get_pending_upgrades() );
		$this->assertStringContainsString( 'Run failed.', end( bbp_admin()->notices ) );
	}

	/**
	 * @covers ::bbp_admin_repair_closed_topics_dry_run
	 * @covers ::bbp_admin_repair_handler
	 */
	public function test_repair_handler_runs_the_registered_closed_topics_dry_run_callback() {
		require_once BBP_PLUGIN_DIR . 'includes/admin/tools/repair.php';

		$topic_id = $this->factory->topic->create( array( 'post_status' => bbp_get_closed_status_id() ) );
		delete_post_meta( $topic_id, '_bbp_status' );

		bbp_admin()->tools = array();
		bbp_register_default_repair_tools();
		$_GET = array(
			'action'   => 'dry-run',
			'checked'  => array( 'bbp-sync-closed-topics' ),
			'_wpnonce' => wp_create_nonce( 'bbpress-do-counts' ),
		);
		$_REQUEST = $_GET;
		$_SERVER['REQUEST_METHOD'] = 'GET';

		bbp_admin_repair_handler();

		$this->assertSame( '', get_post_meta( $topic_id, '_bbp_status', true ) );
		$this->assertStringContainsString( '1 closed topic would be repaired', end( bbp_admin()->notices ) );
	}

	/**
	 * @covers ::bbp_admin_repair_handler
	 */
	public function test_repair_handler_uses_the_action_for_the_clicked_bulk_button() {
		$run_called = false;
		$context    = array();

		bbp_admin()->tools = array();
		bbp_register_repair_tool(
			array(
				'id'        => 'custom-dry-run',
				'type'      => 'repair',
				'title'     => 'Custom dry run',
				'priority'  => 10,
				'callbacks' => array(
					'run'     => function () use ( &$run_called ) {
						$run_called = true;
						return array( 0, 'Run complete.' );
					},
					'dry-run' => function ( $received ) use ( &$context ) {
						$context = $received;
						return array( 0, 'Dry run complete.' );
					},
				),
			)
		);

		update_option( '_bbp_db_pending_upgrades', array( 'custom-dry-run' ) );
		$_GET = array(
			'action'    => 'run',
			'action2'   => 'dry-run',
			'doaction2' => 'Apply',
			'checked'   => array( 'custom-dry-run' ),
			'_wpnonce'  => wp_create_nonce( 'bbpress-do-counts' ),
		);
		$_REQUEST = $_GET;
		$_SERVER['REQUEST_METHOD'] = 'GET';

		bbp_admin_repair_handler();

		$this->assertFalse( $run_called );
		$this->assertSame( 'dry-run', $context['action'] );
		$this->assertSame( array( 'custom-dry-run' ), bbp_get_pending_upgrades() );
	}

	/**
	 * @covers ::bbp_admin_repair_handler
	 */
	public function test_repair_handler_preserves_legacy_callback_overrides() {
		$original_run = false;
		$dry_run      = false;
		$override_run = false;
		$received_args = null;

		bbp_admin()->tools = array();
		bbp_register_repair_tool(
			array(
				'id'        => 'custom-dry-run',
				'type'      => 'repair',
				'title'     => 'Custom dry run',
				'priority'  => 10,
				'callbacks' => array(
					'run'     => function () use ( &$original_run ) {
						$original_run = true;
						return array( 0, 'Run complete.' );
					},
					'dry-run' => function () use ( &$dry_run ) {
						$dry_run = true;
						return array( 0, 'Dry run complete.' );
					},
				),
			)
		);

		$override = function ( $tools ) use ( &$override_run, &$received_args ) {
			$tools['custom-dry-run']['callback'] = function () use ( &$override_run, &$received_args ) {
				$override_run = true;
				$received_args = func_num_args();
				return array( 0, 'Overridden run complete.' );
			};
			return $tools;
		};
		add_filter( 'bbp_get_admin_repair_tools', $override );

		update_option( '_bbp_db_pending_upgrades', array( 'custom-dry-run' ) );
		$_GET = array(
			'action'   => 'run',
			'checked'  => array( 'custom-dry-run' ),
			'_wpnonce' => wp_create_nonce( 'bbpress-do-counts' ),
		);
		$_REQUEST = $_GET;
		$_SERVER['REQUEST_METHOD'] = 'GET';

		try {
			bbp_admin_repair_handler();
		} finally {
			remove_filter( 'bbp_get_admin_repair_tools', $override );
		}

		$this->assertTrue( $override_run );
		$this->assertSame( 0, $received_args );
		$this->assertFalse( $original_run );
		$this->assertFalse( $dry_run );
		$this->assertSame( array(), bbp_get_pending_upgrades() );
	}

	/**
	 * @covers ::bbp_admin_repair_handler
	 */
	public function test_repair_handler_preserves_action_keyed_callback_overrides() {
		$original_run = false;
		$override_run = false;
		$dry_run      = function () {
			return array( 0, 'Dry run complete.' );
		};

		bbp_admin()->tools = array();
		bbp_register_repair_tool(
			array(
				'id'        => 'custom-dry-run',
				'type'      => 'repair',
				'title'     => 'Custom dry run',
				'priority'  => 10,
				'callbacks' => array(
					'run'     => function () use ( &$original_run ) {
						$original_run = true;
						return array( 0, 'Run complete.' );
					},
					'dry-run' => $dry_run,
				),
			)
		);

		$override = function ( $tools ) use ( &$override_run ) {
			$tools['custom-dry-run']['callbacks']['run'] = function () use ( &$override_run ) {
				$override_run = true;
				return array( 0, 'Overridden run complete.' );
			};
			return $tools;
		};
		add_filter( 'bbp_get_admin_repair_tools', $override );

		update_option( '_bbp_db_pending_upgrades', array( 'custom-dry-run' ) );
		$_GET = array(
			'action'   => 'run',
			'checked'  => array( 'custom-dry-run' ),
			'_wpnonce' => wp_create_nonce( 'bbpress-do-counts' ),
		);
		$_REQUEST = $_GET;
		$_SERVER['REQUEST_METHOD'] = 'GET';

		try {
			$tools     = bbp_get_admin_repair_tools();
			$callbacks = bbp_get_admin_repair_tool_callbacks( $tools['custom-dry-run'] );
			$this->assertSame( $dry_run, $callbacks['dry-run'] );
			bbp_admin_repair_handler();
		} finally {
			remove_filter( 'bbp_get_admin_repair_tools', $override );
		}

		$this->assertTrue( $override_run );
		$this->assertFalse( $original_run );
		$this->assertSame( array(), bbp_get_pending_upgrades() );
	}

	/**
	 * @covers ::bbp_admin_repair_handler
	 */
	public function test_repair_handler_ignores_filter_and_search_submissions() {
		$run_called = false;
		bbp_admin()->tools = array(
			'custom-run' => $this->tool(
				array(
					'callback' => function () use ( &$run_called ) {
						$run_called = true;
						return array( 0, 'Run complete.' );
					},
				)
			),
		);
		$_SERVER['REQUEST_METHOD'] = 'GET';

		foreach ( array( 'filter_action', 'search_action' ) as $submit ) {
			$_GET = array(
				'action'           => 'run',
				'checked'          => array( 'custom-run' ),
				$submit            => 'Submit',
			);
			$_REQUEST = $_GET;
			bbp_admin_repair_handler();
		}

		$this->assertFalse( $run_called );
	}

	/**
	 * @covers ::bbp_admin_repair_handler
	 */
	public function test_repair_handler_rejects_an_unsupported_dry_run() {
		$run_called = false;
		bbp_admin()->tools = array(
			'custom-run' => $this->tool(
				array(
					'callback' => function () use ( &$run_called ) {
						$run_called = true;
						return array( 0, 'Run complete.' );
					},
				)
			),
		);
		update_option( '_bbp_db_pending_upgrades', array( 'custom-run' ) );
		$_GET = array(
			'action'   => 'dry-run',
			'checked'  => array( 'custom-run' ),
			'_wpnonce' => wp_create_nonce( 'bbpress-do-counts' ),
		);
		$_REQUEST = $_GET;
		$_SERVER['REQUEST_METHOD'] = 'GET';

		bbp_admin_repair_handler();

		$this->assertFalse( $run_called );
		$this->assertSame( array( 'custom-run' ), bbp_get_pending_upgrades() );
		$this->assertStringContainsString( 'does not support the Dry Run action', end( bbp_admin()->notices ) );
	}

	/**
	 * @covers ::bbp_admin_repair_handler
	 */
	public function test_repair_handler_reports_an_invalid_callback_result() {
		bbp_admin()->tools = array();
		bbp_register_repair_tool(
			array(
				'id'        => 'invalid-result',
				'type'      => 'repair',
				'title'     => 'Invalid result',
				'priority'  => 10,
				'callbacks' => array(
					'run'     => '__return_empty_array',
					'dry-run' => '__return_empty_array',
				),
			)
		);
		$_GET = array(
			'action'   => 'dry-run',
			'checked'  => array( 'invalid-result' ),
			'_wpnonce' => wp_create_nonce( 'bbpress-do-counts' ),
		);
		$_REQUEST = $_GET;
		$_SERVER['REQUEST_METHOD'] = 'GET';

		bbp_admin_repair_handler();

		$this->assertStringContainsString( 'did not return a valid result for the Dry Run action', end( bbp_admin()->notices ) );
	}

	/**
	 * @covers ::bbp_admin_repair_list_components_filter
	 * @covers ::bbp_admin_repair_list_search_form
	 * @covers ::bbp_admin_repair_list_versions_filter
	 * @covers ::bbp_get_admin_repair_tool_registered_components
	 * @covers ::bbp_get_admin_repair_tool_registered_versions
	 */
	public function test_registered_metadata_and_list_controls() {
		$_GET['page'] = 'bbp-repair';
		bbp_admin()->tools = array(
			'first'        => $this->tool( array( 'components' => array( 'forum', 'topic' ), 'version' => '2.6' ) ),
			'second'       => $this->tool( array( 'components' => array( 'topic', 'reply' ), 'version' => array( '2.6', '2.7' ) ) ),
			'upgrade-only' => $this->tool( array( 'type' => 'upgrade', 'components' => array( 'upgrade-only' ), 'version' => '9.9' ) ),
		);

		$this->assertSame( array( 'forum', 'topic', 'reply' ), bbp_get_admin_repair_tool_registered_components() );
		$this->assertSame( array( '2.6', '2.7' ), bbp_get_admin_repair_tool_registered_versions() );
		$this->assertNotContains( 'upgrade-only', bbp_get_admin_repair_tool_registered_components() );
		$this->assertNotContains( '9.9', bbp_get_admin_repair_tool_registered_versions() );

		$_GET['components'] = 'topic';
		ob_start();
		bbp_admin_repair_list_components_filter();
		$components = ob_get_clean();
		$this->assertStringContainsString( 'name="components"', $components );
		$this->assertMatchesRegularExpression( '/value="topic"\s+selected=/', $components );

		$_GET['version'] = '2.7';
		ob_start();
		bbp_admin_repair_list_versions_filter();
		$versions = ob_get_clean();
		$this->assertStringContainsString( 'name="version"', $versions );
		$this->assertMatchesRegularExpression( '/value="2\.7"\s+selected=/', $versions );

		$_REQUEST['s'] = 'voices';
		ob_start();
		bbp_admin_repair_list_search_form();
		$search = ob_get_clean();
		$this->assertStringContainsString( 'name="s" value="voices"', $search );
	}

	/**
	 * @covers ::bbp_admin_repair_tool_translate_component
	 * @covers ::bbp_admin_repair_tool_translate_overhead
	 * @covers ::bbp_admin_repair_tool_translate_version
	 * @covers ::bbp_get_admin_repair_tool_components
	 * @covers ::bbp_get_admin_repair_tool_overhead
	 * @covers ::bbp_get_admin_repair_tool_version
	 */
	public function test_tool_labels_and_filter_links() {
		$_GET['page'] = 'bbp-repair';

		$this->assertSame( 'Low', bbp_admin_repair_tool_translate_overhead( 'low' ) );
		$this->assertSame( 'Custom Level', bbp_admin_repair_tool_translate_overhead( 'custom level' ) );
		$this->assertSame( 'Forums', bbp_admin_repair_tool_translate_component( bbp_get_forum_post_type() ) );
		$this->assertSame( 'Custom Component', bbp_admin_repair_tool_translate_component( 'custom component' ) );
		$this->assertSame( '2.6.0', bbp_admin_repair_tool_translate_version( '2.6' ) );
		$this->assertSame( '2.7', bbp_admin_repair_tool_translate_version( '2.7' ) );

		$links = bbp_get_admin_repair_tool_components( array( 'components' => array( bbp_get_forum_post_type() ) ) );
		$this->assertStringContainsString( 'components=forum', $links[0] );
		$this->assertStringContainsString( '>Forums</a>', $links[0] );
		$this->assertSame( array( '&mdash;' ), bbp_get_admin_repair_tool_components() );

		$links = bbp_get_admin_repair_tool_version( array( 'version' => '2.6' ) );
		$this->assertStringContainsString( 'version=2.6', $links[0] );
		$this->assertStringContainsString( '>2.6.0</a>', $links[0] );
		$this->assertSame( array( '&mdash;' ), bbp_get_admin_repair_tool_version() );

		$links = bbp_get_admin_repair_tool_overhead( array( 'overhead' => 'high' ) );
		$this->assertStringContainsString( 'overhead=high', $links[0] );
		$this->assertStringContainsString( '>High</a>', $links[0] );
		$this->assertSame( array( '&mdash;' ), bbp_get_admin_repair_tool_overhead() );
	}

	/**
	 * @covers ::bbp_admin_repair_tool_overhead_filters
	 * @covers ::bbp_admin_repair_tool_status_filters
	 * @covers ::bbp_get_admin_repair_tool_overhead_filters
	 * @covers ::bbp_get_admin_repair_tool_status_filters
	 */
	public function test_overhead_and_status_filter_links() {
		$_GET['page'] = 'bbp-repair';
		bbp_admin()->tools = array(
			'low-one'     => $this->tool( array( 'overhead' => 'low' ) ),
			'high-one'    => $this->tool( array( 'overhead' => 'high' ) ),
			'high-two'    => $this->tool( array( 'overhead' => 'high' ) ),
			'upgrade-one' => $this->tool( array( 'type' => 'upgrade', 'overhead' => 'medium' ) ),
		);
		update_option( '_bbp_db_pending_upgrades', array( 'high-one', 'upgrade-one' ) );

		$_GET['overhead'] = 'high';
		$overhead = bbp_get_admin_repair_tool_overhead_filters( array( 'separator' => ' / ' ) );
		$this->assertStringContainsString( '<span class="count">(3)</span>', $overhead );
		$this->assertStringContainsString( 'overhead=high', $overhead );
		$this->assertStringContainsString( 'class="current">High <span class="count">(2)</span>', $overhead );
		$this->assertStringNotContainsString( 'overhead=medium', $overhead );
		$this->assertStringContainsString( ' / ', $overhead );

		ob_start();
		bbp_admin_repair_tool_overhead_filters( array( 'separator' => ' / ' ) );
		$this->assertSame( $overhead, ob_get_clean() );

		unset( $_GET['overhead'] );
		$_GET['status'] = 'pending';
		$status = bbp_get_admin_repair_tool_status_filters();
		$this->assertStringContainsString( '<span class="count">(3)</span>', $status );
		$this->assertStringContainsString( 'status=pending', $status );
		$this->assertStringContainsString( 'class="current">Pending', $status );
		$this->assertStringContainsString( '<span class="count">(1)</span>', $status );

		ob_start();
		bbp_admin_repair_tool_status_filters();
		$this->assertSame( $status, ob_get_clean() );
	}

	/**
	 * @covers ::bbp_admin_tools_box
	 * @covers ::bbp_get_tools_admin_pages
	 * @covers ::bbp_get_tools_admin_tabs
	 * @covers ::bbp_tools_admin_tabs
	 */
	public function test_tools_pages_tabs_and_box_respect_capabilities() {
		$pages = bbp_get_tools_admin_pages();
		$this->assertSame( array( 'bbp-repair', 'bbp-upgrade', 'bbp-converter', 'bbp-reset' ), wp_list_pluck( $pages, 'page' ) );

		$tabs = bbp_get_tools_admin_tabs( 'bbp-repair' );
		$this->assertMatchesRegularExpression( '/page=bbp-repair" class="nav-tab nav-tab-active"/', $tabs );
		$this->assertMatchesRegularExpression( '/page=bbp-upgrade" class="nav-tab"/', $tabs );

		ob_start();
		bbp_tools_admin_tabs( 'bbp-repair' );
		$this->assertSame( $tabs, ob_get_clean() );

		ob_start();
		bbp_admin_tools_box();
		$box = ob_get_clean();
		$this->assertStringContainsString( '<div class="card">', $box );
		$this->assertStringContainsString( 'page=bbp-repair', $box );

		$this->set_current_user( 0 );
		$this->assertSame( '', bbp_get_tools_admin_tabs( 'bbp-repair' ) );
		ob_start();
		bbp_admin_tools_box();
		$this->assertSame( '', ob_get_clean() );
	}

	private function tool( $args = array() ) {
		return wp_parse_args(
			$args,
			array(
				'type'        => 'repair',
				'title'       => 'Test tool',
				'description' => 'Test description',
				'priority'    => 10,
				'callback'    => '__return_true',
				'overhead'    => 'low',
				'version'     => array(),
				'components'  => array(),
			)
		);
	}
}
