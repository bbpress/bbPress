<?php

/**
 * Tests for the topic revision template functions.
 *
 * @group topics
 * @group template
 * @group revisions
 */
class BBP_Tests_Topics_Template_Revisions extends BBP_UnitTestCase {

	/**
	 * @covers ::bbp_topic_content_append_revisions
	 */
	public function test_bbp_topic_content_append_revisions() {
		$topic_id = $this->factory->topic->create();

		$this->assertSame( 'Topic content', bbp_topic_content_append_revisions( 'Topic content', $topic_id ) );
		$revision_id = $this->create_topic_revision( $topic_id );
		update_post_meta( $topic_id, '_bbp_revision_log', array(
			$revision_id => array( 'author' => 0, 'reason' => 'Updated.' ),
		) );
		$this->assertSame( 'Topic content' . bbp_get_topic_revision_log( $topic_id ), bbp_topic_content_append_revisions( 'Topic content', $topic_id ) );
	}

	/**
	 * @covers ::bbp_topic_revision_log
	 * @covers ::bbp_get_topic_revision_log
	 */
	public function test_bbp_get_topic_revision_log() {
		$topic_id = $this->factory->topic->create();
		$this->assertFalse( bbp_get_topic_revision_log( $topic_id ) );

		$revision_id = $this->create_topic_revision( $topic_id );
		update_post_meta( $topic_id, '_bbp_revision_log', array(
			$revision_id => array( 'author' => 0, 'reason' => 'Clarified the question.' ),
		) );
		$log = bbp_get_topic_revision_log( $topic_id );

		$this->assertStringContainsString( 'bbp-topic-revision-log-' . $topic_id, $log );
		$this->assertStringContainsString( 'item-' . $revision_id, $log );
		$this->assertStringContainsString( 'Clarified the question.', $log );
		$this->expectOutputString( $log );
		bbp_topic_revision_log( $topic_id );
	}

	/**
	 * @covers ::bbp_get_topic_revision_log
	 */
	public function test_bbp_get_topic_revision_log_respects_logged_author_id() {
		$current_user_id = $this->factory->user->create( array( 'display_name' => 'Current Topic Editor' ) );
		bbp_set_user_role( $current_user_id, bbp_get_keymaster_role() );
		$this->set_current_user( $current_user_id );
		$topic_id        = $this->factory->topic->create( array( 'post_author' => $current_user_id ) );
		$revision_id     = $this->create_topic_revision( $topic_id );
		$other_user_id   = $this->factory->user->create( array( 'display_name' => 'Other Topic Editor' ) );
		bbp_set_user_role( $other_user_id, bbp_get_moderator_role() );

		bbp_update_topic_revision_log(
			array(
				'topic_id'    => $topic_id,
				'revision_id' => $revision_id,
			)
		);

		$log = bbp_get_topic_revision_log( $topic_id );
		$this->assertStringContainsString( get_the_author_meta( 'display_name', $current_user_id ), $log );

		bbp_update_topic_revision_log(
			array(
				'topic_id'    => $topic_id,
				'revision_id' => $revision_id,
				'author_id'   => $other_user_id,
			)
		);

		$log = bbp_get_topic_revision_log( $topic_id );
		$this->assertStringContainsString( bbp_get_user_profile_url( $other_user_id ), $log );
		$this->assertStringContainsString( get_avatar( $other_user_id, 14 ), $log );
		$this->assertStringContainsString( 'Other Topic Editor', $log );
		$this->assertStringNotContainsString( get_the_author_meta( 'display_name', $current_user_id ), $log );
	}

	/**
	 * @covers ::bbp_get_topic_revision_log
	 */
	public function test_bbp_get_topic_revision_log_falls_back_to_revision_author() {
		$author_id = $this->factory->user->create( array( 'display_name' => 'Revision Author' ) );
		$this->set_current_user( $author_id );
		$topic_id    = $this->factory->topic->create( array( 'post_author' => $author_id ) );
		$revision_id = $this->create_topic_revision( $topic_id );

		update_post_meta(
			$topic_id,
			'_bbp_revision_log',
			array(
				0 => array( 'author' => 0, 'reason' => '' ),
			)
		);
		$log = bbp_get_topic_revision_log( $topic_id );
		$this->assertStringContainsString( bbp_get_user_profile_url( $author_id ), $log );

		update_post_meta(
			$topic_id,
			'_bbp_revision_log',
			array(
				$revision_id => array( 'author' => 0, 'reason' => '' ),
			)
		);
		$log = bbp_get_topic_revision_log( $topic_id );
		$this->assertStringContainsString( bbp_get_user_profile_url( $author_id ), $log );
		$this->assertStringContainsString( 'Revision Author', $log );

		update_post_meta(
			$topic_id,
			'_bbp_revision_log',
			array(
				$revision_id => array( 'author' => 'invalid', 'reason' => '' ),
			)
		);
		$log = bbp_get_topic_revision_log( $topic_id );
		$this->assertStringContainsString( bbp_get_user_profile_url( $author_id ), $log );
	}

	/**
	 * @covers ::bbp_get_topic_raw_revision_log
	 */
	public function test_bbp_get_topic_raw_revision_log() {
		$topic_id = $this->factory->topic->create();
		$this->assertSame( array(), bbp_get_topic_raw_revision_log( $topic_id ) );

		$log = array( 123 => array( 'author' => 0, 'reason' => 'Updated.' ) );
		update_post_meta( $topic_id, '_bbp_revision_log', $log );
		$this->assertSame( $log, bbp_get_topic_raw_revision_log( $topic_id ) );
	}

	/**
	 * @covers ::bbp_get_topic_revisions
	 */
	public function test_bbp_get_topic_revisions() {
		$topic_id    = $this->factory->topic->create();
		$revision_id = $this->create_topic_revision( $topic_id );
		$revisions   = bbp_get_topic_revisions( $topic_id );

		$this->assertArrayHasKey( $revision_id, $revisions );
		$this->assertSame( $topic_id, $revisions[ $revision_id ]->post_parent );
	}

	/**
	 * @covers ::bbp_get_topic_revision_count
	 */
	public function test_bbp_get_topic_revision_count() {
		$topic_id = $this->factory->topic->create();
		$this->assertSame( '0', bbp_get_topic_revision_count( $topic_id ) );

		$this->create_topic_revision( $topic_id );
		$this->assertSame( '1', bbp_get_topic_revision_count( $topic_id ) );
		$this->assertSame( 1, bbp_get_topic_revision_count( $topic_id, true ) );
	}

	private function create_topic_revision( $topic_id ) {
		wp_update_post( array( 'ID' => $topic_id, 'post_content' => 'Updated topic content.' ) );
		$revisions = wp_get_post_revisions( $topic_id );

		$this->assertNotEmpty( $revisions );
		return key( $revisions );
	}
}
