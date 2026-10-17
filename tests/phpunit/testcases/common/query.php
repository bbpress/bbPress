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

		/* Empty Forum ********************************************************/

		$f = $this->factory->forum->create();

		// Test initial zero forum public child counts
		$count = bbp_get_public_child_count( $f, bbp_get_forum_post_type() );
		$this->assertSame( 0, $count );

		$count = bbp_get_public_child_count( $f, bbp_get_topic_post_type() );
		$this->assertSame( 0, $count );

		/* Sub-Forums *********************************************************/

		// 3 public sub-forums
		$this->factory->forum->create_many( 3, array(
			'post_parent' => $f,
		) );

		// 1 private sub-forum
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
	 * @covers ::bbp_get_child_counts
	 * @ticket BBP3749
	 */
	public function test_bbp_get_child_counts_uses_indexed_post_relationships() {
		$forum_id = $this->factory->forum->create();
		$topic_id = $this->factory->topic->create( array(
			'post_parent' => $forum_id,
			'topic_meta'  => array( 'forum_id' => $forum_id ),
		) );
		$this->factory->reply->create( array(
			'post_parent' => $topic_id,
			'reply_meta'  => array(
				'forum_id' => $forum_id,
				'topic_id' => $topic_id,
			),
		) );

		$topic_counts = bbp_get_child_counts( $topic_id );
		$topic_query  = bbp_db()->last_query;

		$this->assertSame( 1, $topic_counts[ bbp_get_reply_post_type() ][ bbp_get_public_status_id() ] );
		$this->assertStringContainsString( 'post_parent', $topic_query );
		$this->assertStringNotContainsString( bbp_db()->postmeta, $topic_query );

		$forum_counts = bbp_get_child_counts( $forum_id );
		$forum_query  = bbp_db()->last_query;

		$this->assertSame( 1, $forum_counts[ bbp_get_topic_post_type() ][ bbp_get_public_status_id() ] );
		$this->assertSame( 1, $forum_counts[ bbp_get_reply_post_type() ][ bbp_get_public_status_id() ] );
		$this->assertStringContainsString( 'UNION ALL', $forum_query );
		$this->assertStringContainsString( 'post_parent', $forum_query );
		$this->assertStringNotContainsString( bbp_db()->postmeta, $forum_query );
	}

	/**
	 * @covers ::bbp_get_child_counts
	 * @ticket BBP3749
	 */
	public function test_bbp_get_child_counts_query_is_filterable() {
		$topic_id = $this->factory->topic->create();
		$callback = function( $query, $parent_id, $post_type ) use ( $topic_id ) {
			$this->assertSame( $topic_id, $parent_id );
			$this->assertSame( bbp_get_topic_post_type(), $post_type );

			return "SELECT 'reply' AS type, 'publish' AS status, 7 AS count";
		};

		add_filter( 'bbp_get_child_counts_query', $callback, 10, 3 );

		try {
			$counts = bbp_get_child_counts( $topic_id );
		} finally {
			remove_filter( 'bbp_get_child_counts_query', $callback, 10 );
		}

		$this->assertSame( 7, $counts['reply']['publish'] );
		$this->assertSame( "SELECT 'reply' AS type, 'publish' AS status, 7 AS count", bbp_db()->last_query );
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
	 * @covers ::bbp_get_child_ids_by_status
	 */
	public function test_bbp_get_child_ids_by_status() {
		$forum_id = $this->factory->forum->create( array( 'post_status' => bbp_get_hidden_status_id() ) );
		$public_id = $this->factory->topic->create(
			array(
				'post_parent' => $forum_id,
				'topic_meta'  => array( 'forum_id' => $forum_id ),
			)
		);
		$pending_id = $this->factory->topic->create(
			array(
				'post_parent' => $forum_id,
				'post_status' => bbp_get_pending_status_id(),
				'topic_meta'  => array( 'forum_id' => $forum_id ),
			)
		);
		$this->factory->topic->create(
			array(
				'post_parent' => $forum_id,
				'post_status' => bbp_get_spam_status_id(),
				'topic_meta'  => array( 'forum_id' => $forum_id ),
			)
		);

		$this->set_current_user( 0 );

		$topic_ids = bbp_get_child_ids_by_status(
			$forum_id,
			bbp_get_topic_post_type(),
			array( bbp_get_public_status_id(), bbp_get_pending_status_id() )
		);

		$this->assertSame( array( $pending_id, $public_id ), $topic_ids );
		$this->assertSame( array( $pending_id ), bbp_get_child_ids_by_status( $forum_id, bbp_get_topic_post_type(), bbp_get_pending_status_id() ) );
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
