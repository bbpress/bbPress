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

	private function assert_topic_action( $function, $hook ) {
		$topic_id = $this->factory->topic->create();
		$seen     = array();
		$callback = function ( $id ) use ( &$seen ) {
			$seen[] = $id;
		};
		add_action( $hook, $callback );

		try {
			$this->assertFalse( $function( PHP_INT_MAX ) );
			$function( $topic_id );
			$this->assertSame( array( $topic_id ), $seen );
		} finally {
			remove_action( $hook, $callback );
		}
	}

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
	 */
	public function test_bbp_new_topic_handler() {
		$forum_id         = $this->factory->forum->create();
		$old_post         = $_POST;
		$old_request      = $_REQUEST;
		$old_server       = $_SERVER;
		$old_user         = get_current_user_id();
		$old_errors       = bbpress()->errors;
		$created_topic_id = 0;
		$redirect         = null;
		$record_topic     = function ( $topic_id ) use ( &$created_topic_id ) {
			$created_topic_id = $topic_id;
		};
		$prevent_redirect = function ( $location ) use ( &$redirect ) {
			$redirect = $location;
			throw new RuntimeException( 'New topic redirect.' );
		};
		$home_url = wp_parse_url( home_url( '/' ) );

		$_POST                   = array();
		$_REQUEST                = array();
		$_SERVER['HTTP_HOST']    = $home_url['host'] . ( isset( $home_url['port'] ) ? ':' . $home_url['port'] : '' );
		$_SERVER['REQUEST_URI']  = $home_url['path'];
		bbpress()->errors       = new WP_Error();
		add_action( 'bbp_new_topic', $record_topic );
		add_filter( 'wp_redirect', $prevent_redirect );

		try {
			$this->assertNull( bbp_new_topic_handler( 'invalid-action' ) );
			$this->assertNull( bbp_new_topic_handler( 'bbp-new-topic' ) );
			$this->assertContains( 'bbp_new_topic_nonce', bbpress()->errors->get_error_codes() );

			$admin_id = $this->factory->user->create( array( 'role' => 'administrator' ) );
			bbp_set_user_role( $admin_id, bbp_get_keymaster_role() );
			$this->set_current_user( $admin_id );
			bbpress()->errors = new WP_Error();
			$_POST = array(
				'bbp_forum_id'      => $forum_id,
				'bbp_topic_title'   => 'New topic',
				'bbp_topic_content' => 'Topic content',
			);
			$_REQUEST['_wpnonce'] = wp_create_nonce( 'bbp-new-topic' );

			try {
				bbp_new_topic_handler( 'bbp-new-topic' );
				$this->fail( 'A new topic should redirect.' );
			} catch ( RuntimeException $exception ) {
				if ( 'New topic redirect.' !== $exception->getMessage() ) {
					throw $exception;
				}
			}

			$this->assertSame( array(), bbpress()->errors->get_error_codes() );
			$this->assertGreaterThan( 0, $created_topic_id );
			$this->assertSame( $forum_id, wp_get_post_parent_id( $created_topic_id ) );
			$this->assertSame( $admin_id, (int) get_post_field( 'post_author', $created_topic_id ) );
			$this->assertSame( 'New topic', get_post_field( 'post_title', $created_topic_id ) );
			$this->assertSame( 'Topic content', get_post_field( 'post_content', $created_topic_id ) );
			$this->assertSame( bbp_get_topic_permalink( $created_topic_id ), $redirect );
		} finally {
			remove_filter( 'wp_redirect', $prevent_redirect );
			remove_action( 'bbp_new_topic', $record_topic );
			$_POST            = $old_post;
			$_REQUEST         = $old_request;
			$_SERVER          = $old_server;
			bbpress()->errors = $old_errors;
			$this->set_current_user( $old_user );
		}
	}

	/**
	 * @covers ::bbp_edit_topic_handler
	 */
	public function test_bbp_edit_topic_handler() {
		$forum_id         = $this->factory->forum->create();
		$admin_id         = $this->factory->user->create( array( 'role' => 'administrator' ) );
		$topic_id         = $this->factory->topic->create( array(
			'post_author' => $admin_id,
			'post_parent' => $forum_id,
			'topic_meta'  => array( 'forum_id' => $forum_id ),
		) );
		$old_post         = $_POST;
		$old_request      = $_REQUEST;
		$old_server       = $_SERVER;
		$old_user         = get_current_user_id();
		$old_errors       = bbpress()->errors;
		$redirect         = null;
		$edited_topic_id  = 0;
		$record_topic     = function ( $edited_id ) use ( &$edited_topic_id ) {
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
				'bbp_topic_id'      => $topic_id,
				'bbp_forum_id'      => $forum_id,
				'bbp_topic_title'   => 'Edited topic',
				'bbp_topic_content' => 'Edited content',
			);
			$_REQUEST['_wpnonce'] = wp_create_nonce( 'bbp-edit-topic_' . $topic_id );

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
	 */
	public function test_bbp_update_topic() {
		$old_user = get_current_user_id();
		$user_id  = $this->factory->user->create();
		$forum_id = $this->factory->forum->create();
		$topic_id = $this->factory->topic->create( array( 'post_parent' => $forum_id, 'post_author' => $user_id ) );
		update_post_meta( $topic_id, '_edit_lock', 'stale' );
		delete_post_meta( $topic_id, '_bbp_forum_id' );
		delete_post_meta( $topic_id, '_bbp_topic_id' );
		$this->set_current_user( $user_id );

		try {
			bbp_update_topic( $topic_id, $forum_id, array(), $user_id, true );
			$this->assertSame( (string) $user_id, get_post_meta( $topic_id, '_edit_last', true ) );
			$this->assertFalse( metadata_exists( 'post', $topic_id, '_edit_lock' ) );
			$this->assertSame( (string) $forum_id, get_post_meta( $topic_id, '_bbp_forum_id', true ) );
			$this->assertSame( (string) $topic_id, get_post_meta( $topic_id, '_bbp_topic_id', true ) );
			delete_post_meta( $topic_id, '_bbp_last_active_id' );
			delete_post_meta( $topic_id, '_bbp_reply_count' );
			bbp_update_topic( $topic_id, $forum_id, array(), $user_id, false );
			$this->assertSame( (string) $topic_id, get_post_meta( $topic_id, '_bbp_last_active_id', true ) );
			$this->assertSame( '0', get_post_meta( $topic_id, '_bbp_reply_count', true ) );
		} finally {
			$this->set_current_user( $old_user );
		}
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
	 */
	public function test_bbp_get_topic_statuses() {
		$statuses = bbp_get_topic_statuses();
		$this->assertSame( array( 'Open', 'Closed', 'Spam', 'Trash', 'Pending' ), array_values( $statuses ) );
		$this->assertSame( array( bbp_get_public_status_id(), bbp_get_closed_status_id(), bbp_get_spam_status_id(), bbp_get_trash_status_id(), bbp_get_pending_status_id() ), array_keys( $statuses ) );
	}

	/**
	 * @covers ::bbp_get_topic_types
	 */
	public function test_bbp_get_topic_types() {
		$this->assertSame( array( 'unstick' => 'Normal', 'stick' => 'Sticky', 'super' => 'Super Sticky' ), bbp_get_topic_types() );
	}

	/**
	 * @covers ::bbp_get_stickies
	 */
	public function test_bbp_get_stickies() {
		$forum_id = $this->factory->forum->create();
		$topic_id = $this->factory->topic->create( array( 'post_parent' => $forum_id ) );
		$this->assertSame( array(), bbp_get_stickies( $forum_id ) );
		update_post_meta( $forum_id, '_bbp_sticky_topics', array( (string) $topic_id, (string) $topic_id ) );
		$this->assertSame( array( $topic_id ), bbp_get_stickies( $forum_id ) );
	}

	/**
	 * @covers ::bbp_get_super_stickies
	 */
	public function test_bbp_get_super_stickies() {
		$topic_id = $this->factory->topic->create();
		update_option( '_bbp_super_sticky_topics', array( (string) $topic_id, (string) $topic_id ) );
		$this->assertSame( array( $topic_id ), bbp_get_super_stickies() );
		$this->assertSame( array( $topic_id ), bbp_get_stickies() );
	}

	/**
	 * @covers ::bbp_toggle_topic_handler
	 */
	public function test_bbp_toggle_topic_handler() {
		$forum_id         = $this->factory->forum->create();
		$topic_id         = $this->factory->topic->create( array( 'post_parent' => $forum_id ) );
		$old_get          = $_GET;
		$old_request      = $_REQUEST;
		$old_user         = get_current_user_id();
		$old_errors       = bbpress()->errors;
		$redirect         = null;
		$seen             = array();
		$prevent_redirect = function ( $location ) use ( &$redirect ) {
			$redirect = $location;
			throw new RuntimeException( 'Topic toggle redirect.' );
		};
		$record_toggle = function ( $status, $data, $action ) use ( &$seen ) {
			$seen[] = array( $status, $data, $action );
		};

		$_GET               = array();
		$_REQUEST           = array();
		bbpress()->errors   = new WP_Error();
		add_filter( 'wp_redirect', $prevent_redirect );
		add_action( 'bbp_toggle_topic_handler', $record_toggle, 10, 3 );

		try {
			$this->assertNull( bbp_toggle_topic_handler( 'bbp_toggle_topic_close' ) );
			$_GET['topic_id'] = $topic_id;
			$this->assertNull( bbp_toggle_topic_handler( 'invalid-action' ) );
			$this->assertTrue( bbp_is_topic_open( $topic_id ) );

			$_GET['topic_id'] = 999999;
			bbp_toggle_topic_handler( 'bbp_toggle_topic_close' );
			$this->assertContains( 'bbp_toggle_topic_missing', bbpress()->errors->get_error_codes() );

			$_GET['topic_id'] = $topic_id;
			$this->set_current_user( 0 );
			bbp_toggle_topic_handler( 'bbp_toggle_topic_close' );
			$this->assertContains( 'bbp_toggle_topic_permission', bbpress()->errors->get_error_codes() );
			$this->assertTrue( bbp_is_topic_open( $topic_id ) );

			$admin_id = $this->factory->user->create( array( 'role' => 'administrator' ) );
			bbp_set_user_role( $admin_id, bbp_get_keymaster_role() );
			$this->set_current_user( $admin_id );
			$_REQUEST['_wpnonce'] = wp_create_nonce( 'close-' . bbp_get_topic_post_type() . '_' . $topic_id );
			try {
				bbp_toggle_topic_handler( 'bbp_toggle_topic_close' );
				$this->fail( 'Closing the topic should redirect.' );
			} catch ( RuntimeException $exception ) {
				if ( 'Topic toggle redirect.' !== $exception->getMessage() ) {
					throw $exception;
				}
			}
			$this->assertTrue( bbp_is_topic_closed( $topic_id ) );
			$this->assertSame( bbp_get_topic_permalink( $topic_id ), $redirect );
			$this->assertCount( 1, $seen );
			$this->assertSame( $topic_id, $seen[0][1]['ID'] );
			$this->assertSame( 'bbp_toggle_topic_close', $seen[0][2] );
		} finally {
			remove_action( 'bbp_toggle_topic_handler', $record_toggle, 10 );
			remove_filter( 'wp_redirect', $prevent_redirect );
			$_GET             = $old_get;
			$_REQUEST         = $old_request;
			bbpress()->errors = $old_errors;
			$this->set_current_user( $old_user );
		}
	}

	/**
	 * @covers ::bbp_remove_topic_from_all_favorites
	 */
	public function test_bbp_remove_topic_from_all_favorites() {
		$topic_id = $this->factory->topic->create();
		$user_ids = $this->factory->user->create_many( 2 );
		foreach ( $user_ids as $user_id ) {
			bbp_add_user_favorite( $user_id, $topic_id );
		}
		$this->assertEqualSets( $user_ids, bbp_get_topic_favoriters( $topic_id ) );
		bbp_remove_topic_from_all_favorites( $topic_id );
		$this->assertEmpty( bbp_get_topic_favoriters( $topic_id ) );
	}

	/**
	 * @covers ::bbp_remove_topic_from_all_subscriptions
	 */
	public function test_bbp_remove_topic_from_all_subscriptions() {
		$topic_id = $this->factory->topic->create();
		$user_ids = $this->factory->user->create_many( 2 );
		foreach ( $user_ids as $user_id ) {
			bbp_add_user_topic_subscription( $user_id, $topic_id );
		}
		$this->assertEqualSets( $user_ids, bbp_get_topic_subscribers( $topic_id ) );
		bbp_remove_topic_from_all_subscriptions( $topic_id );
		$this->assertEmpty( bbp_get_topic_subscribers( $topic_id ) );
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
	 */
	public function test_bbp_update_topic_topic_id() {
		$topic_id = $this->factory->topic->create();
		delete_post_meta( $topic_id, '_bbp_topic_id' );
		$this->assertSame( $topic_id, bbp_update_topic_topic_id( $topic_id ) );
		$this->assertSame( (string) $topic_id, get_post_meta( $topic_id, '_bbp_topic_id', true ) );
	}

	/**
	 * @covers ::bbp_update_topic_revision_log
	 */
	public function test_bbp_update_topic_revision_log() {
		$topic_id = $this->factory->topic->create();
		$user_id  = $this->factory->user->create();
		$args     = array( 'topic_id' => $topic_id, 'author_id' => $user_id, 'revision_id' => 17, 'reason' => 'Clarify wording' );

		$this->assertNotFalse( bbp_update_topic_revision_log( $args ) );
		$log = bbp_get_topic_raw_revision_log( $topic_id );
		$this->assertSame( $user_id, $log[17]['author'] );
		$this->assertSame( 'Clarify wording', $log[17]['reason'] );
	}

	/**
	 * @covers ::bbp_delete_topic
	 */
	public function test_bbp_delete_topic() {
		$this->assert_topic_action( 'bbp_delete_topic', 'bbp_delete_topic' );
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
	 */
	public function test_bbp_trash_topic() {
		$this->assert_topic_action( 'bbp_trash_topic', 'bbp_trash_topic' );
	}

	/**
	 * @covers ::bbp_trash_topic_replies
	 */
	public function test_bbp_trash_topic_replies() {
		$topic_id = $this->factory->topic->create();
		$public   = $this->factory->reply->create( array( 'post_parent' => $topic_id ) );
		$trashed  = $this->factory->reply->create( array( 'post_parent' => $topic_id ) );
		wp_trash_post( $trashed );

		bbp_trash_topic_replies( $topic_id );
		$this->assertSame( bbp_get_trash_status_id(), get_post_status( $public ) );
		$this->assertSame( bbp_get_trash_status_id(), get_post_status( $trashed ) );
		$this->assertSame( array( $public ), get_post_meta( $topic_id, '_bbp_pre_trashed_replies', true ) );
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
	 */
	public function test_bbp_deleted_topic() {
		$this->assert_topic_action( 'bbp_deleted_topic', 'bbp_deleted_topic' );
	}

	/**
	 * @covers ::bbp_trashed_topic
	 */
	public function test_bbp_trashed_topic() {
		$this->assert_topic_action( 'bbp_trashed_topic', 'bbp_trashed_topic' );
	}

	/**
	 * @covers ::bbp_untrashed_topic
	 */
	public function test_bbp_untrashed_topic() {
		$this->assert_topic_action( 'bbp_untrashed_topic', 'bbp_untrashed_topic' );
	}

	/**
	 * @covers ::bbp_get_topics_per_page
	 */
	public function test_bbp_get_topics_per_page() {
		update_option( '_bbp_topics_per_page', 0 );
		$this->assertSame( 12, bbp_get_topics_per_page( 12 ) );
		update_option( '_bbp_topics_per_page', '22' );
		$this->assertSame( 22, bbp_get_topics_per_page() );
	}

	/**
	 * @covers ::bbp_get_topics_per_rss_page
	 */
	public function test_bbp_get_topics_per_rss_page() {
		update_option( '_bbp_topics_per_rss_page', 0 );
		$this->assertSame( 18, bbp_get_topics_per_rss_page( 18 ) );
		update_option( '_bbp_topics_per_rss_page', '30' );
		$this->assertSame( 30, bbp_get_topics_per_rss_page() );
	}

	/**
	 * @covers ::bbp_topic_content_autoembed
	 */
	public function test_bbp_topic_content_autoembed() {
		global $wp_embed;

		$callback = array( $wp_embed, 'autoembed' );
		$priority = has_filter( 'bbp_get_topic_content', $callback );
		remove_filter( 'bbp_get_topic_content', $callback, $priority );
		add_filter( 'bbp_use_autoembed', '__return_false' );

		try {
			bbp_topic_content_autoembed();
			$this->assertFalse( has_filter( 'bbp_get_topic_content', $callback ) );
			remove_filter( 'bbp_use_autoembed', '__return_false' );
			add_filter( 'bbp_use_autoembed', '__return_true' );
			bbp_topic_content_autoembed();
			$this->assertSame( 2, has_filter( 'bbp_get_topic_content', $callback ) );
		} finally {
			remove_filter( 'bbp_use_autoembed', '__return_false' );
			remove_filter( 'bbp_use_autoembed', '__return_true' );
			remove_filter( 'bbp_get_topic_content', $callback, 2 );
			if ( false !== $priority ) {
				add_filter( 'bbp_get_topic_content', $callback, $priority );
			}
		}
	}

	/**
	 * @covers ::bbp_display_topics_feed_rss2
	 */
	public function test_bbp_display_topics_feed_rss2() {
		require_once dirname( __DIR__, 3 ) . '/includes/feed-rss2-test.php';

		$feed  = new DOMDocument();
		$xml   = bbp_test_run_feed_rss2( 'topics' );
		$valid = $feed->loadXML( $xml );
		$this->assertTrue( $valid, $xml );

		$items = $feed->getElementsByTagName( 'item' );
		$this->assertSame( 2, $items->length );
		$by_title = array();
		foreach ( $items as $item ) {
			$title = $item->getElementsByTagName( 'title' )->item( 0 )->textContent;
			$by_title[ $title ] = $item;
		}
		$this->assertArrayHasKey( 'Feed Test Topic', $by_title );
		$this->assertArrayHasKey( 'Protected Feed Topic', $by_title );
		$this->assertStringContainsString( 'Feed test topic content', $by_title['Feed Test Topic']->textContent );
		$this->assertSame( 0, $by_title['Protected Feed Topic']->getElementsByTagName( 'description' )->length );
		$this->assertStringNotContainsString( 'Protected topic secret content', $xml );
	}

	/**
	 * @covers ::bbp_check_topic_edit
	 */
	public function test_bbp_check_topic_edit() {
		$topic_id         = $this->factory->topic->create();
		$old_user         = get_current_user_id();
		$old_topic_id     = bbpress()->current_topic_id;
		$redirect         = null;
		$prevent_redirect = function ( $location ) use ( &$redirect ) {
			$redirect = $location;
			throw new RuntimeException( 'Topic edit redirect.' );
		};

		bbpress()->current_topic_id = $topic_id;
		add_filter( 'wp_redirect', $prevent_redirect );

		try {
			$this->set_current_user( 0 );
			$this->assertNull( bbp_check_topic_edit() );
			$this->assertNull( $redirect );

			add_filter( 'bbp_is_topic_edit', '__return_true' );
			try {
				bbp_check_topic_edit();
				$this->fail( 'Anonymous topic editing should redirect.' );
			} catch ( RuntimeException $exception ) {
				if ( 'Topic edit redirect.' !== $exception->getMessage() ) {
					throw $exception;
				}
			}
			$this->assertSame( bbp_get_topic_permalink( $topic_id ), $redirect );

			$admin_id = $this->factory->user->create( array( 'role' => 'administrator' ) );
			bbp_set_user_role( $admin_id, bbp_get_keymaster_role() );
			$this->set_current_user( $admin_id );
			$redirect = null;
			$this->assertNull( bbp_check_topic_edit() );
			$this->assertNull( $redirect );
		} finally {
			remove_filter( 'bbp_is_topic_edit', '__return_true' );
			remove_filter( 'wp_redirect', $prevent_redirect );
			bbpress()->current_topic_id = $old_topic_id;
			$this->set_current_user( $old_user );
		}
	}
}
