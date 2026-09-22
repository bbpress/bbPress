<?php

/**
 * Tests for structural action forms.
 *
 * @group common
 * @group capabilities
 */
class BBP_Tests_Common_Structural_Form_Permissions extends BBP_UnitTestCase {

	/**
	 * @covers ::bbp_current_user_can_access_topic_moderation
	 * @covers ::bbp_get_topic_merge_link
	 * @covers ::bbp_get_topic_split_link
	 * @covers ::bbp_get_reply_move_link
	 * @covers ::bbp_merge_topic_form_fields
	 * @covers ::bbp_split_topic_form_fields
	 * @covers ::bbp_move_reply_form_fields
	 */
	public function test_structural_action_links_and_forms_require_topic_moderation() {
		$user_id  = $this->factory->user->create( array( 'role' => bbp_get_participant_role() ) );
		$forum_id = $this->factory->forum->create();
		$topic_id = $this->factory->topic->create( array( 'post_author' => $user_id, 'post_parent' => $forum_id, 'topic_meta' => array( 'forum_id' => $forum_id ) ) );
		$reply_id = $this->factory->reply->create( array( 'post_parent' => $topic_id, 'reply_meta' => array( 'forum_id' => $forum_id, 'topic_id' => $topic_id ) ) );
		$old_get  = $_GET;
		$old_topic_id = bbpress()->current_topic_id;
		$old_reply_id = bbpress()->current_reply_id;
		$old_topic_query = bbpress()->topic_query;
		$old_reply_query = bbpress()->reply_query;
		$wp_query = bbp_get_wp_query();
		$old_topic_edit = isset( $wp_query->bbp_is_topic_edit ) ? $wp_query->bbp_is_topic_edit : null;

		$this->set_current_user( $user_id );
		bbpress()->current_topic_id = $topic_id;
		bbpress()->current_reply_id = $reply_id;
		bbpress()->topic_query = (object) array( 'in_the_loop' => true, 'post' => get_post( $topic_id ) );
		bbpress()->reply_query = (object) array( 'in_the_loop' => true, 'post' => get_post( $reply_id ) );
		$wp_query->bbp_is_topic_edit = true;
		$_GET['reply_id'] = $reply_id;
		$this->assertSame( $topic_id, bbp_get_topic_id() );
		$this->assertTrue( current_user_can( 'edit_topic', $topic_id ) );
		$this->assertFalse( current_user_can( 'moderate', $topic_id ) );
		$this->assertFalse( bbp_current_user_can_access_topic_moderation( $topic_id ) );
		$this->assertEmpty( bbp_get_topic_merge_link( array( 'id' => $topic_id ) ) );
		$this->assertEmpty( bbp_get_topic_split_link( array( 'id' => $reply_id ) ) );
		$this->assertEmpty( bbp_get_reply_move_link( array( 'id' => $reply_id ) ) );

		try {
			foreach ( array( 'form-topic-merge.php', 'form-topic-split.php', 'form-reply-move.php' ) as $template ) {
				ob_start();
				include BBP_PLUGIN_DIR . 'templates/default/bbpress/' . $template;
				$html = ob_get_clean();
				$this->assertStringNotContainsString( 'name="_wpnonce"', $html, $template );
			}

			$moderator_id = $this->factory->user->create();
			bbp_set_user_role( $moderator_id, bbp_get_moderator_role() );
			$this->set_current_user( $moderator_id );
			$this->assertTrue( current_user_can( 'moderate', $topic_id ) );
			$this->assertTrue( bbp_current_user_can_access_topic_moderation( $topic_id ) );
			$this->assertNotEmpty( bbp_get_topic_merge_link( array( 'id' => $topic_id ) ) );
			$this->assertNotEmpty( bbp_get_topic_split_link( array( 'id' => $reply_id ) ) );
			$this->assertNotEmpty( bbp_get_reply_move_link( array( 'id' => $reply_id ) ) );
			foreach ( array( 'form-topic-merge.php', 'form-topic-split.php', 'form-reply-move.php' ) as $template ) {
				ob_start();
				include BBP_PLUGIN_DIR . 'templates/default/bbpress/' . $template;
				$html = ob_get_clean();
				$this->assertStringContainsString( 'name="_wpnonce"', $html, $template );
			}
		} finally {
			$_GET = $old_get;
			bbpress()->current_topic_id = $old_topic_id;
			bbpress()->current_reply_id = $old_reply_id;
			bbpress()->topic_query = $old_topic_query;
			bbpress()->reply_query = $old_reply_query;
			$wp_query->bbp_is_topic_edit = $old_topic_edit;
			$this->set_current_user( 0 );
		}
	}
}
