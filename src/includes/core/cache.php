<?php

/**
 * bbPress Cache Helpers.
 *
 * Helper functions used to communicate with WordPress's various caches. Many
 * of these functions are used to work around specific WordPress nuances. They
 * are subject to changes, tweaking, and will need iteration as performance
 * improvements are made to WordPress core.
 *
 * @package bbPress
 * @subpackage Cache
 */

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

/** Helpers *******************************************************************/

/**
 * Skip invalidation of child post content when editing a parent.
 *
 * This prevents invalidating caches for topics and replies when editing a forum
 * or a topic. Without this in place, WordPress will attempt to invalidate all
 * child posts whenever a parent post is modified. This can cause thousands of
 * cache invalidations to occur on a single edit, which is no good for anyone.
 *
 * @since 2.1.0 bbPress (r4011)
 *
 * @package bbPress
 * @subpackage Cache
 */
class BBP_Skip_Children {

	/**
	 * @var int Post ID being updated.
	 */
	private $updating_post = 0;

	/**
	 * @var bool The original value of $_wp_suspend_cache_invalidation global.
	 */
	private $original_cache_invalidation = false;

	/** Methods ***************************************************************/

	/**
	 * Hook into the 'pre_post_update' action.
	 *
	 * @since 2.1.0 bbPress (r4011)
	 */
	public function __construct() {
		add_action( 'pre_post_update', array( $this, 'pre_post_update' ) );
	}

	/**
	 * Only clean post caches for main bbPress posts.
	 *
	 * Check that the post being updated is a bbPress post type, saves the
	 * post ID to be used later, and adds an action to 'clean_post_cache' that
	 * prevents child post caches from being cleared.
	 *
	 * @since 2.1.0 bbPress (r4011)
	 *
	 * @param int $post_id The post ID being updated.
	 */
	public function pre_post_update( $post_id = 0 ) {

		// Bail if post ID is not a bbPress post type
		if ( empty( $post_id ) || ! bbp_is_custom_post_type( $post_id ) ) {
			return;
		}

		// Store the $post_id
		$this->updating_post = $post_id;

		// Skip related post cache invalidation. This prevents invalidating the
		// caches of the child posts when there is no reason to do so.
		add_action( 'clean_post_cache', array( $this, 'skip_related_posts' ) );
	}

	/**
	 * Suspend child post cache invalidation after the updated post is cleaned.
	 *
	 * The updated post triggers this callback first. Suspending invalidation here
	 * prevents WordPress from clearing its children during the same update.
	 *
	 * @since 2.1.0 bbPress (r4011)
	 *
	 * @global bool $_wp_suspend_cache_invalidation Whether cache invalidation is suspended.
	 *
	 * @param int $post_id The post ID of the cache being invalidated.
	 */
	public function skip_related_posts( $post_id = 0 ) {

		// Bail if this post is not the current bbPress post
		if ( empty( $post_id ) || ( $this->updating_post !== $post_id ) ) {
			return;
		}

		// Stash the current cache invalidation value in a variable, so we can
		// restore back to it nicely in the future.
		global $_wp_suspend_cache_invalidation;

		$this->original_cache_invalidation = $_wp_suspend_cache_invalidation;

		// Turn off cache invalidation
		wp_suspend_cache_invalidation( true );

		// Restore cache invalidation
		add_action( 'wp_insert_post', array( $this, 'restore_cache_invalidation' ) );
	}

	/**
	 * Restore the cache invalidation to its previous value.
	 *
	 * @since 2.1.0 bbPress (r4011)
	 */
	public function restore_cache_invalidation() {
		wp_suspend_cache_invalidation( $this->original_cache_invalidation );
	}
}
new BBP_Skip_Children();

/** General *******************************************************************/

/**
 * Invalidate parent post caches after a bbPress post cache is cleaned.
 *
 * Recurses through parent posts and updates the bbpress_posts last_changed
 * cache value when the forum root is reached. Runs on clean_post_cache.
 *
 * @since 2.1.0 bbPress (r4040)
 * @since 2.6.0 bbPress (r6053) Introduced the `$post_id` parameter.
 *
 * @param int     $post_id ID supplied by clean_post_cache. The post object supplies the ID used here.
 * @param WP_Post $post    Required post object supplied by clean_post_cache.
 */
function bbp_clean_post_cache( $post_id = null, $post = null ) {

	// Child query types to clean
	$post_types = array(
		bbp_get_forum_post_type(),
		bbp_get_topic_post_type(),
		bbp_get_reply_post_type()
	);

	// Bail if not a bbPress post type
	if ( ! in_array( $post->post_type, $post_types, true ) ) {
		return;
	}

	/**
	 * Fires immediately after the given post cache is cleaned.
	 *
	 * @since 2.1.0
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post object.
	 */
	do_action( 'bbp_clean_post_cache', $post->ID, $post );

	// Invalidate parent caches
	if ( ! empty( $post->post_parent ) ) {
		clean_post_cache( $post->post_parent );

	// Only bump `last_changed` when forum-root is reached
	} else {
		wp_cache_set( 'last_changed', microtime(), 'bbpress_posts' );
	}
}

/**
 * Invalidate cached forum-user counts across sites sharing the users table.
 *
 * @since 2.7.0
 */
function bbp_clean_user_count_cache() {
	wp_cache_set( 'bbp_forum_users_last_changed', microtime(), 'users' );
}

/**
 * Invalidate forum-user counts after capabilities metadata changes.
 *
 * @since 2.7.0
 *
 * @param int|array $meta_id  Metadata ID or IDs.
 * @param int       $user_id User ID.
 * @param string    $key     Metadata key.
 */
function bbp_clean_user_count_cache_on_meta_change( $meta_id, $user_id, $key ) {
	if ( preg_match( '/(^|_)capabilities$/', $key ) ) {
		bbp_clean_user_count_cache();
	}
}
