<?php

/**
 * Tests that public WordPress reply queries respect parent topic visibility.
 *
 * @group common
 * @group visibility
 */
class BBP_Tests_Common_Reply_Query_Visibility extends BBP_UnitTestCase {

	public function tearDown(): void {
		$this->set_current_user( 0 );
		$this->go_to_without_reregistering_blocks( '/' );
		unset( $_GET['view'] );

		parent::tearDown();
	}

	/**
	 * @covers ::bbp_public_topic_replies_where
	 */
	public function test_public_reply_listing_excludes_reply_under_pending_topic_for_public_viewers() {
		$fixture  = $this->create_reply_visibility_fixture();
		$user_ids = array(
			0,
			$this->create_user_without_role(),
			$this->create_user_with_bbp_role( bbp_get_participant_role() ),
			$this->create_user_with_bbp_role( bbp_get_moderator_role() ),
			$this->create_user_with_bbp_role( bbp_get_keymaster_role() ),
		);

		foreach ( $user_ids as $user_id ) {
			$this->set_current_user( $user_id );
			$post_ids = $this->go_to_reply_listing();

			$this->assertContains( $fixture['public_reply'], $post_ids );
			$this->assertNotContains( $fixture['pending_reply'], $post_ids );
		}
	}

	/**
	 * @covers ::bbp_public_topic_replies_where
	 */
	public function test_public_reply_listing_preserves_mixed_non_bbp_posts() {
		$fixture = $this->create_reply_visibility_fixture();
		$post_id = $this->factory->post->create();

		$this->go_to_without_reregistering_blocks(
			add_query_arg(
				array(
					'post_type' => array( 'post', bbp_get_reply_post_type() ),
				),
				'/'
			)
		);

		$post_ids = wp_list_pluck( $GLOBALS['wp_query']->posts, 'ID' );
		$this->assertContains( $post_id, $post_ids );
		$this->assertContains( $fixture['public_reply'], $post_ids );
		$this->assertNotContains( $fixture['pending_reply'], $post_ids );
	}

	/**
	 * @covers ::bbp_public_topic_replies_where
	 */
	public function test_moderator_view_all_includes_reply_under_pending_topic() {
		$fixture  = $this->create_reply_visibility_fixture();
		$user_ids = array(
			$this->create_user_with_bbp_role( bbp_get_moderator_role() ),
			$this->create_user_with_bbp_role( bbp_get_keymaster_role() ),
		);

		foreach ( $user_ids as $user_id ) {
			$this->set_current_user( $user_id );
			$post_ids = $this->go_to_reply_listing( true );

			$this->assertContains( $fixture['pending_reply'], $post_ids );
		}
	}

	/**
	 * @covers ::bbp_reply_enforce_visibility
	 */
	public function test_single_reply_uses_parent_topic_read_capability() {
		$this->assertSame( 1, has_action( 'bbp_template_redirect', 'bbp_reply_enforce_visibility' ) );

		$topic_author_id = $this->create_user_with_bbp_role( bbp_get_participant_role() );
		$fixture        = $this->create_reply_visibility_fixture( $topic_author_id );
		$user_ids       = array(
			$topic_author_id,
			$this->create_user_with_bbp_role( bbp_get_moderator_role() ),
			$this->create_user_with_bbp_role( bbp_get_keymaster_role() ),
		);

		foreach ( $user_ids as $user_id ) {
			$this->set_current_user( $user_id );
			$this->go_to_reply( $fixture['pending_reply'] );
			bbp_reply_enforce_visibility();

			$this->assertFalse( $GLOBALS['wp_query']->is_404() );
		}

		$this->set_current_user( 0 );
		$this->go_to_reply( $fixture['pending_reply'] );
		bbp_reply_enforce_visibility();

		$this->assertTrue( $GLOBALS['wp_query']->is_404() );
	}

	/**
	 * @covers ::bbp_public_topic_replies_where
	 * @covers ::_bbp_has_replies_where
	 */
	public function test_flagged_topic_reply_query_does_not_rewrite_subquery_index_hint() {
		$topic_id = $this->factory->topic->create();
		$reply_id = $this->factory->reply->create(
			array(
				'post_parent' => $topic_id,
				'reply_meta'  => array( 'topic_id' => $topic_id ),
			)
		);

		global $wpdb;

		$wpdb->last_error = '';
		$query            = new WP_Query(
			array(
				'post_type'                 => array( bbp_get_topic_post_type(), bbp_get_reply_post_type() ),
				'post_parent'               => $topic_id,
				'posts_per_page'            => -1,
				'orderby'                   => 'ID',
				'order'                     => 'ASC',
				'_bbp_public_topic_replies' => true,
			)
		);

		$this->assertSame( '', $wpdb->last_error );
		$this->assertSame( array( $topic_id, $reply_id ), wp_list_pluck( $query->posts, 'ID' ) );
	}

	/**
	 * @covers ::bbp_public_topic_replies_where
	 */
	public function test_parent_topic_visibility_does_not_depend_on_forum_visibility_filter() {
		$fixture = $this->create_reply_visibility_fixture();

		remove_filter( 'posts_where', '_bbp_forum_visibility_where', 10 );
		try {
			$post_ids = $this->go_to_reply_listing();
		} finally {
			add_filter( 'posts_where', '_bbp_forum_visibility_where', 10, 2 );
		}

		$this->assertContains( $fixture['public_reply'], $post_ids );
		$this->assertNotContains( $fixture['pending_reply'], $post_ids );
	}

	private function create_reply_visibility_fixture( $pending_topic_author_id = 0 ) {
		$forum_id        = $this->factory->forum->create();
		$public_topic_id = $this->factory->topic->create(
			array(
				'post_parent' => $forum_id,
				'topic_meta'  => array( 'forum_id' => $forum_id ),
			)
		);
		$pending_topic_id = $this->factory->topic->create(
			array(
				'post_author' => $pending_topic_author_id,
				'post_parent' => $forum_id,
				'post_status' => bbp_get_pending_status_id(),
				'topic_meta'  => array( 'forum_id' => $forum_id ),
			)
		);

		return array(
			'public_topic'  => $public_topic_id,
			'pending_topic' => $pending_topic_id,
			'public_reply'  => $this->factory->reply->create(
				array(
					'post_parent' => $public_topic_id,
					'reply_meta'  => array( 'forum_id' => $forum_id, 'topic_id' => $public_topic_id ),
				)
			),
			'pending_reply' => $this->factory->reply->create(
				array(
					'post_parent' => $pending_topic_id,
					'post_status' => bbp_get_public_status_id(),
					'reply_meta'  => array( 'forum_id' => $forum_id, 'topic_id' => $pending_topic_id ),
				)
			),
		);
	}

	private function create_user_without_role() {
		$user_id = $this->factory->user->create();
		$user    = new WP_User( $user_id );
		$user->set_role( '' );

		return $user_id;
	}

	private function create_user_with_bbp_role( $role ) {
		$user_id = $this->factory->user->create();
		bbp_set_user_role( $user_id, $role );

		return $user_id;
	}

	private function go_to_reply_listing( $view_all = false ) {
		$url = '/?post_type=' . bbp_get_reply_post_type();
		if ( $view_all ) {
			$url .= '&view=all';
		}

		$this->go_to_without_reregistering_blocks( $url );

		return wp_list_pluck( $GLOBALS['wp_query']->posts, 'ID' );
	}

	private function go_to_reply( $reply_id ) {
		$this->go_to_without_reregistering_blocks(
			add_query_arg(
				array(
					'p'         => $reply_id,
					'post_type' => bbp_get_reply_post_type(),
				),
				'/'
			)
		);
	}

	private function go_to_without_reregistering_blocks( $url ) {
		remove_action( 'bbp_register', 'bbp_register_blocks', 10 );
		try {
			$this->go_to( home_url( $url ) );
		} finally {
			add_action( 'bbp_register', 'bbp_register_blocks', 10 );
		}
	}
}
