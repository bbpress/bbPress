<?php

/**
 * Tests for the XMB converter.
 *
 * @group converters
 */
class BBP_Tests_Admin_Converters_XMB extends BBP_UnitTestCase {

	/**
	 * @var XMB
	 */
	protected $converter;

	public function setUp(): void {
		parent::setUp();

		bbp_setup_converter();
		require_once BBP_PLUGIN_DIR . 'includes/admin/converters/XMB.php';

		$reflection      = new ReflectionClass( 'XMB' );
		$this->converter = $reflection->newInstanceWithoutConstructor();
	}

	public function tearDown(): void {
		unset( $_POST['log'], $_POST['pwd'] );
		delete_option( '_bbp_converter_platform' );

		parent::tearDown();
	}

	/**
	 * @covers XMB::setup_globals
	 * @ticket BBP3684
	 */
	public function test_password_upgrade_class_is_mapped_to_user() {
		$this->converter->setup_globals();

		$get_field_map = Closure::bind(
			function( $converter ) {
				return $converter->field_map;
			},
			null,
			'BBP_Converter_Base'
		);
		$field_map     = $get_field_map( $this->converter );
		$mapping       = wp_filter_object_list( $field_map, array( 'to_fieldname' => '_bbp_class' ) );

		$this->assertCount( 1, $mapping );
		$this->assertSame( 'user', reset( $mapping )['to_type'] );
		$this->assertSame( 'XMB', reset( $mapping )['default'] );
	}

	/**
	 * @covers XMB::callback_savepass
	 * @ticket BBP3684
	 */
	public function test_callback_savepass_preserves_hash() {
		$this->assertSame(
			array( 'hash' => '5c8315e93cb86e3fcbf9a92673545161' ),
			$this->converter->callback_savepass( '5c8315e93cb86e3fcbf9a92673545161', array() )
		);
	}

	/**
	 * @covers XMB::authenticate_pass
	 * @ticket BBP3684
	 */
	public function test_authenticate_pass_with_xmb_hash() {
		$pass = serialize( array( 'hash' => '5c8315e93cb86e3fcbf9a92673545161' ) );

		$this->assertTrue( $this->converter->authenticate_pass( 'Correct Horse Battery Staple', $pass ) );
		$this->assertFalse( $this->converter->authenticate_pass( 'incorrect', $pass ) );
	}

	/**
	 * @covers XMB::authenticate_pass
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
	 * @covers XMB::authenticate_pass
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
	 * @covers XMB::authenticate_pass
	 * @ticket BBP3684
	 */
	public function test_callback_pass_upgrades_password_and_removes_converter_metadata() {
		global $wpdb;

		$password = 'Correct Horse Battery Staple';
		$user_id  = $this->factory->user->create( array( 'user_login' => 'xmb-imported-user' ) );
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
		update_user_meta( $user_id, '_bbp_class', 'XMB' );
		clean_user_cache( $user_id );

		$this->converter->callback_pass( 'xmb-imported-user', $password );

		$user = get_userdata( $user_id );

		$this->assertTrue( wp_check_password( $password, $user->user_pass, $user_id ) );
		$this->assertSame( '', get_user_meta( $user_id, '_bbp_password', true ) );
		$this->assertSame( '', get_user_meta( $user_id, '_bbp_class', true ) );
	}

	/**
	 * @covers BBP_Converter_Base::callback_pass
	 * @covers XMB::authenticate_pass
	 * @ticket BBP3684
	 */
	public function test_callback_pass_rejects_wrong_password_and_preserves_converter_metadata() {
		global $wpdb;

		$user_id  = $this->factory->user->create( array( 'user_login' => 'xmb-imported-failure' ) );
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
		update_user_meta( $user_id, '_bbp_class', 'XMB' );
		clean_user_cache( $user_id );

		$this->converter->callback_pass( 'xmb-imported-failure', 'incorrect' );

		$this->assertSame( '', get_userdata( $user_id )->user_pass );
		$this->assertTrue( metadata_exists( 'user', $user_id, '_bbp_password' ) );
		$this->assertTrue( metadata_exists( 'user', $user_id, '_bbp_class' ) );
	}

	/**
	 * @covers ::bbp_user_maybe_convert_pass
	 * @covers XMB::authenticate_pass
	 * @ticket BBP3684
	 */
	public function test_existing_import_without_class_meta_upgrades_from_saved_platform() {
		global $wpdb;

		$password = 'Correct Horse Battery Staple';
		$user_id  = $this->factory->user->create( array( 'user_login' => 'xmb-existing-import' ) );

		$wpdb->update( $wpdb->users, array( 'user_pass' => '' ), array( 'ID' => $user_id ) );
		update_user_meta(
			$user_id,
			'_bbp_password',
			array(
				'hash' => '5c8315e93cb86e3fcbf9a92673545161',
				'salt' => null,
			)
		);
		update_option( '_bbp_converter_platform', 'XMB' );
		clean_user_cache( $user_id );

		$_POST['log'] = 'xmb-existing-import';
		$_POST['pwd'] = $password;

		bbp_user_maybe_convert_pass();

		$this->assertTrue( wp_check_password( $password, get_userdata( $user_id )->user_pass, $user_id ) );
		$this->assertFalse( metadata_exists( 'user', $user_id, '_bbp_password' ) );
		$this->assertFalse( metadata_exists( 'user', $user_id, '_bbp_class' ) );
	}
}
