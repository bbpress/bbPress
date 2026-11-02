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
	 * Create public, closed, private, and hidden content in a public forum.
	 *
	 * @return int[] Forum, topic, and reply IDs keyed by status.
	 */
	protected function create_status_test_posts() {
		$posts       = array();
		$forum_id    = $this->factory->forum->create();
		$post_status = array(
			'public'  => bbp_get_public_status_id(),
			'closed'  => bbp_get_closed_status_id(),
			'private' => bbp_get_private_status_id(),
			'hidden'  => bbp_get_hidden_status_id(),
		);

		$posts['forum'] = $forum_id;

		foreach ( $post_status as $status => $status_id ) {
			$topic_id = $this->factory->topic->create( array(
				'post_parent' => $forum_id,
				'post_status' => $status_id,
				'topic_meta'  => array(
					'forum_id' => $forum_id,
				),
			) );
			$reply_id = $this->factory->reply->create( array(
				'post_parent' => $topic_id,
				'post_status' => $status_id,
				'reply_meta'  => array(
					'forum_id' => $forum_id,
					'topic_id' => $topic_id,
				),
			) );

			$posts[ "{$status}_topic" ] = $topic_id;
			$posts[ "{$status}_reply" ] = $reply_id;
		}

		return $posts;
	}

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
	 * Create a restricted forum with public descendants and content.
	 *
	 * @param string $status Parent forum status.
	 * @return int[] Forum, topic, and reply IDs.
	 */
	protected function create_inherited_visibility_test_posts( $status = 'hidden' ) {
		$parent_id = $this->factory->forum->create( array(
			'post_status' => $status,
		) );
		$child_id = $this->factory->forum->create( array(
			'post_parent' => $parent_id,
		) );
		$grandchild_id = $this->factory->forum->create( array(
			'post_parent' => $child_id,
		) );
		$topic_id = $this->factory->topic->create( array(
			'post_parent' => $grandchild_id,
			'topic_meta'  => array(
				'forum_id' => $grandchild_id,
			),
		) );
		$reply_id = $this->factory->reply->create( array(
			'post_parent' => $topic_id,
			'reply_meta'  => array(
				'forum_id' => $grandchild_id,
				'topic_id' => $topic_id,
			),
		) );

		return compact( 'parent_id', 'child_id', 'grandchild_id', 'topic_id', 'reply_id' );
	}

	/**
	 * @covers ::bbp_get_excluded_forum_ids
	 */
	public function test_bbp_get_excluded_forum_ids_includes_public_descendants_of_hidden_forum() {
		$posts = $this->create_inherited_visibility_test_posts( bbp_get_hidden_status_id() );

		$this->set_current_user( 0 );

		$this->assertEqualSets(
			array( $posts['parent_id'], $posts['child_id'], $posts['grandchild_id'] ),
			bbp_get_excluded_forum_ids()
		);
	}

	/**
	 * @covers ::bbp_get_excluded_forum_ids
	 */
	public function test_bbp_get_excluded_forum_ids_includes_public_descendants_of_private_forum() {
		$posts = $this->create_inherited_visibility_test_posts( bbp_get_private_status_id() );

		$this->set_current_user( 0 );

		$this->assertEqualSets(
			array( $posts['parent_id'], $posts['child_id'], $posts['grandchild_id'] ),
			bbp_get_excluded_forum_ids()
		);
	}

	/**
	 * @covers ::bbp_map_forum_meta_caps
	 */
	public function test_participant_cannot_read_public_descendants_of_hidden_forum() {
		$posts   = $this->create_inherited_visibility_test_posts( bbp_get_hidden_status_id() );
		$user_id = $this->factory->user->create();

		bbp_set_user_role( $user_id, bbp_get_participant_role() );
		$this->set_current_user( $user_id );

		$this->assertFalse( current_user_can( 'read_forum', $posts['child_id'] ) );
		$this->assertFalse( current_user_can( 'read_forum', $posts['grandchild_id'] ) );
	}

	/**
	 * @covers ::bbp_map_forum_meta_caps
	 */
	public function test_participant_can_read_public_descendants_of_private_forum() {
		$posts   = $this->create_inherited_visibility_test_posts( bbp_get_private_status_id() );
		$user_id = $this->factory->user->create();

		bbp_set_user_role( $user_id, bbp_get_participant_role() );
		$this->set_current_user( $user_id );

		$this->assertTrue( current_user_can( 'read_forum', $posts['child_id'] ) );
		$this->assertTrue( current_user_can( 'read_forum', $posts['grandchild_id'] ) );
	}

	/**
	 * @covers ::bbp_map_forum_meta_caps
	 */
	public function test_keymaster_can_read_public_descendants_of_hidden_forum() {
		$posts   = $this->create_inherited_visibility_test_posts( bbp_get_hidden_status_id() );
		$user_id = $this->factory->user->create( array( 'role' => 'administrator' ) );

		bbp_set_user_role( $user_id, bbp_get_keymaster_role() );
		$this->set_current_user( $user_id );

		$this->assertTrue( current_user_can( 'read_forum', $posts['child_id'] ) );
		$this->assertTrue( current_user_can( 'read_forum', $posts['grandchild_id'] ) );
	}

	/**
	 * @covers ::bbp_map_forum_meta_caps
	 * @covers ::bbp_allow_forums_of_user
	 */
	public function test_parent_forum_moderator_can_read_public_descendants_of_hidden_forum() {
		$posts   = $this->create_inherited_visibility_test_posts( bbp_get_hidden_status_id() );
		$user_id = $this->factory->user->create();

		bbp_set_user_role( $user_id, bbp_get_participant_role() );
		bbp_add_moderator( $posts['parent_id'], $user_id );
		$this->set_current_user( $user_id );

		$this->assertTrue( current_user_can( 'read_forum', $posts['child_id'] ) );
		$this->assertTrue( current_user_can( 'read_forum', $posts['grandchild_id'] ) );
		$this->assertEmpty( bbp_get_excluded_forum_ids() );
	}

	/**
	 * @covers ::bbp_map_forum_meta_caps
	 * @covers ::bbp_allow_forums_of_user
	 */
	public function test_descendant_forum_moderator_can_read_descendant_of_hidden_parent() {
		$posts   = $this->create_inherited_visibility_test_posts( bbp_get_hidden_status_id() );
		$user_id = $this->factory->user->create();

		bbp_set_user_role( $user_id, bbp_get_participant_role() );
		bbp_add_moderator( $posts['grandchild_id'], $user_id );
		$this->set_current_user( $user_id );

		$this->assertTrue( current_user_can( 'read_forum', $posts['grandchild_id'] ) );
		$this->assertNotContains( $posts['grandchild_id'], bbp_get_excluded_forum_ids() );
	}

	/**
	 * @covers ::bbp_allow_forums_of_user
	 */
	public function test_filtered_forum_moderator_is_removed_from_excluded_forums() {
		$posts   = $this->create_inherited_visibility_test_posts( bbp_get_hidden_status_id() );
		$user_id = $this->factory->user->create();
		$filter  = function( $retval, $check_user_id, $forum_id ) use ( $user_id, $posts ) {
			return ( ( $user_id === $check_user_id ) && ( $posts['grandchild_id'] === $forum_id ) )
				? true
				: $retval;
		};

		bbp_set_user_role( $user_id, bbp_get_participant_role() );
		$this->set_current_user( $user_id );
		add_filter( 'bbp_is_user_forum_moderator', $filter, 10, 3 );

		$excluded = bbp_get_excluded_forum_ids();

		$this->assertContains( $posts['parent_id'], $excluded );
		$this->assertNotContains( $posts['grandchild_id'], $excluded );
		$this->assertTrue( current_user_can( 'read_forum', $posts['grandchild_id'] ) );

		remove_filter( 'bbp_is_user_forum_moderator', $filter, 10 );
	}

	/**
	 * @covers ::bbp_get_excluded_forum_ids
	 * @covers ::bbp_pre_get_posts_normalize_forum_visibility
	 * @covers ::_bbp_forum_visibility_where
	 */
	public function test_query_excludes_content_in_public_descendants_of_hidden_forum() {
		$posts = $this->create_inherited_visibility_test_posts( bbp_get_hidden_status_id() );

		$this->set_current_user( 0 );

		$query = new WP_Query(
			array(
				'post_type'      => array( bbp_get_topic_post_type(), bbp_get_reply_post_type() ),
				'posts_per_page' => -1,
				'fields'         => 'ids',
			)
		);

		$this->assertNotContains( $posts['topic_id'], $query->posts );
		$this->assertNotContains( $posts['reply_id'], $query->posts );
	}

	/**
	 * @covers ::bbp_get_excluded_forum_ids
	 * @covers ::bbp_pre_get_posts_normalize_forum_visibility
	 * @covers ::_bbp_forum_visibility_where
	 */
	public function test_query_excludes_content_in_public_descendants_of_private_forum() {
		$posts = $this->create_inherited_visibility_test_posts( bbp_get_private_status_id() );

		$this->set_current_user( 0 );

		$query = new WP_Query(
			array(
				'post_type'      => array( bbp_get_topic_post_type(), bbp_get_reply_post_type() ),
				'posts_per_page' => -1,
				'fields'         => 'ids',
			)
		);

		$this->assertNotContains( $posts['topic_id'], $query->posts );
		$this->assertNotContains( $posts['reply_id'], $query->posts );
	}

	/**
	 * @covers ::bbp_pre_get_posts_normalize_forum_visibility
	 */
	public function test_explicit_forum_id_query_excludes_restricted_forums() {
		$posts = $this->create_visibility_test_posts();

		$this->set_current_user( 0 );

		foreach ( array( 'private_forum', 'hidden_forum' ) as $forum ) {
			$query = new WP_Query(
				array(
					'post_type' => bbp_get_forum_post_type(),
					'p'         => $posts[ $forum ],
				)
			);

			$this->assertEmpty( $query->posts );
		}
	}

	/**
	 * @covers ::bbp_pre_get_posts_normalize_forum_visibility
	 */
	public function test_forum_post_in_query_excludes_restricted_forums() {
		$posts = $this->create_visibility_test_posts();
		$this->set_current_user( 0 );

		$query = new WP_Query( array(
			'post_type' => bbp_get_forum_post_type(),
			'post__in'  => array( $posts['public_forum'], $posts['private_forum'], $posts['hidden_forum'] ),
			'fields'    => 'ids',
		) );

		$this->assertSame( array( $posts['public_forum'] ), $query->posts );

		$keymaster_id = $this->factory->user->create();
		bbp_set_user_role( $keymaster_id, bbp_get_keymaster_role() );
		$this->set_current_user( $keymaster_id );

		$query = new WP_Query( array(
			'post_type' => bbp_get_forum_post_type(),
			'post__in'  => array( $posts['public_forum'], $posts['private_forum'], $posts['hidden_forum'] ),
			'fields'    => 'ids',
		) );

		$this->assertEqualSets( array( $posts['public_forum'], $posts['private_forum'], $posts['hidden_forum'] ), $query->posts );
	}

	/**
	 * @covers ::bbp_pre_get_posts_normalize_forum_visibility
	 */
	public function test_forum_post_in_query_returns_no_restricted_forums() {
		$posts = $this->create_visibility_test_posts();
		$this->set_current_user( 0 );

		$query = new WP_Query( array(
			'post_type' => bbp_get_forum_post_type(),
			'post__in'  => array( $posts['private_forum'], $posts['hidden_forum'] ),
			'fields'    => 'ids',
		) );

		$this->assertEmpty( $query->posts );
	}

	/**
	 * @covers ::bbp_pre_get_posts_normalize_forum_visibility
	 */
	public function test_mixed_post_in_query_preserves_non_forum_posts() {
		$posts   = $this->create_visibility_test_posts();
		$post_id = $this->factory->post->create();
		$this->set_current_user( 0 );

		$query = new WP_Query( array(
			'post_type'   => array( bbp_get_forum_post_type(), 'post' ),
			'post_status' => array( bbp_get_public_status_id(), bbp_get_hidden_status_id() ),
			'post__in'    => array( $post_id, $posts['public_forum'], $posts['hidden_forum'] ),
			'fields'      => 'ids',
		) );

		$this->assertEqualSets( array( $post_id, $posts['public_forum'] ), $query->posts );
	}

	/**
	 * @covers ::bbp_pre_get_posts_normalize_forum_visibility
	 */
	public function test_explicit_forum_id_query_includes_readable_forums() {
		$posts   = $this->create_visibility_test_posts();
		$user_id = $this->factory->user->create( array( 'role' => 'administrator' ) );

		bbp_set_user_role( $user_id, bbp_get_keymaster_role() );
		$this->set_current_user( $user_id );

		foreach ( array( 'public_forum', 'private_forum', 'hidden_forum' ) as $forum ) {
			$query = new WP_Query(
				array(
					'post_type' => bbp_get_forum_post_type(),
					'p'         => $posts[ $forum ],
				)
			);

			$this->assertSame( array( $posts[ $forum ] ), wp_list_pluck( $query->posts, 'ID' ) );
		}
	}

	/**
	 * @covers ::bbp_allow_forums_of_user
	 * @covers ::bbp_pre_get_posts_normalize_forum_visibility
	 * @covers ::_bbp_forum_visibility_where
	 */
	public function test_query_includes_descendant_content_for_parent_forum_moderator() {
		$posts   = $this->create_inherited_visibility_test_posts( bbp_get_hidden_status_id() );
		$user_id = $this->factory->user->create();

		bbp_set_user_role( $user_id, bbp_get_participant_role() );
		bbp_add_moderator( $posts['parent_id'], $user_id );
		$this->set_current_user( $user_id );

		$query = new WP_Query(
			array(
				'post_type'      => array( bbp_get_topic_post_type(), bbp_get_reply_post_type() ),
				'posts_per_page' => -1,
				'fields'         => 'ids',
			)
		);

		$this->assertContains( $posts['topic_id'], $query->posts );
		$this->assertContains( $posts['reply_id'], $query->posts );
	}

	/**
	 * @covers ::bbp_map_forum_meta_caps
	 * @covers ::bbp_is_forum_public
	 */
	public function test_read_forum_honors_filtered_public_statuses() {
		$forum_id = $this->factory->post->create(
			array(
				'post_author' => 0,
				'post_status' => 'draft',
				'post_type'   => bbp_get_forum_post_type(),
			)
		);
		$filter   = function( $statuses ) {
			$statuses[] = 'draft';

			return $statuses;
		};

		add_filter( 'bbp_get_public_forum_statuses', $filter );

		try {
			add_filter( 'bbp_allow_anonymous', '__return_true' );
			$this->assertTrue( bbp_is_forum_public( $forum_id, false ) );
			$this->assertSame( array( 'exist' ), bbp_map_forum_meta_caps( array(), 'read_forum', 0, array( $forum_id ) ) );
		} finally {
			remove_filter( 'bbp_allow_anonymous', '__return_true' );
			remove_filter( 'bbp_get_public_forum_statuses', $filter );
		}
	}

	/**
	 * @covers ::bbp_map_forum_meta_caps
	 * @covers ::bbp_get_excluded_forum_ids
	 */
	public function test_public_forum_tree_remains_readable() {
		$posts   = $this->create_inherited_visibility_test_posts( bbp_get_public_status_id() );
		$user_id = $this->factory->user->create();

		bbp_set_user_role( $user_id, bbp_get_participant_role() );
		$this->set_current_user( $user_id );

		$this->assertTrue( current_user_can( 'read_forum', $posts['child_id'] ) );
		$this->assertTrue( current_user_can( 'read_forum', $posts['grandchild_id'] ) );
		$this->assertEmpty( bbp_get_excluded_forum_ids() );
	}

	/**
	 * @covers ::bbp_get_unclassified_forum_ids
	 * @covers ::bbp_get_excluded_forum_ids
	 * @covers ::bbp_pre_get_posts_normalize_forum_visibility
	 */
	public function test_query_excludes_published_content_below_forums_with_unclassified_statuses() {
		foreach ( array( 'draft', 'pending', 'future', 'trash', 'bbp-unknown' ) as $status ) {
			$posts     = $this->create_inherited_visibility_test_posts( bbp_get_public_status_id() );
			$post_data = array(
				'ID'          => $posts['parent_id'],
				'post_status' => $status,
			);

			if ( 'future' === $status ) {
				$post_data['post_date']     = gmdate( 'Y-m-d H:i:s', time() + DAY_IN_SECONDS );
				$post_data['post_date_gmt'] = $post_data['post_date'];
			}

			wp_update_post( $post_data );
			$this->set_current_user( 0 );

			$this->assertSame( $status, get_post_status( $posts['parent_id'] ) );
			$this->assertEqualSets(
				array( $posts['parent_id'], $posts['child_id'], $posts['grandchild_id'] ),
				array_intersect( bbp_get_excluded_forum_ids(), array( $posts['parent_id'], $posts['child_id'], $posts['grandchild_id'] ) )
			);

			$query = new WP_Query( array(
				'post_type'      => array( bbp_get_topic_post_type(), bbp_get_reply_post_type() ),
				'posts_per_page' => -1,
				'fields'         => 'ids',
			) );

			$this->assertNotContains( $posts['topic_id'], $query->posts );
			$this->assertNotContains( $posts['reply_id'], $query->posts );
		}
	}

	/**
	 * @covers ::bbp_get_unclassified_forum_ids
	 */
	public function test_unclassified_forum_cache_invalidates_on_status_change() {
		$forum_id = $this->factory->forum->create();

		$this->assertNotContains( $forum_id, bbp_get_unclassified_forum_ids() );

		wp_update_post( array( 'ID' => $forum_id, 'post_status' => 'draft' ) );
		$this->assertContains( $forum_id, bbp_get_unclassified_forum_ids() );

		wp_update_post( array( 'ID' => $forum_id, 'post_status' => bbp_get_public_status_id() ) );
		$this->assertNotContains( $forum_id, bbp_get_unclassified_forum_ids() );
	}

	/**
	 * @covers ::bbp_get_unclassified_forum_ids
	 * @covers ::bbp_get_excluded_forum_ids
	 */
	public function test_unclassified_forum_status_comparison_is_binary() {
		$bbp_db   = bbp_db();
		$forum_id = $this->factory->forum->create();

		foreach ( array( 'Publish', 'publish ', 'Hidden' ) as $status ) {
			$bbp_db->update(
				$bbp_db->posts,
				array( 'post_status' => $status ),
				array( 'ID' => $forum_id )
			);
			clean_post_cache( $forum_id );

			$this->assertSame( $status, get_post_status( $forum_id ) );
			$this->assertContains( $forum_id, bbp_get_unclassified_forum_ids() );
			$this->assertContains( $forum_id, bbp_get_excluded_forum_ids() );
		}
	}

	/**
	 * @covers ::bbp_get_unclassified_forum_ids
	 * @covers ::bbp_get_excluded_forum_ids
	 * @covers ::bbp_pre_get_posts_normalize_forum_visibility
	 * @covers ::_bbp_forum_visibility_where
	 */
	public function test_mixed_search_and_direct_queries_exclude_content_below_unclassified_forum() {
		$posts   = $this->create_inherited_visibility_test_posts( bbp_get_public_status_id() );
		$marker  = 'unclassified-forum-query-marker';
		$post_id = $this->factory->post->create( array( 'post_content' => $marker ) );

		wp_update_post( array( 'ID' => $posts['parent_id'], 'post_status' => 'bbp-unknown' ) );
		wp_update_post( array( 'ID' => $posts['topic_id'], 'post_content' => $marker ) );
		wp_update_post( array( 'ID' => $posts['reply_id'], 'post_content' => $marker ) );
		$this->set_current_user( 0 );

		$query = new WP_Query( array(
			'post_type'      => array( 'post', bbp_get_topic_post_type(), bbp_get_reply_post_type() ),
			'posts_per_page' => -1,
			'fields'         => 'ids',
			's'              => $marker,
		) );

		$this->assertContains( $post_id, $query->posts );
		$this->assertNotContains( $posts['topic_id'], $query->posts );
		$this->assertNotContains( $posts['reply_id'], $query->posts );

		foreach ( array( 'topic_id', 'reply_id' ) as $post_key ) {
			$query = new WP_Query( array(
				'post_type' => get_post_type( $posts[ $post_key ] ),
				'p'         => $posts[ $post_key ],
			) );

			$this->assertEmpty( $query->posts );
		}
	}

	/**
	 * @covers ::bbp_allow_forums_of_user
	 * @covers ::bbp_get_excluded_forum_ids
	 * @covers ::bbp_map_forum_meta_caps
	 * @covers ::bbp_pre_get_posts_normalize_forum_visibility
	 */
	public function test_participant_cannot_query_published_content_below_draft_forum() {
		$posts   = $this->create_inherited_visibility_test_posts( bbp_get_public_status_id() );
		$user_id = $this->factory->user->create();

		wp_update_post( array( 'ID' => $posts['parent_id'], 'post_status' => 'draft' ) );
		bbp_set_user_role( $user_id, bbp_get_participant_role() );
		$this->set_current_user( $user_id );

		$query = new WP_Query( array(
			'post_type'      => array( bbp_get_topic_post_type(), bbp_get_reply_post_type() ),
			'posts_per_page' => -1,
			'fields'         => 'ids',
		) );

		$this->assertContains( $posts['parent_id'], bbp_get_excluded_forum_ids() );
		$this->assertNotContains( $posts['topic_id'], $query->posts );
		$this->assertNotContains( $posts['reply_id'], $query->posts );
	}

	/**
	 * @covers ::bbp_allow_forums_of_user
	 * @covers ::bbp_get_excluded_forum_ids
	 * @covers ::bbp_pre_get_posts_normalize_forum_visibility
	 */
	public function test_moderator_can_query_published_content_below_draft_forum() {
		$posts   = $this->create_inherited_visibility_test_posts( bbp_get_public_status_id() );
		$user_id = $this->factory->user->create();
		$setting = get_option( '_bbp_allow_forum_mods', null );

		wp_update_post( array( 'ID' => $posts['parent_id'], 'post_status' => 'draft' ) );
		bbp_set_user_role( $user_id, bbp_get_moderator_role() );
		$this->set_current_user( $user_id );
		update_option( '_bbp_allow_forum_mods', false );

		try {
			$query = new WP_Query( array(
				'post_type'      => array( bbp_get_topic_post_type(), bbp_get_reply_post_type() ),
				'posts_per_page' => -1,
				'fields'         => 'ids',
			) );

			$this->assertNotContains( $posts['parent_id'], bbp_get_excluded_forum_ids() );
			$this->assertContains( $posts['topic_id'], $query->posts );
			$this->assertContains( $posts['reply_id'], $query->posts );
		} finally {
			if ( null === $setting ) {
				delete_option( '_bbp_allow_forum_mods' );
			} else {
				update_option( '_bbp_allow_forum_mods', $setting );
			}
		}
	}

	/**
	 * @covers ::bbp_get_unclassified_forum_ids
	 * @covers ::bbp_get_excluded_forum_ids
	 * @covers ::bbp_pre_get_posts_normalize_forum_visibility
	 */
	public function test_unclassified_forum_ids_use_filtered_public_forum_statuses() {
		$posts        = $this->create_inherited_visibility_test_posts( bbp_get_public_status_id() );
		$forum_id     = $posts['parent_id'];
		$label_filter = function( $visibilities ) {
			$visibilities['draft'] = 'Draft';

			return $visibilities;
		};
		$public_filter = function( $statuses ) {
			$statuses[] = 'draft';

			return $statuses;
		};

		wp_update_post( array( 'ID' => $forum_id, 'post_status' => 'draft' ) );
		$this->assertContains( $forum_id, bbp_get_unclassified_forum_ids() );

		add_filter( 'bbp_get_forum_visibilities', $label_filter );
		try {
			$this->set_current_user( 0 );
			$this->assertContains( $forum_id, bbp_get_unclassified_forum_ids() );
			$this->assertContains( $forum_id, bbp_get_excluded_forum_ids() );

			$query = new WP_Query( array(
				'post_type'      => array( bbp_get_topic_post_type(), bbp_get_reply_post_type() ),
				'posts_per_page' => -1,
				'fields'         => 'ids',
			) );

			$this->assertNotContains( $posts['topic_id'], $query->posts );
			$this->assertNotContains( $posts['reply_id'], $query->posts );
		} finally {
			remove_filter( 'bbp_get_forum_visibilities', $label_filter );
		}

		add_filter( 'bbp_get_public_forum_statuses', $public_filter );

		try {
			$this->set_current_user( 0 );
			$this->assertNotContains( $forum_id, bbp_get_unclassified_forum_ids() );
			$this->assertNotContains( $forum_id, bbp_get_excluded_forum_ids() );

			$query = new WP_Query( array(
				'post_type'      => array( bbp_get_topic_post_type(), bbp_get_reply_post_type() ),
				'posts_per_page' => -1,
				'fields'         => 'ids',
			) );

			$this->assertContains( $posts['topic_id'], $query->posts );
			$this->assertContains( $posts['reply_id'], $query->posts );
		} finally {
			remove_filter( 'bbp_get_public_forum_statuses', $public_filter );
		}
	}

	/**
	 * @covers ::bbp_allow_forums_of_user
	 * @covers ::bbp_get_excluded_forum_ids
	 * @covers ::bbp_map_forum_meta_caps
	 */
	public function test_hidden_forum_reader_can_read_unclassified_forum_tree() {
		$posts   = $this->create_inherited_visibility_test_posts( bbp_get_public_status_id() );
		$user_id = $this->factory->user->create();

		wp_update_post( array( 'ID' => $posts['parent_id'], 'post_status' => 'draft' ) );
		bbp_set_user_role( $user_id, bbp_get_participant_role() );
		$user = get_user_by( 'id', $user_id );
		$user->add_cap( 'read_hidden_forums' );
		$this->set_current_user( $user_id );

		$this->assertTrue( current_user_can( 'read_forum', $posts['parent_id'] ) );
		$this->assertEmpty(
			array_intersect(
				bbp_get_excluded_forum_ids(),
				array( $posts['parent_id'], $posts['child_id'], $posts['grandchild_id'] )
			)
		);
	}

	/**
	 * @covers ::bbp_get_excluded_forum_ids
	 */
	public function test_bbp_get_excluded_forum_ids_traverses_non_countable_descendants() {
		foreach ( array( 'trash', 'pending', 'draft' ) as $status ) {
			$posts = $this->create_inherited_visibility_test_posts( bbp_get_hidden_status_id() );

			wp_update_post( array(
				'ID'          => $posts['child_id'],
				'post_status' => $status,
			) );
			$this->set_current_user( 0 );

			$this->assertEqualSets(
				array( $posts['parent_id'], $posts['child_id'], $posts['grandchild_id'] ),
				array_intersect( bbp_get_excluded_forum_ids(), array( $posts['parent_id'], $posts['child_id'], $posts['grandchild_id'] ) )
			);

			$query = new WP_Query( array(
				'post_type'      => array( bbp_get_topic_post_type(), bbp_get_reply_post_type() ),
				'posts_per_page' => -1,
				'fields'         => 'ids',
			) );

			$this->assertNotContains( $posts['topic_id'], $query->posts );
			$this->assertNotContains( $posts['reply_id'], $query->posts );
		}
	}

	/**
	 * @covers ::bbp_get_excluded_forum_ids
	 * @covers ::bbp_forum_query_all_descendant_ids
	 */
	public function test_large_exclusion_list_includes_descendants_and_invalidates_cache() {
		global $wpdb;

		$parents    = $this->factory->forum->create_many( 21 );
		$child      = $this->factory->forum->create( array( 'post_parent' => $parents[0] ) );
		$grandchild = $this->factory->forum->create( array( 'post_parent' => $child ) );

		foreach ( $parents as $parent_id ) {
			bbp_hide_forum( $parent_id );
		}
		wp_trash_post( $child );
		$this->set_current_user( 0 );

		$child_queries = array();
		$record_query  = function( $query ) use ( &$child_queries ) {
			if ( false !== strpos( $query, 'SELECT ID FROM' ) && false !== strpos( $query, 'post_parent' ) ) {
				$child_queries[] = $query;
			}

			return $query;
		};
		wp_cache_flush();
		add_filter( 'query', $record_query );
		$start_queries = $wpdb->num_queries;
		$excluded = bbp_get_excluded_forum_ids();
		$query_count = $wpdb->num_queries - $start_queries;
		remove_filter( 'query', $record_query );
		$this->assertLessThan( 5, count( $child_queries ) );
		$this->assertLessThan( 15, $query_count );
		$this->assertContains( $child, $excluded );
		$this->assertContains( $grandchild, $excluded );

		$new_child = $this->factory->forum->create( array( 'post_parent' => $parents[0] ) );
		$this->assertContains( $new_child, bbp_get_excluded_forum_ids() );
	}

	/**
	 * @covers ::bbp_repair_forum_visibility
	 */
	public function test_bbp_repair_forum_visibility() {
		$private_id = $this->factory->forum->create( array(
			'post_status' => bbp_get_private_status_id(),
		) );
		$hidden_id = $this->factory->forum->create( array(
			'post_status' => bbp_get_hidden_status_id(),
		) );

		delete_option( '_bbp_private_forums' );
		delete_option( '_bbp_hidden_forums' );

		$this->assertTrue( bbp_repair_forum_visibility() );
		$this->assertSame( array( $private_id ), bbp_get_private_forum_ids() );
		$this->assertSame( array( $hidden_id ), bbp_get_hidden_forum_ids() );
	}

	/**
	 * @covers ::bbp_get_forum_visibilities
	 */
	public function test_bbp_get_forum_visibilities() {
		$this->assertSame(
			array(
				bbp_get_public_status_id()  => 'Public',
				bbp_get_private_status_id() => 'Private',
				bbp_get_hidden_status_id()  => 'Hidden',
			),
			bbp_get_forum_visibilities()
		);
	}

	/**
	 * @covers ::bbp_get_forum_visibility_ids
	 */
	public function test_bbp_get_forum_visibility_ids() {
		$filter = function( $visibilities ) {
			$visibilities['archived'] = 'Archived';

			return $visibilities;
		};

		$this->assertSame(
			array(
				bbp_get_public_status_id(),
				bbp_get_private_status_id(),
				bbp_get_hidden_status_id(),
			),
			bbp_get_forum_visibility_ids()
		);

		add_filter( 'bbp_get_forum_visibilities', $filter );
		try {
			$this->assertSame(
				array(
					bbp_get_public_status_id(),
					bbp_get_private_status_id(),
					bbp_get_hidden_status_id(),
					'archived',
				),
				bbp_get_forum_visibility_ids()
			);
		} finally {
			remove_filter( 'bbp_get_forum_visibilities', $filter );
		}
	}

	/**
	 * @covers ::bbp_get_hidden_forum_ids
	 */
	public function test_bbp_get_hidden_forum_ids() {
		update_option( '_bbp_hidden_forums', array( 3, '4', 3 ) );

		$this->assertSame( array( 3, 4 ), bbp_get_hidden_forum_ids() );
	}

	/**
	 * @covers ::bbp_get_private_forum_ids
	 */
	public function test_bbp_get_private_forum_ids() {
		update_option( '_bbp_private_forums', array( 5, '6', 5 ) );

		$this->assertSame( array( 5, 6 ), bbp_get_private_forum_ids() );
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
	 */
	public function test_bbp_pre_get_posts_normalize_forum_visibility_with_or_meta_query() {
		$posts           = $this->create_visibility_test_posts();
		$second_topic    = $this->factory->topic->create( array(
			'post_parent' => $posts['public_forum'],
			'topic_meta'  => array( 'forum_id' => $posts['public_forum'] ),
		) );
		$unmatched_topic = $this->factory->topic->create( array(
			'post_parent' => $posts['public_forum'],
			'topic_meta'  => array( 'forum_id' => $posts['public_forum'] ),
		) );

		foreach ( array( 'public', 'private', 'hidden' ) as $visibility ) {
			update_post_meta( $posts[ "{$visibility}_topic" ], 'bbp_visibility_test', 'match' );
			update_post_meta( $posts[ "{$visibility}_reply" ], 'bbp_visibility_test', 'match' );
		}
		update_post_meta( $second_topic, 'bbp_visibility_other', 'missing' );

		$this->set_current_user( 0 );

		$query = new WP_Query(
			array(
				'post_type'      => array( bbp_get_topic_post_type(), bbp_get_reply_post_type() ),
				'post_status'    => bbp_get_public_status_id(),
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'meta_query'    => array(
					'relation' => 'OR',
					array(
						'key'   => 'bbp_visibility_test',
						'value' => 'match',
					),
					array(
						'key'   => 'bbp_visibility_other',
						'value' => 'missing',
					),
				),
			)
		);

		$this->assertContains( $posts['public_topic'], $query->posts );
		$this->assertContains( $posts['public_reply'], $query->posts );
		$this->assertContains( $second_topic, $query->posts );
		$this->assertNotContains( $unmatched_topic, $query->posts );
		$this->assertNotContains( $posts['private_topic'], $query->posts );
		$this->assertNotContains( $posts['private_reply'], $query->posts );
		$this->assertNotContains( $posts['hidden_topic'], $query->posts );
		$this->assertNotContains( $posts['hidden_reply'], $query->posts );
	}

	/**
	 * @covers ::bbp_pre_get_posts_normalize_forum_visibility
	 */
	public function test_bbp_pre_get_posts_normalize_forum_visibility_with_mixed_bbp_post_types() {
		$posts = $this->create_status_test_posts();

		$this->set_current_user( 0 );

		$query = new WP_Query(
			array(
				'post_type'      => bbp_get_post_types(),
				'post_status'    => bbp_get_public_topic_statuses(),
				'posts_per_page' => -1,
				'fields'         => 'ids',
			)
		);

		$this->assertContains( $posts['forum'], $query->posts );
		$this->assertContains( $posts['public_topic'], $query->posts );
		$this->assertContains( $posts['public_reply'], $query->posts );
		$this->assertContains( $posts['closed_topic'], $query->posts );
		$this->assertContains( $posts['closed_reply'], $query->posts );
		$this->assertNotContains( $posts['private_topic'], $query->posts );
		$this->assertNotContains( $posts['hidden_topic'], $query->posts );
		$this->assertNotContains( $posts['private_reply'], $query->posts );
		$this->assertNotContains( $posts['hidden_reply'], $query->posts );
	}

	/**
	 * @covers ::bbp_pre_get_posts_normalize_forum_visibility
	 */
	public function test_bbp_pre_get_posts_normalize_forum_visibility_with_any_status() {
		$posts = $this->create_status_test_posts();

		$this->set_current_user( 0 );

		$query = new WP_Query(
			array(
				'post_type'      => bbp_get_post_types(),
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'fields'         => 'ids',
			)
		);

		$this->assertContains( $posts['public_topic'], $query->posts );
		$this->assertContains( $posts['public_reply'], $query->posts );
		$this->assertContains( $posts['private_topic'], $query->posts );
		$this->assertContains( $posts['private_reply'], $query->posts );
		$this->assertNotContains( $posts['hidden_topic'], $query->posts );
		$this->assertNotContains( $posts['hidden_reply'], $query->posts );
	}

	/**
	 * @covers ::bbp_pre_get_posts_normalize_forum_visibility
	 */
	public function test_bbp_pre_get_posts_normalize_forum_visibility_preserves_private_status() {
		$posts   = $this->create_status_test_posts();
		$user_id = $this->factory->user->create();

		bbp_set_user_role( $user_id, bbp_get_moderator_role() );
		$this->set_current_user( $user_id );
		$this->assertTrue( current_user_can( 'read_private_topics' ) );

		$query = new WP_Query(
			array(
				'post_type'      => bbp_get_post_types(),
				'post_status'    => array_merge( bbp_get_public_topic_statuses(), array( bbp_get_private_status_id() ) ),
				'posts_per_page' => -1,
				'fields'         => 'ids',
			)
		);

		$this->assertContains( $posts['public_topic'], $query->posts );
		$this->assertContains( $posts['public_reply'], $query->posts );
		$this->assertContains( $posts['private_topic'], $query->posts );
		$this->assertContains( $posts['private_reply'], $query->posts );
		$this->assertNotContains( $posts['hidden_topic'], $query->posts );
		$this->assertNotContains( $posts['hidden_reply'], $query->posts );
	}

	/**
	 * @covers ::bbp_pre_get_posts_normalize_forum_visibility
	 */
	public function test_bbp_pre_get_posts_normalize_forum_visibility_preserves_hidden_status() {
		$posts   = $this->create_status_test_posts();
		$user_id = $this->factory->user->create();
		$user     = get_user_by( 'id', $user_id );

		$user->add_cap( 'read_hidden_topics' );
		$this->set_current_user( $user_id );
		$this->assertTrue( current_user_can( 'read_hidden_topics' ) );

		$query = new WP_Query(
			array(
				'post_type'      => bbp_get_post_types(),
				'post_status'    => array_merge( bbp_get_public_topic_statuses(), array( bbp_get_hidden_status_id() ) ),
				'posts_per_page' => -1,
				'fields'         => 'ids',
			)
		);

		$this->assertContains( $posts['public_topic'], $query->posts );
		$this->assertContains( $posts['public_reply'], $query->posts );
		$this->assertContains( $posts['hidden_topic'], $query->posts );
		$this->assertContains( $posts['hidden_reply'], $query->posts );
		$this->assertNotContains( $posts['private_topic'], $query->posts );
		$this->assertNotContains( $posts['private_reply'], $query->posts );
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
