<?php

bbp_setup_converter();

class BBP_Tests_Admin_Converters_Unserialize_Wakeup {
	public static $woke = false;

	public function __wakeup() {
		self::$woke = true;
	}
}

class BBP_Tests_Admin_Converters_Ajax_Response_Exception extends Exception {
}

class BBP_Tests_Admin_Converters_Base_Converter extends BBP_Converter_Base {
	public function info() {
		return '';
	}

	protected function authenticate_pass( $password, $hash ) {
		return false;
	}

	public function get_pass_array( $value ) {
		return $this->unserialize_pass( $value );
	}

	public function insert_converted_post( $post_data ) {
		return $this->insert_post( $post_data );
	}

	public function invoke_callback_slug( $value ) {
		return $this->callback_slug( $value );
	}

	public function invoke_callback_negative( $value ) {
		return $this->callback_negative( $value );
	}

	public function invoke_callback_html( $value ) {
		return $this->callback_html( $value );
	}

	public function invoke_callback_null( $value ) {
		return $this->callback_null( $value );
	}

	public function invoke_callback_datetime( $value ) {
		return $this->callback_datetime( $value );
	}

	public function set_max_rows( $max_rows ) {
		$this->max_rows = $max_rows;
	}
}

class BBP_Tests_Admin_Converters_Base_Delegating_Converter extends BBP_Tests_Admin_Converters_Base_Converter {
	public $conversion_calls = array();

	public function convert_table( $to_type, $start ) {
		$this->conversion_calls[] = array( $to_type, $start );

		return $to_type . ':' . $start;
	}
}

class BBP_Tests_Admin_Converters_Base_Import_Converter extends BBP_Tests_Admin_Converters_Base_Converter {
	public function get_source_error() {
		return $this->opdb->last_error;
	}

	public function source_query( $query ) {
		$this->opdb->db_connect( false );
		return $this->opdb->query( $query );
	}

	public function source_insert( $table, $data ) {
		return $this->opdb->insert( $table, $data );
	}

	public function source_var( $query ) {
		return $this->opdb->get_var( $query );
	}

	public function setup_globals() {
		$this->opdb         = new BBP_Tests_Admin_Converters_Base_Import_Database( DB_USER, DB_PASSWORD, DB_NAME, DB_HOST );
		$this->opdb->prefix = get_option( '_bbp_converter_db_prefix' );
		foreach ( array( 'user_login', 'user_email', 'user_pass' ) as $field ) {
			$this->field_map[] = array(
				'from_tablename' => 'bbp_converter_fixture',
				'from_fieldname' => $field,
				'to_type'        => 'user',
				'to_fieldname'   => $field
			);
		}
		$this->field_map[] = array(
			'from_tablename' => 'bbp_converter_fixture',
			'from_fieldname' => 'author_id',
			'to_type'        => 'user',
			'to_fieldname'   => '_bbp_old_user_id'
		);
		$this->field_map[] = array(
			'from_tablename'  => 'bbp_converter_fixture',
			'from_fieldname'  => 'author_id',
			'to_type'         => 'topic',
			'to_fieldname'    => 'post_author',
			'callback_method' => 'callback_userid'
		);
		$this->field_map[] = array(
			'from_tablename' => 'bbp_converter_fixture',
			'from_fieldname' => 'title',
			'to_type'        => 'topic',
			'to_fieldname'   => 'post_title'
		);
		$this->field_map[] = array(
			'from_tablename' => 'bbp_converter_fixture',
			'from_fieldname' => 'content',
			'to_type'        => 'topic',
			'to_fieldname'   => 'post_content'
		);
	}
}

class BBP_Tests_Admin_Converters_Base_Import_Database extends BBP_Converter_DB {
	private $test_connected = false;

	public function get_connection_settings() {
		return array(
			'user'     => $this->dbuser,
			'password' => $this->dbpassword,
			'name'     => $this->dbname,
			'host'     => $this->dbhost,
			'ready'    => $this->ready,
			'connected' => $this->test_connected,
		);
	}

	public function db_connect( $allow_bail = true ) {
		if ( $this->test_connected ) {
			return true;
		}

		$this->test_connected = parent::db_connect( $allow_bail );
		return $this->test_connected;
	}
}

class BBP_Tests_Admin_Converters_Base_Source_Database {
	public $prefix      = '';
	public $connections = 0;

	public function db_connect( $allow_bail = true ) {
		++$this->connections;

		return true;
	}
}

/**
 * Tests for the shared converter base.
 *
 * @group converters
 */
class BBP_Tests_Admin_Converters_Base extends BBP_UnitTestCase {
	protected static $anonymous_sync_table;

	public static function setUpBeforeClass(): void {
		parent::setUpBeforeClass();

		global $wpdb;

		self::$anonymous_sync_table = $wpdb->prefix . 'bbp_converter_anonymous_authors_test';
		$charset_collate            = $wpdb->get_charset_collate();

		$wpdb->query( "DROP TABLE IF EXISTS " . self::$anonymous_sync_table ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$wpdb->query( "CREATE TABLE " . self::$anonymous_sync_table . " (
			meta_id mediumint(8) unsigned NOT NULL AUTO_INCREMENT,
			value_type varchar(25) NULL,
			value_id bigint(20) unsigned NOT NULL DEFAULT '0',
			meta_key varchar(75) NULL,
			meta_value varchar(75) NULL,
			PRIMARY KEY (meta_id),
			KEY value_id (value_id),
			KEY meta_join (meta_key(75), meta_value(75))
		) ENGINE=InnoDB {$charset_collate}" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	public static function tearDownAfterClass(): void {
		global $wpdb;

		$wpdb->query( "DROP TABLE IF EXISTS " . self::$anonymous_sync_table ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		parent::tearDownAfterClass();
	}

	/**
	 * @covers BBP_Converter_DB::__construct
	 * @ticket 3706
	 */
	public function test_converter_database_stores_credentials_without_connecting() {
		$database = new BBP_Tests_Admin_Converters_Base_Import_Database( 'legacy-user', 'legacy-password', 'legacy-name', 'legacy.invalid:3307' );

		$this->assertSame(
			array(
				'user'     => 'legacy-user',
				'password' => 'legacy-password',
				'name'     => 'legacy-name',
				'host'     => 'legacy.invalid:3307',
				'ready'    => false,
				'connected' => false,
			),
			$database->get_connection_settings()
		);
	}

	/**
	 * @covers BBP_Converter_Base::convert_forums
	 * @covers BBP_Converter_Base::convert_topics
	 * @covers BBP_Converter_Base::convert_replies
	 * @covers BBP_Converter_Base::convert_users
	 * @covers BBP_Converter_Base::convert_tags
	 * @covers BBP_Converter_Base::convert_forum_subscriptions
	 * @covers BBP_Converter_Base::convert_topic_subscriptions
	 * @covers BBP_Converter_Base::convert_favorites
	 * @ticket 3706
	 */
	public function test_conversion_methods_delegate_type_and_start_row() {
		$converter = new BBP_Tests_Admin_Converters_Base_Delegating_Converter();

		$this->assertSame( 'forum:1', $converter->convert_forums() );
		$this->assertSame( 'topic:2', $converter->convert_topics( 2 ) );
		$this->assertSame( 'reply:3', $converter->convert_replies( 3 ) );
		$this->assertSame( 'user:4', $converter->convert_users( 4 ) );
		$this->assertSame( 'tags:5', $converter->convert_tags( 5 ) );
		$this->assertSame( 'forum_subscriptions:6', $converter->convert_forum_subscriptions( 6 ) );
		$this->assertSame( 'topic_subscriptions:7', $converter->convert_topic_subscriptions( 7 ) );
		$this->assertSame( 'favorites:8', $converter->convert_favorites( 8 ) );
		$this->assertSame(
			array(
				array( 'forum', 1 ),
				array( 'topic', 2 ),
				array( 'reply', 3 ),
				array( 'user', 4 ),
				array( 'tags', 5 ),
				array( 'forum_subscriptions', 6 ),
				array( 'topic_subscriptions', 7 ),
				array( 'favorites', 8 ),
			),
			$converter->conversion_calls
		);
	}

	/**
	 * @covers BBP_Converter_Base::callback_slug
	 * @ticket 3706
	 */
	public function test_slug_callback_normalizes_values() {
		$converter = new BBP_Tests_Admin_Converters_Base_Converter();

		$this->assertSame( 'hello-world', $converter->invoke_callback_slug( 'Hello World!' ) );
	}

	/**
	 * @covers BBP_Converter_Base::callback_negative
	 * @ticket 3706
	 */
	public function test_negative_callback_clamps_negative_values() {
		$converter = new BBP_Tests_Admin_Converters_Base_Converter();

		$this->assertSame( 0, $converter->invoke_callback_negative( -1 ) );
		$this->assertSame( 0, $converter->invoke_callback_negative( '-1' ) );
		$this->assertSame( 0, $converter->invoke_callback_negative( -0.5 ) );
		$this->assertSame( 0, $converter->invoke_callback_negative( 0 ) );
		$this->assertSame( 2, $converter->invoke_callback_negative( 2 ) );
		$this->assertNull( $converter->invoke_callback_negative( null ) );
	}

	/**
	 * @covers BBP_Converter_Base::callback_html
	 * @ticket 3706
	 */
	public function test_html_callback_converts_bbcode() {
		require_once BBP_PLUGIN_DIR . 'includes/admin/actions.php';

		$converter = new BBP_Tests_Admin_Converters_Base_Converter();

		try {
			$this->assertSame( '<b>Bold</b>', $converter->invoke_callback_html( '[b]Bold[/b]' ) );
		} finally {
			$instance = new ReflectionProperty( 'BBCode', 'instance' );
			if ( PHP_VERSION_ID < 80100 ) {
				$instance->setAccessible( true );
			}
			$instance->setValue( null, null );
		}
	}

	/**
	 * @covers BBP_Converter_Base::callback_null
	 * @ticket 3706
	 */
	public function test_null_callback_only_normalizes_null() {
		$converter = new BBP_Tests_Admin_Converters_Base_Converter();

		$this->assertSame( '', $converter->invoke_callback_null( null ) );
		$this->assertSame( '', $converter->invoke_callback_null( '' ) );
		$this->assertSame( 0, $converter->invoke_callback_null( 0 ) );
		$this->assertSame( '0', $converter->invoke_callback_null( '0' ) );
		$this->assertFalse( $converter->invoke_callback_null( false ) );
	}

	/**
	 * @covers BBP_Converter_Base::callback_datetime
	 * @ticket 3706
	 */
	public function test_datetime_callback_normalizes_timestamps_and_strings() {
		$converter   = new BBP_Tests_Admin_Converters_Base_Converter();
		$old_timezone = date_default_timezone_get();

		try {
			date_default_timezone_set( 'UTC' );
			$this->assertSame( '2000-01-01 00:00:00', $converter->invoke_callback_datetime( 946684800 ) );
			$this->assertSame( '2000-01-01 00:00:00', $converter->invoke_callback_datetime( '946684800' ) );
			$this->assertSame( '2000-01-01 00:00:00', $converter->invoke_callback_datetime( 'January 1, 2000 UTC' ) );
			$this->assertSame( '1970-01-01 00:00:00', $converter->invoke_callback_datetime( 'not a date' ) );
		} finally {
			date_default_timezone_set( $old_timezone );
		}
	}

	/**
	 * @covers BBP_Converter::process_callback
	 */
	public function test_invalid_source_prefix_is_rejected_before_import() {
		bbp_setup_converter();
		$old_post    = $_POST;
		$old_request = $_REQUEST;

		// The admin capability mapping is unavailable in this test bootstrap.
		remove_filter( 'map_meta_cap', 'bbp_map_meta_caps', 10 );
		$map_meta_cap = function( $caps, $cap ) {
			return 'bbp_tools_import_page' === $cap ? array( 'exist' ) : $caps;
		};
		$wp_die_ajax_handler = function() {
			return function() {
				throw new Exception( 'AJAX response complete' );
			};
		};
		add_filter( 'map_meta_cap', $map_meta_cap, 10, 2 );
		add_filter( 'wp_doing_ajax', '__return_true' );
		add_filter( 'wp_die_ajax_handler', $wp_die_ajax_handler );

		$user_id = $this->factory->user->create( array( 'role' => 'administrator' ) );
		$this->set_current_user( $user_id );
		$_POST = array(
			'_bbp_converter_db_prefix' => 'wp-bb_',
			'_ajax_nonce'             => wp_create_nonce( 'bbp_converter_process' )
		);
		$_REQUEST = $_POST;

		ob_start();
		try {
			( new BBP_Converter() )->process_callback();
			$this->fail( 'Expected an invalid prefix to be rejected.' );
		} catch ( Exception $error ) {
			$response = json_decode( ob_get_contents(), true );
			$this->assertFalse( $response['success'] );
			$this->assertSame( 'Invalid source database table prefix.', $response['data']['message'] );
		} finally {
			ob_end_clean();
			$_POST    = $old_post;
			$_REQUEST = $old_request;
			remove_filter( 'map_meta_cap', $map_meta_cap, 10 );
			add_filter( 'map_meta_cap', 'bbp_map_meta_caps', 10, 4 );
			remove_filter( 'wp_doing_ajax', '__return_true' );
			remove_filter( 'wp_die_ajax_handler', $wp_die_ajax_handler );
		}
	}

	/**
	 * @covers ::bbp_is_valid_converter_prefix
	 * @covers ::bbp_new_converter
	 */
	public function test_invalid_source_prefix_prevents_converter_loading() {
		$old_prefix = get_option( '_bbp_converter_db_prefix', false );

		$this->assertTrue( bbp_is_valid_converter_prefix( '' ) );
		$this->assertTrue( bbp_is_valid_converter_prefix( 'wp_bb_2' ) );
		$this->assertFalse( bbp_is_valid_converter_prefix( 'wp-bb_' ) );
		$this->assertFalse( bbp_is_valid_converter_prefix( array( 'wp_' ) ) );

		try {
			update_option( '_bbp_converter_db_prefix', 'wp_; SELECT' );
			$this->assertNull( bbp_new_converter( 'MyBB' ) );
			update_option( '_bbp_converter_db_prefix', 'wp_bb_2' );
			$this->assertInstanceOf( 'MyBB', bbp_new_converter( 'MyBB' ) );
		} finally {
			if ( false === $old_prefix ) {
				delete_option( '_bbp_converter_db_prefix' );
			} else {
				update_option( '_bbp_converter_db_prefix', $old_prefix );
			}
		}
	}

	/**
	 * @covers BBP_Converter::setup_options
	 * @covers BBP_Converter::maybe_update_options
	 */
	public function test_valid_prefix_can_replace_an_invalid_saved_prefix() {
		$old_post     = $_POST;
		$old_prefix   = get_option( '_bbp_converter_db_prefix', false );
		$old_platform = get_option( '_bbp_converter_platform', false );
		$setup        = new ReflectionMethod( 'BBP_Converter', 'setup_options' );
		$save         = new ReflectionMethod( 'BBP_Converter', 'maybe_update_options' );
		if ( PHP_VERSION_ID < 80100 ) {
			$setup->setAccessible( true );
			$save->setAccessible( true );
		}

		try {
			update_option( '_bbp_converter_db_prefix', 'invalid-prefix' );
			update_option( '_bbp_converter_platform', 'MyBB' );
			$converter = new BBP_Converter();
			$setup->invoke( $converter );
			$this->assertNull( $converter->converter );

			$_POST = array(
				'_bbp_converter_db_prefix' => 'wp_bb_2',
				'_bbp_converter_platform'  => 'MyBB'
			);
			$save->invoke( $converter );
			$setup->invoke( $converter );
			$this->assertInstanceOf( 'MyBB', $converter->converter );
		} finally {
			$_POST = $old_post;
			if ( false === $old_prefix ) {
				delete_option( '_bbp_converter_db_prefix' );
			} else {
				update_option( '_bbp_converter_db_prefix', $old_prefix );
			}
			if ( false === $old_platform ) {
				delete_option( '_bbp_converter_platform' );
			} else {
				update_option( '_bbp_converter_platform', $old_platform );
			}
		}
	}

	/**
	 * @covers BBP_Converter::maybe_update_options
	 */
	public function test_converter_preserves_database_password_when_saving_options() {
		$old_post     = $_POST;
		$old_password = get_option( '_bbp_converter_db_pass', false );
		$old_autoload = array_key_exists( '_bbp_converter_db_pass', wp_load_alloptions( true ) );
		$save_options = new ReflectionMethod( 'BBP_Converter', 'maybe_update_options' );
		if ( PHP_VERSION_ID < 80100 ) {
			$save_options->setAccessible( true );
		}

		try {
			delete_option( '_bbp_converter_db_pass' );
			$_POST = array( '_bbp_converter_db_pass' => '' );
			$save_options->invoke( new BBP_Converter() );
			$this->assertSame( '', get_option( '_bbp_converter_db_pass' ) );
			$this->assertArrayNotHasKey( '_bbp_converter_db_pass', wp_load_alloptions( true ) );

			foreach ( array( 'spaces %20 <tag> \\ end', '0' ) as $password ) {
				$_POST = array( '_bbp_converter_db_pass' => wp_slash( $password ) );
				$save_options->invoke( new BBP_Converter() );
				$this->assertSame( $password, get_option( '_bbp_converter_db_pass' ) );
				$this->assertArrayNotHasKey( '_bbp_converter_db_pass', wp_load_alloptions( true ) );
			}

			$_POST = array( '_bbp_converter_db_pass' => '' );
			$save_options->invoke( new BBP_Converter() );
			$this->assertSame( '0', get_option( '_bbp_converter_db_pass' ) );

			$_POST = array( '_bbp_converter_db_pass_clear' => '1' );
			$save_options->invoke( new BBP_Converter() );
			$this->assertSame( '', get_option( '_bbp_converter_db_pass' ) );
		} finally {
			$_POST = $old_post;
			if ( false === $old_password ) {
				delete_option( '_bbp_converter_db_pass' );
			} else {
				delete_option( '_bbp_converter_db_pass' );
				add_option( '_bbp_converter_db_pass', $old_password, '', $old_autoload );
			}
		}
	}

	/**
	 * @covers BBP_Converter::admin_head
	 */
	public function test_converter_password_does_not_autoload_or_render() {
		$old_password = get_option( '_bbp_converter_db_pass', false );
		$old_autoload = array_key_exists( '_bbp_converter_db_pass', wp_load_alloptions( true ) );

		try {
			delete_option( '_bbp_converter_db_pass' );
			add_option( '_bbp_converter_db_pass', 'saved secret', '', true );

			if ( function_exists( 'wp_set_option_autoload_values' ) ) {
				( new BBP_Converter() )->admin_head();
				$this->assertArrayNotHasKey( '_bbp_converter_db_pass', wp_load_alloptions( true ) );
			}

			ob_start();
			bbp_converter_setting_callback_dbpass();
			$output = ob_get_clean();
			$this->assertStringNotContainsString( 'saved secret', $output );
			$this->assertStringContainsString( 'value=""', $output );
		} finally {
			delete_option( '_bbp_converter_db_pass' );
			if ( false !== $old_password ) {
				add_option( '_bbp_converter_db_pass', $old_password, '', $old_autoload );
			}
		}
	}

	/**
	 * @covers BBP_Converter_Base::clean
	 */
	public function test_clean_without_sync_table_only_deletes_imported_forum_posts() {
		$native_forum_id   = $this->factory->forum->create();
		$native_topic_id   = $this->factory->topic->create( array( 'post_parent' => $native_forum_id ) );
		$native_reply_id   = $this->factory->reply->create( array( 'post_parent' => $native_topic_id ) );
		$import_forum_id   = $this->factory->forum->create();
		$import_topic_id   = $this->factory->topic->create( array( 'post_parent' => $import_forum_id ) );
		$import_reply_id   = $this->factory->reply->create( array( 'post_parent' => $import_topic_id ) );
		$unrelated_post_id = $this->factory->post->create();

		add_post_meta( $native_forum_id, '_bbp_test_native', 'yes' );
		add_post_meta( $native_topic_id, '_bbp_test_native', 'yes' );
		add_post_meta( $native_reply_id, '_bbp_test_native', 'yes' );
		add_post_meta( $import_forum_id, '_bbp_old_forum_id', 11 );
		add_post_meta( $import_topic_id, '_bbp_old_topic_id', 22 );
		add_post_meta( $import_reply_id, '_bbp_old_reply_id', 33 );
		add_post_meta( $unrelated_post_id, '_bbp_old_topic_id', 44 );

		$converter = new BBP_Tests_Admin_Converters_Base_Converter();
		$converter->sync_table = false;

		$this->assertFalse( $converter->clean() );
		$this->assertNull( get_post( $import_forum_id ) );
		$this->assertNull( get_post( $import_topic_id ) );
		$this->assertNull( get_post( $import_reply_id ) );
		$this->assertNotNull( get_post( $native_forum_id ) );
		$this->assertNotNull( get_post( $native_topic_id ) );
		$this->assertNotNull( get_post( $native_reply_id ) );
		$this->assertNotNull( get_post( $unrelated_post_id ) );
		$this->assertTrue( $converter->clean() );
	}

	/**
	 * @covers BBP_Converter_Base::convert_table
	 */
	public function test_imported_topic_without_account_import_has_anonymous_author() {
		global $wpdb;

		$admin_id = $this->factory->user->create( array( 'role' => 'administrator' ) );
		bbp_set_user_role( $admin_id, bbp_get_keymaster_role() );
		$this->set_current_user( $admin_id );
		delete_option( '_bbp_converter_convert_users' );

		$source_table = $wpdb->prefix . 'bbp_converter_fixture';
		$old_prefix   = get_option( '_bbp_converter_db_prefix', false );
		update_option( '_bbp_converter_db_prefix', $wpdb->prefix );

		try {
			$converter = new BBP_Tests_Admin_Converters_Base_Import_Converter();
			$this->assertNotFalse( $converter->source_query( "CREATE TABLE {$source_table} (author_id bigint(20), title varchar(255), content longtext, user_login varchar(60), user_email varchar(100), user_pass varchar(255))" ), $converter->get_source_error() );
			$converter->source_insert( $source_table, array(
				'author_id' => $admin_id,
				'title'     => 'Imported anonymous topic',
				'content'   => '<table><tr><td>Safe content</td></tr></table><script>bad()</script>'
			) );
			$this->assertSame( 1, (int) $converter->source_var( "SELECT COUNT(*) FROM {$source_table}" ), $converter->get_source_error() );
			$this->assertFalse( $converter->convert_users );
			$this->assertFalse( $converter->convert_table( 'topic', 0 ), $converter->get_source_error() . ' ' . get_option( '_bbp_converter_query' ) );
			$post_id = $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_title = %s ORDER BY ID DESC LIMIT 1", 'Imported anonymous topic' ) );
			$this->assertNotEmpty( $post_id );
			$this->assertSame( 0, (int) get_post_field( 'post_author', $post_id ) );
			$this->assertStringContainsString( '<table><tr><td>Safe content</td></tr></table>', get_post_field( 'post_content', $post_id ) );
			$this->assertStringNotContainsString( '<script>', get_post_field( 'post_content', $post_id ) );
		} finally {
			$converter->source_query( "DROP TABLE IF EXISTS {$source_table}" );
			if ( false === $old_prefix ) {
				delete_option( '_bbp_converter_db_prefix' );
			} else {
				update_option( '_bbp_converter_db_prefix', $old_prefix );
			}
		}
	}

	/**
	 * @covers BBP_Converter_Base::convert_table
	 */
	public function test_explicitly_granted_site_admin_can_import_a_user() {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'Requires multisite.' );
		}

		global $wpdb;
		$admin_id = $this->factory->user->create( array( 'role' => 'administrator' ) );
		bbp_set_user_role( $admin_id, bbp_get_keymaster_role() );
		$this->set_current_user( $admin_id );
		$this->assertFalse( current_user_can( 'bbp_tools_import_users' ) );

		$user = get_userdata( $admin_id );
		$user->add_cap( 'bbp_tools_import_users' );
		$this->set_current_user( 0 );
		$this->set_current_user( $admin_id );
		$this->assertTrue( current_user_can( 'bbp_tools_import_users' ) );

		$source_table = $wpdb->prefix . 'bbp_converter_fixture';
		$old_prefix   = get_option( '_bbp_converter_db_prefix', false );
		update_option( '_bbp_converter_db_prefix', $wpdb->prefix );
		update_option( '_bbp_converter_convert_users', true );

		try {
			$converter = new BBP_Tests_Admin_Converters_Base_Import_Converter();
			$this->assertNotFalse( $converter->source_query( "CREATE TABLE {$source_table} (author_id bigint(20), title varchar(255), content longtext, user_login varchar(60), user_email varchar(100), user_pass varchar(255))" ), $converter->get_source_error() );
			$converter->source_insert( $source_table, array(
				'author_id' => 42,
				'user_login' => 'bbp_import_fixture_user',
				'user_email' => 'bbp-import-fixture@example.org',
				'user_pass'  => 'fixture-password'
			) );
			$this->assertTrue( $converter->convert_users );
			$this->assertFalse( $converter->convert_table( 'user', 0 ) );
			$imported_id = username_exists( 'bbp_import_fixture_user' );
			$this->assertNotFalse( $imported_id );
			$this->assertSame( '42', get_user_meta( $imported_id, '_bbp_old_user_id', true ) );
		} finally {
			$converter->source_query( "DROP TABLE IF EXISTS {$source_table}" );
			if ( false === $old_prefix ) {
				delete_option( '_bbp_converter_db_prefix' );
			} else {
				update_option( '_bbp_converter_db_prefix', $old_prefix );
			}
		}
	}

	/**
	 * @covers BBP_Converter_Base::__construct
	 */
	public function test_single_site_account_import_is_opt_in() {
		if ( is_multisite() ) {
			$this->markTestSkipped( 'Requires a single site.' );
		}

		$admin_id = $this->factory->user->create( array( 'role' => 'administrator' ) );
		bbp_set_user_role( $admin_id, bbp_get_keymaster_role() );
		$this->set_current_user( $admin_id );
		delete_option( '_bbp_converter_convert_users' );

		$this->assertTrue( current_user_can( 'bbp_tools_import_users' ) );
		$converter = new BBP_Tests_Admin_Converters_Base_Converter();
		$this->assertFalse( $converter->convert_users );
		$this->assertTrue( $converter->convert_table( 'user', 1 ) );
		$this->assertTrue( $converter->convert_table( 'forum_subscriptions', 1 ) );
		$callback = new ReflectionMethod( 'BBP_Converter_Base', 'callback_userid' );
		if ( PHP_VERSION_ID < 80100 ) {
			$callback->setAccessible( true );
		}
		$this->assertSame( 0, $callback->invoke( $converter, $admin_id ) );

		update_option( '_bbp_converter_convert_users', true );
		$this->assertTrue( ( new BBP_Tests_Admin_Converters_Base_Converter() )->convert_users );
	}
	/**
	 * @covers BBP_Converter_Base::convert_table
	 * @covers BBP_Converter_Base::clean
	 * @covers BBP_Converter::maybe_update_options
	 */
	public function test_multisite_site_admin_cannot_change_network_users_during_import() {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'Requires multisite.' );
		}

		$admin_id = $this->factory->user->create( array( 'role' => 'administrator' ) );
		$network_user_id = $this->factory->user->create();
		$this->set_current_user( $admin_id );
		$this->assertFalse( current_user_can( 'bbp_tools_import_users' ) );
		update_option( '_bbp_converter_convert_users', true );
		update_user_meta( $network_user_id, '_bbp_old_user_id', 42 );

		$converter = new BBP_Tests_Admin_Converters_Base_Converter();
		$this->assertFalse( $converter->convert_users );
		$this->assertTrue( $converter->convert_table( 'user', 1 ) );
		$this->assertTrue( $converter->convert_table( 'forum_subscriptions', 1 ) );
		$this->assertTrue( $converter->convert_table( 'topic_subscriptions', 1 ) );
		$this->assertTrue( $converter->convert_table( 'favorites', 1 ) );

		$callback = new ReflectionMethod( 'BBP_Converter_Base', 'callback_userid' );
		if ( PHP_VERSION_ID < 80100 ) {
			$callback->setAccessible( true );
		}
		$this->assertSame( 0, $callback->invoke( $converter, $network_user_id ) );

		$converter->clean();
		$this->assertNotFalse( get_userdata( $network_user_id ) );

		$old_post = $_POST;
		$_POST['_bbp_converter_convert_users'] = '1';
		$update_options = new ReflectionMethod( 'BBP_Converter', 'maybe_update_options' );
		if ( PHP_VERSION_ID < 80100 ) {
			$update_options->setAccessible( true );
		}
		try {
			$update_options->invoke( new BBP_Converter() );
		} finally {
			$_POST = $old_post;
		}
		$this->assertTrue( get_option( '_bbp_converter_convert_users' ) );
		update_option( '_bbp_converter_convert_users', false );
		$old_post = $_POST;
		$_POST['_bbp_converter_convert_users'] = '1';
		try {
			$update_options->invoke( new BBP_Converter() );
		} finally {
			$_POST = $old_post;
		}
		$this->assertFalse( get_option( '_bbp_converter_convert_users' ) );

		// An explicit grant also permits account imports for a site admin.
		$user = get_userdata( $admin_id );
		$user->add_cap( 'bbp_tools_import_users' );
		$this->set_current_user( 0 );
		$this->set_current_user( $admin_id );
		$this->assertTrue( current_user_can( 'bbp_tools_import_users' ) );
		$old_post = $_POST;
		$_POST['_bbp_converter_convert_users'] = '1';
		try {
			$update_options->invoke( new BBP_Converter() );
		} finally {
			$_POST = $old_post;
		}
		$this->assertTrue( get_option( '_bbp_converter_convert_users' ) );
		$this->assertTrue( ( new BBP_Tests_Admin_Converters_Base_Converter() )->convert_users );
	}

	/**
	 * @covers BBP_Converter_Base::convert_table
	 * @ticket BBP3684
	 */
	public function test_convert_table_connects_to_source_database() {
		bbp_setup_converter();

		$converter = new BBP_Tests_Admin_Converters_Base_Converter();
		$source_db = new BBP_Tests_Admin_Converters_Base_Source_Database();
		$set_source_db = Closure::bind(
			function( $object, $database ) {
				$object->opdb = $database;
			},
			null,
			'BBP_Converter_Base'
		);
		$set_source_db( $converter, $source_db );

		$converter->convert_table( 'connection_probe', 1 );

		$this->assertSame( 1, $source_db->connections );
	}

	/**
	 * @covers BBP_Converter_Base::insert_post
	 * @ticket BBP3686
	 */
	public function test_insert_post_does_not_increment_imported_counts() {
		$converter = new BBP_Tests_Admin_Converters_Base_Converter();
		$forum_id  = $this->factory->forum->create();

		update_post_meta( $forum_id, '_bbp_topic_count', 4 );

		$topic_id = $converter->insert_converted_post(
			array(
				'post_type'   => bbp_get_topic_post_type(),
				'post_status' => bbp_get_public_status_id(),
				'post_parent' => $forum_id,
				'post_title'  => 'Converted topic',
			)
		);

		$this->assertIsInt( $topic_id );
		$this->assertSame( 4, bbp_get_forum_topic_count( $forum_id, false, true ) );
		$this->assertNull( apply_filters( 'bbp_pre_update_counts_on_transition_post_status', null, 'publish', 'new', get_post( $topic_id ) ) );
	}

	/**
	 * @covers BBP_Converter_Base::insert_post
	 * @ticket BBP3349
	 */
	public function test_insert_post_does_not_send_moderation_notifications() {
		$converter  = new BBP_Tests_Admin_Converters_Base_Converter();
		$forum_id   = $this->factory->forum->create();
		$keymaster  = $this->factory->user->create();
		$mail_count = 0;
		$capture    = function () use ( &$mail_count ) {
			$mail_count++;

			return true;
		};

		bbp_set_user_role( $keymaster, bbp_get_keymaster_role() );
		update_option( '_bbp_moderation_notification_recipient_types', array( 'keymasters' ) );
		add_filter( 'pre_wp_mail', $capture );

		try {
			$topic_id = $converter->insert_converted_post(
				array(
					'post_type'   => bbp_get_topic_post_type(),
					'post_status' => bbp_get_pending_status_id(),
					'post_parent' => $forum_id,
					'post_title'  => 'Converted pending topic',
				)
			);
		} finally {
			remove_filter( 'pre_wp_mail', $capture );
		}

		$this->assertIsInt( $topic_id );
		$this->assertSame( 0, $mail_count );
		$this->assertTrue( bbp_is_moderation_notifications_active() );
	}

	/**
	 * @covers BBP_Converter_Base::insert_post
	 * @ticket BBP3349
	 */
	public function test_insert_post_preserves_existing_suppression_filters() {
		$converter = new BBP_Tests_Admin_Converters_Base_Converter();

		add_filter( 'bbp_pre_update_counts_on_transition_post_status', '__return_false' );
		add_filter( 'bbp_is_moderation_notifications_active', '__return_false' );
		try {
			$converter->insert_converted_post(
				array(
					'post_status' => bbp_get_pending_status_id(),
					'post_title'  => 'Converted pending topic',
					'post_type'   => bbp_get_topic_post_type(),
				)
			);

			$this->assertNotFalse( has_filter( 'bbp_pre_update_counts_on_transition_post_status', '__return_false' ) );
			$this->assertNotFalse( has_filter( 'bbp_is_moderation_notifications_active', '__return_false' ) );
		} finally {
			remove_filter( 'bbp_pre_update_counts_on_transition_post_status', '__return_false' );
			remove_filter( 'bbp_is_moderation_notifications_active', '__return_false' );
		}
	}

	/**
	 * @covers BBP_Converter_Base::insert_post
	 */
	public function test_insert_post_sanitizes_untrusted_imported_markup() {
		$converter = new BBP_Tests_Admin_Converters_Base_Converter();
		$post_id = $converter->insert_converted_post( array(
			'post_type'    => bbp_get_topic_post_type(),
			'post_status'  => bbp_get_public_status_id(),
			'post_title'   => '<script>bad()</script>Safe title',
			'post_content' => '<script>bad()</script><table><tr><td>Safe content</td></tr></table><a href="javascript:bad()">Link</a>',
			'post_excerpt' => '<img src="https://example.org/image.png" onerror="bad()">Safe excerpt',
		) );

		$this->assertIsInt( $post_id );
		$this->assertStringNotContainsString( '<script>', get_post_field( 'post_content', $post_id ) );
		$this->assertStringContainsString( '<table><tr><td>Safe content</td></tr></table>', get_post_field( 'post_content', $post_id ) );
		$this->assertStringNotContainsString( 'javascript:', get_post_field( 'post_content', $post_id ) );
		$this->assertStringNotContainsString( 'onerror', get_post_field( 'post_excerpt', $post_id ) );
		$this->assertStringContainsString( 'https://example.org/image.png', get_post_field( 'post_excerpt', $post_id ) );
		$this->assertStringNotContainsString( '<script>', get_post_field( 'post_title', $post_id ) );
	}

	/**
	 * @covers BBP_Converter_Base::unserialize_pass
	 * @ticket BBP3684
	 */
	public function test_unserialize_pass_validates_password_metadata() {
		$converter = new BBP_Tests_Admin_Converters_Base_Converter();
		$metadata  = array(
			'hash' => 'hash',
			'salt' => ';O:8:"stdClass":0:{}',
		);
		$object_metadata = array(
			'hash'   => 'hash',
			'object' => new BBP_Tests_Admin_Converters_Unserialize_Wakeup(),
		);
		BBP_Tests_Admin_Converters_Unserialize_Wakeup::$woke = false;

		$this->assertSame( $metadata, $converter->get_pass_array( serialize( $metadata ) ) );
		$this->assertFalse( $converter->get_pass_array( array() ) );
		$this->assertFalse( $converter->get_pass_array( 'not serialized' ) );
		$this->assertFalse( $converter->get_pass_array( serialize( 'not an array' ) ) );
		$this->assertFalse( $converter->get_pass_array( serialize( new stdClass() ) ) );
		$this->assertFalse( $converter->get_pass_array( serialize( $object_metadata ) ) );
		$this->assertFalse( $converter->get_pass_array( 'a:1:{s:4:"enum";E:3:"T:A";}' ) );
		$this->assertFalse( BBP_Tests_Admin_Converters_Unserialize_Wakeup::$woke );
	}

	/**
	 * @covers BBP_Converter::maybe_restart
	 * @covers BBP_Converter::setup_options
	 * @ticket BBP3729
	 */
	public function test_completed_converter_does_not_restart_after_lost_response() {
		$old_step     = get_option( '_bbp_converter_step', null );
		$old_finished = get_option( '_bbp_converter_finished', null );
		$old_restart  = isset( $_POST['_bbp_converter_restart'] )
			? $_POST['_bbp_converter_restart']
			: null;

		update_option( '_bbp_converter_step', 0 );
		update_option( '_bbp_converter_finished', true );
		unset( $_POST['_bbp_converter_restart'] );

		$maybe_restart = new ReflectionMethod( 'BBP_Converter', 'maybe_restart' );
		$setup_options = new ReflectionMethod( 'BBP_Converter', 'setup_options' );
		$finished      = new ReflectionProperty( 'BBP_Converter', 'finished' );
		if ( PHP_VERSION_ID < 80100 ) {
			$maybe_restart->setAccessible( true );
			$setup_options->setAccessible( true );
			$finished->setAccessible( true );
		}

		try {
			$converter = new BBP_Converter();
			$maybe_restart->invoke( $converter );
			$setup_options->invoke( $converter );

			$this->assertSame( 0, (int) get_option( '_bbp_converter_step' ) );
			$this->assertTrue( $finished->getValue( $converter ) );
		} finally {
			if ( null === $old_step ) {
				delete_option( '_bbp_converter_step' );
			} else {
				update_option( '_bbp_converter_step', $old_step );
			}

			if ( null === $old_finished ) {
				delete_option( '_bbp_converter_finished' );
			} else {
				update_option( '_bbp_converter_finished', $old_finished );
			}

			if ( null === $old_restart ) {
				unset( $_POST['_bbp_converter_restart'] );
			} else {
				$_POST['_bbp_converter_restart'] = $old_restart;
			}
		}
	}

	/**
	 * @covers BBP_Converter::bump_step
	 * @ticket BBP3730
	 */
	public function test_converter_flushes_cache_when_import_finishes() {
		global $wpdb;

		$parent_forum_id = $this->factory->forum->create();
		$child_forum_id  = $this->factory->forum->create();
		$this->assertSame( 0, (int) get_post_field( 'post_parent', $child_forum_id ) );

		$wpdb->update(
			$wpdb->posts,
			array( 'post_parent' => $parent_forum_id ),
			array( 'ID' => $child_forum_id )
		);
		$this->assertSame( 0, (int) get_post_field( 'post_parent', $child_forum_id ) );

		$converter            = new BBP_Converter();
		$bump_step            = new ReflectionMethod( 'BBP_Converter', 'bump_step' );
		if ( PHP_VERSION_ID < 80100 ) {
			$bump_step->setAccessible( true );
		}

		$converter->step = 1;
		$bump_step->invoke( $converter );
		$this->assertSame( 2, (int) get_option( '_bbp_converter_step' ) );
		$this->assertSame( 0, (int) get_post_field( 'post_parent', $child_forum_id ) );

		$converter->step = $converter->max_steps;
		$bump_step->invoke( $converter );

		$this->assertSame( 0, (int) get_option( '_bbp_converter_step' ) );
		$this->assertTrue( (bool) get_option( '_bbp_converter_finished' ) );
		$this->assertSame( 100, $converter->step_percentage );
		$this->assertSame( 100, $converter->total_percentage );
		$this->assertSame( $parent_forum_id, (int) get_post_field( 'post_parent', $child_forum_id ) );
	}

	/**
	 * @covers BBP_Converter::converter_response
	 * @ticket BBP3729
	 */
	public function test_converter_response_reports_finished_state() {
		$wp_die_ajax_handler = function() {
			return function() {
				throw new BBP_Tests_Admin_Converters_Ajax_Response_Exception( 'AJAX response complete' );
			};
		};
		add_filter( 'wp_doing_ajax', '__return_true' );
		add_filter( 'wp_die_ajax_handler', $wp_die_ajax_handler );

		try {
			$converter = new BBP_Converter();
			$response   = new ReflectionMethod( 'BBP_Converter', 'converter_response' );
			$step_done  = new ReflectionMethod( 'BBP_Converter', 'step_done' );
			if ( PHP_VERSION_ID < 80100 ) {
				$response->setAccessible( true );
				$step_done->setAccessible( true );
			}

			ob_start();
			try {
				$response->invoke( $converter, 'Working' );
				$this->fail( 'Expected the JSON response to terminate the request.' );
			} catch ( BBP_Tests_Admin_Converters_Ajax_Response_Exception $error ) {
				$data = json_decode( ob_get_contents(), true );
				$this->assertTrue( $data['success'] );
				$this->assertFalse( $data['data']['finished'] );
			} finally {
				ob_end_clean();
			}

			ob_start();
			try {
				$step_done->invoke( $converter );
				$this->fail( 'Expected the JSON response to terminate the request.' );
			} catch ( BBP_Tests_Admin_Converters_Ajax_Response_Exception $error ) {
				$data = json_decode( ob_get_contents(), true );
				$this->assertTrue( $data['success'] );
				$this->assertTrue( $data['data']['finished'] );
			} finally {
				ob_end_clean();
			}
		} finally {
			remove_filter( 'wp_doing_ajax', '__return_true' );
			remove_filter( 'wp_die_ajax_handler', $wp_die_ajax_handler );
		}
	}

	/**
	 * @covers BBP_Converter_Base::convert_reply_to_parents
	 * @ticket BBP3729
	 */
	public function test_reply_parent_post_processing_paginates_across_batches() {
		global $wpdb;

		$forum_id        = $this->factory->forum->create();
		$topic_id        = $this->factory->topic->create( array( 'post_parent' => $forum_id ) );
		$parent_reply_id = $this->factory->reply->create( array( 'post_parent' => $topic_id ) );
		$old_reply_id    = 300000 + $parent_reply_id;
		$child_reply_ids = $this->factory->reply->create_many( 3, array( 'post_parent' => $topic_id ) );

		add_post_meta( $parent_reply_id, '_bbp_old_reply_id', $old_reply_id );
		foreach ( $child_reply_ids as $child_reply_id ) {
			add_post_meta( $child_reply_id, '_bbp_old_reply_to_id', $old_reply_id );
			add_post_meta( $child_reply_id, '_bbp_reply_to', $old_reply_id );
		}

		$converter = new BBP_Tests_Admin_Converters_Base_Converter();
		$converter->set_max_rows( 2 );
		$converter->sync_table = false;

		$this->assertFalse( $converter->convert_reply_to_parents( 0 ) );
		$this->assertSame( 3, (int) get_option( '_bbp_converter_rows_in_step' ) );
		update_option( '_bbp_converter_rows_in_step', 2 );
		$this->assertFalse( $converter->convert_reply_to_parents( 2 ) );
		$this->assertSame( 3, (int) get_option( '_bbp_converter_rows_in_step' ) );
		$this->assertTrue( $converter->convert_reply_to_parents( 3 ) );

		foreach ( $child_reply_ids as $child_reply_id ) {
			$this->assertSame( $parent_reply_id, (int) $wpdb->get_var( $wpdb->prepare( "SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = %s", $child_reply_id, '_bbp_reply_to' ) ) );
		}
	}

	/**
	 * @covers BBP_Converter::bump_start
	 * @covers BBP_Converter::setup_options
	 * @covers BBP_Converter_Base::convert_reply_to_parents
	 * @ticket BBP3729
	 */
	public function test_post_processing_resumes_from_saved_offset_across_requests() {
		global $wpdb;

		$forum_id        = $this->factory->forum->create();
		$topic_id        = $this->factory->topic->create( array( 'post_parent' => $forum_id ) );
		$parent_reply_id = $this->factory->reply->create( array( 'post_parent' => $topic_id ) );
		$old_reply_id    = 400000 + $parent_reply_id;
		$child_reply_ids = $this->factory->reply->create_many( 5, array( 'post_parent' => $topic_id ) );

		add_post_meta( $parent_reply_id, '_bbp_old_reply_id', $old_reply_id );
		foreach ( $child_reply_ids as $child_reply_id ) {
			add_post_meta( $child_reply_id, '_bbp_old_reply_to_id', $old_reply_id );
			add_post_meta( $child_reply_id, '_bbp_reply_to', $old_reply_id );
		}

		$option_names = array(
			'_bbp_converter_step',
			'_bbp_converter_start',
			'_bbp_converter_rows',
			'_bbp_converter_rows_in_step',
			'_bbp_converter_platform',
			'_bbp_converter_finished',
		);
		$old_options = array();
		foreach ( $option_names as $option_name ) {
			$old_options[ $option_name ] = get_option( $option_name, null );
		}

		update_option( '_bbp_converter_step', 17 );
		update_option( '_bbp_converter_start', 0 );
		update_option( '_bbp_converter_rows', 2 );
		update_option( '_bbp_converter_rows_in_step', 0 );
		update_option( '_bbp_converter_platform', '' );
		update_option( '_bbp_converter_finished', false );

		$setup_options = new ReflectionMethod( 'BBP_Converter', 'setup_options' );
		$bump_start    = new ReflectionMethod( 'BBP_Converter', 'bump_start' );
		$bump_step     = new ReflectionMethod( 'BBP_Converter', 'bump_step' );
		if ( PHP_VERSION_ID < 80100 ) {
			$setup_options->setAccessible( true );
			$bump_start->setAccessible( true );
			$bump_step->setAccessible( true );
		}

		try {
			$base_converter = new BBP_Tests_Admin_Converters_Base_Converter();
			$base_converter->sync_table = false;

			$this->assertFalse( $base_converter->convert_reply_to_parents( 0 ) );
			$this->assertSame( 5, (int) get_option( '_bbp_converter_rows_in_step' ) );

			$request = new BBP_Converter();
			$setup_options->invoke( $request );
			$this->assertSame( 17, $request->step );
			$this->assertSame( 0, $request->start );
			$this->assertSame( 5, $request->rows_in_step );
			$bump_start->invoke( $request );
			$this->assertSame( 2, (int) get_option( '_bbp_converter_start' ) );

			$request = new BBP_Converter();
			$setup_options->invoke( $request );
			$this->assertSame( 2, $request->start );
			$this->assertFalse( $base_converter->convert_reply_to_parents( $request->start ) );
			$bump_start->invoke( $request );
			$this->assertSame( 4, (int) get_option( '_bbp_converter_start' ) );

			$request = new BBP_Converter();
			$setup_options->invoke( $request );
			$this->assertSame( 4, $request->start );
			$this->assertFalse( $base_converter->convert_reply_to_parents( $request->start ) );
			$bump_start->invoke( $request );
			$this->assertSame( 5, (int) get_option( '_bbp_converter_start' ) );

			$request = new BBP_Converter();
			$setup_options->invoke( $request );
			$this->assertSame( 5, $request->start );
			$this->assertTrue( $base_converter->convert_reply_to_parents( $request->start ) );
			$bump_step->invoke( $request );
			$this->assertSame( 0, (int) get_option( '_bbp_converter_step' ) );
			$this->assertSame( 0, (int) get_option( '_bbp_converter_start' ) );
			$this->assertTrue( (bool) get_option( '_bbp_converter_finished' ) );

			foreach ( $child_reply_ids as $child_reply_id ) {
				$this->assertSame( $parent_reply_id, (int) $wpdb->get_var( $wpdb->prepare( "SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = %s", $child_reply_id, '_bbp_reply_to' ) ) );
			}
		} finally {
			foreach ( $old_options as $option_name => $old_value ) {
				if ( null === $old_value ) {
					delete_option( $option_name );
				} else {
					update_option( $option_name, $old_value );
				}
			}
		}
	}

	/**
	 * @covers BBP_Converter_Base::convert_anonymous_topic_authors
	 * @covers BBP_Converter_Base::convert_anonymous_reply_authors
	 * @ticket BBP3729
	 */
	public function test_anonymous_author_post_processing_is_safe_to_replay() {
		$user_id  = $this->factory->user->create();
		$forum_id = $this->factory->forum->create();
		$topic_id = $this->factory->topic->create(
			array(
				'post_author' => $user_id,
				'post_parent' => $forum_id,
			)
		);
		$reply_id = $this->factory->reply->create(
			array(
				'post_author' => $user_id,
				'post_parent' => $topic_id,
			)
		);

		add_post_meta( $topic_id, '_bbp_old_is_topic_anonymous_id', 'true' );
		add_post_meta( $topic_id, '_bbp_old_topic_author_name_id', 'Anonymous Topic Author' );
		add_post_meta( $reply_id, '_bbp_old_is_reply_anonymous_id', 'true' );
		add_post_meta( $reply_id, '_bbp_old_reply_author_name_id', 'Anonymous Reply Author' );

		$converter = new BBP_Tests_Admin_Converters_Base_Converter();
		$converter->sync_table = false;

		$this->assertFalse( $converter->convert_anonymous_topic_authors( 0 ) );
		$this->assertFalse( $converter->convert_anonymous_topic_authors( 0 ) );
		$this->assertFalse( $converter->convert_anonymous_reply_authors( 0 ) );
		$this->assertFalse( $converter->convert_anonymous_reply_authors( 0 ) );
		$this->assertSame( array( 'Anonymous Topic Author' ), get_post_meta( $topic_id, '_bbp_anonymous_name', false ) );
		$this->assertSame( array( 'Anonymous Reply Author' ), get_post_meta( $reply_id, '_bbp_anonymous_name', false ) );
	}

	/**
	 * @covers BBP_Converter_Base::convert_forum_parents
	 * @covers BBP_Converter_Base::convert_topic_stickies
	 * @covers BBP_Converter_Base::convert_topic_super_stickies
	 * @covers BBP_Converter_Base::convert_topic_closed_topics
	 * @covers BBP_Converter_Base::convert_reply_to_parents
	 * @ticket 3706
	 */
	public function test_post_processing_converts_postmeta_relationships_and_statuses() {
		global $wpdb;

		$parent_forum_id = $this->factory->forum->create();
		$child_forum_id  = $this->factory->forum->create();
		$forum_id        = $this->factory->forum->create();
		$topic_id        = $this->factory->topic->create( array( 'post_parent' => $forum_id ) );
		$sticky_id       = $this->factory->topic->create( array( 'post_parent' => $forum_id ) );
		$super_sticky_id = $this->factory->topic->create( array( 'post_parent' => $forum_id ) );
		$closed_id       = $this->factory->topic->create( array( 'post_parent' => $forum_id ) );
		$parent_reply_id = $this->factory->reply->create( array( 'post_parent' => $topic_id ) );
		$child_reply_id  = $this->factory->reply->create( array( 'post_parent' => $topic_id ) );
		$old_forum_id    = 100000 + $parent_forum_id;
		$old_reply_id    = 100000 + $parent_reply_id;

		add_post_meta( $parent_forum_id, '_bbp_old_forum_id', $old_forum_id );
		add_post_meta( $child_forum_id, '_bbp_old_forum_parent_id', $old_forum_id );
		add_post_meta( $sticky_id, '_bbp_old_sticky_status_id', 'sticky' );
		add_post_meta( $super_sticky_id, '_bbp_old_sticky_status_id', 'super-sticky' );
		add_post_meta( $closed_id, '_bbp_old_closed_status_id', 'closed' );
		add_post_meta( $parent_reply_id, '_bbp_old_reply_id', $old_reply_id );
		add_post_meta( $child_reply_id, '_bbp_old_reply_to_id', $old_reply_id );
		add_post_meta( $child_reply_id, '_bbp_reply_to', $old_reply_id );

		$this->assertSame( 0, (int) get_post_field( 'post_parent', $child_forum_id ) );
		$this->assertSame( $old_reply_id, (int) get_post_meta( $child_reply_id, '_bbp_reply_to', true ) );
		$this->assertFalse( bbp_is_topic_sticky( $topic_id ) );
		$this->assertTrue( bbp_is_topic_open( $topic_id ) );

		$converter = new BBP_Tests_Admin_Converters_Base_Converter();
		$converter->sync_table = false;

		$this->assertFalse( $converter->convert_forum_parents( 0 ) );
		$this->assertFalse( $converter->convert_topic_stickies( 0 ) );
		$this->assertFalse( $converter->convert_topic_super_stickies( 0 ) );
		$this->assertFalse( $converter->convert_topic_closed_topics( 0 ) );
		$this->assertFalse( $converter->convert_reply_to_parents( 0 ) );

		$this->assertSame( $parent_forum_id, (int) $wpdb->get_var( $wpdb->prepare( "SELECT post_parent FROM {$wpdb->posts} WHERE ID = %d", $child_forum_id ) ) );
		$this->assertTrue( bbp_is_topic_sticky( $sticky_id, false ) );
		$this->assertFalse( bbp_is_topic_super_sticky( $sticky_id ) );
		$this->assertTrue( bbp_is_topic_super_sticky( $super_sticky_id ) );
		$this->assertContains( $super_sticky_id, (array) get_option( '_bbp_super_sticky_topics' ) );
		$this->assertTrue( bbp_is_topic_closed( $closed_id ) );
		$this->assertFalse( bbp_is_topic_open( $closed_id ) );
		$this->assertFalse( bbp_is_topic_sticky( $topic_id ) );
		$this->assertTrue( bbp_is_topic_open( $topic_id ) );
		$this->assertSame( $parent_reply_id, (int) $wpdb->get_var( $wpdb->prepare( "SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = %s", $child_reply_id, '_bbp_reply_to' ) ) );
	}

	/**
	 * @covers BBP_Converter_Base::convert_forum_parents
	 * @covers BBP_Converter_Base::convert_topic_stickies
	 * @covers BBP_Converter_Base::convert_topic_super_stickies
	 * @covers BBP_Converter_Base::convert_topic_closed_topics
	 * @covers BBP_Converter_Base::convert_reply_to_parents
	 * @ticket 3706
	 */
	public function test_post_processing_converts_sync_table_relationships_and_statuses() {
		global $wpdb;

		$parent_forum_id = $this->factory->forum->create();
		$child_forum_id  = $this->factory->forum->create();
		$forum_id        = $this->factory->forum->create();
		$topic_id        = $this->factory->topic->create( array( 'post_parent' => $forum_id ) );
		$sticky_id       = $this->factory->topic->create( array( 'post_parent' => $forum_id ) );
		$super_sticky_id = $this->factory->topic->create( array( 'post_parent' => $forum_id ) );
		$closed_id       = $this->factory->topic->create( array( 'post_parent' => $forum_id ) );
		$parent_reply_id = $this->factory->reply->create( array( 'post_parent' => $topic_id ) );
		$child_reply_id  = $this->factory->reply->create( array( 'post_parent' => $topic_id ) );
		$old_forum_id    = 200000 + $parent_forum_id;
		$old_reply_id    = 200000 + $parent_reply_id;
		$rows = array(
			array( $parent_forum_id, '_bbp_old_forum_id', $old_forum_id ),
			array( $child_forum_id, '_bbp_old_forum_parent_id', $old_forum_id ),
			array( $sticky_id, '_bbp_old_sticky_status_id', 'sticky' ),
			array( $super_sticky_id, '_bbp_old_sticky_status_id', 'super-sticky' ),
			array( $closed_id, '_bbp_old_closed_status_id', 'closed' ),
			array( $parent_reply_id, '_bbp_old_reply_id', $old_reply_id ),
			array( $child_reply_id, '_bbp_old_reply_to_id', $old_reply_id ),
		);
		foreach ( $rows as $row ) {
			$wpdb->insert(
				self::$anonymous_sync_table,
				array(
					'value_type' => 'post',
					'value_id'   => $row[0],
					'meta_key'   => $row[1],
					'meta_value' => $row[2],
				)
			);
		}
		add_post_meta( $child_reply_id, '_bbp_reply_to', $old_reply_id );

		$this->assertSame( 0, (int) get_post_field( 'post_parent', $child_forum_id ) );
		$this->assertSame( $old_reply_id, (int) get_post_meta( $child_reply_id, '_bbp_reply_to', true ) );
		$this->assertFalse( bbp_is_topic_sticky( $topic_id ) );
		$this->assertTrue( bbp_is_topic_open( $topic_id ) );

		$converter                  = new BBP_Tests_Admin_Converters_Base_Converter();
		$converter->sync_table      = true;
		$converter->sync_table_name = self::$anonymous_sync_table;

		$this->assertFalse( $converter->convert_forum_parents( 0 ) );
		$this->assertFalse( $converter->convert_topic_stickies( 0 ) );
		$this->assertFalse( $converter->convert_topic_super_stickies( 0 ) );
		$this->assertFalse( $converter->convert_topic_closed_topics( 0 ) );
		$this->assertFalse( $converter->convert_reply_to_parents( 0 ) );

		$this->assertSame( $parent_forum_id, (int) $wpdb->get_var( $wpdb->prepare( "SELECT post_parent FROM {$wpdb->posts} WHERE ID = %d", $child_forum_id ) ) );
		$this->assertTrue( bbp_is_topic_sticky( $sticky_id, false ) );
		$this->assertFalse( bbp_is_topic_super_sticky( $sticky_id ) );
		$this->assertTrue( bbp_is_topic_super_sticky( $super_sticky_id ) );
		$this->assertContains( $super_sticky_id, (array) get_option( '_bbp_super_sticky_topics' ) );
		$this->assertTrue( bbp_is_topic_closed( $closed_id ) );
		$this->assertFalse( bbp_is_topic_open( $closed_id ) );
		$this->assertFalse( bbp_is_topic_sticky( $topic_id ) );
		$this->assertTrue( bbp_is_topic_open( $topic_id ) );
		$this->assertSame( $parent_reply_id, (int) $wpdb->get_var( $wpdb->prepare( "SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = %s", $child_reply_id, '_bbp_reply_to' ) ) );
	}

	/**
	 * @covers BBP_Converter_Base::convert_anonymous_topic_authors
	 * @ticket BBP3728
	 */
	public function test_anonymous_topic_conversion_requires_the_anonymous_marker() {
		$user_id             = $this->factory->user->create();
		$registered_topic_id = $this->factory->topic->create( array( 'post_author' => $user_id ) );
		$anonymous_topic_id  = $this->factory->topic->create( array( 'post_author' => $user_id ) );

		add_post_meta( $registered_topic_id, '_bbp_old_is_topic_anonymous_id', 'false' );
		add_post_meta( $registered_topic_id, '_bbp_old_topic_author_name_id', 'Registered Author' );
		add_post_meta( $registered_topic_id, '_bbp_unrelated_import_flag', 'true' );
		add_post_meta( $anonymous_topic_id, '_bbp_old_is_topic_anonymous_id', 'true' );
		add_post_meta( $anonymous_topic_id, '_bbp_old_topic_author_name_id', 'Anonymous Author' );

		$converter = new BBP_Tests_Admin_Converters_Base_Converter();
		$converter->sync_table = false;
		$this->assertFalse( $converter->convert_anonymous_topic_authors( 0 ) );
		clean_post_cache( $registered_topic_id );
		clean_post_cache( $anonymous_topic_id );
		$this->assertSame( $user_id, (int) get_post_field( 'post_author', $registered_topic_id ) );
		$this->assertSame( '', get_post_meta( $registered_topic_id, '_bbp_anonymous_name', true ) );
		$this->assertSame( 0, (int) get_post_field( 'post_author', $anonymous_topic_id ) );
		$this->assertSame( 'Anonymous Author', get_post_meta( $anonymous_topic_id, '_bbp_anonymous_name', true ) );
	}

	/**
	 * @covers BBP_Converter_Base::convert_anonymous_reply_authors
	 * @ticket BBP3728
	 */
	public function test_anonymous_reply_conversion_requires_the_anonymous_marker() {
		$user_id             = $this->factory->user->create();
		$forum_id            = $this->factory->forum->create();
		$topic_id            = $this->factory->topic->create( array( 'post_parent' => $forum_id ) );
		$registered_reply_id = $this->factory->reply->create( array( 'post_author' => $user_id, 'post_parent' => $topic_id ) );
		$anonymous_reply_id  = $this->factory->reply->create( array( 'post_author' => $user_id, 'post_parent' => $topic_id ) );

		add_post_meta( $registered_reply_id, '_bbp_old_is_reply_anonymous_id', 'false' );
		add_post_meta( $registered_reply_id, '_bbp_old_reply_author_name_id', 'Registered Author' );
		add_post_meta( $registered_reply_id, '_bbp_unrelated_import_flag', 'true' );
		add_post_meta( $anonymous_reply_id, '_bbp_old_is_reply_anonymous_id', 'true' );
		add_post_meta( $anonymous_reply_id, '_bbp_old_reply_author_name_id', 'Anonymous Author' );

		$converter = new BBP_Tests_Admin_Converters_Base_Converter();
		$converter->sync_table = false;
		$this->assertFalse( $converter->convert_anonymous_reply_authors( 0 ) );
		clean_post_cache( $registered_reply_id );
		clean_post_cache( $anonymous_reply_id );
		$this->assertSame( $user_id, (int) get_post_field( 'post_author', $registered_reply_id ) );
		$this->assertSame( '', get_post_meta( $registered_reply_id, '_bbp_anonymous_name', true ) );
		$this->assertSame( 0, (int) get_post_field( 'post_author', $anonymous_reply_id ) );
		$this->assertSame( 'Anonymous Author', get_post_meta( $anonymous_reply_id, '_bbp_anonymous_name', true ) );
	}

	/**
	 * @covers BBP_Converter_Base::convert_anonymous_topic_authors
	 * @covers BBP_Converter_Base::convert_anonymous_reply_authors
	 * @ticket BBP3728
	 */
	public function test_anonymous_author_conversion_filters_sync_table_markers() {
		global $wpdb;

		$user_id             = $this->factory->user->create();
		$registered_topic_id = $this->factory->topic->create( array( 'post_author' => $user_id ) );
		$anonymous_topic_id  = $this->factory->topic->create( array( 'post_author' => $user_id ) );
		$forum_id            = $this->factory->forum->create();
		$topic_id            = $this->factory->topic->create( array( 'post_parent' => $forum_id ) );
		$registered_reply_id = $this->factory->reply->create( array( 'post_author' => $user_id, 'post_parent' => $topic_id ) );
		$anonymous_reply_id  = $this->factory->reply->create( array( 'post_author' => $user_id, 'post_parent' => $topic_id ) );
		$rows = array(
			array( $registered_topic_id, '_bbp_old_is_topic_anonymous_id', 'false' ),
			array( $registered_topic_id, '_bbp_old_topic_author_name_id', 'Registered Topic Author' ),
			array( $registered_topic_id, '_bbp_unrelated_import_flag', 'true' ),
			array( $anonymous_topic_id, '_bbp_old_is_topic_anonymous_id', 'true' ),
			array( $anonymous_topic_id, '_bbp_old_topic_author_name_id', 'Anonymous Topic Author' ),
			array( $registered_reply_id, '_bbp_old_is_reply_anonymous_id', 'false' ),
			array( $registered_reply_id, '_bbp_old_reply_author_name_id', 'Registered Reply Author' ),
			array( $registered_reply_id, '_bbp_unrelated_import_flag', 'true' ),
			array( $anonymous_reply_id, '_bbp_old_is_reply_anonymous_id', 'true' ),
			array( $anonymous_reply_id, '_bbp_old_reply_author_name_id', 'Anonymous Reply Author' ),
		);
		foreach ( $rows as $row ) {
			$wpdb->insert(
				self::$anonymous_sync_table,
				array(
					'value_id'   => $row[0],
					'meta_key'   => $row[1],
					'meta_value' => $row[2],
				)
			);
		}

		$converter                  = new BBP_Tests_Admin_Converters_Base_Converter();
		$converter->sync_table      = true;
		$converter->sync_table_name = self::$anonymous_sync_table;

		$this->assertFalse( $converter->convert_anonymous_topic_authors( 0 ) );
		$this->assertFalse( $converter->convert_anonymous_reply_authors( 0 ) );
		foreach ( array( $registered_topic_id, $anonymous_topic_id, $registered_reply_id, $anonymous_reply_id ) as $post_id ) {
			clean_post_cache( $post_id );
		}

		$this->assertSame( $user_id, (int) get_post_field( 'post_author', $registered_topic_id ) );
		$this->assertSame( '', get_post_meta( $registered_topic_id, '_bbp_anonymous_name', true ) );
		$this->assertSame( 0, (int) get_post_field( 'post_author', $anonymous_topic_id ) );
		$this->assertSame( 'Anonymous Topic Author', get_post_meta( $anonymous_topic_id, '_bbp_anonymous_name', true ) );
		$this->assertSame( $user_id, (int) get_post_field( 'post_author', $registered_reply_id ) );
		$this->assertSame( '', get_post_meta( $registered_reply_id, '_bbp_anonymous_name', true ) );
		$this->assertSame( 0, (int) get_post_field( 'post_author', $anonymous_reply_id ) );
		$this->assertSame( 'Anonymous Reply Author', get_post_meta( $anonymous_reply_id, '_bbp_anonymous_name', true ) );
	}
}
