<?php

/**
 * BuddyPress notification tests.
 *
 * @group extend
 * @group buddypress
 * @group notifications
 */
class BBP_Tests_Extend_BuddyPress_Notifications extends BBP_UnitTestCase {

	/**
	 * @covers ::bbp_buddypress_add_notification
	 */
	public function test_only_published_replies_notify_topic_authors() {
		$topic_author = $this->factory->user->create();
		$reply_author = $this->factory->user->create();
		$forum_id     = $this->factory->forum->create();
		$topic_id     = $this->factory->topic->create(
			array(
				'post_parent' => $forum_id,
				'post_author' => $topic_author
			)
		);

		foreach ( array( bbp_get_public_status_id(), bbp_get_pending_status_id(), bbp_get_spam_status_id() ) as $status ) {
			$reply_id = $this->factory->reply->create(
				array(
					'post_parent' => $topic_id,
					'post_author' => $reply_author,
					'post_status' => $status,
					'reply_meta'  => array(
						'_bbp_forum_id' => $forum_id,
						'_bbp_topic_id' => $topic_id
					)
				)
			);

			bbp_buddypress_add_notification( $reply_id, $topic_id, $forum_id, array(), $reply_author, false, 0 );

			$notifications = BP_Notifications_Notification::get(
				array(
					'user_id'          => $topic_author,
					'item_id'          => $reply_id,
					'component_name'   => bbp_get_component_name(),
					'component_action' => 'bbp_new_reply_' . $topic_id
				)
			);

			$this->assertCount( bbp_get_public_status_id() === $status ? 1 : 0, $notifications );
		}
	}
}
