<?php

/**
 * Tests for object moderator lookup.
 *
 * @group users
 * @group capabilities
 */
class BBP_Tests_Users_Functions_Moderators extends BBP_UnitTestCase {

	/**
	 * @covers ::bbp_get_moderators
	 */
	public function test_object_without_moderators_does_not_return_unrelated_users() {
		$forum_id     = $this->factory->forum->create();
		$moderator_id = $this->factory->user->create();
		$other_id     = $this->factory->user->create();

		$this->assertSame( array(), bbp_get_moderators( $forum_id ) );
		$this->assertTrue( bbp_add_moderator( $forum_id, $moderator_id ) );
		$this->assertSame( array( $moderator_id ), wp_list_pluck( bbp_get_moderators( $forum_id ), 'ID' ) );
		$this->assertNotContains( $other_id, wp_list_pluck( bbp_get_moderators( $forum_id ), 'ID' ) );
		$this->assertTrue( bbp_remove_moderator( $forum_id, $moderator_id ) );
		$this->assertSame( array(), bbp_get_moderators( $forum_id ) );
	}

	/**
	 * @ticket BBP3794
	 * @dataProvider moderator_engagement_strategies
	 * @covers ::bbp_remove_all_moderators
	 */
	public function test_remove_all_moderators_only_affects_the_requested_object( $class_name ) {
		$previous_strategy = bbpress()->engagements;
		bbpress()->engagements = new $class_name();

		try {
			$forum_id        = $this->factory->forum->create();
			$other_forum     = $this->factory->forum->create();
			$moderator_id    = $this->factory->user->create();
			$other_moderator = $this->factory->user->create();

			bbp_add_moderator( $forum_id, $moderator_id );
			bbp_add_moderator( $forum_id, $other_moderator );
			bbp_add_moderator( $other_forum, $moderator_id );
			$this->assertEqualSets( array( $moderator_id, $other_moderator ), bbp_get_moderator_ids( $forum_id ) );

			$this->assertTrue( bbp_remove_all_moderators( $forum_id ) );
			$this->assertSame( array(), bbp_get_moderator_ids( $forum_id ) );
			$this->assertSame( array( $moderator_id ), bbp_get_moderator_ids( $other_forum ) );
		} finally {
			bbpress()->engagements = $previous_strategy;
		}
	}

	/**
	 * @return array Engagement strategies.
	 */
	public function moderator_engagement_strategies() {
		return array(
			'meta' => array( 'BBP_User_Engagements_Meta' ),
			'term' => array( 'BBP_User_Engagements_Term' ),
			'user' => array( 'BBP_User_Engagements_User' )
		);
	}
}
