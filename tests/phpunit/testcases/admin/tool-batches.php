<?php

/**
 * Tests for built-in tools that use resumable batches.
 *
 * @group tools
 * @group tool-batches
 */
class BBP_Tests_Admin_Tool_Batches extends BBP_UnitTestCase {

	public function setUp(): void {
		parent::setUp();

		if ( ! function_exists( 'bbp_admin' ) ) {
			require_once BBP_PLUGIN_DIR . 'includes/admin/actions.php';
		}
		bbp_admin();
		require_once BBP_PLUGIN_DIR . 'includes/admin/tools/common.php';
		require_once BBP_PLUGIN_DIR . 'includes/admin/tools/repair.php';
		require_once BBP_PLUGIN_DIR . 'includes/admin/tools/upgrade.php';

		add_filter( 'bbp_admin_repair_tool_batch_size', array( $this, 'batch_size' ) );
	}

	public function tearDown(): void {
		remove_filter( 'bbp_admin_repair_tool_batch_size', array( $this, 'batch_size' ) );
		parent::tearDown();
	}

	public function batch_size() {
		return 2;
	}

	public function upgrade_tools() {
		return array(
			'favorites' => array( 'bbp-user-favorites-move', '_bbp_favorites', '_bbp_favorite', 'bbp_admin_upgrade_user_favorites' ),
			'topic subscriptions' => array( 'bbp-user-topic-subscriptions-move', '_bbp_subscriptions', '_bbp_subscription', 'bbp_admin_upgrade_user_topic_subscriptions' ),
			'forum subscriptions' => array( 'bbp-user-forum-subscriptions-move', '_bbp_forum_subscriptions', '_bbp_subscription', 'bbp_admin_upgrade_user_forum_subscriptions' ),
		);
	}

	private function finish( $callback ) {
		for ( $i = 0; $i < 30; ++$i ) {
			$result = bbp_admin_repair_tool_normalize_result( call_user_func( $callback ) );
			$this->assertTrue( $result['success'], $result['message'] );
			if ( $result['complete'] ) {
				return;
			}
		}
		$this->fail( 'The bounded tool did not finish.' );
	}

	private function stored_data() {
		$db     = bbp_db();
		$stored = array();
		foreach ( array( 'postmeta' => 'meta_id', 'usermeta' => 'umeta_id', 'options' => 'option_id' ) as $table => $id ) {
			$rows             = $db->get_results( "SELECT * FROM {$db->$table} ORDER BY {$id}", ARRAY_A );
			$stored[ $table ] = hash( 'sha256', serialize( $rows ) );
		}
		return $stored;
	}

	/**
	 * @dataProvider upgrade_tools
	 */
	public function test_large_user_lists_are_bounded_and_previews_do_not_advance_them( $tool_id, $old_key, $new_key, $callback ) {
		$db      = bbp_db();
		$user_id = $this->factory->user->create();
		$posts   = $this->factory->post->create_many( 5 );
		$option  = bbp_admin_repair_tool_batch_option_name( $tool_id );

		add_user_meta( $user_id, $db->prefix . $old_key, implode( ',', $posts ) . ',invalid,' . $posts[0] );
		add_user_meta( $user_id, $db->prefix . $old_key, implode( ',', $posts ) );
		bbp_add_pending_upgrade( $tool_id );
		$before  = $this->stored_data();
		$preview = call_user_func( $callback . '_dry_run' );
		$this->assertFalse( $preview['complete'] );
		$this->assertStringContainsString( 'in this batch', $preview[1] );
		$this->assertSame( $before, $this->stored_data() );

		$result = call_user_func( $callback );
		$this->assertFalse( $result['complete'] );
		$this->assertSame( 2, get_option( $option )['offset'] );
		$this->assertSame( array(), get_post_meta( $posts[2], $new_key, false ) );

		$before = $this->stored_data();
		call_user_func( $callback . '_dry_run' );
		$this->assertSame( $before, $this->stored_data() );

		$previous_count = 2;
		for ( $i = 0; $i < 10; ++$i ) {
			$result = call_user_func( $callback );
			$count  = (int) $db->get_var( $db->prepare( "SELECT COUNT(*) FROM {$db->postmeta} WHERE meta_key = %s AND meta_value = %d", $new_key, $user_id ) );
			$this->assertLessThanOrEqual( 2, $count - $previous_count );
			$previous_count = $count;
			if ( $result['complete'] ) {
				break;
			}
		}
		$this->assertTrue( $result['complete'] );
		$this->assertFalse( get_option( $option, false ) );
		foreach ( $posts as $post_id ) {
			$this->assertSame( array( (string) $user_id ), get_post_meta( $post_id, $new_key, false ) );
		}

		// A complete rerun remains harmless even with duplicate source rows
		$this->finish( $callback );
		$this->assertSame( 5, (int) $db->get_var( $db->prepare( "SELECT COUNT(*) FROM {$db->postmeta} WHERE meta_key = %s AND meta_value = %d", $new_key, $user_id ) ) );
	}

	/**
	 * @dataProvider upgrade_tools
	 */
	public function test_empty_source_rows_consume_the_batch_budget( $tool_id, $old_key, $new_key, $callback ) {
		$user_id = $this->factory->user->create();
		$post_id = $this->factory->post->create();
		$key     = bbp_db()->prefix . $old_key;
		for ( $i = 0; $i < 3; ++$i ) {
			add_user_meta( $user_id, $key, 'invalid,0' );
		}
		add_user_meta( $user_id, $key, $post_id );
		$this->assertFalse( call_user_func( $callback )['complete'] );
		$this->assertSame( array(), get_post_meta( $post_id, $new_key, false ) );
		$this->finish( $callback );
		$this->assertSame( array( (string) $user_id ), get_post_meta( $post_id, $new_key, false ) );
	}

	/**
	 * @dataProvider upgrade_tools
	 */
	public function test_new_source_rows_wait_for_the_next_run( $tool_id, $old_key, $new_key, $callback ) {
		$user_id = $this->factory->user->create();
		$posts   = $this->factory->post->create_many( 4 );
		$key     = bbp_db()->prefix . $old_key;
		add_user_meta( $user_id, $key, implode( ',', array_slice( $posts, 0, 3 ) ) );
		$this->assertFalse( call_user_func( $callback )['complete'] );
		add_user_meta( $user_id, $key, $posts[3] );

		$this->finish( $callback );
		$this->assertSame( array(), get_post_meta( $posts[3], $new_key, false ) );
		$this->finish( $callback );
		$this->assertSame( array( (string) $user_id ), get_post_meta( $posts[3], $new_key, false ) );
	}

	/**
	 * @dataProvider upgrade_tools
	 */
	public function test_changed_source_lists_restart_the_current_row( $tool_id, $old_key, $new_key, $callback ) {
		$user_id = $this->factory->user->create();
		$posts   = $this->factory->post->create_many( 5 );
		$key     = bbp_db()->prefix . $old_key;
		add_user_meta( $user_id, $key, implode( ',', $posts ) );
		call_user_func( $callback );
		update_user_meta( $user_id, $key, implode( ',', array_reverse( $posts ) ) );

		$this->finish( $callback );
		foreach ( $posts as $post_id ) {
			$this->assertSame( array( (string) $user_id ), get_post_meta( $post_id, $new_key, false ) );
		}
	}

	/**
	 * @dataProvider upgrade_tools
	 */
	public function test_legacy_cleanup_cannot_remove_an_incomplete_migrations_source( $tool_id, $old_key, $new_key, $callback ) {
		$user_id = $this->factory->user->create();
		$posts   = $this->factory->post->create_many( 5 );
		$key     = bbp_db()->prefix . $old_key;
		$cleanup = str_replace( 'bbp_admin_upgrade_user_', 'bbp_admin_upgrade_remove_', $callback ) . '_from_usermeta';
		add_user_meta( $user_id, $key, implode( ',', $posts ) );
		$this->assertFalse( call_user_func( $callback )['complete'] );
		$before = $this->stored_data();
		$preview = bbp_admin_repair_tool_normalize_result( call_user_func( $cleanup . '_dry_run' ) );
		$result  = bbp_admin_repair_tool_normalize_result( call_user_func( $cleanup ) );
		$this->assertSame( $before, $this->stored_data() );
		$this->assertFalse( $preview['success'] );
		$this->assertFalse( $result['success'] );

		$this->finish( $callback );
		$this->assertSame( 0, call_user_func( $cleanup )[0] );
		$this->assertSame( array(), get_user_meta( $user_id, $key, false ) );
		foreach ( $posts as $post_id ) {
			$this->assertSame( array( (string) $user_id ), get_post_meta( $post_id, $new_key, false ) );
		}
	}

	/**
	 * @dataProvider upgrade_tools
	 */
	public function test_failed_relationship_writes_can_be_retried_without_duplicates( $tool_id, $old_key, $new_key, $callback ) {
		$user_id = $this->factory->user->create();
		$posts   = $this->factory->post->create_many( 4 );
		$option  = bbp_admin_repair_tool_batch_option_name( $tool_id );
		add_user_meta( $user_id, bbp_db()->prefix . $old_key, implode( ',', $posts ) );
		$fail = static function ( $check, $post_id, $meta_key ) use ( $posts, $new_key ) {
			return ( ( $posts[1] === $post_id ) && ( $new_key === $meta_key ) ) ? false : $check;
		};
		add_filter( 'add_post_metadata', $fail, 10, 3 );
		try {
			$result = call_user_func( $callback );
			$this->assertFalse( $result['success'] );
			$this->assertSame( 0, get_option( $option )['cursor'] );
			$this->assertSame( 0, get_option( $option )['offset'] );
			$this->assertSame( array( (string) $user_id ), get_post_meta( $posts[0], $new_key, false ) );
			$cleanup = str_replace( 'bbp_admin_upgrade_user_', 'bbp_admin_upgrade_remove_', $callback ) . '_from_usermeta';
			$before  = $this->stored_data();
			$this->assertFalse( call_user_func( $cleanup . '_dry_run' )['success'] );
			$this->assertFalse( call_user_func( $cleanup )['success'] );
			$this->assertSame( $before, $this->stored_data() );
		} finally {
			remove_filter( 'add_post_metadata', $fail, 10 );
		}
		$this->finish( $callback );
		foreach ( $posts as $post_id ) {
			$this->assertSame( array( (string) $user_id ), get_post_meta( $post_id, $new_key, false ) );
		}
	}

	/**
	 * @dataProvider upgrade_tools
	 */
	public function test_pending_migrations_block_cleanup_without_a_checkpoint( $tool_id, $old_key, $new_key, $callback ) {
		$user_id = $this->factory->user->create();
		$post_id = $this->factory->post->create();
		$key     = bbp_db()->prefix . $old_key;
		$cleanup = str_replace( 'bbp_admin_upgrade_user_', 'bbp_admin_upgrade_remove_', $callback ) . '_from_usermeta';
		add_user_meta( $user_id, $key, $post_id );
		bbp_add_pending_upgrade( $tool_id );
		$this->assertFalse( get_option( bbp_admin_repair_tool_batch_option_name( $tool_id ), false ) );
		$before = $this->stored_data();
		$this->assertFalse( call_user_func( $cleanup . '_dry_run' )['success'] );
		$this->assertFalse( call_user_func( $cleanup )['success'] );
		$this->assertSame( $before, $this->stored_data() );
		$this->assertTrue( bbp_admin_repair_tool_run_automatically( $tool_id, $callback ) );
		$this->assertSame( 0, call_user_func( $cleanup )[0] );
		$this->assertSame( array( (string) $user_id ), get_post_meta( $post_id, $new_key, false ) );
	}

	/**
	 * @dataProvider upgrade_tools
	 */
	public function test_cleanup_cannot_delete_source_while_its_migration_holds_the_lock( $tool_id, $old_key, $new_key, $callback ) {
		$user_id = $this->factory->user->create();
		$post_id = $this->factory->post->create();
		$key     = bbp_db()->prefix . $old_key;
		$cleanup = str_replace( 'bbp_admin_upgrade_user_', 'bbp_admin_upgrade_remove_', $callback ) . '_from_usermeta';
		add_user_meta( $user_id, $key, $post_id );
		$lock   = bbp_admin_repair_tool_lock( $tool_id );
		$before = $this->stored_data();
		try {
			$this->assertFalse( call_user_func( $cleanup )['success'] );
			$this->assertSame( $before, $this->stored_data() );
			$this->assertSame( $lock['value'], get_option( $lock['name'] ) );
		} finally {
			bbp_admin_repair_tool_unlock( $lock );
		}
		$this->assertTrue( bbp_admin_repair_tool_run_automatically( $tool_id, $callback ) );
		$this->assertSame( 0, call_user_func( $cleanup )[0] );
		$this->assertSame( array( (string) $user_id ), get_post_meta( $post_id, $new_key, false ) );
	}

	/**
	 * @dataProvider upgrade_tools
	 */
	public function test_failed_initial_checkpoint_saves_write_no_relationships_and_keep_cleanup_blocked( $tool_id, $old_key, $new_key, $callback ) {
		$user_id = $this->factory->user->create();
		$post_id = $this->factory->post->create();
		$option  = bbp_admin_repair_tool_batch_option_name( $tool_id );
		$cleanup = str_replace( 'bbp_admin_upgrade_user_', 'bbp_admin_upgrade_remove_', $callback ) . '_from_usermeta';
		add_user_meta( $user_id, bbp_db()->prefix . $old_key, $post_id );
		$fail = static function ( $value, $old_value ) {
			return $old_value;
		};
		add_filter( 'pre_update_option_' . $option, $fail, 10, 2 );
		try {
			$result = call_user_func( $callback );
			$this->assertSame( 5, $result[0] );
			$this->assertFalse( $result['success'] );
			$this->assertSame( array(), get_post_meta( $post_id, $new_key, false ) );
			$this->assertContains( $tool_id, bbp_get_pending_upgrades() );
			$before = $this->stored_data();
			$this->assertFalse( call_user_func( $cleanup . '_dry_run' )['success'] );
			$this->assertFalse( call_user_func( $cleanup )['success'] );
			$this->assertSame( $before, $this->stored_data() );
		} finally {
			remove_filter( 'pre_update_option_' . $option, $fail, 10 );
		}
		$this->assertTrue( bbp_admin_repair_tool_run_automatically( $tool_id, $callback ) );
		$this->assertSame( 0, call_user_func( $cleanup )[0] );
		$this->assertSame( array( (string) $user_id ), get_post_meta( $post_id, $new_key, false ) );
	}

	/**
	 * @dataProvider upgrade_tools
	 */
	public function test_failed_progress_saves_do_not_skip_relationships( $tool_id, $old_key, $new_key, $callback ) {
		$user_id = $this->factory->user->create();
		$posts   = $this->factory->post->create_many( 5 );
		$option  = bbp_admin_repair_tool_batch_option_name( $tool_id );
		add_user_meta( $user_id, bbp_db()->prefix . $old_key, implode( ',', $posts ) );
		call_user_func( $callback );
		$before = get_option( $option );
		$fail   = static function ( $value, $old_value ) {
			return $old_value;
		};
		add_filter( 'pre_update_option_' . $option, $fail, 10, 2 );
		try {
			$result = call_user_func( $callback );
			$this->assertSame( 5, $result[0] );
			$this->assertFalse( $result['success'] );
			$this->assertSame( $before, get_option( $option ) );
		} finally {
			remove_filter( 'pre_update_option_' . $option, $fail, 10 );
		}
		$this->finish( $callback );
		foreach ( $posts as $post_id ) {
			$this->assertSame( array( (string) $user_id ), get_post_meta( $post_id, $new_key, false ) );
		}
	}

	private function closed_topics( $count ) {
		$posts = $this->factory->topic->create_many( $count, array( 'post_status' => bbp_get_closed_status_id() ) );
		foreach ( $posts as $post_id ) {
			delete_post_meta( $post_id, '_bbp_status' );
		}
		return $posts;
	}

	public function test_closed_topics_are_bounded_preserve_statuses_and_preview_the_next_batch() {
		$posts  = $this->closed_topics( 5 );
		$option = bbp_admin_repair_tool_batch_option_name( 'bbp-sync-closed-topics' );
		update_post_meta( $posts[0], '_bbp_status', 'private' );
		$before = $this->stored_data();
		$this->assertFalse( bbp_admin_repair_closed_topics_dry_run()['complete'] );
		$this->assertSame( $before, $this->stored_data() );
		$this->assertFalse( bbp_admin_repair_closed_topics()['complete'] );
		$this->assertSame( 'private', get_post_meta( $posts[0], '_bbp_status', true ) );
		$this->assertSame( 'publish', get_post_meta( $posts[1], '_bbp_status', true ) );
		$this->assertSame( '', get_post_meta( $posts[2], '_bbp_status', true ) );

		$late   = $this->closed_topics( 1 );
		$before = $this->stored_data();
		$this->assertStringContainsString( '2 closed topics would be repaired', bbp_admin_repair_closed_topics_dry_run()[1] );
		$this->assertSame( $before, $this->stored_data() );
		$this->finish( 'bbp_admin_repair_closed_topics' );
		$this->assertFalse( get_option( $option, false ) );
		$this->assertSame( '', get_post_meta( $late[0], '_bbp_status', true ) );
		$this->finish( 'bbp_admin_repair_closed_topics' );
		$this->assertSame( 'publish', get_post_meta( $late[0], '_bbp_status', true ) );
		$this->assertSame( 'private', get_post_meta( $posts[0], '_bbp_status', true ) );
	}

	public function test_closed_topic_write_failures_keep_the_checkpoint_for_a_safe_retry() {
		$posts  = $this->closed_topics( 5 );
		$option = bbp_admin_repair_tool_batch_option_name( 'bbp-sync-closed-topics' );
		bbp_admin_repair_closed_topics();
		$before = get_option( $option );
		$fail   = static function ( $check, $post_id, $meta_key ) use ( $posts ) {
			return ( ( $posts[3] === $post_id ) && ( '_bbp_status' === $meta_key ) ) ? false : $check;
		};
		add_filter( 'update_post_metadata', $fail, 10, 3 );
		try {
			$this->assertFalse( bbp_admin_repair_closed_topics()['success'] );
			$this->assertSame( $before, get_option( $option ) );
			$this->assertSame( 'publish', get_post_meta( $posts[2], '_bbp_status', true ) );
		} finally {
			remove_filter( 'update_post_metadata', $fail, 10 );
		}
		$this->finish( 'bbp_admin_repair_closed_topics' );
		foreach ( $posts as $post_id ) {
			$this->assertSame( array( 'publish' ), get_post_meta( $post_id, '_bbp_status', false ) );
		}
	}

	public function test_closed_topic_read_failures_do_not_advance_progress() {
		$this->closed_topics( 5 );
		bbp_admin_repair_closed_topics();
		$db       = bbp_db();
		$option   = bbp_admin_repair_tool_batch_option_name( 'bbp-sync-closed-topics' );
		$before   = get_option( $option );
		$postmeta = $db->postmeta;
		$suppress = $db->suppress_errors( true );
		try {
			$db->postmeta = 'bbp_missing_closed_topic_postmeta';
			$this->assertFalse( bbp_admin_repair_closed_topics_dry_run()['success'] );
			$this->assertFalse( bbp_admin_repair_closed_topics()['success'] );
			$this->assertSame( $before, get_option( $option ) );
		} finally {
			$db->postmeta = $postmeta;
			$db->suppress_errors( $suppress );
		}
		$this->finish( 'bbp_admin_repair_closed_topics' );
	}

	public function test_small_site_updater_completes_all_three_real_migrations_automatically() {
		$this->check_small_site_updater( false );
	}

	public function test_small_site_budget_exhaustion_keeps_real_migrations_pending_and_resumable() {
		$this->check_small_site_updater( true );
	}

	private function check_small_site_updater( $exhaust ) {
		$user_id = $this->factory->user->create();
		$tools   = $this->upgrade_tools();
		$posts   = array();
		foreach ( $tools as $name => $tool ) {
			$posts[ $name ] = ( 'forum subscriptions' === $name )
				? $this->factory->forum->create_many( 5 )
				: $this->factory->topic->create_many( 5 );
			add_user_meta( $user_id, bbp_db()->prefix . $tool[1], implode( ',', $posts[ $name ] ) );
		}
		update_option( '_bbp_db_version', 260 );
		delete_option( '_bbp_db_pending_upgrades' );
		add_filter( 'bbp_is_large_install', '__return_false' );
		if ( $exhaust ) {
			add_filter( 'bbp_admin_repair_tool_automatic_batch_limit', '__return_true' );
		}
		try {
			bbp_version_updater();
		} finally {
			remove_filter( 'bbp_is_large_install', '__return_false' );
			remove_filter( 'bbp_admin_repair_tool_automatic_batch_limit', '__return_true' );
		}
		$this->assertSame( (int) bbp_get_db_version(), (int) bbp_get_db_version_raw() );
		foreach ( $tools as $name => $tool ) {
			$option = bbp_admin_repair_tool_batch_option_name( $tool[0] );
			if ( $exhaust ) {
				$this->assertContains( $tool[0], bbp_get_pending_upgrades() );
				$this->assertIsArray( get_option( $option ) );
				$this->assertTrue( bbp_admin_repair_tool_run_automatically( $tool[0], $tool[3] ) );
			}
			$this->assertNotContains( $tool[0], bbp_get_pending_upgrades() );
			$this->assertFalse( get_option( $option, false ) );
			foreach ( $posts[ $name ] as $post_id ) {
				$this->assertSame( array( (string) $user_id ), get_post_meta( $post_id, $tool[2], false ) );
			}
		}
	}
}
