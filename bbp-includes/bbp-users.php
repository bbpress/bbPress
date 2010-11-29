<?php

/**
 * bbp_has_access()
 *
 * Make sure user can perform special tasks
 *
 * @package bbPress
 * @subpackage Functions
 * @since bbPress (r2464)
 *
 * @uses is_super_admin ()
 * @uses apply_filters
 *
 * @todo bbPress port of existing roles/caps
 * @return bool $has_access
 */
function bbp_has_access () {

	if ( is_super_admin () )
		$has_access = true;
	else
		$has_access = false;

	return apply_filters( 'bbp_has_access', $has_access );
}

/**
 * bbp_allow_anonymous ()
 *
 * Returns true|false if anonymous topic creation and replies are allowed
 *
 * @since bbPress (r2596)
 * @return bool
 */
function bbp_allow_anonymous () {
	return apply_filters( 'bbp_allow_anonymous', get_option( 'bbp_allow_anonymous', false ) );
}

/** START - Favorites *********************************************************/

/**
 * bbp_get_user_favorites_topic_ids ()
 *
 * Get a user's favorite topics' IDs
 *
 * @package bbPress
 * @subpackage Users
 * @since bbPress (r2652)
 *
 * @param int $user_id User ID
 * @return array|bool Results if user has favorites, otherwise false
 */
function bbp_get_user_favorites_topic_ids ( $user_id = 0 ) {
	if ( empty( $user_id ) )
		return;

	$favorites = (string) get_user_meta( $user_id, '_bbp_favorites', true );
	$favorites = (array) explode( ',', $favorites );
	$favorites = array_filter( $favorites );

	if ( !empty( $favorites ) )
		return $favorites;

	return false;
}

/**
 * bbp_get_user_favorites ()
 *
 * Get a user's favorite topics
 *
 * @package bbPress
 * @subpackage Users
 * @since bbPress (r2652)
 *
 * @todo Pagination
 *
 * @param int $user_id User ID
 * @return array|bool Results if user has favorites, otherwise false
 */
function bbp_get_user_favorites ( $user_id = 0 ) {
	// Default to author
	if ( empty( $user_id ) )
		$user_id = get_the_author_meta( 'ID' );

	// If nothing passed and not an author page, return nothing
	if ( empty( $user_id ) )
		return false;

	// Get users' favorites
	$favorites = bbp_get_user_favorites_topic_ids( $user_id );

	// If user has favorites, load them
	if ( !empty( $favorites ) ) {
		$query = bbp_has_topics( array( 'post__in' => $favorites, 'posts_per_page' => -1 ) );
		return $query;
	}

	return false;
}

/**
 * bbp_is_user_favorite ()
 *
 * Check if a topic is in user's favorites or not
 *
 * @package bbPress
 * @subpackage Users
 * @since bbPress (r2652)
 *
 * @param int $user_id User ID
 * @param int $topic_id Topic ID
 * @return bool True if the topic is in user's favorites, otherwise false
 */
function bbp_is_user_favorite ( $user_id = 0, $topic_id = 0 ) {
	global $post, $current_user;

	if ( empty( $user_id ) ) {
		$current_user = wp_get_current_user();
		$user_id      = $current_user->ID;
	}

	if ( empty( $user_id ) )
		return false;

	$favorites = bbp_get_user_favorites_topic_ids( $user_id );

	if ( !empty( $topic_id ) ) {
		$post = get_post( $topic_id );
		$topic_id = $post->ID;
	} elseif ( !$topic_id = bbp_get_topic_id() ) {
		if ( empty( $post ) )
			return false;

		$topic_id = $post->ID;
	}

	if ( empty( $favorites ) || empty( $topic_id ) )
		return false;

	if ( isset( $favorites ) )
		return in_array( $topic_id, $favorites );

	return false;
}

/**
 * bbp_add_user_favorite ()
 *
 * Add a topic to user's favorites
 *
 * @package bbPress
 * @subpackage Users
 * @since bbPress (r2652)
 *
 * @param int $user_id User ID
 * @param int $topic_id Topic ID
 * @return bool True
 */
function bbp_add_user_favorite ( $user_id = 0, $topic_id = 0 ) {
	if ( empty( $user_id ) || empty( $topic_id ) )
		return false;

	$favorites = (array) bbp_get_user_favorites_topic_ids( $user_id );
	$topic     = get_post( $topic_id );

	if ( empty( $favorites ) || empty( $topic ) )
		return false;

	if ( !in_array( $topic_id, $favorites ) ) {
		$favorites[] = $topic_id;
		$favorites   = array_filter( $favorites );
		$favorites   = (string) implode( ',', $favorites );
		update_user_meta( $user_id, '_bbp_favorites', $favorites );
	}

	do_action( 'bbp_add_user_favorite', $user_id, $topic_id );

	return true;
}

/**
 * bbp_remove_user_favorite ()
 *
 * Remove a topic from user's favorites
 *
 * @package bbPress
 * @subpackage Users
 * @since bbPress (r2652)
 *
 * @param int $user_id User ID
 * @param int $topic_id Topic ID
 * @return bool True if the topic was removed from user's favorites, otherwise false
 */
function bbp_remove_user_favorite ( $user_id, $topic_id ) {
	if ( empty( $user_id ) || empty( $topic_id ) )
		return false;

	if ( !$favorites = (array) bbp_get_user_favorites_topic_ids( $user_id ) )
		return false;

	if ( is_int( $pos = array_search( $topic_id, $favorites ) ) ) {
		array_splice( $favorites, $pos, 1 );
		$favorites = array_filter( $favorites );

		if ( !empty( $favorites ) ) {
			$favorites = implode( ',', $favorites );
			update_user_meta( $user_id, '_bbp_favorites', $favorites );
		} else {
			delete_user_meta( $user_id, '_bbp_favorites' );
		}
	}

	do_action( 'bbp_remove_user_favorite', $user_id, $topic_id );

	return true;
}

/** END - Favorites ***********************************************************/

/**
 * bbp_get_user_topics_started ()
 *
 * Get the topics that a user created
 *
 * @package bbPress
 * @subpackage Users
 * @since bbPress (r2652)
 *
 * @param int $user_id User ID
 * @return array|bool Results if user has favorites, otherwise false
 */
function bbp_get_user_topics_started ( $user_id = 0 ) {
	// Default to author
	if ( empty( $user_id ) )
		$user_id = get_the_author_meta( 'ID' );

	// If nothing passed and not an author page, return nothing
	if ( empty( $user_id ) )
		return false;

	if ( $query = bbp_has_topics( array( 'author' => $user_id, 'posts_per_page' => -1 ) ) )
		return $query;

	return false;
}

?>
