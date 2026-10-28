<?php

/**
 * Tests for user engagement functions.
 *
 * @group users
 * @group functions
 * @group engagements
 */
class BBP_Tests_Users_Functions_Engagements extends BBP_UnitTestCase {

	/**
	 * @covers ::bbp_add_user_to_object
	 * @covers ::bbp_remove_user_from_object
	 * @covers ::bbp_remove_user_from_all_objects
	 * @covers ::bbp_remove_object_from_all_users
	 * @covers ::bbp_remove_all_users_from_all_objects
	 * @covers ::bbp_get_users_for_object
	 * @covers ::bbp_is_object_of_user
	 */
	public function test_user_object_relationship_lifecycle() {
		$user_ids  = $this->factory->user->create_many( 3 );
		$topic_ids = $this->factory->topic->create_many( 3 );
		$meta_key  = '_bbp_test_relationship';
		$other_key = '_bbp_test_other_relationship';

		$this->assertTrue( bbp_add_user_to_object( $topic_ids[0], $user_ids[0], $meta_key ) );
		$this->assertTrue( bbp_add_user_to_object( $topic_ids[0], $user_ids[1], $meta_key ) );
		$this->assertTrue( bbp_add_user_to_object( $topic_ids[1], $user_ids[0], $meta_key ) );
		$this->assertTrue( bbp_add_user_to_object( $topic_ids[2], $user_ids[2], $meta_key ) );
		$this->assertFalse( bbp_add_user_to_object( $topic_ids[0], $user_ids[0], $meta_key, 'post', true ) );
		$this->assertEqualSets( array( $user_ids[0], $user_ids[1] ), bbp_get_users_for_object( $topic_ids[0], $meta_key ) );
		$this->assertTrue( bbp_is_object_of_user( $topic_ids[0], $user_ids[1], $meta_key ) );
		$this->assertFalse( bbp_is_object_of_user( $topic_ids[0], $user_ids[2], $meta_key ) );

		$this->assertTrue( bbp_remove_user_from_object( $topic_ids[0], $user_ids[1], $meta_key ) );
		$this->assertSame( array( $user_ids[0] ), bbp_get_users_for_object( $topic_ids[0], $meta_key ) );
		$this->assertTrue( bbp_remove_user_from_all_objects( $user_ids[0], $meta_key ) );
		$this->assertSame( array(), bbp_get_users_for_object( $topic_ids[0], $meta_key ) );
		$this->assertSame( array(), bbp_get_users_for_object( $topic_ids[1], $meta_key ) );
		$this->assertSame( array( $user_ids[2] ), bbp_get_users_for_object( $topic_ids[2], $meta_key ) );

		$this->assertTrue( bbp_add_user_to_object( $topic_ids[2], $user_ids[1], $meta_key ) );
		$this->assertTrue( bbp_add_user_to_object( $topic_ids[0], $user_ids[1], $meta_key ) );
		$this->assertTrue( bbp_remove_object_from_all_users( $topic_ids[2], $meta_key ) );
		$this->assertSame( array(), bbp_get_users_for_object( $topic_ids[2], $meta_key ) );
		$this->assertSame( array( $user_ids[1] ), bbp_get_users_for_object( $topic_ids[0], $meta_key ) );

		$this->assertTrue( bbp_add_user_to_object( $topic_ids[0], $user_ids[0], $meta_key ) );
		$this->assertTrue( bbp_add_user_to_object( $topic_ids[1], $user_ids[1], $meta_key ) );
		$this->assertTrue( bbp_add_user_to_object( $topic_ids[1], $user_ids[2], $other_key ) );
		$this->assertTrue( bbp_remove_all_users_from_all_objects( $meta_key ) );
		$this->assertSame( array(), bbp_get_users_for_object( $topic_ids[0], $meta_key ) );
		$this->assertSame( array(), bbp_get_users_for_object( $topic_ids[1], $meta_key ) );
		$this->assertSame( array( $user_ids[2] ), bbp_get_users_for_object( $topic_ids[1], $other_key ) );
	}

	/**
	 * @covers ::bbp_get_user_object_query
	 * @covers ::bbp_get_user_object_ids
	 * @covers ::bbp_get_moderator_forum_ids
	 * @covers ::bbp_get_user_engaged_topic_ids
	 */
	public function test_user_object_queries_return_only_matching_objects() {
		$user_id       = $this->factory->user->create();
		$other_user_id = $this->factory->user->create();
		$forum_ids     = $this->factory->forum->create_many( 2 );
		$topic_ids     = $this->factory->topic->create_many( 3 );
		$old_displayed = bbpress()->displayed_user;

		bbp_add_moderator( $forum_ids[0], $user_id );
		bbp_add_moderator( $forum_ids[1], $other_user_id );
		bbp_add_user_engagement( $user_id, $topic_ids[0] );
		bbp_add_user_engagement( $user_id, $topic_ids[1] );
		bbp_add_user_engagement( $other_user_id, $topic_ids[2] );

		try {
			$query = bbp_get_user_object_query( $user_id, 'engagements', '_bbp_engagement' );

			$this->assertSame( '_bbp_engagement', $query['meta_query'][0]['key'] );
			$this->assertSame( $user_id, $query['meta_query'][0]['value'] );
			$this->assertSame( 'NUMERIC', $query['meta_query'][0]['compare'] );

			bbpress()->displayed_user = get_userdata( $user_id );
			$query                     = bbp_get_user_object_query( array( 'post_status' => 'publish' ), 'engagements', '_bbp_engagement' );

			$this->assertSame( 'publish', $query['post_status'] );
			$this->assertSame( '_bbp_engagement', $query['meta_query'][0]['key'] );
			$this->assertSame( $user_id, $query['meta_query'][0]['value'] );
			$this->assertEqualSets( array( $forum_ids[0] ), bbp_get_moderator_forum_ids( $user_id ) );
			$this->assertEqualSets( array( $topic_ids[0], $topic_ids[1] ), bbp_get_user_engaged_topic_ids( $user_id ) );
			$this->assertEqualSets(
				array( $topic_ids[0], $topic_ids[1] ),
				bbp_get_user_object_ids(
					array(
						'user_id' => $user_id,
						'rel_key' => '_bbp_engagement',
					)
				)
			);
		} finally {
			bbpress()->displayed_user = $old_displayed;
		}
	}

	/**
	 * @covers ::bbp_get_topic_engagements
	 * @covers ::bbp_get_user_engagements
	 * @covers ::bbp_is_user_engaged
	 * @covers ::bbp_add_user_engagement
	 * @covers ::bbp_remove_user_engagement
	 */
	public function test_topic_engagement_lifecycle() {
		$user_ids       = $this->factory->user->create_many( 3 );
		$topic_id       = $this->factory->topic->create();
		$other_topic_id = $this->factory->topic->create();
		$old_query      = bbpress()->topic_query;

		$this->assertFalse( bbp_is_user_engaged( $user_ids[0], $topic_id ) );
		$this->assertFalse( bbp_add_user_engagement( 0, $topic_id ) );
		$this->assertTrue( bbp_add_user_engagement( $user_ids[0], $topic_id ) );
		$this->assertFalse( bbp_add_user_engagement( $user_ids[0], $topic_id ) );
		$this->assertTrue( bbp_add_user_engagement( $user_ids[1], $topic_id ) );
		$this->assertEqualSets( array( $user_ids[0], $user_ids[1] ), bbp_get_topic_engagements( $topic_id ) );
		$this->assertTrue( bbp_is_user_engaged( $user_ids[0], $topic_id ) );

		try {
			$this->assertTrue( bbp_get_user_engagements( $user_ids[0] ) );
			$this->assertSame( array( $topic_id ), wp_list_pluck( bbpress()->topic_query->posts, 'ID' ) );
			$this->assertNotContains( $other_topic_id, wp_list_pluck( bbpress()->topic_query->posts, 'ID' ) );
			$this->assertFalse( bbp_get_user_engagements( $user_ids[2] ) );
		} finally {
			bbpress()->topic_query = $old_query;
		}

		$this->assertTrue( bbp_remove_user_engagement( $user_ids[0], $topic_id ) );
		$this->assertFalse( bbp_remove_user_engagement( $user_ids[0], $topic_id ) );
		$this->assertSame( array( $user_ids[1] ), bbp_get_topic_engagements( $topic_id ) );
	}

	/**
	 * @covers ::bbp_update_topic_engagements
	 */
	public function test_update_topic_engagements_accepts_only_public_topics_and_replies() {
		$topic_author_id = $this->factory->user->create();
		$reply_author_id = $this->factory->user->create();
		$forum_id        = $this->factory->forum->create();
		$topic_id        = $this->factory->topic->create(
			array(
				'post_author' => $topic_author_id,
				'post_parent' => $forum_id,
				'topic_meta'  => array( 'forum_id' => $forum_id ),
			)
		);
		$reply_id        = $this->factory->reply->create(
			array(
				'post_author' => $reply_author_id,
				'post_parent' => $topic_id,
				'reply_meta'  => array(
					'forum_id' => $forum_id,
					'topic_id' => $topic_id,
				),
			)
		);
		$spam_reply_id   = $this->factory->reply->create(
			array(
				'post_author' => $this->factory->user->create(),
				'post_parent' => $topic_id,
				'post_status' => bbp_get_spam_status_id(),
				'reply_meta'  => array(
					'forum_id' => $forum_id,
					'topic_id' => $topic_id,
				),
			)
		);
		$pending_topic_id = $this->factory->topic->create(
			array(
				'post_author' => $topic_author_id,
				'post_parent' => $forum_id,
				'post_status' => bbp_get_pending_status_id(),
				'topic_meta'  => array( 'forum_id' => $forum_id ),
			)
		);
		$private_topic_id = $this->factory->topic->create(
			array(
				'post_author' => $topic_author_id,
				'post_parent' => $forum_id,
				'post_status' => bbp_get_private_status_id(),
				'topic_meta'  => array( 'forum_id' => $forum_id ),
			)
		);
		$private_reply_id = $this->factory->reply->create(
			array(
				'post_author' => $this->factory->user->create(),
				'post_parent' => $private_topic_id,
				'reply_meta'  => array(
					'forum_id' => $forum_id,
					'topic_id' => $private_topic_id,
				),
			)
		);
		$anonymous_reply_id = $this->factory->reply->create(
			array(
				'post_author' => 0,
				'post_parent' => $topic_id,
				'reply_meta'  => array(
					'forum_id' => $forum_id,
					'topic_id' => $topic_id,
				),
			)
		);

		bbp_remove_object_from_all_users( $topic_id, '_bbp_engagement' );

		$this->assertTrue( bbp_update_topic_engagements( $topic_id ) );
		$this->assertFalse( bbp_update_topic_engagements( $topic_id ) );
		$this->assertTrue( bbp_update_topic_engagements( $reply_id ) );
		$this->assertNull( bbp_update_topic_engagements( $spam_reply_id ) );
		$this->assertNull( bbp_update_topic_engagements( $pending_topic_id ) );
		$this->assertNull( bbp_update_topic_engagements( $private_reply_id ) );
		$this->assertFalse( bbp_update_topic_engagements( $anonymous_reply_id ) );
		$this->assertNull( bbp_update_topic_engagements( $this->factory->post->create() ) );
		$this->assertEqualSets( array( $topic_author_id, $reply_author_id ), bbp_get_topic_engagements( $topic_id ) );
	}

	/**
	 * @covers ::bbp_get_subscribers
	 */
	public function test_get_subscribers_returns_only_subscribed_users() {
		$user_ids = $this->factory->user->create_many( 3 );
		$forum_id = $this->factory->forum->create();

		bbp_add_user_subscription( $user_ids[0], $forum_id );
		bbp_add_user_subscription( $user_ids[1], $forum_id );

		$this->assertEqualSets( array( $user_ids[0], $user_ids[1] ), bbp_get_subscribers( $forum_id ) );
		$this->assertNotContains( $user_ids[2], bbp_get_subscribers( $forum_id ) );
	}

	/**
	 * @covers ::bbp_update_user_topic_subscription
	 * @covers ::bbp_update_topic_subscription
	 * @covers ::bbp_update_reply_subscription
	 */
	public function test_update_topic_subscription_adapters_apply_form_choice() {
		$user_id  = $this->factory->user->create();
		$topic_id = $this->factory->topic->create();
		$old_post = $_POST;

		try {
			$_POST['bbp_topic_subscription'] = 'bbp_subscribe';
			bbp_update_user_topic_subscription( $user_id, $topic_id );
			$this->assertTrue( bbp_is_user_subscribed( $user_id, $topic_id ) );

			unset( $_POST['bbp_topic_subscription'] );
			bbp_update_topic_subscription( $topic_id, 0, array(), $user_id );
			$this->assertFalse( bbp_is_user_subscribed( $user_id, $topic_id ) );

			$_POST['bbp_topic_subscription'] = 'bbp_subscribe';
			bbp_update_reply_subscription( 0, $topic_id, 0, array(), $user_id );
			$this->assertTrue( bbp_is_user_subscribed( $user_id, $topic_id ) );

			add_filter( 'bbp_is_subscriptions_active', '__return_false' );
			unset( $_POST['bbp_topic_subscription'] );
			bbp_update_user_topic_subscription( $user_id, $topic_id );
			$this->assertTrue( bbp_is_user_subscribed( $user_id, $topic_id ) );
		} finally {
			remove_filter( 'bbp_is_subscriptions_active', '__return_false' );
			$_POST = $old_post;
		}
	}
}
