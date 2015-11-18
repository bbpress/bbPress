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
	 * @covers ::bbp_query_post_parent__in
	 * @todo   Implement test_bbp_query_post_parent__in().
	 */
	public function test_bbp_query_post_parent__in() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_get_public_child_last_id
	 */
	public function test_bbp_get_public_child_last_id() {
		$f = $this->factory->forum->create();

		$t = $this->factory->topic->create( array(
			'post_parent' => $f,
			'topic_meta' => array(
				'forum_id' => $f,
			),
		) );

		$last_id = bbp_get_public_child_last_id( $f, bbp_get_topic_post_type() );
		$this->assertSame( $t, $last_id );

		$r = $this->factory->reply->create( array(
			'post_parent' => $t,
			'reply_meta' => array(
				'forum_id' => $f,
				'topic_id' => $t,
			),
		) );

		$last_id = bbp_get_public_child_last_id( $t, bbp_get_reply_post_type() );
		$this->assertSame( $r, $last_id );
	}

	/**
	 * @group  counts
	 * @covers ::bbp_get_public_child_count
	 */
	public function test_bbp_get_public_child_count() {
		$f = $this->factory->forum->create();

		// Test initial forum public child counts
		$count = bbp_get_public_child_count( $f, bbp_get_forum_post_type() );
		$this->assertSame( 0, $count );

		$count = bbp_get_public_child_count( $f, bbp_get_topic_post_type() );
		$this->assertSame( 0, $count );

		/* Sub-Forums *********************************************************/

		$this->factory->forum->create_many( 3, array(
			'post_parent' => $f,
		) );

		$this->factory->forum->create( array(
			'post_parent' => $f,
			'post_status' => bbp_get_private_status_id(),
		) );

		$count = bbp_get_public_child_count( $f, bbp_get_forum_post_type() );
		$this->assertSame( 3, $count );

		$this->factory->forum->create_many( 2, array(
			'post_parent' => $f,
		) );

		$count = bbp_get_public_child_count( $f, bbp_get_forum_post_type() );
		$this->assertSame( 5, $count );

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

		$count = bbp_get_public_child_count( $f, bbp_get_topic_post_type() );
		$this->assertSame( 3, $count );

		$this->factory->topic->create_many( 2, array(
			'post_parent' => $f,
			'topic_meta' => array(
				'forum_id' => $f,
			),
		) );

		$count = bbp_get_public_child_count( $f, bbp_get_topic_post_type() );
		$this->assertSame( 5, $count );

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

		$count = bbp_get_public_child_count( $t1[0], bbp_get_reply_post_type() );
		$this->assertSame( 3, $count );

		$this->factory->reply->create_many( 2, array(
			'post_parent' => $t1[0],
			'reply_meta' => array(
				'forum_id' => $f,
				'topic_id' => $t1[0],
			),
		) );

		$count = bbp_get_public_child_count( $t1[0], bbp_get_reply_post_type() );
		$this->assertSame( 5, $count );
	}

	/**
	 * @covers ::bbp_get_public_child_ids
	 */
	public function test_bbp_get_public_child_ids() {
		$f = $this->factory->forum->create();

		// Test initial forum public child counts
		$count = count( bbp_get_public_child_ids( $f, bbp_get_forum_post_type() ) );
		$this->assertSame( 0, $count );

		$count = count( bbp_get_public_child_ids( $f, bbp_get_topic_post_type() ) );
		$this->assertSame( 0, $count );

		/* Sub-Forums *********************************************************/

		$this->factory->forum->create_many( 3, array(
			'post_parent' => $f,
		) );

		$this->factory->forum->create( array(
			'post_parent' => $f,
			'post_status' => bbp_get_private_status_id(),
		) );

		$count = count( bbp_get_public_child_ids( $f, bbp_get_forum_post_type() ) );
		$this->assertSame( 3, $count );

		$this->factory->forum->create_many( 2, array(
			'post_parent' => $f,
		) );

		$count = count( bbp_get_public_child_ids( $f, bbp_get_forum_post_type() ) );
		$this->assertSame( 5, $count );

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

		$count = count( bbp_get_public_child_ids( $f, bbp_get_topic_post_type() ) );
		$this->assertSame( 3, $count );

		$this->factory->topic->create_many( 2, array(
			'post_parent' => $f,
			'topic_meta' => array(
				'forum_id' => $f,
			),
		) );

		$count = count( bbp_get_public_child_ids( $f, bbp_get_topic_post_type() ) );
		$this->assertSame( 5, $count );

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

		$count = count( bbp_get_public_child_ids( $t1[0], bbp_get_reply_post_type() ) );
		$this->assertSame( 3, $count );

		$this->factory->reply->create_many( 2, array(
			'post_parent' => $t1[0],
			'reply_meta' => array(
				'forum_id' => $f,
				'topic_id' => $t1[0],
			),
		) );

		$count = count( bbp_get_public_child_ids( $t1[0], bbp_get_reply_post_type() ) );
		$this->assertSame( 5, $count );
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
