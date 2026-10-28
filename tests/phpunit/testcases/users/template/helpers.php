<?php

/**
 * Tests for remaining user template helpers.
 *
 * @group users
 * @group template
 */
class BBP_Tests_Users_Template_Helpers extends BBP_UnitTestCase {

	/**
	 * @covers ::bbp_has_users
	 * @covers ::bbp_users
	 * @covers ::bbp_the_user
	 */
	public function test_user_loop_queries_and_iterates_matching_users() {
		$user_ids = array(
			$this->factory->user->create( array( 'user_login' => 'bbp-z-user' ) ),
			$this->factory->user->create( array( 'user_login' => 'bbp-a-user' ) ),
		);
		$old_query = bbpress()->user_query;
		$seen      = array();

		try {
			$this->assertTrue( bbp_has_users( array( 'include' => $user_ids ) ) );

			while ( bbp_users() ) {
				bbp_the_user();
				$seen[] = bbp_get_user_id();
			}

			$this->assertSame( array( $user_ids[1], $user_ids[0] ), $seen );
			$this->assertFalse( bbpress()->user_query->in_the_loop );
			$this->assertFalse( bbp_has_users( array( 'include' => array( PHP_INT_MAX ) ) ) );
		} finally {
			bbpress()->user_query = $old_query;
		}
	}

	/**
	 * @covers ::bbp_get_fallback_display_name
	 */
	public function test_fallback_display_name_supports_object_context_filtering() {
		$object_id = $this->factory->post->create();
		$seen      = 0;
		$filter    = function( $name, $filtered_object_id ) use ( &$seen ) {
			$seen = $filtered_object_id;
			return 'Unknown author';
		};

		$this->assertSame( 'Anonymous', bbp_get_fallback_display_name( $object_id ) );
		add_filter( 'bbp_get_anonymous_name', $filter, 10, 2 );

		try {
			$this->assertSame( 'Unknown author', bbp_get_fallback_display_name( $object_id ) );
			$this->assertSame( $object_id, $seen );
		} finally {
			remove_filter( 'bbp_get_anonymous_name', $filter, 10 );
		}
	}

	/**
	 * @covers ::bbp_user_auto_role_exclusion_form
	 */
	public function test_auto_role_exclusion_form_reflects_site_scope() {
		$user_id = $this->factory->user->create();

		ob_start();
		bbp_user_auto_role_exclusion_form();
		$this->assertSame( '', ob_get_clean() );

		ob_start();
		bbp_user_auto_role_exclusion_form( $user_id );
		$output = ob_get_clean();

		$this->assertStringContainsString( 'name="bbp-auto-role-exclusion-present"', $output );
		$this->assertStringContainsString( 'name="bbp-auto-role-exclusion"', $output );
		$this->assertStringNotContainsString( "checked='checked'", $output );
		$this->assertStringContainsString( 'Existing and assigned forum roles are unaffected.', $output );

		update_user_option( $user_id, '_bbp_auto_role_exclusion', '1', false );

		ob_start();
		bbp_user_auto_role_exclusion_form( $user_id );
		$output = ob_get_clean();

		$this->assertStringContainsString( "checked='checked'", $output );
	}

	/**
	 * @covers ::bbp_filter_user_edit_password_fields
	 */
	public function test_password_fields_follow_profile_permissions_only_on_front_end_edits() {
		$user_id = $this->factory->user->create();
		$old_user = get_current_user_id();

		$this->set_current_user( $user_id );
		add_filter( 'bbp_is_single_user_edit', '__return_true' );

		try {
			$this->assertTrue( bbp_filter_user_edit_password_fields( true, get_userdata( $user_id ) ) );
			$this->assertFalse( bbp_filter_user_edit_password_fields( false, get_userdata( $user_id ) ) );

			add_filter( 'bbp_current_user_can_edit_user_field', '__return_false' );
			$this->assertFalse( bbp_filter_user_edit_password_fields( true, get_userdata( $user_id ) ) );

			set_current_screen( 'user-edit.php' );
			$this->assertTrue( bbp_filter_user_edit_password_fields( true, get_userdata( $user_id ) ) );
		} finally {
			remove_filter( 'bbp_is_single_user_edit', '__return_true' );
			remove_filter( 'bbp_current_user_can_edit_user_field', '__return_false' );
			set_current_screen( 'front' );
			$this->set_current_user( $old_user );
		}
	}

	/**
	 * @covers ::bbp_edit_user_language
	 * @covers ::bbp_user_languages_dropdown
	 * @covers ::bbp_get_user_languages_dropdown
	 */
	public function test_user_language_dropdown_uses_user_locale_and_edit_context() {
		$user_id = $this->factory->user->create();
		$parsed  = array();
		$filter  = function( $retval, $args ) use ( &$parsed ) {
			$parsed = $args;
			return $retval;
		};

		update_user_meta( $user_id, 'locale', 'en_US' );
		add_filter( 'bbp_get_user_languages_dropdown', $filter, 10, 2 );

		try {
			$output = bbp_get_user_languages_dropdown(
				array(
					'user_id' => $user_id,
					'name'    => 'bbp_locale',
					'id'      => 'bbp-locale',
				)
			);

			$this->assertStringContainsString( 'name="bbp_locale"', $output );
			$this->assertStringContainsString( 'id="bbp-locale"', $output );
			$this->assertSame( '', $parsed['selected'] );
			$this->assertArrayNotHasKey( 'user_id', $parsed );

			update_user_meta( $user_id, 'locale', 'xx_XX' );
			bbp_get_user_languages_dropdown( array( 'user_id' => $user_id ) );
			$this->assertSame( 'site-default', $parsed['selected'] );

			ob_start();
			bbp_user_languages_dropdown( array( 'user_id' => $user_id ) );
			$this->assertSame( bbp_get_user_languages_dropdown( array( 'user_id' => $user_id ) ), ob_get_clean() );

			ob_start();
			bbp_edit_user_language( array( 'user_id' => $user_id ) );
			$this->assertSame( '', ob_get_clean() );

			add_filter( 'bbp_is_single_user_edit', '__return_true' );
			ob_start();
			bbp_edit_user_language( array( 'user_id' => $user_id ) );
			$this->assertSame( bbp_get_user_languages_dropdown( array( 'user_id' => $user_id ) ), ob_get_clean() );
		} finally {
			remove_filter( 'bbp_is_single_user_edit', '__return_true' );
			remove_filter( 'bbp_get_user_languages_dropdown', $filter, 10 );
		}
	}

	/**
	 * @covers ::bbp_is_user_profile_form_post_request
	 */
	public function test_user_profile_request_requires_post_and_valid_nonce() {
		$user_id     = $this->factory->user->create();
		$old_method  = isset( $_SERVER['REQUEST_METHOD'] ) ? $_SERVER['REQUEST_METHOD'] : null;
		$old_request = $_REQUEST;

		try {
			$_SERVER['REQUEST_METHOD'] = 'GET';
			$this->assertFalse( bbp_is_user_profile_form_post_request( $user_id ) );
			$this->assertFalse( bbp_is_user_profile_form_post_request() );

			$_SERVER['REQUEST_METHOD'] = 'POST';
			$_REQUEST['_wpnonce']      = 'invalid';
			$this->assertFalse( bbp_is_user_profile_form_post_request( $user_id ) );

			$_REQUEST['_wpnonce'] = wp_create_nonce( 'update-user_' . $user_id );
			$this->assertTrue( bbp_is_user_profile_form_post_request( $user_id ) );
		} finally {
			$_REQUEST = $old_request;
			if ( null === $old_method ) {
				unset( $_SERVER['REQUEST_METHOD'] );
			} else {
				$_SERVER['REQUEST_METHOD'] = $old_method;
			}
		}
	}

	/**
	 * @covers ::bbp_user_engagements_url
	 * @covers ::bbp_get_user_engagements_url
	 */
	public function test_user_engagements_url_supports_permalink_modes_and_interception() {
		$user_id = $this->factory->user->create( array( 'user_nicename' => 'engaged-user' ) );
		$filter  = function() {
			return 'https://example.org/engagements/';
		};

		$this->set_permalink_structure( '/%postname%/' );
		$expected = 'http://' . WP_TESTS_DOMAIN . '/forums/users/engaged-user/engagements/';
		$this->assertSame( $expected, bbp_get_user_engagements_url( $user_id ) );

		ob_start();
		bbp_user_engagements_url( $user_id );
		$this->assertSame( $expected, ob_get_clean() );

		$this->set_permalink_structure();
		$this->assertSame( 'http://' . WP_TESTS_DOMAIN . '/?bbp_user=' . $user_id . '&bbp_engagements=1', bbp_get_user_engagements_url( $user_id ) );

		add_filter( 'bbp_pre_get_user_engagements_url', $filter );
		try {
			$this->assertSame( 'https://example.org/engagements/', bbp_get_user_engagements_url( $user_id ) );
		} finally {
			remove_filter( 'bbp_pre_get_user_engagements_url', $filter );
		}
	}

	/**
	 * @covers ::bbp_moderator_list
	 * @covers ::bbp_get_moderator_list
	 */
	public function test_moderator_list_formats_front_end_admin_and_empty_results() {
		$forum_id = $this->factory->forum->create();
		$user_ids = array(
			$this->factory->user->create( array( 'display_name' => 'First Moderator', 'user_login' => 'bbp-mod-a', 'user_nicename' => 'first-moderator' ) ),
			$this->factory->user->create( array( 'display_name' => 'Second Moderator', 'user_login' => 'bbp-mod-b', 'user_nicename' => 'second-moderator' ) ),
		);
		$args = array(
			'before' => '<strong>',
			'sep'    => ' | ',
			'after'  => '</strong>',
			'none'   => 'No moderators',
		);

		bbp_add_moderator( $forum_id, $user_ids[0] );
		bbp_add_moderator( $forum_id, $user_ids[1] );

		try {
			set_current_screen( 'front' );
			$output = bbp_get_moderator_list( $forum_id, $args );
			$this->assertStringStartsWith( '<strong><a ', $output );
			$this->assertStringContainsString( 'First Moderator', $output );
			$this->assertStringContainsString( ' | ', $output );

			ob_start();
			bbp_moderator_list( $forum_id, $args );
			$this->assertSame( $output, ob_get_clean() );

			set_current_screen( 'edit.php' );
			$output = bbp_get_moderator_list( $forum_id, $args );
			$this->assertSame( '<strong>first-moderator | second-moderator</strong>', $output );
			$this->assertSame( 'No moderators', bbp_get_moderator_list( $this->factory->forum->create(), $args ) );
		} finally {
			set_current_screen( 'front' );
		}
	}
}
