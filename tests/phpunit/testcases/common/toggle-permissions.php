<?php

/**
 * Tests for front-end moderation toggle permissions.
 *
 * @group common
 * @group capabilities
 */
class BBP_Tests_Common_Toggle_Permissions extends BBP_UnitTestCase {

	protected $old_get;
	protected $old_errors;

	public function setUp(): void {
		parent::setUp();

		$this->old_get    = $_GET;
		$this->old_errors = bbpress()->errors;
	}

	public function tearDown(): void {
		$_GET              = $this->old_get;
		bbpress()->errors  = $this->old_errors;

		parent::tearDown();
	}

	/**
	 * @covers ::bbp_toggle_topic_handler
	 * @covers ::bbp_toggle_reply_handler
	 */
	public function test_participant_cannot_perform_moderation_toggles() {
		$participant_id = $this->factory->user->create();
		$forum_id       = $this->factory->forum->create();
		$topic_id       = $this->factory->topic->create( array(
			'post_author' => $participant_id,
			'post_parent' => $forum_id,
			'post_status' => bbp_get_pending_status_id(),
			'topic_meta'  => array( 'forum_id' => $forum_id ),
		) );
		$reply_id       = $this->factory->reply->create( array(
			'post_author' => $participant_id,
			'post_parent' => $topic_id,
			'post_status' => bbp_get_pending_status_id(),
			'reply_meta'  => array( 'forum_id' => $forum_id, 'topic_id' => $topic_id ),
		) );

		bbp_set_user_role( $participant_id, bbp_get_participant_role() );
		$this->set_current_user( $participant_id );
		$this->assertTrue( current_user_can( 'edit_topic', $topic_id ) );
		$this->assertTrue( current_user_can( 'edit_reply', $reply_id ) );
		$this->assertFalse( current_user_can( 'moderate', $topic_id ) );

		$_GET['topic_id'] = $topic_id;
		foreach ( array( 'approve', 'close', 'stick', 'spam' ) as $toggle ) {
			bbpress()->errors = new WP_Error();
			bbp_toggle_topic_handler( 'bbp_toggle_topic_' . $toggle );
			$this->assertContains( 'bbp_toggle_topic_permission', bbpress()->errors->get_error_codes() );
		}

		$_GET['reply_id'] = $reply_id;
		foreach ( array( 'approve', 'spam' ) as $toggle ) {
			bbpress()->errors = new WP_Error();
			bbp_toggle_reply_handler( 'bbp_toggle_reply_' . $toggle );
			$this->assertContains( 'bbp_toggle_reply_permission', bbpress()->errors->get_error_codes() );
		}

		$this->assertSame( bbp_get_pending_status_id(), get_post_status( $topic_id ) );
		$this->assertSame( bbp_get_pending_status_id(), get_post_status( $reply_id ) );
	}
}
