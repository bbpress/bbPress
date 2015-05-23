<?php

/**
 * Tests for the common query functions.
 *
 * @group common
 * @group functions
 * @group query
 */
class BBP_Tests_Common_Functions_Query extends BBP_UnitTestCase {

	/**
	 * @group  counts
	 * @covers ::bbp_get_public_child_count
	 * @todo   Implement test_bbp_get_public_child_ids().
	 */
	public function test_bbp_get_public_child_count() {

	}

	/**
	 * @covers ::bbp_get_public_child_ids
	 * @todo   Implement test_bbp_get_public_child_ids().
	 */
	public function test_bbp_get_public_child_ids() {

	}

	/**
	 * @covers ::bbp_get_all_child_ids
	 */
	public function test_bbp_get_all_child_ids() {
		$f = $this->factory->forum->create();

		// Test initial forum public child counts
		$count = count( bbp_get_all_child_ids( $f, bbp_get_forum_post_type() ) );
		$this->assertSame( 0, $count );

		$count = count( bbp_get_all_child_ids( $f, bbp_get_topic_post_type() ) );
		$this->assertSame( 0, $count );

		/* Sub-Forums *********************************************************/

		$this->factory->forum->create_many( 3, array(
			'post_parent' => $f,
		) );

		$this->factory->forum->create( array(
			'post_parent' => $f,
			'post_status' => bbp_get_private_status_id(),
		) );

		$count = count( bbp_get_all_child_ids( $f, bbp_get_forum_post_type() ) );
		$this->assertSame( 4, $count );

		$this->factory->forum->create_many( 2, array(
			'post_parent' => $f,
		) );

		$count = count( bbp_get_all_child_ids( $f, bbp_get_forum_post_type() ) );
		$this->assertSame( 6, $count );

		/* Topics *************************************************************/

		$t1 = $this->factory->topic->create_many( 3, array(
			'post_parent' => $f,
			'topic_meta' => array(
				'forum_id' => $f,
			),
		) );

		$this->factory->topic->create( array(
			'post_parent' => $f,
			'post_status' => bbp_get_spam_status_id(),
			'topic_meta' => array(
				'forum_id' => $f,
			),
		) );

		$count = count( bbp_get_all_child_ids( $f, bbp_get_topic_post_type() ) );
		$this->assertSame( 4, $count );

		$this->factory->topic->create_many( 2, array(
			'post_parent' => $f,
			'topic_meta' => array(
				'forum_id' => $f,
			),
		) );

		$count = count( bbp_get_all_child_ids( $f, bbp_get_topic_post_type() ) );
		$this->assertSame( 6, $count );

		$this->factory->topic->create( array(
			'post_parent' => $f,
			'post_status' => bbp_get_pending_status_id(),
			'topic_meta' => array(
				'forum_id' => $f,
			),
		) );

		$count = count( bbp_get_all_child_ids( $f, bbp_get_topic_post_type() ) );
		$this->assertSame( 7, $count );

		/* Replies ************************************************************/

		$this->factory->reply->create_many( 3, array(
			'post_parent' => $t1[0],
			'reply_meta' => array(
				'forum_id' => $f,
				'topic_id' => $t1[0],
			),
		) );

		$this->factory->reply->create( array(
			'post_parent' => $t1[0],
			'post_status' => bbp_get_spam_status_id(),
			'reply_meta' => array(
				'forum_id' => $f,
				'topic_id' => $t1[0],
			),
		) );

		$count = count( bbp_get_all_child_ids( $t1[0], bbp_get_reply_post_type() ) );
		$this->assertSame( 4, $count );

		$this->factory->reply->create_many( 2, array(
			'post_parent' => $t1[0],
			'reply_meta' => array(
				'forum_id' => $f,
				'topic_id' => $t1[0],
			),
		) );

		$count = count( bbp_get_all_child_ids( $t1[0], bbp_get_reply_post_type() ) );
		$this->assertSame( 6, $count );

		$this->factory->reply->create( array(
			'post_parent' => $t1[0],
			'post_status' => bbp_get_pending_status_id(),
			'reply_meta' => array(
				'forum_id' => $f,
				'topic_id' => $t1[0],
			),
		) );

		$count = count( bbp_get_all_child_ids( $t1[0], bbp_get_reply_post_type() ) );
		$this->assertSame( 7, $count );
	}
}
