<?php
/**
 * Tests for the core update functions.
 *
 * @group core
 * @group update
 */
class BBP_Tests_Core_Update extends BBP_UnitTestCase {

	/**
	 * @covers ::bbp_maybe_append_pending_upgrade_count
	 * @ticket BBP3717
	 *
	 * @requires PHP 8.0
	 */
	public function test_bbp_maybe_append_pending_upgrade_count_preserves_named_arguments() {
		$this->assertSame(
			'Label',
			call_user_func_array(
				'bbp_maybe_append_pending_upgrade_count',
				array(
					'string' => 'Label',
					'type'   => '',
				)
			)
		);
	}

	/**
	 * @covers ::bbp_add_pending_upgrade
	 * @covers ::bbp_clear_pending_upgrades
	 * @covers ::bbp_get_pending_upgrade_count
	 * @covers ::bbp_get_pending_upgrades
	 * @covers ::bbp_remove_pending_upgrade
	 */
	public function test_pending_upgrade_storage_helpers() {
		delete_option( '_bbp_db_pending_upgrades' );

		$this->assertSame( array(), bbp_get_pending_upgrades() );
		$this->assertSame( 0, bbp_get_pending_upgrade_count() );

		$this->assertTrue( bbp_add_pending_upgrade( 'upgrade-one' ) );
		$this->assertFalse( bbp_add_pending_upgrade( 'upgrade-one' ) );
		$this->assertTrue( bbp_add_pending_upgrade( 'repair-one' ) );
		$this->assertSame( array( 'upgrade-one', 'repair-one' ), bbp_get_pending_upgrades() );
		$this->assertSame( 2, bbp_get_pending_upgrade_count() );

		$this->assertFalse( bbp_remove_pending_upgrade( 'missing' ) );
		$this->assertTrue( bbp_remove_pending_upgrade( 'upgrade-one' ) );
		$this->assertSame( array( 'repair-one' ), array_values( bbp_get_pending_upgrades() ) );
		$this->assertSame( 1, bbp_get_pending_upgrade_count() );

		$this->assertTrue( bbp_clear_pending_upgrades() );
		$this->assertFalse( bbp_clear_pending_upgrades() );
		$this->assertSame( array(), bbp_get_pending_upgrades() );
		$this->assertSame( 0, bbp_get_pending_upgrade_count() );
	}

	/**
	 * @covers ::bbp_get_pending_upgrade_count
	 * @covers ::bbp_get_pending_upgrades
	 * @covers ::bbp_maybe_append_pending_upgrade_count
	 * @ticket 3775
	 */
	public function test_pending_upgrades_are_filtered_by_registered_tool_type() {
		if ( ! function_exists( 'bbp_admin' ) ) {
			require_once BBP_PLUGIN_DIR . 'includes/admin/actions.php';
		}

		$original_tools = bbp_admin()->tools;

		bbp_admin()->tools = array(
			'upgrade-one' => array( 'type' => 'upgrade' ),
			'repair-one'  => array( 'type' => 'repair' ),
			'upgrade-two' => array( 'type' => 'upgrade' ),
		);

		update_option(
			'_bbp_db_pending_upgrades',
			array( 'upgrade-one', 'repair-one', 'missing', 'upgrade-two' )
		);

		try {
			$this->assertSame(
				array( 'upgrade-one', 'repair-one', 'missing', 'upgrade-two' ),
				bbp_get_pending_upgrades()
			);
			$this->assertSame( 4, bbp_get_pending_upgrade_count() );
			$this->assertSame(
				array( 'upgrade-one', 'upgrade-two' ),
				array_values( bbp_get_pending_upgrades( 'upgrade' ) )
			);
			$this->assertSame( array( 'repair-one' ), array_values( bbp_get_pending_upgrades( 'repair' ) ) );
			$this->assertSame( array(), bbp_get_pending_upgrades( 'missing-type' ) );
			$this->assertSame( 2, bbp_get_pending_upgrade_count( 'upgrade' ) );
			$this->assertSame( 1, bbp_get_pending_upgrade_count( 'repair' ) );
			$this->assertSame(
				'Updates <span class="awaiting-mod bbp-menu-count count-2"><span class="pending-count" aria-hidden="true">2</span><span class="screen-reader-text">2 pending upgrades</span></span>',
				bbp_maybe_append_pending_upgrade_count( 'Updates', 'upgrade' )
			);
			$this->assertSame(
				'Repairs <span class="awaiting-mod bbp-menu-count count-1"><span class="pending-count" aria-hidden="true">1</span><span class="screen-reader-text">1 pending upgrade</span></span>',
				bbp_maybe_append_pending_upgrade_count( 'Repairs', 'repair' )
			);
			$this->assertSame( 'Updates', bbp_maybe_append_pending_upgrade_count( 'Updates', 'missing-type' ) );
		} finally {
			bbp_admin()->tools = $original_tools;
			delete_option( '_bbp_db_pending_upgrades' );
		}
	}

	/**
	 * @covers ::bbp_is_install
	 */
	public function test_bbp_is_install() {
		delete_option( '_bbp_db_version' );
		$this->assertTrue( bbp_is_install() );

		update_option( '_bbp_db_version', bbp_get_db_version() );
		$this->assertFalse( bbp_is_install() );
	}

	/**
	 * @covers ::bbp_is_update
	 */
	public function test_bbp_is_update() {
		$current_version = (int) bbp_get_db_version();
		update_option( '_bbp_db_version', $current_version - 1 );
		$this->assertTrue( bbp_is_update() );

		update_option( '_bbp_db_version', $current_version );
		$this->assertFalse( bbp_is_update() );

		update_option( '_bbp_db_version', $current_version + 1 );
		$this->assertFalse( bbp_is_update() );
	}

	/**
	 * @covers ::bbp_is_activation
	 */
	public function test_bbp_is_activation() {
		global $pagenow;

		$old_screen  = isset( $GLOBALS['current_screen'] ) ? $GLOBALS['current_screen'] : null;
		$old_pagenow = $pagenow;
		$old_request = $_REQUEST;
		$old_get     = $_GET;
		$old_post    = $_POST;
		$basename    = bbpress()->basename;

		try {
			set_current_screen( 'plugins' );
			$pagenow = 'plugins.php';
			$_REQUEST['action'] = 'activate';
			$_GET['plugin'] = $basename;
			$this->assertTrue( bbp_is_activation() );

			$_GET['plugin'] = 'another/plugin.php';
			$this->assertFalse( bbp_is_activation() );

			$_REQUEST['action'] = '-1';
			$_REQUEST['action2'] = 'activate-selected';
			$_POST['checked'] = array( $basename );
			$this->assertTrue( bbp_is_activation() );

			$pagenow = 'index.php';
			$this->assertFalse( bbp_is_activation() );
		} finally {
			$GLOBALS['current_screen'] = $old_screen;
			$pagenow = $old_pagenow;
			$_REQUEST = $old_request;
			$_GET = $old_get;
			$_POST = $old_post;
		}
	}

	/**
	 * @covers ::bbp_is_deactivation
	 */
	public function test_bbp_is_deactivation() {
		global $pagenow;

		$old_screen  = isset( $GLOBALS['current_screen'] ) ? $GLOBALS['current_screen'] : null;
		$old_pagenow = $pagenow;
		$old_request = $_REQUEST;
		$old_get     = $_GET;
		$old_post    = $_POST;
		$basename    = bbpress()->basename;

		try {
			set_current_screen( 'plugins' );
			$pagenow = 'plugins.php';
			$_REQUEST['action'] = 'deactivate';
			$_GET['plugin'] = $basename;
			$this->assertTrue( bbp_is_deactivation() );

			$_GET['plugin'] = 'another/plugin.php';
			$this->assertFalse( bbp_is_deactivation() );

			$_REQUEST['action'] = '-1';
			$_REQUEST['action2'] = 'deactivate-selected';
			$_POST['checked'] = array( $basename );
			$this->assertTrue( bbp_is_deactivation() );

			$pagenow = 'index.php';
			$this->assertFalse( bbp_is_deactivation() );
		} finally {
			$GLOBALS['current_screen'] = $old_screen;
			$pagenow = $old_pagenow;
			$_REQUEST = $old_request;
			$_GET = $old_get;
			$_POST = $old_post;
		}
	}

	/**
	 * @covers ::bbp_version_bump
	 */
	public function test_bbp_version_bump() {
		update_option( '_bbp_db_version', 1 );
		bbp_version_bump();
		$this->assertSame( (int) bbp_get_db_version(), (int) bbp_get_db_version_raw() );
	}

	/**
	 * @covers ::bbp_setup_updater
	 */
	public function test_bbp_setup_updater() {
		global $pagenow;

		require_once ABSPATH . 'wp-admin/includes/admin.php';
		require_once bbpress()->includes_dir . 'admin/actions.php';

		// WordPress may restore hooks from before the admin actions were loaded.
		add_action( 'current_screen', 'bbp_current_screen' );
		add_action( 'bbp_current_screen', 'bbp_setup_updater', 999 );

		$this->assertFalse( has_action( 'bbp_admin_init', 'bbp_setup_updater' ) );
		$this->assertSame( 999, has_action( 'bbp_current_screen', 'bbp_setup_updater' ) );

		$screen         = WP_Screen::get( 'dashboard' );
		$front_screen   = WP_Screen::get( 'front' );
		$participant_id = $this->factory->user->create( array( 'role' => bbp_get_participant_role() ) );
		$keymaster_id   = $this->factory->user->create( array( 'role' => 'subscriber' ) );
		$site_admin_id  = $this->factory->user->create( array( 'role' => 'administrator' ) );
		bbp_set_user_role( $keymaster_id, bbp_get_keymaster_role() );

		update_option( '_bbp_db_version', 263 );
		update_option( '_bbp_converter_query', 'saved progress' );

		$this->set_current_user( 0 );
		$this->assertTrue( bbp_is_update() );
		bbp_setup_updater( $screen );
		$this->assertSame( 263, (int) bbp_get_db_version_raw() );

		$this->set_current_user( $participant_id );
		bbp_setup_updater( $screen );
		$this->assertSame( 263, (int) bbp_get_db_version_raw() );

		$this->set_current_user( $keymaster_id );
		bbp_setup_updater( $front_screen );
		$this->assertSame( 263, (int) bbp_get_db_version_raw() );

		add_filter( 'wp_doing_ajax', '__return_true' );
		bbp_setup_updater( $screen );
		remove_filter( 'wp_doing_ajax', '__return_true' );
		$this->assertSame( 263, (int) bbp_get_db_version_raw() );

		$old_pagenow = $pagenow;
		$pagenow     = 'admin-post.php';
		bbp_setup_updater( $screen );
		$pagenow = $old_pagenow;
		$this->assertSame( 263, (int) bbp_get_db_version_raw() );

		do_action( 'current_screen', $screen );
		$this->assertSame( 264, (int) bbp_get_db_version_raw() );
		$this->assertFalse( get_option( '_bbp_converter_query' ) );

		update_option( '_bbp_db_version', 263 );
		$this->set_current_user( $site_admin_id );
		$this->assertTrue( current_user_can( 'manage_options' ) );
		bbp_setup_updater( $screen );
		$this->assertSame( 264, (int) bbp_get_db_version_raw() );
	}

	/**
	 * @covers ::bbp_setup_new_site
	 */
	public function test_bbp_setup_new_site_creates_initial_content_only_once() {
		$site_id = get_current_blog_id();
		$option  = '_bbp_flag_initial_content';
		$counts  = array(
			'forum' => (int) wp_count_posts( bbp_get_forum_post_type() )->publish,
			'topic' => (int) wp_count_posts( bbp_get_topic_post_type() )->publish,
			'reply' => (int) wp_count_posts( bbp_get_reply_post_type() )->publish,
		);

		if ( is_multisite() ) {
			delete_blog_option( $site_id, $option );
		} else {
			delete_option( $option );
		}

		bbp_setup_new_site( $site_id );

		$this->assertSame( $counts['forum'], (int) wp_count_posts( bbp_get_forum_post_type() )->publish );
		$this->assertSame( $counts['topic'], (int) wp_count_posts( bbp_get_topic_post_type() )->publish );
		$this->assertSame( $counts['reply'], (int) wp_count_posts( bbp_get_reply_post_type() )->publish );
		$this->assertSame(
			'missing',
			is_multisite()
				? get_blog_option( $site_id, $option, 'missing' )
				: get_option( $option, 'missing' )
		);

		if ( is_multisite() ) {
			update_blog_option( $site_id, $option, true );
		} else {
			update_option( $option, true );
		}

		bbp_setup_new_site( $site_id );

		$this->assertSame( $counts['forum'] + 1, (int) wp_count_posts( bbp_get_forum_post_type() )->publish );
		$this->assertSame( $counts['topic'] + 1, (int) wp_count_posts( bbp_get_topic_post_type() )->publish );
		$this->assertSame( $counts['reply'] + 1, (int) wp_count_posts( bbp_get_reply_post_type() )->publish );

		$create = is_multisite()
			? get_blog_option( $site_id, $option, false )
			: get_option( $option, false );
		$this->assertFalse( (bool) $create );

		bbp_setup_new_site( $site_id );

		$this->assertSame( $counts['forum'] + 1, (int) wp_count_posts( bbp_get_forum_post_type() )->publish );
		$this->assertSame( $counts['topic'] + 1, (int) wp_count_posts( bbp_get_topic_post_type() )->publish );
		$this->assertSame( $counts['reply'] + 1, (int) wp_count_posts( bbp_get_reply_post_type() )->publish );
	}

	/**
	 * @group canonical
	 * @covers ::bbp_create_initial_content
	 */
	public function test_bbp_create_initial_content() {

		$category_id = $this->factory->forum->create( array(
			'forum_meta' => array(
				'forum_type' => 'category',
				'status'     => 'open',
			),
		) );

		$u = $this->factory->user->create();

		bbp_create_initial_content( array(
			'forum_parent' => $category_id,
			'forum_author' => $u,
			'topic_author' => $u,
			'reply_author' => $u
		) );

		$forum_id = bbp_forum_query_subforum_ids( $category_id );
		$forum_id = (int) $forum_id[0];
		$topic_id = bbp_get_forum_last_topic_id( $forum_id );
		$reply_id = bbp_get_forum_last_reply_id( $forum_id );

		// Forum post
		$this->assertSame( 'General', bbp_get_forum_title( $forum_id ) );
		$this->assertSame( 'General Discussion', bbp_get_forum_content( $forum_id ) );
		$this->assertSame( 'open', bbp_get_forum_status( $forum_id ) );
		$this->assertTrue( bbp_is_forum_public( $forum_id ) );
		$this->assertSame( $category_id, bbp_get_forum_parent_id( $forum_id ) );

		// Topic post
		$this->assertSame( $forum_id, bbp_get_topic_forum_id( $topic_id ) );
		$this->assertSame( 'Hello World!', bbp_get_topic_title( $topic_id ) );
		remove_all_filters( 'bbp_get_topic_content' );
		$topic_content = "This is the very first topic in these forums.";
		$this->assertSame( $topic_content, bbp_get_topic_content( $topic_id ) );
		$this->assertSame( 'publish', bbp_get_topic_status( $topic_id ) );
		$this->assertTrue( bbp_is_topic_published( $topic_id ) );

		// Reply post
		$this->assertSame( $forum_id, bbp_get_reply_forum_id( $reply_id ) );
		$this->assertSame( 'Reply To: Hello World!', bbp_get_reply_title( $reply_id ) );
		$this->assertSame( $reply_id, bbp_get_reply_title_fallback( $reply_id ) );
		remove_all_filters( 'bbp_get_reply_content' );
		$reply_content = "And this is the very first reply.";
		$this->assertSame( $reply_content, bbp_get_reply_content( $reply_id ) );
		$this->assertSame( 'publish', bbp_get_reply_status( $reply_id ) );
		$this->assertTrue( bbp_is_reply_published( $reply_id ) );

		// Category meta
		$this->assertSame( 1, bbp_get_forum_subforum_count( $category_id, true ) );
		$this->assertSame( 0, bbp_get_forum_topic_count( $category_id, false, true ) );
		$this->assertSame( 0, bbp_get_forum_topic_count_hidden( $category_id, true, true ) );
		$this->assertSame( 0, bbp_get_forum_reply_count( $category_id, false, true ) );
		$this->assertSame( 1, bbp_get_forum_topic_count( $category_id, true, true ) );
		$this->assertSame( 1, bbp_get_forum_reply_count( $category_id, true, true ) );
		$this->assertSame( 0, bbp_get_forum_post_count( $category_id, false, true ) );
		$this->assertSame( 2, bbp_get_forum_post_count( $category_id, true, true ) );
		$this->assertSame( $topic_id, bbp_get_forum_last_topic_id( $category_id ) );
		$this->assertSame( 'Hello World!', bbp_get_forum_last_topic_title( $category_id ) );
		$this->assertSame( $reply_id, bbp_get_forum_last_reply_id( $category_id ) );
		$this->assertSame( 'Reply To: Hello World!', bbp_get_forum_last_reply_title( $category_id ) );
		$this->assertSame( $reply_id, bbp_get_forum_last_active_id( $category_id ) );
		$this->assertSame( '1 day, 16 hours ago', bbp_get_forum_last_active_time( $category_id ) );

		// Forum meta
		$this->assertSame( 0, bbp_get_forum_subforum_count( $forum_id, true ) );
		$this->assertSame( 1, bbp_get_forum_topic_count( $forum_id, false, true ) );
		$this->assertSame( 0, bbp_get_forum_topic_count_hidden( $forum_id, true, true ) );
		$this->assertSame( 1, bbp_get_forum_reply_count( $forum_id, false, true ) );
		$this->assertSame( 1, bbp_get_forum_topic_count( $forum_id, true, true ) );
		$this->assertSame( 1, bbp_get_forum_reply_count( $forum_id, true, true ) );
		$this->assertSame( 2, bbp_get_forum_post_count( $forum_id, false, true ) );
		$this->assertSame( 2, bbp_get_forum_post_count( $forum_id, true, true ) );
		$this->assertSame( $topic_id, bbp_get_forum_last_topic_id( $forum_id ) );
		$this->assertSame( 'Hello World!', bbp_get_forum_last_topic_title( $forum_id ) );
		$this->assertSame( $reply_id, bbp_get_forum_last_reply_id( $forum_id ) );
		$this->assertSame( 'Reply To: Hello World!', bbp_get_forum_last_reply_title( $forum_id ) );
		$this->assertSame( $reply_id, bbp_get_forum_last_active_id( $forum_id ) );
		$this->assertSame( '1 day, 16 hours ago', bbp_get_forum_last_active_time( $forum_id ) );

		// Topic meta
		$this->assertSame( '127.0.0.1', bbp_current_author_ip( $topic_id ) );
		$this->assertSame( $forum_id, bbp_get_topic_forum_id( $topic_id ) );
		$this->assertSame( 1, bbp_get_topic_voice_count( $topic_id, true ) );
		$this->assertSame( 1, bbp_get_topic_reply_count( $topic_id, true ) );
		$this->assertSame( 0, bbp_get_topic_reply_count_hidden( $topic_id, true ) );
		$this->assertSame( $reply_id, bbp_get_topic_last_reply_id( $topic_id ) );
		$this->assertSame( $reply_id, bbp_get_topic_last_active_id( $topic_id ) );
		$this->assertSame( '1 day, 16 hours ago', bbp_get_topic_last_active_time( $topic_id ) );

		// Reply Meta
		$this->assertSame( '127.0.0.1', bbp_current_author_ip( $reply_id ) );
		$this->assertSame( $forum_id, bbp_get_reply_forum_id( $reply_id ) );
		$this->assertSame( $topic_id, bbp_get_reply_topic_id( $reply_id ) );
	}

	/**
	 * @covers ::bbp_version_updater
	 */
	public function test_bbp_version_updater() {
		delete_option( '_bbp_db_version' );
		update_option( 'rewrite_rules', array( 'sentinel' => 'saved' ) );

		bbp_version_updater();

		$this->assertSame( (int) bbp_get_db_version(), (int) bbp_get_db_version_raw() );
		$this->assertFalse( get_option( 'rewrite_rules' ) );
	}

	/**
	 * @covers ::bbp_version_updater
	 */
	public function test_version_updater_does_not_keep_successful_favorites_pending() {
		$old_db_version = get_option( '_bbp_db_version', null );
		$old_pending    = get_option( '_bbp_db_pending_upgrades', null );
		$user_id        = $this->factory->user->create();
		$topic_id       = $this->factory->topic->create();
		$small_install  = static function () {
			return false;
		};

		update_user_option( $user_id, '_bbp_favorites', $topic_id );
		update_option( '_bbp_db_version', 260 );
		delete_option( '_bbp_db_pending_upgrades' );
		add_filter( 'bbp_is_large_install', $small_install );

		try {
			bbp_version_updater();
			$this->assertSame( array( (string) $user_id ), get_post_meta( $topic_id, '_bbp_favorite', false ) );
			$this->assertNotContains( 'bbp-user-favorites-move', bbp_get_pending_upgrades() );
			$this->assertSame( (int) bbp_get_db_version(), (int) bbp_get_db_version_raw() );
		} finally {
			remove_filter( 'bbp_is_large_install', $small_install );

			if ( null === $old_db_version ) {
				delete_option( '_bbp_db_version' );
			} else {
				update_option( '_bbp_db_version', $old_db_version );
			}

			if ( null === $old_pending ) {
				delete_option( '_bbp_db_pending_upgrades' );
			} else {
				update_option( '_bbp_db_pending_upgrades', $old_pending );
			}
		}
	}

	/**
	 * @covers ::bbp_version_updater
	 */
	public function test_version_updater_keeps_failed_favorites_pending() {
		$old_db_version = get_option( '_bbp_db_version', null );
		$old_pending    = get_option( '_bbp_db_pending_upgrades', null );
		$user_id        = $this->factory->user->create();
		$topic_id       = $this->factory->topic->create();
		$fail_favorite  = static function ( $check, $object_id, $meta_key ) {
			return ( '_bbp_favorite' === $meta_key ) ? false : $check;
		};
		$small_install  = static function () {
			return false;
		};

		update_user_option( $user_id, '_bbp_favorites', $topic_id );
		update_option( '_bbp_db_version', 260 );
		delete_option( '_bbp_db_pending_upgrades' );
		add_filter( 'add_post_metadata', $fail_favorite, 10, 3 );
		add_filter( 'bbp_is_large_install', $small_install );

		try {
			bbp_version_updater();
			$this->assertContains( 'bbp-user-favorites-move', bbp_get_pending_upgrades() );
			$this->assertSame( (int) bbp_get_db_version(), (int) bbp_get_db_version_raw() );
		} finally {
			remove_filter( 'add_post_metadata', $fail_favorite, 10 );
			remove_filter( 'bbp_is_large_install', $small_install );

			if ( null === $old_db_version ) {
				delete_option( '_bbp_db_version' );
			} else {
				update_option( '_bbp_db_version', $old_db_version );
			}

			if ( null === $old_pending ) {
				delete_option( '_bbp_db_pending_upgrades' );
			} else {
				update_option( '_bbp_db_pending_upgrades', $old_pending );
			}
		}
	}

	/**
	 * @covers ::bbp_version_updater
	 */
	public function test_version_updater_does_not_keep_successful_topic_subscriptions_pending() {
		$old_db_version = get_option( '_bbp_db_version', null );
		$old_pending    = get_option( '_bbp_db_pending_upgrades', null );
		$user_id        = $this->factory->user->create();
		$topic_id       = $this->factory->topic->create();
		$small_install  = static function () {
			return false;
		};

		update_user_option( $user_id, '_bbp_subscriptions', $topic_id );
		update_option( '_bbp_db_version', 260 );
		delete_option( '_bbp_db_pending_upgrades' );
		add_filter( 'bbp_is_large_install', $small_install );

		try {
			bbp_version_updater();
			$this->assertSame( array( (string) $user_id ), get_post_meta( $topic_id, '_bbp_subscription', false ) );
			$this->assertNotContains( 'bbp-user-topic-subscriptions-move', bbp_get_pending_upgrades() );
			$this->assertSame( (int) bbp_get_db_version(), (int) bbp_get_db_version_raw() );
		} finally {
			remove_filter( 'bbp_is_large_install', $small_install );

			if ( null === $old_db_version ) {
				delete_option( '_bbp_db_version' );
			} else {
				update_option( '_bbp_db_version', $old_db_version );
			}

			if ( null === $old_pending ) {
				delete_option( '_bbp_db_pending_upgrades' );
			} else {
				update_option( '_bbp_db_pending_upgrades', $old_pending );
			}
		}
	}

	/**
	 * @covers ::bbp_version_updater
	 */
	public function test_version_updater_keeps_failed_topic_subscriptions_pending() {
		$old_db_version    = get_option( '_bbp_db_version', null );
		$old_pending       = get_option( '_bbp_db_pending_upgrades', null );
		$user_id           = $this->factory->user->create();
		$topic_id          = $this->factory->topic->create();
		$fail_subscription = static function ( $check, $object_id, $meta_key ) {
			return ( '_bbp_subscription' === $meta_key ) ? false : $check;
		};
		$small_install     = static function () {
			return false;
		};

		update_user_option( $user_id, '_bbp_subscriptions', $topic_id );
		update_option( '_bbp_db_version', 260 );
		delete_option( '_bbp_db_pending_upgrades' );
		add_filter( 'add_post_metadata', $fail_subscription, 10, 3 );
		add_filter( 'bbp_is_large_install', $small_install );

		try {
			bbp_version_updater();
			$this->assertContains( 'bbp-user-topic-subscriptions-move', bbp_get_pending_upgrades() );
			$this->assertNotContains( 'bbp-user-forum-subscriptions-move', bbp_get_pending_upgrades() );
			$this->assertSame( (int) bbp_get_db_version(), (int) bbp_get_db_version_raw() );
		} finally {
			remove_filter( 'add_post_metadata', $fail_subscription, 10 );
			remove_filter( 'bbp_is_large_install', $small_install );

			if ( null === $old_db_version ) {
				delete_option( '_bbp_db_version' );
			} else {
				update_option( '_bbp_db_version', $old_db_version );
			}

			if ( null === $old_pending ) {
				delete_option( '_bbp_db_pending_upgrades' );
			} else {
				update_option( '_bbp_db_pending_upgrades', $old_pending );
			}
		}
	}

	/**
	 * @covers ::bbp_version_updater
	 */
	public function test_version_updater_removes_saved_converter_query() {
		update_option( '_bbp_db_version', 263 );
		update_option( '_bbp_converter_query', 'UPDATE users SET user_pass = saved_hash' );

		bbp_version_updater();

		$this->assertFalse( get_option( '_bbp_converter_query' ) );
		$this->assertSame( 264, (int) bbp_get_db_version_raw() );
	}

	/**
	 * @covers ::bbp_add_activation_redirect
	 */
	public function test_bbp_add_activation_redirect() {
		$old_user = get_current_user_id();
		$old_get  = $_GET;
		$user_id  = $this->factory->user->create();
		$this->set_current_user( $user_id );

		try {
			bbp_add_activation_redirect();
			$this->assertTrue( (bool) get_user_option( '_bbp_activation_redirect', $user_id ) );

			delete_user_option( $user_id, '_bbp_activation_redirect' );
			$_GET['activate-multi'] = '1';
			bbp_add_activation_redirect();
			$this->assertFalse( (bool) get_user_option( '_bbp_activation_redirect', $user_id ) );
		} finally {
			$_GET = $old_get;
			$this->set_current_user( $old_user );
		}
	}

	/**
	 * @covers ::bbp_do_activation_redirect
	 */
	public function test_bbp_do_activation_redirect() {
		$old_user      = get_current_user_id();
		$old_get       = $_GET;
		$old_screen    = isset( $GLOBALS['current_screen'] ) ? $GLOBALS['current_screen'] : null;
		$subscriber_id = $this->factory->user->create( array( 'role' => 'subscriber' ) );
		$admin_id      = $this->factory->user->create( array( 'role' => 'administrator' ) );
		$redirect      = null;
		$interrupt     = function ( $location ) use ( &$redirect ) {
			$redirect = $location;
			throw new RuntimeException( 'Activation redirect.' );
		};
		$cap_filter    = array( 'BBP_Admin', 'map_settings_meta_caps' );

		bbp_set_user_role( $admin_id, bbp_get_keymaster_role() );

		if ( ! function_exists( 'bbp_admin' ) ) {
			require_once BBP_PLUGIN_DIR . 'includes/admin/actions.php';
		}

		bbp_admin();

		try {
			add_filter( 'bbp_map_meta_caps', $cap_filter, 10, 4 );
			add_filter( 'wp_redirect', $interrupt );

			$this->set_current_user( $admin_id );
			delete_user_option( $admin_id, '_bbp_activation_redirect' );
			bbp_do_activation_redirect();
			$this->assertNull( $redirect );

			update_user_option( $admin_id, '_bbp_activation_redirect', true );
			$_GET['activate-multi'] = '1';
			bbp_do_activation_redirect();
			$this->assertNull( $redirect );
			$this->assertFalse( (bool) get_user_option( '_bbp_activation_redirect', $admin_id ) );
			unset( $_GET['activate-multi'] );

			$GLOBALS['current_screen'] = WP_Screen::get( 'dashboard-network' );
			update_user_option( $admin_id, '_bbp_activation_redirect', true );
			bbp_do_activation_redirect();
			$this->assertNull( $redirect );
			$this->assertFalse( (bool) get_user_option( '_bbp_activation_redirect', $admin_id ) );
			$GLOBALS['current_screen'] = $old_screen;

			$this->set_current_user( $subscriber_id );
			update_user_option( $subscriber_id, '_bbp_activation_redirect', true );
			$this->assertFalse( current_user_can( 'bbp_about_page' ) );
			bbp_do_activation_redirect();
			$this->assertNull( $redirect );
			$this->assertFalse( (bool) get_user_option( '_bbp_activation_redirect', $subscriber_id ) );

			$this->set_current_user( $admin_id );
			$this->assertTrue( current_user_can( 'bbp_about_page' ) );
			update_user_option( $admin_id, '_bbp_activation_redirect', true );

			try {
				bbp_do_activation_redirect();
				$this->fail( 'The activation redirect was not attempted.' );
			} catch ( RuntimeException $exception ) {
				$this->assertSame( 'Activation redirect.', $exception->getMessage() );
			}

			$this->assertSame(
				add_query_arg( array( 'page' => 'bbp-about' ), admin_url( 'index.php' ) ),
				$redirect
			);
			$this->assertFalse( (bool) get_user_option( '_bbp_activation_redirect', $admin_id ) );
		} finally {
			remove_filter( 'bbp_map_meta_caps', $cap_filter, 10 );
			remove_filter( 'wp_redirect', $interrupt );
			$GLOBALS['current_screen'] = $old_screen;
			$_GET                      = $old_get;
			$this->set_current_user( $old_user );
		}
	}

	/**
	 * @covers ::bbp_make_current_user_keymaster
	 */
	public function test_bbp_make_current_user_keymaster() {
		$old_user = get_current_user_id();
		$user_id = $this->factory->user->create( array( 'role' => 'administrator' ) );
		if ( is_multisite() ) {
			grant_super_admin( $user_id );
		}
		$this->set_current_user( $user_id );
		$forum_role = bbp_get_user_role( $user_id );
		if ( $forum_role ) {
			get_userdata( $user_id )->remove_role( $forum_role );
		}

		try {
			$this->assertFalse( bbp_get_user_role( $user_id ) );
			bbp_make_current_user_keymaster();
			$this->assertSame( bbp_get_keymaster_role(), bbp_get_user_role( $user_id ) );

			bbp_make_current_user_keymaster();
			$this->assertSame( bbp_get_keymaster_role(), bbp_get_user_role( $user_id ) );
		} finally {
			$this->set_current_user( $old_user );
			if ( is_multisite() ) {
				revoke_super_admin( $user_id );
			}
		}
	}
}
