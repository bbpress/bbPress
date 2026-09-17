<?php

/**
 * Tests for the Mingle Forum converter.
 *
 * @group converters
 */
class BBP_Tests_Admin_Converters_Mingle extends BBP_UnitTestCase {

	/**
	 * @var Mingle
	 */
	protected $converter;

	public function setUp(): void {
		parent::setUp();

		bbp_setup_converter();
		require_once BBP_PLUGIN_DIR . 'includes/admin/converters/Mingle.php';

		$reflection      = new ReflectionClass( 'Mingle' );
		$this->converter = $reflection->newInstanceWithoutConstructor();

		$set_database = Closure::bind(
			function( $converter ) {
				$converter->wpdb     = bbp_db();
				$converter->max_rows = 100;
			},
			null,
			'BBP_Converter_Base'
		);
		$set_database( $this->converter );
	}

	/**
	 * @covers Mingle::setup_globals
	 * @ticket BBP3684
	 */
	public function test_password_mapping_uses_portable_wordpress_hash() {
		$this->converter->setup_globals();

		$get_field_map = Closure::bind(
			function( $converter ) {
				return $converter->field_map;
			},
			null,
			'BBP_Converter_Base'
		);
		$field_map     = $get_field_map( $this->converter );
		$password_maps = wp_filter_object_list( $field_map, array( 'to_fieldname' => '_bbp_password' ) );
		$class_maps    = wp_filter_object_list( $field_map, array( 'to_fieldname' => '_bbp_class' ) );
		$password_map  = reset( $password_maps );

		$this->assertCount( 1, $password_maps );
		$this->assertCount( 0, $class_maps );
		$this->assertSame( 'users', $password_map['from_tablename'] );
		$this->assertSame( 'user_pass', $password_map['from_fieldname'] );
		$this->assertArrayNotHasKey( 'callback_method', $password_map );
	}

	/**
	 * @covers BBP_Converter_Base::clean_passwords
	 * @ticket BBP3684
	 */
	public function test_import_cleanup_restores_wordpress_password_hash() {
		global $wpdb;

		$password = 'mingle-password';
		$hash     = wp_hash_password( $password );
		$user_id  = $this->factory->user->create();

		$wpdb->update( $wpdb->users, array( 'user_pass' => '' ), array( 'ID' => $user_id ) );
		update_user_meta( $user_id, '_bbp_password', $hash );
		clean_user_cache( $user_id );

		$this->assertFalse( $this->converter->clean_passwords( 0 ) );
		$this->assertSame( $hash, get_userdata( $user_id )->user_pass );
		$this->assertTrue( wp_check_password( $password, get_userdata( $user_id )->user_pass, $user_id ) );
		$this->assertFalse( metadata_exists( 'user', $user_id, '_bbp_password' ) );
	}

	/**
	 * @covers BBP_Converter_Base::clean_passwords
	 * @ticket BBP3684
	 */
	public function test_legacy_wordpress_md5_hash_uses_normal_login_upgrade() {
		global $wpdb;

		$password = 'mingle-legacy-password';
		$hash     = md5( $password );
		$user_id  = $this->factory->user->create( array( 'user_login' => 'mingle-legacy-user' ) );

		$wpdb->update( $wpdb->users, array( 'user_pass' => '' ), array( 'ID' => $user_id ) );
		update_user_meta( $user_id, '_bbp_password', $hash );
		clean_user_cache( $user_id );

		$this->converter->clean_passwords( 0 );
		$user = wp_authenticate_username_password( null, 'mingle-legacy-user', $password );

		$this->assertInstanceOf( 'WP_User', $user );
		$this->assertNotSame( $hash, get_userdata( $user_id )->user_pass );
		$this->assertTrue( wp_check_password( $password, get_userdata( $user_id )->user_pass, $user_id ) );
		$this->assertFalse( metadata_exists( 'user', $user_id, '_bbp_password' ) );
	}
}
