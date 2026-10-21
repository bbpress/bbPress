<?php

/**
 * Tests for topic move permissions in administration.
 *
 * @group admin
 * @group topics
 * @group capabilities
 */
class BBP_Tests_Admin_Topic_Move_Permissions extends BBP_UnitTestCase {

	private $old_post;
	private $old_screen;

	public function setUp(): void {
		parent::setUp();

		$this->old_post   = $_POST;
		$this->old_screen = isset( $GLOBALS['current_screen'] ) ? $GLOBALS['current_screen'] : null;

		if ( ! function_exists( 'bbp_admin_topics' ) ) {
			require_once BBP_PLUGIN_DIR . 'includes/admin/topics.php';
		}
	}

	public function tearDown(): void {
		$_POST = $this->old_post;

		if ( is_null( $this->old_screen ) ) {
			unset( $GLOBALS['current_screen'] );
		} else {
			$GLOBALS['current_screen'] = $this->old_screen;
		}

		parent::tearDown();
	}

	/**
	 * @covers BBP_Topics_Admin::filter_post_data
	 */
	public function test_scoped_moderator_cannot_move_private_topic_to_public_forum() {
		$user_id      = $this->factory->user->create( array( 'role' => bbp_get_participant_role() ) );
		$author_id    = $this->factory->user->create( array( 'role' => bbp_get_participant_role() ) );
		$source_forum = $this->factory->forum->create();
		$destination  = $this->factory->forum->create();
		$topic_id     = $this->factory->topic->create( array(
			'post_author' => $author_id,
			'post_parent' => $source_forum,
			'topic_meta'  => array( 'forum_id' => $source_forum ),
		) );

		bbp_privatize_forum( $source_forum );
		bbp_add_moderator( $source_forum, $user_id );
		$this->set_current_user( $user_id );
		$GLOBALS['current_screen'] = WP_Screen::get( 'topic' );
		$_POST['action']            = 'editpost';
		$_POST['bbp_topic_metabox'] = wp_create_nonce( 'bbp_topic_metabox_save' );

		$this->assertTrue( is_admin() );
		$this->assertTrue( current_user_can( 'edit_topic', $topic_id ) );
		$this->assertTrue( current_user_can( 'edit_forum', $source_forum ) );
		$this->assertTrue( current_user_can( 'read_forum', $destination ) );
		$this->assertFalse( current_user_can( 'edit_forum', $destination ) );

		$reflection = new ReflectionClass( 'BBP_Topics_Admin' );
		$admin      = $reflection->newInstanceWithoutConstructor();
		$data       = $admin->filter_post_data(
			array( 'post_type' => bbp_get_topic_post_type(), 'post_parent' => $destination ),
			array( 'ID' => $topic_id )
		);

		$this->assertSame( $source_forum, $data['post_parent'] );
	}
}
