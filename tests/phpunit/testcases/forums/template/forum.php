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
	 */
	public function test_bbp_get_forum() {
		$forum_id = $this->factory->forum->create();
		$topic_id = $this->factory->topic->create( array( 'post_parent' => $forum_id ) );
		$forum    = bbp_get_forum( $forum_id );

		$this->assertInstanceOf( 'WP_Post', $forum );
		$this->assertSame( $forum_id, $forum->ID );
		$this->assertSame( $forum_id, bbp_get_forum( $forum )->ID );
		$this->assertSame( $forum_id, bbp_get_forum( $forum_id, ARRAY_A )['ID'] );
		$this->assertSame( array_values( get_object_vars( $forum ) ), bbp_get_forum( $forum_id, ARRAY_N ) );
		$this->assertNull( bbp_get_forum( $topic_id ) );
		$this->assertNull( bbp_get_forum( 999999 ) );
	}

	/**
	 * @covers ::bbp_forum_permalink
	 * @covers ::bbp_get_forum_permalink
	 */
	public function test_bbp_get_forum_permalink() {

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
	 */
	public function test_bbp_get_forum_archive_title() {
		$title = get_post_type_object( bbp_get_forum_post_type() )->labels->name;

		$this->assertSame( $title, bbp_get_forum_archive_title() );
		$this->assertSame( 'Custom archive', bbp_get_forum_archive_title( 'Custom archive' ) );
		$this->expectOutputString( 'Custom archive' );
		bbp_forum_archive_title( 'Custom archive' );
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
	 * @covers ::bbp_get_forum_content
	 */
	public function test_bbp_get_forum_content_requires_ancestor_password() {
		$parent_forum_id = $this->factory->forum->create( array(
			'post_password' => 'parent-secret',
		) );
		$forum_id = $this->factory->forum->create( array(
			'post_content' => 'Protected forum marker',
			'post_parent'  => $parent_forum_id,
		) );

		$this->assertStringNotContainsString( 'Protected forum marker', bbp_get_forum_content( $forum_id ) );

		require_once ABSPATH . WPINC . '/class-phpass.php';
		$hasher = new PasswordHash( 8, true );
		$cookie = 'wp-postpass_' . COOKIEHASH;
		$old_cookie = isset( $_COOKIE[ $cookie ] ) ? $_COOKIE[ $cookie ] : null;
		$_COOKIE[ $cookie ] = $hasher->HashPassword( 'parent-secret' );

		try {
			$this->assertStringContainsString( 'Protected forum marker', bbp_get_forum_content( $forum_id ) );
		} finally {
			if ( null === $old_cookie ) {
				unset( $_COOKIE[ $cookie ] );
			} else {
				$_COOKIE[ $cookie ] = $old_cookie;
			}
		}
	}

	/**
	 * @covers ::bbp_forum_freshness_link
	 * @covers ::bbp_get_forum_freshness_link
	 */
	public function test_bbp_get_forum_freshness_link() {

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
	 */
	public function test_bbp_get_forum_ancestors() {
		$root_id   = $this->factory->forum->create();
		$parent_id = $this->factory->forum->create( array( 'post_parent' => $root_id ) );
		$child_id  = $this->factory->forum->create( array( 'post_parent' => $parent_id ) );

		$this->assertSame( array(), bbp_get_forum_ancestors( $root_id ) );
		$this->assertSame( array( $parent_id, $root_id ), bbp_get_forum_ancestors( $child_id ) );
		$this->assertSame( array(), bbp_get_forum_ancestors( 999999 ) );
	}

	/**
	 * @covers ::bbp_forum_get_subforums
	 */
	public function test_bbp_forum_get_subforums() {
		$parent_id = $this->factory->forum->create();
		$first_id  = $this->factory->forum->create( array( 'post_parent' => $parent_id, 'post_title' => 'Alpha' ) );
		$second_id = $this->factory->forum->create( array( 'post_parent' => $parent_id, 'post_title' => 'Beta' ) );
		$this->factory->forum->create();
		bbp_update_forum_subforum_count( $parent_id );

		$this->assertSame( array(), bbp_forum_get_subforums( 999999 ) );
		$this->assertSame( array( $first_id, $second_id ), wp_list_pluck( bbp_forum_get_subforums( $parent_id ), 'ID' ) );
		$this->assertSame( array( $second_id ), wp_list_pluck( bbp_forum_get_subforums( array( 'post_parent' => $parent_id, 'posts_per_page' => 1, 'order' => 'DESC' ) ), 'ID' ) );
	}

	/**
	 * @covers ::bbp_list_forums
	 */
	public function test_bbp_list_forums() {
		$parent_id = $this->factory->forum->create();
		$child_id  = $this->factory->forum->create( array( 'post_parent' => $parent_id, 'post_title' => 'Listed child' ) );
		$category_id = $this->factory->forum->create( array( 'post_parent' => $parent_id, 'post_title' => 'Listed category' ) );
		bbp_categorize_forum( $category_id );
		bbp_update_forum_subforum_count( $parent_id );

		$this->assertSame( '', bbp_list_forums( array( 'forum_id' => $child_id, 'echo' => false ) ) );
		$list = bbp_list_forums( array( 'forum_id' => $parent_id, 'echo' => false, 'show_topic_count' => false, 'show_reply_count' => false ) );
		$this->assertStringContainsString( '<ul class="bbp-forums-list">', $list );
		$this->assertStringContainsString( 'Listed child', $list );
		$this->assertStringContainsString( 'Listed category', $list );
		$this->assertStringContainsString( esc_url( bbp_get_forum_permalink( $child_id ) ), $list );
		$this->assertStringNotContainsString( ' (0, 0)', $list );
		$counted_list = bbp_list_forums( array( 'forum_id' => $parent_id, 'echo' => false ) );
		$this->assertStringContainsString( 'Listed child (0, 0)', $counted_list );
		$this->assertStringContainsString( 'Listed category</a>', $counted_list );

		ob_start();
		bbp_list_forums( array( 'forum_id' => $parent_id, 'show_topic_count' => false, 'show_reply_count' => false ) );
		$this->assertSame( $list, ob_get_clean() );
	}

	/**
	 * @covers ::bbp_forum_subscription_link
	 * @covers ::bbp_get_forum_subscription_link
	 */
	public function test_bbp_get_forum_subscription_link() {
		$forum_id = $this->factory->forum->create();
		$user_id  = $this->factory->user->create( array( 'role' => bbp_get_participant_role() ) );
		$this->set_current_user( $user_id );

		$args = array( 'user_id' => $user_id, 'object_id' => $forum_id );
		$this->assertTrue( bbp_is_subscriptions_active() );
		$this->assertFalse( bbp_is_forum_category() );
		$this->assertTrue( current_user_can( 'read_forum', $forum_id ) );
		$this->assertTrue( current_user_can( 'edit_user', $user_id ) );
		$link = bbp_get_forum_subscription_link( $args );
		$this->assertStringContainsString( 'Subscribe', $link );
		$this->assertStringContainsString( 'data-bbp-object-id="' . $forum_id . '"', $link );
		$this->assertStringContainsString( 'data-bbp-object-type="post"', $link );
		$this->assertTrue( bbp_add_user_subscription( $user_id, $forum_id ) );
		$subscribed_link = bbp_get_forum_subscription_link( $args );
		$this->assertStringContainsString( 'Unsubscribe', $subscribed_link );
		$this->assertStringContainsString( 'is-subscribed', $subscribed_link );

		ob_start();
		bbp_forum_subscription_link( $args );
		$this->assertSame( $subscribed_link, ob_get_clean() );

		$this->set_current_user( 0 );
		$this->assertFalse( bbp_get_forum_subscription_link( array( 'object_id' => $forum_id ) ) );
	}

	/**
	 * @covers ::bbp_forum_topics_link
	 * @covers ::bbp_get_forum_topics_link
	 */
	public function test_bbp_get_forum_topics_link() {
		$forum_id = $this->factory->forum->create();
		$this->assertSame( '0 topics', bbp_get_forum_topics_link( $forum_id ) );

		$this->factory->topic->create( array( 'post_parent' => $forum_id, 'topic_meta' => array( 'forum_id' => $forum_id ) ) );
		$this->assertSame( '1 topic', bbp_get_forum_topics_link( $forum_id ) );

		ob_start();
		bbp_forum_topics_link( $forum_id );
		$this->assertSame( '1 topic', ob_get_clean() );
	}

	/**
	 * @covers ::bbp_forum_class
	 * @covers ::bbp_get_forum_class
	 */
	public function test_bbp_get_forum_class() {
		$parent_id = $this->factory->forum->create();
		$child_id  = $this->factory->forum->create( array( 'post_parent' => $parent_id ) );
		bbp_categorize_forum( $child_id );
		bbp_close_forum( $child_id );

		$class = bbp_get_forum_class( $child_id, array( 'custom-forum-class' ) );
		$this->assertStringContainsString( 'custom-forum-class', $class );
		$this->assertStringContainsString( 'bbp-forum-status-closed', $class );
		$this->assertStringContainsString( 'bbp-forum-visibility-publish', $class );
		$this->assertStringContainsString( 'status-category', $class );
		$this->assertStringContainsString( 'bbp-parent-forum-' . $parent_id, $class );

		ob_start();
		bbp_forum_class( $child_id, array( 'custom-forum-class' ) );
		$this->assertSame( $class, ob_get_clean() );
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

	/**
	 * @covers ::bbp_get_single_forum_description
	 */
	public function test_bbp_get_single_forum_description_can_be_disabled() {
		$args                     = array( 'forum_id' => 123 );
		$filtered_args            = array();
		$original_args            = array();
		$filtered_use_description = null;
		$default_description      = null;
		$topic_count_called       = 0;
		$disable                  = function( $use_description, $description_args, $description_original_args ) use ( &$filtered_args, &$original_args, &$filtered_use_description ) {
			$filtered_args            = $description_args;
			$original_args            = $description_original_args;
			$filtered_use_description = $use_description;

			return false;
		};
		$filter_description = function( $description ) use ( &$default_description ) {
			$default_description = $description;

			return 'Filtered description.';
		};
		$count_topics = function( $topic_count ) use ( &$topic_count_called ) {
			++$topic_count_called;

			return $topic_count;
		};

		add_filter( 'bbp_use_single_forum_description', $disable, 10, 3 );
		add_filter( 'bbp_get_single_forum_description', $filter_description );
		add_filter( 'bbp_get_forum_topic_count_int', $count_topics );

		try {
			$description = bbp_get_single_forum_description( $args );
		} finally {
			remove_filter( 'bbp_get_forum_topic_count_int', $count_topics );
			remove_filter( 'bbp_get_single_forum_description', $filter_description );
			remove_filter( 'bbp_use_single_forum_description', $disable, 10 );
		}

		$this->assertSame( 'Filtered description.', $description );
		$this->assertSame( '', $default_description );
		$this->assertTrue( $filtered_use_description );
		$this->assertSame( 123, $filtered_args['forum_id'] );
		$this->assertSame( $args, $original_args );
		$this->assertSame( 0, $topic_count_called );
	}

	/**
	 * @covers ::bbp_get_single_forum_description
	 */
	public function test_bbp_get_single_forum_description_uses_topic_count_for_sentence_plural() {
		$forum_id      = $this->factory->forum->create();
		$topic_id      = $this->factory->topic->create( array( 'post_parent' => $forum_id ) );
		$last_active   = $topic_id;
		$plural_calls  = array();
		$sentence_args = array(
			array( false, true,  true,  'This forum has %1$s, %2$s, and was last updated %3$s by %4$s.' ),
			array( true,  true,  true,  'This category has %1$s, %2$s, and was last updated %3$s by %4$s.' ),
			array( false, true,  false, 'This forum has %1$s, and was last updated %2$s by %3$s.' ),
			array( true,  true,  false, 'This category has %1$s, and was last updated %2$s by %3$s.' ),
			array( false, false, true,  'This forum has %1$s and %2$s.' ),
			array( true,  false, true,  'This category has %1$s and %2$s.' ),
			array( false, false, false, 'This forum has %1$s.' ),
			array( true,  false, false, 'This category has %1$s.' ),
		);
		$sentences     = array_column( $sentence_args, 3 );
		$active_filter = function() use ( &$last_active ) {
			return $last_active;
		};
		$plural_filter = function( $translation, $source_single, $source_plural, $number, $domain ) use ( &$plural_calls, $sentences ) {
			if ( in_array( $source_single, $sentences, true ) && ( 'bbpress' === $domain ) ) {
				$plural_calls[] = array( $source_single, $number );
				$translation    = '<b>Localized form ' . $number . ': ' . $source_single . '</b>';
			}

			return $translation;
		};

		add_filter( 'bbp_get_forum_last_active_id', $active_filter );
		add_filter( 'ngettext', $plural_filter, 10, 5 );

		try {
			foreach ( $sentence_args as $sentence_arg ) {
				list( $is_category, $has_activity, $has_replies ) = $sentence_arg;

				$is_category ? bbp_categorize_forum( $forum_id ) : bbp_normalize_forum( $forum_id );
				$last_active = $has_activity ? $topic_id : 0;
				update_post_meta( $forum_id, '_bbp_total_reply_count', $has_replies ? 1 : 0 );

				foreach ( array( 1, 2 ) as $topic_count ) {
					update_post_meta( $forum_id, '_bbp_total_topic_count', $topic_count );
					$description = bbp_get_single_forum_description( array( 'forum_id' => $forum_id ) );
					$this->assertStringContainsString( '&lt;b&gt;Localized form ' . $topic_count . ':', $description );
				}
			}
		} finally {
			remove_filter( 'ngettext', $plural_filter, 10 );
			remove_filter( 'bbp_get_forum_last_active_id', $active_filter );
		}

		$expected_calls = array();
		foreach ( $sentence_args as $sentence_arg ) {
			$expected_calls[] = array( $sentence_arg[3], 1 );
			$expected_calls[] = array( $sentence_arg[3], 2 );
		}

		$this->assertSame( $expected_calls, $plural_calls );
	}
}
