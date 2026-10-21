<?php

/**
 * @group common
 * @group formatting
 * @group bbp_rel_ugc
 */
class BBP_Tests_Common_Formatting_Rel_Ugc extends BBP_UnitTestCase {

	/**
	 * @dataProvider content_types
	 */
	public function test_topic_and_reply_content_add_user_generated_relationships( $post_type ) {
		$forum_id = $this->factory->forum->create();
		$topic_id = $this->factory->topic->create( array( 'post_parent' => $forum_id, 'topic_meta' => array( 'forum_id' => $forum_id ) ) );
		$content  = '<a href="https://example.net/">External</a> <a href="' . home_url( '/forums/' ) . '">Internal</a>';

		if ( 'topic' === $post_type ) {
			wp_update_post( array( 'ID' => $topic_id, 'post_content' => $content ) );
			$post_id = $topic_id;
			$output = bbp_get_topic_content( $topic_id );
		} else {
			$reply_id = $this->factory->reply->create( array(
				'post_parent'  => $topic_id,
				'post_content' => $content,
				'reply_meta'   => array( 'forum_id' => $forum_id, 'topic_id' => $topic_id ),
			) );
			$post_id = $reply_id;
			$output = bbp_get_reply_content( $reply_id );
		}

		$this->assertStringContainsString( '<a href="https://example.net/" rel="nofollow ugc">External</a>', $output );
		$this->assertStringContainsString( '<a href="' . home_url( '/forums/' ) . '" rel="ugc">Internal</a>', $output );
		$this->assertSame( $content, get_post_field( 'post_content', $post_id, 'raw' ) );
	}

	public function content_types() {
		return array( array( 'topic' ), array( 'reply' ) );
	}

	/**
	 * @covers ::bbp_rel_ugc
	 */
	public function test_user_generated_links_preserve_existing_relationships_and_attributes() {
		$link = '<a href="https://example.net/?a=1&amp;b=2" title="A &amp; B" data-example rel="sponsored noopener">Link</a>';

		$this->assertSame( '<a href="https://example.net/?a=1&amp;b=2" title="A &amp; B" data-example rel="sponsored noopener nofollow ugc">Link</a>', bbp_rel_ugc( $link ) );
		$this->assertSame( bbp_rel_ugc( $link ), bbp_rel_ugc( bbp_rel_ugc( $link ) ) );
	}

	/**
	 * @covers ::bbp_rel_ugc
	 */
	public function test_user_generated_links_do_not_decode_escaped_attributes_or_code() {
		$link   = '<a href="https://example.net/" rel="nofollow x=\\x22\\x3Eimg\\x3Cimg src=x onerror=alert(1)\\x3E">Link</a>';
		$output = bbp_rel_ugc( $link );
		$code   = '<pre><code>&lt;a href=&quot;https://example.net/&quot;&gt;Link&lt;/a&gt;</code></pre>';

		$this->assertStringNotContainsString( '<img', $output );
		$this->assertStringContainsString( '\\x22', $output );
		$this->assertSame( $code, bbp_rel_ugc( $code ) );
	}

	public function test_generated_links_keep_their_existing_relationship_policy() {
		$link = '<a href="https://example.net/">Link</a>';

		foreach ( array( 'bbp_get_topic_reply_link', 'bbp_get_user_subscribe_link', 'bbp_get_user_profile_link' ) as $hook ) {
			$this->assertSame( '<a href="https://example.net/" rel="nofollow">Link</a>', apply_filters( $hook, $link ) );
		}
	}
}
