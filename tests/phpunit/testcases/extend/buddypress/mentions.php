<?php

/**
 * @group buddypress
 * @group mentions
 * @ticket BBP2773
 */
class BBP_Tests_Extend_BuddyPress_Mentions extends BBP_UnitTestCase {
	protected $bp_factory;
	private $mail = array();
	private $bp_mail = 0;
	private $recipient;
	private $author;
	private $forum;

	public function setUp(): void {
		parent::setUp();
		$this->recipient = $this->factory->user->create( array( 'role' => bbp_get_participant_role(), 'user_login' => 'forum-mention-user', 'user_nicename' => 'forum-mention-user' ) );
		bp_update_user_meta( $this->recipient, 'notification_activity_new_mention', 'yes' );
		$this->author = $this->factory->user->create( array( 'role' => bbp_get_participant_role() ) );
		$this->forum = $this->factory->forum->create();
		$this->set_current_user( $this->author );
		bbpress()->mention_notification_queue = array();
		bbpress()->mention_subscription_recipients = array();
		add_filter( 'pre_wp_mail', array( $this, 'capture_mail' ), 10, 2 );
		add_action( 'bp_send_email', array( $this, 'capture_bp_mail' ) );
	}

	public function tearDown(): void {
		remove_filter( 'pre_wp_mail', array( $this, 'capture_mail' ), 10 );
		remove_action( 'bp_send_email', array( $this, 'capture_bp_mail' ) );
		bbpress()->mention_notification_queue = array();
		bbpress()->mention_subscription_recipients = array();
		parent::tearDown();
	}

	public function capture_mail( $return, $atts ) {
		$this->mail[] = $atts;
		return true;
	}

	public function capture_bp_mail() {
		++$this->bp_mail;
	}

	private function create_topic_activity() {
		$topic = $this->factory->topic->create( array(
			'post_parent' => $this->forum,
			'post_author' => $this->author,
			'post_content' => 'Hello @forum-mention-user',
		) );
		do_action( 'bbp_new_topic', $topic, $this->forum, array(), $this->author );
		return $topic;
	}

	public function test_missing_preferences_do_not_opt_a_buddypress_user_into_forum_email() {
		bp_delete_user_meta( $this->recipient, 'notification_activity_new_mention' );
		$this->assertFalse( bbp_is_user_mention_email_enabled( $this->recipient ) );
		$topic = $this->create_topic_activity();
		bbp_shutdown();
		$this->assertCount( 0, $this->mail );
		$this->assertSame( 0, $this->bp_mail );
		$this->assertSame( array( $this->recipient ), bbp_get_users_for_object( $topic, '_bbp_mention' ) );
		$this->assertNotEmpty( BP_Core_Notification::get_all_for_user( $this->recipient, 'all' ) );
	}

	public function test_native_email_replaces_only_the_forum_activity_email() {
		$topic = $this->create_topic_activity();
		bbp_send_mention_notifications();
		$this->assertCount( 1, $this->mail );
		$this->assertSame( 0, $this->bp_mail );
		$activity_id = (int) get_post_meta( $topic, '_bbp_activity_id', true );
		$this->assertGreaterThan( 0, $activity_id );
		$notifications = BP_Core_Notification::get_all_for_user( $this->recipient, 'all' );
		$this->assertContains( $activity_id, array_map( 'intval', wp_list_pluck( $notifications, 'item_id' ) ) );
		$activity = new BP_Activity_Activity( $activity_id );
		$activity->component = 'activity';
		$activity->type = 'activity_update';
		$this->assertTrue( bbpress()->extend->buddypress->activity->mention_notifications( true, array(), $this->recipient, $activity ) );
	}

	public function test_existing_buddypress_opt_out_is_respected_without_hiding_on_site_notifications() {
		bp_update_user_meta( $this->recipient, 'notification_activity_new_mention', 'no' );
		$topic = $this->create_topic_activity();
		bbp_send_mention_notifications();
		$this->assertCount( 0, $this->mail );
		$this->assertSame( 0, $this->bp_mail );
		$this->assertNotEmpty( BP_Core_Notification::get_all_for_user( $this->recipient, 'all' ) );
		$this->assertSame( array( $this->recipient ), bbp_get_users_for_object( $topic, '_bbp_mention' ) );
	}

	public function test_subscriber_receives_one_email_and_a_buddypress_notification() {
		bbp_add_user_subscription( $this->recipient, $this->forum );
		$this->create_topic_activity();
		bbp_send_mention_notifications();
		$this->assertCount( 1, $this->mail );
		$this->assertSame( 0, $this->bp_mail );
		$this->assertNotEmpty( BP_Core_Notification::get_all_for_user( $this->recipient, 'all' ) );
	}

	public function test_explicit_bbpress_preference_takes_precedence_over_buddypress() {
		bp_update_user_meta( $this->recipient, 'notification_activity_new_mention', 'no' );
		update_user_meta( $this->recipient, '_bbp_mention_email', 'yes' );
		$this->assertTrue( bbp_is_user_mention_email_enabled( $this->recipient ) );

		bp_update_user_meta( $this->recipient, 'notification_activity_new_mention', 'yes' );
		update_user_meta( $this->recipient, '_bbp_mention_email', 'no' );
		$this->assertFalse( bbp_is_user_mention_email_enabled( $this->recipient ) );
	}

	public function test_new_mentions_on_edits_use_native_email_once_and_respect_buddypress_preferences() {
		$topic = $this->factory->topic->create( array( 'post_parent' => $this->forum, 'post_author' => $this->author, 'post_content' => 'No mentions.' ) );
		do_action( 'bbp_new_topic', $topic, $this->forum, array(), $this->author );
		wp_update_post( array( 'ID' => $topic, 'post_content' => '@forum-mention-user' ) );
		do_action( 'bbp_edit_topic', $topic, $this->forum, array(), $this->author );
		bbp_shutdown();
		$this->assertCount( 1, $this->mail );
		$this->assertSame( 0, $this->bp_mail );
		wp_update_post( array( 'ID' => $topic, 'post_content' => 'Removed.' ) );
		wp_update_post( array( 'ID' => $topic, 'post_content' => '@forum-mention-user restored.' ) );
		do_action( 'bbp_edit_topic', $topic, $this->forum, array(), $this->author );
		bbp_shutdown();
		$this->assertCount( 1, $this->mail );
		$this->assertSame( 0, $this->bp_mail );

		$topic = $this->factory->topic->create( array( 'post_parent' => $this->forum, 'post_author' => $this->author, 'post_content' => 'No mentions.' ) );
		wp_update_post( array( 'ID' => $topic, 'post_content' => '@forum-mention-user' ) );
		bp_update_user_meta( $this->recipient, 'notification_activity_new_mention', 'no' );
		bbp_shutdown();
		$this->assertCount( 1, $this->mail );
	}

	public function test_buddypress_preference_is_supplied_by_a_removable_filter() {
		bp_update_user_meta( $this->recipient, 'notification_activity_new_mention', 'yes' );
		$this->assertTrue( bbp_is_user_mention_email_enabled( $this->recipient ) );
		$callback = array( bbpress()->extend->buddypress->members, 'is_mention_email_enabled' );
		remove_filter( 'bbp_is_user_mention_email_enabled', $callback, 10 );
		try {
			$this->assertFalse( bbp_is_user_mention_email_enabled( $this->recipient ) );
		} finally {
			add_filter( 'bbp_is_user_mention_email_enabled', $callback, 10, 3 );
		}
	}

	public function test_member_mentions_url_uses_the_forum_component() {
		$url = bbp_get_user_mentions_url( $this->recipient );
		$this->assertStringContainsString( '/forums/mentions/', $url );
		$this->assertSame( $url, bbpress()->extend->buddypress->members->get_mentions_permalink( $this->recipient ) );
		$settings = apply_filters( 'bbp_mention_email_settings_url', '', $this->recipient );
		$this->assertStringContainsString( '/settings/notifications/', $settings );
	}

	public function test_member_mentions_pagination_preserves_the_forum_component() {
		$directory = $this->factory->post->create( array( 'post_type' => bp_core_get_directory_post_type(), 'post_name' => 'members' ) );
		bp_core_update_directory_page_ids( array( 'members' => $directory ) );
		buddypress()->pages = bp_core_get_directory_pages();
		$this->set_permalink_structure( '/%postname%/' );
		bp_add_rewrite_tags();
		bp_add_rewrite_rules();
		flush_rewrite_rules( false );
		$this->set_current_user( $this->recipient );
		$posts = array();
		for ( $i = 0; $i < 3; ++$i ) {
			$posts[] = $this->create_topic_activity();
		}
		update_option( '_bbp_replies_per_page', 2 );
		$url = bbp_get_user_mentions_url( $this->recipient );
		WP_UnitTestCase::go_to( $url );
		do_action( 'bp_template_redirect' );
		$this->assertTrue( bbp_is_single_user_mentions() );
		$this->assertSame( $url . 'page/%#%/', bbp_get_search_pagination_base() );
		$this->assertTrue( bbp_get_user_mentions( $this->recipient ) );
		$this->assertSame( 3, (int) bbpress()->search_query->found_posts );
		WP_UnitTestCase::go_to( $url . 'page/2/' );
		do_action( 'bp_template_redirect' );
		$this->assertTrue( bbp_is_single_user_mentions() );
		$this->assertSame( 2, bbp_get_paged() );
		$this->assertTrue( bbp_get_user_mentions( $this->recipient ) );
		$this->assertSame( array( $posts[0] ), wp_list_pluck( bbpress()->search_query->posts, 'ID' ) );
	}

	public function test_private_group_access_is_checked_for_the_recipient() {
		$group = $this->bp_factory->group->create( array( 'creator_id' => $this->author, 'status' => 'private' ) );
		bbp_update_forum_group_ids( $this->forum, array( $group ) );
		groups_update_groupmeta( $group, 'forum_id', array( $this->forum ) );
		bbp_privatize_forum( $this->forum );
		new BBP_Forums_Group_Extension();
		$topic = $this->create_topic_activity();
		$this->assertFalse( bbp_user_can_read_mention( $topic, $this->recipient ) );
		bbp_send_mention_notifications();
		$this->assertCount( 0, $this->mail );
		$this->assertSame( 0, $this->bp_mail );
	}
	public function test_private_group_history_counts_change_with_membership() {
		$group = $this->bp_factory->group->create( array( 'creator_id' => $this->author, 'status' => 'private' ) );
		bbp_update_forum_group_ids( $this->forum, array( $group ) );
		groups_update_groupmeta( $group, 'forum_id', array( $this->forum ) );
		bbp_privatize_forum( $this->forum );
		new BBP_Forums_Group_Extension();
		$topic = $this->create_topic_activity();
		$this->set_current_user( $this->recipient );
		$this->assertFalse( bbp_get_user_mentions( $this->recipient ) );
		$this->assertSame( 0, (int) bbpress()->search_query->found_posts );
		groups_join_group( $group, $this->recipient );
		$this->assertTrue( bbp_get_user_mentions( $this->recipient ) );
		$this->assertSame( array( $topic ), wp_list_pluck( bbpress()->search_query->posts, 'ID' ) );
		$this->assertSame( 1, (int) bbpress()->search_query->found_posts );
		groups_leave_group( $group, $this->recipient );
		$this->assertFalse( bbp_get_user_mentions( $this->recipient ) );
	}

}
