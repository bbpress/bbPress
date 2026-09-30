<?php

/**
 * Topic replies list-table output.
 *
 * @group admin
 */
class BBP_Tests_Admin_Topic_Replies_List_Table extends BBP_UnitTestCase {

	private $screen;
	private $hook_suffix;
	private $reply;

	public function setUp(): void {
		parent::setUp();

		$this->screen = isset( $GLOBALS['current_screen'] ) ? $GLOBALS['current_screen'] : null;
		$this->hook_suffix = isset( $GLOBALS['hook_suffix'] ) ? $GLOBALS['hook_suffix'] : null;
		$GLOBALS['hook_suffix'] = 'edit-topic';
		set_current_screen( 'edit-topic' );
		require_once BBP_PLUGIN_DIR . 'includes/admin/classes/class-bbp-topic-replies-list-table.php';

		$user_id  = $this->factory->user->create( array( 'role' => 'administrator' ) );
		$forum_id = $this->factory->forum->create();
		$topic_id = $this->factory->topic->create( array( 'post_parent' => $forum_id, 'topic_meta' => array( 'forum_id' => $forum_id ) ) );
		$reply_id = $this->factory->reply->create( array( 'post_author' => $user_id, 'post_parent' => $topic_id, 'reply_meta' => array( 'forum_id' => $forum_id, 'topic_id' => $topic_id ) ) );

		bbp_set_user_role( $user_id, bbp_get_keymaster_role() );
		$this->set_current_user( $user_id );
		$this->reply = get_post( $reply_id );
	}

	public function tearDown(): void {
		remove_filter( 'bbp_get_reply_author_display_name', array( $this, 'unsafe_name' ) );
		remove_filter( 'bbp_get_reply_author_email', array( $this, 'unsafe_email' ) );
		remove_filter( 'bbp_get_reply_url', array( $this, 'unsafe_url' ) );
		remove_filter( 'get_edit_post_link', array( $this, 'unsafe_url' ) );
		$GLOBALS['current_screen'] = $this->screen;
		if ( null === $this->hook_suffix ) {
			unset( $GLOBALS['hook_suffix'] );
		} else {
			$GLOBALS['hook_suffix'] = $this->hook_suffix;
		}

		parent::tearDown();
	}

	public function unsafe_name() {
		return '<script>name</script>';
	}

	public function unsafe_email() {
		return '<script>email</script>';
	}

	public function unsafe_url() {
		return 'javascript:alert(1)';
	}

	/**
	 * @covers BBP_Topic_Replies_List_Table::column_bbp_topic_reply_author
	 */
	public function test_author_name_and_email_are_escaped() {
		add_filter( 'bbp_get_reply_author_display_name', array( $this, 'unsafe_name' ) );
		add_filter( 'bbp_get_reply_author_email', array( $this, 'unsafe_email' ) );

		ob_start();
		( new BBP_Topic_Replies_List_Table() )->column_bbp_topic_reply_author( $this->reply );
		$output = ob_get_clean();

		$this->assertStringContainsString( '&lt;script&gt;name&lt;/script&gt;', $output );
		$this->assertStringContainsString( '&lt;script&gt;email&lt;/script&gt;', $output );
		$this->assertStringNotContainsString( '<script>', $output );
	}

	/**
	 * @covers BBP_Topic_Replies_List_Table::column_bbp_reply_content
	 */
	public function test_view_and_edit_urls_are_escaped() {
		$this->assertTrue( current_user_can( 'edit_reply', $this->reply->ID ) );
		add_filter( 'bbp_get_reply_url', array( $this, 'unsafe_url' ) );
		add_filter( 'get_edit_post_link', array( $this, 'unsafe_url' ) );

		$output = ( new BBP_Topic_Replies_List_Table() )->column_bbp_reply_content( $this->reply );

		$this->assertSame( 2, substr_count( $output, 'href=""' ) );
		$this->assertStringNotContainsString( 'javascript:', $output );
	}
}
