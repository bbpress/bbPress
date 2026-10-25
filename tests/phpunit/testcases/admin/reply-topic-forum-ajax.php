<?php

/**
 * Exception used to capture the end of an AJAX request.
 */
class BBP_Tests_Admin_Reply_Topic_Forum_Ajax_Exit extends Exception {
}

/**
 * Reply topic/forum AJAX previews.
 *
 * @group admin
 */
class BBP_Tests_Admin_Reply_Topic_Forum_Ajax extends BBP_UnitTestCase {

	public function setUp(): void {
		parent::setUp();

		bbp_setup_admin();
	}

	/**
	 * @ticket BBP3724
	 * @covers ::bbp_ajax_reply_topic_forum
	 */
	public function test_preview_returns_destination_forum_and_reply_choices_without_saving() {
		$user_id              = $this->factory->user->create();
		$source_forum_id      = $this->factory->forum->create();
		$destination_forum_id = $this->factory->forum->create( array( 'post_title' => 'Destination Forum' ) );
		$source_topic_id      = $this->factory->topic->create( array( 'post_parent' => $source_forum_id ) );
		$destination_topic_id = $this->factory->topic->create( array( 'post_parent' => $destination_forum_id, 'post_title' => 'Destination Topic' ) );
		$source_reply_id      = $this->factory->reply->create( array( 'post_parent' => $source_topic_id ) );
		$destination_reply_id = $this->factory->reply->create( array( 'post_parent' => $destination_topic_id ) );
		$reply_id             = $this->factory->reply->create( array( 'post_parent' => $source_topic_id ) );

		update_post_meta( $reply_id, '_bbp_forum_id', $source_forum_id );
		bbp_update_reply_to( $reply_id, $source_reply_id );
		bbp_set_user_role( $user_id, bbp_get_keymaster_role() );
		$this->set_current_user( $user_id );

		$response = $this->preview( $reply_id, $destination_topic_id, wp_create_nonce( 'bbp_reply_topic_forum_' . $reply_id ) );
		$data     = json_decode( $response['body'], true );

		$this->assertTrue( $data['success'] );
		$this->assertSame( $destination_forum_id, $data['data']['forum_id'] );
		$this->assertSame( 'Destination Forum', $data['data']['forum_title'] );
		$this->assertStringContainsString( 'value="' . $destination_reply_id . '"', $data['data']['reply_to_dropdown'] );
		$this->assertStringNotContainsString( 'value="' . $source_reply_id . '"', $data['data']['reply_to_dropdown'] );
		$this->assertSame( $source_topic_id, wp_get_post_parent_id( $reply_id ) );
		$this->assertSame( $source_forum_id, (int) get_post_meta( $reply_id, '_bbp_forum_id', true ) );
		$this->assertSame( $source_reply_id, bbp_get_reply_to( $reply_id ) );
	}

	/**
	 * @ticket BBP3724
	 * @covers ::bbp_ajax_reply_topic_forum
	 */
	public function test_preview_does_not_disclose_a_denied_destination() {
		$user_id              = $this->factory->user->create( array( 'role' => bbp_get_participant_role() ) );
		$source_forum_id      = $this->factory->forum->create();
		$destination_forum_id = $this->factory->forum->create( array( 'post_title' => 'Destination Forum' ) );
		$source_topic_id      = $this->factory->topic->create( array( 'post_parent' => $source_forum_id ) );
		$destination_topic_id = $this->factory->topic->create( array( 'post_parent' => $destination_forum_id ) );
		$reply_id             = $this->factory->reply->create( array( 'post_author' => $user_id, 'post_parent' => $source_topic_id ) );

		bbp_add_moderator( $source_forum_id, $user_id );
		$this->set_current_user( $user_id );
		$this->assertTrue( current_user_can( 'moderate', $source_topic_id ) );
		$this->assertFalse( current_user_can( 'moderate', $destination_topic_id ) );

		$response = $this->preview( $reply_id, $destination_topic_id, wp_create_nonce( 'bbp_reply_topic_forum_' . $reply_id ) );
		$data     = json_decode( $response['body'], true );

		$this->assertFalse( $data['success'] );
		$this->assertSame( 'You cannot move this reply.', $data['data']['message'] );
		$this->assertArrayNotHasKey( 'forum_id', $data['data'] );
		$this->assertStringNotContainsString( 'Destination Forum', $response['body'] );
	}

	/**
	 * @ticket BBP3724
	 * @covers ::bbp_ajax_reply_topic_forum
	 */
	public function test_preview_rejects_invalid_reply_topic_and_request_method() {
		$user_id  = $this->factory->user->create();
		$forum_id = $this->factory->forum->create();
		$topic_id = $this->factory->topic->create( array( 'post_parent' => $forum_id ) );
		$reply_id = $this->factory->reply->create( array( 'post_parent' => $topic_id ) );

		bbp_set_user_role( $user_id, bbp_get_keymaster_role() );
		$this->set_current_user( $user_id );
		$nonce = wp_create_nonce( 'bbp_reply_topic_forum_' . $reply_id );

		foreach ( array(
			array( 0, $topic_id, 'POST' ),
			array( $reply_id, 0, 'POST' ),
			array( $reply_id, $topic_id, 'GET' )
		) as $request ) {
			$response = $this->preview( $request[0], $request[1], $nonce, $request[2] );
			$data     = json_decode( $response['body'], true );

			$this->assertFalse( $data['success'] );
			$this->assertArrayNotHasKey( 'forum_id', $data['data'] );
		}
	}

	/**
	 * @ticket BBP3724
	 * @covers ::bbp_ajax_reply_topic_forum
	 */
	public function test_preview_rejects_an_invalid_nonce() {
		$forum_id = $this->factory->forum->create();
		$topic_id = $this->factory->topic->create( array( 'post_parent' => $forum_id ) );
		$reply_id = $this->factory->reply->create( array( 'post_parent' => $topic_id ) );
		$response = $this->preview( $reply_id, $topic_id, 'invalid' );

		$this->assertSame( '', $response['body'] );
		$this->assertSame( '-1', (string) $response['die_message'] );
	}

	/**
	 * Call the AJAX handler while capturing its JSON and termination.
	 */
	private function preview( $reply_id, $topic_id, $nonce, $method = 'POST' ) {
		$old_post    = $_POST;
		$old_request = $_REQUEST;
		$old_method  = isset( $_SERVER['REQUEST_METHOD'] ) ? $_SERVER['REQUEST_METHOD'] : null;
		$die_message = null;
		$body        = '';
		$level       = ob_get_level();
		$die_handler = function() use ( &$die_message ) {
			return function( $message ) use ( &$die_message ) {
				$die_message = $message;
				throw new BBP_Tests_Admin_Reply_Topic_Forum_Ajax_Exit();
			};
		};
		$_POST = array(
			'action'      => 'bbp_reply_topic_forum',
			'reply_id'    => $reply_id,
			'topic_id'    => $topic_id,
			'_ajax_nonce' => $nonce
		);
		$_REQUEST                  = $_POST;
		$_SERVER['REQUEST_METHOD'] = $method;
		add_filter( 'wp_doing_ajax', '__return_true' );
		add_filter( 'wp_die_ajax_handler', $die_handler );
		ob_start();

		try {
			bbp_ajax_reply_topic_forum();
			$this->fail( 'Expected the AJAX response to terminate.' );
		} catch ( BBP_Tests_Admin_Reply_Topic_Forum_Ajax_Exit $error ) {
			$body = ob_get_clean();
		} finally {
			while ( ob_get_level() > $level ) {
				ob_end_clean();
			}
			remove_filter( 'wp_die_ajax_handler', $die_handler );
			remove_filter( 'wp_doing_ajax', '__return_true' );
			$_POST    = $old_post;
			$_REQUEST = $old_request;
			if ( null === $old_method ) {
				unset( $_SERVER['REQUEST_METHOD'] );
			} else {
				$_SERVER['REQUEST_METHOD'] = $old_method;
			}
		}

		return array( 'body' => $body, 'die_message' => $die_message );
	}
}
