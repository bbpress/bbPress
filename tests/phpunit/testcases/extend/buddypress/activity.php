<?php

/**
 * BuddyPress Extension Activity Tests.
 *
 * @group extend
 * @group buddypress
 * @group activity
 */
class BBP_Tests_Extend_BuddyPress_Activity extends BBP_UnitTestCase {

	/**
	 * Password representations and forum states that activity queries must cover.
	 *
	 * @return array[] Password and ancestor-state cases.
	 */
	public function password_activity_query_cases() {
		$cases = array(
			'draft ancestor'      => array( 'publish', 'draft', 'parent', 'forum-secret' ),
			'pending ancestor'    => array( 'publish', 'pending', 'parent', 'forum-secret' ),
			'trashed ancestor'    => array( 'publish', 'trash', 'parent', 'forum-secret' ),
			'auto-draft ancestor' => array( 'publish', 'auto-draft', 'parent', 'forum-secret' ),
			'draft password root' => array( 'draft', 'publish', 'parent', 'forum-secret' ),
			'trashed password root' => array( 'trash', 'publish', 'parent', 'forum-secret' ),
			'auto-draft password root' => array( 'auto-draft', 'publish', 'parent', 'forum-secret' ),
			'forum spaces'        => array( 'publish', 'publish', 'parent', '   ' ),
			'topic spaces'        => array( 'publish', 'publish', 'topic', '   ' ),
			'reply spaces'        => array( 'publish', 'publish', 'reply', '   ' ),
			'forum tab'           => array( 'publish', 'publish', 'parent', "\t" ),
			'zero password'       => array( 'publish', 'publish', 'parent', '0' ),
		);

		$result = array();
		foreach ( array( false, true ) as $group_activity ) {
			foreach ( $cases as $name => $case ) {
				$result[ ( $group_activity ? 'group ' : 'sitewide ' ) . $name ] = array_merge( $case, array( $group_activity ) );
			}
		}

		return $result;
	}

	/**
	 * Existing activity obeys passwords across collection and direct reads.
	 *
	 * @dataProvider password_activity_query_cases
	 *
	 * @param string $parent_status Password-root status.
	 * @param string $middle_status Intermediate forum status.
	 * @param string $target        Post receiving the password.
	 * @param string $password      Password representation.
	 * @param bool   $group_activity Whether to use real group activity mapping.
	 */
	public function test_activity_password_queries_cover_descendants_and_whitespace( $parent_status, $middle_status, $target, $password, $group_activity ) {
		global $wpdb;

		$parent = $this->factory->forum->create();
		$middle = $this->factory->forum->create( array( 'post_parent' => $parent ) );
		$tree   = $this->create_password_activity_tree( $middle, $group_activity );
		$public = $this->create_password_activity_tree( 0, $group_activity );
		$ids    = array_merge( $tree['activities'], $public['activities'] );

		wp_update_post( array( 'ID' => $parent, 'post_status' => $parent_status ) );
		wp_update_post( array( 'ID' => $middle, 'post_status' => $middle_status ) );
		$post_id = ( 'parent' === $target ) ? $parent : $tree[ $target ];

		// Model existing rows retained by an import or a direct database update.
		$this->assertSame( 1, $wpdb->update( $wpdb->posts, array( 'post_password' => $password ), array( 'ID' => $post_id ) ) );
		clean_post_cache( $post_id );
		$this->assertSame( $password, get_post_field( 'post_password', $post_id ) );

		$protected_ids = ( '0' === $password ) ? array() : $tree['activities'];
		if ( 'reply' === $target ) {
			$protected_ids = array( $tree['activities'][1] );
		}
		$visible_ids = array_values( array_diff( $ids, $protected_ids ) );
		$viewers     = array( 0 );
		foreach ( array( '', bbp_get_participant_role(), bbp_get_moderator_role(), bbp_get_keymaster_role() ) as $role ) {
			$viewer = $this->factory->user->create();
			get_userdata( $viewer )->set_role( '' );
			if ( '' !== $role ) {
				bbp_set_user_role( $viewer, $role );
			}
			$viewers[] = $viewer;
		}

		$route      = '/' . bp_rest_namespace() . '/' . bp_rest_version() . '/activity';
		$request    = new WP_REST_Request( 'GET', $route );
		$old_user   = get_current_user_id();
		$cookie     = 'wp-postpass_' . COOKIEHASH;
		$old_cookie = isset( $_COOKIE[ $cookie ] ) ? $_COOKIE[ $cookie ] : null;
		$request->set_param( 'include', $ids );
		unset( $_COOKIE[ $cookie ] );

		try {
			foreach ( $viewers as $viewer ) {
				wp_set_current_user( $viewer );
				$this->assertSame( '0' !== $password, post_password_required( $post_id ) );
				$activity = bp_activity_get( array( 'in' => $ids, 'count_total' => true ) );
				$this->assertEqualsCanonicalizing( $visible_ids, wp_list_pluck( $activity['activities'], 'id' ) );
				$this->assertSame( count( $visible_ids ), (int) $activity['total'] );

				$response = rest_do_request( $request );
				$this->assertSame( 200, $response->get_status() );
				$this->assertEqualsCanonicalizing( $visible_ids, wp_list_pluck( $response->get_data(), 'id' ) );

				foreach ( $ids as $activity_id ) {
					$can_read = ! in_array( $activity_id, $protected_ids, true );
					$this->assertSame( $can_read, bp_activity_user_can_read( new BP_Activity_Activity( $activity_id ), $viewer ) );
					$this->assertSame( $can_read, 200 === rest_do_request( new WP_REST_Request( 'GET', $route . '/' . $activity_id ) )->get_status() );
				}
			}

			require_once ABSPATH . WPINC . '/class-phpass.php';
			$hasher = new PasswordHash( 8, true );
			$_COOKIE[ $cookie ] = $hasher->HashPassword( $password );
			wp_set_current_user( 0 );

			$unlocked = bp_activity_get( array( 'in' => $ids, 'count_total' => true ) );
			$this->assertEqualsCanonicalizing( $ids, wp_list_pluck( $unlocked['activities'], 'id' ) );
			$this->assertSame( count( $ids ), (int) $unlocked['total'] );
			$this->assertEqualsCanonicalizing( $ids, wp_list_pluck( rest_do_request( $request )->get_data(), 'id' ) );

			foreach ( $ids as $activity_id ) {
				$this->assertTrue( bp_activity_user_can_read( new BP_Activity_Activity( $activity_id ), 0 ) );
			}

			unset( $_COOKIE[ $cookie ] );
			$locked = bp_activity_get( array( 'in' => $ids, 'count_total' => true ) );
			$this->assertEqualsCanonicalizing( $visible_ids, wp_list_pluck( $locked['activities'], 'id' ) );
		} finally {
			wp_set_current_user( $old_user );
			if ( null === $old_cookie ) {
				unset( $_COOKIE[ $cookie ] );
			} else {
				$_COOKIE[ $cookie ] = $old_cookie;
			}
		}
	}

	/**
	 * Filter-added forum trees remain protected, including non-countable children.
	 */
	public function test_activity_password_descendants_preserve_subforum_filter() {
		$parent  = $this->factory->forum->create( array( 'post_password' => 'forum-secret' ) );
		$virtual = $this->factory->forum->create();
		$middle  = $this->factory->forum->create( array( 'post_parent' => $virtual ) );
		$tree    = $this->create_password_activity_tree( $middle );
		$public  = $this->create_password_activity_tree();
		$visited = array();
		$inputs  = array();
		wp_update_post( array( 'ID' => $middle, 'post_status' => 'draft' ) );

		$filter = function( $children, $forum_id ) use ( $parent, $virtual, $tree, &$visited, &$inputs ) {
			$visited[] = (int) $forum_id;
			$inputs[ $forum_id ] = wp_parse_id_list( $children );
			if ( $parent === (int) $forum_id ) {
				$children[] = $virtual;
			}
			if ( $tree['forum'] === (int) $forum_id ) {
				$children = array_merge( $children, array( $parent, $virtual, $tree['forum'] ) );
			}
			return $children;
		};
		add_filter( 'bbp_forum_query_subforum_ids', $filter, 10, 2 );

		try {
			wp_set_current_user( 0 );
			$ids      = array_merge( $tree['activities'], $public['activities'] );
			$activity = bp_activity_get( array( 'in' => $ids, 'count_total' => true ) );
			$this->assertEqualsCanonicalizing( $public['activities'], wp_list_pluck( $activity['activities'], 'id' ) );
			$this->assertSame( count( $public['activities'] ), (int) $activity['total'] );
			$this->assertContains( $parent, $visited );
			$this->assertContains( $virtual, $visited );
			$this->assertContains( $middle, $visited );
			$this->assertContains( $tree['forum'], $visited );
			$this->assertCount( count( array_unique( $visited ) ), $visited );
			$this->assertSame( array(), $inputs[ $virtual ] );
			$this->assertSame( array( $tree['forum'] ), $inputs[ $middle ] );
		} finally {
			remove_filter( 'bbp_forum_query_subforum_ids', $filter, 10 );
		}
	}

	/**
	 * Invalid filter IDs must not expand into unrelated forum trees.
	 */
	public function test_activity_password_subforum_filter_ignores_invalid_ids() {
		$parent = $this->factory->forum->create( array( 'post_password' => 'forum-secret' ) );
		$public = $this->create_password_activity_tree();
		$filter = function( $children, $forum_id ) use ( $parent, $public ) {
			return ( $parent === (int) $forum_id )
				? array( 0, '', null, 'invalid', -$public['forum'] )
				: $children;
		};
		add_filter( 'bbp_forum_query_subforum_ids', $filter, 10, 2 );

		try {
			wp_set_current_user( 0 );
			$activity = bp_activity_get( array( 'in' => $public['activities'], 'count_total' => true ) );
			$this->assertEqualsCanonicalizing( $public['activities'], wp_list_pluck( $activity['activities'], 'id' ) );
			$this->assertSame( count( $public['activities'] ), (int) $activity['total'] );
		} finally {
			remove_filter( 'bbp_forum_query_subforum_ids', $filter, 10 );
		}
	}

	/**
	 * A failed password lookup is retried rather than cached as an empty result.
	 */
	public function test_activity_password_lookup_retries_database_errors() {
		global $wpdb;

		$tree   = $this->create_password_activity_tree();
		$public = $this->create_password_activity_tree();
		$wpdb->update( $wpdb->posts, array( 'post_password' => 'forum-secret' ), array( 'ID' => $tree['forum'] ) );
		clean_post_cache( $tree['forum'] );
		$queries = 0;
		$counter = function( $query ) use ( $wpdb, &$queries ) {
			if ( false !== strpos( $query, 'CHAR_LENGTH( post_password ) > 0' ) ) {
				$queries++;
				if ( 1 === $queries ) {
					return "SELECT bbpress_missing_password_column FROM {$wpdb->posts}";
				}
			}
			return $query;
		};
		$old_errors = $wpdb->suppress_errors();
		add_filter( 'query', $counter );

		try {
			wp_set_current_user( 0 );
			$args = array( 'in' => array_merge( $tree['activities'], $public['activities'] ), 'count_total' => true );
			bp_activity_get( $args );
			$this->assertSame( 1, $queries );
			$activity = bp_activity_get( $args );
			$this->assertSame( 2, $queries );
			$this->assertEqualsCanonicalizing( $public['activities'], wp_list_pluck( $activity['activities'], 'id' ) );
			$this->assertSame( count( $public['activities'] ), (int) $activity['total'] );
		} finally {
			remove_filter( 'query', $counter );
			$wpdb->suppress_errors( $old_errors );
		}
	}

	/**
	 * Cached password IDs, including an empty lookup, refresh after post changes.
	 */
	public function test_activity_password_lookup_cache_refreshes_after_post_changes() {
		global $wpdb;

		$tree    = $this->create_password_activity_tree();
		$public  = $this->create_password_activity_tree();
		$ids     = array_merge( $tree['activities'], $public['activities'] );
		$queries = 0;
		$counter = function( $query ) use ( &$queries ) {
			if ( false !== strpos( $query, 'CHAR_LENGTH( post_password ) > 0' ) ) {
				$queries++;
			}
			return $query;
		};
		add_filter( 'query', $counter );

		try {
			wp_set_current_user( 0 );
			foreach ( array( '', '   ', '' ) as $index => $password ) {
				if ( $index ) {
					$this->assertSame( 1, $wpdb->update( $wpdb->posts, array( 'post_password' => $password ), array( 'ID' => $tree['forum'] ) ) );
					clean_post_cache( $tree['forum'] );
				}

				$expected = empty( $password ) ? $ids : $public['activities'];
				for ( $query = 0; $query < 2; $query++ ) {
					$activity = bp_activity_get( array( 'in' => $ids, 'count_total' => true ) );
					$this->assertEqualsCanonicalizing( $expected, wp_list_pluck( $activity['activities'], 'id' ) );
					$this->assertSame( count( $expected ), (int) $activity['total'] );
					$this->assertSame( $index + 1, $queries );
				}
			}
		} finally {
			remove_filter( 'query', $counter );
		}
	}

	/**
	 * Cached password IDs stay separate across sites and survive a round trip.
	 */
	public function test_activity_password_lookup_cache_is_separate_per_site() {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'Multisite only.' );
		}
		global $wpdb;

		$blog_id  = $this->factory->blog->create();
		$this->factory->forum->create( array( 'post_password' => 'forum-secret' ) );
		$original = $wpdb->posts;
		$tables   = array();
		$counter  = function( $query ) use ( $wpdb, &$tables ) {
			if ( false !== strpos( $query, 'CHAR_LENGTH( post_password ) > 0' ) ) {
				$tables[] = $wpdb->posts;
			}
			return $query;
		};
		$activity = bbpress()->extend->buddypress->activity;
		$args     = array( 'filter' => array( 'action' => 'bbp_topic_create' ) );
		add_filter( 'query', $counter );
		$switched = false;

		try {
			$conditions = $activity->activity_get_where_conditions( array(), $args );
			$this->assertArrayHasKey( 'bbpress_topic_visibility', $conditions );
			switch_to_blog( $blog_id );
			$switched = true;
			$other    = $wpdb->posts;
			$this->assertSame( array(), $activity->activity_get_where_conditions( array(), $args ) );
			restore_current_blog();
			$switched = false;
			$this->assertSame( $conditions, $activity->activity_get_where_conditions( array(), $args ) );
			$this->assertSame( array( $original, $other ), $tables );
		} finally {
			if ( $switched ) {
				restore_current_blog();
			}
			remove_filter( 'query', $counter );
		}
	}

	/**
	 * Create real topic and reply activity before changing password visibility.
	 *
	 * @param int  $parent         Parent forum.
	 * @param bool $group_activity Whether to attach a public BuddyPress group.
	 * @return array Created posts and their activity IDs.
	 */
	private function create_password_activity_tree( $parent = 0, $group_activity = false ) {
		$user_id  = $this->factory->user->create();
		$forum_id = $this->factory->forum->create( array( 'post_parent' => $parent ) );
		$topic_id = $this->factory->topic->create( array( 'post_parent' => $forum_id, 'post_author' => $user_id ) );
		$reply_id = $this->factory->reply->create( array( 'post_parent' => $topic_id, 'post_author' => $user_id ) );
		$old_group = isset( buddypress()->groups->current_group ) ? buddypress()->groups->current_group : null;

		try {
			if ( $group_activity ) {
				$group_id = $this->bp_factory->group->create( array( 'creator_id' => $user_id, 'status' => 'public' ) );
				bbp_add_forum_id_to_group( $group_id, $forum_id );
				bbp_add_group_id_to_forum( $forum_id, $group_id );
				buddypress()->groups->current_group = groups_get_group( $group_id );
			}

			bbpress()->extend->buddypress->activity->topic_create( $topic_id, $forum_id, array(), $user_id );
			bbpress()->extend->buddypress->activity->reply_create( $reply_id, $topic_id, $forum_id, array(), $user_id );
		} finally {
			if ( null === $old_group ) {
				unset( buddypress()->groups->current_group );
			} else {
				buddypress()->groups->current_group = $old_group;
			}
		}

		$activity_ids = array( (int) get_post_meta( $topic_id, '_bbp_activity_id', true ), (int) get_post_meta( $reply_id, '_bbp_activity_id', true ) );
		$this->assertNotContains( 0, $activity_ids );

		foreach ( array( $topic_id, $reply_id ) as $index => $post_id ) {
			$activity = new BP_Activity_Activity( $activity_ids[ $index ] );
			$this->assertSame( $group_activity ? buddypress()->groups->id : 'bbpress', $activity->component );
			$this->assertSame( $post_id, (int) ( $group_activity ? $activity->secondary_item_id : $activity->item_id ) );
		}

		return array( 'forum' => $forum_id, 'topic' => $topic_id, 'reply' => $reply_id, 'activities' => $activity_ids );
	}


	/**
	 * Activity visibility uses the supported BuddyPress query filter.
	 *
	 * @ticket BBP3741
	 */
	public function test_activity_visibility_uses_supported_query_filter() {
		$activity = bbpress()->extend->buddypress->activity;

		if ( version_compare( bp_get_version(), '15.0.0-alpha', '>=' ) ) {
			$this->assertFalse( has_filter( 'bp_activity_get_where_conditions', array( $activity, 'activity_get_where_conditions' ) ) );
			$this->assertSame( 10, has_filter( 'bp_activity_get_sql_clauses', array( $activity, 'activity_get_sql_clauses' ) ) );
		} else {
			$this->assertSame( 10, has_filter( 'bp_activity_get_where_conditions', array( $activity, 'activity_get_where_conditions' ) ) );
			$this->assertFalse( has_filter( 'bp_activity_get_sql_clauses', array( $activity, 'activity_get_sql_clauses' ) ) );
		}
	}

	/**
	 * Restricted ancestors and activity types that must not be sitewide.
	 *
	 * @return array[] Test cases.
	 */
	public function restricted_ancestor_activity_cases() {
		return array(
			'private parent topic'             => array( 'private', false, 'topic' ),
			'private parent reply'             => array( 'private', false, 'reply' ),
			'hidden parent topic'              => array( 'hidden', false, 'topic' ),
			'hidden parent reply'              => array( 'hidden', false, 'reply' ),
			'private middle ancestor topic'   => array( 'private', true, 'topic' ),
			'private middle ancestor reply'   => array( 'private', true, 'reply' ),
			'hidden middle ancestor topic'    => array( 'hidden', true, 'topic' ),
			'hidden middle ancestor reply'    => array( 'hidden', true, 'reply' ),
		);
	}

	/**
	 * A public child inherits a private or hidden ancestor's visibility.
	 *
	 * @dataProvider restricted_ancestor_activity_cases
	 *
	 * @param string $status Restricted ancestor visibility.
	 * @param bool   $has_public_grandparent Whether the restricted forum has a public parent.
	 * @param string $activity_type Topic or reply activity.
	 */
	public function test_restricted_ancestor_activity_is_not_public( $status, $has_public_grandparent, $activity_type ) {
		$user_id = $this->factory->user->create();
		$parent  = $has_public_grandparent
			? $this->factory->forum->create()
			: 0;
		$restricted = $this->factory->forum->create( array(
			'post_parent' => $parent,
			'post_status' => $status,
		) );
		$forum_id = $this->factory->forum->create( array(
			'post_parent' => $restricted,
		) );
		$topic_id = $this->factory->topic->create( array(
			'post_parent'  => $forum_id,
			'post_author'  => $user_id,
			'post_content' => 'Restricted topic content',
		) );

		if ( 'reply' === $activity_type ) {
			$reply_id = $this->factory->reply->create( array(
				'post_parent'  => $topic_id,
				'post_author'  => $user_id,
				'post_content' => 'Restricted reply content',
			) );
			bbpress()->extend->buddypress->activity->reply_create( $reply_id, $topic_id, $forum_id, array(), $user_id );
			$post_id = $reply_id;
		} else {
			bbpress()->extend->buddypress->activity->topic_create( $topic_id, $forum_id, array(), $user_id );
			$post_id = $topic_id;
		}

		$activity_id = (int) get_post_meta( $post_id, '_bbp_activity_id', true );
		$this->assertGreaterThan( 0, $activity_id );

		wp_set_current_user( 0 );
		$public_activity = bp_activity_get( array(
			'in' => array( $activity_id ),
		) );
		$this->assertEmpty( $public_activity['activities'] );
		$this->assertSame( 1, (int) ( new BP_Activity_Activity( $activity_id ) )->hide_sitewide );

		$route    = '/' . bp_rest_namespace() . '/' . bp_rest_version() . '/activity';
		$request  = new WP_REST_Request( 'GET', $route );
		$request->set_param( 'include', array( $activity_id ) );
		$response = rest_do_request( $request );
		$this->assertSame( 200, $response->get_status() );
		$this->assertNotContains( $activity_id, wp_list_pluck( $response->get_data(), 'id' ) );

		$single_response = rest_do_request( new WP_REST_Request( 'GET', $route . '/' . $activity_id ) );
		$this->assertNotSame( 200, $single_response->get_status() );
	}

	/**
	 * Restrict activity that was created before an ancestor became non-public.
	 *
	 * @dataProvider restricted_ancestor_activity_cases
	 *
	 * @param string $status Restricted ancestor visibility.
	 * @param bool   $has_public_grandparent Whether the restricted forum has a public parent.
	 * @param string $activity_type Topic or reply activity.
	 */
	public function test_activity_tracks_ancestor_visibility_changes( $status, $has_public_grandparent, $activity_type ) {
		$user_id = $this->factory->user->create();
		$parent  = $has_public_grandparent
			? $this->factory->forum->create()
			: 0;
		$ancestor = $this->factory->forum->create( array(
			'post_parent' => $parent,
		) );
		$forum_id = $this->factory->forum->create( array(
			'post_parent' => $ancestor,
		) );
		$topic_id = $this->factory->topic->create( array(
			'post_parent' => $forum_id,
			'post_author' => $user_id,
		) );

		if ( 'reply' === $activity_type ) {
			$reply_id = $this->factory->reply->create( array(
				'post_parent' => $topic_id,
				'post_author' => $user_id,
			) );
			bbpress()->extend->buddypress->activity->reply_create( $reply_id, $topic_id, $forum_id, array(), $user_id );
			$post_id = $reply_id;
		} else {
			bbpress()->extend->buddypress->activity->topic_create( $topic_id, $forum_id, array(), $user_id );
			$post_id = $topic_id;
		}

		$activity_id = (int) get_post_meta( $post_id, '_bbp_activity_id', true );
		$this->assertGreaterThan( 0, $activity_id );
		$this->assertSame( 0, (int) ( new BP_Activity_Activity( $activity_id ) )->hide_sitewide );

		if ( 'private' === $status ) {
			bbp_privatize_forum( $ancestor );
		} else {
			bbp_hide_forum( $ancestor );
		}

		wp_set_current_user( 0 );
		$public_activity = bp_activity_get( array(
			'in' => array( $activity_id ),
		) );
		$this->assertEmpty( $public_activity['activities'] );

		$route    = '/' . bp_rest_namespace() . '/' . bp_rest_version() . '/activity';
		$request  = new WP_REST_Request( 'GET', $route );
		$request->set_param( 'include', array( $activity_id ) );
		$response = rest_do_request( $request );
		$this->assertSame( 200, $response->get_status() );
		$this->assertNotContains( $activity_id, wp_list_pluck( $response->get_data(), 'id' ) );
		$single_response = rest_do_request( new WP_REST_Request( 'GET', $route . '/' . $activity_id ) );
		$this->assertNotSame( 200, $single_response->get_status() );
	}

	/**
	 * Public forum descendants remain visible in the sitewide activity stream.
	 */
	public function test_public_ancestor_activity_remains_public() {
		$user_id = $this->factory->user->create();
		$parent  = $this->factory->forum->create();
		$forum_id = $this->factory->forum->create( array(
			'post_parent' => $parent,
		) );
		$topic_id = $this->factory->topic->create( array(
			'post_parent'  => $forum_id,
			'post_author'  => $user_id,
			'post_content' => 'Public topic content',
		) );
		$reply_id = $this->factory->reply->create( array(
			'post_parent'  => $topic_id,
			'post_author'  => $user_id,
			'post_content' => 'Public reply content',
		) );

		bbpress()->extend->buddypress->activity->topic_create( $topic_id, $forum_id, array(), $user_id );
		bbpress()->extend->buddypress->activity->reply_create( $reply_id, $topic_id, $forum_id, array(), $user_id );

		wp_set_current_user( 0 );
		foreach ( array( $topic_id, $reply_id ) as $post_id ) {
			$activity_id = (int) get_post_meta( $post_id, '_bbp_activity_id', true );
			$this->assertGreaterThan( 0, $activity_id );

			$public_activity = bp_activity_get( array(
				'in' => array( $activity_id ),
			) );
			$this->assertCount( 1, $public_activity['activities'] );
			$this->assertSame( 0, (int) ( new BP_Activity_Activity( $activity_id ) )->hide_sitewide );

			$route    = '/' . bp_rest_namespace() . '/' . bp_rest_version() . '/activity';
			$request  = new WP_REST_Request( 'GET', $route );
			$request->set_param( 'include', array( $activity_id ) );
			$response = rest_do_request( $request );
			$this->assertSame( 200, $response->get_status() );
			$this->assertContains( $activity_id, wp_list_pluck( $response->get_data(), 'id' ) );

			$single_response = rest_do_request( new WP_REST_Request( 'GET', $route . '/' . $activity_id ) );
			$this->assertSame( 200, $single_response->get_status() );
		}
	}

	/**
	 * Activity inherits password requirements from forum ancestors.
	 */
	public function test_password_protected_ancestor_activity_requires_password() {
		$user_id = $this->factory->user->create();
		$parent  = $this->factory->forum->create();
		$forum_id = $this->factory->forum->create(
			array(
				'post_parent' => $parent,
			)
		);
		$topic_id = $this->factory->topic->create(
			array(
				'post_parent'  => $forum_id,
				'post_author'  => $user_id,
				'post_content' => 'Protected topic content',
			)
		);
		$reply_id = $this->factory->reply->create(
			array(
				'post_parent'  => $topic_id,
				'post_author'  => $user_id,
				'post_content' => 'Protected reply content',
			)
		);

		bbpress()->extend->buddypress->activity->topic_create( $topic_id, $forum_id, array(), $user_id );
		bbpress()->extend->buddypress->activity->reply_create( $reply_id, $topic_id, $forum_id, array(), $user_id );

		$activity_ids = array(
			(int) get_post_meta( $topic_id, '_bbp_activity_id', true ),
			(int) get_post_meta( $reply_id, '_bbp_activity_id', true ),
		);
		$this->assertNotContains( 0, $activity_ids );

		wp_update_post(
			array(
				'ID'            => $parent,
				'post_password' => 'parent-secret',
			)
		);

		wp_set_current_user( 0 );
		$public_activity = bp_activity_get( array( 'in' => $activity_ids ) );
		$this->assertEmpty( $public_activity['activities'] );

		$route    = '/' . bp_rest_namespace() . '/' . bp_rest_version() . '/activity';
		$request  = new WP_REST_Request( 'GET', $route );
		$request->set_param( 'include', $activity_ids );
		$response = rest_do_request( $request );
		$this->assertSame( 200, $response->get_status() );
		$this->assertEmpty( array_intersect( $activity_ids, wp_list_pluck( $response->get_data(), 'id' ) ) );

		foreach ( $activity_ids as $activity_id ) {
			$activity = new BP_Activity_Activity( $activity_id );
			$this->assertFalse( bp_activity_user_can_read( $activity, 0 ) );
			$this->assertNotSame( 200, rest_do_request( new WP_REST_Request( 'GET', $route . '/' . $activity_id ) )->get_status() );
		}

		require_once ABSPATH . WPINC . '/class-phpass.php';
		$hasher     = new PasswordHash( 8, true );
		$cookie     = 'wp-postpass_' . COOKIEHASH;
		$old_cookie = isset( $_COOKIE[ $cookie ] ) ? $_COOKIE[ $cookie ] : null;
		$_COOKIE[ $cookie ] = $hasher->HashPassword( 'parent-secret' );

		try {
			$unlocked_activity = bp_activity_get( array( 'in' => $activity_ids ) );
			$this->assertCount( count( $activity_ids ), $unlocked_activity['activities'] );
			$this->assertEmpty( array_diff( $activity_ids, wp_list_pluck( $unlocked_activity['activities'], 'id' ) ) );

			$response = rest_do_request( $request );
			$this->assertSame( 200, $response->get_status() );
			$this->assertEmpty( array_diff( $activity_ids, wp_list_pluck( $response->get_data(), 'id' ) ) );

			foreach ( $activity_ids as $activity_id ) {
				$activity = new BP_Activity_Activity( $activity_id );
				$this->assertTrue( bp_activity_user_can_read( $activity, 0 ) );
				$this->assertSame( 200, rest_do_request( new WP_REST_Request( 'GET', $route . '/' . $activity_id ) )->get_status() );
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
	 * Existing activity respects passwords set directly on topics and replies.
	 */
	public function test_existing_password_protected_post_activity_requires_password() {
		$user_id  = $this->factory->user->create();
		$forum_id = $this->factory->forum->create();
		$topic_id = $this->factory->topic->create(
			array(
				'post_parent'  => $forum_id,
				'post_author'  => $user_id,
				'post_content' => 'Protected topic content',
			)
		);
		$public_topic_id = $this->factory->topic->create(
			array(
				'post_parent' => $forum_id,
				'post_author' => $user_id,
			)
		);
		$reply_id = $this->factory->reply->create(
			array(
				'post_parent'  => $public_topic_id,
				'post_author'  => $user_id,
				'post_content' => 'Protected reply content',
			)
		);

		bbpress()->extend->buddypress->activity->topic_create( $topic_id, $forum_id, array(), $user_id );
		bbpress()->extend->buddypress->activity->reply_create( $reply_id, $public_topic_id, $forum_id, array(), $user_id );

		$activity_ids = array(
			(int) get_post_meta( $topic_id, '_bbp_activity_id', true ),
			(int) get_post_meta( $reply_id, '_bbp_activity_id', true ),
		);
		$this->assertNotContains( 0, $activity_ids );

		global $wpdb;
		foreach ( array( $topic_id, $reply_id ) as $post_id ) {
			$wpdb->update(
				$wpdb->posts,
				array( 'post_password' => 'post-secret' ),
				array( 'ID' => $post_id )
			);
			clean_post_cache( $post_id );
		}

		wp_set_current_user( 0 );
		$this->assertEmpty( bp_activity_get( array( 'in' => $activity_ids ) )['activities'] );
		$uppercase_filter = array(
			'in'     => $activity_ids,
			'filter' => array( 'action' => 'BBP_TOPIC_CREATE,BBP_REPLY_CREATE' ),
		);
		$this->assertEmpty( bp_activity_get( $uppercase_filter )['activities'] );

		foreach ( $activity_ids as $activity_id ) {
			$this->assertFalse( bp_activity_user_can_read( new BP_Activity_Activity( $activity_id ), 0 ) );
		}

		require_once ABSPATH . WPINC . '/class-phpass.php';
		$hasher               = new PasswordHash( 8, true );
		$cookie               = 'wp-postpass_' . COOKIEHASH;
		$old_cookie           = isset( $_COOKIE[ $cookie ] ) ? $_COOKIE[ $cookie ] : null;
		$_COOKIE[ $cookie ] = $hasher->HashPassword( 'post-secret' );

		try {
			$unlocked_activity = bp_activity_get( array( 'in' => $activity_ids ) );
			$this->assertCount( count( $activity_ids ), $unlocked_activity['activities'] );
			$this->assertEmpty( array_diff( $activity_ids, wp_list_pluck( $unlocked_activity['activities'], 'id' ) ) );
			$unlocked_filtered_activity = bp_activity_get( $uppercase_filter );
			$this->assertCount( count( $activity_ids ), $unlocked_filtered_activity['activities'] );
			$this->assertEmpty( array_diff( $activity_ids, wp_list_pluck( $unlocked_filtered_activity['activities'], 'id' ) ) );

			foreach ( $activity_ids as $activity_id ) {
				$this->assertTrue( bp_activity_user_can_read( new BP_Activity_Activity( $activity_id ), 0 ) );
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
	 * Password-protected topics and replies do not create activity.
	 *
	 * @dataProvider activity_edit_cases
	 *
	 * @param string $activity_type Topic or reply activity.
	 */
	public function test_password_protected_post_does_not_create_activity( $activity_type ) {
		$user_id  = $this->factory->user->create();
		$forum_id = $this->factory->forum->create();
		$topic_id = $this->factory->topic->create(
			array(
				'post_parent'  => $forum_id,
				'post_author'  => $user_id,
				'post_content' => 'Public topic content',
			)
		);

		if ( 'reply' === $activity_type ) {
			$post_id = $this->factory->reply->create(
				array(
					'post_parent'   => $topic_id,
					'post_author'   => $user_id,
					'post_content'  => 'Protected reply content',
					'post_password' => 'post-secret',
				)
			);
			bbpress()->extend->buddypress->activity->reply_create( $post_id, $topic_id, $forum_id, array(), $user_id );
		} else {
			$post_id = wp_update_post(
				array(
					'ID'            => $topic_id,
					'post_password' => 'post-secret',
				)
			);
			bbpress()->extend->buddypress->activity->topic_create( $post_id, $forum_id, array(), $user_id );
		}

		$this->assertEmpty( get_post_meta( $post_id, '_bbp_activity_id', true ) );
	}

	/**
	 * An unlocked password-protected topic or reply does not create activity.
	 *
	 * @dataProvider activity_edit_cases
	 *
	 * @param string $activity_type Topic or reply activity.
	 */
	public function test_unlocked_password_protected_post_does_not_create_activity( $activity_type ) {
		$user_id  = $this->factory->user->create();
		$forum_id = $this->factory->forum->create();
		$topic_id = $this->factory->topic->create(
			array(
				'post_parent'  => $forum_id,
				'post_author'  => $user_id,
				'post_content' => 'Public topic content',
			)
		);

		require_once ABSPATH . WPINC . '/class-phpass.php';
		$hasher               = new PasswordHash( 8, true );
		$cookie               = 'wp-postpass_' . COOKIEHASH;
		$old_cookie           = isset( $_COOKIE[ $cookie ] ) ? $_COOKIE[ $cookie ] : null;
		$_COOKIE[ $cookie ] = $hasher->HashPassword( 'post-secret' );

		try {
			if ( 'reply' === $activity_type ) {
				$post_id = $this->factory->reply->create(
					array(
						'post_parent'   => $topic_id,
						'post_author'   => $user_id,
						'post_content'  => 'Protected reply content',
						'post_password' => 'post-secret',
					)
				);
				bbpress()->extend->buddypress->activity->reply_create( $post_id, $topic_id, $forum_id, array(), $user_id );
			} else {
				$post_id = wp_update_post(
					array(
						'ID'            => $topic_id,
						'post_password' => 'post-secret',
					)
				);
				bbpress()->extend->buddypress->activity->topic_create( $post_id, $forum_id, array(), $user_id );
			}

			$this->assertFalse( post_password_required( $post_id ) );
			$this->assertEmpty( get_post_meta( $post_id, '_bbp_activity_id', true ) );
		} finally {
			if ( null === $old_cookie ) {
				unset( $_COOKIE[ $cookie ] );
			} else {
				$_COOKIE[ $cookie ] = $old_cookie;
			}
		}
	}

	/**
	 * Topics and replies do not create activity beneath a protected forum.
	 */
	public function test_inherited_password_protection_does_not_create_activity() {
		$user_id  = $this->factory->user->create();
		$forum_id = $this->factory->forum->create(
			array( 'post_password' => 'forum-secret' )
		);
		$topic_id = $this->factory->topic->create(
			array(
				'post_parent' => $forum_id,
				'post_author' => $user_id,
			)
		);
		$reply_id = $this->factory->reply->create(
			array(
				'post_parent' => $topic_id,
				'post_author' => $user_id,
			)
		);

		bbpress()->extend->buddypress->activity->topic_create( $topic_id, $forum_id, array(), $user_id );
		bbpress()->extend->buddypress->activity->reply_create( $reply_id, $topic_id, $forum_id, array(), $user_id );

		$this->assertEmpty( get_post_meta( $topic_id, '_bbp_activity_id', true ) );
		$this->assertEmpty( get_post_meta( $reply_id, '_bbp_activity_id', true ) );

		$public_forum_id = $this->factory->forum->create();
		$protected_topic_id = $this->factory->topic->create(
			array(
				'post_parent'   => $public_forum_id,
				'post_author'   => $user_id,
				'post_password' => 'topic-secret',
			)
		);
		$inherited_reply_id = $this->factory->reply->create(
			array(
				'post_parent' => $protected_topic_id,
				'post_author' => $user_id,
			)
		);

		bbpress()->extend->buddypress->activity->reply_create( $inherited_reply_id, $protected_topic_id, $public_forum_id, array(), $user_id );

		$this->assertEmpty( get_post_meta( $inherited_reply_id, '_bbp_activity_id', true ) );
	}

	/**
	 * Activity follows direct password changes on topics and replies.
	 *
	 * @dataProvider activity_edit_cases
	 *
	 * @param string $activity_type Topic or reply activity.
	 */
	public function test_activity_follows_post_password_changes( $activity_type ) {
		$user_id  = $this->factory->user->create();
		$forum_id = $this->factory->forum->create();
		$topic_id = $this->factory->topic->create(
			array(
				'post_parent'  => $forum_id,
				'post_author'  => $user_id,
				'post_content' => 'Public topic content',
			)
		);

		if ( 'reply' === $activity_type ) {
			$post_id = $this->factory->reply->create(
				array(
					'post_parent'  => $topic_id,
					'post_author'  => $user_id,
					'post_content' => 'Public reply content',
				)
			);
			bbpress()->extend->buddypress->activity->reply_create( $post_id, $topic_id, $forum_id, array(), $user_id );
		} else {
			$post_id = $topic_id;
			bbpress()->extend->buddypress->activity->topic_create( $post_id, $forum_id, array(), $user_id );
		}

		$activity_id = (int) get_post_meta( $post_id, '_bbp_activity_id', true );
		$this->assertGreaterThan( 0, $activity_id );

		add_filter( 'bbp_allow_revisions', '__return_false' );
		try {
			wp_update_post(
				array(
					'ID'            => $post_id,
					'post_password' => 'post-secret',
				)
			);

			$protected_activity = new BP_Activity_Activity( $activity_id );
			$this->assertEmpty( $protected_activity->component );

			wp_update_post(
				array(
					'ID'            => $post_id,
					'post_password' => '',
				)
			);

			$restored_activity_id = (int) get_post_meta( $post_id, '_bbp_activity_id', true );
			$this->assertGreaterThan( 0, $restored_activity_id );
			$this->assertNotSame( $activity_id, $restored_activity_id );
			$this->assertSame( $post_id, (int) ( new BP_Activity_Activity( $restored_activity_id ) )->item_id );
		} finally {
			remove_filter( 'bbp_allow_revisions', '__return_false' );
		}
	}

	/**
	 * Disabled revisions do not update or recreate missing activity.
	 *
	 * @dataProvider activity_edit_cases
	 *
	 * @param string $activity_type Topic or reply activity.
	 */
	public function test_disabled_revisions_do_not_update_or_recreate_activity( $activity_type ) {
		$user_id  = $this->factory->user->create();
		$forum_id = $this->factory->forum->create();
		$topic_id = $this->factory->topic->create(
			array(
				'post_parent'  => $forum_id,
				'post_author'  => $user_id,
				'post_content' => 'Original topic content',
			)
		);

		if ( 'reply' === $activity_type ) {
			$post_id = $this->factory->reply->create(
				array(
					'post_parent'  => $topic_id,
					'post_author'  => $user_id,
					'post_content' => 'Original reply content',
				)
			);
			bbpress()->extend->buddypress->activity->reply_create( $post_id, $topic_id, $forum_id, array(), $user_id );
		} else {
			$post_id = $topic_id;
			bbpress()->extend->buddypress->activity->topic_create( $post_id, $forum_id, array(), $user_id );
		}

		$activity_id      = (int) get_post_meta( $post_id, '_bbp_activity_id', true );
		$original_content = ( new BP_Activity_Activity( $activity_id ) )->content;
		$this->assertGreaterThan( 0, $activity_id );

		add_filter( 'bbp_allow_revisions', '__return_false' );
		try {
			wp_update_post(
				array(
					'ID'           => $post_id,
					'post_content' => 'Edited activity content',
				)
			);

			$this->assertSame( $original_content, ( new BP_Activity_Activity( $activity_id ) )->content );

			bp_activity_delete( array( 'id' => $activity_id ) );
			wp_update_post(
				array(
					'ID'           => $post_id,
					'post_content' => 'Edited after activity deletion',
				)
			);

			$remaining_activity_id = (int) get_post_meta( $post_id, '_bbp_activity_id', true );
			$this->assertEmpty( ( new BP_Activity_Activity( $remaining_activity_id ) )->component );
		} finally {
			remove_filter( 'bbp_allow_revisions', '__return_false' );
		}
	}

	/**
	 * Activity follows visibility changes when revisions are disabled.
	 *
	 * @dataProvider activity_edit_cases
	 *
	 * @param string $activity_type Topic or reply activity.
	 */
	public function test_visibility_changes_update_activity_when_revisions_are_disabled( $activity_type ) {
		$user_id  = $this->factory->user->create();
		$forum_id = $this->factory->forum->create();
		$topic_id = $this->factory->topic->create(
			array(
				'post_parent' => $forum_id,
				'post_author' => $user_id,
			)
		);

		if ( 'reply' === $activity_type ) {
			$post_id = $this->factory->reply->create(
				array(
					'post_parent' => $topic_id,
					'post_author' => $user_id,
				)
			);
			bbpress()->extend->buddypress->activity->reply_create( $post_id, $topic_id, $forum_id, array(), $user_id );
		} else {
			$post_id = $topic_id;
			bbpress()->extend->buddypress->activity->topic_create( $post_id, $forum_id, array(), $user_id );
		}

		$activity_id = (int) get_post_meta( $post_id, '_bbp_activity_id', true );
		$this->assertGreaterThan( 0, $activity_id );

		add_filter( 'bbp_allow_revisions', '__return_false' );
		try {
			wp_update_post(
				array(
					'ID'          => $post_id,
					'post_status' => bbp_get_pending_status_id(),
				)
			);

			$this->assertEmpty( ( new BP_Activity_Activity( $activity_id ) )->component );

			wp_update_post(
				array(
					'ID'          => $post_id,
					'post_status' => bbp_get_public_status_id(),
				)
			);

			$restored_activity_id = (int) get_post_meta( $post_id, '_bbp_activity_id', true );
			$this->assertGreaterThan( 0, $restored_activity_id );
			$this->assertNotSame( $activity_id, $restored_activity_id );
			$this->assertSame( $post_id, (int) ( new BP_Activity_Activity( $restored_activity_id ) )->item_id );
		} finally {
			remove_filter( 'bbp_allow_revisions', '__return_false' );
		}
	}

	/**
	 * Closed topics regain activity after protection or pending status.
	 */
	public function test_closed_topic_activity_is_restored() {
		$user_id  = $this->factory->user->create();
		$forum_id = $this->factory->forum->create();
		$topic_id = $this->factory->topic->create( array(
			'post_parent' => $forum_id,
			'post_author' => $user_id,
		) );
		bbpress()->extend->buddypress->activity->topic_create( $topic_id, $forum_id, array(), $user_id );
		$activity_id = (int) get_post_meta( $topic_id, '_bbp_activity_id', true );
		$this->assertGreaterThan( 0, $activity_id );

		add_filter( 'bbp_allow_revisions', '__return_false' );
		try {
			wp_update_post( array( 'ID' => $topic_id, 'post_status' => bbp_get_closed_status_id() ) );
			wp_update_post( array( 'ID' => $topic_id, 'post_password' => 'topic-secret' ) );
			$this->assertEmpty( ( new BP_Activity_Activity( $activity_id ) )->component );

			wp_update_post( array( 'ID' => $topic_id, 'post_password' => '' ) );
			$restored_id = (int) get_post_meta( $topic_id, '_bbp_activity_id', true );
			$this->assertNotSame( $activity_id, $restored_id );
			$this->assertSame( $topic_id, (int) ( new BP_Activity_Activity( $restored_id ) )->item_id );

			wp_update_post( array( 'ID' => $topic_id, 'post_status' => bbp_get_pending_status_id() ) );
			$this->assertEmpty( ( new BP_Activity_Activity( $restored_id ) )->component );
			wp_update_post( array( 'ID' => $topic_id, 'post_status' => bbp_get_closed_status_id() ) );
			$approved_id = (int) get_post_meta( $topic_id, '_bbp_activity_id', true );
			$this->assertNotSame( $restored_id, $approved_id );
			$this->assertSame( $topic_id, (int) ( new BP_Activity_Activity( $approved_id ) )->item_id );
		} finally {
			remove_filter( 'bbp_allow_revisions', '__return_false' );
		}
	}

	/**
	 * Anonymous conversion cannot preserve protected or unpublished activity.
	 *
	 * @dataProvider activity_edit_cases
	 *
	 * @param string $activity_type Topic or reply activity.
	 */
	public function test_existing_anonymous_activity_is_removed( $activity_type ) {
		$user_id  = $this->factory->user->create();
		$forum_id = $this->factory->forum->create();
		$topic_id = $this->factory->topic->create( array(
			'post_parent' => $forum_id,
			'post_author' => $user_id,
		) );

		add_filter( 'bbp_allow_revisions', '__return_false' );
		try {
			foreach ( array( 'password', 'status' ) as $change ) {
				if ( 'reply' === $activity_type ) {
					$post_id = $this->factory->reply->create( array(
						'post_parent' => $topic_id,
						'post_author' => $user_id,
					) );
					bbpress()->extend->buddypress->activity->reply_create( $post_id, $topic_id, $forum_id, array(), $user_id );
				} else {
					$post_id = $this->factory->topic->create( array(
						'post_parent' => $forum_id,
						'post_author' => $user_id,
					) );
					bbpress()->extend->buddypress->activity->topic_create( $post_id, $forum_id, array(), $user_id );
				}

				$activity_id = (int) get_post_meta( $post_id, '_bbp_activity_id', true );
				$this->assertGreaterThan( 0, $activity_id );
				wp_update_post( array( 'ID' => $post_id, 'post_author' => 0 ) );
				$this->assertSame( $post_id, (int) ( new BP_Activity_Activity( $activity_id ) )->item_id );

				$update = array( 'ID' => $post_id );
				if ( 'password' === $change ) {
					$update['post_password'] = 'post-secret';
				} else {
					$update['post_status'] = bbp_get_pending_status_id();
				}
				wp_update_post( $update );
				$this->assertEmpty( ( new BP_Activity_Activity( $activity_id ) )->component );
			}
		} finally {
			remove_filter( 'bbp_allow_revisions', '__return_false' );
		}
	}

	/**
	 * Visibility restoration does not create activity for anonymous posts.
	 *
	 * @dataProvider activity_edit_cases
	 *
	 * @param string $activity_type Topic or reply activity.
	 */
	public function test_visibility_restoration_skips_anonymous_posts( $activity_type ) {
		$user_id  = $this->factory->user->create();
		$forum_id = $this->factory->forum->create();
		$topic_id = $this->factory->topic->create(
			array(
				'post_parent'  => $forum_id,
				'post_author'  => $user_id,
				'post_password' => ( 'topic' === $activity_type ) ? 'post-secret' : '',
			)
		);

		if ( 'reply' === $activity_type ) {
			$post_id = $this->factory->reply->create(
				array(
					'post_parent'   => $topic_id,
					'post_author'   => 0,
					'post_password' => 'post-secret',
				)
			);
		} else {
			$post_id = $topic_id;
			wp_update_post(
				array(
					'ID'          => $post_id,
					'post_author' => 0,
				)
			);
		}

		$this->assertTrue( bbp_is_topic_anonymous( $post_id ) || bbp_is_reply_anonymous( $post_id ) );
		$this->assertEmpty( get_post_meta( $post_id, '_bbp_activity_id', true ) );

		add_filter( 'bbp_allow_revisions', '__return_false' );
		try {
			wp_update_post(
				array(
					'ID'            => $post_id,
					'post_password' => '',
				)
			);

			$this->assertEmpty( get_post_meta( $post_id, '_bbp_activity_id', true ) );
		} finally {
			remove_filter( 'bbp_allow_revisions', '__return_false' );
		}
	}

	/**
	 * Unrelated activity filters skip bbPress password visibility queries.
	 */
	public function test_unrelated_activity_filters_skip_password_lookup() {
		$forum_id         = $this->factory->forum->create( array( 'post_password' => 'forum-secret' ) );
		$password_queries = 0;
		$password_checks  = 0;
		$count_queries    = function( $query ) use ( &$password_queries ) {
			if ( false !== strpos( $query, 'CHAR_LENGTH( post_password ) > 0' ) ) {
				$password_queries++;
			}
			return $query;
		};
		$count_checks = function( $required, $post ) use ( $forum_id, &$password_checks ) {
			if ( $forum_id === (int) $post->ID ) {
				$password_checks++;
			}
			return $required;
		};

		add_filter( 'query', $count_queries );
		add_filter( 'post_password_required', $count_checks, 10, 2 );

		try {
			bp_activity_get( array( 'filter' => array( 'action' => 'activity_update' ) ) );
			bp_activity_get( array( 'filter' => array( 'object' => 'activity' ) ) );
			$this->assertSame( 0, $password_queries );
			$this->assertSame( 0, $password_checks );

			bp_activity_get( array( 'filter' => array( 'action' => 'activity_update,bbp_topic_create' ) ) );
			bp_activity_get( array( 'filter' => array( 'object' => array( 'activity', 'bbpress' ) ) ) );
			$this->assertSame( 1, $password_queries );
			$this->assertSame( 2, $password_checks );

			bp_activity_get( array( 'filter' => array( 'action' => array( ' BBP_TOPIC_CREATE ' ) ) ) );
			bp_activity_get( array( 'filter' => array( 'action' => "BBP_TOPIC_CREATE\0" ) ) );
			bp_activity_get( array( 'filter' => array( 'object' => 'bbpréss' ) ) );
			bp_activity_get( array( 'filter' => array( 'action' => '00' ) ) );
			bp_activity_get( array( 'filter' => array( 'action' => 'activity_update,0e1' ) ) );
			bp_activity_get( array( 'filter' => array( 'object' => array( 0 ) ) ) );
			$this->assertSame( 1, $password_queries );
			$this->assertSame( 8, $password_checks );

			if ( bp_is_active( 'groups' ) ) {
				bp_activity_get( array( 'filter' => array( 'object' => buddypress()->groups->id ) ) );
				$this->assertSame( 1, $password_queries );
				$this->assertSame( 9, $password_checks );
			}
		} finally {
			remove_filter( 'query', $count_queries );
			remove_filter( 'post_password_required', $count_checks, 10 );
		}
	}

	/**
	 * Activity visibility applies when action and object filters use uppercase.
	 */
	public function test_activity_visibility_filters_are_case_insensitive() {
		$user_id     = $this->factory->user->create();
		$no_role     = $this->factory->user->create();
		$participant = $this->factory->user->create();
		$moderator   = $this->factory->user->create();
		$keymaster   = $this->factory->user->create();
		bbp_set_user_role( $participant, bbp_get_participant_role() );
		bbp_set_user_role( $moderator, bbp_get_moderator_role() );
		bbp_set_user_role( $keymaster, bbp_get_keymaster_role() );
		get_userdata( $no_role )->set_role( '' );

		$forum_id = $this->factory->forum->create();
		$topic_id = $this->factory->topic->create(
			array(
				'post_parent' => $forum_id,
				'post_author' => $user_id,
			)
		);
		$reply_id = $this->factory->reply->create(
			array(
				'post_parent' => $topic_id,
				'post_author' => $user_id,
			)
		);

		bbpress()->extend->buddypress->activity->topic_create( $topic_id, $forum_id, array(), $user_id );
		bbpress()->extend->buddypress->activity->reply_create( $reply_id, $topic_id, $forum_id, array(), $user_id );

		$topic_activity_id = (int) get_post_meta( $topic_id, '_bbp_activity_id', true );
		$reply_activity_id = (int) get_post_meta( $reply_id, '_bbp_activity_id', true );
		$this->assertGreaterThan( 0, $topic_activity_id );
		$this->assertGreaterThan( 0, $reply_activity_id );

		bbp_hide_forum( $forum_id );

		$contexts = array(
			'anonymous'   => array( 0, false ),
			'no role'     => array( $no_role, false ),
			'participant' => array( $participant, false ),
			'moderator'   => array( $moderator, true ),
			'keymaster'   => array( $keymaster, true ),
		);

		foreach ( $contexts as $context => $data ) {
			list( $viewer_id, $can_read ) = $data;
			wp_set_current_user( $viewer_id );

			$topic_activity = bp_activity_get(
				array(
					'filter' => array( 'action' => 'BBP_TOPIC_CREATE' ),
				)
			);
			$reply_activity = bp_activity_get(
				array(
					'filter' => array( 'action' => 'activity_update, BBP_REPLY_CREATE' ),
				)
			);
			$padded_topic_activity = bp_activity_get(
				array(
					'filter' => array( 'action' => array( ' BBP_TOPIC_CREATE ' ) ),
				)
			);
			$nul_topic_activity = bp_activity_get(
				array(
					'filter' => array( 'action' => "BBP_TOPIC_CREATE\0" ),
				)
			);
			$numeric_activity = bp_activity_get(
				array(
					'filter' => array( 'action' => '00' ),
				)
			);
			$numeric_object_activity = bp_activity_get(
				array(
					'filter' => array( 'object' => array( 0 ) ),
				)
			);
			$forum_activity = bp_activity_get(
				array(
					'filter' => array( 'object' => 'BBPRESS' ),
				)
			);

			$topic_ids          = wp_list_pluck( $topic_activity['activities'], 'id' );
			$reply_ids          = wp_list_pluck( $reply_activity['activities'], 'id' );
			$padded_ids         = wp_list_pluck( $padded_topic_activity['activities'], 'id' );
			$nul_ids            = wp_list_pluck( $nul_topic_activity['activities'], 'id' );
			$numeric_ids        = wp_list_pluck( $numeric_activity['activities'], 'id' );
			$numeric_object_ids = wp_list_pluck( $numeric_object_activity['activities'], 'id' );
			$forum_ids          = wp_list_pluck( $forum_activity['activities'], 'id' );
			if ( $can_read ) {
				$this->assertContains( $topic_activity_id, $topic_ids, $context );
				$this->assertContains( $reply_activity_id, $reply_ids, $context );
				$this->assertContains( $topic_activity_id, $padded_ids, $context );
				$this->assertContains( $topic_activity_id, $nul_ids, $context );
				$this->assertEmpty( array_diff( array( $topic_activity_id, $reply_activity_id ), $numeric_ids ), $context );
				$this->assertEmpty( array_diff( array( $topic_activity_id, $reply_activity_id ), $numeric_object_ids ), $context );
				$this->assertEmpty( array_diff( array( $topic_activity_id, $reply_activity_id ), $forum_ids ), $context );
			} else {
				$this->assertNotContains( $topic_activity_id, $topic_ids, $context );
				$this->assertNotContains( $reply_activity_id, $reply_ids, $context );
				$this->assertNotContains( $topic_activity_id, $padded_ids, $context );
				$this->assertNotContains( $topic_activity_id, $nul_ids, $context );
				$this->assertEmpty( array_intersect( array( $topic_activity_id, $reply_activity_id ), $numeric_ids ), $context );
				$this->assertEmpty( array_intersect( array( $topic_activity_id, $reply_activity_id ), $numeric_object_ids ), $context );
				$this->assertEmpty( array_intersect( array( $topic_activity_id, $reply_activity_id ), $forum_ids ), $context );
			}
		}
	}

	/**
	 * Group-mapped activity must follow later forum visibility changes.
	 *
	 * @dataProvider restricted_ancestor_activity_cases
	 *
	 * @param string $status Restricted ancestor visibility.
	 * @param bool   $has_public_grandparent Whether the restricted forum has a public parent.
	 * @param string $activity_type Topic or reply activity.
	 */
	public function test_group_activity_tracks_ancestor_visibility_changes( $status, $has_public_grandparent, $activity_type ) {
		$user_id  = $this->factory->user->create();
		$group_id = $this->bp_factory->group->create( array( 'creator_id' => $user_id ) );
		$parent   = $has_public_grandparent
			? $this->factory->forum->create()
			: 0;
		$ancestor = $this->factory->forum->create( array( 'post_parent' => $parent ) );
		$forum_id = $this->factory->forum->create( array( 'post_parent' => $ancestor ) );
		bbp_add_forum_id_to_group( $group_id, $forum_id );
		bbp_add_group_id_to_forum( $forum_id, $group_id );
		$topic_id = $this->factory->topic->create( array(
			'post_parent' => $forum_id,
			'post_author' => $user_id,
		) );

		// Exercise the real group mapping when the activity is recorded.
		buddypress()->groups->current_group = groups_get_group( $group_id );
		if ( 'reply' === $activity_type ) {
			$post_id = $this->factory->reply->create( array(
				'post_parent' => $topic_id,
				'post_author' => $user_id,
			) );
			bbpress()->extend->buddypress->activity->reply_create( $post_id, $topic_id, $forum_id, array(), $user_id );
		} else {
			$post_id = $topic_id;
			bbpress()->extend->buddypress->activity->topic_create( $topic_id, $forum_id, array(), $user_id );
		}
		unset( buddypress()->groups->current_group );

		$activity_id = (int) get_post_meta( $post_id, '_bbp_activity_id', true );
		$activity    = new BP_Activity_Activity( $activity_id );
		$this->assertGreaterThan( 0, $activity_id );
		$this->assertSame( 'groups', $activity->component );
		$this->assertSame( $group_id, (int) $activity->item_id );
		$this->assertSame( $post_id, (int) $activity->secondary_item_id );
		$this->assertSame( 0, (int) $activity->hide_sitewide );
		wp_set_current_user( 0 );
		$before_activity = bp_activity_get( array( 'in' => array( $activity_id ) ) );
		$this->assertCount( 1, $before_activity['activities'] );

		if ( 'private' === $status ) {
			bbp_privatize_forum( $ancestor );
		} else {
			bbp_hide_forum( $ancestor );
		}

		wp_set_current_user( 0 );
		$public_activity = bp_activity_get( array( 'in' => array( $activity_id ) ) );
		$this->assertEmpty( $public_activity['activities'] );
		$uppercase_group_activity = bp_activity_get(
			array(
				'in'     => array( $activity_id ),
				'filter' => array( 'object' => 'GROUPS' ),
			)
		);
		$this->assertEmpty( $uppercase_group_activity['activities'] );
		$this->assertFalse( bp_activity_user_can_read( $activity, 0 ) );
		$hidden_activity = bp_activity_get( array(
			'in'          => array( $activity_id ),
			'show_hidden' => true,
		) );
		$this->assertEmpty( $hidden_activity['activities'] );
		$single_signature_activity = bp_activity_get( array(
			'in'               => array( $activity_id ),
			'show_hidden'      => true,
			'display_comments' => 'threaded',
			'per_page'         => false,
		) );
		$this->assertEmpty( $single_signature_activity['activities'] );

		$route    = '/' . bp_rest_namespace() . '/' . bp_rest_version() . '/activity';
		$request  = new WP_REST_Request( 'GET', $route );
		$request->set_param( 'include', array( $activity_id ) );
		$response = rest_do_request( $request );
		$this->assertSame( 200, $response->get_status() );
		$this->assertNotContains( $activity_id, wp_list_pluck( $response->get_data(), 'id' ) );
		$single_response = rest_do_request( new WP_REST_Request( 'GET', $route . '/' . $activity_id ) );
		$this->assertNotSame( 200, $single_response->get_status() );

		// A group member can request hidden group activity independently of forum access.
		wp_set_current_user( $user_id );
		$member_request = new WP_REST_Request( 'GET', $route );
		$member_request->set_param( 'group_id', $group_id );
		$member_request->set_param( 'include', array( $activity_id ) );
		$member_request->set_param( 'display_comments', 'threaded' );
		$member_response = rest_do_request( $member_request );
		$this->assertSame( 200, $member_response->get_status() );
		if ( bbp_is_forum_restricted_for_user( $forum_id, $user_id ) ) {
			$this->assertNotContains( $activity_id, wp_list_pluck( $member_response->get_data(), 'id' ) );
			$member_single = rest_do_request( new WP_REST_Request( 'GET', $route . '/' . $activity_id ) );
			$this->assertNotSame( 200, $member_single->get_status() );
		} else {
			$this->assertContains( $activity_id, wp_list_pluck( $member_response->get_data(), 'id' ) );
		}
	}

	/**
	 * Copied from `BBP_Forums_Group_Extension::new_forum()`.
	 *
	 * @since x.x.x
	 *
	 * @param int $forum_id The forum id.
	 * @param int $group_id The group id.
	 */
	private function attach_forum_to_group( $forum_id, $group_id ) {
		bbp_add_forum_id_to_group( $group_id, $forum_id );
		bbp_add_group_id_to_forum( $forum_id, $group_id );
	}

	/**
	 * Topic and reply activity update cases.
	 *
	 * @return array[] Test cases.
	 */
	public function activity_edit_cases() {
		return array(
			'topic' => array( 'topic' ),
			'reply' => array( 'reply' ),
		);
	}

	/**
	 * Editing a topic or reply updates its existing activity item.
	 *
	 * bbPress temporarily removes post-type revision support while updating a
	 * topic or reply to avoid creating a duplicate WordPress revision.
	 *
	 * @ticket BBP3328
	 * @dataProvider activity_edit_cases
	 *
	 * @param string $activity_type Topic or reply activity.
	 */
	public function test_edit_updates_activity_when_revision_support_is_temporarily_removed( $activity_type ) {
		$user_id  = $this->factory->user->create();
		$forum_id = $this->factory->forum->create();
		$topic_id = $this->factory->topic->create( array(
			'post_parent'  => $forum_id,
			'post_author'  => $user_id,
			'post_content' => 'Original topic activity content',
		) );

		if ( 'reply' === $activity_type ) {
			$post_id = $this->factory->reply->create( array(
				'post_parent'  => $topic_id,
				'post_author'  => $user_id,
				'post_content' => 'Original reply activity content',
			) );
			bbpress()->extend->buddypress->activity->reply_create( $post_id, $topic_id, $forum_id, array(), $user_id );
		} else {
			$post_id = $topic_id;
			bbpress()->extend->buddypress->activity->topic_create( $post_id, $forum_id, array(), $user_id );
		}

		$activity_id = (int) get_post_meta( $post_id, '_bbp_activity_id', true );
		$post_type    = get_post_type( $post_id );
		$had_support  = post_type_supports( $post_type, 'revisions' );
		$new_content  = 'Edited ' . $activity_type . ' activity content';

		$this->assertGreaterThan( 0, $activity_id );

		remove_post_type_support( $post_type, 'revisions' );
		try {
			wp_update_post( array(
				'ID'           => $post_id,
				'post_content' => $new_content,
			) );
		} finally {
			if ( $had_support ) {
				add_post_type_support( $post_type, 'revisions' );
			}
		}

		$activity = new BP_Activity_Activity( $activity_id );

		$this->assertSame( $activity_id, (int) get_post_meta( $post_id, '_bbp_activity_id', true ) );
		$this->assertStringContainsString( $new_content, $activity->content );
	}

	/**
	 * Dynamic activity actions for site-wide forum topics.
	 *
	 * @since 2.6.0 bbPress (r6370)
	 *
	 * @ticket BBP2794
	 */
	public function test_bp_activity_actions_for_site_wide_forum_topic() {
		$u = $this->factory->user->create();
		$f = $this->factory->forum->create();
		$t = $this->factory->topic->create( array(
			'post_parent' => $f,
			'post_author' => $u,
		) );

		// Set up our activity text test string.
		$user_link       = bbp_get_user_profile_link( $u );
		$topic_permalink = bbp_get_topic_permalink( $t );
		$topic_title     = get_post_field( 'post_title',   $t, 'raw' );
		$topic_link      = '<a href="' . $topic_permalink . '">' . $topic_title . '</a>';
		$forum_permalink = bbp_get_forum_permalink( $f );
		$forum_title     = get_post_field( 'post_title', $f, 'raw' );
		$forum_link      = '<a href="' . $forum_permalink . '">' . $forum_title . '</a>';
		$activity_text   = sprintf( esc_html__( '%1$s started the topic %2$s in the forum %3$s', 'bbpress' ), $user_link, $topic_link, $forum_link );

		// Create the activity.
		bbpress()->extend->buddypress->activity->topic_create( $t, $f, array(), $u );

		$activity_id = (int) get_post_meta( $t, '_bbp_activity_id', true );
		$activity    = new BP_Activity_Activity( $activity_id );

		// Test the default generated string.
		$this->assertEquals( $activity_text, $activity->action );

		// Update a few items for testing.
		wp_update_user( array( 'ID' => $u, 'display_name' => 'New Name' ) );
		$user_link = bbp_get_user_profile_link( $u );

		wp_update_post( array( 'ID' => $f, 'post_title' => 'New Forum Title' ) );
		$forum_link = '<a href="' . $forum_permalink . '">New Forum Title</a>';

		wp_update_post( array( 'ID' => $t, 'post_title' => 'New Topic Title' ) );
		$topic_link = '<a href="' . $topic_permalink . '">New Topic Title</a>';

		// Set up our new test string.
		$activity_text = sprintf( esc_html__( '%1$s started the topic %2$s in the forum %3$s', 'bbpress' ), $user_link, $topic_link, $forum_link );

		$activity = new BP_Activity_Activity( $activity_id );

		// Are we dynamic?
		$this->assertEquals( $activity_text, $activity->action );
	}

	/**
	 * Dynamic activity actions for replies to site-wide forum topics.
	 *
	 * @since 2.6.0 bbPress (r6370)
	 *
	 * @ticket BBP2794
	 */
	public function test_bp_activity_actions_for_reply_to_site_wide_forum_topic() {
		$u = $this->factory->user->create();
		$f = $this->factory->forum->create();
		$t = $this->factory->topic->create( array(
			'post_parent' => $f,
			'post_author' => $u,
		) );
		$r = $this->factory->reply->create( array(
			'post_parent' => $t,
			'post_author' => $u,
		) );

		// Set up our activity text test string.
		$user_link       = bbp_get_user_profile_link( $u );
		$topic_permalink = bbp_get_topic_permalink( $t );
		$topic_title     = get_post_field( 'post_title',   $t, 'raw' );
		$topic_link      = '<a href="' . $topic_permalink . '">' . $topic_title . '</a>';
		$forum_permalink = bbp_get_forum_permalink( $f );
		$forum_title     = get_post_field( 'post_title', $f, 'raw' );
		$forum_link      = '<a href="' . $forum_permalink . '">' . $forum_title . '</a>';
		$activity_text   = sprintf( esc_html__( '%1$s replied to the topic %2$s in the forum %3$s', 'bbpress' ), $user_link, $topic_link, $forum_link );

		// Create the activity.
		bbpress()->extend->buddypress->activity->reply_create( $r, $t, $f, array(), $u );

		$activity_id = (int) get_post_meta( $r, '_bbp_activity_id', true );
		$activity    = new BP_Activity_Activity( $activity_id );

		// Test the default generated string.
		$this->assertEquals( $activity_text, $activity->action );

		// Update a few items for testing.
		wp_update_user( array( 'ID' => $u, 'display_name' => 'New Name' ) );
		$user_link = bbp_get_user_profile_link( $u );

		wp_update_post( array( 'ID' => $f, 'post_title' => 'New Forum Title' ) );
		$forum_link = '<a href="' . $forum_permalink . '">New Forum Title</a>';

		wp_update_post( array( 'ID' => $t, 'post_title' => 'New Topic Title' ) );
		$topic_link = '<a href="' . $topic_permalink . '">New Topic Title</a>';

		// Set up our new test string.
		$activity_text = sprintf( esc_html__( '%1$s replied to the topic %2$s in the forum %3$s', 'bbpress' ), $user_link, $topic_link, $forum_link );

		$activity = new BP_Activity_Activity( $activity_id );

		// Are we dynamic?
		$this->assertEquals( $activity_text, $activity->action );
	}

	/**
	 * Dynamic activity actions for group forum topics.
	 *
	 * @since 2.6.0 bbPress (r6370)
	 *
	 * @ticket BBP2794
	 */
	public function test_bp_activity_actions_for_group_forum_topic() {

		// See https://bbpress.trac.wordpress.org/ticket/2794.
		// See https://bbpress.trac.wordpress.org/ticket/3089.
		$this->markTestSkipped( 'Skipping dynamic group activity action tests.' );

		$g = $this->bp_factory->group->create();
		$group = groups_get_group( array( 'group_id' => $g ) );
		$u = $group->creator_id;
		$f = $this->factory->forum->create();
		$t = $this->factory->topic->create( array(
			'post_parent' => $f,
			'post_author' => $u,
		) );
		$r = $this->factory->reply->create( array(
			'post_parent' => $t,
			'post_author' => $u,
		) );
		$this->attach_forum_to_group( $f, $g );
		buddypress()->groups->current_group = $group;

		// Set up our activity text test string.
		$user_link       = bbp_get_user_profile_link( $u );
		$topic_permalink = bbp_get_topic_permalink( $t );
		$topic_title     = get_post_field( 'post_title', $t, 'raw' );
		$topic_link      = '<a href="' . $topic_permalink . '">' . $topic_title . '</a>';
		$forum_permalink = bbp_get_forum_permalink( $f );
		$forum_title     = get_post_field( 'post_title', $f, 'raw' );
		$forum_link      = '<a href="' . $forum_permalink . '">' . $forum_title . '</a>';
		$activity_text   = sprintf( esc_html__( '%1$s started the topic %2$s in the forum %3$s', 'bbpress' ), $user_link, $topic_link, $forum_link );

		// Create the activity.
		bbpress()->extend->buddypress->activity->topic_create( $t, $f, array(), $u );

		$activity_id = (int) get_post_meta( $t, '_bbp_activity_id', true );
		$activity    = new BP_Activity_Activity( $activity_id );

		// Test the default generated string.
		$this->assertEquals( $activity_text, $activity->action );

		// Update a few items for testing.
		wp_update_user( array( 'ID' => $u, 'display_name' => 'New Name' ) );
		$user_link = bbp_get_user_profile_link( $u );

		wp_update_post( array( 'ID' => $f, 'post_title' => 'New Forum Title' ) );
		$forum_link = '<a href="' . $forum_permalink . '">New Forum Title</a>';

		wp_update_post( array( 'ID' => $t, 'post_title' => 'New Topic Title' ) );
		$topic_link = '<a href="' . $topic_permalink . '">New Topic Title</a>';

		// Set up our new test string.
		$activity_text = sprintf( esc_html__( '%1$s started the topic %2$s in the forum %3$s', 'bbpress' ), $user_link, $topic_link, $forum_link );

		$activity = new BP_Activity_Activity( $activity_id );

		// Are we dynamic?
		$this->assertEquals( $activity_text, $activity->action );
	}

	/**
	 * Dynamic activity actions for replies to group forum topics.
	 *
	 * @since 2.6.0 bbPress (r6370)
	 *
	 * @ticket BBP2794
	 */
	public function test_bp_activity_actions_for_reply_to_group_forum_topic() {

		// See https://bbpress.trac.wordpress.org/ticket/2794.
		// See https://bbpress.trac.wordpress.org/ticket/3089.
		$this->markTestSkipped( 'Skipping dynamic group activity action tests.' );

		$g = $this->bp_factory->group->create();
		$group = groups_get_group( array( 'group_id' => $g ) );
		$u = $group->creator_id;
		$f = $this->factory->forum->create();
		$t = $this->factory->topic->create( array(
			'post_parent' => $f,
			'post_author' => $u,
		) );
		$r = $this->factory->reply->create( array(
			'post_parent' => $t,
			'post_author' => $u,
		) );
		$this->attach_forum_to_group( $f, $g );
		buddypress()->groups->current_group = $group;

		// Set up our activity text test string.
		$user_link       = bbp_get_user_profile_link( $u );
		$topic_permalink = bbp_get_topic_permalink( $t );
		$topic_title     = get_post_field( 'post_title',   $t, 'raw' );
		$topic_link      = '<a href="' . $topic_permalink . '">' . $topic_title . '</a>';
		$forum_permalink = bbp_get_forum_permalink( $f );
		$forum_title     = get_post_field( 'post_title', $f, 'raw' );
		$forum_link      = '<a href="' . $forum_permalink . '">' . $forum_title . '</a>';
		$activity_text   = sprintf( esc_html__( '%1$s replied to the topic %2$s in the forum %3$s', 'bbpress' ), $user_link, $topic_link, $forum_link );

		// Create the activity.
		bbpress()->extend->buddypress->activity->reply_create( $r, $t, $f, array(), $u );

		$activity_id = (int) get_post_meta( $r, '_bbp_activity_id', true );
		$activity    = new BP_Activity_Activity( $activity_id );

		// Test the default generated string.
		$this->assertEquals( $activity_text, $activity->action );

		// Update a few items for testing.
		wp_update_user( array( 'ID' => $u, 'display_name' => 'New Name' ) );
		$user_link = bbp_get_user_profile_link( $u );

		wp_update_post( array( 'ID' => $f, 'post_title' => 'New Forum Title' ) );
		$forum_link = '<a href="' . $forum_permalink . '">New Forum Title</a>';

		wp_update_post( array( 'ID' => $t, 'post_title' => 'New Topic Title' ) );
		$topic_link = '<a href="' . $topic_permalink . '">New Topic Title</a>';

		// Set up our new test string.
		$activity_text = sprintf( esc_html__( '%1$s replied to the topic %2$s in the forum %3$s', 'bbpress' ), $user_link, $topic_link, $forum_link );

		$activity = new BP_Activity_Activity( $activity_id );

		// Are we dynamic?
		$this->assertEquals( $activity_text, $activity->action );
	}
}
