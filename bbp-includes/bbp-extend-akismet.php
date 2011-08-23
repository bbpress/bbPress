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
	 * The main bbPress Akismet loader
	 *
	 * @since bbPress (r3277)
	 *
	 * @uses add_filter()
	 */
	function __construct() {
		$this->setup_actions();
	}

	/**
	 * Setup the admin hooks
	 *
	 * @since bbPress (r3277)
	 * @access private
	 *
	 * @uses add_filter() To add various filters
	 * @uses add_action() To add various actions
	 */
	function setup_actions() {

		// bbPress functions to check for spam
		$checks['check']  = array(
			'bbp_new_topic_pre_insert' => 1,  // Topic check
			'bbp_new_reply_pre_insert' => 1   // Reply check
		);

		// bbPress functions for spam and ham submissions
		$checks['submit'] = array(
			'bbp_spammed_topic'        => 10, // Spammed topic
			'bbp_unspammed_topic'      => 10, // Unspammed reply
			'bbp_spammed_reply'        => 10, // Spammed reply
			'bbp_unspammed_reply'      => 10, // Unspammed reply
		);

		// Add the checks
		foreach ( $checks as $type => $functions )
			foreach( $functions as $function => $priority )
				add_filter( $function, array( $this, $type . '_post'  ), $priority );

		// Update post meta
		add_action( 'wp_insert_post', array( $this, 'update_post_meta' ), 10, 2 );
	}

	/**
	 * Converts topic/reply data into Akismet comment checking format
	 *
	 * @since bbPress (r3277)
	 *
	 * @param string $post_data
	 *
	 * @global bbPress $bbp
	 *
	 * @uses get_userdata() To get the user data
	 * @uses bbp_filter_anonymous_user_data() To get anonymous user data
	 * @uses get_permalink() To get the permalink of the post parent
	 * @uses bbp_current_author_ip() To get the IP address of the current user
	 * @uses BBP_Akismet::maybe_spam() To check if post is spam
	 * @uses akismet_get_user_roles() To get the role(s) of the current user
	 * @uses do_action() To call the 'bbp_akismet_spam_caught' hook
	 * @uses add_filter() To call the 'bbp_new_reply_pre_set_terms' hook
	 *
	 * @return array Array of post data
	 */
	function check_post( $post_data ) {
		global $bbp;

		// Post is not published
		if ( 'publish' != $post_data['post_status'] )
			return $post_data;

		// Extract post_data into variables
		extract( $post_data );

		// Get user data
		$userdata       = get_userdata( $post_author );
		$anonymous_data = bbp_filter_anonymous_post_data();

		// Put post_data back into usable array
		$post = array(
			'comment_author'       => $anonymous_data ? $anonymous_data['bbp_anonymous_name']    : $userdata->display_name,
			'comment_author_email' => $anonymous_data ? $anonymous_data['bbp_anonymous_email']   : $userdata->user_email,
			'comment_author_url'   => $anonymous_data ? $anonymous_data['bbp_anonymous_website'] : $userdata->user_url,
			'comment_content'      => $post_content,
			'comment_post_ID'      => $post_parent,
			'comment_type'         => $post_type,
			'permalink'            => get_permalink( $post_parent ),
			'referrer'             => $_SERVER['HTTP_REFERER'],
			'user_agent'           => $_SERVER['HTTP_USER_AGENT'],
			'user_ID'              => $post_author,
			'user_ip'              => bbp_current_author_ip(),
			'user_role'            => akismet_get_user_roles( $post_author ),
		);

		// Check the post_data
		$post = $this->maybe_spam( $post );

		// Get the result
		$post_data['bbp_akismet_result'] = $post['bbp_akismet_result'];
		unset( $post['bbp_akismet_result'] );

		// Store the data as submitted
		$post_data['bbp_post_as_submitted'] = $post;

		// Spam
		if ( 'true' == $post_data['bbp_akismet_result'] ) {

			// Let plugins do their thing
			do_action( 'bbp_akismet_spam_caught' );

			// This is spam
			$post_data['post_status'] = $bbp->spam_status_id;

			// We don't want your spam tags here
			add_filter( 'bbp_new_reply_pre_set_terms', array( $this, 'filter_post_terms' ), 1, 3 );

			// @todo Spam counter?
		}

		// @todo Topic/reply moderation? No true/false response - 'pending' or 'draft'
		// @todo Auto-delete old spam?

		// Log the last post
		$this->last_post = $post_data;

		// Pass the data back to the filter
		return $post_data;
	}

	/**
	 * Submit a post for spamming or hamming
	 *
	 * @since bbPress ({unknown})
	 *
	 * @param int $post_id
	 *
	 * @global bbPress $bbp
	 * @global WP_Query $wpdb
	 * @global string $akismet_api_host
	 * @global string $akismet_api_port
	 * @global object $current_user
	 * @global object $current_site
	 *
	 * @uses current_filter() To get the reply_id
	 * @uses get_post() To get the post object
	 * @uses get_the_author_meta() To get the author meta
	 * @uses get_post_meta() To get the post meta
	 * @uses bbp_get_user_profile_url() To get a user's profile url
	 * @uses get_permalink() To get the permalink of the post_parent
	 * @uses akismet_get_user_roles() To get the role(s) of the post_author
	 * @uses bbp_current_author_ip() To get the IP address of the current user
	 * @uses BBP_Akismet::maybe_spam() To submit the post as ham or spam
	 * @uses update_post_meta() To update the post meta with some Akismet data
	 * @uses do_action() To call the 'bbp_akismet_submit_spam_post' and 'bbp_akismet_submit_ham_post' hooks
	 *
	 * @return array Array of existing topic terms
	 */
	function submit_post( $post_id = 0 ) {
		global $bbp, $wpdb, $akismet_api_host, $akismet_api_port, $current_user, $current_site;

		switch ( current_filter() ) {

			// Mysterious, and straight from the can
			case 'bbp_spammed_topic' :
			case 'bbp_spammed_reply' :
				$request_type = 'spam';
				break;

			// Honey-glazed, a straight off the bone
			case 'bbp_unspammed_topic' :
			case 'bbp_unspammed_reply' :
				$request_type = 'ham';
				break;

			// Possibly poison...
			default :
				return;
		}

		// Setup some variables
		$post_id = (int) $post_id;

		// Make sure we have a post
		if ( !$post = get_post( $post_id ) )
			return;

		// Bail if we're spamming, but the post_status isn't spam
		if ( ( 'spam' == $request_type ) && ( $bbp->spam_status_id != $post->post_status ) )
			return;

		// Set some default post_data
		$post_data = array(
			'comment_approved'     => $post->post_status,
			'comment_author'       => $post->post_author ? get_the_author_meta( 'display_name', $post->post_author ) : get_post_meta( $post_id, '_bbp_anonymous_name',    true ),
			'comment_author_email' => $post->post_author ? get_the_author_meta( 'email',        $post->post_author ) : get_post_meta( $post_id, '_bbp_anonymous_email',   true ),
			'comment_author_url'   => $post->post_author ? bbp_get_user_profile_url(            $post->post_author ) : get_post_meta( $post_id, '_bbp_anonymous_website', true ),
			'comment_content'      => $post->post_content,
			'comment_date'         => $post->post_date,
			'comment_ID'           => $post_id,
			'comment_post_ID'      => $post->post_parent,
			'comment_type'         => $post->post_type,
			'permalink'            => get_permalink( $post_id ),
			'user_ID'              => $post->post_author,
			'user_ip'              => get_post_meta( $post_id, '_bbp_author_ip', true ),
			'user_role'            => akismet_get_user_roles( $post->post_author ),
		);

		// Use the original version stored in post_meta if available
		$as_submitted = get_post_meta( $post_id, '_bbp_akismet_as_submitted', true );
		if ( $as_submitted && is_array( $as_submitted ) && isset( $as_submitted['comment_content'] ) )
			$post_data = array_merge( $post_data, $as_submitted );

		// Add the reporter IP address
		$post_data['reporter_ip']  = bbp_current_author_ip();

		// Add some reporter info
		if ( is_object( $current_user ) )
		    $post_data['reporter'] = $current_user->user_login;

		// Add the current site domain
		if ( is_object( $current_site ) )
			$post_data['site_domain'] = $current_site->domain;

		// Place your slide beneath the microscope
		$post_data = $this->maybe_spam( $post_data, 'submit', $request_type );

		// Manual user action
		if ( isset( $post_data['reporter'] ) ) {

			// What kind of action
			switch ( $request_type ) {

				// Spammy
				case 'spam' :
					$this->update_post_history( $post_id, sprintf( __( '%1$s reported this %2$s as spam', 'bbpress' ),     $post_data['reporter'], $post_data['comment_type'] ), 'report-spam' );
					update_post_meta( $post_id, '_bbp_akismet_user_result', 'true'                 );
					update_post_meta( $post_id, '_bbp_akismet_user',        $post_data['reporter'] );
					break;

				// Hammy
				case 'ham'  :
					$this->update_post_history( $post_id, sprintf( __( '%1$s reported this %2$s as not spam', 'bbpress' ), $post_data['reporter'], $post_data['comment_type'] ), 'report-ham'  );
					update_post_meta( $post_id, '_bbp_akismet_user_result', 'false'                 );
					update_post_meta( $post_id, '_bbp_akismet_user',         $post_data['reporter'] );

					// @todo Topic term revision history
					break;

				// Possible other actions
				default :
					break;
			}
		}

		do_action( 'bbp_akismet_submit_' . $request_type . '_post', $post_id, $post_data['bbp_akismet_result'] );
	}

	/**
	 * Ping Akismet service and check for spam/ham response
	 *
	 * @since bbPress (r3277)
	 *
	 * @param array $post_data
	 * @param string $check Accepts check|submit
	 * @param string $spam Accepts spam|ham
	 *
	 * @global string $akismet_api_host
	 * @global string $akismet_api_port
	 *
	 * @uses akismet_test_mode() To determine if Akismet is in test mode
	 * @uses akismet_http_post() To send data to the mothership for processing
	 *
	 * @return array Array of post data
	 */
	function maybe_spam( $post_data, $check = 'check', $spam = 'spam' ) {
		global $akismet_api_host, $akismet_api_port;

		// Define variables
		$query_string = $path = $response = '';

		// Populate post data
		$post_data['blog']         = get_option( 'home' );
		$post_data['blog_charset'] = get_option( 'blog_charset' );
		$post_data['blog_lang']    = get_locale();
		$post_data['referrer']     = $_SERVER['HTTP_REFERER'];
		$post_data['user_agent']   = $_SERVER['HTTP_USER_AGENT'];

		// Akismet Test Mode
		if ( akismet_test_mode() )
			$post_data['is_test'] = 'true';

		// Loop through _POST args and rekey strings
		foreach ( $_POST as $key => $value )
			if ( is_string( $value ) )
				$post_data['POST_' . $key] = $value;

		// Keys to ignore
		$ignore = array( 'HTTP_COOKIE', 'HTTP_COOKIE2', 'PHP_AUTH_PW' );

		// Loop through _SERVER args and remove whitelisted keys
		foreach ( $_SERVER as $key => $value ) {

			// Key should not be ignored
			if ( !in_array( $key, $ignore ) && is_string( $value ) ) {
				$post_data[$key] = $value;

			// Key should be ignored
			} else {
				$post_data[$key] = '';
			}
		}

		// Ready...
		foreach ( $post_data as $key => $data )
			$query_string .= $key . '=' . urlencode( stripslashes( $data ) ) . '&';

		// Aim...
		if ( 'check' == $check )
			$path = '/1.1/comment-check';
		elseif ( 'submit' == $check )
			$path = '/1.1/submit-' . $spam;

		// Fire!
		$response = akismet_http_post( $query_string, $akismet_api_host, $path, $akismet_api_port );

		// Check the high-speed cam
		$post_data['bbp_akismet_result'] = $response[1];

		// This is ham
		return $post_data;
	}

	/**
	 * Update post meta after a spam check
	 *
	 * @since bbPress (r3308)
	 *
	 * @param int $post_id
	 * @param object $post
	 *
	 * @global bbPress $bbp
	 * @global object $this->last_post
	 *
	 * @uses get_post() To get the post object
	 * @uses get_userdata() To get the user data
	 * @uses bbp_filter_anonymous_user_data() To get anonymous user data
	 * @uses update_post_meta() To update post meta with Akismet data
	 * @uses BBP_Akismet::update_post_history() To update post Akismet history
	 */
	function update_post_meta( $post_id = 0, $post ) {
		global $bbp;

		// Define local variable(s)
		$as_submitted = false;

		// Setup some variables
		$post_id = (int) $post_id;

		// Make sure we have a post object
		if ( !$post = get_post( $post_id ) )
			return;

		// Get user data
		$userdata       = get_userdata( $post->post_author );
		$anonymous_data = bbp_filter_anonymous_post_data();

		// Set up Akismet last post data
		if ( !empty( $this->last_post ) )
			$as_submitted = $this->last_post['bbp_post_as_submitted'];

		// wp_insert_post() might be called in other contexts. Make sure this is
		// the same topic/reply as was checked by BBP_Akismet::check_post()
		if ( is_object( $post ) && !empty( $this->last_post ) && is_array( $as_submitted ) ) {

			// More checks
			if (	intval( $as_submitted['comment_post_ID'] )    == intval( $post->post_parent )
					&&      $as_submitted['comment_author']       == ( $anonymous_data ? $anonymous_data['bbp_anonymous_name']  : $userdata->display_name )
					&&      $as_submitted['comment_author_email'] == ( $anonymous_data ? $anonymous_data['bbp_anonymous_email'] : $userdata->user_email   )
				) {

				// Normal result: true
				if ( $this->last_post['bbp_akismet_result'] == 'true' ) {

					// Leave a trail so other's know what we did
					update_post_meta( $post_id, '_bbp_akismet_result', 'true' );
					$this->update_post_history( $post_id, __( 'Akismet caught this post as spam', 'bbpress' ), 'check-spam' );

					// If post_status isn't the spam status, as expected, leave a note
					if ( $post->post_status != $bbp->spam_status_id )
						$this->update_post_history( $post_id, sprintf( __( 'Post status was changed to %s', 'bbpress' ), $post->post_status ), 'status-changed-' . $post->post_status );

				// Normal result: false
				} elseif ( $this->last_post['bbp_akismet_result'] == 'false' ) {

					// Leave a trail so other's know what we did
					update_post_meta( $post_id, '_bbp_akismet_result', 'false' );
					$this->update_post_history( $post_id, __( 'Akismet cleared this post', 'bbpress' ), 'check-ham' );

					// If post_status is the spam status, which isn't expected, leave a note
					if ( $post->post_status == $bbp->spam_status_id ) {

						// @todo Use wp_blacklist_check()

						$this->update_post_history( $post_id, sprintf( __( 'Post status was changed to %s', 'bbpress' ), $post->post_status ), 'status-changed-' . $post->post_status );
					}

				// Abnormal result: error
				} else {
					// Leave a trail so other's know what we did
					update_post_meta( $post_id, '_bbp_akismet_error', time() );
					$this->update_post_history( $post_id, sprintf( __( 'Akismet was unable to check this post (response: %s), will automatically retry again later.', 'bbpress' ), $this->last_post['bbp_akismet_result'] ), 'check-error' );
				}

				// Record the complete original data as submitted for checking
				if ( isset( $this->last_post['bbp_post_as_submitted'] ) )
					update_post_meta( $post_id, '_bbp_akismet_as_submitted', $this->last_post['bbp_post_as_submitted'] );
			}
		}
	}

	/**
	 * Update a post's Akismet history
	 *
	 * @since bbPress (r3308)
	 *
	 * @param int $post_id
	 * @param string $message
	 * @param string $event
	 *
	 * @uses wp_get_current_user() To get the current_user object
	 * @uses add_post_meta() Add Akismet post history
	 */
	function update_post_history( $post_id = 0, $message = null, $event = null ) {

		// Define local variable(s)
		$user = '';

		// Get the current user
		$current_user = wp_get_current_user();

		// Get the user's login name if possible
		if ( is_object( $current_user ) && isset( $current_user->user_login ) )
			$user = $current_user->user_login;

		// Setup the event to be saved
		$event = array(
			'time'    => akismet_microtime(),
			'message' => $message,
			'event'   => $event,
			'user'    => $user,
		);

		// Save the event data
		add_post_meta( $post_id, '_bbp_akismet_history', $event );
	}

	/**
	 * Get a post's Akismet history
	 *
	 * @since bbPress (r3308)
	 *
	 * @param int $post_id
	 *
	 * @uses wp_get_current_user() To get the current_user object
	 * @uses get_post_meta() Get a post's Akismet history
	 *
	 * @return array Array of a post's Akismet history
	 */
	function get_post_history( $post_id = 0 ) {

		// Retrieve any previous history
		$history = get_post_meta( $post_id, '_bbp_akismet_history' );

		// Sort it by the time recorded
		usort( $history, 'akismet_cmp_time' );

		return $history;
	}

	/**
	 * Handle any terms submitted with a post flagged as spam
	 *
	 * @since bbPress (r3308)
	 *
	 * @param string $terms Comma-separated list of terms
	 * @param int $topic_id
	 * @param int $reply_id
	 *
	 * @global bbPress $bbp
	 *
	 * @uses bbp_get_reply_id() To get the reply_id
	 * @uses bbp_get_topic_id() To get the topic_id
	 * @uses wp_get_object_terms() To a post's current terms
	 * @uses update_post_meta() To add spam terms to post meta
	 *
	 * @return array Array of existing topic terms
	 */
	function filter_post_terms( $terms = '', $topic_id = 0, $reply_id = 0 ) {
		global $bbp;

		// Validate the reply_id and topic_id
		$reply_id = bbp_get_reply_id( $reply_id );
		$topic_id = bbp_get_topic_id( $topic_id );

		// Get any pre-existing terms
		$existing_terms = wp_get_object_terms( $topic_id, bbp_get_topic_tag_tax_id(), array( 'fields' => 'names' ) );

		// Save the terms for later in case the reply gets hammed
		if ( !empty( $terms ) )
			update_post_meta( $reply_id, '_bbp_akismet_spam_terms', $terms );

		// Keep the topic tags the same for now
		return $existing_terms;
	}

}
endif;

/**
 * Loads Akismet inside the bbPress global class
 *
 * @since bbPress (r3277)
 *
 * @global bbPress $bbp
 * @return If bbPress is not active
 */
function bbp_setup_akismet() {
	global $bbp;

	// Bail if no akismet
	if ( !defined( 'AKISMET_VERSION' ) ) return;

	// Instantiate Akismet for bbPress
	$bbp->extend->akismet = new BBP_Akismet();
}

?>
