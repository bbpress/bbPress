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
		bbp_set_user_role( $user_id, bbp_get_participant_role() );
		bbp_add_moderator( $forum_id, $user_id );
		wp_set_current_user( $user_id );

		$this->assertFalse( current_user_can( 'moderate' ) );
		$this->assertTrue( current_user_can( 'moderate', $forum_id ) );
		$this->assertFalse( current_user_can( 'edit_topic_tag', $tag_id ) );
		$this->assertFalse( current_user_can( 'delete_topic_tag', $tag_id ) );
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
