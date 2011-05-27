<?php

/**
 * bbPress Reply Template Tags
 *
 * @package bbPress
 * @subpackage TemplateTags
 */

// Redirect if accessed directly
if ( !defined( 'ABSPATH' ) ) exit;

/** Post Type *****************************************************************/

/**
 * Return the unique id of the custom post type for replies
 *
 * @since bbPress (r2857)
 *
 * @uses bbp_get_reply_post_type() To get the reply post type
 */
function bbp_reply_post_type() {
	echo bbp_get_reply_post_type();
}
	/**
	 * Return the unique id of the custom post type for replies
	 *
	 * @since bbPress (r2857)
	 *
	 * @uses apply_filters() Calls 'bbp_get_forum_post_type' with the forum
	 *                        post type id
	 * @return string The unique reply post type id
	 */
	function bbp_get_reply_post_type() {
		global $bbp;

		return apply_filters( 'bbp_get_reply_post_type', $bbp->reply_post_type );
	}

/** Reply Loop Functions ******************************************************/

/**
 * The main reply loop. WordPress makes this easy for us
 *
 * @since bbPress (r2553)
 *
 * @param mixed $args All the arguments supported by {@link WP_Query}
 * @uses bbp_is_topic() To check if it's the topic page
 * @uses bbp_show_lead_topic() Are we showing the topic as a lead?
 * @uses bbp_get_topic_id() To get the topic id
 * @uses bbp_get_reply_post_type() To get the reply post type
 * @uses bbp_get_topic_post_type() To get the topic post type
 * @uses bbp_is_query_name() To check if we are getting replies for a widget
 * @uses get_option() To get the replies per page option
 * @uses bbp_get_paged() To get the current page value
 * @uses current_user_can() To check if the current user is capable of editing
 *                           others' replies
 * @uses WP_Query To make query and get the replies
 * @uses WP_Rewrite::using_permalinks() To check if the blog is using permalinks
 * @uses get_permalink() To get the permalink
 * @uses add_query_arg() To add custom args to the url
 * @uses apply_filters() Calls 'bbp_replies_pagination' with the pagination args
 * @uses paginate_links() To paginate the links
 * @uses apply_filters() Calls 'bbp_has_replies' with
 *                        bbPres::reply_query::have_posts()
 *                        and bbPres::reply_query
 * @return object Multidimensional array of reply information
 */
function bbp_has_replies( $args = '' ) {
	global $wp_rewrite, $bbp;

	// Make sure we're back where we started
	wp_reset_postdata();

	// Default status
	$default_status = join( ',', array( 'publish', $bbp->closed_status_id ) );

	// Skip topic_id if in the replies widget query
	if ( !bbp_is_query_name( 'bbp_widget' ) ) {
		$parent_args['meta_query'] = array(
			array(
				'key'     => '_bbp_topic_id',
				'value'   => bbp_get_topic_id(),
				'compare' => '='
			)
		);

		// What are the default allowed statuses (based on user caps)
		if ( !empty( $_GET['view'] ) && ( 'all' == $_GET['view'] && current_user_can( 'edit_others_replies' ) ) )
			$default_status = join( ',', array( 'publish', $bbp->closed_status_id, $bbp->spam_status_id, 'trash' ) );
	}

	// Default query args
	$default = array(

		// Post type(s) depending on bbp_show_lead_topic()
		'post_type'      => bbp_show_lead_topic() ? bbp_get_reply_post_type() : array( bbp_get_topic_post_type(), bbp_get_reply_post_type() ),

		// 'author', 'date', 'title', 'modified', 'parent', rand',
		'orderby'        => 'date',

		// 'ASC', 'DESC'
		'order'          => 'ASC',

		// Max number
		'posts_per_page' => get_option( '_bbp_replies_per_page', 15 ),

		// Page Number
		'paged'          => bbp_get_paged(),

		// Reply Search
		's'              => !empty( $_REQUEST['rs'] ) ? $_REQUEST['rs'] : '',

		// Post Status
		'post_status'    => $default_status
	);

	// Merge the default args and parent args together
	if ( isset( $parent_args ) )
		$default = array_merge( $parent_args, $default );

	// Set up topic variables
	$bbp_r = wp_parse_args( $args, $default );

	// Filter the replies query to allow just-in-time modifications
	$bbp_r = apply_filters( 'bbp_has_replies_query', $bbp_r );

	// Extract the query variables
	extract( $bbp_r );

	// Call the query
	$bbp->reply_query = new WP_Query( $bbp_r );

	// Add pagination values to query object
	$bbp->reply_query->posts_per_page = $posts_per_page;
	$bbp->reply_query->paged          = $paged;

	// Only add pagination if query returned results
	if ( (int) $bbp->reply_query->found_posts && (int) $bbp->reply_query->posts_per_page ) {

		// If pretty permalinks are enabled, make our pagination pretty
		if ( $wp_rewrite->using_permalinks() )
			$base = user_trailingslashit( trailingslashit( get_permalink( bbp_get_topic_id() ) ) . 'page/%#%/' );
		else
			$base = add_query_arg( 'paged', '%#%' );

		// Pagination settings with filter
		$bbp_replies_pagination = apply_filters( 'bbp_replies_pagination', array(
			'base'      => $base,
			'format'    => '',
			'total'     => ceil( (int) $bbp->reply_query->found_posts / (int) $posts_per_page ),
			'current'   => (int) $bbp->reply_query->paged,
			'prev_text' => '&larr;',
			'next_text' => '&rarr;',
			'mid_size'  => 1,
			'add_args'  => ( !empty( $_GET['view'] ) && 'all' == $_GET['view'] ) ? array( 'view' => 'all' ) : false
		) );

		// Add pagination to query object
		$bbp->reply_query->pagination_links = paginate_links( $bbp_replies_pagination );

		// Remove first page from pagination
		if ( $wp_rewrite->using_permalinks() )
			$bbp->reply_query->pagination_links = str_replace( 'page/1/\'',     '\'', $bbp->reply_query->pagination_links );
		else
			$bbp->reply_query->pagination_links = str_replace( '&#038;paged=1', '',   $bbp->reply_query->pagination_links );
	}

	// Return object
	return apply_filters( 'bbp_has_replies', $bbp->reply_query->have_posts(), $bbp->reply_query );
}

/**
 * Whether there are more replies available in the loop
 *
 * @since bbPress (r2553)
 *
 * @uses WP_Query bbPress::reply_query::have_posts() To check if there are more
 *                                                    replies available
 * @return object Replies information
 */
function bbp_replies() {
	global $bbp;
	return $bbp->reply_query->have_posts();
}

/**
 * Loads up the current reply in the loop
 *
 * @since bbPress (r2553)
 *
 * @uses WP_Query bbPress::reply_query::the_post() To get the current reply
 * @return object Reply information
 */
function bbp_the_reply() {
	global $bbp;
	return $bbp->reply_query->the_post();
}

/**
 * Output reply id
 *
 * @since bbPress (r2553)
 *
 * @param $reply_id Optional. Used to check emptiness
 * @uses bbp_get_reply_id() To get the reply id
 */
function bbp_reply_id( $reply_id = 0 ) {
	echo bbp_get_reply_id( $reply_id );
}
	/**
	 * Return the id of the reply in a replies loop
	 *
	 * @since bbPress (r2553)
	 *
	 * @param $reply_id Optional. Used to check emptiness
	 * @uses bbPress::reply_query::post::ID To get the reply id
	 * @uses bbp_is_reply() To check if it's a reply page
	 * @uses bbp_is_reply_edit() To check if it's a reply edit page
	 * @uses get_post_field() To get the post's post type
	 * @uses WP_Query::post::ID To get the reply id
	 * @uses bbp_get_reply_post_type() To get the reply post type
	 * @uses apply_filters() Calls 'bbp_get_reply_id' with the reply id and
	 *                        supplied reply id
	 * @return int The reply id
	 */
	function bbp_get_reply_id( $reply_id = 0 ) {
		global $bbp, $wp_query;

		// Easy empty checking
		if ( !empty( $reply_id ) && is_numeric( $reply_id ) )
			$bbp_reply_id = $reply_id;

		// Currently viewing a reply
		elseif ( ( bbp_is_reply() || bbp_is_reply_edit() ) && isset( $wp_query->post->ID ) )
			$bbp_reply_id = $bbp->current_reply_id = $wp_query->post->ID;

		// Currently inside a replies loop
		elseif ( isset( $bbp->reply_query->post->ID ) )
			$bbp_reply_id = $bbp->current_reply_id = $bbp->reply_query->post->ID;

		// Fallback
		else
			$bbp_reply_id = 0;

		// Check if current_reply_id is set, and check post_type if so
		if ( !empty( $bbp->current_reply_id ) && ( bbp_get_reply_post_type() != get_post_field( 'post_type', $bbp_reply_id ) ) )
			$bbp->current_reply_id = null;

		return apply_filters( 'bbp_get_reply_id', (int) $bbp_reply_id, $reply_id );
	}

/**
 * Gets a reply
 *
 * @since bbPress (r2787)
 *
 * @param int|object $reply reply id or reply object
 * @param string $output Optional. OBJECT, ARRAY_A, or ARRAY_N. Default = OBJECT
 * @param string $filter Optional Sanitation filter. See {@link sanitize_post()}
 * @uses get_post() To get the reply
 * @uses bbp_get_reply_post_type() To get the reply post type
 * @uses apply_filters() Calls 'bbp_get_reply' with the reply, output type and
 *                        sanitation filter
 * @return mixed Null if error or reply (in specified form) if success
 */
function bbp_get_reply( $reply, $output = OBJECT, $filter = 'raw' ) {
	if ( empty( $reply ) || is_numeric( $reply ) )
		$reply = bbp_get_reply_id( $reply );

	if ( !$reply = get_post( $reply, OBJECT, $filter ) )
		return $reply;

	if ( $reply->post_type !== bbp_get_reply_post_type() )
		return null;

	if ( $output == OBJECT ) {
		return $reply;

	} elseif ( $output == ARRAY_A ) {
		$_reply = get_object_vars( $reply );
		return $_reply;

	} elseif ( $output == ARRAY_N ) {
		$_reply = array_values( get_object_vars( $reply ) );
		return $_reply;

	}

	return apply_filters( 'bbp_get_reply', $reply, $output, $filter );
}

/**
 * Output the link to the reply in the reply loop
 *
 * @since bbPress (r2553)
 *
 * @param int $reply_id Optional. Reply id
 * @uses bbp_get_reply_permalink() To get the reply permalink
 */
function bbp_reply_permalink( $reply_id = 0 ) {
	echo bbp_get_reply_permalink( $reply_id );
}
	/**
	 * Return the link to the reply
	 *
	 * @since bbPress (r2553)
	 *
	 * @param int $reply_id Optional. Reply id
	 * @uses bbp_get_reply_id() To get the reply id
	 * @uses get_permalink() To get the permalink of the reply
	 * @uses apply_filters() Calls 'bbp_get_reply_permalink' with the link
	 *                        and reply id
	 * @return string Permanent link to reply
	 */
	function bbp_get_reply_permalink( $reply_id = 0 ) {
		$reply_id = bbp_get_reply_id( $reply_id );

		return apply_filters( 'bbp_get_reply_permalink', get_permalink( $reply_id ), $reply_id );
	}
/**
 * Output the paginated url to the reply in the reply loop
 *
 * @since bbPress (r2679)
 *
 * @param int $reply_id Optional. Reply id
 * @uses bbp_get_reply_url() To get the reply url
 */
function bbp_reply_url( $reply_id = 0 ) {
	echo bbp_get_reply_url( $reply_id );
}
	/**
	 * Return the paginated url to the reply in the reply loop
	 *
	 * @since bbPress (r2679)
	 *
	 * @param int $reply_id Optional. Reply id
	 * @param bool $count_hidden Optional. Count hidden (trashed/spammed)
	 *                            replies? If $_GET['view'] == all, it is
	 *                            automatically set to true. To override
	 *                            this, set $count_hidden = (int) -1
	 * @uses bbp_get_reply_id() To get the reply id
	 * @uses bbp_get_reply_topic_id() To get the reply topic id
	 * @uses bbp_get_topic_permalink() To get the topic permalink
	 * @uses bbp_get_reply_position() To get the reply position
	 * @uses get_option() To get the replies per page option
	 * @uses WP_Rewrite::using_permalinks() To check if the blog uses
	 *                                       permalinks
	 * @uses add_query_arg() To add custom args to the url
	 * @uses apply_filters() Calls 'bbp_get_reply_url' with the reply url,
	 *                        reply id and bool count hidden
	 * @return string Link to reply relative to paginated topic
	 */
	function bbp_get_reply_url( $reply_id = 0, $count_hidden = false ) {
		global $bbp, $wp_rewrite;

		// Set needed variables
		$reply_id       = bbp_get_reply_id       ( $reply_id );
		$topic_id       = bbp_get_reply_topic_id ( $reply_id );
		$topic_url      = bbp_get_topic_permalink( $topic_id );
		$reply_position = bbp_get_reply_position ( $reply_id );

		// Check if in query with pagination
		$reply_page     = ceil( $reply_position / get_option( '_bbp_replies_per_page', 15 ) );

		// Hash to add to end of URL
		$reply_hash     = !empty( $bbp->errors ) ? "#post-{$reply_id}" : '';

		// Remove the topic view query arg if its set
		$topic_url      = remove_query_arg( 'view', $topic_url );

		// Don't include pagination if on first page
		if ( 1 >= $reply_page ) {
			$url = trailingslashit( $topic_url ) . $reply_hash;

		// Include pagination
		} else {

			// Pretty permalinks
			if ( $wp_rewrite->using_permalinks() ) {
				$url = trailingslashit( $topic_url ) . trailingslashit( "page/{$reply_page}" ) . $reply_hash;

			// Yucky links
			} else {
				$url = add_query_arg( 'paged', $reply_page, $topic_url ) . $reply_hash;
			}
		}

		return apply_filters( 'bbp_get_reply_url', $url, $reply_id, $count_hidden );
	}

/**
 * Output the title of the reply
 *
 * @since bbPress (r2553)
 *
 * @param int $reply_id Optional. Reply id
 * @uses bbp_get_reply_title() To get the reply title
 */
function bbp_reply_title( $reply_id = 0 ) {
	echo bbp_get_reply_title( $reply_id );
}

	/**
	 * Return the title of the reply
	 *
	 * @since bbPress (r2553)
	 *
	 * @param int $reply_id Optional. Reply id
	 * @uses bbp_get_reply_id() To get the reply id
	 * @uses get_the_title() To get the reply title
	 * @uses apply_filters() Calls 'bbp_get_reply_title' with the title and
	 *                        reply id
	 * @return string Title of reply
	 */
	function bbp_get_reply_title( $reply_id = 0 ) {
		$reply_id = bbp_get_reply_id( $reply_id );

		return apply_filters( 'bbp_get_reply_title', get_the_title( $reply_id ), $reply_id );
	}

/**
 * Output the content of the reply
 *
 * @since bbPress (r2553)
 *
 * @param int $reply_id Optional. reply id
 * @uses bbp_get_reply_content() To get the reply content
 */
function bbp_reply_content( $reply_id = 0 ) {
	echo bbp_get_reply_content( $reply_id );
}
	/**
	 * Return the content of the reply
	 *
	 * @since bbPress (r2780)
	 *
	 * @param int $reply_id Optional. reply id
	 * @uses bbp_get_reply_id() To get the reply id
	 * @uses post_password_required() To check if the reply requires pass
	 * @uses get_the_password_form() To get the password form
	 * @uses get_post_field() To get the content post field
	 * @uses apply_filters() Calls 'bbp_get_reply_content' with the content
	 *                        and reply id
	 * @return string Content of the reply
	 */
	function bbp_get_reply_content( $reply_id = 0 ) {
		$reply_id = bbp_get_reply_id( $reply_id );

		// Check if password is required
		if ( post_password_required( $reply_id ) )
			return get_the_password_form();

		$content = get_post_field( 'post_content', $reply_id );

		return apply_filters( 'bbp_get_reply_content', $content, $reply_id );
	}

/**
 * Output the excerpt of the reply
 *
 * @since bbPress (r2751)
 *
 * @param int $reply_id Optional. Reply id
 * @param int $length Optional. Length of the excerpt. Defaults to 100 letters
 * @uses bbp_get_reply_excerpt() To get the reply excerpt
 */
function bbp_reply_excerpt( $reply_id = 0, $length = 100 ) {
	echo bbp_get_reply_excerpt( $reply_id, $length );
}
	/**
	 * Return the excerpt of the reply
	 *
	 * @since bbPress (r2751)
	 *
	 * @param int $reply_id Optional. Reply id
	 * @param int $length Optional. Length of the excerpt. Defaults to 100
	 *                     letters
	 * @uses bbp_get_reply_id() To get the reply id
	 * @uses get_post_field() To get the excerpt
	 * @uses bbp_get_reply_content() To get the reply content
	 * @uses apply_filters() Calls 'bbp_get_reply_excerpt' with the excerpt,
	 *                        reply id and length
	 * @return string Reply Excerpt
	 */
	function bbp_get_reply_excerpt( $reply_id = 0, $length = 100 ) {
		$reply_id = bbp_get_reply_id( $reply_id );
		$length   = (int) $length;
		$excerpt  = get_post_field( $reply_id, 'post_excerpt' );

		if ( empty( $excerpt ) )
			$excerpt = bbp_get_reply_content( $reply_id );

		$excerpt = trim ( strip_tags( $excerpt ) );

		if ( !empty( $length ) && strlen( $excerpt ) > $length ) {
			$excerpt  = substr( $excerpt, 0, $length - 1 );
			$excerpt .= '&hellip;';
		}

		return apply_filters( 'bbp_get_reply_excerpt', $excerpt, $reply_id, $length );
	}

/**
 * Append revisions to the reply content
 *
 * @since bbPress (r2782)
 *
 * @param string $content Optional. Content to which we need to append the revisions to
 * @param int $reply_id Optional. Reply id
 * @uses bbp_get_reply_revision_log() To get the reply revision log
 * @uses apply_filters() Calls 'bbp_reply_append_revisions' with the processed
 *                        content, original content and reply id
 * @return string Content with the revisions appended
 */
function bbp_reply_content_append_revisions( $content = '', $reply_id = 0 ) {
	$reply_id = bbp_get_reply_id( $reply_id );

	return apply_filters( 'bbp_reply_append_revisions', $content . bbp_get_reply_revision_log( $reply_id ), $content, $reply_id );
}

/**
 * Output the revision log of the reply
 *
 * @since bbPress (r2782)
 *
 * @param int $reply_id Optional. Reply id
 * @uses bbp_get_reply_revision_log() To get the reply revision log
 */
function bbp_reply_revision_log( $reply_id = 0 ) {
	echo bbp_get_reply_revision_log( $reply_id );
}
	/**
	 * Return the formatted revision log of the reply
	 *
	 * @since bbPress (r2782)
	 *
	 * @param int $reply_id Optional. Reply id
	 * @uses bbp_get_reply_id() To get the reply id
	 * @uses bbp_get_reply_revisions() To get the reply revisions
	 * @uses bbp_get_reply_raw_revision_log() To get the raw revision log
	 * @uses bbp_get_reply_author_display_name() To get the reply author
	 * @uses bbp_get_reply_author_link() To get the reply author link
	 * @uses bbp_convert_date() To convert the date
	 * @uses bbp_get_time_since() To get the time in since format
	 * @uses apply_filters() Calls 'bbp_get_reply_revision_log' with the
	 *                        log and reply id
	 * @return string Revision log of the reply
	 */
	function bbp_get_reply_revision_log( $reply_id = 0 ) {
		// Create necessary variables
		$reply_id     = bbp_get_reply_id( $reply_id );
		$revision_log = bbp_get_reply_raw_revision_log( $reply_id );

		// Check reply and revision log exist
		if ( empty( $reply_id ) || empty( $revision_log ) || !is_array( $revision_log ) )
			return false;

		// Get the actual revisions
		if ( !$revisions = bbp_get_reply_revisions( $reply_id ) )
			return false;

		$r = "\n\n" . '<ul id="bbp-reply-revision-log-' . $reply_id . '" class="bbp-reply-revision-log">' . "\n\n";

		// Loop through revisions
		foreach ( (array) $revisions as $revision ) {

			if ( empty( $revision_log[$revision->ID] ) ) {
				$author_id = $revision->post_author;
				$reason    = '';
			} else {
				$author_id = $revision_log[$revision->ID]['author'];
				$reason    = $revision_log[$revision->ID]['reason'];
			}

			$author = bbp_get_author_link( array( 'size' => 14, 'link_text' => bbp_get_reply_author_display_name( $revision->ID ), 'post_id' => $revision->ID ) );
			$since  = bbp_get_time_since( bbp_convert_date( $revision->post_modified ) );

			$r .= "\t" . '<li id="bbp-reply-revision-log-' . $reply_id . '-item-' . $revision->ID . '" class="bbp-reply-revision-log-item">' . "\n";
				$r .= "\t\t" . sprintf( __( empty( $reason ) ? 'This reply was modified %1$s ago by %2$s.' : 'This reply was modified %1$s ago by %2$s. Reason: %3$s', 'bbpress' ), $since, $author, $reason ) . "\n";
			$r .= "\t" . '</li>' . "\n";

		}

		$r .= "\n" . '</ul>' . "\n\n";

		return apply_filters( 'bbp_get_reply_revision_log', $r, $reply_id );
	}
		/**
		 * Return the raw revision log of the reply
		 *
		 * @since bbPress (r2782)
		 *
		 * @param int $reply_id Optional. Reply id
		 * @uses bbp_get_reply_id() To get the reply id
		 * @uses get_post_meta() To get the revision log meta
		 * @uses apply_filters() Calls 'bbp_get_reply_raw_revision_log'
		 *                        with the log and reply id
		 * @return string Raw revision log of the reply
		 */
		function bbp_get_reply_raw_revision_log( $reply_id = 0 ) {
			$reply_id     = bbp_get_reply_id( $reply_id );
			$revision_log = get_post_meta( $reply_id, '_bbp_revision_log', true );
			$revision_log = empty( $revision_log ) ? array() : $revision_log;

			return apply_filters( 'bbp_get_reply_raw_revision_log', $revision_log, $reply_id );
		}

/**
 * Return the revisions of the reply
 *
 * @since bbPress (r2782)
 *
 * @param int $reply_id Optional. Reply id
 * @uses bbp_get_reply_id() To get the reply id
 * @uses wp_get_post_revisions() To get the reply revisions
 * @uses apply_filters() Calls 'bbp_get_reply_revisions'
 *                        with the revisions and reply id
 * @return string reply revisions
 */
function bbp_get_reply_revisions( $reply_id = 0 ) {
	$reply_id  = bbp_get_reply_id( $reply_id );
	$revisions = wp_get_post_revisions( $reply_id, array( 'order' => 'ASC' ) );

	return apply_filters( 'bbp_get_reply_revisions', $revisions, $reply_id );
}

/**
 * Return the revision count of the reply
 *
 * @since bbPress (r2782)
 *
 * @param int $reply_id Optional. Reply id
 * @uses bbp_get_reply_revisions() To get the reply revisions
 * @uses apply_filters() Calls 'bbp_get_reply_revision_count'
 *                        with the revision count and reply id
 * @return string reply revision count
 */
function bbp_get_reply_revision_count( $reply_id = 0 ) {
	return apply_filters( 'bbp_get_reply_revisions', count( bbp_get_reply_revisions( $reply_id ) ), $reply_id );
}

/**
 * Output the status of the reply
 *
 * @since bbPress (r2667)
 *
 * @param int $reply_id Optional. Reply id
 * @uses bbp_get_reply_status() To get the reply status
 */
function bbp_reply_status( $reply_id = 0 ) {
	echo bbp_get_reply_status( $reply_id );
}
	/**
	 * Return the status of the reply
	 *
	 * @since bbPress (r2667)
	 *
	 * @param int $reply_id Optional. Reply id
	 * @uses bbp_get_reply_id() To get the reply id
	 * @uses get_post_status() To get the reply status
	 * @uses apply_filters() Calls 'bbp_get_reply_status' with the reply id
	 * @return string Status of reply
	 */
	function bbp_get_reply_status( $reply_id = 0 ) {
		$reply_id = bbp_get_reply_id( $reply_id );

		return apply_filters( 'bbp_get_reply_status', get_post_status( $reply_id ), $reply_id );
	}

/**
 * Is the reply marked as spam?
 *
 * @since bbPress (r2740)
 *
 * @param int $reply_id Optional. Reply id
 * @uses bbp_get_reply_id() To get the reply id
 * @uses bbp_get_reply_status() To get the reply status
 * @return bool True if spam, false if not.
 */
function bbp_is_reply_spam( $reply_id = 0 ) {
	global $bbp;

	$reply_status = bbp_get_reply_status( bbp_get_reply_id( $reply_id ) );

	return apply_filters( 'bbp_is_reply_spam', $bbp->spam_status_id == $reply_status, $reply_id );
}

/**
 * Is the reply trashed?
 *
 * @since bbPress (r2884)
 *
 * @param int $reply_id Optional. Topic id
 * @uses bbp_get_reply_id() To get the reply id
 * @uses bbp_get_reply_status() To get the reply status
 * @return bool True if spam, false if not.
 */
function bbp_is_reply_trash( $reply_id = 0 ) {
	global $bbp;

	$reply_status = bbp_get_reply_status( bbp_get_reply_id( $reply_id ) );

	return apply_filters( 'bbp_is_reply_trash', $bbp->trash_status_id == $reply_status, $reply_id );
}

/**
 * Is the reply by an anonymous user?
 *
 * @since bbPress (r2753)
 *
 * @param int $reply_id Optional. Reply id
 * @uses bbp_get_reply_id() To get the reply id
 * @uses bbp_get_reply_author_id() To get the reply author id
 * @uses get_post_meta() To get the anonymous name and email metas
 * @return bool True if the post is by an anonymous user, false if not.
 */
function bbp_is_reply_anonymous( $reply_id = 0 ) {
	$reply_id = bbp_get_reply_id( $reply_id );

	$retval = false;

	if ( !bbp_get_reply_author_id( $reply_id ) )
		$retval = true;

	elseif ( get_post_meta( $reply_id, '_bbp_anonymous_name', true ) )
		$retval = true;

	elseif ( get_post_meta( $reply_id, '_bbp_anonymous_email', true ) )
		$retval = true;

	return apply_filters( 'bbp_is_reply_anonymous', $retval );
}

/**
 * Output the author of the reply
 *
 * @since bbPress (r2667)
 *
 * @param int $reply_id Optional. Reply id
 * @uses bbp_get_reply_author() To get the reply author
 */
function bbp_reply_author( $reply_id = 0 ) {
	echo bbp_get_reply_author( $reply_id );
}
	/**
	 * Return the author of the reply
	 *
	 * @since bbPress (r2667)
	 *
	 * @param int $reply_id Optional. Reply id
	 * @uses bbp_get_reply_id() To get the reply id
	 * @uses bbp_is_reply_anonymous() To check if the reply is by an
	 *                                 anonymous user
	 * @uses get_the_author_meta() To get the reply author display name
	 * @uses get_post_meta() To get the anonymous poster name
	 * @uses apply_filters() Calls 'bbp_get_reply_author' with the reply
	 *                        author and reply id
	 * @return string Author of reply
	 */
	function bbp_get_reply_author( $reply_id = 0 ) {
		$reply_id = bbp_get_reply_id( $reply_id );

		if ( !bbp_is_reply_anonymous( $reply_id ) )
			$author = get_the_author_meta( 'display_name', bbp_get_reply_author_id( $reply_id ) );
		else
			$author = get_post_meta( $reply_id, '_bbp_anonymous_name', true );

		return apply_filters( 'bbp_get_reply_author', $author, $reply_id );
	}

/**
 * Output the author ID of the reply
 *
 * @since bbPress (r2667)
 *
 * @param int $reply_id Optional. Reply id
 * @uses bbp_get_reply_author_id() To get the reply author id
 */
function bbp_reply_author_id( $reply_id = 0 ) {
	echo bbp_get_reply_author_id( $reply_id );
}
	/**
	 * Return the author ID of the reply
	 *
	 * @since bbPress (r2667)
	 *
	 * @param int $reply_id Optional. Reply id
	 * @uses bbp_get_reply_id() To get the reply id
	 * @uses get_post_field() To get the reply author id
	 * @uses apply_filters() Calls 'bbp_get_reply_author_id' with the author
	 *                        id and reply id
	 * @return string Author id of reply
	 */
	function bbp_get_reply_author_id( $reply_id = 0 ) {
		$reply_id  = bbp_get_reply_id( $reply_id );
		$author_id = get_post_field( 'post_author', $reply_id );

		return apply_filters( 'bbp_get_reply_author_id', (int) $author_id, $reply_id );
	}

/**
 * Output the author display_name of the reply
 *
 * @since bbPress (r2667)
 *
 * @param int $reply_id Optional. Reply id
 * @uses bbp_get_reply_author_display_name()
 */
function bbp_reply_author_display_name( $reply_id = 0 ) {
	echo bbp_get_reply_author_display_name( $reply_id );
}
	/**
	 * Return the author display_name of the reply
	 *
	 * @since bbPress (r2667)
	 *
	 * @param int $reply_id Optional. Reply id
	 * @uses bbp_get_reply_id() To get the reply id
	 * @uses bbp_is_reply_anonymous() To check if the reply is by an
	 *                                 anonymous user
	 * @uses bbp_get_reply_author_id() To get the reply author id
	 * @uses get_the_author_meta() To get the reply author's display name
	 * @uses get_post_meta() To get the anonymous poster's name
	 * @uses apply_filters() Calls 'bbp_get_reply_author_display_name' with
	 *                        the author display name and reply id
	 * @return string Reply's author's display name
	 */
	function bbp_get_reply_author_display_name( $reply_id = 0 ) {
		$reply_id = bbp_get_reply_id( $reply_id );

		// Check for anonymous user
		if ( !bbp_is_reply_anonymous( $reply_id ) )
			$author_name = get_the_author_meta( 'display_name', bbp_get_reply_author_id( $reply_id ) );
		else
			$author_name = get_post_meta( $reply_id, '_bbp_anonymous_name', true );

		return apply_filters( 'bbp_get_reply_author_display_name', esc_attr( $author_name ), $reply_id );
	}

/**
 * Output the author avatar of the reply
 *
 * @since bbPress (r2667)
 *
 * @param int $reply_id Optional. Reply id
 * @param int $size Optional. Size of the avatar. Defaults to 40
 * @uses bbp_get_reply_author_avatar() To get the reply author id
 */
function bbp_reply_author_avatar( $reply_id = 0, $size = 40 ) {
	echo bbp_get_reply_author_avatar( $reply_id, $size );
}
	/**
	 * Return the author avatar of the reply
	 *
	 * @since bbPress (r2667)
	 *
	 * @param int $reply_id Optional. Reply id
	 * @param int $size Optional. Size of the avatar. Defaults to 40
	 * @uses bbp_get_reply_id() To get the reply id
	 * @uses bbp_is_reply_anonymous() To check if the reply is by an
	 *                                 anonymous user
	 * @uses bbp_get_reply_author_id() To get the reply author id
	 * @uses get_post_meta() To get the anonymous poster's email id
	 * @uses get_avatar() To get the avatar
	 * @uses apply_filters() Calls 'bbp_get_reply_author_avatar' with the
	 *                        author avatar, reply id and size
	 * @return string Avatar of author of the reply
	 */
	function bbp_get_reply_author_avatar( $reply_id = 0, $size = 40 ) {
		if ( $reply_id = bbp_get_reply_id( $reply_id ) ) {
			// Check for anonymous user
			if ( !bbp_is_reply_anonymous( $reply_id ) )
				$author_avatar = get_avatar( bbp_get_reply_author_id( $reply_id ), $size );
			else
				$author_avatar = get_avatar( get_post_meta( $reply_id, '_bbp_anonymous_email', true ), $size );
		} else {
			$author_avatar = '';
		}

		return apply_filters( 'bbp_get_reply_author_avatar', $author_avatar, $reply_id, $size );
	}

/**
 * Output the author link of the reply
 *
 * @since bbPress (r2717)
 *
 * @param mixed $args Optional. If it is an integer, it is used as reply id.
 * @uses bbp_get_reply_author_link() To get the reply author link
 */
function bbp_reply_author_link( $args = '' ) {
	echo bbp_get_reply_author_link( $args );
}
	/**
	 * Return the author link of the reply
	 *
	 * @since bbPress (r2717)
	 *
	 * @param mixed $args Optional. If an integer, it is used as reply id.
	 * @uses bbp_get_reply_id() To get the reply id
	 * @uses bbp_is_topic() To check if it's a topic page
	 * @uses bbp_is_reply() To check if it's a reply page
	 * @uses bbp_is_reply_anonymous() To check if the reply is by an
	 *                                 anonymous user
	 * @uses bbp_get_reply_author() To get the reply author name
	 * @uses bbp_get_reply_author_url() To get the reply author url
	 * @uses bbp_get_reply_author_avatar() To get the reply author avatar
	 * bbp_get_reply_author_display_name() To get the reply author display
	 *                                      name
	 * @uses apply_filters() Calls 'bbp_get_reply_author_link' with the
	 *                        author link and args
	 * @return string Author link of reply
	 */
	function bbp_get_reply_author_link( $args = '' ) {
		$defaults = array (
			'post_id'    => 0,
			'link_title' => '',
			'type'       => 'both',
			'size'       => 80
		);

		$r = wp_parse_args( $args, $defaults );
		extract( $r );

		// Used as reply_id
		if ( is_numeric( $args ) )
			$reply_id = bbp_get_reply_id( $args );
		else
			$reply_id = bbp_get_reply_id( $post_id );

		if ( !empty( $reply_id ) ) {
			if ( empty( $link_title ) )
				$link_title = sprintf( !bbp_is_reply_anonymous( $reply_id ) ? __( 'View %s\'s profile', 'bbpress' ) : __( 'Visit %s\'s website', 'bbpress' ), bbp_get_reply_author_display_name( $reply_id ) );

			$link_title = !empty( $link_title ) ? ' title="' . $link_title . '"' : '';
			$author_url = bbp_get_reply_author_url( $reply_id );
			$anonymous  = bbp_is_reply_anonymous( $reply_id );

			// Get avatar
			if ( 'avatar' == $type || 'both' == $type )
				$author_links[] = bbp_get_reply_author_avatar( $reply_id, $size );

			// Get display name
			if ( 'name' == $type   || 'both' == $type )
				$author_links[] = bbp_get_reply_author_display_name( $reply_id );

			// Add links if not anonymous
			if ( empty( $anonymous ) ) {
				foreach ( $author_links as $link_text ) {
					$author_link[] = sprintf( '<a href="%1$s"%2$s>%3$s</a>', $author_url, $link_title, $link_text );
				}
				$author_link = join( '&nbsp;', $author_link );

			// No links if anonymous
			} else {
				$author_link = join( '&nbsp;', $author_links );
			}

		// No replies so link is empty
		} else {
			$author_link = '';
		}

		return apply_filters( 'bbp_get_reply_author_link', $author_link, $args );
	}

		/**
		 * Output the author url of the reply
		 *
		 * @since bbPress (r2667)
		 *
		 * @param int $reply_id Optional. Reply id
		 * @uses bbp_get_reply_author_url() To get the reply author url
		 */
		function bbp_reply_author_url( $reply_id = 0 ) {
			echo bbp_get_reply_author_url( $reply_id );
		}
			/**
			 * Return the author url of the reply
			 *
			 * @since bbPress (r22667)
			 *
			 * @param int $reply_id Optional. Reply id
			 * @uses bbp_get_reply_id() To get the reply id
			 * @uses bbp_is_reply_anonymous() To check if the reply
			 *                                 is by an anonymous
			 *                                 user
			 * @uses bbp_get_reply_author_id() To get the reply
			 *                                  author id
			 * @uses bbp_get_user_profile_url() To get the user
			 *                                   profile url
			 * @uses get_post_meta() To get the anonymous poster's
			 *                        website url
			 * @uses apply_filters() Calls bbp_get_reply_author_url
			 *                        with the author url & reply id
			 * @return string Author URL of the reply
			 */
			function bbp_get_reply_author_url( $reply_id = 0 ) {
				$reply_id = bbp_get_reply_id( $reply_id );

				// Check for anonymous user
				if ( !bbp_is_reply_anonymous( $reply_id ) )
					$author_url = bbp_get_user_profile_url( bbp_get_reply_author_id( $reply_id ) );
				else
					if ( !$author_url = get_post_meta( $reply_id, '_bbp_anonymous_website', true ) )
						$author_url = '';

				return apply_filters( 'bbp_get_reply_author_url', $author_url, $reply_id );
			}

/**
 * Output the topic title a reply belongs to
 *
 * @since bbPress (r2553)
 *
 * @param int $reply_id Optional. Reply id
 * @uses bbp_get_reply_topic_title() To get the reply topic title
 */
function bbp_reply_topic_title( $reply_id = 0 ) {
	echo bbp_get_reply_topic_title( $reply_id );
}
	/**
	 * Return the topic title a reply belongs to
	 *
	 * @since bbPress (r2553)
	 *
	 * @param int $reply_id Optional. Reply id
	 * @uses bbp_get_reply_id() To get the reply id
	 * @uses bbp_get_reply_topic_id() To get the reply topic id
	 * @uses bbp_get_topic_title() To get the reply topic title
	 * @uses apply_filters() Calls 'bbp_get_reply_topic_title' with the
	 *                        topic title and reply id
	 * @return string Reply's topic's title
	 */
	function bbp_get_reply_topic_title( $reply_id = 0 ) {
		$reply_id = bbp_get_reply_id( $reply_id );
		$topic_id = bbp_get_reply_topic_id( $reply_id );

		return apply_filters( 'bbp_get_reply_topic_title', bbp_get_topic_title( $topic_id ), $reply_id );
	}

/**
 * Output the topic id a reply belongs to
 *
 * @since bbPress (r2553)
 *
 * @param int $reply_id Optional. Reply id
 * @uses bbp_get_reply_topic_id() To get the reply topic id
 */
function bbp_reply_topic_id( $reply_id = 0 ) {
	echo bbp_get_reply_topic_id( $reply_id );
}
	/**
	 * Return the topic id a reply belongs to
	 *
	 * @since bbPress (r2553)
	 *
	 * @param int $reply_id Optional. Reply id
	 * @uses bbp_get_reply_id() To get the reply id
	 * @uses get_post_meta() To get the reply topic id from meta
	 * @uses get_post_ancestors() To get the reply's ancestors
	 * @uses get_post_field() To get the ancestor's post type
	 * @uses bbp_get_topic_post_type() To get the topic post type
	 * @uses bbp_update_reply_topic_id() To update the reply topic id
	 * @uses bbp_get_topic_id() To get the topic id
	 * @uses apply_filters() Calls 'bbp_get_reply_topic_id' with the topic
	 *                        id and reply id
	 * @return int Reply's topic id
	 */
	function bbp_get_reply_topic_id( $reply_id = 0 ) {

		// Assume there is no topic id
		$topic_id = 0;

		// Check that reply_id is valid
		if ( $reply_id = bbp_get_reply_id( $reply_id ) )

			// Get topic_id from reply
			if ( $topic_id = get_post_meta( $reply_id, '_bbp_topic_id', true ) )

				// Validate the topic_id
				$topic_id = bbp_get_topic_id( $topic_id );

		return apply_filters( 'bbp_get_reply_topic_id', (int) $topic_id, $reply_id );
	}

/**
 * Output the forum id a reply belongs to
 *
 * @since bbPress (r2679)
 *
 * @param int $reply_id Optional. Reply id
 * @uses bbp_get_reply_forum_id() To get the reply forum id
 */
function bbp_reply_forum_id( $reply_id = 0 ) {
	echo bbp_get_reply_forum_id( $reply_id );
}
	/**
	 * Return the forum id a reply belongs to
	 *
	 * @since bbPress (r2679)
	 *
	 * @param int $reply_id Optional. Reply id
	 * @uses bbp_get_reply_id() To get the reply id
	 * @uses get_post_meta() To get the reply forum id
	 * @uses apply_filters() Calls 'bbp_get_reply_forum_id' with the forum
	 *                        id and reply id
	 * @return int Reply's forum id
	 */
	function bbp_get_reply_forum_id( $reply_id = 0 ) {

		// Assume there is no forum
		$forum_id = 0;

		// Check that reply_id is valid
		if ( $reply_id = bbp_get_reply_id( $reply_id ) )

			// Get forum_id from reply
			if ( $forum_id = get_post_meta( $reply_id, '_bbp_forum_id', true ) )

				// Validate the forum_id
				$forum_id = bbp_get_forum_id( $forum_id );

		return apply_filters( 'bbp_get_reply_forum_id', (int) $forum_id, $reply_id );
	}

/**
 * Output the numeric position of a reply within a topic
 *
 * @since bbPress (r2984)
 *
 * @param int $reply_id Optional. Reply id
 * @uses bbp_get_reply_position() To get the reply position
 */
function bbp_reply_position( $reply_id = 0 ) {
	echo bbp_get_reply_position( $reply_id );
}
	/**
	 * Return the numeric position of a reply within a topic
	 *
	 * @since bbPress (r2984)
	 *
	 * @param int $reply_id
	 * @uses bbp_get_reply_id() To get the reply id
	 * @uses bbp_get_reply_topic_id() Get the topic id of the reply id
	 * @uses bbp_get_topic_reply_count() To get the topic reply count
	 * @uses bbp_get_reply_post_type() To get the reply post type
	 * @uses bbp_get_public_child_ids() To get the reply ids of the topic id
	 * @uses bbp_show_lead_topic() Bump the count if lead topic is included
	 * @uses apply_filters() Calls 'bbp_get_reply_position' with the reply
	 *                        position, reply id and topic id
	 * @return int Reply position
	 */
	function bbp_get_reply_position( $reply_id = 0 ) {

		// Get required data
		$reply_position  = 0;
		$reply_id        = bbp_get_reply_id      ( $reply_id );
		$topic_id        = bbp_get_reply_topic_id( $reply_id );

		// Make sure the topic has replies before running another query
		if ( $reply_count = bbp_get_topic_reply_count( $topic_id ) ) {

			// Get reply id's
			if ( $topic_replies = bbp_get_public_child_ids( $topic_id, bbp_get_reply_post_type() ) ) {

				// Reverse replies array and search for current reply position
				$topic_replies  = array_reverse( $topic_replies );

				// Position found
				if ( $reply_position = array_search( (string) $reply_id, $topic_replies ) ) {

					// Bump if topic is in replies loop
					if ( !bbp_show_lead_topic() )
						$reply_position++;

					// Bump now so we don't need to do math later
					$reply_position++;
				}
			}
		}

		return apply_filters( 'bbp_get_reply_position', (int) $reply_position, $reply_id, $topic_id );
	}

/** Reply Admin Links *********************************************************/

/**
 * Output admin links for reply
 *
 * @since bbPress (r2667)
 *
 * @param mixed $args See {@link bbp_get_reply_admin_links()}
 * @uses bbp_get_reply_admin_links() To get the reply admin links
 */
function bbp_reply_admin_links( $args = '' ) {
	echo bbp_get_reply_admin_links( $args );
}
	/**
	 * Return admin links for reply
	 *
	 * @since bbPress (r2667)
	 *
	 * @param mixed $args This function supports these arguments:
	 *  - id: Optional. Reply id
	 *  - before: HTML before the links. Defaults to
	 *             '<span class="bbp-admin-links">'
	 *  - after: HTML after the links. Defaults to '</span>'
	 *  - sep: Separator. Defaults to ' | '
	 *  - links: Array of the links to display. By default, edit, trash,
	 *            spam and topic split links are displayed
	 * @uses bbp_is_topic() To check if it's the topic page
	 * @uses bbp_is_reply() To check if it's the reply page
	 * @uses bbp_get_reply_id() To get the reply id
	 * @uses bbp_get_reply_edit_link() To get the reply edit link
	 * @uses bbp_get_reply_trash_link() To get the reply trash link
	 * @uses bbp_get_reply_spam_link() To get the reply spam link
	 * @uses bbp_get_topic_split_link() To get the topic split link
	 * @uses current_user_can() To check if the current user can edit or
	 *                           delete the reply
	 * @uses apply_filters() Calls 'bbp_get_reply_admin_links' with the
	 *                        reply admin links and args
	 * @return string Reply admin links
	 */
	function bbp_get_reply_admin_links( $args = '' ) {
		global $bbp;

		$defaults = array (
			'id'     => 0,
			'before' => '<span class="bbp-admin-links">',
			'after'  => '</span>',
			'sep'    => ' | ',
			'links'  => array()
		);

		$r = wp_parse_args( $args, $defaults );

		$r['id'] = bbp_get_reply_id( (int) $r['id'] );

		// If post is a topic, return the topic admin links instead
		if ( bbp_is_topic( $r['id'] ) )
			return bbp_get_topic_admin_links( $args );

		// If post is not a reply, return
		if ( !bbp_is_reply( $r['id'] ) )
			return;

		// Make sure user can edit this reply
		if ( !current_user_can( 'edit_reply', $r['id'] ) )
			return;

		// If topic is trashed, do not show admin links
		if ( bbp_is_topic_trash( bbp_get_reply_topic_id( $r['id'] ) ) )
			return;

		// If no links were passed, default to the standard
		if ( empty( $r['links'] ) ) {
			$r['links'] = array (
				'edit'  => bbp_get_reply_edit_link ( $r ),
				'trash' => bbp_get_reply_trash_link( $r ),
				'spam'  => bbp_get_reply_spam_link ( $r ),
				'split' => bbp_get_topic_split_link( $r )
			);
		}

		// Check caps for trashing the topic
		if ( !current_user_can( 'delete_reply', $r['id'] ) && !empty( $r['links']['trash'] ) )
			unset( $r['links']['trash'] );

		// See if links need to be unset
		$reply_status = bbp_get_reply_status( $r['id'] );
		if ( in_array( $reply_status, array( $bbp->spam_status_id, $bbp->trash_status_id ) ) ) {

			// Spam link shouldn't be visible on trashed topics
			if ( $reply_status == $bbp->trash_status_id )
				unset( $r['links']['spam'] );

			// Trash link shouldn't be visible on spam topics
			elseif ( isset( $r['links']['trash'] ) && $reply_status == $bbp->spam_status_id )
				unset( $r['links']['trash'] );
		}

		// Process the admin links
		$links = implode( $r['sep'], array_filter( $r['links'] ) );

		return apply_filters( 'bbp_get_reply_admin_links', $r['before'] . $links . $r['after'], $args );
	}

/**
 * Output the edit link of the reply
 *
 * @since bbPress (r2740)
 *
 * @param mixed $args See {@link bbp_get_reply_edit_link()}
 * @uses bbp_get_reply_edit_link() To get the reply edit link
 */
function bbp_reply_edit_link( $args = '' ) {
	echo bbp_get_reply_edit_link( $args );
}

	/**
	 * Return the edit link of the reply
	 *
	 * @since bbPress (r2740)
	 *
	 * @param mixed $args This function supports these arguments:
	 *  - id: Reply id
	 *  - link_before: HTML before the link
	 *  - link_after: HTML after the link
	 *  - edit_text: Edit text. Defaults to 'Edit'
	 * @uses bbp_get_reply_id() To get the reply id
	 * @uses bbp_get_reply() To get the reply
	 * @uses current_user_can() To check if the current user can edit the
	 *                           reply
	 * @uses bbp_get_reply_edit_url() To get the reply edit url
	 * @uses apply_filters() Calls 'bbp_get_reply_edit_link' with the reply
	 *                        edit link and args
	 * @return string Reply edit link
	 */
	function bbp_get_reply_edit_link( $args = '' ) {
		$defaults = array (
			'id'           => 0,
			'link_before'  => '',
			'link_after'   => '',
			'edit_text'    => __( 'Edit', 'bbpress' )
		);

		$r = wp_parse_args( $args, $defaults );
		extract( $r );

		$reply = bbp_get_reply( bbp_get_reply_id( (int) $id ) );

		// Bypass check if user has caps
		if ( !is_super_admin() || !current_user_can( 'edit_others_replies' ) ) {

			// User cannot edit or it is past the lock time
			if ( empty( $reply ) || !current_user_can( 'edit_reply', $reply->ID ) || bbp_past_edit_lock( $reply->post_date_gmt ) )
				return;
		}

		// No uri to edit reply
		if ( !$uri = bbp_get_reply_edit_url( $id ) )
			return;

		return apply_filters( 'bbp_get_reply_edit_link', $link_before . '<a href="' . $uri . '">' . $edit_text . '</a>' . $link_after, $args );
	}

/**
 * Output URL to the reply edit page
 *
 * @since bbPress (r2753)
 *
 * @param int $reply_id Optional. Reply id
 * @uses bbp_get_reply_edit_url() To get the reply edit url
 */
function bbp_reply_edit_url( $reply_id = 0 ) {
	echo bbp_get_reply_edit_url( $reply_id );
}
	/**
	 * Return URL to the reply edit page
	 *
	 * @since bbPress (r2753)
	 *
	 * @param int $reply_id Optional. Reply id
	 * @uses bbp_get_reply_id() To get the reply id
	 * @uses bbp_get_reply() To get the reply
	 * @uses bbp_get_reply_post_type() To get the reply post type
	 * @uses add_query_arg() To add custom args to the url
	 * @uses home_url() To get the home url
	 * @uses apply_filters() Calls 'bbp_get_reply_edit_url' with the edit
	 *                        url and reply id
	 * @return string Reply edit url
	 */
	function bbp_get_reply_edit_url( $reply_id = 0 ) {
		global $wp_rewrite, $bbp;

		if ( !$reply = bbp_get_reply( bbp_get_reply_id( $reply_id ) ) )
			return;

		// Pretty permalinks
		if ( $wp_rewrite->using_permalinks() ) {
			$url = $wp_rewrite->root . $bbp->reply_slug . '/' . $reply->post_name . '/edit';
			$url = home_url( user_trailingslashit( $url ) );

		// Unpretty permalinks
		} else {
			$url = add_query_arg( array( bbp_get_reply_post_type() => $reply->post_name, 'edit' => '1' ), home_url( '/' ) );
		}

		return apply_filters( 'bbp_get_reply_edit_url', $url, $reply_id );
	}

/**
 * Output the trash link of the reply
 *
 * @since bbPress (r2740)
 *
 * @param mixed $args See {@link bbp_get_reply_trash_link()}
 * @uses bbp_get_reply_trash_link() To get the reply trash link
 */
function bbp_reply_trash_link( $args = '' ) {
	echo bbp_get_reply_trash_link( $args );
}

	/**
	 * Return the trash link of the reply
	 *
	 * @since bbPress (r2740)
	 *
	 * @param mixed $args This function supports these arguments:
	 *  - id: Reply id
	 *  - link_before: HTML before the link
	 *  - link_after: HTML after the link
	 *  - sep: Separator
	 *  - trash_text: Trash text
	 *  - restore_text: Restore text
	 *  - delete_text: Delete text
	 * @uses bbp_get_reply_id() To get the reply id
	 * @uses bbp_get_reply() To get the reply
	 * @uses current_user_can() To check if the current user can delete the
	 *                           reply
	 * @uses bbp_is_reply_trash() To check if the reply is trashed
	 * @uses bbp_get_reply_status() To get the reply status
	 * @uses add_query_arg() To add custom args to the url
	 * @uses wp_nonce_url() To nonce the url
	 * @uses esc_url() To escape the url
	 * @uses bbp_get_reply_edit_url() To get the reply edit url
	 * @uses apply_filters() Calls 'bbp_get_reply_trash_link' with the reply
	 *                        trash link and args
	 * @return string Reply trash link
	 */
	function bbp_get_reply_trash_link( $args = '' ) {
		$defaults = array (
			'id'           => 0,
			'link_before'  => '',
			'link_after'   => '',
			'sep'          => ' | ',
			'trash_text'   => __( 'Trash',   'bbpress' ),
			'restore_text' => __( 'Restore', 'bbpress' ),
			'delete_text'  => __( 'Delete',  'bbpress' )
		);

		$r = wp_parse_args( $args, $defaults );
		extract( $r );

		$actions = array();
		$reply   = bbp_get_reply( bbp_get_reply_id( (int) $id ) );

		if ( empty( $reply ) || !current_user_can( 'delete_reply', $reply->ID ) )
			return;

		if ( bbp_is_reply_trash( $reply->ID ) )
			$actions['untrash'] = '<a title="' . esc_attr( __( 'Restore this item from the Trash', 'bbpress' ) ) . '" href="' . esc_url( wp_nonce_url( add_query_arg( array( 'action' => 'bbp_toggle_reply_trash', 'sub_action' => 'untrash', 'reply_id' => $reply->ID ) ), 'untrash-' . $reply->post_type . '_' . $reply->ID ) ) . '" onclick="return confirm(\'' . esc_js( __( 'Are you sure you want to restore that?', 'bbpress' ) ) . '\');">' . esc_html( $restore_text ) . '</a>';
		elseif ( EMPTY_TRASH_DAYS )
			$actions['trash']   = '<a title="' . esc_attr( __( 'Move this item to the Trash', 'bbpress' ) ) . '" href="' . esc_url( wp_nonce_url( add_query_arg( array( 'action' => 'bbp_toggle_reply_trash', 'sub_action' => 'trash', 'reply_id' => $reply->ID ) ), 'trash-' . $reply->post_type . '_' . $reply->ID ) ) . '" onclick="return confirm(\'' . esc_js( __( 'Are you sure you want to trash that?', 'bbpress' ) ) . '\' );">' . esc_html( $trash_text ) . '</a>';

		if ( bbp_is_reply_trash( $reply->ID ) || !EMPTY_TRASH_DAYS )
			$actions['delete']  = '<a title="' . esc_attr( __( 'Delete this item permanently', 'bbpress' ) ) . '" href="' . esc_url( wp_nonce_url( add_query_arg( array( 'action' => 'bbp_toggle_reply_trash', 'sub_action' => 'delete', 'reply_id' => $reply->ID ) ), 'delete-' . $reply->post_type . '_' . $reply->ID ) ) . '" onclick="return confirm(\'' . esc_js( __( 'Are you sure you want to delete that permanently?', 'bbpress' ) ) . '\' );">' . esc_html( $delete_text ) . '</a>';

		// Process the admin links
		$actions = implode( $sep, $actions );

		return apply_filters( 'bbp_get_reply_trash_link', $link_before . $actions . $link_after, $args );
	}

/**
 * Output the spam link of the reply
 *
 * @since bbPress (r2740)
 *
 * @param mixed $args See {@link bbp_get_reply_spam_link()}
 * @uses bbp_get_reply_spam_link() To get the reply spam link
 */
function bbp_reply_spam_link( $args = '' ) {
	echo bbp_get_reply_spam_link( $args );
}

	/**
	 * Return the spam link of the reply
	 *
	 * @since bbPress (r2740)
	 *
	 * @param mixed $args This function supports these arguments:
	 *  - id: Reply id
	 *  - link_before: HTML before the link
	 *  - link_after: HTML after the link
	 *  - spam_text: Spam text
	 *  - unspam_text: Unspam text
	 * @uses bbp_get_reply_id() To get the reply id
	 * @uses bbp_get_reply() To get the reply
	 * @uses current_user_can() To check if the current user can edit the
	 *                           reply
	 * @uses bbp_is_reply_spam() To check if the reply is marked as spam
	 * @uses add_query_arg() To add custom args to the url
	 * @uses wp_nonce_url() To nonce the url
	 * @uses esc_url() To escape the url
	 * @uses bbp_get_reply_edit_url() To get the reply edit url
	 * @uses apply_filters() Calls 'bbp_get_reply_spam_link' with the reply
	 *                        spam link and args
	 * @return string Reply spam link
	 */
	function bbp_get_reply_spam_link( $args = '' ) {
		$defaults = array (
			'id'           => 0,
			'link_before'  => '',
			'link_after'   => '',
			'spam_text'    => __( 'Spam',   'bbpress' ),
			'unspam_text'  => __( 'Unspam', 'bbpress' )
		);

		$r = wp_parse_args( $args, $defaults );
		extract( $r );

		$reply = bbp_get_reply( bbp_get_reply_id( (int) $id ) );

		if ( empty( $reply ) || !current_user_can( 'moderate', $reply->ID ) )
			return;

		$display  = bbp_is_reply_spam( $reply->ID ) ? $unspam_text : $spam_text;

		$uri = add_query_arg( array( 'action' => 'bbp_toggle_reply_spam', 'reply_id' => $reply->ID ) );
		$uri = esc_url( wp_nonce_url( $uri, 'spam-reply_' . $reply->ID ) );

		return apply_filters( 'bbp_get_reply_spam_link', $link_before . '<a href="' . $uri . '">' . $display . '</a>' . $link_after, $args );
	}

/**
 * Split topic link
 *
 * Output the split link of the topic (but is bundled with each topic)
 *
 * @since bbPress (r2756)
 *
 * @param mixed $args See {@link bbp_get_topic_split_link()}
 * @uses bbp_get_topic_split_link() To get the topic split link
 */
function bbp_topic_split_link( $args = '' ) {
	echo bbp_get_topic_split_link( $args );
}

	/**
	 * Get split topic link
	 *
	 * Return the split link of the topic (but is bundled with each reply)
	 *
	 * @since bbPress (r2756)
	 *
	 * @param mixed $args This function supports these arguments:
	 *  - id: Reply id
	 *  - link_before: HTML before the link
	 *  - link_after: HTML after the link
	 *  - split_text: Split text
	 *  - split_title: Split title attribute
	 * @uses bbp_get_reply_id() To get the reply id
	 * @uses bbp_get_reply() To get the reply
	 * @uses current_user_can() To check if the current user can edit the
	 *                           topic
	 * @uses bbp_get_reply_topic_id() To get the reply topic id
	 * @uses bbp_get_topic_edit_url() To get the topic edit url
	 * @uses add_query_arg() To add custom args to the url
	 * @uses wp_nonce_url() To nonce the url
	 * @uses esc_url() To escape the url
	 * @uses apply_filters() Calls 'bbp_get_topic_split_link' with the topic
	 *                        split link and args
	 * @return string Reply spam link
	 */
	function bbp_get_topic_split_link( $args = '' ) {
		$defaults = array (
			'id'          => 0,
			'link_before' => '',
			'link_after'  => '',
			'split_text'  => __( 'Split',                           'bbpress' ),
			'split_title' => __( 'Split the topic from this reply', 'bbpress' )
		);

		$r = wp_parse_args( $args, $defaults );
		extract( $r );

		$reply_id = bbp_get_reply_id( $id );
		$topic_id = bbp_get_reply_topic_id( $reply_id );

		if ( empty( $reply_id ) || !current_user_can( 'moderate', $topic_id ) )
			return;

		$uri = esc_url(
			add_query_arg(
				array(
					'action'   => 'split',
					'reply_id' => $reply_id
				),
			bbp_get_topic_edit_url( $topic_id )
		) );

		return apply_filters( 'bbp_get_topic_split_link', $link_before . '<a href="' . $uri . '" title="' . esc_attr( $split_title ) . '">' . $split_text . '</a>' . $link_after, $args );
	}

/**
 * Output the row class of a reply
 *
 * @since bbPress (r2678)
 */
function bbp_reply_class() {
	echo bbp_get_reply_class();
}
	/**
	 * Return the row class of a reply
	 *
	 * @since bbPress (r2678)
	 *
	 * @uses post_class() To get all the classes including ours
	 * @uses apply_filters() Calls 'bbp_get_reply_class' with the classes
	 * @return string Row class of the reply
	 */
	function bbp_get_reply_class() {
		global $bbp;

		$count     = isset( $bbp->reply_query->current_post ) ? $bbp->reply_query->current_post : 1;
		$alternate = (int) $count % 2 ? 'even' : 'odd';
		$post      = post_class( array( $alternate ) );

		return apply_filters( 'bbp_reply_class', $post );
	}

/**
 * Output the topic pagination count
 *
 * @since bbPress (r2519)
 *
 * @uses bbp_get_topic_pagination_count() To get the topic pagination count
 */
function bbp_topic_pagination_count() {
	echo bbp_get_topic_pagination_count();
}
	/**
	 * Return the topic pagination count
	 *
	 * @since bbPress (r2519)
	 *
	 * @uses bbp_number_format() To format the number value
	 * @uses bbp_show_lead_topic() Are we showing the topic as a lead?
	 * @uses apply_filters() Calls 'bbp_get_topic_pagination_count' with the
	 *                        pagination count
	 * @return string Topic pagination count
	 */
	function bbp_get_topic_pagination_count() {
		global $bbp;

		// Set pagination values
		$start_num = intval( ( $bbp->reply_query->paged - 1 ) * $bbp->reply_query->posts_per_page ) + 1;
		$from_num  = bbp_number_format( $start_num );
		$to_num    = bbp_number_format( ( $start_num + ( $bbp->reply_query->posts_per_page - 1 ) > $bbp->reply_query->found_posts ) ? $bbp->reply_query->found_posts : $start_num + ( $bbp->reply_query->posts_per_page - 1 ) );
		$total     = bbp_number_format( $bbp->reply_query->found_posts );

		// We are not including the lead topic
		if ( bbp_show_lead_topic() ) {

			// Set return string
			if ( $total > 1 && (int) $from_num == (int) $to_num )
				$retstr = sprintf( __( 'Viewing reply %1$s (of %2$s total)', 'bbpress' ), $from_num, $total );
			elseif ( $total > 1 && empty( $to_num ) )
				$retstr = sprintf( __( 'Viewing %1$s replies', 'bbpress' ), $total );
			elseif ( $total > 1 && (int) $from_num != (int) $to_num )
				$retstr = sprintf( __( 'Viewing %1$s replies - %2$s through %3$s (of %4$s total)', 'bbpress' ), $bbp->reply_query->post_count, $from_num, $to_num, $total );
			else
				$retstr = sprintf( __( 'Viewing %1$s reply', 'bbpress' ), $total );

		// We are including the lead topic
		} else {

			// Set return string
			if ( $total > 1 && (int) $from_num == (int) $to_num )
				$retstr = sprintf( __( 'Viewing post %1$s (of %2$s total)', 'bbpress' ), $from_num, $total );
			elseif ( $total > 1 && empty( $to_num ) )
				$retstr = sprintf( __( 'Viewing %1$s posts', 'bbpress' ), $total );
			elseif ( $total > 1 && (int) $from_num != (int) $to_num )
				$retstr = sprintf( __( 'Viewing %1$s posts - %2$s through %3$s (of %4$s total)', 'bbpress' ), $bbp->reply_query->post_count, $from_num, $to_num, $total );
			elseif ( $total == 1 )
				$retstr = sprintf( __( 'Viewing %1$s post', 'bbpress' ), $total );
		}

		// Filter and return
		return apply_filters( 'bbp_get_topic_pagination_count', $retstr );
	}

/**
 * Output topic pagination links
 *
 * @since bbPress (r2519)
 *
 * @uses bbp_get_topic_pagination_links() To get the topic pagination links
 */
function bbp_topic_pagination_links() {
	echo bbp_get_topic_pagination_links();
}
	/**
	 * Return topic pagination links
	 *
	 * @since bbPress (r2519)
	 *
	 * @uses apply_filters() Calls 'bbp_get_topic_pagination_links' with the
	 *                        pagination links
	 * @return string Topic pagination links
	 */
	function bbp_get_topic_pagination_links() {
		global $bbp;

		if ( !isset( $bbp->reply_query->pagination_links ) || empty( $bbp->reply_query->pagination_links ) )
			return false;

		return apply_filters( 'bbp_get_topic_pagination_links', $bbp->reply_query->pagination_links );
	}

/** Forms *********************************************************************/

/**
 * Output the value of reply content field
 *
 * @since bbPress {unknown}
 *
 * @uses bbp_get_form_reply_content() To get value of reply content field
 */
function bbp_form_reply_content() {
	echo bbp_get_form_reply_content();
}
	/**
	 * Return the value of reply content field
	 *
	 * @since bbPress {unknown}
	 *
	 * @uses bbp_is_reply_edit() To check if it's the reply edit page
	 * @uses apply_filters() Calls 'bbp_get_form_reply_content' with the content
	 * @return string Value of reply content field
	 */
	function bbp_get_form_reply_content() {
		global $post;

		// Get _POST data
		if ( 'POST' == strtoupper( $_SERVER['REQUEST_METHOD'] ) && isset( $_POST['bbp_reply_content'] ) )
			$reply_content = $_POST['bbp_reply_content'];

		// Get edit data
		elseif ( !empty( $post->post_content ) && bbp_is_reply_edit() )
			$reply_content = $post->post_content;

		// No data
		else
			$reply_content = '';

		return apply_filters( 'bbp_get_form_reply_content', esc_textarea( $reply_content ) );
	}

/**
 * Output checked value of reply log edit field
 *
 * @since bbPress {unknown}
 *
 * @uses bbp_get_form_reply_log_edit() To get the reply log edit value
 */
function bbp_form_reply_log_edit() {
	echo bbp_get_form_reply_log_edit();
}
	/**
	 * Return checked value of reply log edit field
	 *
	 * @since bbPress {unknown}
	 *
	 * @uses apply_filters() Calls 'bbp_get_form_reply_log_edit' with the
	 *                        log edit value
	 * @return string Reply log edit checked value
	 */
	function bbp_get_form_reply_log_edit() {
		global $post;

		// Get _POST data
		if ( 'post' == strtolower( $_SERVER['REQUEST_METHOD'] ) && isset( $_POST['bbp_log_reply_edit'] ) )
			$reply_revision = $_POST['bbp_log_reply_edit'];

		// No data
		else
			$reply_revision = 1;

		return apply_filters( 'bbp_get_form_reply_log_edit', checked( true, $reply_revision, false ) );
	}

/**
 * Output the value of the reply edit reason
 *
 * @since bbPress {unknown}
 *
 * @uses bbp_get_form_reply_edit_reason() To get the reply edit reason value
 */
function bbp_form_reply_edit_reason() {
	echo bbp_get_form_reply_edit_reason();
}
	/**
	 * Return the value of the reply edit reason
	 *
	 * @since bbPress {unknown}
	 *
	 * @uses apply_filters() Calls 'bbp_get_form_reply_edit_reason' with the
	 *                        reply edit reason value
	 * @return string Reply edit reason value
	 */
	function bbp_get_form_reply_edit_reason() {
		global $post;

		// Get _POST data
		if ( 'post' == strtolower( $_SERVER['REQUEST_METHOD'] ) && isset( $_POST['bbp_reply_edit_reason'] ) )
			$reply_edit_reason = $_POST['bbp_reply_edit_reason'];

		// No data
		else
			$reply_edit_reason = '';

		return apply_filters( 'bbp_get_form_reply_edit_reason', esc_attr( $reply_edit_reason ) );
	}

?>
