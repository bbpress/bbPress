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
