<?php

/**
 * bbPress Abstractions
 *
 * @package bbPress
 * @subpackage Core
 *
 * This file contains functions for abstracting WordPress core functionality
 * into convenient wrappers so they can be used more reliably.
 *
 * Many of the functions in this file are considered superfluous by
 * WordPress coding standards, but
 */

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

/**
 * Return the database class being used to interface with the environment.
 *
 * This function is abstracted to avoid global touches to the primary database
 * class. bbPress supports WordPress's `$wpdb` global by default, and can be
 * filtered to support other configurations if needed.
 *
 * @since bbPress (r5814)
 *
 * @return object
 */
function bbp_get_db() {

	// WordPress's `$wpdb` global
	if ( isset( $GLOBALS['wpdb'] ) && is_a( $GLOBALS['wpdb'], 'WPDB' ) ) {
		$retval = $GLOBALS['wpdb'];
	}

	// Filter & return
	return apply_filters( 'bbp_get_db', $retval );
}

/**
 * Return the rewrite rules class being used to interact with URLs.
 *
 * This function is abstracted to avoid global touches to the primary rewrite
 * rules class. bbPress supports WordPress's `$wp_rewrite` by default, but can
 * be filtered to support other configurations if needed.
 *
 * @since bbPress (r5814)
 *
 * @return object
 */
function bbp_get_rewrite() {

	// WordPress `$wp_rewrite` global
	if ( isset( $GLOBALS['wp_rewrite'] ) && is_a( $GLOBALS['wp_rewrite'], 'WP_Rewrite' ) ) {
		$retval = $GLOBALS['wp_rewrite'];

	// Mock the expected object
	} else {
		$retval = (object) array(
			'root'            => '',
			'pagination_base' => '',
		);
	}

	// Filter & return
	return apply_filters( 'bbp_get_rewrite', $retval );
}

/**
 * Get the URL root
 *
 * @since bbPress (r5814)
 *
 * @return string
 */
function bbp_get_root_url() {
	return apply_filters( 'bbp_get_root_url', bbp_get_rewrite()->root );
}

/**
 * Get the slug used for paginated requests
 *
 * @since bbPress (r4926)
 *
 * @return string
 */
function bbp_get_paged_slug() {
	return apply_filters( 'bbp_get_paged_slug', bbp_get_rewrite()->pagination_base );
}

/**
 * Is the environment using pretty URLs?
 *
 * @since bbPress (r5814)
 *
 * @global object $wp_rewrite The WP_Rewrite object
 *
 * @return bool
 */
function bbp_pretty_urls() {

	// Default
	$retval  = false;
	$rewrite = bbp_get_rewrite();

	// Use $wp_rewrite->using_permalinks() if available
	if ( method_exists( $rewrite, 'using_permalinks' ) ) {
		$retval = $rewrite->using_permalinks();
	}

	// Filter & return
	return apply_filters( 'bbp_pretty_urls', $retval );
}
