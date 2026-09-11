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
	public function test_participant_cannot_edit_topic_in_closed_forum() {
		$user_id  = $this->create_participant();
		$forum_id = $this->factory->forum->create(
			array( 'forum_meta' => array( 'status' => 'closed' ) )
		);
		$topic_id = $this->factory->topic->create(
			array(
				'post_author' => $user_id,
				'post_parent' => $forum_id,
				'topic_meta'  => array( 'forum_id' => $forum_id ),
			)
		);

		$result = $this->edit_post( $topic_id, array( 'post_content' => 'Updated topic content.' ) );

		$this->assertInstanceOf( 'IXR_Error', $result );
		$this->assertNotSame( 'Updated topic content.', get_post_field( 'post_content', $topic_id ) );
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
