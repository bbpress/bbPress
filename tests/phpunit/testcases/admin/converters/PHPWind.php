<?php

/**
 * Minimal source database adapter for PHPWind converter tests.
 */
class BBP_Tests_PHPWind_Source_DB {

	public $prefix;
	public $connect_count = 0;
	public $query_count   = 0;
	public $last_error    = '';

	private $wpdb;
	private $connect_result;

	public function __construct( $wpdb, $connect_result = true ) {
		$this->wpdb           = $wpdb;
		$this->prefix         = $wpdb->prefix;
		$this->connect_result = $connect_result;
	}

	public function db_connect( $allow_bail = true ) {
		++$this->connect_count;

		return $this->connect_result;
	}

	public function prepare( $query, $user_id ) {
		return $this->wpdb->prepare( $query, $user_id );
	}

	public function get_row( $query, $output ) {
		++$this->query_count;
		$row              = $this->wpdb->get_row( $query, $output );
		$this->last_error = $this->wpdb->last_error;

		return $row;
	}
}

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
		delete_option( '_bbp_converter_db_user' );
		delete_option( '_bbp_converter_db_pass' );
		delete_option( '_bbp_converter_db_name' );
		delete_option( '_bbp_converter_db_server' );
		delete_option( '_bbp_converter_db_port' );
		delete_option( '_bbp_converter_db_prefix' );
		delete_transient( 'bbp_phpwind_source_connection_failed' );
		global $wpdb;
		$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}user" );
		$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}windid_user" );

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
	 * @covers PHPWind::setup_globals
	 * @ticket BBP3686
	 */
	public function test_forum_date_mappings_target_forums() {
		$this->converter->setup_globals();

		$get_field_map = Closure::bind(
			function( $converter ) {
				return $converter->field_map;
			},
			null,
			'BBP_Converter_Base'
		);
		$field_map     = $get_field_map( $this->converter );
		$date_fields   = array( 'post_date', 'post_date_gmt', 'post_modified', 'post_modified_gmt' );

		foreach ( $date_fields as $date_field ) {
			$mapping = wp_filter_object_list(
				$field_map,
				array(
					'to_type'      => 'forum',
					'to_fieldname' => $date_field,
				)
			);

			$this->assertCount( 1, $mapping, $date_field . ' does not target forums.' );
			$this->assertArrayHasKey( 'default', reset( $mapping ) );
		}
	}

	/**
	 * @dataProvider topic_status_provider
	 * @covers PHPWind::callback_topic_status
	 * @ticket BBP3686
	 */
	public function test_callback_topic_status_handles_bit_flags( $status, $expected ) {
		$this->assertSame( $expected, $this->converter->callback_topic_status( $status ) );
	}

	/**
	 * @covers PHPWind::callback_topic_status
	 * @ticket BBP3686
	 */
	public function test_callback_topic_status_defaults_to_publish() {
		$this->assertSame( 'publish', $this->converter->callback_topic_status() );
	}

	public function topic_status_provider() {
		return array(
			'open'                         => array( 0, 'publish' ),
			'locked'                       => array( 1, 'closed' ),
			'closed'                       => array( 2, 'closed' ),
			'locked and closed'            => array( 3, 'closed' ),
			'unrelated flag'                => array( 4, 'publish' ),
			'locked with unrelated flag'    => array( 5, 'closed' ),
			'closed with unrelated flag'    => array( 6, 'closed' ),
			'all flags'                     => array( 7, 'closed' ),
			'numeric string'                => array( '3', 'closed' ),
		);
	}

	/**
	 * @dataProvider topic_reply_count_provider
	 * @covers PHPWind::callback_topic_reply_count
	 * @ticket BBP3686
	 */
	public function test_callback_topic_reply_count_preserves_count( $count, $expected ) {
		$this->assertSame( $expected, $this->converter->callback_topic_reply_count( $count ) );
	}

	public function topic_reply_count_provider() {
		return array(
			'no replies'   => array( 0, 0 ),
			'one reply'    => array( 1, 1 ),
			'many replies' => array( 12, 12 ),
		);
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
	/**
	 * @covers ::bbp_user_maybe_convert_pass
	 * @covers PHPWind::callback_pass
	 * @covers PHPWind::authenticate_pass
	 * @ticket BBP3691
	 */
	public function test_login_upgrades_raw_metadata_from_interrupted_import_using_source_salt() {
		global $wpdb;

		$password = 'Correct Horse Battery Staple';
		$token    = '073d73ef8d98047f1e3556faba272160';
		$user_id  = $this->factory->user->create(
			array(
				'user_login' => 'phpwind-interrupted-' . wp_generate_password( 8, false ),
			)
		);
		$user     = get_userdata( $user_id );

		$wpdb->update( $wpdb->users, array( 'user_pass' => '' ), array( 'ID' => $user_id ) );
		clean_user_cache( $user_id );
		update_user_meta( $user_id, '_bbp_password', $token );
		update_user_meta( $user_id, '_bbp_old_user_id', 42 );
		$this->create_source_user( 42, $token, '032db847259044fac706099fbd5da562', 'a1B2c3' );
		update_option( '_bbp_converter_platform', 'PHPWind' );

		$_POST['log'] = $user->user_login;
		$_POST['pwd'] = $password;

		$this->run_login_with_test_source_database();

		$this->assertTrue( wp_check_password( $password, get_userdata( $user_id )->user_pass, $user_id ) );
		$this->assertFalse( metadata_exists( 'user', $user_id, '_bbp_password' ) );
		$this->assertFalse( metadata_exists( 'user', $user_id, '_bbp_class' ) );
	}

	/**
	 * @covers ::bbp_user_maybe_convert_pass
	 * @covers PHPWind::callback_pass
	 * @covers PHPWind::authenticate_pass
	 * @ticket BBP3691
	 */
	public function test_login_preserves_raw_metadata_after_failed_authentication() {
		global $wpdb;

		$token   = '073d73ef8d98047f1e3556faba272160';
		$user_id = $this->factory->user->create(
			array(
				'user_login' => 'phpwind-interrupted-' . wp_generate_password( 8, false ),
			)
		);
		$user     = get_userdata( $user_id );

		$wpdb->update( $wpdb->users, array( 'user_pass' => '' ), array( 'ID' => $user_id ) );
		clean_user_cache( $user_id );
		update_user_meta( $user_id, '_bbp_password', $token );
		update_user_meta( $user_id, '_bbp_old_user_id', 42 );
		$this->create_source_user( 42, $token, '032db847259044fac706099fbd5da562', 'a1B2c3' );
		update_option( '_bbp_converter_platform', 'PHPWind' );

		$_POST['log'] = $user->user_login;
		$_POST['pwd'] = 'incorrect';

		$this->run_login_with_test_source_database();

		$this->assertSame( '', get_userdata( $user_id )->user_pass );
		$this->assertSame( $token, get_user_meta( $user_id, '_bbp_password', true ) );
	}

	/**
	 * @covers ::bbp_user_maybe_convert_pass
	 * @covers PHPWind::callback_pass
	 * @ticket BBP3691
	 */
	public function test_login_preserves_raw_metadata_when_source_token_does_not_match() {
		global $wpdb;

		$token   = '073d73ef8d98047f1e3556faba272160';
		$user_id = $this->factory->user->create(
			array(
				'user_login' => 'phpwind-interrupted-' . wp_generate_password( 8, false ),
			)
		);
		$user     = get_userdata( $user_id );

		$wpdb->update( $wpdb->users, array( 'user_pass' => '' ), array( 'ID' => $user_id ) );
		clean_user_cache( $user_id );
		update_user_meta( $user_id, '_bbp_password', $token );
		update_user_meta( $user_id, '_bbp_old_user_id', 42 );
		$this->create_source_user( 42, md5( 'different source token' ), '032db847259044fac706099fbd5da562', 'a1B2c3' );
		update_option( '_bbp_converter_platform', 'PHPWind' );

		$_POST['log'] = $user->user_login;
		$_POST['pwd'] = 'Correct Horse Battery Staple';

		$this->run_login_with_test_source_database();

		$this->assertSame( '', get_userdata( $user_id )->user_pass );
		$this->assertSame( $token, get_user_meta( $user_id, '_bbp_password', true ) );
	}

	/**
	 * @covers ::bbp_user_maybe_convert_pass
	 * @covers PHPWind::callback_user_pass
	 * @covers PHPWind::authenticate_pass
	 * @ticket BBP3691
	 */
	public function test_login_upgrades_hash_from_completed_import_using_source_salt() {
		$password = 'Correct Horse Battery Staple';
		$token    = '073d73ef8d98047f1e3556faba272160';
		$user_id  = $this->create_completed_import_user( $token );
		$user     = get_userdata( $user_id );

		$this->create_source_user( 42, $token, '032db847259044fac706099fbd5da562', 'a1B2c3' );
		update_user_meta( $user_id, '_bbp_old_user_id', 42 );
		update_option( '_bbp_converter_platform', 'PHPWind' );

		$_POST['log'] = $user->user_login;
		$_POST['pwd'] = $password;

		$this->run_login_with_test_source_database();

		$this->assertTrue( wp_check_password( $password, get_userdata( $user_id )->user_pass, $user_id ) );
		$this->assertFalse( metadata_exists( 'user', $user_id, '_bbp_password' ) );
		$this->assertFalse( metadata_exists( 'user', $user_id, '_bbp_class' ) );
	}

	/**
	 * @covers ::bbp_user_maybe_convert_pass
	 * @covers PHPWind::callback_user_pass
	 * @covers PHPWind::authenticate_pass
	 * @ticket BBP3691
	 */
	public function test_login_uses_per_user_class_without_saved_platform() {
		$password = 'Correct Horse Battery Staple';
		$token    = '073d73ef8d98047f1e3556faba272160';
		$user_id  = $this->create_completed_import_user( $token );
		$user     = get_userdata( $user_id );

		$this->create_source_user( 42, $token, '032db847259044fac706099fbd5da562', 'a1B2c3' );
		update_user_meta( $user_id, '_bbp_old_user_id', 42 );
		update_user_meta( $user_id, '_bbp_class', 'PHPWind' );

		$_POST['log'] = $user->user_login;
		$_POST['pwd'] = $password;

		$this->run_login_with_test_source_database();

		$this->assertTrue( wp_check_password( $password, get_userdata( $user_id )->user_pass, $user_id ) );
		$this->assertFalse( metadata_exists( 'user', $user_id, '_bbp_class' ) );
	}

	/**
	 * @covers ::bbp_user_maybe_convert_pass
	 * @covers PHPWind::callback_user_pass
	 * @covers PHPWind::authenticate_pass
	 * @ticket BBP3691
	 */
	public function test_login_preserves_completed_import_hash_after_failed_authentication() {
		$token   = '073d73ef8d98047f1e3556faba272160';
		$user_id = $this->create_completed_import_user( $token );
		$user     = get_userdata( $user_id );

		$this->create_source_user( 42, $token, '032db847259044fac706099fbd5da562', 'a1B2c3' );
		update_user_meta( $user_id, '_bbp_old_user_id', 42 );
		update_option( '_bbp_converter_platform', 'PHPWind' );

		$_POST['log'] = $user->user_login;
		$_POST['pwd'] = 'incorrect';

		$this->run_login_with_test_source_database();

		$this->assertSame( $token, get_userdata( $user_id )->user_pass );
	}

	/**
	 * @covers ::bbp_user_maybe_convert_pass
	 * @covers PHPWind::callback_user_pass
	 * @ticket BBP3691
	 */
	public function test_login_preserves_completed_import_hash_when_source_token_does_not_match() {
		$token   = '073d73ef8d98047f1e3556faba272160';
		$user_id = $this->create_completed_import_user( $token );
		$user     = get_userdata( $user_id );

		$this->create_source_user( 42, md5( 'different source token' ), '032db847259044fac706099fbd5da562', 'a1B2c3' );
		update_user_meta( $user_id, '_bbp_old_user_id', 42 );
		update_option( '_bbp_converter_platform', 'PHPWind' );

		$_POST['log'] = $user->user_login;
		$_POST['pwd'] = 'Correct Horse Battery Staple';

		$this->run_login_with_test_source_database();

		$this->assertSame( $token, get_userdata( $user_id )->user_pass );
	}

	/**
	 * @covers ::bbp_user_maybe_convert_pass
	 * @covers PHPWind::callback_pass
	 * @ticket BBP3691
	 */
	public function test_login_preserves_interrupted_import_when_source_connection_fails() {
		global $wpdb;

		$token   = '073d73ef8d98047f1e3556faba272160';
		$user_id = $this->factory->user->create(
			array(
				'user_login' => 'phpwind-interrupted-' . wp_generate_password( 8, false ),
			)
		);
		$user     = get_userdata( $user_id );

		$wpdb->update( $wpdb->users, array( 'user_pass' => '' ), array( 'ID' => $user_id ) );
		clean_user_cache( $user_id );
		update_user_meta( $user_id, '_bbp_password', $token );
		update_user_meta( $user_id, '_bbp_old_user_id', 42 );
		update_option( '_bbp_converter_platform', 'PHPWind' );

		$_POST['log'] = $user->user_login;
		$_POST['pwd'] = 'Correct Horse Battery Staple';

		$source_database = new BBP_Tests_PHPWind_Source_DB( $wpdb, false );
		$this->run_login_with_test_source_database( $source_database );
		$this->run_login_with_test_source_database( $source_database );

		$this->assertSame( 1, $source_database->connect_count );
		$this->assertSame( '', get_userdata( $user_id )->user_pass );
		$this->assertSame( $token, get_user_meta( $user_id, '_bbp_password', true ) );
	}

	/**
	 * @covers ::bbp_user_maybe_convert_pass
	 * @covers PHPWind::callback_user_pass
	 * @ticket BBP3691
	 */
	public function test_login_preserves_completed_import_when_source_connection_fails() {
		global $wpdb;

		$token   = '073d73ef8d98047f1e3556faba272160';
		$user_id = $this->create_completed_import_user( $token );
		$user     = get_userdata( $user_id );

		update_user_meta( $user_id, '_bbp_old_user_id', 42 );
		update_option( '_bbp_converter_platform', 'PHPWind' );

		$_POST['log'] = $user->user_login;
		$_POST['pwd'] = 'Correct Horse Battery Staple';

		$source_database = new BBP_Tests_PHPWind_Source_DB( $wpdb, false );
		$this->run_login_with_test_source_database( $source_database );

		$this->assertSame( 1, $source_database->connect_count );
		$this->assertSame( $token, get_userdata( $user_id )->user_pass );
	}

	/**
	 * @covers ::bbp_user_maybe_convert_pass
	 * @covers PHPWind::callback_user_pass
	 * @ticket BBP3691
	 */
	public function test_login_backs_off_when_source_tables_are_missing() {
		global $wpdb;

		$token   = '073d73ef8d98047f1e3556faba272160';
		$user_id = $this->create_completed_import_user( $token );
		$user    = get_userdata( $user_id );

		update_user_meta( $user_id, '_bbp_old_user_id', 42 );
		update_option( '_bbp_converter_platform', 'PHPWind' );

		$_POST['log'] = $user->user_login;
		$_POST['pwd'] = 'Correct Horse Battery Staple';

		$source_database = new BBP_Tests_PHPWind_Source_DB( $wpdb );
		$this->run_login_with_test_source_database( $source_database );
		$this->run_login_with_test_source_database( $source_database );

		$this->assertSame( 1, $source_database->connect_count );
		$this->assertSame( 1, $source_database->query_count );
		$this->assertNotEmpty( $source_database->last_error );
		$this->assertSame( $token, get_userdata( $user_id )->user_pass );
	}

	/**
	 * @covers ::bbp_user_maybe_convert_pass
	 * @covers PHPWind::callback_user_pass
	 * @dataProvider data_invalid_old_user_ids
	 * @ticket BBP3691
	 */
	public function test_login_does_not_connect_without_valid_old_user_id( $old_user_id ) {
		global $wpdb;

		$token   = '073d73ef8d98047f1e3556faba272160';
		$user_id = $this->create_completed_import_user( $token );
		$user     = get_userdata( $user_id );

		if ( ! is_null( $old_user_id ) ) {
			update_user_meta( $user_id, '_bbp_old_user_id', $old_user_id );
		}
		update_option( '_bbp_converter_platform', 'PHPWind' );

		$_POST['log'] = $user->user_login;
		$_POST['pwd'] = 'Correct Horse Battery Staple';

		$source_database = new BBP_Tests_PHPWind_Source_DB( $wpdb );
		$this->run_login_with_test_source_database( $source_database );

		$this->assertSame( 0, $source_database->connect_count );
		$this->assertSame( $token, get_userdata( $user_id )->user_pass );
	}

	/**
	 * Values that cannot identify a PHPWind source user.
	 */
	public function data_invalid_old_user_ids() {
		return array(
			'missing' => array( null ),
			'invalid' => array( 'invalid' ),
		);
	}

	/**
	 * @covers ::bbp_user_maybe_convert_pass
	 * @covers PHPWind::callback_user_pass
	 * @ticket BBP3691
	 */
	public function test_login_does_not_change_wordpress_md5_password() {
		global $wpdb;

		$password = 'Current WordPress Password';
		$hash     = md5( $password );
		$user_id  = $this->create_completed_import_user( $hash );
		$user     = get_userdata( $user_id );

		update_user_meta( $user_id, '_bbp_old_user_id', 42 );
		update_option( '_bbp_converter_platform', 'PHPWind' );

		$_POST['log'] = $user->user_login;
		$_POST['pwd'] = $password;

		$source_database = new BBP_Tests_PHPWind_Source_DB( $wpdb );
		$this->run_login_with_test_source_database( $source_database );

		$stored_hash = get_userdata( $user_id )->user_pass;

		$this->assertSame( 0, $source_database->connect_count );
		if ( function_exists( 'wp_password_needs_rehash' ) ) {
			$this->assertSame( $hash, $stored_hash );
		}
		$this->assertTrue( wp_check_password( $password, $stored_hash, $user_id ) );
	}

	/**
	 * @covers ::bbp_user_maybe_convert_pass
	 * @covers PHPWind::callback_user_pass
	 * @ticket BBP3691
	 */
	public function test_login_does_not_change_password_recognized_by_plugin() {
		global $wpdb;

		$hash    = '032db847259044fac706099fbd5da562';
		$user_id = $this->create_completed_import_user( $hash );
		$user     = get_userdata( $user_id );

		update_user_meta( $user_id, '_bbp_old_user_id', 42 );
		update_option( '_bbp_converter_platform', 'PHPWind' );

		$_POST['log'] = $user->user_login;
		$_POST['pwd'] = 'password-recognized-by-plugin';

		$recognize_password = function( $check, $password, $stored_hash, $checked_user_id ) use ( $hash, $user_id ) {
			if ( ( 'password-recognized-by-plugin' === $password ) && ( $hash === $stored_hash ) && ( $user_id === $checked_user_id ) ) {
				return true;
			}

			return $check;
		};
		$source_database   = new BBP_Tests_PHPWind_Source_DB( $wpdb );

		add_filter( 'check_password', $recognize_password, 10, 4 );

		try {
			$this->run_login_with_test_source_database( $source_database );
		} finally {
			remove_filter( 'check_password', $recognize_password, 10 );
		}

		$this->assertSame( 0, $source_database->connect_count );
		$this->assertSame( $hash, get_userdata( $user_id )->user_pass );
	}

	/**
	 * Create a user with an exact value in wp_users.user_pass.
	 *
	 * @param string $hash Stored password hash.
	 * @return int User ID.
	 */
	private function create_completed_import_user( $hash ) {
		global $wpdb;

		$user_id = $this->factory->user->create(
			array(
				'user_login' => 'phpwind-completed-' . wp_generate_password( 8, false ),
			)
		);

		$wpdb->update( $wpdb->users, array( 'user_pass' => $hash ), array( 'ID' => $user_id ) );
		clean_user_cache( $user_id );

		return $user_id;
	}

	/**
	 * Create a minimal PHPWind source user table and row.
	 *
	 * @param int    $user_id Source user ID.
	 * @param string $token   Source synchronization token.
	 * @param string $hash    Source login password hash.
	 * @param string $salt    Source login password salt.
	 */
	private function create_source_user( $user_id, $token, $hash, $salt ) {
		global $wpdb;

		$legacy_table = $wpdb->prefix . 'user';
		$windid_table = $wpdb->prefix . 'windid_user';
		$created      = $wpdb->query( "CREATE TABLE {$legacy_table} ( uid bigint(20) unsigned NOT NULL, password char(32) NOT NULL, PRIMARY KEY (uid) )" );
		$this->assertNotFalse( $created, $wpdb->last_error );
		$created = $wpdb->query( "CREATE TABLE {$windid_table} ( uid bigint(20) unsigned NOT NULL, password char(32) NOT NULL, salt char(6) NOT NULL, PRIMARY KEY (uid) )" );
		$this->assertNotFalse( $created, $wpdb->last_error );
		$wpdb->insert(
			$legacy_table,
			array(
				'uid'      => $user_id,
				'password' => $token,
			)
		);
		$wpdb->insert(
			$windid_table,
			array(
				'uid'      => $user_id,
				'password' => $hash,
				'salt'     => $salt,
			)
		);

		update_option( '_bbp_converter_db_prefix', $wpdb->prefix );
		update_option( '_bbp_converter_db_user', DB_USER );
		update_option( '_bbp_converter_db_pass', DB_PASSWORD );
		update_option( '_bbp_converter_db_name', DB_NAME );
		update_option( '_bbp_converter_db_server', DB_HOST );
		delete_option( '_bbp_converter_db_port' );
	}

	/**
	 * Run the login hook with the WordPress test database as the source.
	 */
	private function run_login_with_test_source_database( $source_database = null ) {
		global $wpdb;
		if ( is_null( $source_database ) ) {
			$source_database = new BBP_Tests_PHPWind_Source_DB( $wpdb );
		}

		$use_test_database = function( $converter, $platform ) use ( $source_database ) {
			if ( 'PHPWind' === $platform ) {
				$set_opdb = Closure::bind(
					function( $target, $database ) {
						$target->opdb = $database;
					},
					null,
					'BBP_Converter_Base'
				);
				$set_opdb( $converter, $source_database );
			}

			return $converter;
		};

		add_filter( 'bbp_new_converter', $use_test_database, 10, 2 );

		try {
			bbp_user_maybe_convert_pass();
		} finally {
			remove_filter( 'bbp_new_converter', $use_test_database, 10 );
		}
	}
}
