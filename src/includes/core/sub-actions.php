<?php

/**
 * Plugin Dependency
 *
 * The purpose of the following hooks is to mimic the behavior of something
 * called 'plugin dependency' which enables a plugin to have plugins of their
 * own in a safe and reliable way.
 *
 * We do this in bbPress by mirroring existing WordPress hooks in many places
 * allowing dependant plugins to hook into the bbPress specific ones, thus
 * guaranteeing proper code execution only when bbPress is active.
 *
 * The following functions are wrappers for hooks, allowing them to be
 * manually called and/or piggy-backed on top of other hooks if needed.
 *
 * @package bbPress
 * @subpackage Core
 *
 * @todo use anonymous functions when PHP minimum requirement allows (5.3)
 */

/** Activation Actions ********************************************************/

/**
 * Runs on bbPress activation
 *
 * @since 2.0.0 bbPress (r2509)
 */
function bbp_activation() {
	do_action( 'bbp_activation' );
}

/**
 * Runs on bbPress deactivation
 *
 * @since 2.0.0 bbPress (r2509)
 */
function bbp_deactivation() {
	do_action( 'bbp_deactivation' );
}

/**
 * Runs when uninstalling bbPress
 *
 * @since 2.0.0 bbPress (r2509)
 */
function bbp_uninstall() {
	do_action( 'bbp_uninstall' );
}

/** Main Actions **************************************************************/

/**
 * Main action responsible for constants, globals, and includes
 *
 * @since 2.0.0 bbPress (r2599)
 */
function bbp_loaded() {
	do_action( 'bbp_loaded' );
}

/**
 * Setup constants
 *
 * @since 2.0.0 bbPress (r2599)
 */
function bbp_constants() {
	do_action( 'bbp_constants' );
}

/**
 * Setup globals BEFORE includes
 *
 * @since 2.0.0 bbPress (r2599)
 */
function bbp_boot_strap_globals() {
	do_action( 'bbp_boot_strap_globals' );
}

/**
 * Include files
 *
 * @since 2.0.0 bbPress (r2599)
 */
function bbp_includes() {
	do_action( 'bbp_includes' );
}

/**
 * Setup globals AFTER includes
 *
 * @since 2.0.0 bbPress (r2599)
 */
function bbp_setup_globals() {
	do_action( 'bbp_setup_globals' );
}

/**
 * Register any objects before anything is initialized
 *
 * @since 2.2.0 bbPress (r4180)
 */
function bbp_register() {
	do_action( 'bbp_register' );
}

/**
 * Initialize any code after everything has been loaded
 *
 * @since 2.0.0 bbPress (r2599)
 */
function bbp_init() {
	do_action( 'bbp_init' );
}

/**
 * Initialize roles
 *
 * @since 2.6.0 bbPress (r6106)
 *
 * @param WP_Roles $wp_roles The array of WP_Role objects that was initialized
 */
function bbp_roles_init( $wp_roles ) {
	do_action( 'bbp_roles_init', $wp_roles );
}

/**
 * Initialize widgets
 *
 * @since 2.0.0 bbPress (r3389)
 */
function bbp_widgets_init() {
	do_action( 'bbp_widgets_init' );
}

/**
 * Setup the currently logged-in user
 *
 * @link https://bbpress.trac.wordpress.org/ticket/2309
 * @link https://core.trac.wordpress.org/ticket/24169
 *
 * @since 2.0.0 bbPress (r2695)
 */
function bbp_setup_current_user() {
	do_action( 'bbp_setup_current_user' );
}

/**
 * Setup the user engagements strategy
 *
 * @since 2.6.0 bbPress (r6875)
 */
function bbp_setup_engagements() {
	do_action( 'bbp_setup_engagements' );
}

/** Supplemental Actions ******************************************************/

/**
 * Load translations for current language
 *
 * @since 2.0.0 bbPress (r2599)
 */
function bbp_load_textdomain() {
	do_action( 'bbp_load_textdomain' );
}

/**
 * Setup the post types
 *
 * @since 2.0.0 bbPress (r2464)
 */
function bbp_register_post_types() {
	do_action( 'bbp_register_post_types' );
}

/**
 * Setup the post statuses
 *
 * @since 2.0.0 bbPress (r2727)
 */
function bbp_register_post_statuses() {
	do_action( 'bbp_register_post_statuses' );
}

/**
 * Register the built in bbPress taxonomies
 *
 * @since 2.0.0 bbPress (r2464)
 */
function bbp_register_taxonomies() {
	do_action( 'bbp_register_taxonomies' );
}

/**
 * Register the default bbPress views
 *
 * @since 2.0.0 bbPress (r2789)
 */
function bbp_register_views() {
	do_action( 'bbp_register_views' );
}

/**
 * Register the default bbPress shortcodes
 *
 * @since 2.2.0 bbPress (r4211)
 */
function bbp_register_shortcodes() {
	do_action( 'bbp_register_shortcodes' );
}

/**
 * Register the default bbPress meta-data
 *
 * @since 2.6.0 bbPress (r46300)
 */
function bbp_register_meta() {
	do_action( 'bbp_register_meta' );
}

/**
 * Enqueue bbPress specific CSS and JS
 *
 * @since 2.0.0 bbPress (r3373)
 */
function bbp_enqueue_scripts() {
	do_action( 'bbp_enqueue_scripts' );
}

/**
 * Add the bbPress-specific rewrite tags
 *
 * @since 2.0.0 bbPress (r2753)
 */
function bbp_add_rewrite_tags() {
	do_action( 'bbp_add_rewrite_tags' );
}

/**
 * Add the bbPress-specific rewrite rules
 *
 * @since 2.4.0 bbPress (r4918)
 */
function bbp_add_rewrite_rules() {
	do_action( 'bbp_add_rewrite_rules' );
}

/**
 * Add the bbPress-specific permalink structures
 *
 * @since 2.4.0 bbPress (r4918)
 */
function bbp_add_permastructs() {
	do_action( 'bbp_add_permastructs' );
}

/**
 * Add the bbPress-specific login forum action
 *
 * @since 2.0.0 bbPress (r2753)
 */
function bbp_login_form_login() {
	do_action( 'bbp_login_form_login' );
}

/**
 * Add the bbPress-specific post status transition action
 *
 * @since 2.6.0 bbPress (r6792)
 *
 * @param string  $new_status New post status
 * @param string  $old_status Old post status
 * @param WP_Post $post       Post object
 */
function bbp_transition_post_status( $new_status = '', $old_status = '', $post = false ) {

	// Get bbPress post types
	$post_type = get_post_type( $post );
	$types     = bbp_get_post_types();

	// Bail if post is not a bbPress post type
	if ( ! in_array( $post_type, $types, true ) ) {
		return;
	}

	// Do the action
	do_action( 'bbp_transition_post_status', $new_status, $old_status, $post );
}

/** User Actions **************************************************************/

/**
 * The main action for hooking into when a user account is updated
 *
 * @since 2.2.0 bbPress (r4304)
 *
 * @param int $user_id ID of user being edited
 * @param array $old_user_data The old, unmodified user data
 */
function bbp_profile_update( $user_id = 0, $old_user_data = array() ) {
	do_action( 'bbp_profile_update', $user_id, $old_user_data );
}

/**
 * The main action for hooking into a user being registered
 *
 * @since 2.2.0 bbPress (r4304)
 *
 * @param int $user_id ID of user being edited
 */
function bbp_user_register( $user_id = 0 ) {
	do_action( 'bbp_user_register', $user_id );
}

/** Final Action **************************************************************/

/**
 * bbPress has loaded and initialized everything, and is okay to go
 *
 * @since 2.0.0 bbPress (r2618)
 */
function bbp_ready() {
	do_action( 'bbp_ready' );
}

/** Theme Permissions *********************************************************/

/**
 * The main action used for redirecting bbPress theme actions that are not
 * permitted by the current_user
 *
 * @since 2.1.0 bbPress (r3605)
 */
function bbp_template_redirect() {
	do_action( 'bbp_template_redirect' );
}

/** Theme Helpers *************************************************************/

/**
 * The main action used for executing code before the theme has been setup
 *
 * @since 2.1.0 bbPress (r3829)
 */
function bbp_register_theme_packages() {
	do_action( 'bbp_register_theme_packages' );
}

/**
 * The main action used for executing code before the theme has been setup
 *
 * @since 2.1.0 bbPress (r3732)
 */
function bbp_setup_theme() {
	do_action( 'bbp_setup_theme' );
}

/**
 * The main action used for executing code after the theme has been setup
 *
 * @since 2.1.0 bbPress (r3732)
 */
function bbp_after_setup_theme() {
	do_action( 'bbp_after_setup_theme' );
}

/**
 * The main action used for handling theme-side POST requests
 *
 * @since 2.3.0 bbPress (r4550)
 */
function bbp_post_request() {

	// Bail if not a POST action
	if ( ! bbp_is_post_request() ) {
		return;
	}

	// Bail if no action
	if ( empty( $_POST['action'] ) ) {
		return;
	}

	// Sanitize the POST action
	$action = sanitize_key( $_POST['action'] );

	// Bail if action was totally invalid
	if ( empty( $action ) ) {
		return;
	}

	// This dynamic action is probably the one you want to use. It narrows down
	// the scope of the 'action' without needing to check it in your function.
	do_action( 'bbp_post_request_' . $action );

	// Use this static action if you don't mind checking the 'action' yourself.
	do_action( 'bbp_post_request',   $action );
}

/**
 * The main action used for handling theme-side GET requests
 *
 * @since 2.3.0 bbPress (r4550)
 */
function bbp_get_request() {

	// Bail if not a POST action
	if ( ! bbp_is_get_request() ) {
		return;
	}

	// Bail if no action
	if ( empty( $_GET['action'] ) ) {
		return;
	}

	// Sanitize the GET action
	$action = sanitize_key( $_GET['action'] );

	// Bail if action was totally invalid
	if ( empty( $action ) ) {
		return;
	}

	// This dynamic action is probably the one you want to use. It narrows down
	// the scope of the 'action' without needing to check it in your function.
	do_action( 'bbp_get_request_' . $action );

	// Use this static action if you don't mind checking the 'action' yourself.
	do_action( 'bbp_get_request',   $action );
}

/** Filters *******************************************************************/

/**
 * Filter the plugin locale and domain.
 *
 * @since 2.2.0 bbPress (r4213)
 *
 * @param string $locale
 * @param string $domain
 */
function bbp_plugin_locale( $locale = '', $domain = '' ) {

	// Filter & return
	return apply_filters( 'bbp_plugin_locale', $locale, $domain );
}

/**
 * Piggy back filter for WordPress's 'request' filter
 *
 * @since 2.1.0 bbPress (r3758)
 *
 * @param array $query_vars
 * @return array
 */
function bbp_request( $query_vars = array() ) {

	// Filter & return
	return apply_filters( 'bbp_request', $query_vars );
}

/**
 * The main filter used for theme compatibility and displaying custom bbPress
 * theme files.
 *
 * @since 2.0.0 bbPress (r3311)
 *
 * @param string $template
 * @return string Template file to use
 */
function bbp_template_include( $template = '' ) {

	// Filter & return
	return apply_filters( 'bbp_template_include', $template );
}

/**
 * Generate bbPress-specific rewrite rules
 *
 * @since 2.0.0 bbPress (r2688)
 *
 * @deprecated 2.4.0 bbPress (r4918)
 *
 * @param WP_Rewrite $wp_rewrite
 */
function bbp_generate_rewrite_rules( $wp_rewrite ) {
	do_action_ref_array( 'bbp_generate_rewrite_rules', array( &$wp_rewrite ) );
}

/**
 * Filter the allowed themes list for bbPress specific themes
 *
 * @since 2.0.0 bbPress (r2944)
 *
 * @param array $themes
 *
 * @return array Array of allowed themes
 */
function bbp_allowed_themes( $themes ) {

	// Filter & return
	return (array) apply_filters( 'bbp_allowed_themes', $themes );
}

/**
 * Maps forum/topic/reply caps to built in WordPress caps
 *
 * @since 2.0.0 bbPress (r2593)
 *
 * @param array $caps Capabilities for meta capability
 * @param string $cap Capability name
 * @param int $user_id User id
 * @param array $args Arguments
 *
 * @return array Array of capabilities
 */
function bbp_map_meta_caps( $caps = array(), $cap = '', $user_id = 0, $args = array() ) {

	// Filter & return
	return (array) apply_filters( 'bbp_map_meta_caps', $caps, $cap, $user_id, $args );
}

/**
 * Filter the arguments used by wp_mail for bbPress specific emails
 *
 * @since 2.6.0 bbPress (r6918)
 *
 * @param array $args A compacted array of wp_mail() arguments, including the "to" email,
 *                    subject, message, headers, and attachments values.
 *
 * @return array Array of capabilities
 */
function bbp_mail( $args = array() ) {

	// Bail if headers are missing/malformed
	if ( empty( $args['headers'] ) || ! is_array( $args['headers'] ) ) {
		return $args;
	}

	// Header to search all headers for
	$bbp_header = bbp_get_email_header();

	// Bail if no bbPress header found
	if ( false === array_search( $bbp_header, $args['headers'], true ) ) {
		return $args;
	}

	// Filter & return
	return (array) apply_filters( 'bbp_mail', $args );
}
