<?php

/**
 * bbPress Abstractions
 *
 * This file contains functions for abstracting WordPress core functionality
 * into convenient wrappers so they can be used more reliably.
 *
 * Many of the functions in this file are considered superfluous by
 * WordPress coding standards, but they're handy for plugins of plugins to use.
 *
 * @package bbPress
 * @subpackage Core
 */

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

/**
 * Lookup and return a global variable
 *
 * @since 2.5.8 bbPress (r5814)
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
 * Get the `$wp_roles` global without needing to declare it everywhere
 *
 * @since 2.2.0 bbPress (r4293)
 *
 * @return WP_Roles
 */
function bbp_get_wp_roles() {
	return bbp_get_global_object( 'wp_roles', 'WP_Roles' );
}

/**
 * Return the database class being used to interface with the environment.
 *
 * This function is abstracted to avoid global touches to the primary database
 * class. bbPress supports WordPress's `$wpdb` global by default, and can be
 * filtered to support other configurations if needed.
 *
 * @since 2.5.8 bbPress (r5814)
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
 * @since 2.5.8 bbPress (r5814)
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
 * @since 2.5.8 bbPress (r5814)
 *
 * @return string
 */
function bbp_get_root_url() {

	// Filter & return
	return apply_filters( 'bbp_get_root_url', bbp_rewrite()->root );
}

/**
 * Get the slug used for paginated requests
 *
 * @since 2.4.0 bbPress (r4926)
 *
 * @return string
 */
function bbp_get_paged_slug() {

	// Filter & return
	return apply_filters( 'bbp_get_paged_slug', bbp_rewrite()->pagination_base );
}

/**
 * Is the environment using pretty URLs?
 *
 * @since 2.5.8 bbPress (r5814)
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

/**
 * Parse the WordPress core version number
 *
 * @since 2.6.0 bbPress (r6051)
 *
 * @global string $wp_version
 *
 * @return string $wp_version
 */
function bbp_get_major_wp_version() {
	global $wp_version;

	return (float) $wp_version;
}

/**
 * Is this a large bbPress installation?
 *
 * @since 2.6.0 bbPress (r6242)
 *
 * @return bool True if more than 10000 users, false not
 */
function bbp_is_large_install() {

	// Multisite has a function specifically for this
	$retval = function_exists( 'wp_is_large_network' )
		? wp_is_large_network( 'users' )
		: ( bbp_get_total_users() > 10000 );

	// Filter & return
	return (bool) apply_filters( 'bbp_is_large_install', $retval );
}

/**
 * Get the total number of users on the forums
 *
 * @since 2.0.0 bbPress (r2769)
 *
 * @uses apply_filters() Calls 'bbp_get_total_users' with number of users
 * @return int Total number of users
 */
function bbp_get_total_users() {
	$bbp_db = bbp_db();
	$count  = $bbp_db->get_var( "SELECT COUNT(ID) as c FROM {$bbp_db->users} WHERE user_status = '0'" );

	// Filter & return
	return (int) apply_filters( 'bbp_get_total_users', (int) $count );
}
