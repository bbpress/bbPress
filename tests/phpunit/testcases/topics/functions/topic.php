<?php

/**
 * Tests for the topic component functions.
 *
 * @group topics
 * @group functions
 * @group topic
 */
class BBP_Tests_Topics_Functions_Topic extends BBP_UnitTestCase {

	protected $old_current_user;
	protected $keymaster_id;

	/**
	 * @group canonical
	 * @covers ::bbp_insert_topic
	 */
	public function test_bbp_insert_topic() {
		$u = $this->factory->user->create_many( 2 );
		$f = $this->factory->forum->create();

		$now = time();
		$post_date = date( 'Y-m-d H:i:s', $now - 60 * 60 * 100 );

		$t = $this->factory->topic->create( array(
			'post_title'   => 'Topic 1',
			'post_content' => 'Content for Topic 1',
			'post_parent'  => $f,
			'post_date'    => $post_date,
			'post_author'  => $u[0],
			'topic_meta'   => array(
				'forum_id' => $f,
			),
		) );

		$r = $this->factory->reply->create( array(
			'post_parent' => $t,
			'post_date'   => $post_date,
			'post_author'  => $u[1],
			'reply_meta'  => array(
				'forum_id' => $f,
				'topic_id' => $t,
			),
		) );

		// Get the topic.
		$topic = bbp_get_topic( $t );

		remove_all_filters( 'bbp_get_topic_content' );

		// Topic post.
		$this->assertSame( 'Topic 1', bbp_get_topic_title( $t ) );
		$this->assertSame( 'Content for Topic 1', bbp_get_topic_content( $t ) );
		$this->assertSame( 'publish', bbp_get_topic_status( $t ) );
		$this->assertSame( $f, wp_get_post_parent_id( $t ) );
		$this->assertEquals( 'http://' . WP_TESTS_DOMAIN . '/?topic=' . $topic->post_name, $topic->guid );

		// Topic meta.
		$this->assertSame( $f, bbp_get_topic_forum_id( $t ) );
		$this->assertSame( 1,  bbp_get_topic_reply_count( $t, true ) );
		$this->assertSame( 0,  bbp_get_topic_reply_count_hidden( $t, true ) );
		$this->assertSame( 2,  bbp_get_topic_voice_count( $t, true ) );
		$this->assertSame( $r, bbp_get_topic_last_reply_id( $t ) );
		$this->assertSame( $r, bbp_get_topic_last_active_id( $t ) );
		$this->assertSame( '4 days, 4 hours ago', bbp_get_topic_last_active_time( $t ) );
	}

	/**
	 * @covers ::bbp_new_topic_handler
	 * @todo   Implement test_bbp_new_topic_handler().
	 */
	public function test_bbp_new_topic_handler() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_new_topic_handler
	 */
	public function test_bbp_new_topic_handler_requires_forum_id_for_all_roles() {
		$forum_id                   = $this->factory->forum->create();
		$no_role_id                 = $this->factory->user->create();
		$participant_id             = $this->factory->user->create();
		$moderator_id               = $this->factory->user->create();
		$keymaster_id               = $this->factory->user->create();
		$old_post                   = $_POST;
		$old_request                = $_REQUEST;
		$old_server                 = $_SERVER;
		$old_user                   = get_current_user_id();
		$old_errors                 = bbpress()->errors;
		$old_current_forum_id       = bbpress()->current_forum_id;
		$old_allow_anonymous        = get_option( '_bbp_allow_anonymous' );
		$old_allow_content_throttle = get_option( '_bbp_allow_content_throttle' );
		$created_topic_id           = 0;
		$record_topic               = function ( $topic_id ) use ( &$created_topic_id ) {
			$created_topic_id = $topic_id;
		};
		$prevent_redirect           = function() {
			throw new RuntimeException( 'New topic redirect.' );
		};
		$prevent_anonymous_cookies  = function() {
			return array();
		};
		$home_url                   = wp_parse_url( home_url( '/' ) );

		bbp_set_user_role( $participant_id, bbp_get_participant_role() );
		bbp_set_user_role( $moderator_id, bbp_get_moderator_role() );
		bbp_set_user_role( $keymaster_id, bbp_get_keymaster_role() );
		bbp_close_forum( $forum_id );
		update_option( '_bbp_allow_content_throttle', false );
		bbpress()->current_forum_id = $forum_id;
		$_SERVER['HTTP_HOST']       = $home_url['host'] . ( isset( $home_url['port'] ) ? ':' . $home_url['port'] : '' );
		$_SERVER['REQUEST_URI']     = $home_url['path'];
		$_SERVER['REMOTE_ADDR']     = '127.0.0.1';

		add_filter( 'bbp_is_single_forum', '__return_true' );
		add_action( 'bbp_new_topic', $record_topic );
		add_filter( 'wp_redirect', $prevent_redirect );
		add_filter( 'bbp_filter_anonymous_post_data', $prevent_anonymous_cookies );

		try {
			$actors = array(
				'logged-out'  => array( 0, false ),
				'anonymous'   => array( 0, true ),
				'no-role'     => array( $no_role_id, false ),
				'participant' => array( $participant_id, false ),
				'moderator'   => array( $moderator_id, false ),
				'keymaster'   => array( $keymaster_id, false ),
			);

			foreach ( $actors as $actor => $configuration ) {
				$created_topic_id = 0;
				$this->set_current_user( $configuration[0] );
				update_option( '_bbp_allow_anonymous', $configuration[1] );
				bbpress()->errors = new WP_Error();
				$_POST = array(
					'bbp_topic_title'   => 'Topic without a forum ID for ' . $actor,
					'bbp_topic_content' => 'Topic content',
				);
				$_REQUEST = array( '_wpnonce' => wp_create_nonce( 'bbp-new-topic' ) );

				if ( 'anonymous' === $actor ) {
					$_POST['bbp_anonymous_name']  = 'Anonymous User';
					$_POST['bbp_anonymous_email'] = 'anonymous@example.org';
				}

				try {
					bbp_new_topic_handler( 'bbp-new-topic' );
				} catch ( RuntimeException $exception ) {
					if ( 'New topic redirect.' !== $exception->getMessage() ) {
						throw $exception;
					}
				}

				$this->assertContains( 'bbp_topic_forum_id', bbpress()->errors->get_error_codes(), $actor );
				$this->assertSame( 0, $created_topic_id, $actor );
			}

			$cases = array(
				'empty'       => array( '', '<strong>Error</strong>: Forum ID is missing.' ),
				'zero'        => array( 0, '<strong>Error</strong>: Forum ID is missing.' ),
				'nonnumeric'  => array( 'not-a-number', '<strong>Error</strong>: Forum ID must be a number.' ),
				'negative'    => array( -1, '<strong>Error</strong>: Forum ID cannot be a negative number.' ),
				'nonexistent' => array( PHP_INT_MAX, '<strong>Error</strong>: Forum does not exist.' ),
			);

			$this->set_current_user( $keymaster_id );
			update_option( '_bbp_allow_anonymous', false );

			foreach ( $cases as $case => $configuration ) {
				$created_topic_id = 0;
				bbpress()->errors = new WP_Error();
				$_POST = array(
					'bbp_forum_id'      => $configuration[0],
					'bbp_topic_title'   => 'Topic with invalid forum ID: ' . $case,
					'bbp_topic_content' => 'Topic content',
				);
				$_REQUEST = array( '_wpnonce' => wp_create_nonce( 'bbp-new-topic' ) );

				try {
					bbp_new_topic_handler( 'bbp-new-topic' );
				} catch ( RuntimeException $exception ) {
					if ( 'New topic redirect.' !== $exception->getMessage() ) {
						throw $exception;
					}
				}

				$this->assertSame( array( 'bbp_topic_forum_id' ), bbpress()->errors->get_error_codes(), $case );
				$this->assertSame( $configuration[1], bbpress()->errors->get_error_message( 'bbp_topic_forum_id' ), $case );
				$this->assertSame( 0, $created_topic_id, $case );
			}
		} finally {
			remove_filter( 'bbp_filter_anonymous_post_data', $prevent_anonymous_cookies );
			remove_filter( 'wp_redirect', $prevent_redirect );
			remove_action( 'bbp_new_topic', $record_topic );
			remove_filter( 'bbp_is_single_forum', '__return_true' );
			$_POST                      = $old_post;
			$_REQUEST                   = $old_request;
			$_SERVER                    = $old_server;
			bbpress()->errors           = $old_errors;
			bbpress()->current_forum_id = $old_current_forum_id;
			update_option( '_bbp_allow_anonymous', $old_allow_anonymous );
			update_option( '_bbp_allow_content_throttle', $old_allow_content_throttle );
			$this->set_current_user( $old_user );
		}
	}

	/**
	 * @covers ::bbp_edit_topic_handler
	 * @covers ::bbp_filter_revision_post_author
	 * @covers ::bbp_save_initial_post_revision
	 */
	public function test_bbp_edit_topic_handler() {
		$forum_id         = $this->factory->forum->create();
		$author_id        = $this->factory->user->create();
		$admin_id         = $this->factory->user->create( array( 'role' => 'administrator' ) );
		$topic_id         = $this->factory->topic->create( array(
			'post_author' => $author_id,
			'post_date'   => '2020-01-02 03:04:05',
			'post_parent' => $forum_id,
			'topic_meta'  => array( 'forum_id' => $forum_id ),
		) );
		$old_post          = $_POST;
		$old_request       = $_REQUEST;
		$old_server        = $_SERVER;
		$old_user          = get_current_user_id();
		$old_errors        = bbpress()->errors;
		$original_title    = get_post_field( 'post_title', $topic_id );
		$original_content  = get_post_field( 'post_content', $topic_id );
		$original_modified = get_post_field( 'post_modified', $topic_id );
		$redirect          = null;
		$edited_topic_id   = 0;
		$record_topic      = function ( $edited_id ) use ( &$edited_topic_id ) {
			$edited_topic_id = $edited_id;
		};
		$prevent_redirect = function ( $location ) use ( &$redirect ) {
			$redirect = $location;
			throw new RuntimeException( 'Edit topic redirect.' );
		};
		$home_url = wp_parse_url( home_url( '/' ) );

		$_POST                   = array();
		$_REQUEST                = array();
		$_SERVER['HTTP_HOST']    = $home_url['host'] . ( isset( $home_url['port'] ) ? ':' . $home_url['port'] : '' );
		$_SERVER['REQUEST_URI']  = $home_url['path'];
		bbpress()->errors       = new WP_Error();
		add_action( 'bbp_edit_topic', $record_topic );
		add_filter( 'wp_redirect', $prevent_redirect );

		try {
			$this->assertNull( bbp_edit_topic_handler( 'invalid-action' ) );
			$this->assertNull( bbp_edit_topic_handler( 'bbp-edit-topic' ) );
			$this->assertContains( 'bbp_edit_topic_id', bbpress()->errors->get_error_codes() );

			$_POST['bbp_topic_id'] = $topic_id;
			$this->set_current_user( 0 );
			bbpress()->errors = new WP_Error();
			$this->assertNull( bbp_edit_topic_handler( 'bbp-edit-topic' ) );
			$this->assertContains( 'bbp_edit_topic_permission', bbpress()->errors->get_error_codes() );

			bbp_set_user_role( $admin_id, bbp_get_keymaster_role() );
			$this->set_current_user( $admin_id );
			bbpress()->errors = new WP_Error();
			$_POST = array(
				'bbp_topic_id'          => $topic_id,
				'bbp_forum_id'          => $forum_id,
				'bbp_topic_title'       => 'Edited topic',
				'bbp_topic_content'     => 'Edited content',
				'bbp_log_topic_edit'    => '1',
				'bbp_topic_edit_reason' => 'Clarify the question.',
			);
			$_REQUEST['_wpnonce'] = wp_create_nonce( 'bbp-edit-topic_' . $topic_id );
			$this->assertSame( array(), wp_get_post_revisions( $topic_id ) );

			$autosave_id = wp_create_post_autosave(
				array(
					'post_ID'    => $topic_id,
					'post_type'  => bbp_get_topic_post_type(),
					'post_title' => 'Autosaved topic',
					'content'    => 'Autosaved content',
				)
			);
			$this->assertIsInt( $autosave_id );
			$this->assertSame( $topic_id, wp_is_post_autosave( $autosave_id ) );

			try {
				bbp_edit_topic_handler( 'bbp-edit-topic' );
				$this->fail( 'Editing a topic should redirect.' );
			} catch ( RuntimeException $exception ) {
				if ( 'Edit topic redirect.' !== $exception->getMessage() ) {
					throw $exception;
				}
			}

			$this->assertSame( array(), bbpress()->errors->get_error_codes() );
			$this->assertSame( $topic_id, $edited_topic_id );
			$this->assertSame( $forum_id, wp_get_post_parent_id( $topic_id ) );
			$this->assertSame( 'Edited topic', get_post_field( 'post_title', $topic_id ) );
			$this->assertSame( 'Edited content', get_post_field( 'post_content', $topic_id ) );
			$this->assertSame( bbp_get_topic_permalink( $topic_id ), $redirect );
			$this->assertFalse( has_filter( 'wp_insert_post_data', 'bbp_filter_revision_post_author' ) );

			$revisions = array_values(
				wp_get_post_revisions(
					$topic_id,
					array(
						'name'  => "{$topic_id}-revision-v1",
						'order' => 'ASC',
					)
				)
			);
			$this->assertCount( 2, $revisions );
			$this->assertSame( $original_title, $revisions[0]->post_title );
			$this->assertSame( $original_content, $revisions[0]->post_content );
			$this->assertSame( $original_modified, $revisions[0]->post_modified );
			$this->assertSame( $author_id, (int) $revisions[0]->post_author );
			$this->assertSame( 'Edited topic', $revisions[1]->post_title );
			$this->assertSame( 'Edited content', $revisions[1]->post_content );
			$this->assertSame( $admin_id, (int) $revisions[1]->post_author );
			$this->assertSame(
				array(
					$revisions[1]->ID => array(
						'author' => $admin_id,
						'reason' => 'Clarify the question',
					),
				),
				bbp_get_topic_raw_revision_log( $topic_id )
			);
		} finally {
			remove_filter( 'wp_redirect', $prevent_redirect );
			remove_action( 'bbp_edit_topic', $record_topic );
			$_POST            = $old_post;
			$_REQUEST         = $old_request;
			$_SERVER          = $old_server;
			bbpress()->errors = $old_errors;
			$this->set_current_user( $old_user );
		}
	}

	/**
	 * @covers ::bbp_update_topic
	 * @todo   Implement test_bbp_update_topic().
	 */
	public function test_bbp_update_topic() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_update_topic_walker
	 */
	public function test_bbp_update_topic_walker() {
		$forum_id = $this->factory->forum->create();
		$topic_id = wp_insert_post( array(
			'post_parent' => $forum_id,
			'post_status' => bbp_get_public_status_id(),
			'post_type'   => bbp_get_topic_post_type(),
			'post_title'  => 'Topic walker',
		) );
		$active_time = get_post_field( 'post_date', $topic_id );

		bbp_update_topic_walker( $topic_id, $active_time, $forum_id, 0, false );

		$this->assertSame( 1, bbp_get_forum_topic_count( $forum_id, false, true ) );
		$this->assertSame( 0, bbp_get_forum_topic_count_hidden( $forum_id, false, true ) );
		$this->assertSame( $topic_id, bbp_get_forum_last_topic_id( $forum_id ) );
		$this->assertSame( $topic_id, bbp_get_forum_last_active_id( $forum_id ) );
	}

	/**
	 * @covers ::bbp_update_topic_walker
	 * @covers ::bbp_update_forum
	 */
	public function test_bbp_new_topic_updates_each_forum_ancestor_once() {
		$root_id  = $this->factory->forum->create();
		$forum_id = $this->factory->forum->create(
			array(
				'post_parent' => $root_id,
			)
		);
		$topic_id = wp_insert_post(
			array(
				'post_parent' => $forum_id,
				'post_status' => bbp_get_public_status_id(),
				'post_type'   => bbp_get_topic_post_type(),
				'post_title'  => 'Nested topic walker',
			)
		);

		$forum_updates   = array();
		$subforum_counts = 0;
		$voice_counts    = 0;
		$forum_callback  = function( $last_topic_id, $updated_forum_id ) use ( &$forum_updates ) {
			$forum_updates[] = $updated_forum_id;
			return $last_topic_id;
		};
		$subforum_callback = function( $count ) use ( &$subforum_counts ) {
			$subforum_counts++;
			return $count;
		};
		$voice_callback = function( $count ) use ( &$voice_counts ) {
			$voice_counts++;
			return $count;
		};

		add_filter( 'bbp_update_forum_last_topic_id', $forum_callback, 10, 2 );
		add_filter( 'bbp_update_forum_subforum_count', $subforum_callback );
		add_filter( 'bbp_update_topic_voice_count', $voice_callback );

		do_action( 'bbp_new_topic', $topic_id, $forum_id, array(), 0 );

		remove_filter( 'bbp_update_forum_last_topic_id', $forum_callback, 10 );
		remove_filter( 'bbp_update_forum_subforum_count', $subforum_callback, 10 );
		remove_filter( 'bbp_update_topic_voice_count', $voice_callback, 10 );

		$this->assertSame( array( $forum_id, $root_id ), $forum_updates );
		$this->assertSame( 0, $subforum_counts );
		$this->assertSame( 1, $voice_counts );
		$this->assertSame( $topic_id, bbp_get_forum_last_topic_id( $forum_id ) );
		$this->assertSame( $topic_id, bbp_get_forum_last_topic_id( $root_id ) );
		$this->assertSame( $topic_id, bbp_get_forum_last_active_id( $forum_id ) );
		$this->assertSame( $topic_id, bbp_get_forum_last_active_id( $root_id ) );
	}

	/**
	 * @covers ::bbp_move_topic_handler
	 */
	public function test_bbp_move_topic_handler() {
		$old_current_user = 0;
		$this->old_current_user = get_current_user_id();
		$this->set_current_user( $this->factory->user->create( array( 'role' => 'administrator' ) ) );
		$this->keymaster_id = get_current_user_id();
		bbp_set_user_role( $this->keymaster_id, bbp_get_keymaster_role() );

		$old_forum_id = $this->factory->forum->create();
		$topic_id = $this->factory->topic->create( array(
			'post_parent' => $old_forum_id,
			'topic_meta' => array(
				'forum_id' => $old_forum_id,
			),
		) );

		$reply_id = $this->factory->reply->create( array(
			'post_parent' => $topic_id,
			'reply_meta' => array(
				'forum_id' => $old_forum_id,
				'topic_id' => $topic_id,
			),
		) );

		// Topic post parent
		$topic_parent = wp_get_post_parent_id( $topic_id );
		$this->assertSame( $old_forum_id, $topic_parent );

		// Forum meta
		$this->assertSame( 1, bbp_get_forum_topic_count( $old_forum_id, true, true ) );
		$this->assertSame( 1, bbp_get_forum_reply_count( $old_forum_id, true, true ) );
		$this->assertSame( $topic_id, bbp_get_forum_last_topic_id( $old_forum_id ) );
		$this->assertSame( $reply_id, bbp_get_forum_last_reply_id( $old_forum_id ) );
		$this->assertSame( $reply_id, bbp_get_forum_last_active_id( $old_forum_id ) );

		// Topic meta
		$this->assertSame( $old_forum_id, bbp_get_topic_forum_id( $topic_id ) );
		$this->assertSame( 1, bbp_get_topic_voice_count( $topic_id, true ) );
		$this->assertSame( 1, bbp_get_topic_reply_count( $topic_id, true ) );
		$this->assertSame( $reply_id, bbp_get_topic_last_reply_id( $topic_id ) );
		$this->assertSame( $reply_id, bbp_get_topic_last_active_id( $topic_id ) );

		// Reply Meta
		$this->assertSame( $old_forum_id, bbp_get_reply_forum_id( $reply_id ) );
		$this->assertSame( $topic_id, bbp_get_reply_topic_id( $reply_id ) );

		// Create a new forum
		$new_forum_id = $this->factory->forum->create();

		// Track unbounded topic ID queries during the move
		$unbounded_topic_queries = 0;
		$query_callback = function( $query ) use ( &$unbounded_topic_queries ) {
			if (
				( 'ids' === $query->get( 'fields' ) ) &&
				( bbp_get_topic_post_type() === $query->get( 'post_type' ) ) &&
				! empty( $query->get( 'post_parent' ) ) &&
				! empty( $query->get( 'nopaging' ) )
			) {
				$unbounded_topic_queries++;
			}
		};
		add_action( 'pre_get_posts', $query_callback );

		// Move the topic into the new forum
		bbp_move_topic_handler( $topic_id, $old_forum_id, $new_forum_id );
		remove_action( 'pre_get_posts', $query_callback );

		$this->assertSame( 0, $unbounded_topic_queries );

		// Topic post parent
		$topic_parent = wp_get_post_parent_id( $topic_id );
		$this->assertSame( $new_forum_id, $topic_parent );

		// Forum meta
		$this->assertSame( 1, bbp_get_forum_topic_count( $new_forum_id, true, true ) );
		$this->assertSame( 1, bbp_get_forum_reply_count( $new_forum_id, true, true ) );
		$this->assertSame( $topic_id, bbp_get_forum_last_topic_id( $new_forum_id ) );
		$this->assertSame( $reply_id, bbp_get_forum_last_reply_id( $new_forum_id ) );
		$this->assertSame( $reply_id, bbp_get_forum_last_active_id( $new_forum_id ) );

		// Topic meta
		$this->assertSame( $new_forum_id, bbp_get_topic_forum_id( $topic_id ) );
		$this->assertSame( 1, bbp_get_topic_voice_count( $topic_id, true ) );
		$this->assertSame( 1, bbp_get_topic_reply_count( $topic_id, true ) );
		$this->assertSame( $reply_id, bbp_get_topic_last_reply_id( $topic_id ) );
		$this->assertSame( $reply_id, bbp_get_topic_last_active_id( $topic_id ) );

		// Reply Meta
		$this->assertSame( $new_forum_id, bbp_get_reply_forum_id( $reply_id ) );
		$this->assertSame( $topic_id, bbp_get_reply_topic_id( $reply_id ) );

		// Old Topic/Reply Counts
		$this->assertSame( 0, bbp_get_forum_topic_count( $old_forum_id, true, true ) );
		$this->assertSame( 0, bbp_get_forum_reply_count( $old_forum_id, true, true ) );


		// Retore the user
		$this->set_current_user( $this->old_current_user );
	}

	/**
	 * @covers ::bbp_merge_topic_handler
	 * @todo   Implement test_bbp_merge_topic_handler().
	 */
	public function test_bbp_merge_topic_handler() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_merge_topic_count
	 */
	public function test_bbp_merge_topic_count() {
		$source_author_id      = $this->factory->user->create();
		$reply_author_id       = $this->factory->user->create();
		$destination_author_id = $this->factory->user->create();
		$source_parent_id      = $this->factory->forum->create( array( 'forum_meta' => array( 'forum_type' => 'category' ) ) );
		$destination_parent_id = $this->factory->forum->create( array( 'forum_meta' => array( 'forum_type' => 'category' ) ) );
		$source_forum_id       = $this->factory->forum->create( array( 'post_parent' => $source_parent_id ) );
		$destination_forum_id  = $this->factory->forum->create( array( 'post_parent' => $destination_parent_id ) );
		$source_topic_id      = $this->factory->topic->create( array(
			'post_author' => $source_author_id,
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
		wp_update_post( array(
			'ID'          => $source_topic_id,
			'post_parent' => $destination_topic_id,
			'post_type'   => bbp_get_reply_post_type(),
		) );
		bbp_update_reply_topic_id( $source_topic_id, $destination_topic_id );
		bbp_update_reply_forum_id( $source_topic_id, $destination_forum_id );
		bbp_merge_topic_count( $destination_topic_id, $source_topic_id, $source_forum_id );

		$this->assertSame( 0, bbp_get_forum_topic_count( $source_forum_id, true, true ) );
		$this->assertSame( 0, bbp_get_forum_reply_count( $source_forum_id, true, true ) );
		$this->assertSame( 2, bbp_get_forum_reply_count( $destination_forum_id, true, true ) );
		$this->assertSame( 0, bbp_get_forum_topic_count( $source_parent_id, true, true ) );
		$this->assertSame( 0, bbp_get_forum_reply_count( $source_parent_id, true, true ) );
		$this->assertSame( 2, bbp_get_forum_reply_count( $destination_parent_id, true, true ) );
		$this->assertSame( 2, bbp_get_topic_reply_count( $destination_topic_id, true ) );
		$this->assertSame( 0, bbp_get_user_topic_count( $source_author_id, true ) );
		$this->assertSame( 1, bbp_get_user_reply_count( $source_author_id, true ) );
		$this->assertEqualSets( array( $source_author_id, $reply_author_id, $destination_author_id ), bbp_get_topic_engagements( $destination_topic_id ) );
		$this->assertSame( 3, bbp_get_topic_voice_count( $destination_topic_id, true ) );
	}

	/**
	 * @covers ::bbp_split_topic_handler
	 * @todo   Implement test_bbp_split_topic_handler().
	 */
	public function test_bbp_split_topic_handler() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_split_topic_count
	 */
	public function test_bbp_split_topic_count() {
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
		bbp_split_topic_count( $reply_id, $source_topic_id, $destination_topic_id );

		$this->assertSame( 0, bbp_get_forum_reply_count( $source_forum_id, true, true ) );
		$this->assertSame( 1, bbp_get_forum_reply_count( $destination_forum_id, true, true ) );
		$this->assertSame( 0, bbp_get_forum_reply_count( $source_parent_id, true, true ) );
		$this->assertSame( 1, bbp_get_forum_reply_count( $destination_parent_id, true, true ) );
		$this->assertSame( 0, bbp_get_topic_reply_count( $source_topic_id, true ) );
		$this->assertSame( 1, bbp_get_topic_reply_count( $destination_topic_id, true ) );
	}

	/**
	 * @covers ::bbp_split_topic_count
	 * @ticket BBP3678
	 */
	public function test_bbp_split_topic_count_updates_converted_reply_counts_and_engagements() {
		$source_author_id = $this->factory->user->create();
		$reply_author_id  = $this->factory->user->create();
		$forum_id         = $this->factory->forum->create();
		$source_topic_id  = $this->factory->topic->create( array(
			'post_author' => $source_author_id,
			'post_parent' => $forum_id,
			'topic_meta'  => array( 'forum_id' => $forum_id ),
		) );
		$from_reply_id = $this->factory->reply->create( array(
			'post_author' => $reply_author_id,
			'post_parent' => $source_topic_id,
			'reply_meta'  => array(
				'forum_id' => $forum_id,
				'topic_id' => $source_topic_id,
			),
		) );

		wp_update_post( array(
			'ID'          => $from_reply_id,
			'post_parent' => $forum_id,
			'post_type'   => bbp_get_topic_post_type(),
		) );
		bbp_update_topic_topic_id( $from_reply_id );
		bbp_update_topic_forum_id( $from_reply_id, $forum_id );

		bbp_split_topic_count( $from_reply_id, $source_topic_id, $from_reply_id );

		$this->assertSame( 2, bbp_get_forum_topic_count( $forum_id, true, true ) );
		$this->assertSame( 0, bbp_get_forum_reply_count( $forum_id, true, true ) );
		$this->assertSame( 1, bbp_get_user_topic_count( $source_author_id, true ) );
		$this->assertSame( 1, bbp_get_user_topic_count( $reply_author_id, true ) );
		$this->assertSame( 0, bbp_get_user_reply_count( $reply_author_id, true ) );
		$this->assertSame( array( $source_author_id ), bbp_get_topic_engagements( $source_topic_id ) );
		$this->assertSame( array( $reply_author_id ), bbp_get_topic_engagements( $from_reply_id ) );
		$this->assertSame( 1, bbp_get_topic_voice_count( $source_topic_id, true ) );
		$this->assertSame( 1, bbp_get_topic_voice_count( $from_reply_id, true ) );
	}

	/**
	 * @covers ::bbp_get_topic_statuses
	 * @todo   Implement test_bbp_get_topic_statuses().
	 */
	public function test_bbp_get_topic_statuses() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_get_topic_types
	 * @todo   Implement test_bbp_get_topic_types().
	 */
	public function test_bbp_get_topic_types() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_get_stickies
	 * @todo   Implement test_bbp_get_stickies().
	 */
	public function test_bbp_get_stickies() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_get_super_stickies
	 * @todo   Implement test_bbp_get_super_stickies().
	 */
	public function test_bbp_get_super_stickies() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_toggle_topic_handler
	 * @todo   Implement test_bbp_toggle_topic_handler().
	 */
	public function test_bbp_toggle_topic_handler() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_remove_topic_from_all_favorites
	 * @todo   Implement test_bbp_remove_topic_from_all_favorites().
	 */
	public function test_bbp_remove_topic_from_all_favorites() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_remove_topic_from_all_subscriptions
	 * @todo   Implement test_bbp_remove_topic_from_all_subscriptions().
	 */
	public function test_bbp_remove_topic_from_all_subscriptions() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_update_topic_forum_id
	 */
	public function test_bbp_update_topic_forum_id() {
		$f = $this->factory->forum->create();
		$t = $this->factory->topic->create( array(
			'post_parent' => $f,
			'topic_meta' => array(
				'forum_id' => $f,
			),
		) );

		$forum_id = bbp_get_topic_forum_id( $t );
		$this->assertSame( $f, $forum_id );

		$topic_parent = wp_get_post_parent_id( $t );
		$this->assertSame( $f, $topic_parent );

		$this->assertTrue( delete_post_meta_by_key( '_bbp_forum_id' ) );

		bbp_update_topic_forum_id( $t, $f );

		$forum_id = bbp_get_topic_forum_id( $t );
		$this->assertSame( $f, $forum_id );
	}

	/**
	 * @covers ::bbp_update_topic_topic_id
	 * @todo   Implement test_bbp_update_topic_topic_id().
	 */
	public function test_bbp_update_topic_topic_id() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_update_topic_revision_log
	 * @todo   Implement test_bbp_update_topic_revision_log().
	 */
	public function test_bbp_update_topic_revision_log() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_delete_topic
	 * @todo   Implement test_bbp_delete_topic().
	 */
	public function test_bbp_delete_topic() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_delete_topic_replies
	 */
	public function test_bbp_delete_topic_replies() {
		$f = $this->factory->forum->create();
		$t = $this->factory->topic->create( array(
			'post_parent' => $f,
			'topic_meta' => array(
				'forum_id' => $f,
			),
		) );
		$r = $this->factory->reply->create_many( 2, array(
			'post_parent' => $t,
			'reply_meta' => array(
				'forum_id' => $f,
				'topic_id' => $t,
			),
		) );

		$this->assertSame( 2, bbp_get_topic_reply_count( $t, true ) );

		bbp_delete_topic_replies( $t );

		$count = count( bbp_get_all_child_ids( $t, bbp_get_reply_post_type() ) );
		$this->assertSame( 0, ( $count ) );
	}

	/**
	 * @covers ::bbp_trash_topic
	 * @todo   Implement test_bbp_trash_topic().
	 */
	public function test_bbp_trash_topic() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_trash_topic_replies
	 * @todo   Implement test_bbp_trash_topic_replies().
	 */
	public function test_bbp_trash_topic_replies() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_spam_topic_replies
	 */
	public function test_bbp_spam_topic_replies_in_hidden_forum() {
		$forum_id = $this->factory->forum->create(
			array( 'post_status' => bbp_get_hidden_status_id() )
		);
		$topic_id = $this->factory->topic->create(
			array(
				'post_parent' => $forum_id,
				'topic_meta'  => array( 'forum_id' => $forum_id ),
			)
		);
		$reply_id = $this->factory->reply->create(
			array(
				'post_parent' => $topic_id,
				'reply_meta'  => array(
					'forum_id' => $forum_id,
					'topic_id' => $topic_id,
				),
			)
		);

		$this->set_current_user( 0 );
		bbp_spam_topic( $topic_id );

		$this->assertSame( bbp_get_spam_status_id(), get_post_status( $topic_id ) );
		$this->assertSame( bbp_get_trash_status_id(), get_post_status( $reply_id ) );
		$this->assertSame( array( $reply_id ), get_post_meta( $topic_id, '_bbp_pre_spammed_replies', true ) );
	}

	/**
	 * @covers ::bbp_untrash_topic
	 */
	public function test_bbp_untrash_topic() {
		$f = $this->factory->forum->create();
		$t = $this->factory->topic->create( array(
			'post_parent' => $f,
			'topic_meta' => array(
				'forum_id' => $f,
			),
		) );

		wp_trash_post( $t );

		wp_untrash_post( $t );

		$this->assertTrue( 'publish' === get_post_status( $t ) );
	}

	/**
	 * @covers ::bbp_untrash_topic_replies
	 */
	public function test_bbp_untrash_topic_replies() {
		$f = $this->factory->forum->create();
		$t = $this->factory->topic->create( array(
			'post_parent' => $f,
			'topic_meta' => array(
				'forum_id' => $f,
			),
		) );
		$r = $this->factory->reply->create_many( 2, array(
			'post_parent' => $t,
			'reply_meta' => array(
				'forum_id' => $f,
				'topic_id' => $t,
			),
		) );

		wp_trash_post( $t );

		wp_untrash_post( $t );

		$expected = [];
		$expected[] = get_post_status( $r[0] );
		$expected[] = get_post_status( $r[1] );

		$this->assertSame( [ 'publish', 'publish' ], $expected );
	}

	/**
	 * @covers ::bbp_deleted_topic
	 * @todo   Implement test_bbp_deleted_topic().
	 */
	public function test_bbp_deleted_topic() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_trashed_topic
	 * @todo   Implement test_bbp_trashed_topic().
	 */
	public function test_bbp_trashed_topic() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_untrashed_topic
	 * @todo   Implement test_bbp_untrashed_topic().
	 */
	public function test_bbp_untrashed_topic() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_get_topics_per_page
	 * @todo   Implement test_bbp_get_topics_per_page().
	 */
	public function test_bbp_get_topics_per_page() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_get_topics_per_rss_page
	 * @todo   Implement test_bbp_get_topics_per_rss_page().
	 */
	public function test_bbp_get_topics_per_rss_page() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_topic_content_autoembed
	 * @todo   Implement test_bbp_topic_content_autoembed().
	 */
	public function test_bbp_topic_content_autoembed() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_display_topics_feed_rss2
	 * @todo   Implement test_bbp_display_topics_feed_rss2().
	 */
	public function test_bbp_display_topics_feed_rss2() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_check_topic_edit
	 * @todo   Implement test_bbp_check_topic_edit().
	 */
	public function test_bbp_check_topic_edit() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}
}
