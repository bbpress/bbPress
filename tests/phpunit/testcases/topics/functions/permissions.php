<?php

/**
 * Tests for topic handler permissions.
 *
 * @group topics
 * @group functions
 * @group capabilities
 */
class BBP_Tests_Topics_Functions_Permissions extends BBP_UnitTestCase {

	protected $old_post;
	protected $old_get;
	protected $old_request;
	protected $old_server;
	protected $old_errors;

	public function setUp(): void {
		parent::setUp();

		$this->old_post    = $_POST;
		$this->old_get     = $_GET;
		$this->old_request = $_REQUEST;
		$this->old_server  = $_SERVER;
		$this->old_errors  = bbpress()->errors;
	}

	public function tearDown(): void {
		$_POST            = $this->old_post;
		$_GET             = $this->old_get;
		$_REQUEST         = $this->old_request;
		$_SERVER          = $this->old_server;
		bbpress()->errors = $this->old_errors;

		parent::tearDown();
	}

	protected function submit_topic_edit( $topic_id, $forum_id, $content ) {
		$home_url             = wp_parse_url( home_url( '/' ) );
		$_SERVER['HTTP_HOST'] = $home_url['host'];

		if ( isset( $home_url['port'] ) ) {
			$_SERVER['HTTP_HOST'] .= ':' . $home_url['port'];
		}

		$_SERVER['REQUEST_URI']     = $home_url['path'];
		$_POST['bbp_topic_id']      = $topic_id;
		$_POST['bbp_forum_id']      = $forum_id;
		$_POST['bbp_topic_title']   = bbp_get_topic_title( $topic_id );
		$_POST['bbp_topic_content'] = $content;
		$_REQUEST['_wpnonce']       = wp_create_nonce( 'bbp-edit-topic_' . $topic_id );

		$did_redirect     = false;
		$prevent_redirect = function() use ( &$did_redirect ) {
			$did_redirect = true;
			throw new RuntimeException( 'Topic edit redirect.' );
		};

		add_filter( 'wp_redirect', $prevent_redirect );

		try {
			bbp_edit_topic_handler( 'bbp-edit-topic' );
		} catch ( RuntimeException $exception ) {
			if ( 'Topic edit redirect.' !== $exception->getMessage() ) {
				throw $exception;
			}
		} finally {
			remove_filter( 'wp_redirect', $prevent_redirect );
		}

		return $did_redirect;
	}

	/**
	 * @covers ::bbp_edit_topic_handler
	 */
	public function test_participant_can_edit_own_closed_topic_in_closed_forum_theme_side() {
		$user_id  = $this->factory->user->create( array( 'role' => bbp_get_participant_role() ) );
		$forum_id = $this->factory->forum->create();
		$topic_id = $this->factory->topic->create(
			array(
				'post_author' => $user_id,
				'post_parent' => $forum_id,
				'topic_meta'  => array( 'forum_id' => $forum_id ),
			)
		);

		bbp_close_topic( $topic_id );
		bbp_close_forum( $forum_id );
		$this->set_current_user( $user_id );
		bbpress()->errors = new WP_Error();

		$this->assertTrue( current_user_can( 'edit_topic', $topic_id ) );
		$this->assertFalse( current_user_can( 'edit_forum', $forum_id ) );

		$did_redirect = $this->submit_topic_edit( $topic_id, $forum_id, 'Participant edit in closed content.' );

		$this->assertSame( array(), bbpress()->errors->get_error_codes() );
		$this->assertSame( 'Participant edit in closed content.', get_post_field( 'post_content', $topic_id ) );
		$this->assertTrue( $did_redirect );
	}

	/**
	 * @covers ::bbp_edit_topic_handler
	 */
	public function test_forum_moderator_can_edit_closed_topic_in_closed_forum_theme_side() {
		$user_id        = $this->factory->user->create( array( 'role' => bbp_get_participant_role() ) );
		$topic_owner_id = $this->factory->user->create( array( 'role' => bbp_get_participant_role() ) );
		$forum_id       = $this->factory->forum->create();
		$topic_id       = $this->factory->topic->create(
			array(
				'post_author' => $topic_owner_id,
				'post_parent' => $forum_id,
				'topic_meta'  => array( 'forum_id' => $forum_id ),
			)
		);

		bbp_add_moderator( $forum_id, $user_id );
		bbp_close_topic( $topic_id );
		bbp_close_forum( $forum_id );
		$this->set_current_user( $user_id );
		bbpress()->errors = new WP_Error();

		$this->assertTrue( current_user_can( 'moderate', $topic_id ) );
		$this->assertTrue( current_user_can( 'edit_forum', $forum_id ) );

		$did_redirect = $this->submit_topic_edit( $topic_id, $forum_id, 'Forum moderator edit in closed content.' );

		$this->assertSame( array(), bbpress()->errors->get_error_codes() );
		$this->assertSame( 'Forum moderator edit in closed content.', get_post_field( 'post_content', $topic_id ) );
		$this->assertTrue( $did_redirect );
	}

	/**
	 * @covers ::bbp_edit_topic_handler
	 */
	public function test_keymaster_cannot_edit_topic_with_invalid_forum_id() {
		$user_id  = $this->factory->user->create();
		$forum_id = $this->factory->forum->create();
		$topic_id = $this->factory->topic->create(
			array(
				'post_parent' => $forum_id,
				'topic_meta'  => array( 'forum_id' => $forum_id ),
			)
		);

		bbp_set_user_role( $user_id, bbp_get_keymaster_role() );
		$this->set_current_user( $user_id );
		$cases = array(
			'nonnumeric'  => array( 'not-a-number', '<strong>Error</strong>: Forum ID must be a number.' ),
			'numeric-zero' => array( '00', '<strong>Error</strong>: Forum ID is missing.' ),
			'negative'    => array( -1, '<strong>Error</strong>: Forum ID cannot be a negative number.' ),
			'nonexistent' => array( PHP_INT_MAX, '<strong>Error</strong>: Forum does not exist.' ),
		);

		foreach ( $cases as $case => $configuration ) {
			bbpress()->errors = new WP_Error();

			$did_redirect = $this->submit_topic_edit( $topic_id, $configuration[0], 'Invalid forum ID edit: ' . $case );

			$this->assertContains( 'bbp_topic_forum_id', bbpress()->errors->get_error_codes(), $case );
			$this->assertSame( $configuration[1], bbpress()->errors->get_error_message( 'bbp_topic_forum_id' ), $case );
			$this->assertSame( $forum_id, bbp_get_topic_forum_id( $topic_id ), $case );
			$this->assertFalse( $did_redirect, $case );
		}
	}

	/**
	 * @covers ::bbp_edit_topic_handler
	 */
	public function test_forum_moderator_cannot_move_topic_to_closed_forum() {
		$user_id         = $this->factory->user->create( array( 'role' => bbp_get_participant_role() ) );
		$topic_owner_id  = $this->factory->user->create( array( 'role' => bbp_get_participant_role() ) );
		$source_forum_id = $this->factory->forum->create();
		$closed_forum_id = $this->factory->forum->create();
		$topic_id        = $this->factory->topic->create(
			array(
				'post_author' => $topic_owner_id,
				'post_parent' => $source_forum_id,
				'topic_meta'  => array( 'forum_id' => $source_forum_id ),
			)
		);

		bbp_add_moderator( $source_forum_id, $user_id );
		bbp_close_forum( $closed_forum_id );
		$this->set_current_user( $user_id );
		bbpress()->errors = new WP_Error();

		$this->assertTrue( current_user_can( 'edit_forum', $source_forum_id ) );
		$this->assertFalse( current_user_can( 'edit_forum', $closed_forum_id ) );

		$did_redirect = $this->submit_topic_edit( $topic_id, $closed_forum_id, 'Move to closed forum.' );

		$this->assertContains( 'bbp_edit_topic_forum_closed', bbpress()->errors->get_error_codes() );
		$this->assertSame( $source_forum_id, bbp_get_topic_forum_id( $topic_id ) );
		$this->assertFalse( $did_redirect );
	}


	/**
	 * @covers ::bbp_new_topic_handler
	 */
	public function test_closed_stacks_topic_errors_before_insert() {
		$user_id  = $this->factory->user->create();
		$forum_id = $this->factory->forum->create();
		$home_url = wp_parse_url( home_url( '/' ) );
		$inserted = false;
		$track_insert = function() use ( &$inserted ) {
			$inserted = true;
		};

		bbp_set_user_role( $user_id, bbp_get_participant_role() );
		$this->set_current_user( $user_id );
		update_option( '_bbp_forums_status', 'closed' );
		bbpress()->errors = new WP_Error();
		$_SERVER['HTTP_HOST'] = $home_url['host'];
		if ( isset( $home_url['port'] ) ) {
			$_SERVER['HTTP_HOST'] .= ':' . $home_url['port'];
		}
		$_SERVER['REQUEST_URI'] = $home_url['path'];
		$_REQUEST['_wpnonce']   = wp_create_nonce( 'bbp-new-topic' );
		$_POST['bbp_forum_id']  = $forum_id;
		add_filter( 'bbp_new_topic_pre_insert', $track_insert );
		$this->assertFalse( current_user_can( 'publish_topics' ) );

		try {
			bbp_new_topic_handler( 'bbp-new-topic' );
		} finally {
			remove_filter( 'bbp_new_topic_pre_insert', $track_insert );
		}

		$this->assertContains( 'bbp_topic_permission', bbpress()->errors->get_error_codes(), implode( ', ', bbpress()->errors->get_error_codes() ) );
		$this->assertContains( 'bbp_new_topic_forums_closed', bbpress()->errors->get_error_codes() );
		$this->assertContains( 'bbp_topic_title', bbpress()->errors->get_error_codes() );
		$this->assertContains( 'bbp_topic_content', bbpress()->errors->get_error_codes() );
		$this->assertFalse( $inserted );
	}

	protected function submit_topic_split( $reply_id, $source_topic_id, $split_option, $destination_title = null ) {
		$home_url             = wp_parse_url( home_url( '/' ) );
		$_SERVER['HTTP_HOST'] = $home_url['host'];

		if ( isset( $home_url['port'] ) ) {
			$_SERVER['HTTP_HOST'] .= ':' . $home_url['port'];
		}

		$_SERVER['REQUEST_URI']        = $home_url['path'];
		$_POST['bbp_reply_id']          = $reply_id;
		$_POST['bbp_topic_split_option'] = $split_option;
		$_REQUEST['_wpnonce']           = wp_create_nonce( 'bbp-split-topic_' . $source_topic_id );

		if ( null !== $destination_title ) {
			$_POST['bbp_topic_split_destination_title'] = $destination_title;
		}

		$did_redirect     = false;
		$prevent_redirect = function() use ( &$did_redirect ) {
			$did_redirect = true;
			throw new RuntimeException( 'Topic split redirect.' );
		};

		add_filter( 'wp_redirect', $prevent_redirect );

		try {
			bbp_split_topic_handler( 'bbp-split-topic' );
		} catch ( RuntimeException $exception ) {
			if ( 'Topic split redirect.' !== $exception->getMessage() ) {
				throw $exception;
			}
		}

		remove_filter( 'wp_redirect', $prevent_redirect );

		return $did_redirect;
	}

	protected function submit_topic_merge( $source_topic_id, $destination_topic_id = null ) {
		$home_url             = wp_parse_url( home_url( '/' ) );
		$_SERVER['HTTP_HOST'] = $home_url['host'];

		if ( isset( $home_url['port'] ) ) {
			$_SERVER['HTTP_HOST'] .= ':' . $home_url['port'];
		}

		$_SERVER['REQUEST_URI'] = $home_url['path'];
		$_POST['bbp_topic_id']  = $source_topic_id;
		$_REQUEST['_wpnonce']  = wp_create_nonce( 'bbp-merge-topic_' . $source_topic_id );

		if ( null !== $destination_topic_id ) {
			$_POST['bbp_destination_topic'] = $destination_topic_id;
		} else {
			unset( $_POST['bbp_destination_topic'] );
		}

		$did_redirect     = false;
		$prevent_redirect = function() use ( &$did_redirect ) {
			$did_redirect = true;
			throw new RuntimeException( 'Topic merge redirect.' );
		};

		add_filter( 'wp_redirect', $prevent_redirect );

		try {
			bbp_merge_topic_handler( 'bbp-merge-topic' );
		} catch ( RuntimeException $exception ) {
			if ( 'Topic merge redirect.' !== $exception->getMessage() ) {
				throw $exception;
			}
		}

		remove_filter( 'wp_redirect', $prevent_redirect );

		return $did_redirect;
	}

	/**
	 * @covers ::bbp_merge_topic_handler
	 */
	public function test_merge_moves_replies_with_non_public_statuses() {
		$user_id              = $this->factory->user->create();
		$source_forum_id      = $this->factory->forum->create();
		$destination_forum_id = $this->factory->forum->create();
		$source_topic_id      = $this->factory->topic->create( array( 'post_parent' => $source_forum_id ) );
		$destination_topic_id = $this->factory->topic->create( array( 'post_parent' => $destination_forum_id ) );
		$reply_ids            = array();

		foreach ( array( bbp_get_public_status_id(), bbp_get_pending_status_id(), bbp_get_spam_status_id(), bbp_get_trash_status_id() ) as $status ) {
			$reply_ids[ $status ] = $this->factory->reply->create( array(
				'post_parent' => $source_topic_id,
				'post_status' => $status,
				'reply_meta'  => array(
					'forum_id' => $source_forum_id,
					'topic_id' => $source_topic_id,
				),
			) );
		}

		bbp_set_user_role( $user_id, bbp_get_keymaster_role() );
		$this->set_current_user( $user_id );
		bbpress()->errors = new WP_Error();

		$this->assertTrue( $this->submit_topic_merge( $source_topic_id, $destination_topic_id ) );

		foreach ( $reply_ids as $status => $reply_id ) {
			$this->assertSame( $status, get_post_status( $reply_id ) );
			$this->assertSame( $destination_topic_id, wp_get_post_parent_id( $reply_id ) );
			$this->assertSame( $destination_topic_id, bbp_get_reply_topic_id( $reply_id ) );
			$this->assertSame( $destination_forum_id, bbp_get_reply_forum_id( $reply_id ) );
		}

		$this->assertSame( 2, bbp_get_topic_reply_count( $destination_topic_id, true ) );
		$this->assertSame( 3, bbp_get_topic_reply_count_hidden( $destination_topic_id, true ) );
	}

	/**
	 * @covers ::bbp_merge_topic_handler
	 */
	public function test_moderator_cannot_merge_topic_into_itself() {
		$user_id  = $this->factory->user->create();
		$forum_id = $this->factory->forum->create();
		$topic_id = $this->factory->topic->create( array( 'post_parent' => $forum_id ) );
		$did_run  = false;
		$callback = function() use ( &$did_run ) {
			$did_run = true;
			throw new RuntimeException( 'Unexpected topic merge.' );
		};

		bbp_set_user_role( $user_id, bbp_get_moderator_role() );
		$this->set_current_user( $user_id );
		bbpress()->errors = new WP_Error();
		add_action( 'bbp_merge_topic', $callback, 1 );

		try {
			$did_redirect = $this->submit_topic_merge( $topic_id, $topic_id );
		} finally {
			remove_action( 'bbp_merge_topic', $callback, 1 );
		}

		$this->assertContains( 'bbp_merge_topic_destination_same', bbpress()->errors->get_error_codes() );
		$this->assertFalse( $did_run );
		$this->assertFalse( $did_redirect );
		$this->assertSame( bbp_get_topic_post_type(), get_post_type( $topic_id ) );
	}

	/**
	 * @covers ::bbp_merge_topic_handler
	 */
	public function test_moderator_cannot_merge_without_destination_topic() {
		$user_id  = $this->factory->user->create();
		$forum_id = $this->factory->forum->create();
		$topic_id = $this->factory->topic->create( array( 'post_parent' => $forum_id ) );

		bbp_set_user_role( $user_id, bbp_get_moderator_role() );
		$this->set_current_user( $user_id );
		bbpress()->errors = new WP_Error();
		$warnings = array();
		set_error_handler( function( $severity, $message ) use ( &$warnings ) {
			if ( E_WARNING === $severity ) {
				$warnings[] = $message;
				return true;
			}
			return false;
		} );

		try {
			$did_redirect = $this->submit_topic_merge( $topic_id );
		} finally {
			restore_error_handler();
		}

		$this->assertContains( 'bbp_merge_topic_destination_not_found', bbpress()->errors->get_error_codes() );
		$this->assertSame( array(), $warnings );
		$this->assertFalse( $did_redirect );
		$this->assertSame( bbp_get_topic_post_type(), get_post_type( $topic_id ) );
	}

	/**
	 * @covers ::bbp_merge_topic_handler
	 */
	public function test_moderator_can_merge_into_another_topic() {
		$user_id              = $this->factory->user->create();
		$forum_id             = $this->factory->forum->create();
		$source_topic_id      = $this->factory->topic->create( array( 'post_parent' => $forum_id ) );
		$destination_topic_id = $this->factory->topic->create( array( 'post_parent' => $forum_id ) );

		bbp_set_user_role( $user_id, bbp_get_moderator_role() );
		$this->set_current_user( $user_id );
		bbpress()->errors = new WP_Error();

		$did_redirect = $this->submit_topic_merge( $source_topic_id, $destination_topic_id );

		$this->assertSame( array(), bbpress()->errors->get_error_codes() );
		$this->assertSame( bbp_get_reply_post_type(), get_post_type( $source_topic_id ) );
		$this->assertSame( $destination_topic_id, wp_get_post_parent_id( $source_topic_id ) );
		$this->assertTrue( $did_redirect );
	}

	/**
	 * @covers ::bbp_merge_topic_handler
	 * @covers ::wp_old_slug_redirect
	 */
	public function test_merge_redirects_source_topic_slugs_to_destination_topic() {
		$user_id              = $this->factory->user->create();
		$forum_id             = $this->factory->forum->create();
		$source_topic_id      = $this->factory->topic->create( array(
			'post_name'   => 'source-topic-slug',
			'post_parent' => $forum_id,
		) );
		$destination_topic_id = $this->factory->topic->create( array(
			'post_name'   => 'destination-topic-slug',
			'post_parent' => $forum_id,
		) );

		add_post_meta( $source_topic_id, '_wp_old_slug', 'earlier-source-topic-slug' );
		add_post_meta( $destination_topic_id, '_wp_old_slug', 'earlier-destination-topic-slug' );
		bbp_set_user_role( $user_id, bbp_get_moderator_role() );
		$this->set_current_user( $user_id );
		bbpress()->errors = new WP_Error();

		$this->assertTrue( $this->submit_topic_merge( $source_topic_id, $destination_topic_id ) );
		$this->assertEqualSets(
			array( 'source-topic-slug', 'earlier-source-topic-slug', 'earlier-destination-topic-slug' ),
			get_post_meta( $destination_topic_id, '_wp_old_slug', false )
		);

		$redirect_url = null;
		$filter       = function( $url ) use ( &$redirect_url ) {
			$redirect_url = $url;
			return false;
		};
		add_filter( 'old_slug_redirect_url', $filter );
		set_query_var( 'post_type', bbp_get_topic_post_type() );

		try {
			foreach ( array( 'source-topic-slug', 'earlier-source-topic-slug' ) as $slug ) {
				set_query_var( 'name', $slug );
				$GLOBALS['wp_query']->is_404 = true;
				wp_old_slug_redirect();
				$this->assertSame( get_permalink( $destination_topic_id ), $redirect_url );
				$redirect_url = null;
			}
		} finally {
			remove_filter( 'old_slug_redirect_url', $filter );
		}

		$this->assertSame( array(), get_post_meta( $source_topic_id, '_wp_old_slug', false ) );
	}

	/**
	 * @covers ::bbp_merge_topic_handler
	 */
	public function test_keymaster_can_merge_existing_topics_when_frozen() {
		$user_id              = $this->factory->user->create();
		$forum_id             = $this->factory->forum->create();
		$source_topic_id      = $this->factory->topic->create( array( 'post_parent' => $forum_id ) );
		$destination_topic_id = $this->factory->topic->create( array( 'post_parent' => $forum_id ) );

		bbp_set_user_role( $user_id, bbp_get_keymaster_role() );
		$this->set_current_user( $user_id );
		update_option( '_bbp_forums_status', 'frozen' );
		bbpress()->errors = new WP_Error();

		$did_redirect = $this->submit_topic_merge( $source_topic_id, $destination_topic_id );

		$this->assertSame( array(), bbpress()->errors->get_error_codes() );
		$this->assertSame( bbp_get_reply_post_type(), get_post_type( $source_topic_id ) );
		$this->assertSame( $destination_topic_id, wp_get_post_parent_id( $source_topic_id ) );
		$this->assertSame( $destination_topic_id, bbp_get_reply_topic_id( $source_topic_id ) );
		$this->assertTrue( $did_redirect );
	}

	/**
	 * @covers ::bbp_merge_topic_handler
	 */
	public function test_missing_merge_topics_collect_both_errors() {
		$user_id = $this->factory->user->create();

		bbp_set_user_role( $user_id, bbp_get_moderator_role() );
		$this->set_current_user( $user_id );
		bbpress()->errors = new WP_Error();

		$did_redirect = $this->submit_topic_merge( 999999 );

		$this->assertContains( 'bbp_merge_topic_source_not_found', bbpress()->errors->get_error_codes() );
		$this->assertContains( 'bbp_merge_topic_destination_not_found', bbpress()->errors->get_error_codes() );
		$this->assertFalse( $did_redirect );
	}

	/**
	 * @covers ::bbp_split_topic_handler
	 */
	public function test_participant_cannot_split_another_users_reply_from_own_topic() {
		$user_id         = $this->factory->user->create( array( 'role' => bbp_get_participant_role() ) );
		$other_user_id   = $this->factory->user->create( array( 'role' => bbp_get_participant_role() ) );
		$forum_id        = $this->factory->forum->create();
		$source_topic_id = $this->factory->topic->create( array( 'post_author' => $user_id, 'post_parent' => $forum_id, 'topic_meta' => array( 'forum_id' => $forum_id ) ) );
		$reply_id        = $this->factory->reply->create( array( 'post_author' => $other_user_id, 'post_parent' => $source_topic_id, 'reply_meta' => array( 'forum_id' => $forum_id, 'topic_id' => $source_topic_id ) ) );

		$this->set_current_user( $user_id );
		bbpress()->errors = new WP_Error();

		$this->assertTrue( current_user_can( 'edit_topic', $source_topic_id ) );
		$this->assertFalse( current_user_can( 'moderate', $source_topic_id ) );
		$this->submit_topic_split( $reply_id, $source_topic_id, 'reply', 'Split reply' );

		$this->assertContains( 'bbp_split_topic_source_permission', bbpress()->errors->get_error_codes() );
		$this->assertSame( bbp_get_reply_post_type(), get_post_type( $reply_id ) );
	}

	/**
	 * @covers ::bbp_merge_topic_handler
	 */
	public function test_participant_cannot_merge_own_topics() {
		$user_id              = $this->factory->user->create( array( 'role' => bbp_get_participant_role() ) );
		$forum_id             = $this->factory->forum->create();
		$source_topic_id      = $this->factory->topic->create( array( 'post_author' => $user_id, 'post_parent' => $forum_id, 'topic_meta' => array( 'forum_id' => $forum_id ) ) );
		$destination_topic_id = $this->factory->topic->create( array( 'post_author' => $user_id, 'post_parent' => $forum_id, 'topic_meta' => array( 'forum_id' => $forum_id ) ) );
		$home_url             = wp_parse_url( home_url( '/' ) );

		$this->set_current_user( $user_id );
		bbpress()->errors       = new WP_Error();
		$_SERVER['HTTP_HOST']    = $home_url['host'];
		if ( isset( $home_url['port'] ) ) {
			$_SERVER['HTTP_HOST'] .= ':' . $home_url['port'];
		}
		$_SERVER['REQUEST_URI']  = $home_url['path'];
		$_POST['bbp_topic_id']   = $source_topic_id;
		$_POST['bbp_destination_topic'] = $destination_topic_id;
		$_REQUEST['_wpnonce']   = wp_create_nonce( 'bbp-merge-topic_' . $source_topic_id );

		$this->assertTrue( current_user_can( 'edit_topic', $source_topic_id ) );
		$this->assertFalse( current_user_can( 'moderate', $source_topic_id ) );

		$prevent_redirect = function() {
			throw new RuntimeException( 'Topic merge redirect.' );
		};
		add_filter( 'wp_redirect', $prevent_redirect );

		try {
			bbp_merge_topic_handler( 'bbp-merge-topic' );
		} catch ( RuntimeException $exception ) {
			if ( 'Topic merge redirect.' !== $exception->getMessage() ) {
				throw $exception;
			}
		}

		remove_filter( 'wp_redirect', $prevent_redirect );

		$this->assertContains( 'bbp_merge_topic_source_permission', bbpress()->errors->get_error_codes() );
		$this->assertSame( bbp_get_topic_post_type(), get_post_type( $source_topic_id ) );
	}

	/**
	 * @covers ::bbp_split_topic_handler
	 */
	public function test_participant_cannot_split_reply_when_source_topic_cannot_be_edited() {
		$user_id         = $this->factory->user->create( array( 'role' => bbp_get_participant_role() ) );
		$other_user_id   = $this->factory->user->create( array( 'role' => bbp_get_participant_role() ) );
		$forum_id        = $this->factory->forum->create();
		$source_topic_id = $this->factory->topic->create(
			array(
				'post_author' => $other_user_id,
				'post_parent' => $forum_id,
				'topic_meta'  => array( 'forum_id' => $forum_id ),
			)
		);
		$reply_id        = $this->factory->reply->create(
			array(
				'post_author' => $user_id,
				'post_parent' => $source_topic_id,
				'reply_meta'  => array(
					'forum_id' => $forum_id,
					'topic_id' => $source_topic_id,
				),
			)
		);

		$this->set_current_user( $user_id );
		bbpress()->errors = new WP_Error();

		$this->assertFalse( current_user_can( 'edit_topic', $source_topic_id ) );
		$this->assertTrue( current_user_can( 'publish_topics' ) );

		$this->submit_topic_split( $reply_id, $source_topic_id, 'reply' );

		$this->assertContains( 'bbp_split_topic_source_permission', bbpress()->errors->get_error_codes() );
		$this->assertSame( bbp_get_reply_post_type(), get_post_type( $reply_id ) );
		$this->assertSame( $source_topic_id, wp_get_post_parent_id( $reply_id ) );
		$this->assertSame( $source_topic_id, bbp_get_reply_topic_id( $reply_id ) );
	}

	/**
	 * @covers ::bbp_split_topic_handler
	 */
	public function test_moderator_can_convert_split_reply_to_topic() {
		$user_id         = $this->factory->user->create();
		$other_user_id   = $this->factory->user->create( array( 'role' => bbp_get_participant_role() ) );
		$forum_id        = $this->factory->forum->create();
		$source_topic_id = $this->factory->topic->create(
			array(
				'post_author' => $other_user_id,
				'post_parent' => $forum_id,
				'topic_meta'  => array( 'forum_id' => $forum_id ),
			)
		);
		$reply_id        = $this->factory->reply->create(
			array(
				'post_author' => $other_user_id,
				'post_parent' => $source_topic_id,
				'reply_meta'  => array(
					'forum_id' => $forum_id,
					'topic_id' => $source_topic_id,
				),
			)
		);

		bbp_set_user_role( $user_id, bbp_get_moderator_role() );
		$this->set_current_user( $user_id );
		bbpress()->errors = new WP_Error();

		$filter_title = function( $title ) {
			return $title . ' filtered';
		};

		add_filter( 'bbp_new_topic_pre_title', $filter_title );
		$did_redirect = $this->submit_topic_split( $reply_id, $source_topic_id, 'reply', 'Split topic' );
		remove_filter( 'bbp_new_topic_pre_title', $filter_title );

		$this->assertSame( array(), bbpress()->errors->get_error_codes() );
		$this->assertSame( bbp_get_topic_post_type(), get_post_type( $reply_id ) );
		$this->assertSame( 'Split topic filtered', get_post_field( 'post_title', $reply_id ) );
		$this->assertSame( $forum_id, wp_get_post_parent_id( $reply_id ) );
		$this->assertSame( $reply_id, (int) get_post_meta( $reply_id, '_bbp_topic_id', true ) );
		$this->assertTrue( $did_redirect );
	}

	/**
	 * @covers ::bbp_split_topic_handler
	 */
	public function test_keymaster_cannot_split_reply_into_new_topic_when_frozen() {
		$user_id         = $this->factory->user->create();
		$forum_id        = $this->factory->forum->create();
		$source_topic_id = $this->factory->topic->create( array( 'post_parent' => $forum_id ) );
		$reply_id        = $this->factory->reply->create(
			array(
				'post_parent' => $source_topic_id,
				'reply_meta'  => array( 'forum_id' => $forum_id, 'topic_id' => $source_topic_id ),
			)
		);

		bbp_set_user_role( $user_id, bbp_get_keymaster_role() );
		$this->set_current_user( $user_id );
		update_option( '_bbp_forums_status', 'frozen' );
		bbpress()->errors = new WP_Error();

		$did_redirect = $this->submit_topic_split( $reply_id, $source_topic_id, 'reply', 'New topic' );

		$this->assertContains( 'bbp_split_topic_destination_permission', bbpress()->errors->get_error_codes() );
		$this->assertSame( bbp_get_reply_post_type(), get_post_type( $reply_id ) );
		$this->assertSame( $source_topic_id, wp_get_post_parent_id( $reply_id ) );
		$this->assertSame( $source_topic_id, bbp_get_reply_topic_id( $reply_id ) );
		$this->assertFalse( $did_redirect );
	}

	/**
	 * @covers ::bbp_split_topic_handler
	 */
	public function test_moderator_cannot_convert_split_reply_to_topic_with_long_title() {
		$user_id         = $this->factory->user->create();
		$other_user_id   = $this->factory->user->create( array( 'role' => bbp_get_participant_role() ) );
		$forum_id        = $this->factory->forum->create();
		$source_topic_id = $this->factory->topic->create(
			array(
				'post_author' => $other_user_id,
				'post_parent' => $forum_id,
				'topic_meta'  => array( 'forum_id' => $forum_id ),
			)
		);
		$reply_id        = $this->factory->reply->create(
			array(
				'post_author' => $other_user_id,
				'post_parent' => $source_topic_id,
				'reply_meta'  => array(
					'forum_id' => $forum_id,
					'topic_id' => $source_topic_id,
				),
			)
		);

		bbp_set_user_role( $user_id, bbp_get_moderator_role() );
		$this->set_current_user( $user_id );
		bbpress()->errors = new WP_Error();

		$did_redirect = $this->submit_topic_split( $reply_id, $source_topic_id, 'reply', str_repeat( 'a', bbp_get_title_max_length() + 1 ) );

		$this->assertContains( 'bbp_topic_title', bbpress()->errors->get_error_codes() );
		$this->assertSame( bbp_get_reply_post_type(), get_post_type( $reply_id ) );
		$this->assertSame( $source_topic_id, wp_get_post_parent_id( $reply_id ) );
		$this->assertSame( $source_topic_id, bbp_get_reply_topic_id( $reply_id ) );
		$this->assertFalse( $did_redirect );
	}

	/**
	 * @covers ::bbp_split_topic_handler
	 */
	public function test_moderator_cannot_split_reply_into_its_own_topic() {
		$user_id  = $this->factory->user->create();
		$forum_id = $this->factory->forum->create();
		$topic_id = $this->factory->topic->create( array( 'post_parent' => $forum_id ) );
		$reply_id = $this->factory->reply->create( array( 'post_parent' => $topic_id ) );
		$did_run  = false;
		$callback = function() use ( &$did_run ) {
			$did_run = true;
			throw new RuntimeException( 'Unexpected topic split.' );
		};

		bbp_set_user_role( $user_id, bbp_get_moderator_role() );
		$this->set_current_user( $user_id );
		bbpress()->errors = new WP_Error();
		$_POST['bbp_destination_topic'] = $topic_id;
		add_action( 'bbp_pre_split_topic', $callback, 1 );

		try {
			$did_redirect = $this->submit_topic_split( $reply_id, $topic_id, 'existing' );
		} finally {
			remove_action( 'bbp_pre_split_topic', $callback, 1 );
		}

		$this->assertContains( 'bbp_split_topic_destination_same', bbpress()->errors->get_error_codes() );
		$this->assertFalse( $did_run );
		$this->assertFalse( $did_redirect );
		$this->assertSame( $topic_id, wp_get_post_parent_id( $reply_id ) );
	}

	/**
	 * @covers ::bbp_split_topic_handler
	 */
	public function test_moderator_cannot_split_reply_without_destination_topic() {
		$user_id  = $this->factory->user->create();
		$forum_id = $this->factory->forum->create();
		$topic_id = $this->factory->topic->create( array( 'post_parent' => $forum_id ) );
		$reply_id = $this->factory->reply->create( array( 'post_parent' => $topic_id ) );

		bbp_set_user_role( $user_id, bbp_get_moderator_role() );
		$this->set_current_user( $user_id );
		bbpress()->errors = new WP_Error();
		unset( $_POST['bbp_destination_topic'] );
		$warnings = array();
		set_error_handler( function( $severity, $message ) use ( &$warnings ) {
			if ( E_WARNING === $severity ) {
				$warnings[] = $message;
				return true;
			}
			return false;
		} );

		try {
			$did_redirect = $this->submit_topic_split( $reply_id, $topic_id, 'existing' );
		} finally {
			restore_error_handler();
		}

		$this->assertContains( 'bbp_split_topic_destination_not_found', bbpress()->errors->get_error_codes() );
		$this->assertSame( array(), $warnings );
		$this->assertFalse( $did_redirect );
		$this->assertSame( $topic_id, wp_get_post_parent_id( $reply_id ) );
	}

	/**
	 * @covers ::bbp_split_topic_handler
	 */
	public function test_moderator_cannot_split_missing_reply() {
		$user_id = $this->factory->user->create();

		bbp_set_user_role( $user_id, bbp_get_moderator_role() );
		$this->set_current_user( $user_id );
		bbpress()->errors = new WP_Error();
		$warnings = array();
		set_error_handler( function( $severity, $message ) use ( &$warnings ) {
			if ( E_WARNING === $severity ) {
				$warnings[] = $message;
				return true;
			}
			return false;
		} );

		try {
			$did_redirect = $this->submit_topic_split( 999999, 0, 'existing' );
		} finally {
			restore_error_handler();
		}

		$this->assertContains( 'bbp_split_topic_r_not_found', bbpress()->errors->get_error_codes() );
		$this->assertContains( 'bbp_split_topic_destination_not_found', bbpress()->errors->get_error_codes() );
		$this->assertSame( array(), $warnings );
		$this->assertFalse( $did_redirect );
	}

	/**
	 * @covers ::bbp_split_topic_handler
	 */
	public function test_moderator_can_split_reply_into_another_topic() {
		$user_id              = $this->factory->user->create();
		$forum_id             = $this->factory->forum->create();
		$source_topic_id      = $this->factory->topic->create( array( 'post_parent' => $forum_id ) );
		$destination_topic_id = $this->factory->topic->create( array( 'post_parent' => $forum_id ) );
		$reply_id             = $this->factory->reply->create( array( 'post_parent' => $source_topic_id ) );

		bbp_set_user_role( $user_id, bbp_get_moderator_role() );
		$this->set_current_user( $user_id );
		bbpress()->errors = new WP_Error();
		$_POST['bbp_destination_topic'] = $destination_topic_id;

		$did_redirect = $this->submit_topic_split( $reply_id, $source_topic_id, 'existing' );

		$this->assertSame( array(), bbpress()->errors->get_error_codes() );
		$this->assertSame( $destination_topic_id, wp_get_post_parent_id( $reply_id ) );
		$this->assertTrue( $did_redirect );
	}

	/**
	 * @covers ::bbp_update_topic
	 * @covers ::bbp_get_topic_stick_link
	 * @covers ::bbp_get_form_topic_type_dropdown
	 */
	public function test_forum_moderator_can_stick_locally_but_not_globally() {
		$forum_id   = $this->factory->forum->create();
		$topic_id   = $this->factory->topic->create( array( 'post_parent' => $forum_id ) );
		$local_id   = $this->factory->user->create( array( 'role' => bbp_get_participant_role() ) );
		$global_id  = $this->factory->user->create();

		bbp_add_moderator( $forum_id, $local_id );
		bbp_set_user_role( $global_id, bbp_get_moderator_role() );
		$this->set_current_user( $local_id );
		$this->assertTrue( current_user_can( 'moderate', $topic_id ) );
		$this->assertFalse( current_user_can( 'moderate' ) );

		$_POST['bbp_stick_topic'] = 'super';
		bbp_update_topic( $topic_id, $forum_id, array(), $local_id, true );
		$this->assertFalse( bbp_is_topic_super_sticky( $topic_id ) );

		$_POST['bbp_stick_topic'] = 'stick';
		bbp_update_topic( $topic_id, $forum_id, array(), $local_id, true );
		$this->assertTrue( bbp_is_topic_sticky( $topic_id, false ) );
		$this->assertStringNotContainsString( 'bbp-topic-super-sticky-link', bbp_get_topic_stick_link( array( 'id' => $topic_id ) ) );
		$this->assertStringNotContainsString( 'value="super"', bbp_get_form_topic_type_dropdown( array( 'topic_id' => $topic_id ) ) );

		$this->set_current_user( $global_id );
		$this->assertTrue( current_user_can( 'moderate' ) );
		$_POST['bbp_stick_topic'] = 'super';
		bbp_update_topic( $topic_id, $forum_id, array(), $global_id, true );
		$this->assertTrue( bbp_is_topic_super_sticky( $topic_id ) );

		$this->set_current_user( $local_id );
		$_POST['bbp_stick_topic'] = 'unstick';
		bbp_update_topic( $topic_id, $forum_id, array(), $local_id, true );
		$this->assertTrue( bbp_is_topic_super_sticky( $topic_id ) );
		$this->assertEmpty( bbp_get_topic_stick_link( array( 'id' => $topic_id ) ) );
		$this->assertEmpty( bbp_get_form_topic_type_dropdown( array( 'topic_id' => $topic_id ) ) );
	}

	/**
	 * @covers ::bbp_toggle_topic_handler
	 */
	public function test_forum_moderator_cannot_toggle_global_stickiness() {
		$forum_id = $this->factory->forum->create();
		$topic_id = $this->factory->topic->create( array( 'post_parent' => $forum_id ) );
		$local_id = $this->factory->user->create( array( 'role' => bbp_get_participant_role() ) );

		bbp_add_moderator( $forum_id, $local_id );
		$this->set_current_user( $local_id );
		bbpress()->errors = new WP_Error();
		$_GET['topic_id'] = $topic_id;
		$_GET['super'] = '1';

		bbp_toggle_topic_handler( 'bbp_toggle_topic_stick' );

		$this->assertContains( 'bbp_toggle_topic_permission', bbpress()->errors->get_error_codes() );
		$this->assertFalse( bbp_is_topic_super_sticky( $topic_id ) );

		bbp_stick_topic( $topic_id, true );
		bbpress()->errors = new WP_Error();
		unset( $_GET['super'] );

		bbp_toggle_topic_handler( 'bbp_toggle_topic_stick' );

		$this->assertContains( 'bbp_toggle_topic_permission', bbpress()->errors->get_error_codes() );
		$this->assertTrue( bbp_is_topic_super_sticky( $topic_id ) );
	}
}
