<?php
/**
 * Tests for the users component user template functions.
 *
 * @group users
 * @group template
 * @group user
 */
class BBP_Tests_Users_Template_User extends BBP_UnitTestCase {

    protected $keymaster_userdata;
	protected $keymaster_id;
	protected $old_current_user = 0;

	public function setUp(): void {
		parent::setUp();
		$this->old_current_user = get_current_user_id();
		$this->set_current_user( $this->factory->user->create( array( 'role' => 'administrator' ) ) );
		$this->keymaster_id = get_current_user_id();
		$this->keymaster_userdata = get_userdata( $this->keymaster_id );
		bbp_set_user_role( $this->keymaster_id, bbp_get_keymaster_role() );
	}

	public function tearDown(): void {
		parent::tearDown();
		$this->set_current_user( $this->old_current_user );

	}

	/**
	 * @covers ::bbp_user_id
	 * @covers ::bbp_get_user_id
	 */
	public function test_bbp_get_user_id() {
		$int_value = $this->keymaster_userdata->ID;
		$formatted_value = bbp_number_format( $int_value );

		// Integer.
		$user_id = bbp_get_user_id( $this->keymaster_id );
		$this->assertSame( $this->keymaster_id, $user_id );

		// Output.
		$this->expectOutputRegex( '/' . preg_quote($formatted_value, '/') . '/' );
        bbp_user_id( $this->keymaster_id );
	}

	/**
	 * @covers ::bbp_current_user_id
	 * @covers ::bbp_get_current_user_id
	 */
	public function test_bbp_get_current_user_id() {
		$int_value = $this->keymaster_userdata->ID;
		$formatted_value = bbp_number_format( $int_value );

		// Integer.
		$user_id = bbp_get_current_user_id();
		$this->assertSame( $this->keymaster_id, $user_id );

		// Output.
		$this->expectOutputString( $formatted_value );
		bbp_current_user_id( $this->keymaster_id );
	}

	/**
	 * @covers ::bbp_displayed_user_id
	 * @covers ::bbp_get_displayed_user_id
	 */
	public function test_bbp_get_displayed_user_id() {
		$old_displayed_user       = bbpress()->displayed_user;
		bbpress()->displayed_user = $this->keymaster_userdata;

		try {
			$this->assertSame( $this->keymaster_id, bbp_get_displayed_user_id() );
			$this->expectOutputString( (string) $this->keymaster_id );
			bbp_displayed_user_id();
		} finally {
			bbpress()->displayed_user = $old_displayed_user;
		}
	}

	/**
	 * @covers ::bbp_displayed_user_field
	 * @covers ::bbp_get_displayed_user_field
	 */
	public function test_bbp_get_displayed_user_field() {
		$old_displayed_user       = bbpress()->displayed_user;
		bbpress()->displayed_user = $this->keymaster_userdata;
		$old_filter              = $this->keymaster_userdata->filter;

		try {
			$expected_display_name = sanitize_user_field( 'display_name', $this->keymaster_userdata->display_name, $this->keymaster_id, 'display' );
			$this->assertSame( $expected_display_name, bbp_get_displayed_user_field( 'display_name' ) );
			$this->assertSame( $old_filter, $this->keymaster_userdata->filter );
			$this->assertSame( $this->keymaster_userdata->user_email, bbp_get_displayed_user_field( 'user_email', 'raw' ) );
			$this->assertSame( $old_filter, $this->keymaster_userdata->filter );
			$this->expectOutputString( $expected_display_name );
			bbp_displayed_user_field( 'display_name' );
		} finally {
			bbpress()->displayed_user = $old_displayed_user;
		}
	}

	/**
	 * @covers ::bbp_current_user_name
	 * @covers ::bbp_get_current_user_name
	 */
	public function test_bbp_get_current_user_name() {
		$current_user = wp_get_current_user();

		// String.
		$user_id = bbp_get_current_user_name();
		$this->assertSame( $current_user->display_name, $user_id );

		// Output.
		$this->expectOutputString( $current_user->display_name );
		bbp_current_user_name( $this->keymaster_id );
	}

	/**
	 * @covers ::bbp_current_user_avatar
	 * @covers ::bbp_get_current_user_avatar
	 */
	public function test_bbp_get_current_user_avatar() {
		$current_user = get_current_user_id();
		$size = 40;
		$wp_avatar = get_avatar( $current_user, $size );

		// String.
		$bbp_avatar = bbp_get_current_user_avatar( $size );
		$this->assertSame( $bbp_avatar, $wp_avatar );

		// Output.
		$this->expectOutputString( $wp_avatar );
		bbp_current_user_avatar( $size );
	}

	/**
	 * @covers ::bbp_user_profile_link
	 * @covers ::bbp_get_user_profile_link
	 */
	public function test_bbp_get_user_profile_link() {
		$display_name = $this->keymaster_userdata->display_name;

		// Pretty permalinks
		$this->set_permalink_structure( '/%postname%/' );

		$profile_link      = '<a href="http://' . WP_TESTS_DOMAIN . '/forums/users/' . $this->keymaster_userdata->user_nicename . '/">' . $display_name . '</a>';
		$user_profile_link = bbp_get_user_profile_link( $this->keymaster_id );

		// String.
		$this->assertSame( $profile_link, $user_profile_link );

		// Output.
		$this->expectOutputString( $profile_link );
		bbp_user_profile_link( $this->keymaster_id );

		ob_clean();

		// Ugly permalinks
		$this->set_permalink_structure();

		$profile_link      = '<a href="http://' . WP_TESTS_DOMAIN . '/?bbp_user=' . $this->keymaster_id . '">' . $display_name . '</a>';
		$user_profile_link = bbp_get_user_profile_link( $this->keymaster_id );

		// String.
		$this->assertSame( $profile_link, $user_profile_link );

		// Output.
		$this->expectOutputString( $profile_link );
		bbp_user_profile_link( $this->keymaster_id );
	}

	/**
	 * @covers ::bbp_user_nicename
	 * @covers ::bbp_get_user_nicename
	 */
	public function test_bbp_get_user_nicename() {
		$user_nicename = $this->keymaster_userdata->user_nicename;

		// String.
		$this->assertSame( $user_nicename, bbp_get_user_nicename( $this->keymaster_id ) );

		// Output.
		$this->expectOutputString( $user_nicename );
		bbp_user_nicename( $this->keymaster_id );
	}

	/**
	 * @covers ::bbp_user_profile_url
	 * @covers ::bbp_get_user_profile_url
	 */
	public function test_bbp_get_user_profile_url() {

		// Pretty permalinks
		$this->set_permalink_structure( '/%postname%/' );
		$profile_url      = 'http://' . WP_TESTS_DOMAIN . '/forums/users/' . $this->keymaster_userdata->user_nicename . '/';
		$user_profile_url = bbp_get_user_profile_url( $this->keymaster_id );

		// String.
		$this->assertSame( $profile_url, $user_profile_url );

		// Output.
		$this->expectOutputString( $profile_url );
		bbp_user_profile_url( $this->keymaster_id );

		ob_clean();

		// Ugly permalinks
		$this->set_permalink_structure();

		$profile_url      = 'http://' . WP_TESTS_DOMAIN . '/?bbp_user=' . $this->keymaster_id;
		$user_profile_url = bbp_get_user_profile_url( $this->keymaster_id );

		// String.
		$this->assertSame( $profile_url, $user_profile_url );

		// Output.
		$this->expectOutputString( $profile_url );
		bbp_user_profile_url( $this->keymaster_id );
	}

	/**
	 * @covers ::bbp_user_profile_edit_link
	 * @covers ::bbp_get_user_profile_edit_link
	 */
	public function test_bbp_get_user_profile_edit_link() {
		$display_name = $this->keymaster_userdata->display_name;

		// Pretty permalinks
		$this->set_permalink_structure( '/%postname%/' );
		$profile_edit_link      = '<a href="http://' . WP_TESTS_DOMAIN . '/forums/users/' . $this->keymaster_userdata->user_nicename . '/edit/">' . $display_name . '</a>';
		$user_profile_edit_link = bbp_get_user_profile_edit_link( $this->keymaster_id );

		// String.
		$this->assertSame( $profile_edit_link, $user_profile_edit_link );

		// Output.
		$this->expectOutputString( $profile_edit_link );
		bbp_user_profile_edit_link( $this->keymaster_id );

		ob_clean();

		// Ugly permalinks
		$this->set_permalink_structure();
		$profile_edit_link      = '<a href="http://' . WP_TESTS_DOMAIN . '/?bbp_user=' . $this->keymaster_id . '&#038;edit=1">' . $display_name . '</a>';
		$user_profile_edit_link = bbp_get_user_profile_edit_link( $this->keymaster_id );

		// String.
		$this->assertSame( $profile_edit_link, $user_profile_edit_link );

		// Output.
		$this->expectOutputString( $profile_edit_link );
		bbp_user_profile_edit_link( $this->keymaster_id );
	}

	/**
	 * @covers ::bbp_user_profile_edit_url
	 */
	public function test_bbp_user_profile_edit_url() {

		// Pretty permalinks
		$this->set_permalink_structure( '/%postname%/' );
		$profile_edit_url = 'http://' . WP_TESTS_DOMAIN . '/forums/users/' . $this->keymaster_userdata->user_nicename . '/edit/';

		// Output.
		$this->expectOutputString( $profile_edit_url );
		bbp_user_profile_edit_url( $this->keymaster_id );

		ob_clean();

		// Ugly permalinks
		$this->set_permalink_structure();
		$profile_edit_url = 'http://' . WP_TESTS_DOMAIN . '/?bbp_user=' . $this->keymaster_id . '&#038;edit=1';

		// Output.
		$this->expectOutputString( $profile_edit_url );
		bbp_user_profile_edit_url( $this->keymaster_id );
	}

	/**
	 * @covers ::bbp_get_user_profile_edit_url
	 */
	public function test_bbp_get_user_profile_edit_url() {

		// Pretty permalinks
		$this->set_permalink_structure( '/%postname%/' );
		$profile_edit_url = 'http://' . WP_TESTS_DOMAIN . '/forums/users/' . $this->keymaster_userdata->user_nicename . '/edit/';

		// String.
		$this->assertSame( $profile_edit_url, bbp_get_user_profile_edit_url( $this->keymaster_id ) );

		// Ugly permalinks
		$this->set_permalink_structure();
		$profile_edit_url = 'http://' . WP_TESTS_DOMAIN . '/?bbp_user=' . $this->keymaster_id . '&edit=1';

		// String.
		$this->assertSame( $profile_edit_url, bbp_get_user_profile_edit_url( $this->keymaster_id ) );
	}

	/**
	 * @covers ::bbp_user_display_role
	 * @covers ::bbp_get_user_display_role
	 */
	public function test_bbp_get_user_display_role() {
		$display_role = 'Keymaster';

		// String.
		$this->assertSame( $display_role, bbp_get_user_display_role( $this->keymaster_id ) );

		// Output.
		$this->expectOutputString( $display_role );
		bbp_user_display_role( $this->keymaster_id );
	}

	/**
	 * @covers ::bbp_admin_link
	 * @covers ::bbp_get_admin_link
	 */
	public function test_bbp_get_admin_link() {
		$admin_link = '<a href="http://' . WP_TESTS_DOMAIN . '/wp-admin/">Admin</a>';

		$user_admin_link = bbp_get_admin_link( $this->keymaster_id );

		// String.
		$this->assertSame( $admin_link, $user_admin_link );

		// Output.
		$this->expectOutputString( $admin_link );
		bbp_admin_link( $this->keymaster_id );
	}

	/**
	 * @covers ::bbp_author_ip
	 * @covers ::bbp_get_author_ip
	 */
	public function test_bbp_get_author_ip() {
		$t = $this->factory->topic->create();

		$author_ip = '<span class="bbp-author-ip">(127.0.0.1)</span>';

		// String.
		$this->assertSame( $author_ip, bbp_get_author_ip( $t ) );

		// Output.
		$this->expectOutputString( $author_ip );
		bbp_author_ip( $t );
	}

	/**
	 * @covers ::bbp_author_display_name
	 * @covers ::bbp_get_author_display_name
	 */
	public function test_bbp_get_author_display_name() {
		$this->assertSame( '', bbp_get_author_display_name() );
		$this->expectOutputString( '' );
		bbp_author_display_name();
	}

	/**
	 * @covers ::bbp_author_email
	 * @covers ::bbp_get_author_email
	 */
	public function test_bbp_get_author_email() {
		$this->assertSame( '', bbp_get_author_email() );
		$this->expectOutputString( '' );
		bbp_author_email();
	}

	/**
	 * @covers ::bbp_author_url
	 * @covers ::bbp_get_author_url
	 */
	public function test_bbp_get_author_url() {
		$this->assertSame( '', bbp_get_author_url() );
		$this->expectOutputString( '' );
		bbp_author_url();
	}

 	/**
 	 * @covers ::bbp_favorites_permalink
	 */
	public function test_bbp_favorites_permalink() {

		// Pretty permalinks
		$this->set_permalink_structure( '/%postname%/' );
		$favorites_url = 'http://' . WP_TESTS_DOMAIN . '/forums/users/' . $this->keymaster_userdata->user_nicename . '/favorites/';

		// Output.
		$this->expectOutputString( $favorites_url );
		bbp_favorites_permalink( $this->keymaster_id );

		ob_clean();

		// Ugly permalinks
		$this->set_permalink_structure();
		$favorites_url = 'http://' . WP_TESTS_DOMAIN . '/?bbp_user=' . $this->keymaster_id . '&#038;bbp_favs=favorites';

		// Output.
		$this->expectOutputString( $favorites_url );
		bbp_favorites_permalink( $this->keymaster_id );
	}

	/**
 	 * @covers ::bbp_get_favorites_permalink
 	 */
 	public function test_bbp_get_favorites_permalink() {

		// Pretty permalinks
		$this->set_permalink_structure( '/%postname%/' );
		$favorites_url = 'http://' . WP_TESTS_DOMAIN . '/forums/users/' . $this->keymaster_userdata->user_nicename . '/favorites/';

		// String.
		$this->assertSame( $favorites_url, bbp_get_favorites_permalink( $this->keymaster_id ) );

		// Ugly permalinks
		$this->set_permalink_structure();
		$favorites_url = 'http://' . WP_TESTS_DOMAIN . '/?bbp_user=' . $this->keymaster_id . '&bbp_favs=favorites';

		// String.
		$this->assertSame( $favorites_url, bbp_get_favorites_permalink( $this->keymaster_id ) );
 	}

	/**
	 * @covers ::bbp_user_favorites_link
	 * @covers ::bbp_get_user_favorites_link
	 */
	public function test_bbp_get_user_favorites_link() {
		$this->assertFalse( bbp_get_user_favorites_link( array( 'object_id' => 0 ) ) );
		$this->expectOutputString( '' );
		bbp_user_favorites_link( array( 'object_id' => 0 ) );
	}

 	/**
 	 * @covers ::bbp_subscriptions_permalink
	 */
	public function test_bbp_subscriptions_permalink() {

		// Pretty permalinks
		$this->set_permalink_structure( '/%postname%/' );
		$subscriptions_url = 'http://' . WP_TESTS_DOMAIN . '/forums/users/' . $this->keymaster_userdata->user_nicename . '/subscriptions/';

		// Output.
		$this->expectOutputString( $subscriptions_url );
		bbp_subscriptions_permalink( $this->keymaster_id );

		ob_clean();

		// Ugly permalinks
		$this->set_permalink_structure();
		$subscriptions_url = 'http://' . WP_TESTS_DOMAIN . '/?bbp_user=' . $this->keymaster_id . '&#038;bbp_subs=subscriptions';

		// Output.
		$this->expectOutputString( $subscriptions_url );
		bbp_subscriptions_permalink( $this->keymaster_id );
	}

	/**
 	 * @covers ::bbp_get_subscriptions_permalink
 	 */
 	public function test_bbp_get_subscriptions_permalink() {

		// Pretty permalinks
		$this->set_permalink_structure( '/%postname%/' );
		$subscriptions_url = 'http://' . WP_TESTS_DOMAIN . '/forums/users/' . $this->keymaster_userdata->user_nicename . '/subscriptions/';

		// String.
		$this->assertSame( $subscriptions_url, bbp_get_subscriptions_permalink( $this->keymaster_id ) );

		// Ugly permalinks
		$this->set_permalink_structure();
		$subscriptions_url = 'http://' . WP_TESTS_DOMAIN . '/?bbp_user=' . $this->keymaster_id . '&bbp_subs=subscriptions';

		// String.
		$this->assertSame( $subscriptions_url, bbp_get_subscriptions_permalink( $this->keymaster_id ) );
 	}

	/**
	 * @covers ::bbp_user_subscribe_link
	 * @covers ::bbp_get_user_subscribe_link
	 */
	public function test_bbp_get_user_subscribe_link() {
		$this->assertFalse( bbp_get_user_subscribe_link( array( 'object_id' => 0 ) ) );
		$this->expectOutputString( '' );
		bbp_user_subscribe_link( array( 'object_id' => 0 ) );
	}

	/**
	 * @covers ::bbp_notice_edit_user_success
	 */
	public function test_bbp_notice_edit_user_success() {
		ob_start();
		bbp_notice_edit_user_success();
		$this->assertSame( '', ob_get_clean() );

		$_GET['updated'] = '1';
		add_filter( 'bbp_is_single_user_edit', '__return_true' );
		try {
			ob_start();
			bbp_notice_edit_user_success();
			$this->assertStringContainsString( 'User updated.', ob_get_clean() );
		} finally {
			remove_filter( 'bbp_is_single_user_edit', '__return_true' );
			unset( $_GET['updated'] );
		}
	}

	/**
	 * @covers ::bbp_notice_edit_user_pending_email
	 */
	public function test_bbp_notice_edit_user_pending_email() {
		ob_start();
		bbp_notice_edit_user_pending_email();
		$this->assertSame( '', ob_get_clean() );

		$old_displayed_user       = bbpress()->displayed_user;
		bbpress()->displayed_user = $this->keymaster_userdata;
		add_filter( 'bbp_is_user_home_edit', '__return_true' );
		update_user_meta( $this->keymaster_id, '_new_email', array( 'newemail' => 'new@example.org' ) );
		try {
			ob_start();
			bbp_notice_edit_user_pending_email();
			$output = ob_get_clean();
			$this->assertStringContainsString( '<code>new@example.org</code>', $output );
			$this->assertStringContainsString( 'Cancel', $output );
		} finally {
			delete_user_meta( $this->keymaster_id, '_new_email' );
			remove_filter( 'bbp_is_user_home_edit', '__return_true' );
			bbpress()->displayed_user = $old_displayed_user;
		}
	}

	/**
	 * @covers ::bbp_notice_edit_user_is_super_admin
	 */
	public function test_bbp_notice_edit_user_is_super_admin() {
		ob_start();
		bbp_notice_edit_user_is_super_admin();
		$this->assertSame( '', ob_get_clean() );
	}

	/**
	 * @covers ::bbp_edit_user_display_name
	 */
	public function test_bbp_edit_user_display_name() {
		$old_displayed_user = bbpress()->displayed_user;
		bbpress()->displayed_user = (object) array(
			'user_login'  => 'test-user',
			'nickname'    => '<script>alert(1)</script>',
			'first_name'  => '',
			'last_name'   => '',
			'display_name' => 'test-user',
		);

		ob_start();
		bbp_edit_user_display_name();
		$output = ob_get_clean();
		bbpress()->displayed_user = $old_displayed_user;

		$this->assertStringContainsString( 'value="&lt;script&gt;alert(1)&lt;/script&gt;"', $output );
		$this->assertStringContainsString( '&lt;script&gt;alert(1)&lt;/script&gt;</option>', $output );
		$this->assertStringNotContainsString( '<script>', $output );
	}

	/**
	 * @covers ::bbp_edit_user_blog_role
	 */
	public function test_bbp_edit_user_blog_role() {
		ob_start();
		bbp_edit_user_blog_role();
		$this->assertSame( '', ob_get_clean() );

		$old_displayed_user       = bbpress()->displayed_user;
		bbpress()->displayed_user = $this->keymaster_userdata;
		add_filter( 'bbp_is_single_user_edit', '__return_true' );
		try {
			ob_start();
			bbp_edit_user_blog_role();
			$output = ob_get_clean();
			$this->assertStringContainsString( '<select name="role" id="role">', $output );
			$this->assertStringContainsString( 'value="administrator"', $output );
		} finally {
			remove_filter( 'bbp_is_single_user_edit', '__return_true' );
			bbpress()->displayed_user = $old_displayed_user;
		}
	}

	/**
	 * @covers ::bbp_edit_user_forums_role
	 */
	public function test_bbp_edit_user_forums_role() {
		ob_start();
		bbp_edit_user_forums_role();
		$this->assertSame( '', ob_get_clean() );

		$old_displayed_user       = bbpress()->displayed_user;
		bbpress()->displayed_user = $this->keymaster_userdata;
		add_filter( 'bbp_is_single_user_edit', '__return_true' );
		try {
			ob_start();
			bbp_edit_user_forums_role();
			$output = ob_get_clean();
			$this->assertStringContainsString( '<select name="bbp-forums-role" id="bbp-forums-role">', $output );
			$this->assertStringContainsString( 'value="bbp_keymaster"', $output );
		} finally {
			remove_filter( 'bbp_is_single_user_edit', '__return_true' );
			bbpress()->displayed_user = $old_displayed_user;
		}
	}

	/**
	 * @covers ::bbp_edit_user_contact_methods
	 */
	public function test_bbp_edit_user_contact_methods() {
		$old_displayed_user       = bbpress()->displayed_user;
		bbpress()->displayed_user = $this->keymaster_userdata;

		try {
			$this->assertSame( wp_get_user_contact_methods( $this->keymaster_userdata ), bbp_edit_user_contact_methods() );
		} finally {
			bbpress()->displayed_user = $old_displayed_user;
		}
	}

	/**
	 * @covers ::bbp_user_topics_created_url
	 */
	public function test_bbp_user_topics_created_url() {

		// Pretty permalinks
		$this->set_permalink_structure( '/%postname%/' );
		$topics_created_url = 'http://' . WP_TESTS_DOMAIN . '/forums/users/' . $this->keymaster_userdata->user_nicename . '/topics/';

		// Output.
		$this->expectOutputString( $topics_created_url );
		bbp_user_topics_created_url( $this->keymaster_id );

		ob_clean();

		// Ugly permalinks
		$this->set_permalink_structure();
		$topics_created_url = 'http://' . WP_TESTS_DOMAIN . '/?bbp_user=' . $this->keymaster_id . '&#038;bbp_tops=1';

		// Output.
		$this->expectOutputString( $topics_created_url );
		bbp_user_topics_created_url( $this->keymaster_id );
	}

	/**
	 * @covers ::bbp_get_user_topics_created_url
	 */
	public function test_bbp_get_user_topics_created_url() {

		// Pretty permalinks
		$this->set_permalink_structure( '/%postname%/' );
		$topics_created_url = 'http://' . WP_TESTS_DOMAIN . '/forums/users/' . $this->keymaster_userdata->user_nicename . '/topics/';

		// String.
		$this->assertSame( $topics_created_url, bbp_get_user_topics_created_url( $this->keymaster_id ) );

		// Ugly permalinks
		$this->set_permalink_structure();
		$topics_created_url = 'http://' . WP_TESTS_DOMAIN . '/?bbp_user=' . $this->keymaster_id . '&bbp_tops=1';

		// String.
		$this->assertSame( $topics_created_url, bbp_get_user_topics_created_url( $this->keymaster_id ) );
	}

	/**
	 * @covers ::bbp_user_replies_created_url
	 */
	public function test_bbp_user_replies_created_url() {

		// Pretty permalinks
		$this->set_permalink_structure( '/%postname%/' );
		$replies_created_url = 'http://' . WP_TESTS_DOMAIN . '/forums/users/' . $this->keymaster_userdata->user_nicename . '/replies/';

		// Output.
		$this->expectOutputString( $replies_created_url );
		bbp_user_replies_created_url( $this->keymaster_id );

		ob_clean();

		// Ugly permalinks
		$this->set_permalink_structure();
		$replies_created_url = 'http://' . WP_TESTS_DOMAIN . '/?bbp_user='. $this->keymaster_id . '&#038;bbp_reps=1';

		// Output.
		$this->expectOutputString( $replies_created_url );
		bbp_user_replies_created_url( $this->keymaster_id );
	}

	/**
	 * @covers ::bbp_get_user_replies_created_url
	 */
	public function test_bbp_get_user_replies_created_url() {

		// Pretty permalinks
		$this->set_permalink_structure( '/%postname%/' );
		$replies_created_url = 'http://' . WP_TESTS_DOMAIN . '/forums/users/' . $this->keymaster_userdata->user_nicename . '/replies/';

		// String.
		$this->assertSame( $replies_created_url, bbp_get_user_replies_created_url( $this->keymaster_id ) );

		// Ugly permalinks
		$this->set_permalink_structure();
		$replies_created_url = 'http://' . WP_TESTS_DOMAIN . '/?bbp_user='. $this->keymaster_id . '&bbp_reps=1';

		// String.
		$this->assertSame( $replies_created_url, bbp_get_user_replies_created_url( $this->keymaster_id ) );
	}

	/**
	 * @covers ::bbp_login_notices
	 */
	public function test_bbp_login_notices() {
		$old_errors       = bbpress()->errors;
		bbpress()->errors = new WP_Error();

		try {
			bbp_login_notices();
			$this->assertFalse( bbp_has_errors() );

			$_GET['loggedout'] = 'true';
			bbp_login_notices();
			$this->assertSame( 'message', bbpress()->errors->get_error_data( 'loggedout' ) );
			$this->assertSame( 'You are now logged out.', bbpress()->errors->get_error_message( 'loggedout' ) );
		} finally {
			unset( $_GET['loggedout'] );
			bbpress()->errors = $old_errors;
		}
	}

	/**
	 * @covers ::bbp_logged_in_redirect
	 */
	public function test_bbp_logged_in_redirect() {
		$this->set_current_user( 0 );
		$this->assertNull( bbp_logged_in_redirect( 'https://example.org/' ) );
	}

	/**
	 * @covers ::bbp_user_login_fields
	 */
	public function test_bbp_user_login_fields() {
		ob_start();
		bbp_user_login_fields();
		$output = ob_get_clean();
		$this->assertStringContainsString( 'name="user-cookie" value="1"', $output );
		$this->assertStringContainsString( 'name="_wpnonce"', $output );
		$this->assertStringContainsString( 'name="_wp_http_referer"', $output );
	}

	/**
	 * @covers ::bbp_user_register_fields
	 */
	public function test_bbp_user_register_fields() {
		ob_start();
		bbp_user_register_fields();
		$output = ob_get_clean();
		$this->assertStringContainsString( 'name="action"      value="register"', $output );
		$this->assertStringContainsString( 'name="user-cookie" value="1"', $output );
		$this->assertStringContainsString( 'name="_wpnonce"', $output );
		$this->assertStringContainsString( 'checkemail=registered', $output );
	}

	/**
	 * @covers ::bbp_user_lost_pass_fields
	 */
	public function test_bbp_user_lost_pass_fields() {
		ob_start();
		bbp_user_lost_pass_fields();
		$output = ob_get_clean();
		$this->assertStringContainsString( 'name="user-cookie" value="1"', $output );
		$this->assertStringContainsString( 'name="_wpnonce"', $output );
		$this->assertStringContainsString( 'checkemail=confirm', $output );
	}

	/**
	 * @covers ::bbp_author_link
	 * @covers ::bbp_get_author_link
	 */
	public function test_bbp_get_author_link() {
		$t = $this->factory->topic->create();

		$display_name = $this->keymaster_userdata->display_name;
		$current_user = get_current_user_id();
		$size = 80;
		$wp_avatar = get_avatar( $current_user, $size );

		// Pretty permalinks
		$this->set_permalink_structure( '/%postname%/' );
		$author_link = '<a href="http://' . WP_TESTS_DOMAIN . '/forums/users/' . $this->keymaster_userdata->user_nicename . '/" title="View ' . $display_name . '&#039;s profile" class="bbp-author-link"><span class="bbp-author-avatar">' . $wp_avatar .
			'</span><span class="bbp-author-name">' . $display_name . '</span></a>';

		// String.
		$this->assertSame( $author_link, bbp_get_author_link( $t ) );

		// Output.
		$this->expectOutputString( $author_link );
		bbp_author_link( $t );

		ob_clean();

		// Ugly permalinks
		$this->set_permalink_structure();
		$author_link = '<a href="http://' . WP_TESTS_DOMAIN . '/?bbp_user=' . $this->keymaster_id . '" title="View ' . $display_name .
			'&#039;s profile" class="bbp-author-link"><span class="bbp-author-avatar">' . $wp_avatar .
			'</span><span class="bbp-author-name">' . $display_name . '</span></a>';

		// String.
		$this->assertSame( $author_link, bbp_get_author_link( $t ) );

		// Output.
		$this->expectOutputString( $author_link );
		bbp_author_link( $t );
	}

	/**
	 * @covers ::bbp_user_can_view_forum
	 */
	public function test_bbp_user_can_view_forum() {
		$public_id  = $this->factory->forum->create();
		$private_id = $this->factory->forum->create( array( 'post_status' => bbp_get_private_status_id() ) );
		$hidden_id  = $this->factory->forum->create( array( 'post_status' => bbp_get_hidden_status_id() ) );
		$child_id   = $this->factory->forum->create( array( 'post_parent' => $hidden_id ) );

		$participant_id = $this->factory->user->create();
		bbp_set_user_role( $participant_id, bbp_get_participant_role() );
		$moderator_id = $this->factory->user->create();
		bbp_set_user_role( $moderator_id, bbp_get_moderator_role() );

		$this->assertTrue( bbp_user_can_view_forum( array( 'user_id' => 0, 'forum_id' => $public_id ) ) );
		$this->assertFalse( bbp_user_can_view_forum( array( 'user_id' => 0, 'forum_id' => $private_id ) ) );
		$this->assertFalse( bbp_user_can_view_forum( array( 'user_id' => $participant_id, 'forum_id' => $hidden_id ) ) );
		$this->assertFalse( bbp_user_can_view_forum( array( 'user_id' => 0, 'forum_id' => $child_id ) ) );
		$this->assertTrue( bbp_user_can_view_forum( array( 'user_id' => 0, 'forum_id' => $child_id, 'check_ancestors' => false ) ) );
		$this->assertTrue( bbp_user_can_view_forum( array( 'user_id' => $moderator_id, 'forum_id' => $private_id ) ) );
		$this->assertTrue( bbp_user_can_view_forum( array( 'user_id' => $moderator_id, 'forum_id' => $hidden_id ) ) );
		$this->assertTrue( bbp_user_can_view_forum( array( 'user_id' => $this->keymaster_id, 'forum_id' => $hidden_id ) ) );
	}

	/**
	 * @covers ::bbp_current_user_can_publish_forums
	 */
	public function test_bbp_current_user_can_publish_forums() {
		$this->assertTrue( bbp_current_user_can_publish_forums() );
		$this->set_current_user( 0 );
		$this->assertFalse( bbp_current_user_can_publish_forums() );
	}

	/**
	 * @covers ::bbp_current_user_can_publish_topics
	 */
	public function test_bbp_current_user_can_publish_topics() {
		$this->assertTrue( bbp_current_user_can_publish_topics() );
		$this->set_current_user( 0 );
		$this->assertSame( bbp_allow_anonymous(), bbp_current_user_can_publish_topics() );
	}

	/**
	 * @covers ::bbp_current_user_can_publish_replies
	 */
	public function test_bbp_current_user_can_publish_replies() {
		$this->assertTrue( bbp_current_user_can_publish_replies() );
		$this->set_current_user( 0 );
		$this->assertSame( bbp_allow_anonymous(), bbp_current_user_can_publish_replies() );
	}

	/**
	 * @covers ::bbp_get_forums_for_current_user
	 */
	public function test_bbp_get_forums_for_current_user() {
		$this->assertFalse( bbp_get_forums_for_current_user() );
		$forum_id = $this->factory->forum->create();
		$forums   = bbp_get_forums_for_current_user();
		$this->assertCount( 1, $forums );
		$this->assertSame( $forum_id, $forums[0]->ID );
	}

	/**
	 * @covers ::bbp_current_user_can_access_create_forum_form
	 */
	public function test_bbp_current_user_can_access_create_forum_form() {
		$this->assertTrue( bbp_current_user_can_access_create_forum_form() );
		$this->set_current_user( 0 );
		$this->assertFalse( bbp_current_user_can_access_create_forum_form() );
	}

	/**
	 * @covers ::bbp_current_user_can_access_create_topic_form
	 */
	public function test_bbp_current_user_can_access_create_topic_form() {
		$this->assertTrue( bbp_current_user_can_access_create_topic_form() );
		$this->set_current_user( 0 );
		$this->assertFalse( bbp_current_user_can_access_create_topic_form() );
	}

	/**
	 * @covers ::bbp_current_user_can_access_create_reply_form
	 */
	public function test_bbp_current_user_can_access_create_reply_form() {
		$this->assertTrue( bbp_current_user_can_access_create_reply_form() );
		$this->set_current_user( 0 );
		$this->assertFalse( bbp_current_user_can_access_create_reply_form() );
	}

	/**
	 * @covers ::bbp_current_user_can_access_anonymous_user_form
	 */
	public function test_bbp_current_user_can_access_anonymous_user_form() {
		$this->assertFalse( bbp_current_user_can_access_anonymous_user_form() );
		$this->set_current_user( 0 );
		$this->assertSame( bbp_allow_anonymous(), bbp_current_user_can_access_anonymous_user_form() );
	}
}
