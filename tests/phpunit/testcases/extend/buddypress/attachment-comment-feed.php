<?php

/**
 * Attachment comment feeds for BuddyPress group forums.
 *
 * @group extend
 * @group buddypress
 * @group groups
 */
class BBP_Tests_Extend_BuddyPress_Attachment_Comment_Feed extends BBP_UnitTestCase {

	public $bp_factory;

	public function test_group_membership_controls_attachment_comment_feeds() {
		$creator_id  = self::factory()->user->create();
		$member_id   = self::factory()->user->create();
		$outsider_id = self::factory()->user->create();
		bbp_set_user_role( $creator_id, bbp_get_participant_role() );
		bbp_set_user_role( $member_id, bbp_get_participant_role() );
		bbp_set_user_role( $outsider_id, bbp_get_participant_role() );

		foreach ( array( 'private', 'hidden' ) as $status ) {
			$group_id = $this->bp_factory->group->create( array( 'creator_id' => $creator_id, 'status' => $status ) );
			$forum_id = $this->factory->forum->create();
			bbp_add_forum_id_to_group( $group_id, $forum_id );
			bbp_add_group_id_to_forum( $forum_id, $group_id );
			bbp_privatize_forum( $forum_id );
			groups_join_group( $group_id, $member_id );
			$topic_id = $this->factory->topic->create( array( 'post_parent' => $forum_id ) );
			$media_id = wp_insert_attachment( array( 'post_parent' => $topic_id, 'post_status' => 'inherit', 'post_title' => 'Group attachment' ) );
			$comment_id = self::factory()->comment->create( array( 'comment_post_ID' => $media_id, 'comment_approved' => 1 ) );

			foreach ( array( $creator_id, $member_id, $outsider_id, 0 ) as $user_id ) {
				$this->set_current_user( $user_id );
				$allowed  = in_array( $user_id, array( $creator_id, $member_id ), true );
				$query    = new WP_Query( array( 'feed' => 'comments-rss2' ) );
				$comments = array_map( 'intval', wp_list_pluck( $query->comments, 'comment_ID' ) );
				$posts    = array_map( 'intval', wp_list_pluck( $query->posts, 'ID' ) );
				$this->assertSame( $allowed, bbp_user_can_view_attachment( $media_id ) );
				$this->assertSame( $allowed, in_array( $comment_id, $comments, true ) );
				$this->assertSame( $allowed, in_array( $media_id, $posts, true ) );
			}

			$this->set_current_user( $member_id );
			groups_leave_group( $group_id, $member_id );
			$query    = new WP_Query( array( 'feed' => 'comments-atom' ) );
			$comments = array_map( 'intval', wp_list_pluck( $query->comments, 'comment_ID' ) );
			$posts    = array_map( 'intval', wp_list_pluck( $query->posts, 'ID' ) );
			$this->assertNotContains( $comment_id, $comments );
			$this->assertNotContains( $media_id, $posts );
		}
	}
}
