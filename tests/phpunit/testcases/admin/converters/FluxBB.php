<?php

/**
 * Tests for the FluxBB converter.
 *
 * @group converters
 */
class BBP_Tests_Admin_Converters_FluxBB extends BBP_UnitTestCase {

	/**
	 * @var FluxBB
	 */
	protected $converter;

	public function setUp(): void {
		parent::setUp();

		bbp_setup_converter();
		require_once BBP_PLUGIN_DIR . 'includes/admin/converters/FluxBB.php';

		$reflection      = new ReflectionClass( 'FluxBB' );
		$this->converter = $reflection->newInstanceWithoutConstructor();
	}

	/**
	 * @covers FluxBB::setup_globals
	 * @ticket BBP3684
	 */
	public function test_password_upgrade_fields_are_mapped_for_standard_schema() {
		$this->converter->setup_globals();

		$field_map     = $this->get_field_map( $this->converter );
		$class_mapping = wp_filter_object_list( $field_map, array( 'to_fieldname' => '_bbp_class' ) );
		$salt_mapping  = wp_filter_object_list( $field_map, array( 'from_fieldname' => 'salt' ) );

		$this->assertCount( 1, $class_mapping );
		$this->assertSame( 'user', reset( $class_mapping )['to_type'] );
		$this->assertSame( 'FluxBB', reset( $class_mapping )['default'] );
		$this->assertCount( 0, $salt_mapping );
	}

	/**
	 * @covers FluxBB::setup_globals
	 * @ticket BBP3684
	 */
	public function test_legacy_salt_is_mapped_when_source_field_exists() {
		$source_db = new class() {
			public $prefix = 'flux_';

			public function get_var( $query ) {
				return "SHOW COLUMNS FROM flux_users LIKE 'salt'" === $query
					? 'salt'
					: null;
			}
		};
		$set_opdb  = Closure::bind(
			function( $converter, $database ) {
				$converter->opdb = $database;
			},
			null,
			'BBP_Converter_Base'
		);
		$set_opdb( $this->converter, $source_db );
		$this->converter->setup_globals();

		$field_map    = $this->get_field_map( $this->converter );
		$salt_mapping = wp_filter_object_list( $field_map, array( 'from_fieldname' => 'salt' ) );

		$this->assertCount( 1, $salt_mapping );
		$this->assertSame( 'user', reset( $salt_mapping )['to_type'] );
		$this->assertSame( '', reset( $salt_mapping )['to_fieldname'] );
	}

	/**
	 * @covers FluxBB::callback_savepass
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
	 * @covers FluxBB::callback_savepass
	 * @ticket BBP3684
	 */
	public function test_callback_savepass_normalizes_missing_and_null_salts() {
		$hash = '0bcf1df3cb81df3908d74d46b7fa9dd036b3b3c2';

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
	 * @covers FluxBB::authenticate_pass
	 * @ticket BBP3684
	 */
	public function test_authenticate_pass_with_fluxbb_1_5_sha1_hash() {
		$pass = serialize(
			array(
				'hash' => '0bcf1df3cb81df3908d74d46b7fa9dd036b3b3c2',
				'salt' => ''
			)
		);

		$this->assertTrue( $this->converter->authenticate_pass( 'Correct Horse Battery Staple', $pass ) );
		$this->assertFalse( $this->converter->authenticate_pass( 'incorrect', $pass ) );
	}

	/**
	 * @covers FluxBB::authenticate_pass
	 * @ticket BBP3684
	 */
	public function test_authenticate_pass_treats_null_salt_as_unsalted() {
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
	 * @covers FluxBB::authenticate_pass
	 * @ticket BBP3684
	 */
	public function test_authenticate_pass_with_fluxbb_1_3_salted_sha1_hash() {
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
	 * @covers FluxBB::authenticate_pass
	 * @ticket BBP3684
	 */
	public function test_authenticate_pass_with_fluxbb_1_2_md5_hash() {
		$pass = serialize(
			array(
				'hash' => '5c8315e93cb86e3fcbf9a92673545161',
				'salt' => ''
			)
		);

		$this->assertTrue( $this->converter->authenticate_pass( 'Correct Horse Battery Staple', $pass ) );
		$this->assertFalse( $this->converter->authenticate_pass( 'incorrect', $pass ) );
	}

	/**
	 * @covers FluxBB::authenticate_pass
	 * @ticket BBP3684
	 */
	public function test_authenticate_pass_rejects_invalid_metadata() {
		$this->assertFalse( $this->converter->authenticate_pass( 'password', serialize( array() ) ) );
		$this->assertFalse( $this->converter->authenticate_pass( 'password', serialize( 'not an array' ) ) );
		$this->assertFalse( $this->converter->authenticate_pass( 'password', serialize( array( 'hash' => array(), 'salt' => 'salt' ) ) ) );
		$this->assertFalse( $this->converter->authenticate_pass( 'password', serialize( array( 'hash' => 'hash', 'salt' => array() ) ) ) );
	}

	/**
	 * @covers BBP_Converter_Base::callback_pass
	 * @covers FluxBB::authenticate_pass
	 * @ticket BBP3684
	 */
	public function test_callback_pass_upgrades_password_and_removes_converter_metadata() {
		global $wpdb;

		$password = 'Correct Horse Battery Staple';
		$user_id  = $this->factory->user->create( array( 'user_login' => 'fluxbb-imported-user' ) );
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
				'hash' => '0bcf1df3cb81df3908d74d46b7fa9dd036b3b3c2',
				'salt' => ''
			)
		);
		update_user_meta( $user_id, '_bbp_class', 'FluxBB' );
		clean_user_cache( $user_id );

		$this->converter->callback_pass( 'fluxbb-imported-user', $password );

		$user = get_userdata( $user_id );

		$this->assertTrue( wp_check_password( $password, $user->user_pass, $user_id ) );
		$this->assertSame( '', get_user_meta( $user_id, '_bbp_password', true ) );
		$this->assertSame( '', get_user_meta( $user_id, '_bbp_class', true ) );
	}

	/**
	 * Get a converter's protected field map.
	 *
	 * @param FluxBB $converter Converter instance.
	 * @return array Converter field map.
	 */
	private function get_field_map( $converter ) {
		$get_field_map = Closure::bind(
			function( $instance ) {
				return $instance->field_map;
			},
			null,
			'BBP_Converter_Base'
		);

		return $get_field_map( $converter );
	}
}
