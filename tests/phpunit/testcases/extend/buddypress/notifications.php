<?php

class BBP_Tests_Extend_BuddyPress_Notification_Exception extends Exception {}

/**
 * BuddyPress notification tests.
 *
 * @group extend
 * @group buddypress
 * @group notifications
 */
class BBP_Tests_Extend_BuddyPress_Notifications extends BBP_UnitTestCase {

	private $old_get;
	private $old_errors;
	private $old_request;
	private $old_user;

	public function setUp(): void {
		parent::setUp();

		$this->old_get     = $_GET;
		$this->old_errors  = bbpress()->errors;
		$this->old_request = $_REQUEST;
		$this->old_user    = get_current_user_id();

		bbpress()->errors = new WP_Error();
	}

	public function tearDown(): void {
		$_GET             = $this->old_get;
		$_REQUEST         = $this->old_request;
		bbpress()->errors = $this->old_errors;
		$this->set_current_user( $this->old_user );

		parent::tearDown();
	}

	private function capture_mark_notifications_redirect( $action ) {
		$redirect   = null;
		$requested  = null;
		$url_filter = static function ( $url, $reply_id ) use ( &$requested ) {
			$requested = array( $url, $reply_id );
			return $url;
		};
		$filter = static function ( $location, $status ) use ( &$redirect ) {
			$redirect = array( $location, $status );
			throw new BBP_Tests_Extend_BuddyPress_Notification_Exception( 'Redirect intercepted.' );
		};
		add_filter( 'bbp_get_reply_url', $url_filter, 10, 2 );
		add_filter( 'wp_redirect', $filter, 10, 2 );

		try {
			bbp_buddypress_mark_notifications( $action );
			$this->fail( 'Expected the redirect filter to intercept the redirect.' );
		} catch ( BBP_Tests_Extend_BuddyPress_Notification_Exception $exception ) {
			$this->assertSame( 'Redirect intercepted.', $exception->getMessage() );
		} finally {
			remove_filter( 'bbp_get_reply_url', $url_filter, 10 );
			remove_filter( 'wp_redirect', $filter, 10 );
		}

		return array( $requested, $redirect );
	}

	private function format_multiple_reply_notification( $count ) {
		$forum_id = $this->factory->forum->create();
		$topic_id = $this->factory->topic->create(
			array(
				'post_parent' => $forum_id,
				'post_title'  => 'Plural Topic'
			)
		);
		$reply_id = $this->factory->reply->create(
			array(
				'post_parent' => $topic_id,
				'reply_meta'  => array(
					'_bbp_forum_id' => $forum_id,
					'_bbp_topic_id' => $topic_id
				)
			)
		);

		return bbp_format_buddypress_notifications(
			'',
			$reply_id,
			0,
			$count,
			'object',
			'bbp_new_reply_' . $topic_id,
			bbp_get_component_name(),
			0
		);
	}

	/**
	 * @covers ::bbp_format_buddypress_notifications
	 */
	public function test_multiple_reply_notification_uses_english_plural() {
		$this->assertSame( 'You have 2 new replies to Plural Topic', $this->format_multiple_reply_notification( 2 )['text'] );
	}

	/**
	 * @covers ::bbp_format_buddypress_notifications
	 */
	public function test_multiple_reply_notification_uses_locale_plural_form() {
		$filter = function( $translation, $single, $plural, $number, $domain ) {
			if ( ( 21 === $number ) && ( 'bbpress' === $domain ) ) {
				$translation = 'You have %1$d localized replies to %2$s';
			}

			return $translation;
		};

		add_filter( 'ngettext', $filter, 10, 5 );
		$text = $this->format_multiple_reply_notification( 21 )['text'];
		remove_filter( 'ngettext', $filter, 10 );

		$this->assertSame( 'You have 21 localized replies to Plural Topic', $text );
	}

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

	/**
	 * @covers ::bbp_filter_notifications_get_registered_components
	 */
	public function test_registered_notification_components_include_bbpress_component() {
		$this->assertSame( array( 'activity', bbp_get_component_name() ), bbp_filter_notifications_get_registered_components( array( 'activity' ) ) );
		$this->assertSame( array( bbp_get_component_name() ), bbp_filter_notifications_get_registered_components( 'activity' ) );
	}

	/**
	 * @covers ::bbp_buddypress_mark_notifications
	 */
	public function test_mark_notifications_ignores_unrelated_requests() {
		$filter = static function () {
			throw new BBP_Tests_Extend_BuddyPress_Notification_Exception( 'Unexpected redirect.' );
		};
		add_filter( 'wp_redirect', $filter );

		$_GET = array();
		try {
			$this->assertNull( bbp_buddypress_mark_notifications( 'bbp_mark_read' ) );

			$_GET['topic_id'] = 123;
			$this->assertNull( bbp_buddypress_mark_notifications( 'other-action' ) );
		} finally {
			remove_filter( 'wp_redirect', $filter );
		}
	}

	/**
	 * @covers ::bbp_buddypress_mark_notifications
	 */
	public function test_mark_notifications_marks_topic_reply_notifications_read() {
		$user_id  = $this->factory->user->create( array( 'role' => 'subscriber' ) );
		$forum_id = $this->factory->forum->create();
		$topic_id = $this->factory->topic->create( array( 'post_parent' => $forum_id ) );
		$reply_id = $this->factory->reply->create( array( 'post_parent' => $topic_id ) );
		$action   = 'bbp_new_reply_' . $topic_id;

		$this->set_current_user( $user_id );
		$this->assertNotFalse(
			bp_notifications_add_notification(
				array(
					'user_id'          => $user_id,
					'item_id'          => $reply_id,
					'component_name'   => bbp_get_component_name(),
					'component_action' => $action,
				)
			)
		);
		$this->assertSame( 1, bp_notifications_get_unread_notification_count( $user_id ) );

		$_GET['topic_id']     = $topic_id;
		$_REQUEST['_wpnonce'] = wp_create_nonce( 'bbp_mark_topic_' . $topic_id );
		$observed             = null;
		$handler              = static function ( $marked, $handled_user_id, $handled_topic_id, $handled_action ) use ( &$observed ) {
			$observed = array( $marked, $handled_user_id, $handled_topic_id, $handled_action );
		};
		add_action( 'bbp_notifications_handler', $handler, 10, 4 );

		try {
			list( $requested, $redirect ) = $this->capture_mark_notifications_redirect( 'bbp_mark_read' );
		} finally {
			remove_action( 'bbp_notifications_handler', $handler, 10 );
		}

		$this->assertSame( array( 1, $user_id, $topic_id, 'bbp_mark_read' ), $observed );
		$this->assertSame( array( bbp_get_reply_url( $reply_id ), $reply_id ), $requested );
		$this->assertSame( 302, $redirect[1] );
		$this->assertSame( 0, bp_notifications_get_unread_notification_count( $user_id ) );
	}

	/**
	 * @covers ::bbp_buddypress_mark_notifications
	 */
	public function test_mark_notifications_rejects_invalid_nonce() {
		$user_id  = $this->factory->user->create( array( 'role' => 'subscriber' ) );
		$forum_id = $this->factory->forum->create();
		$topic_id = $this->factory->topic->create( array( 'post_parent' => $forum_id ) );
		$reply_id = $this->factory->reply->create( array( 'post_parent' => $topic_id ) );

		$this->set_current_user( $user_id );
		bp_notifications_add_notification(
			array(
				'user_id'          => $user_id,
				'item_id'          => $reply_id,
				'component_name'   => bbp_get_component_name(),
				'component_action' => 'bbp_new_reply_' . $topic_id,
			)
		);
		$_GET['topic_id']     = $topic_id;
		$_REQUEST['_wpnonce'] = 'invalid';

		list( $requested, $redirect ) = $this->capture_mark_notifications_redirect( 'bbp_mark_read' );

		$this->assertContains( 'bbp_notification_topic_id', bbpress()->errors->get_error_codes() );
		$this->assertSame( array( bbp_get_reply_url( $topic_id ), $topic_id ), $requested );
		$this->assertSame( 302, $redirect[1] );
		$this->assertSame( 1, bp_notifications_get_unread_notification_count( $user_id ) );
	}

	/**
	 * @covers ::bbp_buddypress_mark_notifications
	 */
	public function test_mark_notifications_rejects_user_without_permission() {
		$user_id  = $this->factory->user->create();
		$forum_id = $this->factory->forum->create();
		$topic_id = $this->factory->topic->create( array( 'post_parent' => $forum_id ) );
		$reply_id = $this->factory->reply->create( array( 'post_parent' => $topic_id ) );

		$this->set_current_user( $user_id );
		bp_notifications_add_notification(
			array(
				'user_id'          => $user_id,
				'item_id'          => $reply_id,
				'component_name'   => bbp_get_component_name(),
				'component_action' => 'bbp_new_reply_' . $topic_id,
			)
		);
		$_GET['topic_id']     = $topic_id;
		$_REQUEST['_wpnonce'] = wp_create_nonce( 'bbp_mark_topic_' . $topic_id );
		$deny_edit_user       = static function ( $caps, $cap ) {
			return ( 'edit_user' === $cap ) ? array( 'do_not_allow' ) : $caps;
		};
		add_filter( 'map_meta_cap', $deny_edit_user, 10, 2 );

		try {
			list( $requested, $redirect ) = $this->capture_mark_notifications_redirect( 'bbp_mark_read' );
		} finally {
			remove_filter( 'map_meta_cap', $deny_edit_user, 10 );
		}

		$this->assertContains( 'bbp_notification_permission', bbpress()->errors->get_error_codes() );
		$this->assertSame( array( bbp_get_reply_url( $topic_id ), $topic_id ), $requested );
		$this->assertSame( 302, $redirect[1] );
		$this->assertSame( 1, bp_notifications_get_unread_notification_count( $user_id ) );
	}
}
