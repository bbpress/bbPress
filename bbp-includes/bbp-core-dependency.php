<?php

/**
 * Plugin Dependency
 *
 * The purpose of the following hooks is to mimic the behavior of something
 * called 'plugin dependency' which enables a plugin to have plugins of their
 * own in a safe and reliable way.
 *
 * We do this in bbPress by mirroring existing WordPress hookss in many places
 * allowing dependant plugins to hook into the bbPress specific ones, thus
 * guaranteeing proper code execution only when bbPress is active.
 *
 * The following functions are wrappers for hookss, allowing them to be
 * manually called and/or piggy-backed on top of other hooks if needed.
 *
 * @todo use anonymous functions when PHP minimun requirement allows (5.3)
 */

/** Activation Actions ********************************************************/

/**
 * Runs on bbPress activation
 *
 * @since bbPress (r2509)
 * @uses register_uninstall_hook() To register our own uninstall hook
 * @uses do_action() Calls 'bbp_activation' hook
 */
function bbp_activation() {
	do_action( 'bbp_activation' );
}

/**
 * Runs on bbPress deactivation
 *
 * @since bbPress (r2509)
 * @uses do_action() Calls 'bbp_deactivation' hook
 */
function bbp_deactivation() {
	do_action( 'bbp_deactivation' );
}

/**
 * Runs when uninstalling bbPress
 *
 * @since bbPress (r2509)
 * @uses do_action() Calls 'bbp_uninstall' hook
 */
function bbp_uninstall() {
	do_action( 'bbp_uninstall' );
}

/** Main Actions **************************************************************/

/**
 * Main action responsible for constants, globals, and includes
 *
 * @since bbPress (r2599)
 * @uses do_action() Calls 'bbp_loaded'
 */
function bbp_loaded() {
	do_action( 'bbp_loaded' );
}

/**
 * Setup constants
 *
 * @since bbPress (r2599)
 * @uses do_action() Calls 'bbp_constants'
 */
function bbp_constants() {
	do_action( 'bbp_constants' );
}

/**
 * Setup globals BEFORE includes
 *
 * @since bbPress (r2599)
 * @uses do_action() Calls 'bbp_boot_strap_globals'
 */
function bbp_boot_strap_globals() {
	do_action( 'bbp_boot_strap_globals' );
}

/**
 * Include files
 *
 * @since bbPress (r2599)
 * @uses do_action() Calls 'bbp_includes'
 */
function bbp_includes() {
	do_action( 'bbp_includes' );
}

/**
 * Setup globals AFTER includes
 *
 * @since bbPress (r2599)
 * @uses do_action() Calls 'bbp_setup_globals'
 */
function bbp_setup_globals() {
	do_action( 'bbp_setup_globals' );
}

/**
 * Register any objects before anything is initialized
 *
 * @since bbPress (r4180)
 * @uses do_action() Calls 'bbp_register'
 */
function bbp_register() {
	do_action( 'bbp_register' );
}

/**
 * Initialize any code after everything has been loaded
 *
 * @since bbPress (r2599)
 * @uses do_action() Calls 'bbp_init'
 */
function bbp_init() {
	do_action( 'bbp_init' );
}

/**
 * Initialize widgets
 *
 * @since bbPress (r3389)
 * @uses do_action() Calls 'bbp_widgets_init'
 */
function bbp_widgets_init() {
	do_action( 'bbp_widgets_init' );
}

/**
 * Setup the currently logged-in user
 *
 * @since bbPress (r2695)
 * @uses do_action() Calls 'bbp_setup_current_user'
 */
function bbp_setup_current_user() {
	do_action( 'bbp_setup_current_user' );
}

/** Supplemental Actions ******************************************************/

/**
 * Load translations for current language
 *
 * @since bbPress (r2599)
 * @uses do_action() Calls 'bbp_load_textdomain'
 */
function bbp_load_textdomain() {
	do_action( 'bbp_load_textdomain' );
}

/**
 * Sets up the theme directory
 *
 * @since bbPress (r2507)
 * @uses do_action() Calls 'bbp_register_theme_directory'
 */
function bbp_register_theme_directory() {
	do_action( 'bbp_register_theme_directory' );
}

/**
 * Setup the post types
 *
 * @since bbPress (r2464)
 * @uses do_action() Calls 'bbp_register_post_type'
 */
function bbp_register_post_types() {
	do_action( 'bbp_register_post_types' );
}

/**
 * Setup the post statuses
 *
 * @since bbPress (r2727)
 * @uses do_action() Calls 'bbp_register_post_statuses'
 */
function bbp_register_post_statuses() {
	do_action( 'bbp_register_post_statuses' );
}

/**
 * Register the built in bbPress taxonomies
 *
 * @since bbPress (r2464)
 * @uses do_action() Calls 'bbp_register_taxonomies'
 */
function bbp_register_taxonomies() {
	do_action( 'bbp_register_taxonomies' );
}

/**
 * Register the default bbPress views
 *
 * @since bbPress (r2789)
 * @uses do_action() Calls 'bbp_register_views'
 */
function bbp_register_views() {
	do_action( 'bbp_register_views' );
}

/**
 * Enqueue bbPress specific CSS and JS
 *
 * @since bbPress (r3373)
 * @uses do_action() Calls 'bbp_enqueue_scripts'
 */
function bbp_enqueue_scripts() {
	do_action( 'bbp_enqueue_scripts' );
}

/**
 * Add the bbPress-specific rewrite tags
 *
 * @since bbPress (r2753)
 * @uses do_action() Calls 'bbp_add_rewrite_tags'
 */
function bbp_add_rewrite_tags() {
	do_action( 'bbp_add_rewrite_tags' );
}

/**
 * Add the bbPress-specific login forum action
 *
 * @since bbPress (r2753)
 * @uses do_action() Calls 'bbp_login_form_login'
 */
function bbp_login_form_login() {
	do_action( 'bbp_login_form_login' );
}

/** Final Action **************************************************************/

/**
 * bbPress has loaded and initialized everything, and is okay to go
 *
 * @since bbPress (r2618)
 * @uses do_action() Calls 'bbp_ready'
 */
function bbp_ready() {
	do_action( 'bbp_ready' );
}

/** Theme Permissions *********************************************************/

/**
 * The main action used for redirecting bbPress theme actions that are not
 * permitted by the current_user
 *
 * @since bbPress (r3605)
 * @uses do_action()
 */
function bbp_template_redirect() {
	do_action( 'bbp_template_redirect' );
}

/** Theme Helpers *************************************************************/

/**
 * The main action used for executing code before the theme has been setup
 *
 * @since bbPress (r3829)
 * @uses do_action()
 */
function bbp_register_theme_packages() {
	do_action( 'bbp_register_theme_packages' );
}

/**
 * The main action used for executing code before the theme has been setup
 *
 * @since bbPress (r3732)
 * @uses do_action()
 */
function bbp_setup_theme() {
	do_action( 'bbp_setup_theme' );
}

/**
 * The main action used for executing code after the theme has been setup
 *
 * @since bbPress (r3732)
 * @uses do_action()
 */
function bbp_after_setup_theme() {
	do_action( 'bbp_after_setup_theme' );
}

/** Filters *******************************************************************/

/**
 * Piggy back filter for WordPress's 'request' filter
 *
 * @since bbPress (r3758)
 * @param array $query_vars
 * @return array
 */
function bbp_request( $query_vars = array() ) {
	return apply_filters( 'bbp_request', $query_vars );
}

/**
 * The main filter used for theme compatibility and displaying custom bbPress
 * theme files.
 *
 * @since bbPress (r3311)
 * @uses apply_filters()
 * @param string $template
 * @return string Template file to use
 */
function bbp_template_include( $template = '' ) {
	return apply_filters( 'bbp_template_include', $template );
}

/**
 * Generate bbPress-specific rewrite rules
 *
 * @since bbPress (r2688)
 * @param WP_Rewrite $wp_rewrite
 * @uses do_action() Calls 'bbp_generate_rewrite_rules' with {@link WP_Rewrite}
 */
function bbp_generate_rewrite_rules( $wp_rewrite ) {
	do_action_ref_array( 'bbp_generate_rewrite_rules', array( &$wp_rewrite ) );
}

/**
 * Filter the allowed themes list for bbPress specific themes
 *
 * @since bbPress (r2944)
 * @uses apply_filters() Calls 'bbp_allowed_themes' with the allowed themes list
 */
function bbp_allowed_themes( $themes ) {
	return apply_filters( 'bbp_allowed_themes', $themes );
}
