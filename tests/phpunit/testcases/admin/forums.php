<?php

/**
 * Tests for forum administration.
 *
 * @group admin
 * @group forums
 */
class BBP_Tests_Admin_Forums_Redirect_Exception extends Exception {}

class BBP_Tests_Admin_Forums extends BBP_UnitTestCase {

	private $get;
	private $post;
	private $request;
	private $request_method;
	private $screen;
	private $had_screen;
	private $global_post;
	private $had_global_post;
	private $admin_notices;
	private $request_uri;
	private $had_request_uri;

	public function setUp(): void {
		parent::setUp();

		$this->get            = $_GET;
		$this->post           = $_POST;
		$this->request        = $_REQUEST;
		$this->request_method = isset( $_SERVER['REQUEST_METHOD'] ) ? $_SERVER['REQUEST_METHOD'] : null;
		$this->had_screen      = array_key_exists( 'current_screen', $GLOBALS );
		$this->screen          = $this->had_screen ? $GLOBALS['current_screen'] : null;
		$this->had_global_post = array_key_exists( 'post', $GLOBALS );
		$this->global_post     = $this->had_global_post ? $GLOBALS['post'] : null;
		$this->had_request_uri = array_key_exists( 'REQUEST_URI', $_SERVER );
		$this->request_uri     = $this->had_request_uri ? $_SERVER['REQUEST_URI'] : null;

		if ( ! function_exists( 'bbp_admin' ) ) {
			require_once BBP_PLUGIN_DIR . 'includes/admin/actions.php';
		}

		bbp_admin();

		if ( ! function_exists( 'bbp_admin_forums' ) ) {
			require_once BBP_PLUGIN_DIR . 'includes/admin/forums.php';
		}

		$this->admin_notices = bbp_admin()->notices;
	}

	public function tearDown(): void {
		$_GET                = $this->get;
		$_POST               = $this->post;
		$_REQUEST            = $this->request;
		bbp_admin()->notices = $this->admin_notices;

		if ( $this->had_screen ) {
			$GLOBALS['current_screen'] = $this->screen;
		} else {
			unset( $GLOBALS['current_screen'] );
		}

		if ( $this->had_global_post ) {
			$GLOBALS['post'] = $this->global_post;
		} else {
			unset( $GLOBALS['post'] );
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

		parent::tearDown();
	}

	private function get_admin_without_hooks() {
		$reflection = new ReflectionClass( 'BBP_Forums_Admin' );
		$admin      = $reflection->newInstanceWithoutConstructor();
		$post_type  = $reflection->getProperty( 'post_type' );
		if ( PHP_VERSION_ID < 80100 ) {
			$post_type->setAccessible( true );
		}
		$post_type->setValue( $admin, bbp_get_forum_post_type() );

		return $admin;
	}

	private function invoke_private_method( $admin, $method, $arguments = array() ) {
		$reflection = new ReflectionMethod( $admin, $method );
		if ( PHP_VERSION_ID < 80100 ) {
			$reflection->setAccessible( true );
		}

		return $reflection->invokeArgs( $admin, $arguments );
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
		remove_filter( 'manage_' . bbp_get_forum_post_type() . '_posts_columns', array( $admin, 'column_headers' ) );
		remove_action( 'manage_' . bbp_get_forum_post_type() . '_posts_custom_column', array( $admin, 'column_data' ), 10 );
		remove_filter( 'page_row_actions', array( $admin, 'row_actions' ), 10 );
		remove_action( 'add_meta_boxes', array( $admin, 'attributes_metabox' ) );
		remove_action( 'add_meta_boxes', array( $admin, 'moderators_metabox' ) );
		remove_action( 'add_meta_boxes', array( $admin, 'subscriptions_metabox' ) );
		remove_action( 'add_meta_boxes', array( $admin, 'comments_metabox' ) );
		remove_action( 'save_post', array( $admin, 'save_meta_boxes' ) );
		remove_action( 'load-edit.php', array( $admin, 'toggle_forum' ) );
		remove_action( 'load-edit.php', array( $admin, 'toggle_forum_notice' ) );
		remove_action( 'load-edit.php', array( $admin, 'edit_help' ) );
		remove_action( 'load-post.php', array( $admin, 'new_help' ) );
		remove_action( 'load-post-new.php', array( $admin, 'new_help' ) );
	}

	/**
	 * @covers BBP_Forums_Admin::__construct
	 * @ticket 3706
	 */
	public function test_constructor_registers_forum_admin_hooks_and_loaded_action() {
		$loaded = null;
		$observe = function ( $admin ) use ( &$loaded ) {
			$loaded = $admin;
		};
		add_action( 'bbp_admin_forums_loaded', $observe );

		$admin = new BBP_Forums_Admin();

		try {
			$this->assertSame( $admin, $loaded );
			$this->assertSame( 10, has_filter( 'post_updated_messages', array( $admin, 'updated_messages' ) ) );
			$this->assertSame( 10, has_filter( 'manage_' . bbp_get_forum_post_type() . '_posts_columns', array( $admin, 'column_headers' ) ) );
			$this->assertSame( 10, has_action( 'manage_' . bbp_get_forum_post_type() . '_posts_custom_column', array( $admin, 'column_data' ) ) );
			$this->assertSame( 10, has_filter( 'page_row_actions', array( $admin, 'row_actions' ) ) );
			$this->assertSame( 10, has_action( 'add_meta_boxes', array( $admin, 'attributes_metabox' ) ) );
			$this->assertSame( 10, has_action( 'add_meta_boxes', array( $admin, 'moderators_metabox' ) ) );
			$this->assertSame( 10, has_action( 'add_meta_boxes', array( $admin, 'subscriptions_metabox' ) ) );
			$this->assertSame( 10, has_action( 'add_meta_boxes', array( $admin, 'comments_metabox' ) ) );
			$this->assertSame( 10, has_action( 'save_post', array( $admin, 'save_meta_boxes' ) ) );
			$this->assertSame( 10, has_action( 'load-edit.php', array( $admin, 'toggle_forum' ) ) );
			$this->assertSame( 10, has_action( 'load-edit.php', array( $admin, 'toggle_forum_notice' ) ) );
			$this->assertSame( 10, has_action( 'load-edit.php', array( $admin, 'edit_help' ) ) );
			$this->assertSame( 10, has_action( 'load-post.php', array( $admin, 'new_help' ) ) );
			$this->assertSame( 10, has_action( 'load-post-new.php', array( $admin, 'new_help' ) ) );
		} finally {
			remove_action( 'bbp_admin_forums_loaded', $observe );
			$this->remove_admin_hooks( $admin );
		}
	}

	/**
	 * @covers BBP_Forums_Admin::edit_help
	 * @covers BBP_Forums_Admin::new_help
	 * @ticket 3706
	 */
	public function test_help_methods_add_expected_tabs_and_sidebar() {
		$admin                  = $this->get_admin_without_hooks();
		$had_theme_features     = array_key_exists( '_wp_theme_features', $GLOBALS );
		$theme_features         = $had_theme_features ? $GLOBALS['_wp_theme_features'] : null;
		$had_post_type_features = array_key_exists( '_wp_post_type_features', $GLOBALS );
		$post_type_features     = $had_post_type_features ? $GLOBALS['_wp_post_type_features'] : null;

		$edit_screen                  = WP_Screen::get( 'edit-forum' );
		$edit_sidebar                 = $edit_screen->get_help_sidebar();
		$GLOBALS['current_screen']    = $edit_screen;
		$admin->edit_help();
		$tabs = get_current_screen()->get_help_tabs();

		$this->assertSame( array( 'overview', 'screen-content', 'action-links', 'bulk-actions' ), array_keys( $tabs ) );
		$this->assertStringContainsString( 'individual forums', $tabs['overview']['content'] );
		$this->assertStringContainsString( 'bbPress Documentation', get_current_screen()->get_help_sidebar() );

		$new_screen               = WP_Screen::get( 'forum' );
		$new_sidebar              = $new_screen->get_help_sidebar();
		$GLOBALS['current_screen'] = $new_screen;

		try {
			remove_theme_support( 'forum-thumbnails' );
			remove_post_type_support( bbp_get_forum_post_type(), 'thumbnail' );
			$admin->new_help();
			$tabs = get_current_screen()->get_help_tabs();
			$this->assertStringNotContainsString( 'Featured Image', $tabs['publish-box']['content'] );

			add_theme_support( 'forum-thumbnails' );
			add_post_type_support( bbp_get_forum_post_type(), 'thumbnail' );
			$admin->new_help();
			$tabs = get_current_screen()->get_help_tabs();

			$this->assertSame( array( 'customize-display', 'title-forum-editor', 'forum-attributes', 'publish-box' ), array_keys( $tabs ) );
			$this->assertStringContainsString( 'Featured Image', $tabs['publish-box']['content'] );
			$this->assertStringContainsString( 'bbPress Support Forums', get_current_screen()->get_help_sidebar() );
		} finally {
			foreach ( array( 'overview', 'screen-content', 'action-links', 'bulk-actions' ) as $tab_id ) {
				$edit_screen->remove_help_tab( $tab_id );
			}
			$edit_screen->set_help_sidebar( $edit_sidebar );

			foreach ( array( 'customize-display', 'title-forum-editor', 'forum-attributes', 'publish-box' ) as $tab_id ) {
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
	 * @covers BBP_Forums_Admin::attributes_metabox
	 * @covers BBP_Forums_Admin::moderators_metabox
	 * @covers BBP_Forums_Admin::subscriptions_metabox
	 * @ticket 3706
	 */
	public function test_metabox_registration_honors_permissions_features_and_context() {
		$admin          = $this->get_admin_without_hooks();
		$had_metaboxes  = array_key_exists( 'wp_meta_boxes', $GLOBALS );
		$old_metaboxes  = $had_metaboxes ? $GLOBALS['wp_meta_boxes'] : null;
		$post_type      = bbp_get_forum_post_type();
		$_GET           = array();
		$GLOBALS['wp_meta_boxes'] = array();

		try {
			$this->set_current_user( 0 );
			$admin->attributes_metabox();
			$admin->moderators_metabox();
			$admin->subscriptions_metabox();
			$this->assertArrayNotHasKey( $post_type, $GLOBALS['wp_meta_boxes'] );

			$this->create_keymaster();
			update_option( '_bbp_allow_forum_mods', 0 );
			update_option( '_bbp_enable_subscriptions', 0 );
			$_GET['action'] = 'edit';
			$admin->attributes_metabox();
			$admin->moderators_metabox();
			$admin->subscriptions_metabox();

			$this->assertArrayHasKey( 'bbp_forum_attributes', $GLOBALS['wp_meta_boxes'][ $post_type ]['side']['high'] );
			$this->assertArrayNotHasKey( 'bbp_moderator_assignment_metabox', $GLOBALS['wp_meta_boxes'][ $post_type ]['side']['high'] );
			$this->assertArrayNotHasKey( 'normal', $GLOBALS['wp_meta_boxes'][ $post_type ] );

			update_option( '_bbp_allow_forum_mods', 1 );
			update_option( '_bbp_enable_subscriptions', 1 );
			$admin->moderators_metabox();
			$admin->subscriptions_metabox();

			$this->assertArrayHasKey( 'bbp_moderator_assignment_metabox', $GLOBALS['wp_meta_boxes'][ $post_type ]['side']['high'] );
			$this->assertArrayHasKey( 'bbp_forum_subscriptions_metabox', $GLOBALS['wp_meta_boxes'][ $post_type ]['normal']['high'] );
		} finally {
			if ( $had_metaboxes ) {
				$GLOBALS['wp_meta_boxes'] = $old_metaboxes;
			} else {
				unset( $GLOBALS['wp_meta_boxes'] );
			}
		}
	}

	/**
	 * @covers BBP_Forums_Admin::comments_metabox
	 * @ticket 3706
	 */
	public function test_comments_metabox_removes_unsupported_wordpress_boxes() {
		$admin                  = $this->get_admin_without_hooks();
		$had_metaboxes          = array_key_exists( 'wp_meta_boxes', $GLOBALS );
		$old_metaboxes          = $had_metaboxes ? $GLOBALS['wp_meta_boxes'] : null;
		$had_post_type_features = array_key_exists( '_wp_post_type_features', $GLOBALS );
		$post_type_features     = $had_post_type_features ? $GLOBALS['_wp_post_type_features'] : null;
		$post_type              = bbp_get_forum_post_type();
		$GLOBALS['wp_meta_boxes'] = array();
		remove_post_type_support( $post_type, 'comments' );

		try {
			add_meta_box( 'commentstatusdiv', 'Discussion', '__return_null', $post_type, 'normal' );
			add_meta_box( 'commentsdiv', 'Comments', '__return_null', $post_type, 'normal' );
			$admin->comments_metabox();

			$this->assertFalse( $GLOBALS['wp_meta_boxes'][ $post_type ]['normal']['default']['commentstatusdiv'] );
			$this->assertFalse( $GLOBALS['wp_meta_boxes'][ $post_type ]['normal']['default']['commentsdiv'] );

			$GLOBALS['wp_meta_boxes'] = array();
			add_post_type_support( $post_type, 'comments' );
			add_meta_box( 'commentstatusdiv', 'Discussion', '__return_null', $post_type, 'normal' );
			add_meta_box( 'commentsdiv', 'Comments', '__return_null', $post_type, 'normal' );
			$admin->comments_metabox();
			$this->assertIsArray( $GLOBALS['wp_meta_boxes'][ $post_type ]['normal']['default']['commentstatusdiv'] );
			$this->assertIsArray( $GLOBALS['wp_meta_boxes'][ $post_type ]['normal']['default']['commentsdiv'] );
		} finally {
			if ( $had_metaboxes ) {
				$GLOBALS['wp_meta_boxes'] = $old_metaboxes;
			} else {
				unset( $GLOBALS['wp_meta_boxes'] );
			}

			if ( $had_post_type_features ) {
				$GLOBALS['_wp_post_type_features'] = $post_type_features;
			} else {
				unset( $GLOBALS['_wp_post_type_features'] );
			}
		}
	}

	/**
	 * @covers BBP_Forums_Admin::save_meta_boxes
	 * @ticket 3706
	 */
	public function test_save_meta_boxes_requires_post_nonce_forum_and_permissions() {
		$admin       = $this->get_admin_without_hooks();
		$forum_id    = $this->factory->forum->create();
		$post_id     = $this->factory->post->create();
		$action_runs = 0;
		$observe     = function () use ( &$action_runs ) {
			$action_runs++;
		};
		update_post_meta( $forum_id, '_bbp_forum_id', 77 );
		update_post_meta( $post_id, '_bbp_forum_id', 88 );
		add_action( 'bbp_forum_attributes_metabox_save', $observe );

		try {
			$this->create_keymaster();
			$_SERVER['REQUEST_METHOD'] = 'GET';
			$_POST = array(
				'bbp_forum_metabox' => wp_create_nonce( 'bbp_forum_metabox_save' ),
				'parent_id'         => 123,
			);
			$admin->save_meta_boxes( $forum_id );
			$this->assertSame( 77, (int) get_post_meta( $forum_id, '_bbp_forum_id', true ) );

			$_SERVER['REQUEST_METHOD'] = 'POST';
			unset( $_POST['bbp_forum_metabox'] );
			$admin->save_meta_boxes( $forum_id );
			$this->assertSame( 77, (int) get_post_meta( $forum_id, '_bbp_forum_id', true ) );

			$_POST['bbp_forum_metabox'] = wp_create_nonce( 'bbp_forum_metabox_save' );
			$admin->save_meta_boxes( $post_id );
			$this->assertSame( 88, (int) get_post_meta( $post_id, '_bbp_forum_id', true ) );

			$this->create_keymaster();
			$deny_assignment = function ( $allcaps ) {
				$allcaps['assign_moderators'] = false;
				return $allcaps;
			};
			add_filter( 'user_has_cap', $deny_assignment );

			try {
				$this->assertTrue( current_user_can( 'edit_forum', $forum_id ) );
				$this->assertFalse( current_user_can( 'assign_moderators' ) );
				$_POST['bbp_forum_metabox'] = wp_create_nonce( 'bbp_forum_metabox_save' );
				$admin->save_meta_boxes( $forum_id );
				$this->assertSame( 77, (int) get_post_meta( $forum_id, '_bbp_forum_id', true ) );
			} finally {
				remove_filter( 'user_has_cap', $deny_assignment );
			}

			$deny_edit = function ( $allcaps, $caps, $args ) use ( $forum_id ) {
				if ( ( 'edit_forum' === $args[0] ) && ( $forum_id === $args[2] ) ) {
					foreach ( $caps as $capability ) {
						$allcaps[ $capability ] = false;
					}
				}

				return $allcaps;
			};
			add_filter( 'user_has_cap', $deny_edit, 10, 3 );

			try {
				$this->assertFalse( current_user_can( 'edit_forum', $forum_id ) );
				$this->assertTrue( current_user_can( 'assign_moderators' ) );
				$_POST['bbp_forum_metabox'] = wp_create_nonce( 'bbp_forum_metabox_save' );
				$admin->save_meta_boxes( $forum_id );
				$this->assertSame( 77, (int) get_post_meta( $forum_id, '_bbp_forum_id', true ) );
			} finally {
				remove_filter( 'user_has_cap', $deny_edit, 10 );
			}

			$this->set_current_user( 0 );
			$this->assertFalse( current_user_can( 'edit_forum', $forum_id ) );
			$_POST['bbp_forum_metabox'] = wp_create_nonce( 'bbp_forum_metabox_save' );
			$admin->save_meta_boxes( $forum_id );
			$this->assertSame( 77, (int) get_post_meta( $forum_id, '_bbp_forum_id', true ) );
			$this->assertSame( 0, $action_runs );
		} finally {
			remove_action( 'bbp_forum_attributes_metabox_save', $observe );
		}
	}

	/**
	 * @covers BBP_Forums_Admin::save_meta_boxes
	 * @ticket 3706
	 */
	public function test_save_meta_boxes_updates_parent_and_fires_extension_action() {
		$admin     = $this->get_admin_without_hooks();
		$parent_id = $this->factory->forum->create();
		$forum_id  = $this->factory->forum->create();
		$saved_id  = null;
		$observe   = function ( $id ) use ( &$saved_id ) {
			$saved_id = $id;
		};
		$this->create_keymaster();
		$_SERVER['REQUEST_METHOD'] = 'POST';
		$_POST = array(
			'bbp_forum_metabox' => wp_create_nonce( 'bbp_forum_metabox_save' ),
			'parent_id'         => (string) $parent_id,
		);
		add_action( 'bbp_forum_attributes_metabox_save', $observe );

		try {
			$this->assertSame( $forum_id, $admin->save_meta_boxes( $forum_id ) );
			$this->assertSame( $parent_id, (int) get_post_meta( $forum_id, '_bbp_forum_id', true ) );
			$this->assertSame( $forum_id, $saved_id );

			$_POST['parent_id'] = 'invalid';
			$admin->save_meta_boxes( $forum_id );
			$this->assertSame( 0, (int) get_post_meta( $forum_id, '_bbp_forum_id', true ) );
		} finally {
			remove_action( 'bbp_forum_attributes_metabox_save', $observe );
		}
	}

	/**
	 * @covers BBP_Forums_Admin::toggle_forum
	 * @ticket 3706
	 */
	public function test_toggle_forum_ignores_missing_and_unrecognized_requests() {
		$admin = $this->get_admin_without_hooks();
		$_SERVER['REQUEST_METHOD'] = 'POST';
		$_GET = array( 'action' => 'bbp_toggle_forum_close', 'forum_id' => 123 );
		$this->assertNull( $admin->toggle_forum() );

		$_SERVER['REQUEST_METHOD'] = 'GET';
		$_GET['action'] = 'not-allowed';
		$this->assertNull( $admin->toggle_forum() );
	}

	/**
	 * @covers BBP_Forums_Admin::toggle_forum
	 * @ticket 3706
	 */
	public function test_toggle_forum_rejects_missing_forum_and_insufficient_permissions() {
		$admin = $this->get_admin_without_hooks();
		$_SERVER['REQUEST_METHOD'] = 'GET';
		$_GET = array( 'action' => 'bbp_toggle_forum_close', 'forum_id' => 999999 );

		try {
			$admin->toggle_forum();
			$this->fail( 'A missing forum should be rejected.' );
		} catch ( WPDieException $error ) {
			$this->assertStringContainsString( 'not found', $error->getMessage() );
		}

		$forum_id       = $this->factory->forum->create();
		$_GET['forum_id'] = $forum_id;
		$this->set_current_user( $this->factory->user->create( array( 'role' => 'subscriber' ) ) );

		try {
			$admin->toggle_forum();
			$this->fail( 'A user without forum-management permission should be rejected.' );
		} catch ( WPDieException $error ) {
			$this->assertStringContainsString( 'permission', $error->getMessage() );
		}

		$this->create_keymaster();
		$_GET = array(
			'action'   => 'bbp_toggle_forum_close',
			'forum_id' => $forum_id,
			'_wpnonce' => 'invalid',
		);
		$_REQUEST = $_GET;

		try {
			$admin->toggle_forum();
			$this->fail( 'An invalid forum-toggle nonce should be rejected.' );
		} catch ( WPDieException $error ) {
			$this->assertStringContainsString( 'expired', $error->getMessage() );
		}

		$this->assertTrue( bbp_is_forum_open( $forum_id ) );
	}

	/**
	 * @covers BBP_Forums_Admin::toggle_forum
	 * @ticket 3706
	 */
	public function test_toggle_forum_closes_opens_filters_actions_and_redirects() {
		$admin      = $this->get_admin_without_hooks();
		$forum_id   = $this->factory->forum->create();
		$redirects  = array();
		$action_args = array();
		$this->create_keymaster();
		$_SERVER['REQUEST_METHOD'] = 'GET';
		$_SERVER['REQUEST_URI']    = '/wp-admin/edit.php?post_type=forum&action=bbp_toggle_forum_close&forum_id=' . $forum_id;
		$_GET = array(
			'action'    => 'bbp_toggle_forum_close',
			'forum_id'  => $forum_id,
			'_wpnonce'  => wp_create_nonce( 'close-forum_' . $forum_id ),
		);
		$_REQUEST = $_GET;

		$filter_args = function ( $retval, $id, $action ) use ( $forum_id ) {
			$this->assertSame( $forum_id, $id );
			$this->assertSame( 'bbp_toggle_forum_close', $action );
			$retval['extension'] = 'filtered';
			return $retval;
		};
		$observe_action = function ( $success, $post_data, $action, $retval ) use ( &$action_args ) {
			$action_args = compact( 'success', 'post_data', 'action', 'retval' );
		};
		$stop_redirect = function ( $location, $status ) use ( &$redirects ) {
			$redirects[] = compact( 'location', 'status' );
			throw new BBP_Tests_Admin_Forums_Redirect_Exception( 'Redirect intercepted.' );
		};
		add_filter( 'bbp_toggle_forum_action_admin', $filter_args, 10, 3 );
		add_action( 'bbp_toggle_forum_admin', $observe_action, 10, 4 );
		add_filter( 'wp_redirect', $stop_redirect, 10, 2 );

		try {
			foreach ( array( 'closed', 'opened' ) as $expected_notice ) {
				try {
					$admin->toggle_forum();
					$this->fail( 'The forum toggle should redirect.' );
				} catch ( BBP_Tests_Admin_Forums_Redirect_Exception $error ) {
					$this->assertSame( 'Redirect intercepted.', $error->getMessage() );
				}

				$this->assertSame( 'filtered', $action_args['retval']['extension'] );
				$this->assertSame( $forum_id, $action_args['post_data']['ID'] );
				$this->assertSame( 'bbp_toggle_forum_close', $action_args['action'] );
				$this->assertNotFalse( $action_args['success'] );
				$redirect = end( $redirects );
				$this->assertSame( 302, $redirect['status'] );
				$this->assertStringContainsString( 'bbp_forum_toggle_notice=' . $expected_notice, $redirect['location'] );
				$this->assertStringContainsString( 'extension=filtered', $redirect['location'] );
				$this->assertArrayNotHasKey( 'failed', $action_args['retval'] );
			}

			$this->assertTrue( bbp_is_forum_open( $forum_id ) );
		} finally {
			remove_filter( 'bbp_toggle_forum_action_admin', $filter_args, 10 );
			remove_action( 'bbp_toggle_forum_admin', $observe_action, 10 );
			remove_filter( 'wp_redirect', $stop_redirect, 10 );
		}
	}

	/**
	 * @covers BBP_Forums_Admin::toggle_forum_notice
	 * @ticket 3706
	 */
	public function test_toggle_forum_notice_adds_success_failure_and_filtered_notices() {
		$admin    = $this->get_admin_without_hooks();
		$forum_id = $this->factory->forum->create( array( 'post_title' => 'Notice Forum' ) );
		$_SERVER['REQUEST_METHOD'] = 'GET';
		$_GET = array( 'forum_id' => $forum_id, 'bbp_forum_toggle_notice' => 'opened' );
		bbp_admin()->notices = array();

		$admin->toggle_forum_notice();
		$notices = bbp_admin()->notices;
		$notice  = end( $notices );
		$this->assertStringContainsString( 'successfully opened', $notice );
		$this->assertStringContainsString( 'notice updated', $notice );

		$_GET['bbp_forum_toggle_notice'] = 'closed';
		$_GET['failed']                  = '1';
		$admin->toggle_forum_notice();
		$notices = bbp_admin()->notices;
		$notice  = end( $notices );
		$this->assertStringContainsString( 'problem closing', $notice );
		$this->assertStringContainsString( 'notice error', $notice );

		$filter = function ( $message, $id, $notice, $failed ) use ( $forum_id ) {
			$this->assertSame( $forum_id, $id );
			$this->assertSame( 'closed', $notice );
			$this->assertTrue( $failed );
			return 'Filtered forum notice';
		};
		add_filter( 'bbp_toggle_forum_notice_admin', $filter, 10, 4 );

		try {
			$admin->toggle_forum_notice();
			$notices = bbp_admin()->notices;
			$this->assertStringContainsString( 'Filtered forum notice', end( $notices ) );
		} finally {
			remove_filter( 'bbp_toggle_forum_notice_admin', $filter, 10 );
		}
	}

	/**
	 * @covers BBP_Forums_Admin::toggle_forum_notice
	 * @ticket 3706
	 */
	public function test_toggle_forum_notice_ignores_invalid_requests() {
		$admin    = $this->get_admin_without_hooks();
		$forum_id = $this->factory->forum->create();
		$_SERVER['REQUEST_METHOD'] = 'GET';
		bbp_admin()->notices = array();

		foreach (
			array(
				array(),
				array( 'forum_id' => $forum_id, 'bbp_forum_toggle_notice' => 'not-allowed' ),
				array( 'forum_id' => 999999, 'bbp_forum_toggle_notice' => 'opened' ),
			)
			as $_GET
		) {
			$this->assertNull( $admin->toggle_forum_notice() );
		}

		$this->assertSame( array(), bbp_admin()->notices );
	}

	/**
	 * @covers BBP_Forums_Admin::column_headers
	 * @ticket 3706
	 */
	public function test_column_headers_honor_moderator_feature_and_filter() {
		$admin = $this->get_admin_without_hooks();
		update_option( '_bbp_allow_forum_mods', 1 );
		$columns = $admin->column_headers( array( 'ignored' => 'Ignored' ) );
		$this->assertSame( array( 'cb', 'title', 'bbp_forum_topic_count', 'bbp_forum_reply_count', 'bbp_forum_mods', 'author', 'bbp_forum_created', 'bbp_forum_freshness' ), array_keys( $columns ) );

		update_option( '_bbp_allow_forum_mods', 0 );
		$filter = function ( $headers ) {
			$headers['extension'] = 'Extension';
			return $headers;
		};
		add_filter( 'bbp_admin_forums_column_headers', $filter );

		try {
			$columns = $admin->column_headers( array() );
			$this->assertArrayNotHasKey( 'bbp_forum_mods', $columns );
			$this->assertSame( 'Extension', $columns['extension'] );
		} finally {
			remove_filter( 'bbp_admin_forums_column_headers', $filter );
		}
	}

	/**
	 * @covers BBP_Forums_Admin::column_data
	 * @ticket 3706
	 */
	public function test_column_data_outputs_counts_dates_freshness_and_extension_columns() {
		$admin    = $this->get_admin_without_hooks();
		$forum_id = $this->factory->forum->create();
		$post     = get_post( $forum_id );
		$GLOBALS['post'] = $post;
		$GLOBALS['current_screen'] = WP_Screen::get( 'edit-forum' );
		update_post_meta( $forum_id, '_bbp_total_topic_count', 3 );
		update_post_meta( $forum_id, '_bbp_total_reply_count', 4 );

		$this->assertSame( '3', $this->capture_callback( array( $admin, 'column_data' ), 'bbp_forum_topic_count', $forum_id ) );
		$this->assertSame( '4', $this->capture_callback( array( $admin, 'column_data' ), 'bbp_forum_reply_count', $forum_id ) );
		$this->assertSame( get_the_date() . ' <br /> ' . esc_attr( get_the_time() ), $this->capture_callback( array( $admin, 'column_data' ), 'bbp_forum_created', $forum_id ) );
		$this->assertSame( 'No Topics', $this->capture_callback( array( $admin, 'column_data' ), 'bbp_forum_freshness', $forum_id ) );

		$moderator_id = $this->factory->user->create( array( 'user_login' => 'forum-moderator' ) );
		bbp_add_moderator( $forum_id, $moderator_id );
		$this->assertSame( 'forum-moderator', $this->capture_callback( array( $admin, 'column_data' ), 'bbp_forum_mods', $forum_id ) );

		$freshness = function ( $active_time, $id ) use ( $forum_id ) {
			return ( $forum_id === $id ) ? 'Deterministic freshness' : $active_time;
		};
		add_filter( 'bbp_get_forum_last_active', $freshness, 10, 2 );

		try {
			$this->assertSame( 'Deterministic freshness', $this->capture_callback( array( $admin, 'column_data' ), 'bbp_forum_freshness', $forum_id ) );
		} finally {
			remove_filter( 'bbp_get_forum_last_active', $freshness, 10 );
		}

		$observer = function ( $column, $id ) use ( $forum_id ) {
			if ( ( 'extension' === $column ) && ( $forum_id === $id ) ) {
				echo 'Extension output';
			}
		};
		add_action( 'bbp_admin_forums_column_data', $observer, 10, 2 );

		try {
			$this->assertSame( 'Extension output', $this->capture_callback( array( $admin, 'column_data' ), 'extension', $forum_id ) );
		} finally {
			remove_action( 'bbp_admin_forums_column_data', $observer, 10 );
		}
	}

	/**
	 * @covers BBP_Forums_Admin::row_actions
	 * @ticket 3706
	 */
	public function test_row_actions_remove_quick_edit_toggle_state_escape_content_and_sort_actions() {
		$admin    = $this->get_admin_without_hooks();
		$forum_id = $this->factory->forum->create( array( 'post_content' => '<em>Summary & more</em>' ) );
		$forum    = get_post( $forum_id );
		$this->create_keymaster();
		$actions = array(
			'view'               => 'View',
			'custom'             => 'Custom',
			'inline hide-if-no-js' => 'Quick Edit',
			'edit'               => 'Edit',
		);

		$output = $this->capture_callback( function () use ( $admin, $actions, $forum, &$result ) {
			$result = $admin->row_actions( $actions, $forum );
		} );

		$this->assertSame( array( 'edit', 'closed', 'view', 'custom' ), array_keys( $result ) );
		$this->assertStringContainsString( '>Close</a>', $result['closed'] );
		$this->assertStringContainsString( 'Summary &amp; more', $output );
		$this->assertStringNotContainsString( '<em>', $output );

		bbp_close_forum( $forum_id );
		$forum = get_post( $forum_id );
		$this->capture_callback( function () use ( $admin, $forum, &$result ) {
			$result = $admin->row_actions( array(), $forum );
		} );
		$this->assertStringContainsString( '>Open</a>', $result['closed'] );
	}

	/**
	 * @covers BBP_Forums_Admin::row_actions
	 * @ticket 3706
	 */
	public function test_row_actions_hide_content_without_access_or_for_password_protected_forums() {
		$admin    = $this->get_admin_without_hooks();
		$forum_id = $this->factory->forum->create( array( 'post_content' => 'Private summary' ) );
		$forum    = get_post( $forum_id );
		$this->set_current_user( 0 );
		$this->assertFalse( current_user_can( 'read_forum', $forum_id ) );

		$output = $this->capture_callback( array( $admin, 'row_actions' ), array( 'inline hide-if-no-js' => 'Quick Edit' ), $forum );
		$this->assertSame( '', $output );

		$this->create_keymaster();
		wp_update_post( array( 'ID' => $forum_id, 'post_password' => 'secret' ) );
		$forum  = get_post( $forum_id );
		$output = $this->capture_callback( array( $admin, 'row_actions' ), array(), $forum );
		$this->assertSame( '', $output );
	}

	/**
	 * @covers BBP_Forums_Admin::row_actions
	 * @ticket 3706
	 */
	public function test_row_action_sort_order_is_filterable() {
		$admin = $this->get_admin_without_hooks();
		$filter = function () {
			return array( 'custom', 'edit' );
		};
		add_filter( 'bbp_admin_forum_row_action_sort_order', $filter );

		try {
			$actions = $this->invoke_private_method(
				$admin,
				'sort_row_actions',
				array( array( 'view' => 'View', 'edit' => 'Edit', 'custom' => 'Custom' ) )
			);
			$this->assertSame( array( 'custom', 'edit', 'view' ), array_keys( $actions ) );
		} finally {
			remove_filter( 'bbp_admin_forum_row_action_sort_order', $filter );
		}
	}

	/**
	 * @covers BBP_Forums_Admin::updated_messages
	 * @ticket 3706
	 */
	public function test_updated_messages_populate_forum_messages_and_preview_links() {
		$admin       = $this->get_admin_without_hooks();
		$forum_id    = $this->factory->forum->create( array( 'post_title' => 'Message Forum' ) );
		$had_post_id = array_key_exists( 'post_ID', $GLOBALS );
		$old_post_id = $had_post_id ? $GLOBALS['post_ID'] : null;
		$GLOBALS['post_ID'] = $forum_id;
		$GLOBALS['post'] = get_post( $forum_id );
		$_GET = array();

		try {
			$messages = $admin->updated_messages( array( 'post' => array( 'Existing' ) ) );
			$forum    = $messages[ bbp_get_forum_post_type() ];

			$this->assertSame( '', $forum[0] );
			$this->assertStringContainsString( 'Forum updated.', $forum[1] );
			$this->assertStringContainsString( bbp_get_forum_permalink( $forum_id ), $forum[1] );
			$this->assertFalse( $forum[5] );
			$this->assertStringContainsString( 'Forum created.', $forum[6] );
			$this->assertStringContainsString( 'preview=true', $forum[8] );
			$this->assertStringContainsString( date_i18n( __( 'M j, Y @ G:i', 'bbpress' ), strtotime( get_post_field( 'post_date', $forum_id ) ) ), $forum[9] );
			$this->assertStringContainsString( 'Forum draft updated.', $forum[10] );

			$revision_id = wp_save_post_revision( $forum_id );
			$_GET['revision'] = $revision_id;
			$messages = $admin->updated_messages( array() );
			$this->assertStringContainsString( wp_post_revision_title( $revision_id, false ), $messages[ bbp_get_forum_post_type() ][5] );
		} finally {
			if ( $had_post_id ) {
				$GLOBALS['post_ID'] = $old_post_id;
			} else {
				unset( $GLOBALS['post_ID'] );
			}
		}
	}

	/**
	 * @covers ::bbp_admin_forums
	 * @ticket 3706
	 */
	public function test_admin_forums_loads_only_on_forum_admin_screen() {
		$old_forums = isset( bbp_admin()->forums ) ? bbp_admin()->forums : null;
		$sentinel   = new stdClass();
		bbp_admin()->forums = $sentinel;

		try {
			$GLOBALS['current_screen'] = WP_Screen::get( 'edit-post' );
			bbp_admin_forums( get_current_screen() );
			$this->assertSame( $sentinel, bbp_admin()->forums );

			$GLOBALS['current_screen'] = WP_Screen::get( 'edit-forum' );
			bbp_admin_forums( get_current_screen() );
			$this->assertInstanceOf( 'BBP_Forums_Admin', bbp_admin()->forums );
		} finally {
			if ( bbp_admin()->forums instanceof BBP_Forums_Admin ) {
				$this->remove_admin_hooks( bbp_admin()->forums );
			}

			bbp_admin()->forums = $old_forums;
		}
	}
}
