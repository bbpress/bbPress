<?php

/**
 * bbPress Forum Functions
 *
 * @package bbPress
 * @subpackage Functions
 */

/** Walk **********************************************************************/

/**
 * Walk the forum tree
 *
 * @param object $forums Forums
 * @param int $depth Depth
 * @param int $current Current forum
 * @param array $r Parsed arguments, supported by the walker. If you want to
 *                  use your own walker, pass the 'walker' arg with the walker.
 *                  The walker defaults to {@link BBP_Walker_Forum}
 * @return object Walked forum tree
 */
function bbp_walk_forum( $forums, $depth, $current, $r ) {
	$walker = empty( $r['walker'] ) ? new BBP_Walker_Forum : $r['walker'];
	$args   = array( $forums, $depth, $r, $current );
	return call_user_func_array( array( &$walker, 'walk' ), $args );
}

/** Forum Handlers ************************************************************/

/**
 * Handles new forum craetion from within wp-admin
 *
 * @since bbPress (r2613)
 *
 * @param int $post_id
 * @param obj $post
 *
 * @uses bbp_get_forum_post_type()
 * @uses bbp_update_forum()
 */
function bbp_new_forum_admin_handler( $post_id, $post ) {
	global $bbp;

	if (    // Check if POST action
			'POST'                        === $_SERVER['REQUEST_METHOD'] &&

			// Check Actions exist in POST
			!empty( $_POST['action']    )                                &&
			!empty( $_POST['post_type'] )                                &&

			// Check that actions match what we need
			'editpost'                    === $_POST['action']           &&
			bbp_get_forum_post_type()     === $_POST['post_type']
	) {

		// Update the forum meta bidness
		bbp_update_forum( array( 'forum_id' => $post_id, 'post_parent' => (int) $_POST['parent_id'] ) );
	}
}


/** Forum Actions *************************************************************/

/**
 * Closes a forum
 *
 * @since bbPress (r2746)
 *
 * @param int $forum_id forum id
 * @uses wp_get_single_post() To get the forum
 * @uses do_action() Calls 'bbp_close_forum' with the forum id
 * @uses add_post_meta() To add the previous status to a meta
 * @uses wp_insert_post() To update the forum with the new status
 * @uses do_action() Calls 'bbp_opened_forum' with the forum id
 * @return mixed False or {@link WP_Error} on failure, forum id on success
 */
function bbp_close_forum( $forum_id = 0 ) {
	global $bbp;

	if ( !$forum = wp_get_single_post( $forum_id, ARRAY_A ) )
		return $forum;

	do_action( 'bbp_close_forum', $forum_id );

	update_post_meta( $forum_id, '_bbp_status', 'closed' );

	do_action( 'bbp_closed_forum', $forum_id );

	return $forum_id;
}

/**
 * Opens a forum
 *
 * @since bbPress (r2746)
 *
 * @param int $forum_id forum id
 * @uses wp_get_single_post() To get the forum
 * @uses do_action() Calls 'bbp_open_forum' with the forum id
 * @uses get_post_meta() To get the previous status
 * @uses delete_post_meta() To delete the previous status meta
 * @uses wp_insert_post() To update the forum with the new status
 * @uses do_action() Calls 'bbp_opened_forum' with the forum id
 * @return mixed False or {@link WP_Error} on failure, forum id on success
 */
function bbp_open_forum( $forum_id = 0 ) {
	global $bbp;

	if ( !$forum = wp_get_single_post( $forum_id, ARRAY_A ) )
		return $forum;

	do_action( 'bbp_open_forum', $forum_id );

	update_post_meta( $forum_id, '_bbp_status', 'open' );

	do_action( 'bbp_opened_forum', $forum_id );

	return $forum_id;
}

/**
 * Make the forum a category
 *
 * @since bbPress (r2746)
 *
 * @param int $forum_id Optional. Forum id
 * @uses update_post_meta() To update the forum category meta
 * @return bool False on failure, true on success
 */
function bbp_categorize_forum( $forum_id = 0 ) {
	return update_post_meta( $forum_id, '_bbp_forum_type', 'category' );
}

/**
 * Remove the category status from a forum
 *
 * @since bbPress (r2746)
 *
 * @param int $forum_id Optional. Forum id
 * @uses delete_post_meta() To delete the forum category meta
 * @return bool False on failure, true on success
 */
function bbp_normalize_forum( $forum_id = 0 ) {
	return update_post_meta( $forum_id, '_bbp_forum_type', 'forum' );
}

/**
 * Mark the forum as private
 *
 * @since bbPress (r2746)
 *
 * @param int $forum_id Optional. Forum id
 * @uses update_post_meta() To update the forum private meta
 * @return bool False on failure, true on success
 */
function bbp_privatize_forum( $forum_id = 0 ) {
	return update_post_meta( $forum_id, '_bbp_visibility', 'private' );
}

/**
 * Unmark the forum as private
 *
 * @since bbPress (r2746)
 *
 * @param int $forum_id Optional. Forum id
 * @uses delete_post_meta() To delete the forum private meta
 * @return bool False on failure, true on success
 */
function bbp_publicize_forum( $forum_id = 0 ) {
	return update_post_meta( $forum_id, '_bbp_visibility', 'public' );
}

/** Forum Updaters ************************************************************/

/**
 * Update the forum last topic id
 *
 * @since bbPress (r2625)
 *
 * @param int $forum_id Optional. Forum id
 * @param int $topic_id Optional. Topic id
 * @uses bbp_get_forum_id() To get the forum id
 * @uses bbp_get_topic_id() To get the topic id
 * @uses update_post_meta() To update the forum's last topic id meta
 * @return bool True on success, false on failure
 */
function bbp_update_forum_last_topic_id( $forum_id = 0, $topic_id = 0 ) {
	$forum_id = bbp_get_forum_id( $forum_id );
	
	// Do some calculating if not manually set
	if ( empty( $topic_id ) ) {

		// Loop through children and add together forum reply counts
		if ( $children = bbp_forum_query_subforum_ids( $forum_id ) )
			foreach ( (array) $children as $child )
				$children_last_topic = bbp_update_forum_last_topic_id ( $child );

		// Get the most recent topic in this forum_id
		if ( $recent_topic = get_posts( array( 'post_parent' => $forum_id, 'post_type' => bbp_get_topic_post_type(), 'meta_key' => '_bbp_last_active_time', 'orderby' => 'meta_value', 'numberposts' => 1 ) ) )
			$topic_id = $recent_topic[0]->ID;
	}

	// If child forums have higher ID, use that instead
	if ( !empty( $children ) && ( $children_last_topic > $topic_id ) )
		$topic_id = $children_last_topic;

	// Update the last topic ID
	update_post_meta( $forum_id, '_bbp_last_topic_id', (int) $topic_id );

	return apply_filters( 'bbp_update_forum_last_topic_id', (int) $topic_id, $forum_id );
}

/**
 * Update the forum last reply id
 *
 * @since bbPress (r2625)
 *
 * @param int $forum_id Optional. Forum id
 * @param int $reply_id Optional. Reply id
 * @uses bbp_get_forum_id() To get the forum id
 * @uses update_post_meta() To update the forum's last reply id meta
 * @return bool True on success, false on failure
 */
function bbp_update_forum_last_reply_id( $forum_id = 0, $reply_id = 0 ) {
	$forum_id = bbp_get_forum_id( $forum_id );

	// Do some calculating if not manually set
	if ( empty( $reply_id ) ) {

		// Loop through children and get the most recent reply id
		if ( $children = bbp_forum_query_subforum_ids( $forum_id ) )
			foreach ( (array) $children as $child )
				$children_last_reply = bbp_update_forum_last_reply_id ( $child );

		// If this forum has topics...
		if ( $topic_ids = bbp_forum_query_topic_ids( $forum_id ) ) {

			// ...get the most recent reply from those topics...
			$reply_id = bbp_forum_query_last_reply_id( $forum_id, $topic_ids );

			// ...and compare it to the most recent topic id...
			$reply_id = ( $reply_id > max( $topic_ids ) ) ? $reply_id : max( $topic_ids );
		}
	}

	// If child forums have higher ID, check for newer reply id
	if ( !empty( $children ) && ( (int) $children_last_reply > (int) $reply_id ) )
		$reply_id = $children_last_reply;

	// Update the last reply ID with what was passed
	update_post_meta( $forum_id, '_bbp_last_reply_id', (int) $reply_id );

	return apply_filters( 'bbp_update_forum_last_reply_id', (int) $reply_id, $forum_id );
}

/**
 * Update the forum last active post ID
 *
 * @since bbPress (r2860)
 *
 * @param int $forum_id Optional. Forum id
 * @param int $active_id Optional. active id
 * @uses bbp_get_forum_id() To get the forum id
 * @uses bbp_get_active_id() To get the active id
 * @uses update_post_meta() To update the forum's last active id meta
 * @return bool True on success, false on failure
 */
function bbp_update_forum_last_active_id( $forum_id = 0, $active_id = 0 ) {
	$forum_id  = bbp_get_forum_id( $forum_id );

	// Do some calculating if not manually set
	if ( empty( $active_id ) ) {

		// Loop through children and add together forum reply counts
		if ( $children = bbp_forum_query_subforum_ids( $forum_id ) )
			foreach ( (array) $children as $child )
				$children_last_reply = bbp_update_forum_last_active_id ( $child, $active_id );

		// Don't count replies if the forum is a category
		if ( $topic_ids = bbp_forum_query_topic_ids( $forum_id ) ) {
			$active_id  = bbp_forum_query_last_reply_id( $forum_id, $topic_ids );
			$active_id = $active_id > max( $topic_ids ) ? $active_id : max( $topic_ids );

		// Forum has no topics
		} else {
			$active_id = 0;
		}
	}

	// If child forums have higher ID, use that instead
	if ( !empty( $children ) && ( $children_last_reply > $active_id ) )
		$active_id = $children_last_reply;

	update_post_meta( $forum_id, '_bbp_last_active_id', (int) $active_id );

	return apply_filters( 'bbp_update_forum_last_active_id', (int) $active_id, $forum_id );
}

/**
 * Update the forums last active date/time (aka freshness)
 *
 * @since bbPress (r2680)
 *
 * @param int $forum_id Optional. Topic id
 * @param string $new_time Optional. New time in mysql format
 * @uses bbp_get_forum_id() To get the forum id
 * @uses bbp_get_reply_forum_id() To get the reply forum id
 * @uses current_time() To get the current time
 * @uses update_post_meta() To update the forum last active meta
 * @return bool True on success, false on failure
 */
function bbp_update_forum_last_active_time( $forum_id = 0, $new_time = '' ) {
	$forum_id = bbp_get_forum_id( $forum_id );

	// Check time and use current if empty
	if ( empty( $new_time ) )
		$new_time = get_post_field( 'post_date', bbp_get_forum_last_active_id ( $forum_id ) );

	update_post_meta( $forum_id, '_bbp_last_active_time', $new_time );

	return apply_filters( 'bbp_update_forum_last_active', $new_time, $forum_id );
}

/**
 * Update the forum sub-forum count
 *
 * @since bbPress (r2625)
 *
 * @param int $forum_id Optional. Forum id
 * @uses bbp_get_forum_id() To get the forum id
 * @return bool True on success, false on failure
 */
function bbp_update_forum_subforum_count( $forum_id = 0, $subforums = 0 ) {
	$forum_id = bbp_get_forum_id( $forum_id );

	if ( empty( $subforums ) )
		$subforums = count( bbp_forum_query_subforum_ids( $forum_id ) );

	update_post_meta( $forum_id, '_bbp_forum_subforum_count', (int) $subforums );

	return apply_filters( 'bbp_update_forum_subforum_count', (int) $subforums, $forum_id );
}

/**
 * Adjust the total topic count of a forum
 *
 * @since bbPress (r2464)
 *
 * @param int $forum_id Optional. Forum id or topic id. It is checked whether it
 *                       is a topic or a forum. If it's a topic, its parent,
 *                       i.e. the forum is automatically retrieved.
 * @param bool $total_count Optional. To return the total count or normal
 *                           count?
 * @uses get_post_field() To check whether the supplied id is a topic
 * @uses bbp_get_topic_forum_id() To get the topic's forum id
 * @uses wpdb::prepare() To prepare the sql statement
 * @uses wpdb::get_col() To execute the query and get the column back
 * @uses bbp_get_topic_status() To get the topic status
 * @uses update_post_meta() To update the forum's topic count meta
 * @uses apply_filters() Calls 'bbp_update_forum_topic_count' with the topic
 *                        count, forum id and total count bool
 */
function bbp_update_forum_topic_count( $forum_id = 0 ) {
	$forum_id = bbp_get_forum_id( $forum_id );
	$children_topic_count = 0;

	// Loop through subforums and add together forum topic counts
	if ( $children = bbp_forum_query_subforum_ids( $forum_id ) )
		foreach ( (array) $children as $child )
			$children_topic_count += bbp_update_forum_topic_count ( $child );

	// Get total topics for this forum
	$topics = (int) count( bbp_forum_query_topic_ids( $forum_id ) );

	// Calculate total topics in this forum
	$total_topics = $topics + $children_topic_count;

	// Update the count
	update_post_meta( $forum_id, '_bbp_forum_topic_count',       (int) $topics       );
	update_post_meta( $forum_id, '_bbp_total_topic_count', (int) $total_topics );

	return apply_filters( 'bbp_update_forum_topic_count', (int) $total_topics, $forum_id );
}

/**
 * Adjust the total hidden topic count of a forum (hidden includes trashed and spammed topics)
 *
 * @since bbPress (r2888)
 *
 * @param int $forum_id Optional. Topic id to update
 * @param int $topic_count Optional. Set the topic count manually
 * @uses bbp_get_forum_id() To get the forum id
 * @uses get_post_field() To get the post type of the supplied id
 * @uses bbp_get_topic_forum_id() To get the topic forum id
 * @uses wpdb::prepare() To prepare our sql query
 * @uses wpdb::get_col() To execute our query and get the column back
 * @uses update_post_meta() To update the forum hidden topic count meta
 * @uses apply_filters() Calls 'bbp_update_forum_hidden_topic_count' with the
 *                        hidden topic count and forum id
 * @return int Topic hidden topic count
 */
function bbp_update_forum_hidden_topic_count( $forum_id = 0, $topic_count = 0 ) {
	global $wpdb, $bbp;

	// If topic_id was passed as $forum_id, then get its forum
	if ( bbp_is_topic( $forum_id ) ) {
		$topic_id = bbp_get_topic_id( $forum_id );
		$forum_id = bbp_get_topic_forum_id( $topic_id );

	// $forum_id is not a topic_id, so validate and proceed
	} else {
		$forum_id = bbp_get_forum_id( $forum_id );
	}

	// Can't update what isn't there
	if ( !empty( $forum_id ) ) {

		// Get topics of forum
		if ( empty( $topic_count ) )
			$topic_count = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(ID) FROM {$wpdb->posts} WHERE post_parent = %d AND post_status IN ( '" . join( '\',\'', array( $bbp->trash_status_id, $bbp->spam_status_id ) ) . "') AND post_type = '%s';", $forum_id, bbp_get_topic_post_type() ) );

		// Update the count
		update_post_meta( $forum_id, '_bbp_topic_count_hidden', (int) $topic_count );
	}

	return apply_filters( 'bbp_update_forum_hidden_topic_count', (int) $topic_count, $forum_id );
}

/**
 * Adjust the total topic count of a forum
 *
 * @since bbPress (r2464)
 *
 * @param int $forum_id Optional. Forum id or topic id. It is checked whether it
 *                       is a topic or a forum. If it's a topic, its parent,
 *                       i.e. the forum is automatically retrieved.
 * @param bool $total_count Optional. To return the total count or normal
 *                           count?
 * @uses get_post_field() To check whether the supplied id is a topic
 * @uses bbp_get_topic_forum_id() To get the topic's forum id
 * @uses wpdb::prepare() To prepare the sql statement
 * @uses wpdb::get_col() To execute the query and get the column back
 * @uses bbp_get_topic_status() To get the topic status
 * @uses update_post_meta() To update the forum's topic count meta
 * @uses apply_filters() Calls 'bbp_update_forum_topic_count' with the topic
 *                        count, forum id and total count bool
 */
function bbp_update_forum_reply_count( $forum_id = 0 ) {
	global $wpdb, $bbp;

	$forum_id = bbp_get_forum_id( $forum_id );
	$children_reply_count = 0;

	// Loop through children and add together forum reply counts
	if ( $children = bbp_forum_query_subforum_ids( $forum_id ) )
		foreach ( (array) $children as $child )
			$children_reply_count += bbp_update_forum_reply_count ( $child );

	// Don't count replies if the forum is a category
	if ( $topic_ids = bbp_forum_query_topic_ids( $forum_id ) )
		$reply_count = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(ID) FROM {$wpdb->posts} WHERE post_parent IN ( " . join( ',', $topic_ids ) . " ) AND post_status = 'publish' AND post_type = '%s';", bbp_get_reply_post_type() ) );
	else
		$reply_count = 0;

	// Calculate total replies in this forum
	$total_replies = (int) $reply_count + $children_reply_count;

	// Update the count
	update_post_meta( $forum_id, '_bbp_reply_count',       $reply_count   );
	update_post_meta( $forum_id, '_bbp_total_reply_count', $total_replies );

	return apply_filters( 'bbp_update_forum_reply_count', $total_replies );
}

/**
 * Updates the counts of a forum. This calls a few internal functions that all run
 * manual queries against the database to get their results. As such, this
 * function can be costly to run but is necessary to keep everything accurate.
 *
 * @since bbPress (r2908)
 * @param int $args
 *
 * @uses bbp_update_forum_last_topic_id()
 * @uses bbp_update_forum_last_reply_id()
 * @uses bbp_update_forum_last_active_id()
 * @uses bbp_update_forum_last_active_time()
 * @uses bbp_update_forum_topic_count()
 */
function bbp_update_forum( $args = '' ) {
	$defaults = array(
		'forum_id'         => 0,
		'post_parent'      => 0,
		'last_topic_id'    => 0,
		'last_reply_id'    => 0,
		'last_active_id'   => 0,
		'last_active_time' => 0,
	);

	$r = wp_parse_args( $args, $defaults );
	extract( $r );

	// Last topic and reply ID's
	bbp_update_forum_last_topic_id( $forum_id, $last_topic_id );
	bbp_update_forum_last_reply_id( $forum_id, $last_reply_id );

	// Active dance
	$last_active_id = bbp_update_forum_last_active_id( $forum_id, $last_active_id );

	// If no active time was passed, get it from the last_active_id
	if ( empty( $last_active_time ) )
		$last_active_time = get_post_field( 'post_date', $last_active_id );

	bbp_update_forum_last_active_time( $forum_id, $last_active_time );

	// Counts
	bbp_update_forum_subforum_count    ( $forum_id );
	bbp_update_forum_reply_count       ( $forum_id );
	bbp_update_forum_topic_count       ( $forum_id );
	bbp_update_forum_hidden_topic_count( $forum_id );

	// Update the parent forum if one was passed
	if ( !empty( $post_parent ) && is_numeric( $post_parent ) )
		bbp_update_forum( array(
			'forum_id'    => $post_parent,
			'post_parent' => get_post_field( 'post_parent', $post_parent )
		) );
}

/** Queries *******************************************************************/

function bbp_forum_query_topic_ids( $forum_id ) {
	global $bbp, $wpdb;

	$topic_ids = bbp_get_public_child_ids( $forum_id, bbp_get_topic_post_type() );

	return apply_filters( 'bbp_get_forum_topic_ids', $topic_ids, $forum_id );
}

function bbp_forum_query_subforum_ids( $forum_id ) {
	global $bbp, $wpdb;

	$subforum_ids = bbp_get_public_child_ids( $forum_id, bbp_get_forum_post_type() );

	return apply_filters( 'bbp_get_forum_subforum_ids', $subforum_ids, $forum_id );
}

function bbp_forum_query_last_reply_id( $forum_id, $topic_ids = 0 ) {
	global $bbp, $wpdb;

	$cache_id = 'bbp_get_forum_' . $forum_id . '_reply_id';

	if ( !$reply_id = (int) wp_cache_get( $cache_id, 'bbpress' ) ) {

		if ( empty( $topic_ids ) )
			$topic_ids = bbp_get_forum_topic_ids( $forum_id );

		if ( $reply_id = (int) $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_parent IN ( " . join( ',', $topic_ids ) . " ) AND post_status = 'publish' AND post_type = '%s' ORDER BY ID DESC LIMIT 1;", bbp_get_reply_post_type() ) ) )
			wp_cache_set( $cache_id, $reply_id, 'bbpress' );
		else
			wp_cache_set( $cache_id, '0', 'bbpress' );
	}

	return apply_filters( 'bbp_get_forum_last_reply_id', (int) $reply_id, $forum_id );
}

?>
