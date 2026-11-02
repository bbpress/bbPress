<?php

/**
 * Tests for pending upgrades in the admin tools list.
 *
 * @group admin
 * @group tools
 */
class BBP_Tests_Admin_Upgrade_List extends BBP_UnitTestCase {

	private $old_get;
	private $old_tools;
	private $old_user;
	private $old_pending;

	public function setUp(): void {
		parent::setUp();

		if ( ! function_exists( 'bbp_admin' ) ) {
			require_once BBP_PLUGIN_DIR . 'includes/admin/actions.php';
		}
		bbp_admin();
		if ( ! function_exists( 'bbp_admin_upgrade_page' ) ) {
			require_once BBP_PLUGIN_DIR . 'includes/admin/tools/upgrade.php';
		}

		$this->old_get     = $_GET;
		$this->old_tools   = bbp_admin()->tools;
		$this->old_user    = get_current_user_id();
		$this->old_pending = get_option( '_bbp_db_pending_upgrades', null );

		$_GET = array( 'page' => 'bbp-upgrade' );
		bbp_admin()->tools = array();

		foreach ( array( 10, 20, 30, 40 ) as $priority ) {
			bbp_register_repair_tool(
				array(
					'id'       => 'upgrade-' . $priority,
					'type'     => 'upgrade',
					'title'    => 'Upgrade ' . $priority,
					'priority' => $priority,
					'version'  => ( 40 === $priority ) ? '2.7.0' : '2.6.0',
					'callback' => '__return_true'
				)
			);
		}

		update_option( '_bbp_db_pending_upgrades', array( 'upgrade-40', 'upgrade-20' ) );

		$user_id = $this->factory->user->create( array( 'role' => 'administrator' ) );
		bbp_set_user_role( $user_id, bbp_get_keymaster_role() );
		$this->set_current_user( $user_id );
	}

	public function tearDown(): void {
		$_GET             = $this->old_get;
		bbp_admin()->tools = $this->old_tools;
		$this->set_current_user( $this->old_user );

		if ( null === $this->old_pending ) {
			delete_option( '_bbp_db_pending_upgrades' );
		} else {
			update_option( '_bbp_db_pending_upgrades', $this->old_pending );
		}

		parent::tearDown();
	}

	/**
	 * @covers ::bbp_admin_repair_list
	 * @dataProvider list_orders
	 */
	public function test_pending_order_preserves_priorities_and_explicit_sorting( $query, $expected ) {
		$_GET = array_merge( $_GET, $query );
		$list = bbp_admin_repair_list( 'upgrade' );

		$this->assertSame( $expected, array_keys( $list ) );
		$this->assertTrue( $list[20]['pending'] );
		$this->assertTrue( $list[40]['pending'] );
	}

	public static function list_orders() {
		return array(
			'default'           => array( array(), array( 20, 40, 10, 30 ) ),
			'pending filter'    => array( array( 'status' => 'pending' ), array( 20, 40 ) ),
			'explicit priority' => array( array( 'orderby' => 'priority' ), array( 10, 20, 30, 40 ) ),
			'explicit order'    => array( array( 'order' => 'desc' ), array( 40, 30, 20, 10 ) ),
			'explicit version'  => array( array( 'orderby' => 'version', 'order' => 'asc' ), array( 10, 20, 30, 40 ) )
		);
	}

	/**
	 * @covers ::bbp_admin_repair_list
	 */
	public function test_no_pending_upgrades_retains_priority_order() {
		update_option( '_bbp_db_pending_upgrades', array() );
		$list = bbp_admin_repair_list( 'upgrade' );

		$this->assertSame( array( 10, 20, 30, 40 ), array_keys( $list ) );
		$this->assertSame( array( false ), array_values( array_unique( wp_list_pluck( $list, 'pending' ) ) ) );
	}

	/**
	 * @covers ::bbp_admin_repair_list
	 */
	public function test_repair_tools_keep_their_existing_order() {
		$tools = bbp_admin()->tools;
		foreach ( $tools as &$tool ) {
			$tool['type'] = 'repair';
		}
		unset( $tool );
		bbp_admin()->tools = $tools;

		$this->assertSame( array( 10, 20, 30, 40 ), array_keys( bbp_admin_repair_list( 'repair' ) ) );
	}

	/**
	 * @covers ::bbp_admin_upgrade_page
	 */
	public function test_pending_rows_have_visible_labels_and_clear_when_completed() {
		ob_start();
		bbp_admin_upgrade_page();
		$html = ob_get_clean();

		$this->assertSame( 2, substr_count( $html, 'class="bbp-repair-tools inactive status-pending"' ) );
		$this->assertSame( 2, substr_count( $html, '<span class="post-state">&mdash; Pending</span>' ) );
		$this->assertStringContainsString( 'bbp-upgrade-tools', $html );

		update_option( '_bbp_db_pending_upgrades', array() );
		ob_start();
		bbp_admin_upgrade_page();
		$html = ob_get_clean();

		$this->assertStringNotContainsString( 'status-pending', $html );
		$this->assertStringNotContainsString( 'class="post-state"', $html );
	}
}
