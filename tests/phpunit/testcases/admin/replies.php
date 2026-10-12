<?php

class BBP_Tests_Admin_Replies_Redirect_Exception extends Exception {}

/**
 * Tests for reply administration.
 *
 * @group admin
 * @group replies
 */
class BBP_Tests_Admin_Replies extends BBP_UnitTestCase {

	private $get;
	private $request;
	private $request_method;
	private $request_uri;
	private $screen;
	private $had_screen;
	private $post_id;
	private $had_post_id;
	private $global_post;
	private $had_global_post;
	private $admin_notices;

	public function setUp(): void {
		parent::setUp();

		$this->get             = $_GET;
		$this->request         = $_REQUEST;
		$this->request_method  = isset( $_SERVER['REQUEST_METHOD'] ) ? $_SERVER['REQUEST_METHOD'] : null;
		$this->request_uri     = isset( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : null;
		$this->had_screen      = array_key_exists( 'current_screen', $GLOBALS );
		$this->screen          = $this->had_screen ? $GLOBALS['current_screen'] : null;
		$this->had_post_id     = array_key_exists( 'post_ID', $GLOBALS );
		$this->post_id         = $this->had_post_id ? $GLOBALS['post_ID'] : null;
		$this->had_global_post = array_key_exists( 'post', $GLOBALS );
		$this->global_post     = $this->had_global_post ? $GLOBALS['post'] : null;

		if ( ! function_exists( 'bbp_admin' ) ) {
			require_once BBP_PLUGIN_DIR . 'includes/admin/actions.php';
		}
		bbp_admin();
		if ( ! function_exists( 'bbp_admin_replies' ) ) {
			require_once BBP_PLUGIN_DIR . 'includes/admin/replies.php';
		}
		$this->admin_notices = bbp_admin()->notices;
	}

	public function tearDown(): void {
		$_GET                = $this->get;
		$_REQUEST            = $this->request;
		bbp_admin()->notices = $this->admin_notices;

		if ( is_null( $this->request_method ) ) {
			unset( $_SERVER['REQUEST_METHOD'] );
		} else {
			$_SERVER['REQUEST_METHOD'] = $this->request_method;
		}
		if ( is_null( $this->request_uri ) ) {
			unset( $_SERVER['REQUEST_URI'] );
		} else {
			$_SERVER['REQUEST_URI'] = $this->request_uri;
		}
		if ( $this->had_screen ) {
			$GLOBALS['current_screen'] = $this->screen;
		} else {
			unset( $GLOBALS['current_screen'] );
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
		$reflection = new ReflectionClass( 'BBP_Replies_Admin' );
		$admin      = $reflection->newInstanceWithoutConstructor();
		$post_type  = $reflection->getProperty( 'post_type' );
		if ( PHP_VERSION_ID < 80100 ) {
			$post_type->setAccessible( true );
		}
		$post_type->setValue( $admin, bbp_get_reply_post_type() );
		return $admin;
	}

	private function create_keymaster() {
		$user_id = $this->factory->user->create( array( 'role' => 'administrator' ) );
		bbp_set_user_role( $user_id, bbp_get_keymaster_role() );
		$this->set_current_user( $user_id );
		return $user_id;
	}

	private function capture( $callback, ...$arguments ) {
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

	private function remove_hooks( $admin ) {
		remove_filter( 'post_updated_messages', array( $admin, 'updated_messages' ) );
		remove_filter( 'bulk_actions-edit-reply', array( $admin, 'bulk_actions' ) );
		remove_filter( 'handle_bulk_actions-edit-reply', array( $admin, 'handle_bulk_actions' ), 10 );
		remove_filter( 'bulk_post_updated_messages', array( $admin, 'bulk_post_updated_messages' ), 10 );
		remove_filter( 'manage_' . bbp_get_reply_post_type() . '_posts_columns', array( $admin, 'column_headers' ) );
		remove_action( 'manage_' . bbp_get_reply_post_type() . '_posts_custom_column', array( $admin, 'column_data' ), 10 );
		remove_filter( 'post_row_actions', array( $admin, 'row_actions' ), 10 );
		remove_action( 'add_meta_boxes', array( $admin, 'attributes_metabox' ) );
		remove_action( 'add_meta_boxes', array( $admin, 'author_metabox' ) );
		remove_action( 'add_meta_boxes', array( $admin, 'comments_metabox' ) );
		remove_action( 'save_post', array( $admin, 'save_meta_boxes' ) );
		remove_filter( 'wp_insert_post_data', array( $admin, 'filter_post_data' ), 20 );
		remove_action( 'load-edit.php', array( $admin, 'toggle_reply' ) );
		remove_action( 'load-edit.php', array( $admin, 'toggle_reply_notice' ) );
		remove_filter( 'restrict_manage_posts', array( $admin, 'filter_dropdown' ) );
		remove_filter( 'bbp_request', array( $admin, 'filter_post_rows' ) );
		remove_filter( 'manage_posts_extra_tablenav', array( $admin, 'filter_empty_spam' ) );
		remove_action( 'load-edit.php', array( $admin, 'edit_help' ) );
		remove_action( 'load-post.php', array( $admin, 'new_help' ) );
		remove_action( 'load-post-new.php', array( $admin, 'new_help' ) );
	}

	/** @covers BBP_Replies_Admin::__construct @ticket 3706 */
	public function test_constructor_registers_hooks_and_loaded_action() {
		$loaded = null;
		$observe = function ( $admin ) use ( &$loaded ) { $loaded = $admin; };
		add_action( 'bbp_admin_replies_loaded', $observe );
		$admin = new BBP_Replies_Admin();
		try {
			$this->assertSame( $admin, $loaded );
			$this->assertSame( 10, has_filter( 'post_updated_messages', array( $admin, 'updated_messages' ) ) );
			$this->assertSame( 10, has_filter( 'handle_bulk_actions-edit-reply', array( $admin, 'handle_bulk_actions' ) ) );
			$this->assertSame( 20, has_filter( 'wp_insert_post_data', array( $admin, 'filter_post_data' ) ) );
			$this->assertSame( 10, has_action( 'save_post', array( $admin, 'save_meta_boxes' ) ) );
			$this->assertSame( 10, has_action( 'load-edit.php', array( $admin, 'toggle_reply' ) ) );
			$this->assertSame( 10, has_filter( 'bbp_request', array( $admin, 'filter_post_rows' ) ) );
		} finally {
			remove_action( 'bbp_admin_replies_loaded', $observe );
			$this->remove_hooks( $admin );
		}
	}

	/**
	 * @covers BBP_Replies_Admin::bulk_actions
	 * @covers BBP_Replies_Admin::bulk_post_updated_messages
	 * @covers BBP_Replies_Admin::handle_bulk_actions
	 * @ticket 3706
	 */
	public function test_bulk_actions_labels_counts_and_state_changes() {
		$admin = $this->get_admin_without_hooks();
		$old_status = get_query_var( 'post_status' );
		$this->set_current_user( 0 );
		$this->assertSame( array(), $admin->bulk_actions( array() ) );
		$this->create_keymaster();
		try {
			set_query_var( 'post_status', bbp_get_spam_status_id() );
			$this->assertSame( 'Unspam', $admin->bulk_actions( array() )['unspam'] );
			set_query_var( 'post_status', bbp_get_public_status_id() );
			$this->assertSame( 'Spam', $admin->bulk_actions( array() )['spam'] );
		} finally {
			set_query_var( 'post_status', $old_status );
		}
		$messages = $admin->bulk_post_updated_messages( array(), array( 'updated' => 2, 'locked' => 1 ) );
		$this->assertSame( '%s replies updated.', $messages['reply']['updated'] );
		$this->assertSame( '1 reply not updated, somebody is editing it.', $messages['reply']['locked'] );

		$forum_id = $this->factory->forum->create();
		$topic_id = $this->factory->topic->create( array( 'post_parent' => $forum_id ) );
		$reply_id = $this->factory->reply->create( array( 'post_parent' => $topic_id ) );
		$sendback = $admin->handle_bulk_actions( 'edit.php?post_type=reply&spam=1', 'spam', array( $reply_id ) );
		$this->assertTrue( bbp_is_reply_spam( $reply_id ) );
		$this->assertStringContainsString( 'updated=1', $sendback );
		$admin->handle_bulk_actions( 'edit.php?post_type=reply', 'unspam', array( $reply_id ) );
		$this->assertFalse( bbp_is_reply_spam( $reply_id ) );
	}

	/**
	 * @covers BBP_Replies_Admin::attributes_metabox
	 * @covers BBP_Replies_Admin::author_metabox
	 * @covers BBP_Replies_Admin::comments_metabox
	 * @ticket 3706
	 */
	public function test_metaboxes_respect_edit_action_and_comment_support() {
		$admin = $this->get_admin_without_hooks();
		$had_boxes = array_key_exists( 'wp_meta_boxes', $GLOBALS );
		$boxes = $had_boxes ? $GLOBALS['wp_meta_boxes'] : null;
		$had_features = array_key_exists( '_wp_post_type_features', $GLOBALS );
		$features = $had_features ? $GLOBALS['_wp_post_type_features'] : null;
		$GLOBALS['wp_meta_boxes'] = array();
		try {
			add_post_type_support( bbp_get_reply_post_type(), 'comments' );
			add_meta_box( 'commentstatusdiv', 'Discussion', '__return_null', bbp_get_reply_post_type(), 'normal' );
			add_meta_box( 'commentsdiv', 'Comments', '__return_null', bbp_get_reply_post_type(), 'normal' );
			$admin->comments_metabox();
			$this->assertNotFalse( $GLOBALS['wp_meta_boxes']['reply']['normal']['default']['commentstatusdiv'] );

			$admin->attributes_metabox();
			$admin->author_metabox();
			$this->assertArrayHasKey( 'bbp_reply_attributes', $GLOBALS['wp_meta_boxes']['reply']['side']['high'] );
			$this->assertArrayNotHasKey( 'bbp_author_metabox', $GLOBALS['wp_meta_boxes']['reply']['side']['high'] );
			$_GET['action'] = 'edit';
			$admin->author_metabox();
			$this->assertArrayHasKey( 'bbp_author_metabox', $GLOBALS['wp_meta_boxes']['reply']['side']['high'] );

			remove_post_type_support( bbp_get_reply_post_type(), 'comments' );
			$admin->comments_metabox();
			$this->assertFalse( $GLOBALS['wp_meta_boxes']['reply']['normal']['default']['commentstatusdiv'] );
			$this->assertFalse( $GLOBALS['wp_meta_boxes']['reply']['normal']['default']['commentsdiv'] );
		} finally {
			if ( $had_boxes ) {
				$GLOBALS['wp_meta_boxes'] = $boxes;
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

	/** @covers BBP_Replies_Admin::toggle_reply @ticket 3706 */
	public function test_toggle_reply_approves_unapproves_spams_and_redirects() {
		$admin = $this->get_admin_without_hooks();
		$forum_id = $this->factory->forum->create();
		$topic_id = $this->factory->topic->create( array( 'post_parent' => $forum_id ) );
		$reply_id = $this->factory->reply->create( array( 'post_parent' => $topic_id ) );
		$this->create_keymaster();
		$_SERVER['REQUEST_METHOD'] = 'GET';
		$_SERVER['REQUEST_URI'] = '/wp-admin/edit.php?post_type=reply';
		$redirects = array();
		$stop = function ( $location ) use ( &$redirects ) {
			$redirects[] = $location;
			throw new BBP_Tests_Admin_Replies_Redirect_Exception();
		};
		add_filter( 'wp_redirect', $stop );
		try {
			$cases = array(
				array( 'bbp_toggle_reply_approve', 'approve', null, 'bbp_is_reply_pending' ),
				array( 'bbp_toggle_reply_approve', 'approve', 'approved', 'bbp_is_reply_public' ),
				array( 'bbp_toggle_reply_spam', 'spam', 'spammed', 'bbp_is_reply_spam' ),
				array( 'bbp_toggle_reply_spam', 'spam', 'unspammed', 'bbp_is_reply_public' ),
			);
			foreach ( $cases as $case ) {
				$_GET = array( 'action' => $case[0], 'reply_id' => $reply_id, '_wpnonce' => wp_create_nonce( $case[1] . '-reply_' . $reply_id ) );
				$_REQUEST = $_GET;
				try {
					$admin->toggle_reply();
					$this->fail( 'The reply toggle should redirect.' );
				} catch ( BBP_Tests_Admin_Replies_Redirect_Exception $error ) {
					$this->assertInstanceOf( 'BBP_Tests_Admin_Replies_Redirect_Exception', $error );
				}
				$this->assertTrue( call_user_func( $case[3], $reply_id ) );
				$this->assertNotEmpty( $redirects );
				if ( ! is_null( $case[2] ) ) {
					$this->assertStringContainsString( 'bbp_reply_toggle_notice=' . $case[2], end( $redirects ) );
				}
			}
		} finally {
			remove_filter( 'wp_redirect', $stop );
		}
	}

	/** @covers BBP_Replies_Admin::toggle_reply @ticket 3706 */
	public function test_toggle_reply_rejects_invalid_requests_permissions_and_nonces() {
		$admin = $this->get_admin_without_hooks();
		$forum_id = $this->factory->forum->create();
		$topic_id = $this->factory->topic->create( array( 'post_parent' => $forum_id ) );
		$reply_id = $this->factory->reply->create( array( 'post_parent' => $topic_id ) );
		$_SERVER['REQUEST_METHOD'] = 'POST';
		$_GET = array( 'action' => 'bbp_toggle_reply_spam', 'reply_id' => $reply_id );
		$this->assertNull( $admin->toggle_reply() );

		$_SERVER['REQUEST_METHOD'] = 'GET';
		$_GET['action'] = 'not-allowed';
		$this->assertNull( $admin->toggle_reply() );
		$_GET = array( 'action' => 'bbp_toggle_reply_spam', 'reply_id' => 999999 );
		try {
			$admin->toggle_reply();
			$this->fail( 'A missing reply should be rejected.' );
		} catch ( WPDieException $error ) {
			$this->assertStringContainsString( 'not found', $error->getMessage() );
		}

		$user_id = $this->factory->user->create();
		bbp_set_user_role( $user_id, bbp_get_participant_role() );
		$this->set_current_user( $user_id );
		$_GET = array( 'action' => 'bbp_toggle_reply_spam', 'reply_id' => $reply_id );
		try {
			$admin->toggle_reply();
			$this->fail( 'A participant should not be able to toggle a reply.' );
		} catch ( WPDieException $error ) {
			$this->assertStringContainsString( 'permission', $error->getMessage() );
		}

		$this->create_keymaster();
		$_GET['_wpnonce'] = 'invalid';
		$_REQUEST = $_GET;
		try {
			$admin->toggle_reply();
			$this->fail( 'An invalid reply-toggle nonce should be rejected.' );
		} catch ( WPDieException $error ) {
			$this->assertStringContainsString( 'expired', $error->getMessage() );
		}
	}

	/**
	 * @covers BBP_Replies_Admin::toggle_reply_notice
	 * @covers BBP_Replies_Admin::column_headers
	 * @covers BBP_Replies_Admin::column_data
	 * @ticket 3706
	 */
	public function test_notices_and_columns_cover_expected_output() {
		$admin = $this->get_admin_without_hooks();
		$forum_id = $this->factory->forum->create( array( 'post_title' => 'Reply Forum' ) );
		$topic_id = $this->factory->topic->create( array( 'post_parent' => $forum_id, 'post_title' => 'Reply Topic' ) );
		$reply_id = $this->factory->reply->create( array( 'post_parent' => $topic_id ) );
		$_SERVER['REQUEST_METHOD'] = 'GET';
		$_GET = array( 'reply_id' => $reply_id, 'bbp_reply_toggle_notice' => 'approved' );
		bbp_admin()->notices = array();
		$admin->toggle_reply_notice();
		$this->assertStringContainsString( 'successfully approved', bbp_admin()->notices[0] );
		$this->assertSame( array( 'cb', 'title', 'bbp_reply_forum', 'bbp_reply_topic', 'bbp_reply_author', 'bbp_reply_created' ), array_keys( $admin->column_headers( array() ) ) );
		$this->assertSame( 'Reply Topic', $this->capture( array( $admin, 'column_data' ), 'bbp_reply_topic', $reply_id ) );
		$this->assertSame( 'Reply Forum', $this->capture( array( $admin, 'column_data' ), 'bbp_reply_forum', $reply_id ) );
	}

	/**
	 * @covers BBP_Replies_Admin::row_actions
	 * @covers BBP_Replies_Admin::filter_dropdown
	 * @covers BBP_Replies_Admin::updated_messages
	 * @covers ::bbp_admin_replies
	 * @ticket 3706
	 */
	public function test_list_actions_filters_messages_and_loader() {
		$admin = $this->get_admin_without_hooks();
		$forum_id = $this->factory->forum->create( array( 'post_title' => 'Selected Forum' ) );
		$topic_id = $this->factory->topic->create( array( 'post_parent' => $forum_id ) );
		$reply_id = $this->factory->reply->create( array( 'post_parent' => $topic_id ) );
		$this->create_keymaster();
		$_SERVER['REQUEST_URI'] = '/wp-admin/edit.php?post_type=reply';
		$actions = $admin->row_actions( array( 'inline hide-if-no-js' => 'Quick', 'edit' => 'Edit' ), get_post( $reply_id ) );
		$this->assertArrayNotHasKey( 'inline hide-if-no-js', $actions );
		$this->assertArrayHasKey( 'unapproved', $actions );
		$this->assertArrayHasKey( 'spam', $actions );
		$this->assertArrayHasKey( 'view', $actions );

		$_GET['bbp_forum_id'] = $forum_id;
		$this->assertStringContainsString( 'value="' . $forum_id . '" selected=', $this->capture( array( $admin, 'filter_dropdown' ) ) );
		$GLOBALS['post_ID'] = $reply_id;
		$GLOBALS['post'] = get_post( $reply_id );
		$messages = $admin->updated_messages( array() );
		$this->assertStringContainsString( 'Reply updated.', $messages['reply'][1] );
		$this->assertStringContainsString( 'preview=true', $messages['reply'][8] );

		$original = isset( bbp_admin()->replies ) ? bbp_admin()->replies : null;
		$screen = WP_Screen::get( 'edit-reply' );
		$GLOBALS['current_screen'] = $screen;
		unset( bbp_admin()->replies );
		try {
			bbp_admin_replies( $screen );
			$this->assertInstanceOf( 'BBP_Replies_Admin', bbp_admin()->replies );
		} finally {
			if ( isset( bbp_admin()->replies ) && ( bbp_admin()->replies !== $original ) ) {
				$this->remove_hooks( bbp_admin()->replies );
			}
			if ( $original ) {
				bbp_admin()->replies = $original;
			} else {
				unset( bbp_admin()->replies );
			}
		}
	}
}
