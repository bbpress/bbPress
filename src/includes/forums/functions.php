<?php

/**
 * bbPress Forum Functions
 *
 * @package bbPress
 * @subpackage Functions
 */

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

/** Insert ********************************************************************/

/**
 * A wrapper for wp_insert_post() that also includes the necessary meta values
 * for the forum to function properly.
 *
 * @since 2.0.0 bbPress (r3349)
 *
 * @param array $forum_data Forum post data
 * @param array $forum_meta Forum meta data
 */
function bbp_insert_forum( $forum_data = array(), $forum_meta = array() ) {

	// Forum
	$forum_data = bbp_parse_args( $forum_data, array(
		'post_parent'    => 0, // forum ID
		'post_status'    => bbp_get_public_status_id(),
		'post_type'      => bbp_get_forum_post_type(),
		'post_author'    => bbp_get_current_user_id(),
		'post_password'  => '',
		'post_content'   => '',
		'post_title'     => '',
		'menu_order'     => 0,
		'comment_status' => 'closed'
	), 'insert_forum' );

	// Insert forum
	$forum_id = wp_insert_post( $forum_data, false );

	// Bail if no forum was added
	if ( empty( $forum_id ) ) {
		return false;
	}

	// Forum meta
	$forum_meta = bbp_parse_args( $forum_meta, array(
		'forum_type'           => 'forum',
		'status'               => 'open',
		'reply_count'          => 0,
		'topic_count'          => 0,
		'topic_count_hidden'   => 0,
		'total_reply_count'    => 0,
		'total_topic_count'    => 0,
		'last_topic_id'        => 0,
		'last_reply_id'        => 0,
		'last_active_id'       => 0,
		'last_active_time'     => 0,
		'forum_subforum_count' => 0,
	), 'insert_forum_meta' );

	// Insert forum meta
	foreach ( $forum_meta as $meta_key => $meta_value ) {

		// Prefix if not prefixed
		if ( '_bbp_' !== substr( $meta_key, 0, 5 ) ) {
			$meta_key = '_bbp_' . $meta_key;
		}

		// Update the meta
		update_post_meta( $forum_id, $meta_key, $meta_value );
	}

	// Update the forum and hierarchy
	bbp_update_forum( array(
		'forum_id'    => $forum_id,
		'post_parent' => $forum_data['post_parent']
	) );

	// Maybe make private
	if ( bbp_is_forum_private( $forum_id, false ) ) {
		bbp_privatize_forum( $forum_id );

	// Maybe make hidden
	} elseif ( bbp_is_forum_hidden( $forum_id, false ) ) {
		bbp_hide_forum( $forum_id );

	// Publicize
	} else {
		bbp_publicize_forum( $forum_id );
	}

	/**
	 * Fires after forum has been inserted via `bbp_insert_forum`.
	 *
	 * @since 2.6.0 bbPress (r6036)
	 *
	 * @param int $forum_id The forum id.
	 */
	do_action( 'bbp_insert_forum', (int) $forum_id );

	// Bump the last changed cache
	wp_cache_set( 'last_changed', microtime(), 'bbpress_posts' );

	// Return forum_id
	return $forum_id;
}

/** Post Form Handlers ********************************************************/

/**
 * Handles the front end forum submission
 *
 * @param string $action The requested action to compare this function to
 */
function bbp_new_forum_handler( $action = '' ) {

	// Bail if action is not bbp-new-forum
	if ( 'bbp-new-forum' !== $action ) {
		return;
	}

	// Nonce check
	if ( ! bbp_verify_nonce_request( 'bbp-new-forum' ) ) {
		bbp_add_error( 'bbp_new_forum_nonce', __( '<strong>Error</strong>: Are you sure you wanted to do that?', 'bbpress' ) );
		return;
	}

	// Define local variable(s)
	$view_all = false;
	$forum_parent_id = $forum_author = 0;
	$forum_title = $forum_content = '';
	$anonymous_data = array();

	/** Forum Author **********************************************************/

	// User cannot create forums
	if ( ! current_user_can( 'publish_forums' ) ) {
		bbp_add_error( 'bbp_forum_permission', __( '<strong>Error</strong>: You do not have permission to create new forums.', 'bbpress' ) );
		return;
	}

	// Forum author is current user
	$forum_author = bbp_get_current_user_id();

	// Remove kses filters from title and content for capable users and if the nonce is verified
	if ( current_user_can( 'unfiltered_html' ) && ! empty( $_POST['_bbp_unfiltered_html_forum'] ) && wp_create_nonce( 'bbp-unfiltered-html-forum_new' ) === $_POST['_bbp_unfiltered_html_forum'] ) {
		remove_filter( 'bbp_new_forum_pre_title',   'wp_filter_kses'      );
		remove_filter( 'bbp_new_forum_pre_content', 'bbp_encode_bad',  10 );
		remove_filter( 'bbp_new_forum_pre_content', 'bbp_filter_kses', 30 );
	}

	/** Forum Title ***********************************************************/

	if ( ! empty( $_POST['bbp_forum_title'] ) ) {
		$forum_title = sanitize_text_field( $_POST['bbp_forum_title'] );
	}

	// Filter and sanitize
	$forum_title = apply_filters( 'bbp_new_forum_pre_title', $forum_title );

	// No forum title
	if ( empty( $forum_title ) ) {
		bbp_add_error( 'bbp_forum_title', __( '<strong>Error</strong>: Your forum needs a title.', 'bbpress' ) );
	}

	// Title too long
	if ( bbp_is_title_too_long( $forum_title ) ) {
		bbp_add_error( 'bbp_forum_title', __( '<strong>Error</strong>: Your title is too long.', 'bbpress' ) );
	}

	/** Forum Content *********************************************************/

	if ( ! empty( $_POST['bbp_forum_content'] ) ) {
		$forum_content = $_POST['bbp_forum_content'];
	}

	// Filter and sanitize
	$forum_content = apply_filters( 'bbp_new_forum_pre_content', $forum_content );

	// No forum content
	if ( empty( $forum_content ) ) {
		bbp_add_error( 'bbp_forum_content', __( '<strong>Error</strong>: Your forum description cannot be empty.', 'bbpress' ) );
	}

	/** Forum Parent **********************************************************/

	// Forum parent was passed (the norm)
	if ( ! empty( $_POST['bbp_forum_parent_id'] ) ) {
		$forum_parent_id = bbp_get_forum_id( $_POST['bbp_forum_parent_id'] );
	}

	// Filter and sanitize
	$forum_parent_id = apply_filters( 'bbp_new_forum_pre_parent_id', $forum_parent_id );

	// No forum parent was passed (should never happen)
	if ( empty( $forum_parent_id ) ) {
		bbp_add_error( 'bbp_new_forum_missing_parent', __( '<strong>Error</strong>: Your forum must have a parent.', 'bbpress' ) );

	// Forum exists
	} elseif ( ! empty( $forum_parent_id ) ) {

		// Forum is a category
		if ( bbp_is_forum_category( $forum_parent_id ) ) {
			bbp_add_error( 'bbp_new_forum_forum_category', __( '<strong>Error</strong>: This forum is a category. No forums can be created in this forum.', 'bbpress' ) );
		}

		// Forum is closed and user cannot access
		if ( bbp_is_forum_closed( $forum_parent_id ) && ! current_user_can( 'edit_forum', $forum_parent_id ) ) {
			bbp_add_error( 'bbp_new_forum_forum_closed', __( '<strong>Error</strong>: This forum has been closed to new forums.', 'bbpress' ) );
		}

		// Forum is private and user cannot access
		if ( bbp_is_forum_private( $forum_parent_id ) && ! current_user_can( 'read_forum', $forum_parent_id ) ) {
			bbp_add_error( 'bbp_new_forum_forum_private', __( '<strong>Error</strong>: This forum is private and you do not have the capability to read or create new forums in it.', 'bbpress' ) );
		}

		// Forum is hidden and user cannot access
		if ( bbp_is_forum_hidden( $forum_parent_id ) && ! current_user_can( 'read_forum', $forum_parent_id ) ) {
			bbp_add_error( 'bbp_new_forum_forum_hidden', __( '<strong>Error</strong>: This forum is hidden and you do not have the capability to read or create new forums in it.', 'bbpress' ) );
		}
	}

	/** Forum Flooding ********************************************************/

	if ( ! bbp_check_for_flood( $anonymous_data, $forum_author ) ) {
		bbp_add_error( 'bbp_forum_flood', __( '<strong>Error</strong>: Slow down; you move too fast.', 'bbpress' ) );
	}

	/** Forum Duplicate *******************************************************/

	$dupe_args = array(
		'post_type'      => bbp_get_forum_post_type(),
		'post_author'    => $forum_author,
		'post_content'   => $forum_content,
		'post_parent'    => $forum_parent_id,
		'anonymous_data' => $anonymous_data
	);

	if ( ! bbp_check_for_duplicate( $dupe_args ) ) {
		bbp_add_error( 'bbp_forum_duplicate', __( '<strong>Error</strong>: This forum already exists.', 'bbpress' ) );
	}

	/** Forum Bad Words *******************************************************/

	if ( ! bbp_check_for_moderation( $anonymous_data, $forum_author, $forum_title, $forum_content, true ) ) {
		bbp_add_error( 'bbp_forum_moderation', __( '<strong>Error</strong>: Your forum cannot be created at this time.', 'bbpress' ) );
	}

	/** Forum Moderation ******************************************************/

	// Default to published
	$forum_status = bbp_get_public_status_id();

	// Maybe force into pending
	if ( ! bbp_check_for_moderation( $anonymous_data, $forum_author, $forum_title, $forum_content ) ) {
		$forum_status = bbp_get_pending_status_id();
	}

	/** Additional Actions (Before Save) **************************************/

	do_action( 'bbp_new_forum_pre_extras', $forum_parent_id );

	// Bail if errors
	if ( bbp_has_errors() ) {
		return;
	}

	/** No Errors *************************************************************/

	// Add the content of the form to $forum_data as an array
	// Just in time manipulation of forum data before being created
	$forum_data = apply_filters( 'bbp_new_forum_pre_insert', array(
		'post_author'    => $forum_author,
		'post_title'     => $forum_title,
		'post_content'   => $forum_content,
		'post_parent'    => $forum_parent_id,
		'post_status'    => $forum_status,
		'post_type'      => bbp_get_forum_post_type(),
		'comment_status' => 'closed'
	) );

	// Insert forum
	$forum_id = wp_insert_post( $forum_data, true );

	/** No Errors *************************************************************/

	if ( ! empty( $forum_id ) && ! is_wp_error( $forum_id ) ) {

		/** Trash Check *******************************************************/

		// If the forum is trash, or the forum_status is switched to
		// trash, trash it properly
		if ( ( get_post_field( 'post_status', $forum_id ) === bbp_get_trash_status_id() ) || ( $forum_data['post_status'] === bbp_get_trash_status_id() ) ) {

			// Trash the reply
			wp_trash_post( $forum_id );

			// Force view=all
			$view_all = true;
		}

		/** Spam Check ********************************************************/

		// If reply or forum are spam, officially spam this reply
		if ( $forum_data['post_status'] === bbp_get_spam_status_id() ) {
			add_post_meta( $forum_id, '_bbp_spam_meta_status', bbp_get_public_status_id() );

			// Force view=all
			$view_all = true;
		}

		/** Update counts, etc... *********************************************/

		do_action( 'bbp_new_forum', array(
			'forum_id'           => $forum_id,
			'post_parent'        => $forum_parent_id,
			'forum_author'       => $forum_author,
			'last_topic_id'      => 0,
			'last_reply_id'      => 0,
			'last_active_id'     => 0,
			'last_active_time'   => 0,
			'last_active_status' => bbp_get_public_status_id()
		) );

		/** Additional Actions (After Save) ***********************************/

		do_action( 'bbp_new_forum_post_extras', $forum_id );

		/** Redirect **********************************************************/

		// Redirect to
		$redirect_to  = bbp_get_redirect_to();

		// Get the forum URL
		$redirect_url = bbp_get_forum_permalink( $forum_id, $redirect_to );

		// Add view all?
		if ( bbp_get_view_all() || ! empty( $view_all ) ) {

			// User can moderate, so redirect to forum with view all set
			if ( current_user_can( 'moderate', $forum_id ) ) {
				$redirect_url = bbp_add_view_all( $redirect_url );

			// User cannot moderate, so redirect to forum
			} else {
				$redirect_url = bbp_get_forum_permalink( $forum_id );
			}
		}

		// Allow to be filtered
		$redirect_url = apply_filters( 'bbp_new_forum_redirect_to', $redirect_url, $redirect_to );

		/** Successful Save ***************************************************/

		// Redirect back to new forum
		bbp_redirect( $redirect_url );

	/** Errors ****************************************************************/

	// WP_Error
	} elseif ( is_wp_error( $forum_id ) ) {
		bbp_add_error( 'bbp_forum_error', sprintf( __( '<strong>Error</strong>: The following problem(s) occurred: %s', 'bbpress' ), $forum_id->get_error_message() ) );

	// Generic error
	} else {
		bbp_add_error( 'bbp_forum_error', __( '<strong>Error</strong>: The forum was not created.', 'bbpress' ) );
	}
}

/**
 * Handles the front end edit forum submission
 *
 * @param string $action The requested action to compare this function to
 */
function bbp_edit_forum_handler( $action = '' ) {

	// Bail if action is not bbp-edit-forum
	if ( 'bbp-edit-forum' !== $action ) {
		return;
	}

	// Define local variable(s)
	$anonymous_data = array();
	$forum = $forum_id = $forum_parent_id = 0;
	$forum_title = $forum_content = $forum_edit_reason = '';

	/** Forum *****************************************************************/

	// Forum id was not passed
	if ( empty( $_POST['bbp_forum_id'] ) ) {
		bbp_add_error( 'bbp_edit_forum_id', __( '<strong>Error</strong>: Forum ID not found.', 'bbpress' ) );
		return;

	// Forum id was passed
	} elseif ( is_numeric( $_POST['bbp_forum_id'] ) ) {
		$forum_id = (int) $_POST['bbp_forum_id'];
		$forum    = bbp_get_forum( $forum_id );
	}

	// Nonce check
	if ( ! bbp_verify_nonce_request( 'bbp-edit-forum_' . $forum_id ) ) {
		bbp_add_error( 'bbp_edit_forum_nonce', __( '<strong>Error</strong>: Are you sure you wanted to do that?', 'bbpress' ) );
		return;

	// Forum does not exist
	} elseif ( empty( $forum ) ) {
		bbp_add_error( 'bbp_edit_forum_not_found', __( '<strong>Error</strong>: The forum you want to edit was not found.', 'bbpress' ) );
		return;

	// User cannot edit this forum
	} elseif ( ! current_user_can( 'edit_forum', $forum_id ) ) {
		bbp_add_error( 'bbp_edit_forum_permission', __( '<strong>Error</strong>: You do not have permission to edit that forum.', 'bbpress' ) );
		return;
	}

	// Remove kses filters from title and content for capable users and if the nonce is verified
	if ( current_user_can( 'unfiltered_html' ) && ! empty( $_POST['_bbp_unfiltered_html_forum'] ) && ( wp_create_nonce( 'bbp-unfiltered-html-forum_' . $forum_id ) === $_POST['_bbp_unfiltered_html_forum'] ) ) {
		remove_filter( 'bbp_edit_forum_pre_title',   'wp_filter_kses'      );
		remove_filter( 'bbp_edit_forum_pre_content', 'bbp_encode_bad',  10 );
		remove_filter( 'bbp_edit_forum_pre_content', 'bbp_filter_kses', 30 );
	}

	/** Forum Parent ***********************************************************/

	// Forum parent id was passed
	if ( ! empty( $_POST['bbp_forum_parent_id'] ) ) {
		$forum_parent_id = bbp_get_forum_id( $_POST['bbp_forum_parent_id'] );
	}

	// Current forum this forum is in
	$current_parent_forum_id = bbp_get_forum_parent_id( $forum_id );

	// Forum exists
	if ( ! empty( $forum_parent_id ) && ( $forum_parent_id !== $current_parent_forum_id ) ) {

		// Forum is closed and user cannot access
		if ( bbp_is_forum_closed( $forum_parent_id ) && ! current_user_can( 'edit_forum', $forum_parent_id ) ) {
			bbp_add_error( 'bbp_edit_forum_forum_closed', __( '<strong>Error</strong>: This forum has been closed to new forums.', 'bbpress' ) );
		}

		// Forum is private and user cannot access
		if ( bbp_is_forum_private( $forum_parent_id ) && ! current_user_can( 'read_forum', $forum_parent_id ) ) {
			bbp_add_error( 'bbp_edit_forum_forum_private', __( '<strong>Error</strong>: This forum is private and you do not have the capability to read or create new forums in it.', 'bbpress' ) );
		}

		// Forum is hidden and user cannot access
		if ( bbp_is_forum_hidden( $forum_parent_id ) && ! current_user_can( 'read_forum', $forum_parent_id ) ) {
			bbp_add_error( 'bbp_edit_forum_forum_hidden', __( '<strong>Error</strong>: This forum is hidden and you do not have the capability to read or create new forums in it.', 'bbpress' ) );
		}
	}

	/** Forum Title ***********************************************************/

	if ( ! empty( $_POST['bbp_forum_title'] ) ) {
		$forum_title = sanitize_text_field( $_POST['bbp_forum_title'] );
	}

	// Filter and sanitize
	$forum_title = apply_filters( 'bbp_edit_forum_pre_title', $forum_title, $forum_id );

	// No forum title
	if ( empty( $forum_title ) ) {
		bbp_add_error( 'bbp_edit_forum_title', __( '<strong>Error</strong>: Your forum needs a title.', 'bbpress' ) );
	}

	// Title too long
	if ( bbp_is_title_too_long( $forum_title ) ) {
		bbp_add_error( 'bbp_forum_title', __( '<strong>Error</strong>: Your title is too long.', 'bbpress' ) );
	}

	/** Forum Content *********************************************************/

	if ( ! empty( $_POST['bbp_forum_content'] ) ) {
		$forum_content = $_POST['bbp_forum_content'];
	}

	// Filter and sanitize
	$forum_content = apply_filters( 'bbp_edit_forum_pre_content', $forum_content, $forum_id );

	// No forum content
	if ( empty( $forum_content ) ) {
		bbp_add_error( 'bbp_edit_forum_content', __( '<strong>Error</strong>: Your forum description cannot be empty.', 'bbpress' ) );
	}

	/** Forum Bad Words *******************************************************/

	if ( ! bbp_check_for_moderation( $anonymous_data, bbp_get_forum_author_id( $forum_id ), $forum_title, $forum_content, true ) ) {
		bbp_add_error( 'bbp_forum_moderation', __( '<strong>Error</strong>: Your forum cannot be edited at this time.', 'bbpress' ) );
	}

	/** Forum Moderation ******************************************************/

	// Use existing post_status
	$forum_status = $forum->post_status;

	// Maybe force into pending
	if ( ! bbp_check_for_moderation( $anonymous_data, bbp_get_forum_author_id( $forum_id ), $forum_title, $forum_content ) ) {
		$forum_status = bbp_get_pending_status_id();
	}

	/** Additional Actions (Before Save) **************************************/

	do_action( 'bbp_edit_forum_pre_extras', $forum_id );

	// Bail if errors
	if ( bbp_has_errors() ) {
		return;
	}

	/** No Errors *************************************************************/

	// Add the content of the form to $forum_data as an array
	// Just in time manipulation of forum data before being edited
	$forum_data = apply_filters( 'bbp_edit_forum_pre_insert', array(
		'ID'           => $forum_id,
		'post_title'   => $forum_title,
		'post_content' => $forum_content,
		'post_status'  => $forum_status,
		'post_parent'  => $forum_parent_id
	) );

	// Insert forum
	$forum_id = wp_update_post( $forum_data );

	/** No Errors *************************************************************/

	if ( ! empty( $forum_id ) && ! is_wp_error( $forum_id ) ) {

		// Update counts, etc...
		do_action( 'bbp_edit_forum', array(
			'forum_id'           => $forum_id,
			'post_parent'        => $forum_parent_id,
			'forum_author'       => $forum->post_author,
			'last_topic_id'      => 0,
			'last_reply_id'      => 0,
			'last_active_id'     => 0,
			'last_active_time'   => 0,
			'last_active_status' => bbp_get_public_status_id()
		) );

		/** Revisions *********************************************************/

		// Update locks
		update_post_meta( $forum_id, '_edit_last', bbp_get_current_user_id() );
		delete_post_meta( $forum_id, '_edit_lock' );

		/**
		 * @todo omitted for now
		// Revision Reason
		if ( ! empty( $_POST['bbp_forum_edit_reason'] ) )
			$forum_edit_reason = sanitize_text_field( $_POST['bbp_forum_edit_reason'] );

		// Update revision log
		if ( ! empty( $_POST['bbp_log_forum_edit'] ) && ( "1" === $_POST['bbp_log_forum_edit'] ) && ( $revision_id = wp_save_post_revision( $forum_id ) ) ) {
			bbp_update_forum_revision_log( array(
				'forum_id'    => $forum_id,
				'revision_id' => $revision_id,
				'author_id'   => bbp_get_current_user_id(),
				'reason'      => $forum_edit_reason
			) );
		}

		// If the new forum parent id is not equal to the old forum parent
		// id, run the bbp_move_forum action and pass the forum's parent id
		// as the first argument and new forum parent id as the second.
		// @todo implement
		if ( $forum_id !== $forum->post_parent ) {
			bbp_move_forum_handler( $forum_parent_id, $forum->post_parent, $forum_id );
		}

		*/

		/** Additional Actions (After Save) ***********************************/

		do_action( 'bbp_edit_forum_post_extras', $forum_id );

		/** Redirect **********************************************************/

		// Redirect to
		$redirect_to = bbp_get_redirect_to();

		// View all?
		$view_all = bbp_get_view_all();

		// Get the forum URL
		$forum_url = bbp_get_forum_permalink( $forum_id, $redirect_to );

		// Add view all?
		if ( ! empty( $view_all ) ) {
			$forum_url = bbp_add_view_all( $forum_url );
		}

		// Allow to be filtered
		$forum_url = apply_filters( 'bbp_edit_forum_redirect_to', $forum_url, $view_all, $redirect_to );

		/** Successful Edit ***************************************************/

		// Redirect back to new forum
		bbp_redirect( $forum_url );

	/** Errors ****************************************************************/

	} else {
		$append_error = ( is_wp_error( $forum_id ) && $forum_id->get_error_message() ) ? $forum_id->get_error_message() . ' ' : '';
		bbp_add_error( 'bbp_forum_error', __( '<strong>Error</strong>: The following problem(s) have been found with your forum:' . $append_error . 'Please try again.', 'bbpress' ) );
	}
}

/**
 * Handle the saving of core forum metadata (Status, Visibility, and Type)
 *
 * @since 2.1.0 bbPress (r3678)
 *
 * @param int $forum_id
 * @return If forum ID is empty
 */
function bbp_save_forum_extras( $forum_id = 0 ) {

	// Validate the forum ID
	$forum_id = bbp_get_forum_id( $forum_id );

	// Bail if forum ID is empty
	if ( empty( $forum_id ) || ! bbp_is_forum( $forum_id ) ) {
		return;
	}

	/** Forum Status **********************************************************/

	if ( ! empty( $_POST['bbp_forum_status'] ) && in_array( $_POST['bbp_forum_status'], array( 'open', 'closed' ), true ) ) {
		if ( 'closed' === $_POST['bbp_forum_status'] && ! bbp_is_forum_closed( $forum_id, false ) ) {
			bbp_close_forum( $forum_id );
		} elseif ( 'open' === $_POST['bbp_forum_status'] && bbp_is_forum_open( $forum_id, false ) ) {
			bbp_open_forum( $forum_id );
		} elseif ( 'open' === $_POST['bbp_forum_status'] && bbp_is_forum_closed( $forum_id, false ) ) {
			bbp_open_forum( $forum_id );
		}
	}

	/** Forum Type ************************************************************/

	if ( ! empty( $_POST['bbp_forum_type'] ) && in_array( $_POST['bbp_forum_type'], array( 'forum', 'category' ), true ) ) {
		if ( 'category' === $_POST['bbp_forum_type'] && ! bbp_is_forum_category( $forum_id ) ) {
			bbp_categorize_forum( $forum_id );
		} elseif ( 'forum' === $_POST['bbp_forum_type'] && ! bbp_is_forum_category( $forum_id ) ) {
			bbp_normalize_forum( $forum_id );
		} elseif ( 'forum' === $_POST['bbp_forum_type'] && bbp_is_forum_category( $forum_id ) ) {
			bbp_normalize_forum( $forum_id );
		}
	}

	/** Forum Visibility ******************************************************/

	if ( ! empty( $_POST['bbp_forum_visibility'] ) && in_array( $_POST['bbp_forum_visibility'], array_keys( bbp_get_forum_visibilities() ), true ) ) {

		// Get forums current visibility
		$old_visibility = bbp_get_forum_visibility( $forum_id );

		// Sanitize the new visibility
		$new_visibility = sanitize_key( $_POST['bbp_forum_visibility'] );

		// What is the new forum visibility setting?
		switch ( $new_visibility ) {

			// Hidden
			case bbp_get_hidden_status_id()  :
				bbp_hide_forum( $forum_id, $old_visibility );
				break;

			// Private
			case bbp_get_private_status_id() :
				bbp_privatize_forum( $forum_id, $old_visibility );
				break;

			// Publish (default)
			case bbp_get_public_status_id()  :
			default :
				bbp_publicize_forum( $forum_id, $old_visibility );
				break;
		}

		/**
		 * Allow custom forum visibility save actions
		 *
		 * @since 2.6.0 bbPress (r5855)
		 *
		 * @param int    $forum_id       The forum ID
		 * @param string $old_visibility The current forum visibility
		 * @param string $new_visibility The new forum visibility
		 */
		do_action( 'bbp_update_forum_visibility', $forum_id, $old_visibility, $new_visibility );
	}

	/** Forum Moderators ******************************************************/

	// Either replace terms
	if ( bbp_allow_forum_mods() ) {
		if ( current_user_can( 'assign_moderators' ) && ! empty( $_POST['bbp_moderators'] ) ) {

			// Escape tag input
			$users    = sanitize_text_field( $_POST['bbp_moderators'] );
			$user_ids = bbp_get_user_ids_from_nicenames( $users );

			// Update forum moderators
			if ( ! empty( $user_ids ) ) {

				// Remove all moderators
				bbp_remove_moderator( $forum_id, null );

				// Add moderators
				foreach ( $user_ids as $user_id ) {
					bbp_add_moderator( $forum_id, $user_id );
				}
			}

		// ...or remove them.
		} elseif ( isset( $_POST['bbp_moderators'] ) ) {
			bbp_remove_moderator( $forum_id, null );
		}
	}
}

/** Forum Open/Close **********************************************************/

/**
 * Closes a forum
 *
 * @since 2.0.0 bbPress (r2746)
 *
 * @param int $forum_id forum id
 * @return mixed False or {@link WP_Error} on failure, forum id on success
 */
function bbp_close_forum( $forum_id = 0 ) {

	$forum_id = bbp_get_forum_id( $forum_id );

	do_action( 'bbp_close_forum',  $forum_id );

	update_post_meta( $forum_id, '_bbp_status', 'closed' );

	do_action( 'bbp_closed_forum', $forum_id );

	return $forum_id;
}

/**
 * Opens a forum
 *
 * @since 2.0.0 bbPress (r2746)
 *
 * @param int $forum_id forum id
 * @return mixed False or {@link WP_Error} on failure, forum id on success
 */
function bbp_open_forum( $forum_id = 0 ) {

	$forum_id = bbp_get_forum_id( $forum_id );

	do_action( 'bbp_open_forum',   $forum_id );

	update_post_meta( $forum_id, '_bbp_status', 'open' );

	do_action( 'bbp_opened_forum', $forum_id );

	return $forum_id;
}

/** Forum Type ****************************************************************/

/**
 * Make the forum a category
 *
 * @since 2.0.0 bbPress (r2746)
 *
 * @param int $forum_id Optional. Forum id
 * @return bool False on failure, true on success
 */
function bbp_categorize_forum( $forum_id = 0 ) {

	$forum_id = bbp_get_forum_id( $forum_id );

	do_action( 'bbp_categorize_forum',  $forum_id );

	update_post_meta( $forum_id, '_bbp_forum_type', 'category' );

	do_action( 'bbp_categorized_forum', $forum_id );

	return $forum_id;
}

/**
 * Remove the category status from a forum
 *
 * @since 2.0.0 bbPress (r2746)
 *
 * @param int $forum_id Optional. Forum id
 * @return bool False on failure, true on success
 */
function bbp_normalize_forum( $forum_id = 0 ) {

	$forum_id = bbp_get_forum_id( $forum_id );

	do_action( 'bbp_normalize_forum',  $forum_id );

	update_post_meta( $forum_id, '_bbp_forum_type', 'forum' );

	do_action( 'bbp_normalized_forum', $forum_id );

	return $forum_id;
}

/** Forum Visibility **********************************************************/

/**
 * Mark the forum as public
 *
 * @since 2.0.0 bbPress (r2746)
 *
 * @param int $forum_id Optional. Forum id
 * @return bool False on failure, true on success
 */
function bbp_publicize_forum( $forum_id = 0, $current_visibility = '' ) {

	$forum_id = bbp_get_forum_id( $forum_id );

	do_action( 'bbp_publicize_forum',  $forum_id );

	// Get private forums
	$private = bbp_get_private_forum_ids();

	// Find this forum in the array
	if ( in_array( $forum_id, $private, true ) ) {

		$offset = array_search( $forum_id, $private, true );

		// Splice around it
		array_splice( $private, $offset, 1 );

		// Update private forums minus this one
		update_option( '_bbp_private_forums', bbp_get_unique_array_values( $private ) );
	}

	// Get hidden forums
	$hidden = bbp_get_hidden_forum_ids();

	// Find this forum in the array
	if ( in_array( $forum_id, $hidden, true ) ) {

		$offset = array_search( $forum_id, $hidden, true );

		// Splice around it
		array_splice( $hidden, $offset, 1 );

		// Update hidden forums minus this one
		update_option( '_bbp_hidden_forums', bbp_get_unique_array_values( $hidden ) );
	}

	// Only run queries if visibility is changing
	if ( bbp_get_public_status_id() !== $current_visibility ) {
		$bbp_db = bbp_db();
		$bbp_db->update( $bbp_db->posts, array( 'post_status' => bbp_get_public_status_id() ), array( 'ID' => $forum_id ) );
		wp_transition_post_status( bbp_get_public_status_id(), $current_visibility, get_post( $forum_id ) );
		clean_post_cache( $forum_id );
	}

	do_action( 'bbp_publicized_forum', $forum_id );

	return $forum_id;
}

/**
 * Mark the forum as private
 *
 * @since 2.0.0 bbPress (r2746)
 *
 * @param int $forum_id Optional. Forum id
 * @return bool False on failure, true on success
 */
function bbp_privatize_forum( $forum_id = 0, $current_visibility = '' ) {

	$forum_id = bbp_get_forum_id( $forum_id );

	do_action( 'bbp_privatize_forum',  $forum_id );

	// Only run queries if visibility is changing
	if ( bbp_get_private_status_id() !== $current_visibility ) {

		// Get hidden forums
		$hidden = bbp_get_hidden_forum_ids();

		// Find this forum in the array
		if ( in_array( $forum_id, $hidden, true ) ) {

			$offset = array_search( $forum_id, $hidden, true );

			// Splice around it
			array_splice( $hidden, $offset, 1 );

			// Update hidden forums minus this one
			update_option( '_bbp_hidden_forums', bbp_get_unique_array_values( $hidden ) );
		}

		// Add to '_bbp_private_forums' site option
		$private   = bbp_get_private_forum_ids();
		$private[] = $forum_id;
		update_option( '_bbp_private_forums', bbp_get_unique_array_values( $private ) );

		// Update forums visibility setting
		$bbp_db = bbp_db();
		$bbp_db->update( $bbp_db->posts, array( 'post_status' => bbp_get_private_status_id() ), array( 'ID' => $forum_id ) );
		wp_transition_post_status( bbp_get_private_status_id(), $current_visibility, get_post( $forum_id ) );
		clean_post_cache( $forum_id );
	}

	do_action( 'bbp_privatized_forum', $forum_id );

	return $forum_id;
}

/**
 * Mark the forum as hidden
 *
 * @since 2.0.0 bbPress (r2996)
 *
 * @param int $forum_id Optional. Forum id
 * @return bool False on failure, true on success
 */
function bbp_hide_forum( $forum_id = 0, $current_visibility = '' ) {

	$forum_id = bbp_get_forum_id( $forum_id );

	do_action( 'bbp_hide_forum', $forum_id );

	// Only run queries if visibility is changing
	if ( bbp_get_hidden_status_id() !== $current_visibility ) {

		// Get private forums
		$private = bbp_get_private_forum_ids();

		// Find this forum in the array
		if ( in_array( $forum_id, $private, true ) ) {

			$offset = array_search( $forum_id, $private, true );

			// Splice around it
			array_splice( $private, $offset, 1 );

			// Update private forums minus this one
			update_option( '_bbp_private_forums', bbp_get_unique_array_values( $private ) );
		}

		// Add to '_bbp_hidden_forums' site option
		$hidden   = bbp_get_hidden_forum_ids();
		$hidden[] = $forum_id;
		update_option( '_bbp_hidden_forums', bbp_get_unique_array_values( $hidden ) );

		// Update forums visibility setting
		$bbp_db = bbp_db();
		$bbp_db->update( $bbp_db->posts, array( 'post_status' => bbp_get_hidden_status_id() ), array( 'ID' => $forum_id ) );
		wp_transition_post_status( bbp_get_hidden_status_id(), $current_visibility, get_post( $forum_id ) );
		clean_post_cache( $forum_id );
	}

	do_action( 'bbp_hid_forum',  $forum_id );

	return $forum_id;
}

/**
 * Recaches the private and hidden forums
 *
 * @since 2.4.0 bbPress (r5017)
 *
 * @return array An array of the status code and the message
 */
function bbp_repair_forum_visibility() {

	// First, delete everything.
	delete_option( '_bbp_private_forums' );
	delete_option( '_bbp_hidden_forums'  );

	/**
	 * Don't search for both private/hidden statuses. Since 'pre_get_posts' is an
	 * action, it's not removed by suppress_filters. We need to make sure that
	 * we're only searching for the supplied post_status.
	 *
	 * @see https://bbpress.trac.wordpress.org/ticket/2512
	 */
	remove_action( 'pre_get_posts', 'bbp_pre_get_posts_normalize_forum_visibility', 4 );

	// Query for private forums
	$private_forums = new WP_Query( array(
		'fields'         => 'ids',
		'post_type'      => bbp_get_forum_post_type(),
		'post_status'    => bbp_get_private_status_id(),
		'posts_per_page' => -1,

		// Performance
		'nopaging'               => true,
		'suppress_filters'       => true,
		'update_post_term_cache' => false,
		'update_post_meta_cache' => false,
		'ignore_sticky_posts'    => true,
		'no_found_rows'          => true
	) );

	// Query for hidden forums
	$hidden_forums = new WP_Query( array(
		'fields'           => 'ids',
		'suppress_filters' => true,
		'post_type'        => bbp_get_forum_post_type(),
		'post_status'      => bbp_get_hidden_status_id(),
		'posts_per_page'   => -1,

		// Performance
		'nopaging'               => true,
		'suppress_filters'       => true,
		'update_post_term_cache' => false,
		'update_post_meta_cache' => false,
		'ignore_sticky_posts'    => true,
		'no_found_rows'          => true
	) );

	// Enable forum visibilty normalization
	add_action( 'pre_get_posts', 'bbp_pre_get_posts_normalize_forum_visibility', 4 );

	// Reset the $post global
	wp_reset_postdata();

	// Private
	if ( ! is_wp_error( $private_forums ) ) {
		update_option( '_bbp_private_forums', $private_forums->posts );
	}

	// Hidden forums
	if ( ! is_wp_error( $hidden_forums ) ) {
		update_option( '_bbp_hidden_forums',  $hidden_forums->posts  );
	}

	// Complete results
	return true;
}

/** Subscriptions *************************************************************/

/**
 * Remove a deleted forum from all user subscriptions
 *
 * @since 2.5.0 bbPress (r5156)
 *
 * @param int $forum_id Get the forum ID to remove
 */
function bbp_remove_forum_from_all_subscriptions( $forum_id = 0 ) {

	// Subscriptions are not active
	if ( ! bbp_is_subscriptions_active() ) {
		return;
	}

	// Bail if no forum
	$forum_id = bbp_get_forum_id( $forum_id );
	if ( empty( $forum_id ) ) {
		return;
	}

	// Remove forum from all subscriptions
	return bbp_remove_object_from_all_users( $forum_id, '_bbp_subscription', 'post' );
}

/** Count Bumpers *************************************************************/

/**
 * Bump the total topic count of a forum
 *
 * @since 2.1.0 bbPress (r3825)
 *
 * @param int $forum_id Optional. Forum id.
 * @param int $difference Optional. Default 1
 * @param bool $update_ancestors Optional. Default true
 *
 * @return int Forum topic count
 */
function bbp_bump_forum_topic_count( $forum_id = 0, $difference = 1, $update_ancestors = true ) {

	// Bail if no bump
	if ( empty( $difference ) ) {
		return false;
	}

	// Get some counts
	$forum_id          = bbp_get_forum_id( $forum_id );
	$topic_count       = bbp_get_forum_topic_count( $forum_id, false, true );
	$total_topic_count = bbp_get_forum_topic_count( $forum_id, true,  true );
	$difference        = (int) $difference;

	// Update this forum id
	update_post_meta( $forum_id, '_bbp_topic_count',       (int) ( $topic_count       + $difference ) );
	update_post_meta( $forum_id, '_bbp_total_topic_count', (int) ( $total_topic_count + $difference ) );

	// Check for ancestors
	if ( true === $update_ancestors ) {

		// Get post ancestors
		$forum     = get_post( $forum_id );
		$ancestors = get_post_ancestors( $forum );

		// If has ancestors, loop through them...
		if ( ! empty( $ancestors ) ) {
			foreach ( (array) $ancestors as $parent_forum_id ) {

				// Only update topic count when an ancestor is not a category.
				if ( ! bbp_is_forum_category( $parent_forum_id ) ) {

					$parent_topic_count = bbp_get_forum_topic_count( $parent_forum_id, false, true );
					update_post_meta( $parent_forum_id, '_bbp_topic_count', (int) ( $parent_topic_count + $difference ) );
				}

				// Update the total topic count.
				$parent_total_topic_count = bbp_get_forum_topic_count( $parent_forum_id, true,  true );
				update_post_meta( $parent_forum_id, '_bbp_total_topic_count', (int) ( $parent_total_topic_count + $difference ) );
			}
		}
	}

	$forum_topic_count = (int) ( $total_topic_count + $difference );

	// Filter & return
	return (int) apply_filters( 'bbp_bump_forum_topic_count', $forum_topic_count, $forum_id, $difference, $update_ancestors );
}

/**
 * Increase the total topic count of a forum by one.
 *
 * @since 2.6.0 bbPress (r6036)
 *
 * @param int $forum_id The forum id.
 * @return void
 */
function bbp_increase_forum_topic_count( $forum_id = 0 ) {

	// Bail early if no id is passed.
	if ( empty( $forum_id ) ) {
		return;
	}

	// If it's a topic, get the forum id.
	if ( bbp_is_topic( $forum_id ) ) {
		$topic_id = $forum_id;
		$forum_id = bbp_get_topic_forum_id( $topic_id );

		// Update inverse based on item status
		if ( ! bbp_is_topic_public( $topic_id ) ) {
			bbp_increase_forum_topic_count_hidden( $forum_id );
			return;
		}
	}

	// Bump up
	bbp_bump_forum_topic_count( $forum_id );
}

/**
 * Decrease the total topic count of a forum by one.
 *
 * @since 2.6.0 bbPress (r6036)
 *
 * @param int $forum_id The forum id.
 *
 * @return void
 */
function bbp_decrease_forum_topic_count( $forum_id = 0 ) {

	// Bail early if no id is passed.
	if ( empty( $forum_id ) ) {
		return;
	}

	// If it's a topic, get the forum id.
	if ( bbp_is_topic( $forum_id ) ) {
		$topic_id = $forum_id;
		$forum_id = bbp_get_topic_forum_id( $topic_id );

		// Update inverse based on item status
		if ( ! bbp_is_topic_public( $topic_id ) ) {
			bbp_decrease_forum_topic_count_hidden( $forum_id );
			return;
		}
	}

	// Bump down
	bbp_bump_forum_topic_count( $forum_id, -1 );
}

/**
 * Bump the total topic count of a forum
 *
 * @since 2.1.0 bbPress (r3825)
 *
 * @param int $forum_id Optional. Forum id.
 * @param int $difference Optional. Default 1
 * @param bool $update_ancestors Optional. Default true
 *
 * @return int Forum topic count
 */
function bbp_bump_forum_topic_count_hidden( $forum_id = 0, $difference = 1, $update_ancestors = true ) {

	// Bail if no bump
	if ( empty( $difference ) ) {
		return false;
	}

	// Get some counts
	$forum_id          = bbp_get_forum_id( $forum_id );
	$reply_count       = bbp_get_forum_topic_count_hidden( $forum_id, false, true );
	$total_topic_count = bbp_get_forum_topic_count_hidden( $forum_id, true,  true );
	$difference        = (int) $difference;

	// Update this forum id
	update_post_meta( $forum_id, '_bbp_topic_count_hidden',       (int) ( $reply_count       + $difference ) );
	update_post_meta( $forum_id, '_bbp_total_topic_count_hidden', (int) ( $total_topic_count + $difference ) );

	// Check for ancestors
	if ( true === $update_ancestors ) {

		// Get post ancestors
		$forum     = get_post( $forum_id );
		$ancestors = get_post_ancestors( $forum );

		// If has ancestors, loop through them...
		if ( ! empty( $ancestors ) ) {
			foreach ( (array) $ancestors as $parent_forum_id ) {

				// Only update topic count when an ancestor is not a category.
				if ( ! bbp_is_forum_category( $parent_forum_id ) ) {

					$parent_topic_count = bbp_get_forum_topic_count_hidden( $parent_forum_id, false, true );
					update_post_meta( $parent_forum_id, '_bbp_topic_count_hidden', (int) ( $parent_topic_count + $difference ) );
				}

				// Update the total topic count.
				$parent_total_topic_count = bbp_get_forum_topic_count_hidden( $parent_forum_id, true, true );
				update_post_meta( $parent_forum_id, '_bbp_total_topic_count_hidden', (int) ( $parent_total_topic_count + $difference ) );
			}
		}
	}

	$forum_topic_count = (int) ( $total_topic_count + $difference );

	// Filter & return
	return (int) apply_filters( 'bbp_bump_forum_topic_count_hidden', $forum_topic_count, $forum_id, $difference, $update_ancestors );
}

/**
 * Increase the total hidden topic count of a forum by one.
 *
 * @since 2.6.0 bbPress (r6036)
 *
 * @param int $forum_id The forum id.
 *
 * @return void
 */
function bbp_increase_forum_topic_count_hidden( $forum_id = 0 ) {

	// Bail early if no id is passed.
	if ( empty( $forum_id ) ) {
		return;
	}

	// If it's a topic, get the forum id.
	if ( bbp_is_topic( $forum_id ) ) {
		$topic_id = $forum_id;
		$forum_id = bbp_get_topic_forum_id( $topic_id );

		// Update inverse based on item status
		if ( bbp_is_topic_public( $topic_id ) ) {
			bbp_increase_forum_topic_count( $forum_id );
			return;
		}
	}

	// Bump up
	bbp_bump_forum_topic_count_hidden( $forum_id );
}

/**
 * Decrease the total hidden topic count of a forum by one.
 *
 * @since 2.6.0 bbPress (r6036)
 *
 * @param int $forum_id The forum id.
 *
 * @return void
 */
function bbp_decrease_forum_topic_count_hidden( $forum_id = 0 ) {

	// Bail early if no id is passed.
	if ( empty( $forum_id ) ) {
		return;
	}

	// If it's a topic, get the forum id.
	if ( bbp_is_topic( $forum_id ) ) {
		$topic_id = $forum_id;
		$forum_id = bbp_get_topic_forum_id( $topic_id );

		// Update inverse based on item status
		if ( bbp_is_topic_public( $topic_id ) ) {
			bbp_decrease_forum_topic_count( $forum_id );
			return;
		}
	}

	// Bump down
	bbp_bump_forum_topic_count_hidden( $forum_id, -1 );
}

/**
 * Bump the total topic count of a forum
 *
 * @since 2.1.0 bbPress (r3825)
 *
 * @param int $forum_id Optional. Forum id.
 * @param int $difference Optional. Default 1
 * @param bool $update_ancestors Optional. Default true
 *
 * @return int Forum topic count
 */
function bbp_bump_forum_reply_count( $forum_id = 0, $difference = 1, $update_ancestors = true ) {

	// Bail if no bump
	if ( empty( $difference ) ) {
		return false;
	}

	// Get some counts
	$forum_id          = bbp_get_forum_id( $forum_id );
	$reply_count       = bbp_get_forum_reply_count( $forum_id, false, true );
	$total_reply_count = bbp_get_forum_reply_count( $forum_id, true,  true );
	$difference        = (int) $difference;

	// Update this forum id
	update_post_meta( $forum_id, '_bbp_reply_count',       (int) ( $reply_count       + $difference ) );
	update_post_meta( $forum_id, '_bbp_total_reply_count', (int) ( $total_reply_count + $difference ) );

	// Check for ancestors
	if ( true === $update_ancestors ) {

		// Get post ancestors
		$forum     = get_post( $forum_id );
		$ancestors = get_post_ancestors( $forum );

		// If has ancestors, loop through them...
		if ( ! empty( $ancestors ) ) {
			foreach ( (array) $ancestors as $parent_forum_id ) {

				// Only update reply count when an ancestor is not a category.
				if ( ! bbp_is_forum_category( $parent_forum_id ) ) {

					$parent_reply_count = bbp_get_forum_reply_count( $parent_forum_id, false, true );
					update_post_meta( $parent_forum_id, '_bbp_reply_count', (int) ( $parent_reply_count + $difference ) );
				}

				// Update the total reply count.
				$parent_total_reply_count = bbp_get_forum_reply_count( $parent_forum_id, true,  true );
				update_post_meta( $parent_forum_id, '_bbp_total_reply_count', (int) ( $parent_total_reply_count + $difference ) );
			}
		}
	}

	$forum_reply_count = (int) ( $total_reply_count + $difference );

	// Filter & return
	return (int) apply_filters( 'bbp_bump_forum_reply_count', $forum_reply_count, $forum_id, $difference, $update_ancestors );
}

/**
 * Bump the total topic count of a forum
 *
 * @since 2.6.0 bbPress (r6922)
 *
 * @param int $forum_id Optional. Forum id.
 * @param int $difference Optional. Default 1
 * @param bool $update_ancestors Optional. Default true
 *
 * @return int Forum topic count
 */
function bbp_bump_forum_reply_count_hidden( $forum_id = 0, $difference = 1, $update_ancestors = true ) {

	// Bail if no bump
	if ( empty( $difference ) ) {
		return false;
	}

	// Get some counts
	$forum_id          = bbp_get_forum_id( $forum_id );
	$reply_count       = bbp_get_forum_reply_count_hidden( $forum_id, false, true );
	$total_reply_count = bbp_get_forum_reply_count_hidden( $forum_id, true,  true );
	$difference        = (int) $difference;

	// Update this forum id
	update_post_meta( $forum_id, '_bbp_reply_count_hidden',       (int) ( $reply_count       + $difference ) );
	update_post_meta( $forum_id, '_bbp_total_reply_count_hidden', (int) ( $total_reply_count + $difference ) );

	// Check for ancestors
	if ( true === $update_ancestors ) {

		// Get post ancestors
		$forum     = get_post( $forum_id );
		$ancestors = get_post_ancestors( $forum );

		// If has ancestors, loop through them...
		if ( ! empty( $ancestors ) ) {
			foreach ( (array) $ancestors as $parent_forum_id ) {

				// Only update reply count when an ancestor is not a category.
				if ( ! bbp_is_forum_category( $parent_forum_id ) ) {

					$parent_reply_count = bbp_get_forum_reply_count_hidden( $parent_forum_id, false, true );
					update_post_meta( $parent_forum_id, '_bbp_reply_count_hidden', (int) ( $parent_reply_count + $difference ) );
				}

				// Update the total reply count.
				$parent_total_reply_count = bbp_get_forum_reply_count_hidden( $parent_forum_id, true,  true );
				update_post_meta( $parent_forum_id, '_bbp_total_reply_count_hidden', (int) ( $parent_total_reply_count + $difference ) );
			}
		}
	}

	$forum_reply_count = (int) ( $total_reply_count + $difference );

	// Filter & return
	return (int) apply_filters( 'bbp_bump_forum_reply_count_hidden', $forum_reply_count, $forum_id, $difference, $update_ancestors );
}

/**
 * Increase the total reply count of a forum by one.
 *
 * @since 2.6.0 bbPress (r6036)
 *
 * @param int $forum_id The forum id.
 *
 * @return void
 */
function bbp_increase_forum_reply_count( $forum_id = 0 ) {

	// Bail early if no id is passed.
	if ( empty( $forum_id ) ) {
		return;
	}

	// If it's a reply, get the forum id.
	if ( bbp_is_reply( $forum_id ) ) {
		$reply_id = $forum_id;
		$forum_id = bbp_get_reply_forum_id( $reply_id );

		// Update inverse based on item status
		if ( ! bbp_is_reply_public( $reply_id ) ) {
			bbp_increase_forum_reply_count_hidden( $forum_id );
			return;
		}
	}

	// Bump up
	bbp_bump_forum_reply_count( $forum_id );
}

/**
 * Decrease the total reply count of a forum by one.
 *
 * @since 2.6.0 bbPress (r6036)
 *
 * @param int $forum_id The forum id.
 *
 * @return void
 */
function bbp_decrease_forum_reply_count( $forum_id = 0 ) {

	// Bail early if no id is passed.
	if ( empty( $forum_id ) ) {
		return;
	}

	// If it's a reply, get the forum id.
	if ( bbp_is_reply( $forum_id ) ) {
		$reply_id = $forum_id;
		$forum_id = bbp_get_reply_forum_id( $reply_id );

		// Update inverse based on item status
		if ( ! bbp_is_reply_public( $reply_id ) ) {
			bbp_decrease_forum_reply_count_hidden( $forum_id );
			return;
		}
	}

	// Bump down
	bbp_bump_forum_reply_count( $forum_id, -1 );
}

/**
 * Increase the total hidden reply count of a forum by one.
 *
 * @since 2.6.0 bbPress (r6036)
 *
 * @param int $forum_id The forum id.
 *
 * @return void
 */
function bbp_increase_forum_reply_count_hidden( $forum_id = 0 ) {

	// Bail early if no id is passed.
	if ( empty( $forum_id ) ) {
		return;
	}

	// If it's a reply, get the forum id.
	if ( bbp_is_reply( $forum_id ) ) {
		$reply_id = $forum_id;
		$forum_id = bbp_get_reply_forum_id( $reply_id );

		// Update inverse based on item status
		if ( bbp_is_reply_public( $reply_id ) ) {
			bbp_increase_forum_reply_count( $forum_id );
			return;
		}
	}

	// Bump up
	bbp_bump_forum_reply_count_hidden( $forum_id );
}

/**
 * Decrease the total hidden reply count of a forum by one.
 *
 * @since 2.6.0 bbPress (r6036)
 *
 * @param int $forum_id The forum id.
 *
 * @return void
 */
function bbp_decrease_forum_reply_count_hidden( $forum_id = 0 ) {

	// Bail early if no id is passed.
	if ( empty( $forum_id ) ) {
		return;
	}

	// If it's a reply, get the forum id.
	if ( bbp_is_reply( $forum_id ) ) {
		$reply_id = $forum_id;
		$forum_id = bbp_get_reply_forum_id( $reply_id );

		// Update inverse based on item status
		if ( bbp_is_reply_public( $reply_id ) ) {
			bbp_decrease_forum_reply_count( $forum_id );
			return;
		}
	}

	// Bump down
	bbp_bump_forum_reply_count_hidden( $forum_id, -1 );
}

/**
 * Update forum reply counts when a topic is approved or unapproved.
 *
 * @since 2.6.0 bbPress (r6036)
 *
 * @param int $topic_id The topic id.
 *
 * @return void
 */
function bbp_approved_unapproved_topic_update_forum_reply_count( $topic_id = 0 ) {

	// Bail early if we don't have a topic id.
	if ( empty( $topic_id ) ) {
		return;
	}

	// Get the topic's replies.
	$count = bbp_get_public_child_count( $topic_id, bbp_get_reply_post_type() );

	// If we're unapproving, set count to negative.
	if ( 'bbp_unapproved_topic' === current_filter() ) {
		$count = -$count;
	}

	// Bump up or down
	bbp_bump_forum_reply_count( bbp_get_topic_forum_id( $topic_id ), $count );
}

/** Forum Updaters ************************************************************/

/**
 * Update the forum last topic id
 *
 * @since 2.0.0 bbPress (r2625)
 *
 * @param int $forum_id Optional. Forum id.
 * @param int $topic_id Optional. Topic id.
 * @return int Id of the forums most recent topic
 */
function bbp_update_forum_last_topic_id( $forum_id = 0, $topic_id = 0 ) {
	$forum_id = bbp_get_forum_id( $forum_id );

	// Define local variable(s)
	$children_last_topic = 0;

	// Do some calculation if not manually set
	if ( empty( $topic_id ) ) {

		// Loop through children and add together forum reply counts
		$children = bbp_forum_query_subforum_ids( $forum_id );
		if ( ! empty( $children ) ) {
			foreach ( $children as $child ) {
				$children_last_topic = bbp_update_forum_last_topic_id( $child ); // Recursive
			}
		}

		// Setup recent topic query vars
		$post_vars = array(
			'post_parent' => $forum_id,
			'post_type'   => bbp_get_topic_post_type(),
			'meta_key'    => '_bbp_last_active_time',
			'meta_type'   => 'DATETIME',
			'orderby'     => 'meta_value',
			'numberposts' => 1
		);

		// Get the most recent topic in this forum_id
		$recent_topic = get_posts( $post_vars );
		if ( ! empty( $recent_topic ) ) {
			$topic_id = $recent_topic[0]->ID;
		}
	}

	// Cast as integer in case of empty or string
	$topic_id            = (int) $topic_id;
	$children_last_topic = (int) $children_last_topic;

	// If child forums have higher id, use that instead
	if ( ! empty( $children ) && ( $children_last_topic > $topic_id ) ) {
		$topic_id = $children_last_topic;
	}

	// Update the last public topic ID
	update_post_meta( $forum_id, '_bbp_last_topic_id', $topic_id );

	// Filter & return
	return (int) apply_filters( 'bbp_update_forum_last_topic_id', $topic_id, $forum_id );
}

/**
 * Update the forum last reply id
 *
 * @since 2.0.0 bbPress (r2625)
 *
 * @param int $forum_id Optional. Forum id.
 * @param int $reply_id Optional. Reply id.
 * @return int Id of the forums most recent reply
 */
function bbp_update_forum_last_reply_id( $forum_id = 0, $reply_id = 0 ) {
	$forum_id = bbp_get_forum_id( $forum_id );

	// Define local variable(s)
	$children_last_reply = 0;

	// Do some calculation if not manually set
	if ( empty( $reply_id ) ) {

		// Loop through children and get the most recent reply id
		$children = bbp_forum_query_subforum_ids( $forum_id );
		if ( ! empty( $children ) ) {
			foreach ( $children as $child ) {
				$children_last_reply = bbp_update_forum_last_reply_id( $child ); // Recursive
			}
		}

		// If this forum has topics...
		$topic_ids = bbp_forum_query_topic_ids( $forum_id );
		if ( ! empty( $topic_ids ) ) {

			// ...get the most recent reply from those topics...
			$reply_id = bbp_forum_query_last_reply_id( $forum_id, $topic_ids );

			// ...and compare it to the most recent topic id...
			$reply_id = ( $reply_id > max( $topic_ids ) )
				? $reply_id
				: max( $topic_ids );
		}
	}

	// Cast as integer in case of empty or string
	$reply_id            = (int) $reply_id;
	$children_last_reply = (int) $children_last_reply;

	// If child forums have higher ID, check for newer reply id
	if ( ! empty( $children ) && ( $children_last_reply > $reply_id ) ) {
		$reply_id = $children_last_reply;
	}

	// Update the last public reply ID
	update_post_meta( $forum_id, '_bbp_last_reply_id', $reply_id );

	// Filter & return
	return (int) apply_filters( 'bbp_update_forum_last_reply_id', $reply_id, $forum_id );
}

/**
 * Update the forum last active post id
 *
 * @since 2.0.0 bbPress (r2860)
 *
 * @param int $forum_id Optional. Forum id.
 * @param int $active_id Optional. Active post id.
 * @return int Id of the forums last active post
 */
function bbp_update_forum_last_active_id( $forum_id = 0, $active_id = 0 ) {

	$forum_id = bbp_get_forum_id( $forum_id );

	// Define local variable(s)
	$children_last_active = 0;

	// Do some calculation if not manually set
	if ( empty( $active_id ) ) {

		// Loop through children and get the last active ID
		$children = bbp_forum_query_subforum_ids( $forum_id );
		if ( ! empty( $children ) ) {
			foreach ( $children as $child ) {
				$children_last_active = bbp_update_forum_last_active_id( $child, $active_id );
			}
		}

		// Get topic IDs and only accept larger IDs
		$topic_ids = bbp_forum_query_topic_ids( $forum_id );
		if ( ! empty( $topic_ids ) ) {

			// Make sure ID is larger
			$active_id = bbp_forum_query_last_reply_id( $forum_id, $topic_ids );
			$active_id = $active_id > max( $topic_ids )
				? $active_id
				: max( $topic_ids );

		// Forum has no topics
		} else {
			$active_id = 0;
		}
	}

	// Cast as integer in case of empty or string
	$active_id            = (int) $active_id;
	$children_last_active = (int) $children_last_active;

	// If child forums have higher ID, use that instead
	if ( ! empty( $children ) && ( $children_last_active > $active_id ) ) {
		$active_id = $children_last_active;
	}

	update_post_meta( $forum_id, '_bbp_last_active_id', $active_id );

	// Filter & return
	return (int) apply_filters( 'bbp_update_forum_last_active_id', $active_id, $forum_id );
}

/**
 * Update the forums last active date/time (aka freshness)
 *
 * @since 2.0.0 bbPress (r2680)
 *
 * @param int    $forum_id Optional. Topic id.
 * @param string $new_time Optional. New time in mysql format.
 *
 * @return string MySQL timestamp of last active topic or reply
 */
function bbp_update_forum_last_active_time( $forum_id = 0, $new_time = '' ) {
	$forum_id = bbp_get_forum_id( $forum_id );

	// Check time and use current if empty
	if ( empty( $new_time ) ) {
		$new_time = get_post_field( 'post_date', bbp_get_forum_last_active_id( $forum_id ) );
	}

	// Update only if there is a time
	if ( ! empty( $new_time ) ) {
		update_post_meta( $forum_id, '_bbp_last_active_time', $new_time );
	}

	// Filter & return
	return apply_filters( 'bbp_update_forum_last_active', $new_time, $forum_id );
}

/**
 * Update the forum sub-forum count
 *
 * @since 2.0.0 bbPress (r2625)
 *
 * @param int $forum_id Optional. Forum id
 * @param int $subforums Optional. Number of subforums
 * @return bool True on success, false on failure
 */
function bbp_update_forum_subforum_count( $forum_id = 0, $subforums = false ) {
	$forum_id = bbp_get_forum_id( $forum_id );

	// Maybe query for counts
	$subforums = ! is_int( $subforums )
		? bbp_get_public_child_count( $forum_id, bbp_get_forum_post_type() )
		: (int) $subforums;

	update_post_meta( $forum_id, '_bbp_forum_subforum_count', $subforums );

	// Filter & return
	return (int) apply_filters( 'bbp_update_forum_subforum_count', $subforums, $forum_id );
}

/**
 * Adjust the total topic count of a forum
 *
 * @since 2.0.0 bbPress (r2464)
 *
 * @param int $forum_id Optional. Forum id or topic id. It is checked whether it
 *                       is a topic or a forum. If it's a topic, its parent,
 *                       i.e. the forum is automatically retrieved.
 * @param bool $total_count Optional. To return the total count or normal count?
 * @return int Forum topic count
 */
function bbp_update_forum_topic_count( $forum_id = 0 ) {
	$forum_id = bbp_get_forum_id( $forum_id );
	$children_topic_count = 0;

	// Loop through subforums and add together forum topic counts
	$children = bbp_forum_query_subforum_ids( $forum_id );
	if ( ! empty( $children ) ) {
		foreach ( $children as $child ) {
			$children_topic_count += bbp_update_forum_topic_count( $child ); // Recursive
		}
	}

	// Get total topics for this forum
	$topics = bbp_get_public_child_count( $forum_id, bbp_get_topic_post_type() );

	// Calculate total topics in this forum
	$total_topics = (int) ( $topics + $children_topic_count );

	// Update the count
	update_post_meta( $forum_id, '_bbp_topic_count',       $topics       );
	update_post_meta( $forum_id, '_bbp_total_topic_count', $total_topics );

	// Filter & return
	return (int) apply_filters( 'bbp_update_forum_topic_count', $total_topics, $forum_id );
}

/**
 * Adjust the total hidden topic count of a forum (hidden includes trashed,
 * spammed and pending topics)
 *
 * @since 2.0.0 bbPress (r2888)
 * @since 2.6.0 bbPress (r5954) Replace direct queries with WP_Query() objects
 *
 * @param int $forum_id Optional. Topic id to update.
 * @param int $topic_count Optional. Set the topic count manually.
 *
 * @return int Topic hidden topic count
 */
function bbp_update_forum_topic_count_hidden( $forum_id = 0, $topic_count = false ) {

	// If topic_id was passed as $forum_id, then get its forum
	if ( bbp_is_topic( $forum_id ) ) {
		$topic_id = bbp_get_topic_id( $forum_id );
		$forum_id = bbp_get_topic_forum_id( $topic_id );

	// $forum_id is not a topic_id, so validate and proceed
	} else {
		$forum_id = bbp_get_forum_id( $forum_id );
	}

	// Can't update what isn't there
	if ( ! empty( $forum_id ) ) {

		// Get topics of forum
		if ( ! is_int( $topic_count ) ) {
			$query = new WP_Query( array(
				'fields'         => 'ids',
				'post_parent'    => $forum_id,
				'post_status'    => bbp_get_non_public_topic_statuses(),
				'post_type'      => bbp_get_topic_post_type(),
				'posts_per_page' => -1,

				// Performance
				'nopaging'               => true,
				'suppress_filters'       => true,
				'update_post_term_cache' => false,
				'update_post_meta_cache' => false,
				'ignore_sticky_posts'    => true,
				'no_found_rows'          => true
			) );
			$topic_count = $query->post_count;
			unset( $query );
		}

		$topic_count = (int) $topic_count;

		// Update the count
		update_post_meta( $forum_id, '_bbp_topic_count_hidden', $topic_count );
	}

	// Filter & return
	return (int) apply_filters( 'bbp_update_forum_topic_count_hidden', $topic_count, $forum_id );
}

/**
 * Adjust the total reply count of a forum
 *
 * @since 2.0.0 bbPress (r2464)
 * @since 2.6.0 bbPress (r5954) Replace direct queries with WP_Query() objects
 *
 * @param int  $forum_id Optional. Forum id or topic id. It is checked whether it
 *                       is a topic or a forum. If it's a topic, its parent,
 *                       i.e. the forum is automatically retrieved.
 *
 * @return int Forum reply count
 */
function bbp_update_forum_reply_count( $forum_id = 0 ) {

	$forum_id = bbp_get_forum_id( $forum_id );
	$children_reply_count = 0;

	// Loop through children and add together forum reply counts
	$children = bbp_forum_query_subforum_ids( $forum_id );
	if ( ! empty( $children ) ) {
		foreach ( (array) $children as $child ) {
			$children_reply_count += bbp_update_forum_reply_count( $child );
		}
	}

	// Don't count replies if the forum is a category
	$reply_count = ! bbp_is_forum_category( $forum_id )
		? bbp_get_public_child_count( $forum_id, bbp_get_reply_post_type() )
		: 0;

	// Calculate total replies in this forum
	$total_replies = (int) ( $reply_count + $children_reply_count );

	// Update the counts
	update_post_meta( $forum_id, '_bbp_reply_count',       $reply_count   );
	update_post_meta( $forum_id, '_bbp_total_reply_count', $total_replies );

	// Filter & return
	return (int) apply_filters( 'bbp_update_forum_reply_count', $total_replies, $forum_id );
}

/**
 * Adjust the total hidden reply count of a forum
 *
 * @since 2.6.0 bbPress (r6922)
 *
 * @param int  $forum_id Optional. Forum id or topic id. It is checked whether it
 *                       is a topic or a forum. If it's a topic, its parent,
 *                       i.e. the forum is automatically retrieved.
 *
 * @return int Forum reply count
 */
function bbp_update_forum_reply_count_hidden( $forum_id = 0 ) {

	$forum_id = bbp_get_forum_id( $forum_id );
	$children_reply_count = 0;

	// Loop through children and add together forum reply counts
	$children = bbp_forum_query_subforum_ids( $forum_id );
	if ( ! empty( $children ) ) {
		foreach ( (array) $children as $child ) {
			$children_reply_count += bbp_update_forum_reply_count_hidden( $child );
		}
	}

	// Don't count replies if the forum is a category
	$reply_count = ! bbp_is_forum_category( $forum_id )
		? bbp_get_non_public_child_count( $forum_id, bbp_get_reply_post_type() )
		: 0;

	// Calculate total replies in this forum
	$total_replies = (int) ( $reply_count + $children_reply_count );

	// Update the counts
	update_post_meta( $forum_id, '_bbp_reply_count_hidden',       $reply_count   );
	update_post_meta( $forum_id, '_bbp_total_reply_count_hidden', $total_replies );

	// Filter & return
	return (int) apply_filters( 'bbp_update_forum_reply_count_hidden', $total_replies, $forum_id );
}

/**
 * Updates the counts of a forum.
 *
 * This calls a few internal functions that all run manual queries against the
 * database to get their results. As such, this function can be costly to run
 * but is necessary to keep everything accurate.
 *
 * @since 2.0.0 bbPress (r2908)
 *
 * @param array $args Supports these arguments:
 *  - forum_id: Forum id
 *  - last_topic_id: Last topic id
 *  - last_reply_id: Last reply id
 *  - last_active_id: Last active post id
 *  - last_active_time: last active time
 */
function bbp_update_forum( $args = array() ) {

	// Parse arguments against default values
	$r = bbp_parse_args( $args, array(
		'forum_id'           => 0,
		'post_parent'        => 0,
		'last_topic_id'      => 0,
		'last_reply_id'      => 0,
		'last_active_id'     => 0,
		'last_active_time'   => 0,
		'last_active_status' => bbp_get_public_status_id()
	), 'update_forum' );

	// Update the forum parent
	bbp_update_forum_id( $r['forum_id'], $r['post_parent'] );

	// Last topic and reply ID's
	bbp_update_forum_last_topic_id( $r['forum_id'], $r['last_topic_id'] );
	bbp_update_forum_last_reply_id( $r['forum_id'], $r['last_reply_id'] );

	// Active dance
	$r['last_active_id'] = bbp_update_forum_last_active_id( $r['forum_id'], $r['last_active_id'] );

	// If no active time was passed, get it from the last_active_id
	if ( empty( $r['last_active_time'] ) ) {
		$r['last_active_time'] = get_post_field( 'post_date', $r['last_active_id'] );
	}

	if ( bbp_get_public_status_id() === $r['last_active_status'] ) {
		bbp_update_forum_last_active_time( $r['forum_id'], $r['last_active_time'] );
	}

	// Counts
	bbp_update_forum_subforum_count( $r['forum_id'] );

	// Only update topic count if we've deleted a topic
	if ( in_array( current_filter(), array( 'bbp_deleted_topic', 'save_post' ), true ) ) {
		bbp_update_forum_reply_count(        $r['forum_id'] );
		bbp_update_forum_topic_count(        $r['forum_id'] );
		bbp_update_forum_topic_count_hidden( $r['forum_id'] );
		bbp_update_forum_reply_count_hidden( $r['forum_id'] );
	}

	// Update the parent forum if one was passed
	if ( ! empty( $r['post_parent'] ) && is_numeric( $r['post_parent'] ) ) {
		bbp_update_forum( array(
			'forum_id'    => $r['post_parent'],
			'post_parent' => get_post_field( 'post_parent', $r['post_parent'] )
		) );
	}

	// Bump the custom query cache
	wp_cache_set( 'last_changed', microtime(), 'bbpress_posts' );
}

/** Helpers *******************************************************************/

/**
 * Return an associative array of available topic statuses
 *
 * Developers note: these statuses are actually stored as meta data, and
 * Visibilities are stored in post_status.
 *
 * @since 2.4.0 bbPress (r5059)
 *
 * @param int $forum_id   Optional. Forum id.
 *
 * @return array
 */
function bbp_get_forum_statuses( $forum_id = 0 ) {

	// Filter & return
	return (array) apply_filters( 'bbp_get_forum_statuses', array(
		'open'   => _x( 'Open',    'Open the forum',  'bbpress' ),
		'closed' => _x( 'Closed',  'Close the forum', 'bbpress' )
	), $forum_id );
}

/**
 * Return an associative array of forum types
 *
 * @since 2.4.0 bbPress (r5059)
 *
 * @param int $forum_id   Optional. Forum id.
 *
 * @return array
 */
function bbp_get_forum_types( $forum_id = 0 ) {

	// Filter & return
	return (array) apply_filters( 'bbp_get_forum_types', array(
		'forum'    => _x( 'Forum',    'Forum accepts new topics', 'bbpress' ),
		'category' => _x( 'Category', 'Forum is a category',      'bbpress' )
	), $forum_id );
}

/**
 * Return an associative array of forum visibility
 *
 * Developers note: these visibilities are actually stored in post_status, and
 * Statuses are stored in meta data.
 *
 * @since 2.4.0 bbPress (r5059)
 *
 * @param int $forum_id   Optional. Forum id.
 *
 * @return array
 */
function bbp_get_forum_visibilities( $forum_id = 0) {

	// Filter & return
	return (array) apply_filters( 'bbp_get_forum_visibilities', array(
		bbp_get_public_status_id()  => _x( 'Public',  'Make forum public',  'bbpress' ),
		bbp_get_private_status_id() => _x( 'Private', 'Make forum private', 'bbpress' ),
		bbp_get_hidden_status_id()  => _x( 'Hidden',  'Make forum hidden',  'bbpress' )
	), $forum_id );
}

/**
 * Return array of public forum statuses.
 *
 * @since 2.6.0 bbPress (r6921)
 *
 * @return array
 */
function bbp_get_public_forum_statuses() {
	$statuses = array(
		bbp_get_public_status_id()
	);

	// Filter & return
	return (array) apply_filters( 'bbp_get_public_forum_statuses', $statuses );
}

/**
 * Return array of non-public forum statuses.
 *
 * @since 2.6.0 bbPress (r6921)
 *
 * @return array
 */
function bbp_get_non_public_forum_statuses() {
	$statuses = array(
		bbp_get_private_status_id(),
		bbp_get_hidden_status_id()
	);

	// Filter & return
	return (array) apply_filters( 'bbp_get_non_public_forum_statuses', $statuses );
}

/** Queries *******************************************************************/

/**
 * Returns the hidden forum ids
 *
 * Only hidden forum ids are returned. Public and private ids are not.
 *
 * @since 2.0.0 bbPress (r3007)
 */
function bbp_get_hidden_forum_ids() {
	$forum_ids = get_option( '_bbp_hidden_forums', array() );
	$forum_ids = ! empty( $forum_ids )
		? wp_parse_id_list( $forum_ids )
		: array();

	// Filter & return
	return (array) apply_filters( 'bbp_get_hidden_forum_ids', $forum_ids );
}

/**
 * Returns the private forum ids
 *
 * Only private forum ids are returned. Public and hidden ids are not.
 *
 * @since 2.0.0 bbPress (r3007)
 */
function bbp_get_private_forum_ids() {
	$forum_ids = get_option( '_bbp_private_forums', array() );
	$forum_ids = ! empty( $forum_ids )
		? wp_parse_id_list( $forum_ids )
		: array();

	// Filter & return
	return (array) apply_filters( 'bbp_get_private_forum_ids', $forum_ids );
}

/**
 * Returns the forum IDs that should be excluded from various views & queries,
 * based on the current user's capabilities.
 *
 * These results are automatically filtered by bbp_allow_forums_of_user(), to
 * allow per-forum moderators to see forums that would otherwise be private or
 * hidden to them.
 *
 * If you have a need to filter these results based on your own custom
 * engagements API usages, please see: bbp_allow_forums_of_user()
 *
 * @since 2.6.0 bbPress (r6425)
 *
 * @return array Forum IDs to exclude, or an empty array
 */
function bbp_get_excluded_forum_ids() {

	// Private forums
	$private = ! current_user_can( 'read_private_forums' )
		? bbp_get_private_forum_ids()
		: array();

	// Hidden forums
	$hidden = ! current_user_can( 'read_hidden_forums' )
		? bbp_get_hidden_forum_ids()
		: array();

	// Merge private & hidden forums together, and remove any empties
	$forum_ids = ( ! empty( $private ) || ! empty( $hidden ) )
		? array_filter( wp_parse_id_list( array_merge( $private, $hidden ) ) )
		: array();

	// Filter & return
	return (array) apply_filters( 'bbp_get_excluded_forum_ids', $forum_ids, $private, $hidden );
}

/**
 * Returns a meta_query that either includes or excludes hidden forum IDs
 * from a query.
 *
 * @since 2.0.0 bbPress (r3291)
 *
 * @param string Optional. The type of value to return. (string|array|meta_query)
 */
function bbp_exclude_forum_ids( $type = 'string' ) {

	// Setup arrays
	$forum_ids = array();

	// Types
	$types = array(
		'array'      => array(),
		'string'     => '',
		'meta_query' => array()
	);

	// Exclude for everyone but keymasters
	if ( ! bbp_is_user_keymaster() ) {

		// Get forum IDs to exclude
		$forum_ids = bbp_get_excluded_forum_ids();

		// Store return values in static types array
		if ( ! empty( $forum_ids ) ) {

			// Comparison
			$compare = ( 1 < count( $forum_ids ) )
				? 'NOT IN'
				: '!=';

			// Setup types
			$types['array']      = $forum_ids;
			$types['string']     = implode( ',', $forum_ids );
			$types['meta_query'] = array(
				'key'     => '_bbp_forum_id',
				'value'   => $types['string'],
				'type'    => 'NUMERIC',
				'compare' => $compare
			);
		}
	}

	// There are forums that need to be excluded
	$retval = $types[ $type ];

	// Filter & return
	return apply_filters( 'bbp_exclude_forum_ids', $retval, $forum_ids, $type );
}

/**
 * Adjusts forum, topic, and reply queries to exclude items that might be
 * contained inside hidden or private forums that the user does not have the
 * capability to view.
 *
 * Doing it with an action allows us to trap all WP_Query's rather than needing
 * to hardcode this logic into each query. It also protects forum content for
 * plugins that might be doing their own queries.
 *
 * @since 2.0.0 bbPress (r3291)
 *
 * @param WP_Query $posts_query
 *
 * @return WP_Query
 */
function bbp_pre_get_posts_normalize_forum_visibility( $posts_query = null ) {

	// Bail if all forums are explicitly allowed
	if ( true === apply_filters( 'bbp_include_all_forums', false, $posts_query ) ) {
		return;
	}

	// Bail if $posts_query is not an object or of incorrect class
	if ( ! is_object( $posts_query ) || ! is_a( $posts_query, 'WP_Query' ) ) {
		return;
	}

	// Get query post types array .
	$post_types = (array) $posts_query->get( 'post_type' );

	// Forums
	if ( bbp_get_forum_post_type() === implode( '', $post_types ) ) {

		// Prevent accidental wp-admin post_row override
		if ( is_admin() && isset( $_REQUEST['post_status'] ) ) {
			return;
		}

		/** Default ***********************************************************/

		// Add all supported forum visibilities
		$posts_query->set( 'post_status', array_keys( bbp_get_forum_visibilities() ) );

		// Get forums to exclude
		$hidden_ids = bbp_exclude_forum_ids( 'array' );

		// Bail if no forums to exclude
		if ( empty( $hidden_ids ) ) {
			return;
		}

		// Get any existing meta queries
		$not_in = $posts_query->get( 'post__not_in', array() );

		// Add our meta query to existing
		$not_in = array_unique( array_merge( $not_in, $hidden_ids ) );

		// Set the meta_query var
		$posts_query->set( 'post__not_in', $not_in );

	// Some other post type besides Forums, Topics, or Replies
	} elseif ( ! array_diff( $post_types, bbp_get_post_types() ) ) {

		// Get forums to exclude
		$forum_ids = bbp_exclude_forum_ids( 'meta_query' );

		// Bail if no forums to exclude
		if ( empty( $forum_ids ) ) {
			return;
		}

		// Get any existing meta queries
		$meta_query   = (array) $posts_query->get( 'meta_query', array() );

		// Add our meta query to existing
		$meta_query[] = $forum_ids;

		// Set the meta_query var
		$posts_query->set( 'meta_query', $meta_query );
	}
}

/**
 * Returns the forum's topic ids
 *
 * Only topics with published and closed statuses are returned
 *
 * @since 2.0.0 bbPress (r2908)
 *
 * @param int $forum_id Forum id
 */
function bbp_forum_query_topic_ids( $forum_id ) {
	$topic_ids = bbp_get_public_child_ids( $forum_id, bbp_get_topic_post_type() );

	// Filter & return
	return (array) apply_filters( 'bbp_forum_query_topic_ids', $topic_ids, $forum_id );
}

/**
 * Returns the forum's subforum ids
 *
 * Only forums with published status are returned
 *
 * @since 2.0.0 bbPress (r2908)
 *
 * @param int $forum_id Forum id
 */
function bbp_forum_query_subforum_ids( $forum_id ) {
	$subforum_ids = bbp_get_all_child_ids( $forum_id, bbp_get_forum_post_type() );

	// Filter & return
	return (array) apply_filters( 'bbp_forum_query_subforum_ids', $subforum_ids, $forum_id );
}

/**
 * Returns the forum's last reply id
 *
 * @since 2.0.0 bbPress (r2908)
 * @since 2.6.0 bbPress (r5954) Replace direct queries with WP_Query() objects
 *
 * @param int $forum_id Forum id.
 * @param int $topic_ids Optional. Topic ids.
 */
function bbp_forum_query_last_reply_id( $forum_id = 0, $topic_ids = 0 ) {

	// Validate forum
	$forum_id = bbp_get_forum_id( $forum_id );

	// Get topic ID's if none were passed
	if ( empty( $topic_ids ) ) {
		$topic_ids = bbp_forum_query_topic_ids( $forum_id );
	}

	$query = new WP_Query( array(
		'fields'           => 'ids',
		'suppress_filters' => true,
		'post_parent__in'  => $topic_ids,
		'post_status'      => bbp_get_public_status_id(),
		'post_type'        => bbp_get_reply_post_type(),
		'posts_per_page'   => 1,
		'orderby'          => array(
			'post_date' => 'DESC',
			'ID'        => 'DESC'
		),

		// Performance
		'update_post_term_cache' => false,
		'update_post_meta_cache' => false,
		'ignore_sticky_posts'    => true,
		'no_found_rows'          => true
	) );

	$reply_id = array_shift( $query->posts );

	unset( $query );

	// Filter & return
	return (int) apply_filters( 'bbp_forum_query_last_reply_id', $reply_id, $forum_id );
}

/** Listeners *****************************************************************/

/**
 * Check if it's a hidden forum or a topic or reply of a hidden forum and if
 * the user can't view it, then sets a 404
 *
 * @since 2.0.0 bbPress (r2996)
 */
function bbp_forum_enforce_hidden() {

	// Bail if not viewing a single item or if user has caps
	if ( ! is_singular() || bbp_is_user_keymaster() || current_user_can( 'read_hidden_forums' ) ) {
		return;
	}

	// Define local variables
	$forum_id = 0;
	$wp_query = bbp_get_wp_query();

	// Check post type
	switch ( $wp_query->get( 'post_type' ) ) {

		// Forum
		case bbp_get_forum_post_type() :
			$forum_id = bbp_get_forum_id( $wp_query->post->ID );
			break;

		// Topic
		case bbp_get_topic_post_type() :
			$forum_id = bbp_get_topic_forum_id( $wp_query->post->ID );
			break;

		// Reply
		case bbp_get_reply_post_type() :
			$forum_id = bbp_get_reply_forum_id( $wp_query->post->ID );
			break;
	}

	// If forum is explicitly hidden and user not capable, set 404
	if ( ! empty( $forum_id ) && bbp_is_forum_hidden( $forum_id ) && ! current_user_can( 'read_forum', $forum_id ) ) {
		bbp_set_404( $wp_query );
	}
}

/**
 * Check if it's a private forum or a topic or reply of a private forum and if
 * the user can't view it, then sets a 404
 *
 * @since 2.0.0 bbPress (r2996)
 */
function bbp_forum_enforce_private() {

	// Bail if not viewing a single item or if user has caps
	if ( ! is_singular() || bbp_is_user_keymaster() || current_user_can( 'read_private_forums' ) ) {
		return;
	}

	// Define local variables
	$forum_id = 0;
	$wp_query = bbp_get_wp_query();

	// Check post type
	switch ( $wp_query->get( 'post_type' ) ) {

		// Forum
		case bbp_get_forum_post_type() :
			$forum_id = bbp_get_forum_id( $wp_query->post->ID );
			break;

		// Topic
		case bbp_get_topic_post_type() :
			$forum_id = bbp_get_topic_forum_id( $wp_query->post->ID );
			break;

		// Reply
		case bbp_get_reply_post_type() :
			$forum_id = bbp_get_reply_forum_id( $wp_query->post->ID );
			break;

	}

	// If forum is explicitly hidden and user not capable, set 404
	if ( ! empty( $forum_id ) && bbp_is_forum_private( $forum_id ) && ! current_user_can( 'read_forum', $forum_id ) ) {
		bbp_set_404( $wp_query );
	}
}

/** Permissions ***************************************************************/

/**
 * Redirect if unauthorized user is attempting to edit a forum
 *
 * @since 2.1.0 bbPress (r3607)
 */
function bbp_check_forum_edit() {

	// Bail if not editing a topic
	if ( ! bbp_is_forum_edit() ) {
		return;
	}

	// User cannot edit topic, so redirect back to reply
	if ( ! current_user_can( 'edit_forum', bbp_get_forum_id() ) ) {
		bbp_redirect( bbp_get_forum_permalink() );
	}
}

/**
 * Delete all topics (and their replies) for a specific forum ID
 *
 * @since 2.1.0 bbPress (r3668)
 *
 * @param int $forum_id
 * @return If forum is not valid
 */
function bbp_delete_forum_topics( $forum_id = 0 ) {

	// Validate forum ID
	$forum_id = bbp_get_forum_id( $forum_id );
	if ( empty( $forum_id ) ) {
		return;
	}

	// Forum is being permanently deleted, so its content has go too
	// Note that we get all post statuses here
	$topics = new WP_Query( array(
		'fields'         => 'id=>parent',
		'post_type'      => bbp_get_topic_post_type(),
		'post_parent'    => $forum_id,
		'post_status'    => array_keys( get_post_stati() ),
		'posts_per_page' => -1,

		// Performance
		'nopaging'               => true,
		'suppress_filters'       => true,
		'update_post_term_cache' => false,
		'update_post_meta_cache' => false,
		'ignore_sticky_posts'    => true,
		'no_found_rows'          => true
	) );

	// Loop through and delete child topics. Topic replies will get deleted by
	// the bbp_delete_topic() action.
	if ( ! empty( $topics->posts ) ) {
		foreach ( $topics->posts as $topic ) {
			wp_delete_post( $topic->ID, true );
		}

		// Reset the $post global
		wp_reset_postdata();
	}

	// Cleanup
	unset( $topics );
}

/**
 * Trash all topics inside a forum
 *
 * @since 2.1.0 bbPress (r3668)
 *
 * @param int $forum_id
 * @return If forum is not valid
 */
function bbp_trash_forum_topics( $forum_id = 0 ) {

	// Validate forum ID
	$forum_id = bbp_get_forum_id( $forum_id );
	if ( empty( $forum_id ) ) {
		return;
	}

	// Allowed post statuses to pre-trash
	$post_stati = array(
		bbp_get_public_status_id(),
		bbp_get_closed_status_id(),
		bbp_get_pending_status_id()
	);

	// Forum is being trashed, so its topics (and replies) are trashed too
	$topics = new WP_Query( array(
		'fields'         => 'id=>parent',
		'post_type'      => bbp_get_topic_post_type(),
		'post_parent'    => $forum_id,
		'post_status'    => $post_stati,
		'posts_per_page' => -1,

		// Performance
		'nopaging'               => true,
		'suppress_filters'       => true,
		'update_post_term_cache' => false,
		'update_post_meta_cache' => false,
		'ignore_sticky_posts'    => true,
		'no_found_rows'          => true
	) );

	// Loop through and trash child topics. Topic replies will get trashed by
	// the bbp_trash_topic() action.
	if ( ! empty( $topics->posts ) ) {

		// Prevent debug notices
		$pre_trashed_topics = array();

		// Loop through topics, trash them, and add them to array
		foreach ( $topics->posts as $topic ) {
			wp_trash_post( $topic->ID, true );
			$pre_trashed_topics[] = $topic->ID;
		}

		// Set a post_meta entry of the topics that were trashed by this action.
		// This is so we can possibly untrash them, without untrashing topics
		// that were purposefully trashed before.
		update_post_meta( $forum_id, '_bbp_pre_trashed_topics', $pre_trashed_topics );

		// Reset the $post global
		wp_reset_postdata();
	}

	// Cleanup
	unset( $topics );
}

/**
 * Untrash all topics inside a forum
 *
 * @since 2.1.0 bbPress (r3668)
 *
 * @param int $forum_id
 * @return If forum is not valid
 */
function bbp_untrash_forum_topics( $forum_id = 0 ) {

	// Validate forum ID
	$forum_id = bbp_get_forum_id( $forum_id );

	if ( empty( $forum_id ) ) {
		return;
	}

	// Get the topics that were not previously trashed
	$pre_trashed_topics = get_post_meta( $forum_id, '_bbp_pre_trashed_topics', true );

	// There are topics to untrash
	if ( ! empty( $pre_trashed_topics ) ) {

		// Maybe reverse the trashed topics array
		if ( is_array( $pre_trashed_topics ) ) {
			$pre_trashed_topics = array_reverse( $pre_trashed_topics );
		}

		// Loop through topics
		foreach ( (array) $pre_trashed_topics as $topic ) {
			wp_untrash_post( $topic );
		}
	}
}

/** Before Delete/Trash/Untrash ***********************************************/

/**
 * Called before deleting a forum.
 *
 * This function is supplemental to the actual forum deletion which is
 * handled by WordPress core API functions. It is used to clean up after
 * a forum that is being deleted.
 *
 * @since 2.1.0 bbPress (r3668)
 */
function bbp_delete_forum( $forum_id = 0 ) {
	$forum_id = bbp_get_forum_id( $forum_id );

	if ( empty( $forum_id ) || ! bbp_is_forum( $forum_id ) ) {
		return false;
	}

	do_action( 'bbp_delete_forum', $forum_id );
}

/**
 * Called before trashing a forum
 *
 * This function is supplemental to the actual forum being trashed which is
 * handled by WordPress core API functions. It is used to clean up after
 * a forum that is being trashed.
 *
 * @since 2.1.0 bbPress (r3668)
 */
function bbp_trash_forum( $forum_id = 0 ) {
	$forum_id = bbp_get_forum_id( $forum_id );

	if ( empty( $forum_id ) || ! bbp_is_forum( $forum_id ) ) {
		return false;
	}

	do_action( 'bbp_trash_forum', $forum_id );
}

/**
 * Called before untrashing a forum
 *
 * @since 2.1.0 bbPress (r3668)
 */
function bbp_untrash_forum( $forum_id = 0 ) {
	$forum_id = bbp_get_forum_id( $forum_id );

	if ( empty( $forum_id ) || ! bbp_is_forum( $forum_id ) ) {
		return false;
	}

	do_action( 'bbp_untrash_forum', $forum_id );
}

/** After Delete/Trash/Untrash ************************************************/

/**
 * Called after deleting a forum
 *
 * Try not to use this action. All meta & taxonomy terms have already been
 * deleted, making them impossible to use.
 *
 * @since 2.1.0 bbPress (r3668)
 * @since 2.6.0 bbPress (r6526) Not recommend for usage
 */
function bbp_deleted_forum( $forum_id = 0 ) {
	$forum_id = bbp_get_forum_id( $forum_id );

	if ( empty( $forum_id ) || ! bbp_is_forum( $forum_id ) ) {
		return false;
	}

	do_action( 'bbp_deleted_forum', $forum_id );
}

/**
 * Called after trashing a forum
 *
 * @since 2.1.0 bbPress (r3668)
 */
function bbp_trashed_forum( $forum_id = 0 ) {
	$forum_id = bbp_get_forum_id( $forum_id );

	if ( empty( $forum_id ) || ! bbp_is_forum( $forum_id ) ) {
		return false;
	}

	do_action( 'bbp_trashed_forum', $forum_id );
}

/**
 * Called after untrashing a forum
 *
 * @since 2.1.0 bbPress (r3668)
 */
function bbp_untrashed_forum( $forum_id = 0 ) {
	$forum_id = bbp_get_forum_id( $forum_id );

	if ( empty( $forum_id ) || ! bbp_is_forum( $forum_id ) ) {
		return false;
	}

	do_action( 'bbp_untrashed_forum', $forum_id );
}
