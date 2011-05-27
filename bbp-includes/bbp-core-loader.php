<?php

/**
 * bbPress Loader Actions
 *
 * @package bbPress
 * @subpackage Loader
 */

// Exit if accessed directly
if ( !defined( 'ABSPATH' ) ) exit;

/** Main Actions **************************************************************/

/**
 * Main action responsible for constants, globals, and includes
 *
 * @since bbPress (r2599)
 *
 * @uses do_action() Calls 'bbp_loaded'
 */
function bbp_loaded() {
	do_action( 'bbp_loaded' );
}

/**
 * Setup constants
 *
 * @since bbPress (r2599)
 *
 * @uses do_action() Calls 'bbp_constants'
 */
function bbp_constants() {
	do_action( 'bbp_constants' );
}

/**
 * Setup globals BEFORE includes
 *
 * @since bbPress (r2599)
 *
 * @uses do_action() Calls 'bbp_boot_strap_globals'
 */
function bbp_boot_strap_globals() {
	do_action( 'bbp_boot_strap_globals' );
}

/**
 * Include files
 *
 * @since bbPress (r2599)
 *
 * @uses do_action() Calls 'bbp_includes'
 */
function bbp_includes() {
	do_action( 'bbp_includes' );
}

/**
 * Setup globals AFTER includes
 *
 * @since bbPress (r2599)
 *
 * @uses do_action() Calls 'bbp_setup_globals'
 */
function bbp_setup_globals() {
	do_action( 'bbp_setup_globals' );
}

/**
 * Initialize any code after everything has been loaded
 *
 * @since bbPress (r2599)
 *
 * @uses do_action() Calls 'bbp_init'
 */
function bbp_init() {
	do_action ( 'bbp_init' );
}

/** Supplemental Actions ******************************************************/

/**
 * Setup the currently logged-in user
 *
 * @since bbPress (r2695)
 *
 * @uses do_action() Calls 'bbp_setup_current_user'
 */
function bbp_setup_current_user() {
	do_action ( 'bbp_setup_current_user' );
}

/**
 * Load translations for current language
 *
 * @since bbPress (r2599)
 *
 * @uses do_action() Calls 'bbp_load_textdomain'
 */
function bbp_register_textdomain() {
	do_action( 'bbp_load_textdomain' );
}

/**
 * Sets up the theme directory
 *
 * @since bbPress (r2507)
 *
 * @uses do_action() Calls 'bbp_register_theme_directory'
 */
function bbp_register_theme_directory() {
	do_action( 'bbp_register_theme_directory' );
}

/**
 * Setup the post types
 *
 * @since bbPress (r2464)
 *
 * @uses do_action() Calls 'bbp_register_post_type'
 */
function bbp_register_post_types() {
	do_action ( 'bbp_register_post_types' );
}

/**
 * Setup the post statuses
 *
 * @since bbPress (r2727)
 *
 * @uses do_action() Calls 'bbp_register_post_statuses'
 */
function bbp_register_post_statuses() {
	do_action ( 'bbp_register_post_statuses' );
}

/**
 * Register the built in bbPress taxonomies
 *
 * @since bbPress (r2464)
 *
 * @uses do_action() Calls 'bbp_register_taxonomies'
 */
function bbp_register_taxonomies() {
	do_action ( 'bbp_register_taxonomies' );
}

/**
 * Register the default bbPress views
 *
 * @since bbPress (r2789)
 *
 * @uses do_action() Calls 'bbp_register_views'
 */
function bbp_register_views() {
	do_action ( 'bbp_register_views' );
}

/**
 * Add the bbPress-specific rewrite tags
 *
 * @since bbPress (r2753)
 *
 * @uses do_action() Calls 'bbp_add_rewrite_tags'
 */
function bbp_add_rewrite_tags() {
	do_action ( 'bbp_add_rewrite_tags' );
}

/**
 * Generate bbPress-specific rewrite rules
 *
 * @since bbPress (r2688)
 *
 * @param WP_Rewrite $wp_rewrite
 *
 * @uses do_action() Calls 'bbp_generate_rewrite_rules' with {@link WP_Rewrite}
 */
function bbp_generate_rewrite_rules( $wp_rewrite ) {
	do_action_ref_array( 'bbp_generate_rewrite_rules', array( &$wp_rewrite ) );
}

/**
 * Setup bbPress theme compatability actions
 *
 * @since bbPress (r3028)
 *
 * @uses do_action() Calls 'bbp_setup_theme_compat'
 */
function bbp_setup_theme_compat() {
	do_action( 'bbp_setup_theme_compat' );
}

/** Final Action **************************************************************/

/**
 * bbPress has loaded and initialized everything, and is okay to go
 *
 * @since bbPress (r2618)
 *
 * @uses do_action() Calls 'bbp_ready'
 */
function bbp_ready() {
	do_action( 'bbp_ready' );
}

?>
