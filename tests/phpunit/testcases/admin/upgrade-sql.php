<?php

/**
 * Tests for database value handling in admin upgrades.
 *
 * @group admin
 * @group tools
 */
class BBP_Tests_Admin_Upgrade_SQL extends BBP_UnitTestCase {

	private $groups_table;
	private $groupmeta_table;

	public function setUp(): void {
		parent::setUp();
		global $wpdb;

		$this->groups_table    = $wpdb->base_prefix . 'bp_groups';
		$this->groupmeta_table = $wpdb->base_prefix . 'bp_groups_groupmeta';
		$wpdb->query( "CREATE TEMPORARY TABLE `{$this->groups_table}` ( `id` bigint(20) unsigned NOT NULL, `status` varchar(20) NOT NULL, PRIMARY KEY (`id`) )" );
		$wpdb->query( "CREATE TEMPORARY TABLE `{$this->groupmeta_table}` ( `group_id` bigint(20) unsigned NOT NULL, `meta_key` varchar(100) NOT NULL, `meta_value` longtext NOT NULL )" );

		require_once BBP_PLUGIN_DIR . 'includes/admin/tools/upgrade.php';
	}

	public function tearDown(): void {
		global $wpdb;
		$wpdb->query( "DROP TEMPORARY TABLE IF EXISTS `{$this->groupmeta_table}`" );
		$wpdb->query( "DROP TEMPORARY TABLE IF EXISTS `{$this->groups_table}`" );

		parent::tearDown();
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
		$this->assertStringContainsString( '1 groups would be updated; 1 forums would be updated; 0 forum statuses would be synced', $dry_run[1] );
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
		$this->assertStringContainsString( '1 forum statuses would be synced', $dry_run[1] );
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
			$this->assertStringContainsString( 'No converted group forums to upgrade.', $dry_run[1] );
			$this->assertNotNull( get_role( 'member' ) );

			$result = bbp_admin_upgrade_group_forum_relationships();
			$this->assertSame( 1, $result[0] );
			$this->assertStringContainsString( 'No converted group forums to upgrade.', $result[1] );
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
			$this->assertStringContainsString( '0 groups updated; 1 forums updated', $result[1] );
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
		$this->assertStringContainsString( '0 groups would be updated; 0 forums would be updated; 0 forum statuses would be synced', $dry_run[1] );
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
			$this->assertStringContainsString( '1 forum statuses would be synced', $dry_run[1] );

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
		$this->assertStringContainsString( '1 forum statuses would be synced', $dry_run[1] );
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
		$this->assertStringContainsString( '1 forum statuses would be synced', $dry_run[1] );
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
		$this->assertStringContainsString( '0 groups would be updated; 1 forums would be updated; 0 forum statuses would be synced', $dry_run[1] );
		$this->assertSame( array(), get_post_meta( $forum_id, '_bbp_group_ids', false ) );

		$result = bbp_admin_upgrade_group_forum_relationships();
		$this->assertSame( 0, $result[0] );
		$this->assertStringContainsString( '0 groups updated; 1 forums updated; 0 forum statuses synced', $result[1] );
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
		$this->assertStringContainsString( '0 groups would be updated; 1 forums would be updated; 1 forum statuses would be synced', $dry_run[1] );

		$this->assertSame( 0, bbp_admin_upgrade_group_forum_relationships()[0] );
		$this->assertSame( array( 7 ), get_post_meta( $forum_id, '_bbp_group_ids', true ) );
		$this->assertSame( bbp_get_hidden_status_id(), bbp_get_forum_visibility( $forum_id ) );

		$dry_run = bbp_admin_upgrade_group_forum_relationships_dry_run();
		$this->assertSame( 0, $dry_run[0] );
		$this->assertStringContainsString( '0 groups would be updated; 0 forums would be updated; 0 forum statuses would be synced', $dry_run[1] );
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
		$this->assertStringContainsString( '0 groups would be updated; 0 forums would be updated; 0 forum statuses would be synced', $dry_run[1] );
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
		$this->assertStringContainsString( '0 groups would be updated; 0 forums would be updated; 0 forum statuses would be synced', $dry_run[1] );
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

		$dry_run = bbp_admin_upgrade_group_forum_relationships_dry_run();
		$this->assertSame( 0, $dry_run[0] );
		$this->assertStringContainsString( '0 groups would be updated; 0 forums would be updated; 0 forum statuses would be synced', $dry_run[1] );
		$this->assertSame( $stored_forum_ids, $wpdb->get_var( "SELECT meta_value FROM `{$this->groupmeta_table}` WHERE group_id = 7" ) );

		$result = bbp_admin_upgrade_group_forum_relationships();
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
		$this->assertStringContainsString( '2 groups would be updated; 2 forums would be updated; 1 forum statuses would be synced', $dry_run[1] );
		$this->assertSame( array( '42', (string) $forum_ids[0] ), $wpdb->get_col( "SELECT meta_value FROM `{$this->groupmeta_table}` ORDER BY group_id" ) );

		$this->assertSame( 0, bbp_admin_upgrade_group_forum_relationships()[0] );
		$this->assertSame( array( (string) $forum_ids[0], (string) $forum_ids[1] ), $wpdb->get_col( "SELECT meta_value FROM `{$this->groupmeta_table}` ORDER BY group_id" ) );
		$this->assertSame( array( 7 ), get_post_meta( $forum_ids[0], '_bbp_group_ids', true ) );
		$this->assertSame( array( 9 ), get_post_meta( $forum_ids[1], '_bbp_group_ids', true ) );
		$this->assertSame( bbp_get_hidden_status_id(), bbp_get_forum_visibility( $forum_ids[1] ) );

		$dry_run = bbp_admin_upgrade_group_forum_relationships_dry_run();
		$this->assertSame( 0, $dry_run[0] );
		$this->assertStringContainsString( '0 groups would be updated; 0 forums would be updated; 0 forum statuses would be synced', $dry_run[1] );
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
		$this->assertStringContainsString( '0 groups would be updated; 0 forums would be updated; 0 forum statuses would be synced', $dry_run[1] );
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
			$this->assertStringContainsString( '1 group root settings would be updated; 1 forums would be renamed; 1 legacy roles would be removed (member)', $dry_run[1] );
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
				$this->assertSame( 999, get_option( '_bbp_group_forums_root_id' ) );
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
}
