<?php

/**
 * Tests for the `bbp_*_forum_*()` template functions.
 *
 * @group forums
 * @group template
 * @group forum
 */
class BBP_Tests_Forums_Template_Forum extends BBP_UnitTestCase {

	/**
	 * @covers ::bbp_forum_id
	 * @covers ::bbp_get_forum_id
	 */
	public function test_bbp_get_forum_id() {
		$f = $this->factory->forum->create();

		$forum_id = bbp_get_forum_id( $f );
		$this->assertSame( $f, $forum_id );
	}

	/**
	 * @covers ::bbp_get_forum
	 * @todo   Implement test_bbp_get_forum().
	 */
	public function test_bbp_get_forum() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_forum_permalink
	 * @covers ::bbp_get_forum_permalink
	 */
	public function test_bbp_get_forum_permalink() {

		if ( is_multisite() ) {
			$this->markTestSkipped( 'Skipping URL tests in multiste for now.' );
		}

		// Public category.
		$c = $this->factory->forum->create( array(
			'post_title' => 'Public Category',
		) );

		$category = bbp_get_forum_permalink( $c );
		$this->assertSame( 'http://' . WP_TESTS_DOMAIN . '/?forum=public-category', $category );

		// Public forum of public category.
		$f = $this->factory->forum->create( array(
			'post_title' => 'Public Forum',
			'post_parent' => $c,
		) );

		$forum_permalink = bbp_get_forum_permalink( $f );
		$this->expectOutputString( $forum_permalink );
		bbp_forum_permalink( $f );

		$forum = bbp_get_forum_permalink( $f );
		$this->assertSame( 'http://' . WP_TESTS_DOMAIN . '/?forum=public-category/public-forum', $forum );

		// Private category.
		$c = $this->factory->forum->create( array(
			'post_title' => 'Private Category',
		) );
		bbp_privatize_forum( $c );

		$category = bbp_get_forum_permalink( $c );
		$this->assertSame( 'http://' . WP_TESTS_DOMAIN . '/?forum=private-category', $category );

		// Private forum of private category.
		$f = $this->factory->forum->create( array(
			'post_title' => 'Private Forum',
			'post_parent' => $c,
		) );

		bbp_privatize_forum( $c );
		$forum = bbp_get_forum_permalink( $f );
		$this->assertSame( 'http://' . WP_TESTS_DOMAIN . '/?forum=private-category/private-forum', $forum );

		// Hidden category.
		$c = $this->factory->forum->create( array(
			'post_title' => 'Hidden Category',
		) );

		bbp_hide_forum( $c );
		$category = bbp_get_forum_permalink( $c );
		$this->assertSame( 'http://' . WP_TESTS_DOMAIN . '/?forum=hidden-category', $category );

		// Hidden forum of hidden category.
		$f = $this->factory->forum->create( array(
			'post_title' => 'Hidden Forum',
			'post_parent' => $c,
		) );

		$forum = bbp_get_forum_permalink( $f );
		$this->assertSame( 'http://' . WP_TESTS_DOMAIN . '/?forum=hidden-category/hidden-forum', $forum );
	}

	/**
	 * @covers ::bbp_forum_title
	 * @covers ::bbp_get_forum_title
	 */
	public function test_bbp_get_forum_title() {
		$f = $this->factory->forum->create( array(
			'post_title' => 'Forum 1',
		) );

		$forum = bbp_get_forum_title( $f );
		$this->assertSame( 'Forum 1', $forum );
	}

	/**
	 * @covers ::bbp_forum_archive_title
	 * @covers ::bbp_get_forum_archive_title
	 * @todo   Implement test_bbp_forum_archive_title().
	 * @todo   Implement test_bbp_get_forum_archive_title().
	 */
	public function test_bbp_get_forum_archive_title() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_forum_content
	 * @covers ::bbp_get_forum_content
	 */
	public function test_bbp_get_forum_content() {
		$f = $this->factory->forum->create( array(
			'post_content' => 'Content of Forum 1',
		) );

		$forum = bbp_get_forum_content( $f );
		$this->assertSame( 'Content of Forum 1', $forum );
	}

	/**
	 * @covers ::bbp_forum_freshness_link
	 * @covers ::bbp_get_forum_freshness_link
	 */
	public function test_bbp_get_forum_freshness_link() {

		if ( is_multisite() ) {
			$this->markTestSkipped( 'Skipping URL tests in multiste for now.' );
		}

		$now = time();
		$post_date = date( 'Y-m-d H:i:s', $now - 60*60*100 );

		$f = $this->factory->forum->create();

		$fresh_link = bbp_get_forum_freshness_link( $f );
		$this->assertSame( 'No Topics', $fresh_link );

		$t = $this->factory->topic->create( array(
			'post_title' => 'Topic 1',
			'post_parent' => $f,
			'post_date' => $post_date,
			'topic_meta' => array(
				'forum_id' => $f,
			),
		) );

		$fresh_link = bbp_get_forum_freshness_link( $f );
		$this->assertSame( '<a href="http://' . WP_TESTS_DOMAIN . '/?topic=topic-1" title="Topic 1">4 days, 4 hours ago</a>', $fresh_link );
	}

	/**
	 * A newly pending topic must not replace the public forum's last activity.
	 *
	 * @covers ::bbp_update_topic_walker
	 */
	public function test_pending_topic_does_not_replace_public_forum_freshness() {
		$forum_id = $this->factory->forum->create();
		$public_id = $this->factory->topic->create( array(
			'post_parent' => $forum_id,
			'post_date'   => date( 'Y-m-d H:i:s', time() - HOUR_IN_SECONDS ),
			'post_title'  => 'Public topic',
			'topic_meta'  => array( 'forum_id' => $forum_id ),
		) );
		$pending_id = $this->factory->topic->create( array(
			'post_parent' => $forum_id,
			'post_status' => bbp_get_pending_status_id(),
			'post_title'  => 'Pending topic',
			'topic_meta'  => array( 'forum_id' => $forum_id ),
		) );

		$this->assertSame( bbp_get_pending_status_id(), get_post_status( $pending_id ) );
		$this->assertSame( 1, bbp_get_forum_topic_count( $forum_id, false, true ) );
		$this->assertSame( 1, bbp_get_forum_topic_count_hidden( $forum_id, false, true ) );
		$this->assertSame( $public_id, bbp_get_forum_last_topic_id( $forum_id ) );
		$this->assertSame( $public_id, bbp_get_forum_last_active_id( $forum_id ) );

		wp_set_current_user( 0 );
		$this->assertStringContainsString( 'Public topic', bbp_get_forum_freshness_link( $forum_id ) );
		$this->assertStringNotContainsString( 'Pending topic', bbp_get_forum_freshness_link( $forum_id ) );

		bbp_approve_topic( $pending_id );
		$this->assertSame( 2, bbp_get_forum_topic_count( $forum_id, false, true ) );
		$this->assertSame( 0, bbp_get_forum_topic_count_hidden( $forum_id, false, true ) );
		$this->assertSame( $pending_id, bbp_get_forum_last_topic_id( $forum_id ) );
		$this->assertStringContainsString( 'Pending topic', bbp_get_forum_freshness_link( $forum_id ) );
	}

	/**
	 * A newly spammed reply must not replace public topic or forum activity.
	 *
	 * @covers ::bbp_update_reply_walker
	 */
	public function test_spam_reply_does_not_replace_public_forum_freshness() {
		$forum_id = $this->factory->forum->create();
		$topic_id = $this->factory->topic->create( array(
			'post_parent' => $forum_id,
			'topic_meta'  => array( 'forum_id' => $forum_id ),
		) );
		$public_id = $this->factory->reply->create( array(
			'post_parent' => $topic_id,
			'post_title'  => 'Public reply',
			'reply_meta'  => array( 'forum_id' => $forum_id, 'topic_id' => $topic_id ),
		) );
		$spam_id = $this->factory->reply->create( array(
			'post_parent' => $topic_id,
			'post_status' => bbp_get_spam_status_id(),
			'post_title'  => 'Spam reply',
			'reply_meta'  => array( 'forum_id' => $forum_id, 'topic_id' => $topic_id ),
		) );

		$this->assertSame( bbp_get_spam_status_id(), get_post_status( $spam_id ) );
		$this->assertSame( 1, bbp_get_topic_reply_count( $topic_id, true ) );
		$this->assertSame( 1, bbp_get_topic_reply_count_hidden( $topic_id, true ) );
		$this->assertSame( 1, bbp_get_forum_reply_count( $forum_id, false, true ) );
		$this->assertSame( 1, bbp_get_forum_reply_count_hidden( $forum_id, false, true ) );
		$this->assertSame( $public_id, bbp_get_topic_last_reply_id( $topic_id ) );
		$this->assertSame( $public_id, bbp_get_forum_last_reply_id( $forum_id ) );
		$this->assertSame( $public_id, bbp_get_forum_last_active_id( $forum_id ) );

		wp_set_current_user( 0 );
		$this->assertStringContainsString( 'Public reply', bbp_get_forum_freshness_link( $forum_id ) );
		$this->assertStringNotContainsString( 'Spam reply', bbp_get_forum_freshness_link( $forum_id ) );

		bbp_unspam_reply( $spam_id );
		$this->assertSame( 2, bbp_get_topic_reply_count( $topic_id, true ) );
		$this->assertSame( 0, bbp_get_topic_reply_count_hidden( $topic_id, true ) );
		$this->assertSame( 2, bbp_get_forum_reply_count( $forum_id, false, true ) );
		$this->assertSame( 0, bbp_get_forum_reply_count_hidden( $forum_id, false, true ) );
		$this->assertSame( $spam_id, bbp_get_forum_last_reply_id( $forum_id ) );
		$this->assertStringContainsString( 'Spam reply', bbp_get_forum_freshness_link( $forum_id ) );
	}

	/**
	 * Non-public author links follow per-forum moderation permissions.
	 *
	 * @covers ::bbp_suppress_private_author_link
	 */
	public function test_non_public_author_links_follow_forum_moderation() {
		$moderated_forum = $this->factory->forum->create();
		$other_forum     = $this->factory->forum->create();
		$author_id       = $this->factory->user->create( array( 'display_name' => 'Pending author sentinel' ) );
		$moderator_id    = $this->factory->user->create();
		bbp_set_user_role( $moderator_id, bbp_get_participant_role() );
		bbp_add_moderator( $moderated_forum, $moderator_id );

		$moderated_topic = $this->factory->topic->create( array(
			'post_parent' => $moderated_forum,
			'post_status' => bbp_get_pending_status_id(),
			'post_author' => $author_id,
			'topic_meta'  => array( 'forum_id' => $moderated_forum ),
		) );
		$other_topic = $this->factory->topic->create( array(
			'post_parent' => $other_forum,
			'post_status' => bbp_get_pending_status_id(),
			'post_author' => $author_id,
			'topic_meta'  => array( 'forum_id' => $other_forum ),
		) );
		$public_topic = $this->factory->topic->create( array(
			'post_parent' => $moderated_forum,
			'topic_meta'  => array( 'forum_id' => $moderated_forum ),
		) );
		$spam_reply = $this->factory->reply->create( array(
			'post_parent' => $public_topic,
			'post_status' => bbp_get_spam_status_id(),
			'post_author' => $author_id,
			'reply_meta'  => array( 'forum_id' => $moderated_forum, 'topic_id' => $public_topic ),
		) );
		$reply_in_pending_topic = $this->factory->reply->create( array(
			'post_parent' => $moderated_topic,
			'post_author' => $author_id,
			'reply_meta'  => array( 'forum_id' => $moderated_forum, 'topic_id' => $moderated_topic ),
		) );

		wp_set_current_user( 0 );
		$this->assertSame( '-', bbp_get_author_link( $moderated_topic ) );
		$this->assertSame( '-', bbp_get_author_link( $spam_reply ) );
		$this->assertSame( '-', bbp_get_author_link( $reply_in_pending_topic ) );

		wp_set_current_user( $moderator_id );
		$this->assertFalse( current_user_can( 'moderate' ) );
		$this->assertTrue( current_user_can( 'moderate', $moderated_topic ) );
		$this->assertTrue( current_user_can( 'moderate', $spam_reply ) );
		$this->assertFalse( current_user_can( 'moderate', $other_topic ) );
		$this->assertStringContainsString( 'Pending author sentinel', bbp_get_author_link( $moderated_topic ) );
		$this->assertStringContainsString( 'Pending author sentinel', bbp_get_reply_author_link( $spam_reply ) );
		$this->assertStringContainsString( 'Pending author sentinel', bbp_get_author_link( $reply_in_pending_topic ) );
		$this->assertSame( '-', bbp_get_author_link( $other_topic ) );
		$this->assertSame( '-', bbp_get_author_link( 0 ) );
	}

	/**
	 * Suppress non-public activity stored by an earlier bbPress version.
	 *
	 * @covers ::bbp_is_forum_activity_public
	 * @covers ::bbp_get_forum_freshness_link
	 */
	public function test_stale_non_public_forum_activity_is_not_rendered() {
		$this->assertFalse( bbp_is_forum_activity_public( 0 ) );

		$forum_id = $this->factory->forum->create();
		$author_id = $this->factory->user->create( array( 'display_name' => 'Pending author sentinel' ) );
		$topic_id = $this->factory->topic->create( array(
			'post_parent' => $forum_id,
			'post_status' => bbp_get_pending_status_id(),
			'post_title'  => 'Pending title sentinel',
			'post_author' => $author_id,
			'topic_meta'  => array( 'forum_id' => $forum_id ),
		) );
		update_post_meta( $forum_id, '_bbp_last_topic_id', $topic_id );
		update_post_meta( $forum_id, '_bbp_last_active_id', $topic_id );

		wp_set_current_user( 0 );
		$this->assertSame( '-', bbp_get_author_link( array( 'post_id' => 0, 'size' => 14 ) ) );
		$this->assertSame( '-', bbp_get_author_link( 0 ) );
		$this->assertSame( '-', bbp_get_author_link( array( 'post_id' => $topic_id, 'size' => 14 ) ) );
		$this->assertSame( '-', bbp_get_topic_author_link( $topic_id ) );
		$this->assertSame( '-', bbp_get_forum_freshness_link( $forum_id ) );
		$this->assertSame( '', bbp_get_forum_last_active_time( $forum_id ) );
		$this->assertStringNotContainsString( 'Pending author sentinel', bbp_get_single_forum_description( array( 'forum_id' => $forum_id ) ) );

		bbp_approve_topic( $topic_id );
		$reply_id = $this->factory->reply->create( array(
			'post_parent' => $topic_id,
			'post_status' => bbp_get_spam_status_id(),
			'post_title'  => 'Spam title sentinel',
			'reply_meta'  => array( 'forum_id' => $forum_id, 'topic_id' => $topic_id ),
		) );
		update_post_meta( $forum_id, '_bbp_last_reply_id', $reply_id );
		update_post_meta( $forum_id, '_bbp_last_active_id', $reply_id );

		$this->assertSame( '-', bbp_get_author_link( array( 'post_id' => $reply_id, 'size' => 14 ) ) );
		$this->assertSame( '-', bbp_get_reply_author_link( $reply_id ) );
		$this->assertSame( '-', bbp_get_forum_freshness_link( $forum_id ) );
		$this->assertSame( '', bbp_get_forum_last_active_time( $forum_id ) );

		delete_post_meta( $forum_id, '_bbp_last_active_id' );
		delete_post_meta( $forum_id, '_bbp_last_active_time' );
		$this->assertSame( '-', bbp_get_forum_freshness_link( $forum_id ) );
		$this->assertSame( '', bbp_get_forum_last_active_time( $forum_id ) );
	}

	/**
	 * @covers ::bbp_get_forum_freshness_link
	 */
	public function test_bbp_get_forum_freshness_link_with_unpublished_replies() {

		if ( is_multisite() ) {
			$this->markTestSkipped( 'Skipping URL tests in multiste for now.' );
		}

		$now = time();
		$post_date_t1 = date( 'Y-m-d H:i:s', $now - 60 * 60 * 18 ); // 18 hours ago
		$post_date_t2 = date( 'Y-m-d H:i:s', $now - 60 * 60 * 16 ); // 16 hours ago
		$post_date_t3 = date( 'Y-m-d H:i:s', $now - 60 * 60 * 14 ); // 14 hours ago
		$post_date_t4 = date( 'Y-m-d H:i:s', $now - 60 * 60 * 12 ); // 12 hours ago
		$post_date_t5 = date( 'Y-m-d H:i:s', $now - 60 * 60 * 10 ); // 1o hours ago

		$f = $this->factory->forum->create();

		$link = bbp_get_forum_freshness_link( $f );
		$this->assertSame( 'No Topics', $link );

		$t1 = $this->factory->topic->create( array(
			'post_title' => 'Topic 1',
			'post_parent' => $f,
			'post_date' => $post_date_t1,
			'topic_meta' => array(
				'forum_id' => $f,
			),
		) );

		$link = bbp_get_forum_freshness_link( $f );
		$this->assertSame( '<a href="http://' . WP_TESTS_DOMAIN . '/?topic=topic-1" title="Topic 1">18 hours ago</a>', $link );

		$t2 = $this->factory->topic->create( array(
			'post_title' => 'Topic 2',
			'post_parent' => $f,
			'post_date' => $post_date_t2,
			'topic_meta' => array(
				'forum_id' => $f,
			),
		) );

		$link = bbp_get_forum_freshness_link( $f );
		$this->assertSame( '<a href="http://' . WP_TESTS_DOMAIN . '/?topic=topic-2" title="Topic 2">16 hours ago</a>', $link );

		bbp_spam_topic( $t2 );

		$link = bbp_get_forum_freshness_link( $f );
		$this->assertSame( '<a href="http://' . WP_TESTS_DOMAIN . '/?topic=topic-1" title="Topic 1">18 hours ago</a>', $link );

		$t3 = $this->factory->topic->create( array(
			'post_title' => 'Topic 3',
			'post_parent' => $f,
			'post_date' => $post_date_t3,
			'topic_meta' => array(
				'forum_id' => $f,
			),
		) );

		$link = bbp_get_forum_freshness_link( $f );
		$this->assertSame( '<a href="http://' . WP_TESTS_DOMAIN . '/?topic=topic-3" title="Topic 3">14 hours ago</a>', $link );

		// Todo: Use bbp_trash_topic() and not wp_trash_post()
		wp_trash_post( $t3 );

		$link = bbp_get_forum_freshness_link( $f );
		$this->assertSame( '<a href="http://' . WP_TESTS_DOMAIN . '/?topic=topic-1" title="Topic 1">18 hours ago</a>', $link );

		$t4 = $this->factory->topic->create( array(
			'post_title' => 'Topic 4',
			'post_parent' => $f,
			'post_date' => $post_date_t4,
			'topic_meta' => array(
				'forum_id' => $f,
			),
		) );

		$link = bbp_get_forum_freshness_link( $f );
		$this->assertSame( '<a href="http://' . WP_TESTS_DOMAIN . '/?topic=topic-4" title="Topic 4">12 hours ago</a>', $link );

		bbp_unapprove_topic( $t4 );

		$link = bbp_get_forum_freshness_link( $f );
		$this->assertSame( '<a href="http://' . WP_TESTS_DOMAIN . '/?topic=topic-1" title="Topic 1">18 hours ago</a>', $link );

		bbp_unspam_topic( $t2 );

		$link = bbp_get_forum_freshness_link( $f );
		$this->assertSame( '<a href="http://' . WP_TESTS_DOMAIN . '/?topic=topic-2" title="Topic 2">16 hours ago</a>', $link );

		// Todo: Use bbp_untrash_topic() and not wp_untrash_post()
		wp_untrash_post( $t3 );

		$link = bbp_get_forum_freshness_link( $f );
		$this->assertSame( '<a href="http://' . WP_TESTS_DOMAIN . '/?topic=topic-3" title="Topic 3">14 hours ago</a>', $link );

		bbp_approve_topic( $t4 );

		$link = bbp_get_forum_freshness_link( $f );
		$this->assertSame( '<a href="http://' . WP_TESTS_DOMAIN . '/?topic=topic-4" title="Topic 4">12 hours ago</a>', $link );

		$t5 = $this->factory->topic->create( array(
			'post_title' => 'Topic 5',
			'post_parent' => $f,
			'post_date' => $post_date_t5,
			'topic_meta' => array(
				'forum_id' => $f,
			),
		) );

		$link = bbp_get_forum_freshness_link( $f );
		$this->assertSame( '<a href="http://' . WP_TESTS_DOMAIN . '/?topic=topic-5" title="Topic 5">10 hours ago</a>', $link );
	}

	/**
	 * @covers ::bbp_forum_parent_id
	 * @covers ::bbp_get_forum_parent_id
	 */
	public function test_bbp_get_forum_parent_id() {
		$f1 = $this->factory->forum->create();

		$forum_id = bbp_get_forum_parent_id( $f1 );
		$this->assertSame( 0, $forum_id );

		$f2 = $this->factory->forum->create( array(
			'post_parent' => $f1,
		) );

		$forum_id = bbp_get_forum_parent_id( $f2 );
		$this->assertSame( $f1, $forum_id );
	}

	/**
	 * @covers ::bbp_get_forum_ancestors
	 * @todo   Implement test_bbp_get_forum_ancestors().
	 */
	public function test_bbp_get_forum_ancestors() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_forum_get_subforums
	 * @todo   Implement test_bbp_forum_get_subforums().
	 */
	public function test_bbp_forum_get_subforums() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_list_forums
	 * @todo   Implement test_bbp_list_forums().
	 */
	public function test_bbp_list_forums() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_forum_subscription_link
	 * @covers ::bbp_get_forum_subscription_link
	 * @todo   Implement test_bbp_get_forum_subscription_link().
	 */
	public function test_bbp_get_forum_subscription_link() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_forum_topics_link
	 * @covers ::bbp_get_forum_topics_link
	 * @todo   Implement test_bbp_get_forum_topics_link().
	 */
	public function test_bbp_get_forum_topics_link() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_forum_class
	 * @covers ::bbp_get_forum_class
	 * @todo   Implement test_bbp_get_forum_class().
	 */
	public function test_bbp_get_forum_class() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_single_forum_description
	 * @covers ::bbp_get_single_forum_description
	 */
	public function test_bbp_get_single_forum_description() {
		$f = $this->factory->forum->create();

		$forum = bbp_get_single_forum_description( $f );
		$this->assertSame( '<div class="bbp-template-notice info"><ul><li class="bbp-forum-description">This forum is empty.</li></ul></div>', $forum );
	}
}
