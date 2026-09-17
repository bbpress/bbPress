<?php

/**
 * Tests for the XenForo converter.
 *
 * @group converters
 */
class BBP_Tests_Admin_Converters_XenForo extends BBP_UnitTestCase {

	/**
	 * @var XenForo
	 */
	protected $converter;

	public function setUp(): void {
		parent::setUp();

		bbp_setup_converter();
		require_once BBP_PLUGIN_DIR . 'includes/admin/converters/XenForo.php';

		$reflection      = new ReflectionClass( 'XenForo' );
		$this->converter = $reflection->newInstanceWithoutConstructor();
	}

	public function tearDown(): void {
		unset( $_POST['log'], $_POST['pwd'] );
		delete_option( '_bbp_converter_platform' );

		parent::tearDown();
	}

	/**
	 * @covers XenForo::setup_globals
	 * @ticket BBP3684
	 */
	public function test_password_is_mapped_from_authentication_table() {
		$this->converter->setup_globals();

		$get_field_map = Closure::bind(
			function( $converter ) {
				return $converter->field_map;
			},
			null,
			'BBP_Converter_Base'
		);
		$field_map       = $get_field_map( $this->converter );
		$password_maps   = wp_filter_object_list( $field_map, array( 'to_fieldname' => '_bbp_password' ) );
		$scheme_mappings = wp_filter_object_list( $field_map, array( 'from_fieldname' => 'scheme_class' ) );
		$password_map    = reset( $password_maps );
		$scheme_mapping  = reset( $scheme_mappings );

		$this->assertSame( 'user_authenticate', $password_map['from_tablename'] );
		$this->assertSame( 'data', $password_map['from_fieldname'] );
		$this->assertSame( 'user', $password_map['join_tablename'] );
		$this->assertSame( 'callback_savepass', $password_map['callback_method'] );
		$this->assertSame( 'user_authenticate', $scheme_mapping['from_tablename'] );
		$this->assertSame( '', $scheme_mapping['to_fieldname'] );
		$this->assertLessThan( key( $scheme_mappings ), key( $password_maps ) );
	}

	/**
	 * @covers XenForo::callback_savepass
	 * @ticket BBP3684
	 */
	public function test_callback_savepass_preserves_authentication_data_and_scheme() {
		$data = array(
			'hash'     => str_repeat( 'a', 64 ),
			'salt'     => str_repeat( 'b', 64 ),
			'hashFunc' => 'sha256',
		);

		$this->assertSame(
			array_merge( $data, array( 'scheme' => 'XenForo_Authentication_Core' ) ),
			$this->converter->callback_savepass( serialize( $data ), array( 'scheme_class' => 'XenForo_Authentication_Core' ) )
		);
	}

	/**
	 * @covers XenForo::callback_savepass
	 * @ticket BBP3684
	 */
	public function test_callback_savepass_preserves_unknown_scheme_for_future_support() {
		$this->assertSame(
			array(
				'hash'   => 'legacy-hash',
				'salt'   => 'legacy-salt',
				'scheme' => 'XenForo_Authentication_ImportedPlatform',
			),
			$this->converter->callback_savepass(
				serialize( array( 'hash' => 'legacy-hash', 'salt' => 'legacy-salt' ) ),
				array( 'scheme_class' => 'XenForo_Authentication_ImportedPlatform' )
			)
		);
	}

	/**
	 * @covers XenForo::callback_savepass
	 * @ticket BBP3684
	 */
	public function test_callback_savepass_rejects_invalid_data() {
		$truncated = 'a:1:{s:4:"hash";s:60:"short";}';

		$this->assertTrue( is_serialized( $truncated ) );
		$this->assertFalse( $this->converter->callback_savepass( $truncated, array( 'scheme_class' => 'XenForo_Authentication_Core' ) ) );
		$this->assertFalse( $this->converter->callback_savepass( 'not serialized', array( 'scheme_class' => 'XenForo_Authentication_Core' ) ) );
		$this->assertFalse( $this->converter->callback_savepass( serialize( 'not an array' ), array( 'scheme_class' => 'XenForo_Authentication_Core' ) ) );
		$this->assertFalse( $this->converter->callback_savepass( serialize( array() ), array( 'scheme_class' => 'XenForo_Authentication_Core' ) ) );
		$this->assertFalse( $this->converter->callback_savepass( serialize( array( 'hash' => array() ) ), array( 'scheme_class' => 'XenForo_Authentication_Core' ) ) );
		$this->assertFalse( $this->converter->callback_savepass( serialize( array( 'hash' => 'hash' ) ), array() ) );
	}

	/**
	 * @covers XenForo::callback_savepass
	 * @ticket BBP3684
	 */
	public function test_callback_savepass_preserves_backslashes_through_user_meta() {
		$user_id = $this->factory->user->create();
		$data    = array(
			'hash' => 'legacy\\hash',
			'salt' => 'legacy\\salt',
		);

		add_user_meta(
			$user_id,
			'_bbp_password',
			$this->converter->callback_savepass(
				serialize( $data ),
				array( 'scheme_class' => 'XenForo_Authentication_ImportedPlatform' )
			),
			true
		);

		$this->assertSame(
			array_merge( $data, array( 'scheme' => 'XenForo_Authentication_ImportedPlatform' ) ),
			get_user_meta( $user_id, '_bbp_password', true )
		);
	}

	/**
	 * @covers XenForo::authenticate_pass
	 * @dataProvider data_native_passwords
	 * @ticket BBP3684
	 */
	public function test_authenticate_pass_with_native_formats( $metadata ) {
		$password = 'Correct Horse Battery Staple';

		$this->assertTrue( $this->converter->authenticate_pass( $password, serialize( $metadata ) ) );
		$this->assertFalse( $this->converter->authenticate_pass( 'incorrect', serialize( $metadata ) ) );
	}

	/**
	 * Native XenForo password metadata.
	 */
	public function data_native_passwords() {
		$password = 'Correct Horse Battery Staple';
		$salt     = 'a1B2c3D4';

		return array(
			'pre-1.2 SHA-256' => array(
				array(
					'hash'     => hash( 'sha256', hash( 'sha256', $password ) . $salt ),
					'salt'     => $salt,
					'hashFunc' => 'sha256',
					'scheme'   => 'XenForo_Authentication_Core',
				),
			),
			'pre-1.2 SHA-1' => array(
				array(
					'hash'     => sha1( sha1( $password ) . $salt ),
					'salt'     => $salt,
					'hashFunc' => 'sha1',
					'scheme'   => 'XenForo_Authentication_Core',
				),
			),
			'XenForo 2 legacy SHA scheme name' => array(
				array(
					'hash'     => hash( 'sha256', hash( 'sha256', $password ) . $salt ),
					'salt'     => $salt,
					'hashFunc' => 'sha256',
					'scheme'   => 'XF:Core',
				),
			),
			'1.2+ bcrypt' => array(
				array(
					'hash'   => '$2y$12$J.9fjdYho0cRy2TJAoSiYOtuCxaHV/U/UuTyU180mOX2TENlp0jc6',
					'scheme' => 'XenForo_Authentication_Core12',
				),
			),
			'XenForo 2 bcrypt scheme name' => array(
				array(
					'hash'   => '$2y$12$J.9fjdYho0cRy2TJAoSiYOtuCxaHV/U/UuTyU180mOX2TENlp0jc6',
					'scheme' => 'XF:Core12',
				),
			),
		);
	}

	/**
	 * @covers XenForo::authenticate_pass
	 * @ticket BBP3684
	 */
	public function test_authenticate_pass_rejects_invalid_or_unsupported_metadata() {
		$this->assertFalse( $this->converter->authenticate_pass( 'password', serialize( array() ) ) );
		$this->assertFalse( $this->converter->authenticate_pass( 'password', serialize( array( 'hash' => array(), 'scheme' => 'XenForo_Authentication_Core12' ) ) ) );
		$this->assertFalse( $this->converter->authenticate_pass( 'password', serialize( array( 'hash' => 'hash', 'scheme' => 'unsupported' ) ) ) );
		$this->assertFalse( $this->converter->authenticate_pass( 'password', serialize( array( 'hash' => 'hash', 'scheme' => 'XenForo_Authentication_Core', 'hashFunc' => array(), 'salt' => 'salt' ) ) ) );
		$this->assertFalse( $this->converter->authenticate_pass( array(), serialize( array( 'hash' => 'hash', 'scheme' => 'XenForo_Authentication_Core12' ) ) ) );
	}

	/**
	 * @covers ::bbp_user_maybe_convert_pass
	 * @covers XenForo::authenticate_pass
	 * @ticket BBP3684
	 */
	public function test_email_login_upgrades_password_without_source_database() {
		global $wpdb;

		$password = 'Correct Horse Battery Staple';
		$user_id  = $this->factory->user->create(
			array(
				'user_login' => 'xenforo-imported-user',
				'user_email' => 'xenforo-imported@example.org',
			)
		);
		$metadata = array(
			'hash'   => '$2y$12$J.9fjdYho0cRy2TJAoSiYOtuCxaHV/U/UuTyU180mOX2TENlp0jc6',
			'scheme' => 'XenForo_Authentication_Core12',
		);

		$wpdb->update( $wpdb->users, array( 'user_pass' => '' ), array( 'ID' => $user_id ) );
		update_user_meta( $user_id, '_bbp_password', $metadata );
		update_user_meta( $user_id, '_bbp_class', 'XenForo' );
		clean_user_cache( $user_id );

		$_POST['log'] = 'xenforo-imported@example.org';
		$_POST['pwd'] = $password;

		bbp_user_maybe_convert_pass();

		$this->assertTrue( wp_check_password( $password, get_userdata( $user_id )->user_pass, $user_id ) );
		$this->assertFalse( metadata_exists( 'user', $user_id, '_bbp_password' ) );
		$this->assertFalse( metadata_exists( 'user', $user_id, '_bbp_class' ) );
	}

	/**
	 * @covers ::bbp_user_maybe_convert_pass
	 * @covers XenForo::authenticate_pass
	 * @ticket BBP3684
	 */
	public function test_failed_login_preserves_password_metadata() {
		global $wpdb;

		$user_id  = $this->factory->user->create( array( 'user_login' => 'xenforo-failed-user' ) );
		$metadata = array(
			'hash'   => '$2y$12$J.9fjdYho0cRy2TJAoSiYOtuCxaHV/U/UuTyU180mOX2TENlp0jc6',
			'scheme' => 'XenForo_Authentication_Core12',
		);

		$wpdb->update( $wpdb->users, array( 'user_pass' => '' ), array( 'ID' => $user_id ) );
		update_user_meta( $user_id, '_bbp_password', $metadata );
		update_user_meta( $user_id, '_bbp_class', 'XenForo' );
		clean_user_cache( $user_id );

		$_POST['log'] = 'xenforo-failed-user';
		$_POST['pwd'] = 'incorrect';

		bbp_user_maybe_convert_pass();

		$this->assertSame( '', get_userdata( $user_id )->user_pass );
		$this->assertSame( $metadata, get_user_meta( $user_id, '_bbp_password', true ) );
		$this->assertSame( 'XenForo', get_user_meta( $user_id, '_bbp_class', true ) );
	}
}
