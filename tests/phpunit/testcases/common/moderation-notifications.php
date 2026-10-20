<?php

/**
 * Tests for moderation notifications.
 *
 * @group common
 * @group notifications
 */
class BBP_Tests_Common_Moderation_Notifications extends BBP_UnitTestCase {

	private $mail = array();

	public function setUp(): void {
		parent::setUp();

		update_option(
			'_bbp_moderation_notification_recipient_types',
			array( 'keymasters', 'moderators', 'forum_moderators' )
		);
		add_filter( 'pre_wp_mail', array( $this, 'capture_mail' ), 10, 2 );
	}

	public function tearDown(): void {
		remove_filter( 'pre_wp_mail', array( $this, 'capture_mail' ), 10 );
		parent::tearDown();
	}

	public function capture_mail( $return, $atts ) {
		$this->mail[] = $atts;

		return true;
	}

	private function get_headers() {
		return implode( "\n", (array) $this->mail[0]['headers'] );
	}

	private function create_user_with_bbp_role( $role ) {
		$user_id = $this->factory->user->create();

		bbp_set_user_role( $user_id, $role );

		return $user_id;
	}

	/**
	 * @ticket BBP3349
	 * @covers ::bbp_notify_moderation_recipients_on_post_pending
	 * @covers ::bbp_notify_moderation_recipients
	 * @covers ::bbp_get_moderation_notification_user_ids
	 */
	public function test_pending_topic_notifies_users_who_can_moderate_forum() {
		$forum_id           = $this->factory->forum->create( array( 'post_title' => 'Notification Forum' ) );
		$other_forum_id     = $this->factory->forum->create();
		$author_id          = $this->factory->user->create( array( 'display_name' => 'Topic Author' ) );
		$keymaster_id       = $this->create_user_with_bbp_role( bbp_get_keymaster_role() );
		$moderator_id       = $this->create_user_with_bbp_role( bbp_get_moderator_role() );
		$forum_mod_id       = $this->factory->user->create();
		$other_forum_mod_id = $this->factory->user->create();

		bbp_set_user_role( $author_id, bbp_get_participant_role() );
		bbp_set_user_role( $forum_mod_id, bbp_get_participant_role() );
		bbp_set_user_role( $other_forum_mod_id, bbp_get_participant_role() );
		bbp_add_moderator( $forum_id, $forum_mod_id );
		bbp_add_moderator( $other_forum_id, $other_forum_mod_id );

		$topic_id = $this->factory->topic->create(
			array(
				'post_author'  => $author_id,
				'post_content' => 'Held topic content.',
				'post_parent'  => $forum_id,
				'post_status'  => bbp_get_pending_status_id(),
				'post_title'   => 'Held topic title',
			)
		);

		$this->assertCount( 1, $this->mail );
		$this->assertStringContainsString( get_userdata( $keymaster_id )->user_email, $this->get_headers() );
		$this->assertStringContainsString( get_userdata( $moderator_id )->user_email, $this->get_headers() );
		$this->assertStringContainsString( get_userdata( $forum_mod_id )->user_email, $this->get_headers() );
		$this->assertStringNotContainsString( get_userdata( $author_id )->user_email, $this->get_headers() );
		$this->assertStringNotContainsString( get_userdata( $other_forum_mod_id )->user_email, $this->get_headers() );
		$this->assertSame( '[' . wp_specialchars_decode( get_option( 'blogname' ), ENT_QUOTES ) . '] New topic awaiting moderation: "Held topic title"', $this->mail[0]['subject'] );
		$this->assertStringContainsString( "Author: Topic Author\nForum: Notification Forum\nTopic: Held topic title", $this->mail[0]['message'] );
		$this->assertStringContainsString( 'Held topic content.', $this->mail[0]['message'] );
		$this->assertStringContainsString( bbp_add_view_all( bbp_get_topic_permalink( $topic_id ), true ), $this->mail[0]['message'] );
		$this->assertStringContainsString( 'included in the forum moderation notification settings', $this->mail[0]['message'] );
	}

	/**
	 * @ticket BBP3349
	 * @covers ::bbp_notify_moderation_recipients_on_post_pending
	 * @covers ::bbp_notify_moderation_recipients
	 */
	public function test_pending_reply_notifies_once_on_creation() {
		$forum_id     = $this->factory->forum->create();
		$topic_id     = $this->factory->topic->create(
			array(
				'post_parent' => $forum_id,
				'post_title'  => 'Reply topic title',
			)
		);
		$author_id    = $this->factory->user->create( array( 'display_name' => 'Reply Author' ) );
		$keymaster_id = $this->create_user_with_bbp_role( bbp_get_keymaster_role() );

		$reply_id = $this->factory->reply->create(
			array(
				'post_author'  => $author_id,
				'post_content' => 'Held reply content.',
				'post_parent'  => $topic_id,
				'post_status'  => bbp_get_pending_status_id(),
			)
		);

		$this->assertCount( 1, $this->mail );
		$this->assertStringContainsString( get_userdata( $keymaster_id )->user_email, $this->get_headers() );
		$this->assertSame( '[' . wp_specialchars_decode( get_option( 'blogname' ), ENT_QUOTES ) . '] New reply awaiting moderation: "Reply topic title"', $this->mail[0]['subject'] );
		$this->assertStringContainsString( 'Author: Reply Author', $this->mail[0]['message'] );
		$this->assertStringContainsString( 'Held reply content.', $this->mail[0]['message'] );

		wp_update_post( array( 'ID' => $reply_id, 'post_title' => 'Still pending' ) );
		bbp_approve_reply( $reply_id );
		bbp_unapprove_reply( $reply_id );

		$this->assertCount( 1, $this->mail );
	}

	/**
	 * @ticket BBP3349
	 * @covers ::bbp_insert_topic
	 * @covers ::bbp_notify_moderation_recipients
	 */
	public function test_insert_topic_notifies_after_forum_meta_is_saved() {
		update_option( '_bbp_moderation_notification_recipient_types', array( 'keymasters', 'forum_moderators' ) );

		$forum_id           = $this->factory->forum->create();
		$other_forum_id     = $this->factory->forum->create();
		$keymaster_id       = $this->create_user_with_bbp_role( bbp_get_keymaster_role() );
		$forum_mod_id       = $this->factory->user->create();
		$other_forum_mod_id = $this->factory->user->create();

		bbp_set_user_role( $forum_mod_id, bbp_get_participant_role() );
		bbp_set_user_role( $other_forum_mod_id, bbp_get_participant_role() );
		bbp_add_moderator( $forum_id, $forum_mod_id );
		bbp_add_moderator( $other_forum_id, $other_forum_mod_id );

		add_filter( 'bbp_is_moderation_notifications_active', '__return_true', PHP_INT_MAX );
		try {
			$topic_id = bbp_insert_topic(
				array(
					'post_status' => bbp_get_pending_status_id(),
					'post_title'  => 'Pending topic inserted by code',
				),
				array(
					'forum_id' => $forum_id,
				)
			);
		} finally {
			remove_filter( 'bbp_is_moderation_notifications_active', '__return_true', PHP_INT_MAX );
		}

		$this->assertSame( $forum_id, bbp_get_topic_forum_id( $topic_id ) );
		$this->assertCount( 1, $this->mail );
		$this->assertStringContainsString( get_userdata( $keymaster_id )->user_email, $this->get_headers() );
		$this->assertStringContainsString( get_userdata( $forum_mod_id )->user_email, $this->get_headers() );
		$this->assertStringNotContainsString( get_userdata( $other_forum_mod_id )->user_email, $this->get_headers() );

		do_action( 'bbp_new_topic', $topic_id, $forum_id, array(), 0 );
		$this->assertCount( 1, $this->mail );
	}

	/**
	 * @ticket BBP3349
	 * @covers ::bbp_insert_reply
	 * @covers ::bbp_notify_moderation_recipients
	 */
	public function test_insert_reply_notifies_after_topic_meta_is_saved() {
		update_option( '_bbp_moderation_notification_recipient_types', array( 'forum_moderators' ) );

		$forum_id           = $this->factory->forum->create();
		$other_forum_id     = $this->factory->forum->create();
		$topic_id           = $this->factory->topic->create( array( 'post_parent' => $forum_id ) );
		$forum_mod_id       = $this->factory->user->create();
		$other_forum_mod_id = $this->factory->user->create();

		bbp_set_user_role( $forum_mod_id, bbp_get_participant_role() );
		bbp_set_user_role( $other_forum_mod_id, bbp_get_participant_role() );
		bbp_add_moderator( $forum_id, $forum_mod_id );
		bbp_add_moderator( $other_forum_id, $other_forum_mod_id );

		$reply_id = bbp_insert_reply(
			array(
				'post_content' => 'Pending reply inserted by code',
				'post_parent'  => 0,
				'post_status'  => bbp_get_pending_status_id(),
			),
			array(
				'forum_id' => $forum_id,
				'topic_id' => $topic_id,
			)
		);

		$this->assertSame( $topic_id, bbp_get_reply_topic_id( $reply_id ) );
		$this->assertCount( 1, $this->mail );
		$this->assertStringContainsString( get_userdata( $forum_mod_id )->user_email, $this->get_headers() );
		$this->assertStringNotContainsString( get_userdata( $other_forum_mod_id )->user_email, $this->get_headers() );
	}

	/**
	 * @ticket BBP3349
	 * @covers ::bbp_notify_moderation_recipients_on_post_pending
	 * @covers ::bbp_notify_moderation_recipients
	 */
	public function test_raw_inserts_notify_once_and_status_transitions_do_not_notify_again() {
		$forum_id = $this->factory->forum->create();
		$topic_id = $this->factory->topic->create( array( 'post_parent' => $forum_id ) );

		$this->create_user_with_bbp_role( bbp_get_keymaster_role() );

		$pending_topic_id = wp_insert_post(
			array(
				'post_content' => 'Raw pending topic',
				'post_parent'  => $forum_id,
				'post_status'  => bbp_get_pending_status_id(),
				'post_title'   => 'Raw pending topic',
				'post_type'    => bbp_get_topic_post_type(),
			)
		);

		$this->assertCount( 1, $this->mail );

		wp_update_post( array( 'ID' => $pending_topic_id, 'post_title' => 'Still pending' ) );
		bbp_approve_topic( $pending_topic_id );
		bbp_unapprove_topic( $pending_topic_id );
		bbp_spam_topic( $pending_topic_id );
		bbp_unspam_topic( $pending_topic_id );
		wp_trash_post( $pending_topic_id );
		wp_untrash_post( $pending_topic_id );

		$this->assertCount( 1, $this->mail );

		$pending_reply_id = wp_insert_post(
			array(
				'post_content' => 'Raw pending reply',
				'post_parent'  => $topic_id,
				'post_status'  => bbp_get_pending_status_id(),
				'post_type'    => bbp_get_reply_post_type(),
			)
		);

		$this->assertCount( 2, $this->mail );

		wp_update_post( array( 'ID' => $pending_reply_id, 'post_content' => 'Still pending' ) );
		bbp_approve_reply( $pending_reply_id );
		bbp_unapprove_reply( $pending_reply_id );
		bbp_spam_reply( $pending_reply_id );
		bbp_unspam_reply( $pending_reply_id );
		wp_trash_post( $pending_reply_id );
		wp_untrash_post( $pending_reply_id );

		$this->assertCount( 2, $this->mail );
	}

	/**
	 * @ticket BBP3349
	 * @covers ::bbp_notify_moderation_recipients_on_post_pending
	 */
	public function test_disabled_notifications_do_not_send() {
		update_option( '_bbp_moderation_notification_recipient_types', array() );
		$this->create_user_with_bbp_role( bbp_get_keymaster_role() );
		$forum_id = $this->factory->forum->create();

		$this->factory->topic->create(
			array(
				'post_parent' => $forum_id,
				'post_status' => bbp_get_pending_status_id(),
			)
		);

		$this->assertCount( 0, $this->mail );
	}

	/**
	 * @ticket BBP3349
	 * @covers ::bbp_get_moderation_notification_user_ids
	 */
	public function test_enabled_recipient_types_limit_moderation_notification_users() {
		update_option( '_bbp_moderation_notification_recipient_types', array( 'forum_moderators' ) );

		$forum_id     = $this->factory->forum->create();
		$keymaster_id = $this->create_user_with_bbp_role( bbp_get_keymaster_role() );
		$moderator_id = $this->create_user_with_bbp_role( bbp_get_moderator_role() );
		$forum_mod_id = $this->factory->user->create();

		bbp_set_user_role( $forum_mod_id, bbp_get_participant_role() );
		bbp_add_moderator( $forum_id, $forum_mod_id );

		$topic_id = $this->factory->topic->create(
			array(
				'post_parent' => $forum_id,
				'post_status' => bbp_get_pending_status_id(),
			)
		);
		$user_ids = bbp_get_moderation_notification_user_ids( $topic_id, $forum_id );

		$this->assertSame( array( $forum_mod_id ), $user_ids );
		$this->assertNotContains( $keymaster_id, $user_ids );
		$this->assertNotContains( $moderator_id, $user_ids );
	}

	/**
	 * @ticket BBP3349
	 * @covers ::bbp_get_moderation_notification_user_ids
	 */
	public function test_core_recipient_types_exclude_users_without_current_moderation_access() {
		update_option( '_bbp_moderation_notification_recipient_types', array( 'forum_moderators' ) );

		$forum_id    = $this->factory->forum->create();
		$topic_id    = $this->factory->topic->create( array( 'post_parent' => $forum_id ) );
		$active_id   = $this->factory->user->create();
		$inactive_id = $this->factory->user->create();

		bbp_set_user_role( $active_id, bbp_get_participant_role() );
		get_userdata( $inactive_id )->set_role( '' );
		bbp_add_moderator( $forum_id, $active_id );
		bbp_add_moderator( $forum_id, $inactive_id );

		$this->assertSame( array( $active_id ), bbp_get_moderation_notification_user_ids( $topic_id, $forum_id ) );
	}

	/**
	 * @ticket BBP3349
	 * @covers ::bbp_notify_moderation_recipients
	 */
	public function test_pending_reply_to_pending_topic_does_not_send_another_notification() {
		$this->create_user_with_bbp_role( bbp_get_keymaster_role() );
		$forum_id = $this->factory->forum->create();
		$topic_id = $this->factory->topic->create(
			array(
				'post_parent' => $forum_id,
				'post_status' => bbp_get_pending_status_id(),
			)
		);

		$this->assertCount( 1, $this->mail );

		$this->factory->reply->create(
			array(
				'post_parent' => $topic_id,
				'post_status' => bbp_get_pending_status_id(),
			)
		);

		$this->assertCount( 1, $this->mail );
	}

	/**
	 * @ticket BBP3349
	 * @covers ::bbp_notify_moderation_recipients
	 */
	public function test_front_end_anonymous_topic_notification_uses_saved_author_name() {
		update_option( '_bbp_moderation_notification_recipient_types', array() );
		$forum_id = $this->factory->forum->create();
		$topic_id = $this->factory->topic->create(
			array(
				'post_author' => 0,
				'post_parent' => $forum_id,
				'post_status' => bbp_get_pending_status_id(),
				'post_title'  => 'Anonymous pending topic',
			)
		);

		bbp_update_anonymous_post_author(
			$topic_id,
			array(
				'bbp_anonymous_name'    => 'Anonymous Author',
				'bbp_anonymous_email'   => 'anonymous@example.org',
				'bbp_anonymous_website' => '',
			),
			bbp_get_topic_post_type()
		);
		$this->create_user_with_bbp_role( bbp_get_keymaster_role() );
		update_option( '_bbp_moderation_notification_recipient_types', array( 'keymasters' ) );
		do_action( 'bbp_new_topic', $topic_id, $forum_id, array(), 0 );

		$this->assertCount( 1, $this->mail );
		$this->assertStringContainsString( 'Author: Anonymous Author', $this->mail[0]['message'] );
	}

	/**
	 * @ticket BBP3349
	 * @covers ::bbp_notify_moderation_recipients_on_post_pending
	 */
	public function test_public_topic_does_not_notify_moderation_recipients() {
		$this->create_user_with_bbp_role( bbp_get_keymaster_role() );
		$forum_id = $this->factory->forum->create();

		$this->factory->topic->create(
			array(
				'post_parent' => $forum_id,
				'post_status' => bbp_get_public_status_id(),
			)
		);

		$this->assertCount( 0, $this->mail );
	}
}
