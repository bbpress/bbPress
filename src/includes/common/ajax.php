<?php

/**
 * bbPress Common AJAX Functions
 *
 * Common AJAX functions are ones that are used to setup and/or use during
 * bbPress specific, theme-side  AJAX requests.
 *
 * @package bbPress
 * @subpackage Ajax
 */

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

/**
 * Output the URL to use for theme-side bbPress AJAX requests
 *
 * @since 2.3.0 bbPress (r4543)
 */
function bbp_ajax_url() {
	echo esc_url( bbp_get_ajax_url() );
}
	/**
	 * Return the URL to use for theme-side bbPress AJAX requests
	 *
	 * @since 2.3.0 bbPress (r4543)
	 *
	 * @global WP $wp
	 * @return string
	 */
	function bbp_get_ajax_url() {
		global $wp;

		$ssl      = is_ssl() ? 'https' : 'http';
		$url      = trailingslashit( $wp->request );
		$base_url = home_url( $url, $ssl );
		$ajaxurl  = add_query_arg( array( 'bbp-ajax' => 'true' ), $base_url );

		// Filter & return
		return apply_filters( 'bbp_get_ajax_url', $ajaxurl );
	}

/**
 * Is this a bbPress AJAX request?
 *
 * @since 2.3.0 bbPress (r4543)
 *
 * @return bool Looking for bbp-ajax
 */
function bbp_is_ajax() {
	return (bool) ( ( isset( $_GET['bbp-ajax'] ) || isset( $_POST['bbp-ajax'] ) ) && ! empty( $_REQUEST['action'] ) );
}

/**
 * Hooked to the 'bbp_template_redirect' action, this is also the custom
 * theme-side AJAX handler.
 *
 * @since 2.3.0 bbPress (r4543)
 *
 * @return If not a bbPress AJAX request
 */
function bbp_do_ajax() {

	// Bail if not an AJAX request
	if ( ! bbp_is_ajax() ) {
		return;
	}

	// Set WordPress core AJAX constant
	define( 'DOING_AJAX', true );

	// Set the header content type
	@header( 'Content-Type: ' . get_option( 'html_type' ) . '; charset=' . get_option( 'blog_charset' ) );

	// Disable content sniffing in browsers that support it
	send_nosniff_header();

	// Everything is 200 OK.
	bbp_set_200();

	// Perform custom bbPress ajax
	do_action( 'bbp_ajax_' . sanitize_key( $_REQUEST['action'] ) );

	// All done
	wp_die( '0' );
}

/**
 * Helper method to return JSON response for the AJAX calls
 *
 * @since 2.3.0 bbPress (r4542)
 *
 * @param bool $success
 * @param string $content
 * @param array $extras
 */
function bbp_ajax_response( $success = false, $content = '', $status = -1, $extras = array() ) {

	// Set status to 200 if setting response as successful
	if ( ( true === $success ) && ( -1 === $status ) ) {
		$status = 200;
	}

	// Setup the response array
	$response = array(
		'success' => $success,
		'status'  => $status,
		'content' => $content
	);

	// Merge extra response parameters in
	if ( ! empty( $extras ) && is_array( $extras ) ) {
		$response = array_merge( $response, $extras );
	}

	// Send back the JSON
	@header( 'Content-type: application/json' );
	echo json_encode( $response );
	die();
}
