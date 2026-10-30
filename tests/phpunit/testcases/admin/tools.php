<?php

/**
 * Tests for the admin tools functions.
 *
 * @group tools
 */
class BBP_Tests_Admin_Tools extends BBP_UnitTestCase {
	protected $old_current_user = 0;
	protected $keymaster_id;

	public function setUp(): void {
		parent::setUp();
		$this->old_current_user = get_current_user_id();
		$this->set_current_user( $this->factory->user->create( array( 'role' => 'administrator' ) ) );
		$this->keymaster_id = get_current_user_id();

		bbp_set_user_role( $this->keymaster_id, bbp_get_keymaster_role() );

		if ( ! function_exists( 'bbp_admin' ) ) {
			require_once BBP_PLUGIN_DIR . 'includes/admin/actions.php';
		}
		bbp_admin();

		if ( ! function_exists( 'bbp_admin_repair_page' ) ) {
			require_once BBP_PLUGIN_DIR . 'includes/admin/tools/repair.php';
			require_once BBP_PLUGIN_DIR . 'includes/admin/tools/upgrade.php';
		}
		if ( ! function_exists( 'bbp_admin_reset_page' ) ) {
			require_once BBP_PLUGIN_DIR . 'includes/admin/tools/reset.php';
		}
	}

	public function tearDown(): void {
		parent::tearDown();
		$this->set_current_user( $this->old_current_user );
	}

	/**
	 * @covers ::bbp_admin_repair_page
	 */
	public function test_bbp_admin_repair() {
		$original_get   = $_GET;
		$original_tools = bbp_admin()->tools;
		$_GET           = array( 'page' => 'bbp-repair' );
		bbp_register_default_repair_tools();

		try {
			ob_start();
			bbp_admin_repair_page();
			$output = ob_get_clean();

			$this->assertStringContainsString( 'name="page" value="bbp-repair"', $output );
			$this->assertStringContainsString( 'name="checked[]"', $output );
			$this->assertStringContainsString( 'name="_wpnonce"', $output );
		} finally {
			$_GET              = $original_get;
			bbp_admin()->tools = $original_tools;
		}
	}

	/**
	 * @covers ::bbp_admin_repair_handler
	 * @ticket 3695
	 */
	public function test_bbp_admin_repair_handler() {
		$original_get             = $_GET;
		$original_method          = isset( $_SERVER['REQUEST_METHOD'] ) ? $_SERVER['REQUEST_METHOD'] : null;
		$_GET                     = array( 'action' => 'not-run' );
		$_SERVER['REQUEST_METHOD'] = 'GET';

		try {
			$this->assertNull( bbp_admin_repair_handler() );
		} finally {
			$_GET = $original_get;
			if ( is_null( $original_method ) ) {
				unset( $_SERVER['REQUEST_METHOD'] );
			} else {
				$_SERVER['REQUEST_METHOD'] = $original_method;
			}
		}
	}

	/**
	 * @covers ::bbp_admin_tools_repair_help
	 * @ticket 3695
	 */
	public function test_bbp_admin_tools_repair_help() {
		$previous = get_current_screen();
		set_current_screen( 'tools_page_bbp-repair' );

		try {
			bbp_admin_tools_repair_help();
			$tabs = get_current_screen()->get_help_tabs();
			$this->assertArrayHasKey( 'repair_forums', $tabs );
			$this->assertStringContainsString( 'bbPress: Repair Forums', $tabs['repair_forums']['content'] );
			$this->assertStringContainsString( 'bbPress Documentation', get_current_screen()->get_help_sidebar() );
		} finally {
			$GLOBALS['current_screen'] = $previous;
		}
	}

	/**
	 * @covers ::bbp_admin_tools_reset_help
	 * @ticket 3695
	 */
	public function test_bbp_admin_tools_reset_help() {
		$previous = get_current_screen();
		set_current_screen( 'tools_page_bbp-reset' );

		try {
			bbp_admin_tools_reset_help();
			$tabs = get_current_screen()->get_help_tabs();
			$this->assertArrayHasKey( 'reset_forums', $tabs );
			$this->assertStringContainsString( 'bbPress: Reset Forums', $tabs['reset_forums']['content'] );
		} finally {
			$GLOBALS['current_screen'] = $previous;
		}
	}

	/**
	 * @covers ::bbp_admin_tools_converter_help
	 * @ticket 3695
	 */
	public function test_bbp_admin_tools_converter_help() {
		$previous = get_current_screen();
		set_current_screen( 'tools_page_bbp-converter' );

		try {
			bbp_admin_tools_converter_help();
			$tabs = get_current_screen()->get_help_tabs();
			$this->assertSame( array( 'overview', 'database_settings', 'importer_options' ), array_keys( $tabs ) );
			$this->assertStringContainsString( 'bbPress: Import Forums', $tabs['overview']['content'] );
		} finally {
			$GLOBALS['current_screen'] = $previous;
		}
	}

	/**
	 * @covers ::bbp_admin_tools_feedback
	 * @ticket 3695
	 */
	public function test_bbp_admin_tools_feedback() {
		$notices = bbp_admin()->notices;

		try {
			bbp_admin_tools_feedback( 'Repair complete', 'success', false );
			$this->assertStringContainsString( 'notice success', end( bbp_admin()->notices ) );
			$this->assertStringContainsString( '<p>Repair complete</p>', end( bbp_admin()->notices ) );
			$this->assertStringNotContainsString( 'is-dismissible', end( bbp_admin()->notices ) );
			$this->assertFalse( bbp_admin_tools_feedback( array( 'invalid' ) ) );
		} finally {
			bbp_admin()->notices = $notices;
		}
	}

	/**
	 * @ticket BBP3768
	 * @covers ::bbp_admin_reset_query_feedback
	 */
	public function test_bbp_admin_reset_query_feedback_reports_query_outcomes() {
		require_once BBP_PLUGIN_DIR . 'includes/admin/tools/reset.php';

		$db              = bbp_db();
		$old_suppression = $db->suppress_errors( true );
		$args            = array( 'message' => 'Reset: %s' );

		try {
			$this->assertSame( 'Reset: Skipped.', bbp_admin_reset_query_feedback( $args ) );

			$args['query'] = 'SELECT 1';
			$this->assertSame( 'Reset: Success!', bbp_admin_reset_query_feedback( $args ) );

			$args['query'] = "DELETE FROM {$db->posts} WHERE 1 = 0";
			$this->assertSame( 'Reset: Success!', bbp_admin_reset_query_feedback( $args ) );

			$args['query'] = 'SELECT * FROM bbp_missing_reset_feedback_table';
			$this->assertSame( 'Reset: Failed!', bbp_admin_reset_query_feedback( $args ) );
		} finally {
			$db->suppress_errors( $old_suppression );
		}
	}

	/**
	 * @covers ::bbp_admin_repair_list
	 * @ticket 3695
	 */
	public function test_bbp_admin_repair_list() {
		$original_get   = $_GET;
		$original_tools = bbp_admin()->tools;
		$_GET           = array( 's' => 'Recount' );
		bbp_register_default_repair_tools();

		try {
			$this->assertArrayHasKey( 'bbp-forum-subforums', bbp_admin()->tools );

			$list = bbp_admin_repair_list();
			$this->assertNotEmpty( $list );
			foreach ( $list as $tool ) {
				$this->assertSame( 'repair', $tool['type'] );
				$this->assertStringContainsString( 'recount', strtolower( $tool['title'] ) );
			}
		} finally {
			$_GET              = $original_get;
			bbp_admin()->tools = $original_tools;
		}
	}

	/**
	 * @covers ::bbp_admin_repair_topic_reply_count
	 * @covers ::bbp_admin_repair_topic_reply_count_dry_run
	 * @covers ::bbp_admin_repair_topic_reply_count_process
	 * @ticket BBP2664
	 */
	public function test_bbp_admin_repair_topic_reply_count() {
		$topic_id = $this->factory->topic->create();

		$this->factory->reply->create_many( 2, array( 'post_parent' => $topic_id ) );
		$this->factory->reply->create( array(
			'post_parent' => $topic_id,
			'post_status' => bbp_get_pending_status_id(),
		) );
		bbp_update_topic_reply_count( $topic_id, 9 );

		$public_statuses = function ( $statuses ) {
			$statuses[] = bbp_get_pending_status_id();
			return $statuses;
		};
		add_filter( 'bbp_get_public_reply_statuses', $public_statuses );
		$dry_run = bbp_admin_repair_topic_reply_count_dry_run();
		$this->assertSame( 0, $dry_run[0] );
		$this->assertStringContainsString( 'Reply counts would be repaired in 1 topic', $dry_run[1] );
		$this->assertSame( 9, bbp_get_topic_reply_count( $topic_id, true ) );

		$result = bbp_admin_repair_topic_reply_count();
		$this->assertSame( 1, bbp_admin_repair_topic_reply_count_dry_run()[0] );
		remove_filter( 'bbp_get_public_reply_statuses', $public_statuses );

		$this->assertSame( 0, $result[0] );
		$this->assertSame( 3, bbp_get_topic_reply_count( $topic_id, true ) );
	}

	/**
	 * @covers ::bbp_admin_repair_topic_reply_count
	 * @covers ::bbp_admin_repair_topic_reply_count_dry_run
	 * @covers ::bbp_admin_repair_topic_reply_count_process
	 */
	public function test_bbp_admin_repair_topic_reply_count_uses_current_database_values() {
		$db       = bbp_db();
		$topic_id = $this->factory->topic->create();

		$this->factory->reply->create( array( 'post_parent' => $topic_id ) );
		bbp_update_topic_reply_count( $topic_id, 1 );
		$this->assertSame( 1, bbp_get_topic_reply_count( $topic_id, true ) );
		$this->assertNotFalse(
			$db->insert(
				$db->postmeta,
				array(
					'post_id'    => $topic_id,
					'meta_key'   => '_bbp_reply_count',
					'meta_value' => 1,
				)
			)
		);

		$dry_run = bbp_admin_repair_topic_reply_count_dry_run();
		$this->assertSame( 0, $dry_run[0] );
		$this->assertStringContainsString( 'Reply counts would be repaired in 1 topic', $dry_run[1] );
		$this->assertSame(
			2,
			(int) $db->get_var( $db->prepare( "SELECT COUNT(*) FROM {$db->postmeta} WHERE post_id = %d AND meta_key = '_bbp_reply_count'", $topic_id ) )
		);

		$this->assertSame( 0, bbp_admin_repair_topic_reply_count()[0] );
		$this->assertSame( array( '1' ), get_post_meta( $topic_id, '_bbp_reply_count', false ) );
	}

	/**
	 * @covers ::bbp_admin_repair_topic_reply_count
	 * @covers ::bbp_admin_repair_topic_reply_count_dry_run
	 * @covers ::bbp_admin_repair_topic_reply_count_process
	 */
	public function test_bbp_admin_repair_topic_reply_count_repairs_missing_meta() {
		$db       = bbp_db();
		$topic_id = $this->factory->topic->create();

		$this->factory->reply->create( array( 'post_parent' => $topic_id ) );
		bbp_update_topic_reply_count( $topic_id, 1 );
		$this->assertSame(
			1,
			$db->delete( $db->postmeta, array( 'post_id' => $topic_id, 'meta_key' => '_bbp_reply_count' ) )
		);

		$dry_run = bbp_admin_repair_topic_reply_count_dry_run();
		$this->assertSame( 0, $dry_run[0] );
		$this->assertStringContainsString( 'Reply counts would be repaired in 1 topic', $dry_run[1] );
		$this->assertSame(
			0,
			(int) $db->get_var( $db->prepare( "SELECT COUNT(*) FROM {$db->postmeta} WHERE post_id = %d AND meta_key = '_bbp_reply_count'", $topic_id ) )
		);

		$this->assertSame( 0, bbp_admin_repair_topic_reply_count()[0] );
		$this->assertSame( array( '1' ), get_post_meta( $topic_id, '_bbp_reply_count', false ) );
	}

	/**
	 * @covers ::bbp_admin_repair_topic_reply_count
	 * @covers ::bbp_admin_repair_topic_reply_count_dry_run
	 * @covers ::bbp_admin_repair_topic_reply_count_process
	 */
	public function test_bbp_admin_repair_topic_reply_count_detects_malformed_meta_strings() {
		$db       = bbp_db();
		$topic_id = $this->factory->topic->create();

		$this->factory->reply->create( array( 'post_parent' => $topic_id ) );
		bbp_update_topic_reply_count( $topic_id, 1 );
		$this->assertSame(
			1,
			$db->update( $db->postmeta, array( 'meta_value' => '01' ), array( 'post_id' => $topic_id, 'meta_key' => '_bbp_reply_count' ) )
		);

		$dry_run = bbp_admin_repair_topic_reply_count_dry_run();
		$this->assertSame( 0, $dry_run[0] );
		$this->assertStringContainsString( 'Reply counts would be repaired in 1 topic', $dry_run[1] );
		$this->assertSame(
			'01',
			$db->get_var( $db->prepare( "SELECT meta_value FROM {$db->postmeta} WHERE post_id = %d AND meta_key = '_bbp_reply_count'", $topic_id ) )
		);

		$this->assertSame( 0, bbp_admin_repair_topic_reply_count()[0] );
		$this->assertSame( array( '1' ), get_post_meta( $topic_id, '_bbp_reply_count', false ) );
	}

	/**
	 * @covers ::bbp_admin_repair_topic_reply_count
	 * @covers ::bbp_admin_repair_topic_reply_count_dry_run
	 * @covers ::bbp_admin_repair_topic_reply_count_process
	 */
	public function test_bbp_admin_repair_topic_reply_count_run_clears_stale_cache_when_database_matches() {
		$topic_id = $this->factory->topic->create();

		$this->factory->reply->create( array( 'post_parent' => $topic_id ) );
		bbp_update_topic_reply_count( $topic_id, 1 );
		$this->assertSame( '1', get_post_meta( $topic_id, '_bbp_reply_count', true ) );
		$meta_cache                     = wp_cache_get( $topic_id, 'post_meta' );
		$meta_cache['_bbp_reply_count'] = array( '99' );
		$this->assertTrue( wp_cache_set( $topic_id, $meta_cache, 'post_meta' ) );
		$this->assertSame( '99', get_post_meta( $topic_id, '_bbp_reply_count', true ) );

		$this->assertSame( 1, bbp_admin_repair_topic_reply_count_dry_run()[0] );
		$this->assertSame( '99', get_post_meta( $topic_id, '_bbp_reply_count', true ) );

		$this->assertSame( 0, bbp_admin_repair_topic_reply_count()[0] );
		$this->assertSame( '1', get_post_meta( $topic_id, '_bbp_reply_count', true ) );
	}

	/**
	 * @covers ::bbp_admin_repair_topic_reply_count
	 * @covers ::bbp_admin_repair_topic_reply_count_dry_run
	 * @covers ::bbp_admin_repair_topic_reply_count_process
	 */
	public function test_bbp_admin_repair_topic_reply_count_repairs_non_public_topic_meta() {
		$topic_id = $this->factory->topic->create( array( 'post_status' => bbp_get_pending_status_id() ) );

		update_post_meta( $topic_id, '_bbp_reply_count', 7 );
		$dry_run = bbp_admin_repair_topic_reply_count_dry_run();
		$this->assertSame( 0, $dry_run[0] );
		$this->assertStringContainsString( 'Reply counts would be repaired in 1 topic', $dry_run[1] );
		$this->assertSame( '7', get_post_meta( $topic_id, '_bbp_reply_count', true ) );

		$this->assertSame( 0, bbp_admin_repair_topic_reply_count()[0] );
		$this->assertFalse( metadata_exists( 'post', $topic_id, '_bbp_reply_count' ) );
	}

	/**
	 * @covers ::bbp_admin_repair_topic_reply_count_dry_run
	 * @covers ::bbp_admin_repair_topic_reply_count_process
	 */
	public function test_bbp_admin_repair_topic_reply_count_dry_run_counts_multiple_topics() {
		$topic_ids = $this->factory->topic->create_many( 3 );

		foreach ( $topic_ids as $topic_id ) {
			bbp_update_topic_reply_count( $topic_id, 0 );
		}

		update_post_meta( reset( $topic_ids ), '_bbp_reply_count', 9 );
		update_post_meta( end( $topic_ids ), '_bbp_reply_count', 9 );

		$dry_run = bbp_admin_repair_topic_reply_count_dry_run();
		$this->assertSame( 0, $dry_run[0] );
		$this->assertStringContainsString( 'Reply counts would be repaired in 2 topics', $dry_run[1] );
		$this->assertSame( '9', get_post_meta( reset( $topic_ids ), '_bbp_reply_count', true ) );
		$this->assertSame( '9', get_post_meta( end( $topic_ids ), '_bbp_reply_count', true ) );
	}

	/**
	 * @covers ::bbp_admin_repair_topic_voice_count
	 * @covers ::bbp_admin_repair_topic_voice_count_dry_run
	 * @covers ::bbp_admin_repair_topic_voice_count_process
	 * @ticket BBP2664
	 */
	public function test_bbp_admin_repair_topic_voice_count() {
		$u = $this->factory->user->create_many( 2 );

		$f = $this->factory->forum->create();

		$t = $this->factory->topic->create( array(
			'post_parent' => $f,
			'topic_meta' => array(
				'forum_id' => $f,
			),
		) );

		$r = $this->factory->reply->create( array(
			'post_author' => $u[0],
			'post_parent' => $t,
			'reply_meta' => array(
				'forum_id' => $f,
				'topic_id' => $t,
			),
		) );

		$r = $this->factory->reply->create( array(
			'post_author' => $u[1],
			'post_parent' => $t,
			'reply_meta' => array(
				'forum_id' => $f,
				'topic_id' => $t,
			),
		) );

		$count = bbp_get_topic_voice_count( $t );
		$this->assertSame( '3', $count );

		$pending_user_id = $this->factory->user->create();
		$this->factory->reply->create( array(
			'post_author' => $pending_user_id,
			'post_parent' => $t,
			'post_status' => bbp_get_pending_status_id(),
			'reply_meta'  => array(
				'forum_id' => $f,
				'topic_id' => $t,
			),
		) );

		// Delete the topic _bbp_voice_count meta key.
		$this->assertTrue( delete_post_meta_by_key( '_bbp_voice_count' ) );
		delete_post_meta( $t, '_bbp_engagement', $pending_user_id );

		$count = bbp_get_topic_voice_count( $t );
		$this->assertSame( '0', $count );

		// Repair the topic voice count meta.
		$public_statuses = function ( $statuses ) {
			$statuses[] = bbp_get_pending_status_id();
			return $statuses;
		};
		add_filter( 'bbp_get_public_reply_statuses', $public_statuses );

		$engagements = get_post_meta( $t, '_bbp_engagement', false );
		$dry_run     = bbp_admin_repair_topic_voice_count_dry_run();
		$this->assertSame( 0, $dry_run[0] );
		$this->assertStringContainsString( 'Engagements would be repaired in 1 topic', $dry_run[1] );
		$this->assertStringContainsString( 'Voice counts would be repaired in 1 topic', $dry_run[1] );
		$this->assertSame( $engagements, get_post_meta( $t, '_bbp_engagement', false ) );
		$this->assertFalse( metadata_exists( 'post', $t, '_bbp_voice_count' ) );

		$result = bbp_admin_repair_topic_voice_count();
		$this->assertSame( 0, $result[0] );
		$this->assertStringContainsString( 'Complete!', $result[1] );
		$this->assertSame( 1, bbp_admin_repair_topic_voice_count_dry_run()[0] );
		remove_filter( 'bbp_get_public_reply_statuses', $public_statuses );

		$count = bbp_get_topic_voice_count( $t );
		$this->assertSame( '4', $count );
	}

	/**
	 * @covers ::bbp_admin_repair_topic_voice_count
	 * @covers ::bbp_admin_repair_topic_voice_count_process
	 */
	public function test_bbp_admin_repair_topic_voice_count_excludes_anonymous_authors() {
		$forum_id = $this->factory->forum->create();
		$topic_id = $this->factory->topic->create( array(
			'post_author' => 0,
			'post_parent' => $forum_id,
			'topic_meta'  => array(
				'forum_id' => $forum_id,
			),
		) );

		$this->factory->reply->create( array(
			'post_author' => 0,
			'post_parent' => $topic_id,
			'reply_meta'  => array(
				'forum_id' => $forum_id,
				'topic_id' => $topic_id,
			),
		) );

		$result = bbp_admin_repair_topic_voice_count();

		$this->assertSame( 0, $result[0] );
		$this->assertSame( 0, bbp_get_topic_voice_count( $topic_id, true ) );
	}

	/**
	 * @covers ::bbp_admin_repair_topic_voice_count
	 * @covers ::bbp_admin_repair_topic_voice_count_dry_run
	 * @covers ::bbp_admin_repair_topic_voice_count_process
	 */
	public function test_bbp_admin_repair_topic_voice_count_uses_current_database_values() {
		$db        = bbp_db();
		$user_id   = $this->factory->user->create();
		$forum_id  = $this->factory->forum->create();
		$topic_id  = $this->factory->topic->create( array(
			'post_author' => $user_id,
			'post_parent' => $forum_id,
			'topic_meta'  => array(
				'forum_id' => $forum_id,
			),
		) );

		$this->assertSame( array( (string) $user_id ), get_post_meta( $topic_id, '_bbp_engagement', false ) );
		$this->assertSame( array( '1' ), get_post_meta( $topic_id, '_bbp_voice_count', false ) );
		$this->assertNotFalse(
			$db->insert(
				$db->postmeta,
				array(
					'post_id'    => $topic_id,
					'meta_key'   => '_bbp_engagement',
					'meta_value' => $user_id,
				)
			)
		);
		$this->assertNotFalse(
			$db->insert(
				$db->postmeta,
				array(
					'post_id'    => $topic_id,
					'meta_key'   => '_bbp_voice_count',
					'meta_value' => 99,
				)
			)
		);

		$dry_run = bbp_admin_repair_topic_voice_count_dry_run();
		$this->assertSame( 0, $dry_run[0] );
		$this->assertStringContainsString( 'Engagements would be repaired in 1 topic', $dry_run[1] );
		$this->assertStringContainsString( 'Voice counts would be repaired in 1 topic', $dry_run[1] );
		$this->assertSame(
			2,
			(int) $db->get_var( $db->prepare( "SELECT COUNT(*) FROM {$db->postmeta} WHERE post_id = %d AND meta_key = '_bbp_engagement'", $topic_id ) )
		);
		$this->assertSame(
			2,
			(int) $db->get_var( $db->prepare( "SELECT COUNT(*) FROM {$db->postmeta} WHERE post_id = %d AND meta_key = '_bbp_voice_count'", $topic_id ) )
		);

		$result = bbp_admin_repair_topic_voice_count();
		$this->assertSame( 0, $result[0] );
		$this->assertSame( array( (string) $user_id ), get_post_meta( $topic_id, '_bbp_engagement', false ) );
		$this->assertSame( array( '1' ), get_post_meta( $topic_id, '_bbp_voice_count', false ) );
	}

	/**
	 * @covers ::bbp_admin_repair_topic_voice_count
	 * @covers ::bbp_admin_repair_topic_voice_count_dry_run
	 * @covers ::bbp_admin_repair_topic_voice_count_process
	 */
	public function test_bbp_admin_repair_topic_voice_count_detects_malformed_meta_strings() {
		$db        = bbp_db();
		$user_id   = $this->factory->user->create();
		$forum_id  = $this->factory->forum->create();
		$topic_id  = $this->factory->topic->create( array(
			'post_author' => $user_id,
			'post_parent' => $forum_id,
			'topic_meta'  => array(
				'forum_id' => $forum_id,
			),
		) );
		$malformed_user_id = '0' . $user_id;

		$this->assertSame(
			1,
			$db->update( $db->postmeta, array( 'meta_value' => $malformed_user_id ), array( 'post_id' => $topic_id, 'meta_key' => '_bbp_engagement' ) )
		);
		$this->assertSame(
			1,
			$db->update( $db->postmeta, array( 'meta_value' => '01' ), array( 'post_id' => $topic_id, 'meta_key' => '_bbp_voice_count' ) )
		);

		$dry_run = bbp_admin_repair_topic_voice_count_dry_run();
		$this->assertSame( 0, $dry_run[0] );
		$this->assertStringContainsString( 'Engagements would be repaired in 1 topic', $dry_run[1] );
		$this->assertStringContainsString( 'Voice counts would be repaired in 1 topic', $dry_run[1] );
		$this->assertSame(
			$malformed_user_id,
			$db->get_var( $db->prepare( "SELECT meta_value FROM {$db->postmeta} WHERE post_id = %d AND meta_key = '_bbp_engagement'", $topic_id ) )
		);
		$this->assertSame(
			'01',
			$db->get_var( $db->prepare( "SELECT meta_value FROM {$db->postmeta} WHERE post_id = %d AND meta_key = '_bbp_voice_count'", $topic_id ) )
		);

		$this->assertSame( 0, bbp_admin_repair_topic_voice_count()[0] );
		$this->assertSame( array( (string) $user_id ), get_post_meta( $topic_id, '_bbp_engagement', false ) );
		$this->assertSame( array( '1' ), get_post_meta( $topic_id, '_bbp_voice_count', false ) );
	}

	/**
	 * @covers ::bbp_admin_repair_topic_voice_count
	 * @covers ::bbp_admin_repair_topic_voice_count_dry_run
	 * @covers ::bbp_admin_repair_topic_voice_count_process
	 */
	public function test_bbp_admin_repair_topic_voice_count_run_clears_stale_cache_when_database_matches() {
		$user_id  = $this->factory->user->create();
		$forum_id = $this->factory->forum->create();
		$topic_id = $this->factory->topic->create( array(
			'post_author' => $user_id,
			'post_parent' => $forum_id,
			'topic_meta'  => array(
				'forum_id' => $forum_id,
			),
		) );

		$this->assertSame( '1', get_post_meta( $topic_id, '_bbp_voice_count', true ) );
		$meta_cache                       = wp_cache_get( $topic_id, 'post_meta' );
		$meta_cache['_bbp_voice_count']   = array( '99' );
		$this->assertTrue( wp_cache_set( $topic_id, $meta_cache, 'post_meta' ) );
		$this->assertSame( '99', get_post_meta( $topic_id, '_bbp_voice_count', true ) );

		$this->assertSame( 1, bbp_admin_repair_topic_voice_count_dry_run()[0] );
		$this->assertSame( '99', get_post_meta( $topic_id, '_bbp_voice_count', true ) );

		$result = bbp_admin_repair_topic_voice_count();
		$this->assertSame( 0, $result[0] );
		$this->assertSame( '1', get_post_meta( $topic_id, '_bbp_voice_count', true ) );
	}

	/**
	 * @covers ::bbp_admin_repair_topic_voice_count
	 * @covers ::bbp_admin_repair_topic_voice_count_dry_run
	 * @covers ::bbp_admin_repair_topic_voice_count_process
	 */
	public function test_bbp_admin_repair_topic_voice_count_repairs_non_public_topic_meta() {
		$user_id  = $this->factory->user->create();
		$forum_id = $this->factory->forum->create();
		$topic_id = $this->factory->topic->create( array(
			'post_author' => $user_id,
			'post_parent' => $forum_id,
			'post_status' => bbp_get_pending_status_id(),
			'topic_meta'  => array(
				'forum_id' => $forum_id,
			),
		) );

		update_post_meta( $topic_id, '_bbp_engagement', $user_id );
		update_post_meta( $topic_id, '_bbp_voice_count', 1 );

		$dry_run = bbp_admin_repair_topic_voice_count_dry_run();
		$this->assertSame( 0, $dry_run[0] );
		$this->assertStringContainsString( 'Engagements would be repaired in 1 topic', $dry_run[1] );
		$this->assertStringContainsString( 'Voice counts would be repaired in 1 topic', $dry_run[1] );
		$this->assertTrue( metadata_exists( 'post', $topic_id, '_bbp_engagement' ) );
		$this->assertTrue( metadata_exists( 'post', $topic_id, '_bbp_voice_count' ) );

		$this->assertSame( 0, bbp_admin_repair_topic_voice_count()[0] );
		$this->assertFalse( metadata_exists( 'post', $topic_id, '_bbp_engagement' ) );
		$this->assertFalse( metadata_exists( 'post', $topic_id, '_bbp_voice_count' ) );
	}

	/**
	 * @covers ::bbp_admin_repair_topic_voice_count_dry_run
	 * @covers ::bbp_admin_repair_topic_voice_count_process
	 */
	public function test_bbp_admin_repair_topic_voice_count_dry_run_counts_multiple_topics() {
		$user_id   = $this->factory->user->create();
		$forum_id  = $this->factory->forum->create();
		$topic_ids = $this->factory->topic->create_many(
			3,
			array(
				'post_author' => $user_id,
				'post_parent' => $forum_id,
				'topic_meta'  => array(
					'forum_id' => $forum_id,
				),
			)
		);

		update_post_meta( reset( $topic_ids ), '_bbp_voice_count', 99 );
		update_post_meta( end( $topic_ids ), '_bbp_voice_count', 99 );

		$dry_run = bbp_admin_repair_topic_voice_count_dry_run();
		$this->assertSame( 0, $dry_run[0] );
		$this->assertStringContainsString( 'Engagements would be repaired in 0 topics', $dry_run[1] );
		$this->assertStringContainsString( 'Voice counts would be repaired in 2 topics', $dry_run[1] );
		$this->assertSame( '99', get_post_meta( reset( $topic_ids ), '_bbp_voice_count', true ) );
		$this->assertSame( '99', get_post_meta( end( $topic_ids ), '_bbp_voice_count', true ) );
	}

	/**
	 * @covers ::bbp_admin_repair_topic_hidden_reply_count
	 * @covers ::bbp_admin_repair_topic_hidden_reply_count_dry_run
	 * @covers ::bbp_admin_repair_topic_hidden_reply_count_process
	 * @ticket BBP2664
	 */
	public function test_bbp_admin_repair_topic_hidden_reply_count() {

		$f = $this->factory->forum->create();

		$t = $this->factory->topic->create( array(
			'post_parent' => $f,
			'topic_meta' => array(
				'forum_id' => $f,
			),
		) );

		$r = $this->factory->reply->create( array(
			'post_parent' => $t,
			'reply_meta' => array(
				'forum_id' => $f,
				'topic_id' => $t,
			),
		) );

		$count = bbp_get_topic_reply_count( $t, true );
		$this->assertSame( 1, $count );

		$r = $this->factory->reply->create_many( 3, array(
			'post_parent' => $t,
			'reply_meta' => array(
				'forum_id' => $f,
				'topic_id' => $t,
			),
		) );

		bbp_spam_reply( $r[0] );
		bbp_unapprove_reply( $r[2] );

		$count = bbp_get_topic_reply_count_hidden( $t, true );
		$this->assertSame( 2, $count );

		// Delete the topic _bbp_reply_count_hidden meta key.
		$this->assertTrue( delete_post_meta_by_key( '_bbp_reply_count_hidden' ) );

		$count = bbp_get_topic_reply_count_hidden( $t, true );
		$this->assertSame( 0, $count );

		// Preview and repair the topic hidden reply count meta.
		$dry_run = bbp_admin_repair_topic_hidden_reply_count_dry_run();
		$this->assertSame( 0, $dry_run[0] );
		$this->assertStringContainsString( 'Hidden reply counts would be repaired in 1 topic', $dry_run[1] );
		$this->assertFalse( metadata_exists( 'post', $t, '_bbp_reply_count_hidden' ) );

		$this->assertSame( 0, bbp_admin_repair_topic_hidden_reply_count()[0] );
		$this->assertSame( 1, bbp_admin_repair_topic_hidden_reply_count_dry_run()[0] );

		$count = bbp_get_topic_reply_count_hidden( $t, true );
		$this->assertSame( 2, $count );
	}

	/**
	 * @covers ::bbp_admin_repair_topic_hidden_reply_count
	 * @covers ::bbp_admin_repair_topic_hidden_reply_count_dry_run
	 * @covers ::bbp_admin_repair_topic_hidden_reply_count_process
	 */
	public function test_bbp_admin_repair_topic_hidden_reply_count_uses_current_database_values() {
		$db       = bbp_db();
		$topic_id = $this->factory->topic->create();
		$reply_id = $this->factory->reply->create( array( 'post_parent' => $topic_id ) );

		bbp_spam_reply( $reply_id );
		bbp_update_topic_reply_count_hidden( $topic_id, 1 );
		$this->assertNotFalse(
			$db->insert(
				$db->postmeta,
				array(
					'post_id'    => $topic_id,
					'meta_key'   => '_bbp_reply_count_hidden',
					'meta_value' => 1,
				)
			)
		);

		$dry_run = bbp_admin_repair_topic_hidden_reply_count_dry_run();
		$this->assertSame( 0, $dry_run[0] );
		$this->assertStringContainsString( 'Hidden reply counts would be repaired in 1 topic', $dry_run[1] );
		$this->assertSame(
			2,
			(int) $db->get_var( $db->prepare( "SELECT COUNT(*) FROM {$db->postmeta} WHERE post_id = %d AND meta_key = '_bbp_reply_count_hidden'", $topic_id ) )
		);

		$this->assertSame( 0, bbp_admin_repair_topic_hidden_reply_count()[0] );
		$this->assertSame( array( '1' ), get_post_meta( $topic_id, '_bbp_reply_count_hidden', false ) );
	}

	/**
	 * @covers ::bbp_admin_repair_topic_hidden_reply_count
	 * @covers ::bbp_admin_repair_topic_hidden_reply_count_dry_run
	 * @covers ::bbp_admin_repair_topic_hidden_reply_count_process
	 */
	public function test_bbp_admin_repair_topic_hidden_reply_count_detects_malformed_meta_strings() {
		$db       = bbp_db();
		$topic_id = $this->factory->topic->create();
		$reply_id = $this->factory->reply->create( array( 'post_parent' => $topic_id ) );

		bbp_spam_reply( $reply_id );
		bbp_update_topic_reply_count_hidden( $topic_id, 1 );
		$this->assertSame(
			1,
			$db->update( $db->postmeta, array( 'meta_value' => '01' ), array( 'post_id' => $topic_id, 'meta_key' => '_bbp_reply_count_hidden' ) )
		);

		$dry_run = bbp_admin_repair_topic_hidden_reply_count_dry_run();
		$this->assertSame( 0, $dry_run[0] );
		$this->assertStringContainsString( 'Hidden reply counts would be repaired in 1 topic', $dry_run[1] );
		$this->assertSame(
			'01',
			$db->get_var( $db->prepare( "SELECT meta_value FROM {$db->postmeta} WHERE post_id = %d AND meta_key = '_bbp_reply_count_hidden'", $topic_id ) )
		);

		$this->assertSame( 0, bbp_admin_repair_topic_hidden_reply_count()[0] );
		$this->assertSame( array( '1' ), get_post_meta( $topic_id, '_bbp_reply_count_hidden', false ) );
	}

	/**
	 * @covers ::bbp_admin_repair_topic_hidden_reply_count
	 * @covers ::bbp_admin_repair_topic_hidden_reply_count_dry_run
	 * @covers ::bbp_admin_repair_topic_hidden_reply_count_process
	 */
	public function test_bbp_admin_repair_topic_hidden_reply_count_removes_meta_without_hidden_replies() {
		$topic_id = $this->factory->topic->create();

		update_post_meta( $topic_id, '_bbp_reply_count_hidden', 7 );
		$dry_run = bbp_admin_repair_topic_hidden_reply_count_dry_run();
		$this->assertSame( 0, $dry_run[0] );
		$this->assertStringContainsString( 'Hidden reply counts would be repaired in 1 topic', $dry_run[1] );
		$this->assertSame( '7', get_post_meta( $topic_id, '_bbp_reply_count_hidden', true ) );

		$this->assertSame( 0, bbp_admin_repair_topic_hidden_reply_count()[0] );
		$this->assertFalse( metadata_exists( 'post', $topic_id, '_bbp_reply_count_hidden' ) );
	}

	/**
	 * @covers ::bbp_admin_repair_topic_hidden_reply_count
	 * @covers ::bbp_admin_repair_topic_hidden_reply_count_dry_run
	 * @covers ::bbp_admin_repair_topic_hidden_reply_count_process
	 */
	public function test_bbp_admin_repair_topic_hidden_reply_count_removes_stored_zero_without_hidden_replies() {
		$topic_id = $this->factory->topic->create();

		$this->assertSame( '0', get_post_meta( $topic_id, '_bbp_reply_count_hidden', true ) );
		$dry_run = bbp_admin_repair_topic_hidden_reply_count_dry_run();
		$this->assertSame( 0, $dry_run[0] );
		$this->assertStringContainsString( 'Hidden reply counts would be repaired in 1 topic', $dry_run[1] );
		$this->assertSame( '0', get_post_meta( $topic_id, '_bbp_reply_count_hidden', true ) );

		$this->assertSame( 0, bbp_admin_repair_topic_hidden_reply_count()[0] );
		$this->assertFalse( metadata_exists( 'post', $topic_id, '_bbp_reply_count_hidden' ) );
	}

	/**
	 * @covers ::bbp_admin_repair_topic_hidden_reply_count
	 * @covers ::bbp_admin_repair_topic_hidden_reply_count_dry_run
	 * @covers ::bbp_admin_repair_topic_hidden_reply_count_process
	 */
	public function test_bbp_admin_repair_topic_hidden_reply_count_run_clears_stale_cache_when_database_matches() {
		$topic_id = $this->factory->topic->create();
		$reply_id = $this->factory->reply->create( array( 'post_parent' => $topic_id ) );

		bbp_spam_reply( $reply_id );
		bbp_update_topic_reply_count_hidden( $topic_id, 1 );
		$this->assertSame( '1', get_post_meta( $topic_id, '_bbp_reply_count_hidden', true ) );
		$meta_cache                            = wp_cache_get( $topic_id, 'post_meta' );
		$meta_cache['_bbp_reply_count_hidden'] = array( '99' );
		$this->assertTrue( wp_cache_set( $topic_id, $meta_cache, 'post_meta' ) );
		$this->assertSame( '99', get_post_meta( $topic_id, '_bbp_reply_count_hidden', true ) );

		$this->assertSame( 1, bbp_admin_repair_topic_hidden_reply_count_dry_run()[0] );
		$this->assertSame( '99', get_post_meta( $topic_id, '_bbp_reply_count_hidden', true ) );

		$this->assertSame( 0, bbp_admin_repair_topic_hidden_reply_count()[0] );
		$this->assertSame( '1', get_post_meta( $topic_id, '_bbp_reply_count_hidden', true ) );
	}

	/**
	 * @covers ::bbp_admin_repair_topic_hidden_reply_count_dry_run
	 * @covers ::bbp_admin_repair_topic_hidden_reply_count_process
	 */
	public function test_bbp_admin_repair_topic_hidden_reply_count_dry_run_counts_multiple_topics() {
		$topic_ids = $this->factory->topic->create_many( 3 );

		delete_post_meta_by_key( '_bbp_reply_count_hidden' );
		update_post_meta( reset( $topic_ids ), '_bbp_reply_count_hidden', 9 );
		update_post_meta( end( $topic_ids ), '_bbp_reply_count_hidden', 9 );

		$dry_run = bbp_admin_repair_topic_hidden_reply_count_dry_run();
		$this->assertSame( 0, $dry_run[0] );
		$this->assertStringContainsString( 'Hidden reply counts would be repaired in 2 topics', $dry_run[1] );
		$this->assertSame( '9', get_post_meta( reset( $topic_ids ), '_bbp_reply_count_hidden', true ) );
		$this->assertSame( '9', get_post_meta( end( $topic_ids ), '_bbp_reply_count_hidden', true ) );
	}

	/**
	 * @covers ::bbp_admin_repair_forum_topic_count
	 */
	public function test_bbp_admin_repair_forum_topic_count() {
		$c = $this->factory->forum->create( array(
			'forum_meta' => array(
				'forum_type' => 'category',
				'status'     => 'open',
			),
		) );

		$f = $this->factory->forum->create( array(
			'post_parent' => $c,
			'forum_meta' => array(
				'forum_id'   => $c,
				'forum_type' => 'forum',
				'status'     => 'open',
			),
		) );

		$t = $this->factory->topic->create( array(
			'post_parent' => $f,
			'topic_meta' => array(
				'forum_id' => $f,
			),
		) );

		$count = bbp_get_forum_topic_count( $f, true, true );
		$this->assertSame( 1, $count );

		$t = $this->factory->topic->create_many( 3, array(
			'post_parent' => $f,
			'topic_meta' => array(
				'forum_id' => $f,
			),
		) );

		bbp_update_forum_topic_count( $c );
		bbp_update_forum_topic_count( $f );

		// Category topic count.
		$count = bbp_get_forum_topic_count( $c, false, true );
		$this->assertSame( 0, $count );

		// Category total topic count.
		$count = bbp_get_forum_topic_count( $c, true, true );
		$this->assertSame( 4, $count );

		// Category topic count hidden.
		$count = bbp_get_forum_topic_count_hidden( $c, true, true );
		$this->assertSame( 0, $count );

		// Forum topic count.
		$count = bbp_get_forum_topic_count( $f, false, true );
		$this->assertSame( 4, $count );

		// Forum total topic count.
		$count = bbp_get_forum_topic_count( $f, true, true );
		$this->assertSame( 4, $count );

		// Forum topic count hidden.
		$count = bbp_get_forum_topic_count_hidden( $f, true, true );
		$this->assertSame( 0, $count );

		bbp_spam_topic( $t[0] );
		bbp_unapprove_topic( $t[2] );

		// Category topic count.
		$count = bbp_get_forum_topic_count( $c, false, true );
		$this->assertSame( 0, $count );

		// Category total topic count.
		$count = bbp_get_forum_topic_count( $c, true, true );
		$this->assertSame( 2, $count );

		// Category topic count hidden.
		$count = bbp_get_forum_topic_count_hidden( $c, false, true );
		$this->assertSame( 0, $count );

		// Category total topic count hidden.
		$count = bbp_get_forum_topic_count_hidden( $c, true, true );
		$this->assertSame( 2, $count );

		// Forum topic count.
		$count = bbp_get_forum_topic_count( $f, false, true );
		$this->assertSame( 2, $count );

		// Forum total topic count.
		$count = bbp_get_forum_topic_count( $f, true, true );
		$this->assertSame( 2, $count );

		// Forum topic count hidden.
		$count = bbp_get_forum_topic_count_hidden( $f, true, true );
		$this->assertSame( 2, $count );

		// Delete the _bbp_topic_count_hidden meta key.
		$this->assertTrue( delete_post_meta_by_key( '_bbp_topic_count_hidden' ) );

		// Delete the _bbp_total_topic_count_hidden meta key.
		$this->assertTrue( delete_post_meta_by_key( '_bbp_total_topic_count_hidden' ) );

		// Delete the _bbp_total_topic_count meta key.
		$this->assertTrue( delete_post_meta_by_key( '_bbp_total_topic_count' ) );

		// Delete the  _bbp_topic_count meta key.
		$this->assertTrue( delete_post_meta_by_key( '_bbp_topic_count' ) );

		// Category topic count.
		$count = bbp_get_forum_topic_count( $c, false, true );
		$this->assertSame( 0, $count );

		// Category total topic count.
		$count = bbp_get_forum_topic_count( $c, true, true );
		$this->assertSame( 0, $count );

		// Category topic count hidden.
		$count = bbp_get_forum_topic_count_hidden( $c, false, true );
		$this->assertSame( 0, $count );

		// Category total topic count hidden.
		$count = bbp_get_forum_topic_count_hidden( $c, true, true );
		$this->assertSame( 0, $count );

		// Forum topic count.
		$count = bbp_get_forum_topic_count( $f, false, true );
		$this->assertSame( 0, $count );

		// Forum total topic count.
		$count = bbp_get_forum_topic_count( $f, true, true );
		$this->assertSame( 0, $count );

		// Forum topic count hidden.
		$count = bbp_get_forum_topic_count_hidden( $f, false, true );
		$this->assertSame( 0, $count );

		// Repair the forum topic count meta.
		bbp_admin_repair_forum_topic_count();

		// Category topic count.
		$count = bbp_get_forum_topic_count( $c, false, true );
		$this->assertSame( 0, $count );

		// Category total topic count.
		$count = bbp_get_forum_topic_count( $c, true, true );
		$this->assertSame( 2, $count );

		// Category topic count hidden.
		$count = bbp_get_forum_topic_count_hidden( $c, false, true );
		$this->assertSame( 0, $count );

		// Category total topic count hidden.
		$count = bbp_get_forum_topic_count_hidden( $c, true, true );
		$this->assertSame( 2, $count );

		// Forum topic count.
		$count = bbp_get_forum_topic_count( $f, false, true );
		$this->assertSame( 2, $count );

		// Forum total topic count.
		$count = bbp_get_forum_topic_count( $f, true, true );
		$this->assertSame( 2, $count );

		// Forum topic count hidden.
		$count = bbp_get_forum_topic_count_hidden( $f, true, true );
		$this->assertSame( 2, $count );
	}

	/**
	 * @covers ::bbp_admin_repair_forum_subforum_count
	 * @ticket BBP3332
	 */
	public function test_bbp_admin_repair_forum_subforum_count() {
		$parent_ids = array();

		foreach ( array( bbp_get_public_status_id(), bbp_get_private_status_id(), bbp_get_hidden_status_id(), 'draft', bbp_get_trash_status_id() ) as $status ) {
			$parent_id    = $this->factory->forum->create( array( 'post_status' => $status ) );
			$parent_ids[] = $parent_id;

			foreach ( array( bbp_get_public_status_id(), bbp_get_private_status_id(), bbp_get_hidden_status_id() ) as $child_status ) {
				$child_id = $this->factory->forum->create(
					array(
						'post_parent' => $parent_id,
						'post_status' => $child_status,
					)
				);
			}

			$this->factory->forum->create( array( 'post_parent' => $child_id ) );
			bbp_update_forum_subforum_count( $parent_id, 99 );
		}

		$empty_forum_id = $this->factory->forum->create();
		bbp_update_forum_subforum_count( $empty_forum_id, 99 );

		$result = bbp_admin_repair_forum_subforum_count();

		$this->assertSame( 0, $result[0] );

		foreach ( $parent_ids as $parent_id ) {
			$this->assertSame( 3, bbp_get_forum_subforum_count( $parent_id, true ) );
		}
		$this->assertSame( 0, bbp_get_forum_subforum_count( $empty_forum_id, true ) );
	}

	/**
	 * @covers ::bbp_admin_repair_forum_hidden_reply_count
	 */
	public function test_bbp_admin_repair_forum_hidden_reply_count() {
		$c = $this->factory->forum->create( array(
			'forum_meta' => array( 'forum_type' => 'category' ),
		) );
		$f = $this->factory->forum->create( array(
			'post_parent' => $c,
			'forum_meta'  => array( 'forum_id' => $c ),
		) );
		$t = $this->factory->topic->create( array(
			'post_parent' => $f,
			'topic_meta'  => array( 'forum_id' => $f ),
		) );

		$this->factory->reply->create( array(
			'post_parent' => $t,
			'post_status' => bbp_get_pending_status_id(),
			'reply_meta'  => array(
				'forum_id' => $f,
				'topic_id' => $t,
			),
		) );

		$this->assertSame( 0, bbp_get_forum_reply_count_hidden( $c, false, true ) );
		$this->assertSame( 1, bbp_get_forum_reply_count_hidden( $c, true, true ) );
		$this->assertSame( 1, bbp_get_forum_reply_count_hidden( $f, false, true ) );
		$this->assertSame( 1, bbp_get_forum_reply_count_hidden( $f, true, true ) );

		$this->assertTrue( delete_post_meta_by_key( '_bbp_reply_count_hidden' ) );
		$this->assertTrue( delete_post_meta_by_key( '_bbp_total_reply_count_hidden' ) );

		bbp_admin_repair_forum_hidden_reply_count();

		$this->assertSame( 0, bbp_get_forum_reply_count_hidden( $c, false, true ) );
		$this->assertSame( 1, bbp_get_forum_reply_count_hidden( $c, true, true ) );
		$this->assertSame( 1, bbp_get_forum_reply_count_hidden( $f, false, true ) );
		$this->assertSame( 1, bbp_get_forum_reply_count_hidden( $f, true, true ) );
	}

	/**
	 * @covers ::bbp_admin_repair_forum_reply_count
	 */
	public function test_bbp_admin_repair_forum_reply_count() {
		$c = $this->factory->forum->create( array(
			'forum_meta' => array(
				'forum_type' => 'category',
				'status'     => 'open',
			),
		) );

		$f = $this->factory->forum->create( array(
			'post_parent' => $c,
			'forum_meta' => array(
				'forum_id'   => $c,
				'forum_type' => 'forum',
				'status'     => 'open',
			),
		) );

		$t = $this->factory->topic->create( array(
			'post_parent' => $f,
			'topic_meta' => array(
				'forum_id' => $f,
			),
		) );

		$r = $this->factory->reply->create( array(
			'post_parent' => $t,
			'reply_meta' => array(
				'forum_id' => $f,
				'topic_id' => $t,
			),
		) );

		$count = bbp_get_forum_reply_count( $f, true, true );
		$this->assertSame( 1, $count );

		$r = $this->factory->reply->create_many( 3, array(
			'post_parent' => $t,
			'reply_meta' => array(
				'forum_id' => $f,
				'topic_id' => $t,
			),
		) );

		bbp_update_forum_reply_count( $c );
		bbp_update_forum_reply_count( $f );

		// Category reply count.
		$count = bbp_get_forum_reply_count( $c, false, true );
		$this->assertSame( 0, $count );

		// Category total reply count.
		$count = bbp_get_forum_reply_count( $c, true, true );
		$this->assertSame( 4, $count );

		// Forum reply count.
		$count = bbp_get_forum_reply_count( $f, false, true );
		$this->assertSame( 4, $count );

		// Forum total reply count.
		$count = bbp_get_forum_reply_count( $f, true, true );
		$this->assertSame( 4, $count );

		bbp_spam_reply( $r[0] );
		bbp_unapprove_reply( $r[2] );

		// Category reply count.
		$count = bbp_get_forum_reply_count( $c, false, true );
		$this->assertSame( 0, $count );

		// Category total reply count.
		$count = bbp_get_forum_reply_count( $c, true, true );
		$this->assertSame( 2, $count );

		// Forum reply count.
		$count = bbp_get_forum_reply_count( $f, false, true );
		$this->assertSame( 2, $count );

		// Forum total reply count.
		$count = bbp_get_forum_reply_count( $f, true, true );
		$this->assertSame( 2, $count );

		// Delete the _bbp_total_reply_count meta key.
		$this->assertTrue( delete_post_meta_by_key( '_bbp_total_reply_count' ) );

		// Delete the _bbp_reply_count meta key.
		$this->assertTrue( delete_post_meta_by_key( '_bbp_reply_count' ) );

		// Category reply count.
		$count = bbp_get_forum_reply_count( $c, false, true );
		$this->assertSame( 0, $count );

		// Category total reply count.
		$count = bbp_get_forum_reply_count( $c, true, true );
		$this->assertSame( 0, $count );

		// Forum reply count.
		$count = bbp_get_forum_reply_count( $f, false, true );
		$this->assertSame( 0, $count );

		// Forum total reply count.
		$count = bbp_get_forum_reply_count( $f, true, true );
		$this->assertSame( 0, $count );

		// Repair the forum reply count meta.
		bbp_admin_repair_forum_reply_count();

		// Category reply count.
		$count = bbp_get_forum_reply_count( $c, false, true );
		$this->assertSame( 0, $count );

		// Category total reply count.
		$count = bbp_get_forum_reply_count( $c, true, true );
		$this->assertSame( 2, $count );

		// Forum reply count.
		$count = bbp_get_forum_reply_count( $f, false, true );
		$this->assertSame( 2, $count );

		// Forum total reply count.
		$count = bbp_get_forum_reply_count( $f, true, true );
		$this->assertSame( 2, $count );
	}

	/**
	 * @covers ::bbp_admin_repair_forum_topic_count
	 * @covers ::bbp_admin_repair_forum_reply_count
	 * @covers ::bbp_admin_repair_forum_hidden_reply_count
	 */
	public function test_forum_count_repairs_include_private_and_hidden_forums() {
		$forum_ids = array();

		foreach ( array( bbp_get_private_status_id(), bbp_get_hidden_status_id() ) as $status ) {
			$forum_id   = $this->factory->forum->create( array( 'post_status' => $status ) );
			$forum_ids[] = $forum_id;
			$topic_id   = $this->factory->topic->create( array(
				'post_parent' => $forum_id,
				'topic_meta'  => array( 'forum_id' => $forum_id ),
			) );

			$this->factory->reply->create( array(
				'post_parent' => $topic_id,
				'reply_meta'  => array(
					'forum_id' => $forum_id,
					'topic_id' => $topic_id,
				),
			) );
			$this->factory->reply->create( array(
				'post_parent' => $topic_id,
				'post_status' => bbp_get_spam_status_id(),
				'reply_meta'  => array(
					'forum_id' => $forum_id,
					'topic_id' => $topic_id,
				),
			) );
		}

		bbp_admin_repair_forum_topic_count();
		bbp_admin_repair_forum_reply_count();
		bbp_admin_repair_forum_hidden_reply_count();

		foreach ( $forum_ids as $forum_id ) {
			$this->assertSame( 1, bbp_get_forum_topic_count( $forum_id, true, true ) );
			$this->assertSame( 1, bbp_get_forum_reply_count( $forum_id, true, true ) );
			$this->assertSame( 1, bbp_get_forum_reply_count_hidden( $forum_id, true, true ) );
		}
	}

	/**
	 * @covers ::bbp_admin_repair_topic_voice_count
	 * @covers ::bbp_admin_repair_topic_hidden_reply_count
	 * @covers ::bbp_admin_repair_forum_topic_count
	 * @covers ::bbp_admin_repair_forum_reply_count
	 * @covers ::bbp_admin_repair_forum_hidden_reply_count
	 * @ticket BBP2586
	 */
	public function test_count_repairs_preserve_non_bbp_post_meta() {
		$this->factory->forum->create();
		$post_id  = $this->factory->post->create();
		$reply_id = $this->factory->post->create( array(
			'post_author' => $this->factory->user->create(),
			'post_parent' => $post_id,
			'post_status' => bbp_get_public_status_id(),
			'post_type'   => bbp_get_reply_post_type(),
		) );

		update_post_meta( $reply_id, '_bbp_topic_id', $post_id );
		$this->factory->post->create( array(
			'post_parent' => $post_id,
			'post_status' => bbp_get_spam_status_id(),
			'post_type'   => bbp_get_reply_post_type(),
		) );

		$metadata = array(
			'_bbp_reply_count_hidden'       => 4,
			'_bbp_voice_count'              => 5,
			'_bbp_engagement'               => 6,
			'_bbp_total_reply_count'        => 7,
			'_bbp_total_reply_count_hidden' => 8,
			'_bbp_topic_count'              => 9,
			'_bbp_total_topic_count'        => 10,
			'_bbp_topic_count_hidden'       => 11,
		);

		foreach ( $metadata as $key => $value ) {
			update_post_meta( $post_id, $key, $value );
		}

		bbp_admin_repair_topic_voice_count();
		bbp_admin_repair_topic_hidden_reply_count();
		bbp_admin_repair_forum_topic_count();
		bbp_admin_repair_forum_reply_count();
		bbp_admin_repair_forum_hidden_reply_count();

		foreach ( $metadata as $key => $value ) {
			$this->assertSame( array( (string) $value ), get_post_meta( $post_id, $key, false ) );
		}
	}

	/**
	 * @covers ::bbp_admin_repair_user_topic_count
	 * @covers ::bbp_admin_repair_user_topic_count_dry_run
	 * @covers ::bbp_admin_repair_user_topic_count_process
	 * @ticket BBP2664
	 */
	public function test_bbp_admin_repair_user_topic_count() {
		$user_id = $this->factory->user->create();

		$this->factory->topic->create_many( 2, array( 'post_author' => $user_id ) );
		$this->factory->topic->create( array(
			'post_author' => $user_id,
			'post_status' => bbp_get_spam_status_id(),
		) );
		bbp_update_user_topic_count( $user_id, 9 );
		$this->assertSame( 9, bbp_get_user_topic_count( $user_id, true ) );

		$dry_run = bbp_admin_repair_user_topic_count_dry_run();
		$this->assertSame( 0, $dry_run[0] );
		$this->assertStringContainsString( 'Topic counts would be repaired for 1 user', $dry_run[1] );
		$this->assertSame( 9, bbp_get_user_topic_count( $user_id, true ) );

		$result = bbp_admin_repair_user_topic_count();
		$this->assertSame( 0, $result[0] );
		$this->assertSame( 2, bbp_get_user_topic_count( $user_id, true ) );
		$this->assertSame( 1, bbp_admin_repair_user_topic_count_dry_run()[0] );
	}

	/**
	 * @covers ::bbp_admin_repair_user_topic_count
	 * @covers ::bbp_admin_repair_user_topic_count_dry_run
	 * @covers ::bbp_admin_repair_user_topic_count_process
	 */
	public function test_bbp_admin_repair_user_topic_count_uses_current_database_values() {
		$db      = bbp_db();
		$user_id = $this->factory->user->create();

		$this->factory->topic->create( array( 'post_author' => $user_id ) );
		bbp_update_user_topic_count( $user_id, 1 );
		$this->assertNotFalse(
			$db->insert(
				$db->usermeta,
				array(
					'user_id'    => $user_id,
					'meta_key'   => $db->prefix . '_bbp_topic_count',
					'meta_value' => 1,
				)
			)
		);

		$dry_run = bbp_admin_repair_user_topic_count_dry_run();
		$this->assertSame( 0, $dry_run[0] );
		$this->assertStringContainsString( 'Topic counts would be repaired for 1 user', $dry_run[1] );
		$this->assertSame(
			2,
			(int) $db->get_var( $db->prepare( "SELECT COUNT(*) FROM {$db->usermeta} WHERE user_id = %d AND meta_key = %s", $user_id, $db->prefix . '_bbp_topic_count' ) )
		);

		$this->assertSame( 0, bbp_admin_repair_user_topic_count()[0] );
		$this->assertSame( array( '1' ), get_user_meta( $user_id, $db->prefix . '_bbp_topic_count', false ) );
	}

	/**
	 * @covers ::bbp_admin_repair_user_topic_count
	 * @covers ::bbp_admin_repair_user_topic_count_dry_run
	 * @covers ::bbp_admin_repair_user_topic_count_process
	 */
	public function test_bbp_admin_repair_user_topic_count_repairs_missing_meta() {
		$db      = bbp_db();
		$user_id = $this->factory->user->create();

		$this->factory->topic->create( array( 'post_author' => $user_id ) );
		delete_user_meta( $user_id, $db->prefix . '_bbp_topic_count' );

		$dry_run = bbp_admin_repair_user_topic_count_dry_run();
		$this->assertSame( 0, $dry_run[0] );
		$this->assertStringContainsString( 'Topic counts would be repaired for 1 user', $dry_run[1] );
		$this->assertFalse( metadata_exists( 'user', $user_id, $db->prefix . '_bbp_topic_count' ) );

		$this->assertSame( 0, bbp_admin_repair_user_topic_count()[0] );
		$this->assertSame( array( '1' ), get_user_meta( $user_id, $db->prefix . '_bbp_topic_count', false ) );
	}

	/**
	 * @covers ::bbp_admin_repair_user_topic_count
	 * @covers ::bbp_admin_repair_user_topic_count_dry_run
	 * @covers ::bbp_admin_repair_user_topic_count_process
	 */
	public function test_bbp_admin_repair_user_topic_count_detects_malformed_meta_strings() {
		$db      = bbp_db();
		$user_id = $this->factory->user->create();

		$this->factory->topic->create_many( 2, array( 'post_author' => $user_id ) );
		bbp_update_user_topic_count( $user_id, 2 );
		$this->assertSame(
			1,
			$db->update( $db->usermeta, array( 'meta_value' => '02' ), array( 'user_id' => $user_id, 'meta_key' => $db->prefix . '_bbp_topic_count' ) )
		);

		$dry_run = bbp_admin_repair_user_topic_count_dry_run();
		$this->assertSame( 0, $dry_run[0] );
		$this->assertStringContainsString( 'Topic counts would be repaired for 1 user', $dry_run[1] );
		$this->assertSame(
			'02',
			$db->get_var( $db->prepare( "SELECT meta_value FROM {$db->usermeta} WHERE user_id = %d AND meta_key = %s", $user_id, $db->prefix . '_bbp_topic_count' ) )
		);

		$this->assertSame( 0, bbp_admin_repair_user_topic_count()[0] );
		$this->assertSame( array( '2' ), get_user_meta( $user_id, $db->prefix . '_bbp_topic_count', false ) );
	}

	/**
	 * @covers ::bbp_admin_repair_user_topic_count
	 * @covers ::bbp_admin_repair_user_topic_count_dry_run
	 * @covers ::bbp_admin_repair_user_topic_count_process
	 */
	public function test_bbp_admin_repair_user_topic_count_run_clears_stale_cache_when_database_matches() {
		$db      = bbp_db();
		$user_id = $this->factory->user->create();

		$this->factory->topic->create( array( 'post_author' => $user_id ) );
		bbp_update_user_topic_count( $user_id, 1 );
		$this->assertSame( '1', get_user_meta( $user_id, $db->prefix . '_bbp_topic_count', true ) );
		$meta_cache = wp_cache_get( $user_id, 'user_meta' );
		$meta_cache[ $db->prefix . '_bbp_topic_count' ] = array( '99' );
		$this->assertTrue( wp_cache_set( $user_id, $meta_cache, 'user_meta' ) );
		$this->assertSame( '99', get_user_meta( $user_id, $db->prefix . '_bbp_topic_count', true ) );

		$this->assertSame( 1, bbp_admin_repair_user_topic_count_dry_run()[0] );
		$this->assertSame( '99', get_user_meta( $user_id, $db->prefix . '_bbp_topic_count', true ) );

		$this->assertSame( 0, bbp_admin_repair_user_topic_count()[0] );
		$this->assertSame( '1', get_user_meta( $user_id, $db->prefix . '_bbp_topic_count', true ) );
	}

	/**
	 * @covers ::bbp_admin_repair_user_topic_count_dry_run
	 * @covers ::bbp_admin_repair_user_topic_count_process
	 */
	public function test_bbp_admin_repair_user_topic_count_dry_run_counts_multiple_users() {
		$user_ids = $this->factory->user->create_many( 3 );

		foreach ( $user_ids as $user_id ) {
			$this->factory->topic->create( array( 'post_author' => $user_id ) );
			bbp_update_user_topic_count( $user_id, 9 );
		}

		$dry_run = bbp_admin_repair_user_topic_count_dry_run();
		$this->assertSame( 0, $dry_run[0] );
		$this->assertStringContainsString( 'Topic counts would be repaired for 3 users', $dry_run[1] );

		foreach ( $user_ids as $user_id ) {
			$this->assertSame( 9, bbp_get_user_topic_count( $user_id, true ) );
		}
	}

	/**
	 * @covers ::bbp_admin_repair_user_reply_count
	 * @covers ::bbp_admin_repair_user_reply_count_dry_run
	 * @covers ::bbp_admin_repair_user_reply_count_process
	 * @ticket BBP2664
	 */
	public function test_bbp_admin_repair_user_reply_count() {
		$user_id = $this->factory->user->create();
		$topic_id = $this->factory->topic->create();

		$this->factory->reply->create_many( 2, array(
			'post_author' => $user_id,
			'post_parent' => $topic_id,
		) );
		$this->factory->reply->create( array(
			'post_author' => $user_id,
			'post_parent' => $topic_id,
			'post_status' => bbp_get_spam_status_id(),
		) );
		bbp_update_user_reply_count( $user_id, 9 );
		$this->assertSame( 9, bbp_get_user_reply_count( $user_id, true ) );

		$dry_run = bbp_admin_repair_user_reply_count_dry_run();
		$this->assertSame( 0, $dry_run[0] );
		$this->assertStringContainsString( 'Reply counts would be repaired for 1 user', $dry_run[1] );
		$this->assertSame( 9, bbp_get_user_reply_count( $user_id, true ) );

		$result = bbp_admin_repair_user_reply_count();
		$this->assertSame( 0, $result[0] );
		$this->assertSame( 2, bbp_get_user_reply_count( $user_id, true ) );
		$this->assertSame( 1, bbp_admin_repair_user_reply_count_dry_run()[0] );
	}

	/**
	 * @covers ::bbp_admin_repair_user_reply_count
	 * @covers ::bbp_admin_repair_user_reply_count_dry_run
	 * @covers ::bbp_admin_repair_user_reply_count_process
	 */
	public function test_bbp_admin_repair_user_reply_count_uses_current_database_values() {
		$db       = bbp_db();
		$user_id  = $this->factory->user->create();
		$topic_id = $this->factory->topic->create();

		$this->factory->reply->create( array( 'post_author' => $user_id, 'post_parent' => $topic_id ) );
		bbp_update_user_reply_count( $user_id, 1 );
		$this->assertNotFalse(
			$db->insert(
				$db->usermeta,
				array(
					'user_id'    => $user_id,
					'meta_key'   => $db->prefix . '_bbp_reply_count',
					'meta_value' => 1,
				)
			)
		);

		$dry_run = bbp_admin_repair_user_reply_count_dry_run();
		$this->assertSame( 0, $dry_run[0] );
		$this->assertStringContainsString( 'Reply counts would be repaired for 1 user', $dry_run[1] );
		$this->assertSame(
			2,
			(int) $db->get_var( $db->prepare( "SELECT COUNT(*) FROM {$db->usermeta} WHERE user_id = %d AND meta_key = %s", $user_id, $db->prefix . '_bbp_reply_count' ) )
		);

		$this->assertSame( 0, bbp_admin_repair_user_reply_count()[0] );
		$this->assertSame( array( '1' ), get_user_meta( $user_id, $db->prefix . '_bbp_reply_count', false ) );
	}

	/**
	 * @covers ::bbp_admin_repair_user_reply_count
	 * @covers ::bbp_admin_repair_user_reply_count_dry_run
	 * @covers ::bbp_admin_repair_user_reply_count_process
	 */
	public function test_bbp_admin_repair_user_reply_count_repairs_missing_meta() {
		$db       = bbp_db();
		$user_id  = $this->factory->user->create();
		$topic_id = $this->factory->topic->create();

		$this->factory->reply->create( array( 'post_author' => $user_id, 'post_parent' => $topic_id ) );
		delete_user_meta( $user_id, $db->prefix . '_bbp_reply_count' );

		$dry_run = bbp_admin_repair_user_reply_count_dry_run();
		$this->assertSame( 0, $dry_run[0] );
		$this->assertStringContainsString( 'Reply counts would be repaired for 1 user', $dry_run[1] );
		$this->assertFalse( metadata_exists( 'user', $user_id, $db->prefix . '_bbp_reply_count' ) );

		$this->assertSame( 0, bbp_admin_repair_user_reply_count()[0] );
		$this->assertSame( array( '1' ), get_user_meta( $user_id, $db->prefix . '_bbp_reply_count', false ) );
	}

	/**
	 * @covers ::bbp_admin_repair_user_reply_count
	 * @covers ::bbp_admin_repair_user_reply_count_dry_run
	 * @covers ::bbp_admin_repair_user_reply_count_process
	 */
	public function test_bbp_admin_repair_user_reply_count_detects_malformed_meta_strings() {
		$db       = bbp_db();
		$user_id  = $this->factory->user->create();
		$topic_id = $this->factory->topic->create();

		$this->factory->reply->create_many( 2, array( 'post_author' => $user_id, 'post_parent' => $topic_id ) );
		bbp_update_user_reply_count( $user_id, 2 );
		$this->assertSame(
			1,
			$db->update( $db->usermeta, array( 'meta_value' => '02' ), array( 'user_id' => $user_id, 'meta_key' => $db->prefix . '_bbp_reply_count' ) )
		);

		$dry_run = bbp_admin_repair_user_reply_count_dry_run();
		$this->assertSame( 0, $dry_run[0] );
		$this->assertStringContainsString( 'Reply counts would be repaired for 1 user', $dry_run[1] );
		$this->assertSame(
			'02',
			$db->get_var( $db->prepare( "SELECT meta_value FROM {$db->usermeta} WHERE user_id = %d AND meta_key = %s", $user_id, $db->prefix . '_bbp_reply_count' ) )
		);

		$this->assertSame( 0, bbp_admin_repair_user_reply_count()[0] );
		$this->assertSame( array( '2' ), get_user_meta( $user_id, $db->prefix . '_bbp_reply_count', false ) );
	}

	/**
	 * @covers ::bbp_admin_repair_user_reply_count
	 * @covers ::bbp_admin_repair_user_reply_count_dry_run
	 * @covers ::bbp_admin_repair_user_reply_count_process
	 */
	public function test_bbp_admin_repair_user_reply_count_run_clears_stale_cache_when_database_matches() {
		$db       = bbp_db();
		$user_id  = $this->factory->user->create();
		$topic_id = $this->factory->topic->create();

		$this->factory->reply->create( array( 'post_author' => $user_id, 'post_parent' => $topic_id ) );
		bbp_update_user_reply_count( $user_id, 1 );
		$this->assertSame( '1', get_user_meta( $user_id, $db->prefix . '_bbp_reply_count', true ) );
		$meta_cache = wp_cache_get( $user_id, 'user_meta' );
		$meta_cache[ $db->prefix . '_bbp_reply_count' ] = array( '99' );
		$this->assertTrue( wp_cache_set( $user_id, $meta_cache, 'user_meta' ) );
		$this->assertSame( '99', get_user_meta( $user_id, $db->prefix . '_bbp_reply_count', true ) );

		$this->assertSame( 1, bbp_admin_repair_user_reply_count_dry_run()[0] );
		$this->assertSame( '99', get_user_meta( $user_id, $db->prefix . '_bbp_reply_count', true ) );

		$this->assertSame( 0, bbp_admin_repair_user_reply_count()[0] );
		$this->assertSame( '1', get_user_meta( $user_id, $db->prefix . '_bbp_reply_count', true ) );
	}

	/**
	 * @covers ::bbp_admin_repair_user_reply_count_dry_run
	 * @covers ::bbp_admin_repair_user_reply_count_process
	 */
	public function test_bbp_admin_repair_user_reply_count_dry_run_counts_multiple_users() {
		$user_ids = $this->factory->user->create_many( 3 );
		$topic_id = $this->factory->topic->create();

		foreach ( $user_ids as $user_id ) {
			$this->factory->reply->create( array( 'post_author' => $user_id, 'post_parent' => $topic_id ) );
			bbp_update_user_reply_count( $user_id, 9 );
		}

		$dry_run = bbp_admin_repair_user_reply_count_dry_run();
		$this->assertSame( 0, $dry_run[0] );
		$this->assertStringContainsString( 'Reply counts would be repaired for 3 users', $dry_run[1] );

		foreach ( $user_ids as $user_id ) {
			$this->assertSame( 9, bbp_get_user_reply_count( $user_id, true ) );
		}
	}

	/**
	 * @covers ::bbp_admin_repair_user_topic_count
	 * @covers ::bbp_admin_repair_user_topic_count_dry_run
	 * @covers ::bbp_admin_repair_user_topic_count_process
	 * @covers ::bbp_admin_repair_user_reply_count
	 * @covers ::bbp_admin_repair_user_reply_count_dry_run
	 * @covers ::bbp_admin_repair_user_reply_count_process
	 * @ticket BBP2664
	 */
	public function test_user_count_repairs_clear_stale_counts_without_public_posts() {
		$user_id = $this->factory->user->create();

		bbp_update_user_topic_count( $user_id, 7 );
		bbp_update_user_reply_count( $user_id, 8 );
		$this->assertSame( 7, bbp_get_user_topic_count( $user_id, true ) );
		$this->assertSame( 8, bbp_get_user_reply_count( $user_id, true ) );

		$topic_dry_run = bbp_admin_repair_user_topic_count_dry_run();
		$this->assertSame( 0, $topic_dry_run[0] );
		$this->assertStringContainsString( 'Topic counts would be repaired for 1 user', $topic_dry_run[1] );
		$this->assertSame( 7, bbp_get_user_topic_count( $user_id, true ) );

		$reply_dry_run = bbp_admin_repair_user_reply_count_dry_run();
		$this->assertSame( 0, $reply_dry_run[0] );
		$this->assertStringContainsString( 'Reply counts would be repaired for 1 user', $reply_dry_run[1] );
		$this->assertSame( 8, bbp_get_user_reply_count( $user_id, true ) );

		$topic_result = bbp_admin_repair_user_topic_count();
		$reply_result = bbp_admin_repair_user_reply_count();

		$this->assertSame( 0, $topic_result[0] );
		$this->assertSame( 0, $reply_result[0] );
		$this->assertSame( 0, bbp_get_user_topic_count( $user_id, true ) );
		$this->assertSame( 0, bbp_get_user_reply_count( $user_id, true ) );
	}

	/**
	 * @covers ::bbp_admin_repair_user_topic_count
	 * @covers ::bbp_admin_repair_user_topic_count_dry_run
	 * @covers ::bbp_admin_repair_user_topic_count_process
	 */
	public function test_bbp_admin_repair_user_topic_count_removes_stored_zero_without_public_topics() {
		$db      = bbp_db();
		$user_id = $this->factory->user->create();

		bbp_update_user_topic_count( $user_id, 0 );
		$this->assertSame( '0', get_user_meta( $user_id, $db->prefix . '_bbp_topic_count', true ) );

		$dry_run = bbp_admin_repair_user_topic_count_dry_run();
		$this->assertSame( 0, $dry_run[0] );
		$this->assertStringContainsString( 'Topic counts would be repaired for 1 user', $dry_run[1] );
		$this->assertSame( '0', get_user_meta( $user_id, $db->prefix . '_bbp_topic_count', true ) );

		$this->assertSame( 0, bbp_admin_repair_user_topic_count()[0] );
		$this->assertFalse( metadata_exists( 'user', $user_id, $db->prefix . '_bbp_topic_count' ) );
	}

	/**
	 * @covers ::bbp_admin_repair_user_reply_count
	 * @covers ::bbp_admin_repair_user_reply_count_dry_run
	 * @covers ::bbp_admin_repair_user_reply_count_process
	 */
	public function test_bbp_admin_repair_user_reply_count_removes_stored_zero_without_public_replies() {
		$db      = bbp_db();
		$user_id = $this->factory->user->create();

		bbp_update_user_reply_count( $user_id, 0 );
		$this->assertSame( '0', get_user_meta( $user_id, $db->prefix . '_bbp_reply_count', true ) );

		$dry_run = bbp_admin_repair_user_reply_count_dry_run();
		$this->assertSame( 0, $dry_run[0] );
		$this->assertStringContainsString( 'Reply counts would be repaired for 1 user', $dry_run[1] );
		$this->assertSame( '0', get_user_meta( $user_id, $db->prefix . '_bbp_reply_count', true ) );

		$this->assertSame( 0, bbp_admin_repair_user_reply_count()[0] );
		$this->assertFalse( metadata_exists( 'user', $user_id, $db->prefix . '_bbp_reply_count' ) );
	}

	/**
	 * @covers ::bbp_admin_repair_user_favorites
	 * @covers ::bbp_admin_repair_user_favorites_dry_run
	 * @covers ::bbp_admin_repair_user_favorites_process
	 */
	public function test_bbp_admin_repair_user_favorites() {
		$db      = bbp_db();
		$user_id = $this->factory->user->create();
		$live_id = $this->factory->topic->create();
		$old_id  = $this->factory->topic->create();
		$key     = $db->prefix . '_bbp_favorites';
		wp_trash_post( $old_id );
		update_user_meta( $user_id, $key, "{$live_id},{$old_id}" );
		$this->assertSame( "{$live_id},{$old_id}", get_user_meta( $user_id, $key, true ) );

		$dry_run = bbp_admin_repair_user_favorites_dry_run();
		$this->assertSame( 0, $dry_run[0] );
		$this->assertStringContainsString( 'Favorites would be repaired for 1 user', $dry_run[1] );
		$this->assertSame( "{$live_id},{$old_id}", get_user_meta( $user_id, $key, true ) );
		$this->assertSame(
			"{$live_id},{$old_id}",
			$db->get_var( $db->prepare( "SELECT meta_value FROM {$db->usermeta} WHERE user_id = %d AND meta_key = %s", $user_id, $key ) )
		);

		$this->assertSame( 0, bbp_admin_repair_user_favorites()[0] );
		$this->assertSame( (string) $live_id, get_user_meta( $user_id, $key, true ) );
		$this->assertSame( 1, bbp_admin_repair_user_favorites_dry_run()[0] );
	}

	/**
	 * @covers ::bbp_admin_repair_user_favorites
	 * @covers ::bbp_admin_repair_user_favorites_dry_run
	 * @covers ::bbp_admin_repair_user_favorites_process
	 */
	public function test_bbp_admin_repair_user_favorites_does_nothing_without_surviving_favorites() {
		$db      = bbp_db();
		$user_id = $this->factory->user->create();
		$old_id  = $this->factory->topic->create();
		$key     = $db->prefix . '_bbp_favorites';

		wp_trash_post( $old_id );
		update_user_meta( $user_id, $key, $old_id );

		$dry_run = bbp_admin_repair_user_favorites_dry_run();
		$this->assertSame( 1, $dry_run[0] );
		$this->assertStringContainsString( 'No user favorites to repair', $dry_run[1] );
		$this->assertSame(
			(string) $old_id,
			$db->get_var( $db->prepare( "SELECT meta_value FROM {$db->usermeta} WHERE user_id = %d AND meta_key = %s", $user_id, $key ) )
		);

		$run = bbp_admin_repair_user_favorites();
		$this->assertSame( 0, $run[0] );
		$this->assertStringContainsString( 'Nothing to remove', $run[1] );
		$this->assertSame(
			(string) $old_id,
			$db->get_var( $db->prepare( "SELECT meta_value FROM {$db->usermeta} WHERE user_id = %d AND meta_key = %s", $user_id, $key ) )
		);
	}

	/**
	 * @covers ::bbp_admin_repair_user_favorites
	 * @covers ::bbp_admin_repair_user_favorites_dry_run
	 * @covers ::bbp_admin_repair_user_favorites_process
	 */
	public function test_bbp_admin_repair_user_favorites_removes_rows_without_surviving_favorites() {
		$db       = bbp_db();
		$user_ids = $this->factory->user->create_many( 2 );
		$live_id  = $this->factory->topic->create();
		$old_id   = $this->factory->topic->create();
		$key      = $db->prefix . '_bbp_favorites';

		wp_trash_post( $old_id );
		update_user_meta( $user_ids[0], $key, "{$live_id},{$old_id}" );
		update_user_meta( $user_ids[1], $key, $old_id );

		$dry_run = bbp_admin_repair_user_favorites_dry_run();
		$this->assertSame( 0, $dry_run[0] );
		$this->assertStringContainsString( 'Favorites would be repaired for 2 users', $dry_run[1] );
		$this->assertSame( 2, (int) $db->get_var( "SELECT COUNT(*) FROM {$db->usermeta} WHERE meta_key = '{$key}'" ) );

		$this->assertSame( 0, bbp_admin_repair_user_favorites()[0] );
		$this->assertSame(
			(string) $live_id,
			$db->get_var( $db->prepare( "SELECT meta_value FROM {$db->usermeta} WHERE user_id = %d AND meta_key = %s", $user_ids[0], $key ) )
		);
		$this->assertNull(
			$db->get_var( $db->prepare( "SELECT meta_value FROM {$db->usermeta} WHERE user_id = %d AND meta_key = %s", $user_ids[1], $key ) )
		);
	}

	/**
	 * @covers ::bbp_admin_repair_user_favorites
	 * @covers ::bbp_admin_repair_user_favorites_dry_run
	 * @covers ::bbp_admin_repair_user_favorites_process
	 */
	public function test_bbp_admin_repair_user_favorites_repairs_duplicate_rows() {
		$db      = bbp_db();
		$user_id = $this->factory->user->create();
		$live_id = $this->factory->topic->create();
		$old_id  = $this->factory->topic->create();
		$key     = $db->prefix . '_bbp_favorites';

		wp_trash_post( $old_id );
		update_user_meta( $user_id, $key, "{$live_id},{$old_id}" );
		add_user_meta( $user_id, $key, $old_id );

		$dry_run = bbp_admin_repair_user_favorites_dry_run();
		$this->assertSame( 0, $dry_run[0] );
		$this->assertStringContainsString( 'Favorites would be repaired for 1 user', $dry_run[1] );
		$this->assertSame(
			2,
			(int) $db->get_var( $db->prepare( "SELECT COUNT(*) FROM {$db->usermeta} WHERE user_id = %d AND meta_key = %s", $user_id, $key ) )
		);

		$this->assertSame( 0, bbp_admin_repair_user_favorites()[0] );
		$this->assertSame( array( (string) $live_id ), get_user_meta( $user_id, $key, false ) );
	}

	/**
	 * @covers ::bbp_admin_repair_user_favorites
	 * @covers ::bbp_admin_repair_user_favorites_dry_run
	 * @covers ::bbp_admin_repair_user_favorites_process
	 */
	public function test_bbp_admin_repair_user_favorites_normalizes_repeated_live_topics() {
		$db      = bbp_db();
		$user_id = $this->factory->user->create();
		$live_id = $this->factory->topic->create();
		$key     = $db->prefix . '_bbp_favorites';

		update_user_meta( $user_id, $key, "{$live_id},{$live_id}" );
		$dry_run = bbp_admin_repair_user_favorites_dry_run();
		$this->assertSame( 0, $dry_run[0] );
		$this->assertStringContainsString( 'Favorites would be repaired for 1 user', $dry_run[1] );
		$this->assertSame(
			"{$live_id},{$live_id}",
			$db->get_var( $db->prepare( "SELECT meta_value FROM {$db->usermeta} WHERE user_id = %d AND meta_key = %s", $user_id, $key ) )
		);

		$this->assertSame( 0, bbp_admin_repair_user_favorites()[0] );
		$this->assertSame( (string) $live_id, get_user_meta( $user_id, $key, true ) );
	}

	/**
	 * @covers ::bbp_admin_repair_user_favorites_dry_run
	 * @covers ::bbp_admin_repair_user_favorites_process
	 */
	public function test_bbp_admin_repair_user_favorites_dry_run_reports_read_errors() {
		$db              = bbp_db();
		$old_suppression = $db->suppress_errors( true );
		$old_usermeta    = $db->usermeta;
		$old_posts       = $db->posts;

		try {
			$db->usermeta = 'bbp_missing_favorites_usermeta';
			$this->assertSame( 1, bbp_admin_repair_user_favorites_dry_run()[0] );

			$db->usermeta = $old_usermeta;
			$db->posts    = 'bbp_missing_favorites_posts';
			$this->assertSame( 2, bbp_admin_repair_user_favorites_dry_run()[0] );
		} finally {
			$db->usermeta = $old_usermeta;
			$db->posts    = $old_posts;
			$db->suppress_errors( $old_suppression );
		}
	}

	/**
	 * @covers ::bbp_admin_repair_user_favorites
	 * @covers ::bbp_admin_repair_user_favorites_process
	 */
	public function test_bbp_admin_repair_user_favorites_reports_delete_errors() {
		$db              = bbp_db();
		$user_id         = $this->factory->user->create();
		$live_id         = $this->factory->topic->create();
		$key             = $db->prefix . '_bbp_favorites';
		$old_suppression = $db->suppress_errors( true );
		$query_filter    = static function ( $query ) use ( $db, $key ) {
			$sql_delete = "DELETE FROM `{$db->usermeta}` WHERE `meta_key` = '{$key}'";

			return ( $query === $sql_delete )
				? 'DELETE FROM bbp_missing_favorites_usermeta'
				: $query;
		};

		update_user_meta( $user_id, $key, "{$live_id},999999" );
		add_filter( 'query', $query_filter );

		try {
			$this->assertSame( 4, bbp_admin_repair_user_favorites()[0] );
			$this->assertSame( "{$live_id},999999", get_user_meta( $user_id, $key, true ) );
		} finally {
			remove_filter( 'query', $query_filter );
			$db->suppress_errors( $old_suppression );
		}
	}

	/**
	 * @covers ::bbp_admin_repair_user_favorites
	 * @covers ::bbp_admin_repair_user_favorites_process
	 */
	public function test_bbp_admin_repair_user_favorites_reports_insert_errors_and_clears_cache() {
		$db              = bbp_db();
		$user_id         = $this->factory->user->create();
		$live_id         = $this->factory->topic->create();
		$key             = $db->prefix . '_bbp_favorites';
		$old_suppression = $db->suppress_errors( true );
		$query_filter    = static function ( $query ) use ( $db ) {
			$sql_insert = "INSERT INTO `{$db->usermeta}`";

			return ( 0 === strpos( $query, $sql_insert ) )
				? 'INSERT INTO bbp_missing_favorites_usermeta (`user_id`) VALUES (1)'
				: $query;
		};

		update_user_meta( $user_id, $key, "{$live_id},999999" );
		$this->assertSame( "{$live_id},999999", get_user_meta( $user_id, $key, true ) );
		add_filter( 'query', $query_filter );

		try {
			$this->assertSame( 5, bbp_admin_repair_user_favorites()[0] );
			$this->assertFalse( metadata_exists( 'user', $user_id, $key ) );
		} finally {
			remove_filter( 'query', $query_filter );
			$db->suppress_errors( $old_suppression );
		}
	}

	/**
	 * @covers ::bbp_admin_repair_user_favorites_dry_run
	 * @covers ::bbp_admin_repair_user_favorites_process
	 */
	public function test_bbp_admin_repair_user_favorites_dry_run_reports_clean_values() {
		$db      = bbp_db();
		$user_id = $this->factory->user->create();
		$live_id = $this->factory->topic->create();
		$key     = $db->prefix . '_bbp_favorites';

		update_user_meta( $user_id, $key, $live_id );
		$dry_run = bbp_admin_repair_user_favorites_dry_run();
		$this->assertSame( 1, $dry_run[0] );
		$this->assertStringContainsString( 'No user favorites to repair', $dry_run[1] );
		$this->assertSame( (string) $live_id, get_user_meta( $user_id, $key, true ) );
	}

	/**
	 * @covers ::bbp_admin_repair_user_topic_subscriptions
	 * @covers ::bbp_admin_repair_user_topic_subscriptions_dry_run
	 * @covers ::bbp_admin_repair_user_topic_subscriptions_process
	 */
	public function test_bbp_admin_repair_user_topic_subscriptions() {
		$db      = bbp_db();
		$user_id = $this->factory->user->create();
		$live_id = $this->factory->topic->create();
		$old_id  = $this->factory->topic->create();
		$key     = $db->prefix . '_bbp_subscriptions';
		wp_trash_post( $old_id );
		update_user_meta( $user_id, $key, "{$live_id},{$old_id}" );
		$this->assertSame( "{$live_id},{$old_id}", get_user_meta( $user_id, $key, true ) );

		$dry_run = bbp_admin_repair_user_topic_subscriptions_dry_run();
		$this->assertSame( 0, $dry_run[0] );
		$this->assertStringContainsString( 'Topic subscriptions would be repaired for 1 user', $dry_run[1] );
		$this->assertSame( "{$live_id},{$old_id}", get_user_meta( $user_id, $key, true ) );
		$this->assertSame(
			"{$live_id},{$old_id}",
			$db->get_var( $db->prepare( "SELECT meta_value FROM {$db->usermeta} WHERE user_id = %d AND meta_key = %s", $user_id, $key ) )
		);

		$this->assertSame( 0, bbp_admin_repair_user_topic_subscriptions()[0] );
		$this->assertSame( (string) $live_id, get_user_meta( $user_id, $key, true ) );
		$this->assertSame( 1, bbp_admin_repair_user_topic_subscriptions_dry_run()[0] );
	}

	/**
	 * @covers ::bbp_admin_repair_user_topic_subscriptions
	 * @covers ::bbp_admin_repair_user_topic_subscriptions_dry_run
	 * @covers ::bbp_admin_repair_user_topic_subscriptions_process
	 */
	public function test_bbp_admin_repair_user_topic_subscriptions_does_nothing_without_surviving_subscriptions() {
		$db      = bbp_db();
		$user_id = $this->factory->user->create();
		$old_id  = $this->factory->topic->create();
		$key     = $db->prefix . '_bbp_subscriptions';

		wp_trash_post( $old_id );
		update_user_meta( $user_id, $key, $old_id );

		$dry_run = bbp_admin_repair_user_topic_subscriptions_dry_run();
		$this->assertSame( 1, $dry_run[0] );
		$this->assertStringContainsString( 'No user topic subscriptions to repair', $dry_run[1] );
		$this->assertSame(
			(string) $old_id,
			$db->get_var( $db->prepare( "SELECT meta_value FROM {$db->usermeta} WHERE user_id = %d AND meta_key = %s", $user_id, $key ) )
		);

		$run = bbp_admin_repair_user_topic_subscriptions();
		$this->assertSame( 0, $run[0] );
		$this->assertStringContainsString( 'Nothing to remove', $run[1] );
		$this->assertSame(
			(string) $old_id,
			$db->get_var( $db->prepare( "SELECT meta_value FROM {$db->usermeta} WHERE user_id = %d AND meta_key = %s", $user_id, $key ) )
		);
	}

	/**
	 * @covers ::bbp_admin_repair_user_topic_subscriptions
	 * @covers ::bbp_admin_repair_user_topic_subscriptions_dry_run
	 * @covers ::bbp_admin_repair_user_topic_subscriptions_process
	 */
	public function test_bbp_admin_repair_user_topic_subscriptions_removes_rows_without_surviving_subscriptions() {
		$db       = bbp_db();
		$user_ids = $this->factory->user->create_many( 2 );
		$live_id  = $this->factory->topic->create();
		$old_id   = $this->factory->topic->create();
		$key      = $db->prefix . '_bbp_subscriptions';

		wp_trash_post( $old_id );
		update_user_meta( $user_ids[0], $key, "{$live_id},{$old_id}" );
		update_user_meta( $user_ids[1], $key, $old_id );

		$dry_run = bbp_admin_repair_user_topic_subscriptions_dry_run();
		$this->assertSame( 0, $dry_run[0] );
		$this->assertStringContainsString( 'Topic subscriptions would be repaired for 2 users', $dry_run[1] );
		$this->assertSame( 2, (int) $db->get_var( "SELECT COUNT(*) FROM {$db->usermeta} WHERE meta_key = '{$key}'" ) );

		$this->assertSame( 0, bbp_admin_repair_user_topic_subscriptions()[0] );
		$this->assertSame(
			(string) $live_id,
			$db->get_var( $db->prepare( "SELECT meta_value FROM {$db->usermeta} WHERE user_id = %d AND meta_key = %s", $user_ids[0], $key ) )
		);
		$this->assertNull(
			$db->get_var( $db->prepare( "SELECT meta_value FROM {$db->usermeta} WHERE user_id = %d AND meta_key = %s", $user_ids[1], $key ) )
		);
	}

	/**
	 * @covers ::bbp_admin_repair_user_topic_subscriptions
	 * @covers ::bbp_admin_repair_user_topic_subscriptions_dry_run
	 * @covers ::bbp_admin_repair_user_topic_subscriptions_process
	 */
	public function test_bbp_admin_repair_user_topic_subscriptions_repairs_duplicate_rows() {
		$db      = bbp_db();
		$user_id = $this->factory->user->create();
		$live_id = $this->factory->topic->create();
		$old_id  = $this->factory->topic->create();
		$key     = $db->prefix . '_bbp_subscriptions';

		wp_trash_post( $old_id );
		update_user_meta( $user_id, $key, "{$live_id},{$old_id}" );
		add_user_meta( $user_id, $key, $old_id );

		$dry_run = bbp_admin_repair_user_topic_subscriptions_dry_run();
		$this->assertSame( 0, $dry_run[0] );
		$this->assertStringContainsString( 'Topic subscriptions would be repaired for 1 user', $dry_run[1] );
		$this->assertSame(
			2,
			(int) $db->get_var( $db->prepare( "SELECT COUNT(*) FROM {$db->usermeta} WHERE user_id = %d AND meta_key = %s", $user_id, $key ) )
		);

		$this->assertSame( 0, bbp_admin_repair_user_topic_subscriptions()[0] );
		$this->assertSame( array( (string) $live_id ), get_user_meta( $user_id, $key, false ) );
	}

	/**
	 * @covers ::bbp_admin_repair_user_topic_subscriptions
	 * @covers ::bbp_admin_repair_user_topic_subscriptions_dry_run
	 * @covers ::bbp_admin_repair_user_topic_subscriptions_process
	 */
	public function test_bbp_admin_repair_user_topic_subscriptions_normalizes_repeated_live_topics() {
		$db      = bbp_db();
		$user_id = $this->factory->user->create();
		$live_id = $this->factory->topic->create();
		$key     = $db->prefix . '_bbp_subscriptions';

		update_user_meta( $user_id, $key, "{$live_id},{$live_id}" );
		$dry_run = bbp_admin_repair_user_topic_subscriptions_dry_run();
		$this->assertSame( 0, $dry_run[0] );
		$this->assertStringContainsString( 'Topic subscriptions would be repaired for 1 user', $dry_run[1] );
		$this->assertSame(
			"{$live_id},{$live_id}",
			$db->get_var( $db->prepare( "SELECT meta_value FROM {$db->usermeta} WHERE user_id = %d AND meta_key = %s", $user_id, $key ) )
		);

		$this->assertSame( 0, bbp_admin_repair_user_topic_subscriptions()[0] );
		$this->assertSame( (string) $live_id, get_user_meta( $user_id, $key, true ) );
	}

	/**
	 * @covers ::bbp_admin_repair_user_topic_subscriptions_dry_run
	 * @covers ::bbp_admin_repair_user_topic_subscriptions_process
	 */
	public function test_bbp_admin_repair_user_topic_subscriptions_dry_run_reports_read_errors() {
		$db              = bbp_db();
		$old_suppression = $db->suppress_errors( true );
		$old_usermeta    = $db->usermeta;
		$old_posts       = $db->posts;

		try {
			$db->usermeta = 'bbp_missing_topic_subscriptions_usermeta';
			$this->assertSame( 1, bbp_admin_repair_user_topic_subscriptions_dry_run()[0] );

			$db->usermeta = $old_usermeta;
			$db->posts    = 'bbp_missing_topic_subscriptions_posts';
			$this->assertSame( 2, bbp_admin_repair_user_topic_subscriptions_dry_run()[0] );
		} finally {
			$db->usermeta = $old_usermeta;
			$db->posts    = $old_posts;
			$db->suppress_errors( $old_suppression );
		}
	}

	/**
	 * @covers ::bbp_admin_repair_user_topic_subscriptions
	 * @covers ::bbp_admin_repair_user_topic_subscriptions_process
	 */
	public function test_bbp_admin_repair_user_topic_subscriptions_reports_delete_errors() {
		$db              = bbp_db();
		$user_id         = $this->factory->user->create();
		$live_id         = $this->factory->topic->create();
		$key             = $db->prefix . '_bbp_subscriptions';
		$old_suppression = $db->suppress_errors( true );
		$query_filter    = static function ( $query ) use ( $db, $key ) {
			$sql_delete = "DELETE FROM `{$db->usermeta}` WHERE `meta_key` = '{$key}'";

			return ( $query === $sql_delete )
				? 'DELETE FROM bbp_missing_topic_subscriptions_usermeta'
				: $query;
		};

		update_user_meta( $user_id, $key, "{$live_id},999999" );
		add_filter( 'query', $query_filter );

		try {
			$this->assertSame( 4, bbp_admin_repair_user_topic_subscriptions()[0] );
			$this->assertSame( "{$live_id},999999", get_user_meta( $user_id, $key, true ) );
		} finally {
			remove_filter( 'query', $query_filter );
			$db->suppress_errors( $old_suppression );
		}
	}

	/**
	 * @covers ::bbp_admin_repair_user_topic_subscriptions
	 * @covers ::bbp_admin_repair_user_topic_subscriptions_process
	 */
	public function test_bbp_admin_repair_user_topic_subscriptions_reports_insert_errors_and_clears_cache() {
		$db              = bbp_db();
		$user_id         = $this->factory->user->create();
		$live_id         = $this->factory->topic->create();
		$key             = $db->prefix . '_bbp_subscriptions';
		$old_suppression = $db->suppress_errors( true );
		$query_filter    = static function ( $query ) use ( $db ) {
			$sql_insert = "INSERT INTO `{$db->usermeta}`";

			return ( 0 === strpos( $query, $sql_insert ) )
				? 'INSERT INTO bbp_missing_topic_subscriptions_usermeta (`user_id`) VALUES (1)'
				: $query;
		};

		update_user_meta( $user_id, $key, "{$live_id},999999" );
		$this->assertSame( "{$live_id},999999", get_user_meta( $user_id, $key, true ) );
		add_filter( 'query', $query_filter );

		try {
			$this->assertSame( 5, bbp_admin_repair_user_topic_subscriptions()[0] );
			$this->assertFalse( metadata_exists( 'user', $user_id, $key ) );
		} finally {
			remove_filter( 'query', $query_filter );
			$db->suppress_errors( $old_suppression );
		}
	}

	/**
	 * @covers ::bbp_admin_repair_user_topic_subscriptions_dry_run
	 * @covers ::bbp_admin_repair_user_topic_subscriptions_process
	 */
	public function test_bbp_admin_repair_user_topic_subscriptions_dry_run_reports_clean_values() {
		$db      = bbp_db();
		$user_id = $this->factory->user->create();
		$live_id = $this->factory->topic->create();
		$key     = $db->prefix . '_bbp_subscriptions';

		update_user_meta( $user_id, $key, $live_id );
		$dry_run = bbp_admin_repair_user_topic_subscriptions_dry_run();
		$this->assertSame( 1, $dry_run[0] );
		$this->assertStringContainsString( 'No user topic subscriptions to repair', $dry_run[1] );
		$this->assertSame( (string) $live_id, get_user_meta( $user_id, $key, true ) );
	}

	/**
	 * @covers ::bbp_admin_repair_user_forum_subscriptions
	 * @covers ::bbp_admin_repair_user_forum_subscriptions_dry_run
	 * @covers ::bbp_admin_repair_user_forum_subscriptions_process
	 */
	public function test_bbp_admin_repair_user_forum_subscriptions() {
		$db      = bbp_db();
		$user_id = $this->factory->user->create();
		$live_id = $this->factory->forum->create();
		$old_id  = $this->factory->forum->create();
		$key     = $db->prefix . '_bbp_forum_subscriptions';
		wp_trash_post( $old_id );
		update_user_meta( $user_id, $key, "{$live_id},{$old_id}" );
		$this->assertSame( "{$live_id},{$old_id}", get_user_meta( $user_id, $key, true ) );

		$dry_run = bbp_admin_repair_user_forum_subscriptions_dry_run();
		$this->assertSame( 0, $dry_run[0] );
		$this->assertStringContainsString( 'Forum subscriptions would be repaired for 1 user', $dry_run[1] );
		$this->assertSame( "{$live_id},{$old_id}", get_user_meta( $user_id, $key, true ) );
		$this->assertSame(
			"{$live_id},{$old_id}",
			$db->get_var( $db->prepare( "SELECT meta_value FROM {$db->usermeta} WHERE user_id = %d AND meta_key = %s", $user_id, $key ) )
		);

		$this->assertSame( 0, bbp_admin_repair_user_forum_subscriptions()[0] );
		$this->assertSame( (string) $live_id, get_user_meta( $user_id, $key, true ) );
		$this->assertSame( 1, bbp_admin_repair_user_forum_subscriptions_dry_run()[0] );
	}

	/**
	 * @covers ::bbp_admin_repair_user_forum_subscriptions
	 * @covers ::bbp_admin_repair_user_forum_subscriptions_dry_run
	 * @covers ::bbp_admin_repair_user_forum_subscriptions_process
	 */
	public function test_bbp_admin_repair_user_forum_subscriptions_does_nothing_without_surviving_subscriptions() {
		$db      = bbp_db();
		$user_id = $this->factory->user->create();
		$old_id  = $this->factory->forum->create();
		$key     = $db->prefix . '_bbp_forum_subscriptions';

		wp_trash_post( $old_id );
		update_user_meta( $user_id, $key, $old_id );

		$dry_run = bbp_admin_repair_user_forum_subscriptions_dry_run();
		$this->assertSame( 1, $dry_run[0] );
		$this->assertStringContainsString( 'No user forum subscriptions to repair', $dry_run[1] );
		$this->assertSame(
			(string) $old_id,
			$db->get_var( $db->prepare( "SELECT meta_value FROM {$db->usermeta} WHERE user_id = %d AND meta_key = %s", $user_id, $key ) )
		);

		$run = bbp_admin_repair_user_forum_subscriptions();
		$this->assertSame( 0, $run[0] );
		$this->assertStringContainsString( 'Nothing to remove', $run[1] );
		$this->assertSame(
			(string) $old_id,
			$db->get_var( $db->prepare( "SELECT meta_value FROM {$db->usermeta} WHERE user_id = %d AND meta_key = %s", $user_id, $key ) )
		);
	}

	/**
	 * @covers ::bbp_admin_repair_user_forum_subscriptions
	 * @covers ::bbp_admin_repair_user_forum_subscriptions_dry_run
	 * @covers ::bbp_admin_repair_user_forum_subscriptions_process
	 */
	public function test_bbp_admin_repair_user_forum_subscriptions_removes_rows_without_surviving_subscriptions() {
		$db       = bbp_db();
		$user_ids = $this->factory->user->create_many( 2 );
		$live_id  = $this->factory->forum->create();
		$old_id   = $this->factory->forum->create();
		$key      = $db->prefix . '_bbp_forum_subscriptions';

		wp_trash_post( $old_id );
		update_user_meta( $user_ids[0], $key, "{$live_id},{$old_id}" );
		update_user_meta( $user_ids[1], $key, $old_id );

		$dry_run = bbp_admin_repair_user_forum_subscriptions_dry_run();
		$this->assertSame( 0, $dry_run[0] );
		$this->assertStringContainsString( 'Forum subscriptions would be repaired for 2 users', $dry_run[1] );
		$this->assertSame( 2, (int) $db->get_var( "SELECT COUNT(*) FROM {$db->usermeta} WHERE meta_key = '{$key}'" ) );

		$this->assertSame( 0, bbp_admin_repair_user_forum_subscriptions()[0] );
		$this->assertSame(
			(string) $live_id,
			$db->get_var( $db->prepare( "SELECT meta_value FROM {$db->usermeta} WHERE user_id = %d AND meta_key = %s", $user_ids[0], $key ) )
		);
		$this->assertNull(
			$db->get_var( $db->prepare( "SELECT meta_value FROM {$db->usermeta} WHERE user_id = %d AND meta_key = %s", $user_ids[1], $key ) )
		);
	}

	/**
	 * @covers ::bbp_admin_repair_user_forum_subscriptions
	 * @covers ::bbp_admin_repair_user_forum_subscriptions_dry_run
	 * @covers ::bbp_admin_repair_user_forum_subscriptions_process
	 */
	public function test_bbp_admin_repair_user_forum_subscriptions_repairs_duplicate_rows() {
		$db      = bbp_db();
		$user_id = $this->factory->user->create();
		$live_id = $this->factory->forum->create();
		$old_id  = $this->factory->forum->create();
		$key     = $db->prefix . '_bbp_forum_subscriptions';

		wp_trash_post( $old_id );
		update_user_meta( $user_id, $key, "{$live_id},{$old_id}" );
		add_user_meta( $user_id, $key, $old_id );

		$dry_run = bbp_admin_repair_user_forum_subscriptions_dry_run();
		$this->assertSame( 0, $dry_run[0] );
		$this->assertStringContainsString( 'Forum subscriptions would be repaired for 1 user', $dry_run[1] );
		$this->assertSame(
			2,
			(int) $db->get_var( $db->prepare( "SELECT COUNT(*) FROM {$db->usermeta} WHERE user_id = %d AND meta_key = %s", $user_id, $key ) )
		);

		$this->assertSame( 0, bbp_admin_repair_user_forum_subscriptions()[0] );
		$this->assertSame( array( (string) $live_id ), get_user_meta( $user_id, $key, false ) );
	}

	/**
	 * @covers ::bbp_admin_repair_user_forum_subscriptions
	 * @covers ::bbp_admin_repair_user_forum_subscriptions_dry_run
	 * @covers ::bbp_admin_repair_user_forum_subscriptions_process
	 */
	public function test_bbp_admin_repair_user_forum_subscriptions_normalizes_repeated_live_forums() {
		$db      = bbp_db();
		$user_id = $this->factory->user->create();
		$live_id = $this->factory->forum->create();
		$key     = $db->prefix . '_bbp_forum_subscriptions';

		update_user_meta( $user_id, $key, "{$live_id},{$live_id}" );
		$dry_run = bbp_admin_repair_user_forum_subscriptions_dry_run();
		$this->assertSame( 0, $dry_run[0] );
		$this->assertStringContainsString( 'Forum subscriptions would be repaired for 1 user', $dry_run[1] );
		$this->assertSame(
			"{$live_id},{$live_id}",
			$db->get_var( $db->prepare( "SELECT meta_value FROM {$db->usermeta} WHERE user_id = %d AND meta_key = %s", $user_id, $key ) )
		);

		$this->assertSame( 0, bbp_admin_repair_user_forum_subscriptions()[0] );
		$this->assertSame( (string) $live_id, get_user_meta( $user_id, $key, true ) );
	}

	/**
	 * @covers ::bbp_admin_repair_user_forum_subscriptions_dry_run
	 * @covers ::bbp_admin_repair_user_forum_subscriptions_process
	 */
	public function test_bbp_admin_repair_user_forum_subscriptions_dry_run_reports_read_errors() {
		$db              = bbp_db();
		$old_suppression = $db->suppress_errors( true );
		$old_usermeta    = $db->usermeta;
		$old_posts       = $db->posts;

		try {
			$db->usermeta = 'bbp_missing_forum_subscriptions_usermeta';
			$this->assertSame( 1, bbp_admin_repair_user_forum_subscriptions_dry_run()[0] );

			$db->usermeta = $old_usermeta;
			$db->posts    = 'bbp_missing_forum_subscriptions_posts';
			$this->assertSame( 2, bbp_admin_repair_user_forum_subscriptions_dry_run()[0] );
		} finally {
			$db->usermeta = $old_usermeta;
			$db->posts    = $old_posts;
			$db->suppress_errors( $old_suppression );
		}
	}

	/**
	 * @covers ::bbp_admin_repair_user_forum_subscriptions
	 * @covers ::bbp_admin_repair_user_forum_subscriptions_process
	 */
	public function test_bbp_admin_repair_user_forum_subscriptions_reports_delete_errors() {
		$db              = bbp_db();
		$user_id         = $this->factory->user->create();
		$live_id         = $this->factory->forum->create();
		$key             = $db->prefix . '_bbp_forum_subscriptions';
		$old_suppression = $db->suppress_errors( true );
		$query_filter    = static function ( $query ) use ( $db, $key ) {
			$sql_delete = "DELETE FROM `{$db->usermeta}` WHERE `meta_key` = '{$key}'";

			return ( $query === $sql_delete )
				? 'DELETE FROM bbp_missing_forum_subscriptions_usermeta'
				: $query;
		};

		update_user_meta( $user_id, $key, "{$live_id},999999" );
		add_filter( 'query', $query_filter );

		try {
			$this->assertSame( 4, bbp_admin_repair_user_forum_subscriptions()[0] );
			$this->assertSame( "{$live_id},999999", get_user_meta( $user_id, $key, true ) );
		} finally {
			remove_filter( 'query', $query_filter );
			$db->suppress_errors( $old_suppression );
		}
	}

	/**
	 * @covers ::bbp_admin_repair_user_forum_subscriptions
	 * @covers ::bbp_admin_repair_user_forum_subscriptions_process
	 */
	public function test_bbp_admin_repair_user_forum_subscriptions_reports_insert_errors_and_clears_cache() {
		$db              = bbp_db();
		$user_id         = $this->factory->user->create();
		$live_id         = $this->factory->forum->create();
		$key             = $db->prefix . '_bbp_forum_subscriptions';
		$old_suppression = $db->suppress_errors( true );
		$query_filter    = static function ( $query ) use ( $db ) {
			$sql_insert = "INSERT INTO `{$db->usermeta}`";

			return ( 0 === strpos( $query, $sql_insert ) )
				? 'INSERT INTO bbp_missing_forum_subscriptions_usermeta (`user_id`) VALUES (1)'
				: $query;
		};

		update_user_meta( $user_id, $key, "{$live_id},999999" );
		$this->assertSame( "{$live_id},999999", get_user_meta( $user_id, $key, true ) );
		add_filter( 'query', $query_filter );

		try {
			$this->assertSame( 5, bbp_admin_repair_user_forum_subscriptions()[0] );
			$this->assertFalse( metadata_exists( 'user', $user_id, $key ) );
		} finally {
			remove_filter( 'query', $query_filter );
			$db->suppress_errors( $old_suppression );
		}
	}

	/**
	 * @covers ::bbp_admin_repair_user_forum_subscriptions_dry_run
	 * @covers ::bbp_admin_repair_user_forum_subscriptions_process
	 */
	public function test_bbp_admin_repair_user_forum_subscriptions_dry_run_reports_clean_values() {
		$db      = bbp_db();
		$user_id = $this->factory->user->create();
		$live_id = $this->factory->forum->create();
		$key     = $db->prefix . '_bbp_forum_subscriptions';

		update_user_meta( $user_id, $key, $live_id );
		$dry_run = bbp_admin_repair_user_forum_subscriptions_dry_run();
		$this->assertSame( 1, $dry_run[0] );
		$this->assertStringContainsString( 'No user forum subscriptions to repair', $dry_run[1] );
		$this->assertSame( (string) $live_id, get_user_meta( $user_id, $key, true ) );
	}

	/**
	 * @covers ::bbp_admin_repair_user_roles
	 */
	public function test_bbp_admin_repair_user_roles() {
		$user_id = $this->factory->user->create( array( 'role' => 'subscriber' ) );
		bbp_set_user_role( $user_id, bbp_get_blocked_role() );

		$this->assertSame( 0, bbp_admin_repair_user_roles()[0] );
		$this->assertSame( bbp_get_default_role(), bbp_get_user_role( $user_id ) );
	}

	/**
	 * @ticket BBP3262
	 * @covers ::bbp_admin_repair_user_roles
	 */
	public function test_role_repair_does_not_remove_role_for_empty_mapped_role() {
		$user_id  = $this->factory->user->create( array( 'role' => 'subscriber' ) );
		$empty_map = function ( $roles ) {
			$roles['subscriber'] = '';
			return $roles;
		};

		bbp_set_user_role( $user_id, bbp_get_blocked_role() );
		add_filter( 'bbp_get_user_role_map', $empty_map );

		try {
			$this->assertSame( 0, bbp_admin_repair_user_roles()[0] );
			$this->assertSame( bbp_get_blocked_role(), bbp_get_user_role( $user_id ) );
		} finally {
			remove_filter( 'bbp_get_user_role_map', $empty_map );
		}
	}

	/**
	 * @covers ::bbp_admin_repair_freshness
	 */
	public function test_bbp_admin_repair_freshness() {
		$forum_id = $this->factory->forum->create();
		$topic_id = $this->factory->topic->create( array( 'post_parent' => $forum_id ) );
		$reply_id = $this->factory->reply->create( array( 'post_parent' => $topic_id ) );
		update_post_meta( $topic_id, '_bbp_last_reply_id', 999 );
		update_post_meta( $forum_id, '_bbp_last_topic_id', 999 );

		$this->assertSame( 0, bbp_admin_repair_freshness()[0] );
		$this->assertSame( (string) $reply_id, get_post_meta( $topic_id, '_bbp_last_reply_id', true ) );
		$this->assertSame( (string) $topic_id, get_post_meta( $forum_id, '_bbp_last_topic_id', true ) );
	}

	/**
	 * @covers ::bbp_admin_repair_sticky
	 * @covers ::bbp_admin_repair_sticky_dry_run
	 * @covers ::bbp_admin_repair_sticky_process
	 */
	public function test_bbp_admin_repair_sticky() {
		$first_forum  = $this->factory->forum->create();
		$second_forum = $this->factory->forum->create();
		$topic_id     = $this->factory->topic->create( array( 'post_parent' => $first_forum ) );
		$super_id     = $this->factory->topic->create( array( 'post_parent' => $first_forum ) );
		update_post_meta( $first_forum, '_bbp_sticky_topics', array( $topic_id ) );
		update_post_meta( $second_forum, '_bbp_sticky_topics', array( $topic_id, $super_id ) );
		update_option( '_bbp_super_sticky_topics', array( $super_id ) );

		$dry_run = bbp_admin_repair_sticky_dry_run();
		$this->assertSame( 0, $dry_run[0] );
		$this->assertStringContainsString( '1 sticky topic relationship would be repaired', $dry_run[1] );
		$this->assertSame( array( $topic_id ), get_post_meta( $first_forum, '_bbp_sticky_topics', true ) );
		$this->assertSame( array( $topic_id, $super_id ), get_post_meta( $second_forum, '_bbp_sticky_topics', true ) );

		$result = bbp_admin_repair_sticky();
		$this->assertSame( 0, $result[0] );
		$this->assertStringContainsString( '1 sticky topic relationship repaired', $result[1] );
		$this->assertSame( array( $topic_id ), get_post_meta( $first_forum, '_bbp_sticky_topics', true ) );
		$this->assertSame( array( $super_id ), get_post_meta( $second_forum, '_bbp_sticky_topics', true ) );
		$this->assertStringContainsString( '0 sticky topic relationships would be repaired', bbp_admin_repair_sticky_dry_run()[1] );
	}

	/**
	 * @covers ::bbp_admin_repair_sticky_dry_run
	 * @covers ::bbp_admin_repair_sticky_process
	 */
	public function test_bbp_admin_repair_sticky_dry_run_uses_current_metadata() {
		$db           = bbp_db();
		$first_forum  = $this->factory->forum->create();
		$second_forum = $this->factory->forum->create();
		$topic_id     = $this->factory->topic->create( array( 'post_parent' => $first_forum ) );

		update_post_meta( $second_forum, '_bbp_sticky_topics', array() );
		$this->assertSame( array(), get_post_meta( $second_forum, '_bbp_sticky_topics', true ) );
		$this->assertSame(
			1,
			$db->update(
				$db->postmeta,
				array( 'meta_value' => maybe_serialize( array( $topic_id ) ) ),
				array(
					'post_id'  => $second_forum,
					'meta_key' => '_bbp_sticky_topics',
				)
			)
		);

		$dry_run = bbp_admin_repair_sticky_dry_run();
		$this->assertSame( 0, $dry_run[0] );
		$this->assertStringContainsString( '1 sticky topic relationship would be repaired', $dry_run[1] );
		$this->assertSame( array( $topic_id ), get_post_meta( $second_forum, '_bbp_sticky_topics', true ) );
	}

	/**
	 * @covers ::bbp_admin_repair_sticky_dry_run
	 * @covers ::bbp_admin_repair_sticky_process
	 */
	public function test_bbp_admin_repair_sticky_dry_run_uses_current_topic_parent() {
		$db           = bbp_db();
		$first_forum  = $this->factory->forum->create();
		$second_forum = $this->factory->forum->create();
		$topic_id     = $this->factory->topic->create( array( 'post_parent' => $first_forum ) );

		update_post_meta( $second_forum, '_bbp_sticky_topics', array( $topic_id ) );
		$this->assertSame( $first_forum, (int) get_post_field( 'post_parent', $topic_id ) );
		$this->assertSame( 1, $db->update( $db->posts, array( 'post_parent' => $second_forum ), array( 'ID' => $topic_id ) ) );

		$dry_run = bbp_admin_repair_sticky_dry_run();
		$this->assertSame( 0, $dry_run[0] );
		$this->assertStringContainsString( '0 sticky topic relationships would be repaired', $dry_run[1] );
		$this->assertSame( array( $topic_id ), get_post_meta( $second_forum, '_bbp_sticky_topics', true ) );
	}

	/**
	 * @covers ::bbp_admin_repair_sticky_dry_run
	 * @covers ::bbp_admin_repair_sticky_process
	 */
	public function test_bbp_admin_repair_sticky_dry_run_uses_current_super_stickies() {
		$db           = bbp_db();
		$first_forum  = $this->factory->forum->create();
		$second_forum = $this->factory->forum->create();
		$topic_id     = $this->factory->topic->create( array( 'post_parent' => $first_forum ) );

		update_post_meta( $second_forum, '_bbp_sticky_topics', array( $topic_id ) );
		update_option( '_bbp_super_sticky_topics', array() );
		$this->assertSame( array(), bbp_get_super_stickies() );
		$this->assertSame(
			1,
			$db->update(
				$db->options,
				array( 'option_value' => maybe_serialize( array( $topic_id ) ) ),
				array( 'option_name' => '_bbp_super_sticky_topics' )
			)
		);

		$dry_run = bbp_admin_repair_sticky_dry_run();
		$this->assertSame( 0, $dry_run[0] );
		$this->assertStringContainsString( '0 sticky topic relationships would be repaired', $dry_run[1] );
		$this->assertSame( array( $topic_id ), get_post_meta( $second_forum, '_bbp_sticky_topics', true ) );
	}

	/**
	 * @covers ::bbp_admin_repair_sticky
	 * @covers ::bbp_admin_repair_sticky_dry_run
	 * @covers ::bbp_admin_repair_sticky_process
	 */
	public function test_bbp_admin_repair_sticky_preserves_filtered_super_stickies() {
		$first_forum  = $this->factory->forum->create();
		$second_forum = $this->factory->forum->create();
		$topic_id     = $this->factory->topic->create( array( 'post_parent' => $first_forum ) );
		$filter       = static function () use ( $topic_id ) {
			return array( $topic_id );
		};

		update_post_meta( $second_forum, '_bbp_sticky_topics', array( $topic_id ) );
		add_filter( 'pre_option__bbp_super_sticky_topics', $filter );

		try {
			$dry_run = bbp_admin_repair_sticky_dry_run();
			$this->assertSame( 0, $dry_run[0] );
			$this->assertStringContainsString( '0 sticky topic relationships would be repaired', $dry_run[1] );
			$this->assertSame( array( $topic_id ), get_post_meta( $second_forum, '_bbp_sticky_topics', true ) );

			$result = bbp_admin_repair_sticky();
			$this->assertSame( 0, $result[0] );
			$this->assertStringContainsString( '0 sticky topic relationships repaired', $result[1] );
			$this->assertSame( array( $topic_id ), get_post_meta( $second_forum, '_bbp_sticky_topics', true ) );
		} finally {
			remove_filter( 'pre_option__bbp_super_sticky_topics', $filter );
		}
	}

	/**
	 * @covers ::bbp_admin_repair_sticky
	 * @covers ::bbp_admin_repair_sticky_dry_run
	 * @covers ::bbp_admin_repair_sticky_process
	 */
	public function test_bbp_admin_repair_sticky_handles_malformed_metadata() {
		$forum_id = $this->factory->forum->create();
		update_post_meta( $forum_id, '_bbp_sticky_topics', 'malformed' );

		$dry_run = bbp_admin_repair_sticky_dry_run();
		$this->assertSame( 0, $dry_run[0] );
		$this->assertStringContainsString( '1 sticky topic relationship would be repaired', $dry_run[1] );
		$this->assertSame( 'malformed', get_post_meta( $forum_id, '_bbp_sticky_topics', true ) );

		$result = bbp_admin_repair_sticky();
		$this->assertSame( 0, $result[0] );
		$this->assertStringContainsString( '1 sticky topic relationship repaired', $result[1] );
		$this->assertSame( '', get_post_meta( $forum_id, '_bbp_sticky_topics', true ) );
	}

	/**
	 * @covers ::bbp_admin_repair_closed_topics
	 * @covers ::bbp_admin_repair_closed_topics_dry_run
	 * @covers ::bbp_admin_repair_closed_topics_process
	 */
	public function test_bbp_admin_repair_closed_topics() {
		$topic_id = $this->factory->topic->create( array( 'post_status' => bbp_get_closed_status_id() ) );
		delete_post_meta( $topic_id, '_bbp_status' );

		$dry_run = bbp_admin_repair_closed_topics_dry_run();
		$this->assertSame( 0, $dry_run[0] );
		$this->assertStringContainsString( '1 closed topic would be repaired', $dry_run[1] );
		$this->assertSame( '', get_post_meta( $topic_id, '_bbp_status', true ) );

		$result = bbp_admin_repair_closed_topics();
		$this->assertSame( 0, $result[0] );
		$this->assertStringContainsString( '1 closed topic repaired', $result[1] );
		$this->assertSame( 'publish', get_post_meta( $topic_id, '_bbp_status', true ) );
		$this->assertStringContainsString( '0 closed topics repaired', bbp_admin_repair_closed_topics()[1] );
		$this->assertStringContainsString( '0 closed topics would be repaired', bbp_admin_repair_closed_topics_dry_run()[1] );
	}

	/**
	 * @covers ::bbp_admin_repair_closed_topics_dry_run
	 * @covers ::bbp_admin_repair_closed_topics_process
	 */
	public function test_bbp_admin_repair_closed_topics_dry_run_uses_current_metadata() {
		$db       = bbp_db();
		$topic_id = $this->factory->topic->create( array( 'post_status' => bbp_get_closed_status_id() ) );

		update_post_meta( $topic_id, '_bbp_status', 'publish' );
		$this->assertSame( 'publish', get_post_meta( $topic_id, '_bbp_status', true ) );
		$this->assertSame(
			1,
			$db->delete(
				$db->postmeta,
				array(
					'post_id'  => $topic_id,
					'meta_key' => '_bbp_status',
				)
			)
		);

		$dry_run = bbp_admin_repair_closed_topics_dry_run();
		$this->assertSame( 0, $dry_run[0] );
		$this->assertStringContainsString( '1 closed topic would be repaired', $dry_run[1] );
		$this->assertFalse( metadata_exists( 'post', $topic_id, '_bbp_status' ) );
	}

	/**
	 * @covers ::bbp_admin_repair_forum_visibility
	 */
	public function test_bbp_admin_repair_forum_visibility() {
		$private_id = $this->factory->forum->create( array( 'post_status' => bbp_get_private_status_id() ) );
		$hidden_id  = $this->factory->forum->create( array( 'post_status' => bbp_get_hidden_status_id() ) );
		update_option( '_bbp_private_forums', array() );
		update_option( '_bbp_hidden_forums', array() );

		$this->assertSame( 0, bbp_admin_repair_forum_visibility()[0] );
		$this->assertContains( $private_id, get_option( '_bbp_private_forums' ) );
		$this->assertContains( $hidden_id, get_option( '_bbp_hidden_forums' ) );
	}

	/**
	 * @covers ::bbp_admin_repair_forum_meta
	 */
	public function test_bbp_admin_repair_forum_meta() {

		$f = $this->factory->forum->create();

		$t = $this->factory->topic->create( array(
			'post_parent' => $f,
			'topic_meta' => array(
				'forum_id' => $f,
			),
		) );

		$r = $this->factory->reply->create( array(
			'post_parent' => $t,
			'reply_meta' => array(
				'forum_id' => $f,
				'topic_id' => $t,
			),
		) );

		// Forums should have an empty _bbp_forum_id meta key
		$this->assertEquals( array( 0 => '0' ), get_post_meta( $f, '_bbp_forum_id', false ) );

		// Topics should have a _bbp_forum_id meta key
		$this->assertSame( $f, bbp_get_topic_forum_id( $t ) );

		// Replies should have a _bbp_forum_id meta key
		$this->assertSame( $f, bbp_get_reply_forum_id( $r ) );

		// Delete the topic and reply _bbp_forum_id meta key
		$this->assertTrue( delete_post_meta_by_key( '_bbp_forum_id' ) );

		// Check the _bbp_forum_id meta key is deleted
		$this->assertEquals( array(), get_post_meta( $f, '_bbp_forum_id', false ) );
		$this->assertEquals( array(), get_post_meta( $t, '_bbp_forum_id', false ) );
		$this->assertEquals( array(), get_post_meta( $r, '_bbp_forum_id', false ) );

		// Repair the forum meta
		bbp_admin_repair_forum_meta();

		clean_post_cache( $f );
		clean_post_cache( $t );
		clean_post_cache( $r );

		// Topics should have a _bbp_forum_id meta key
		$this->assertEquals( array( $f ), get_post_meta( $t, '_bbp_forum_id', false ) );
		$this->assertSame( $f, bbp_get_topic_forum_id( $t ) );

		// Replies should have a _bbp_forum_id meta key
		$this->assertEquals( array( $f ), get_post_meta( $r, '_bbp_forum_id', false ) );
		$this->assertSame( $f, bbp_get_reply_forum_id( $r ) );
	}

	/**
	 * @covers ::bbp_admin_repair_topic_meta
	 */
	public function test_bbp_admin_repair_topic_meta() {
		$forum_id = $this->factory->forum->create();
		$topic_id = $this->factory->topic->create( array( 'post_parent' => $forum_id ) );
		$reply_id = $this->factory->reply->create( array( 'post_parent' => $topic_id ) );
		update_post_meta( $topic_id, '_bbp_topic_id', 999 );
		update_post_meta( $reply_id, '_bbp_topic_id', 999 );

		$this->assertSame( 0, bbp_admin_repair_topic_meta()[0] );
		$this->assertSame( (string) $topic_id, get_post_meta( $topic_id, '_bbp_topic_id', true ) );
		$this->assertSame( (string) $topic_id, get_post_meta( $reply_id, '_bbp_topic_id', true ) );
	}

	/**
	 * @covers ::bbp_admin_repair_reply_menu_order
	 * @covers ::bbp_admin_repair_reply_menu_order_dry_run
	 * @covers ::bbp_admin_repair_reply_menu_order_process
	 */
	public function test_bbp_admin_repair_reply_menu_order() {
		$topic_id = $this->factory->topic->create();
		$reply_ids = $this->factory->reply->create_many(
			2,
			array(
				'post_parent' => $topic_id,
				'menu_order'  => 7,
			)
		);
		update_post_meta( $reply_ids[0], '_bbp_reply_to', $reply_ids[0] );

		$dry_run = bbp_admin_repair_reply_menu_order_dry_run();
		$this->assertSame( 0, $dry_run[0] );
		$this->assertStringContainsString( '1 self-referential reply relationship would be removed', $dry_run[1] );
		$this->assertStringContainsString( '2 reply positions would be recalculated', $dry_run[1] );
		$this->assertTrue( metadata_exists( 'post', $reply_ids[0], '_bbp_reply_to' ) );
		$this->assertSame( array( 7, 7 ), array_map( 'intval', wp_list_pluck( get_posts( array( 'post__in' => $reply_ids, 'post_type' => bbp_get_reply_post_type(), 'orderby' => 'post__in' ) ), 'menu_order' ) ) );

		$result = bbp_admin_repair_reply_menu_order();
		$this->assertSame( 0, $result[0] );
		$this->assertStringContainsString( '1 self-referential reply relationship was removed', $result[1] );
		$this->assertStringContainsString( '2 reply positions were recalculated', $result[1] );
		$this->assertFalse( metadata_exists( 'post', $reply_ids[0], '_bbp_reply_to' ) );
		$this->assertEqualSets(
			array( 1, 2 ),
			array( (int) get_post_field( 'menu_order', $reply_ids[0] ), (int) get_post_field( 'menu_order', $reply_ids[1] ) )
		);
		$this->assertSame( 1, bbp_admin_repair_reply_menu_order_dry_run()[0] );
	}

	/**
	 * @covers ::bbp_admin_repair_reply_menu_order
	 * @covers ::bbp_admin_repair_reply_menu_order_dry_run
	 * @covers ::bbp_admin_repair_reply_menu_order_process
	 */
	public function test_bbp_admin_repair_reply_menu_order_repairs_self_relationship_without_duplicate_positions() {
		$topic_id = $this->factory->topic->create();
		$reply_id = $this->factory->reply->create(
			array(
				'post_parent' => $topic_id,
				'menu_order'  => 1,
			)
		);
		update_post_meta( $reply_id, '_bbp_reply_to', $reply_id );

		$dry_run = bbp_admin_repair_reply_menu_order_dry_run();
		$this->assertSame( 0, $dry_run[0] );
		$this->assertStringContainsString( '1 self-referential reply relationship would be removed', $dry_run[1] );
		$this->assertStringContainsString( '0 reply positions would be recalculated', $dry_run[1] );
		$this->assertTrue( metadata_exists( 'post', $reply_id, '_bbp_reply_to' ) );

		$result = bbp_admin_repair_reply_menu_order();
		$this->assertSame( 0, $result[0] );
		$this->assertStringContainsString( '1 self-referential reply relationship was removed', $result[1] );
		$this->assertStringContainsString( '0 reply positions were recalculated', $result[1] );
		$this->assertFalse( metadata_exists( 'post', $reply_id, '_bbp_reply_to' ) );
	}

	/**
	 * @covers ::bbp_admin_repair_reply_menu_order_dry_run
	 * @covers ::bbp_admin_repair_reply_menu_order_process
	 */
	public function test_bbp_admin_repair_reply_menu_order_dry_run_uses_current_relationship_metadata() {
		$db       = bbp_db();
		$topic_id = $this->factory->topic->create();
		$reply_id = $this->factory->reply->create(
			array(
				'post_parent' => $topic_id,
				'menu_order'  => 1,
			)
		);

		$this->assertFalse( metadata_exists( 'post', $reply_id, '_bbp_reply_to' ) );
		$this->assertNotFalse(
			$db->insert(
				$db->postmeta,
				array(
					'post_id'    => $reply_id,
					'meta_key'   => '_bbp_reply_to',
					'meta_value' => $reply_id,
				)
			)
		);

		$dry_run = bbp_admin_repair_reply_menu_order_dry_run();
		$this->assertSame( 0, $dry_run[0] );
		$this->assertStringContainsString( '1 self-referential reply relationship would be removed', $dry_run[1] );
		$this->assertTrue( metadata_exists( 'post', $reply_id, '_bbp_reply_to' ) );
	}

	/**
	 * @covers ::bbp_admin_repair_reply_menu_order_dry_run
	 * @covers ::bbp_admin_repair_reply_menu_order_process
	 */
	public function test_bbp_admin_repair_reply_menu_order_dry_run_uses_current_menu_order() {
		$db        = bbp_db();
		$topic_id  = $this->factory->topic->create();
		$reply_ids = array(
			$this->factory->reply->create(
				array(
					'post_parent' => $topic_id,
					'menu_order'  => 1,
				)
			),
			$this->factory->reply->create(
				array(
					'post_parent' => $topic_id,
					'menu_order'  => 2,
				)
			),
		);

		$this->assertSame( 1, (int) get_post_field( 'menu_order', $reply_ids[0] ) );
		$this->assertSame( 2, (int) get_post_field( 'menu_order', $reply_ids[1] ) );
		$this->assertSame( 1, $db->update( $db->posts, array( 'menu_order' => 7 ), array( 'ID' => $reply_ids[0] ) ) );
		$this->assertSame( 1, $db->update( $db->posts, array( 'menu_order' => 7 ), array( 'ID' => $reply_ids[1] ) ) );

		$dry_run = bbp_admin_repair_reply_menu_order_dry_run();
		$this->assertSame( 0, $dry_run[0] );
		$this->assertStringContainsString( '2 reply positions would be recalculated', $dry_run[1] );
		$this->assertSame( 7, (int) get_post_field( 'menu_order', $reply_ids[0] ) );
		$this->assertSame( 7, (int) get_post_field( 'menu_order', $reply_ids[1] ) );
	}

	/**
	 * @covers ::bbp_admin_repair_reply_menu_order
	 * @covers ::bbp_admin_repair_reply_menu_order_dry_run
	 * @covers ::bbp_admin_repair_reply_menu_order_process
	 */
	public function test_bbp_admin_repair_reply_menu_order_counts_only_changed_positions() {
		$topic_id = $this->factory->topic->create();
		$reply_ids = $this->factory->reply->create_many(
			2,
			array(
				'post_parent' => $topic_id,
				'menu_order'  => 1,
			)
		);

		$dry_run = bbp_admin_repair_reply_menu_order_dry_run();
		$this->assertSame( 0, $dry_run[0] );
		$this->assertStringContainsString( '1 reply position would be recalculated', $dry_run[1] );

		$result = bbp_admin_repair_reply_menu_order();
		$this->assertSame( 0, $result[0] );
		$this->assertStringContainsString( '1 reply position was recalculated', $result[1] );
		$this->assertEqualSets(
			array( 1, 2 ),
			array( (int) get_post_field( 'menu_order', $reply_ids[0] ), (int) get_post_field( 'menu_order', $reply_ids[1] ) )
		);
	}

	/**
	 * @covers ::bbp_admin_repair_reply_menu_order
	 * @covers ::bbp_admin_repair_reply_menu_order_process
	 */
	public function test_bbp_admin_repair_reply_menu_order_run_uses_current_database_values() {
		$db        = bbp_db();
		$topic_id  = $this->factory->topic->create();
		$reply_ids = array(
			$this->factory->reply->create(
				array(
					'post_parent' => $topic_id,
					'menu_order'  => 1,
				)
			),
			$this->factory->reply->create(
				array(
					'post_parent' => $topic_id,
					'menu_order'  => 2,
				)
			),
		);

		$this->assertSame( 1, (int) get_post_field( 'menu_order', $reply_ids[0] ) );
		$this->assertSame( 2, (int) get_post_field( 'menu_order', $reply_ids[1] ) );
		$this->assertFalse( metadata_exists( 'post', $reply_ids[0], '_bbp_reply_to' ) );
		$this->assertSame( 1, $db->update( $db->posts, array( 'menu_order' => 7 ), array( 'ID' => $reply_ids[0] ) ) );
		$this->assertSame( 1, $db->update( $db->posts, array( 'menu_order' => 7 ), array( 'ID' => $reply_ids[1] ) ) );
		$this->assertNotFalse(
			$db->insert(
				$db->postmeta,
				array(
					'post_id'    => $reply_ids[0],
					'meta_key'   => '_bbp_reply_to',
					'meta_value' => $reply_ids[0],
				)
			)
		);

		$result = bbp_admin_repair_reply_menu_order();
		$this->assertSame( 0, $result[0] );
		$this->assertStringContainsString( '1 self-referential reply relationship was removed', $result[1] );
		$this->assertStringContainsString( '2 reply positions were recalculated', $result[1] );
		$this->assertFalse( metadata_exists( 'post', $reply_ids[0], '_bbp_reply_to' ) );
		$this->assertEqualSets(
			array( 1, 2 ),
			array( (int) get_post_field( 'menu_order', $reply_ids[0] ), (int) get_post_field( 'menu_order', $reply_ids[1] ) )
		);
	}

	/**
	 * @covers ::bbp_admin_repair_clean_post_meta_cache
	 */
	public function test_bbp_admin_repair_clean_post_meta_cache() {
		$db       = bbp_db();
		$topic_id = $this->factory->topic->create();

		update_post_meta( $topic_id, '_bbp_test_cache', 'stale' );
		$this->assertSame( 'stale', get_post_meta( $topic_id, '_bbp_test_cache', true ) );
		$this->assertSame(
			1,
			$db->update(
				$db->postmeta,
				array( 'meta_value' => 'fresh' ),
				array( 'post_id' => $topic_id, 'meta_key' => '_bbp_test_cache' )
			)
		);
		$this->assertSame( 'stale', get_post_meta( $topic_id, '_bbp_test_cache', true ) );

		bbp_admin_repair_clean_post_meta_cache( bbp_get_topic_post_type() );

		$this->assertSame( 'fresh', get_post_meta( $topic_id, '_bbp_test_cache', true ) );
	}

	/**
	 * @covers ::bbp_admin_repair_topic_tag_count
	 */
	public function test_bbp_admin_repair_topic_tag_count() {
		$db       = bbp_db();
		$taxonomy = bbp_get_topic_tag_tax_id();
		$topic_id = $this->factory->topic->create();
		$term     = wp_insert_term( 'Repair count', $taxonomy );
		$term_id  = $term['term_id'];
		$tt_id    = $term['term_taxonomy_id'];

		wp_set_object_terms( $topic_id, array( $term_id ), $taxonomy );
		$db->update( $db->term_taxonomy, array( 'count' => 99 ), array( 'term_taxonomy_id' => $tt_id ) );
		clean_term_cache( $term_id, $taxonomy );
		$this->assertSame( 99, get_term( $term_id, $taxonomy )->count );

		$result = bbp_admin_repair_topic_tag_count();

		$this->assertSame( 0, $result[0] );
		$this->assertSame( 1, get_term( $term_id, $taxonomy )->count );

		$filter = static function () {
			return 'bbp_missing_topic_tag_taxonomy';
		};
		add_filter( 'bbp_get_topic_tag_tax_id', $filter );
		try {
			$this->assertSame( 1, bbp_admin_repair_topic_tag_count()[0] );
		} finally {
			remove_filter( 'bbp_get_topic_tag_tax_id', $filter );
		}
	}

	/**
	 * @covers ::bbp_admin_upgrade_user_engagements
	 */
	public function test_bbp_admin_upgrade_user_engagements() {
		$users    = $this->factory->user->create_many( 2 );
		$forum_id = $this->factory->forum->create();
		$topic_id = $this->factory->topic->create( array( 'post_author' => $users[0], 'post_parent' => $forum_id ) );
		$this->factory->reply->create( array( 'post_author' => $users[1], 'post_parent' => $topic_id ) );

		delete_post_meta( $topic_id, '_bbp_engagement' );
		$result = bbp_admin_upgrade_user_engagements();

		$this->assertSame( 0, $result[0] );
		$this->assertEqualSets( $users, get_post_meta( $topic_id, '_bbp_engagement', false ) );
	}

	/**
	 * @covers ::bbp_admin_upgrade_remove_favorites_from_usermeta
	 * @covers ::bbp_admin_upgrade_remove_topic_subscriptions_from_usermeta
	 * @covers ::bbp_admin_upgrade_remove_forum_subscriptions_from_usermeta
	 *
	 * @dataProvider usermeta_removal_upgrade_provider
	 */
	public function test_bbp_admin_upgrade_removes_legacy_usermeta( $callback, $key ) {
		$user_id  = $this->factory->user->create();
		$meta_key = bbp_db()->prefix . $key;

		update_user_option( $user_id, $key, '1,2' );
		$this->assertSame( '1,2', get_user_meta( $user_id, $meta_key, true ) );
		$this->assertSame( 0, $callback()[0] );
		$this->assertFalse( metadata_exists( 'user', $user_id, $meta_key ) );
		$this->assertSame( 1, $callback()[0] );
	}

	public static function usermeta_removal_upgrade_provider() {
		return array(
			'favorites'           => array( 'bbp_admin_upgrade_remove_favorites_from_usermeta', '_bbp_favorites' ),
			'topic subscriptions' => array( 'bbp_admin_upgrade_remove_topic_subscriptions_from_usermeta', '_bbp_subscriptions' ),
			'forum subscriptions' => array( 'bbp_admin_upgrade_remove_forum_subscriptions_from_usermeta', '_bbp_forum_subscriptions' ),
		);
	}

	/**
	 * @covers ::bbp_admin_upgrade_user_favorites
	 */
	public function test_bbp_admin_upgrade_user_favorites() {

		$u = $this->factory->user->create_many( 2 );
		$t = $this->factory->topic->create();

		// Create 2.5.x user meta favorites
		update_user_option( $u[0], '_bbp_favorites', $t );
		update_user_option( $u[1], '_bbp_favorites', $t );

		// Upgrade the user favorites.
		bbp_admin_upgrade_user_favorites();

		$expected            = array( $u[0], $u[1] );
		$favoriters          = bbp_get_topic_favoriters( $t );
		$post_meta_favorites = get_post_meta( $t, '_bbp_favorite', false );

		$this->assertEqualSets( $expected, $favoriters );
		$this->assertEqualSets( $expected, $post_meta_favorites );
	}

	/**
	 * @covers ::bbp_admin_upgrade_user_topic_subscriptions
	 */
	public function test_bbp_admin_upgrade_user_topic_subscriptions() {

		$u = $this->factory->user->create_many( 2 );

		$f = $this->factory->forum->create();

		$t = $this->factory->topic->create( array(
			'post_parent' => $f,
			'topic_meta' => array(
				'forum_id' => $f,
			),
		) );

		// Create 2.5.x user meta topic subscriptions.
		update_user_option( $u[0], '_bbp_subscriptions', $t );
		update_user_option( $u[1], '_bbp_subscriptions', $t );

		// Upgrade the user topic subscriptions.
		bbp_admin_upgrade_user_topic_subscriptions();

		$expected            = array( $u[0], $u[1] );
		$topic_subscribers   = bbp_get_topic_subscribers( $t );
		$post_meta_topic_sub = get_post_meta( $t, '_bbp_subscription', false );

		$this->assertEqualSets( $expected, $topic_subscribers );
		$this->assertEqualSets( $expected, $post_meta_topic_sub );
	}

	/**
	 * @covers ::bbp_admin_upgrade_user_forum_subscriptions
	 */
	public function test_bbp_admin_upgrade_user_forum_subscriptions() {

		$u = $this->factory->user->create_many( 2 );

		$f = $this->factory->forum->create();

		// Create 2.5.x user meta forum subscriptions.
		update_user_option( $u[0], '_bbp_forum_subscriptions', $f );
		update_user_option( $u[1], '_bbp_forum_subscriptions', $f );

		// Upgrade the user forum subscriptions.
		bbp_admin_upgrade_user_forum_subscriptions();

		$expected            = array( $u[0], $u[1] );
		$forum_subscribers   = bbp_get_forum_subscribers( $f );
		$post_meta_forum_sub = get_post_meta( $f, '_bbp_subscription', false );

		$this->assertEqualSets( $expected, $forum_subscribers );
		$this->assertEqualSets( $expected, $post_meta_forum_sub );
	}

	/**
	 * @covers ::bbp_admin_reset_page
	 */
	public function test_bbp_admin_reset_page() {
		ob_start();
		bbp_admin_reset_page();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'name="bbpress-are-you-sure"', $output );
		$this->assertStringContainsString( 'name="_wpnonce"', $output );
		$this->assertStringContainsString( 'Reset bbPress', $output );
	}

	/**
	 * @covers ::bbp_admin_reset_handler
	 */
	public function test_bbp_admin_reset_handler() {
		$old_method = isset( $_SERVER['REQUEST_METHOD'] ) ? $_SERVER['REQUEST_METHOD'] : null;
		$old_post   = $_POST;

		try {
			$_SERVER['REQUEST_METHOD'] = 'GET';
			$_POST = array( 'bbpress-are-you-sure' => '1' );
			$this->assertNull( bbp_admin_reset_handler() );
			$_SERVER['REQUEST_METHOD'] = 'POST';
			$_POST = array();
			$this->assertNull( bbp_admin_reset_handler() );
		} finally {
			$_POST = $old_post;
			if ( null === $old_method ) {
				unset( $_SERVER['REQUEST_METHOD'] );
			} else {
				$_SERVER['REQUEST_METHOD'] = $old_method;
			}
		}
	}
}
