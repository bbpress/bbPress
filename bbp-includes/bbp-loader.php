<?php
/**
 * bbp-loader.php
 *
 * The main bbPress loader. Action priorities included for the sake of human
 * readability and clarification.
 *
 * @package bbPress
 * @subpackage Loader
 * @since bbPress (r2464)
 *
 */

// Attach to WordPress actions
add_action( 'plugins_loaded', 'bbp_loaded'                   , 10 );
add_action( 'init',           'bbp_init'                     , 10 );

// Attach to bbp_loaded.
add_action( 'bbp_loaded',     'bbp_constants'                , 2  );
add_action( 'bbp_loaded',     'bbp_boot_strap_globals'       , 4  );
add_action( 'bbp_loaded',     'bbp_includes'                 , 6  );
add_action( 'bbp_loaded',     'bbp_setup_globals'            , 8  );
add_action( 'bbp_loaded',     'bbp_register_theme_directory' , 10 );

// Attach to bbp_init.
add_action( 'bbp_init',       'bbp_register_post_types'      , 6  );
add_action( 'bbp_init',       'bbp_register_taxonomies'      , 8  );
add_action( 'bbp_init',       'bbp_register_textdomain'      , 10 );

// Register bbPress activation/deactivation sequences
register_activation_hook  ( __FILE__, 'bbp_activation'       , 10 );
register_deactivation_hook( __FILE__, 'bbp_deactivation'     , 10 );

/**
 * bbp_bbp_constants ()
 *
 * Setup constants
 */
function bbp_constants () {
	do_action( 'bbp_constants' );
}

/**
 * boot_strap_globals ()
 *
 * Setup globals BEFORE includes
 */
function bbp_boot_strap_globals () {
	do_action( 'bbp_boot_strap_globals' );
}

/**
 * bbp_includes ()
 *
 * Include files
 */
function bbp_includes () {
	do_action( 'bbp_includes' );
}

/**
 * bbp_setup_globals ()
 *
 * Setup globals AFTER includes
 */
function bbp_setup_globals () {
	do_action( 'bbp_setup_globals' );
}

/**
 * bbp_loaded ()
 *
 * Main action responsible for constants, globals, and includes
 */
function bbp_loaded () {
	do_action( 'bbp_loaded' );
}

/**
 * bbp_init ()
 *
 * Initialize any code after everything has been loaded
 */
function bbp_init () {
	do_action ( 'bbp_init' );
}

/**
 * register_textdomain ()
 *
 * Load translations for current language
 */
function bbp_register_textdomain () {
	do_action( 'bbp_load_textdomain' );
}

/**
 * bbp_register_theme_directory ()
 *
 * Sets up the theme directory
 *
 * @since bbPress (r2507)
 */
function bbp_register_theme_directory () {
	do_action( 'bbp_register_theme_directory' );
}

/**
 * bbp_register_post_types ()
 *
 * Setup the post types
 *
 * @since bbPress (r2464)
 */
function bbp_register_post_types () {
	do_action ( 'bbp_register_post_types' );
}

/**
 * bbp_register_taxonomies ()
 *
 * Register the built in bbPress taxonomies
 *
 * @since bbPress (r2464)
 */
function bbp_register_taxonomies () {
	do_action ( 'bbp_register_taxonomies' );
}

/**
 * bbp_activation ()
 *
 * Runs on bbPress activation
 *
 * @since bbPress (r2509)
 */
function bbp_activation () {
	do_action( 'bbp_activation' );
}

/**
 * bbp_deactivation ()
 *
 * Runs on bbPress deactivation
 *
 * @since bbPress (r2509)
 */
function bbp_deactivation () {
	do_action( 'bbp_deactivation' );
}

/**
 * bbp_uninstall ()
 *
 * Runs when uninstalling bbPress
 *
 * @since bbPress (r2509)
 */
function bbp_uninstall () {
	do_action( 'bbp_uninstall' );
}

?>
