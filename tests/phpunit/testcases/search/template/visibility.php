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
}
