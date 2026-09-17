<?php

/**
 * Tests for the vBulletin 4 converter.
 *
 * @group converters
 */
class BBP_Tests_Admin_Converters_vBulletin extends BBP_UnitTestCase {

	/**
	 * @var vBulletin
	 */
	protected $converter;

	public function setUp(): void {
		parent::setUp();

		bbp_setup_converter();
		require_once BBP_PLUGIN_DIR . 'includes/admin/converters/vBulletin.php';

		$reflection      = new ReflectionClass( 'vBulletin' );
		$this->converter = $reflection->newInstanceWithoutConstructor();
	}

	public function tearDown(): void {
		unset( $_POST['log'], $_POST['pwd'] );
		delete_option( '_bbp_converter_platform' );

		parent::tearDown();
	}

	/**
	 * @covers vBulletin::setup_globals
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
		$salt_maps     = wp_filter_object_list( $field_map, array( 'from_fieldname' => 'salt' ) );
		$password_map  = reset( $password_maps );
		$salt_map      = reset( $salt_maps );

		$this->assertCount( 1, $salt_maps );
		$this->assertSame( 'user', $password_map['from_tablename'] );
		$this->assertSame( 'password', $password_map['from_fieldname'] );
		$this->assertSame( 'callback_savepass', $password_map['callback_method'] );
		$this->assertSame( '', $salt_map['to_fieldname'] );
	}

	/**
	 * @covers vBulletin::callback_savepass
	 * @ticket BBP3684
	 */
	public function test_callback_savepass_slashes_printable_ascii_salt() {
		$salt = 'ab\\c"def';

		$this->assertSame(
			array(
				'hash' => '5c8315e93cb86e3fcbf9a92673545161',
				'salt' => wp_slash( $salt ),
			),
			$this->converter->callback_savepass(
				'5c8315e93cb86e3fcbf9a92673545161',
				array( 'salt' => $salt )
			)
		);
	}

	/**
	 * @covers vBulletin::callback_savepass
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
	 * @covers vBulletin::authenticate_pass
	 * @ticket BBP3684
	 */
	public function test_authenticate_pass_with_vbulletin_hash() {
		$password = 'Correct Horse Battery Staple';
		$salt     = 'ab\\c"def';
		$metadata = serialize(
			array(
				'hash' => md5( md5( $password ) . $salt ),
				'salt' => $salt,
			)
		);

		$this->assertTrue( $this->converter->authenticate_pass( $password, $metadata ) );
		$this->assertFalse( $this->converter->authenticate_pass( 'incorrect', $metadata ) );
	}

	/**
	 * @covers vBulletin::authenticate_pass
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
	 * @covers vBulletin::callback_savepass
	 * @covers vBulletin::authenticate_pass
	 * @ticket BBP3684
	 */
	public function test_email_login_upgrades_escaped_salt_without_source_database() {
		global $wpdb;

		$password = 'Correct Horse Battery Staple';
		$salt     = 'ab\\c"def';
		$user_id  = $this->factory->user->create(
			array(
				'user_login' => 'vbulletin4-imported-user',
				'user_email' => 'vbulletin4-imported@example.org',
			)
		);

		$wpdb->update( $wpdb->users, array( 'user_pass' => '' ), array( 'ID' => $user_id ) );
		update_user_meta(
			$user_id,
			'_bbp_password',
			$this->converter->callback_savepass(
				md5( md5( $password ) . $salt ),
				array( 'salt' => $salt )
			)
		);
		update_user_meta( $user_id, '_bbp_class', 'vBulletin' );
		clean_user_cache( $user_id );

		$this->assertSame( $salt, get_user_meta( $user_id, '_bbp_password', true )['salt'] );

		$_POST['log'] = 'vbulletin4-imported@example.org';
		$_POST['pwd'] = $password;

		bbp_user_maybe_convert_pass();

		$this->assertTrue( wp_check_password( $password, get_userdata( $user_id )->user_pass, $user_id ) );
		$this->assertFalse( metadata_exists( 'user', $user_id, '_bbp_password' ) );
		$this->assertFalse( metadata_exists( 'user', $user_id, '_bbp_class' ) );
	}

	/**
	 * @covers ::bbp_user_maybe_convert_pass
	 * @covers vBulletin::authenticate_pass
	 * @ticket BBP3684
	 */
	public function test_historical_password_metadata_still_upgrades() {
		global $wpdb;

		$password = 'Correct Horse Battery Staple';
		$salt     = 'abc';
		$user_id  = $this->factory->user->create( array( 'user_login' => 'vbulletin4-historical-user' ) );

		$wpdb->update( $wpdb->users, array( 'user_pass' => '' ), array( 'ID' => $user_id ) );
		update_user_meta(
			$user_id,
			'_bbp_password',
			array(
				'hash' => md5( md5( $password ) . $salt ),
				'salt' => $salt,
			)
		);
		update_user_meta( $user_id, '_bbp_salt', $salt );
		update_user_meta( $user_id, '_bbp_class', 'vBulletin' );
		clean_user_cache( $user_id );

		$_POST['log'] = 'vbulletin4-historical-user';
		$_POST['pwd'] = $password;

		bbp_user_maybe_convert_pass();

		$this->assertTrue( wp_check_password( $password, get_userdata( $user_id )->user_pass, $user_id ) );
		$this->assertFalse( metadata_exists( 'user', $user_id, '_bbp_password' ) );
		$this->assertFalse( metadata_exists( 'user', $user_id, '_bbp_class' ) );
		$this->assertSame( $salt, get_user_meta( $user_id, '_bbp_salt', true ) );
	}

	/**
	 * @covers ::bbp_user_maybe_convert_pass
	 * @covers vBulletin::authenticate_pass
	 * @ticket BBP3684
	 */
	public function test_historical_import_without_class_meta_upgrades_from_saved_platform() {
		global $wpdb;

		$password = 'Correct Horse Battery Staple';
		$salt     = 'abc';
		$user_id  = $this->factory->user->create( array( 'user_login' => 'vbulletin4-existing-import' ) );

		$wpdb->update( $wpdb->users, array( 'user_pass' => '' ), array( 'ID' => $user_id ) );
		update_user_meta(
			$user_id,
			'_bbp_password',
			array(
				'hash' => md5( md5( $password ) . $salt ),
				'salt' => $salt,
			)
		);
		update_option( '_bbp_converter_platform', 'vBulletin' );
		clean_user_cache( $user_id );

		$_POST['log'] = 'vbulletin4-existing-import';
		$_POST['pwd'] = $password;

		bbp_user_maybe_convert_pass();

		$this->assertTrue( wp_check_password( $password, get_userdata( $user_id )->user_pass, $user_id ) );
		$this->assertFalse( metadata_exists( 'user', $user_id, '_bbp_password' ) );
		$this->assertFalse( metadata_exists( 'user', $user_id, '_bbp_class' ) );
	}

	/**
	 * @covers ::bbp_user_maybe_convert_pass
	 * @covers vBulletin::authenticate_pass
	 * @ticket BBP3684
	 */
	public function test_failed_login_preserves_password_metadata() {
		global $wpdb;

		$user_id  = $this->factory->user->create( array( 'user_login' => 'vbulletin4-failed-user' ) );
		$metadata = array(
			'hash' => '5c8315e93cb86e3fcbf9a92673545161',
			'salt' => 'abc',
		);

		$wpdb->update( $wpdb->users, array( 'user_pass' => '' ), array( 'ID' => $user_id ) );
		update_user_meta( $user_id, '_bbp_password', $metadata );
		update_user_meta( $user_id, '_bbp_class', 'vBulletin' );
		clean_user_cache( $user_id );

		$_POST['log'] = 'vbulletin4-failed-user';
		$_POST['pwd'] = 'incorrect';

		bbp_user_maybe_convert_pass();

		$this->assertSame( '', get_userdata( $user_id )->user_pass );
		$this->assertSame( $metadata, get_user_meta( $user_id, '_bbp_password', true ) );
		$this->assertSame( 'vBulletin', get_user_meta( $user_id, '_bbp_class', true ) );
	}
}
