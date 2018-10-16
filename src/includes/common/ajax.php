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

		$ssl      = bbp_get_url_scheme();
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
 * This is largely taken from admin-ajax.php, but adapted specifically for
 * theme-side bbPress-only AJAX requests.
 *
 * @since 2.3.0 bbPress (r4543)
 *
 * @param string $action Sanitized action from bbp_post_request/bbp_get_request
 *
 * @return If not a bbPress AJAX request
 */
function bbp_do_ajax( $action = '' ) {

	// Bail if not a bbPress specific AJAX request
	if ( ! bbp_is_ajax() ) {
		return;
	}

	// Set WordPress core AJAX constant for back-compat
	if ( ! defined( 'DOING_AJAX' ) ) {
		define( 'DOING_AJAX', true );
	}

	// Setup AJAX headers
	bbp_ajax_headers();

	// Compat for targeted action hooks (without $action param)
	$action = empty( $action )
		? sanitize_key( $_REQUEST['action'] ) // isset checked by bbp_is_ajax()
		: $action;

	// Setup action key
	$key = "bbp_ajax_{$action}";

	// Bail if no action is registered
	if ( empty( $action ) || ! has_action( $key ) ) {
		wp_die( '0', 400 );
	}

	// Everything is 200 OK.
	bbp_set_200();

	// Execute custom bbPress AJAX action
	do_action( $key );

	// All done
	wp_die( '0' );
}

/**
 * Send headers for AJAX specific requests
 *
 * This was abstracted from bbp_do_ajax() for use in custom theme-side AJAX
 * implementations.
 *
 * @since 2.6.0 bbPress (r6757)
 */
function bbp_ajax_headers() {

	// Set the header content type
	@header( 'Content-Type: ' . get_option( 'html_type' ) . '; charset=' . get_option( 'blog_charset' ) );
	@header( 'X-Robots-Tag: noindex' );

	// Disable content sniffing in browsers that support it
	send_nosniff_header();

	// Disable browser caching for all AJAX requests
	nocache_headers();
}

/**
 * Helper method to return JSON response for bbPress AJAX calls
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
