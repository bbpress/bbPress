<?php

/**
 * Main bbPress Akismet Class
 *
 * @package bbPress
 * @subpackage Akismet
 */

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'BBP_Akismet' ) ) :
/**
 * Loads Akismet extension
 *
 * @since 2.0.0 bbPress (r3277)
 *
 * @package bbPress
 * @subpackage Akismet
 */
class BBP_Akismet {

	/**
	 * The main bbPress Akismet loader
	 *
	 * @since 2.0.0 bbPress (r3277)
	 */
	public function __construct() {
		$this->setup_actions();
	}

	/**
	 * Setup the admin hooks
	 *
	 * @since 2.0.0 bbPress (r3277)
	 *
	 * @access private
	 */
	private function setup_actions() {

		// Prevent debug notices
		$checks = array();

		// bbPress functions to check for spam
		$checks['check']  = array(
			'bbp_new_topic_pre_insert'  => 1,  // New topic check
			'bbp_new_reply_pre_insert'  => 1,  // New reply check
			'bbp_edit_topic_pre_insert' => 1,  // Edit topic check
			'bbp_edit_reply_pre_insert' => 1   // Edit reply check
		);

		// bbPress functions for spam and ham submissions
		$checks['submit'] = array(
			'bbp_spammed_topic'   => 10, // Spammed topic
			'bbp_unspammed_topic' => 10, // Unspammed reply
			'bbp_spammed_reply'   => 10, // Spammed reply
			'bbp_unspammed_reply' => 10, // Unspammed reply
		);

		// Add the checks
		foreach ( $checks as $type => $functions ) {
			foreach ( $functions as $function => $priority ) {
				add_filter( $function, array( $this, $type . '_post'  ), $priority );
			}
		}

		// Update post meta
		add_action( 'wp_insert_post', array( $this, 'update_post_meta' ), 10, 2 );

		// Cleanup
		add_action( 'akismet_scheduled_delete', array( $this, 'delete_old_spam' ) );
		add_action( 'akismet_scheduled_delete', array( $this, 'delete_old_spam_meta' ) );
		add_action( 'akismet_scheduled_delete', array( $this, 'delete_orphaned_spam_meta' ) );

		// Admin
		if ( is_admin() ) {
			add_action( 'add_meta_boxes', array( $this, 'add_metaboxes' ) );
		}
	}

	/**
	 * Converts topic/reply data into Akismet comment checking format
	 *
	 * @since 2.0.0 bbPress (r3277)
	 *
	 * @param array $post_data
	 *
	 * @return array Array of post data
	 */
	public function check_post( $post_data = array() ) {

		// Define local variables
		$user_data = array();
		$post_permalink = '';

		// Cast the post_author to 0 if it's empty
		if ( empty( $post_data['post_author'] ) ) {
			$post_data['post_author'] = 0;
		}

		/** Author ************************************************************/

		$user_data['last_active'] = '';
		$user_data['registered']  = date( 'Y-m-d H:i:s');
		$user_data['total_posts'] = (int) bbp_get_user_post_count( $post_data['post_author'] );

		// Get user data
		$userdata       = get_userdata( $post_data['post_author'] );
		$anonymous_data = bbp_filter_anonymous_post_data();

		// Author is anonymous
		if ( ! bbp_has_errors() ) {
			$user_data['name']    = $anonymous_data['bbp_anonymous_name'];
			$user_data['email']   = $anonymous_data['bbp_anonymous_email'];
			$user_data['website'] = $anonymous_data['bbp_anonymous_website'];

		// Author is logged in
		} elseif ( ! empty( $userdata ) ) {
			$user_data['name']       = $userdata->display_name;
			$user_data['email']      = $userdata->user_email;
			$user_data['website']    = $userdata->user_url;
			$user_data['registered'] = $userdata->user_registered;

		// Missing author data, so set some empty strings
		} else {
			$user_data['name']    = '';
			$user_data['email']   = '';
			$user_data['website'] = '';
		}

		/** Post **************************************************************/

		if ( ! empty( $post_data['post_parent'] ) ) {

			// Use post parent for permalink
			$post_permalink = get_permalink( $post_data['post_parent'] );

			// Use post parent to get datetime of last reply on this topic
			$reply_id = bbp_get_topic_last_reply_id( $post_data['post_parent'] );
			if ( ! empty( $reply_id ) ) {
				$user_data['last_active'] = get_post_field( 'post_date', $reply_id );
			}
		}

		// Pass title & content together into comment content
		$_post_content = trim( $post_data['post_title'] . "\n\n" . $post_data['post_content'] );

		// Check if the post data is spammy...
		$_post = $this->maybe_spam( array(
			'comment_author'                 => $user_data['name'],
			'comment_author_email'           => $user_data['email'],
			'comment_author_url'             => $user_data['website'],
			'comment_content'                => $_post_content,
			'comment_post_ID'                => $post_data['post_parent'],
			'comment_type'                   => $post_data['post_type'],
			'comment_total'                  => $user_data['total_posts'],
			'comment_last_active_gmt'        => $user_data['last_active'],
			'comment_account_registered_gmt' => $user_data['registered'],
			'permalink'                      => $post_permalink,
			'referrer'                       => wp_get_raw_referer(),
			'user_agent'                     => bbp_current_author_ua(),
			'user_ID'                        => $post_data['post_author'],
			'user_ip'                        => bbp_current_author_ip(),
			'user_role'                      => $this->get_user_roles( $post_data['post_author'] ),
		) );

		// Set the results (from maybe_spam() above)
		$post_data['bbp_akismet_result_headers'] = $_post['bbp_akismet_result_headers'];
		$post_data['bbp_akismet_result']         = $_post['bbp_akismet_result'];
		$post_data['bbp_post_as_submitted']      = $_post;

		// Avoid recursion by unsetting results from post-as-submitted
		unset(
			$post_data['bbp_post_as_submitted']['bbp_akismet_result_headers'],
			$post_data['bbp_post_as_submitted']['bbp_akismet_result']
		);

		// Allow post_data to be manipulated
		$post_data = apply_filters( 'bbp_akismet_check_post', $post_data );

		// Parse and log the last response
		$this->last_post = $this->parse_response( $post_data );

		// Return the last response back to the filter
		return $this->last_post;
	}

	/**
	 * Parse the response from the Akismet service, and alter the post data as
	 * necessary. For example, switch the status to `spam` if spammy.
	 *
	 * Note: this method also is responsible for allowing users who can moderate to
	 * never have their posts marked as spam. This is because they are "trusted"
	 * users. However, their posts are still sent to Akismet to be checked.
	 *
	 * @since 2.6.0 bbPress (r6873)
	 *
	 * @param array $post_data
	 *
	 * @return array
	 */
	private function parse_response( $post_data = array() ) {

		// Get the parent ID of the post as submitted
		$parent_id = ! empty( $post_data['bbp_post_as_submitted']['comment_post_ID'] )
			? absint( $post_data['bbp_post_as_submitted']['comment_post_ID'] )
			: 0;

		// Allow moderators to skip spam (includes per-forum moderators via $parent_id)
		$skip_spam = current_user_can( 'moderate', $parent_id );

		// Bail early if current user can skip spam enforcement
		if ( apply_filters( 'bbp_bypass_spam_enforcement', $skip_spam, $post_data ) ) {
			return $post_data;
		}

		// Discard obvious spam
		if ( get_option( 'akismet_strictness' ) ) {

			// Akismet is 100% confident this is spam
			if (
				! empty( $post_data['bbp_akismet_result_headers']['x-akismet-pro-tip'] )
				&&
				( 'discard' === $post_data['bbp_akismet_result_headers']['x-akismet-pro-tip'] )
			) {

				// URL to redirect to (current, or forum root)
				$redirect_to = ( ! empty( $_SERVER['HTTP_HOST'] ) && ! empty( $_SERVER['REQUEST_URI'] ) )
					? bbp_get_url_scheme() . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']
					: bbp_get_root_url();

				// Do the redirect (post data not saved!)
				bbp_redirect( $redirect_to );
			}
		}

		// Result is spam, so set the status as such
		if ( 'true' === $post_data['bbp_akismet_result'] ) {

			// Let plugins do their thing
			do_action( 'bbp_akismet_spam_caught' );

			// Set post_status to spam
			$post_data['post_status'] = bbp_get_spam_status_id();

			// Filter spammy tags into meta data
			add_filter( 'bbp_new_reply_pre_set_terms', array( $this, 'filter_post_terms' ), 1, 3 );
		}

		// Return the (potentially modified) post data
		return $post_data;
	}

	/**
	 * Submit a post for spamming or hamming
	 *
	 * @since 2.0.0 bbPress (r3277)
	 *
	 * @param int $post_id
	 *
	 * @global string $akismet_api_host
	 * @global string $akismet_api_port
	 * @global object $current_user
	 * @global object $current_site
	 *
	 * @return array Array of existing topic terms
	 */
	public function submit_post( $post_id = 0 ) {
		global $current_user, $current_site;

		// Innocent until proven guilty
		$request_type   = 'ham';
		$current_filter = current_filter();

		// Check this filter and adjust the $request_type accordingly
		switch ( $current_filter ) {

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
		$_post = get_post( $post_id );

		// Bail if get_post() fails
		if ( empty( $_post ) ) {
			return;
		}

		// Bail if we're spamming, but the post_status isn't spam
		if ( ( 'spam' === $request_type ) && ( bbp_get_spam_status_id() !== $_post->post_status ) ) {
			return;
		}

		// Pass title & content together into comment content
		$_post_content = trim( $_post->post_title . "\n\n" . $_post->post_content );

		// Set some default post_data
		$post_data = array(
			'comment_approved'     => $_post->post_status,
			'comment_author'       => $_post->post_author ? get_the_author_meta( 'display_name', $_post->post_author ) : get_post_meta( $post_id, '_bbp_anonymous_name',    true ),
			'comment_author_email' => $_post->post_author ? get_the_author_meta( 'email',        $_post->post_author ) : get_post_meta( $post_id, '_bbp_anonymous_email',   true ),
			'comment_author_url'   => $_post->post_author ? bbp_get_user_profile_url(            $_post->post_author ) : get_post_meta( $post_id, '_bbp_anonymous_website', true ),
			'comment_content'      => $_post_content,
			'comment_date_gmt'     => $_post->post_date_gmt,
			'comment_ID'           => $post_id,
			'comment_post_ID'      => $_post->post_parent,
			'comment_type'         => $_post->post_type,
			'permalink'            => get_permalink( $post_id ),
			'user_ID'              => $_post->post_author,
			'user_ip'              => get_post_meta( $post_id, '_bbp_author_ip', true ),
			'user_role'            => $this->get_user_roles( $_post->post_author ),
		);

		// Use the original version stored in post_meta if available
		$as_submitted = get_post_meta( $post_id, '_bbp_akismet_as_submitted', true );
		if ( $as_submitted && is_array( $as_submitted ) && isset( $as_submitted['comment_content'] ) ) {
			$post_data = array_merge( $post_data, $as_submitted );
		}

		// Add the reporter IP address
		$post_data['reporter_ip']  = bbp_current_author_ip();

		// Add some reporter info
		if ( is_object( $current_user ) ) {
			$post_data['reporter'] = $current_user->user_login;
		}

		// Add the current site domain
		if ( is_object( $current_site ) ) {
			$post_data['site_domain'] = $current_site->domain;
		}

		// Place your slide beneath the microscope
		$post_data = $this->maybe_spam( $post_data, 'submit', $request_type );

		// Manual user action
		if ( isset( $post_data['reporter'] ) ) {

			// What kind of action
			switch ( $request_type ) {

				// Spammy
				case 'spam' :
					if ( 'topic' === $post_data['comment_type'] ) {
						/* translators: %s: reporter name */
						$message = sprintf( esc_html__( '%s reported this topic as spam', 'bbpress' ),
							$post_data['reporter']
						);
					} elseif ( 'reply' === $post_data['comment_type'] ) {
						/* translators: %s: reporter name */
						$message = sprintf( esc_html__( '%s reported this reply as spam', 'bbpress' ),
							$post_data['reporter']
						);
					} else {
						/* translators: 1: reporter name, 2: comment type */
						$message = sprintf( esc_html__( '%1$s reported this %2$s as spam', 'bbpress' ),
							$post_data['reporter'],
							$post_data['comment_type']
						);
					}

					$this->update_post_history( $post_id, $message, 'report-spam' );
					update_post_meta( $post_id, '_bbp_akismet_user_result', 'true'                 );
					update_post_meta( $post_id, '_bbp_akismet_user',        $post_data['reporter'] );
					break;

				// Hammy
				case 'ham' :
					if ( 'topic' === $post_data['comment_type'] ) {
						/* translators: %s: reporter name */
						$message = sprintf( esc_html__( '%s reported this topic as not spam', 'bbpress' ),
							$post_data['reporter']
						);
					} elseif ( 'reply' === $post_data['comment_type'] ) {
						/* translators: %s: reporter name */
						$message = sprintf( esc_html__( '%s reported this reply as not spam', 'bbpress' ),
							$post_data['reporter']
						);
					} else {
						/* translators: 1: reporter name, 2: comment type */
						$message = sprintf( esc_html__( '%1$s reported this %2$s as not spam', 'bbpress' ),
							$post_data['reporter'],
							$post_data['comment_type']
						);
					}

					$this->update_post_history( $post_id, $message, 'report-ham' );
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
	 * @since 2.0.0 bbPress (r3277)
	 *
	 * @param array $post_data
	 * @param string $check Accepts check|submit
	 * @param string $spam Accepts spam|ham
	 *
	 * @global string $akismet_api_host
	 * @global string $akismet_api_port
	 *
	 * @return array Array of post data
	 */
	private function maybe_spam( $post_data = array(), $check = 'check', $spam = 'spam' ) {
		global $akismet_api_host, $akismet_api_port;

		// Define variables
		$query_string = $path = '';
		$response = array( '', '' );

		// Make sure post data is an array
		if ( ! is_array( $post_data ) ) {
			$post_data = array();
		}

		// Populate post data
		$post_data['blog']         = get_option( 'home' );
		$post_data['blog_charset'] = get_option( 'blog_charset' );
		$post_data['blog_lang']    = get_locale();
		$post_data['referrer']     = wp_get_raw_referer();
		$post_data['user_agent']   = bbp_current_author_ua();

		// Loop through _POST args and rekey strings
		if ( ! empty( $_POST ) && is_countable( $_POST ) ) {
			foreach ( $_POST as $key => $value ) {
				if ( is_string( $value ) ) {
					$post_data[ 'POST_' . $key ] = $value;
				}
			}
		}

		// Loop through _SERVER args and remove allowed keys
		if ( ! empty( $_SERVER ) && is_countable( $_SERVER ) ) {

			// Keys to ignore
			$ignore = array( 'HTTP_COOKIE', 'HTTP_COOKIE2', 'PHP_AUTH_PW' );

			foreach ( $_SERVER as $key => $value ) {

				// Key should not be ignored
				if ( ! in_array( $key, $ignore, true ) && is_string( $value ) ) {
					$post_data[ $key ] = $value;

				// Key should be ignored
				} else {
					$post_data[ $key ] = '';
				}
			}
		}

		// Encode post data
		if ( ! empty( $post_data ) && is_countable( $post_data ) ) {
			foreach ( $post_data as $key => $data ) {
				$query_string .= $key . '=' . urlencode( wp_unslash( $data ) ) . '&';
			}
		}

		// Only accepts spam|ham
		if ( ! in_array( $spam, array( 'spam', 'ham' ), true ) ) {
			$spam = 'spam';
		}

		// Setup the API route
		if ( 'check' === $check ) {
			$path = '/1.1/comment-check';
		} elseif ( 'submit' === $check ) {
			$path = '/1.1/submit-' . $spam;
		}

		// Send data to Akismet
		if ( ! apply_filters( 'bbp_bypass_check_for_spam', false, $post_data ) ) {
			$response = $this->http_post( $query_string, $akismet_api_host, $path, $akismet_api_port );
		}

		// Set the result headers
		$post_data['bbp_akismet_result_headers'] = ! empty( $response[0] )
			? $response[0] // raw
			: esc_html__( 'No response', 'bbpress' );

		// Set the result
		$post_data['bbp_akismet_result'] = ! empty( $response[1] )
			? $response[1] // raw
			: esc_html__( 'No response', 'bbpress' );

		// Return the post data, with the results of the external Akismet request
		return $post_data;
	}

	/**
	 * Update post meta after a spam check
	 *
	 * @since 2.0.0 bbPress (r3308)
	 *
	 * @param int $post_id
	 * @param object $_post
	 *
	 * @global object $this->last_post
	 */
	public function update_post_meta( $post_id = 0, $_post = false ) {

		// Define local variable(s)
		$as_submitted = false;

		// Setup some variables
		$post_id = (int) $post_id;

		// Ensure we have a post object
		if ( empty( $_post ) ) {
			$_post = get_post( $post_id );
		}

		// Set up Akismet last post data
		if ( ! empty( $this->last_post['bbp_post_as_submitted'] ) ) {
			$as_submitted = $this->last_post['bbp_post_as_submitted'];
		}

		// wp_insert_post() might be called in other contexts. Ensure this is
		// the same topic/reply as was checked by BBP_Akismet::check_post()
		if ( is_object( $_post ) && ! empty( $this->last_post ) && is_array( $as_submitted ) ) {

			// Get user data
			$userdata       = get_userdata( $_post->post_author );
			$anonymous_data = bbp_filter_anonymous_post_data();

			// Which name?
			$name = ! empty( $anonymous_data['bbp_anonymous_name'] )
				? $anonymous_data['bbp_anonymous_name']
				: $userdata->display_name;

			// Which email?
			$email = ! empty( $anonymous_data['bbp_anonymous_email'] )
				? $anonymous_data['bbp_anonymous_email']
				: $userdata->user_email;

			// More checks
			if (

				// Post matches
				( intval( $as_submitted['comment_post_ID'] ) === intval( $_post->post_parent ) )

				&&

				// Name matches
				( $as_submitted['comment_author'] === $name )

				&&

				// Email matches
				( $as_submitted['comment_author_email'] === $email )
			) {

				// Delete old content daily
				if ( ! wp_next_scheduled( 'akismet_scheduled_delete' ) ) {
					wp_schedule_event( time(), 'daily', 'akismet_scheduled_delete' );
				}

				// Normal result: true
				if ( ! empty( $this->last_post['bbp_akismet_result'] ) && ( $this->last_post['bbp_akismet_result'] === 'true' ) ) {

					// Leave a trail so other's know what we did
					update_post_meta( $post_id, '_bbp_akismet_result', 'true' );
					$this->update_post_history(
						$post_id,
						esc_html__( 'Akismet caught this post as spam', 'bbpress' ),
						'check-spam'
					);

					// If post_status isn't the spam status, as expected, leave a note
					if ( bbp_get_spam_status_id() !== $_post->post_status ) {
						$this->update_post_history(
							$post_id,
							sprintf(
								esc_html__( 'Post status was changed to %s', 'bbpress' ),
								$_post->post_status
							),
							'status-changed-' . $_post->post_status
						);
					}

				// Normal result: false
				} elseif ( ! empty( $this->last_post['bbp_akismet_result'] ) && ( $this->last_post['bbp_akismet_result'] === 'false' ) ) {

					// Leave a trail so other's know what we did
					update_post_meta( $post_id, '_bbp_akismet_result', 'false' );
					$this->update_post_history(
						$post_id,
						esc_html__( 'Akismet cleared this post as not spam', 'bbpress' ),
						'check-ham'
					);

					// If post_status is the spam status, which isn't expected, leave a note
					if ( bbp_get_spam_status_id() === $_post->post_status ) {
						$this->update_post_history(
							$post_id,
							sprintf(
								esc_html__( 'Post status was changed to %s', 'bbpress' ),
								$_post->post_status
							),
							'status-changed-' . $_post->post_status
						);
					}

				// Abnormal result: error
				} else {
					// Leave a trail so other's know what we did
					update_post_meta( $post_id, '_bbp_akismet_error', time() );
					$this->update_post_history(
						$post_id,
						sprintf(
							esc_html__( 'Akismet was unable to check this post (response: %s), will automatically retry again later.', 'bbpress' ),
							$this->last_post['bbp_akismet_result']
						),
						'check-error'
					);
				}

				// Record the complete original data as submitted for checking
				if ( isset( $this->last_post['bbp_post_as_submitted'] ) ) {
					update_post_meta(
						$post_id,
						'_bbp_akismet_as_submitted',
						$this->last_post['bbp_post_as_submitted']
					);
				}
			}
		}
	}

	/**
	 * Update Akismet history of a Post
	 *
	 * @since 2.0.0 bbPress (r3308)
	 *
	 * @param int $post_id
	 * @param string $message
	 * @param string $event
	 */
	private function update_post_history( $post_id = 0, $message = null, $event = null ) {

		// Define local variable(s)
		$user = '';

		// Get the current user
		$current_user = wp_get_current_user();

		// Get the user's login name if possible
		if ( is_object( $current_user ) && isset( $current_user->user_login ) ) {
			$user = $current_user->user_login;
		}

		// This used to be akismet_microtime() but it was removed in 3.0
		$mtime        = explode( ' ', microtime() );
		$message_time = $mtime[1] + $mtime[0];

		// Setup the event to be saved
		$event = array(
			'time'    => $message_time,
			'message' => $message,
			'event'   => $event,
			'user'    => $user,
		);

		// Save the event data
		add_post_meta( $post_id, '_bbp_akismet_history', $event );
	}

	/**
	 * Get the Akismet history of a Post
	 *
	 * @since 2.0.0 bbPress (r3308)
	 *
	 * @param int $post_id
	 *
	 * @return array Array of Akismet history
	 */
	public function get_post_history( $post_id = 0 ) {

		// Retrieve any previous history
		$history = get_post_meta( $post_id, '_bbp_akismet_history' );

		// Sort it by the time recorded
		usort( $history, 'akismet_cmp_time' );

		return $history;
	}

	/**
	 * Handle any terms submitted with a post flagged as spam
	 *
	 * @since 2.0.0 bbPress (r3308)
	 *
	 * @param string $terms Comma-separated list of terms
	 * @param int $topic_id
	 * @param int $reply_id
	 *
	 * @return array Array of existing topic terms
	 */
	public function filter_post_terms( $terms = '', $topic_id = 0, $reply_id = 0 ) {

		// Validate the reply_id and topic_id
		$reply_id = bbp_get_reply_id( $reply_id );
		$topic_id = bbp_get_topic_id( $topic_id );

		// Get any pre-existing terms
		$existing_terms = bbp_get_topic_tag_names( $topic_id );

		// Save the terms for later in case the reply gets hammed
		if ( ! empty( $terms ) ) {
			update_post_meta( $reply_id, '_bbp_akismet_spam_terms', $terms );
		}

		// Keep the topic tags the same for now
		return $existing_terms;
	}

	/**
	 * Submit data to Akismet service with unique bbPress User Agent
	 *
	 * This code is directly taken from the akismet_http_post() function and
	 * documented to bbPress 2.0 standard.
	 *
	 * @since 2.0.0 bbPress (r3466)
	 *
	 * @param string $request The request we are sending
	 * @param string $host The host to send our request to
	 * @param string $path The path from the host
	 * @param string $port The port to use
	 * @param string $ip Optional Override $host with an IP address
	 * @return mixed WP_Error on error, array on success, empty on failure
	 */
	private function http_post( $request, $host, $path, $port = 80, $ip = '' ) {

		// Preload required variables
		$bbp_version  = bbp_get_version();
		$ak_version   = constant( 'AKISMET_VERSION' );
		$http_host    = $host;
		$blog_charset = get_option( 'blog_charset' );

		// User Agent & Content Type
		$akismet_ua   = "bbPress/{$bbp_version} | Akismet/{$ak_version}";
		$content_type = 'application/x-www-form-urlencoded; charset=' . $blog_charset;

		// Use specific IP (if provided)
		if ( ! empty( $ip ) && long2ip( ip2long( $ip ) ) ) {
			$http_host = $ip;
		}

		// Setup the arguments
		$http_args = array(
			'httpversion' => '1.0',
			'timeout'     => 15,
			'body'        => $request,
			'headers'     => array(
				'Content-Type' => $content_type,
				'Host'         => $host,
				'User-Agent'   => $akismet_ua
			)
		);

		// Return the response
		return $this->get_response( $http_host . $path, $http_args );
	}

	/**
	 * Handles the repeated calls to wp_remote_post(), including SSL support.
	 *
	 * @since 2.6.7 (bbPress r7194)
	 *
	 * @param string $host_and_path Scheme-less URL
	 * @param array  $http_args     Array of arguments for wp_remote_post()
	 * @return array
	 */
	private function get_response( $host_and_path = '', $http_args = array() ) {

		// Default variables
		$akismet_url = $http_akismet_url = 'http://' . $host_and_path;
		$is_ssl = $ssl_failed = false;
		$now = time();

		// Check if SSL requests were disabled fewer than 24 hours ago
		$ssl_disabled_time = get_option( 'akismet_ssl_disabled' );

		// Clean-up if 24 hours have passed
		if ( ! empty( $ssl_disabled_time ) && ( $ssl_disabled_time < ( $now - DAY_IN_SECONDS ) ) ) {
			delete_option( 'akismet_ssl_disabled' );
			$ssl_disabled_time = false;
		}

		// Maybe HTTPS if not disabled
		if ( empty( $ssl_disabled_time ) && ( $is_ssl = wp_http_supports( array( 'ssl' ) ) ) ) {
			$akismet_url = set_url_scheme( $akismet_url, 'https' );
		}

		// Initial remote request
		$response = wp_remote_post( $akismet_url, $http_args );

		// Initial request produced an error, so retry...
		if ( ! empty( $is_ssl ) && is_wp_error( $response ) ) {

			// Intermittent connection problems may cause the first HTTPS
			// request to fail and subsequent HTTP requests to succeed randomly.
			// Retry the HTTPS request once before disabling SSL for a time.
			$response = wp_remote_post( $akismet_url, $http_args );

			// SSL request failed twice, so try again without it
			if ( is_wp_error( $response ) ) {
				$response   = wp_remote_post( $http_akismet_url, $http_args );
				$ssl_failed = true;
			}
		}

		// Bail if errored
		if ( is_wp_error( $response ) ) {
			return array( '', '' );
		}

		// Maybe disable SSL for future requests
		if ( ! empty( $ssl_failed ) ) {
			update_option( 'akismet_ssl_disabled', $now );
		}

		// No errors so return response
		return array(
			$response['headers'],
			$response['body']
		);
	}

	/**
	 * Return a user's roles on this site (including super_admin)
	 *
	 * @since 2.3.0 bbPress (r4812)
	 *
	 * @param int $user_id
	 *
	 * @return boolean
	 */
	private function get_user_roles( $user_id = 0 ) {

		// Default return value
		$roles = array();

		// Bail if cannot query the user
		if ( ! class_exists( 'WP_User' ) || empty( $user_id ) ) {
			return false;
		}

		// User ID
		$user = new WP_User( $user_id );
		if ( isset( $user->roles ) ) {
			$roles = (array) $user->roles;
		}

		// Super admin
		if ( is_multisite() && is_super_admin( $user_id ) ) {
			$roles[] = 'super_admin';
		}

		return implode( ',', $roles );
	}

	/** Admin *****************************************************************/

	/**
	 * Add Aksimet History meta-boxes to topics and replies
	 *
	 * @since 2.4.0 bbPress (r5049)
	 */
	public function add_metaboxes() {

		// Topics
		add_meta_box(
			'bbp_akismet_topic_history',
			__( 'Akismet History', 'bbpress' ),
			array( $this, 'history_metabox' ),
			bbp_get_topic_post_type(),
			'normal',
			'core'
		);

		// Replies
		add_meta_box(
			'bbp_akismet_reply_history',
			__( 'Akismet History', 'bbpress' ),
			array( $this, 'history_metabox' ),
			bbp_get_reply_post_type(),
			'normal',
			'core'
		);
	}

	/**
	 * Output for Akismet History meta-box
	 *
	 * @since 2.4.0 bbPress (r5049)
	 */
	public function history_metabox() {

		// Post ID
		$history = $this->get_post_history( get_the_ID() ); ?>

		<div class="akismet-history" style="margin: 13px 0;">

			<?php if ( ! empty( $history ) ) : ?>

				<table>
					<tbody>

						<?php foreach ( $history as $row ) : ?>

							<tr>
								<td style="color: #999; text-align: right; white-space: nowrap;">
									<span title="<?php echo esc_attr( date( 'D d M Y @ h:i:m a', $row['time'] ) . ' GMT' ); ?>">
										<?php bbp_time_since( $row['time'], false, true ); ?>
									</span>
								</td>
								<td style="padding-left: 5px;">
									<?php echo esc_html( $row['message'] ); ?>
								</td>
							</tr>

						<?php endforeach; ?>
					</tbody>
				</table>

			<?php else : ?>

				<p><?php esc_html_e( 'No recorded history. Akismet has not checked this post.', 'bbpress' ); ?></p>

			<?php endif; ?>

		</div>

		<?php
	}

	/**
	 * Get the number of rows to delete in a single clean-up query.
	 *
	 * @since 2.6.9 bbPress (r7225)
	 *
	 * @param string $filter The name of the filter to run.
	 * @return int
	 */
	public function get_delete_limit( $filter = '' ) {

		// Default filter
		if ( empty( $filter ) ) {
			$filter = '_bbp_akismet_delete_spam_limit';
		}

		/**
		 * Determines how many rows will be deleted in each batch.
		 *
		 * @param int The number of rows. Default 1000.
		 */
		$delete_limit = (int) apply_filters( $filter, 1000 );

		// Validate and return the deletion limit
		return max( 1, $delete_limit );
	}

	/**
	 * Get the interval (in days) for spam to remain in the queue.
	 *
	 * @since 2.6.9 bbPress (r7225)
	 *
	 * @param string $filter The name of the filter to run.
	 * @return int
	 */
	public function get_delete_interval( $filter = '' ) {

		// Default filter
		if ( empty( $filter ) ) {
			$filter = '_bbp_akismet_delete_spam_interval';
		}

		/**
		 * Determines how many days a piece of spam will be left in the Spam
		 * queue before being deleted.
		 *
		 * @param int The number of days. Default 15.
		 */
		$delete_interval = (int) apply_filters( $filter, 15 );

		// Validate and return the deletion interval
		return max( 1, $delete_interval );
	}

	/**
	 * Deletes old spam topics & replies from the queue after 15 days
	 * (determined by `_bbp_akismet_delete_spam_interval` filter)
	 * since they are not useful in the long term.
	 *
	 * @since 2.6.7 bbPress (r7203)
	 *
	 * @global wpdb $wpdb
	 */
	public function delete_old_spam() {
		global $wpdb;

		// Get the deletion limit & interval
		$delete_limit    = $this->get_delete_limit( '_bbp_akismet_delete_spam_limit' );
		$delete_interval = $this->get_delete_interval( '_bbp_akismet_delete_spam_interval' );

		// Setup the query
		$sql = "SELECT id FROM {$wpdb->posts} WHERE post_type IN ('topic', 'reply') AND post_status = 'spam' AND DATE_SUB(NOW(), INTERVAL %d DAY) > post_date_gmt LIMIT %d";

		// Query loop of topic & reply IDs
		while ( $spam_ids = $wpdb->get_col( $wpdb->prepare( $sql, $delete_interval, $delete_limit ) ) ) {

			// Exit loop if no spam IDs
			if ( empty( $spam_ids ) ) {
				break;
			}

			// Reset queries
			$wpdb->queries = array();

			// Loop through each of the topic/reply IDs
			foreach ( $spam_ids as $spam_id ) {

				/**
				 * Perform a single action on the single topic/reply ID for
				 * simpler batch processing.
				 *
				 * Maybe we should run the bbp_delete_topic or bbp_delete_reply
				 * actions here, too?
				 *
				 * @param string The current function.
				 * @param int    The current topic/reply ID.
				 */
				do_action( '_bbp_akismet_batch_delete', __FUNCTION__, $spam_id );
			}

			// Prepared as strings since id is an unsigned BIGINT, and using %
			// will constrain the value to the maximum signed BIGINT.
			$format_string = implode( ', ', array_fill( 0, count( $spam_ids ), '%s' ) );

			// Run the delete queries
			$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->posts} WHERE ID IN ( {$format_string} )", $spam_ids ) );
			$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->postmeta} WHERE post_id IN ( {$format_string} )", $spam_ids ) );

			// Clean the post cache for these topics & replies
			clean_post_cache( $spam_ids );

			/**
			 * Single action that encompasses all topic/reply IDs after the
			 * delete queries have been run.
			 *
			 * @param int   Count of topic/reply IDs
			 * @param array Array of topic/reply IDs
			 */
			do_action( '_bbp_akismet_delete_spam_count', count( $spam_ids ), $spam_ids );
		}

		/**
		 * Determines whether tables should be optimized.
		 *
		 * @param int Random number between 1 and 5000.
		 */
		$optimize = (int) apply_filters( '_bbp_akismet_optimize_tables', mt_rand( 1, 5000 ), array( $wpdb->posts, $wpdb->postmeta ) );

		// Lucky number 11
		if ( 11 === $optimize ) {
			$wpdb->query( "OPTIMIZE TABLE {$wpdb->posts}" );
			$wpdb->query( "OPTIMIZE TABLE {$wpdb->postmeta}" );
		}
	}

	/**
	 * Deletes `_bbp_akismet_as_submitted` meta keys after 15 days
	 * (determined by `_bbp_akismet_delete_spam_meta_interval` filter)
	 * since they are large and not useful in the long term.
	 *
	 * @since 2.6.7 bbPress (r7203)
	 *
	 * @global wpdb $wpdb
	 */
	public function delete_old_spam_meta() {
		global $wpdb;

		// Get the deletion limit & interval
		$delete_limit    = $this->get_delete_limit( '_bbp_akismet_delete_spam_meta_limit' );
		$delete_interval = $this->get_delete_interval( '_bbp_akismet_delete_spam_meta_interval' );

		// Setup the query
		$sql = "SELECT m.post_id FROM {$wpdb->postmeta} as m INNER JOIN {$wpdb->posts} as p ON m.post_id = p.ID WHERE m.meta_key = '_bbp_akismet_as_submitted' AND DATE_SUB(NOW(), INTERVAL %d DAY) > p.post_date_gmt LIMIT %d";

		// Query loop of topic & reply IDs
		while ( $spam_ids = $wpdb->get_col( $wpdb->prepare( $sql, $delete_interval, $delete_limit ) ) ) {

			// Exit loop if no spam IDs
			if ( empty( $spam_ids ) ) {
				break;
			}

			// Reset queries
			$wpdb->queries = array();

			// Loop through each of the topic/reply IDs
			foreach ( $spam_ids as $spam_id ) {

				// Delete the as_submitted meta data
				delete_post_meta( $spam_id, '_bbp_akismet_as_submitted' );

				/**
				 * Perform a single action on the single topic/reply ID for
				 * simpler batch processing.
				 *
				 * @param string The current function.
				 * @param int    The current topic/reply ID.
				 */
				do_action( '_bbp_akismet_batch_delete', __FUNCTION__, $spam_id );
			}

			/**
			 * Single action that encompasses all topic/reply IDs after the
			 * delete queries have been run.
			 *
			 * @param int   Count of topic/reply IDs
			 * @param array Array of topic/reply IDs
			 */
			do_action( '_bbp_akismet_delete_spam_meta_count', count( $spam_ids ), $spam_ids );
		}

		// Maybe optimize
		$this->maybe_optimize_postmeta();
	}

	/**
	 * Clears post meta that no longer has corresponding posts in the database
	 * (determined by `_bbp_akismet_delete_spam_orphaned_limit` filter)
	 * since it is not useful in the long term.
	 *
	 * @since 2.6.7 bbPress (r7203)
	 *
	 * @global wpdb $wpdb
	 */
	public function delete_orphaned_spam_meta() {
		global $wpdb;

		// Get the deletion limit
		$delete_limit = $this->get_delete_limit( '_bbp_akismet_delete_spam_orphaned_limit' );

		// Default last meta ID
		$last_meta_id = 0;

		// Start time (float)
		$start_time = isset( $_SERVER['REQUEST_TIME_FLOAT'] )
			? (float) $_SERVER['REQUEST_TIME_FLOAT']
			: microtime( true );

		// Maximum time
		$max_exec_time = (float) max( ini_get( 'max_execution_time' ) - 5, 3 );

		// Setup the query
		$sql = "SELECT m.meta_id, m.post_id, m.meta_key FROM {$wpdb->postmeta} as m LEFT JOIN {$wpdb->posts} as p ON m.post_id = p.ID WHERE p.ID IS NULL AND m.meta_id > %d ORDER BY m.meta_id LIMIT %d";

		// Query loop of topic & reply IDs
		while ( $spam_meta_results = $wpdb->get_results( $wpdb->prepare( $sql, $last_meta_id, $delete_limit ) ) ) {

			// Exit loop if no spam IDs
			if ( empty( $spam_meta_results ) ) {
				break;
			}

			// Reset queries
			$wpdb->queries = array();

			// Reset deleted meta count
			$spam_meta_deleted = array();

			// Loop through each of the metas
			foreach ( $spam_meta_results as $spam_meta ) {

				// Skip if not an Akismet key
				if ( 'akismet_' !== substr( $spam_meta->meta_key, 0, 8 ) ) {
					continue;
				}

				// Delete the meta
				delete_post_meta( $spam_meta->post_id, $spam_meta->meta_key );

				/**
				 * Perform a single action on the single topic/reply ID for
				 * simpler batch processing.
				 *
				 * @param string The current function.
				 * @param int    The current topic/reply ID.
				 */
				do_action( '_bbp_akismet_batch_delete', __FUNCTION__, $spam_meta );

				// Stash the meta ID being deleted
				$spam_meta_deleted[] = $last_meta_id = $spam_meta->meta_id;
			}

			/**
			 * Single action that encompasses all topic/reply IDs after the
			 * delete queries have been run.
			 *
			 * @param int   Count of spam meta IDs
			 * @param array Array of spam meta IDs
			 */
			do_action( '_bbp_akismet_delete_spam_meta_count', count( $spam_meta_deleted ), $spam_meta_deleted );

			// Break if getting close to max_execution_time.
			if ( ( microtime( true ) - $start_time ) > $max_exec_time ) {
				break;
			}
		}

		// Maybe optimize
		$this->maybe_optimize_postmeta();
	}

	/**
	 * Maybe OPTIMIZE the _postmeta database table.
	 *
	 * @since 2.7.0 bbPress (r7203)
	 *
	 * @global wpdb $wpdb
	 */
	private function maybe_optimize_postmeta() {
		global $wpdb;

		/**
		 * Determines whether tables should be optimized.
		 *
		 * @param int Random number between 1 and 5000.
		 */
		$optimize = (int) apply_filters( '_bbp_akismet_optimize_table', mt_rand( 1, 5000 ), $wpdb->postmeta );

		// Lucky number 11
		if ( 11 === $optimize ) {
			$wpdb->query( "OPTIMIZE TABLE {$wpdb->postmeta}" );
		}
	}
}
endif;
