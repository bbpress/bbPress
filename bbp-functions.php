<?php

/**
 * bbp_has_access()
 *
 * Make sure user can perform special tasks
 *
 * @package bbPress
 * @subpackage Functions
 * @since bbPress (1.2-r2464)
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
 * bbp_number_format ( $number, $decimals optional )
 *
 * A bbPress specific method of formatting numeric values
 *
 * @package bbPress
 * @subpackage Functions
 * @since bbPress (1.2-r2485)
 *
 * @param string $number Number to format
 * @param string $decimals optional Display decimals
 * @return string Formatted string
 */
function bbp_number_format ( $number, $decimals = false ) {
	// If empty, set $number to '0'
	if ( empty( $number ) || !is_numeric( $number ) )
		$number = '0';

	return apply_filters( 'bbp_number_format', number_format( $number, $decimals ), $number, $decimals );
}

/**
 * bbp_get_modified_time( $post, $d, $gmt, $translate )
 *
 * Retrieve the time at which the post was last modified.
 *
 * @package bbPress
 * @subpackage Functions
 * @since bbPress (1.2-r2455)
 *
 * @param int|object $post Optional, default is global post object. A post_id or post object
 * @param string $d Optional, default is 'U'. Either 'G', 'U', or php date format.
 * @param bool $gmt Optional, default is false. Whether to return the gmt time.
 * @param bool $translate Optional, default is false. Whether to translate the result
 *
 * @return string Returns timestamp
 */
function bbp_get_modified_time( $post = null, $d = 'U', $gmt = false, $translate = false ) {
	$post = get_post($post);

	if ( $gmt )
		$time = $post->post_modified_gmt;
	else
		$time = $post->post_modified;

	$time = mysql2date( $d, $time, $translate );

	return apply_filters( 'bbp_get_post_modified_time', $time, $d, $gmt );
}

/**
 * bbp_time_since( $time )
 *
 * Output formatted time to display human readable time difference.
 *
 * @package bbPress
 * @subpackage Functions
 * @since bbPress (1.2-r2454)
 *
 * @param $time
 */
function bbp_time_since( $time ) {
	echo bbp_get_time_since( $time );
}
	/**
	 * bbp_get_time_since( $time )
	 *
	 * Return formatted time to display human readable time difference.
	 *
	 * @package bbPress
	 * @subpackage Functions
	 * @since bbPress (1.2-r2454)
	 *
	 * @param $time
	 */
	function bbp_get_time_since ( $time ) {
		return apply_filters( 'bbp_get_time_since', human_time_diff( $time, current_time( 'timestamp' ) ) );
	}


/**
 * bbp_new_reply_handler ()
 *
 * Handles the front end reply submission
 *
 * @todo security sweep
 */
function bbp_new_reply_handler () {
	if ( 'POST' == $_SERVER['REQUEST_METHOD'] && !empty( $_POST['action'] ) ) {

		// Handle Title
		if ( isset( $_POST['bbp_reply_title'] ) )
			$reply_title = $_POST['bbp_reply_title'];

		// Handle Description
		if ( isset( $_POST['bbp_reply_description'] ) )
			$reply_content = $_POST['bbp_reply_description'];

		// Handle Topic ID to append reply to
		if ( isset( $_POST['bbp_topic_id'] ) )
			$topic_id = $_POST['bbp_topic_id'];

		// Handle Tags
		if ( isset( $_POST['bbp_topic_tags'] ) )
			$tags = $_POST['bbp_topic_tags'];

		// Handle insertion into posts table
		if ( !empty( $topic_id ) && !empty( $reply_title ) && !empty( $reply_content ) ) {

			// Add the content of the form to $post as an array
			$reply_data = array(
				'post_title'    => $reply_title,
				'post_content'  => $reply_content,
				'post_parent'   => $topic_id,
				//'tags_input'    => $tags,
				'post_status'   => 'publish',
				'post_type'     => BBP_REPLY_POST_TYPE_ID
			);

			// Insert reply
			$reply_id = wp_insert_post( $reply_data );

			// Update counts, etc...
			do_action( 'bbp_new_reply', $reply_data );

			// Redirect back to new reply
			wp_redirect( bbp_get_topic_permalink( $topic_id ) . '#reply-' . $reply_id );

			// For good measure
			exit();
		}
	}
}
add_action( 'init', 'bbp_new_reply_handler' );

/**
 * bbp_new_topic_handler ()
 *
 * Handles the front end topic submission
 *
 * @todo security sweep
 */
function bbp_new_topic_handler () {
	if ( 'POST' == $_SERVER['REQUEST_METHOD'] && !empty( $_POST['action'] ) ) {

		// Handle Title
		if ( isset( $_POST['bbp_topic_title'] ) )
			$topic_title = $_POST['bbp_topic_title'];

		// Handle Description
		if ( isset( $_POST['bbp_topic_description'] ) )
			$topic_content = $_POST['bbp_topic_description'];

		// Handle Topic ID to append reply to
		if ( isset( $_POST['bbp_forum_id'] ) )
			$forum_id = $_POST['bbp_forum_id'];

		// Handle Tags
		if ( isset( $_POST['bbp_topic_tags'] ) )
			$tags = $_POST['bbp_topic_tags'];

		// Handle insertion into posts table
		if ( !empty( $forum_id ) && !empty( $topic_title ) && !empty( $topic_content ) ) {

			// Add the content of the form to $post as an array
			$topic_data = array(
				'post_title'    => $topic_title,
				'post_content'  => $topic_content,
				'post_parent'   => $forum_id,
				//'tags_input'    => $tags,
				'post_status'   => 'publish',
				'post_type'     => BBP_TOPIC_POST_TYPE_ID
			);

			// Insert reply
			$topic_id = wp_insert_post( $topic_data );

			// Update counts, etc...
			do_action( 'bbp_new_topic', $topic_data );

			// Redirect back to new reply
			wp_redirect( bbp_get_topic_permalink( $topic_id ) . '#topic-' . $topic_id );

			// For good measure
			exit();
		}
	}
}
add_action( 'init', 'bbp_new_topic_handler' );

?>
