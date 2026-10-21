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
	 */
	public function test_old_slug_redirect_post_id_honors_custom_non_public_topic_status() {
		global $wp_post_statuses;

		$status          = 'archived';
		$original_status = isset( $wp_post_statuses[ $status ] )
			? $wp_post_statuses[ $status ]
			: null;

		$author_id      = $this->factory->user->create();
		$participant_id = $this->factory->user->create();
		$moderator_id   = $this->factory->user->create();
		$forum_id       = $this->factory->forum->create();

		register_post_status( $status, array(
			'public'    => false,
			'protected' => true,
		) );

		try {
			$topic_id = $this->factory->topic->create( array(
				'post_author' => $author_id,
				'post_parent' => $forum_id,
				'post_status' => $status,
				'topic_meta'  => array( 'forum_id' => $forum_id ),
			) );

			bbp_set_user_role( $author_id, bbp_get_participant_role() );
			bbp_set_user_role( $participant_id, bbp_get_participant_role() );
			bbp_set_user_role( $moderator_id, bbp_get_moderator_role() );

			$this->set_current_user( $participant_id );
			$this->assertSame( 0, apply_filters( 'old_slug_redirect_post_id', $topic_id ) );

			$this->set_current_user( $author_id );
			$this->assertSame( $topic_id, apply_filters( 'old_slug_redirect_post_id', $topic_id ) );

			$this->set_current_user( $moderator_id );
			$this->assertSame( $topic_id, apply_filters( 'old_slug_redirect_post_id', $topic_id ) );
		} finally {
			$this->set_current_user( 0 );

			if ( $original_status ) {
				$wp_post_statuses[ $status ] = $original_status;
			} else {
				unset( $wp_post_statuses[ $status ] );
			}
		}
	}

	/**
	 * @covers ::bbp_filter_old_slug_redirect_post_id
	 */
	public function test_old_slug_redirect_post_id_honors_filtered_forum_visibilities() {
		$forum_id = $this->factory->forum->create();
		$topic_id = $this->factory->topic->create( array(
			'post_parent' => $forum_id,
			'topic_meta'  => array( 'forum_id' => $forum_id ),
		) );
		$filter   = function( $visibilities, $visibility_forum_id ) use ( $forum_id ) {
			if ( $forum_id === $visibility_forum_id ) {
				unset( $visibilities[ bbp_get_public_status_id() ] );
			}

			return $visibilities;
		};

		add_filter( 'bbp_get_forum_visibilities', $filter, 10, 2 );

		try {
			$this->assertSame( 0, apply_filters( 'old_slug_redirect_post_id', $topic_id ) );
		} finally {
			remove_filter( 'bbp_get_forum_visibilities', $filter, 10 );
		}
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
			$this->assertNull( $redirect_url );

			wp_update_post( array( 'ID' => $topic_id, 'post_status' => bbp_get_public_status_id() ) );
			wp_old_slug_redirect();
			$this->assertSame( get_permalink( $topic_id ), $redirect_url );
		} finally {
			remove_filter( 'old_slug_redirect_url', $filter );
		}
	}

	/**
	 * @covers ::bbp_filter_old_slug_redirect_post_id
	 * @covers ::wp_old_slug_redirect
	 */
	public function test_wp_old_slug_redirect_does_not_redirect_to_unreadable_reply() {
		$forum_id = $this->factory->forum->create();
		$topic_id = $this->factory->topic->create( array(
			'post_parent' => $forum_id,
			'topic_meta'  => array( 'forum_id' => $forum_id ),
		) );
		$reply_id = $this->factory->reply->create( array(
			'post_name'   => 'restricted-reply-current-title',
			'post_parent' => $topic_id,
			'reply_meta'  => array( 'forum_id' => $forum_id, 'topic_id' => $topic_id ),
		) );

		add_post_meta( $reply_id, '_wp_old_slug', 'restricted-reply-old-title' );
		bbp_privatize_forum( $forum_id );
		set_query_var( 'post_type', bbp_get_reply_post_type() );
		set_query_var( 'name', 'restricted-reply-old-title' );
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
			$this->assertNull( $redirect_url );

			bbp_publicize_forum( $forum_id );
			wp_old_slug_redirect();
			$this->assertSame( get_permalink( $reply_id ), $redirect_url );
		} finally {
			remove_filter( 'old_slug_redirect_url', $filter );
		}
	}

	/**
	 * @covers ::bbp_filter_old_slug_redirect_post_id
	 */
	public function test_old_slug_redirect_post_id_requires_reply_topic_and_forum_access() {
		$author_id      = $this->factory->user->create();
		$participant_id = $this->factory->user->create();
		$moderator_id   = $this->factory->user->create();
		$keymaster_id   = $this->factory->user->create();
		$forum_id       = $this->factory->forum->create();
		$topic_id       = $this->factory->topic->create( array(
			'post_parent' => $forum_id,
			'topic_meta'  => array( 'forum_id' => $forum_id ),
		) );
		$reply_id       = $this->factory->reply->create( array(
			'post_author' => $author_id,
			'post_parent' => $topic_id,
			'reply_meta'  => array( 'forum_id' => $forum_id, 'topic_id' => $topic_id ),
		) );

		bbp_set_user_role( $author_id, bbp_get_participant_role() );
		bbp_set_user_role( $participant_id, bbp_get_participant_role() );
		bbp_set_user_role( $moderator_id, bbp_get_moderator_role() );
		bbp_set_user_role( $keymaster_id, bbp_get_keymaster_role() );

		$this->set_current_user( 0 );
		$this->assertSame( $reply_id, apply_filters( 'old_slug_redirect_post_id', $reply_id ) );

		bbp_privatize_forum( $forum_id );
		$this->assertSame( 0, apply_filters( 'old_slug_redirect_post_id', $reply_id ) );
		$this->set_current_user( $participant_id );
		$this->assertSame( $reply_id, apply_filters( 'old_slug_redirect_post_id', $reply_id ) );

		bbp_hide_forum( $forum_id, bbp_get_private_status_id() );
		$this->assertSame( 0, apply_filters( 'old_slug_redirect_post_id', $reply_id ) );
		$this->set_current_user( 0 );
		$this->assertSame( 0, apply_filters( 'old_slug_redirect_post_id', $reply_id ) );
		$this->set_current_user( $moderator_id );
		$this->assertSame( $reply_id, apply_filters( 'old_slug_redirect_post_id', $reply_id ) );
		$this->set_current_user( $keymaster_id );
		$this->assertSame( $reply_id, apply_filters( 'old_slug_redirect_post_id', $reply_id ) );

		bbp_publicize_forum( $forum_id, bbp_get_hidden_status_id() );
		wp_update_post( array( 'ID' => $topic_id, 'post_status' => bbp_get_pending_status_id() ) );
		$this->set_current_user( 0 );
		$this->assertSame( 0, apply_filters( 'old_slug_redirect_post_id', $reply_id ) );
		$this->set_current_user( $moderator_id );
		$this->assertSame( $reply_id, apply_filters( 'old_slug_redirect_post_id', $reply_id ) );

		wp_update_post( array( 'ID' => $topic_id, 'post_status' => bbp_get_public_status_id() ) );
		foreach ( array( bbp_get_pending_status_id(), bbp_get_spam_status_id(), 'trash' ) as $reply_status ) {
			wp_update_post( array( 'ID' => $reply_id, 'post_status' => $reply_status ) );
			$this->set_current_user( 0 );
			$this->assertSame( 0, apply_filters( 'old_slug_redirect_post_id', $reply_id ), $reply_status );
			$this->set_current_user( $participant_id );
			$this->assertSame( 0, apply_filters( 'old_slug_redirect_post_id', $reply_id ), $reply_status );
			$this->set_current_user( $moderator_id );
			$this->assertSame( $reply_id, apply_filters( 'old_slug_redirect_post_id', $reply_id ), $reply_status );
		}

		wp_update_post( array( 'ID' => $reply_id, 'post_status' => bbp_get_pending_status_id() ) );
		$this->set_current_user( $author_id );
		$this->assertSame( $reply_id, apply_filters( 'old_slug_redirect_post_id', $reply_id ) );

		wp_update_post( array( 'ID' => $reply_id, 'post_parent' => 0 ) );
		delete_post_meta( $reply_id, '_bbp_topic_id' );
		$this->assertSame( 0, apply_filters( 'old_slug_redirect_post_id', $reply_id ) );

		wp_update_post( array( 'ID' => $reply_id, 'post_parent' => $topic_id ) );
		wp_update_post( array( 'ID' => $topic_id, 'post_parent' => 0 ) );
		delete_post_meta( $topic_id, '_bbp_forum_id' );
		$this->assertSame( 0, apply_filters( 'old_slug_redirect_post_id', $reply_id ) );
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
