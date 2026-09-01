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
	 * Create public, private, and hidden forum content for visibility tests.
	 *
	 * @return int[] Forum, topic, and reply IDs keyed by visibility.
	 */
	protected function create_visibility_test_posts() {
		$posts = array();

		$posts['public_forum']  = $this->factory->forum->create();
		$posts['private_forum'] = $this->factory->forum->create( array(
			'post_status' => bbp_get_private_status_id(),
		) );
		$posts['hidden_forum'] = $this->factory->forum->create( array(
			'post_status' => bbp_get_hidden_status_id(),
		) );

		foreach ( array( 'public', 'private', 'hidden' ) as $visibility ) {
			$forum_id = $posts[ "{$visibility}_forum" ];
			$topic_id = $this->factory->topic->create( array(
				'post_parent' => $forum_id,
				'topic_meta'  => array(
					'forum_id' => $forum_id,
				),
			) );
			$reply_id = $this->factory->reply->create( array(
				'post_parent' => $topic_id,
				'reply_meta'  => array(
					'forum_id' => $forum_id,
					'topic_id' => $topic_id,
				),
			) );

			$posts[ "{$visibility}_topic" ] = $topic_id;
			$posts[ "{$visibility}_reply" ] = $reply_id;
		}

		return $posts;
	}

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
		$posts = $this->create_visibility_test_posts();

		$this->set_current_user( 0 );

		$query = new WP_Query(
			array(
				'post_type'      => array( bbp_get_topic_post_type(), bbp_get_reply_post_type() ),
				'posts_per_page' => -1,
				'fields'         => 'ids',
			)
		);

		$this->assertContains( $posts['public_topic'], $query->posts );
		$this->assertContains( $posts['public_reply'], $query->posts );
		$this->assertNotContains( $posts['private_topic'], $query->posts );
		$this->assertNotContains( $posts['private_reply'], $query->posts );
		$this->assertNotContains( $posts['hidden_topic'], $query->posts );
		$this->assertNotContains( $posts['hidden_reply'], $query->posts );
	}

	/**
	 * @covers ::bbp_pre_get_posts_normalize_forum_visibility
	 * @covers ::_bbp_forum_visibility_where
	 */
	public function test_bbp_pre_get_posts_normalize_forum_visibility_with_mixed_post_types() {
		$posts   = $this->create_visibility_test_posts();
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
		$this->assertContains( $posts['public_topic'], $topic_query->posts );
		$this->assertNotContains( $posts['private_topic'], $topic_query->posts );
		$this->assertNotContains( $posts['hidden_topic'], $topic_query->posts );

		$reply_query = new WP_Query(
			array(
				'post_type'      => array( bbp_get_reply_post_type(), 'post' ),
				'posts_per_page' => -1,
				'fields'         => 'ids',
			)
		);

		$this->assertContains( $post_id, $reply_query->posts );
		$this->assertContains( $posts['public_reply'], $reply_query->posts );
		$this->assertNotContains( $posts['private_reply'], $reply_query->posts );
		$this->assertNotContains( $posts['hidden_reply'], $reply_query->posts );
	}

	/**
	 * @covers ::bbp_pre_get_posts_normalize_forum_visibility
	 * @covers ::_bbp_forum_visibility_where
	 */
	public function test_bbp_pre_get_posts_normalize_forum_visibility_with_mixed_forums_topics_and_posts() {
		$posts    = $this->create_visibility_test_posts();
		$post_id  = $this->factory->post->create();
		$statuses = array(
			bbp_get_public_status_id(),
			bbp_get_private_status_id(),
			bbp_get_hidden_status_id(),
		);

		$this->set_current_user( 0 );

		$query = new WP_Query(
			array(
				'post_type'      => array(
					bbp_get_forum_post_type(),
					bbp_get_topic_post_type(),
					'post',
				),
				'post_status'    => $statuses,
				'posts_per_page' => -1,
				'fields'         => 'ids',
			)
		);

		$this->assertContains( $post_id, $query->posts );
		$this->assertContains( $posts['public_forum'], $query->posts );
		$this->assertContains( $posts['public_topic'], $query->posts );
		$this->assertNotContains( $posts['private_forum'], $query->posts );
		$this->assertNotContains( $posts['private_topic'], $query->posts );
		$this->assertNotContains( $posts['hidden_forum'], $query->posts );
		$this->assertNotContains( $posts['hidden_topic'], $query->posts );
	}

	/**
	 * @covers ::bbp_pre_get_posts_normalize_forum_visibility
	 */
	public function test_bbp_pre_get_posts_normalize_forum_visibility_ignores_non_bbp_post_types() {
		$post_id = $this->factory->post->create();

		$this->create_visibility_test_posts();
		$this->set_current_user( 0 );

		$query = new WP_Query(
			array(
				'post_type'      => 'post',
				'posts_per_page' => -1,
				'fields'         => 'ids',
			)
		);

		$this->assertSame( array( $post_id ), $query->posts );
		$this->assertEmpty( $query->get( '_bbp_forum_visibility_normalized' ) );
	}

	/**
	 * @covers ::_bbp_forum_visibility_where
	 */
	public function test_bbp_forum_visibility_where_preserves_custom_post_types() {
		$posts = $this->create_visibility_test_posts();

		register_post_type(
			'external_item',
			array(
				'public' => true,
			)
		);

		$external_id = $this->factory->post->create( array(
			'post_type' => 'external_item',
		) );

		// Ensure similarly named third-party metadata is not interpreted by bbPress.
		update_post_meta( $external_id, '_bbp_forum_id', $posts['private_forum'] );

		$this->set_current_user( 0 );

		$query = new WP_Query(
			array(
				'post_type'      => array( bbp_get_topic_post_type(), 'external_item' ),
				'posts_per_page' => -1,
				'fields'         => 'ids',
			)
		);

		unregister_post_type( 'external_item' );

		$this->assertContains( $external_id, $query->posts );
		$this->assertContains( $posts['public_topic'], $query->posts );
		$this->assertNotContains( $posts['private_topic'], $query->posts );
		$this->assertNotContains( $posts['hidden_topic'], $query->posts );
	}

	/**
	 * @covers ::_bbp_forum_visibility_where
	 */
	public function test_bbp_forum_visibility_where_fails_closed_without_forum_meta() {
		$posts        = $this->create_visibility_test_posts();
		$post_id      = $this->factory->post->create();
		$orphan_topic = wp_insert_post(
			array(
				'post_title'  => 'Topic without forum metadata',
				'post_status' => bbp_get_public_status_id(),
				'post_type'   => bbp_get_topic_post_type(),
			)
		);

		$this->set_current_user( 0 );

		$query = new WP_Query(
			array(
				'post_type'      => array( bbp_get_topic_post_type(), 'post' ),
				'posts_per_page' => -1,
				'fields'         => 'ids',
			)
		);

		$this->assertContains( $post_id, $query->posts );
		$this->assertContains( $posts['public_topic'], $query->posts );
		$this->assertNotContains( $orphan_topic, $query->posts );
	}

	/**
	 * @covers ::_bbp_forum_visibility_where
	 */
	public function test_bbp_forum_visibility_where_for_user_with_access() {
		$posts   = $this->create_visibility_test_posts();
		$post_id = $this->factory->post->create();
		$filter  = function() {
			return array();
		};

		add_filter( 'bbp_get_excluded_forum_ids', $filter, 99 );

		$query = new WP_Query(
			array(
				'post_type'      => array( bbp_get_topic_post_type(), 'post' ),
				'post_status'    => array(
					bbp_get_public_status_id(),
					bbp_get_private_status_id(),
					bbp_get_hidden_status_id(),
				),
				'posts_per_page' => -1,
				'fields'         => 'ids',
			)
		);

		remove_filter( 'bbp_get_excluded_forum_ids', $filter, 99 );

		$this->assertContains( $post_id, $query->posts );
		$this->assertContains( $posts['public_topic'], $query->posts );
		$this->assertContains( $posts['private_topic'], $query->posts );
		$this->assertContains( $posts['hidden_topic'], $query->posts );
	}

	/**
	 * @covers ::_bbp_forum_visibility_where
	 */
	public function test_bbp_forum_visibility_where_without_protected_forums() {
		$forum_id = $this->factory->forum->create();
		$topic_id = $this->factory->topic->create( array(
			'post_parent' => $forum_id,
			'topic_meta'  => array(
				'forum_id' => $forum_id,
			),
		) );
		$post_id = $this->factory->post->create();

		$this->set_current_user( 0 );

		$query = new WP_Query(
			array(
				'post_type'      => array( bbp_get_topic_post_type(), 'post' ),
				'posts_per_page' => -1,
				'fields'         => 'ids',
			)
		);

		$this->assertEqualSets( array( $topic_id, $post_id ), $query->posts );
	}

	/**
	 * @covers ::bbp_pre_get_posts_normalize_forum_visibility
	 */
	public function test_bbp_pre_get_posts_normalize_forum_visibility_with_suppressed_filters() {
		$posts   = $this->create_visibility_test_posts();
		$post_id = $this->factory->post->create();

		$this->set_current_user( 0 );

		$query = new WP_Query(
			array(
				'post_type'        => array( bbp_get_topic_post_type(), 'post' ),
				'posts_per_page'   => -1,
				'fields'           => 'ids',
				'suppress_filters' => true,
			)
		);

		$this->assertSame( array( $post_id ), $query->posts );
		$this->assertNotContains( $posts['public_topic'], $query->posts );
		$this->assertNotContains( $posts['private_topic'], $query->posts );
	}

	/**
	 * @covers ::bbp_pre_get_posts_normalize_forum_visibility
	 * @covers ::_bbp_forum_visibility_where
	 */
	public function test_bbp_forum_visibility_where_with_any_post_type() {
		$posts               = $this->create_visibility_test_posts();
		$post_id              = $this->factory->post->create();
		$post_type_object     = get_post_type_object( bbp_get_topic_post_type() );
		$exclude_from_search = $post_type_object->exclude_from_search;

		// Simulate a plugin opting bbPress topics into broad WordPress queries.
		$post_type_object->exclude_from_search = false;
		$this->set_current_user( 0 );

		$query = new WP_Query(
			array(
				'post_type'      => 'any',
				'posts_per_page' => -1,
				'fields'         => 'ids',
			)
		);

		$post_type_object->exclude_from_search = $exclude_from_search;

		$this->assertContains( $post_id, $query->posts );
		$this->assertContains( $posts['public_topic'], $query->posts );
		$this->assertNotContains( $posts['private_topic'], $query->posts );
		$this->assertNotContains( $posts['hidden_topic'], $query->posts );
	}

	/**
	 * @covers ::bbp_pre_get_posts_normalize_forum_visibility
	 * @covers ::_bbp_forum_visibility_where
	 */
	public function test_bbp_forum_visibility_where_with_implicit_search_post_types() {
		$posts       = $this->create_visibility_test_posts();
		$search_term = 'Unique forum visibility search marker';
		$post_id      = $this->factory->post->create( array(
			'post_title' => $search_term,
		) );

		foreach ( array( 'public_topic', 'private_topic', 'hidden_topic' ) as $topic_key ) {
			wp_update_post( array(
				'ID'         => $posts[ $topic_key ],
				'post_title' => $search_term,
			) );
		}

		$post_type_object     = get_post_type_object( bbp_get_topic_post_type() );
		$exclude_from_search = $post_type_object->exclude_from_search;

		// Simulate a plugin opting bbPress topics into ordinary WordPress search.
		$post_type_object->exclude_from_search = false;
		$this->set_current_user( 0 );

		$query = new WP_Query(
			array(
				's'              => $search_term,
				'posts_per_page' => -1,
				'fields'         => 'ids',
			)
		);

		$post_type_object->exclude_from_search = $exclude_from_search;

		$this->assertContains( $post_id, $query->posts );
		$this->assertContains( $posts['public_topic'], $query->posts );
		$this->assertNotContains( $posts['private_topic'], $query->posts );
		$this->assertNotContains( $posts['hidden_topic'], $query->posts );
	}

	/**
	 * @covers ::_bbp_forum_visibility_where
	 */
	public function test_bbp_forum_visibility_posts_where_filter() {
		$posts  = $this->create_visibility_test_posts();
		$called = false;
		$filter = function( $where, $query, $post_types, $forum_ids ) use ( &$called, $posts ) {
			$called = true;
			$this->assertInstanceOf( 'WP_Query', $query );
			$this->assertContains( bbp_get_topic_post_type(), $post_types );
			$this->assertContains( $posts['private_forum'], $forum_ids );

			return $where;
		};

		add_filter( 'bbp_forum_visibility_posts_where', $filter, 10, 4 );

		new WP_Query(
			array(
				'post_type'      => array( bbp_get_topic_post_type(), 'post' ),
				'posts_per_page' => -1,
				'fields'         => 'ids',
			)
		);

		remove_filter( 'bbp_forum_visibility_posts_where', $filter, 10 );

		$this->assertTrue( $called );
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
