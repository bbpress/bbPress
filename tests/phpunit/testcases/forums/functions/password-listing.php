<?php

/**
 * Tests aggregate listings beneath password-protected forums.
 *
 * @group forums
 * @group visibility
 */
class BBP_Tests_Forums_Functions_Password_Listing extends BBP_UnitTestCase {
	private $cookie_name;
	private $old_cookie;
	private $old_topic_query;

	public function setUp(): void {
		parent::setUp();
		$this->cookie_name     = 'wp-postpass_' . COOKIEHASH;
		$this->old_cookie      = isset( $_COOKIE[ $this->cookie_name ] ) ? $_COOKIE[ $this->cookie_name ] : null;
		$this->old_topic_query = bbpress()->topic_query;
		$this->set_current_user( 0 );
	}

	public function tearDown(): void {
		if ( null === $this->old_cookie ) {
			unset( $_COOKIE[ $this->cookie_name ] );
		} else {
			$_COOKIE[ $this->cookie_name ] = $this->old_cookie;
		}
		bbpress()->topic_query = $this->old_topic_query;
		$this->set_current_user( 0 );
		parent::tearDown();
	}

	/**
	 * @covers ::bbp_pre_get_posts_normalize_forum_visibility
	 * @covers ::bbp_password_protected_search_where
	 */
	public function test_topics_stay_out_of_aggregate_listings_after_forum_unlock() {
		$password = wp_generate_password( 24, false, false );
		$parent   = $this->factory->forum->create( array( 'post_password' => $password ) );
		$child    = $this->factory->forum->create( array( 'post_parent' => $parent ) );
		$public   = $this->factory->forum->create();
		$topics   = array();

		foreach ( compact( 'parent', 'child', 'public' ) as $name => $forum_id ) {
			$topics[ $name ] = $this->factory->topic->create( array( 'post_parent' => $forum_id, 'topic_meta' => array( 'forum_id' => $forum_id ) ) );
		}

		$this->assertSame( array( $topics['public'] ), $this->listed_topic_ids() );
		$this->assertSame( 1, (int) bbpress()->topic_query->found_posts );

		// Direct requests still load the forum password form and topic page.
		$forum_query = new WP_Query( array( 'post_type' => bbp_get_forum_post_type(), 'p' => $parent ) );
		$topic_query = new WP_Query( array( 'post_type' => bbp_get_topic_post_type(), 'p' => $topics['parent'] ) );
		$this->assertSame( array( $parent ), wp_list_pluck( $forum_query->posts, 'ID' ) );
		$this->assertSame( array( $topics['parent'] ), wp_list_pluck( $topic_query->posts, 'ID' ) );

		$this->set_password_cookie( $password );
		$this->assertSame( 0, bbp_get_password_required_id( $child ) );
		$this->assertSame( array( $topics['public'] ), $this->listed_topic_ids() );
		bbp_has_topics( array( 'post_parent' => $parent, 'posts_per_page' => -1, 'show_stickies' => false ) );
		$this->assertContains( $topics['parent'], wp_list_pluck( bbpress()->topic_query->posts, 'ID' ) );
		bbp_has_topics( array( 'post_parent' => $child, 'posts_per_page' => -1, 'show_stickies' => false ) );
		$this->assertContains( $topics['child'], wp_list_pluck( bbpress()->topic_query->posts, 'ID' ) );

		$user_id = $this->factory->user->create();
		bbp_set_user_role( $user_id, bbp_get_keymaster_role() );
		$this->set_current_user( $user_id );
		$this->assertSame( array( $topics['public'] ), $this->listed_topic_ids() );
	}

	/**
	 * @covers ::bbp_pre_get_posts_normalize_forum_visibility
	 * @covers ::bbp_password_protected_search_where
	 */
	public function test_reply_and_mixed_listings_reject_protected_forum_ancestry() {
		$password  = wp_generate_password( 24, false, false );
		$protected = $this->factory->forum->create( array( 'post_password' => $password ) );
		$public    = $this->factory->forum->create();
		$topics    = array();
		$replies   = array();

		foreach ( compact( 'protected', 'public' ) as $name => $forum_id ) {
			$topics[ $name ]  = $this->factory->topic->create( array( 'post_parent' => $forum_id, 'topic_meta' => array( 'forum_id' => $forum_id ) ) );
			$replies[ $name ] = $this->factory->reply->create( array( 'post_parent' => $topics[ $name ], 'reply_meta' => array( 'forum_id' => $forum_id, 'topic_id' => $topics[ $name ] ) ) );
		}

		// Canonical ancestry wins when metadata incorrectly points to a public forum.
		update_post_meta( $topics['protected'], '_bbp_forum_id', $public );
		update_post_meta( $replies['protected'], '_bbp_forum_id', $public );

		$reply_query = new WP_Query( array( 'post_type' => bbp_get_reply_post_type(), 'post__in' => array_values( $replies ), 'posts_per_page' => -1 ) );
		$this->assertSame( array( $replies['public'] ), wp_list_pluck( $reply_query->posts, 'ID' ) );
		$this->assertSame( 1, (int) $reply_query->found_posts );

		$post_id = $this->factory->post->create();
		$mixed   = new WP_Query( array( 'post_type' => array( 'post', bbp_get_topic_post_type(), bbp_get_reply_post_type() ), 'post__in' => array_merge( array_values( $topics ), array_values( $replies ), array( $post_id ) ), 'posts_per_page' => -1 ) );
		$this->assertEqualSets( array( $post_id, $topics['public'], $replies['public'] ), wp_list_pluck( $mixed->posts, 'ID' ) );

		$this->assertSame( array( $topics['public'] ), wp_list_pluck( get_posts( array( 'post_type' => bbp_get_topic_post_type(), 'post__in' => array_values( $topics ) ) ), 'ID' ) );
		$this->assertSame( $topics['public'], bbp_get_public_child_last_id( $public, bbp_get_topic_post_type() ) );

		$this->set_password_cookie( $password );
		$this->assertSame( array( $topics['public'] ), wp_list_pluck( get_posts( array( 'post_type' => bbp_get_topic_post_type(), 'post__in' => array_values( $topics ) ) ), 'ID' ) );
	}

	/**
	 * @covers ::bbp_add_sticky_topics
	 * @covers ::bbp_pre_get_posts_normalize_forum_visibility
	 */
	public function test_super_stickies_stay_hidden_when_their_forum_is_protected() {
		$password       = wp_generate_password( 24, false, false );
		$protected      = $this->factory->forum->create( array( 'post_password' => $password ) );
		$public         = $this->factory->forum->create();
		$other_public   = $this->factory->forum->create();
		$locked_sticky  = $this->factory->topic->create( array( 'post_parent' => $protected, 'topic_meta' => array( 'forum_id' => $protected ) ) );
		$public_sticky  = $this->factory->topic->create( array( 'post_parent' => $other_public, 'topic_meta' => array( 'forum_id' => $other_public ) ) );

		bbp_stick_topic( $locked_sticky, true );
		bbp_stick_topic( $public_sticky, true );

		bbp_has_topics( array( 'post_parent' => $public, 'posts_per_page' => -1, 'show_stickies' => true ) );
		$this->assertContains( $public_sticky, wp_list_pluck( bbpress()->topic_query->posts, 'ID' ) );
		$this->assertNotContains( $locked_sticky, wp_list_pluck( bbpress()->topic_query->posts, 'ID' ) );

		$this->set_password_cookie( $password );
		bbp_has_topics( array( 'post_parent' => $public, 'posts_per_page' => -1, 'show_stickies' => true ) );
		$this->assertContains( $public_sticky, wp_list_pluck( bbpress()->topic_query->posts, 'ID' ) );
		$this->assertNotContains( $locked_sticky, wp_list_pluck( bbpress()->topic_query->posts, 'ID' ) );
	}

	private function listed_topic_ids() {
		bbp_has_topics( array( 'post_parent' => 'any', 'posts_per_page' => -1, 'show_stickies' => false ) );
		return wp_list_pluck( bbpress()->topic_query->posts, 'ID' );
	}

	private function set_password_cookie( $password ) {
		require_once ABSPATH . WPINC . '/class-phpass.php';
		$hasher                         = new PasswordHash( 8, true );
		$_COOKIE[ $this->cookie_name ] = $hasher->HashPassword( $password );
	}
}
