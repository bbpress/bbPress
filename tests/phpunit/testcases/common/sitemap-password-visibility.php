<?php

/**
 * Tests that WordPress sitemaps respect bbPress post passwords.
 *
 * @group common
 * @group visibility
 */
class BBP_Tests_Common_Sitemap_Password_Visibility extends BBP_UnitTestCase {

	public function tearDown(): void {
		$this->set_current_user( 0 );

		parent::tearDown();
	}

	/**
	 * @covers ::bbp_exclude_password_protected_sitemap_posts
	 * @covers ::bbp_password_protected_search_where
	 */
	public function test_sitemaps_exclude_password_protected_forum_content_for_all_roles() {
		$public_forum = $this->factory->forum->create();
		$public_topic = $this->factory->topic->create( array( 'post_parent' => $public_forum ) );
		$public_reply = $this->factory->reply->create( array( 'post_parent' => $public_topic ) );

		$protected_forum = $this->factory->forum->create( array( 'post_password' => 'forum-secret' ) );
		$protected_topic = $this->factory->topic->create( array( 'post_parent' => $protected_forum ) );
		$protected_reply = $this->factory->reply->create( array( 'post_parent' => $protected_topic ) );
		update_post_meta( $protected_topic, '_bbp_forum_id', $public_forum );
		update_post_meta( $protected_reply, '_bbp_forum_id', $public_forum );
		$child_forum     = $this->factory->forum->create( array( 'post_parent' => $protected_forum ) );
		$child_topic     = $this->factory->topic->create( array( 'post_parent' => $child_forum ) );
		$child_reply     = $this->factory->reply->create( array( 'post_parent' => $child_topic ) );
		$metadata_topic = $this->factory->topic->create( array( 'post_parent' => 0 ) );
		update_post_meta( $metadata_topic, '_bbp_forum_id', $protected_forum );
		$metadata_reply = $this->factory->reply->create( array( 'post_parent' => $metadata_topic ) );
		update_post_meta( $metadata_reply, '_bbp_forum_id', $protected_forum );

		$own_password_topic = $this->factory->topic->create(
			array( 'post_parent' => $public_forum, 'post_password' => 'topic-secret' )
		);
		$own_password_reply = $this->factory->reply->create( array( 'post_parent' => $own_password_topic ) );
		$ordinary_password_post = $this->factory->post->create( array( 'post_password' => 'post-secret' ) );
		$private_forum         = $this->factory->forum->create( array( 'post_status' => bbp_get_private_status_id() ) );
		$private_topic         = $this->factory->topic->create( array( 'post_parent' => $private_forum ) );
		$hidden_forum          = $this->factory->forum->create( array( 'post_status' => bbp_get_hidden_status_id() ) );
		$hidden_topic          = $this->factory->topic->create( array( 'post_parent' => $hidden_forum ) );

		$user_ids = array(
			0,
			$this->create_user_with_bbp_role( bbp_get_participant_role() ),
			$this->create_user_with_bbp_role( bbp_get_moderator_role() ),
			$this->create_user_with_bbp_role( bbp_get_keymaster_role() ),
		);

		foreach ( $user_ids as $user_id ) {
			$this->set_current_user( $user_id );

			$forum_urls = $this->get_sitemap_urls( bbp_get_forum_post_type() );
			$topic_urls = $this->get_sitemap_urls( bbp_get_topic_post_type() );
			$reply_urls = $this->get_sitemap_urls( bbp_get_reply_post_type() );

			$this->assertContains( get_permalink( $public_forum ), $forum_urls );
			$this->assertNotContains( get_permalink( $protected_forum ), $forum_urls );
			$this->assertNotContains( get_permalink( $child_forum ), $forum_urls );

			$this->assertContains( get_permalink( $public_topic ), $topic_urls );
			$this->assertNotContains( get_permalink( $protected_topic ), $topic_urls );
			$this->assertNotContains( get_permalink( $child_topic ), $topic_urls );
			$this->assertNotContains( get_permalink( $metadata_topic ), $topic_urls );
			$this->assertNotContains( get_permalink( $own_password_topic ), $topic_urls );

			$this->assertContains( get_permalink( $public_reply ), $reply_urls );
			$this->assertNotContains( get_permalink( $protected_reply ), $reply_urls );
			$this->assertNotContains( get_permalink( $child_reply ), $reply_urls );
			$this->assertNotContains( get_permalink( $metadata_reply ), $reply_urls );
			$this->assertNotContains( get_permalink( $own_password_reply ), $reply_urls );

			// WordPress post-password behavior outside bbPress is unchanged.
			$this->assertContains( get_permalink( $ordinary_password_post ), $this->get_sitemap_urls( 'post' ) );

			if ( 0 === $user_id ) {
				$this->assertNotContains( get_permalink( $private_forum ), $forum_urls );
				$this->assertNotContains( get_permalink( $hidden_forum ), $forum_urls );
				$this->assertNotContains( get_permalink( $private_topic ), $topic_urls );
				$this->assertNotContains( get_permalink( $hidden_topic ), $topic_urls );
			}
		}

		// A visitor's forum cookie must not publish its URLs in the sitemap.
		require_once ABSPATH . WPINC . '/class-phpass.php';
		$cookie_name = 'wp-postpass_' . COOKIEHASH;
		$old_cookie  = isset( $_COOKIE[ $cookie_name ] ) ? $_COOKIE[ $cookie_name ] : null;
		$hasher      = new PasswordHash( 8, true );
		$_COOKIE[ $cookie_name ] = $hasher->HashPassword( get_post_field( 'post_password', $protected_forum ) );

		try {
			$this->set_current_user( 0 );
			$this->assertNotContains( get_permalink( $protected_forum ), $this->get_sitemap_urls( bbp_get_forum_post_type() ) );
			$this->assertNotContains( get_permalink( $protected_topic ), $this->get_sitemap_urls( bbp_get_topic_post_type() ) );
			$this->assertNotContains( get_permalink( $protected_reply ), $this->get_sitemap_urls( bbp_get_reply_post_type() ) );
		} finally {
			if ( null === $old_cookie ) {
				unset( $_COOKIE[ $cookie_name ] );
			} else {
				$_COOKIE[ $cookie_name ] = $old_cookie;
			}
		}
	}

	/**
	 * @covers ::bbp_exclude_password_protected_sitemap_posts
	 * @covers ::bbp_get_password_protected_forum_ids
	 */
	public function test_sitemap_reflects_forum_password_changes_and_whitespace_passwords() {
		$forum_id = $this->factory->forum->create();
		$topic_id = $this->factory->topic->create( array( 'post_parent' => $forum_id ) );
		$this->set_current_user( 0 );

		$this->assertContains( get_permalink( $forum_id ), $this->get_sitemap_urls( bbp_get_forum_post_type() ) );
		$this->assertContains( get_permalink( $topic_id ), $this->get_sitemap_urls( bbp_get_topic_post_type() ) );

		wp_update_post( array( 'ID' => $forum_id, 'post_password' => '   ' ) );
		$this->assertNotContains( get_permalink( $forum_id ), $this->get_sitemap_urls( bbp_get_forum_post_type() ) );
		$this->assertNotContains( get_permalink( $topic_id ), $this->get_sitemap_urls( bbp_get_topic_post_type() ) );

		wp_update_post( array( 'ID' => $forum_id, 'post_password' => '' ) );
		$this->assertContains( get_permalink( $forum_id ), $this->get_sitemap_urls( bbp_get_forum_post_type() ) );
		$this->assertContains( get_permalink( $topic_id ), $this->get_sitemap_urls( bbp_get_topic_post_type() ) );
	}

	/**
	 * @covers ::bbp_pre_get_posts_normalize_forum_visibility
	 */
	public function test_sitemap_fails_closed_if_post_query_filters_are_suppressed() {
		$forum_id = $this->factory->forum->create( array( 'post_password' => 'forum-secret' ) );
		$topic_id = $this->factory->topic->create( array( 'post_parent' => $forum_id ) );
		$filter   = function ( $args, $post_type ) {
			if ( bbp_get_topic_post_type() === $post_type ) {
				$args['suppress_filters'] = true;
			}

			return $args;
		};

		add_filter( 'wp_sitemaps_posts_query_args', $filter, 20, 2 );
		try {
			$this->assertNotContains( get_permalink( $topic_id ), $this->get_sitemap_urls( bbp_get_topic_post_type() ) );
		} finally {
			remove_filter( 'wp_sitemaps_posts_query_args', $filter, 20 );
		}
	}

	/**
	 * Return the URLs generated by WordPress's post sitemap provider.
	 *
	 * @param string $post_type Post type.
	 * @return string[] Sitemap URLs.
	 */
	private function get_sitemap_urls( $post_type ) {
		$sitemap = new WP_Sitemaps_Posts();

		return wp_list_pluck( $sitemap->get_url_list( 1, $post_type ), 'loc' );
	}

	/**
	 * Create a user with a bbPress role.
	 *
	 * @param string $role bbPress role.
	 * @return int User ID.
	 */
	private function create_user_with_bbp_role( $role ) {
		$user_id = $this->factory->user->create();
		bbp_set_user_role( $user_id, $role );

		return $user_id;
	}
}
