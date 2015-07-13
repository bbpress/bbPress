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
 * Lookup and return a global variable
 *
 * @since bbPress (r5814)
 *
 * @param  string  $name     Name of global variable
 * @param  string  $type     Type of variable to check with `is_a()`
 * @param  mixed   $default  Default value to return if no global found
 *
 * @return mixed   Verified object if valid, Default or null if invalid
 */
function bbp_get_global_object( $name = '', $type = '', $default = null ) {

	// Bail if no name passed
	if ( empty( $name ) ) {
		$retval = $default;

	// Bail if no global exists
	} elseif ( ! isset( $GLOBALS[ $name ] ) ) {
		$retval = $default;

	// Bail if not the correct type of global
	} elseif ( ! empty( $type ) && ! is_a( $GLOBALS[ $name ], $type ) ) {
		$retval = $default;

	// Global variable exists
	} else {
		$retval = $GLOBALS[ $name ];
	}

	// Filter & return
	return apply_filters( 'bbp_get_global_object', $retval, $name, $type, $default );
}

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
function bbp_db() {
	return bbp_get_global_object( 'wpdb', 'WPDB' );
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
function bbp_rewrite() {
	return bbp_get_global_object( 'wp_rewrite', 'WP_Rewrite', (object) array(
		'root'            => '',
		'pagination_base' => '',
	) );
}

/**
 * Get the root URL
 *
 * @since bbPress (r5814)
 *
 * @return string
 */
function bbp_get_root_url() {
	return apply_filters( 'bbp_get_root_url', bbp_rewrite()->root );
}

/**
 * Get the slug used for paginated requests
 *
 * @since bbPress (r4926)
 *
 * @return string
 */
function bbp_get_paged_slug() {
	return apply_filters( 'bbp_get_paged_slug', bbp_rewrite()->pagination_base );
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
function bbp_use_pretty_urls() {

	// Default
	$retval  = false;
	$rewrite = bbp_rewrite();

	// Use $wp_rewrite->using_permalinks() if available
	if ( method_exists( $rewrite, 'using_permalinks' ) ) {
		$retval = $rewrite->using_permalinks();
	}

	// Filter & return
	return apply_filters( 'bbp_pretty_urls', $retval );
}
