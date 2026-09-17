<?php

/**
 * Source database stub for phpFox 3 password tests.
 */
class BBP_Tests_Admin_Converters_PHPFox3_Source_DB {

	public $prefix = 'phpfox_';
	public $salt;
	public $connect_result = true;
	public $connect_calls = 0;
	public $last_query = '';

	public function db_connect( $allow_bail = true ) {
		$this->connect_calls++;
		return $this->connect_result;
	}

	public function prepare( $query, $user_id ) {
		$this->last_query = sprintf( $query, $user_id );
		return $this->last_query;
	}

	public function get_var( $query ) {
		$this->last_query = $query;
		return $this->salt;
	}
}

/**
 * Tests for the phpFox 3 converter.
 *
 * @group converters
 */
class BBP_Tests_Admin_Converters_PHPFox3 extends BBP_UnitTestCase {

	/**
	 * @var PHPFox3
	 */
	protected $converter;

	/**
	 * @var BBP_Tests_Admin_Converters_PHPFox3_Source_DB
	 */
	protected $source_db;

	public function setUp(): void {
		parent::setUp();

		bbp_setup_converter();
		require_once BBP_PLUGIN_DIR . 'includes/admin/converters/PHPFox3.php';

		$reflection      = new ReflectionClass( 'PHPFox3' );
		$this->converter = $reflection->newInstanceWithoutConstructor();
		$this->source_db = new BBP_Tests_Admin_Converters_PHPFox3_Source_DB();

		$set_databases = Closure::bind(
			function( $converter, $source_db ) {
				$converter->wpdb = bbp_db();
				$converter->opdb = $source_db;
			},
			null,
			'BBP_Converter_Base'
		);
		$set_databases( $this->converter, $this->source_db );
	}

	/**
	 * @covers PHPFox3::setup_globals
	 * @ticket BBP3684
	 */
	public function test_password_salt_and_class_mappings() {
		$this->converter->setup_globals();

		$get_field_map = Closure::bind(
			function( $converter ) {
				return $converter->field_map;
			},
			null,
			'BBP_Converter_Base'
		);
		$field_map     = $get_field_map( $this->converter );
		$password_maps = wp_filter_object_list( $field_map, array( 'to_fieldname' => '_bbp_password' ) );
		$salt_maps     = wp_filter_object_list( $field_map, array( 'from_fieldname' => 'password_salt' ) );
		$class_maps    = wp_filter_object_list( $field_map, array( 'to_fieldname' => '_bbp_class' ) );
		$password_map  = reset( $password_maps );
		$salt_map      = reset( $salt_maps );
		$class_map     = reset( $class_maps );

		$this->assertCount( 1, $password_maps );
		$this->assertCount( 1, $salt_maps );
		$this->assertCount( 1, $class_maps );
		$this->assertSame( 'password', $password_map['from_fieldname'] );
		$this->assertSame( 'callback_savepass', $password_map['callback_method'] );
		$this->assertSame( '', $salt_map['to_fieldname'] );
		$this->assertSame( 'PHPFox3', $class_map['default'] );
	}

	/**
	 * @covers PHPFox3::callback_savepass
	 * @ticket BBP3684
	 */
	public function test_callback_savepass_preserves_password_salt() {
		$salt     = '\\A"';
		$metadata = $this->converter->callback_savepass(
			'ef6b1567d92e2c343799e8ac032ceb32',
			array( 'password_salt' => $salt )
		);
		$user_id  = $this->factory->user->create();

		$this->assertSame( wp_slash( $salt ), $metadata['salt'] );
		update_user_meta( $user_id, '_bbp_password', $metadata );
		$this->assertSame( $salt, get_user_meta( $user_id, '_bbp_password', true )['salt'] );
	}

	/**
	 * @covers PHPFox3::authenticate_pass
	 * @ticket BBP3684
	 */
	public function test_authenticate_pass_with_native_hash() {
		$metadata = serialize(
			array(
				'hash' => 'ef6b1567d92e2c343799e8ac032ceb32',
				'salt' => '\\A"',
			)
		);

		$this->assertTrue( $this->converter->authenticate_pass( 'Correct Horse Battery Staple!', $metadata ) );
		$this->assertFalse( $this->converter->authenticate_pass( 'incorrect', $metadata ) );
	}

	/**
	 * @covers PHPFox3::authenticate_pass
	 * @ticket BBP3684
	 */
	public function test_authenticate_pass_retains_historical_verifier() {
		$metadata = serialize(
			array(
				'hash' => 'bbfe6ec2b2a9ec6d80c0e8c3c809f56d',
				'salt' => '\\A"',
			)
		);

		$this->assertTrue( $this->converter->authenticate_pass( 'Correct Horse Battery Staple!', $metadata ) );
	}

	/**
	 * @covers PHPFox3::authenticate_pass
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
	 * @covers PHPFox3::callback_pass
	 * @covers PHPFox3::authenticate_pass
	 * @ticket BBP3684
	 */
	public function test_callback_pass_upgrades_new_import_without_source_database() {
		global $wpdb;

		$password = 'Correct Horse Battery Staple!';
		$user_id  = $this->factory->user->create( array( 'user_login' => 'phpfox-new-import' ) );

		$wpdb->update( $wpdb->users, array( 'user_pass' => '' ), array( 'ID' => $user_id ) );
		update_user_meta(
			$user_id,
			'_bbp_password',
			$this->converter->callback_savepass(
				'ef6b1567d92e2c343799e8ac032ceb32',
				array( 'password_salt' => '\\A"' )
			)
		);
		update_user_meta( $user_id, '_bbp_class', 'PHPFox3' );
		clean_user_cache( $user_id );

		$this->source_db->connect_result = false;
		$this->converter->callback_pass( 'phpfox-new-import', $password );

		$this->assertSame( 0, $this->source_db->connect_calls );
		$this->assertTrue( wp_check_password( $password, get_userdata( $user_id )->user_pass, $user_id ) );
		$this->assertFalse( metadata_exists( 'user', $user_id, '_bbp_password' ) );
		$this->assertFalse( metadata_exists( 'user', $user_id, '_bbp_class' ) );
	}

	/**
	 * @covers PHPFox3::callback_pass
	 * @covers PHPFox3::authenticate_pass
	 * @ticket BBP3684
	 */
	public function test_callback_pass_recovers_old_import_from_source_database() {
		global $wpdb;

		$password = 'Correct Horse Battery Staple!';
		$user_id  = $this->factory->user->create( array( 'user_login' => 'phpfox-old-import' ) );

		$wpdb->update( $wpdb->users, array( 'user_pass' => '' ), array( 'ID' => $user_id ) );
		update_user_meta( $user_id, '_bbp_password', array( 'hash' => 'ef6b1567d92e2c343799e8ac032ceb32', 'salt' => null ) );
		update_user_meta( $user_id, '_bbp_class', 'PHPFox3' );
		update_user_meta( $user_id, '_bbp_old_user_id', 42 );
		clean_user_cache( $user_id );

		$this->source_db->salt = '\\A"';
		$this->converter->callback_pass( 'phpfox-old-import', $password );

		$this->assertSame( 1, $this->source_db->connect_calls );
		$this->assertStringContainsString( 'phpfox_user', $this->source_db->last_query );
		$this->assertStringContainsString( '42', $this->source_db->last_query );
		$this->assertTrue( wp_check_password( $password, get_userdata( $user_id )->user_pass, $user_id ) );
		$this->assertFalse( metadata_exists( 'user', $user_id, '_bbp_password' ) );
		$this->assertFalse( metadata_exists( 'user', $user_id, '_bbp_class' ) );
	}

	/**
	 * @covers PHPFox3::callback_pass
	 * @ticket BBP3684
	 */
	public function test_failed_login_retains_recovered_salt() {
		global $wpdb;

		$user_id = $this->factory->user->create( array( 'user_login' => 'phpfox-recovered-failure' ) );

		$wpdb->update( $wpdb->users, array( 'user_pass' => '' ), array( 'ID' => $user_id ) );
		update_user_meta( $user_id, '_bbp_password', array( 'hash' => 'ef6b1567d92e2c343799e8ac032ceb32', 'salt' => null ) );
		update_user_meta( $user_id, '_bbp_class', 'PHPFox3' );
		update_user_meta( $user_id, '_bbp_old_user_id', 42 );
		clean_user_cache( $user_id );

		$this->source_db->salt = '\\A"';
		$this->converter->callback_pass( 'phpfox-recovered-failure', 'incorrect' );

		$this->assertSame( '', get_userdata( $user_id )->user_pass );
		$this->assertSame( '\\A"', get_user_meta( $user_id, '_bbp_password', true )['salt'] );
		$this->assertSame( 'PHPFox3', get_user_meta( $user_id, '_bbp_class', true ) );
	}

	/**
	 * @covers PHPFox3::callback_pass
	 * @ticket BBP3684
	 */
	public function test_unavailable_source_database_preserves_old_metadata() {
		global $wpdb;

		$user_id  = $this->factory->user->create( array( 'user_login' => 'phpfox-source-unavailable' ) );
		$metadata = array( 'hash' => 'ef6b1567d92e2c343799e8ac032ceb32', 'salt' => null );

		$wpdb->update( $wpdb->users, array( 'user_pass' => '' ), array( 'ID' => $user_id ) );
		update_user_meta( $user_id, '_bbp_password', $metadata );
		update_user_meta( $user_id, '_bbp_class', 'PHPFox3' );
		update_user_meta( $user_id, '_bbp_old_user_id', 42 );
		clean_user_cache( $user_id );

		$this->source_db->connect_result = false;
		$this->converter->callback_pass( 'phpfox-source-unavailable', 'Correct Horse Battery Staple!' );

		$this->assertSame( 1, $this->source_db->connect_calls );
		$this->assertSame( '', get_userdata( $user_id )->user_pass );
		$this->assertSame( $metadata, get_user_meta( $user_id, '_bbp_password', true ) );
		$this->assertSame( 'PHPFox3', get_user_meta( $user_id, '_bbp_class', true ) );
	}

	/**
	 * @covers PHPFox3::callback_pass
	 * @ticket BBP3684
	 */
	public function test_existing_wordpress_password_skips_source_database() {
		$user_id  = $this->factory->user->create(
			array(
				'user_login' => 'phpfox-existing-password',
				'user_pass'  => 'wordpress-password',
			)
		);
		$metadata = array( 'hash' => 'ef6b1567d92e2c343799e8ac032ceb32', 'salt' => null );

		update_user_meta( $user_id, '_bbp_password', $metadata );
		update_user_meta( $user_id, '_bbp_class', 'PHPFox3' );
		update_user_meta( $user_id, '_bbp_old_user_id', 42 );

		$this->source_db->salt = '\\A"';
		$this->converter->callback_pass( 'phpfox-existing-password', 'wordpress-password' );

		$this->assertSame( 0, $this->source_db->connect_calls );
		$this->assertTrue( wp_check_password( 'wordpress-password', get_userdata( $user_id )->user_pass, $user_id ) );
		$this->assertSame( $metadata, get_user_meta( $user_id, '_bbp_password', true ) );
		$this->assertSame( 'PHPFox3', get_user_meta( $user_id, '_bbp_class', true ) );
	}
}
