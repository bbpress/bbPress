<?php

/**
 * Subscription handling when topics and replies are saved in administration.
 *
 * @group admin
 */
class BBP_Tests_Admin_Subscriptions extends BBP_UnitTestCase {

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
		require_once BBP_PLUGIN_DIR . 'includes/admin/metaboxes.php';
	}

	public function tearDown(): void {
		$_POST                    = $this->post;
		$_SERVER['REQUEST_METHOD'] = $this->request_method;
		$GLOBALS['current_screen'] = $this->screen;

		parent::tearDown();
	}

	public function test_topic_subscriptions_metabox_does_not_submit_subscription() {
		$topic_id = $this->factory->topic->create();

		ob_start();
		bbp_topic_subscriptions_metabox( get_post( $topic_id ) );
		$output = ob_get_clean();

		$this->assertStringNotContainsString( 'name="bbp_topic_subscription"', $output );
	}

	public function test_topic_admin_save_preserves_author_subscription_without_metabox_field() {
		$author_id = $this->factory->user->create();
		$editor_id = $this->factory->user->create( array( 'role' => 'administrator' ) );
		$forum_id  = $this->factory->forum->create();
		$topic_id  = $this->factory->topic->create( array(
			'post_author' => $author_id,
			'post_parent' => $forum_id,
			'topic_meta'  => array( 'forum_id' => $forum_id ),
		) );

		bbp_add_user_subscription( $author_id, $topic_id );
		bbp_set_user_role( $editor_id, bbp_get_keymaster_role() );
		$this->set_current_user( $editor_id );
		set_current_screen( 'edit-topic' );
		$_POST['bbp_topic_metabox'] = wp_create_nonce( 'bbp_topic_metabox_save' );

		( new BBP_Topics_Admin() )->save_meta_boxes( $topic_id );

		$this->assertTrue( bbp_is_user_subscribed( $author_id, $topic_id ) );
		$this->assertFalse( bbp_is_user_subscribed( $editor_id, $topic_id ) );

		// A stale or extension-provided field must not change the author's subscription.
		$_POST['bbp_topic_subscription'] = '';
		( new BBP_Topics_Admin() )->save_meta_boxes( $topic_id );

		$this->assertTrue( bbp_is_user_subscribed( $author_id, $topic_id ) );
		$this->assertFalse( bbp_is_user_subscribed( $editor_id, $topic_id ) );
	}

	public function test_topic_admin_save_does_not_apply_editors_subscription_to_author() {
		$author_id = $this->factory->user->create();
		$editor_id = $this->factory->user->create( array( 'role' => 'administrator' ) );
		$forum_id  = $this->factory->forum->create();
		$topic_id  = $this->factory->topic->create( array(
			'post_author' => $author_id,
			'post_parent' => $forum_id,
			'topic_meta'  => array( 'forum_id' => $forum_id ),
		) );

		bbp_add_user_subscription( $editor_id, $topic_id );
		bbp_set_user_role( $editor_id, bbp_get_keymaster_role() );
		$this->set_current_user( $editor_id );
		set_current_screen( 'edit-topic' );
		$_POST['bbp_topic_metabox']      = wp_create_nonce( 'bbp_topic_metabox_save' );
		$_POST['bbp_topic_subscription'] = 'bbp_subscribe';

		( new BBP_Topics_Admin() )->save_meta_boxes( $topic_id );

		$this->assertFalse( bbp_is_user_subscribed( $author_id, $topic_id ) );
		$this->assertTrue( bbp_is_user_subscribed( $editor_id, $topic_id ) );
	}

	public function test_reply_admin_save_preserves_author_subscription() {
		$author_id = $this->factory->user->create();
		$editor_id = $this->factory->user->create( array( 'role' => 'administrator' ) );
		$forum_id  = $this->factory->forum->create();
		$topic_id  = $this->factory->topic->create( array(
			'post_parent' => $forum_id,
			'topic_meta'  => array( 'forum_id' => $forum_id ),
		) );
		$reply_id  = $this->factory->reply->create( array(
			'post_author' => $author_id,
			'post_parent' => $topic_id,
			'reply_meta'  => array( 'forum_id' => $forum_id, 'topic_id' => $topic_id ),
		) );

		bbp_add_user_subscription( $author_id, $topic_id );
		bbp_set_user_role( $editor_id, bbp_get_keymaster_role() );
		$this->set_current_user( $editor_id );
		set_current_screen( 'edit-reply' );
		$_POST['bbp_reply_metabox'] = wp_create_nonce( 'bbp_reply_metabox_save' );

		( new BBP_Replies_Admin() )->save_meta_boxes( $reply_id );

		$this->assertTrue( bbp_is_user_subscribed( $author_id, $topic_id ) );
		$this->assertFalse( bbp_is_user_subscribed( $editor_id, $topic_id ) );
	}

	public function test_topic_form_actions_update_subscription() {
		$author_id = $this->factory->user->create();
		$forum_id  = $this->factory->forum->create();
		$topic_id  = $this->factory->topic->create( array(
			'post_author' => $author_id,
			'post_parent' => $forum_id,
			'topic_meta'  => array( 'forum_id' => $forum_id ),
		) );

		$this->set_current_user( $author_id );
		$_POST['bbp_topic_subscription'] = 'bbp_subscribe';
		bbp_update_topic( $topic_id, $forum_id, array(), $author_id, true );
		$this->assertFalse( bbp_is_user_subscribed( $author_id, $topic_id ) );

		do_action( 'bbp_edit_topic', $topic_id, $forum_id, array(), $author_id, true );
		$this->assertTrue( bbp_is_user_subscribed( $author_id, $topic_id ) );

		unset( $_POST['bbp_topic_subscription'] );
		bbp_update_topic( $topic_id, $forum_id, array(), $author_id, true );
		$this->assertTrue( bbp_is_user_subscribed( $author_id, $topic_id ) );

		do_action( 'bbp_edit_topic', $topic_id, $forum_id, array(), $author_id, true );
		$this->assertFalse( bbp_is_user_subscribed( $author_id, $topic_id ) );

		$_POST['bbp_topic_subscription'] = 'bbp_subscribe';
		do_action( 'bbp_new_topic', $topic_id, $forum_id, array(), $author_id, false );
		$this->assertTrue( bbp_is_user_subscribed( $author_id, $topic_id ) );
	}

	public function test_reply_form_actions_update_subscription() {
		$author_id = $this->factory->user->create();
		$forum_id  = $this->factory->forum->create();
		$topic_id  = $this->factory->topic->create( array(
			'post_parent' => $forum_id,
			'topic_meta'  => array( 'forum_id' => $forum_id ),
		) );
		$reply_id  = $this->factory->reply->create( array(
			'post_author' => $author_id,
			'post_parent' => $topic_id,
			'reply_meta'  => array( 'forum_id' => $forum_id, 'topic_id' => $topic_id ),
		) );

		$this->set_current_user( $author_id );
		$_POST['bbp_topic_subscription'] = 'bbp_subscribe';
		bbp_update_reply( $reply_id, $topic_id, $forum_id, array(), $author_id, true );
		$this->assertFalse( bbp_is_user_subscribed( $author_id, $topic_id ) );

		do_action( 'bbp_edit_reply', $reply_id, $topic_id, $forum_id, array(), $author_id, true, 0 );
		$this->assertTrue( bbp_is_user_subscribed( $author_id, $topic_id ) );

		unset( $_POST['bbp_topic_subscription'] );
		bbp_update_reply( $reply_id, $topic_id, $forum_id, array(), $author_id, true );
		$this->assertTrue( bbp_is_user_subscribed( $author_id, $topic_id ) );

		do_action( 'bbp_edit_reply', $reply_id, $topic_id, $forum_id, array(), $author_id, true, 0 );
		$this->assertFalse( bbp_is_user_subscribed( $author_id, $topic_id ) );

		$_POST['bbp_topic_subscription'] = 'bbp_subscribe';
		do_action( 'bbp_new_reply', $reply_id, $topic_id, $forum_id, array(), $author_id, false, 0 );
		$this->assertTrue( bbp_is_user_subscribed( $author_id, $topic_id ) );
	}
}
