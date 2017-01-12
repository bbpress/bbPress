<?php

class BBP_UnitTestCase extends WP_UnitTestCase {

	protected static $cached_SERVER_NAME = null;

	/**
	 * Fake WP mail globals, to avoid errors
	 */
	public static function setUpBeforeClass() {
		add_filter( 'wp_mail',      array( 'BBP_UnitTestCase', 'setUp_wp_mail'    ) );
		add_filter( 'wp_mail_from', array( 'BBP_UnitTestCase', 'tearDown_wp_mail' ) );
	}

	public function setUp() {
		parent::setUp();

		$this->factory = new BBP_UnitTest_Factory;

		if ( class_exists( 'BP_UnitTest_Factory' ) ) {
			$this->bp_factory = new BP_UnitTest_Factory();
		}

		global $wpdb;

		// Our default is ugly permalinks, so reset when needed.
		global $wp_rewrite;
		if ( $wp_rewrite->permalink_structure ) {
			$this->set_permalink_structure();
		}
	}

	public function tearDown() {
		global $wpdb;

		parent::tearDown();

		if ( is_multisite() ) {
			foreach ( $wpdb->get_col( "SELECT blog_id FROM $wpdb->blogs WHERE blog_id != 1" ) as $blog_id ) {
				wpmu_delete_blog( $blog_id, true );
			}
		}

		foreach ( $wpdb->get_col( "SELECT ID FROM $wpdb->users WHERE ID != 1" ) as $user_id ) {
			if ( is_multisite() ) {
				wpmu_delete_user( $user_id );
			} else {
				wp_delete_user( $user_id );
			}
		}

		$this->commit_transaction();
	}

	function clean_up_global_scope() {
		parent::clean_up_global_scope();
	}

	function assertPreConditions() {
		parent::assertPreConditions();
	}

	function go_to( $url ) {

		parent::go_to( $url );

		do_action( 'bbp_init' );
	}

	/**
	 * WP's core tests use wp_set_current_user() to change the current
	 * user during tests. BP caches the current user differently, so we
	 * have to do a bit more work to change it
	 */
	public static function set_current_user( $user_id ) {
		wp_set_current_user( $user_id );
	}

	/**
	 * We can't use grant_super_admin() because we will need to modify
	 * the list more than once, and grant_super_admin() can only be run
	 * once because of its global check
	 */
	public function grant_super_admin( $user_id ) {
		global $super_admins;
		if ( ! is_multisite() ) {
			return;
		}

		$user = get_userdata( $user_id );
		$super_admins[] = $user->user_login;
	}

	/**
	 * We assume that the global can be wiped out
	 *
	 * @see grant_super_admin()
	 */
	public function restore_admins() {
		unset( $GLOBALS['super_admins'] );
	}

	/**
	 * Set up globals necessary to avoid errors when using wp_mail()
	 */
	public static function setUp_wp_mail( $args ) {
		if ( isset( $_SERVER['SERVER_NAME'] ) ) {
			self::$cached_SERVER_NAME = $_SERVER['SERVER_NAME'];
		}

		$_SERVER['SERVER_NAME'] = 'example.com';

		// passthrough
		return $args;
	}

	/**
	 * Tear down globals set up in setUp_wp_mail()
	 */
	public static function tearDown_wp_mail( $args ) {
		if ( ! empty( self::$cached_SERVER_NAME ) ) {
			$_SERVER['SERVER_NAME'] = self::$cached_SERVER_NAME;
			self::$cached_SERVER_NAME = '';
		} else {
			unset( $_SERVER['SERVER_NAME'] );
		}

		// passthrough
		return $args;
	}

	/**
	 * Commit a MySQL transaction.
	 */
	public static function commit_transaction() {
		global $wpdb;
		$wpdb->query( 'COMMIT;' );
	}

	/**
	 * Utility method that resets permalinks and flushes rewrites.
	 *
	 * @since 2.6.0 bbPress (r5947)
	 *
	 * @global WP_Rewrite $wp_rewrite
	 *
	 * @uses WP_UnitTestCase::set_permalink_structure()
	 *
	 * @param string $structure Optional. Permalink structure to set. Default empty.
	 */
	public function set_permalink_structure( $structure = '' ) {

		// Use WP 4.4+'s version if it exists.
		if ( method_exists( 'parent', 'set_permalink_structure' ) ) {
			parent::set_permalink_structure( $structure );
		} else {
			global $wp_rewrite;

			$wp_rewrite->init();
			$wp_rewrite->set_permalink_structure( $structure );
			$wp_rewrite->flush_rules();
		}
	}
}
