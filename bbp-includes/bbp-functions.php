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

/**
 * bbp_number_format ( $number, $decimals optional )
 *
 * A bbPress specific method of formatting numeric values
 *
 * @package bbPress
 * @subpackage Functions
 * @since bbPress (r2485)
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
 * @since bbPress (r2455)
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
 * @since bbPress (r2454)
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
	 * @since bbPress (r2454)
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
	global $bbp;

	// Only proceed if POST is a new reply
	if ( 'POST' == $_SERVER['REQUEST_METHOD'] && !empty( $_POST['action'] ) && 'bbp-new-reply' === $_POST['action'] ) {

		// Nonce check
		check_admin_referer( 'bbp-new-reply' );

		// Handle Title
		if ( isset( $_POST['bbp_reply_title'] ) )
			$reply_title = esc_attr( strip_tags( $_POST['bbp_reply_title'] ) );

		// Handle Description
		if ( isset( $_POST['bbp_reply_content'] ) )
			$reply_content = current_user_can( 'unfiltered_html' ) ? $_POST['bbp_reply_content'] : wp_filter_post_kses( $_POST['bbp_reply_content'] );

		// Handle Topic ID to append reply to
		if ( isset( $_POST['bbp_topic_id'] ) )
			$topic_id = $_POST['bbp_topic_id'];

		// Handle Tags
		if ( isset( $_POST['bbp_topic_tags'] ) && !empty( $_POST['bbp_topic_tags'] ) ) {
			// Escape tag input
			$terms = esc_html( $_POST['bbp_topic_tags'] );

			// Explode by comma
			if ( strstr( $terms, ',' ) )
				$terms = explode( ',', $terms );

			// Add topic tag ID as main key
			$terms = array( $bbp->topic_tag_id => $terms );

			// @todo - Handle adding of tags from reply
		}

		// Handle insertion into posts table
		if ( !empty( $topic_id ) && !empty( $reply_title ) && !empty( $reply_content ) ) {

			// Add the content of the form to $post as an array
			$reply_data = array(
				'post_author'   => bbp_get_current_user_id(),
				'post_title'    => $reply_title,
				'post_content'  => $reply_content,
				'post_parent'   => $topic_id,
				'post_status'   => 'publish',
				'post_type'     => $bbp->reply_id
			);

			// Insert reply
			$reply_id = wp_insert_post( $reply_data );

			// Update counts, etc...
			do_action( 'bbp_new_reply', $reply_data );

			// Check for missing reply_id or error
			if ( !empty( $reply_id ) && !is_wp_error( $reply_id ) ) {

				// Redirect back to new reply
				wp_redirect( bbp_get_topic_permalink( $topic_id ) . '#reply-' . $reply_id );

				// For good measure
				exit();
			}
		}
	}
}
add_action( 'template_redirect', 'bbp_new_reply_handler' );

/**
 * bbp_new_topic_handler ()
 *
 * Handles the front end topic submission
 *
 * @todo security sweep
 */
function bbp_new_topic_handler () {
	global $bbp;

	// Only proceed if POST is a new topic
	if ( 'POST' == $_SERVER['REQUEST_METHOD'] && !empty( $_POST['action'] ) && 'bbp-new-topic' === $_POST['action'] ) {

		// Nonce check
		check_admin_referer( 'bbp-new-topic' );

		// Handle Title
		if ( isset( $_POST['bbp_topic_title'] ) )
			$topic_title = esc_attr( strip_tags( $_POST['bbp_topic_title'] ) );

		// Handle Description
		if ( isset( $_POST['bbp_topic_content'] ) )
			$topic_content = current_user_can( 'unfiltered_html' ) ? $_POST['bbp_topic_content'] : wp_filter_post_kses( $_POST['bbp_topic_content'] );

		// Handle Topic ID to append reply to
		if ( isset( $_POST['bbp_forum_id'] ) )
			$forum_id = $_POST['bbp_forum_id'];

		// Handle Tags
		if ( isset( $_POST['bbp_topic_tags'] ) && !empty( $_POST['bbp_topic_tags'] ) ) {
			// Escape tag input
			$terms = esc_html( $_POST['bbp_topic_tags'] );

			// Explode by comma
			if ( strstr( $terms, ',' ) )
				$terms = explode( ',', $terms );

			// Add topic tag ID as main key
			$terms = array( $bbp->topic_tag_id => $terms );

		// No tags
		} else {
			$terms = '';
		}

		// Handle insertion into posts table
		if ( !empty( $forum_id ) && !empty( $topic_title ) && !empty( $topic_content ) ) {

			// Add the content of the form to $post as an array
			$topic_data = array(
				'post_author'   => bbp_get_current_user_id(),
				'post_title'    => $topic_title,
				'post_content'  => $topic_content,
				'post_parent'   => $forum_id,
				'tax_input'     => $terms,
				'post_status'   => 'publish',
				'post_type'     => $bbp->topic_id
			);

			// Insert reply
			$topic_id = wp_insert_post( $topic_data );

			// Update counts, etc...
			do_action( 'bbp_new_topic', $topic_data );

			// Check for missing topic_id or error
			if ( !empty( $topic_id ) && !is_wp_error( $topic_id ) ) {

				// Redirect back to new reply
				wp_redirect( bbp_get_topic_permalink( $topic_id ) . '#topic-' . $topic_id );

				// For good measure
				exit();
			}
		}
	}
}
add_action( 'template_redirect', 'bbp_new_topic_handler' );

/**
 * bbp_get_stickies()
 *
 * Return sticky topics from forum
 *
 * @since bbPress (r2592)
 * @param int $forum_id
 * @return array Post ID's of sticky topics
 */
function bbp_get_stickies ( $forum_id = 0 ) {
	global $bbp;

	if ( empty( $forum_id ) ) {
		$stickies = get_option( 'bbp_sticky_topics' );
	} else {
		if ( $bbp->forum_id == get_post_type( $forum_id ) ) {
			$stickies = get_post_meta( $forum_id );
		} else {
			$stickies = null;
		}
	}

	return apply_filters( 'bbp_get_stickies', $stickies, (int)$forum_id );
}

/**
 * bbp_get_super_stickies ()
 *
 * Return topics stuck to front page of forums
 *
 * @since bbPress (r2592)
 * @return array Post ID's of super sticky topics
 */
function bbp_get_super_stickies () {
	$stickies = get_option( 'bbp_super_sticky_topics' );

	return apply_filters( 'bbp_get_super_stickies', $stickies );
}

?>
