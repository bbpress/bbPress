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
	 * @covers ::bbp_has_topics
	 * @ticket 2763
	 */
	public function test_bbp_has_topics_does_not_repeat_stickies_on_later_pages() {
		$forum_id = $this->factory->forum->create();
		$topic_ids = array();

		foreach ( range( 1, 5 ) as $day ) {
			$topic_id = $this->factory->topic->create( array( 'post_parent' => $forum_id ) );
			update_post_meta( $topic_id, '_bbp_last_active_time', sprintf( '2020-01-%02d 00:00:00', $day ) );
			$topic_ids[] = $topic_id;
		}

		bbp_stick_topic( $topic_ids[1] );

		$args = array(
			'post_parent'    => $forum_id,
			'posts_per_page' => 2,
			'show_stickies'  => true,
		);

		$this->assertTrue( bbp_has_topics( array_merge( $args, array( 'paged' => 1 ) ) ) );
		$this->assertSame( array( $topic_ids[1], $topic_ids[4], $topic_ids[3] ), wp_list_pluck( bbpress()->topic_query->posts, 'ID' ) );
		$this->assertSame( 5, bbpress()->topic_query->found_posts );
		$this->assertSame( 2, bbpress()->topic_query->max_num_pages );
		$this->assertStringContainsString( '1 through 3 (of 5 total)', bbp_get_forum_pagination_count() );

		$this->assertTrue( bbp_has_topics( array_merge( $args, array( 'paged' => 2 ) ) ) );
		$this->assertSame( array( $topic_ids[2], $topic_ids[0] ), wp_list_pluck( bbpress()->topic_query->posts, 'ID' ) );
		$this->assertSame( 5, bbpress()->topic_query->found_posts );
		$this->assertSame( 2, bbpress()->topic_query->max_num_pages );
		$this->assertStringContainsString( '4 through 5 (of 5 total)', bbp_get_forum_pagination_count() );
	}

	/**
	 * @covers ::bbp_has_topics
	 * @ticket 2763
	 */
	public function test_bbp_has_topics_with_post_in_does_not_repeat_stickies() {
		$forum_id = $this->factory->forum->create();
		$topic_ids = array();

		foreach ( range( 1, 3 ) as $day ) {
			$topic_id = $this->factory->topic->create( array( 'post_parent' => $forum_id ) );
			update_post_meta( $topic_id, '_bbp_last_active_time', sprintf( '2020-01-%02d 00:00:00', $day ) );
			$topic_ids[] = $topic_id;
		}

		bbp_stick_topic( $topic_ids[0] );

		$args = array(
			'post_parent'    => $forum_id,
			'post__in'      => $topic_ids,
			'posts_per_page' => 1,
			'show_stickies'  => true,
		);

		$this->assertTrue( bbp_has_topics( array_merge( $args, array( 'paged' => 1 ) ) ) );
		$this->assertSame( array( $topic_ids[0], $topic_ids[2] ), wp_list_pluck( bbpress()->topic_query->posts, 'ID' ) );

		$this->assertTrue( bbp_has_topics( array_merge( $args, array( 'paged' => 2 ) ) ) );
		$this->assertSame( array( $topic_ids[1] ), wp_list_pluck( bbpress()->topic_query->posts, 'ID' ) );
		$this->assertSame( 3, bbpress()->topic_query->found_posts );
		$this->assertSame( 2, bbpress()->topic_query->max_num_pages );
	}

	/**
	 * @covers ::bbp_get_topic_page_counts
	 * @ticket 2763
	 */
	public function test_bbp_get_topic_page_counts() {
		// Inputs: total, stickies, shown on page, regular topics per page, page.
		$cases = array(
			'no stickies, first page'  => array( 5, 0, 2, 2, 1, array( 'pages' => 3, 'from' => 1, 'to' => 2, 'total' => 5 ) ),
			'one sticky, first page'   => array( 5, 1, 3, 2, 1, array( 'pages' => 2, 'from' => 1, 'to' => 3, 'total' => 5 ) ),
			'one sticky, second page'  => array( 5, 1, 2, 2, 2, array( 'pages' => 2, 'from' => 4, 'to' => 5, 'total' => 5 ) ),
			'only a sticky'            => array( 1, 1, 1, 2, 1, array( 'pages' => 1, 'from' => 1, 'to' => 1, 'total' => 1 ) ),
			'three stickies, one page' => array( 4, 3, 4, 2, 1, array( 'pages' => 1, 'from' => 1, 'to' => 4, 'total' => 4 ) ),
		);

		foreach ( $cases as $description => $case ) {
			$actual = bbp_get_topic_page_counts( $case[0], $case[1], $case[2], $case[3], $case[4] );
			$this->assertSame( $case[5], $actual, $description );
		}
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
