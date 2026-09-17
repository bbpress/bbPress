<?php

/**
 * Tests for the e107 v1 converter.
 *
 * @group converters
 */
class BBP_Tests_Admin_Converters_e107v1 extends BBP_UnitTestCase {

	/**
	 * @var e107v1
	 */
	protected $converter;

	public function setUp(): void {
		parent::setUp();

		bbp_setup_converter();
		require_once BBP_PLUGIN_DIR . 'includes/admin/converters/e107v1.php';

		$reflection      = new ReflectionClass( 'e107v1' );
		$this->converter = $reflection->newInstanceWithoutConstructor();
	}

	public function tearDown(): void {
		unset( $_POST['log'], $_POST['pwd'] );
		delete_option( '_bbp_converter_platform' );

		parent::tearDown();
	}

	/**
	 * @covers e107v1::callback_savepass
	 * @ticket BBP3684
	 */
	public function test_callback_savepass_preserves_hash_without_salt() {
		$this->assertSame(
			array( 'hash' => '5c8315e93cb86e3fcbf9a92673545161' ),
			$this->converter->callback_savepass( '5c8315e93cb86e3fcbf9a92673545161', array() )
		);
	}

	/**
	 * @covers e107v1::authenticate_pass
	 * @ticket BBP3684
	 */
	public function test_authenticate_pass_with_e107_hash() {
		$pass = serialize( array( 'hash' => '5c8315e93cb86e3fcbf9a92673545161' ) );

		$this->assertTrue( $this->converter->authenticate_pass( 'Correct Horse Battery Staple', $pass ) );
		$this->assertFalse( $this->converter->authenticate_pass( 'incorrect', $pass ) );
	}

	/**
	 * @covers e107v1::authenticate_pass
	 * @ticket BBP3684
	 */
	public function test_authenticate_pass_with_existing_import_metadata_shape() {
		$pass = serialize(
			array(
				'hash' => '5c8315e93cb86e3fcbf9a92673545161',
				'salt' => null,
			)
		);

		$this->assertTrue( $this->converter->authenticate_pass( 'Correct Horse Battery Staple', $pass ) );
	}

	/**
	 * @covers e107v1::authenticate_pass
	 * @ticket BBP3684
	 */
	public function test_authenticate_pass_with_e107_utf8_compatibility_hash() {
		if ( ! function_exists( 'mb_convert_encoding' ) || ! function_exists( 'mb_substitute_character' ) ) {
			$this->markTestSkipped( 'The mbstring extension is not available.' );
		}

		$pass = serialize( array( 'hash' => '0126cf1f6e0dba240af4c5537ca51d0e' ) );

		$this->assertTrue( $this->converter->authenticate_pass( 'pässword', $pass ) );
		$this->assertFalse( $this->converter->authenticate_pass( 'incorrect', $pass ) );
	}

	/**
	 * @covers e107v1::authenticate_pass
	 * @ticket BBP3684
	 */
	public function test_authenticate_pass_pins_e107_utf8_substitution_character() {
		if ( ! function_exists( 'mb_convert_encoding' ) || ! function_exists( 'mb_substitute_character' ) ) {
			$this->markTestSkipped( 'The mbstring extension is not available.' );
		}

		$substitute_character = mb_substitute_character();
		$pass                 = serialize( array( 'hash' => 'b2bdc1ff1a122cdd1cab08a6eaf09898' ) );

		mb_substitute_character( 'none' );

		try {
			$this->assertTrue( $this->converter->authenticate_pass( 'pass😀word', $pass ) );
		} finally {
			mb_substitute_character( $substitute_character );
		}
	}

	/**
	 * @covers e107v1::authenticate_pass
	 * @ticket BBP3684
	 */
	public function test_authenticate_pass_rejects_invalid_metadata() {
		$this->assertFalse( $this->converter->authenticate_pass( 'password', serialize( array() ) ) );
		$this->assertFalse( $this->converter->authenticate_pass( 'password', serialize( 'not an array' ) ) );
		$this->assertFalse( $this->converter->authenticate_pass( 'password', serialize( array( 'hash' => array() ) ) ) );
		$this->assertFalse( $this->converter->authenticate_pass( array(), serialize( array( 'hash' => 'hash' ) ) ) );
	}

	/**
	 * @covers BBP_Converter_Base::callback_pass
	 * @covers e107v1::authenticate_pass
	 * @ticket BBP3684
	 */
	public function test_callback_pass_upgrades_password_and_removes_converter_metadata() {
		global $wpdb;

		$password = 'Correct Horse Battery Staple';
		$user_id  = $this->factory->user->create( array( 'user_login' => 'e107-imported-user' ) );
		$set_wpdb = Closure::bind(
			function( $converter, $database ) {
				$converter->wpdb = $database;
			},
			null,
			'BBP_Converter_Base'
		);
		$set_wpdb( $this->converter, $wpdb );

		$wpdb->update( $wpdb->users, array( 'user_pass' => '' ), array( 'ID' => $user_id ) );
		update_user_meta( $user_id, '_bbp_password', array( 'hash' => '5c8315e93cb86e3fcbf9a92673545161' ) );
		update_user_meta( $user_id, '_bbp_class', 'e107v1' );
		clean_user_cache( $user_id );

		$this->converter->callback_pass( 'e107-imported-user', $password );

		$user = get_userdata( $user_id );

		$this->assertTrue( wp_check_password( $password, $user->user_pass, $user_id ) );
		$this->assertSame( '', get_user_meta( $user_id, '_bbp_password', true ) );
		$this->assertSame( '', get_user_meta( $user_id, '_bbp_class', true ) );
	}

	/**
	 * @covers BBP_Converter_Base::callback_pass
	 * @covers e107v1::authenticate_pass
	 * @ticket BBP3684
	 */
	public function test_callback_pass_rejects_wrong_password_and_preserves_converter_metadata() {
		global $wpdb;

		$user_id  = $this->factory->user->create( array( 'user_login' => 'e107-imported-failure' ) );
		$set_wpdb = Closure::bind(
			function( $converter, $database ) {
				$converter->wpdb = $database;
			},
			null,
			'BBP_Converter_Base'
		);
		$set_wpdb( $this->converter, $wpdb );

		$wpdb->update( $wpdb->users, array( 'user_pass' => '' ), array( 'ID' => $user_id ) );
		update_user_meta( $user_id, '_bbp_password', array( 'hash' => '5c8315e93cb86e3fcbf9a92673545161' ) );
		update_user_meta( $user_id, '_bbp_class', 'e107v1' );
		clean_user_cache( $user_id );

		$this->converter->callback_pass( 'e107-imported-failure', 'incorrect' );

		$this->assertSame( '', get_userdata( $user_id )->user_pass );
		$this->assertTrue( metadata_exists( 'user', $user_id, '_bbp_password' ) );
		$this->assertTrue( metadata_exists( 'user', $user_id, '_bbp_class' ) );
	}

	/**
	 * @covers ::bbp_user_maybe_convert_pass
	 * @covers e107v1::authenticate_pass
	 * @ticket BBP3684
	 */
	public function test_existing_import_without_class_meta_upgrades_from_saved_platform() {
		global $wpdb;

		$password = 'Correct Horse Battery Staple';
		$user_id  = $this->factory->user->create( array( 'user_login' => 'e107-existing-import' ) );

		$wpdb->update( $wpdb->users, array( 'user_pass' => '' ), array( 'ID' => $user_id ) );
		update_user_meta(
			$user_id,
			'_bbp_password',
			array(
				'hash' => '5c8315e93cb86e3fcbf9a92673545161',
				'salt' => null,
			)
		);
		update_option( '_bbp_converter_platform', 'e107v1' );
		clean_user_cache( $user_id );

		$_POST['log'] = 'e107-existing-import';
		$_POST['pwd'] = $password;

		bbp_user_maybe_convert_pass();

		$this->assertTrue( wp_check_password( $password, get_userdata( $user_id )->user_pass, $user_id ) );
		$this->assertFalse( metadata_exists( 'user', $user_id, '_bbp_password' ) );
		$this->assertFalse( metadata_exists( 'user', $user_id, '_bbp_class' ) );
	}
}
