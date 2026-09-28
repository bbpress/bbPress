<?php

/**
 * Tests for public WordPress user discovery with bbPress content.
 *
 * @group common
 * @group rest
 */
class BBP_Tests_Common_User_Discovery extends BBP_UnitTestCase {

	/**
	 * A user with only restricted forum content is not a public WordPress author.
	 */
	public function test_restricted_forum_author_is_not_discovered() {
		$forum_author_id = $this->factory->user->create();
		$post_author_id  = $this->factory->user->create();
		$forum_id       = $this->factory->forum->create(
			array( 'post_status' => bbp_get_hidden_status_id() )
		);

		$this->factory->topic->create(
			array(
				'post_author' => $forum_author_id,
				'post_parent' => $forum_id,
				'topic_meta'  => array( 'forum_id' => $forum_id ),
			)
		);
		$this->factory->post->create( array( 'post_author' => $post_author_id ) );
		$this->set_current_user( 0 );

		$collection = rest_get_server()->dispatch( new WP_REST_Request( 'GET', '/wp/v2/users' ) );
		$ids        = wp_list_pluck( $collection->get_data(), 'id' );
		$this->assertSame( 200, $collection->get_status() );
		$this->assertNotContains( $forum_author_id, $ids );
		$this->assertContains( $post_author_id, $ids );

		$forum_only_request = new WP_REST_Request( 'GET', '/wp/v2/users' );
		$forum_only_request->set_param( 'has_published_posts', array( bbp_get_topic_post_type() ) );
		$forum_only = rest_get_server()->dispatch( $forum_only_request );
		$this->assertSame( 200, $forum_only->get_status() );
		$this->assertSame( array(), $forum_only->get_data() );

		$single = rest_get_server()->dispatch( new WP_REST_Request( 'GET', '/wp/v2/users/' . $forum_author_id ) );
		$this->assertSame( 401, $single->get_status() );

		$single = rest_get_server()->dispatch( new WP_REST_Request( 'GET', '/wp/v2/users/' . $post_author_id ) );
		$this->assertSame( 200, $single->get_status() );

		$this->set_current_user( $forum_author_id );
		$single = rest_get_server()->dispatch( new WP_REST_Request( 'GET', '/wp/v2/users/' . $forum_author_id ) );
		$this->assertSame( 200, $single->get_status() );
		$this->set_current_user( 0 );

		$sitemap = new WP_Sitemaps_Users();
		$urls    = wp_list_pluck( $sitemap->get_url_list( 1 ), 'loc' );
		$this->assertNotContains( get_author_posts_url( $forum_author_id ), $urls );
		$this->assertContains( get_author_posts_url( $post_author_id ), $urls );

		$admin_id = $this->factory->user->create( array( 'role' => 'administrator' ) );
		$this->set_current_user( $admin_id );
		$forum_only = rest_get_server()->dispatch( $forum_only_request );
		$this->assertContains( $forum_author_id, wp_list_pluck( $forum_only->get_data(), 'id' ) );
		$single = rest_get_server()->dispatch( new WP_REST_Request( 'GET', '/wp/v2/users/' . $forum_author_id ) );
		$this->assertSame( 200, $single->get_status() );
	}
}
