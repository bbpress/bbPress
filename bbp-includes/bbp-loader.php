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
	 * The main bbPress loader. Action priorities included within this function
	 * are for the sake of human readability and clarification.
	 */
	function BBP_Loader () {
		// Attach to WordPress actions
		add_action( 'plugins_loaded', array ( $this, 'loaded'                   ), 10 );
		add_action( 'init',           array ( $this, 'init'                     ), 10 );

		// Attach to bbp_loaded.
		add_action( 'bbp_loaded',     array ( $this, 'constants'                ), 2  );
		add_action( 'bbp_loaded',     array ( $this, 'boot_strap_globals'       ), 4  );
		add_action( 'bbp_loaded',     array ( $this, 'includes'                 ), 6  );
		add_action( 'bbp_loaded',     array ( $this, 'setup_globals'            ), 8  );
		add_action( 'bbp_loaded',     array ( $this, 'register_theme_directory' ), 10 );

		// Attach to bbp_init.
		add_action( 'bbp_init',       array ( $this, 'register_content_types'   ), 6  );
		add_action( 'bbp_init',       array ( $this, 'register_taxonomies'      ), 8  );
		add_action( 'bbp_init',       array ( $this, 'register_textdomain',     ), 10 );

		// Register bbPress activation/deactivation sequences
		register_activation_hook  ( __FILE__, array ( $this, 'activation'       ), 10 );
		register_deactivation_hook( __FILE__, array ( $this, 'deactivation'     ), 10 );
	}

	/**
	 * constants ()
	 *
	 * Setup constants
	 */
	function constants () {
		do_action( 'bbp_constants' );
	}

	/**
	 * boot_strap_globals ()
	 * 
	 * Setup globals BEFORE includes
	 */
	function boot_strap_globals () {
		do_action( 'bbp_boot_strap_globals' );
	}

	/**
	 * includes ()
	 *
	 * Include files
	 */
	function includes () {
		do_action( 'bbp_includes' );
	}

	/**
	 * setup_globals ()
	 *
	 * Setup globals AFTER includes
	 */
	function setup_globals () {
		do_action( 'bbp_setup_globals' );
	}

	/**
	 * loaded ()
	 *
	 * Main action responsible for constants, globals, and includes
	 */
	function loaded () {
		do_action( 'bbp_loaded' );
	}

	/**
	 * init ()
	 *
	 * Initialize any code after everything has been loaded
	 */
	function init () {
		do_action ( 'bbp_init' );
	}

	/**
	 * register_textdomain ()
	 *
	 * Load translations for current language
	 */
	function register_textdomain () {
		do_action( 'bbp_load_textdomain' );
	}

	/**
	 * register_theme_directory ()
	 *
	 * Sets up the theme directory
	 *
	 * @since bbPress (r2507)
	 */
	function register_theme_directory () {
		do_action( 'bbp_register_theme_directory' );
	}

	/**
	 * register_content_types ()
	 *
	 * Setup the content types
	 *
	 * @since bbPress (r2464)
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

$bbp->loader = new BBP_Loader();

endif; // class_exists check

?>
