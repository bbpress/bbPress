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

		$_SERVER['HTTP_HOST']       = wp_parse_url( home_url(), PHP_URL_HOST );
		$_SERVER['REQUEST_URI']     = '/';
		$_POST['bbp_topic_id']      = $topic_id;
		$_POST['bbp_reply_content'] = 'A reply to a private topic.';
		$_REQUEST['_wpnonce']       = wp_create_nonce( 'bbp-new-reply' );

		$did_redirect     = false;
		$prevent_redirect = function( $location ) use ( &$did_redirect ) {
			$did_redirect = true;
			throw new RuntimeException( 'Unexpected reply redirect.' );
		};

		add_filter( 'wp_redirect', $prevent_redirect );

		try {
			bbp_new_reply_handler( 'bbp-new-reply' );
		} catch ( RuntimeException $exception ) {
			if ( 'Unexpected reply redirect.' !== $exception->getMessage() ) {
				throw $exception;
			}
		}

		remove_filter( 'wp_redirect', $prevent_redirect );

		$reply_ids = get_posts(
			array(
				'fields'      => 'ids',
				'post_parent' => $topic_id,
				'post_status' => 'any',
				'post_type'   => bbp_get_reply_post_type(),
			)
		);

		$this->assertSame( array(), $reply_ids );
		$this->assertContains( 'bbp_new_reply_topic_public', bbpress()->errors->get_error_codes() );
		$this->assertFalse( $did_redirect );
	}
}
