<?php

/**
 * Tests for topic-tag capability mapping.
 *
 * @group topics
 * @group functions
 * @group capabilities
 */
class BBP_Tests_Topics_Functions_Tag_Capabilities extends BBP_UnitTestCase {

	/**
	 * @ticket 2970
	 * @covers ::bbp_map_topic_tag_meta_caps
	 */
	public function test_only_topic_author_or_moderator_can_assign_existing_topic_tags() {
		$author_id      = $this->factory->user->create();
		$participant_id = $this->factory->user->create();
		$moderator_id   = $this->factory->user->create();
		$forum_id       = $this->factory->forum->create();
		$topic_id       = $this->factory->topic->create( array(
			'post_author' => $author_id,
			'post_parent' => $forum_id,
		) );
		$tag_ids        = wp_set_post_terms( $topic_id, array( 'alpha' ), bbp_get_topic_tag_tax_id() );

		bbp_set_user_role( $author_id, bbp_get_participant_role() );
		bbp_set_user_role( $participant_id, bbp_get_participant_role() );
		bbp_set_user_role( $moderator_id, bbp_get_participant_role() );

		wp_set_current_user( $participant_id );
		$this->assertTrue( current_user_can( 'assign_topic_tags' ) );
		$this->assertTrue( current_user_can( 'assign_topic_tags', $topic_id, 'topic' ) );
		$this->assertTrue( current_user_can( 'remove_topic_tag', $topic_id, $tag_ids[0] ) );

		update_option( '_bbp_topic_tag_edit_scope', 'authors_only' );
		$this->assertTrue( current_user_can( 'assign_topic_tags', $topic_id ) );
		$this->assertFalse( current_user_can( 'assign_topic_tags', $topic_id, 'topic' ) );
		$this->assertFalse( current_user_can( 'remove_topic_tag', $topic_id, $tag_ids[0] ) );
		$this->assertFalse( current_user_can( 'assign_topic_tags', 999999, 'topic' ) );

		wp_set_current_user( $author_id );
		$this->assertTrue( current_user_can( 'assign_topic_tags', $topic_id, 'topic' ) );
		$this->assertTrue( current_user_can( 'remove_topic_tag', $topic_id, $tag_ids[0] ) );

		bbp_add_moderator( $forum_id, $moderator_id );
		wp_set_current_user( $moderator_id );
		$this->assertTrue( current_user_can( 'assign_topic_tags', $topic_id, 'topic' ) );
		$this->assertTrue( current_user_can( 'remove_topic_tag', $topic_id, $tag_ids[0] ) );
	}

	/**
	 * @covers ::bbp_map_topic_tag_meta_caps
	 */
	public function test_forum_moderator_cannot_edit_or_delete_global_tag_with_colliding_id() {
		global $wpdb;

		$max_post_id = (int) $wpdb->get_var( "SELECT MAX(ID) FROM {$wpdb->posts}" );
		for ( $number = 0; $number < 50; $number++ ) {
			$term = wp_insert_term( "collision-tag-{$number}", bbp_get_topic_tag_tax_id() );
			$this->assertNotWPError( $term );
			if ( $term['term_id'] > $max_post_id ) {
				break;
			}
		}

		$tag_id   = $term['term_id'];
		$this->assertGreaterThan( $max_post_id, $tag_id );
		$forum_id = $this->factory->forum->create( array( 'import_id' => $tag_id ) );
		$this->assertSame( $tag_id, $forum_id );

		$user_id = $this->factory->user->create();
		bbp_set_user_role( $user_id, bbp_get_spectator_role() );
		bbp_add_moderator( $forum_id, $user_id );
		wp_set_current_user( $user_id );

		$this->assertFalse( current_user_can( 'moderate' ) );
		$this->assertTrue( current_user_can( 'moderate', $forum_id ) );
		$this->assertFalse( current_user_can( 'assign_topic_tags', $tag_id ) );
		$this->assertFalse( current_user_can( 'assign_term', $tag_id ) );
		$this->assertFalse( current_user_can( 'assign_topic_tags', $tag_id, 'topic' ) );
		$this->assertFalse( current_user_can( 'edit_topic_tag', $tag_id ) );
		$this->assertFalse( current_user_can( 'delete_topic_tag', $tag_id ) );
	}

	/**
	 * @ticket 2970
	 * @covers ::bbp_map_topic_tag_meta_caps
	 */
	public function test_global_moderator_without_spectate_can_change_topic_tags() {
		$forum_id = $this->factory->forum->create();
		$topic_id = $this->factory->topic->create( array( 'post_parent' => $forum_id ) );
		$tag_ids  = wp_set_post_terms( $topic_id, array( 'alpha' ), bbp_get_topic_tag_tax_id() );
		$user_id  = $this->factory->user->create();

		bbp_set_user_role( $user_id, bbp_get_spectator_role() );
		$user = get_userdata( $user_id );
		$user->add_cap( 'moderate' );
		$user->add_cap( 'spectate', false );
		wp_set_current_user( $user_id );
		update_option( '_bbp_topic_tag_edit_scope', 'authors_only' );

		$this->assertTrue( current_user_can( 'moderate' ) );
		$this->assertFalse( current_user_can( 'spectate' ) );
		$this->assertTrue( current_user_can( 'assign_topic_tags', $topic_id, 'topic' ) );
		$this->assertTrue( current_user_can( 'remove_topic_tag', $topic_id, $tag_ids[0] ) );
	}

	/**
	 * @covers ::bbp_map_topic_tag_meta_caps
	 */
	public function test_global_staff_and_explicit_tag_caps_can_manage_terms() {
		$term = wp_insert_term( 'global-tag', bbp_get_topic_tag_tax_id() );
		$this->assertNotWPError( $term );
		$tag_id = $term['term_id'];

		$moderator_id = $this->factory->user->create();
		bbp_set_user_role( $moderator_id, bbp_get_moderator_role() );
		wp_set_current_user( $moderator_id );
		$this->assertTrue( current_user_can( 'edit_topic_tag', $tag_id ) );
		$this->assertTrue( current_user_can( 'delete_topic_tag', $tag_id ) );

		$participant_id = $this->factory->user->create();
		bbp_set_user_role( $participant_id, bbp_get_participant_role() );
		$user = get_userdata( $participant_id );
		$user->add_cap( 'edit_topic_tags' );
		$user->add_cap( 'delete_topic_tags' );
		wp_set_current_user( $participant_id );
		$this->assertTrue( current_user_can( 'edit_topic_tag', $tag_id ) );
		$this->assertTrue( current_user_can( 'delete_topic_tag', $tag_id ) );
		$this->assertFalse( current_user_can( 'edit_topic_tag', 0 ) );
		$this->assertFalse( current_user_can( 'delete_topic_tag', 0 ) );
		$this->assertFalse( current_user_can( 'delete_topic_tag', 999999 ) );
	}
}
