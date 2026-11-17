<?php

/**
 * Tests for the `bbp_*_form_forum_*_feed_link()` functions.
 *
 * @group forums
 * @group template
 * @group feeds
 */
class BBP_Tests_Forums_Template_Feeds extends BBP_UnitTestCase {

	/**
	 * @covers ::bbp_forum_topics_feed_link
	 * @covers ::bbp_get_forum_topics_feed_link
	 */
	public function test_bbp_get_forum_topics_feed_link() {

		$f = $this->factory->forum->create( array(
			'post_title' => 'Forum 1',
		) );

		$feed_link = bbp_get_forum_topics_feed_link( $f );
		$this->expectOutputString( $feed_link );
		bbp_forum_topics_feed_link( $f );

		$feed_link = bbp_get_forum_topics_feed_link( $f );
		$this->assertSame( '<a href="http://' . WP_TESTS_DOMAIN . '/?feed=rss2&#038;forum=forum-1" class="bbp-forum-rss-link topics"><span>Topics</span></a>', $feed_link );
	}

	/**
	 * @covers ::bbp_forum_replies_feed_link
	 * @covers ::bbp_get_forum_replies_feed_link
	 */
	public function test_bbp_get_forum_replies_feed_link() {

		$f = $this->factory->forum->create( array(
			'post_title' => 'Forum 1',
		) );

		$feed_link = bbp_get_forum_replies_feed_link( $f );
		$this->expectOutputString( $feed_link );
		bbp_forum_replies_feed_link( $f );

		$feed_link = bbp_get_forum_replies_feed_link( $f );
		$this->assertSame( '<a href="http://' . WP_TESTS_DOMAIN . '/?type=reply&#038;feed=rss2&#038;forum=forum-1" class="bbp-forum-rss-link replies"><span>Replies</span></a>', $feed_link );
	}

	/**
	 * @covers ::bbp_get_forum_topics_feed_link
	 * @covers ::bbp_get_forum_replies_feed_link
	 */
	public function test_bbp_get_forum_feed_links_without_a_forum() {
		$no_forum = static function () {
			return 0;
		};
		$seen = array();
		$capture = static function ( $link, $url, $forum_id ) use ( &$seen ) {
			$seen[] = array( $link, $url, $forum_id );
			return $link;
		};

		add_filter( 'bbp_get_forum_id', $no_forum );
		add_filter( 'bbp_get_forum_topics_feed_link', $capture, 10, 3 );
		add_filter( 'bbp_get_forum_replies_feed_link', $capture, 10, 3 );

		try {
			$this->assertSame( '', bbp_get_forum_topics_feed_link() );
			$this->assertSame( '', bbp_get_forum_replies_feed_link() );
			$this->assertSame( array( array( '', '', 0 ), array( '', '', 0 ) ), $seen );
		} finally {
			remove_filter( 'bbp_get_forum_id', $no_forum );
			remove_filter( 'bbp_get_forum_topics_feed_link', $capture );
			remove_filter( 'bbp_get_forum_replies_feed_link', $capture );
		}
	}
}
