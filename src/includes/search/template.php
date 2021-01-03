<?php

/**
 * bbPress Search Template Tags
 *
 * @package bbPress
 * @subpackage TemplateTags
 */

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

/** Search Loop Functions *****************************************************/

/**
 * The main search loop. WordPress does the heavy lifting.
 *
 * @since 2.3.0 bbPress (r4579)
 *
 * @param array $args All the arguments supported by {@link WP_Query}
 * @return object Multidimensional array of search information
 */
function bbp_has_search_results( $args = array() ) {

	/** Defaults **************************************************************/

	$default_search_terms = bbp_get_search_terms();
	$default_post_types   = bbp_get_post_types();

	// Default query args
	$default = array(
		'post_type'           => $default_post_types,        // Forums, topics, and replies
		'posts_per_page'      => bbp_get_replies_per_page(), // This many
		'paged'               => bbp_get_paged(),            // On this page
		'orderby'             => 'date',                     // Sorted by date
		'order'               => 'DESC',                     // Most recent first
		'ignore_sticky_posts' => true,                       // Stickies not supported,

		// Conditionally prime the cache for last active posts
		'update_post_family_cache' => true
	);

	// Only set 's' if search terms exist
	// https://bbpress.trac.wordpress.org/ticket/2607
	if ( false !== $default_search_terms ) {
		$default['s'] = $default_search_terms;
	}

	// What are the default allowed statuses (based on user caps)
	if ( bbp_get_view_all() ) {

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

	/** Setup *****************************************************************/

	// Parse arguments against default values
	$r = bbp_parse_args( $args, $default, 'has_search_results' );

	// Get bbPress
	$bbp = bbpress();

	// Only call the search query if 's' is not empty
	if ( ! empty( $r['s'] ) ) {
		$bbp->search_query = new WP_Query( $r );
	}

	// Maybe prime last active posts
	if ( ! empty( $r['update_post_family_cache'] ) ) {
		bbp_update_post_family_caches( $bbp->search_query->posts );
	}

	// Add pagination values to query object
	$bbp->search_query->posts_per_page = (int) $r['posts_per_page'];
	$bbp->search_query->paged          = (int) $r['paged'];

	// Never home, regardless of what parse_query says
	$bbp->search_query->is_home        = false;

	// Only add pagination is query returned results
	if ( ! empty( $bbp->search_query->found_posts ) && ! empty( $bbp->search_query->posts_per_page ) ) {

		// Total for pagination boundaries
		$total_pages = ( $bbp->search_query->posts_per_page === $bbp->search_query->found_posts )
			? 1
			: ceil( $bbp->search_query->found_posts / $bbp->search_query->posts_per_page );

		// Pagination settings with filter
		$bbp_search_pagination = apply_filters( 'bbp_search_results_pagination', array(
			'base'    => bbp_get_search_pagination_base(),
			'total'   => $total_pages,
			'current' => $bbp->search_query->paged
		) );

		// Add pagination to query object
		$bbp->search_query->pagination_links = bbp_paginate_links( $bbp_search_pagination );
	}

	// Filter & return
	return apply_filters( 'bbp_has_search_results', $bbp->search_query->have_posts(), $bbp->search_query );
}

/**
 * Whether there are more search results available in the loop
 *
 * @since 2.3.0 bbPress (r4579)
 *
 * @return object Search information
 */
function bbp_search_results() {

	// Put into variable to check against next
	$have_posts = bbpress()->search_query->have_posts();

	// Reset the post data when finished
	if ( empty( $have_posts ) ) {
		wp_reset_postdata();
	}

	return $have_posts;
}

/**
 * Loads up the current search result in the loop
 *
 * @since 2.3.0 bbPress (r4579)
 *
 * @return object Search information
 */
function bbp_the_search_result() {
	$search_result = bbpress()->search_query->the_post();

	// Reset each current (forum|topic|reply) id
	bbpress()->current_forum_id = bbp_get_forum_id();
	bbpress()->current_topic_id = bbp_get_topic_id();
	bbpress()->current_reply_id = bbp_get_reply_id();

	return $search_result;
}

/**
 * Output the search page title
 *
 * @since 2.3.0 bbPress (r4579)
 */
function bbp_search_title() {
	echo bbp_get_search_title();
}

	/**
	 * Get the search page title
	 *
	 * @since 2.3.0 bbPress (r4579)
	 */
	function bbp_get_search_title() {

		// Get search terms
		$search_terms = bbp_get_search_terms();

		// No search terms specified
		if ( empty( $search_terms ) ) {
			$title = esc_html__( 'Search', 'bbpress' );

		// Include search terms in title
		} else {
			$title = sprintf( esc_html__( "Search Results for '%s'", 'bbpress' ), esc_attr( $search_terms ) );
		}

		// Filter & return
		return apply_filters( 'bbp_get_search_title', $title, $search_terms );
	}

/**
 * Output the search url
 *
 * @since 2.3.0 bbPress (r4579)
 */
function bbp_search_url() {
	echo esc_url( bbp_get_search_url() );
}
	/**
	 * Return the search url
	 *
	 * @since 2.3.0 bbPress (r4579)
	 *
	 * @return string Search url
	 */
	function bbp_get_search_url() {

		// Pretty permalinks
		if ( bbp_use_pretty_urls() ) {

			// Run through home_url()
			$url = bbp_get_root_url() . bbp_get_search_slug();
			$url = user_trailingslashit( $url );
			$url = home_url( $url );

		// Unpretty permalinks
		} else {
			$url = add_query_arg( array(
				bbp_get_search_rewrite_id() => ''
			), home_url( '/' ) );
		}

		// Filter & return
		return apply_filters( 'bbp_get_search_url', $url );
	}

/**
 * Output the search results url
 *
 * @since 2.4.0 bbPress (r4928)
 */
function bbp_search_results_url() {
	echo esc_url( bbp_get_search_results_url() );
}
	/**
	 * Return the search url
	 *
	 * @since 2.4.0 bbPress (r4928)
	 *
	 * @return string Search url
	 */
	function bbp_get_search_results_url() {

		// Get the search terms
		$search_terms = bbp_get_search_terms();

		// Pretty permalinks
		if ( bbp_use_pretty_urls() ) {

			// Root search URL
			$url = bbp_get_root_url() . bbp_get_search_slug();

			// Append search terms
			if ( ! empty( $search_terms ) ) {
				$url = trailingslashit( $url ) . urlencode( $search_terms );
			}

			// Run through home_url()
			$url = user_trailingslashit( $url );
			$url = home_url( $url );

		// Unpretty permalinks
		} else {
			$url = add_query_arg( array(
				bbp_get_search_rewrite_id() => urlencode( $search_terms )
			), home_url( '/' ) );
		}

		// Filter & return
		return apply_filters( 'bbp_get_search_results_url', $url );
	}

/**
 * Output the search terms
 *
 * @since 2.3.0 bbPress (r4579)
 *
 * @param string $search_terms Optional. Search terms
 */
function bbp_search_terms( $search_terms = '' ) {
	echo esc_attr( bbp_get_search_terms( $search_terms ) );
}

	/**
	 * Get the search terms
	 *
	 * @since 2.3.0 bbPress (r4579)
	 *
	 * If search terms are supplied, those are used. Otherwise check the
	 * search rewrite id query var.
	 *
	 * @param string $passed_terms Optional. Search terms
	 * @return bool|string Search terms on success, false on failure
	 */
	function bbp_get_search_terms( $passed_terms = '' ) {

		// Sanitize terms if they were passed in
		if ( ! empty( $passed_terms ) ) {
			$search_terms = sanitize_title( $passed_terms );

		// Use query variable if not
		} else {

			// Global
			$search_terms = get_query_var( bbp_get_search_rewrite_id(), null );

			// Searching globally
			if ( ! is_null( $search_terms )  ) {
				$search_terms = wp_unslash( $search_terms );

			// Other searches
			} else {

				// Get known search type IDs
				$types = bbp_get_search_type_ids();

				// Filterable, so make sure types exist
				if ( ! empty( $types ) ) {

					// Loop through types
					foreach ( $types as $type ) {

						// Look for search terms
						$terms = bbp_sanitize_search_request( $type );

						// Skip if no terms
						if ( empty( $terms ) ) {
							continue;
						}

						// Set terms if not empty
						$search_terms = $terms;
					}
				}
			}
		}

		// Trim whitespace & decode if non-empty string, or set to false
		$search_terms = ! empty( $search_terms ) && is_string( $search_terms )
			? urldecode( trim( $search_terms ) )
			: false;

		// Filter & return
		return apply_filters( 'bbp_get_search_terms', $search_terms, $passed_terms );
	}

/** Pagination ****************************************************************/

/**
 * Return the base URL used inside of pagination links
 *
 * @since 2.6.0 bbPress (r6679)
 *
 * @return string
 */
function bbp_get_search_pagination_base() {

	// If pretty permalinks are enabled, make our pagination pretty
	if ( bbp_use_pretty_urls() ) {

		// Any single post (for shortcodes)
		if ( is_singular() ) {
			$base = get_permalink();

		// Default search location
		} else {
			$base = bbp_get_search_results_url();
		}

		// Add pagination base
		$base = trailingslashit( $base ) . user_trailingslashit( bbp_get_paged_slug() . '/%#%/' );

	// Unpretty permalinks
	} else {
		$base = add_query_arg( 'paged', '%#%' );
	}

	// Filter & return
	return apply_filters( 'bbp_get_search_pagination_base', $base );
}

/**
 * Output the search result pagination count
 *
 * @since 2.3.0 bbPress (r4579)
 */
function bbp_search_pagination_count() {
	echo bbp_get_search_pagination_count();
}

	/**
	 * Return the search results pagination count
	 *
	 * @since 2.3.0 bbPress (r4579)
	 *
	 * @return string Search pagination count
	 */
	function bbp_get_search_pagination_count() {
		$bbp = bbpress();

		// Define local variable(s)
		$retstr = '';

		// Set pagination values
		$total_int = intval( $bbp->search_query->found_posts    );
		$ppp_int   = intval( $bbp->search_query->posts_per_page );
		$start_int = intval( ( $bbp->search_query->paged - 1 ) * $ppp_int ) + 1;
		$to_int    = intval( ( $start_int + ( $ppp_int - 1 ) > $total_int )
				? $total_int
				: $start_int + ( $ppp_int - 1 ) );

		// Format numbers for display
		$total_num = bbp_number_format( $total_int );
		$from_num  = bbp_number_format( $start_int );
		$to_num    = bbp_number_format( $to_int    );

		// Single page of results
		if ( empty( $to_num ) ) {
			$retstr = sprintf( _n( 'Viewing %1$s result', 'Viewing %1$s results', $total_int, 'bbpress' ), $total_num );

		// Several pages of results
		} else {
			$retstr = sprintf( _n( 'Viewing %2$s results (of %4$s total)', 'Viewing %1$s results - %2$s through %3$s (of %4$s total)', $bbp->search_query->post_count, 'bbpress' ), $bbp->search_query->post_count, $from_num, $to_num, $total_num );
		}

		// Filter & return
		return apply_filters( 'bbp_get_search_pagination_count', esc_html( $retstr ) );
	}

/**
 * Output search pagination links
 *
 * @since 2.3.0 bbPress (r4579)
 */
function bbp_search_pagination_links() {
	echo bbp_get_search_pagination_links();
}

	/**
	 * Return search pagination links
	 *
	 * @since 2.3.0 bbPress (r4579)
	 *
	 * @return string Search pagination links
	 */
	function bbp_get_search_pagination_links() {
		$bbp = bbpress();

		if ( ! isset( $bbp->search_query->pagination_links ) || empty( $bbp->search_query->pagination_links ) ) {
			return false;
		}

		// Filter & return
		return apply_filters( 'bbp_get_search_pagination_links', $bbp->search_query->pagination_links );
	}
