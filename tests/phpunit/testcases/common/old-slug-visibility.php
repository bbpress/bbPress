<?php

/**
 * Old topic slug redirects must respect bbPress visibility.
 *
 * @group common
 * @group visibility
 */
class BBP_Tests_Common_Old_Slug_Visibility extends BBP_UnitTestCase {

	/**
	 * @covers ::bbp_filter_old_slug_redirect_post_id
	 */
	public function test_old_slug_redirect_post_id_requires_topic_and_forum_access() {
		$author_id      = $this->factory->user->create();
		$participant_id = $this->factory->user->create();
		$moderator_id   = $this->factory->user->create();
		$forum_id       = $this->factory->forum->create();
		$topic_id       = $this->factory->topic->create( array(
			'post_author' => $author_id,
			'post_parent' => $forum_id,
			'post_status' => bbp_get_pending_status_id(),
			'topic_meta'  => array( 'forum_id' => $forum_id ),
		) );

		bbp_set_user_role( $author_id, bbp_get_participant_role() );
		bbp_set_user_role( $participant_id, bbp_get_participant_role() );
		bbp_set_user_role( $moderator_id, bbp_get_moderator_role() );

		$this->set_current_user( $participant_id );
		$this->assertSame( 0, apply_filters( 'old_slug_redirect_post_id', $topic_id ) );

		$this->set_current_user( $author_id );
		$this->assertSame( $topic_id, apply_filters( 'old_slug_redirect_post_id', $topic_id ) );

		wp_update_post( array( 'ID' => $topic_id, 'post_status' => bbp_get_spam_status_id() ) );
		$this->set_current_user( $participant_id );
		$this->assertSame( 0, apply_filters( 'old_slug_redirect_post_id', $topic_id ) );

		$this->set_current_user( $moderator_id );
		$this->assertSame( $topic_id, apply_filters( 'old_slug_redirect_post_id', $topic_id ) );

		wp_update_post( array( 'ID' => $topic_id, 'post_status' => bbp_get_public_status_id() ) );
		$this->set_current_user( 0 );
		$this->assertSame( $topic_id, apply_filters( 'old_slug_redirect_post_id', $topic_id ) );

		bbp_hide_forum( $forum_id );
		$this->set_current_user( $participant_id );
		$this->assertSame( 0, apply_filters( 'old_slug_redirect_post_id', $topic_id ) );
	}

	/**
	 * @covers ::bbp_filter_old_slug_redirect_post_id
	 * @covers ::wp_old_slug_redirect
	 */
	public function test_wp_old_slug_redirect_does_not_redirect_to_unreadable_topic() {
		$forum_id = $this->factory->forum->create();
		$topic_id = $this->factory->topic->create( array(
			'post_name'   => 'private-old-slug-target',
			'post_parent' => $forum_id,
			'post_status' => bbp_get_pending_status_id(),
			'topic_meta'  => array( 'forum_id' => $forum_id ),
		) );

		add_post_meta( $topic_id, '_wp_old_slug', 'private-old-topic-slug' );
		set_query_var( 'post_type', bbp_get_topic_post_type() );
		set_query_var( 'name', 'private-old-topic-slug' );
		$GLOBALS['wp_query']->is_404 = true;
		$this->set_current_user( 0 );

		$redirect_url = null;
		$filter       = function( $url ) use ( &$redirect_url ) {
			$redirect_url = $url;
			return false;
		};
		add_filter( 'old_slug_redirect_url', $filter );

		try {
			wp_old_slug_redirect();
		} finally {
			remove_filter( 'old_slug_redirect_url', $filter );
		}

		$this->assertNull( $redirect_url );
	}

	/**
	 * @covers ::bbp_filter_old_slug_redirect_post_id
	 */
	public function test_old_slug_redirect_post_id_does_not_change_other_post_types() {
		$post_id = $this->factory->post->create( array( 'post_status' => 'private' ) );

		$this->set_current_user( 0 );
		$this->assertSame( $post_id, apply_filters( 'old_slug_redirect_post_id', $post_id ) );
	}
}
