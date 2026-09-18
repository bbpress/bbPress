<?php

/**
 * Tests for the PHPWind converter.
 *
 * @group converters
 */
class BBP_Tests_Admin_Converters_PHPWind extends BBP_UnitTestCase {

	protected $converter;

	public function setUp(): void {
		parent::setUp();

		bbp_setup_converter();
		require_once BBP_PLUGIN_DIR . 'includes/admin/converters/PHPWind.php';

		$reflection      = new ReflectionClass( 'PHPWind' );
		$this->converter = $reflection->newInstanceWithoutConstructor();
	}

	public function tearDown(): void {
		unset( $_POST['log'], $_POST['pwd'] );
		delete_option( '_bbp_converter_platform' );

		parent::tearDown();
	}

	/**
	 * @covers PHPWind::setup_globals
	 * @ticket BBP3684
	 */
	public function test_users_and_passwords_are_mapped_from_windid() {
		$this->converter->setup_globals();

		$get_field_map = Closure::bind(
			function( $converter ) {
				return $converter->field_map;
			},
			null,
			'BBP_Converter_Base'
		);
		$field_map      = $get_field_map( $this->converter );
		$old_user_map   = wp_filter_object_list( $field_map, array( 'to_fieldname' => '_bbp_old_user_id' ) );
		$password_map   = wp_filter_object_list( $field_map, array( 'to_fieldname' => '_bbp_password' ) );
		$salt_map       = wp_filter_object_list( $field_map, array( 'from_fieldname' => 'salt' ) );
		$class_map      = wp_filter_object_list( $field_map, array( 'to_fieldname' => '_bbp_class' ) );
		$login_map      = wp_filter_object_list( $field_map, array( 'to_fieldname' => 'user_login' ) );
		$nicename_map   = wp_filter_object_list( $field_map, array( 'to_fieldname' => 'user_nicename' ) );
		$email_map      = wp_filter_object_list( $field_map, array( 'to_fieldname' => 'user_email' ) );
		$registered_map = wp_filter_object_list( $field_map, array( 'to_fieldname' => 'user_registered' ) );
		$display_map    = wp_filter_object_list( $field_map, array( 'to_fieldname' => 'display_name' ) );

		$this->assertCount( 1, $old_user_map );
		$this->assertCount( 1, $password_map );
		$this->assertCount( 1, $salt_map );
		$this->assertCount( 1, $class_map );
		$this->assertCount( 1, $login_map );
		$this->assertCount( 1, $nicename_map );
		$this->assertCount( 1, $email_map );
		$this->assertCount( 1, $registered_map );
		$this->assertCount( 1, $display_map );

		$this->assertSame( 'windid_user', reset( $old_user_map )['from_tablename'] );
		$this->assertSame( 'uid', reset( $old_user_map )['from_fieldname'] );
		$this->assertSame( 'windid_user', reset( $password_map )['from_tablename'] );
		$this->assertSame( 'password', reset( $password_map )['from_fieldname'] );
		$this->assertSame( 'callback_savepass', reset( $password_map )['callback_method'] );
		$this->assertSame( 'windid_user', reset( $salt_map )['from_tablename'] );
		$this->assertSame( 'salt', reset( $salt_map )['from_fieldname'] );
		$this->assertSame( 'user', reset( $class_map )['to_type'] );
		$this->assertSame( 'PHPWind', reset( $class_map )['default'] );
		$this->assertSame( 'windid_user', reset( $login_map )['from_tablename'] );
		$this->assertSame( 'username', reset( $login_map )['from_fieldname'] );
		$this->assertSame( 'windid_user', reset( $nicename_map )['from_tablename'] );
		$this->assertSame( 'username', reset( $nicename_map )['from_fieldname'] );
		$this->assertSame( 'windid_user', reset( $email_map )['from_tablename'] );
		$this->assertSame( 'email', reset( $email_map )['from_fieldname'] );
		$this->assertSame( 'windid_user', reset( $registered_map )['from_tablename'] );
		$this->assertSame( 'regdate', reset( $registered_map )['from_fieldname'] );
		$this->assertSame( 'windid_user_info', reset( $display_map )['from_tablename'] );
		$this->assertSame( 'realname', reset( $display_map )['from_fieldname'] );
		$this->assertSame( 'windid_user', reset( $display_map )['join_tablename'] );
		$this->assertSame( 'LEFT', reset( $display_map )['join_type'] );
		$this->assertSame( 'ON windid_user.uid = windid_user_info.uid', reset( $display_map )['join_expression'] );
	}

	/**
	 * @covers PHPWind::callback_savepass
	 * @ticket BBP3684
	 */
	public function test_callback_savepass_preserves_hash_and_slashes_salt() {
		$this->assertSame(
			array(
				'hash' => '032db847259044fac706099fbd5da562',
				'salt' => "a\\\\b\\'",
			),
			$this->converter->callback_savepass(
				'032db847259044fac706099fbd5da562',
				array( 'salt' => "a\\b'" )
			)
		);
		$this->assertSame(
			array(
				'hash' => 'hash',
				'salt' => '',
			),
			$this->converter->callback_savepass( 'hash', array() )
		);
	}

	/**
	 * @covers PHPWind::callback_savepass
	 * @ticket BBP3684
	 */
	public function test_callback_savepass_survives_user_meta_storage() {
		$user_id  = $this->factory->user->create();
		$metadata = $this->converter->callback_savepass(
			'032db847259044fac706099fbd5da562',
			array( 'salt' => "a\\b'" )
		);

		update_user_meta( $user_id, '_bbp_password', $metadata );

		$this->assertSame(
			array(
				'hash' => '032db847259044fac706099fbd5da562',
				'salt' => "a\\b'",
			),
			get_user_meta( $user_id, '_bbp_password', true )
		);
	}

	/**
	 * @covers PHPWind::authenticate_pass
	 * @ticket BBP3684
	 */
	public function test_authenticate_pass_with_phpwind_9_hash() {
		$pass = serialize(
			array(
				'hash' => '032db847259044fac706099fbd5da562',
				'salt' => 'a1B2c3',
			)
		);

		$this->assertTrue( $this->converter->authenticate_pass( 'Correct Horse Battery Staple', $pass ) );
		$this->assertFalse( $this->converter->authenticate_pass( 'incorrect', $pass ) );
	}

	/**
	 * @covers PHPWind::authenticate_pass
	 * @ticket BBP3684
	 */
	public function test_authenticate_pass_rejects_malformed_metadata() {
		$this->assertFalse( $this->converter->authenticate_pass( 'password', serialize( array() ) ) );
		$this->assertFalse( $this->converter->authenticate_pass( 'password', serialize( 'not an array' ) ) );
		$this->assertFalse( $this->converter->authenticate_pass( 'password', serialize( array( 'hash' => array(), 'salt' => 'salt' ) ) ) );
		$this->assertFalse( $this->converter->authenticate_pass( 'password', serialize( array( 'hash' => 'hash', 'salt' => array() ) ) ) );
		$this->assertFalse( $this->converter->authenticate_pass( array(), serialize( array( 'hash' => 'hash', 'salt' => 'salt' ) ) ) );
		$this->assertFalse( $this->converter->authenticate_pass( 'password', array() ) );
	}

	/**
	 * @dataProvider login_provider
	 * @covers ::bbp_user_maybe_convert_pass
	 * @covers PHPWind::authenticate_pass
	 * @ticket BBP3684
	 */
	public function test_login_upgrades_password_without_source_database( $use_email, $with_class ) {
		global $wpdb;

		$password = 'Correct Horse Battery Staple';
		$email    = 'phpwind-' . wp_generate_password( 8, false ) . '@example.org';
		$login    = 'phpwind-' . wp_generate_password( 8, false );
		$user_id  = $this->factory->user->create(
			array(
				'user_login' => $login,
				'user_email' => $email,
			)
		);

		$wpdb->update( $wpdb->users, array( 'user_pass' => '' ), array( 'ID' => $user_id ) );
		update_user_meta(
			$user_id,
			'_bbp_password',
			array(
				'hash' => '032db847259044fac706099fbd5da562',
				'salt' => 'a1B2c3',
			)
		);
		if ( $with_class ) {
			update_user_meta( $user_id, '_bbp_class', 'PHPWind' );
		} else {
			update_option( '_bbp_converter_platform', 'PHPWind' );
		}
		clean_user_cache( $user_id );

		$_POST['log'] = $use_email ? $email : $login;
		$_POST['pwd'] = $password;
		bbp_user_maybe_convert_pass();

		$this->assertTrue( wp_check_password( $password, get_userdata( $user_id )->user_pass, $user_id ) );
		$this->assertFalse( metadata_exists( 'user', $user_id, '_bbp_password' ) );
		$this->assertFalse( metadata_exists( 'user', $user_id, '_bbp_class' ) );
	}

	public function login_provider() {
		return array(
			'username and class metadata' => array( false, true ),
			'email and saved platform'    => array( true, false ),
		);
	}

	/**
	 * @covers ::bbp_user_maybe_convert_pass
	 * @ticket BBP3684
	 */
	public function test_failed_login_retains_password_metadata() {
		global $wpdb;

		$user_id = $this->factory->user->create( array( 'user_login' => 'phpwind-failed-' . wp_generate_password( 8, false ) ) );
		$user    = get_userdata( $user_id );
		$meta    = array(
			'hash' => '032db847259044fac706099fbd5da562',
			'salt' => 'a1B2c3',
		);

		$wpdb->update( $wpdb->users, array( 'user_pass' => '' ), array( 'ID' => $user_id ) );
		update_user_meta( $user_id, '_bbp_password', $meta );
		update_user_meta( $user_id, '_bbp_class', 'PHPWind' );
		clean_user_cache( $user_id );

		$_POST['log'] = $user->user_login;
		$_POST['pwd'] = 'incorrect';
		bbp_user_maybe_convert_pass();

		$this->assertSame( '', get_userdata( $user_id )->user_pass );
		$this->assertSame( $meta, get_user_meta( $user_id, '_bbp_password', true ) );
		$this->assertSame( 'PHPWind', get_user_meta( $user_id, '_bbp_class', true ) );
	}
}
