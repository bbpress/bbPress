<?php

/**
 * Tests for the common engagements API.
 *
 * @group common
 * @group engagements
 * @group counts
 */
class BBP_Tests_Common_Engagements extends BBP_UnitTestCase {

	/**
	 * @covers BBP_User_Engagements_User::get_query
	 * @ticket BBP3401
	 */
	public function test_user_strategy_keeps_forum_and_topic_subscriptions_separate() {
		$strategy = new BBP_User_Engagements_User();
		$user_id  = $this->factory->user->create();
		$forum_id = $this->factory->forum->create();
		$topic_id = $this->factory->topic->create();

		update_user_option( $user_id, '_bbp_forum_subscriptions', $forum_id );
		update_user_option( $user_id, '_bbp_subscriptions', $topic_id );

		$forum_query = $strategy->get_query( $user_id, 'get_user_forum_subscriptions', '_bbp_subscription' );
		$topic_query = $strategy->get_query( $user_id, 'get_user_topic_subscriptions', '_bbp_subscription' );

		$this->assertSame( array( $forum_id ), $forum_query['post__in'] );
		$this->assertSame( array( $topic_id ), $topic_query['post__in'] );
	}

	/**
	 * @covers BBP_User_Engagements_Term::add_user_to_object
	 * @covers BBP_User_Engagements_Term::remove_user_from_all_objects
	 * @covers BBP_User_Engagements_Term::get_users_for_object
	 * @ticket BBP3735
	 */
	public function test_term_strategy_handles_removal_for_a_missing_user_relationship() {
		$strategy = new BBP_User_Engagements_Term();
		$user_ids = $this->factory->user->create_many( 2 );
		$topic_id = $this->factory->topic->create();
		$meta_key = '_bbp_tmiss';

		$this->assertNotWPError( $strategy->add_user_to_object( $topic_id, $user_ids[0], $meta_key ) );

		$this->assertFalse( $strategy->remove_user_from_all_objects( $user_ids[1], $meta_key ) );
		$this->assertSame( array( $user_ids[0] ), $strategy->get_users_for_object( $topic_id, $meta_key ) );
	}

	/**
	 * @covers BBP_User_Engagements_Term::add_user_to_object
	 * @covers BBP_User_Engagements_Term::remove_user_from_object
	 * @covers BBP_User_Engagements_Term::remove_all_users_from_all_objects
	 * @covers BBP_User_Engagements_Term::get_users_for_object
	 * @ticket BBP3735
	 */
	public function test_term_strategy_removes_all_users_from_all_objects() {
		$strategy  = new BBP_User_Engagements_Term();
		$user_ids  = $this->factory->user->create_many( 2 );
		$topic_ids = $this->factory->topic->create_many( 2 );
		$meta_key  = '_bbp_tall';
		$other_key = '_bbp_tkeep';
		$tax_key   = "{$meta_key}_post";

		$strategy->add_user_to_object( $topic_ids[0], $user_ids[0], $meta_key );
		$strategy->add_user_to_object( $topic_ids[0], $user_ids[1], $meta_key );
		$strategy->add_user_to_object( $topic_ids[1], $user_ids[0], $meta_key );
		$strategy->add_user_to_object( $topic_ids[0], $user_ids[0], $other_key );

		$this->assertTrue( $strategy->remove_user_from_object( $topic_ids[0], $user_ids[1], $meta_key ) );
		$this->assertInstanceOf( 'WP_Term', get_term_by( 'slug', "{$meta_key}_user_id_{$user_ids[1]}", $tax_key ) );
		$this->assertSame( array( $user_ids[0] ), $strategy->get_users_for_object( $topic_ids[0], $meta_key ) );
		$this->assertSame( array( $user_ids[0] ), $strategy->get_users_for_object( $topic_ids[1], $meta_key ) );

		$this->assertTrue( $strategy->remove_all_users_from_all_objects( $meta_key ) );
		$this->assertSame( array(), $strategy->get_users_for_object( $topic_ids[0], $meta_key ) );
		$this->assertSame( array(), $strategy->get_users_for_object( $topic_ids[1], $meta_key ) );
		$this->assertSame( array( $user_ids[0] ), $strategy->get_users_for_object( $topic_ids[0], $other_key ) );
		$this->assertSame(
			array(),
			get_terms(
				array(
					'taxonomy'   => $tax_key,
					'hide_empty' => false,
					'fields'     => 'ids',
				)
			)
		);
		$this->assertTrue( $strategy->remove_all_users_from_all_objects( $meta_key ) );
	}

	/**
	 * @covers BBP_User_Engagements_User::get_users_for_object
	 * @covers BBP_User_Engagements_User::remove_all_users_from_all_objects
	 */
	public function test_user_strategy_handles_relationship_keys_with_quotes() {
		$strategy  = new BBP_User_Engagements_User();
		$user_id   = $this->factory->user->create();
		$topic_id  = $this->factory->topic->create();
		$meta_key  = "_bbp_custom'key";

		$this->assertNotFalse( $strategy->add_user_to_object( $topic_id, $user_id, $meta_key ) );
		$this->assertSame( array( $user_id ), $strategy->get_users_for_object( $topic_id, $meta_key ) );
		$this->assertTrue( $strategy->remove_all_users_from_all_objects( $meta_key ) );
		$this->assertSame( array(), $strategy->get_users_for_object( $topic_id, $meta_key ) );
	}

	/**
	 * @covers BBP_User_Engagements_Term::remove_object_from_all_users
	 * @ticket BBP3678
	 */
	public function test_term_strategy_removes_only_the_requested_relationship() {
		$strategy = new BBP_User_Engagements_Term();
		$user_id  = $this->factory->user->create();
		$topic_id = $this->factory->topic->create();

		$strategy->add_user_to_object( $topic_id, $user_id, '_bbp_engagement' );
		$strategy->add_user_to_object( $topic_id, $user_id, '_bbp_favorite' );
		$strategy->add_user_to_object( $topic_id, $user_id, '_bbp_subscription' );

		$this->assertSame( array( $user_id ), $strategy->get_users_for_object( $topic_id, '_bbp_engagement' ) );
		$this->assertSame( array( $user_id ), $strategy->get_users_for_object( $topic_id, '_bbp_favorite' ) );
		$this->assertSame( array( $user_id ), $strategy->get_users_for_object( $topic_id, '_bbp_subscription' ) );

		$strategy->remove_object_from_all_users( $topic_id, '_bbp_engagement' );

		$this->assertSame( array(), $strategy->get_users_for_object( $topic_id, '_bbp_engagement' ) );
		$this->assertSame( array( $user_id ), $strategy->get_users_for_object( $topic_id, '_bbp_favorite' ) );
		$this->assertSame( array( $user_id ), $strategy->get_users_for_object( $topic_id, '_bbp_subscription' ) );
	}
}
