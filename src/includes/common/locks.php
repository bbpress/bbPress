<?php

/**
 * bbPress Locking
 *
 * @package bbPress
 * @subpackage Common
 */

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

/**
 * Check to see if the post is currently being edited by another user.
 *
 * @see wp_check_post_lock()
 *
 * @since 2.6.0 bbPress (r6341)
 *
 * @param int $post_id ID of the post to check for editing.
 * @return int|false User ID of the user with the lock, or false when not locked by another user.
 */
function bbp_check_post_lock( $post_id = 0 ) {

	// Bail if no post
	$post = get_post( $post_id );
	if ( empty( $post ) ) {
		return false;
	}

	// Bail if no lock
	$lock = get_post_meta( $post->ID, '_edit_lock', true );
	if ( empty( $lock ) ) {
		return false;
	}

	// Get lock
	$lock = explode( ':', $lock );
	$time = $lock[0];
	$user = isset( $lock[1] )
		? (int) $lock[1]
		: (int) get_post_meta( $post->ID, '_edit_last', true );

	// Filter editing window duration

	/**
	 * Filters the duration of the post edit lock in seconds.
	 *
	 * @since 2.6.0 bbPress (r6344)
	 *
	 * @param int $value The duration of the post edit lock in seconds.
	 */
	$time_window = apply_filters( 'bbp_check_post_lock_window', 3 * MINUTE_IN_SECONDS );

	// Return user who is or last edited
	if ( ! empty( $time ) && ( $time > ( time() - $time_window ) ) && ( bbp_get_current_user_id() ) !== $user ) {
		return (int) $user;
	}

	return false;
}

/**
 * Mark the post as currently being edited by the current user.
 *
 * @since 2.6.0 bbPress (r6341)
 *
 * @param int $post_id Post ID to lock.
 * @return array{int, int}|false Lock time and user ID, or false if the post or current user is unavailable.
 */
function bbp_set_post_lock( $post_id = 0 ) {

	// Bail if no post
	$post = get_post( $post_id );
	if ( empty( $post ) ) {
		return false;
	}

	// Bail if no user
	$user_id = get_current_user_id();
	if ( empty( $user_id ) ) {
		return false;
	}

	// Get time & lock value
	$now  = time();
	$lock = "{$now}:{$user_id}";

	// Set lock value
	update_post_meta( $post->ID, '_edit_lock', $lock );

	return array( $now, $user_id );
}
