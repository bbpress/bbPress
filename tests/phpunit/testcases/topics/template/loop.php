<?php

/**
 * Tests for the topic loop functions.
 *
 * @group topics
 * @group template
 * @group loop
 */
class BBP_Tests_Topics_Template_Topic_Loop extends BBP_UnitTestCase {

	/**
	 * @covers ::bbp_has_topics
	 */
	public function test_bbp_has_topics() {
		$forum_id = $this->factory->forum->create();
		$topic_id = $this->factory->topic->create( array( 'post_parent' => $forum_id ) );

		$this->assertTrue( bbp_has_topics( array(
			'post_parent'    => $forum_id,
			'posts_per_page' => -1,
		) ) );
		$this->assertSame( 1, bbpress()->topic_query->post_count );
		$this->assertSame( $topic_id, bbpress()->topic_query->posts[0]->ID );
		$this->assertFalse( bbp_has_topics( array( 'post_parent' => $topic_id ) ) );
	}

	/**
	 * @covers ::bbp_topics
	 */
	public function test_bbp_topics() {
		$forum_id = $this->factory->forum->create();
		$this->factory->topic->create( array( 'post_parent' => $forum_id ) );
		bbp_has_topics( array( 'post_parent' => $forum_id ) );

		$this->assertTrue( bbp_topics() );
		bbp_the_topic();
		$this->assertFalse( bbp_topics() );
	}

	/**
	 * @covers ::bbp_the_topic
	 */
	public function test_bbp_the_topic() {
		$forum_id = $this->factory->forum->create();
		$topic_id = $this->factory->topic->create( array( 'post_parent' => $forum_id ) );
		bbp_has_topics( array( 'post_parent' => $forum_id ) );

		$this->assertTrue( bbp_topics() );
		bbp_the_topic();
		$this->assertSame( $topic_id, get_the_ID() );
		$this->assertFalse( bbp_topics() );
	}

	/**
	 * @covers ::bbp_add_sticky_topics
	 * @ticket 3706
	 */
	public function test_bbp_add_sticky_topics_orders_and_fetches_stickies() {
		$forum_id        = $this->factory->forum->create();
		$other_forum_id  = $this->factory->forum->create();
		$normal_id       = $this->factory->topic->create( array( 'post_parent' => $forum_id ) );
		$forum_sticky_id = $this->factory->topic->create( array( 'post_parent' => $forum_id ) );
		$super_sticky_id = $this->factory->topic->create( array( 'post_parent' => $other_forum_id ) );

		bbp_stick_topic( $forum_sticky_id );
		bbp_stick_topic( $super_sticky_id, true );

		$query = new WP_Query(
			array(
				'post_type'      => bbp_get_topic_post_type(),
				'post_parent'    => $forum_id,
				'post_status'    => bbp_get_public_status_id(),
				'posts_per_page' => -1,
			)
		);

		$this->assertSame( 2, $query->post_count );
		bbp_add_sticky_topics( $query, array( 'post_parent' => $forum_id ) );

		$this->assertSame( array( $super_sticky_id, $forum_sticky_id, $normal_id ), wp_list_pluck( $query->posts, 'ID' ) );
		$this->assertSame( 3, $query->post_count );
		$this->assertSame( 3, $query->found_posts );

		$excluded_query = new WP_Query(
			array(
				'post_type'      => bbp_get_topic_post_type(),
				'post_parent'    => $forum_id,
				'post_status'    => bbp_get_public_status_id(),
				'posts_per_page' => -1,
			)
		);

		bbp_add_sticky_topics(
			$excluded_query,
			array(
				'post_parent'  => $forum_id,
				'post__not_in' => array( $super_sticky_id ),
			)
		);

		$this->assertSame( array( $forum_sticky_id, $normal_id ), wp_list_pluck( $excluded_query->posts, 'ID' ) );
		$this->assertSame( 2, $excluded_query->post_count );
		$this->assertSame( 2, $excluded_query->found_posts );
	}
}
