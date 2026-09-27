<?php

/**
 * Tests for front-end edit lock enforcement.
 *
 * @group common
 * @group capabilities
 */
class BBP_Tests_Common_Edit_Lock extends BBP_UnitTestCase {

	protected $old_post;
	protected $old_request;
	protected $old_server;
	protected $old_errors;
	protected $old_allow_content_edit;
	protected $old_edit_lock;

	public function setUp(): void {
		parent::setUp();

		$this->old_post    = $_POST;
		$this->old_request = $_REQUEST;
		$this->old_server  = $_SERVER;
		$this->old_errors  = bbpress()->errors;
		$this->old_allow_content_edit = get_option( '_bbp_allow_content_edit' );
		$this->old_edit_lock         = get_option( '_bbp_edit_lock' );

		update_option( '_bbp_allow_content_edit', true );
		update_option( '_bbp_edit_lock', 5 );
		bbpress()->errors = new WP_Error();

		$home_url             = wp_parse_url( home_url( '/' ) );
		$_SERVER['HTTP_HOST'] = $home_url['host'];

		if ( isset( $home_url['port'] ) ) {
			$_SERVER['HTTP_HOST'] .= ':' . $home_url['port'];
		}

		$_SERVER['REQUEST_URI'] = $home_url['path'];
	}

	public function tearDown(): void {
		$_POST            = $this->old_post;
		$_REQUEST         = $this->old_request;
		$_SERVER          = $this->old_server;
		bbpress()->errors = $this->old_errors;
		update_option( '_bbp_allow_content_edit', $this->old_allow_content_edit );
		update_option( '_bbp_edit_lock', $this->old_edit_lock );

		parent::tearDown();
	}

	/**
	 * @covers ::bbp_edit_topic_handler
	 */
	public function test_topic_author_cannot_submit_after_edit_lock_on_another_page() {
		$user_id  = $this->factory->user->create();
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

		bbp_set_user_role( $user_id, bbp_get_participant_role() );
		$this->set_current_user( $user_id );

		$this->assertFalse( bbp_is_topic_edit() );
		$this->assertTrue( current_user_can( 'edit_topic', $topic_id ) );

		$_POST['bbp_topic_id']  = $topic_id;
		$_REQUEST['_wpnonce'] = wp_create_nonce( 'bbp-edit-topic_' . $topic_id );

		bbp_edit_topic_handler( 'bbp-edit-topic' );

		$this->assertContains( 'bbp_edit_topic_permission', bbpress()->errors->get_error_codes() );

		update_option( '_bbp_edit_lock', 0 );
		bbpress()->errors = new WP_Error();
		bbp_edit_topic_handler( 'bbp-edit-topic' );
		$this->assertNotContains( 'bbp_edit_topic_permission', bbpress()->errors->get_error_codes() );

		update_option( '_bbp_edit_lock', 5 );
		bbp_set_user_role( $user_id, bbp_get_moderator_role() );
		$this->set_current_user( 0 );
		$this->set_current_user( $user_id );
		bbpress()->errors = new WP_Error();
		bbp_edit_topic_handler( 'bbp-edit-topic' );
		$this->assertNotContains( 'bbp_edit_topic_permission', bbpress()->errors->get_error_codes() );
	}

	/**
	 * @covers ::bbp_edit_reply_handler
	 */
	public function test_reply_author_cannot_submit_after_edit_lock_on_another_page() {
		$user_id  = $this->factory->user->create();
		$forum_id = $this->factory->forum->create();
		$topic_id = $this->factory->topic->create(
			array(
				'post_parent' => $forum_id,
				'topic_meta'  => array( 'forum_id' => $forum_id ),
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

		bbp_set_user_role( $user_id, bbp_get_participant_role() );
		$this->set_current_user( $user_id );

		$this->assertFalse( bbp_is_reply_edit() );
		$this->assertTrue( current_user_can( 'edit_reply', $reply_id ) );

		$_POST['bbp_reply_id'] = $reply_id;
		$_REQUEST['_wpnonce'] = wp_create_nonce( 'bbp-edit-reply_' . $reply_id );

		bbp_edit_reply_handler( 'bbp-edit-reply' );

		$this->assertContains( 'bbp_edit_reply_permission', bbpress()->errors->get_error_codes() );

		update_option( '_bbp_edit_lock', 0 );
		bbpress()->errors = new WP_Error();
		bbp_edit_reply_handler( 'bbp-edit-reply' );
		$this->assertNotContains( 'bbp_edit_reply_permission', bbpress()->errors->get_error_codes() );

		update_option( '_bbp_edit_lock', 5 );
		bbp_set_user_role( $user_id, bbp_get_moderator_role() );
		$this->set_current_user( 0 );
		$this->set_current_user( $user_id );
		bbpress()->errors = new WP_Error();
		bbp_edit_reply_handler( 'bbp-edit-reply' );
		$this->assertNotContains( 'bbp_edit_reply_permission', bbpress()->errors->get_error_codes() );
	}
}
