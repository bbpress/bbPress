<?php

/**
 * Tests for bbPress forum visibility functions.
 *
 * @group forums
 * @group functions
 * @group visibility
 */
class BBP_Tests_Forums_Functions_Visibility extends BBP_UnitTestCase {

	/**
	 * @covers ::bbp_repair_forum_visibility
	 * @todo   Implement test_bbp_repair_forum_visibility().
	 */
	public function test_bbp_repair_forum_visibility() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_get_forum_visibilities
	 * @todo   Implement test_bbp_get_forum_visibilities().
	 */
	public function test_bbp_get_forum_visibilities() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_get_hidden_forum_ids
	 * @todo   Implement test_bbp_get_hidden_forum_ids().
	 */
	public function test_bbp_get_hidden_forum_ids() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_get_private_forum_ids
	 * @todo   Implement test_bbp_get_private_forum_ids().
	 */
	public function test_bbp_get_private_forum_ids() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_pre_get_posts_normalize_forum_visibility
	 */
	public function test_bbp_pre_get_posts_normalize_forum_visibility() {
		$public_forum = $this->factory->forum->create();
		$private_forum = $this->factory->forum->create( array(
			'post_status' => bbp_get_private_status_id(),
		) );
		$hidden_forum = $this->factory->forum->create( array(
			'post_status' => bbp_get_hidden_status_id(),
		) );

		$public_topic = $this->factory->topic->create( array(
			'post_parent' => $public_forum,
			'topic_meta'  => array(
				'forum_id' => $public_forum,
			),
		) );
		$private_topic = $this->factory->topic->create( array(
			'post_parent' => $private_forum,
			'topic_meta'  => array(
				'forum_id' => $private_forum,
			),
		) );
		$hidden_topic = $this->factory->topic->create( array(
			'post_parent' => $hidden_forum,
			'topic_meta'  => array(
				'forum_id' => $hidden_forum,
			),
		) );

		$public_reply = $this->factory->reply->create( array(
			'post_parent' => $public_topic,
			'reply_meta'  => array(
				'forum_id' => $public_forum,
				'topic_id' => $public_topic,
			),
		) );
		$private_reply = $this->factory->reply->create( array(
			'post_parent' => $private_topic,
			'reply_meta'  => array(
				'forum_id' => $private_forum,
				'topic_id' => $private_topic,
			),
		) );
		$hidden_reply = $this->factory->reply->create( array(
			'post_parent' => $hidden_topic,
			'reply_meta'  => array(
				'forum_id' => $hidden_forum,
				'topic_id' => $hidden_topic,
			),
		) );

		$post_id = $this->factory->post->create();

		$this->set_current_user( 0 );

		$topic_query = new WP_Query(
			array(
				'post_type'      => array( bbp_get_topic_post_type(), 'post' ),
				'posts_per_page' => -1,
				'fields'         => 'ids',
			)
		);

		$this->assertContains( $post_id, $topic_query->posts );
		$this->assertNotContains( $public_topic, $topic_query->posts );
		$this->assertNotContains( $private_topic, $topic_query->posts );
		$this->assertNotContains( $hidden_topic, $topic_query->posts );

		$reply_query = new WP_Query(
			array(
				'post_type'      => array( bbp_get_reply_post_type(), 'post' ),
				'posts_per_page' => -1,
				'fields'         => 'ids',
			)
		);

		$this->assertContains( $post_id, $reply_query->posts );
		$this->assertNotContains( $public_reply, $reply_query->posts );
		$this->assertNotContains( $private_reply, $reply_query->posts );
		$this->assertNotContains( $hidden_reply, $reply_query->posts );

		$bbp_query = new WP_Query(
			array(
				'post_type'      => array( bbp_get_topic_post_type(), bbp_get_reply_post_type() ),
				'posts_per_page' => -1,
				'fields'         => 'ids',
			)
		);

		$this->assertContains( $public_topic, $bbp_query->posts );
		$this->assertContains( $public_reply, $bbp_query->posts );
		$this->assertNotContains( $private_topic, $bbp_query->posts );
		$this->assertNotContains( $private_reply, $bbp_query->posts );
		$this->assertNotContains( $hidden_topic, $bbp_query->posts );
		$this->assertNotContains( $hidden_reply, $bbp_query->posts );
	}

	/**
	 * @covers ::bbp_forum_enforce_hidden
	 * @todo   Implement test_bbp_forum_enforce_hidden().
	 */
	public function test_bbp_forum_enforce_hidden() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_forum_enforce_private
	 * @todo   Implement test_bbp_forum_enforce_private().
	 */
	public function test_bbp_forum_enforce_private() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}
}
