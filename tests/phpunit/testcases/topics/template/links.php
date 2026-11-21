<?php

/**
 * Tests for the topics component link template functions.
 *
 * @group topics
 * @group template
 * @group links
 */
class BBP_Tests_Topics_Template_Links extends BBP_UnitTestCase {

	/**
	 * @covers ::bbp_topic_subscription_link
	 * @covers ::bbp_get_topic_subscription_link
	 * @todo   Implement test_bbp_get_topic_subscription_link().
	 */
	public function test_bbp_get_topic_subscription_link() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_topic_favorite_link
	 * @covers ::bbp_get_topic_favorite_link
	 * @todo   Implement test_bbp_get_topic_favorite_link().
	 */
	public function test_bbp_get_topic_favorite_link() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_topic_freshness_link
	 * @covers ::bbp_get_topic_freshness_link
	 */
	public function test_bbp_get_topic_freshness_link() {

		if ( is_multisite() ) {
			$this->markTestSkipped( 'Skipping URL tests in multiste for now.' );
		}

		$now = time();
		$post_date    = date( 'Y-m-d H:i:s', $now - 60 * 60 * 100 );
		$post_date_r1 = date( 'Y-m-d H:i:s', $now - 60 * 60 * 80 );
		$post_date_r2 = date( 'Y-m-d H:i:s', $now - 60 * 60 * 60 );

		$f = $this->factory->forum->create();
		$t = $this->factory->topic->create( array(
			'post_title' => 'Topic 1',
			'post_parent' => $f,
			'post_date' => $post_date,
			'topic_meta' => array(
				'forum_id' => $f,
			),
		) );

		$link = bbp_get_topic_freshness_link( $t );
		$this->assertSame( '<a href="http://' . WP_TESTS_DOMAIN . '/?topic=topic-1" title="">4 days, 4 hours ago</a>', $link );

		$r1 = $this->factory->reply->create( array(
			'post_parent' => $t,
			'post_date' => $post_date_r1,
			'reply_meta' => array(
				'forum_id' => $f,
				'topic_id' => $t,
			),
		) );

		$link = bbp_get_topic_freshness_link( $t );
		$this->assertSame( '<a href="http://' . WP_TESTS_DOMAIN . '/?topic=topic-1#post-' . bbp_get_reply_id( $r1 ) . '" title="Reply To: ' . bbp_get_topic_title( $t ) . '">3 days, 8 hours ago</a>', $link );

		$r2 = $this->factory->reply->create( array(
			'post_parent' => $t,
			'post_date' => $post_date_r2,
			'reply_meta' => array(
				'forum_id' => $f,
				'topic_id' => $t,
			),
		) );

		$link = bbp_get_topic_freshness_link( $t );
		$this->assertSame( '<a href="http://' . WP_TESTS_DOMAIN . '/?topic=topic-1#post-' . bbp_get_reply_id( $r2 ) . '" title="Reply To: ' . bbp_get_topic_title( $t ) . '">2 days, 12 hours ago</a>', $link );
	}

	/**
	 * A public topic must not expose its latest reply's protected title.
	 *
	 * @covers ::bbp_get_topic_freshness_link
	 * @covers ::bbp_get_topic_last_active_time
	 */
	public function test_password_protected_reply_topic_freshness_requires_password() {
		$forum_id = $this->factory->forum->create();
		$topic_id = $this->factory->topic->create( array(
			'post_parent' => $forum_id,
			'topic_meta'  => array( 'forum_id' => $forum_id ),
		) );
		$public_reply_id = $this->factory->reply->create( array(
			'post_parent' => $topic_id,
			'post_title'  => 'Public reply marker',
			'reply_meta'  => array( 'forum_id' => $forum_id, 'topic_id' => $topic_id ),
		) );
		$this->assertSame( $public_reply_id, bbp_get_topic_last_reply_id( $topic_id ) );
		$this->assertStringContainsString( 'Public reply marker', bbp_get_topic_freshness_link( $topic_id ) );

		$author_id = $this->factory->user->create( array( 'display_name' => 'Protected author marker' ) );
		$protected_reply_id = $this->factory->reply->create( array(
			'post_parent'   => $topic_id,
			'post_title'    => 'Protected reply marker',
			'post_content'  => 'Protected body marker',
			'post_author'   => $author_id,
			'post_password' => 'reply-secret',
			'reply_meta'    => array( 'forum_id' => $forum_id, 'topic_id' => $topic_id ),
		) );
		$this->assertSame( $protected_reply_id, bbp_get_topic_last_reply_id( $topic_id ) );
		$this->assertSame( $protected_reply_id, bbp_get_topic_last_active_id( $topic_id ) );

		$participant_id = $this->factory->user->create();
		$keymaster_id   = $this->factory->user->create( array( 'role' => 'administrator' ) );
		bbp_set_user_role( $participant_id, bbp_get_participant_role() );
		bbp_set_user_role( $keymaster_id, bbp_get_keymaster_role() );

		foreach ( array( 0, $participant_id, $keymaster_id ) as $user_id ) {
			wp_set_current_user( $user_id );
			$this->assertSame( $protected_reply_id, bbp_get_password_required_id( $protected_reply_id ) );
			$this->assertSame( '-', bbp_get_topic_freshness_link( $topic_id ) );
			$this->assertSame( '', bbp_get_topic_last_active_time( $topic_id ) );
			$this->assertStringNotContainsString( 'Protected body marker', bbp_get_reply_content( $protected_reply_id ) );
			if ( $keymaster_id !== $user_id ) {
				$this->assertStringNotContainsString( 'Protected author marker', bbp_get_author_link( $protected_reply_id ) );
			}
		}

		require_once ABSPATH . WPINC . '/class-phpass.php';
		$hasher = new PasswordHash( 8, true );
		$cookie = 'wp-postpass_' . COOKIEHASH;
		$old_cookie = isset( $_COOKIE[ $cookie ] ) ? $_COOKIE[ $cookie ] : null;
		$_COOKIE[ $cookie ] = $hasher->HashPassword( 'reply-secret' );

		try {
			foreach ( array( 0, $participant_id, $keymaster_id ) as $user_id ) {
				wp_set_current_user( $user_id );
				$this->assertSame( 0, bbp_get_password_required_id( $protected_reply_id ) );
				$this->assertStringContainsString( 'Protected reply marker', bbp_get_topic_freshness_link( $topic_id ) );
				$this->assertNotSame( '', bbp_get_topic_last_active_time( $topic_id ) );
			}
		} finally {
			if ( null === $old_cookie ) {
				unset( $_COOKIE[ $cookie ] );
			} else {
				$_COOKIE[ $cookie ] = $old_cookie;
			}
			wp_set_current_user( 0 );
		}
	}

	/**
	 * A nested forum inherits its parent's password for topic activity.
	 *
	 * @covers ::bbp_get_topic_freshness_link
	 * @covers ::bbp_get_topic_last_active_time
	 */
	public function test_nested_forum_password_hides_topic_freshness() {
		$public_forum_id = $this->factory->forum->create();
		$protected_forum_id = $this->factory->forum->create( array(
			'post_parent'   => $public_forum_id,
			'post_password' => 'forum-secret',
		) );
		$nested_forum_id = $this->factory->forum->create( array( 'post_parent' => $protected_forum_id ) );
		$topic_id = $this->factory->topic->create( array(
			'post_parent' => $nested_forum_id,
			'topic_meta'  => array( 'forum_id' => $nested_forum_id ),
		) );
		$reply_id = $this->factory->reply->create( array(
			'post_parent' => $topic_id,
			'post_title'  => 'Nested reply marker',
			'reply_meta'  => array( 'forum_id' => $nested_forum_id, 'topic_id' => $topic_id ),
		) );

		wp_set_current_user( 0 );
		$this->assertSame( $protected_forum_id, bbp_get_password_required_id( $reply_id ) );
		$this->assertSame( '-', bbp_get_topic_freshness_link( $topic_id ) );
		$this->assertSame( '', bbp_get_topic_last_active_time( $topic_id ) );

		require_once ABSPATH . WPINC . '/class-phpass.php';
		$hasher = new PasswordHash( 8, true );
		$cookie = 'wp-postpass_' . COOKIEHASH;
		$old_cookie = isset( $_COOKIE[ $cookie ] ) ? $_COOKIE[ $cookie ] : null;
		$_COOKIE[ $cookie ] = $hasher->HashPassword( 'forum-secret' );

		try {
			$this->assertStringContainsString( 'Nested reply marker', bbp_get_topic_freshness_link( $topic_id ) );
			$this->assertNotSame( '', bbp_get_topic_last_active_time( $topic_id ) );
		} finally {
			if ( null === $old_cookie ) {
				unset( $_COOKIE[ $cookie ] );
			} else {
				$_COOKIE[ $cookie ] = $old_cookie;
			}
		}
	}

	/**
	 * Stale topic metadata cannot select a protected reply's title or time.
	 *
	 * @covers ::bbp_get_topic_freshness_link
	 * @covers ::bbp_get_topic_last_active_time
	 */
	public function test_topic_freshness_checks_both_activity_ids() {
		$forum_id = $this->factory->forum->create();
		$topic_id = $this->factory->topic->create( array(
			'post_parent' => $forum_id,
			'topic_meta'  => array( 'forum_id' => $forum_id ),
		) );
		$public_reply_id = $this->factory->reply->create( array(
			'post_parent' => $topic_id,
			'reply_meta'  => array( 'forum_id' => $forum_id, 'topic_id' => $topic_id ),
		) );
		$protected_reply_id = $this->factory->reply->create( array(
			'post_parent'   => $topic_id,
			'post_title'    => 'Protected reply marker',
			'post_password' => 'reply-secret',
			'reply_meta'    => array( 'forum_id' => $forum_id, 'topic_id' => $topic_id ),
		) );

		wp_set_current_user( 0 );
		update_post_meta( $topic_id, '_bbp_last_active_id', $public_reply_id );
		$this->assertSame( '-', bbp_get_topic_freshness_link( $topic_id ) );
		$this->assertSame( '', bbp_get_topic_last_active_time( $topic_id ) );

		update_post_meta( $topic_id, '_bbp_last_active_id', $protected_reply_id );
		update_post_meta( $topic_id, '_bbp_last_reply_id', $public_reply_id );
		$this->assertSame( '-', bbp_get_topic_freshness_link( $topic_id ) );
		$this->assertSame( '', bbp_get_topic_last_active_time( $topic_id ) );
	}

	/**
	 * @covers ::bbp_get_topic_freshness_link
	 */
	public function test_bbp_get_topic_freshness_link_with_unpublished_replies() {

		if ( is_multisite() ) {
			$this->markTestSkipped( 'Skipping URL tests in multiste for now.' );
		}

		$now = time();
		$post_date    = date( 'Y-m-d H:i:s', $now - 60 * 60 * 20 ); // 2o hours ago
		$post_date_r1 = date( 'Y-m-d H:i:s', $now - 60 * 60 * 18 ); // 18 hours ago
		$post_date_r2 = date( 'Y-m-d H:i:s', $now - 60 * 60 * 16 ); // 16 hours ago
		$post_date_r3 = date( 'Y-m-d H:i:s', $now - 60 * 60 * 14 ); // 14 hours ago
		$post_date_r4 = date( 'Y-m-d H:i:s', $now - 60 * 60 * 12 ); // 12 hours ago
		$post_date_r5 = date( 'Y-m-d H:i:s', $now - 60 * 60 * 10 ); // 1o hours ago

		$f = $this->factory->forum->create();
		$t = $this->factory->topic->create( array(
			'post_title' => 'Topic 1',
			'post_parent' => $f,
			'post_date' => $post_date,
			'topic_meta' => array(
				'forum_id' => $f,
			),
		) );

		$link = bbp_get_topic_freshness_link( $t );
		$this->assertSame( '<a href="http://' . WP_TESTS_DOMAIN . '/?topic=topic-1" title="">20 hours ago</a>', $link );

		$r1 = $this->factory->reply->create( array(
			'post_parent' => $t,
			'post_date' => $post_date_r1,
			'reply_meta' => array(
				'forum_id' => $f,
				'topic_id' => $t,
			),
		) );

		$link = bbp_get_topic_freshness_link( $t );
		$this->assertSame( '<a href="http://' . WP_TESTS_DOMAIN . '/?topic=topic-1#post-' . bbp_get_reply_id( $r1 ) . '" title="Reply To: ' . bbp_get_topic_title( $t ) . '">18 hours ago</a>', $link );

		$r2 = $this->factory->reply->create( array(
			'post_parent' => $t,
			'post_date' => $post_date_r2,
			'reply_meta' => array(
				'forum_id' => $f,
				'topic_id' => $t,
			),
		) );

		$link = bbp_get_topic_freshness_link( $t );
		$this->assertSame( '<a href="http://' . WP_TESTS_DOMAIN . '/?topic=topic-1#post-' . bbp_get_reply_id( $r2 ) . '" title="Reply To: ' . bbp_get_topic_title( $t ) . '">16 hours ago</a>', $link );

		bbp_spam_reply( $r2 );

		$link = bbp_get_topic_freshness_link( $t );
		$this->assertSame( '<a href="http://' . WP_TESTS_DOMAIN . '/?topic=topic-1#post-' . bbp_get_reply_id( $r1 ) . '" title="Reply To: ' . bbp_get_topic_title( $t ) . '">18 hours ago</a>', $link );

		$r3 = $this->factory->reply->create( array(
			'post_parent' => $t,
			'post_date' => $post_date_r3,
			'reply_meta' => array(
				'forum_id' => $f,
				'topic_id' => $t,
			),
		) );

		$link = bbp_get_topic_freshness_link( $t );
		$this->assertSame( '<a href="http://' . WP_TESTS_DOMAIN . '/?topic=topic-1#post-' . bbp_get_reply_id( $r3 ) . '" title="Reply To: ' . bbp_get_topic_title( $t ) . '">14 hours ago</a>', $link );

		// Todo: Use bbp_trash_reply() and not wp_trash_post()
		wp_trash_post( $r3 );

		$link = bbp_get_topic_freshness_link( $t );
		$this->assertSame( '<a href="http://' . WP_TESTS_DOMAIN . '/?topic=topic-1#post-' . bbp_get_reply_id( $r1 ) . '" title="Reply To: ' . bbp_get_topic_title( $t ) . '">18 hours ago</a>', $link );

		$r4 = $this->factory->reply->create( array(
			'post_parent' => $t,
			'post_date' => $post_date_r4,
			'reply_meta' => array(
				'forum_id' => $f,
				'topic_id' => $t,
			),
		) );

		$link = bbp_get_topic_freshness_link( $t );
		$this->assertSame( '<a href="http://' . WP_TESTS_DOMAIN . '/?topic=topic-1#post-' . bbp_get_reply_id( $r4 ) . '" title="Reply To: ' . bbp_get_topic_title( $t ) . '">12 hours ago</a>', $link );

		bbp_unapprove_reply( $r4 );

		$link = bbp_get_topic_freshness_link( $t );
		$this->assertSame( '<a href="http://' . WP_TESTS_DOMAIN . '/?topic=topic-1#post-' . bbp_get_reply_id( $r1 ) . '" title="Reply To: ' . bbp_get_topic_title( $t ) . '">18 hours ago</a>', $link );

		bbp_unspam_reply( $r2 );

		$link = bbp_get_topic_freshness_link( $t );
		$this->assertSame( '<a href="http://' . WP_TESTS_DOMAIN . '/?topic=topic-1#post-' . bbp_get_reply_id( $r2 ) . '" title="Reply To: ' . bbp_get_topic_title( $t ) . '">16 hours ago</a>', $link );

		// Todo: Use bbp_untrash_reply() and not wp_untrash_post()
		wp_untrash_post( $r3 );

		$link = bbp_get_topic_freshness_link( $t );
		$this->assertSame( '<a href="http://' . WP_TESTS_DOMAIN . '/?topic=topic-1#post-' . bbp_get_reply_id( $r3 ) . '" title="Reply To: ' . bbp_get_topic_title( $t ) . '">14 hours ago</a>', $link );

		bbp_approve_reply( $r4 );

		$link = bbp_get_topic_freshness_link( $t );
		$this->assertSame( '<a href="http://' . WP_TESTS_DOMAIN . '/?topic=topic-1#post-' . bbp_get_reply_id( $r4 ) . '" title="Reply To: ' . bbp_get_topic_title( $t ) . '">12 hours ago</a>', $link );

		$r5 = $this->factory->reply->create( array(
			'post_parent' => $t,
			'post_date' => $post_date_r5,
			'reply_meta' => array(
				'forum_id' => $f,
				'topic_id' => $t,
			),
		) );

		$link = bbp_get_topic_freshness_link( $t );
		$this->assertSame( '<a href="http://' . WP_TESTS_DOMAIN . '/?topic=topic-1#post-' . bbp_get_reply_id( $r5 ) . '" title="Reply To: ' . bbp_get_topic_title( $t ) . '">10 hours ago</a>', $link );
	}

	/**
	 * @covers ::bbp_topic_replies_link
	 * @covers ::bbp_get_topic_replies_link
	 * @todo   Implement test_bbp_get_topic_replies_link().
	 */
	public function test_bbp_get_topic_replies_link() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_topic_admin_links
	 * @covers ::bbp_get_topic_admin_links
	 * @todo   Implement test_bbp_get_topic_admin_links().
	 */
	public function test_bbp_get_topic_admin_links() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_topic_edit_link
	 * @covers ::bbp_get_topic_edit_link
	 * @todo   Implement test_bbp_get_topic_edit_link().
	 */
	public function test_bbp_get_topic_edit_link() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_topic_edit_url
	 * @covers ::bbp_get_topic_edit_url
	 * @todo   Implement test_bbp_get_topic_edit_url().
	 */
	public function test_bbp_get_topic_edit_url() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_topic_trash_link
	 * @covers ::bbp_get_topic_trash_link
	 * @todo   Implement test_bbp_get_topic_trash_link().
	 */
	public function test_bbp_get_topic_trash_link() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_topic_close_link
	 * @covers ::bbp_get_topic_close_link
	 * @todo   Implement test_bbp_get_topic_close_link().
	 */
	public function test_bbp_get_topic_close_link() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_topic_approve_link
	 * @covers ::bbp_get_topic_approve_link
	 * @todo   Implement test_bbp_get_topic_approve_link().
	 */
	public function test_bbp_get_topic_approve_link() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_topic_stick_link
	 * @covers ::bbp_get_topic_stick_link
	 * @todo   Implement test_bbp_get_topic_stick_link().
	 */
	public function test_bbp_get_topic_stick_link() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_topic_merge_link
	 * @covers ::bbp_get_topic_merge_link
	 * @todo   Implement test_bbp_get_topic_merge_link().
	 */
	public function test_bbp_get_topic_merge_link() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_topic_spam_link
	 * @covers ::bbp_get_topic_spam_link
	 * @todo   Implement test_bbp_get_topic_spam_link().
	 */
	public function test_bbp_get_topic_spam_link() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_topic_reply_link
	 * @covers ::bbp_get_topic_reply_link
	 * @todo   Implement test_bbp_get_topic_reply_link().
	 */
	public function test_bbp_get_topic_reply_link() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_forum_pagination_links
	 * @covers ::bbp_get_forum_pagination_links
	 * @todo   Implement test_bbp_get_forum_pagination_links().
	 */
	public function test_bbp_get_forum_pagination_links() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}
}
