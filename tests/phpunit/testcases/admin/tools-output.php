<?php

/**
 * Tests for escaped admin tool labels.
 *
 * @group tools
 */
class BBP_Tests_Admin_Tools_Output extends BBP_UnitTestCase {

	public function test_filtered_tool_name_preserves_safe_markup() {
		require_once BBP_PLUGIN_DIR . 'includes/admin/actions.php';

		$user_id = $this->factory->user->create( array( 'role' => 'administrator' ) );
		bbp_set_user_role( $user_id, bbp_get_keymaster_role() );
		$this->set_current_user( $user_id );

		$filter = function ( $tools ) {
			$tools[0]['name'] = 'Repair <span class="pending-count">7</span><script>alert(1)</script>';
			return $tools;
		};
		add_filter( 'bbp_tools_admin_tabs', $filter );

		$tabs = bbp_get_tools_admin_tabs();
		ob_start();
		bbp_admin_tools_box();
		$box = ob_get_clean();

		remove_filter( 'bbp_tools_admin_tabs', $filter );

		$this->assertStringContainsString( '<span class="pending-count">7</span>', $tabs );
		$this->assertStringContainsString( '<span class="pending-count">7</span>', $box );
		$this->assertStringNotContainsString( '<script>', $tabs );
		$this->assertStringNotContainsString( '<script>', $box );
	}

	public function test_repair_and_upgrade_run_labels_escape_tool_titles() {
		require_once BBP_PLUGIN_DIR . 'includes/admin/actions.php';
		require_once BBP_PLUGIN_DIR . 'includes/admin/tools/repair.php';
		require_once BBP_PLUGIN_DIR . 'includes/admin/tools/upgrade.php';

		$user_id = $this->factory->user->create( array( 'role' => 'administrator' ) );
		bbp_set_user_role( $user_id, bbp_get_keymaster_role() );
		$this->set_current_user( $user_id );

		$filter = function () {
			return array( array(
				'id'          => 'test-tool',
				'type'        => 'repair',
				'title'       => 'Title" onmouseover="alert(1)',
				'description' => '',
				'priority'    => 1,
				'callback'    => '__return_true',
				'overhead'    => 'low',
				'version'     => array(),
				'components'  => array(),
			) );
		};
		add_filter( 'bbp_repair_list', $filter );

		$old_get = $_GET;
		foreach ( array( 'bbp-repair' => 'bbp_admin_repair_page', 'bbp-upgrade' => 'bbp_admin_upgrade_page' ) as $slug => $page ) {
			$_GET['page'] = $slug;
			ob_start();
			$page();
			$output = ob_get_clean();

			$this->assertStringContainsString( 'aria-label="Run Title&quot; onmouseover=&quot;alert(1)"', $output );
			$this->assertStringNotContainsString( 'aria-label="Run Title" onmouseover="alert(1)"', $output );
		}
		$_GET = $old_get;

		remove_filter( 'bbp_repair_list', $filter );
	}
}
