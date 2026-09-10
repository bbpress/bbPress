<?php

/**
 * Tests for the `bbp_*_form_forum_*` visibility template functions.
 *
 * @group forums
 * @group template
 * @group visibility
 */
class BBP_Tests_Forums_Template_Visibility extends BBP_UnitTestCase {

	/**
	 * @covers ::bbp_forum_visibility
	 * @covers ::bbp_get_forum_visibility
	 */
	public function test_bbp_get_forum_visibility() {
		$f = $this->factory->forum->create();

		$forum = bbp_get_forum_visibility( $f, bbp_get_public_status_id(), false );
		$this->assertSame( 'publish', $forum );

		$f = $this->factory->forum->create( array(
			'post_status' => bbp_get_private_status_id()
		) );

		//bbp_privatize_forum( $f );
		$forum = bbp_get_forum_visibility( $f, bbp_get_private_status_id(), false );
		$this->assertSame( 'private', $forum );

		$f = $this->factory->forum->create();

		bbp_hide_forum( $f );
		$forum = bbp_get_forum_visibility( $f, bbp_get_hidden_status_id(), false );
		$this->assertSame( 'hidden', $forum );
	}

	/**
	 * @covers ::bbp_is_forum_visibility
	 */
	public function test_bbp_is_forum_visibility() {
		$f = $this->factory->forum->create();

		$forum = bbp_is_forum_visibility( $f, bbp_get_public_status_id(), false );
		$this->assertTrue( $forum );

		$f = $this->factory->forum->create( array(
			'post_status' => bbp_get_private_status_id()
		) );

		$forum = bbp_is_forum_visibility( $f, bbp_get_private_status_id(), false );
		$this->assertTrue( $forum );

		$f = $this->factory->forum->create();

		bbp_hide_forum( $f );
		$forum = bbp_is_forum_visibility( $f, bbp_get_hidden_status_id(), false );
		$this->assertTrue( $forum );
	}

	/**
	 * @covers ::bbp_suppress_private_forum_meta
	 */
	public function test_bbp_suppress_private_forum_meta() {
		$public_forum = $this->factory->forum->create();
		$keymaster    = $this->factory->user->create( array(
			'role' => bbp_get_keymaster_role(),
		) );

		foreach ( array( bbp_get_private_status_id(), bbp_get_hidden_status_id() ) as $status ) {
			$restricted_forum = $this->factory->forum->create( array(
				'post_parent' => $public_forum,
				'post_status' => $status,
			) );
			$topic            = $this->factory->topic->create( array(
				'post_parent' => $restricted_forum,
				'topic_meta'  => array(
					'forum_id' => $restricted_forum,
				),
			) );
			$reply            = $this->factory->reply->create( array(
				'post_parent' => $topic,
				'post_title'  => 'Restricted reply title',
				'reply_meta'  => array(
					'forum_id' => $restricted_forum,
					'topic_id' => $topic,
				),
			) );

			bbp_update_forum_last_reply_id( $public_forum, $reply );
			bbp_update_forum_last_active_id( $public_forum, $reply );
			bbp_update_forum_last_active_time( $public_forum, get_post_field( 'post_date', $reply ) );

			wp_set_current_user( 0 );
			$this->assertSame( '-', bbp_get_forum_freshness_link( $public_forum ) );

			wp_set_current_user( $keymaster );
			$this->assertStringContainsString( 'Restricted reply title', bbp_get_forum_freshness_link( $public_forum ) );
		}
	}

	/**
	 * @covers ::bbp_suppress_private_forum_meta
	 * @covers ::bbp_suppress_private_author_link
	 */
	public function test_bbp_suppress_private_forum_meta_inherits_ancestor_visibility() {
		$public_forum = $this->factory->forum->create();
		$keymaster    = $this->factory->user->create( array(
			'role' => bbp_get_keymaster_role(),
		) );

		foreach ( array( bbp_get_private_status_id(), bbp_get_hidden_status_id() ) as $status ) {
			$restricted_parent = $this->factory->forum->create( array(
				'post_parent' => $public_forum,
				'post_status' => $status,
			) );
			$public_child      = $this->factory->forum->create( array(
				'post_parent' => $restricted_parent,
			) );
			$topic             = $this->factory->topic->create( array(
				'post_parent' => $public_child,
				'topic_meta'  => array(
					'forum_id' => $public_child,
				),
			) );
			$reply             = $this->factory->reply->create( array(
				'post_parent' => $topic,
				'post_title'  => 'Inherited restricted reply title',
				'reply_meta'  => array(
					'forum_id' => $public_child,
					'topic_id' => $topic,
				),
			) );

			bbp_update_forum_last_reply_id( $public_forum, $reply );
			bbp_update_forum_last_active_id( $public_forum, $reply );
			bbp_update_forum_last_active_time( $public_forum, get_post_field( 'post_date', $reply ) );

			wp_set_current_user( 0 );
			$this->assertSame( '-', bbp_get_forum_freshness_link( $public_forum ) );
			$this->assertSame( '', bbp_get_author_link( $reply ) );

			wp_set_current_user( $keymaster );
			$this->assertStringContainsString( 'Inherited restricted reply title', bbp_get_forum_freshness_link( $public_forum ) );
			$this->assertNotSame( '', bbp_get_author_link( $reply ) );
		}
	}

	/**
	 * @covers ::bbp_suppress_private_author_link
	 */
	public function test_bbp_suppress_private_author_link() {
		$keymaster = $this->factory->user->create( array(
			'role' => bbp_get_keymaster_role(),
		) );

		foreach ( array( bbp_get_private_status_id(), bbp_get_hidden_status_id() ) as $status ) {
			$restricted_forum = $this->factory->forum->create( array(
				'post_status' => $status,
			) );
			$topic            = $this->factory->topic->create( array(
				'post_parent' => $restricted_forum,
				'topic_meta'  => array(
					'forum_id' => $restricted_forum,
				),
			) );
			$reply            = $this->factory->reply->create( array(
				'post_parent' => $topic,
				'reply_meta'  => array(
					'forum_id' => $restricted_forum,
					'topic_id' => $topic,
				),
			) );

			wp_set_current_user( 0 );
			$this->assertSame( '', bbp_get_author_link( $reply ) );

			wp_set_current_user( $keymaster );
			$this->assertNotSame( '', bbp_get_author_link( $reply ) );
		}
	}
}
