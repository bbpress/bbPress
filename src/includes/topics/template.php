<?php

/**
 * bbPress Topic Template Tags.
 *
 * @package bbPress
 * @subpackage TemplateTags
 */

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

/** Post Type *****************************************************************/

/**
 * Output the unique id of the custom post type for topics.
 *
 * @since 2.0.0 bbPress (r2858)
 *
 * @return void
 */
function bbp_topic_post_type() {
	echo bbp_get_topic_post_type();
}

/**
 * Return the unique id of the custom post type for topics.
 *
 * @since 2.0.0 bbPress (r2858)
 *
 * @return string The unique topic post type id.
 */
function bbp_get_topic_post_type() {

	/**
	 * Filters the topic post type ID.
	 *
	 * @since 2.0.0 bbPress (r2858)
	 *
	 * @param string $topic_post_type The unique topic post type id.
	 */
	return apply_filters( 'bbp_get_topic_post_type', bbpress()->topic_post_type );
}

/**
 * Return array of labels used by the topic post type.
 *
 * @since 2.5.0 bbPress (r5130)
 *
 * @return array<string, string>
 */
function bbp_get_topic_post_type_labels() {

	/**
	 * Filters the array of labels used by the topic post type.
	 *
	 * @since 2.5.0 bbPress (r5130)
	 *
	 * @param array<string, string> $value The array of labels used by the topic post type.
	 */
	return (array) apply_filters(
		'bbp_get_topic_post_type_labels',
		array(
			'name'                     => esc_attr__( 'Topics',                     'bbpress' ),
			'menu_name'                => esc_attr__( 'Topics',                     'bbpress' ),
			'singular_name'            => esc_attr__( 'Topic',                      'bbpress' ),
			'all_items'                => esc_attr__( 'All Topics',                 'bbpress' ),
			'add_new'                  => esc_attr__( 'Add New',                    'bbpress' ),
			'add_new_item'             => esc_attr__( 'Add Topic',                  'bbpress' ),
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
		)
	);
}

/**
 * Return array of topic post type rewrite settings.
 *
 * @since 2.5.0 bbPress (r5130)
 *
 * @return array<string, mixed>
 */
function bbp_get_topic_post_type_rewrite() {

	/**
	 * Filters the array of topic post type rewrite settings.
	 *
	 * @since 2.5.0 bbPress (r5130)
	 *
	 * @param array<string, mixed> $value The array of topic post type rewrite settings.
	 */
	return (array) apply_filters(
		'bbp_get_topic_post_type_rewrite',
		array(
			'slug'       => bbp_get_topic_slug(),
			'with_front' => false
		)
	);
}

/**
 * Return array of features the topic post type supports.
 *
 * @since 2.5.0 bbPress (r5130)
 *
 * @return array<array-key, string>
 */
function bbp_get_topic_post_type_supports() {

	/**
	 * Filters the array of features the topic post type supports.
	 *
	 * @since 2.5.0 bbPress (r5130)
	 *
	 * @param array<array-key, string> $value The array of features the topic post type supports.
	 */
	return (array) apply_filters(
		'bbp_get_topic_post_type_supports',
		array(
			'title',
			'editor',
			'revisions'
		)
	);
}

/**
 * The plugin version of bbPress comes with two topic display options:
 * - Traditional: Topics are included in the reply loop (default)
 * - New Style: Topics appear as "lead" posts, ahead of replies
 *
 * @since 2.0.0 bbPress (r2954)
 *
 * @param bool $show_lead Optional. Whether to show the topic before its replies.
 * @return bool Yes if the topic appears as a lead, otherwise false.
 */
function bbp_show_lead_topic( $show_lead = false ) {

	// Never separate the lead topic in feeds
	if ( is_feed() ) {
		return false;
	}

	/**
	 * Filters whether to show the topic above its replies.
	 *
	 * @since 2.0.0 bbPress (r2954)
	 *
	 * @param bool $show_lead Yes if the topic appears as a lead, otherwise false.
	 */
	return (bool) apply_filters( 'bbp_show_lead_topic', (bool) $show_lead );
}

/** Topic Loop ****************************************************************/

/**
 * The main topic loop. WordPress makes this easy for us.
 *
 * @since 2.0.0 bbPress (r2485)
 *
 * @param array<string, mixed> $args All the arguments supported by {@link WP_Query}.
 * @return bool Whether topics are available.
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
		'orderby'        => 'meta_value',              // 'meta_value', 'author', 'date', 'title', 'modified', 'parent', 'rand',
		'order'          => 'DESC',                    // 'ASC' or 'DESC'
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
	$view_all_args   = wp_parse_args( $args );
	$view_all_parent = isset( $view_all_args['post_parent'] ) ? $view_all_args['post_parent'] : $default_post_parent;
	$view_all_parent = is_numeric( $view_all_parent ) && ( (int) $view_all_parent > 0 ) ? (int) $view_all_parent : 0;
	$view_all        = bbp_get_view_all_for_object( 'edit_others_topics', $view_all_parent );

	// What are the default allowed statuses (based on user caps)
	if ( $view_all ) {

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
	if ( $view_all && ! current_user_can( 'edit_others_topics' ) && $view_all_parent !== (int) $r['post_parent'] ) {
		unset( $r['post_status'] );
		$r['perm'] = 'readable';
		$view_all  = false;
	}

	// Get bbPress
	$bbp = bbpress();

	// Call the query
	$bbp->topic_query = bbp_get_topic_loop_query( $r );

	// Set post_parent back to 0 if originally set to 'any'
	if ( 'any' === $r['post_parent'] ) {
		$r['post_parent'] = 0;
	}

	// Limited the number of pages shown
	if ( ! empty( $r['max_num_pages'] ) ) {
		$bbp->topic_query->max_num_pages = (int) $r['max_num_pages'];
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
		if ( ! empty( $r['show_stickies'] ) ) {
			$bbp->topic_query->query_vars['bbp_topic_page'] = bbp_get_topic_page_counts(
				$bbp->topic_query->found_posts,
				$bbp->topic_query->query_vars['bbp_sticky_count'],
				$bbp->topic_query->post_count,
				$bbp->topic_query->posts_per_page,
				$bbp->topic_query->paged
			);
			$total_pages = $bbp->topic_query->query_vars['bbp_topic_page']['pages'];
		} else {
			$total_pages = ( $bbp->topic_query->posts_per_page === $bbp->topic_query->found_posts )
				? 1
				: ceil( $bbp->topic_query->found_posts / $bbp->topic_query->posts_per_page );
		}

		// Maybe add view-all args
		$add_args = $view_all
			? array( 'view' => 'all' )
			: false;

		// Pagination settings with filter

		/**
		 * Filters the topic pagination arguments.
		 *
		 * @since 2.0.0 bbPress (r2519)
		 *
		 * @param array $args The topic pagination arguments.
		 */
		$bbp_topic_pagination = apply_filters(
			'bbp_topic_pagination',
			array(
				'base'      => bbp_get_topics_pagination_base( $r['post_parent'] ),
				'format'    => '',
				'total'     => $total_pages,
				'current'   => $bbp->topic_query->paged,
				'prev_text' => is_rtl() ? '&rarr;' : '&larr;',
				'next_text' => is_rtl() ? '&larr;' : '&rarr;',
				'mid_size'  => 1,
				'add_args'  => $add_args,
			)
		);

		// Add pagination to query object
		$bbp->topic_query->pagination_links = bbp_paginate_links( $bbp_topic_pagination );
	}

	/**
	 * Filters whether topics are available.
	 *
	 * @since 2.0.0 bbPress (r2485)
	 *
	 * @param bool $value Whether topics are available.
	 * @param WP_Query $topic_query Query object.
	 */
	return apply_filters( 'bbp_has_topics', $bbp->topic_query->have_posts(), $bbp->topic_query );
}

/**
 * Query topics for a topic loop.
 *
 * @since 2.7.0 bbPress (r8610)
 *
 * @param array<string, mixed> $args Parsed topic query arguments.
 * @return WP_Query Topic loop query.
 */
function bbp_get_topic_loop_query( $args ) {
	if ( empty( $args['show_stickies'] ) ) {
		$query = new WP_Query( $args );
	} else {
		$query = bbp_get_sticky_topic_loop_query( $args );
	}

	// Maybe prime last active posts
	if ( ! empty( $args['update_post_family_cache'] ) && is_array( $query->posts ) ) {
		bbp_update_post_family_caches( $query->posts );
	}

	return $query;
}

/**
 * Query regular topics and add visible stickies for a topic loop.
 *
 * @since 2.7.0 bbPress (r8610)
 *
 * @param array<string, mixed> $args Parsed topic query arguments.
 * @return WP_Query Topic loop query.
 */
function bbp_get_sticky_topic_loop_query( $args ) {
	$forum_stickies = is_numeric( $args['post_parent'] ) && ( (int) $args['post_parent'] > 0 )
		? bbp_get_stickies( (int) $args['post_parent'] )
		: array();
	$sticky_ids = array_merge( bbp_get_super_stickies(), $forum_stickies );
	$query_args = $args;

	// WordPress ignores post__not_in when post__in is set.
	if ( ! empty( $args['post__in'] ) ) {
		$post_in = array_diff( bbp_parse_id_list( $args['post__in'] ), $sticky_ids );
		$query_args['post__in'] = ! empty( $post_in ) ? $post_in : array( 0 );
	} else {
		$query_args['post__not_in'] = array_unique( array_merge( (array) ( $args['post__not_in'] ?? array() ), $sticky_ids ) );
	}

	$query              = new WP_Query( $query_args );
	$regular_page_count = $query->post_count;

	// Sticky queries use 0 for the archive's 'any' parent.
	if ( 'any' === $args['post_parent'] ) {
		$args['post_parent'] = 0;
	}

	// Count visible stickies on every page, but show them only on the first.
	bbp_add_sticky_topics( $query, $args );
	$sticky_count = max( 0, $query->post_count - $regular_page_count );
	$query->query_vars['bbp_sticky_count'] = $sticky_count;

	if ( ( $args['paged'] > 1 ) && ( $sticky_count > 0 ) && is_array( $query->posts ) ) {
		$query->posts      = array_slice( $query->posts, $sticky_count );
		$query->post_count = $regular_page_count;
	}

	return $query;
}

/**
 * Calculate the page count and displayed range for a topic loop with stickies.
 *
 * Sticky topics are extra rows on page 1. Regular topics fill each page.
 *
 * @since 2.7.0 bbPress (r8610)
 *
 * @param int $total_count  Total visible topics, including stickies.
 * @param int $sticky_count Visible stickies promoted to page 1.
 * @param int $page_count   Topics displayed on this page.
 * @param int $per_page     Regular topics per page.
 * @param int $paged        Current page number.
 * @return array<string, int> Total pages and displayed topic range.
 */
function bbp_get_topic_page_counts( $total_count, $sticky_count, $page_count, $per_page, $paged ) {
	$regular_count = max( 0, $total_count - $sticky_count );
	$pages         = ( $per_page > 0 ) ? (int) ceil( $regular_count / $per_page ) : 0;
	$pages         = ( $sticky_count > 0 ) ? max( 1, $pages ) : $pages;
	$from          = ( ( $paged - 1 ) * $per_page ) + 1;
	$from          = ( $paged > 1 ) ? $from + $sticky_count : $from;
	$to            = min( $total_count, $from + $page_count - 1 );

	return array(
		'pages' => $pages,
		'from'  => $from,
		'to'    => $to,
		'total' => $total_count,
	);
}

/**
 * Whether there are more topics available in the loop.
 *
 * @since 2.0.0 bbPress (r2485)
 *
 * @return bool Whether another topic is available.
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
 * Loads up the current topic in the loop.
 *
 * @since 2.0.0 bbPress (r2485)
 *
 * @return null Sets up the current topic and returns null.
 */
function bbp_the_topic() {
	return bbpress()->topic_query->the_post();
}

/**
 * Add sticky topics to a topics query object.
 *
 * @since 2.6.0 bbPress (r6402)
 *
 * @param WP_Query             $query Topic query to update.
 * @param array<string, mixed> $args  Sticky topic query arguments.
 * @return mixed Intercepted value, or null after updating the query.
 */
function bbp_add_sticky_topics( &$query, $args = array() ) {

	// Bail if intercepted
	$intercept = bbp_maybe_intercept( __FUNCTION__, func_get_args() );
	if ( bbp_is_intercepted( $intercept ) ) {
		return $intercept;
	}

	// Parse arguments against what gets used locally
	$r = bbp_parse_args(
		$args,
		array(
			'post_parent'         => 0,
			'post_parent__not_in' => array(),
			'post__not_in'        => array(),
			'post_status'         => '',
			'perm'                => ''
		),
		'add_sticky_topics'
	);

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

	// A posts filter may leave no rows for the sticky pass.
	if ( ! is_array( $query->posts ) ) {
		return;
	}

	// Loop through posts
	foreach ( $query->posts as $key => $post ) {
		if ( ! ( $post instanceof WP_Post ) ) {
			continue;
		}

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
		$post_parent_id    = $r['post_parent'];

		// Maybe remove the current forum from excluded forum IDs
		if ( ! empty( $post_parent_id ) ) {
			unset( $exclude_forum_ids[ $post_parent_id ] );
		}

		// Maybe exclude specific forums
		if ( ! empty( $exclude_forum_ids ) ) {
			$sticky_query['post_parent__not_in'] = $exclude_forum_ids;
		}

		// Allowed statuses, or lean on the 'perm' argument (probably 'readable')
		$global_view_all = bbp_get_view_all( 'edit_others_topics' );
		$sticky_query['post_status'] = $global_view_all
			? $r['post_status']
			: $r['perm'];

		// Get unqueried stickies
		$_posts = get_posts( $sticky_query );

		// Query hidden stickies only in the forum this user can moderate
		$forum_id = is_numeric( $r['post_parent'] ) && ( (int) $r['post_parent'] > 0 ) ? (int) $r['post_parent'] : 0;
		if ( ! $global_view_all && ! empty( $forum_id ) && ! empty( $r['post_status'] ) && bbp_get_view_all_for_object( 'edit_others_topics', $forum_id ) ) {
			$local_stickies = array_diff( $stickies, wp_list_pluck( $_posts, 'ID' ) );
			if ( ! empty( $local_stickies ) ) {
				$sticky_query['post_parent'] = $forum_id;
				$sticky_query['post_status'] = $r['post_status'];
				$sticky_query['include']     = $local_stickies;
				$_posts = array_merge( $_posts, get_posts( $sticky_query ) );
			}
		}

		if ( ! empty( $_posts ) ) {

			// Merge the stickies topics with the query topics .
			$sticky_topics = array_merge( $sticky_topics, $_posts );

			// Get a count of the visible stickies
			$sticky_count = count( $_posts );

			// Adjust loop and counts for new sticky positions
			$query->found_posts = (int) $query->found_posts + (int) $sticky_count;
			$query->post_count  = (int) $query->post_count + (int) $sticky_count;
		}
	}

	// Bail if there are no sticky topics
	if ( empty( $sticky_topics ) ) {
		return;
	}

	// Default ordered stickies array
	$ordered_stickies = array(
		'supers' => array(),
		'forums' => array()
	);

	// Separate supers from forums
	foreach ( $sticky_topics as $post ) {
		if ( ! ( $post instanceof WP_Post ) ) {
			continue;
		}
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
 * Output the topic id.
 *
 * @since 2.0.0 bbPress (r2485)
 *
 * @param int|string $topic_id Optional. Topic ID.
 * @return void
 */
function bbp_topic_id( $topic_id = 0 ) {
	echo bbp_get_topic_id( $topic_id );
}

/**
 * Return the topic id.
 *
 * @since 2.0.0 bbPress (r2485)
 *
 * @param int|float|string|false $topic_id Optional. Topic ID, or false to use the current topic.
 * @return int The topic id.
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

	/**
	 * Filters the topic id.
	 *
	 * @since 2.0.0 bbPress (r2485)
	 *
	 * @param int $bbp_topic_id The topic id.
	 * @param int|float|string|false $topic_id Topic ID, or false to use the current topic.
	 */
	return (int) apply_filters( 'bbp_get_topic_id', (int) $bbp_topic_id, $topic_id );
}

/**
 * Gets a topic.
 *
 * @since 2.0.0 bbPress (r2787)
 *
 * @param int|WP_Post $topic Topic ID or post object.
 * @param string      $output Output type. Defaults to OBJECT.
 * @param string      $filter Post filter. Defaults to raw.
 * @return WP_Post|array<array-key, mixed>|null Topic in the requested format, or null if unavailable.
 * @phpstan-return ($output is 'ARRAY_A'|'ARRAY_N' ? array<array-key, mixed>|null : WP_Post|null)
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
	if ( bbp_get_topic_post_type() !== $topic->post_type ) {
		return null;
	}

	// Default return value is OBJECT
	$retval = $topic;

	// Array A
	if ( ARRAY_A === $output ) {
		$retval = get_object_vars( $topic );

	// Array N
	} elseif ( ARRAY_N === $output ) {
		$retval = array_values( get_object_vars( $topic ) );
	}

	/**
	 * Filters a topic.
	 *
	 * @since 2.0.0 bbPress (r2788)
	 *
	 * @param WP_Post|array<array-key, mixed>|null $retval Topic in the requested format, or null if unavailable.
	 * @param int|WP_Post $topic Topic ID or post object.
	 * @param string $output Output type. Defaults to OBJECT.
	 * @param string $filter Post filter. Defaults to raw.
	 */
	return apply_filters( 'bbp_get_topic', $retval, $topic, $output, $filter );
}

/**
 * Output the link to the topic in the topic loop.
 *
 * @since 2.0.0 bbPress (r2485)
 *
 * @param int    $topic_id Optional. Topic id.
 * @param string $redirect_to Optional. Pass a redirect value for use with
 *                              shortcodes and other fun things.
 *
 * @return void
 */
function bbp_topic_permalink( $topic_id = 0, $redirect_to = '' ) {
	echo esc_url( bbp_get_topic_permalink( $topic_id, $redirect_to ) );
}

/**
 * Return the link to the topic.
 *
 * @since 2.0.0 bbPress (r2485)
 *
 * @param int    $topic_id Optional. Topic id.
 * @param string $redirect_to Optional. Pass a redirect value for use with
 *                              shortcodes and other fun things.
 * @return string Permanent link to topic.
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

	/**
	 * Filters the link to the topic.
	 *
	 * @since 2.0.0 bbPress (r2485)
	 *
	 * @param mixed $topic_permalink Topic permalink, or false when unavailable.
	 * @param int $topic_id Topic id.
	 */
	return apply_filters( 'bbp_get_topic_permalink', $topic_permalink, $topic_id );
}

/**
 * Output the title of the topic.
 *
 * @since 2.0.0 bbPress (r2485)
 *
 * @param int $topic_id Optional. Topic id.
 * @return void
 */
function bbp_topic_title( $topic_id = 0 ) {
	echo bbp_get_topic_title( $topic_id );
}

/**
 * Return the title of the topic.
 *
 * @since 2.0.0 bbPress (r2485)
 *
 * @param int $topic_id Optional. Topic id.
 * @return string Title of topic.
 */
function bbp_get_topic_title( $topic_id = 0 ) {
	$topic_id = bbp_get_topic_id( $topic_id );
	$title    = get_post_field( 'post_title', $topic_id );
	$title    = apply_filters( 'the_title', $title, $topic_id );

	/**
	 * Filters the title of the topic.
	 *
	 * @since 2.0.0 bbPress (r2485)
	 *
	 * @param string $title Title of topic.
	 * @param int $topic_id Topic id.
	 */
	return apply_filters( 'bbp_get_topic_title', $title, $topic_id );
}

/**
 * Output the topic archive title.
 *
 * @since 2.0.0 bbPress (r3250)
 *
 * @param string $title Default text to use as title.
 * @return void
 */
function bbp_topic_archive_title( $title = '' ) {
	echo bbp_get_topic_archive_title( $title );
}

/**
 * Return the topic archive title.
 *
 * @since 2.0.0 bbPress (r3250)
 *
 * @param string $title Default text to use as title.
 *
 * @return string The topic archive title.
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
			$tto   = get_post_type_object( bbp_get_topic_post_type() );
			if ( $tto instanceof WP_Post_Type ) {
				$title = $tto->labels->name;
			}
		}
	}

	/**
	 * Filters the topic archive title.
	 *
	 * @since 2.0.0 bbPress (r3250)
	 *
	 * @param string $title The topic archive title.
	 */
	return apply_filters( 'bbp_get_topic_archive_title', $title );
}

/**
 * Output the content of the topic.
 *
 * @since 2.0.0 bbPress (r2780)
 *
 * @param int $topic_id Optional. Topic id.
 * @return void
 */
function bbp_topic_content( $topic_id = 0 ) {
	echo bbp_get_topic_content( $topic_id );
}

/**
 * Return the content of the topic.
 *
 * @since 2.0.0 bbPress (r2780)
 *
 * @param int $topic_id Optional. Topic id.
 * @return string Content of the topic.
 */
function bbp_get_topic_content( $topic_id = 0 ) {
	$topic_id = bbp_get_topic_id( $topic_id );

	// Check the topic and its forum ancestors.
	$password_id = bbp_get_password_required_id( $topic_id );
	if ( ! empty( $password_id ) ) {
		return get_the_password_form( $password_id );
	}

	$content = get_post_field( 'post_content', $topic_id );

	/**
	 * Filters the content of the topic.
	 *
	 * @since 2.0.0 bbPress (r2780)
	 *
	 * @param string $content Content of the topic.
	 * @param int $topic_id Topic id.
	 */
	return apply_filters( 'bbp_get_topic_content', $content, $topic_id );
}

/**
 * Output the excerpt of the topic.
 *
 * @since 2.0.0 bbPress (r2780)
 *
 * @param int $topic_id Optional. Topic id.
 * @param int $length Optional. Length of the excerpt. Defaults to 100 letters.
 * @return void
 */
function bbp_topic_excerpt( $topic_id = 0, $length = 100 ) {
	echo bbp_get_topic_excerpt( $topic_id, $length );
}

/**
 * Return the excerpt of the topic.
 *
 * @since 2.0.0 bbPress (r2780)
 *
 * @param int $topic_id Optional. topic id.
 * @param int $length Optional. Length of the excerpt. Defaults to 100
 *                     letters.
 * @return string topic Excerpt.
 */
function bbp_get_topic_excerpt( $topic_id = 0, $length = 100 ) {
	$topic_id    = bbp_get_topic_id( $topic_id );
	$length      = (int) $length;
	$password_id = bbp_get_password_required_id( $topic_id );
	$excerpt     = ! empty( $password_id )
		? get_the_password_form( $password_id )
		: get_post_field( 'post_excerpt', $topic_id );

	if ( empty( $excerpt ) ) {
		$excerpt = bbp_get_topic_content( $topic_id );
	}

	$excerpt = wp_strip_all_tags( $excerpt );

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

	/**
	 * Filters the excerpt of the topic.
	 *
	 * @since 2.0.0 bbPress (r2780)
	 *
	 * @param string $excerpt topic Excerpt.
	 * @param int $topic_id topic id.
	 * @param int $length Length of the excerpt. Defaults to 100 letters.
	 */
	return apply_filters( 'bbp_get_topic_excerpt', $excerpt, $topic_id, $length );
}

/**
 * Output the post date and time of a topic.
 *
 * @since 2.2.0 bbPress (r4156)
 * @since 2.6.20 bbPress (r7972) Added the `$date_format` parameter.
 *
 * @param int    $topic_id Optional. Topic id.
 * @param bool   $humanize Optional. Humanize output using time_since.
 * @param bool   $gmt Optional. Use GMT.
 * @param string $date_format Optional. PHP date format. Defaults to the WordPress date format.
 *                            Ignored when `$humanize` is true.
 *
 * @return void
 */
function bbp_topic_post_date( $topic_id = 0, $humanize = false, $gmt = false, $date_format = '' ) {
	echo bbp_get_topic_post_date( $topic_id, $humanize, $gmt, $date_format );
}

/**
 * Return the post date and time of a topic.
 *
 * @since 2.2.0 bbPress (r4156)
 * @since 2.6.20 bbPress (r7972) Added the `$date_format` parameter.
 *
 * @param int    $topic_id Optional. Topic id.
 * @param bool   $humanize Optional. Humanize output using time_since.
 * @param bool   $gmt Optional. Use GMT.
 * @param string $date_format Optional. PHP date format. Defaults to the WordPress date format.
 *                            Ignored when `$humanize` is true.
 * @return string
 */
function bbp_get_topic_post_date( $topic_id = 0, $humanize = false, $gmt = false, $date_format = '' ) {
	$topic_id    = bbp_get_topic_id( $topic_id );
	$date_format = ! empty( $date_format ) ? $date_format : get_option( 'date_format' );

	// 4 days, 4 hours ago
	if ( ! empty( $humanize ) ) {
		$gmt_s  = ! empty( $gmt ) ? 'G' : 'U';
		$date   = get_post_time( $gmt_s, $gmt, $topic_id );
		$time   = false; // For filter below
		$result = bbp_get_time_since( $date );

	// August 4, 2012 at 2:37 pm
	} else {
		$date   = get_post_time( $date_format, $gmt, $topic_id, true );
		$time   = get_post_time( get_option( 'time_format' ), $gmt, $topic_id, true );
		/* translators: 1: Date, 2: Time */
		$result = sprintf( _x( '%1$s at %2$s', 'date at time', 'bbpress' ), $date, $time );
	}

	/**
	 * Filters the post date and time of a topic.
	 *
	 * @since 2.2.0 bbPress (r4156)
	 *
	 * @param string $result The post date and time of a topic.
	 * @param int $topic_id Topic id.
	 * @param bool $humanize Humanize output using time_since.
	 * @param bool $gmt Use GMT.
	 * @param int|string|false $date Formatted date or timestamp, or false when unavailable.
	 * @param int|string|false $time Formatted time or timestamp, or false when unavailable.
	 * @param string $date_format PHP date format. Defaults to the WordPress date format. Ignored when `$humanize` is true.
	 */
	return apply_filters( 'bbp_get_topic_post_date', $result, $topic_id, $humanize, $gmt, $date, $time, $date_format );
}

/**
 * Output pagination links of a topic within the topic loop.
 *
 * @since 2.0.0 bbPress (r2967)
 *
 * @param array<string, mixed> $args See {@link bbp_get_topic_pagination()}.
 * @return void
 */
function bbp_topic_pagination( $args = array() ) {
	echo bbp_get_topic_pagination( $args );
}

/**
 * Returns pagination links of a topic within the topic loop.
 *
 * @since 2.0.0 bbPress (r2967)
 *
 * @param array<string, mixed> $args Supported arguments.
 *  - topic_id: Topic id
 *  - before: Before the links
 *  - after: After the links.
 * @return string|null Pagination links, or null when replies are threaded.
 */
function bbp_get_topic_pagination( $args = array() ) {

	// Bail if threading replies
	if ( bbp_thread_replies() ) {
		return null;
	}

	// Parse arguments against default values
	$r = bbp_parse_args(
		$args,
		array(
			'topic_id' => bbp_get_topic_id(),
			'before'   => '<span class="bbp-topic-pagination">',
			'after'    => '</span>',
		),
		'get_topic_pagination'
	);

	// Slug must be checked for topics that have never been approved/published
	$topic    = bbp_get_topic( $r['topic_id'] );
	$has_slug = ( $topic instanceof WP_Post ) ? $topic->post_name : '';

	// Get the topic permalink
	$topic_permalink = bbp_get_topic_permalink( $r['topic_id'] );

	// If pretty permalinks are enabled, make our pagination pretty
	$base = ! empty( $has_slug ) && bbp_use_pretty_urls() && bbp_is_topic_public( $r['topic_id'] )
		? trailingslashit( $topic_permalink ) . user_trailingslashit( bbp_get_paged_slug() . '/%#%/' )
		: add_query_arg( 'paged', '%#%', $topic_permalink );

	// Get total and add 1 if topic is included in the reply loop
	$total = bbp_get_topic_reply_count( $r['topic_id'], true );

	// Bump if topic is in loop
	if ( ! bbp_show_lead_topic() ) {
		++$total;
	}

	// Total for pagination boundaries
	$total_pages = ceil( $total / bbp_get_replies_per_page() );

	// Maybe add view-all args
	$add_args = bbp_get_view_all_for_object( 'edit_others_replies', $r['topic_id'] )
		? array( 'view' => 'all' )
		: false;

	// Pagination settings with filter
	/** This filter is documented in src/includes/topics/template.php. */
	$bbp_topic_pagination = apply_filters(
		'bbp_get_topic_pagination',
		array(
			'base'      => $base,
			'total'     => $total_pages,
			'current'   => 0,
			'prev_next' => false,
			'mid_size'  => 2,
			'end_size'  => 2,
			'add_args'  => $add_args
		)
	);

	// Add pagination to query object
	$pagination_links = bbp_paginate_links( $bbp_topic_pagination );

	// Maybe add before and after to pagination links
	if ( ! empty( $pagination_links ) ) {
		$pagination_links = $r['before'] . $pagination_links . $r['after'];
	}

	/**
	 * Filters the pagination links of a topic within the topic loop.
	 *
	 * @since 2.0.0 bbPress (r2967)
	 *
	 * @param mixed $pagination_links Pagination HTML, an empty array, or null when replies are threaded.
	 * @param array<string, mixed> $args Arguments passed to the function.
	 */
	return apply_filters( 'bbp_get_topic_pagination', $pagination_links, $args );
}

/**
 * Append revisions to the topic content.
 *
 * @since 2.0.0 bbPress (r2782)
 *
 * @param string $content Optional. Content to which we need to append the revisions to.
 * @param int    $topic_id Optional. Topic id.
 * @return string|null Content with revisions appended, or null in admin and feeds.
 */
function bbp_topic_content_append_revisions( $content = '', $topic_id = 0 ) {

	// Bail if in admin or feed
	if ( is_admin() || is_feed() ) {
		return null;
	}

	// Validate the ID
	$topic_id = bbp_get_topic_id( $topic_id );

	/**
	 * Filters the content with revisions appended.
	 *
	 * @since 2.0.0 bbPress (r2782)
	 *
	 * @param string|null $value Content with revisions appended, or null in admin and feeds.
	 * @param string $content Content to which we need to append the revisions to.
	 * @param int $topic_id Topic id.
	 */
	return apply_filters( 'bbp_topic_append_revisions', $content . bbp_get_topic_revision_log( $topic_id ), $content, $topic_id );
}

/**
 * Output the revision log of the topic.
 *
 * @since 2.0.0 bbPress (r2782)
 *
 * @param int $topic_id Optional. Topic id.
 * @return void
 */
function bbp_topic_revision_log( $topic_id = 0 ) {
	echo bbp_get_topic_revision_log( $topic_id );
}

/**
 * Return the formatted revision log of the topic.
 *
 * @since 2.0.0 bbPress (r2782)
 *
 * @param int $topic_id Optional. Topic id.
 * @return string|false Topic revision log, or false when no revisions are available.
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

	$retval = "\n\n" . '<ul id="bbp-topic-revision-log-' . esc_attr( (string) $topic_id ) . '" class="bbp-topic-revision-log">' . "\n\n";

	// Loop through revisions
	foreach ( (array) $revisions as $revision ) {

		if ( empty( $revision_log[ $revision->ID ] ) ) {
			$author_id = $revision->post_author;
			$reason    = '';
		} else {
			$author_id = (int) $revision_log[ $revision->ID ]['author'];
			$reason    = $revision_log[ $revision->ID ]['reason'];

			if ( empty( $author_id ) ) {
				$author_id = $revision->post_author;
			}
		}

		$since  = bbp_get_time_since( bbp_convert_date( $revision->post_modified ) );
		$author = bbp_get_author_link(
			array(
				'post_id'   => $revision->ID,
				'user_id'   => $author_id,
				'size'      => 14,
				'link_text' => bbp_get_topic_author_display_name( $revision->ID )
			)
		);

		$retval .= "\t" . '<li id="bbp-topic-revision-log-' . esc_attr( (string) $topic_id ) . '-item-' . esc_attr( (string) $revision->ID ) . '" class="bbp-topic-revision-log-item">' . "\n";
		if ( ! empty( $reason ) ) {
			/* translators: 1: Time since modification, 2: Author link, 3: Modification reason */
			$retval .= "\t\t" . sprintf( esc_html__( 'This topic was modified %1$s by %2$s. Reason: %3$s', 'bbpress' ), esc_html( $since ), $author, esc_html( $reason ) ) . "\n";
		} else {
			/* translators: 1: Time since modification, 2: Author link */
			$retval .= "\t\t" . sprintf( esc_html__( 'This topic was modified %1$s by %2$s.',              'bbpress' ), esc_html( $since ), $author ) . "\n";
		}
		$retval .= "\t" . '</li>' . "\n";
	}

	$retval .= "\n" . '</ul>' . "\n\n";

	/**
	 * Filters the formatted revision log of the topic.
	 *
	 * @since 2.0.0 bbPress (r2782)
	 *
	 * @param string|false $retval Topic revision log, or false when no revisions are available.
	 * @param int $topic_id Topic id.
	 */
	return apply_filters( 'bbp_get_topic_revision_log', $retval, $topic_id );
}

/**
 * Return the raw revision log of the topic.
 *
 * @since 2.0.0 bbPress (r2782)
 *
 * @param int $topic_id Optional. Topic id.
 * @return array<array-key, mixed> Raw revision log of the topic, or an empty array.
 */
function bbp_get_topic_raw_revision_log( $topic_id = 0 ) {
	$topic_id = bbp_get_topic_id( $topic_id );

	$revision_log = get_post_meta( $topic_id, '_bbp_revision_log', true );
	$revision_log = ! empty( $revision_log )
		? $revision_log
		: array();

	/**
	 * Filters the raw revision log of the topic.
	 *
	 * @since 2.0.0 bbPress (r2782)
	 *
	 * @param array<array-key, mixed> $revision_log Raw revision log of the topic, or an empty array.
	 * @param int $topic_id Topic id.
	 */
	return apply_filters( 'bbp_get_topic_raw_revision_log', $revision_log, $topic_id );
}

/**
 * Return the revisions of the topic.
 *
 * @since 2.0.0 bbPress (r2782)
 *
 * @param int $topic_id Optional. Topic id.
 * @return WP_Post[] Topic revisions, keyed by revision ID.
 */
function bbp_get_topic_revisions( $topic_id = 0 ) {
	$topic_id  = bbp_get_topic_id( $topic_id );
	$revisions = wp_get_post_revisions( $topic_id, array( 'order' => 'ASC' ) );

	/**
	 * Filters the revisions of the topic.
	 *
	 * @since 2.0.0 bbPress (r2782)
	 *
	 * @param mixed $revisions Topic revision IDs or posts, keyed by revision ID.
	 * @param int $topic_id Topic id.
	 */
	return apply_filters( 'bbp_get_topic_revisions', $revisions, $topic_id );
}

/**
 * Return the revision count of the topic.
 *
 * @since 2.0.0 bbPress (r2782)
 *
 * @param int  $topic_id Optional. Topic ID.
 * @param bool $integer  Whether to return an integer.
 * @return string|int Formatted revision count, or integer when $integer is true.
 */
function bbp_get_topic_revision_count( $topic_id = 0, $integer = false ) {
	$topic_id = bbp_get_topic_id( $topic_id );
	$count    = count( bbp_get_topic_revisions( $topic_id ) );
	$filter   = ( true === $integer )
		? 'bbp_get_topic_revision_count_int'
		: 'bbp_get_topic_revision_count';

	/**
	 * Filters a topic's revision count.
	 *
	 * The hook name is `bbp_get_topic_revision_count_int` for integer output and
	 * `bbp_get_topic_revision_count` for formatted output.
	 *
	 * @since 2.0.0 bbPress (r2782)
	 * @since 2.2.0 bbPress (r4258) Added the integer-count filter.
	 *
	 * @param int|string $count Unformatted count, or a formatted count after filtering.
	 * @param int $topic_id Topic id.
	 */
	return apply_filters( $filter, $count, $topic_id );
}

/**
 * Is the topic a sticky or super sticky?
 *
 * @since 2.0.0 bbPress (r2754)
 *
 * @param int $topic_id Optional. Topic id.
 * @param bool $check_super Optional. If set to true and if the topic is not a
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

	/**
	 * Filters whether the topic is a sticky or super sticky.
	 *
	 * @since 2.6.0 bbPress (r5680)
	 *
	 * @param bool $retval True if sticky or super sticky, false if not.
	 * @param int $topic_id Topic id.
	 * @param bool $check_super If set to true and if the topic is not a normal sticky, it is checked if it is a super sticky or not. Defaults to true.
	 */
	return (bool) apply_filters( 'bbp_is_topic_sticky', $retval, $topic_id, $check_super );
}

/**
 * Is the topic a super sticky?
 *
 * @since 2.0.0 bbPress (r2754)
 *
 * @param int $topic_id Optional. Topic id.
 * @return bool True if super sticky, false if not.
 */
function bbp_is_topic_super_sticky( $topic_id = 0 ) {
	$topic_id = bbp_get_topic_id( $topic_id );
	$stickies = bbp_get_super_stickies();
	$retval   = in_array( $topic_id, $stickies, true );

	/**
	 * Filters whether the topic is a super sticky.
	 *
	 * @since 2.6.0 bbPress (r5680)
	 *
	 * @param bool $retval True if super sticky, false if not.
	 * @param int $topic_id Topic id.
	 */
	return (bool) apply_filters( 'bbp_is_topic_super_sticky', $retval, $topic_id );
}

/**
 * Output the status of the topic.
 *
 * @since 2.0.0 bbPress (r2667)
 *
 * @param int $topic_id Optional. Topic id.
 * @return void
 */
function bbp_topic_status( $topic_id = 0 ) {
	echo bbp_get_topic_status( $topic_id );
}

/**
 * Return the status of the topic.
 *
 * @since 2.0.0 bbPress (r2667)
 *
 * @param int $topic_id Optional. Topic id.
 * @return string Status of topic.
 */
function bbp_get_topic_status( $topic_id = 0 ) {
	$topic_id = bbp_get_topic_id( $topic_id );

	/**
	 * Filters the status of the topic.
	 *
	 * @since 2.0.0 bbPress (r2667)
	 *
	 * @param mixed $value Status of topic.
	 * @param int $topic_id Topic id.
	 */
	return apply_filters( 'bbp_get_topic_status', get_post_status( $topic_id ), $topic_id );
}

/**
 * Is the topic closed to new replies?
 *
 * @since 2.0.0 bbPress (r2746)
 *
 * @param int $topic_id Optional. Topic id.
 *
 * @return bool True if closed, false if not.
 */
function bbp_is_topic_closed( $topic_id = 0 ) {
	$topic_id     = bbp_get_topic_id( $topic_id );
	$status       = bbp_get_closed_status_id();
	$topic_status = ( bbp_get_topic_status( $topic_id ) === $status );

	/**
	 * Filters whether the topic is closed to new replies.
	 *
	 * @since 2.1.0 bbPress (r3805)
	 *
	 * @param bool $topic_status True if closed, false if not.
	 * @param int $topic_id Topic id.
	 */
	return (bool) apply_filters( 'bbp_is_topic_closed', $topic_status, $topic_id );
}

/**
 * Is the topic open to new replies?
 *
 * @since 2.0.0 bbPress (r2727)
 *
 * @param int $topic_id Optional. Topic id.
 *
 * @return bool True if open, false if closed.
 */
function bbp_is_topic_open( $topic_id = 0 ) {
	return ! bbp_is_topic_closed( $topic_id );
}

/**
 * Is the topic publicly viewable?
 *
 * The topic status and filters can change between calls.
 *
 * See bbp_get_public_topic_statuses() for public statuses.
 *
 * @since 2.6.0 bbPress (r6384)
 *
 * @param int $topic_id Optional. Topic id.
 * @return bool True if public, false if not.
 *
 * @phpstan-impure
 */
function bbp_is_topic_public( $topic_id = 0 ) {
	$topic_id  = bbp_get_topic_id( $topic_id );
	$status    = bbp_get_topic_status( $topic_id );
	$public    = bbp_get_public_topic_statuses();
	$is_public = in_array( $status, $public, true );

	/**
	 * Filters whether the topic is publicly viewable.
	 *
	 * @since 2.6.0 bbPress (r6384)
	 *
	 * @param bool $is_public True if public, false if not.
	 * @param int $topic_id Topic id.
	 */
	return (bool) apply_filters( 'bbp_is_topic_public', $is_public, $topic_id );
}

/**
 * Does the topic have a published status?
 *
 * @since 2.0.0 bbPress (r3501)
 *
 * @param int $topic_id Optional. Topic id.
 * @return bool True if published, false if not.
 */
function bbp_is_topic_published( $topic_id = 0 ) {
	$topic_id     = bbp_get_topic_id( $topic_id );
	$status       = bbp_get_public_status_id();
	$topic_status = ( bbp_get_topic_status( $topic_id ) === $status );

	/**
	 * Filters whether a topic is published.
	 *
	 * @since 2.1.0 bbPress (r3805)
	 *
	 * @param bool $topic_status True if published, false if not.
	 * @param int $topic_id Topic id.
	 */
	return (bool) apply_filters( 'bbp_is_topic_published', $topic_status, $topic_id );
}

/**
 * Is the topic marked as spam?
 *
 * @since 2.0.0 bbPress (r2727)
 *
 * @param int $topic_id Optional. Topic id.
 * @return bool True if spam, false if not.
 */
function bbp_is_topic_spam( $topic_id = 0 ) {
	$topic_id     = bbp_get_topic_id( $topic_id );
	$status       = bbp_get_spam_status_id();
	$topic_status = ( bbp_get_topic_status( $topic_id ) === $status );

	/**
	 * Filters whether the topic is marked as spam.
	 *
	 * @since 2.1.0 bbPress (r3805)
	 *
	 * @param bool $topic_status True if spam, false if not.
	 * @param int $topic_id Topic id.
	 */
	return (bool) apply_filters( 'bbp_is_topic_spam', $topic_status, $topic_id );
}

/**
 * Is the topic trashed?
 *
 * @since 2.0.0 bbPress (r2893)
 *
 * @param int $topic_id Optional. Topic id.
 * @return bool True if trashed, false if not.
 */
function bbp_is_topic_trash( $topic_id = 0 ) {
	$topic_id     = bbp_get_topic_id( $topic_id );
	$status       = bbp_get_trash_status_id();
	$topic_status = ( bbp_get_topic_status( $topic_id ) === $status );

	/**
	 * Filters whether the topic is trashed.
	 *
	 * @since 2.1.0 bbPress (r3805)
	 *
	 * @param bool $topic_status True if trashed, false if not.
	 * @param int $topic_id Topic id.
	 */
	return (bool) apply_filters( 'bbp_is_topic_trash', $topic_status, $topic_id );
}

/**
 * Is the topic pending?
 *
 * @since 2.6.0 bbPress (r5504)
 *
 * @param int $topic_id Optional. Topic id.
 * @return bool True if pending, false if not.
 */
function bbp_is_topic_pending( $topic_id = 0 ) {
	$topic_id     = bbp_get_topic_id( $topic_id );
	$status       = bbp_get_pending_status_id();
	$topic_status = ( bbp_get_topic_status( $topic_id ) === $status );

	/**
	 * Filters whether the topic is pending.
	 *
	 * @since 2.6.0 bbPress (r5504)
	 *
	 * @param bool $topic_status True if pending, false if not.
	 * @param int $topic_id Topic id.
	 */
	return (bool) apply_filters( 'bbp_is_topic_pending', $topic_status, $topic_id );
}

/**
 * Is the topic private?
 *
 * @since 2.6.0 bbPress (r5504)
 *
 * @param int $topic_id Optional. Topic id.
 * @return bool True if private, false if not.
 */
function bbp_is_topic_private( $topic_id = 0 ) {
	$topic_id     = bbp_get_topic_id( $topic_id );
	$status       = bbp_get_private_status_id();
	$topic_status = ( bbp_get_topic_status( $topic_id ) === $status );

	/**
	 * Filters whether the topic is private.
	 *
	 * @since 2.6.0 bbPress (r5504)
	 *
	 * @param bool $topic_status True if private, false if not.
	 * @param int $topic_id Topic id.
	 */
	return (bool) apply_filters( 'bbp_is_topic_private', $topic_status, $topic_id );
}

/**
 * Is the posted by an anonymous user?
 *
 * @since 2.0.0 bbPress (r2753)
 *
 * @param int $topic_id Optional. Topic id.
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

	/**
	 * Filters whether a topic has an anonymous author.
	 *
	 * @since 2.1.0 bbPress (r3805)
	 *
	 * @param bool $retval True if the post is by an anonymous user, false if not.
	 * @param int $topic_id Topic id.
	 */
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
 * @param int $topic_id Optional. Topic id.
 * @return void
 */
function bbp_topic_author( $topic_id = 0 ) {
	echo bbp_get_topic_author_display_name( $topic_id );
}

/**
 * Deprecated. Use bbp_get_topic_author_display_name() instead.
 *
 * Return the author of the topic.
 *
 * @since 2.0.0 bbPress (r2590)
 *
 * @deprecated 2.5.0 bbPress (r5119)
 *
 * @param int $topic_id Optional. Topic id.
 * @return string Author of topic.
 */
function bbp_get_topic_author( $topic_id = 0 ) {
	$topic_id = bbp_get_topic_id( $topic_id );

	if ( ! bbp_is_topic_anonymous( $topic_id ) ) {
		$author = get_the_author_meta( 'display_name', bbp_get_topic_author_id( $topic_id ) );
	} else {
		$author = get_post_meta( $topic_id, '_bbp_anonymous_name', true );
	}

	/**
	 * Filters the author of topic.
	 *
	 * @since 2.0.0 bbPress (r2590)
	 *
	 * @param string $author Author of topic.
	 * @param int $topic_id Topic id.
	 */
	return apply_filters( 'bbp_get_topic_author', $author, $topic_id );
}

/**
 * Output the author ID of the topic.
 *
 * @since 2.0.0 bbPress (r2590)
 *
 * @param int $topic_id Optional. Topic id.
 * @return void
 */
function bbp_topic_author_id( $topic_id = 0 ) {
	echo bbp_get_topic_author_id( $topic_id );
}

/**
 * Return the author ID of the topic.
 *
 * @since 2.0.0 bbPress (r2590)
 *
 * @param int $topic_id Optional. Topic id.
 * @return int Author ID of the topic.
 */
function bbp_get_topic_author_id( $topic_id = 0 ) {
	$topic_id  = bbp_get_topic_id( $topic_id );
	$author_id = get_post_field( 'post_author', $topic_id );

	/**
	 * Filters the author ID of the topic.
	 *
	 * @since 2.0.0 bbPress (r2590)
	 *
	 * @param int $author_id Author ID of the topic.
	 * @param int $topic_id Topic id.
	 */
	return (int) apply_filters( 'bbp_get_topic_author_id', (int) $author_id, $topic_id );
}

/**
 * Output the author display_name of the topic.
 *
 * @since 2.0.0 bbPress (r2590)
 *
 * @param int $topic_id Optional. Topic id.
 * @return void
 */
function bbp_topic_author_display_name( $topic_id = 0 ) {
	echo bbp_get_topic_author_display_name( $topic_id );
}

/**
 * Return the author display_name of the topic.
 *
 * @since 2.0.0 bbPress (r2590)
 *
 * @param int $topic_id Optional. Topic id.
 * @return string Topic's author's display name.
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

	/**
	 * Filters a topic author's display name.
	 *
	 * @since 2.1.0 bbPress (r3648)
	 *
	 * @param string $author_name Topic's author's display name.
	 * @param int $topic_id Topic id.
	 */
	return apply_filters( 'bbp_get_topic_author_display_name', $author_name, $topic_id );
}

/**
 * Output the author avatar of the topic.
 *
 * @since 2.0.0 bbPress (r2590)
 *
 * @param int $topic_id Optional. Topic id.
 * @param int $size Optional. Avatar size. Defaults to 40.
 * @return void
 */
function bbp_topic_author_avatar( $topic_id = 0, $size = 40 ) {
	echo bbp_get_topic_author_avatar( $topic_id, $size );
}

/**
 * Return the author avatar of the topic.
 *
 * @since 2.0.0 bbPress (r2590)
 *
 * @param int $topic_id Optional. Topic id.
 * @param int $size Optional. Avatar size. Defaults to 40.
 * @return string Avatar of the author of the topic.
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

	/**
	 * Filters the author avatar of the topic.
	 *
	 * @since 2.0.0 bbPress (r2590)
	 *
	 * @param mixed $author_avatar Avatar of the author of the topic.
	 * @param int $topic_id Topic id.
	 * @param int $size Avatar size. Defaults to 40.
	 */
	return apply_filters( 'bbp_get_topic_author_avatar', $author_avatar, $topic_id, $size );
}

/**
 * Output the author link of the topic.
 *
 * @since 2.0.0 bbPress (r2720)
 *
 * @param mixed|int $args If it is an integer, it is used as topic_id. Optional.
 * @return void
 */
function bbp_topic_author_link( $args = array() ) {
	echo bbp_get_topic_author_link( $args );
}

/**
 * Return the author link of the topic.
 *
 * @since 2.0.0 bbPress (r2720)
 *
 * @param mixed|int $args If it is an integer, it is used as topic id.
 *                         Optional.
 * @return string Author link of topic.
 */
function bbp_get_topic_author_link( $args = array() ) {

	// Parse arguments against default values
	$parse_args = $args;
	if ( ! is_array( $parse_args ) && ! is_int( $parse_args ) && ! is_string( $parse_args ) && ! is_object( $parse_args ) ) {
		$parse_args = array();
	}

	$r = bbp_parse_args(
		$parse_args,
		array(
			'post_id'    => 0,
			'link_title' => '',
			'type'       => 'both',
			'size'       => 80,
			'sep'        => '',
			'show_role'  => false
		),
		'get_topic_author_link'
	);

	$post_arg  = is_numeric( $r['post_id'] ) ? $r['post_id'] : 0;
	$title_arg = bbp_get_string_arg( $r, 'link_title' );
	$sep       = bbp_get_string_arg( $r, 'sep' );
	$size       = is_numeric( $r['size'] ) ? (int) $r['size'] : 80;

	// Default return value
	$author_link = '';

	// Used as topic_id
	$topic_id = is_numeric( $args )
		? bbp_get_topic_id( $args )
		: bbp_get_topic_id( $post_arg );

	// Topic ID is good
	if ( ! empty( $topic_id ) ) {

		// Get some useful topic information
		$author_url = bbp_validate_url( bbp_get_topic_author_url( $topic_id ) );
		$anonymous  = bbp_is_topic_anonymous( $topic_id );

		// Tweak link title if empty
		if ( empty( $title_arg ) ) {
			$author = bbp_get_topic_author_display_name( $topic_id );
			$title  = empty( $anonymous )
				/* translators: %s: Author's name */
				? esc_attr__( "View %s's profile",  'bbpress' )
				/* translators: %s: Author's name */
				: esc_attr__( "Visit %s's website", 'bbpress' );

			$link_title = sprintf( $title, $author );

		// Use what was passed if not
		} else {
			$link_title = $title_arg;
		}

		// Setup title and author_links array
		$author_links = array();
		$link_title   = ! empty( $link_title )
			? ' title="' . esc_attr( $link_title ) . '"'
			: '';

		// Get avatar (unescaped, because HTML)
		if ( ( 'avatar' === $r['type'] ) || ( 'both' === $r['type'] ) ) {
			$author_links['avatar'] = bbp_get_topic_author_avatar( $topic_id, $size );
		}

		// Get display name (escaped, because never HTML)
		if ( ( 'name' === $r['type'] ) || ( 'both' === $r['type'] ) ) {
			$author_links['name'] = esc_html( bbp_get_topic_author_display_name( $topic_id ) );
		}

		// Empty array
		$links  = array();
		$sprint = '<span%1$s>%2$s</span>';

		// Wrap each link
		foreach ( $author_links as $link => $link_html ) {
			$link_class = ' class="bbp-author-' . esc_attr( $link ) . '"';
			$links[]    = sprintf( $sprint, $link_class, $link_html );
		}

		// Juggle
		$author_links = $links;
		unset( $links );

		// Filter sections

		/**
		 * Filters the links in a topic author's information.
		 *
		 * @since 2.6.0 bbPress (r6591)
		 *
		 * @param array $author_links The links in a topic author's information.
		 * @param array $r Parsed arguments.
		 * @param mixed|int $args Original arguments.
		 */
		$sections = apply_filters( 'bbp_get_topic_author_links', $author_links, $r, $args );

		// Assemble sections into author link
		$author_link = implode( $sep, $sections );

		// Only wrap in link if profile exists
		if ( empty( $anonymous ) && ( false !== $author_url ) && bbp_user_has_profile( bbp_get_topic_author_id( $topic_id ) ) ) {
			$author_link = sprintf( '<a href="%1$s"%2$s%3$s>%4$s</a>', esc_url( $author_url ), $link_title, ' class="bbp-author-link"', $author_link );
		}

		// Role is not linked
		if ( true === $r['show_role'] ) {
			$author_link .= bbp_get_topic_author_role( array( 'topic_id' => $topic_id ) );
		}
	}

	/**
	 * Filters the author link of the topic.
	 *
	 * @since 2.0.0 bbPress (r2720)
	 *
	 * @param string $author_link Author link of topic.
	 * @param array $r Arguments.
	 * @param mixed|int $args If it is an integer, it is used as topic id. Optional.
	 */
	return apply_filters( 'bbp_get_topic_author_link', $author_link, $r, $args );
}

/**
 * Output the author url of the topic.
 *
 * @since 2.0.0 bbPress (r2590)
 *
 * @param int $topic_id Optional. Topic id.
 * @return void
 */
function bbp_topic_author_url( $topic_id = 0 ) {
	echo esc_url( bbp_get_topic_author_url( $topic_id ) );
}

/**
 * Return the author url of the topic.
 *
 * @since 2.0.0 bbPress (r2590)
 *
 * @param int $topic_id Optional. Topic id.
 * @return string Author URL of topic.
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

	/**
	 * Filters the author url of the topic.
	 *
	 * @since 2.0.0 bbPress (r2590)
	 *
	 * @param string $author_url Author URL of topic.
	 * @param int $topic_id Topic id.
	 */
	return apply_filters( 'bbp_get_topic_author_url', $author_url, $topic_id );
}

/**
 * Output the topic author email address.
 *
 * @since 2.0.0 bbPress (r3446)
 *
 * @param int $topic_id Optional. Reply id.
 * @return void
 */
function bbp_topic_author_email( $topic_id = 0 ) {
	echo bbp_get_topic_author_email( $topic_id );
}

/**
 * Return the topic author email address.
 *
 * @since 2.0.0 bbPress (r3446)
 *
 * @param int $topic_id Optional. Reply id.
 * @return string Topic author email address.
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

	/**
	 * Filters the topic author email address.
	 *
	 * @since 2.0.0 bbPress (r3446)
	 *
	 * @param string $author_email Topic author email address.
	 * @param int $topic_id Reply id.
	 */
	return apply_filters( 'bbp_get_topic_author_email', $author_email, $topic_id );
}

/**
 * Output the topic author role.
 *
 * @since 2.1.0 bbPress (r3860)
 *
 * @param array<string, mixed> $args Optional.
 * @return void
 */
function bbp_topic_author_role( $args = array() ) {
	echo bbp_get_topic_author_role( $args );
}

/**
 * Return the topic author role.
 *
 * @since 2.1.0 bbPress (r3860)
 *
 * @param array<string, mixed> $args Optional.
 * @return string topic author role.
 */
function bbp_get_topic_author_role( $args = array() ) {

	// Parse arguments against default values
	$r = bbp_parse_args(
		$args,
		array(
			'topic_id' => 0,
			'class'    => false,
			'before'   => '<div class="bbp-author-role">',
			'after'    => '</div>'
		),
		'get_topic_author_role'
	);

	$topic_id = bbp_get_topic_id( $r['topic_id'] );
	$role     = bbp_get_user_display_role( bbp_get_topic_author_id( $topic_id ) );
	$css_role = sanitize_key( $role );

	// Add role as CSS class to default 'before' value.
	if ( empty( $args['before'] ) ) {
		$r['before'] = '<div class="bbp-author-role bbp-role-' . esc_attr( $css_role ) . '">';
	}

	$before_html = bbp_get_string_arg( $r, 'before' );
	$after_html  = bbp_get_string_arg( $r, 'after' );

	// Backwards compatibilty with old 'class' argument
	if ( ! empty( $r['class'] ) ) {
		$author_role = sprintf( '%1$s<div class="%2$s">%3$s</div>%4$s', $before_html, $r['class'], $role, $after_html );

	// Simpler before & after arguments
	// https://bbpress.trac.wordpress.org/ticket/2557
	} else {
		$author_role = $before_html . $role . $after_html;
	}

	/**
	 * Filters the topic author role.
	 *
	 * @since 2.1.0 bbPress (r3860)
	 *
	 * @param string $author_role topic author role.
	 * @param array $r Arguments.
	 */
	return apply_filters( 'bbp_get_topic_author_role', $author_role, $r );
}

/**
 * Output the title of the forum a topic belongs to.
 *
 * @since 2.0.0 bbPress (r2572)
 *
 * @param int $topic_id Optional. Topic id.
 * @return void
 */
function bbp_topic_forum_title( $topic_id = 0 ) {
	echo bbp_get_topic_forum_title( $topic_id );
}

/**
 * Return the title of the forum a topic belongs to.
 *
 * @since 2.0.0 bbPress (r2572)
 *
 * @param int $topic_id Optional. Topic id.
 * @return string Topic forum title.
 */
function bbp_get_topic_forum_title( $topic_id = 0 ) {
	$topic_id = bbp_get_topic_id( $topic_id );
	$forum_id = bbp_get_topic_forum_id( $topic_id );

	/**
	 * Filters the title of the forum a topic belongs to.
	 *
	 * @since 2.0.0 bbPress (r2498)
	 *
	 * @param string $value Topic forum title.
	 * @param int $topic_id Topic id.
	 * @param int $forum_id Forum id.
	 */
	return apply_filters( 'bbp_get_topic_forum', bbp_get_forum_title( $forum_id ), $topic_id, $forum_id );
}

/**
 * Output the forum id a topic belongs to.
 *
 * @since 2.0.0 bbPress (r2494)
 *
 * @param int $topic_id Optional. Topic id.
 * @return void
 */
function bbp_topic_forum_id( $topic_id = 0 ) {
	echo bbp_get_topic_forum_id( $topic_id );
}

/**
 * Return the forum id a topic belongs to.
 *
 * @since 2.0.0 bbPress (r2494)
 *
 * @param int $topic_id Optional. Topic id.
 * @return int Topic forum id.
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

	/**
	 * Filters the forum id a topic belongs to.
	 *
	 * @since 2.0.0 bbPress (r2498)
	 *
	 * @param int $forum_id Topic forum id.
	 * @param int $topic_id Topic id.
	 */
	return (int) apply_filters( 'bbp_get_topic_forum_id', $forum_id, $topic_id );
}

/**
 * Output the topics last active ID.
 *
 * @since 2.0.0 bbPress (r2872)
 *
 * @param int $topic_id Optional. Forum id.
 * @return void
 */
function bbp_topic_last_active_id( $topic_id = 0 ) {
	echo bbp_get_topic_last_active_id( $topic_id );
}

/**
 * Return the topics last active ID.
 *
 * @since 2.0.0 bbPress (r2872)
 *
 * @param int $topic_id Optional. Forum id.
 * @return int Forum's last active id.
 */
function bbp_get_topic_last_active_id( $topic_id = 0 ) {
	$topic_id  = bbp_get_topic_id( $topic_id );
	$active_id = (int) get_post_meta( $topic_id, '_bbp_last_active_id', true );

	/**
	 * Filters a topic's last active post ID.
	 *
	 * @since 2.0.0 bbPress (r2872)
	 *
	 * @param int $active_id Last active post ID in the topic.
	 * @param int $topic_id Forum id.
	 */
	return (int) apply_filters( 'bbp_get_topic_last_active_id', $active_id, $topic_id );
}

/**
 * Output the topics last update date/time (aka freshness).
 *
 * @since 2.0.0 bbPress (r2872)
 *
 * @param int $topic_id Optional. Topic id.
 * @return void
 */
function bbp_topic_last_active_time( $topic_id = 0 ) {
	echo bbp_get_topic_last_active_time( $topic_id );
}

/**
 * Return the topics last update date/time (aka freshness).
 *
 * @since 2.0.0 bbPress (r2872)
 *
 * @param int $topic_id Optional. Topic id.
 * @return string Topic freshness.
 */
function bbp_get_topic_last_active_time( $topic_id = 0 ) {
	$topic_id     = bbp_get_topic_id( $topic_id );
	$active_id    = bbp_get_topic_last_active_id( $topic_id );
	$reply_id     = bbp_get_topic_last_reply_id( $topic_id );
	$activity_ids = array( $topic_id, $active_id, $reply_id );

	// Topic metadata may point to activity that still requires a password.
	if ( ! empty( $topic_id ) && bbp_get_password_required_id( $activity_ids ) ) {
		/** This filter is documented in src/includes/topics/template.php. */
		return apply_filters( 'bbp_get_topic_last_active', '', $topic_id );
	}

	// Try to get the most accurate freshness time possible
	$last_active = get_post_meta( $topic_id, '_bbp_last_active_time', true );
	if ( empty( $last_active ) ) {
		if ( ! empty( $reply_id ) ) {
			$last_active = get_post_field( 'post_date', $reply_id );
		} else {
			$last_active = get_post_field( 'post_date', $topic_id );
		}
	}

	$last_active = ! empty( $last_active ) ? bbp_get_time_since( bbp_convert_date( $last_active ) ) : '';

	/**
	 * Filters the topics last update date/time (aka freshness).
	 *
	 * @since 2.0.0 bbPress (r2485)
	 *
	 * @param string $last_active Topic freshness.
	 * @param int $topic_id Topic id.
	 */
	return apply_filters( 'bbp_get_topic_last_active', $last_active, $topic_id );
}

/** Topic Subscriptions *******************************************************/

/**
 * Output the topic subscription link.
 *
 * @since 2.5.0 bbPress (r5156)
 * @since 2.6.0 bbPress (r6307) Add 'redirect_to' support
 *
 * @param array<string, mixed> $args Subscription link arguments.
 * @return void
 */
function bbp_topic_subscription_link( $args = array() ) {
	echo bbp_get_topic_subscription_link( $args );
}

/**
 * Get the topic subscription link.
 *
 * A custom wrapper for bbp_get_user_subscribe_link()
 *
 * @since 2.5.0 bbPress (r5156)
 * @since 2.6.0 bbPress (r6307) Add 'redirect_to' support
 *
 * @param array<string, mixed> $args Subscription link arguments.
 * @return string|false|null Subscription link, false when unavailable, or null when subscriptions are inactive.
 */
function bbp_get_topic_subscription_link( $args = array() ) {

	// Defaults
	$retval      = false;
	$user_id     = bbp_is_subscriptions()
		? bbp_get_user_id( 0, true, true )
		: bbp_get_current_user_id();
	$redirect_to = bbp_is_subscriptions()
		? bbp_get_subscriptions_permalink( $user_id )
		: '';

	// Parse the arguments
	$r = bbp_parse_args(
		$args,
		array(
			'user_id'     => $user_id,
			'object_id'   => bbp_get_topic_id(),
			'object_type' => 'post',
			'before'      => '&nbsp;|&nbsp;',
			'after'       => '',
			'subscribe'   => esc_html__( 'Subscribe',   'bbpress' ),
			'unsubscribe' => esc_html__( 'Unsubscribe', 'bbpress' ),
			'redirect_to' => $redirect_to
		),
		'get_topic_subscribe_link'
	);

	// Get the link
	$retval = bbp_get_user_subscribe_link( $r );

	/**
	 * Filters the topic subscription link.
	 *
	 * @since 2.5.0 bbPress (r5156)
	 *
	 * @param string|false|null $retval Subscription link, false when unavailable, or null when subscriptions are inactive.
	 * @param array $r Arguments.
	 * @param array<string, mixed> $args Subscription link arguments.
	 */
	return apply_filters( 'bbp_get_topic_subscribe_link', $retval, $r, $args );
}

/** Topic Favorites ***********************************************************/

/**
 * Output the topic favorite link.
 *
 * @since 2.5.0 bbPress (r5156)
 * @since 2.6.0 bbPress (r6307) Add 'redirect_to' support
 *
 * @param array<string, mixed> $args Favorite link arguments.
 * @return void
 */
function bbp_topic_favorite_link( $args = array() ) {
	echo bbp_get_topic_favorite_link( $args );
}

/**
 * Get the forum favorite link.
 *
 * A custom wrapper for bbp_get_user_favorites_link().
 *
 * @since 2.5.0 bbPress (r5156)
 * @since 2.6.0 bbPress (r6307) Add 'redirect_to' support
 *
 * @param array<string, mixed> $args Favorite link arguments.
 * @return string|false Favorite link, or false when unavailable.
 */
function bbp_get_topic_favorite_link( $args = array() ) {

	// No link
	$retval      = false;
	$user_id     = bbp_is_favorites()
		? bbp_get_user_id( 0, true, true )
		: bbp_get_current_user_id();
	$redirect_to = bbp_is_favorites()
		? bbp_get_favorites_permalink( $user_id )
		: '';

	// Parse the arguments
	$r = bbp_parse_args(
		$args,
		array(
			'user_id'     => $user_id,
			'object_id'   => bbp_get_topic_id(),
			'object_type' => 'post',
			'before'      => '',
			'after'       => '',
			'favorite'    => esc_html__( 'Favorite',   'bbpress' ),
			'favorited'   => esc_html__( 'Unfavorite', 'bbpress' ),
			'redirect_to' => $redirect_to
		),
		'get_topic_favorite_link'
	);

	// Get the link
	$retval = bbp_get_user_favorites_link( $r );

	/**
	 * Filters the topic favorite link.
	 *
	 * @since 2.5.0 bbPress (r5156)
	 *
	 * @param string|false $retval Favorite link, or false when unavailable.
	 * @param array $r Arguments.
	 * @param array<string, mixed> $args Favorite link arguments.
	 */
	return apply_filters( 'bbp_get_topic_favorite_link', $retval, $r, $args );
}

/** Topic Last Reply **********************************************************/

/**
 * Output the id of the topics last reply.
 *
 * @since 2.0.0 bbPress (r2627)
 *
 * @param int $topic_id Optional. Topic id.
 * @return void
 */
function bbp_topic_last_reply_id( $topic_id = 0 ) {
	echo bbp_get_topic_last_reply_id( $topic_id );
}

/**
 * Return the id of the topics last reply.
 *
 * @since 2.0.0 bbPress (r2627)
 *
 * @param int $topic_id Optional. Topic id.
 * @return int Topic last reply id.
 */
function bbp_get_topic_last_reply_id( $topic_id = 0 ) {
	$topic_id = bbp_get_topic_id( $topic_id );
	$reply_id = (int) get_post_meta( $topic_id, '_bbp_last_reply_id', true );

	/**
	 * Filters the id of the topics last reply.
	 *
	 * @since 2.0.0 bbPress (r2627)
	 *
	 * @param int $reply_id Topic last reply id.
	 * @param int $topic_id Topic id.
	 */
	return (int) apply_filters( 'bbp_get_topic_last_reply_id', $reply_id, $topic_id );
}

/**
 * Output the title of the last reply inside a topic.
 *
 * @since 2.0.0 bbPress (r2627)
 *
 * @param int $topic_id Optional. Topic id.
 * @return void
 */
function bbp_topic_last_reply_title( $topic_id = 0 ) {
	echo bbp_get_topic_last_reply_title( $topic_id );
}

/**
 * Return the title of the last reply inside a topic.
 *
 * @since 2.0.0 bbPress (r2627)
 * @since 2.6.0 bbPress (r6244) https://bbpress.trac.wordpress.org/ticket/3039
 *
 * @param int $topic_id Optional. Topic id.
 * @return string Topic last reply title.
 */
function bbp_get_topic_last_reply_title( $topic_id = 0 ) {
	$topic_id = bbp_get_topic_id( $topic_id );
	$reply_id = bbp_get_topic_last_reply_id( $topic_id );
	$retval   = bbp_get_reply_title( $reply_id );

	// Misspelled. Use 'bbp_get_topic_last_reply_title' hook instead.

	/**
	 * Filters the latest topic title shown in a topic.
	 *
	 * @since 2.0.0 bbPress (r2627)
	 *
	 * @param string $retval The latest topic title shown in a topic.
	 * @param int $topic_id Topic id.
	 * @param int $reply_id Reply id.
	 */
	$retval = apply_filters( 'bbp_get_topic_last_topic_title', $retval, $topic_id, $reply_id );

	/**
	 * Filters the title of the last reply inside a topic.
	 *
	 * @since 2.6.0 bbPress (r6244)
	 *
	 * @param string $retval Topic last reply title.
	 * @param int $topic_id Topic id.
	 * @param int $reply_id Reply id.
	 */
	return apply_filters( 'bbp_get_topic_last_reply_title', $retval, $topic_id, $reply_id );
}

/**
 * Output the link to the last reply in a topic.
 *
 * @since 2.0.0 bbPress (r2627)
 *
 * @param int $topic_id Optional. Topic id.
 * @return void
 */
function bbp_topic_last_reply_permalink( $topic_id = 0 ) {
	echo esc_url( bbp_get_topic_last_reply_permalink( $topic_id ) );
}

/**
 * Return the link to the last reply in a topic.
 *
 * @since 2.0.0 bbPress (r2627)
 *
 * @param int $topic_id Optional. Topic id.
 * @return string Permanent link to the reply.
 */
function bbp_get_topic_last_reply_permalink( $topic_id = 0 ) {
	$topic_id = bbp_get_topic_id( $topic_id );
	$reply_id = bbp_get_topic_last_reply_id( $topic_id );
	$retval   = bbp_get_reply_permalink( $reply_id );

	/**
	 * Filters the link to the last reply in a topic.
	 *
	 * @since 2.0.0 bbPress (r2627)
	 *
	 * @param string $retval Permanent link to the reply.
	 * @param int $topic_id Topic id.
	 * @param int $reply_id Reply id.
	 */
	return apply_filters( 'bbp_get_topic_last_reply_permalink', $retval, $topic_id, $reply_id );
}

/**
 * Output the link to the last reply in a topic.
 *
 * @since 2.0.0 bbPress (r2684)
 *
 * @param int $topic_id Optional. Topic id.
 * @return void
 */
function bbp_topic_last_reply_url( $topic_id = 0 ) {
	echo esc_url( bbp_get_topic_last_reply_url( $topic_id ) );
}

/**
 * Return the link to the last reply in a topic.
 *
 * @since 2.0.0 bbPress (r2684)
 *
 * @param int $topic_id Optional. Topic id.
 * @return string Topic last reply url.
 */
function bbp_get_topic_last_reply_url( $topic_id = 0 ) {
	$topic_id = bbp_get_topic_id( $topic_id );
	$reply_id = bbp_get_topic_last_reply_id( $topic_id );

	if ( ! empty( $reply_id ) && ( $reply_id !== $topic_id ) ) {
		$reply_url = bbp_get_reply_url( $reply_id );
	} else {
		$reply_url = bbp_get_topic_permalink( $topic_id );
	}

	/**
	 * Filters the link to the last reply in a topic.
	 *
	 * @since 2.0.0 bbPress (r2684)
	 *
	 * @param string $reply_url Topic last reply url.
	 * @param int $topic_id Topic id.
	 * @param int $reply_id Reply id.
	 */
	return apply_filters( 'bbp_get_topic_last_reply_url', $reply_url, $topic_id, $reply_id );
}

/**
 * Output link to the most recent activity inside a topic, complete with link
 * attributes and content.
 *
 * @since 2.0.0 bbPress (r2627)
 *
 * @param int $topic_id Optional. Topic id.
 * @return void
 */
function bbp_topic_freshness_link( $topic_id = 0 ) {
	echo bbp_get_topic_freshness_link( $topic_id );
}

/**
 * Returns link to the most recent activity inside a topic, complete
 * with link attributes and content.
 *
 * @since 2.0.0 bbPress (r2627)
 *
 * @param int $topic_id Optional. Topic id.
 * @return string Topic freshness link.
 */
function bbp_get_topic_freshness_link( $topic_id = 0 ) {
	$topic_id     = bbp_get_topic_id( $topic_id );
	$active_id    = bbp_get_topic_last_active_id( $topic_id );
	$reply_id     = bbp_get_topic_last_reply_id( $topic_id );
	$activity_ids = array( $topic_id, $active_id, $reply_id );

	// Check the activity record and the reply used to build the link.
	if ( ! empty( $topic_id ) && bbp_get_password_required_id( $activity_ids ) ) {
		/** This filter is documented in src/includes/topics/template.php. */
		return apply_filters( 'bbp_get_topic_freshness_link', '-', $topic_id, '', '', '' );
	}

	$link_url   = bbp_get_topic_last_reply_url( $topic_id );
	$title      = bbp_get_topic_last_reply_title( $topic_id );
	$time_since = bbp_get_topic_last_active_time( $topic_id );

	if ( ! empty( $time_since ) ) {
		$anchor = '<a href="' . esc_url( $link_url ) . '" title="' . esc_attr( $title ) . '">' . esc_html( $time_since ) . '</a>';
	} else {
		$anchor = esc_html__( 'No Replies', 'bbpress' );
	}

	/**
	 * Filters the link to the most recent activity inside a topic, complete.
	 *
	 * @since 2.0.0 bbPress (r2627)
	 *
	 * @param string $anchor Topic freshness link.
	 * @param int $topic_id Topic id.
	 * @param string $time_since Human-readable time since activity.
	 * @param string $link_url Link URL.
	 * @param string $title Title.
	 */
	return apply_filters( 'bbp_get_topic_freshness_link', $anchor, $topic_id, $time_since, $link_url, $title );
}

/**
 * Output the replies link of the topic.
 *
 * @since 2.0.0 bbPress (r2740)
 *
 * @param int $topic_id Optional. Topic id.
 * @return void
 */
function bbp_topic_replies_link( $topic_id = 0 ) {
	echo bbp_get_topic_replies_link( $topic_id );
}

/**
 * Return the replies link of the topic.
 *
 * @since 2.0.0 bbPress (r2740)
 *
 * @param int $topic_id Optional. Topic id.
 * @return string Reply count as a link or plain text.
 */
function bbp_get_topic_replies_link( $topic_id = 0 ) {
	$topic_id = bbp_get_topic_id( $topic_id );
	$link     = bbp_get_topic_permalink( $topic_id );
	/* translators: %s: Number of replies */
	$replies  = sprintf( _n( '%s reply', '%s replies', bbp_get_topic_reply_count( $topic_id, true ), 'bbpress' ), bbp_get_topic_reply_count( $topic_id, false ) );

	// First link never has view=all
	$retval = bbp_get_view_all_for_object( 'edit_others_replies', $topic_id )
		? "<a href='" . esc_url( bbp_remove_view_all( $link ) ) . "'>" . esc_html( $replies ) . '</a>'
		: $replies;

	// Any deleted replies?
	$deleted_int = bbp_get_topic_reply_count_hidden( $topic_id, true  );

	// This topic has hidden replies
	if ( ! empty( $deleted_int ) && ( current_user_can( 'edit_others_replies' ) || current_user_can( 'moderate', $topic_id ) ) ) {

		// Hidden replies
		$deleted_num = bbp_get_topic_reply_count_hidden( $topic_id, false );
		/* translators: %s: Number of hidden replies */
		$extra       = ' ' . sprintf( _n( '(+%s hidden)', '(+%s hidden)', $deleted_int, 'bbpress' ), $deleted_num );

		// Hidden link
		$retval .= ! bbp_get_view_all_for_object( 'edit_others_replies', $topic_id )
			? " <a href='" . esc_url( bbp_add_view_all( $link, true ) ) . "'>" . esc_html( $extra ) . '</a>'
			: " {$extra}";
	}

	/**
	 * Filters the replies link of the topic.
	 *
	 * @since 2.0.0 bbPress (r2740)
	 *
	 * @param string $retval Reply count as a link or plain text.
	 * @param int $topic_id Topic id.
	 */
	return apply_filters( 'bbp_get_topic_replies_link', $retval, $topic_id );
}

/**
 * Output total reply count of a topic.
 *
 * @since 2.0.0 bbPress (r2485)
 *
 * @param int     $topic_id Optional. Topic id.
 * @param boolean $integer Optional. Whether or not to format the result.
 * @return void
 */
function bbp_topic_reply_count( $topic_id = 0, $integer = false ) {
	echo bbp_get_topic_reply_count( $topic_id, $integer );
}

/**
 * Return total reply count of a topic.
 *
 * @since 2.0.0 bbPress (r2485)
 *
 * @param int     $topic_id Optional. Topic id.
 * @param boolean $integer Optional. Whether or not to format the result.
 * @return ($integer is true ? int : int|string) Topic reply count, formatted by default.
 */
function bbp_get_topic_reply_count( $topic_id = 0, $integer = false ) {
	$topic_id = bbp_get_topic_id( $topic_id );
	$replies  = (int) get_post_meta( $topic_id, '_bbp_reply_count', true );
	$filter   = ( true === $integer )
		? 'bbp_get_topic_reply_count_int'
		: 'bbp_get_topic_reply_count';

	/**
	 * Filters a topic's reply count.
	 *
	 * The hook name is `bbp_get_topic_reply_count_int` for integer output and
	 * `bbp_get_topic_reply_count` for formatted output.
	 *
	 * @since 2.0.0 bbPress (r2485)
	 * @since 2.2.0 bbPress (r4258) Added the integer-count filter.
	 *
	 * @param int|string $replies Unformatted count, or a formatted count after filtering.
	 * @param int $topic_id Topic id.
	 */
	return apply_filters( $filter, $replies, $topic_id );
}

/**
 * Output total post count of a topic.
 *
 * @since 2.0.0 bbPress (r2955)
 *
 * @param int     $topic_id Optional. Topic id.
 * @param boolean $integer Optional. Whether or not to format the result.
 * @return void
 */
function bbp_topic_post_count( $topic_id = 0, $integer = false ) {
	echo bbp_get_topic_post_count( $topic_id, $integer );
}

/**
 * Return total post count of a topic.
 *
 * @since 2.0.0 bbPress (r2955)
 *
 * @param int     $topic_id Optional. Topic id.
 * @param boolean $integer Optional. Whether or not to format the result.
 * @return int|string Topic post count, formatted by default.
 */
function bbp_get_topic_post_count( $topic_id = 0, $integer = false ) {
	$topic_id = bbp_get_topic_id( $topic_id );
	$replies  = ( (int) get_post_meta( $topic_id, '_bbp_reply_count', true ) ) + 1;
	$filter   = ( true === $integer )
		? 'bbp_get_topic_post_count_int'
		: 'bbp_get_topic_post_count';

	/**
	 * Filters a topic's total post count.
	 *
	 * The hook name is `bbp_get_topic_post_count_int` for integer output and
	 * `bbp_get_topic_post_count` for formatted output.
	 *
	 * @since 2.0.0 bbPress (r2955)
	 * @since 2.2.0 bbPress (r4258) Added the integer-count filter.
	 *
	 * @param int|string $replies Unformatted count, or a formatted count after filtering.
	 * @param int $topic_id Topic id.
	 */
	return apply_filters( $filter, $replies, $topic_id );
}

/**
 * Output total hidden reply count of a topic (hidden includes trashed and
 * spammed replies).
 *
 * @since 2.0.0 bbPress (r3349)
 *
 * @param int     $topic_id Optional. Topic id.
 * @param boolean $integer Optional. Whether or not to format the result.
 * @return void
 */
function bbp_topic_reply_count_hidden( $topic_id = 0, $integer = false ) {
	echo bbp_get_topic_reply_count_hidden( $topic_id, $integer );
}

/**
 * Return total hidden reply count of a topic (hidden includes trashed
 * and spammed replies).
 *
 * @since 2.0.0 bbPress (r3349)
 *
 * @param int     $topic_id Optional. Topic id.
 * @param boolean $integer Optional. Whether or not to format the result.
 * @return ($integer is true ? int : int|string) Topic hidden reply count, formatted by default.
 */
function bbp_get_topic_reply_count_hidden( $topic_id = 0, $integer = false ) {
	$topic_id = bbp_get_topic_id( $topic_id );
	$replies  = (int) get_post_meta( $topic_id, '_bbp_reply_count_hidden', true );
	$filter   = ( true === $integer )
		? 'bbp_get_topic_reply_count_hidden_int'
		: 'bbp_get_topic_reply_count_hidden';

	/**
	 * Filters a topic's hidden reply count.
	 *
	 * The hook name is `bbp_get_topic_reply_count_hidden_int` for integer output and
	 * `bbp_get_topic_reply_count_hidden` for formatted output.
	 *
	 * @since 2.0.0 bbPress (r3349)
	 * @since 2.2.0 bbPress (r4258) Added the integer-count filter.
	 *
	 * @param int|string $replies Unformatted count, or a formatted count after filtering.
	 * @param int $topic_id Topic id.
	 */
	return apply_filters( $filter, $replies, $topic_id );
}

/**
 * Output total voice count of a topic.
 *
 * @since 2.0.0 bbPress (r2567)
 *
 * @param int  $topic_id Optional. Topic ID.
 * @param bool $integer  Whether to return an integer.
 * @return void
 */
function bbp_topic_voice_count( $topic_id = 0, $integer = false ) {
	echo bbp_get_topic_voice_count( $topic_id, $integer );
}

/**
 * Return total voice count of a topic.
 *
 * @since 2.0.0 bbPress (r2567)
 *
 * @param int  $topic_id Optional. Topic ID.
 * @param bool $integer  Whether to return an integer.
 * @return ($integer is true ? int : int|string) Topic voice count, formatted by default.
 */
function bbp_get_topic_voice_count( $topic_id = 0, $integer = false ) {
	$topic_id = bbp_get_topic_id( $topic_id );
	$voices   = (int) get_post_meta( $topic_id, '_bbp_voice_count', true );
	$filter   = ( true === $integer )
		? 'bbp_get_topic_voice_count_int'
		: 'bbp_get_topic_voice_count';

	/**
	 * Filters a topic's participant count.
	 *
	 * The hook name is `bbp_get_topic_voice_count_int` for integer output and
	 * `bbp_get_topic_voice_count` for formatted output.
	 *
	 * @since 2.0.0 bbPress (r2567)
	 * @since 2.2.0 bbPress (r4258) Added the integer-count filter.
	 *
	 * @param int|string $voices Unformatted count, or a formatted count after filtering.
	 * @param int $topic_id Topic id.
	 */
	return apply_filters( $filter, $voices, $topic_id );
}

/**
 * Output the tags of a topic.
 *
 * @since 2.0.0 bbPress (r2583)
 *
 * @param int   $topic_id Optional. Topic id.
 * @param array<string, mixed> $args See {@link bbp_get_topic_tag_list()}.
 * @return void
 */
function bbp_topic_tag_list( $topic_id = 0, $args = array() ) {
	echo bbp_get_topic_tag_list( $topic_id, $args );
}

/**
 * Return the tags of a topic.
 *
 * @since 2.0.0 bbPress (r2583)
 *
 * @param int   $topic_id Optional. Topic id.
 * @param array<string, mixed> $args Supported arguments.
 *  - before: Before the tag list
 *  - sep: Tag separator
 *  - after: After the tag list.
 * @return string Tag list of the topic.
 */
function bbp_get_topic_tag_list( $topic_id = 0, $args = array() ) {

	// Bail if topic-tags are off
	if ( ! bbp_allow_topic_tags() ) {
		return '';
	}

	// Parse arguments against default values
	$r = bbp_parse_args(
		$args,
		array(
			'before' => '<div class="bbp-topic-tags"><p>' . esc_html__( 'Tagged:', 'bbpress' ) . '&nbsp;',
			'sep'    => ', ',
			'after'  => '</p></div>',
			'none'   => ''
		),
		'get_topic_tag_list'
	);

	$before_html = bbp_get_string_arg( $r, 'before' );
	$after_html  = bbp_get_string_arg( $r, 'after' );
	$sep_html    = bbp_get_string_arg( $r, 'sep' );
	$none_html   = bbp_get_string_arg( $r, 'none' );

	$topic_id = bbp_get_topic_id( $topic_id );

	// Topic is spammed, so display pre-spam terms
	if ( bbp_is_topic_spam( $topic_id ) ) {

		// Get pre-spam terms
		$terms = get_post_meta( $topic_id, '_bbp_spam_topic_tags', true );

		// If terms exist, implode them and compile the return value
		if ( ! empty( $terms ) ) {
			$terms = $before_html . implode( $sep_html, $terms ) . $after_html;
		}

	// Topic is not spam so display a clickable term list
	} else {
		$terms = get_the_term_list( $topic_id, bbp_get_topic_tag_tax_id(), $before_html, $sep_html, $after_html );
	}

	// No terms so return none string
	if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
		$retval = $terms;
	} else {
		$retval = $none_html;
	}

	return $retval;
}

/**
 * Output the row class of a topic.
 *
 * @since 2.0.0 bbPress (r2678)
 *
 * @param int   $topic_id Optional. Topic ID.
 * @param array<array-key, string> $classes  Extra classes for the topic.
 * @return void
 */
function bbp_topic_class( $topic_id = 0, $classes = array() ) {
	echo bbp_get_topic_class( $topic_id, $classes );
}

/**
 * Return the row class of a topic.
 *
 * @since 2.0.0 bbPress (r2678)
 *
 * @param int   $topic_id Optional. Topic ID.
 * @param array<array-key, string> $classes  Extra classes for the topic.
 * @return string Row class of a topic.
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
	$forum_moderator = ( ! empty( $author_id ) && ! empty( $forum_id ) && bbp_is_user_forum_moderator( $author_id, $forum_id ) )
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

	/**
	 * Filters the CSS classes on a topic row.
	 *
	 * @since 2.0.0 bbPress (r2678)
	 *
	 * @param array<array-key, string> $post_classes The CSS classes on a topic row.
	 * @param int $topic_id Topic id.
	 * @param array<array-key, string> $classes Classes.
	 */
	$new_classes  = apply_filters( 'bbp_get_topic_class', $post_classes, $topic_id, $classes );

	// Return
	return 'class="' . implode( ' ', $new_classes ) . '"';
}

/** Topic Admin Links *********************************************************/

/**
 * Get the topic named by parsed link arguments.
 *
 * @since 2.7.0 bbPress (r8634)
 * @access private
 *
 * @param array<string, mixed> $args Parsed link arguments.
 * @return WP_Post|null Topic, or null for an invalid ID.
 */
function bbp_get_topic_for_link( $args ) {
	$topic = isset( $args['id'] ) ? $args['id'] : 0;
	if ( empty( $topic ) ) {
		return bbp_get_topic( 0 );
	}
	if ( $topic instanceof WP_Post ) {
		return bbp_get_topic( $topic );
	}
	return is_numeric( $topic ) ? bbp_get_topic( (int) $topic ) : null;
}

/**
 * Output admin links for topic.
 *
 * @param array<string, mixed> $args See {@link bbp_get_topic_admin_links()}.
 * @return void
 */
function bbp_topic_admin_links( $args = array() ) {
	echo bbp_get_topic_admin_links( $args );
}

/**
 * Return admin links for topic.
 *
 * Move topic functionality is handled by the edit topic page.
 *
 * @param array<string, mixed> $args Supported arguments.
 *  - id: Optional. Topic id
 *  - before: Before the links
 *  - after: After the links
 *  - sep: Links separator
 *  - links: Topic admin links array.
 * @return string Topic admin links.
 */
function bbp_get_topic_admin_links( $args = array() ) {

	// Parse arguments against default values
	$r = bbp_parse_args(
		$args,
		array(
			'id'     => bbp_get_topic_id(),
			'before' => '<span class="bbp-admin-links">',
			'after'  => '</span>',
			'sep'    => ' | ',
			'links'  => array()
		),
		'get_topic_admin_links'
	);

	// Normalize filtered link arguments before building links
	$before = bbp_get_string_arg( $r, 'before' );
	$after  = bbp_get_string_arg( $r, 'after' );
	$sep    = bbp_get_string_arg( $r, 'sep' );

	if ( empty( $r['links'] ) ) {

		/**
		 * Filters the available topic admin links.
		 *
		 * @since 2.0.0 bbPress (r2590)
		 *
		 * @param array $value The available topic admin links.
		 * @param mixed $id Id from the parsed arguments.
		 */
		$r['links'] = apply_filters(
			'bbp_topic_admin_links',
			array(
				'edit'    => bbp_get_topic_edit_link   ( $r ),
				'merge'   => bbp_get_topic_merge_link  ( $r ),
				'close'   => bbp_get_topic_close_link  ( $r ),
				'stick'   => bbp_get_topic_stick_link  ( $r ),
				'trash'   => bbp_get_topic_trash_link  ( $r ),
				'spam'    => bbp_get_topic_spam_link   ( $r ),
				'approve' => bbp_get_topic_approve_link( $r ),
				'reply'   => bbp_get_topic_reply_link  ( $r )
			),
			$r['id']
		);
	}

	// See if links need to be unset
	$topic        = bbp_get_topic_for_link( $r );
	$topic_id     = $topic ? $topic->ID : 0;
	$topic_status = bbp_get_topic_status( $topic_id );
	$links        = bbp_get_admin_link_array( $r['links'] );
	if ( in_array( $topic_status, bbp_get_non_public_topic_statuses(), true ) ) {

		// Close link shouldn't be visible on trashed/spammed/pending topics
		unset( $links['close'] );

		// Spam link shouldn't be visible on trashed topics
		if ( bbp_get_trash_status_id() === $topic_status ) {
			unset( $links['spam'] );

		// Trash link shouldn't be visible on spam topics
		} elseif ( bbp_get_spam_status_id() === $topic_status ) {
			unset( $links['trash'] );
		}
	}

	// Process the admin links
	$r['links'] = $links;
	$retval = $before . implode( $sep, array_filter( $links ) ) . $after;

	/**
	 * Filters the admin links for topic.
	 *
	 * @since 2.0.0 bbPress (r2590)
	 *
	 * @param string $retval Topic admin links.
	 * @param array $r Arguments.
	 * @param array<string, mixed> $args Arguments passed to the function.
	 */
	return apply_filters( 'bbp_get_topic_admin_links', $retval, $r, $args );
}

/**
 * Output the edit link of the topic.
 *
 * @since 2.0.0 bbPress (r2727)
 *
 * @param array<string, mixed> $args See {@link bbp_get_topic_edit_link()}.
 * @return void
 */
function bbp_topic_edit_link( $args = array() ) {
	echo bbp_get_topic_edit_link( $args );
}

/**
 * Return the edit link of the topic.
 *
 * @since 2.0.0 bbPress (r2727)
 *
 * @param array<string, mixed> $args Supported arguments.
 *  - id: Optional. Topic id
 *  - link_before: Before the link
 *  - link_after: After the link
 *  - edit_text: Edit text.
 * @return string|null Topic edit link, or null when unavailable.
 */
function bbp_get_topic_edit_link( $args = array() ) {

	// Parse arguments against default values
	$r = bbp_parse_args(
		$args,
		array(
			'id'           => 0,
			'link_before'  => '',
			'link_after'   => '',
			'edit_text'    => esc_html__( 'Edit', 'bbpress' )
		),
		'get_topic_edit_link'
	);

	// Normalize filtered link arguments before building links
	$link_before = bbp_get_string_arg( $r, 'link_before' );
	$link_after  = bbp_get_string_arg( $r, 'link_after' );
	$edit_html   = bbp_get_string_arg( $r, 'edit_text' );

	// Get the topic
	$topic = bbp_get_topic_for_link( $r );
	if ( empty( $topic ) ) {
		return null;
	}

	// Bypass check if user has caps
	if ( ! current_user_can( 'edit_others_topics' ) && ! current_user_can( 'moderate', $topic->ID ) ) {

		// User cannot edit or it is past the lock time
		if ( ! current_user_can( 'edit_topic', $topic->ID ) || bbp_past_edit_lock( $topic->post_date_gmt ) ) {
			return null;
		}
	}

	// Get uri
	$uri = bbp_get_topic_edit_url( $topic->ID );

	// Bail if no uri
	if ( empty( $uri ) ) {
		return null;
	}

	$retval = $link_before . '<a href="' . esc_url( $uri ) . '" class="bbp-topic-edit-link">' . $edit_html . '</a>' . $link_after;

	/**
	 * Filters the edit link of the topic.
	 *
	 * @since 2.0.0 bbPress (r2727)
	 *
	 * @param string|null $retval Topic edit link, or null when unavailable.
	 * @param array $r Arguments.
	 * @param array<string, mixed> $args Arguments passed to the function.
	 */
	return apply_filters( 'bbp_get_topic_edit_link', $retval, $r, $args );
}

/**
 * Output URL to the topic edit page.
 *
 * @since 2.0.0 bbPress (r2753)
 *
 * @param int $topic_id Optional. Topic id.
 * @return void
 */
function bbp_topic_edit_url( $topic_id = 0 ) {
	$url = bbp_get_topic_edit_url( $topic_id ) ?? '';
	echo esc_url( $url );
}

/**
 * Return URL to the topic edit page.
 *
 * @since 2.0.0 bbPress (r2753)
 *
 * @param int $topic_id Optional. Topic id.
 * @return string|null Topic edit URL, or null when unavailable.
 */
function bbp_get_topic_edit_url( $topic_id = 0 ) {

	$topic = bbp_get_topic( $topic_id );
	if ( empty( $topic ) ) {
		return null;
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
		$url = add_query_arg(
			array(
				bbp_get_topic_post_type() => $topic->post_name,
				bbp_get_edit_rewrite_id() => '1'
			),
			$topic_link
		);
	}

	// Maybe add view=all
	$url = bbp_add_view_all( $url );

	/**
	 * Filters the URL to edit a topic.
	 *
	 * @since 2.0.0 bbPress (r2753)
	 *
	 * @param string|null $url Topic edit URL, or null when unavailable.
	 * @param int $topic_id Topic id.
	 */
	return apply_filters( 'bbp_get_topic_edit_url', $url, $topic_id );
}

/**
 * Output the trash link of the topic.
 *
 * @since 2.0.0 bbPress (r2727)
 *
 * @param array<string, mixed> $args See {@link bbp_get_topic_trash_link()}.
 * @return void
 */
function bbp_topic_trash_link( $args = array() ) {
	echo bbp_get_topic_trash_link( $args );
}

/**
 * Return the trash link of the topic.
 *
 * @since 2.0.0 bbPress (r2727)
 *
 * @param array<string, mixed> $args Supported arguments.
 *  - id: Optional. Topic id
 *  - link_before: Before the link
 *  - link_after: After the link
 *  - sep: Links separator
 *  - trash_text: Trash text
 *  - restore_text: Restore text
 *  - delete_text: Delete text.
 * @return string|null Topic trash link, or null when unavailable.
 */
function bbp_get_topic_trash_link( $args = array() ) {

	// Parse arguments against default values
	$r = bbp_parse_args(
		$args,
		array(
			'id'           => 0,
			'link_before'  => '',
			'link_after'   => '',
			'sep'          => ' | ',
			'trash_text'   => esc_html__( 'Trash',   'bbpress' ),
			'restore_text' => esc_html__( 'Restore', 'bbpress' ),
			'delete_text'  => esc_html__( 'Delete',  'bbpress' )
		),
		'get_topic_trash_link'
	);

	// Normalize filtered link arguments before building links
	$link_before  = bbp_get_string_arg( $r, 'link_before' );
	$link_after   = bbp_get_string_arg( $r, 'link_after' );
	$sep          = bbp_get_string_arg( $r, 'sep' );
	$trash_html   = bbp_get_string_arg( $r, 'trash_text' );
	$restore_html = bbp_get_string_arg( $r, 'restore_text' );
	$delete_html  = bbp_get_string_arg( $r, 'delete_text' );

	// Get topic
	$topic = bbp_get_topic_for_link( $r );

	// Bail if no topic or current user cannot delete
	if ( empty( $topic ) || ! current_user_can( 'delete_topic', $topic->ID ) ) {
		return null;
	}

	$actions    = array();
	$trash_days = bbp_get_trash_days( bbp_get_topic_post_type() );

	if ( bbp_is_topic_trash( $topic->ID ) ) {
		$action_url = add_query_arg(
			array(
				'action'     => 'bbp_toggle_topic_trash',
				'sub_action' => 'untrash',
				'topic_id'   => $topic->ID
			)
		);
		$action    = 'untrash-' . $topic->post_type . '_' . $topic->ID;
		$nonce_url = wp_nonce_url( $action_url, $action );

		$actions['untrash'] = '<a title="' . esc_attr__( 'Restore this item from the Trash', 'bbpress' ) . '" href="' . esc_url( $nonce_url ) . '" class="bbp-topic-restore-link">' . $restore_html . '</a>';
	} elseif ( ! empty( $trash_days ) ) {
		$action_url = add_query_arg(
			array(
				'action'     => 'bbp_toggle_topic_trash',
				'sub_action' => 'trash',
				'topic_id'   => $topic->ID
			)
		);
		$action    = 'trash-' . $topic->post_type . '_' . $topic->ID;
		$nonce_url = wp_nonce_url( $action_url, $action );

		$actions['trash']   = '<a title="' . esc_attr__( 'Move this item to the Trash',      'bbpress' ) . '" href="' . esc_url( $nonce_url ) . '" class="bbp-topic-trash-link">'   . $trash_html   . '</a>';
	}

	if ( bbp_is_topic_trash( $topic->ID ) || empty( $trash_days ) ) {
		$action_url = add_query_arg(
			array(
				'action'     => 'bbp_toggle_topic_trash',
				'sub_action' => 'delete',
				'topic_id'   => $topic->ID
			)
		);
		$action    = 'delete-' . $topic->post_type . '_' . $topic->ID;
		$nonce_url = wp_nonce_url( $action_url, $action );

		$actions['delete']  = '<a title="' . esc_attr__( 'Delete this item permanently',     'bbpress' ) . '" href="' . esc_url( $nonce_url ) . '" onclick="return confirm(\'' . esc_js( esc_html__( 'Are you sure you want to delete that permanently?', 'bbpress' ) ) . '\' );" class="bbp-topic-delete-link">' . $delete_html . '</a>';
	}

	// Process the admin links
	$retval = $link_before . implode( $sep, $actions ) . $link_after;

	/**
	 * Filters the trash link of the topic.
	 *
	 * @since 2.0.0 bbPress (r2727)
	 *
	 * @param string|null $retval Topic trash link, or null when unavailable.
	 * @param array $r Arguments.
	 * @param array<string, mixed> $args Arguments passed to the function.
	 */
	return apply_filters( 'bbp_get_topic_trash_link', $retval, $r, $args );
}

/**
 * Output the close link of the topic.
 *
 * @since 2.0.0 bbPress (r2727)
 *
 * @param array<string, mixed> $args See {@link bbp_get_topic_close_link()}.
 * @return void
 */
function bbp_topic_close_link( $args = array() ) {
	echo bbp_get_topic_close_link( $args );
}

/**
 * Return the close link of the topic.
 *
 * @since 2.0.0 bbPress (r2727)
 *
 * @param array<string, mixed> $args Supported arguments.
 *  - id: Optional. Topic id
 *  - link_before: Before the link
 *  - link_after: After the link
 *  - close_text: Close text
 *  - open_text: Open text.
 * @return string|null Topic close link, or null when unavailable.
 */
function bbp_get_topic_close_link( $args = array() ) {

	// Parse arguments against default values
	$r = bbp_parse_args(
		$args,
		array(
			'id'          => 0,
			'link_before' => '',
			'link_after'  => '',
			'sep'         => ' | ',
			'close_text'  => esc_html_x( 'Close', 'Close the topic', 'bbpress' ),
			'open_text'   => esc_html_x( 'Open',  'Open the topic', 'bbpress' )
		),
		'get_topic_close_link'
	);

	// Normalize filtered link arguments before building links
	$link_before = bbp_get_string_arg( $r, 'link_before' );
	$link_after  = bbp_get_string_arg( $r, 'link_after' );
	$close_html  = bbp_get_string_arg( $r, 'close_text' );
	$open_html   = bbp_get_string_arg( $r, 'open_text' );

	// Get topic
	$topic = bbp_get_topic_for_link( $r );

	// Bail if no topic or current user cannot moderate
	if ( empty( $topic ) || ! current_user_can( 'moderate', $topic->ID ) ) {
		return null;
	}

	$display = bbp_is_topic_open( $topic->ID ) ? $close_html : $open_html;
	$uri     = add_query_arg(
		array(
			'action'   => 'bbp_toggle_topic_close',
			'topic_id' => $topic->ID
		)
	);
	$uri     = wp_nonce_url( $uri, 'close-topic_' . $topic->ID );
	$retval  = $link_before . '<a href="' . esc_url( $uri ) . '" class="bbp-topic-close-link">' . $display . '</a>' . $link_after;

	/**
	 * Filters the close link of the topic.
	 *
	 * @since 2.0.0 bbPress (r2727)
	 *
	 * @param string|null $retval Topic close link, or null when unavailable.
	 * @param array $r Arguments.
	 * @param array<string, mixed> $args Arguments passed to the function.
	 */
	return apply_filters( 'bbp_get_topic_close_link', $retval, $r, $args );
}

/**
 * Output the approve link of the topic.
 *
 * @since 2.6.0 bbPress (r5504)
 *
 * @param array<string, mixed> $args See {@link bbp_get_topic_approve_link()}.
 * @return void
 */
function bbp_topic_approve_link( $args = array() ) {
	echo bbp_get_topic_approve_link( $args );
}

/**
 * Return the approve link of the topic.
 *
 * @since 2.6.0 bbPress (r5504)
 *
 * @param array<string, mixed> $args Supported arguments.
 *  - id: Optional. Topic id
 *  - link_before: Before the link
 *  - link_after: After the link
 *  - sep: Separator between links
 *  - approve_text: Approve text
 *  - unapprove_text: Unapprove text.
 * @return string|null Topic approve link, or null when unavailable.
 */
function bbp_get_topic_approve_link( $args = array() ) {

	// Parse arguments against default values
	$r = bbp_parse_args(
		$args,
		array(
			'id'             => 0,
			'link_before'    => '',
			'link_after'     => '',
			'sep'            => ' | ',
			'approve_text'   => esc_html_x( 'Approve',   'Approve the topic', 'bbpress' ),
			'unapprove_text' => esc_html_x( 'Unapprove', 'Unapprove the topic', 'bbpress' )
		),
		'get_topic_approve_link'
	);

	// Normalize filtered link arguments before building links
	$link_before    = bbp_get_string_arg( $r, 'link_before' );
	$link_after     = bbp_get_string_arg( $r, 'link_after' );
	$approve_html   = bbp_get_string_arg( $r, 'approve_text' );
	$unapprove_html = bbp_get_string_arg( $r, 'unapprove_text' );

	// Get topic
	$topic = bbp_get_topic_for_link( $r );

	// Bail if no topic or current user cannot moderate
	if ( empty( $topic ) || ! current_user_can( 'moderate', $topic->ID ) ) {
		return null;
	}

	$display = bbp_is_topic_pending( $topic->ID ) ? $approve_html : $unapprove_html;
	$uri     = add_query_arg(
		array(
			'action'   => 'bbp_toggle_topic_approve',
			'topic_id' => $topic->ID
		)
	);
	$uri     = wp_nonce_url( $uri, 'approve-topic_' . $topic->ID );
	$retval  = $link_before . '<a href="' . esc_url( $uri ) . '" class="bbp-topic-approve-link">' . $display . '</a>' . $link_after;

	/**
	 * Filters the approve link of the topic.
	 *
	 * @since 2.6.0 bbPress (r5504)
	 *
	 * @param string|null $retval Topic approve link, or null when unavailable.
	 * @param array $r Arguments.
	 * @param array<string, mixed> $args Arguments passed to the function.
	 */
	return apply_filters( 'bbp_get_topic_approve_link', $retval, $r, $args );
}

/**
 * Output the stick link of the topic.
 *
 * @since 2.0.0 bbPress (r2754)
 *
 * @param array<string, mixed> $args See {@link bbp_get_topic_stick_link()}.
 * @return void
 */
function bbp_topic_stick_link( $args = array() ) {
	echo bbp_get_topic_stick_link( $args );
}

/**
 * Return the stick link of the topic.
 *
 * @since 2.0.0 bbPress (r2754)
 *
 * @param array<string, mixed> $args Supported arguments.
 *  - id: Optional. Topic id
 *  - link_before: Before the link
 *  - link_after: After the link
 *  - stick_text: Stick text
 *  - unstick_text: Unstick text
 *  - super_text: Stick to front text.
 *
 * @return string|null Topic stick link, or null when unavailable.
 */
function bbp_get_topic_stick_link( $args = array() ) {

	// Parse arguments against default values
	$r = bbp_parse_args(
		$args,
		array(
			'id'           => 0,
			'link_before'  => '',
			'link_after'   => '',
			'stick_text'   => esc_html__( 'Stick',      'bbpress' ),
			'unstick_text' => esc_html__( 'Unstick',    'bbpress' ),
			'super_text'   => esc_html__( '(to front)', 'bbpress' ),
		),
		'get_topic_stick_link'
	);

	// Normalize filtered link arguments before building links
	$link_before  = bbp_get_string_arg( $r, 'link_before' );
	$link_after   = bbp_get_string_arg( $r, 'link_after' );
	$stick_html   = bbp_get_string_arg( $r, 'stick_text' );
	$unstick_html = bbp_get_string_arg( $r, 'unstick_text' );
	$super_html   = bbp_get_string_arg( $r, 'super_text' );

	// Get topic
	$topic = bbp_get_topic_for_link( $r );

	// Bail if no topic or current user cannot moderate
	if ( empty( $topic ) || ! current_user_can( 'moderate', $topic->ID ) ) {
		return null;
	}

	// Per-forum moderators cannot unstick a topic from every forum
	if ( bbp_is_topic_super_sticky( $topic->ID ) && ! current_user_can( 'moderate' ) ) {
		return null;
	}

	$is_sticky = bbp_is_topic_sticky( $topic->ID );

	$stick_uri = add_query_arg(
		array(
			'action'   => 'bbp_toggle_topic_stick',
			'topic_id' => $topic->ID
		)
	);
	$stick_uri = wp_nonce_url( $stick_uri, 'stick-topic_' . $topic->ID );

	$stick_display = ( true === $is_sticky )
		? $unstick_html
		: $stick_html;
	$stick_display = '<a href="' . esc_url( $stick_uri ) . '" class="bbp-topic-sticky-link">' . $stick_display . '</a>';

	if ( empty( $is_sticky ) && current_user_can( 'moderate' ) ) {
		$super_uri = add_query_arg(
			array(
				'action'   => 'bbp_toggle_topic_stick',
				'topic_id' => $topic->ID,
				'super'    => 1
			)
		);
		$super_uri = wp_nonce_url( $super_uri, 'stick-topic_' . $topic->ID );

		$super_display = ' <a href="' . esc_url( $super_uri ) . '" class="bbp-topic-super-sticky-link">' . $super_html . '</a>';
	} else {
		$super_display = '';
	}

	// Combine the HTML into 1 string
	$retval = $link_before . $stick_display . $super_display . $link_after;

	/**
	 * Filters the stick link of the topic.
	 *
	 * @since 2.0.0 bbPress (r2754)
	 *
	 * @param string|null $retval Topic stick link, or null when unavailable.
	 * @param array $r Arguments.
	 * @param array<string, mixed> $args Arguments passed to the function.
	 */
	return apply_filters( 'bbp_get_topic_stick_link', $retval, $r, $args );
}

/**
 * Output the merge link of the topic.
 *
 * @since 2.0.0 bbPress (r2756)
 *
 * @param array<string, mixed> $args Topic merge link arguments.
 * @return void
 */
function bbp_topic_merge_link( $args = array() ) {
	echo bbp_get_topic_merge_link( $args );
}

/**
 * Return the merge link of the topic.
 *
 * @since 2.0.0 bbPress (r2756)
 *
 * @param array<string, mixed> $args Supported arguments.
 *  - id: Optional. Topic id
 *  - link_before: Before the link
 *  - link_after: After the link
 *  - merge_text: Merge text.
 *
 * @return string|null Topic merge link, or null when unavailable.
 */
function bbp_get_topic_merge_link( $args = array() ) {

	// Parse arguments against default values
	$r = bbp_parse_args(
		$args,
		array(
			'id'           => 0,
			'link_before'  => '',
			'link_after'   => '',
			'merge_text'   => esc_html__( 'Merge', 'bbpress' ),
		),
		'get_topic_merge_link'
	);

	// Normalize filtered link arguments before building links
	$link_before = bbp_get_string_arg( $r, 'link_before' );
	$link_after  = bbp_get_string_arg( $r, 'link_after' );
	$merge_html  = bbp_get_string_arg( $r, 'merge_text' );

	// Get topic
	$topic = bbp_get_topic_for_link( $r );

	// Bail if no topic or current user cannot moderate and edit it
	if ( empty( $topic ) || ! bbp_current_user_can_access_topic_moderation( $topic->ID ) ) {
		return null;
	}

	$query  = array( 'action' => 'bbp-merge-topic' );
	$uri    = add_query_arg( $query, bbp_get_topic_edit_url( $topic->ID ) );
	$retval = $link_before . '<a href="' . esc_url( $uri ) . '" class="bbp-topic-merge-link">' . $merge_html . '</a>' . $link_after;

	/**
	 * Filters the merge link of the topic.
	 *
	 * @since 2.0.0 bbPress (r2756)
	 *
	 * @param string|null $retval Topic merge link, or null when unavailable.
	 * @param array $r Arguments.
	 * @param array<string, mixed> $args Arguments passed to the function.
	 */
	return apply_filters( 'bbp_get_topic_merge_link', $retval, $r, $args );
}

/**
 * Output the spam link of the topic.
 *
 * @since 2.0.0 bbPress (r2727)
 *
 * @param array<string, mixed> $args See {@link bbp_get_topic_spam_link()}.
 * @return void
 */
function bbp_topic_spam_link( $args = array() ) {
	echo bbp_get_topic_spam_link( $args );
}

/**
 * Return the spam link of the topic.
 *
 * @since 2.0.0 bbPress (r2727)
 *
 * @param array<string, mixed> $args Supported arguments.
 *  - id: Optional. Topic id
 *  - link_before: Before the link
 *  - link_after: After the link
 *  - spam_text: Spam text
 *  - unspam_text: Unspam text.
 *
 * @return string|null Topic spam link, or null when unavailable.
 */
function bbp_get_topic_spam_link( $args = array() ) {

	// Parse arguments against default values
	$r = bbp_parse_args(
		$args,
		array(
			'id'           => 0,
			'link_before'  => '',
			'link_after'   => '',
			'sep'          => ' | ',
			'spam_text'    => esc_html__( 'Spam',   'bbpress' ),
			'unspam_text'  => esc_html__( 'Unspam', 'bbpress' )
		),
		'get_topic_spam_link'
	);

	// Normalize filtered link arguments before building links
	$link_before = bbp_get_string_arg( $r, 'link_before' );
	$link_after  = bbp_get_string_arg( $r, 'link_after' );
	$spam_html   = bbp_get_string_arg( $r, 'spam_text' );
	$unspam_html = bbp_get_string_arg( $r, 'unspam_text' );

	$topic = bbp_get_topic_for_link( $r );

	if ( empty( $topic ) || ! current_user_can( 'moderate', $topic->ID ) ) {
		return null;
	}

	$display = bbp_is_topic_spam( $topic->ID )
		? $unspam_html
		: $spam_html;

	$uri = add_query_arg(
		array(
			'action'   => 'bbp_toggle_topic_spam',
			'topic_id' => $topic->ID
		)
	);

	$uri    = wp_nonce_url( $uri, 'spam-topic_' . $topic->ID );
	$retval = $link_before . '<a href="' . esc_url( $uri ) . '" class="bbp-topic-spam-link">' . $display . '</a>' . $link_after;

	/**
	 * Filters the spam link of the topic.
	 *
	 * @since 2.0.0 bbPress (r2727)
	 *
	 * @param string|null $retval Topic spam link, or null when unavailable.
	 * @param array $r Arguments.
	 * @param array<string, mixed> $args Arguments passed to the function.
	 */
	return apply_filters( 'bbp_get_topic_spam_link', $retval, $r, $args );
}

/**
 * Output the link to go directly to the reply form.
 *
 * @since 2.4.0 bbPress (r4966)
 *
 * @param array<string, mixed> $args Topic reply link arguments.
 * @return void
 */
function bbp_topic_reply_link( $args = array() ) {
	echo bbp_get_topic_reply_link( $args );
}

/**
 * Return the link to go directly to the reply form.
 *
 * @since 2.4.0 bbPress (r4966)
 *
 * @param array<string, mixed> $args Arguments.
 *
 * @return string|null Link for a reply to a topic, or null when unavailable.
 */
function bbp_get_topic_reply_link( $args = array() ) {

	// Parse arguments against default values
	$r = bbp_parse_args(
		$args,
		array(
			'id'          => 0,
			'link_before' => '',
			'link_after'  => '',
			'reply_text'  => esc_html_x( 'Reply', 'verb', 'bbpress' ),
			'add_below'   => 'post',
			'respond_id'  => 'new-reply-' . bbp_get_topic_id(),
		),
		'get_topic_reply_link'
	);

	// Normalize filtered link arguments before building links
	$link_before = bbp_get_string_arg( $r, 'link_before' );
	$link_after  = bbp_get_string_arg( $r, 'link_after' );
	$reply_html  = bbp_get_string_arg( $r, 'reply_text' );

	// Get the topic to use it's ID and post_parent
	$topic = bbp_get_topic_for_link( $r );

	// Bail if no topic or user cannot reply
	if ( empty( $topic ) || bbp_is_single_reply() || ! bbp_current_user_can_access_create_reply_form() ) {
		return null;
	}

	// Only add onclick if replies are threaded
	$onclick = bbp_thread_replies()
		? ' onclick="return addReply.cancelForm();"'
		: '';

	// Add $uri to the array, to be passed through the filter
	$r['uri'] = remove_query_arg( array( 'bbp_reply_to', '_wpnonce' ) ) . '#new-post';
	$retval   = $link_before . '<a role="button" href="' . esc_url( $r['uri'] ) . '" class="bbp-topic-reply-link"' . $onclick . '>' . $reply_html . '</a>' . $link_after;

	/**
	 * Filters the link to go directly to the reply form.
	 *
	 * @since 2.4.0 bbPress (r4966)
	 *
	 * @param string|null $retval Link for a reply to a topic, or null when unavailable.
	 * @param array $r Arguments.
	 * @param array<string, mixed> $args Arguments.
	 */
	return apply_filters( 'bbp_get_topic_reply_link', $retval, $r, $args );
}

/** Topic Pagination **********************************************************/

/**
 * Return the base URL used inside of pagination links.
 *
 * @since 2.6.0 bbPress (r6402)
 *
 * @param int $forum_id Forum id.
 * @return string|false Pagination base, or false when its URL is invalid.
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

		// Any single post (for shortcodes, ahead of shortcodeables below)
		} elseif ( is_singular() ) {
			$base = get_permalink();

		// View
		} elseif ( bbp_is_single_view() ) {
			$base = bbp_get_view_url();

		// Topic tag
		} elseif ( bbp_is_topic_tag() ) {
			$base = bbp_get_topic_tag_link();

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
		$base = bbp_validate_url( $base );
		if ( false !== $base ) {
			$base = trailingslashit( $base ) . user_trailingslashit( bbp_get_paged_slug() . '/%#%/' );
		}

	// Unpretty pagination
	} else {
		$base = add_query_arg( 'paged', '%#%' );
	}

	/**
	 * Filters the base URL used inside of pagination links.
	 *
	 * @since 2.6.0 bbPress (r6402)
	 *
	 * @param string|false $base Pagination base, or false when its URL is invalid.
	 * @param int $forum_id Forum id.
	 */
	return apply_filters( 'bbp_get_topics_pagination_base', $base, $forum_id );
}

/**
 * Output the pagination count.
 *
 * The results are unescaped by design, to allow them to be filtered freely via
 * the 'bbp_get_forum_pagination_count' filter.
 *
 * @since 2.0.0 bbPress (r2574)
 *
 * @return void
 */
function bbp_forum_pagination_count() {
	echo bbp_get_forum_pagination_count();
}

/**
 * Return the pagination count.
 *
 * @since 2.0.0 bbPress (r2574)
 *
 * @return string Forum Pagination count.
 */
function bbp_get_forum_pagination_count() {
	$bbp = bbpress();

	// Define local variable(s)
	$retstr = '';

	// Topic query exists
	if ( ! empty( $bbp->topic_query ) ) {

		// Set pagination values
		$count_int = intval( $bbp->topic_query->post_count );
		$page      = $bbp->topic_query->query_vars['bbp_topic_page'] ?? array();
		if ( ! empty( $page ) ) {
			$start_num = $page['from'];
			$to_int    = $page['to'];
			$total_int = $page['total'];
		} else {
			$start_num = intval( ( $bbp->topic_query->paged - 1 ) * $bbp->topic_query->posts_per_page ) + 1;
			$total_int = ! empty( $bbp->topic_query->found_posts )
				? (int) $bbp->topic_query->found_posts
				: $count_int;
			$to_int = min( $start_num + $bbp->topic_query->posts_per_page - 1, $bbp->topic_query->found_posts );
		}

		// Format numbers for display
		$count_num = bbp_number_format( $count_int );
		$from_num  = bbp_number_format( $start_num );
		$total     = bbp_number_format( $total_int );
		$to_num = bbp_number_format( $to_int );

		// Several topics in a forum with a single page
		if ( empty( $to_num ) ) {
			/* translators: %1$s: Total number of topics */
			$retstr = sprintf( _n( 'Viewing %1$s topic', 'Viewing %1$s topics', $total_int, 'bbpress' ), $total );

		// Several topics in a forum with several pages
		} else {
			$retstr = sprintf( _n( 'Viewing topic %2$s (of %4$s total)', 'Viewing %1$s topics - %2$s through %3$s (of %4$s total)', $total_int, 'bbpress' ), $count_num, $from_num, $to_num, $total );  //phpcs:ignore
		}

		// Escape results of _n()
		$retstr = esc_html( $retstr );
	}

	/**
	 * Filters the pagination count.
	 *
	 * @since 2.0.0 bbPress (r2753)
	 *
	 * @param string $retstr Forum Pagination count.
	 */
	return apply_filters( 'bbp_get_forum_pagination_count', $retstr );
}

/**
 * Output pagination links.
 *
 * @since 2.0.0 bbPress (r2574)
 *
 * @return void
 */
function bbp_forum_pagination_links() {
	echo bbp_get_forum_pagination_links();
}

/**
 * Return pagination links.
 *
 * @since 2.0.0 bbPress (r2574)
 *
 * @return string|false Forum pagination links, or false when unavailable.
 */
function bbp_get_forum_pagination_links() {
	$bbp = bbpress();

	if ( empty( $bbp->topic_query ) ) {
		return false;
	}

	/**
	 * Filters the pagination links.
	 *
	 * @since 2.0.0 bbPress (r2740)
	 *
	 * @param mixed $pagination_links Forum pagination HTML, a list of links, or null.
	 */
	return apply_filters( 'bbp_get_forum_pagination_links', $bbp->topic_query->pagination_links );
}

/**
 * Displays topic notices.
 *
 * @since 2.0.0 bbPress (r2744)
 *
 * @return void
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

	/**
	 * Filters a notice shown above a topic.
	 *
	 * @since 2.0.0 bbPress (r2744)
	 *
	 * @param string $notice_text A notice shown above a topic.
	 * @param string $topic_status Topic status.
	 * @param int $topic_id Topic id.
	 */
	$notice_text = apply_filters( 'bbp_topic_notices', $notice_text, $topic_status, bbp_get_topic_id() );
	if ( empty( $notice_text ) ) {
		return;
	}

	bbp_add_error( 'topic_notice', $notice_text, 'message' );
}

/**
 * Displays topic type select box (normal/sticky/super sticky).
 *
 * @since 2.0.0 bbPress (r2784)
 *
 * @deprecated 2.4.0 bbPress (r5059)
 *
 * @param array<string, mixed> $args Supported dropdown arguments.
 *  - select_id: Select id. Defaults to bbp_stick_topic
 *  - tab: Deprecated. Tabindex
 *  - topic_id: Topic id
 *  - selected: Override the selected option.
 *
 * @return void
 */
function bbp_topic_type_select( $args = array() ) {
	echo bbp_get_form_topic_type_dropdown( $args );
}

/**
 * Displays topic type select box (normal/sticky/super sticky).
 *
 * @since 2.4.0 bbPress (r5060)
 *
 * @param array<string, mixed> $args Supported dropdown arguments.
 *  - select_id: Select id. Defaults to bbp_stick_topic
 *  - tab: Deprecated. Tabindex
 *  - topic_id: Topic id
 *  - selected: Override the selected option.
 *
 * @return void
 */
function bbp_form_topic_type_dropdown( $args = array() ) {
	echo bbp_get_form_topic_type_dropdown( $args );
}

/**
 * Returns topic type select box (normal/sticky/super sticky).
 *
 * @since 2.4.0 bbPress (r5060)
 *
 * @param array<string, mixed> $args Supported dropdown arguments.
 *  - select_id: Select id. Defaults to bbp_stick_topic
 *  - tab: Deprecated. Tabindex
 *  - topic_id: Topic id
 *  - selected: Override the selected option.
 * @return string Topic type dropdown markup, or an empty string when unavailable.
 */
function bbp_get_form_topic_type_dropdown( $args = array() ) {

	// Parse arguments against default values
	$r = bbp_parse_args(
		$args,
		array(
			'select_id'    => 'bbp_stick_topic',
			'select_class' => 'bbp_dropdown',
			'tab'          => false,
			'topic_id'     => 0,
			'selected'     => false
		),
		'topic_type_select'
	);

	// Use valid field names and IDs from the parsed arguments
	$select_id    = bbp_get_string_arg( $r, 'select_id' );
	$select_class = bbp_get_string_arg( $r, 'select_class' );
	$topic_arg    = is_numeric( $r['topic_id'] ) ? $r['topic_id'] : 0;
	$topic_id     = (int) $topic_arg;

	// No specific selected value passed
	if ( empty( $r['selected'] ) ) {

		// Post value is passed
		if ( bbp_is_topic_form_post_request() && isset( $_POST[ $select_id ] ) ) {
			$r['selected'] = sanitize_key( $_POST[ $select_id ] );

		// Edit topic
		} elseif ( bbp_is_single_topic() || bbp_is_topic_edit() ) {

			// Get current topic id
			$topic_id = bbp_get_topic_id( $topic_arg );
			$r['topic_id'] = $topic_id;

			// Topic is super sticky
			if ( bbp_is_topic_super_sticky( $topic_id ) ) {
				$r['selected'] = 'super';

			// Topic is sticky or normal
			} else {
				$r['selected'] = bbp_is_topic_sticky( $topic_id, false )
					? 'stick'
					: 'unstick';
			}
		}
	}

	// Per-forum moderators cannot change a super sticky or make one
	$topic_types = bbp_get_topic_types( $topic_id );
	if ( ! current_user_can( 'moderate' ) ) {
		if ( ! empty( $topic_id ) && bbp_is_topic_super_sticky( $topic_id ) ) {
			/** This filter is documented in src/includes/topics/template.php. */
			return apply_filters( 'bbp_get_form_topic_type_dropdown', '', $r, $args );
		}
		unset( $topic_types['super'] );
	}

	// Start an output buffer, we'll finish it after the select loop
	ob_start(); ?>

	<select name="<?php echo esc_attr( $select_id ); ?>" id="<?php echo esc_attr( $select_id ); ?>_select" class="<?php echo esc_attr( $select_class ); ?>"<?php bbp_tab_index_attribute( $r['tab'] ); ?>>

		<?php foreach ( $topic_types as $key => $label ) : ?>

			<option value="<?php echo esc_attr( $key ); ?>"<?php selected( $key, $r['selected'] ); ?>><?php echo esc_html( $label ); ?></option>

		<?php endforeach; ?>

	</select>

	<?php

	/**
	 * Filters the topic type select box (normal/sticky/super sticky).
	 *
	 * @since 2.4.0 bbPress (r5060)
	 *
	 * @param mixed $value Topic type dropdown markup, or an empty string when unavailable.
	 * @param array $r Arguments.
	 * @param array<string, mixed> $args Arguments passed to the function.
	 */
	return apply_filters( 'bbp_get_form_topic_type_dropdown', ob_get_clean(), $r, $args );
}

/**
 * Output value topic status dropdown.
 *
 * @since 2.4.0 bbPress (r5060)
 *
 * @param array<string, mixed> $args Supported dropdown arguments.
 *  - select_id: Select id. Defaults to bbp_topic_status
 *  - tab: Deprecated. Tabindex
 *  - topic_id: Topic id
 *  - selected: Override the selected option.
 *
 * @return void
 */
function bbp_form_topic_status_dropdown( $args = array() ) {
	echo bbp_get_form_topic_status_dropdown( $args );
}

/**
 * Returns topic status dropdown.
 *
 * This dropdown is only intended to be seen by users with the 'moderate'
 * capability. Because of this, no additional capability checks are performed
 * within this function to check available topic statuses.
 *
 * @since 2.4.0 bbPress (r5060)
 *
 * @param array<string, mixed> $args Supported dropdown arguments.
 *  - select_id: Select id. Defaults to bbp_topic_status
 *  - tab: Deprecated. Tabindex
 *  - topic_id: Topic id
 *  - selected: Override the selected option.
 * @return string Topic status dropdown markup.
 */
function bbp_get_form_topic_status_dropdown( $args = array() ) {

	// Parse arguments against default values
	$r = bbp_parse_args(
		$args,
		array(
			'select_id'    => 'bbp_topic_status',
			'select_class' => 'bbp_dropdown',
			'tab'          => false,
			'topic_id'     => 0,
			'selected'     => false
		),
		'topic_open_close_select'
	);

	// Use valid field names and IDs from the parsed arguments
	$select_id    = bbp_get_string_arg( $r, 'select_id' );
	$select_class = bbp_get_string_arg( $r, 'select_class' );
	$topic_arg    = is_numeric( $r['topic_id'] ) ? $r['topic_id'] : 0;
	$topic_id     = (int) $topic_arg;

	// No specific selected value passed
	if ( empty( $r['selected'] ) ) {

		// Post value is passed
		if ( bbp_is_topic_form_post_request() && isset( $_POST[ $select_id ] ) ) {
			$r['selected'] = sanitize_key( $_POST[ $select_id ] );

		// Edit topic
		} elseif ( bbp_is_topic_edit() ) {
			$topic_id = bbp_get_topic_id( $topic_arg );
			$r['topic_id'] = $topic_id;
			$r['selected'] = bbp_get_topic_status( $topic_id );

		// New topic
		} else {
			$r['selected'] = bbp_get_public_status_id();
		}
	}

	// Start an output buffer, we'll finish it after the select loop
	ob_start(); ?>

	<select name="<?php echo esc_attr( $select_id ); ?>" id="<?php echo esc_attr( $select_id ); ?>_select" class="<?php echo esc_attr( $select_class ); ?>"<?php bbp_tab_index_attribute( $r['tab'] ); ?>>

		<?php foreach ( bbp_get_topic_statuses( $topic_id ) as $key => $label ) : ?>

			<option value="<?php echo esc_attr( $key ); ?>"<?php selected( $key, $r['selected'] ); ?>><?php echo esc_html( $label ); ?></option>

		<?php endforeach; ?>

	</select>

	<?php

	/**
	 * Filters the topic status dropdown.
	 *
	 * @since 2.4.0 bbPress (r5060)
	 *
	 * @param mixed $value Topic status dropdown markup.
	 * @param array $r Arguments.
	 * @param array<string, mixed> $args Arguments passed to the function.
	 */
	return apply_filters( 'bbp_get_form_topic_status_dropdown', ob_get_clean(), $r, $args );
}

/** Single Topic **************************************************************/

/**
 * Output a fancy description of the current topic, including total topics,
 * total replies, and last activity.
 *
 * @since 2.0.0 bbPress (r2872)
 *
 * @param array<string, mixed> $args See {@link bbp_get_single_topic_description()}.
 * @return void
 */
function bbp_single_topic_description( $args = array() ) {
	echo bbp_get_single_topic_description( $args );
}

/**
 * Return a fancy description of the current topic, including total topics,
 * total replies, and last activity.
 *
 * @since 2.0.0 bbPress (r2872)
 *
 * @param array<string, mixed> $args Supported arguments.
 *  - topic_id: Topic id
 *  - before: Before the text
 *  - after: After the text
 *  - size: Size of the avatar.
 * @return string Filtered topic description.
 */
function bbp_get_single_topic_description( $args = array() ) {

	// Parse arguments against default values
	$r = bbp_parse_args(
		$args,
		array(
			'topic_id'  => 0,
			'before'    => '<div class="bbp-template-notice info"><ul><li class="bbp-topic-description">',
			'after'     => '</li></ul></div>',
			'size'      => 14
		),
		'get_single_topic_description'
	);

	/**
	 * Filters whether to build the single topic description.
	 *
	 * @since 2.7.0 bbPress (r7970)
	 *
	 * @param bool  $use_description Whether to build the description.
	 * @param array $r               Parsed arguments.
	 * @param array<string, mixed> $args            Original arguments.
	 */
	if ( ! apply_filters( 'bbp_use_single_topic_description', true, $r, $args ) ) {
		/** This filter is documented in src/includes/topics/template.php. */
		return apply_filters( 'bbp_get_single_topic_description', '', $r, $args );
	}

	// Validate topic_id
	$topic_id = bbp_get_topic_id( $r['topic_id'] );

	// Unhook the 'view all' query var adder
	remove_filter( 'bbp_get_topic_permalink', 'bbp_add_view_all' );

	// Build the topic description
	$vc_int      = bbp_get_topic_voice_count   ( $topic_id, true  );
	$reply_int   = bbp_get_topic_reply_count   ( $topic_id, true  );
	$voice_count = bbp_get_topic_voice_count   ( $topic_id, false );
	$reply_count = bbp_get_topic_replies_link  ( $topic_id        );
	$time_since  = bbp_get_topic_freshness_link( $topic_id        );

	// Singular/Plural
	/* translators: %s: Number of voices/participants */
	$voice_count = sprintf( _n( '%s voice', '%s voices', $vc_int, 'bbpress' ), $voice_count );

	// Last active ID (could be from reply or topic author)
	$last_active = bbp_get_topic_last_active_id( $topic_id );

	// Topic is in the trash
	if ( bbp_get_topic_status( $topic_id ) === bbp_get_trash_status_id() ) {
		$retstr = esc_html__( 'This topic is in the trash.', 'bbpress' );

	// Topic is spam
	} elseif ( bbp_get_topic_status( $topic_id ) === bbp_get_spam_status_id() ) {
		$retstr = esc_html__( 'This topic is marked as spam.', 'bbpress' );

	// Topic has activity
	} elseif ( ! empty( $last_active ) ) {

		// Last-updated-by should always exist if last-active does
		$last_updated_by = bbp_get_author_link(
			array(
				'post_id' => $last_active,
				'size'    => $r['size']
			)
		);

		// Voice count is non-zero (registered users engaged)
		if ( ! empty( $vc_int ) ) {
			/* translators: Plural form is selected by reply count. 1: Reply count, 2: Voice count, 3: Last updated time, 4: Last updated by */
			$topic_description = _n(
				'This topic has %1$s, %2$s, and was last updated %3$s by %4$s.',
				'This topic has %1$s, %2$s, and was last updated %3$s by %4$s.',
				$reply_int,
				'bbpress'
			);
			$retstr = sprintf(
				esc_html( $topic_description ),
				$reply_count,
				$voice_count,
				$time_since,
				$last_updated_by
			);

		// Voice count is zero (anonymous users only)
		} else {
			/* translators: 1: Last updated time, 2: Last updated by */
			$retstr = sprintf( esc_html__( 'This topic was last updated %1$s by %2$s.', 'bbpress' ), $time_since, $last_updated_by );
		}

	// Topic has no replies
	} elseif ( ! empty( $vc_int ) && ! empty( $reply_count ) ) {
		/* translators: Plural form is selected by reply count. 1: Voice count, 2: Reply count */
		$topic_description = _n( 'This topic has %1$s and %2$s.', 'This topic has %1$s and %2$s.', $reply_int, 'bbpress' );
		$retstr = sprintf(
			esc_html( $topic_description ),
			$voice_count,
			$reply_count
		);

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
	$before_html = bbp_get_string_arg( $r, 'before' );
	$after_html  = bbp_get_string_arg( $r, 'after' );
	$retstr     = $before_html . $retstr . $after_html;

	/**
	 * Filters the description of a single topic.
	 *
	 * @since 2.0.0 bbPress (r2872)
	 *
	 * @param string $retstr Filtered topic description.
	 * @param array $r Arguments.
	 * @param array<string, mixed> $args Arguments passed to the function.
	 */
	return apply_filters( 'bbp_get_single_topic_description', $retstr, $r, $args );
}

/** Topic Tags ****************************************************************/

/**
 * Output the unique id of the topic tag taxonomy.
 *
 * @since 2.0.0 bbPress (r3348)
 *
 * @return void
 */
function bbp_topic_tag_tax_id() {
	echo bbp_get_topic_tag_tax_id();
}

/**
 * Return the unique id of the topic tag taxonomy.
 *
 * @since 2.0.0 bbPress (r3348)
 *
 * @return string The unique topic tag taxonomy.
 */
function bbp_get_topic_tag_tax_id() {

	/**
	 * Filters the topic tag taxonomy ID.
	 *
	 * @since 2.0.0 bbPress (r3348)
	 *
	 * @param string $topic_tag_tax_id The unique topic tag taxonomy.
	 */
	return apply_filters( 'bbp_get_topic_tag_tax_id', bbpress()->topic_tag_tax_id );
}

/**
 * Return array of labels used by the topic-tag taxonomy.
 *
 * @since 2.5.0 bbPress (r5130)
 *
 * @return array<string, string>
 */
function bbp_get_topic_tag_tax_labels() {

	/**
	 * Filters the array of labels used by the topic-tag taxonomy.
	 *
	 * @since 2.5.0 bbPress (r5130)
	 *
	 * @param array<string, string> $value The array of labels used by the topic-tag taxonomy.
	 */
	return (array) apply_filters(
		'bbp_get_topic_tag_tax_labels',
		array(
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
		)
	);
}

/**
 * Return an array of topic-tag taxonomy rewrite settings.
 *
 * @since 2.5.0 bbPress (r5130)
 *
 * @return array<string, mixed>
 */
function bbp_get_topic_tag_tax_rewrite() {

	/**
	 * Filters an array of topic-tag taxonomy rewrite settings.
	 *
	 * @since 2.5.0 bbPress (r5130)
	 *
	 * @param array<string, mixed> $value An array of topic-tag taxonomy rewrite settings.
	 */
	return (array) apply_filters(
		'bbp_get_topic_tag_tax_rewrite',
		array(
			'slug'       => bbp_get_topic_tag_tax_slug(),
			'with_front' => false
		)
	);
}

/**
 * Output the id of the current tag.
 *
 * @since 2.0.0 bbPress (r3109)
 *
 * @param string $tag Optional. Topic tag slug.
 * @return void
 */
function bbp_topic_tag_id( $tag = '' ) {
	echo bbp_get_topic_tag_id( $tag );
}

/**
 * Return the id of the current tag.
 *
 * @since 2.0.0 bbPress (r3109)
 *
 * @param string $tag Optional. Topic tag slug.
 * @return int Topic tag ID.
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

	/**
	 * Filters the id of the current tag.
	 *
	 * @since 2.0.0 bbPress (r3109)
	 *
	 * @param int $retval Topic tag ID.
	 * @param string $tag Topic tag slug.
	 * @param object|false|null $term Resolved topic tag or queried object, or false or null.
	 */
	return (int) apply_filters( 'bbp_get_topic_tag_id', (int) $retval, $tag, $term );
}

/**
 * Output the name of the current tag.
 *
 * @since 2.0.0 bbPress (r3109)
 *
 * @param string $tag Optional. Topic tag slug.
 * @return void
 */
function bbp_topic_tag_name( $tag = '' ) {
	echo bbp_get_topic_tag_name( $tag );
}

/**
 * Return the name of the current tag.
 *
 * @since 2.0.0 bbPress (r3109)
 *
 * @param string $tag Optional. Topic tag slug.
 * @return string Topic tag name.
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

	/**
	 * Filters the name of the current tag.
	 *
	 * @since 2.0.0 bbPress (r3109)
	 *
	 * @param string $retval Topic tag name.
	 * @param string $tag Topic tag slug.
	 * @param object|false|null $term Resolved topic tag or queried object, or false or null.
	 */
	return apply_filters( 'bbp_get_topic_tag_name', $retval, $tag, $term );
}

/**
 * Output the slug of the current tag.
 *
 * @since 2.0.0 bbPress (r3109)
 *
 * @param string $tag Optional. Topic tag slug.
 * @return void
 */
function bbp_topic_tag_slug( $tag = '' ) {
	echo bbp_get_topic_tag_slug( $tag );
}

/**
 * Return the slug of the current tag.
 *
 * @since 2.0.0 bbPress (r3109)
 *
 * @param string $tag Optional. Topic tag slug.
 * @return string Topic tag slug.
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

	/**
	 * Filters the slug of the current tag.
	 *
	 * @since 2.0.0 bbPress (r3109)
	 *
	 * @param string $retval Topic tag slug.
	 * @param string $tag Topic tag slug.
	 * @param object|false|null $term Resolved topic tag or queried object, or false or null.
	 */
	return apply_filters( 'bbp_get_topic_tag_slug', $retval, $tag, $term );
}

/**
 * Output the link of the current tag.
 *
 * @since 2.0.0 bbPress (r3348)
 *
 * @param string $tag Optional. Topic tag slug.
 * @return void
 */
function bbp_topic_tag_link( $tag = '' ) {
	echo esc_url( bbp_get_topic_tag_link( $tag ) );
}

/**
 * Return the link of the current tag.
 *
 * @since 2.0.0 bbPress (r3348)
 *
 * @param string $tag Optional. Topic tag slug.
 * @return string Topic tag URL.
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
	$retval = ( $term instanceof WP_Term )
		? get_term_link( $term, bbp_get_topic_tag_tax_id() )
		: '';
	if ( is_wp_error( $retval ) ) {
		$retval = '';
	}

	/**
	 * Filters the link of the current tag.
	 *
	 * @since 2.0.0 bbPress (r3348)
	 *
	 * @param string $retval Topic tag URL.
	 * @param string $tag Topic tag slug.
	 * @param object|false|null $term Resolved topic tag or queried object, or false or null.
	 */
	return apply_filters( 'bbp_get_topic_tag_link', $retval, $tag, $term );
}

/**
 * Output the topic-tag subscription link.
 *
 * @since 2.7.0 bbPress (r8335)
 *
 * @param array<string, mixed> $args See {@link bbp_get_topic_tag_subscription_link()}.
 * @return void
 */
function bbp_topic_tag_subscription_link( $args = array() ) {
	echo bbp_get_topic_tag_subscription_link( $args );
}

/**
 * Get the topic-tag subscription link.
 *
 * A custom wrapper for bbp_get_user_subscribe_link().
 *
 * @since 2.7.0 bbPress (r8335)
 *
 * @param array<string, mixed> $args Optional. Subscription-link arguments.
 * @return string|false Subscription link, or false when unavailable.
 */
function bbp_get_topic_tag_subscription_link( $args = array() ) {
	if ( bbp_is_subscriptions() ) {
		$user_id     = bbp_get_user_id( 0, true, true );
		$redirect_to = bbp_get_subscriptions_permalink( $user_id );
	} else {
		$user_id     = bbp_get_current_user_id();
		$redirect_to = '';
	}

	$r = bbp_parse_args(
		$args,
		array(
			'user_id'     => $user_id,
			'object_id'   => bbp_get_topic_tag_id(),
			'object_type' => 'term',
			'before'      => '',
			'after'       => '',
			'subscribe'   => esc_html__( 'Subscribe',   'bbpress' ),
			'unsubscribe' => esc_html__( 'Unsubscribe', 'bbpress' ),
			'redirect_to' => $redirect_to
		),
		'get_topic_tag_subscribe_link'
	);

	$retval = bbp_is_subscribable( $r['object_id'], $r['object_type'] )
		? bbp_get_user_subscribe_link( $r )
		: false;

	/**
	 * Filters the topic-tag subscription link.
	 *
	 * @since 2.7.0 bbPress (r8335)
	 *
	 * @param mixed $retval Subscription link, or false or null when unavailable.
	 * @param array $r Arguments.
	 * @param array<string, mixed> $args Subscription-link arguments.
	 */
	return apply_filters( 'bbp_get_topic_tag_subscribe_link', $retval, $r, $args );
}

/**
 * Output the link of the current tag.
 *
 * @since 2.0.0 bbPress (r3348)
 *
 * @param string $tag Optional. Topic tag slug.
 * @return void
 */
function bbp_topic_tag_edit_link( $tag = '' ) {
	echo esc_url( bbp_get_topic_tag_edit_link( $tag ) );
}

/**
 * Return the link of the current tag.
 *
 * @since 2.0.0 bbPress (r3348)
 *
 * @param string $tag Optional. Topic tag slug.
 * @return string Topic tag edit URL.
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
			? user_trailingslashit( trailingslashit( bbp_get_topic_tag_link( $tag ) ) . bbp_get_edit_slug() )
			: add_query_arg( array( bbp_get_edit_rewrite_id() => '1' ), bbp_get_topic_tag_link( $tag ) );

	// No link
	} else {
		$retval = '';
	}

	/**
	 * Filters the link of the current tag.
	 *
	 * @since 2.0.0 bbPress (r3348)
	 *
	 * @param string $retval Topic tag edit URL.
	 * @param string $tag Topic tag slug.
	 * @param object|false|null $term Resolved topic tag or queried object, or false or null.
	 */
	return apply_filters( 'bbp_get_topic_tag_edit_link', $retval, $tag, $term );
}

/**
 * Output the description of the current tag.
 *
 * @since 2.0.0 bbPress (r3109)
 *
 * @param array<string, mixed> $args Description arguments.
 * @return void
 */
function bbp_topic_tag_description( $args = array() ) {
	echo bbp_get_topic_tag_description( $args );
}

/**
 * Return the description of the current tag.
 *
 * @since 2.0.0 bbPress (r3109)
 *
 * @param array<string, mixed> $args before|after|tag.
 *
 * @return string Term Name.
 */
function bbp_get_topic_tag_description( $args = array() ) {

	// Parse arguments against default values
	$r = bbp_parse_args(
		$args,
		array(
			'before' => '<div class="bbp-topic-tag-description"><p>',
			'after'  => '</p></div>',
			'tag'    => ''
		),
		'get_topic_tag_description'
	);

	$before_html = bbp_get_string_arg( $r, 'before' );
	$after_html  = bbp_get_string_arg( $r, 'after' );

	// Get the term
	$tag = $r['tag'];
	if ( ! empty( $tag ) ) {
		$term = get_term_by( 'slug', $tag, bbp_get_topic_tag_tax_id() );
	} else {
		$tag = $r['tag'] = get_query_var( 'term' );
		$term = get_queried_object();
	}

	// Add before & after if description exists
	$retval = ! empty( $term->description )
		? $before_html . $term->description . $after_html
		: '';

	/**
	 * Filters the description of the current tag.
	 *
	 * @since 2.0.0 bbPress (r3109)
	 *
	 * @param string $retval Term Name.
	 * @param array $r Arguments.
	 * @param array<string, mixed> $args before|after|tag.
	 * @param string $tag Topic tag slug.
	 * @param object|false|null $term Resolved topic tag or queried object, or false or null.
	 */
	return apply_filters( 'bbp_get_topic_tag_description', $retval, $r, $args, $tag, $term );
}

/** Forms *********************************************************************/

/**
 * Output the value of topic title field.
 *
 * @since 2.0.0 bbPress (r2976)
 *
 * @return void
 */
function bbp_form_topic_title() {
	echo bbp_get_form_topic_title();
}

/**
 * Return the value of topic title field.
 *
 * @since 2.0.0 bbPress (r2976)
 *
 * @return string Value of topic title field.
 */
function bbp_get_form_topic_title() {

	// Get _POST data
	if ( bbp_is_topic_form_post_request() && isset( $_POST['bbp_topic_title'] ) ) {
		$topic_title = wp_unslash( $_POST['bbp_topic_title'] );

	// Get edit data
	} elseif ( bbp_is_topic_edit() ) {
		$topic_id    = bbp_get_topic_id();
		$topic_title = ! current_user_can( 'moderate', $topic_id ) && bbp_get_password_required_id( $topic_id )
			? ''
			: bbp_get_global_post_field( 'post_title', 'raw' );

	// No data
	} else {
		$topic_title = '';
	}

	/**
	 * Filters the value of topic title field.
	 *
	 * @since 2.0.0 bbPress (r2976)
	 *
	 * @param string $topic_title Value of topic title field.
	 */
	return apply_filters( 'bbp_get_form_topic_title', $topic_title );
}

/**
 * Output the value of topic content field.
 *
 * @since 2.0.0 bbPress (r2976)
 *
 * @return void
 */
function bbp_form_topic_content() {
	echo bbp_get_form_topic_content();
}

/**
 * Return the value of topic content field.
 *
 * @since 2.0.0 bbPress (r2976)
 *
 * @return string Value of topic content field.
 */
function bbp_get_form_topic_content() {

	// Get _POST data
	if ( bbp_is_topic_form_post_request() && isset( $_POST['bbp_topic_content'] ) ) {
		$topic_content = wp_unslash( $_POST['bbp_topic_content'] );

	// Get edit data
	} elseif ( bbp_is_topic_edit() ) {
		$topic_id      = bbp_get_topic_id();
		$topic_content = ! current_user_can( 'moderate', $topic_id ) && bbp_get_password_required_id( $topic_id )
			? ''
			: bbp_get_global_post_field( 'post_content', 'raw' );

	// No data
	} else {
		$topic_content = '';
	}

	/**
	 * Filters the value of topic content field.
	 *
	 * @since 2.0.0 bbPress (r2976)
	 *
	 * @param string $topic_content Value of topic content field.
	 */
	return apply_filters( 'bbp_get_form_topic_content', $topic_content );
}

/**
 * Allow topic rows to have administrative actions.
 *
 * @since 2.1.0 bbPress (r3654)
 *
 * @todo Links and filter.
 * @return void
 */
function bbp_topic_row_actions() {
	/**
	 * Fires while rendering topic row actions.
	 *
	 * @since 2.1.0 bbPress (r3654)
	 */
	do_action( 'bbp_topic_row_actions' );
}

/**
 * Output value of topic tags field.
 *
 * @since 2.0.0 bbPress (r2976)
 *
 * @return void
 */
function bbp_form_topic_tags() {
	echo bbp_get_form_topic_tags();
}

/**
 * Return value of topic tags field.
 *
 * @since 2.0.0 bbPress (r2976)
 *
 * @return string Value of topic tags field.
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

	/**
	 * Filters the value of topic tags field.
	 *
	 * @since 2.0.0 bbPress (r2976)
	 *
	 * @param string $topic_tags Value of topic tags field.
	 */
	return apply_filters( 'bbp_get_form_topic_tags', $topic_tags );
}

/**
 * Output value of topic forum.
 *
 * @since 2.0.0 bbPress (r2976)
 *
 * @return void
 */
function bbp_form_topic_forum() {
	echo bbp_get_form_topic_forum();
}

/**
 * Return value of topic forum.
 *
 * @since 2.0.0 bbPress (r2976)
 *
 * @return int Forum ID for the topic, or 0 when none is selected.
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

	/**
	 * Filters the value of topic forum.
	 *
	 * @since 2.0.0 bbPress (r2976)
	 *
	 * @param int $topic_forum Forum ID for the topic, or 0 when none is selected.
	 */
	return apply_filters( 'bbp_get_form_topic_forum', $topic_forum );
}

/**
 * Output checked value of topic subscription.
 *
 * @since 2.0.0 bbPress (r2976)
 *
 * @return void
 */
function bbp_form_topic_subscribed() {
	echo bbp_get_form_topic_subscribed();
}

/**
 * Return checked value of topic subscription.
 *
 * @since 2.0.0 bbPress (r2976)
 *
 * @return string Checked value of topic subscription.
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

	/**
	 * Filters the checked value of topic subscription.
	 *
	 * @since 2.0.0 bbPress (r2976)
	 *
	 * @param string $checked Checked value of topic subscription.
	 * @param bool $topic_subscribed Whether the topic is subscribed.
	 */
	return apply_filters( 'bbp_get_form_topic_subscribed', $checked, $topic_subscribed );
}

/**
 * Output checked value of topic log edit field.
 *
 * @since 2.0.0 bbPress (r2976)
 *
 * @return void
 */
function bbp_form_topic_log_edit() {
	echo bbp_get_form_topic_log_edit();
}

/**
 * Return checked value of topic log edit field.
 *
 * @since 2.0.0 bbPress (r2976)
 *
 * @return string Topic log edit checked value.
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

	/**
	 * Filters the checked value of topic log edit field.
	 *
	 * @since 2.0.0 bbPress (r2976)
	 *
	 * @param string $checked Topic log edit checked value.
	 * @param bool $topic_revision Whether to log the topic edit.
	 */
	return apply_filters( 'bbp_get_form_topic_log_edit', $checked, $topic_revision );
}

/**
 * Output the value of the topic edit reason.
 *
 * @since 2.0.0 bbPress (r2976)
 *
 * @return void
 */
function bbp_form_topic_edit_reason() {
	echo bbp_get_form_topic_edit_reason();
}

/**
 * Return the value of the topic edit reason.
 *
 * @since 2.0.0 bbPress (r2976)
 *
 * @return string Topic edit reason value.
 */
function bbp_get_form_topic_edit_reason() {

	// Get _POST data
	if ( bbp_is_topic_form_post_request() && isset( $_POST['bbp_topic_edit_reason'] ) ) {
		$topic_edit_reason = wp_unslash( $_POST['bbp_topic_edit_reason'] );

	// No data
	} else {
		$topic_edit_reason = '';
	}

	/**
	 * Filters the value of the topic edit reason.
	 *
	 * @since 2.0.0 bbPress (r2976)
	 *
	 * @param string $topic_edit_reason Topic edit reason value.
	 */
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
 * @return boolean True if is a post request with valid nonce.
 */
function bbp_is_topic_form_post_request() {

	// Bail if not a post request
	if ( ! bbp_is_post_request() ) {
		return false;
	}

	$action = bbp_get_post_action();

	// Bail if not a topic form action
	if ( ! bbp_is_post_action( array( 'bbp-new-topic', 'bbp-edit-topic' ) ) ) {
		return false;
	}

	// Creating a new topic
	if ( ( 'bbp-new-topic' === $action ) && bbp_verify_nonce_request( 'bbp-new-topic' ) ) {
		return true;
	}

	// Editing an existing topic
	if ( ( 'bbp-edit-topic' === $action ) && bbp_verify_nonce_request( 'bbp-edit-topic_' . bbp_get_topic_id() ) ) {
		return true;
	}

	return false;
}

/** Warning *******************************************************************/

/**
 * Should the topic-lock alert appear?
 *
 * @since 2.6.0 bbPress (r6344)
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

	/**
	 * Filters whether to show the topic lock alert.
	 *
	 * @since 2.6.0 bbPress (r6344)
	 *
	 * @param bool $retval Whether to show the topic lock alert.
	 * @param int $topic_id Topic id.
	 */
	return (bool) apply_filters( 'bbp_show_topic_lock_alert', $retval, $topic_id );
}

/**
 * Output the topic lock description.
 *
 * @since 2.6.0 bbPress (r6344)
 *
 * @param int $topic_id Optional. Topic id.
 * @return void
 */
function bbp_topic_lock_description( $topic_id = 0 ) {
	echo bbp_get_topic_lock_description( $topic_id );
}

/**
 * Return the topic lock description.
 *
 * @since 2.6.0 bbPress (r6344)
 *
 * @param int $topic_id Optional. Topic id.
 * @return string Topic lock description.
 */
function bbp_get_topic_lock_description( $topic_id = 0 ) {

	// Check if topic is edit locked
	$topic_id = bbp_get_topic_id( $topic_id );
	$user_id  = bbp_check_post_lock( $topic_id );
	$person   = empty( $user_id )
		? esc_html__( 'Nobody', 'bbpress' )
		: bbp_get_user_profile_link( $user_id );

	// Get the text
	/* translators: %1$s: Person who is editing (user profile link or "Nobody") */
	$text = sprintf( esc_html__( '%1$s is currently editing this topic.', 'bbpress' ), $person );

	/**
	 * Filters the topic lock description.
	 *
	 * @since 2.6.0 bbPress (r6344)
	 *
	 * @param string $text Topic lock description.
	 * @param int|false $user_id User id.
	 * @param int $topic_id Topic id.
	 */
	return apply_filters( 'bbp_get_topic_lock_description', $text, $user_id, $topic_id );
}
