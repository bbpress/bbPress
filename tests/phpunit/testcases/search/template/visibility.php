<?php

/**
 * Tests that search respects parent topic visibility.
 *
 * @group search
 * @group visibility
 */
class BBP_Tests_Search_Template_Visibility extends BBP_UnitTestCase {
	private $old_search_query;

	public function setUp(): void {
		parent::setUp();
		$this->old_search_query = bbpress()->search_query;
	}

	public function tearDown(): void {
		bbpress()->search_query = $this->old_search_query;
		$this->set_current_user( 0 );

		parent::tearDown();
	}

	/**
	 * @covers ::bbp_has_search_results
	 */
	public function test_search_excludes_published_reply_after_topic_is_unapproved() {
		$forum_id = $this->factory->forum->create();
		$topic_id = $this->factory->topic->create(
			array(
				'post_parent' => $forum_id,
				'topic_meta'  => array( 'forum_id' => $forum_id ),
			)
		);
		$reply_id = $this->factory->reply->create(
			array(
				'post_parent'  => $topic_id,
				'post_content' => 'Private search sentinel 7138',
				'reply_meta'   => array( 'forum_id' => $forum_id, 'topic_id' => $topic_id ),
			)
		);
		$this->set_current_user( 0 );
		$this->assertTrue( bbp_has_search_results( array( 's' => 'Private search sentinel 7138' ) ) );
		$this->assertContains( $reply_id, wp_list_pluck( bbpress()->search_query->posts, 'ID' ) );

		bbp_unapprove_topic( $topic_id );

		$this->assertSame( bbp_get_public_status_id(), get_post_status( $reply_id ) );
		$this->assertFalse( bbp_has_search_results( array( 's' => 'Private search sentinel 7138' ) ) );
		$this->assertSame( 0, (int) bbpress()->search_query->found_posts );
	}

	/**
	 * @covers ::bbp_has_search_results
	 */
	public function test_private_topic_reply_is_visible_only_to_authorized_search() {
		$forum_id = $this->factory->forum->create();
		$topic_id = $this->factory->topic->create( array( 'post_parent' => $forum_id, 'post_status' => bbp_get_private_status_id(), 'topic_meta' => array( 'forum_id' => $forum_id ) ) );
		$reply_id = $this->factory->reply->create( array( 'post_parent' => $topic_id, 'post_content' => 'Private search sentinel 9284', 'reply_meta' => array( 'forum_id' => $forum_id, 'topic_id' => $topic_id ) ) );

		$this->set_current_user( 0 );
		$this->assertFalse( bbp_has_search_results( array( 's' => 'Private search sentinel 9284' ) ) );

		$user_id = $this->factory->user->create();
		bbp_set_user_role( $user_id, bbp_get_moderator_role() );
		$this->set_current_user( $user_id );
		$this->assertTrue( bbp_has_search_results( array( 's' => 'Private search sentinel 9284' ) ) );
		$this->assertContains( $reply_id, wp_list_pluck( bbpress()->search_query->posts, 'ID' ) );
	}

	/**
	 * @covers ::bbp_has_search_results
	 * @covers ::bbp_get_password_protected_forum_ids
	 * @covers ::bbp_password_protected_search_where
	 * @covers ::bbp_pre_get_posts_normalize_forum_visibility
	 * @covers ::bbp_public_topic_replies_where
	 */
	public function test_search_excludes_password_protected_content_for_all_roles() {
		$forum_id = $this->factory->forum->create(
			array(
				'post_content'  => 'Protected forum search sentinel 8143',
				'post_password' => 'forum-secret',
			)
		);
		$topic_id = $this->factory->topic->create(
			array(
				'post_content' => 'Protected topic search sentinel 8462',
				'post_parent'  => $forum_id,
				'topic_meta'   => array( 'forum_id' => $forum_id ),
			)
		);
		$reply_id = $this->factory->reply->create(
			array(
				'post_parent'  => $topic_id,
				'post_content' => 'Protected search oracle sentinel 4816',
				'reply_meta'   => array( 'forum_id' => $forum_id, 'topic_id' => $topic_id ),
			)
		);
		$child_forum_id = $this->factory->forum->create(
			array( 'post_parent' => $forum_id )
		);
		$child_topic_id = $this->factory->topic->create(
			array(
				'post_parent' => $child_forum_id,
				'topic_meta'  => array( 'forum_id' => $child_forum_id ),
			)
		);
		$child_reply_id = $this->factory->reply->create(
			array(
				'post_parent'  => $child_topic_id,
				'post_content' => 'Nested protected search sentinel 5937',
				'reply_meta'   => array( 'forum_id' => $child_forum_id, 'topic_id' => $child_topic_id ),
			)
		);
		$draft_forum_id = $this->factory->forum->create(
			array(
				'post_parent' => $forum_id,
				'post_status' => 'draft',
			)
		);
		$deep_forum_id = $this->factory->forum->create(
			array( 'post_parent' => $draft_forum_id )
		);
		$deep_topic_id = $this->factory->topic->create(
			array(
				'post_parent' => $deep_forum_id,
				'topic_meta'  => array( 'forum_id' => $deep_forum_id ),
			)
		);
		$deep_reply_id = $this->factory->reply->create(
			array(
				'post_parent'  => $deep_topic_id,
				'post_content' => 'Deep protected search sentinel 9164',
				'reply_meta'   => array( 'forum_id' => $deep_forum_id, 'topic_id' => $deep_topic_id ),
			)
		);
		$public_forum_id = $this->factory->forum->create();
		$protected_topic_id = $this->factory->topic->create(
			array(
				'post_parent'   => $public_forum_id,
				'post_password' => 'topic-secret',
				'topic_meta'    => array( 'forum_id' => $public_forum_id ),
			)
		);
		$topic_reply_id = $this->factory->reply->create(
			array(
				'post_parent'  => $protected_topic_id,
				'post_content' => 'Topic protected search sentinel 6249',
				'reply_meta'   => array( 'forum_id' => $public_forum_id, 'topic_id' => $protected_topic_id ),
			)
		);
		$public_topic_id = $this->factory->topic->create(
			array(
				'post_parent' => $public_forum_id,
				'topic_meta'  => array( 'forum_id' => $public_forum_id ),
			)
		);
		$public_reply_id = $this->factory->reply->create(
			array(
				'post_parent'  => $public_topic_id,
				'post_content' => 'Public search sentinel 7351',
				'reply_meta'   => array( 'forum_id' => $public_forum_id, 'topic_id' => $public_topic_id ),
			)
		);
		$public_post_id = $this->factory->post->create(
			array( 'post_content' => 'Topic protected search sentinel 6249' )
		);
		$restricted = array(
			'Protected forum search sentinel 8143' => $forum_id,
			'Protected topic search sentinel 8462' => $topic_id,
			'Protected search oracle sentinel 4816' => $reply_id,
			'Nested protected search sentinel 5937' => $child_reply_id,
			'Deep protected search sentinel 9164'   => $deep_reply_id,
			'Topic protected search sentinel 6249'  => $topic_reply_id,
		);
		$user_ids = array( 0 );
		$this->assertFalse( bbp_has_search_results( array( 's' => 'Protected search oracle sentinel 4816' ) ) );
		$this->assertStringContainsString( 'post_password ) = 0', bbpress()->search_query->request );

		$custom_query = new WP_Query(
			array(
				'post_type' => bbp_get_reply_post_type(),
				's'         => 'Topic protected search sentinel 6249',
			)
		);
		$this->assertNotContains( $topic_reply_id, wp_list_pluck( $custom_query->posts, 'ID' ) );

		$mixed_query = new WP_Query(
			array(
				'post_type' => array( 'post', bbp_get_reply_post_type() ),
				's'         => 'Topic protected search sentinel 6249',
			)
		);
		$this->assertContains( $public_post_id, wp_list_pluck( $mixed_query->posts, 'ID' ) );
		$this->assertNotContains( $topic_reply_id, wp_list_pluck( $mixed_query->posts, 'ID' ) );

		$suppressed_query = new WP_Query(
			array(
				'post_type'        => bbp_get_reply_post_type(),
				's'                => 'Topic protected search sentinel 6249',
				'suppress_filters' => true,
			)
		);
		$this->assertEmpty( $suppressed_query->posts );

		foreach ( array( '', bbp_get_participant_role(), bbp_get_moderator_role(), bbp_get_keymaster_role() ) as $role ) {
			$user_id = $this->factory->user->create();
			$user    = new WP_User( $user_id );

			if ( empty( $role ) ) {
				$user->set_role( '' );
			} else {
				bbp_set_user_role( $user_id, $role );
			}

			$user_ids[] = $user_id;
		}

		foreach ( $user_ids as $user_id ) {
			$this->set_current_user( $user_id );

			foreach ( $restricted as $search_term => $restricted_reply_id ) {
				$this->assertFalse( bbp_has_search_results( array( 's' => $search_term ) ) );
				$this->assertNotContains( $restricted_reply_id, wp_list_pluck( bbpress()->search_query->posts, 'ID' ) );
			}

			$this->assertTrue( bbp_has_search_results( array( 's' => 'Public search sentinel 7351' ) ) );
			$this->assertContains( $public_reply_id, wp_list_pluck( bbpress()->search_query->posts, 'ID' ) );
		}

		require_once ABSPATH . WPINC . '/class-phpass.php';
		$hasher     = new PasswordHash( 8, true );
		$cookie     = 'wp-postpass_' . COOKIEHASH;
		$old_cookie = isset( $_COOKIE[ $cookie ] ) ? $_COOKIE[ $cookie ] : null;

		try {
			$this->set_current_user( 0 );

			foreach ( array( 'forum-secret', 'topic-secret' ) as $password ) {
				$_COOKIE[ $cookie ] = $hasher->HashPassword( $password );

				foreach ( $restricted as $search_term => $restricted_post_id ) {
					$this->assertFalse( bbp_has_search_results( array( 's' => $search_term ) ) );
					$this->assertNotContains( $restricted_post_id, wp_list_pluck( bbpress()->search_query->posts, 'ID' ) );
				}
			}
		} finally {
			if ( null === $old_cookie ) {
				unset( $_COOKIE[ $cookie ] );
			} else {
				$_COOKIE[ $cookie ] = $old_cookie;
			}
		}
	}

	/**
	 * @covers ::bbp_pre_get_posts_normalize_forum_visibility
	 * @covers ::bbp_password_protected_search_where
	 */
	public function test_search_terms_remain_protected_in_singular_requests() {
		$protected = $this->create_search_tree( 'forum-secret' );
		$public    = $this->create_search_tree();

		foreach ( array( $protected, $public ) as $tree ) {
			foreach ( array( 'forum', 'topic', 'reply' ) as $type ) {
				$post_id = $tree[ $type ];
				$post    = get_post( $post_id );

				foreach ( array( 'p' => $post_id, 'page_id' => $post_id, 'name' => $post->post_name ) as $selector => $value ) {
					foreach ( array( 'sentinel', '0', 'absentterm' ) as $term ) {
						$args = array(
							'post_type' => $post->post_type,
							$selector   => $value,
							's'         => $term,
						);
						$query = new WP_Query( $args );
						$ids   = wp_list_pluck( $query->posts, 'ID' );

						if ( ( $tree === $public ) && ( 'absentterm' !== $term ) ) {
							$this->assertContains( $post_id, $ids );
						} else {
							$this->assertEmpty( $ids );
							$this->assertSame( 0, (int) $query->found_posts );
						}

						// Explicit IDs must not override the fail-closed fallback.
						$args['suppress_filters'] = true;
						$suppressed = new WP_Query( $args );
						$this->assertEmpty( $suppressed->posts );
					}
				}

				// A request with no search term retains normal direct navigation.
				$direct = new WP_Query( array( 'post_type' => $post->post_type, 'p' => $post_id ) );
				$this->assertContains( $post_id, wp_list_pluck( $direct->posts, 'ID' ) );
			}
		}

		$attachment = new WP_Query( array( 'post_type' => bbp_get_reply_post_type(), 'attachment_id' => $protected['reply'], 's' => 'sentinel', 'suppress_filters' => true ) );
		$this->assertEmpty( $attachment->posts );

		// Exercise the actual public main-query route, not only secondary queries.
		$register_blocks = remove_action( 'bbp_register', 'bbp_register_blocks', 10 );
		try {
			$this->go_to( add_query_arg( array( 'post_type' => bbp_get_reply_post_type(), 'p' => $protected['reply'], 's' => 'sentinel' ), home_url( '/' ) ) );
			$this->assertEmpty( $GLOBALS['wp_query']->posts );
			$this->go_to( add_query_arg( array( 'post_type' => bbp_get_reply_post_type(), 'p' => $public['reply'], 's' => 'sentinel' ), home_url( '/' ) ) );
			$this->assertContains( $public['reply'], wp_list_pluck( $GLOBALS['wp_query']->posts, 'ID' ) );
		} finally {
			if ( $register_blocks ) {
				add_action( 'bbp_register', 'bbp_register_blocks', 10 );
			}
		}
	}

	/**
	 * @covers ::bbp_password_protected_search_where
	 * @covers ::bbp_public_topic_replies_where
	 * @covers ::_bbp_forum_visibility_where
	 */
	public function test_search_checks_canonical_ancestry_and_all_forum_metadata() {
		$protected = $this->create_search_tree( 'forum-secret' );
		$public    = $this->create_search_tree();
		$post_id   = $this->factory->post->create( array( 'post_content' => 'Search ancestry sentinel 0' ) );

		foreach ( array( 'duplicate', 'stale', 'missing', 'fallback' ) as $metadata ) {
			foreach ( array( 'topic', 'reply' ) as $type ) {
				delete_post_meta( $protected[ $type ], '_bbp_forum_id' );

				if ( 'missing' !== $metadata ) {
					add_post_meta( $protected[ $type ], '_bbp_forum_id', ( 'fallback' === $metadata ) ? $protected['forum'] : $public['forum'] );
				}
				if ( 'duplicate' === $metadata ) {
					add_post_meta( $protected[ $type ], '_bbp_forum_id', $protected['forum'] );
				}
			}

			if ( 'fallback' === $metadata ) {
				wp_update_post( array( 'ID' => $protected['topic'], 'post_parent' => 0 ) );
			}

			foreach ( array( bbp_get_post_types(), array_merge( array( 'post' ), bbp_get_post_types() ) ) as $post_types ) {
				$query = new WP_Query( array( 'post_type' => $post_types, 's' => 'sentinel', 'posts_per_page' => -1 ) );
				$ids   = wp_list_pluck( $query->posts, 'ID' );

				foreach ( $protected as $protected_id ) {
					$this->assertNotContains( $protected_id, $ids );
				}
				foreach ( $public as $public_id ) {
					$this->assertContains( $public_id, $ids );
				}
				if ( in_array( 'post', $post_types, true ) ) {
					$this->assertContains( $post_id, $ids );
					$this->assertSame( 4, (int) $query->found_posts );
				} else {
					$this->assertSame( 3, (int) $query->found_posts );
				}
			}
		}
	}

	/**
	 * @covers ::bbp_pre_get_posts_normalize_forum_visibility
	 */
	public function test_search_excludes_whitespace_only_passwords() {
		foreach ( array( 'forum', 'topic', 'reply' ) as $type ) {
			$tree = $this->create_search_tree();
			wp_update_post( array( 'ID' => $tree[ $type ], 'post_password' => '   ' ) );
			$this->assertTrue( post_password_required( $tree[ $type ] ) );

			$query = new WP_Query( array( 'post_type' => bbp_get_post_types(), 'post__in' => array_values( $tree ), 's' => 'sentinel' ) );
			$ids   = wp_list_pluck( $query->posts, 'ID' );
			$types = array_keys( $tree );
			$index = array_search( $type, $types, true );

			foreach ( array_slice( $tree, $index ) as $protected_id ) {
				$this->assertNotContains( $protected_id, $ids );
			}
		}
	}

	/**
	 * @covers ::bbp_pre_get_posts_normalize_forum_visibility
	 */
	public function test_authorized_admin_search_does_not_require_a_status_parameter() {
		$protected   = $this->create_search_tree( 'forum-secret' );
		$old_request = $_REQUEST;
		$old_pagenow = isset( $GLOBALS['pagenow'] ) ? $GLOBALS['pagenow'] : null;
		$old_query   = $GLOBALS['wp_the_query'];

		try {
			unset( $_REQUEST['post_status'] );
			set_current_screen( 'edit-topic' );
			$GLOBALS['pagenow'] = 'edit.php';

			foreach ( array( bbp_get_moderator_role(), bbp_get_keymaster_role() ) as $role ) {
				$user_id = $this->factory->user->create();
				bbp_set_user_role( $user_id, $role );
				$this->set_current_user( $user_id );
				$query = new WP_Query();
				$GLOBALS['wp_the_query'] = $query;
				$query->query( array( 'post_type' => bbp_get_topic_post_type(), 's' => 'sentinel' ) );
				$this->assertContains( $protected['topic'], wp_list_pluck( $query->posts, 'ID' ) );

				$secondary = new WP_Query( array( 'post_type' => bbp_get_topic_post_type(), 's' => 'sentinel' ) );
				$this->assertEmpty( $secondary->posts );

				// Admin context alone does not exempt an unauthorized main query.
				$this->set_current_user( 0 );
				$query = new WP_Query();
				$GLOBALS['wp_the_query'] = $query;
				$query->query( array( 'post_type' => bbp_get_topic_post_type(), 's' => 'sentinel' ) );
				$this->assertEmpty( $query->posts );
			}
		} finally {
			$_REQUEST = $old_request;
			$GLOBALS['wp_the_query'] = $old_query;
			$GLOBALS['pagenow'] = $old_pagenow;
			set_current_screen( 'front' );
		}
	}

	/**
	 * @covers ::bbp_get_password_protected_forum_ids
	 */
	public function test_password_forum_lookup_caches_empty_results_and_invalidates_with_posts() {
		global $wpdb;

		$forum_id = $this->factory->forum->create();
		$this->assertSame( array(), bbp_get_password_protected_forum_ids() );
		$queries = $wpdb->num_queries;
		$this->assertSame( array(), bbp_get_password_protected_forum_ids() );
		$this->assertSame( $queries, $wpdb->num_queries );

		wp_update_post( array( 'ID' => $forum_id, 'post_password' => 'forum-secret' ) );
		$this->assertContains( $forum_id, bbp_get_password_protected_forum_ids() );
		$queries = $wpdb->num_queries;
		$this->assertContains( $forum_id, bbp_get_password_protected_forum_ids() );
		$this->assertSame( $queries, $wpdb->num_queries );

		$child_id = $this->factory->forum->create( array( 'post_parent' => $forum_id ) );
		$this->assertContains( $child_id, bbp_get_password_protected_forum_ids() );

		wp_update_post( array( 'ID' => $child_id, 'post_parent' => 0 ) );
		$this->assertNotContains( $child_id, bbp_get_password_protected_forum_ids() );

		wp_update_post( array( 'ID' => $forum_id, 'post_password' => '' ) );
		$this->assertSame( array(), bbp_get_password_protected_forum_ids() );
	}

	/**
	 * @group multisite
	 * @covers ::bbp_get_password_protected_forum_ids
	 */
	public function test_password_forum_lookup_is_scoped_to_the_current_site() {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'Requires multisite.' );
		}

		$forum_id = $this->factory->forum->create( array( 'post_password' => 'forum-secret' ) );
		$site_id  = $this->factory->blog->create();
		$this->assertContains( $forum_id, bbp_get_password_protected_forum_ids() );

		switch_to_blog( $site_id );
		try {
			$this->assertSame( array(), bbp_get_password_protected_forum_ids() );
			$other_id = $this->factory->forum->create( array( 'post_password' => 'other-secret' ) );
			$this->assertSame( array( $other_id ), bbp_get_password_protected_forum_ids() );
		} finally {
			restore_current_blog();
		}

		$this->assertSame( array( $forum_id ), bbp_get_password_protected_forum_ids() );
	}

	/**
	 * @covers ::bbp_password_protected_search_where
	 * @covers ::bbp_public_topic_replies_where
	 */
	public function test_password_search_preserves_secondary_reply_status_opt_ins() {
		$public  = $this->create_search_tree();
		$private = $this->create_search_tree();
		$pending = $this->create_search_tree();
		wp_update_post( array( 'ID' => $private['topic'], 'post_status' => bbp_get_private_status_id() ) );
		wp_update_post( array( 'ID' => $pending['topic'], 'post_status' => bbp_get_pending_status_id() ) );
		$args = array(
			'post_type'      => bbp_get_reply_post_type(),
			'post__in'       => array( $public['reply'], $private['reply'], $pending['reply'] ),
			's'              => 'sentinel',
			'fields'         => 'ids',
			'posts_per_page' => -1,
		);

		foreach ( array( bbp_get_moderator_role(), bbp_get_keymaster_role() ) as $role ) {
			$user_id = $this->factory->user->create();
			bbp_set_user_role( $user_id, $role );
			$this->set_current_user( $user_id );

			// Password protection must not opt a custom query into status filtering.
			$query = new WP_Query( $args );
			$this->assertContains( $public['reply'], $query->posts );
			$this->assertContains( $private['reply'], $query->posts );
			$this->assertContains( $pending['reply'], $query->posts );

			// Existing public-listing and private-search opt-ins retain their meaning.
			$public_args = array_merge( $args, array( '_bbp_public_topic_replies' => true ) );
			$query = new WP_Query( $public_args );
			$this->assertContains( $public['reply'], $query->posts );
			$this->assertNotContains( $private['reply'], $query->posts );
			$this->assertNotContains( $pending['reply'], $query->posts );

			$public_args['_bbp_search_private_topic_replies'] = true;
			$query = new WP_Query( $public_args );
			$this->assertContains( $public['reply'], $query->posts );
			$this->assertContains( $private['reply'], $query->posts );
			$this->assertNotContains( $pending['reply'], $query->posts );
		}
	}

	/**
	 * @covers ::bbp_pre_get_posts_normalize_forum_visibility
	 */
	public function test_ajax_password_exemption_preserves_existing_forum_filters() {
		$excluded  = $this->create_search_tree();
		$protected = $this->create_search_tree( 'forum-secret' );
		$user_id   = $this->factory->user->create();
		bbp_set_user_role( $user_id, bbp_get_moderator_role() );
		$this->set_current_user( $user_id );

		// Model an integration's additional forum restrictions, not stock role caps.
		$filter = function( $value, $forum_ids, $type ) use ( $excluded ) {
			if ( 'array' === $type ) {
				return wp_parse_id_list( array_merge( $value, array( $excluded['forum'] ) ) );
			}
			if ( 'meta_query' === $type ) {
				return array(
					'key'     => '_bbp_forum_id',
					'value'   => array( $excluded['forum'] ),
					'compare' => 'NOT IN',
					'type'    => 'NUMERIC',
				);
			}

			return $value;
		};
		add_filter( 'bbp_exclude_forum_ids', $filter, 10, 3 );
		add_filter( 'wp_doing_ajax', '__return_true' );

		try {
			$args = array(
				'post_type'        => bbp_get_topic_post_type(),
				'post__in'         => array( $excluded['topic'], $protected['topic'] ),
				's'                => 'sentinel',
				'fields'           => 'ids',
				'suppress_filters' => true,
			);
			$query = new WP_Query( $args );
			$this->assertNotContains( $excluded['topic'], $query->posts );
			$this->assertContains( $protected['topic'], $query->posts );
			$this->assertNotEmpty( $query->get( 'meta_query' ) );

			$this->set_current_user( 0 );
			$query = new WP_Query( $args );
			$this->assertEmpty( $query->posts );

			$this->set_current_user( $user_id );
			remove_filter( 'wp_doing_ajax', '__return_true' );
			$query = new WP_Query( $args );
			$this->assertEmpty( $query->posts );
		} finally {
			remove_filter( 'bbp_exclude_forum_ids', $filter, 10 );
			remove_filter( 'wp_doing_ajax', '__return_true' );
		}
	}

	/**
	 * @covers ::bbp_pre_get_posts_normalize_forum_visibility
	 */
	public function test_empty_search_excludes_password_content_but_archive_does_not() {
		$protected = $this->create_search_tree( 'forum-secret' );
		$public    = $this->create_search_tree();
		$args = array(
			'post_type' => bbp_get_topic_post_type(),
			'post__in'  => array( $protected['topic'], $public['topic'] ),
			'fields'    => 'ids',
			's'         => '',
		);
		$query = new WP_Query( $args );
		$this->assertTrue( $query->is_search() );
		$this->assertContains( $public['topic'], $query->posts );
		$this->assertNotContains( $protected['topic'], $query->posts );

		unset( $args['s'] );
		$query = new WP_Query( $args );
		$this->assertContains( $public['topic'], $query->posts );
		$this->assertContains( $protected['topic'], $query->posts );
		$this->assertTrue( post_password_required( $protected['topic'] ) );
	}

	/**
	 * @covers ::bbp_clean_post_cache
	 * @covers ::bbp_get_password_protected_forum_ids
	 */
	public function test_password_cache_invalidates_with_missing_or_non_forum_parents() {
		$other_post = $this->factory->post->create();
		foreach ( array( 0, $other_post + 100000, $other_post ) as $parent_id ) {
			$tree = $this->create_search_tree();
			wp_update_post( array( 'ID' => $tree['forum'], 'post_parent' => $parent_id ) );
			$this->assertSame( $parent_id, (int) get_post_field( 'post_parent', $tree['forum'] ) );

			// Warm the exclusion cache before an ordinary WordPress password update.
			$this->assertNotContains( $tree['forum'], bbp_get_password_protected_forum_ids() );
			wp_update_post( array( 'ID' => $tree['forum'], 'post_password' => 'forum-secret' ) );
			$this->assertContains( $tree['forum'], bbp_get_password_protected_forum_ids(), 'Parent: ' . $parent_id );

			$args = array(
				'post_type' => array( bbp_get_topic_post_type(), bbp_get_reply_post_type() ),
				'post__in'  => array( $tree['topic'], $tree['reply'] ),
				's'         => 'sentinel',
				'fields'    => 'ids',
			);
			$query = new WP_Query( $args );
			$this->assertEmpty( $query->posts );

			wp_update_post( array( 'ID' => $tree['forum'], 'post_password' => '' ) );
			$this->assertNotContains( $tree['forum'], bbp_get_password_protected_forum_ids() );
			$query = new WP_Query( $args );
			$this->assertContains( $tree['topic'], $query->posts );
			$this->assertContains( $tree['reply'], $query->posts );
		}
	}

	/**
	 * @covers ::bbp_pre_get_posts_normalize_forum_visibility
	 */
	public function test_authorized_admin_forum_all_view_retains_non_public_statuses() {
		$old_request = $_REQUEST;
		$old_pagenow = isset( $GLOBALS['pagenow'] ) ? $GLOBALS['pagenow'] : null;
		$old_query   = $GLOBALS['wp_the_query'];
		$user_id     = $this->factory->user->create();
		bbp_set_user_role( $user_id, bbp_get_moderator_role() );
		$this->set_current_user( $user_id );
		$forum_ids = array();

		foreach ( array( bbp_get_public_status_id(), bbp_get_private_status_id(), bbp_get_hidden_status_id(), 'draft', 'pending', 'future' ) as $status ) {
			$forum_id  = $this->factory->forum->create( array( 'post_author' => $user_id ) );
			$post_date = gmdate( 'Y-m-d H:i:s', time() + ( 'future' === $status ? DAY_IN_SECONDS : -DAY_IN_SECONDS ) );
			wp_update_post(
				array(
					'ID'            => $forum_id,
					'post_status'   => $status,
					'post_date'     => $post_date,
					'post_date_gmt' => $post_date,
				)
			);
			$forum_ids[] = $forum_id;
			$this->assertSame( $status, get_post_status( $forum_id ) );
		}

		try {
			unset( $_REQUEST['post_status'] );
			set_current_screen( 'edit-forum' );
			$GLOBALS['pagenow'] = 'edit.php';
			$query = new WP_Query();
			$GLOBALS['wp_the_query'] = $query;
			$query->query( array( 'post_type' => bbp_get_forum_post_type(), 'post__in' => $forum_ids, 'fields' => 'ids', 'posts_per_page' => -1 ) );
			foreach ( $forum_ids as $forum_id ) {
				$this->assertContains( $forum_id, $query->posts );
			}
		} finally {
			$_REQUEST = $old_request;
			$GLOBALS['wp_the_query'] = $old_query;
			$GLOBALS['pagenow'] = $old_pagenow;
			set_current_screen( 'front' );
		}
	}

	/**
	 * @covers ::bbp_pre_get_posts_normalize_forum_visibility
	 */
	public function test_admin_all_view_preserves_custom_forum_exclusions() {
		$excluded    = $this->create_search_tree();
		$protected   = $this->create_search_tree( 'forum-secret' );
		$public      = $this->create_search_tree();
		$old_request = $_REQUEST;
		$old_pagenow = isset( $GLOBALS['pagenow'] ) ? $GLOBALS['pagenow'] : null;
		$old_query   = $GLOBALS['wp_the_query'];
		$user_id     = $this->factory->user->create();
		bbp_set_user_role( $user_id, bbp_get_moderator_role() );
		$this->set_current_user( $user_id );

		$filter = function( $value, $forum_ids, $type ) use ( $excluded ) {
			if ( 'array' === $type ) {
				return wp_parse_id_list( array_merge( $value, array( $excluded['forum'] ) ) );
			}
			if ( 'meta_query' === $type ) {
				return array(
					'key'     => '_bbp_forum_id',
					'value'   => array( $excluded['forum'] ),
					'compare' => 'NOT IN',
					'type'    => 'NUMERIC',
				);
			}

			return $value;
		};
		add_filter( 'bbp_exclude_forum_ids', $filter, 10, 3 );

		try {
			unset( $_REQUEST['post_status'] );
			$GLOBALS['pagenow'] = 'edit.php';

			foreach ( array( 'forum', 'topic', 'reply' ) as $type ) {
				set_current_screen( 'edit-' . get_post_type( $public[ $type ] ) );
				foreach ( array( null, 'sentinel' ) as $term ) {
					$query = new WP_Query();
					$GLOBALS['wp_the_query'] = $query;
					$args = array(
						'post_type'      => get_post_type( $public[ $type ] ),
						'post__in'       => array( $excluded[ $type ], $protected[ $type ], $public[ $type ] ),
						'fields'         => 'ids',
						'posts_per_page' => -1,
					);
					if ( null !== $term ) {
						$args['s'] = $term;
					}
					$query->query( $args );

					$this->assertNotContains( $excluded[ $type ], $query->posts );
					$this->assertContains( $public[ $type ], $query->posts );
					$this->assertContains( $protected[ $type ], $query->posts );
				}
			}
		} finally {
			remove_filter( 'bbp_exclude_forum_ids', $filter, 10 );
			$_REQUEST = $old_request;
			$GLOBALS['pagenow'] = $old_pagenow;
			$GLOBALS['wp_the_query'] = $old_query;
			set_current_screen( 'front' );
		}
	}

	/**
	 * @covers ::bbp_pre_get_posts_normalize_forum_visibility
	 * @covers ::_bbp_forum_visibility_where
	 */
	public function test_password_search_preserves_meta_query_only_exclusion_filter() {
		$this->factory->forum->create( array( 'post_status' => bbp_get_hidden_status_id() ) );
		$excluded  = $this->create_search_tree();
		$protected = $this->create_search_tree( 'forum-secret' );
		$public    = $this->create_search_tree();

		// Duplicate public metadata must not bypass the password-search clause.
		foreach ( array( 'topic', 'reply' ) as $type ) {
			add_post_meta( $protected[ $type ], '_bbp_forum_id', $public['forum'] );
		}

		$filter = function( $value, $forum_ids, $type ) use ( $excluded ) {
			if ( 'meta_query' === $type ) {
				$value['value'] = wp_parse_id_list( array_merge( $value['value'], array( $excluded['forum'] ) ) );
			}

			return $value;
		};
		add_filter( 'bbp_exclude_forum_ids', $filter, 10, 3 );
		$this->set_current_user( 0 );

		try {
			foreach ( array( array(), array(
				'relation' => 'OR',
				array( 'key' => '_bbp_forum_id', 'value' => $public['forum'] ),
				array( 'key' => '_bbp_forum_id', 'value' => $excluded['forum'] ),
			) ) as $meta_query ) {
				$query = new WP_Query(
					array(
						'post_type'      => array( bbp_get_topic_post_type(), bbp_get_reply_post_type() ),
						'post__in'       => array_merge( array_values( $excluded ), array_values( $protected ), array_values( $public ) ),
						's'              => 'sentinel',
						'fields'         => 'ids',
						'posts_per_page' => -1,
						'meta_query'     => $meta_query,
					)
				);
				foreach ( array( 'topic', 'reply' ) as $type ) {
					$this->assertNotContains( $excluded[ $type ], $query->posts );
					$this->assertNotContains( $protected[ $type ], $query->posts );
					$this->assertContains( $public[ $type ], $query->posts );
				}
			}
		} finally {
			remove_filter( 'bbp_exclude_forum_ids', $filter, 10 );
		}
	}

	/**
	 * Create matching forum, topic, and reply search controls.
	 */
	private function create_search_tree( $password = '' ) {
		$forum_id = $this->factory->forum->create(
			array( 'post_password' => $password, 'post_content' => 'Search ancestry sentinel 0' )
		);
		$topic_id = $this->factory->topic->create(
			array( 'post_parent' => $forum_id, 'post_content' => 'Search ancestry sentinel 0', 'topic_meta' => array( 'forum_id' => $forum_id ) )
		);
		$reply_id = $this->factory->reply->create(
			array( 'post_parent' => $topic_id, 'post_content' => 'Search ancestry sentinel 0', 'reply_meta' => array( 'forum_id' => $forum_id, 'topic_id' => $topic_id ) )
		);

		return array( 'forum' => $forum_id, 'topic' => $topic_id, 'reply' => $reply_id );
	}

}
