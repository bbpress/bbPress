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
	 */
	public function test_bbp_get_reply_id() {
		$f = $this->factory->forum->create();
		$t = $this->factory->topic->create( array(
			'post_parent' => $f,
			'topic_meta' => array(
				'forum_id' => $f,
			),
		) );

		$r = $this->factory->reply->create( array(
			'post_parent' => $t,
			'reply_meta' => array(
				'forum_id' => $f,
				'topic_id' => $t,
			),
		) );

		$reply_id = bbp_get_reply_id( $r );
		$this->assertSame( $r, $reply_id );
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
	 */
	public function test_bbp_get_reply_permalink() {
		if ( is_multisite() ) {
			$this->markTestSkipped( 'Skipping URL tests in multiste for now.' );
		}
		$f = $this->factory->forum->create();
		$t = $this->factory->topic->create( array(
			'post_parent' => $f,
			'topic_meta' => array(
				'forum_id' => $f,
			),
		) );

		$r = $this->factory->reply->create( array(
			'post_parent' => $t,
			'reply_meta' => array(
				'forum_id' => $f,
				'topic_id' => $t,
			),
		) );

		$reply_permalink = bbp_get_reply_permalink( $r );

		$this->expectOutputString( $reply_permalink );
		bbp_reply_permalink( $r );

		$this->assertSame( 'http://' . WP_TESTS_DOMAIN . '/?reply=' . bbp_get_reply_id( $r ), $reply_permalink );
	}

	/**
	 * @covers ::bbp_reply_url
	 * @covers ::bbp_get_reply_url
	 *
	 * @ticket BBP2845
	 */
	public function test_bbp_get_reply_url() {
		if ( is_multisite() ) {
			$this->markTestSkipped( 'Skipping URL tests in multisite for now.' );
		}

		$f = $this->factory->forum->create();

		$t = $this->factory->topic->create( array(
			'post_parent' => $f,
			'topic_meta' => array(
				'forum_id' => $f,
			),
		) );

		$r = $this->factory->reply->create_many( 7, array(
			'post_parent' => $t,
			'reply_meta' => array(
				'forum_id' => $f,
				'topic_id' => $t,
			),
		) );

		// Store the original reply pagination option value.
		$default_reply_page = bbp_get_replies_per_page();

		// Lower the reply pagination value to test without so many replies.
		update_option( '_bbp_replies_per_page', 3 );

		// Reply menu position is unaltered when bbp_show_lead_topic() true.
		add_filter( 'bbp_show_lead_topic', '__return_true' );

		// 1st reply is on the first page, 3 replies and 1 topic per page.
		$reply_url = bbp_get_topic_permalink( $t ) . '/#post-' . bbp_get_reply_id( $r[0] );
		$this->assertSame( $reply_url, bbp_get_reply_url( $r[0] ) );

		// 2nd reply is on the first page, 3 replies and 1 topic per page.
		$reply_url = bbp_get_topic_permalink( $t ) . '/#post-' . bbp_get_reply_id( $r[1] );
		$this->assertSame( $reply_url, bbp_get_reply_url( $r[1] ) );

		// 3rd reply is on the first page, 3 replies and 1 topic per page.
		$reply_url = bbp_get_topic_permalink( $t ) . '/#post-' . bbp_get_reply_id( $r[2] );
		$this->assertSame( $reply_url, bbp_get_reply_url( $r[2] ) );

		// 4th reply is on the second page, 3 replies and 1 topic per page.
		$reply_url = bbp_get_topic_permalink( $t ) . '&paged=2#post-' . bbp_get_reply_id( $r[3] );
		$this->assertSame( $reply_url, bbp_get_reply_url( $r[3] ) );

		// 5th reply is on the second page, 3 replies and 1 topic per page.
		$reply_url = bbp_get_topic_permalink( $t ) . '&paged=2#post-' . bbp_get_reply_id( $r[4] );
		$this->assertSame( $reply_url, bbp_get_reply_url( $r[4] ) );

		// 6th reply is on the second page, 3 replies and 1 topic per page.
		$reply_url = bbp_get_topic_permalink( $t ) . '&paged=2#post-' . bbp_get_reply_id( $r[5] );
		$this->assertSame( $reply_url, bbp_get_reply_url( $r[5] ) );

		// 7th reply is on the third page, 3 replies and 1 topic per page.
		$reply_url = bbp_get_topic_permalink( $t ) . '&paged=3#post-' . bbp_get_reply_id( $r[6] );
		$this->assertSame( $reply_url, bbp_get_reply_url( $r[6] ) );

		// Remove the filter for WordPress < 4.0 compatibility.
		remove_filter( 'bbp_show_lead_topic', '__return_true' );

		// Reply menu position is bumped by 1 when bbp_show_lead_topic() false.
		add_filter( 'bbp_show_lead_topic', '__return_false' );

		// 1st reply is on the first page, 2 replies and 1 topic per first page.
		$reply_url = bbp_get_topic_permalink( $t ) . '/#post-' . bbp_get_reply_id( $r[0] );
		$this->assertSame( $reply_url, bbp_get_reply_url( $r[0] ) );

		// 2nd reply is on the first page, 2 replies and 1 topic per first page.
		$reply_url = bbp_get_topic_permalink( $t ) . '/#post-' . bbp_get_reply_id( $r[1] );
		$this->assertSame( $reply_url, bbp_get_reply_url( $r[1] ) );

		// 3rd reply is on the second page, 2 replies and 1 topic per first page.
		$reply_url = bbp_get_topic_permalink( $t ) . '&paged=2#post-' . bbp_get_reply_id( $r[2] );
		$this->assertSame( $reply_url, bbp_get_reply_url( $r[2] ) );

		// 4th reply is on the second page, 3 replies per subsequent page.
		$reply_url = bbp_get_topic_permalink( $t ) . '&paged=2#post-' . bbp_get_reply_id( $r[3] );
		$this->assertSame( $reply_url, bbp_get_reply_url( $r[3] ) );

		// 5th reply is on the second page, 3 replies per subsequent page.
		$reply_url = bbp_get_topic_permalink( $t ) . '&paged=2#post-' . bbp_get_reply_id( $r[4] );
		$this->assertSame( $reply_url, bbp_get_reply_url( $r[4] ) );

		// 6th reply is on the third page, 3 replies per subsequent page.
		$reply_url = bbp_get_topic_permalink( $t ) . '&paged=3#post-' . bbp_get_reply_id( $r[5] );
		$this->assertSame( $reply_url, bbp_get_reply_url( $r[5] ) );

		// 7th reply is on the third page, 3 replies per subsequent page.
		$reply_url = bbp_get_topic_permalink( $t ) . '&paged=3#post-' . bbp_get_reply_id( $r[6] );
		$this->assertSame( $reply_url, bbp_get_reply_url( $r[6] ) );

		// Remove the filter for WordPress < 4.0 compatibility.
		remove_filter( 'bbp_show_lead_topic', '__return_false' );

		// Restore the original reply pagination option value.
		update_option( '_bbp_replies_per_page', $default_reply_page );
	}

	/**
	 * @covers ::bbp_reply_title
	 * @covers ::bbp_get_reply_title
	 */
	public function test_bbp_get_reply_title() {
		$f = $this->factory->forum->create();
		$t = $this->factory->topic->create( array(
			'post_parent' => $f,
			'topic_meta' => array(
				'forum_id' => $f,
			),
		) );

		$r = $this->factory->reply->create( array(
			'post_parent' => $t,
			'reply_meta' => array(
				'forum_id' => $f,
				'topic_id' => $t,
			),
		) );

		$reply_title = bbp_get_reply_title( $r );
		$this->assertSame( 'Reply To: ' . bbp_get_topic_title( $t ), $reply_title );
	}

	/**
	 * @covers ::bbp_get_reply_title_fallback
	 */
	public function test_bbp_get_reply_title_fallback() {
		$f = $this->factory->forum->create();
		$t = $this->factory->topic->create( array(
			'post_parent' => $f,
			'topic_meta' => array(
				'forum_id' => $f,
			),
		) );

		$r = $this->factory->reply->create( array(
			'post_parent' => $t,
			'post_title'  => 'What are you supposed to be, some kind of a cosmonaut?',
			'reply_meta' => array(
				'forum_id' => $f,
				'topic_id' => $t,
			),
		) );

		$reply_title = 'What are you supposed to be, some kind of a cosmonaut?';
		$reply_title_fallback = bbp_get_reply_title_fallback( $reply_title, $r );
		$this->assertSame( $reply_title , $reply_title_fallback );

		$r = $this->factory->reply->create( array(
			'post_parent' => $t,
			'reply_meta' => array(
				'forum_id' => $f,
				'topic_id' => $t,
			),
		) );

		$reply_title = '';
		$reply_title_fallback = bbp_get_reply_title_fallback( $reply_title, $r );
		$this->assertSame( 'Reply To: ' . bbp_get_topic_title( $t ), $reply_title_fallback );
	}

	/**
	 * @covers ::bbp_reply_content
	 * @covers ::bbp_get_reply_content
	 */
	public function test_bbp_get_reply_content() {
		$f = $this->factory->forum->create();
		$t = $this->factory->topic->create( array(
			'post_parent' => $f,
			'topic_meta' => array(
				'forum_id' => $f,
			),
		) );

		$r = $this->factory->reply->create( array(
			'post_content' => 'Content of Reply 1',
			'post_parent' => $t,
			'reply_meta' => array(
				'forum_id' => $f,
				'topic_id' => $t,
			),
		) );

		remove_all_filters( 'bbp_get_reply_content' );
		$reply_content = bbp_get_reply_content( $r );
		$this->assertSame( 'Content of Reply 1', $reply_content );
	}

	/**
	 * @covers ::bbp_reply_excerpt
	 * @covers ::bbp_get_reply_excerpt
	 */
	public function test_bbp_get_reply_excerpt() {
		$f = $this->factory->forum->create();
		$t = $this->factory->topic->create( array(
			'post_parent' => $f,
			'topic_meta' => array(
				'forum_id' => $f,
			),
		) );

		$r = $this->factory->reply->create( array(
			'post_parent'   => $t,
			'post_content'  => 'I feel like the floor of a taxi cab.',
			'reply_meta' => array(
				'forum_id' => $f,
				'topic_id' => $t,
			),
		) );

		remove_all_filters( 'bbp_get_reply_content' );
		$reply_excerpt = bbp_get_reply_excerpt( $r, 22 );
		$this->assertSame( 'I feel like the floor&hellip;', $reply_excerpt );
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
		$post_date = date( 'Y-m-d H:i:s', $now - 60 * 66 );

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
	 */
	public function test_bbp_get_reply_topic_title() {
		$f = $this->factory->forum->create();
		$t = $this->factory->topic->create( array(
			'post_parent' => $f,
			'topic_meta' => array(
				'forum_id' => $f,
			),
		) );

		$r = $this->factory->reply->create( array(
			'post_parent' => $t,
			'reply_meta' => array(
				'forum_id' => $f,
				'topic_id' => $t,
			),
		) );

		$reply_topic_title = bbp_get_reply_topic_title( $r );
		$this->assertSame( bbp_get_topic_title( $t ), $reply_topic_title );
	}

	/**
	 * @covers ::bbp_reply_topic_id
	 * @covers ::bbp_get_reply_topic_id
	 */
	public function test_bbp_get_reply_topic_id() {
		$f = $this->factory->forum->create();
		$t = $this->factory->topic->create( array(
			'post_parent' => $f,
			'topic_meta' => array(
				'forum_id' => $f,
			),
		) );

		$r = $this->factory->reply->create( array(
			'post_parent' => $t,
			'reply_meta' => array(
				'forum_id' => $f,
				'topic_id' => $t,
			),
		) );

		$reply_topic_id = bbp_get_reply_topic_id( $r );
		$this->assertSame( $t, $reply_topic_id );
	}

	/**
	 * @covers ::bbp_reply_forum_id
	 * @covers ::bbp_get_reply_forum_id
	 */
	public function test_bbp_get_reply_forum_id() {
		$f = $this->factory->forum->create();
		$t = $this->factory->topic->create( array(
			'post_parent' => $f,
			'topic_meta' => array(
				'forum_id' => $f,
			),
		) );

		$r = $this->factory->reply->create( array(
			'post_parent' => $t,
			'reply_meta' => array(
				'forum_id' => $f,
				'topic_id' => $t,
			),
		) );

		$reply_forum_id = bbp_get_reply_forum_id( $r );
		$this->assertSame( $f, $reply_forum_id );
	}

	/**
	 * @covers ::bbp_reply_ancestor_id
	 * @covers ::bbp_get_reply_ancestor_id
	 */
	public function test_bbp_get_reply_ancestor_id() {
		$f = $this->factory->forum->create();
		$t = $this->factory->topic->create( array(
			'post_parent' => $f,
			'topic_meta' => array(
				'forum_id' => $f,
			),
		) );

		$r1 = $this->factory->reply->create( array(
			'post_parent' => $t,
			'reply_meta' => array(
				'forum_id' => $f,
				'topic_id' => $t,
			),
		) );

		$r2 = $this->factory->reply->create( array(
			'post_parent' => $t,
			'reply_meta' => array(
				'forum_id' => $f,
				'topic_id' => $t,
				'reply_to' => $r1,
			),
		) );

		$reply_ancestor_id = bbp_get_reply_ancestor_id( $r2 );
		$this->assertSame( $r1, $reply_ancestor_id );
	}

	/**
	 * @covers ::bbp_reply_to
	 * @covers ::bbp_get_reply_to
	 */
	public function test_bbp_get_reply_to() {
		$f = $this->factory->forum->create();
		$t = $this->factory->topic->create( array(
			'post_parent' => $f,
			'topic_meta' => array(
				'forum_id' => $f,
			),
		) );

		$r1 = $this->factory->reply->create( array(
			'post_parent' => $t,
			'reply_meta' => array(
				'forum_id' => $f,
				'topic_id' => $t,
			),
		) );

		$r2 = $this->factory->reply->create( array(
			'post_parent' => $t,
			'reply_meta' => array(
				'forum_id' => $f,
				'topic_id' => $t,
				'reply_to' => $r1,
			),
		) );

		$reply_to = bbp_get_reply_to( $r2 );
		$this->assertSame( $r1, $reply_to );
	}

	/**
	 * @covers ::bbp_reply_position
	 * @covers ::bbp_get_reply_position
	 *
	 * @ticket BBP2845
	 */
	public function test_bbp_get_reply_position() {
		$f = $this->factory->forum->create();

		$t = $this->factory->topic->create( array(
			'post_parent' => $f,
			'topic_meta' => array(
				'forum_id' => $f,
			),
		) );

		$r = $this->factory->reply->create_many( 7, array(
			'post_parent' => $t,
			'reply_meta' => array(
				'forum_id' => $f,
				'topic_id' => $t,
			),
		) );

		// Reply menu position is unaltered when bbp_show_lead_topic() true.
		add_filter( 'bbp_show_lead_topic', '__return_true' );

		$position = get_post_field( 'menu_order', $r[3] );
		$this->assertSame( 4, $position );

		$position = bbp_get_reply_position_raw( $r[3] );
		$this->assertSame( 4, $position );

		$position = bbp_get_reply_position( $r[3] );
		$this->assertSame( 4, $position );

		// Force a reply's 'menu_order' to 0.
		wp_update_post( array(
			'ID'         => $r[3],
			'menu_order' => 0,
		) );

		$position = get_post_field( 'menu_order', $r[3] );
		$this->assertSame( 0, $position );

		$position = bbp_get_reply_position_raw( $r[3] );
		$this->assertSame( 4, $position );

		$position = bbp_get_reply_position( $r[3] );
		$this->assertSame( 4, $position );

		// Remove the filter for WordPress < 4.0 compatibility.
		remove_filter( 'bbp_show_lead_topic', '__return_true' );

		// Reply menu position is bumped by 1 when bbp_show_lead_topic() false.
		add_filter( 'bbp_show_lead_topic', '__return_false' );

		$position = get_post_field( 'menu_order', $r[3] );
		$this->assertSame( 4, $position );

		$position = bbp_get_reply_position_raw( $r[3] );
		$this->assertSame( 4, $position );

		$position = bbp_get_reply_position( $r[3] );
		$this->assertSame( 5, $position );

		// Force a reply's 'menu_order' to 0.
		wp_update_post( array(
			'ID'         => $r[3],
			'menu_order' => 0,
		) );

		$position = get_post_field( 'menu_order', $r[3] );
		$this->assertSame( 0, $position );

		$position = bbp_get_reply_position_raw( $r[3] );
		$this->assertSame( 4, $position );

		$position = bbp_get_reply_position( $r[3] );
		$this->assertSame( 5, $position );

		// Remove the filter for WordPress < 4.0 compatibility.
		remove_filter( 'bbp_show_lead_topic', '__return_false' );
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
