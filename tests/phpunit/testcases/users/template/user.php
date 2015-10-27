<?php
/**
 * Tests for the users component user template functions.
 *
 * @group users
 * @group template
 * @group user
 */
class BBP_Tests_Users_Template_User extends BBP_UnitTestCase {

	protected $old_current_user = 0;

	public function setUp() {
		parent::setUp();
		$this->old_current_user = get_current_user_id();
		$this->set_current_user( $this->factory->user->create( array( 'role' => 'administrator' ) ) );
		$this->keymaster_id = get_current_user_id();
		$this->keymaster_userdata = get_userdata( $this->keymaster_id );
		bbp_set_user_role( $this->keymaster_id, bbp_get_keymaster_role() );
	}

	public function tearDown() {
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
		$this->expectOutputString( $formatted_value );
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
	 * @todo   Implement test_bbp_get_displayed_user_id().
	 */
	public function test_bbp_get_displayed_user_id() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_displayed_user_field
	 * @covers ::bbp_get_displayed_user_field
	 * @todo   Implement test_bbp_get_displayed_user_field().
	 */
	public function test_bbp_get_displayed_user_field() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
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

		$profile_link      = '<a href="http://' . WP_TESTS_DOMAIN . '/forums/user/' . $this->keymaster_userdata->user_nicename . '/" rel="nofollow">' . $display_name . '</a>';
		$user_profile_link = bbp_get_user_profile_link( $this->keymaster_id );

		// String.
		$this->assertSame( $profile_link, $user_profile_link );

		// Output.
		$this->expectOutputString( $profile_link );
		bbp_user_profile_link( $this->keymaster_id );

		ob_clean();

		// Ugly permalinks
		$this->set_permalink_structure();

		$profile_link      = '<a href="http://' . WP_TESTS_DOMAIN . '/?bbp_user=' . $this->keymaster_id . '" rel="nofollow">' . $display_name . '</a>';
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
		$profile_url      = 'http://' . WP_TESTS_DOMAIN . '/forums/user/' . $this->keymaster_userdata->user_nicename . '/';
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
		$profile_edit_link      = '<a href="http://' . WP_TESTS_DOMAIN . '/forums/user/' . $this->keymaster_userdata->user_nicename . '/edit/" rel="nofollow">' . $display_name . '</a>';
		$user_profile_edit_link = bbp_get_user_profile_edit_link( $this->keymaster_id );

		// String.
		$this->assertSame( $profile_edit_link, $user_profile_edit_link );

		// Output.
		$this->expectOutputString( $profile_edit_link );
		bbp_user_profile_edit_link( $this->keymaster_id );

		ob_clean();

		// Ugly permalinks
		$this->set_permalink_structure();
		$profile_edit_link      = '<a href="http://' . WP_TESTS_DOMAIN . '/?bbp_user=' . $this->keymaster_id . '&#038;edit=1" rel="nofollow">' . $display_name . '</a>';
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
		$profile_edit_url = 'http://' . WP_TESTS_DOMAIN . '/forums/user/' . $this->keymaster_userdata->user_nicename . '/edit/';

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
		$profile_edit_url = 'http://' . WP_TESTS_DOMAIN . '/forums/user/' . $this->keymaster_userdata->user_nicename . '/edit/';

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
	 * @todo   Implement test_bbp_get_author_display_name().
	 */
	public function test_bbp_get_author_display_name() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_author_email
	 * @covers ::bbp_get_author_email
	 * @todo   Implement test_bbp_get_author_email().
	 */
	public function test_bbp_get_author_email() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_author_url
	 * @covers ::bbp_get_author_url
	 * @todo   Implement test_bbp_get_author_url().
	 */
	public function test_bbp_get_author_url() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

 	/**
 	 * @covers ::bbp_favorites_permalink
	 */
	public function test_bbp_favorites_permalink() {

		// Pretty permalinks
		$this->set_permalink_structure( '/%postname%/' );
		$favorites_url = 'http://' . WP_TESTS_DOMAIN . '/forums/user/' . $this->keymaster_userdata->user_nicename . '/favorites/';

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
		$favorites_url = 'http://' . WP_TESTS_DOMAIN . '/forums/user/' . $this->keymaster_userdata->user_nicename . '/favorites/';

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
	 * @todo   Implement test_bbp_get_user_favorites_link().
	 */
	public function test_bbp_get_user_favorites_link() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

 	/**
 	 * @covers ::bbp_subscriptions_permalink
	 */
	public function test_bbp_subscriptions_permalink() {

		// Pretty permalinks
		$this->set_permalink_structure( '/%postname%/' );
		$subscriptions_url = 'http://' . WP_TESTS_DOMAIN . '/forums/user/' . $this->keymaster_userdata->user_nicename . '/subscriptions/';

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
		$subscriptions_url = 'http://' . WP_TESTS_DOMAIN . '/forums/user/' . $this->keymaster_userdata->user_nicename . '/subscriptions/';

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
	 * @todo   Implement test_bbp_get_user_subscribe_link().
	 */
	public function test_bbp_get_user_subscribe_link() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_notice_edit_user_success
	 * @todo   Implement test_bbp_notice_edit_user_success().
	 */
	public function test_bbp_notice_edit_user_success() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_notice_edit_user_pending_email
	 * @todo   Implement test_bbp_notice_edit_user_pending_email().
	 */
	public function test_bbp_notice_edit_user_pending_email() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_notice_edit_user_is_super_admin
	 * @todo   Implement test_bbp_notice_edit_user_is_super_admin().
	 */
	public function test_bbp_notice_edit_user_is_super_admin() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_edit_user_display_name
	 * @todo   Implement test_bbp_edit_user_display_name().
	 */
	public function test_bbp_edit_user_display_name() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_edit_user_blog_role
	 * @todo   Implement test_bbp_edit_user_blog_role().
	 */
	public function test_bbp_edit_user_blog_role() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_edit_user_forums_role
	 * @todo   Implement test_bbp_edit_user_forums_role().
	 */
	public function test_bbp_edit_user_forums_role() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_edit_user_contact_methods
	 * @todo   Implement test_bbp_edit_user_contact_methods().
	 */
	public function test_bbp_edit_user_contact_methods() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_user_topics_created_url
	 */
	public function test_bbp_user_topics_created_url() {

		// Pretty permalinks
		$this->set_permalink_structure( '/%postname%/' );
		$topics_created_url = 'http://' . WP_TESTS_DOMAIN . '/forums/user/' . $this->keymaster_userdata->user_nicename . '/topics/';

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
		$topics_created_url = 'http://' . WP_TESTS_DOMAIN . '/forums/user/' . $this->keymaster_userdata->user_nicename . '/topics/';

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
		$replies_created_url = 'http://' . WP_TESTS_DOMAIN . '/forums/user/' . $this->keymaster_userdata->user_nicename . '/replies/';

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
		$replies_created_url = 'http://' . WP_TESTS_DOMAIN . '/forums/user/' . $this->keymaster_userdata->user_nicename . '/replies/';

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
	 * @todo   Implement test_bbp_login_notices().
	 */
	public function test_bbp_login_notices() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_logged_in_redirect
	 * @todo   Implement test_bbp_logged_in_redirect().
	 */
	public function test_bbp_logged_in_redirect() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_user_login_fields
	 * @todo   Implement test_bbp_user_login_fields().
	 */
	public function test_bbp_user_login_fields() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_user_register_fields
	 * @todo   Implement test_bbp_user_register_fields().
	 */
	public function test_bbp_user_register_fields() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_user_lost_pass_fields
	 * @todo   Implement test_bbp_user_lost_pass_fields().
	 */
	public function test_bbp_user_lost_pass_fields() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
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
		$author_link = '<a href="http://' . WP_TESTS_DOMAIN . '/forums/user/' . $this->keymaster_userdata->user_nicename . '/" title="View ' . $display_name .
			'&#039;s profile" class="bbp-author-avatar" rel="nofollow">' . $wp_avatar .
			'</a>&nbsp;<a href="http://' . WP_TESTS_DOMAIN . '/forums/user/' . $this->keymaster_userdata->user_nicename . '/" title="View ' . $display_name .
			'&#039;s profile" class="bbp-author-name" rel="nofollow">' . $display_name . '</a>';

		// String.
		$this->assertSame( $author_link, bbp_get_author_link( $t ) );

		// Output.
		$this->expectOutputString( $author_link );
		bbp_author_link( $t );

		ob_clean();

		// Ugly permalinks
		$this->set_permalink_structure();
		$author_link = '<a href="http://' . WP_TESTS_DOMAIN . '/?bbp_user=' . $this->keymaster_id . '" title="View ' . $display_name .
			'&#039;s profile" class="bbp-author-avatar" rel="nofollow">' . $wp_avatar .
			'</a>&nbsp;<a href="http://' . WP_TESTS_DOMAIN . '/?bbp_user=' . $this->keymaster_id . '" title="View ' . $display_name .
			'&#039;s profile" class="bbp-author-name" rel="nofollow">' . $display_name . '</a>';

		// String.
		$this->assertSame( $author_link, bbp_get_author_link( $t ) );

		// Output.
		$this->expectOutputString( $author_link );
		bbp_author_link( $t );
	}

	/**
	 * @covers ::bbp_user_can_view_forum
	 * @todo   Implement test_bbp_user_can_view_forum().
	 */
	public function test_bbp_user_can_view_forum() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_current_user_can_publish_forums
	 * @todo   Implement test_bbp_current_user_can_publish_forums().
	 */
	public function test_bbp_current_user_can_publish_forums() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_current_user_can_publish_topics
	 * @todo   Implement test_bbp_current_user_can_publish_topics().
	 */
	public function test_bbp_current_user_can_publish_topics() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_current_user_can_publish_replies
	 * @todo   Implement test_bbp_current_user_can_publish_replies().
	 */
	public function test_bbp_current_user_can_publish_replies() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_get_forums_for_current_user
	 * @todo   Implement test_bbp_get_forums_for_current_user().
	 */
	public function test_bbp_get_forums_for_current_user() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_current_user_can_access_create_forum_form
	 * @todo   Implement test_bbp_current_user_can_access_create_forum_form().
	 */
	public function test_bbp_current_user_can_access_create_forum_form() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_current_user_can_access_create_topic_form
	 * @todo   Implement test_bbp_current_user_can_access_create_topic_form().
	 */
	public function test_bbp_current_user_can_access_create_topic_form() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_current_user_can_access_create_reply_form
	 * @todo   Implement test_bbp_current_user_can_access_create_reply_form().
	 */
	public function test_bbp_current_user_can_access_create_reply_form() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_current_user_can_access_anonymous_user_form
	 * @todo   Implement test_bbp_current_user_can_access_anonymous_user_form().
	 */
	public function test_bbp_current_user_can_access_anonymous_user_form() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}
}
