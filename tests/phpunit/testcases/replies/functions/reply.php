<?php

/**
 * Tests for the reply component functions.
 *
 * @group replies
 * @group functions
 * @group reply
 */
class BBP_Tests_Replies_Functions_Reply extends BBP_UnitTestCase {

	/**
	 * @covers ::bbp_edit_reply_handler
	 */
	public function test_bbp_edit_reply_handler_preserves_the_original_revision() {
		$forum_id        = $this->factory->forum->create();
		$author_id       = $this->factory->user->create();
		$admin_id        = $this->factory->user->create( array( 'role' => 'administrator' ) );
		$topic_id        = $this->factory->topic->create(
			array(
				'post_parent' => $forum_id,
				'topic_meta'  => array( 'forum_id' => $forum_id ),
			)
		);
		$reply_id        = $this->factory->reply->create(
			array(
				'post_author' => $author_id,
				'post_date'   => '2020-01-02 03:04:05',
				'post_parent' => $topic_id,
				'reply_meta'  => array(
					'forum_id' => $forum_id,
					'topic_id' => $topic_id,
				),
			)
		);
		$original_content  = get_post_field( 'post_content', $reply_id );
		$original_modified = get_post_field( 'post_modified', $reply_id );
		$old_post         = $_POST;
		$old_request      = $_REQUEST;
		$old_server       = $_SERVER;
		$old_user         = get_current_user_id();
		$old_errors       = bbpress()->errors;
		$prevent_redirect = function () {
			throw new RuntimeException( 'Edit reply redirect.' );
		};
		$home_url = wp_parse_url( home_url( '/' ) );

		$_POST                   = array();
		$_REQUEST                = array();
		$_SERVER['HTTP_HOST']    = $home_url['host'] . ( isset( $home_url['port'] ) ? ':' . $home_url['port'] : '' );
		$_SERVER['REQUEST_URI']  = $home_url['path'];
		bbpress()->errors       = new WP_Error();
		add_filter( 'wp_redirect', $prevent_redirect );

		try {
			bbp_set_user_role( $admin_id, bbp_get_keymaster_role() );
			$this->set_current_user( $admin_id );
			$_POST = array(
				'bbp_reply_id'          => $reply_id,
				'bbp_reply_content'     => 'Edited reply content',
				'bbp_log_reply_edit'    => '1',
				'bbp_reply_edit_reason' => 'Clarify the answer.',
			);
			$_REQUEST['_wpnonce'] = wp_create_nonce( 'bbp-edit-reply_' . $reply_id );
			$this->assertSame( array(), wp_get_post_revisions( $reply_id ) );

			$autosave_id = wp_create_post_autosave(
				array(
					'post_ID'   => $reply_id,
					'post_type' => bbp_get_reply_post_type(),
					'content'   => 'Autosaved reply content',
				)
			);
			$this->assertIsInt( $autosave_id );
			$this->assertSame( $reply_id, wp_is_post_autosave( $autosave_id ) );

			try {
				bbp_edit_reply_handler( 'bbp-edit-reply' );
				$this->fail( 'Editing a reply should redirect.' );
			} catch ( RuntimeException $exception ) {
				if ( 'Edit reply redirect.' !== $exception->getMessage() ) {
					throw $exception;
				}
			}

			$this->assertSame( array(), bbpress()->errors->get_error_codes() );
			$this->assertSame( 'Edited reply content', get_post_field( 'post_content', $reply_id ) );

			$revisions = array_values(
				wp_get_post_revisions(
					$reply_id,
					array(
						'name'  => "{$reply_id}-revision-v1",
						'order' => 'ASC',
					)
				)
			);
			$this->assertCount( 2, $revisions );
			$this->assertSame( $original_content, $revisions[0]->post_content );
			$this->assertSame( $original_modified, $revisions[0]->post_modified );
			$this->assertSame( $author_id, (int) $revisions[0]->post_author );
			$this->assertSame( 'Edited reply content', $revisions[1]->post_content );
			$this->assertSame( $admin_id, (int) $revisions[1]->post_author );
			$this->assertSame(
				array(
					$revisions[1]->ID => array(
						'author' => $admin_id,
						'reason' => 'Clarify the answer',
					),
				),
				bbp_get_reply_raw_revision_log( $reply_id )
			);
		} finally {
			remove_filter( 'wp_redirect', $prevent_redirect );
			$_POST            = $old_post;
			$_REQUEST         = $old_request;
			$_SERVER          = $old_server;
			bbpress()->errors = $old_errors;
			$this->set_current_user( $old_user );
		}
	}

	/**
	 * @group canonical
	 * @covers ::bbp_insert_reply
	 */
	public function test_bbp_insert_reply() {

		$f = $this->factory->forum->create();

		$t = $this->factory->topic->create( array(
			'post_parent' => $f,
			'topic_meta' => array(
				'forum_id' => $f,
			),
		) );

		$r = $this->factory->reply->create( array(
			'post_title' => 'Reply To: Topic 1',
			'post_content' => 'Content of reply to Topic 1',
			'post_parent' => $t,
			'reply_meta' => array(
				'forum_id' => $f,
				'topic_id' => $t,
			),
		) );

		// Get the reply.
		$reply = bbp_get_reply( $r );

		remove_all_filters( 'bbp_get_reply_content' );

		// Reply post.
		$this->assertSame( 'Reply To: Topic 1', bbp_get_reply_title( $r ) );
		$this->assertSame( 'Content of reply to Topic 1', bbp_get_reply_content( $r ) );
		$this->assertSame( 'publish', bbp_get_reply_status( $r ) );
		$this->assertSame( $t, wp_get_post_parent_id( $r ) );
		$this->assertEquals( 'http://' . WP_TESTS_DOMAIN . '/?reply=' . $reply->post_name, $reply->guid );

		// Reply meta.
		$this->assertSame( $f, bbp_get_reply_forum_id( $r ) );
		$this->assertSame( $t, bbp_get_reply_topic_id( $r ) );
	}

	/**
	 * @covers ::bbp_new_reply_handler
	 * @todo   Implement test_bbp_new_reply_handler().
	 */
	public function test_bbp_new_reply_handler() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_edit_reply_handler
	 * @todo   Implement test_bbp_edit_reply_handler().
	 */
	public function test_bbp_edit_reply_handler() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_update_reply
	 * @todo   Implement test_bbp_update_reply().
	 */
	public function test_bbp_update_reply() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_update_reply_walker
	 */
	public function test_bbp_update_reply_walker() {
		$forum_id = $this->factory->forum->create();
		$topic_id = $this->factory->topic->create( array(
			'post_parent' => $forum_id,
			'topic_meta'  => array( 'forum_id' => $forum_id ),
		) );
		$reply_id = wp_insert_post( array(
			'post_parent' => $topic_id,
			'post_status' => bbp_get_public_status_id(),
			'post_type'   => bbp_get_reply_post_type(),
			'post_title'  => 'Reply walker',
		) );
		$active_time = get_post_field( 'post_date', $reply_id );

		bbp_update_reply_walker( $reply_id, $active_time, $forum_id, $topic_id, false );

		$this->assertSame( 1, bbp_get_topic_reply_count( $topic_id, true ) );
		$this->assertSame( 0, bbp_get_topic_reply_count_hidden( $topic_id, true ) );
		$this->assertSame( 1, bbp_get_forum_reply_count( $forum_id, false, true ) );
		$this->assertSame( 0, bbp_get_forum_reply_count_hidden( $forum_id, false, true ) );
		$this->assertSame( $reply_id, bbp_get_topic_last_reply_id( $topic_id ) );
		$this->assertSame( $reply_id, bbp_get_forum_last_reply_id( $forum_id ) );
	}

	/**
	 * @covers ::bbp_update_reply
	 * @covers ::bbp_update_reply_walker
	 */
	public function test_bbp_new_reply_updates_each_ancestor_once() {
		$root_id  = $this->factory->forum->create();
		$forum_id = $this->factory->forum->create(
			array(
				'post_parent' => $root_id,
			)
		);
		$topic_id = $this->factory->topic->create(
			array(
				'post_parent' => $forum_id,
				'topic_meta'  => array( 'forum_id' => $forum_id )
			)
		);
		$reply_id = wp_insert_post(
			array(
				'post_parent' => $topic_id,
				'post_status' => bbp_get_public_status_id(),
				'post_type'   => bbp_get_reply_post_type(),
				'post_title'  => 'Nested reply walker'
			)
		);

		$topic_updates  = array();
		$forum_updates  = array();
		$voice_counts   = 0;
		$topic_callback = function( $last_reply_id, $updated_topic_id ) use ( &$topic_updates ) {
			$topic_updates[] = $updated_topic_id;
			return $last_reply_id;
		};
		$forum_callback = function( $last_reply_id, $updated_forum_id ) use ( &$forum_updates ) {
			$forum_updates[] = $updated_forum_id;
			return $last_reply_id;
		};
		$voice_callback = function( $count ) use ( &$voice_counts ) {
			$voice_counts++;
			return $count;
		};

		add_filter( 'bbp_update_topic_last_reply_id', $topic_callback, 10, 2 );
		add_filter( 'bbp_update_forum_last_reply_id', $forum_callback, 10, 2 );
		add_filter( 'bbp_update_topic_voice_count', $voice_callback );

		do_action( 'bbp_new_reply', $reply_id, $topic_id, $forum_id, array(), 0, false, 0 );

		remove_filter( 'bbp_update_topic_last_reply_id', $topic_callback, 10 );
		remove_filter( 'bbp_update_forum_last_reply_id', $forum_callback, 10 );
		remove_filter( 'bbp_update_topic_voice_count', $voice_callback, 10 );

		$this->assertSame( array( $topic_id ), $topic_updates );
		$this->assertSame( array( $forum_id, $root_id ), $forum_updates );
		$this->assertSame( 1, $voice_counts );
		$this->assertSame( $reply_id, bbp_get_topic_last_reply_id( $topic_id ) );
		$this->assertSame( $reply_id, bbp_get_forum_last_reply_id( $forum_id ) );
		$this->assertSame( $reply_id, bbp_get_forum_last_reply_id( $root_id ) );
	}

	/**
	 * @covers ::bbp_update_reply_forum_id
	 */
	public function test_bbp_update_reply_forum_id() {
		bbp_create_initial_content();

		$forum_id = 36;
		$topic_id = 37;
		$reply_id = 38;

		bbp_update_reply_forum_id( $reply_id, $forum_id);

		$reply_forum_id = bbp_get_reply_forum_id( $reply_id );
		$this->assertSame( 36, $reply_forum_id );
	}

	/**
	 * @covers ::bbp_update_reply_topic_id
	 */
	public function test_bbp_update_reply_topic_id() {
		bbp_create_initial_content();

		$forum_id = 36;
		$topic_id = 37;
		$reply_id = 38;

		bbp_update_reply_topic_id( $reply_id, $topic_id);

		$reply_topic_id = bbp_get_reply_topic_id( $reply_id );
		$this->assertSame( 37, $reply_topic_id );
	}

	/**
	 * @covers ::bbp_update_reply_to
	 */
	public function test_bbp_update_reply_to() {
		$forum_id  = $this->factory->forum->create();
		$topic_ids = $this->factory->topic->create_many( 2, array( 'post_parent' => $forum_id ) );
		$parent_id = $this->factory->reply->create( array( 'post_parent' => $topic_ids[0] ) );
		$reply_id  = $this->factory->reply->create( array( 'post_parent' => $topic_ids[0] ) );
		$other_id  = $this->factory->reply->create( array( 'post_parent' => $topic_ids[1] ) );

		$this->assertSame( $parent_id, bbp_update_reply_to( $reply_id, $parent_id ) );
		$this->assertSame( $parent_id, bbp_get_reply_to( $reply_id ) );
		$this->assertSame( 0, bbp_update_reply_to( $reply_id, $other_id ) );
		$this->assertSame( 0, bbp_get_reply_to( $reply_id ) );
	}

	/**
	 * @covers ::bbp_get_reply_ancestors
	 */
	public function test_bbp_get_reply_ancestors() {
		$topic_id  = $this->factory->topic->create();
		$root_id   = $this->factory->reply->create( array( 'post_parent' => $topic_id ) );
		$parent_id = $this->factory->reply->create( array( 'post_parent' => $topic_id ) );
		$child_id  = $this->factory->reply->create( array( 'post_parent' => $topic_id ) );
		bbp_update_reply_to( $parent_id, $root_id );
		bbp_update_reply_to( $child_id, $parent_id );

		$this->assertSame( array(), bbp_get_reply_ancestors( $root_id ) );
		$this->assertSame( array( $root_id ), bbp_get_reply_ancestors( $parent_id ) );
		$this->assertSame( array( $parent_id, $root_id ), bbp_get_reply_ancestors( $child_id ) );
	}

	/**
	 * @covers ::bbp_update_reply_revision_log
	 * @todo   Implement test_bbp_update_reply_revision_log().
	 */
	public function test_bbp_update_reply_revision_log() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_move_reply_handler
	 * @todo   Implement test_bbp_move_reply_handler().
	 */
	public function test_bbp_move_reply_handler() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_move_reply_count
	 */
	public function test_bbp_move_reply_count() {
		$topic_author_id       = $this->factory->user->create();
		$reply_author_id       = $this->factory->user->create();
		$destination_author_id = $this->factory->user->create();
		$source_parent_id      = $this->factory->forum->create( array( 'forum_meta' => array( 'forum_type' => 'category' ) ) );
		$destination_parent_id = $this->factory->forum->create( array( 'forum_meta' => array( 'forum_type' => 'category' ) ) );
		$source_forum_id       = $this->factory->forum->create( array( 'post_parent' => $source_parent_id ) );
		$destination_forum_id  = $this->factory->forum->create( array( 'post_parent' => $destination_parent_id ) );
		$source_topic_id      = $this->factory->topic->create( array(
			'post_author' => $topic_author_id,
			'post_parent' => $source_forum_id,
			'topic_meta'  => array( 'forum_id' => $source_forum_id ),
		) );
		$destination_topic_id = $this->factory->topic->create( array(
			'post_author' => $destination_author_id,
			'post_parent' => $destination_forum_id,
			'topic_meta'  => array( 'forum_id' => $destination_forum_id ),
		) );
		$reply_id = $this->factory->reply->create( array(
			'post_author' => $reply_author_id,
			'post_parent' => $source_topic_id,
			'reply_meta'  => array(
				'forum_id' => $source_forum_id,
				'topic_id' => $source_topic_id,
			),
		) );

		wp_update_post( array(
			'ID'          => $reply_id,
			'post_parent' => $destination_topic_id,
		) );
		bbp_update_reply_topic_id( $reply_id, $destination_topic_id );
		bbp_update_reply_forum_id( $reply_id, $destination_forum_id );
		bbp_move_reply_count( $reply_id, $source_topic_id, $destination_topic_id );

		$this->assertSame( 0, bbp_get_forum_reply_count( $source_forum_id, true, true ) );
		$this->assertSame( 1, bbp_get_forum_reply_count( $destination_forum_id, true, true ) );
		$this->assertSame( 0, bbp_get_forum_reply_count( $source_parent_id, true, true ) );
		$this->assertSame( 1, bbp_get_forum_reply_count( $destination_parent_id, true, true ) );
		$this->assertSame( 0, bbp_get_topic_reply_count( $source_topic_id, true ) );
		$this->assertSame( 1, bbp_get_topic_reply_count( $destination_topic_id, true ) );
		$this->assertEqualSets( array( $topic_author_id ), bbp_get_topic_engagements( $source_topic_id ) );
		$this->assertEqualSets( array( $reply_author_id, $destination_author_id ), bbp_get_topic_engagements( $destination_topic_id ) );
		$this->assertSame( 1, bbp_get_topic_voice_count( $source_topic_id, true ) );
		$this->assertSame( 2, bbp_get_topic_voice_count( $destination_topic_id, true ) );
	}

	/**
	 * @covers ::bbp_move_reply_count
	 */
	public function test_bbp_move_reply_count_updates_hidden_counts() {
		$source_parent_id      = $this->factory->forum->create( array( 'forum_meta' => array( 'forum_type' => 'category' ) ) );
		$destination_parent_id = $this->factory->forum->create( array( 'forum_meta' => array( 'forum_type' => 'category' ) ) );
		$source_forum_id       = $this->factory->forum->create( array( 'post_parent' => $source_parent_id ) );
		$destination_forum_id  = $this->factory->forum->create( array( 'post_parent' => $destination_parent_id ) );
		$source_topic_id      = $this->factory->topic->create( array(
			'post_parent' => $source_forum_id,
			'topic_meta'  => array( 'forum_id' => $source_forum_id ),
		) );
		$destination_topic_id = $this->factory->topic->create( array(
			'post_parent' => $destination_forum_id,
			'topic_meta'  => array( 'forum_id' => $destination_forum_id ),
		) );
		$reply_id = $this->factory->reply->create( array(
			'post_parent' => $source_topic_id,
			'post_status' => bbp_get_pending_status_id(),
			'reply_meta'  => array(
				'forum_id' => $source_forum_id,
				'topic_id' => $source_topic_id,
			),
		) );

		wp_update_post( array(
			'ID'          => $reply_id,
			'post_parent' => $destination_topic_id,
		) );
		bbp_update_reply_topic_id( $reply_id, $destination_topic_id );
		bbp_update_reply_forum_id( $reply_id, $destination_forum_id );
		bbp_move_reply_count( $reply_id, $source_topic_id, $destination_topic_id );

		$this->assertSame( 0, bbp_get_forum_reply_count_hidden( $source_forum_id, true, true ) );
		$this->assertSame( 1, bbp_get_forum_reply_count_hidden( $destination_forum_id, true, true ) );
		$this->assertSame( 0, bbp_get_forum_reply_count_hidden( $source_parent_id, true, true ) );
		$this->assertSame( 1, bbp_get_forum_reply_count_hidden( $destination_parent_id, true, true ) );
		$this->assertSame( 0, bbp_get_topic_reply_count_hidden( $source_topic_id, true ) );
		$this->assertSame( 1, bbp_get_topic_reply_count_hidden( $destination_topic_id, true ) );
	}

	/**
	 * @covers ::bbp_move_reply_count
	 */
	public function test_bbp_move_reply_count_transfers_contribution_when_converted_to_topic() {
		$user_id  = $this->factory->user->create();
		$forum_id = $this->factory->forum->create();
		$topic_id = $this->factory->topic->create( array(
			'post_parent' => $forum_id,
			'topic_meta'  => array( 'forum_id' => $forum_id ),
		) );
		$reply_id = $this->factory->reply->create( array(
			'post_author' => $user_id,
			'post_parent' => $topic_id,
			'reply_meta'  => array(
				'forum_id' => $forum_id,
				'topic_id' => $topic_id,
			),
		) );

		wp_update_post( array(
			'ID'          => $reply_id,
			'post_parent' => $forum_id,
			'post_type'   => bbp_get_topic_post_type(),
		) );
		bbp_update_topic_forum_id( $reply_id, $forum_id );
		bbp_update_topic_topic_id( $reply_id );
		bbp_move_reply_count( $reply_id, $topic_id, $reply_id );

		$this->assertSame( 0, bbp_get_user_reply_count( $user_id, true ) );
		$this->assertSame( 1, bbp_get_user_topic_count( $user_id, true ) );
		$this->assertSame( 0, bbp_get_forum_reply_count( $forum_id, true, true ) );
		$this->assertSame( 2, bbp_get_forum_topic_count( $forum_id, true, true ) );
	}

	/**
	 * @covers ::bbp_toggle_reply_handler
	 * @todo   Implement test_bbp_toggle_reply_handler().
	 */
	public function test_bbp_toggle_reply_handler() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_delete_reply
	 * @todo   Implement test_bbp_delete_reply().
	 */
	public function test_bbp_delete_reply() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_trash_reply
	 * @todo   Implement test_bbp_trash_reply().
	 */
	public function test_bbp_trash_reply() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_untrash_reply
	 * @todo   Implement test_bbp_untrash_reply().
	 */
	public function test_bbp_untrash_reply() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_deleted_reply
	 * @todo   Implement test_bbp_deleted_reply().
	 */
	public function test_bbp_deleted_reply() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_trashed_reply
	 * @todo   Implement test_bbp_trashed_reply().
	 */
	public function test_bbp_trashed_reply() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_untrashed_reply
	 * @todo   Implement test_bbp_untrashed_reply().
	 */
	public function test_bbp_untrashed_reply() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_get_replies_per_page
	 * @todo   Implement test_bbp_get_replies_per_page().
	 */
	public function test_bbp_get_replies_per_page() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_get_replies_per_rss_page
	 * @todo   Implement test_bbp_get_replies_per_rss_page().
	 */
	public function test_bbp_get_replies_per_rss_page() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_reply_content_autoembed
	 * @todo   Implement test_bbp_reply_content_autoembed().
	 */
	public function test_bbp_reply_content_autoembed() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::_bbp_has_replies_where
	 * @todo   Implement test_bbp_has_replies_where().
	 */
	public function test_bbp_has_replies_where() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_display_replies_feed_rss2
	 * @todo   Implement test_bbp_display_replies_feed_rss2().
	 */
	public function test_bbp_display_replies_feed_rss2() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_check_reply_edit
	 * @todo   Implement test_bbp_check_reply_edit().
	 */
	public function test_bbp_check_reply_edit() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_update_reply_position
	 */
	public function test_bbp_update_reply_position() {
		$topic_id = $this->factory->topic->create();
		$reply_id = $this->factory->reply->create( array( 'post_parent' => $topic_id ) );

		$this->assertSame( 3, bbp_update_reply_position( $reply_id, 3 ) );
		$this->assertSame( 3, (int) get_post_field( 'menu_order', $reply_id ) );
		$this->assertFalse( bbp_update_reply_position( $reply_id, 3 ) );
	}

	/**
	 * @covers ::bbp_get_reply_position_raw
	 * @todo   Implement test_bbp_get_reply_position_raw().
	 */
	public function test_bbp_get_reply_position_raw() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_list_replies
	 * @todo   Implement test_bbp_list_replies().
	 */
	public function test_bbp_list_replies() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_validate_reply_to
	 */
	public function test_bbp_validate_reply_to() {
		$forum_id  = $this->factory->forum->create();
		$topic_ids = $this->factory->topic->create_many( 2, array( 'post_parent' => $forum_id ) );
		$parent_id = $this->factory->reply->create( array( 'post_parent' => $topic_ids[0] ) );
		$reply_id  = $this->factory->reply->create( array( 'post_parent' => $topic_ids[0] ) );
		$other_id  = $this->factory->reply->create( array( 'post_parent' => $topic_ids[1] ) );

		$this->assertSame( $parent_id, bbp_validate_reply_to( $parent_id, $reply_id ) );
		$this->assertSame( 0, bbp_validate_reply_to( $reply_id, $reply_id ) );
		$this->assertSame( 0, bbp_validate_reply_to( (string) $reply_id, $reply_id ) );
		$this->assertSame( 0, bbp_validate_reply_to( $other_id, $reply_id ) );
		$this->assertSame( $other_id, bbp_validate_reply_to( $other_id ) );
	}
}
