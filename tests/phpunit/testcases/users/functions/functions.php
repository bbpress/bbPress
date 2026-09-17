<?php

/**
 * Tests for the user component functions.
 *
 * @group users
 * @group functions
 */
 class BBP_Tests_Users_Functions extends BBP_UnitTestCase {

	public function tearDown(): void {
		unset( $_POST['log'], $_POST['pwd'] );
		delete_option( '_bbp_converter_platform' );

		parent::tearDown();
	}

	/**
	 * @covers ::bbp_redirect_login
	 * @todo   Implement test_bbp_redirect_login().
	 */
	public function test_bbp_redirect_login() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_is_anonymous
	 * @todo   Implement test_bbp_is_anonymous().
	 */
	public function test_bbp_is_anonymous() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_current_anonymous_user_data
	 * @todo   Implement test_bbp_current_anonymous_user_data().
	 */
	public function test_bbp_current_anonymous_user_data() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_get_current_anonymous_user_data
	 * @todo   Implement test_bbp_get_current_anonymous_user_data().
	 */
	public function test_bbp_get_current_anonymous_user_data() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_set_current_anonymous_user_data
	 * @todo   Implement test_bbp_set_current_anonymous_user_data().
	 */
	public function test_bbp_set_current_anonymous_user_data() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_current_author_ip
	 * @todo   Implement test_bbp_current_author_ip().
	 */
	public function test_bbp_current_author_ip() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_current_author_ua
	 * @todo   Implement test_bbp_current_author_ua().
	 */
	public function test_bbp_current_author_ua() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_add_user_to_object
	 */
	public function test_bbp_add_user_to_object() {
		$u = $this->factory->user->create_many( 3 );
		$t = $this->factory->topic->create();

		// Add object terms.
		foreach ( $u as $k => $v ) {
			bbp_add_user_to_object( $t, $v, '_bbp_moderator' );
		}

		$r = get_metadata( 'post', $t, '_bbp_moderator', false );

		$this->assertCount( 3, $r );
	}

	/**
	 * @covers ::bbp_remove_user_from_object
	 */
	public function test_bbp_remove_user_from_object() {
		$u = $this->factory->user->create();
		$t = $this->factory->topic->create();

		// Add object terms.
		add_metadata( 'post', $t, '_bbp_moderator', $u, false );

		$r = get_metadata( 'post', $t, '_bbp_moderator', false );

		$this->assertCount( 1, $r );

		$r = bbp_remove_user_from_object( $t, $u, '_bbp_moderator' );

		$this->assertTrue( $r );

		$r = get_metadata( 'post', $t, '_bbp_moderator', false );

		$this->assertCount( 0, $r );
	}

	/**
	 * @covers ::bbp_is_object_of_user
	 */
	public function test_bbp_is_object_of_user() {
		$u = $this->factory->user->create();
		$t = $this->factory->topic->create();

		$r = bbp_is_object_of_user( $t, $u, '_bbp_moderator' );

		$this->assertFalse( $r );

		// Add user id.
		add_metadata( 'post', $t, '_bbp_moderator', $u, false );

		$r = bbp_is_object_of_user( $t, $u, '_bbp_moderator' );

		$this->assertTrue( $r );
	}

 	/**
	 * @covers ::bbp_edit_user_handler
	 * @todo   Implement test_bbp_edit_user_handler().
	 */
	public function test_bbp_edit_user_handler() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_user_email_change_handler
	 * @todo   Implement test_bbp_user_email_change_handler().
	 */
	public function test_bbp_user_email_change_handler() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_edit_user_email_send_notification
	 * @todo   Implement test_bbp_edit_user_email_send_notification().
	 */
	public function test_bbp_edit_user_email_send_notification() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_user_edit_after
	 * @todo   Implement test_bbp_user_edit_after().
	 */
	public function test_bbp_user_edit_after() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_check_user_edit
	 * @todo   Implement test_bbp_check_user_edit().
	 */
	public function test_bbp_check_user_edit() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_forum_enforce_blocked
	 * @todo   Implement test_bbp_forum_enforce_blocked().
	 */
	public function test_bbp_forum_enforce_blocked() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_sanitize_displayed_user_field
	 * @todo   Implement test_bbp_sanitize_displayed_user_field().
	 */
	public function test_bbp_sanitize_displayed_user_field() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_user_maybe_convert_pass
	 * @ticket BBP3684
	 */
	public function test_bbp_user_maybe_convert_pass() {
		global $wpdb;

		$password = 'Correct Horse Battery Staple';
		$user_id  = $this->factory->user->create(
			array(
				'user_login' => 'phpbb-imported-user',
				'user_pass'  => $password,
			)
		);

		$wpdb->update(
			$wpdb->users,
			array( 'user_pass' => '' ),
			array( 'ID' => $user_id )
		);
		clean_user_cache( $user_id );

		add_user_meta(
			$user_id,
			'_bbp_password',
			array(
				'hash' => md5( $password ),
				'salt' => '',
			)
		);
		add_user_meta( $user_id, '_bbp_class', 'phpBB' );

		$converter = null;
		$capture   = function( $new_converter ) use ( &$converter ) {
			$converter = $new_converter;

			return $new_converter;
		};

		add_filter( 'bbp_new_converter', $capture );

		$_POST['log'] = 'phpbb-imported-user';
		$_POST['pwd'] = $password;

		try {
			bbp_user_maybe_convert_pass();
		} finally {
			remove_filter( 'bbp_new_converter', $capture );
		}

		$user = get_userdata( $user_id );

		$this->assertTrue( wp_check_password( $password, $user->user_pass, $user_id ) );
		$this->assertFalse( metadata_exists( 'user', $user_id, '_bbp_password' ) );
		$this->assertFalse( metadata_exists( 'user', $user_id, '_bbp_class' ) );

		$this->assert_source_database_not_connected( $converter );
	}

	/**
	 * @covers ::bbp_user_maybe_convert_pass
	 * @ticket BBP3684
	 */
	public function test_bbp_user_maybe_convert_pass_with_incorrect_password() {
		global $wpdb;

		$password = 'Correct Horse Battery Staple';
		$user_id  = $this->factory->user->create(
			array(
				'user_login' => 'phpbb-imported-user',
				'user_pass'  => $password,
			)
		);

		$wpdb->update(
			$wpdb->users,
			array( 'user_pass' => '' ),
			array( 'ID' => $user_id )
		);
		clean_user_cache( $user_id );

		add_user_meta(
			$user_id,
			'_bbp_password',
			array(
				'hash' => md5( $password ),
				'salt' => '',
			)
		);
		add_user_meta( $user_id, '_bbp_class', 'phpBB' );

		$converter = null;
		$capture   = function( $new_converter ) use ( &$converter ) {
			$converter = $new_converter;

			return $new_converter;
		};

		add_filter( 'bbp_new_converter', $capture );

		$_POST['log'] = 'phpbb-imported-user';
		$_POST['pwd'] = 'incorrect';

		try {
			bbp_user_maybe_convert_pass();
		} finally {
			remove_filter( 'bbp_new_converter', $capture );
		}

		$user = get_userdata( $user_id );

		$this->assertSame( '', $user->user_pass );
		$this->assertTrue( metadata_exists( 'user', $user_id, '_bbp_password' ) );
		$this->assertTrue( metadata_exists( 'user', $user_id, '_bbp_class' ) );
		$this->assert_source_database_not_connected( $converter );
	}

	/**
	 * @covers ::bbp_user_maybe_convert_pass
	 * @ticket BBP3684
	 */
	public function test_bbp_user_maybe_convert_pass_uses_saved_platform_when_class_meta_is_missing() {
		$password = 'Correct Horse Battery Staple';
		$user_id  = $this->create_imported_phpbb_user( $password );
		$user     = get_userdata( $user_id );

		delete_user_meta( $user_id, '_bbp_class' );
		update_option( '_bbp_converter_platform', 'phpBB' );

		$_POST['log'] = $user->user_login;
		$_POST['pwd'] = $password;

		bbp_user_maybe_convert_pass();

		$user = get_userdata( $user_id );

		$this->assertTrue( wp_check_password( $password, $user->user_pass, $user_id ) );
		$this->assertFalse( metadata_exists( 'user', $user_id, '_bbp_password' ) );
	}

	/**
	 * @covers ::bbp_user_maybe_convert_pass
	 * @ticket BBP3684
	 */
	public function test_bbp_user_maybe_convert_pass_saved_platform_fails_closed() {
		$password = 'Correct Horse Battery Staple';
		$user_id  = $this->create_imported_phpbb_user( $password );
		$user     = get_userdata( $user_id );

		delete_user_meta( $user_id, '_bbp_class' );
		update_option( '_bbp_converter_platform', 'NotAConverter' );

		$_POST['log'] = $user->user_login;
		$_POST['pwd'] = $password;

		bbp_user_maybe_convert_pass();

		$this->assertSame( '', get_userdata( $user_id )->user_pass );
		$this->assertTrue( metadata_exists( 'user', $user_id, '_bbp_password' ) );
		$this->assertFalse( metadata_exists( 'user', $user_id, '_bbp_class' ) );
	}

	/**
	 * @covers ::bbp_user_maybe_convert_pass
	 * @ticket BBP3684
	 */
	public function test_bbp_user_maybe_convert_pass_missing_class_fails_closed() {
		$password = 'Correct Horse Battery Staple';
		$user_id  = $this->create_imported_phpbb_user( $password );
		$user     = get_userdata( $user_id );

		delete_user_meta( $user_id, '_bbp_class' );
		update_option( '_bbp_converter_platform', 'phpBB' );

		$_POST['log'] = $user->user_login;
		$_POST['pwd'] = 'incorrect';

		bbp_user_maybe_convert_pass();

		$this->assertSame( '', get_userdata( $user_id )->user_pass );
		$this->assertTrue( metadata_exists( 'user', $user_id, '_bbp_password' ) );
		$this->assertFalse( metadata_exists( 'user', $user_id, '_bbp_class' ) );
	}

	/**
	 * @covers ::bbp_user_maybe_convert_pass
	 * @ticket BBP3684
	 */
	public function test_bbp_user_maybe_convert_pass_class_meta_takes_precedence_over_saved_platform() {
		$password = 'Correct Horse Battery Staple';
		$user_id  = $this->create_imported_phpbb_user( $password );
		$user     = get_userdata( $user_id );

		update_option( '_bbp_converter_platform', 'NotAConverter' );

		$_POST['log'] = $user->user_login;
		$_POST['pwd'] = $password;

		bbp_user_maybe_convert_pass();

		$this->assertTrue( wp_check_password( $password, get_userdata( $user_id )->user_pass, $user_id ) );
	}

	/**
	 * @covers ::bbp_user_maybe_convert_pass
	 * @ticket BBP3684
	 */
	public function test_bbp_user_maybe_convert_pass_does_not_use_saved_platform_without_password_meta() {
		$password = 'Current WordPress Password';
		$user_id  = $this->factory->user->create(
			array(
				'user_login' => 'not-imported-' . wp_generate_password( 8, false ),
				'user_pass'  => $password,
			)
		);
		$user     = get_userdata( $user_id );

		update_option( '_bbp_converter_platform', 'phpBB' );

		$_POST['log'] = $user->user_login;
		$_POST['pwd'] = $password;

		bbp_user_maybe_convert_pass();

		$this->assertTrue( wp_check_password( $password, get_userdata( $user_id )->user_pass, $user_id ) );
	}

	/**
	 * @covers ::bbp_user_maybe_convert_pass
	 * @ticket BBP3684
	 */
	public function test_bbp_user_maybe_convert_pass_routes_both_metadata_paths_for_all_converters() {
		$password = 'Correct Horse Battery Staple';
		$user_id  = $this->create_imported_phpbb_user( $password );
		$user     = get_userdata( $user_id );

		$_POST['log'] = $user->user_login;
		$_POST['pwd'] = $password;

		$platforms = array_keys( bbp_get_converters() );

		foreach ( $platforms as $platform ) {
			foreach ( array( true, false ) as $has_class_meta ) {
				if ( $has_class_meta ) {
					update_user_meta( $user_id, '_bbp_class', $platform );
					update_option( '_bbp_converter_platform', 'NotAConverter' );
				} else {
					delete_user_meta( $user_id, '_bbp_class' );
					update_option( '_bbp_converter_platform', $platform );
				}

				$requested_platform  = null;
				$requested_converter = null;
				$capture             = function( $converter, $requested ) use ( &$requested_platform, &$requested_converter ) {
					$requested_platform  = $requested;
					$requested_converter = $converter;

					return null;
				};

				add_filter( 'bbp_new_converter', $capture, 10, 2 );

				try {
					bbp_user_maybe_convert_pass();
				} finally {
					remove_filter( 'bbp_new_converter', $capture, 10 );
				}

				$this->assertSame(
					$platform,
					$requested_platform,
					sprintf( '%s did not use the %s metadata path.', $platform, $has_class_meta ? 'per-user' : 'saved-platform' )
				);
				$this->assert_source_database_not_connected( $requested_converter );
			}
		}
	}

	/**
	 * @covers ::bbp_user_maybe_convert_pass
	 * @ticket BBP3684
	 */
	public function test_bbp_user_maybe_convert_pass_by_email() {
		$password = 'Correct Horse Battery Staple';
		$user_id  = $this->create_imported_phpbb_user( $password );
		$user     = get_userdata( $user_id );

		$_POST['log'] = $user->user_email;
		$_POST['pwd'] = $password;

		bbp_user_maybe_convert_pass();

		$user = get_userdata( $user_id );

		$this->assertNotSame( '', $user->user_pass );
		$this->assertTrue( wp_check_password( $password, $user->user_pass, $user_id ) );
	}

	/**
	 * @covers ::bbp_user_maybe_convert_pass
	 * @ticket BBP3684
	 */
	public function test_bbp_user_maybe_convert_pass_accepts_zero() {
		$password = '0';
		$user_id  = $this->create_imported_phpbb_user( $password );
		$user     = get_userdata( $user_id );

		$_POST['log'] = $user->user_login;
		$_POST['pwd'] = $password;

		bbp_user_maybe_convert_pass();

		$this->assertTrue( wp_check_password( $password, get_userdata( $user_id )->user_pass, $user_id ) );
	}

	/**
	 * @covers ::bbp_user_maybe_convert_pass
	 * @ticket BBP3684
	 */
	public function test_bbp_user_maybe_convert_pass_unslashes_password() {
		$password = "Correct 'Horse' \\ Battery";
		$user_id  = $this->create_imported_phpbb_user( $password );
		$user     = get_userdata( $user_id );

		$_POST['log'] = $user->user_login;
		$_POST['pwd'] = wp_slash( $password );

		bbp_user_maybe_convert_pass();

		$user = wp_signon( array(), false );

		$this->assertInstanceOf( 'WP_User', $user );
		$this->assertSame( $user_id, $user->ID );
	}

	/**
	 * @covers ::bbp_user_maybe_convert_pass
	 * @ticket BBP3684
	 */
	public function test_bbp_user_maybe_convert_pass_does_not_replace_existing_password() {
		$password = 'Current WordPress Password';
		$user_id  = $this->create_imported_phpbb_user( 'Legacy phpBB Password' );
		$user     = get_userdata( $user_id );

		wp_set_password( $password, $user_id );

		$_POST['log'] = $user->user_login;
		$_POST['pwd'] = $password;

		bbp_user_maybe_convert_pass();

		$user = get_userdata( $user_id );

		$this->assertTrue( wp_check_password( $password, $user->user_pass, $user_id ) );
		$this->assertTrue( metadata_exists( 'user', $user_id, '_bbp_password' ) );
		$this->assertTrue( metadata_exists( 'user', $user_id, '_bbp_class' ) );
	}

	/**
	 * @covers ::bbp_user_maybe_convert_pass
	 * @ticket BBP3684
	 */
	public function test_bbp_user_maybe_convert_pass_rejects_non_scalar_input() {
		$password = 'Correct Horse Battery Staple';
		$user_id  = $this->create_imported_phpbb_user( $password );
		$user     = get_userdata( $user_id );

		$_POST['log'] = array( $user->user_login );
		$_POST['pwd'] = array( $password );

		bbp_user_maybe_convert_pass();

		$this->assertSame( '', get_userdata( $user_id )->user_pass );
		$this->assertTrue( metadata_exists( 'user', $user_id, '_bbp_password' ) );
		$this->assertTrue( metadata_exists( 'user', $user_id, '_bbp_class' ) );
	}

	/**
	 * Create a user with imported phpBB password metadata.
	 *
	 * @param string $password Password to store in phpBB's imported format.
	 * @return int User ID.
	 */
	private function create_imported_phpbb_user( $password ) {
		global $wpdb;

		$user_id = $this->factory->user->create(
			array(
				'user_login' => 'phpbb-imported-' . wp_generate_password( 8, false ),
				'user_email' => wp_generate_password( 8, false ) . '@example.org',
				'user_pass'  => $password,
			)
		);

		$wpdb->update(
			$wpdb->users,
			array( 'user_pass' => '' ),
			array( 'ID' => $user_id )
		);
		clean_user_cache( $user_id );

		add_user_meta(
			$user_id,
			'_bbp_password',
			array(
				'hash' => md5( $password ),
				'salt' => '',
			)
		);
		add_user_meta( $user_id, '_bbp_class', 'phpBB' );

		return $user_id;
	}

	/**
	 * Assert that a converter has not connected to its source database.
	 *
	 * The database handle is protected and has no public connection-state
	 * accessor, so inspect it directly for this regression test.
	 *
	 * @param BBP_Converter_Base $converter Converter object.
	 */
	private function assert_source_database_not_connected( $converter ) {
		$get_source_db = Closure::bind(
			function( $object ) {
				return $object->opdb;
			},
			null,
			'BBP_Converter_Base'
		);
		$get_db_handle = Closure::bind(
			function( $object ) {
				return $object->dbh;
			},
			null,
			'wpdb'
		);

		$this->assertEmpty( $get_db_handle( $get_source_db( $converter ) ) );
	}
}
