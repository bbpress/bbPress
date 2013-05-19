<?php

/**
 * bbPress Search Functions
 *
 * @package bbPress
 * @subpackage Functions
 */

// Exit if accessed directly
if ( !defined( 'ABSPATH' ) ) exit;

/** Query *********************************************************************/

/**
 * Run the search query
 *
 * @since bbPress (r4579) 
 *
 * @param mixed $new_args New arguments
 * @uses bbp_get_search_query_args() To get the search query args
 * @uses bbp_parse_args() To parse the args
 * @uses bbp_has_search_results() To make the search query
 * @return bool False if no results, otherwise if search results are there
 */
function bbp_search_query( $new_args = array() ) {

	// Existing arguments 
	$query_args = bbp_get_search_query_args();

	// Merge arguments
	if ( !empty( $new_args ) ) {
		$new_args   = bbp_parse_args( $new_args, array(), 'search_query' );
		$query_args = array_merge( $query_args, $new_args );
	}

	return bbp_has_search_results( $query_args );
}

/**
 * Return the search's query args
 *
 * @since bbPress (r4579)
 *
 * @uses bbp_get_search_terms() To get the search terms
 * @return array Query arguments
 */
function bbp_get_search_query_args() {

	// Get search terms
	$search_terms = bbp_get_search_terms();
	$retval = !empty( $search_terms ) ? array( 's' => $search_terms ) : array();

	return apply_filters( 'bbp_get_search_query_args', $retval );
}

/**
 * Redirect to search results page if needed
 *
 * @since bbPress (r4928)
 * @return If a redirect is not needed
 */
function bbp_search_template_redirect() {

	// Bail if already on search results page
	if ( bbp_is_search_results() )
		return;

	// Bail if search terms are empty
	if ( false === bbp_get_search_terms() )
		return;

	// Get the redirect URL
	$redirect_to = bbp_get_search_results_url();

	// Bail if no redirect URL or may cause infinite loop
	if ( empty( $redirect_to ) || ( bbp_get_search_url() === $redirect_to ) )
		return;

	// Redirect and bail
	wp_safe_redirect( $redirect_to );
	exit();
}
