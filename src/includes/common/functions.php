<?php

/**
 * bbPress Common Functions
 *
 * Common functions are ones that are used by more than one component, like
 * forums, topics, replies, users, topic tags, etc...
 *
 * @package bbPress
 * @subpackage Functions
 */

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

/**
 * Return array of bbPress registered post types
 *
 * @since 2.6.0 bbPress (r6813)
 *
 * @param array $args Array of arguments to pass into `get_post_types()`
 *
 * @return array
 */
function bbp_get_post_types( $args = array() ) {

	// Parse args
	$r = bbp_parse_args( $args, array(
		'source' => 'bbpress'
	), 'get_post_types' );

	// Return post types
	return get_post_types( $r );
}

/** URLs **********************************************************************/

/**
 * Return the unescaped redirect_to request value
 *
 * @bbPress (r4655)
 *
 * @return string The URL to redirect to, if set
 */
function bbp_get_redirect_to() {

	// Check 'redirect_to' request parameter
	$retval = ! empty( $_REQUEST['redirect_to'] )
		? $_REQUEST['redirect_to']
		: '';

	// Filter & return
	return apply_filters( 'bbp_get_redirect_to', $retval );
}

/**
 * Append 'view=all' to query string if it's already there from referer
 *
 * @since 2.0.0 bbPress (r3325)
 *
 * @param string $original_link Original Link to be modified
 * @param bool $force Override bbp_get_view_all() check
 * @return string The link with 'view=all' appended if necessary
 */
function bbp_add_view_all( $original_link = '', $force = false ) {

	// Are we appending the view=all vars?
	$link = ( bbp_get_view_all() || ! empty( $force ) )
		? add_query_arg( array( 'view' => 'all' ), $original_link )
		: $original_link;

	// Filter & return
	return apply_filters( 'bbp_add_view_all', $link, $original_link );
}

/**
 * Remove 'view=all' from query string
 *
 * @since 2.0.0 bbPress (r3325)
 *
 * @param string $original_link Original Link to be modified
 * @return string The link with 'view=all' appended if necessary
 */
function bbp_remove_view_all( $original_link = '' ) {

	// Remove `view' argument
	$link = remove_query_arg( 'view', $original_link );

	// Filter & return
	return apply_filters( 'bbp_remove_view_all', $link, $original_link );
}

/**
 * If current user can and is viewing all topics/replies
 *
 * @since 2.0.0 bbPress (r3325)
 *
 * @param string $cap Capability used to ensure user can view all
 *
 * @return bool Whether current user can and is viewing all
 */
function bbp_get_view_all( $cap = 'moderate' ) {
	$retval = ( ( ! empty( $_GET['view'] ) && ( 'all' === $_GET['view'] ) && current_user_can( $cap ) ) );

	// Filter & return
	return (bool) apply_filters( 'bbp_get_view_all', (bool) $retval, $cap );
}

/**
 * Assist pagination by returning correct page number
 *
 * @since 2.0.0 bbPress (r2628)
 *
 * @return int Current page number
 */
function bbp_get_paged() {
	$wp_query = bbp_get_wp_query();

	// Check the query var
	if ( get_query_var( 'paged' ) ) {
		$paged = get_query_var( 'paged' );

	// Check query paged
	} elseif ( ! empty( $wp_query->query['paged'] ) ) {
		$paged = $wp_query->query['paged'];
	}

	// Paged found
	if ( ! empty( $paged ) ) {
		return (int) $paged;
	}

	// Default to first page
	return 1;
}

/** Misc **********************************************************************/

/**
 * Return the unique non-empty values of an array.
 *
 * @since 2.6.0 bbPress (r6481)
 *
 * @param array $array Array to get values of
 *
 * @return array
 */
function bbp_get_unique_array_values( $array = array() ) {
	return array_unique( array_filter( array_values( $array ) ) );
}

/**
 * Fix post author id on post save
 *
 * When a logged in user changes the status of an anonymous reply or topic, or
 * edits it, the post_author field is set to the logged in user's id. This
 * function fixes that.
 *
 * @since 2.0.0 bbPress (r2734)
 *
 * @param array $data Post data
 * @param array $postarr Original post array (includes post id)
 * @return array Data
 */
function bbp_fix_post_author( $data = array(), $postarr = array() ) {

	// Post is not being updated or the post_author is already 0, return
	if ( empty( $postarr['ID'] ) || empty( $data['post_author'] ) ) {
		return $data;
	}

	// Post is not a topic or reply, return
	if ( ! in_array( $data['post_type'], array( bbp_get_topic_post_type(), bbp_get_reply_post_type() ), true ) ) {
		return $data;
	}

	// Is the post by an anonymous user?
	if ( ( bbp_get_topic_post_type() === $data['post_type'] && ! bbp_is_topic_anonymous( $postarr['ID'] ) ) ||
	     ( bbp_get_reply_post_type() === $data['post_type'] && ! bbp_is_reply_anonymous( $postarr['ID'] ) ) ) {
		return $data;
	}

	// The post is being updated. It is a topic or a reply and is written by an anonymous user.
	// Set the post_author back to 0
	$data['post_author'] = 0;

	return $data;
}

/**
 * Check a date against the length of time something can be edited.
 *
 * It is recommended to leave $utc set to true and to work with UTC/GMT dates.
 * Turning this off will use the WordPress offset which is likely undesirable.
 *
 * @since 2.0.0 bbPress (r3133)
 * @since 2.6.0 bbPress (r6868) Inverted some logic and added unit tests
 *
 * @param string  $datetime Gets run through strtotime()
 * @param boolean $utc      Default true. Is the timestamp in UTC?
 *
 * @return bool True by default, if date is past, or editing is disabled.
 */
function bbp_past_edit_lock( $datetime = '', $utc = true ) {

	// Default value
	$retval = true;

	// Check if date and editing is allowed
	if ( bbp_allow_content_edit() ) {

		// Get number of minutes to allow editing for
		$minutes = bbp_get_edit_lock();

		// 0 minutes means forever, so can never be past edit-lock time
		if ( 0 === $minutes ) {
			$retval = false;

		// Checking against a specific datetime
		} elseif ( ! empty( $datetime ) ) {

			// Period of time
			$lockable = "+{$minutes} minutes";
			if ( true === $utc ) {
				$lockable .= " UTC";
			}

			// Now
			$cur_time  = current_time( 'timestamp', $utc );

			// Get the duration in seconds
			$duration  = strtotime( $lockable ) - $cur_time;

			// Diff the times down to seconds
			$lock_time = strtotime( $lockable, $cur_time );
			$past_time = strtotime( $datetime, $cur_time );
			$diff_time = ( $lock_time - $past_time ) - $duration;

			// Check if less than lock time
			if ( $diff_time < $duration ) {
				$retval = false;
			}
		}
	}

	// Filter & return
	return (bool) apply_filters( 'bbp_past_edit_lock', $retval, $datetime, $utc );
}

/**
 * Get number of days something should remain trashed for before it is cleaned
 * up by WordPress Cron. If set to 0, items will skip trash and be deleted
 * immediately.
 *
 * @since 2.6.0 bbPress (r6424)
 *
 * @param string $context Provide context for additional filtering
 * @return int Number of days items remain in trash
 */
function bbp_get_trash_days( $context = 'forum' ) {

	// Sanitize the context
	$context = sanitize_key( $context );

	// Check the WordPress constant
	$days    = defined( 'EMPTY_TRASH_DAYS' )
		? (int) EMPTY_TRASH_DAYS
		: 30;

	// Filter & return
	return (int) apply_filters( 'bbp_get_trash_days', $days, $context );
}

/** Statistics ****************************************************************/

/**
 * Get the forum statistics
 *
 * @since 2.0.0 bbPress (r2769)
 * @since 2.6.0 bbPress (r6055) Introduced the `count_pending_topics` and
 *                               `count_pending_replies` arguments.
 *
 * @param array $args Optional. The function supports these arguments (all
 *                     default to true):
 *  - count_users: Count users?
 *  - count_forums: Count forums?
 *  - count_topics: Count topics? If set to false, private, spammed and trashed
 *                   topics are also not counted.
 *  - count_pending_topics: Count pending topics? (only counted if the current
 *                           user has edit_others_topics cap)
 *  - count_private_topics: Count private topics? (only counted if the current
 *                           user has read_private_topics cap)
 *  - count_spammed_topics: Count spammed topics? (only counted if the current
 *                           user has edit_others_topics cap)
 *  - count_trashed_topics: Count trashed topics? (only counted if the current
 *                           user has view_trash cap)
 *  - count_replies: Count replies? If set to false, private, spammed and
 *                   trashed replies are also not counted.
 *  - count_pending_replies: Count pending replies? (only counted if the current
 *                           user has edit_others_replies cap)
 *  - count_private_replies: Count private replies? (only counted if the current
 *                           user has read_private_replies cap)
 *  - count_spammed_replies: Count spammed replies? (only counted if the current
 *                           user has edit_others_replies cap)
 *  - count_trashed_replies: Count trashed replies? (only counted if the current
 *                           user has view_trash cap)
 *  - count_tags: Count tags? If set to false, empty tags are also not counted
 *  - count_empty_tags: Count empty tags?
 * @return object Walked forum tree
 */
function bbp_get_statistics( $args = array() ) {

	// Parse arguments against default values
	$r = bbp_parse_args( $args, array(

		// Users
		'count_users'           => true,

		// Forums
		'count_forums'          => true,

		// Topics
		'count_topics'          => true,
		'count_pending_topics'  => true,
		'count_private_topics'  => true,
		'count_spammed_topics'  => true,
		'count_trashed_topics'  => true,

		// Replies
		'count_replies'         => true,
		'count_pending_replies' => true,
		'count_private_replies' => true,
		'count_spammed_replies' => true,
		'count_trashed_replies' => true,

		// Topic tags
		'count_tags'            => true,
		'count_empty_tags'      => true

	), 'get_statistics' );

	// Defaults
	$topic_count     = $topic_count_hidden    = 0;
	$reply_count     = $reply_count_hidden    = 0;
	$topic_tag_count = $empty_topic_tag_count = 0;
	$hidden_topic_title = $hidden_reply_title = '';

	// Users
	$user_count = ! empty( $r['count_users'] )
		? bbp_get_total_users()
		: 0;

	// Forums
	$forum_count = ! empty( $r['count_forums'] )
		? wp_count_posts( bbp_get_forum_post_type() )->publish
		: 0;

	// Post statuses
	$pending = bbp_get_pending_status_id();
	$private = bbp_get_private_status_id();
	$spam    = bbp_get_spam_status_id();
	$trash   = bbp_get_trash_status_id();
	$closed  = bbp_get_closed_status_id();

	// Topics
	if ( ! empty( $r['count_topics'] ) ) {
		$all_topics  = wp_count_posts( bbp_get_topic_post_type() );

		// Published (publish + closed)
		$topic_count = $all_topics->publish + $all_topics->{$closed};

		if ( current_user_can( 'read_private_topics' ) || current_user_can( 'edit_others_topics' ) || current_user_can( 'view_trash' ) ) {

			// Declare empty arrays
			$topics = $topic_titles = array();

			// Pending
			$topics['pending'] = ( ! empty( $r['count_pending_topics'] ) && current_user_can( 'edit_others_topics' ) )
				? (int) $all_topics->{$pending}
				: 0;

			// Private
			$topics['private'] = ( ! empty( $r['count_private_topics'] ) && current_user_can( 'read_private_topics' ) )
				? (int) $all_topics->{$private}
				: 0;

			// Spam
			$topics['spammed'] = ( ! empty( $r['count_spammed_topics'] ) && current_user_can( 'edit_others_topics'  ) )
				? (int) $all_topics->{$spam}
				: 0;

			// Trash
			$topics['trashed'] = ( ! empty( $r['count_trashed_topics'] ) && current_user_can( 'view_trash' ) )
				? (int) $all_topics->{$trash}
				: 0;

			// Total hidden (pending + private + spam + trash)
			$topic_count_hidden = $topics['pending'] + $topics['private'] + $topics['spammed'] + $topics['trashed'];

			// Generate the hidden topic count's title attribute
			$topic_titles[] = ! empty( $topics['pending'] )
				? sprintf( esc_html__( 'Pending: %s', 'bbpress' ), bbp_number_format_i18n( $topics['pending'] ) )
				: '';

			$topic_titles[] = ! empty( $topics['private'] )
				? ''//sprintf( esc_html__( 'Private: %s', 'bbpress' ), bbp_number_format_i18n( $topics['private'] ) )
				: '';

			$topic_titles[] = ! empty( $topics['spammed'] )
				? sprintf( esc_html__( 'Spammed: %s', 'bbpress' ), bbp_number_format_i18n( $topics['spammed'] ) )
				: '';

			$topic_titles[] = ! empty( $topics['trashed'] )
				? sprintf( esc_html__( 'Trashed: %s', 'bbpress' ), bbp_number_format_i18n( $topics['trashed'] ) )
				: '';

			// Compile the hidden topic title
			$hidden_topic_title = implode( ' | ', array_filter( $topic_titles ) );
		}
	}

	// Replies
	if ( ! empty( $r['count_replies'] ) ) {

		$all_replies = wp_count_posts( bbp_get_reply_post_type() );

		// Published
		$reply_count = $all_replies->publish;

		if ( current_user_can( 'read_private_replies' ) || current_user_can( 'edit_others_replies' ) || current_user_can( 'view_trash' ) ) {

			// Declare empty arrays
			$replies = $reply_titles = array();

			// Pending
			$replies['pending'] = ( ! empty( $r['count_pending_replies'] ) && current_user_can( 'edit_others_replies' ) )
				? (int) $all_replies->{$pending}
				: 0;

			// Private
			$replies['private'] = ( ! empty( $r['count_private_replies'] ) && current_user_can( 'read_private_replies' ) )
				? (int) $all_replies->{$private}
				: 0;

			// Spam
			$replies['spammed'] = ( ! empty( $r['count_spammed_replies'] ) && current_user_can( 'edit_others_replies'  ) )
				? (int) $all_replies->{$spam}
				: 0;

			// Trash
			$replies['trashed'] = ( ! empty( $r['count_trashed_replies'] ) && current_user_can( 'view_trash' ) )
				? (int) $all_replies->{$trash}
				: 0;

			// Total hidden (pending + private + spam + trash)
			$reply_count_hidden = $replies['pending'] + $replies['private'] + $replies['spammed'] + $replies['trashed'];

			// Generate the hidden topic count's title attribute
			$reply_titles[] = ! empty( $replies['pending'] )
				? sprintf( esc_html__( 'Pending: %s', 'bbpress' ), bbp_number_format_i18n( $replies['pending'] ) )
				: '';

			$reply_titles[] = ! empty( $replies['private'] )
				? sprintf( esc_html__( 'Private: %s', 'bbpress' ), bbp_number_format_i18n( $replies['private'] ) )
				: '';

			$reply_titles[] = ! empty( $replies['spammed'] )
				? sprintf( esc_html__( 'Spammed: %s', 'bbpress' ), bbp_number_format_i18n( $replies['spammed'] ) )
				: '';

			$reply_titles[] = ! empty( $replies['trashed'] )
				? sprintf( esc_html__( 'Trashed: %s', 'bbpress' ), bbp_number_format_i18n( $replies['trashed'] ) )
				: '';

			// Compile the hidden replies title
			$hidden_reply_title = implode( ' | ', array_filter( $reply_titles ) );
		}
	}

	// Topic Tags
	if ( ! empty( $r['count_tags'] ) && bbp_allow_topic_tags() ) {

		// Get the count
		$topic_tag_count = wp_count_terms( bbp_get_topic_tag_tax_id(), array( 'hide_empty' => true ) );

		// Empty tags
		if ( ! empty( $r['count_empty_tags'] ) && current_user_can( 'edit_topic_tags' ) ) {
			$empty_topic_tag_count = wp_count_terms( bbp_get_topic_tag_tax_id() ) - $topic_tag_count;
		}
	}

	// Tally the tallies
	$counts = array_filter( array_map( 'absint', compact(
		'user_count',
		'forum_count',
		'topic_count',
		'topic_count_hidden',
		'reply_count',
		'reply_count_hidden',
		'topic_tag_count',
		'empty_topic_tag_count'
	) ) );

	// Define return value
	$statistics = array();

	// Loop through and store the integer and i18n formatted counts.
	foreach ( $counts as $key => $count ) {
		$statistics[ $key ]         = bbp_number_format_i18n( $count );
		$statistics[ "{$key}_int" ] = $count;
	}

	// Add the hidden (topic/reply) count title attribute strings because we
	// don't need to run the math functions on these (see above)
	$statistics['hidden_topic_title'] = $hidden_topic_title;
	$statistics['hidden_reply_title'] = $hidden_reply_title;

	// Filter & return
	return (array) apply_filters( 'bbp_get_statistics', $statistics, $r, $args );
}

/** New/edit topic/reply helpers **********************************************/

/**
 * Filter anonymous post data
 *
 * We use REMOTE_ADDR here directly. If you are behind a proxy, you should
 * ensure that it is properly set, such as in wp-config.php, for your
 * environment. See {@link https://core.trac.wordpress.org/ticket/9235}
 *
 * Note that bbp_pre_anonymous_filters() is responsible for sanitizing each
 * of the filtered core anonymous values here.
 *
 * If there are any errors, those are directly added to {@link bbPress:errors}
 *
 * @since 2.0.0 bbPress (r2734)
 *
 * @param array $args Optional. If no args are there, then $_POST values are
 * @return bool|array False on errors, values in an array on success
 */
function bbp_filter_anonymous_post_data( $args = array() ) {

	// Parse arguments against default values
	$r = bbp_parse_args( $args, array(
		'bbp_anonymous_name'    => ! empty( $_POST['bbp_anonymous_name']    ) ? $_POST['bbp_anonymous_name']    : false,
		'bbp_anonymous_email'   => ! empty( $_POST['bbp_anonymous_email']   ) ? $_POST['bbp_anonymous_email']   : false,
		'bbp_anonymous_website' => ! empty( $_POST['bbp_anonymous_website'] ) ? $_POST['bbp_anonymous_website'] : false,
	), 'filter_anonymous_post_data' );

	// Strip invalid characters
	$r = bbp_sanitize_anonymous_post_author( $r );

	// Filter name
	$r['bbp_anonymous_name'] = apply_filters( 'bbp_pre_anonymous_post_author_name', $r['bbp_anonymous_name'] );
	if ( empty( $r['bbp_anonymous_name'] ) ) {
		bbp_add_error( 'bbp_anonymous_name',  __( '<strong>ERROR</strong>: Invalid author name.', 'bbpress' ) );
	}

	// Filter email address
	$r['bbp_anonymous_email'] = apply_filters( 'bbp_pre_anonymous_post_author_email', $r['bbp_anonymous_email'] );
	if ( empty( $r['bbp_anonymous_email'] ) ) {
		bbp_add_error( 'bbp_anonymous_email', __( '<strong>ERROR</strong>: Invalid email address.', 'bbpress' ) );
	}

	// Website is optional (can be empty)
	$r['bbp_anonymous_website'] = apply_filters( 'bbp_pre_anonymous_post_author_website', $r['bbp_anonymous_website'] );

	// Filter & return
	return (array) apply_filters( 'bbp_filter_anonymous_post_data', $r, $args );
}

/**
 * Sanitize an array of anonymous post author data
 *
 * @since 2.6.0 bbPress (r6400)
 *
 * @param array $anonymous_data
 * @return array
 */
function bbp_sanitize_anonymous_post_author( $anonymous_data = array() ) {

	// Make sure anonymous data is an array
	if ( ! is_array( $anonymous_data ) ) {
		$anonymous_data = array();
	}

	// Map meta data to comment fields (as guides for stripping invalid text)
	$fields = array(
		'bbp_anonymous_name'    => 'comment_author',
		'bbp_anonymous_email'   => 'comment_author_email',
		'bbp_anonymous_website' => 'comment_author_url'
	);

	// Setup a new return array
	$r = $anonymous_data;

	// Get the database
	$bbp_db = bbp_db();

	// Strip invalid text from fields
	foreach ( $fields as $bbp_field => $comment_field ) {
		if ( ! empty( $r[ $bbp_field ] ) ) {
			$r[ $bbp_field ] = $bbp_db->strip_invalid_text_for_column( $bbp_db->comments, $comment_field, $r[ $bbp_field ] );
		}
	}

	// Filter & return
	return (array) apply_filters( 'bbp_sanitize_anonymous_post_author', $r, $anonymous_data );
}

/**
 * Update the relevant meta-data for an anonymous post author
 *
 * @since 2.6.0 bbPress (r6400)
 *
 * @param int    $post_id
 * @param array  $anonymous_data
 * @param string $post_type
 */
function bbp_update_anonymous_post_author( $post_id = 0, $anonymous_data = array(), $post_type = '' ) {

	// Maybe look for anonymous
	if ( empty( $anonymous_data ) ) {
		$anonymous_data = bbp_filter_anonymous_post_data();
	}

	// Sanitize parameters
	$post_id   = (int) $post_id;
	$post_type = sanitize_key( $post_type );

	// Bail if missing required data
	if ( empty( $post_id ) || empty( $post_type ) || empty( $anonymous_data ) ) {
		return;
	}

	// Parse arguments against default values
	$r = bbp_parse_args( $anonymous_data, array(
		'bbp_anonymous_name'    => '',
		'bbp_anonymous_email'   => '',
		'bbp_anonymous_website' => '',
	), "update_{$post_type}" );

	// Update all anonymous metas
	foreach ( $r as $anon_key => $anon_value ) {

		// Update, or delete if empty
		! empty( $anon_value )
			? update_post_meta( $post_id, '_' . $anon_key, (string) $anon_value, false )
			: delete_post_meta( $post_id, '_' . $anon_key );
	}
}

/**
 * Check for duplicate topics/replies
 *
 * Check to make sure that a user is not making a duplicate post
 *
 * @since 2.0.0 bbPress (r2763)
 *
 * @param array $post_data Contains information about the comment
 * @return bool True if it is not a duplicate, false if it is
 */
function bbp_check_for_duplicate( $post_data = array() ) {

	// Parse arguments against default values
	$r = bbp_parse_args( $post_data, array(
		'post_author'    => 0,
		'post_type'      => array( bbp_get_topic_post_type(), bbp_get_reply_post_type() ),
		'post_parent'    => 0,
		'post_content'   => '',
		'post_status'    => bbp_get_trash_status_id(),
		'anonymous_data' => array()
	), 'check_for_duplicate' );

	// No duplicate checks for those who can throttle
	if ( user_can( (int) $r['post_author'], 'throttle' ) ) {
		return true;
	}

	// Get the DB
	$bbp_db = bbp_db();

	// Default clauses
	$join = $where = '';

	// Check for anonymous post
	if ( empty( $r['post_author'] ) && ( ! empty( $r['anonymous_data'] ) && ! empty( $r['anonymous_data']['bbp_anonymous_email'] ) ) ) {

		// Sanitize the email address for querying
		$email = sanitize_email( $r['anonymous_data']['bbp_anonymous_email'] );

		// Only proceed
		if ( ! empty( $email ) && is_email( $email ) ) {

			// Get the meta SQL
			$clauses = get_meta_sql( array( array(
				'key'   => '_bbp_anonymous_email',
				'value' => $email,
			) ), 'post', $bbp_db->posts, 'ID' );

			// Set clauses
			$join  = $clauses['join'];

			// "'", "%", "$" and are valid characters in email addresses
			$where = $bbp_db->remove_placeholder_escape( $clauses['where'] );
		}
	}

	// Unslash $r to pass through DB->prepare()
	//
	// @see: https://bbpress.trac.wordpress.org/ticket/2185/
	// @see: https://core.trac.wordpress.org/changeset/23973/
	$r = wp_unslash( $r );

	// Prepare duplicate check query
	$query  = "SELECT ID FROM {$bbp_db->posts} {$join}";
	$query .= $bbp_db->prepare( "WHERE post_type = %s AND post_status != %s AND post_author = %d AND post_content = %s", $r['post_type'], $r['post_status'], $r['post_author'], $r['post_content'] );
	$query .= ! empty( $r['post_parent'] )
		? $bbp_db->prepare( " AND post_parent = %d", $r['post_parent'] )
		: '';
	$query .= $where;
	$query .= " LIMIT 1";
	$dupe   = apply_filters( 'bbp_check_for_duplicate_query', $query, $r );

	// Dupe found
	if ( $bbp_db->get_var( $dupe ) ) {
		do_action( 'bbp_check_for_duplicate_trigger', $post_data );
		return false;
	}

	// Dupe not found
	return true;
}

/**
 * Check for flooding
 *
 * Check to make sure that a user is not making too many posts in a short amount
 * of time.
 *
 * @since 2.0.0 bbPress (r2734)
 *
 * @param array $anonymous_data Optional - if it's an anonymous post. Do not
 *                              supply if supplying $author_id. Should be
 *                              sanitized (see {@link bbp_filter_anonymous_post_data()}
 * @param int $author_id Optional. Supply if it's a post by a logged in user.
 *                        Do not supply if supplying $anonymous_data.
 * @return bool True if there is no flooding, false if there is
 */
function bbp_check_for_flood( $anonymous_data = array(), $author_id = 0 ) {

	// Allow for flood check to be skipped
	if ( apply_filters( 'bbp_bypass_check_for_flood', false, $anonymous_data, $author_id ) ) {
		return true;
	}

	// Option disabled. No flood checks.
	$throttle_time = get_option( '_bbp_throttle_time' );
	if ( empty( $throttle_time ) || ! bbp_allow_content_throttle() ) {
		return true;
	}

	// User is anonymous, so check a transient based on the IP
	if ( ! empty( $anonymous_data ) ) {
		$last_posted = get_transient( '_bbp_' . bbp_current_author_ip() . '_last_posted' );

		if ( ! empty( $last_posted ) && ( time() < ( $last_posted + $throttle_time ) ) ) {
			return false;
		}

	// User is logged in, so check their last posted time
	} elseif ( ! empty( $author_id ) ) {
		$author_id   = (int) $author_id;
		$last_posted = bbp_get_user_last_posted( $author_id );

		if ( ! empty( $last_posted ) && ( time() < ( $last_posted + $throttle_time ) ) && ! user_can( $author_id, 'throttle' ) ) {
			return false;
		}
	} else {
		return false;
	}

	return true;
}

/**
 * Checks topics and replies against the discussion moderation of blocked keys
 *
 * @since 2.1.0 bbPress (r3581)
 *
 * @param array $anonymous_data Optional - if it's an anonymous post. Do not
 *                              supply if supplying $author_id. Should be
 *                              sanitized (see {@link bbp_filter_anonymous_post_data()}
 * @param int $author_id Topic or reply author ID
 * @param string $title The title of the content
 * @param string $content The content being posted
 * @param mixed  $strict  False for moderation_keys. True for blacklist_keys.
 *                        String for custom keys.
 * @return bool True if test is passed, false if fail
 */
function bbp_check_for_moderation( $anonymous_data = array(), $author_id = 0, $title = '', $content = '', $strict = false ) {

	// Custom moderation option key
	if ( is_string( $strict ) ) {
		$strict = sanitize_key( $strict );

		// Use custom key
		if ( ! empty( $strict ) ) {
			$hook_name   = $strict;
			$option_name = "{$strict}_keys";

		// Key was invalid, so default to moderation keys
		} else {
			$strict = false;
		}
	}

	// Strict mode uses WordPress "blacklist" settings
	if ( true === $strict ) {
		$hook_name   = 'blacklist';
		$option_name = 'blacklist_keys';

	// Non-strict uses WordPress "moderation" settings
	} elseif ( false === $strict ) {
		$hook_name   = 'moderation';
		$option_name = 'moderation_keys';
	}

	// Allow for moderation check to be skipped
	if ( apply_filters( "bbp_bypass_check_for_{$hook_name}", false, $anonymous_data, $author_id, $title, $content, $strict ) ) {
		return true;
	}

	// Maybe perform some author-specific capability checks
	if ( ! empty( $author_id ) ) {

		// Bail if user is a keymaster
		if ( bbp_is_user_keymaster( $author_id ) ) {
			return true;

		// Bail if user can moderate
		// https://bbpress.trac.wordpress.org/ticket/2726
		} elseif ( ( false === $strict ) && user_can( $author_id, 'moderate' ) ) {
			return true;
		}
	}

	// Define local variable(s)
	$_post     = array();
	$match_out = '';

	/** Max Links *************************************************************/

	// Only check max_links when not being strict
	if ( false === $strict ) {
		$max_links = get_option( 'comment_max_links' );
		if ( ! empty( $max_links ) ) {

			// How many links?
			$num_links = preg_match_all( '/(http|ftp|https):\/\//i', $content, $match_out );

			// Allow for bumping the max to include the user's URL
			if ( ! empty( $_post['url'] ) ) {
				$num_links = apply_filters( 'comment_max_links_url', $num_links, $_post['url'], $content );
			}

			// Das ist zu viele links!
			if ( $num_links >= $max_links ) {
				return false;
			}
		}
	}

	/** Moderation ************************************************************/

	/**
	 * Filters the bbPress moderation keys.
	 *
	 * @since 2.6.0 bbPress (r6050)
	 *
	 * @param string $moderation List of moderation keys. One per new line.
	 */
	$moderation = apply_filters( "bbp_{$hook_name}_keys", trim( get_option( $option_name ) ) );

	// Bail if no words to look for
	if ( empty( $moderation ) ) {
		return true;
	}

	/** User Data *************************************************************/

	// Map anonymous user data
	if ( ! empty( $anonymous_data ) ) {
		$_post['author'] = $anonymous_data['bbp_anonymous_name'];
		$_post['email']  = $anonymous_data['bbp_anonymous_email'];
		$_post['url']    = $anonymous_data['bbp_anonymous_website'];

	// Map current user data
	} elseif ( ! empty( $author_id ) ) {

		// Get author data
		$user = get_userdata( $author_id );

		// If data exists, map it
		if ( ! empty( $user ) ) {
			$_post['author'] = $user->display_name;
			$_post['email']  = $user->user_email;
			$_post['url']    = $user->user_url;
		}
	}

	// Current user IP and user agent
	$_post['user_ip'] = bbp_current_author_ip();
	$_post['user_ua'] = bbp_current_author_ua();

	// Post title and content
	$_post['title']   = $title;
	$_post['content'] = $content;

	// Ensure HTML tags are not being used to bypass the moderation list.
	$_post['comment_without_html'] = wp_strip_all_tags( $content );

	/** Words *****************************************************************/

	// Get words separated by new lines
	$words = explode( "\n", $moderation );

	// Loop through words
	foreach ( (array) $words as $word ) {

		// Trim the whitespace from the word
		$word = trim( $word );

		// Skip empty lines
		if ( empty( $word ) ) {
			continue;
		}

		// Do some escaping magic so that '#' chars in the
		// spam words don't break things:
		$word    = preg_quote( $word, '#' );
		$pattern = "#{$word}#i";

		// Loop through post data
		foreach ( $_post as $post_data ) {

			// Check each user data for current word
			if ( preg_match( $pattern, $post_data ) ) {

				// Post does not pass
				return false;
			}
		}
	}

	// Check passed successfully
	return true;
}

/**
 * Deprecated. Use bbp_check_for_moderation() with strict flag set.
 *
 * @since 2.0.0 bbPress (r3446)
 * @since 2.6.0 bbPress (r6854)
 * @deprecated 2.6.0 Use bbp_check_for_moderation() with strict flag set
 */
function bbp_check_for_blacklist( $anonymous_data = array(), $author_id = 0, $title = '', $content = '' ) {
	return bbp_check_for_moderation( $anonymous_data, $author_id, $title, $content, true );
}

/** Subscriptions *************************************************************/

/**
 * Get the "Do Not Reply" email address to use when sending subscription emails.
 *
 * We make some educated guesses here based on the home URL. Filters are
 * available to customize this address further. In the future, we may consider
 * using `admin_email` instead, though this is not normally publicized.
 *
 * We use `$_SERVER['SERVER_NAME']` here to mimic similar functionality in
 * WordPress core. Previously, we used `get_home_url()` to use already validated
 * user input, but it was causing issues in some installations.
 *
 * @since 2.6.0 bbPress (r5409)
 *
 * @see  wp_mail
 * @see  wp_notify_postauthor
 * @link https://bbpress.trac.wordpress.org/ticket/2618
 *
 * @return string
 */
function bbp_get_do_not_reply_address() {
	$sitename = strtolower( $_SERVER['SERVER_NAME'] );
	if ( substr( $sitename, 0, 4 ) === 'www.' ) {
		$sitename = substr( $sitename, 4 );
	}

	// Filter & return
	return apply_filters( 'bbp_get_do_not_reply_address', 'noreply@' . $sitename );
}

/**
 * Sends notification emails for new replies to subscribed topics
 *
 * Gets new post ID and check if there are subscribed users to that topic, and
 * if there are, send notifications
 *
 * Note: in bbPress 2.6, we've moved away from 1 email per subscriber to 1 email
 * with everyone BCC'd. This may have negative repercussions for email services
 * that limit the number of addresses in a BCC field (often to around 500.) In
 * those cases, we recommend unhooking this function and creating your own
 * custom email script.
 *
 * @since 2.6.0 bbPress (r5413)
 *
 * @param int $reply_id ID of the newly made reply
 * @param int $topic_id ID of the topic of the reply
 * @param int $forum_id ID of the forum of the reply
 * @param array $anonymous_data Optional - if it's an anonymous post. Do not
 *                              supply if supplying $author_id. Should be
 *                              sanitized (see {@link bbp_filter_anonymous_post_data()}
 * @param int $reply_author ID of the topic author ID
 * @return bool True on success, false on failure
 */
function bbp_notify_topic_subscribers( $reply_id = 0, $topic_id = 0, $forum_id = 0, $anonymous_data = array(), $reply_author = 0 ) {

	// Bail if subscriptions are turned off
	if ( ! bbp_is_subscriptions_active() ) {
		return false;
	}

	// Bail if importing
	if ( defined( 'WP_IMPORTING' ) && WP_IMPORTING ) {
		return false;
	}

	/** Validation ************************************************************/

	$reply_id = bbp_get_reply_id( $reply_id );
	$topic_id = bbp_get_topic_id( $topic_id );
	$forum_id = bbp_get_forum_id( $forum_id );

	/** Topic *****************************************************************/

	// Bail if topic is not public (includes closed)
	if ( ! bbp_is_topic_public( $topic_id ) ) {
		return false;
	}

	/** Reply *****************************************************************/

	// Bail if reply is not published
	if ( ! bbp_is_reply_published( $reply_id ) ) {
		return false;
	}

	// Poster name
	$reply_author_name = bbp_get_reply_author_display_name( $reply_id );

	/** Users *****************************************************************/

	// Get topic subscribers and bail if empty
	$user_ids = bbp_get_subscribers( $topic_id );

	// Remove the reply author from the list.
	$reply_author_key = array_search( (int) $reply_author, $user_ids, true );
	if ( false !== $reply_author_key ) {
		unset( $user_ids[ $reply_author_key ] );
	}

	// Dedicated filter to manipulate user ID's to send emails to
	$user_ids = (array) apply_filters( 'bbp_topic_subscription_user_ids', $user_ids, $reply_id, $topic_id );

	// Bail of the reply author was the only one subscribed.
	if ( empty( $user_ids ) ) {
		return false;
	}

	// Get email addresses, bail if empty
	$email_addresses = bbp_get_email_addresses_from_user_ids( $user_ids );
	if ( empty( $email_addresses ) ) {
		return false;
	}

	/** Mail ******************************************************************/

	// Remove filters from reply content and topic title to prevent content
	// from being encoded with HTML entities, wrapped in paragraph tags, etc...
	bbp_remove_all_filters( 'bbp_get_reply_content' );
	bbp_remove_all_filters( 'bbp_get_topic_title'   );
	bbp_remove_all_filters( 'the_title'             );

	// Strip tags from text and setup mail data
	$blog_name         = wp_specialchars_decode( get_option( 'blogname' ), ENT_QUOTES );
	$topic_title       = wp_specialchars_decode( strip_tags( bbp_get_topic_title( $topic_id ) ), ENT_QUOTES );
	$reply_author_name = wp_specialchars_decode( strip_tags( $reply_author_name ), ENT_QUOTES );
	$reply_content     = wp_specialchars_decode( strip_tags( bbp_get_reply_content( $reply_id ) ), ENT_QUOTES );
	$reply_url         = bbp_get_reply_url( $reply_id );

	// For plugins to filter messages per reply/topic/user
	$message = sprintf( esc_html__( '%1$s wrote:

%2$s

Post Link: %3$s

-----------

You are receiving this email because you subscribed to a forum topic.

Login and visit the topic to unsubscribe from these emails.', 'bbpress' ),

		$reply_author_name,
		$reply_content,
		$reply_url
	);

	$message = apply_filters( 'bbp_subscription_mail_message', $message, $reply_id, $topic_id );
	if ( empty( $message ) ) {
		return;
	}

	// For plugins to filter titles per reply/topic/user
	$subject = apply_filters( 'bbp_subscription_mail_title', '[' . $blog_name . '] ' . $topic_title, $reply_id, $topic_id );
	if ( empty( $subject ) ) {
		return;
	}

	/** Headers ***************************************************************/

	// Default bbPress X-header
	$headers    = array( bbp_get_email_header() );

	// Get the noreply@ address
	$no_reply   = bbp_get_do_not_reply_address();

	// Setup "From" email address
	$from_email = apply_filters( 'bbp_subscription_from_email', $no_reply );

	// Setup the From header
	$headers[]  = 'From: ' . get_bloginfo( 'name' ) . ' <' . $from_email . '>';

	// Loop through addresses
	foreach ( (array) $email_addresses as $address ) {
		$headers[] = 'Bcc: ' . $address;
	}

	/** Send it ***************************************************************/

	// Custom headers
	$headers  = apply_filters( 'bbp_subscription_mail_headers', $headers  );
 	$to_email = apply_filters( 'bbp_subscription_to_email',     $no_reply );

	// Before
	do_action( 'bbp_pre_notify_subscribers', $reply_id, $topic_id, $user_ids );

	// Send notification email
	wp_mail( $to_email, $subject, $message, $headers );

	// After
	do_action( 'bbp_post_notify_subscribers', $reply_id, $topic_id, $user_ids );

	// Restore previously removed filters
	bbp_restore_all_filters( 'bbp_get_topic_content' );
	bbp_restore_all_filters( 'bbp_get_topic_title'   );
	bbp_restore_all_filters( 'the_title'             );

	return true;
}

/**
 * Sends notification emails for new topics to subscribed forums
 *
 * Gets new post ID and check if there are subscribed users to that forum, and
 * if there are, send notifications
 *
 * Note: in bbPress 2.6, we've moved away from 1 email per subscriber to 1 email
 * with everyone BCC'd. This may have negative repercussions for email services
 * that limit the number of addresses in a BCC field (often to around 500.) In
 * those cases, we recommend unhooking this function and creating your own
 * custom email script.
 *
 * @since 2.5.0 bbPress (r5156)
 *
 * @param int $topic_id ID of the newly made reply
 * @param int $forum_id ID of the forum for the topic
 * @param array $anonymous_data Optional - if it's an anonymous post. Do not
 *                              supply if supplying $author_id. Should be
 *                              sanitized (see {@link bbp_filter_anonymous_post_data()}
 * @param int $topic_author ID of the topic author ID
 * @return bool True on success, false on failure
 */
function bbp_notify_forum_subscribers( $topic_id = 0, $forum_id = 0, $anonymous_data = array(), $topic_author = 0 ) {

	// Bail if subscriptions are turned off
	if ( ! bbp_is_subscriptions_active() ) {
		return false;
	}

	// Bail if importing
	if ( defined( 'WP_IMPORTING' ) && WP_IMPORTING ) {
		return false;
	}

	/** Validation ************************************************************/

	$topic_id = bbp_get_topic_id( $topic_id );
	$forum_id = bbp_get_forum_id( $forum_id );

	/**
	 * Necessary for backwards compatibility
	 *
	 * @see https://bbpress.trac.wordpress.org/ticket/2620
	 */
	$user_id  = 0;

	/** Topic *****************************************************************/

	// Bail if topic is not public (includes closed)
	if ( ! bbp_is_topic_public( $topic_id ) ) {
		return false;
	}

	// Poster name
	$topic_author_name = bbp_get_topic_author_display_name( $topic_id );

	/** Users *****************************************************************/

	// Get topic subscribers and bail if empty
	$user_ids = bbp_get_subscribers( $forum_id );

	// Remove the topic author from the list.
	$topic_author_key = array_search( (int) $topic_author, $user_ids, true );
	if ( false !== $topic_author_key ) {
		unset( $user_ids[ $topic_author_key ] );
	}

	// Dedicated filter to manipulate user ID's to send emails to
	$user_ids = (array) apply_filters( 'bbp_forum_subscription_user_ids', $user_ids, $topic_id, $forum_id );

	// Bail of the reply author was the only one subscribed.
	if ( empty( $user_ids ) ) {
		return false;
	}

	// Get email addresses, bail if empty
	$email_addresses = bbp_get_email_addresses_from_user_ids( $user_ids );
	if ( empty( $email_addresses ) ) {
		return false;
	}

	/** Mail ******************************************************************/

	// Remove filters from reply content and topic title to prevent content
	// from being encoded with HTML entities, wrapped in paragraph tags, etc...
	bbp_remove_all_filters( 'bbp_get_topic_content' );
	bbp_remove_all_filters( 'bbp_get_topic_title'   );
	bbp_remove_all_filters( 'the_title'             );

	// Strip tags from text and setup mail data
	$blog_name         = wp_specialchars_decode( get_option( 'blogname' ), ENT_QUOTES );
	$topic_title       = wp_specialchars_decode( strip_tags( bbp_get_topic_title( $topic_id ) ), ENT_QUOTES );
	$topic_author_name = wp_specialchars_decode( strip_tags( $topic_author_name ), ENT_QUOTES );
	$topic_content     = wp_specialchars_decode( strip_tags( bbp_get_topic_content( $topic_id ) ), ENT_QUOTES );
	$topic_url         = get_permalink( $topic_id );

	// For plugins to filter messages per reply/topic/user
	$message = sprintf( esc_html__( '%1$s wrote:

%2$s

Topic Link: %3$s

-----------

You are receiving this email because you subscribed to a forum.

Login and visit the topic to unsubscribe from these emails.', 'bbpress' ),

		$topic_author_name,
		$topic_content,
		$topic_url
	);

	$message = apply_filters( 'bbp_forum_subscription_mail_message', $message, $topic_id, $forum_id, $user_id );
	if ( empty( $message ) ) {
		return;
	}

	// For plugins to filter titles per reply/topic/user
	$subject = apply_filters( 'bbp_forum_subscription_mail_title', '[' . $blog_name . '] ' . $topic_title, $topic_id, $forum_id, $user_id );
	if ( empty( $subject ) ) {
		return;
	}

	/** Headers ***************************************************************/

	// Default bbPress X-header
	$headers    = array( bbp_get_email_header() );

	// Get the noreply@ address
	$no_reply   = bbp_get_do_not_reply_address();

	// Setup "From" email address
	$from_email = apply_filters( 'bbp_subscription_from_email', $no_reply );

	// Setup the From header
	$headers[] = 'From: ' . get_bloginfo( 'name' ) . ' <' . $from_email . '>';

	// Loop through addresses
	foreach ( (array) $email_addresses as $address ) {
		$headers[] = 'Bcc: ' . $address;
	}

	/** Send it ***************************************************************/

	// Custom headers
	$headers  = apply_filters( 'bbp_subscription_mail_headers', $headers  );
	$to_email = apply_filters( 'bbp_subscription_to_email',     $no_reply );

	// Before
	do_action( 'bbp_pre_notify_forum_subscribers', $topic_id, $forum_id, $user_ids );

	// Send notification email
	wp_mail( $to_email, $subject, $message, $headers );

	// After
	do_action( 'bbp_post_notify_forum_subscribers', $topic_id, $forum_id, $user_ids );

	// Restore previously removed filters
	bbp_restore_all_filters( 'bbp_get_topic_content' );
	bbp_restore_all_filters( 'bbp_get_topic_title'   );
	bbp_restore_all_filters( 'the_title'             );

	return true;
}

/**
 * Sends notification emails for new replies to subscribed topics
 *
 * This function is deprecated. Please use: bbp_notify_topic_subscribers()
 *
 * @since 2.0.0 bbPress (r2668)
 *
 * @deprecated 2.6.0 bbPress (r5412)
 *
 * @param int $reply_id ID of the newly made reply
 * @param int $topic_id ID of the topic of the reply
 * @param int $forum_id ID of the forum of the reply
 * @param array $anonymous_data Optional - if it's an anonymous post. Do not
 *                              supply if supplying $author_id. Should be
 *                              sanitized (see {@link bbp_filter_anonymous_post_data()}
 * @param int $reply_author ID of the topic author ID
 *
 * @return bool True on success, false on failure
 */
function bbp_notify_subscribers( $reply_id = 0, $topic_id = 0, $forum_id = 0, $anonymous_data = array(), $reply_author = 0 ) {
	return bbp_notify_topic_subscribers( $reply_id, $topic_id, $forum_id, $anonymous_data, $reply_author );
}

/**
 * Return an array of user email addresses from an array of user IDs
 *
 * @since 2.6.0 bbPress (r6722)
 *
 * @param array $user_ids
 * @return array
 */
function bbp_get_email_addresses_from_user_ids( $user_ids = array() ) {

	// Default return value
	$retval = array();

	// Maximum number of users to get per database query
	$limit = apply_filters( 'bbp_get_users_chunk_limit', 100 );

	// Only do the work if there are user IDs to query for
	if ( ! empty( $user_ids ) ) {

		// Get total number of sets
		$steps = ceil( count( $user_ids ) / $limit );
		$range = array_map( 'intval', range( 1, $steps ) );

		// Loop through users
		foreach ( $range as $loop ) {

			// Initial loop has no offset
			$offset = ( 1 === $loop )
				? 0
				: $limit * $loop;

			// Calculate user IDs to include
			$loop_ids = array_slice( $user_ids, $offset, $limit );

			// Skip if something went wrong
			if ( empty( $loop_ids ) ) {
				continue;
			}

			// Call get_users() in a way that users are cached
			$loop_users = get_users( array(
				'blog_id' => 0,
				'fields'  => 'all_with_meta',
				'include' => $loop_ids
			) );

			// Pluck emails from users
			$loop_emails = wp_list_pluck( $loop_users, 'user_email' );

			// Clean-up memory, for big user sets
			unset( $loop_users );

			// Merge users into return value
			if ( ! empty( $loop_emails ) ) {
				$retval = array_merge( $retval, $loop_emails );
			}
		}

		// No duplicates
		$retval = bbp_get_unique_array_values( $retval );
	}

	// Filter & return
	return apply_filters( 'bbp_get_email_addresses_from_user_ids', $retval, $user_ids, $limit );
}

/**
 * Automatically splits bbPress emails with many Bcc recipients into chunks.
 *
 * This middleware is useful because topics and forums with many subscribers
 * run into problems with Bcc limits, and many hosting companies & third-party
 * services limit the size of a Bcc audience to prevent spamming.
 *
 * The default "chunk" size is 40 users per iteration, and can be filtered if
 * desired. A future version of bbPress will introduce a setting to more easily
 * tune this.
 *
 * @since 2.6.0 bbPress (r6918)
 *
 * @param array $args Original arguments passed to wp_mail().
 * @return array
 */
function bbp_chunk_emails( $args = array() ) {

	// Get the maximum number of Bcc's per chunk
	$max_num = apply_filters( 'bbp_get_bcc_chunk_limit', 40, $args );

	// Look for "bcc: " in a case-insensitive way, and split into 2 sets
	$match       = '/^bcc: (\w+)/i';
	$old_headers = preg_grep( $match, $args['headers'], PREG_GREP_INVERT );
	$bcc_headers = preg_grep( $match, $args['headers'] );

	// Bail if less than $max_num recipients
	if ( empty( $bcc_headers ) || ( count( $bcc_headers ) < $max_num ) ) {
		return $args;
	}

	// Reindex the headers arrays
	$old_headers = array_values( $old_headers );
	$bcc_headers = array_values( $bcc_headers );

	// Break the Bcc emails into chunks
	foreach ( array_chunk( $bcc_headers, $max_num ) as $i => $chunk ) {

		// Skip the first chunk (it will get used in the original wp_mail() call)
		if ( 0 === $i ) {
			$first_chunk = $chunk;
			continue;
		}

		// Send out the chunk
		$chunk_headers = array_merge( $old_headers, $chunk );

		// Recursion alert, but should be OK!
		wp_mail(
			$args['to'],
			$args['subject'],
			$args['message'],
			$chunk_headers,
			$args['attachments']
		);
	}

	// Set headers to old headers + the $first_chunk of Bcc's
	$args['headers'] = array_merge( $old_headers, $first_chunk );

	// Return the reduced args, with the first chunk of Bcc's
	return $args;
}

/**
 * Return the string used for the bbPress specific X-header.
 *
 * @since 2.6.0 bbPress (r6919)
 *
 * @return string
 */
function bbp_get_email_header() {
	return apply_filters( 'bbp_get_email_header', 'X-bbPress: ' . bbp_get_version() );
}

/** Login *********************************************************************/

/**
 * Return a clean and reliable logout URL
 *
 * This function is used to filter `logout_url`. If no $redirect_to value is
 * passed, it will default to the request uri, then the forum root.
 *
 * See: `wp_logout_url()`
 *
 * @since 2.1.0 bbPress (2815)
 *
 * @param string $url URL used to log out
 * @param string $redirect_to Where to redirect to?
 *
 * @return string The url
 */
function bbp_logout_url( $url = '', $redirect_to = '' ) {

	// If there is no redirect in the URL, let's add one...
	if ( ! strstr( $url, 'redirect_to' ) ) {

		// Get the forum root, to maybe use as a default
		$forum_root = bbp_get_root_url();

		// No redirect passed, so check referer and fallback to request uri
		if ( empty( $redirect_to ) ) {

			// Check for a valid referer
			$redirect_to = wp_get_referer();

			// Fallback to request uri if invalid referer
			if ( false === $redirect_to ) {
				$redirect_to = bbp_get_url_scheme() . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
			}
		}

		// Filter the $redirect_to destination
		$filtered  = apply_filters( 'bbp_logout_url_redirect_to', $redirect_to );

		// Validate $redirect_to, default to root
		$validated = wp_validate_redirect( $filtered, $forum_root );

		// Assemble $redirect_to and add it (encoded) to full $url
		$appended  = add_query_arg( array( 'loggedout'   => 'true'   ), $validated );
		$encoded   = urlencode( $appended );
		$url       = add_query_arg( array( 'redirect_to' => $encoded ), $url       );
	}

	// Filter & return
	return apply_filters( 'bbp_logout_url', $url, $redirect_to );
}

/** Queries *******************************************************************/

/**
 * Merge user defined arguments into defaults array.
 *
 * This function is used throughout bbPress to allow for either a string or array
 * to be merged into another array. It is identical to wp_parse_args() except
 * it allows for arguments to be passively or aggressively filtered using the
 * optional $filter_key parameter.
 *
 * @since 2.1.0 bbPress (r3839)
 *
 * @param string|array $args Value to merge with $defaults
 * @param array $defaults Array that serves as the defaults.
 * @param string $filter_key String to key the filters from
 * @return array Merged user defined values with defaults.
 */
function bbp_parse_args( $args, $defaults = array(), $filter_key = '' ) {

	// Setup a temporary array from $args
	if ( is_object( $args ) ) {
		$r = get_object_vars( $args );
	} elseif ( is_array( $args ) ) {
		$r =& $args;
	} else {
		wp_parse_str( $args, $r );
	}

	// Passively filter the args before the parse
	if ( ! empty( $filter_key ) ) {
		$r = apply_filters( "bbp_before_{$filter_key}_parse_args", $r, $args, $defaults );
	}

	// Parse
	if ( is_array( $defaults ) && ! empty( $defaults ) ) {
		$r = array_merge( $defaults, $r );
	}

	// Aggressively filter the args after the parse
	if ( ! empty( $filter_key ) ) {
		$r = apply_filters( "bbp_after_{$filter_key}_parse_args", $r, $args, $defaults );
	}

	// Return the parsed results
	return $r;
}

/**
 * Adds ability to include or exclude specific post_parent ID's
 *
 * @since 2.0.0 bbPress (r2996)
 *
 * @deprecated 2.5.8 bbPress (r5814)
 *
 * @global WP $wp
 * @param string $where
 * @param WP_Query $object
 * @return string
 */
function bbp_query_post_parent__in( $where, $object = '' ) {
	global $wp;

	// Noop if WP core supports this already
	if ( in_array( 'post_parent__in', $wp->private_query_vars, true ) ) {
		return $where;
	}

	// Bail if no object passed
	if ( empty( $object ) ) {
		return $where;
	}

	// Only 1 post_parent so return $where
	if ( is_numeric( $object->query_vars['post_parent'] ) ) {
		return $where;
	}

	// Get the DB
	$bbp_db = bbp_db();

	// Including specific post_parent's
	if ( ! empty( $object->query_vars['post_parent__in'] ) ) {
		$ids    = implode( ',', wp_parse_id_list( $object->query_vars['post_parent__in'] ) );
		$where .= " AND {$bbp_db->posts}.post_parent IN ($ids)";

	// Excluding specific post_parent's
	} elseif ( ! empty( $object->query_vars['post_parent__not_in'] ) ) {
		$ids    = implode( ',', wp_parse_id_list( $object->query_vars['post_parent__not_in'] ) );
		$where .= " AND {$bbp_db->posts}.post_parent NOT IN ($ids)";
	}

	// Return possibly modified $where
	return $where;
}

/**
 * Query the DB and get the last public post_id that has parent_id as post_parent
 *
 * @since 2.0.0 bbPress (r2868)
 * @since 2.6.0 bbPress (r5954) Replace direct queries with WP_Query() objects
 *
 * @param int    $parent_id Parent id.
 * @param string $post_type Post type. Defaults to 'post'.
 * @return int The last active post_id
 */
function bbp_get_public_child_last_id( $parent_id = 0, $post_type = 'post' ) {

	// Bail if nothing passed
	if ( empty( $parent_id ) ) {
		return false;
	}

	// Which statuses
	switch ( $post_type ) {

		// Forum
		case bbp_get_forum_post_type() :
			$post_status = bbp_get_public_forum_statuses();
			break;

		// Topic
		case bbp_get_topic_post_type() :
			$post_status = bbp_get_public_topic_statuses();
			break;

		// Reply
		case bbp_get_reply_post_type() :
		default :
			$post_status = bbp_get_public_reply_statuses();
			break;
	}

	$query = new WP_Query( array(
		'fields'         => 'ids',
		'post_parent'    => $parent_id,
		'post_status'    => $post_status,
		'post_type'      => $post_type,
		'posts_per_page' => 1,
		'orderby'        => array(
			'post_date' => 'DESC',
			'ID'        => 'DESC'
		),

		// Performance
		'suppress_filters'       => true,
		'update_post_term_cache' => false,
		'update_post_meta_cache' => false,
		'ignore_sticky_posts'    => true,
		'no_found_rows'          => true
	) );
	$child_id = array_shift( $query->posts );
	unset( $query );

	// Filter & return
	return (int) apply_filters( 'bbp_get_public_child_last_id', $child_id, $parent_id, $post_type );
}

/**
 * Query the database for child counts, grouped by type & status
 *
 * @since 2.6.0 bbPress (r6826)
 *
 * @param int $parent_id
 */
function bbp_get_child_counts( $parent_id = 0 ) {

	// Create cache key
	$parent_id    = absint( $parent_id );
	$key          = md5( serialize( array( 'parent_id' => $parent_id, 'post_type' => bbp_get_post_types() ) ) );
	$last_changed = wp_cache_get_last_changed( 'bbpress_posts' );
	$cache_key    = "bbp_child_counts:{$key}:{$last_changed}";

	// Check for cache and set if needed
	$retval = wp_cache_get( $cache_key, 'bbpress_posts' );
	if ( false === $retval ) {

		// Setup the DB & query
		$bbp_db = bbp_db();
		$sql    = "SELECT
						p.post_type AS type,
						p.post_status AS status,
						COUNT( * ) AS count
					FROM {$bbp_db->posts} AS p
						LEFT JOIN {$bbp_db->postmeta} AS pm
							ON p.ID = pm.post_id
							AND pm.meta_key = %s
					WHERE pm.meta_value = %d
					GROUP BY p.post_status, p.post_type";

		// Get prepare vars
		$post_type = get_post_type( $parent_id );
		$meta_key  = "_bbp_{$post_type}_id";

		// Prepare & get results
		$query     = $bbp_db->prepare( $sql, $meta_key, $parent_id );
		$results   = $bbp_db->get_results( $query, ARRAY_A );

		// Setup return value
		$retval    = wp_list_pluck( $results, 'type', 'type' );
		$statuses  = get_post_stati();

		// Loop through results
		foreach ( $results as $row ) {

			// Setup empties
			if ( ! is_array( $retval[ $row['type'] ] ) ) {
				$retval[ $row['type'] ] = array_fill_keys( $statuses, 0 );
			}

			// Set statuses
			$retval[ $row['type'] ][ $row['status'] ] = bbp_number_not_negative( $row['count'] );
		}

		// Always cache the results
		wp_cache_set( $cache_key, $retval, 'bbpress_posts' );
	}

	// Make sure results are INTs
	return (array) apply_filters( 'bbp_get_child_counts', $retval, $parent_id );
}

/**
 * Filter a list of child counts, from `bbp_get_child_counts()`
 *
 * @since 2.6.0 bbPress (r6826)
 *
 * @param int    $parent_id  ID of post to get child counts from
 * @param array  $types      Optional. An array of post types to filter by
 * @param array  $statuses   Optional. An array of post statuses to filter by
 *
 * @return array A list of objects or object fields.
 */
function bbp_filter_child_counts_list( $parent_id = 0, $types = array( 'post' ), $statuses = array() ) {

	// Setup local vars
	$retval   = array();
	$types    = array_flip( (array) $types    );
	$statuses = array_flip( (array) $statuses );
	$counts   = bbp_get_child_counts( $parent_id );

	// Loop through counts by type
	foreach ( $counts as $type => $type_counts ) {

		// Skip if not this type
		if ( ! isset( $types[ $type ] ) ) {
			continue;
		}

		// Maybe filter statuses
		if ( ! empty( $statuses ) ) {
			$type_counts = array_intersect_key( $type_counts, $statuses );
		}

		// Add type counts to return array
		$retval[ $type ] = $type_counts;
	}

	// Filter & return
	return (array) apply_filters( 'bbp_filter_child_counts_list', $retval, $parent_id, $types, $statuses );
}

/**
 * Query the DB and get a count of public children
 *
 * @since 2.0.0 bbPress (r2868)
 * @since 2.6.0 bbPress (r5954) Replace direct queries with WP_Query() objects
 *
 * @param int    $parent_id Parent id.
 * @param string $post_type Post type. Defaults to 'post'.
 * @return int The number of children
 */
function bbp_get_public_child_count( $parent_id = 0, $post_type = 'post' ) {

	// Bail if nothing passed
	if ( empty( $post_type ) ) {
		return false;
	}

	// Which statuses
	switch ( $post_type ) {

		// Forum
		case bbp_get_forum_post_type() :
			$post_status = bbp_get_public_forum_statuses();
			break;

		// Topic
		case bbp_get_topic_post_type() :
			$post_status = bbp_get_public_topic_statuses();
			break;

		// Reply
		case bbp_get_reply_post_type() :
		default :
			$post_status = bbp_get_public_reply_statuses();
			break;
	}

	// Get counts
	$counts      = bbp_filter_child_counts_list( $parent_id, $post_type, $post_status );
	$child_count = isset( $counts[ $post_type ] )
		? bbp_number_not_negative( array_sum( array_values( $counts[ $post_type ] ) ) )
		: 0;

	// Filter & return
	return (int) apply_filters( 'bbp_get_public_child_count', $child_count, $parent_id, $post_type );
}
/**
 * Query the DB and get a count of public children
 *
 * @since 2.0.0 bbPress (r2868)
 * @since 2.6.0 bbPress (r5954) Replace direct queries with WP_Query() objects
 *
 * @param int    $parent_id Parent id.
 * @param string $post_type Post type. Defaults to 'post'.
 * @return int The number of children
 */
function bbp_get_non_public_child_count( $parent_id = 0, $post_type = 'post' ) {

	// Bail if nothing passed
	if ( empty( $parent_id ) || empty( $post_type ) ) {
		return false;
	}

	// Which statuses
	switch ( $post_type ) {

		// Forum
		case bbp_get_forum_post_type() :
			$post_status = bbp_get_non_public_forum_statuses();
			break;

		// Topic
		case bbp_get_topic_post_type() :
			$post_status = bbp_get_non_public_topic_statuses();
			break;

		// Reply
		case bbp_get_reply_post_type() :
			$post_status = bbp_get_non_public_reply_statuses();
			break;

		// Any
		default :
			$post_status = bbp_get_public_status_id();
			break;
	}

	// Get counts
	$counts      = bbp_filter_child_counts_list( $parent_id, $post_type, $post_status );
	$child_count = isset( $counts[ $post_type ] )
		? bbp_number_not_negative( array_sum( array_values( $counts[ $post_type ] ) ) )
		: 0;

	// Filter & return
	return (int) apply_filters( 'bbp_get_non_public_child_count', $child_count, $parent_id, $post_type );
}

/**
 * Query the DB and get the child id's of public children
 *
 * @since 2.0.0 bbPress (r2868)
 * @since 2.6.0 bbPress (r5954) Replace direct queries with WP_Query() objects
 *
 * @param int    $parent_id Parent id.
 * @param string $post_type Post type. Defaults to 'post'.
 *
 * @return array The array of children
 */
function bbp_get_public_child_ids( $parent_id = 0, $post_type = 'post' ) {

	// Bail if nothing passed
	if ( empty( $parent_id ) || empty( $post_type ) ) {
		return array();
	}

	// Which statuses
	switch ( $post_type ) {

		// Forum
		case bbp_get_forum_post_type() :
			$post_status = bbp_get_public_forum_statuses();
			break;

		// Topic
		case bbp_get_topic_post_type() :
			$post_status = bbp_get_public_topic_statuses();
			break;

		// Reply
		case bbp_get_reply_post_type() :
		default :
			$post_status = bbp_get_public_reply_statuses();
			break;
	}

	$query = new WP_Query( array(
		'fields'         => 'ids',
		'post_parent'    => $parent_id,
		'post_status'    => $post_status,
		'post_type'      => $post_type,
		'posts_per_page' => -1,
		'orderby'        => array(
			'post_date' => 'DESC',
			'ID'        => 'DESC'
		),

		// Performance
		'nopaging'               => true,
		'suppress_filters'       => true,
		'update_post_term_cache' => false,
		'update_post_meta_cache' => false,
		'ignore_sticky_posts'    => true,
		'no_found_rows'          => true
	) );

	$child_ids = ! empty( $query->posts )
		? $query->posts
		: array();

	unset( $query );

	// Filter & return
	return (array) apply_filters( 'bbp_get_public_child_ids', $child_ids, $parent_id, $post_type );
}

/**
 * Query the DB and get the child id's of all children
 *
 * @since 2.0.0 bbPress (r3325)
 *
 * @param int $parent_id  Parent id
 * @param string $post_type Post type. Defaults to 'post'
 *
 * @return array The array of children
 */
function bbp_get_all_child_ids( $parent_id = 0, $post_type = 'post' ) {

	// Bail if nothing passed
	if ( empty( $parent_id ) || empty( $post_type ) ) {
		return array();
	}

	// Make cache key
	$not_in = array( 'draft', 'future' );
	$key    = md5( serialize( array(
		'parent_id'   => $parent_id,
		'post_type'   => $post_type,
		'post_status' => $not_in
	) ) );

	// Check last changed
	$last_changed = wp_cache_get_last_changed( 'bbpress_posts' );
	$cache_key    = "bbp_child_ids:{$key}:{$last_changed}";

	// Check for cache and set if needed
	$child_ids = wp_cache_get( $cache_key, 'bbpress_posts' );

	// Not already cached
	if ( false === $child_ids ) {

		// Join post statuses to specifically exclude together
		$post_status = "'" . implode( "', '", $not_in ) . "'";
		$bbp_db      = bbp_db();

		// Note that we can't use WP_Query here thanks to post_status assumptions
		$query       = $bbp_db->prepare( "SELECT ID FROM {$bbp_db->posts} WHERE post_parent = %d AND post_status NOT IN ( {$post_status} ) AND post_type = %s ORDER BY ID DESC", $parent_id, $post_type );
		$child_ids   = (array) $bbp_db->get_col( $query );

		// Always cache the results
		wp_cache_set( $cache_key, $child_ids, 'bbpress_posts' );
	}

	// Make sure results are INTs
	$child_ids = wp_parse_id_list( $child_ids );

	// Filter & return
	return (array) apply_filters( 'bbp_get_all_child_ids', $child_ids, $parent_id, $post_type );
}

/**
 * Prime familial post caches.
 *
 * This function uses _prime_post_caches() to prepare the object cache for
 * imminent requests to post objects that aren't naturally cached by the primary
 * WP_Query calls themselves. Post author caches are also primed.
 *
 * This is triggered when a `update_post_family_cache` argument is set to true.
 *
 * Also see: bbp_update_post_author_caches()
 *
 * @since 2.6.0 bbPress (r6699)
 *
 * @param array $objects Array of objects, fresh from a query
 *
 * @return bool True if some IDs were cached
 */
function bbp_update_post_family_caches( $objects = array() ) {

	// Bail if no posts
	if ( empty( $objects ) ) {
		return false;
	}

	// Default value
	$post_ids = array();

	// Filter the types of IDs to prime
	$ids = apply_filters( 'bbp_update_post_family_caches', array(
		'_bbp_last_active_id',
		'_bbp_last_reply_id',
		'_bbp_last_topic_id',
		'_bbp_reply_to'
	), $objects );

	// Get the last active IDs
	foreach ( $objects as $object ) {
		$object = get_post( $object );

		// Skip if post ID is empty.
		if ( empty( $object->ID ) ) {
			continue;
		}

		// Meta IDs
		foreach ( $ids as $key ) {
			$post_ids[] = get_post_meta( $object->ID, $key, true );
		}

		// This post ID is already cached, but the post author may not be
		$post_ids[] = $object->ID;
	}

	// Unique, non-zero values
	$post_ids = bbp_get_unique_array_values( $post_ids );

	// Bail if no IDs to prime
	if ( empty( $post_ids ) ) {
		return false;
	}

	// Prime post caches
	_prime_post_caches( $post_ids, true, true );

	// Prime post author caches
	bbp_update_post_author_caches( $post_ids );

	// Return
	return true;
}

/**
 * Prime post author caches.
 *
 * This function uses cache_users() to prepare the object cache for
 * imminent requests to user objects that aren't naturally cached by the primary
 * WP_Query calls themselves.
 *
 * This is triggered when a `update_post_author_cache` argument is set to true.
 *
 * @since 2.6.0 bbPress (r6699)
 *
 * @param array $objects Array of objects, fresh from a query
 *
 * @return bool True if some IDs were cached
 */
function bbp_update_post_author_caches( $objects = array() ) {

	// Bail if no posts
	if ( empty( $objects ) ) {
		return false;
	}

	// Default value
	$user_ids = array();

	// Get the user IDs (could use wp_list_pluck() if this is ever a bottleneck)
	foreach ( $objects as $object ) {
		$object = get_post( $object );

		// Skip if post does not have an author ID.
		if ( empty( $object->post_author ) ) {
			continue;
		}

		// If post exists, add post author to the array.
		$user_ids[] = (int) $object->post_author;
	}

	// Unique, non-zero values
	$user_ids = bbp_get_unique_array_values( $user_ids );

	// Bail if no IDs to prime
	if ( empty( $user_ids ) ) {
		return false;
	}

	// Try to prime user caches
	cache_users( $user_ids );

	// Return
	return true;
}

/** Globals *******************************************************************/

/**
 * Get the unfiltered value of a global $post's key
 *
 * Used most frequently when editing a forum/topic/reply
 *
 * @since 2.1.0 bbPress (r3694)
 *
 * @param string $field Name of the key
 * @param string $context How to sanitize - raw|edit|db|display|attribute|js
 * @return string Field value
 */
function bbp_get_global_post_field( $field = 'ID', $context = 'edit' ) {

	// Get the post, and maybe get a field from it
	$post   = get_post();
	$retval = isset( $post->{$field} )
		? sanitize_post_field( $field, $post->{$field}, $post->ID, $context )
		: '';

	// Filter & return
	return apply_filters( 'bbp_get_global_post_field', $retval, $post, $field, $context );
}

/** Nonces ********************************************************************/

/**
 * Makes sure the user requested an action from another page on this site.
 *
 * To avoid security exploits within the theme.
 *
 * @since 2.1.0 bbPress (r4022)
 *
 * @param string $action Action nonce
 * @param string $query_arg where to look for nonce in $_REQUEST
 */
function bbp_verify_nonce_request( $action = '', $query_arg = '_wpnonce' ) {

	/** Home URL **************************************************************/

	// Parse home_url() into pieces to remove query-strings, strange characters,
	// and other funny things that plugins might to do to it.
	$parsed_home = parse_url( home_url( '/', ( is_ssl() ? 'https' : 'http' ) ) );

	// Maybe include the port, if it's included
	if ( isset( $parsed_home['port'] ) ) {
		$parsed_host = $parsed_home['host'] . ':' . $parsed_home['port'];
	} else {
		$parsed_host = $parsed_home['host'];
	}

	// Set the home URL for use in comparisons
	$home_url = trim( strtolower( $parsed_home['scheme'] . '://' . $parsed_host . $parsed_home['path'] ), '/' );

	/** Requested URL *********************************************************/

	// Maybe include the port, if it's included in home_url()
	if ( isset( $parsed_home['port'] ) && false === strpos( $_SERVER['HTTP_HOST'], ':' ) ) {
		$request_host = $_SERVER['HTTP_HOST'] . ':' . $_SERVER['SERVER_PORT'];
	} else {
		$request_host = $_SERVER['HTTP_HOST'];
	}

	// Build the currently requested URL
	$scheme        = bbp_get_url_scheme();
	$requested_url = strtolower( $scheme . $request_host . $_SERVER['REQUEST_URI'] );

	/** Look for match ********************************************************/

	/**
	 * Filters the requested URL being nonce-verified.
	 *
	 * Useful for configurations like reverse proxying.
	 *
	 * @since 2.2.0 bbPress (r4361)
	 *
	 * @param string $requested_url The requested URL.
	 */
	$matched_url = apply_filters( 'bbp_verify_nonce_request_url', $requested_url );

	// Check the nonce
	$result = isset( $_REQUEST[ $query_arg ] )
		? wp_verify_nonce( $_REQUEST[ $query_arg ], $action )
		: false;

	// Nonce check failed
	if ( empty( $result ) || empty( $action ) || ( strpos( $matched_url, $home_url ) !== 0 ) ) {
		$result = false;
	}

	/**
	 * Fires at the end of the nonce verification check.
	 *
	 * @since 2.1.0 bbPress (r4023)
	 *
	 * @param string $action Action nonce.
	 * @param bool   $result Boolean result of nonce verification.
	 */
	do_action( 'bbp_verify_nonce_request', $action, $result );

	return $result;
}

/** Feeds *********************************************************************/

/**
 * This function is hooked into the WordPress 'request' action and is
 * responsible for sniffing out the query vars and serving up RSS2 feeds if
 * the stars align and the user has requested a feed of any bbPress type.
 *
 * @since 2.0.0 bbPress (r3171)
 *
 * @param array $query_vars
 * @return array
 */
function bbp_request_feed_trap( $query_vars = array() ) {

	// Looking at a feed
	if ( isset( $query_vars['feed'] ) ) {

		// Forum/Topic/Reply Feed
		if ( isset( $query_vars['post_type'] ) ) {

			// Matched post type
			$post_type = false;

			// Post types to check
			$post_types = array(
				bbp_get_forum_post_type(),
				bbp_get_topic_post_type(),
				bbp_get_reply_post_type()
			);

			// Cast query vars as array outside of foreach loop
			$qv_array = (array) $query_vars['post_type'];

			// Check if this query is for a bbPress post type
			foreach ( $post_types as $bbp_pt ) {
				if ( in_array( $bbp_pt, $qv_array, true ) ) {
					$post_type = $bbp_pt;
					break;
				}
			}

			// Looking at a bbPress post type
			if ( ! empty( $post_type ) ) {

				// Supported select query vars
				$select_query_vars = array(
					'p'        => false,
					'name'     => false,
					$post_type => false,
				);

				// Setup matched variables to select
				foreach ( $query_vars as $key => $value ) {
					if ( isset( $select_query_vars[ $key ] ) ) {
						$select_query_vars[ $key ] = $value;
					}
				}

				// Remove any empties
				$select_query_vars = array_filter( $select_query_vars );

				// What bbPress post type are we looking for feeds on?
				switch ( $post_type ) {

					// Forum
					case bbp_get_forum_post_type() :

						// Define local variable(s)
						$meta_query = array();

						// Single forum
						if ( ! empty( $select_query_vars ) ) {

							// Load up our own query
							query_posts( array_merge( array(
								'post_type' => bbp_get_forum_post_type(),
								'feed'      => true
							), $select_query_vars ) );

							// Restrict to specific forum ID
							$meta_query = array( array(
								'key'     => '_bbp_forum_id',
								'value'   => bbp_get_forum_id(),
								'type'    => 'NUMERIC',
								'compare' => '='
							) );
						}

						// Only forum replies
						if ( ! empty( $_GET['type'] ) && ( bbp_get_reply_post_type() === $_GET['type'] ) ) {

							// The query
							$the_query = array(
								'author'         => 0,
								'feed'           => true,
								'post_type'      => bbp_get_reply_post_type(),
								'post_parent'    => 'any',
								'post_status'    => bbp_get_public_reply_statuses(),
								'posts_per_page' => bbp_get_replies_per_rss_page(),
								'order'          => 'DESC',
								'meta_query'     => $meta_query
							);

							// Output the feed
							bbp_display_replies_feed_rss2( $the_query );

						// Only forum topics
						} elseif ( ! empty( $_GET['type'] ) && ( bbp_get_topic_post_type() === $_GET['type'] ) ) {

							// The query
							$the_query = array(
								'author'         => 0,
								'feed'           => true,
								'post_type'      => bbp_get_topic_post_type(),
								'post_parent'    => bbp_get_forum_id(),
								'post_status'    => bbp_get_public_topic_statuses(),
								'posts_per_page' => bbp_get_topics_per_rss_page(),
								'order'          => 'DESC'
							);

							// Output the feed
							bbp_display_topics_feed_rss2( $the_query );

						// All forum topics and replies
						} else {

							// Exclude private/hidden forums if not looking at single
							if ( empty( $select_query_vars ) ) {
								$meta_query = array( bbp_exclude_forum_ids( 'meta_query' ) );
							}

							// The query
							$the_query = array(
								'author'         => 0,
								'feed'           => true,
								'post_type'      => array( bbp_get_reply_post_type(), bbp_get_topic_post_type() ),
								'post_parent'    => 'any',
								'post_status'    => bbp_get_public_topic_statuses(),
								'posts_per_page' => bbp_get_replies_per_rss_page(),
								'order'          => 'DESC',
								'meta_query'     => $meta_query
							);

							// Output the feed
							bbp_display_replies_feed_rss2( $the_query );
						}

						break;

					// Topic feed - Show replies
					case bbp_get_topic_post_type() :

						// Single topic
						if ( ! empty( $select_query_vars ) ) {

							// Load up our own query
							query_posts( array_merge( array(
								'post_type' => bbp_get_topic_post_type(),
								'feed'      => true
							), $select_query_vars ) );

							// Output the feed
							bbp_display_replies_feed_rss2( array( 'feed' => true ) );

						// All topics
						} else {

							// The query
							$the_query = array(
								'author'         => 0,
								'feed'           => true,
								'post_parent'    => 'any',
								'posts_per_page' => bbp_get_topics_per_rss_page(),
								'show_stickies'  => false
							);

							// Output the feed
							bbp_display_topics_feed_rss2( $the_query );
						}

						break;

					// Replies
					case bbp_get_reply_post_type() :

						// The query
						$the_query = array(
							'posts_per_page' => bbp_get_replies_per_rss_page(),
							'meta_query'     => array( array( ) ),
							'feed'           => true
						);

						// All replies
						if ( empty( $select_query_vars ) ) {
							bbp_display_replies_feed_rss2( $the_query );
						}

						break;
				}
			}

		// Single Topic Vview
		} elseif ( isset( $query_vars[ bbp_get_view_rewrite_id() ] ) ) {

			// Get the view
			$view = $query_vars[ bbp_get_view_rewrite_id() ];

			// We have a view to display a feed
			if ( ! empty( $view ) ) {

				// Get the view query
				$the_query = bbp_get_view_query_args( $view );

				// Output the feed
				bbp_display_topics_feed_rss2( $the_query );
			}
		}

		// @todo User profile feeds
	}

	// No feed so continue on
	return $query_vars;
}

/** Templates ******************************************************************/

/**
 * Used to guess if page exists at requested path
 *
 * @since 2.0.0 bbPress (r3304)
 *
 * @param string $path
 * @return mixed False if no page, Page object if true
 */
function bbp_get_page_by_path( $path = '' ) {

	// Default to false
	$retval = false;

	// Path is not empty
	if ( ! empty( $path ) ) {

		// Pretty permalinks are on so path might exist
		if ( get_option( 'permalink_structure' ) ) {
			$retval = get_page_by_path( $path );
		}
	}

	// Filter & return
	return apply_filters( 'bbp_get_page_by_path', $retval, $path );
}

/**
 * Sets the 404 status.
 *
 * Used primarily with topics/replies inside hidden forums.
 *
 * @since 2.0.0 bbPress (r3051)
 * @since 2.6.0 bbPress (r6583) Use status_header() & nocache_headers()
 *
 * @param WP_Query $query  The query being checked
 *
 * @return bool Always returns true
 */
function bbp_set_404( $query = null ) {

	// Global fallback
	if ( empty( $query ) ) {
		$query = bbp_get_wp_query();
	}

	// Setup environment
	$query->set_404();

	// Setup request
	status_header( 404 );
	nocache_headers();
}

/**
 * Sets the 200 status header.
 *
 * @since 2.6.0 bbPress (r6583)
 */
function bbp_set_200() {
	status_header( 200 );
}

/**
 * Maybe handle the default 404 handling for some bbPress conditions
 *
 * Some conditions (like private/hidden forums and edits) have their own checks
 * on `bbp_template_redirect` and are not currently 404s.
 *
 * @since 2.6.0 bbPress (r6555)
 *
 * @param bool $override Whether to override the default handler
 * @param WP_Query $wp_query The posts query being referenced
 *
 * @return bool False to leave alone, true to override
 */
function bbp_pre_handle_404( $override = false, $wp_query = false ) {

	// Handle a bbPress 404 condition
	if ( isset( $wp_query->bbp_is_404 ) ) {

		// Either force a 404 when 200, or a 200 when 404
		$override = ( true === $wp_query->bbp_is_404 )
			? bbp_set_404( $wp_query )
			: bbp_set_200();
	}

	// Return, maybe overridden
	return $override;
}

/**
 * Maybe pre-assign the posts that are returned from a WP_Query.
 *
 * This effectively short-circuits the default query for posts, which is
 * currently only used to avoid calling the main query when it's not necessary.
 *
 * @since 2.6.0 bbPress (r6580)
 *
 * @param mixed $posts Default null. Array of posts (possibly empty)
 * @param WP_Query $wp_query
 *
 * @return mixed Null if no override. Array if overridden.
 */
function bbp_posts_pre_query( $posts = null, $wp_query = false ) {

	// Custom 404 handler is set, so set posts to empty array to avoid 2 queries
	if ( ! empty( $wp_query->bbp_is_404 ) ) {
		$posts = array();
	}

	// Return, maybe overridden
	return $posts;
}

/**
 * Get scheme for a URL based on is_ssl() results.
 *
 * @since 2.6.0 bbPress (r6759)
 *
 * @return string https:// if is_ssl(), otherwise http://
 */
function bbp_get_url_scheme() {
	return is_ssl()
		? 'https://'
		: 'http://';
}

/** Titles ********************************************************************/

/**
 * Is a title longer that the maximum title length?
 *
 * Uses mb_strlen() in `8bit` mode to treat strings as raw. This matches the
 * behavior present in Comments, PHPMailer, RandomCompat, and others.
 *
 * @since 2.6.0 bbPress (r6783)
 *
 * @param string $title
 * @return bool
 */
function bbp_is_title_too_long( $title = '' ) {
	$max    = bbp_get_title_max_length();
	$len    = mb_strlen( $title, '8bit' );
	$result = ( $len > $max );

	// Filter & return
	return (bool) apply_filters( 'bbp_is_title_too_long', $result, $title, $max, $len );
}
