<?php

/**
 * Tests for the `bbp_*_reply_*()` template functions.
 *
 * @group replies
 * @group template
 * @group reply
 */
class BBP_Tests_Replies_Template_Reply extends BBP_UnitTestCase {

	/**
	 * @covers ::bbp_reply_id
	 * @covers ::bbp_get_reply_id
	 * @todo   Implement test_bbp_get_reply_id().
	 */
	public function test_bbp_get_reply_id() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_get_reply
	 * @todo   Implement test_bbp_get_reply().
	 */
	public function test_bbp_get_reply() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_reply_permalink
	 * @covers ::bbp_get_reply_permalink
	 * @todo   Implement test_bbp_get_reply_permalink().
	 */
	public function test_bbp_get_reply_permalink() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_reply_url
	 * @covers ::bbp_get_reply_url
	 * @todo   Implement test_bbp_get_reply_url().
	 */
	public function test_bbp_get_reply_url() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_reply_title
	 * @covers ::bbp_get_reply_title
	 * @todo   Implement test_bbp_get_reply_title().
	 */
	public function test_bbp_get_reply_title() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_get_reply_title_fallback
	 * @todo   Implement test_bbp_get_reply_title_fallback().
	 */
	public function test_bbp_get_reply_title_fallback() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_reply_content
	 * @covers ::bbp_get_reply_content
	 * @todo   Implement test_bbp_get_reply_content().
	 */
	public function test_bbp_get_reply_content() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_reply_excerpt
	 * @covers ::bbp_get_reply_excerpt
	 * @todo   Implement test_bbp_get_reply_excerpt().
	 */
	public function test_bbp_get_reply_excerpt() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_reply_post_date
	 * @covers ::bbp_get_reply_post_date
	 */
	public function test_bbp_get_reply_post_date() {
		$f = $this->factory->forum->create();

		$t = $this->factory->topic->create( array(
			'post_parent' => $f,
			'topic_meta' => array(
				'forum_id' => $f,
			),
		) );

		$now = time();
		$post_date = date( 'Y-m-d H:i:s', $now - 60*66 );

		$r = $this->factory->reply->create( array(
			'post_parent' => $t,
			'post_date' => $post_date,
			'reply_meta' => array(
				'forum_id' => $f,
				'topic_id' => $t,
			),
		) );

		// Configue our written date time, August 4, 2012 at 2:37 pm.
		$gmt = false;
		$date   = get_post_time( get_option( 'date_format' ), $gmt, $r, true );
		$time   = get_post_time( get_option( 'time_format' ), $gmt, $r, true );
		$result = sprintf( '%1$s at %2$s', $date, $time );

		// Output, string, August 4, 2012 at 2:37 pm.
		$this->expectOutputString( $result );
		bbp_reply_post_date( $r );

		// String, August 4, 2012 at 2:37 pm.
		$datetime = bbp_get_topic_post_date( $r, false, false );
		$this->assertSame( $result, $datetime );

		// Humanized string, 4 days, 4 hours ago.
		$datetime = bbp_get_topic_post_date( $r, true, false );
		$this->assertSame( '1 hour, 6 minutes ago', $datetime );

		// Humanized string using GMT formatted date, 4 days, 4 hours ago.
		$datetime = bbp_get_topic_post_date( $r, true, true );
		$this->assertSame( '1 hour, 6 minutes ago', $datetime );
	}

	/**
	 * @covers ::bbp_reply_topic_title
	 * @covers ::bbp_get_reply_topic_title
	 * @todo   Implement test_bbp_get_reply_topic_title().
	 */
	public function test_bbp_get_reply_topic_title() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_reply_topic_id
	 * @covers ::bbp_get_reply_topic_id
	 * @todo   Implement test_bbp_get_reply_topic_id().
	 */
	public function test_bbp_get_reply_topic_id() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_reply_forum_id
	 * @covers ::bbp_get_reply_forum_id
	 * @todo   Implement test_bbp_get_reply_forum_id().
	 */
	public function test_bbp_get_reply_forum_id() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_reply_ancestor_id
	 * @covers ::bbp_get_reply_ancestor_id
	 * @todo   Implement test_bbp_get_reply_ancestor_id().
	 */
	public function test_bbp_get_reply_ancestor_id() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_reply_to
	 * @covers ::bbp_get_reply_to
	 * @todo   Implement test_bbp_get_reply_to().
	 */
	public function test_bbp_get_reply_to() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_reply_position
	 * @covers ::bbp_get_reply_position
	 * @todo   Implement test_bbp_get_reply_position().
	 */
	public function test_bbp_get_reply_position() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_reply_class
	 * @covers ::bbp_get_reply_class
	 * @todo   Implement test_bbp_get_reply_class().
	 */
	public function test_bbp_get_reply_class() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_topic_pagination_count
	 * @covers ::bbp_get_topic_pagination_count
	 * @todo   Implement test_bbp_get_topic_pagination_count().
	 */
	public function test_bbp_get_topic_pagination_count() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}
}
