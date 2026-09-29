<?php

/**
 * Admin topic and reply destination permissions.
 *
 * @group admin
 */
class BBP_Tests_Admin_Attributes_Security extends BBP_UnitTestCase {

	private $post;
	private $request_method;
	private $screen;

	public function setUp(): void {
		parent::setUp();

		$this->post           = $_POST;
		$this->request_method = $_SERVER['REQUEST_METHOD'];
		$this->screen         = isset( $GLOBALS['current_screen'] ) ? $GLOBALS['current_screen'] : null;
		$_POST               = array( 'action' => 'editpost', 'hidden_post_status' => 'publish' );
		$_SERVER['REQUEST_METHOD'] = 'POST';

		require_once BBP_PLUGIN_DIR . 'includes/admin/topics.php';
		require_once BBP_PLUGIN_DIR . 'includes/admin/replies.php';
		set_current_screen( 'edit-topic' );
		update_option( '_bbp_allow_forum_mods', 1 );
	}

	public function tearDown(): void {
		$_POST                    = $this->post;
		$_SERVER['REQUEST_METHOD'] = $this->request_method;
		$GLOBALS['current_screen'] = $this->screen;

		parent::tearDown();
	}

	/**
	 * @covers BBP_Topics_Admin::toggle_topic
	 * @covers BBP_Topics_Admin::row_actions
	 */
	public function test_forum_moderator_cannot_toggle_global_topic_stickiness() {
		$forum_id = $this->factory->forum->create();
		$topic_id = $this->factory->topic->create( array( 'post_parent' => $forum_id ) );
		$user_id  = $this->factory->user->create( array( 'role' => bbp_get_participant_role() ) );
		$old_get  = $_GET;

		bbp_add_moderator( $forum_id, $user_id );
		$this->set_current_user( $user_id );
		$this->assertTrue( current_user_can( 'moderate', $topic_id ) );
		$this->assertFalse( current_user_can( 'moderate' ) );
		$_SERVER['REQUEST_METHOD'] = 'GET';
		$_GET = array( 'action' => 'bbp_toggle_topic_stick', 'topic_id' => $topic_id, 'super' => '1' );
		$admin = new BBP_Topics_Admin();

		try {
			$actions = $admin->row_actions( array(), get_post( $topic_id ) );
			$this->assertStringNotContainsString( '(to front)', $actions['stick'] );
			$admin->toggle_topic();
			$this->fail( 'A forum moderator should not be able to super-stick a topic.' );
		} catch ( WPDieException $error ) {
			$this->assertStringContainsString( 'permission', $error->getMessage() );
		} finally {
			$_GET = $old_get;
		}

		$this->assertFalse( bbp_is_topic_super_sticky( $topic_id ) );

		bbp_stick_topic( $topic_id, true );
		$_GET = array( 'action' => 'bbp_toggle_topic_stick', 'topic_id' => $topic_id );

		try {
			$actions = $admin->row_actions( array(), get_post( $topic_id ) );
			$this->assertArrayNotHasKey( 'stick', $actions );
			$admin->toggle_topic();
			$this->fail( 'A forum moderator should not be able to globally unstick a topic.' );
		} catch ( WPDieException $error ) {
			$this->assertStringContainsString( 'permission', $error->getMessage() );
		} finally {
			$_GET = $old_get;
		}

		$this->assertTrue( bbp_is_topic_super_sticky( $topic_id ) );
	}

	public function test_forum_moderator_cannot_move_topic_into_category() {
		$user_id     = $this->factory->user->create( array( 'role' => bbp_get_participant_role() ) );
		$forum_id    = $this->factory->forum->create();
		$category_id = $this->factory->forum->create( array( 'forum_meta' => array( 'forum_type' => 'category' ) ) );
		$topic_id    = $this->factory->topic->create( array( 'post_author' => $user_id, 'post_parent' => $forum_id ) );

		bbp_add_moderator( $forum_id, $user_id );
		$this->set_current_user( $user_id );

		$this->assertTrue( is_admin() );
		$this->assertTrue( current_user_can( 'edit_topic', $topic_id ) );
		$this->assertTrue( bbp_is_forum_category( $category_id ) );
		new BBP_Topics_Admin();
		$_POST['parent_id']         = $category_id;
		$_POST['bbp_topic_metabox'] = wp_create_nonce( 'bbp_topic_metabox_save' );

		wp_update_post( array( 'ID' => $topic_id, 'post_parent' => $category_id ) );

		$this->assertSame( $forum_id, wp_get_post_parent_id( $topic_id ) );
		$this->assertSame( $forum_id, (int) get_post_meta( $topic_id, '_bbp_forum_id', true ) );
	}

	/**
	 * @dataProvider invalid_new_post_actions
	 */
	public function test_new_topic_cannot_be_published_in_category( $action ) {
		$user_id     = $this->factory->user->create();
		$category_id = $this->factory->forum->create( array( 'forum_meta' => array( 'forum_type' => 'category' ) ) );
		$topic_id    = $this->factory->post->create( array( 'post_type' => bbp_get_topic_post_type(), 'post_status' => 'auto-draft', 'post_author' => $user_id ) );

		bbp_set_user_role( $user_id, bbp_get_moderator_role() );
		$this->set_current_user( $user_id );
		$_POST['action'] = $action;
		$this->assertTrue( current_user_can( 'edit_topic', $topic_id ) );
		$this->assertTrue( current_user_can( 'publish_topics' ) );
		new BBP_Topics_Admin();
		$_POST['parent_id']         = $category_id;
		$_POST['bbp_topic_metabox'] = wp_create_nonce( 'bbp_topic_metabox_save' );

		try {
			wp_update_post( array( 'ID' => $topic_id, 'post_status' => 'publish', 'post_parent' => $category_id ) );
			$this->fail( 'Publishing a topic in a category should be rejected.' );
		} catch ( WPDieException $error ) {
			$this->assertSame( 403, $error->getCode() );
		}

		$this->assertSame( 0, wp_get_post_parent_id( $topic_id ) );
		$this->assertSame( 'auto-draft', get_post_status( $topic_id ) );
		$this->assertSame( 0, (int) get_post_meta( $topic_id, '_bbp_forum_id', true ) );
	}

	public function test_new_topic_can_be_published_in_readable_forum() {
		$user_id  = $this->factory->user->create();
		$forum_id = $this->factory->forum->create();
		$topic_id = $this->factory->post->create( array( 'post_type' => bbp_get_topic_post_type(), 'post_status' => 'auto-draft', 'post_author' => $user_id ) );

		bbp_set_user_role( $user_id, bbp_get_moderator_role() );
		$this->set_current_user( $user_id );
		new BBP_Topics_Admin();
		$_POST['parent_id']          = $forum_id;
		$_POST['hidden_post_status'] = 'draft';
		$_POST['bbp_topic_metabox']  = wp_create_nonce( 'bbp_topic_metabox_save' );

		wp_update_post( array( 'ID' => $topic_id, 'post_status' => 'publish', 'post_parent' => $forum_id ) );

		$this->assertSame( $forum_id, wp_get_post_parent_id( $topic_id ) );
		$this->assertSame( $forum_id, (int) get_post_meta( $topic_id, '_bbp_forum_id', true ) );
	}

	public function test_forum_moderator_cannot_move_reply_into_unmoderated_topic() {
		set_current_screen( 'edit-reply' );

		$user_id              = $this->factory->user->create( array( 'role' => bbp_get_participant_role() ) );
		$source_forum_id      = $this->factory->forum->create();
		$destination_forum_id = $this->factory->forum->create();
		$source_topic_id      = $this->factory->topic->create( array( 'post_parent' => $source_forum_id ) );
		$destination_topic_id = $this->factory->topic->create( array( 'post_parent' => $destination_forum_id ) );
		$reply_id             = $this->factory->reply->create( array( 'post_author' => $user_id, 'post_parent' => $source_topic_id ) );
		$other_reply_id       = $this->factory->reply->create( array( 'post_parent' => $destination_topic_id ) );

		bbp_add_moderator( $source_forum_id, $user_id );
		$this->set_current_user( $user_id );

		$this->assertTrue( current_user_can( 'edit_reply', $reply_id ) );
		$this->assertTrue( current_user_can( 'moderate', $source_topic_id ) );
		$this->assertFalse( current_user_can( 'moderate', $destination_topic_id ) );
		new BBP_Replies_Admin();
		$_POST['parent_id']         = $destination_topic_id;
		$_POST['bbp_forum_id']      = $destination_forum_id;
		$_POST['bbp_reply_to']      = $other_reply_id;
		$_POST['bbp_reply_metabox'] = wp_create_nonce( 'bbp_reply_metabox_save' );

		wp_update_post( array( 'ID' => $reply_id, 'post_parent' => $destination_topic_id ) );

		$this->assertSame( $source_topic_id, wp_get_post_parent_id( $reply_id ) );
		$this->assertSame( $source_topic_id, (int) get_post_meta( $reply_id, '_bbp_topic_id', true ) );
		$this->assertSame( $source_forum_id, (int) get_post_meta( $reply_id, '_bbp_forum_id', true ) );
		$this->assertSame( 0, bbp_get_reply_to( $reply_id ) );
	}

	/**
	 * @dataProvider invalid_new_post_actions
	 */
	public function test_new_reply_cannot_be_published_in_invalid_topic( $action ) {
		set_current_screen( 'edit-reply' );

		$user_id  = $this->factory->user->create();
		$topic_id = 999999;
		$reply_id = $this->factory->post->create( array( 'post_type' => bbp_get_reply_post_type(), 'post_status' => 'auto-draft', 'post_author' => $user_id ) );

		bbp_set_user_role( $user_id, bbp_get_moderator_role() );
		$this->set_current_user( $user_id );
		$_POST['action'] = $action;
		$this->assertTrue( current_user_can( 'edit_reply', $reply_id ) );
		$this->assertTrue( current_user_can( 'publish_replies' ) );
		$this->assertEmpty( bbp_get_topic( $topic_id ) );
		new BBP_Replies_Admin();
		$_POST['parent_id']         = $topic_id;
		$_POST['bbp_reply_metabox'] = wp_create_nonce( 'bbp_reply_metabox_save' );

		try {
			wp_update_post( array( 'ID' => $reply_id, 'post_status' => 'publish', 'post_parent' => $topic_id ) );
			$this->fail( 'Publishing a reply in an invalid topic should be rejected.' );
		} catch ( WPDieException $error ) {
			$this->assertSame( 403, $error->getCode() );
		}

		$this->assertSame( 0, wp_get_post_parent_id( $reply_id ) );
		$this->assertSame( 'auto-draft', get_post_status( $reply_id ) );
		$this->assertSame( 0, (int) get_post_meta( $reply_id, '_bbp_topic_id', true ) );
	}

	public function test_new_reply_can_be_published_in_readable_topic() {
		set_current_screen( 'edit-reply' );

		$user_id  = $this->factory->user->create();
		$forum_id = $this->factory->forum->create();
		$topic_id = $this->factory->topic->create( array( 'post_parent' => $forum_id ) );
		$reply_id = $this->factory->post->create( array( 'post_type' => bbp_get_reply_post_type(), 'post_status' => 'auto-draft', 'post_author' => $user_id ) );

		bbp_set_user_role( $user_id, bbp_get_moderator_role() );
		$this->set_current_user( $user_id );
		new BBP_Replies_Admin();
		$_POST['parent_id']          = $topic_id;
		$_POST['hidden_post_status'] = 'draft';
		$_POST['bbp_reply_metabox']  = wp_create_nonce( 'bbp_reply_metabox_save' );

		wp_update_post( array( 'ID' => $reply_id, 'post_status' => 'publish', 'post_parent' => $topic_id ) );

		$this->assertSame( $topic_id, wp_get_post_parent_id( $reply_id ) );
		$this->assertSame( $topic_id, (int) get_post_meta( $reply_id, '_bbp_topic_id', true ) );
		$this->assertSame( $forum_id, (int) get_post_meta( $reply_id, '_bbp_forum_id', true ) );
	}

	public function test_forum_moderator_can_move_topic_into_readable_forum() {
		$user_id              = $this->factory->user->create( array( 'role' => bbp_get_participant_role() ) );
		$source_forum_id      = $this->factory->forum->create();
		$destination_forum_id = $this->factory->forum->create();
		$topic_id             = $this->factory->topic->create( array( 'post_author' => $user_id, 'post_parent' => $source_forum_id ) );
		$reply_id             = $this->factory->reply->create( array( 'post_parent' => $topic_id ) );

		bbp_add_moderator( $source_forum_id, $user_id );
		$this->set_current_user( $user_id );

		$this->assertTrue( current_user_can( 'edit_forum', $source_forum_id ) );
		$this->assertTrue( current_user_can( 'read_forum', $destination_forum_id ) );
		new BBP_Topics_Admin();
		$_POST['parent_id']         = $destination_forum_id;
		$_POST['bbp_topic_metabox'] = wp_create_nonce( 'bbp_topic_metabox_save' );

		wp_update_post( array( 'ID' => $topic_id, 'post_parent' => $destination_forum_id ) );

		$this->assertSame( $destination_forum_id, wp_get_post_parent_id( $topic_id ) );
		$this->assertGreaterThan( 0, did_action( 'save_post' ) );
		$this->assertGreaterThan( 0, did_action( 'bbp_topic_attributes_metabox_save' ) );
		$this->assertSame( $destination_forum_id, (int) get_post_meta( $topic_id, '_bbp_forum_id', true ) );
		$this->assertSame( $destination_forum_id, (int) get_post_meta( $reply_id, '_bbp_forum_id', true ) );
		$this->assertSame( 0, bbp_get_forum_topic_count( $source_forum_id, true, true ) );
		$this->assertSame( 1, bbp_get_forum_topic_count( $destination_forum_id, true, true ) );
		$this->assertSame( 0, bbp_get_forum_reply_count( $source_forum_id, true, true ) );
		$this->assertSame( 1, bbp_get_forum_reply_count( $destination_forum_id, true, true ) );
	}

	public function test_forum_moderator_cannot_move_topic_into_hidden_forum() {
		$user_id              = $this->factory->user->create( array( 'role' => bbp_get_participant_role() ) );
		$source_forum_id      = $this->factory->forum->create();
		$destination_forum_id = $this->factory->forum->create();
		$topic_id             = $this->factory->topic->create( array( 'post_author' => $user_id, 'post_parent' => $source_forum_id ) );

		bbp_hide_forum( $destination_forum_id );
		bbp_add_moderator( $source_forum_id, $user_id );
		$this->set_current_user( $user_id );
		new BBP_Topics_Admin();

		$this->assertFalse( current_user_can( 'read_forum', $destination_forum_id ) );
		$_POST['parent_id']         = $destination_forum_id;
		$_POST['bbp_topic_metabox'] = wp_create_nonce( 'bbp_topic_metabox_save' );

		wp_update_post( array( 'ID' => $topic_id, 'post_parent' => $destination_forum_id ) );

		$this->assertSame( $source_forum_id, wp_get_post_parent_id( $topic_id ) );
		$this->assertSame( $source_forum_id, (int) get_post_meta( $topic_id, '_bbp_forum_id', true ) );
	}

	public function test_inline_save_cannot_move_topic() {
		$user_id              = $this->factory->user->create( array( 'role' => bbp_get_participant_role() ) );
		$source_forum_id      = $this->factory->forum->create();
		$destination_forum_id = $this->factory->forum->create();
		$topic_id             = $this->factory->topic->create( array( 'post_author' => $user_id, 'post_parent' => $source_forum_id ) );

		bbp_add_moderator( $source_forum_id, $user_id );
		$this->set_current_user( $user_id );
		$this->assertTrue( current_user_can( 'edit_topic', $topic_id ) );
		new BBP_Topics_Admin();
		$_POST['action'] = 'inline-save';

		wp_update_post( array( 'ID' => $topic_id, 'post_parent' => $destination_forum_id ) );

		$this->assertSame( $source_forum_id, wp_get_post_parent_id( $topic_id ) );
		$this->assertSame( $source_forum_id, (int) get_post_meta( $topic_id, '_bbp_forum_id', true ) );
	}

	public function test_inline_save_cannot_move_reply() {
		set_current_screen( 'edit-reply' );

		$user_id              = $this->factory->user->create( array( 'role' => bbp_get_participant_role() ) );
		$source_forum_id      = $this->factory->forum->create();
		$destination_forum_id = $this->factory->forum->create();
		$source_topic_id      = $this->factory->topic->create( array( 'post_parent' => $source_forum_id ) );
		$destination_topic_id = $this->factory->topic->create( array( 'post_parent' => $destination_forum_id ) );
		$reply_id             = $this->factory->reply->create( array( 'post_author' => $user_id, 'post_parent' => $source_topic_id ) );

		bbp_add_moderator( $source_forum_id, $user_id );
		$this->set_current_user( $user_id );
		$this->assertTrue( current_user_can( 'edit_reply', $reply_id ) );
		new BBP_Replies_Admin();
		$_POST['action'] = 'inline-save';

		wp_update_post( array( 'ID' => $reply_id, 'post_parent' => $destination_topic_id ) );

		$this->assertSame( $source_topic_id, wp_get_post_parent_id( $reply_id ) );
		$this->assertSame( $source_topic_id, (int) get_post_meta( $reply_id, '_bbp_topic_id', true ) );
	}

	public function test_admin_topic_move_requires_metabox_nonce() {
		$user_id              = $this->factory->user->create();
		$source_forum_id      = $this->factory->forum->create();
		$destination_forum_id = $this->factory->forum->create();
		$topic_id             = $this->factory->topic->create( array( 'post_parent' => $source_forum_id ) );

		bbp_set_user_role( $user_id, bbp_get_moderator_role() );
		$this->set_current_user( $user_id );
		new BBP_Topics_Admin();

		$this->assertTrue( current_user_can( 'edit_topic', $topic_id ) );
		$_POST['parent_id'] = $destination_forum_id;

		wp_update_post( array( 'ID' => $topic_id, 'post_parent' => $destination_forum_id ) );

		$this->assertSame( $source_forum_id, wp_get_post_parent_id( $topic_id ) );
	}

	public function test_moderator_can_move_reply_into_moderated_topic() {
		set_current_screen( 'edit-reply' );

		$user_id              = $this->factory->user->create();
		$source_forum_id      = $this->factory->forum->create();
		$destination_forum_id = $this->factory->forum->create();
		$source_topic_id      = $this->factory->topic->create( array( 'post_parent' => $source_forum_id ) );
		$destination_topic_id = $this->factory->topic->create( array( 'post_parent' => $destination_forum_id ) );
		$reply_id             = $this->factory->reply->create( array( 'post_parent' => $source_topic_id ) );
		$reply_to             = $this->factory->reply->create( array( 'post_parent' => $destination_topic_id ) );

		bbp_set_user_role( $user_id, bbp_get_moderator_role() );
		$this->set_current_user( $user_id );

		$this->assertTrue( current_user_can( 'moderate', $source_topic_id ) );
		$this->assertTrue( current_user_can( 'moderate', $destination_topic_id ) );
		new BBP_Replies_Admin();
		$_POST['parent_id']         = $destination_topic_id;
		$_POST['bbp_forum_id']      = $source_forum_id;
		$_POST['bbp_reply_to']      = $reply_to;
		$_POST['bbp_reply_metabox'] = wp_create_nonce( 'bbp_reply_metabox_save' );
		$saved_topic_ids = array();
		add_action( 'bbp_reply_attributes_metabox_save', function( $saved_reply_id, $saved_topic_id ) use ( &$saved_topic_ids ) {
			$saved_topic_ids[] = array( $saved_reply_id, $saved_topic_id );
		}, 10, 2 );

		wp_update_post( array( 'ID' => $reply_id, 'post_parent' => $destination_topic_id ) );

		$this->assertSame( $destination_topic_id, wp_get_post_parent_id( $reply_id ) );
		$this->assertTrue( current_user_can( 'edit_reply', $reply_id ) );
		$this->assertGreaterThan( 0, did_action( 'bbp_reply_attributes_metabox_save' ) );
		$this->assertSame( array( array( $reply_id, $destination_topic_id ) ), $saved_topic_ids );
		$this->assertSame( $destination_topic_id, (int) get_post_meta( $reply_id, '_bbp_topic_id', true ) );
		$this->assertSame( $destination_forum_id, (int) get_post_meta( $reply_id, '_bbp_forum_id', true ) );
		$this->assertSame( $reply_to, bbp_get_reply_to( $reply_id ) );
		$this->assertSame( 0, bbp_get_topic_reply_count( $source_topic_id, true ) );
		$this->assertSame( 2, bbp_get_topic_reply_count( $destination_topic_id, true ) );
		$this->assertSame( 0, bbp_get_forum_reply_count( $source_forum_id, true, true ) );
		$this->assertSame( 2, bbp_get_forum_reply_count( $destination_forum_id, true, true ) );
	}

	public function invalid_new_post_actions() {
		return array(
			'editor'      => array( 'editpost' ),
			'inline save' => array( 'inline-save' ),
		);
	}
}
