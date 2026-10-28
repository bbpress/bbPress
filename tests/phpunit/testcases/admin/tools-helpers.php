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

		$user_id = $this->factory->user->create( array( 'role' => 'administrator' ) );
		bbp_set_user_role( $user_id, bbp_get_keymaster_role() );
		$this->set_current_user( $user_id );
	}

	public function tearDown(): void {
		$_GET              = $this->old_get;
		$_REQUEST          = $this->old_request;
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

		ob_start();
		bbp_admin_repair_tool_run_url( $component );
		$this->assertSame( esc_url( $repair_url ), ob_get_clean() );

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
		$this->assertSame( array( 'custom-repair' ), array_keys( bbp_get_admin_repair_tools( 'repair' ) ) );
		$this->assertSame( array(), bbp_get_admin_repair_tools( 'upgrade' ) );

		bbp_register_default_repair_tools();
		$this->assertArrayHasKey( 'bbp-sync-topic-meta', bbp_admin()->tools );
		$this->assertArrayHasKey( 'bbp-user-favorites-move', bbp_admin()->tools );
		$this->assertSame( 'repair', bbp_admin()->tools['bbp-sync-topic-meta']['type'] );
		$this->assertSame( 'upgrade', bbp_admin()->tools['bbp-user-favorites-move']['type'] );
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
