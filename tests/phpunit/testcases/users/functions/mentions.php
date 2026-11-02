<?php

/**
 * @group users
 * @group mentions
 * @group notifications
 * @ticket BBP2773
 */
class BBP_Tests_Users_Functions_Mentions extends BBP_UnitTestCase {
	private $mail = array();
	private $recipient;
	private $author;
	private $forum;
	private $old_search_query;
	private $old_displayed_user;

	public function setUp(): void {
		parent::setUp();
		$this->old_search_query = bbpress()->search_query;
		$this->old_displayed_user = bbpress()->displayed_user;
		$this->recipient = $this->factory->user->create( array( 'role' => bbp_get_participant_role(), 'user_nicename' => 'mention-user' ) );
		update_user_meta( $this->recipient, '_bbp_mention_email', 'yes' );
		$this->author = $this->factory->user->create( array( 'role' => bbp_get_participant_role() ) );
		$this->forum = $this->factory->forum->create();
		bbpress()->mention_notification_queue = array();
		bbpress()->mention_subscription_recipients = array();
		add_filter( 'pre_wp_mail', array( $this, 'capture_mail' ), 10, 2 );
	}

	public function tearDown(): void {
		remove_filter( 'pre_wp_mail', array( $this, 'capture_mail' ), 10 );
		bbpress()->mention_notification_queue = array();
		bbpress()->mention_subscription_recipients = array();
		bbpress()->search_query = $this->old_search_query;
		bbpress()->displayed_user = $this->old_displayed_user;
		unset( $_POST['_bbp_mention_nonce'], $_POST['bbp_mention_email'] );
		$this->set_current_user( 0 );
		parent::tearDown();
	}

	public function capture_mail( $return, $atts ) {
		$this->mail[] = $atts;
		return true;
	}

	private function topic( $args = array() ) {
		return $this->factory->topic->create( array_merge( array(
			'post_parent' => $this->forum,
			'post_author' => $this->author,
			'post_content' => 'Hello @mention-user!',
		), $args ) );
	}

	public function test_parser_recognizes_unique_complete_slugs_only_in_authored_text() {
		$this->assertSame( array( $this->recipient ), bbp_get_mentioned_user_ids( '@mention-user. [@mention-user] (@mention-user) @missing' ) );
		$this->assertSame( array(), bbp_get_mentioned_user_ids( 'email@mention-user.com @@mention-user @mention-user.extra @mention-user@example.com <a title="@mention-user">link</a> <a href="/">@mention-user</a><pre>@mention-user</pre><code>@mention-user</code><blockquote>@mention-user</blockquote>' ) );
		$this->assertSame( array( $this->recipient ), bbp_get_mentioned_user_ids( '<code><code>@mention-user</code></code> @mention-user' ) );
	}

	public function test_mentions_are_indexed_but_not_emailed_until_the_user_opts_in() {
		delete_user_meta( $this->recipient, '_bbp_mention_email' );
		$this->assertFalse( bbp_is_user_mention_email_enabled( $this->recipient ) );
		$topic = $this->topic();
		$reply = $this->factory->reply->create( array( 'post_parent' => $topic, 'post_author' => $this->author, 'post_content' => '@mention-user', 'reply_meta' => array( 'topic_id' => $topic, 'forum_id' => $this->forum ) ) );
		$edited = $this->topic( array( 'post_content' => 'No mentions.' ) );
		wp_update_post( array( 'ID' => $edited, 'post_content' => '@mention-user added.' ) );
		bbp_shutdown();
		$this->assertCount( 0, $this->mail );
		foreach ( array( $topic, $reply, $edited ) as $post_id ) {
			$this->assertSame( array( $this->recipient ), bbp_get_users_for_object( $post_id, '_bbp_mention' ) );
		}

		$this->set_current_user( $this->recipient );
		ob_start();
		bbp_mention_email_settings( get_userdata( $this->recipient ) );
		$html = ob_get_clean();
		$this->assertStringContainsString( 'name="bbp_mention_email"', $html );
		$this->assertStringNotContainsString( 'checked=', $html );
		$_POST['_bbp_mention_nonce'] = wp_create_nonce( 'bbp-mention-preferences-' . $this->recipient );
		$_POST['bbp_mention_email'] = '1';
		bbp_save_mention_email_preference( $this->recipient );
		$this->assertTrue( bbp_is_user_mention_email_enabled( $this->recipient ) );
		wp_update_post( array( 'ID' => $topic, 'post_content' => '@mention-user already mentioned.' ) );
		$this->topic();
		bbp_shutdown();
		$this->assertCount( 1, $this->mail );
	}

	public function test_only_an_explicit_yes_enables_native_mention_email() {
		foreach ( array( '', 'no', 'invalid', 1, true, array( 'yes' ) ) as $setting ) {
			update_user_meta( $this->recipient, '_bbp_mention_email', $setting );
			$this->assertFalse( bbp_is_user_mention_email_enabled( $this->recipient ) );
		}
		update_user_meta( $this->recipient, '_bbp_mention_email', 'yes' );
		$this->assertTrue( bbp_is_user_mention_email_enabled( $this->recipient ) );
	}

	public function test_new_topic_notifies_an_unsubscribed_user_once() {
		$topic = $this->topic();
		$this->assertSame( array( $this->recipient ), bbp_get_users_for_object( $topic, '_bbp_mention' ) );
		$this->assertCount( 0, $this->mail );
		bbp_shutdown();
		bbp_shutdown();
		$this->assertCount( 1, $this->mail );
		$this->assertSame( get_userdata( $this->recipient )->user_email, $this->mail[0]['to'] );
		$this->assertStringContainsString( bbp_get_topic_permalink( $topic ), $this->mail[0]['message'] );
		$this->assertStringNotContainsString( 'Hello', $this->mail[0]['message'] );
		$this->assertSame( array( 'handled' => array( $this->recipient ) ), get_post_meta( $topic, '_bbp_mention_notification', true ) );
	}

	public function test_reply_notifies_without_a_subscription() {
		$topic = $this->topic( array( 'post_content' => 'No mentions.' ) );
		$reply = $this->factory->reply->create( array( 'post_parent' => $topic, 'post_author' => $this->author, 'post_content' => '@mention-user', 'reply_meta' => array( 'topic_id' => $topic, 'forum_id' => $this->forum ) ) );
		bbp_send_mention_notifications();
		$this->assertCount( 1, $this->mail );
		$this->assertStringContainsString( bbp_get_reply_url( $reply ), $this->mail[0]['message'] );
	}

	public function test_pending_topic_waits_for_approval_and_does_not_resend_after_restore() {
		$topic = $this->topic( array( 'post_status' => bbp_get_pending_status_id() ) );
		bbp_send_mention_notifications();
		$this->assertCount( 0, $this->mail );
		wp_update_post( array( 'ID' => $topic, 'post_status' => bbp_get_public_status_id() ) );
		bbp_send_mention_notifications();
		$this->assertCount( 1, $this->mail );
		wp_trash_post( $topic );
		wp_untrash_post( $topic );
		wp_update_post( array( 'ID' => $topic, 'post_status' => bbp_get_public_status_id() ) );
		bbp_send_mention_notifications();
		$this->assertCount( 1, $this->mail );
	}

	public static function notification_post_types() {
		return array( 'topic' => array( 'topic' ), 'reply' => array( 'reply' ) );
	}

	public function test_unmentioned_posts_do_not_allocate_notification_records() {
		foreach ( array( bbp_get_public_status_id(), bbp_get_pending_status_id() ) as $status ) {
			$topic = $this->topic( array( 'post_status' => $status, 'post_content' => 'No mentions.' ) );
			wp_update_post( array( 'ID' => $topic, 'post_title' => 'Edited title.' ) );
			$this->assertFalse( metadata_exists( 'post', $topic, '_bbp_mention_notification' ) );
		}
	}

	/**
	 * @dataProvider notification_post_types
	 */
	public function test_edits_notify_each_new_recipient_once_per_post( $post_type ) {
		$post = $this->topic( array( 'post_content' => 'No mentions.' ) );
		if ( 'reply' === $post_type ) {
			$post = $this->factory->reply->create( array( 'post_parent' => $post, 'post_author' => $this->author, 'post_content' => 'No mentions.', 'reply_meta' => array( 'topic_id' => $post, 'forum_id' => $this->forum ) ) );
		}
		$other = $this->factory->user->create( array( 'role' => bbp_get_participant_role(), 'user_nicename' => 'other-mention-user' ) );
		update_user_meta( $other, '_bbp_mention_email', 'yes' );
		bbp_shutdown();
		$this->assertCount( 0, $this->mail );

		wp_update_post( array( 'ID' => $post, 'post_content' => '@mention-user @mention-user' ) );
		bbp_shutdown();
		$this->assertCount( 1, $this->mail );
		$this->assertSame( get_userdata( $this->recipient )->user_email, $this->mail[0]['to'] );
		$url = ( 'reply' === $post_type ) ? bbp_get_reply_url( $post ) : bbp_get_topic_permalink( $post );
		$this->assertStringContainsString( $url, $this->mail[0]['message'] );

		wp_update_post( array( 'ID' => $post, 'post_content' => '@mention-user edited.' ) );
		wp_update_post( array( 'ID' => $post, 'post_title' => 'Title only.' ) );
		bbp_shutdown();
		$this->assertCount( 1, $this->mail );

		wp_update_post( array( 'ID' => $post, 'post_content' => 'Removed.' ) );
		$this->assertSame( array(), bbp_get_users_for_object( $post, '_bbp_mention' ) );
		wp_update_post( array( 'ID' => $post, 'post_content' => '@mention-user restored. @other-mention-user added.' ) );
		bbp_shutdown();
		bbp_shutdown();
		$this->assertCount( 2, $this->mail );
		$this->assertSame( get_userdata( $other )->user_email, $this->mail[1]['to'] );
		$this->assertSame( array( 'handled' => array( $this->recipient, $other ) ), get_post_meta( $post, '_bbp_mention_notification', true ) );

		$this->topic();
		bbp_shutdown();
		$this->assertCount( 3, $this->mail );
		$this->assertSame( get_userdata( $this->recipient )->user_email, $this->mail[2]['to'] );
	}

	public function test_multiple_edits_before_shutdown_preserve_all_new_recipients() {
		$topic = $this->topic();
		$other = $this->factory->user->create( array( 'role' => bbp_get_participant_role(), 'user_nicename' => 'other-mention-user' ) );
		update_user_meta( $other, '_bbp_mention_email', 'yes' );
		wp_update_post( array( 'ID' => $topic, 'post_content' => '@mention-user @other-mention-user' ) );
		wp_update_post( array( 'ID' => $topic, 'post_content' => '@mention-user @other-mention-user edited.' ) );
		bbp_shutdown();
		$this->assertSame( array( get_userdata( $this->recipient )->user_email, get_userdata( $other )->user_email ), wp_list_pluck( $this->mail, 'to' ) );
	}

	public function test_removed_mentions_are_not_emailed_or_requeued_when_restored() {
		$topic = $this->topic();
		wp_update_post( array( 'ID' => $topic, 'post_content' => 'Removed.' ) );
		bbp_send_mention_notifications();
		$this->assertCount( 0, $this->mail );
		wp_update_post( array( 'ID' => $topic, 'post_content' => '@mention-user again' ) );
		bbp_send_mention_notifications();
		$this->assertCount( 0, $this->mail );
	}

	public function test_first_edit_of_an_unindexed_post_only_notifies_new_mentions() {
		remove_action( 'wp_after_insert_post', 'bbp_save_post_mentions', 20 );
		try {
			$topic = $this->topic();
		} finally {
			add_action( 'wp_after_insert_post', 'bbp_save_post_mentions', 20, 4 );
		}
		$other = $this->factory->user->create( array( 'role' => bbp_get_participant_role(), 'user_nicename' => 'other-mention-user' ) );
		update_user_meta( $other, '_bbp_mention_email', 'yes' );
		wp_update_post( array( 'ID' => $topic, 'post_content' => '@mention-user @other-mention-user' ) );
		bbp_shutdown();
		$this->assertCount( 1, $this->mail );
		$this->assertSame( get_userdata( $other )->user_email, $this->mail[0]['to'] );
		$this->assertSame( array( 'handled' => array( $this->recipient, $other ) ), get_post_meta( $topic, '_bbp_mention_notification', true ) );
		wp_update_post( array( 'ID' => $topic, 'post_content' => 'Removed.' ) );
		wp_update_post( array( 'ID' => $topic, 'post_content' => '@mention-user @other-mention-user restored.' ) );
		bbp_shutdown();
		$this->assertCount( 1, $this->mail );
	}

	public function test_repair_does_not_queue_historical_mentions_on_a_later_edit() {
		remove_action( 'wp_after_insert_post', 'bbp_save_post_mentions', 20 );
		try {
			$topic = $this->topic();
		} finally {
			add_action( 'wp_after_insert_post', 'bbp_save_post_mentions', 20, 4 );
		}
		bbp_update_post_mentions( $topic );
		wp_update_post( array( 'ID' => $topic, 'post_content' => '@mention-user already mentioned.' ) );
		bbp_shutdown();
		$this->assertCount( 0, $this->mail );
	}

	public function test_edits_while_unpublished_notify_only_current_unhandled_mentions_on_approval() {
		$topic = $this->topic();
		bbp_shutdown();
		$other = $this->factory->user->create( array( 'role' => bbp_get_participant_role(), 'user_nicename' => 'other-mention-user' ) );
		update_user_meta( $other, '_bbp_mention_email', 'yes' );
		$removed = $this->factory->user->create( array( 'role' => bbp_get_participant_role(), 'user_nicename' => 'removed-mention-user' ) );
		update_user_meta( $removed, '_bbp_mention_email', 'yes' );
		wp_update_post( array( 'ID' => $topic, 'post_status' => bbp_get_pending_status_id(), 'post_content' => '@mention-user @removed-mention-user' ) );
		wp_update_post( array( 'ID' => $topic, 'post_content' => '@mention-user @other-mention-user' ) );
		bbp_shutdown();
		$this->assertCount( 1, $this->mail );
		wp_update_post( array( 'ID' => $topic, 'post_status' => bbp_get_public_status_id() ) );
		bbp_shutdown();
		$this->assertCount( 2, $this->mail );
		$this->assertSame( get_userdata( $other )->user_email, $this->mail[1]['to'] );
	}

	public function test_lost_shutdown_queue_does_not_resurrect_recipients_on_a_later_edit() {
		bbp_add_user_subscription( $this->recipient, $this->forum );
		$topic = $this->topic();
		bbp_notify_forum_subscribers( $topic, $this->forum, array(), $this->author );
		$this->assertCount( 1, $this->mail );
		bbpress()->mention_notification_queue = array();
		bbpress()->mention_subscription_recipients = array();
		$other = $this->factory->user->create( array( 'role' => bbp_get_participant_role(), 'user_nicename' => 'other-mention-user' ) );
		update_user_meta( $other, '_bbp_mention_email', 'yes' );
		wp_update_post( array( 'ID' => $topic, 'post_content' => '@mention-user @other-mention-user' ) );
		bbp_shutdown();
		$this->assertCount( 2, $this->mail );
		$this->assertSame( get_userdata( $other )->user_email, $this->mail[1]['to'] );
		wp_update_post( array( 'ID' => $topic, 'post_content' => 'Removed.' ) );
		wp_update_post( array( 'ID' => $topic, 'post_content' => '@mention-user restored.' ) );
		bbp_shutdown();
		$this->assertCount( 2, $this->mail );
	}

	public function test_failed_transport_is_not_retried_by_an_edit() {
		$topic = $this->topic();
		add_filter( 'pre_wp_mail', '__return_false', 20 );
		try {
			bbp_shutdown();
		} finally {
			remove_filter( 'pre_wp_mail', '__return_false', 20 );
		}
		$this->assertCount( 1, $this->mail );
		wp_update_post( array( 'ID' => $topic, 'post_content' => 'Removed.' ) );
		wp_update_post( array( 'ID' => $topic, 'post_content' => '@mention-user restored.' ) );
		bbp_shutdown();
		$this->assertCount( 1, $this->mail );
	}

	public function test_suspended_notifications_consume_mentions_introduced_by_edits() {
		$topic = $this->topic( array( 'post_content' => 'No mentions.' ) );
		add_filter( 'bbp_is_mention_notifications_active', '__return_false' );
		try {
			wp_update_post( array( 'ID' => $topic, 'post_content' => '@mention-user' ) );
		} finally {
			remove_filter( 'bbp_is_mention_notifications_active', '__return_false' );
		}
		wp_update_post( array( 'ID' => $topic, 'post_content' => 'Removed.' ) );
		wp_update_post( array( 'ID' => $topic, 'post_content' => '@mention-user restored.' ) );
		bbp_shutdown();
		$this->assertCount( 0, $this->mail );
		$this->assertSame( array( $this->recipient ), bbp_get_users_for_object( $topic, '_bbp_mention' ) );
	}

	public function test_subscription_alone_does_not_suppress_a_new_mention_on_an_edit() {
		$topic = $this->topic( array( 'post_content' => 'No mentions.' ) );
		bbp_add_user_subscription( $this->recipient, $topic );
		wp_update_post( array( 'ID' => $topic, 'post_content' => '@mention-user' ) );
		bbp_shutdown();
		$this->assertCount( 1, $this->mail );
		$this->assertStringContainsString( 'You were mentioned', $this->mail[0]['subject'] );
	}

	public function test_new_mentions_on_edits_recheck_preferences_and_access_at_delivery() {
		$topic = $this->topic( array( 'post_content' => 'No mentions.' ) );
		wp_update_post( array( 'ID' => $topic, 'post_content' => '@mention-user' ) );
		update_user_meta( $this->recipient, '_bbp_mention_email', 'no' );
		bbp_shutdown();
		$this->assertCount( 0, $this->mail );
		update_user_meta( $this->recipient, '_bbp_mention_email', 'yes' );
		wp_update_post( array( 'ID' => $topic, 'post_content' => 'Removed.' ) );
		wp_update_post( array( 'ID' => $topic, 'post_content' => '@mention-user restored.' ) );
		bbp_shutdown();
		$this->assertCount( 0, $this->mail );

		$topic = $this->topic( array( 'post_content' => 'No mentions.' ) );
		wp_update_post( array( 'ID' => $topic, 'post_content' => '@mention-user' ) );
		bbp_hide_forum( $this->forum );
		bbp_shutdown();
		$this->assertCount( 0, $this->mail );
		bbp_publicize_forum( $this->forum );
		wp_update_post( array( 'ID' => $topic, 'post_content' => 'Removed.' ) );
		wp_update_post( array( 'ID' => $topic, 'post_content' => '@mention-user restored.' ) );
		bbp_shutdown();
		$this->assertCount( 0, $this->mail );
	}

	public function test_failed_reservation_does_not_queue_email() {
		$topic = $this->topic( array( 'post_content' => 'No mentions.' ) );
		$callback = function( $sql ) {
			if ( 0 === strpos( $sql, 'UPDATE `' . bbp_db()->postmeta . '`' ) ) {
				$sql .= ' AND 1 = 0';
			}
			return $sql;
		};
		add_filter( 'query', $callback );
		try {
			wp_update_post( array( 'ID' => $topic, 'post_content' => '@mention-user' ) );
		} finally {
			remove_filter( 'query', $callback );
		}
		bbp_shutdown();
		$this->assertCount( 0, $this->mail );
		$this->assertSame( array( 'handled' => array() ), get_post_meta( $topic, '_bbp_mention_notification', true ) );
	}

	public function test_claims_use_the_oldest_metadata_row_even_if_initialization_was_duplicated() {
		$topic = $this->topic();
		add_post_meta( $topic, '_bbp_mention_notification', array( 'handled' => array() ) );
		$this->assertSame( array(), bbp_claim_mention_notification_recipients( $topic, array( $this->recipient ) ) );
		bbp_shutdown();
		$this->assertCount( 1, $this->mail );
	}

	public function test_interleaved_claims_cannot_reserve_the_same_recipient_twice() {
		$topic = $this->topic( array( 'post_content' => 'No mentions.' ) );
		$other = $this->factory->user->create( array( 'role' => bbp_get_participant_role() ) );
		$claimed = array();
		$interleaved = false;
		$callback = function( $sql ) use ( $topic, &$claimed, &$interleaved ) {
			if ( ! $interleaved && ( 0 === strpos( $sql, 'UPDATE `' . bbp_db()->postmeta . '`' ) ) ) {
				$interleaved = true;
				$claimed = bbp_claim_mention_notification_recipients( $topic, array( $this->recipient ) );
			}
			return $sql;
		};
		add_filter( 'query', $callback );
		try {
			$this->assertSame( array( $other ), bbp_claim_mention_notification_recipients( $topic, array( $this->recipient, $other ) ) );
		} finally {
			remove_filter( 'query', $callback );
		}
		$this->assertSame( array( $this->recipient ), $claimed );
		$this->assertSame( array( 'handled' => array( $this->recipient, $other ) ), get_post_meta( $topic, '_bbp_mention_notification', true ) );
	}

	public function test_opt_out_and_self_mentions_do_not_send_but_remain_indexed() {
		update_user_meta( $this->recipient, '_bbp_mention_email', 'no' );
		$topic = $this->topic();
		bbp_send_mention_notifications();
		$this->assertCount( 0, $this->mail );
		$this->assertSame( array( $this->recipient ), bbp_get_users_for_object( $topic, '_bbp_mention' ) );
		update_user_meta( $this->recipient, '_bbp_mention_email', 'yes' );
		$this->topic( array( 'post_author' => $this->recipient ) );
		bbp_send_mention_notifications();
		$this->assertCount( 0, $this->mail );
	}

	public function test_subscription_delivery_suppresses_a_duplicate_mention_email() {
		bbp_add_user_subscription( $this->recipient, $this->forum );
		$topic = $this->topic();
		bbp_notify_forum_subscribers( $topic, $this->forum, array(), $this->author );
		bbp_send_mention_notifications();
		$this->assertCount( 1, $this->mail );
		$this->assertStringNotContainsString( 'You were mentioned', $this->mail[0]['subject'] );
	}

	public function test_subscription_relationship_alone_does_not_suppress_email() {
		bbp_add_user_subscription( $this->recipient, $this->forum );
		$this->topic();
		bbp_send_mention_notifications();
		$this->assertCount( 1, $this->mail );
		$this->assertStringContainsString( 'You were mentioned', $this->mail[0]['subject'] );
	}

	public function test_access_is_checked_at_delivery_and_read_time() {
		$topic = $this->topic();
		bbp_hide_forum( $this->forum );
		bbp_send_mention_notifications();
		$this->assertCount( 0, $this->mail );
		$this->set_current_user( $this->recipient );
		$this->assertFalse( bbp_get_user_mentions( $this->recipient ) );
		$this->assertSame( array( $this->recipient ), bbp_get_users_for_object( $topic, '_bbp_mention' ) );
		bbp_publicize_forum( $this->forum );
		$this->assertTrue( bbp_get_user_mentions( $this->recipient ) );
	}

	public function test_passwords_missing_parents_and_inactive_users_fail_closed() {
		$this->topic( array( 'post_password' => 'secret' ) );
		$this->topic( array( 'post_parent' => 0 ) );
		$this->topic();
		bbp_set_user_role( $this->recipient, bbp_get_blocked_role() );
		bbp_send_mention_notifications();
		$this->assertCount( 0, $this->mail );
	}

	public function test_mentions_page_is_private_and_contains_matching_topics_and_replies() {
		$topic = $this->topic();
		$reply = $this->factory->reply->create( array( 'post_parent' => $topic, 'post_content' => '@mention-user', 'reply_meta' => array( 'topic_id' => $topic, 'forum_id' => $this->forum ) ) );
		$this->assertFalse( bbp_current_user_can_view_mentions( $this->recipient ) );
		$this->assertFalse( bbp_get_user_mentions( $this->recipient ) );
		$this->set_current_user( $this->author );
		$this->assertFalse( bbp_get_user_mentions( $this->recipient ) );
		$this->set_current_user( $this->recipient );
		$this->assertTrue( bbp_get_user_mentions( $this->recipient ) );
		$this->assertSame( array( $reply, $topic ), wp_list_pluck( bbpress()->search_query->posts, 'ID' ) );
	}

	public function test_hidden_reply_cannot_expose_an_otherwise_public_topic() {
		$topic = $this->topic( array( 'post_content' => 'No mention' ) );
		$this->factory->reply->create( array( 'post_parent' => $topic, 'post_status' => bbp_get_spam_status_id(), 'post_content' => '@mention-user', 'reply_meta' => array( 'topic_id' => $topic, 'forum_id' => $this->forum ) ) );
		$this->set_current_user( $this->recipient );
		$this->assertFalse( bbp_get_user_mentions( $this->recipient ) );
	}

	public function test_unchanged_content_preserves_stable_identity_after_a_slug_change() {
		$topic = $this->topic();
		wp_update_user( array( 'ID' => $this->recipient, 'user_nicename' => 'changed-name' ) );
		wp_update_post( array( 'ID' => $topic, 'post_title' => 'New title' ) );
		$this->assertSame( array( $this->recipient ), bbp_get_users_for_object( $topic, '_bbp_mention' ) );
	}

	public function test_preferences_require_both_nonce_and_authorization() {
		$this->set_current_user( $this->recipient );
		$this->assertTrue( bbp_is_user_mention_email_enabled( $this->recipient ) );
		bbp_save_mention_email_preference( $this->recipient );
		$this->assertTrue( bbp_is_user_mention_email_enabled( $this->recipient ) );
		$_POST['_bbp_mention_nonce'] = wp_create_nonce( 'bbp-mention-preferences-' . $this->recipient );
		bbp_save_mention_email_preference( $this->recipient );
		$this->assertFalse( bbp_is_user_mention_email_enabled( $this->recipient ) );
		$this->set_current_user( $this->author );
		$_POST['bbp_mention_email'] = '1';
		$_POST['_bbp_mention_nonce'] = wp_create_nonce( 'bbp-mention-preferences-' . $this->recipient );
		bbp_save_mention_email_preference( $this->recipient );
		$this->assertFalse( bbp_is_user_mention_email_enabled( $this->recipient ) );
	}

	public function test_plain_and_pretty_profile_routes_and_pagination() {
		bbPress::add_rewrite_tags();
		bbPress::add_rewrite_rules();
		$this->set_current_user( $this->recipient );
		WP_UnitTestCase::go_to( bbp_get_user_mentions_url( $this->recipient ) );
		$this->assertTrue( bbp_is_single_user_mentions() );
		$this->assertSame( $this->recipient, bbp_get_displayed_user_id() );

		$this->set_permalink_structure( '/%postname%/' );
		$url = bbp_get_user_mentions_url( $this->recipient );
		WP_UnitTestCase::go_to( $url . 'page/2/' );
		$this->assertTrue( bbp_is_single_user_mentions() );
		$this->assertSame( 2, (int) get_query_var( 'paged' ) );
		$this->assertSame( $url . 'page/%#%/', bbp_get_search_pagination_base() );
		$this->set_current_user( $this->author );
		WP_UnitTestCase::go_to( $url );
		$this->assertTrue( bbp_get_wp_query()->bbp_is_404 );
		$this->assertFalse( bbp_is_single_user_mentions() );
	}

	public function test_profile_preference_is_not_duplicated_by_wordpress_profile_hooks() {
		require_once BBP_PLUGIN_DIR . 'includes/admin/users.php';
		$this->set_current_user( $this->recipient );
		bbpress()->displayed_user = get_userdata( $this->recipient );
		ob_start();
		do_action( 'bbp_user_edit_after_account' );
		do_action( 'bbp_user_edit_notifications' );
		do_action( 'show_user_profile', bbpress()->displayed_user );
		do_action( 'edit_user_profile', bbpress()->displayed_user );
		$html = ob_get_clean();
		$this->assertSame( 1, substr_count( $html, 'name="bbp_mention_email"' ) );
		$this->assertSame( 1, substr_count( $html, 'name="_bbp_mention_nonce"' ) );

		set_current_screen( 'profile' );
		try {
			ob_start();
			do_action( 'show_user_profile', bbpress()->displayed_user );
			$html = ob_get_clean();
			$this->assertSame( 1, substr_count( $html, 'name="bbp_mention_email"' ) );
		} finally {
			set_current_screen( 'front' );
		}
	}

	public function test_mention_access_check_does_not_reject_another_selected_profile_view() {
		bbPress::add_rewrite_tags();
		bbPress::add_rewrite_rules();
		$this->set_current_user( $this->author );
		$url = add_query_arg( bbp_get_user_mentions_rewrite_id(), '1', bbp_get_user_topics_created_url( $this->recipient ) );
		WP_UnitTestCase::go_to( $url );

		$this->assertTrue( bbp_is_single_user_topics() );
		$this->assertFalse( bbp_is_single_user_mentions() );
		$this->assertFalse( bbp_get_wp_query()->bbp_is_404 );
	}

	public function test_native_mention_email_preference_can_be_filtered() {
		delete_user_meta( $this->recipient, '_bbp_mention_email' );
		$received = array();
		$callback = function( $enabled, $user_id, $setting ) use ( &$received ) {
			$received = array( $enabled, $user_id, $setting );
			return true;
		};
		add_filter( 'bbp_is_user_mention_email_enabled', $callback, 10, 3 );
		try {
			$this->assertTrue( bbp_is_user_mention_email_enabled( $this->recipient ) );
			$this->assertSame( array( false, $this->recipient, false ), $received );
		} finally {
			remove_filter( 'bbp_is_user_mention_email_enabled', $callback, 10 );
		}
	}

	public function test_profile_form_groups_mention_preference_in_notifications_fieldset() {
		$this->set_current_user( $this->recipient );
		bbpress()->displayed_user = get_userdata( $this->recipient );

		// The PHPUnit bootstrap leaves WP_USE_THEMES off; load located templates.
		$template_loader = function( $located, $template_name, $template_names, $template_locations, $load ) {
			if ( $load && $located ) {
				load_template( $located, false );
			}
		};
		add_action( 'bbp_locate_template', $template_loader, 10, 5 );

		try {
			ob_start();
			bbp_get_template_part( 'form', 'user-edit' );
			$html = ob_get_clean();
		} finally {
			remove_action( 'bbp_locate_template', $template_loader, 10 );
		}

		$document = new DOMDocument();
		$document->loadHTML( $html );
		$xpath = new DOMXPath( $document );

		$this->assertSame( 1, $xpath->query( '//form[@id="bbp-your-profile"]/fieldset[@id="bbp-user-notifications"][legend="Notifications"]' )->length );
		$this->assertSame( 1, $xpath->query( '//fieldset[@id="bbp-user-notifications"]//input[@name="bbp_mention_email"]' )->length );
		$this->assertSame( 0, $xpath->query( '//fieldset[legend="Account"]//input[@name="bbp_mention_email"]' )->length );
		$this->assertSame( 1, $xpath->query( '//input[@name="bbp_mention_email"]' )->length );
		$this->assertSame( 1, $xpath->query( '//fieldset[@id="bbp-user-notifications"]//input[@name="_bbp_mention_nonce"]' )->length );
	}

	public function test_notifications_fieldset_is_hidden_without_email_edit_access() {
		$this->set_current_user( $this->author );
		bbpress()->displayed_user = get_userdata( $this->recipient );
		ob_start();
		include BBP_PLUGIN_DIR . 'templates/default/bbpress/form-user-notifications.php';
		$html = ob_get_clean();

		$this->assertSame( '', trim( $html ) );
	}

	public function test_profile_pagination_counts_only_accessible_topics_once() {
		$this->topic();
		$this->topic();
		$this->topic( array( 'post_status' => bbp_get_pending_status_id() ) );
		$this->topic( array( 'post_password' => 'secret' ) );
		$this->set_current_user( $this->recipient );
		update_option( '_bbp_replies_per_page', 1 );
		$this->assertTrue( bbp_get_user_mentions( $this->recipient ) );
		$this->assertSame( 2, (int) bbpress()->search_query->found_posts );
		$this->assertSame( 2, (int) bbpress()->search_query->max_num_pages );
		$this->assertCount( 1, bbpress()->search_query->posts );
	}

	public function test_approval_uses_the_latest_held_content() {
		$topic = $this->topic( array( 'post_status' => bbp_get_pending_status_id(), 'post_content' => 'No mention' ) );
		wp_update_post( array( 'ID' => $topic, 'post_content' => '@mention-user' ) );
		wp_update_post( array( 'ID' => $topic, 'post_status' => bbp_get_public_status_id() ) );
		bbp_send_mention_notifications();
		$this->assertCount( 1, $this->mail );
	}

	public static function relationship_strategies() {
		return array( array( 'BBP_User_Engagements_Meta' ), array( 'BBP_User_Engagements_Term' ), array( 'BBP_User_Engagements_User' ) );
	}

	/** @dataProvider relationship_strategies */
	public function test_history_paginates_matching_posts_in_sql_with_stable_ordering( $class ) {
		$old_strategy = bbpress()->engagements;
		bbpress()->engagements = new $class();
		$posts = array();
		for ( $i = 0; $i < 12; ++$i ) {
			$topic = $this->topic( array( 'post_date' => '2020-01-01 00:00:00' ) );
			$posts[] = $topic;
			for ( $reply = 0; $reply < 3; ++$reply ) {
				$posts[] = $this->factory->reply->create( array( 'post_date' => '2020-01-01 00:00:00', 'post_parent' => $topic, 'post_content' => '@mention-user', 'reply_meta' => array( 'topic_id' => $topic, 'forum_id' => $this->forum ) ) );
			}
		}
		rsort( $posts );
		$this->set_current_user( $this->recipient );
		update_option( '_bbp_replies_per_page', 5 );
		$old_page = get_query_var( 'paged' );
		$loaded = array();
		$record = function( $posts, $query ) use ( &$loaded ) {
			$loaded[] = count( $posts );
			return $posts;
		};
		add_filter( 'posts_results', $record, 10, 2 );
		try {
			foreach ( array( 1, 2, 10 ) as $page ) {
				set_query_var( 'paged', $page );
				$this->assertTrue( bbp_get_user_mentions( $this->recipient ) );
				$query = bbpress()->search_query;
				$this->assertSame( array_slice( $posts, ( $page - 1 ) * 5, 5 ), wp_list_pluck( $query->posts, 'ID' ) );
				$this->assertSame( 48, (int) $query->found_posts );
				$this->assertSame( 10, (int) $query->max_num_pages );
				$this->assertEmpty( $query->get( 'post__in' ) );
				$this->assertStringContainsString( 'LIMIT ' . ( ( $page - 1 ) * 5 ) . ', 5', $query->request );
			}
			$this->assertLessThanOrEqual( 5, max( $loaded ) );
		} finally {
			remove_filter( 'posts_results', $record, 10 );
			set_query_var( 'paged', $old_page );
			bbpress()->engagements = $old_strategy;
		}
	}

	public function test_legacy_user_strategy_keeps_mentions_in_post_meta_without_user_scans() {
		$old = bbpress()->engagements;
		bbpress()->engagements = new BBP_User_Engagements_User();
		$queries = array();
		$collect = static function( $query ) use ( &$queries ) {
			$queries[] = $query;
			return $query;
		};
		add_filter( 'query', $collect );
		try {
			$topic = $this->topic();
			$this->assertSame( array( (string) $this->recipient ), get_post_meta( $topic, '_bbp_mention' ) );
			foreach ( $queries as $query ) {
				if ( false !== strpos( $query, 'FIND_IN_SET' ) ) {
					$this->assertStringNotContainsString( '_bbp_mentions', $query );
				}
			}
			$this->assertSame( bbpress()->engagements, bbp_user_engagements_interface( '_bbp_favorite' ) );
			$this->assertSame( bbpress()->engagements, bbp_user_engagements_interface( '_bbp_subscription' ) );
		} finally {
			remove_filter( 'query', $collect );
			bbpress()->engagements = $old;
		}
	}

	public function test_custom_mention_relationship_adapters_can_override_the_default() {
		$old = bbpress()->engagements;
		$custom = new class() extends BBP_User_Engagements_User {};
		bbpress()->engagements = $custom;
		$this->assertSame( $custom, bbp_user_engagements_interface( '_bbp_mention' ) );
		bbpress()->engagements = new BBP_User_Engagements_User();
		$override = static function( $interface, $key ) use ( $custom ) {
			return ( '_bbp_mention' === $key ) ? $custom : $interface;
		};
		add_filter( 'bbp_user_engagements_interface', $override, 10, 2 );
		try {
			$this->assertSame( $custom, bbp_user_engagements_interface( '_bbp_mention' ) );
		} finally {
			remove_filter( 'bbp_user_engagements_interface', $override, 10 );
			bbpress()->engagements = $old;
		}
	}

	public function test_history_counts_exclude_restricted_and_password_protected_ancestry() {
		$visible = $this->topic();
		$hidden = $this->factory->forum->create( array( 'post_status' => bbp_get_hidden_status_id() ) );
		$this->topic( array( 'post_parent' => $hidden ) );
		$protected = $this->factory->forum->create( array( 'post_password' => 'secret' ) );
		$child = $this->factory->forum->create( array( 'post_parent' => $protected ) );
		$this->topic( array( 'post_parent' => $child ) );
		$topic = $this->topic( array( 'post_content' => 'No mention in this topic.' ) );
		$this->factory->reply->create( array( 'post_parent' => $topic, 'post_password' => 'secret', 'post_content' => '@mention-user', 'reply_meta' => array( 'topic_id' => $topic, 'forum_id' => $this->forum ) ) );
		$this->topic( array( 'post_parent' => 999999 ) );
		$this->set_current_user( $this->recipient );

		$this->assertTrue( bbp_get_user_mentions( $this->recipient ) );
		$this->assertSame( array( $visible ), wp_list_pluck( bbpress()->search_query->posts, 'ID' ) );
		$this->assertSame( 1, (int) bbpress()->search_query->found_posts );
	}

	public function test_history_viewer_cannot_count_mentions_in_forums_hidden_from_the_owner() {
		$visible = $this->topic();
		$hidden = $this->factory->forum->create( array( 'post_status' => bbp_get_hidden_status_id() ) );
		$this->topic( array( 'post_parent' => $hidden ) );
		$admin = $this->factory->user->create( array( 'role' => 'administrator' ) );
		bbp_set_user_role( $admin, bbp_get_keymaster_role() );
		if ( is_multisite() ) {
			grant_super_admin( $admin );
		}
		$this->set_current_user( $admin );
		try {
			$this->assertTrue( bbp_current_user_can_view_mentions( $this->recipient ) );
			$this->assertTrue( bbp_get_user_mentions( $this->recipient ) );
			$this->assertSame( array( $visible ), wp_list_pluck( bbpress()->search_query->posts, 'ID' ) );
			$this->assertSame( 1, (int) bbpress()->search_query->found_posts );
		} finally {
			if ( is_multisite() ) {
				revoke_super_admin( $admin );
			}
		}
	}

	public function test_history_honors_relationship_query_filters_without_affecting_other_queries() {
		$visible = $this->topic();
		$excluded = $this->topic();
		$this->set_current_user( $this->recipient );
		$filter = function( $args, $user_id, $context ) use ( $excluded ) {
			if ( 'mentions' === $context ) {
				$args['post__not_in'] = array( $excluded );
			}
			return $args;
		};
		add_filter( 'bbp_get_user_object_query', $filter, 10, 3 );
		try {
			$this->assertTrue( bbp_get_user_mentions( $this->recipient ) );
			$this->assertSame( array( $visible ), wp_list_pluck( bbpress()->search_query->posts, 'ID' ) );
			$this->assertSame( 1, (int) bbpress()->search_query->found_posts );
		} finally {
			remove_filter( 'bbp_get_user_object_query', $filter, 10 );
		}
		$post = $this->factory->post->create();
		$query = new WP_Query( array( 'p' => $post ) );
		$this->assertSame( array( $post ), wp_list_pluck( $query->posts, 'ID' ) );
		$this->assertStringNotContainsString( 'bbp_mention_parent', $query->request );
	}

	public function test_history_counts_follow_mention_edits_and_post_status_changes() {
		$topic = $this->topic( array( 'post_content' => 'No mention.' ) );
		$reply = $this->factory->reply->create( array( 'post_parent' => $topic, 'post_content' => '@mention-user @mention-user', 'reply_meta' => array( 'topic_id' => $topic, 'forum_id' => $this->forum ) ) );
		$this->set_current_user( $this->recipient );
		$this->assertTrue( bbp_get_user_mentions( $this->recipient ) );
		$this->assertSame( array( $reply ), wp_list_pluck( bbpress()->search_query->posts, 'ID' ) );
		$this->assertSame( 1, (int) bbpress()->search_query->found_posts );

		wp_update_post( array( 'ID' => $topic, 'post_status' => bbp_get_pending_status_id() ) );
		$this->assertFalse( bbp_get_user_mentions( $this->recipient ) );
		wp_update_post( array( 'ID' => $topic, 'post_status' => bbp_get_closed_status_id() ) );
		$this->assertTrue( bbp_get_user_mentions( $this->recipient ) );
		wp_update_post( array( 'ID' => $reply, 'post_content' => 'Mention removed.' ) );
		$this->assertFalse( bbp_get_user_mentions( $this->recipient ) );
		$this->assertSame( 0, (int) bbpress()->search_query->found_posts );
	}

	public function test_empty_public_reply_statuses_do_not_hide_matching_topics() {
		$topic = $this->topic();
		$this->factory->reply->create( array( 'post_parent' => $topic, 'post_content' => '@mention-user', 'reply_meta' => array( 'topic_id' => $topic, 'forum_id' => $this->forum ) ) );
		$this->set_current_user( $this->recipient );
		add_filter( 'bbp_get_public_reply_statuses', '__return_empty_array' );
		try {
			$this->assertTrue( bbp_get_user_mentions( $this->recipient ) );
			$this->assertSame( array( $topic ), wp_list_pluck( bbpress()->search_query->posts, 'ID' ) );
		} finally {
			remove_filter( 'bbp_get_public_reply_statuses', '__return_empty_array' );
		}
	}

	public function test_history_reuses_search_templates_with_direct_post_links() {
		$topic = $this->topic();
		$reply = $this->factory->reply->create( array( 'post_parent' => $topic, 'post_content' => '@mention-user', 'reply_meta' => array( 'topic_id' => $topic, 'forum_id' => $this->forum ) ) );
		$this->set_current_user( $this->recipient );
		bbPress::add_rewrite_tags();
		bbPress::add_rewrite_rules();
		WP_UnitTestCase::go_to( bbp_get_user_mentions_url( $this->recipient ) );
		$template_loader = function( $located, $template_name, $template_names, $template_locations, $load ) {
			if ( $load && $located ) {
				load_template( $located, false );
			}
		};
		add_action( 'bbp_locate_template', $template_loader, 10, 5 );
		try {
			ob_start();
			bbp_get_template_part( 'user', 'mentions' );
			$html = ob_get_clean();
		} finally {
			remove_action( 'bbp_locate_template', $template_loader, 10 );
		}
		$this->assertStringContainsString( 'id="bbp-search-results"', $html );
		$this->assertStringContainsString( 'id="post-' . $topic . '"', $html );
		$this->assertStringContainsString( 'id="post-' . $reply . '"', $html );
		$this->assertStringContainsString( esc_url( bbp_get_reply_url( $reply ) ), $html );
		$this->assertStringNotContainsString( 'Search Results', $html );
	}

	public function test_password_protected_ancestor_is_not_disclosed() {
		$topic = $this->topic();
		$parent = $this->factory->forum->create( array( 'post_password' => 'secret' ) );
		wp_update_post( array( 'ID' => $this->forum, 'post_parent' => $parent ) );
		$this->assertFalse( bbp_user_can_read_mention( $topic, $this->recipient ) );
		bbp_send_mention_notifications();
		$this->assertCount( 0, $this->mail );
	}

	public function test_direct_wordpress_insert_and_unrelated_post_types() {
		wp_insert_post( array( 'post_type' => 'post', 'post_status' => 'publish', 'post_content' => '@mention-user' ) );
		bbp_send_mention_notifications();
		$this->assertCount( 0, $this->mail );
		wp_insert_post( array( 'post_type' => bbp_get_topic_post_type(), 'post_parent' => $this->forum, 'post_author' => $this->author, 'post_status' => 'publish', 'post_content' => '@mention-user' ) );
		bbp_send_mention_notifications();
		$this->assertCount( 1, $this->mail );
	}

	public function test_mentions_work_with_each_existing_relationship_strategy() {
		$old = bbpress()->engagements;
		$this->set_current_user( $this->recipient );
		try {
			foreach ( array( 'BBP_User_Engagements_Meta', 'BBP_User_Engagements_Term', 'BBP_User_Engagements_User' ) as $class ) {
				bbpress()->engagements = new $class();
				$topic = $this->topic();
				$this->assertContains( $this->recipient, bbp_get_users_for_object( $topic, '_bbp_mention' ), $class );
				$this->assertTrue( bbp_get_user_mentions( $this->recipient ), $class );
				$this->assertContains( $topic, wp_list_pluck( bbpress()->search_query->posts, 'ID' ), $class );
				wp_delete_post( $topic, true );
				$this->assertSame( array(), bbp_get_users_for_object( $topic, '_bbp_mention' ), $class );
			}
		} finally {
			bbpress()->engagements = $old;
		}
	}

	public function test_converter_indexes_without_queuing_retroactive_email() {
		require_once bbpress()->includes_dir . 'admin/classes/class-bbp-converter-base.php';
		$converter = new class() extends BBP_Converter_Base {
			public function __construct() {}
			protected function authenticate_pass( $password, $hash ) { return false; }
			protected function info() { return array(); }
			public function insert_for_test( $data ) {
				return $this->insert_post( $data );
			}
		};
		$topic = $converter->insert_for_test( array(
			'post_type' => bbp_get_topic_post_type(),
			'post_status' => bbp_get_pending_status_id(),
			'post_parent' => $this->forum,
			'post_content' => '@mention-user',
		) );
		$this->assertSame( array( $this->recipient ), bbp_get_users_for_object( $topic, '_bbp_mention' ) );
		$this->assertTrue( bbp_is_mention_notifications_active() );
		wp_update_post( array( 'ID' => $topic, 'post_status' => bbp_get_public_status_id() ) );
		bbp_send_mention_notifications();
		$this->assertCount( 0, $this->mail );
	}

	/**
	 * @group ms-required
	 */
	public function test_multisite_queue_preserves_origin_sites_with_a_global_preference() {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'Multisite only.' );
		}
		$origin = get_current_blog_id();
		update_user_meta( $this->recipient, '_bbp_mention_email', 'yes' );
		$topic = $this->topic( array( 'post_content' => 'No mentions.' ) );
		wp_update_post( array( 'ID' => $topic, 'post_content' => '@mention-user' ) );
		$origin_url = bbp_get_topic_permalink( $topic );
		$blog = $this->factory->blog->create();
		switch_to_blog( $blog );
		try {
			add_user_to_blog( $blog, $this->recipient, 'subscriber' );
			bbp_set_user_role( $this->recipient, bbp_get_participant_role() );
			$this->assertTrue( bbp_is_user_mention_email_enabled( $this->recipient ) );
			$forum = $this->factory->forum->create();
			$topic = $this->topic( array( 'post_parent' => $forum, 'post_content' => 'No mentions.' ) );
			wp_update_post( array( 'ID' => $topic, 'post_content' => '@mention-user' ) );
			$url = bbp_get_topic_permalink( $topic );
		} finally {
			restore_current_blog();
		}
		bbp_send_mention_notifications();
		$this->assertSame( $origin, get_current_blog_id() );
		$this->assertCount( 2, $this->mail );
		$this->assertStringContainsString( $origin_url, $this->mail[0]['message'] );
		$this->assertStringContainsString( $url, $this->mail[1]['message'] );
		$this->assertTrue( bbp_is_user_mention_email_enabled( $this->recipient ) );
	}

	public function test_site_specific_options_cannot_override_the_global_preference() {
		delete_user_meta( $this->recipient, '_bbp_mention_email' );
		update_user_option( $this->recipient, '_bbp_mention_email', 'yes' );
		$this->assertFalse( bbp_is_user_mention_email_enabled( $this->recipient ) );

		update_user_option( $this->recipient, '_bbp_mention_email', 'no' );
		update_user_meta( $this->recipient, '_bbp_mention_email', 'yes' );
		$this->assertTrue( bbp_is_user_mention_email_enabled( $this->recipient ) );

		update_user_option( $this->recipient, '_bbp_mention_email', 'yes' );
		update_user_meta( $this->recipient, '_bbp_mention_email', 'no' );
		$this->assertFalse( bbp_is_user_mention_email_enabled( $this->recipient ) );
	}

	/**
	 * @group ms-required
	 */
	public function test_profile_preference_saved_on_either_site_applies_to_both() {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'Multisite only.' );
		}

		$this->set_current_user( $this->recipient );
		$_POST['_bbp_mention_nonce'] = wp_create_nonce( 'bbp-mention-preferences-' . $this->recipient );
		bbp_save_mention_email_preference( $this->recipient );
		$this->assertSame( 'no', get_user_meta( $this->recipient, '_bbp_mention_email', true ) );
		$this->assertFalse( bbp_is_user_mention_email_enabled( $this->recipient ) );

		$blog = $this->factory->blog->create();
		switch_to_blog( $blog );
		try {
			add_user_to_blog( $blog, $this->recipient, 'subscriber' );
			bbp_set_user_role( $this->recipient, bbp_get_participant_role() );
			$this->assertFalse( bbp_is_user_mention_email_enabled( $this->recipient ) );

			$_POST['bbp_mention_email'] = '1';
			$_POST['_bbp_mention_nonce'] = wp_create_nonce( 'bbp-mention-preferences-' . $this->recipient );
			bbp_save_mention_email_preference( $this->recipient );
			$this->assertTrue( bbp_is_user_mention_email_enabled( $this->recipient ) );
		} finally {
			restore_current_blog();
		}

		$this->assertSame( 'yes', get_user_meta( $this->recipient, '_bbp_mention_email', true ) );
		$this->assertTrue( bbp_is_user_mention_email_enabled( $this->recipient ) );
	}

	/**
	 * @group ms-required
	 */
	public function test_global_opt_out_on_another_site_suppresses_queued_emails() {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'Multisite only.' );
		}

		$origin = get_current_blog_id();
		$this->topic();
		$blog = $this->factory->blog->create();
		switch_to_blog( $blog );
		try {
			add_user_to_blog( $blog, $this->recipient, 'subscriber' );
			bbp_set_user_role( $this->recipient, bbp_get_participant_role() );
			$forum = $this->factory->forum->create();
			$this->topic( array( 'post_parent' => $forum ) );

			$this->set_current_user( $this->recipient );
			$_POST['_bbp_mention_nonce'] = wp_create_nonce( 'bbp-mention-preferences-' . $this->recipient );
			bbp_save_mention_email_preference( $this->recipient );
			$this->assertFalse( bbp_is_user_mention_email_enabled( $this->recipient ) );
		} finally {
			restore_current_blog();
		}

		$this->assertFalse( bbp_is_user_mention_email_enabled( $this->recipient ) );
		bbp_send_mention_notifications();
		$this->assertCount( 0, $this->mail );
		$this->assertSame( $origin, get_current_blog_id() );
	}

	public function test_reply_email_rechecks_the_reply_status_filter_at_shutdown() {
		$topic = $this->topic( array( 'post_content' => 'No mentions.' ) );
		$reply = $this->factory->reply->create( array(
			'post_parent'  => $topic,
			'post_author'  => $this->author,
			'post_content' => '@mention-user',
			'reply_meta'   => array( 'topic_id' => $topic, 'forum_id' => $this->forum ),
		) );
		$this->assertTrue( bbp_user_can_read_mention( $reply, $this->recipient ) );
		add_filter( 'bbp_get_public_reply_statuses', '__return_empty_array' );
		try {
			$this->assertFalse( bbp_user_can_read_mention( $reply, $this->recipient ) );
			$this->assertTrue( bbp_user_can_read_mention( $topic, $this->recipient ) );
			bbp_shutdown();
			$this->assertCount( 0, $this->mail );
		} finally {
			remove_filter( 'bbp_get_public_reply_statuses', '__return_empty_array' );
		}
	}

	public function test_custom_public_reply_status_can_notify_an_authorized_reader() {
		$topic  = $this->topic( array( 'post_content' => 'No mentions.' ) );
		$filter = function() { return array( 'visible-reply' ); };
		bbp_set_user_role( $this->recipient, bbp_get_moderator_role() );
		register_post_status( 'visible-reply', array( 'public' => true ) );
		add_filter( 'bbp_get_public_reply_statuses', $filter );
		try {
			$reply = $this->factory->reply->create( array(
				'post_parent'  => $topic,
				'post_author'  => $this->author,
				'post_status'  => 'visible-reply',
				'post_content' => '@mention-user',
				'reply_meta'   => array( 'topic_id' => $topic, 'forum_id' => $this->forum ),
			) );
			$this->assertTrue( bbp_user_can_read_mention( $reply, $this->recipient ) );
			bbp_shutdown();
			$this->assertCount( 1, $this->mail );
			$this->assertSame( get_userdata( $this->recipient )->user_email, $this->mail[0]['to'] );
		} finally {
			remove_filter( 'bbp_get_public_reply_statuses', $filter );
			unset( $GLOBALS['wp_post_statuses']['visible-reply'] );
		}
	}

}
