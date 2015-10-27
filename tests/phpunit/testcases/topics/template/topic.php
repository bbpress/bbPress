<?php
/**
 * Tests for the topics component topic template functions.
 *
 * @group topics
 * @group template
 * @group topic
 */
class BBP_Tests_Topics_Template_Topic extends BBP_UnitTestCase {

	/**
	 * @covers ::bbp_show_lead_topic
	 * @todo   Implement test_bbp_show_lead_topic().
	 */
	public function test_bbp_show_lead_topic() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_topic_id
	 * @covers ::bbp_get_topic_id
	 */
	public function test_bbp_get_topic_id() {
		$f = $this->factory->forum->create();
		$t = $this->factory->topic->create( array(
			'post_parent' => $f,
			'topic_meta' => array(
				'forum_id' => $f,
			),
		) );

		$topic_id = bbp_get_topic_id( $t );
		$this->assertSame( $t, $topic_id );
	}

	/**
	 * @covers ::bbp_get_topic
	 * @todo   Implement test_bbp_get_topic().
	 */
	public function test_bbp_get_topic() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_topic_permalink
	 * @covers ::bbp_get_topic_permalink
	 */
	public function test_bbp_get_topic_permalink() {
		if ( is_multisite() ) {
			$this->markTestSkipped( 'Skipping URL tests in multiste for now.' );
		}
		$f = $this->factory->forum->create();
		$t = $this->factory->topic->create( array(
			'post_title' => 'Topic 1',
			'post_parent' => $f,
			'topic_meta' => array(
				'forum_id' => $f,
			),
		) );

		$topic_permalink = bbp_get_topic_permalink( $t );

		$this->expectOutputString( $topic_permalink );
		bbp_topic_permalink( $t );

		$this->assertSame( 'http://' . WP_TESTS_DOMAIN . '/?topic=topic-1', $topic_permalink );
	}

	/**
	 * @covers ::bbp_topic_title
	 * @covers ::bbp_get_topic_title
	 */
	public function test_bbp_get_topic_title() {
		$f = $this->factory->forum->create();
		$t = $this->factory->topic->create( array(
			'post_title' => 'Topic 1',
			'post_parent' => $f,
			'topic_meta' => array(
				'forum_id' => $f,
			),
		) );

		$topic_title = bbp_get_topic_title( $t );
		$this->assertSame( 'Topic 1', $topic_title );
	}

	/**
	 * @covers ::bbp_topic_archive_title
	 * @covers ::bbp_get_topic_archive_title
	 * @todo   Implement test_bbp_get_topic_archive_title().
	 */
	public function test_bbp_get_topic_archive_title() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_topic_content
	 * @covers ::bbp_get_topic_content
	 */
	public function test_bbp_get_topic_content() {
		$f = $this->factory->forum->create();
		$t = $this->factory->topic->create( array(
			'post_content' => 'Content of Topic 1',
			'post_parent' => $f,
			'topic_meta' => array(
				'forum_id' => $f,
			),
		) );

		remove_all_filters( 'bbp_get_topic_content' );
		$topic_content = bbp_get_topic_content( $t );
		$this->assertSame( 'Content of Topic 1', $topic_content );
	}

	/**
	 * @covers ::bbp_topic_excerpt
	 * @covers ::bbp_get_topic_excerpt
	 */
	public function test_bbp_get_topic_excerpt() {
		$f = $this->factory->forum->create();
		$t = $this->factory->topic->create( array(
			'post_parent' => $f,
			'post_content'  => 'Talk about telekinetic activity, look at this mess!',
			'topic_meta' => array(
				'forum_id' => $f,
			),
		) );

		remove_all_filters( 'bbp_get_topic_content' );
		$topic_excerpt = bbp_get_topic_excerpt( $t, 23 );
		$this->assertSame( 'Talk about telekinetic&hellip;', $topic_excerpt );
	}

	/**
	 * @covers ::bbp_topic_post_date
	 * @covers ::bbp_get_topic_post_date
	 */
	public function test_bbp_get_topic_post_date() {
		$f = $this->factory->forum->create();

		$now = time();
		$post_date = date( 'Y-m-d H:i:s', $now - 60*60*100 );

		$t = $this->factory->topic->create( array(
			'post_parent' => $f,
			'post_date' => $post_date,
			'topic_meta' => array(
				'forum_id' => $f,
			),
		) );

		// Configue our written date time, August 4, 2012 at 2:37 pm.
		$gmt = false;
		$date   = get_post_time( get_option( 'date_format' ), $gmt, $t, true );
		$time   = get_post_time( get_option( 'time_format' ), $gmt, $t, true );
		$result = sprintf( '%1$s at %2$s', $date, $time );

		// Output, string, August 4, 2012 at 2:37 pm.
		$this->expectOutputString( $result );
		bbp_topic_post_date( $t );

		// String, August 4, 2012 at 2:37 pm.
		$datetime = bbp_get_topic_post_date( $t, false, false );
		$this->assertSame( $result, $datetime );

		// Humanized string, 4 days, 4 hours ago.
		$datetime = bbp_get_topic_post_date( $t, true, false );
		$this->assertSame( '4 days, 4 hours ago', $datetime );

		// Humanized string using GMT formatted date, 4 days, 4 hours ago.
		$datetime = bbp_get_topic_post_date( $t, true, true );
		$this->assertSame( '4 days, 4 hours ago', $datetime );
	}

	/**
	 * @covers ::bbp_topic_pagination
	 * @covers ::bbp_get_topic_pagination
	 * @todo   Implement test_bbp_get_topic_pagination().
	 */
	public function test_bbp_get_topic_pagination() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_topic_forum_title
	 * @covers ::bbp_get_topic_forum_title
	 */
	public function test_bbp_get_topic_forum_title() {
		$f = $this->factory->forum->create();
		$t = $this->factory->topic->create( array(
			'post_parent' => $f,
			'topic_meta' => array(
				'forum_id' => $f,
			),
		) );

		$topic_forum_title = bbp_get_topic_forum_title( $t );
		$this->assertSame( bbp_get_forum_title( $f ), $topic_forum_title );
	}

	/**
	 * @covers ::bbp_topic_forum_id
	 * @covers ::bbp_get_topic_forum_id
	 */
	public function test_bbp_get_topic_forum_id() {
		$f = $this->factory->forum->create();
		$t = $this->factory->topic->create( array(
			'post_parent' => $f,
			'topic_meta' => array(
				'forum_id' => $f,
			),
		) );

		$topic_forum_id = bbp_get_topic_forum_id( $t );
		$this->assertSame( $f, $topic_forum_id );
	}

	/**
	 * @covers ::bbp_topic_class
	 * @covers ::bbp_get_topic_class
	 * @todo   Implement test_bbp_get_topic_class().
	 */
	public function test_bbp_get_topic_class() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_forum_pagination_count
	 * @covers ::bbp_get_forum_pagination_count
	 * @todo   Implement test_bbp_get_forum_pagination_count().
	 */
	public function test_bbp_get_forum_pagination_count() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_topic_notices
	 * @todo   Implement test_bbp_topic_notices().
	 */
	public function test_bbp_topic_notices() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_topic_type_select
	 * @todo   Implement test_bbp_topic_type_select().
	 */
	public function test_bbp_topic_type_select() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_single_topic_description
	 * @covers ::bbp_get_single_topic_description
	 * @todo   Implement test_bbp_get_single_topic_description().
	 */
	public function test_bbp_get_single_topic_description() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_topic_row_actions
	 * @todo   Implement test_bbp_topic_row_actions().
	 */
	public function test_bbp_topic_row_actions() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}
}
