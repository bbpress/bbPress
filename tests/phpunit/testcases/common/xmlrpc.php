<?php

/**
 * Tests for bbPress XML-RPC integration.
 *
 * @group common
 * @group xmlrpc
 */
class BBP_Tests_Common_XMLRPC extends BBP_UnitTestCase {

	/**
	 * XML-RPC server instance.
	 *
	 * @var wp_xmlrpc_server
	 */
	protected $xmlrpc_server;

	/**
	 * Participant credentials.
	 *
	 * @var string
	 */
	protected $username = 'bbp-participant';
	protected $password = 'bbp-password';

	/**
	 * Set up XML-RPC and a participant.
	 */
	public function setUp(): void {
		parent::setUp();

		add_filter( 'pre_option_enable_xmlrpc', '__return_true' );
		$this->xmlrpc_server = new wp_xmlrpc_server();
	}

	/**
	 * Remove XML-RPC test filters.
	 */
	public function tearDown(): void {
		remove_filter( 'pre_option_enable_xmlrpc', '__return_true' );
		$GLOBALS['bbp_xmlrpc_error_post_id']   = 0;
		$GLOBALS['bbp_xmlrpc_error_post_type'] = '';

		parent::tearDown();
	}

	/**
	 * Create a participant with XML-RPC credentials.
	 *
	 * @return int User ID.
	 */
	protected function create_participant() {
		$user_id = $this->factory->user->create(
			array(
				'user_login' => $this->username,
				'user_pass'  => $this->password,
				'role'       => 'subscriber',
			)
		);

		bbp_set_user_role( $user_id, bbp_get_participant_role() );

		return $user_id;
	}

	/**
	 * Create a WordPress Author with a bbPress Participant role.
	 *
	 * @return int User ID.
	 */
	protected function create_author_participant() {
		$user_id = $this->factory->user->create(
			array(
				'user_login' => $this->username,
				'user_pass'  => $this->password,
				'role'       => 'author',
			)
		);

		bbp_set_user_role( $user_id, bbp_get_participant_role() );

		return $user_id;
	}

	/**
	 * Create a keymaster with XML-RPC credentials.
	 *
	 * @return int User ID.
	 */
	protected function create_keymaster() {
		$user_id = $this->factory->user->create(
			array(
				'user_login' => $this->username,
				'user_pass'  => $this->password,
				'role'       => 'administrator',
			)
		);

		bbp_set_user_role( $user_id, bbp_get_keymaster_role() );

		return $user_id;
	}

	/**
	 * Create a global moderator with XML-RPC credentials.
	 *
	 * @return int User ID.
	 */
	protected function create_moderator() {
		$user_id = $this->factory->user->create(
			array(
				'user_login' => $this->username,
				'user_pass'  => $this->password,
				'role'       => 'subscriber',
			)
		);

		bbp_set_user_role( $user_id, bbp_get_moderator_role() );

		return $user_id;
	}

	/**
	 * Create a per-forum moderator with XML-RPC credentials.
	 *
	 * @param int $forum_id Forum ID.
	 * @return int User ID.
	 */
	protected function create_forum_moderator( $forum_id ) {
		$user_id = $this->create_participant();

		bbp_add_moderator( $forum_id, $user_id );

		return $user_id;
	}

	/**
	 * Edit a post through the XML-RPC wp.editPost method.
	 *
	 * @param int   $post_id Post ID.
	 * @param array $data    Post fields.
	 * @return true|IXR_Error Result.
	 */
	protected function edit_post( $post_id, $data ) {
		return $this->xmlrpc_server->wp_editPost(
			array( 1, $this->username, $this->password, $post_id, $data )
		);
	}

	/**
	 * Create a post through the XML-RPC wp.newPost method.
	 *
	 * @param array $data Post fields.
	 * @return string|IXR_Error Result.
	 */
	protected function new_post( $data ) {
		return $this->xmlrpc_server->wp_newPost(
			array( 1, $this->username, $this->password, $data )
		);
	}

	/**
	 * XML-RPC raw reads and edits must honor a password added later.
	 *
	 * @covers ::bbp_map_xmlrpc_meta_caps
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_author_xmlrpc_reads_and_edits_require_new_forum_password() {
		if ( ! defined( 'XMLRPC_REQUEST' ) ) {
			define( 'XMLRPC_REQUEST', true );
		}

		$user_id  = $this->create_participant();
		$forum_id = $this->factory->forum->create();
		$topic_id = $this->factory->topic->create(
			array(
				'post_author'  => $user_id,
				'post_parent'  => $forum_id,
				'post_content' => 'Original topic content.',
				'topic_meta'   => array( 'forum_id' => $forum_id ),
			)
		);
		$reply_id = $this->factory->reply->create(
			array(
				'post_author'  => $user_id,
				'post_parent'  => $topic_id,
				'post_content' => 'Original reply content.',
				'reply_meta'   => array( 'forum_id' => $forum_id, 'topic_id' => $topic_id ),
			)
		);

		foreach ( array( $topic_id, $reply_id ) as $post_id ) {
			wp_update_post( array( 'ID' => $post_id, 'post_content' => 'Updated content.' ) );
			$this->assertNotEmpty( wp_get_post_revisions( $post_id ) );
		}

		$password = wp_generate_password( 24, false );
		wp_update_post( array( 'ID' => $forum_id, 'post_password' => $password ) );

		foreach ( array( $topic_id, $reply_id ) as $post_id ) {
			$this->assertInstanceOf( 'IXR_Error', $this->xmlrpc_server->wp_getPost( array( 1, $this->username, $this->password, $post_id ) ) );
			$this->assertInstanceOf( 'IXR_Error', $this->xmlrpc_server->wp_getRevisions( array( 1, $this->username, $this->password, $post_id ) ) );
			$this->assertInstanceOf( 'IXR_Error', $this->edit_post( $post_id, array( 'post_content' => 'Blocked overwrite.' ) ) );
			$this->assertSame( 'Updated content.', get_post_field( 'post_content', $post_id ) );
		}

		require_once ABSPATH . WPINC . '/class-phpass.php';
		$cookie     = 'wp-postpass_' . COOKIEHASH;
		$old_cookie = isset( $_COOKIE[ $cookie ] ) ? $_COOKIE[ $cookie ] : null;
		$hasher     = new PasswordHash( 8, true );
		$_COOKIE[ $cookie ] = $hasher->HashPassword( 'incorrect-password' );
		$this->assertInstanceOf( 'IXR_Error', $this->xmlrpc_server->wp_getPost( array( 1, $this->username, $this->password, $topic_id ) ) );
		$_COOKIE[ $cookie ] = $hasher->HashPassword( $password );

		try {
			foreach ( array( $topic_id, $reply_id ) as $post_id ) {
				$this->assertIsArray( $this->xmlrpc_server->wp_getPost( array( 1, $this->username, $this->password, $post_id ) ) );
				$this->assertIsArray( $this->xmlrpc_server->wp_getRevisions( array( 1, $this->username, $this->password, $post_id ) ) );
				$this->assertTrue( $this->edit_post( $post_id, array( 'post_content' => 'Authorized edit.' ) ) );
				$this->assertSame( 'Authorized edit.', get_post_field( 'post_content', $post_id ) );
			}
		} finally {
			if ( null === $old_cookie ) {
				unset( $_COOKIE[ $cookie ] );
			} else {
				$_COOKIE[ $cookie ] = $old_cookie;
			}
		}

		bbp_set_user_role( $user_id, bbp_get_moderator_role() );
		$this->assertIsArray( $this->xmlrpc_server->wp_getPost( array( 1, $this->username, $this->password, $topic_id ) ) );
	}

	/**
	 * @covers ::bbp_map_xmlrpc_meta_caps
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_other_xmlrpc_editor_requires_new_forum_password() {
		if ( ! defined( 'XMLRPC_REQUEST' ) ) {
			define( 'XMLRPC_REQUEST', true );
		}

		$editor_id = $this->create_participant();
		$author_id = $this->factory->user->create();
		$forum_id  = $this->factory->forum->create();
		$topic_id  = $this->factory->topic->create(
			array( 'post_author' => $author_id, 'post_parent' => $forum_id, 'topic_meta' => array( 'forum_id' => $forum_id ) )
		);

		get_userdata( $editor_id )->add_cap( 'edit_others_topics' );
		$this->assertIsArray( $this->xmlrpc_server->wp_getPost( array( 1, $this->username, $this->password, $topic_id ) ) );

		wp_update_post( array( 'ID' => $forum_id, 'post_password' => wp_generate_password( 24, false ) ) );
		$this->assertInstanceOf( 'IXR_Error', $this->xmlrpc_server->wp_getPost( array( 1, $this->username, $this->password, $topic_id ) ) );
	}

	/**
	 * Older XML-RPC aliases check edit_post before firing xmlrpc_call.
	 *
	 * @covers ::bbp_map_xmlrpc_meta_caps
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_xmlrpc_alias_requires_password_before_call_action() {
		if ( ! defined( 'XMLRPC_REQUEST' ) ) {
			define( 'XMLRPC_REQUEST', true );
		}

		$user_id  = $this->create_participant();
		$forum_id = $this->factory->forum->create();
		$topic_id = $this->factory->topic->create(
			array( 'post_author' => $user_id, 'post_parent' => $forum_id, 'post_content' => 'Saved content.', 'topic_meta' => array( 'forum_id' => $forum_id ) )
		);
		wp_update_post( array( 'ID' => $forum_id, 'post_password' => wp_generate_password( 24, false ) ) );

		$this->assertInstanceOf( 'IXR_Error', $this->xmlrpc_server->mw_getPost( array( $topic_id, $this->username, $this->password ) ) );
		$this->assertInstanceOf( 'IXR_Error', $this->xmlrpc_server->blogger_getPost( array( 1, $topic_id, $this->username, $this->password ) ) );
	}

	/**
	 * mt.publishPost must not change a bbPress post status directly.
	 *
	 * @covers ::bbp_validate_xmlrpc_post
	 */
	public function test_mt_publish_post_cannot_publish_nonpublic_topic() {
		$user_id  = $this->create_author_participant();
		$forum_id = $this->factory->forum->create();
		$topic_id = $this->factory->topic->create(
			array(
				'post_author' => $user_id,
				'post_parent' => $forum_id,
				'post_status' => bbp_get_pending_status_id(),
				'topic_meta'  => array( 'forum_id' => $forum_id ),
			)
		);
		$closed_topic_id = $this->factory->topic->create(
			array(
				'post_author' => $user_id,
				'post_parent' => $forum_id,
				'post_status' => bbp_get_closed_status_id(),
				'topic_meta'  => array( 'forum_id' => $forum_id ),
			)
		);
		$public_topic_id = $this->factory->topic->create(
			array(
				'post_author' => $user_id,
				'post_parent' => $forum_id,
				'topic_meta'  => array( 'forum_id' => $forum_id ),
			)
		);
		$post_id = $this->factory->post->create(
			array(
				'post_author' => $user_id,
				'post_status' => 'draft',
			)
		);

		$topic_result = $this->xmlrpc_server->mt_publishPost( array( $topic_id, $this->username, $this->password ) );

		$this->assertInstanceOf( 'IXR_Error', $topic_result );
		$this->assertSame( bbp_get_pending_status_id(), get_post_status( $topic_id ) );

		$closed_result = $this->xmlrpc_server->mt_publishPost( array( $closed_topic_id, $this->username, $this->password ) );

		$this->assertInstanceOf( 'IXR_Error', $closed_result );
		$this->assertSame( bbp_get_closed_status_id(), get_post_status( $closed_topic_id ) );

		$public_result = $this->xmlrpc_server->mt_publishPost( array( $public_topic_id, $this->username, $this->password ) );

		$this->assertSame( $public_topic_id, $public_result );
		$this->assertSame( bbp_get_public_status_id(), get_post_status( $public_topic_id ) );

		$post_result = $this->xmlrpc_server->mt_publishPost( array( $post_id, $this->username, $this->password ) );

		$this->assertSame( $post_id, $post_result );
		$this->assertSame( 'publish', get_post_status( $post_id ) );
	}

	/**
	 * @coversNothing
	 */
	public function test_participant_cannot_delete_topic() {
		$user_id  = $this->create_participant();
		$forum_id = $this->factory->forum->create();
		$topic_id = $this->factory->topic->create(
			array(
				'post_author' => $user_id,
				'post_parent' => $forum_id,
				'topic_meta'  => array( 'forum_id' => $forum_id ),
			)
		);

		$result = $this->xmlrpc_server->wp_deletePost(
			array( 1, $this->username, $this->password, $topic_id )
		);

		$this->assertInstanceOf( 'IXR_Error', $result );
		$this->assertSame( bbp_get_topic_post_type(), get_post_type( $topic_id ) );
	}

	/**
	 * @coversNothing
	 */
	public function test_participant_cannot_restore_topic_revision() {
		$user_id  = $this->create_participant();
		$forum_id = $this->factory->forum->create();
		$topic_id = $this->factory->topic->create(
			array(
				'post_author'  => $user_id,
				'post_content' => 'Original topic content.',
				'post_parent'  => $forum_id,
				'topic_meta'   => array( 'forum_id' => $forum_id ),
			)
		);

		wp_update_post(
			array(
				'ID'           => $topic_id,
				'post_content' => 'Updated topic content.',
			)
		);
		update_option( 'disallowed_keys', 'topic content' );
		$revisions   = wp_get_post_revisions( $topic_id );
		$revision_id = key( $revisions );
		$result      = $this->xmlrpc_server->wp_restoreRevision(
			array( 1, $this->username, $this->password, $revision_id )
		);

		$this->assertInstanceOf( 'IXR_Error', $result );
		$this->assertSame( 'Updated topic content.', get_post_field( 'post_content', $topic_id ) );
	}

	/**
	 * @covers ::bbp_validate_xmlrpc_post
	 */
	public function test_participant_can_restore_allowed_topic_revision() {
		$user_id  = $this->create_participant();
		$forum_id = $this->factory->forum->create();
		$topic_id = $this->factory->topic->create(
			array(
				'post_author'  => $user_id,
				'post_content' => 'Original topic content.',
				'post_parent'  => $forum_id,
				'topic_meta'   => array( 'forum_id' => $forum_id ),
			)
		);

		wp_update_post(
			array(
				'ID'           => $topic_id,
				'post_content' => 'Updated topic content.',
			)
		);
		$revisions   = wp_get_post_revisions( $topic_id );
		$revision_id = key( $revisions );
		$result      = $this->xmlrpc_server->wp_restoreRevision(
			array( 1, $this->username, $this->password, $revision_id )
		);

		$this->assertTrue( $result );
	}

	/**
	 * @covers ::bbp_validate_xmlrpc_post
	 */
	public function test_participant_cannot_move_topic_to_closed_forum() {
		$user_id = $this->create_participant();
		$open_id = $this->factory->forum->create();
		$closed_id = $this->factory->forum->create(
			array( 'forum_meta' => array( 'status' => 'closed' ) )
		);
		$topic_id = $this->factory->topic->create(
			array(
				'post_author' => $user_id,
				'post_parent' => $open_id,
				'topic_meta'  => array( 'forum_id' => $open_id ),
			)
		);

		$result = $this->edit_post( $topic_id, array( 'post_parent' => $closed_id ) );

		$this->assertInstanceOf( 'IXR_Error', $result );
		$this->assertSame( $open_id, wp_get_post_parent_id( $topic_id ) );
	}

	/**
	 * @covers ::bbp_validate_xmlrpc_post
	 */
	public function test_participant_cannot_move_reply_to_closed_topic() {
		$user_id = $this->create_participant();
		$forum_id = $this->factory->forum->create();
		$open_topic_id = $this->factory->topic->create(
			array(
				'post_author' => $user_id,
				'post_parent' => $forum_id,
				'topic_meta'  => array( 'forum_id' => $forum_id ),
			)
		);
		$closed_topic_id = $this->factory->topic->create(
			array(
				'post_author' => $user_id,
				'post_parent' => $forum_id,
				'post_status' => bbp_get_closed_status_id(),
				'topic_meta'  => array( 'forum_id' => $forum_id ),
			)
		);
		$reply_id = $this->factory->reply->create(
			array(
				'post_author' => $user_id,
				'post_parent' => $open_topic_id,
				'reply_meta'  => array(
					'forum_id' => $forum_id,
					'topic_id' => $open_topic_id,
				),
			)
		);

		$result = $this->edit_post( $reply_id, array( 'post_parent' => $closed_topic_id ) );

		$this->assertInstanceOf( 'IXR_Error', $result );
		$this->assertSame( $open_topic_id, wp_get_post_parent_id( $reply_id ) );
	}

	/**
	 * @covers ::bbp_validate_xmlrpc_post
	 */
	public function test_keymaster_cannot_bypass_topic_move_lifecycle() {
		$user_id     = $this->create_keymaster();
		$old_forum_id = $this->factory->forum->create();
		$new_forum_id = $this->factory->forum->create();
		$topic_id     = $this->factory->topic->create(
			array(
				'post_author' => $user_id,
				'post_parent' => $old_forum_id,
				'topic_meta'  => array( 'forum_id' => $old_forum_id ),
			)
		);

		$result = $this->edit_post( $topic_id, array( 'post_parent' => $new_forum_id ) );

		$this->assertInstanceOf( 'IXR_Error', $result );
		$this->assertSame( $old_forum_id, wp_get_post_parent_id( $topic_id ) );
		$this->assertSame( $old_forum_id, bbp_get_topic_forum_id( $topic_id ) );
	}

	/**
	 * @covers ::bbp_validate_xmlrpc_post
	 */
	public function test_strict_moderation_rejects_topic_edit() {
		$user_id = $this->create_participant();
		$forum_id = $this->factory->forum->create();
		$topic_id = $this->factory->topic->create(
			array(
				'post_author' => $user_id,
				'post_parent' => $forum_id,
				'topic_meta'  => array( 'forum_id' => $forum_id ),
			)
		);

		update_option( 'disallowed_keys', 'blocked phrase' );
		$result = $this->edit_post( $topic_id, array( 'post_content' => 'A blocked phrase.' ) );

		$this->assertInstanceOf( 'IXR_Error', $result );
		$this->assertNotSame( 'A blocked phrase.', get_post_field( 'post_content', $topic_id ) );
	}

	/**
	 * @covers ::bbp_xmlrpc_wp_insert_post_data
	 */
	public function test_moderation_holds_topic_edit_for_review() {
		$user_id = $this->create_participant();
		$forum_id = $this->factory->forum->create();
		$topic_id = $this->factory->topic->create(
			array(
				'post_author' => $user_id,
				'post_parent' => $forum_id,
				'topic_meta'  => array( 'forum_id' => $forum_id ),
			)
		);

		update_option( 'moderation_keys', 'review phrase' );
		$result = $this->edit_post( $topic_id, array( 'post_content' => 'A review phrase.' ) );

		$this->assertNotInstanceOf( 'IXR_Error', $result );
		$this->assertSame( bbp_get_pending_status_id(), get_post_status( $topic_id ) );
	}

	/**
	 * @coversNothing
	 */
	public function test_participant_cannot_create_topic() {
		$user_id = $this->create_participant();
		$forum_id = $this->factory->forum->create();

		$result = $this->new_post(
			array(
				'post_author'  => $user_id,
				'post_parent'  => $forum_id,
				'post_status'  => bbp_get_public_status_id(),
				'post_type'    => bbp_get_topic_post_type(),
				'post_title'   => 'XML-RPC topic',
				'post_content' => 'XML-RPC topic content.',
			)
		);

		$this->assertInstanceOf( 'IXR_Error', $result );
	}

	/**
	 * The site-wide status also applies to XML-RPC topic creation.
	 *
	 * @covers ::bbp_validate_xmlrpc_post
	 */
	public function test_closed_blocks_author_participant_topic_creation() {
		$user_id  = $this->create_author_participant();
		$forum_id = $this->factory->forum->create();
		update_option( '_bbp_forums_status', 'closed' );

		$result = $this->new_post(
			array(
				'post_author'  => $user_id,
				'post_parent'  => $forum_id,
				'post_status'  => bbp_get_public_status_id(),
				'post_type'    => bbp_get_topic_post_type(),
				'post_title'   => 'Closed XML-RPC topic',
				'post_content' => 'Cannot post while closed.',
			)
		);

		$this->assertInstanceOf( 'IXR_Error', $result );
		$this->assertSame( bbp_get_topic_post_type(), $GLOBALS['bbp_xmlrpc_error_post_type'] );
	}

	/**
	 * @covers ::bbp_map_forum_meta_caps
	 */
	public function test_frozen_blocks_keymaster_forum_creation() {
		$this->create_keymaster();
		update_option( '_bbp_forums_status', 'frozen' );

		$result = $this->new_post(
			array(
				'post_status'  => bbp_get_public_status_id(),
				'post_type'    => bbp_get_forum_post_type(),
				'post_title'   => 'Ordinary forum',
				'post_content' => 'Cannot create while frozen.',
			)
		);

		$this->assertInstanceOf( 'IXR_Error', $result );
	}

	/**
	 * @covers ::bbp_validate_xmlrpc_post
	 */
	public function test_moderator_create_obeys_strict_block_list() {
		$user_id = $this->create_moderator();
		$forum_id = $this->factory->forum->create();
		update_option( 'disallowed_keys', 'blocked phrase' );
		$data = array(
			'post_author'  => $user_id,
			'post_parent'  => $forum_id,
			'post_status'  => bbp_get_public_status_id(),
			'post_type'    => bbp_get_topic_post_type(),
			'post_title'   => 'XML-RPC topic',
			'post_content' => 'A blocked phrase.',
		);

		$this->assertInstanceOf( 'IXR_Error', $this->new_post( $data ) );
		$data['post_content'] = 'Clean moderator topic.';
		$this->assertIsNumeric( $this->new_post( $data ) );
	}

	/**
	 * @coversNothing
	 */
	public function test_participant_cannot_create_duplicate_topics() {
		$user_id = $this->create_participant();
		$forum_id = $this->factory->forum->create();
		$data = array(
			'post_author'  => $user_id,
			'post_parent'  => $forum_id,
			'post_status'  => bbp_get_public_status_id(),
			'post_type'    => bbp_get_topic_post_type(),
			'post_title'   => 'Duplicate XML-RPC topic',
			'post_content' => 'Duplicate XML-RPC topic content.',
		);

		$first  = $this->new_post( $data );
		$second = $this->new_post( $data );

		$this->assertInstanceOf( 'IXR_Error', $first );
		$this->assertInstanceOf( 'IXR_Error', $second );
	}

	/**
	 * XML-RPC edits must honor the bbPress edit window.
	 *
	 * @covers ::bbp_validate_xmlrpc_post
	 */
	public function test_participant_cannot_edit_topic_or_reply_after_lock() {
		$user_id  = $this->create_participant();
		$forum_id = $this->factory->forum->create();
		$topic_id = $this->factory->topic->create(
			array(
				'post_author'   => $user_id,
				'post_parent'   => $forum_id,
				'post_date'     => '2020-01-01 00:00:00',
				'post_date_gmt' => '2020-01-01 00:00:00',
				'topic_meta'    => array( 'forum_id' => $forum_id ),
			)
		);
		$reply_id = $this->factory->reply->create(
			array(
				'post_author'   => $user_id,
				'post_parent'   => $topic_id,
				'post_date'     => '2020-01-01 00:00:00',
				'post_date_gmt' => '2020-01-01 00:00:00',
				'reply_meta'    => array( 'forum_id' => $forum_id, 'topic_id' => $topic_id ),
			)
		);

		foreach ( array( $topic_id, $reply_id ) as $post_id ) {
			$result = $this->edit_post( $post_id, array( 'post_content' => 'Late XML-RPC edit.' ) );
			$this->assertInstanceOf( 'IXR_Error', $result );
			$this->assertNotSame( 'Late XML-RPC edit.', get_post_field( 'post_content', $post_id ) );
		}
	}

	/**
	 * XML-RPC clients cannot move the author edit window by changing dates.
	 *
	 * @covers ::bbp_validate_xmlrpc_post
	 * @covers ::bbp_xmlrpc_post_date_changed
	 */
	public function test_participant_cannot_change_topic_or_reply_date() {
		update_option( 'timezone_string', 'America/Chicago' );
		update_option( '_bbp_edit_lock', 0 );

		$user_id  = $this->create_participant();
		$forum_id = $this->factory->forum->create();
		$topic_id = $this->factory->topic->create(
			array(
				'post_author' => $user_id,
				'post_parent' => $forum_id,
				'topic_meta'  => array( 'forum_id' => $forum_id ),
			)
		);
		$reply_id = $this->factory->reply->create(
			array(
				'post_author' => $user_id,
				'post_parent' => $topic_id,
				'reply_meta'  => array( 'forum_id' => $forum_id, 'topic_id' => $topic_id ),
			)
		);

		foreach ( array( $topic_id => 'post_date', $reply_id => 'post_date_gmt' ) as $post_id => $field ) {
			$original = get_post_field( $field, $post_id );
			$result   = $this->edit_post( $post_id, array( $field => new IXR_Date( strtotime( '+1 day' ) ) ) );

			$this->assertInstanceOf( 'IXR_Error', $result );
			$this->assertSame( $original, get_post_field( $field, $post_id ) );
		}

		$unchanged = new IXR_Date( mysql2date( 'Ymd\TH:i:s', get_post_field( 'post_date', $topic_id ), false ) );
		$result    = $this->edit_post( $topic_id, array( 'post_date' => $unchanged ) );

		$this->assertNotInstanceOf( 'IXR_Error', $result );
	}

	/**
	 * XML-RPC clients may send back an unchanged repeated local hour.
	 *
	 * @covers ::bbp_validate_xmlrpc_post
	 * @covers ::bbp_xmlrpc_post_date_changed
	 * @covers ::bbp_xmlrpc_wp_insert_post_data
	 * @dataProvider data_unchanged_post_dates
	 *
	 * @param string $post_date     Local post date.
	 * @param string $post_date_gmt GMT post date.
	 */
	public function test_participant_can_roundtrip_unchanged_post_date( $post_date, $post_date_gmt ) {
		update_option( 'timezone_string', 'America/Chicago' );
		update_option( '_bbp_edit_lock', 0 );

		$user_id  = $this->create_participant();
		$forum_id = $this->factory->forum->create();
		$topic_id = $this->factory->topic->create(
			array(
				'post_author'   => $user_id,
				'post_parent'   => $forum_id,
				'post_date'     => $post_date,
				'post_date_gmt' => $post_date_gmt,
				'topic_meta'    => array( 'forum_id' => $forum_id ),
			)
		);

		$local_result = $this->edit_post( $topic_id, array( 'post_date' => new IXR_Date( mysql2date( 'Ymd\\TH:i:s', $post_date, false ) ) ) );
		$gmt_result   = $this->edit_post( $topic_id, array( 'post_date_gmt' => new IXR_Date( mysql2date( 'Ymd\\TH:i:s', $post_date_gmt, false ) ) ) );

		$this->assertNotInstanceOf( 'IXR_Error', $local_result );
		$this->assertNotInstanceOf( 'IXR_Error', $gmt_result );
		$this->assertSame( $post_date, get_post_field( 'post_date', $topic_id ) );
		$this->assertSame( $post_date_gmt, get_post_field( 'post_date_gmt', $topic_id ) );
	}

	/**
	 * Data provider for unchanged post dates exposed by XML-RPC.
	 *
	 * @return array[] Local and GMT dates.
	 */
	public function data_unchanged_post_dates() {
		return array(
			'first repeated hour'  => array( '2025-11-02 01:30:00', '2025-11-02 06:30:00' ),
			'second repeated hour' => array( '2025-11-02 01:30:00', '2025-11-02 07:30:00' ),
			'ordinary date'        => array( '2025-07-01 12:30:00', '2025-07-01 17:30:00' ),
		);
	}

	/**
	 * A derived GMT date for a legacy pending post must round-trip unchanged.
	 *
	 * @covers ::bbp_validate_xmlrpc_post
	 * @covers ::bbp_xmlrpc_post_date_changed
	 * @covers ::bbp_xmlrpc_wp_insert_post_data
	 */
	public function test_participant_can_roundtrip_legacy_zero_gmt_date() {
		global $wpdb;

		update_option( 'timezone_string', 'America/Chicago' );
		update_option( '_bbp_edit_lock', 0 );

		$user_id  = $this->create_participant();
		$forum_id = $this->factory->forum->create();
		$topic_id = $this->factory->topic->create( array( 'post_author' => $user_id, 'post_parent' => $forum_id, 'post_status' => bbp_get_pending_status_id(), 'topic_meta' => array( 'forum_id' => $forum_id ) ) );
		$post_date = get_post_field( 'post_date', $topic_id );

		$wpdb->update( $wpdb->posts, array( 'post_date_gmt' => '0000-00-00 00:00:00' ), array( 'ID' => $topic_id ) );
		clean_post_cache( $topic_id );

		$result = $this->edit_post( $topic_id, array( 'post_date_gmt' => new IXR_Date( mysql2date( 'Ymd\\TH:i:s', get_gmt_from_date( $post_date ), false ) ), 'post_content' => 'Updated content.' ) );

		$this->assertNotInstanceOf( 'IXR_Error', $result );
		$this->assertSame( 'Updated content.', get_post_field( 'post_content', $topic_id ) );
		$this->assertSame( $post_date, get_post_field( 'post_date', $topic_id ) );
	}

	/**
	 * An unchanged offset date must preserve its stored repeated-hour occurrence.
	 *
	 * @covers ::bbp_validate_xmlrpc_post
	 * @covers ::bbp_xmlrpc_post_date_changed
	 * @covers ::bbp_xmlrpc_wp_insert_post_data
	 */
	public function test_participant_can_roundtrip_unchanged_offset_date() {
		update_option( 'timezone_string', 'America/Chicago' );
		update_option( '_bbp_edit_lock', 0 );

		$user_id  = $this->create_participant();
		$forum_id = $this->factory->forum->create();
		$topic_id = $this->factory->topic->create(
			array(
				'post_author'   => $user_id,
				'post_parent'   => $forum_id,
				'post_date'     => '2025-11-02 01:30:00',
				'post_date_gmt' => '2025-11-02 07:30:00',
				'topic_meta'    => array( 'forum_id' => $forum_id ),
			)
		);

		foreach ( array( '-06:00', '-05:00' ) as $offset ) {
			$result = $this->edit_post( $topic_id, array( 'post_date' => new IXR_Date( '20251102T01:30:00' . $offset ) ) );

			$this->assertNotInstanceOf( 'IXR_Error', $result );
		}

		$this->assertSame( '2025-11-02 01:30:00', get_post_field( 'post_date', $topic_id ) );
		$this->assertSame( '2025-11-02 07:30:00', get_post_field( 'post_date_gmt', $topic_id ) );

		$result = $this->edit_post( $topic_id, array( 'post_date_gmt' => new IXR_Date( '20251102T06:30:00' ) ) );

		$this->assertInstanceOf( 'IXR_Error', $result );
		$this->assertSame( '2025-11-02 07:30:00', get_post_field( 'post_date_gmt', $topic_id ) );
	}

	/**
	 * @covers ::bbp_map_xmlrpc_meta_caps
	 */
	public function test_map_xmlrpc_meta_caps_denies_only_failed_post_and_post_type() {
		$GLOBALS['bbp_xmlrpc_error_post_id']   = 123;
		$GLOBALS['bbp_xmlrpc_error_post_type'] = bbp_get_topic_post_type();

		$this->assertSame( array( 'edit_posts', 'do_not_allow' ), bbp_map_xmlrpc_meta_caps( array( 'edit_posts' ), 'edit_post', 7, array( 123 ) ) );
		$this->assertSame( array( 'edit_topics', 'do_not_allow' ), bbp_map_xmlrpc_meta_caps( array( 'edit_topics' ), 'edit_topic', 7, array( 123 ) ) );
		$this->assertSame( array( 'edit_posts' ), bbp_map_xmlrpc_meta_caps( array( 'edit_posts' ), 'edit_post', 7, array( 456 ) ) );
		$this->assertSame( array( 'edit_posts' ), bbp_map_xmlrpc_meta_caps( array( 'edit_posts' ), 'edit_post', 7 ) );
		$this->assertSame( array( 'edit_topics', 'do_not_allow' ), bbp_map_xmlrpc_meta_caps( array( 'edit_topics' ), 'edit_topics', 7 ) );
		$this->assertSame( array( 'edit_replies' ), bbp_map_xmlrpc_meta_caps( array( 'edit_replies' ), 'edit_replies', 7 ) );

		$GLOBALS['bbp_xmlrpc_error_post_type'] = bbp_get_reply_post_type();

		$this->assertSame( array( 'edit_replies', 'do_not_allow' ), bbp_map_xmlrpc_meta_caps( array( 'edit_replies' ), 'edit_reply', 7, array( 123 ) ) );
		$this->assertSame( array( 'edit_replies', 'do_not_allow' ), bbp_map_xmlrpc_meta_caps( array( 'edit_replies' ), 'edit_replies', 7 ) );
		$this->assertSame( array( 'edit_topics' ), bbp_map_xmlrpc_meta_caps( array( 'edit_topics' ), 'edit_topics', 7 ) );
	}

	/**
	 * @covers ::bbp_validate_xmlrpc_post
	 */
	public function test_keymaster_can_edit_topic_after_lock() {
		$user_id  = $this->create_keymaster();
		$forum_id = $this->factory->forum->create();
		$topic_id = $this->factory->topic->create(
			array(
				'post_author'   => $user_id,
				'post_parent'   => $forum_id,
				'post_date'     => '2020-01-01 00:00:00',
				'post_date_gmt' => '2020-01-01 00:00:00',
				'topic_meta'    => array( 'forum_id' => $forum_id ),
			)
		);

		$result = $this->edit_post( $topic_id, array( 'post_content' => 'Keymaster XML-RPC edit.' ) );

		$this->assertNotInstanceOf( 'IXR_Error', $result );
		$this->assertSame( 'Keymaster XML-RPC edit.', get_post_field( 'post_content', $topic_id ) );

		$date_result = $this->edit_post( $topic_id, array( 'post_date_gmt' => new IXR_Date( '20200102T00:00:00' ) ) );

		$this->assertNotInstanceOf( 'IXR_Error', $date_result );
		$this->assertSame( '2020-01-02 00:00:00', get_post_field( 'post_date_gmt', $topic_id ) );
	}

	/**
	 * @covers ::bbp_validate_xmlrpc_post
	 */
	public function test_participant_can_edit_topic_content_in_open_forum() {
		$user_id  = $this->create_participant();
		$forum_id = $this->factory->forum->create();
		$topic_id = $this->factory->topic->create(
			array(
				'post_author' => $user_id,
				'post_parent' => $forum_id,
				'topic_meta'  => array( 'forum_id' => $forum_id ),
			)
		);

		$result = $this->edit_post( $topic_id, array( 'post_content' => 'Updated topic content.' ) );

		$this->assertNotInstanceOf( 'IXR_Error', $result );
		$this->assertSame( 'Updated topic content.', get_post_field( 'post_content', $topic_id ) );
	}

	/**
	 * @covers ::bbp_validate_xmlrpc_post
	 */
	public function test_participant_can_edit_topic_and_reply_in_closed_forum() {
		$user_id        = $this->create_participant();
		$topic_owner_id = $this->factory->user->create( array( 'role' => bbp_get_participant_role() ) );
		$forum_id       = $this->factory->forum->create(
			array( 'forum_meta' => array( 'status' => 'closed' ) )
		);
		$topic_id = $this->factory->topic->create(
			array(
				'post_author' => $user_id,
				'post_parent' => $forum_id,
				'topic_meta'  => array( 'forum_id' => $forum_id ),
			)
		);
		$reply_topic_id = $this->factory->topic->create(
			array(
				'post_author' => $topic_owner_id,
				'post_parent' => $forum_id,
				'topic_meta'  => array( 'forum_id' => $forum_id ),
			)
		);
		$reply_id = $this->factory->reply->create(
			array(
				'post_author' => $user_id,
				'post_parent' => $reply_topic_id,
				'reply_meta'  => array(
					'forum_id' => $forum_id,
					'topic_id' => $reply_topic_id,
				),
			)
		);

		bbp_close_topic( $topic_id );
		bbp_close_topic( $reply_topic_id );

		$result = $this->edit_post( $topic_id, array( 'post_content' => 'Updated topic content.' ) );

		$this->assertNotInstanceOf( 'IXR_Error', $result );
		$this->assertSame( 'Updated topic content.', get_post_field( 'post_content', $topic_id ) );

		$result = $this->edit_post( $reply_id, array( 'post_content' => 'Updated reply content.' ) );

		$this->assertNotInstanceOf( 'IXR_Error', $result );
		$this->assertSame( 'Updated reply content.', get_post_field( 'post_content', $reply_id ) );
	}

	/**
	 * @covers ::bbp_validate_xmlrpc_post
	 */
	public function test_participant_cannot_change_topic_status() {
		$user_id  = $this->create_participant();
		$forum_id = $this->factory->forum->create();
		$topic_id = $this->factory->topic->create(
			array(
				'post_author' => $user_id,
				'post_parent' => $forum_id,
				'topic_meta'  => array( 'forum_id' => $forum_id ),
			)
		);

		$result = $this->edit_post( $topic_id, array( 'post_status' => bbp_get_closed_status_id() ) );

		$this->assertInstanceOf( 'IXR_Error', $result );
		$this->assertSame( bbp_get_public_status_id(), get_post_status( $topic_id ) );
	}

	/**
	 * @covers ::bbp_validate_xmlrpc_post
	 */
	public function test_participant_cannot_change_reply_order() {
		$user_id  = $this->create_participant();
		$forum_id = $this->factory->forum->create();
		$topic_id = $this->factory->topic->create(
			array(
				'post_author' => $user_id,
				'post_parent' => $forum_id,
				'topic_meta'  => array( 'forum_id' => $forum_id ),
			)
		);
		$reply_id = $this->factory->reply->create(
			array(
				'post_author' => $user_id,
				'post_parent' => $topic_id,
				'menu_order'  => 1,
				'reply_meta'  => array(
					'forum_id' => $forum_id,
					'topic_id' => $topic_id,
				),
			)
		);

		$result = $this->edit_post( $reply_id, array( 'menu_order' => 99 ) );

		$this->assertInstanceOf( 'IXR_Error', $result );
		$this->assertSame( 1, (int) get_post_field( 'menu_order', $reply_id ) );
	}

	/**
	 * @covers ::bbp_xmlrpc_wp_insert_post_data
	 */
	public function test_moderation_holds_reply_edit_for_review() {
		$user_id  = $this->create_participant();
		$forum_id = $this->factory->forum->create();
		$topic_id = $this->factory->topic->create(
			array(
				'post_author' => $user_id,
				'post_parent' => $forum_id,
				'topic_meta'  => array( 'forum_id' => $forum_id ),
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

		update_option( 'moderation_keys', 'review phrase' );
		$result = $this->edit_post( $reply_id, array( 'post_content' => 'A review phrase.' ) );

		$this->assertNotInstanceOf( 'IXR_Error', $result );
		$this->assertSame( bbp_get_pending_status_id(), get_post_status( $reply_id ) );
	}

	/**
	 * @covers ::bbp_validate_xmlrpc_post
	 */
	public function test_topic_edit_requires_title_and_content() {
		$user_id  = $this->create_participant();
		$forum_id = $this->factory->forum->create();
		$topic_id = $this->factory->topic->create(
			array(
				'post_author' => $user_id,
				'post_parent' => $forum_id,
				'topic_meta'  => array( 'forum_id' => $forum_id ),
			)
		);

		$title_result   = $this->edit_post( $topic_id, array( 'post_title' => '' ) );
		$content_result = $this->edit_post( $topic_id, array( 'post_content' => '' ) );

		$this->assertInstanceOf( 'IXR_Error', $title_result );
		$this->assertInstanceOf( 'IXR_Error', $content_result );
	}

	/**
	 * @covers ::bbp_validate_xmlrpc_post
	 */
	public function test_blog_post_edit_is_unchanged() {
		$user_id = $this->factory->user->create(
			array(
				'user_login' => $this->username,
				'user_pass'  => $this->password,
				'role'       => 'author',
			)
		);
		$post_id = $this->factory->post->create(
			array( 'post_author' => $user_id )
		);

		$result = $this->edit_post( $post_id, array( 'post_content' => 'Updated blog post content.' ) );

		$this->assertNotInstanceOf( 'IXR_Error', $result );
		$this->assertSame( 'Updated blog post content.', get_post_field( 'post_content', $post_id ) );
	}

	/**
	 * @covers ::bbp_validate_xmlrpc_post
	 * @dataProvider data_forum_attribute_fields
	 *
	 * @param string $field Field to update.
	 */
	public function test_per_forum_moderator_cannot_change_forum_structure_through_xmlrpc( $field ) {
		$old_parent_id = $this->factory->forum->create();
		$new_parent_id = $this->factory->forum->create();
		$forum_id      = $this->factory->forum->create(
			array(
				'post_parent' => $old_parent_id,
				'menu_order'  => 1,
			)
		);
		$values        = array(
			'post_parent' => $new_parent_id,
			'post_status' => bbp_get_private_status_id(),
			'menu_order'  => 99,
		);

		$this->create_forum_moderator( $forum_id );

		$result = $this->edit_post( $forum_id, array( $field => $values[ $field ] ) );

		$this->assertInstanceOf( 'IXR_Error', $result );
		$this->assertSame( $old_parent_id, bbp_get_forum_parent_id( $forum_id ) );
		$this->assertTrue( bbp_is_forum_public( $forum_id, false ) );
		$this->assertSame( 1, (int) get_post_field( 'menu_order', $forum_id ) );
	}

	/**
	 * Data provider for forum attribute fields exposed by XML-RPC.
	 *
	 * @return array[] Field names.
	 */
	public function data_forum_attribute_fields() {
		return array(
			array( 'post_parent' ),
			array( 'post_status' ),
			array( 'menu_order' ),
		);
	}

	/**
	 * @covers ::bbp_validate_xmlrpc_post
	 */
	public function test_global_moderator_cannot_trash_forum_through_xmlrpc() {
		$user_id  = $this->create_moderator();
		$forum_id = $this->factory->forum->create(
			array(
				'post_author' => $user_id,
				'post_status' => bbp_get_hidden_status_id(),
			)
		);

		$result = $this->edit_post( $forum_id, array( 'post_status' => 'trash' ) );

		$this->assertInstanceOf( 'IXR_Error', $result );
		$this->assertTrue( bbp_is_forum_hidden( $forum_id, false ) );
	}

	/**
	 * @covers ::bbp_validate_xmlrpc_post
	 */
	public function test_keymaster_can_trash_forum_through_xmlrpc() {
		$forum_id = $this->factory->forum->create();

		$this->create_keymaster();

		$result = $this->edit_post( $forum_id, array( 'post_status' => 'trash' ) );

		$this->assertNotInstanceOf( 'IXR_Error', $result );
		$this->assertSame( 'trash', get_post_status( $forum_id ) );
	}

	/**
	 * @covers ::bbp_validate_xmlrpc_post
	 */
	public function test_global_moderator_cannot_untrash_forum_through_xmlrpc() {
		$user_id  = $this->create_moderator();
		$forum_id = $this->factory->forum->create( array( 'post_author' => $user_id ) );

		wp_trash_post( $forum_id );

		$result = $this->edit_post( $forum_id, array( 'post_status' => 'publish' ) );

		$this->assertInstanceOf( 'IXR_Error', $result );
		$this->assertSame( 'trash', get_post_status( $forum_id ) );
	}

	/**
	 * @covers ::bbp_validate_xmlrpc_post
	 */
	public function test_forum_deleter_cannot_change_visibility_through_trash() {
		$user_id = $this->create_participant();
		$user    = get_user_by( 'id', $user_id );

		$user->add_cap( 'edit_forums' );
		$user->add_cap( 'delete_forums' );

		$hidden_id  = $this->factory->forum->create(
			array(
				'post_author' => $user_id,
				'post_status' => bbp_get_hidden_status_id(),
			)
		);
		$trashed_id = $this->factory->forum->create(
			array(
				'post_author' => $user_id,
				'post_status' => bbp_get_hidden_status_id(),
			)
		);

		$this->set_current_user( $user_id );
		$this->assertTrue( current_user_can( 'delete_forum', $hidden_id ) );
		$this->assertFalse( current_user_can( 'manage_forum_attributes', $hidden_id ) );

		$result = $this->edit_post( $hidden_id, array( 'post_status' => 'trash' ) );

		$this->assertInstanceOf( 'IXR_Error', $result );
		$this->assertTrue( bbp_is_forum_hidden( $hidden_id, false ) );

		wp_trash_post( $trashed_id );

		$result = $this->edit_post( $trashed_id, array( 'post_status' => 'publish' ) );

		$this->assertInstanceOf( 'IXR_Error', $result );
		$this->assertSame( 'trash', get_post_status( $trashed_id ) );
	}

	/**
	 * @covers ::bbp_validate_xmlrpc_post
	 */
	public function test_keymaster_can_untrash_forum_through_xmlrpc() {
		$forum_id = $this->factory->forum->create();

		wp_trash_post( $forum_id );
		$this->create_keymaster();

		$result = $this->edit_post( $forum_id, array( 'post_status' => 'publish' ) );

		$this->assertNotInstanceOf( 'IXR_Error', $result );
		$this->assertSame( 'publish', get_post_status( $forum_id ) );
	}

	/**
	 * @covers ::bbp_validate_xmlrpc_post
	 */
	public function test_keymaster_can_change_forum_structure_through_xmlrpc() {
		$old_parent_id = $this->factory->forum->create();
		$new_parent_id = $this->factory->forum->create();
		$forum_id      = $this->factory->forum->create(
			array(
				'post_parent' => $old_parent_id,
				'menu_order'  => 1,
			)
		);

		$this->create_keymaster();

		$result = $this->edit_post(
			$forum_id,
			array(
				'post_parent' => $new_parent_id,
				'post_status' => bbp_get_private_status_id(),
			)
		);

		$this->assertNotInstanceOf( 'IXR_Error', $result );
		$this->assertSame( $new_parent_id, bbp_get_forum_parent_id( $forum_id ) );
		$this->assertTrue( bbp_is_forum_private( $forum_id, false ) );
	}
}
