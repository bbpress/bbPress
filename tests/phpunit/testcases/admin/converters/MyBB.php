<?php

/**
 * Tests for the MyBB converter.
 *
 * @group converters
 */
class BBP_Tests_Admin_Converters_MyBB extends BBP_UnitTestCase {

	/**
	 * @var MyBB
	 */
	protected $converter;

	public function setUp(): void {
		parent::setUp();

		bbp_setup_converter();
		require_once BBP_PLUGIN_DIR . 'includes/admin/converters/MyBB.php';

		$reflection      = new ReflectionClass( 'MyBB' );
		$this->converter = $reflection->newInstanceWithoutConstructor();
	}

	/**
	 * @covers MyBB::setup_globals
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
		$this->assertSame( 'MyBB', reset( $mapping )['default'] );
	}

	/**
	 * @covers MyBB::callback_savepass
	 * @ticket BBP3684
	 */
	public function test_callback_savepass_preserves_hash_and_salt() {
		$this->assertSame(
			array(
				'hash' => '3313b9decb68474a31612ce91ba02b8f',
				'salt' => 'abc12345'
			),
			$this->converter->callback_savepass(
				'3313b9decb68474a31612ce91ba02b8f',
				array( 'salt' => 'abc12345' )
			)
		);
		$this->assertSame(
			array( 'hash' => 'hash', 'salt' => '' ),
			$this->converter->callback_savepass( 'hash', array() )
		);
	}

	/**
	 * @covers MyBB::callback_savepass
	 * @ticket BBP3684
	 */
	public function test_callback_savepass_survives_user_meta_storage() {
		$user_id  = $this->factory->user->create();
		$metadata = $this->converter->callback_savepass( 'hash', array( 'salt' => "a\\b'" ) );

		update_user_meta( $user_id, '_bbp_password', $metadata );

		$this->assertSame(
			array( 'hash' => 'hash', 'salt' => "a\\b'" ),
			get_user_meta( $user_id, '_bbp_password', true )
		);
	}

	/**
	 * @covers MyBB::authenticate_pass
	 * @ticket BBP3684
	 */
	public function test_authenticate_pass_with_mybb_1_6_hash() {
		$pass = serialize(
			array(
				'hash' => '3313b9decb68474a31612ce91ba02b8f',
				'salt' => 'abc12345'
			)
		);

		$this->assertTrue( $this->converter->authenticate_pass( 'Correct Horse Battery Staple', $pass ) );
		$this->assertFalse( $this->converter->authenticate_pass( 'incorrect', $pass ) );
	}

	/**
	 * @covers MyBB::authenticate_pass
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
	 * @covers MyBB::authenticate_pass
	 * @ticket BBP3684
	 */
	public function test_callback_pass_upgrades_password_and_removes_converter_metadata() {
		global $wpdb;

		$password = 'Correct Horse Battery Staple';
		$user_id  = $this->factory->user->create( array( 'user_login' => 'mybb-imported-user' ) );
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
				'hash' => '3313b9decb68474a31612ce91ba02b8f',
				'salt' => 'abc12345'
			)
		);
		update_user_meta( $user_id, '_bbp_class', 'MyBB' );
		clean_user_cache( $user_id );

		$this->converter->callback_pass( 'mybb-imported-user', $password );

		$user = get_userdata( $user_id );

		$this->assertTrue( wp_check_password( $password, $user->user_pass, $user_id ) );
		$this->assertSame( '', get_user_meta( $user_id, '_bbp_password', true ) );
		$this->assertSame( '', get_user_meta( $user_id, '_bbp_class', true ) );
	}
}
