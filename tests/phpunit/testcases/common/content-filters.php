<?php

/**
 * Tests for bbPress content passed through WordPress content filters.
 *
 * @group common
 * @group content
 */
class BBP_Tests_Common_Content_Filters extends BBP_UnitTestCase {

	/**
	 * Shortcodes must continue to run in a classic bbPress Page Template.
	 *
	 * @covers bbp_pre_do_shortcode_tag
	 * @covers bbp_prevent_content_shortcodes
	 */
	public function test_shortcodes_run_in_classic_bbpress_page_template() {
		$page_id         = $this->factory->post->create(
			array(
				'post_type'    => 'page',
				'post_content' => '[bbp-stats] [bbp_content_probe]',
			)
		);
		$wp_query       = bbp_get_wp_query();
		$old_post       = isset( $GLOBALS['post'] ) ? $GLOBALS['post'] : null;
		$old_query_post = $wp_query->post;
		$old_posts      = $wp_query->posts;
		$render         = function( $output ) {
			return $output . 'classic-page-bbpress-ran';
		};

		update_post_meta( $page_id, '_wp_page_template', 'page-front-forums.php' );
		add_filter( 'bbp_display_shortcode', $render );
		add_shortcode( 'bbp_content_probe', function() {
			return 'classic-page-shortcode-ran';
		} );

		try {
			$GLOBALS['post'] = get_post( $page_id );
			$wp_query->post  = $GLOBALS['post'];
			$wp_query->posts = array( $GLOBALS['post'] );
			$wp_query->setup_postdata( $GLOBALS['post'] );
			$content = apply_filters( 'the_content', $GLOBALS['post']->post_content );

			$this->assertSame( 'page-front-forums.php', get_page_template_slug( $page_id ) );
			$this->assertStringContainsString( 'classic-page-bbpress-ran', $content );
			$this->assertStringContainsString( 'classic-page-shortcode-ran', $content );
		} finally {
			remove_shortcode( 'bbp_content_probe' );
			remove_filter( 'bbp_display_shortcode', $render );
			$GLOBALS['post'] = $old_post;
			$wp_query->post  = $old_query_post;
			$wp_query->posts = $old_posts;
			wp_reset_postdata();
		}
	}

	/**
	 * @covers bbp_pre_do_shortcode_tag
	 * @covers bbp_prevent_content_shortcodes
	 */
	public function test_wordpress_content_keeps_forum_topic_and_reply_shortcodes_literal() {
		$shortcode = '[bbp_content_probe] [[bbp_content_probe]]';
		$forum_id = $this->factory->forum->create( array( 'post_content' => $shortcode ) );
		$topic_id = $this->factory->topic->create(
			array(
				'post_author'  => 0,
				'post_parent'  => $forum_id,
				'post_content' => $shortcode,
				'topic_meta'   => array( 'forum_id' => $forum_id ),
			)
		);
		$reply_id = $this->factory->reply->create(
			array(
				'post_author'  => 0,
				'post_parent'  => $topic_id,
				'post_content' => $shortcode,
				'reply_meta'   => array( 'forum_id' => $forum_id, 'topic_id' => $topic_id ),
			)
		);
		$post_id = $this->factory->post->create( array( 'post_content' => $shortcode ) );

		$old_post = isset( $GLOBALS['post'] ) ? $GLOBALS['post'] : null;
		add_shortcode( 'bbp_content_probe', function() {
			return 'bbp-probe-executed';
		} );

		try {
			$this->set_current_user( 0 );
			register_post_type( 'bbp_test_content', array( 'public' => true, 'source' => 'bbpress' ) );
			$extension_id = $this->factory->post->create(
				array(
					'post_type'    => 'bbp_test_content',
					'post_content' => $shortcode,
				)
			);

			foreach ( array( $forum_id, $topic_id, $reply_id, $extension_id ) as $post_id_to_test ) {
				$GLOBALS['post'] = get_post( $post_id_to_test );
				$content = apply_filters( 'the_content', $GLOBALS['post']->post_content );

				$this->assertStringContainsString( '[bbp_content_probe]', html_entity_decode( $content ) );
				$this->assertStringNotContainsString( 'bbp-probe-executed', $content );
			}

			$GLOBALS['post'] = get_post( $post_id );
			$this->assertStringContainsString( 'bbp-probe-executed', apply_filters( 'the_content', $GLOBALS['post']->post_content ) );

			// bbPress and other shortcodes outside the content filter still run.
			$GLOBALS['post'] = get_post( $topic_id );
			$this->assertStringContainsString( 'bbp-probe-executed', do_shortcode( '[bbp_content_probe]' ) );
		} finally {
			$GLOBALS['post'] = $old_post;
			remove_shortcode( 'bbp_content_probe' );
			unregister_post_type( 'bbp_test_content' );
		}
	}

	/**
	 * @covers bbp_pre_do_shortcode_tag
	 * @covers bbp_prevent_content_shortcodes
	 */
	public function test_explicitly_allowed_shortcode_runs_only_in_bbpress_content() {
		$shortcode = '[bbp_content_allowed] [bbp_content_blocked]';
		$forum_id  = $this->factory->forum->create( array( 'post_content' => $shortcode ) );
		$post_id   = $this->factory->post->create( array( 'post_content' => $shortcode ) );
		$old_post  = isset( $GLOBALS['post'] ) ? $GLOBALS['post'] : null;
		$allow     = function( $allowed ) {
			$allowed[] = 'bbp_content_allowed';
			return $allowed;
		};

		add_shortcode( 'bbp_content_allowed', function() {
			return 'allowed-shortcode-ran';
		} );
		add_shortcode( 'bbp_content_blocked', function() {
			return 'blocked-shortcode-ran';
		} );
		add_filter( 'bbp_allowed_content_shortcodes', $allow );

		try {
			$GLOBALS['post'] = get_post( $forum_id );
			$content = apply_filters( 'the_content', $shortcode );
			$this->assertStringContainsString( 'allowed-shortcode-ran', $content );
			$this->assertStringContainsString( '[bbp_content_blocked]', $content );
			$this->assertStringNotContainsString( 'blocked-shortcode-ran', $content );

			$GLOBALS['post'] = get_post( $post_id );
			$content = apply_filters( 'the_content', $shortcode );
			$this->assertStringContainsString( 'allowed-shortcode-ran', $content );
			$this->assertStringContainsString( 'blocked-shortcode-ran', $content );
		} finally {
			$GLOBALS['post'] = $old_post;
			remove_filter( 'bbp_allowed_content_shortcodes', $allow );
			remove_shortcode( 'bbp_content_allowed' );
			remove_shortcode( 'bbp_content_blocked' );
		}
	}

	/**
	 * Topic and reply shortcodes must not render in WordPress feeds.
	 *
	 * @dataProvider get_topic_and_reply_post_types
	 *
	 * @covers bbp_pre_do_shortcode_tag
	 * @covers bbp_prevent_content_shortcodes
	 *
	 * @param string $post_type bbPress post type.
	 */
	public function test_wordpress_feeds_remove_topic_and_reply_shortcodes( $post_type ) {
		$shortcode       = "Before feed shortcode.\n\n[bbp_content_feed_probe]\n\nAfter feed shortcode. [bbp_unregistered_feed_code]";
		$post_id         = $this->factory->post->create(
			array(
				'post_type'    => $post_type,
				'post_content' => $shortcode,
			)
		);
		$wp_query       = bbp_get_wp_query();
		$old_post       = isset( $GLOBALS['post'] ) ? $GLOBALS['post'] : null;
		$old_query_post = $wp_query->post;
		$old_posts      = $wp_query->posts;
		$old_is_feed    = $wp_query->is_feed;
		$executions     = 0;
		$allow          = function( $allowed ) {
			$allowed[] = 'bbp_content_feed_probe';
			return $allowed;
		};

		add_shortcode( 'bbp_content_feed_probe', function() use ( &$executions ) {
			++$executions;
			return 'feed-shortcode-ran';
		} );
		add_filter( 'bbp_allowed_content_shortcodes', $allow );

		try {
			$GLOBALS['post'] = get_post( $post_id );
			$wp_query->post  = $GLOBALS['post'];
			$wp_query->posts = array( $GLOBALS['post'] );
			$wp_query->setup_postdata( $GLOBALS['post'] );
			$wp_query->is_feed = true;
			$content = get_the_content_feed( 'rss2' );

			$this->assertSame( 0, $executions );
			$this->assertStringContainsString( '<p>Before feed shortcode.</p>', $content );
			$this->assertStringContainsString( '<p>After feed shortcode. [bbp_unregistered_feed_code]</p>', $content );
			$this->assertStringNotContainsString( 'bbp_content_feed_probe', $content );
			$this->assertStringNotContainsString( 'feed-shortcode-ran', $content );
		} finally {
			remove_filter( 'bbp_allowed_content_shortcodes', $allow );
			remove_shortcode( 'bbp_content_feed_probe' );
			$GLOBALS['post'] = $old_post;
			$wp_query->post  = $old_query_post;
			$wp_query->posts = $old_posts;
			$wp_query->is_feed = $old_is_feed;
			wp_reset_postdata();
		}
	}

	/**
	 * Topic and reply post types.
	 *
	 * @return array[] Topic and reply post types.
	 */
	public function get_topic_and_reply_post_types() {
		return array(
			'topic' => array( bbp_get_topic_post_type() ),
			'reply' => array( bbp_get_reply_post_type() ),
		);
	}

	/**
	 * @covers bbp_prevent_content_shortcodes
	 */
	public function test_private_attachment_gallery_is_not_rendered_from_forum_content() {
		$private_post_id = $this->factory->post->create( array( 'post_status' => 'private' ) );
		$attachment_id   = wp_insert_attachment(
			array(
				'post_title'     => 'Private gallery control',
				'post_status'    => 'inherit',
				'post_parent'    => $private_post_id,
				'post_mime_type' => 'image/png',
				'guid'           => 'https://example.org/private-gallery-test.png',
			)
		);
		update_post_meta( $attachment_id, '_wp_attached_file', 'private-gallery-test.png' );
		update_post_meta(
			$attachment_id,
			'_wp_attachment_metadata',
			array( 'width' => 1, 'height' => 1, 'file' => 'private-gallery-test.png', 'sizes' => array() )
		);

		$shortcode = '[gallery ids="' . $attachment_id . '" size="full" link="file"]';
		$forum_id  = $this->factory->forum->create( array( 'post_content' => $shortcode ) );
		$topic_id  = $this->factory->topic->create(
			array(
				'post_parent'  => $forum_id,
				'post_content' => $shortcode,
				'topic_meta'   => array( 'forum_id' => $forum_id ),
			)
		);
		$reply_id  = $this->factory->reply->create(
			array(
				'post_parent'  => $topic_id,
				'post_content' => $shortcode,
				'reply_meta'   => array( 'forum_id' => $forum_id, 'topic_id' => $topic_id ),
			)
		);
		$post_id   = $this->factory->post->create( array( 'post_content' => $shortcode ) );
		$media_url = wp_get_attachment_url( $attachment_id );
		$old_post  = isset( $GLOBALS['post'] ) ? $GLOBALS['post'] : null;

		try {
			$this->set_current_user( 0 );
			$this->assertSame( 'private', get_post_status( $attachment_id ) );

			// Core's gallery shortcode renders this image in an ordinary post.
			$GLOBALS['post'] = get_post( $post_id );
			$this->assertStringContainsString( $media_url, apply_filters( 'the_content', $shortcode ) );

			// Direct bbPress rendering leaves the shortcode literal too.
			foreach ( array( bbp_get_forum_content( $forum_id ), bbp_get_topic_content( $topic_id ), bbp_get_reply_content( $reply_id ) ) as $content ) {
				$this->assertStringContainsString( '[gallery ids=', $content );
				$this->assertStringNotContainsString( $media_url, $content );
			}

			foreach ( array( $forum_id, $topic_id, $reply_id ) as $post_id_to_test ) {
				$GLOBALS['post'] = get_post( $post_id_to_test );
				$content = apply_filters( 'the_content', $shortcode );

				$this->assertStringContainsString( '[gallery ids=', $content );
				$this->assertStringNotContainsString( $media_url, $content );
			}
		} finally {
			$GLOBALS['post'] = $old_post;
		}
	}
}
