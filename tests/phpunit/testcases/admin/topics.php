<?php

class BBP_Tests_Admin_Topics_Redirect_Exception extends Exception {}

/**
 * Tests for topic administration.
 *
 * @group admin
 * @group topics
 */
class BBP_Tests_Admin_Topics extends BBP_UnitTestCase {

	private $get;
	private $request;
	private $request_method;
	private $screen;
	private $had_screen;
	private $admin_notices;
	private $request_uri;
	private $had_request_uri;
	private $post_id;
	private $had_post_id;
	private $global_post;
	private $had_global_post;

	public function setUp(): void {
		parent::setUp();

		$this->get             = $_GET;
		$this->request         = $_REQUEST;
		$this->request_method  = isset( $_SERVER['REQUEST_METHOD'] ) ? $_SERVER['REQUEST_METHOD'] : null;
		$this->had_screen      = array_key_exists( 'current_screen', $GLOBALS );
		$this->screen          = $this->had_screen ? $GLOBALS['current_screen'] : null;
		$this->had_request_uri = array_key_exists( 'REQUEST_URI', $_SERVER );
		$this->request_uri     = $this->had_request_uri ? $_SERVER['REQUEST_URI'] : null;
		$this->had_post_id     = array_key_exists( 'post_ID', $GLOBALS );
		$this->post_id         = $this->had_post_id ? $GLOBALS['post_ID'] : null;
		$this->had_global_post = array_key_exists( 'post', $GLOBALS );
		$this->global_post     = $this->had_global_post ? $GLOBALS['post'] : null;

		if ( ! function_exists( 'bbp_admin' ) ) {
			require_once BBP_PLUGIN_DIR . 'includes/admin/actions.php';
		}

		bbp_admin();

		if ( ! function_exists( 'bbp_admin_topics' ) ) {
			require_once BBP_PLUGIN_DIR . 'includes/admin/topics.php';
		}

		$this->admin_notices = bbp_admin()->notices;
	}

	public function tearDown(): void {
		$_GET                = $this->get;
		$_REQUEST            = $this->request;
		bbp_admin()->notices = $this->admin_notices;

		if ( $this->had_screen ) {
			$GLOBALS['current_screen'] = $this->screen;
		} else {
			unset( $GLOBALS['current_screen'] );
		}

		if ( is_null( $this->request_method ) ) {
			unset( $_SERVER['REQUEST_METHOD'] );
		} else {
			$_SERVER['REQUEST_METHOD'] = $this->request_method;
		}

		if ( $this->had_request_uri ) {
			$_SERVER['REQUEST_URI'] = $this->request_uri;
		} else {
			unset( $_SERVER['REQUEST_URI'] );
		}

		if ( $this->had_post_id ) {
			$GLOBALS['post_ID'] = $this->post_id;
		} else {
			unset( $GLOBALS['post_ID'] );
		}

		if ( $this->had_global_post ) {
			$GLOBALS['post'] = $this->global_post;
		} else {
			unset( $GLOBALS['post'] );
		}

		parent::tearDown();
	}

	private function get_admin_without_hooks() {
		$reflection = new ReflectionClass( 'BBP_Topics_Admin' );
		$admin      = $reflection->newInstanceWithoutConstructor();
		$post_type  = $reflection->getProperty( 'post_type' );
		if ( PHP_VERSION_ID < 80100 ) {
			$post_type->setAccessible( true );
		}
		$post_type->setValue( $admin, bbp_get_topic_post_type() );

		return $admin;
	}

	private function create_keymaster() {
		$user_id = $this->factory->user->create( array( 'role' => 'administrator' ) );
		bbp_set_user_role( $user_id, bbp_get_keymaster_role() );
		$this->set_current_user( $user_id );

		return $user_id;
	}

	private function capture_callback( $callback, ...$arguments ) {
		$level = ob_get_level();
		ob_start();

		try {
			call_user_func_array( $callback, $arguments );
			return ob_get_clean();
		} catch ( Throwable $throwable ) {
			while ( ob_get_level() > $level ) {
				ob_end_clean();
			}

			throw $throwable;
		}
	}

	private function remove_admin_hooks( $admin ) {
		remove_filter( 'post_updated_messages', array( $admin, 'updated_messages' ) );
		remove_filter( 'bulk_actions-edit-topic', array( $admin, 'bulk_actions' ) );
		remove_filter( 'handle_bulk_actions-edit-topic', array( $admin, 'handle_bulk_actions' ), 10 );
		remove_filter( 'bulk_post_updated_messages', array( $admin, 'bulk_post_updated_messages' ), 10 );
		remove_filter( 'manage_' . bbp_get_topic_post_type() . '_posts_columns', array( $admin, 'column_headers' ) );
		remove_action( 'manage_' . bbp_get_topic_post_type() . '_posts_custom_column', array( $admin, 'column_data' ), 10 );
		remove_filter( 'post_row_actions', array( $admin, 'row_actions' ), 10 );
		remove_action( 'add_meta_boxes', array( $admin, 'attributes_metabox' ) );
		remove_action( 'add_meta_boxes', array( $admin, 'author_metabox' ) );
		remove_action( 'add_meta_boxes', array( $admin, 'replies_metabox' ) );
		remove_action( 'add_meta_boxes', array( $admin, 'engagements_metabox' ) );
		remove_action( 'add_meta_boxes', array( $admin, 'favorites_metabox' ) );
		remove_action( 'add_meta_boxes', array( $admin, 'subscriptions_metabox' ) );
		remove_action( 'add_meta_boxes', array( $admin, 'comments_metabox' ) );
		remove_action( 'save_post', array( $admin, 'save_meta_boxes' ) );
		remove_filter( 'wp_insert_post_data', array( $admin, 'filter_post_data' ), 20 );
		remove_action( 'load-edit.php', array( $admin, 'toggle_topic' ) );
		remove_action( 'load-edit.php', array( $admin, 'toggle_topic_notice' ) );
		remove_filter( 'restrict_manage_posts', array( $admin, 'filter_dropdown' ) );
		remove_filter( 'bbp_request', array( $admin, 'filter_post_rows' ) );
		remove_filter( 'manage_posts_extra_tablenav', array( $admin, 'filter_empty_spam' ) );
		remove_action( 'load-edit.php', array( $admin, 'edit_help' ) );
		remove_action( 'load-post.php', array( $admin, 'new_help' ) );
		remove_action( 'load-post-new.php', array( $admin, 'new_help' ) );
	}

	/**
	 * @covers BBP_Topics_Admin::__construct
	 * @ticket 3706
	 */
	public function test_constructor_registers_topic_admin_hooks_and_loaded_action() {
		$loaded  = null;
		$observe = function ( $admin ) use ( &$loaded ) {
			$loaded = $admin;
		};
		add_action( 'bbp_admin_topics_loaded', $observe );

		$admin = new BBP_Topics_Admin();

		try {
			$this->assertSame( $admin, $loaded );
			$this->assertSame( 10, has_filter( 'post_updated_messages', array( $admin, 'updated_messages' ) ) );
			$this->assertSame( 10, has_filter( 'bulk_actions-edit-topic', array( $admin, 'bulk_actions' ) ) );
			$this->assertSame( 10, has_filter( 'handle_bulk_actions-edit-topic', array( $admin, 'handle_bulk_actions' ) ) );
			$this->assertSame( 10, has_filter( 'bulk_post_updated_messages', array( $admin, 'bulk_post_updated_messages' ) ) );
			$this->assertSame( 20, has_filter( 'wp_insert_post_data', array( $admin, 'filter_post_data' ) ) );
			$this->assertSame( 10, has_filter( 'post_row_actions', array( $admin, 'row_actions' ) ) );
			$this->assertSame( 10, has_action( 'save_post', array( $admin, 'save_meta_boxes' ) ) );
			$this->assertSame( 10, has_action( 'load-edit.php', array( $admin, 'toggle_topic' ) ) );
			$this->assertSame( 10, has_action( 'load-edit.php', array( $admin, 'toggle_topic_notice' ) ) );
			$this->assertSame( 10, has_filter( 'restrict_manage_posts', array( $admin, 'filter_dropdown' ) ) );
			$this->assertSame( 10, has_filter( 'bbp_request', array( $admin, 'filter_post_rows' ) ) );
			$this->assertSame( 10, has_filter( 'manage_posts_extra_tablenav', array( $admin, 'filter_empty_spam' ) ) );
		} finally {
			remove_action( 'bbp_admin_topics_loaded', $observe );
			$this->remove_admin_hooks( $admin );
		}
	}

	/**
	 * @covers BBP_Topics_Admin::edit_help
	 * @covers BBP_Topics_Admin::new_help
	 * @ticket 3706
	 */
	public function test_help_methods_add_expected_tabs_sidebar_and_thumbnail_guidance() {
		$admin                  = $this->get_admin_without_hooks();
		$had_theme_features     = array_key_exists( '_wp_theme_features', $GLOBALS );
		$theme_features         = $had_theme_features ? $GLOBALS['_wp_theme_features'] : null;
		$had_post_type_features = array_key_exists( '_wp_post_type_features', $GLOBALS );
		$post_type_features     = $had_post_type_features ? $GLOBALS['_wp_post_type_features'] : null;
		$edit_screen            = WP_Screen::get( 'edit-topic' );
		$edit_sidebar           = $edit_screen->get_help_sidebar();
		$GLOBALS['current_screen'] = $edit_screen;
		$admin->edit_help();
		$tabs = get_current_screen()->get_help_tabs();

		$this->assertSame( array( 'overview', 'screen-content', 'action-links', 'bulk-actions' ), array_keys( $tabs ) );
		$this->assertStringContainsString( 'individual topics', $tabs['overview']['content'] );
		$this->assertStringContainsString( 'bbPress Documentation', get_current_screen()->get_help_sidebar() );

		$new_screen                = WP_Screen::get( 'topic' );
		$new_sidebar               = $new_screen->get_help_sidebar();
		$GLOBALS['current_screen'] = $new_screen;

		try {
			remove_theme_support( 'topic-thumbnails' );
			remove_post_type_support( bbp_get_topic_post_type(), 'thumbnail' );
			$admin->new_help();
			$tabs = get_current_screen()->get_help_tabs();
			$this->assertStringNotContainsString( 'Featured Image', $tabs['publish-box']['content'] );

			add_theme_support( 'topic-thumbnails' );
			add_post_type_support( bbp_get_topic_post_type(), 'thumbnail' );
			$admin->new_help();
			$tabs = get_current_screen()->get_help_tabs();

			$this->assertSame( array( 'customize-display', 'title-topic-editor', 'topic-attributes', 'publish-box' ), array_keys( $tabs ) );
			$this->assertStringContainsString( 'Featured Image', $tabs['publish-box']['content'] );
			$this->assertStringContainsString( 'bbPress Support Forums', get_current_screen()->get_help_sidebar() );
		} finally {
			foreach ( array( 'overview', 'screen-content', 'action-links', 'bulk-actions' ) as $tab_id ) {
				$edit_screen->remove_help_tab( $tab_id );
			}
			$edit_screen->set_help_sidebar( $edit_sidebar );

			foreach ( array( 'customize-display', 'title-topic-editor', 'topic-attributes', 'publish-box' ) as $tab_id ) {
				$new_screen->remove_help_tab( $tab_id );
			}
			$new_screen->set_help_sidebar( $new_sidebar );

			if ( $had_theme_features ) {
				$GLOBALS['_wp_theme_features'] = $theme_features;
			} else {
				unset( $GLOBALS['_wp_theme_features'] );
			}

			if ( $had_post_type_features ) {
				$GLOBALS['_wp_post_type_features'] = $post_type_features;
			} else {
				unset( $GLOBALS['_wp_post_type_features'] );
			}
		}
	}

	/**
	 * @covers BBP_Topics_Admin::bulk_actions
	 * @covers BBP_Topics_Admin::bulk_post_updated_messages
	 * @ticket 3706
	 */
	public function test_bulk_action_labels_respect_status_capability_and_counts() {
		$admin = $this->get_admin_without_hooks();
		$this->set_current_user( 0 );
		$this->assertSame( array( 'edit' => 'Edit' ), $admin->bulk_actions( array( 'edit' => 'Edit' ) ) );

		$this->create_keymaster();
		set_query_var( 'post_status', bbp_get_spam_status_id() );
		$actions = $admin->bulk_actions( array() );
		$this->assertSame( 'Unspam', $actions['unspam'] );

		set_query_var( 'post_status', bbp_get_public_status_id() );
		$actions = $admin->bulk_actions( array() );
		$this->assertSame( 'Spam', $actions['spam'] );

		$messages = $admin->bulk_post_updated_messages( array(), array( 'updated' => 2, 'locked' => 1 ) );
		$this->assertSame( '%s topics updated.', $messages['topic']['updated'] );
		$this->assertSame( '1 topic not updated, somebody is editing it.', $messages['topic']['locked'] );

		$messages = $admin->bulk_post_updated_messages( array(), array( 'updated' => 1, 'locked' => 2 ) );
		$this->assertSame( '%s topic updated.', $messages['topic']['updated'] );
		$this->assertSame( '%s topics not updated, somebody is editing them.', $messages['topic']['locked'] );
	}

	/**
	 * @covers BBP_Topics_Admin::handle_bulk_actions
	 * @ticket 3706
	 */
	public function test_bulk_spam_and_unspam_update_topics_and_sendback_counts() {
		$admin    = $this->get_admin_without_hooks();
		$forum_id = $this->factory->forum->create();
		$topic_id = $this->factory->topic->create( array( 'post_parent' => $forum_id ) );
		$locked_id = $this->factory->topic->create( array( 'post_parent' => $forum_id ) );
		$this->create_keymaster();
		$lock_user_id = $this->factory->user->create();
		update_post_meta( $locked_id, '_edit_lock', time() . ':' . $lock_user_id );

		$sendback = $admin->handle_bulk_actions( 'edit.php?post_type=topic&spam=1', 'spam', array( $topic_id, $locked_id ) );
		$this->assertTrue( bbp_is_topic_spam( $topic_id ) );
		$this->assertFalse( bbp_is_topic_spam( $locked_id ) );
		$this->assertStringContainsString( 'updated=1', $sendback );
		$this->assertStringContainsString( 'locked=1', $sendback );
		$this->assertStringNotContainsString( 'spam=1', $sendback );

		$sendback = $admin->handle_bulk_actions( 'edit.php?post_type=topic', 'unspam', array( $topic_id ) );
		$this->assertFalse( bbp_is_topic_spam( $topic_id ) );
		$this->assertStringContainsString( 'updated=1', $sendback );
		$this->assertStringContainsString( 'ids=' . $topic_id, $sendback );

		$this->assertSame( 'edit.php?post_type=topic', $admin->handle_bulk_actions( 'edit.php?post_type=topic', 'edit', array( $topic_id ) ) );
	}

	/**
	 * @covers BBP_Topics_Admin::handle_bulk_actions
	 * @ticket 3706
	 */
	public function test_bulk_spam_rejects_users_without_moderation_capability() {
		$admin    = $this->get_admin_without_hooks();
		$forum_id = $this->factory->forum->create();
		$topic_id = $this->factory->topic->create( array( 'post_parent' => $forum_id ) );
		$user_id  = $this->factory->user->create();
		bbp_set_user_role( $user_id, bbp_get_participant_role() );
		$this->set_current_user( $user_id );

		try {
			$admin->handle_bulk_actions( 'edit.php?post_type=topic', 'spam', array( $topic_id ) );
			$this->fail( 'A participant should not be able to bulk-spam a topic.' );
		} catch ( WPDieException $error ) {
			$this->assertStringContainsString( 'not allowed', $error->getMessage() );
		}

		$this->assertFalse( bbp_is_topic_spam( $topic_id ) );
	}

	/**
	 * @covers BBP_Topics_Admin::attributes_metabox
	 * @covers BBP_Topics_Admin::author_metabox
	 * @covers BBP_Topics_Admin::replies_metabox
	 * @covers BBP_Topics_Admin::engagements_metabox
	 * @covers BBP_Topics_Admin::favorites_metabox
	 * @covers BBP_Topics_Admin::subscriptions_metabox
	 * @ticket 3706
	 */
	public function test_metabox_registration_respects_edit_action_and_features() {
		$admin          = $this->get_admin_without_hooks();
		$had_metaboxes  = array_key_exists( 'wp_meta_boxes', $GLOBALS );
		$meta_boxes     = $had_metaboxes ? $GLOBALS['wp_meta_boxes'] : null;
		$missing        = 'bbp-missing-option';
		$had_engagement = get_option( '_bbp_enable_engagements', $missing );
		$had_favorites  = get_option( '_bbp_enable_favorites', $missing );
		$had_subs       = get_option( '_bbp_enable_subscriptions', $missing );
		$GLOBALS['wp_meta_boxes'] = array();

		try {
			$_GET = array();
			$admin->attributes_metabox();
			$admin->author_metabox();
			$admin->replies_metabox();
			$admin->engagements_metabox();
			$admin->favorites_metabox();
			$admin->subscriptions_metabox();
			$this->assertArrayHasKey( 'bbp_topic_attributes', $GLOBALS['wp_meta_boxes']['topic']['side']['high'] );
			$this->assertArrayNotHasKey( 'normal', $GLOBALS['wp_meta_boxes']['topic'] );

			update_option( '_bbp_enable_engagements', 0 );
			update_option( '_bbp_enable_favorites', 0 );
			update_option( '_bbp_enable_subscriptions', 0 );
			$_GET['action'] = 'edit';
			$admin->engagements_metabox();
			$admin->favorites_metabox();
			$admin->subscriptions_metabox();
			$this->assertArrayNotHasKey( 'low', $GLOBALS['wp_meta_boxes']['topic']['side'] );
			$this->assertArrayNotHasKey( 'normal', $GLOBALS['wp_meta_boxes']['topic'] );

			update_option( '_bbp_enable_engagements', 1 );
			update_option( '_bbp_enable_favorites', 1 );
			update_option( '_bbp_enable_subscriptions', 1 );
			$_GET['action'] = 'edit';
			$admin->author_metabox();
			$admin->replies_metabox();
			$admin->engagements_metabox();
			$admin->favorites_metabox();
			$admin->subscriptions_metabox();

			$this->assertArrayHasKey( 'bbp_author_metabox', $GLOBALS['wp_meta_boxes']['topic']['side']['high'] );
			$this->assertArrayHasKey( 'bbp_topic_engagements_metabox', $GLOBALS['wp_meta_boxes']['topic']['side']['low'] );
			$this->assertArrayHasKey( 'bbp_topic_replies_metabox', $GLOBALS['wp_meta_boxes']['topic']['normal']['high'] );
			$this->assertArrayHasKey( 'bbp_topic_favorites_metabox', $GLOBALS['wp_meta_boxes']['topic']['normal']['high'] );
			$this->assertArrayHasKey( 'bbp_topic_subscriptions_metabox', $GLOBALS['wp_meta_boxes']['topic']['normal']['high'] );
		} finally {
			foreach (
				array(
					'_bbp_enable_engagements'   => $had_engagement,
					'_bbp_enable_favorites'     => $had_favorites,
					'_bbp_enable_subscriptions' => $had_subs,
				)
				as $option => $value
			) {
				if ( $missing === $value ) {
					delete_option( $option );
				} else {
					update_option( $option, $value );
				}
			}
			if ( $had_metaboxes ) {
				$GLOBALS['wp_meta_boxes'] = $meta_boxes;
			} else {
				unset( $GLOBALS['wp_meta_boxes'] );
			}
		}
	}

	/**
	 * @covers BBP_Topics_Admin::comments_metabox
	 * @ticket 3706
	 */
	public function test_comments_metabox_removes_wordpress_boxes_without_support() {
		$admin          = $this->get_admin_without_hooks();
		$had_metaboxes  = array_key_exists( 'wp_meta_boxes', $GLOBALS );
		$meta_boxes     = $had_metaboxes ? $GLOBALS['wp_meta_boxes'] : null;
		$had_features   = array_key_exists( '_wp_post_type_features', $GLOBALS );
		$features       = $had_features ? $GLOBALS['_wp_post_type_features'] : null;
		$GLOBALS['wp_meta_boxes'] = array();

		try {
			add_post_type_support( bbp_get_topic_post_type(), 'comments' );
			add_meta_box( 'commentstatusdiv', 'Discussion', '__return_null', bbp_get_topic_post_type(), 'normal' );
			add_meta_box( 'commentsdiv', 'Comments', '__return_null', bbp_get_topic_post_type(), 'normal' );
			$admin->comments_metabox();
			$this->assertNotFalse( $GLOBALS['wp_meta_boxes']['topic']['normal']['default']['commentstatusdiv'] );

			remove_post_type_support( bbp_get_topic_post_type(), 'comments' );
			$admin->comments_metabox();
			$this->assertFalse( $GLOBALS['wp_meta_boxes']['topic']['normal']['default']['commentstatusdiv'] );
			$this->assertFalse( $GLOBALS['wp_meta_boxes']['topic']['normal']['default']['commentsdiv'] );
		} finally {
			if ( $had_metaboxes ) {
				$GLOBALS['wp_meta_boxes'] = $meta_boxes;
			} else {
				unset( $GLOBALS['wp_meta_boxes'] );
			}

			if ( $had_features ) {
				$GLOBALS['_wp_post_type_features'] = $features;
			} else {
				unset( $GLOBALS['_wp_post_type_features'] );
			}
		}
	}

	/**
	 * @covers BBP_Topics_Admin::toggle_topic
	 * @ticket 3706
	 */
	public function test_toggle_topic_closes_opens_filters_actions_and_redirects() {
		$admin       = $this->get_admin_without_hooks();
		$forum_id    = $this->factory->forum->create();
		$topic_id    = $this->factory->topic->create( array( 'post_parent' => $forum_id ) );
		$redirects   = array();
		$action_args = array();
		$this->create_keymaster();
		$_SERVER['REQUEST_METHOD'] = 'GET';
		$_SERVER['REQUEST_URI']    = '/wp-admin/edit.php?post_type=topic&action=bbp_toggle_topic_close&topic_id=' . $topic_id;
		$_GET = array(
			'action'   => 'bbp_toggle_topic_close',
			'topic_id' => $topic_id,
			'_wpnonce' => wp_create_nonce( 'close-topic_' . $topic_id ),
		);
		$_REQUEST = $_GET;

		$filter_args = function ( $retval, $id, $action ) use ( $topic_id ) {
			$this->assertSame( $topic_id, $id );
			$this->assertSame( 'bbp_toggle_topic_close', $action );
			$retval['extension'] = 'filtered';
			return $retval;
		};
		$observe_action = function ( $success, $post_data, $action, $retval ) use ( &$action_args ) {
			$action_args = compact( 'success', 'post_data', 'action', 'retval' );
		};
		$stop_redirect = function ( $location, $status ) use ( &$redirects ) {
			$redirects[] = compact( 'location', 'status' );
			throw new BBP_Tests_Admin_Topics_Redirect_Exception( 'Redirect intercepted.' );
		};
		add_filter( 'bbp_toggle_topic_action_admin', $filter_args, 10, 3 );
		add_action( 'bbp_toggle_topic_admin', $observe_action, 10, 4 );
		add_filter( 'wp_redirect', $stop_redirect, 10, 2 );

		try {
			foreach ( array( 'closed', 'opened' ) as $expected_notice ) {
				try {
					$admin->toggle_topic();
					$this->fail( 'The topic toggle should redirect.' );
				} catch ( BBP_Tests_Admin_Topics_Redirect_Exception $error ) {
					$this->assertSame( 'Redirect intercepted.', $error->getMessage() );
				}

				$this->assertSame( 'filtered', $action_args['retval']['extension'] );
				$this->assertSame( $topic_id, $action_args['post_data']['ID'] );
				$this->assertNotFalse( $action_args['success'] );
				$redirect = end( $redirects );
				$this->assertSame( 302, $redirect['status'] );
				$this->assertStringContainsString( 'bbp_topic_toggle_notice=' . $expected_notice, $redirect['location'] );
			}

			$this->assertTrue( bbp_is_topic_open( $topic_id ) );
		} finally {
			remove_filter( 'bbp_toggle_topic_action_admin', $filter_args, 10 );
			remove_action( 'bbp_toggle_topic_admin', $observe_action, 10 );
			remove_filter( 'wp_redirect', $stop_redirect, 10 );
		}
	}

	/**
	 * @covers BBP_Topics_Admin::toggle_topic
	 * @ticket 3706
	 */
	public function test_toggle_topic_rejects_invalid_requests_permissions_and_nonces() {
		$admin    = $this->get_admin_without_hooks();
		$forum_id = $this->factory->forum->create();
		$topic_id = $this->factory->topic->create( array( 'post_parent' => $forum_id ) );
		$_SERVER['REQUEST_METHOD'] = 'POST';
		$_GET = array( 'action' => 'bbp_toggle_topic_close', 'topic_id' => $topic_id );
		$this->assertNull( $admin->toggle_topic() );

		$_SERVER['REQUEST_METHOD'] = 'GET';
		$_GET['action'] = 'not-allowed';
		$this->assertNull( $admin->toggle_topic() );

		$_GET = array( 'action' => 'bbp_toggle_topic_close', 'topic_id' => 999999 );
		try {
			$admin->toggle_topic();
			$this->fail( 'A missing topic should be rejected.' );
		} catch ( WPDieException $error ) {
			$this->assertStringContainsString( 'not found', $error->getMessage() );
		}

		$user_id = $this->factory->user->create();
		bbp_set_user_role( $user_id, bbp_get_participant_role() );
		$this->set_current_user( $user_id );
		$_GET = array( 'action' => 'bbp_toggle_topic_close', 'topic_id' => $topic_id );
		try {
			$admin->toggle_topic();
			$this->fail( 'A participant should not be able to toggle a topic.' );
		} catch ( WPDieException $error ) {
			$this->assertStringContainsString( 'permission', $error->getMessage() );
		}

		$this->create_keymaster();
		$_GET['_wpnonce'] = 'invalid';
		$_REQUEST = $_GET;
		try {
			$admin->toggle_topic();
			$this->fail( 'An invalid topic-toggle nonce should be rejected.' );
		} catch ( WPDieException $error ) {
			$this->assertStringContainsString( 'expired', $error->getMessage() );
		}

		$this->assertTrue( bbp_is_topic_open( $topic_id ) );
	}

	/**
	 * @covers BBP_Topics_Admin::toggle_topic
	 * @ticket 3706
	 */
	public function test_toggle_topic_approves_spams_and_sticks_topics() {
		$admin    = $this->get_admin_without_hooks();
		$forum_id = $this->factory->forum->create();
		$topic_id = $this->factory->topic->create( array( 'post_parent' => $forum_id ) );
		$this->create_keymaster();
		$_SERVER['REQUEST_METHOD'] = 'GET';

		$redirects = array();
		$stop_redirect = function ( $location ) use ( &$redirects ) {
			$redirects[] = $location;
			throw new BBP_Tests_Admin_Topics_Redirect_Exception( 'Redirect intercepted.' );
		};
		add_filter( 'wp_redirect', $stop_redirect );

		try {
			$cases = array(
				array( 'bbp_toggle_topic_approve', 'approve', 'unapproved', 'bbp_is_topic_pending' ),
				array( 'bbp_toggle_topic_approve', 'approve', 'approved', 'bbp_is_topic_public' ),
				array( 'bbp_toggle_topic_spam', 'spam', 'spammed', 'bbp_is_topic_spam' ),
				array( 'bbp_toggle_topic_spam', 'spam', 'unspammed', 'bbp_is_topic_public' ),
				array( 'bbp_toggle_topic_stick', 'stick', 'stuck', 'bbp_is_topic_sticky' ),
				array( 'bbp_toggle_topic_stick', 'stick', 'unstuck', null ),
			);

			foreach ( $cases as $case ) {
				$_GET = array(
					'action'   => $case[0],
					'topic_id' => $topic_id,
					'_wpnonce' => wp_create_nonce( $case[1] . '-topic_' . $topic_id ),
				);
				$_REQUEST = $_GET;
				$_SERVER['REQUEST_URI'] = '/wp-admin/edit.php?post_type=topic';

				try {
					$admin->toggle_topic();
					$this->fail( 'The topic toggle should redirect.' );
				} catch ( BBP_Tests_Admin_Topics_Redirect_Exception $error ) {
					$this->assertSame( 'Redirect intercepted.', $error->getMessage() );
				}
				$this->assertStringContainsString( 'bbp_topic_toggle_notice=' . $case[2], end( $redirects ) );

				if ( $case[3] ) {
					$this->assertTrue( call_user_func( $case[3], $topic_id ) );
				} else {
					$this->assertFalse( bbp_is_topic_sticky( $topic_id ) );
				}
			}

			$_GET = array(
				'action'   => 'bbp_toggle_topic_stick',
				'topic_id' => $topic_id,
				'super'    => '1',
				'_wpnonce' => wp_create_nonce( 'stick-topic_' . $topic_id ),
			);
			$_REQUEST = $_GET;
			try {
				$admin->toggle_topic();
				$this->fail( 'The topic toggle should redirect.' );
			} catch ( BBP_Tests_Admin_Topics_Redirect_Exception $error ) {
				$this->assertSame( 'Redirect intercepted.', $error->getMessage() );
			}
			$this->assertTrue( bbp_is_topic_super_sticky( $topic_id ) );
			$this->assertStringContainsString( 'bbp_topic_toggle_notice=super_sticky', end( $redirects ) );
		} finally {
			remove_filter( 'wp_redirect', $stop_redirect );
		}
	}

	/**
	 * @covers BBP_Topics_Admin::toggle_topic_notice
	 * @ticket 3706
	 */
	public function test_toggle_topic_notice_adds_success_failure_and_filtered_notices() {
		$admin    = $this->get_admin_without_hooks();
		$forum_id = $this->factory->forum->create();
		$topic_id = $this->factory->topic->create( array( 'post_parent' => $forum_id, 'post_title' => 'Notice Topic' ) );
		$_SERVER['REQUEST_METHOD'] = 'GET';
		$_GET = array( 'topic_id' => $topic_id, 'bbp_topic_toggle_notice' => 'closed' );
		bbp_admin()->notices = array();

		$admin->toggle_topic_notice();
		$notices = bbp_admin()->notices;
		$this->assertStringContainsString( 'notice updated', $notices[0] );
		$this->assertStringContainsString( 'successfully closed', $notices[0] );

		$_GET['failed'] = '1';
		$admin->toggle_topic_notice();
		$notices = bbp_admin()->notices;
		$this->assertStringContainsString( 'notice error', $notices[1] );
		$this->assertStringContainsString( 'problem closing', $notices[1] );

		$filter = function () {
			return 'Filtered topic notice.';
		};
		add_filter( 'bbp_toggle_topic_notice_admin', $filter );
		try {
			$admin->toggle_topic_notice();
			$notices = bbp_admin()->notices;
			$this->assertStringContainsString( 'Filtered topic notice.', $notices[2] );
		} finally {
			remove_filter( 'bbp_toggle_topic_notice_admin', $filter );
		}
	}

	/**
	 * @covers BBP_Topics_Admin::toggle_topic_notice
	 * @ticket 3706
	 */
	public function test_toggle_topic_notice_ignores_invalid_requests() {
		$admin    = $this->get_admin_without_hooks();
		$topic_id = $this->factory->topic->create();
		$_SERVER['REQUEST_METHOD'] = 'GET';
		bbp_admin()->notices = array();

		foreach (
			array(
				array(),
				array( 'topic_id' => $topic_id, 'bbp_topic_toggle_notice' => 'not-allowed' ),
				array( 'topic_id' => 999999, 'bbp_topic_toggle_notice' => 'closed' ),
			)
			as $_GET
		) {
			$this->assertNull( $admin->toggle_topic_notice() );
		}

		$this->assertSame( array(), bbp_admin()->notices );
	}

	/**
	 * @covers BBP_Topics_Admin::column_headers
	 * @covers BBP_Topics_Admin::column_data
	 * @ticket 3706
	 */
	public function test_topic_columns_cover_known_and_extension_output() {
		$admin    = $this->get_admin_without_hooks();
		$forum_id = $this->factory->forum->create( array( 'post_title' => 'Column Forum' ) );
		$user_id  = $this->factory->user->create( array( 'display_name' => 'Column Author' ) );
		$topic_id = $this->factory->topic->create( array( 'post_parent' => $forum_id, 'post_author' => $user_id ) );

		$columns = $admin->column_headers( array( 'legacy' => 'Legacy' ) );
		$this->assertSame( array( 'cb', 'title', 'bbp_topic_forum', 'bbp_topic_reply_count', 'bbp_topic_voice_count', 'bbp_topic_author', 'bbp_topic_created', 'bbp_topic_freshness' ), array_keys( $columns ) );
		$this->assertSame( 'Column Forum', $this->capture_callback( array( $admin, 'column_data' ), 'bbp_topic_forum', $topic_id ) );

		$topic_without_forum = $this->factory->topic->create( array( 'post_parent' => 0 ) );
		$output = $this->capture_callback( array( $admin, 'column_data' ), 'bbp_topic_forum', $topic_without_forum );
		$this->assertStringContainsString( 'No forum', $output );
		$this->assertSame( '0', $this->capture_callback( array( $admin, 'column_data' ), 'bbp_topic_reply_count', $topic_id ) );
		$this->assertSame( '1', $this->capture_callback( array( $admin, 'column_data' ), 'bbp_topic_voice_count', $topic_id ) );
		$this->assertStringContainsString( 'Column Author', $this->capture_callback( array( $admin, 'column_data' ), 'bbp_topic_author', $topic_id ) );

		$GLOBALS['post'] = get_post( $topic_id );
		$this->assertStringContainsString( '<br />', $this->capture_callback( array( $admin, 'column_data' ), 'bbp_topic_created', $topic_id ) );
		$this->assertNotSame( '', $this->capture_callback( array( $admin, 'column_data' ), 'bbp_topic_freshness', $topic_id ) );

		$extension = function ( $column, $id ) use ( $topic_id ) {
			if ( 'extension' === $column && $topic_id === $id ) {
				echo 'Extension output';
			}
		};
		add_action( 'bbp_admin_topics_column_data', $extension, 10, 2 );
		try {
			$this->assertSame( 'Extension output', $this->capture_callback( array( $admin, 'column_data' ), 'extension', $topic_id ) );
		} finally {
			remove_action( 'bbp_admin_topics_column_data', $extension, 10 );
		}
	}

	/**
	 * @covers BBP_Topics_Admin::row_actions
	 * @ticket 3706
	 */
	public function test_row_actions_cover_public_pending_spam_and_trash_states() {
		$admin    = $this->get_admin_without_hooks();
		$forum_id = $this->factory->forum->create();
		$topic_id = $this->factory->topic->create( array( 'post_parent' => $forum_id ) );
		$this->create_keymaster();
		$_SERVER['REQUEST_URI'] = '/wp-admin/edit.php?post_type=topic';

		$actions = $admin->row_actions( array( 'inline hide-if-no-js' => 'Quick Edit', 'edit' => 'Edit' ), get_post( $topic_id ) );
		$this->assertSame( array( 'edit', 'stick', 'unapproved', 'closed', 'spam' ), array_slice( array_keys( $actions ), 0, 5 ) );
		$this->assertArrayHasKey( empty( bbp_get_trash_days( bbp_get_topic_post_type() ) ) ? 'delete' : 'trash', $actions );
		$this->assertStringContainsString( '(to front)', $actions['stick'] );

		bbp_unapprove_topic( $topic_id );
		$actions = $admin->row_actions( array( 'edit' => 'Edit' ), get_post( $topic_id ) );
		$this->assertArrayHasKey( 'approved', $actions );
		$this->assertArrayHasKey( 'view', $actions );
		$this->assertArrayNotHasKey( 'stick', $actions );

		bbp_spam_topic( $topic_id );
		$actions = $admin->row_actions( array( 'edit' => 'Edit' ), get_post( $topic_id ) );
		$this->assertArrayHasKey( 'unspam', $actions );
		$this->assertArrayNotHasKey( 'stick', $actions );

		wp_trash_post( $topic_id );
		$actions = $admin->row_actions( array( 'edit' => 'Edit' ), get_post( $topic_id ) );
		$this->assertArrayHasKey( 'untrash', $actions );
		$this->assertArrayHasKey( 'delete', $actions );
		$this->assertArrayHasKey( 'spam', $actions );
	}

	/**
	 * @covers BBP_Topics_Admin::filter_dropdown
	 * @covers BBP_Topics_Admin::filter_empty_spam
	 * @ticket 3706
	 */
	public function test_list_filters_select_forum_and_limit_empty_spam_to_moderators() {
		$admin    = $this->get_admin_without_hooks();
		$forum_id = $this->factory->forum->create( array( 'post_title' => 'Selected Forum' ) );
		$_GET['bbp_forum_id'] = (string) $forum_id;
		$output = $this->capture_callback( array( $admin, 'filter_dropdown' ) );
		$this->assertStringContainsString( 'In all forums', $output );
		$this->assertStringContainsString( 'value="' . $forum_id . '" selected=', $output );

		$this->create_keymaster();
		$_GET['post_status'] = bbp_get_spam_status_id();
		$output = $this->capture_callback( array( $admin, 'filter_empty_spam' ) );
		$this->assertStringContainsString( 'Empty Spam', $output );
		$this->assertStringContainsString( '_destroy_nonce', $output );

		$_GET['post_status'] = bbp_get_public_status_id();
		$this->assertSame( '', $this->capture_callback( array( $admin, 'filter_empty_spam' ) ) );
	}

	/**
	 * @covers BBP_Topics_Admin::updated_messages
	 * @ticket 3706
	 */
	public function test_updated_messages_cover_topic_states_and_revision() {
		$admin      = $this->get_admin_without_hooks();
		$forum_id   = $this->factory->forum->create();
		$topic_id   = $this->factory->topic->create( array( 'post_parent' => $forum_id ) );
		$revision_id = wp_save_post_revision( $topic_id );
		$GLOBALS['post_ID'] = $topic_id;
		$GLOBALS['post']    = get_post( $topic_id );
		$_GET['revision']   = $revision_id;

		$messages = $admin->updated_messages( array() );
		$this->assertStringContainsString( 'Topic updated.', $messages['topic'][1] );
		$this->assertStringContainsString( 'restored to revision', $messages['topic'][5] );
		$this->assertStringContainsString( 'Topic created.', $messages['topic'][6] );
		$this->assertStringContainsString( 'preview=true', $messages['topic'][8] );
		$this->assertStringContainsString( 'Topic scheduled for:', $messages['topic'][9] );
		$this->assertStringContainsString( 'Topic draft updated.', $messages['topic'][10] );
	}

	/**
	 * @covers ::bbp_admin_topics
	 * @ticket 3706
	 */
	public function test_admin_topics_loads_only_for_topic_site_admin_screen() {
		$original = isset( bbp_admin()->topics ) ? bbp_admin()->topics : null;
		$screen   = WP_Screen::get( 'edit-topic' );
		$GLOBALS['current_screen'] = $screen;
		unset( bbp_admin()->topics );

		try {
			bbp_admin_topics( $screen );
			$this->assertInstanceOf( 'BBP_Topics_Admin', bbp_admin()->topics );
			$this->remove_admin_hooks( bbp_admin()->topics );

			unset( bbp_admin()->topics );
			$screen->post_type = bbp_get_forum_post_type();
			bbp_admin_topics( $screen );
			$this->assertFalse( isset( bbp_admin()->topics ) );
		} finally {
			if ( $original ) {
				bbp_admin()->topics = $original;
			} else {
				unset( bbp_admin()->topics );
			}
		}
	}
}
