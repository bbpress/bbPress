<?php

/**
 * Tests for the Drupal 7 converter.
 *
 * @group converters
 */
class BBP_Tests_Admin_Converters_Drupal7 extends BBP_UnitTestCase {

	/**
	 * @var Drupal7
	 */
	protected $converter;

	public function setUp(): void {
		parent::setUp();

		bbp_setup_converter();
		require_once BBP_PLUGIN_DIR . 'includes/admin/converters/Drupal7.php';

		$reflection      = new ReflectionClass( 'Drupal7' );
		$this->converter = $reflection->newInstanceWithoutConstructor();
	}

	public function tearDown(): void {
		unset( $_POST['log'], $_POST['pwd'] );
		delete_option( '_bbp_converter_platform' );

		parent::tearDown();
	}

	/**
	 * @covers Drupal7::setup_globals
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
		$password_map  = wp_filter_object_list( $field_map, array( 'to_fieldname' => '_bbp_password' ) );
		$class_mapping = wp_filter_object_list( $field_map, array( 'to_fieldname' => '_bbp_class' ) );

		$this->assertCount( 1, $password_map );
		$this->assertSame( 'callback_savepass', reset( $password_map )['callback_method'] );
		$this->assertCount( 1, $class_mapping );
		$this->assertSame( 'user', reset( $class_mapping )['to_type'] );
		$this->assertSame( 'Drupal7', reset( $class_mapping )['default'] );
	}

	/**
	 * @covers Drupal7::callback_savepass
	 * @ticket BBP3684
	 */
	public function test_callback_savepass_preserves_hash() {
		$hash = '$S$C12345678gcOxhZNdyZ5wOJBbWfq8jip.heJSgLntavKZ0U.zXgx';

		$this->assertSame( array( 'hash' => $hash ), $this->converter->callback_savepass( $hash, array() ) );
	}

	/**
	 * @covers Drupal7::authenticate_pass
	 * @ticket BBP3684
	 */
	public function test_authenticate_pass_with_drupal_7_sha512_hash() {
		$pass = serialize( array( 'hash' => '$S$C12345678gcOxhZNdyZ5wOJBbWfq8jip.heJSgLntavKZ0U.zXgx' ) );

		$this->assertTrue( $this->converter->authenticate_pass( 'Correct Horse Battery Staple', $pass ) );
		$this->assertFalse( $this->converter->authenticate_pass( 'incorrect', $pass ) );
	}

	/**
	 * @covers Drupal7::authenticate_pass
	 * @ticket BBP3684
	 */
	public function test_authenticate_pass_with_imported_phpass_hash() {
		$pass = serialize( array( 'hash' => '$P$912345678.tn7RRNRFBjgoWufz9MKM1' ) );

		$this->assertTrue( $this->converter->authenticate_pass( 'Correct Horse Battery Staple', $pass ) );
		$this->assertFalse( $this->converter->authenticate_pass( 'incorrect', $pass ) );
	}

	/**
	 * @covers Drupal7::authenticate_pass
	 * @ticket BBP3684
	 */
	public function test_authenticate_pass_with_drupal_6_upgrade_hash() {
		$pass = serialize( array( 'hash' => 'U$P$912345678HAIyTJwHUlvsplLaSh2B/0' ) );

		$this->assertTrue( $this->converter->authenticate_pass( 'Correct Horse Battery Staple', $pass ) );
		$this->assertFalse( $this->converter->authenticate_pass( 'incorrect', $pass ) );
	}

	/**
	 * @covers Drupal7::authenticate_pass
	 * @ticket BBP3684
	 */
	public function test_authenticate_pass_rejects_invalid_metadata_and_settings() {
		$this->assertFalse( $this->converter->authenticate_pass( 'password', serialize( array() ) ) );
		$this->assertFalse( $this->converter->authenticate_pass( 'password', serialize( 'not an array' ) ) );
		$this->assertFalse( $this->converter->authenticate_pass( 'password', serialize( array( 'hash' => array() ) ) ) );
		$this->assertFalse( $this->converter->authenticate_pass( array(), serialize( array( 'hash' => 'hash' ) ) ) );
		$this->assertFalse( $this->converter->authenticate_pass( 'password', serialize( array( 'hash' => '$S$z12345678invalid' ) ) ) );
		$this->assertFalse( $this->converter->authenticate_pass( str_repeat( 'a', 513 ), serialize( array( 'hash' => '$S$C12345678invalid' ) ) ) );
	}

	/**
	 * @covers BBP_Converter_Base::callback_pass
	 * @covers Drupal7::authenticate_pass
	 * @ticket BBP3684
	 */
	public function test_callback_pass_upgrades_password_and_removes_converter_metadata() {
		global $wpdb;

		$password = 'Correct Horse Battery Staple';
		$user_id  = $this->factory->user->create( array( 'user_login' => 'drupal7-imported-user' ) );
		$set_wpdb = Closure::bind(
			function( $converter, $database ) {
				$converter->wpdb = $database;
			},
			null,
			'BBP_Converter_Base'
		);
		$set_wpdb( $this->converter, $wpdb );

		$wpdb->update( $wpdb->users, array( 'user_pass' => '' ), array( 'ID' => $user_id ) );
		update_user_meta( $user_id, '_bbp_password', array( 'hash' => '$S$C12345678gcOxhZNdyZ5wOJBbWfq8jip.heJSgLntavKZ0U.zXgx' ) );
		update_user_meta( $user_id, '_bbp_class', 'Drupal7' );
		clean_user_cache( $user_id );

		$this->converter->callback_pass( 'drupal7-imported-user', $password );

		$user = get_userdata( $user_id );

		$this->assertTrue( wp_check_password( $password, $user->user_pass, $user_id ) );
		$this->assertSame( '', get_user_meta( $user_id, '_bbp_password', true ) );
		$this->assertSame( '', get_user_meta( $user_id, '_bbp_class', true ) );
	}

	/**
	 * @covers Drupal7::callback_pass
	 * @covers Drupal7::authenticate_pass
	 * @ticket BBP3684
	 */
	public function test_callback_pass_supports_serialized_metadata_with_obsolete_salt() {
		global $wpdb;

		$password = 'Correct Horse Battery Staple';
		$user_id  = $this->create_imported_user( '' );
		$set_wpdb = Closure::bind(
			function( $converter, $database ) {
				$converter->wpdb = $database;
			},
			null,
			'BBP_Converter_Base'
		);
		$set_wpdb( $this->converter, $wpdb );

		update_user_meta(
			$user_id,
			'_bbp_password',
			array(
				'hash' => '$S$C12345678gcOxhZNdyZ5wOJBbWfq8jip.heJSgLntavKZ0U.zXgx',
				'salt' => null,
			)
		);
		update_user_meta( $user_id, '_bbp_class', 'Drupal7' );

		$this->converter->callback_pass( get_userdata( $user_id )->user_login, $password );

		$this->assertTrue( wp_check_password( $password, get_userdata( $user_id )->user_pass, $user_id ) );
		$this->assertFalse( metadata_exists( 'user', $user_id, '_bbp_password' ) );
		$this->assertFalse( metadata_exists( 'user', $user_id, '_bbp_class' ) );
	}

	/**
	 * @covers Drupal7::callback_pass
	 * @covers Drupal7::authenticate_pass
	 * @ticket BBP3684
	 */
	public function test_callback_pass_upgrades_raw_password_metadata_from_interrupted_import() {
		$password = 'Correct Horse Battery Staple';
		$user_id  = $this->create_imported_user( '' );

		update_user_meta( $user_id, '_bbp_password', '$S$C12345678gcOxhZNdyZ5wOJBbWfq8jip.heJSgLntavKZ0U.zXgx' );
		update_user_meta( $user_id, '_bbp_class', 'Drupal7' );

		$this->converter->callback_pass( get_userdata( $user_id )->user_login, $password );

		$this->assertTrue( wp_check_password( $password, get_userdata( $user_id )->user_pass, $user_id ) );
		$this->assertFalse( metadata_exists( 'user', $user_id, '_bbp_password' ) );
		$this->assertFalse( metadata_exists( 'user', $user_id, '_bbp_class' ) );
	}

	/**
	 * @covers Drupal7::callback_pass
	 * @covers Drupal7::authenticate_pass
	 * @ticket BBP3684
	 */
	public function test_callback_pass_preserves_raw_password_metadata_after_failed_authentication() {
		$hash    = '$S$C12345678gcOxhZNdyZ5wOJBbWfq8jip.heJSgLntavKZ0U.zXgx';
		$user_id = $this->create_imported_user( '' );

		update_user_meta( $user_id, '_bbp_password', $hash );
		update_user_meta( $user_id, '_bbp_class', 'Drupal7' );

		$this->converter->callback_pass( get_userdata( $user_id )->user_login, 'incorrect' );

		$this->assertSame( '', get_userdata( $user_id )->user_pass );
		$this->assertSame( $hash, get_user_meta( $user_id, '_bbp_password', true ) );
		$this->assertSame( 'Drupal7', get_user_meta( $user_id, '_bbp_class', true ) );
	}

	/**
	 * @covers ::bbp_user_maybe_convert_pass
	 * @covers Drupal7::callback_pass
	 * @covers Drupal7::authenticate_pass
	 * @ticket BBP3684
	 */
	public function test_login_upgrades_raw_password_metadata_using_saved_platform() {
		$password = 'Correct Horse Battery Staple';
		$user_id  = $this->create_imported_user( '' );
		$user     = get_userdata( $user_id );

		update_user_meta( $user_id, '_bbp_password', '$S$C12345678gcOxhZNdyZ5wOJBbWfq8jip.heJSgLntavKZ0U.zXgx' );
		update_option( '_bbp_converter_platform', 'Drupal7' );

		$_POST['log'] = $user->user_login;
		$_POST['pwd'] = $password;

		bbp_user_maybe_convert_pass();

		$this->assertTrue( wp_check_password( $password, get_userdata( $user_id )->user_pass, $user_id ) );
		$this->assertFalse( metadata_exists( 'user', $user_id, '_bbp_password' ) );
		$this->assertFalse( metadata_exists( 'user', $user_id, '_bbp_class' ) );
	}

	/**
	 * @covers ::bbp_user_maybe_convert_pass
	 * @covers Drupal7::callback_user_pass
	 * @covers Drupal7::authenticate_pass
	 * @ticket BBP3684
	 */
	public function test_login_upgrades_hash_in_user_pass_from_completed_import() {
		$password = 'Correct Horse Battery Staple';
		$user_id  = $this->create_imported_user( '$S$C12345678gcOxhZNdyZ5wOJBbWfq8jip.heJSgLntavKZ0U.zXgx' );
		$user     = get_userdata( $user_id );

		update_option( '_bbp_converter_platform', 'Drupal7' );

		$_POST['log'] = $user->user_login;
		$_POST['pwd'] = $password;

		bbp_user_maybe_convert_pass();

		$this->assertTrue( wp_check_password( $password, get_userdata( $user_id )->user_pass, $user_id ) );
		$this->assertFalse( metadata_exists( 'user', $user_id, '_bbp_password' ) );
		$this->assertFalse( metadata_exists( 'user', $user_id, '_bbp_class' ) );
	}

	/**
	 * @covers ::bbp_user_maybe_convert_pass
	 * @covers Drupal7::callback_user_pass
	 * @covers Drupal7::authenticate_pass
	 * @ticket BBP3684
	 */
	public function test_login_preserves_hash_in_user_pass_after_failed_authentication() {
		$hash    = '$S$C12345678gcOxhZNdyZ5wOJBbWfq8jip.heJSgLntavKZ0U.zXgx';
		$user_id = $this->create_imported_user( $hash );
		$user     = get_userdata( $user_id );

		update_option( '_bbp_converter_platform', 'Drupal7' );

		$_POST['log'] = $user->user_login;
		$_POST['pwd'] = 'incorrect';

		bbp_user_maybe_convert_pass();

		$this->assertSame( $hash, get_userdata( $user_id )->user_pass );
		$this->assertFalse( metadata_exists( 'user', $user_id, '_bbp_password' ) );
		$this->assertFalse( metadata_exists( 'user', $user_id, '_bbp_class' ) );
	}

	/**
	 * @covers ::bbp_user_maybe_convert_pass
	 * @covers Drupal7::callback_user_pass
	 * @ticket BBP3684
	 */
	public function test_login_does_not_change_wordpress_password_when_saved_platform_is_drupal7() {
		$password = 'Current WordPress Password';
		$user_id  = $this->factory->user->create(
			array(
				'user_login' => 'wordpress-user-' . wp_generate_password( 8, false ),
				'user_pass'  => $password,
			)
		);
		$user     = get_userdata( $user_id );
		$hash     = $user->user_pass;

		update_option( '_bbp_converter_platform', 'Drupal7' );

		$_POST['log'] = $user->user_login;
		$_POST['pwd'] = $password;

		bbp_user_maybe_convert_pass();

		$this->assertSame( $hash, get_userdata( $user_id )->user_pass );
		$this->assertTrue( wp_check_password( $password, $hash, $user_id ) );
	}

	/**
	 * @covers ::bbp_user_maybe_convert_pass
	 * @covers Drupal7::callback_user_pass
	 * @ticket BBP3684
	 */
	public function test_login_does_not_change_wordpress_compatible_phpass_hash() {
		$password = 'Correct Horse Battery Staple';
		$hash     = '$P$912345678.tn7RRNRFBjgoWufz9MKM1';
		$user_id  = $this->create_imported_user( $hash );
		$user     = get_userdata( $user_id );

		update_option( '_bbp_converter_platform', 'Drupal7' );

		$_POST['log'] = $user->user_login;
		$_POST['pwd'] = $password;

		bbp_user_maybe_convert_pass();

		$this->assertTrue( wp_check_password( $password, get_userdata( $user_id )->user_pass, $user_id ) );
		$this->assertSame( $hash, get_userdata( $user_id )->user_pass );
	}

	/**
	 * @covers ::bbp_user_maybe_convert_pass
	 * @covers Drupal7::callback_user_pass
	 * @ticket BBP3684
	 */
	public function test_login_does_not_change_password_recognized_by_wordpress_plugin() {
		$password = 'Correct Horse Battery Staple';
		$hash     = '$S$C12345678gcOxhZNdyZ5wOJBbWfq8jip.heJSgLntavKZ0U.zXgx';
		$user_id  = $this->create_imported_user( $hash );
		$user     = get_userdata( $user_id );
		$check    = function( $is_valid, $submitted_password, $stored_hash, $stored_user_id ) use ( $password, $hash, $user_id ) {
			if ( $password === $submitted_password && $hash === $stored_hash && $user_id === $stored_user_id ) {
				return true;
			}

			return $is_valid;
		};

		update_option( '_bbp_converter_platform', 'Drupal7' );

		$_POST['log'] = $user->user_login;
		$_POST['pwd'] = $password;

		add_filter( 'check_password', $check, 10, 4 );

		try {
			bbp_user_maybe_convert_pass();
		} finally {
			remove_filter( 'check_password', $check, 10 );
		}

		$this->assertSame( $hash, get_userdata( $user_id )->user_pass );
	}

	/**
	 * @covers ::bbp_user_maybe_convert_pass
	 * @ticket BBP3684
	 */
	public function test_login_does_not_use_drupal_hash_without_drupal_saved_platform() {
		$hash    = '$S$C12345678gcOxhZNdyZ5wOJBbWfq8jip.heJSgLntavKZ0U.zXgx';
		$user_id = $this->create_imported_user( $hash );
		$user     = get_userdata( $user_id );

		update_option( '_bbp_converter_platform', 'phpBB' );

		$_POST['log'] = $user->user_login;
		$_POST['pwd'] = 'Correct Horse Battery Staple';

		bbp_user_maybe_convert_pass();

		$this->assertSame( $hash, get_userdata( $user_id )->user_pass );
	}

	/**
	 * Create a user with an exact value in wp_users.user_pass.
	 *
	 * @param string $hash Stored password hash.
	 * @return int User ID.
	 */
	private function create_imported_user( $hash ) {
		global $wpdb;

		$user_id = $this->factory->user->create(
			array(
				'user_login' => 'drupal7-imported-' . wp_generate_password( 8, false ),
			)
		);

		$wpdb->update( $wpdb->users, array( 'user_pass' => $hash ), array( 'ID' => $user_id ) );
		clean_user_cache( $user_id );

		return $user_id;
	}
}
