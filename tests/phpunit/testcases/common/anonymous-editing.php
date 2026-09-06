<?php

/**
 * Tests for anonymous editing permissions.
 *
 * @group common
 * @group capabilities
 */
class BBP_Tests_Common_Anonymous_Editing extends BBP_UnitTestCase {

	protected $old_post;
	protected $old_request;
	protected $old_server;
	protected $old_errors;

	public function setUp(): void {
		parent::setUp();

		$this->old_post    = $_POST;
		$this->old_request = $_REQUEST;
		$this->old_server  = $_SERVER;
		$this->old_errors  = bbpress()->errors;
	}

	public function tearDown(): void {
		$_POST             = $this->old_post;
		$_REQUEST          = $this->old_request;
		$_SERVER           = $this->old_server;
		bbpress()->errors  = $this->old_errors;

		parent::tearDown();
	}

	/**
	 * @covers ::bbp_map_topic_meta_caps
	 */
	public function test_anonymous_user_cannot_edit_topic() {
		$forum_id = $this->factory->forum->create();
		$topic_id = $this->factory->topic->create(
			array(
				'post_author' => 0,
				'post_parent' => $forum_id,
				'topic_meta'  => array(
					'forum_id' => $forum_id,
				),
			)
		);

		update_option( '_bbp_allow_anonymous', true );
		$this->set_current_user( 0 );

		$this->assertSame(
			array( 'do_not_allow' ),
			bbp_map_topic_meta_caps( array(), 'edit_topic', 0, array( $topic_id ) )
		);
		$this->assertFalse( current_user_can( 'edit_topic', $topic_id ) );
	}

	/**
	 * @covers ::bbp_map_reply_meta_caps
	 */
	public function test_anonymous_user_cannot_edit_reply() {
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
				'post_author' => 0,
				'post_parent' => $topic_id,
				'reply_meta'  => array(
					'forum_id' => $forum_id,
					'topic_id' => $topic_id,
				),
			)
		);

		update_option( '_bbp_allow_anonymous', true );
		$this->set_current_user( 0 );

		$this->assertSame(
			array( 'do_not_allow' ),
			bbp_map_reply_meta_caps( array(), 'edit_reply', 0, array( $reply_id ) )
		);
		$this->assertFalse( current_user_can( 'edit_reply', $reply_id ) );
	}

	/**
	 * @covers ::bbp_edit_topic_handler
	 */
	public function test_anonymous_user_cannot_submit_topic_edit() {
		$forum_id = $this->factory->forum->create();
		$topic_id = $this->factory->topic->create(
			array(
				'post_author' => 0,
				'post_parent' => $forum_id,
				'topic_meta'  => array(
					'forum_id' => $forum_id,
				),
			)
		);

		update_option( '_bbp_allow_anonymous', true );
		$this->set_current_user( 0 );
		bbpress()->errors = new WP_Error();

		$_POST['bbp_topic_id'] = $topic_id;

		bbp_edit_topic_handler( 'bbp-edit-topic' );

		$this->assertContains( 'bbp_edit_topic_permission', bbpress()->errors->get_error_codes() );
	}

	/**
	 * @covers ::bbp_edit_reply_handler
	 */
	public function test_anonymous_user_cannot_submit_reply_edit() {
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
				'post_author' => 0,
				'post_parent' => $topic_id,
				'reply_meta'  => array(
					'forum_id' => $forum_id,
					'topic_id' => $topic_id,
				),
			)
		);

		update_option( '_bbp_allow_anonymous', true );
		$this->set_current_user( 0 );
		bbpress()->errors = new WP_Error();
		$this->go_to( home_url( '/' ) );

		$_POST['bbp_reply_id'] = $reply_id;
		$_REQUEST['_wpnonce']  = wp_create_nonce( 'bbp-edit-reply_' . $reply_id );

		bbp_edit_reply_handler( 'bbp-edit-reply' );

		$this->assertContains( 'bbp_edit_reply_permission', bbpress()->errors->get_error_codes() );
	}
}
