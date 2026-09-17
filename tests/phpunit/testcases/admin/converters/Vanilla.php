<?php

/**
 * Tests for the Vanilla 2 converter.
 *
 * @group converters
 */
class BBP_Tests_Admin_Converters_Vanilla extends BBP_UnitTestCase {

	/**
	 * @var Vanilla
	 */
	protected $converter;

	public function setUp(): void {
		parent::setUp();

		bbp_setup_converter();
		require_once BBP_PLUGIN_DIR . 'includes/admin/converters/Vanilla.php';

		$reflection      = new ReflectionClass( 'Vanilla' );
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

	public function tearDown(): void {
		unset( $_POST['log'], $_POST['pwd'] );
		delete_option( '_bbp_converter_platform' );

		parent::tearDown();
	}

	/**
	 * @covers Vanilla::setup_globals
	 * @ticket BBP3684
	 */
	public function test_password_method_and_class_mappings() {
		$this->converter->setup_globals();

		$get_field_map = Closure::bind(
			function( $converter ) {
				return $converter->field_map;
			},
			null,
			'BBP_Converter_Base'
		);
		$field_map      = $get_field_map( $this->converter );
		$password_maps  = wp_filter_object_list( $field_map, array( 'to_fieldname' => '_bbp_password' ) );
		$method_maps    = wp_filter_object_list( $field_map, array( 'from_fieldname' => 'HashMethod' ) );
		$class_maps     = wp_filter_object_list( $field_map, array( 'to_fieldname' => '_bbp_class' ) );
		$password_map   = reset( $password_maps );
		$method_map     = reset( $method_maps );
		$class_map      = reset( $class_maps );

		$this->assertCount( 1, $password_maps );
		$this->assertCount( 1, $method_maps );
		$this->assertCount( 1, $class_maps );
		$this->assertSame( 'Password', $password_map['from_fieldname'] );
		$this->assertSame( 'callback_savepass', $password_map['callback_method'] );
		$this->assertSame( '', $method_map['to_fieldname'] );
		$this->assertSame( 'Vanilla', $class_map['default'] );
	}

	/**
	 * @covers Vanilla::callback_savepass
	 * @ticket BBP3684
	 */
	public function test_callback_savepass_preserves_legacy_plaintext_password() {
		$password = 'legacy\\"password';
		$metadata = $this->converter->callback_savepass( $password, array( 'HashMethod' => '' ) );
		$user_id  = $this->factory->user->create();

		$this->assertSame( wp_slash( $password ), $metadata['hash'] );
		$this->assertSame( '', $metadata['method'] );
		update_user_meta( $user_id, '_bbp_password', $metadata );
		$this->assertSame( $password, get_user_meta( $user_id, '_bbp_password', true )['hash'] );
	}

	/**
	 * @covers Vanilla::authenticate_pass
	 * @ticket BBP3684
	 */
	public function test_authenticate_pass_with_native_phpass_hash() {
		$metadata = serialize(
			array(
				'hash'   => '$P$BrCcPc.mOwmL.7dO6EExggauzt0YqG/',
				'method' => 'Vanilla',
			)
		);

		$this->assertTrue( $this->converter->authenticate_pass( '123456', $metadata ) );
		$this->assertFalse( $this->converter->authenticate_pass( 'incorrect', $metadata ) );
	}

	/**
	 * @covers Vanilla::authenticate_pass
	 * @ticket BBP3684
	 */
	public function test_authenticate_pass_with_extended_des_phpass_hash() {
		if ( 1 !== CRYPT_EXT_DES ) {
			$this->markTestSkipped( 'Extended DES is unavailable.' );
		}

		$metadata = serialize(
			array(
				'hash'   => '_J9..rasmL3ElVNUFhV.',
				'method' => 'Vanilla',
			)
		);

		$this->assertTrue( $this->converter->authenticate_pass( 'test12345', $metadata ) );
		$this->assertFalse( $this->converter->authenticate_pass( 'incorrect', $metadata ) );
	}

	/**
	 * @covers Vanilla::authenticate_pass
	 * @ticket BBP3684
	 */
	public function test_authenticate_pass_with_legacy_md5_hash() {
		$metadata = serialize(
			array(
				'hash'   => md5( 'legacy-md5-password' ),
				'method' => '',
			)
		);

		$this->assertTrue( $this->converter->authenticate_pass( 'legacy-md5-password', $metadata ) );
		$this->assertFalse( $this->converter->authenticate_pass( 'incorrect', $metadata ) );
	}

	/**
	 * @covers Vanilla::authenticate_pass
	 * @ticket BBP3684
	 */
	public function test_authenticate_pass_with_legacy_plaintext_password() {
		$metadata = serialize(
			array(
				'hash'   => 'legacy-plain-password',
				'method' => '',
			)
		);

		$this->assertTrue( $this->converter->authenticate_pass( 'legacy-plain-password', $metadata ) );
		$this->assertFalse( $this->converter->authenticate_pass( 'incorrect', $metadata ) );
	}

	/**
	 * @covers Vanilla::authenticate_pass
	 * @ticket BBP3684
	 */
	public function test_authenticate_pass_rejects_invalid_metadata_and_external_methods() {
		$this->assertFalse( $this->converter->authenticate_pass( 'password', serialize( array() ) ) );
		$this->assertFalse( $this->converter->authenticate_pass( 'password', serialize( 'not an array' ) ) );
		$this->assertFalse( $this->converter->authenticate_pass( 'password', serialize( array( 'hash' => array(), 'method' => 'Vanilla' ) ) ) );
		$this->assertFalse( $this->converter->authenticate_pass( 'password', serialize( array( 'hash' => 'hash', 'method' => array() ) ) ) );
		$this->assertFalse( $this->converter->authenticate_pass( array(), serialize( array( 'hash' => 'hash', 'method' => 'Vanilla' ) ) ) );
		$this->assertFalse( $this->converter->authenticate_pass( 'password', serialize( array( 'hash' => 'password', 'method' => 'Django' ) ) ) );
		$this->assertFalse( $this->converter->authenticate_pass( 'password', serialize( array( 'hash' => '*', 'method' => 'Vanilla' ) ) ) );
		$this->assertFalse( $this->converter->authenticate_pass( '0', serialize( array( 'hash' => '0', 'method' => 'Vanilla' ) ) ) );
	}

	/**
	 * @covers ::bbp_user_maybe_convert_pass
	 * @covers Vanilla::callback_savepass
	 * @covers Vanilla::authenticate_pass
	 * @ticket BBP3684
	 */
	public function test_username_login_upgrades_native_hash_without_source_database() {
		global $wpdb;

		$password = '123456';
		$user_id  = $this->factory->user->create( array( 'user_login' => 'vanilla-native-user' ) );

		$wpdb->update( $wpdb->users, array( 'user_pass' => '' ), array( 'ID' => $user_id ) );
		update_user_meta(
			$user_id,
			'_bbp_password',
			$this->converter->callback_savepass(
				'$P$BrCcPc.mOwmL.7dO6EExggauzt0YqG/',
				array( 'HashMethod' => 'Vanilla' )
			)
		);
		update_user_meta( $user_id, '_bbp_class', 'Vanilla' );
		clean_user_cache( $user_id );

		$_POST['log'] = 'vanilla-native-user';
		$_POST['pwd'] = $password;

		bbp_user_maybe_convert_pass();

		$this->assertTrue( wp_check_password( $password, get_userdata( $user_id )->user_pass, $user_id ) );
		$this->assertFalse( metadata_exists( 'user', $user_id, '_bbp_password' ) );
		$this->assertFalse( metadata_exists( 'user', $user_id, '_bbp_class' ) );
	}

	/**
	 * @covers ::bbp_user_maybe_convert_pass
	 * @covers Vanilla::authenticate_pass
	 * @ticket BBP3684
	 */
	public function test_email_login_upgrades_legacy_plaintext_password() {
		global $wpdb;

		$password = 'legacy-plain-password';
		$user_id  = $this->factory->user->create(
			array(
				'user_login' => 'vanilla-plain-user',
				'user_email' => 'vanilla-plain@example.org',
			)
		);

		$wpdb->update( $wpdb->users, array( 'user_pass' => '' ), array( 'ID' => $user_id ) );
		update_user_meta(
			$user_id,
			'_bbp_password',
			$this->converter->callback_savepass( $password, array( 'HashMethod' => '' ) )
		);
		update_user_meta( $user_id, '_bbp_class', 'Vanilla' );
		clean_user_cache( $user_id );

		$_POST['log'] = 'vanilla-plain@example.org';
		$_POST['pwd'] = $password;

		bbp_user_maybe_convert_pass();

		$this->assertTrue( wp_check_password( $password, get_userdata( $user_id )->user_pass, $user_id ) );
		$this->assertFalse( metadata_exists( 'user', $user_id, '_bbp_password' ) );
		$this->assertFalse( metadata_exists( 'user', $user_id, '_bbp_class' ) );
	}

	/**
	 * @covers ::bbp_user_maybe_convert_pass
	 * @covers Vanilla::authenticate_pass
	 * @ticket BBP3684
	 */
	public function test_import_without_class_meta_upgrades_from_saved_platform() {
		global $wpdb;

		$password = 'legacy-plain-password';
		$user_id  = $this->factory->user->create( array( 'user_login' => 'vanilla-saved-platform-user' ) );

		$wpdb->update( $wpdb->users, array( 'user_pass' => '' ), array( 'ID' => $user_id ) );
		update_user_meta(
			$user_id,
			'_bbp_password',
			array(
				'hash'   => $password,
				'method' => '',
			)
		);
		update_option( '_bbp_converter_platform', 'Vanilla' );
		clean_user_cache( $user_id );

		$_POST['log'] = 'vanilla-saved-platform-user';
		$_POST['pwd'] = $password;

		bbp_user_maybe_convert_pass();

		$this->assertTrue( wp_check_password( $password, get_userdata( $user_id )->user_pass, $user_id ) );
		$this->assertFalse( metadata_exists( 'user', $user_id, '_bbp_password' ) );
	}

	/**
	 * @covers ::bbp_user_maybe_convert_pass
	 * @covers Vanilla::authenticate_pass
	 * @ticket BBP3684
	 */
	public function test_failed_login_preserves_password_metadata() {
		global $wpdb;

		$user_id  = $this->factory->user->create( array( 'user_login' => 'vanilla-failed-user' ) );
		$metadata = array(
			'hash'   => '$P$BrCcPc.mOwmL.7dO6EExggauzt0YqG/',
			'method' => 'Vanilla',
		);

		$wpdb->update( $wpdb->users, array( 'user_pass' => '' ), array( 'ID' => $user_id ) );
		update_user_meta( $user_id, '_bbp_password', $metadata );
		update_user_meta( $user_id, '_bbp_class', 'Vanilla' );
		clean_user_cache( $user_id );

		$_POST['log'] = 'vanilla-failed-user';
		$_POST['pwd'] = 'incorrect';

		bbp_user_maybe_convert_pass();

		$this->assertSame( '', get_userdata( $user_id )->user_pass );
		$this->assertSame( $metadata, get_user_meta( $user_id, '_bbp_password', true ) );
		$this->assertSame( 'Vanilla', get_user_meta( $user_id, '_bbp_class', true ) );
	}

	/**
	 * @covers BBP_Converter_Base::clean_passwords
	 * @ticket BBP3684
	 */
	public function test_historical_native_hash_import_remains_compatible() {
		global $wpdb;

		$password = '123456';
		$hash     = '$P$BrCcPc.mOwmL.7dO6EExggauzt0YqG/';
		$user_id  = $this->factory->user->create( array( 'user_login' => 'vanilla-historical-user' ) );

		$wpdb->update( $wpdb->users, array( 'user_pass' => '' ), array( 'ID' => $user_id ) );
		update_user_meta( $user_id, '_bbp_password', $hash );
		clean_user_cache( $user_id );

		$this->assertFalse( $this->converter->clean_passwords( 0 ) );
		$this->assertSame( $hash, get_userdata( $user_id )->user_pass );

		$user = wp_authenticate_username_password( null, 'vanilla-historical-user', $password );

		$this->assertInstanceOf( 'WP_User', $user );
		$this->assertTrue( wp_check_password( $password, get_userdata( $user_id )->user_pass, $user_id ) );
		$this->assertFalse( metadata_exists( 'user', $user_id, '_bbp_password' ) );
	}
}
