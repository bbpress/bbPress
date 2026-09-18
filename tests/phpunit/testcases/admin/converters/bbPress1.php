<?php

/**
 * Tests for the bbPress 1.x converter.
 *
 * @group converters
 */
class BBP_Tests_Admin_Converters_bbPress1 extends BBP_UnitTestCase {

	/**
	 * @var bbPress1
	 */
	protected $converter;

	/**
	 * @var object
	 */
	protected $source_db;

	public function setUp(): void {
		parent::setUp();

		bbp_setup_converter();
		require_once BBP_PLUGIN_DIR . 'includes/admin/converters/bbPress1.php';

		$reflection      = new ReflectionClass( 'bbPress1' );
		$this->converter = $reflection->newInstanceWithoutConstructor();
		$this->source_db = new class() {
			public $connect_calls = 0;

			public function db_connect( $allow_bail = true ) {
				++$this->connect_calls;

				return false;
			}
		};

		$set_databases = Closure::bind(
			function( $converter, $source_db ) {
				$converter->wpdb     = bbp_db();
				$converter->opdb     = $source_db;
				$converter->max_rows = 100;
			},
			null,
			'BBP_Converter_Base'
		);
		$set_databases( $this->converter, $this->source_db );
	}

	public function tearDown(): void {
		unset( $_POST['log'], $_POST['pwd'] );
		delete_option( '_bbp_converter_platform' );

		parent::tearDown();
	}

	/**
	 * @covers bbPress1::setup_globals
	 * @ticket BBP3684
	 */
	public function test_password_mapping_uses_wordpress_compatible_hash() {
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
	public function test_import_cleanup_restores_bbpress_1_2_portable_hash() {
		global $wpdb;

		$password = 'bbpress1-password';
		$hash     = '$P$B12345678hJXDBqWWtknjxzFbCSCJO.';
		$user_id  = $this->factory->user->create();

		$wpdb->update( $wpdb->users, array( 'user_pass' => '' ), array( 'ID' => $user_id ) );
		update_user_meta( $user_id, '_bbp_password', $hash );
		clean_user_cache( $user_id );

		$this->assertFalse( $this->converter->clean_passwords( 0 ) );
		clean_user_cache( $user_id );
		wp_cache_delete( $user_id, 'user_meta' );
		$this->assertSame( $hash, get_userdata( $user_id )->user_pass );
		$this->assertTrue( wp_check_password( $password, get_userdata( $user_id )->user_pass, $user_id ) );
		$this->assertFalse( metadata_exists( 'user', $user_id, '_bbp_password' ) );
		$this->assertSame( 0, $this->source_db->connect_calls );
	}

	/**
	 * @covers BBP_Converter_Base::clean_passwords
	 * @ticket BBP3684
	 */
	public function test_username_login_accepts_bbpress_1_2_portable_hash() {
		global $wpdb;

		$password = 'bbpress1-password';
		$hash     = '$P$B12345678hJXDBqWWtknjxzFbCSCJO.';
		$user_id  = $this->factory->user->create( array( 'user_login' => 'bbpress1-portable-user' ) );

		$wpdb->update( $wpdb->users, array( 'user_pass' => '' ), array( 'ID' => $user_id ) );
		update_user_meta( $user_id, '_bbp_password', $hash );
		clean_user_cache( $user_id );

		$this->converter->clean_passwords( 0 );
		clean_user_cache( $user_id );
		wp_cache_delete( $user_id, 'user_meta' );
		$user = wp_authenticate_username_password( null, 'bbpress1-portable-user', $password );

		$this->assertInstanceOf( 'WP_User', $user );
		$this->assertTrue( wp_check_password( $password, get_userdata( $user_id )->user_pass, $user_id ) );
		$this->assertFalse( metadata_exists( 'user', $user_id, '_bbp_password' ) );
		$this->assertSame( 0, $this->source_db->connect_calls );
	}

	/**
	 * @covers BBP_Converter_Base::clean_passwords
	 * @ticket BBP3684
	 */
	public function test_email_login_accepts_bbpress_1_2_portable_hash() {
		global $wpdb;

		$password = 'bbpress1-password';
		$hash     = '$P$B12345678hJXDBqWWtknjxzFbCSCJO.';
		$email    = 'bbpress1-import@example.org';
		$user_id  = $this->factory->user->create( array( 'user_email' => $email ) );

		$wpdb->update( $wpdb->users, array( 'user_pass' => '' ), array( 'ID' => $user_id ) );
		update_user_meta( $user_id, '_bbp_password', $hash );
		clean_user_cache( $user_id );

		$this->converter->clean_passwords( 0 );
		clean_user_cache( $user_id );
		wp_cache_delete( $user_id, 'user_meta' );
		$user = wp_authenticate_email_password( null, $email, $password );

		$this->assertInstanceOf( 'WP_User', $user );
		$this->assertTrue( wp_check_password( $password, get_userdata( $user_id )->user_pass, $user_id ) );
		$this->assertFalse( metadata_exists( 'user', $user_id, '_bbp_password' ) );
		$this->assertSame( 0, $this->source_db->connect_calls );
	}

	/**
	 * @covers BBP_Converter_Base::clean_passwords
	 * @ticket BBP3684
	 */
	public function test_legacy_md5_hash_uses_normal_wordpress_login_upgrade() {
		global $wpdb;

		$password = 'bbpress1-legacy-password';
		$hash     = md5( $password );
		$user_id  = $this->factory->user->create( array( 'user_login' => 'bbpress1-legacy-user' ) );

		$wpdb->update( $wpdb->users, array( 'user_pass' => '' ), array( 'ID' => $user_id ) );
		update_user_meta( $user_id, '_bbp_password', $hash );
		clean_user_cache( $user_id );

		$this->converter->clean_passwords( 0 );
		clean_user_cache( $user_id );
		wp_cache_delete( $user_id, 'user_meta' );
		$user = wp_authenticate_username_password( null, 'bbpress1-legacy-user', $password );

		$this->assertInstanceOf( 'WP_User', $user );
		$this->assertNotSame( $hash, get_userdata( $user_id )->user_pass );
		$this->assertTrue( wp_check_password( $password, get_userdata( $user_id )->user_pass, $user_id ) );
		$this->assertFalse( metadata_exists( 'user', $user_id, '_bbp_password' ) );
		$this->assertSame( 0, $this->source_db->connect_calls );
	}

	/**
	 * @covers BBP_Converter_Base::clean_passwords
	 * @ticket BBP3684
	 */
	public function test_failed_login_preserves_imported_hash() {
		global $wpdb;

		$hash    = '$P$B12345678hJXDBqWWtknjxzFbCSCJO.';
		$user_id = $this->factory->user->create( array( 'user_login' => 'bbpress1-failed-login' ) );

		$wpdb->update( $wpdb->users, array( 'user_pass' => '' ), array( 'ID' => $user_id ) );
		update_user_meta( $user_id, '_bbp_password', $hash );
		clean_user_cache( $user_id );

		$this->converter->clean_passwords( 0 );
		clean_user_cache( $user_id );
		wp_cache_delete( $user_id, 'user_meta' );
		$user = wp_authenticate_username_password( null, 'bbpress1-failed-login', 'incorrect' );

		$this->assertWPError( $user );
		$this->assertSame( $hash, get_userdata( $user_id )->user_pass );
		$this->assertFalse( metadata_exists( 'user', $user_id, '_bbp_password' ) );
		$this->assertSame( 0, $this->source_db->connect_calls );
	}

	/**
	 * @covers BBP_Converter_Base::clean_passwords
	 * @covers ::bbp_user_maybe_convert_pass
	 * @ticket BBP3684
	 */
	public function test_malformed_serialized_metadata_is_not_promoted_to_user_pass() {
		global $wpdb;

		$user_id = $this->factory->user->create( array( 'user_login' => 'bbpress1-malformed-user' ) );

		$wpdb->update( $wpdb->users, array( 'user_pass' => '' ), array( 'ID' => $user_id ) );
		update_user_meta( $user_id, '_bbp_password', array( 'hash' => array() ) );
		update_option( '_bbp_converter_platform', 'bbPress1' );
		clean_user_cache( $user_id );

		$this->converter->clean_passwords( 0 );

		$_POST['log'] = 'bbpress1-malformed-user';
		$_POST['pwd'] = 'password';
		bbp_user_maybe_convert_pass();

		$this->assertSame( '', get_userdata( $user_id )->user_pass );
		$this->assertTrue( metadata_exists( 'user', $user_id, '_bbp_password' ) );
		$this->assertFalse( metadata_exists( 'user', $user_id, '_bbp_class' ) );
		$this->assertSame( 0, $this->source_db->connect_calls );
	}
}
