<?php

/**
 * BBP_Main
 *
 * The main bbPress container class
 *
 * @package bbPress
 * @subpackage Loader
 * @since bbPress (1.2-r2464)
 *
 * @todo Alot ;)
 */
class BBP_Main {

	function init () {
		// Setup globals
		add_action ( 'bbp_setup_globals', array( 'BBP_Main', 'setup_globals' ) );

		// wp_head
		add_action ( 'bbp_head',          array( 'BBP_Main', 'bbp_enqueue_scripts' ) );
	}

	/**
	 * setup_globals ()
	 *
	 * Setup all plugin global
	 *
	 * @global array $bbp
	 * @global object $wpdb
	 */
	function setup_globals () {
		global $bbp, $wpdb;

		// For internal identification
		$bbp->id        = BBP_FORUM_POST_TYPE_ID;
		$bbp->slug      = BBP_SLUG;
		$bbp->settings  = BBP_Main::settings();

		// Register this in the active components array
		$bbp->active_components[$bbp->slug] = $bbp->id;
	}

	/**
	 * settings ()
	 *
	 * Loads up any saved settings and filters each default value
	 *
	 * @return array
	 */
	function settings () {

		// @todo site|network wide forum option? Don't see why not both?
		$settings = get_site_option( 'bbp_settings', false );

		// Set default values and allow them to be filtered
		$defaults = array (
			// the cake is a lie
		);

		// Allow settings array to be filtered and return
		return apply_filters( 'bbp_settings', wp_parse_args( $settings, $defaults ) );
	}

	/**
	 * enqueue_scripts ()
	 *
	 * Hooks into wp_head ()
	 *
	 * @return Only return if no data to display
	 */
	function enqueue_scripts () {
		// Load up the JS
		wp_enqueue_script( 'jquery' );

		do_action( 'bbp_enqueue_scripts' );
	}
}

class BBP_Forum {
	function bbp_forum() {

	}
}

class BBP_Topic {
	function bbp_topic() {

	}
}

class BBP_Post {
	function bbp_post() {

	}
}

class BBP_User {
	function bbp_user() {

	}
}

?>
