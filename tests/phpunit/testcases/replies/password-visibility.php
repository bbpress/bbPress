<?php

/**
 * Password inheritance for reply content.
 *
 * @group replies
 * @group visibility
 */
class BBP_Tests_Replies_Password_Visibility extends BBP_UnitTestCase {

	/**
	 * @covers ::bbp_filter_post_password_required
	 */
	public function test_password_required_guard_allows_unrelated_nested_checks() {
		$forum_id = $this->factory->forum->create(
			array( 'post_password' => 'first-secret' )
		);
		list( $topic_id, $reply_id ) = $this->create_reply_in_forum( $forum_id );

		$other_forum_id = $this->factory->forum->create(
			array( 'post_password' => 'second-secret' )
		);
		list( , $other_reply_id ) = $this->create_reply_in_forum( $other_forum_id );
		$nested_password_id = 0;
		$filter             = function( $required, $post ) use ( $topic_id, $other_reply_id, &$nested_password_id ) {
			if ( $topic_id === $post->ID ) {
				$nested_password_id = bbp_get_password_required_id( $other_reply_id );
			}

			return $required;
		};

		add_filter( 'post_password_required', $filter, 20, 2 );

		try {
			$this->assertSame( $forum_id, bbp_get_password_required_id( $reply_id ) );
			$this->assertSame( $other_forum_id, $nested_password_id );
		} finally {
			remove_filter( 'post_password_required', $filter, 20 );
		}
	}

	/**
	 * @covers ::bbp_filter_post_password_required
	 */
	public function test_password_required_filter_ignores_missing_post() {
		$this->assertFalse( bbp_filter_post_password_required( false, null ) );
	}

	/**
	 * @covers ::bbp_filter_post_password_required
	 */
	public function test_core_reply_listings_require_parent_forum_password_for_all_roles() {
		$forum_id = $this->factory->forum->create(
			array( 'post_password' => 'forum-secret' )
		);
		list( , $reply_id ) = $this->create_reply_in_forum(
			$forum_id,
			'Protected core reply marker'
		);
		$user_ids = array(
			0,
			$this->create_user_without_role(),
			$this->create_user_with_bbp_role( bbp_get_participant_role() ),
			$this->create_user_with_bbp_role( bbp_get_moderator_role() ),
			$this->create_user_with_bbp_role( bbp_get_keymaster_role() ),
		);
		$cookie     = 'wp-postpass_' . COOKIEHASH;
		$old_cookie = isset( $_COOKIE[ $cookie ] ) ? $_COOKIE[ $cookie ] : null;

		try {
			foreach ( $user_ids as $user_id ) {
				$this->set_current_user( $user_id );

				foreach ( array( '/?post_type=reply', '/?s=Protected&post_type=reply' ) as $url ) {
					$this->go_to_without_reregistering_blocks( $url );
					$this->assertContains( $reply_id, wp_list_pluck( $GLOBALS['wp_query']->posts, 'ID' ) );
					$this->assertTrue( post_password_required( $reply_id ) );
					$this->assertStringNotContainsString( 'Protected core reply marker', get_the_content( null, false, $reply_id ) );
				}
			}

			require_once ABSPATH . WPINC . '/class-phpass.php';
			$hasher            = new PasswordHash( 8, true );
			$_COOKIE[ $cookie ] = $hasher->HashPassword( 'forum-secret' );

			$this->set_current_user( 0 );
			$this->assertFalse( post_password_required( $reply_id ) );
			$this->assertStringContainsString( 'Protected core reply marker', get_the_content( null, false, $reply_id ) );
		} finally {
			$this->set_current_user( 0 );

			if ( null === $old_cookie ) {
				unset( $_COOKIE[ $cookie ] );
			} else {
				$_COOKIE[ $cookie ] = $old_cookie;
			}
		}
	}

	/**
	 * @covers ::bbp_get_reply_content
	 * @covers ::bbp_get_reply_excerpt
	 */
	public function test_reply_content_and_excerpt_require_parent_topic_password() {
		$forum_id = $this->factory->forum->create();
		$topic_id = $this->factory->topic->create( array(
			'post_parent'   => $forum_id,
			'post_password' => 'topic-secret',
			'topic_meta'    => array( 'forum_id' => $forum_id ),
		) );
		$reply_id = $this->factory->reply->create( array(
			'post_parent'  => $topic_id,
			'post_content' => 'Protected reply marker',
			'post_excerpt' => 'Protected excerpt marker',
			'reply_meta'   => array( 'forum_id' => $forum_id, 'topic_id' => $topic_id ),
		) );

		$this->assertStringNotContainsString( 'Protected reply marker', bbp_get_reply_content( $reply_id ) );
		$this->assertStringNotContainsString( 'Protected excerpt marker', bbp_get_reply_excerpt( $reply_id ) );

		require_once ABSPATH . WPINC . '/class-phpass.php';
		$hasher = new PasswordHash( 8, true );
		$cookie = 'wp-postpass_' . COOKIEHASH;
		$old_cookie = isset( $_COOKIE[ $cookie ] ) ? $_COOKIE[ $cookie ] : null;
		$_COOKIE[ $cookie ] = $hasher->HashPassword( 'topic-secret' );

		try {
			$this->assertStringContainsString( 'Protected reply marker', bbp_get_reply_content( $reply_id ) );
			$this->assertStringContainsString( 'Protected excerpt marker', bbp_get_reply_excerpt( $reply_id ) );
		} finally {
			if ( null === $old_cookie ) {
				unset( $_COOKIE[ $cookie ] );
			} else {
				$_COOKIE[ $cookie ] = $old_cookie;
			}
		}
	}

	private function create_user_without_role() {
		$user_id = $this->factory->user->create();
		$user    = new WP_User( $user_id );
		$user->set_role( '' );

		return $user_id;
	}

	private function create_reply_in_forum( $forum_id, $content = '' ) {
		$topic_id = $this->factory->topic->create(
			array(
				'post_parent' => $forum_id,
				'topic_meta'  => array( 'forum_id' => $forum_id ),
			)
		);
		$reply_id = $this->factory->reply->create(
			array(
				'post_parent'  => $topic_id,
				'post_content' => $content,
				'reply_meta'   => array( 'forum_id' => $forum_id, 'topic_id' => $topic_id ),
			)
		);

		return array( $topic_id, $reply_id );
	}

	private function create_user_with_bbp_role( $role ) {
		$user_id = $this->factory->user->create();
		bbp_set_user_role( $user_id, $role );

		return $user_id;
	}

	private function go_to_without_reregistering_blocks( $url ) {
		remove_action( 'bbp_register', 'bbp_register_blocks', 10 );
		try {
			$this->go_to( home_url( $url ) );
		} finally {
			add_action( 'bbp_register', 'bbp_register_blocks', 10 );
		}
	}
}
