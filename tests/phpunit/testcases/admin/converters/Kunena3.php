<?php

/**
 * Tests for the Kunena 3 converter.
 *
 * @group converters
 */
class BBP_Tests_Admin_Converters_Kunena3 extends BBP_UnitTestCase {

	/**
	 * @var Kunena3
	 */
	protected $converter;

	public function setUp(): void {
		parent::setUp();

		bbp_setup_converter();
		require_once BBP_PLUGIN_DIR . 'includes/admin/converters/Kunena3.php';

		$reflection      = new ReflectionClass( 'Kunena3' );
		$this->converter = $reflection->newInstanceWithoutConstructor();
	}

	public function tearDown(): void {
		unset( $_POST['log'], $_POST['pwd'] );
		delete_option( '_bbp_converter_platform' );

		parent::tearDown();
	}

	/**
	 * @covers Kunena3::setup_globals
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
		$this->assertSame( 'Kunena3', reset( $mapping )['default'] );
	}

	/**
	 * @covers Kunena3::callback_savepass
	 * @ticket BBP3684
	 */
	public function test_callback_savepass_preserves_joomla_hash() {
		$this->assertSame(
			array( 'hash' => 'fb7b0a16d7e0e6706c0f962832e1fdd8:vQnUrofbvGRcBR6l502Bt8nioKj8MObh' ),
			$this->converter->callback_savepass( 'fb7b0a16d7e0e6706c0f962832e1fdd8:vQnUrofbvGRcBR6l502Bt8nioKj8MObh', array() )
		);
	}

	/**
	 * @covers Kunena3::authenticate_pass
	 * @ticket BBP3684
	 */
	public function test_authenticate_pass_with_legacy_joomla_md5_hash() {
		$pass = serialize( array( 'hash' => 'fb7b0a16d7e0e6706c0f962832e1fdd8:vQnUrofbvGRcBR6l502Bt8nioKj8MObh' ) );

		$this->assertTrue( $this->converter->authenticate_pass( 'mySuperSecretPassword', $pass ) );
		$this->assertFalse( $this->converter->authenticate_pass( 'incorrect', $pass ) );
	}

	/**
	 * @covers Kunena3::authenticate_pass
	 * @ticket BBP3684
	 */
	public function test_authenticate_pass_with_existing_import_metadata_shape() {
		$pass = serialize(
			array(
				'hash' => 'fb7b0a16d7e0e6706c0f962832e1fdd8:vQnUrofbvGRcBR6l502Bt8nioKj8MObh',
				'salt' => null,
			)
		);

		$this->assertTrue( $this->converter->authenticate_pass( 'mySuperSecretPassword', $pass ) );
	}

	/**
	 * @covers Kunena3::authenticate_pass
	 * @ticket BBP3684
	 */
	public function test_authenticate_pass_with_legacy_unsalted_joomla_md5_hash() {
		$pass = serialize( array( 'hash' => '693560686f4d591d8dd5e34006442061' ) );

		$this->assertTrue( $this->converter->authenticate_pass( 'mySuperSecretPassword', $pass ) );
		$this->assertFalse( $this->converter->authenticate_pass( 'incorrect', $pass ) );
	}

	/**
	 * @covers Kunena3::authenticate_pass
	 * @ticket BBP3684
	 */
	public function test_authenticate_pass_with_legacy_empty_salt_delimiter() {
		$pass = serialize( array( 'hash' => '098f6bcd4621d373cade4e832627b4f6:' ) );

		$this->assertTrue( $this->converter->authenticate_pass( 'test', $pass ) );
		$this->assertFalse( $this->converter->authenticate_pass( 'incorrect', $pass ) );
	}

	/**
	 * @covers Kunena3::authenticate_pass
	 * @ticket BBP3684
	 */
	public function test_authenticate_pass_with_legacy_joomla_sha256_hash() {
		$pass = serialize( array( 'hash' => '{SHA256}972c5f5b845306847cb4bf941b7a683f1a828f48c46abef8b9ae4dac9798b1d5:oeLpBZ2sFJwLZmm4' ) );

		$this->assertTrue( $this->converter->authenticate_pass( 'mySuperSecretPassword', $pass ) );
		$this->assertFalse( $this->converter->authenticate_pass( 'incorrect', $pass ) );
	}

	/**
	 * @covers Kunena3::authenticate_pass
	 * @ticket BBP3684
	 */
	public function test_authenticate_pass_with_phpass_hash() {
		$pass = serialize( array( 'hash' => '$P$D6vpNa203LlaQUah3KcVQIhgFZ4E6o1' ) );

		$this->assertTrue( $this->converter->authenticate_pass( 'mySuperSecretPassword', $pass ) );
		$this->assertFalse( $this->converter->authenticate_pass( 'incorrect', $pass ) );
	}

	/**
	 * @covers Kunena3::authenticate_pass
	 * @ticket BBP3684
	 */
	public function test_authenticate_pass_with_bcrypt_hash() {
		$pass = serialize( array( 'hash' => '$2y$10$0GfV1d.dfYvWu83ZKFD4surhsaRpVjUZqhG9bShmPcSnmqwCes/lC' ) );

		$this->assertTrue( $this->converter->authenticate_pass( 'mySuperSecretPassword', $pass ) );
		$this->assertFalse( $this->converter->authenticate_pass( 'incorrect', $pass ) );
	}

	/**
	 * @covers Kunena3::authenticate_pass
	 * @ticket BBP3684
	 */
	public function test_authenticate_pass_with_argon2i_hash() {
		if ( ! defined( 'PASSWORD_ARGON2I' ) ) {
			$this->markTestSkipped( 'Argon2i is not supported by this PHP build.' );
		}

		$pass = serialize( array( 'hash' => '$argon2i$v=19$m=8192,t=1,p=1$eDNrdXR4LlRQREZDNXVKUw$7APtVLu3iGPNgHpc1YnjK4NHPGnqmjq9geAL5d+EB4U' ) );

		$this->assertTrue( $this->converter->authenticate_pass( 'Correct Horse Battery Staple', $pass ) );
		$this->assertFalse( $this->converter->authenticate_pass( 'incorrect', $pass ) );
	}

	/**
	 * @covers Kunena3::authenticate_pass
	 * @ticket BBP3684
	 */
	public function test_authenticate_pass_with_argon2id_hash() {
		if ( ! defined( 'PASSWORD_ARGON2ID' ) ) {
			$this->markTestSkipped( 'Argon2id is not supported by this PHP build.' );
		}

		$pass = serialize( array( 'hash' => '$argon2id$v=19$m=8192,t=1,p=1$bWYubFBHS2RwSFJlaVdHeA$RTUSaxYrx7eiZMjd5MgL1Q6ADlGKxoyYceR6YybCEb0' ) );

		$this->assertTrue( $this->converter->authenticate_pass( 'Correct Horse Battery Staple', $pass ) );
		$this->assertFalse( $this->converter->authenticate_pass( 'incorrect', $pass ) );
	}

	/**
	 * @covers Kunena3::authenticate_pass
	 * @ticket BBP3684
	 */
	public function test_authenticate_pass_rejects_invalid_metadata() {
		$this->assertFalse( $this->converter->authenticate_pass( 'password', serialize( array() ) ) );
		$this->assertFalse( $this->converter->authenticate_pass( 'password', serialize( 'not an array' ) ) );
		$this->assertFalse( $this->converter->authenticate_pass( 'password', serialize( array( 'hash' => array() ) ) ) );
		$this->assertFalse( $this->converter->authenticate_pass( array(), serialize( array( 'hash' => 'hash' ) ) ) );
	}

	/**
	 * @covers Kunena3::authenticate_pass
	 * @ticket BBP3684
	 */
	public function test_authenticate_pass_rejects_non_joomla_multi_colon_hash() {
		$pass = serialize( array( 'hash' => '813b4edd3038d4cbbd07827737b29527:salt:extra' ) );

		$this->assertFalse( $this->converter->authenticate_pass( 'secret', $pass ) );
	}

	/**
	 * @covers BBP_Converter_Base::callback_pass
	 * @covers Kunena3::authenticate_pass
	 * @ticket BBP3684
	 */
	public function test_callback_pass_upgrades_password_and_removes_converter_metadata() {
		global $wpdb;

		$password = 'Correct Horse Battery Staple';
		$user_id  = $this->factory->user->create( array( 'user_login' => 'kunena3-imported-user' ) );
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
		update_user_meta( $user_id, '_bbp_class', 'Kunena3' );
		clean_user_cache( $user_id );

		$this->converter->callback_pass( 'kunena3-imported-user', $password );

		$user = get_userdata( $user_id );

		$this->assertTrue( wp_check_password( $password, $user->user_pass, $user_id ) );
		$this->assertSame( '', get_user_meta( $user_id, '_bbp_password', true ) );
		$this->assertSame( '', get_user_meta( $user_id, '_bbp_class', true ) );
	}

	/**
	 * @covers BBP_Converter_Base::callback_pass
	 * @covers Kunena3::authenticate_pass
	 * @ticket BBP3684
	 */
	public function test_callback_pass_rejects_wrong_password_and_preserves_converter_metadata() {
		global $wpdb;

		$user_id  = $this->factory->user->create( array( 'user_login' => 'kunena3-imported-failure' ) );
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
		update_user_meta( $user_id, '_bbp_class', 'Kunena3' );
		clean_user_cache( $user_id );

		$this->converter->callback_pass( 'kunena3-imported-failure', 'incorrect' );

		$this->assertSame( '', get_userdata( $user_id )->user_pass );
		$this->assertTrue( metadata_exists( 'user', $user_id, '_bbp_password' ) );
		$this->assertTrue( metadata_exists( 'user', $user_id, '_bbp_class' ) );
	}

	/**
	 * @covers ::bbp_user_maybe_convert_pass
	 * @covers Kunena3::authenticate_pass
	 * @ticket BBP3684
	 */
	public function test_existing_import_without_class_meta_upgrades_from_saved_platform() {
		global $wpdb;

		$password = 'Correct Horse Battery Staple';
		$user_id  = $this->factory->user->create( array( 'user_login' => 'kunena3-existing-import' ) );

		$wpdb->update( $wpdb->users, array( 'user_pass' => '' ), array( 'ID' => $user_id ) );
		update_user_meta( $user_id, '_bbp_password', array( 'hash' => '241a794170e288fccc5a82f94f51225f:a1B2' ) );
		update_option( '_bbp_converter_platform', 'Kunena3' );
		clean_user_cache( $user_id );

		$_POST['log'] = 'kunena3-existing-import';
		$_POST['pwd'] = $password;

		bbp_user_maybe_convert_pass();

		$this->assertTrue( wp_check_password( $password, get_userdata( $user_id )->user_pass, $user_id ) );
		$this->assertFalse( metadata_exists( 'user', $user_id, '_bbp_password' ) );
		$this->assertFalse( metadata_exists( 'user', $user_id, '_bbp_class' ) );
	}
}
