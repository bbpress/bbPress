<?php

/**
 * Tests for the administration menus.
 *
 * @group admin
 */
class BBP_Tests_Admin_Menus extends BBP_UnitTestCase {

	private $current_user;
	private $globals = array();

	public function setUp(): void {
		parent::setUp();

		if ( ! function_exists( 'bbp_admin' ) ) {
			require_once BBP_PLUGIN_DIR . 'includes/admin/actions.php';
		}
		bbp_admin();

		$this->current_user = get_current_user_id();
		foreach ( array( 'submenu', 'admin_page_hooks', '_registered_pages', '_parent_pages' ) as $global ) {
			$this->globals[ $global ] = array_key_exists( $global, $GLOBALS )
				? $GLOBALS[ $global ]
				: null;
		}
	}

	public function tearDown(): void {
		$this->set_current_user( $this->current_user );
		foreach ( $this->globals as $global => $value ) {
			if ( is_null( $value ) ) {
				unset( $GLOBALS[ $global ] );
			} else {
				$GLOBALS[ $global ] = $value;
			}
		}

		parent::tearDown();
	}

	/**
	 * @covers BBP_Admin::admin_menus
	 * @ticket 3480
	 */
	public function test_about_and_credits_pages_have_distinct_menu_titles() {
		$user_id = $this->factory->user->create( array( 'role' => 'administrator' ) );
		$user    = get_userdata( $user_id );
		$user->add_cap( bbp_admin()->minimum_capability );
		$this->set_current_user( $user_id );

		$GLOBALS['submenu'] = array();
		bbp_admin()->admin_menus();

		$about = wp_list_filter( $GLOBALS['submenu']['index.php'], array( 2 => 'bbp-about' ) );
		$about = reset( $about );
		$this->assertSame( 'Welcome to bbPress', $about[0] );
		$this->assertSame( 'Welcome to bbPress', $about[3] );

		$credits = wp_list_filter( $GLOBALS['submenu']['index.php'], array( 2 => 'bbp-credits' ) );
		$credits = reset( $credits );
		$this->assertSame( 'Credits', $credits[0] );
		$this->assertSame( 'Welcome to bbPress', $credits[3] );
	}
}
