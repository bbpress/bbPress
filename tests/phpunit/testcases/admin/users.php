<?php

/**
 * Tests for user administration.
 *
 * @group admin
 * @group users
 */
class BBP_Tests_Admin_Users extends BBP_UnitTestCase {

	private $request;
	private $super_admins = array();

	public function setUp(): void {
		parent::setUp();

		$this->request = $_REQUEST;

		require_once BBP_PLUGIN_DIR . 'includes/admin/users.php';
	}

	public function tearDown(): void {
		$_REQUEST = $this->request;
		foreach ( $this->super_admins as $user_id ) {
			revoke_super_admin( $user_id );
		}
		$this->super_admins = array();

		parent::tearDown();
	}

	private function get_admin_without_hooks() {
		$reflection = new ReflectionClass( 'BBP_Users_Admin' );

		return $reflection->newInstanceWithoutConstructor();
	}

	private function remove_hooks( $admin ) {
		remove_action( 'edit_user_profile', array( $admin, 'secondary_role_display' ) );
		remove_action( 'restrict_manage_users', array( $admin, 'user_role_bulk_dropdown' ), 10 );
		remove_filter( 'manage_users_columns', array( $admin, 'user_role_column' ), 10 );
		remove_filter( 'manage_users_custom_column', array( $admin, 'user_role_row' ), 10 );
		remove_filter( 'get_role_list', array( $admin, 'user_role_list_filter' ), 10 );
		remove_action( 'load-users.php', array( $admin, 'user_role_bulk_change' ), 10 );
		remove_action( 'user_row_actions', array( $admin, 'user_row_actions' ), 10 );
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

	private function create_administrator() {
		$user_id = $this->factory->user->create( array( 'role' => 'administrator' ) );
		if ( is_multisite() ) {
			grant_super_admin( $user_id );
			$this->super_admins[] = $user_id;
		}
		$this->set_current_user( $user_id );

		return $user_id;
	}

	/**
	 * @covers BBP_Users_Admin::__construct
	 * @ticket 3706
	 */
	public function test_constructor_registers_user_admin_hooks() {
		$loaded  = null;
		$observe = function ( $admin ) use ( &$loaded ) {
			$loaded = $admin;
		};
		add_action( 'bbp_admin_users_loaded', $observe );
		$admin = new BBP_Users_Admin();

		try {
			$this->assertSame( $admin, $loaded );
			$this->assertSame( 10, has_action( 'edit_user_profile', array( $admin, 'secondary_role_display' ) ) );
			$this->assertSame( 10, has_action( 'restrict_manage_users', array( $admin, 'user_role_bulk_dropdown' ) ) );
			$this->assertSame( 10, has_filter( 'manage_users_columns', array( $admin, 'user_role_column' ) ) );
			$this->assertSame( 10, has_filter( 'manage_users_custom_column', array( $admin, 'user_role_row' ) ) );
			$this->assertSame( 10, has_filter( 'get_role_list', array( $admin, 'user_role_list_filter' ) ) );
			$this->assertSame( 10, has_action( 'load-users.php', array( $admin, 'user_role_bulk_change' ) ) );
			$this->assertSame( 10, has_action( 'user_row_actions', array( $admin, 'user_row_actions' ) ) );
		} finally {
			remove_action( 'bbp_admin_users_loaded', $observe );
			$this->remove_hooks( $admin );
		}
	}

	/**
	 * @covers BBP_Users_Admin::__construct
	 * @ticket 3706
	 */
	public function test_constructor_does_not_register_hooks_in_network_admin() {
		$had_screen = array_key_exists( 'current_screen', $GLOBALS );
		$screen     = $had_screen ? $GLOBALS['current_screen'] : null;
		$loaded     = null;
		$observe    = function ( $admin ) use ( &$loaded ) {
			$loaded = $admin;
		};
		add_action( 'bbp_admin_users_loaded', $observe );
		set_current_screen( 'users-network' );
		$admin = new BBP_Users_Admin();

		try {
			$this->assertNull( $loaded );
			$this->assertFalse( has_action( 'edit_user_profile', array( $admin, 'secondary_role_display' ) ) );
			$this->assertFalse( has_action( 'restrict_manage_users', array( $admin, 'user_role_bulk_dropdown' ) ) );
			$this->assertFalse( has_action( 'load-users.php', array( $admin, 'user_role_bulk_change' ) ) );
		} finally {
			remove_action( 'bbp_admin_users_loaded', $observe );
			if ( $had_screen ) {
				$GLOBALS['current_screen'] = $screen;
			} else {
				unset( $GLOBALS['current_screen'] );
			}
		}
	}

	/**
	 * @covers BBP_Users_Admin::secondary_role_display
	 * @ticket 3706
	 */
	public function test_secondary_role_display_requires_permission_and_selects_current_role() {
		$target_id = $this->factory->user->create( array( 'role' => 'subscriber' ) );
		bbp_set_user_role( $target_id, bbp_get_participant_role() );
		$target = get_userdata( $target_id );

		$subscriber_id = $this->factory->user->create( array( 'role' => 'subscriber' ) );
		$this->set_current_user( $subscriber_id );
		$this->assertSame( '', $this->capture( array( 'BBP_Users_Admin', 'secondary_role_display' ), $target ) );

		$this->create_administrator();
		$output = $this->capture( array( 'BBP_Users_Admin', 'secondary_role_display' ), $target );
		$this->assertStringContainsString( '<h2>Forums</h2>', $output );
		$this->assertStringContainsString( 'id="bbp-forums-role"', $output );
		$this->assertStringContainsString( "selected='selected' value=\"" . bbp_get_participant_role() . '"', $output );

		get_userdata( $target_id )->remove_role( bbp_get_participant_role() );
		$output = $this->capture( array( 'BBP_Users_Admin', 'secondary_role_display' ), get_userdata( $target_id ) );
		$this->assertStringContainsString( '<option value="" selected="selected">', $output );
	}

	/**
	 * @ticket BBP3262
	 * @covers BBP_Users_Admin::secondary_role_display
	 */
	public function test_secondary_role_display_keeps_unassignable_current_role_selected() {
		if ( is_multisite() ) {
			$this->markTestSkipped( 'The site administrator cannot edit this profile on multisite.' );
		}

		$target_id = $this->factory->user->create( array( 'role' => 'subscriber' ) );
		$hide_role = function ( $roles ) {
			unset( $roles[ bbp_get_moderator_role() ] );
			return $roles;
		};

		bbp_set_user_role( $target_id, bbp_get_moderator_role() );
		$this->create_administrator();
		add_filter( 'bbp_get_user_editable_forum_roles', $hide_role );

		try {
			$output = $this->capture( array( 'BBP_Users_Admin', 'secondary_role_display' ), get_userdata( $target_id ) );
			$this->assertStringContainsString( 'value="' . bbp_get_moderator_role() . '" selected="selected"', $output );
		} finally {
			remove_filter( 'bbp_get_user_editable_forum_roles', $hide_role );
		}
	}

	/**
	 * @covers BBP_Users_Admin::secondary_role_display
	 * @covers BBP_Users_Admin::user_role_bulk_dropdown
	 * @ticket 3706
	 */
	public function test_multisite_site_administrator_permissions() {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'Requires multisite.' );
		}

		$admin_id  = $this->factory->user->create( array( 'role' => 'administrator' ) );
		$target_id = $this->factory->user->create( array( 'role' => 'subscriber' ) );
		$this->set_current_user( $admin_id );

		$this->assertFalse( is_super_admin( $admin_id ) );
		$this->assertSame( '', $this->capture( array( 'BBP_Users_Admin', 'secondary_role_display' ), get_userdata( $target_id ) ) );
		$this->assertStringContainsString( 'name="bbp-new-role"', $this->capture( array( 'BBP_Users_Admin', 'user_role_bulk_dropdown' ), 'top' ) );
	}

	/**
	 * @covers BBP_Users_Admin::user_role_bulk_dropdown
	 * @ticket 3706
	 */
	public function test_bulk_role_dropdown_requires_permission_and_uses_location_specific_controls() {
		$this->set_current_user( 0 );
		$this->assertSame( '', $this->capture( array( 'BBP_Users_Admin', 'user_role_bulk_dropdown' ), 'top' ) );

		$this->create_administrator();
		$top    = $this->capture( array( 'BBP_Users_Admin', 'user_role_bulk_dropdown' ), 'top' );
		$bottom = $this->capture( array( 'BBP_Users_Admin', 'user_role_bulk_dropdown' ), 'bottom' );
		$this->assertStringContainsString( 'name="bbp-new-role"', $top );
		$this->assertStringContainsString( 'id="bbp-change-role"', $top );
		$this->assertStringContainsString( 'name="bbp-new-role2"', $bottom );
		$this->assertStringContainsString( 'id="bbp-change-role2"', $bottom );
		$this->assertStringContainsString( 'name="bbp-bulk-users-nonce"', $top );
		$this->assertStringContainsString( 'value="' . bbp_get_participant_role() . '"', $top );
	}

	/**
	 * @covers BBP_Users_Admin::user_role_bulk_change
	 * @ticket 3706
	 */
	public function test_bulk_role_change_ignores_invalid_requests_and_payloads() {
		$admin = $this->get_admin_without_hooks();
		$this->create_administrator();
		$target_id = $this->factory->user->create( array( 'role' => 'subscriber' ) );
		bbp_set_user_role( $target_id, bbp_get_participant_role() );

		$_REQUEST = array();
		$admin->user_role_bulk_change();
		$this->assertSame( bbp_get_participant_role(), bbp_get_user_role( $target_id ) );
		$_REQUEST = array( 'users' => array( $target_id ), 'bbp-new-role' => bbp_get_spectator_role() );
		$admin->user_role_bulk_change();
		$this->assertSame( bbp_get_participant_role(), bbp_get_user_role( $target_id ) );
		$_REQUEST = array(
			'users'                => array( $target_id ),
			'bbp-new-role'         => 'not-a-role',
			'bbp-change-role'      => 'Change',
			'bbp-bulk-users-nonce' => wp_create_nonce( 'bbp-bulk-users' ),
		);
		$admin->user_role_bulk_change();
		$this->assertSame( bbp_get_participant_role(), bbp_get_user_role( $target_id ) );
		$_REQUEST['bbp-new-role'] = array( bbp_get_spectator_role() );
		$admin->user_role_bulk_change();
		$this->assertSame( bbp_get_participant_role(), bbp_get_user_role( $target_id ) );
	}

	/**
	 * @covers BBP_Users_Admin::user_role_bulk_change
	 * @ticket 3706
	 */
	public function test_bulk_role_change_checks_nonce_and_permission() {
		$admin     = $this->get_admin_without_hooks();
		$target_id = $this->factory->user->create( array( 'role' => 'subscriber' ) );
		bbp_set_user_role( $target_id, bbp_get_participant_role() );
		$this->create_administrator();
		$_REQUEST = array(
			'users'                => array( $target_id ),
			'bbp-new-role'         => bbp_get_spectator_role(),
			'bbp-change-role'      => 'Change',
			'bbp-bulk-users-nonce' => 'invalid',
		);
		try {
			$admin->user_role_bulk_change();
			$this->fail( 'An invalid bulk-role nonce should be rejected.' );
		} catch ( WPDieException $error ) {
			$this->assertInstanceOf( 'WPDieException', $error );
		}
		$this->assertSame( bbp_get_participant_role(), bbp_get_user_role( $target_id ) );

		$user_id = $this->factory->user->create( array( 'role' => 'subscriber' ) );
		$this->set_current_user( $user_id );
		$_REQUEST = array(
			'users'                => array( $target_id ),
			'bbp-new-role'         => bbp_get_spectator_role(),
			'bbp-change-role'      => 'Change',
			'bbp-bulk-users-nonce' => wp_create_nonce( 'bbp-bulk-users' ),
		);
		$admin->user_role_bulk_change();
		$this->assertSame( bbp_get_participant_role(), bbp_get_user_role( $target_id ) );
	}

	/**
	 * @covers BBP_Users_Admin::user_role_bulk_change
	 * @ticket 3706
	 */
	public function test_bulk_role_change_uses_bottom_role_and_continues_after_skipped_users() {
		$admin      = $this->get_admin_without_hooks();
		$admin_id   = $this->create_administrator();
		$denied_id  = $this->factory->user->create( array( 'role' => 'subscriber' ) );
		$allowed_id = $this->factory->user->create( array( 'role' => 'subscriber' ) );
		bbp_set_user_role( $admin_id, bbp_get_participant_role() );
		bbp_set_user_role( $denied_id, bbp_get_participant_role() );
		bbp_set_user_role( $allowed_id, bbp_get_participant_role() );
		$deny = function ( $roles, $user_id ) use ( $denied_id ) {
			return ( $denied_id === (int) $user_id ) ? array() : $roles;
		};
		add_filter( 'bbp_get_user_editable_forum_roles', $deny, 10, 2 );
		$_REQUEST = array(
			'users'                => array( $admin_id, $denied_id, $allowed_id ),
			'bbp-new-role'         => bbp_get_spectator_role(),
			'bbp-change-role'      => 'Change',
			'bbp-new-role2'        => bbp_get_blocked_role(),
			'bbp-change-role2'     => 'Change',
			'bbp-bulk-users-nonce' => wp_create_nonce( 'bbp-bulk-users' ),
		);
		try {
			$admin->user_role_bulk_change();
		} finally {
			remove_filter( 'bbp_get_user_editable_forum_roles', $deny, 10 );
		}

		$this->assertSame( bbp_get_participant_role(), bbp_get_user_role( $admin_id ) );
		$this->assertSame( bbp_get_participant_role(), bbp_get_user_role( $denied_id ) );
		$this->assertSame( bbp_get_blocked_role(), bbp_get_user_role( $allowed_id ) );
	}

	/**
	 * @covers BBP_Users_Admin::user_role_bulk_change
	 * @ticket 3706
	 */
	public function test_bulk_role_change_continues_after_promotion_and_keymaster_guards() {
		$admin       = $this->get_admin_without_hooks();
		$admin_id    = $this->factory->user->create( array( 'role' => 'administrator' ) );
		$denied_id   = $this->factory->user->create( array( 'role' => 'subscriber' ) );
		$keymaster_id = $this->factory->user->create( array( 'role' => 'subscriber' ) );
		$allowed_id  = $this->factory->user->create( array( 'role' => 'subscriber' ) );
		bbp_set_user_role( $admin_id, bbp_get_participant_role() );
		bbp_set_user_role( $denied_id, bbp_get_participant_role() );
		bbp_set_user_role( $keymaster_id, bbp_get_keymaster_role() );
		bbp_set_user_role( $allowed_id, bbp_get_participant_role() );
		get_userdata( $admin_id )->add_cap( 'manage_options', false );
		$this->set_current_user( $admin_id );
		$deny = function ( $caps, $cap, $user_id, $args ) use ( $denied_id ) {
			if ( ( 'promote_user' === $cap ) && ! empty( $args[0] ) && ( $denied_id === (int) $args[0] ) ) {
				return array( 'do_not_allow' );
			}

			return $caps;
		};
		add_filter( 'map_meta_cap', $deny, 10, 4 );
		$_REQUEST = array(
			'users'                => array( $denied_id, $keymaster_id, $allowed_id ),
			'bbp-new-role'         => bbp_get_spectator_role(),
			'bbp-change-role'      => 'Change',
			'bbp-bulk-users-nonce' => wp_create_nonce( 'bbp-bulk-users' ),
		);
		try {
			$admin->user_role_bulk_change();
		} finally {
			remove_filter( 'map_meta_cap', $deny, 10 );
		}

		$this->assertSame( bbp_get_participant_role(), bbp_get_user_role( $denied_id ) );
		$this->assertSame( bbp_get_keymaster_role(), bbp_get_user_role( $keymaster_id ) );
		$this->assertSame( bbp_get_spectator_role(), bbp_get_user_role( $allowed_id ) );
	}

	/**
	 * @covers BBP_Users_Admin::user_row_actions
	 * @ticket 3706
	 */
	public function test_user_row_actions_prepends_profile_link() {
		$user_id = $this->factory->user->create();
		$actions = $this->get_admin_without_hooks()->user_row_actions(
			array(
				'edit'   => 'Edit',
				'delete' => 'Delete',
			),
			get_userdata( $user_id )
		);

		$this->assertSame( array( 'view', 'edit', 'delete' ), array_keys( $actions ) );
		$this->assertStringContainsString( bbp_get_user_profile_url( $user_id ), $actions['view'] );
		$this->assertStringContainsString( 'bbp-user-profile-link', $actions['view'] );

		$actions = $this->get_admin_without_hooks()->user_row_actions(
			array(
				'edit'   => 'Edit',
				'view'   => 'Old view',
				'delete' => 'Delete',
			),
			get_userdata( $user_id )
		);
		$this->assertSame( array( 'edit', 'view', 'delete' ), array_keys( $actions ) );
		$this->assertStringNotContainsString( 'Old view', $actions['view'] );
	}

	/**
	 * @covers BBP_Users_Admin::user_role_column
	 * @covers BBP_Users_Admin::user_role_row
	 * @ticket 3706
	 */
	public function test_user_role_column_and_row_display_forum_roles() {
		$columns = BBP_Users_Admin::user_role_column(
			array(
				'username' => 'Username',
				'name'     => 'Name',
				'role'     => 'Role',
				'posts'    => 'Posts',
			)
		);
		$this->assertSame( array( 'username', 'name', 'role', 'bbp_user_role', 'posts' ), array_keys( $columns ) );
		$this->assertSame( 'Site Role', $columns['role'] );
		$this->assertSame( 'Forum Role', $columns['bbp_user_role'] );

		$user_id = $this->factory->user->create();
		get_userdata( $user_id )->remove_role( bbp_get_participant_role() );
		$this->assertSame( 'unchanged', BBP_Users_Admin::user_role_row( 'unchanged', 'email', $user_id ) );
		$this->assertFalse( BBP_Users_Admin::user_role_row( 'unchanged', 'bbp_user_role', $user_id ) );
		bbp_set_user_role( $user_id, bbp_get_participant_role() );
		$this->assertSame( 'Participant', BBP_Users_Admin::user_role_row( '', 'bbp_user_role', $user_id ) );
	}

	/**
	 * @covers BBP_Users_Admin::user_role_list_filter
	 * @ticket 3706
	 */
	public function test_user_role_list_filter_removes_only_the_forum_role() {
		$user_id = $this->factory->user->create();
		$user    = get_userdata( $user_id );
		$user->remove_role( bbp_get_participant_role() );
		$roles   = array(
			'subscriber'               => 'Subscriber',
			bbp_get_participant_role() => 'Participant',
		);

		$this->assertSame( $roles, BBP_Users_Admin::user_role_list_filter( $roles, $user ) );
		bbp_set_user_role( $user_id, bbp_get_participant_role() );
		$this->assertSame( array( 'subscriber' => 'Subscriber' ), BBP_Users_Admin::user_role_list_filter( $roles, $user ) );
	}
}
