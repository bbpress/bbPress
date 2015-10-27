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

		$t1 = $this->factory->topic->create( array(
			'post_parent' => $f,
			'topic_meta' => array(
				'forum_id' => $f,
			),
		) );

		$t2 = $this->factory->topic->create( array(
			'post_parent' => $f,
			'topic_meta' => array(
				'forum_id' => $f,
			),
		) );

		$t3 = $this->factory->topic->create( array(
			'post_parent' => $f,
			'topic_meta' => array(
				'forum_id' => $f,
			),
		) );

		$this->assertEqualSets( array( $t1, $t2, $t3 ), bbp_forum_query_topic_ids( $f ) );
	}

	/**
	 * @covers ::bbp_forum_query_subforum_ids
	 */
	public function test_bbp_forum_query_subforum_ids() {
		$f1 = $this->factory->forum->create();

		$f2 = $this->factory->forum->create( array(
			'post_parent' => $f1,
		) );

		$f3 = $this->factory->forum->create( array(
			'post_parent' => $f1,
		) );

		$this->assertEqualSets( array( $f2, $f3 ), bbp_forum_query_subforum_ids( $f1 ) );
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
