<?php

/**
 * Tests for bbPress forum functions.
 *
 * @group forums
 * @group functions
 * @group forum
 */
class BBP_Tests_Forums_Functions_Forum extends BBP_UnitTestCase {

	/**
	 * Assert a forum lifecycle wrapper validates its target and fires its action.
	 *
	 * @param string $function Wrapper function.
	 * @param string $action   Action name.
	 */
	protected function assert_forum_action_dispatches( $function, $action ) {
		$forum_id = $this->factory->forum->create();
		$observed = array();
		$callback = function( $id ) use ( &$observed ) {
			$observed[] = $id;
		};

		add_action( $action, $callback );
		try {
			$this->assertFalse( call_user_func( $function, 0 ) );
			call_user_func( $function, $forum_id );
		} finally {
			remove_action( $action, $callback );
		}

		$this->assertSame( array( $forum_id ), $observed );
	}

	/**
	 * Submit a front-end forum form without allowing its redirect to exit PHPUnit.
	 *
	 * @param string $handler      Submission handler.
	 * @param string $action       Form action.
	 * @param string $nonce_action Nonce action.
	 * @param string $saved_action After-save action.
	 * @param array  $post         Form fields.
	 * @return array Saved forum IDs, redirect, and errors.
	 */
	protected function submit_forum_form( $handler, $action, $nonce_action, $saved_action, $post ) {
		$old_post    = $_POST;
		$old_request = $_REQUEST;
		$old_server  = $_SERVER;
		$old_errors  = bbpress()->errors;
		$home_url    = wp_parse_url( home_url( '/' ) );
		$saved_ids   = array();
		$redirect    = '';
		$on_save     = function( $forum_id ) use ( &$saved_ids ) {
			$saved_ids[] = $forum_id;
		};
		$prevent_redirect = function( $location ) use ( &$redirect ) {
			$redirect = $location;
			throw new RuntimeException( 'Forum form redirect.' );
		};

		$_SERVER['HTTP_HOST']   = $home_url['host'] . ( isset( $home_url['port'] ) ? ':' . $home_url['port'] : '' );
		$_SERVER['REQUEST_URI'] = $home_url['path'];
		$_POST                  = $post;
		$_REQUEST               = array( '_wpnonce' => wp_create_nonce( $nonce_action ) );
		bbpress()->errors       = new WP_Error();
		add_action( $saved_action, $on_save );
		add_filter( 'wp_redirect', $prevent_redirect );

		try {
			call_user_func( $handler, $action );
		} catch ( RuntimeException $exception ) {
			if ( 'Forum form redirect.' !== $exception->getMessage() ) {
				throw $exception;
			}
		} finally {
			$errors            = bbpress()->errors->get_error_codes();
			$_POST             = $old_post;
			$_REQUEST          = $old_request;
			$_SERVER           = $old_server;
			bbpress()->errors = $old_errors;
			remove_action( $saved_action, $on_save );
			remove_filter( 'wp_redirect', $prevent_redirect );
		}

		return array( $saved_ids, $redirect, $errors );
	}

	/**
	 * @group canonical
	 * @covers ::bbp_insert_forum
	 */
	public function test_bbp_insert_forum() {

		$c = $this->factory->forum->create( array(
			'post_title' => 'Category 1',
			'post_content' => 'Content of Category 1',
			'forum_meta' => array(
				'forum_type' => 'category',
				'status'     => 'open',
			),
		) );

		$f = $this->factory->forum->create( array(
			'post_title' => 'Forum 1',
			'post_content' => 'Content of Forum 1',
			'post_parent' => $c,
			'forum_meta' => array(
				'forum_id'   => $c,
				'forum_type' => 'forum',
				'status'     => 'open',
			),
		) );

		$now = time();
		$post_date = date( 'Y-m-d H:i:s', $now - 60 * 60 * 100 );

		$t = $this->factory->topic->create( array(
			'post_parent' => $f,
			'post_date' => $post_date,
			'topic_meta' => array(
				'forum_id' => $f,
			),
		) );

		$r = $this->factory->reply->create( array(
			'post_parent' => $t,
			'post_date' => $post_date,
			'reply_meta' => array(
				'forum_id' => $f,
				'topic_id' => $t,
			),
		) );

		// Get the category.
		$category = bbp_get_forum( $c );

		// Get the forum.
		$forum = bbp_get_forum( $f );

		// Category post.
		$this->assertSame( 'Category 1', bbp_get_forum_title( $c ) );
		$this->assertSame( 'Content of Category 1', bbp_get_forum_content( $c ) );
		$this->assertSame( 'open', bbp_get_forum_status( $c ) );
		$this->assertSame( 'category', bbp_get_forum_type( $c ) );
		$this->assertTrue( bbp_is_forum( $c ) );
		$this->assertTrue( bbp_is_forum_category( $c ) );
		$this->assertTrue( bbp_is_forum_open( $c ) );
		$this->assertTrue( bbp_is_forum_public( $c ) );
		$this->assertFalse( bbp_is_forum_closed( $c ) );
		$this->assertFalse( bbp_is_forum_hidden( $c ) );
		$this->assertFalse( bbp_is_forum_private( $c ) );
		$this->assertSame( 0, bbp_get_forum_parent_id( $c ) );
		$this->assertEquals( 'http://' . WP_TESTS_DOMAIN . '/?forum=' . $category->post_name, $category->guid );

		// Forum post.
		$this->assertSame( 'Forum 1', bbp_get_forum_title( $f ) );
		$this->assertSame( 'Content of Forum 1', bbp_get_forum_content( $f ) );
		$this->assertSame( 'open', bbp_get_forum_status( $f ) );
		$this->assertSame( 'forum', bbp_get_forum_type( $f ) );
		$this->assertTrue( bbp_is_forum( $f ) );
		$this->assertTrue( bbp_is_forum_open( $f ) );
		$this->assertTrue( bbp_is_forum_public( $f ) );
		$this->assertFalse( bbp_is_forum_closed( $f ) );
		$this->assertFalse( bbp_is_forum_hidden( $f ) );
		$this->assertFalse( bbp_is_forum_private( $f ) );
		$this->assertSame( $c, bbp_get_forum_parent_id( $f ) );
		$this->assertEquals( 'http://' . WP_TESTS_DOMAIN . '/?forum=' . $category->post_name . '/' . $forum->post_name, $forum->guid );

		// Category meta.
		$this->assertSame( 1, bbp_get_forum_subforum_count( $c, true ) );
		$this->assertSame( 0, bbp_get_forum_topic_count( $c, false, true ) );
		$this->assertSame( 1, bbp_get_forum_topic_count( $c, true, true ) );
		$this->assertSame( 0, bbp_get_forum_topic_count_hidden( $c, true, true ) );
		$this->assertSame( 0, bbp_get_forum_reply_count( $c, false, true ) );
		$this->assertSame( 1, bbp_get_forum_reply_count( $c, true, true ) );
		$this->assertSame( 0, bbp_get_forum_post_count( $c, false, true ) );
		$this->assertSame( 2, bbp_get_forum_post_count( $c, true, true ) );
		$this->assertSame( $t, bbp_get_forum_last_topic_id( $c ) );
		$this->assertSame( $r, bbp_get_forum_last_reply_id( $c ) );
		$this->assertSame( $r, bbp_get_forum_last_active_id( $c ) );
		$this->assertSame( '4 days, 4 hours ago', bbp_get_forum_last_active_time( $c ) );

		// Forum meta.
		$this->assertSame( 0, bbp_get_forum_subforum_count( $f, true ) );
		$this->assertSame( 1, bbp_get_forum_topic_count( $f, false, true ) );
		$this->assertSame( 1, bbp_get_forum_topic_count( $f, true, true ) );
		$this->assertSame( 0, bbp_get_forum_topic_count_hidden( $f, true, true ) );
		$this->assertSame( 1, bbp_get_forum_reply_count( $f, false, true ) );
		$this->assertSame( 1, bbp_get_forum_reply_count( $f, true, true ) );

		$this->assertSame( 2, bbp_get_forum_post_count( $f, false, true ) );
		$this->assertSame( 2, bbp_get_forum_post_count( $f, true, true ) );
		$this->assertSame( $t, bbp_get_forum_last_topic_id( $f ) );
		$this->assertSame( $r, bbp_get_forum_last_reply_id( $f ) );
		$this->assertSame( $r, bbp_get_forum_last_active_id( $f ) );
		$this->assertSame( '4 days, 4 hours ago', bbp_get_forum_last_active_time( $f ) );
	}

	/**
	 * @covers ::bbp_new_forum_handler
	 */
	public function test_bbp_new_forum_handler() {
		$parent_id = $this->factory->forum->create();
		$user_id   = $this->factory->user->create();
		bbp_set_user_role( $user_id, bbp_get_keymaster_role() );
		$this->set_current_user( $user_id );

		list( $saved_ids, $redirect, $errors ) = $this->submit_forum_form(
			'bbp_new_forum_handler',
			'bbp-new-forum',
			'bbp-new-forum',
			'bbp_new_forum_post_extras',
			array(
				'bbp_forum_parent_id' => $parent_id,
				'bbp_forum_title'     => 'Front-end new forum',
				'bbp_forum_content'   => 'New forum description',
			)
		);

		$this->assertSame( array(), $errors );
		$this->assertCount( 1, $saved_ids );
		$forum_id = $saved_ids[0];
		$this->assertSame( bbp_get_forum_post_type(), get_post_type( $forum_id ) );
		$this->assertSame( $parent_id, wp_get_post_parent_id( $forum_id ) );
		$this->assertSame( 'Front-end new forum', get_the_title( $forum_id ) );
		$this->assertSame( 'New forum description', get_post_field( 'post_content', $forum_id ) );
		$this->assertSame( bbp_get_forum_permalink( $forum_id ), $redirect );
	}

	/**
	 * @covers ::bbp_new_forum_handler
	 */
	public function test_bbp_new_forum_handler_rejects_invalid_nonce() {
		$user_id = $this->factory->user->create();
		bbp_set_user_role( $user_id, bbp_get_keymaster_role() );
		$this->set_current_user( $user_id );

		list( $saved_ids, $redirect, $errors ) = $this->submit_forum_form(
			'bbp_new_forum_handler',
			'bbp-new-forum',
			'invalid-new-forum-action',
			'bbp_new_forum_post_extras',
			array( 'bbp_forum_title' => 'Rejected forum' )
		);

		$this->assertSame( array(), $saved_ids );
		$this->assertSame( '', $redirect );
		$this->assertContains( 'bbp_new_forum_nonce', $errors );
	}

	/**
	 * @covers ::bbp_edit_forum_handler
	 */
	public function test_bbp_edit_forum_handler() {
		$forum_id = $this->factory->forum->create();
		$user_id  = $this->factory->user->create();
		bbp_set_user_role( $user_id, bbp_get_keymaster_role() );
		$this->set_current_user( $user_id );

		list( $saved_ids, $redirect, $errors ) = $this->submit_forum_form(
			'bbp_edit_forum_handler',
			'bbp-edit-forum',
			'bbp-edit-forum_' . $forum_id,
			'bbp_edit_forum_post_extras',
			array(
				'bbp_forum_id'      => $forum_id,
				'bbp_forum_title'   => 'Front-end edited forum',
				'bbp_forum_content' => 'Edited forum description',
			)
		);

		$this->assertSame( array(), $errors );
		$this->assertSame( array( $forum_id ), $saved_ids );
		$this->assertSame( 'Front-end edited forum', get_the_title( $forum_id ) );
		$this->assertSame( 'Edited forum description', get_post_field( 'post_content', $forum_id ) );
		$this->assertSame( $user_id, (int) get_post_meta( $forum_id, '_edit_last', true ) );
		$this->assertSame( bbp_get_forum_permalink( $forum_id ), $redirect );
	}

	/**
	 * @covers ::bbp_edit_forum_handler
	 */
	public function test_bbp_edit_forum_handler_rejects_invalid_nonce() {
		$forum_id = $this->factory->forum->create( array( 'post_title' => 'Unchanged forum' ) );
		$user_id  = $this->factory->user->create();
		bbp_set_user_role( $user_id, bbp_get_keymaster_role() );
		$this->set_current_user( $user_id );

		list( $saved_ids, $redirect, $errors ) = $this->submit_forum_form(
			'bbp_edit_forum_handler',
			'bbp-edit-forum',
			'invalid-edit-forum-action',
			'bbp_edit_forum_post_extras',
			array(
				'bbp_forum_id'    => $forum_id,
				'bbp_forum_title' => 'Rejected edit',
			)
		);

		$this->assertSame( array(), $saved_ids );
		$this->assertSame( '', $redirect );
		$this->assertContains( 'bbp_edit_forum_nonce', $errors );
		$this->assertSame( 'Unchanged forum', get_the_title( $forum_id ) );
	}

	/**
	 * @covers ::bbp_save_forum_extras
	 */
	public function test_bbp_save_forum_extras() {
		$forum_id = $this->factory->forum->create();
		$user_id  = $this->factory->user->create();
		$old_post = $_POST;
		bbp_set_user_role( $user_id, bbp_get_keymaster_role() );
		$this->set_current_user( $user_id );

		try {
			$_POST = array(
				'bbp_forum_status'     => 'closed',
				'bbp_forum_type'       => 'category',
				'bbp_forum_visibility' => bbp_get_hidden_status_id(),
			);
			bbp_save_forum_extras( $forum_id );
			$this->assertTrue( bbp_is_forum_closed( $forum_id ) );
			$this->assertTrue( bbp_is_forum_category( $forum_id ) );
			$this->assertTrue( bbp_is_forum_hidden( $forum_id, false ) );

			$_POST = array(
				'bbp_forum_status'     => 'open',
				'bbp_forum_type'       => 'forum',
				'bbp_forum_visibility' => bbp_get_public_status_id(),
			);
			bbp_save_forum_extras( $forum_id );
			$this->assertTrue( bbp_is_forum_open( $forum_id ) );
			$this->assertFalse( bbp_is_forum_category( $forum_id ) );
			$this->assertTrue( bbp_is_forum_public( $forum_id, false ) );
		} finally {
			$_POST = $old_post;
		}
	}

	/**
	 * @covers ::bbp_remove_forum_from_all_subscriptions
	 */
	public function test_bbp_remove_forum_from_all_subscriptions() {
		$forum_id = $this->factory->forum->create();
		$user_ids = $this->factory->user->create_many( 2 );

		foreach ( $user_ids as $user_id ) {
			$this->assertTrue( bbp_add_user_subscription( $user_id, $forum_id ) );
			$this->assertTrue( bbp_is_user_subscribed( $user_id, $forum_id ) );
		}

		bbp_remove_forum_from_all_subscriptions( $forum_id );
		foreach ( $user_ids as $user_id ) {
			$this->assertFalse( bbp_is_user_subscribed( $user_id, $forum_id ) );
		}
	}

	/**
	 * @covers ::bbp_update_forum
	 * @covers ::bbp_update_forum_walker
	 */
	public function test_bbp_update_forum() {
		$root_id  = $this->factory->forum->create();
		$forum_id = $this->factory->forum->create(
			array(
				'post_parent' => $root_id,
			)
		);
		$active_time  = '2026-09-08 12:00:00';
		$forum_updates = array();
		$callback      = function( $last_topic_id, $updated_forum_id ) use ( &$forum_updates ) {
			$forum_updates[] = $updated_forum_id;
			return $last_topic_id;
		};

		add_filter( 'bbp_update_forum_last_topic_id', $callback, 10, 2 );

		bbp_update_forum(
			array(
				'forum_id'         => $forum_id,
				'post_parent'      => $root_id,
				'last_topic_id'    => 100,
				'last_reply_id'    => 101,
				'last_active_id'   => 101,
				'last_active_time' => $active_time
			)
		);

		remove_filter( 'bbp_update_forum_last_topic_id', $callback, 10 );

		$this->assertSame( array( $forum_id, $root_id ), $forum_updates );
		$this->assertSame( 100, bbp_get_forum_last_topic_id( $forum_id ) );
		$this->assertSame( 100, bbp_get_forum_last_topic_id( $root_id ) );
		$this->assertSame( 101, bbp_get_forum_last_reply_id( $forum_id ) );
		$this->assertSame( 101, bbp_get_forum_last_reply_id( $root_id ) );
		$this->assertSame( $active_time, get_post_meta( $forum_id, '_bbp_last_active_time', true ) );
		$this->assertSame( $active_time, get_post_meta( $root_id, '_bbp_last_active_time', true ) );
		$this->assertFalse(
			bbp_update_forum_walker(
				array(
					'forum_id'    => $root_id,
					'post_parent' => 0
				)
			)
		);
	}

	/**
	 * @covers ::bbp_check_forum_edit
	 */
	public function test_bbp_check_forum_edit() {
		$forum_id = $this->factory->forum->create();
		$redirect = '';
		$this->set_current_user( 0 );

		$editing = '__return_true';
		$forum_filter = function( $resolved_id, $requested_id ) use ( $forum_id ) {
			return empty( $requested_id ) ? $forum_id : $resolved_id;
		};
		$prevent_redirect = function( $location ) use ( &$redirect ) {
			$redirect = $location;
			throw new RuntimeException( 'Forum edit redirect.' );
		};
		add_filter( 'bbp_is_forum_edit', $editing );
		add_filter( 'bbp_get_forum_id', $forum_filter, 10, 2 );
		add_filter( 'wp_redirect', $prevent_redirect );

		try {
			bbp_check_forum_edit();
			$this->fail( 'An unauthorized forum edit should redirect.' );
		} catch ( RuntimeException $exception ) {
			$this->assertSame( 'Forum edit redirect.', $exception->getMessage() );
		} finally {
			remove_filter( 'bbp_is_forum_edit', $editing );
			remove_filter( 'bbp_get_forum_id', $forum_filter );
			remove_filter( 'wp_redirect', $prevent_redirect );
		}

		$this->assertSame( bbp_get_forum_permalink( $forum_id ), $redirect );
	}
	/**
	 * @covers ::bbp_delete_forum_topics
	 * @ticket BBP2944
	 */
	public function test_bbp_delete_forum_topics() {
		$bbp_db   = bbp_db();
		$forum_id = $this->factory->forum->create();
		$topic_id = $this->factory->topic->create( array(
			'post_parent' => $forum_id,
			'topic_meta'  => array( 'forum_id' => $forum_id ),
		) );
		$reply_id = $this->factory->reply->create( array(
			'post_parent' => $topic_id,
			'reply_meta'  => array(
				'forum_id' => $forum_id,
				'topic_id' => $topic_id,
			),
		) );

		wp_delete_post( $forum_id, true );

		foreach ( array( $forum_id, $topic_id, $reply_id ) as $post_id ) {
			$this->assertNull( get_post( $post_id ) );

			$meta_keys = $bbp_db->get_col( $bbp_db->prepare( "SELECT meta_key FROM {$bbp_db->postmeta} WHERE post_id = %d", $post_id ) );
			$this->assertSame( array(), $meta_keys, "Orphaned metadata for post {$post_id}" );
		}
	}

	/**
	 * @covers ::bbp_delete_forum_topics
	 * @covers ::bbp_delete_topic_replies
	 */
	public function test_bbp_delete_restricted_forum_topics_without_current_user() {
		foreach ( array( bbp_get_private_status_id(), bbp_get_hidden_status_id() ) as $status ) {
			$forum_id = $this->factory->forum->create( array( 'post_status' => $status ) );
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
			wp_delete_post( $forum_id, true );

			$this->assertNull( get_post( $forum_id ) );
			$this->assertNull( get_post( $topic_id ) );
			$this->assertNull( get_post( $reply_id ) );
		}
	}

	/**
	 * @covers ::bbp_trash_forum_topics
	 */
	public function test_bbp_trash_forum_topics() {
		$forum_id = $this->factory->forum->create();
		$topic_id = $this->factory->topic->create( array(
			'post_parent' => $forum_id,
			'topic_meta'  => array( 'forum_id' => $forum_id ),
		) );
		$spam_id = $this->factory->topic->create( array(
			'post_parent' => $forum_id,
			'post_status' => bbp_get_spam_status_id(),
			'topic_meta'  => array( 'forum_id' => $forum_id ),
		) );

		bbp_trash_forum_topics( $forum_id );

		$this->assertSame( 'trash', get_post_status( $topic_id ) );
		$this->assertSame( bbp_get_spam_status_id(), get_post_status( $spam_id ) );
		$this->assertSame( array( $topic_id ), get_post_meta( $forum_id, '_bbp_pre_trashed_topics', true ) );
	}

	/**
	 * @covers ::bbp_untrash_forum_topics
	 */
	public function test_bbp_untrash_forum_topics() {
		$forum_id = $this->factory->forum->create();
		$topic_id = $this->factory->topic->create( array(
			'post_parent' => $forum_id,
			'topic_meta'  => array( 'forum_id' => $forum_id ),
		) );
		$already_trashed_id = $this->factory->topic->create( array(
			'post_parent' => $forum_id,
			'topic_meta'  => array( 'forum_id' => $forum_id ),
		) );
		wp_trash_post( $already_trashed_id );
		bbp_trash_forum_topics( $forum_id );

		$this->assertSame( 'trash', get_post_status( $topic_id ) );
		bbp_untrash_forum_topics( $forum_id );
		$this->assertSame( bbp_get_public_status_id(), get_post_status( $topic_id ) );
		$this->assertSame( 'trash', get_post_status( $already_trashed_id ) );
	}

	/**
	 * @covers ::bbp_trash_forum
	 * @covers ::bbp_untrash_forum
	 * @covers ::bbp_trash_forum_topics
	 * @covers ::bbp_untrash_forum_topics
	 * @covers ::bbp_remove_forum_from_all_subscriptions
	 */
	public function test_forum_trash_restores_only_its_topics_and_removes_subscriptions() {
		$forum_id = $this->factory->forum->create();
		$topic_id = $this->factory->topic->create( array(
			'post_parent' => $forum_id,
			'topic_meta'  => array( 'forum_id' => $forum_id ),
		) );
		$already_trashed_id = $this->factory->topic->create( array(
			'post_parent' => $forum_id,
			'topic_meta'  => array( 'forum_id' => $forum_id ),
		) );
		$user_id = $this->factory->user->create();
		$this->assertTrue( bbp_add_user_subscription( $user_id, $forum_id ) );
		wp_trash_post( $already_trashed_id );

		wp_trash_post( $forum_id );
		$this->assertSame( 'trash', get_post_status( $forum_id ) );
		$this->assertSame( 'trash', get_post_status( $topic_id ) );
		$this->assertFalse( bbp_is_user_subscribed( $user_id, $forum_id ) );

		wp_untrash_post( $forum_id );
		$this->assertSame( bbp_get_public_status_id(), get_post_status( $forum_id ) );
		$this->assertSame( bbp_get_public_status_id(), get_post_status( $topic_id ) );
		$this->assertSame( 'trash', get_post_status( $already_trashed_id ) );
	}

	/**
	 * @covers ::bbp_delete_forum
	 */
	public function test_bbp_delete_forum() {
		$this->assert_forum_action_dispatches( 'bbp_delete_forum', 'bbp_delete_forum' );
	}

	/**
	 * @covers ::bbp_trash_forum
	 */
	public function test_bbp_trash_forum() {
		$this->assert_forum_action_dispatches( 'bbp_trash_forum', 'bbp_trash_forum' );
	}

	/**
	 * @covers ::bbp_untrash_forum
	 */
	public function test_bbp_untrash_forum() {
		$this->assert_forum_action_dispatches( 'bbp_untrash_forum', 'bbp_untrash_forum' );
	}

	/**
	 * @covers ::bbp_deleted_forum
	 */
	public function test_bbp_deleted_forum() {
		$forum_id = $this->factory->forum->create();
		$forum    = get_post( $forum_id );
		$observed = array();
		$callback = function( $id, $post ) use ( &$observed ) {
			$observed[] = array( $id, $post );
		};

		add_action( 'bbp_deleted_forum', $callback, 10, 2 );
		try {
			$this->assertFalse( bbp_deleted_forum( 0 ) );
			bbp_deleted_forum( $forum_id, $forum );
		} finally {
			remove_action( 'bbp_deleted_forum', $callback );
		}

		$this->assertSame( array( array( $forum_id, $forum ) ), $observed );
	}

	/**
	 * @covers ::bbp_trashed_forum
	 */
	public function test_bbp_trashed_forum() {
		$this->assert_forum_action_dispatches( 'bbp_trashed_forum', 'bbp_trashed_forum' );
	}

	/**
	 * @covers ::bbp_untrashed_forum
	 */
	public function test_bbp_untrashed_forum() {
		$this->assert_forum_action_dispatches( 'bbp_untrashed_forum', 'bbp_untrashed_forum' );
	}
}
