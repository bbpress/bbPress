<?php

/**
 * Reset permissions and multisite user data isolation.
 *
 * @group tools
 * @group multisite
 */
class BBP_Tests_Admin_Reset_Security extends BBP_UnitTestCase {

	/**
	 * @covers ::BBP_Admin::map_settings_meta_caps
	 */
	public function test_site_keymaster_can_access_reset_and_import_on_multisite() {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'Requires multisite.' );
		}
		require_once BBP_PLUGIN_DIR . 'includes/admin/tools/reset.php';
		require_once BBP_PLUGIN_DIR . 'includes/admin/tools/common.php';
		require_once BBP_PLUGIN_DIR . 'includes/admin/actions.php';

		$user_id = $this->factory->user->create( array( 'role' => 'administrator' ) );
		bbp_set_user_role( $user_id, bbp_get_keymaster_role() );
		$this->set_current_user( $user_id );
		$this->assertTrue( bbp_is_user_keymaster() );
		$this->assertFalse( is_super_admin() );
		$this->assertTrue( current_user_can( 'bbp_tools_reset_page' ) );
		$this->assertTrue( current_user_can( 'bbp_tools_import_page' ) );
		$this->assertFalse( current_user_can( 'bbp_tools_import_users' ) );
		$user = get_userdata( $user_id );
		$user->add_cap( 'bbp_tools_import_users' );
		$this->set_current_user( 0 );
		$this->set_current_user( $user_id );
		$this->assertTrue( current_user_can( 'bbp_tools_import_users' ) );
		$user->remove_cap( 'bbp_tools_import_users' );
		$this->set_current_user( 0 );
		$this->set_current_user( $user_id );
		$this->assertFalse( current_user_can( 'bbp_tools_import_users' ) );

		grant_super_admin( $user_id );
		try {
			$this->assertTrue( current_user_can( 'bbp_tools_import_users' ) );
		} finally {
			revoke_super_admin( $user_id );
		}
	}

	/**
	 * @covers ::bbp_admin_reset_database
	 */
	public function test_multisite_reset_preserves_other_blog_and_global_user_data() {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'Requires multisite.' );
		}
		require_once BBP_PLUGIN_DIR . 'includes/admin/tools/reset.php';
		require_once BBP_PLUGIN_DIR . 'includes/admin/tools/common.php';
		require_once BBP_PLUGIN_DIR . 'includes/admin/actions.php';

		$user_id = $this->factory->user->create();
		remove_action( 'wpmu_new_blog', 'bbp_new_site' );
		try {
			$other_blog = $this->factory->blog->create();
		} finally {
			add_action( 'wpmu_new_blog', 'bbp_new_site', 10, 6 );
		}
		update_user_option( $user_id, '_bbp_topic_count', 11, false );
		update_user_meta( $user_id, '_bbp_old_user_id', 42 );

		switch_to_blog( $other_blog );
		update_user_option( $user_id, '_bbp_topic_count', 22, false );
		restore_current_blog();

		$old_post = $_POST;
		$_POST['bbpress-delete-imported-users'] = '1';
		ob_start();
		try {
			bbp_admin_reset_database();
		} finally {
			ob_end_clean();
			$_POST = $old_post;
		}

		$this->assertFalse( get_user_option( '_bbp_topic_count', $user_id ) );
		$this->assertSame( '42', get_user_meta( $user_id, '_bbp_old_user_id', true ) );
		$this->assertNotFalse( get_userdata( $user_id ) );

		switch_to_blog( $other_blog );
		try {
			$this->assertSame( '22', get_user_option( '_bbp_topic_count', $user_id ) );
		} finally {
			restore_current_blog();
		}
	}
}
