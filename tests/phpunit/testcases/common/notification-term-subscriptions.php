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
		remove_filter( 'bbp_topic_subscription_user_ids', array( $this, 'capture_reply_subscriber_sources' ), 10 );

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

	public function capture_reply_subscriber_sources( $user_ids, $reply_id, $topic_id, $topic_user_ids, $term_user_ids ) {
		$this->subscriber_sources = compact( 'topic_user_ids', 'term_user_ids' );

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

	private function create_reply( $topic_id, $forum_id ) {
		$author_id = $this->factory->user->create( array( 'role' => bbp_get_keymaster_role() ) );
		$reply_id  = $this->factory->reply->create(
			array(
				'post_author' => $author_id,
				'post_parent' => $topic_id,
				'reply_meta'  => array(
					'forum_id' => $forum_id,
					'topic_id' => $topic_id
				)
			)
		);

		return compact( 'author_id', 'reply_id' );
	}

	private function unsubscribe_query() {
		$matches = array();
		$this->assertSame( 1, preg_match( '/List-Unsubscribe: <([^>]+)>/', $this->mail_headers(), $matches ) );

		$query = array();
		wp_parse_str( wp_parse_url( $matches[1], PHP_URL_QUERY ), $query );

		return $query;
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
		$this->assertCount( 1, $this->mail );
		$this->assertSame( 'tag-subscriber@example.org', $this->mail[0]['to'] );
		$this->assertStringContainsString( 'subscribed to the topic tag: ' . get_term( $term_id )->name, $this->mail[0]['message'] );
		$this->assertStringContainsString( 'Unsubscribe from this subscription:', $this->mail[0]['message'] );
		$this->assertStringContainsString( 'List-Unsubscribe-Post: List-Unsubscribe=One-Click', $this->mail_headers() );
		$this->assertSame( (string) $term_id, $this->unsubscribe_query()['object_id'] );
		$this->assertSame( 'term', $this->unsubscribe_query()['object_type'] );
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
		$this->assertCount( 1, $this->mail );
		$this->assertSame( 'shared-subscriber@example.org', $this->mail[0]['to'] );
		$this->assertStringContainsString( 'subscribed to the forum: ' . get_the_title( $content['forum_id'] ), $this->mail[0]['message'] );
		$this->assertSame( (string) $content['forum_id'], $this->unsubscribe_query()['object_id'] );
		$this->assertSame( 'post', $this->unsubscribe_query()['object_type'] );
		$this->assertSame( array( $subscriber_id ), $this->subscriber_sources['forum_user_ids'] );
		$this->assertSame( array(), $this->subscriber_sources['term_user_ids'] );
	}

	/**
	 * @ticket BBP2989
	 *
	 * @covers ::bbp_get_topic_term_subscription_contexts
	 * @covers ::bbp_notify_forum_subscribers
	 */
	public function test_topic_notification_deduplicates_multiple_matching_terms() {
		$content       = $this->create_topic();
		$subscriber_id = $this->factory->user->create(
			array(
				'role'       => bbp_get_participant_role(),
				'user_email' => 'multiple-terms@example.org'
			)
		);
		$term_ids      = $this->factory->term->create_many(
			2,
			array( 'taxonomy' => bbp_get_topic_tag_tax_id() )
		);
		$term_queries  = 0;
		$count_queries = function( $user_ids, $object_id, $type ) use ( &$term_queries ) {
			if ( 'term' === $type ) {
				$term_queries++;
			}

			return $user_ids;
		};

		wp_set_object_terms( $content['topic_id'], $term_ids, bbp_get_topic_tag_tax_id() );
		bbp_add_user_subscription( $subscriber_id, $term_ids[0], 'term' );
		bbp_add_user_subscription( $subscriber_id, $term_ids[1], 'term' );
		update_option( '_bbp_enable_subscriptions', array( 'term:' . bbp_get_topic_tag_tax_id() ) );
		add_filter( 'bbp_get_subscribers', $count_queries, 10, 3 );

		try {
			$this->assertTrue( bbp_notify_forum_subscribers( $content['topic_id'], $content['forum_id'], array(), $content['author_id'] ) );
		} finally {
			remove_filter( 'bbp_get_subscribers', $count_queries, 10 );
		}

		$this->assertCount( 1, $this->mail );
		$this->assertSame( 2, $term_queries );
		$this->assertContains( (int) $this->unsubscribe_query()['object_id'], $term_ids );
		$this->assertSame( 'term', $this->unsubscribe_query()['object_type'] );
	}

	/**
	 * @ticket BBP2989
	 *
	 * @covers ::bbp_get_topic_term_subscription_contexts
	 * @covers ::bbp_notify_forum_subscribers
	 */
	public function test_topic_notification_deduplicates_matching_taxonomies() {
		$taxonomy = 'bbp_test_notify_contexts';
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
					'user_email' => 'multiple-taxonomies@example.org'
				)
			);
			$tag_id        = $this->factory->term->create( array( 'taxonomy' => bbp_get_topic_tag_tax_id() ) );
			$custom_id     = $this->factory->term->create( array( 'taxonomy' => $taxonomy ) );

			wp_set_object_terms( $content['topic_id'], $tag_id, bbp_get_topic_tag_tax_id() );
			wp_set_object_terms( $content['topic_id'], $custom_id, $taxonomy );
			bbp_add_user_subscription( $subscriber_id, $tag_id, 'term' );
			bbp_add_user_subscription( $subscriber_id, $custom_id, 'term' );
			update_option(
				'_bbp_enable_subscriptions',
				array(
					'term:' . bbp_get_topic_tag_tax_id(),
					'term:' . $taxonomy
				)
			);

			$this->assertTrue( bbp_notify_forum_subscribers( $content['topic_id'], $content['forum_id'], array(), $content['author_id'] ) );
			$this->assertCount( 1, $this->mail );
			$this->assertContains( (int) $this->unsubscribe_query()['object_id'], array( $tag_id, $custom_id ) );
			$this->assertSame( 'term', $this->unsubscribe_query()['object_type'] );
		} finally {
			unregister_taxonomy( $taxonomy );
		}
	}

	/**
	 * @ticket BBP2989
	 *
	 * @covers ::bbp_get_topic_term_subscription_contexts
	 * @covers ::bbp_notify_topic_subscribers
	 */
	public function test_topic_tag_subscriber_receives_new_reply_notification() {
		$content       = $this->create_topic();
		$reply         = $this->create_reply( $content['topic_id'], $content['forum_id'] );
		$subscriber_id = $this->factory->user->create(
			array(
				'role'       => bbp_get_participant_role(),
				'user_email' => 'tag-reply-subscriber@example.org'
			)
		);
		$term_id       = $this->factory->term->create( array( 'taxonomy' => bbp_get_topic_tag_tax_id() ) );

		wp_set_object_terms( $content['topic_id'], $term_id, bbp_get_topic_tag_tax_id() );
		bbp_add_user_subscription( $subscriber_id, $term_id, 'term' );
		update_option( '_bbp_enable_subscriptions', array( 'term:' . bbp_get_topic_tag_tax_id() ) );
		add_filter( 'bbp_topic_subscription_user_ids', array( $this, 'capture_reply_subscriber_sources' ), 10, 5 );

		$this->assertTrue( bbp_notify_topic_subscribers( $reply['reply_id'], $content['topic_id'], $content['forum_id'], array(), $reply['author_id'] ) );
		$this->assertCount( 1, $this->mail );
		$this->assertSame( 'tag-reply-subscriber@example.org', $this->mail[0]['to'] );
		$this->assertStringContainsString( 'subscribed to the topic tag: ' . get_term( $term_id )->name, $this->mail[0]['message'] );
		$this->assertSame( (string) $term_id, $this->unsubscribe_query()['object_id'] );
		$this->assertSame( 'term', $this->unsubscribe_query()['object_type'] );
		$this->assertSame( array(), $this->subscriber_sources['topic_user_ids'] );
		$this->assertSame( array( $subscriber_id ), $this->subscriber_sources['term_user_ids'] );
	}

	/**
	 * @ticket BBP2989
	 *
	 * @covers ::bbp_notify_topic_subscribers
	 */
	public function test_reply_notification_deduplicates_topic_and_term_subscribers() {
		$content       = $this->create_topic();
		$reply         = $this->create_reply( $content['topic_id'], $content['forum_id'] );
		$subscriber_id = $this->factory->user->create(
			array(
				'role'       => bbp_get_participant_role(),
				'user_email' => 'shared-reply-subscriber@example.org'
			)
		);
		$term_id       = $this->factory->term->create( array( 'taxonomy' => bbp_get_topic_tag_tax_id() ) );

		wp_set_object_terms( $content['topic_id'], $term_id, bbp_get_topic_tag_tax_id() );
		bbp_add_user_topic_subscription( $subscriber_id, $content['topic_id'] );
		bbp_add_user_subscription( $subscriber_id, $term_id, 'term' );
		update_option(
			'_bbp_enable_subscriptions',
			array(
				'post:' . bbp_get_topic_post_type(),
				'term:' . bbp_get_topic_tag_tax_id()
			)
		);
		add_filter( 'bbp_topic_subscription_user_ids', array( $this, 'capture_reply_subscriber_sources' ), 10, 5 );

		$this->assertTrue( bbp_notify_topic_subscribers( $reply['reply_id'], $content['topic_id'], $content['forum_id'], array(), $reply['author_id'] ) );
		$this->assertCount( 1, $this->mail );
		$this->assertSame( 'shared-reply-subscriber@example.org', $this->mail[0]['to'] );
		$this->assertStringContainsString( 'subscribed to the topic: ' . get_the_title( $content['topic_id'] ), $this->mail[0]['message'] );
		$this->assertSame( (string) $content['topic_id'], $this->unsubscribe_query()['object_id'] );
		$this->assertSame( 'post', $this->unsubscribe_query()['object_type'] );
		$this->assertSame( array( $subscriber_id ), $this->subscriber_sources['topic_user_ids'] );
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
				'bbp_subscriptions' => true,
				'labels'            => array(
					'name'          => 'Subscription Things',
					'singular_name' => 'Subscription Thing'
				)
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
			$this->assertCount( 1, $this->mail );
			$this->assertSame( 'custom-subscriber@example.org', $this->mail[0]['to'] );
			$this->assertStringContainsString( 'subscribed to the subscription thing: ' . get_term( $term_id )->name, $this->mail[0]['message'] );
			$this->assertSame( (string) $term_id, $this->unsubscribe_query()['object_id'] );
			$this->assertSame( 'term', $this->unsubscribe_query()['object_type'] );
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
