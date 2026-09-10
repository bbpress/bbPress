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
