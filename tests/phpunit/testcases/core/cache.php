<?php
/**
 * Tests for the cache functions.
 *
 * @group cache
 */
class BBP_Core_Cache_Tests extends BBP_UnitTestCase {

	/**
	 * @group counts
	 * @covers ::bbp_clean_post_cache
	 */
	public function test_bbp_clean_post_cache() {

		// Get the post types.
		$tpt = bbp_get_topic_post_type();
		$rpt = bbp_get_reply_post_type();

		// Set up a forum with 1 topic and 1 reply to that topic.
		$f = $this->factory->forum->create();
		$t = $this->factory->topic->create( array(
			'post_parent' => $f,
			'topic_meta' => array(
				'forum_id' => $f,
			),
		) );
		$r = $this->factory->reply->create( array(
			'post_parent' => $t,
			'reply_meta' => array(
				'forum_id' => $f,
				'topic_id' => $t,
			),
		) );

		// Make sure we've cached some data.
		bbp_get_all_child_ids( $f, $tpt );
		bbp_get_all_child_ids( $t, $rpt );

		// Setup
		$f_key        = md5( serialize( array( 'parent_id' => $f, 'post_type' => $tpt, 'post_status' => array( 'draft', 'future' ) ) ) );
		$t_key        = md5( serialize( array( 'parent_id' => $t, 'post_type' => $rpt, 'post_status' => array( 'draft', 'future' ) ) ) );
		$last_changed = wp_cache_get_last_changed( 'bbpress_posts' );

		// Keys
		$f_key = "bbp_child_ids:{$f_key}:{$last_changed}";
		$t_key = "bbp_child_ids:{$t_key}:{$last_changed}";

		$this->assertEquals( array( $t ), wp_cache_get( $f_key, 'bbpress_posts' ) );
		$this->assertEquals( array( $r ), wp_cache_get( $t_key, 'bbpress_posts' ) );

		// Clean the reply cache.
		clean_post_cache( $r );

		// Setup
		$last_changed = wp_cache_get_last_changed( 'bbpress_posts' );

		// Keys
		$f_key = "bbp_child_ids:{$f_key}:{$last_changed}";
		$t_key = "bbp_child_ids:{$t_key}:{$last_changed}";

		$this->assertEquals( false, wp_cache_get( $f_key, 'bbpress_posts' ) );
		$this->assertEquals( false, wp_cache_get( $t_key, 'bbpress_posts' ) );
	}

	/**
	 * Updating a bbPress post must not suspend later cache invalidation.
	 */
	public function test_post_update_does_not_suspend_later_cache_invalidation() {
		$post_id = $this->factory->reply->create( array( 'post_parent' => 0 ) );
		wp_update_post( array( 'ID' => $post_id, 'post_title' => 'Updated reply' ) );
		clean_post_cache( $post_id );

		try {
			$this->assertEmpty( $GLOBALS['_wp_suspend_cache_invalidation'] );
		} finally {
			wp_suspend_cache_invalidation( false );
		}
	}

	/**
	 * A nested update must be visible immediately, including in status hooks.
	 */
	public function test_nested_post_update_cleans_its_cache() {
		$topic_id = $this->factory->topic->create();
		$reply_id = $this->factory->reply->create( array( 'post_parent' => 0 ) );
		get_post( $reply_id );
		$update_reply = function ( $new_status, $old_status, $post ) use ( $topic_id, $reply_id ) {
			if ( $topic_id === $post->ID ) {
				wp_update_post( array( 'ID' => $reply_id, 'post_title' => 'Nested update' ) );
			}
		};
		add_action( 'transition_post_status', $update_reply, 20, 3 );
		try {
			wp_update_post( array( 'ID' => $topic_id, 'post_title' => 'Outer update' ) );
			$this->assertSame( 'Nested update', get_post( $reply_id )->post_title );
			$this->assertEmpty( $GLOBALS['_wp_suspend_cache_invalidation'] );
		} finally {
			remove_action( 'transition_post_status', $update_reply, 20 );
			wp_suspend_cache_invalidation( false );
		}
	}

	/**
	 * bbPress must preserve suspension requested by the caller.
	 */
	public function test_post_update_preserves_existing_cache_suspension() {
		$post_id = $this->factory->reply->create( array( 'post_parent' => 0 ) );
		wp_update_post( array( 'ID' => $post_id, 'post_title' => 'First update' ) );
		wp_suspend_cache_invalidation( true );
		try {
			wp_update_post( array( 'ID' => $post_id, 'post_title' => 'Suspended update' ) );
			$this->assertTrue( $GLOBALS['_wp_suspend_cache_invalidation'] );
		} finally {
			wp_suspend_cache_invalidation( false );
		}
	}

	/**
	 * Updating a parent must not evict its descendants' post caches.
	 */
	public function test_parent_update_preserves_child_post_cache() {
		$forum_id = $this->factory->forum->create();
		$topic_id = $this->factory->topic->create( array( 'post_parent' => $forum_id ) );
		$reply_id = $this->factory->reply->create( array( 'post_parent' => $topic_id ) );
		get_post( $topic_id );
		get_post( $reply_id );
		wp_update_post( array( 'ID' => $forum_id, 'post_title' => 'Updated forum' ) );

		$this->assertNotFalse( wp_cache_get( $topic_id, 'posts' ) );
		$this->assertNotFalse( wp_cache_get( $reply_id, 'posts' ) );
	}

}
