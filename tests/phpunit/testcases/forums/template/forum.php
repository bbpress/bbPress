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

		$category = bbp_get_forum_permalink( $c );

		$forum = bbp_get_forum_permalink( $f );
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
