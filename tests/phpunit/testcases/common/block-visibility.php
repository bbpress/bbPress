<?php

/**
 * Tests for data sent to bbPress blocks in the editor.
 *
 * @group common
 * @group blocks
 * @group visibility
 */
class BBP_Tests_Common_Block_Visibility extends BBP_UnitTestCase {

	/**
	 * @covers ::BBP_Blocks::get_localize_script_data
	 */
	public function test_forum_options_respect_editor_visibility() {
		$public_id  = $this->factory->forum->create( array( 'post_title' => 'Public block forum' ) );
		$private_id = $this->factory->forum->create( array( 'post_title' => 'Private block forum', 'post_status' => bbp_get_private_status_id() ) );
		$hidden_id  = $this->factory->forum->create( array( 'post_title' => 'Hidden block forum', 'post_status' => bbp_get_hidden_status_id() ) );
		$child_id   = $this->factory->forum->create( array( 'post_title' => 'Public child block forum', 'post_parent' => $hidden_id ) );
		$user_id    = $this->factory->user->create( array( 'role' => 'author' ) );

		bbp_set_user_role( $user_id, bbp_get_spectator_role() );
		$this->set_current_user( $user_id );
		$this->assertTrue( current_user_can( 'edit_posts' ) );
		$this->assertFalse( current_user_can( 'read_private_forums' ) );
		$this->assertFalse( current_user_can( 'read_hidden_forums' ) );

		$options = BBP_Blocks::get_localize_script_data( 'forums' );
		$ids     = wp_list_pluck( $options, 'value' );

		$this->assertContains( $public_id, $ids );
		$this->assertNotContains( $private_id, $ids );
		$this->assertNotContains( $hidden_id, $ids );
		$this->assertNotContains( $child_id, $ids );
	}

	/**
	 * @covers ::BBP_Blocks::get_localize_script_data
	 */
	public function test_topic_tag_options_exclude_restricted_only_tags() {
		$public_forum    = $this->factory->forum->create();
		$private_forum   = $this->factory->forum->create( array( 'post_status' => bbp_get_private_status_id() ) );
		$hidden_forum    = $this->factory->forum->create( array( 'post_status' => bbp_get_hidden_status_id() ) );
		$public_topic    = $this->factory->topic->create( array( 'post_parent' => $public_forum, 'topic_meta' => array( 'forum_id' => $public_forum ) ) );
		$private_topic   = $this->factory->topic->create( array( 'post_parent' => $private_forum, 'topic_meta' => array( 'forum_id' => $private_forum ) ) );
		$hidden_topic    = $this->factory->topic->create( array( 'post_parent' => $hidden_forum, 'topic_meta' => array( 'forum_id' => $hidden_forum ) ) );
		$pending_topic   = $this->factory->topic->create( array( 'post_parent' => $public_forum, 'post_status' => bbp_get_pending_status_id(), 'topic_meta' => array( 'forum_id' => $public_forum ) ) );
		$user_id         = $this->factory->user->create( array( 'role' => 'author' ) );

		wp_set_object_terms( $public_topic, array( 'public-block-tag', 'shared-block-tag' ), bbp_get_topic_tag_tax_id() );
		wp_set_object_terms( $private_topic, array( 'private-block-tag', 'shared-block-tag' ), bbp_get_topic_tag_tax_id() );
		wp_set_object_terms( $hidden_topic, 'hidden-block-tag', bbp_get_topic_tag_tax_id() );
		wp_set_object_terms( $pending_topic, 'pending-block-tag', bbp_get_topic_tag_tax_id() );
		bbp_set_user_role( $user_id, bbp_get_spectator_role() );
		$this->set_current_user( $user_id );

		$labels = wp_list_pluck( BBP_Blocks::get_localize_script_data( 'topic_tags' ), 'label' );
		$this->assertContains( 'public-block-tag', $labels );
		$this->assertContains( 'shared-block-tag', $labels );
		$this->assertNotContains( 'private-block-tag', $labels );
		$this->assertNotContains( 'hidden-block-tag', $labels );
		$this->assertNotContains( 'pending-block-tag', $labels );
		$this->assertContains( 'private-block-tag', wp_list_pluck( get_terms( array( 'taxonomy' => bbp_get_topic_tag_tax_id(), 'hide_empty' => false ) ), 'name' ) );

		bbp_set_user_role( $user_id, bbp_get_participant_role() );
		$labels = wp_list_pluck( BBP_Blocks::get_localize_script_data( 'topic_tags' ), 'label' );
		$this->assertContains( 'private-block-tag', $labels );
		$this->assertNotContains( 'hidden-block-tag', $labels );

		bbp_add_moderator( $hidden_forum, $user_id );
		$labels = wp_list_pluck( BBP_Blocks::get_localize_script_data( 'topic_tags' ), 'label' );
		$this->assertContains( 'hidden-block-tag', $labels );

		wp_update_post( array( 'ID' => $public_topic, 'post_status' => bbp_get_pending_status_id() ) );
		$labels = wp_list_pluck( BBP_Blocks::get_localize_script_data( 'topic_tags' ), 'label' );
		$this->assertNotContains( 'public-block-tag', $labels );

		wp_update_post( array( 'ID' => $public_topic, 'post_status' => bbp_get_public_status_id() ) );
		$labels = wp_list_pluck( BBP_Blocks::get_localize_script_data( 'topic_tags' ), 'label' );
		$this->assertContains( 'public-block-tag', $labels );

		bbp_remove_moderator( $hidden_forum, $user_id );
		bbp_set_user_role( $user_id, bbp_get_spectator_role() );
		$labels = wp_list_pluck( BBP_Blocks::get_localize_script_data( 'topic_tags' ), 'label' );
		$this->assertContains( 'public-block-tag', $labels );

		update_post_meta( $public_topic, '_bbp_forum_id', $private_forum );
		$labels = wp_list_pluck( BBP_Blocks::get_localize_script_data( 'topic_tags' ), 'label' );
		$this->assertNotContains( 'public-block-tag', $labels );
		update_post_meta( $public_topic, '_bbp_forum_id', $public_forum );
		$labels = wp_list_pluck( BBP_Blocks::get_localize_script_data( 'topic_tags' ), 'label' );
		$this->assertContains( 'public-block-tag', $labels );

		wp_update_post( array( 'ID' => $public_topic, 'post_parent' => $private_forum ) );
		update_post_meta( $public_topic, '_bbp_forum_id', $private_forum );
		$labels = wp_list_pluck( BBP_Blocks::get_localize_script_data( 'topic_tags' ), 'label' );
		$this->assertNotContains( 'public-block-tag', $labels );
	}
}
