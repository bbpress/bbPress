<?php

bbp_setup_converter();

class BBP_Tests_Admin_Converters_Unserialize_Wakeup {
	public static $woke = false;

	public function __wakeup() {
		self::$woke = true;
	}
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
}
