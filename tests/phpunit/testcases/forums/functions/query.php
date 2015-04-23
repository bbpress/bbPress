<?php

/**
 * Tests for the forum component query functions.
 *
 * @group forums
 * @group functions
 * @group query
 */
class BBP_Tests_Forums_Functions_Query extends BBP_UnitTestCase {

	/**
	 * @covers ::bbp_exclude_forum_ids
	 * @todo   Implement test_bbp_exclude_forum_ids().
	 */
	public function test_bbp_exclude_forum_ids() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_forum_query_topic_ids
	 */
	public function test_bbp_forum_query_topic_ids() {
		$f = $this->factory->forum->create();

		$this->factory->topic->create_many( 9, array(
			'post_parent' => $f,
		) );

		bbp_update_forum_topic_count( $f );

		$count = count( bbp_forum_query_topic_ids( $f ) );
		$this->assertSame( 9, $count );;
	}

	/**
	 * @covers ::bbp_forum_query_subforum_ids
	 */
	public function test_bbp_forum_query_subforum_ids() {
		$f1 = $this->factory->forum->create();

		$f2 = $this->factory->forum->create_many( 9, array(
			'post_parent' => $f1,
		) );

		$count = count( bbp_forum_query_subforum_ids( $f1 ) );
		$this->assertSame( 9, $count );;
	}

	/**
	 * @covers ::bbp_forum_query_last_reply_id
	 * @todo   Implement test_bbp_forum_query_last_reply_id().
	 */
	public function test_bbp_forum_query_last_reply_id() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}
}
