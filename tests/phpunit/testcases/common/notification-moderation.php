<?php

/**
 * Tests for subscription notifications after moderation.
 *
 * @group common
 * @group notifications
 */
class BBP_Tests_Common_Notification_Moderation extends BBP_UnitTestCase {

	private $mail = array();

	public function setUp(): void {
		parent::setUp();

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

	private function create_forum_subscription() {
		$subscriber_id = $this->factory->user->create();
		$author_id     = $this->factory->user->create();
		$forum_id      = $this->factory->forum->create();

		bbp_set_user_role( $subscriber_id, bbp_get_participant_role() );
		bbp_set_user_role( $author_id, bbp_get_keymaster_role() );
		bbp_add_user_forum_subscription( $subscriber_id, $forum_id );

		return compact( 'subscriber_id', 'author_id', 'forum_id' );
	}

	private function create_topic_subscription() {
		$content  = $this->create_forum_subscription();
		$topic_id = $this->factory->topic->create(
			array(
				'post_author' => $content['author_id'],
				'post_parent' => $content['forum_id'],
				'topic_meta'  => array( 'forum_id' => $content['forum_id'] ),
			)
		);

		bbp_add_user_topic_subscription( $content['subscriber_id'], $topic_id );
		$content['topic_id'] = $topic_id;

		return $content;
	}

	private function create_topic( $content, $status ) {
		return $this->factory->topic->create(
			array(
				'post_author' => $content['author_id'],
				'post_parent' => $content['forum_id'],
				'post_status' => $status,
				'topic_meta'  => array( 'forum_id' => $content['forum_id'] ),
			)
		);
	}

	private function create_reply( $content, $status ) {
		return $this->factory->reply->create(
			array(
				'post_author' => $content['author_id'],
				'post_parent' => $content['topic_id'],
				'post_status' => $status,
				'reply_meta'  => array(
					'forum_id' => $content['forum_id'],
					'topic_id' => $content['topic_id'],
				),
			)
		);
	}

	private function fire_new_topic( $topic_id, $content ) {
		do_action( 'bbp_new_topic', $topic_id, $content['forum_id'], array(), $content['author_id'] );
	}

	private function fire_new_reply( $reply_id, $content ) {
		do_action( 'bbp_new_reply', $reply_id, $content['topic_id'], $content['forum_id'], array(), $content['author_id'], false, 0 );
	}

	private function assert_mail_recipient( $content ) {
		$headers          = implode( "\n", (array) $this->mail[0]['headers'] );
		$subscriber_email = get_userdata( $content['subscriber_id'] )->user_email;
		$author_email     = get_userdata( $content['author_id'] )->user_email;

		$this->assertStringContainsString( $subscriber_email, $headers );
		$this->assertStringNotContainsString( $author_email, $headers );
	}

	/**
	 * @covers ::bbp_defer_subscription_notification
	 * @covers ::bbp_notify_forum_subscribers_on_topic_publication
	 */
	public function test_pending_topic_notifies_once_when_approved() {
		$content   = $this->create_forum_subscription();
		$topic_id  = $this->create_topic( $content, bbp_get_pending_status_id() );
		$published = array();
		$callback  = function( $published_topic_id, $forum_id, $anonymous_data, $author_id ) use ( &$published ) {
			$published = compact( 'published_topic_id', 'forum_id', 'anonymous_data', 'author_id' );
		};

		add_action( 'bbp_deferred_topic_published', $callback, 10, 4 );
		$this->fire_new_topic( $topic_id, $content );

		$this->assertCount( 0, $this->mail );
		$this->assertTrue( metadata_exists( 'post', $topic_id, '_bbp_subscription_notification_pending' ) );
		$this->assertSame( $topic_id, bbp_approve_topic( $topic_id ) );
		remove_action( 'bbp_deferred_topic_published', $callback, 10 );

		$this->assertCount( 1, $this->mail );
		$this->assertFalse( metadata_exists( 'post', $topic_id, '_bbp_subscription_notification_pending' ) );
		$this->assertSame( $topic_id, $published['published_topic_id'] );
		$this->assertSame( $content['forum_id'], $published['forum_id'] );
		$this->assertSame( array(), $published['anonymous_data'] );
		$this->assertSame( $content['author_id'], $published['author_id'] );
		$this->assert_mail_recipient( $content );

		$this->assertSame( $topic_id, bbp_unapprove_topic( $topic_id ) );
		$this->assertSame( $topic_id, bbp_approve_topic( $topic_id ) );
		$this->assertCount( 1, $this->mail );
	}

	/**
	 * @ticket BBP2989
	 *
	 * @covers ::bbp_defer_subscription_notification
	 * @covers ::bbp_notify_forum_subscribers_on_topic_publication
	 * @covers ::bbp_notify_forum_subscribers
	 */
	public function test_pending_topic_notifies_term_subscriber_when_approved() {
		$content  = $this->create_forum_subscription();
		$topic_id = $this->create_topic( $content, bbp_get_pending_status_id() );
		$term_id  = $this->factory->term->create( array( 'taxonomy' => bbp_get_topic_tag_tax_id() ) );

		wp_set_object_terms( $topic_id, $term_id, bbp_get_topic_tag_tax_id() );
		bbp_add_user_subscription( $content['subscriber_id'], $term_id, 'term' );
		update_option( '_bbp_enable_subscriptions', array( 'term:' . bbp_get_topic_tag_tax_id() ) );

		$this->fire_new_topic( $topic_id, $content );

		$this->assertCount( 0, $this->mail );
		$this->assertTrue( metadata_exists( 'post', $topic_id, '_bbp_subscription_notification_pending' ) );
		$this->assertSame( $topic_id, bbp_approve_topic( $topic_id ) );
		$this->assertCount( 1, $this->mail );
		$this->assertFalse( metadata_exists( 'post', $topic_id, '_bbp_subscription_notification_pending' ) );
		$this->assert_mail_recipient( $content );
	}

	/**
	 * @covers ::bbp_defer_subscription_notification
	 * @covers ::bbp_notify_forum_subscribers_on_topic_publication
	 */
	public function test_pending_topic_trashed_and_restored_notifies_once_when_approved() {
		$content  = $this->create_forum_subscription();
		$topic_id = $this->create_topic( $content, bbp_get_pending_status_id() );

		$this->fire_new_topic( $topic_id, $content );
		$this->assertNotFalse( wp_trash_post( $topic_id ) );
		$this->assertNotFalse( wp_untrash_post( $topic_id ) );

		$this->assertSame( bbp_get_pending_status_id(), get_post_status( $topic_id ) );
		$this->assertCount( 0, $this->mail );
		$this->assertTrue( metadata_exists( 'post', $topic_id, '_bbp_subscription_notification_pending' ) );
		$this->assertSame( $topic_id, bbp_approve_topic( $topic_id ) );
		$this->assertCount( 1, $this->mail );
		$this->assertFalse( metadata_exists( 'post', $topic_id, '_bbp_subscription_notification_pending' ) );
	}

	/**
	 * @covers ::bbp_defer_subscription_notification
	 * @covers ::bbp_notify_forum_subscribers_on_topic_publication
	 */
	public function test_spam_topic_notifies_once_when_unspammed() {
		$content  = $this->create_forum_subscription();
		$topic_id = $this->create_topic( $content, bbp_get_spam_status_id() );

		update_post_meta( $topic_id, '_bbp_spam_meta_status', bbp_get_public_status_id() );
		$this->fire_new_topic( $topic_id, $content );

		$this->assertCount( 0, $this->mail );
		$this->assertTrue( metadata_exists( 'post', $topic_id, '_bbp_subscription_notification_pending' ) );
		$this->assertSame( $topic_id, bbp_unspam_topic( $topic_id ) );
		$this->assertCount( 1, $this->mail );
		$this->assertFalse( metadata_exists( 'post', $topic_id, '_bbp_subscription_notification_pending' ) );

		$this->assertSame( $topic_id, bbp_spam_topic( $topic_id ) );
		$this->assertSame( $topic_id, bbp_unspam_topic( $topic_id ) );
		$this->assertCount( 1, $this->mail );
	}

	/**
	 * @covers ::bbp_defer_subscription_notification
	 * @covers ::bbp_notify_topic_subscribers_on_reply_publication
	 */
	public function test_pending_reply_notifies_once_when_approved() {
		$content   = $this->create_topic_subscription();
		$reply_id  = $this->create_reply( $content, bbp_get_pending_status_id() );
		$published = array();
		$callback  = function( $published_reply_id, $topic_id, $forum_id, $anonymous_data, $author_id ) use ( &$published ) {
			$published = compact( 'published_reply_id', 'topic_id', 'forum_id', 'anonymous_data', 'author_id' );
		};

		add_action( 'bbp_deferred_reply_published', $callback, 10, 5 );
		$this->fire_new_reply( $reply_id, $content );

		$this->assertCount( 0, $this->mail );
		$this->assertTrue( metadata_exists( 'post', $reply_id, '_bbp_subscription_notification_pending' ) );
		$this->assertSame( $reply_id, bbp_approve_reply( $reply_id ) );
		remove_action( 'bbp_deferred_reply_published', $callback, 10 );

		$this->assertCount( 1, $this->mail );
		$this->assertFalse( metadata_exists( 'post', $reply_id, '_bbp_subscription_notification_pending' ) );
		$this->assertSame( $reply_id, $published['published_reply_id'] );
		$this->assertSame( $content['topic_id'], $published['topic_id'] );
		$this->assertSame( $content['forum_id'], $published['forum_id'] );
		$this->assertSame( array(), $published['anonymous_data'] );
		$this->assertSame( $content['author_id'], $published['author_id'] );
		$this->assert_mail_recipient( $content );

		$this->assertSame( $reply_id, bbp_unapprove_reply( $reply_id ) );
		$this->assertSame( $reply_id, bbp_approve_reply( $reply_id ) );
		$this->assertCount( 1, $this->mail );
	}

	/**
	 * @covers ::bbp_defer_subscription_notification
	 * @covers ::bbp_notify_topic_subscribers_on_reply_publication
	 */
	public function test_spam_reply_notifies_once_when_unspammed() {
		$content  = $this->create_topic_subscription();
		$reply_id = $this->create_reply( $content, bbp_get_spam_status_id() );

		update_post_meta( $reply_id, '_bbp_spam_meta_status', bbp_get_public_status_id() );
		$this->fire_new_reply( $reply_id, $content );

		$this->assertCount( 0, $this->mail );
		$this->assertTrue( metadata_exists( 'post', $reply_id, '_bbp_subscription_notification_pending' ) );
		$this->assertSame( $reply_id, bbp_unspam_reply( $reply_id ) );
		$this->assertCount( 1, $this->mail );
		$this->assertFalse( metadata_exists( 'post', $reply_id, '_bbp_subscription_notification_pending' ) );

		$this->assertSame( $reply_id, bbp_spam_reply( $reply_id ) );
		$this->assertSame( $reply_id, bbp_unspam_reply( $reply_id ) );
		$this->assertCount( 1, $this->mail );
	}

	/** @covers ::bbp_notify_forum_subscribers_on_topic_publication */
	public function test_direct_topic_status_transition_notifies() {
		$content  = $this->create_forum_subscription();
		$topic_id = $this->create_topic( $content, bbp_get_pending_status_id() );

		$this->fire_new_topic( $topic_id, $content );
		wp_update_post( array( 'ID' => $topic_id, 'post_title' => 'Still pending' ) );

		$this->assertCount( 0, $this->mail );
		$this->assertTrue( metadata_exists( 'post', $topic_id, '_bbp_subscription_notification_pending' ) );

		wp_update_post( array( 'ID' => $topic_id, 'post_status' => bbp_get_public_status_id() ) );

		$this->assertCount( 1, $this->mail );
		$this->assertFalse( metadata_exists( 'post', $topic_id, '_bbp_subscription_notification_pending' ) );
	}

	/** @covers ::bbp_notify_topic_subscribers_on_reply_publication */
	public function test_direct_reply_status_transition_notifies() {
		$content  = $this->create_topic_subscription();
		$reply_id = $this->create_reply( $content, bbp_get_pending_status_id() );

		$this->fire_new_reply( $reply_id, $content );
		wp_update_post( array( 'ID' => $reply_id, 'post_status' => bbp_get_public_status_id() ) );

		$this->assertCount( 1, $this->mail );
		$this->assertFalse( metadata_exists( 'post', $reply_id, '_bbp_subscription_notification_pending' ) );
	}

	/** @covers ::bbp_notify_forum_subscribers_on_topic_publication */
	public function test_pending_topic_spammed_then_unspammed_waits_for_approval() {
		$content  = $this->create_forum_subscription();
		$topic_id = $this->create_topic( $content, bbp_get_pending_status_id() );

		$this->fire_new_topic( $topic_id, $content );
		$this->assertSame( $topic_id, bbp_spam_topic( $topic_id ) );
		$this->assertSame( $topic_id, bbp_unspam_topic( $topic_id ) );

		$this->assertCount( 0, $this->mail );
		$this->assertTrue( metadata_exists( 'post', $topic_id, '_bbp_subscription_notification_pending' ) );
		$this->assertSame( $topic_id, bbp_approve_topic( $topic_id ) );
		$this->assertCount( 1, $this->mail );
	}

	/** @covers ::bbp_notify_forum_subscribers_on_topic_publication */
	public function test_publication_without_deferred_notification_does_not_send_mail() {
		$content  = $this->create_forum_subscription();
		$topic_id = $this->create_topic( $content, bbp_get_pending_status_id() );

		$this->assertSame( $topic_id, bbp_approve_topic( $topic_id ) );
		$this->assertCount( 0, $this->mail );
	}

	/**
	 * @covers ::bbp_get_deferred_subscription_statuses
	 * @covers ::bbp_defer_subscription_notification
	 * @covers ::bbp_notify_forum_subscribers_on_topic_publication
	 */
	public function test_filtered_status_defers_notification() {
		$content  = $this->create_forum_subscription();
		$topic_id = $this->create_topic( $content, bbp_get_trash_status_id() );
		$filter   = function( $statuses ) {
			$statuses[] = bbp_get_trash_status_id();
			return $statuses;
		};

		add_filter( 'bbp_get_deferred_subscription_statuses', $filter );
		$this->fire_new_topic( $topic_id, $content );

		$this->assertTrue( metadata_exists( 'post', $topic_id, '_bbp_subscription_notification_pending' ) );
		wp_update_post( array( 'ID' => $topic_id, 'post_status' => bbp_get_public_status_id() ) );
		remove_filter( 'bbp_get_deferred_subscription_statuses', $filter );

		$this->assertCount( 1, $this->mail );
		$this->assertFalse( metadata_exists( 'post', $topic_id, '_bbp_subscription_notification_pending' ) );
	}

	/** @covers ::bbp_notify_forum_subscribers_on_topic_publication */
	public function test_non_deferred_status_consumes_stale_marker_without_notifying() {
		$content  = $this->create_forum_subscription();
		$topic_id = $this->create_topic( $content, bbp_get_trash_status_id() );

		add_post_meta( $topic_id, '_bbp_subscription_notification_pending', 1, true );
		wp_update_post( array( 'ID' => $topic_id, 'post_status' => bbp_get_public_status_id() ) );

		$this->assertCount( 0, $this->mail );
		$this->assertFalse( metadata_exists( 'post', $topic_id, '_bbp_subscription_notification_pending' ) );
	}

	/** @covers ::bbp_defer_subscription_notification */
	public function test_trashed_content_is_not_deferred() {
		$content  = $this->create_topic_subscription();
		$topic_id = $this->create_topic( $content, bbp_get_trash_status_id() );
		$reply_id = $this->create_reply( $content, bbp_get_trash_status_id() );

		$this->fire_new_topic( $topic_id, $content );
		$this->fire_new_reply( $reply_id, $content );

		$this->assertFalse( metadata_exists( 'post', $topic_id, '_bbp_subscription_notification_pending' ) );
		$this->assertFalse( metadata_exists( 'post', $reply_id, '_bbp_subscription_notification_pending' ) );
		$this->assertCount( 0, $this->mail );
	}

	/** @covers ::bbp_defer_subscription_notification */
	public function test_public_content_is_not_deferred() {
		$content  = $this->create_topic_subscription();
		$topic_id = $this->create_topic( $content, bbp_get_public_status_id() );
		$reply_id = $this->create_reply( $content, bbp_get_public_status_id() );

		$this->fire_new_topic( $topic_id, $content );
		$this->fire_new_reply( $reply_id, $content );

		$this->assertFalse( metadata_exists( 'post', $topic_id, '_bbp_subscription_notification_pending' ) );
		$this->assertFalse( metadata_exists( 'post', $reply_id, '_bbp_subscription_notification_pending' ) );
		$this->assertCount( 2, $this->mail );
	}

	/** @covers ::bbp_notify_topic_subscribers_on_reply_publication */
	public function test_reply_published_before_topic_consumes_marker_without_notifying() {
		$content = $this->create_topic_subscription();
		wp_update_post( array( 'ID' => $content['topic_id'], 'post_status' => bbp_get_pending_status_id() ) );
		$reply_id = $this->create_reply( $content, bbp_get_pending_status_id() );

		$this->fire_new_reply( $reply_id, $content );
		wp_update_post( array( 'ID' => $reply_id, 'post_status' => bbp_get_public_status_id() ) );

		$this->assertCount( 0, $this->mail );
		$this->assertFalse( metadata_exists( 'post', $reply_id, '_bbp_subscription_notification_pending' ) );

		wp_update_post( array( 'ID' => $content['topic_id'], 'post_status' => bbp_get_public_status_id() ) );
		$this->assertCount( 0, $this->mail );
	}

	/** @covers ::bbp_defer_subscription_notification */
	public function test_unhooked_topic_notifier_does_not_defer() {
		$content  = $this->create_forum_subscription();
		$topic_id = $this->create_topic( $content, bbp_get_pending_status_id() );

		remove_action( 'bbp_new_topic', 'bbp_notify_forum_subscribers', 11 );
		$this->fire_new_topic( $topic_id, $content );
		add_action( 'bbp_new_topic', 'bbp_notify_forum_subscribers', 11, 4 );

		$this->assertFalse( metadata_exists( 'post', $topic_id, '_bbp_subscription_notification_pending' ) );
		$this->assertCount( 0, $this->mail );
	}

	/** @covers ::bbp_notify_forum_subscribers_on_topic_publication */
	public function test_notifier_unhooked_after_deferral_consumes_marker_without_notifying() {
		$content  = $this->create_forum_subscription();
		$topic_id = $this->create_topic( $content, bbp_get_pending_status_id() );

		$this->fire_new_topic( $topic_id, $content );
		remove_action( 'bbp_new_topic', 'bbp_notify_forum_subscribers', 11 );
		wp_update_post( array( 'ID' => $topic_id, 'post_status' => bbp_get_public_status_id() ) );
		add_action( 'bbp_new_topic', 'bbp_notify_forum_subscribers', 11, 4 );

		$this->assertFalse( metadata_exists( 'post', $topic_id, '_bbp_subscription_notification_pending' ) );
		$this->assertCount( 0, $this->mail );
	}

	/** @covers ::bbp_notify_forum_subscribers_on_topic_publication */
	public function test_disabled_subscriptions_consume_marker_without_notifying() {
		$content  = $this->create_forum_subscription();
		$topic_id = $this->create_topic( $content, bbp_get_pending_status_id() );

		$this->fire_new_topic( $topic_id, $content );
		add_filter( 'bbp_is_subscriptions_active', '__return_false' );
		wp_update_post( array( 'ID' => $topic_id, 'post_status' => bbp_get_public_status_id() ) );
		remove_filter( 'bbp_is_subscriptions_active', '__return_false' );

		$this->assertFalse( metadata_exists( 'post', $topic_id, '_bbp_subscription_notification_pending' ) );
		$this->assertCount( 0, $this->mail );
	}

	/** @covers ::bbp_defer_subscription_notification */
	public function test_disabled_subscriptions_do_not_defer() {
		$content  = $this->create_forum_subscription();
		$topic_id = $this->create_topic( $content, bbp_get_pending_status_id() );

		add_filter( 'bbp_is_subscriptions_active', '__return_false' );
		$this->fire_new_topic( $topic_id, $content );
		remove_filter( 'bbp_is_subscriptions_active', '__return_false' );

		$this->assertFalse( metadata_exists( 'post', $topic_id, '_bbp_subscription_notification_pending' ) );
		$this->assertCount( 0, $this->mail );
	}

	/**
	 * @ticket BBP3785
	 * @covers ::bbp_defer_subscription_notification
	 */
	public function test_deferred_notifications_require_their_subscription_subtype() {
		$content        = $this->create_topic_subscription();
		$disabled_topic = $this->create_topic( $content, bbp_get_pending_status_id() );
		$disabled_reply = $this->create_reply( $content, bbp_get_pending_status_id() );

		update_option( '_bbp_enable_subscriptions', array( 'post:' . bbp_get_topic_post_type() ) );
		$this->fire_new_topic( $disabled_topic, $content );
		$this->assertFalse( metadata_exists( 'post', $disabled_topic, '_bbp_subscription_notification_pending' ) );

		update_option( '_bbp_enable_subscriptions', array( 'post:' . bbp_get_forum_post_type() ) );
		$this->fire_new_reply( $disabled_reply, $content );
		$this->assertFalse( metadata_exists( 'post', $disabled_reply, '_bbp_subscription_notification_pending' ) );

		$enabled_topic = $this->create_topic( $content, bbp_get_pending_status_id() );
		$this->fire_new_topic( $enabled_topic, $content );
		$this->assertTrue( metadata_exists( 'post', $enabled_topic, '_bbp_subscription_notification_pending' ) );

		$enabled_reply = $this->create_reply( $content, bbp_get_pending_status_id() );
		update_option( '_bbp_enable_subscriptions', array( 'post:' . bbp_get_topic_post_type() ) );
		$this->fire_new_reply( $enabled_reply, $content );
		$this->assertTrue( metadata_exists( 'post', $enabled_reply, '_bbp_subscription_notification_pending' ) );
		$this->assertCount( 0, $this->mail );
	}
}
