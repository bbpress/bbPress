<?php

/**
 * @group users
 * @group counts
 * @group statistics
 */
class BBP_Tests_Users_Functions_Statistics extends BBP_UnitTestCase {

	public $count_queries = 0;

	public function track_count_queries( $sql ) {
		if ( false !== strpos( $sql, 'COUNT(DISTINCT user_id)' ) ) {
			++$this->count_queries;
		}
		return $sql;
	}

	public function test_forum_count_cache_handles_zero_and_capability_metadata_changes() {
		global $wpdb;
		$user = $this->factory->user->create();
		$key  = $wpdb->get_blog_prefix() . 'capabilities';
		delete_user_meta( $user, $key );
		$before = bbp_get_total_forum_users();
		bbp_clean_user_count_cache();
		$this->count_queries = 0;
		add_filter( 'query', array( $this, 'track_count_queries' ) );

		$this->assertSame( $before, bbp_get_total_forum_users() );
		$this->assertSame( $before, bbp_get_total_forum_users() );
		$this->assertSame( 1, $this->count_queries );
		update_user_meta( $user, 'description', 'Unrelated metadata' );
		$this->assertSame( $before, bbp_get_total_forum_users() );
		$this->assertSame( 1, $this->count_queries );

		add_user_meta( $user, $key, array( bbp_get_participant_role() => true ) );
		$this->assertSame( $before + 1, bbp_get_total_forum_users() );
		$this->assertSame( 2, $this->count_queries );
		update_user_meta( $user, $key, array( 'subscriber' => true ) );
		$this->assertSame( $before, bbp_get_total_forum_users() );
		$this->assertSame( 3, $this->count_queries );
		delete_user_meta( $user, $key );
		$this->assertSame( $before, bbp_get_total_forum_users() );
		$this->assertSame( 4, $this->count_queries );
		remove_filter( 'query', array( $this, 'track_count_queries' ) );
	}

	public function test_forum_count_deduplicates_capability_rows() {
		global $wpdb;
		$before = bbp_get_total_forum_users();
		$user   = $this->factory->user->create( array( 'role' => bbp_get_participant_role() ) );
		add_user_meta( $user, $wpdb->get_blog_prefix() . 'capabilities', array( bbp_get_spectator_role() => true ) );
		$this->assertSame( $before + 1, bbp_get_total_forum_users() );
	}

	public function invalidate_during_count( $sql ) {
		if ( false !== strpos( $sql, 'COUNT(DISTINCT user_id)' ) ) {
			bbp_clean_user_count_cache();
			remove_filter( 'query', array( $this, 'invalidate_during_count' ) );
		}
		return $sql;
	}

	public function test_invalidation_during_a_count_does_not_repopulate_current_generation() {
		bbp_clean_user_count_cache();
		$this->count_queries = 0;
		add_filter( 'query', array( $this, 'track_count_queries' ) );
		add_filter( 'query', array( $this, 'invalidate_during_count' ) );
		$before = bbp_get_total_forum_users();
		$this->assertSame( $before, bbp_get_total_forum_users() );
		$this->assertSame( 2, $this->count_queries );
		remove_filter( 'query', array( $this, 'track_count_queries' ) );
	}

	public function test_forum_count_filter_does_not_replace_cached_value() {
		$before = bbp_get_total_forum_users();
		add_filter( 'bbp_get_total_forum_users', array( $this, 'filtered_count' ) );
		$this->assertSame( 12345, bbp_get_total_forum_users() );
		remove_filter( 'bbp_get_total_forum_users', array( $this, 'filtered_count' ) );
		$this->assertSame( $before, bbp_get_total_forum_users() );
	}

	public function test_forum_count_cache_survives_runtime_cache_reset() {
		if ( ! wp_using_ext_object_cache() ) {
			$this->markTestSkipped( 'Requires a persistent object cache.' );
		}
		$before = bbp_get_total_forum_users();
		wp_cache_init();
		$this->count_queries = 0;
		add_filter( 'query', array( $this, 'track_count_queries' ) );
		$this->assertSame( $before, bbp_get_total_forum_users() );
		$this->assertSame( 0, $this->count_queries );
		remove_filter( 'query', array( $this, 'track_count_queries' ) );
	}

	public function test_capability_changes_for_another_site_invalidate_its_cached_count() {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'Requires multisite.' );
		}
		global $wpdb;
		$user = $this->factory->user->create();
		$site = $this->factory->blog->create();
		switch_to_blog( $site );
		$before = bbp_get_total_forum_users();
		restore_current_blog();
		update_user_meta( $user, $wpdb->get_blog_prefix( $site ) . 'capabilities', array( bbp_get_participant_role() => true ) );
		switch_to_blog( $site );
		$this->assertSame( $before + 1, bbp_get_total_forum_users() );
		restore_current_blog();
	}

	private function user_count( $enabled = true ) {
		$statistics = bbp_get_statistics( array(
			'count_users'   => $enabled,
			'count_forums'  => false,
			'count_topics'  => false,
			'count_replies' => false,
			'count_tags'    => false
		) );

		return $statistics['user_count_int'];
	}

	public function test_statistics_count_forum_roles_once_and_follow_role_changes() {
		$before = $this->user_count();
		$plain  = $this->factory->user->create( array( 'role' => 'subscriber' ) );
		$user   = get_userdata( $plain );

		foreach ( array_keys( bbp_get_dynamic_roles() ) as $role ) {
			$user->remove_role( $role );
		}

		$this->assertSame( $before, $this->user_count() );
		$user->add_role( bbp_get_participant_role() );
		$user->add_role( bbp_get_spectator_role() );
		$this->assertSame( $before + 1, $this->user_count() );
		$user->remove_role( bbp_get_participant_role() );
		$this->assertSame( $before + 1, $this->user_count() );
		$user->remove_role( bbp_get_spectator_role() );
		$this->assertSame( $before, $this->user_count() );
		$user->add_role( bbp_get_blocked_role() );
		$this->assertSame( $before + 1, $this->user_count() );
		wp_delete_user( $plain );
		$this->assertSame( $before, $this->user_count() );
	}

	public function test_statistics_do_not_query_users_when_disabled() {
		bbp_clean_user_count_cache();
		$this->count_queries = 0;
		add_filter( 'query', array( $this, 'track_count_queries' ) );
		$this->assertSame( 0, $this->user_count( false ) );
		$this->assertSame( 0, $this->count_queries );
		remove_filter( 'query', array( $this, 'track_count_queries' ) );
	}

	public function test_statistics_with_no_forum_roles_do_not_count_all_users() {
		$this->factory->user->create( array( 'role' => bbp_get_participant_role() ) );
		$this->assertGreaterThan( 0, $this->user_count() );
		add_filter( 'bbp_get_dynamic_roles', '__return_empty_array' );
		$this->assertSame( 0, $this->user_count() );
		remove_filter( 'bbp_get_dynamic_roles', '__return_empty_array' );
	}

	public function test_statistics_include_filtered_forum_roles() {
		$before = $this->user_count();
		add_role( 'bbp_test_member', 'Test member', array( 'read' => true ) );
		$user = get_userdata( $this->factory->user->create( array( 'role' => 'bbp_test_member' ) ) );
		foreach ( array_keys( bbp_get_dynamic_roles() ) as $role ) {
			$user->remove_role( $role );
		}
		$this->assertSame( $before, $this->user_count() );
		add_filter( 'bbp_get_dynamic_roles', array( $this, 'custom_forum_role' ) );
		$this->assertSame( $before + 1, $this->user_count() );
		remove_filter( 'bbp_get_dynamic_roles', array( $this, 'custom_forum_role' ) );
		remove_role( 'bbp_test_member' );
	}

	public function custom_forum_role( $roles ) {
		$roles['bbp_test_member'] = array( 'name' => 'Test member', 'capabilities' => array( 'read' => true ) );
		return $roles;
	}

	public function test_statistics_are_separate_from_filtered_installation_count() {
		$before = $this->user_count();
		add_filter( 'bbp_get_total_users', array( $this, 'filtered_count' ) );
		$this->assertSame( 12345, bbp_get_total_users() );
		$this->assertSame( $before, $this->user_count() );
		remove_filter( 'bbp_get_total_users', array( $this, 'filtered_count' ) );
	}

	public function filtered_count() {
		return 12345;
	}

	public function test_installation_count_uses_core_cached_count() {
		update_network_option( null, 'user_count', 10000 );
		$this->assertSame( 10000, bbp_get_total_users() );
		$this->assertFalse( bbp_is_large_install() );
		update_network_option( null, 'user_count', 10001 );
		$this->assertSame( 10001, bbp_get_total_users() );
		$this->assertTrue( bbp_is_large_install() );
	}

	public function test_large_install_preserves_core_and_bbpress_filters() {
		add_filter( 'wp_is_large_user_count', '__return_true' );
		$this->assertTrue( bbp_is_large_install() );
		add_filter( 'bbp_is_large_install', '__return_false' );
		$this->assertFalse( bbp_is_large_install() );
		remove_filter( 'bbp_is_large_install', '__return_false' );
		remove_filter( 'wp_is_large_user_count', '__return_true' );

		if ( is_multisite() ) {
			add_filter( 'wp_is_large_network', '__return_true' );
			$this->assertTrue( bbp_is_large_install() );
			remove_filter( 'wp_is_large_network', '__return_true' );
		}
	}

	public function test_statistics_are_scoped_to_current_site() {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'Requires multisite.' );
		}

		$before = $this->user_count();
		$site   = $this->factory->blog->create();
		switch_to_blog( $site );
		$other = $this->user_count();
		$this->factory->user->create( array( 'role' => bbp_get_participant_role() ) );
		$this->assertSame( $other + 1, $this->user_count() );
		restore_current_blog();
		$this->assertSame( $before, $this->user_count() );
	}
}
