<?php

/**
 * oEmbed must respect bbPress visibility.
 *
 * @group common
 * @group visibility
 */
class BBP_Tests_Common_Oembed_Visibility extends BBP_UnitTestCase {

	/**
	 * @covers ::get_oembed_response_data_for_url
	 */
	public function test_hidden_forum_topic_has_no_oembed_response_for_visitor() {
		$forum_id = $this->factory->forum->create();
		$topic_id = $this->factory->topic->create( array(
			'post_parent' => $forum_id,
			'post_title'  => 'Hidden embed marker',
			'topic_meta'  => array( 'forum_id' => $forum_id ),
		) );
		bbp_hide_forum( $forum_id );
		$this->set_current_user( 0 );

		$url = home_url( '/?p=' . $topic_id );
		$this->assertFalse( get_oembed_response_data_for_url( $url, array( 'width' => 600 ) ) );
		$this->assertFalse( apply_filters( 'redirect_canonical', get_permalink( $topic_id ), $url ) );

		bbp_publicize_forum( $forum_id, bbp_get_hidden_status_id() );
		$this->assertNotFalse( get_oembed_response_data_for_url( $url, array( 'width' => 600 ) ) );
		$this->assertSame( get_permalink( $topic_id ), apply_filters( 'redirect_canonical', get_permalink( $topic_id ), $url ) );
	}

	/**
	 * @covers ::bbp_get_reply_title_fallback
	 */
	public function test_reply_title_fallback_does_not_reveal_unreadable_topic_title() {
		$forum_id = $this->factory->forum->create();
		$topic_id = $this->factory->topic->create( array(
			'post_parent' => $forum_id,
			'post_title'  => 'Unapproved topic marker',
			'post_status' => bbp_get_pending_status_id(),
			'topic_meta'  => array( 'forum_id' => $forum_id ),
		) );
		$reply_id = $this->factory->reply->create( array(
			'post_parent' => $topic_id,
			'reply_meta'  => array( 'forum_id' => $forum_id, 'topic_id' => $topic_id ),
		) );
		$this->set_current_user( 0 );

		$this->assertStringNotContainsString( 'Unapproved topic marker', bbp_get_reply_title_fallback( '', $reply_id ) );
		wp_update_post( array( 'ID' => $topic_id, 'post_status' => bbp_get_public_status_id() ) );
		wp_update_post( array( 'ID' => $reply_id, 'post_status' => bbp_get_public_status_id() ) );
		$this->assertStringContainsString( 'Unapproved topic marker', bbp_get_reply_title_fallback( '', $reply_id ) );
	}
}
