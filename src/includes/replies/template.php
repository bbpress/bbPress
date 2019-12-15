<?php

/**
 * bbPress Reply Template Tags
 *
 * @package bbPress
 * @subpackage TemplateTags
 */

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

/** Post Type *****************************************************************/

/**
 * Return the unique id of the custom post type for replies
 *
 * @since 2.0.0 bbPress (r2857)
 *
 */
function bbp_reply_post_type() {
	echo bbp_get_reply_post_type();
}
	/**
	 * Return the unique id of the custom post type for replies
	 *
	 * @since 2.0.0 bbPress (r2857)
	 *
	 *                        post type id
	 * @return string The unique reply post type id
	 */
	function bbp_get_reply_post_type() {

		// Filter & return
		return apply_filters( 'bbp_get_reply_post_type', bbpress()->reply_post_type );
	}

/**
 * Return array of labels used by the reply post type
 *
 * @since 2.5.0 bbPress (r5129)
 *
 * @return array
 */
function bbp_get_reply_post_type_labels() {

	// Filter & return
	return (array) apply_filters( 'bbp_get_reply_post_type_labels', array(
		'name'                     => esc_attr__( 'Replies',                    'bbpress' ),
		'menu_name'                => esc_attr__( 'Replies',                    'bbpress' ),
		'singular_name'            => esc_attr_x( 'Reply', 'noun',              'bbpress' ),
		'all_items'                => esc_attr__( 'All Replies',                'bbpress' ),
		'add_new'                  => esc_attr__( 'Add New',                    'bbpress' ),
		'add_new_item'             => esc_attr__( 'Create New Reply',           'bbpress' ),
		'edit'                     => esc_attr__( 'Edit',                       'bbpress' ),
		'edit_item'                => esc_attr__( 'Edit Reply',                 'bbpress' ),
		'new_item'                 => esc_attr__( 'New Reply',                  'bbpress' ),
		'view'                     => esc_attr__( 'View Reply',                 'bbpress' ),
		'view_item'                => esc_attr__( 'View Reply',                 'bbpress' ),
		'view_items'               => esc_attr__( 'View Replies',               'bbpress' ),
		'search_items'             => esc_attr__( 'Search Replies',             'bbpress' ),
		'not_found'                => esc_attr__( 'No replies found',           'bbpress' ),
		'not_found_in_trash'       => esc_attr__( 'No replies found in Trash',  'bbpress' ),
		'filter_items_list'        => esc_attr__( 'Filter replies list',        'bbpress' ),
		'items_list'               => esc_attr__( 'Replies list',               'bbpress' ),
		'items_list_navigation'    => esc_attr__( 'Replies list navigation',    'bbpress' ),
		'parent_item_colon'        => esc_attr__( 'Parent Topic:',              'bbpress' ),
		'archives'                 => esc_attr__( 'Forum Replies',              'bbpress' ),
		'attributes'               => esc_attr__( 'Reply Attributes',           'bbpress' ),
		'insert_into_item'         => esc_attr__( 'Insert into reply',          'bbpress' ),
		'uploaded_to_this_item'    => esc_attr__( 'Uploaded to this reply',     'bbpress' ),
		'featured_image'           => esc_attr__( 'Reply Image',                'bbpress' ),
		'set_featured_image'       => esc_attr__( 'Set reply image',            'bbpress' ),
		'remove_featured_image'    => esc_attr__( 'Remove reply image',         'bbpress' ),
		'use_featured_image'       => esc_attr__( 'Use as reply image',         'bbpress' ),
		'item_published'           => esc_attr__( 'Reply published.',           'bbpress' ),
		'item_published_privately' => esc_attr__( 'Reply published privately.', 'bbpress' ),
		'item_reverted_to_draft'   => esc_attr__( 'Reply reverted to draft.',   'bbpress' ),
		'item_scheduled'           => esc_attr__( 'Reply scheduled.',           'bbpress' ),
		'item_updated'             => esc_attr__( 'Reply updated.',             'bbpress' )
	) );
}

/**
 * Return array of reply post type rewrite settings
 *
 * @since 2.5.0 bbPress (r5129)
 *
 * @return array
 */
function bbp_get_reply_post_type_rewrite() {

	// Filter & return
	return (array) apply_filters( 'bbp_get_reply_post_type_rewrite', array(
		'slug'       => bbp_get_reply_slug(),
		'with_front' => false
	) );
}

/**
 * Return array of features the reply post type supports
 *
 * @since 2.5.0 bbPress (r5129)
 *
 * @return array
 */
function bbp_get_reply_post_type_supports() {

	// Filter & return
	return (array) apply_filters( 'bbp_get_reply_post_type_supports', array(
		'title',
		'editor',
		'revisions'
	) );
}

/** Reply Loop Functions ******************************************************/

/**
 * The main reply loop. WordPress makes this easy for us
 *
 * @since 2.0.0 bbPress (r2553)
 *
 * @param array $args All the arguments supported by {@link WP_Query}
 * @return object Multidimensional array of reply information
 */
function bbp_has_replies( $args = array() ) {

	/** Defaults **************************************************************/

	// Other defaults
	$default_reply_search   = bbp_sanitize_search_request( 'rs' );
	$default_post_parent    = ( bbp_is_single_topic() ) ? bbp_get_topic_id() : 'any';
	$default_post_type      = ( bbp_is_single_topic() && bbp_show_lead_topic() ) ? bbp_get_reply_post_type() : array( bbp_get_topic_post_type(), bbp_get_reply_post_type() );
	$default_thread_replies = (bool) ( bbp_is_single_topic() && bbp_thread_replies() );

	// Default query args
	$default = array(
		'post_type'              => $default_post_type,         // Only replies
		'post_parent'            => $default_post_parent,       // Of this topic
		'posts_per_page'         => bbp_get_replies_per_page(), // This many
		'paged'                  => bbp_get_paged(),            // On this page
		'orderby'                => 'date',                     // Sorted by date
		'order'                  => 'ASC',                      // Oldest to newest
		'hierarchical'           => $default_thread_replies,    // Hierarchical replies
		'ignore_sticky_posts'    => true,                       // Stickies not supported
		'update_post_term_cache' => false,                      // No terms to cache

		// Conditionally prime the cache for all related posts
		'update_post_family_cache' => true
	);

	// Only add 's' arg if searching for replies
	// See https://bbpress.trac.wordpress.org/ticket/2607
	if ( ! empty( $default_reply_search ) ) {
		$default['s'] = $default_reply_search;
	}

	// What are the default allowed statuses (based on user caps)
	if ( bbp_get_view_all( 'edit_others_replies' ) ) {

		// Default view=all statuses
		$post_statuses = array_keys( bbp_get_topic_statuses() );

		// Add support for private status
		if ( current_user_can( 'read_private_replies' ) ) {
			$post_statuses[] = bbp_get_private_status_id();
		}

		// Join post statuses together
		$default['post_status'] = $post_statuses;

	// Lean on the 'perm' query var value of 'readable' to provide statuses
	} else {
		$default['perm'] = 'readable';
	}

	/** Setup *****************************************************************/

	// Parse arguments against default values
	$r = bbp_parse_args( $args, $default, 'has_replies' );

	// Set posts_per_page value if replies are threaded
	$replies_per_page = (int) $r['posts_per_page'];
	if ( true === $r['hierarchical'] ) {
		$r['posts_per_page'] = -1;
	}

	// Get bbPress
	$bbp = bbpress();

	// Call the query
	$bbp->reply_query = new WP_Query( $r );

	// Maybe prime the post author caches
	if ( ! empty( $r['update_post_family_cache'] ) ) {
		bbp_update_post_family_caches( $bbp->reply_query->posts );
	}

	// Add pagination values to query object
	$bbp->reply_query->posts_per_page = (int) $replies_per_page;
	$bbp->reply_query->paged          = (int) $r['paged'];

	// Never home, regardless of what parse_query says
	$bbp->reply_query->is_home        = false;

	// Reset is_single if single topic
	if ( bbp_is_single_topic() ) {
		$bbp->reply_query->is_single = true;
	}

	// Only add reply to if query returned results
	if ( ! empty( $bbp->reply_query->found_posts ) ) {

		// Get reply to for each reply
		foreach ( $bbp->reply_query->posts as &$post ) {

			// Check for reply post type
			if ( bbp_get_reply_post_type() === $post->post_type ) {
				$reply_to = bbp_get_reply_to( $post->ID );

				// Make sure it's a reply to a reply
				if ( empty( $reply_to ) || ( bbp_get_reply_topic_id( $post->ID ) === $reply_to ) ) {
					$reply_to = 0;
				}

				// Add reply_to to the post object so we can walk it later
				$post->reply_to = $reply_to;
			}
		}
	}

	// Only add pagination if query returned results
	if ( ! empty( $bbp->reply_query->found_posts ) && ! empty( $bbp->reply_query->posts_per_page ) ) {

		// Figure out total pages
		if ( true === $r['hierarchical'] ) {
			$walker      = new BBP_Walker_Reply();
			$total_pages = ceil( $walker->get_number_of_root_elements( $bbp->reply_query->posts ) / $bbp->reply_query->posts_per_page );
		} else {

			// Total for pagination boundaries
			$total_pages = ( $bbp->reply_query->posts_per_page === $bbp->reply_query->found_posts )
				? 1
				: ceil( $bbp->reply_query->found_posts / $bbp->reply_query->posts_per_page );

			// Pagination settings with filter
			$bbp_replies_pagination = apply_filters( 'bbp_replies_pagination', array(
				'base'    => bbp_get_replies_pagination_base( bbp_get_topic_id() ),
				'total'   => $total_pages,
				'current' => $bbp->reply_query->paged
			) );

			// Add pagination to query object
			$bbp->reply_query->pagination_links = bbp_paginate_links( $bbp_replies_pagination );
		}
	}

	// Filter & return
	return apply_filters( 'bbp_has_replies', $bbp->reply_query->have_posts(), $bbp->reply_query );
}

/**
 * Whether there are more replies available in the loop
 *
 * @since 2.0.0 bbPress (r2553)
 *
 * @return object Replies information
 */
function bbp_replies() {

	// Put into variable to check against next
	$have_posts = bbpress()->reply_query->have_posts();

	// Reset the post data when finished
	if ( empty( $have_posts ) ) {
		wp_reset_postdata();
	}

	return $have_posts;
}

/**
 * Loads up the current reply in the loop
 *
 * @since 2.0.0 bbPress (r2553)
 *
 * @return object Reply information
 */
function bbp_the_reply() {
	return bbpress()->reply_query->the_post();
}

/**
 * Output reply id
 *
 * @since 2.0.0 bbPress (r2553)
 *
 * @param $reply_id Optional. Used to check emptiness
 */
function bbp_reply_id( $reply_id = 0 ) {
	echo bbp_get_reply_id( $reply_id );
}
	/**
	 * Return the id of the reply in a replies loop
	 *
	 * @since 2.0.0 bbPress (r2553)
	 *
	 * @param $reply_id Optional. Used to check emptiness
	 * @return int The reply id
	 */
	function bbp_get_reply_id( $reply_id = 0 ) {
		$bbp      = bbpress();
		$wp_query = bbp_get_wp_query();

		// Easy empty checking
		if ( ! empty( $reply_id ) && is_numeric( $reply_id ) ) {
			$bbp_reply_id = $reply_id;

		// Currently inside a replies loop
		} elseif ( ! empty( $bbp->reply_query->in_the_loop ) && isset( $bbp->reply_query->post->ID ) ) {
			$bbp_reply_id = $bbp->reply_query->post->ID;

		// Currently inside a search loop
		} elseif ( ! empty( $bbp->search_query->in_the_loop ) && isset( $bbp->search_query->post->ID ) && bbp_is_reply( $bbp->search_query->post->ID ) ) {
			$bbp_reply_id = $bbp->search_query->post->ID;

		// Currently viewing a forum
		} elseif ( ( bbp_is_single_reply() || bbp_is_reply_edit() ) && ! empty( $bbp->current_reply_id ) ) {
			$bbp_reply_id = $bbp->current_reply_id;

		// Currently viewing a reply
		} elseif ( ( bbp_is_single_reply() || bbp_is_reply_edit() ) && isset( $wp_query->post->ID ) ) {
			$bbp_reply_id = $wp_query->post->ID;

		// Fallback
		} else {
			$bbp_reply_id = 0;
		}

		// Filter & return
		return (int) apply_filters( 'bbp_get_reply_id', $bbp_reply_id, $reply_id );
	}

/**
 * Gets a reply
 *
 * @since 2.0.0 bbPress (r2787)
 *
 * @param int|object $reply reply id or reply object
 * @param string $output Optional. OBJECT, ARRAY_A, or ARRAY_N. Default = OBJECT
 * @param string $filter Optional Sanitation filter. See {@link sanitize_post()}
 * @return mixed Null if error or reply (in specified form) if success
 */
function bbp_get_reply( $reply, $output = OBJECT, $filter = 'raw' ) {

	// Maybe get ID from empty or int
	if ( empty( $reply ) || is_numeric( $reply ) ) {
		$reply = bbp_get_reply_id( $reply );
	}

	// Bail if no post object
	$reply = get_post( $reply, OBJECT, $filter );
	if ( empty( $reply ) ) {
		return $reply;
	}

	// Bail if not correct post type
	if ( $reply->post_type !== bbp_get_reply_post_type() ) {
		return null;
	}

	// Default return value is OBJECT
	$retval = $reply;

	// Array A
	if ( $output === ARRAY_A ) {
		$retval = get_object_vars( $reply );

	// Array N
	} elseif ( $output === ARRAY_N ) {
		$retval = array_values( get_object_vars( $reply ) );
	}

	// Filter & return
	return apply_filters( 'bbp_get_reply', $retval, $reply, $output, $filter );
}

/**
 * Output the link to the reply in the reply loop
 *
 * @since 2.0.0 bbPress (r2553)
 *
 * @param int $reply_id Optional. Reply id
 */
function bbp_reply_permalink( $reply_id = 0 ) {
	echo esc_url( bbp_get_reply_permalink( $reply_id ) );
}
	/**
	 * Return the link to the reply
	 *
	 * @since 2.0.0 bbPress (r2553)
	 *
	 * @param int $reply_id Optional. Reply id
	 *                        and reply id
	 * @return string Permanent link to reply
	 */
	function bbp_get_reply_permalink( $reply_id = 0 ) {
		$reply_id = bbp_get_reply_id( $reply_id );

		// Filter & return
		return apply_filters( 'bbp_get_reply_permalink', get_permalink( $reply_id ), $reply_id );
	}

/**
 * Output the paginated url to the reply in the reply loop
 *
 * @since 2.0.0 bbPress (r2679)
 *
 * @param int $reply_id Optional. Reply id
 */
function bbp_reply_url( $reply_id = 0 ) {
	echo esc_url( bbp_get_reply_url( $reply_id ) );
}
	/**
	 * Return the paginated url to the reply in the reply loop
	 *
	 * @since 2.0.0 bbPress (r2679)
	 *
	 * @param int $reply_id Optional. Reply id
	 * @param string $redirect_to Optional. Pass a redirect value for use with
	 *                              shortcodes and other fun things.
	 * @return string Link to reply relative to paginated topic
	 */
	function bbp_get_reply_url( $reply_id = 0, $redirect_to = '' ) {

		// Set needed variables
		$reply_id = bbp_get_reply_id( $reply_id );

		// Juggle reply & topic IDs for unpretty URL formatting
		if ( bbp_is_reply( $reply_id ) ) {
			$topic_id = bbp_get_reply_topic_id( $reply_id );
			$topic    = bbp_get_topic( $topic_id );
		} elseif ( bbp_is_topic( $reply_id ) ) {
			$topic_id = bbp_get_topic_id( $reply_id );
			$topic    = bbp_get_topic( $topic_id );
			$reply_id = $topic_id;
		}

		// Hierarchical reply page
		if ( bbp_thread_replies() ) {
			$reply_page = 1;

		// Standard reply page
		} else {
			$reply_page = ceil( (int) bbp_get_reply_position( $reply_id, $topic_id ) / (int) bbp_get_replies_per_page() );
		}

		// Get links & URLS
		$reply_hash = '#post-' . $reply_id;
		$topic_link = bbp_get_topic_permalink( $topic_id, $redirect_to );
		$topic_url  = remove_query_arg( 'view', $topic_link );

		// Get vars needed to support pending topics with unpretty links
		$has_slug   = ! empty( $topic ) ? $topic->post_name : '';
		$pretty     = bbp_use_pretty_urls();
		$published  = ! bbp_is_topic_pending( $topic_id );

		// Don't include pagination if on first page
		if ( 1 >= $reply_page ) {

			// Pretty permalinks
			if ( ! empty( $has_slug ) && ! empty( $pretty ) && ! empty( $published ) ) {
				$url = user_trailingslashit( $topic_url ) . $reply_hash;

			// Unpretty links
			} else {
				$url = $topic_url . $reply_hash;
			}

		// Include pagination
		} else {

			// Pretty permalinks
			if ( ! empty( $has_slug ) && ! empty( $pretty ) && ! empty( $published ) ) {
				$url = trailingslashit( $topic_url ) . trailingslashit( bbp_get_paged_slug() ) . $reply_page;
				$url = user_trailingslashit( $url ) . $reply_hash;

			// Unpretty links
			} else {
				$url = add_query_arg( 'paged', $reply_page, $topic_url ) . $reply_hash;
			}
		}

		// Add topic view query arg back to end if it is set
		if ( bbp_get_view_all() ) {
			$url = bbp_add_view_all( $url );
		}

		// Filter & return
		return apply_filters( 'bbp_get_reply_url', $url, $reply_id, $redirect_to );
	}

/**
 * Output the title of the reply
 *
 * @since 2.0.0 bbPress (r2553)
 *
 * @param int $reply_id Optional. Reply id
 */
function bbp_reply_title( $reply_id = 0 ) {
	echo bbp_get_reply_title( $reply_id );
}

	/**
	 * Return the title of the reply
	 *
	 * @since 2.0.0 bbPress (r2553)
	 *
	 * @param int $reply_id Optional. Reply id
	 * @return string Title of reply
	 */
	function bbp_get_reply_title( $reply_id = 0 ) {
		$reply_id = bbp_get_reply_id( $reply_id );
		$title    = get_the_title( $reply_id );

		// Filter & return
		return apply_filters( 'bbp_get_reply_title', $title, $reply_id );
	}

	/**
	 * Get empty reply title fallback.
	 *
	 * @since 2.5.0 bbPress (r5177)
	 *
	 * @param string $post_title Required. Reply Title
	 * @param int $post_id Required. Reply ID
	 * @return string Title of reply
	 */
	function bbp_get_reply_title_fallback( $post_title = '', $post_id = 0 ) {

		// Bail if title not empty, or post is not a reply
		if ( ! empty( $post_title ) || ! bbp_is_reply( $post_id ) ) {
			return $post_title;
		}

		// Get reply topic title.
		$topic_title = bbp_get_reply_topic_title( $post_id );

		// Get empty reply title fallback.
		$reply_title = sprintf( esc_html__( 'Reply To: %s', 'bbpress' ), $topic_title );

		// Filter & return
		return apply_filters( 'bbp_get_reply_title_fallback', $reply_title, $post_id, $topic_title );
	}

/**
 * Output the content of the reply
 *
 * @since 2.0.0 bbPress (r2553)
 *
 * @param int $reply_id Optional. reply id
 */
function bbp_reply_content( $reply_id = 0 ) {
	echo bbp_get_reply_content( $reply_id );
}
	/**
	 * Return the content of the reply
	 *
	 * @since 2.0.0 bbPress (r2780)
	 *
	 * @param int $reply_id Optional. reply id
	 * @return string Content of the reply
	 */
	function bbp_get_reply_content( $reply_id = 0 ) {
		$reply_id = bbp_get_reply_id( $reply_id );

		// Check if password is required
		if ( post_password_required( $reply_id ) ) {
			return get_the_password_form();
		}

		$content = get_post_field( 'post_content', $reply_id );

		// Filter & return
		return apply_filters( 'bbp_get_reply_content', $content, $reply_id );
	}

/**
 * Output the excerpt of the reply
 *
 * @since 2.0.0 bbPress (r2751)
 *
 * @param int $reply_id Optional. Reply id
 * @param int $length Optional. Length of the excerpt. Defaults to 100 letters
 */
function bbp_reply_excerpt( $reply_id = 0, $length = 100 ) {
	echo bbp_get_reply_excerpt( $reply_id, $length );
}
	/**
	 * Return the excerpt of the reply
	 *
	 * @since 2.0.0 bbPress (r2751)
	 *
	 * @param int $reply_id Optional. Reply id
	 * @param int $length Optional. Length of the excerpt. Defaults to 100
	 *                     letters
	 * @return string Reply Excerpt
	 */
	function bbp_get_reply_excerpt( $reply_id = 0, $length = 100 ) {
		$reply_id = bbp_get_reply_id( $reply_id );
		$length   = (int) $length;
		$excerpt  = get_post_field( 'post_excerpt', $reply_id );

		if ( empty( $excerpt ) ) {
			$excerpt = bbp_get_reply_content( $reply_id );
		}

		$excerpt = trim ( strip_tags( $excerpt ) );

		// Multibyte support
		if ( function_exists( 'mb_strlen' ) ) {
			$excerpt_length = mb_strlen( $excerpt );
		} else {
			$excerpt_length = strlen( $excerpt );
		}

		if ( ! empty( $length ) && ( $excerpt_length > $length ) ) {
			$excerpt  = mb_substr( $excerpt, 0, $length - 1 );
			$excerpt .= '&hellip;';
		}

		// Filter & return
		return apply_filters( 'bbp_get_reply_excerpt', $excerpt, $reply_id, $length );
	}

/**
 * Output the post date and time of a reply
 *
 * @since 2.2.0 bbPress (r4155)
 *
 * @param int $reply_id Optional. Reply id.
 * @param bool $humanize Optional. Humanize output using time_since
 * @param bool $gmt Optional. Use GMT
 */
function bbp_reply_post_date( $reply_id = 0, $humanize = false, $gmt = false ) {
	echo bbp_get_reply_post_date( $reply_id, $humanize, $gmt );
}
	/**
	 * Return the post date and time of a reply
	 *
	 * @since 2.2.0 bbPress (r4155)
	 *
	 * @param int $reply_id Optional. Reply id.
	 * @param bool $humanize Optional. Humanize output using time_since
	 * @param bool $gmt Optional. Use GMT
	 * @return string
	 */
	function bbp_get_reply_post_date( $reply_id = 0, $humanize = false, $gmt = false ) {
		$reply_id = bbp_get_reply_id( $reply_id );

		// 4 days, 4 hours ago
		if ( ! empty( $humanize ) ) {
			$gmt_s  = ! empty( $gmt ) ? 'G' : 'U';
			$date   = get_post_time( $gmt_s, $gmt, $reply_id );
			$time   = false; // For filter below
			$result = bbp_get_time_since( $date );

		// August 4, 2012 at 2:37 pm
		} else {
			$date   = get_post_time( get_option( 'date_format' ), $gmt, $reply_id, true );
			$time   = get_post_time( get_option( 'time_format' ), $gmt, $reply_id, true );
			$result = sprintf( _x( '%1$s at %2$s', 'date at time', 'bbpress' ), $date, $time );
		}

		// Filter & return
		return apply_filters( 'bbp_get_reply_post_date', $result, $reply_id, $humanize, $gmt, $date, $time );
	}

/**
 * Append revisions to the reply content
 *
 * @since 2.0.0 bbPress (r2782)
 *
 * @param string $content Optional. Content to which we need to append the revisions to
 * @param int $reply_id Optional. Reply id
 * @return string Content with the revisions appended
 */
function bbp_reply_content_append_revisions( $content = '', $reply_id = 0 ) {

	// Bail if in admin or feed
	if ( is_admin() || is_feed() ) {
		return $content;
	}

	// Validate the ID
	$reply_id = bbp_get_reply_id( $reply_id );

	// Filter & return
	return apply_filters( 'bbp_reply_append_revisions', $content . bbp_get_reply_revision_log( $reply_id ), $content, $reply_id );
}

/**
 * Output the revision log of the reply
 *
 * @since 2.0.0 bbPress (r2782)
 *
 * @param int $reply_id Optional. Reply id
 */
function bbp_reply_revision_log( $reply_id = 0 ) {
	echo bbp_get_reply_revision_log( $reply_id );
}
	/**
	 * Return the formatted revision log of the reply
	 *
	 * @since 2.0.0 bbPress (r2782)
	 *
	 * @param int $reply_id Optional. Reply id
	 * @return string Revision log of the reply
	 */
	function bbp_get_reply_revision_log( $reply_id = 0 ) {

		// Create necessary variables
		$reply_id = bbp_get_reply_id( $reply_id );

		// Show the topic reply log if this is a topic in a reply loop
		if ( bbp_is_topic( $reply_id ) ) {
			return bbp_get_topic_revision_log( $reply_id );
		}

		// Get the reply revision log (out of post meta
		$revision_log = bbp_get_reply_raw_revision_log( $reply_id );

		// Check reply and revision log exist
		if ( empty( $reply_id ) || empty( $revision_log ) || ! is_array( $revision_log ) ) {
			return false;
		}

		// Get the actual revisions
		$revisions = bbp_get_reply_revisions( $reply_id );
		if ( empty( $revisions ) ) {
			return false;
		}

		$r = "\n\n" . '<ul id="bbp-reply-revision-log-' . esc_attr( $reply_id ) . '" class="bbp-reply-revision-log">' . "\n\n";

		// Loop through revisions
		foreach ( (array) $revisions as $revision ) {

			if ( empty( $revision_log[ $revision->ID ] ) ) {
				$author_id = $revision->post_author;
				$reason    = '';
			} else {
				$author_id = $revision_log[ $revision->ID ]['author'];
				$reason    = $revision_log[ $revision->ID ]['reason'];
			}

			$author = bbp_get_author_link( array( 'size' => 14, 'link_text' => bbp_get_reply_author_display_name( $revision->ID ), 'post_id' => $revision->ID ) );
			$since  = bbp_get_time_since( bbp_convert_date( $revision->post_modified ) );

			$r .= "\t" . '<li id="bbp-reply-revision-log-' . esc_attr( $reply_id ) . '-item-' . esc_attr( $revision->ID ) . '" class="bbp-reply-revision-log-item">' . "\n";
			if ( ! empty( $reason ) ) {
				$r .= "\t\t" . sprintf( esc_html__( 'This reply was modified %1$s by %2$s. Reason: %3$s', 'bbpress' ), esc_html( $since ), $author, esc_html( $reason ) ) . "\n";
			} else {
				$r .= "\t\t" . sprintf( esc_html__( 'This reply was modified %1$s by %2$s.', 'bbpress' ), esc_html( $since ), $author ) . "\n";
			}
			$r .= "\t" . '</li>' . "\n";

		}

		$r .= "\n" . '</ul>' . "\n\n";

		// Filter & return
		return apply_filters( 'bbp_get_reply_revision_log', $r, $reply_id );
	}
		/**
		 * Return the raw revision log of the reply
		 *
		 * @since 2.0.0 bbPress (r2782)
		 *
		 * @param int $reply_id Optional. Reply id
		 * @return string Raw revision log of the reply
		 */
		function bbp_get_reply_raw_revision_log( $reply_id = 0 ) {
			$reply_id     = bbp_get_reply_id( $reply_id );
			$revision_log = get_post_meta( $reply_id, '_bbp_revision_log', true );
			$revision_log = ! empty( $revision_log )
				? $revision_log
				: array();

			// Filter & return
			return apply_filters( 'bbp_get_reply_raw_revision_log', $revision_log, $reply_id );
		}

/**
 * Return the revisions of the reply
 *
 * @since 2.0.0 bbPress (r2782)
 *
 * @param int $reply_id Optional. Reply id
 * @return string reply revisions
 */
function bbp_get_reply_revisions( $reply_id = 0 ) {
	$reply_id  = bbp_get_reply_id( $reply_id );
	$revisions = wp_get_post_revisions( $reply_id, array( 'order' => 'ASC' ) );

	// Filter & return
	return apply_filters( 'bbp_get_reply_revisions', $revisions, $reply_id );
}

/**
 * Return the revision count of the reply
 *
 * @since 2.0.0 bbPress (r2782)
 *
 * @param int $reply_id Optional. Reply id
 * @param boolean $integer Optional. Whether or not to format the result
 * @return string reply revision count
 */
function bbp_get_reply_revision_count( $reply_id = 0, $integer = false ) {
	$reply_id = bbp_get_reply_id( $reply_id );
	$count    = count( bbp_get_reply_revisions( $reply_id ) );
	$filter   = ( true === $integer )
		? 'bbp_get_reply_revision_count_int'
		: 'bbp_get_reply_revision_count';

	return apply_filters( $filter, $count, $reply_id );
}

/**
 * Output the status of the reply
 *
 * @since 2.0.0 bbPress (r2667)
 *
 * @param int $reply_id Optional. Reply id
 */
function bbp_reply_status( $reply_id = 0 ) {
	echo bbp_get_reply_status( $reply_id );
}
	/**
	 * Return the status of the reply
	 *
	 * @since 2.0.0 bbPress (r2667)
	 *
	 * @param int $reply_id Optional. Reply id
	 * @return string Status of reply
	 */
	function bbp_get_reply_status( $reply_id = 0 ) {
		$reply_id = bbp_get_reply_id( $reply_id );

		// Filter & return
		return apply_filters( 'bbp_get_reply_status', get_post_status( $reply_id ), $reply_id );
	}

/**
 * Is the reply publicly viewable?
 *
 * See bbp_get_public_reply_statuses() for public statuses.
 *
 * @since 2.6.0 bbPress (r6391)
 *
 * @param int $reply_id Optional. Reply id
 * @return bool True if public, false if not.
 */
function bbp_is_reply_public( $reply_id = 0 ) {
	$reply_id  = bbp_get_reply_id( $reply_id );
	$status    = bbp_get_reply_status( $reply_id );
	$public    = bbp_get_public_reply_statuses();
	$is_public = in_array( $status, $public, true );

	// Filter & return
	return (bool) apply_filters( 'bbp_is_reply_public', $is_public, $reply_id );
}

/**
 * Is the reply not spam or deleted?
 *
 * @since 2.0.0 bbPress (r3496)
 * @since 2.6.0 bbPress (r6922) Returns false if topic is also not published
 *
 * @param int $reply_id Optional. Topic id
 * @return bool True if published, false if not.
 */
function bbp_is_reply_published( $reply_id = 0 ) {
	$reply_id     = bbp_get_reply_id( $reply_id );
	$topic_id     = bbp_get_reply_topic_id( $reply_id );
	$status       = bbp_get_public_status_id();
	$topic_status = bbp_is_topic_published( $topic_id );
	$reply_status = ( bbp_get_reply_status( $reply_id ) === $status );
	$retval       = ( $reply_status && $topic_status );

	// Filter & return
	return (bool) apply_filters( 'bbp_is_reply_published', (bool) $retval, $reply_id );
}

/**
 * Is the reply marked as spam?
 *
 * @since 2.0.0 bbPress (r2740)
 *
 * @param int $reply_id Optional. Reply id
 * @return bool True if spam, false if not.
 */
function bbp_is_reply_spam( $reply_id = 0 ) {
	$reply_id     = bbp_get_reply_id( $reply_id );
	$status       = bbp_get_spam_status_id();
	$reply_status = bbp_get_reply_status( $reply_id ) === $status;

	// Filter & return
	return (bool) apply_filters( 'bbp_is_reply_spam', (bool) $reply_status, $reply_id );
}

/**
 * Is the reply trashed?
 *
 * @since 2.0.0 bbPress (r2884)
 *
 * @param int $reply_id Optional. Topic id
 * @return bool True if spam, false if not.
 */
function bbp_is_reply_trash( $reply_id = 0 ) {
	$reply_id     = bbp_get_reply_id( $reply_id );
	$status       = bbp_get_trash_status_id();
	$reply_status = bbp_get_reply_status( $reply_id ) === $status;

	// Filter & return
	return (bool) apply_filters( 'bbp_is_reply_trash', (bool) $reply_status, $reply_id );
}

/**
 * Is the reply pending?
 *
 * @since 2.6.0 bbPress (r5507)
 *
 * @param int $reply_id Optional. Topic id
 * @return bool True if pending, false if not.
 */
function bbp_is_reply_pending( $reply_id = 0 ) {
	$reply_id     = bbp_get_reply_id( $reply_id );
	$status       = bbp_get_pending_status_id();
	$reply_status = bbp_get_reply_status( $reply_id ) === $status;

	// Filter & return
	return (bool) apply_filters( 'bbp_is_reply_pending', (bool) $reply_status, $reply_id );
}

/**
 * Is the reply private?
 *
 * @since 2.6.0 bbPress (r5507)
 *
 * @param int $reply_id Optional. Topic id
 * @return bool True if private, false if not.
 */
function bbp_is_reply_private( $reply_id = 0 ) {
	$reply_id     = bbp_get_reply_id( $reply_id );
	$status       = bbp_get_private_status_id();
	$reply_status = bbp_get_reply_status( $reply_id ) === $status;

	// Filter & return
	return (bool) apply_filters( 'bbp_is_reply_private', (bool) $reply_status, $reply_id );
}

/**
 * Is the reply by an anonymous user?
 *
 * @since 2.0.0 bbPress (r2753)
 *
 * @param int $reply_id Optional. Reply id
 * @return bool True if the post is by an anonymous user, false if not.
 */
function bbp_is_reply_anonymous( $reply_id = 0 ) {
	$reply_id = bbp_get_reply_id( $reply_id );
	$retval   = false;

	if ( ! bbp_get_reply_author_id( $reply_id ) ) {
		$retval = true;

	} elseif ( get_post_meta( $reply_id, '_bbp_anonymous_name', true ) ) {
		$retval = true;

	} elseif ( get_post_meta( $reply_id, '_bbp_anonymous_email', true ) ) {
		$retval = true;
	}

	// Filter & return
	return (bool) apply_filters( 'bbp_is_reply_anonymous', $retval, $reply_id );
}

/**
 * Deprecated. Use bbp_reply_author_display_name() instead.
 *
 * Output the author of the reply
 *
 * @since 2.0.0 bbPress (r2667)
 *
 * @deprecated 2.5.0 bbPress (r5119)
 *
 * @param int $reply_id Optional. Reply id
 */
function bbp_reply_author( $reply_id = 0 ) {
	echo bbp_get_reply_author( $reply_id );
}
	/**
	 * Deprecated. Use bbp_get_reply_author_display_name() instead.
	 *
	 * Return the author of the reply
	 *
	 * @since 2.0.0 bbPress (r2667)
	 *
	 * @deprecated 2.5.0 bbPress (r5119)
	 *
	 * @param int $reply_id Optional. Reply id
	 * @return string Author of reply
	 */
	function bbp_get_reply_author( $reply_id = 0 ) {
		$reply_id = bbp_get_reply_id( $reply_id );

		if ( ! bbp_is_reply_anonymous( $reply_id ) ) {
			$author = get_the_author_meta( 'display_name', bbp_get_reply_author_id( $reply_id ) );
		} else {
			$author = get_post_meta( $reply_id, '_bbp_anonymous_name', true );
		}

		// Filter & return
		return apply_filters( 'bbp_get_reply_author', $author, $reply_id );
	}

/**
 * Output the author ID of the reply
 *
 * @since 2.0.0 bbPress (r2667)
 *
 * @param int $reply_id Optional. Reply id
 */
function bbp_reply_author_id( $reply_id = 0 ) {
	echo bbp_get_reply_author_id( $reply_id );
}
	/**
	 * Return the author ID of the reply
	 *
	 * @since 2.0.0 bbPress (r2667)
	 *
	 * @param int $reply_id Optional. Reply id
	 * @return string Author id of reply
	 */
	function bbp_get_reply_author_id( $reply_id = 0 ) {
		$reply_id  = bbp_get_reply_id( $reply_id );
		$author_id = get_post_field( 'post_author', $reply_id );

		// Filter & return
		return (int) apply_filters( 'bbp_get_reply_author_id', $author_id, $reply_id );
	}

/**
 * Output the author display_name of the reply
 *
 * @since 2.0.0 bbPress (r2667)
 *
 * @param int $reply_id Optional. Reply id
 */
function bbp_reply_author_display_name( $reply_id = 0 ) {
	echo bbp_get_reply_author_display_name( $reply_id );
}
	/**
	 * Return the author display_name of the reply
	 *
	 * @since 2.0.0 bbPress (r2667)
	 *
	 * @param int $reply_id Optional. Reply id
	 * @return string The display name of the author of the reply
	 */
	function bbp_get_reply_author_display_name( $reply_id = 0 ) {
		$reply_id = bbp_get_reply_id( $reply_id );

		// User is not a guest
		if ( ! bbp_is_reply_anonymous( $reply_id ) ) {

			// Get the author ID
			$author_id = bbp_get_reply_author_id( $reply_id );

			// Try to get a display name
			$author_name = get_the_author_meta( 'display_name', $author_id );

			// Fall back to user login
			if ( empty( $author_name ) ) {
				$author_name = get_the_author_meta( 'user_login', $author_id );
			}

		// User does not have an account
		} else {
			$author_name = get_post_meta( $reply_id, '_bbp_anonymous_name', true );
		}

		// Fallback if nothing could be found
		if ( empty( $author_name ) ) {
			$author_name = bbp_get_fallback_display_name( $reply_id );
		}

		// Encode possible UTF8 display names
		if ( seems_utf8( $author_name ) === false ) {
			$author_name = utf8_encode( $author_name );
		}

		// Filter & return
		return apply_filters( 'bbp_get_reply_author_display_name', $author_name, $reply_id );
	}

/**
 * Output the author avatar of the reply
 *
 * @since 2.0.0 bbPress (r2667)
 *
 * @param int $reply_id Optional. Reply id
 * @param int $size Optional. Size of the avatar. Defaults to 40
 */
function bbp_reply_author_avatar( $reply_id = 0, $size = 40 ) {
	echo bbp_get_reply_author_avatar( $reply_id, $size );
}
	/**
	 * Return the author avatar of the reply
	 *
	 * @since 2.0.0 bbPress (r2667)
	 *
	 * @param int $reply_id Optional. Reply id
	 * @param int $size Optional. Size of the avatar. Defaults to 40
	 * @return string Avatar of author of the reply
	 */
	function bbp_get_reply_author_avatar( $reply_id = 0, $size = 40 ) {
		$reply_id = bbp_get_reply_id( $reply_id );
		if ( ! empty( $reply_id ) ) {
			// Check for anonymous user
			if ( ! bbp_is_reply_anonymous( $reply_id ) ) {
				$author_avatar = get_avatar( bbp_get_reply_author_id( $reply_id ), $size );
			} else {
				$author_avatar = get_avatar( get_post_meta( $reply_id, '_bbp_anonymous_email', true ), $size );
			}
		} else {
			$author_avatar = '';
		}

		// Filter & return
		return apply_filters( 'bbp_get_reply_author_avatar', $author_avatar, $reply_id, $size );
	}

/**
 * Output the author link of the reply
 *
 * @since 2.0.0 bbPress (r2717)
 *
 * @param array $args Optional. If it is an integer, it is used as reply id.
 */
function bbp_reply_author_link( $args = array() ) {
	echo bbp_get_reply_author_link( $args );
}
	/**
	 * Return the author link of the reply
	 *
	 * @since 2.0.0 bbPress (r2717)
	 *
	 * @param array $args Optional. If an integer, it is used as reply id.
	 * @return string Author link of reply
	 */
	function bbp_get_reply_author_link( $args = array() ) {

		// Parse arguments against default values
		$r = bbp_parse_args( $args, array(
			'post_id'    => 0,
			'link_title' => '',
			'type'       => 'both',
			'size'       => 80,
			'sep'        => '',
			'show_role'  => false
		), 'get_reply_author_link' );

		// Default return value
		$author_link = '';

		// Used as reply_id
		$reply_id = is_numeric( $args )
			? bbp_get_reply_id( $args )
			: bbp_get_reply_id( $r['post_id'] );

		// Reply ID is good
		if ( ! empty( $reply_id ) ) {

			// Get some useful reply information
			$author_url = bbp_get_reply_author_url( $reply_id );
			$anonymous  = bbp_is_reply_anonymous( $reply_id );

			// Tweak link title if empty
			if ( empty( $r['link_title'] ) ) {
				$author = bbp_get_reply_author_display_name( $reply_id );
				$title  = empty( $anonymous )
					? esc_attr__( "View %s's profile",  'bbpress' )
					: esc_attr__( "Visit %s's website", 'bbpress' );

				$link_title = sprintf( $title, $author );

			// Use what was passed if not
			} else {
				$link_title = $r['link_title'];
			}

			// Setup title and author_links array
			$author_links = array();
			$link_title   = ! empty( $link_title )
				? ' title="' . esc_attr( $link_title ) . '"'
				: '';

			// Get avatar (unescaped, because HTML)
			if ( ( 'avatar' === $r['type'] ) || ( 'both' === $r['type'] ) ) {
				$author_links['avatar'] = bbp_get_reply_author_avatar( $reply_id, $r['size'] );
			}

			// Get display name (escaped, because never HTML)
			if ( ( 'name' === $r['type'] ) || ( 'both' === $r['type'] ) ) {
				$author_links['name'] = esc_html( bbp_get_reply_author_display_name( $reply_id ) );
			}

			// Empty array
			$links  = array();
			$sprint = '<span %1$s>%2$s</span>';

			// Wrap each link
			foreach ( $author_links as $link => $link_text ) {
				$link_class = ' class="bbp-author-' . esc_attr( $link ) . '"';
				$links[]    = sprintf( $sprint, $link_class, $link_text );
			}

			// Juggle
			$author_links = $links;
			unset( $links );

			// Filter sections
			$sections    = apply_filters( 'bbp_get_reply_author_links', $author_links, $r, $args );

			// Assemble sections into author link
			$author_link = implode( $r['sep'], $sections );

			// Only wrap in link if profile exists
			if ( empty( $anonymous ) && bbp_user_has_profile( bbp_get_reply_author_id( $reply_id ) ) ) {
				$author_link = sprintf( '<a href="%1$s"%2$s%3$s>%4$s</a>', esc_url( $author_url ), $link_title, ' class="bbp-author-link"', $author_link );
			}

			// Role is not linked
			if ( true === $r['show_role'] ) {
				$author_link .= bbp_get_reply_author_role( array( 'reply_id' => $reply_id ) );
			}
		}

		// Filter & return
		return apply_filters( 'bbp_get_reply_author_link', $author_link, $r, $args );
	}

/**
 * Output the author url of the reply
 *
 * @since 2.0.0 bbPress (r2667)
 *
 * @param int $reply_id Optional. Reply id
 */
function bbp_reply_author_url( $reply_id = 0 ) {
	echo esc_url( bbp_get_reply_author_url( $reply_id ) );
}
	/**
	 * Return the author url of the reply
	 *
	 * @since 2.0.0 bbPress (r2667)
	 *
	 * @param int $reply_id Optional. Reply id
	 * @return string Author URL of the reply
	 */
	function bbp_get_reply_author_url( $reply_id = 0 ) {
		$reply_id = bbp_get_reply_id( $reply_id );

		// Check for anonymous user or non-existant user
		if ( ! bbp_is_reply_anonymous( $reply_id ) && bbp_user_has_profile( bbp_get_reply_author_id( $reply_id ) ) ) {
			$author_url = bbp_get_user_profile_url( bbp_get_reply_author_id( $reply_id ) );
		} else {
			$author_url = get_post_meta( $reply_id, '_bbp_anonymous_website', true );
			if ( empty( $author_url ) ) {
				$author_url = '';
			}
		}

		// Filter & return
		return apply_filters( 'bbp_get_reply_author_url', $author_url, $reply_id );
	}

/**
 * Output the reply author email address
 *
 * @since 2.0.0 bbPress (r3445)
 *
 * @param int $reply_id Optional. Reply id
 */
function bbp_reply_author_email( $reply_id = 0 ) {
	echo bbp_get_reply_author_email( $reply_id );
}
	/**
	 * Return the reply author email address
	 *
	 * @since 2.0.0 bbPress (r3445)
	 *
	 * @param int $reply_id Optional. Reply id
	 * @return string Reply author email address
	 */
	function bbp_get_reply_author_email( $reply_id = 0 ) {
		$reply_id = bbp_get_reply_id( $reply_id );

		// Not anonymous
		if ( ! bbp_is_reply_anonymous( $reply_id ) ) {

			// Use reply author email address
			$user_id      = bbp_get_reply_author_id( $reply_id );
			$user         = get_userdata( $user_id );
			$author_email = ! empty( $user->user_email ) ? $user->user_email : '';

		// Anonymous
		} else {

			// Get email from post meta
			$author_email = get_post_meta( $reply_id, '_bbp_anonymous_email', true );

			// Sanity check for missing email address
			if ( empty( $author_email ) ) {
				$author_email = '';
			}
		}

		// Filter & return
		return apply_filters( 'bbp_get_reply_author_email', $author_email, $reply_id );
	}

/**
 * Output the reply author role
 *
 * @since 2.1.0 bbPress (r3860)
 *
 * @param array $args Optional.
 */
function bbp_reply_author_role( $args = array() ) {
	echo bbp_get_reply_author_role( $args );
}
	/**
	 * Return the reply author role
	 *
	 * @since 2.1.0 bbPress (r3860)
	 *
	 * @param array $args Optional.
	 * @return string Reply author role
	 */
	function bbp_get_reply_author_role( $args = array() ) {

		// Parse arguments against default values
		$r = bbp_parse_args( $args, array(
			'reply_id' => 0,
			'class'    => false,
			'before'   => '<div class="bbp-author-role">',
			'after'    => '</div>'
		), 'get_reply_author_role' );

		$reply_id    = bbp_get_reply_id( $r['reply_id'] );
		$role        = bbp_get_user_display_role( bbp_get_reply_author_id( $reply_id ) );

		// Backwards compatibilty with old 'class' argument
		if ( ! empty( $r['class'] ) ) {
			$author_role = sprintf( '%1$s<div class="%2$s">%3$s</div>%4$s', $r['before'], esc_attr( $r['class'] ), esc_html( $role ), $r['after'] );

		// Simpler before & after arguments
		// https://bbpress.trac.wordpress.org/ticket/2557
		} else {
			$author_role = $r['before'] . $role . $r['after'];
		}

		// Filter & return
		return apply_filters( 'bbp_get_reply_author_role', $author_role, $r, $args );
	}

/**
 * Output the topic title a reply belongs to
 *
 * @since 2.0.0 bbPress (r2553)
 *
 * @param int $reply_id Optional. Reply id
 */
function bbp_reply_topic_title( $reply_id = 0 ) {
	echo bbp_get_reply_topic_title( $reply_id );
}
	/**
	 * Return the topic title a reply belongs to
	 *
	 * @since 2.0.0 bbPress (r2553)
	 *
	 * @param int $reply_id Optional. Reply id
	 * @return string The topic title of the reply
	 */
	function bbp_get_reply_topic_title( $reply_id = 0 ) {
		$reply_id = bbp_get_reply_id( $reply_id );
		$topic_id = bbp_get_reply_topic_id( $reply_id );

		// Filter & return
		return apply_filters( 'bbp_get_reply_topic_title', bbp_get_topic_title( $topic_id ), $reply_id );
	}

/**
 * Output the topic id a reply belongs to
 *
 * @since 2.0.0 bbPress (r2553)
 *
 * @param int $reply_id Optional. Reply id
 */
function bbp_reply_topic_id( $reply_id = 0 ) {
	echo bbp_get_reply_topic_id( $reply_id );
}
	/**
	 * Return the topic id a reply belongs to
	 *
	 * @since 2.0.0 bbPress (r2553)
	 *
	 * @param int $reply_id Optional. Reply id
	 * @return int The topic id of the reply
	 */
	function bbp_get_reply_topic_id( $reply_id = 0 ) {
		$reply_id = bbp_get_reply_id( $reply_id );
		$topic_id = (int) get_post_field( 'post_parent', $reply_id );

		// Meta-data fallback
		if ( empty( $topic_id ) ) {
			$topic_id = (int) get_post_meta( $reply_id, '_bbp_topic_id', true );
		}

		// Filter
		if ( ! empty( $topic_id ) ) {
			$topic_id = (int) bbp_get_topic_id( $topic_id );
		}

		// Filter & return
		return (int) apply_filters( 'bbp_get_reply_topic_id', $topic_id, $reply_id );
	}

/**
 * Output the forum id a reply belongs to
 *
 * @since 2.0.0 bbPress (r2679)
 *
 * @param int $reply_id Optional. Reply id
 */
function bbp_reply_forum_id( $reply_id = 0 ) {
	echo bbp_get_reply_forum_id( $reply_id );
}
	/**
	 * Return the forum id a reply belongs to
	 *
	 * @since 2.0.0 bbPress (r2679)
	 *
	 * @param int $reply_id Optional. Reply id
	 *                        id and reply id
	 * @return int The forum id of the reply
	 */
	function bbp_get_reply_forum_id( $reply_id = 0 ) {
		$reply_id = bbp_get_reply_id( $reply_id );
		$topic_id = bbp_get_reply_topic_id( $reply_id );
		$forum_id = (int) get_post_field( 'post_parent', $topic_id );

		// Meta-data fallback
		if ( empty( $forum_id ) ) {
			$forum_id = (int) get_post_meta( $reply_id, '_bbp_forum_id', true );
		}

		// Filter
		if ( ! empty( $forum_id ) ) {
			$forum_id = (int) bbp_get_forum_id( $forum_id );
		}

		// Filter & return
		return (int) apply_filters( 'bbp_get_reply_forum_id', $forum_id, $reply_id );
	}

/**
 * Output the ancestor reply id of a reply
 *
 * @since 2.4.0 bbPress (r4944)
 *
 * @param int $reply_id Optional. Reply id
 */
function bbp_reply_ancestor_id( $reply_id = 0 ) {
	echo bbp_get_reply_ancestor_id( $reply_id );
}
	/**
	 * Return the ancestor reply id of a reply
	 *
	 * @since 2.4.0 bbPress (r4944)
	 *
	 * @param in $reply_id Reply id
	 */
	function bbp_get_reply_ancestor_id( $reply_id = 0 ) {

		// Validation
		$reply_id = bbp_get_reply_id( $reply_id );
		if ( empty( $reply_id ) ) {
			return false;
		}

		// Find highest reply ancestor
		$ancestor_id = $reply_id;
		while ( $parent_id = bbp_get_reply_to( $ancestor_id ) ) {
			if ( empty( $parent_id ) || ( $parent_id === $ancestor_id ) || ( bbp_get_reply_topic_id( $reply_id ) === $parent_id ) || ( $parent_id === $reply_id ) ) {
				break;
			}
			$ancestor_id = $parent_id;
		}

		return (int) $ancestor_id;
	}

/**
 * Output the reply to id of a reply
 *
 * @since 2.4.0 bbPress (r4944)
 *
 * @param int $reply_id Optional. Reply id
 */
function bbp_reply_to( $reply_id = 0 ) {
	echo bbp_get_reply_to( $reply_id );
}
	/**
	 * Return the reply to id of a reply
	 *
 	 * @since 2.4.0 bbPress (r4944)
	 *
	 * @param int $reply_id Optional. Reply id
	 * @return int The parent reply id of the reply
	 */
	function bbp_get_reply_to( $reply_id = 0 ) {

		// Assume there is no reply_to set
		$reply_to = 0;

		// Check that reply_id is valid
		$reply_id = bbp_get_reply_id( $reply_id );

		// Get reply_to value
		if ( ! empty( $reply_id ) ) {
			$reply_to = (int) get_post_meta( $reply_id, '_bbp_reply_to', true );
		}

		// Filter & return
		return (int) apply_filters( 'bbp_get_reply_to', $reply_to, $reply_id );
	}

/**
 * Output the link for the reply to
 *
 * @since 2.4.0 bbPress (r4944)
 *
 * @param array $args
 */
function bbp_reply_to_link( $args = array() ) {
	echo bbp_get_reply_to_link( $args );
}

	/**
	 * Return the link for a reply to a reply
	 *
	 * @since 2.4.0 bbPress (r4944)
	 *
	 * @param array $args Arguments
	 * @return string Link for a reply to a reply
	 */
	function bbp_get_reply_to_link( $args = array() ) {

		// Parse arguments against default values
		$r = bbp_parse_args( $args, array(
			'id'           => 0,
			'link_before'  => '',
			'link_after'   => '',
			'reply_text'   => esc_html_x( 'Reply', 'verb', 'bbpress' ),
			'depth'        => 0,
			'add_below'    => 'post',
			'respond_id'   => 'new-reply-' . bbp_get_topic_id(),
		), 'get_reply_to_link' );

		// Get the reply to use it's ID and post_parent
		$reply    = bbp_get_reply( $r['id'] );
		$topic_id = bbp_get_reply_topic_id( $reply->ID );

		// Bail if no reply or user cannot reply
		if ( empty( $reply ) || empty( $topic_id ) || bbp_is_single_reply() || ! bbp_current_user_can_access_create_reply_form() ) {
			return;
		}

		// Build the URI and return value
		$uri = remove_query_arg( array( 'bbp_reply_to' ) );
		$uri = add_query_arg( array( 'bbp_reply_to' => $reply->ID ) );
		$uri = wp_nonce_url( $uri, 'respond_id_' . $reply->ID );
		$uri = $uri . '#new-post';

		// Only add onclick if replies are threaded
		if ( bbp_thread_replies() ) {

			// Array of classes to pass to moveForm
			$move_form = array(
				$r['add_below'] . '-' . $reply->ID,
				$reply->ID,
				$r['respond_id'],
				$reply->post_parent
			);

			// Build the onclick
			$onclick  = ' onclick="return addReply.moveForm(\'' . implode( "','", $move_form ) . '\');"';

		// No onclick if replies are not threaded
		} else {
			$onclick  = '';
		}

		// Add $uri to the array, to be passed through the filter
		$r['uri'] = $uri;
		$retval   = $r['link_before'] . '<a role="button" href="' . esc_url( $r['uri'] ) . '" class="bbp-reply-to-link"' . $onclick . '>' . $r['reply_text'] . '</a>' . $r['link_after'];

		// Filter & return
		return apply_filters( 'bbp_get_reply_to_link', $retval, $r, $args );
	}

/**
 * Output the reply to a reply cancellation link
 *
 * @since 2.4.0 bbPress (r4944)
 */
function bbp_cancel_reply_to_link( $text = '' ) {
	echo bbp_get_cancel_reply_to_link( $text );
}
	/**
	 * Return the cancellation link for a reply to a reply
	 *
	 * @since 2.4.0 bbPress (r4944)
	 *
	 * @param string $text The cancel text
	 * @return string The cancellation link
	 */
	function bbp_get_cancel_reply_to_link( $text = '' ) {

		// Bail if not hierarchical or editing a reply
		if ( ! bbp_thread_replies() || bbp_is_reply_edit() ) {
			return;
		}

		// Set default text
		if ( empty( $text ) ) {
			$text = esc_html__( 'Cancel', 'bbpress' );
		}

		// Replying to...
		$reply_to = isset( $_GET['bbp_reply_to'] )
			? (int) $_GET['bbp_reply_to']
			: 0;

		// Set visibility
		$style  = ! empty( $reply_to ) ? '' : ' style="display:none;"';
		$link   = remove_query_arg( array( 'bbp_reply_to', '_wpnonce' ) ) . "#post-{$reply_to}";
		$retval = sprintf( '<a href="%1$s" id="bbp-cancel-reply-to-link"%2$s>%3$s</a>', esc_url( $link ), $style, esc_html( $text ) );

		// Filter & return
		return apply_filters( 'bbp_get_cancel_reply_to_link', $retval, $link, $text );
	}

/**
 * Output the numeric position of a reply within a topic
 *
 * @since 2.0.0 bbPress (r2984)
 *
 * @param int $reply_id Optional. Reply id
 * @param int $topic_id Optional. Topic id
 */
function bbp_reply_position( $reply_id = 0, $topic_id = 0 ) {
	echo bbp_get_reply_position( $reply_id, $topic_id );
}
	/**
	 * Return the numeric position of a reply within a topic
	 *
	 * @since 2.0.0 bbPress (r2984)
	 *
	 * @param int $reply_id Optional. Reply id
	 * @param int $topic_id Optional. Topic id
	 * @return int Reply position
	 */
	function bbp_get_reply_position( $reply_id = 0, $topic_id = 0 ) {

		// Get required data
		$reply_id       = bbp_get_reply_id( $reply_id );
		$reply_position = get_post_field( 'menu_order', $reply_id );

		// Reply doesn't have a position so get the raw value
		if ( empty( $reply_position ) ) {

			// Get topic ID
			$topic_id = ! empty( $topic_id )
				? bbp_get_topic_id( $topic_id )
				: bbp_get_reply_topic_id( $reply_id );

			// Post is not the topic
			if ( $reply_id !== $topic_id ) {
				$reply_position = bbp_get_reply_position_raw( $reply_id, $topic_id );

				// Update the reply position in the posts table so we'll never have
				// to hit the DB again.
				if ( ! empty( $reply_position ) ) {
					bbp_update_reply_position( $reply_id, $reply_position );
				}

			// Topic's position is always 0
			} else {
				$reply_position = 0;
			}
		}

		// Bump the position by one if the topic is included in the reply loop
		if ( ! bbp_show_lead_topic() ) {
			$reply_position++;
		}

		// Filter & return
		return (int) apply_filters( 'bbp_get_reply_position', $reply_position, $reply_id, $topic_id );
	}

/** Reply Admin Links *********************************************************/

/**
 * Output admin links for reply
 *
 * @since 2.0.0 bbPress (r2667)
 *
 * @param array $args See {@link bbp_get_reply_admin_links()}
 */
function bbp_reply_admin_links( $args = array() ) {
	echo bbp_get_reply_admin_links( $args );
}
	/**
	 * Return admin links for reply
	 *
	 * @since 2.0.0 bbPress (r2667)
	 *
	 * @param array $args This function supports these arguments:
	 *  - id: Optional. Reply id
	 *  - before: HTML before the links. Defaults to
	 *             '<span class="bbp-admin-links">'
	 *  - after: HTML after the links. Defaults to '</span>'
	 *  - sep: Separator. Defaults to ' | '
	 *  - links: Array of the links to display. By default, edit, trash,
	 *            spam, reply move, and topic split links are displayed
	 * @return string Reply admin links
	 */
	function bbp_get_reply_admin_links( $args = array() ) {

		// Parse arguments against default values
		$r = bbp_parse_args( $args, array(
			'id'     => 0,
			'before' => '<span class="bbp-admin-links">',
			'after'  => '</span>',
			'sep'    => ' | ',
			'links'  => array()
		), 'get_reply_admin_links' );

		$r['id'] = bbp_get_reply_id( $r['id'] );

		// If post is a topic, return the topic admin links instead
		if ( bbp_is_topic( $r['id'] ) ) {
			return bbp_get_topic_admin_links( $args );
		}

		// If post is not a reply, return
		if ( ! bbp_is_reply( $r['id'] ) ) {
			return;
		}

		// If topic is trashed, do not show admin links
		if ( bbp_is_topic_trash( bbp_get_reply_topic_id( $r['id'] ) ) ) {
			return;
		}

		// If no links were passed, default to the standard
		if ( empty( $r['links'] ) ) {
			$r['links'] = apply_filters( 'bbp_reply_admin_links', array(
				'edit'    => bbp_get_reply_edit_link   ( $r ),
				'move'    => bbp_get_reply_move_link   ( $r ),
				'split'   => bbp_get_topic_split_link  ( $r ),
				'trash'   => bbp_get_reply_trash_link  ( $r ),
				'spam'    => bbp_get_reply_spam_link   ( $r ),
				'approve' => bbp_get_reply_approve_link( $r ),
				'reply'   => bbp_get_reply_to_link     ( $r )
			), $r['id'] );
		}

		// See if links need to be unset
		$reply_status = bbp_get_reply_status( $r['id'] );
		if ( in_array( $reply_status, array( bbp_get_spam_status_id(), bbp_get_trash_status_id(), bbp_get_pending_status_id() ), true ) ) {

			// Spam link shouldn't be visible on trashed topics
			if ( bbp_get_trash_status_id() === $reply_status ) {
				unset( $r['links']['spam'] );

			// Trash link shouldn't be visible on spam topics
			} elseif ( bbp_get_spam_status_id() === $reply_status ) {
				unset( $r['links']['trash'] );
			}
		}

		// Process the admin links
		$links  = implode( $r['sep'], array_filter( $r['links'] ) );
		$retval = $r['before'] . $links . $r['after'];

		// Filter & return
		return apply_filters( 'bbp_get_reply_admin_links', $retval, $r, $args );
	}

/**
 * Output the edit link of the reply
 *
 * @since 2.0.0 bbPress (r2740)
 *
 * @param array $args See {@link bbp_get_reply_edit_link()}
 */
function bbp_reply_edit_link( $args = array() ) {
	echo bbp_get_reply_edit_link( $args );
}

	/**
	 * Return the edit link of the reply
	 *
	 * @since 2.0.0 bbPress (r2740)
	 *
	 * @param array $args This function supports these arguments:
	 *  - id: Reply id
	 *  - link_before: HTML before the link
	 *  - link_after: HTML after the link
	 *  - edit_text: Edit text. Defaults to 'Edit'
	 * @return string Reply edit link
	 */
	function bbp_get_reply_edit_link( $args = array() ) {

		// Parse arguments against default values
		$r = bbp_parse_args( $args, array(
			'id'           => 0,
			'link_before'  => '',
			'link_after'   => '',
			'edit_text'    => esc_html__( 'Edit', 'bbpress' )
		), 'get_reply_edit_link' );

		// Get reply
		$reply = bbp_get_reply( $r['id'] );

		// Bypass check if user has caps
		if ( ! current_user_can( 'edit_others_replies' ) ) {

			// User cannot edit or it is past the lock time
			if ( empty( $reply ) || ! current_user_can( 'edit_reply', $reply->ID ) || bbp_past_edit_lock( $reply->post_date_gmt ) ) {
				return;
			}
		}

		// Get uri
		$uri = bbp_get_reply_edit_url( $r['id'] );

		// Bail if no uri
		if ( empty( $uri ) ) {
			return;
		}

		$retval = $r['link_before'] . '<a href="' . esc_url( $uri ) . '" class="bbp-reply-edit-link">' . $r['edit_text'] . '</a>' . $r['link_after'];

		// Filter & return
		return apply_filters( 'bbp_get_reply_edit_link', $retval, $r, $args );
	}

/**
 * Output URL to the reply edit page
 *
 * @since 2.0.0 bbPress (r2753)
 *
 * @param int $reply_id Optional. Reply id
 */
function bbp_reply_edit_url( $reply_id = 0 ) {
	echo esc_url( bbp_get_reply_edit_url( $reply_id ) );
}
	/**
	 * Return URL to the reply edit page
	 *
	 * @since 2.0.0 bbPress (r2753)
	 *
	 * @param int $reply_id Optional. Reply id
	 * @return string Reply edit url
	 */
	function bbp_get_reply_edit_url( $reply_id = 0 ) {

		// Bail if no reply
		$reply = bbp_get_reply( $reply_id );
		if ( empty( $reply ) ) {
			return;
		}

		$reply_link = bbp_remove_view_all( bbp_get_reply_permalink( $reply_id ) );

		// Pretty permalinks, previously used `bbp_use_pretty_urls()`
		// https://bbpress.trac.wordpress.org/ticket/3054
		if ( false === strpos( $reply_link, '?' ) ) {
			$url = trailingslashit( $reply_link ) . bbp_get_edit_slug();
			$url = user_trailingslashit( $url );

		// Unpretty permalinks
		} else {
			$url = add_query_arg( array(
				bbp_get_reply_post_type() => $reply->post_name,
				bbp_get_edit_rewrite_id() => '1'
			), $reply_link );
		}

		// Maybe add view all
		$url = bbp_add_view_all( $url );

		// Filter & return
		return apply_filters( 'bbp_get_reply_edit_url', $url, $reply_id );
	}

/**
 * Output the trash link of the reply
 *
 * @since 2.0.0 bbPress (r2740)
 *
 * @param array $args See {@link bbp_get_reply_trash_link()}
 */
function bbp_reply_trash_link( $args = array() ) {
	echo bbp_get_reply_trash_link( $args );
}

	/**
	 * Return the trash link of the reply
	 *
	 * @since 2.0.0 bbPress (r2740)
	 *
	 * @param array $args This function supports these arguments:
	 *  - id: Reply id
	 *  - link_before: HTML before the link
	 *  - link_after: HTML after the link
	 *  - sep: Separator
	 *  - trash_text: Trash text
	 *  - restore_text: Restore text
	 *  - delete_text: Delete text
	 * @return string Reply trash link
	 */
	function bbp_get_reply_trash_link( $args = array() ) {

		// Parse arguments against default values
		$r = bbp_parse_args( $args, array(
			'id'           => 0,
			'link_before'  => '',
			'link_after'   => '',
			'sep'          => ' | ',
			'trash_text'   => esc_html__( 'Trash',   'bbpress' ),
			'restore_text' => esc_html__( 'Restore', 'bbpress' ),
			'delete_text'  => esc_html__( 'Delete',  'bbpress' )
		), 'get_reply_trash_link' );

		// Get reply
		$reply = bbp_get_reply( $r['id'] );

		// Bail if no reply or current user cannot delete
		if ( empty( $reply ) || ! current_user_can( 'delete_reply', $reply->ID ) ) {
			return;
		}

		$actions    = array();
		$trash_days = bbp_get_trash_days( bbp_get_reply_post_type() );

		// Trashed
		if ( bbp_is_reply_trash( $reply->ID ) ) {
			$actions['untrash'] = '<a title="' . esc_attr__( 'Restore this item from the Trash', 'bbpress' ) . '" href="' . esc_url( wp_nonce_url( add_query_arg( array( 'action' => 'bbp_toggle_reply_trash', 'sub_action' => 'untrash', 'reply_id' => $reply->ID ) ), 'untrash-' . $reply->post_type . '_' . $reply->ID ) ) . '" class="bbp-reply-restore-link">' . $r['restore_text'] . '</a>';

		// Trash
		} elseif ( ! empty( $trash_days ) ) {
			$actions['trash']   = '<a title="' . esc_attr__( 'Move this item to the Trash',      'bbpress' ) . '" href="' . esc_url( wp_nonce_url( add_query_arg( array( 'action' => 'bbp_toggle_reply_trash', 'sub_action' => 'trash',   'reply_id' => $reply->ID ) ), 'trash-'   . $reply->post_type . '_' . $reply->ID ) ) . '" class="bbp-reply-trash-link">'   . $r['trash_text']   . '</a>';
		}

		// No trash
		if ( bbp_is_reply_trash( $reply->ID ) || empty( $trash_days ) ) {
			$actions['delete']  = '<a title="' . esc_attr__( 'Delete this item permanently',     'bbpress' ) . '" href="' . esc_url( wp_nonce_url( add_query_arg( array( 'action' => 'bbp_toggle_reply_trash', 'sub_action' => 'delete',  'reply_id' => $reply->ID ) ), 'delete-'  . $reply->post_type . '_' . $reply->ID ) ) . '" onclick="return confirm(\'' . esc_js( __( 'Are you sure you want to delete that permanently?', 'bbpress' ) ) . '\' );" class="bbp-reply-delete-link">' . $r['delete_text'] . '</a>';
		}

		// Process the admin links
		$retval = $r['link_before'] . implode( $r['sep'], $actions ) . $r['link_after'];

		// Filter & return
		return apply_filters( 'bbp_get_reply_trash_link', $retval, $r, $args );
	}

/**
 * Output the spam link of the reply
 *
 * @since 2.0.0 bbPress (r2740)
 *
 * @param array $args See {@link bbp_get_reply_spam_link()}
 */
function bbp_reply_spam_link( $args = array() ) {
	echo bbp_get_reply_spam_link( $args );
}

	/**
	 * Return the spam link of the reply
	 *
	 * @since 2.0.0 bbPress (r2740)
	 *
	 * @param array $args This function supports these arguments:
	 *  - id: Reply id
	 *  - link_before: HTML before the link
	 *  - link_after: HTML after the link
	 *  - spam_text: Spam text
	 *  - unspam_text: Unspam text
	 * @return string Reply spam link
	 */
	function bbp_get_reply_spam_link( $args = array() ) {

		// Parse arguments against default values
		$r = bbp_parse_args( $args, array(
			'id'           => 0,
			'link_before'  => '',
			'link_after'   => '',
			'spam_text'    => esc_html__( 'Spam',   'bbpress' ),
			'unspam_text'  => esc_html__( 'Unspam', 'bbpress' )
		), 'get_reply_spam_link' );

		// Get reply
		$reply = bbp_get_reply( $r['id'] );

		// Bail if no reply or current user cannot moderate
		if ( empty( $reply ) || ! current_user_can( 'moderate', $reply->ID ) ) {
			return;
		}

		$display = bbp_is_reply_spam( $reply->ID ) ? $r['unspam_text'] : $r['spam_text'];
		$uri     = add_query_arg( array( 'action' => 'bbp_toggle_reply_spam', 'reply_id' => $reply->ID ) );
		$uri     = wp_nonce_url( $uri, 'spam-reply_' . $reply->ID );
		$retval  = $r['link_before'] . '<a href="' . esc_url( $uri ) . '" class="bbp-reply-spam-link">' . $display . '</a>' . $r['link_after'];

		// Filter & return
		return apply_filters( 'bbp_get_reply_spam_link', $retval, $r, $args );
	}

/**
 * Move reply link
 *
 * Output the move link of the reply
 *
 * @since 2.3.0 bbPress (r4521)
 *
 * @param array $args See {@link bbp_get_reply_move_link()}
 */
function bbp_reply_move_link( $args = array() ) {
	echo bbp_get_reply_move_link( $args );
}

	/**
	 * Get move reply link
	 *
	 * Return the move link of the reply
	 *
	 * @since 2.3.0 bbPress (r4521)
	 *
	 * @param array $args This function supports these arguments:
	 *  - id: Reply id
	 *  - link_before: HTML before the link
	 *  - link_after: HTML after the link
	 *  - move_text: Move text
	 *  - move_title: Move title attribute
	 * @return string Reply move link
	 */
	function bbp_get_reply_move_link( $args = array() ) {

		// Parse arguments against default values
		$r = bbp_parse_args( $args, array(
			'id'          => 0,
			'link_before' => '',
			'link_after'  => '',
			'split_text'  => esc_html__( 'Move',            'bbpress' ),
			'split_title' => esc_attr__( 'Move this reply', 'bbpress' )
		), 'get_reply_move_link' );

		// Get IDs
		$reply_id = bbp_get_reply_id( $r['id'] );
		$topic_id = bbp_get_reply_topic_id( $reply_id );

		// Bail if no reply ID or user cannot moderate
		if ( empty( $reply_id ) || ! current_user_can( 'moderate', $topic_id ) ) {
			return;
		}

		$uri = add_query_arg( array(
			'action'   => 'move',
			'reply_id' => $reply_id
		), bbp_get_reply_edit_url( $reply_id ) );

		$retval = $r['link_before'] . '<a href="' . esc_url( $uri ) . '" title="' . $r['split_title'] . '" class="bbp-reply-move-link">' . $r['split_text'] . '</a>' . $r['link_after'];

		// Filter & return
		return apply_filters( 'bbp_get_reply_move_link', $retval, $r, $args );
	}

/**
 * Split topic link
 *
 * Output the split link of the topic (but is bundled with each reply)
 *
 * @since 2.0.0 bbPress (r2756)
 *
 * @param array $args See {@link bbp_get_topic_split_link()}
 */
function bbp_topic_split_link( $args = array() ) {
	echo bbp_get_topic_split_link( $args );
}

	/**
	 * Get split topic link
	 *
	 * Return the split link of the topic (but is bundled with each reply)
	 *
	 * @since 2.0.0 bbPress (r2756)
	 *
	 * @param array $args This function supports these arguments:
	 *  - id: Reply id
	 *  - link_before: HTML before the link
	 *  - link_after: HTML after the link
	 *  - split_text: Split text
	 *  - split_title: Split title attribute
	 * @return string Topic split link
	 */
	function bbp_get_topic_split_link( $args = array() ) {

		// Parse arguments against default values
		$r = bbp_parse_args( $args, array(
			'id'          => 0,
			'link_before' => '',
			'link_after'  => '',
			'split_text'  => esc_html__( 'Split',                           'bbpress' ),
			'split_title' => esc_attr__( 'Split the topic from this reply', 'bbpress' )
		), 'get_topic_split_link' );

		// Get IDs
		$reply_id = bbp_get_reply_id( $r['id'] );
		$topic_id = bbp_get_reply_topic_id( $reply_id );

		// Bail if no reply/topic ID, or user cannot moderate
		if ( empty( $reply_id ) || empty( $topic_id ) || ! current_user_can( 'moderate', $topic_id ) ) {
			return;
		}

		$uri = add_query_arg( array(
			'action'   => 'split',
			'reply_id' => $reply_id
		), bbp_get_topic_edit_url( $topic_id ) );

		$retval = $r['link_before'] . '<a href="' . esc_url( $uri ) . '" title="' . $r['split_title'] . '" class="bbp-topic-split-link">' . $r['split_text'] . '</a>' . $r['link_after'];

		// Filter & return
		return apply_filters( 'bbp_get_topic_split_link', $retval, $r, $args );
	}

/**
 * Output the approve link of the reply
 *
 * @since 2.6.0 bbPress (r5507)
 *
 * @param array $args See {@link bbp_get_reply_approve_link()}
 */
function bbp_reply_approve_link( $args = array() ) {
	echo bbp_get_reply_approve_link( $args );
}

	/**
	 * Return the approve link of the reply
	 *
	 * @since 2.6.0 bbPress (r5507)
	 *
	 * @param array $args This function supports these args:
	 *  - id: Optional. Reply id
	 *  - link_before: Before the link
	 *  - link_after: After the link
	 *  - sep: Separator between links
	 *  - approve_text: Approve text
	 *  - unapprove_text: Unapprove text
	 * @return string Reply approve link
	 */
	function bbp_get_reply_approve_link( $args = array() ) {

		// Parse arguments against default values
		$r = bbp_parse_args( $args, array(
			'id'             => 0,
			'link_before'    => '',
			'link_after'     => '',
			'sep'            => ' | ',
			'approve_text'   => _x( 'Approve',   'Pending Status', 'bbpress' ),
			'unapprove_text' => _x( 'Unapprove', 'Pending Status', 'bbpress' )
		), 'get_reply_approve_link' );

		// Get reply
		$reply = bbp_get_reply( $r['id'] );

		// Bail if no reply or current user cannot moderate
		if ( empty( $reply ) || ! current_user_can( 'moderate', $reply->ID ) ) {
			return;
		}

		$display = bbp_is_reply_pending( $reply->ID ) ? $r['approve_text'] : $r['unapprove_text'];
		$uri     = add_query_arg( array( 'action' => 'bbp_toggle_reply_approve', 'reply_id' => $reply->ID ) );
		$uri     = wp_nonce_url( $uri, 'approve-reply_' . $reply->ID );
		$retval  = $r['link_before'] . '<a href="' . esc_url( $uri ) . '" class="bbp-reply-approve-link">' . $display . '</a>' . $r['link_after'];

		// Filter & return
		return apply_filters( 'bbp_get_reply_approve_link', $retval, $r, $args );
	}

/**
 * Output the row class of a reply
 *
 * @since 2.0.0 bbPress (r2678)
 *
 * @param int $reply_id Optional. Reply ID
 * @param array Extra classes you can pass when calling this function
 */
function bbp_reply_class( $reply_id = 0, $classes = array() ) {
	echo bbp_get_reply_class( $reply_id, $classes );
}
	/**
	 * Return the row class of a reply
	 *
	 * @since 2.0.0 bbPress (r2678)
	 *
	 * @param int $reply_id Optional. Reply ID
	 * @param array Extra classes you can pass when calling this function
	 * @return string Row class of the reply
	 */
	function bbp_get_reply_class( $reply_id = 0, $classes = array() ) {
		$bbp       = bbpress();
		$reply_id  = bbp_get_reply_id( $reply_id );
		$topic_id  = bbp_get_reply_topic_id( $reply_id );
		$forum_id  = bbp_get_reply_forum_id( $reply_id );
		$author_id = bbp_get_reply_author_id( $reply_id );
		$reply_pos = bbp_get_reply_position( $reply_id, true );
		$classes   = array_filter( (array) $classes );
		$count     = isset( $bbp->reply_query->current_post )
			? (int) $bbp->reply_query->current_post
			: 1;

		//  Stripes
		$even_odd = ( $count % 2 )
			? 'even'
			: 'odd';

		// Forum moderator replied to topic
		$forum_moderator = ( bbp_is_user_forum_moderator( $author_id, $forum_id ) === $author_id )
			? 'forum-mod'
			: '';

		// Topic author replied to others
		$topic_author = ( bbp_get_topic_author_id( $topic_id ) === $author_id )
			? 'topic-author'
			: '';

		// Get reply classes
		$reply_classes = array(
			'loop-item-'          . $count,
			'user-id-'            . $author_id,
			'bbp-parent-forum-'   . $forum_id,
			'bbp-parent-topic-'   . $topic_id,
			'bbp-reply-position-' . $reply_pos,
			$even_odd,
			$topic_author,
			$forum_moderator
		);

		// Run the topic classes through the post-class filters, which also
		// handles the escaping of each individual class.
		$post_classes = get_post_class( array_merge( $classes, $reply_classes ), $reply_id );

		// Filter
		$new_classes  = apply_filters( 'bbp_get_reply_class', $post_classes, $reply_id, $classes );

		// Return
		return 'class="' . implode( ' ', $new_classes ) . '"';
	}

/** Pagination ****************************************************************/

/**
 * Return the base URL used inside of pagination links
 *
 * @since 2.6.0 bbPress (r6679)
 *
 * @param int $topic_id
 * @return string
 */
function bbp_get_replies_pagination_base( $topic_id = 0 ) {

	// If pretty permalinks are enabled, make our pagination pretty
	if ( bbp_use_pretty_urls() && ! bbp_is_topic_pending( $topic_id )) {

		// User's replies
		if ( bbp_is_single_user_replies() ) {
			$base = bbp_get_user_replies_created_url( bbp_get_displayed_user_id() );

		// Root profile page
		} elseif ( bbp_is_single_user() ) {
			$base = bbp_get_user_profile_url( bbp_get_displayed_user_id() );

		// Page or single post
		} elseif ( is_page() || is_single() ) {
			$base = get_permalink();

		// Single topic
		} else {
			$base = get_permalink( $topic_id );
		}

		$base = trailingslashit( $base ) . user_trailingslashit( bbp_get_paged_slug() . '/%#%/' );

	// Unpretty permalinks
	} else {
		$base = add_query_arg( 'paged', '%#%' );
	}

	// Filter & return
	return apply_filters( 'bbp_get_replies_pagination_base', $base, $topic_id );
}

/**
 * Output the topic pagination count
 *
 * The results are unescaped by design, to allow them to be filtered freely via
 * the 'bbp_get_topic_pagination_count' filter.
 *
 * @since 2.0.0 bbPress (r2519)
 *
 */
function bbp_topic_pagination_count() {
	echo bbp_get_topic_pagination_count();
}
	/**
	 * Return the topic pagination count
	 *
	 * @since 2.0.0 bbPress (r2519)
	 *
	 * @return string Topic pagination count
	 */
	function bbp_get_topic_pagination_count() {
		$bbp = bbpress();

		// Define local variable(s)
		$retstr = '';

		// Set pagination values
		$count_int = intval( $bbp->reply_query->post_count     );
		$total_int = intval( $bbp->reply_query->found_posts    );
		$ppp_int   = intval( $bbp->reply_query->posts_per_page );
		$start_int = intval( ( $bbp->reply_query->paged - 1 ) * $ppp_int ) + 1;
		$to_int    = intval( ( $start_int + ( $ppp_int - 1 ) > $total_int )
			? $total_int
			: $start_int + ( $ppp_int - 1 ) );

		// Format numbers for display
		$count_num = bbp_number_format( $count_int );
		$total_num = bbp_number_format( $total_int );
		$from_num  = bbp_number_format( $start_int );
		$to_num    = bbp_number_format( $to_int    );

		// We are threading replies
		if ( bbp_thread_replies() ) {
			$walker  = new BBP_Walker_Reply();
			$threads = absint( $walker->get_number_of_root_elements( $bbp->reply_query->posts ) - 1 );
			$retstr  = sprintf( _n( 'Viewing %1$s reply thread', 'Viewing %1$s reply threads', $threads, 'bbpress' ), bbp_number_format( $threads ) );

		// We are not including the lead topic
		} elseif ( bbp_show_lead_topic() ) {

			// Several replies in a topic with a single page
			if ( empty( $to_num ) ) {
				$retstr = sprintf( _n( 'Viewing %1$s reply', 'Viewing %1$s replies', $total_int, 'bbpress' ), $total_num );

			// Several replies in a topic with several pages
			} else {
				$retstr = sprintf( _n( 'Viewing %2$s replies (of %4$s total)', 'Viewing %1$s replies - %2$s through %3$s (of %4$s total)', $count_int, 'bbpress' ), $count_num, $from_num, $to_num, $total_num );
			}

		// We are including the lead topic
		} else {

			// Several posts in a topic with a single page
			if ( empty( $to_num ) ) {
				$retstr = sprintf( _n( 'Viewing %1$s post', 'Viewing %1$s posts', $total_int, 'bbpress' ), $total_num );

			// Several posts in a topic with several pages
			} else {
				$retstr = sprintf( _n( 'Viewing %2$s post (of %4$s total)', 'Viewing %1$s posts - %2$s through %3$s (of %4$s total)', $count_int, 'bbpress' ), $count_num, $from_num, $to_num, $total_num );
			}
		}

		// Escape results of _n()
		$retstr = esc_html( $retstr );

		// Filter & return
		return apply_filters( 'bbp_get_topic_pagination_count', $retstr );
	}

/**
 * Output topic pagination links
 *
 * @since 2.0.0 bbPress (r2519)
 */
function bbp_topic_pagination_links() {
	echo bbp_get_topic_pagination_links();
}
	/**
	 * Return topic pagination links
	 *
	 * @since 2.0.0 bbPress (r2519)
	 *
	 * @return string Topic pagination links
	 */
	function bbp_get_topic_pagination_links() {
		$bbp = bbpress();

		if ( ! isset( $bbp->reply_query->pagination_links ) || empty( $bbp->reply_query->pagination_links ) ) {
			return false;
		}

		// Filter & return
		return apply_filters( 'bbp_get_topic_pagination_links', $bbp->reply_query->pagination_links );
	}

/** Forms *********************************************************************/

/**
 * Output the value of reply content field
 *
 * @since 2.0.0 bbPress (r3130)
 */
function bbp_form_reply_content() {
	echo bbp_get_form_reply_content();
}
	/**
	 * Return the value of reply content field
	 *
	 * @since 2.0.0 bbPress (r3130)
	 *
	 * @return string Value of reply content field
	 */
	function bbp_get_form_reply_content() {

		// Get _POST data
		if ( bbp_is_reply_form_post_request() && isset( $_POST['bbp_reply_content'] ) ) {
			$reply_content = wp_unslash( $_POST['bbp_reply_content'] );

		// Get edit data
		} elseif ( bbp_is_reply_edit() ) {
			$reply_content = bbp_get_global_post_field( 'post_content', 'raw' );

		// No data
		} else {
			$reply_content = '';
		}

		// Filter & return
		return apply_filters( 'bbp_get_form_reply_content', $reply_content );
	}

/**
 * Output the value of the reply to field
 *
 * @since 2.4.0 bbPress (r4944)
 */
function bbp_form_reply_to() {
	echo bbp_get_form_reply_to();
}

	/**
	 * Return the value of reply to field
	 *
	 * @since 2.4.0 bbPress (r4944)
	 *
	 * @return string Value of reply to field
	 */
	function bbp_get_form_reply_to() {

		// Set initial value
		$reply_to = 0;

		// Get $_REQUEST data
		if ( bbp_is_reply_form_post_request() && isset( $_REQUEST['bbp_reply_to'] ) ) {
			$reply_to = bbp_validate_reply_to( $_REQUEST['bbp_reply_to'] );
		}

		// If empty, get from meta
		if ( empty( $reply_to ) ) {
			$reply_to = bbp_get_reply_to();
		}

		// Filter & return
		return apply_filters( 'bbp_get_form_reply_to', $reply_to );
	}

/**
 * Output a select box allowing to pick which reply an existing hierarchical
 * reply belongs to.
 *
 * @since 2.6.0 bbPress (r5387)
 *
 * @param int $reply_id
 */
function bbp_reply_to_dropdown( $reply_id = 0 ) {
	echo bbp_get_reply_to_dropdown( $reply_id );
}
	/**
	 * Return a select box allowing to pick which topic/reply a reply belongs.
	 *
	 * @since 2.6.0 bbPress (r5387)
	 *
	 * @param int $reply_id
	 *
	 * @return string The dropdown
	 */
	function bbp_get_reply_to_dropdown( $reply_id = 0 ) {

		// Validate the reply data
		$reply_id = bbp_get_reply_id( $reply_id );
		$reply_to = bbp_get_reply_to( $reply_id );
		$topic_id = bbp_get_reply_topic_id( $reply_id );

		// Get the replies
		$posts = get_posts( array(
			'post_type'   => bbp_get_reply_post_type(),
			'post_status' => bbp_get_public_status_id(),
			'post_parent' => $topic_id,
			'numberposts' => -1,
			'orderby'     => 'menu_order',
			'order'       => 'ASC',
		) );

		// Append `reply_to` for each reply so it can be walked
		foreach ( $posts as &$post ) {

			// Check for reply post type
			$_reply_to = bbp_get_reply_to( $post->ID );

			// Make sure it's a reply to a reply
			if ( empty( $_reply_to ) || ( $topic_id === $_reply_to ) ) {
				$_reply_to = 0;
			}

			// Add reply_to to the post object so we can walk it later
			$post->reply_to = $_reply_to;
		}

		// Default "None" text
		$show_none = ( 0 === $reply_id )
			? esc_attr_x( 'None', 'Default reply to dropdown text', 'bbpress' )
			: sprintf( esc_attr__( '%1$s - %2$s', 'bbpress' ), $topic_id, bbp_get_topic_title( $topic_id ) );

		// Get the dropdown and return it
		$retval = bbp_get_dropdown( array(
			'show_none'    => $show_none,
			'select_id'    => 'bbp_reply_to',
			'select_class' => 'bbp_dropdown',
			'exclude'      => $reply_id,
			'selected'     => $reply_to,
			'post_parent'  => $topic_id,
			'post_type'    => bbp_get_reply_post_type(),
			'max_depth'    => bbp_thread_replies_depth(),
			'page'         => 1,
			'per_page'     => -1,
			'walker'       => new BBP_Walker_Reply_Dropdown(),
			'posts'        => $posts
		) );

		// Filter & return
		return apply_filters( 'bbp_get_reply_to_dropdown', $retval, $reply_id, $reply_to, $topic_id );
	}

/**
 * Output checked value of reply log edit field
 *
 * @since 2.0.0 bbPress (r3130)
 */
function bbp_form_reply_log_edit() {
	echo bbp_get_form_reply_log_edit();
}
	/**
	 * Return checked value of reply log edit field
	 *
	 * @since 2.0.0 bbPress (r3130)
	 *
	 * @return string Reply log edit checked value
	 */
	function bbp_get_form_reply_log_edit() {

		// Get _POST data
		if ( bbp_is_reply_form_post_request() && isset( $_POST['bbp_log_reply_edit'] ) ) {
			$reply_revision = (bool) $_POST['bbp_log_reply_edit'];

		// No data
		} else {
			$reply_revision = true;
		}

		// Get checked output
		$checked = checked( $reply_revision, true, false );

		// Filter & return
		return apply_filters( 'bbp_get_form_reply_log_edit', $checked, $reply_revision );
	}

/**
 * Output the value of the reply edit reason
 *
 * @since 2.0.0 bbPress (r3130)
 */
function bbp_form_reply_edit_reason() {
	echo bbp_get_form_reply_edit_reason();
}
	/**
	 * Return the value of the reply edit reason
	 *
	 * @since 2.0.0 bbPress (r3130)
	 *
	 * @return string Reply edit reason value
	 */
	function bbp_get_form_reply_edit_reason() {

		// Get _POST data
		if ( bbp_is_reply_form_post_request() && isset( $_POST['bbp_reply_edit_reason'] ) ) {
			$reply_edit_reason = wp_unslash( $_POST['bbp_reply_edit_reason'] );

		// No data
		} else {
			$reply_edit_reason = '';
		}

		// Filter & return
		return apply_filters( 'bbp_get_form_reply_edit_reason', $reply_edit_reason );
	}

/**
 * Output value reply status dropdown
 *
 * @since 2.6.0 bbPress (r5399)
 *
 * @param $args This function supports these arguments:
 *  - select_id: Select id. Defaults to bbp_reply_status
 *  - tab: Deprecated. Tabindex
 *  - reply_id: Reply id
 *  - selected: Override the selected option
 */
function bbp_form_reply_status_dropdown( $args = array() ) {
	echo bbp_get_form_reply_status_dropdown( $args );
}
	/**
	 * Returns reply status dropdown
	 *
	 * This dropdown is only intended to be seen by users with the 'moderate'
	 * capability. Because of this, no additional capability checks are performed
	 * within this function to check available reply statuses.
	 *
	 * @since 2.6.0 bbPress (r5399)
	 *
	 * @param $args This function supports these arguments:
	 *  - select_id: Select id. Defaults to bbp_reply_status
	 *  - tab: Deprecated. Tabindex
	 *  - reply_id: Reply id
	 *  - selected: Override the selected option
	 */
	function bbp_get_form_reply_status_dropdown( $args = array() ) {

		// Parse arguments against default values
		$r = bbp_parse_args( $args, array(
			'select_id'    => 'bbp_reply_status',
			'select_class' => 'bbp_dropdown',
			'tab'          => false,
			'reply_id'     => 0,
			'selected'     => false
		), 'reply_status_dropdown' );

		// No specific selected value passed
		if ( empty( $r['selected'] ) ) {

			// Post value is passed
			if ( bbp_is_reply_form_post_request() && isset( $_POST[ $r['select_id'] ] ) ) {
				$r['selected'] = sanitize_key( $_POST[ $r['select_id'] ] );

			// No Post value was passed
			} else {

				// Edit reply
				if ( bbp_is_reply_edit() ) {
					$r['reply_id'] = bbp_get_reply_id( $r['reply_id'] );
					$r['selected'] = bbp_get_reply_status( $r['reply_id'] );

				// New reply
				} else {
					$r['selected'] = bbp_get_public_status_id();
				}
			}
		}

		// Start an output buffer, we'll finish it after the select loop
		ob_start(); ?>

		<select name="<?php echo esc_attr( $r['select_id'] ) ?>" id="<?php echo esc_attr( $r['select_id'] ); ?>_select" class="<?php echo esc_attr( $r['select_class'] ); ?>"<?php bbp_tab_index_attribute( $r['tab'] ); ?>>

			<?php foreach ( bbp_get_reply_statuses( $r['reply_id'] ) as $key => $label ) : ?>

				<option value="<?php echo esc_attr( $key ); ?>"<?php selected( $key, $r['selected'] ); ?>><?php echo esc_html( $label ); ?></option>

			<?php endforeach; ?>

		</select>

		<?php

		// Filter & return
		return apply_filters( 'bbp_get_form_reply_status_dropdown', ob_get_clean(), $r, $args );
	}

/**
 * Verify if a POST request came from a failed reply attempt.
 *
 * Used to avoid cross-site request forgeries when checking posted reply form
 * content.
 *
 * @see bbp_reply_form_fields()
 *
 * @since 2.6.0 bbPress (r5558)
 *
 * @return boolean True if is a post request with valid nonce
 */
function bbp_is_reply_form_post_request() {

	// Bail if not a post request
	if ( ! bbp_is_post_request() ) {
		return false;
	}

	// Creating a new reply
	if ( bbp_verify_nonce_request( 'bbp-new-reply' ) ) {
		return true;
	}

	// Editing an existing reply
	if ( bbp_verify_nonce_request( 'bbp-edit-reply' ) ) {
		return true;
	}

	return false;
}
