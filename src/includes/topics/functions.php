<?php

/**
 * bbPress Topic Functions
 *
 * @package bbPress
 * @subpackage Functions
 */

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

/** Insert ********************************************************************/

/**
 * A wrapper for wp_insert_post() that also includes the necessary meta values
 * for the topic to function properly.
 *
 * @since 2.0.0 bbPress (r3349)
 *
 * @param array $topic_data Forum post data
 * @param array $topic_meta Forum meta data
 */
function bbp_insert_topic( $topic_data = array(), $topic_meta = array() ) {

	// Parse arguments against default values
	$topic_data = bbp_parse_args( $topic_data, array(
		'post_parent'    => 0, // forum ID
		'post_status'    => bbp_get_public_status_id(),
		'post_type'      => bbp_get_topic_post_type(),
		'post_author'    => bbp_get_current_user_id(),
		'post_password'  => '',
		'post_content'   => '',
		'post_title'     => '',
		'comment_status' => 'closed',
		'menu_order'     => 0
	), 'insert_topic' );

	// Insert topic
	$topic_id = wp_insert_post( $topic_data, false );

	// Bail if no topic was added
	if ( empty( $topic_id ) ) {
		return false;
	}

	// Parse arguments against default values
	$topic_meta = bbp_parse_args( $topic_meta, array(
		'author_ip'          => bbp_current_author_ip(),
		'forum_id'           => 0,
		'topic_id'           => $topic_id,
		'voice_count'        => 1,
		'reply_count'        => 0,
		'reply_count_hidden' => 0,
		'last_reply_id'      => 0,
		'last_active_id'     => $topic_id,
		'last_active_time'   => get_post_field( 'post_date', $topic_id, 'db' )
	), 'insert_topic_meta' );

	// Insert topic meta
	foreach ( $topic_meta as $meta_key => $meta_value ) {

		// Prefix if not prefixed
		if ( '_bbp_' !== substr( $meta_key, 0, 5 ) ) {
			$meta_key = '_bbp_' . $meta_key;
		}

		// Update the meta
		update_post_meta( $topic_id, $meta_key, $meta_value );
	}

	// Update the topic and hierarchy
	bbp_update_topic( $topic_id, $topic_meta['forum_id'], array(), $topic_data['post_author'], false );

	/**
	 * Fires after topic has been inserted via `bbp_insert_topic`.
	 *
	 * @since 2.6.0 bbPress (r6036)
	 *
	 * @param int $topic_id               The topic id.
	 * @param int $topic_meta['forum_id'] The topic forum meta.
	 */
	do_action( 'bbp_insert_topic', (int) $topic_id, (int) $topic_meta['forum_id'] );

	// Return topic_id
	return $topic_id;
}

/** Post Form Handlers ********************************************************/

/**
 * Handles the front end topic submission
 *
 * @param string $action The requested action to compare this function to
 */
function bbp_new_topic_handler( $action = '' ) {

	// Bail if action is not bbp-new-topic
	if ( 'bbp-new-topic' !== $action ) {
		return;
	}

	// Nonce check
	if ( ! bbp_verify_nonce_request( 'bbp-new-topic' ) ) {
		bbp_add_error( 'bbp_new_topic_nonce', __( '<strong>Error</strong>: Are you sure you wanted to do that?', 'bbpress' ) );
		return;
	}

	// Define local variable(s)
	$view_all = false;
	$forum_id = $topic_author = 0;
	$topic_title = $topic_content = '';
	$anonymous_data = array();
	$terms = array( bbp_get_topic_tag_tax_id() => array() );

	/** Topic Author **********************************************************/

	// User is anonymous
	if ( bbp_is_anonymous() ) {

		// Filter anonymous data (variable is used later)
		$anonymous_data = bbp_filter_anonymous_post_data();

		// Anonymous data checks out, so set cookies, etc...
		bbp_set_current_anonymous_user_data( $anonymous_data );

	// User is logged in
	} else {

		// User cannot create topics
		if ( ! current_user_can( 'publish_topics' ) ) {
			bbp_add_error( 'bbp_topic_permission', __( '<strong>Error</strong>: You do not have permission to create new topics.', 'bbpress' ) );
			return;
		}

		// Topic author is current user
		$topic_author = bbp_get_current_user_id();
	}

	// Remove kses filters from title and content for capable users and if the nonce is verified
	if ( current_user_can( 'unfiltered_html' ) && ! empty( $_POST['_bbp_unfiltered_html_topic'] ) && wp_create_nonce( 'bbp-unfiltered-html-topic_new' ) === $_POST['_bbp_unfiltered_html_topic'] ) {
		remove_filter( 'bbp_new_topic_pre_title',   'wp_filter_kses'      );
		remove_filter( 'bbp_new_topic_pre_content', 'bbp_encode_bad',  10 );
		remove_filter( 'bbp_new_topic_pre_content', 'bbp_filter_kses', 30 );
	}

	/** Topic Title ***********************************************************/

	if ( ! empty( $_POST['bbp_topic_title'] ) ) {
		$topic_title = sanitize_text_field( $_POST['bbp_topic_title'] );
	}

	// Filter and sanitize
	$topic_title = apply_filters( 'bbp_new_topic_pre_title', $topic_title );

	// No topic title
	if ( empty( $topic_title ) ) {
		bbp_add_error( 'bbp_topic_title', __( '<strong>Error</strong>: Your topic needs a title.', 'bbpress' ) );
	}

	// Title too long
	if ( bbp_is_title_too_long( $topic_title ) ) {
		bbp_add_error( 'bbp_topic_title', __( '<strong>Error</strong>: Your title is too long.', 'bbpress' ) );
	}

	/** Topic Content *********************************************************/

	if ( ! empty( $_POST['bbp_topic_content'] ) ) {
		$topic_content = $_POST['bbp_topic_content'];
	}

	// Filter and sanitize
	$topic_content = apply_filters( 'bbp_new_topic_pre_content', $topic_content );

	// No topic content
	if ( empty( $topic_content ) ) {
		bbp_add_error( 'bbp_topic_content', __( '<strong>Error</strong>: Your topic cannot be empty.', 'bbpress' ) );
	}

	/** Topic Forum ***********************************************************/

	// Error check the POST'ed topic id
	if ( isset( $_POST['bbp_forum_id'] ) ) {

		// Empty Forum id was passed
		if ( empty( $_POST['bbp_forum_id'] ) ) {
			bbp_add_error( 'bbp_topic_forum_id', __( '<strong>Error</strong>: Forum ID is missing.', 'bbpress' ) );

		// Forum id is not a number
		} elseif ( ! is_numeric( $_POST['bbp_forum_id'] ) ) {
			bbp_add_error( 'bbp_topic_forum_id', __( '<strong>Error</strong>: Forum ID must be a number.', 'bbpress' ) );

		// Forum id might be valid
		} else {

			// Get the forum id
			$posted_forum_id = intval( $_POST['bbp_forum_id'] );

			// Forum id is empty
			if ( 0 === $posted_forum_id ) {
				bbp_add_error( 'bbp_topic_forum_id', __( '<strong>Error</strong>: Forum ID is missing.', 'bbpress' ) );

			// Forum id is a negative number
			} elseif ( 0 > $posted_forum_id ) {
				bbp_add_error( 'bbp_topic_forum_id', __( '<strong>Error</strong>: Forum ID cannot be a negative number.', 'bbpress' ) );

			// Forum does not exist
			} elseif ( ! bbp_get_forum( $posted_forum_id ) ) {
				bbp_add_error( 'bbp_topic_forum_id', __( '<strong>Error</strong>: Forum does not exist.', 'bbpress' ) );

			// Use the POST'ed forum id
			} else {
				$forum_id = $posted_forum_id;
			}
		}
	}

	// Forum exists
	if ( ! empty( $forum_id ) ) {

		// Forum is a category
		if ( bbp_is_forum_category( $forum_id ) ) {
			bbp_add_error( 'bbp_new_topic_forum_category', __( '<strong>Error</strong>: This forum is a category. No topics can be created in this forum.', 'bbpress' ) );

		// Forum is not a category
		} else {

			// Forum is closed and user cannot access
			if ( bbp_is_forum_closed( $forum_id ) && ! current_user_can( 'edit_forum', $forum_id ) ) {
				bbp_add_error( 'bbp_new_topic_forum_closed', __( '<strong>Error</strong>: This forum has been closed to new topics.', 'bbpress' ) );
			}

			// Forum is private and user cannot access
			if ( bbp_is_forum_private( $forum_id ) && ! current_user_can( 'read_forum', $forum_id ) ) {
				bbp_add_error( 'bbp_new_topic_forum_private', __( '<strong>Error</strong>: This forum is private and you do not have the capability to read or create new topics in it.', 'bbpress' ) );

			// Forum is hidden and user cannot access
			} elseif ( bbp_is_forum_hidden( $forum_id ) && ! current_user_can( 'read_forum', $forum_id ) ) {
				bbp_add_error( 'bbp_new_topic_forum_hidden', __( '<strong>Error</strong>: This forum is hidden and you do not have the capability to read or create new topics in it.', 'bbpress' ) );
			}
		}
	}

	/** Topic Flooding ********************************************************/

	if ( ! bbp_check_for_flood( $anonymous_data, $topic_author ) ) {
		bbp_add_error( 'bbp_topic_flood', __( '<strong>Error</strong>: Slow down; you move too fast.', 'bbpress' ) );
	}

	/** Topic Duplicate *******************************************************/

	$dupe_args = array(
		'post_type'      => bbp_get_topic_post_type(),
		'post_author'    => $topic_author,
		'post_content'   => $topic_content,
		'post_parent'    => $forum_id,
		'anonymous_data' => $anonymous_data
	);

	if ( ! bbp_check_for_duplicate( $dupe_args ) ) {
		bbp_add_error( 'bbp_topic_duplicate', __( '<strong>Error</strong>: Duplicate topic detected; it looks as though you&#8217;ve already said that.', 'bbpress' ) );
	}

	/** Topic Bad Words *******************************************************/

	if ( ! bbp_check_for_moderation( $anonymous_data, $topic_author, $topic_title, $topic_content, true ) ) {
		bbp_add_error( 'bbp_topic_moderation', __( '<strong>Error</strong>: Your topic cannot be created at this time.', 'bbpress' ) );
	}

	/** Topic Status **********************************************************/

	// Get available topic statuses
	$topic_statuses = bbp_get_topic_statuses();

	// Default to published
	$topic_status = bbp_get_public_status_id();

	// Maybe force into pending
	if ( ! bbp_check_for_moderation( $anonymous_data, $topic_author, $topic_title, $topic_content ) ) {
		$topic_status = bbp_get_pending_status_id();

	// Check for possible posted topic status
	} elseif ( ! empty( $_POST['bbp_topic_status'] ) && in_array( $_POST['bbp_topic_status'], array_keys( $topic_statuses ), true ) ) {

		// Allow capable users to explicitly override the status
		if ( current_user_can( 'moderate', $forum_id ) ) {
			$topic_status = sanitize_key( $_POST['bbp_topic_status'] );

		// Not capable
		} else {
			bbp_add_error( 'bbp_new_topic_status', __( '<strong>Error</strong>: You do not have permission to do that.', 'bbpress' ) );
		}
	}

	/** Topic Tags ************************************************************/

	if ( bbp_allow_topic_tags() && ! empty( $_POST['bbp_topic_tags'] ) ) {

		// Escape tag input
		$terms = sanitize_text_field( $_POST['bbp_topic_tags'] );

		// Explode by comma
		if ( strstr( $terms, ',' ) ) {
			$terms = explode( ',', $terms );
		}

		// Add topic tag ID as main key
		$terms = array( bbp_get_topic_tag_tax_id() => $terms );
	}

	/** Additional Actions (Before Save) **************************************/

	do_action( 'bbp_new_topic_pre_extras', $forum_id );

	// Bail if errors
	if ( bbp_has_errors() ) {
		return;
	}

	/** No Errors *************************************************************/

	// Add the content of the form to $topic_data as an array.
	// Just in time manipulation of topic data before being created
	$topic_data = apply_filters( 'bbp_new_topic_pre_insert', array(
		'post_author'    => $topic_author,
		'post_title'     => $topic_title,
		'post_content'   => $topic_content,
		'post_status'    => $topic_status,
		'post_parent'    => $forum_id,
		'post_type'      => bbp_get_topic_post_type(),
		'tax_input'      => $terms,
		'comment_status' => 'closed'
	) );

	// Insert topic
	$topic_id = wp_insert_post( $topic_data, true );

	/** No Errors *************************************************************/

	if ( ! empty( $topic_id ) && ! is_wp_error( $topic_id ) ) {

		/** Close Check *******************************************************/

		// If the topic is closed, close it properly
		if ( ( get_post_field( 'post_status', $topic_id ) === bbp_get_closed_status_id() ) || ( $topic_data['post_status'] === bbp_get_closed_status_id() ) ) {

			// Close the topic
			bbp_close_topic( $topic_id );
		}

		/** Trash Check *******************************************************/

		// If the forum is trash, or the topic_status is switched to
		// trash, trash the topic properly
		if ( ( get_post_field( 'post_status', $forum_id ) === bbp_get_trash_status_id() ) || ( $topic_data['post_status'] === bbp_get_trash_status_id() ) ) {

			// Trash the topic
			wp_trash_post( $topic_id );

			// Force view=all
			$view_all = true;
		}

		/** Spam Check ********************************************************/

		// If the topic is spam, officially spam this topic
		if ( $topic_data['post_status'] === bbp_get_spam_status_id() ) {
			add_post_meta( $topic_id, '_bbp_spam_meta_status', bbp_get_public_status_id() );

			// Force view=all
			$view_all = true;
		}

		/** Update counts, etc... *********************************************/

		do_action( 'bbp_new_topic', $topic_id, $forum_id, $anonymous_data, $topic_author );

		/** Additional Actions (After Save) ***********************************/

		do_action( 'bbp_new_topic_post_extras', $topic_id );

		/** Redirect **********************************************************/

		// Redirect to
		$redirect_to = bbp_get_redirect_to();

		// Get the topic URL
		$redirect_url = bbp_get_topic_permalink( $topic_id, $redirect_to );

		// Add view all?
		if ( bbp_get_view_all() || ! empty( $view_all ) ) {

			// User can moderate, so redirect to topic with view all set
			if ( current_user_can( 'moderate', $topic_id ) ) {
				$redirect_url = bbp_add_view_all( $redirect_url );

			// User cannot moderate, so redirect to forum
			} else {
				$redirect_url = bbp_get_forum_permalink( $forum_id );
			}
		}

		// Allow to be filtered
		$redirect_url = apply_filters( 'bbp_new_topic_redirect_to', $redirect_url, $redirect_to, $topic_id );

		/** Successful Save ***************************************************/

		// Redirect back to new topic
		bbp_redirect( $redirect_url );

	/** Errors ****************************************************************/

	// WP_Error
	} elseif ( is_wp_error( $topic_id ) ) {
		bbp_add_error( 'bbp_topic_error', sprintf( __( '<strong>Error</strong>: The following problem(s) occurred: %s', 'bbpress' ), $topic_id->get_error_message() ) );

	// Generic error
	} else {
		bbp_add_error( 'bbp_topic_error', __( '<strong>Error</strong>: The topic was not created.', 'bbpress' ) );
	}
}

/**
 * Handles the front end edit topic submission
 *
 * @param string $action The requested action to compare this function to
 */
function bbp_edit_topic_handler( $action = '' ) {

	// Bail if action is not bbp-edit-topic
	if ( 'bbp-edit-topic' !== $action ) {
		return;
	}

	// Define local variable(s)
	$revisions_removed = false;
	$topic = $topic_id = $topic_author = $forum_id = 0;
	$topic_title = $topic_content = $topic_edit_reason = '';
	$anonymous_data = array();

	/** Topic *****************************************************************/

	// Topic id was not passed
	if ( empty( $_POST['bbp_topic_id'] ) ) {
		bbp_add_error( 'bbp_edit_topic_id', __( '<strong>Error</strong>: Topic ID not found.', 'bbpress' ) );
		return;

	// Topic id was passed
	} elseif ( is_numeric( $_POST['bbp_topic_id'] ) ) {
		$topic_id = (int) $_POST['bbp_topic_id'];
		$topic    = bbp_get_topic( $topic_id );
	}

	// Topic does not exist
	if ( empty( $topic ) ) {
		bbp_add_error( 'bbp_edit_topic_not_found', __( '<strong>Error</strong>: The topic you want to edit was not found.', 'bbpress' ) );
		return;

	// Topic exists
	} else {

		// Check users ability to create new topic
		if ( ! bbp_is_topic_anonymous( $topic_id ) ) {

			// User cannot edit this topic
			if ( ! current_user_can( 'edit_topic', $topic_id ) ) {
				bbp_add_error( 'bbp_edit_topic_permission', __( '<strong>Error</strong>: You do not have permission to edit that topic.', 'bbpress' ) );
			}

			// Set topic author
			$topic_author = bbp_get_topic_author_id( $topic_id );

		// It is an anonymous post
		} else {

			// Filter anonymous data
			$anonymous_data = bbp_filter_anonymous_post_data();
		}
	}

	// Nonce check
	if ( ! bbp_verify_nonce_request( 'bbp-edit-topic_' . $topic_id ) ) {
		bbp_add_error( 'bbp_edit_topic_nonce', __( '<strong>Error</strong>: Are you sure you wanted to do that?', 'bbpress' ) );
		return;
	}

	// Remove kses filters from title and content for capable users and if the nonce is verified
	if ( current_user_can( 'unfiltered_html' ) && ! empty( $_POST['_bbp_unfiltered_html_topic'] ) && ( wp_create_nonce( 'bbp-unfiltered-html-topic_' . $topic_id ) === $_POST['_bbp_unfiltered_html_topic'] ) ) {
		remove_filter( 'bbp_edit_topic_pre_title',   'wp_filter_kses'      );
		remove_filter( 'bbp_edit_topic_pre_content', 'bbp_encode_bad',  10 );
		remove_filter( 'bbp_edit_topic_pre_content', 'bbp_filter_kses', 30 );
	}

	/** Topic Forum ***********************************************************/

	// Forum id was not passed
	if ( empty( $_POST['bbp_forum_id'] ) ) {
		bbp_add_error( 'bbp_topic_forum_id', __( '<strong>Error</strong>: Forum ID is missing.', 'bbpress' ) );

	// Forum id was passed
	} elseif ( is_numeric( $_POST['bbp_forum_id'] ) ) {
		$forum_id = (int) $_POST['bbp_forum_id'];
	}

	// Current forum this topic is in
	$current_forum_id = bbp_get_topic_forum_id( $topic_id );

	// Forum exists
	if ( ! empty( $forum_id ) && ( $forum_id !== $current_forum_id ) ) {

		// Forum is a category
		if ( bbp_is_forum_category( $forum_id ) ) {
			bbp_add_error( 'bbp_edit_topic_forum_category', __( '<strong>Error</strong>: This forum is a category. No topics can be created in it.', 'bbpress' ) );

		// Forum is not a category
		} else {

			// Forum is closed and user cannot access
			if ( bbp_is_forum_closed( $forum_id ) && ! current_user_can( 'edit_forum', $forum_id ) ) {
				bbp_add_error( 'bbp_edit_topic_forum_closed', __( '<strong>Error</strong>: This forum has been closed to new topics.', 'bbpress' ) );
			}

			// Forum is private and user cannot access
			if ( bbp_is_forum_private( $forum_id ) && ! current_user_can( 'read_forum', $forum_id ) ) {
				bbp_add_error( 'bbp_edit_topic_forum_private', __( '<strong>Error</strong>: This forum is private and you do not have the capability to read or create new topics in it.', 'bbpress' ) );

			// Forum is hidden and user cannot access
			} elseif ( bbp_is_forum_hidden( $forum_id ) && ! current_user_can( 'read_forum', $forum_id ) ) {
				bbp_add_error( 'bbp_edit_topic_forum_hidden', __( '<strong>Error</strong>: This forum is hidden and you do not have the capability to read or create new topics in it.', 'bbpress' ) );
			}
		}
	}

	/** Topic Title ***********************************************************/

	if ( ! empty( $_POST['bbp_topic_title'] ) ) {
		$topic_title = sanitize_text_field( $_POST['bbp_topic_title'] );
	}

	// Filter and sanitize
	$topic_title = apply_filters( 'bbp_edit_topic_pre_title', $topic_title, $topic_id );

	// No topic title
	if ( empty( $topic_title ) ) {
		bbp_add_error( 'bbp_edit_topic_title', __( '<strong>Error</strong>: Your topic needs a title.', 'bbpress' ) );
	}

	// Title too long
	if ( bbp_is_title_too_long( $topic_title ) ) {
		bbp_add_error( 'bbp_topic_title', __( '<strong>Error</strong>: Your title is too long.', 'bbpress' ) );
	}

	/** Topic Content *********************************************************/

	if ( ! empty( $_POST['bbp_topic_content'] ) ) {
		$topic_content = $_POST['bbp_topic_content'];
	}

	// Filter and sanitize
	$topic_content = apply_filters( 'bbp_edit_topic_pre_content', $topic_content, $topic_id );

	// No topic content
	if ( empty( $topic_content ) ) {
		bbp_add_error( 'bbp_edit_topic_content', __( '<strong>Error</strong>: Your topic cannot be empty.', 'bbpress' ) );
	}

	/** Topic Bad Words *******************************************************/

	if ( ! bbp_check_for_moderation( $anonymous_data, $topic_author, $topic_title, $topic_content, true ) ) {
		bbp_add_error( 'bbp_topic_moderation', __( '<strong>Error</strong>: Your topic cannot be edited at this time.', 'bbpress' ) );
	}

	/** Topic Status **********************************************************/

	// Get available topic statuses
	$topic_statuses = bbp_get_topic_statuses( $topic_id );

	// Use existing post_status
	$topic_status = $topic->post_status;

	// Maybe force into pending
	if ( bbp_is_topic_public( $topic->ID ) && ! bbp_check_for_moderation( $anonymous_data, $topic_author, $topic_title, $topic_content ) ) {
		$topic_status = bbp_get_pending_status_id();

	// Check for possible posted topic status
	} elseif ( ! empty( $_POST['bbp_topic_status'] ) && in_array( $_POST['bbp_topic_status'], array_keys( $topic_statuses ), true ) ) {

		// Allow capable users to explicitly override the status
		if ( current_user_can( 'moderate', $forum_id ) ) {
			$topic_status = sanitize_key( $_POST['bbp_topic_status'] );

		// Not capable
		} else {
			bbp_add_error( 'bbp_edit_topic_status', __( '<strong>Error</strong>: You do not have permission to do that.', 'bbpress' ) );
		}
	}

	/** Topic Tags ************************************************************/

	// Either replace terms
	if ( bbp_allow_topic_tags() && current_user_can( 'assign_topic_tags', $topic_id ) && ! empty( $_POST['bbp_topic_tags'] ) ) {

		// Escape tag input
		$terms = sanitize_text_field( $_POST['bbp_topic_tags'] );

		// Explode by comma
		if ( strstr( $terms, ',' ) ) {
			$terms = explode( ',', $terms );
		}

		// Add topic tag ID as main key
		$terms = array( bbp_get_topic_tag_tax_id() => $terms );

	// ...or remove them.
	} elseif ( isset( $_POST['bbp_topic_tags'] ) ) {
		$terms = array( bbp_get_topic_tag_tax_id() => array() );

	// Existing terms
	} else {
		$terms = array( bbp_get_topic_tag_tax_id() => explode( ',', bbp_get_topic_tag_names( $topic_id, ',' ) ) );
	}

	/** Additional Actions (Before Save) **************************************/

	do_action( 'bbp_edit_topic_pre_extras', $topic_id );

	// Bail if errors
	if ( bbp_has_errors() ) {
		return;
	}

	/** No Errors *************************************************************/

	// Add the content of the form to $topic_data as an array
	// Just in time manipulation of topic data before being edited
	$topic_data = apply_filters( 'bbp_edit_topic_pre_insert', array(
		'ID'           => $topic_id,
		'post_title'   => $topic_title,
		'post_content' => $topic_content,
		'post_status'  => $topic_status,
		'post_parent'  => $forum_id,
		'post_author'  => $topic_author,
		'post_type'    => bbp_get_topic_post_type(),
		'tax_input'    => $terms,
	) );

	// Toggle revisions to avoid duplicates
	if ( post_type_supports( bbp_get_topic_post_type(), 'revisions' ) ) {
		$revisions_removed = true;
		remove_post_type_support( bbp_get_topic_post_type(), 'revisions' );
	}

	// Insert topic
	$topic_id = wp_update_post( $topic_data );

	// Toggle revisions back on
	if ( true === $revisions_removed ) {
		$revisions_removed = false;
		add_post_type_support( bbp_get_topic_post_type(), 'revisions' );
	}

	/** No Errors *************************************************************/

	if ( ! empty( $topic_id ) && ! is_wp_error( $topic_id ) ) {

		// Update counts, etc...
		do_action( 'bbp_edit_topic', $topic_id, $forum_id, $anonymous_data, $topic_author , true /* Is edit */ );

		/** Revisions *********************************************************/

		// Update locks
		update_post_meta( $topic_id, '_edit_last', bbp_get_current_user_id() );
		delete_post_meta( $topic_id, '_edit_lock' );

		// Revision Reason
		if ( ! empty( $_POST['bbp_topic_edit_reason'] ) ) {
			$topic_edit_reason = sanitize_text_field( $_POST['bbp_topic_edit_reason'] );
		}

		// Update revision log
		if ( ! empty( $_POST['bbp_log_topic_edit'] ) && ( "1" === $_POST['bbp_log_topic_edit'] ) )  {
			$revision_id = wp_save_post_revision( $topic_id );
			if ( ! empty( $revision_id ) ) {
				bbp_update_topic_revision_log( array(
					'topic_id'    => $topic_id,
					'revision_id' => $revision_id,
					'author_id'   => bbp_get_current_user_id(),
					'reason'      => $topic_edit_reason
				) );
			}
		}

		/** Move Topic ********************************************************/

		// If the new forum id is not equal to the old forum id, run the
		// bbp_move_topic action and pass the topic's forum id as the
		// first arg and topic id as the second to update counts.
		if ( $forum_id !== $topic->post_parent ) {
			bbp_move_topic_handler( $topic_id, $topic->post_parent, $forum_id );
		}

		/** Additional Actions (After Save) ***********************************/

		do_action( 'bbp_edit_topic_post_extras', $topic_id );

		/** Redirect **********************************************************/

		// Redirect to
		$redirect_to = bbp_get_redirect_to();

		// View all?
		$view_all = bbp_get_view_all( 'edit_others_replies' );

		// Get the topic URL
		$topic_url = bbp_get_topic_permalink( $topic_id, $redirect_to );

		// Add view all?
		if ( ! empty( $view_all ) ) {
			$topic_url = bbp_add_view_all( $topic_url );
		}

		// Allow to be filtered
		$topic_url = apply_filters( 'bbp_edit_topic_redirect_to', $topic_url, $view_all, $redirect_to );

		/** Successful Edit ***************************************************/

		// Redirect back to new topic
		bbp_redirect( $topic_url );

	/** Errors ****************************************************************/

	} else {
		$append_error = ( is_wp_error( $topic_id ) && $topic_id->get_error_message() ) ? $topic_id->get_error_message() . ' ' : '';
		bbp_add_error( 'bbp_topic_error', __( '<strong>Error</strong>: The following problem(s) have been found with your topic:' . $append_error . 'Please try again.', 'bbpress' ) );
	}
}

/**
 * Handle all the extra meta stuff from posting a new topic
 *
 * @param int $topic_id Optional. Topic id
 * @param int $forum_id Optional. Forum id
 * @param array $anonymous_data Optional - if it's an anonymous post. Do not
 *                              supply if supplying $author_id. Should be
 *                              sanitized (see {@link bbp_filter_anonymous_post_data()}
 * @param int $author_id Author id
 * @param bool $is_edit Optional. Is the post being edited? Defaults to false.
 */
function bbp_update_topic( $topic_id = 0, $forum_id = 0, $anonymous_data = array(), $author_id = 0, $is_edit = false ) {

	// Validate the ID's passed from 'bbp_new_topic' action
	$topic_id = bbp_get_topic_id( $topic_id );
	$forum_id = bbp_get_forum_id( $forum_id );

	// Bail if there is no topic
	if ( empty( $topic_id ) ) {
		return;
	}

	// Check author_id
	if ( empty( $author_id ) ) {
		$author_id = bbp_get_current_user_id();
	}

	// Forum/Topic meta (early, for use in downstream functions)
	bbp_update_topic_forum_id( $topic_id, $forum_id );
	bbp_update_topic_topic_id( $topic_id, $topic_id );

	// Get the topic types
	$topic_types = bbp_get_topic_types( $topic_id );

	// Sticky check after 'bbp_new_topic' action so forum ID meta is set
	if ( ! empty( $_POST['bbp_stick_topic'] ) && in_array( $_POST['bbp_stick_topic'], array_keys( $topic_types ), true ) ) {

		// What's the caps?
		if ( current_user_can( 'moderate', $topic_id ) ) {

			// What's the haps?
			switch ( $_POST['bbp_stick_topic'] ) {

				// Sticky in this forum
				case 'stick'   :
					bbp_stick_topic( $topic_id );
					break;

				// Super sticky in all forums
				case 'super'   :
					bbp_stick_topic( $topic_id, true );
					break;

				// Unsticky from everywhere
				case 'unstick' :
				default        :
					bbp_unstick_topic( $topic_id );
					break;
			}
		}
	}

	// If anonymous post, store name, email, website and ip in post_meta.
	if ( ! empty( $anonymous_data ) ) {

		// Update anonymous meta data (not cookies)
		bbp_update_anonymous_post_author( $topic_id, $anonymous_data, bbp_get_topic_post_type() );

		// Set transient for throttle check (only on new, not edit)
		if ( empty( $is_edit ) ) {
			set_transient( '_bbp_' . bbp_current_author_ip() . '_last_posted', time(), HOUR_IN_SECONDS );
		}
	}

	// Handle Subscription Checkbox
	if ( bbp_is_subscriptions_active() && ! empty( $author_id ) ) {

		// Check if subscribed
		$subscribed = bbp_is_user_subscribed( $author_id, $topic_id );

		// Check for action
		$subscheck  = ( ! empty( $_POST['bbp_topic_subscription'] ) && ( 'bbp_subscribe' === $_POST['bbp_topic_subscription'] ) )
			? true
			: false;

		// Subscribed and unsubscribing
		if ( ( true === $subscribed ) && ( false === $subscheck ) ) {
			bbp_remove_user_subscription( $author_id, $topic_id );

		// Not subscribed and subscribing
		} elseif ( ( false === $subscribed ) && ( true === $subscheck ) ) {
			bbp_add_user_subscription( $author_id, $topic_id );
		}
	}

	// Update associated topic values if this is a new topic
	if ( empty( $is_edit ) ) {

		// Update poster activity time
		bbp_update_user_last_posted( $author_id );

		// Update poster IP
		update_post_meta( $topic_id, '_bbp_author_ip', bbp_current_author_ip(), false );

		// Last active time
		$last_active = get_post_field( 'post_date', $topic_id );

		// Reply topic meta
		bbp_update_topic_last_reply_id      ( $topic_id, 0            );
		bbp_update_topic_last_active_id     ( $topic_id, $topic_id    );
		bbp_update_topic_last_active_time   ( $topic_id, $last_active );
		bbp_update_topic_reply_count        ( $topic_id, 0            );
		bbp_update_topic_reply_count_hidden ( $topic_id, 0            );
		bbp_update_topic_voice_count        ( $topic_id               );

		// Walk up ancestors and do the dirty work
		bbp_update_topic_walker( $topic_id, $last_active, $forum_id, 0, false );
	}

	// Bump the custom query cache
	wp_cache_set( 'last_changed', microtime(), 'bbpress_posts' );
}

/**
 * Walks up the post_parent tree from the current topic_id, and updates the
 * meta data of forums above it. This calls several functions that all run
 * manual queries against the database to get their results. As such, this
 * function can be costly to run but is necessary to keep everything accurate.
 *
 * @since 2.0.0 bbPress (r2800)
 *
 * @param int $topic_id Topic id
 * @param string $last_active_time Optional. Last active time
 * @param int $forum_id Optional. Forum id
 * @param int $reply_id Optional. Reply id
 * @param bool $refresh Reset all the previous parameters? Defaults to true.
 */
function bbp_update_topic_walker( $topic_id, $last_active_time = '', $forum_id = 0, $reply_id = 0, $refresh = true ) {

	// Validate topic_id
	$topic_id  = bbp_get_topic_id( $topic_id );

	// Define local variable(s)
	$active_id = 0;

	// Topic was passed
	if ( ! empty( $topic_id ) ) {

		// Get the forum ID if none was passed
		if ( empty( $forum_id )  ) {
			$forum_id = bbp_get_topic_forum_id( $topic_id );
		}

		// Set the active_id based on topic_id/reply_id
		$active_id = empty( $reply_id ) ? $topic_id : $reply_id;
	}

	// Get topic ancestors
	$ancestors = array_values( array_unique( array_merge( array( $forum_id ), (array) get_post_ancestors( $topic_id ) ) ) );

	// Topic status
	$topic_status = get_post_status( $topic_id );

	// If we want a full refresh, unset any of the possibly passed variables
	if ( true === $refresh ) {
		$forum_id = $topic_id = $reply_id = $active_id = $last_active_time = 0;
		$topic_status = bbp_get_public_status_id();
	}

	// Loop through ancestors
	if ( ! empty( $ancestors ) ) {
		foreach ( $ancestors as $ancestor ) {

			// If ancestor is a forum, update counts
			if ( bbp_is_forum( $ancestor ) ) {

				// Get the forum
				$forum = bbp_get_forum( $ancestor );

				// Update the forum
				bbp_update_forum( array(
					'forum_id'           => $forum->ID,
					'post_parent'        => $forum->post_parent,
					'last_topic_id'      => $topic_id,
					'last_reply_id'      => $reply_id,
					'last_active_id'     => $active_id,
					'last_active_time'   => $last_active_time,
					'last_active_status' => $topic_status
				) );
			}
		}
	}
}

/**
 * Handle the moving of a topic from one forum to another. This includes walking
 * up the old and new branches and updating the counts.
 *
 * @since 2.0.0 bbPress (r2907)
 *
 * @param int $topic_id     The topic id.
 * @param int $old_forum_id Old forum id.
 * @param int $new_forum_id New forum id.
 */
function bbp_move_topic_handler( $topic_id, $old_forum_id, $new_forum_id ) {

	// Validate parameters
	$topic_id     = bbp_get_topic_id( $topic_id     );
	$old_forum_id = bbp_get_forum_id( $old_forum_id );
	$new_forum_id = bbp_get_forum_id( $new_forum_id );

	// Clean old and new forum caches before proceeding, to ensure subsequent
	// calls to forum objects are using updated data.
	clean_post_cache( $old_forum_id );
	clean_post_cache( $new_forum_id );

	// Update topic forum's ID
	bbp_update_topic_forum_id( $topic_id, $new_forum_id );

	// Update topic post parent with the new forum ID
	wp_update_post( array(
		'ID'          => $topic_id,
		'post_parent' => $new_forum_id,
	) );

	/** Stickies **************************************************************/

	// Get forum stickies
	$old_stickies = bbp_get_stickies( $old_forum_id );

	// Only proceed if stickies are found
	if ( ! empty( $old_stickies ) ) {

		// Define local variables
		$updated_stickies = array();

		// Loop through stickies of forum and add misses to the updated array
		foreach ( (array) $old_stickies as $sticky_topic_id ) {
			if ( $topic_id !== $sticky_topic_id ) {
				$updated_stickies[] = $sticky_topic_id;
			}
		}

		// If stickies are different, update or delete them
		if ( $updated_stickies !== $old_stickies ) {

			// No more stickies so delete the meta
			if ( empty( $updated_stickies ) ) {
				delete_post_meta( $old_forum_id, '_bbp_sticky_topics' );

			// Still stickies so update the meta
			} else {
				update_post_meta( $old_forum_id, '_bbp_sticky_topics', $updated_stickies );
			}

			// Topic was sticky, so restick in new forum
			bbp_stick_topic( $topic_id );
		}
	}

	/** Topic Replies *********************************************************/

	// Get the topics replies
	$replies = bbp_get_all_child_ids( $topic_id, bbp_get_reply_post_type() );

	// Update the forum_id of all replies in the topic
	foreach ( $replies as $reply_id ) {
		bbp_update_reply_forum_id( $reply_id, $new_forum_id );
	}

	/** Old forum_id **********************************************************/

	// Get topic ancestors
	$old_forum_ancestors = array_values( array_unique( array_merge( array( $old_forum_id ), (array) get_post_ancestors( $old_forum_id ) ) ) );

	// Public counts
	if ( bbp_is_topic_public( $topic_id ) ) {

		// Update old forum counts.
		bbp_decrease_forum_topic_count( $old_forum_id );

		// Update new forum counts.
		bbp_increase_forum_topic_count( $new_forum_id );

	// Non-public counts
	} else {

		// Update old forum counts.
		bbp_decrease_forum_topic_count_hidden( $old_forum_id );

		// Update new forum counts.
		bbp_increase_forum_topic_count_hidden( $new_forum_id );
	}

	// Get reply counts.
	$public_reply_count = bbp_get_public_child_count( $topic_id, bbp_get_reply_post_type() );
	$hidden_reply_count = bbp_get_non_public_child_count( $topic_id, bbp_get_reply_post_type() );

	// Bump reply counts.
	bbp_bump_forum_reply_count( $old_forum_id, -$public_reply_count );
	bbp_bump_forum_reply_count( $new_forum_id, $public_reply_count );
	bbp_bump_forum_reply_count_hidden( $old_forum_id, -$hidden_reply_count );
	bbp_bump_forum_reply_count_hidden( $new_forum_id, $hidden_reply_count );

	// Loop through ancestors and update them
	if ( ! empty( $old_forum_ancestors ) ) {
		foreach ( $old_forum_ancestors as $ancestor ) {
			if ( bbp_is_forum( $ancestor ) ) {
				bbp_update_forum( array(
					'forum_id' => $ancestor,
				) );
			}
		}
	}

	/** New forum_id **********************************************************/

	// Make sure we're not walking twice
	if ( ! in_array( $new_forum_id, $old_forum_ancestors, true ) ) {

		// Get topic ancestors
		$new_forum_ancestors = array_values( array_unique( array_merge( array( $new_forum_id ), (array) get_post_ancestors( $new_forum_id ) ) ) );

		// Make sure we're not walking twice
		$new_forum_ancestors = array_diff( $new_forum_ancestors, $old_forum_ancestors );

		// Loop through ancestors and update them
		if ( ! empty( $new_forum_ancestors ) ) {
			foreach ( $new_forum_ancestors as $ancestor ) {
				if ( bbp_is_forum( $ancestor ) ) {
					bbp_update_forum( array(
						'forum_id' => $ancestor,
					) );
				}
			}
		}
	}
}

/**
 * Merge topic handler
 *
 * Handles the front end merge topic submission
 *
 * @since 2.0.0 bbPress (r2756)
 *
 * @param string $action The requested action to compare this function to
 */
function bbp_merge_topic_handler( $action = '' ) {

	// Bail if action is not bbp-merge-topic
	if ( 'bbp-merge-topic' !== $action ) {
		return;
	}

	// Define local variable(s)
	$source_topic_id = $destination_topic_id = 0;
	$source_topic = $destination_topic = 0;
	$subscribers = $favoriters = $replies = array();

	/** Source Topic **********************************************************/

	// Topic id
	if ( empty( $_POST['bbp_topic_id'] ) ) {
		bbp_add_error( 'bbp_merge_topic_source_id', __( '<strong>Error</strong>: Topic ID not found.', 'bbpress' ) );
	} else {
		$source_topic_id = (int) $_POST['bbp_topic_id'];
	}

	// Nonce check
	if ( ! bbp_verify_nonce_request( 'bbp-merge-topic_' . $source_topic_id ) ) {
		bbp_add_error( 'bbp_merge_topic_nonce', __( '<strong>Error</strong>: Are you sure you wanted to do that?', 'bbpress' ) );
		return;

	// Source topic not found
	} elseif ( ! $source_topic = bbp_get_topic( $source_topic_id ) ) {
		bbp_add_error( 'bbp_merge_topic_source_not_found', __( '<strong>Error</strong>: The topic you want to merge was not found.', 'bbpress' ) );
		return;
	}

	// Cannot edit source topic
	if ( ! current_user_can( 'edit_topic', $source_topic->ID ) ) {
		bbp_add_error( 'bbp_merge_topic_source_permission', __( '<strong>Error</strong>: You do not have permission to edit the source topic.', 'bbpress' ) );
		return;
	}

	/** Destination Topic *****************************************************/

	// Topic id
	if ( empty( $_POST['bbp_destination_topic'] ) ) {
		bbp_add_error( 'bbp_merge_topic_destination_id', __( '<strong>Error</strong>: Destination topic ID not found.', 'bbpress' ) );
	} else {
		$destination_topic_id = (int) $_POST['bbp_destination_topic'];
	}

	// Destination topic not found
	if ( ! $destination_topic = bbp_get_topic( $destination_topic_id ) ) {
		bbp_add_error( 'bbp_merge_topic_destination_not_found', __( '<strong>Error</strong>: The topic you want to merge to was not found.', 'bbpress' ) );
	}

	// Cannot edit destination topic
	if ( ! current_user_can( 'edit_topic', $destination_topic->ID ) ) {
		bbp_add_error( 'bbp_merge_topic_destination_permission', __( '<strong>Error</strong>: You do not have permission to edit the destination topic.', 'bbpress' ) );
	}

	// Bail if errors
	if ( bbp_has_errors() ) {
		return;
	}

	/** No Errors *************************************************************/

	// Update counts, etc...
	do_action( 'bbp_merge_topic', $destination_topic->ID, $source_topic->ID );

	/** Date Check ************************************************************/

	// Check if the destination topic is older than the source topic
	if ( strtotime( $source_topic->post_date ) < strtotime( $destination_topic->post_date ) ) {

		// Set destination topic post_date to 1 second before source topic
		$destination_post_date = date( 'Y-m-d H:i:s', strtotime( $source_topic->post_date ) - 1 );

		// Update destination topic
		wp_update_post( array(
			'ID'            => $destination_topic_id,
			'post_date'     => $destination_post_date,
			'post_date_gmt' => get_gmt_from_date( $destination_post_date )
		) );
	}

	/** Engagements ***********************************************************/

	// Get engagements from source topic
	$engagements = bbp_get_topic_engagements( $source_topic->ID );

	// Maybe migrate engagements
	if ( ! empty( $engagements ) ) {
		foreach ( $engagements as $engager ) {
			bbp_add_user_engagement( $engager, $destination_topic->ID );
		}
	}

	/** Subscriptions *********************************************************/

	// Get subscribers from source topic
	$subscribers = bbp_get_subscribers( $source_topic->ID );

	// Maybe migrate subscriptions
	if ( ! empty( $subscribers ) && ! empty( $_POST['bbp_topic_subscribers'] ) && ( '1' === $_POST['bbp_topic_subscribers'] ) ) {
		foreach ( $subscribers as $subscriber ) {
			bbp_add_user_subscription( $subscriber, $destination_topic->ID );
		}
	}

	/** Favorites *************************************************************/

	// Get favoriters from source topic
	$favoriters = bbp_get_topic_favoriters( $source_topic->ID );

	// Maybe migrate favorites
	if ( ! empty( $favoriters ) && ! empty( $_POST['bbp_topic_favoriters'] ) && ( '1' === $_POST['bbp_topic_favoriters'] ) ) {
		foreach ( $favoriters as $favoriter ) {
			bbp_add_user_favorite( $favoriter, $destination_topic->ID );
		}
	}

	/** Tags ******************************************************************/

	// Get the source topic tags
	$source_topic_tags = wp_get_post_terms( $source_topic->ID, bbp_get_topic_tag_tax_id(), array( 'fields' => 'names' ) );

	// Tags to possibly merge
	if ( ! empty( $source_topic_tags ) && ! is_wp_error( $source_topic_tags ) ) {

		// Shift the tags if told to
		if ( ! empty( $_POST['bbp_topic_tags'] ) && ( "1" === $_POST['bbp_topic_tags'] ) ) {
			wp_set_post_terms( $destination_topic->ID, $source_topic_tags, bbp_get_topic_tag_tax_id(), true );
		}

		// Delete the tags from the source topic
		wp_delete_object_term_relationships( $source_topic->ID, bbp_get_topic_tag_tax_id() );
	}

	/** Source Topic **********************************************************/

	// Status
	bbp_open_topic( $source_topic->ID );

	// Sticky
	bbp_unstick_topic( $source_topic->ID );

	// Delete source topic's last & count meta data
	delete_post_meta( $source_topic->ID, '_bbp_last_reply_id'      );
	delete_post_meta( $source_topic->ID, '_bbp_last_active_id'     );
	delete_post_meta( $source_topic->ID, '_bbp_last_active_time'   );
	delete_post_meta( $source_topic->ID, '_bbp_voice_count'        );
	delete_post_meta( $source_topic->ID, '_bbp_reply_count'        );
	delete_post_meta( $source_topic->ID, '_bbp_reply_count_hidden' );

	// Delete source topics user relationships
	delete_post_meta( $source_topic->ID, '_bbp_favorite'     );
	delete_post_meta( $source_topic->ID, '_bbp_subscription' );
	delete_post_meta( $source_topic->ID, '_bbp_engagement'   );

	// Get the replies of the source topic
	$replies = (array) get_posts( array(
		'post_parent'    => $source_topic->ID,
		'post_type'      => bbp_get_reply_post_type(),
		'posts_per_page' => -1,
		'order'          => 'ASC'
	) );

	// Prepend the source topic to its replies array for processing
	array_unshift( $replies, $source_topic );

	if ( ! empty( $replies ) ) {

		/** Merge Replies *****************************************************/

		// Change the post_parent of each reply to the destination topic id
		foreach ( $replies as $reply ) {

			// Update the reply
			wp_update_post( array(
				'ID'          => $reply->ID,
				'post_title'  => '',
				'post_name'   => false,
				'post_type'   => bbp_get_reply_post_type(),
				'post_parent' => $destination_topic->ID,
				'guid'        => ''
			) );

			// Adjust reply meta values
			bbp_update_reply_topic_id( $reply->ID, $destination_topic->ID                           );
			bbp_update_reply_forum_id( $reply->ID, bbp_get_topic_forum_id( $destination_topic->ID ) );

			// Update the reply position
			bbp_update_reply_position( $reply->ID );

			// Do additional actions per merged reply
			do_action( 'bbp_merged_topic_reply', $reply->ID, $destination_topic->ID );
		}
	}

	/** Successful Merge ******************************************************/

	// Update topic's last meta data
	bbp_update_topic_last_reply_id   ( $destination_topic->ID );
	bbp_update_topic_last_active_id  ( $destination_topic->ID );
	bbp_update_topic_last_active_time( $destination_topic->ID );

	// Send the post parent of the source topic as it has been shifted
	// (possibly to a new forum) so we need to update the counts of the
	// old forum as well as the new one
	do_action( 'bbp_merged_topic', $destination_topic->ID, $source_topic->ID, $source_topic->post_parent );

	// Redirect back to new topic
	bbp_redirect( bbp_get_topic_permalink( $destination_topic->ID ) );
}

/**
 * Fix counts on topic merge
 *
 * When a topic is merged, update the counts of source and destination topic
 * and their forums.
 *
 * @since 2.0.0 bbPress (r2756)
 *
 * @param int $destination_topic_id Destination topic id
 * @param int $source_topic_id Source topic id
 * @param int $source_topic_forum_id Source topic's forum id
 */
function bbp_merge_topic_count( $destination_topic_id, $source_topic_id, $source_topic_forum_id ) {

	/** Source Topic **********************************************************/

	// Forum Topic Counts
	bbp_update_forum_topic_count( $source_topic_forum_id );

	// Forum Reply Counts
	bbp_update_forum_reply_count( $source_topic_forum_id );

	/** Destination Topic *****************************************************/

	// Topic Reply Counts
	bbp_update_topic_reply_count( $destination_topic_id );

	// Topic Hidden Reply Counts
	bbp_update_topic_reply_count_hidden( $destination_topic_id );

	// Topic Voice Counts
	bbp_update_topic_voice_count( $destination_topic_id );

	do_action( 'bbp_merge_topic_count', $destination_topic_id, $source_topic_id, $source_topic_forum_id );
}

/**
 * Split topic handler
 *
 * Handles the front end split topic submission
 *
 * @since 2.0.0 bbPress (r2756)
 *
 * @param string $action The requested action to compare this function to
 */
function bbp_split_topic_handler( $action = '' ) {

	// Bail if action is not 'bbp-split-topic'
	if ( 'bbp-split-topic' !== $action ) {
		return;
	}

	// Prevent debug notices
	$from_reply_id = $destination_topic_id = 0;
	$destination_topic_title = '';
	$destination_topic = $from_reply = $source_topic = '';
	$split_option = false;

	/** Split Reply ***********************************************************/

	if ( empty( $_POST['bbp_reply_id'] ) ) {
		bbp_add_error( 'bbp_split_topic_reply_id', __( '<strong>Error</strong>: A reply ID is required.', 'bbpress' ) );
	} else {
		$from_reply_id = (int) $_POST['bbp_reply_id'];
	}

	$from_reply = bbp_get_reply( $from_reply_id );

	// Reply exists
	if ( empty( $from_reply ) ) {
		bbp_add_error( 'bbp_split_topic_r_not_found', __( '<strong>Error</strong>: The reply you want to split from was not found.', 'bbpress' ) );
	}

	/** Topic to Split ********************************************************/

	// Get the topic being split
	$source_topic = bbp_get_topic( $from_reply->post_parent );

	// No topic
	if ( empty( $source_topic ) ) {
		bbp_add_error( 'bbp_split_topic_source_not_found', __( '<strong>Error</strong>: The topic you want to split was not found.', 'bbpress' ) );
	}

	// Nonce check failed
	if ( ! bbp_verify_nonce_request( 'bbp-split-topic_' . $source_topic->ID ) ) {
		bbp_add_error( 'bbp_split_topic_nonce', __( '<strong>Error</strong>: Are you sure you wanted to do that?', 'bbpress' ) );
		return;
	}

	// Use cannot edit topic
	if ( ! current_user_can( 'edit_topic', $source_topic->ID ) ) {
		bbp_add_error( 'bbp_split_topic_source_permission', __( '<strong>Error</strong>: You do not have permission to edit the source topic.', 'bbpress' ) );
	}

	// How to Split
	if ( ! empty( $_POST['bbp_topic_split_option'] ) ) {
		$split_option = sanitize_key( $_POST['bbp_topic_split_option'] );
	}

	// Invalid split option
	if ( empty( $split_option ) || ! in_array( $split_option, array( 'existing', 'reply' ), true ) ) {
		bbp_add_error( 'bbp_split_topic_option', __( '<strong>Error</strong>: You need to choose a valid split option.', 'bbpress' ) );

	// Valid Split Option
	} else {

		// What kind of split
		switch ( $split_option ) {

			// Into an existing topic
			case 'existing' :

				// Get destination topic id
				if ( empty( $_POST['bbp_destination_topic'] ) ) {
					bbp_add_error( 'bbp_split_topic_destination_id', __( '<strong>Error</strong>: A topic ID is required.', 'bbpress' ) );
				} else {
					$destination_topic_id = (int) $_POST['bbp_destination_topic'];
				}

				// Get the destination topic
				$destination_topic = bbp_get_topic( $destination_topic_id );

				// No destination topic
				if ( empty( $destination_topic ) ) {
					bbp_add_error( 'bbp_split_topic_destination_not_found', __( '<strong>Error</strong>: The topic you want to split to was not found.', 'bbpress' ) );
				}

				// User cannot edit the destination topic
				if ( ! current_user_can( 'edit_topic', $destination_topic->ID ) ) {
					bbp_add_error( 'bbp_split_topic_destination_permission', __( '<strong>Error</strong>: You do not have permission to edit the destination topic.', 'bbpress' ) );
				}

				break;

			// Split at reply into a new topic
			case 'reply' :
			default :

				// User needs to be able to publish topics
				if ( current_user_can( 'publish_topics' ) ) {

					// Use the new title that was passed
					if ( ! empty( $_POST['bbp_topic_split_destination_title'] ) ) {
						$destination_topic_title = sanitize_text_field( $_POST['bbp_topic_split_destination_title'] );

					// Use the source topic title
					} else {
						$destination_topic_title = $source_topic->post_title;
					}

					// Update the topic
					$destination_topic_id = wp_update_post( array(
						'ID'          => $from_reply->ID,
						'post_title'  => $destination_topic_title,
						'post_name'   => false,
						'post_type'   => bbp_get_topic_post_type(),
						'post_parent' => $source_topic->post_parent,
						'menu_order'  => 0,
						'guid'        => ''
					) );
					$destination_topic = bbp_get_topic( $destination_topic_id );

					// Make sure the new topic knows its a topic
					bbp_update_topic_topic_id( $from_reply->ID );

					// Shouldn't happen
					if ( false === $destination_topic_id || is_wp_error( $destination_topic_id ) || empty( $destination_topic ) ) {
						bbp_add_error( 'bbp_split_topic_destination_reply', __( '<strong>Error</strong>: There was a problem converting the reply into the topic. Please try again.', 'bbpress' ) );
					}

				// User cannot publish posts
				} else {
					bbp_add_error( 'bbp_split_topic_destination_permission', __( '<strong>Error</strong>: You do not have permission to create new topics. The reply could not be converted into a topic.', 'bbpress' ) );
				}

				break;
		}
	}

	// Bail if there are errors
	if ( bbp_has_errors() ) {
		return;
	}

	/** No Errors - Do the Spit ***********************************************/

	// Update counts, etc...
	do_action( 'bbp_pre_split_topic', $from_reply->ID, $source_topic->ID, $destination_topic->ID );

	/** Date Check ************************************************************/

	// Check if the destination topic is older than the from reply
	if ( strtotime( $from_reply->post_date ) < strtotime( $destination_topic->post_date ) ) {

		// Set destination topic post_date to 1 second before from reply
		$destination_post_date = date( 'Y-m-d H:i:s', strtotime( $from_reply->post_date ) - 1 );

		// Update destination topic
		wp_update_post( array(
			'ID'            => $destination_topic_id,
			'post_date'     => $destination_post_date,
			'post_date_gmt' => get_gmt_from_date( $destination_post_date )
		) );
	}

	/** Subscriptions *********************************************************/

	// Copy the subscribers
	if ( ! empty( $_POST['bbp_topic_subscribers'] ) && "1" === $_POST['bbp_topic_subscribers'] && bbp_is_subscriptions_active() ) {

		// Get the subscribers
		$subscribers = bbp_get_subscribers( $source_topic->ID );

		if ( ! empty( $subscribers ) ) {

			// Add subscribers to new topic
			foreach ( (array) $subscribers as $subscriber ) {
				bbp_add_user_subscription( $subscriber, $destination_topic->ID );
			}
		}
	}

	/** Favorites *************************************************************/

	// Copy the favoriters if told to
	if ( ! empty( $_POST['bbp_topic_favoriters'] ) && ( "1" === $_POST['bbp_topic_favoriters'] ) ) {

		// Get the favoriters
		$favoriters = bbp_get_topic_favoriters( $source_topic->ID );

		if ( ! empty( $favoriters ) ) {

			// Add the favoriters to new topic
			foreach ( (array) $favoriters as $favoriter ) {
				bbp_add_user_favorite( $favoriter, $destination_topic->ID );
			}
		}
	}

	/** Tags ******************************************************************/

	// Copy the tags if told to
	if ( ! empty( $_POST['bbp_topic_tags'] ) && ( "1" === $_POST['bbp_topic_tags'] ) ) {

		// Get the source topic tags
		$source_topic_tags = wp_get_post_terms( $source_topic->ID, bbp_get_topic_tag_tax_id(), array( 'fields' => 'names' ) );

		if ( ! empty( $source_topic_tags ) ) {
			wp_set_post_terms( $destination_topic->ID, $source_topic_tags, bbp_get_topic_tag_tax_id(), true );
		}
	}

	/** Split Replies *********************************************************/

	// get_posts() is not used because it doesn't allow us to use '>='
	// comparision without a filter.
	$bbp_db  = bbp_db();
	$query   = $bbp_db->prepare( "SELECT * FROM {$bbp_db->posts} WHERE {$bbp_db->posts}.post_date >= %s AND {$bbp_db->posts}.post_parent = %d AND {$bbp_db->posts}.post_type = %s ORDER BY {$bbp_db->posts}.post_date ASC", $from_reply->post_date, $source_topic->ID, bbp_get_reply_post_type() );
	$replies = (array) $bbp_db->get_results( $query );

	// Make sure there are replies to loop through
	if ( ! empty( $replies ) && ! is_wp_error( $replies ) ) {

		// Save reply ids
		$reply_ids = array();

		// Change the post_parent of each reply to the destination topic id
		foreach ( $replies as $reply ) {

			// Update the reply
			wp_update_post( array(
				'ID'          => $reply->ID,
				'post_title'  => '',
				'post_name'   => false, // will be automatically generated
				'post_parent' => $destination_topic->ID,
				'guid'        => ''
			) );

			// Gather reply ids
			$reply_ids[] = $reply->ID;

			// Adjust reply meta values
			bbp_update_reply_topic_id( $reply->ID, $destination_topic->ID                           );
			bbp_update_reply_forum_id( $reply->ID, bbp_get_topic_forum_id( $destination_topic->ID ) );

			// Adjust reply position
			bbp_update_reply_position( $reply->ID );

			// Adjust reply to values
			$reply_to = bbp_get_reply_to( $reply->ID );

			// Not a reply to a reply that moved over
			if ( ! in_array( $reply_to, $reply_ids, true ) ) {
				bbp_update_reply_to( $reply->ID, 0 );
			}

			// New topic from reply can't be a reply to
			if ( ( $from_reply->ID === $destination_topic->ID ) && ( $from_reply->ID === $reply_to ) ) {
				bbp_update_reply_to( $reply->ID, 0 );
			}

			// Do additional actions per split reply
			do_action( 'bbp_split_topic_reply', $reply->ID, $destination_topic->ID );
		}

		// Remove reply to from new topic
		if ( $from_reply->ID === $destination_topic->ID ) {
			delete_post_meta( $from_reply->ID, '_bbp_reply_to' );
		}

		// Set the last reply ID and freshness
		$last_reply_id = $reply->ID;
		$freshness     = $reply->post_date;

	// Set the last reply ID and freshness to the from_reply
	} else {
		$last_reply_id = $from_reply->ID;
		$freshness     = $from_reply->post_date;
	}

	// It is a new topic and we need to set some default metas to make
	// the topic display in bbp_has_topics() list
	if ( 'reply' === $split_option ) {
		bbp_update_topic_last_reply_id   ( $destination_topic->ID, $last_reply_id );
		bbp_update_topic_last_active_id  ( $destination_topic->ID, $last_reply_id );
		bbp_update_topic_last_active_time( $destination_topic->ID, $freshness     );
	}

	// Update source topic ID last active
	bbp_update_topic_last_reply_id   ( $source_topic->ID );
	bbp_update_topic_last_active_id  ( $source_topic->ID );
	bbp_update_topic_last_active_time( $source_topic->ID );

	/** Successful Split ******************************************************/

	// Update counts, etc...
	do_action( 'bbp_post_split_topic', $from_reply->ID, $source_topic->ID, $destination_topic->ID );

	// Redirect back to the topic
	bbp_redirect( bbp_get_topic_permalink( $destination_topic->ID ) );
}

/**
 * Fix counts on topic split
 *
 * When a topic is split, update the counts of source and destination topic
 * and their forums.
 *
 * @since 2.0.0 bbPress (r2756)
 *
 * @param int $from_reply_id From reply id
 * @param int $source_topic_id Source topic id
 * @param int $destination_topic_id Destination topic id
 */
function bbp_split_topic_count( $from_reply_id, $source_topic_id, $destination_topic_id ) {

	// Forum Topic Counts
	bbp_update_forum_topic_count( bbp_get_topic_forum_id( $destination_topic_id ) );

	// Forum Reply Counts
	bbp_update_forum_reply_count( bbp_get_topic_forum_id( $destination_topic_id ) );

	// Topic Reply Counts
	bbp_update_topic_reply_count( $source_topic_id      );
	bbp_update_topic_reply_count( $destination_topic_id );

	// Topic Hidden Reply Counts
	bbp_update_topic_reply_count_hidden( $source_topic_id      );
	bbp_update_topic_reply_count_hidden( $destination_topic_id );

	// Topic Voice Counts
	bbp_update_topic_voice_count( $source_topic_id      );
	bbp_update_topic_voice_count( $destination_topic_id );

	do_action( 'bbp_split_topic_count', $from_reply_id, $source_topic_id, $destination_topic_id );
}

/**
 * Handles the front end tag management (renaming, merging, destroying)
 *
 * @since 2.0.0 bbPress (r2768)
 *
 * @param string $action The requested action to compare this function to
 */
function bbp_edit_topic_tag_handler( $action = '' ) {

	// Bail if required POST actions aren't passed
	if ( empty( $_POST['tag-id'] ) ) {
		return;
	}

	// Setup possible get actions
	$possible_actions = array(
		'bbp-update-topic-tag',
		'bbp-merge-topic-tag',
		'bbp-delete-topic-tag'
	);

	// Bail if actions aren't meant for this function
	if ( ! in_array( $action, $possible_actions, true ) ) {
		return;
	}

	// Setup vars
	$tag_id = (int) $_POST['tag-id'];
	$tag    = get_term( $tag_id, bbp_get_topic_tag_tax_id() );

	// Tag does not exist
	if ( is_wp_error( $tag ) && $tag->get_error_message() ) {
		bbp_add_error( 'bbp_manage_topic_invalid_tag', sprintf( __( '<strong>Error</strong>: The following problem(s) have been found while getting the tag: %s', 'bbpress' ), $tag->get_error_message() ) );
		return;
	}

	// What action are we trying to perform?
	switch ( $action ) {

		// Update tag
		case 'bbp-update-topic-tag' :

			// Nonce check
			if ( ! bbp_verify_nonce_request( 'update-tag_' . $tag_id ) ) {
				bbp_add_error( 'bbp_manage_topic_tag_update_nonce', __( '<strong>Error</strong>: Are you sure you wanted to do that?', 'bbpress' ) );
				return;
			}

			// Can user edit topic tags?
			if ( ! current_user_can( 'edit_topic_tag', $tag_id ) ) {
				bbp_add_error( 'bbp_manage_topic_tag_update_permission', __( '<strong>Error</strong>: You do not have permission to edit the topic tags.', 'bbpress' ) );
				return;
			}

			// No tag name was provided
			if ( empty( $_POST['tag-name'] ) || ! $name = $_POST['tag-name'] ) {
				bbp_add_error( 'bbp_manage_topic_tag_update_name', __( '<strong>Error</strong>: You need to enter a tag name.', 'bbpress' ) );
				return;
			}

			// Attempt to update the tag
			$slug        = ! empty( $_POST['tag-slug']        ) ? $_POST['tag-slug']        : '';
			$description = ! empty( $_POST['tag-description'] ) ? $_POST['tag-description'] : '';
			$tag         = wp_update_term( $tag_id, bbp_get_topic_tag_tax_id(), array(
				'name'        => $name,
				'slug'        => $slug,
				'description' => $description
			) );

			// Cannot update tag
			if ( is_wp_error( $tag ) && $tag->get_error_message() ) {
				bbp_add_error( 'bbp_manage_topic_tag_update_error', sprintf( __( '<strong>Error</strong>: The following problem(s) have been found while updating the tag: %s', 'bbpress' ), $tag->get_error_message() ) );
				return;
			}

			// Redirect
			$redirect = get_term_link( $tag_id, bbp_get_topic_tag_tax_id() );

			// Update counts, etc...
			do_action( 'bbp_update_topic_tag', $tag_id, $tag, $name, $slug, $description );

			break;

		// Merge two tags
		case 'bbp-merge-topic-tag'  :

			// Nonce check
			if ( ! bbp_verify_nonce_request( 'merge-tag_' . $tag_id ) ) {
				bbp_add_error( 'bbp_manage_topic_tag_merge_nonce', __( '<strong>Error</strong>: Are you sure you wanted to do that?', 'bbpress' ) );
				return;
			}

			// Can user edit topic tags?
			if ( ! current_user_can( 'edit_topic_tags' ) ) {
				bbp_add_error( 'bbp_manage_topic_tag_merge_permission', __( '<strong>Error</strong>: You do not have permission to edit the topic tags.', 'bbpress' ) );
				return;
			}

			// No tag name was provided
			if ( empty( $_POST['tag-existing-name'] ) || ! $name = $_POST['tag-existing-name'] ) {
				bbp_add_error( 'bbp_manage_topic_tag_merge_name', __( '<strong>Error</strong>: You need to enter a tag name.', 'bbpress' ) );
				return;
			}

			// If term does not exist, create it
			if ( ! $tag = term_exists( $name, bbp_get_topic_tag_tax_id() ) ) {
				$tag = wp_insert_term( $name, bbp_get_topic_tag_tax_id() );
			}

			// Problem inserting the new term
			if ( is_wp_error( $tag ) && $tag->get_error_message() ) {
				bbp_add_error( 'bbp_manage_topic_tag_merge_error', sprintf( __( '<strong>Error</strong>: The following problem(s) have been found while merging the tags: %s', 'bbpress' ), $tag->get_error_message() ) );
				return;
			}

			// Merging in to...
			$to_tag = $tag['term_id'];

			// Attempting to merge a tag into itself
			if ( $tag_id === $to_tag ) {
				bbp_add_error( 'bbp_manage_topic_tag_merge_same', __( '<strong>Error</strong>: The tags which are being merged can not be the same.', 'bbpress' ) );
				return;
			}

			// Delete the old term
			$tag = wp_delete_term( $tag_id, bbp_get_topic_tag_tax_id(), array(
				'default'       => $to_tag,
				'force_default' => true
			) );

			// Error merging the terms
			if ( is_wp_error( $tag ) && $tag->get_error_message() ) {
				bbp_add_error( 'bbp_manage_topic_tag_merge_error', sprintf( __( '<strong>Error</strong>: The following problem(s) have been found while merging the tags: %s', 'bbpress' ), $tag->get_error_message() ) );
				return;
			}

			// Redirect
			$redirect = get_term_link( (int) $to_tag, bbp_get_topic_tag_tax_id() );

			// Update counts, etc...
			do_action( 'bbp_merge_topic_tag', $tag_id, $to_tag, $tag );

			break;

		// Delete tag
		case 'bbp-delete-topic-tag' :

			// Nonce check
			if ( ! bbp_verify_nonce_request( 'delete-tag_' . $tag_id ) ) {
				bbp_add_error( 'bbp_manage_topic_tag_delete_nonce', __( '<strong>Error</strong>: Are you sure you wanted to do that?', 'bbpress' ) );
				return;
			}

			// Can user delete topic tags?
			if ( ! current_user_can( 'delete_topic_tag', $tag_id ) ) {
				bbp_add_error( 'bbp_manage_topic_tag_delete_permission', __( '<strong>Error</strong>: You do not have permission to delete the topic tags.', 'bbpress' ) );
				return;
			}

			// Attempt to delete term
			$tag = wp_delete_term( $tag_id, bbp_get_topic_tag_tax_id() );

			// Error deleting term
			if ( is_wp_error( $tag ) && $tag->get_error_message() ) {
				bbp_add_error( 'bbp_manage_topic_tag_delete_error', sprintf( __( '<strong>Error</strong>: The following problem(s) have been found while deleting the tag: %s', 'bbpress' ), $tag->get_error_message() ) );
				return;
			}

			// We don't have any other place to go other than home! Or we may die because of the 404 disease
			$redirect = bbp_get_forums_url();

			// Update counts, etc...
			do_action( 'bbp_delete_topic_tag', $tag_id, $tag );

			break;
	}

	/** Successful Moderation *************************************************/

	// Redirect back
	$redirect = ( ! empty( $redirect ) && ! is_wp_error( $redirect ) ) ? $redirect : home_url();
	bbp_redirect( $redirect );
}

/** Helpers *******************************************************************/

/**
 * Return an associative array of available topic statuses
 *
 * @since 2.4.0 bbPress (r5059)
 *
 * @param int $topic_id   Optional. Topic id.
 *
 * @return array
 */
function bbp_get_topic_statuses( $topic_id = 0 ) {

	// Filter & return
	return (array) apply_filters( 'bbp_get_topic_statuses', array(
		bbp_get_public_status_id()  => _x( 'Open',    'Open the topic',      'bbpress' ),
		bbp_get_closed_status_id()  => _x( 'Closed',  'Close the topic',     'bbpress' ),
		bbp_get_spam_status_id()    => _x( 'Spam',    'Spam the topic',      'bbpress' ),
		bbp_get_trash_status_id()   => _x( 'Trash',   'Trash the topic',     'bbpress' ),
		bbp_get_pending_status_id() => _x( 'Pending', 'Unapprove the topic', 'bbpress' )
	), $topic_id );
}

/**
 * Return an associative array of topic sticky types
 *
 * @since 2.4.0 bbPress (r5059)
 *
 * @param int $topic_id   Optional. Topic id.
 *
 * @return array
 */
function bbp_get_topic_types( $topic_id = 0 ) {

	// Filter & return
	return (array) apply_filters( 'bbp_get_topic_types', array(
		'unstick' => _x( 'Normal',       'Unstick a topic',         'bbpress' ),
		'stick'   => _x( 'Sticky',       'Make topic sticky',       'bbpress' ),
		'super'   => _x( 'Super Sticky', 'Make topic super sticky', 'bbpress' )
	), $topic_id );
}

/**
 * Return array of available topic toggle actions
 *
 * @since 2.6.0 bbPress (r6133)
 *
 * @param int $topic_id   Optional. Topic id.
 *
 * @return array
 */
function bbp_get_topic_toggles( $topic_id = 0 ) {

	// Filter & return
	return (array) apply_filters( 'bbp_get_toggle_topic_actions', array(
		'bbp_toggle_topic_close',
		'bbp_toggle_topic_stick',
		'bbp_toggle_topic_spam',
		'bbp_toggle_topic_trash',
		'bbp_toggle_topic_approve'
	), $topic_id );
}

/**
 * Return array of public topic statuses.
 *
 * @since 2.6.0 bbPress (r6383)
 *
 * @return array
 */
function bbp_get_public_topic_statuses() {
	$statuses = array(
		bbp_get_public_status_id(),
		bbp_get_closed_status_id()
	);

	// Filter & return
	return (array) apply_filters( 'bbp_get_public_topic_statuses', $statuses );
}

/**
 * Return array of non-public topic statuses.
 *
 * @since 2.6.0 bbPress (r6642)
 *
 * @return array
 */
function bbp_get_non_public_topic_statuses() {
	$statuses = array(
		bbp_get_trash_status_id(),
		bbp_get_spam_status_id(),
		bbp_get_pending_status_id()
	);

	// Filter & return
	return (array) apply_filters( 'bbp_get_non_public_topic_statuses', $statuses );
}

/** Stickies ******************************************************************/

/**
 * Return sticky topics of a forum
 *
 * @since 2.0.0 bbPress (r2592)
 *
 * @param int $forum_id Optional. If not passed, super stickies are returned.
 * @return array IDs of sticky topics of a forum or super stickies
 */
function bbp_get_stickies( $forum_id = 0 ) {

	// Get stickies (maybe super if empty)
	$stickies = empty( $forum_id )
		? bbp_get_super_stickies()
		: get_post_meta( $forum_id, '_bbp_sticky_topics', true );

	// Cast as array
	$stickies = ( empty( $stickies ) || ! is_array( $stickies ) )
		? array()
		: wp_parse_id_list( $stickies );

	// Filter & return
	return (array) apply_filters( 'bbp_get_stickies', $stickies, $forum_id );
}

/**
 * Return topics stuck to front page of the forums
 *
 * @since 2.0.0 bbPress (r2592)
 *
 * @return array IDs of super sticky topics
 */
function bbp_get_super_stickies() {

	// Get super stickies
	$stickies = get_option( '_bbp_super_sticky_topics', array() );

	// Cast as array
	$stickies = ( empty( $stickies ) || ! is_array( $stickies ) )
		? array()
		: wp_parse_id_list( $stickies );

	// Filter & return
	return (array) apply_filters( 'bbp_get_super_stickies', $stickies );
}

/** Topics Actions ************************************************************/

/**
 * Handles the front end opening/closing, spamming/unspamming,
 * sticking/unsticking and trashing/untrashing/deleting of topics
 *
 * @since 2.0.0 bbPress (r2727)
 *
 * @param string $action The requested action to compare this function to
 */
function bbp_toggle_topic_handler( $action = '' ) {

	// Bail if required GET actions aren't passed
	if ( empty( $_GET['topic_id'] ) ) {
		return;
	}

	// What's the topic id?
	$topic_id = bbp_get_topic_id( (int) $_GET['topic_id'] );

	// Get possible topic-handler toggles
	$toggles = bbp_get_topic_toggles( $topic_id );

	// Bail if actions aren't meant for this function
	if ( ! in_array( $action, $toggles, true ) ) {
		return;
	}

	// Make sure topic exists
	$topic = bbp_get_topic( $topic_id );
	if ( empty( $topic ) ) {
		bbp_add_error( 'bbp_toggle_topic_missing', __( '<strong>Error</strong>: This topic could not be found or no longer exists.', 'bbpress' ) );
		return;
	}

	// What is the user doing here?
	if ( ! current_user_can( 'edit_topic', $topic_id ) || ( 'bbp_toggle_topic_trash' === $action && ! current_user_can( 'delete_topic', $topic_id ) ) ) {
		bbp_add_error( 'bbp_toggle_topic_permission', __( '<strong>Error</strong>: You do not have permission to do that.', 'bbpress' ) );
		return;
	}

	// Sub-action?
	$sub_action = ! empty( $_GET['sub_action'] )
		? sanitize_key( $_GET['sub_action'] )
		: false;

	// Preliminary array
	$post_data = array( 'ID' => $topic_id );

	// Do the topic toggling
	$retval = bbp_toggle_topic( array(
		'id'         => $topic_id,
		'action'     => $action,
		'sub_action' => $sub_action,
		'data'       => $post_data
	) );

	// Do additional topic toggle actions
	do_action( 'bbp_toggle_topic_handler', $retval['status'], $post_data, $action );

	// No errors
	if ( ( false !== $retval['status'] ) && ! is_wp_error( $retval['status'] ) ) {
		bbp_redirect( $retval['redirect_to'] );

	// Handle errors
	} else {
		bbp_add_error( 'bbp_toggle_topic', $retval['message'] );
	}
}

/**
 * Do the actual topic toggling
 *
 * This function is used by `bbp_toggle_topic_handler()` to do the actual heavy
 * lifting when it comes to toggling topic. It only really makes sense to call
 * within that context, so if you need to call this function directly, make sure
 * you're also doing what the handler does too.
 *
 * @since 2.6.0  bbPress (r6133)
 * @access private
 *
 * @param array $args
 */
function bbp_toggle_topic( $args = array() ) {

	// Parse the arguments
	$r = bbp_parse_args( $args, array(
		'id'         => 0,
		'action'     => '',
		'sub_action' => '',
		'data'       => array()
	) );

	// Build the nonce suffix
	$nonce_suffix = bbp_get_topic_post_type() . '_' . (int) $r['id'];

	// Default return values
	$retval = array(
		'status'      => 0,
		'message'     => '',
		'redirect_to' => bbp_get_topic_permalink( $r['id'], bbp_get_redirect_to() ),
		'view_all'    => false
	);

	// What action are we trying to perform?
	switch ( $r['action'] ) {

		// Toggle approve/unapprove
		case 'bbp_toggle_topic_approve' :
			check_ajax_referer( "approve-{$nonce_suffix}" );

			$is_pending         = bbp_is_topic_pending( $r['id'] );
			$retval['view_all'] = ! $is_pending;

			// Toggle
			$retval['status'] = ( true === $is_pending )
				? bbp_approve_topic( $r['id'] )
				: bbp_unapprove_topic( $r['id'] );

			// Feedback
			$retval['message'] = ( true === $is_pending )
				? __( '<strong>Error</strong>: There was a problem approving the topic.',   'bbpress' )
				: __( '<strong>Error</strong>: There was a problem unapproving the topic.', 'bbpress' );

			break;

		// Toggle open/close
		case 'bbp_toggle_topic_close' :
			check_ajax_referer( "close-{$nonce_suffix}" );

			$is_open = bbp_is_topic_open( $r['id'] );

			// Toggle
			$retval['status'] = ( true === $is_open )
				? bbp_close_topic( $r['id'] )
				: bbp_open_topic( $r['id'] );

			// Feedback
			$retval['message'] = ( true === $is_open )
				? __( '<strong>Error</strong>: There was a problem closing the topic.', 'bbpress' )
				: __( '<strong>Error</strong>: There was a problem opening the topic.', 'bbpress' );

			break;

		// Toggle sticky/super-sticky/unstick
		case 'bbp_toggle_topic_stick' :
			check_ajax_referer( "stick-{$nonce_suffix}" );

			$is_sticky = bbp_is_topic_sticky( $r['id'] );
			$is_super  = false === $is_sticky && ! empty( $_GET['super'] ) && ( "1" === $_GET['super'] ) ? true : false;

			// Toggle
			$retval['status'] = ( true === $is_sticky )
				? bbp_unstick_topic( $r['id'] )
				: bbp_stick_topic( $r['id'], $is_super );

			// Feedback
			$retval['message'] = ( true === $is_sticky )
				? __( '<strong>Error</strong>: There was a problem unsticking the topic.', 'bbpress' )
				: __( '<strong>Error</strong>: There was a problem sticking the topic.',   'bbpress' );

			break;

		// Toggle spam
		case 'bbp_toggle_topic_spam' :
			check_ajax_referer( "spam-{$nonce_suffix}" );

			$is_spam            = bbp_is_topic_spam( $r['id'] );
			$retval['view_all'] = ! $is_spam;

			// Toggle
			$retval['status'] = ( true === $is_spam )
				? bbp_unspam_topic( $r['id'] )
				: bbp_spam_topic( $r['id'] );

			// Feedback
			$retval['message'] = ( true === $is_spam )
				? __( '<strong>Error</strong>: There was a problem unmarking the topic as spam.', 'bbpress' )
				: __( '<strong>Error</strong>: There was a problem marking the topic as spam.',   'bbpress' );

			break;

		// Toggle trash
		case 'bbp_toggle_topic_trash' :

			switch ( $r['sub_action'] ) {
				case 'trash':
					check_ajax_referer( "trash-{$nonce_suffix}" );

					$retval['view_all']    = true;
					$retval['status']      = wp_trash_post( $r['id'] );
					$retval['message']     = __( '<strong>Error</strong>: There was a problem trashing the topic.', 'bbpress' );
					$retval['redirect_to'] = current_user_can( 'view_trash' )
						? bbp_get_topic_permalink( $r['id'] )
						: bbp_get_forum_permalink( bbp_get_topic_forum_id( $r['id'] ) );

					break;

				case 'untrash':
					check_ajax_referer( "untrash-{$nonce_suffix}" );

					$retval['status']      = wp_untrash_post( $r['id'] );
					$retval['message']     = __( '<strong>Error</strong>: There was a problem untrashing the topic.', 'bbpress' );
					$retval['redirect_to'] = bbp_get_topic_permalink( $r['id'] );

					break;

				case 'delete':
					check_ajax_referer( "delete-{$nonce_suffix}" );

					$retval['status']      = wp_delete_post( $r['id'] );
					$retval['message']     = __( '<strong>Error</strong>: There was a problem deleting the topic.', 'bbpress' );
					$retval['redirect_to'] = bbp_get_forum_permalink( $retval['status']->post_parent );

					break;
			}

			break;
	}

	// Add view all if needed
	if ( ! empty( $retval['view_all'] ) ) {
		$retval['redirect_to'] = bbp_add_view_all( $retval['redirect_to'], true );
	}

	// Filter & return
	return apply_filters( 'bbp_toggle_topic', $retval, $r, $args );
}

/** Favorites & Subscriptions *************************************************/

/**
 * Remove a deleted topic from all user favorites
 *
 * @since 2.0.0 bbPress (r2652)
 *
 * @param int $topic_id Get the topic id to remove
 */
function bbp_remove_topic_from_all_favorites( $topic_id = 0 ) {
	$topic_id = bbp_get_topic_id( $topic_id );

	// Bail if no topic
	if ( empty( $topic_id ) ) {
		return;
	}

	// Get users
	$users = (array) bbp_get_topic_favoriters( $topic_id );

	// Users exist
	if ( ! empty( $users ) ) {

		// Loop through users
		foreach ( $users as $user ) {

			// Remove each user
			bbp_remove_user_favorite( $user, $topic_id );
		}
	}
}

/**
 * Remove a deleted topic from all user subscriptions
 *
 * @since 2.0.0 bbPress (r2652)
 *
 * @param int $topic_id Get the topic id to remove
 */
function bbp_remove_topic_from_all_subscriptions( $topic_id = 0 ) {

	// Subscriptions are not active
	if ( ! bbp_is_subscriptions_active() ) {
		return;
	}

	// Bail if no topic
	$topic_id = bbp_get_topic_id( $topic_id );
	if ( empty( $topic_id ) ) {
		return;
	}

	// Remove all users
	return bbp_remove_object_from_all_users( $topic_id, '_bbp_subscription', 'post' );
}

/** Count Bumpers *************************************************************/

/**
 * Bump the total reply count of a topic
 *
 * @since 2.1.0 bbPress (r3825)
 *
 * @param int $topic_id   Optional. Topic id.
 * @param int $difference Optional. Default 1
 * @return int Topic reply count
 */
function bbp_bump_topic_reply_count( $topic_id = 0, $difference = 1 ) {

	// Bail if no bump
	if ( empty( $difference ) ) {
		return false;
	}

	// Get counts
	$topic_id    = bbp_get_topic_id( $topic_id );
	$reply_count = bbp_get_topic_reply_count( $topic_id, true );
	$difference  = (int) $difference;
	$new_count   = (int) ( $reply_count + $difference );

	// Update this topic id's reply count
	update_post_meta( $topic_id, '_bbp_reply_count', $new_count );

	// Filter & return
	return (int) apply_filters( 'bbp_bump_topic_reply_count', $new_count, $topic_id, $difference );
}

/**
 * Increase the total reply count of a topic by one.
 *
 * @since 2.6.0 bbPress (r6036)
 *
 * @param int $topic_id The topic id.
 *
 * @return void
 */
function bbp_increase_topic_reply_count( $topic_id = 0 ) {

	// Bail early if no id is passed.
	if ( empty( $topic_id ) ) {
		return;
	}

	// If it's a reply, get the topic id.
	if ( bbp_is_reply( $topic_id ) ) {
		$reply_id = $topic_id;
		$topic_id = bbp_get_reply_topic_id( $reply_id );

		// Update inverse based on item status
		if ( ! bbp_is_reply_public( $reply_id ) ) {
			bbp_increase_topic_reply_count_hidden( $topic_id );
			return;
		}
	}

	// Bump up
	bbp_bump_topic_reply_count( $topic_id );
}

/**
 * Decrease the total reply count of a topic by one.
 *
 * @since 2.6.0 bbPress (r6036)
 *
 * @param int $topic_id The topic id.
 *
 * @return void
 */
function bbp_decrease_topic_reply_count( $topic_id = 0 ) {

	// Bail early if no id is passed.
	if ( empty( $topic_id ) ) {
		return;
	}

	// If it's a reply, get the topic id.
	if ( bbp_is_reply( $topic_id ) ) {
		$reply_id = $topic_id;
		$topic_id = bbp_get_reply_topic_id( $reply_id );

		// Update inverse based on item status
		if ( ! bbp_is_reply_public( $reply_id ) ) {
			bbp_decrease_topic_reply_count_hidden( $topic_id );
			return;
		}
	}

	// Bump down
	bbp_bump_topic_reply_count( $topic_id, -1 );
}

/**
 * Bump the total hidden reply count of a topic
 *
 * @since 2.1.0 bbPress (r3825)
 *
 * @param int $topic_id   Optional. Topic id.
 * @param int $difference Optional. Default 1
 * @return int Topic hidden reply count
 */
function bbp_bump_topic_reply_count_hidden( $topic_id = 0, $difference = 1 ) {

	// Bail if no bump
	if ( empty( $difference ) ) {
		return false;
	}

	// Get counts
	$topic_id    = bbp_get_topic_id( $topic_id );
	$reply_count = bbp_get_topic_reply_count_hidden( $topic_id, true );
	$difference  = (int) $difference;
	$new_count   = (int) ( $reply_count + $difference );

	// Update this topic id's hidden reply count
	update_post_meta( $topic_id, '_bbp_reply_count_hidden', $new_count );

	// Filter & return
	return (int) apply_filters( 'bbp_bump_topic_reply_count_hidden', $new_count, $topic_id, $difference );
}

/**
 * Increase the total hidden reply count of a topic by one.
 *
 * @since 2.6.0 bbPress (r6036)
 *
 * @param int $topic_id The topic id.
 *
 * @return void
 */
function bbp_increase_topic_reply_count_hidden( $topic_id = 0 ) {

	// Bail early if no id is passed.
	if ( empty( $topic_id ) ) {
		return;
	}

	// If it's a reply, get the topic id.
	if ( bbp_is_reply( $topic_id ) ) {
		$reply_id = $topic_id;
		$topic_id = bbp_get_reply_topic_id( $reply_id );

		// Update inverse based on item status
		if ( bbp_is_reply_public( $reply_id ) ) {
			bbp_increase_topic_reply_count( $topic_id );
			return;
		}
	}

	// Bump up
	bbp_bump_topic_reply_count_hidden( $topic_id );
}

/**
 * Decrease the total hidden reply count of a topic by one.
 *
 * @since 2.6.0 bbPress (r6036)
 *
 * @param int $topic_id The topic id.
 *
 * @return void
 */
function bbp_decrease_topic_reply_count_hidden( $topic_id = 0 ) {

	// Bail early if no id is passed.
	if ( empty( $topic_id ) ) {
		return;
	}

	// If it's a reply, get the topic id.
	if ( bbp_is_reply( $topic_id ) ) {
		$reply_id = $topic_id;
		$topic_id = bbp_get_reply_topic_id( $reply_id );

		// Update inverse based on item status
		if ( bbp_is_reply_public( $reply_id ) ) {
			bbp_decrease_topic_reply_count( $topic_id );
			return;
		}
	}

	// Bump down
	bbp_bump_topic_reply_count_hidden( $topic_id, -1 );
}

/**
 * Update counts after a topic is inserted via `bbp_insert_topic`.
 *
 * @since 2.6.0 bbPress (r6036)
 *
 * @param int $topic_id The topic id.
 * @param int $forum_id The forum id.
 *
 * @return void
 */
function bbp_insert_topic_update_counts( $topic_id = 0, $forum_id = 0 ) {

	// If the topic is public, update the forum topic counts.
	if ( bbp_is_topic_public( $topic_id ) ) {
		bbp_increase_forum_topic_count( $forum_id );

	// If the topic isn't public only update the forum topic hidden count.
	} else {
		bbp_increase_forum_topic_count_hidden( $forum_id );
	}
}

/** Topic Updaters ************************************************************/

/**
 * Update the topic's forum id
 *
 * @since 2.0.0 bbPress (r2855)
 *
 * @param int $topic_id Optional. Topic id to update
 * @param int $forum_id Optional. Forum id
 * @return int Forum id
 */
function bbp_update_topic_forum_id( $topic_id = 0, $forum_id = 0 ) {

	// If it's a reply, then get the parent (topic id)
	$topic_id = bbp_is_reply( $topic_id )
		? bbp_get_reply_topic_id( $topic_id )
		: bbp_get_topic_id( $topic_id );

	// Forum ID fallback
	if ( empty( $forum_id ) ) {
		$forum_id = get_post_field( 'post_parent', $topic_id );
	}

	// Update the forum ID
	$forum_id = bbp_update_forum_id( $topic_id, $forum_id );

	// Filter & return
	return (int) apply_filters( 'bbp_update_topic_forum_id', $forum_id, $topic_id );
}

/**
 * Update the topic's topic id
 *
 * @since 2.0.0 bbPress (r2954)
 *
 * @param int $topic_id Optional. Topic id to update
 * @return int Topic id
 */
function bbp_update_topic_topic_id( $topic_id = 0 ) {
	$topic_id = bbp_get_topic_id( $topic_id );
	$topic_id = bbp_update_topic_id( $topic_id, $topic_id );

	// Filter & return
	return (int) apply_filters( 'bbp_update_topic_topic_id', $topic_id );
}

/**
 * Adjust the total reply count of a topic
 *
 * @since 2.0.0 bbPress (r2467)
 *
 * @param int $topic_id Optional. Topic id to update
 * @param int $reply_count Optional. Set the reply count manually.
 * @return int Topic reply count
 */
function bbp_update_topic_reply_count( $topic_id = 0, $reply_count = false ) {

	// If it's a reply, then get the parent (topic id)
	$topic_id = bbp_is_reply( $topic_id )
		? bbp_get_reply_topic_id( $topic_id )
		: bbp_get_topic_id( $topic_id );

	// Get replies of topic if not passed
	$reply_count = ! is_int( $reply_count )
		? bbp_get_public_child_count( $topic_id, bbp_get_reply_post_type() )
		: (int) $reply_count;

	update_post_meta( $topic_id, '_bbp_reply_count', $reply_count );

	// Filter & return
	return (int) apply_filters( 'bbp_update_topic_reply_count', $reply_count, $topic_id );
}

/**
 * Adjust the total hidden reply count of a topic (hidden includes trashed,
 * spammed and pending replies)
 *
 * @since 2.0.0 bbPress (r2740)
 *
 * @param int $topic_id Optional. Topic id to update
 * @param int $reply_count Optional. Set the reply count manually
 * @return int Topic hidden reply count
 */
function bbp_update_topic_reply_count_hidden( $topic_id = 0, $reply_count = false ) {

	// If it's a reply, then get the parent (topic id)
	$topic_id = bbp_is_reply( $topic_id )
		? bbp_get_reply_topic_id( $topic_id )
		: bbp_get_topic_id( $topic_id );

	// Get replies of topic
	$reply_count = ! is_int( $reply_count )
		? bbp_get_non_public_child_count( $topic_id, bbp_get_reply_post_type() )
		: (int) $reply_count;

	update_post_meta( $topic_id, '_bbp_reply_count_hidden', $reply_count );

	// Filter & return
	return (int) apply_filters( 'bbp_update_topic_reply_count_hidden', $reply_count, $topic_id );
}

/**
 * Update the topic with the last active post ID
 *
 * @since 2.0.0 bbPress (r2888)
 *
 * @param int $topic_id Optional. Topic id to update
 * @param int $active_id Optional. active id
 * @return int Active id
 */
function bbp_update_topic_last_active_id( $topic_id = 0, $active_id = 0 ) {

	// If it's a reply, then get the parent (topic id)
	$topic_id = bbp_is_reply( $topic_id )
		? bbp_get_reply_topic_id( $topic_id )
		: bbp_get_topic_id( $topic_id );

	// Get last public active id if not passed
	if ( empty( $active_id ) ) {
		$active_id = bbp_get_public_child_last_id( $topic_id, bbp_get_reply_post_type() );
	}

	// Adjust last_id's based on last_reply post_type
	if ( empty( $active_id ) || ! bbp_is_reply( $active_id ) ) {
		$active_id = $topic_id;
	}

	$active_id = (int) $active_id;

	// Update only if published
	update_post_meta( $topic_id, '_bbp_last_active_id', $active_id );

	// Filter & return
	return (int) apply_filters( 'bbp_update_topic_last_active_id', $active_id, $topic_id );
}

/**
 * Update the topics last active date/time (aka freshness)
 *
 * @since 2.0.0 bbPress (r2680)
 *
 * @param int    $topic_id Optional. Topic id.
 * @param string $new_time Optional. New time in mysql format.
 * @return string MySQL timestamp of last active reply
 */
function bbp_update_topic_last_active_time( $topic_id = 0, $new_time = '' ) {

	// If it's a reply, then get the parent (topic id)
	$topic_id = bbp_is_reply( $topic_id )
		? bbp_get_reply_topic_id( $topic_id )
		: bbp_get_topic_id( $topic_id );

	// Check time and use current if empty
	if ( empty( $new_time ) ) {
		$new_time = get_post_field( 'post_date', bbp_get_public_child_last_id( $topic_id, bbp_get_reply_post_type() ) );
	}

	// Update only if published
	if ( ! empty( $new_time ) ) {
		update_post_meta( $topic_id, '_bbp_last_active_time', $new_time );
	}

	// Filter & return
	return apply_filters( 'bbp_update_topic_last_active_time', $new_time, $topic_id );
}

/**
 * Update the topic with the most recent reply ID
 *
 * @since 2.0.0 bbPress (r2625)
 *
 * @param int $topic_id Optional. Topic id to update
 * @param int $reply_id Optional. Reply id
 * @return int Reply id
 */
function bbp_update_topic_last_reply_id( $topic_id = 0, $reply_id = 0 ) {

	// If it's a reply, then get the parent (topic id)
	if ( empty( $reply_id ) && bbp_is_reply( $topic_id ) ) {
		$reply_id = bbp_get_reply_id( $topic_id );
		$topic_id = bbp_get_reply_topic_id( $reply_id );
	} else {
		$reply_id = bbp_get_reply_id( $reply_id );
		$topic_id = bbp_get_topic_id( $topic_id );
	}

	if ( empty( $reply_id ) ) {
		$reply_id = bbp_get_public_child_last_id( $topic_id, bbp_get_reply_post_type() );
	}

	// Adjust last_id's based on last_reply post_type
	if ( empty( $reply_id ) || ! bbp_is_reply( $reply_id ) ) {
		$reply_id = 0;
	}

	$reply_id = (int) $reply_id;

	// Update if reply is published
	update_post_meta( $topic_id, '_bbp_last_reply_id', $reply_id );

	// Filter & return
	return (int) apply_filters( 'bbp_update_topic_last_reply_id', $reply_id, $topic_id );
}

/**
 * Adjust the total voice count of a topic
 *
 * @since 2.0.0 bbPress (r2567)
 * @since 2.6.0 bbPress (r6515) This must be called after any engagement changes
 *
 * @param int $topic_id Optional. Topic id to update
 * @return int Voice count
 */
function bbp_update_topic_voice_count( $topic_id = 0 ) {

	// If it's a reply, then get the parent (topic id)
	$topic_id = bbp_is_reply( $topic_id )
		? bbp_get_reply_topic_id( $topic_id )
		: bbp_get_topic_id( $topic_id );

	// Bail if no topic ID
	if ( empty( $topic_id ) ) {
		return;
	}

	// Count the engagements
	$count = count( bbp_get_topic_engagements( $topic_id ) );

	// Update the voice count for this topic id
	update_post_meta( $topic_id, '_bbp_voice_count', $count );

	// Filter & return
	return (int) apply_filters( 'bbp_update_topic_voice_count', $count, $topic_id );
}

/**
 * Adjust the total anonymous reply count of a topic
 *
 * @since 2.0.0 bbPress (r2567)
 *
 * @param int $topic_id Optional. Topic id to update
 * @return int Anonymous reply count
 */
function bbp_update_topic_anonymous_reply_count( $topic_id = 0 ) {

	// If it's a reply, then get the parent (topic id)
	$topic_id = bbp_is_reply( $topic_id )
		? bbp_get_reply_topic_id( $topic_id )
		: bbp_get_topic_id( $topic_id );

	// Query the DB to get anonymous replies in this topic
	$bbp_db  = bbp_db();
	$query   = $bbp_db->prepare( "SELECT COUNT( ID ) FROM {$bbp_db->posts} WHERE ( post_parent = %d AND post_status = %s AND post_type = %s AND post_author = 0 ) OR ( ID = %d AND post_type = %s AND post_author = 0 )", $topic_id, bbp_get_public_status_id(), bbp_get_reply_post_type(), $topic_id, bbp_get_topic_post_type() );
	$replies = (int) $bbp_db->get_var( $query );

	update_post_meta( $topic_id, '_bbp_anonymous_reply_count', $replies );

	// Filter & return
	return (int) apply_filters( 'bbp_update_topic_anonymous_reply_count', $replies, $topic_id );
}

/**
 * Update the revision log of the topic
 *
 * @since 2.0.0 bbPress (r2782)
 *
 * @param array $args Supports these args:
 *  - topic_id: Topic id
 *  - author_id: Author id
 *  - reason: Reason for editing
 *  - revision_id: Revision id
 * @return mixed False on failure, true on success
 */
function bbp_update_topic_revision_log( $args = array() ) {

	// Parse arguments against default values
	$r = bbp_parse_args( $args, array(
		'reason'      => '',
		'topic_id'    => 0,
		'author_id'   => 0,
		'revision_id' => 0
	), 'update_topic_revision_log' );

	// Populate the variables
	$r['reason']      = bbp_format_revision_reason( $r['reason'] );
	$r['topic_id']    = bbp_get_topic_id( $r['topic_id'] );
	$r['author_id']   = bbp_get_user_id ( $r['author_id'], false, true );
	$r['revision_id'] = (int) $r['revision_id'];

	// Get the logs and append the new one to those
	$revision_log                      = bbp_get_topic_raw_revision_log( $r['topic_id'] );
	$revision_log[ $r['revision_id'] ] = array( 'author' => $r['author_id'], 'reason' => $r['reason'] );

	// Finally, update
	return update_post_meta( $r['topic_id'], '_bbp_revision_log', $revision_log );
}

/** Topic Actions *************************************************************/

/**
 * Closes a topic
 *
 * @since 2.0.0 bbPress (r2740)
 *
 * @param int $topic_id Topic id
 * @return mixed False or {@link WP_Error} on failure, topic id on success
 */
function bbp_close_topic( $topic_id = 0 ) {

	// Get topic
	$topic = bbp_get_topic( $topic_id );
	if ( empty( $topic ) ) {
		return $topic;
	}

	// Get previous topic status meta
	$status       = bbp_get_closed_status_id();
	$topic_status = get_post_meta( $topic_id, '_bbp_status', true );

	// Bail if already closed and topic status meta exists
	if ( $status === $topic->post_status && ! empty( $topic_status ) ) {
		return false;
	}

	// Set status meta public
	$topic_status = $topic->post_status;

	// Execute pre close code
	do_action( 'bbp_close_topic', $topic_id );

	// Add pre close status
	add_post_meta( $topic_id, '_bbp_status', $topic_status );

	// Set closed status
	$topic->post_status = $status;

	// Toggle revisions off as we are not altering content
	if ( post_type_supports( bbp_get_topic_post_type(), 'revisions' ) ) {
		$revisions_removed = true;
		remove_post_type_support( bbp_get_topic_post_type(), 'revisions' );
	}

	// Update topic
	$topic_id = wp_update_post( $topic );

	// Toggle revisions back on
	if ( true === $revisions_removed ) {
		$revisions_removed = false;
		add_post_type_support( bbp_get_topic_post_type(), 'revisions' );
	}

	// Execute post close code
	do_action( 'bbp_closed_topic', $topic_id );

	// Return topic_id
	return $topic_id;
}

/**
 * Opens a topic
 *
 * @since 2.0.0 bbPress (r2740)
 *
 * @param int $topic_id Topic id
 * @return mixed False or {@link WP_Error} on failure, topic id on success
 */
function bbp_open_topic( $topic_id = 0 ) {

	// Get topic
	$topic = bbp_get_topic( $topic_id );
	if ( empty( $topic ) ) {
		return $topic;
	}

	// Bail if not closed
	if ( bbp_get_closed_status_id() !== $topic->post_status ) {
		return false;
	}

	// Execute pre open code
	do_action( 'bbp_open_topic', $topic_id );

	// Get previous status
	$topic_status = get_post_meta( $topic_id, '_bbp_status', true );

	// If no previous status, default to publish
	if ( empty( $topic_status ) ) {
		$topic_status = bbp_get_public_status_id();
	}

	// Set previous status
	$topic->post_status = $topic_status;

	// Remove old status meta
	delete_post_meta( $topic_id, '_bbp_status' );

	// Toggle revisions off as we are not altering content
	if ( post_type_supports( bbp_get_topic_post_type(), 'revisions' ) ) {
		$revisions_removed = true;
		remove_post_type_support( bbp_get_topic_post_type(), 'revisions' );
	}

	// Update topic
	$topic_id = wp_update_post( $topic );

	// Toggle revisions back on
	if ( true === $revisions_removed ) {
		$revisions_removed = false;
		add_post_type_support( bbp_get_topic_post_type(), 'revisions' );
	}

	// Execute post open code
	do_action( 'bbp_opened_topic', $topic_id );

	// Return topic_id
	return $topic_id;
}

/**
 * Marks a topic as spam
 *
 * @since 2.0.0 bbPress (r2740)
 *
 * @param int $topic_id Topic id
 * @return mixed False or {@link WP_Error} on failure, topic id on success
 */
function bbp_spam_topic( $topic_id = 0 ) {

	// Get the topic
	$topic = bbp_get_topic( $topic_id );
	if ( empty( $topic ) ) {
		return $topic;
	}

	// Get new status
	$status = bbp_get_spam_status_id();

	// Bail if topic is spam
	if ( $status === $topic->post_status ) {
		return false;
	}

	// Add the original post status as post meta for future restoration
	add_post_meta( $topic_id, '_bbp_spam_meta_status', $topic->post_status );

	// Execute pre spam code
	do_action( 'bbp_spam_topic', $topic_id );

	// Set post status to spam
	$topic->post_status = $status;

	// Empty the topic of its tags
	$topic->tax_input = bbp_spam_topic_tags( $topic_id );

	// No revisions
	remove_action( 'pre_post_update', 'wp_save_post_revision' );

	// Update the topic
	$topic_id = wp_update_post( $topic );

	// Execute post spam code
	do_action( 'bbp_spammed_topic', $topic_id );

	// Return topic_id
	return $topic_id;
}

/**
 * Trash replies to a topic when it's marked as spam
 *
 * Usually you'll want to do this before the topic itself is marked as spam.
 *
 * @since 2.6.0 bbPress (r5405)
 *
 * @param int $topic_id
 */
function bbp_spam_topic_replies( $topic_id = 0 ) {

	// Validation
	$topic_id = bbp_get_topic_id( $topic_id );

	// Topic is being spammed, so its replies are trashed
	$replies = new WP_Query( array(
		'fields'         => 'id=>parent',
		'post_type'      => bbp_get_reply_post_type(),
		'post_status'    => bbp_get_public_status_id(),
		'post_parent'    => $topic_id,
		'posts_per_page' => -1,

		// Performance
		'nopaging'               => true,
		'suppress_filters'       => true,
		'update_post_term_cache' => false,
		'update_post_meta_cache' => false,
		'ignore_sticky_posts'    => true,
		'no_found_rows'          => true
	) );

	if ( ! empty( $replies->posts ) ) {

		// Prevent debug notices
		$pre_spammed_replies = array();

		// Loop through replies, trash them, and add them to array
		foreach ( $replies->posts as $reply ) {
			wp_trash_post( $reply->ID );
			$pre_spammed_replies[] = $reply->ID;
		}

		// Set a post_meta entry of the replies that were trashed by this action.
		// This is so we can possibly untrash them, without untrashing replies
		// that were purposefully trashed before.
		update_post_meta( $topic_id, '_bbp_pre_spammed_replies', $pre_spammed_replies );

		// Reset the global post data after looping through the above WP_Query
		wp_reset_postdata();
	}
}

/**
 * Store the tags to a topic in post meta before it's marked as spam so they
 * can be retrieved and unspammed later.
 *
 * Usually you'll want to do this before the topic itself is marked as spam.
 *
 * @since 2.6.0 bbPress (r5405)
 *
 * @param int $topic_id
 */
function bbp_spam_topic_tags( $topic_id = 0 ) {

	// Validation
	$topic_id = bbp_get_topic_id( $topic_id );

	// Get topic tags
	$terms = get_the_terms( $topic_id, bbp_get_topic_tag_tax_id() );

	// Define local variable(s)
	$term_names = array();

	// Topic has tags
	if ( ! empty( $terms ) ) {

		// Loop through and collect term names
		foreach ( $terms as $term ) {
			$term_names[] = $term->name;
		}

		// Topic terms have slugs
		if ( ! empty( $term_names ) ) {

			// Add the original post status as post meta for future restoration
			add_post_meta( $topic_id, '_bbp_spam_topic_tags', $term_names );
		}
	}

	return array( bbp_get_topic_tag_tax_id() => '' );
}

/**
 * Unspams a topic
 *
 * @since 2.0.0 bbPress (r2740)
 *
 * @param int $topic_id Topic id
 * @return mixed False or {@link WP_Error} on failure, topic id on success
 */
function bbp_unspam_topic( $topic_id = 0 ) {

	// Get the topic
	$topic = bbp_get_topic( $topic_id );
	if ( empty( $topic ) ) {
		return $topic;
	}

	// Bail if already not spam
	if ( bbp_get_spam_status_id() !== $topic->post_status ) {
		return false;
	}

	// Execute pre unspam code
	do_action( 'bbp_unspam_topic', $topic_id );

	// Get pre spam status
	$topic_status = get_post_meta( $topic_id, '_bbp_spam_meta_status', true );

	// If no previous status, default to publish
	if ( empty( $topic_status ) ) {
		$topic_status = bbp_get_public_status_id();
	}

	// Set post status to pre spam
	$topic->post_status = $topic_status;
	$topic->tax_input   = bbp_unspam_topic_tags( $topic_id );

	// Delete pre spam meta
	delete_post_meta( $topic_id, '_bbp_spam_meta_status' );

	// No revisions
	remove_action( 'pre_post_update', 'wp_save_post_revision' );

	// Update the topic
	$topic_id = wp_update_post( $topic );

	// Execute post unspam code
	do_action( 'bbp_unspammed_topic', $topic_id );

	// Return topic_id
	return $topic_id;
}

/**
 * Untrash replies to a topic previously marked as spam.
 *
 * Usually you'll want to do this after the topic is unspammed.
 *
 * @since 2.6.0 bbPress (r5405)
 *
 * @param int $topic_id
 */
function bbp_unspam_topic_replies( $topic_id = 0 ) {

	// Validation
	$topic_id = bbp_get_topic_id( $topic_id );

	// Get the replies that were not previously trashed
	$pre_spammed_replies = get_post_meta( $topic_id, '_bbp_pre_spammed_replies', true );

	// There are replies to untrash
	if ( ! empty( $pre_spammed_replies ) ) {

		// Maybe reverse the trashed replies array
		if ( is_array( $pre_spammed_replies ) ) {
			$pre_spammed_replies = array_reverse( $pre_spammed_replies );
		}

		// Loop through replies
		foreach ( (array) $pre_spammed_replies as $reply ) {
			wp_untrash_post( $reply );
		}
	}

	// Clear the trasheed reply meta for the topic
	delete_post_meta( $topic_id, '_bbp_pre_spammed_replies' );
}

/**
 * Retrieve tags to a topic from post meta before it's unmarked as spam so they.
 *
 * Usually you'll want to do this before the topic itself is unmarked as spam.
 *
 * @since 2.6.0 bbPress (r5405)
 *
 * @param int $topic_id
 */
function bbp_unspam_topic_tags( $topic_id = 0 ) {

	// Validation
	$topic_id = bbp_get_topic_id( $topic_id );

	// Get pre-spam topic tags
	$terms = get_post_meta( $topic_id, '_bbp_spam_topic_tags', true );

	// Delete pre-spam topic tag meta
	if ( ! empty( $terms ) ) {
		delete_post_meta( $topic_id, '_bbp_spam_topic_tags' );
	}

	return array( bbp_get_topic_tag_tax_id() => $terms );
}

/**
 * Sticks a topic to a forum or front
 *
 * @since 2.0.0 bbPress (r2754)
 *
 * @param int $topic_id Optional. Topic id
 * @param int $super Should we make the topic a super sticky?
 * @return bool True on success, false on failure
 */
function bbp_stick_topic( $topic_id = 0, $super = false ) {

	// Validation
	$topic_id = bbp_get_topic_id( $topic_id );

	// Bail if a topic is not a topic (prevents revisions as stickies)
	if ( ! bbp_is_topic( $topic_id ) ) {
		return false;
	}

	do_action( 'bbp_stick_topic', $topic_id, $super );

	// Maybe get the forum ID if not getting supers
	$forum_id = empty( $super )
		? bbp_get_topic_forum_id( $topic_id )
		: 0;

	// Get the stickies, maybe from the forum ID
	$stickies = bbp_get_stickies( $forum_id );

	// Add the topic to the stickies
	$stickies[] = $topic_id;

	// Pull out duplicates and empties
	$stickies = array_unique( array_filter( $stickies ) );

	// Unset incorrectly stuck revisions
	foreach ( (array) $stickies as $key => $id ) {
		if ( ! bbp_is_topic( $id ) ) {
			unset( $stickies[ $key ] );
		}
	}

	// Reset keys
	$stickies = array_values( $stickies );

	// Update
	$success  = ! empty( $super )
		? update_option( '_bbp_super_sticky_topics', $stickies )
		: update_post_meta( $forum_id, '_bbp_sticky_topics', $stickies );

	do_action( 'bbp_stuck_topic', $topic_id, $super, $success );

	return (bool) $success;
}

/**
 * Approves a pending topic
 *
 * @since 2.6.0 bbPress (r5503)
 *
 * @param int $topic_id Topic id
 * @return mixed False or {@link WP_Error} on failure, topic id on success
 */
function bbp_approve_topic( $topic_id = 0 ) {

	// Get topic
	$topic = bbp_get_topic( $topic_id );
	if ( empty( $topic ) ) {
		return $topic;
	}

	// Get new status
	$status = bbp_get_public_status_id();

	// Bail if already approved
	if ( $status === $topic->post_status ) {
		return false;
	}

	// Execute pre pending code
	do_action( 'bbp_approve_topic', $topic_id );

	// Set publish status
	$topic->post_status = $status;

	// Set post date GMT - prevents post_date override in wp_update_post()
	$topic->post_date_gmt = get_gmt_from_date( $topic->post_date );

	// No revisions
	remove_action( 'pre_post_update', 'wp_save_post_revision' );

	// Update topic
	$topic_id = wp_update_post( $topic );

	// Execute post pending code
	do_action( 'bbp_approved_topic', $topic_id );

	// Return topic_id
	return $topic_id;
}

/**
 * Unapproves a topic
 *
 * @since 2.6.0 bbPress (r5503)
 *
 * @param int $topic_id Topic id
 * @return mixed False or {@link WP_Error} on failure, topic id on success
 */
function bbp_unapprove_topic( $topic_id = 0 ) {

	// Get topic
	$topic = bbp_get_topic( $topic_id );
	if ( empty( $topic ) ) {
		return $topic;
	}

	// Get new status
	$status = bbp_get_pending_status_id();

	// Bail if already unapproved
	if ( ! bbp_is_topic_public( $topic_id ) ) {
		return false;
	}

	// Execute pre open code
	do_action( 'bbp_unapprove_topic', $topic_id );

	// Set pending status
	$topic->post_status = $status;

	// No revisions
	remove_action( 'pre_post_update', 'wp_save_post_revision' );

	// Update topic
	$topic_id = wp_update_post( $topic );

	// Execute post open code
	do_action( 'bbp_unapproved_topic', $topic_id );

	// Return topic_id
	return $topic_id;
}

/**
 * Unsticks a topic both from front and it's forum
 *
 * @since 2.0.0 bbPress (r2754)
 *
 * @param int $topic_id Optional. Topic id
 * @return bool Always true.
 */
function bbp_unstick_topic( $topic_id = 0 ) {

	// Get topic sticky status
	$topic_id = bbp_get_topic_id( $topic_id );
	$super    = bbp_is_topic_super_sticky( $topic_id );
	$forum_id = empty( $super ) ? bbp_get_topic_forum_id( $topic_id ) : 0;
	$stickies = bbp_get_stickies( $forum_id );
	$offset   = array_search( $topic_id, $stickies );

	do_action( 'bbp_unstick_topic', $topic_id );

	// Nothing to unstick
	if ( empty( $stickies ) ) {
		$success = true;

	// Topic not in stickies
	} elseif ( ! in_array( $topic_id, $stickies, true ) ) {
		$success = true;

	// Topic not in stickies
	} elseif ( false === $offset ) {
		$success = true;

	// Splice out the offset
	} else {
		array_splice( $stickies, $offset, 1 );

		if ( empty( $stickies ) ) {
			$success = ! empty( $super )
				? delete_option( '_bbp_super_sticky_topics' )
				: delete_post_meta( $forum_id, '_bbp_sticky_topics' );
		} else {
			$success = ! empty( $super )
				? update_option( '_bbp_super_sticky_topics', $stickies )
				: update_post_meta( $forum_id, '_bbp_sticky_topics', $stickies );
		}
	}

	do_action( 'bbp_unstuck_topic', $topic_id, $success );

	return (bool) $success;
}

/** Before Delete/Trash/Untrash ***********************************************/

/**
 * Called before deleting a topic.
 *
 * This function is supplemental to the actual topic deletion which is
 * handled by WordPress core API functions. It is used to clean up after
 * a topic that is being deleted.
 */
function bbp_delete_topic( $topic_id = 0 ) {

	// Validate topic ID
	$topic_id = bbp_get_topic_id( $topic_id );

	if ( empty( $topic_id ) || ! bbp_is_topic( $topic_id ) ) {
		return false;
	}

	do_action( 'bbp_delete_topic', $topic_id );
}

/**
 * Delete replies to a topic when it's deleted
 *
 * Usually you'll want to do this before the topic itself is deleted.
 *
 * @since 2.6.0 bbPress (r5405)
 *
 * @param int $topic_id
 */
function bbp_delete_topic_replies( $topic_id = 0 ) {

	// Validate topic ID
	$topic_id = bbp_get_topic_id( $topic_id );

	// Topic is being permanently deleted, so its replies gotta go too
	// Note that we get all post statuses here
	$replies = new WP_Query( array(
		'fields'         => 'id=>parent',
		'post_type'      => bbp_get_reply_post_type(),
		'post_status'    => array_keys( get_post_stati() ),
		'post_parent'    => $topic_id,
		'posts_per_page' => -1,

		// Performance
		'nopaging'               => true,
		'suppress_filters'       => true,
		'update_post_term_cache' => false,
		'update_post_meta_cache' => false,
		'ignore_sticky_posts'    => true,
		'no_found_rows'          => true
	) );

	// Loop through and delete child replies
	if ( ! empty( $replies->posts ) ) {
		foreach ( $replies->posts as $reply ) {
			wp_delete_post( $reply->ID, true );
		}

		// Reset the $post global
		wp_reset_postdata();
	}
}

/**
 * Called before trashing a topic
 *
 * This function is supplemental to the actual topic being trashed which is
 * handled by WordPress core API functions. It is used to clean up after
 * a topic that is being trashed.
 */
function bbp_trash_topic( $topic_id = 0 ) {

	// Validate topic ID
	$topic_id = bbp_get_topic_id( $topic_id );

	if ( empty( $topic_id ) || ! bbp_is_topic( $topic_id ) ) {
		return false;
	}

	do_action( 'bbp_trash_topic', $topic_id );
}

/**
 * Trash replies to a topic when it's trashed.
 *
 * Usually you'll want to do this before the topic itself is marked as spam.
 *
 * @since 2.6.0 bbPress (r5405)
 *
 * @param int $topic_id
 */
function bbp_trash_topic_replies( $topic_id = 0 ) {

	// Validate topic ID
	$topic_id = bbp_get_topic_id( $topic_id );

	// Topic is being trashed, so its replies are trashed too
	$replies = new WP_Query( array(
		'fields'         => 'id=>parent',
		'post_type'      => bbp_get_reply_post_type(),
		'post_status'    => bbp_get_public_status_id(),
		'post_parent'    => $topic_id,
		'posts_per_page' => -1,

		// Performance
		'nopaging'               => true,
		'suppress_filters'       => true,
		'update_post_term_cache' => false,
		'update_post_meta_cache' => false,
		'ignore_sticky_posts'    => true,
		'no_found_rows'          => true
	) );

	if ( ! empty( $replies->posts ) ) {

		// Prevent debug notices
		$pre_trashed_replies = array();

		// Loop through replies, trash them, and add them to array
		foreach ( $replies->posts as $reply ) {
			wp_trash_post( $reply->ID );
			$pre_trashed_replies[] = $reply->ID;
		}

		// Set a post_meta entry of the replies that were trashed by this action.
		// This is so we can possibly untrash them, without untrashing replies
		// that were purposefully trashed before.
		update_post_meta( $topic_id, '_bbp_pre_trashed_replies', $pre_trashed_replies );

		// Reset the $post global
		wp_reset_postdata();
	}
}

/**
 * Called before untrashing a topic
 */
function bbp_untrash_topic( $topic_id = 0 ) {
	$topic_id = bbp_get_topic_id( $topic_id );

	if ( empty( $topic_id ) || ! bbp_is_topic( $topic_id ) ) {
		return false;
	}

	do_action( 'bbp_untrash_topic', $topic_id );
}

/**
 * Untrash replies to a topic previously trashed.
 *
 * Usually you'll want to do this after the topic is unspammed.
 *
 * @since 2.6.0 bbPress (r5405)
 *
 * @param int $topic_id
 */
function bbp_untrash_topic_replies( $topic_id = 0 ) {

	// Validation
	$topic_id = bbp_get_topic_id( $topic_id );

	// Get the replies that were not previously trashed
	$pre_trashed_replies = get_post_meta( $topic_id, '_bbp_pre_trashed_replies', true );

	// There are replies to untrash
	if ( ! empty( $pre_trashed_replies ) ) {

		// Maybe reverse the trashed replies array
		if ( is_array( $pre_trashed_replies ) ) {
			$pre_trashed_replies = array_reverse( $pre_trashed_replies );
		}

		// Loop through replies
		foreach ( (array) $pre_trashed_replies as $reply ) {
			wp_untrash_post( $reply );
		}
	}

	// Clear the trashed reply meta for the topic
	delete_post_meta( $topic_id, '_bbp_pre_trashed_replies' );
}

/** After Delete/Trash/Untrash ************************************************/

/**
 * Called after deleting a topic
 *
 * @since 2.0.0 bbPress (r2993)
 */
function bbp_deleted_topic( $topic_id = 0 ) {
	$topic_id = bbp_get_topic_id( $topic_id );

	if ( empty( $topic_id ) || ! bbp_is_topic( $topic_id ) ) {
		return false;
	}

	do_action( 'bbp_deleted_topic', $topic_id );
}

/**
 * Called after trashing a topic
 *
 * @since 2.0.0 bbPress (r2993)
 */
function bbp_trashed_topic( $topic_id = 0 ) {
	$topic_id = bbp_get_topic_id( $topic_id );

	if ( empty( $topic_id ) || ! bbp_is_topic( $topic_id ) ) {
		return false;
	}

	do_action( 'bbp_trashed_topic', $topic_id );
}

/**
 * Called after untrashing a topic
 *
 * @since 2.0.0 bbPress (r2993)
 */
function bbp_untrashed_topic( $topic_id = 0 ) {
	$topic_id = bbp_get_topic_id( $topic_id );

	if ( empty( $topic_id ) || ! bbp_is_topic( $topic_id ) ) {
		return false;
	}

	do_action( 'bbp_untrashed_topic', $topic_id );
}

/** Settings ******************************************************************/

/**
 * Return the topics per page setting
 *
 * @since 2.0.0 bbPress (r3540)
 * @return int
 */
function bbp_get_topics_per_page( $default = 15 ) {

	// Get database option and cast as integer
	$retval = get_option( '_bbp_topics_per_page', $default );

	// If return val is empty, set it to default
	if ( empty( $retval ) ) {
		$retval = $default;
	}

	// Filter & return
	return (int) apply_filters( 'bbp_get_topics_per_page', $retval, $default );
}

/**
 * Return the topics per RSS page setting
 *
 * @since 2.0.0 bbPress (r3540)
 *
 * @param int $default Default replies per page (25)
 * @return int
 */
function bbp_get_topics_per_rss_page( $default = 25 ) {

	// Get database option and cast as integer
	$retval = get_option( '_bbp_topics_per_rss_page', $default );

	// If return val is empty, set it to default
	if ( empty( $retval ) ) {
		$retval = $default;
	}

	// Filter & return
	return (int) apply_filters( 'bbp_get_topics_per_rss_page', $retval, $default );
}

/** Topic Tags ****************************************************************/

/**
 * Get topic tags for a specific topic ID
 *
 * @since 2.6.0 bbPress (r5836)
 *
 * @param int $topic_id
 *
 * @return string
 */
function bbp_get_topic_tags( $topic_id = 0 ) {
	$topic_id   = bbp_get_topic_id( $topic_id );
	$terms      = (array) get_the_terms( $topic_id, bbp_get_topic_tag_tax_id() );
	$topic_tags = array_filter( $terms );

	// Filter & return
	return apply_filters( 'bbp_get_topic_tags', $topic_tags, $topic_id );
}

/**
 * Get topic tags for a specific topic ID
 *
 * @since 2.2.0 bbPress (r4165)
 *
 * @param int    $topic_id
 * @param string $sep
 *
 * @return string
 */
function bbp_get_topic_tag_names( $topic_id = 0, $sep = ', ' ) {
	$topic_tags = bbp_get_topic_tags( $topic_id );
	$pluck      = wp_list_pluck( $topic_tags, 'name' );
	$terms      = ! empty( $pluck )
		? implode( $sep, $pluck )
		: '';

	// Filter & return
	return apply_filters( 'bbp_get_topic_tag_names', $terms, $topic_id, $sep );
}

/**
 * Will update topic-tag count based on object type.
 *
 * Function for the default callback for topic-tag taxonomies.
 *
 * @see https://bbpress.trac.wordpress.org/ticket/3043
 * @access private
 *
 * @since 2.6.0 bbPress (r6253)
 *
 * @param array  $terms    List of Term taxonomy IDs.
 * @param object $taxonomy Current taxonomy object of terms.
 */
function bbp_update_topic_tag_count( $terms, $taxonomy ) {

	// Bail if no object types are available
	if ( empty( $terms ) || empty( $taxonomy->object_type ) ) {
		return;
	}

	// Get object types
	$object_types = (array) $taxonomy->object_type;

	foreach ( $object_types as &$object_type ) {
		list( $object_type ) = explode( ':', $object_type );
	}

	$object_types = array_unique( $object_types );

	if ( ! empty( $object_types ) ) {
		$object_types = esc_sql( array_filter( $object_types, 'post_type_exists' ) );
	}

	// Statuses to count
	$object_statuses = bbp_get_public_topic_statuses();

	// Get database
	$bbp_db = bbp_db();

	// Loop through terms, maybe update counts
	foreach ( (array) $terms as $term ) {
		$count = 0;

		// Get count, and bump it
		if ( ! empty( $object_types ) ) {
			$query    = "SELECT COUNT(*) FROM {$bbp_db->term_relationships}, {$bbp_db->posts} WHERE {$bbp_db->posts}.ID = {$bbp_db->term_relationships}.object_id AND post_status IN ('" . implode("', '", $object_statuses ) . "') AND post_type IN ('" . implode("', '", $object_types ) . "') AND term_taxonomy_id = %d";
			$prepare  = $bbp_db->prepare( $query, $term );
			$count   += (int) $bbp_db->get_var( $prepare );
		}

		/** This action is documented in wp-includes/taxonomy.php */
		do_action( 'edit_term_taxonomy', $term, $taxonomy->name );
		$bbp_db->update( $bbp_db->term_taxonomy, compact( 'count' ), array( 'term_taxonomy_id' => $term ) );

		/** This action is documented in wp-includes/taxonomy.php */
		do_action( 'edited_term_taxonomy', $term, $taxonomy->name );
	}
}

/** Autoembed *****************************************************************/

/**
 * Check if autoembeds are enabled and hook them in if so
 *
 * @since 2.1.0 bbPress (r3752)
 *
 * @global WP_Embed $wp_embed
 */
function bbp_topic_content_autoembed() {
	global $wp_embed;

	if ( bbp_use_autoembed() && is_a( $wp_embed, 'WP_Embed' ) ) {
		add_filter( 'bbp_get_topic_content', array( $wp_embed, 'autoembed' ), 2 );
	}
}

/** Feeds *********************************************************************/

/**
 * Output an RSS2 feed of topics, based on the query passed.
 *
 * @since 2.0.0 bbPress (r3171)
 *
 * @param array $topics_query
 */
function bbp_display_topics_feed_rss2( $topics_query = array() ) {

	// User cannot access this forum
	if ( bbp_is_single_forum() && ! bbp_user_can_view_forum( array( 'forum_id' => bbp_get_forum_id() ) ) ) {
		return;
	}

	// Feed title
	$title = get_bloginfo_rss( 'name' ) . ' &#187; ' . esc_html__( 'All Topics', 'bbpress' );
	$title = apply_filters( 'wp_title_rss', $title );

	// Display the feed
	header( 'Content-Type: ' . feed_content_type( 'rss2' ) . '; charset=' . get_option( 'blog_charset' ), true );
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

		<title><?php echo $title; // Already escaped ?></title>
		<atom:link href="<?php self_link(); ?>" rel="self" type="application/rss+xml" />
		<link><?php self_link(); ?></link>
		<description><?php //?></description>
		<lastBuildDate><?php echo date( 'r' ); ?></lastBuildDate>
		<generator><?php echo esc_url_raw( 'https://bbpress.org/?v=' . convert_chars( bbp_get_version() ) ); ?></generator>
		<language><?php bloginfo_rss( 'language' ); ?></language>

		<?php do_action( 'bbp_feed_head' ); ?>

		<?php if ( bbp_has_topics( $topics_query ) ) : ?>

			<?php while ( bbp_topics() ) : bbp_the_topic(); ?>

				<item>
					<guid><?php bbp_topic_permalink(); ?></guid>
					<title><![CDATA[<?php bbp_topic_title(); ?>]]></title>
					<link><?php bbp_topic_permalink(); ?></link>
					<pubDate><?php echo mysql2date( 'D, d M Y H:i:s +0000', get_post_meta( bbp_get_topic_id(), '_bbp_last_active_time', true ), false ); ?></pubDate>
					<dc:creator><?php the_author() ?></dc:creator>

					<?php if ( !post_password_required() ) : ?>

					<description>
						<![CDATA[
						<p><?php printf( esc_html__( 'Replies: %s', 'bbpress' ), bbp_get_topic_reply_count() ); ?></p>
						<?php bbp_topic_content(); ?>
						]]>
					</description>

					<?php rss_enclosure(); ?>

					<?php endif; ?>

					<?php do_action( 'bbp_feed_item' ); ?>

				</item>

				<?php endwhile; ?>
			<?php endif; ?>

		<?php do_action( 'bbp_feed_footer' ); ?>

	</channel>
	</rss>

<?php
	exit();
}

/** Permissions ***************************************************************/

/**
 * Redirect if unauthorized user is attempting to edit a topic
 *
 * @since 2.1.0 bbPress (r3605)
 */
function bbp_check_topic_edit() {

	// Bail if not editing a topic
	if ( ! bbp_is_topic_edit() ) {
		return;
	}

	// User cannot edit topic, so redirect back to topic
	if ( ! current_user_can( 'edit_topic', bbp_get_topic_id() ) ) {
		bbp_redirect( bbp_get_topic_permalink() );
	}
}

/**
 * Redirect if unauthorized user is attempting to edit a topic tag
 *
 * @since 2.1.0 bbPress (r3605)
 */
function bbp_check_topic_tag_edit() {

	// Bail if not editing a topic tag
	if ( ! bbp_is_topic_tag_edit() ) {
		return;
	}

	// Bail if current user cannot edit topic tags
	if ( ! current_user_can( 'edit_topic_tag', bbp_get_topic_tag_id() ) ) {
		bbp_redirect( bbp_get_topic_tag_link() );
	}
}
