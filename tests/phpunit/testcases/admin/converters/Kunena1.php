<?php

/**
 * Tests for the Kunena 1 converter.
 *
 * @group converters
 */
class BBP_Tests_Admin_Converters_Kunena1 extends BBP_UnitTestCase {

	/**
	 * @var Kunena1
	 */
	protected $converter;

	public function setUp(): void {
		parent::setUp();

		bbp_setup_converter();
		require_once BBP_PLUGIN_DIR . 'includes/admin/converters/Kunena1.php';

		$reflection      = new ReflectionClass( 'Kunena1' );
		$this->converter = $reflection->newInstanceWithoutConstructor();
	}

	public function tearDown(): void {
		unset( $_POST['log'], $_POST['pwd'] );
		delete_option( '_bbp_converter_platform' );

		parent::tearDown();
	}


	/**
	 * @covers Kunena1::setup_globals
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
		$this->assertSame( 'Kunena1', reset( $mapping )['default'] );
	}

	/**
	 * @covers Kunena1::callback_savepass
	 * @ticket BBP3684
	 */
	public function test_callback_savepass_preserves_joomla_hash() {
		$this->assertSame(
			array( 'hash' => 'd2064d358136996bd22421584a7cb33e:trd7TvKHx6dMeoMmBVxYmg0vuXEA4199' ),
			$this->converter->callback_savepass( 'd2064d358136996bd22421584a7cb33e:trd7TvKHx6dMeoMmBVxYmg0vuXEA4199', array() )
		);
	}

	/**
	 * @covers Kunena1::authenticate_pass
	 * @ticket BBP3684
	 */
	public function test_authenticate_pass_with_joomla_hash() {
		// Joomla 1.5's documented administrator recovery hash for "secret".
		$pass = serialize( array( 'hash' => 'd2064d358136996bd22421584a7cb33e:trd7TvKHx6dMeoMmBVxYmg0vuXEA4199' ) );

		$this->assertTrue( $this->converter->authenticate_pass( 'secret', $pass ) );
		$this->assertFalse( $this->converter->authenticate_pass( 'incorrect', $pass ) );
	}

	/**
	 * @covers Kunena1::authenticate_pass
	 * @ticket BBP3684
	 */
	public function test_authenticate_pass_with_legacy_unsalted_joomla_hash() {
		// Joomla 1.5 also accepts and upgrades legacy unsalted MD5 hashes.
		$pass = serialize( array( 'hash' => '5ebe2294ecd0e0f08eab7690d2a6ee69' ) );

		$this->assertTrue( $this->converter->authenticate_pass( 'secret', $pass ) );
		$this->assertFalse( $this->converter->authenticate_pass( 'incorrect', $pass ) );
	}

	/**
	 * @covers Kunena1::authenticate_pass
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
	 * @covers Kunena1::authenticate_pass
	 * @ticket BBP3684
	 */
	public function test_callback_pass_upgrades_password_and_removes_converter_metadata() {
		global $wpdb;

		$password = 'Correct Horse Battery Staple';
		$user_id  = $this->factory->user->create( array( 'user_login' => 'kunena1-imported-user' ) );
		$set_wpdb = Closure::bind(
			function( $converter, $database ) {
				$converter->wpdb = $database;
			},
			null,
			'BBP_Converter_Base'
		);
		$set_wpdb( $this->converter, $wpdb );

		$wpdb->update( $wpdb->users, array( 'user_pass' => '' ), array( 'ID' => $user_id ) );
		update_user_meta( $user_id, '_bbp_password', array( 'hash' => '241a794170e288fccc5a82f94f51225f:a1B2' ) );
		update_user_meta( $user_id, '_bbp_class', 'Kunena1' );
		clean_user_cache( $user_id );

		$this->converter->callback_pass( 'kunena1-imported-user', $password );

		$user = get_userdata( $user_id );

		$this->assertTrue( wp_check_password( $password, $user->user_pass, $user_id ) );
		$this->assertSame( '', get_user_meta( $user_id, '_bbp_password', true ) );
		$this->assertSame( '', get_user_meta( $user_id, '_bbp_class', true ) );
	}

	/**
	 * @covers BBP_Converter_Base::callback_pass
	 * @covers Kunena1::authenticate_pass
	 * @ticket BBP3684
	 */
	public function test_callback_pass_rejects_wrong_password_and_preserves_converter_metadata() {
		global $wpdb;

		$user_id  = $this->factory->user->create( array( 'user_login' => 'kunena1-imported-failure' ) );
		$set_wpdb = Closure::bind(
			function( $converter, $database ) {
				$converter->wpdb = $database;
			},
			null,
			'BBP_Converter_Base'
		);
		$set_wpdb( $this->converter, $wpdb );

		$wpdb->update( $wpdb->users, array( 'user_pass' => '' ), array( 'ID' => $user_id ) );
		update_user_meta( $user_id, '_bbp_password', array( 'hash' => '241a794170e288fccc5a82f94f51225f:a1B2' ) );
		update_user_meta( $user_id, '_bbp_class', 'Kunena1' );
		clean_user_cache( $user_id );

		$this->converter->callback_pass( 'kunena1-imported-failure', 'incorrect' );

		$this->assertSame( '', get_userdata( $user_id )->user_pass );
		$this->assertTrue( metadata_exists( 'user', $user_id, '_bbp_password' ) );
		$this->assertTrue( metadata_exists( 'user', $user_id, '_bbp_class' ) );
	}

	/**
	 * @covers ::bbp_user_maybe_convert_pass
	 * @covers Kunena1::authenticate_pass
	 * @ticket BBP3684
	 */
	public function test_existing_import_without_class_meta_upgrades_from_saved_platform() {
		global $wpdb;

		$password = 'Correct Horse Battery Staple';
		$user_id  = $this->factory->user->create( array( 'user_login' => 'kunena1-existing-import' ) );

		$wpdb->update( $wpdb->users, array( 'user_pass' => '' ), array( 'ID' => $user_id ) );
		update_user_meta( $user_id, '_bbp_password', array( 'hash' => '241a794170e288fccc5a82f94f51225f:a1B2' ) );
		update_option( '_bbp_converter_platform', 'Kunena1' );
		clean_user_cache( $user_id );

		$_POST['log'] = 'kunena1-existing-import';
		$_POST['pwd'] = $password;

		bbp_user_maybe_convert_pass();

		$this->assertTrue( wp_check_password( $password, get_userdata( $user_id )->user_pass, $user_id ) );
		$this->assertFalse( metadata_exists( 'user', $user_id, '_bbp_password' ) );
		$this->assertFalse( metadata_exists( 'user', $user_id, '_bbp_class' ) );
	}

}
