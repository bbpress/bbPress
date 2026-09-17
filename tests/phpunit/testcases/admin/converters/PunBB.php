<?php

/**
 * Tests for the PunBB converter.
 *
 * @group converters
 */
class BBP_Tests_Admin_Converters_PunBB extends BBP_UnitTestCase {

	/**
	 * @var PunBB
	 */
	protected $converter;

	public function setUp(): void {
		parent::setUp();

		bbp_setup_converter();
		require_once BBP_PLUGIN_DIR . 'includes/admin/converters/PunBB.php';

		$reflection      = new ReflectionClass( 'PunBB' );
		$this->converter = $reflection->newInstanceWithoutConstructor();
	}

	/**
	 * @covers PunBB::setup_globals
	 * @ticket BBP3684
	 */
	public function test_password_upgrade_fields_are_mapped_to_user() {
		$this->converter->setup_globals();

		$get_field_map = Closure::bind(
			function( $converter ) {
				return $converter->field_map;
			},
			null,
			'BBP_Converter_Base'
		);
		$field_map     = $get_field_map( $this->converter );
		$class_mapping = wp_filter_object_list( $field_map, array( 'to_fieldname' => '_bbp_class' ) );
		$salt_mapping  = wp_filter_object_list( $field_map, array( 'from_fieldname' => 'salt' ) );

		$this->assertCount( 1, $class_mapping );
		$this->assertSame( 'user', reset( $class_mapping )['to_type'] );
		$this->assertSame( 'PunBB', reset( $class_mapping )['default'] );
		$this->assertCount( 1, $salt_mapping );
		$this->assertSame( 'user', reset( $salt_mapping )['to_type'] );
		$this->assertSame( '', reset( $salt_mapping )['to_fieldname'] );
	}

	/**
	 * @covers PunBB::callback_savepass
	 * @ticket BBP3684
	 */
	public function test_callback_savepass_preserves_hash_and_salt() {
		$this->assertSame(
			array(
				'hash' => '4da3e69b6b919a4f6e010aaac999a3de40d4f5d3',
				'salt' => 'abc12345'
			),
			$this->converter->callback_savepass(
				'4da3e69b6b919a4f6e010aaac999a3de40d4f5d3',
				array( 'salt' => 'abc12345' )
			)
		);
	}

	/**
	 * @covers PunBB::callback_savepass
	 * @ticket BBP3684
	 */
	public function test_callback_savepass_normalizes_missing_and_null_salts() {
		$hash = '5c8315e93cb86e3fcbf9a92673545161';

		$this->assertSame(
			array( 'hash' => $hash, 'salt' => '' ),
			$this->converter->callback_savepass( $hash, array() )
		);
		$this->assertSame(
			array( 'hash' => $hash, 'salt' => '' ),
			$this->converter->callback_savepass( $hash, array( 'salt' => null ) )
		);
	}

	/**
	 * @covers PunBB::authenticate_pass
	 * @ticket BBP3684
	 */
	public function test_authenticate_pass_with_punbb_1_4_salted_sha1_hash() {
		$pass = serialize(
			array(
				'hash' => '4da3e69b6b919a4f6e010aaac999a3de40d4f5d3',
				'salt' => 'abc12345'
			)
		);

		$this->assertTrue( $this->converter->authenticate_pass( 'Correct Horse Battery Staple', $pass ) );
		$this->assertFalse( $this->converter->authenticate_pass( 'incorrect', $pass ) );
	}

	/**
	 * @covers PunBB::authenticate_pass
	 * @ticket BBP3684
	 */
	public function test_authenticate_pass_with_legacy_unsalted_sha1_hash() {
		$pass = serialize(
			array(
				'hash' => '0bcf1df3cb81df3908d74d46b7fa9dd036b3b3c2',
				'salt' => null
			)
		);

		$this->assertTrue( $this->converter->authenticate_pass( 'Correct Horse Battery Staple', $pass ) );
		$this->assertFalse( $this->converter->authenticate_pass( 'incorrect', $pass ) );
	}

	/**
	 * @covers PunBB::authenticate_pass
	 * @ticket BBP3684
	 */
	public function test_authenticate_pass_with_legacy_md5_hash() {
		$pass = serialize(
			array(
				'hash' => '5c8315e93cb86e3fcbf9a92673545161',
				'salt' => null
			)
		);

		$this->assertTrue( $this->converter->authenticate_pass( 'Correct Horse Battery Staple', $pass ) );
		$this->assertFalse( $this->converter->authenticate_pass( 'incorrect', $pass ) );
		$this->assertTrue(
			$this->converter->authenticate_pass(
				'Correct Horse Battery Staple',
				serialize( array( 'hash' => '5c8315e93cb86e3fcbf9a92673545161' ) )
			)
		);
	}

	/**
	 * @covers PunBB::authenticate_pass
	 * @ticket BBP3684
	 */
	public function test_authenticate_pass_rejects_invalid_metadata() {
		$this->assertFalse( $this->converter->authenticate_pass( 'password', serialize( array() ) ) );
		$this->assertFalse( $this->converter->authenticate_pass( 'password', serialize( 'not an array' ) ) );
		$this->assertFalse( $this->converter->authenticate_pass( 'password', serialize( array( 'hash' => array(), 'salt' => 'salt' ) ) ) );
		$this->assertFalse( $this->converter->authenticate_pass( 'password', serialize( array( 'hash' => 'hash', 'salt' => array() ) ) ) );
	}

	/**
	 * @covers PunBB::callback_savepass
	 * @covers BBP_Converter_Base::callback_pass
	 * @covers PunBB::authenticate_pass
	 * @ticket BBP3684
	 */
	public function test_callback_pass_preserves_backslash_in_salt() {
		global $wpdb;

		$password = 'Correct Horse Battery Staple';
		$salt     = 'ab\\cdefghijk';
		$user_id  = $this->factory->user->create( array( 'user_login' => 'punbb-slashed-salt-user' ) );
		$set_wpdb = Closure::bind(
			function( $converter, $database ) {
				$converter->wpdb = $database;
			},
			null,
			'BBP_Converter_Base'
		);
		$set_wpdb( $this->converter, $wpdb );

		$wpdb->update( $wpdb->users, array( 'user_pass' => '' ), array( 'ID' => $user_id ) );
		update_user_meta(
			$user_id,
			'_bbp_password',
			$this->converter->callback_savepass(
				'f28737d6d7fcd544e926f640d06f766f1a32af4a',
				array( 'salt' => $salt )
			)
		);
		update_user_meta( $user_id, '_bbp_class', 'PunBB' );
		clean_user_cache( $user_id );

		$stored_password = get_user_meta( $user_id, '_bbp_password', true );

		$this->assertSame( $salt, $stored_password['salt'] );

		$this->converter->callback_pass( 'punbb-slashed-salt-user', $password );

		$user = get_userdata( $user_id );

		$this->assertTrue( wp_check_password( $password, $user->user_pass, $user_id ) );
		$this->assertSame( '', get_user_meta( $user_id, '_bbp_password', true ) );
		$this->assertSame( '', get_user_meta( $user_id, '_bbp_class', true ) );
	}

	/**
	 * @covers BBP_Converter_Base::callback_pass
	 * @covers PunBB::authenticate_pass
	 * @ticket BBP3684
	 */
	public function test_callback_pass_upgrades_password_and_removes_converter_metadata() {
		global $wpdb;

		$password = 'Correct Horse Battery Staple';
		$user_id  = $this->factory->user->create( array( 'user_login' => 'punbb-imported-user' ) );
		$set_wpdb = Closure::bind(
			function( $converter, $database ) {
				$converter->wpdb = $database;
			},
			null,
			'BBP_Converter_Base'
		);
		$set_wpdb( $this->converter, $wpdb );

		$wpdb->update( $wpdb->users, array( 'user_pass' => '' ), array( 'ID' => $user_id ) );
		update_user_meta(
			$user_id,
			'_bbp_password',
			array(
				'hash' => '5c8315e93cb86e3fcbf9a92673545161',
				'salt' => null
			)
		);
		update_user_meta( $user_id, '_bbp_class', 'PunBB' );
		clean_user_cache( $user_id );

		$this->converter->callback_pass( 'punbb-imported-user', $password );

		$user = get_userdata( $user_id );

		$this->assertTrue( wp_check_password( $password, $user->user_pass, $user_id ) );
		$this->assertSame( '', get_user_meta( $user_id, '_bbp_password', true ) );
		$this->assertSame( '', get_user_meta( $user_id, '_bbp_class', true ) );
	}
}
