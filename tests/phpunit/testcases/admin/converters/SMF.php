<?php

/**
 * Tests for the SMF converter.
 *
 * @group converters
 */
class BBP_Tests_Admin_Converters_SMF extends BBP_UnitTestCase {

	/**
	 * @var SMF
	 */
	protected $converter;

	public function setUp(): void {
		parent::setUp();

		bbp_setup_converter();
		require_once BBP_PLUGIN_DIR . 'includes/admin/converters/SMF.php';

		$reflection      = new ReflectionClass( 'SMF' );
		$this->converter = $reflection->newInstanceWithoutConstructor();
	}

	public function tearDown(): void {
		unset( $_POST['log'], $_POST['pwd'] );
		delete_option( '_bbp_converter_platform' );

		parent::tearDown();
	}

	/**
	 * @covers SMF::setup_globals
	 * @ticket BBP3684
	 */
	public function test_password_username_and_class_mappings() {
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
		$login_maps    = wp_filter_object_list( $field_map, array( 'to_fieldname' => 'user_login' ) );
		$email_maps    = wp_filter_object_list( $field_map, array( 'to_fieldname' => 'user_email' ) );
		$class_maps    = wp_filter_object_list( $field_map, array( 'to_fieldname' => '_bbp_class' ) );
		$password_map  = reset( $password_maps );
		$login_map     = reset( $login_maps );
		$email_map     = reset( $email_maps );
		$class_map     = reset( $class_maps );

		$this->assertCount( 1, $password_maps );
		$this->assertCount( 1, $login_maps );
		$this->assertCount( 1, $email_maps );
		$this->assertCount( 1, $class_maps );
		$this->assertSame( 'members', $password_map['from_tablename'] );
		$this->assertSame( 'passwd', $password_map['from_fieldname'] );
		$this->assertSame( 'callback_savepass', $password_map['callback_method'] );
		$this->assertSame( 'member_name', $login_map['from_fieldname'] );
		$this->assertSame( 'email_address', $email_map['from_fieldname'] );
		$this->assertSame( 'SMF', $class_map['default'] );
	}

	/**
	 * @covers SMF::callback_savepass
	 * @ticket BBP3684
	 */
	public function test_callback_savepass_preserves_hash_and_source_username() {
		$this->assertSame(
			array(
				'hash'     => '31640e36c8fafe1aa4b39d14fac4867653c9f186',
				'username' => 'ForumAdmin',
			),
			$this->converter->callback_savepass(
				'31640e36c8fafe1aa4b39d14fac4867653c9f186',
				array( 'member_name' => 'ForumAdmin' )
			)
		);
	}

	/**
	 * @covers SMF::authenticate_pass
	 * @ticket BBP3684
	 */
	public function test_authenticate_pass_with_native_hash() {
		// SMF 2.0.4: sha1( strtolower( 'ForumAdmin' ) . $password ).
		$metadata = serialize(
			array(
				'hash'     => '31640e36c8fafe1aa4b39d14fac4867653c9f186',
				'username' => 'ForumAdmin',
			)
		);

		$this->assertTrue( $this->converter->authenticate_pass( 'Correct Horse & Battery!*', $metadata ) );
		$this->assertFalse( $this->converter->authenticate_pass( 'correct horse & battery!*', $metadata ) );
	}

	/**
	 * @covers SMF::authenticate_pass
	 * @ticket BBP3684
	 */
	public function test_authenticate_pass_uses_ascii_lowercase_source_username() {
		// SMF uses PHP's bytewise strtolower() for the source member name.
		$metadata = serialize(
			array(
				'hash'     => '2c8b4117f002e8d0ce7a30201cc91b2b9991db05',
				'username' => 'MIXEDCase',
			)
		);

		$this->assertTrue( $this->converter->authenticate_pass( 'Backslash\\Quote"Amp&', $metadata ) );
	}

	/**
	 * @covers SMF::authenticate_pass
	 * @ticket BBP3684
	 */
	public function test_authenticate_pass_rejects_invalid_metadata() {
		$this->assertFalse( $this->converter->authenticate_pass( 'password', array() ) );
		$this->assertFalse( $this->converter->authenticate_pass( 'password', serialize( array() ) ) );
		$this->assertFalse( $this->converter->authenticate_pass( 'password', serialize( 'not an array' ) ) );
		$this->assertFalse( $this->converter->authenticate_pass( 'password', serialize( array( 'hash' => array(), 'username' => 'user' ) ) ) );
		$this->assertFalse( $this->converter->authenticate_pass( 'password', serialize( array( 'hash' => 'hash', 'username' => array() ) ) ) );
		$this->assertFalse( $this->converter->authenticate_pass( array(), serialize( array( 'hash' => 'hash', 'username' => 'user' ) ) ) );
	}

	/**
	 * @covers ::bbp_user_maybe_convert_pass
	 * @covers SMF::callback_savepass
	 * @covers SMF::authenticate_pass
	 * @ticket BBP3684
	 */
	public function test_username_login_upgrades_without_source_database() {
		$user_id = $this->create_imported_user( 'smf-login-user', 'smf-login@example.org', true );

		$_POST['log'] = 'smf-login-user';
		$_POST['pwd'] = 'Correct Horse & Battery!*';

		bbp_user_maybe_convert_pass();

		$this->assertTrue( wp_check_password( 'Correct Horse & Battery!*', get_userdata( $user_id )->user_pass, $user_id ) );
		$this->assertFalse( metadata_exists( 'user', $user_id, '_bbp_password' ) );
		$this->assertFalse( metadata_exists( 'user', $user_id, '_bbp_class' ) );
	}

	/**
	 * @covers ::bbp_user_maybe_convert_pass
	 * @covers SMF::authenticate_pass
	 * @ticket BBP3684
	 */
	public function test_email_login_upgrades_without_source_database() {
		$user_id = $this->create_imported_user( 'smf-email-user', 'smf-email@example.org', true );

		$_POST['log'] = 'smf-email@example.org';
		$_POST['pwd'] = 'Correct Horse & Battery!*';

		bbp_user_maybe_convert_pass();

		$this->assertTrue( wp_check_password( 'Correct Horse & Battery!*', get_userdata( $user_id )->user_pass, $user_id ) );
		$this->assertFalse( metadata_exists( 'user', $user_id, '_bbp_password' ) );
		$this->assertFalse( metadata_exists( 'user', $user_id, '_bbp_class' ) );
	}

	/**
	 * @covers ::bbp_user_maybe_convert_pass
	 * @covers SMF::authenticate_pass
	 * @ticket BBP3684
	 */
	public function test_saved_platform_fallback_upgrades_without_class_metadata() {
		$user_id = $this->create_imported_user( 'smf-fallback-user', 'smf-fallback@example.org', false );

		update_option( '_bbp_converter_platform', 'SMF' );

		$_POST['log'] = 'smf-fallback-user';
		$_POST['pwd'] = 'Correct Horse & Battery!*';

		bbp_user_maybe_convert_pass();

		$this->assertTrue( wp_check_password( 'Correct Horse & Battery!*', get_userdata( $user_id )->user_pass, $user_id ) );
		$this->assertFalse( metadata_exists( 'user', $user_id, '_bbp_password' ) );
		$this->assertFalse( metadata_exists( 'user', $user_id, '_bbp_class' ) );
	}

	/**
	 * @covers ::bbp_user_maybe_convert_pass
	 * @covers SMF::authenticate_pass
	 * @ticket BBP3684
	 */
	public function test_failed_login_preserves_password_metadata() {
		$user_id  = $this->create_imported_user( 'smf-failed-user', 'smf-failed@example.org', true );
		$metadata = get_user_meta( $user_id, '_bbp_password', true );

		$_POST['log'] = 'smf-failed-user';
		$_POST['pwd'] = 'incorrect';

		bbp_user_maybe_convert_pass();

		$this->assertSame( '', get_userdata( $user_id )->user_pass );
		$this->assertSame( $metadata, get_user_meta( $user_id, '_bbp_password', true ) );
		$this->assertSame( 'SMF', get_user_meta( $user_id, '_bbp_class', true ) );
	}

	/**
	 * Create a WordPress user with imported SMF password metadata.
	 *
	 * @param string $login      WordPress user login.
	 * @param string $email      WordPress user email.
	 * @param bool   $class_meta Whether to add converter class metadata.
	 * @return int User ID.
	 */
	protected function create_imported_user( $login, $email, $class_meta ) {
		global $wpdb;

		$user_id = $this->factory->user->create(
			array(
				'user_login' => $login,
				'user_email' => $email,
			)
		);

		$wpdb->update( $wpdb->users, array( 'user_pass' => '' ), array( 'ID' => $user_id ) );
		update_user_meta(
			$user_id,
			'_bbp_password',
			$this->converter->callback_savepass(
				'31640e36c8fafe1aa4b39d14fac4867653c9f186',
				array( 'member_name' => 'ForumAdmin' )
			)
		);

		if ( $class_meta ) {
			update_user_meta( $user_id, '_bbp_class', 'SMF' );
		}

		clean_user_cache( $user_id );

		return $user_id;
	}
}
