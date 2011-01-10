<?php

/**
 * bbPress Reply Functions
 *
 * @package bbPress
 * @subpackage Functions
 */

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
 * @uses remove_filter() To remove 'wp_filter_kses' filters if needed
 * @uses esc_attr() For sanitization
 * @uses bbp_check_for_flood() To check for flooding
 * @uses bbp_check_for_duplicate() To check for duplicates
 * @uses apply_filters() Calls 'bbp_new_reply_pre_title' with the title
 * @uses apply_filters() Calls 'bbp_new_reply_pre_content' with the content
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

		// Handle Topic ID to append reply to
		if ( empty( $_POST['bbp_topic_id'] ) || !$topic_id = $_POST['bbp_topic_id'] )
			$bbp->errors->add( 'bbp_reply_topic_id', __( '<strong>ERROR</strong>: Topic ID is missing.', 'bbpress' ) );

		// Handle Forum ID to adjust counts of
		if ( empty( $_POST['bbp_forum_id'] ) || !$forum_id = $_POST['bbp_forum_id'] )
			$bbp->errors->add( 'bbp_reply_forum_id', __( '<strong>ERROR</strong>: Forum ID is missing.', 'bbpress' ) );

		// Remove wp_filter_kses filters from title and content for capable users and if the nonce is verified
		if ( current_user_can( 'unfiltered_html' ) && !empty( $_POST['_bbp_unfiltered_html_reply'] ) && wp_create_nonce( 'bbp-unfiltered-html-reply_' . $topic_id ) == $_POST['_bbp_unfiltered_html_reply'] ) {
			remove_filter( 'bbp_new_reply_pre_title',   'wp_filter_kses' );
			remove_filter( 'bbp_new_reply_pre_content', 'wp_filter_kses' );
		}

		// Handle Title (optional for replies)
		if ( !empty( $_POST['bbp_reply_title'] ) )
			$reply_title = esc_attr( strip_tags( $_POST['bbp_reply_title'] ) );

		$reply_title = apply_filters( 'bbp_new_reply_pre_title', $reply_title );

		// Handle Content
		if ( empty( $_POST['bbp_reply_content'] ) || !$reply_content = $_POST['bbp_reply_content'] ) {
			$bbp->errors->add( 'bbp_reply_content', __( '<strong>ERROR</strong>: Your reply cannot be empty.', 'bbpress' ) );
			$reply_content = '';
		}

		$reply_content = apply_filters( 'bbp_new_reply_pre_content', $reply_content );

		// Check for flood
		if ( !bbp_check_for_flood( $anonymous_data, $reply_author ) )
			$bbp->errors->add( 'bbp_reply_flood', __( '<strong>ERROR</strong>: Slow down; you move too fast.', 'bbpress' ) );

		// Check for duplicate
		if ( !bbp_check_for_duplicate( array( 'post_type' => $bbp->reply_id, 'post_author' => $reply_author, 'post_content' => $reply_content, 'post_parent' => $topic_id, 'anonymous_data' => $anonymous_data ) ) )
			$bbp->errors->add( 'bbp_reply_duplicate', __( '<strong>ERROR</strong>: Duplicate reply detected; it looks as though you&#8217;ve already said that!', 'bbpress' ) );

		// Handle Tags
		if ( !empty( $_POST['bbp_topic_tags'] ) && $tags = esc_attr( strip_tags( $_POST['bbp_topic_tags'] ) ) ) {
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
 * @uses bbp_get_reply() To get the reply
 * @uses check_admin_referer() To verify the nonce and check the referer
 * @uses bbp_is_reply_anonymous() To check if the reply was by an anonymous user
 * @uses current_user_can() To check if the current user can edit that reply
 * @uses bbp_filter_anonymous_post_data() To filter anonymous data
 * @uses is_wp_error() To check if the value retrieved is a {@link WP_Error}
 * @uses remove_filter() To remove 'wp_filter_kses' filters if needed
 * @uses esc_attr() For sanitization
 * @uses apply_filters() Calls 'bbp_edit_reply_pre_title' with the title and
 *                       reply id
 * @uses apply_filters() Calls 'bbp_edit_reply_pre_content' with the content
 *                        reply id
 * @uses wp_set_post_terms() To set the topic tags
 * @uses bbPress::errors::get_error_codes() To get the {@link WP_Error} errors
 * @uses wp_save_post_revision() To save a reply revision
 * @uses bbp_update_topic_revision_log() To update the reply revision log
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
		} elseif ( !$reply = bbp_get_reply( $reply_id ) ) {
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

		// Remove wp_filter_kses filters from title and content for capable users and if the nonce is verified
		if ( current_user_can( 'unfiltered_html' ) && !empty( $_POST['_bbp_unfiltered_html_reply'] ) && wp_create_nonce( 'bbp-unfiltered-html-reply_' . $reply_id ) == $_POST['_bbp_unfiltered_html_reply'] ) {
			remove_filter( 'bbp_edit_reply_pre_title',   'wp_filter_kses' );
			remove_filter( 'bbp_edit_reply_pre_content', 'wp_filter_kses' );
		}

		// Handle Title (optional for replies)
		$reply_title = !empty( $_POST['bbp_reply_title'] ) ? esc_attr( strip_tags( $_POST['bbp_reply_title'] ) ) : $reply_title = $reply->post_title;
		$reply_title = apply_filters( 'bbp_edit_reply_pre_title', $reply_title, $reply_id );

		// Handle Content
		if ( empty( $_POST['bbp_reply_content'] ) || !$reply_content = $_POST['bbp_reply_content'] )
			$bbp->errors->add( 'bbp_edit_reply_content', __( '<strong>ERROR</strong>: Your reply cannot be empty.', 'bbpress' ) );

		$reply_content = apply_filters( 'bbp_edit_reply_pre_content', $reply_content, $reply_id );

		// Handle insertion into posts table
		if ( !is_wp_error( $bbp->errors ) || !$bbp->errors->get_error_codes() ) {

			$reply_edit_reason = !empty( $_POST['bbp_reply_edit_reason'] ) ? esc_attr( strip_tags( $_POST['bbp_reply_edit_reason'] ) ) : '';

			if ( !empty( $_POST['bbp_log_reply_edit'] ) && 1 == $_POST['bbp_log_reply_edit'] && $revision_id = wp_save_post_revision( $reply_id ) )
				bbp_update_reply_revision_log( array( 'reply_id' => $reply_id, 'revision_id' => $revision_id, 'author_id' => bbp_get_current_user_id(), 'reason' => $reply_edit_reason ) );

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

/** Reply Actions *************************************************************/

/**
 * Handles the front end spamming/unspamming and trashing/untrashing/deleting of
 * replies
 *
 * @since bbPress (r2740)
 *
 * @uses bbp_get_reply() To get the reply
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
		if ( !$reply = bbp_get_reply( $reply_id ) )
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

/** Reply Actions *************************************************************/

/**
 * Marks a reply as spam
 *
 * @since bbPress (r2740)
 *
 * @param int $reply_id Reply id
 * @uses wp_get_single_post() To get the reply
 * @uses do_action() Calls 'bbp_spam_reply' with the reply id before marking
 *                    the reply as spam
 * @uses add_post_meta() To add the previous status to a meta
 * @uses wp_insert_post() To insert the updated post
 * @uses do_action() Calls 'bbp_spammed_reply' with the reply id after marking
 *                    the reply as spam
 * @return mixed False or {@link WP_Error} on failure, reply id on success
 */
function bbp_spam_reply( $reply_id = 0 ) {
	global $bbp;

	if ( !$reply = wp_get_single_post( $reply_id, ARRAY_A ) )
		return $reply;

	if ( $reply['post_status'] == $bbp->spam_status_id )
		return false;

	do_action( 'bbp_spam_reply', $reply_id );

	add_post_meta( $reply_id, '_bbp_spam_meta_status', $reply['post_status'] );

	$reply['post_status'] = $bbp->spam_status_id;
	$reply_id = wp_insert_post( $reply );

	do_action( 'bbp_spammed_reply', $reply_id );

	return $reply_id;
}

/**
 * Unspams a reply
 *
 * @since bbPress (r2740)
 *
 * @param int $reply_id Reply id
 * @uses wp_get_single_post() To get the reply
 * @uses do_action() Calls 'bbp_unspam_reply' with the reply id before unmarking
 *                    the reply as spam
 * @uses get_post_meta() To get the previous status meta
 * @uses delete_post_meta() To delete the previous status meta
 * @uses wp_insert_post() To insert the updated post
 * @uses do_action() Calls 'bbp_unspammed_reply' with the reply id after
 *                    unmarking the reply as spam
 * @return mixed False or {@link WP_Error} on failure, reply id on success
 */
function bbp_unspam_reply( $reply_id = 0 ) {
	global $bbp;

	if ( !$reply = wp_get_single_post( $reply_id, ARRAY_A ) )
		return $reply;

	if ( $reply['post_status'] != $bbp->spam_status_id )
		return false;

	do_action( 'bbp_unspam_reply', $reply_id );

	$reply_status         = get_post_meta( $reply_id, '_bbp_spam_meta_status', true );
	$reply['post_status'] = $reply_status;

	delete_post_meta( $reply_id, '_bbp_spam_meta_status' );

	$reply_id = wp_insert_post( $reply );

	do_action( 'bbp_unspammed_reply', $reply_id );

	return $reply_id;
}

?>
