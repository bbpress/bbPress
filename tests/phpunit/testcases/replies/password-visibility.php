<?php

/**
 * Password inheritance for reply content.
 *
 * @group replies
 * @group visibility
 */
class BBP_Tests_Replies_Password_Visibility extends BBP_UnitTestCase {

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
}
