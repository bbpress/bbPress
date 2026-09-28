<?php

/**
 * Tests for restoring filters after subscription notifications.
 *
 * @group common
 * @group notifications
 */
class BBP_Tests_Common_Notification_Filters extends BBP_UnitTestCase {

	/**
	 * Create public content with a subscribed user.
	 *
	 * @return array Forum, topic, reply, and author IDs.
	 */
	private function create_subscribed_content() {
		$subscriber_id = $this->factory->user->create();
		$author_id     = $this->factory->user->create();
		bbp_set_user_role( $subscriber_id, bbp_get_participant_role() );
		bbp_set_user_role( $author_id, bbp_get_keymaster_role() );

		$forum_id = $this->factory->forum->create();
		$topic_id = $this->factory->topic->create(
			array(
				'post_author' => $author_id,
				'post_parent' => $forum_id,
				'topic_meta'  => array( 'forum_id' => $forum_id ),
			)
		);
		$reply_id = $this->factory->reply->create(
			array(
				'post_author' => $author_id,
				'post_parent' => $topic_id,
				'reply_meta'  => array(
					'forum_id' => $forum_id,
					'topic_id' => $topic_id,
				),
			)
		);

		bbp_add_user_topic_subscription( $subscriber_id, $topic_id );
		bbp_add_user_forum_subscription( $subscriber_id, $forum_id );

		return compact( 'forum_id', 'topic_id', 'reply_id', 'author_id' );
	}

	/**
	 * Reply mail restores every filter after success and cancellation.
	 */
	public function test_reply_notification_restores_filters() {
		$content        = $this->create_subscribed_content();
		$hooks          = array( 'bbp_get_reply_content', 'bbp_get_topic_title', 'the_title' );
		$identity       = function( $value ) { return $value; };
		$cancel         = function() { return ''; };
		$cancel_subject = function() { return ''; };
		$send           = function() { return true; };

		foreach ( $hooks as $hook ) {
			add_filter( $hook, $identity );
		}
		add_filter( 'pre_wp_mail', $send );

		try {
			foreach ( $hooks as $hook ) {
				$this->assertSame( 10, has_filter( $hook, $identity ), 'before ' . $hook );
			}
			$this->assertTrue( bbp_notify_topic_subscribers( $content['reply_id'], $content['topic_id'], $content['forum_id'], array(), $content['author_id'] ) );
			foreach ( $hooks as $hook ) {
				$this->assertSame( 10, has_filter( $hook, $identity ), $hook );
			}

			add_filter( 'bbp_subscription_mail_message', $cancel );
			$this->assertNull( bbp_notify_topic_subscribers( $content['reply_id'], $content['topic_id'], $content['forum_id'], array(), $content['author_id'] ) );
			foreach ( $hooks as $hook ) {
				$this->assertSame( 10, has_filter( $hook, $identity ), $hook );
			}

			remove_filter( 'bbp_subscription_mail_message', $cancel );
			add_filter( 'bbp_subscription_mail_title', $cancel_subject );
			$this->assertNull( bbp_notify_topic_subscribers( $content['reply_id'], $content['topic_id'], $content['forum_id'], array(), $content['author_id'] ) );
			foreach ( $hooks as $hook ) {
				$this->assertSame( 10, has_filter( $hook, $identity ), $hook );
			}
		} finally {
			remove_filter( 'bbp_subscription_mail_message', $cancel );
			remove_filter( 'bbp_subscription_mail_title', $cancel_subject );
			remove_filter( 'pre_wp_mail', $send );
			foreach ( $hooks as $hook ) {
				bbp_restore_all_filters( $hook );
				remove_filter( $hook, $identity );
			}
		}
	}

	/**
	 * Topic mail restores every filter after success and cancellation.
	 */
	public function test_topic_notification_restores_filters() {
		$content        = $this->create_subscribed_content();
		$hooks          = array( 'bbp_get_topic_content', 'bbp_get_topic_title', 'the_title' );
		$identity       = function( $value ) { return $value; };
		$cancel         = function() { return ''; };
		$cancel_subject = function() { return ''; };
		$send           = function() { return true; };

		foreach ( $hooks as $hook ) {
			add_filter( $hook, $identity );
		}
		add_filter( 'pre_wp_mail', $send );

		try {
			foreach ( $hooks as $hook ) {
				$this->assertSame( 10, has_filter( $hook, $identity ), 'before ' . $hook );
			}
			$this->assertTrue( bbp_notify_forum_subscribers( $content['topic_id'], $content['forum_id'], array(), $content['author_id'] ) );
			foreach ( $hooks as $hook ) {
				$this->assertSame( 10, has_filter( $hook, $identity ), $hook );
			}

			add_filter( 'bbp_forum_subscription_mail_message', $cancel );
			$this->assertNull( bbp_notify_forum_subscribers( $content['topic_id'], $content['forum_id'], array(), $content['author_id'] ) );
			foreach ( $hooks as $hook ) {
				$this->assertSame( 10, has_filter( $hook, $identity ), $hook );
			}

			remove_filter( 'bbp_forum_subscription_mail_message', $cancel );
			add_filter( 'bbp_forum_subscription_mail_title', $cancel_subject );
			$this->assertNull( bbp_notify_forum_subscribers( $content['topic_id'], $content['forum_id'], array(), $content['author_id'] ) );
			foreach ( $hooks as $hook ) {
				$this->assertSame( 10, has_filter( $hook, $identity ), $hook );
			}
		} finally {
			remove_filter( 'bbp_forum_subscription_mail_message', $cancel );
			remove_filter( 'bbp_forum_subscription_mail_title', $cancel_subject );
			remove_filter( 'pre_wp_mail', $send );
			foreach ( $hooks as $hook ) {
				bbp_restore_all_filters( $hook );
				remove_filter( $hook, $identity );
			}
		}
	}
}
