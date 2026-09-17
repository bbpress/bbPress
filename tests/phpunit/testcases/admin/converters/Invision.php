<?php

/**
 * Tests for the IP.Board 3 converter.
 *
 * @group converters
 */
class BBP_Tests_Admin_Converters_Invision extends BBP_UnitTestCase {

	/**
	 * @var Invision
	 */
	protected $converter;

	public function setUp(): void {
		parent::setUp();

		bbp_setup_converter();
		require_once BBP_PLUGIN_DIR . 'includes/admin/converters/Invision.php';

		$reflection      = new ReflectionClass( 'Invision' );
		$this->converter = $reflection->newInstanceWithoutConstructor();
	}

	public function tearDown(): void {
		unset( $_POST['log'], $_POST['pwd'] );
		delete_option( '_bbp_converter_platform' );

		parent::tearDown();
	}

	/**
	 * @covers Invision::setup_globals
	 * @ticket BBP3684
	 */
	public function test_password_and_salt_mappings() {
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
		$salt_maps     = wp_filter_object_list( $field_map, array( 'from_fieldname' => 'members_pass_salt' ) );
		$class_maps    = wp_filter_object_list( $field_map, array( 'to_fieldname' => '_bbp_class' ) );
		$password_map  = reset( $password_maps );
		$salt_map      = reset( $salt_maps );
		$class_map     = reset( $class_maps );

		$this->assertCount( 1, $password_maps );
		$this->assertCount( 1, $salt_maps );
		$this->assertCount( 1, $class_maps );
		$this->assertSame( 'members', $password_map['from_tablename'] );
		$this->assertSame( 'members_pass_hash', $password_map['from_fieldname'] );
		$this->assertSame( 'callback_savepass', $password_map['callback_method'] );
		$this->assertSame( '', $salt_map['to_fieldname'] );
		$this->assertSame( 'user', $class_map['to_type'] );
		$this->assertSame( 'Invision', $class_map['default'] );
	}

	/**
	 * @covers Invision::callback_savepass
	 * @ticket BBP3684
	 */
	public function test_callback_savepass_preserves_hash_and_salt() {
		$this->assertSame(
			array(
				'hash' => 'f3f3c75110ea9a27a1c01e580676997f',
				'salt' => 'Do."O',
			),
			$this->converter->callback_savepass(
				'f3f3c75110ea9a27a1c01e580676997f',
				array( 'members_pass_salt' => 'Do."O' )
			)
		);
	}

	/**
	 * @covers Invision::callback_savepass
	 * @ticket BBP3684
	 */
	public function test_callback_savepass_normalizes_missing_salt() {
		$this->assertSame(
			array(
				'hash' => '5c8315e93cb86e3fcbf9a92673545161',
				'salt' => '',
			),
			$this->converter->callback_savepass( '5c8315e93cb86e3fcbf9a92673545161', array() )
		);
	}

	/**
	 * @covers Invision::authenticate_pass
	 * @ticket BBP3684
	 */
	public function test_authenticate_pass_with_native_alphanumeric_hash() {
		$metadata = serialize(
			array(
				'hash' => 'f3f3c75110ea9a27a1c01e580676997f',
				'salt' => 'Do.|O',
			)
		);

		$this->assertTrue( $this->converter->authenticate_pass( 'fsk23478cf', $metadata ) );
		$this->assertFalse( $this->converter->authenticate_pass( 'incorrect', $metadata ) );
	}

	/**
	 * @covers Invision::authenticate_pass
	 * @ticket BBP3684
	 */
	public function test_authenticate_pass_with_native_punctuation_hash() {
		// IP.Board 3.4 fixture: md5( md5( 'ppxps' ) . md5( 'fsk23478cf&#33;*' ) ).
		$metadata = serialize(
			array(
				'hash' => 'd060c2fb78c5b8a9e9d303c7b4fab456',
				'salt' => 'ppxps',
			)
		);

		$this->assertTrue( $this->converter->authenticate_pass( 'fsk23478cf!*', $metadata ) );
		$this->assertFalse( $this->converter->authenticate_pass( 'incorrect', $metadata ) );
	}

	/**
	 * @covers Invision::authenticate_pass
	 * @ticket BBP3684
	 */
	public function test_authenticate_pass_with_native_request_cleaning() {
		// Exercises parseCleanValue() ordering, including its &#032; restoration.
		$password = "one<!--x-->two<SCRIPT>x\r\n&\$!'\"&#032;";
		$metadata = serialize(
			array(
				'hash' => '4c52ff1b602aa44a60ae2e6b672effd3',
				'salt' => 'a"J9T',
			)
		);

		$this->assertTrue( $this->converter->authenticate_pass( $password, $metadata ) );
		$this->assertFalse( $this->converter->authenticate_pass( 'incorrect', $metadata ) );
	}

	/**
	 * @covers Invision::authenticate_pass
	 * @ticket BBP3684
	 */
	public function test_authenticate_pass_retains_historical_bbpress_transformation() {
		$metadata = serialize(
			array(
				'hash' => '277c8077d9ae9c9a5fd100834489189e',
				'salt' => 'ppxps',
			)
		);

		$this->assertTrue( $this->converter->authenticate_pass( 'fsk23478cf!*', $metadata ) );
	}

	/**
	 * @covers Invision::authenticate_pass
	 * @ticket BBP3684
	 */
	public function test_authenticate_pass_does_not_add_raw_password_fallback() {
		$password = 'fsk23478cf!*';
		$salt     = 'ppxps';
		$metadata = serialize(
			array(
				'hash' => md5( md5( $salt ) . md5( $password ) ),
				'salt' => $salt,
			)
		);

		$this->assertFalse( $this->converter->authenticate_pass( $password, $metadata ) );
	}

	/**
	 * @covers Invision::authenticate_pass
	 * @ticket BBP3684
	 */
	public function test_authenticate_pass_rejects_invalid_metadata() {
		$this->assertFalse( $this->converter->authenticate_pass( 'password', serialize( array() ) ) );
		$this->assertFalse( $this->converter->authenticate_pass( 'password', serialize( 'not an array' ) ) );
		$this->assertFalse( $this->converter->authenticate_pass( 'password', serialize( array( 'hash' => array(), 'salt' => 'salt' ) ) ) );
		$this->assertFalse( $this->converter->authenticate_pass( 'password', serialize( array( 'hash' => 'hash', 'salt' => array() ) ) ) );
		$this->assertFalse( $this->converter->authenticate_pass( array(), serialize( array( 'hash' => 'hash', 'salt' => 'salt' ) ) ) );
	}

	/**
	 * @covers ::bbp_user_maybe_convert_pass
	 * @covers Invision::callback_savepass
	 * @covers Invision::authenticate_pass
	 * @ticket BBP3684
	 */
	public function test_email_login_upgrades_punctuation_password_without_source_database() {
		global $wpdb;

		$password = 'fsk23478cf!*';
		$salt     = 'ppxps';
		$user_id  = $this->factory->user->create(
			array(
				'user_login' => 'invision-imported-user',
				'user_email' => 'invision-imported@example.org',
			)
		);

		$wpdb->update( $wpdb->users, array( 'user_pass' => '' ), array( 'ID' => $user_id ) );
		update_user_meta(
			$user_id,
			'_bbp_password',
			$this->converter->callback_savepass(
				'd060c2fb78c5b8a9e9d303c7b4fab456',
				array( 'members_pass_salt' => $salt )
			)
		);
		update_user_meta( $user_id, '_bbp_class', 'Invision' );
		clean_user_cache( $user_id );

		$this->assertSame( $salt, get_user_meta( $user_id, '_bbp_password', true )['salt'] );

		$_POST['log'] = 'invision-imported@example.org';
		$_POST['pwd'] = $password;

		bbp_user_maybe_convert_pass();

		$this->assertTrue( wp_check_password( $password, get_userdata( $user_id )->user_pass, $user_id ) );
		$this->assertFalse( metadata_exists( 'user', $user_id, '_bbp_password' ) );
		$this->assertFalse( metadata_exists( 'user', $user_id, '_bbp_class' ) );
	}

	/**
	 * @covers ::bbp_user_maybe_convert_pass
	 * @covers Invision::authenticate_pass
	 * @ticket BBP3684
	 */
	public function test_historical_password_metadata_still_upgrades() {
		global $wpdb;

		$password = 'fsk23478cf!*';
		$salt     = 'ppxps';
		$user_id  = $this->factory->user->create( array( 'user_login' => 'invision-historical-user' ) );

		$wpdb->update( $wpdb->users, array( 'user_pass' => '' ), array( 'ID' => $user_id ) );
		update_user_meta(
			$user_id,
			'_bbp_password',
			array(
				'hash' => '277c8077d9ae9c9a5fd100834489189e',
				'salt' => $salt,
			)
		);
		update_user_meta( $user_id, '_bbp_class', 'Invision' );
		clean_user_cache( $user_id );

		$_POST['log'] = 'invision-historical-user';
		$_POST['pwd'] = $password;

		bbp_user_maybe_convert_pass();

		$this->assertTrue( wp_check_password( $password, get_userdata( $user_id )->user_pass, $user_id ) );
		$this->assertFalse( metadata_exists( 'user', $user_id, '_bbp_password' ) );
		$this->assertFalse( metadata_exists( 'user', $user_id, '_bbp_class' ) );
	}

	/**
	 * @covers ::bbp_user_maybe_convert_pass
	 * @covers Invision::authenticate_pass
	 * @ticket BBP3684
	 */
	public function test_historical_import_without_class_meta_upgrades_from_saved_platform() {
		global $wpdb;

		$password = 'fsk23478cf';
		$salt     = 'Do.|O';
		$user_id  = $this->factory->user->create( array( 'user_login' => 'invision-existing-import' ) );

		$wpdb->update( $wpdb->users, array( 'user_pass' => '' ), array( 'ID' => $user_id ) );
		update_user_meta(
			$user_id,
			'_bbp_password',
			array(
				'hash' => 'f3f3c75110ea9a27a1c01e580676997f',
				'salt' => $salt,
			)
		);
		update_option( '_bbp_converter_platform', 'Invision' );
		clean_user_cache( $user_id );

		$_POST['log'] = 'invision-existing-import';
		$_POST['pwd'] = $password;

		bbp_user_maybe_convert_pass();

		$this->assertTrue( wp_check_password( $password, get_userdata( $user_id )->user_pass, $user_id ) );
		$this->assertFalse( metadata_exists( 'user', $user_id, '_bbp_password' ) );
		$this->assertFalse( metadata_exists( 'user', $user_id, '_bbp_class' ) );
	}

	/**
	 * @covers ::bbp_user_maybe_convert_pass
	 * @covers Invision::authenticate_pass
	 * @ticket BBP3684
	 */
	public function test_failed_login_preserves_password_metadata() {
		global $wpdb;

		$user_id  = $this->factory->user->create( array( 'user_login' => 'invision-failed-user' ) );
		$metadata = array(
			'hash' => '5c8315e93cb86e3fcbf9a92673545161',
			'salt' => 'abc',
		);

		$wpdb->update( $wpdb->users, array( 'user_pass' => '' ), array( 'ID' => $user_id ) );
		update_user_meta( $user_id, '_bbp_password', $metadata );
		update_user_meta( $user_id, '_bbp_class', 'Invision' );
		clean_user_cache( $user_id );

		$_POST['log'] = 'invision-failed-user';
		$_POST['pwd'] = 'incorrect';

		bbp_user_maybe_convert_pass();

		$this->assertSame( '', get_userdata( $user_id )->user_pass );
		$this->assertSame( $metadata, get_user_meta( $user_id, '_bbp_password', true ) );
		$this->assertSame( 'Invision', get_user_meta( $user_id, '_bbp_class', true ) );
	}
}
