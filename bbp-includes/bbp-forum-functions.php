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

	update_post_meta( $forum_id, '_bbp_forum_status', 'closed' );

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

	update_post_meta( $forum_id, '_bbp_forum_status', 'open' );

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
	return update_post_meta( $forum_id, '_bbp_forum_visibility', 'private' );
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
	return update_post_meta( $forum_id, '_bbp_forum_visibility', 'public' );
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
	$topic_id = bbp_get_topic_id( $topic_id );

	// Update the last topic ID
	if ( !empty( $topic_id ) )
		return update_post_meta( $forum_id, '_bbp_forum_last_topic_id', $topic_id );

	return false;
}

/**
 * Update the forum last reply id
 *
 * @since bbPress (r2625)
 *
 * @param int $forum_id Optional. Forum id
 * @param int $reply_id Optional. Reply id
 * @uses bbp_get_forum_id() To get the forum id
 * @uses bbp_get_reply_id() To get the reply id
 * @uses update_post_meta() To update the forum's last reply id meta
 * @return bool True on success, false on failure
 */
function bbp_update_forum_last_reply_id( $forum_id = 0, $reply_id = 0 ) {
	$forum_id = bbp_get_forum_id( $forum_id );
	$reply_id = bbp_get_reply_id( $reply_id );

	// Update the last reply ID
	if ( !empty( $reply_id ) )
		return update_post_meta( $forum_id, '_bbp_forum_last_reply_id', $reply_id );

	return false;
}

/**
 * Update the forums last active date/time (aka freshness)
 *
 * @since bbPress (r2680)
 *
 * @param int $forum_id Optional. Forum id
 * @param string $new_time Optional. New time in mysql format
 * @uses bbp_forum_has_subforums() Get sub forums
 * @uses bbp_get_topic_forum_id() Get forum_id from possible topic_id
 * @uses get_posts() Get topics from the forum_id
 * @uses bbp_get_forum_id() Get the forum id
 * @uses current_time() Get the current time
 * @uses get_post_meta() Get the last active times of topics and forums
 * @uses update_post_meta() Update the forum's last active meta
 * @uses delete_post_meta() Delete last active meta if no topics exist
 * @return bool True on success, false on failure
 */
function bbp_update_forum_last_active( $forum_id = 0, $new_time = '' ) {
	global $wpdb, $bbp;

	$forum_id = bbp_get_forum_id( $forum_id );
	$sub_forum_time = $topic_time = $calculated_time = '';

	// If it's a topic, then get the parent (forum id)
	if ( $bbp->topic_id == get_post_field( 'post_type', $forum_id ) ) {
		$topic_id = $forum_id;
		$forum_id = bbp_get_topic_forum_id( $forum_id );
	}

	// No time was passed, so we need to do some calculating
	if ( empty( $new_time ) ) {

		// If forum has sub forums, loop through them and get the last active time
		if ( $sub_forums = bbp_forum_has_subforums( $forum_id ) ) {

			// Loop through sub forums
			foreach( $sub_forums as $sub_forum ) {

				// Get the sub forum last active time
				$sub_forum_temp_time = get_post_meta( $sub_forum->ID, '_bbp_forum_last_active', true );

				// Compare this sub forum time to the most recent, and assign to
				// $sub_forum_time if it's more recent than the last
				if ( strtotime( $sub_forum_temp_time ) > strtotime( $sub_forum_time ) ) {
					$sub_forum_time = $sub_forum_temp_time;
				}
			}
		}

		// Load the most recent topic in this forum_id based on
		// the '_bbp_topic_last_active' post_meta value
		if ( $topics = get_posts( array( 'numberposts' => 1, 'post_parent' => $forum_id, 'post_type' => $bbp->topic_id, 'meta_key' => '_bbp_topic_last_active', 'orderby' => 'meta_value' ) ) )
			$topic_time = get_post_meta( $topics[0]->ID, '_bbp_topic_last_active', true );

		// Calculate a new time
		if ( strtotime( $topic_time ) > strtotime( $sub_forum_time ) )
			$calculated_time = $topic_time;
		else
			$calculated_time = $sub_forum_time;

	// Specific time was passed, so skip calculations
	} else {
		$calculated_time = $new_time;
	}

	// No forums or topics in this forum_id, so delete the meta entries
	if ( empty( $calculated_time ) ) {
		delete_post_meta( $forum_id, '_bbp_forum_last_active'   );
		delete_post_meta( $forum_id, '_bbp_forum_last_topic_id' );

	// Update the forum last active time
	} else
		update_post_meta( $forum_id, '_bbp_forum_last_active', $calculated_time );

	// Walk up ancestors
	if ( $parent_id = bbp_get_forum_parent( $forum_id ) )
		bbp_update_forum_last_active( $parent_id );

	return apply_filters( 'bbp_update_forum_last_active', $calculated_time, $forum_id );
}

/**
 * Update the forum sub-forum count
 *
 * @todo Make this work.
 *
 * @since bbPress (r2625)
 *
 * @param int $forum_id Optional. Forum id
 * @uses bbp_get_forum_id() To get the forum id
 * @return bool True on success, false on failure
 */
function bbp_update_forum_subforum_count( $forum_id = 0 ) {
	$forum_id = bbp_get_forum_id( $forum_id );

	return false;
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
 * @return int Forum topic count
 */
function bbp_update_forum_topic_count( $forum_id = 0, $total_count = true ) {
	global $wpdb, $bbp;

	$forum_id = bbp_get_forum_id( $forum_id );

	// If it's a topic, then get the parent (forum id)
	if ( $bbp->topic_id == get_post_field( 'post_type', $forum_id ) ) {
		$topic_id = $forum_id;
		$forum_id = bbp_get_topic_forum_id( $forum_id );
	}

	$topics   = $children_topic_count = 0;
	$children = get_posts( array( 'post_parent' => $forum_id, 'post_type' => $bbp->forum_id, 'meta_key' => '_bbp_forum_visibility', 'meta_value' => 'public' ) );

	// Loop through children and add together forum topic counts
	foreach ( (array) $children as $child )
		$children_topic_count += (int) bbp_get_forum_topic_count( $child->ID );

	// Don't count topics if the forum is a category
	if ( !bbp_is_forum_category( $forum_id ) ) {
		if ( empty( $topic_id ) || !$topics = (int) get_post_meta( $forum_id, '_bbp_forum_topic_count', true ) ) {
			$topics = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(ID) FROM {$wpdb->posts} WHERE post_parent = %d AND post_status IN ( '" . join( "', '", array( 'publish', $bbp->closed_status_id ) ) . "' ) AND post_type = '" . $bbp->topic_id . "';", $forum_id ) );
		} else {
			if ( in_array( bbp_get_topic_status( $topic_id ), array( 'publish', $bbp->closed_status_id ) ) ) {
				$topics++;
			} else {
				$topics--;
			}
		}
	}

	// Calculate total topics in this forum
	$total_topics = $topics + $children_topic_count;

	// Update the count
	update_post_meta( $forum_id, '_bbp_forum_topic_count',       $topics       );
	update_post_meta( $forum_id, '_bbp_forum_total_topic_count', $total_topics );

	// Walk up ancestors
	if ( $parent_id = bbp_get_forum_parent( $forum_id ) )
		bbp_update_forum_topic_count( $parent_id );

	return apply_filters( 'bbp_update_forum_topic_count', empty( $total_count ) ? $topics : $total_topics, $forum_id, $total_count );
}

/**
 * Adjust the total reply count of a forum
 *
 * @todo Make this work
 *
 * @since bbPress (r2464)
 *
 * @param int $forum_id Optional. Forum id or topic id reply id. It is checked
 *                       whether it is a reply or a topic or a forum and the
 *                       forum id is automatically retrieved.
 * @param bool $total_count Optional. To return the total count or normal
 *                           count?
 * @uses get_post_field() To check whether the supplied id is a reply
 * @uses bbp_get_reply_forum_id() To get the reply's forum id
 * @uses bbp_get_topic_forum_id() To get the topic's forum id
 * @uses wpdb::prepare() To prepare the sql statement
 * @uses wpdb::get_col() To execute the query and get the column back
 * @uses wpdb::get_var() To execute the query and get the var back
 * @uses bbp_get_reply_status() To get the reply status
 * @uses update_post_meta() To update the forum's reply count meta
 * @uses apply_filters() Calls 'bbp_update_forum_reply_count' with the reply
 *                        count, forum id and total count bool
 * @return int Forum reply count
 */
function bbp_update_forum_reply_count( $forum_id = 0, $total_count = true ) {
	global $wpdb, $bbp;

	$forum_id = bbp_get_forum_id( $forum_id );

	// If it's a reply, then get the grandparent (forum id)
	if ( $bbp->reply_id == get_post_field( 'post_type', $forum_id ) ) {
		$reply_id = $forum_id;
		$forum_id = bbp_get_reply_forum_id( $forum_id );
	}

	// If it's a topic, then get the parent (forum id)
	if ( $bbp->topic_id == get_post_field( 'post_type', $forum_id ) )
		$forum_id = bbp_get_topic_forum_id( $forum_id );

	$replies  = $children_reply_count = 0;
	$children = get_posts( array( 'post_parent' => $forum_id, 'post_type' => $bbp->forum_id, 'meta_key' => '_bbp_forum_visibility', 'meta_value' => 'public' ) );

	// Loop through children and add together forum reply counts
	foreach ( (array) $children as $child )
		$children_reply_count += (int) bbp_get_forum_reply_count( $child->ID );

	// Don't count replies if the forum is a category
	if ( !bbp_is_forum_category( $forum_id ) ) {
		if ( empty( $reply_id ) || !$replies = (int) get_post_meta( $forum_id, '_bbp_forum_reply_count', true ) ) {
			$topics  = $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_parent = %d AND post_status = 'publish' AND post_type = '" . $bbp->topic_id . "';", $forum_id ) );
			$replies = (int) !empty( $topics ) ? $wpdb->get_var( "SELECT COUNT(ID) FROM {$wpdb->posts} WHERE post_parent IN ( " . join( ',', $topics ) . " ) AND post_status = 'publish' AND post_type = '" . $bbp->reply_id . "';" ) : 0;
		} else {
			if ( 'publish' == bbp_get_reply_status( $reply_id ) ) {
				$replies++;
			} else {
				$replies--;
			}
		}
	}

	// Calculate total replies in this forum
	$total_replies = $replies + $children_reply_count;

	// Update the count
	update_post_meta( $forum_id, '_bbp_forum_reply_count',       $replies       );
	update_post_meta( $forum_id, '_bbp_forum_total_reply_count', $total_replies );

	// Walk up ancestors
	if ( $parent = bbp_get_forum_parent( $forum_id ) )
		bbp_update_forum_reply_count( $parent );

	return apply_filters( 'bbp_update_forum_reply_count', empty( $total_count ) ? $replies : $total_replies, $forum_id, $total_count );
}

/**
 * Adjust the total voice count of a forum
 *
 * @since bbPress (r2567)
 *
 * @param int $forum_id Optional. Forum, topic or reply id. The forum is
 *                                 automatically retrieved based on the input.
 * @uses get_post_field() To check whether the supplied id is a reply
 * @uses bbp_get_reply_topic_id() To get the reply's topic id
 * @uses bbp_get_topic_forum_id() To get the topic's forum id
 * @uses wpdb::prepare() To prepare the sql statement
 * @uses wpdb::get_col() To execute the query and get the column back
 * @uses update_post_meta() To update the forum's voice count meta
 * @uses apply_filters() Calls 'bbp_update_forum_voice_count' with the voice
 *                        count and forum id
 * @return int Forum voice count
 */
function bbp_update_forum_voice_count( $forum_id = 0 ) {
	global $wpdb, $bbp;

	$forum_id = bbp_get_forum_id( $forum_id );

	// If it's a reply, then get the parent (topic id)
	if ( $bbp->reply_id == get_post_field( 'post_type', $forum_id ) )
		$forum_id = bbp_get_reply_topic_id( $forum_id );

	// If it's a topic, then get the parent (forum id)
	if ( $bbp->topic_id == get_post_field( 'post_type', $forum_id ) )
		$forum_id = bbp_get_topic_forum_id( $forum_id );

	// There should always be at least 1 voice
	if ( !$voices = count( $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT post_author FROM $wpdb->posts WHERE ( post_parent = %d AND post_status = 'publish' AND post_type = '" . $bbp->reply_id . "' ) OR ( ID = %d AND post_type = '" . $bbp->forum_id . "' );", $forum_id, $forum_id ) ) ) )
		$voices = 1;

	// Update the count
	update_post_meta( $forum_id, '_bbp_forum_voice_count', (int) $voices );

	return apply_filters( 'bbp_update_forum_voice_count', (int) $voices, $forum_id );
}

?>
