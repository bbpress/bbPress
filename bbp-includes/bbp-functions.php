<?php

/**
 * bbp_number_format ( $number, $decimals optional )
 *
 * A bbPress specific method of formatting numeric values
 *
 * @package bbPress
 * @subpackage Functions
 * @since bbPress (r2485)
 *
 * @param string $number Number to format
 * @param string $decimals optional Display decimals
 * @return string Formatted string
 */
function bbp_number_format ( $number, $decimals = false ) {
	// If empty, set $number to '0'
	if ( empty( $number ) || !is_numeric( $number ) )
		$number = '0';

	return apply_filters( 'bbp_number_format', number_format( $number, $decimals ), $number, $decimals );
}

/**
 * bbp_convert_date( $post, $d, $gmt, $translate )
 *
 * Convert time supplied from database query into specified date format.
 *
 * @package bbPress
 * @subpackage Functions
 * @since bbPress (r2455)
 *
 * @param int|object $post Optional, default is global post object. A post_id or post object
 * @param string $d Optional, default is 'U'. Either 'G', 'U', or php date format.
 * @param bool $translate Optional, default is false. Whether to translate the result
 *
 * @return string Returns timestamp
 */
function bbp_convert_date( $time, $d = 'U', $translate = false ) {
	$time = mysql2date( $d, $time, $translate );

	return apply_filters( 'bbp_convert_date', $time, $d );
}

/**
 * bbp_time_since( $time )
 *
 * Output formatted time to display human readable time difference.
 *
 * @package bbPress
 * @subpackage Functions
 * @since bbPress (r2454)
 *
 * @param $time
 */
function bbp_time_since( $time ) {
	echo bbp_get_time_since( $time );
}
	/**
	 * bbp_get_time_since( $time )
	 *
	 * Return formatted time to display human readable time difference.
	 *
	 * @package bbPress
	 * @subpackage Functions
	 * @since bbPress (r2454)
	 *
	 * @param $time
	 */
	function bbp_get_time_since ( $time ) {
		return apply_filters( 'bbp_get_time_since', human_time_diff( $time, current_time( 'timestamp' ) ) );
	}

/**
 * bbp_walk_forum ()
 *
 * Walk the forum tree
 *
 * @param obj $forums
 * @param int $depth
 * @param int $current
 * @param obj $r
 * @return obj
 */
function bbp_walk_forum ( $forums, $depth, $current, $r ) {
	$walker = empty( $r['walker'] ) ? new BBP_Walker_Forum : $r['walker'];
	$args   = array( $forums, $depth, $r, $current );
	return call_user_func_array( array( &$walker, 'walk' ), $args );
}

/** Post Form Handlers ********************************************************/

/**
 * bbp_new_reply_handler ()
 *
 * Handles the front end reply submission
 *
 * @todo security sweep
 */
function bbp_new_reply_handler () {
	// Only proceed if POST is a new reply
	if ( 'POST' == $_SERVER['REQUEST_METHOD'] && !empty( $_POST['action'] ) && 'bbp-new-reply' === $_POST['action'] ) {
		global $bbp;

		// Nonce check
		check_admin_referer( 'bbp-new-reply' );

		// Check users ability to create new reply
		if ( !bbp_is_anonymous() ) {
			if ( !current_user_can( 'publish_replies' ) )
				$bbp->errors->add( 'bbp_reply_permissions', __( '<strong>ERROR</strong>: You do not have permission to reply.', 'bbpress' ) );

			$anonymous_data = false;
			$reply_author   = bbp_get_current_user_id();

		// It is an anonymous post
		} else {
			$anonymous_data = bbp_filter_anonymous_post_data(); // Filter anonymous data
			$reply_author   = 0;

			if ( !is_wp_error( $bbp->errors ) )
				bbp_set_current_anonymous_poster_data( $anonymous_data );
		}

		// Handle Title (optional for replies)
		if ( isset( $_POST['bbp_reply_title'] ) )
			$reply_title = esc_attr( strip_tags( $_POST['bbp_reply_title'] ) );

		// Handle Description
		if ( isset( $_POST['bbp_reply_content'] ) )
			if ( !$reply_content = current_user_can( 'unfiltered_html' ) ? $_POST['bbp_reply_content'] : wp_filter_post_kses( $_POST['bbp_reply_content'] ) )
				$bbp->errors->add( 'bbp_reply_content', __( '<strong>ERROR</strong>: Your reply cannot be empty.', 'bbpress' ) );

		// Handle Topic ID to append reply to
		if ( isset( $_POST['bbp_topic_id'] ) )
			if ( !$topic_id = $_POST['bbp_topic_id'] )
				$bbp->errors->add( 'bbp_reply_topic_id', __( '<strong>ERROR</strong>: Topic ID is missing.', 'bbpress' ) );

		// Handle Forum ID to adjust counts of
		if ( isset( $_POST['bbp_forum_id'] ) )
			if ( !$forum_id = $_POST['bbp_forum_id'] )
				$bbp->errors->add( 'bbp_reply_forum_id', __( '<strong>ERROR</strong>: Forum ID is missing.', 'bbpress' ) );

		// Check for flood
		if ( !bbp_check_for_flood( $anonymous_data, $reply_author ) )
			$bbp->errors->add( 'bbp_reply_flood', __( '<strong>ERROR</strong>: Slow down; you move too fast.', 'bbpress' ) );

		// Handle Tags
		if ( isset( $_POST['bbp_topic_tags'] ) && !empty( $_POST['bbp_topic_tags'] ) ) {
			$tags = $_POST['bbp_topic_tags'];
			$tags = wp_set_post_terms( $topic_id, $tags, $bbp->topic_tag_id, true );

			if ( is_wp_error( $tags ) || false == $tags ) {
				$bbp->errors->add( 'bbp_reply_tags', __( '<strong>ERROR</strong>: There was some problem adding the tags to the topic.', 'bbpress' ) );
			}
		}

		// Handle insertion into posts table
		if ( !empty( $topic_id ) && !empty( $reply_title ) && !empty( $reply_content ) && ( !is_wp_error( $bbp->errors ) || !$bbp->errors->get_error_codes() ) ) {

			// Add the content of the form to $post as an array
			$reply_data = array(
				'post_author'  => $reply_author,
				'post_title'   => $reply_title,
				'post_content' => $reply_content,
				'post_parent'  => $topic_id,
				'post_status'  => 'publish',
				'post_type'    => $bbp->reply_id
			);

			// Insert reply
			$reply_id = wp_insert_post( $reply_data );

			// Check for missing reply_id or error
			if ( !empty( $reply_id ) && !is_wp_error( $reply_id ) ) {

				// Update counts, etc...
				do_action( 'bbp_new_reply', $reply_id, $topic_id, $forum_id, $anonymous_data, $reply_author );

				// Redirect back to new reply
				wp_redirect( bbp_get_reply_url( $reply_id ) );

				// For good measure
				exit();

			// Errors to report
			} else {
				$append_error = ( is_wp_error( $reply_id ) && $reply_id->get_error_message() ) ? $reply_id->get_error_message() . ' ' : '';
				$bbp->errors->add( 'bbp_reply_error', __( '<strong>ERROR</strong>: The following problem(s) have been found with your reply:' . $append_error . 'Please try again.', 'bbpress' ) );
			}
		}
	}
}
add_action( 'template_redirect', 'bbp_new_reply_handler' );

/**
 * bbp_new_reply_update_topic ()
 *
 * Handle all the extra meta stuff from posting a new reply
 *
 * @param int $reply_id
 * @param int $topic_id
 * @param int $forum_id
 * @param bool|array $anonymous_data Optional. If it is an array, it is extracted and anonymous user info is saved, otherwise nothing happens.
 * @param int $author_id
 */
function bbp_new_reply_update_topic ( $reply_id = 0, $topic_id = 0, $forum_id = 0, $anonymous_data = false, $author_id = 0 ) {
	global $bbp;

	// Validate the ID's passed from 'bbp_new_reply' action
	$reply_id = bbp_get_reply_id( $reply_id );
	$topic_id = bbp_get_topic_id( $topic_id );
	$forum_id = bbp_get_forum_id( $forum_id );
	if ( empty( $author_id ) )
		$author_id = bbp_get_current_user_id();

	// If anonymous post, store name, email, website and ip in post_meta. It expects anonymous_data to be sanitized. Check bbp_filter_anonymous_post_data() for sanitization.
	if ( !empty( $anonymous_data ) && is_array( $anonymous_data ) ) {
		extract( $anonymous_data );

		add_post_meta( $reply_id, '_bbp_anonymous_name',  $bbp_anonymous_name,  false );
		add_post_meta( $reply_id, '_bbp_anonymous_email', $bbp_anonymous_email, false );
		add_post_meta( $reply_id, '_bbp_anonymous_ip',    $bbp_anonymous_ip,    false );

		// Website is optional
		if ( !empty( $bbp_anonymous_website ) )
			add_post_meta( $reply_id, '_bbp_anonymous_website', $bbp_anonymous_website, false );

		// Throttle check
		set_transient( '_bbp_' . $anonymous_data['bbp_anonymous_ip'] . '_last_posted', time() );
	} else {
		if ( !current_user_can( 'throttle' ) )
			update_user_meta( $author_id, '_bbp_last_posted', time() );
	}

	// Handle Subscription Checkbox
	if ( bbp_is_subscriptions_active() && !empty( $author_id ) ) {
		$subscribed = bbp_is_user_subscribed( $author_id, $topic_id ) ? true : false;
		$subscheck  = ( !empty( $_POST['bbp_topic_subscription'] ) && 'bbp_subscribe' == $_POST['bbp_topic_subscription'] ) ? true : false;

		// Subscribed and unsubscribing
		if ( true == $subscribed && false == $subscheck )
			bbp_remove_user_subscription( $author_id, $topic_id );

		// Subscribing
		elseif ( false == $subscribed && true == $subscheck )
			bbp_add_user_subscription( $author_id, $topic_id );
	}

	// Topic meta relating to most recent reply
	bbp_update_topic_last_reply_id( $topic_id, $reply_id );
	bbp_update_topic_last_active  ( $topic_id            );

	// Forum meta relating to most recent topic
	bbp_update_forum_last_topic_id( $forum_id, $topic_id );
	bbp_update_forum_last_reply_id( $forum_id, $reply_id );
	bbp_update_forum_last_active  ( $forum_id            );
}
add_action( 'bbp_new_reply', 'bbp_new_reply_update_topic', 10, 5 );

/**
 * bbp_new_topic_handler ()
 *
 * Handles the front end topic submission
 *
 * @todo security sweep
 */
function bbp_new_topic_handler () {
	// Only proceed if POST is a new topic
	if ( 'POST' == $_SERVER['REQUEST_METHOD'] && !empty( $_POST['action'] ) && 'bbp-new-topic' === $_POST['action'] ) {
		global $bbp;

		// Nonce check
		check_admin_referer( 'bbp-new-topic' );

		// Check users ability to create new topic
		if ( !bbp_is_anonymous() ) {
			if ( !current_user_can( 'publish_topics' ) )
				$bbp->errors->add( 'bbp_topic_permissions', __( '<strong>ERROR</strong>: You do not have permission to create new topics.', 'bbpress' ) );

			$anonymous_data = false;
			$topic_author   = bbp_get_current_user_id();

		// It is an anonymous post
		} else {
			$anonymous_data = bbp_filter_anonymous_post_data(); // Filter anonymous data
			$topic_author   = 0;

			if ( !is_wp_error( $bbp->errors ) )
				bbp_set_current_anonymous_poster_data( $anonymous_data );
		}

		// Handle Title
		if ( isset( $_POST['bbp_topic_title'] ) )
			if ( !$topic_title = esc_attr( strip_tags( $_POST['bbp_topic_title'] ) ) )
				$bbp->errors->add( 'bbp_topic_title', __( '<strong>ERROR</strong>: Your topic needs a title.', 'bbpress' ) );

		// Handle Description
		if ( isset( $_POST['bbp_topic_content'] ) )
			if ( !$topic_content = current_user_can( 'unfiltered_html' ) ? $_POST['bbp_topic_content'] : wp_filter_post_kses( $_POST['bbp_topic_content'] ) )
				$bbp->errors->add( 'bbp_topic_content', __( '<strong>ERROR</strong>: Your topic needs some content.', 'bbpress' ) );

		// Handle Topic ID to append reply to
		if ( isset( $_POST['bbp_forum_id'] ) )
			if ( !$forum_id = $_POST['bbp_forum_id'] )
				$bbp->errors->add( 'bbp_topic_forum_id', __( '<strong>ERROR</strong>: Forum ID is missing.', 'bbpress' ) );

		if ( bbp_is_forum_category( $forum_id ) )
			$bbp->errors->add( 'bbp_topic_forum_category', __( '<strong>ERROR</strong>: This forum is a category. No topics can be created in this forum!', 'bbpress' ) );

		if ( bbp_is_forum_closed( $forum_id ) && !current_user_can( 'edit_forum', $forum_id ) )
			$bbp->errors->add( 'bbp_topic_forum_closed', __( '<strong>ERROR</strong>: This forum has been closed to new topics!', 'bbpress' ) );

		if ( bbp_is_forum_private( $forum_id ) && !current_user_can( 'read_private_forums' ) )
			$bbp->errors->add( 'bbp_topic_forum_private', __( '<strong>ERROR</strong>: This forum is private and you do not have the capability to read or create new topics in this forum!', 'bbpress' ) );

		// Check for flood
		if ( !bbp_check_for_flood( $anonymous_data, $topic_author ) )
			$bbp->errors->add( 'bbp_topic_flood', __( '<strong>ERROR</strong>: Slow down; you move too fast.', 'bbpress' ) );

		// Handle Tags
		if ( isset( $_POST['bbp_topic_tags'] ) && !empty( $_POST['bbp_topic_tags'] ) ) {
			// Escape tag input
			$terms = esc_html( $_POST['bbp_topic_tags'] );

			// Explode by comma
			if ( strstr( $terms, ',' ) )
				$terms = explode( ',', $terms );

			// Add topic tag ID as main key
			$terms = array( $bbp->topic_tag_id => $terms );

		// No tags
		} else {
			$terms = '';
		}

		// Handle insertion into posts table
		if ( !empty( $forum_id ) && !empty( $topic_title ) && !empty( $topic_content ) && ( !is_wp_error( $bbp->errors ) || !$bbp->errors->get_error_codes() ) ) {

			// Add the content of the form to $post as an array
			$topic_data = array(
				'post_author'  => $topic_author,
				'post_title'   => $topic_title,
				'post_content' => $topic_content,
				'post_parent'  => $forum_id,
				'tax_input'    => $terms,
				'post_status'  => 'publish',
				'post_type'    => $bbp->topic_id
			);

			// Insert reply
			$topic_id = wp_insert_post( $topic_data );

			// Check for missing topic_id or error
			if ( !empty( $topic_id ) && !is_wp_error( $topic_id ) ) {

				// Update counts, etc...
				do_action( 'bbp_new_topic', $topic_id, $forum_id, $anonymous_data, $topic_author );

				// Redirect back to new reply
				wp_redirect( bbp_get_topic_permalink( $topic_id ) . '#topic-' . $topic_id );

				// For good measure
				exit();

			// Errors to report
			} else {
				$append_error = ( is_wp_error( $topic_id ) && $topic_id->get_error_message() ) ? $topic_id->get_error_message() . ' ' : '';
				$bbp->errors->add( 'bbp_topic_error', __( '<strong>ERROR</strong>: The following problem(s) have been found with your topic:' . $append_error, 'bbpress' ) );
			}
		}
	}
}
add_action( 'template_redirect', 'bbp_new_topic_handler' );

/**
 * bbp_edit_user_handler ()
 *
 * Handles the front end user editing
 *
 * @since bbPress (r2688)
 */
function bbp_edit_user_handler () {

	if ( 'POST' == $_SERVER['REQUEST_METHOD'] && !empty( $_POST['action'] ) && 'bbp-update-user' == $_POST['action'] ) {

		global $bbp, $wpdb;

		// Execute confirmed email change. See send_confirmation_on_profile_email().
		if ( is_multisite() && bbp_is_user_home() && isset( $_GET['newuseremail'] ) && $bbp->displayed_user->ID ) {

			$new_email = get_option( $bbp->displayed_user->ID . '_new_email' );

			if ( $new_email['hash'] == $_GET['newuseremail'] ) {
				$user->ID         = $bbp->displayed_user->ID;
				$user->user_email = esc_html( trim( $new_email['newemail'] ) );

				if ( $wpdb->get_var( $wpdb->prepare( "SELECT user_login FROM {$wpdb->signups} WHERE user_login = %s", $bbp->displayed_user->user_login ) ) )
					$wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->signups} SET user_email = %s WHERE user_login = %s", $user->user_email, $bbp->displayed_user->user_login ) );

				wp_update_user( get_object_vars( $user ) );
				delete_option( $bbp->displayed_user->ID . '_new_email' );

				wp_redirect( add_query_arg( array( 'updated' => 'true' ), bbp_get_user_profile_edit_url( $bbp->displayed_user->ID ) ) );
				exit;
			}

		} elseif ( is_multisite() && bbp_is_user_home() && !empty( $_GET['dismiss'] ) && $bbp->displayed_user->ID . '_new_email' == $_GET['dismiss'] ) {

			delete_option( $bbp->displayed_user->ID . '_new_email' );
			wp_redirect( add_query_arg( array( 'updated' => 'true' ), bbp_get_user_profile_edit_url( $bbp->displayed_user->ID ) ) );
			exit;

		}

		check_admin_referer( 'update-user_' . $bbp->displayed_user->ID );

		if ( !current_user_can( 'edit_user', $bbp->displayed_user->ID ) )
			wp_die( __( 'What are you doing here? You do not have the permission to edit this user.', 'bbpress' ) );

		if ( bbp_is_user_home() )
			do_action( 'personal_options_update', $bbp->displayed_user->ID );
		else
			do_action( 'edit_user_profile_update', $bbp->displayed_user->ID );

		if ( !is_multisite() ) {
			$bbp->errors = edit_user( $bbp->displayed_user->ID ); // Handles the trouble for us ;)
		} else {
			$user        = get_userdata( $bbp->displayed_user->ID );

			// Update the email address in signups, if present.
			if ( $user->user_login && isset( $_POST['email'] ) && is_email( $_POST['email' ]) && $wpdb->get_var( $wpdb->prepare( "SELECT user_login FROM {$wpdb->signups} WHERE user_login = %s", $user->user_login ) ) )
				$wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->signups} SET user_email = %s WHERE user_login = %s", $_POST['email'], $user_login ) );

			// WPMU must delete the user from the current blog if WP added him after editing.
			$delete_role = false;
			$blog_prefix = $wpdb->get_blog_prefix();

			if ( $bbp->displayed_user->ID != $bbp->displayed_user->ID ) {
				$cap = $wpdb->get_var( "SELECT meta_value FROM {$wpdb->usermeta} WHERE user_id = '{$bbp->displayed_user->ID}' AND meta_key = '{$blog_prefix}capabilities' AND meta_value = 'a:0:{}'" );
				if ( !is_network_admin() && null == $cap && $_POST['role'] == '' ) {
					$_POST['role'] = 'contributor';
					$delete_role = true;
				}
			}

			$bbp->errors = edit_user( $bbp->displayed_user->ID );

			if ( $delete_role ) // stops users being added to current blog when they are edited
				delete_user_meta( $bbp->displayed_user->ID, $blog_prefix . 'capabilities' );

			if ( is_multisite() && is_network_admin() & !bbp_is_user_home() && current_user_can( 'manage_network_options' ) && !isset( $super_admins ) && empty( $_POST['super_admin'] ) == is_super_admin( $bbp->displayed_user->ID ) )
				empty( $_POST['super_admin'] ) ? revoke_super_admin( $bbp->displayed_user->ID ) : grant_super_admin( $bbp->displayed_user->ID );
		}

		if ( !is_wp_error( $bbp->errors ) ) {
			$redirect = add_query_arg( array( 'updated' => 'true' ), bbp_get_user_profile_edit_url( $bbp->displayed_user->ID ) );

			wp_redirect( $redirect );
			exit;
		}
	}
}
add_action( 'template_redirect', 'bbp_edit_user_handler', 1 );

/**
 * bbp_new_topic_update_topic ()
 *
 * Handle all the extra meta stuff from posting a new topic
 *
 * @param int $reply_id
 * @param int $topic_id
 * @param int $forum_id
 * @param bool|array $anonymous_data Optional. If it is an array, it is extracted and anonymous user info is saved, otherwise nothing happens.
 * @param int $author_id
 */
function bbp_new_topic_update_topic ( $topic_id = 0, $forum_id = 0, $anonymous_data = false, $author_id = 0 ) {
	// Validate the ID's passed from 'bbp_new_reply' action
	$topic_id = bbp_get_topic_id( $topic_id );
	$forum_id = bbp_get_forum_id( $forum_id );
	if ( empty( $author_id ) )
		$author_id = bbp_get_current_user_id();

	// If anonymous post, store name, email, website and ip in post_meta. It expects anonymous_data to be sanitized. Check bbp_filter_anonymous_post_data() for sanitization.
	if ( !empty( $anonymous_data ) && is_array( $anonymous_data ) ) {
		extract( $anonymous_data );

		add_post_meta( $topic_id, '_bbp_anonymous_name',    $bbp_anonymous_name,    false );
		add_post_meta( $topic_id, '_bbp_anonymous_email',   $bbp_anonymous_email,   false );
		add_post_meta( $topic_id, '_bbp_anonymous_ip',      $bbp_anonymous_ip,      false );

		// Website is optional
		if ( !empty( $bbp_anonymous_website ) )
			add_post_meta( $topic_id, '_bbp_anonymous_website', $bbp_anonymous_website, false );

		// Throttle check
		set_transient( '_bbp_' . $anonymous_data['bbp_anonymous_ip'] . '_last_posted', time() );
	} else {
		if ( !current_user_can( 'throttle' ) )
			bb_update_usermeta( $author_id, '_bbp_last_posted', time() );
	}

	// Handle Subscription Checkbox
	if ( bbp_is_subscriptions_active() ) {
		if ( !empty( $_POST['bbp_topic_subscription'] ) && 'bbp_subscribe' == $_POST['bbp_topic_subscription'] ) {
			bbp_add_user_subscription( $author_id, $topic_id );
		}
	}

	// Topic meta relating to most recent topic
	bbp_update_topic_last_reply_id( $topic_id, 0 );
	bbp_update_topic_last_active( $topic_id );

	// Forum meta relating to most recent topic
	bbp_update_forum_last_topic_id( $forum_id, $topic_id );
	bbp_update_forum_last_reply_id( $forum_id, 0 );
	bbp_update_forum_last_active( $forum_id );
}
add_action( 'bbp_new_topic', 'bbp_new_topic_update_topic', 10, 4 );

/**
 * bbp_filter_anonymous_post_data ()
 *
 * Filter anonymous post data.
 *
 * We use REMOTE_ADDR here directly. If you are behind a proxy, you should
 * ensure that it is properly set, such as in wp-config.php, for your
 * environment. See {@link http://core.trac.wordpress.org/ticket/9235}
 *
 * @since bbPress (r2734)
 *
 * @param mixed $args Optional. If no args are there, then $_POST values are used.
 */
function bbp_filter_anonymous_post_data ( $args = '' ) {
	global $bbp;

	// Assign variables
	$defaults = array (
		'bbp_anonymous_name'    => $_POST['bbp_anonymous_name'],
		'bbp_anonymous_email'   => $_POST['bbp_anonymous_email'],
		'bbp_anonymous_website' => $_POST['bbp_anonymous_website'],
		'bbp_anonymous_ip'      => $_SERVER['REMOTE_ADDR']
	);

	$r = wp_parse_args( $args, $defaults );
	extract( $r );

	// Filter variables and add errors if necessary
	if ( !$bbp_anonymous_name  = apply_filters( 'bbp_pre_anonymous_post_author_name',  $bbp_anonymous_name  ) )
		$bbp->errors->add( 'bbp_anonymous_name',  __( '<strong>ERROR</strong>: Invalid author name submitted!',          'bbpress' ) );

	if ( !$bbp_anonymous_email = apply_filters( 'bbp_pre_anonymous_post_author_email', $bbp_anonymous_email ) )
		$bbp->errors->add( 'bbp_anonymous_email', __( '<strong>ERROR</strong>: Invalid email address submitted!',             'bbpress' ) );

	if ( !$bbp_anonymous_ip    = apply_filters( 'bbp_pre_anonymous_post_author_ip',    preg_replace( '/[^0-9a-fA-F:., ]/', '', $bbp_anonymous_ip ) ) )
		$bbp->errors->add( 'bbp_anonymous_ip',    __( '<strong>ERROR</strong>: Invalid IP address! Where are you from?', 'bbpress' ) );

	// Website is optional
	$bbp_anonymous_website     = apply_filters( 'bbp_pre_anonymous_post_author_website', $bbp_anonymous_website );

	if ( !is_wp_error( $bbp->errors ) || !$bbp->errors->get_error_codes() )
		$retval = compact( 'bbp_anonymous_name', 'bbp_anonymous_email', 'bbp_anonymous_website', 'bbp_anonymous_ip' );
	else
		$retval = false;

	// Finally, return sanitized data or false
	return apply_filters( 'bbp_filter_anonymous_post_data', $retval, $args );
}

/**
 * Check to make sure that a user is not making too many posts in a short amount of time.
 *
 * @since bbPress (r2734)
 *
 * @param false|array $anonymous_data Optional - do not supply if supplying $author_id. If it's a anonymous post. With key 'bbp_anonymous_ip'. Should be sanitized (see bbp_filter_anonymous_post_data() for sanitization)
 * @param int $author_id Optional - do not supply if supplying $anonymous_data. If it's a post by logged in user.
 */
function bbp_check_for_flood ( $anonymous_data = false, $author_id = 0 ) {

	// Option disabled. No flood checks.
	if ( !$throttle_time = get_option( '_bbp_throttle_time' ) )
		return true;

	if ( !empty( $anonymous_data ) && is_array( $anonymous_data ) && !empty( $anonymous_data['bbp_anonymous_ip'] ) ) {
		if ( ( $last_posted = get_transient( '_bbp_' . $anonymous_data['bbp_anonymous_ip'] . '_last_posted') ) && time() < $last_posted + $throttle_time )
			return false;
	} elseif ( !empty( $author_id ) ) {
		$author_id   = (int) $author_id;
		$last_posted = get_user_meta( $author_id, '_bbp_last_posted', true );

		if ( isset( $last_posted ) && time() < $last_posted + $throttle_time && !current_user_can( 'throttle' ) )
			return false;
	} else {
		return false;
	}

	return true;
}

/**
 * bbp_check_for_profile_page ()
 *
 * Add checks for a user page. If it is, then locate the user page template.
 *
 * @since bbPress (r2688)
 */
function bbp_check_for_profile_page ( $template = '' ) {

	// Viewing a profile
	if ( bbp_is_user_profile_page() ) {
		$template = array( 'user.php', 'author.php', 'index.php' );

	// Editing a profile
	} elseif ( bbp_is_user_profile_edit() ) {
		$template = array( 'user-edit.php', 'user.php', 'author.php', 'index.php' );
	}

	if ( !$template = apply_filters( 'bbp_check_for_profile_page', $template ) )
		return false;

	// Try to load a template file
	bbp_load_template( $template );
}
add_action( 'template_redirect', 'bbp_check_for_profile_page', 2 );

/**
 * bbp_pre_get_posts ()
 *
 * Add checks for a user page. If it is, then locate the user page template.
 *
 * @since bbPress (r2688)
 */
function bbp_pre_get_posts ( $wp_query ) {
	global $bbp, $wp_version;

	$bbp_user     = get_query_var( 'bbp_user'         );
	$is_user_edit = get_query_var( 'bbp_edit_profile' );

	if ( empty( $bbp_user ) )
		return;

	// Create new user
	$user = new WP_User( $bbp_user );

	// Stop if no user
	if ( !isset( $user ) || empty( $user ) || empty( $user->ID ) ) {
		$wp_query->set_404();
		return;
	}

	// Confirmed existence of the bbPress user

	// Define new query variable
	if ( !empty( $is_user_edit ) ) {
		// Only allow super admins on multisite to edit every user.
		if ( ( is_multisite() && !current_user_can( 'manage_network_users' ) && $user_id != $current_user->ID && ! apply_filters( 'enable_edit_any_user_configuration', true ) ) || !current_user_can( 'edit_user', $user->ID ) )
			wp_die( __( 'You do not have the permission to edit this user.', 'bbpress' ) );

		$wp_query->bbp_is_user_profile_edit = true;

		// Load the required user editing functions
		if ( version_compare( $wp_version, '3.1', '<=' ) ) // registration.php is not required in wp 3.1+
			include_once( ABSPATH . 'wp-includes/registration.php' );

		require_once( ABSPATH . 'wp-admin/includes/user.php'   );

	} else {
		$wp_query->bbp_is_user_profile_page = true;
	}

	// Set query variables
	$wp_query->is_home                   = false;                   // Correct is_home variable
	$wp_query->query_vars['bbp_user_id'] = $user->ID;               // Set bbp_user_id for future reference
	$wp_query->query_vars['author_name'] = $user->user_nicename;    // Set author_name as current user's nicename to get correct posts

	// Set the displayed user global to this user
	$bbp->displayed_user = $user;

	add_filter( 'wp_title', 'bbp_profile_page_title', 10, 3 );      // Correct wp_title
}
add_action( 'pre_get_posts', 'bbp_pre_get_posts', 100 );

	/**
	 * bbp_profile_page_title ()
	 *
	 * Custom wp_title for bbPress User Profile Pages
	 *
	 * @since bbPress (r2688)
	 *
	 * @param string $title Optional. The title (not used).
	 * @param string $sep Optional, default is '&raquo;'. How to separate the various items within the page title.
	 * @param string $seplocation Optional. Direction to display title, 'right'.
	 * @return string The tite
	 */
	function bbp_profile_page_title ( $title = '', $sep = '&raquo;', $seplocation = '' ) {
		$userdata = get_userdata( get_query_var( 'bbp_user_id' ) );
		$title    = apply_filters( 'bbp_profile_page_wp_raw_title', $userdata->display_name, $sep, $seplocation );
		$t_sep    = '%WP_TITILE_SEP%'; // Temporary separator, for accurate flipping, if necessary

		$prefix = '';
		if ( !empty( $title ) )
			$prefix = " $sep ";

		// Determines position of the separator and direction of the breadcrumb
		if ( 'right' == $seplocation ) { // sep on right, so reverse the order
			$title_array = explode( $t_sep, $title );
			$title_array = array_reverse( $title_array );
			$title       = implode( " $sep ", $title_array ) . $prefix;
		} else {
			$title_array = explode( $t_sep, $title );
			$title       = $prefix . implode( " $sep ", $title_array );
		}

		$title = apply_filters( 'bbp_profile_page_wp_title', $title, $sep, $seplocation );

		return $title;
	}

/**
 * bbp_load_template( $files )
 *
 *
 * @param str $files
 * @return On failure
 */
function bbp_load_template( $files ) {
	if ( empty( $files ) )
		return;

	// Force array
	if ( is_string( $files ) )
		$files = (array)$files;

	// Exit if file is found
	if ( locate_template( $files, true ) )
		exit();

	return;
}

/**
 * bbp_get_stickies()
 *
 * Return sticky topics from forum
 *
 * @since bbPress (r2592)
 * @param int $forum_id
 * @return array Post ID's of sticky topics
 */
function bbp_get_stickies ( $forum_id = 0 ) {
	global $bbp;

	if ( empty( $forum_id ) ) {
		$stickies = get_option( '_bbp_super_sticky_topics' );
	} else {
		if ( $bbp->forum_id == get_post_type( $forum_id ) ) {
			$stickies = get_post_meta( '_bbp_sticky_topics', $forum_id );
		} else {
			$stickies = null;
		}
	}

	return apply_filters( 'bbp_get_stickies', $stickies, (int)$forum_id );
}

/**
 * bbp_get_super_stickies ()
 *
 * Return topics stuck to front page of forums
 *
 * @since bbPress (r2592)
 * @return array Post ID's of super sticky topics
 */
function bbp_get_super_stickies () {
	$stickies = get_option( '_bbp_super_sticky_topics' );

	return apply_filters( 'bbp_get_super_stickies', $stickies );
}

/**
 * bbp_redirect_canonical ()
 *
 * Remove the canonical redirect to allow pretty pagination
 *
 * @package bbPress
 * @subpackage Functions
 * @since bbPress (r2628)
 *
 * @param string $redirect_url
 */
function bbp_redirect_canonical ( $redirect_url ) {
	global $wp_rewrite;

	if ( $wp_rewrite->using_permalinks() ) {
		if ( bbp_is_topic() && 1 < get_query_var( 'paged' ) ){
			$redirect_url = false;
		} elseif ( bbp_is_forum() && 1 < get_query_var( 'paged' ) ) {
			$redirect_url = false;
		}
	}

	return $redirect_url;
}
add_filter( 'redirect_canonical', 'bbp_redirect_canonical' );

/**
 * bbp_get_paged
 *
 * Assist pagination by returning correct page number
 *
 * @package bbPress
 * @subpackage Functions
 * @since bbPress (r2628)
 *
 * @return int
 */
function bbp_get_paged() {
	if ( $paged = get_query_var( 'paged' ) )
		return (int) $paged;
	else
		return 1;
}

/** Topics Actions ************************************************************/

/**
 * bbp_toggle_topic_handler ()
 *
 * Handles the front end opening/closing, spamming/unspamming and trashing/untrashing/deleting of topics
 *
 * @since bbPress (r2727)
 */
function bbp_toggle_topic_handler () {

	// Only proceed if GET is a topic toggle action
	if ( 'GET' == $_SERVER['REQUEST_METHOD'] && !empty( $_GET['action'] ) && in_array( $_GET['action'], array( 'bbp_toggle_topic_close', 'bbp_toggle_topic_spam', 'bbp_toggle_topic_trash' ) ) && !empty( $_GET['topic_id'] ) ) {
		global $bbp;

		$action    = $_GET['action'];            // What action is taking place?
		$topic_id  = (int) $_GET['topic_id'];    // What's the topic id?
		$success   = false;                      // Flag
		$post_data = array( 'ID' => $topic_id ); // Prelim array

		// Make sure topic exists
		if ( !$topic = get_post( $topic_id ) )
			return;

		// What is the user doing here?
		if ( !current_user_can( 'edit_topic', $topic->ID ) || ( 'bbp_toggle_topic_trash' == $action && !current_user_can( 'delete_topic', $topic->ID ) ) ) {
			$bbp->errors->add( 'bbp_toggle_topic_permission', __( '<strong>ERROR:</strong> You do not have the permission to do that!', 'bbpress' ) );
			return;
		}

		switch ( $action ) {
			case 'bbp_toggle_topic_close' :
				check_ajax_referer( 'close-topic_' . $topic_id );

				$is_open = bbp_is_topic_open( $topic_id );
				$success = $is_open ? bbp_close_topic( $topic_id ) : bbp_open_topic( $topic_id );
				$failure = $is_open ? __( '<strong>ERROR</strong>: There was a problem closing the topic!', 'bbpress' ) : __( '<strong>ERROR</strong>: There was a problem opening the topic!', 'bbpress' );

				break;

			case 'bbp_toggle_topic_spam' :
				check_ajax_referer( 'spam-topic_' . $topic_id );

				$is_spam = bbp_is_topic_spam( $topic_id );
				$success = $is_spam ? bbp_unspam_topic( $topic_id ) : bbp_spam_topic( $topic_id );
				$failure = $is_spam ? __( '<strong>ERROR</strong>: There was a problem unmarking the topic as spam!', 'bbpress' ) : __( '<strong>ERROR</strong>: There was a problem marking the topic as spam!', 'bbpress' );

				break;

			case 'bbp_toggle_topic_trash' :

				$sub_action = in_array( $_GET['sub_action'], array( 'trash', 'untrash', 'delete' ) ) ? $_GET['sub_action'] : false;

				if ( empty( $sub_action ) )
					break;

				switch ( $sub_action ) {
					case 'trash':
						check_ajax_referer( 'trash-' . $bbp->topic_id . '_' . $topic_id );

						$success = wp_trash_post( $topic_id );
						$failure = __( '<strong>ERROR</strong>: There was a problem trashing the topic!', 'bbpress' );

						break;

					case 'untrash':
						check_ajax_referer( 'untrash-' . $bbp->topic_id . '_' . $topic_id );

						$success = wp_untrash_post( $topic_id );
						$failure = __( '<strong>ERROR</strong>: There was a problem untrashing the topic!', 'bbpress' );

						break;

					case 'delete':
						check_ajax_referer( 'delete-' . $bbp->topic_id . '_' . $topic_id );

						$success = wp_delete_post( $topic_id );
						$failure = __( '<strong>ERROR</strong>: There was a problem deleting the topic!', 'bbpress' );

						break;
				}

				break;
		}

		// Do additional topic toggle actions
		do_action( 'bbp_toggle_topic_handler', $success, $post_data, $action );

		// Check for errors
		if ( false != $success && !is_wp_error( $success ) ) {

			// Redirect back to the topic
			$redirect = bbp_get_topic_permalink( $topic_id );
			wp_redirect( $redirect );

			// For good measure
			exit();

		// Handle errors
		} else {
			$bbp->errors->add( 'bbp_toggle_topic', $failure );
		}
	}
}
add_action( 'template_redirect', 'bbp_toggle_topic_handler', 1 );

/** Reply Actions *************************************************************/

/**
 * bbp_toggle_reply_handler ()
 *
 * Handles the front end spamming/unspamming and trashing/untrashing/deleting of replies
 *
 * @since bbPress (r2740)
 */
function bbp_toggle_reply_handler () {

	// Only proceed if GET is a reply toggle action
	if ( 'GET' == $_SERVER['REQUEST_METHOD'] && !empty( $_GET['action'] ) && in_array( $_GET['action'], array( 'bbp_toggle_reply_spam', 'bbp_toggle_reply_trash' ) ) && !empty( $_GET['reply_id'] ) ) {
		global $bbp;

		$action    = $_GET['action'];            // What action is taking place?
		$reply_id  = (int) $_GET['reply_id'];    // What's the reply id?
		$success   = false;                      // Flag
		$post_data = array( 'ID' => $reply_id ); // Prelim array

		// Make sure reply exists
		if ( !$reply = get_post( $reply_id ) )
			return;

		// What is the user doing here?
		if ( !current_user_can( 'edit_reply', $reply->ID ) || ( 'bbp_toggle_reply_trash' == $action && !current_user_can( 'delete_reply', $reply->ID ) ) ) {
			$bbp->errors->add( 'bbp_toggle_reply_permission', __( '<strong>ERROR:</strong> You do not have the permission to do that!', 'bbpress' ) );
			return;
		}

		switch ( $action ) {

			case 'bbp_toggle_reply_spam' :
				check_ajax_referer( 'spam-reply_' . $reply_id );

				$is_spam = bbp_is_reply_spam( $reply_id );
				$success = $is_spam ? bbp_unspam_reply( $reply_id ) : bbp_spam_reply( $reply_id );
				$failure = $is_spam ? __( '<strong>ERROR</strong>: There was a problem unmarking the reply as spam!', 'bbpress' ) : __( '<strong>ERROR</strong>: There was a problem marking the reply as spam!', 'bbpress' );

				break;

			case 'bbp_toggle_reply_trash' :

				$sub_action = in_array( $_GET['sub_action'], array( 'trash', 'untrash', 'delete' ) ) ? $_GET['sub_action'] : false;

				if ( empty( $sub_action ) )
					break;

				switch ( $sub_action ) {
					case 'trash':
						check_ajax_referer( 'trash-' . $bbp->reply_id . '_' . $reply_id );

						$success = wp_trash_post( $reply_id );
						$failure = __( '<strong>ERROR</strong>: There was a problem trashing the reply!', 'bbpress' );

						break;

					case 'untrash':
						check_ajax_referer( 'untrash-' . $bbp->reply_id . '_' . $reply_id );

						$success = wp_untrash_post( $reply_id );
						$failure = __( '<strong>ERROR</strong>: There was a problem untrashing the reply!', 'bbpress' );

						break;

					case 'delete':
						check_ajax_referer( 'delete-' . $bbp->reply_id . '_' . $reply_id );

						$success = wp_delete_post( $reply_id );
						$failure = __( '<strong>ERROR</strong>: There was a problem deleting the reply!', 'bbpress' );

						break;
				}

				break;
		}

		// Do additional reply toggle actions
		do_action( 'bbp_toggle_reply_handler', $success, $post_data, $action );

		// Check for errors
		if ( false != $success && !is_wp_error( $success ) ) {

			// Redirect back to the reply
			$redirect = add_query_arg( array( 'view' => 'all' ), bbp_get_reply_url( $reply_id, true ) );
			wp_redirect( $redirect );

			// For good measure
			exit();

		// Handle errors
		} else {
			$bbp->errors->add( 'bbp_toggle_reply', $failure );
		}
	}
}
add_action( 'template_redirect', 'bbp_toggle_reply_handler', 1 );

/** Favorites *****************************************************************/

/**
 * bbp_favorites_handler ()
 *
 * Handles the front end adding and removing of favorite topics
 */
function bbp_favorites_handler () {

	// Only proceed if GET is a favorite action
	if ( 'GET' == $_SERVER['REQUEST_METHOD'] && !empty( $_GET['action'] ) && in_array( $_GET['action'], array( 'bbp_favorite_add', 'bbp_favorite_remove' ) ) && !empty( $_GET['topic_id'] ) ) {

		// What action is taking place?
		$action  = $_GET['action'];

		// Get user_id
		$user_id = bbp_get_user_id( 0, true, true );

		// Check current user's ability to edit the user
		if ( !current_user_can( 'edit_user', $user_id ) )
			$bbp->errors->add( 'bbp_favorite_permissions', __( '<strong>ERROR</strong>: You don\'t have the permission to edit favorites of that user!', 'bbpress' ) );

		// Load favorite info
		if ( !$topic_id = intval( $_GET['topic_id'] ) )
			$bbp->errors->add( 'bbp_favorite_topic_id', __( '<strong>ERROR</strong>: No topic was found! Which topic are you marking/unmarking as favorite?', 'bbpress' ) );

		$is_favorite    = bbp_is_user_favorite( $user_id, $topic_id );
		$success        = false;

		// Handle insertion into posts table
		if ( !empty( $topic_id ) && !empty( $user_id ) && ( !is_wp_error( $bbp->errors ) || !$bbp->errors->get_error_codes() ) ) {

			if ( $is_favorite && 'bbp_favorite_remove' == $action )
				$success = bbp_remove_user_favorite( $user_id, $topic_id );
			elseif ( !$is_favorite && 'bbp_favorite_add' == $action )
				$success = bbp_add_user_favorite( $user_id, $topic_id );

			// Do additional favorites actions
			do_action( 'bbp_favorites_handler', $success, $user_id, $topic_id, $action );

			// Check for missing reply_id or error
			if ( true == $success ) {

				// Redirect back to new reply
				$redirect = bbp_is_favorites( false ) ? bbp_get_favorites_permalink( $user_id ) : bbp_get_topic_permalink( $topic_id );
				wp_redirect( $redirect );

				// For good measure
				exit();

			// Handle errors
			} else {
				if ( $is_favorite && 'bbp_favorite_remove' == $action )
					$bbp->errors->add( 'bbp_favorite_remove', __( '<strong>ERROR</strong>: There was a problem removing that topic from favorites!', 'bbpress' ) );
				elseif ( !$is_favorite && 'bbp_favorite_add' == $action )
					$bbp->errors->add( 'bbp_favorite_add',    __( '<strong>ERROR</strong>: There was a problem favoriting that topic!', 'bbpress' ) );
			}
		}
	}
}
add_action( 'template_redirect', 'bbp_favorites_handler', 1 );

/**
 * bbp_is_favorites_active ()
 *
 * Checks if favorites feature is enabled.
 *
 * @package bbPress
 * @subpackage Functions
 * @since bbPress (r2658)
 *
 * @return bool Is 'favorites' enabled or not
 */
function bbp_is_favorites_active () {
	return (bool) get_option( '_bbp_enable_favorites' );
}

/**
 * bbp_remove_topic_from_all_favorites ()
 *
 * Remove a deleted topic from all users' favorites
 *
 * @package bbPress
 * @subpackage Functions
 * @since bbPress (r2652)
 *
 * @uses bbp_get_topic_favoriters ()
 * @param int $topic_id Topic ID to remove
 * @return void
 */
function bbp_remove_topic_from_all_favorites ( $topic_id = 0 ) {
	if ( empty( $topic_id ) )
		return;

	if ( $users = bbp_get_topic_favoriters( $topic_id ) )
		foreach ( $users as $user )
			bbp_remove_user_favorite( $user, $topic_id );
}
add_action( 'trash_post',  'bbp_remove_topic_from_all_favorites' );
add_action( 'delete_post', 'bbp_remove_topic_from_all_favorites' );

/** Subscriptions *************************************************************/

/**
 * bbp_subscriptions_handler ()
 *
 * Handles the front end subscribing and unsubscribing topics
 */
function bbp_subscriptions_handler () {
	global $bbp;

	if ( !bbp_is_subscriptions_active() )
		return false;

	// Only proceed if GET is a favorite action
	if ( 'GET' == $_SERVER['REQUEST_METHOD'] && !empty( $_GET['action'] ) && in_array( $_GET['action'], array( 'bbp_subscribe', 'bbp_unsubscribe' ) ) && !empty( $_GET['topic_id'] ) ) {
		// What action is taking place?
		$action = $_GET['action'];

		// Get user_id
		$user_id = bbp_get_user_id( 0, true, true );

		// Check current user's ability to edit the user
		if ( !current_user_can( 'edit_user', $user_id ) )
			$bbp->errors->add( 'bbp_subscription_permissions', __( '<strong>ERROR</strong>: You don\'t have the permission to edit favorites of that user!', 'bbpress' ) );

		// Load subscription info
		if ( !$topic_id  = intval( $_GET['topic_id'] ) )
			$bbp->errors->add( 'bbp_subscription_topic_id', __( '<strong>ERROR</strong>: No topic was found! Which topic are you subscribing/unsubscribing to?', 'bbpress' ) );

		$is_subscription = bbp_is_user_subscribed( $user_id, $topic_id );
		$success         = false;

		if ( !empty( $topic_id ) && !empty( $user_id ) && ( !is_wp_error( $bbp->errors ) || !$bbp->errors->get_error_codes() ) ) {

			if ( $is_subscription && 'bbp_unsubscribe' == $action )
				$success = bbp_remove_user_subscription( $user_id, $topic_id );
			elseif ( !$is_subscription && 'bbp_subscribe' == $action )
				$success = bbp_add_user_subscription( $user_id, $topic_id );

			// Do additional subscriptions actions
			do_action( 'bbp_subscriptions_handler', $success, $user_id, $topic_id, $action );

			// Check for missing reply_id or error
			if ( true == $success ) {

				// Redirect back to new reply
				$redirect = bbp_is_subscriptions( false ) ? bbp_get_subscriptions_permalink( $user_id ) : bbp_get_topic_permalink( $topic_id );
				wp_redirect( $redirect );

				// For good measure
				exit();

			// Handle errors
			} else {
				if ( $is_subscription && 'bbp_unsubscribe' == $action )
					$bbp->errors->add( 'bbp_unsubscribe', __( '<strong>ERROR</strong>: There was a problem unsubscribing from that topic!', 'bbpress' ) );
				elseif ( !$is_subscription && 'bbp_subscribe' == $action )
					$bbp->errors->add( 'bbp_subscribe',    __( '<strong>ERROR</strong>: There was a problem subscribing to that topic!', 'bbpress' ) );
			}
		}
	}
}
add_action( 'template_redirect', 'bbp_subscriptions_handler', 1 );

/**
 * bbp_remove_topic_from_all_subscriptions ()
 *
 * Remove a deleted topic from all users' subscriptions
 *
 * @package bbPress
 * @subpackage Functions
 * @since bbPress (r2652)
 *
 * @uses bbp_get_topic_subscribers ()
 * @param int $topic_id Topic ID to remove
 * @return void
 */
function bbp_remove_topic_from_all_subscriptions ( $topic_id = 0 ) {
	if ( empty( $topic_id ) )
		return;

	if ( !bbp_is_subscriptions_active() )
		return;

	if ( $users = bbp_get_topic_subscribers( $topic_id ) ) {
		foreach ( $users as $user ) {
			bbp_remove_user_subscription( $user, $topic_id );
		}
	}
}
add_action( 'trash_post',  'bbp_remove_topic_from_all_subscriptions' );
add_action( 'delete_post', 'bbp_remove_topic_from_all_subscriptions' );

/**
 * bbp_is_subscriptions_active ()
 *
 * Checks if subscription feature is enabled.
 *
 * @package bbPress
 * @subpackage Functions
 * @since bbPress (r2658)
 *
 * @return bool Is subscription enabled or not
 */
function bbp_is_subscriptions_active () {
	return (bool) get_option( '_bbp_enable_subscriptions' );
}

/**
 * bbp_notify_subscribers ()
 *
 * Sends notification emails for new posts.
 *
 * Gets new post's ID and check if there are subscribed
 * user to that topic, and if there are, send notifications
 *
 * @package bbPress
 * @subpackage Functions
 * @since bbPress (r2668)
 *
 * @todo When Akismet is made to work with bbPress posts, add a check if the post is spam or not, to avoid sending out spam mails
 *
 * @param int $reply_id ID of the newly made reply
 * @return bool True on success, false on failure
 */
function bbp_notify_subscribers ( $reply_id = 0 ) {
	global $bbp, $wpdb;

	if ( !bbp_is_subscriptions_active() )
		return false;

	if ( empty( $reply_id ) )
		return false;

	if ( !$reply = get_post( $reply_id ) )
		return false;

	if ( $reply->post_type != $bbp->reply_id || empty( $reply->post_parent ) )
		return false;

	if ( !$topic = get_post( $post->post_parent ) )
		return false;

	if ( !$poster_name = get_the_author_meta( 'display_name', $reply->post_author ) )
		return false;

	$reply_id = $reply->ID;
	$topic_id = $topic->ID;

	do_action( 'bbp_pre_notify_subscribers', $reply_id, $topic_id );

	// Get the users who have favorited the topic and have subscriptions on
	if ( !$user_ids = bbp_get_topic_subscribers( $topic_id, true ) )
		return false;

	foreach ( (array) $user_ids as $user_id ) {

		// Don't send notifications to the person who made the post
		if ( $user_id == $reply->post_author )
			continue;

		// For plugins
		if ( !$message = apply_filters( 'bbp_subscription_mail_message', __( "%1\$s wrote:\n\n%2\$s\n\nPost Link: %3\$s\n\nYou're getting this mail because you subscribed to the topic, visit the topic and login to unsubscribe." ), $reply_id, $topic_id, $user_id ) )
			continue;

		$user = get_userdata( $user_id );

		wp_mail(
			$user->user_email,
			apply_filters( 'bbp_subscription_mail_title', '[' . get_option( 'blogname' ) . '] ' . $topic->post_title, $reply_id, $topic_id ),
			sprintf( $message, $poster_name, strip_tags( $reply->post_content ), bbp_get_reply_permalink( $reply_id ) )
		);
	}

	do_action( 'bbp_post_notify_subscribers', $reply_id, $topic_id );

	return true;
}
add_action( 'bbp_new_reply', 'bbp_notify_subscribers', 1, 1 );

/**
 * bbp_fix_post_author ()
 *
 * When a logged in user changes the status of an anonymous reply or topic,
 * the post_author field is set to the logged in user's id. This function
 * fixes that.
 *
 * @package bbPress
 * @subpackage Functions
 * @since bbPress (r2734)
 *
 * @param array $data Post data
 * @param array $postarr Original post array (includes post id)
 * @return array Filtered data
 */
function bbp_fix_post_author ( $data = array(), $postarr = array() ) {
	global $bbp;

	// Post is not being updated, return
	if ( empty( $postarr['ID'] ) )
		return $data;

	// Post is not a topic or reply, return
	if ( !in_array( $data['post_type'], array( $bbp->topic_id, $bbp->reply_id ) ) )
		return $data;

	// The post is not anonymous
	if ( get_post_field( 'post_author', $postarr['ID'] ) )
		return $data;

	// The post is being updated. It is a topic or a reply and is written by an anonymous user.
	// Set the post_author back to 0
	$data['post_author'] = 0;

	return $data;
}

?>
