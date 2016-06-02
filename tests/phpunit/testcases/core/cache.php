<?php
/**
 * Tests for the cache functions.
 *
 * @group cache
 */
class BBP_Core_Cache_Tests extends BBP_UnitTestCase {

	/**
	 * @covers ::bbp_clean_post_cache
	 */
	public function test_bbp_clean_post_cache() {

		// Get the topic post type.
		$tpt = bbp_get_topic_post_type();

		// Set up a forum with 1 topic and 1 reply to that topic.
		$f = $this->factory->forum->create();
		$t = $this->factory->topic->create( array(
			'post_parent' => $f,
			'topic_meta' => array(
				'forum_id' => $f,
			),
		) );
		$r = $this->factory->topic->create( array(
			'post_parent' => $t,
			'reply_meta' => array(
				'forum_id' => $f,
				'topic_id' => $t,
			),
		) );

		// Make sure we've cached some data.
		bbp_get_all_child_ids( $f, $tpt );
		bbp_get_all_child_ids( $t, $tpt );

		$this->assertEquals( array( $t ), wp_cache_get( "bbp_parent_all_{$f}_type_{$tpt}_child_ids", 'bbpress_posts' ) );
		$this->assertEquals( array( $r ), wp_cache_get( "bbp_parent_all_{$t}_type_{$tpt}_child_ids", 'bbpress_posts' ) );

		// Clean the cache.
		bbp_clean_post_cache( $r );

		$this->assertEquals( false, wp_cache_get( "bbp_parent_all_{$f}_type_{$tpt}_child_ids", 'bbpress_posts' ) );
		$this->assertEquals( false, wp_cache_get( "bbp_parent_all_{$t}_type_{$tpt}_child_ids", 'bbpress_posts' ) );
	}
}
