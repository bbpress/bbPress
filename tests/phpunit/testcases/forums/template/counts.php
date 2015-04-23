<?php

/**
 * Tests for the `bbp_*_forum_*_count()` template functions.
 *
 * @group forums
 * @group template
 * @group counts
 */
class BBP_Tests_Forums_Template_Counts extends BBP_UnitTestCase {

	/**
	 * @covers ::bbp_forum_subforum_count
	 * @covers ::bbp_get_forum_subforum_count
	 */
	public function test_bbp_get_forum_subforum_count() {
		$f1 = $this->factory->forum->create();

		$f2 = $this->factory->forum->create_many( 9, array(
			'post_parent' => $f1,
		) );

		bbp_update_forum_subforum_count( $f1 );

		$count = bbp_get_forum_subforum_count( $f1 );
		$this->expectOutputString( $count );
		bbp_forum_subforum_count( $f1 );

		$count = bbp_get_forum_subforum_count( $f1 );
		$this->assertSame( '9', $count );

		$count = bbp_get_forum_subforum_count( $f1, $integer = true );
		$this->assertSame( 9, $count );

		$count = count( bbp_forum_query_subforum_ids( $f1 ) );
		$this->assertSame( 9, $count );
	}

	/**
	 * @covers ::bbp_forum_topic_count
	 * @covers ::bbp_get_forum_topic_count
	 */
	public function test_bbp_get_forum_topic_count() {
		$f = $this->factory->forum->create();

		$count = bbp_get_forum_topic_count( $f );
		$this->expectOutputString( $count );
		bbp_forum_topic_count( $f );

		$count = bbp_get_forum_topic_count( $f, true, false );
		$this->assertSame( '0', $count );

		$count = bbp_get_forum_topic_count( $f, true, true );
		$this->assertSame( 0, $count );
	}

	/**
	 * @covers ::bbp_forum_reply_count
	 * @covers ::bbp_get_forum_reply_count
	 */
	public function test_bbp_get_forum_reply_count() {
		$f = $this->factory->forum->create();

		$count = bbp_get_forum_reply_count( $f );
		$this->expectOutputString( $count );
		bbp_forum_reply_count( $f );

		$count = bbp_get_forum_reply_count( $f, true, false );
		$this->assertSame( '0', $count );

		$count = bbp_get_forum_reply_count( $f, true, true );
		$this->assertSame( 0, $count );
	}

	/**
	 * @covers ::bbp_forum_post_count
	 * @covers ::bbp_get_forum_post_count
	 */
	public function test_bbp_get_forum_post_count() {
		$f = $this->factory->forum->create();

		$count = bbp_get_forum_post_count( $f );
		$this->expectOutputString( $count );
		bbp_forum_post_count( $f );

		$count = bbp_get_forum_post_count( $f, true, false );
		$this->assertSame( '0', $count );

		$count = bbp_get_forum_post_count( $f, true, true );
		$this->assertSame( 0, $count );
	}

	/**
	 * @covers ::bbp_forum_topic_count_hidden
	 * @covers ::bbp_get_forum_topic_count_hidden
	 */
	public function test_bbp_get_forum_topic_count_hidden() {
		$f = $this->factory->forum->create();

		$count = bbp_get_forum_topic_count_hidden( $f );
		$this->expectOutputString( $count );
		bbp_forum_topic_count_hidden( $f );

		$count = bbp_get_forum_topic_count_hidden( $f );
		$this->assertSame( 0, $count );

		$count = bbp_get_forum_topic_count_hidden( $f, true );
		$this->assertSame( 0, $count );
	}
}
