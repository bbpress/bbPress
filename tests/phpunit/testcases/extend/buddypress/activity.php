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
		$parent  = $this->factory->forum->create(
			array(
				'post_password' => 'parent-secret',
			)
		);
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
	 * Activity respects passwords set directly on topics and replies.
	 */
	public function test_password_protected_post_activity_requires_password() {
		$user_id  = $this->factory->user->create();
		$forum_id = $this->factory->forum->create();
		$topic_id = $this->factory->topic->create(
			array(
				'post_parent'   => $forum_id,
				'post_author'   => $user_id,
				'post_content'  => 'Protected topic content',
				'post_password' => 'post-secret',
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
				'post_parent'   => $public_topic_id,
				'post_author'   => $user_id,
				'post_content'  => 'Protected reply content',
				'post_password' => 'post-secret',
			)
		);

		bbpress()->extend->buddypress->activity->topic_create( $topic_id, $forum_id, array(), $user_id );
		bbpress()->extend->buddypress->activity->reply_create( $reply_id, $public_topic_id, $forum_id, array(), $user_id );

		$activity_ids = array(
			(int) get_post_meta( $topic_id, '_bbp_activity_id', true ),
			(int) get_post_meta( $reply_id, '_bbp_activity_id', true ),
		);
		$this->assertNotContains( 0, $activity_ids );

		wp_set_current_user( 0 );
		$this->assertEmpty( bp_activity_get( array( 'in' => $activity_ids ) )['activities'] );

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
	 * Unrelated activity filters skip bbPress password visibility queries.
	 */
	public function test_unrelated_activity_filters_skip_password_lookup() {
		$password_queries = 0;
		$count_queries    = function( $query ) use ( &$password_queries ) {
			if ( true === $query->get( 'has_password' ) ) {
				$password_queries++;
			}
		};

		add_action( 'pre_get_posts', $count_queries );

		try {
			bp_activity_get( array( 'filter' => array( 'action' => 'activity_update' ) ) );
			bp_activity_get( array( 'filter' => array( 'object' => 'activity' ) ) );
			$this->assertSame( 0, $password_queries );

			bp_activity_get( array( 'filter' => array( 'action' => 'activity_update,bbp_topic_create' ) ) );
			bp_activity_get( array( 'filter' => array( 'object' => array( 'activity', 'bbpress' ) ) ) );
			$this->assertSame( 2, $password_queries );

			if ( bp_is_active( 'groups' ) ) {
				bp_activity_get( array( 'filter' => array( 'object' => buddypress()->groups->id ) ) );
				$this->assertSame( 3, $password_queries );
			}
		} finally {
			remove_action( 'pre_get_posts', $count_queries );
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
