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
		$rpt = bbp_get_topic_post_type();

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
		bbp_get_all_child_ids( $t, $rpt );

		// Setup
		$f_key        = md5( serialize( array( 'parent_id' => $f, 'post_type' => $tpt ) ) );
		$t_key        = md5( serialize( array( 'parent_id' => $t, 'post_type' => $rpt ) ) );
		$last_changed = wp_cache_get_last_changed( 'bbpress_posts' );
		
		// Keys
		$f_key = "bbp_child_ids:{$f_key}:{$last_changed}";
		$t_key = "bbp_child_ids:{$t_key}:{$last_changed}";

		$this->assertEquals( array( $t ), wp_cache_get( $f_key, 'bbpress_posts' ) );
		$this->assertEquals( array( $r ), wp_cache_get( $t_key, 'bbpress_posts' ) );

		// Clean the cache.
		bbp_clean_post_cache( $r );

		// Setup
		$last_changed = wp_cache_get_last_changed( 'bbpress_posts' );
		
		// Keys
		$f_key = "bbp_child_ids:{$f_key}:{$last_changed}";
		$t_key = "bbp_child_ids:{$t_key}:{$last_changed}";

		$this->assertEquals( false, wp_cache_get( $f_key, 'bbpress_posts' ) );
		$this->assertEquals( false, wp_cache_get( $t_key, 'bbpress_posts' ) );
	}
}
