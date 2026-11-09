<?php

/**
 * Tests for database value handling in admin upgrades.
 *
 * @group admin
 * @group tools
 */
class BBP_Tests_Admin_Upgrade_SQL extends BBP_UnitTestCase {

	private $size = 100;
	private $groups_table;
	private $groupmeta_table;

	public function setUp(): void {
		parent::setUp();
		global $wpdb;

		$this->groups_table    = $wpdb->base_prefix . 'bp_groups';
		$this->groupmeta_table = $wpdb->base_prefix . 'bp_groups_groupmeta';
		$wpdb->query( "CREATE TEMPORARY TABLE `{$this->groups_table}` ( `id` bigint(20) unsigned NOT NULL, `status` varchar(20) NOT NULL, PRIMARY KEY (`id`) )" );
		$wpdb->query( "CREATE TEMPORARY TABLE `{$this->groupmeta_table}` ( `group_id` bigint(20) unsigned NOT NULL, `meta_key` varchar(100) NOT NULL, `meta_value` longtext NOT NULL )" );

		if ( ! function_exists( 'bbp_admin' ) ) {
			require_once BBP_PLUGIN_DIR . 'includes/admin/actions.php';
		}
		bbp_admin();
		add_filter( 'bbp_admin_repair_tool_batch_size', array( $this, 'batch_size' ) );
	}

	public function tearDown(): void {
		remove_filter( 'bbp_admin_repair_tool_batch_size', array( $this, 'batch_size' ) );
		global $wpdb;
		$wpdb->query( "DROP TEMPORARY TABLE IF EXISTS `{$this->groupmeta_table}`" );
		$wpdb->query( "DROP TEMPORARY TABLE IF EXISTS `{$this->groups_table}`" );

		parent::tearDown();
	}

	private function invoke_without_errors( $callback ) {
		set_error_handler(
			function ( $severity, $message, $file, $line ) {
				if ( error_reporting() & $severity ) {
					throw new ErrorException( $message, 0, $severity, $file, $line );
				}

				return false;
			}
		);

		try {
			return $callback();
		} finally {
			restore_error_handler();
		}
	}

	/**
	 * @covers ::bbp_admin_upgrade_group_forum_relationships
	 * @covers ::bbp_admin_upgrade_group_forum_relationships_dry_run
	 * @covers ::bbp_admin_upgrade_group_forum_relationships_process
	 */
	public function test_group_forum_upgrade_matches_legacy_id_containing_quote() {
		global $wpdb;

		$forum_id  = $this->factory->forum->create();
		$legacy_id = "legacy'forum";
		update_post_meta( $forum_id, '_bbp_old_forum_id', $legacy_id );

		$wpdb->insert( $this->groups_table, array( 'id' => 1, 'status' => 'public' ) );
		$wpdb->insert( $this->groups_table, array( 'id' => 2, 'status' => 'public' ) );
		$wpdb->insert( $this->groupmeta_table, array( 'group_id' => 1, 'meta_key' => 'forum_id', 'meta_value' => $legacy_id ) );
		$wpdb->insert( $this->groupmeta_table, array( 'group_id' => 2, 'meta_key' => 'forum_id', 'meta_value' => 'unrelated' ) );

		$dry_run = bbp_admin_upgrade_group_forum_relationships_dry_run();
		$this->assertSame( 0, $dry_run[0] );
		$this->assertStringContainsString( "1 group would be updated.\n1 forum would be updated.\n0 forum statuses would be synced", $dry_run[1] );
		$this->assertSame( $legacy_id, $wpdb->get_var( $wpdb->prepare( "SELECT meta_value FROM `{$this->groupmeta_table}` WHERE group_id = %d", 1 ) ) );
		$this->assertSame( array(), get_post_meta( $forum_id, '_bbp_group_ids', false ) );

		$result = bbp_admin_upgrade_group_forum_relationships();
		$this->assertSame( 0, $result[0] );

		$this->assertSame( (string) $forum_id, $wpdb->get_var( $wpdb->prepare( "SELECT meta_value FROM `{$this->groupmeta_table}` WHERE group_id = %d", 1 ) ) );
		$this->assertSame( 'unrelated', $wpdb->get_var( $wpdb->prepare( "SELECT meta_value FROM `{$this->groupmeta_table}` WHERE group_id = %d", 2 ) ) );
		$this->assertSame( array( 1 ), get_post_meta( $forum_id, '_bbp_group_ids', true ) );
	}

	/**
	 * @covers ::bbp_admin_upgrade_group_forum_relationships
	 * @covers ::bbp_admin_upgrade_group_forum_relationships_dry_run
	 * @covers ::bbp_admin_upgrade_group_forum_relationships_process
	 */
	public function test_group_forum_upgrade_hides_private_group_forum() {
		global $wpdb;

		$forum_id = $this->factory->forum->create();
		update_post_meta( $forum_id, '_bbp_old_forum_id', '42' );
		$wpdb->insert( $this->groups_table, array( 'id' => 7, 'status' => 'private' ) );
		$wpdb->insert( $this->groupmeta_table, array( 'group_id' => 7, 'meta_key' => 'forum_id', 'meta_value' => '42' ) );

		$dry_run = bbp_admin_upgrade_group_forum_relationships_dry_run();
		$this->assertSame( 0, $dry_run[0] );
		$this->assertStringContainsString( '1 forum status would be synced', $dry_run[1] );
		$this->assertSame( '42', $wpdb->get_var( $wpdb->prepare( "SELECT meta_value FROM `{$this->groupmeta_table}` WHERE group_id = %d", 7 ) ) );
		$this->assertSame( array(), get_post_meta( $forum_id, '_bbp_group_ids', false ) );
		$this->assertSame( bbp_get_public_status_id(), bbp_get_forum_visibility( $forum_id ) );

		$this->assertSame( 0, bbp_admin_upgrade_group_forum_relationships()[0] );
		$this->assertSame( (string) $forum_id, $wpdb->get_var( $wpdb->prepare( "SELECT meta_value FROM `{$this->groupmeta_table}` WHERE group_id = %d", 7 ) ) );
		$this->assertSame( array( 7 ), get_post_meta( $forum_id, '_bbp_group_ids', true ) );
		$this->assertSame( bbp_get_hidden_status_id(), bbp_get_forum_visibility( $forum_id ) );
	}

	/**
	 * @covers ::bbp_admin_upgrade_group_forum_relationships
	 * @covers ::bbp_admin_upgrade_group_forum_relationships_dry_run
	 * @covers ::bbp_admin_upgrade_group_forum_relationships_process
	 */
	public function test_group_forum_upgrade_reports_no_converted_forums() {
		$this->factory->forum->create();
		$this->assertNull( get_role( 'member' ) );
		add_role( 'member', 'Member' );

		try {
			$dry_run = bbp_admin_upgrade_group_forum_relationships_dry_run();
			$this->assertSame( 1, $dry_run[0] );
			$this->assertStringContainsString( '0 converted group forums to upgrade.', $dry_run[1] );
			$this->assertNotNull( get_role( 'member' ) );

			$result = bbp_admin_upgrade_group_forum_relationships();
			$this->assertSame( 1, $result[0] );
			$this->assertStringContainsString( '0 converted group forums to upgrade.', $result[1] );
			$this->assertNotNull( get_role( 'member' ) );
		} finally {
			remove_role( 'member' );
		}
	}

	/**
	 * @covers ::bbp_admin_upgrade_group_forum_relationships
	 * @covers ::bbp_admin_upgrade_group_forum_relationships_dry_run
	 * @covers ::bbp_admin_upgrade_group_forum_relationships_process
	 */
	public function test_group_forum_upgrade_reports_converted_forum_read_errors() {
		global $wpdb;

		$old_posts       = $wpdb->posts;
		$old_suppression = $wpdb->suppress_errors( true );

		try {
			$wpdb->posts = 'bbp_missing_group_forum_posts';
			$result      = bbp_admin_upgrade_group_forum_relationships_dry_run();
			$this->assertSame( 2, $result[0] );
			$this->assertFalse( $result['success'] );
			$this->assertStringContainsString( 'Failed!', $result[1] );

			$result = bbp_admin_upgrade_group_forum_relationships();
			$this->assertSame( 2, $result[0] );
			$this->assertFalse( $result['success'] );
		} finally {
			$wpdb->posts = $old_posts;
			$wpdb->suppress_errors( $old_suppression );
		}
	}

	/**
	 * @covers ::bbp_admin_upgrade_group_forum_relationships
	 * @covers ::bbp_admin_upgrade_group_forum_relationships_dry_run
	 * @covers ::bbp_admin_upgrade_group_forum_relationships_process
	 */
	public function test_group_forum_upgrade_reports_groupmeta_read_errors() {
		global $wpdb;

		$forum_id       = $this->factory->forum->create();
		$old_suppression = $wpdb->suppress_errors( true );
		update_post_meta( $forum_id, '_bbp_old_forum_id', '42' );
		$wpdb->query( "DROP TEMPORARY TABLE `{$this->groupmeta_table}`" );

		try {
			$result = bbp_admin_upgrade_group_forum_relationships_dry_run();
			$this->assertSame( 3, $result[0] );
			$this->assertFalse( $result['success'] );
			$this->assertStringContainsString( 'Failed!', $result[1] );

			$result = bbp_admin_upgrade_group_forum_relationships();
			$this->assertSame( 3, $result[0] );
			$this->assertFalse( $result['success'] );
		} finally {
			$wpdb->suppress_errors( $old_suppression );
		}
	}

	/**
	 * @covers ::bbp_admin_upgrade_group_forum_relationships
	 * @covers ::bbp_admin_upgrade_group_forum_relationships_dry_run
	 * @covers ::bbp_admin_upgrade_group_forum_relationships_process
	 */
	public function test_group_forum_upgrade_reports_group_read_errors() {
		global $wpdb;

		$forum_id       = $this->factory->forum->create();
		$old_suppression = $wpdb->suppress_errors( true );
		update_post_meta( $forum_id, '_bbp_old_forum_id', '42' );
		$wpdb->insert( $this->groupmeta_table, array( 'group_id' => 7, 'meta_key' => 'forum_id', 'meta_value' => '42' ) );
		$wpdb->query( "DROP TEMPORARY TABLE `{$this->groups_table}`" );

		try {
			$result = bbp_admin_upgrade_group_forum_relationships_dry_run();
			$this->assertSame( 4, $result[0] );
			$this->assertFalse( $result['success'] );
			$this->assertStringContainsString( 'Failed!', $result[1] );

			$result = bbp_admin_upgrade_group_forum_relationships();
			$this->assertSame( 4, $result[0] );
			$this->assertFalse( $result['success'] );
		} finally {
			$wpdb->suppress_errors( $old_suppression );
		}
	}

	/**
	 * @covers ::bbp_admin_upgrade_group_forum_relationships
	 * @covers ::bbp_admin_upgrade_group_forum_relationships_dry_run
	 * @covers ::bbp_admin_upgrade_group_forum_relationships_process
	 */
	public function test_group_forum_upgrade_reports_groupmeta_write_errors() {
		global $wpdb;

		$forum_id       = $this->factory->forum->create();
		$old_suppression = $wpdb->suppress_errors( true );
		$query_filter    = static function ( $query ) use ( $wpdb ) {
			$sql_update = "UPDATE `{$wpdb->base_prefix}bp_groups_groupmeta`";

			return ( 0 === strpos( $query, $sql_update ) )
				? 'UPDATE bbp_missing_groupmeta SET meta_value = 1'
				: $query;
		};

		update_post_meta( $forum_id, '_bbp_old_forum_id', '42' );
		$wpdb->insert( $this->groups_table, array( 'id' => 7, 'status' => 'public' ) );
		$wpdb->insert( $this->groupmeta_table, array( 'group_id' => 7, 'meta_key' => 'forum_id', 'meta_value' => '42' ) );
		add_filter( 'query', $query_filter );

		try {
			$dry_run = bbp_admin_upgrade_group_forum_relationships_dry_run();
			$this->assertSame( 0, $dry_run[0] );
			$this->assertSame( '42', $wpdb->get_var( $wpdb->prepare( "SELECT meta_value FROM `{$this->groupmeta_table}` WHERE group_id = %d", 7 ) ) );

			$result = bbp_admin_upgrade_group_forum_relationships();
			$this->assertSame( 5, $result[0] );
			$this->assertFalse( $result['success'] );
			$this->assertStringContainsString( "0 groups updated.\n1 forum updated", $result[1] );
			$this->assertSame( '42', $wpdb->get_var( $wpdb->prepare( "SELECT meta_value FROM `{$this->groupmeta_table}` WHERE group_id = %d", 7 ) ) );
			$this->assertSame( array( 7 ), get_post_meta( $forum_id, '_bbp_group_ids', true ) );
		} finally {
			remove_filter( 'query', $query_filter );
			$wpdb->suppress_errors( $old_suppression );
		}

		$this->assertSame( 0, bbp_admin_upgrade_group_forum_relationships()[0] );
		$this->assertSame( (string) $forum_id, $wpdb->get_var( $wpdb->prepare( "SELECT meta_value FROM `{$this->groupmeta_table}` WHERE group_id = %d", 7 ) ) );
	}

	/**
	 * @covers ::bbp_admin_upgrade_group_forum_relationships
	 * @covers ::bbp_admin_upgrade_group_forum_relationships_dry_run
	 * @covers ::bbp_admin_upgrade_group_forum_relationships_process
	 */
	public function test_group_forum_upgrade_recovers_from_forum_groupmeta_failures_with_overlapping_ids() {
		global $wpdb;

		$forum_ids  = $this->factory->forum->create_many( 2 );
		$meta_filter = static function ( $check, $object_id, $meta_key ) use ( $forum_ids ) {
			return ( ( $forum_ids[1] === $object_id ) && ( '_bbp_group_ids' === $meta_key ) )
				? false
				: $check;
		};

		update_post_meta( $forum_ids[0], '_bbp_old_forum_id', '42' );
		update_post_meta( $forum_ids[1], '_bbp_old_forum_id', $forum_ids[0] );
		$wpdb->insert( $this->groups_table, array( 'id' => 7, 'status' => 'public' ) );
		$wpdb->insert( $this->groups_table, array( 'id' => 9, 'status' => 'public' ) );
		$wpdb->insert( $this->groupmeta_table, array( 'group_id' => 7, 'meta_key' => 'forum_id', 'meta_value' => '42' ) );
		$wpdb->insert( $this->groupmeta_table, array( 'group_id' => 9, 'meta_key' => 'forum_id', 'meta_value' => $forum_ids[0] ) );
		add_filter( 'update_post_metadata', $meta_filter, 10, 3 );

		try {
			$result = bbp_admin_upgrade_group_forum_relationships();
			$this->assertSame( 8, $result[0] );
			$this->assertFalse( $result['success'] );
			$this->assertSame( array( (string) $forum_ids[0], (string) $forum_ids[0] ), $wpdb->get_col( "SELECT meta_value FROM `{$this->groupmeta_table}` ORDER BY group_id" ) );
			$this->assertSame( array( 7 ), get_post_meta( $forum_ids[0], '_bbp_group_ids', true ) );
			$this->assertSame( array(), get_post_meta( $forum_ids[1], '_bbp_group_ids', false ) );
		} finally {
			remove_filter( 'update_post_metadata', $meta_filter, 10 );
		}

		$this->assertSame( 0, bbp_admin_upgrade_group_forum_relationships()[0] );
		$this->assertSame( array( (string) $forum_ids[0], (string) $forum_ids[1] ), $wpdb->get_col( "SELECT meta_value FROM `{$this->groupmeta_table}` ORDER BY group_id" ) );
		$this->assertSame( array( 7 ), get_post_meta( $forum_ids[0], '_bbp_group_ids', true ) );
		$this->assertSame( array( 9 ), get_post_meta( $forum_ids[1], '_bbp_group_ids', true ) );

		$dry_run = bbp_admin_upgrade_group_forum_relationships_dry_run();
		$this->assertSame( 0, $dry_run[0] );
		$this->assertStringContainsString( "0 groups would be updated.\n0 forums would be updated.\n0 forum statuses would be synced", $dry_run[1] );
	}

	/**
	 * @covers ::bbp_admin_upgrade_group_forum_relationships
	 * @covers ::bbp_admin_upgrade_group_forum_relationships_dry_run
	 * @covers ::bbp_admin_upgrade_group_forum_relationships_process
	 */
	public function test_group_forum_upgrade_reports_forum_groupmeta_write_errors() {
		global $wpdb;

		$forum_id   = $this->factory->forum->create();
		$meta_filter = static function ( $check, $object_id, $meta_key ) use ( $forum_id ) {
			return ( ( $forum_id === $object_id ) && ( '_bbp_group_ids' === $meta_key ) )
				? false
				: $check;
		};

		update_post_meta( $forum_id, '_bbp_old_forum_id', '42' );
		$wpdb->insert( $this->groups_table, array( 'id' => 7, 'status' => 'public' ) );
		$wpdb->insert( $this->groupmeta_table, array( 'group_id' => 7, 'meta_key' => 'forum_id', 'meta_value' => $forum_id ) );
		add_filter( 'update_post_metadata', $meta_filter, 10, 3 );

		try {
			$dry_run = bbp_admin_upgrade_group_forum_relationships_dry_run();
			$this->assertSame( 0, $dry_run[0] );
			$this->assertSame( array(), get_post_meta( $forum_id, '_bbp_group_ids', false ) );

			$result = bbp_admin_upgrade_group_forum_relationships();
			$this->assertSame( 8, $result[0] );
			$this->assertFalse( $result['success'] );
			$this->assertStringContainsString( 'Forum group metadata could not be updated.', $result[1] );
			$this->assertSame( array(), get_post_meta( $forum_id, '_bbp_group_ids', false ) );
		} finally {
			remove_filter( 'update_post_metadata', $meta_filter, 10 );
		}

		$this->assertSame( 0, bbp_admin_upgrade_group_forum_relationships()[0] );
		$this->assertSame( array( 7 ), get_post_meta( $forum_id, '_bbp_group_ids', true ) );
	}

	/**
	 * @covers ::bbp_admin_upgrade_group_forum_relationships
	 * @covers ::bbp_admin_upgrade_group_forum_relationships_dry_run
	 * @covers ::bbp_admin_upgrade_group_forum_relationships_process
	 */
	public function test_group_forum_upgrade_reports_forum_visibility_write_errors() {
		global $wpdb;

		$forum_id       = $this->factory->forum->create();
		$old_suppression = $wpdb->suppress_errors( true );
		$query_filter    = static function ( $query ) use ( $wpdb ) {
			$sql_update = "UPDATE `{$wpdb->posts}` SET `post_status`";

			return ( 0 === strpos( $query, $sql_update ) )
				? 'UPDATE bbp_missing_group_forum_posts SET post_status = post_status'
				: $query;
		};

		update_post_meta( $forum_id, '_bbp_old_forum_id', '42' );
		update_post_meta( $forum_id, '_bbp_group_ids', array( 7 ) );
		$wpdb->insert( $this->groups_table, array( 'id' => 7, 'status' => 'private' ) );
		$wpdb->insert( $this->groupmeta_table, array( 'group_id' => 7, 'meta_key' => 'forum_id', 'meta_value' => $forum_id ) );
		add_filter( 'query', $query_filter );

		try {
			$dry_run = bbp_admin_upgrade_group_forum_relationships_dry_run();
			$this->assertSame( 0, $dry_run[0] );
			$this->assertStringContainsString( '1 forum status would be synced', $dry_run[1] );

			$result = bbp_admin_upgrade_group_forum_relationships();
			$this->assertSame( 9, $result[0] );
			$this->assertFalse( $result['success'] );
			$this->assertStringContainsString( 'Forum visibility could not be updated.', $result[1] );
			$this->assertSame( bbp_get_public_status_id(), bbp_get_forum_visibility( $forum_id ) );
		} finally {
			remove_filter( 'query', $query_filter );
			$wpdb->suppress_errors( $old_suppression );
		}

		$this->assertSame( 0, bbp_admin_upgrade_group_forum_relationships()[0] );
		$this->assertSame( bbp_get_hidden_status_id(), bbp_get_forum_visibility( $forum_id ) );
	}

	/**
	 * @covers ::bbp_admin_upgrade_group_forum_relationships
	 * @covers ::bbp_admin_upgrade_group_forum_relationships_dry_run
	 * @covers ::bbp_admin_upgrade_group_forum_relationships_process
	 */
	public function test_group_forum_upgrade_syncs_stale_visibility_options_for_public_groups() {
		global $wpdb;

		$forum_id = $this->factory->forum->create();
		update_post_meta( $forum_id, '_bbp_old_forum_id', '42' );
		update_post_meta( $forum_id, '_bbp_group_ids', array( 7 ) );
		update_option( '_bbp_hidden_forums', array( $forum_id ) );
		$wpdb->insert( $this->groups_table, array( 'id' => 7, 'status' => 'public' ) );
		$wpdb->insert( $this->groupmeta_table, array( 'group_id' => 7, 'meta_key' => 'forum_id', 'meta_value' => $forum_id ) );

		$dry_run = bbp_admin_upgrade_group_forum_relationships_dry_run();
		$this->assertSame( 0, $dry_run[0] );
			$this->assertStringContainsString( '1 forum status would be synced', $dry_run[1] );
		$this->assertContains( $forum_id, bbp_get_hidden_forum_ids() );
		$this->assertSame( bbp_get_public_status_id(), bbp_get_forum_visibility( $forum_id ) );

		$this->assertSame( 0, bbp_admin_upgrade_group_forum_relationships()[0] );
		$this->assertNotContains( $forum_id, bbp_get_hidden_forum_ids() );
		$this->assertSame( bbp_get_public_status_id(), bbp_get_forum_visibility( $forum_id ) );
	}

	/**
	 * @covers ::bbp_admin_upgrade_group_forum_relationships
	 * @covers ::bbp_admin_upgrade_group_forum_relationships_dry_run
	 * @covers ::bbp_admin_upgrade_group_forum_relationships_process
	 */
	public function test_group_forum_upgrade_syncs_stale_visibility_options_for_hidden_groups() {
		global $wpdb;

		$forum_id = $this->factory->forum->create();
		update_post_meta( $forum_id, '_bbp_old_forum_id', '42' );
		update_post_meta( $forum_id, '_bbp_group_ids', array( 7 ) );
		$wpdb->update( $wpdb->posts, array( 'post_status' => bbp_get_hidden_status_id() ), array( 'ID' => $forum_id ) );
		clean_post_cache( $forum_id );
		update_option( '_bbp_hidden_forums', array() );
		update_option( '_bbp_private_forums', array( $forum_id ) );
		$wpdb->insert( $this->groups_table, array( 'id' => 7, 'status' => 'private' ) );
		$wpdb->insert( $this->groupmeta_table, array( 'group_id' => 7, 'meta_key' => 'forum_id', 'meta_value' => $forum_id ) );

		$dry_run = bbp_admin_upgrade_group_forum_relationships_dry_run();
		$this->assertSame( 0, $dry_run[0] );
			$this->assertStringContainsString( '1 forum status would be synced', $dry_run[1] );
		$this->assertNotContains( $forum_id, bbp_get_hidden_forum_ids() );
		$this->assertContains( $forum_id, bbp_get_private_forum_ids() );

		$this->assertSame( 0, bbp_admin_upgrade_group_forum_relationships()[0] );
		$this->assertContains( $forum_id, bbp_get_hidden_forum_ids() );
		$this->assertNotContains( $forum_id, bbp_get_private_forum_ids() );
		$this->assertSame( bbp_get_hidden_status_id(), bbp_get_forum_visibility( $forum_id ) );

		$dry_run = bbp_admin_upgrade_group_forum_relationships_dry_run();
		$this->assertSame( 0, $dry_run[0] );
		$this->assertStringContainsString( '0 forum statuses would be synced', $dry_run[1] );
	}

	/**
	 * @covers ::bbp_admin_upgrade_group_forum_relationships
	 * @covers ::bbp_admin_upgrade_group_forum_relationships_dry_run
	 * @covers ::bbp_admin_upgrade_group_forum_relationships_process
	 */
	public function test_group_forum_upgrade_repairs_relationships_when_legacy_and_new_ids_match() {
		global $wpdb;

		$forum_id = $this->factory->forum->create();
		update_post_meta( $forum_id, '_bbp_old_forum_id', $forum_id );
		$wpdb->insert( $this->groups_table, array( 'id' => 7, 'status' => 'public' ) );
		$wpdb->insert( $this->groupmeta_table, array( 'group_id' => 7, 'meta_key' => 'forum_id', 'meta_value' => $forum_id ) );

		$dry_run = bbp_admin_upgrade_group_forum_relationships_dry_run();
		$this->assertSame( 0, $dry_run[0] );
		$this->assertStringContainsString( "0 groups would be updated.\n1 forum would be updated.\n0 forum statuses would be synced", $dry_run[1] );
		$this->assertSame( array(), get_post_meta( $forum_id, '_bbp_group_ids', false ) );

		$result = bbp_admin_upgrade_group_forum_relationships();
		$this->assertSame( 0, $result[0] );
		$this->assertStringContainsString( "0 groups updated.\n1 forum updated.\n0 forum statuses synced", $result[1] );
		$this->assertSame( array( 7 ), get_post_meta( $forum_id, '_bbp_group_ids', true ) );
	}

	/**
	 * @covers ::bbp_admin_upgrade_group_forum_relationships
	 * @covers ::bbp_admin_upgrade_group_forum_relationships_dry_run
	 * @covers ::bbp_admin_upgrade_group_forum_relationships_process
	 */
	public function test_group_forum_upgrade_resumes_an_already_converted_relationship() {
		global $wpdb;

		$forum_id = $this->factory->forum->create();
		update_post_meta( $forum_id, '_bbp_old_forum_id', '42' );
		$wpdb->insert( $this->groups_table, array( 'id' => 7, 'status' => 'private' ) );
		$wpdb->insert( $this->groupmeta_table, array( 'group_id' => 7, 'meta_key' => 'forum_id', 'meta_value' => $forum_id ) );

		$dry_run = bbp_admin_upgrade_group_forum_relationships_dry_run();
		$this->assertSame( 0, $dry_run[0] );
		$this->assertStringContainsString( "0 groups would be updated.\n1 forum would be updated.\n1 forum status would be synced", $dry_run[1] );

		$this->assertSame( 0, bbp_admin_upgrade_group_forum_relationships()[0] );
		$this->assertSame( array( 7 ), get_post_meta( $forum_id, '_bbp_group_ids', true ) );
		$this->assertSame( bbp_get_hidden_status_id(), bbp_get_forum_visibility( $forum_id ) );

		$dry_run = bbp_admin_upgrade_group_forum_relationships_dry_run();
		$this->assertSame( 0, $dry_run[0] );
		$this->assertStringContainsString( "0 groups would be updated.\n0 forums would be updated.\n0 forum statuses would be synced", $dry_run[1] );
	}

	/**
	 * @covers ::bbp_admin_upgrade_group_forum_relationships
	 * @covers ::bbp_admin_upgrade_group_forum_relationships_dry_run
	 * @covers ::bbp_admin_upgrade_group_forum_relationships_process
	 */
	public function test_group_forum_upgrade_skips_modern_many_to_many_relationships() {
		global $wpdb;

		$forum_id = $this->factory->forum->create();
		update_post_meta( $forum_id, '_bbp_old_forum_id', '42' );
		update_post_meta( $forum_id, '_bbp_group_ids', array( 7, 9 ) );
		$wpdb->insert( $this->groups_table, array( 'id' => 7, 'status' => 'public' ) );
		$wpdb->insert( $this->groups_table, array( 'id' => 9, 'status' => 'private' ) );
		$wpdb->insert( $this->groupmeta_table, array( 'group_id' => 7, 'meta_key' => 'forum_id', 'meta_value' => maybe_serialize( array( $forum_id ) ) ) );
		$wpdb->insert( $this->groupmeta_table, array( 'group_id' => 9, 'meta_key' => 'forum_id', 'meta_value' => maybe_serialize( array( $forum_id ) ) ) );

		$before = $wpdb->get_col( "SELECT meta_value FROM `{$this->groupmeta_table}` ORDER BY group_id" );
		$dry_run = bbp_admin_upgrade_group_forum_relationships_dry_run();
		$this->assertSame( 0, $dry_run[0] );
		$this->assertStringContainsString( "0 groups would be updated.\n0 forums would be updated.\n0 forum statuses would be synced", $dry_run[1] );
		$this->assertSame( $before, $wpdb->get_col( "SELECT meta_value FROM `{$this->groupmeta_table}` ORDER BY group_id" ) );
		$this->assertSame( array( 7, 9 ), get_post_meta( $forum_id, '_bbp_group_ids', true ) );

		$result = bbp_admin_upgrade_group_forum_relationships();
		$this->assertSame( 0, $result[0] );
		$this->assertSame( $before, $wpdb->get_col( "SELECT meta_value FROM `{$this->groupmeta_table}` ORDER BY group_id" ) );
		$this->assertSame( array( 7, 9 ), get_post_meta( $forum_id, '_bbp_group_ids', true ) );
	}

	/**
	 * @covers ::bbp_admin_upgrade_group_forum_relationships
	 * @covers ::bbp_admin_upgrade_group_forum_relationships_dry_run
	 * @covers ::bbp_admin_upgrade_group_forum_relationships_process
	 */
	public function test_group_forum_upgrade_skips_modern_serialized_relationships() {
		global $wpdb;

		$forum_id  = $this->factory->forum->create();
		$forum_ids = maybe_serialize( array( $forum_id ) );
		update_post_meta( $forum_id, '_bbp_old_forum_id', '42' );
		update_post_meta( $forum_id, '_bbp_group_ids', array( 7 ) );
		$wpdb->insert( $this->groups_table, array( 'id' => 7, 'status' => 'public' ) );
		$wpdb->insert( $this->groupmeta_table, array( 'group_id' => 7, 'meta_key' => 'forum_id', 'meta_value' => $forum_ids ) );

		$dry_run = bbp_admin_upgrade_group_forum_relationships_dry_run();
		$this->assertSame( 0, $dry_run[0] );
		$this->assertStringContainsString( "0 groups would be updated.\n0 forums would be updated.\n0 forum statuses would be synced", $dry_run[1] );
		$this->assertSame( $forum_ids, $wpdb->get_var( "SELECT meta_value FROM `{$this->groupmeta_table}` WHERE group_id = 7" ) );

		$result = bbp_admin_upgrade_group_forum_relationships();
		$this->assertSame( 0, $result[0] );
		$this->assertSame( $forum_ids, $wpdb->get_var( "SELECT meta_value FROM `{$this->groupmeta_table}` WHERE group_id = 7" ) );
		$this->assertSame( array( 7 ), get_post_meta( $forum_id, '_bbp_group_ids', true ) );
	}

	/**
	 * @covers ::bbp_admin_upgrade_group_forum_relationships
	 * @covers ::bbp_admin_upgrade_group_forum_relationships_dry_run
	 * @covers ::bbp_admin_upgrade_group_forum_relationships_process
	 */
	public function test_group_forum_upgrade_skips_stale_modern_relationships() {
		global $wpdb;

		$forum_ids       = $this->factory->forum->create_many( 2 );
		$stored_forum_ids = maybe_serialize( array( 999 ) );
		update_post_meta( $forum_ids[0], '_bbp_old_forum_id', '42' );
		update_post_meta( $forum_ids[0], '_bbp_group_ids', array( 7 ) );
		update_post_meta( $forum_ids[1], '_bbp_old_forum_id', '84' );
		update_post_meta( $forum_ids[1], '_bbp_group_ids', array( 9 ) );
		$wpdb->insert( $this->groups_table, array( 'id' => 7, 'status' => 'public' ) );
		$wpdb->insert( $this->groupmeta_table, array( 'group_id' => 7, 'meta_key' => 'forum_id', 'meta_value' => $stored_forum_ids ) );

		$dry_run = $this->invoke_without_errors(
			function() {
				return bbp_admin_upgrade_group_forum_relationships_dry_run();
			}
		);
		$this->assertSame( 0, $dry_run[0] );
		$this->assertStringContainsString( "0 groups would be updated.\n0 forums would be updated.\n0 forum statuses would be synced", $dry_run[1] );
		$this->assertSame( $stored_forum_ids, $wpdb->get_var( "SELECT meta_value FROM `{$this->groupmeta_table}` WHERE group_id = 7" ) );

		$result = $this->invoke_without_errors(
			function() {
				return bbp_admin_upgrade_group_forum_relationships();
			}
		);
		$this->assertSame( 0, $result[0] );
		$this->assertSame( $stored_forum_ids, $wpdb->get_var( "SELECT meta_value FROM `{$this->groupmeta_table}` WHERE group_id = 7" ) );
		$this->assertSame( array( 7 ), get_post_meta( $forum_ids[0], '_bbp_group_ids', true ) );
		$this->assertSame( array( 9 ), get_post_meta( $forum_ids[1], '_bbp_group_ids', true ) );
	}

	/**
	 * @covers ::bbp_admin_upgrade_group_forum_relationships
	 * @covers ::bbp_admin_upgrade_group_forum_relationships_dry_run
	 * @covers ::bbp_admin_upgrade_group_forum_relationships_process
	 */
	public function test_group_forum_upgrade_rejects_duplicate_legacy_ids() {
		global $wpdb;

		$forum_ids = $this->factory->forum->create_many( 2 );
		update_post_meta( $forum_ids[0], '_bbp_old_forum_id', '42' );
		update_post_meta( $forum_ids[1], '_bbp_old_forum_id', '42' );
		$wpdb->insert( $this->groups_table, array( 'id' => 7, 'status' => 'public' ) );
		$wpdb->insert( $this->groupmeta_table, array( 'group_id' => 7, 'meta_key' => 'forum_id', 'meta_value' => '42' ) );

		$result = bbp_admin_upgrade_group_forum_relationships_dry_run();
		$this->assertSame( 7, $result[0] );
		$this->assertFalse( $result['success'] );
		$this->assertStringContainsString( 'Ambiguous converted forum ID mappings', $result[1] );

		$result = bbp_admin_upgrade_group_forum_relationships();
		$this->assertSame( 7, $result[0] );
		$this->assertFalse( $result['success'] );
		$this->assertSame( '42', $wpdb->get_var( $wpdb->prepare( "SELECT meta_value FROM `{$this->groupmeta_table}` WHERE group_id = %d", 7 ) ) );
	}

	/**
	 * @covers ::bbp_admin_upgrade_group_forum_relationships
	 * @covers ::bbp_admin_upgrade_group_forum_relationships_dry_run
	 * @covers ::bbp_admin_upgrade_group_forum_relationships_process
	 */
	public function test_group_forum_upgrade_handles_overlapping_legacy_and_new_ids() {
		global $wpdb;

		$forum_ids = $this->factory->forum->create_many( 2 );
		update_post_meta( $forum_ids[0], '_bbp_old_forum_id', '42' );
		update_post_meta( $forum_ids[1], '_bbp_old_forum_id', $forum_ids[0] );
		$wpdb->insert( $this->groups_table, array( 'id' => 7, 'status' => 'public' ) );
		$wpdb->insert( $this->groups_table, array( 'id' => 9, 'status' => 'private' ) );
		$wpdb->insert( $this->groupmeta_table, array( 'group_id' => 7, 'meta_key' => 'forum_id', 'meta_value' => '42' ) );
		$wpdb->insert( $this->groupmeta_table, array( 'group_id' => 9, 'meta_key' => 'forum_id', 'meta_value' => $forum_ids[0] ) );

		$dry_run = bbp_admin_upgrade_group_forum_relationships_dry_run();
		$this->assertSame( 0, $dry_run[0] );
		$this->assertStringContainsString( "2 groups would be updated.\n2 forums would be updated.\n1 forum status would be synced", $dry_run[1] );
		$this->assertSame( array( '42', (string) $forum_ids[0] ), $wpdb->get_col( "SELECT meta_value FROM `{$this->groupmeta_table}` ORDER BY group_id" ) );

		$this->assertSame( 0, bbp_admin_upgrade_group_forum_relationships()[0] );
		$this->assertSame( array( (string) $forum_ids[0], (string) $forum_ids[1] ), $wpdb->get_col( "SELECT meta_value FROM `{$this->groupmeta_table}` ORDER BY group_id" ) );
		$this->assertSame( array( 7 ), get_post_meta( $forum_ids[0], '_bbp_group_ids', true ) );
		$this->assertSame( array( 9 ), get_post_meta( $forum_ids[1], '_bbp_group_ids', true ) );
		$this->assertSame( bbp_get_hidden_status_id(), bbp_get_forum_visibility( $forum_ids[1] ) );

		$dry_run = bbp_admin_upgrade_group_forum_relationships_dry_run();
		$this->assertSame( 0, $dry_run[0] );
		$this->assertStringContainsString( "0 groups would be updated.\n0 forums would be updated.\n0 forum statuses would be synced", $dry_run[1] );
	}

	/**
	 * @covers ::bbp_admin_upgrade_group_forum_relationships
	 * @covers ::bbp_admin_upgrade_group_forum_relationships_dry_run
	 * @covers ::bbp_admin_upgrade_group_forum_relationships_process
	 */
	public function test_group_forum_upgrade_ignores_unreferenced_legacy_id_collisions() {
		$native_forum_id = $this->factory->forum->create();
		$forum_ids       = $this->factory->forum->create_many( 2 );
		update_post_meta( $forum_ids[0], '_bbp_old_forum_id', $native_forum_id );
		update_post_meta( $forum_ids[1], '_bbp_old_forum_id', $native_forum_id );

		$dry_run = bbp_admin_upgrade_group_forum_relationships_dry_run();
		$this->assertSame( 0, $dry_run[0] );
		$this->assertStringContainsString( "0 groups would be updated.\n0 forums would be updated.\n0 forum statuses would be synced", $dry_run[1] );
		$this->assertSame( array(), get_post_meta( $forum_ids[0], '_bbp_group_ids', false ) );
		$this->assertSame( array(), get_post_meta( $forum_ids[1], '_bbp_group_ids', false ) );

		$result = bbp_admin_upgrade_group_forum_relationships();
		$this->assertSame( 0, $result[0] );
		$this->assertSame( array(), get_post_meta( $forum_ids[0], '_bbp_group_ids', false ) );
		$this->assertSame( array(), get_post_meta( $forum_ids[1], '_bbp_group_ids', false ) );
	}

	/**
	 * @covers ::bbp_admin_upgrade_group_forum_relationships
	 * @covers ::bbp_admin_upgrade_group_forum_relationships_dry_run
	 * @covers ::bbp_admin_upgrade_group_forum_relationships_process
	 */
	public function test_group_forum_upgrade_rejects_legacy_ids_matching_native_forum_ids() {
		global $wpdb;

		$native_forum_id    = $this->factory->forum->create();
		$converted_forum_id = $this->factory->forum->create();
		update_post_meta( $converted_forum_id, '_bbp_old_forum_id', $native_forum_id );
		$wpdb->insert( $this->groups_table, array( 'id' => 7, 'status' => 'public' ) );
		$wpdb->insert( $this->groupmeta_table, array( 'group_id' => 7, 'meta_key' => 'forum_id', 'meta_value' => $native_forum_id ) );

		$result = bbp_admin_upgrade_group_forum_relationships_dry_run();
		$this->assertSame( 7, $result[0] );
		$this->assertFalse( $result['success'] );
		$this->assertStringContainsString( 'Ambiguous converted forum ID mappings', $result[1] );

		$result = bbp_admin_upgrade_group_forum_relationships();
		$this->assertSame( 7, $result[0] );
		$this->assertFalse( $result['success'] );
		$this->assertSame( (string) $native_forum_id, $wpdb->get_var( $wpdb->prepare( "SELECT meta_value FROM `{$this->groupmeta_table}` WHERE group_id = %d", 7 ) ) );
		$this->assertSame( array(), get_post_meta( $converted_forum_id, '_bbp_group_ids', false ) );
	}

	/**
	 * @covers ::bbp_admin_upgrade_group_forum_relationships
	 * @covers ::bbp_admin_upgrade_group_forum_relationships_dry_run
	 * @covers ::bbp_admin_upgrade_group_forum_relationships_process
	 */
	public function test_group_forum_upgrade_rejects_multiple_groups_for_one_legacy_forum() {
		global $wpdb;

		$forum_id = $this->factory->forum->create();
		update_post_meta( $forum_id, '_bbp_old_forum_id', '42' );
		$wpdb->insert( $this->groups_table, array( 'id' => 7, 'status' => 'public' ) );
		$wpdb->insert( $this->groups_table, array( 'id' => 9, 'status' => 'public' ) );
		$wpdb->insert( $this->groupmeta_table, array( 'group_id' => 7, 'meta_key' => 'forum_id', 'meta_value' => '42' ) );
		$wpdb->insert( $this->groupmeta_table, array( 'group_id' => 9, 'meta_key' => 'forum_id', 'meta_value' => '42' ) );

		$result = bbp_admin_upgrade_group_forum_relationships_dry_run();
		$this->assertSame( 7, $result[0] );
		$this->assertFalse( $result['success'] );
		$this->assertStringContainsString( 'Ambiguous BuddyPress group-forum relationships', $result[1] );

		$result = bbp_admin_upgrade_group_forum_relationships();
		$this->assertSame( 7, $result[0] );
		$this->assertFalse( $result['success'] );
		$this->assertSame( array( '42', '42' ), $wpdb->get_col( "SELECT meta_value FROM `{$this->groupmeta_table}` ORDER BY group_id" ) );
		$this->assertSame( array(), get_post_meta( $forum_id, '_bbp_group_ids', false ) );
	}

	/**
	 * @covers ::bbp_admin_upgrade_group_forum_relationships
	 * @covers ::bbp_admin_upgrade_group_forum_relationships_dry_run
	 * @covers ::bbp_admin_upgrade_group_forum_relationships_process
	 */
	public function test_group_forum_upgrade_preflights_all_relationships_before_writing() {
		global $wpdb;

		$forum_ids = $this->factory->forum->create_many( 2 );
		update_post_meta( $forum_ids[0], '_bbp_old_forum_id', '42' );
		update_post_meta( $forum_ids[1], '_bbp_old_forum_id', '84' );
		$wpdb->insert( $this->groups_table, array( 'id' => 7, 'status' => 'public' ) );
		$wpdb->insert( $this->groups_table, array( 'id' => 9, 'status' => 'public' ) );
		$wpdb->insert( $this->groups_table, array( 'id' => 11, 'status' => 'public' ) );
		$wpdb->insert( $this->groupmeta_table, array( 'group_id' => 7, 'meta_key' => 'forum_id', 'meta_value' => '42' ) );
		$wpdb->insert( $this->groupmeta_table, array( 'group_id' => 9, 'meta_key' => 'forum_id', 'meta_value' => '84' ) );
		$wpdb->insert( $this->groupmeta_table, array( 'group_id' => 11, 'meta_key' => 'forum_id', 'meta_value' => '84' ) );

		$result = bbp_admin_upgrade_group_forum_relationships_dry_run();
		$this->assertSame( 7, $result[0] );
		$this->assertFalse( $result['success'] );

		$result = bbp_admin_upgrade_group_forum_relationships();
		$this->assertSame( 7, $result[0] );
		$this->assertFalse( $result['success'] );
		$this->assertSame( array( '42', '84', '84' ), $wpdb->get_col( "SELECT meta_value FROM `{$this->groupmeta_table}` ORDER BY group_id" ) );
		$this->assertSame( array(), get_post_meta( $forum_ids[0], '_bbp_group_ids', false ) );
		$this->assertSame( bbp_get_public_status_id(), bbp_get_forum_visibility( $forum_ids[0] ) );
	}

	/**
	 * @covers ::bbp_admin_upgrade_group_forum_relationships
	 * @covers ::bbp_admin_upgrade_group_forum_relationships_dry_run
	 * @covers ::bbp_admin_upgrade_group_forum_relationships_process
	 */
	public function test_group_forum_upgrade_dry_run_skips_root_forum_and_role_writes() {
		$forum_id       = $this->factory->forum->create( array( 'post_title' => 'Default Forum' ) );
		$old_root_forum = get_option( '_bbp_group_forums_root_id', null );

		update_post_meta( $forum_id, '_bbp_old_forum_id', 1 );
		$this->assertNull( get_role( 'member' ) );
		add_role( 'member', 'Member' );

		try {
			$dry_run = bbp_admin_upgrade_group_forum_relationships_dry_run();
			$this->assertSame( 0, $dry_run[0] );
			$this->assertStringContainsString( "1 group root setting would be updated.\n1 forum would be renamed.\n1 legacy role would be removed (member)", $dry_run[1] );
			$this->assertSame( 'Default Forum', get_post( $forum_id )->post_title );
			$this->assertSame( $old_root_forum, get_option( '_bbp_group_forums_root_id', null ) );
			$this->assertNotNull( get_role( 'member' ) );

			$this->assertSame( 0, bbp_admin_upgrade_group_forum_relationships()[0] );
			$this->assertSame( 'Group Forums', get_post( $forum_id )->post_title );
			$this->assertSame( $forum_id, get_option( '_bbp_group_forums_root_id' ) );
			$this->assertNull( get_role( 'member' ) );
		} finally {
			remove_role( 'member' );

			if ( null === $old_root_forum ) {
				delete_option( '_bbp_group_forums_root_id' );
			} else {
				update_option( '_bbp_group_forums_root_id', $old_root_forum );
			}
		}
	}

	/**
	 * @covers ::bbp_admin_upgrade_group_forum_relationships
	 * @covers ::bbp_admin_upgrade_group_forum_relationships_dry_run
	 * @covers ::bbp_admin_upgrade_group_forum_relationships_process
	 */
	public function test_group_forum_upgrade_reports_root_forum_write_errors_before_option_or_role_writes() {
		global $wpdb;

		$forum_id        = $this->factory->forum->create( array( 'post_title' => 'Default Forum' ) );
		$old_root_forum  = get_option( '_bbp_group_forums_root_id', null );
		$old_suppression = $wpdb->suppress_errors( true );
		$query_filter    = static function ( $query ) use ( $wpdb ) {
			$sql_update = "UPDATE `{$wpdb->posts}`";

			return ( 0 === strpos( $query, $sql_update ) )
				? 'UPDATE bbp_missing_group_root_posts SET post_title = post_title'
				: $query;
		};

		update_post_meta( $forum_id, '_bbp_old_forum_id', 1 );
		$this->assertNull( get_role( 'member' ) );
		add_role( 'member', 'Member' );
		add_filter( 'query', $query_filter );

		try {
			$result = bbp_admin_upgrade_group_forum_relationships();
			$this->assertSame( 6, $result[0] );
			$this->assertFalse( $result['success'] );
			$this->assertStringContainsString( 'The group forums root could not be renamed.', $result[1] );
			$this->assertSame( 'Default Forum', get_post( $forum_id )->post_title );
			$this->assertSame( $old_root_forum, get_option( '_bbp_group_forums_root_id', null ) );
			$this->assertNotNull( get_role( 'member' ) );
		} finally {
			remove_filter( 'query', $query_filter );
			$wpdb->suppress_errors( $old_suppression );
			remove_role( 'member' );

			if ( null === $old_root_forum ) {
				delete_option( '_bbp_group_forums_root_id' );
			} else {
				update_option( '_bbp_group_forums_root_id', $old_root_forum );
			}
		}
	}

	/**
	 * @covers ::bbp_admin_upgrade_group_forum_relationships
	 * @covers ::bbp_admin_upgrade_group_forum_relationships_dry_run
	 * @covers ::bbp_admin_upgrade_group_forum_relationships_process
	 */
	public function test_group_forum_upgrade_reports_root_option_write_errors_before_role_writes() {
		$forum_id       = $this->factory->forum->create( array( 'post_title' => 'Default Forum' ) );
		$old_root_forum = get_option( '_bbp_group_forums_root_id', null );
		$option_filter  = static function ( $value, $old_value ) {
			return $old_value;
		};

		update_post_meta( $forum_id, '_bbp_old_forum_id', 1 );
		update_option( '_bbp_group_forums_root_id', 999 );
		$this->assertNull( get_role( 'member' ) );
		add_role( 'member', 'Member' );

		try {
			add_filter( 'pre_update_option__bbp_group_forums_root_id', $option_filter, 10, 2 );

			try {
				$result = bbp_admin_upgrade_group_forum_relationships();
				$this->assertSame( 10, $result[0] );
				$this->assertFalse( $result['success'] );
				$this->assertStringContainsString( 'The group forums root setting could not be updated.', $result[1] );
				$this->assertSame( 'Group Forums', get_post( $forum_id )->post_title );
				$this->assertSame( 999, (int) get_option( '_bbp_group_forums_root_id' ) );
				$this->assertNotNull( get_role( 'member' ) );
			} finally {
				remove_filter( 'pre_update_option__bbp_group_forums_root_id', $option_filter, 10 );
			}

			$this->assertSame( 0, bbp_admin_upgrade_group_forum_relationships()[0] );
			$this->assertSame( $forum_id, get_option( '_bbp_group_forums_root_id' ) );
			$this->assertNull( get_role( 'member' ) );
		} finally {
			remove_filter( 'pre_update_option__bbp_group_forums_root_id', $option_filter, 10 );
			remove_role( 'member' );

			if ( null === $old_root_forum ) {
				delete_option( '_bbp_group_forums_root_id' );
			} else {
				update_option( '_bbp_group_forums_root_id', $old_root_forum );
			}
		}
	}
	public function batch_size() {
		return $this->size;
	}

	public function sizes() {
		return array( array( 1 ), array( 7 ), array( 1000 ) );
	}

	private function checkpoint() {
		return bbp_admin_repair_tool_batch_option_name( 'bbp-group-forums' );
	}

	private function fixture( $overlap = false ) {
		$db     = bbp_db();
		$forums = $this->factory->forum->create_many( 2 );
		foreach ( $forums as $index => $id ) {
			$legacy = $overlap && $index ? (string) $forums[0] : 'legacy-' . $index;
			update_post_meta( $id, '_bbp_old_forum_id', $legacy );
			$db->insert( $this->groups_table, array( 'id' => 7 + $index, 'status' => $index ? 'private' : 'public' ) );
			$db->insert( $this->groupmeta_table, array( 'group_id' => 7 + $index, 'meta_key' => 'forum_id', 'meta_value' => $legacy ) );
		}
		return $forums;
	}

	private function run_batch( $dry_run = false ) {
		$worker = new BBP_Admin_Group_Forums( $dry_run );
		return $worker->run();
	}

	private function until_phase( $phase ) {
		for ( $i = 0; $i < 300; ++$i ) {
			$state = get_option( $this->checkpoint() );
			if ( is_array( $state ) && $phase === $state['phase'] ) {
				return $state;
			}
			$this->assertSame( 0, $this->run_batch()['code'] );
		}
		$this->fail( 'Phase was not reached.' );
	}

	private function snapshot() {
		$db = bbp_db();
		return array(
			$db->get_results( "SELECT ID, post_type, post_status, post_title, post_name FROM {$db->posts} ORDER BY ID", ARRAY_A ),
			$db->get_results( "SELECT * FROM {$db->postmeta} ORDER BY meta_id", ARRAY_A ),
			$db->get_results( "SELECT option_name, option_value FROM {$db->options} ORDER BY option_name", ARRAY_A ),
			$db->get_results( "SELECT * FROM {$this->groupmeta_table} ORDER BY group_id", ARRAY_A ),
		);
	}

	/** @dataProvider sizes */
	public function test_bounded_batches_preview_exactly_without_writes_and_rerun_safely( $size ) {
		$forums     = $this->fixture();
		$this->size = $size;
		$totals     = array( 'groups' => 0, 'forums' => 0, 'statuses' => 0 );
		for ( $i = 0; $i < 300; ++$i ) {
			$before  = $this->snapshot();
			$preview = $this->run_batch( true );
			$this->assertSame( $before, $this->snapshot() );
			$result = $this->run_batch();
			$this->assertSame( 0, $result['code'] );
			$this->assertLessThanOrEqual( $size, $result['work'] );
			foreach ( array( 'groups', 'forums', 'statuses', 'root', 'rename', 'roles', 'complete' ) as $key ) {
				$this->assertSame( $preview[ $key ], $result[ $key ], $key );
			}
			foreach ( $totals as $key => $value ) {
				$totals[ $key ] += $result[ $key ];
			}
			if ( $result['complete'] ) {
				break;
			}
			$this->assertLessThan( 600, strlen( serialize( get_option( $this->checkpoint() ) ) ) );
		}
		$this->assertTrue( $result['complete'] );
		$this->assertSame( array( 'groups' => 2, 'forums' => 2, 'statuses' => 1 ), $totals );
		$this->assertSame( array( 7 ), get_post_meta( $forums[0], '_bbp_group_ids', true ) );
		$this->assertSame( array( 8 ), get_post_meta( $forums[1], '_bbp_group_ids', true ) );
		$this->assertSame( bbp_get_hidden_status_id(), bbp_get_forum_visibility( $forums[1] ) );
		$db = bbp_db();
		$this->assertSame( '0', $db->get_var( $db->prepare( "SELECT COUNT(*) FROM {$db->options} WHERE option_name LIKE %s", $db->esc_like( $this->checkpoint() ) . '%' ) ) );
		$this->size = 1000;
		$result     = $this->run_batch();
		$this->assertTrue( $result['complete'] );
		$this->assertSame( 0, $result['groups'] + $result['forums'] + $result['statuses'] );
	}

	public function current_id_formats() {
		return array( array( 'zeros', false ), array( 'zeros', true ), array( 'spaces', false ), array( 'spaces', true ) );
	}

	/** @dataProvider current_id_formats */
	public function test_current_id_database_equivalence_is_preserved_across_batches( $format, $marked ) {
		$db         = bbp_db();
		$forum      = $this->factory->forum->create();
		$value      = 'zeros' === $format ? '00' . $forum : ' ' . $forum . ' ';
		$this->size = 1;
		update_post_meta( $forum, '_bbp_old_forum_id', 'legacy-forum' );
		if ( $marked ) {
			update_post_meta( $forum, '_bbp_group_ids', array( 7 ) );
		}
		$db->insert( $this->groups_table, array( 'id' => 7, 'status' => 'public' ) );
		$db->insert( $this->groupmeta_table, array( 'group_id' => 7, 'meta_key' => 'forum_id', 'meta_value' => $value ) );
		for ( $i = 0; $i < 100; ++$i ) {
			$before  = $this->snapshot();
			$preview = bbp_admin_upgrade_group_forum_relationships_dry_run();
			$this->assertSame( 0, $preview[0] );
			$this->assertSame( $before, $this->snapshot() );
			$result = bbp_admin_upgrade_group_forum_relationships();
			$this->assertSame( 0, $result[0] );
			if ( $result['complete'] ) {
				break;
			}
		}
		$this->assertTrue( $result['complete'] );
		$this->assertSame( array( 7 ), get_post_meta( $forum, '_bbp_group_ids', true ) );
		$this->assertSame( $value, $db->get_var( "SELECT meta_value FROM `{$this->groupmeta_table}` WHERE group_id = 7" ) );
		$this->size = 1000;
		$this->assertTrue( bbp_admin_upgrade_group_forum_relationships()['complete'] );
		$this->assertSame( $value, $db->get_var( "SELECT meta_value FROM `{$this->groupmeta_table}` WHERE group_id = 7" ) );
	}

	public function test_global_preflight_still_precedes_every_relationship_write() {
		$forums     = $this->fixture();
		$this->size = 1;
		$db         = bbp_db();
		$db->insert( $this->groupmeta_table, array( 'group_id' => 99, 'meta_key' => 'forum_id', 'meta_value' => 'legacy-1' ) );
		for ( $i = 0; $i < 100; ++$i ) {
			$result = $this->run_batch();
			$this->assertSame( array(), get_post_meta( $forums[0], '_bbp_group_ids', false ) );
			$this->assertSame( array( 'legacy-0', 'legacy-1', 'legacy-1' ), $db->get_col( "SELECT meta_value FROM {$this->groupmeta_table} ORDER BY group_id" ) );
			if ( $result['code'] ) {
				break;
			}
		}
		$this->assertSame( 7, $result['code'] );
		$this->assertFalse( $result['complete'] );
	}

	public function test_saved_plan_does_not_overwrite_changed_forum_markers() {
		$forums     = $this->fixture();
		$this->size = 1;
		$this->until_phase( 'apply' );
		update_post_meta( $forums[0], '_bbp_group_ids', array( 99 ) );
		$result = $this->run_batch();
		$this->assertSame( 12, $result['code'] );
		$this->assertSame( array( 99 ), get_post_meta( $forums[0], '_bbp_group_ids', true ) );
		$this->assertSame( 'legacy-0', bbp_db()->get_var( "SELECT meta_value FROM {$this->groupmeta_table} WHERE group_id = 7" ) );
	}

	public function test_lost_checkpoint_replays_overlapping_mappings_without_reassigning_groups() {
		$forums     = $this->fixture( true );
		$this->size = 1;
		$this->until_phase( 'apply' );
		$reject = static function ( $value, $old ) { return $old; };
		add_filter( 'pre_update_option_' . $this->checkpoint(), $reject, 10, 2 );
		try {
			$this->assertSame( 11, $this->run_batch()['code'] );
		} finally {
			remove_filter( 'pre_update_option_' . $this->checkpoint(), $reject, 10 );
		}
		$this->assertSame( array( 7 ), get_post_meta( $forums[0], '_bbp_group_ids', true ) );
		$this->size = 1000;
		$this->assertTrue( $this->run_batch()['complete'] );
		$this->assertSame( array( 7 ), get_post_meta( $forums[0], '_bbp_group_ids', true ) );
		$this->assertSame( array( 8 ), get_post_meta( $forums[1], '_bbp_group_ids', true ) );
	}

	public function test_abandoned_plan_cleanup_is_bounded_and_scoped() {
		$this->fixture();
		$this->size = 1;
		$prefix     = $this->checkpoint() . '_groups_';
		for ( $i = 0; $i < 8; ++$i ) {
			add_option( $prefix . 'abandoned_' . $i, array( 'old' => true ), '', false );
		}
		add_option( '_bbp_tool_batch_other_groups_keep', true, '', false );
		$db    = bbp_db();
		$count = static function () use ( $db, $prefix ) {
			return (int) $db->get_var( $db->prepare( "SELECT COUNT(*) FROM {$db->options} WHERE option_name LIKE %s", $db->esc_like( $prefix ) . '%' ) );
		};
		for ( $i = 8; $i > 0; --$i ) {
			$this->assertSame( $i, $count() );
			$this->assertSame( 0, $this->run_batch()['code'] );
			$this->assertSame( $i - 1, $count() );
		}
		$this->assertNotFalse( get_option( '_bbp_tool_batch_other_groups_keep' ) );
	}

	public function test_explicit_group_marker_takes_precedence_over_many_other_candidates() {
		$id = $this->factory->forum->create();
		update_post_meta( $id, '_bbp_old_forum_id', '42' );
		update_post_meta( $id, '_bbp_group_ids', array( 99 ) );
		$db = bbp_db();
		foreach ( array( 7, 8, 99 ) as $group ) {
			$db->insert( $this->groups_table, array( 'id' => $group, 'status' => 'public' ) );
			$db->insert( $this->groupmeta_table, array( 'group_id' => $group, 'meta_key' => 'forum_id', 'meta_value' => '42' ) );
		}
		$this->assertSame( 0, $this->run_batch()['code'] );
		$this->assertSame( array( 99 ), get_post_meta( $id, '_bbp_group_ids', true ) );
		$this->assertSame( '42', $db->get_var( "SELECT meta_value FROM {$this->groupmeta_table} WHERE group_id = 7" ) );
	}

	public function test_legacy_role_write_failure_is_verified_and_can_be_retried() {
		$this->fixture();
		add_role( 'member', 'Member' );
		$reject = static function ( $value, $old ) { return $old; };
		$hook   = 'pre_update_option_' . wp_roles()->role_key;
		add_filter( $hook, $reject, 10, 2 );
		try {
			$this->assertSame( 13, $this->run_batch()['code'] );
			$this->assertNotNull( get_role( 'member' ) );
		} finally {
			remove_filter( $hook, $reject, 10 );
		}
		$this->assertTrue( $this->run_batch()['complete'] );
		$this->assertNull( get_role( 'member' ) );
	}

	public function test_small_site_runner_finishes_every_phase_and_retains_progress_at_its_limit() {
		$forums     = $this->fixture();
		$this->size = 1;
		add_filter( 'bbp_admin_repair_tool_automatic_batch_limit', '__return_true' );
		try {
			$this->assertFalse( bbp_admin_repair_tool_run_automatically( 'bbp-group-forums', 'bbp_admin_upgrade_group_forum_relationships' ) );
			$this->assertContains( 'bbp-group-forums', bbp_get_pending_upgrades() );
			$this->assertNotFalse( get_option( $this->checkpoint() ) );
		} finally {
			remove_filter( 'bbp_admin_repair_tool_automatic_batch_limit', '__return_true' );
		}
		$this->assertTrue( bbp_admin_repair_tool_run_automatically( 'bbp-group-forums', 'bbp_admin_upgrade_group_forum_relationships' ) );
		$this->assertNotContains( 'bbp-group-forums', bbp_get_pending_upgrades() );
		$this->assertSame( array( 8 ), get_post_meta( $forums[1], '_bbp_group_ids', true ) );
	}

	public function test_changed_source_is_rechecked_before_planning() {
		$forums     = $this->fixture();
		$this->size = 1;
		$this->until_phase( 'plan' );
		update_post_meta( $forums[0], '_bbp_old_forum_id', 'different' );
		$this->assertSame( 12, $this->run_batch()['code'] );
		$this->assertSame( array(), get_post_meta( $forums[0], '_bbp_group_ids', false ) );
		$this->size = 1000;
		$this->assertTrue( $this->run_batch()['complete'] );
	}

	public function test_missing_preflight_plan_cannot_be_treated_as_an_unrelated_forum() {
		$forums     = $this->fixture();
		$this->size = 1;
		$state      = $this->until_phase( 'apply' );
		$name       = $this->checkpoint() . '_groups_' . $state['token'] . '_forum_' . $forums[0];
		delete_option( $name );
		$this->assertSame( 12, $this->run_batch()['code'] );
		$this->assertSame( array(), get_post_meta( $forums[0], '_bbp_group_ids', false ) );
		$this->size = 1000;
		$this->assertTrue( $this->run_batch()['complete'] );
	}

	public function test_group_write_verification_rejects_false_success_and_clears_native_cache() {
		$forums = $this->fixture();
		$db     = bbp_db();
		$reject = static function ( $sql ) use ( $db ) {
			return 0 === strpos( $sql, "UPDATE `{$db->base_prefix}bp_groups_groupmeta`" ) ? 'SELECT 1' : $sql;
		};
		wp_cache_set( 7, array( 'forum_id' => array( 'legacy-0' ) ), 'group_meta' );
		add_filter( 'query', $reject );
		try {
			$this->assertSame( 5, $this->run_batch()['code'] );
			$this->assertSame( 'legacy-0', $db->get_var( "SELECT meta_value FROM {$this->groupmeta_table} WHERE group_id = 7" ) );
			$this->assertFalse( wp_cache_get( 7, 'group_meta' ) );
		} finally {
			remove_filter( 'query', $reject );
		}
		$this->assertTrue( $this->run_batch()['complete'] );
		$this->assertSame( array( 7 ), get_post_meta( $forums[0], '_bbp_group_ids', true ) );
	}

	public function test_forum_write_verification_rejects_false_success_and_retains_group_source() {
		$forums = $this->fixture();
		$reject = static function ( $check, $id, $key ) {
			return '_bbp_group_ids' === $key ? true : $check;
		};
		add_filter( 'update_post_metadata', $reject, 10, 3 );
		try {
			$this->assertSame( 8, $this->run_batch()['code'] );
			$this->assertSame( 'legacy-0', bbp_db()->get_var( "SELECT meta_value FROM {$this->groupmeta_table} WHERE group_id = 7" ) );
			$this->assertSame( array(), get_post_meta( $forums[0], '_bbp_group_ids', false ) );
		} finally {
			remove_filter( 'update_post_metadata', $reject, 10 );
		}
		$this->assertTrue( $this->run_batch()['complete'] );
	}

	public function test_failed_plan_write_never_advances_the_saved_cursor() {
		$this->fixture();
		$this->size = 1;
		$before     = $this->until_phase( 'index' );
		$reject     = static function ( $value, $name ) {
			return false !== strpos( $name, '_groups_' ) ? false : $value;
		};
		add_filter( 'pre_add_option', $reject, 10, 2 );
		// pre_update_option also handles an option that did not previously exist.
		$reject_update = static function ( $value, $name, $old ) {
			return false !== strpos( $name, '_groups_' ) ? $old : $value;
		};
		add_filter( 'pre_update_option', $reject_update, 10, 3 );
		try {
			$this->assertSame( 11, $this->run_batch()['code'] );
			$this->assertSame( $before, get_option( $this->checkpoint() ) );
		} finally {
			remove_filter( 'pre_add_option', $reject, 10 );
			remove_filter( 'pre_update_option', $reject_update, 10 );
		}
		$this->size = 1000;
		$this->assertTrue( $this->run_batch()['complete'] );
	}

	public function test_serialized_objects_in_modern_group_mappings_are_left_unchanged() {
		$id = $this->factory->forum->create();
		update_post_meta( $id, '_bbp_old_forum_id', '42' );
		update_post_meta( $id, '_bbp_group_ids', array( 7 ) );
		$value = serialize( (object) array( 'forum' => $id ) );
		bbp_db()->insert( $this->groupmeta_table, array( 'group_id' => 7, 'meta_key' => 'forum_id', 'meta_value' => $value ) );
		$this->invoke_without_errors( function () { $this->assertTrue( $this->run_batch()['complete'] ); } );
		$this->assertSame( $value, bbp_db()->get_var( "SELECT meta_value FROM {$this->groupmeta_table} WHERE group_id = 7" ) );
	}

	public function test_malformed_forum_metadata_fails_without_parser_warnings_or_group_writes() {
		$forums = $this->fixture();
		update_post_meta( $forums[0], '_bbp_group_ids', array( new stdClass() ) );
		$result = $this->invoke_without_errors( function () { return $this->run_batch(); } );
		$this->assertSame( 12, $result['code'] );
		$this->assertSame( 'legacy-0', bbp_db()->get_var( "SELECT meta_value FROM {$this->groupmeta_table} WHERE group_id = 7" ) );
	}

	public function test_mixed_legacy_and_modern_group_values_fail_during_preflight() {
		$forums = $this->fixture();
		bbp_db()->insert( $this->groupmeta_table, array( 'group_id' => 8, 'meta_key' => 'forum_id', 'meta_value' => serialize( array( 99, 100 ) ) ) );
		$this->assertSame( 7, $this->run_batch()['code'] );
		$this->assertSame( array(), get_post_meta( $forums[0], '_bbp_group_ids', false ) );
	}

	public function test_failed_bounds_read_does_not_publish_a_checkpoint_and_retry_migrates_real_data() {
		$forums = $this->fixture();
		$db     = bbp_db();
		$reject = static function ( $sql ) use ( $db ) {
			return false !== strpos( $sql, "SELECT MAX(ID) FROM {$db->posts} WHERE post_type" ) ? 'SELECT MAX(ID) FROM bbp_missing_upgrade_source' : $sql;
		};
		$suppressed = $db->suppress_errors( true );
		add_filter( 'query', $reject );
		try {
			$this->assertSame( 2, $this->run_batch()['code'] );
			$this->assertFalse( get_option( $this->checkpoint(), false ) );
		} finally {
			remove_filter( 'query', $reject );
			$db->suppress_errors( $suppressed );
		}
		$this->assertTrue( $this->run_batch()['complete'] );
		$this->assertSame( array( 7 ), get_post_meta( $forums[0], '_bbp_group_ids', true ) );
	}

	public function test_new_distinct_forum_field_after_preflight_is_preserved() {
		$forums = $this->fixture();
		update_post_meta( $forums[0], '_bbp_group_ids', array( 99 ) );
		$this->size = 1;
		$this->until_phase( 'apply' );
		add_post_meta( $forums[0], '_bbp_group_ids', array( 8, 9 ) );
		$this->assertSame( 7, $this->run_batch()['code'] );
		$this->assertSame( array( array( 99 ), array( 8, 9 ) ), get_post_meta( $forums[0], '_bbp_group_ids', false ) );
		$this->assertSame( 'legacy-0', bbp_db()->get_var( "SELECT meta_value FROM {$this->groupmeta_table} WHERE group_id = 7" ) );
	}

	public function test_new_modern_group_field_after_preflight_prevents_relationship_publication() {
		$forums     = $this->fixture();
		$this->size = 1;
		$this->until_phase( 'apply' );
		bbp_db()->insert( $this->groupmeta_table, array( 'group_id' => 7, 'meta_key' => 'forum_id', 'meta_value' => serialize( array( 222, 333 ) ) ) );
		$this->assertSame( 7, $this->run_batch()['code'] );
		$this->assertSame( array(), get_post_meta( $forums[0], '_bbp_group_ids', false ) );
		$this->assertSame( array( 'legacy-0', serialize( array( 222, 333 ) ) ), bbp_db()->get_col( "SELECT meta_value FROM {$this->groupmeta_table} WHERE group_id = 7" ) );
	}

	public function candidate_changes() {
		return array( array( 'behind' ), array( 'verify' ), array( 'deleted' ) );
	}

	/** @dataProvider candidate_changes */
	public function test_group_source_changes_are_checked_before_publication( $when ) {
		$forums     = $this->fixture();
		$this->size = 1;
		if ( 'behind' === $when ) {
			$this->until_phase( 'plan' );
			$this->assertSame( 0, $this->run_batch()['code'] );
		} else {
			$this->until_phase( 'deleted' === $when ? 'apply' : 'verify' );
		}
		$db = bbp_db();
		if ( 'deleted' === $when ) {
			$db->delete( $this->groups_table, array( 'id' => 7 ) );
		} else {
			$db->insert( $this->groups_table, array( 'id' => 6, 'status' => 'public' ) );
			$db->insert( $this->groupmeta_table, array( 'group_id' => 6, 'meta_key' => 'forum_id', 'meta_value' => 'legacy-0' ) );
		}
		for ( $i = 0; $i < 100; ++$i ) {
			$result = $this->run_batch();
			if ( $result['code'] ) {
				break;
			}
			$this->assertFalse( $result['complete'] );
		}
		$this->assertSame( 'deleted' === $when ? 12 : 7, $result['code'] );
		$this->assertSame( array(), get_post_meta( $forums[0], '_bbp_group_ids', false ) );
	}

	public function duplicate_fields() {
		return array( array( 'legacy', false ), array( 'legacy', true ), array( 'groups', false ), array( 'groups', true ), array( 'group', false ), array( 'group', true ) );
	}

	/** @dataProvider duplicate_fields */
	public function test_duplicate_relationship_fields_are_rejected_before_writes( $field, $identical ) {
		$forums = $this->fixture();
		if ( 'legacy' === $field ) {
			add_post_meta( $forums[0], '_bbp_old_forum_id', $identical ? 'legacy-0' : 'legacy-other' );
		} elseif ( 'groups' === $field ) {
			update_post_meta( $forums[0], '_bbp_group_ids', array( 7 ) );
			add_post_meta( $forums[0], '_bbp_group_ids', $identical ? array( 7 ) : array( 7, 9 ) );
		} else {
			bbp_db()->insert( $this->groupmeta_table, array( 'group_id' => 7, 'meta_key' => 'forum_id', 'meta_value' => $identical ? 'legacy-0' : (string) $forums[0] ) );
		}
		$before = $this->snapshot();
		$this->assertSame( 7, $this->run_batch( true )['code'] );
		$this->assertSame( $before, $this->snapshot() );
		$this->assertSame( 7, $this->run_batch()['code'] );
		$after = $this->snapshot();
		foreach ( array( 0, 1, 3 ) as $key ) {
			$this->assertSame( $before[ $key ], $after[ $key ] );
		}
	}

	public function malformed_group_members() {
		return array( array( array( true ) ), array( 1.5 ), array( 'nonsense' ), array( array( false ) ), array( array( 1.5 ) ), array( array( '7x' ) ) );
	}

	/** @dataProvider malformed_group_members */
	public function test_malformed_group_list_members_do_not_become_integer_relationships( $value ) {
		$forums = $this->fixture();
		update_post_meta( $forums[0], '_bbp_group_ids', $value );
		$stored = get_post_meta( $forums[0], '_bbp_group_ids', true );
		$this->assertSame( 12, $this->run_batch()['code'] );
		$this->assertSame( $stored, get_post_meta( $forums[0], '_bbp_group_ids', true ) );
	}

	public function test_option_only_visibility_repair_does_not_emit_a_status_transition() {
		$forums = $this->fixture();
		update_option( '_bbp_hidden_forums', array( $forums[0] ) );
		$transitions = array();
		$capture     = static function ( $new, $old, $post ) use ( $forums, &$transitions ) {
			if ( $forums[0] === $post->ID ) {
				$transitions[] = array( $new, $old );
			}
		};
		add_action( 'transition_post_status', $capture, 10, 3 );
		try {
			$this->assertTrue( $this->run_batch()['complete'] );
		} finally {
			remove_action( 'transition_post_status', $capture, 10 );
		}
		$this->assertSame( array(), $transitions );
		$this->assertNotContains( $forums[0], get_option( '_bbp_hidden_forums' ) );
	}

	public function test_option_filter_cannot_mask_a_missing_visibility_write() {
		$forums = $this->fixture();
		bbp_db()->update( $this->groups_table, array( 'status' => 'private' ), array( 'id' => 7 ) );
		$mask = static function () use ( $forums ) { return array( $forums[0] ); };
		add_filter( 'pre_option__bbp_hidden_forums', $mask );
		try {
			$this->assertSame( 9, $this->run_batch()['code'] );
		} finally {
			remove_filter( 'pre_option__bbp_hidden_forums', $mask );
		}
		$this->assertTrue( $this->run_batch()['complete'] );
		$this->assertContains( $forums[0], get_option( '_bbp_hidden_forums' ) );
	}

	public function test_duplicate_root_candidates_fail_global_preflight() {
		$forums = $this->fixture();
		foreach ( $forums as $id ) {
			update_post_meta( $id, '_bbp_old_forum_id', 1 );
		}
		$this->assertSame( 7, $this->run_batch()['code'] );
		$this->assertSame( array(), get_post_meta( $forums[0], '_bbp_group_ids', false ) );
		$this->assertFalse( get_option( '_bbp_group_forums_root_id', false ) );
	}

	public function test_root_rename_noop_is_detected_before_option_or_role_changes_and_can_retry() {
		$id = $this->factory->forum->create( array( 'post_title' => 'Default Forum' ) );
		update_post_meta( $id, '_bbp_old_forum_id', 1 );
		add_role( 'member', 'Member' );
		$reject = static function ( $data, $postarr ) use ( $id ) {
			if ( isset( $postarr['ID'] ) && $id === $postarr['ID'] ) {
				$data['post_title'] = 'Default Forum';
			}
			return $data;
		};
		add_filter( 'wp_insert_post_data', $reject, 10, 2 );
		try {
			$this->assertSame( 6, $this->run_batch()['code'] );
			$this->assertFalse( get_option( '_bbp_group_forums_root_id', false ) );
			$this->assertNotNull( get_role( 'member' ) );
		} finally {
			remove_filter( 'wp_insert_post_data', $reject, 10 );
		}
		$this->assertTrue( $this->run_batch()['complete'] );
		$this->assertSame( 'Group Forums', get_post_field( 'post_title', $id ) );
		$this->assertNull( get_role( 'member' ) );
	}

	public function test_wordpress_unique_root_slug_is_accepted_and_verified() {
		$this->factory->forum->create( array( 'post_name' => 'group-forums' ) );
		$id = $this->factory->forum->create( array( 'post_title' => 'Default Forum' ) );
		update_post_meta( $id, '_bbp_old_forum_id', 1 );
		$this->assertTrue( $this->run_batch()['complete'] );
		$this->assertSame( 'group-forums-2', get_post_field( 'post_name', $id ) );
		$this->assertSame( $id, get_option( '_bbp_group_forums_root_id' ) );
	}

	public function test_native_forum_group_fields_are_outside_the_legacy_migration() {
		$this->fixture();
		$native = $this->factory->forum->create();
		add_post_meta( $native, '_bbp_group_ids', array( 7, 9 ) );
		add_post_meta( $native, '_bbp_group_ids', new stdClass() );
		$db     = bbp_db();
		$before = $db->get_results( $db->prepare( "SELECT meta_key, meta_value FROM {$db->postmeta} WHERE post_id = %d ORDER BY meta_id", $native ), ARRAY_A );
		$this->assertTrue( $this->run_batch()['complete'] );
		$this->assertSame( $before, $db->get_results( $db->prepare( "SELECT meta_key, meta_value FROM {$db->postmeta} WHERE post_id = %d ORDER BY meta_id", $native ), ARRAY_A ) );
	}

	public function test_root_edit_after_preflight_is_preserved_before_the_first_rename_attempt() {
		$id = $this->factory->forum->create( array( 'post_title' => 'Default Forum' ) );
		update_post_meta( $id, '_bbp_old_forum_id', 1 );
		$this->size = 1;
		$this->until_phase( 'root' );
		wp_update_post( array( 'ID' => $id, 'post_title' => 'Custom group root' ) );
		$this->assertSame( 12, $this->run_batch()['code'] );
		$this->assertSame( 'Custom group root', get_post_field( 'post_title', $id ) );
		$this->size = 1000;
		$this->assertTrue( $this->run_batch()['complete'] );
		$this->assertSame( 'Custom group root', get_post_field( 'post_title', $id ) );
	}

	public function test_transformed_root_slug_fails_verification_and_retries_the_saved_intent() {
		$id = $this->factory->forum->create( array( 'post_title' => 'Default Forum' ) );
		update_post_meta( $id, '_bbp_old_forum_id', 1 );
		$transform = static function ( $data, $postarr ) use ( $id ) {
			if ( isset( $postarr['ID'] ) && $id === $postarr['ID'] ) {
				$data['post_name'] = 'unexpected-root-slug';
			}
			return $data;
		};
		add_filter( 'wp_insert_post_data', $transform, 10, 2 );
		try {
			$this->assertSame( 6, $this->run_batch()['code'] );
			$this->assertFalse( get_option( '_bbp_group_forums_root_id', false ) );
		} finally {
			remove_filter( 'wp_insert_post_data', $transform, 10 );
		}
		$this->assertTrue( $this->run_batch()['complete'] );
		$this->assertSame( 'group-forums', get_post_field( 'post_name', $id ) );
	}

	public function test_root_retry_preserves_its_already_verified_unique_slug() {
		$other = $this->factory->forum->create( array( 'post_name' => 'group-forums' ) );
		$id    = $this->factory->forum->create( array( 'post_title' => 'Default Forum' ) );
		update_post_meta( $id, '_bbp_old_forum_id', 1 );
		$reject = static function ( $value, $old ) { return $old; };
		add_filter( 'pre_update_option__bbp_group_forums_root_id', $reject, 10, 2 );
		try {
			$this->assertSame( 10, $this->run_batch()['code'] );
			$this->assertSame( 'group-forums-2', get_post_field( 'post_name', $id ) );
		} finally {
			remove_filter( 'pre_update_option__bbp_group_forums_root_id', $reject, 10 );
		}
		wp_delete_post( $other, true );
		$result = $this->run_batch();
		$this->assertTrue( $result['complete'] );
		$this->assertSame( 0, $result['rename'] );
		$this->assertSame( 'group-forums-2', get_post_field( 'post_name', $id ) );
	}

}
