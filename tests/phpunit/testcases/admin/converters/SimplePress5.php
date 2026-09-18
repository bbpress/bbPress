<?php

/**
 * Source database stub for Simple:Press 5 password tests.
 */
class BBP_Tests_Admin_Converters_SimplePress5_Source_DB {

	public $connect_calls = 0;

	public function db_connect( $allow_bail = true ) {
		$this->connect_calls++;
		return false;
	}
}

/**
 * Tests for the Simple:Press 5 converter.
 *
 * @group converters
 */
class BBP_Tests_Admin_Converters_SimplePress5 extends BBP_UnitTestCase {

	/**
	 * @var SimplePress5
	 */
	protected $converter;

	/**
	 * @var BBP_Tests_Admin_Converters_SimplePress5_Source_DB
	 */
	protected $source_db;

	public function setUp(): void {
		parent::setUp();

		bbp_setup_converter();
		require_once BBP_PLUGIN_DIR . 'includes/admin/converters/SimplePress5.php';

		$reflection      = new ReflectionClass( 'SimplePress5' );
		$this->converter = $reflection->newInstanceWithoutConstructor();
		$this->source_db = new BBP_Tests_Admin_Converters_SimplePress5_Source_DB();

		$set_databases = Closure::bind(
			function( $converter, $source_db ) {
				$converter->wpdb     = bbp_db();
				$converter->opdb     = $source_db;
				$converter->max_rows = 100;
			},
			null,
			'BBP_Converter_Base'
		);
		$set_databases( $this->converter, $this->source_db );
	}

	public function tearDown(): void {
		unset( $_POST['log'], $_POST['pwd'] );
		delete_option( '_bbp_converter_platform' );

		parent::tearDown();
	}

	/**
	 * @covers SimplePress5::setup_globals
	 * @ticket BBP3684
	 */
	public function test_password_mapping_uses_wordpress_users_table_hash() {
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
		$class_maps    = wp_filter_object_list( $field_map, array( 'to_fieldname' => '_bbp_class' ) );
		$password_map  = reset( $password_maps );

		$this->assertCount( 1, $password_maps );
		$this->assertCount( 0, $class_maps );
		$this->assertSame( 'users', $password_map['from_tablename'] );
		$this->assertSame( 'user_pass', $password_map['from_fieldname'] );
		$this->assertArrayNotHasKey( 'callback_method', $password_map );
	}

	/**
	 * @covers BBP_Converter_Base::clean_passwords
	 * @ticket BBP3684
	 */
	public function test_import_cleanup_restores_historical_wordpress_phpass_hash() {
		global $wpdb, $wp_version;

		$password = 'Correct Horse Battery Staple';
		$hash     = '$P$912345678.tn7RRNRFBjgoWufz9MKM1';
		$user_id  = $this->factory->user->create( array( 'user_login' => 'simplepress-phpass' ) );

		$wpdb->update( $wpdb->users, array( 'user_pass' => '' ), array( 'ID' => $user_id ) );
		update_user_meta( $user_id, '_bbp_password', $hash );
		clean_user_cache( $user_id );

		$this->assertFalse( $this->converter->clean_passwords( 0 ) );
		$this->assertSame( $hash, get_userdata( $user_id )->user_pass );
		$this->assertInstanceOf( 'WP_User', wp_authenticate_username_password( null, 'simplepress-phpass', $password ) );

		$resulting_hash = get_userdata( $user_id )->user_pass;

		$this->assertTrue( wp_check_password( $password, $resulting_hash, $user_id ) );

		if ( version_compare( $wp_version, '6.8', '>=' ) ) {
			$this->assertNotSame( $hash, $resulting_hash );
		} else {
			$this->assertSame( $hash, $resulting_hash );
		}

		$this->assert_password_metadata_removed( $user_id );
		$this->assertSame( 0, $this->source_db->connect_calls );
	}

	/**
	 * @covers BBP_Converter_Base::clean_passwords
	 * @ticket BBP3684
	 */
	public function test_failed_login_preserves_imported_wordpress_hash() {
		global $wpdb;

		$hash    = '$P$912345678.tn7RRNRFBjgoWufz9MKM1';
		$user_id = $this->factory->user->create( array( 'user_login' => 'simplepress-failed' ) );

		$wpdb->update( $wpdb->users, array( 'user_pass' => '' ), array( 'ID' => $user_id ) );
		update_user_meta( $user_id, '_bbp_password', $hash );
		clean_user_cache( $user_id );

		$this->converter->clean_passwords( 0 );
		$user = wp_authenticate_username_password( null, 'simplepress-failed', 'incorrect' );

		$this->assertWPError( $user );
		$this->assertSame( $hash, get_userdata( $user_id )->user_pass );
		$this->assert_password_metadata_removed( $user_id );
		$this->assertSame( 0, $this->source_db->connect_calls );
	}

	/**
	 * @covers BBP_Converter_Base::clean_passwords
	 * @ticket BBP3684
	 */
	public function test_import_cleanup_allows_email_login_without_source_database() {
		global $wpdb;

		$password = 'simplepress-email-password';
		$hash     = wp_hash_password( $password );
		$user_id  = $this->factory->user->create( array( 'user_email' => 'simplepress@example.org' ) );

		$wpdb->update( $wpdb->users, array( 'user_pass' => '' ), array( 'ID' => $user_id ) );
		update_user_meta( $user_id, '_bbp_password', $hash );
		clean_user_cache( $user_id );

		$this->converter->clean_passwords( 0 );
		$user = wp_authenticate_email_password( null, 'simplepress@example.org', $password );

		$this->assertInstanceOf( 'WP_User', $user );
		$this->assertSame( $user_id, $user->ID );
		$this->assert_password_metadata_removed( $user_id );
		$this->assertSame( 0, $this->source_db->connect_calls );
	}

	/**
	 * @covers BBP_Converter_Base::clean_passwords
	 * @ticket BBP3684
	 */
	public function test_legacy_wordpress_md5_hash_uses_normal_login_upgrade() {
		global $wpdb;

		$password = 'simplepress-legacy-password';
		$hash     = md5( $password );
		$user_id  = $this->factory->user->create( array( 'user_login' => 'simplepress-legacy' ) );

		$wpdb->update( $wpdb->users, array( 'user_pass' => '' ), array( 'ID' => $user_id ) );
		update_user_meta( $user_id, '_bbp_password', $hash );
		clean_user_cache( $user_id );

		$this->converter->clean_passwords( 0 );
		$user = wp_authenticate_username_password( null, 'simplepress-legacy', $password );

		$this->assertInstanceOf( 'WP_User', $user );
		$this->assertNotSame( $hash, get_userdata( $user_id )->user_pass );
		$this->assertTrue( wp_check_password( $password, get_userdata( $user_id )->user_pass, $user_id ) );
		$this->assert_password_metadata_removed( $user_id );
	}

	/**
	 * @covers BBP_Converter_Base::clean_passwords
	 * @covers ::bbp_user_maybe_convert_pass
	 * @ticket BBP3684
	 */
	public function test_malformed_serialized_metadata_is_not_installed_as_a_password() {
		global $wpdb;

		$metadata = array( 'hash' => array() );
		$user_id  = $this->factory->user->create( array( 'user_login' => 'simplepress-malformed' ) );

		$wpdb->update( $wpdb->users, array( 'user_pass' => '' ), array( 'ID' => $user_id ) );
		update_user_meta( $user_id, '_bbp_password', $metadata );
		update_user_meta( $user_id, '_bbp_class', 'SimplePress5' );
		clean_user_cache( $user_id );

		$this->converter->clean_passwords( 0 );
		$_POST['log'] = 'simplepress-malformed';
		$_POST['pwd'] = 'incorrect';
		bbp_user_maybe_convert_pass();

		$this->assertSame( '', get_userdata( $user_id )->user_pass );
		$this->assertSame( $metadata, get_user_meta( $user_id, '_bbp_password', true ) );
		$this->assertSame( 'SimplePress5', get_user_meta( $user_id, '_bbp_class', true ) );
		$this->assertSame( 0, $this->source_db->connect_calls );
	}

	/**
	 * @covers ::bbp_user_maybe_convert_pass
	 * @ticket BBP3684
	 */
	public function test_saved_platform_fallback_does_not_accept_serialized_metadata() {
		global $wpdb;

		$metadata = array( 'hash' => 'not-a-simplepress-password' );
		$user_id  = $this->factory->user->create( array( 'user_login' => 'simplepress-fallback' ) );

		$wpdb->update( $wpdb->users, array( 'user_pass' => '' ), array( 'ID' => $user_id ) );
		update_user_meta( $user_id, '_bbp_password', $metadata );
		update_option( '_bbp_converter_platform', 'SimplePress5' );
		clean_user_cache( $user_id );

		$_POST['log'] = 'simplepress-fallback';
		$_POST['pwd'] = 'incorrect';
		bbp_user_maybe_convert_pass();

		$this->assertSame( '', get_userdata( $user_id )->user_pass );
		$this->assertSame( $metadata, get_user_meta( $user_id, '_bbp_password', true ) );
		$this->assertFalse( metadata_exists( 'user', $user_id, '_bbp_class' ) );
		$this->assertSame( 0, $this->source_db->connect_calls );
	}

	/**
	 * Assert that importer password metadata was removed from persistent storage.
	 *
	 * WordPress 6.0 does not clear the user-meta cache in clean_user_cache().
	 * The converter cleanup uses a direct query, so inspect the database directly.
	 *
	 * @param int $user_id User ID.
	 */
	private function assert_password_metadata_removed( $user_id ) {
		global $wpdb;

		$count = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->usermeta} WHERE user_id = %d AND meta_key = %s", $user_id, '_bbp_password' ) );

		$this->assertSame( 0, (int) $count );
	}
}
