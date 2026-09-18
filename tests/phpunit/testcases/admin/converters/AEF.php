<?php

/**
 * Tests for the AEF converter.
 *
 * @group converters
 */
class BBP_Tests_Admin_Converters_AEF extends BBP_UnitTestCase {

	/**
	 * @var AEF
	 */
	protected $converter;

	public function setUp(): void {
		parent::setUp();

		bbp_setup_converter();
		require_once BBP_PLUGIN_DIR . 'includes/admin/converters/AEF.php';

		$reflection      = new ReflectionClass( 'AEF' );
		$this->converter = $reflection->newInstanceWithoutConstructor();
	}

	/**
	 * @covers AEF::setup_globals
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
		$this->assertSame( 'AEF', reset( $mapping )['default'] );
	}

	/**
	 * @covers AEF::callback_savepass
	 * @ticket BBP3684
	 */
	public function test_callback_savepass_preserves_hash_and_salt() {
		$this->assertSame(
			array(
				'hash' => 'ce13d9cdc786eb0d8e78f40e4bc7fab3',
				'salt' => 'a1B2'
			),
			$this->converter->callback_savepass(
				'ce13d9cdc786eb0d8e78f40e4bc7fab3',
				array( 'salt' => 'a1B2' )
			)
		);
		$this->assertSame(
			array( 'hash' => 'hash', 'salt' => '' ),
			$this->converter->callback_savepass( 'hash', array() )
		);
	}

	/**
	 * @covers AEF::callback_savepass
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
	 * @covers AEF::authenticate_pass
	 * @ticket BBP3684
	 */
	public function test_authenticate_pass_with_aef_1_0_9_hash() {
		// Derived from AEF 1.0.9's htmlizer(), inputsec(), and registration hash.
		$pass = serialize(
			array(
				'hash' => '6075d1083236a388577bbf90a0b47573',
				'salt' => 'a1B2'
			)
		);

		$this->assertTrue( $this->converter->authenticate_pass( "P'a\\ss<&", $pass ) );
		$this->assertFalse( $this->converter->authenticate_pass( 'incorrect', $pass ) );
	}

	/**
	 * @covers AEF::authenticate_pass
	 * @ticket BBP3684
	 */
	public function test_authenticate_pass_supports_both_aef_charsets() {
		$pass = serialize(
			array(
				'hash' => 'bc5210da8563d7a99842a2eb2072191d',
				'salt' => 'a1B2'
			)
		);

		$this->assertTrue( $this->converter->authenticate_pass( "caf\xE9", $pass ) );
		$this->assertTrue( $this->converter->authenticate_pass( 'café', $pass ) );
	}

	/**
	 * @covers AEF::authenticate_pass
	 * @ticket BBP3684
	 */
	public function test_authenticate_pass_does_not_collapse_invalid_utf8_to_empty() {
		$pass = serialize(
			array(
				'hash' => '4c40dcd0c427909b7ccd2aa703c0626a',
				'salt' => 'a1B2'
			)
		);

		$this->assertFalse( $this->converter->authenticate_pass( "\xC3", $pass ) );
	}

	/**
	 * @covers AEF::authenticate_pass
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
	 * @covers BBP_Converter_Base::callback_pass
	 * @covers AEF::authenticate_pass
	 * @ticket BBP3684
	 */
	public function test_callback_pass_upgrades_password_and_removes_converter_metadata() {
		global $wpdb;

		$password = 'Correct Horse Battery Staple';
		$user_id  = $this->factory->user->create( array( 'user_login' => 'aef-imported-user' ) );
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
				'hash' => 'ce13d9cdc786eb0d8e78f40e4bc7fab3',
				'salt' => 'a1B2'
			)
		);
		update_user_meta( $user_id, '_bbp_class', 'AEF' );
		clean_user_cache( $user_id );

		$this->converter->callback_pass( 'aef-imported-user', $password );

		$user = get_userdata( $user_id );

		$this->assertTrue( wp_check_password( $password, $user->user_pass, $user_id ) );
		$this->assertSame( '', get_user_meta( $user_id, '_bbp_password', true ) );
		$this->assertSame( '', get_user_meta( $user_id, '_bbp_class', true ) );
	}

	/**
	 * @covers BBP_Converter_Base::callback_pass
	 * @covers AEF::authenticate_pass
	 * @ticket BBP3684
	 */
	public function test_callback_pass_rejects_wrong_password_and_preserves_converter_metadata() {
		global $wpdb;

		$user_id  = $this->factory->user->create( array( 'user_login' => 'aef-imported-failure' ) );
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
				'hash' => 'ce13d9cdc786eb0d8e78f40e4bc7fab3',
				'salt' => 'a1B2'
			)
		);
		update_user_meta( $user_id, '_bbp_class', 'AEF' );
		clean_user_cache( $user_id );

		$this->converter->callback_pass( 'aef-imported-failure', 'incorrect' );

		$this->assertSame( '', get_userdata( $user_id )->user_pass );
		$this->assertTrue( metadata_exists( 'user', $user_id, '_bbp_password' ) );
		$this->assertTrue( metadata_exists( 'user', $user_id, '_bbp_class' ) );
	}
}
