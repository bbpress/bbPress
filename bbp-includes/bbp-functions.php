<?php

/**
 * bbPress General Functions
 *
 * @package bbPress
 * @subpackage Functions
 */

/**
 * A bbPress specific method of formatting numeric values
 *
 * @since bbPress (r2486)
 *
 * @param string $number Number to format
 * @param string $decimals Optional. Display decimals
 * @uses apply_filters() Calls 'bbp_number_format' with the formatted values,
 *                        number and display decimals bool
 * @return string Formatted string
 */
function bbp_number_format( $number, $decimals = false ) {
	// If empty, set $number to '0'
	if ( empty( $number ) || !is_numeric( $number ) )
		$number = '0';

	return apply_filters( 'bbp_number_format', number_format( $number, $decimals ), $number, $decimals );
}

/**
 * Convert time supplied from database query into specified date format.
 *
 * @since bbPress (r2455)
 *
 * @param int|object $post Optional. Default is global post object. A post_id or
 *                          post object
 * @param string $d Optional. Default is 'U'. Either 'G', 'U', or php date
 *                             format
 * @param bool $translate Optional. Default is false. Whether to translate the
 *                                   result
 * @uses mysql2date() To convert the format
 * @uses apply_filters() Calls 'bbp_convert_date' with the time, date format
 *                        and translate bool
 * @return string Returns timestamp
 */
function bbp_convert_date( $time, $d = 'U', $translate = false ) {
	$time = mysql2date( $d, $time, $translate );

	return apply_filters( 'bbp_convert_date', $time, $d, $translate );
}

/**
 * Output formatted time to display human readable time difference.
 *
 * @since bbPress (r2544)
 *
 * @param $time Unix timestamp from which the difference begins.
 * @uses bbp_get_time_since() To get the formatted time
 */
function bbp_time_since( $time ) {
	echo bbp_get_time_since( $time );
}
	/**
	 * Return formatted time to display human readable time difference.
	 *
	 * @since bbPress (r2544)
	 *
	 * @param $time Unix timestamp from which the difference begins.
         * @uses current_time() To get the current time in mysql format
         * @uses human_time_diff() To get the time differene in since format
         * @uses apply_filters() Calls 'bbp_get_time_since' with the time
         *                        difference and time
         * @return string Formatted time
	 */
	function bbp_get_time_since( $time ) {
		return apply_filters( 'bbp_get_time_since', human_time_diff( $time, current_time( 'timestamp' ) ), $time );
	}

/**
 * Walk the forum tree
 *
 * @param object $forums Forums
 * @param int $depth Depth
 * @param int $current Current forum
 * @param array $r Parsed arguments, supported by the walker. If you want to
 *                  use your own walker, pass the 'walker' arg with the walker.
 *                  The walker defaults to {@link BBP_Walker_Forum}
 * @return object Walked forum tree
 */
function bbp_walk_forum( $forums, $depth, $current, $r ) {
	$walker = empty( $r['walker'] ) ? new BBP_Walker_Forum : $r['walker'];
	$args   = array( $forums, $depth, $r, $current );
	return call_user_func_array( array( &$walker, 'walk' ), $args );
}

/** Post Form Handlers ********************************************************/

/**
 * Handles the front end reply submission
 *
 * @since bbPress (r2574)
 *
 * @uses bbPress:errors::add() To log various error messages
 * @uses check_admin_referer() To verify the nonce and check the referer
 * @uses bbp_is_anonymous() To check if an anonymous post is being made
 * @uses current_user_can() To check if the current user can publish replies
 * @uses bbp_get_current_user_id() To get the current user id
 * @uses bbp_filter_anonymous_post_data() To filter anonymous data
 * @uses bbp_set_current_anonymous_user_data() To set the anonymous user
 *                                                cookies
 * @uses is_wp_error() To check if the value retrieved is a {@link WP_Error}
 * @uses esc_attr() For sanitization
 * @uses bbp_check_for_flood() To check for flooding
 * @uses bbp_check_for_duplicate() To check for duplicates
 * @uses author_can() To check if the author of the reply can post unfiltered
 *                     html or not
 * @uses wp_filter_post_kses() To filter the post content
 * @uses wp_set_post_terms() To set the topic tags
 * @uses bbPress::errors::get_error_codes() To get the {@link WP_Error} errors
 * @uses wp_insert_post() To insert the reply
 * @uses do_action() Calls 'bbp_new_reply' with the reply id, topic id, forum
 *                    id, anonymous data and reply author
 * @uses bbp_get_reply_url() To get the paginated url to the reply
 * @uses wp_redirect() To redirect to the reply url
 * @uses bbPress::errors::get_error_message() To get the {@link WP_Error} error
 *                                              message
 */
function bbp_new_reply_handler() {
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
				bbp_set_current_anonymous_user_data( $anonymous_data );
		}

		// Handle Title (optional for replies)
		if ( !empty( $_POST['bbp_reply_title'] ) )
			$reply_title = esc_attr( strip_tags( $_POST['bbp_reply_title'] ) );

		// Handle Content
		if ( empty( $_POST['bbp_reply_content'] ) || !$reply_content = ( !bbp_is_anonymous() && author_can( $reply_author, 'unfiltered_html' ) ) ? $_POST['bbp_reply_content'] : wp_filter_post_kses( $_POST['bbp_reply_content'] ) ) {
			$bbp->errors->add( 'bbp_reply_content', __( '<strong>ERROR</strong>: Your reply cannot be empty.', 'bbpress' ) );
			$reply_content = '';
		}

		// Handle Topic ID to append reply to
		if ( empty( $_POST['bbp_topic_id'] ) || !$topic_id = $_POST['bbp_topic_id'] )
			$bbp->errors->add( 'bbp_reply_topic_id', __( '<strong>ERROR</strong>: Topic ID is missing.', 'bbpress' ) );

		// Handle Forum ID to adjust counts of
		if ( empty( $_POST['bbp_forum_id'] ) || !$forum_id = $_POST['bbp_forum_id'] )
			$bbp->errors->add( 'bbp_reply_forum_id', __( '<strong>ERROR</strong>: Forum ID is missing.', 'bbpress' ) );

		// Check for flood
		if ( !bbp_check_for_flood( $anonymous_data, $reply_author ) )
			$bbp->errors->add( 'bbp_reply_flood', __( '<strong>ERROR</strong>: Slow down; you move too fast.', 'bbpress' ) );

		// Check for duplicate
		if ( !bbp_check_for_duplicate( array( 'post_type' => $bbp->reply_id, 'post_author' => $reply_author, 'post_content' => $reply_content, 'post_parent' => $topic_id, 'anonymous_data' => $anonymous_data ) ) )
			$bbp->errors->add( 'bbp_reply_duplicate', __( '<strong>ERROR</strong>: Duplicate reply detected; it looks as though you&#8217;ve already said that!', 'bbpress' ) );

		// Handle Tags
		if ( !empty( $_POST['bbp_topic_tags'] ) ) {
			$tags = $_POST['bbp_topic_tags'];
			$tags = wp_set_post_terms( $topic_id, $tags, $bbp->topic_tag_id, true );

			if ( is_wp_error( $tags ) || false == $tags )
				$bbp->errors->add( 'bbp_reply_tags', __( '<strong>ERROR</strong>: There was some problem adding the tags to the topic.', 'bbpress' ) );
		}

		// Handle insertion into posts table
		if ( !is_wp_error( $bbp->errors ) || !$bbp->errors->get_error_codes() ) {

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

/**
 * Handles the front end edit reply submission
 *
 * @uses bbPress:errors::add() To log various error messages
 * @uses get_post() To get the reply
 * @uses check_admin_referer() To verify the nonce and check the referer
 * @uses bbp_is_reply_anonymous() To check if the reply was by an anonymous user
 * @uses current_user_can() To check if the current user can edit that reply
 * @uses bbp_filter_anonymous_post_data() To filter anonymous data
 * @uses is_wp_error() To check if the value retrieved is a {@link WP_Error}
 * @uses esc_attr() For sanitization
 * @uses author_can() To check if the author of the reply can post unfiltered
 *                     html or not
 * @uses wp_filter_post_kses() To filter the post content
 * @uses wp_set_post_terms() To set the topic tags
 * @uses bbPress::errors::get_error_codes() To get the {@link WP_Error} errors
 * @uses wp_update_post() To update the reply
 * @uses do_action() Calls 'bbp_edit_reply' with the reply id, topic id, forum
 *                    id, anonymous data, reply author and bool true (for edit)
 * @uses bbp_get_reply_url() To get the paginated url to the reply
 * @uses wp_redirect() To redirect to the reply url
 * @uses bbPress::errors::get_error_message() To get the {@link WP_Error} error
 *                                              message
 */
function bbp_edit_reply_handler() {
	// Only proceed if POST is an reply request
	if ( 'POST' == $_SERVER['REQUEST_METHOD'] && !empty( $_POST['action'] ) && 'bbp-edit-reply' === $_POST['action'] ) {
		global $bbp;

		if ( empty( $_POST['bbp_reply_id'] ) || !$reply_id = (int) $_POST['bbp_reply_id'] ) {
			$bbp->errors->add( 'bbp_edit_reply_id', __( '<strong>ERROR</strong>: Reply ID not found!', 'bbpress' ) );
		} elseif ( !$reply = get_post( $reply_id ) ) {
			$bbp->errors->add( 'bbp_edit_reply_not_found', __( '<strong>ERROR</strong>: The reply you want to edit was not found!', 'bbpress' ) );
		} else {

			// Nonce check
			check_admin_referer( 'bbp-edit-reply_' . $reply_id );

			// Check users ability to create new reply
			if ( !bbp_is_reply_anonymous( $reply_id ) ) {
				if ( !current_user_can( 'edit_reply', $reply_id ) ) {
					$bbp->errors->add( 'bbp_edit_reply_permissions', __( '<strong>ERROR</strong>: You do not have permission to edit that reply!', 'bbpress' ) );
				}

				$anonymous_data = false;

			// It is an anonymous post
			} else {
				$anonymous_data = bbp_filter_anonymous_post_data( array(), true ); // Filter anonymous data
			}

		}

		// Handle Title (optional for replies)
		$reply_title = !empty( $_POST['bbp_reply_title'] ) ? esc_attr( strip_tags( $_POST['bbp_reply_title'] ) ) : $reply_title = $reply->post_title;

		// Handle Content
		if ( empty( $_POST['bbp_reply_content'] ) || !$reply_content = ( !bbp_is_reply_anonymous( $reply_id ) && author_can( $reply->post_author, 'unfiltered_html' ) ) ? $_POST['bbp_reply_content'] : wp_filter_post_kses( $_POST['bbp_reply_content'] ) )
			$bbp->errors->add( 'bbp_edit_reply_content', __( '<strong>ERROR</strong>: Your reply cannot be empty.', 'bbpress' ) );

		// Handle insertion into posts table
		if ( !is_wp_error( $bbp->errors ) || !$bbp->errors->get_error_codes() ) {

			// Add the content of the form to $post as an array
			$reply_data = array(
				'ID'           => $reply_id,
				'post_title'   => $reply_title,
				'post_content' => $reply_content
			);

			// Insert reply
			$reply_id = wp_update_post( $reply_data );

			// Check for missing reply_id or error
			if ( !empty( $reply_id ) && !is_wp_error( $reply_id ) ) {

				// Update counts, etc...
				do_action( 'bbp_edit_reply', $reply_id, $reply->post_parent, bbp_get_topic_forum_id( $reply->post_parent ), $anonymous_data, $reply->post_author , true /* Is edit */ );

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

/**
 * Handle all the extra meta stuff from posting a new reply or editing a reply
 *
 * @param int $reply_id Optional. Reply id
 * @param int $topic_id Optional. Topic id
 * @param int $forum_id Optional. Forum id
 * @param bool|array $anonymous_data Optional. If it is an array, it is
 *                    extracted and anonymous user info is saved
 * @param int $author_id Author id
 * @param bool $is_edit Optional. Is the post being edited? Defaults to false.
 * @uses bbp_get_reply_id() To get the reply id
 * @uses bbp_get_topic_id() To get the topic id
 * @uses bbp_get_forum_id() To get the forum id
 * @uses bbp_get_current_user_id() To get the current user id
 * @uses update_post_meta() To update the reply metas
 * @uses set_transient() To update the flood check transient for the ip
 * @uses update_user_meta() To update the last posted meta for the user
 * @uses bbp_is_subscriptions_active() To check if the subscriptions feature is
 *                                      activated or not
 * @uses bbp_is_user_subscribed() To check if the user is subscribed
 * @uses bbp_remove_user_subscription() To remove the user's subscription
 * @uses bbp_add_user_subscription() To add the user's subscription
 * @uses bbp_update_topic_last_active() To update the last active topic meta
 * @uses bbp_update_forum_last_active() To update the last active forum meta
 * @uses bbp_update_topic_last_reply_id() To update the last reply id topic meta
 * @uses bbp_update_forum_last_topic_id() To update the last topic id forum meta
 * @uses bbp_update_forum_last_reply_id() To update the last reply id forum meta
 */
function bbp_new_reply_update_reply( $reply_id = 0, $topic_id = 0, $forum_id = 0, $anonymous_data = false, $author_id = 0, $is_edit = false ) {
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

		update_post_meta( $reply_id, '_bbp_anonymous_name',  $bbp_anonymous_name,  false );
		update_post_meta( $reply_id, '_bbp_anonymous_email', $bbp_anonymous_email, false );

		// Set transient for throttle check and update ip address meta (only when the reply is not being edited)
		if ( empty( $is_edit ) ) {
			update_post_meta( $reply_id, '_bbp_anonymous_ip', $bbp_anonymous_ip, false );
			set_transient( '_bbp_' . $bbp_anonymous_ip . '_last_posted', time() );
		}

		// Website is optional
		if ( !empty( $bbp_anonymous_website ) )
			update_post_meta( $reply_id, '_bbp_anonymous_website', $bbp_anonymous_website, false );
	} else {
		if ( empty( $is_edit ) && !current_user_can( 'throttle' ) )
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

	if ( empty( $is_edit ) ) {
		// Topic meta relating to most recent reply
		bbp_update_topic_last_reply_id( $topic_id, $reply_id );
		bbp_update_topic_last_active  ( $topic_id            );

		// Forum meta relating to most recent topic
		bbp_update_forum_last_topic_id( $forum_id, $topic_id );
		bbp_update_forum_last_reply_id( $forum_id, $reply_id );
		bbp_update_forum_last_active  ( $forum_id            );
	}
}

/**
 * Handles the front end topic submission
 *
 * @uses bbPress:errors::add() To log various error messages
 * @uses check_admin_referer() To verify the nonce and check the referer
 * @uses bbp_is_anonymous() To check if an anonymous post is being made
 * @uses current_user_can() To check if the current user can publish topic
 * @uses bbp_get_current_user_id() To get the current user id
 * @uses bbp_filter_anonymous_post_data() To filter anonymous data
 * @uses bbp_set_current_anonymous_user_data() To set the anonymous user cookies
 * @uses is_wp_error() To check if the value retrieved is a {@link WP_Error}
 * @uses esc_attr() For sanitization
 * @uses author_can() To check if the author of the reply can post unfiltered
 *                     html or not
 * @uses bbp_is_forum_category() To check if the forum is a category
 * @uses bbp_is_forum_closed() To check if the forum is closed
 * @uses bbp_is_forum_private() To check if the forum is private
 * @uses bbp_check_for_flood() To check for flooding
 * @uses bbp_check_for_duplicate() To check for duplicates
 * @uses wp_filter_post_kses() To filter the post content
 * @uses bbPress::errors::get_error_codes() To get the {@link WP_Error} errors
 * @uses wp_insert_post() To insert the topic
 * @uses do_action() Calls 'bbp_new_topic' with the topic id, forum id,
 *                    anonymous data and reply author
 * @uses bbp_get_topic_permalink() To get the topic permalink
 * @uses wp_redirect() To redirect to the topic link
 * @uses bbPress::errors::get_error_messages() To get the {@link WP_Error} error
 *                                              messages
 */
function bbp_new_topic_handler() {
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
				bbp_set_current_anonymous_user_data( $anonymous_data );
		}

		// Handle Title
		if ( empty( $_POST['bbp_topic_title'] ) || !$topic_title = esc_attr( strip_tags( $_POST['bbp_topic_title'] ) ) )
			$bbp->errors->add( 'bbp_topic_title', __( '<strong>ERROR</strong>: Your topic needs a title.', 'bbpress' ) );

		// Handle Content
		if ( empty( $_POST['bbp_topic_content'] ) || !$topic_content = ( !bbp_is_anonymous() && author_can( $topic_author, 'unfiltered_html' ) ) ? $_POST['bbp_topic_content'] : wp_filter_post_kses( $_POST['bbp_topic_content'] ) )
			$bbp->errors->add( 'bbp_topic_content', __( '<strong>ERROR</strong>: Your topic needs some content.', 'bbpress' ) );

		// Handle Forum id to append topic to
		if ( empty( $_POST['bbp_forum_id'] ) || !$forum_id = $_POST['bbp_forum_id'] ) {
			$bbp->errors->add( 'bbp_topic_forum_id', __( '<strong>ERROR</strong>: Forum ID is missing.', 'bbpress' ) );
		} else {
			if ( bbp_is_forum_category( $forum_id ) )
				$bbp->errors->add( 'bbp_topic_forum_category', __( '<strong>ERROR</strong>: This forum is a category. No topics can be created in this forum!', 'bbpress' ) );

			if ( bbp_is_forum_closed( $forum_id ) && !current_user_can( 'edit_forum', $forum_id ) )
				$bbp->errors->add( 'bbp_topic_forum_closed', __( '<strong>ERROR</strong>: This forum has been closed to new topics!', 'bbpress' ) );

			if ( bbp_is_forum_private( $forum_id ) && !current_user_can( 'read_private_forums' ) )
				$bbp->errors->add( 'bbp_topic_forum_private', __( '<strong>ERROR</strong>: This forum is private and you do not have the capability to read or create new topics in this forum!', 'bbpress' ) );
		}

		// Check for flood
		if ( !bbp_check_for_flood( $anonymous_data, $topic_author ) )
			$bbp->errors->add( 'bbp_topic_flood', __( '<strong>ERROR</strong>: Slow down; you move too fast.', 'bbpress' ) );

		// Check for duplicate
		if ( !bbp_check_for_duplicate( array( 'post_type' => $bbp->topic_id, 'post_author' => $topic_author, 'post_content' => $topic_content, 'anonymous_data' => $anonymous_data ) ) )
			$bbp->errors->add( 'bbp_topic_duplicate', __( '<strong>ERROR</strong>: Duplicate topic detected; it looks as though you&#8217;ve already said that!', 'bbpress' ) );

		// Handle Tags
		if ( !empty( $_POST['bbp_topic_tags'] ) ) {
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
		if ( !is_wp_error( $bbp->errors ) || !$bbp->errors->get_error_codes() ) {

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

/**
 * Handles the front end edit topic submission
 *
 * @uses bbPress:errors::add() To log various error messages
 * @uses get_post() To get the topic
 * @uses check_admin_referer() To verify the nonce and check the referer
 * @uses bbp_is_topic_anonymous() To check if topic is by an anonymous user
 * @uses current_user_can() To check if the current user can edit the topic
 * @uses bbp_filter_anonymous_post_data() To filter anonymous data
 * @uses is_wp_error() To check if the value retrieved is a {@link WP_Error}
 * @uses esc_attr() For sanitization
 * @uses author_can() To check if the author of the reply can post unfiltered
 *                     html or not
 * @uses bbp_is_forum_category() To check if the forum is a category
 * @uses bbp_is_forum_closed() To check if the forum is closed
 * @uses bbp_is_forum_private() To check if the forum is private
 * @uses wp_filter_post_kses() To filter the post content
 * @uses bbPress::errors::get_error_codes() To get the {@link WP_Error} errors
 * @uses wp_update_post() To update the topic
 * @uses do_action() Calls 'bbp_edit_topic' with the topic id, forum id,
 *                    anonymous data and reply author
 * @uses do_action() Calls 'bbp_move_topic' with the forum id and topic id, if
 *                    the old forum id doesn't equal the new one
 * @uses bbp_get_topic_permalink() To get the topic permalink
 * @uses wp_redirect() To redirect to the topic link
 * @uses bbPress::errors::get_error_messages() To get the {@link WP_Error} error
 *                                              messages
 */
function bbp_edit_topic_handler() {
	// Only proceed if POST is an edit topic request
	if ( 'POST' == $_SERVER['REQUEST_METHOD'] && !empty( $_POST['action'] ) && 'bbp-edit-topic' === $_POST['action'] ) {
		global $bbp;

		if ( !$topic_id = (int) $_POST['bbp_topic_id'] ) {
			$bbp->errors->add( 'bbp_edit_topic_id', __( '<strong>ERROR</strong>: Topic ID not found!', 'bbpress' ) );
		} elseif ( !$topic = get_post( $topic_id ) ) {
			$bbp->errors->add( 'bbp_edit_topic_not_found', __( '<strong>ERROR</strong>: The topic you want to edit was not found!', 'bbpress' ) );
		} else {
			// Nonce check
			check_admin_referer( 'bbp-edit-topic_' . $topic_id );

			// Check users ability to create new topic
			if ( !bbp_is_topic_anonymous( $topic_id ) ) {
				if ( !current_user_can( 'edit_topic', $topic_id ) )
					$bbp->errors->add( 'bbp_edit_topic_permissions', __( '<strong>ERROR</strong>: You do not have permission to edit that topic!', 'bbpress' ) );

				$anonymous_data = false;

			// It is an anonymous post
			} else {
				$anonymous_data = bbp_filter_anonymous_post_data( array(), true ); // Filter anonymous data
			}
		}

		// Handle Forum id to append topic to
		if ( empty( $_POST['bbp_forum_id'] ) || !$forum_id = $_POST['bbp_forum_id'] ) {
			$bbp->errors->add( 'bbp_topic_forum_id', __( '<strong>ERROR</strong>: Forum ID is missing.', 'bbpress' ) );
		} elseif ( $forum_id != $topic->post_parent ) {
			if ( bbp_is_forum_category( $forum_id ) )
				$bbp->errors->add( 'bbp_edit_topic_forum_category', __( '<strong>ERROR</strong>: This forum is a category. No topics can be created in this forum!', 'bbpress' ) );

			if ( bbp_is_forum_closed( $forum_id ) && !current_user_can( 'edit_forum', $forum_id ) )
				$bbp->errors->add( 'bbp_edit_topic_forum_closed', __( '<strong>ERROR</strong>: This forum has been closed to new topics!', 'bbpress' ) );

			if ( bbp_is_forum_private( $forum_id ) && !current_user_can( 'read_private_forums' ) )
				$bbp->errors->add( 'bbp_edit_topic_forum_private', __( '<strong>ERROR</strong>: This forum is private and you do not have the capability to read or create new topics in this forum!', 'bbpress' ) );
		}

		// Handle Title
		if ( empty( $_POST['bbp_topic_title'] ) || !$topic_title = esc_attr( strip_tags( $_POST['bbp_topic_title'] ) ) )
			$bbp->errors->add( 'bbp_edit_topic_title', __( '<strong>ERROR</strong>: Your topic needs a title.', 'bbpress' ) );

		// Handle Content
		if ( empty( $_POST['bbp_topic_content'] ) || !$topic_content = ( !bbp_is_topic_anonymous( $topic_id ) && author_can( $topic->post_author, 'unfiltered_html' ) ) ? $_POST['bbp_topic_content'] : wp_filter_post_kses( $_POST['bbp_topic_content'] ) )
			$bbp->errors->add( 'bbp_edit_topic_content', __( '<strong>ERROR</strong>: Your topic cannot be empty.', 'bbpress' ) );

		// Handle insertion into posts table
		if ( !is_wp_error( $bbp->errors ) || !$bbp->errors->get_error_codes() ) {

			// Add the content of the form to $post as an array
			$topic_data = array(
				'ID'           => $topic_id,
				'post_title'   => $topic_title,
				'post_content' => $topic_content,
				'post_parent'  => $forum_id
			);

			// Insert topic
			$topic_id = wp_update_post( $topic_data );

			// Check for missing topic_id or error
			if ( !empty( $topic_id ) && !is_wp_error( $topic_id ) ) {

				// If the new forum id is not equal to the old forum id, run the bbp_move_topic action and pass the topic's forum id as the first arg and topic id as the second to update counts
				if ( $forum_id != $topic->post_parent )
					do_action( 'bbp_move_topic', $topic->post_parent, $topic_id );

				// Update counts, etc...
				do_action( 'bbp_edit_topic', $topic_id, $forum_id, $anonymous_data, $topic->post_author , true /* Is edit */ );

				// Redirect back to new topic
				wp_redirect( bbp_get_topic_permalink( $topic_id ) );

				// For good measure
				exit();

			// Errors to report
			} else {
				$append_error = ( is_wp_error( $topic_id ) && $topic_id->get_error_message() ) ? $topic_id->get_error_message() . ' ' : '';
				$bbp->errors->add( 'bbp_topic_error', __( '<strong>ERROR</strong>: The following problem(s) have been found with your topic:' . $append_error . 'Please try again.', 'bbpress' ) );
			}
		}
	}
}

/**
 * Handle all the extra meta stuff from posting a new topic
 *
 * @param int $topic_id Optional. Topic id
 * @param int $forum_id Optional. Forum id
 * @param bool|array $anonymous_data Optional. If it is an array, it is
 *                    extracted and anonymous user info is saved
 * @param int $author_id Author id
 * @param bool $is_edit Optional. Is the post being edited? Defaults to false.
 * @uses bbp_get_topic_id() To get the topic id
 * @uses bbp_get_forum_id() To get the forum id
 * @uses bbp_get_current_user_id() To get the current user id
 * @uses update_post_meta() To update the topic metas
 * @uses set_transient() To update the flood check transient for the ip
 * @uses update_user_meta() To update the last posted meta for the user
 * @uses bbp_is_subscriptions_active() To check if the subscriptions feature is
 *                                      activated or not
 * @uses bbp_is_user_subscribed() To check if the user is subscribed
 * @uses bbp_remove_user_subscription() To remove the user's subscription
 * @uses bbp_add_user_subscription() To add the user's subscription
 * @uses bbp_update_topic_last_active() To update the last active topic meta
 * @uses bbp_update_forum_last_active() To update the last active forum meta
 * @uses bbp_update_topic_last_reply_id() To update the last reply id topic meta
 * @uses bbp_update_forum_last_topic_id() To update the last topic id forum meta
 * @uses bbp_update_forum_last_reply_id() To update the last reply id forum meta
 */
function bbp_new_topic_update_topic( $topic_id = 0, $forum_id = 0, $anonymous_data = false, $author_id = 0, $is_edit = false ) {
	// Validate the ID's passed from 'bbp_new_reply' action
	$topic_id = bbp_get_topic_id( $topic_id );
	$forum_id = bbp_get_forum_id( $forum_id );
	if ( empty( $author_id ) )
		$author_id = bbp_get_current_user_id();

	// If anonymous post, store name, email, website and ip in post_meta. It expects anonymous_data to be sanitized. Check bbp_filter_anonymous_post_data() for sanitization.
	if ( !empty( $anonymous_data ) && is_array( $anonymous_data ) ) {
		extract( $anonymous_data );

		update_post_meta( $topic_id, '_bbp_anonymous_name',  $bbp_anonymous_name,  false );
		update_post_meta( $topic_id, '_bbp_anonymous_email', $bbp_anonymous_email, false );

		// Set transient for throttle check and update ip address meta (only when the topic is not being edited)
		if ( empty( $is_edit ) ) {
			update_post_meta( $topic_id, '_bbp_anonymous_ip', $bbp_anonymous_ip, false );
			set_transient( '_bbp_' . $bbp_anonymous_ip . '_last_posted', time() );
		}

		// Website is optional
		if ( !empty( $bbp_anonymous_website ) )
			update_post_meta( $topic_id, '_bbp_anonymous_website', $bbp_anonymous_website, false );
	} else {
		if ( empty( $is_edit ) && !current_user_can( 'throttle' ) )
			update_user_meta( $author_id, '_bbp_last_posted', time() );
	}

	// Handle Subscription Checkbox
	if ( bbp_is_subscriptions_active() ) {
		if ( !empty( $_POST['bbp_topic_subscription'] ) && 'bbp_subscribe' == $_POST['bbp_topic_subscription'] ) {
			bbp_add_user_subscription( $author_id, $topic_id );
		}
	}

	// Handle Subscription Checkbox
	if ( bbp_is_subscriptions_active() && !empty( $author_id ) ) {
		$subscheck  = ( !empty( $_POST['bbp_topic_subscription'] ) && 'bbp_subscribe' == $_POST['bbp_topic_subscription'] ) ? true : false;

		// Subscribed and unsubscribing and is a topic edit
		if ( !empty( $is_edit ) && false == $subscheck && true == bbp_is_user_subscribed( $author_id, $topic_id ) )
			bbp_remove_user_subscription( $author_id, $topic_id );

		// Subscribing
		elseif ( true == $subscheck && false == bbp_is_user_subscribed( $author_id, $topic_id ) )
			bbp_add_user_subscription( $author_id, $topic_id );
	}

	if ( empty( $is_edit ) ) {
		// Topic meta relating to most recent topic
		bbp_update_topic_last_reply_id( $topic_id, 0 );
		bbp_update_topic_last_active  ( $topic_id     );

		// Forum meta relating to most recent topic
		bbp_update_forum_last_topic_id( $forum_id, $topic_id );
		bbp_update_forum_last_reply_id( $forum_id, 0         );
		bbp_update_forum_last_active  ( $forum_id            );
	}
}

/**
 * Filter anonymous post data
 *
 * We use REMOTE_ADDR here directly. If you are behind a proxy, you should
 * ensure that it is properly set, such as in wp-config.php, for your
 * environment. See {@link http://core.trac.wordpress.org/ticket/9235}
 *
 * If there are any errors, those are directly added to {@link bbPress:errors}
 *
 * @since bbPress (r2734)
 *
 * @param mixed $args Optional. If no args are there, then $_POST values are
 *                     used.
 * @param bool $is_edit Optional. Is the topic/reply being edited? There are no
 *                       IP checks then.
 * @uses apply_filters() Calls 'bbp_pre_anonymous_post_author_name' with the
 *                        anonymous user name
 * @uses apply_filters() Calls 'bbp_pre_anonymous_post_author_email' with the
 *                        anonymous user email
 * @uses apply_filters() Calls 'bbp_pre_anonymous_post_author_ip' with the
 *                        anonymous user's ip address
 * @uses apply_filters() Calls 'bbp_pre_anonymous_post_author_website' with the
 *                        anonymous user website
 * @return bool|array False on errors, values in an array on success
 */
function bbp_filter_anonymous_post_data( $args = '', $is_edit = false ) {
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
		$bbp->errors->add( 'bbp_anonymous_name',  __( '<strong>ERROR</strong>: Invalid author name submitted!',   'bbpress' ) );

	if ( !$bbp_anonymous_email = apply_filters( 'bbp_pre_anonymous_post_author_email', $bbp_anonymous_email ) )
		$bbp->errors->add( 'bbp_anonymous_email', __( '<strong>ERROR</strong>: Invalid email address submitted!', 'bbpress' ) );

	if ( empty( $is_edit ) ) {
		if ( !$bbp_anonymous_ip = apply_filters( 'bbp_pre_anonymous_post_author_ip', preg_replace( '/[^0-9a-fA-F:., ]/', '', $bbp_anonymous_ip ) ) )
			$bbp->errors->add( 'bbp_anonymous_ip', __( '<strong>ERROR</strong>: Invalid IP address! Where are you from?', 'bbpress' ) );
	} else {
		$bbp_anonymous_ip = false;
	}

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
 * Check for duplicate topics/replies
 *
 * Check to make sure that a user is not making a duplicate post
 *
 * @since bbPress (r2763)
 *
 * @param array $post_data Contains information about the comment
 * @uses current_user_can() To check if the current user can throttle
 * @uses _get_meta_sql() To generate the meta sql for checking anonymous email
 * @uses apply_filters() Calls 'bbp_check_for_duplicate_query' with the
 *                        duplicate check query and post data
 * @uses wpdb::get_var() To execute our query and get the var back
 * @uses get_post_meta() To get the anonymous user email post meta
 * @uses do_action() Calls 'bbp_post_duplicate_trigger' with the post data when
 *                    it is found that it is a duplicate
 * @return bool True if it is not a duplicate, false if it is
 */
function bbp_check_for_duplicate( $post_data ) {

	// No duplicate checks for those who can throttle
	if ( current_user_can( 'throttle' ) )
		return true;

	global $bbp, $wpdb;

	extract( $post_data, EXTR_SKIP );

	// Check for anonymous post
	if ( empty( $post_author ) && !empty( $anonymous_data['bbp_anonymous_email'] ) ) {
		$clauses = _get_meta_sql( array( array( 'key' => '_bbp_anonymous_email', 'value' => $anonymous_data['bbp_anonymous_email'] ) ), 'post', $wpdb->posts, 'ID' );
		$join    = $clauses['join'];
		$where   = $clauses['where'];
	} else{
		$join    = $where = '';
	}

	// Simple duplicate check
	// Expected slashed ($post_type, $post_parent, $post_author, $post_content, $anonymous_data)
	$dupe  = "SELECT ID FROM $wpdb->posts $join WHERE post_type = '$post_type' AND post_status != '$bbp->trash_status_id' AND post_author = $post_author AND post_content = '$post_content' $where";
	$dupe .= !empty( $post_parent ) ? " AND post_parent = '$post_parent'" : '';
	$dupe .= " LIMIT 1";
	$dupe  = apply_filters( 'bbp_check_for_duplicate_query', $dupe, $post_data );

	if ( $wpdb->get_var( $dupe ) ) {
		do_action( 'bbp_check_for_duplicate_trigger', $post_data );
		return false;
	}

	return true;
}

/**
 * Check for flooding
 *
 * Check to make sure that a user is not making too many posts in a short amount
 * of time.
 *
 * @since bbPress (r2734)
 *
 * @param false|array $anonymous_data Optional - if it's an anonymous post. Do
 *                                     not supply if supplying $author_id.
 *                                     Should have key 'bbp_anonymous_ip'.
 *                                     Should be sanitized (see
 *                                     {@link bbp_filter_anonymous_post_data()}
 *                                     for sanitization)
 * @param int $author_id Optional. Supply if it's a post by a logged in user.
 *                        Do not supply if supplying $anonymous_data.
 * @uses get_option() To get the throttle time
 * @uses get_transient() To get the last posted transient of the ip
 * @uses get_user_meta() To get the last posted meta of the user
 * @uses current_user_can() To check if the current user can throttle
 * @return bool True if there is no flooding, true if there is
 */
function bbp_check_for_flood( $anonymous_data = false, $author_id = 0 ) {

	// Option disabled. No flood checks.
	if ( !$throttle_time = get_option( '_bbp_throttle_time' ) )
		return true;

	if ( !empty( $anonymous_data ) && is_array( $anonymous_data ) && !empty( $anonymous_data['bbp_anonymous_ip'] ) ) {
		$last_posted = get_transient( '_bbp_' . $anonymous_data['bbp_anonymous_ip'] . '_last_posted' );
		if ( !empty( $last_posted ) && time() < $last_posted + $throttle_time )
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
 * Merge topic handler
 *
 * Handles the front end merge topic submission
 *
 * @since bbPress (r2756)
 *
 * @uses bbPress:errors::add() To log various error messages
 * @uses get_post() To get the topics
 * @uses check_admin_referer() To verify the nonce and check the referer
 * @uses current_user_can() To check if the current user can edit the topics
 * @uses is_wp_error() To check if the value retrieved is a {@link WP_Error}
 * @uses do_action() Calls 'bbp_merge_topic' with the destination and source
 *                    topic ids
 * @uses bbp_get_topic_subscribers() To get the source topic subscribers
 * @uses bbp_add_user_subscription() To add the user subscription
 * @uses bbp_remove_user_subscription() To remove the user subscription
 * @uses bbp_get_topic_favoriters() To get the source topic favoriters
 * @uses bbp_add_user_favorite() To add the user favorite
 * @uses bbp_remove_user_favorite() To remove the user favorite
 * @uses wp_get_post_terms() To get the source topic tags
 * @uses wp_set_post_terms() To set the topic tags
 * @uses wp_delete_object_term_relationships() To delete the topic tags
 * @uses bbp_open_topic() To open the topic
 * @uses bbp_unstick_topic() To unstick the topic
 * @uses get_posts() To get the replies
 * @uses wp_update_post() To update the topic
 * @uses do_action() Calls 'bbp_merged_topic' with the destination and source
 *                    topic ids and source topic's forum id
 * @uses bbp_get_topic_permalink() To get the topic permalink
 * @uses wp_redirect() To redirect to the topic link
 */
function bbp_merge_topic_handler() {
	// Only proceed if POST is an merge topic request
	if ( 'POST' == $_SERVER['REQUEST_METHOD'] && !empty( $_POST['action'] ) && 'bbp-merge-topic' === $_POST['action'] ) {
		global $bbp;

		if ( !$source_topic_id = (int) $_POST['bbp_topic_id'] )
			$bbp->errors->add( 'bbp_merge_topic_source_id', __( '<strong>ERROR</strong>: Topic ID not found!', 'bbpress' ) );

		// Nonce check
		check_admin_referer( 'bbp-merge-topic_' . $source_topic_id );

		if ( !$source_topic = get_post( $source_topic_id ) )
			$bbp->errors->add( 'bbp_merge_topic_source_not_found', __( '<strong>ERROR</strong>: The topic you want to merge was not found!', 'bbpress' ) );

		if ( !current_user_can( 'edit_topic', $source_topic->ID ) )
			$bbp->errors->add( 'bbp_merge_topic_source_permission', __( '<strong>ERROR</strong>: You do not have the permissions to edit the source topic!', 'bbpress' ) );

		if ( !$destination_topic_id = (int) $_POST['bbp_destination_topic'] )
			$bbp->errors->add( 'bbp_merge_topic_destination_id', __( '<strong>ERROR</strong>: Destination topic ID not found!', 'bbpress' ) );

		if ( !$destination_topic = get_post( $destination_topic_id ) )
			$bbp->errors->add( 'bbp_merge_topic_destination_not_found', __( '<strong>ERROR</strong>: The topic you want to merge to was not found!', 'bbpress' ) );

		if ( !current_user_can( 'edit_topic', $destination_topic->ID ) )
			$bbp->errors->add( 'bbp_merge_topic_destination_permission', __( '<strong>ERROR</strong>: You do not have the permissions to edit the destination topic!', 'bbpress' ) );

		// Handle the merge
		if ( !is_wp_error( $bbp->errors ) || !$bbp->errors->get_error_codes() ) {

			// Update counts, etc...
			do_action( 'bbp_merge_topic', $destination_topic->ID, $source_topic->ID );

			// Remove the topic from everybody's subscriptions
			$subscribers = bbp_get_topic_subscribers( $source_topic->ID );
			foreach ( (array) $subscribers as $subscriber ) {

				// Shift the subscriber if told to
				if ( !empty( $_POST['bbp_topic_subscribers'] ) && 1 == $_POST['bbp_topic_subscribers'] && bbp_is_subscriptions_active() )
					bbp_add_user_subscription( $subscriber, $destination_topic->ID );

				bbp_remove_user_subscription( $subscriber, $source_topic->ID );
			}

			// Remove the topic from everybody's favorites
			$favoriters = bbp_get_topic_favoriters( $source_topic->ID );
			foreach ( (array) $favoriters as $favoriter ) {

				// Shift the favoriter if told to
				if ( !empty( $_POST['bbp_topic_favoriters'] ) && 1 == $_POST['bbp_topic_favoriters'] )
					bbp_add_user_favorite( $favoriter, $destination_topic->ID );

				bbp_remove_user_favorite( $favoriter, $source_topic->ID );
			}

			// Get the source topic tags
			$source_topic_tags = wp_get_post_terms( $source_topic->ID, $bbp->topic_tag_id, array( 'fields' => 'names' ) );
			if ( !empty( $source_topic_tags ) && !is_wp_error( $source_topic_tags ) ) {

				// Shift the tags if told to
				if ( !empty( $_POST['bbp_topic_tags'] ) && 1 == $_POST['bbp_topic_tags'] )
					wp_set_post_terms( $destination_topic->ID, $source_topic_tags, $bbp->topic_tag_id, true );

				// Delete the tags from the source topic
				wp_delete_object_term_relationships( $source_topic->ID, $bbp->topic_tag_id );
			}

			// Attempt to revert the closed/sticky status
			bbp_open_topic   ( $source_topic->ID );
			bbp_unstick_topic( $source_topic->ID );

			// Get the replies of the source topic
			$replies = (array) get_posts( array( 'post_parent' => $source_topic->ID, 'post_type' => $bbp->reply_id, 'posts_per_page' => -1, 'order' => 'ASC' ) );

			// Prepend the source topic to its replies array for processing
			array_unshift( $replies, $source_topic );

			// Change the post_parent of each reply to the destination topic id
			foreach ( $replies as $reply ) {
				$postarr = array(
					'ID'          => $reply->ID,
					'post_title'  => sprintf( __( 'Reply To: %s', 'bbpress' ), $destination_topic->post_title ),
					'post_name'   => false, // will be automatically generated
					'post_type'   => $bbp->reply_id,
					'post_parent' => $destination_topic->ID,
					'guid'        => '' // @todo Make this work somehow
				);

				wp_update_post( $postarr );
			}

			// And we're done! ;)
			// Whew! Run the action and redirect!

			// Update counts, etc...
			// We sent the post parent of the source topic because the source topic has been actually shifted (and might be to a new forum), so we need to update the counts of the old forum too!
			do_action( 'bbp_merged_topic', $destination_topic->ID, $source_topic->ID, $source_topic->post_parent );

			// Redirect back to new topic
			wp_redirect( bbp_get_topic_permalink( $destination_topic->ID ) );

			// For good measure
			exit();
		}
	}
}

/**
 * Fix counts on topic merge
 *
 * When a topic is merged, update the counts of source and destination topic
 * and their forums.
 *
 * @since bbPress (r2756)
 *
 * @param int $destination_topic_id Destination topic id
 * @param int $source_topic_id Source topic id
 * @param int $source_topic_forum Source topic's forum id
 * @uses bbp_update_forum_topic_count() To update the forum topic counts
 * @uses bbp_update_forum_reply_count() To update the forum reply counts
 * @uses bbp_update_forum_voice_count() To update the forum voice counts
 * @uses bbp_update_topic_reply_count() To update the topic reply counts
 * @uses bbp_update_topic_voice_count() To update the topic voice counts
 * @uses bbp_update_topic_hidden_reply_count() To update the topic hidden reply
 *                                              count
 * @uses do_action() Calls 'bbp_merge_topic_count' with the destination topic
 *                    id, source topic id & source topic forum id
 */
function bbp_merge_topic_count( $destination_topic_id, $source_topic_id, $source_topic_forum_id ) {

	// Forum Topic Counts
	bbp_update_forum_topic_count( $source_topic_forum_id );
	bbp_update_forum_topic_count( $destination_topic_id  );

	// Forum Reply Counts
	bbp_update_forum_reply_count( $source_topic_forum_id );
	bbp_update_forum_reply_count( $destination_topic_id  );

	// Forum Voice Counts
	bbp_update_forum_voice_count( $source_topic_forum_id );
	bbp_update_forum_voice_count( $destination_topic_id  );

	// Topic Reply Counts
	bbp_update_topic_reply_count( $destination_topic_id );

	// Topic Hidden Reply Counts
	bbp_update_topic_hidden_reply_count( $destination_topic_id );

	// Topic Voice Counts
	bbp_update_topic_voice_count( $destination_topic_id );

	do_action( 'bbp_merge_topic_count', $destination_topic_id, $source_topic_id, $source_topic_forum_id );
}

/**
 * Split topic handler
 *
 * Handles the front end split topic submission
 *
 * @since bbPress (r2756)
 *
 * @uses bbPress:errors::add() To log various error messages
 * @uses get_post() To get the reply and topics
 * @uses check_admin_referer() To verify the nonce and check the referer
 * @uses current_user_can() To check if the current user can edit the topics
 * @uses is_wp_error() To check if the value retrieved is a {@link WP_Error}
 * @uses do_action() Calls 'bbp_pre_split_topic' with the from reply id, source
 *                    and destination topic ids
 * @uses bbp_get_topic_subscribers() To get the source topic subscribers
 * @uses bbp_add_user_subscription() To add the user subscription
 * @uses bbp_get_topic_favoriters() To get the source topic favoriters
 * @uses bbp_add_user_favorite() To add the user favorite
 * @uses wp_get_post_terms() To get the source topic tags
 * @uses wp_set_post_terms() To set the topic tags
 * @uses wpdb::prepare() To prepare our sql query
 * @uses wpdb::get_results() To execute the sql query and get results
 * @uses wp_update_post() To update the replies
 * @uses bbp_update_topic_last_reply_id() To update the topic last reply id
 * @uses bbp_update_topic_last_active() To update the topic last active meta
 * @uses do_action() Calls 'bbp_post_split_topic' with the destination and
 *                    source topic ids and source topic's forum id
 * @uses bbp_get_topic_permalink() To get the topic permalink
 * @uses wp_redirect() To redirect to the topic link
 */
function bbp_split_topic_handler() {
	// Only proceed if POST is an split topic request
	if ( 'POST' == $_SERVER['REQUEST_METHOD'] && !empty( $_POST['action'] ) && 'bbp-split-topic' === $_POST['action'] ) {
		global $wpdb, $bbp;

		if ( !$from_reply_id = (int) $_POST['bbp_reply_id'] )
			$bbp->errors->add( 'bbp_split_topic_reply_id', __( '<strong>ERROR</strong>: Reply ID to split the topic from not found!', 'bbpress' ) );

		if ( !$from_reply = get_post( $from_reply_id ) )
			$bbp->errors->add( 'bbp_split_topic_r_not_found', __( '<strong>ERROR</strong>: The reply you want to split from was not found!', 'bbpress' ) );

		if ( !$source_topic = get_post( $from_reply->post_parent ) )
			$bbp->errors->add( 'bbp_split_topic_source_not_found', __( '<strong>ERROR</strong>: The topic you want to split was not found!', 'bbpress' ) );

		// Nonce check
		check_admin_referer( 'bbp-split-topic_' . $source_topic->ID );

		if ( !current_user_can( 'edit_topic', $source_topic->ID ) )
			$bbp->errors->add( 'bbp_split_topic_source_permission', __( '<strong>ERROR</strong>: You do not have the permissions to edit the source topic!', 'bbpress' ) );

		$split_option = !empty( $_POST['bbp_topic_split_option'] ) ? (string) trim( $_POST['bbp_topic_split_option'] ) : false;
		if ( empty( $split_option ) || !in_array( $split_option, array( 'existing', 'reply' ) ) )
			$bbp->errors->add( 'bbp_split_topic_option', __( '<strong>ERROR</strong>: You need to choose a valid split option!', 'bbpress' ) );

		switch ( $split_option ) {
			case 'existing' :
				if ( !$destination_topic_id = (int) $_POST['bbp_destination_topic'] )
					$bbp->errors->add( 'bbp_split_topic_destination_id', __( '<strong>ERROR</strong>: Destination topic ID not found!', 'bbpress' ) );

				if ( !$destination_topic = get_post( $destination_topic_id ) )
					$bbp->errors->add( 'bbp_split_topic_destination_not_found', __( '<strong>ERROR</strong>: The topic you want to split to was not found!', 'bbpress' ) );

				if ( !current_user_can( 'edit_topic', $destination_topic->ID ) )
					$bbp->errors->add( 'bbp_split_topic_destination_permission', __( '<strong>ERROR</strong>: You do not have the permissions to edit the destination topic!', 'bbpress' ) );

				break;

			case 'reply' :
			default :
				if ( current_user_can( 'publish_topics' ) ) {

					if ( !$destination_topic_title = esc_attr( strip_tags( $_POST['bbp_topic_split_destination_title'] ) ) )
						$destination_topic_title = $source_topic->post_title;

					$postarr = array(
						'ID'          => $from_reply->ID,
						'post_title'  => $destination_topic_title,
						'post_name'   => false, // will be automatically generated
						'post_type'   => $bbp->topic_id,
						'post_parent' => $source_topic->post_parent,
						'guid'        => '' // @todo Make this work somehow
					);

					$destination_topic_id = wp_update_post( $postarr );

					// Shouldn't happen
					if ( false == $destination_topic_id || is_wp_error( $destination_topic_id ) || !$destination_topic = get_post( $destination_topic_id ) )
						$bbp->errors->add( 'bbp_split_topic_destination_reply', __( '<strong>ERROR</strong>: There was a problem converting the reply into the topic, please try again!', 'bbpress' ) );

				} else {
					$bbp->errors->add( 'bbp_split_topic_destination_permission', __( '<strong>ERROR</strong>: You do not have the permissions to create new topics and hence the reply could not be converted into a topic!', 'bbpress' ) );
				}

				break;
		}

		// We should have the from reply, source topic & destination topic by now.

		// Handle the split
		if ( !is_wp_error( $bbp->errors ) || !$bbp->errors->get_error_codes() ) {

			// Update counts, etc...
			do_action( 'bbp_pre_split_topic', $from_reply->ID, $source_topic->ID, $destination_topic->ID );

			// Copy the subscribers if told to
			if ( !empty( $_POST['bbp_topic_subscribers'] ) && 1 == $_POST['bbp_topic_subscribers'] && bbp_is_subscriptions_active() ) {
				$subscribers = bbp_get_topic_subscribers( $source_topic->ID );

				foreach ( (array) $subscribers as $subscriber ) {
					bbp_add_user_subscription( $subscriber, $destination_topic->ID );
				}
			}

			// Copy the favoriters if told to
			if ( !empty( $_POST['bbp_topic_favoriters'] ) && 1 == $_POST['bbp_topic_favoriters'] ) {
				$favoriters = bbp_get_topic_favoriters( $source_topic->ID );

				foreach ( (array) $favoriters as $favoriter ) {
					bbp_add_user_favorite( $favoriter, $destination_topic->ID );
				}
			}

			// Copy the tags if told to
			if ( !empty( $_POST['bbp_topic_tags'] ) && 1 == $_POST['bbp_topic_tags'] ) {
				// Get the source topic tags
				$source_topic_tags = wp_get_post_terms( $source_topic->ID, $bbp->topic_tag_id, array( 'fields' => 'names' ) );

				wp_set_post_terms( $destination_topic->ID, $source_topic_tags, $bbp->topic_tag_id, true );
			}

			// Get the replies of the source topic
			// get_posts() is not used because it doesn't allow us to use '>=' comparision without a filter
			$replies = (array) $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $wpdb->posts WHERE $wpdb->posts.post_date >= %s AND $wpdb->posts.post_parent = %d AND $wpdb->posts.post_type = %s ORDER BY $wpdb->posts.post_date ASC", $from_reply->post_date, $source_topic->ID, $bbp->reply_id ) );

			// Change the post_parent of each reply to the destination topic id
			foreach ( $replies as $reply ) {
				$postarr = array(
					'ID'          => $reply->ID,
					'post_title'  => sprintf( __( 'Reply To: %s', 'bbpress' ), $destination_topic->post_title ),
					'post_name'   => false, // will be automatically generated
					'post_parent' => $destination_topic->ID,
					'guid'        => '' // @todo Make this work somehow
				);

				wp_update_post( $postarr );
			}

			// It is a new topic and we need to set some default metas to make the topic display in bbp_has_topics() list
			if ( 'reply' == $split_option ) {
				$last_reply_id = ( empty( $reply ) || empty( $reply->ID        ) ) ? 0  : $reply->ID;
				$freshness     = ( empty( $reply ) || empty( $reply->post_date ) ) ? '' : $reply->post_date;

				bbp_update_topic_last_reply_id( $destination_topic->ID, $last_reply_id );
				bbp_update_topic_last_active  ( $destination_topic->ID, $freshness     );
			}

			// And we're done! ;)
			// Whew! Run the action and redirect!

			// Update counts, etc...
			do_action( 'bbp_post_split_topic', $from_reply->ID, $source_topic->ID, $destination_topic->ID );

			// Redirect back to the topic
			wp_redirect( bbp_get_topic_permalink( $destination_topic->ID ) );

			// For good measure
			exit();
		}
	}
}

/**
 * Fix counts on topic split
 *
 * When a topic is split, update the counts of source and destination topic
 * and their forums.
 *
 * @since bbPress (r2756)
 *
 * @param int $from_reply_id From reply id
 * @param int $source_topic_id Source topic id
 * @param int $destination_topic_id Destination topic id
 * @uses bbp_update_forum_topic_count() To update the forum topic counts
 * @uses bbp_update_forum_reply_count() To update the forum reply counts
 * @uses bbp_update_forum_voice_count() To update the forum voice counts
 * @uses bbp_update_topic_reply_count() To update the topic reply counts
 * @uses bbp_update_topic_voice_count() To update the topic voice counts
 * @uses bbp_update_topic_hidden_reply_count() To update the topic hidden reply
 *                                              count
 * @uses do_action() Calls 'bbp_split_topic_count' with the from reply id,
 *                    source topic id & destination topic id
 */
function bbp_split_topic_count( $from_reply_id, $source_topic_id, $destination_topic_id ) {

	// Forum Topic Counts
	bbp_update_forum_topic_count( $source_topic_id      );
	bbp_update_forum_topic_count( $destination_topic_id );

	// Forum Reply Counts
	bbp_update_forum_reply_count( $source_topic_id      );
	bbp_update_forum_reply_count( $destination_topic_id );

	// Forum Voice Counts
	bbp_update_forum_voice_count( $source_topic_id      );
	bbp_update_forum_voice_count( $destination_topic_id );

	// Topic Reply Counts
	bbp_update_topic_reply_count( $source_topic_id      );
	bbp_update_topic_reply_count( $destination_topic_id );

	// Topic Hidden Reply Counts
	bbp_update_topic_hidden_reply_count( $source_topic_id      );
	bbp_update_topic_hidden_reply_count( $destination_topic_id );

	// Topic Voice Counts
	bbp_update_topic_voice_count( $source_topic_id      );
	bbp_update_topic_voice_count( $destination_topic_id );

	do_action( 'bbp_split_topic_count', $from_reply_id, $source_topic_id, $destination_topic_id );
}

/**
 * Handles the front end tag management (renaming, merging, destroying)
 *
 * @since bbPress (r2768)
 *
 * @uses check_admin_referer() To verify the nonce and check the referer
 * @uses current_user_can() To check if the current user can edit/delete tags
 * @uses bbPress::errors::add() To log the error messages
 * @uses wp_update_term() To update the topic tag
 * @uses get_term_link() To get the topic tag url
 * @uses term_exists() To check if the topic tag already exists
 * @uses wp_insert_term() To insert a topic tag
 * @uses wp_delete_term() To delete the topic tag
 * @uses home_url() To get the blog's home page url
 * @uses do_action() Calls actions based on the actions with associated args
 * @uses is_wp_error() To check if the value retrieved is a {@link WP_Error}
 * @uses wp_redirect() To redirect to the url
 */
function bbp_manage_topic_tag_handler() {

	if ( 'POST' == $_SERVER['REQUEST_METHOD'] && !empty( $_POST['action'] ) && in_array( $_POST['action'], array( 'bbp-update-topic-tag', 'bbp-merge-topic-tag', 'bbp-delete-topic-tag' ) ) && !empty( $_POST['tag-id'] ) ) {

		global $bbp;

		$action = $_POST['action'];
		$tag_id = (int) $_POST['tag-id'];
		$tag    = get_term( $tag_id, $bbp->topic_tag_id );

		if ( is_wp_error( $tag ) && $tag->get_error_message() ) {
			$append_error = $tag->get_error_message() . ' ';
			$bbp->errors->add( 'bbp_manage_topic_invalid_tag', __( '<strong>ERROR</strong>: The following problem(s) have been found while getting the tag:' . $append_error . 'Please try again.', 'bbpress' ) );
			return;
		}

		switch ( $action ) {
			case 'bbp-update-topic-tag' :
				check_admin_referer( 'update-tag_' . $tag_id );

				if ( !current_user_can( 'edit_topic_tags' ) ) {
					$bbp->errors->add( 'bbp_manage_topic_tag_update_permissions', __( '<strong>ERROR</strong>: You do not have the permissions to edit the topic tags!', 'bbpress' ) );
					return;
				}

				if ( empty( $_POST['tag-name'] ) || !$name = $_POST['tag-name'] ) {
					$bbp->errors->add( 'bbp_manage_topic_tag_update_name', __( '<strong>ERROR</strong>: You need to enter a tag name!', 'bbpress' ) );
					return;
				}

				$slug = !empty( $_POST['tag-slug'] ) ? $_POST['tag-slug'] : '';

				$tag = wp_update_term( $tag_id, $bbp->topic_tag_id, array( 'name' => $name, 'slug' => $slug ) );

				if ( is_wp_error( $tag ) && $tag->get_error_message() ) {
					$append_error = $tag->get_error_message() . ' ';
					$bbp->errors->add( 'bbp_manage_topic_tag_update_error', __( '<strong>ERROR</strong>: The following problem(s) have been found while updating the tag:' . $append_error . 'Please try again.', 'bbpress' ) );
					return;
				}

				$redirect = get_term_link( $tag_id, $bbp->topic_tag_id );

				// Update counts, etc...
				do_action( 'bbp_update_topic_tag', $tag_id, $tag, $name, $slug );

				break;

			case 'bbp-merge-topic-tag'  :
				check_admin_referer( 'merge-tag_' . $tag_id );

				if ( !current_user_can( 'edit_topic_tags' ) ) {
					$bbp->errors->add( 'bbp_manage_topic_tag_merge_permissions', __( '<strong>ERROR</strong>: You do not have the permissions to edit the topic tags!', 'bbpress' ) );
					return;
				}

				if ( empty( $_POST['tag-name'] ) || !$name = $_POST['tag-name'] ) {
					$bbp->errors->add( 'bbp_manage_topic_tag_merge_name', __( '<strong>ERROR</strong>: You need to enter a tag name!', 'bbpress' ) );
					return;
				}

				// Much part of merge tags functionality taken from Scribu's Term Management Tools WordPress Plugin

				if ( !$tag = term_exists( $name, $bbp->topic_tag_id ) )
					$tag = wp_insert_term( $name, $bbp->topic_tag_id );

				if ( is_wp_error( $tag ) && $tag->get_error_message() ) {
					$append_error = $tag->get_error_message() . ' ';
					$bbp->errors->add( 'bbp_manage_topic_tag_merge_error', __( '<strong>ERROR</strong>: The following problem(s) have been found while merging the tags:' . $append_error . 'Please try again.', 'bbpress' ) );
					return;
				}

				$to_tag = $tag['term_id'];

				if ( $tag_id == $to_tag ) {
					$bbp->errors->add( 'bbp_manage_topic_tag_merge_same', __( '<strong>ERROR</strong>: The tags which are being merged can not be the same.', 'bbpress' ) );
					return;
				}

				$tag = wp_delete_term( $tag_id, $bbp->topic_tag_id, array( 'default' => $to_tag, 'force_default' => true ) );

				if ( is_wp_error( $tag ) && $tag->get_error_message() ) {
					$append_error = $tag->get_error_message() . ' ';
					$bbp->errors->add( 'bbp_manage_topic_tag_merge_error', __( '<strong>ERROR</strong>: The following problem(s) have been found while merging the tags:' . $append_error . 'Please try again.', 'bbpress' ) );
					return;
				}

				$redirect = get_term_link( (int) $to_tag, $bbp->topic_tag_id );

				// Update counts, etc...
				do_action( 'bbp_merge_topic_tag', $tag_id, $to_tag, $tag );

				break;

			case 'bbp-delete-topic-tag' :
				check_admin_referer( 'delete-tag_' . $tag_id );

				if ( !current_user_can( 'delete_topic_tags' ) ) {
					$bbp->errors->add( 'bbp_manage_topic_tag_delete_permissions', __( '<strong>ERROR</strong>: You do not have the permissions to delete the topic tags!', 'bbpress' ) );
					return;
				}

				$tag = wp_delete_term( $tag_id, $bbp->topic_tag_id );

				if ( is_wp_error( $tag ) && $tag->get_error_message() ) {
					$append_error = $tag->get_error_message() . ' ';
					$bbp->errors->add( 'bbp_manage_topic_tag_delete_error', __( '<strong>ERROR</strong>: The following problem(s) have been found while deleting the tag:' . $append_error . 'Please try again.', 'bbpress' ) );
					return;
				}

				// We don't have any other place to go other than home! Or we may die because of the 404 disease
				$redirect = home_url();

				// Update counts, etc...
				do_action( 'bbp_delete_topic_tag', $tag_id, $tag );

				break;
		}

		// Redirect back
		$redirect = ( !empty( $redirect ) && !is_wp_error( $redirect ) ) ? $redirect : home_url();
		wp_redirect( $redirect );

		// For good measure
		exit();

	}
}

/**
 * Handles the front end user editing
 *
 * @uses is_multisite() To check if it's a multisite
 * @uses bbp_is_user_home() To check if the user is at home (the display page
 *                           is the one of the logged in user)
 * @uses get_option() To get the displayed user's new email id option
 * @uses wpdb::prepare() To sanitize our sql query
 * @uses wpdb::get_var() To execute our query and get back the variable
 * @uses wpdb::query() To execute our query
 * @uses wp_update_user() To update the user
 * @uses delete_option() To delete the displayed user's email id option
 * @uses bbp_get_user_profile_edit_url() To get the edit profile url
 * @uses wp_redirect() To redirect to the url
 * @uses check_admin_referer() To verify the nonce and check the referer
 * @uses current_user_can() To check if the current user can edit the user
 * @uses do_action() Calls 'personal_options_update' or
 *                   'edit_user_options_update' (based on if it's the user home)
 *                   with the displayed user id
 * @uses edit_user() To edit the user based on the post data
 * @uses get_userdata() To get the user data
 * @uses is_email() To check if the string is an email id or not
 * @uses wpdb::get_blog_prefix() To get the blog prefix
 * @uses is_network_admin() To check if the user is the network admin
 * @uses is_super_admin() To check if the user is super admin
 * @uses revoke_super_admin() To revoke super admin priviledges
 * @uses grant_super_admin() To grant super admin priviledges
 * @uses is_wp_error() To check if the value retrieved is a {@link WP_Error}
 */
function bbp_edit_user_handler() {

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
			if ( $user->user_login && isset( $_POST['email'] ) && is_email( $_POST['email'] ) && $wpdb->get_var( $wpdb->prepare( "SELECT user_login FROM {$wpdb->signups} WHERE user_login = %s", $user->user_login ) ) )
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

/**
 * Load bbPress custom templates
 *
 * Loads custom templates for bbPress user profile, user edit, topic edit and
 * reply edit pages.
 *
 * @since bbPress (r2753)
 *
 * @uses bbp_is_user_profile_page() To check if it's a profile page
 * @uses bbp_is_user_profile_edit() To check if it's a profile edit page
 * @uses bbp_is_topic_edit() To check if it's a topic edit page
 * @uses bbp_is_reply_edit() To check if it's a reply edit page
 * @uses apply_filters() Calls 'bbp_custom_template' with the template array
 * @uses bbp_load_template() To load the template
 */
function bbp_custom_template() {
	global $bbp;

	$template = false;

	// Viewing a profile
	if ( bbp_is_user_profile_page() ) {
		$template = array( 'user.php', 'author.php', 'index.php' );

	// Editing a profile
	} elseif ( bbp_is_user_profile_edit() ) {
		$template = array( 'user-edit.php', 'user.php', 'author.php', 'index.php' );

	// Editing a topic
	} elseif ( bbp_is_topic_edit() ) {
		$template = array( 'page-bbp_edit.php', 'single-' . $bbp->topic_id, 'single.php', 'index.php' );

		if ( !empty( $_GET['action'] ) && in_array( $_GET['action'], array( 'merge', 'split' ) ) )
			array_unshift( $template, 'page-bbp_split-merge.php' );

	// Editing a reply
	} elseif ( bbp_is_reply_edit() ) {
		$template = array( 'page-bbp_edit.php', 'single-' . $bbp->reply_id, 'single.php', 'index.php' );
	}

	if ( !$template = apply_filters( 'bbp_custom_template', $template ) )
		return false;

	// Try to load a template file
	bbp_load_template( $template );
}

/**
 * Add checks for user page, user edit, topic edit and reply edit pages.
 *
 * If it's a user page, WP_Query::bbp_is_user_profile_page is set to true.
 * If it's a user edit page, WP_Query::bbp_is_user_profile_edit is set to true
 * and the the 'wp-admin/includes/user.php' file is included.
 * In addition, on user/user edit pages, WP_Query::home is set to false & query
 * vars 'bbp_user_id' with the displayed user id and 'author_name' with the
 * displayed user's nicename are added.
 *
 * If it's a topic edit, WP_Query::bbp_is_topic_edit is set to true and
 * similarly, if it's a reply edit, WP_Query::bbp_is_reply_edit is set to true.
 *
 * @since bbPress (r2688)
 *
 * @uses get_query_var() To get {@link WP_Query} query var
 * @uses WP_User to get the user data
 * @uses WP_Query::set_404() To set a 404 status
 * @uses is_multisite() To check if it's a multisite
 * @uses current_user_can() To check if the current user can edit the user
 * @uses apply_filters() Calls 'enable_edit_any_user_configuration' with true
 * @uses wp_die() To die
 */
function bbp_pre_get_posts( $wp_query ) {
	global $bbp, $wp_version;

	$bbp_user = get_query_var( 'bbp_user' );
	$is_edit  = get_query_var( 'edit'     );

	if ( !empty( $bbp_user ) ) {

		// It is a user page (most probably), we'll also check if it is user edit

		// Create new user
		$user = new WP_User( $bbp_user );

		// Stop if no user
		if ( !isset( $user ) || empty( $user ) || empty( $user->ID ) ) {
			$wp_query->set_404();
			return;
		}

		// Confirmed existence of the bbPress user

		// Define new query variable
		if ( !empty( $is_edit ) ) {
			// Only allow super admins on multisite to edit every user.
			if ( ( is_multisite() && !current_user_can( 'manage_network_users' ) && $user_id != $current_user->ID && !apply_filters( 'enable_edit_any_user_configuration', true ) ) || !current_user_can( 'edit_user', $user->ID ) )
				wp_die( __( 'You do not have the permission to edit this user.', 'bbpress' ) );

			$wp_query->bbp_is_user_profile_edit = true;

			// Load the required user editing functions
//			if ( version_compare( $wp_version, '3.1', '<=' ) ) // registration.php is not required in wp 3.1+
//				include_once( ABSPATH . 'wp-includes/registration.php' );
			require_once( ABSPATH . 'wp-admin/includes/user.php' );

		} else {
			$wp_query->bbp_is_user_profile_page = true;
		}

		// Set query variables
		$wp_query->is_home                   = false;                   // Correct is_home variable
		$wp_query->query_vars['bbp_user_id'] = $user->ID;               // Set bbp_user_id for future reference
		$wp_query->query_vars['author_name'] = $user->user_nicename;    // Set author_name as current user's nicename to get correct posts

		// Set the displayed user global to this user
		$bbp->displayed_user = $user;
	} elseif ( !empty( $is_edit ) ) {

		// It is a topic edit page
		if ( get_query_var( 'post_type' ) == $bbp->topic_id )
			$wp_query->bbp_is_topic_edit = true;

		// It is a reply edit page
		elseif ( get_query_var( 'post_type' ) == $bbp->reply_id )
			$wp_query->bbp_is_reply_edit = true;
	}
}

/**
 * Custom page title for bbPress User Profile Pages
 *
 * @since bbPress (r2688)
 *
 * @param string $title Optional. The title (not used).
 * @param string $sep Optional, default is '&raquo;'. How to separate the various items within the page title.
 * @param string $seplocation Optional. Direction to display title, 'right'.
 * @uses bbp_is_user_profile_page() To check if it's a user profile page
 * @uses bbp_is_user_profile_edit() To check if it's a user profile edit page
 * @uses get_query_var() To get the user id
 * @uses get_userdata() To get the user data
 * @uses apply_filters() Calls 'bbp_profile_page_wp_raw_title' with the user's
 *                        display name, separator and separator location
 * @uses apply_filters() Calls 'bbp_profile_page_wp_title' with the title,
 *                        separator and separator location
 * @return string The tite
 */
function bbp_profile_page_title( $title = '', $sep = '&raquo;', $seplocation = '' ) {
	if ( !bbp_is_user_profile_page() && !bbp_is_user_profile_edit() )
		return;

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
 * Load custom template
 *
 * @param string|array $files
 * @uses locate_template() To locate and include the template
 * @return bool False on failure (nothing on success)
 */
function bbp_load_template( $files ) {
	if ( empty( $files ) )
		return;

	// Force array
	if ( is_string( $files ) )
		$files = (array) $files;

	// Exit if file is found
	if ( locate_template( $files, true ) )
		exit();

	return;
}

/**
 * Return sticky topics of a forum
 *
 * @since bbPress (r2592)
 *
 * @param int $forum_id Optional. If not passed, super stickies are returned.
 * @uses bbp_get_super_stickies() To get the super stickies
 * @uses get_post_meta() To get the forum stickies
 * @uses apply_filters() Calls 'bbp_get_stickies' with the stickies and forum id
 * @return array IDs of sticky topics of a forum or super stickies
 */
function bbp_get_stickies( $forum_id = 0 ) {
	$stickies = empty( $forum_id ) ? bbp_get_super_stickies() : get_post_meta( $forum_id, '_bbp_sticky_topics', true );
	$stickies = ( empty( $stickies ) || !is_array( $stickies ) ) ? array() : $stickies;

	return apply_filters( 'bbp_get_stickies', $stickies, (int) $forum_id );
}

/**
 * Return topics stuck to front page of the forums
 *
 * @since bbPress (r2592)
 *
 * @uses get_option() To get super sticky topics
 * @uses apply_filters() Calls 'bbp_get_super_stickies' with the stickies
 * @return array IDs of super sticky topics
 */
function bbp_get_super_stickies() {
	$stickies = get_option( '_bbp_super_sticky_topics', array() );
	$stickies = ( empty( $stickies ) || !is_array( $stickies ) ) ? array() : $stickies;

	return apply_filters( 'bbp_get_super_stickies', $stickies );
}

/**
 * Remove the canonical redirect to allow pretty pagination
 *
 * @since bbPress (r2628)
 *
 * @param string $redirect_url Redirect url
 * @uses WP_Rewrite::using_permalinks() To check if the blog is using permalinks
 * @uses bbp_is_topic() To check if it's a topic page
 * @uses bbp_get_paged() To get the current page number
 * @uses bbp_is_forum() To check if it's a forum page
 * @return bool|string False if it's a topic/forum and their first page,
 *                      otherwise the redirect url
 */
function bbp_redirect_canonical( $redirect_url ) {
	global $wp_rewrite;

	if ( $wp_rewrite->using_permalinks() ) {
		if ( bbp_is_topic() && 1 < bbp_get_paged() )
			$redirect_url = false;
		elseif ( bbp_is_forum() && 1 < bbp_get_paged() )
			$redirect_url = false;
	}

	return $redirect_url;
}

/**
 * Assist pagination by returning correct page number
 *
 * @since bbPress (r2628)
 *
 * @uses get_query_var() To get the 'paged' value
 * @return int Current page number
 */
function bbp_get_paged() {
	if ( $paged = get_query_var( 'paged' ) )
		return (int) $paged;
	else
		return 1;
}

/** Topics Actions ************************************************************/

/**
 * Handles the front end opening/closing, spamming/unspamming,
 * sticking/unsticking and trashing/untrashing/deleting of topics
 *
 * @since bbPress (r2727)
 *
 * @uses get_post() To get the topic
 * @uses current_user_can() To check if the user is capable of editing or
 *                           deleting the topic
 * @uses check_ajax_referer() To verify the nonce and check the referer
 * @uses bbp_is_topic_open() To check if the topic is open
 * @uses bbp_close_topic() To close the topic
 * @uses bbp_open_topic() To open the topic
 * @uses bbp_is_topic_sticky() To check if the topic is a sticky
 * @uses bbp_unstick_topic() To unstick the topic
 * @uses bbp_stick_topic() To stick the topic
 * @uses bbp_is_topic_spam() To check if the topic is marked as spam
 * @uses bbp_spam_topic() To make the topic as spam
 * @uses bbp_unspam_topic() To unmark the topic as spam
 * @uses wp_trash_post() To trash the topic
 * @uses wp_untrash_post() To untrash the topic
 * @uses wp_delete_post() To delete the topic
 * @uses do_action() Calls 'bbp_toggle_topic_handler' with success, post data
 *                    and action
 * @uses bbp_get_topic_permalink() To get the topic link
 * @uses wp_redirect() To redirect to the topic
 * @uses bbPress::errors:add() To log the error messages
 */
function bbp_toggle_topic_handler() {

	// Only proceed if GET is a topic toggle action
	if ( 'GET' == $_SERVER['REQUEST_METHOD'] && !empty( $_GET['action'] ) && in_array( $_GET['action'], array( 'bbp_toggle_topic_close', 'bbp_toggle_topic_stick', 'bbp_toggle_topic_spam', 'bbp_toggle_topic_trash' ) ) && !empty( $_GET['topic_id'] ) ) {
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

			case 'bbp_toggle_topic_stick' :
				check_ajax_referer( 'stick-topic_' . $topic_id );

				$is_sticky = bbp_is_topic_sticky( $topic_id );
				$is_super  = ( empty( $is_sticky ) && !empty( $_GET['super'] ) && 1 == (int) $_GET['super'] ) ? true : false;
				$success   = $is_sticky ? bbp_unstick_topic( $topic_id ) : bbp_stick_topic( $topic_id, $is_super );
				$failure   = $is_sticky ? __( '<strong>ERROR</strong>: There was a problem unsticking the topic!', 'bbpress' ) : __( '<strong>ERROR</strong>: There was a problem sticking the topic!', 'bbpress' );

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

/** Reply Actions *************************************************************/

/**
 * Handles the front end spamming/unspamming and trashing/untrashing/deleting of
 * replies
 *
 * @since bbPress (r2740)
 *
 * @uses get_post() To get the reply
 * @uses current_user_can() To check if the user is capable of editing or
 *                           deleting the reply
 * @uses check_ajax_referer() To verify the nonce and check the referer
 * @uses bbp_is_reply_spam() To check if the reply is marked as spam
 * @uses bbp_spam_reply() To make the reply as spam
 * @uses bbp_unspam_reply() To unmark the reply as spam
 * @uses wp_trash_post() To trash the reply
 * @uses wp_untrash_post() To untrash the reply
 * @uses wp_delete_post() To delete the reply
 * @uses do_action() Calls 'bbp_toggle_reply_handler' with success, post data
 *                    and action
 * @uses bbp_get_reply_url() To get the reply url
 * @uses add_query_arg() To add custom args to the reply url
 * @uses wp_redirect() To redirect to the reply
 * @uses bbPress::errors:add() To log the error messages
 */
function bbp_toggle_reply_handler() {

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

/** Favorites *****************************************************************/

/**
 * Handles the front end adding and removing of favorite topics
 *
 * @uses bbp_get_user_id() To get the user id
 * @uses current_user_can() To check if the current user can edit the user
 * @uses bbPress:errors:add() To log the error messages
 * @uses bbp_is_user_favorite() To check if the topic is in user's favorites
 * @uses bbp_remove_user_favorite() To remove the user favorite
 * @uses bbp_add_user_favorite() To add the user favorite
 * @uses do_action() Calls 'bbp_favorites_handler' with success, user id, topic
 *                    id and action
 * @uses bbp_is_favorites() To check if it's the favorites page
 * @uses bbp_get_favorites_link() To get the favorites page link
 * @uses bbp_get_topic_permalink() To get the topic permalink
 * @uses wp_redirect() To redirect to the url
 */
function bbp_favorites_handler() {

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

/**
 * Checks if favorites feature is enabled.
 *
 * @since bbPress (r2658)
 *
 * @uses get_option() To get the favorites option
 * @return bool Is favorites enabled or not
 */
function bbp_is_favorites_active() {
	return (bool) get_option( '_bbp_enable_favorites' );
}

/**
 * Remove a deleted topic from all users' favorites
 *
 * @since bbPress (r2652)
 *
 * @param int $topic_id Topic ID to remove
 * @uses bbp_get_topic_favoriters() To get the topic's favoriters
 * @uses bbp_remove_user_favorite() To remove the topic from user's favorites
 */
function bbp_remove_topic_from_all_favorites( $topic_id = 0 ) {
	if ( empty( $topic_id ) )
		return;

	$users = (array) bbp_get_topic_favoriters( $topic_id );

	if ( !empty( $users ) ) {
		foreach ( $users as $user ) {
			bbp_remove_user_favorite( $user, $topic_id );
		}
	}
}

/** Subscriptions *************************************************************/

/**
 * Handles the front end subscribing and unsubscribing topics
 *
 * @uses bbp_is_subscriptions_active() To check if the subscriptions are active
 * @uses bbp_get_user_id() To get the user id
 * @uses current_user_can() To check if the current user can edit the user
 * @uses bbPress:errors:add() To log the error messages
 * @uses bbp_is_user_subscribed() To check if the topic is in user's
 *                                 subscriptions
 * @uses bbp_remove_user_subscription() To remove the user subscription
 * @uses bbp_add_user_subscription() To add the user subscription
 * @uses do_action() Calls 'bbp_subscriptions_handler' with success, user id,
 *                    topic id and action
 * @uses bbp_is_subscription() To check if it's the subscription page
 * @uses bbp_get_subscription_link() To get the subscription page link
 * @uses bbp_get_topic_permalink() To get the topic permalink
 * @uses wp_redirect() To redirect to the url
 */
function bbp_subscriptions_handler() {
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

/**
 * Remove a deleted topic from all users' subscriptions
 *
 * @since bbPress (r2652)
 *
 * @param int $topic_id Topic ID to remove
 * @uses bbp_is_subscriptions_active() To check if the subscriptions are active
 * @uses bbp_get_topic_subscribers() To get the topic subscribers
 * @uses bbp_remove_user_subscription() To remove the user subscription
 */
function bbp_remove_topic_from_all_subscriptions( $topic_id = 0 ) {
	if ( empty( $topic_id ) )
		return;

	if ( !bbp_is_subscriptions_active() )
		return;

	$users = (array) bbp_get_topic_subscribers( $topic_id );

	if ( !empty( $users ) ) {
		foreach ( $users as $user ) {
			bbp_remove_user_subscription( $user, $topic_id );
		}
	}
}

/**
 * Checks if subscription feature is enabled.
 *
 * @since bbPress (r2658)
 *
 * @uses get_option() To get the subscriptions option
 * @return bool Is subscription enabled or not
 */
function bbp_is_subscriptions_active() {
	return (bool) get_option( '_bbp_enable_subscriptions' );
}

/**
 * Sends notification emails for new posts
 *
 * Gets new post's ID and check if there are subscribed users to that topic, and
 * if there are, send notifications
 *
 * @since bbPress (r2668)
 *
 * @todo When Akismet is made to work with bbPress posts, add a check if the
 * post is spam or not, to avoid sending out mails for spam posts
 *
 * @param int $reply_id ID of the newly made reply
 * @uses bbp_is_subscriptions_active() To check if the subscriptions are active
 * @uses get_post() To get the topic and reply
 * @uses get_the_author_meta() To get the author's display name
 * @uses do_action() Calls 'bbp_pre_notify_subscribers' with the reply id and
 *                    topic id
 * @uses bbp_get_topic_subscribers() To get the topic subscribers
 * @uses apply_filters() Calls 'bbp_subscription_mail_message' with the
 *                        message, reply id, topic id and user id
 * @uses get_userdata() To get the user data
 * @uses wp_mail() To send the mail
 * @uses do_action() Calls 'bbp_post_notify_subscribers' with the reply id
 *                    and topic id
 * @return bool True on success, false on failure
 */
function bbp_notify_subscribers( $reply_id = 0 ) {
	global $bbp, $wpdb;

	if ( !bbp_is_subscriptions_active() )
		return false;

	if ( empty( $reply_id ) )
		return false;

	if ( !$reply = get_post( $reply_id ) )
		return false;

	if ( $reply->post_type != $bbp->reply_id || empty( $reply->post_parent ) )
		return false;

	if ( !$topic = get_post( $reply->post_parent ) )
		return false;

	if ( !$topic->post_type != $bbp->topic_id )
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

/**
 * Fix post author id on post save
 *
 * When a logged in user changes the status of an anonymous reply or topic, or
 * edits it, the post_author field is set to the logged in user's id. This
 * function fixes that.
 *
 * @since bbPress (r2734)
 *
 * @param array $data Post data
 * @param array $postarr Original post array (includes post id)
 * @uses bbp_is_topic_anonymous() To check if the topic is by an anonymous user
 * @uses bbp_is_reply_anonymous() To check if the reply is by an anonymous user
 * @return array Data
 */
function bbp_fix_post_author( $data = array(), $postarr = array() ) {
	global $bbp;

	// Post is not being updated or the post_author is already 0, return
	if ( empty( $postarr['ID'] ) || empty( $data['post_author'] ) )
		return $data;

	// Post is not a topic or reply, return
	if ( !in_array( $data['post_type'], array( $bbp->topic_id, $bbp->reply_id ) ) )
		return $data;

	// Is the post by an anonymous user?
	if ( ( $bbp->topic_id == $data['post_type'] && !bbp_is_topic_anonymous( $postarr['ID'] ) ) ||
	     ( $bbp->reply_id == $data['post_type'] && !bbp_is_reply_anonymous( $postarr['ID'] ) ) )
		return $data;

	// The post is being updated. It is a topic or a reply and is written by an anonymous user.
	// Set the post_author back to 0
	$data['post_author'] = 0;

	return $data;
}

?>
