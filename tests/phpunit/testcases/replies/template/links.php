<?php

/**
 * Tests for the `bbp_*_reply_*()` template functions.
 *
 * @group replies
 * @group template
 * @group links
 */
class BBP_Tests_Replies_Template_Links extends BBP_UnitTestCase {

	private function with_keymaster_reply( $callback ) {
		$old_user = get_current_user_id();
		$user_id  = $this->factory->user->create( array( 'role' => 'administrator' ) );
		$forum_id = $this->factory->forum->create();
		$topic_id = $this->factory->topic->create( array(
			'post_parent' => $forum_id,
			'topic_meta'  => array( 'forum_id' => $forum_id ),
		) );
		$reply_id = $this->factory->reply->create( array(
			'post_parent' => $topic_id,
			'reply_meta'  => array( 'forum_id' => $forum_id, 'topic_id' => $topic_id ),
		) );
		bbp_set_user_role( $user_id, bbp_get_keymaster_role() );
		$this->set_current_user( $user_id );

		try {
			$callback( $reply_id, $topic_id, $forum_id, $user_id );
		} finally {
			$this->set_current_user( $old_user );
		}
	}

	/**
	 * @covers ::bbp_reply_to_link
	 * @covers ::bbp_get_reply_to_link
	 */
	public function test_bbp_get_reply_to_link() {
		$this->with_keymaster_reply( function ( $reply_id ) {
			$args = array( 'id' => $reply_id, 'reply_text' => 'Answer', 'link_before' => '<span>', 'link_after' => '</span>' );
			$link = bbp_get_reply_to_link( $args );
			$this->assertStringStartsWith( '<span><a role="button"', $link );
			$this->assertStringContainsString( 'bbp_reply_to=' . $reply_id, $link );
			$this->assertStringContainsString( 'class="bbp-reply-to-link"', $link );
			$this->assertStringContainsString( '>Answer</a></span>', $link );
			$this->expectOutputString( $link );
			bbp_reply_to_link( $args );
		} );
	}

	/**
	 * @covers ::bbp_cancel_reply_to_link
	 * @covers ::bbp_get_cancel_reply_to_link
	 */
	public function test_bbp_get_cancel_reply_to_link() {
		$this->assertNull( bbp_get_cancel_reply_to_link() );
		$old_reply_to = isset( $_GET['bbp_reply_to'] ) ? $_GET['bbp_reply_to'] : null;
		add_filter( 'bbp_thread_replies', '__return_true' );

		try {
			unset( $_GET['bbp_reply_to'] );
			$this->assertStringContainsString( 'style="display:none;"', bbp_get_cancel_reply_to_link() );
			$_GET['bbp_reply_to'] = '42';
			$link = bbp_get_cancel_reply_to_link( 'Stop' );
			$this->assertStringContainsString( '#post-42', $link );
			$this->assertStringContainsString( '>Stop</a>', $link );
			$this->assertStringNotContainsString( 'display:none', $link );
			$this->expectOutputString( $link );
			bbp_cancel_reply_to_link( 'Stop' );
		} finally {
			remove_filter( 'bbp_thread_replies', '__return_true' );
			if ( null === $old_reply_to ) {
				unset( $_GET['bbp_reply_to'] );
			} else {
				$_GET['bbp_reply_to'] = $old_reply_to;
			}
		}
	}

	/**
	 * @covers ::bbp_reply_admin_links
	 * @covers ::bbp_get_reply_admin_links
	 */
	public function test_bbp_get_reply_admin_links() {
		$old_user_id = get_current_user_id();
		$user_id     = $this->factory->user->create( array( 'role' => 'administrator' ) );
		$forum_id    = $this->factory->forum->create();
		$topic_id    = $this->factory->topic->create( array( 'post_parent' => $forum_id ) );
		$reply_id    = $this->factory->reply->create( array( 'post_parent' => $topic_id ) );

		bbp_set_user_role( $user_id, bbp_get_keymaster_role() );
		$this->set_current_user( $user_id );
		wp_trash_post( $reply_id );

		$links = bbp_get_reply_admin_links( array( 'id' => $reply_id ) );

		$this->set_current_user( $old_user_id );
		$this->assertStringContainsString( 'bbp-reply-spam-link', $links );
	}

	/**
	 * @covers ::bbp_reply_edit_link
	 * @covers ::bbp_get_reply_edit_link
	 */
	public function test_bbp_get_reply_edit_link() {
		$this->with_keymaster_reply( function ( $reply_id ) {
			$args = array( 'id' => $reply_id, 'edit_text' => 'Modify', 'link_before' => '<span>', 'link_after' => '</span>' );
			$link = bbp_get_reply_edit_link( $args );
			$this->assertStringContainsString( 'class="bbp-reply-edit-link"', $link );
			$this->assertStringContainsString( esc_url( bbp_get_reply_edit_url( $reply_id ) ), $link );
			$this->assertStringContainsString( '>Modify</a></span>', $link );
			$this->expectOutputString( $link );
			bbp_reply_edit_link( $args );
		} );
	}

	/**
	 * @covers ::bbp_reply_edit_url
	 * @covers ::bbp_get_reply_edit_url
	 */
	public function test_bbp_get_reply_edit_url() {
		$this->assertNull( bbp_get_reply_edit_url( PHP_INT_MAX ) );
		$this->with_keymaster_reply( function ( $reply_id ) {
			$url = bbp_get_reply_edit_url( $reply_id );
			$this->assertNotEmpty( $url );
			$this->assertStringContainsString( bbp_get_edit_rewrite_id(), $url );
			$this->expectOutputString( esc_url( $url ) );
			bbp_reply_edit_url( $reply_id );
		} );
	}

	/**
	 * @covers ::bbp_reply_trash_link
	 * @covers ::bbp_get_reply_trash_link
	 */
	public function test_bbp_get_reply_trash_link() {
		$this->with_keymaster_reply( function ( $reply_id ) {
			$args = array( 'id' => $reply_id, 'trash_text' => 'Discard', 'restore_text' => 'Recover', 'delete_text' => 'Erase' );
			$link = bbp_get_reply_trash_link( $args );
			$this->assertStringContainsString( 'bbp-reply-trash-link', $link );
			$this->assertStringContainsString( '>Discard</a>', $link );
			wp_trash_post( $reply_id );
			$link = bbp_get_reply_trash_link( $args );
			$this->assertStringContainsString( 'bbp-reply-restore-link', $link );
			$this->assertStringContainsString( 'bbp-reply-delete-link', $link );
			$this->assertStringContainsString( '>Recover</a>', $link );
			$this->assertStringContainsString( '>Erase</a>', $link );
			$this->expectOutputString( $link );
			bbp_reply_trash_link( $args );
		} );
	}

	/**
	 * @covers ::bbp_reply_spam_link
	 * @covers ::bbp_get_reply_spam_link
	 */
	public function test_bbp_get_reply_spam_link() {
		$this->with_keymaster_reply( function ( $reply_id ) {
			$args = array( 'id' => $reply_id, 'spam_text' => 'Flag', 'unspam_text' => 'Clear' );
			$link = bbp_get_reply_spam_link( $args );
			$this->assertStringContainsString( 'bbp-reply-spam-link', $link );
			$this->assertStringContainsString( '>Flag</a>', $link );
			bbp_spam_reply( $reply_id );
			$link = bbp_get_reply_spam_link( $args );
			$this->assertStringContainsString( '>Clear</a>', $link );
			$this->expectOutputString( $link );
			bbp_reply_spam_link( $args );
		} );
	}

	/**
	 * @covers ::bbp_reply_move_link
	 * @covers ::bbp_get_reply_move_link
	 */
	public function test_bbp_get_reply_move_link() {
		$this->with_keymaster_reply( function ( $reply_id ) {
			$args = array( 'id' => $reply_id, 'split_text' => 'Relocate' );
			$link = bbp_get_reply_move_link( $args );
			$this->assertStringContainsString( 'bbp-reply-move-link', $link );
			$this->assertStringContainsString( 'action=bbp-move-reply', $link );
			$this->assertStringContainsString( '>Relocate</a>', $link );
			$this->expectOutputString( $link );
			bbp_reply_move_link( $args );
		} );
	}

	/**
	 * @covers ::bbp_topic_split_link
	 * @covers ::bbp_get_topic_split_link
	 */
	public function test_bbp_get_topic_split_link() {
		$this->with_keymaster_reply( function ( $reply_id ) {
			$args = array( 'id' => $reply_id, 'split_text' => 'Divide' );
			$link = bbp_get_topic_split_link( $args );
			$this->assertStringContainsString( 'bbp-topic-split-link', $link );
			$this->assertStringContainsString( 'action=bbp-split-topic', $link );
			$this->assertStringContainsString( '>Divide</a>', $link );
			$this->expectOutputString( $link );
			bbp_topic_split_link( $args );
		} );
	}

	/**
	 * @covers ::bbp_reply_approve_link
	 * @covers ::bbp_get_reply_approve_link
	 */
	public function test_bbp_get_reply_approve_link() {
		$this->with_keymaster_reply( function ( $reply_id ) {
			$args = array( 'id' => $reply_id, 'approve_text' => 'Accept', 'unapprove_text' => 'Hold' );
			$link = bbp_get_reply_approve_link( $args );
			$this->assertStringContainsString( 'bbp-reply-approve-link', $link );
			$this->assertStringContainsString( '>Hold</a>', $link );
			bbp_unapprove_reply( $reply_id );
			$link = bbp_get_reply_approve_link( $args );
			$this->assertStringContainsString( '>Accept</a>', $link );
			$this->expectOutputString( $link );
			bbp_reply_approve_link( $args );
		} );
	}

	/**
	 * @covers ::bbp_topic_pagination_links
	 * @covers ::bbp_get_topic_pagination_links
	 */
	public function test_bbp_get_topic_pagination_links() {
		$old_query = bbpress()->reply_query;
		bbpress()->reply_query = (object) array();

		try {
			$this->assertFalse( bbp_get_topic_pagination_links() );
			bbpress()->reply_query->pagination_links = '<a href="?paged=2">2</a>';
			$this->assertSame( '<a href="?paged=2">2</a>', bbp_get_topic_pagination_links() );
			$this->expectOutputString( '<a href="?paged=2">2</a>' );
			bbp_topic_pagination_links();
		} finally {
			bbpress()->reply_query = $old_query;
		}
	}
}
