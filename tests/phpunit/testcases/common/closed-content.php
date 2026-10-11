<?php

/**
 * Tests for editing existing content after it is closed.
 *
 * @group common
 * @group capabilities
 */
class BBP_Tests_Common_Closed_Content extends BBP_UnitTestCase {

	/**
	 * Closing content must not change its existing edit capabilities.
	 *
	 * @covers ::bbp_map_topic_meta_caps
	 * @covers ::bbp_map_reply_meta_caps
	 */
	public function test_closed_content_preserves_existing_edit_capabilities() {
		$topic_owner_id     = $this->factory->user->create( array( 'role' => bbp_get_participant_role() ) );
		$reply_owner_id     = $this->factory->user->create( array( 'role' => bbp_get_participant_role() ) );
		$participant_id     = $this->factory->user->create( array( 'role' => bbp_get_participant_role() ) );
		$spectator_id       = $this->factory->user->create( array( 'role' => bbp_get_spectator_role() ) );
		$forum_moderator_id = $this->factory->user->create( array( 'role' => bbp_get_participant_role() ) );
		$moderator_id       = $this->factory->user->create();
		$keymaster_id       = $this->factory->user->create();
		$forum_id           = $this->factory->forum->create();
		$topic_id           = $this->factory->topic->create(
			array(
				'post_author' => $topic_owner_id,
				'post_parent' => $forum_id,
				'topic_meta'  => array( 'forum_id' => $forum_id ),
			)
		);
		$reply_id           = $this->factory->reply->create(
			array(
				'post_author' => $reply_owner_id,
				'post_parent' => $topic_id,
				'reply_meta'  => array(
					'forum_id' => $forum_id,
					'topic_id' => $topic_id,
				),
			)
		);

		bbp_add_moderator( $forum_id, $forum_moderator_id );
		bbp_set_user_role( $moderator_id, bbp_get_moderator_role() );
		bbp_set_user_role( $keymaster_id, bbp_get_keymaster_role() );
		bbp_close_topic( $topic_id );
		bbp_close_forum( $forum_id );

		$this->assertTrue( user_can( $topic_owner_id, 'edit_topic', $topic_id ) );
		$this->assertTrue( user_can( $spectator_id, 'read_topic', $topic_id ) );
		$this->assertTrue( user_can( $reply_owner_id, 'read_topic', $topic_id ) );
		$this->assertTrue( user_can( $reply_owner_id, 'edit_reply', $reply_id ) );
		$this->assertFalse( user_can( $participant_id, 'edit_topic', $topic_id ) );
		$this->assertFalse( user_can( $participant_id, 'edit_reply', $reply_id ) );

		foreach ( array( $forum_moderator_id, $moderator_id, $keymaster_id ) as $user_id ) {
			$this->assertTrue( user_can( $user_id, 'moderate', $topic_id ) );
			$this->assertTrue( user_can( $user_id, 'edit_topic', $topic_id ) );
			$this->assertTrue( user_can( $user_id, 'edit_reply', $reply_id ) );
		}
	}

	/**
	 * The topic edit form must submit its current closed forum without making
	 * other closed forums available as move destinations.
	 *
	 * @covers BBP_Walker_Dropdown::start_el
	 */
	public function test_topic_edit_dropdown_keeps_current_closed_forum_available() {
		$user_id          = $this->factory->user->create( array( 'role' => bbp_get_participant_role() ) );
		$current_forum_id = $this->factory->forum->create( array( 'post_title' => 'Current closed forum' ) );
		$other_forum_id   = $this->factory->forum->create( array( 'post_title' => 'Other closed forum' ) );
		$topic_id         = $this->factory->topic->create( array( 'post_author' => $user_id, 'post_parent' => $current_forum_id, 'topic_meta' => array( 'forum_id' => $current_forum_id ) ) );

		bbp_close_forum( $current_forum_id );
		bbp_close_forum( $other_forum_id );
		$this->set_current_user( $user_id );
		bbpress()->current_topic_id = $topic_id;
		add_filter( 'bbp_is_topic_edit', '__return_true' );

		try {
			$output = bbp_get_dropdown(
				array(
					'posts'        => array( get_post( $current_forum_id ), get_post( $other_forum_id ) ),
					'selected'     => $current_forum_id,
					'options_only' => true,
				)
			);
		} finally {
			remove_filter( 'bbp_is_topic_edit', '__return_true' );
		}

		$new_topic_output = bbp_get_dropdown(
			array(
				'posts'        => array( get_post( $current_forum_id ) ),
				'selected'     => $current_forum_id,
				'options_only' => true,
			)
		);
		bbpress()->current_topic_id = 0;

		$this->assertStringContainsString( 'value="' . $current_forum_id . '" selected=\'selected\'>Current closed forum', $output );
		$this->assertStringContainsString( 'disabled="disabled" value="">Other closed forum', $output );
		$this->assertStringContainsString( 'disabled="disabled" value="">Current closed forum', $new_topic_output );
	}
}
