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
 * @since 2.6.0 bbPress (r6340)
 *
 * @param int $post_id ID of the post to check for editing
 * @return integer False: not locked or locked by current user. Int: user ID of user with lock.
 */
function bbp_check_post_lock( $post_id = 0 ) {

	// Bail if no post
	if ( !$post = get_post( $post_id ) ) {
		return false;
	}

	// Bail if no lock
	if ( !$lock = get_post_meta( $post->ID, '_edit_lock', true ) ) {
		return false;
	}

	// Get lock
	$lock = explode( ':', $lock );
	$time = $lock[0];
	$user = (int) isset( $lock[1] )
		? $lock[1]
		: get_post_meta( $post->ID, '_edit_last', true );

	// Filter editing window duration
	$time_window = apply_filters( 'bbp_check_post_lock_window', 3 * MINUTE_IN_SECONDS );

	// Return user who is or last edited
	if ( ! empty( $time ) && ( $time > ( time() - $time_window ) ) && ( $user !== bbp_get_current_user_id() ) ) {
		return (int) $user;
	}

	return false;
}

/**
 * Mark the post as currently being edited by the current user
 *
 * @since 2.6.0 bbPress (r6340)
 *
 * @param int $post_id ID of the post to being edited
 * @return bool|array Returns false if the post doesn't exist of there is no current user, or
 * 	an array of the lock time and the user ID.
 */
function bbp_set_post_lock( $post_id = 0 ) {

	// Bail if no post
	if ( !$post = get_post( $post_id ) ) {
		return false;
	}

	// Bail if no user
	if ( 0 == ( $user_id = get_current_user_id() ) ) {
		return false;
	}

	// Get time & lock value
	$now  = time();
	$lock = "{$now}:{$user_id}";

	// Set lock value
	update_post_meta( $post->ID, '_edit_lock', $lock );

	return array( $now, $user_id );
}
