<?php

if ( !class_exists( 'BBP_Loader' ) ) :
/**
 * BBP_Loader
 *
 * tap tap tap... Is this thing on?
 *
 * @package bbPress
 * @subpackage Loader
 * @since bbPress (r2464)
 *
 */
class BBP_Loader {

	/**
	 * The main bbPress loader
	 */
	function bbp_loader () {
		// Attach the bbp_loaded action to the WordPress plugins_loaded action.
		add_action( 'plugins_loaded',  array ( $this, 'loaded' ) );

		// Attach the bbp_init to the WordPress init action.
		add_action( 'init',            array ( $this, 'init' ) );

		// Attach constants to bbp_loaded.
		add_action( 'bbp_loaded',      array ( $this, 'constants' ) );

		// Attach includes to bbp_loaded.
		add_action( 'bbp_loaded',      array ( $this, 'includes' ) );

		// Attach theme directory bbp_loaded.
		add_action( 'bbp_loaded',      array ( $this, 'register_theme_directory' ) );

		// Attach textdomain to bbp_init.
		add_action( 'bbp_init',        array ( $this, 'textdomain' ) );

		// Attach post type registration to bbp_init.
		add_action( 'bbp_init',        array ( $this, 'register_content_types' ) );

		// Attach topic tag registration bbp_init.
		add_action( 'bbp_init',        array ( $this, 'register_taxonomies' ) );

		// Register bbPress activation sequence
		register_activation_hook( __FILE__, array( $this, 'activation' ) );

		// Register bbPress deactivation sequence
		register_deactivation_hook( __FILE__, array( $this, 'deactivation' ) );
	}

	/**
	 * constants ()
	 *
	 * Default component constants that can be overridden or filtered
	 */
	function constants () {
		do_action( 'bbp_constants' );
	}

	/**
	 * includes ()
	 *
	 * Include required files
	 *
	 * @uses is_admin If in WordPress admin, load additional file
	 */
	function includes () {
		do_action( 'bbp_includes' );
	}

	/**
	 * loaded ()
	 *
	 * A bbPress specific action to say that it has started its
	 * boot strapping sequence. It's attached to the existing WordPress
	 * action 'plugins_loaded' because that's when all plugins have loaded. Duh. :P
	 *
	 * @uses do_action()
	 */
	function loaded () {
		do_action( 'bbp_loaded' );
	}

	/**
	 * init ()
	 *
	 * Initialize bbPress as part of the WordPress initilization process
	 *
	 * @uses do_action Calls custom action to allow external enhancement
	 */
	function init () {
		do_action ( 'bbp_init' );
	}

	/**
	 * textdomain ()
	 *
	 * Load the translation file for current language
	 */
	function textdomain () {
		do_action( 'bbp_load_textdomain' );
	}

	/**
	 * register_theme_directory ()
	 *
	 * Sets up the bbPress theme directory to use in WordPress
	 *
	 * @since bbPress (r2507)
	 * @uses register_theme_directory
	 */
	function register_theme_directory () {
		do_action( 'bbp_register_theme_directory' );
	}

	/**
	 * register_content_types ()
	 *
	 * Setup the post types and taxonomy for forums
	 *
	 * @todo Finish up the post type admin area with messages, columns, etc...*
	 */
	function register_content_types () {
		do_action ( 'bbp_register_content_types' );
	}

	/**
	 * register_taxonomies ()
	 *
	 * Register the built in bbPress taxonomies
	 *
	 * @since bbPress (r2464)
	 *
	 * @uses register_taxonomy()
	 * @uses apply_filters(0
	 */
	function register_taxonomies () {
		do_action ( 'bbp_register_taxonomies' );
	}

	/**
	 * activation ()
	 *
	 * Runs on bbPress activation
	 *
	 * @since bbPress (r2509)
	 */
	function activation () {
		do_action( 'bbp_activation' );
	}

	/**
	 * deactivation ()
	 *
	 * Runs on bbPress deactivation
	 *
	 * @since bbPress (r2509)
	 */
	function deactivation () {
		do_action( 'bbp_deactivation' );
	}

	/**
	 * uninstall ()
	 *
	 * Runs when uninstalling bbPress
	 *
	 * @since bbPress (r2509)
	 */
	function uninstall () {
		do_action( 'bbp_uninstall' );
	}
}

endif; // class_exists check

$bbp_loader = new BBP_Loader();

?>
