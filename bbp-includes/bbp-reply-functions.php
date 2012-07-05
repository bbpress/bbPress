<?php

/**
 * bbPress Reply Functions
 *
 * @package bbPress
 * @subpackage Functions
 */

// Exit if accessed directly
if ( !defined( 'ABSPATH' ) ) exit;

/** Insert ********************************************************************/

/**
 * A wrapper for wp_insert_post() that also includes the necessary meta values
 * for the reply to function properly.
 *
 * @since bbPress (r3349)
 *
 * @uses bbp_parse_args()
 * @uses bbp_get_reply_post_type()
 * @uses wp_insert_post()
 * @uses update_post_meta()
 *
 * @param array $reply_data Forum post data
 * @param arrap $reply_meta Forum meta data
 */
function bbp_insert_reply( $reply_data = array(), $reply_meta = array() ) {

	// Forum
	$default_reply = array(
		'post_parent'    => 0, // topic ID
		'post_status'    => bbp_get_public_status_id(),
		'post_type'      => bbp_get_reply_post_type(),
		'post_author'    => bbp_get_current_user_id(),
		'post_password'  => '',
		'post_content'   => '',
		'post_title'     => '',
		'menu_order'     => 0,
		'comment_status' => 'closed'
	);
	$reply_data = bbp_parse_args( $reply_data, $default_reply, 'insert_reply' );

	// Insert reply
	$reply_id   = wp_insert_post( $reply_data );

	// Bail if no reply was added
	if ( empty( $reply_id ) )
		return false;

	// Forum meta
	$default_meta = array(
		'author_ip' => bbp_current_author_ip(),
		'forum_id'  => 0,
		'topic_id'  => 0,
	);
	$reply_meta = bbp_parse_args( $reply_meta, $default_meta, 'insert_reply_meta' );

	// Insert reply meta
	foreach ( $reply_meta as $meta_key => $meta_value )
		update_post_meta( $reply_id, '_bbp_' . $meta_key, $meta_value );

	// Update the topic
	$topic_id = bbp_get_reply_topic_id( $reply_id );
	if ( !empty( $topic_id ) )
		bbp_update_topic( $topic_id );

	// Return new reply ID
	return $reply_id;
}

/** Post Form Handlers ********************************************************/

/**
 * Handles the front end reply submission
 *
 * @since bbPress (r2574)
 *
 * @uses bbp_add_error() To add an error message
 * @uses bbp_verify_nonce_request() To verify the nonce and check the request
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
 * @uses bbp_get_reply_post_type() To get the reply post type
 * @uses wp_set_post_terms() To set the topic tags
 * @uses wp_insert_post() To insert the reply
 * @uses do_action() Calls 'bbp_new_reply' with the reply id, topic id, forum
 *                    id, anonymous data and reply author
 * @uses bbp_get_reply_url() To get the paginated url to the reply
 * @uses wp_safe_redirect() To redirect to the reply url
 * @uses bbPress::errors::get_error_message() To get the {@link WP_Error} error
 *                                              message
 */
function bbp_new_reply_handler() {

	// Bail if not a POST action
	if ( 'POST' !== strtoupper( $_SERVER['REQUEST_METHOD'] ) )
		return;

	// Bail if action is not bbp-new-reply
	if ( empty( $_POST['action'] ) || ( 'bbp-new-reply' !== $_POST['action'] ) )
		return;

	// Nonce check
	if ( ! bbp_verify_nonce_request( 'bbp-new-reply' ) ) {
		bbp_add_error( 'bbp_rew_reply_nonce', __( '<strong>ERROR</strong>: Are you sure you wanted to do that?', 'bbpress' ) );
		return;
	}

	// Define local variable(s)
	$topic_id = $forum_id = $reply_author = $anonymous_data = 0;
	$reply_title = $reply_content = $terms = '';

	/** Reply Author **********************************************************/

	// User is anonymous
	if ( bbp_is_anonymous() ) {

		// Filter anonymous data
		$anonymous_data = bbp_filter_anonymous_post_data();

		// Anonymous data checks out, so set cookies, etc...
		if ( !empty( $anonymous_data ) && is_array( $anonymous_data ) ) {
			bbp_set_current_anonymous_user_data( $anonymous_data );
		}

	// User is logged in
	} else {

		// User cannot create replies
		if ( !current_user_can( 'publish_replies' ) ) {
			bbp_add_error( 'bbp_reply_permissions', __( '<strong>ERROR</strong>: You do not have permission to reply.', 'bbpress' ) );
		}

		// Reply author is current user
		$reply_author = bbp_get_current_user_id();

	}

	/** Topic ID **************************************************************/

	// Handle Topic ID to append reply to
	if ( isset( $_POST['bbp_topic_id'] ) ) {
		$topic_id = (int) $_POST['bbp_topic_id'];
	} else {
		bbp_add_error( 'bbp_reply_topic_id', __( '<strong>ERROR</strong>: Topic ID is missing.', 'bbpress' ) );
	}

	/** Forum ID **************************************************************/

	// Handle Forum ID to adjust counts of
	if ( isset( $_POST['bbp_forum_id'] ) ) {
		$forum_id = (int) $_POST['bbp_forum_id'];
	} elseif ( !empty( $topic_id ) ) {
		$forum_id = bbp_get_topic_forum_id( $topic_id );
	} else {
		bbp_add_error( 'bbp_reply_forum_id', __( '<strong>ERROR</strong>: Forum ID is missing.', 'bbpress' ) );
	}

	/** Unfiltered HTML *******************************************************/

	// Remove wp_filter_kses filters from title and content for capable users and if the nonce is verified
	if ( current_user_can( 'unfiltered_html' ) && !empty( $_POST['_bbp_unfiltered_html_reply'] ) && wp_create_nonce( 'bbp-unfiltered-html-reply_' . $topic_id ) == $_POST['_bbp_unfiltered_html_reply'] ) {
		remove_filter( 'bbp_new_reply_pre_title',   'wp_filter_kses' );
		remove_filter( 'bbp_new_reply_pre_content', 'wp_filter_kses' );
	}

	/** Reply Title ***********************************************************/

	if ( !empty( $_POST['bbp_reply_title'] ) )
		$reply_title = esc_attr( strip_tags( $_POST['bbp_reply_title'] ) );

	// Filter and sanitize
	$reply_title = apply_filters( 'bbp_new_reply_pre_title', $reply_title );

	// No reply title
	if ( empty( $reply_title ) )
		bbp_add_error( 'bbp_reply_title', __( '<strong>ERROR</strong>: Your reply needs a title.', 'bbpress' ) );

	/** Reply Content *********************************************************/

	if ( !empty( $_POST['bbp_reply_content'] ) )
		$reply_content = $_POST['bbp_reply_content'];

	// Filter and sanitize
	$reply_content = apply_filters( 'bbp_new_reply_pre_content', $reply_content );

	// No reply content
	if ( empty( $reply_content ) )
		bbp_add_error( 'bbp_reply_content', __( '<strong>ERROR</strong>: Your reply cannot be empty.', 'bbpress' ) );

	/** Reply Flooding ********************************************************/

	if ( !bbp_check_for_flood( $anonymous_data, $reply_author ) )
		bbp_add_error( 'bbp_reply_flood', __( '<strong>ERROR</strong>: Slow down; you move too fast.', 'bbpress' ) );

	/** Reply Duplicate *******************************************************/

	if ( !bbp_check_for_duplicate( array( 'post_type' => bbp_get_reply_post_type(), 'post_author' => $reply_author, 'post_content' => $reply_content, 'post_parent' => $topic_id, 'anonymous_data' => $anonymous_data ) ) )
		bbp_add_error( 'bbp_reply_duplicate', __( '<strong>ERROR</strong>: Duplicate reply detected; it looks as though you&#8217;ve already said that!', 'bbpress' ) );

	/** Reply Blacklist *******************************************************/

	if ( !bbp_check_for_blacklist( $anonymous_data, $reply_author, $reply_title, $reply_content ) )
		bbp_add_error( 'bbp_reply_blacklist', __( '<strong>ERROR</strong>: Your reply cannot be created at this time.', 'bbpress' ) );

	/** Reply Moderation ******************************************************/

	$post_status = bbp_get_public_status_id();
	if ( !bbp_check_for_moderation( $anonymous_data, $reply_author, $reply_title, $reply_content ) )
		$post_status = bbp_get_pending_status_id();

	/** Topic Tags ************************************************************/

	if ( !empty( $_POST['bbp_topic_tags'] ) )
		$terms = esc_attr( strip_tags( $_POST['bbp_topic_tags'] ) );

	/** Additional Actions (Before Save) **************************************/

	do_action( 'bbp_new_reply_pre_extras', $topic_id, $forum_id );

	// Bail if errors
	if ( bbp_has_errors() )
		return;

	/** No Errors *************************************************************/

	// Add the content of the form to $reply_data as an array
	// Just in time manipulation of reply data before being created
	$reply_data = apply_filters( 'bbp_new_reply_pre_insert', array(
		'post_author'    => $reply_author,
		'post_title'     => $reply_title,
		'post_content'   => $reply_content,
		'post_parent'    => $topic_id,
		'post_status'    => $post_status,
		'post_type'      => bbp_get_reply_post_type(),
		'comment_status' => 'closed',
		'menu_order'     => (int) ( bbp_get_topic_reply_count( $topic_id ) + 1 )
	) );

	// Insert reply
	$reply_id = wp_insert_post( $reply_data );

	/** No Errors *************************************************************/

	// Check for missing reply_id or error
	if ( !empty( $reply_id ) && !is_wp_error( $reply_id ) ) {

		/** Topic Tags ********************************************************/

		// Just in time manipulation of reply terms before being edited
		$terms = apply_filters( 'bbp_new_reply_pre_set_terms', $terms, $topic_id, $reply_id );

		// Insert terms
		$terms = wp_set_post_terms( $topic_id, $terms, bbp_get_topic_tag_tax_id(), false );

		// Term error
		if ( is_wp_error( $terms ) ) {
			bbp_add_error( 'bbp_reply_tags', __( '<strong>ERROR</strong>: There was a problem adding the tags to the topic.', 'bbpress' ) );
		}

		/** Trash Check *******************************************************/

		// If this reply starts as trash, add it to pre_trashed_replies
		// for the topic, so it is properly restored.
		if ( bbp_is_topic_trash( $topic_id ) || ( $reply_data['post_status'] == bbp_get_trash_status_id() ) ) {

			// Trash the reply
			wp_trash_post( $reply_id );

			// Get pre_trashed_replies for topic
			$pre_trashed_replies = get_post_meta( $topic_id, '_bbp_pre_trashed_replies', true );

			// Add this reply to the end of the existing replies
			$pre_trashed_replies[] = $reply_id;

			// Update the pre_trashed_reply post meta
			update_post_meta( $topic_id, '_bbp_pre_trashed_replies', $pre_trashed_replies );
		}

		/** Spam Check ********************************************************/

		// If reply or topic are spam, officially spam this reply
		if ( bbp_is_topic_spam( $topic_id ) || ( $reply_data['post_status'] == bbp_get_spam_status_id() ) )
			add_post_meta( $reply_id, '_bbp_spam_meta_status', bbp_get_public_status_id() );

		/** Update counts, etc... *********************************************/

		do_action( 'bbp_new_reply', $reply_id, $topic_id, $forum_id, $anonymous_data, $reply_author );

		/** Additional Actions (After Save) ***********************************/

		do_action( 'bbp_new_reply_post_extras', $reply_id );

		/** Redirect **********************************************************/

		// Redirect to
		$redirect_to = !empty( $_REQUEST['redirect_to'] ) ? $_REQUEST['redirect_to'] : '';

		// Get the reply URL
		$reply_url = bbp_get_reply_url( $reply_id, $redirect_to );

		// Allow to be filtered
		$reply_url = apply_filters( 'bbp_new_reply_redirect_to', $reply_url, $redirect_to, $reply_id );

		/** Successful Save ***************************************************/

		// Redirect back to new reply
		wp_safe_redirect( $reply_url );

		// For good measure
		exit();

	/** Errors ****************************************************************/

	} else {
		$append_error = ( is_wp_error( $reply_id ) && $reply_id->get_error_message() ) ? $reply_id->get_error_message() . ' ' : '';
		bbp_add_error( 'bbp_reply_error', __( '<strong>ERROR</strong>: The following problem(s) have been found with your reply:' . $append_error . 'Please try again.', 'bbpress' ) );
	}
}

/**
 * Handles the front end edit reply submission
 *
 * @uses bbp_add_error() To add an error message
 * @uses bbp_get_reply() To get the reply
 * @uses bbp_verify_nonce_request() To verify the nonce and check the request
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
 * @uses bbp_has_errors() To get the {@link WP_Error} errors
 * @uses wp_save_post_revision() To save a reply revision
 * @uses bbp_update_topic_revision_log() To update the reply revision log
 * @uses wp_update_post() To update the reply
 * @uses bbp_get_reply_topic_id() To get the reply topic id
 * @uses bbp_get_topic_forum_id() To get the topic forum id
 * @uses do_action() Calls 'bbp_edit_reply' with the reply id, topic id, forum
 *                    id, anonymous data, reply author and bool true (for edit)
 * @uses bbp_get_reply_url() To get the paginated url to the reply
 * @uses wp_safe_redirect() To redirect to the reply url
 * @uses bbPress::errors::get_error_message() To get the {@link WP_Error} error
 *                                             message
 */
function bbp_edit_reply_handler() {

	// Bail if not a POST action
	if ( 'POST' !== strtoupper( $_SERVER['REQUEST_METHOD'] ) )
		return;

	// Bail if action is not bbp-edit-reply
	if ( empty( $_POST['action'] ) || ( 'bbp-edit-reply' !== $_POST['action'] ) )
		return;

	// Define local variable(s)
	$reply = $reply_id = $topic_id = $forum_id = $anonymous_data = 0;
	$reply_title = $reply_content = $reply_edit_reason = $terms = '';

	/** Reply *****************************************************************/

	// Reply id was not passed
	if ( empty( $_POST['bbp_reply_id'] ) ) {
		bbp_add_error( 'bbp_edit_reply_id', __( '<strong>ERROR</strong>: Reply ID not found.', 'bbpress' ) );
		return;

	// Reply id was passed
	} elseif ( is_numeric( $_POST['bbp_reply_id'] ) ) {
		$reply_id = (int) $_POST['bbp_reply_id'];
		$reply    = bbp_get_reply( $reply_id );
	}

	// Nonce check
	if ( ! bbp_verify_nonce_request( 'bbp-edit-reply_' . $reply_id ) ) {
		bbp_add_error( 'bbp_edit_reply_nonce', __( '<strong>ERROR</strong>: Are you sure you wanted to do that?', 'bbpress' ) );
		return;
	}

	// Reply does not exist
	if ( empty( $reply ) ) {
		bbp_add_error( 'bbp_edit_reply_not_found', __( '<strong>ERROR</strong>: The reply you want to edit was not found.', 'bbpress' ) );
		return;

	// Reply exists
	} else {

		// Check users ability to create new reply
		if ( !bbp_is_reply_anonymous( $reply_id ) ) {

			// User cannot edit this reply
			if ( !current_user_can( 'edit_reply', $reply_id ) ) {
				bbp_add_error( 'bbp_edit_reply_permissions', __( '<strong>ERROR</strong>: You do not have permission to edit that reply.', 'bbpress' ) );
				return;
			}

		// It is an anonymous post
		} else {

			// Filter anonymous data
			$anonymous_data = bbp_filter_anonymous_post_data();
		}
	}

	// Remove wp_filter_kses filters from title and content for capable users and if the nonce is verified
	if ( current_user_can( 'unfiltered_html' ) && !empty( $_POST['_bbp_unfiltered_html_reply'] ) && wp_create_nonce( 'bbp-unfiltered-html-reply_' . $reply_id ) == $_POST['_bbp_unfiltered_html_reply'] ) {
		remove_filter( 'bbp_edit_reply_pre_title',   'wp_filter_kses' );
		remove_filter( 'bbp_edit_reply_pre_content', 'wp_filter_kses' );
	}

	/** Reply Topic ***********************************************************/

	$topic_id = bbp_get_reply_topic_id( $reply_id );

	/** Topic Forum ***********************************************************/

	$forum_id = bbp_get_topic_forum_id( $topic_id );

	// Forum exists
	if ( !empty( $forum_id ) && ( $forum_id !== bbp_get_reply_forum_id( $reply_id ) ) ) {

		// Forum is a category
		if ( bbp_is_forum_category( $forum_id ) )
			bbp_add_error( 'bbp_edit_reply_forum_category', __( '<strong>ERROR</strong>: This forum is a category. No topics or replies can be created in it.', 'bbpress' ) );

		// Forum is closed and user cannot access
		if ( bbp_is_forum_closed( $forum_id ) && !current_user_can( 'edit_forum', $forum_id ) )
			bbp_add_error( 'bbp_edit_reply_forum_closed', __( '<strong>ERROR</strong>: This forum has been closed to new topics and replies.', 'bbpress' ) );

		// Forum is private and user cannot access
		if ( bbp_is_forum_private( $forum_id ) && !current_user_can( 'read_private_forums' ) )
			bbp_add_error( 'bbp_edit_reply_forum_private', __( '<strong>ERROR</strong>: This forum is private and you do not have the capability to read or create new replies in it.', 'bbpress' ) );

		// Forum is hidden and user cannot access
		if ( bbp_is_forum_hidden( $forum_id ) && !current_user_can( 'read_hidden_forums' ) )
			bbp_add_error( 'bbp_edit_reply_forum_hidden', __( '<strong>ERROR</strong>: This forum is hidden and you do not have the capability to read or create new replies in it.', 'bbpress' ) );
	}

	/** Reply Title ***********************************************************/

	if ( !empty( $_POST['bbp_reply_title'] ) )
		$reply_title = esc_attr( strip_tags( $_POST['bbp_reply_title'] ) );

	// Filter and sanitize
	$reply_title = apply_filters( 'bbp_edit_reply_pre_title', $reply_title, $reply_id );

	/** Reply Content *********************************************************/

	if ( !empty( $_POST['bbp_reply_content'] ) )
		$reply_content = $_POST['bbp_reply_content'];

	// Filter and sanitize
	$reply_content = apply_filters( 'bbp_edit_reply_pre_content', $reply_content, $reply_id );

	// No reply content
	if ( empty( $reply_content ) )
		bbp_add_error( 'bbp_edit_reply_content', __( '<strong>ERROR</strong>: Your reply cannot be empty.', 'bbpress' ) );

	/** Reply Blacklist *******************************************************/

	if ( !bbp_check_for_blacklist( $anonymous_data, bbp_get_reply_author_id( $reply_id ), $reply_title, $reply_content ) )
		bbp_add_error( 'bbp_reply_blacklist', __( '<strong>ERROR</strong>: Your reply cannot be edited at this time.', 'bbpress' ) );

	/** Reply Moderation ******************************************************/

	$post_status = bbp_get_public_status_id();
	if ( !bbp_check_for_moderation( $anonymous_data, bbp_get_reply_author_id( $reply_id ), $reply_title, $reply_content ) )
		$post_status = bbp_get_pending_status_id();

	/** Topic Tags ************************************************************/

	if ( !empty( $_POST['bbp_topic_tags'] ) )
		$terms = esc_attr( strip_tags( $_POST['bbp_topic_tags'] ) );

	/** Additional Actions (Before Save) **************************************/

	do_action( 'bbp_edit_reply_pre_extras', $reply_id );

	// Bail if errors
	if ( bbp_has_errors() )
		return;

	/** No Errors *************************************************************/

	// Add the content of the form to $reply_data as an array
	// Just in time manipulation of reply data before being edited
	$reply_data = apply_filters( 'bbp_edit_reply_pre_insert', array(
		'ID'           => $reply_id,
		'post_title'   => $reply_title,
		'post_content' => $reply_content,
		'post_status'  => $post_status,
		'post_parent'  => $reply->post_parent,
		'post_author'  => $reply->post_author,
		'post_type'    => bbp_get_reply_post_type()
	) );

	// Insert reply
	$reply_id = wp_update_post( $reply_data );

	/** Topic Tags ************************************************************/

	// Just in time manipulation of reply terms before being edited
	$terms = apply_filters( 'bbp_edit_reply_pre_set_terms', $terms, $topic_id, $reply_id );

	// Insert terms
	$terms = wp_set_post_terms( $topic_id, $terms, bbp_get_topic_tag_tax_id(), false );

	// Term error
	if ( is_wp_error( $terms ) ) {
		bbp_add_error( 'bbp_reply_tags', __( '<strong>ERROR</strong>: There was a problem adding the tags to the topic.', 'bbpress' ) );
	}

	/** Revisions *************************************************************/

	// Revision Reason
	if ( !empty( $_POST['bbp_reply_edit_reason'] ) )
		$reply_edit_reason = esc_attr( strip_tags( $_POST['bbp_reply_edit_reason'] ) );

	// Update revision log
	if ( !empty( $_POST['bbp_log_reply_edit'] ) && ( 1 == $_POST['bbp_log_reply_edit'] ) ) {
		$revision_id = wp_save_post_revision( $reply_id );
		if ( !empty( $revision_id ) ) {
			bbp_update_reply_revision_log( array(
				'reply_id'    => $reply_id,
				'revision_id' => $revision_id,
				'author_id'   => bbp_get_current_user_id(),
				'reason'      => $reply_edit_reason
			) );
		}
	}

	/** No Errors *************************************************************/

	if ( !empty( $reply_id ) && !is_wp_error( $reply_id ) ) {

		// Update counts, etc...
		do_action( 'bbp_edit_reply', $reply_id, $topic_id, $forum_id, $anonymous_data, $reply->post_author , true /* Is edit */ );

		/** Additional Actions (After Save) ***********************************/

		do_action( 'bbp_edit_reply_post_extras', $reply_id );

		/** Redirect **********************************************************/

		// Redirect to
		$redirect_to = !empty( $_REQUEST['redirect_to'] ) ? $_REQUEST['redirect_to'] : '';

		// Get the reply URL
		$reply_url = bbp_get_reply_url( $reply_id, $redirect_to );

		// Allow to be filtered
		$reply_url = apply_filters( 'bbp_edit_reply_redirect_to', $reply_url, $redirect_to );

		/** Successful Edit ***************************************************/

		// Redirect back to new reply
		wp_safe_redirect( $reply_url );

		// For good measure
		exit();

	/** Errors ****************************************************************/

	} else {
		$append_error = ( is_wp_error( $reply_id ) && $reply_id->get_error_message() ) ? $reply_id->get_error_message() . ' ' : '';
		bbp_add_error( 'bbp_reply_error', __( '<strong>ERROR</strong>: The following problem(s) have been found with your reply:' . $append_error . 'Please try again.', 'bbpress' ) );
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
 * @uses bbp_get_reply_topic_id() To get the reply topic id
 * @uses bbp_get_topic_forum_id() To get the topic forum id
 * @uses update_post_meta() To update the reply metas
 * @uses set_transient() To update the flood check transient for the ip
 * @uses bbp_update_user_last_posted() To update the users last posted time
 * @uses bbp_is_subscriptions_active() To check if the subscriptions feature is
 *                                      activated or not
 * @uses bbp_is_user_subscribed() To check if the user is subscribed
 * @uses bbp_remove_user_subscription() To remove the user's subscription
 * @uses bbp_add_user_subscription() To add the user's subscription
 * @uses bbp_update_reply_forum_id() To update the reply forum id
 * @uses bbp_update_reply_topic_id() To update the reply topic id
 * @uses bbp_update_reply_walker() To update the reply's ancestors' counts
 */
function bbp_update_reply( $reply_id = 0, $topic_id = 0, $forum_id = 0, $anonymous_data = false, $author_id = 0, $is_edit = false ) {

	// Validate the ID's passed from 'bbp_new_reply' action
	$reply_id = bbp_get_reply_id( $reply_id );
	$topic_id = bbp_get_topic_id( $topic_id );
	$forum_id = bbp_get_forum_id( $forum_id );

	// Bail if there is no reply
	if ( empty( $reply_id ) )
		return;

	// Check author_id
	if ( empty( $author_id ) )
		$author_id = bbp_get_current_user_id();

	// Check topic_id
	if ( empty( $topic_id ) )
		$topic_id = bbp_get_reply_topic_id( $reply_id );

	// Check forum_id
	if ( !empty( $topic_id ) && empty( $forum_id ) )
		$forum_id = bbp_get_topic_forum_id( $topic_id );

	// If anonymous post, store name, email, website and ip in post_meta.
	// It expects anonymous_data to be sanitized.
	// Check bbp_filter_anonymous_post_data() for sanitization.
	if ( !empty( $anonymous_data ) && is_array( $anonymous_data ) ) {

		// Always set at least these three values to empty
		$defaults = array(
			'bbp_anonymous_name'    => '',
			'bbp_anonymous_email'   => '',
			'bbp_anonymous_website' => '',
		);
		$r = bbp_parse_args( $anonymous_data, $defaults, 'update_reply' );

		// Update all anonymous metas
		foreach( $r as $anon_key => $anon_value ) {
			update_post_meta( $reply_id, '_' . $anon_key, (string) $anon_value, false );
		}

		// Set transient for throttle check (only on new, not edit)
		if ( empty( $is_edit ) ) {
			set_transient( '_bbp_' . bbp_current_author_ip() . '_last_posted', time() );
		}

	} else {
		if ( empty( $is_edit ) && !current_user_can( 'throttle' ) ) {
			bbp_update_user_last_posted( $author_id );
		}
	}

	// Handle Subscription Checkbox
	if ( bbp_is_subscriptions_active() && !empty( $author_id ) && !empty( $topic_id ) ) {
		$subscribed = bbp_is_user_subscribed( $author_id, $topic_id );
		$subscheck  = ( !empty( $_POST['bbp_topic_subscription'] ) && ( 'bbp_subscribe' == $_POST['bbp_topic_subscription'] ) ) ? true : false;

		// Subscribed and unsubscribing
		if ( true == $subscribed && false == $subscheck ) {
			bbp_remove_user_subscription( $author_id, $topic_id );

		// Subscribing
		} elseif ( false == $subscribed && true == $subscheck ) {
			bbp_add_user_subscription( $author_id, $topic_id );
		}
	}

	// Reply meta relating to reply position in tree
	bbp_update_reply_forum_id( $reply_id, $forum_id );
	bbp_update_reply_topic_id( $reply_id, $topic_id );

	// Update associated topic values if this is a new reply
	if ( empty( $is_edit ) ) {

		// Update poster IP if not editing
		update_post_meta( $reply_id, '_bbp_author_ip', bbp_current_author_ip(), false );

		// Last active time
		$last_active_time = current_time( 'mysql' );

		// Walk up ancestors and do the dirty work
		bbp_update_reply_walker( $reply_id, $last_active_time, $forum_id, $topic_id, false );
	}
}

/**
 * Walk up the ancestor tree from the current reply, and update all the counts
 *
 * @since bbPress (r2884)
 *
 * @param int $reply_id Optional. Reply id
 * @param string $last_active_time Optional. Last active time
 * @param int $forum_id Optional. Forum id
 * @param int $topic_id Optional. Topic id
 * @param bool $refresh If set to true, unsets all the previous parameters.
 *                       Defaults to true
 * @uses bbp_get_reply_id() To get the reply id
 * @uses bbp_get_reply_topic_id() To get the reply topic id
 * @uses bbp_get_reply_forum_id() To get the reply forum id
 * @uses get_post_ancestors() To get the ancestors of the reply
 * @uses bbp_is_reply() To check if the ancestor is a reply
 * @uses bbp_is_topic() To check if the ancestor is a topic
 * @uses bbp_update_topic_last_reply_id() To update the topic last reply id
 * @uses bbp_update_topic_last_active_id() To update the topic last active id
 * @uses bbp_get_topic_last_active_id() To get the topic last active id
 * @uses get_post_field() To get the post date of the last active id
 * @uses bbp_update_topic_last_active_time() To update the last active topic meta
 * @uses bbp_update_topic_voice_count() To update the topic voice count
 * @uses bbp_update_topic_reply_count() To update the topic reply count
 * @uses bbp_update_topic_reply_count_hidden() To update the topic hidden reply
 *                                              count
 * @uses bbp_is_forum() To check if the ancestor is a forum
 * @uses bbp_update_forum_last_topic_id() To update the last topic id forum meta
 * @uses bbp_update_forum_last_reply_id() To update the last reply id forum meta
 * @uses bbp_update_forum_last_active_id() To update the forum last active id
 * @uses bbp_get_forum_last_active_id() To get the forum last active id
 * @uses bbp_update_forum_last_active_time() To update the forum last active time
 * @uses bbp_update_forum_reply_count() To update the forum reply count
 */
function bbp_update_reply_walker( $reply_id, $last_active_time = '', $forum_id = 0, $topic_id = 0, $refresh = true ) {

	// Verify the reply ID
	$reply_id = bbp_get_reply_id( $reply_id );

	// Reply was passed
	if ( !empty( $reply_id ) ) {

		// Get the topic ID if none was passed
		if ( empty( $topic_id ) ) {
			$topic_id = bbp_get_reply_topic_id( $reply_id );
		}

		// Get the forum ID if none was passed
		if ( empty( $forum_id ) ) {
			$forum_id = bbp_get_reply_forum_id( $reply_id );
		}
	}

	// Set the active_id based on topic_id/reply_id
	$active_id = empty( $reply_id ) ? $topic_id : $reply_id;

	// Setup ancestors array to walk up
	$ancestors = array_values( array_unique( array_merge( array( $topic_id, $forum_id ), get_post_ancestors( $topic_id ) ) ) );

	// If we want a full refresh, unset any of the possibly passed variables
	if ( true == $refresh )
		$forum_id = $topic_id = $reply_id = $active_id = $last_active_time = 0;

	// Walk up ancestors
	foreach ( $ancestors as $ancestor ) {

		// Reply meta relating to most recent reply
		if ( bbp_is_reply( $ancestor ) ) {
			// @todo - hierarchical replies

		// Topic meta relating to most recent reply
		} elseif ( bbp_is_topic( $ancestor ) ) {

			// Last reply and active ID's
			bbp_update_topic_last_reply_id ( $ancestor, $reply_id  );
			bbp_update_topic_last_active_id( $ancestor, $active_id );

			// Get the last active time if none was passed
			$topic_last_active_time = $last_active_time;
			if ( empty( $last_active_time ) ) {
				$topic_last_active_time = get_post_field( 'post_date', bbp_get_topic_last_active_id( $ancestor ) );
			}

			// Only update if reply is published
			if ( bbp_is_reply_published( $reply_id ) ) {
				bbp_update_topic_last_active_time( $ancestor, $topic_last_active_time );
			}

			// Counts
			bbp_update_topic_voice_count       ( $ancestor );
			bbp_update_topic_reply_count       ( $ancestor );
			bbp_update_topic_reply_count_hidden( $ancestor );

		// Forum meta relating to most recent topic
		} elseif ( bbp_is_forum( $ancestor ) ) {

			// Last topic and reply ID's
			bbp_update_forum_last_topic_id( $ancestor, $topic_id );
			bbp_update_forum_last_reply_id( $ancestor, $reply_id );

			// Last Active
			bbp_update_forum_last_active_id( $ancestor, $active_id );

			// Get the last active time if none was passed
			$forum_last_active_time = $last_active_time;
			if ( empty( $last_active_time ) ) {
				$forum_last_active_time = get_post_field( 'post_date', bbp_get_forum_last_active_id( $ancestor ) );
			}

			// Only update if reply is published
			if ( bbp_is_reply_published( $reply_id ) ) {
				bbp_update_forum_last_active_time( $ancestor, $forum_last_active_time );
			}

			// Counts
			bbp_update_forum_reply_count( $ancestor );
		}
	}
}

/** Reply Updaters ************************************************************/

/**
 * Update the reply with its forum id it is in
 *
 * @since bbPress (r2855)
 *
 * @param int $reply_id Optional. Reply id to update
 * @param int $forum_id Optional. Forum id
 * @uses bbp_get_reply_id() To get the reply id
 * @uses bbp_get_forum_id() To get the forum id
 * @uses get_post_ancestors() To get the reply's forum
 * @uses get_post_field() To get the post type of the post
 * @uses update_post_meta() To update the reply forum id meta
 * @uses apply_filters() Calls 'bbp_update_reply_forum_id' with the forum id
 *                        and reply id
 * @return bool Reply's forum id
 */
function bbp_update_reply_forum_id( $reply_id = 0, $forum_id = 0 ) {

	// Validation
	$reply_id = bbp_get_reply_id( $reply_id );
	$forum_id = bbp_get_forum_id( $forum_id );

	// If no forum_id was passed, walk up ancestors and look for forum type
	if ( empty( $forum_id ) ) {

		// Get ancestors
		$ancestors = get_post_ancestors( $reply_id );

		// Loop through ancestors
		foreach ( $ancestors as $ancestor ) {

			// Get first parent that is a forum
			if ( get_post_field( 'post_type', $ancestor ) == bbp_get_forum_post_type() ) {
				$forum_id = $ancestor;

				// Found a forum, so exit the loop and continue
				continue;
			}
		}
	}

	// Update the forum ID
	bbp_update_forum_id( $reply_id, $forum_id );

	return apply_filters( 'bbp_update_reply_forum_id', (int) $forum_id, $reply_id );
}

/**
 * Update the reply with its topic id it is in
 *
 * @since bbPress (r2855)
 *
 * @param int $reply_id Optional. Reply id to update
 * @param int $topic_id Optional. Topic id
 * @uses bbp_get_reply_id() To get the reply id
 * @uses bbp_get_topic_id() To get the topic id
 * @uses get_post_ancestors() To get the reply's topic
 * @uses get_post_field() To get the post type of the post
 * @uses update_post_meta() To update the reply topic id meta
 * @uses apply_filters() Calls 'bbp_update_reply_topic_id' with the topic id
 *                        and reply id
 * @return bool Reply's topic id
 */
function bbp_update_reply_topic_id( $reply_id = 0, $topic_id = 0 ) {

	// Validation
	$reply_id = bbp_get_reply_id( $reply_id );
	$topic_id = bbp_get_topic_id( $topic_id );

	// If no topic_id was passed, walk up ancestors and look for topic type
	if ( empty( $topic_id ) ) {

		// Get ancestors
		$ancestors = get_post_ancestors( $reply_id );

		// Loop through ancestors
		foreach ( $ancestors as $ancestor ) {

			// Get first parent that is a forum
			if ( get_post_field( 'post_type', $ancestor ) == bbp_get_topic_post_type() ) {
				$topic_id = $ancestor;

				// Found a forum, so exit the loop and continue
				continue;
			}
		}
	}

	// Update the topic ID
	bbp_update_topic_id( $reply_id, $topic_id );

	return apply_filters( 'bbp_update_reply_topic_id', (int) $topic_id, $reply_id );
}

/**
 * Update the revision log of the reply
 *
 * @since bbPress (r2782)
 *
 * @param mixed $args Supports these args:
 *  - reply_id: reply id
 *  - author_id: Author id
 *  - reason: Reason for editing
 *  - revision_id: Revision id
 * @uses bbp_get_reply_id() To get the reply id
 * @uses bbp_get_user_id() To get the user id
 * @uses bbp_format_revision_reason() To format the reason
 * @uses bbp_get_reply_raw_revision_log() To get the raw reply revision log
 * @uses update_post_meta() To update the reply revision log meta
 * @return mixed False on failure, true on success
 */
function bbp_update_reply_revision_log( $args = '' ) {
	$defaults = array (
		'reason'      => '',
		'reply_id'    => 0,
		'author_id'   => 0,
		'revision_id' => 0
	);

	$r = bbp_parse_args( $args, $defaults, 'update_reply_revision_log' );
	extract( $r );

	// Populate the variables
	$reason      = bbp_format_revision_reason( $reason );
	$reply_id    = bbp_get_reply_id( $reply_id );
	$author_id   = bbp_get_user_id ( $author_id, false, true );
	$revision_id = (int) $revision_id;

	// Get the logs and append the new one to those
	$revision_log               = bbp_get_reply_raw_revision_log( $reply_id );
	$revision_log[$revision_id] = array( 'author' => $author_id, 'reason' => $reason );

	// Finally, update
	update_post_meta( $reply_id, '_bbp_revision_log', $revision_log );

	return apply_filters( 'bbp_update_reply_revision_log', $revision_log, $reply_id );
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
 * @uses bbp_get_reply_post_type() To get the reply post type
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
 * @uses wp_safe_redirect() To redirect to the reply
 * @uses bbPress::errors:add() To log the error messages
 */
function bbp_toggle_reply_handler() {

	// Bail if not a GET action
	if ( 'GET' !== strtoupper( $_SERVER['REQUEST_METHOD'] ) )
		return;

	// Bail if required GET actions aren't passed
	if ( empty( $_GET['reply_id'] ) || empty( $_GET['action'] ) )
		return;

	// Setup possible get actions
	$possible_actions = array(
		'bbp_toggle_reply_spam',
		'bbp_toggle_reply_trash'
	);

	// Bail if actions aren't meant for this function
	if ( !in_array( $_GET['action'], $possible_actions ) )
		return;

	$failure   = '';                         // Empty failure string
	$view_all  = false;                      // Assume not viewing all
	$action    = $_GET['action'];            // What action is taking place?
	$reply_id  = (int) $_GET['reply_id'];    // What's the reply id?
	$success   = false;                      // Flag
	$post_data = array( 'ID' => $reply_id ); // Prelim array

	// Make sure reply exists
	$reply = bbp_get_reply( $reply_id );
	if ( empty( $reply ) )
		return;

	// What is the user doing here?
	if ( !current_user_can( 'edit_reply', $reply->ID ) || ( 'bbp_toggle_reply_trash' == $action && !current_user_can( 'delete_reply', $reply->ID ) ) ) {
		bbp_add_error( 'bbp_toggle_reply_permission', __( '<strong>ERROR:</strong> You do not have the permission to do that!', 'bbpress' ) );
		return;
	}

	// What action are we trying to perform?
	switch ( $action ) {

		// Toggle spam
		case 'bbp_toggle_reply_spam' :
			check_ajax_referer( 'spam-reply_' . $reply_id );

			$is_spam  = bbp_is_reply_spam( $reply_id );
			$success  = $is_spam ? bbp_unspam_reply( $reply_id ) : bbp_spam_reply( $reply_id );
			$failure  = $is_spam ? __( '<strong>ERROR</strong>: There was a problem unmarking the reply as spam!', 'bbpress' ) : __( '<strong>ERROR</strong>: There was a problem marking the reply as spam!', 'bbpress' );
			$view_all = !$is_spam;

			break;

		// Toggle trash
		case 'bbp_toggle_reply_trash' :

			$sub_action = in_array( $_GET['sub_action'], array( 'trash', 'untrash', 'delete' ) ) ? $_GET['sub_action'] : false;

			if ( empty( $sub_action ) )
				break;

			switch ( $sub_action ) {
				case 'trash':
					check_ajax_referer( 'trash-' . bbp_get_reply_post_type() . '_' . $reply_id );

					$view_all = true;
					$success  = wp_trash_post( $reply_id );
					$failure  = __( '<strong>ERROR</strong>: There was a problem trashing the reply!', 'bbpress' );

					break;

				case 'untrash':
					check_ajax_referer( 'untrash-' . bbp_get_reply_post_type() . '_' . $reply_id );

					$success = wp_untrash_post( $reply_id );
					$failure = __( '<strong>ERROR</strong>: There was a problem untrashing the reply!', 'bbpress' );

					break;

				case 'delete':
					check_ajax_referer( 'delete-' . bbp_get_reply_post_type() . '_' . $reply_id );

					$success = wp_delete_post( $reply_id );
					$failure = __( '<strong>ERROR</strong>: There was a problem deleting the reply!', 'bbpress' );

					break;
			}

			break;
	}

	// Do additional reply toggle actions
	do_action( 'bbp_toggle_reply_handler', $success, $post_data, $action );

	// No errors
	if ( ( false != $success ) && !is_wp_error( $success ) ) {

		/** Redirect **********************************************************/

		// Redirect to
		$redirect_to = !empty( $_REQUEST['redirect_to'] ) ? $_REQUEST['redirect_to'] : '';

		// Get the reply URL
		$reply_url = bbp_get_reply_url( $reply_id, $redirect_to );

		// Add view all if needed
		if ( !empty( $view_all ) )
			$reply_url = bbp_add_view_all( $reply_url, true );

		// Redirect back to reply
		wp_safe_redirect( $reply_url );

		// For good measure
		exit();

	// Handle errors
	} else {
		bbp_add_error( 'bbp_toggle_reply', $failure );
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
 * @uses do_action() Calls 'bbp_spam_reply' with the reply ID
 * @uses add_post_meta() To add the previous status to a meta
 * @uses wp_insert_post() To insert the updated post
 * @uses do_action() Calls 'bbp_spammed_reply' with the reply ID
 * @return mixed False or {@link WP_Error} on failure, reply id on success
 */
function bbp_spam_reply( $reply_id = 0 ) {

	// Get reply
	$reply = wp_get_single_post( $reply_id, ARRAY_A );
	if ( empty( $reply ) )
		return $reply;

	// Bail if already spam
	if ( bbp_get_spam_status_id() == $reply['post_status'] )
		return false;

	// Execute pre spam code
	do_action( 'bbp_spam_reply', $reply_id );

	// Add the original post status as post meta for future restoration
	add_post_meta( $reply_id, '_bbp_spam_meta_status', $reply['post_status'] );

	// Set post status to spam
	$reply['post_status'] = bbp_get_spam_status_id();

	// No revisions
	remove_action( 'pre_post_update', 'wp_save_post_revision' );

	// Update the reply
	$reply_id = wp_insert_post( $reply );

	// Execute post spam code
	do_action( 'bbp_spammed_reply', $reply_id );

	// Return reply_id
	return $reply_id;
}

/**
 * Unspams a reply
 *
 * @since bbPress (r2740)
 *
 * @param int $reply_id Reply id
 * @uses wp_get_single_post() To get the reply
 * @uses do_action() Calls 'bbp_unspam_reply' with the reply ID
 * @uses get_post_meta() To get the previous status meta
 * @uses delete_post_meta() To delete the previous status meta
 * @uses wp_insert_post() To insert the updated post
 * @uses do_action() Calls 'bbp_unspammed_reply' with the reply ID
 * @return mixed False or {@link WP_Error} on failure, reply id on success
 */
function bbp_unspam_reply( $reply_id = 0 ) {

	// Get reply
	$reply = wp_get_single_post( $reply_id, ARRAY_A );
	if ( empty( $reply ) )
		return $reply;

	// Bail if already not spam
	if ( bbp_get_spam_status_id() != $reply['post_status'] )
		return false;

	// Execute pre unspam code
	do_action( 'bbp_unspam_reply', $reply_id );

	// Get pre spam status
	$reply['post_status'] = get_post_meta( $reply_id, '_bbp_spam_meta_status', true );

	// Delete pre spam meta
	delete_post_meta( $reply_id, '_bbp_spam_meta_status' );

	// No revisions
	remove_action( 'pre_post_update', 'wp_save_post_revision' );

	// Update the reply
	$reply_id = wp_insert_post( $reply );

	// Execute post unspam code
	do_action( 'bbp_unspammed_reply', $reply_id );

	// Return reply_id
	return $reply_id;
}

/** Before Delete/Trash/Untrash ***********************************************/

/**
 * Called before deleting a reply
 *
 * @uses bbp_get_reply_id() To get the reply id
 * @uses bbp_is_reply() To check if the passed id is a reply
 * @uses do_action() Calls 'bbp_delete_reply' with the reply id
 */
function bbp_delete_reply( $reply_id = 0 ) {
	$reply_id = bbp_get_reply_id( $reply_id );

	if ( empty( $reply_id ) || !bbp_is_reply( $reply_id ) )
		return false;

	do_action( 'bbp_delete_reply', $reply_id );
}

/**
 * Called before trashing a reply
 *
 * @uses bbp_get_reply_id() To get the reply id
 * @uses bbp_is_reply() To check if the passed id is a reply
 * @uses do_action() Calls 'bbp_trash_reply' with the reply id
 */
function bbp_trash_reply( $reply_id = 0 ) {
	$reply_id = bbp_get_reply_id( $reply_id );

	if ( empty( $reply_id ) || !bbp_is_reply( $reply_id ) )
		return false;

	do_action( 'bbp_trash_reply', $reply_id );
}

/**
 * Called before untrashing (restoring) a reply
 *
 * @uses bbp_get_reply_id() To get the reply id
 * @uses bbp_is_reply() To check if the passed id is a reply
 * @uses do_action() Calls 'bbp_unstrash_reply' with the reply id
 */
function bbp_untrash_reply( $reply_id = 0 ) {
	$reply_id = bbp_get_reply_id( $reply_id );

	if ( empty( $reply_id ) || !bbp_is_reply( $reply_id ) )
		return false;

	do_action( 'bbp_untrash_reply', $reply_id );
}

/** After Delete/Trash/Untrash ************************************************/

/**
 * Called after deleting a reply
 *
 * @uses bbp_get_reply_id() To get the reply id
 * @uses bbp_is_reply() To check if the passed id is a reply
 * @uses do_action() Calls 'bbp_deleted_reply' with the reply id
 */
function bbp_deleted_reply( $reply_id = 0 ) {
	$reply_id = bbp_get_reply_id( $reply_id );

	if ( empty( $reply_id ) || !bbp_is_reply( $reply_id ) )
		return false;

	do_action( 'bbp_deleted_reply', $reply_id );
}

/**
 * Called after trashing a reply
 *
 * @uses bbp_get_reply_id() To get the reply id
 * @uses bbp_is_reply() To check if the passed id is a reply
 * @uses do_action() Calls 'bbp_trashed_reply' with the reply id
 */
function bbp_trashed_reply( $reply_id = 0 ) {
	$reply_id = bbp_get_reply_id( $reply_id );

	if ( empty( $reply_id ) || !bbp_is_reply( $reply_id ) )
		return false;

	do_action( 'bbp_trashed_reply', $reply_id );
}

/**
 * Called after untrashing (restoring) a reply
 *
 * @uses bbp_get_reply_id() To get the reply id
 * @uses bbp_is_reply() To check if the passed id is a reply
 * @uses do_action() Calls 'bbp_untrashed_reply' with the reply id
 */
function bbp_untrashed_reply( $reply_id = 0 ) {
	$reply_id = bbp_get_reply_id( $reply_id );

	if ( empty( $reply_id ) || !bbp_is_reply( $reply_id ) )
		return false;

	do_action( 'bbp_untrashed_reply', $reply_id );
}

/** Settings ******************************************************************/

/**
 * Return the replies per page setting
 *
 * @since bbPress (r3540)
 *
 * @uses get_option() To get the setting
 * @uses apply_filters() To allow the return value to be manipulated
 * @return int
 */
function bbp_get_replies_per_page() {

	// The default per setting
	$default = 15;

	// Get database option and cast as integer
	$per = $retval = (int) get_option( '_bbp_replies_per_page', $default );

	// If return val is empty, set it to default
	if ( empty( $retval ) )
		$retval = $default;

	// Filter and return
	return (int) apply_filters( 'bbp_get_replies_per_page', $retval, $per );
}

/**
 * Return the replies per RSS page setting
 *
 * @since bbPress (r3540)
 *
 * @uses get_option() To get the setting
 * @uses apply_filters() To allow the return value to be manipulated
 * @return int
 */
function bbp_get_replies_per_rss_page() {

	// The default per setting
	$default = 25;

	// Get database option and cast as integer
	$per = $retval = (int) get_option( '_bbp_replies_per_rss_page', $default );

	// If return val is empty, set it to default
	if ( empty( $retval ) )
		$retval = $default;

	// Filter and return
	return (int) apply_filters( 'bbp_get_replies_per_rss_page', $retval, $per );
}

/** Autoembed *****************************************************************/

/**
 * Check if autoembeds are enabled and hook them in if so
 *
 * @since bbPress (r3752)
 * @global WP_Embed $wp_embed
 */
function bbp_reply_content_autoembed() {
	global $wp_embed;

	if ( bbp_use_autoembed() && is_a( $wp_embed, 'WP_Embed' ) ) {
		add_filter( 'bbp_get_reply_content', array( $wp_embed, 'autoembed' ), 8 );
	}
}

/** Filters *******************************************************************/

/**
 * Used by bbp_has_replies() to add the topic to the posts
 *
 * This function filters the 'post_where' of the WP_Query, and changes the query
 * to include both the topic AND its children in the same loop.
 *
 * @since bbPress (r4058)
 *
 * @param string $where
 * @return string
 */
function _bbp_has_replies_where( $where, $query ) {

	// Bail if no post_parent to replace
	if ( ! is_numeric( $query->get( 'post_parent' ) ) )
		return $where;

	// Bail if not a topic and reply query
	if ( array( bbp_get_topic_post_type(), bbp_get_reply_post_type() ) != $query->get( 'post_type' ) )
		return $where;

	// Get the topic ID
	$topic_id = bbp_get_topic_id();

	// The text we're searching for
	$search   = 'wp_posts.post_parent = ' . $topic_id ;

	// The text to replace it with
	$replace  = '(wp_posts.ID = ' . $topic_id . ' OR wp_posts.post_parent = ' . $topic_id . ')';

	// Try to replace the search text with the replacement
	if ( $new_where = str_replace( $search, $replace, $where ) )
		$where = $new_where;

	return $where;
}

/** Feeds *********************************************************************/

/**
 * Output an RSS2 feed of replies, based on the query passed.
 *
 * @since bbPress (r3171)
 *
 * @uses bbp_version()
 * @uses bbp_is_single_topic()
 * @uses bbp_user_can_view_forum()
 * @uses bbp_get_topic_forum_id()
 * @uses bbp_show_load_topic()
 * @uses bbp_topic_permalink()
 * @uses bbp_topic_title()
 * @uses bbp_get_topic_reply_count()
 * @uses bbp_topic_content()
 * @uses bbp_has_replies()
 * @uses bbp_replies()
 * @uses bbp_the_reply()
 * @uses bbp_reply_url()
 * @uses bbp_reply_title()
 * @uses bbp_reply_content()
 * @uses get_wp_title_rss()
 * @uses get_option()
 * @uses bloginfo_rss
 * @uses self_link()
 * @uses the_author()
 * @uses get_post_time()
 * @uses rss_enclosure()
 * @uses do_action()
 * @uses apply_filters()
 *
 * @param array $replies_query
 */
function bbp_display_replies_feed_rss2( $replies_query = array() ) {

	// User cannot access forum this topic is in
	if ( bbp_is_single_topic() && !bbp_user_can_view_forum( array( 'forum_id' => bbp_get_topic_forum_id() ) ) )
		return;

	// Adjust the title based on context
	if ( bbp_is_single_topic() && bbp_user_can_view_forum( array( 'forum_id' => bbp_get_topic_forum_id() ) ) )
		$title = apply_filters( 'wp_title_rss', get_wp_title_rss( ' &#187; ' ) );
	elseif ( !bbp_show_lead_topic() )
		$title = ' &#187; ' .  __( 'All Posts',   'bbpress' );
	else
		$title = ' &#187; ' .  __( 'All Replies', 'bbpress' );

	// Display the feed
	header( 'Content-Type: text/xml; charset=' . get_option( 'blog_charset' ), true );
	header( 'Status: 200 OK' );
	echo '<?xml version="1.0" encoding="' . get_option( 'blog_charset' ) . '"?' . '>'; ?>

	<rss version="2.0"
		xmlns:content="http://purl.org/rss/1.0/modules/content/"
		xmlns:wfw="http://wellformedweb.org/CommentAPI/"
		xmlns:dc="http://purl.org/dc/elements/1.1/"
		xmlns:atom="http://www.w3.org/2005/Atom"

		<?php do_action( 'bbp_feed' ); ?>
	>

	<channel>
		<title><?php bloginfo_rss('name'); echo $title; ?></title>
		<atom:link href="<?php self_link(); ?>" rel="self" type="application/rss+xml" />
		<link><?php self_link(); ?></link>
		<description><?php //?></description>
		<pubDate><?php echo mysql2date( 'D, d M Y H:i:s O', '', false ); ?></pubDate>
		<generator>http://bbpress.org/?v=<?php bbp_version(); ?></generator>
		<language><?php echo get_option( 'rss_language' ); ?></language>

		<?php do_action( 'bbp_feed_head' ); ?>

		<?php if ( bbp_is_single_topic() ) : ?>
			<?php if ( bbp_user_can_view_forum( array( 'forum_id' => bbp_get_topic_forum_id() ) ) ) : ?>
				<?php if ( bbp_show_lead_topic() ) : ?>

					<item>
						<guid><?php bbp_topic_permalink(); ?></guid>
						<title><![CDATA[<?php bbp_topic_title(); ?>]]></title>
						<link><?php bbp_topic_permalink(); ?></link>
						<pubDate><?php echo mysql2date( 'D, d M Y H:i:s +0000', get_post_time( 'Y-m-d H:i:s', true ), false ); ?></pubDate>
						<dc:creator><?php the_author(); ?></dc:creator>

						<description>
							<![CDATA[
							<p><?php printf( __( 'Replies: %s', 'bbpress' ), bbp_get_topic_reply_count() ); ?></p>
							<?php bbp_topic_content(); ?>
							]]>
						</description>

						<?php rss_enclosure(); ?>

						<?php do_action( 'bbp_feed_item' ); ?>

					</item>

				<?php endif; ?>
			<?php endif; ?>
		<?php endif; ?>

		<?php if ( bbp_has_replies( $replies_query ) ) : ?>
			<?php while ( bbp_replies() ) : bbp_the_reply(); ?>

				<item>
					<guid><?php bbp_reply_url(); ?></guid>
					<title><![CDATA[<?php bbp_reply_title(); ?>]]></title>
					<link><?php bbp_reply_url(); ?></link>
					<pubDate><?php echo mysql2date( 'D, d M Y H:i:s +0000', get_post_time( 'Y-m-d H:i:s', true ), false ); ?></pubDate>
					<dc:creator><?php the_author() ?></dc:creator>

					<description>
						<![CDATA[
						<?php bbp_reply_content(); ?>
						]]>
					</description>

					<?php rss_enclosure(); ?>

					<?php do_action( 'bbp_feed_item' ); ?>

				</item>

			<?php endwhile; ?>
		<?php endif; ?>

		<?php do_action( 'bbp_feed_footer' ); ?>

	</channel>
	</rss>

<?php

	// We're done here
	exit();
}

/** Permissions ***************************************************************/

/**
 * Redirect if unathorized user is attempting to edit a reply
 *
 * @since bbPress (r3605)
 *
 * @uses bbp_is_reply_edit()
 * @uses current_user_can()
 * @uses bbp_get_topic_id()
 * @uses wp_safe_redirect()
 * @uses bbp_get_topic_permalink()
 */
function bbp_check_reply_edit() {

	// Bail if not editing a topic
	if ( !bbp_is_reply_edit() )
		return;

	// User cannot edit topic, so redirect back to reply
	if ( !current_user_can( 'edit_reply', bbp_get_reply_id() ) ) {
		wp_safe_redirect( bbp_get_reply_url() );
		exit();
	}
}

/** Reply Position ************************************************************/

/**
 * Update the position of the reply.
 *
 * The reply position is stored in the menu_order column of the posts table.
 * This is done to prevent using a meta_query to retrieve posts in the proper
 * freshness order. By updating the menu_order accordingly, we're able to
 * leverage core WordPress query ordering much more effectively.
 *
 * @since bbPress (r3933)
 *
 * @global type $wpdb
 * @param type $reply_id
 * @param type $reply_position
 * @return mixed
 */
function bbp_update_reply_position( $reply_id = 0, $reply_position = 0 ) {

	// Bail if reply_id is empty
	$reply_id = bbp_get_reply_id( $reply_id );
	if ( empty( $reply_id ) )
		return false;

	// If no position was passed, get it from the db and update the menu_order
	if ( empty( $reply_position ) ) {
		$reply_position = bbp_get_reply_position_raw( $reply_id, bbp_get_reply_topic_id( $reply_id ) );
	}

	// Update the replies' 'menp_order' with the reply position
	global $wpdb;
	$wpdb->update( $wpdb->posts, array( 'menu_order' => $reply_position ), array( 'ID' => $reply_id ) );

	return (int) $reply_position;
}

/**
 * Get the position of a reply by querying the DB directly for the replies
 * of a given topic.
 *
 * @since bbPress (r3933)
 *
 * @param int $reply_id
 * @param int $topic_id
 */
function bbp_get_reply_position_raw( $reply_id = 0, $topic_id = 0 ) {

	// Get required data
	$reply_id       = bbp_get_reply_id( $reply_id );
	$topic_id       = !empty( $topic_id ) ? bbp_get_topic_id( $topic_id ) : bbp_get_reply_topic_id( $reply_id );
	$reply_position = 0;

	// If reply is actually the first post in a topic, return 0
	if ( $reply_id != $topic_id ) {

		// Make sure the topic has replies before running another query
		$reply_count = bbp_get_topic_reply_count( $topic_id );
		if ( !empty( $reply_count ) ) {

			// Get reply id's
			$topic_replies = bbp_get_all_child_ids( $topic_id, bbp_get_reply_post_type() );
			if ( !empty( $topic_replies ) ) {

				// Reverse replies array and search for current reply position
				$topic_replies  = array_reverse( $topic_replies );
				$reply_position = array_search( (string) $reply_id, $topic_replies );

				// Bump the position to compensate for the lead topic post
				$reply_position++;
			}
		}
	}

	return (int) $reply_position;
}
