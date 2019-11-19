<?php

/**
 * bbPress Topic Template Tags
 *
 * @package bbPress
 * @subpackage TemplateTags
 */

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

/** Post Type *****************************************************************/

/**
 * Output the unique id of the custom post type for topics
 *
 * @since 2.0.0 bbPress (r2857)
 */
function bbp_topic_post_type() {
	echo bbp_get_topic_post_type();
}
	/**
	 * Return the unique id of the custom post type for topics
	 *
	 * @since 2.0.0 bbPress (r2857)
	 *
	 * @return string The unique topic post type id
	 */
	function bbp_get_topic_post_type() {

		// Filter & return
		return apply_filters( 'bbp_get_topic_post_type', bbpress()->topic_post_type );
	}

/**
 * Return array of labels used by the topic post type
 *
 * @since 2.5.0 bbPress (r5129)
 *
 * @return array
 */
function bbp_get_topic_post_type_labels() {

	// Filter & return
	return (array) apply_filters( 'bbp_get_topic_post_type_labels', array(
		'name'                     => esc_attr__( 'Topics',                     'bbpress' ),
		'menu_name'                => esc_attr__( 'Topics',                     'bbpress' ),
		'singular_name'            => esc_attr__( 'Topic',                      'bbpress' ),
		'all_items'                => esc_attr__( 'All Topics',                 'bbpress' ),
		'add_new'                  => esc_attr__( 'Add New',                    'bbpress' ),
		'add_new_item'             => esc_attr__( 'Create New Topic',           'bbpress' ),
		'edit'                     => esc_attr__( 'Edit',                       'bbpress' ),
		'edit_item'                => esc_attr__( 'Edit Topic',                 'bbpress' ),
		'new_item'                 => esc_attr__( 'New Topic',                  'bbpress' ),
		'view'                     => esc_attr__( 'View Topic',                 'bbpress' ),
		'view_item'                => esc_attr__( 'View Topic',                 'bbpress' ),
		'view_items'               => esc_attr__( 'View Topics',                'bbpress' ),
		'search_items'             => esc_attr__( 'Search Topics',              'bbpress' ),
		'not_found'                => esc_attr__( 'No topics found',            'bbpress' ),
		'not_found_in_trash'       => esc_attr__( 'No topics found in Trash',   'bbpress' ),
		'filter_items_list'        => esc_attr__( 'Filter topics list',         'bbpress' ),
		'items_list'               => esc_attr__( 'Topics list',                'bbpress' ),
		'items_list_navigation'    => esc_attr__( 'Topics list navigation',     'bbpress' ),
		'parent_item_colon'        => esc_attr__( 'Forum:',                     'bbpress' ),
		'archives'                 => esc_attr__( 'Forum Topics',               'bbpress' ),
		'attributes'               => esc_attr__( 'Topic Attributes',           'bbpress' ),
		'insert_into_item'         => esc_attr__( 'Insert into topic',          'bbpress' ),
		'uploaded_to_this_item'    => esc_attr__( 'Uploaded to this topic',     'bbpress' ),
		'featured_image'           => esc_attr__( 'Topic Image',                'bbpress' ),
		'set_featured_image'       => esc_attr__( 'Set topic image',            'bbpress' ),
		'remove_featured_image'    => esc_attr__( 'Remove topic image',         'bbpress' ),
		'use_featured_image'       => esc_attr__( 'Use as topic image',         'bbpress' ),
		'item_published'           => esc_attr__( 'Topic published.',           'bbpress' ),
		'item_published_privately' => esc_attr__( 'Topic published privately.', 'bbpress' ),
		'item_reverted_to_draft'   => esc_attr__( 'Topic reverted to draft.',   'bbpress' ),
		'item_scheduled'           => esc_attr__( 'Topic scheduled.',           'bbpress' ),
		'item_updated'             => esc_attr__( 'Topic updated.',             'bbpress' )
	) );
}

/**
 * Return array of topic post type rewrite settings
 *
 * @since 2.5.0 bbPress (r5129)
 *
 * @return array
 */
function bbp_get_topic_post_type_rewrite() {

	// Filter & return
	return (array) apply_filters( 'bbp_get_topic_post_type_rewrite', array(
		'slug'       => bbp_get_topic_slug(),
		'with_front' => false
	) );
}

/**
 * Return array of features the topic post type supports
 *
 * @since 2.5.0 bbPress (r5129)
 *
 * @return array
 */
function bbp_get_topic_post_type_supports() {

	// Filter & return
	return (array) apply_filters( 'bbp_get_topic_post_type_supports', array(
		'title',
		'editor',
		'revisions'
	) );
}

/**
 * The plugin version of bbPress comes with two topic display options:
 * - Traditional: Topics are included in the reply loop (default)
 * - New Style: Topics appear as "lead" posts, ahead of replies
 *
 * @since 2.0.0 bbPress (r2954)
 *
 * @param $show_lead Optional. Default false
 * @return bool Yes if the topic appears as a lead, otherwise false
 */
function bbp_show_lead_topic( $show_lead = false ) {

	// Never separate the lead topic in feeds
	if ( is_feed() ) {
		return false;
	}

	// Filter & return
	return (bool) apply_filters( 'bbp_show_lead_topic', (bool) $show_lead );
}

/** Topic Loop ****************************************************************/

/**
 * The main topic loop. WordPress makes this easy for us
 *
 * @since 2.0.0 bbPress (r2485)
 *
 * @param array $args All the arguments supported by {@link WP_Query}
 * @return object Multidimensional array of topic information
 */
function bbp_has_topics( $args = array() ) {

	/** Defaults **************************************************************/

	// Other defaults
	$default_topic_search  = bbp_sanitize_search_request( 'ts' );
	$default_show_stickies = (bool) ( bbp_is_single_forum() || bbp_is_topic_archive() ) && ( false === $default_topic_search );
	$default_post_parent   = bbp_is_single_forum() ? bbp_get_forum_id() : 'any';

	// Default argument array
	$default = array(
		'post_type'      => bbp_get_topic_post_type(), // Narrow query down to bbPress topics
		'post_parent'    => $default_post_parent,      // Forum ID
		'meta_key'       => '_bbp_last_active_time',   // Make sure topic has some last activity time
		'meta_type'      => 'DATETIME',
		'orderby'        => 'meta_value',              // 'meta_value', 'author', 'date', 'title', 'modified', 'parent', rand',
		'order'          => 'DESC',                    // 'ASC', 'DESC'
		'posts_per_page' => bbp_get_topics_per_page(), // Topics per page
		'paged'          => bbp_get_paged(),           // Page Number
		'show_stickies'  => $default_show_stickies,    // Ignore sticky topics?
		'max_num_pages'  => false,                     // Maximum number of pages to show

		// Conditionally prime the cache for related posts
		'update_post_family_cache' => true
	);

	// Only add 's' arg if searching for topics
	// See https://bbpress.trac.wordpress.org/ticket/2607
	if ( ! empty( $default_topic_search ) ) {
		$default['s'] = $default_topic_search;
	}

	// What are the default allowed statuses (based on user caps)
	if ( bbp_get_view_all( 'edit_others_topics' ) ) {

		// Default view=all statuses
		$post_statuses = array_keys( bbp_get_topic_statuses() );

		// Add support for private status
		if ( current_user_can( 'read_private_topics' ) ) {
			$post_statuses[] = bbp_get_private_status_id();
		}

		// Join post statuses together
		$default['post_status'] = $post_statuses;

	// Lean on the 'perm' query var value of 'readable' to provide statuses
	} else {
		$default['perm'] = 'readable';
	}

	// Maybe query for topic tags
	if ( bbp_is_topic_tag() ) {
		$default['term']     = bbp_get_topic_tag_slug();
		$default['taxonomy'] = bbp_get_topic_tag_tax_id();
	}

	/** Setup *****************************************************************/

	// Parse arguments against default values
	$r = bbp_parse_args( $args, $default, 'has_topics' );

	// Get bbPress
	$bbp = bbpress();

	// Call the query
	$bbp->topic_query = new WP_Query( $r );

	// Maybe prime last active posts
	if ( ! empty( $r['update_post_family_cache'] ) ) {
		bbp_update_post_family_caches( $bbp->topic_query->posts );
	}

	// Set post_parent back to 0 if originally set to 'any'
	if ( 'any' === $r['post_parent'] ) {
		$r['post_parent'] = 0;
	}

	// Limited the number of pages shown
	if ( ! empty( $r['max_num_pages'] ) ) {
		$bbp->topic_query->max_num_pages = (int) $r['max_num_pages'];
	}

	/** Stickies **************************************************************/

	// Put sticky posts at the top of the posts array
	if ( ! empty( $r['show_stickies'] ) && ( $r['paged'] <= 1 ) ) {
		bbp_add_sticky_topics( $bbp->topic_query, $r );
	}

	// If no limit to posts per page, set it to the current post_count
	if ( -1 === $r['posts_per_page'] ) {
		$r['posts_per_page'] = $bbp->topic_query->post_count;
	}

	// Add pagination values to query object
	$bbp->topic_query->posts_per_page = (int) $r['posts_per_page'];
	$bbp->topic_query->paged          = (int) $r['paged'];

	// Only add pagination if query returned results
	if ( ( ! empty( $bbp->topic_query->post_count ) || ! empty( $bbp->topic_query->found_posts ) ) && ! empty( $bbp->topic_query->posts_per_page ) ) {

		// Limit the number of topics shown based on maximum allowed pages
		if ( ( ! empty( $r['max_num_pages'] ) ) && ( $bbp->topic_query->found_posts > ( $bbp->topic_query->max_num_pages * $bbp->topic_query->post_count ) ) ) {
			$bbp->topic_query->found_posts = $bbp->topic_query->max_num_pages * $bbp->topic_query->post_count;
		}

		// Total topics for pagination boundaries
		$total_pages = ( $bbp->topic_query->posts_per_page === $bbp->topic_query->found_posts )
			? 1
			: ceil( $bbp->topic_query->found_posts / $bbp->topic_query->posts_per_page );

		// Maybe add view-all args
		$add_args = bbp_get_view_all()
			? array( 'view' => 'all' )
			: false;

		// Pagination settings with filter
		$bbp_topic_pagination = apply_filters( 'bbp_topic_pagination', array(
			'base'      => bbp_get_topics_pagination_base( $r['post_parent'] ),
			'format'    => '',
			'total'     => $total_pages,
			'current'   => $bbp->topic_query->paged,
			'prev_text' => is_rtl() ? '&rarr;' : '&larr;',
			'next_text' => is_rtl() ? '&larr;' : '&rarr;',
			'mid_size'  => 1,
			'add_args'  => $add_args,
		) );

		// Add pagination to query object
		$bbp->topic_query->pagination_links = bbp_paginate_links( $bbp_topic_pagination );
	}

	// Filter & return
	return apply_filters( 'bbp_has_topics', $bbp->topic_query->have_posts(), $bbp->topic_query );
}

/**
 * Whether there are more topics available in the loop
 *
 * @since 2.0.0 bbPress (r2485)
 *
 * @return object Topic information
 */
function bbp_topics() {

	// Put into variable to check against next
	$have_posts = bbpress()->topic_query->have_posts();

	// Reset the post data when finished
	if ( empty( $have_posts ) ) {
		wp_reset_postdata();
	}

	return $have_posts;
}

/**
 * Loads up the current topic in the loop
 *
 * @since 2.0.0 bbPress (r2485)
 *
 * @return object Topic information
 */
function bbp_the_topic() {
	return bbpress()->topic_query->the_post();
}

/**
 * Add sticky topics to a topics query object
 *
 * @since 2.6.0 bbPress (r6402)
 *
 * @param WP_Query $query
 * @param array    $args
 */
function bbp_add_sticky_topics( &$query, $args = array() ) {

	// Bail if intercepted
	$intercept = bbp_maybe_intercept( __FUNCTION__, func_get_args() );
	if ( bbp_is_intercepted( $intercept ) ) {
		return $intercept;
	}

	// Parse arguments against what gets used locally
	$r = bbp_parse_args( $args, array(
		'post_parent'         => 0,
		'post_parent__not_in' => array(),
		'post__not_in'        => array(),
		'post_status'         => '',
		'perm'                => ''
	), 'add_sticky_topics' );

	// Get super stickies and stickies in this forum
	$super_stickies = bbp_get_super_stickies();
	$forum_stickies = ! empty( $r['post_parent'] )
		? bbp_get_stickies( $r['post_parent'] )
		: array();

	// Merge stickies (supers first) and remove duplicates
	$stickies = array_filter( array_unique( array_merge( $super_stickies, $forum_stickies ) ) );

	// Bail if no stickies
	if ( empty( $stickies ) ) {
		return;
	}

	// If any posts have been excluded specifically, Ignore those that are sticky.
	if ( ! empty( $r['post__not_in'] ) ) {
		$stickies = array_diff( $stickies, $r['post__not_in'] );
	}

	// Default sticky posts array
	$sticky_topics = array();

	// Loop through posts
	foreach ( $query->posts as $key => $post ) {

		// Looking for stickies in this query loop, and stash & unset them
		if ( in_array( $post->ID, $stickies, true ) ) {
			$sticky_topics[] = $post;
			unset( $query->posts[ $key ] );
		}
	}

	// Remove queried stickies from stickies array
	if ( ! empty( $sticky_topics ) ) {
		$stickies = array_diff( $stickies, wp_list_pluck( $sticky_topics, 'ID' ) );
	}

	// Fetch all stickies that were not in the query
	if ( ! empty( $stickies ) ) {

		// Query to use in get_posts to get sticky posts
		$sticky_query = array(
			'post_type'   => bbp_get_topic_post_type(),
			'post_parent' => 'any',
			'meta_key'    => '_bbp_last_active_time',
			'meta_type'   => 'DATETIME',
			'orderby'     => 'meta_value',
			'order'       => 'DESC',
			'include'     => $stickies
		);

		// Conditionally exclude private/hidden forum ID's
		$exclude_forum_ids = bbp_exclude_forum_ids( 'array' );

		// Maybe remove the current forum from excluded forum IDs
		if ( ! empty( $r['post_parent' ] ) ) {
			unset( $exclude_forum_ids[ $r['post_parent' ] ] );
		}

		// Maybe exclude specific forums
		if ( ! empty( $exclude_forum_ids ) ) {
			$sticky_query['post_parent__not_in'] = $exclude_forum_ids;
		}

		// Allowed statuses, or lean on the 'perm' argument (probably 'readable')
		$sticky_query['post_status'] = bbp_get_view_all( 'edit_others_topics' )
			? $r['post_status']
			: $r['perm'];

		// Get unqueried stickies
		$_posts = get_posts( $sticky_query );
		if ( ! empty( $_posts ) ) {

			// Merge the stickies topics with the query topics .
			$sticky_topics = array_merge( $sticky_topics, $_posts );

			// Get a count of the visible stickies
			$sticky_count = count( $_posts );

			// Adjust loop and counts for new sticky positions
			$query->found_posts = (int) $query->found_posts + (int) $sticky_count;
			$query->post_count  = (int) $query->post_count  + (int) $sticky_count;
		}
	}

	// Bail if no sticky topics empty or not an array
	if ( empty( $sticky_topics ) || ! is_array( $sticky_topics ) ) {
		return;
	}

	// Default ordered stickies array
	$ordered_stickies = array(
		'supers' => array(),
		'forums' => array()
	);

	// Separate supers from forums
	foreach ( $sticky_topics as $post ) {
		if ( in_array( $post->ID, $super_stickies, true ) ) {
			$ordered_stickies['supers'][] = $post;
		} elseif ( in_array( $post->ID, $forum_stickies, true ) ) {
			$ordered_stickies['forums'][] = $post;
		}
	}

	// Merge supers and forums, supers first
	$sticky_topics = array_merge( $ordered_stickies['supers'], $ordered_stickies['forums'] );

	// Update queried posts
	$query->posts = array_merge( $sticky_topics, array_values( $query->posts ) );
}

/**
 * Output the topic id
 *
 * @since 2.0.0 bbPress (r2485)
 */
function bbp_topic_id( $topic_id = 0) {
	echo bbp_get_topic_id( $topic_id );
}
	/**
	 * Return the topic id
	 *
	 * @since 2.0.0 bbPress (r2485)
	 *
	 * @param $topic_id Optional. Used to check emptiness
	 * @return int The topic id
	 */
	function bbp_get_topic_id( $topic_id = 0 ) {
		$bbp      = bbpress();
		$wp_query = bbp_get_wp_query();

		// Easy empty checking
		if ( ! empty( $topic_id ) && is_numeric( $topic_id ) ) {
			$bbp_topic_id = $topic_id;

		// Currently inside a topic loop
		} elseif ( ! empty( $bbp->topic_query->in_the_loop ) && isset( $bbp->topic_query->post->ID ) ) {
			$bbp_topic_id = $bbp->topic_query->post->ID;

		// Currently inside a search loop
		} elseif ( ! empty( $bbp->search_query->in_the_loop ) && isset( $bbp->search_query->post->ID ) && bbp_is_topic( $bbp->search_query->post->ID ) ) {
			$bbp_topic_id = $bbp->search_query->post->ID;

		// Currently viewing/editing a topic, likely alone
		} elseif ( ( bbp_is_single_topic() || bbp_is_topic_edit() ) && ! empty( $bbp->current_topic_id ) ) {
			$bbp_topic_id = $bbp->current_topic_id;

		// Currently viewing/editing a topic, likely in a loop
		} elseif ( ( bbp_is_single_topic() || bbp_is_topic_edit() ) && isset( $wp_query->post->ID ) ) {
			$bbp_topic_id = $wp_query->post->ID;

		// Currently viewing/editing a reply
		} elseif ( bbp_is_single_reply() || bbp_is_reply_edit() ) {
			$bbp_topic_id = bbp_get_reply_topic_id();

		// Fallback
		} else {
			$bbp_topic_id = 0;
		}

		// Filter & return
		return (int) apply_filters( 'bbp_get_topic_id', (int) $bbp_topic_id, $topic_id );
	}

/**
 * Gets a topic
 *
 * @since 2.0.0 bbPress (r2787)
 *
 * @return mixed Null if error or topic (in specified form) if success
 */
function bbp_get_topic( $topic, $output = OBJECT, $filter = 'raw' ) {

	// Maybe get ID from empty or int
	if ( empty( $topic ) || is_numeric( $topic ) ) {
		$topic = bbp_get_topic_id( $topic );
	}

	// Bail if no post object
	$topic = get_post( $topic, OBJECT, $filter );
	if ( empty( $topic ) ) {
		return $topic;
	}

	// Bail if not correct post type
	if ( $topic->post_type !== bbp_get_topic_post_type() ) {
		return null;
	}

	// Default return value is OBJECT
	$retval = $topic;

	// Array A
	if ( $output === ARRAY_A ) {
		$retval = get_object_vars( $topic );

	// Array N
	} elseif ( $output === ARRAY_N ) {
		$retval = array_values( get_object_vars( $topic ) );
	}

	// Filter & return
	return apply_filters( 'bbp_get_topic', $retval, $topic, $output, $filter );
}

/**
 * Output the link to the topic in the topic loop
 *
 * @since 2.0.0 bbPress (r2485)
 *
 * @param int $topic_id Optional. Topic id
 * @param string $redirect_to Optional. Pass a redirect value for use with
 *                              shortcodes and other fun things.
 */
function bbp_topic_permalink( $topic_id = 0, $redirect_to = '' ) {
	echo esc_url( bbp_get_topic_permalink( $topic_id, $redirect_to ) );
}
	/**
	 * Return the link to the topic
	 *
	 * @since 2.0.0 bbPress (r2485)
	 *
	 * @param int $topic_id Optional. Topic id
	 * @param string $redirect_to Optional. Pass a redirect value for use with
	 *                              shortcodes and other fun things.
	 * @return string Permanent link to topic
	 */
	function bbp_get_topic_permalink( $topic_id = 0, $redirect_to = '' ) {
		$topic_id = bbp_get_topic_id( $topic_id );

		// Use the redirect address
		if ( ! empty( $redirect_to ) ) {
			$topic_permalink = esc_url_raw( $redirect_to );

		// Use the topic permalink
		} else {
			$topic_permalink = get_permalink( $topic_id );
		}

		// Filter & return
		return apply_filters( 'bbp_get_topic_permalink', $topic_permalink, $topic_id );
	}

/**
 * Output the title of the topic
 *
 * @since 2.0.0 bbPress (r2485)
 *
 * @param int $topic_id Optional. Topic id
 */
function bbp_topic_title( $topic_id = 0 ) {
	echo bbp_get_topic_title( $topic_id );
}
	/**
	 * Return the title of the topic
	 *
	 * @since 2.0.0 bbPress (r2485)
	 *
	 * @param int $topic_id Optional. Topic id
	 * @return string Title of topic
	 */
	function bbp_get_topic_title( $topic_id = 0 ) {
		$topic_id = bbp_get_topic_id( $topic_id );
		$title    = get_the_title( $topic_id );

		// Filter & return
		return apply_filters( 'bbp_get_topic_title', $title, $topic_id );
	}

/**
 * Output the topic archive title
 *
 * @since 2.0.0 bbPress (r3249)
 *
 * @param string $title Default text to use as title
 */
function bbp_topic_archive_title( $title = '' ) {
	echo bbp_get_topic_archive_title( $title );
}
	/**
	 * Return the topic archive title
	 *
	 * @since 2.0.0 bbPress (r3249)
	 *
	 * @param string $title Default text to use as title
	 *
	 * @return string The topic archive title
	 */
	function bbp_get_topic_archive_title( $title = '' ) {

		// If no title was passed
		if ( empty( $title ) ) {

			// Set root text to page title
			$page = bbp_get_page_by_path( bbp_get_topic_archive_slug() );
			if ( ! empty( $page ) ) {
				$title = get_the_title( $page->ID );

			// Default to topic post type name label
			} else {
				$tto    = get_post_type_object( bbp_get_topic_post_type() );
				$title  = $tto->labels->name;
			}
		}

		// Filter & return
		return apply_filters( 'bbp_get_topic_archive_title', $title );
	}

/**
 * Output the content of the topic
 *
 * @since 2.0.0 bbPress (r2780)
 *
 * @param int $topic_id Optional. Topic id
 */
function bbp_topic_content( $topic_id = 0 ) {
	echo bbp_get_topic_content( $topic_id );
}
	/**
	 * Return the content of the topic
	 *
	 * @since 2.0.0 bbPress (r2780)
	 *
	 * @param int $topic_id Optional. Topic id
	 * @return string Content of the topic
	 */
	function bbp_get_topic_content( $topic_id = 0 ) {
		$topic_id = bbp_get_topic_id( $topic_id );

		// Check if password is required
		if ( post_password_required( $topic_id ) ) {
			return get_the_password_form();
		}

		$content = get_post_field( 'post_content', $topic_id );

		// Filter & return
		return apply_filters( 'bbp_get_topic_content', $content, $topic_id );
	}

/**
 * Output the excerpt of the topic
 *
 * @since 2.0.0 bbPress (r2780)
 *
 * @param int $topic_id Optional. Topic id
 * @param int $length Optional. Length of the excerpt. Defaults to 100 letters
 */
function bbp_topic_excerpt( $topic_id = 0, $length = 100 ) {
	echo bbp_get_topic_excerpt( $topic_id, $length );
}
	/**
	 * Return the excerpt of the topic
	 *
	 * @since 2.0.0 bbPress (r2780)
	 *
	 * @param int $topic_id Optional. topic id
	 * @param int $length Optional. Length of the excerpt. Defaults to 100
	 *                     letters
	 * @return string topic Excerpt
	 */
	function bbp_get_topic_excerpt( $topic_id = 0, $length = 100 ) {
		$topic_id = bbp_get_topic_id( $topic_id );
		$length   = (int) $length;
		$excerpt  = get_post_field( 'post_excerpt', $topic_id );

		if ( empty( $excerpt ) ) {
			$excerpt = bbp_get_topic_content( $topic_id );
		}

		$excerpt = trim( strip_tags( $excerpt ) );

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
		return apply_filters( 'bbp_get_topic_excerpt', $excerpt, $topic_id, $length );
	}

/**
 * Output the post date and time of a topic
 *
 * @since 2.2.0 bbPress (r4155)
 *
 * @param int $topic_id Optional. Topic id.
 * @param bool $humanize Optional. Humanize output using time_since
 * @param bool $gmt Optional. Use GMT
 */
function bbp_topic_post_date( $topic_id = 0, $humanize = false, $gmt = false ) {
	echo bbp_get_topic_post_date( $topic_id, $humanize, $gmt );
}
	/**
	 * Return the post date and time of a topic
	 *
	 * @since 2.2.0 bbPress (r4155)
	 *
	 * @param int $topic_id Optional. Topic id.
	 * @param bool $humanize Optional. Humanize output using time_since
	 * @param bool $gmt Optional. Use GMT
	 * @return string
	 */
	function bbp_get_topic_post_date( $topic_id = 0, $humanize = false, $gmt = false ) {
		$topic_id = bbp_get_topic_id( $topic_id );

		// 4 days, 4 hours ago
		if ( ! empty( $humanize ) ) {
			$gmt_s  = ! empty( $gmt ) ? 'G' : 'U';
			$date   = get_post_time( $gmt_s, $gmt, $topic_id );
			$time   = false; // For filter below
			$result = bbp_get_time_since( $date );

		// August 4, 2012 at 2:37 pm
		} else {
			$date   = get_post_time( get_option( 'date_format' ), $gmt, $topic_id, true );
			$time   = get_post_time( get_option( 'time_format' ), $gmt, $topic_id, true );
			$result = sprintf( _x( '%1$s at %2$s', 'date at time', 'bbpress' ), $date, $time );
		}

		// Filter & return
		return apply_filters( 'bbp_get_topic_post_date', $result, $topic_id, $humanize, $gmt, $date, $time );
	}

/**
 * Output pagination links of a topic within the topic loop
 *
 * @since 2.0.0 bbPress (r2966)
 *
 * @param array $args See {@link bbp_get_topic_pagination()}
 */
function bbp_topic_pagination( $args = array() ) {
	echo bbp_get_topic_pagination( $args );
}
	/**
	 * Returns pagination links of a topic within the topic loop
	 *
	 * @since 2.0.0 bbPress (r2966)
	 *
	 * @param array $args This function supports these arguments:
	 *  - topic_id: Topic id
	 *  - before: Before the links
	 *  - after: After the links
	 * @return string Pagination links
	 */
	function bbp_get_topic_pagination( $args = array() ) {

		// Bail if threading replies
		if ( bbp_thread_replies() ) {
			return;
		}

		// Parse arguments against default values
		$r = bbp_parse_args( $args, array(
			'topic_id' => bbp_get_topic_id(),
			'before'   => '<span class="bbp-topic-pagination">',
			'after'    => '</span>',
		), 'get_topic_pagination' );

		// Slug must be checked for topics that have never been approved/published
		$has_slug = bbp_get_topic( $r['topic_id'] )->post_name;

		// If pretty permalinks are enabled, make our pagination pretty
		$base = ! empty( $has_slug ) && bbp_use_pretty_urls() && ! bbp_is_topic_pending( $r['topic_id'] )
			? trailingslashit( get_permalink( $r['topic_id'] ) ) . user_trailingslashit( bbp_get_paged_slug() . '/%#%/' )
			: add_query_arg( 'paged', '%#%', get_permalink( $r['topic_id'] ) );

		// Get total and add 1 if topic is included in the reply loop
		$total = bbp_get_topic_reply_count( $r['topic_id'], true );

		// Bump if topic is in loop
		if ( ! bbp_show_lead_topic() ) {
			$total++;
		}

		// Total for pagination boundaries
		$total_pages = ceil( $total / bbp_get_replies_per_page() );

		// Maybe add view-all args
		$add_args = bbp_get_view_all( 'edit_others_replies' )
			? array( 'view' => 'all' )
			: false;

		// Pagination settings with filter
		$bbp_topic_pagination = apply_filters( 'bbp_get_topic_pagination', array(
			'base'      => $base,
			'total'     => $total_pages,
			'current'   => 0,
			'prev_next' => false,
			'mid_size'  => 2,
			'end_size'  => 2,
			'add_args'  => $add_args
		) );

		// Add pagination to query object
		$pagination_links = bbp_paginate_links( $bbp_topic_pagination );

		// Maybe add before and after to pagination links
		if ( ! empty( $pagination_links ) ) {
			$pagination_links = $r['before'] . $pagination_links . $r['after'];
		}

		// Filter & return
		return apply_filters( 'bbp_get_topic_pagination', $pagination_links, $args );
	}

/**
 * Append revisions to the topic content
 *
 * @since 2.0.0 bbPress (r2782)
 *
 * @param string $content Optional. Content to which we need to append the revisions to
 * @param int $topic_id Optional. Topic id
 * @return string Content with the revisions appended
 */
function bbp_topic_content_append_revisions( $content = '', $topic_id = 0 ) {

	// Bail if in admin or feed
	if ( is_admin() || is_feed() ) {
		return;
	}

	// Validate the ID
	$topic_id = bbp_get_topic_id( $topic_id );

	// Filter & return
	return apply_filters( 'bbp_topic_append_revisions', $content . bbp_get_topic_revision_log( $topic_id ), $content, $topic_id );
}

/**
 * Output the revision log of the topic
 *
 * @since 2.0.0 bbPress (r2782)
 *
 * @param int $topic_id Optional. Topic id
 */
function bbp_topic_revision_log( $topic_id = 0 ) {
	echo bbp_get_topic_revision_log( $topic_id );
}
	/**
	 * Return the formatted revision log of the topic
	 *
	 * @since 2.0.0 bbPress (r2782)
	 *
	 * @param int $topic_id Optional. Topic id
	 * @return string Revision log of the topic
	 */
	function bbp_get_topic_revision_log( $topic_id = 0 ) {

		// Create necessary variables
		$topic_id     = bbp_get_topic_id( $topic_id );
		$revision_log = bbp_get_topic_raw_revision_log( $topic_id );

		if ( empty( $topic_id ) || empty( $revision_log ) || ! is_array( $revision_log ) ) {
			return false;
		}

		$revisions = bbp_get_topic_revisions( $topic_id );
		if ( empty( $revisions ) ) {
			return false;
		}

		$retval = "\n\n" . '<ul id="bbp-topic-revision-log-' . esc_attr( $topic_id ) . '" class="bbp-topic-revision-log">' . "\n\n";

		// Loop through revisions
		foreach ( (array) $revisions as $revision ) {

			if ( empty( $revision_log[ $revision->ID ] ) ) {
				$author_id = $revision->post_author;
				$reason    = '';
			} else {
				$author_id = $revision_log[ $revision->ID ]['author'];
				$reason    = $revision_log[ $revision->ID ]['reason'];
			}

			$author = bbp_get_author_link( array( 'size' => 14, 'link_text' => bbp_get_topic_author_display_name( $revision->ID ), 'post_id' => $revision->ID ) );
			$since  = bbp_get_time_since( bbp_convert_date( $revision->post_modified ) );

			$retval .= "\t" . '<li id="bbp-topic-revision-log-' . esc_attr( $topic_id ) . '-item-' . esc_attr( $revision->ID ) . '" class="bbp-topic-revision-log-item">' . "\n";
			if ( ! empty( $reason ) ) {
				$retval .= "\t\t" . sprintf( esc_html__( 'This topic was modified %1$s by %2$s. Reason: %3$s', 'bbpress' ), esc_html( $since ), $author, esc_html( $reason ) ) . "\n";
			} else {
				$retval .= "\t\t" . sprintf( esc_html__( 'This topic was modified %1$s by %2$s.',              'bbpress' ), esc_html( $since ), $author ) . "\n";
			}
			$retval .= "\t" . '</li>' . "\n";
		}

		$retval .= "\n" . '</ul>' . "\n\n";

		// Filter & return
		return apply_filters( 'bbp_get_topic_revision_log', $retval, $topic_id );
	}
		/**
		 * Return the raw revision log of the topic
		 *
		 * @since 2.0.0 bbPress (r2782)
		 *
		 * @param int $topic_id Optional. Topic id
		 * @return string Raw revision log of the topic
		 */
		function bbp_get_topic_raw_revision_log( $topic_id = 0 ) {
			$topic_id = bbp_get_topic_id( $topic_id );

			$revision_log = get_post_meta( $topic_id, '_bbp_revision_log', true );
			$revision_log = ! empty( $revision_log )
				? $revision_log
				: array();

			// Filter & return
			return apply_filters( 'bbp_get_topic_raw_revision_log', $revision_log, $topic_id );
		}

/**
 * Return the revisions of the topic
 *
 * @since 2.0.0 bbPress (r2782)
 *
 * @param int $topic_id Optional. Topic id
 * @return string Topic revisions
 */
function bbp_get_topic_revisions( $topic_id = 0 ) {
	$topic_id  = bbp_get_topic_id( $topic_id );
	$revisions = wp_get_post_revisions( $topic_id, array( 'order' => 'ASC' ) );

	// Filter & return
	return apply_filters( 'bbp_get_topic_revisions', $revisions, $topic_id );
}

/**
 * Return the revision count of the topic
 *
 * @since 2.0.0 bbPress (r2782)
 *
 * @param int $topic_id Optional. Topic id
 * @return string Topic revision count
 */
function bbp_get_topic_revision_count( $topic_id = 0, $integer = false ) {
	$topic_id = bbp_get_topic_id( $topic_id );
	$count    = count( bbp_get_topic_revisions( $topic_id ) );
	$filter   = ( true === $integer )
		? 'bbp_get_topic_revision_count_int'
		: 'bbp_get_topic_revision_count';

	return apply_filters( $filter, $count, $topic_id );
}

/**
 * Is the topic a sticky or super sticky?
 *
 * @since 2.0.0 bbPress (r2754)
 *
 * @param int $topic_id Optional. Topic id
 * @param int $check_super Optional. If set to true and if the topic is not a
 *                           normal sticky, it is checked if it is a super
 *                           sticky or not. Defaults to true.
 * @return bool True if sticky or super sticky, false if not.
 */
function bbp_is_topic_sticky( $topic_id = 0, $check_super = true ) {
	$topic_id = bbp_get_topic_id( $topic_id );
	$forum_id = bbp_get_topic_forum_id( $topic_id );
	$stickies = bbp_get_stickies( $forum_id );
	$retval   = in_array( $topic_id, $stickies, true );

	// Maybe check super stickies
	if ( ( false === $retval ) && ( true === $check_super ) ) {
		$retval = bbp_is_topic_super_sticky( $topic_id );
	}

	// Filter & return
	return (bool) apply_filters( 'bbp_is_topic_sticky', $retval, $topic_id, $check_super );
}

/**
 * Is the topic a super sticky?
 *
 * @since 2.0.0 bbPress (r2754)
 *
 * @param int $topic_id Optional. Topic id
 * @return bool True if super sticky, false if not.
 */
function bbp_is_topic_super_sticky( $topic_id = 0 ) {
	$topic_id = bbp_get_topic_id( $topic_id );
	$stickies = bbp_get_super_stickies( $topic_id );
	$retval   = in_array( $topic_id, $stickies, true );

	// Filter & return
	return (bool) apply_filters( 'bbp_is_topic_super_sticky', $retval, $topic_id );
}

/**
 * Output the status of the topic
 *
 * @since 2.0.0 bbPress (r2667)
 *
 * @param int $topic_id Optional. Topic id
 */
function bbp_topic_status( $topic_id = 0 ) {
	echo bbp_get_topic_status( $topic_id );
}
	/**
	 * Return the status of the topic
	 *
	 * @since 2.0.0 bbPress (r2667)
	 *
	 * @param int $topic_id Optional. Topic id
	 * @return string Status of topic
	 */
	function bbp_get_topic_status( $topic_id = 0 ) {
		$topic_id = bbp_get_topic_id( $topic_id );

		// Filter & return
		return apply_filters( 'bbp_get_topic_status', get_post_status( $topic_id ), $topic_id );
	}

/**
 * Is the topic closed to new replies?
 *
 * @since 2.0.0 bbPress (r2746)
 *
 * @param int $topic_id Optional. Topic id
 *
 * @return bool True if closed, false if not.
 */
function bbp_is_topic_closed( $topic_id = 0 ) {
	$topic_id     = bbp_get_topic_id( $topic_id );
	$status       = bbp_get_closed_status_id();
	$topic_status = ( bbp_get_topic_status( $topic_id ) === $status );

	// Filter & return
	return (bool) apply_filters( 'bbp_is_topic_closed', $topic_status, $topic_id );
}

/**
 * Is the topic open to new replies?
 *
 * @since 2.0.0 bbPress (r2727)
 *
 * @param int $topic_id Optional. Topic id
 *
 * @return bool True if open, false if closed.
 */
function bbp_is_topic_open( $topic_id = 0 ) {
	return ! bbp_is_topic_closed( $topic_id );
}

/**
 * Is the topic publicly viewable?
 *
 * See bbp_get_public_topic_statuses() for public statuses.
 *
 * @since 2.6.0 bbPress (r6383)
 *
 * @param int $topic_id Optional. Topic id
 * @return bool True if public, false if not.
 */
function bbp_is_topic_public( $topic_id = 0 ) {
	$topic_id  = bbp_get_topic_id( $topic_id );
	$status    = bbp_get_topic_status( $topic_id );
	$public    = bbp_get_public_topic_statuses();
	$is_public = in_array( $status, $public, true );

	// Filter & return
	return (bool) apply_filters( 'bbp_is_topic_public', $is_public, $topic_id );
}

/**
 * Does the topic have a published status?
 *
 * @since 2.0.0 bbPress (r3496)
 *
 * @param int $topic_id Optional. Topic id
 * @return bool True if published, false if not.
 */
function bbp_is_topic_published( $topic_id = 0 ) {
	$topic_id     = bbp_get_topic_id( $topic_id );
	$status       = bbp_get_public_status_id();
	$topic_status = ( bbp_get_topic_status( $topic_id ) === $status );

	// Filter & return
	return (bool) apply_filters( 'bbp_is_topic_published', $topic_status, $topic_id );
}

/**
 * Is the topic marked as spam?
 *
 * @since 2.0.0 bbPress (r2727)
 *
 * @param int $topic_id Optional. Topic id
 * @return bool True if spam, false if not.
 */
function bbp_is_topic_spam( $topic_id = 0 ) {
	$topic_id     = bbp_get_topic_id( $topic_id );
	$status       = bbp_get_spam_status_id();
	$topic_status = ( bbp_get_topic_status( $topic_id ) === $status );

	// Filter & return
	return (bool) apply_filters( 'bbp_is_topic_spam', $topic_status, $topic_id );
}

/**
 * Is the topic trashed?
 *
 * @since 2.0.0 bbPress (r2888)
 *
 * @param int $topic_id Optional. Topic id
 * @return bool True if trashed, false if not.
 */
function bbp_is_topic_trash( $topic_id = 0 ) {
	$topic_id     = bbp_get_topic_id( $topic_id );
	$status       = bbp_get_trash_status_id();
	$topic_status = ( bbp_get_topic_status( $topic_id ) === $status );

	// Filter & return
	return (bool) apply_filters( 'bbp_is_topic_trash', $topic_status, $topic_id );
}

/**
 * Is the topic pending?
 *
 * @since 2.6.0 bbPress (r5504)
 *
 * @param int $topic_id Optional. Topic id
 * @return bool True if pending, false if not.
 */
function bbp_is_topic_pending( $topic_id = 0 ) {
	$topic_id     = bbp_get_topic_id( $topic_id );
	$status       = bbp_get_pending_status_id();
	$topic_status = ( bbp_get_topic_status( $topic_id ) === $status );

	// Filter & return
	return (bool) apply_filters( 'bbp_is_topic_pending', $topic_status, $topic_id );
}

/**
 * Is the topic private?
 *
 * @since 2.6.0 bbPress (r5504)
 *
 * @param int $topic_id Optional. Topic id
 * @return bool True if private, false if not.
 */
function bbp_is_topic_private( $topic_id = 0 ) {
	$topic_id     = bbp_get_topic_id( $topic_id );
	$status       = bbp_get_private_status_id();
	$topic_status = ( bbp_get_topic_status( $topic_id ) === $status );

	// Filter & return
	return (bool) apply_filters( 'bbp_is_topic_private', $topic_status, $topic_id );
}

/**
 * Is the posted by an anonymous user?
 *
 * @since 2.0.0 bbPress (r2753)
 *
 * @param int $topic_id Optional. Topic id
 * @return bool True if the post is by an anonymous user, false if not.
 */
function bbp_is_topic_anonymous( $topic_id = 0 ) {
	$topic_id = bbp_get_topic_id( $topic_id );
	$retval   = false;

	if ( ! bbp_get_topic_author_id( $topic_id ) ) {
		$retval = true;

	} elseif ( get_post_meta( $topic_id, '_bbp_anonymous_name',  true ) ) {
		$retval = true;

	} elseif ( get_post_meta( $topic_id, '_bbp_anonymous_email', true ) ) {
		$retval = true;
	}

	// Filter & return
	return (bool) apply_filters( 'bbp_is_topic_anonymous', $retval, $topic_id );
}

/**
 * Deprecated. Use bbp_topic_author_display_name() instead.
 *
 * Output the author of the topic.
 *
 * @since 2.0.0 bbPress (r2590)
 *
 * @deprecated 2.5.0 bbPress (r5119)
 *
 * @param int $topic_id Optional. Topic id
 */
function bbp_topic_author( $topic_id = 0 ) {
	echo bbp_get_topic_author( $topic_id );
}
	/**
	 * Deprecated. Use bbp_get_topic_author_display_name() instead.
	 *
	 * Return the author of the topic
	 *
	 * @since 2.0.0 bbPress (r2590)
	 *
	 * @deprecated 2.5.0 bbPress (r5119)
	 *
	 * @param int $topic_id Optional. Topic id
	 * @return string Author of topic
	 */
	function bbp_get_topic_author( $topic_id = 0 ) {
		$topic_id = bbp_get_topic_id( $topic_id );

		if ( ! bbp_is_topic_anonymous( $topic_id ) ) {
			$author = get_the_author_meta( 'display_name', bbp_get_topic_author_id( $topic_id ) );
		} else {
			$author = get_post_meta( $topic_id, '_bbp_anonymous_name', true );
		}

		// Filter & return
		return apply_filters( 'bbp_get_topic_author', $author, $topic_id );
	}

/**
 * Output the author ID of the topic
 *
 * @since 2.0.0 bbPress (r2590)
 *
 * @param int $topic_id Optional. Topic id
 */
function bbp_topic_author_id( $topic_id = 0 ) {
	echo bbp_get_topic_author_id( $topic_id );
}
	/**
	 * Return the author ID of the topic
	 *
	 * @since 2.0.0 bbPress (r2590)
	 *
	 * @param int $topic_id Optional. Topic id
	 * @return string Author of topic
	 */
	function bbp_get_topic_author_id( $topic_id = 0 ) {
		$topic_id  = bbp_get_topic_id( $topic_id );
		$author_id = get_post_field( 'post_author', $topic_id );

		// Filter & return
		return (int) apply_filters( 'bbp_get_topic_author_id', (int) $author_id, $topic_id );
	}

/**
 * Output the author display_name of the topic
 *
 * @since 2.0.0 bbPress (r2590)
 *
 * @param int $topic_id Optional. Topic id
 */
function bbp_topic_author_display_name( $topic_id = 0 ) {
	echo bbp_get_topic_author_display_name( $topic_id );
}
	/**
	 * Return the author display_name of the topic
	 *
	 * @since 2.0.0 bbPress (r2485)
	 *
	 * @param int $topic_id Optional. Topic id
	 * @return string Topic's author's display name
	 */
	function bbp_get_topic_author_display_name( $topic_id = 0 ) {
		$topic_id = bbp_get_topic_id( $topic_id );

		// Check for anonymous user
		if ( ! bbp_is_topic_anonymous( $topic_id ) ) {

			// Get the author ID
			$author_id = bbp_get_topic_author_id( $topic_id );

			// Try to get a display name
			$author_name = get_the_author_meta( 'display_name', $author_id );

			// Fall back to user login
			if ( empty( $author_name ) ) {
				$author_name = get_the_author_meta( 'user_login', $author_id );
			}

		// User does not have an account
		} else {
			$author_name = get_post_meta( $topic_id, '_bbp_anonymous_name', true );
		}

		// Fallback if nothing could be found
		if ( empty( $author_name ) ) {
			$author_name = bbp_get_fallback_display_name( $topic_id );
		}

		// Encode possible UTF8 display names
		if ( seems_utf8( $author_name ) === false ) {
			$author_name = utf8_encode( $author_name );
		}

		// Filter & return
		return apply_filters( 'bbp_get_topic_author_display_name', $author_name, $topic_id );
	}

/**
 * Output the author avatar of the topic
 *
 * @since 2.0.0 bbPress (r2590)
 *
 * @param int $topic_id Optional. Topic id
 * @param int $size Optional. Avatar size. Defaults to 40
 */
function bbp_topic_author_avatar( $topic_id = 0, $size = 40 ) {
	echo bbp_get_topic_author_avatar( $topic_id, $size );
}
	/**
	 * Return the author avatar of the topic
	 *
	 * @since 2.0.0 bbPress (r2590)
	 *
	 * @param int $topic_id Optional. Topic id
	 * @param int $size Optional. Avatar size. Defaults to 40
	 * @return string Avatar of the author of the topic
	 */
	function bbp_get_topic_author_avatar( $topic_id = 0, $size = 40 ) {
		$author_avatar = '';

		$topic_id = bbp_get_topic_id( $topic_id );
		if ( ! empty( $topic_id ) ) {
			if ( ! bbp_is_topic_anonymous( $topic_id ) ) {
				$author_avatar = get_avatar( bbp_get_topic_author_id( $topic_id ), $size );
			} else {
				$author_avatar = get_avatar( get_post_meta( $topic_id, '_bbp_anonymous_email', true ), $size );
			}
		}

		// Filter & return
		return apply_filters( 'bbp_get_topic_author_avatar', $author_avatar, $topic_id, $size );
	}

/**
 * Output the author link of the topic
 *
 * @since 2.0.0 bbPress (r2717)
 *
 * @param mixed|int $args If it is an integer, it is used as topic_id. Optional.
 */
function bbp_topic_author_link( $args = array() ) {
	echo bbp_get_topic_author_link( $args );
}
	/**
	 * Return the author link of the topic
	 *
	 * @since 2.0.0 bbPress (r2717)
	 *
	 * @param mixed|int $args If it is an integer, it is used as topic id.
	 *                         Optional.
	 * @return string Author link of topic
	 */
	function bbp_get_topic_author_link( $args = array() ) {

		// Parse arguments against default values
		$r = bbp_parse_args( $args, array(
			'post_id'    => 0,
			'link_title' => '',
			'type'       => 'both',
			'size'       => 80,
			'sep'        => '',
			'show_role'  => false
		), 'get_topic_author_link' );

		// Default return value
		$author_link = '';

		// Used as topic_id
		$topic_id = is_numeric( $args )
			? bbp_get_topic_id( $args )
			: bbp_get_topic_id( $r['post_id'] );

		// Topic ID is good
		if ( ! empty( $topic_id ) ) {

			// Get some useful topic information
			$author_url = bbp_get_topic_author_url( $topic_id );
			$anonymous  = bbp_is_topic_anonymous( $topic_id );

			// Tweak link title if empty
			if ( empty( $r['link_title'] ) ) {
				$author = bbp_get_topic_author_display_name( $topic_id );
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
				$author_links['avatar'] = bbp_get_topic_author_avatar( $topic_id, $r['size'] );
			}

			// Get display name (escaped, because never HTML)
			if ( ( 'name' === $r['type'] ) || ( 'both' === $r['type'] ) ) {
				$author_links['name'] = esc_html( bbp_get_topic_author_display_name( $topic_id ) );
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
			$sections    = apply_filters( 'bbp_get_topic_author_links', $author_links, $r, $args );

			// Assemble sections into author link
			$author_link = implode( $r['sep'], $sections );

			// Only wrap in link if profile exists
			if ( empty( $anonymous ) && bbp_user_has_profile( bbp_get_topic_author_id( $topic_id ) ) ) {
				$author_link = sprintf( '<a href="%1$s"%2$s%3$s>%4$s</a>', esc_url( $author_url ), $link_title, ' class="bbp-author-link"', $author_link );
			}

			// Role is not linked
			if ( true === $r['show_role'] ) {
				$author_link .= bbp_get_topic_author_role( array( 'topic_id' => $topic_id ) );
			}
		}

		// Filter & return
		return apply_filters( 'bbp_get_topic_author_link', $author_link, $r, $args );
	}

/**
 * Output the author url of the topic
 *
 * @since 2.0.0 bbPress (r2590)
 *
 * @param int $topic_id Optional. Topic id
 */
function bbp_topic_author_url( $topic_id = 0 ) {
	echo esc_url( bbp_get_topic_author_url( $topic_id ) );
}

	/**
	 * Return the author url of the topic
	 *
	 * @since 2.0.0 bbPress (r2590)
	 *
	 * @param int $topic_id Optional. Topic id
	 * @return string Author URL of topic
	 */
	function bbp_get_topic_author_url( $topic_id = 0 ) {
		$topic_id = bbp_get_topic_id( $topic_id );

		// Check for anonymous user or non-existant user
		if ( ! bbp_is_topic_anonymous( $topic_id ) && bbp_user_has_profile( bbp_get_topic_author_id( $topic_id ) ) ) {
			$author_url = bbp_get_user_profile_url( bbp_get_topic_author_id( $topic_id ) );
		} else {
			$author_url = get_post_meta( $topic_id, '_bbp_anonymous_website', true );

			// Set empty author_url as empty string
			if ( empty( $author_url ) ) {
				$author_url = '';
			}
		}

		// Filter & return
		return apply_filters( 'bbp_get_topic_author_url', $author_url, $topic_id );
	}

/**
 * Output the topic author email address
 *
 * @since 2.0.0 bbPress (r3445)
 *
 * @param int $topic_id Optional. Reply id
 */
function bbp_topic_author_email( $topic_id = 0 ) {
	echo bbp_get_topic_author_email( $topic_id );
}
	/**
	 * Return the topic author email address
	 *
	 * @since 2.0.0 bbPress (r3445)
	 *
	 * @param int $topic_id Optional. Reply id
	 * @return string Topic author email address
	 */
	function bbp_get_topic_author_email( $topic_id = 0 ) {
		$topic_id = bbp_get_topic_id( $topic_id );

		// Not anonymous user
		if ( ! bbp_is_topic_anonymous( $topic_id ) ) {

			// Use topic author email address
			$user_id      = bbp_get_topic_author_id( $topic_id );
			$user         = get_userdata( $user_id );
			$author_email = ! empty( $user->user_email ) ? $user->user_email : '';

		// Anonymous
		} else {

			// Get email from post meta
			$author_email = get_post_meta( $topic_id, '_bbp_anonymous_email', true );

			// Sanity check for missing email address
			if ( empty( $author_email ) ) {
				$author_email = '';
			}
		}

		// Filter & return
		return apply_filters( 'bbp_get_topic_author_email', $author_email, $topic_id );
	}

/**
 * Output the topic author role
 *
 * @since 2.1.0 bbPress (r3860)
 *
 * @param array $args Optional.
 */
function bbp_topic_author_role( $args = array() ) {
	echo bbp_get_topic_author_role( $args );
}
	/**
	 * Return the topic author role
	 *
	 * @since 2.1.0 bbPress (r3860)
	 *
	 * @param array $args Optional.
	 * @return string topic author role
	 */
	function bbp_get_topic_author_role( $args = array() ) {

		// Parse arguments against default values
		$r = bbp_parse_args( $args, array(
			'topic_id' => 0,
			'class'    => false,
			'before'   => '<div class="bbp-author-role">',
			'after'    => '</div>'
		), 'get_topic_author_role' );

		$topic_id = bbp_get_topic_id( $r['topic_id'] );
		$role     = bbp_get_user_display_role( bbp_get_topic_author_id( $topic_id ) );

		// Backwards compatibilty with old 'class' argument
		if ( ! empty( $r['class'] ) ) {
			$author_role = sprintf( '%1$s<div class="%2$s">%3$s</div>%4$s', $r['before'], $r['class'], $role, $r['after'] );

		// Simpler before & after arguments
		// https://bbpress.trac.wordpress.org/ticket/2557
		} else {
			$author_role = $r['before'] . $role . $r['after'];
		}

		// Filter & return
		return apply_filters( 'bbp_get_topic_author_role', $author_role, $r );
	}


/**
 * Output the title of the forum a topic belongs to
 *
 * @since 2.0.0 bbPress (r2485)
 *
 * @param int $topic_id Optional. Topic id
 */
function bbp_topic_forum_title( $topic_id = 0 ) {
	echo bbp_get_topic_forum_title( $topic_id );
}
	/**
	 * Return the title of the forum a topic belongs to
	 *
	 * @since 2.0.0 bbPress (r2485)
	 *
	 * @param int $topic_id Optional. Topic id
	 * @return string Topic forum title
	 */
	function bbp_get_topic_forum_title( $topic_id = 0 ) {
		$topic_id = bbp_get_topic_id( $topic_id );
		$forum_id = bbp_get_topic_forum_id( $topic_id );

		// Filter & return
		return apply_filters( 'bbp_get_topic_forum', bbp_get_forum_title( $forum_id ), $topic_id, $forum_id );
	}

/**
 * Output the forum id a topic belongs to
 *
 * @since 2.0.0 bbPress (r2491)
 *
 * @param int $topic_id Optional. Topic id
 */
function bbp_topic_forum_id( $topic_id = 0 ) {
	echo bbp_get_topic_forum_id( $topic_id );
}
	/**
	 * Return the forum id a topic belongs to
	 *
	 * @since 2.0.0 bbPress (r2491)
	 *
	 * @param int $topic_id Optional. Topic id
	 * @return int Topic forum id
	 */
	function bbp_get_topic_forum_id( $topic_id = 0 ) {
		$topic_id = bbp_get_topic_id( $topic_id );
		$forum_id = (int) get_post_field( 'post_parent', $topic_id );

		// Meta-data fallback
		if ( empty( $forum_id ) ) {
			$forum_id = (int) get_post_meta( $topic_id, '_bbp_forum_id', true );
		}

		// Filter
		if ( ! empty( $forum_id ) ) {
			$forum_id = (int) bbp_get_forum_id( $forum_id );
		}

		// Filter & return
		return (int) apply_filters( 'bbp_get_topic_forum_id', $forum_id, $topic_id );
	}

/**
 * Output the topics last active ID
 *
 * @since 2.0.0 bbPress (r2860)
 *
 * @param int $topic_id Optional. Forum id
 */
function bbp_topic_last_active_id( $topic_id = 0 ) {
	echo bbp_get_topic_last_active_id( $topic_id );
}
	/**
	 * Return the topics last active ID
	 *
	 * @since 2.0.0 bbPress (r2860)
	 *
	 * @param int $topic_id Optional. Forum id
	 * @return int Forum's last active id
	 */
	function bbp_get_topic_last_active_id( $topic_id = 0 ) {
		$topic_id  = bbp_get_topic_id( $topic_id );
		$active_id = (int) get_post_meta( $topic_id, '_bbp_last_active_id', true );

		// Filter & return
		return (int) apply_filters( 'bbp_get_topic_last_active_id', $active_id, $topic_id );
	}

/**
 * Output the topics last update date/time (aka freshness)
 *
 * @since 2.0.0 bbPress (r2625)
 *
 * @param int $topic_id Optional. Topic id
 */
function bbp_topic_last_active_time( $topic_id = 0 ) {
	echo bbp_get_topic_last_active_time( $topic_id );
}
	/**
	 * Return the topics last update date/time (aka freshness)
	 *
	 * @since 2.0.0 bbPress (r2625)
	 *
	 * @param int $topic_id Optional. Topic id
	 * @return string Topic freshness
	 */
	function bbp_get_topic_last_active_time( $topic_id = 0 ) {
		$topic_id = bbp_get_topic_id( $topic_id );

		// Try to get the most accurate freshness time possible
		$last_active = get_post_meta( $topic_id, '_bbp_last_active_time', true );
		if ( empty( $last_active ) ) {
			$reply_id = bbp_get_topic_last_reply_id( $topic_id );
			if ( ! empty( $reply_id ) ) {
				$last_active = get_post_field( 'post_date', $reply_id );
			} else {
				$last_active = get_post_field( 'post_date', $topic_id );
			}
		}

		$last_active = ! empty( $last_active ) ? bbp_get_time_since( bbp_convert_date( $last_active ) ) : '';

		// Filter & return
		return apply_filters( 'bbp_get_topic_last_active', $last_active, $topic_id );
	}

/** Topic Subscriptions *******************************************************/

/**
 * Output the topic subscription link
 *
 * @since 2.5.0 bbPress (r5156)
 * @since 2.6.0 bbPress (r6308) Add 'redirect_to' support
 */
function bbp_topic_subscription_link( $args = array() ) {
	echo bbp_get_topic_subscription_link( $args );
}

	/**
	 * Get the topic subscription link
	 *
	 * A custom wrapper for bbp_get_user_subscribe_link()
	 *
	 * @since 2.5.0 bbPress (r5156)
	 * @since 2.6.0 bbPress (r6308) Add 'redirect_to' support
	 */
	function bbp_get_topic_subscription_link( $args = array() ) {

		// Defaults
		$retval      = false;
		$user_id     = bbp_get_current_user_id();
		$redirect_to = bbp_is_subscriptions()
			? bbp_get_subscriptions_permalink( $user_id )
			: '';

		// Parse the arguments
		$r = bbp_parse_args( $args, array(
			'user_id'     => $user_id,
			'object_id'   => bbp_get_topic_id(),
			'object_type' => 'post',
			'before'      => '&nbsp;|&nbsp;',
			'after'       => '',
			'subscribe'   => esc_html__( 'Subscribe',   'bbpress' ),
			'unsubscribe' => esc_html__( 'Unsubscribe', 'bbpress' ),
			'redirect_to' => $redirect_to
		), 'get_topic_subscribe_link' );

		// Get the link
		$retval = bbp_get_user_subscribe_link( $r );

		// Filter & return
		return apply_filters( 'bbp_get_topic_subscribe_link', $retval, $r, $args );
	}

/** Topic Favorites ***********************************************************/

/**
 * Output the topic favorite link
 *
 * @since 2.5.0 bbPress (r5156)
 * @since 2.6.0 bbPress (r6308) Add 'redirect_to' support
 */
function bbp_topic_favorite_link( $args = array() ) {
	echo bbp_get_topic_favorite_link( $args );
}

	/**
	 * Get the forum favorite link
	 *
	 * A custom wrapper for bbp_get_user_favorites_link()
	 *
	 * @since 2.5.0 bbPress (r5156)
	 * @since 2.6.0 bbPress (r6308) Add 'redirect_to' support
	 */
	function bbp_get_topic_favorite_link( $args = array() ) {

		// No link
		$retval      = false;
		$user_id     = bbp_get_current_user_id();
		$redirect_to = bbp_is_favorites()
			? bbp_get_favorites_permalink( $user_id )
			: '';

		// Parse the arguments
		$r = bbp_parse_args( $args, array(
			'user_id'     => $user_id,
			'object_id'   => bbp_get_topic_id(),
			'object_type' => 'post',
			'before'      => '',
			'after'       => '',
			'favorite'    => esc_html__( 'Favorite',   'bbpress' ),
			'favorited'   => esc_html__( 'Unfavorite', 'bbpress' ),
			'redirect_to' => $redirect_to
		), 'get_topic_favorite_link' );

		// Get the link
		$retval = bbp_get_user_favorites_link( $r );

		// Filter & return
		return apply_filters( 'bbp_get_topic_favorite_link', $retval, $r, $args );
	}

/** Topic Last Reply **********************************************************/

/**
 * Output the id of the topics last reply
 *
 * @since 2.0.0 bbPress (r2625)
 *
 * @param int $topic_id Optional. Topic id
 */
function bbp_topic_last_reply_id( $topic_id = 0 ) {
	echo bbp_get_topic_last_reply_id( $topic_id );
}
	/**
	 * Return the id of the topics last reply
	 *
	 * @since 2.0.0 bbPress (r2625)
	 *
	 * @param int $topic_id Optional. Topic id
	 * @return int Topic last reply id
	 */
	function bbp_get_topic_last_reply_id( $topic_id = 0 ) {
		$topic_id = bbp_get_topic_id( $topic_id );
		$reply_id = (int) get_post_meta( $topic_id, '_bbp_last_reply_id', true );

		// Filter & return
		return (int) apply_filters( 'bbp_get_topic_last_reply_id', $reply_id, $topic_id );
	}

/**
 * Output the title of the last reply inside a topic
 *
 * @since 2.0.0 bbPress (r2753)
 *
 * @param int $topic_id Optional. Topic id
 */
function bbp_topic_last_reply_title( $topic_id = 0 ) {
	echo bbp_get_topic_last_reply_title( $topic_id );
}
	/**
	 * Return the title of the last reply inside a topic
	 *
	 * @since 2.0.0 bbPress (r2753)
	 * @since 2.6.0 bbPress https://bbpress.trac.wordpress.org/ticket/3039
	 *
	 * @param int $topic_id Optional. Topic id
	 * @return string Topic last reply title
	 */
	function bbp_get_topic_last_reply_title( $topic_id = 0 ) {
		$topic_id = bbp_get_topic_id( $topic_id );
		$reply_id = bbp_get_topic_last_reply_id( $topic_id );
		$retval   = bbp_get_reply_title( $reply_id );

		// Misspelled. Use 'bbp_get_topic_last_reply_title' hook instead.
		$retval = apply_filters( 'bbp_get_topic_last_topic_title', $retval, $topic_id, $reply_id );

		// Filter & return
		return apply_filters( 'bbp_get_topic_last_reply_title', $retval, $topic_id, $reply_id );
	}

/**
 * Output the link to the last reply in a topic
 *
 * @since 2.0.0 bbPress (r2464)
 *
 * @param int $topic_id Optional. Topic id
 */
function bbp_topic_last_reply_permalink( $topic_id = 0 ) {
	echo esc_url( bbp_get_topic_last_reply_permalink( $topic_id ) );
}
	/**
	 * Return the link to the last reply in a topic
	 *
	 * @since 2.0.0 bbPress (r2464)
	 *
	 * @param int $topic_id Optional. Topic id
	 * @return string Permanent link to the reply
	 */
	function bbp_get_topic_last_reply_permalink( $topic_id = 0 ) {
		$topic_id = bbp_get_topic_id( $topic_id );
		$reply_id = bbp_get_topic_last_reply_id( $topic_id );
		$retval   = bbp_get_reply_permalink( $reply_id );

		// Filter & return
		return apply_filters( 'bbp_get_topic_last_reply_permalink', $retval, $topic_id, $reply_id );
	}

/**
 * Output the link to the last reply in a topic
 *
 * @since 2.0.0 bbPress (r2683)
 *
 * @param int $topic_id Optional. Topic id
 */
function bbp_topic_last_reply_url( $topic_id = 0 ) {
	echo esc_url( bbp_get_topic_last_reply_url( $topic_id ) );
}
	/**
	 * Return the link to the last reply in a topic
	 *
	 * @since 2.0.0 bbPress (r2683)
	 *
	 * @param int $topic_id Optional. Topic id
	 * @return string Topic last reply url
	 */
	function bbp_get_topic_last_reply_url( $topic_id = 0 ) {
		$topic_id = bbp_get_topic_id( $topic_id );
		$reply_id = bbp_get_topic_last_reply_id( $topic_id );

		if ( ! empty( $reply_id ) && ( $reply_id !== $topic_id ) ) {
			$reply_url = bbp_get_reply_url( $reply_id );
		} else {
			$reply_url = bbp_get_topic_permalink( $topic_id );
		}

		// Filter & return
		return apply_filters( 'bbp_get_topic_last_reply_url', $reply_url, $topic_id, $reply_id );
	}

/**
 * Output link to the most recent activity inside a topic, complete with link
 * attributes and content.
 *
 * @since 2.0.0 bbPress (r2625)
 *
 * @param int $topic_id Optional. Topic id
 */
function bbp_topic_freshness_link( $topic_id = 0 ) {
	echo bbp_get_topic_freshness_link( $topic_id );
}
	/**
	 * Returns link to the most recent activity inside a topic, complete
	 * with link attributes and content.
	 *
	 * @since 2.0.0 bbPress (r2625)
	 *
	 * @param int $topic_id Optional. Topic id
	 * @return string Topic freshness link
	 */
	function bbp_get_topic_freshness_link( $topic_id = 0 ) {
		$topic_id   = bbp_get_topic_id( $topic_id );
		$link_url   = bbp_get_topic_last_reply_url( $topic_id );
		$title      = bbp_get_topic_last_reply_title( $topic_id );
		$time_since = bbp_get_topic_last_active_time( $topic_id );

		if ( ! empty( $time_since ) ) {
			$anchor = '<a href="' . esc_url( $link_url ) . '" title="' . esc_attr( $title ) . '">' . esc_html( $time_since ) . '</a>';
		} else {
			$anchor = esc_html__( 'No Replies', 'bbpress' );
		}

		// Filter & return
		return apply_filters( 'bbp_get_topic_freshness_link', $anchor, $topic_id, $time_since, $link_url, $title );
	}

/**
 * Output the replies link of the topic
 *
 * @since 2.0.0 bbPress (r2740)
 *
 * @param int $topic_id Optional. Topic id
 */
function bbp_topic_replies_link( $topic_id = 0 ) {
	echo bbp_get_topic_replies_link( $topic_id );
}

	/**
	 * Return the replies link of the topic
	 *
	 * @since 2.0.0 bbPress (r2740)
	 *
	 * @param int $topic_id Optional. Topic id
	 */
	function bbp_get_topic_replies_link( $topic_id = 0 ) {
		$topic_id = bbp_get_topic_id( $topic_id );
		$link     = bbp_get_topic_permalink( $topic_id );
		$replies  = sprintf( _n( '%s reply', '%s replies', bbp_get_topic_reply_count( $topic_id, true ), 'bbpress' ), bbp_get_topic_reply_count( $topic_id, false ) );

		// First link never has view=all
		$retval = bbp_get_view_all( 'edit_others_replies' )
			? "<a href='" . esc_url( bbp_remove_view_all( $link ) ) . "'>" . esc_html( $replies ) . "</a>"
			: $replies;

		// Any deleted replies?
		$deleted_int = bbp_get_topic_reply_count_hidden( $topic_id, true  );

		// This topic has hidden replies
		if ( ! empty( $deleted_int ) && current_user_can( 'edit_others_replies' ) ) {

			// Hidden replies
			$deleted_num = bbp_get_topic_reply_count_hidden( $topic_id, false );
			$extra       = ' ' . sprintf( _n( '(+%s hidden)', '(+%s hidden)', $deleted_int, 'bbpress' ), $deleted_num );

			// Hidden link
			$retval .= ! bbp_get_view_all( 'edit_others_replies' )
				? " <a href='" . esc_url( bbp_add_view_all( $link, true ) ) . "'>" . esc_html( $extra ) . "</a>"
				: " {$extra}";
		}

		// Filter & return
		return apply_filters( 'bbp_get_topic_replies_link', $retval, $topic_id );
	}

/**
 * Output total reply count of a topic
 *
 * @since 2.0.0 bbPress (r2485)
 *
 * @param int $topic_id Optional. Topic id
 * @param boolean $integer Optional. Whether or not to format the result
 */
function bbp_topic_reply_count( $topic_id = 0, $integer = false ) {
	echo bbp_get_topic_reply_count( $topic_id, $integer );
}
	/**
	 * Return total reply count of a topic
	 *
	 * @since 2.0.0 bbPress (r2485)
	 *
	 * @param int $topic_id Optional. Topic id
	 * @param boolean $integer Optional. Whether or not to format the result
	 * @return int Reply count
	 */
	function bbp_get_topic_reply_count( $topic_id = 0, $integer = false ) {
		$topic_id = bbp_get_topic_id( $topic_id );
		$replies  = (int) get_post_meta( $topic_id, '_bbp_reply_count', true );
		$filter   = ( true === $integer )
			? 'bbp_get_topic_reply_count_int'
			: 'bbp_get_topic_reply_count';

		return apply_filters( $filter, $replies, $topic_id );
	}

/**
 * Output total post count of a topic
 *
 * @since 2.0.0 bbPress (r2954)
 *
 * @param int $topic_id Optional. Topic id
 * @param boolean $integer Optional. Whether or not to format the result
 */
function bbp_topic_post_count( $topic_id = 0, $integer = false ) {
	echo bbp_get_topic_post_count( $topic_id, $integer );
}
	/**
	 * Return total post count of a topic
	 *
	 * @since 2.0.0 bbPress (r2954)
	 *
	 * @param int $topic_id Optional. Topic id
	 * @param boolean $integer Optional. Whether or not to format the result
	 * @return int Post count
	 */
	function bbp_get_topic_post_count( $topic_id = 0, $integer = false ) {
		$topic_id = bbp_get_topic_id( $topic_id );
		$replies  = ( (int) get_post_meta( $topic_id, '_bbp_reply_count', true ) ) + 1;
		$filter   = ( true === $integer )
			? 'bbp_get_topic_post_count_int'
			: 'bbp_get_topic_post_count';

		return apply_filters( $filter, $replies, $topic_id );
	}

/**
 * Output total hidden reply count of a topic (hidden includes trashed and
 * spammed replies)
 *
 * @since 2.0.0 bbPress (r2740)
 *
 * @param int $topic_id Optional. Topic id
 * @param boolean $integer Optional. Whether or not to format the result
 */
function bbp_topic_reply_count_hidden( $topic_id = 0, $integer = false ) {
	echo bbp_get_topic_reply_count_hidden( $topic_id, $integer );
}
	/**
	 * Return total hidden reply count of a topic (hidden includes trashed
	 * and spammed replies)
	 *
	 * @since 2.0.0 bbPress (r2740)
	 *
	 * @param int $topic_id Optional. Topic id
	 * @param boolean $integer Optional. Whether or not to format the result
	 * @return int Topic hidden reply count
	 */
	function bbp_get_topic_reply_count_hidden( $topic_id = 0, $integer = false ) {
		$topic_id = bbp_get_topic_id( $topic_id );
		$replies  = (int) get_post_meta( $topic_id, '_bbp_reply_count_hidden', true );
		$filter   = ( true === $integer )
			? 'bbp_get_topic_reply_count_hidden_int'
			: 'bbp_get_topic_reply_count_hidden';

		return apply_filters( $filter, $replies, $topic_id );
	}

/**
 * Output total voice count of a topic
 *
 * @since 2.0.0 bbPress (r2567)
 *
 * @param int $topic_id Optional. Topic id
 */
function bbp_topic_voice_count( $topic_id = 0, $integer = false ) {
	echo bbp_get_topic_voice_count( $topic_id, $integer );
}
	/**
	 * Return total voice count of a topic
	 *
	 * @since 2.0.0 bbPress (r2567)
	 *
	 * @param int $topic_id Optional. Topic id
	 * @return int Voice count of the topic
	 */
	function bbp_get_topic_voice_count( $topic_id = 0, $integer = false ) {
		$topic_id = bbp_get_topic_id( $topic_id );
		$voices   = (int) get_post_meta( $topic_id, '_bbp_voice_count', true );
		$filter   = ( true === $integer )
			? 'bbp_get_topic_voice_count_int'
			: 'bbp_get_topic_voice_count';

		return apply_filters( $filter, $voices, $topic_id );
	}

/**
 * Output a the tags of a topic
 *
 * @since 2.0.0 bbPress (r2688)
 *
 * @param int $topic_id Optional. Topic id
 * @param array $args See {@link bbp_get_topic_tag_list()}
 */
function bbp_topic_tag_list( $topic_id = 0, $args = array() ) {
	echo bbp_get_topic_tag_list( $topic_id, $args );
}
	/**
	 * Return the tags of a topic
	 *
	 * @since 2.0.0 bbPress (r2688)
	 *
	 * @param int $topic_id Optional. Topic id
	 * @param array $args This function supports these arguments:
	 *  - before: Before the tag list
	 *  - sep: Tag separator
	 *  - after: After the tag list
	 * @return string Tag list of the topic
	 */
	function bbp_get_topic_tag_list( $topic_id = 0, $args = array() ) {

		// Bail if topic-tags are off
		if ( ! bbp_allow_topic_tags() ) {
			return '';
		}

		// Parse arguments against default values
		$r = bbp_parse_args( $args, array(
			'before' => '<div class="bbp-topic-tags"><p>' . esc_html__( 'Tagged:', 'bbpress' ) . '&nbsp;',
			'sep'    => ', ',
			'after'  => '</p></div>',
			'none'   => ''
		), 'get_topic_tag_list' );

		$topic_id = bbp_get_topic_id( $topic_id );

		// Topic is spammed, so display pre-spam terms
		if ( bbp_is_topic_spam( $topic_id ) ) {

			// Get pre-spam terms
			$terms = get_post_meta( $topic_id, '_bbp_spam_topic_tags', true );

			// If terms exist, implode them and compile the return value
			if ( ! empty( $terms ) ) {
				$terms = $r['before'] . implode( $r['sep'], $terms ) . $r['after'];
			}

		// Topic is not spam so display a clickable term list
		} else {
			$terms = get_the_term_list( $topic_id, bbp_get_topic_tag_tax_id(), $r['before'], $r['sep'], $r['after'] );
		}

		// No terms so return none string
		if ( ! empty( $terms ) ) {
			$retval = $terms;
		} else {
			$retval = $r['none'];
		}

		return $retval;
	}

/**
 * Output the row class of a topic
 *
 * @since 2.0.0 bbPress (r2667)
 *
 * @param int $topic_id Optional. Topic id
 * @param array Extra classes you can pass when calling this function
 */
function bbp_topic_class( $topic_id = 0, $classes = array() ) {
	echo bbp_get_topic_class( $topic_id, $classes );
}
	/**
	 * Return the row class of a topic
	 *
	 * @since 2.0.0 bbPress (r2667)
	 *
	 * @param int $topic_id Optional. Topic id
	 * @param array Extra classes you can pass when calling this function
	 * @return string Row class of a topic
	 */
	function bbp_get_topic_class( $topic_id = 0, $classes = array() ) {
		$bbp       = bbpress();
		$topic_id  = bbp_get_topic_id( $topic_id );
		$forum_id  = bbp_get_topic_forum_id( $topic_id );
		$author_id = bbp_get_topic_author_id( $topic_id );
		$classes   = array_filter( (array) $classes );
		$count     = isset( $bbp->topic_query->current_post )
			? (int) $bbp->topic_query->current_post
			: 1;

		//  Stripes
		$even_odd = ( $count % 2 )
			? 'even'
			: 'odd';

		// Forum moderator replied to topic
		$forum_moderator = ( bbp_is_user_forum_moderator( $author_id, $forum_id ) === $author_id )
			? 'forum-mod'
			: '';

		// Is this topic a sticky?
		$sticky = bbp_is_topic_sticky( $topic_id, false )
			? 'sticky'
			: '';

		// Is this topic a super-sticky?
		$super_sticky = bbp_is_topic_super_sticky( $topic_id  )
			? 'super-sticky'
			: '';

		// Get topic classes
		$topic_classes = array(
			'loop-item-'        . $count,
			'user-id-'          . $author_id,
			'bbp-parent-forum-' . $forum_id,
			$even_odd,
			$forum_moderator,
			$sticky,
			$super_sticky
		);

		// Run the topic classes through the post-class filters, which also
		// handles the escaping of each individual class.
		$post_classes = get_post_class( array_merge( $classes, $topic_classes ), $topic_id );

		// Filter
		$new_classes  = apply_filters( 'bbp_get_topic_class', $post_classes, $topic_id, $classes );

		// Return
		return 'class="' . implode( ' ', $new_classes ) . '"';
	}

/** Topic Admin Links *********************************************************/

/**
 * Output admin links for topic
 *
 * @param array $args See {@link bbp_get_topic_admin_links()}
 */
function bbp_topic_admin_links( $args = array() ) {
	echo bbp_get_topic_admin_links( $args );
}
	/**
	 * Return admin links for topic.
	 *
	 * Move topic functionality is handled by the edit topic page.
	 *
	 * @param array $args This function supports these arguments:
	 *  - id: Optional. Topic id
	 *  - before: Before the links
	 *  - after: After the links
	 *  - sep: Links separator
	 *  - links: Topic admin links array
	 * @return string Topic admin links
	 */
	function bbp_get_topic_admin_links( $args = array() ) {

		// Parse arguments against default values
		$r = bbp_parse_args( $args, array(
			'id'     => bbp_get_topic_id(),
			'before' => '<span class="bbp-admin-links">',
			'after'  => '</span>',
			'sep'    => ' | ',
			'links'  => array()
		), 'get_topic_admin_links' );

		if ( empty( $r['links'] ) ) {
			$r['links'] = apply_filters( 'bbp_topic_admin_links', array(
				'edit'    => bbp_get_topic_edit_link   ( $r ),
				'merge'   => bbp_get_topic_merge_link  ( $r ),
				'close'   => bbp_get_topic_close_link  ( $r ),
				'stick'   => bbp_get_topic_stick_link  ( $r ),
				'trash'   => bbp_get_topic_trash_link  ( $r ),
				'spam'    => bbp_get_topic_spam_link   ( $r ),
				'approve' => bbp_get_topic_approve_link( $r ),
				'reply'   => bbp_get_topic_reply_link  ( $r )
			), $r['id'] );
		}

		// See if links need to be unset
		$topic_status = bbp_get_topic_status( $r['id'] );
		if ( in_array( $topic_status, bbp_get_non_public_topic_statuses(), true ) ) {

			// Close link shouldn't be visible on trashed/spammed/pending topics
			unset( $r['links']['close'] );

			// Spam link shouldn't be visible on trashed topics
			if ( bbp_get_trash_status_id() === $topic_status ) {
				unset( $r['links']['spam'] );

			// Trash link shouldn't be visible on spam topics
			} elseif ( bbp_get_spam_status_id() === $topic_status ) {
				unset( $r['links']['trash'] );
			}
		}

		// Process the admin links
		$links  = implode( $r['sep'], array_filter( $r['links'] ) );
		$retval = $r['before'] . $links . $r['after'];

		// Filter & return
		return apply_filters( 'bbp_get_topic_admin_links', $retval, $r, $args );
	}

/**
 * Output the edit link of the topic
 *
 * @since 2.0.0 bbPress (r2727)
 *
 * @param array $args See {@link bbp_get_topic_edit_link()}
 */
function bbp_topic_edit_link( $args = array() ) {
	echo bbp_get_topic_edit_link( $args );
}

	/**
	 * Return the edit link of the topic
	 *
	 * @since 2.0.0 bbPress (r2727)
	 *
	 * @param array $args This function supports these args:
	 *  - id: Optional. Topic id
	 *  - link_before: Before the link
	 *  - link_after: After the link
	 *  - edit_text: Edit text
	 * @return string Topic edit link
	 */
	function bbp_get_topic_edit_link( $args = array() ) {

		// Parse arguments against default values
		$r = bbp_parse_args( $args, array(
			'id'           => 0,
			'link_before'  => '',
			'link_after'   => '',
			'edit_text'    => esc_html__( 'Edit', 'bbpress' )
		), 'get_topic_edit_link' );

		// Get the topic
		$topic = bbp_get_topic( $r['id'] );

		// Bypass check if user has caps
		if ( ! current_user_can( 'edit_others_topics' ) ) {

			// User cannot edit or it is past the lock time
			if ( empty( $topic ) || ! current_user_can( 'edit_topic', $topic->ID ) || bbp_past_edit_lock( $topic->post_date_gmt ) ) {
				return;
			}
		}

		// Get uri
		$uri = bbp_get_topic_edit_url( $topic->ID );

		// Bail if no uri
		if ( empty( $uri ) ) {
			return;
		}

		$retval = $r['link_before'] . '<a href="' . esc_url( $uri ) . '" class="bbp-topic-edit-link">' . $r['edit_text'] . '</a>' . $r['link_after'];

		// Filter & return
		return apply_filters( 'bbp_get_topic_edit_link', $retval, $r, $args );
	}

/**
 * Output URL to the topic edit page
 *
 * @since 2.0.0 bbPress (r2753)
 *
 * @param int $topic_id Optional. Topic id
 */
function bbp_topic_edit_url( $topic_id = 0 ) {
	echo esc_url( bbp_get_topic_edit_url( $topic_id ) );
}
	/**
	 * Return URL to the topic edit page
	 *
	 * @since 2.0.0 bbPress (r2753)
	 *
	 * @param int $topic_id Optional. Topic id
	 * @return string Topic edit url
	 */
	function bbp_get_topic_edit_url( $topic_id = 0 ) {

		$topic = bbp_get_topic( $topic_id );
		if ( empty( $topic ) ) {
			return;
		}

		// Remove view=all link from edit
		$topic_link = bbp_remove_view_all( bbp_get_topic_permalink( $topic_id ) );

		// Pretty permalinks, previously used `bbp_use_pretty_urls()`
		// https://bbpress.trac.wordpress.org/ticket/3054
		if ( false === strpos( $topic_link, '?' ) ) {
			$url = trailingslashit( $topic_link ) . bbp_get_edit_slug();
			$url = user_trailingslashit( $url );

		// Unpretty permalinks
		} else {
			$url = add_query_arg( array(
				bbp_get_topic_post_type() => $topic->post_name,
				bbp_get_edit_rewrite_id() => '1'
			), $topic_link );
		}

		// Maybe add view=all
		$url = bbp_add_view_all( $url );

		// Filter & return
		return apply_filters( 'bbp_get_topic_edit_url', $url, $topic_id );
	}

/**
 * Output the trash link of the topic
 *
 * @since 2.0.0 bbPress (r2727)
 *
 * @param array $args See {@link bbp_get_topic_trash_link()}
 */
function bbp_topic_trash_link( $args = array() ) {
	echo bbp_get_topic_trash_link( $args );
}

	/**
	 * Return the trash link of the topic
	 *
	 * @since 2.0.0 bbPress (r2727)
	 *
	 * @param array $args This function supports these args:
	 *  - id: Optional. Topic id
	 *  - link_before: Before the link
	 *  - link_after: After the link
	 *  - sep: Links separator
	 *  - trash_text: Trash text
	 *  - restore_text: Restore text
	 *  - delete_text: Delete text
	 * @return string Topic trash link
	 */
	function bbp_get_topic_trash_link( $args = array() ) {

		// Parse arguments against default values
		$r = bbp_parse_args( $args, array(
			'id'           => 0,
			'link_before'  => '',
			'link_after'   => '',
			'sep'          => ' | ',
			'trash_text'   => esc_html__( 'Trash',   'bbpress' ),
			'restore_text' => esc_html__( 'Restore', 'bbpress' ),
			'delete_text'  => esc_html__( 'Delete',  'bbpress' )
		), 'get_topic_trash_link' );

		// Get topic
		$topic = bbp_get_topic( $r['id'] );

		// Bail if no topic or current user cannot delete
		if ( empty( $topic ) || ! current_user_can( 'delete_topic', $topic->ID ) ) {
			return;
		}

		$actions    = array();
		$trash_days = bbp_get_trash_days( bbp_get_topic_post_type() );

		if ( bbp_is_topic_trash( $topic->ID ) ) {
			$actions['untrash'] = '<a title="' . esc_attr__( 'Restore this item from the Trash', 'bbpress' ) . '" href="' . esc_url( wp_nonce_url( add_query_arg( array( 'action' => 'bbp_toggle_topic_trash', 'sub_action' => 'untrash', 'topic_id' => $topic->ID ) ), 'untrash-' . $topic->post_type . '_' . $topic->ID ) ) . '" class="bbp-topic-restore-link">' . $r['restore_text'] . '</a>';
		} elseif ( ! empty( $trash_days ) ) {
			$actions['trash']   = '<a title="' . esc_attr__( 'Move this item to the Trash',      'bbpress' ) . '" href="' . esc_url( wp_nonce_url( add_query_arg( array( 'action' => 'bbp_toggle_topic_trash', 'sub_action' => 'trash',   'topic_id' => $topic->ID ) ), 'trash-'   . $topic->post_type . '_' . $topic->ID ) ) . '" class="bbp-topic-trash-link">'   . $r['trash_text']   . '</a>';
		}

		if ( bbp_is_topic_trash( $topic->ID ) || empty( $trash_days ) ) {
			$actions['delete']  = '<a title="' . esc_attr__( 'Delete this item permanently',     'bbpress' ) . '" href="' . esc_url( wp_nonce_url( add_query_arg( array( 'action' => 'bbp_toggle_topic_trash', 'sub_action' => 'delete',  'topic_id' => $topic->ID ) ), 'delete-'  . $topic->post_type . '_' . $topic->ID ) ) . '" onclick="return confirm(\'' . esc_js( esc_html__( 'Are you sure you want to delete that permanently?', 'bbpress' ) ) . '\' );" class="bbp-topic-delete-link">' . $r['delete_text'] . '</a>';
		}

		// Process the admin links
		$retval = $r['link_before'] . implode( $r['sep'], $actions ) . $r['link_after'];

		// Filter & return
		return apply_filters( 'bbp_get_topic_trash_link', $retval, $r, $args );
	}

/**
 * Output the close link of the topic
 *
 * @since 2.0.0 bbPress (r2727)
 *
 * @param array $args See {@link bbp_get_topic_close_link()}
 */
function bbp_topic_close_link( $args = array() ) {
	echo bbp_get_topic_close_link( $args );
}

	/**
	 * Return the close link of the topic
	 *
	 * @since 2.0.0 bbPress (r2727)
	 *
	 * @param array $args This function supports these args:
	 *  - id: Optional. Topic id
	 *  - link_before: Before the link
	 *  - link_after: After the link
	 *  - close_text: Close text
	 *  - open_text: Open text
	 * @return string Topic close link
	 */
	function bbp_get_topic_close_link( $args = array() ) {

		// Parse arguments against default values
		$r = bbp_parse_args( $args, array(
			'id'          => 0,
			'link_before' => '',
			'link_after'  => '',
			'sep'         => ' | ',
			'close_text'  => esc_html_x( 'Close', 'Close the topic', 'bbpress' ),
			'open_text'   => esc_html_x( 'Open',  'Open the topic', 'bbpress' )
		), 'get_topic_close_link' );

		// Get topic
		$topic = bbp_get_topic( $r['id'] );

		// Bail if no topic or current user cannot moderate
		if ( empty( $topic ) || ! current_user_can( 'moderate', $topic->ID ) ) {
			return;
		}

		$display = bbp_is_topic_open( $topic->ID ) ? $r['close_text'] : $r['open_text'];
		$uri     = add_query_arg( array( 'action' => 'bbp_toggle_topic_close', 'topic_id' => $topic->ID ) );
		$uri     = wp_nonce_url( $uri, 'close-topic_' . $topic->ID );
		$retval  = $r['link_before'] . '<a href="' . esc_url( $uri ) . '" class="bbp-topic-close-link">' . $display . '</a>' . $r['link_after'];

		// Filter & return
		return apply_filters( 'bbp_get_topic_close_link', $retval, $r, $args );
	}

/**
 * Output the approve link of the topic
 *
 * @since 2.6.0 bbPress (r5504)
 *
 * @param array $args See {@link bbp_get_topic_approve_link()}
 */
function bbp_topic_approve_link( $args = array() ) {
	echo bbp_get_topic_approve_link( $args );
}

	/**
	 * Return the approve link of the topic
	 *
	 * @since 2.6.0 bbPress (r5504)
	 *
	 * @param array $args This function supports these args:
	 *  - id: Optional. Topic id
	 *  - link_before: Before the link
	 *  - link_after: After the link
	 *  - sep: Separator between links
	 *  - approve_text: Approve text
	 *  - unapprove_text: Unapprove text
	 * @return string Topic approve link
	 */
	function bbp_get_topic_approve_link( $args = array() ) {

		// Parse arguments against default values
		$r = bbp_parse_args( $args, array(
			'id'             => 0,
			'link_before'    => '',
			'link_after'     => '',
			'sep'            => ' | ',
			'approve_text'   => esc_html_x( 'Approve',   'Approve the topic', 'bbpress' ),
			'unapprove_text' => esc_html_x( 'Unapprove', 'Unapprove the topic', 'bbpress' )
		), 'get_topic_approve_link' );

		// Get topic
		$topic = bbp_get_topic( $r['id'] );

		// Bail if no topic or current user cannot moderate
		if ( empty( $topic ) || ! current_user_can( 'moderate', $topic->ID ) ) {
			return;
		}

		$display = bbp_is_topic_pending( $topic->ID ) ? $r['approve_text'] : $r['unapprove_text'];
		$uri     = add_query_arg( array( 'action' => 'bbp_toggle_topic_approve', 'topic_id' => $topic->ID ) );
		$uri     = wp_nonce_url( $uri, 'approve-topic_' . $topic->ID );
		$retval  = $r['link_before'] . '<a href="' . esc_url( $uri ) . '" class="bbp-topic-approve-link">' . $display . '</a>' . $r['link_after'];

		// Filter & return
		return apply_filters( 'bbp_get_topic_approve_link', $retval, $r, $args );
	}

/**
 * Output the stick link of the topic
 *
 * @since 2.0.0 bbPress (r2754)
 *
 * @param array $args See {@link bbp_get_topic_stick_link()}
 */
function bbp_topic_stick_link( $args = array() ) {
	echo bbp_get_topic_stick_link( $args );
}

	/**
	 * Return the stick link of the topic
	 *
	 * @since 2.0.0 bbPress (r2754)
	 *
	 * @param array $args This function supports these args:
	 *  - id: Optional. Topic id
	 *  - link_before: Before the link
	 *  - link_after: After the link
	 *  - stick_text: Stick text
	 *  - unstick_text: Unstick text
	 *  - super_text: Stick to front text
	 *
	 * @return string Topic stick link
	 */
	function bbp_get_topic_stick_link( $args = array() ) {

		// Parse arguments against default values
		$r = bbp_parse_args( $args, array(
			'id'           => 0,
			'link_before'  => '',
			'link_after'   => '',
			'stick_text'   => esc_html__( 'Stick',      'bbpress' ),
			'unstick_text' => esc_html__( 'Unstick',    'bbpress' ),
			'super_text'   => esc_html__( '(to front)', 'bbpress' ),
		), 'get_topic_stick_link' );

		// Get topic
		$topic = bbp_get_topic( $r['id'] );

		// Bail if no topic or current user cannot moderate
		if ( empty( $topic ) || ! current_user_can( 'moderate', $topic->ID ) ) {
			return;
		}

		$is_sticky = bbp_is_topic_sticky( $topic->ID );

		$stick_uri = add_query_arg( array( 'action' => 'bbp_toggle_topic_stick', 'topic_id' => $topic->ID ) );
		$stick_uri = wp_nonce_url( $stick_uri, 'stick-topic_' . $topic->ID );

		$stick_display = ( true === $is_sticky ) ? $r['unstick_text'] : $r['stick_text'];
		$stick_display = '<a href="' . esc_url( $stick_uri ) . '" class="bbp-topic-sticky-link">' . $stick_display . '</a>';

		if ( empty( $is_sticky ) ) {
			$super_uri = add_query_arg( array( 'action' => 'bbp_toggle_topic_stick', 'topic_id' => $topic->ID, 'super' => 1 ) );
			$super_uri = wp_nonce_url( $super_uri, 'stick-topic_' . $topic->ID );

			$super_display = ' <a href="' . esc_url( $super_uri ) . '" class="bbp-topic-super-sticky-link">' . $r['super_text'] . '</a>';
		} else {
			$super_display = '';
		}

		// Combine the HTML into 1 string
		$retval = $r['link_before'] . $stick_display . $super_display . $r['link_after'];

		// Filter & return
		return apply_filters( 'bbp_get_topic_stick_link', $retval, $r, $args );
	}

/**
 * Output the merge link of the topic
 *
 * @since 2.0.0 bbPress (r2756)
 *
 * @param array $args
 */
function bbp_topic_merge_link( $args = array() ) {
	echo bbp_get_topic_merge_link( $args );
}

	/**
	 * Return the merge link of the topic
	 *
	 * @since 2.0.0 bbPress (r2756)
	 *
	 * @param array $args This function supports these args:
	 *  - id: Optional. Topic id
	 *  - link_before: Before the link
	 *  - link_after: After the link
	 *  - merge_text: Merge text
	 *
	 * @return string Topic merge link
	 */
	function bbp_get_topic_merge_link( $args = array() ) {

		// Parse arguments against default values
		$r = bbp_parse_args( $args, array(
			'id'           => 0,
			'link_before'  => '',
			'link_after'   => '',
			'merge_text'   => esc_html__( 'Merge', 'bbpress' ),
		), 'get_topic_merge_link' );

		// Get topic
		$topic = bbp_get_topic( $r['id'] );

		// Bail if no topic or current user cannot moderate
		if ( empty( $topic ) || ! current_user_can( 'moderate', $topic->ID ) ) {
			return;
		}

		$uri    = add_query_arg( array( 'action' => 'merge' ), bbp_get_topic_edit_url( $topic->ID ) );
		$retval = $r['link_before'] . '<a href="' . esc_url( $uri ) . '" class="bbp-topic-merge-link">' . $r['merge_text'] . '</a>' . $r['link_after'];

		// Filter & return
		return apply_filters( 'bbp_get_topic_merge_link', $retval, $r, $args );
	}

/**
 * Output the spam link of the topic
 *
 * @since 2.0.0 bbPress (r2727)
 *
 * @param array $args See {@link bbp_get_topic_spam_link()}
 */
function bbp_topic_spam_link( $args = array() ) {
	echo bbp_get_topic_spam_link( $args );
}

	/**
	 * Return the spam link of the topic
	 *
	 * @since 2.0.0 bbPress (r2727)
	 *
	 * @param array $args This function supports these args:
	 *  - id: Optional. Topic id
	 *  - link_before: Before the link
	 *  - link_after: After the link
	 *  - spam_text: Spam text
	 *  - unspam_text: Unspam text
	 *
	 * @return string Topic spam link
	 */
	function bbp_get_topic_spam_link( $args = array() ) {

		// Parse arguments against default values
		$r = bbp_parse_args( $args, array(
			'id'           => 0,
			'link_before'  => '',
			'link_after'   => '',
			'sep'          => ' | ',
			'spam_text'    => esc_html__( 'Spam',   'bbpress' ),
			'unspam_text'  => esc_html__( 'Unspam', 'bbpress' )
		), 'get_topic_spam_link' );

		$topic = bbp_get_topic( $r['id'] );

		if ( empty( $topic ) || ! current_user_can( 'moderate', $topic->ID ) ) {
			return;
		}

		$display = bbp_is_topic_spam( $topic->ID ) ? $r['unspam_text'] : $r['spam_text'];
		$uri     = add_query_arg( array( 'action' => 'bbp_toggle_topic_spam', 'topic_id' => $topic->ID ) );
		$uri     = wp_nonce_url( $uri, 'spam-topic_' . $topic->ID );
		$retval  = $r['link_before'] . '<a href="' . esc_url( $uri ) . '" class="bbp-topic-spam-link">' . $display . '</a>' . $r['link_after'];

		// Filter & return
		return apply_filters( 'bbp_get_topic_spam_link', $retval, $r, $args );
	}

/**
 * Output the link to go directly to the reply form
 *
 * @since 2.4.0 bbPress (r4966)
 *
 * @param array $args
 */
function bbp_topic_reply_link( $args = array() ) {
	echo bbp_get_topic_reply_link( $args );
}

	/**
	 * Return the link to go directly to the reply form
	 *
	 * @since 2.4.0 bbPress (r4966)
	 *
	 * @param array $args Arguments
	 *
	 * @return string Link for a reply to a topic
	 */
	function bbp_get_topic_reply_link( $args = array() ) {

		// Parse arguments against default values
		$r = bbp_parse_args( $args, array(
			'id'          => 0,
			'link_before' => '',
			'link_after'  => '',
			'reply_text'  => esc_html_x( 'Reply', 'verb', 'bbpress' ),
		), 'get_topic_reply_link' );

		// Get the topic to use it's ID and post_parent
		$topic = bbp_get_topic( $r['id'] );

		// Bail if no topic or user cannot reply
		if ( empty( $topic ) || bbp_is_single_reply() || ! bbp_current_user_can_access_create_reply_form() ) {
			return;
		}

		// Add $uri to the array, to be passed through the filter
		$r['uri'] = '#new-post';
		$retval   = $r['link_before'] . '<a role="button" href="' . esc_url( $r['uri'] ) . '" class="bbp-topic-reply-link">' . $r['reply_text'] . '</a>' . $r['link_after'];

		// Filter & return
		return apply_filters( 'bbp_get_topic_reply_link', $retval, $r, $args );
	}

/** Topic Pagination **********************************************************/

/**
 * Return the base URL used inside of pagination links
 *
 * @since 2.6.0 bbPress (r6402)
 *
 * @param int $forum_id
 * @return string
 */
function bbp_get_topics_pagination_base( $forum_id = 0 ) {

	// If pretty permalinks are enabled, make our pagination pretty
	if ( bbp_use_pretty_urls() ) {

		// User's topics
		if ( bbp_is_single_user_topics() ) {
			$base = bbp_get_user_topics_created_url( bbp_get_displayed_user_id() );

		// User's engagements
		} elseif ( bbp_is_single_user_engagements() ) {
			$base = bbp_get_user_engagements_url( bbp_get_displayed_user_id() );

		// User's favorites
		} elseif ( bbp_is_favorites() ) {
			$base = bbp_get_favorites_permalink( bbp_get_displayed_user_id() );

		// User's subscriptions
		} elseif ( bbp_is_subscriptions() ) {
			$base = bbp_get_subscriptions_permalink( bbp_get_displayed_user_id() );

		// Root profile page
		} elseif ( bbp_is_single_user() ) {
			$base = bbp_get_user_profile_url( bbp_get_displayed_user_id() );

		// View
		} elseif ( bbp_is_single_view() ) {
			$base = bbp_get_view_url();

		// Topic tag
		} elseif ( bbp_is_topic_tag() ) {
			$base = bbp_get_topic_tag_link();

		// Page or single post
		} elseif ( is_page() || is_single() ) {
			$base = get_permalink();

		// Forum archive
		} elseif ( bbp_is_forum_archive() ) {
			$base = bbp_get_forums_url();

		// Topic archive
		} elseif ( bbp_is_topic_archive() ) {
			$base = bbp_get_topics_url();

		// Default
		} else {
			$base = get_permalink( $forum_id );
		}

		// Use pagination base
		$base = trailingslashit( $base ) . user_trailingslashit( bbp_get_paged_slug() . '/%#%/' );

	// Unpretty pagination
	} else {
		$base = add_query_arg( 'paged', '%#%' );
	}

	// Filter & return
	return apply_filters( 'bbp_get_topics_pagination_base', $base, $forum_id );
}

/**
 * Output the pagination count
 *
 * The results are unescaped by design, to allow them to be filtered freely via
 * the 'bbp_get_forum_pagination_count' filter.
 *
 * @since 2.0.0 bbPress (r2519)
 */
function bbp_forum_pagination_count() {
	echo bbp_get_forum_pagination_count();
}
	/**
	 * Return the pagination count
	 *
	 * @since 2.0.0 bbPress (r2519)
	 *
	 * @return string Forum Pagination count
	 */
	function bbp_get_forum_pagination_count() {
		$bbp = bbpress();

		// Define local variable(s)
		$retstr = '';

		// Topic query exists
		if ( ! empty( $bbp->topic_query ) ) {

			// Set pagination values
			$count_int = intval( $bbp->topic_query->post_count );
			$start_num = intval( ( $bbp->topic_query->paged - 1 ) * $bbp->topic_query->posts_per_page ) + 1;
			$total_int = ! empty( $bbp->topic_query->found_posts )
				? (int) $bbp->topic_query->found_posts
				: $count_int;

			// Format numbers for display
			$count_num = bbp_number_format( $count_int );
			$from_num  = bbp_number_format( $start_num );
			$total     = bbp_number_format( $total_int );
			$to_num    = bbp_number_format( ( $start_num + ( $bbp->topic_query->posts_per_page - 1 ) > $bbp->topic_query->found_posts )
				? $bbp->topic_query->found_posts
				: $start_num + ( $bbp->topic_query->posts_per_page - 1 ) );

			// Several topics in a forum with a single page
			if ( empty( $to_num ) ) {
				$retstr = sprintf( _n( 'Viewing %1$s topic', 'Viewing %1$s topics', $total_int, 'bbpress' ), $total );

			// Several topics in a forum with several pages
			} else {
				$retstr = sprintf( _n( 'Viewing topic %2$s (of %4$s total)', 'Viewing %1$s topics - %2$s through %3$s (of %4$s total)', $total_int, 'bbpress' ), $count_num, $from_num, $to_num, $total );
			}

			// Escape results of _n()
			$retstr = esc_html( $retstr );
		}

		// Filter & return
		return apply_filters( 'bbp_get_forum_pagination_count', $retstr );
	}

/**
 * Output pagination links
 *
 * @since 2.0.0 bbPress (r2519)
 */
function bbp_forum_pagination_links() {
	echo bbp_get_forum_pagination_links();
}
	/**
	 * Return pagination links
	 *
	 * @since 2.0.0 bbPress (r2519)
	 *
	 * @return string Pagination links
	 */
	function bbp_get_forum_pagination_links() {
		$bbp = bbpress();

		if ( empty( $bbp->topic_query ) ) {
			return false;
		}

		// Filter & return
		return apply_filters( 'bbp_get_forum_pagination_links', $bbp->topic_query->pagination_links );
	}

/**
 * Displays topic notices
 *
 * @since 2.0.0 bbPress (r2744)
 */
function bbp_topic_notices() {

	// Bail if not viewing a topic
	if ( ! bbp_is_single_topic() ) {
		return;
	}

	// Get the topic_status
	$topic_status = bbp_get_topic_status();

	// Get the topic status
	switch ( $topic_status ) {

		// Spam notice
		case bbp_get_spam_status_id() :
			$notice_text = esc_html__( 'This topic is marked as spam.', 'bbpress' );
			break;

		// Trashed notice
		case bbp_get_trash_status_id() :
			$notice_text = esc_html__( 'This topic is in the trash.', 'bbpress' );
			break;

		// Pending notice
		case bbp_get_pending_status_id() :
			$notice_text = esc_html__( 'This topic is pending moderation.', 'bbpress' );
			break;

		// Standard status
		default :
			$notice_text = '';
			break;
	}

	// Filter notice text and bail if empty
	$notice_text = apply_filters( 'bbp_topic_notices', $notice_text, $topic_status, bbp_get_topic_id() );
	if ( empty( $notice_text ) ) {
		return;
	}

	bbp_add_error( 'topic_notice', $notice_text, 'message' );
}

/**
 * Displays topic type select box (normal/sticky/super sticky)
 *
 * @since 2.0.0 bbPress (r2784)
 *
 * @deprecated 2.4.0 bbPress (r5059)
 *
 * @param $args This function supports these arguments:
 *  - select_id: Select id. Defaults to bbp_stick_topic
 *  - tab: Deprecated. Tabindex
 *  - topic_id: Topic id
 *  - selected: Override the selected option
 */
function bbp_topic_type_select( $args = array() ) {
	echo bbp_get_form_topic_type_dropdown( $args );
}

/**
 * Displays topic type select box (normal/sticky/super sticky)
 *
 * @since 2.4.0 bbPress (r5059)
 *
 * @param $args This function supports these arguments:
 *  - select_id: Select id. Defaults to bbp_stick_topic
 *  - tab: Deprecated. Tabindex
 *  - topic_id: Topic id
 *  - selected: Override the selected option
 */
function bbp_form_topic_type_dropdown( $args = array() ) {
	echo bbp_get_form_topic_type_dropdown( $args );
}
	/**
	 * Returns topic type select box (normal/sticky/super sticky)
	 *
	 * @since 2.4.0 bbPress (r5059)
	 *
	 * @param $args This function supports these arguments:
	 *  - select_id: Select id. Defaults to bbp_stick_topic
	 *  - tab: Deprecated. Tabindex
	 *  - topic_id: Topic id
	 *  - selected: Override the selected option
	 */
	function bbp_get_form_topic_type_dropdown( $args = array() ) {

		// Parse arguments against default values
		$r = bbp_parse_args( $args, array(
			'select_id'    => 'bbp_stick_topic',
			'select_class' => 'bbp_dropdown',
			'tab'          => false,
			'topic_id'     => 0,
			'selected'     => false
		), 'topic_type_select' );

		// No specific selected value passed
		if ( empty( $r['selected'] ) ) {

			// Post value is passed
			if ( bbp_is_topic_form_post_request() && isset( $_POST[ $r['select_id'] ] ) ) {
				$r['selected'] = sanitize_key( $_POST[ $r['select_id'] ] );

			// No Post value passed
			} else {

				// Edit topic
				if ( bbp_is_single_topic() || bbp_is_topic_edit() ) {

					// Get current topic id
					$r['topic_id'] = bbp_get_topic_id( $r['topic_id'] );

					// Topic is super sticky
					if ( bbp_is_topic_super_sticky( $r['topic_id'] ) ) {
						$r['selected'] = 'super';

					// Topic is sticky or normal
					} else {
						$r['selected'] = bbp_is_topic_sticky( $r['topic_id'], false )
							? 'stick'
							: 'unstick';
					}
				}
			}
		}

		// Start an output buffer, we'll finish it after the select loop
		ob_start(); ?>

		<select name="<?php echo esc_attr( $r['select_id'] ); ?>" id="<?php echo esc_attr( $r['select_id'] ); ?>_select" class="<?php echo esc_attr( $r['select_class'] ); ?>"<?php bbp_tab_index_attribute( $r['tab'] ); ?>>

			<?php foreach ( bbp_get_topic_types( $r['topic_id'] ) as $key => $label ) : ?>

				<option value="<?php echo esc_attr( $key ); ?>"<?php selected( $key, $r['selected'] ); ?>><?php echo esc_html( $label ); ?></option>

			<?php endforeach; ?>

		</select>

		<?php

		// Filter & return
		return apply_filters( 'bbp_get_form_topic_type_dropdown', ob_get_clean(), $r, $args );
	}

/**
 * Output value topic status dropdown
 *
 * @since 2.4.0 bbPress (r5059)
 *
 * @param $args This function supports these arguments:
 *  - select_id: Select id. Defaults to bbp_topic_status
 *  - tab: Deprecated. Tabindex
 *  - topic_id: Topic id
 *  - selected: Override the selected option
 */
function bbp_form_topic_status_dropdown( $args = array() ) {
	echo bbp_get_form_topic_status_dropdown( $args );
}
	/**
	 * Returns topic status dropdown
	 *
	 * This dropdown is only intended to be seen by users with the 'moderate'
	 * capability. Because of this, no additional capability checks are performed
	 * within this function to check available topic statuses.
	 *
	 * @since 2.4.0 bbPress (r5059)
	 *
	 * @param $args This function supports these arguments:
	 *  - select_id: Select id. Defaults to bbp_topic_status
	 *  - tab: Deprecated. Tabindex
	 *  - topic_id: Topic id
	 *  - selected: Override the selected option
	 */
	function bbp_get_form_topic_status_dropdown( $args = array() ) {

		// Parse arguments against default values
		$r = bbp_parse_args( $args, array(
			'select_id'    => 'bbp_topic_status',
			'select_class' => 'bbp_dropdown',
			'tab'          => false,
			'topic_id'     => 0,
			'selected'     => false
		), 'topic_open_close_select' );

		// No specific selected value passed
		if ( empty( $r['selected'] ) ) {

			// Post value is passed
			if ( bbp_is_topic_form_post_request() && isset( $_POST[ $r['select_id'] ] ) ) {
				$r['selected'] = sanitize_key( $_POST[ $r['select_id'] ] );

			// No Post value was passed
			} else {

				// Edit topic
				if ( bbp_is_topic_edit() ) {
					$r['topic_id'] = bbp_get_topic_id( $r['topic_id'] );
					$r['selected'] = bbp_get_topic_status( $r['topic_id'] );

				// New topic
				} else {
					$r['selected'] = bbp_get_public_status_id();
				}
			}
		}

		// Start an output buffer, we'll finish it after the select loop
		ob_start(); ?>

		<select name="<?php echo esc_attr( $r['select_id'] ) ?>" id="<?php echo esc_attr( $r['select_id'] ); ?>_select" class="<?php echo esc_attr( $r['select_class'] ); ?>"<?php bbp_tab_index_attribute( $r['tab'] ); ?>>

			<?php foreach ( bbp_get_topic_statuses( $r['topic_id'] ) as $key => $label ) : ?>

				<option value="<?php echo esc_attr( $key ); ?>"<?php selected( $key, $r['selected'] ); ?>><?php echo esc_html( $label ); ?></option>

			<?php endforeach; ?>

		</select>

		<?php

		// Filter & return
		return apply_filters( 'bbp_get_form_topic_status_dropdown', ob_get_clean(), $r, $args );
	}

/** Single Topic **************************************************************/

/**
 * Output a fancy description of the current topic, including total topics,
 * total replies, and last activity.
 *
 * @since 2.0.0 bbPress (r2860)
 *
 * @param array $args See {@link bbp_get_single_topic_description()}
 */
function bbp_single_topic_description( $args = array() ) {
	echo bbp_get_single_topic_description( $args );
}
	/**
	 * Return a fancy description of the current topic, including total topics,
	 * total replies, and last activity.
	 *
	 * @since 2.0.0 bbPress (r2860)
	 *
	 * @param array $args This function supports these arguments:
	 *  - topic_id: Topic id
	 *  - before: Before the text
	 *  - after: After the text
	 *  - size: Size of the avatar
	 * @return string Filtered topic description
	 */
	function bbp_get_single_topic_description( $args = array() ) {

		// Parse arguments against default values
		$r = bbp_parse_args( $args, array(
			'topic_id'  => 0,
			'before'    => '<div class="bbp-template-notice info"><ul><li class="bbp-topic-description">',
			'after'     => '</li></ul></div>',
			'size'      => 14
		), 'get_single_topic_description' );

		// Validate topic_id
		$topic_id = bbp_get_topic_id( $r['topic_id'] );

		// Unhook the 'view all' query var adder
		remove_filter( 'bbp_get_topic_permalink', 'bbp_add_view_all' );

		// Build the topic description
		$vc_int      = bbp_get_topic_voice_count   ( $topic_id, true  );
		$voice_count = bbp_get_topic_voice_count   ( $topic_id, false );
		$reply_count = bbp_get_topic_replies_link  ( $topic_id        );
		$time_since  = bbp_get_topic_freshness_link( $topic_id        );

		// Singular/Plural
		$voice_count = sprintf( _n( '%s voice', '%s voices', $vc_int, 'bbpress' ), $voice_count );

		// Topic has activity (could be from reply or topic author)
		$last_active = bbp_get_topic_last_active_id( $topic_id );
		if ( ! empty( $vc_int ) && ! empty( $last_active ) ) {
			$last_updated_by = bbp_get_author_link( array( 'post_id' => $last_active, 'size' => $r['size'] ) );
			$retstr          = sprintf( esc_html__( 'This topic has %1$s, %2$s, and was last updated %3$s by %4$s.', 'bbpress' ), $reply_count, $voice_count, $time_since, $last_updated_by );

		// Topic has no replies
		} elseif ( ! empty( $vc_int ) && ! empty( $reply_count ) ) {
			$retstr = sprintf( esc_html__( 'This topic has %1$s and %2$s.', 'bbpress' ), $voice_count, $reply_count );

		// Topic has no replies and no voices
		} elseif ( empty( $vc_int ) && empty( $reply_count ) ) {
			$retstr = esc_html__( 'This topic has no replies.', 'bbpress' );

		// Topic is pending
		} elseif ( bbp_get_topic_status( $topic_id ) === bbp_get_pending_status_id() ) {
			$retstr = esc_html__( 'This topic is pending moderation.', 'bbpress' );

		// Fallback
		} else {
			$retstr = esc_html__( 'This topic is empty.', 'bbpress' );
		}

		// Add the 'view all' filter back
		add_filter( 'bbp_get_topic_permalink', 'bbp_add_view_all' );

		// Combine the elements together
		$retstr = $r['before'] . $retstr . $r['after'];

		// Filter & return
		return apply_filters( 'bbp_get_single_topic_description', $retstr, $r, $args );
	}

/** Topic Tags ****************************************************************/

/**
 * Output the unique id of the topic tag taxonomy
 *
 * @since 2.0.0 bbPress (r3348)
 */
function bbp_topic_tag_tax_id() {
	echo bbp_get_topic_tag_tax_id();
}
	/**
	 * Return the unique id of the topic tag taxonomy
	 *
	 * @since 2.0.0 bbPress (r3348)
	 *
	 * @return string The unique topic tag taxonomy
	 */
	function bbp_get_topic_tag_tax_id() {

		// Filter & return
		return apply_filters( 'bbp_get_topic_tag_tax_id', bbpress()->topic_tag_tax_id );
	}

/**
 * Return array of labels used by the topic-tag taxonomy
 *
 * @since 2.5.0 bbPress (r5129)
 *
 * @return array
 */
function bbp_get_topic_tag_tax_labels() {

	// Filter & return
	return (array) apply_filters( 'bbp_get_topic_tag_tax_labels', array(
		'name'                       => esc_attr__( 'Topic Tags',                      'bbpress' ),
		'menu_name'                  => esc_attr__( 'Topic Tags',                      'bbpress' ),
		'singular_name'              => esc_attr__( 'Topic Tag',                       'bbpress' ),
		'search_items'               => esc_attr__( 'Search Tags',                     'bbpress' ),
		'popular_items'              => esc_attr__( 'Popular Tags',                    'bbpress' ),
		'all_items'                  => esc_attr__( 'All Tags',                        'bbpress' ),
		'parent_item'                => esc_attr__( 'Parent Tag',                      'bbpress' ),
		'parent_item_colon'          => esc_attr__( 'Parent Tag:',                     'bbpress' ),
		'edit_item'                  => esc_attr__( 'Edit Tag',                        'bbpress' ),
		'view_item'                  => esc_attr__( 'View Topic Tag',                  'bbpress' ),
		'update_item'                => esc_attr__( 'Update Tag',                      'bbpress' ),
		'add_new_item'               => esc_attr__( 'Add New Tag',                     'bbpress' ),
		'new_item_name'              => esc_attr__( 'New Tag Name',                    'bbpress' ),
		'separate_items_with_commas' => esc_attr__( 'Separate topic tags with commas', 'bbpress' ),
		'add_or_remove_items'        => esc_attr__( 'Add or remove tags',              'bbpress' ),
		'choose_from_most_used'      => esc_attr__( 'Choose from the most used tags',  'bbpress' ),
		'not_found'                  => esc_attr__( 'No topic tags found.',            'bbpress' ),
		'no_terms'                   => esc_attr__( 'No topic tags',                   'bbpress' ),
		'items_list_navigation'      => esc_attr__( 'Topic tags list navigation',      'bbpress' ),
		'items_list'                 => esc_attr__( 'Topic tags list',                 'bbpress' ),
		'most_used'                  => esc_attr__( 'Most used topic tags',            'bbpress' ),
		'back_to_items'              => esc_attr__( '&larr; Back to Tags',             'bbpress' )
	) );
}

/**
 * Return an array of topic-tag taxonomy rewrite settings
 *
 * @since 2.5.0 bbPress (r5129)
 *
 * @return array
 */
function bbp_get_topic_tag_tax_rewrite() {

	// Filter & return
	return (array) apply_filters( 'bbp_get_topic_tag_tax_rewrite', array(
		'slug'       => bbp_get_topic_tag_tax_slug(),
		'with_front' => false
	) );
}

/**
 * Output the id of the current tag
 *
 * @since 2.0.0 bbPress (r3109)
 *
 */
function bbp_topic_tag_id( $tag = '' ) {
	echo bbp_get_topic_tag_id( $tag );
}
	/**
	 * Return the id of the current tag
	 *
	 * @since 2.0.0 bbPress (r3109)
	 *
	 * @return string Term Name
	 */
	function bbp_get_topic_tag_id( $tag = '' ) {

		// Get the term
		if ( ! empty( $tag ) ) {
			$term = get_term_by( 'slug', $tag, bbp_get_topic_tag_tax_id() );
		} else {
			$tag  = get_query_var( 'term' );
			$term = get_queried_object();
		}

		// Get the term ID
		$retval = ! empty( $term->term_id )
			? $term->term_id
			: 0;

		// Filter & return
		return (int) apply_filters( 'bbp_get_topic_tag_id', (int) $retval, $tag, $term );
	}

/**
 * Output the name of the current tag
 *
 * @since 2.0.0 bbPress (r3109)
 */
function bbp_topic_tag_name( $tag = '' ) {
	echo bbp_get_topic_tag_name( $tag );
}
	/**
	 * Return the name of the current tag
	 *
	 * @since 2.0.0 bbPress (r3109)
	 *
	 * @return string Term Name
	 */
	function bbp_get_topic_tag_name( $tag = '' ) {

		// Get the term
		if ( ! empty( $tag ) ) {
			$term = get_term_by( 'slug', $tag, bbp_get_topic_tag_tax_id() );
		} else {
			$tag  = get_query_var( 'term' );
			$term = get_queried_object();
		}

		// Get the term name
		$retval = ! empty( $term->name )
			? $term->name
			: '';

		// Filter & return
		return apply_filters( 'bbp_get_topic_tag_name', $retval, $tag, $term );
	}

/**
 * Output the slug of the current tag
 *
 * @since 2.0.0 bbPress (r3109)
 */
function bbp_topic_tag_slug( $tag = '' ) {
	echo bbp_get_topic_tag_slug( $tag );
}
	/**
	 * Return the slug of the current tag
	 *
	 * @since 2.0.0 bbPress (r3109)
	 *
	 * @return string Term Name
	 */
	function bbp_get_topic_tag_slug( $tag = '' ) {

		// Get the term
		if ( ! empty( $tag ) ) {
			$term = get_term_by( 'slug', $tag, bbp_get_topic_tag_tax_id() );
		} else {
			$tag  = get_query_var( 'term' );
			$term = get_queried_object();
		}

		// Get the term slug
		$retval = ! empty( $term->slug )
			? $term->slug
			: '';

		// Filter & return
		return apply_filters( 'bbp_get_topic_tag_slug', $retval, $tag, $term );
	}

/**
 * Output the link of the current tag
 *
 * @since 2.0.0 bbPress (r3348)
 */
function bbp_topic_tag_link( $tag = '' ) {
	echo esc_url( bbp_get_topic_tag_link( $tag ) );
}
	/**
	 * Return the link of the current tag
	 *
	 * @since 2.0.0 bbPress (r3348)
	 *
	 * @return string Term Name
	 */
	function bbp_get_topic_tag_link( $tag = '' ) {

		// Get the term
		if ( ! empty( $tag ) ) {
			$term = get_term_by( 'slug', $tag, bbp_get_topic_tag_tax_id() );
		} else {
			$tag  = get_query_var( 'term' );
			$term = get_queried_object();
		}

		// Get the term link
		$retval = ! empty( $term->term_id )
			? get_term_link( $term, bbp_get_topic_tag_tax_id() )
			: '';

		// Filter & return
		return apply_filters( 'bbp_get_topic_tag_link', $retval, $tag, $term );
	}

/**
 * Output the link of the current tag
 *
 * @since 2.0.0 bbPress (r3348)
 */
function bbp_topic_tag_edit_link( $tag = '' ) {
	echo esc_url( bbp_get_topic_tag_edit_link( $tag ) );
}
	/**
	 * Return the link of the current tag
	 *
	 * @since 2.0.0 bbPress (r3348)
	 *
	 * @return string Term Name
	 */
	function bbp_get_topic_tag_edit_link( $tag = '' ) {

		// Get the term
		if ( ! empty( $tag ) ) {
			$term = get_term_by( 'slug', $tag, bbp_get_topic_tag_tax_id() );
		} else {
			$tag  = get_query_var( 'term' );
			$term = get_queried_object();
		}

		// Get the term's edit link
		if ( ! empty( $term->term_id ) ) {

			// Pretty or ugly URL
			$retval = bbp_use_pretty_urls()
				? user_trailingslashit( trailingslashit( bbp_get_topic_tag_link() ) . bbp_get_edit_slug() )
				: add_query_arg( array( bbp_get_edit_rewrite_id() => '1' ), bbp_get_topic_tag_link() );

		// No link
		} else {
			$retval = '';
		}

		// Filter & return
		return apply_filters( 'bbp_get_topic_tag_edit_link', $retval, $tag, $term );
	}

/**
 * Output the description of the current tag
 *
 * @since 2.0.0 bbPress (r3109)
 */
function bbp_topic_tag_description( $args = array() ) {
	echo bbp_get_topic_tag_description( $args );
}
	/**
	 * Return the description of the current tag
	 *
	 * @since 2.0.0 bbPress (r3109)
	 *
	 * @param array $args before|after|tag
	 *
	 * @return string Term Name
	 */
	function bbp_get_topic_tag_description( $args = array() ) {

		// Parse arguments against default values
		$r = bbp_parse_args( $args, array(
			'before' => '<div class="bbp-topic-tag-description"><p>',
			'after'  => '</p></div>',
			'tag'    => ''
		), 'get_topic_tag_description' );

		// Get the term
		if ( ! empty( $r['tag'] ) ) {
			$term = get_term_by( 'slug', $r['tag'], bbp_get_topic_tag_tax_id() );
		} else {
			$tag  = $r['tag'] = get_query_var( 'term' );
			$term = get_queried_object();
		}

		// Add before & after if description exists
		$retval = ! empty( $term->description )
			? $r['before'] . $term->description . $r['after']
			: '';

		// Filter & return
		return apply_filters( 'bbp_get_topic_tag_description', $retval, $r, $args, $tag, $term );
	}

/** Forms *********************************************************************/

/**
 * Output the value of topic title field
 *
 * @since 2.0.0 bbPress (r2976)
 */
function bbp_form_topic_title() {
	echo bbp_get_form_topic_title();
}
	/**
	 * Return the value of topic title field
	 *
	 * @since 2.0.0 bbPress (r2976)
	 *
	 * @return string Value of topic title field
	 */
	function bbp_get_form_topic_title() {

		// Get _POST data
		if ( bbp_is_topic_form_post_request() && isset( $_POST['bbp_topic_title'] ) ) {
			$topic_title = wp_unslash( $_POST['bbp_topic_title'] );

		// Get edit data
		} elseif ( bbp_is_topic_edit() ) {
			$topic_title = bbp_get_global_post_field( 'post_title', 'raw' );

		// No data
		} else {
			$topic_title = '';
		}

		// Filter & return
		return apply_filters( 'bbp_get_form_topic_title', $topic_title );
	}

/**
 * Output the value of topic content field
 *
 * @since 2.0.0 bbPress (r2976)
 */
function bbp_form_topic_content() {
	echo bbp_get_form_topic_content();
}
	/**
	 * Return the value of topic content field
	 *
	 * @since 2.0.0 bbPress (r2976)
	 *
	 * @return string Value of topic content field
	 */
	function bbp_get_form_topic_content() {

		// Get _POST data
		if ( bbp_is_topic_form_post_request() && isset( $_POST['bbp_topic_content'] ) ) {
			$topic_content = wp_unslash( $_POST['bbp_topic_content'] );

		// Get edit data
		} elseif ( bbp_is_topic_edit() ) {
			$topic_content = bbp_get_global_post_field( 'post_content', 'raw' );

		// No data
		} else {
			$topic_content = '';
		}

		// Filter & return
		return apply_filters( 'bbp_get_form_topic_content', $topic_content );
	}

/**
 * Allow topic rows to have administrative actions
 *
 * @since 2.1.0 bbPress (r3653)
 *
 * @todo Links and filter
 */
function bbp_topic_row_actions() {
	do_action( 'bbp_topic_row_actions' );
}

/**
 * Output value of topic tags field
 *
 * @since 2.0.0 bbPress (r2976)
 */
function bbp_form_topic_tags() {
	echo bbp_get_form_topic_tags();
}
	/**
	 * Return value of topic tags field
	 *
	 * @since 2.0.0 bbPress (r2976)
	 *
	 * @return string Value of topic tags field
	 */
	function bbp_get_form_topic_tags() {

		// Default return value
		$topic_tags = '';

		// Get _POST data
		if ( ( bbp_is_topic_form_post_request() || bbp_is_reply_form_post_request() ) && isset( $_POST['bbp_topic_tags'] ) ) {
			$topic_tags = wp_unslash( $_POST['bbp_topic_tags'] );

		// Get edit data
		} elseif ( bbp_is_single_topic() || bbp_is_single_reply() || bbp_is_topic_edit() || bbp_is_reply_edit() ) {

			// Determine the topic id based on the post type
			switch ( get_post_type() ) {

				// Post is a topic
				case bbp_get_topic_post_type() :
					$topic_id = bbp_get_topic_id( get_the_ID() );
					break;

				// Post is a reply
				case bbp_get_reply_post_type() :
					$topic_id = bbp_get_reply_topic_id( get_the_ID() );
					break;
			}

			// Topic exists
			if ( ! empty( $topic_id ) ) {

				// Topic is spammed so display pre-spam terms
				if ( bbp_is_topic_spam( $topic_id ) ) {

					// Get pre-spam terms
					$spam_terms = get_post_meta( $topic_id, '_bbp_spam_topic_tags', true );
					$topic_tags = ( ! empty( $spam_terms ) ) ? implode( ', ', $spam_terms ) : '';

				// Topic is not spam so get real terms
				} else {
					$topic_tags = bbp_get_topic_tag_names( $topic_id );
				}
			}
		}

		// Filter & return
		return apply_filters( 'bbp_get_form_topic_tags', $topic_tags );
	}

/**
 * Output value of topic forum
 *
 * @since 2.0.0 bbPress (r2976)
 */
function bbp_form_topic_forum() {
	echo bbp_get_form_topic_forum();
}
	/**
	 * Return value of topic forum
	 *
	 * @since 2.0.0 bbPress (r2976)
	 *
	 * @return string Value of topic content field
	 */
	function bbp_get_form_topic_forum() {

		// Get _POST data
		if ( bbp_is_topic_form_post_request() && isset( $_POST['bbp_forum_id'] ) ) {
			$topic_forum = (int) $_POST['bbp_forum_id'];

		// Get edit data
		} elseif ( bbp_is_topic_edit() ) {
			$topic_forum = bbp_get_topic_forum_id();

		// No data
		} else {
			$topic_forum = 0;
		}

		// Filter & return
		return apply_filters( 'bbp_get_form_topic_forum', $topic_forum );
	}

/**
 * Output checked value of topic subscription
 *
 * @since 2.0.0 bbPress (r2976)
 */
function bbp_form_topic_subscribed() {
	echo bbp_get_form_topic_subscribed();
}
	/**
	 * Return checked value of topic subscription
	 *
	 * @since 2.0.0 bbPress (r2976)
	 *
	 * @return string Checked value of topic subscription
	 */
	function bbp_get_form_topic_subscribed() {

		// Default value
		$topic_subscribed = false;

		// Get _POST data
		if ( bbp_is_topic_form_post_request() && isset( $_POST['bbp_topic_subscription'] ) ) {
			$topic_subscribed = (bool) $_POST['bbp_topic_subscription'];

		// Get edit data
		} elseif ( bbp_is_topic_edit() || bbp_is_reply_edit() ) {
			$post_author      = (int) bbp_get_global_post_field( 'post_author', 'raw' );
			$topic_subscribed = bbp_is_user_subscribed( $post_author, bbp_get_topic_id() );

		// Get current status
		} elseif ( bbp_is_single_topic() ) {
			$topic_subscribed = bbp_is_user_subscribed( bbp_get_current_user_id(), bbp_get_topic_id() );
		}

		// Get checked output
		$checked = checked( $topic_subscribed, true, false );

		// Filter & return
		return apply_filters( 'bbp_get_form_topic_subscribed', $checked, $topic_subscribed );
	}

/**
 * Output checked value of topic log edit field
 *
 * @since 2.0.0 bbPress (r2976)
 */
function bbp_form_topic_log_edit() {
	echo bbp_get_form_topic_log_edit();
}
	/**
	 * Return checked value of topic log edit field
	 *
	 * @since 2.0.0 bbPress (r2976)
	 *
	 * @return string Topic log edit checked value
	 */
	function bbp_get_form_topic_log_edit() {

		// Get _POST data
		if ( bbp_is_topic_form_post_request() && isset( $_POST['bbp_log_topic_edit'] ) ) {
			$topic_revision = (bool) $_POST['bbp_log_topic_edit'];

		// No data
		} else {
			$topic_revision = true;
		}

		// Get checked output
		$checked = checked( $topic_revision, true, false );

		// Filter & return
		return apply_filters( 'bbp_get_form_topic_log_edit', $checked, $topic_revision );
	}

/**
 * Output the value of the topic edit reason
 *
 * @since 2.0.0 bbPress (r2976)
 */
function bbp_form_topic_edit_reason() {
	echo bbp_get_form_topic_edit_reason();
}
	/**
	 * Return the value of the topic edit reason
	 *
	 * @since 2.0.0 bbPress (r2976)
	 *
	 * @return string Topic edit reason value
	 */
	function bbp_get_form_topic_edit_reason() {

		// Get _POST data
		if ( bbp_is_topic_form_post_request() && isset( $_POST['bbp_topic_edit_reason'] ) ) {
			$topic_edit_reason = wp_unslash( $_POST['bbp_topic_edit_reason'] );

		// No data
		} else {
			$topic_edit_reason = '';
		}

		// Filter & return
		return apply_filters( 'bbp_get_form_topic_edit_reason', $topic_edit_reason );
	}

/**
 * Verify if a POST request came from a failed topic attempt.
 *
 * Used to avoid cross-site request forgeries when checking posted topic form
 * content.
 *
 * @see bbp_topic_form_fields()
 *
 * @since 2.6.0 bbPress (r5558)
 *
 * @return boolean True if is a post request with valid nonce
 */
function bbp_is_topic_form_post_request() {

	// Bail if not a post request
	if ( ! bbp_is_post_request() ) {
		return false;
	}

	// Creating a new topic
	if ( bbp_verify_nonce_request( 'bbp-new-topic' ) ) {
		return true;
	}

	// Editing an existing topic
	if ( bbp_verify_nonce_request( 'bbp-edit-topic_' . bbp_get_topic_id() ) ) {
		return true;
	}

	return false;
}

/** Warning *******************************************************************/

/**
 * Should the topic-lock alert appear?
 *
 * @since 2.6.0 bbPress (r6342)
 *
 * @return bool
 */
function bbp_show_topic_lock_alert() {

	// Default to not showing the alert
	$retval = false;

	// Get the current topic ID
	$topic_id = bbp_get_topic_id();

	// Only show on single topic pages
	if ( bbp_is_topic_edit() || bbp_is_single_topic() ) {

		// Only show to moderators
		if ( current_user_can( 'moderate', $topic_id ) ) {

			// Locked?
			$user_id = bbp_check_post_lock( $topic_id );

			// Only show if not locked by the current user
			if ( ! empty( $user_id ) && ( bbp_get_current_user_id() !== $user_id ) ) {
				$retval = true;
			}
		}
	}

	// Filter & return
	return (bool) apply_filters( 'bbp_show_topic_lock_alert', $retval, $topic_id );
}

/**
 * Output the topic lock description
 *
 * @since 2.6.0 bbPress (r6343)
 *
 * @param int $topic_id Optional. Topic id
 */
function bbp_topic_lock_description( $topic_id = 0 ) {
	echo bbp_get_topic_lock_description( $topic_id );
}
	/**
	 * Return the topic lock description
	 *
	 * @since 2.6.0 bbPress (r6343)
	 *
	 * @param int $topic_id Optional. Topic id
	 */
	function bbp_get_topic_lock_description( $topic_id = 0 ) {

		// Check if topic is edit locked
		$topic_id = bbp_get_topic_id( $topic_id );
		$user_id  = bbp_check_post_lock( $topic_id );
		$person   = empty( $user_id )
			? esc_html__( 'Nobody', 'bbpress' )
			: bbp_get_user_profile_link( $user_id );

		// Get the text
		$text = sprintf( esc_html__( '%1$s is currently editing this topic.', 'bbpress' ), $person );

		// Filter & return
		return apply_filters( 'bbp_get_topic_lock_description', $text, $user_id, $topic_id );
	}
