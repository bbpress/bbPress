<?php

/**
 * Tests for taxonomy subscription notifications.
 *
 * @group common
 * @group notifications
 */
class BBP_Tests_Common_Notification_Term_Subscriptions extends BBP_UnitTestCase {

	private $mail = array();
	private $subscriber_sources = array();

	public function setUp(): void {
		parent::setUp();

		add_filter( 'pre_wp_mail', array( $this, 'capture_mail' ), 10, 2 );
	}

	public function tearDown(): void {
		remove_filter( 'pre_wp_mail', array( $this, 'capture_mail' ), 10 );
		remove_filter( 'bbp_forum_subscription_user_ids', array( $this, 'capture_subscriber_sources' ), 10 );

		parent::tearDown();
	}

	public function capture_mail( $return, $atts ) {
		$this->mail[] = $atts;

		return true;
	}

	public function capture_subscriber_sources( $user_ids, $topic_id, $forum_id, $forum_user_ids, $term_user_ids ) {
		$this->subscriber_sources = compact( 'forum_user_ids', 'term_user_ids' );

		return $user_ids;
	}

	private function create_topic() {
		$author_id = $this->factory->user->create( array( 'role' => bbp_get_keymaster_role() ) );
		$forum_id  = $this->factory->forum->create();
		$topic_id  = $this->factory->topic->create(
			array(
				'post_author' => $author_id,
				'post_parent' => $forum_id,
				'topic_meta'  => array( 'forum_id' => $forum_id )
			)
		);

		return compact( 'author_id', 'forum_id', 'topic_id' );
	}

	private function mail_headers() {
		return implode( "\n", (array) $this->mail[0]['headers'] );
	}

	/**
	 * @ticket BBP2989
	 *
	 * @covers ::bbp_get_subscribers_for_topic_terms
	 * @covers ::bbp_notify_forum_subscribers
	 */
	public function test_topic_tag_subscriber_receives_new_topic_notification() {
		$content       = $this->create_topic();
		$subscriber_id = $this->factory->user->create(
			array(
				'role'       => bbp_get_participant_role(),
				'user_email' => 'tag-subscriber@example.org'
			)
		);
		$term_id       = $this->factory->term->create( array( 'taxonomy' => bbp_get_topic_tag_tax_id() ) );

		wp_set_object_terms( $content['topic_id'], $term_id, bbp_get_topic_tag_tax_id() );
		bbp_add_user_subscription( $subscriber_id, $term_id, 'term' );
		update_option( '_bbp_enable_subscriptions', array( 'term:' . bbp_get_topic_tag_tax_id() ) );
		add_filter( 'bbp_forum_subscription_user_ids', array( $this, 'capture_subscriber_sources' ), 10, 5 );

		$this->assertTrue( bbp_notify_forum_subscribers( $content['topic_id'], $content['forum_id'], array(), $content['author_id'] ) );
		$this->assertStringContainsString( 'Bcc: tag-subscriber@example.org', $this->mail_headers() );
		$this->assertStringContainsString( 'subscribed to related forum activity', $this->mail[0]['message'] );
		$this->assertSame( array(), $this->subscriber_sources['forum_user_ids'] );
		$this->assertSame( array( $subscriber_id ), $this->subscriber_sources['term_user_ids'] );
	}

	/**
	 * @ticket BBP2989
	 *
	 * @covers ::bbp_get_subscribers_for_topic_terms
	 * @covers ::bbp_notify_forum_subscribers
	 */
	public function test_topic_notification_deduplicates_forum_and_term_subscribers() {
		$content       = $this->create_topic();
		$subscriber_id = $this->factory->user->create(
			array(
				'role'       => bbp_get_participant_role(),
				'user_email' => 'shared-subscriber@example.org'
			)
		);
		$term_ids      = $this->factory->term->create_many(
			2,
			array( 'taxonomy' => bbp_get_topic_tag_tax_id() )
		);

		wp_set_object_terms( $content['topic_id'], $term_ids, bbp_get_topic_tag_tax_id() );
		bbp_add_user_forum_subscription( $subscriber_id, $content['forum_id'] );
		bbp_add_user_subscription( $subscriber_id, $term_ids[0], 'term' );
		bbp_add_user_subscription( $subscriber_id, $term_ids[1], 'term' );
		add_filter( 'bbp_forum_subscription_user_ids', array( $this, 'capture_subscriber_sources' ), 10, 5 );

		$this->assertTrue( bbp_notify_forum_subscribers( $content['topic_id'], $content['forum_id'], array(), $content['author_id'] ) );
		$this->assertSame( 1, substr_count( $this->mail_headers(), 'Bcc: shared-subscriber@example.org' ) );
		$this->assertStringContainsString( 'subscribed to a forum', $this->mail[0]['message'] );
		$this->assertSame( array( $subscriber_id ), $this->subscriber_sources['forum_user_ids'] );
		$this->assertSame( array(), $this->subscriber_sources['term_user_ids'] );
	}

	/**
	 * @ticket BBP2989
	 *
	 * @covers ::bbp_get_topic_subscription_taxonomies
	 * @covers ::bbp_get_subscribers_for_topic_terms
	 * @covers ::bbp_notify_forum_subscribers
	 */
	public function test_custom_taxonomy_subscriber_receives_new_topic_notification() {
		$taxonomy = 'bbp_test_notify_subscriptions';
		register_taxonomy(
			$taxonomy,
			bbp_get_topic_post_type(),
			array(
				'public'            => true,
				'bbp_subscriptions' => true
			)
		);

		try {
			$content       = $this->create_topic();
			$subscriber_id = $this->factory->user->create(
				array(
					'role'       => bbp_get_participant_role(),
					'user_email' => 'custom-subscriber@example.org'
				)
			);
			$term_id       = $this->factory->term->create( array( 'taxonomy' => $taxonomy ) );

			wp_set_object_terms( $content['topic_id'], $term_id, $taxonomy );
			bbp_add_user_subscription( $subscriber_id, $term_id, 'term' );
			update_option( '_bbp_enable_subscriptions', array( 'term:' . $taxonomy ) );

			$this->assertTrue( bbp_notify_forum_subscribers( $content['topic_id'], $content['forum_id'], array(), $content['author_id'] ) );
			$this->assertStringContainsString( 'Bcc: custom-subscriber@example.org', $this->mail_headers() );
		} finally {
			unregister_taxonomy( $taxonomy );
		}
	}

	/**
	 * @ticket BBP2989
	 *
	 * @covers ::bbp_notify_forum_subscribers
	 */
	public function test_topic_tag_subscriber_without_forum_access_is_not_notified() {
		$author_id = $this->factory->user->create( array( 'role' => bbp_get_keymaster_role() ) );
		$forum_id  = $this->factory->forum->create( array( 'post_status' => bbp_get_hidden_status_id() ) );
		$topic_id  = $this->factory->topic->create(
			array(
				'post_author' => $author_id,
				'post_parent' => $forum_id,
				'topic_meta'  => array( 'forum_id' => $forum_id )
			)
		);
		$subscriber_id = $this->factory->user->create( array( 'role' => bbp_get_participant_role() ) );
		$term_id       = $this->factory->term->create( array( 'taxonomy' => bbp_get_topic_tag_tax_id() ) );

		wp_set_object_terms( $topic_id, $term_id, bbp_get_topic_tag_tax_id() );
		bbp_add_user_subscription( $subscriber_id, $term_id, 'term' );
		update_option( '_bbp_enable_subscriptions', array( 'term:' . bbp_get_topic_tag_tax_id() ) );

		$this->assertFalse( bbp_notify_forum_subscribers( $topic_id, $forum_id, array(), $author_id ) );
		$this->assertSame( array(), $this->mail );
	}
}
