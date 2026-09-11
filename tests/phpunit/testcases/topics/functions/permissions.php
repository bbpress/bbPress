<?php

/**
 * Tests for topic handler permissions.
 *
 * @group topics
 * @group functions
 * @group capabilities
 */
class BBP_Tests_Topics_Functions_Permissions extends BBP_UnitTestCase {

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

	protected function submit_topic_split( $reply_id, $source_topic_id, $split_option ) {
		$home_url             = wp_parse_url( home_url( '/' ) );
		$_SERVER['HTTP_HOST'] = $home_url['host'];

		if ( isset( $home_url['port'] ) ) {
			$_SERVER['HTTP_HOST'] .= ':' . $home_url['port'];
		}

		$_SERVER['REQUEST_URI']        = $home_url['path'];
		$_POST['bbp_reply_id']          = $reply_id;
		$_POST['bbp_topic_split_option'] = $split_option;
		$_REQUEST['_wpnonce']           = wp_create_nonce( 'bbp-split-topic_' . $source_topic_id );

		$did_redirect     = false;
		$prevent_redirect = function() use ( &$did_redirect ) {
			$did_redirect = true;
			throw new RuntimeException( 'Topic split redirect.' );
		};

		add_filter( 'wp_redirect', $prevent_redirect );

		try {
			bbp_split_topic_handler( 'bbp-split-topic' );
		} catch ( RuntimeException $exception ) {
			if ( 'Topic split redirect.' !== $exception->getMessage() ) {
				throw $exception;
			}
		}

		remove_filter( 'wp_redirect', $prevent_redirect );

		return $did_redirect;
	}

	/**
	 * @covers ::bbp_split_topic_handler
	 */
	public function test_participant_cannot_split_reply_when_source_topic_cannot_be_edited() {
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

		$this->submit_topic_split( $reply_id, $source_topic_id, 'reply' );

		$this->assertContains( 'bbp_split_topic_source_permission', bbpress()->errors->get_error_codes() );
		$this->assertSame( bbp_get_reply_post_type(), get_post_type( $reply_id ) );
		$this->assertSame( $source_topic_id, wp_get_post_parent_id( $reply_id ) );
		$this->assertSame( $source_topic_id, bbp_get_reply_topic_id( $reply_id ) );
	}

	/**
	 * @covers ::bbp_split_topic_handler
	 */
	public function test_moderator_can_convert_split_reply_to_topic() {
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

		$did_redirect = $this->submit_topic_split( $reply_id, $source_topic_id, 'reply' );

		$this->assertSame( array(), bbpress()->errors->get_error_codes() );
		$this->assertSame( bbp_get_topic_post_type(), get_post_type( $reply_id ) );
		$this->assertSame( $forum_id, wp_get_post_parent_id( $reply_id ) );
		$this->assertSame( $reply_id, (int) get_post_meta( $reply_id, '_bbp_topic_id', true ) );
		$this->assertTrue( $did_redirect );
	}
}
