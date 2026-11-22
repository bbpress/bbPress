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
	private $topic_id;

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
		$this->topic_id = $topic_id;
	}

	public function tearDown(): void {
		remove_filter( 'bbp_get_reply_author_display_name', array( $this, 'unsafe_name' ) );
		remove_filter( 'bbp_get_reply_author_email', array( $this, 'unsafe_email' ) );
		remove_filter( 'bbp_get_reply_url', array( $this, 'unsafe_url' ) );
		remove_filter( 'get_edit_post_link', array( $this, 'unsafe_url' ) );
		remove_filter( 'get_edit_post_link', '__return_null' );
		remove_filter( 'found_posts', array( $this, 'filter_large_reply_count' ), 10 );
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

	public function filter_large_reply_count( $found_posts ) {
		return 1000;
	}

	/**
	 * @covers BBP_Topic_Replies_List_Table::column_cb
	 * @covers BBP_Topic_Replies_List_Table::column_bbp_topic_reply_author
	 * @covers BBP_Topic_Replies_List_Table::column_bbp_reply_created
	 * @covers BBP_Topic_Replies_List_Table::column_bbp_reply_content
	 */
	public function test_columns_without_reply_item_return_empty_output() {
		$list_table = new BBP_Topic_Replies_List_Table();

		$this->assertSame( '', $list_table->column_cb() );
		$this->assertSame( '', $list_table->column_bbp_reply_created() );
		$this->assertSame( '', $list_table->column_bbp_reply_content() );

		ob_start();
		try {
			$this->assertNull( $list_table->column_bbp_topic_reply_author() );
			$output = ob_get_contents();
		} finally {
			ob_end_clean();
		}

		$this->assertSame( '', $output );
	}

	/**
	 * @ticket BBP3794
	 * @covers BBP_Topic_Replies_List_Table::single_row
	 */
	public function test_single_row_without_a_reply_post_returns_empty_output() {
		$list_table = new BBP_Topic_Replies_List_Table();

		ob_start();
		try {
			$this->assertNull( $list_table->single_row( new stdClass() ) );
			$output = ob_get_contents();
		} finally {
			ob_end_clean();
		}

		$this->assertSame( '', $output );
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

	/**
	 * @covers BBP_Topic_Replies_List_Table::column_bbp_reply_content
	 */
	public function test_missing_edit_url_does_not_render_an_empty_link() {
		$this->assertTrue( current_user_can( 'edit_reply', $this->reply->ID ) );
		add_filter( 'get_edit_post_link', '__return_null' );

		$output = ( new BBP_Topic_Replies_List_Table() )->column_bbp_reply_content( $this->reply );

		$this->assertStringContainsString( '>View</a>', $output );
		$this->assertStringNotContainsString( '>Edit</a>', $output );
	}

	/**
	 * @covers BBP_Topic_Replies_List_Table::prepare_items
	 */
	public function test_prepare_items_uses_integer_pagination_values() {
		// Reproduce the formatted metadata count used by the previous pagination.
		update_post_meta( $this->topic_id, '_bbp_reply_count', 1000 );
		add_filter( 'found_posts', array( $this, 'filter_large_reply_count' ) );

		$list_table = new BBP_Topic_Replies_List_Table();
		$list_table->prepare_items( $this->topic_id );
		remove_filter( 'found_posts', array( $this, 'filter_large_reply_count' ), 10 );

		$this->assertSame( 1000, $list_table->get_pagination_arg( 'total_items' ) );
		$this->assertSame( 200, $list_table->get_pagination_arg( 'total_pages' ) );

		ob_start();
		$list_table->display();
		$output = ob_get_clean();

		$this->assertStringContainsString( '1,000 items', $output );
	}

	/**
	 * @covers BBP_Topic_Replies_List_Table::prepare_items
	 */
	public function test_prepare_items_counts_visible_replies() {
		$this->factory->reply->create_many(
			5,
			array(
				'post_parent' => $this->topic_id,
				'post_status' => bbp_get_spam_status_id(),
				'reply_meta'  => array(
					'forum_id' => bbp_get_topic_forum_id( $this->topic_id ),
					'topic_id' => $this->topic_id,
				)
			)
		);

		$list_table = new BBP_Topic_Replies_List_Table();
		$list_table->prepare_items( $this->topic_id );

		$this->assertSame( 6, $list_table->get_pagination_arg( 'total_items' ) );
		$this->assertSame( 2, $list_table->get_pagination_arg( 'total_pages' ) );

		$user_id = $this->factory->user->create( array( 'role' => 'subscriber' ) );
		bbp_set_user_role( $user_id, bbp_get_participant_role() );
		$this->set_current_user( $user_id );

		$list_table = new BBP_Topic_Replies_List_Table();
		$list_table->prepare_items( $this->topic_id );

		$this->assertSame( 1, $list_table->get_pagination_arg( 'total_items' ) );
		$this->assertSame( 1, $list_table->get_pagination_arg( 'total_pages' ) );
	}
}
