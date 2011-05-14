<?php

/**
 * bbPress General Functions
 *
 * @package bbPress
 * @subpackage Functions
 */

// Redirect if accessed directly
if ( !defined( 'ABSPATH' ) ) exit;

/** Formatting ****************************************************************/

/**
 * A bbPress specific method of formatting numeric values
 *
 * @since bbPress (r2486)
 *
 * @param string $number Number to format
 * @param string $decimals Optional. Display decimals
 * @uses apply_filters() Calls 'bbp_number_format' with the formatted values,
 *                        number and display decimals bool
 * @return string Formatted string
 */
function bbp_number_format( $number, $decimals = false ) {
	// If empty, set $number to '0'
	if ( empty( $number ) || !is_numeric( $number ) )
		$number = '0';

	return apply_filters( 'bbp_number_format', number_format( $number, $decimals ), $number, $decimals );
}

/**
 * Convert time supplied from database query into specified date format.
 *
 * @since bbPress (r2455)
 *
 * @param int|object $post Optional. Default is global post object. A post_id or
 *                          post object
 * @param string $d Optional. Default is 'U'. Either 'G', 'U', or php date
 *                             format
 * @param bool $translate Optional. Default is false. Whether to translate the
 *                                   result
 * @uses mysql2date() To convert the format
 * @uses apply_filters() Calls 'bbp_convert_date' with the time, date format
 *                        and translate bool
 * @return string Returns timestamp
 */
function bbp_convert_date( $time, $d = 'U', $translate = false ) {
	$time = mysql2date( $d, $time, $translate );

	return apply_filters( 'bbp_convert_date', $time, $d, $translate );
}

/**
 * Output formatted time to display human readable time difference.
 *
 * @since bbPress (r2544)
 *
 * @param $time Unix timestamp from which the difference begins.
 * @uses bbp_get_time_since() To get the formatted time
 */
function bbp_time_since( $time ) {
	echo bbp_get_time_since( $time );
}
	/**
	 * Return formatted time to display human readable time difference.
	 *
	 * @since bbPress (r2544)
	 *
	 * @param $time Unix timestamp from which the difference begins.
         * @uses current_time() To get the current time in mysql format
         * @uses human_time_diff() To get the time differene in since format
         * @uses apply_filters() Calls 'bbp_get_time_since' with the time
         *                        difference and time
         * @return string Formatted time
	 */
	function bbp_get_time_since( $time ) {
		return apply_filters( 'bbp_get_time_since', human_time_diff( $time, current_time( 'timestamp' ) ), $time );
	}

/**
 * Formats the reason for editing the topic/reply.
 *
 * Does these things:
 *  - Trimming
 *  - Removing periods from the end of the string
 *  - Trimming again
 *
 * @since bbPress (r2782)
 *
 * @param int $topic_id Optional. Topic id
 * @return string Status of topic
 */
function bbp_format_revision_reason( $reason = '' ) {
	$reason = (string) $reason;

	// Format reason for proper display
	if ( empty( $reason ) )
		return $reason;

	// Trimming
	$reason = trim( $reason );

	// We add our own full stop.
	while ( substr( $reason, -1 ) == '.' ) {
		$reason = substr( $reason, 0, -1 );
	}

	// Trim again
	$reason = trim( $reason );

	return $reason;
}

/** Misc **********************************************************************/

/**
 * The plugin version of bbPress comes with two topic display options:
 * - Traditional: Topics are included in the reply loop (default)
 * - New Style: Topics appear as "lead" posts, ahead of replies
 *
 * @since bbPress (r2954)
 *
 * @param $show_lead Optional. Default false
 * @return bool Yes if the topic appears as a lead, otherwise false
 */
function bbp_show_lead_topic( $show_lead = false ) {
	return apply_filters( 'bbp_show_lead_topic', (bool) $show_lead );
}

/**
 * Remove the canonical redirect to allow pretty pagination
 *
 * @since bbPress (r2628)
 *
 * @param string $redirect_url Redirect url
 * @uses WP_Rewrite::using_permalinks() To check if the blog is using permalinks
 * @uses bbp_is_topic() To check if it's a topic page
 * @uses bbp_get_paged() To get the current page number
 * @uses bbp_is_forum() To check if it's a forum page
 * @return bool|string False if it's a topic/forum and their first page,
 *                      otherwise the redirect url
 */
function bbp_redirect_canonical( $redirect_url ) {
	global $wp_rewrite;

	if ( $wp_rewrite->using_permalinks() ) {
		if ( bbp_is_topic() && 1 < bbp_get_paged() )
			$redirect_url = false;
		elseif ( bbp_is_forum() && 1 < bbp_get_paged() )
			$redirect_url = false;
	}

	return $redirect_url;
}

/**
 * Sets the 404 status.
 *
 * Used primarily with topics/replies inside hidden forums.
 *
 * @since bbPress (r3051)
 *
 * @global WP_Query $wp_query
 * @uses WP_Query::set_404()
 */
function bbp_set_404() {
	global $wp_query;

	if ( ! isset( $wp_query ) ) {
		_doing_it_wrong( __FUNCTION__, __( 'Conditional query tags do not work before the query is run. Before then, they always return false.' ), '3.1' );
		return false;
	}

	$wp_query->set_404();
}

/**
 * Append 'view=all' to query string if it's already there from referer
 *
 * @param string $original_link Original Link to be modified
 * @uses current_user_can() To check if the current user can moderate
 * @uses add_query_arg() To add args to the url
 * @uses apply_filters() Calls 'bbp_add_view_all' with the link and original link
 * @return string The link with 'view=all' appended if necessary
 */
function bbp_add_view_all( $original_link ) {

	// Bail if empty
	if ( empty( $original_link ) )
		return $original_link;

	// Are we appending the view=all vars?
	if ( ( !empty( $_GET['view'] ) && ( 'all' == $_GET['view'] ) && current_user_can( 'moderate' ) ) )
		$link = add_query_arg( array( 'view' => 'all' ), $original_link );
	else
		$link = $original_link;

	return apply_filters( 'bbp_add_view_all', $link, $original_link );
}

/**
 * Assist pagination by returning correct page number
 *
 * @since bbPress (r2628)
 *
 * @uses get_query_var() To get the 'paged' value
 * @return int Current page number
 */
function bbp_get_paged() {

	// Make sure to not paginate widget queries
	if ( !bbp_is_query_name( 'bbp_widget' ) && ( $paged = get_query_var( 'paged' ) ) )
		return (int) $paged;

	// Default to first page
	return 1;
}

/**
 * Fix post author id on post save
 *
 * When a logged in user changes the status of an anonymous reply or topic, or
 * edits it, the post_author field is set to the logged in user's id. This
 * function fixes that.
 *
 * @since bbPress (r2734)
 *
 * @param array $data Post data
 * @param array $postarr Original post array (includes post id)
 * @uses bbp_get_topic_post_type() To get the topic post type
 * @uses bbp_get_reply_post_type() To get the reply post type
 * @uses bbp_is_topic_anonymous() To check if the topic is by an anonymous user
 * @uses bbp_is_reply_anonymous() To check if the reply is by an anonymous user
 * @return array Data
 */
function bbp_fix_post_author( $data = array(), $postarr = array() ) {
	global $bbp;

	// Post is not being updated or the post_author is already 0, return
	if ( empty( $postarr['ID'] ) || empty( $data['post_author'] ) )
		return $data;

	// Post is not a topic or reply, return
	if ( !in_array( $data['post_type'], array( bbp_get_topic_post_type(), bbp_get_reply_post_type() ) ) )
		return $data;

	// Is the post by an anonymous user?
	if ( ( bbp_get_topic_post_type() == $data['post_type'] && !bbp_is_topic_anonymous( $postarr['ID'] ) ) ||
	     ( bbp_get_reply_post_type() == $data['post_type'] && !bbp_is_reply_anonymous( $postarr['ID'] ) ) )
		return $data;

	// The post is being updated. It is a topic or a reply and is written by an anonymous user.
	// Set the post_author back to 0
	$data['post_author'] = 0;

	return $data;
}

/**
 * Check the date against the _bbp_edit_lock setting.
 *
 * @since bbPress (r3133)
 *
 * @param string $post_date_gmt
 *
 * @uses get_option() Get the edit lock time
 * @uses current_time() Get the current time
 * @uses strtotime() Convert strings to time
 * @uses apply_filters() Allow output to be manipulated
 *
 * @return bool
 */
function bbp_past_edit_lock( $post_date_gmt ) {

	// Assume editing is allowed
	$retval = false;

	// Bail if empty date
	if ( ! empty( $post_date_gmt ) ) {

		// Period of time
		$lockable  = '+' . get_option( '_bbp_edit_lock', '5' ) . ' minutes';

		// Now
		$cur_time  = current_time( 'timestamp', true );

		// Add lockable time to post time
		$lock_time = strtotime( $lockable, strtotime( $post_date_gmt ) );

		// Compare
		if ( $cur_time >= $lock_time ) {
			$retval = true;
		}
	}

	return apply_filters( 'bbp_past_edit_lock', (bool) $retval, $cur_time, $lock_time, $post_date_gmt );
}

/** Statistics ****************************************************************/

/**
 * Get the forum statistics
 *
 * @since bbPress (r2769)
 *
 * @param mixed $args Optional. The function supports these arguments (all
 *                     default to true):
 *  - count_users: Count users?
 *  - count_forums: Count forums?
 *  - count_topics: Count topics? If set to false, private, spammed and trashed
 *                   topics are also not counted.
 *  - count_private_topics: Count private topics? (only counted if the current
 *                           user has read_private_topics cap)
 *  - count_spammed_topics: Count spammed topics? (only counted if the current
 *                           user has edit_others_topics cap)
 *  - count_trashed_topics: Count trashed topics? (only counted if the current
 *                           user has view_trash cap)
 *  - count_replies: Count replies? If set to false, private, spammed and
 *                   trashed replies are also not counted.
 *  - count_private_replies: Count private replies? (only counted if the current
 *                           user has read_private_replies cap)
 *  - count_spammed_replies: Count spammed replies? (only counted if the current
 *                           user has edit_others_replies cap)
 *  - count_trashed_replies: Count trashed replies? (only counted if the current
 *                           user has view_trash cap)
 *  - count_tags: Count tags? If set to false, empty tags are also not counted
 *  - count_empty_tags: Count empty tags?
 * @uses bbp_count_users() To count the number of registered users
 * @uses bbp_get_forum_post_type() To get the forum post type
 * @uses bbp_get_topic_post_type() To get the topic post type
 * @uses bbp_get_reply_post_type() To get the reply post type
 * @uses wp_count_posts() To count the number of forums, topics and replies
 * @uses wp_count_terms() To count the number of topic tags
 * @uses current_user_can() To check if the user is capable of doing things
 * @uses number_format_i18n() To format the number
 * @uses apply_filters() Calls 'bbp_get_statistics' with the statistics and args
 * @return object Walked forum tree
 */
function bbp_get_statistics( $args = '' ) {
	global $bbp;

	$defaults = array (
		'count_users'           => true,
		'count_forums'          => true,
		'count_topics'          => true,
		'count_private_topics'  => true,
		'count_spammed_topics'  => true,
		'count_trashed_topics'  => true,
		'count_replies'         => true,
		'count_private_replies' => true,
		'count_spammed_replies' => true,
		'count_trashed_replies' => true,
		'count_tags'            => true,
		'count_empty_tags'      => true
	);

	$r = wp_parse_args( $args, $defaults );
	extract( $r );

	// Users
	if ( !empty( $count_users ) )
		$user_count = bbp_get_total_users();

	// Forums
	if ( !empty( $count_forums ) ) {
		$forum_count = wp_count_posts( bbp_get_forum_post_type() );
		$forum_count = $forum_count->publish;
	}

	// Topics
	if ( !empty( $count_topics ) ) {

		$all_topics     = wp_count_posts( bbp_get_topic_post_type() );

		// Published (publish + closed)
		$topic_count    = $all_topics->publish + $all_topics->{$bbp->closed_status_id};

		if ( current_user_can( 'read_private_topics' ) || current_user_can( 'edit_others_topics' ) || current_user_can( 'view_trash' ) ) {

			// Private
			$private_topics = ( !empty( $count_private_topics ) && current_user_can( 'read_private_topics' ) ) ? (int) $all_topics->private                 : 0;

			// Spam
			$spammed_topics = ( !empty( $count_spammed_topics ) && current_user_can( 'edit_others_topics'  ) ) ? (int) $all_topics->{$bbp->spam_status_id}  : 0;

			// Trash
			$trashed_topics = ( !empty( $count_trashed_topics ) && current_user_can( 'view_trash'          ) ) ? (int) $all_topics->{$bbp->trash_status_id} : 0;

			// Total hidden (private + spam + trash)
			$hidden_topic_count = $private_topics + $spammed_topics + $trashed_topics;

			// Generate the hidden topic count's title attribute
			$hidden_topic_title  = !empty( $private_topics ) ? sprintf( __( 'Private: %s | ', 'bbpress' ), number_format_i18n( $private_topics ) ) : '';
			$hidden_topic_title .= !empty( $spammed_topics ) ? sprintf( __( 'Spammed: %s | ', 'bbpress' ), number_format_i18n( $spammed_topics ) ) : '';
			$hidden_topic_title .= !empty( $trashed_topics ) ? sprintf( __( 'Trashed: %s',    'bbpress' ), number_format_i18n( $trashed_topics ) ) : '';

		}

	}

	// Replies
	if ( !empty( $count_replies ) ) {

		$all_replies     = wp_count_posts( bbp_get_reply_post_type() );

		// Published
		$reply_count     = $all_replies->publish;

		if ( current_user_can( 'read_private_replies' ) || current_user_can( 'edit_others_replies' ) || current_user_can( 'view_trash' ) ) {

			// Private
			$private_replies = ( !empty( $count_private_replies ) && current_user_can( 'read_private_replies' ) ) ? (int) $all_replies->private                 : 0;

			// Spam
			$spammed_replies = ( !empty( $count_spammed_replies ) && current_user_can( 'edit_others_replies'  ) ) ? (int) $all_replies->{$bbp->spam_status_id}  : 0;

			// Trash
			$trashed_replies = ( !empty( $count_trashed_replies ) && current_user_can( 'view_trash'           ) ) ? (int) $all_replies->{$bbp->trash_status_id} : 0;

			// Total hidden (private + spam + trash)
			$hidden_reply_count = $private_replies + $spammed_replies + $trashed_replies;

			// Generate the hidden reply count's title attribute
			$hidden_reply_title  = !empty( $private_replies ) ? sprintf( __( 'Private: %s | ', 'bbpress' ), number_format_i18n( $private_replies ) ) : '';
			$hidden_reply_title .= !empty( $spammed_replies ) ? sprintf( __( 'Spammed: %s | ', 'bbpress' ), number_format_i18n( $spammed_replies ) ) : '';
			$hidden_reply_title .= !empty( $trashed_replies ) ? sprintf( __( 'Trashed: %s',    'bbpress' ), number_format_i18n( $trashed_replies ) ) : '';

		}

	}

	// Topic Tags
	if ( !empty( $count_tags ) ) {
		$topic_tag_count = wp_count_terms( $bbp->topic_tag_id, array( 'hide_empty' => true ) );

		if ( !empty( $count_empty_tags ) && current_user_can( 'edit_topic_tags' ) )
			$empty_topic_tag_count = wp_count_terms( $bbp->topic_tag_id ) - $topic_tag_count;
	}

	$statistics = compact( 'user_count', 'forum_count', 'topic_count', 'hidden_topic_count', 'reply_count', 'hidden_reply_count', 'topic_tag_count', 'empty_topic_tag_count' );
	$statistics = array_map( 'absint',             $statistics );
	$statistics = array_map( 'number_format_i18n', $statistics );

	// Add the hidden (topic/reply) count title attribute strings because we don't need to run the math functions on these (see above)
	if ( isset( $hidden_topic_title ) )
		$statistics['hidden_topic_title'] = $hidden_topic_title;

	if ( isset( $hidden_reply_title ) )
		$statistics['hidden_reply_title'] = $hidden_reply_title;

	return apply_filters( 'bbp_get_statistics', $statistics, $args );
}

/** Views *********************************************************************/

/**
 * Get the registered views
 *
 * Does nothing much other than return the {@link $bbp->views} variable
 *
 * @since bbPress (r2789)
 *
 * @return array Views
 */
function bbp_get_views() {
	global $bbp;

	return $bbp->views;
}

/**
 * Register a bbPress view
 *
 * @todo Implement feeds - See {@link http://trac.bbpress.org/ticket/1422}
 *
 * @since bbPress (r2789)
 *
 * @param string $view View name
 * @param string $title View title
 * @param mixed $query_args {@link bbp_has_topics()} arguments.
 * @param bool $feed Have a feed for the view? Defaults to true. NOT IMPLEMENTED
 * @uses sanitize_title() To sanitize the view name
 * @uses esc_html() To sanitize the view title
 * @return array The just registered (but processed) view
 */
function bbp_register_view( $view, $title, $query_args = '', $feed = true ) {
	global $bbp;

	$view  = sanitize_title( $view );
	$title = esc_html( $title );

	if ( empty( $view ) || empty( $title ) )
		return false;

	$query_args = wp_parse_args( $query_args );

	// Set exclude_stickies to true if it wasn't supplied
	if ( !isset( $query_args['show_stickies'] ) )
		$query_args['show_stickies'] = false;

	$bbp->views[$view]['title'] = $title;
	$bbp->views[$view]['query'] = $query_args;
	$bbp->views[$view]['feed']  = $feed;

	return $bbp->views[$view];
}

/**
 * Deregister a bbPress view
 *
 * @since bbPress (r2789)
 *
 * @param string $view View name
 * @uses sanitize_title() To sanitize the view name
 * @return bool False if the view doesn't exist, true on success
 */
function bbp_deregister_view( $view ) {
	global $bbp;

	$view = sanitize_title( $view );

	if ( !isset( $bbp->views[$view] ) )
		return false;

	unset( $bbp->views[$view] );

	return true;
}

/**
 * Run the view's query
 *
 * @since bbPress (r2789)
 *
 * @param string $view Optional. View id
 * @param mixed $new_args New arguments. See {@link bbp_has_topics()}
 * @uses bbp_get_view_id() To get the view id
 * @uses bbp_get_view_query_args() To get the view query args
 * @uses sanitize_title() To sanitize the view name
 * @uses bbp_has_topics() To make the topics query
 * @return bool False if the view doesn't exist, otherwise if topics are there
 */
function bbp_view_query( $view = '', $new_args = '' ) {
	global $bbp;

	if ( !$view = bbp_get_view_id( $view ) )
		return false;

	$query_args = bbp_get_view_query_args( $view );

	if ( !empty( $new_args ) ) {
		$new_args   = wp_parse_args( $new_args );
		$query_args = array_merge( $query_args, $new_args );
	}

	return bbp_has_topics( $query_args );
}

/**
 * Run the view's query's arguments
 *
 * @since bbPress (r2789)
 *
 * @param string $view View name
 * @uses bbp_get_view_id() To get the view id
 * @uses sanitize_title() To sanitize the view name
 * @return array Query arguments
 */
function bbp_get_view_query_args( $view ) {
	global $bbp;

	if ( !$views = bbp_get_view_id( $view ) )
		return false;

	return $bbp->views[$view]['query'];
}

/** New/edit topic/reply helpers **********************************************/

/**
 * Filter anonymous post data
 *
 * We use REMOTE_ADDR here directly. If you are behind a proxy, you should
 * ensure that it is properly set, such as in wp-config.php, for your
 * environment. See {@link http://core.trac.wordpress.org/ticket/9235}
 *
 * If there are any errors, those are directly added to {@link bbPress:errors}
 *
 * @since bbPress (r2734)
 *
 * @param mixed $args Optional. If no args are there, then $_POST values are
 *                     used.
 * @param bool $is_edit Optional. Is the topic/reply being edited? There are no
 *                       IP checks then.
 * @uses apply_filters() Calls 'bbp_pre_anonymous_post_author_name' with the
 *                        anonymous user name
 * @uses apply_filters() Calls 'bbp_pre_anonymous_post_author_email' with the
 *                        anonymous user email
 * @uses apply_filters() Calls 'bbp_pre_anonymous_post_author_ip' with the
 *                        anonymous user's ip address
 * @uses apply_filters() Calls 'bbp_pre_anonymous_post_author_website' with the
 *                        anonymous user website
 * @return bool|array False on errors, values in an array on success
 */
function bbp_filter_anonymous_post_data( $args = '', $is_edit = false ) {
	global $bbp;

	// Assign variables
	$defaults = array (
		'bbp_anonymous_name'    => !empty( $_POST['bbp_anonymous_name']    ) ? $_POST['bbp_anonymous_name']    : false,
		'bbp_anonymous_email'   => !empty( $_POST['bbp_anonymous_email']   ) ? $_POST['bbp_anonymous_email']   : false,
		'bbp_anonymous_website' => !empty( $_POST['bbp_anonymous_website'] ) ? $_POST['bbp_anonymous_website'] : false,
	);

	$r = wp_parse_args( $args, $defaults );
	extract( $r );

	// Filter variables and add errors if necessary
	if ( !$bbp_anonymous_name  = apply_filters( 'bbp_pre_anonymous_post_author_name',  $bbp_anonymous_name  ) )
		$bbp->errors->add( 'bbp_anonymous_name',  __( '<strong>ERROR</strong>: Invalid author name submitted!',   'bbpress' ) );

	if ( !$bbp_anonymous_email = apply_filters( 'bbp_pre_anonymous_post_author_email', $bbp_anonymous_email ) )
		$bbp->errors->add( 'bbp_anonymous_email', __( '<strong>ERROR</strong>: Invalid email address submitted!', 'bbpress' ) );

	// Website is optional
	$bbp_anonymous_website = apply_filters( 'bbp_pre_anonymous_post_author_website', $bbp_anonymous_website );

	if ( !is_wp_error( $bbp->errors ) || !$bbp->errors->get_error_codes() )
		$retval = compact( 'bbp_anonymous_name', 'bbp_anonymous_email', 'bbp_anonymous_website' );
	else
		$retval = false;

	// Finally, return sanitized data or false
	return apply_filters( 'bbp_filter_anonymous_post_data', $retval, $args );
}

/**
 * Check for duplicate topics/replies
 *
 * Check to make sure that a user is not making a duplicate post
 *
 * @since bbPress (r2763)
 *
 * @param array $post_data Contains information about the comment
 * @uses current_user_can() To check if the current user can throttle
 * @uses get_meta_sql() To generate the meta sql for checking anonymous email
 * @uses apply_filters() Calls 'bbp_check_for_duplicate_query' with the
 *                        duplicate check query and post data
 * @uses wpdb::get_var() To execute our query and get the var back
 * @uses get_post_meta() To get the anonymous user email post meta
 * @uses do_action() Calls 'bbp_post_duplicate_trigger' with the post data when
 *                    it is found that it is a duplicate
 * @return bool True if it is not a duplicate, false if it is
 */
function bbp_check_for_duplicate( $post_data ) {

	// No duplicate checks for those who can throttle
	if ( current_user_can( 'throttle' ) )
		return true;

	global $bbp, $wpdb;

	extract( $post_data, EXTR_SKIP );

	// Check for anonymous post
	if ( empty( $post_author ) && !empty( $anonymous_data['bbp_anonymous_email'] ) ) {

		// WP 3.2
		if ( function_exists( 'get_meta_sql' ) )
			$clauses = get_meta_sql( array( array( 'key' => '_bbp_anonymous_email', 'value' => $anonymous_data['bbp_anonymous_email'] ) ), 'post', $wpdb->posts, 'ID' );

		// WP 3.1
		elseif ( function_exists( '_get_meta_sql' ) )
			$clauses = _get_meta_sql( array( array( 'key' => '_bbp_anonymous_email', 'value' => $anonymous_data['bbp_anonymous_email'] ) ), 'post', $wpdb->posts, 'ID' );

		$join    = $clauses['join'];
		$where   = $clauses['where'];
	} else{
		$join    = $where = '';
	}

	// Simple duplicate check
	// Expected slashed ($post_type, $post_parent, $post_author, $post_content, $anonymous_data)
	$dupe  = "SELECT ID FROM {$wpdb->posts} {$join} WHERE post_type = '{$post_type}' AND post_status != '{$bbp->trash_status_id}' AND post_author = {$post_author} AND post_content = '{$post_content}' {$where}";
	$dupe .= !empty( $post_parent ) ? " AND post_parent = '{$post_parent}'" : '';
	$dupe .= " LIMIT 1";
	$dupe  = apply_filters( 'bbp_check_for_duplicate_query', $dupe, $post_data );

	if ( $wpdb->get_var( $dupe ) ) {
		do_action( 'bbp_check_for_duplicate_trigger', $post_data );
		return false;
	}

	return true;
}

/**
 * Check for flooding
 *
 * Check to make sure that a user is not making too many posts in a short amount
 * of time.
 *
 * @since bbPress (r2734)
 *
 * @param false|array $anonymous_data Optional - if it's an anonymous post. Do
 *                                     not supply if supplying $author_id.
 *                                     Should have key 'bbp_author_ip'.
 *                                     Should be sanitized (see
 *                                     {@link bbp_filter_anonymous_post_data()}
 *                                     for sanitization)
 * @param int $author_id Optional. Supply if it's a post by a logged in user.
 *                        Do not supply if supplying $anonymous_data.
 * @uses get_option() To get the throttle time
 * @uses get_transient() To get the last posted transient of the ip
 * @uses get_user_meta() To get the last posted meta of the user
 * @uses current_user_can() To check if the current user can throttle
 * @return bool True if there is no flooding, true if there is
 */
function bbp_check_for_flood( $anonymous_data = false, $author_id = 0 ) {

	// Option disabled. No flood checks.
	if ( !$throttle_time = get_option( '_bbp_throttle_time' ) )
		return true;

	if ( !empty( $anonymous_data ) && is_array( $anonymous_data ) ) {
		$last_posted = get_transient( '_bbp_' . bbp_current_author_ip() . '_last_posted' );
		if ( !empty( $last_posted ) && time() < $last_posted + $throttle_time )
			return false;
	} elseif ( !empty( $author_id ) ) {
		$author_id   = (int) $author_id;
		$last_posted = get_user_meta( $author_id, '_bbp_last_posted', true );

		if ( isset( $last_posted ) && time() < $last_posted + $throttle_time && !current_user_can( 'throttle' ) )
			return false;
	} else {
		return false;
	}

	return true;
}

/** Subscriptions *************************************************************/

/**
 * Sends notification emails for new posts
 *
 * Gets new post's ID and check if there are subscribed users to that topic, and
 * if there are, send notifications
 *
 * @since bbPress (r2668)
 *
 * @todo When Akismet is made to work with bbPress posts, add a check if the
 * post is spam or not, to avoid sending out mails for spam posts
 *
 * @param int $reply_id ID of the newly made reply
 * @uses bbp_is_subscriptions_active() To check if the subscriptions are active
 * @uses bbp_get_reply() To get the reply
 * @uses bbp_get_topic() To get the reply's topic
 * @uses get_the_author_meta() To get the author's display name
 * @uses do_action() Calls 'bbp_pre_notify_subscribers' with the reply id and
 *                    topic id
 * @uses bbp_get_topic_subscribers() To get the topic subscribers
 * @uses apply_filters() Calls 'bbp_subscription_mail_message' with the
 *                        message, reply id, topic id and user id
 * @uses get_userdata() To get the user data
 * @uses wp_mail() To send the mail
 * @uses do_action() Calls 'bbp_post_notify_subscribers' with the reply id
 *                    and topic id
 * @return bool True on success, false on failure
 */
function bbp_notify_subscribers( $reply_id = 0 ) {
	global $bbp, $wpdb;

	if ( !bbp_is_subscriptions_active() )
		return false;

	if ( empty( $reply_id ) )
		return false;

	if ( !$reply = bbp_get_reply( $reply_id ) )
		return false;

	if ( empty( $reply->post_parent ) )
		return false;

	if ( !$topic = bbp_get_topic( $reply->post_parent ) )
		return false;

	if ( !$poster_name = get_the_author_meta( 'display_name', $reply->post_author ) )
		return false;

	do_action( 'bbp_pre_notify_subscribers', $reply->ID, $topic->ID );

	// Get the users who have favorited the topic and have subscriptions on
	if ( !$user_ids = bbp_get_topic_subscribers( $topic->ID, true ) )
		return false;

	// Loop through users
	foreach ( (array) $user_ids as $user_id ) {

		// Don't send notifications to the person who made the post
		if ( $user_id == $reply->post_author )
			continue;

		// For plugins
		if ( !$message = apply_filters( 'bbp_subscription_mail_message', __( "%1\$s wrote:\n\n%2\$s\n\nPost Link: %3\$s\n\nYou're getting this mail because you subscribed to the topic, visit the topic and login to unsubscribe." ), $reply->ID, $topic->ID, $user_id ) )
			continue;

		// Get user data of this user
		$user = get_userdata( $user_id );

		// Send notification email
		wp_mail(
			$user->user_email,
			apply_filters( 'bbp_subscription_mail_title', '[' . get_option( 'blogname' ) . '] ' . $topic->post_title, $reply->ID, $topic->ID ),
			sprintf( $message, $poster_name, strip_tags( $reply->post_content ), bbp_get_reply_permalink( $reply->ID ) )
		);
	}

	do_action( 'bbp_post_notify_subscribers', $reply->ID, $topic->ID );

	return true;
}

/** Login *********************************************************************/

/**
 * Change the logout URL to /login and add smart redirect
 *
 * This assumes that your login page is 'domain.com/login'
 *
 * @param string $url URL
 * @param string $redirect_to Where to redirect to?
 * @uses add_query_arg() To add args to the url
 * @uses apply_filters() Calls 'bbp_logout_url' with the url and redirect to
 * @return string The url
 */
function bbp_logout_url( $url = '', $redirect_to = '' ) {

	// Rejig the $redirect_to
	if ( !isset( $_SERVER['REDIRECT_URL'] ) || ( !$redirect_to = home_url( $_SERVER['REDIRECT_URL'] ) ) )
		$redirect_to = isset( $_SERVER['HTTP_REFERER'] ) ? $_SERVER['HTTP_REFERER'] : '';

	// Make sure we are directing somewhere
	if ( empty( $redirect_to ) )
		$redirect_to = home_url( isset( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : '' );

	// Sanitize $redirect_to and add it to full $url
	$redirect_to = esc_url( add_query_arg( array( 'loggedout'   => 'true'       ), $redirect_to ) );
	$url         =          add_query_arg( array( 'redirect_to' => $redirect_to ), $url           );

	// Filter and return
	return apply_filters( 'bbp_logout_url', $url, $redirect_to );
}

/** Queries *******************************************************************/

/**
 * Adds ability to include or exclude specific post_parent ID's
 *
 * @since bbPress (r2996)
 *
 * @global DB $wpdb
 * @global WP $wp
 * @param string $where
 * @param WP_Query $object
 * @return string
 */
function bbp_query_post_parent__in( $where, $object ) {
	global $wpdb, $wp;

	// Noop if WP core supports this already
	if ( in_array( 'post_parent__in', $wp->private_query_vars ) )
		return $where;

	// Only 1 post_parent so return $where
	if ( is_numeric( $object->query_vars['post_parent'] ) )
		return $where;

	// Including specific post_parent's
	if ( ! empty( $object->query_vars['post_parent__in'] ) ) {
		$ids    = implode( ',', array_map( 'absint', $object->query_vars['post_parent__in'][0] ) );
		$where .= " AND $wpdb->posts.post_parent IN ($ids)";

	// Excluding specific post_parent's
	} elseif ( ! empty( $object->query_vars['post_parent__not_in'] ) ) {
		$ids    = implode( ',', array_map( 'absint', $object->query_vars['post_parent__not_in'][0] ) );
		$where .= " AND $wpdb->posts.post_parent NOT IN ($ids)";
	}

	// Return possibly modified $where
	return $where;
}
add_filter( 'posts_where', 'bbp_query_post_parent__in', 10, 2 );

/**
 * Query the DB and get the last public post_id that has parent_id as post_parent
 *
 * @param int $parent_id Parent id
 * @param string $post_type Post type. Defaults to 'post'
 * @uses bbp_get_topic_post_type() To get the topic post type
 * @uses wp_cache_get() To check if there is a cache of the last child id
 * @uses wpdb::prepare() To prepare the query
 * @uses wpdb::get_var() To get the result of the query in a variable
 * @uses wp_cache_set() To set the cache for future use
 * @uses apply_filters() Calls 'bbp_get_public_child_last_id' with the child
 *                        id, parent id and post type
 * @return int The last active post_id
 */
function bbp_get_public_child_last_id( $parent_id = 0, $post_type = 'post' ) {
	global $wpdb;

	// Bail if nothing passed
	if ( empty( $parent_id ) )
		return false;

	// The ID of the cached query
	$cache_id    = 'bbp_parent_' . $parent_id . '_type_' . $post_type . '_child_last_id';
	$post_status = array( 'publish' );

	// Add closed status if topic post type
	if ( $post_type == bbp_get_topic_post_type() )
		$post_status[] = $bbp->closed_status_id;

	// Join post statuses together
	$post_status = "'" . join( "', '", $post_status ) . "'";

	// Check for cache and set if needed
	if ( !$child_id = wp_cache_get( $cache_id, 'bbpress' ) ) {
		$child_id = $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_parent = %d AND post_status IN ( {$post_status} ) AND post_type = '%s' ORDER BY ID DESC LIMIT 1;", $parent_id, $post_type ) );
		wp_cache_set( $cache_id, $child_id, 'bbpress' );
	}

	// Filter and return
	return apply_filters( 'bbp_get_public_child_last_id', (int) $child_id, (int) $parent_id, $post_type );
}

/**
 * Query the DB and get a count of public children
 *
 * @param int $parent_id Parent id
 * @param string $post_type Post type. Defaults to 'post'
 * @uses bbp_get_topic_post_type() To get the topic post type
 * @uses wp_cache_get() To check if there is a cache of the children count
 * @uses wpdb::prepare() To prepare the query
 * @uses wpdb::get_var() To get the result of the query in a variable
 * @uses wp_cache_set() To set the cache for future use
 * @uses apply_filters() Calls 'bbp_get_public_child_count' with the child
 *                        count, parent id and post type
 * @return int The number of children
 */
function bbp_get_public_child_count( $parent_id = 0, $post_type = 'post' ) {
	global $wpdb, $bbp;

	// Bail if nothing passed
	if ( empty( $parent_id ) )
		return false;

	// The ID of the cached query
	$cache_id    = 'bbp_parent_' . $parent_id . '_type_' . $post_type . '_child_count';
	$post_status = array( 'publish' );

	// Add closed status if topic post type
	if ( $post_type == bbp_get_topic_post_type() )
		$post_status[] = $bbp->closed_status_id;

	// Join post statuses together
	$post_status = "'" . join( "', '", $post_status ) . "'";

	// Check for cache and set if needed
	if ( !$child_count = wp_cache_get( $cache_id, 'bbpress' ) ) {
		$child_count = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(ID) FROM {$wpdb->posts} WHERE post_parent = %d AND post_status IN ( {$post_status} ) AND post_type = '%s';", $parent_id, $post_type ) );
		wp_cache_set( $cache_id, $child_count, 'bbpress' );
	}

	// Filter and return
	return apply_filters( 'bbp_get_public_child_count', (int) $child_count, (int) $parent_id, $post_type );
}

/**
 * Query the DB and get a the child id's of public children
 *
 * @param int $parent_id Parent id
 * @param string $post_type Post type. Defaults to 'post'
 * @uses bbp_get_topic_post_type() To get the topic post type
 * @uses wp_cache_get() To check if there is a cache of the children
 * @uses wpdb::prepare() To prepare the query
 * @uses wpdb::get_col() To get the result of the query in an array
 * @uses wp_cache_set() To set the cache for future use
 * @uses apply_filters() Calls 'bbp_get_public_child_ids' with the child ids,
 *                        parent id and post type
 * @return array The array of children
 */
function bbp_get_public_child_ids( $parent_id = 0, $post_type = 'post' ) {
	global $wpdb, $bbp;

	// Bail if nothing passed
	if ( empty( $parent_id ) )
		return false;

	// The ID of the cached query
	$cache_id    = 'bbp_parent_' . $parent_id . '_type_' . $post_type . '_child_ids';
	$post_status = array( 'publish' );

	// Add closed status if topic post type
	if ( $post_type == bbp_get_topic_post_type() )
		$post_status[] = $bbp->closed_status_id;

	// Join post statuses together
	$post_status = "'" . join( "', '", $post_status ) . "'";

	// Check for cache and set if needed
	if ( !$child_ids = wp_cache_get( $cache_id, 'bbpress' ) ) {
		$child_ids = $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_parent = %d AND post_status IN ( {$post_status} ) AND post_type = '%s' ORDER BY ID DESC;", $parent_id, $post_type ) );
		wp_cache_set( $cache_id, $child_ids, 'bbpress' );
	}

	// Filter and return
	return apply_filters( 'bbp_get_public_child_ids', $child_ids, (int) $parent_id, $post_type );
}

?>
