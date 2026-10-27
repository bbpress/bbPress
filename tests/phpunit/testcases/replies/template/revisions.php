<?php

/**
 * Tests for the reply revision template functions.
 *
 * @group replies
 * @group template
 * @group revisions
 */
class BBP_Tests_Replies_Template_Revisions extends BBP_UnitTestCase {

	/**
	 * @covers ::bbp_reply_content_append_revisions
	 */
	public function test_bbp_reply_content_append_revisions() {
		$topic_id = $this->factory->topic->create();
		$reply_id = $this->factory->reply->create( array( 'post_parent' => $topic_id ) );

		$this->assertSame( 'Reply content', bbp_reply_content_append_revisions( 'Reply content', $reply_id ) );
		$revision_id = $this->create_reply_revision( $reply_id );
		update_post_meta( $reply_id, '_bbp_revision_log', array(
			$revision_id => array( 'author' => 0, 'reason' => 'Updated.' ),
		) );
		$this->assertSame( 'Reply content' . bbp_get_reply_revision_log( $reply_id ), bbp_reply_content_append_revisions( 'Reply content', $reply_id ) );
	}

	/**
	 * @covers ::bbp_reply_revision_log
	 * @covers ::bbp_get_reply_revision_log
	 */
	public function test_bbp_get_reply_revision_log() {
		$topic_id = $this->factory->topic->create();
		$reply_id = $this->factory->reply->create( array( 'post_parent' => $topic_id ) );
		$this->assertFalse( bbp_get_reply_revision_log( $reply_id ) );

		$revision_id = $this->create_reply_revision( $reply_id );
		update_post_meta( $reply_id, '_bbp_revision_log', array(
			$revision_id => array( 'author' => 0, 'reason' => 'Clarified the answer.' ),
		) );
		$log = bbp_get_reply_revision_log( $reply_id );

		$this->assertStringContainsString( 'bbp-reply-revision-log-' . $reply_id, $log );
		$this->assertStringContainsString( 'item-' . $revision_id, $log );
		$this->assertStringContainsString( 'Clarified the answer.', $log );
		$this->expectOutputString( $log );
		bbp_reply_revision_log( $reply_id );
	}

	/**
	 * @covers ::bbp_get_reply_revision_log
	 */
	public function test_bbp_get_reply_revision_log_respects_logged_author_id() {
		$current_user_id = $this->factory->user->create( array( 'display_name' => 'Current Reply Editor' ) );
		bbp_set_user_role( $current_user_id, bbp_get_keymaster_role() );
		$this->set_current_user( $current_user_id );
		$topic_id        = $this->factory->topic->create( array( 'post_author' => $current_user_id ) );
		$reply_id        = $this->factory->reply->create(
			array(
				'post_author' => $current_user_id,
				'post_parent' => $topic_id,
			)
		);
		$revision_id     = $this->create_reply_revision( $reply_id );
		$other_user_id   = $this->factory->user->create( array( 'display_name' => 'Other Reply Editor' ) );
		bbp_set_user_role( $other_user_id, bbp_get_moderator_role() );

		bbp_update_reply_revision_log(
			array(
				'reply_id'    => $reply_id,
				'revision_id' => $revision_id,
			)
		);

		$log = bbp_get_reply_revision_log( $reply_id );
		$this->assertStringContainsString( get_the_author_meta( 'display_name', $current_user_id ), $log );

		bbp_update_reply_revision_log(
			array(
				'reply_id'    => $reply_id,
				'revision_id' => $revision_id,
				'author_id'   => $other_user_id,
			)
		);

		$log = bbp_get_reply_revision_log( $reply_id );
		$this->assertStringContainsString( bbp_get_user_profile_url( $other_user_id ), $log );
		$this->assertStringContainsString( get_avatar( $other_user_id, 14 ), $log );
		$this->assertStringContainsString( 'Other Reply Editor', $log );
		$this->assertStringNotContainsString( get_the_author_meta( 'display_name', $current_user_id ), $log );
	}

	/**
	 * @covers ::bbp_get_reply_revision_log
	 */
	public function test_bbp_get_reply_revision_log_links_logged_moderator_for_anonymous_revision() {
		$moderator_id = $this->factory->user->create( array( 'display_name' => 'Anonymous Reply Moderator' ) );
		bbp_set_user_role( $moderator_id, bbp_get_moderator_role() );
		$this->set_current_user( $moderator_id );
		$topic_id    = $this->factory->topic->create( array( 'post_author' => $moderator_id ) );
		$reply_id    = $this->factory->reply->create(
			array(
				'post_author' => 0,
				'post_parent' => $topic_id,
			)
		);
		$revision_id = $this->create_reply_revision( $reply_id );
		wp_update_post(
			array(
				'ID'          => $revision_id,
				'post_author' => 0,
			)
		);
		update_post_meta(
			$reply_id,
			'_bbp_revision_log',
			array(
				$revision_id => array( 'author' => $moderator_id, 'reason' => '' ),
			)
		);

		$log = bbp_get_reply_revision_log( $reply_id );
		$this->assertStringContainsString( bbp_get_user_profile_url( $moderator_id ), $log );
		$this->assertStringContainsString( 'Anonymous Reply Moderator', $log );
	}

	/**
	 * @covers ::bbp_get_reply_raw_revision_log
	 */
	public function test_bbp_get_reply_raw_revision_log() {
		$topic_id = $this->factory->topic->create();
		$reply_id = $this->factory->reply->create( array( 'post_parent' => $topic_id ) );
		$this->assertSame( array(), bbp_get_reply_raw_revision_log( $reply_id ) );

		$log = array( 123 => array( 'author' => 0, 'reason' => 'Updated.' ) );
		update_post_meta( $reply_id, '_bbp_revision_log', $log );
		$this->assertSame( $log, bbp_get_reply_raw_revision_log( $reply_id ) );
	}

	/**
	 * @covers ::bbp_get_reply_revisions
	 */
	public function test_bbp_get_reply_revisions() {
		$topic_id    = $this->factory->topic->create();
		$reply_id    = $this->factory->reply->create( array( 'post_parent' => $topic_id ) );
		$revision_id = $this->create_reply_revision( $reply_id );
		$revisions   = bbp_get_reply_revisions( $reply_id );

		$this->assertArrayHasKey( $revision_id, $revisions );
		$this->assertSame( $reply_id, $revisions[ $revision_id ]->post_parent );
	}

	/**
	 * @covers ::bbp_get_reply_revision_count
	 */
	public function test_bbp_get_reply_revision_count() {
		$topic_id = $this->factory->topic->create();
		$reply_id = $this->factory->reply->create( array( 'post_parent' => $topic_id ) );
		$this->assertSame( '0', bbp_get_reply_revision_count( $reply_id ) );

		$this->create_reply_revision( $reply_id );
		$this->assertSame( '1', bbp_get_reply_revision_count( $reply_id ) );
		$this->assertSame( 1, bbp_get_reply_revision_count( $reply_id, true ) );
	}

	private function create_reply_revision( $reply_id ) {
		wp_update_post( array( 'ID' => $reply_id, 'post_content' => 'Updated reply content.' ) );
		$revisions = wp_get_post_revisions( $reply_id );

		$this->assertNotEmpty( $revisions );
		// Keep relative revision dates away from a changing second boundary.
		$this->update_post_modified( key( $revisions ), gmdate( 'Y-m-d H:i:s', time() - DAY_IN_SECONDS - 30 * MINUTE_IN_SECONDS ) );
		clean_post_cache( key( $revisions ) );
		return key( $revisions );
	}
}
