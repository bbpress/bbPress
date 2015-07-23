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
			'post_parent' => $f,
			'post_date' => $post_date,
			'topic_meta' => array(
				'forum_id' => $f,
			),
		) );

		$link = bbp_get_topic_freshness_link( $t );
		$this->assertSame( '<a href="http://example.org/?topic=topic-1" title="">4 days, 4 hours ago</a>', $link );

		$r1 = $this->factory->reply->create( array(
			'post_parent' => $t,
			'post_date' => $post_date_r1,
			'reply_meta' => array(
				'forum_id' => $f,
				'topic_id' => $t,
			),
		) );

		$link = bbp_get_topic_freshness_link( $t );
		$this->assertSame( '<a href="http://example.org/?topic=topic-1/#post-' . bbp_get_reply_id( $r1 ) . '" title="Reply To: ' . bbp_get_topic_title( $t ) . '">3 days, 8 hours ago</a>', $link );

		$r2 = $this->factory->reply->create( array(
			'post_parent' => $t,
			'post_date' => $post_date_r2,
			'reply_meta' => array(
				'forum_id' => $f,
				'topic_id' => $t,
			),
		) );

		$link = bbp_get_topic_freshness_link( $t );
		$this->assertSame( '<a href="http://example.org/?topic=topic-1/#post-' . bbp_get_reply_id( $r2 ) . '" title="Reply To: ' . bbp_get_topic_title( $t ) . '">2 days, 12 hours ago</a>', $link );
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
