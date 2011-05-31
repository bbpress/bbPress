<?php

/**
 * Main bbPress Akismet Class
 *
 * @package bbPress
 * @subpackage Akismet
 */

// Exit if accessed directly
if ( !defined( 'ABSPATH' ) ) exit;

if ( !class_exists( 'BBP_Akismet' ) ) :
/**
 * Loads Akismet extension
 *
 * @since bbPress (r3277)
 *
 * @package bbPress
 * @subpackage Akismet
 */
class BBP_Akismet {

	/**
	 * The main bbPress Akismet loader (PHP4 compat)
	 * 
	 * @since bbPress (r3277)
	 */
	function BBP_Akismet() {
		$this->__construct();
	}

	/**
	 * The main bbPress Akismet loader
	 * 
	 * @since bbPress (r3277)
	 * 
	 * @uses add_filter()
	 */
	function __construct() {
		$this->_setup_actions();
	}
	
	/**
	 * Setup the admin  hooks
	 *
	 * @since bbPress (r3277)
	 * @access private
	 *
	 * @uses add_filter() To add various filters
	 */
	function _setup_actions() {

		// Bail if no akismet
		if ( !defined( 'AKISMET_VERSION' ) ) return;

		// bbPress functions to check for spam
		$checks = array(
			'bbp_new_topic_pre_insert', // Topic check
			'bbp_new_reply_pre_insert'  // Reply check
		);

		// Add the checks
		foreach ( $checks as $function )
			add_filter( $function, array( $this, 'check_post' ), 9 );
	}

	/**
	 * Converts topic/reply data into Akismet comment checking format
	 *
	 * @since bbPress (r3277)
	 *
	 * @param string $post_data
	 * 
	 * @uses get_userdata() To get the user data
	 * @uses bbp_filter_anonymous_user_data() To get anonymous user data
	 * @uses bbPress_Akismet::maybe_spam() To check if post is spam
	 *
	 * @return string 
	 */
	function check_post( $post_data ) {

		// Post is not published
		if ( $post_data['post_status'] != 'publish' )
			return $post_data;

		// Extract post_data into variables
		extract( $post_data );

		// Get user data
		$userdata       = get_userdata( $post_author );
		$anonymous_data = bbp_filter_anonymous_post_data();

		// Put post_data back into usable array
		$post = array(
			'comment_post_ID'      => $post_parent,
			'comment_author'       => $anonymous_data ? $anonymous_data['bbp_anonymous_name']    : $userdata->display_name,
			'comment_author_email' => $anonymous_data ? $anonymous_data['bbp_anonymous_email']   : $userdata->user_email,
			'comment_author_url'   => $anonymous_data ? $anonymous_data['bbp_anonymous_website'] : $userdata->user_url,
			'comment_content'      => $post_content,
			'comment_type'         => $post_data['post_type'],
			'comment_parent'       => null,
			'user_ID'              => $post_author
		);

		// Check if spam
		if ( $this->maybe_spam( $post ) )
			$post_data['post_status'] = 'spam';

		// Return post data
		return $post_data;
	}
	
	/**
	 * Ping Akismet service and check for spam/ham response
	 *
	 * @since bbPress (r3277)
	 *
	 * @global string $akismet_api_host
	 * @global string $akismet_api_port
	 * @global oobject $akismet_last_comment
	 *
	 * @param object $post_data
	 *
	 * @return bool
	 */
	function maybe_spam( $post_data ) {
		global $akismet_api_host, $akismet_api_port, $akismet_last_comment;

		// Define variables
		$query_string = $response = '';

		// Populate post data
		$post                          = $post_data;
		$post['user_ip']               = $_SERVER['REMOTE_ADDR'];
		$post['user_agent']            = $_SERVER['HTTP_USER_AGENT'];
		$post['referrer']              = $_SERVER['HTTP_REFERER'];
		$post['blog']                  = get_option( 'home' );
		$post['blog_lang']             = get_locale();
		$post['blog_charset']          = get_option( 'blog_charset' );
		$post['permalink']             = get_permalink( $post['comment_post_ID'] );
		$post['user_role']             = akismet_get_user_roles( $post['user_ID'] );
		$post['akismet_comment_nonce'] = 'inactive';

		// Akismet Test Mode
		if ( akismet_test_mode() )
			$post['is_test'] = 'true';

		// Loop through _POST args and rekey strings
		foreach ( $_POST as $key => $value )
			if ( is_string( $value ) )
				$post["POST_{$key}"] = $value;

		// Keys to ignore
		$ignore = array( 'HTTP_COOKIE', 'HTTP_COOKIE2', 'PHP_AUTH_PW' );

		// Loop through _SERVER args and remove whitelisted keys
		foreach ( $_SERVER as $key => $value ) {

			// Key should not be ignored
			if ( !in_array( $key, $ignore ) && is_string( $value ) ) {
				$post["{$key}"] = $value;

			// Key should be ignored
			} else {
				$post["{$key}"] = '';
			}
		}

		// Ready...
		foreach ( $post as $key => $data )
			$query_string .= $key . '=' . urlencode( stripslashes( $data ) ) . '&';

		// Aim...
		$post_data['comment_as_submitted'] = $post;

		// Fire!
		$response = akismet_http_post( $query_string, $akismet_api_host, '/1.1/comment-check', $akismet_api_port );

		// Check the high-speed cam
		$post_data['akismet_result'] = $response[1];

		// Spam
		if ( 'true' == $response[1] ) {

			// Let Akismet do its thing
			do_action( 'akismet_spam_caught' );

			// Log the last comment
			$akismet_last_comment = $post_data;

			// This is spam
			return true;
		}

		// Log the last comment
		$akismet_last_comment = $post_data;

		// This is ham
		return false;
	}
}
endif;

/**
 * Loads Akismet in bbPress global namespace
 * 
 * @since bbPress (r3277)
 *
 * @global bbPress $bbp
 * @return If bbPress is not active
 */
function bbp_setup_akismet() {
	global $bbp;

	$bbp->plugins->akismet = new BBP_Akismet();
}

?>