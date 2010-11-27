<?php

/* Favorites */

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
function bbp_get_user_favorites_topic_ids ( $user_id ) {
	if ( !$user_id )
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
function bbp_get_user_favorites ( $user_id ) {
	$favorites = bbp_get_user_favorites_topic_ids( $user_id );

	if ( !empty( $favorites ) ) {
		$query = bbp_has_topics( array( 'post__in' => $favorites, 'per_page' => -1 ) );
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
	if ( !$user_id ) {
		global $current_user;
		wp_get_current_user();
		$user_id = $current_user->ID;
	}

	if ( !$user_id )
		return false;

	$favorites = bbp_get_user_favorites_topic_ids( $user_id );

	if ( $topic_id ) {
		$post = get_post( $topic_id );
		$topic_id = $post->ID;
	} elseif ( !$topic_id = bbp_get_topic_id() ) {
		global $post;
		if ( !$post )
			return false;

		$topic_id = $post->ID;
	}

	if ( !$favorites || !$topic_id )
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
function bbp_add_user_favorite ( $user_id, $topic_id ) {
	$user_id   = (int) $user_id;
	$topic_id  = (int) $topic_id;
	$favorites = (array) bbp_get_user_favorites_topic_ids( $user_id );
	$topic     = get_post( $topic_id );

	if ( !$favorites || !$topic )
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
	$user_id   = (int) $user_id;
	$topic_id  = (int) $topic_id;
	$favorites = (array) bbp_get_user_favorites_topic_ids( $user_id );

	if ( !$favorites || !$topic_id )
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