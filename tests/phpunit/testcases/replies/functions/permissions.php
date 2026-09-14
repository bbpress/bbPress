<?php

/**
 * Tests for reply handler permissions.
 *
 * @group replies
 * @group functions
 * @group capabilities
 */
class BBP_Tests_Replies_Functions_Permissions extends BBP_UnitTestCase {

	protected $old_post;
	protected $old_request;
	protected $old_server;
	protected $old_errors;
	protected $old_allow_anonymous;
	protected $old_allow_content_throttle;
	protected $blocked_topic_tag_id;

	public function setUp(): void {
		parent::setUp();

		$this->old_post                   = $_POST;
		$this->old_request                = $_REQUEST;
		$this->old_server                 = $_SERVER;
		$this->old_errors                 = bbpress()->errors;
		$this->old_allow_anonymous        = get_option( '_bbp_allow_anonymous' );
		$this->old_allow_content_throttle = get_option( '_bbp_allow_content_throttle' );
		$this->blocked_topic_tag_id       = 0;
	}

	public function tearDown(): void {
		$_POST            = $this->old_post;
		$_REQUEST         = $this->old_request;
		$_SERVER          = $this->old_server;
		bbpress()->errors = $this->old_errors;
		update_option( '_bbp_allow_anonymous', $this->old_allow_anonymous );
		update_option( '_bbp_allow_content_throttle', $this->old_allow_content_throttle );
		remove_filter( 'wp_redirect', array( $this, 'filter_reply_edit_redirect' ) );
		remove_filter( 'bbp_map_topic_tag_meta_caps', array( $this, 'filter_remove_topic_tag_caps' ), 10 );

		parent::tearDown();
	}

	public function filter_reply_edit_redirect( $location ) {
		throw new RuntimeException( 'Reply edit redirect.' );
	}

	public function filter_remove_topic_tag_caps( $caps, $cap, $user_id, $args ) {
		if ( ( 'remove_topic_tag' === $cap ) && ( $this->blocked_topic_tag_id === $args[1] ) ) {
			$caps = array( 'do_not_allow' );
		}

		return $caps;
	}

	protected function submit_reply( $topic_id, $forum_id = null, $content = '', $anonymous_data = array() ) {
		$home_url             = wp_parse_url( home_url( '/' ) );
		$_SERVER['HTTP_HOST'] = $home_url['host'];

		if ( isset( $home_url['port'] ) ) {
			$_SERVER['HTTP_HOST'] .= ':' . $home_url['port'];
		}

		$_SERVER['REQUEST_URI']     = $home_url['path'];
		$_SERVER['REMOTE_ADDR']     = '127.0.0.1';
		$_POST['bbp_topic_id']      = $topic_id;
		$_POST['bbp_reply_content'] = $content;
		$_REQUEST['_wpnonce']       = wp_create_nonce( 'bbp-new-reply' );

		if ( null !== $forum_id ) {
			$_POST['bbp_forum_id'] = $forum_id;
		}

		$_POST = array_merge( $_POST, $anonymous_data );

		$did_redirect     = false;
		$prevent_redirect = function( $location ) use ( &$did_redirect ) {
			$did_redirect = true;
			throw new RuntimeException( 'Reply redirect.' );
		};
		$prevent_cookies  = function() {
			return array();
		};

		add_filter( 'wp_redirect', $prevent_redirect );
		add_filter( 'bbp_filter_anonymous_post_data', $prevent_cookies );

		try {
			bbp_new_reply_handler( 'bbp-new-reply' );
		} catch ( RuntimeException $exception ) {
			if ( 'Reply redirect.' !== $exception->getMessage() ) {
				throw $exception;
			}
		}

		remove_filter( 'wp_redirect', $prevent_redirect );
		remove_filter( 'bbp_filter_anonymous_post_data', $prevent_cookies );

		return $did_redirect;
	}

	protected function submit_reply_edit( $reply_id, $content = '', $post_data = array() ) {
		$home_url             = wp_parse_url( home_url( '/' ) );
		$_SERVER['HTTP_HOST'] = $home_url['host'];

		if ( isset( $home_url['port'] ) ) {
			$_SERVER['HTTP_HOST'] .= ':' . $home_url['port'];
		}

		$_SERVER['REQUEST_URI']     = $home_url['path'];
		$_POST['bbp_reply_id']      = $reply_id;
		$_POST['bbp_reply_content'] = $content;
		$_REQUEST['_wpnonce']       = wp_create_nonce( 'bbp-edit-reply_' . $reply_id );
		$_POST                      = array_merge( $_POST, $post_data );

		$did_redirect = false;

		add_filter( 'wp_redirect', array( $this, 'filter_reply_edit_redirect' ) );

		try {
			bbp_edit_reply_handler( 'bbp-edit-reply' );
		} catch ( RuntimeException $exception ) {
			if ( 'Reply edit redirect.' !== $exception->getMessage() ) {
				throw $exception;
			}

			$did_redirect = true;
		}

		remove_filter( 'wp_redirect', array( $this, 'filter_reply_edit_redirect' ) );

		return $did_redirect;
	}

	protected function get_reply_ids( $topic_id ) {
		return get_posts(
			array(
				'fields'      => 'ids',
				'post_parent' => $topic_id,
				'post_status' => 'any',
				'post_type'   => bbp_get_reply_post_type(),
			)
		);
	}

	protected function submit_reply_move( $reply_id, $move_option, $destination_topic_id = 0 ) {
		$home_url             = wp_parse_url( home_url( '/' ) );
		$_SERVER['HTTP_HOST'] = $home_url['host'];

		if ( isset( $home_url['port'] ) ) {
			$_SERVER['HTTP_HOST'] .= ':' . $home_url['port'];
		}

		$_SERVER['REQUEST_URI']            = $home_url['path'];
		$_POST['bbp_reply_id']              = $reply_id;
		$_POST['bbp_reply_move_option']     = $move_option;
		$_POST['bbp_destination_topic']     = $destination_topic_id;
		$_REQUEST['_wpnonce']               = wp_create_nonce( 'bbp-move-reply_' . $reply_id );

		$did_redirect     = false;
		$prevent_redirect = function() use ( &$did_redirect ) {
			$did_redirect = true;
			throw new RuntimeException( 'Reply move redirect.' );
		};

		add_filter( 'wp_redirect', $prevent_redirect );

		try {
			bbp_move_reply_handler( 'bbp-move-reply' );
		} catch ( RuntimeException $exception ) {
			if ( 'Reply move redirect.' !== $exception->getMessage() ) {
				throw $exception;
			}
		}

		remove_filter( 'wp_redirect', $prevent_redirect );

		return $did_redirect;
	}

	/**
	 * @covers ::bbp_move_reply_handler
	 */
	public function test_participant_cannot_move_reply_into_another_users_topic() {
		$user_id              = $this->factory->user->create( array( 'role' => bbp_get_participant_role() ) );
		$other_user_id        = $this->factory->user->create( array( 'role' => bbp_get_participant_role() ) );
		$source_forum_id      = $this->factory->forum->create();
		$destination_forum_id = $this->factory->forum->create();
		$source_topic_id      = $this->factory->topic->create(
			array(
				'post_author' => $user_id,
				'post_parent' => $source_forum_id,
				'topic_meta'  => array( 'forum_id' => $source_forum_id ),
			)
		);
		$destination_topic_id = $this->factory->topic->create(
			array(
				'post_author' => $other_user_id,
				'post_parent' => $destination_forum_id,
				'topic_meta'  => array( 'forum_id' => $destination_forum_id ),
			)
		);
		$reply_id             = $this->factory->reply->create(
			array(
				'post_author' => $user_id,
				'post_parent' => $source_topic_id,
				'reply_meta'  => array(
					'forum_id' => $source_forum_id,
					'topic_id' => $source_topic_id,
				),
			)
		);

		$this->set_current_user( $user_id );
		bbpress()->errors = new WP_Error();

		$this->assertTrue( current_user_can( 'edit_topic', $source_topic_id ) );
		$this->assertFalse( current_user_can( 'edit_topic', $destination_topic_id ) );

		bbp_add_error( 'bbp_test_preexisting', 'Pre-existing test error.' );
		$this->submit_reply_move( $reply_id, 'existing', $destination_topic_id );

		$this->assertContains( 'bbp_test_preexisting', bbpress()->errors->get_error_codes() );
		$this->assertContains( 'bbp_move_reply_destination_permission', bbpress()->errors->get_error_codes() );
		$this->assertSame( $source_topic_id, wp_get_post_parent_id( $reply_id ) );
		$this->assertSame( $source_topic_id, bbp_get_reply_topic_id( $reply_id ) );
		$this->assertSame( $source_forum_id, bbp_get_reply_forum_id( $reply_id ) );
	}

	/**
	 * @covers ::bbp_move_reply_handler
	 */
	public function test_participant_cannot_convert_reply_when_source_topic_cannot_be_edited() {
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

		$this->submit_reply_move( $reply_id, 'topic' );

		$this->assertContains( 'bbp_move_reply_source_permission', bbpress()->errors->get_error_codes() );
		$this->assertSame( bbp_get_reply_post_type(), get_post_type( $reply_id ) );
		$this->assertSame( $source_topic_id, wp_get_post_parent_id( $reply_id ) );
		$this->assertSame( $source_topic_id, bbp_get_reply_topic_id( $reply_id ) );
	}

	/**
	 * @covers ::bbp_move_reply_handler
	 */
	public function test_moderator_can_move_reply_into_another_users_topic() {
		$user_id              = $this->factory->user->create();
		$other_user_id        = $this->factory->user->create( array( 'role' => bbp_get_participant_role() ) );
		$source_forum_id      = $this->factory->forum->create();
		$destination_forum_id = $this->factory->forum->create();
		$source_topic_id      = $this->factory->topic->create(
			array(
				'post_author' => $other_user_id,
				'post_parent' => $source_forum_id,
				'topic_meta'  => array( 'forum_id' => $source_forum_id ),
			)
		);
		$destination_topic_id = $this->factory->topic->create(
			array(
				'post_author' => $other_user_id,
				'post_parent' => $destination_forum_id,
				'topic_meta'  => array( 'forum_id' => $destination_forum_id ),
			)
		);
		$reply_id             = $this->factory->reply->create(
			array(
				'post_author' => $other_user_id,
				'post_parent' => $source_topic_id,
				'reply_meta'  => array(
					'forum_id' => $source_forum_id,
					'topic_id' => $source_topic_id,
				),
			)
		);

		bbp_set_user_role( $user_id, bbp_get_moderator_role() );
		$this->set_current_user( $user_id );
		bbpress()->errors = new WP_Error();

		$this->assertTrue( current_user_can( 'edit_topic', $source_topic_id ) );
		$this->assertTrue( current_user_can( 'edit_topic', $destination_topic_id ) );

		$did_redirect = $this->submit_reply_move( $reply_id, 'existing', $destination_topic_id );

		$this->assertSame( array(), bbpress()->errors->get_error_codes() );
		$this->assertSame( $destination_topic_id, wp_get_post_parent_id( $reply_id ) );
		$this->assertSame( $destination_topic_id, bbp_get_reply_topic_id( $reply_id ) );
		$this->assertSame( $destination_forum_id, bbp_get_reply_forum_id( $reply_id ) );
		$this->assertTrue( $did_redirect );
	}

	/**
	 * @covers ::bbp_move_reply_handler
	 */
	public function test_moderator_can_convert_reply_to_topic() {
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

		$did_redirect = $this->submit_reply_move( $reply_id, 'topic' );

		$this->assertSame( array(), bbpress()->errors->get_error_codes() );
		$this->assertSame( bbp_get_topic_post_type(), get_post_type( $reply_id ) );
		$this->assertSame( $forum_id, wp_get_post_parent_id( $reply_id ) );
		$this->assertSame( $reply_id, (int) get_post_meta( $reply_id, '_bbp_topic_id', true ) );
		$this->assertTrue( $did_redirect );
	}

	/**
	 * @covers ::bbp_new_reply_handler
	 */
	public function test_participant_cannot_submit_reply_to_private_topic() {
		$forum_id = $this->factory->forum->create();
		$topic_id = $this->factory->topic->create(
			array(
				'post_status' => bbp_get_private_status_id(),
				'post_parent' => $forum_id,
				'topic_meta'  => array(
					'forum_id' => $forum_id,
				),
			)
		);
		$user_id  = $this->factory->user->create(
			array(
				'role' => bbp_get_participant_role(),
			)
		);

		$this->set_current_user( $user_id );
		bbpress()->errors = new WP_Error();

		$this->assertFalse( current_user_can( 'read_topic', $topic_id ) );

		$did_redirect = $this->submit_reply( $topic_id, null, 'A reply to a private topic.' );
		$reply_ids    = $this->get_reply_ids( $topic_id );

		$this->assertSame( array(), $reply_ids );
		$this->assertContains( 'bbp_new_reply_topic_public', bbpress()->errors->get_error_codes() );
		$this->assertFalse( $did_redirect );
	}

	/**
	 * @covers ::bbp_new_reply_handler
	 */
	public function test_participant_can_submit_reply_when_forum_id_matches_topic() {
		$forum_id = $this->factory->forum->create();
		$topic_id = $this->factory->topic->create(
			array(
				'post_parent' => $forum_id,
				'topic_meta'  => array(
					'forum_id' => $forum_id,
				),
			)
		);
		$user_id  = $this->factory->user->create(
			array(
				'role' => bbp_get_participant_role(),
			)
		);

		$this->set_current_user( $user_id );
		bbpress()->errors = new WP_Error();

		$did_redirect = $this->submit_reply( $topic_id, $forum_id, 'A reply to a public topic.' );
		$reply_ids    = $this->get_reply_ids( $topic_id );

		$this->assertCount( 1, $reply_ids );
		$this->assertSame( $forum_id, bbp_get_reply_forum_id( $reply_ids[0] ) );
		$this->assertSame( array(), bbpress()->errors->get_error_codes() );
		$this->assertTrue( $did_redirect );
	}

	/**
	 * @covers ::bbp_new_reply_handler
	 */
	public function test_participant_cannot_spoof_forum_id_to_reply_to_hidden_topic() {
		$public_forum_id = $this->factory->forum->create();
		$hidden_forum_id = $this->factory->forum->create(
			array(
				'post_status' => bbp_get_hidden_status_id(),
			)
		);
		$topic_id       = $this->factory->topic->create(
			array(
				'post_parent' => $hidden_forum_id,
				'topic_meta'  => array(
					'forum_id' => $hidden_forum_id,
				),
			)
		);
		$user_id        = $this->factory->user->create(
			array(
				'role' => bbp_get_participant_role(),
			)
		);

		$this->set_current_user( $user_id );
		bbpress()->errors = new WP_Error();

		$this->assertTrue( current_user_can( 'read_topic', $topic_id ) );
		$this->assertFalse( current_user_can( 'read_forum', $hidden_forum_id ) );
		$this->assertTrue( current_user_can( 'read_forum', $public_forum_id ) );

		$did_redirect = $this->submit_reply( $topic_id, $public_forum_id, 'A reply to a topic in a hidden forum.' );
		$reply_ids    = $this->get_reply_ids( $topic_id );

		$this->assertSame( array(), $reply_ids );
		$this->assertContains( 'bbp_new_reply_forum_read', bbpress()->errors->get_error_codes() );
		$this->assertFalse( $did_redirect );
	}

	/**
	 * @covers ::bbp_new_reply_handler
	 */
	public function test_participant_cannot_spoof_forum_id_to_reply_below_hidden_forum() {
		$public_forum_id = $this->factory->forum->create();
		$hidden_forum_id = $this->factory->forum->create(
			array(
				'post_status' => bbp_get_hidden_status_id(),
			)
		);
		$child_forum_id  = $this->factory->forum->create(
			array(
				'post_parent' => $hidden_forum_id,
			)
		);
		$topic_id        = $this->factory->topic->create(
			array(
				'post_parent' => $child_forum_id,
				'topic_meta'  => array(
					'forum_id' => $child_forum_id,
				),
			)
		);
		$user_id         = $this->factory->user->create(
			array(
				'role' => bbp_get_participant_role(),
			)
		);

		$this->set_current_user( $user_id );
		bbpress()->errors = new WP_Error();

		$this->assertTrue( current_user_can( 'read_topic', $topic_id ) );
		$this->assertFalse( current_user_can( 'read_forum', $child_forum_id ) );
		$this->assertTrue( current_user_can( 'read_forum', $public_forum_id ) );

		$did_redirect = $this->submit_reply( $topic_id, $public_forum_id, 'A reply below a hidden forum.' );
		$reply_ids    = $this->get_reply_ids( $topic_id );

		$this->assertSame( array(), $reply_ids );
		$this->assertContains( 'bbp_new_reply_forum_read', bbpress()->errors->get_error_codes() );
		$this->assertFalse( $did_redirect );
	}

	/**
	 * @covers ::bbp_new_reply_handler
	 */
	public function test_anonymous_user_cannot_remove_topic_tags_without_capability() {
		$forum_id = $this->factory->forum->create();
		$topic_id = $this->factory->topic->create(
			array(
				'post_parent' => $forum_id,
				'topic_meta'  => array(
					'forum_id' => $forum_id,
				),
			)
		);
		$taxonomy = bbp_get_topic_tag_tax_id();
		$tag_ids  = wp_set_post_terms( $topic_id, array( 'alpha', 'beta' ), $taxonomy );

		update_option( '_bbp_allow_anonymous', true );
		update_option( '_bbp_allow_content_throttle', false );
		$this->set_current_user( 0 );
		bbpress()->errors = new WP_Error();

		$this->assertFalse( current_user_can( 'assign_topic_tags', $topic_id ) );
		$this->assertFalse( current_user_can( 'remove_topic_tag', $topic_id, $tag_ids[0] ) );
		$this->assertFalse( current_user_can( 'remove_topic_tag', $topic_id, $tag_ids[1] ) );
		$this->assertSame( array( 'alpha', 'beta' ), wp_get_post_terms( $topic_id, $taxonomy, array( 'fields' => 'names' ) ) );

		$did_redirect = $this->submit_reply(
			$topic_id,
			$forum_id,
			'An anonymous reply with an unauthorized topic-tag field.',
			array(
				'bbp_anonymous_name'  => 'Anonymous User',
				'bbp_anonymous_email' => 'anonymous@example.org',
				'bbp_topic_tags'      => 'unauthorized-field-injection',
			)
		);

		$this->assertSame( array(), bbpress()->errors->get_error_codes() );
		$this->assertCount( 1, $this->get_reply_ids( $topic_id ) );
		$this->assertSame( array( 'alpha', 'beta' ), wp_get_post_terms( $topic_id, $taxonomy, array( 'fields' => 'names' ) ) );
		$this->assertTrue( $did_redirect );
	}

	/**
	 * @covers ::bbp_map_forum_meta_caps
	 * @covers ::bbp_map_topic_meta_caps
	 * @covers ::bbp_map_reply_meta_caps
	 */
	public function test_anonymous_user_can_read_public_forum_topic_and_reply() {
		$forum_id = $this->factory->forum->create();
		$topic_id = $this->factory->topic->create(
			array(
				'post_parent' => $forum_id,
				'topic_meta'  => array(
					'forum_id' => $forum_id,
				),
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

		update_option( '_bbp_allow_anonymous', true );
		$this->set_current_user( 0 );

		$this->assertTrue( current_user_can( 'read_forum', $forum_id ) );
		$this->assertTrue( current_user_can( 'read_topic', $topic_id ) );
		$this->assertTrue( current_user_can( 'read_reply', $reply_id ) );
	}

	/**
	 * @covers ::bbp_new_reply_handler
	 * @covers ::bbp_get_topic_tag_names_for_update
	 */
	public function test_participant_can_remove_topic_tags_when_replying() {
		$user_id  = $this->factory->user->create( array( 'role' => bbp_get_participant_role() ) );
		$forum_id = $this->factory->forum->create();
		$topic_id = $this->factory->topic->create(
			array(
				'post_parent' => $forum_id,
				'topic_meta'  => array(
					'forum_id' => $forum_id,
				),
			)
		);
		$taxonomy = bbp_get_topic_tag_tax_id();
		$tag_ids  = wp_set_post_terms( $topic_id, array( 'alpha', 'beta' ), $taxonomy );

		update_option( '_bbp_allow_content_throttle', false );
		$this->set_current_user( $user_id );
		bbpress()->errors = new WP_Error();

		$this->assertTrue( current_user_can( 'assign_topic_tags', $topic_id ) );
		$this->assertTrue( current_user_can( 'remove_topic_tag', $topic_id, $tag_ids[0] ) );

		$did_redirect = $this->submit_reply(
			$topic_id,
			$forum_id,
			'A participant reply that clears the topic tags.',
			array( 'bbp_topic_tags' => '' )
		);

		$this->assertSame( array(), bbpress()->errors->get_error_codes() );
		$this->assertCount( 1, $this->get_reply_ids( $topic_id ) );
		$this->assertSame( array(), wp_get_post_terms( $topic_id, $taxonomy, array( 'fields' => 'names' ) ) );
		$this->assertTrue( $did_redirect );
	}

	/**
	 * @covers ::bbp_get_topic_tag_names_for_update
	 */
	public function test_malformed_topic_tag_input_preserves_existing_tags() {
		$user_id  = $this->factory->user->create( array( 'role' => bbp_get_participant_role() ) );
		$forum_id = $this->factory->forum->create();
		$topic_id = $this->factory->topic->create(
			array(
				'post_parent' => $forum_id,
				'topic_meta'  => array(
					'forum_id' => $forum_id,
				),
			)
		);
		$taxonomy = bbp_get_topic_tag_tax_id();

		wp_set_post_terms( $topic_id, array( 'alpha', 'beta' ), $taxonomy );
		$this->set_current_user( $user_id );

		$tag_names = bbp_get_topic_tag_names_for_update( $topic_id, array( 'malformed' ) );
		wp_set_post_terms( $topic_id, $tag_names, $taxonomy );

		$this->assertSame( array( 'alpha', 'beta' ), wp_get_post_terms( $topic_id, $taxonomy, array( 'fields' => 'names' ) ) );
	}

	/**
	 * @covers ::bbp_edit_reply_handler
	 * @covers ::bbp_get_topic_tag_names_for_update
	 * @covers ::bbp_map_topic_tag_meta_caps
	 */
	public function test_topic_tag_removal_can_be_filtered_per_term() {
		$user_id  = $this->factory->user->create( array( 'role' => bbp_get_participant_role() ) );
		$forum_id = $this->factory->forum->create();
		$topic_id = $this->factory->topic->create(
			array(
				'post_parent' => $forum_id,
				'topic_meta'  => array(
					'forum_id' => $forum_id,
				),
			)
		);
		$reply_id = $this->factory->reply->create(
			array(
				'post_author' => $user_id,
				'post_parent' => $topic_id,
				'reply_meta'  => array(
					'forum_id' => $forum_id,
					'topic_id' => $topic_id,
				),
			)
		);
		$taxonomy = bbp_get_topic_tag_tax_id();
		$tag_ids  = wp_set_post_terms( $topic_id, array( 'alpha', 'beta' ), $taxonomy );

		update_option( '_bbp_allow_content_throttle', false );
		$this->set_current_user( $user_id );
		bbpress()->errors           = new WP_Error();
		$this->blocked_topic_tag_id = $tag_ids[1];

		add_filter( 'bbp_map_topic_tag_meta_caps', array( $this, 'filter_remove_topic_tag_caps' ), 10, 4 );

		$this->assertTrue( current_user_can( 'remove_topic_tag', $topic_id, $tag_ids[0] ) );
		$this->assertFalse( current_user_can( 'remove_topic_tag', $topic_id, $tag_ids[1] ) );

		$did_redirect = $this->submit_reply_edit(
			$reply_id,
			'A participant edit with a filtered topic-tag removal.',
			array( 'bbp_topic_tags' => 'gamma' )
		);

		$this->assertSame( array(), bbpress()->errors->get_error_codes() );
		$this->assertSame( 'A participant edit with a filtered topic-tag removal.', get_post_field( 'post_content', $reply_id ) );
		$this->assertSame( array( 'beta', 'gamma' ), wp_get_post_terms( $topic_id, $taxonomy, array( 'fields' => 'names' ) ) );
		$this->assertTrue( $did_redirect );
	}

}
