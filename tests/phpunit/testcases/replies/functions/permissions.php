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

	public function setUp(): void {
		parent::setUp();

		$this->old_post    = $_POST;
		$this->old_request = $_REQUEST;
		$this->old_server  = $_SERVER;
		$this->old_errors  = bbpress()->errors;
	}

	public function tearDown(): void {
		$_POST            = $this->old_post;
		$_REQUEST         = $this->old_request;
		$_SERVER          = $this->old_server;
		bbpress()->errors = $this->old_errors;

		parent::tearDown();
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

}
