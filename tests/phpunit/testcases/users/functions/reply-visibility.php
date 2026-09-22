<?php

/**
 * Tests that public user reply listings respect parent topic visibility.
 *
 * @group users
 * @group visibility
 */
class BBP_Tests_Users_Functions_Reply_Visibility extends BBP_UnitTestCase {
	private $old_reply_query;

	public function setUp(): void {
		parent::setUp();
		$this->old_reply_query = bbpress()->reply_query;
	}

	public function tearDown(): void {
		bbpress()->reply_query = $this->old_reply_query;
		$this->set_current_user( 0 );

		parent::tearDown();
	}

	/**
	 * @covers ::bbp_get_user_replies_created
	 */
	public function test_public_user_reply_listing_hides_reply_after_topic_is_unapproved() {
		$user_id  = $this->factory->user->create( array( 'role' => bbp_get_participant_role() ) );
		$forum_id = $this->factory->forum->create();
		$topic_id = $this->factory->topic->create( array( 'post_parent' => $forum_id, 'topic_meta' => array( 'forum_id' => $forum_id ) ) );
		$reply_id = $this->factory->reply->create( array( 'post_author' => $user_id, 'post_parent' => $topic_id, 'reply_meta' => array( 'forum_id' => $forum_id, 'topic_id' => $topic_id ) ) );
		$this->set_current_user( 0 );
		$this->assertTrue( bbp_get_user_replies_created( array( 'author' => $user_id ) ) );
		$this->assertContains( $reply_id, wp_list_pluck( bbpress()->reply_query->posts, 'ID' ) );

		bbp_unapprove_topic( $topic_id );

		$this->assertSame( bbp_get_public_status_id(), get_post_status( $reply_id ) );
		$this->assertFalse( bbp_get_user_replies_created( array( 'author' => $user_id ) ) );
		$this->assertSame( 0, (int) bbpress()->reply_query->found_posts );
	}
}
