<?php

/**
 * bbPress Topic Functions
 *
 * @package bbPress
 * @subpackage Functions
 */

// Redirect if accessed directly
if ( !defined( 'ABSPATH' ) ) exit;

/** Post Form Handlers ********************************************************/

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
 * @uses bbp_is_forum_category() To check if the forum is a category
 * @uses bbp_is_forum_closed() To check if the forum is closed
 * @uses bbp_is_forum_private() To check if the forum is private
 * @uses bbp_check_for_flood() To check for flooding
 * @uses bbp_check_for_duplicate() To check for duplicates
 * @uses bbp_get_topic_post_type() To get the topic post type
 * @uses remove_filter() To remove 'wp_filter_kses' filters if needed
 * @uses apply_filters() Calls 'bbp_new_topic_pre_title' with the content
 * @uses apply_filters() Calls 'bbp_new_topic_pre_content' with the content
 * @uses bbPress::errors::get_error_codes() To get the {@link WP_Error} errors
 * @uses wp_insert_post() To insert the topic
 * @uses do_action() Calls 'bbp_new_topic' with the topic id, forum id,
 *                    anonymous data and reply author
 * @uses bbp_stick_topic() To stick or super stick the topic
 * @uses bbp_unstick_topic() To unstick the topic
 * @uses bbp_get_topic_permalink() To get the topic permalink
 * @uses wp_redirect() To redirect to the topic link
 * @uses bbPress::errors::get_error_messages() To get the {@link WP_Error} error
 *                                              messages
 */
function bbp_new_topic_handler() {

	// Only proceed if POST is a new topic
	if ( 'POST' == strtoupper( $_SERVER['REQUEST_METHOD'] ) && !empty( $_POST['action'] ) && ( 'bbp-new-topic' === $_POST['action'] ) ) {
		global $bbp;

		// Nonce check
		check_admin_referer( 'bbp-new-topic' );

		// Set defaults to prevent debug notices
		$forum_id = $topic_author = $anonymous_data = 0;
		$topic_title = $topic_content = '';
		$terms = array( $bbp->topic_tag_id => array() );

		/** Topic Author ******************************************************/

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

			// User cannot create topics
			if ( !current_user_can( 'publish_topics' ) ) {
				$bbp->errors->add( 'bbp_topic_permissions', __( '<strong>ERROR</strong>: You do not have permission to create new topics.', 'bbpress' ) );
			}

			// Topic author is current user
			$topic_author = bbp_get_current_user_id();

		}

		// Remove wp_filter_kses filters from title and content for capable users and if the nonce is verified
		if ( current_user_can( 'unfiltered_html' ) && !empty( $_POST['_bbp_unfiltered_html_topic'] ) && wp_create_nonce( 'bbp-unfiltered-html-topic_new' ) == $_POST['_bbp_unfiltered_html_topic'] ) {
			remove_filter( 'bbp_new_topic_pre_title',   'wp_filter_kses' );
			remove_filter( 'bbp_new_topic_pre_content', 'wp_filter_kses' );
		}

		/** Topic Title *******************************************************/

		// Topic title
		if ( !empty( $_POST['bbp_topic_title'] ) ) {

			// Sanitize and strip HTML tags
			$topic_title = esc_attr( strip_tags( $_POST['bbp_topic_title'] ) );

			// Filter and sanitize
			$topic_title = apply_filters( 'bbp_new_topic_pre_title', $topic_title );
		}

		// No topic title
		if ( empty( $topic_title ) )
			$bbp->errors->add( 'bbp_topic_title', __( '<strong>ERROR</strong>: Your topic needs a title.', 'bbpress' ) );

		/** Topic Content *****************************************************/

		// Topic content
		if ( !empty( $_POST['bbp_topic_content'] ) ) {

			// Set topic content
			$topic_content = $_POST['bbp_topic_content'];

			// Filter and sanitize
			$topic_content = apply_filters( 'bbp_new_topic_pre_content', $topic_content );
		}

		// No topic content
		if ( empty( $topic_content ) )
			$bbp->errors->add( 'bbp_topic_content', __( '<strong>ERROR</strong>: Your topic needs some content.', 'bbpress' ) );

		/** Topic Forum *******************************************************/

		// Forum id was not passed
		if ( empty( $_POST['bbp_forum_id'] ) )
			$bbp->errors->add( 'bbp_topic_forum_id', __( '<strong>ERROR</strong>: Forum ID is missing.', 'bbpress' ) );

		// Forum id was passed
		elseif ( is_numeric( $_POST['bbp_forum_id'] ) )
			$forum_id = (int) $_POST['bbp_forum_id'];

		// Forum exists
		if ( !empty( $forum_id ) ) {

			// Forum is a category
			if ( bbp_is_forum_category( $forum_id ) )
				$bbp->errors->add( 'bbp_edit_topic_forum_category', __( '<strong>ERROR</strong>: This forum is a category. No topics can be created in this forum.', 'bbpress' ) );

			// Forum is closed and user cannot access
			if ( bbp_is_forum_closed( $forum_id ) && !current_user_can( 'edit_forum', $forum_id ) )
				$bbp->errors->add( 'bbp_edit_topic_forum_closed', __( '<strong>ERROR</strong>: This forum has been closed to new topics.', 'bbpress' ) );

			// Forum is private and user cannot access
			if ( bbp_is_forum_private( $forum_id ) && !current_user_can( 'read_private_forums' ) )
				$bbp->errors->add( 'bbp_edit_topic_forum_private', __( '<strong>ERROR</strong>: This forum is private and you do not have the capability to read or create new topics in it.', 'bbpress' ) );

			// Forum is hidden and user cannot access
			if ( bbp_is_forum_hidden( $forum_id ) && !current_user_can( 'read_hidden_forums' ) )
				$bbp->errors->add( 'bbp_edit_topic_forum_hidden', __( '<strong>ERROR</strong>: This forum is hidden and you do not have the capability to read or create new topics in it.', 'bbpress' ) );
		}

		/** Topic Flooding ****************************************************/

		if ( !bbp_check_for_flood( $anonymous_data, $topic_author ) )
			$bbp->errors->add( 'bbp_topic_flood', __( '<strong>ERROR</strong>: Slow down; you move too fast.', 'bbpress' ) );

		/** Topic Duplicate ***************************************************/

		if ( !bbp_check_for_duplicate( array( 'post_type' => bbp_get_topic_post_type(), 'post_author' => $topic_author, 'post_content' => $topic_content, 'anonymous_data' => $anonymous_data ) ) )
			$bbp->errors->add( 'bbp_topic_duplicate', __( '<strong>ERROR</strong>: Duplicate topic detected; it looks as though you&#8217;ve already said that!', 'bbpress' ) );

		/** Topic Tags ********************************************************/

		if ( !empty( $_POST['bbp_topic_tags'] ) ) {

			// Escape tag input
			$terms = esc_attr( strip_tags( $_POST['bbp_topic_tags'] ) );

			// Explode by comma
			if ( strstr( $terms, ',' ) ) {
				$terms = explode( ',', $terms );
			}

			// Add topic tag ID as main key
			$terms = array( $bbp->topic_tag_id => $terms );
		}

		/** Additional Actions (Before Save) **********************************/

		do_action( 'bbp_new_topic_pre_extras' );

		/** No Errors *********************************************************/

		if ( !is_wp_error( $bbp->errors ) || !$bbp->errors->get_error_codes() ) {

			/** Create new topic **********************************************/

			// Add the content of the form to $post as an array
			$topic_data = array(
				'post_author'  => $topic_author,
				'post_title'   => $topic_title,
				'post_content' => $topic_content,
				'post_parent'  => $forum_id,
				'tax_input'    => $terms,
				'post_status'  => 'publish',
				'post_type'    => bbp_get_topic_post_type()
			);

			// Insert topic
			$topic_id = wp_insert_post( $topic_data );

			/** No Errors *****************************************************/

			if ( !empty( $topic_id ) && !is_wp_error( $topic_id ) ) {

				/** Stickies **************************************************/

				if ( !empty( $_POST['bbp_stick_topic'] ) && in_array( $_POST['bbp_stick_topic'], array( 'stick', 'super', 'unstick' ) ) ) {

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

						// We can avoid this as it is a new topic
						case 'unstick' :
						default        :
							break;
					}

				}

				// Update counts, etc...
				do_action( 'bbp_new_topic', $topic_id, $forum_id, $anonymous_data, $topic_author );

				/** Successful Save *******************************************/

				// Redirect back to new reply
				wp_redirect( bbp_get_topic_permalink( $topic_id ) . '#post-' . $topic_id );

				// For good measure
				exit();

			// Errors
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
 * @uses bbp_get_topic() To get the topic
 * @uses check_admin_referer() To verify the nonce and check the referer
 * @uses bbp_is_topic_anonymous() To check if topic is by an anonymous user
 * @uses current_user_can() To check if the current user can edit the topic
 * @uses bbp_filter_anonymous_post_data() To filter anonymous data
 * @uses is_wp_error() To check if the value retrieved is a {@link WP_Error}
 * @uses esc_attr() For sanitization
 * @uses bbp_is_forum_category() To check if the forum is a category
 * @uses bbp_is_forum_closed() To check if the forum is closed
 * @uses bbp_is_forum_private() To check if the forum is private
 * @uses remove_filter() To remove 'wp_filter_kses' filters if needed
 * @uses apply_filters() Calls 'bbp_edit_topic_pre_title' with the title and
 *                        topic id
 * @uses apply_filters() Calls 'bbp_edit_topic_pre_content' with the content
 *                        and topic id
 * @uses bbPress::errors::get_error_codes() To get the {@link WP_Error} errors
 * @uses wp_save_post_revision() To save a topic revision
 * @uses bbp_update_topic_revision_log() To update the topic revision log
 * @uses bbp_stick_topic() To stick or super stick the topic
 * @uses bbp_unstick_topic() To unstick the topic
 * @uses wp_update_post() To update the topic
 * @uses do_action() Calls 'bbp_edit_topic' with the topic id, forum id,
 *                    anonymous data and reply author
 * @uses bbp_move_topic_handler() To handle movement of a topic from one forum
 *                                 to another
 * @uses bbp_get_topic_permalink() To get the topic permalink
 * @uses wp_redirect() To redirect to the topic link
 * @uses bbPress::errors::get_error_messages() To get the {@link WP_Error} error
 *                                              messages
 */
function bbp_edit_topic_handler() {

	// Only proceed if POST is an edit topic request
	if ( ( 'POST' === strtoupper( $_SERVER['REQUEST_METHOD'] ) ) && ( !empty( $_POST['action'] ) && ( 'bbp-edit-topic' === $_POST['action'] ) ) ) {
		global $bbp;

		// Set defaults to prevent debug notices
		$topic_id = $forum_id = $anonymous_data = 0;
		$topic_title = $topic_content = $topic_edit_reason = '';
		$terms = array( $bbp->topic_tag_id => array() );

		/** Topic *************************************************************/

		// Topic id was not passed
		if ( empty( $_POST['bbp_topic_id'] ) )
			$bbp->errors->add( 'bbp_edit_topic_id', __( '<strong>ERROR</strong>: Topic ID not found.', 'bbpress' ) );

		// Topic id was passed
		elseif ( is_numeric( $_POST['bbp_topic_id'] ) )
			$topic_id = (int) $_POST['bbp_topic_id'];

		// Topic does not exist
		if ( !$topic = bbp_get_topic( $topic_id ) ) {
			$bbp->errors->add( 'bbp_edit_topic_not_found', __( '<strong>ERROR</strong>: The topic you want to edit was not found.', 'bbpress' ) );

		// Topic exists
		} else {

			// Nonce check
			check_admin_referer( 'bbp-edit-topic_' . $topic_id );

			// Check users ability to create new topic
			if ( !bbp_is_topic_anonymous( $topic_id ) ) {

				// User cannot edit this topic
				if ( !current_user_can( 'edit_topic', $topic_id ) ) {
					$bbp->errors->add( 'bbp_edit_topic_permissions', __( '<strong>ERROR</strong>: You do not have permission to edit that topic.', 'bbpress' ) );
				}

			// It is an anonymous post
			} else {

				// Filter anonymous data
				$anonymous_data = bbp_filter_anonymous_post_data( array(), true );
			}
		}

		// Remove wp_filter_kses filters from title and content for capable users and if the nonce is verified
		if ( current_user_can( 'unfiltered_html' ) && !empty( $_POST['_bbp_unfiltered_html_topic'] ) && ( wp_create_nonce( 'bbp-unfiltered-html-topic_' . $topic_id ) == $_POST['_bbp_unfiltered_html_topic'] ) ) {
			remove_filter( 'bbp_edit_topic_pre_title',   'wp_filter_kses' );
			remove_filter( 'bbp_edit_topic_pre_content', 'wp_filter_kses' );
		}

		/** Topic Forum *******************************************************/

		// Forum id was not passed
		if ( empty( $_POST['bbp_forum_id'] ) )
			$bbp->errors->add( 'bbp_topic_forum_id', __( '<strong>ERROR</strong>: Forum ID is missing.', 'bbpress' ) );

		// Forum id was passed
		elseif ( is_numeric( $_POST['bbp_forum_id'] ) )
			$forum_id = (int) $_POST['bbp_forum_id'];

		// Forum exists
		if ( !empty( $forum_id ) && ( $forum_id != $topic->post_parent ) ) {

			// Forum is a category
			if ( bbp_is_forum_category( $forum_id ) )
				$bbp->errors->add( 'bbp_edit_topic_forum_category', __( '<strong>ERROR</strong>: This forum is a category. No topics can be created in this forum.', 'bbpress' ) );

			// Forum is closed and user cannot access
			if ( bbp_is_forum_closed( $forum_id ) && !current_user_can( 'edit_forum', $forum_id ) )
				$bbp->errors->add( 'bbp_edit_topic_forum_closed', __( '<strong>ERROR</strong>: This forum has been closed to new topics.', 'bbpress' ) );

			// Forum is private and user cannot access
			if ( bbp_is_forum_private( $forum_id ) && !current_user_can( 'read_private_forums' ) )
				$bbp->errors->add( 'bbp_edit_topic_forum_private', __( '<strong>ERROR</strong>: This forum is private and you do not have the capability to read or create new topics in it.', 'bbpress' ) );

			// Forum is hidden and user cannot access
			if ( bbp_is_forum_hidden( $forum_id ) && !current_user_can( 'read_hidden_forums' ) )
				$bbp->errors->add( 'bbp_edit_topic_forum_hidden', __( '<strong>ERROR</strong>: This forum is hidden and you do not have the capability to read or create new topics in it.', 'bbpress' ) );
		}

		/** Topic Title *******************************************************/

		// Topic title
		if ( !empty( $_POST['bbp_topic_title'] ) ) {

			// Sanitize and strip HTML tags
			$topic_title = esc_attr( strip_tags( $_POST['bbp_topic_title'] ) );

			// Filter and sanitize
			$topic_title = apply_filters( 'bbp_edit_topic_pre_title', $topic_title, $topic_id );
		}

		// No topic title
		if ( empty( $topic_title ) )
			$bbp->errors->add( 'bbp_edit_topic_title', __( '<strong>ERROR</strong>: Your topic needs a title.', 'bbpress' ) );

		/** Topic Content *****************************************************/

		// Topic content
		if ( !empty( $_POST['bbp_topic_content'] ) ) {

			// Set topic content
			$topic_content = $_POST['bbp_topic_content'];

			// Filter and sanitize
			$topic_content = apply_filters( 'bbp_edit_topic_pre_content', $topic_content, $topic_id );
		}

		// No topic content
		if ( empty( $topic_content ) )
			$bbp->errors->add( 'bbp_edit_topic_content', __( '<strong>ERROR</strong>: Your topic cannot be empty.', 'bbpress' ) );

		/** Topic Tags ********************************************************/

		// Tags
		if ( !empty( $_POST['bbp_topic_tags'] ) ) {

			// Escape tag input
			$terms = esc_attr( strip_tags( $_POST['bbp_topic_tags'] ) );

			// Explode by comma
			if ( strstr( $terms, ',' ) ) {
				$terms = explode( ',', $terms );
			}

			// Add topic tag ID as main key
			$terms = array( $bbp->topic_tag_id => $terms );
		}

		/** Additional Actions (Before Save) **********************************/

		do_action( 'bbp_edit_topic_pre_extras', $topic_id );

		/** No Errors *********************************************************/

		if ( !is_wp_error( $bbp->errors ) || !$bbp->errors->get_error_codes() ) {

			/** Stickies ******************************************************/

			if ( !empty( $_POST['bbp_stick_topic'] ) && in_array( $_POST['bbp_stick_topic'], array( 'stick', 'super', 'unstick' ) ) ) {

				// What's the dilly?
				switch ( $_POST['bbp_stick_topic'] ) {

					// Sticky in forum
					case 'stick'   :
						bbp_stick_topic( $topic_id );
						break;

					// Sticky in all forums
					case 'super'   :
						bbp_stick_topic( $topic_id, true );
						break;

					// Normal
					case 'unstick' :
					default        :
						bbp_unstick_topic( $topic_id );
						break;
				}
			}

			/** Update the topic **********************************************/

			// Add the content of the form to $post as an array
			$topic_data = array(
				'ID'           => $topic_id,
				'post_title'   => $topic_title,
				'post_content' => $topic_content,
				'post_parent'  => $forum_id,
				'tax_input'    => $terms,
			);

			// Insert topic
			$topic_id = wp_update_post( $topic_data );

			/** Revisions *****************************************************/

			// Revision Reason
			if ( !empty( $_POST['bbp_topic_edit_reason'] ) ) {
				$topic_edit_reason = esc_attr( strip_tags( $_POST['bbp_topic_edit_reason'] ) );
			}

			// Update revision log
			if ( !empty( $_POST['bbp_log_topic_edit'] ) && ( 1 == $_POST['bbp_log_topic_edit'] ) && ( $revision_id = wp_save_post_revision( $topic_id ) ) ) {
				bbp_update_topic_revision_log( array(
					'topic_id'    => $topic_id,
					'revision_id' => $revision_id,
					'author_id'   => bbp_get_current_user_id(),
					'reason'      => $topic_edit_reason
				) );
			}

			/** No Errors *****************************************************/

			if ( !empty( $topic_id ) && !is_wp_error( $topic_id ) ) {

				// Update counts, etc...
				do_action( 'bbp_edit_topic', $topic_id, $forum_id, $anonymous_data, $topic->post_author , true /* Is edit */ );

				// If the new forum id is not equal to the old forum id, run the
				// bbp_move_topic action and pass the topic's forum id as the
				// first arg and topic id as the second to update counts.
				if ( $forum_id != $topic->post_parent )
					bbp_move_topic_handler( $topic_id, $topic->post_parent, $forum_id );

				/** Additional Actions (After Save) ***************************/

				do_action( 'bbp_edit_topic_post_extras', $topic_id );

				/** Successful Edit *******************************************/

				// Redirect back to new topic
				wp_redirect( bbp_get_topic_permalink( $topic_id ) );

				// For good measure
				exit();

			/** Errors ********************************************************/
			} else {
				$append_error = ( is_wp_error( $topic_id ) && $topic_id->get_error_message() ) ? $topic_id->get_error_message() . ' ' : '';
				$bbp->errors->add( 'bbp_topic_error', __( '<strong>ERROR</strong>: The following problem(s) have been found with your topic:' . $append_error . 'Please try again.', 'bbpress' ) );
			}
		}
	}
}

/**
 * Handles new topic submission from within wp-admin
 *
 * @param int $topic_id Topic id
 * @param object $topic Topic
 * @uses bbp_get_topic_post_type() To get the topic post type
 * @uses bbp_update_topic() To update the topic
 */
function bbp_new_topic_admin_handler( $topic_id, $topic ) {
	global $bbp;

	if (    // Check if POST action
			'POST' === strtoupper( $_SERVER['REQUEST_METHOD'] ) &&

			// Check Actions exist in POST
			!empty( $_POST['action']    )                       &&
			!empty( $_POST['post_type'] )                       &&

			// Check that actions match what we need
			'editpost'                === $_POST['action']      &&
			'publish'                 === $_POST['post_status'] &&
			bbp_get_topic_post_type() === $_POST['post_type']
	) {

		// Update the topic meta bidness
		$parent_id = !empty( $_POST['parent_id'] ) ? (int) $_POST['parent_id'] : 0;
		bbp_update_topic( $topic_id, $parent_id );
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
 * @yses bbp_get_topic_forum_id() To get the topic forum id
 * @uses update_post_meta() To update the topic metas
 * @uses set_transient() To update the flood check transient for the ip
 * @uses update_user_meta() To update the last posted meta for the user
 * @uses bbp_is_subscriptions_active() To check if the subscriptions feature is
 *                                      activated or not
 * @uses bbp_is_user_subscribed() To check if the user is subscribed
 * @uses bbp_remove_user_subscription() To remove the user's subscription
 * @uses bbp_add_user_subscription() To add the user's subscription
 * @uses bbp_update_topic_forum_id() To update the topic's forum id
 * @uses bbp_update_topic_topic_id() To update the topic's topic id
 * @uses bbp_update_topic_last_reply_id() To update the last reply id topic meta
 * @uses bbp_update_topic_last_active_id() To update the topic last active id
 * @uses bbp_update_topic_last_active_time() To update the last active topic meta
 * @uses bbp_update_topic_reply_count() To update the topic reply count
 * @uses bbp_update_topic_hidden_reply_count() To udpate the topic hidden reply count
 * @uses bbp_update_topic_voice_count() To update the topic voice count
 * @uses bbp_update_topic_walker() To udpate the topic's ancestors
 */
function bbp_update_topic( $topic_id = 0, $forum_id = 0, $anonymous_data = false, $author_id = 0, $is_edit = false ) {

	// Validate the ID's passed from 'bbp_new_topic' action
	$topic_id = bbp_get_topic_id( $topic_id );
	$forum_id = bbp_get_forum_id( $forum_id );

	// Check author_id
	if ( empty( $author_id ) )
		$author_id = bbp_get_current_user_id();

	// Check forum_id
	if ( empty( $forum_id ) )
		$forum_id = bbp_get_topic_forum_id( $topic_id );

	// If anonymous post, store name, email, website and ip in post_meta.
	// It expects anonymous_data to be sanitized.
	// Check bbp_filter_anonymous_post_data() for sanitization.
	if ( !empty( $anonymous_data ) && is_array( $anonymous_data ) ) {
		extract( $anonymous_data );

		update_post_meta( $topic_id, '_bbp_anonymous_name',  $bbp_anonymous_name,  false );
		update_post_meta( $topic_id, '_bbp_anonymous_email', $bbp_anonymous_email, false );

		// Set transient for throttle check (only on new, not edit)
		if ( empty( $is_edit ) )
			set_transient( '_bbp_' . bbp_current_author_ip() . '_last_posted', time() );

		// Website is optional
		if ( !empty( $bbp_anonymous_website ) )
			update_post_meta( $topic_id, '_bbp_anonymous_website', $bbp_anonymous_website, false );
	} else {
		if ( empty( $is_edit ) && !current_user_can( 'throttle' ) )
			update_user_meta( $author_id, '_bbp_last_posted', time() );
	}

	// Handle Subscription Checkbox
	if ( bbp_is_subscriptions_active() && !empty( $author_id ) ) {
		$subscribed = bbp_is_user_subscribed( $author_id, $topic_id );
		$subscheck  = ( !empty( $_POST['bbp_topic_subscription'] ) && ( 'bbp_subscribe' == $_POST['bbp_topic_subscription'] ) ) ? true : false;

		// Subscribed and unsubscribing
		if ( true == $subscribed && false == $subscheck )
			bbp_remove_user_subscription( $author_id, $topic_id );

		// Subscribing
		elseif ( false == $subscribed && true == $subscheck )
			bbp_add_user_subscription( $author_id, $topic_id );
	}

	// Forum topic meta
	bbp_update_topic_forum_id( $topic_id, $forum_id );
	bbp_update_topic_topic_id( $topic_id, $topic_id );

	// Update associated topic values if this is a new topic
	if ( empty( $is_edit ) ) {

		// Update poster IP if not editing
		update_post_meta( $topic_id, '_bbp_author_ip', bbp_current_author_ip(), false );

		// Last active time
		$last_active = current_time( 'mysql' );

		// Reply topic meta
		bbp_update_topic_last_reply_id      ( $topic_id, 0            );
		bbp_update_topic_last_active_id     ( $topic_id, $topic_id    );
		bbp_update_topic_last_active_time   ( $topic_id, $last_active );
		bbp_update_topic_reply_count        ( $topic_id, 0            );
		bbp_update_topic_hidden_reply_count ( $topic_id, 0            );
		bbp_update_topic_voice_count        ( $topic_id               );

		// Walk up ancestors and do the dirty work
		bbp_update_topic_walker( $topic_id, $last_active, $forum_id, 0, false );
	}
}

/**
 * Walks up the post_parent tree from the current topic_id, and updates the
 * counts of forums above it. This calls a few internal functions that all run
 * manual queries against the database to get their results. As such, this
 * function can be costly to run but is necessary to keep everything accurate.
 *
 * @since bbPress (r2800)
 * @param int $topic_id Topic id
 * @param string $last_active_time Optional. Last active time
 * @param int $forum_id Optional. Forum id
 * @param int $reply_id Optional. Reply id
 * @param bool $refresh Reset all the previous parameters? Defaults to true.
 * @uses bbp_get_topic_id() To get the topic id
 * @uses bbp_get_topic_forum_id() To get the topic forum id
 * @uses get_post_ancestors() To get the topic's ancestors
 * @uses bbp_is_forum() To check if the ancestor is a forum
 * @uses bbp_update_forum() To update the forum
 */
function bbp_update_topic_walker( $topic_id, $last_active_time = '', $forum_id = 0, $reply_id = 0, $refresh = true ) {

	// Validate topic_id
	if ( $topic_id = bbp_get_topic_id( $topic_id ) ) {

		// Get the forum ID if none was passed
		if ( empty( $forum_id )  )
			$forum_id = bbp_get_topic_forum_id( $topic_id );

		// Set the active_id based on topic_id/reply_id
		$active_id = empty( $reply_id ) ? $topic_id : $reply_id;
	}

	// Get topic ancestors
	$ancestors = array_values( array_unique( array_merge( array( $forum_id ), get_post_ancestors( $topic_id ) ) ) );

	// If we want a full refresh, unset any of the possibly passed variables
	if ( true == $refresh )
		$forum_id = $topic_id = $reply_id = $active_id = $last_active_time = 0;

	// Loop through ancestors
	foreach ( $ancestors as $ancestor ) {

		// If ancestor is a forum, update counts
		if ( bbp_is_forum( $ancestor ) ) {

			// Update the forum
			bbp_update_forum( array(
				'forum_id'         => $ancestor,
				'last_topic_id'    => $topic_id,
				'last_reply_id'    => $reply_id,
				'last_active_id'   => $active_id,
				'last_active_time' => 0,
			) );
		}
	}
}

/**
 * Handle the moving of a topic from one forum to another. This includes walking
 * up the old and new branches and updating the counts.
 *
 * @param int $topic_id Topic id
 * @param int $old_forum_id Old forum id
 * @param int $new_forum_id New forum id
 * @uses bbp_get_topic_id() To get the topic id
 * @uses bbp_get_forum_id() To get the forum id
 * @uses bbp_get_reply_post_type() To get the reply post type
 * @uses bbp_get_public_child_ids() To get the public child ids
 * @uses bbp_update_reply_forum_id() To update the reply forum id
 * @uses bbp_update_topic_forum_id() To update the topic forum id
 * @uses get_post_ancestors() To get the topic's ancestors
 * @uses bbp_is_forum() To check if the ancestor is a forum
 * @uses bbp_update_forum() To update the forum
 */
function bbp_move_topic_handler( $topic_id, $old_forum_id, $new_forum_id ) {
	$topic_id     = bbp_get_topic_id( $topic_id     );
	$old_forum_id = bbp_get_forum_id( $old_forum_id );
	$new_forum_id = bbp_get_forum_id( $new_forum_id );
	$replies      = bbp_get_public_child_ids( $topic_id, bbp_get_reply_post_type() );

	// Update the forum_id of all replies in the topic
	foreach ( $replies as $reply_id )
		bbp_update_reply_forum_id( $reply_id, $new_forum_id );

	// Forum topic meta
	bbp_update_topic_forum_id( $topic_id, $new_forum_id );

	/** Old forum_id **********************************************************/

	// Get topic ancestors
	$ancestors = array_values( array_unique( array_merge( array( $old_forum_id ), get_post_ancestors( $old_forum_id ) ) ) );

	// Loop through ancestors
	foreach ( $ancestors as $ancestor ) {

		// If ancestor is a forum, update counts
		if ( bbp_is_forum( $ancestor ) ) {

			// Update the forum
			bbp_update_forum( array(
				'forum_id' => $ancestor,
			) );
		}
	}

	/** New forum_id **********************************************************/

	// Make sure we're not walking twice
	if ( !in_array( $new_forum_id, $ancestors ) ) {

		// Get topic ancestors
		$ancestors = array_values( array_unique( array_merge( array( $new_forum_id ), get_post_ancestors( $new_forum_id ) ) ) );

		// Loop through ancestors
		foreach ( $ancestors as $ancestor ) {

			// If ancestor is a forum, update counts
			if ( bbp_is_forum( $ancestor ) ) {

				// Update the forum
				bbp_update_forum( array(
					'forum_id' => $ancestor,
				) );
			}
		}
	}
}

/**
 * Merge topic handler
 *
 * Handles the front end merge topic submission
 *
 * @since bbPress (r2756)
 *
 * @uses bbPress:errors::add() To log various error messages
 * @uses bbp_get_topic() To get the topics
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
 * @uses bbp_get_reply_post_type() To get the reply post type
 * @uses get_posts() To get the replies
 * @uses wp_update_post() To update the topic
 * @uses bbp_update_reply_topic_id() To update the reply topic id
 * @uses bbp_get_topic_forum_id() To get the topic forum id
 * @uses bbp_update_reply_forum_id() To update the reply forum id
 * @uses do_action() Calls 'bbp_merged_topic_reply' with the reply id and
 *                    destination topic id
 * @uses do_action() Calls 'bbp_merged_topic' with the destination and source
 *                    topic ids and source topic's forum id
 * @uses bbp_get_topic_permalink() To get the topic permalink
 * @uses wp_redirect() To redirect to the topic link
 */
function bbp_merge_topic_handler() {

	// Only proceed if POST is an merge topic request
	if ( 'POST' == strtoupper( $_SERVER['REQUEST_METHOD'] ) && !empty( $_POST['action'] ) && ( 'bbp-merge-topic' === $_POST['action'] ) ) {
		global $bbp;

		// Prevent debug notices
		$source_topic_id = $destination_topic_id = 0;
		$source_topic = $destination_topic = 0;
		$subscribers = $favoriters = $replies = array();

		/** Source Topic ******************************************************/

		// Topic id
		if ( empty( $_POST['bbp_topic_id'] ) )
			$bbp->errors->add( 'bbp_merge_topic_source_id', __( '<strong>ERROR</strong>: Topic ID not found.', 'bbpress' ) );
		else
			$source_topic_id = (int) $_POST['bbp_topic_id'];

		// Nonce check
		check_admin_referer( 'bbp-merge-topic_' . $source_topic_id );

		// Source topic not found
		if ( !$source_topic = bbp_get_topic( $source_topic_id ) )
			$bbp->errors->add( 'bbp_merge_topic_source_not_found', __( '<strong>ERROR</strong>: The topic you want to merge was not found.', 'bbpress' ) );

		// Cannot edit source topic
		if ( !current_user_can( 'edit_topic', $source_topic->ID ) )
			$bbp->errors->add( 'bbp_merge_topic_source_permission', __( '<strong>ERROR</strong>: You do not have the permissions to edit the source topic.', 'bbpress' ) );

		/** Destination Topic *************************************************/

		// Topic id
		if ( empty( $_POST['bbp_destination_topic'] ) )
			$bbp->errors->add( 'bbp_merge_topic_destination_id', __( '<strong>ERROR</strong>: Destination topic ID not found.', 'bbpress' ) );
		else
			$destination_topic_id = (int) $_POST['bbp_destination_topic'];

		// Destination topic not found
		if ( !$destination_topic = bbp_get_topic( $destination_topic_id ) )
			$bbp->errors->add( 'bbp_merge_topic_destination_not_found', __( '<strong>ERROR</strong>: The topic you want to merge to was not found.', 'bbpress' ) );

		// Cannot edit destination topic
		if ( !current_user_can( 'edit_topic', $destination_topic->ID ) )
			$bbp->errors->add( 'bbp_merge_topic_destination_permission', __( '<strong>ERROR</strong>: You do not have the permissions to edit the destination topic.', 'bbpress' ) );

		/** No Errors *********************************************************/

		if ( !is_wp_error( $bbp->errors ) || !$bbp->errors->get_error_codes() ) {

			// Update counts, etc...
			do_action( 'bbp_merge_topic', $destination_topic->ID, $source_topic->ID );

			/** Date Check ****************************************************/

			// Check if the destination topic is older than the source topic
			if ( strtotime( $source_topic->post_date ) < strtotime( $destination_topic->post_date ) ) {

				// Set destination topic post_date to 1 second before source topic
				$destination_post_date = date( 'Y-m-d H:i:s', strtotime( $source_topic->post_date ) - 1 );

				$postarr = array(
					'ID'            => $destination_topic_id,
					'post_date'     => $destination_post_date,
					'post_date_gmt' => get_gmt_from_date( $destination_post_date )
				);

				// Update destination topic
				wp_update_post( $postarr );
			}

			/** Subscriptions *************************************************/

			// Remove the topic from everybody's subscriptions
			if ( $subscribers = bbp_get_topic_subscribers( $source_topic->ID ) ) {

				// Loop through each user
				foreach ( (array) $subscribers as $subscriber ) {

					// Shift the subscriber if told to
					if ( !empty( $_POST['bbp_topic_subscribers'] ) && ( 1 == $_POST['bbp_topic_subscribers'] ) && bbp_is_subscriptions_active() )
						bbp_add_user_subscription( $subscriber, $destination_topic->ID );

					// Remove old subscription
					bbp_remove_user_subscription( $subscriber, $source_topic->ID );
				}
			}

			/** Favorites *****************************************************/

			// Remove the topic from everybody's favorites
			if ( $favoriters = bbp_get_topic_favoriters( $source_topic->ID ) ) {

				// Loop through each user
				foreach ( (array) $favoriters as $favoriter ) {

					// Shift the favoriter if told to
					if ( !empty( $_POST['bbp_topic_favoriters'] ) && 1 == $_POST['bbp_topic_favoriters'] )
						bbp_add_user_favorite( $favoriter, $destination_topic->ID );

					// Remove old favorite
					bbp_remove_user_favorite( $favoriter, $source_topic->ID );
				}
			}

			/** Tags **********************************************************/

			// Get the source topic tags
			$source_topic_tags = wp_get_post_terms( $source_topic->ID, $bbp->topic_tag_id, array( 'fields' => 'names' ) );

			// Tags to possibly merge
			if ( !empty( $source_topic_tags ) && !is_wp_error( $source_topic_tags ) ) {

				// Shift the tags if told to
				if ( !empty( $_POST['bbp_topic_tags'] ) && ( 1 == $_POST['bbp_topic_tags'] ) )
					wp_set_post_terms( $destination_topic->ID, $source_topic_tags, $bbp->topic_tag_id, true );

				// Delete the tags from the source topic
				wp_delete_object_term_relationships( $source_topic->ID, $bbp->topic_tag_id );
			}

			/** Source Topic **************************************************/

			// Status
			bbp_open_topic( $source_topic->ID );

			// Sticky
			bbp_unstick_topic( $source_topic->ID );

			// Get the replies of the source topic
			$replies = (array) get_posts( array(
				'post_parent'    => $source_topic->ID,
				'post_type'      => bbp_get_reply_post_type(),
				'posts_per_page' => -1,
				'order'          => 'ASC'
			) );

			// Prepend the source topic to its replies array for processing
			array_unshift( $replies, $source_topic );

			if ( !empty( $replies ) ) {

				/** Merge Replies *************************************************/

				// Change the post_parent of each reply to the destination topic id
				foreach ( $replies as $reply ) {
					$postarr = array(
						'ID'          => $reply->ID,
						'post_title'  => sprintf( __( 'Reply To: %s', 'bbpress' ), $destination_topic->post_title ),
						'post_name'   => false,
						'post_type'   => bbp_get_reply_post_type(),
						'post_parent' => $destination_topic->ID,
						'guid'        => ''
					);

					wp_update_post( $postarr );

					// Adjust reply meta values
					bbp_update_reply_topic_id( $reply->ID, $destination_topic->ID                           );
					bbp_update_reply_forum_id( $reply->ID, bbp_get_topic_forum_id( $destination_topic->ID ) );

					// Do additional actions per merged reply
					do_action( 'bbp_merged_topic_reply', $reply->ID, $destination_topic->ID );
				}
			}

			/** Successful Merge *******************************************/

			// Send the post parent of the source topic as it has been shifted
			// (possibly to a new forum) so we need to update the counts of the
			// old forum as well as the new one
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
 * @uses bbp_update_topic_reply_count() To update the topic reply counts
 * @uses bbp_update_topic_voice_count() To update the topic voice counts
 * @uses bbp_update_topic_hidden_reply_count() To update the topic hidden reply
 *                                              count
 * @uses do_action() Calls 'bbp_merge_topic_count' with the destination topic
 *                    id, source topic id & source topic forum id
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
 * @uses bbp_get_reply() To get the reply
 * @uses bbp_get_topic() To get the topics
 * @uses check_admin_referer() To verify the nonce and check the referer
 * @uses current_user_can() To check if the current user can edit the topics
 * @uses bbp_get_topic_post_type() To get the topic post type
 * @uses is_wp_error() To check if the value retrieved is a {@link WP_Error}
 * @uses do_action() Calls 'bbp_pre_split_topic' with the from reply id, source
 *                    and destination topic ids
 * @uses bbp_get_topic_subscribers() To get the source topic subscribers
 * @uses bbp_add_user_subscription() To add the user subscription
 * @uses bbp_get_topic_favoriters() To get the source topic favoriters
 * @uses bbp_add_user_favorite() To add the user favorite
 * @uses wp_get_post_terms() To get the source topic tags
 * @uses wp_set_post_terms() To set the topic tags
 * @uses bbp_get_reply_post_type() To get the reply post type
 * @uses wpdb::prepare() To prepare our sql query
 * @uses wpdb::get_results() To execute the sql query and get results
 * @uses wp_update_post() To update the replies
 * @uses bbp_update_reply_topic_id() To update the reply topic id
 * @uses bbp_get_topic_forum_id() To get the topic forum id
 * @uses bbp_update_reply_forum_id() To update the reply forum id
 * @uses do_action() Calls 'bbp_split_topic_reply' with the reply id and
 *                    destination topic id
 * @uses bbp_update_topic_last_reply_id() To update the topic last reply id
 * @uses bbp_update_topic_last_active_time() To update the topic last active meta
 * @uses do_action() Calls 'bbp_post_split_topic' with the destination and
 *                    source topic ids and source topic's forum id
 * @uses bbp_get_topic_permalink() To get the topic permalink
 * @uses wp_redirect() To redirect to the topic link
 */
function bbp_split_topic_handler() {

	// Only proceed if POST is an split topic request
	if ( ( 'POST' == strtoupper( $_SERVER['REQUEST_METHOD'] ) ) && !empty( $_POST['action'] ) && ( 'bbp-split-topic' === $_POST['action'] ) ) {
		global $wpdb, $bbp;

		// Prevent debug notices
		$from_reply_id = $destination_topic_id = 0;
		$destination_topic_title = '';
		$destination_topic = $from_reply = $source_topic = '';
		$split_option = false;

		/** Split Reply *******************************************************/

		if ( empty( $_POST['bbp_reply_id'] ) )
			$bbp->errors->add( 'bbp_split_topic_reply_id', __( '<strong>ERROR</strong>: Reply ID to split the topic from not found!', 'bbpress' ) );
		else
			$from_reply_id = (int) $_POST['bbp_reply_id'];

		$from_reply = bbp_get_reply( $from_reply_id );

		// Reply exists
		if ( empty( $from_reply ) )
			$bbp->errors->add( 'bbp_split_topic_r_not_found', __( '<strong>ERROR</strong>: The reply you want to split from was not found.', 'bbpress' ) );

		/** Topic to Split ****************************************************/

		// Get the topic being split
		$source_topic = bbp_get_topic( $from_reply->post_parent );

		// No topic
		if ( empty( $source_topic ) )
			$bbp->errors->add( 'bbp_split_topic_source_not_found', __( '<strong>ERROR</strong>: The topic you want to split was not found.', 'bbpress' ) );

		// Nonce check
		check_admin_referer( 'bbp-split-topic_' . $source_topic->ID );

		// Use cannot edit topic
		if ( !current_user_can( 'edit_topic', $source_topic->ID ) )
			$bbp->errors->add( 'bbp_split_topic_source_permission', __( '<strong>ERROR</strong>: You do not have the permissions to edit the source topic.', 'bbpress' ) );

		/** How to Split ******************************************************/

		if ( !empty( $_POST['bbp_topic_split_option'] ) )
			$split_option = (string) trim( $_POST['bbp_topic_split_option'] );

		// Invalid split option
		if ( empty( $split_option ) || !in_array( $split_option, array( 'existing', 'reply' ) ) ) {
			$bbp->errors->add( 'bbp_split_topic_option', __( '<strong>ERROR</strong>: You need to choose a valid split option.', 'bbpress' ) );

		// Valid Split Option
		} else {

			// What kind of split
			switch ( $split_option ) {

				// Into an existing topic
				case 'existing' :

					// Get destination topic id
					if ( empty( $_POST['bbp_destination_topic'] ) )
						$bbp->errors->add( 'bbp_split_topic_destination_id', __( '<strong>ERROR</strong>: Destination topic ID not found!', 'bbpress' ) );
					else
						$destination_topic_id = (int) $_POST['bbp_destination_topic'];

					// Get the destination topic
					$destination_topic = bbp_get_topic( $destination_topic_id );

					// No destination topic
					if ( empty( $destination_topic ) )
						$bbp->errors->add( 'bbp_split_topic_destination_not_found', __( '<strong>ERROR</strong>: The topic you want to split to was not found!', 'bbpress' ) );

					// User cannot edit the destination topic
					if ( !current_user_can( 'edit_topic', $destination_topic->ID ) )
						$bbp->errors->add( 'bbp_split_topic_destination_permission', __( '<strong>ERROR</strong>: You do not have the permissions to edit the destination topic!', 'bbpress' ) );

					break;

				// Split at reply into a new topic
				case 'reply' :
				default :

					// User needs to be able to publish topics
					if ( current_user_can( 'publish_topics' ) ) {

						// Use the new title that was passed
						if ( !empty( $_POST['bbp_topic_split_destination_title'] ) )
							$destination_topic_title = esc_attr( strip_tags( $_POST['bbp_topic_split_destination_title'] ) );

						// Use the source topic title
						else
							$destination_topic_title = $source_topic->post_title;

						// Setup the updated topic parameters
						$postarr = array(
							'ID'          => $from_reply->ID,
							'post_title'  => $destination_topic_title,
							'post_name'   => false,
							'post_type'   => bbp_get_topic_post_type(),
							'post_parent' => $source_topic->post_parent,
							'guid'        => ''
						);

						// Update the topic
						$destination_topic_id = wp_update_post( $postarr );

						// Make sure the new topic knows its a topic
						bbp_update_topic_topic_id( $from_reply->ID );

						// Shouldn't happen
						if ( false == $destination_topic_id || is_wp_error( $destination_topic_id ) || !$destination_topic = bbp_get_topic( $destination_topic_id ) )
							$bbp->errors->add( 'bbp_split_topic_destination_reply', __( '<strong>ERROR</strong>: There was a problem converting the reply into the topic. Please try again.', 'bbpress' ) );

					// User cannot publish posts
					} else {
						$bbp->errors->add( 'bbp_split_topic_destination_permission', __( '<strong>ERROR</strong>: You do not have the permissions to create new topics. The reply could not be converted into a topic.', 'bbpress' ) );
					}

					break;
			}
		}

		/** No Errors - Do the Spit *******************************************/

		if ( !is_wp_error( $bbp->errors ) || !$bbp->errors->get_error_codes() ) {

			// Update counts, etc...
			do_action( 'bbp_pre_split_topic', $from_reply->ID, $source_topic->ID, $destination_topic->ID );

			/** Subscriptions *************************************************/

			// Copy the subscribers
			if ( !empty( $_POST['bbp_topic_subscribers'] ) && 1 == $_POST['bbp_topic_subscribers'] && bbp_is_subscriptions_active() ) {

				// Get the subscribers
				if ( $subscribers = bbp_get_topic_subscribers( $source_topic->ID ) ) {

					// Add subscribers to new topic
					foreach ( (array) $subscribers as $subscriber ) {
						bbp_add_user_subscription( $subscriber, $destination_topic->ID );
					}
				}
			}

			/** Favorites *****************************************************/

			// Copy the favoriters if told to
			if ( !empty( $_POST['bbp_topic_favoriters'] ) && 1 == $_POST['bbp_topic_favoriters'] ) {

				// Get the favoriters
				if ( $favoriters = bbp_get_topic_favoriters( $source_topic->ID ) ) {

					// Add the favoriters to new topic
					foreach ( (array) $favoriters as $favoriter ) {
						bbp_add_user_favorite( $favoriter, $destination_topic->ID );
					}
				}
			}

			/** Tags **********************************************************/

			// Copy the tags if told to
			if ( !empty( $_POST['bbp_topic_tags'] ) && 1 == $_POST['bbp_topic_tags'] ) {

				// Get the source topic tags
				if ( $source_topic_tags = wp_get_post_terms( $source_topic->ID, $bbp->topic_tag_id, array( 'fields' => 'names' ) ) ) {
					wp_set_post_terms( $destination_topic->ID, $source_topic_tags, $bbp->topic_tag_id, true );
				}
			}

			/** Split Replies *************************************************/

			// get_posts() is not used because it doesn't allow us to use '>='
			// comparision without a filter.
			$replies = (array) $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->posts} WHERE {$wpdb->posts}.post_date >= %s AND {$wpdb->posts}.post_parent = %d AND {$wpdb->posts}.post_type = %s ORDER BY {$wpdb->posts}.post_date ASC", $from_reply->post_date, $source_topic->ID, bbp_get_reply_post_type() ) );

			// Make sure there are replies to loop through
			if ( !empty( $replies ) && !is_wp_error( $replies ) ) {

				// Change the post_parent of each reply to the destination topic id
				foreach ( $replies as $reply ) {

					// New reply data
					$postarr = array(
						'ID'          => $reply->ID,
						'post_title'  => sprintf( __( 'Reply To: %s', 'bbpress' ), $destination_topic->post_title ),
						'post_name'   => false, // will be automatically generated
						'post_parent' => $destination_topic->ID,
						'guid'        => ''
					);

					// Update the reply
					wp_update_post( $postarr );

					// Adjust reply meta values
					bbp_update_reply_topic_id( $reply->ID, $destination_topic->ID                           );
					bbp_update_reply_forum_id( $reply->ID, bbp_get_topic_forum_id( $destination_topic->ID ) );

					// Do additional actions per split reply
					do_action( 'bbp_split_topic_reply', $reply->ID, $destination_topic->ID );
				}
			}

			// It is a new topic and we need to set some default metas to make
			// the topic display in bbp_has_topics() list
			if ( 'reply' == $split_option ) {
				$last_reply_id = ( empty( $reply ) || empty( $reply->ID        ) ) ? 0  : $reply->ID;
				$freshness     = ( empty( $reply ) || empty( $reply->post_date ) ) ? '' : $reply->post_date;

				bbp_update_topic_last_reply_id   ( $destination_topic->ID, $last_reply_id );
				bbp_update_topic_last_active_time( $destination_topic->ID, $freshness     );
			}

			/** Successful Split **********************************************/

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
 * @uses bbp_update_topic_reply_count() To update the topic reply counts
 * @uses bbp_update_topic_voice_count() To update the topic voice counts
 * @uses bbp_update_topic_hidden_reply_count() To update the topic hidden reply
 *                                              count
 * @uses do_action() Calls 'bbp_split_topic_count' with the from reply id,
 *                    source topic id & destination topic id
 */
function bbp_split_topic_count( $from_reply_id, $source_topic_id, $destination_topic_id ) {

	// Forum Topic Counts
	bbp_update_forum_topic_count( $destination_topic_id );

	// Forum Reply Counts
	bbp_update_forum_reply_count( $destination_topic_id );

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

	// Are we managing a tag?
	if ( ( 'POST' == strtoupper( $_SERVER['REQUEST_METHOD'] ) ) && !empty( $_POST['tag-id'] ) && !empty( $_POST['action'] ) && in_array( $_POST['action'], array( 'bbp-update-topic-tag', 'bbp-merge-topic-tag', 'bbp-delete-topic-tag' ) ) ) {

		global $bbp;

		// Setup vars
		$action = $_POST['action'];
		$tag_id = (int) $_POST['tag-id'];
		$tag    = get_term( $tag_id, $bbp->topic_tag_id );

		// Tag does not exist
		if ( is_wp_error( $tag ) && $tag->get_error_message() ) {
			$bbp->errors->add( 'bbp_manage_topic_invalid_tag', sprintf( __( '<strong>ERROR</strong>: The following problem(s) have been found while getting the tag: %s', 'bbpress' ), $tag->get_error_message() ) );
			return;
		}

		// What action are we trying to perform?
		switch ( $action ) {

			// Update tag
			case 'bbp-update-topic-tag' :

				// Nonce check
				check_admin_referer( 'update-tag_' . $tag_id );

				// Can user edit topic tags?
				if ( !current_user_can( 'edit_topic_tags' ) ) {
					$bbp->errors->add( 'bbp_manage_topic_tag_update_permissions', __( '<strong>ERROR</strong>: You do not have the permissions to edit the topic tags.', 'bbpress' ) );
					return;
				}

				// No tag name was provided
				if ( empty( $_POST['tag-name'] ) || !$name = $_POST['tag-name'] ) {
					$bbp->errors->add( 'bbp_manage_topic_tag_update_name', __( '<strong>ERROR</strong>: You need to enter a tag name.', 'bbpress' ) );
					return;
				}

				// Attempt to update the tag
				$slug = !empty( $_POST['tag-slug'] ) ? $_POST['tag-slug'] : '';
				$tag  = wp_update_term( $tag_id, $bbp->topic_tag_id, array( 'name' => $name, 'slug' => $slug ) );

				// Cannot update tag
				if ( is_wp_error( $tag ) && $tag->get_error_message() ) {
					$bbp->errors->add( 'bbp_manage_topic_tag_update_error', sprintf( __( '<strong>ERROR</strong>: The following problem(s) have been found while updating the tag: %s', 'bbpress' ), $tag->get_error_message() ) );
					return;
				}

				// Redirect
				$redirect = get_term_link( $tag_id, $bbp->topic_tag_id );

				// Update counts, etc...
				do_action( 'bbp_update_topic_tag', $tag_id, $tag, $name, $slug );

				break;

			// Merge two tags
			case 'bbp-merge-topic-tag'  :

				// Nonce check
				check_admin_referer( 'merge-tag_' . $tag_id );

				// Can user edit topic tags?
				if ( !current_user_can( 'edit_topic_tags' ) ) {
					$bbp->errors->add( 'bbp_manage_topic_tag_merge_permissions', __( '<strong>ERROR</strong>: You do not have the permissions to edit the topic tags.', 'bbpress' ) );
					return;
				}

				// No tag name was provided
				if ( empty( $_POST['tag-name'] ) || !$name = $_POST['tag-name'] ) {
					$bbp->errors->add( 'bbp_manage_topic_tag_merge_name', __( '<strong>ERROR</strong>: You need to enter a tag name.', 'bbpress' ) );
					return;
				}

				// If term does not exist, create it
				if ( !$tag = term_exists( $name, $bbp->topic_tag_id ) )
					$tag = wp_insert_term( $name, $bbp->topic_tag_id );

				// Problem inserting the new term
				if ( is_wp_error( $tag ) && $tag->get_error_message() ) {
					$bbp->errors->add( 'bbp_manage_topic_tag_merge_error', sprintf( __( '<strong>ERROR</strong>: The following problem(s) have been found while merging the tags: %s', 'bbpress' ), $tag->get_error_message() ) );
					return;
				}

				// Merging in to...
				$to_tag = $tag['term_id'];

				// Attempting to merge a tag into itself
				if ( $tag_id == $to_tag ) {
					$bbp->errors->add( 'bbp_manage_topic_tag_merge_same', __( '<strong>ERROR</strong>: The tags which are being merged can not be the same.', 'bbpress' ) );
					return;
				}

				// Delete the old term
				$tag = wp_delete_term( $tag_id, $bbp->topic_tag_id, array( 'default' => $to_tag, 'force_default' => true ) );

				// Error merging the terms
				if ( is_wp_error( $tag ) && $tag->get_error_message() ) {
					$bbp->errors->add( 'bbp_manage_topic_tag_merge_error', sprintf( __( '<strong>ERROR</strong>: The following problem(s) have been found while merging the tags: %s', 'bbpress' ), $tag->get_error_message() ) );
					return;
				}

				// Redirect
				$redirect = get_term_link( (int) $to_tag, $bbp->topic_tag_id );

				// Update counts, etc...
				do_action( 'bbp_merge_topic_tag', $tag_id, $to_tag, $tag );

				break;

			// Delete tag
			case 'bbp-delete-topic-tag' :

				// Nonce check
				check_admin_referer( 'delete-tag_' . $tag_id );

				// Can user delete topic tags?
				if ( !current_user_can( 'delete_topic_tags' ) ) {
					$bbp->errors->add( 'bbp_manage_topic_tag_delete_permissions', __( '<strong>ERROR</strong>: You do not have the permissions to delete the topic tags.', 'bbpress' ) );
					return;
				}

				// Attempt to delete term
				$tag = wp_delete_term( $tag_id, $bbp->topic_tag_id );

				// Error deleting term
				if ( is_wp_error( $tag ) && $tag->get_error_message() ) {
					$bbp->errors->add( 'bbp_manage_topic_tag_delete_error', sprintf( __( '<strong>ERROR</strong>: The following problem(s) have been found while deleting the tag: %s', 'bbpress' ), $tag->get_error_message() ) );
					return;
				}

				// We don't have any other place to go other than home! Or we may die because of the 404 disease
				$redirect = home_url();

				// Update counts, etc...
				do_action( 'bbp_delete_topic_tag', $tag_id, $tag );

				break;
		}

		/** Successful Moderation *********************************************/

		// Redirect back
		$redirect = ( !empty( $redirect ) && !is_wp_error( $redirect ) ) ? $redirect : home_url();
		wp_redirect( $redirect );

		// For good measure
		exit();
	}
}

/** Stickies ******************************************************************/

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

/** Topics Actions ************************************************************/

/**
 * Handles the front end opening/closing, spamming/unspamming,
 * sticking/unsticking and trashing/untrashing/deleting of topics
 *
 * @since bbPress (r2727)
 *
 * @uses bbp_get_topic() To get the topic
 * @uses current_user_can() To check if the user is capable of editing or
 *                           deleting the topic
 * @uses bbp_get_topic_post_type() To get the topic post type
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
 * @uses bbp_get_forum_permalink() To get the forum link
 * @uses bbp_get_topic_permalink() To get the topic link
 * @uses add_query_arg() To add args to the url
 * @uses wp_redirect() To redirect to the topic
 * @uses bbPress::errors:add() To log the error messages
 */
function bbp_toggle_topic_handler() {

	// Only proceed if GET is a topic toggle action
	if ( ( 'GET' == strtoupper( $_SERVER['REQUEST_METHOD'] ) ) && !empty( $_GET['topic_id'] ) && !empty( $_GET['action'] ) && in_array( $_GET['action'], array( 'bbp_toggle_topic_close', 'bbp_toggle_topic_stick', 'bbp_toggle_topic_spam', 'bbp_toggle_topic_trash' ) ) ) {
		global $bbp;

		$action    = $_GET['action'];            // What action is taking place?
		$topic_id  = (int) $_GET['topic_id'];    // What's the topic id?
		$success   = false;                      // Flag
		$post_data = array( 'ID' => $topic_id ); // Prelim array

		// Make sure topic exists
		if ( !$topic = bbp_get_topic( $topic_id ) )
			return;

		// What is the user doing here?
		if ( !current_user_can( 'edit_topic', $topic->ID ) || ( 'bbp_toggle_topic_trash' == $action && !current_user_can( 'delete_topic', $topic->ID ) ) ) {
			$bbp->errors->add( 'bbp_toggle_topic_permission', __( '<strong>ERROR:</strong> You do not have the permission to do that.', 'bbpress' ) );
			return;
		}

		// What action are we trying to perform?
		switch ( $action ) {

			// Toggle open/close
			case 'bbp_toggle_topic_close' :
				check_ajax_referer( 'close-topic_' . $topic_id );

				$is_open = bbp_is_topic_open( $topic_id );
				$success = $is_open ? bbp_close_topic( $topic_id ) : bbp_open_topic( $topic_id );
				$failure = $is_open ? __( '<strong>ERROR</strong>: There was a problem closing the topic.', 'bbpress' ) : __( '<strong>ERROR</strong>: There was a problem opening the topic.', 'bbpress' );

				break;

			// Toggle sticky/super-sticky/unstick
			case 'bbp_toggle_topic_stick' :
				check_ajax_referer( 'stick-topic_' . $topic_id );

				$is_sticky = bbp_is_topic_sticky( $topic_id );
				$is_super  = ( empty( $is_sticky ) && !empty( $_GET['super'] ) && 1 == (int) $_GET['super'] ) ? true : false;
				$success   = $is_sticky ? bbp_unstick_topic( $topic_id ) : bbp_stick_topic( $topic_id, $is_super );
				$failure   = $is_sticky ? __( '<strong>ERROR</strong>: There was a problem unsticking the topic.', 'bbpress' ) : __( '<strong>ERROR</strong>: There was a problem sticking the topic.', 'bbpress' );

				break;

			// Toggle spam
			case 'bbp_toggle_topic_spam' :
				check_ajax_referer( 'spam-topic_' . $topic_id );

				$is_spam = bbp_is_topic_spam( $topic_id );
				$success = $is_spam ? bbp_unspam_topic( $topic_id ) : bbp_spam_topic( $topic_id );
				$failure = $is_spam ? __( '<strong>ERROR</strong>: There was a problem unmarking the topic as spam.', 'bbpress' ) : __( '<strong>ERROR</strong>: There was a problem marking the topic as spam.', 'bbpress' );

				break;

			// Toggle trash
			case 'bbp_toggle_topic_trash' :

				$sub_action = in_array( $_GET['sub_action'], array( 'trash', 'untrash', 'delete' ) ) ? $_GET['sub_action'] : false;

				if ( empty( $sub_action ) )
					break;

				switch ( $sub_action ) {
					case 'trash':
						check_ajax_referer( 'trash-' . bbp_get_topic_post_type() . '_' . $topic_id );

						$success = wp_trash_post( $topic_id );
						$failure = __( '<strong>ERROR</strong>: There was a problem trashing the topic.', 'bbpress' );

						break;

					case 'untrash':
						check_ajax_referer( 'untrash-' . bbp_get_topic_post_type() . '_' . $topic_id );

						$success = wp_untrash_post( $topic_id );
						$failure = __( '<strong>ERROR</strong>: There was a problem untrashing the topic.', 'bbpress' );

						break;

					case 'delete':
						check_ajax_referer( 'delete-' . bbp_get_topic_post_type() . '_' . $topic_id );

						$success = wp_delete_post( $topic_id );
						$failure = __( '<strong>ERROR</strong>: There was a problem deleting the topic.', 'bbpress' );

						break;
				}

				break;
		}

		// Do additional topic toggle actions
		do_action( 'bbp_toggle_topic_handler', $success, $post_data, $action );

		// No Errors
		if ( false != $success && !is_wp_error( $success ) ) {

			// Redirect back to the topic's forum
			if ( isset( $sub_action ) && 'delete' == $sub_action )
				$redirect = bbp_get_forum_permalink( $success->post_parent );

			// Redirect back to the topic
			else
				$redirect = add_query_arg( array( 'view' => 'all' ), bbp_get_topic_permalink( $topic_id ) );

			wp_redirect( $redirect );

			// For good measure
			exit();

		// Handle errors
		} else {
			$bbp->errors->add( 'bbp_toggle_topic', $failure );
		}
	}
}

/** Favorites & Subscriptions *************************************************/

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
	$topic_id = bbp_get_topic_id( $topic_id );

	// Bail if no topic
	if ( empty( $topic_id ) )
		return;

	// Get users
	$users = (array) bbp_get_topic_favoriters( $topic_id );

	// Users exist
	if ( !empty( $users ) ) {

		// Loop through users
		foreach ( $users as $user ) {

			// Remove each user
			bbp_remove_user_favorite( $user, $topic_id );
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

	// Subscriptions are not active
	if ( !bbp_is_subscriptions_active() )
		return;

	$topic_id = bbp_get_topic_id( $topic_id );

	// Bail if no topic
	if ( empty( $topic_id ) )
		return;

	// Get users
	$users = (array) bbp_get_topic_subscribers( $topic_id );

	// Users exist
	if ( !empty( $users ) ) {

		// Loop through users
		foreach ( $users as $user ) {

			// Remove each user
			bbp_remove_user_subscription( $user, $topic_id );
		}
	}
}

/** Topic Updaters ************************************************************/

/**
 * Update the topic's forum id
 *
 * @since bbPress (r2855)
 *
 * @param int $topic_id Optional. Topic id to update
 * @param int $forum_id Optional. Forum id
 * @uses bbp_is_reply() TO check if the passed topic id is a reply
 * @uses bbp_get_reply_topic_id() To get the reply topic id
 * @uses bbp_get_topic_id() To get the topic id
 * @uses get_post_field() To get the post parent of the topic id
 * @uses bbp_get_forum_id() To get the forum id
 * @uses update_post_meta() To update the topic forum id meta
 * @uses apply_filters() Calls 'bbp_update_topic_forum_id' with the forum id
 *                        and topic id
 * @return int Forum id
 */
function bbp_update_topic_forum_id( $topic_id = 0, $forum_id = 0 ) {

	// If it's a reply, then get the parent (topic id)
	if ( bbp_is_reply( $topic_id ) )
		$topic_id = bbp_get_reply_topic_id( $topic_id );
	else
		$topic_id = bbp_get_topic_id( $topic_id );

	if ( empty( $forum_id ) )
		$forum_id = get_post_field( 'post_parent', $topic_id );

	update_post_meta( $topic_id, '_bbp_forum_id', (int) $forum_id );

	return apply_filters( 'bbp_update_topic_forum_id', (int) $forum_id, $topic_id );
}

/**
 * Update the topic's topic id
 *
 * @since bbPress (r2954)
 *
 * @param int $topic_id Optional. Topic id to update
 * @uses bbp_get_topic_id() To get the topic id
 * @uses update_post_meta() To update the topic's topic id meta
 * @uses apply_filters() Calls 'bbp_update_topic_topic_id' with the topic id
 * @return int Topic id
 */
function bbp_update_topic_topic_id( $topic_id = 0 ) {
	$topic_id = bbp_get_topic_id( $topic_id );

	update_post_meta( $topic_id, '_bbp_topic_id', (int) $topic_id );

	return apply_filters( 'bbp_update_topic_topic_id', (int) $topic_id );
}

/**
 * Adjust the total reply count of a topic
 *
 * @since bbPress (r2467)
 *
 * @param int $topic_id Optional. Topic id to update
 * @param int $reply_count Optional. Set the reply count manually.
 * @uses bbp_is_reply() To check if the passed topic id is a reply
 * @uses bbp_get_reply_topic_id() To get the reply topic id
 * @uses bbp_get_topic_id() To get the topic id
 * @uses bbp_get_reply_post_type() To get the reply post type
 * @uses bbp_get_public_child_count() To get the reply count
 * @uses update_post_meta() To update the topic reply count meta
 * @uses apply_filters() Calls 'bbp_update_topic_reply_count' with the reply
 *                        count and topic id
 * @return int Topic reply count
 */
function bbp_update_topic_reply_count( $topic_id = 0, $reply_count = 0 ) {

	// If it's a reply, then get the parent (topic id)
	if ( bbp_is_reply( $topic_id ) )
		$topic_id = bbp_get_reply_topic_id( $reply_id );
	else
		$topic_id = bbp_get_topic_id( $topic_id );

	// Get replies of topic if not passed
	if ( empty( $reply_count ) )
		$reply_count = bbp_get_public_child_count( $topic_id, bbp_get_reply_post_type() );

	update_post_meta( $topic_id, '_bbp_reply_count', (int) $reply_count );

	return apply_filters( 'bbp_update_topic_reply_count', (int) $reply_count, $topic_id );
}

/**
 * Adjust the total hidden reply count of a topic (hidden includes trashed and spammed replies)
 *
 * @since bbPress (r2740)
 *
 * @param int $topic_id Optional. Topic id to update
 * @param int $reply_count Optional. Set the reply count manually
 * @uses bbp_is_reply() To check if the passed topic id is a reply
 * @uses bbp_get_reply_topic_id() To get the reply topic id
 * @uses bbp_get_topic_id() To get the topic id
 * @uses bbp_get_reply_post_type() To get the reply post type
 * @uses wpdb::prepare() To prepare our sql query
 * @uses wpdb::get_var() To execute our query and get the var back
 * @uses update_post_meta() To update the topic hidden reply count meta
 * @uses apply_filters() Calls 'bbp_update_topic_hidden_reply_count' with the
 *                        hidden reply count and topic id
 * @return int Topic hidden reply count
 */
function bbp_update_topic_hidden_reply_count( $topic_id = 0, $reply_count = 0 ) {
	global $wpdb, $bbp;

	// If it's a reply, then get the parent (topic id)
	if ( bbp_is_reply( $topic_id ) )
		$topic_id = bbp_get_reply_topic_id( $topic_id );
	else
		$topic_id = bbp_get_topic_id( $topic_id );

	// Get replies of topic
	if ( empty( $reply_count ) )
		$reply_count = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(ID) FROM {$wpdb->posts} WHERE post_parent = %d AND post_status IN ( '" . join( '\',\'', array( $bbp->trash_status_id, $bbp->spam_status_id ) ) . "') AND post_type = '%s';", $topic_id, bbp_get_reply_post_type() ) );

	update_post_meta( $topic_id, '_bbp_hidden_reply_count', (int) $reply_count );

	return apply_filters( 'bbp_update_topic_hidden_reply_count', (int) $reply_count, $topic_id );
}

/**
 * Update the topic with the last active post ID
 *
 * @since bbPress (r2888)
 *
 * @param int $topic_id Optional. Topic id to update
 * @param int $active_id Optional. active id
 * @uses bbp_is_reply() To check if the passed topic id is a reply
 * @uses bbp_get_reply_topic_id() To get the reply topic id
 * @uses bbp_get_topic_id() To get the topic id
 * @uses bbp_get_reply_post_type() To get the reply post type
 * @uses bbp_get_public_child_last_id() To get the last public reply id
 * @uses bbp_get_active_id() To get the active id
 * @uses update_post_meta() To update the topic last active id meta
 * @uses apply_filters() Calls 'bbp_update_topic_last_active_id' with the active
 *                        id and topic id
 * @return int Active id
 */
function bbp_update_topic_last_active_id( $topic_id = 0, $active_id = 0 ) {

	// If it's a reply, then get the parent (topic id)
	if ( bbp_is_reply( $topic_id ) )
		$topic_id = bbp_get_reply_topic_id( $topic_id );
	else
		$topic_id = bbp_get_topic_id( $topic_id );

	if ( empty( $active_id ) )
		$active_id = bbp_get_public_child_last_id( $topic_id, bbp_get_reply_post_type() );

	// Adjust last_id's based on last_reply post_type
	if ( empty( $active_id ) || !bbp_is_reply( $active_id ) )
		$active_id = $topic_id;

	update_post_meta( $topic_id, '_bbp_last_active_id', (int) $active_id );

	return apply_filters( 'bbp_update_topic_last_active_id', (int) $active_id, $topic_id );
}

/**
 * Update the topics last active date/time (aka freshness)
 *
 * @since bbPress (r2680)
 *
 * @param int $topic_id Optional. Topic id
 * @param string $new_time Optional. New time in mysql format
 * @uses bbp_get_topic_id() To get the topic id
 * @uses bbp_get_reply_topic_id() To get the reply topic id
 * @uses current_time() To get the current time
 * @uses update_post_meta() To update the topic last active meta
 * @return bool True on success, false on failure
 */
function bbp_update_topic_last_active_time( $topic_id = 0, $new_time = '' ) {

	// If it's a reply, then get the parent (topic id)
	if ( bbp_is_reply( $topic_id ) )
		$topic_id = bbp_get_reply_topic_id( $reply_id );
	else
		$topic_id = bbp_get_topic_id( $topic_id );

	// Check time and use current if empty
	if ( empty( $new_time ) )
		$new_time = get_post_field( 'post_date', bbp_get_public_child_last_id( $topic_id, bbp_get_reply_post_type() ) );

	update_post_meta( $topic_id, '_bbp_last_active_time', $new_time );

	return apply_filters( 'bbp_update_topic_last_active_time', $new_time, $topic_id );
}

/**
 * Update the topic with the most recent reply ID
 *
 * @since bbPress (r2625)
 *
 * @param int $topic_id Optional. Topic id to update
 * @param int $reply_id Optional. Reply id
 * @uses bbp_is_reply() To check if the passed topic id is a reply
 * @uses bbp_get_reply_id() To get the reply id
 * @uses bbp_get_reply_topic_id() To get the reply topic id
 * @uses bbp_get_topic_id() To get the topic id
 * @uses bbp_get_reply_post_type() To get the reply post type
 * @uses bbp_get_public_child_last_id() To get the last public reply id
 * @uses update_post_meta() To update the topic last reply id meta
 * @uses apply_filters() Calls 'bbp_update_topic_last_reply_id' with the reply
 *                        id and topic id
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

	if ( empty( $reply_id ) )
		$reply_id = bbp_get_public_child_last_id( $topic_id, bbp_get_reply_post_type() );

	// Adjust last_id's based on last_reply post_type
	if ( empty( $reply_id ) || !bbp_is_reply( $reply_id ) )
		$reply_id = 0;

	update_post_meta( $topic_id, '_bbp_last_reply_id', (int) $reply_id );

	return apply_filters( 'bbp_update_topic_last_reply_id', (int) $reply_id, $topic_id );
}

/**
 * Adjust the total voice count of a topic
 *
 * @since bbPress (r2567)
 *
 * @param int $topic_id Optional. Topic id to update
 * @uses bbp_is_reply() To check if the passed topic id is a reply
 * @uses bbp_get_reply_topic_id() To get the reply topic id
 * @uses bbp_get_topic_id() To get the topic id
 * @uses bbp_get_reply_topic_id() To get the reply topic id
 * @uses bbp_get_reply_post_type() To get the reply post type
 * @uses bbp_get_topic_post_type() To get the topic post type
 * @uses wpdb::prepare() To prepare our sql query
 * @uses wpdb::get_col() To execute our query and get the column back
 * @uses update_post_meta() To update the topic voice count meta
 * @uses apply_filters() Calls 'bbp_update_topic_voice_count' with the voice
 *                        count and topic id
 * @return int Voice count
 */
function bbp_update_topic_voice_count( $topic_id = 0 ) {
	global $wpdb;

	// If it's a reply, then get the parent (topic id)
	if ( bbp_is_reply( $topic_id ) )
		$topic_id = bbp_get_reply_topic_id( $topic_id );
	elseif ( bbp_is_topic( $topic_id ) )
		$topic_id = bbp_get_topic_id( $topic_id );
	else
		return;

	// Query the DB to get voices in this topic
	$voices = $wpdb->get_col( $wpdb->prepare( "SELECT COUNT( DISTINCT post_author ) FROM {$wpdb->posts} WHERE ( post_parent = %d AND post_status = 'publish' AND post_type = '%s' ) OR ( ID = %d AND post_type = '%s' );", $topic_id, bbp_get_reply_post_type(), $topic_id, bbp_get_topic_post_type() ) );

	// If there's an error, make sure we at least have 1 voice
	$voices = ( empty( $voices ) || is_wp_error( $voices ) ) ? 1 : $voices[0];

	// Update the voice count for this topic id
	update_post_meta( $topic_id, '_bbp_voice_count', (int) $voices );

	return apply_filters( 'bbp_update_topic_voice_count', (int) $voices, $topic_id );
}

/**
 * Adjust the total anonymous reply count of a topic
 *
 * @since bbPress (r2567)
 *
 * @param int $topic_id Optional. Topic id to update
 * @uses bbp_is_reply() To check if the passed topic id is a reply
 * @uses bbp_get_reply_topic_id() To get the reply topic id
 * @uses bbp_get_topic_id() To get the topic id
 * @uses bbp_get_reply_topic_id() To get the reply topic id
 * @uses bbp_get_reply_post_type() To get the reply post type
 * @uses bbp_get_topic_post_type() To get the topic post type
 * @uses wpdb::prepare() To prepare our sql query
 * @uses wpdb::get_col() To execute our query and get the column back
 * @uses update_post_meta() To update the topic anonymous reply count meta
 * @uses apply_filters() Calls 'bbp_update_topic_anonymous_reply_count' with the
 *                        anonymous reply count and topic id
 * @return int Anonymous reply count
 */
function bbp_update_topic_anonymous_reply_count( $topic_id = 0 ) {
	global $wpdb;

	// If it's a reply, then get the parent (topic id)
	if ( bbp_is_reply( $topic_id ) )
		$topic_id = bbp_get_reply_topic_id( $topic_id );
	elseif ( bbp_is_topic( $topic_id ) )
		$topic_id = bbp_get_topic_id( $topic_id );
	else
		return;

	$anonymous_replies = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT( ID ) FROM {$wpdb->posts} WHERE ( post_parent = %d AND post_status = 'publish' AND post_type = '%s' AND post_author = 0 ) OR ( ID = %d AND post_type = '%s' AND post_author = 0 );", $topic_id, bbp_get_reply_post_type(), $topic_id, bbp_get_topic_post_type() ) );

	update_post_meta( $topic_id, '_bbp_anonymous_reply_count', (int) $anonymous_replies );

	return apply_filters( 'bbp_update_topic_anonymous_reply_count', (int) $anonymous_replies, $topic_id );
}

/**
 * Update the revision log of the topic
 *
 * @since bbPress (r2782)
 *
 * @param mixed $args Supports these args:
 *  - topic_id: Topic id
 *  - author_id: Author id
 *  - reason: Reason for editing
 *  - revision_id: Revision id
 * @uses bbp_get_topic_id() To get the topic id
 * @uses bbp_get_user_id() To get the user id
 * @uses bbp_format_revision_reason() To format the reason
 * @uses bbp_get_topic_raw_revision_log() To get the raw topic revision log
 * @uses update_post_meta() To update the topic revision log meta
 * @return mixed False on failure, true on success
 */
function bbp_update_topic_revision_log( $args = '' ) {
	$defaults = array (
		'reason'      => '',
		'topic_id'    => 0,
		'author_id'   => 0,
		'revision_id' => 0
	);

	$r = wp_parse_args( $args, $defaults );
	extract( $r );

	// Populate the variables
	$reason      = bbp_format_revision_reason( $reason );
	$topic_id    = bbp_get_topic_id( $topic_id );
	$author_id   = bbp_get_user_id ( $author_id, false, true );
	$revision_id = (int) $revision_id;

	// Get the logs and append the new one to those
	$revision_log               = bbp_get_topic_raw_revision_log( $topic_id );
	$revision_log[$revision_id] = array( 'author' => $author_id, 'reason' => $reason );

	// Finally, update
	return update_post_meta( $topic_id, '_bbp_revision_log', $revision_log );
}

/** Topic Actions *************************************************************/

/**
 * Closes a topic
 *
 * @since bbPress (r2740)
 *
 * @param int $topic_id Topic id
 * @uses wp_get_single_post() To get the topic
 * @uses do_action() Calls 'bbp_close_topic' with the topic id
 * @uses add_post_meta() To add the previous status to a meta
 * @uses wp_insert_post() To update the topic with the new status
 * @uses do_action() Calls 'bbp_opened_topic' with the topic id
 * @return mixed False or {@link WP_Error} on failure, topic id on success
 */
function bbp_close_topic( $topic_id = 0 ) {
	global $bbp;

	if ( !$topic = wp_get_single_post( $topic_id, ARRAY_A ) )
		return $topic;

	if ( $topic['post_status'] == $bbp->closed_status_id )
		return false;

	do_action( 'bbp_close_topic', $topic_id );

	add_post_meta( $topic_id, '_bbp_status', $topic['post_status'] );

	$topic['post_status'] = $bbp->closed_status_id;

	$topic_id = wp_insert_post( $topic );

	do_action( 'bbp_closed_topic', $topic_id );

	return $topic_id;
}

/**
 * Opens a topic
 *
 * @since bbPress (r2740)
 *
 * @param int $topic_id Topic id
 * @uses wp_get_single_post() To get the topic
 * @uses do_action() Calls 'bbp_open_topic' with the topic id
 * @uses get_post_meta() To get the previous status
 * @uses delete_post_meta() To delete the previous status meta
 * @uses wp_insert_post() To update the topic with the new status
 * @uses do_action() Calls 'bbp_opened_topic' with the topic id
 * @return mixed False or {@link WP_Error} on failure, topic id on success
 */
function bbp_open_topic( $topic_id = 0 ) {
	global $bbp;

	if ( !$topic = wp_get_single_post( $topic_id, ARRAY_A ) )
		return $topic;

	if ( $topic['post_status'] != $bbp->closed_status_id )
		return false;

	do_action( 'bbp_open_topic', $topic_id );

	$topic_status         = get_post_meta( $topic_id, '_bbp_status', true );
	$topic['post_status'] = $topic_status;

	delete_post_meta( $topic_id, '_bbp_status' );

	$topic_id = wp_insert_post( $topic );

	do_action( 'bbp_opened_topic', $topic_id );

	return $topic_id;
}

/**
 * Marks a topic as spam
 *
 * @since bbPress (r2740)
 *
 * @param int $topic_id Topic id
 * @uses wp_get_single_post() To get the topic
 * @uses do_action() Calls 'bbp_spam_topic' with the topic id
 * @uses add_post_meta() To add the previous status to a meta
 * @uses wp_insert_post() To update the topic with the new status
 * @uses do_action() Calls 'bbp_spammed_topic' with the topic id
 * @return mixed False or {@link WP_Error} on failure, topic id on success
 */
function bbp_spam_topic( $topic_id = 0 ) {
	global $bbp;

	if ( !$topic = wp_get_single_post( $topic_id, ARRAY_A ) )
		return $topic;

	if ( $topic['post_status'] == $bbp->spam_status_id )
		return false;

	do_action( 'bbp_spam_topic', $topic_id );

	add_post_meta( $topic_id, '_bbp_spam_meta_status', $topic['post_status'] );

	$topic['post_status'] = $bbp->spam_status_id;

	$topic_id = wp_insert_post( $topic );

	do_action( 'bbp_spammed_topic', $topic_id );

	return $topic_id;
}

/**
 * Unspams a topic
 *
 * @since bbPress (r2740)
 *
 * @param int $topic_id Topic id
 * @uses wp_get_single_post() To get the topic
 * @uses do_action() Calls 'bbp_unspam_topic' with the topic id
 * @uses get_post_meta() To get the previous status
 * @uses delete_post_meta() To delete the previous status meta
 * @uses wp_insert_post() To update the topic with the new status
 * @uses do_action() Calls 'bbp_unspammed_topic' with the topic id
 * @return mixed False or {@link WP_Error} on failure, topic id on success
 */
function bbp_unspam_topic( $topic_id = 0 ) {
	global $bbp;

	if ( !$topic = wp_get_single_post( $topic_id, ARRAY_A ) )
		return $topic;

	if ( $topic['post_status'] != $bbp->spam_status_id )
		return false;

	do_action( 'bbp_unspam_topic', $topic_id );

	$topic_status         = get_post_meta( $topic_id, '_bbp_spam_meta_status', true );
	$topic['post_status'] = $topic_status;

	delete_post_meta( $topic_id, '_bbp_spam_meta_status' );

	$topic_id = wp_insert_post( $topic );

	do_action( 'bbp_unspammed_topic', $topic_id );

	return $topic_id;
}

/**
 * Sticks a topic to a forum or front
 *
 * @since bbPress (r2754)
 *
 * @param int $topic_id Optional. Topic id
 * @param int $super Should we make the topic a super sticky?
 * @uses bbp_get_topic_id() To get the topic id
 * @uses bbp_unstick_topic() To unstick the topic
 * @uses bbp_get_topic_forum_id() To get the topic forum id
 * @uses bbp_get_stickies() To get the stickies
 * @uses do_action() 'bbp_stick_topic' with topic id and bool super
 * @uses update_option() To update the super stickies option
 * @uses update_post_meta() To update the forum stickies meta
 * @uses do_action() Calls 'bbp_sticked_topic' with the topic id, bool super
 *                    and success
 * @return bool True on success, false on failure
 */
function bbp_stick_topic( $topic_id = 0, $super = false ) {
	$topic_id = bbp_get_topic_id( $topic_id );

	// We may have a super sticky to which we want to convert into a normal sticky and vice versa
	// So, unstick the topic first to avoid any possible error
	bbp_unstick_topic( $topic_id );

	$forum_id = empty( $super ) ? bbp_get_topic_forum_id( $topic_id ) : 0;
	$stickies = bbp_get_stickies( $forum_id );

	do_action( 'bbp_stick_topic', $topic_id, $super );

	if ( !is_array( $stickies ) )
		$stickies   = array( $topic_id );
	else
		$stickies[] = $topic_id;

	$stickies = array_unique( array_filter( $stickies ) );

	$success = !empty( $super ) ? update_option( '_bbp_super_sticky_topics', $stickies ) : update_post_meta( $forum_id, '_bbp_sticky_topics', $stickies );

	do_action( 'bbp_sticked_topic', $topic_id, $super, $success );

	return $success;
}

/**
 * Unsticks a topic both from front and it's forum
 *
 * @since bbPress (r2754)
 *
 * @param int $topic_id Optional. Topic id
 * @uses bbp_get_topic_id() To get the topic id
 * @uses bbp_is_topic_super_sticky() To check if the topic is a super sticky
 * @uses bbp_get_topic_forum_id() To get the topic forum id
 * @uses bbp_get_stickies() To get the forum stickies
 * @uses do_action() Calls 'bbp_unstick_topic' with the topic id
 * @uses delete_option() To delete the super stickies option
 * @uses update_option() To update the super stickies option
 * @uses delete_post_meta() To delete the forum stickies meta
 * @uses update_post_meta() To update the forum stickies meta
 * @uses do_action() Calls 'bbp_unsticked_topic' with the topic id and success
 * @return bool Always true.
 */
function bbp_unstick_topic( $topic_id = 0 ) {
	$topic_id = bbp_get_topic_id( $topic_id );
	$super    = bbp_is_topic_super_sticky( $topic_id );
	$forum_id = empty( $super ) ? bbp_get_topic_forum_id( $topic_id ) : 0;
	$stickies = bbp_get_stickies( $forum_id );
	$offset   = array_search( $topic_id, $stickies );

	do_action( 'bbp_unstick_topic', $topic_id );

	if ( empty( $stickies ) ) {
		$success = true;
	} elseif ( !in_array( $topic_id, $stickies ) ) {
		$success = true;
	} elseif ( false === $offset ) {
		$success = true;
	} else {
		array_splice( $stickies, $offset, 1 );
		if ( empty( $stickies ) )
			$success = !empty( $super ) ? delete_option( '_bbp_super_sticky_topics'            ) : delete_post_meta( $forum_id, '_bbp_sticky_topics'            );
		else
			$success = !empty( $super ) ? update_option( '_bbp_super_sticky_topics', $stickies ) : update_post_meta( $forum_id, '_bbp_sticky_topics', $stickies );
	}

	do_action( 'bbp_unsticked_topic', $topic_id, $success );

	return true;
}

/** Before Delete/Trash/Untrash ***********************************************/

/**
 * Called before deleting a topic
 *
 * @uses bbp_get_topic_id() To get the topic id
 * @uses bbp_is_topic() To check if the passed id is a topic
 * @uses do_action() Calls 'bbp_delete_topic' with the topic id
 * @uses bbp_has_replies() To check if the topic has replies
 * @uses bbp_replies() To loop through the replies
 * @uses bbp_the_reply() To set a reply as the current reply in the loop
 * @uses bbp_get_reply_id() To get the reply id
 * @uses wp_delete_post() To delete the reply
 */
function bbp_delete_topic( $topic_id = 0 ) {
	$topic_id = bbp_get_topic_id( $topic_id );

	if ( empty( $topic_id ) || !bbp_is_topic( $topic_id ) )
		return false;

	do_action( 'bbp_delete_topic', $topic_id );

	// Topic is being permanently deleted, so its replies gotta go too
	if ( bbp_has_replies( array( 'post_parent' => $topic_id, 'post_status' => 'publish', 'posts_per_page' => -1 ) ) ) {
		while ( bbp_replies() ) {
			bbp_the_reply();
			wp_delete_post( bbp_get_reply_id(), true );
		}
	}
}

/**
 * Called before trashing a topic
 *
 * @uses bbp_get_topic_id() To get the topic id
 * @uses bbp_is_topic() To check if the passed id is a topic
 * @uses do_action() Calls 'bbp_trash_topic' with the topic id
 * @uses bbp_has_replies() To check if the topic has replies
 * @uses bbp_replies() To loop through the replies
 * @uses bbp_the_reply() To set a reply as the current reply in the loop
 * @uses bbp_get_reply_id() To get the reply id
 * @uses wp_trash_post() To trash the reply
 * @uses update_post_meta() To save a list of just trashed replies for future use
 */
function bbp_trash_topic( $topic_id = 0 ) {
	$topic_id = bbp_get_topic_id( $topic_id );

	if ( empty( $topic_id ) || !bbp_is_topic( $topic_id ) )
		return false;

	do_action( 'bbp_trash_topic', $topic_id );

	// Topic is being permanently deleted, so its replies gotta go too
	if ( bbp_has_replies( array( 'post_parent' => $topic_id, 'post_status' => 'publish', 'posts_per_page' => -1 ) ) ) {
		global $bbp;

		while ( bbp_replies() ) {
			bbp_the_reply();
			wp_trash_post( $bbp->reply_query->post->ID );
			$pre_trashed_replies[] = $bbp->reply_query->post->ID;
		}

		// Set a post_meta entry of the replies that were trashed by this action.
		// This is so we can possibly untrash them, without untrashing replies
		// that were purposefully trashed before.
		update_post_meta( $topic_id, '_bbp_pre_trashed_replies', $pre_trashed_replies );
	}
}

/**
 * Called before untrashing a topic
 *
 * @uses bbp_get_topic_id() To get the topic id
 * @uses bbp_is_topic() To check if the passed id is a topic
 * @uses do_action() Calls 'bbp_untrash_topic' with the topic id
 * @uses get_post_meta() To get the list of replies which were trashed with the
 *                        topic
 * @uses wp_untrash_post() To untrash the reply
 */
function bbp_untrash_topic( $topic_id = 0 ) {
	$topic_id = bbp_get_topic_id( $topic_id );

	if ( empty( $topic_id ) || !bbp_is_topic( $topic_id ) )
		return false;

	do_action( 'bbp_untrash_topic', $topic_id );

	// Loop through and restore pre trashed replies to this topic
	if ( $pre_trashed_replies = get_post_meta( $topic_id, '_bbp_pre_trashed_replies', true ) ) {
		foreach ( $pre_trashed_replies as $reply )
			wp_untrash_post( $reply );
	}
}

/** After Delete/Trash/Untrash ************************************************/

/**
 * Called after deleting a topic
 *
 * @uses bbp_get_topic_id() To get the topic id
 * @uses bbp_is_topic() To check if the passed id is a topic
 * @uses do_action() Calls 'bbp_deleted_topic' with the topic id
 */
function bbp_deleted_topic( $topic_id = 0 ) {
	$topic_id = bbp_get_topic_id( $topic_id );

	if ( empty( $topic_id ) || !bbp_is_topic( $topic_id ) )
		return false;

	do_action( 'bbp_deleted_topic', $topic_id );
}

/**
 * Called after trashing a topic
 *
 * @uses bbp_get_topic_id() To get the topic id
 * @uses bbp_is_topic() To check if the passed id is a topic
 * @uses do_action() Calls 'bbp_trashed_topic' with the topic id
 */
function bbp_trashed_topic( $topic_id = 0 ) {
	$topic_id = bbp_get_topic_id( $topic_id );

	if ( empty( $topic_id ) || !bbp_is_topic( $topic_id ) )
		return false;

	do_action( 'bbp_trashed_topic', $topic_id );
}

/**
 * Called after untrashing a topic
 *
 * @uses bbp_get_topic_id() To get the topic id
 * @uses bbp_is_topic() To check if the passed id is a topic
 * @uses do_action() Calls 'bbp_untrashed_topic' with the topic id
 */
function bbp_untrashed_topic( $topic_id = 0 ) {
	$topic_id = bbp_get_topic_id( $topic_id );

	if ( empty( $topic_id ) || !bbp_is_topic( $topic_id ) )
		return false;

	do_action( 'bbp_untrashed_topic', $topic_id );
}

/** Feeds *********************************************************************/

/**
 * Output an RSS2 feed of topics, based on the query passed.
 *
 * @since bbPress (r3171)
 *
 * @global bbPress $bbp
 *
 * @uses bbp_exclude_forum_ids()
 * @uses bbp_is_topic()
 * @uses bbp_user_can_view_forum()
 * @uses bbp_get_topic_forum_id()
 * @uses bbp_show_load_topic()
 * @uses bbp_topic_permalink()
 * @uses bbp_topic_title()
 * @uses bbp_get_topic_reply_count()
 * @uses bbp_topic_content()
 * @uses bbp_has_topics()
 * @uses bbp_topics()
 * @uses bbp_the_topic()
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
 * @param array $topics_query
 */
function bbp_display_topics_feed_rss2( $topics_query = array() ) {
	global $bbp;

	// User cannot access forum this topic is in
	if ( bbp_is_topic() && !bbp_user_can_view_forum( array( 'forum_id' => bbp_get_topic_forum_id() ) ) )
		return;

	// Remove any topics from hidden forums
	$topics_query = bbp_exclude_forum_ids( $topics_query );

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

		<title><?php bloginfo_rss( 'name' ); ?> &#187; <?php _e( 'All Topics', 'bbpress' ); ?></title>
		<atom:link href="<?php self_link(); ?>" rel="self" type="application/rss+xml" />
		<link><?php self_link(); ?></link>
		<description><?php //?></description>
		<pubDate><?php echo mysql2date( 'D, d M Y H:i:s O', '', false ); ?></pubDate>
		<generator>http://bbpress.org/?v=<?php echo BBP_VERSION; ?></generator>
		<language><?php echo get_option( 'rss_language' ); ?></language>

		<?php do_action( 'bbp_feed_head' ); ?>

		<?php if ( bbp_has_topics( $topics_query ) ) : ?>

			<?php while ( bbp_topics() ) : bbp_the_topic(); ?>

				<item>
					<guid><?php bbp_topic_permalink(); ?></guid>
					<title><![CDATA[<?php bbp_topic_title(); ?>]]></title>
					<link><?php bbp_topic_permalink(); ?></link>
					<pubDate><?php echo mysql2date('D, d M Y H:i:s +0000', get_post_meta( bbp_get_topic_id(), '_bbp_last_active_time', true ) ); ?></pubDate>
					<dc:creator><?php the_author() ?></dc:creator>

					<?php if ( !post_password_required() ) : ?>

					<description>
						<![CDATA[
						<p><?php printf( __( 'Replies: %s', 'bbpress' ), bbp_get_topic_reply_count() ); ?></p>
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

?>
