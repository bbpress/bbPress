<?php

/**
 * bbPress Replies Admin Class
 *
 * @package bbPress
 * @subpackage Administration
 */

// Redirect if accessed directly
if ( !defined( 'ABSPATH' ) ) exit;

if ( !class_exists( 'BBP_Replies_Admin' ) ) :
/**
 * Loads bbPress replies admin area
 *
 * @package bbPress
 * @subpackage Administration
 * @since bbPress (r2464)
 */
class BBP_Replies_Admin {

	/** Variables *************************************************************/

	/**
	 * @var The post type of this admin component
	 */
	var $post_type = '';

	/** Functions *************************************************************/

	/**
	 * The main bbPress admin loader (PHP4 compat)
	 *
	 * @since bbPress (r2515)
	 *
	 * @uses BBP_Replies_Admin::_setup_globals() Setup the globals needed
	 * @uses BBP_Topics_Admin::_setup_actions() Setup the hooks and actions
	 */
	function BBP_Replies_Admin() {
		$this->__construct();
	}

	/**
	 * The main bbPress admin loader
	 *
	 * @since bbPress (r2515)
	 *
	 * @uses BBP_Replies_Admin::_setup_globals() Setup the globals needed
	 * @uses BBP_Replies_Admin::_setup_actions() Setup the hooks and actions
	 */
	function __construct() {
		$this->_setup_globals();
		$this->_setup_actions();
	}

	/**
	 * Setup the admin hooks, actions and filters
	 *
	 * @since bbPress (r2646)
	 * @access private
	 *
	 * @uses add_action() To add various actions
	 * @uses add_filter() To add various filters
	 * @uses bbp_get_forum_post_type() To get the forum post type
	 * @uses bbp_get_topic_post_type() To get the topic post type
	 * @uses bbp_get_reply_post_type() To get the reply post type
	 */
	function _setup_actions() {

		// Add some general styling to the admin area
		add_action( 'admin_head', array( $this, 'admin_head' ) );

		// Reply column headers.
		add_filter( 'manage_' . $this->post_type . '_posts_columns',  array( $this, 'replies_column_headers' ) );

		// Reply columns (in post row)
		add_action( 'manage_' . $this->post_type . '_posts_custom_column',  array( $this, 'replies_column_data' ), 10, 2 );
		add_filter( 'post_row_actions',                                     array( $this, 'replies_row_actions' ), 10, 2 );

		// Reply metabox actions
		add_action( 'add_meta_boxes', array( $this, 'reply_attributes_metabox'      ) );
		add_action( 'save_post',      array( $this, 'reply_attributes_metabox_save' ) );

		// Check if there are any bbp_toggle_reply_* requests on admin_init, also have a message displayed
		add_action( 'bbp_admin_init', array( $this, 'toggle_reply'        ) );
		add_action( 'admin_notices',  array( $this, 'toggle_reply_notice' ) );

		// Anonymous metabox actions
		add_action( 'add_meta_boxes', array( $this, 'anonymous_metabox'      ) );
		add_action( 'save_post',      array( $this, 'anonymous_metabox_save' ) );

		// Add ability to filter topics and replies per forum
		add_filter( 'restrict_manage_posts', array( $this, 'filter_dropdown'  ) );
		add_filter( 'request',               array( $this, 'filter_post_rows' ) );
	}

	/**
	 * Admin globals
	 *
	 * @since bbPress (r2646)
	 * @access private
	 */
	function _setup_globals() {

		// Setup the post type for this admin component
		$this->post_type = bbp_get_reply_post_type();
	}

	/**
	 * Add the reply attributes metabox
	 *
	 * @since bbPress (r2746)
	 *
	 * @uses bbp_get_reply_post_type() To get the reply post type
	 * @uses add_meta_box() To add the metabox
	 * @uses do_action() Calls 'bbp_reply_attributes_metabox'
	 */
	function reply_attributes_metabox() {
		add_meta_box (
			'bbp_reply_attributes',
			__( 'Reply Attributes', 'bbpress' ),
			'bbp_reply_metabox',
			$this->post_type,
			'side',
			'high'
		);

		do_action( 'bbp_reply_attributes_metabox' );
	}

	/**
	 * Pass the reply attributes for processing
	 *
	 * @since bbPress (r2746)
	 *
	 * @param int $reply_id Reply id
	 * @uses current_user_can() To check if the current user is capable of
	 *                           editing the reply
	 * @uses do_action() Calls 'bbp_reply_attributes_metabox_save' with the
	 *                    reply id and parent id
	 * @return int Parent id
	 */
	function reply_attributes_metabox_save( $reply_id ) {

		// Bail if doing an autosave
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE )
			return $reply_id;

		// Current user cannot edit this reply
		if ( !current_user_can( 'edit_reply', $reply_id ) )
			return $reply_id;

		// Load the reply
		if ( !$reply = bbp_get_reply( $reply_id ) )
			return $reply_id;

		// OK, we're authenticated: we need to find and save the data
		$parent_id = isset( $reply->parent_id ) ? $reply->parent_id : 0;

		do_action( 'bbp_reply_attributes_metabox_save', $reply_id, $parent_id );

		return $parent_id;
	}

	/**
	 * Add the anonymous user info metabox
	 *
	 * Allows editing of information about an anonymous user
	 *
	 * @since bbPress (r2828)
	 *
	 * @uses bbp_get_topic() To get the topic
	 * @uses bbp_get_reply() To get the reply
	 * @uses bbp_is_topic_anonymous() To check if the topic is by an
	 *                                 anonymous user
	 * @uses bbp_is_reply_anonymous() To check if the reply is by an
	 *                                 anonymous user
	 * @uses bbp_get_topic_post_type() To get the topic post type
	 * @uses bbp_get_reply_post_type() To get the reply post type
	 * @uses add_meta_box() To add the metabox
	 * @uses do_action() Calls 'bbp_anonymous_metabox' with the topic/reply
	 *                    id
	 */
	function anonymous_metabox() {

		// Bail if post_type is not a topic or reply
		if ( get_post_type() != $this->post_type )
			return;

		// Bail if reply is not anonymous
		if ( !bbp_is_reply_anonymous( get_the_ID() ) )
			return;

		// Add the metabox
		add_meta_box(
			'bbp_anonymous_metabox',
			__( 'Anonymous User Information', 'bbpress' ),
			'bbp_anonymous_metabox',
			$this->post_type,
			'side',
			'high'
		);

		do_action( 'bbp_anonymous_metabox', get_the_ID() );
	}

	/**
	 * Save the anonymous user information for the topic/reply
	 *
	 * @since bbPress (r2828)
	 *
	 * @param int $post_id Topic or reply id
	 * @uses bbp_get_topic() To get the topic
	 * @uses bbp_get_reply() To get the reply
	 * @uses current_user_can() To check if the current user can edit the
	 *                           topic or reply
	 * @uses bbp_is_topic_anonymous() To check if the topic is by an
	 *                                 anonymous user
	 * @uses bbp_is_reply_anonymous() To check if the reply is by an
	 *                                 anonymous user
	 * @uses bbp_filter_anonymous_post_data() To filter the anonymous user data
	 * @uses update_post_meta() To update the anonymous user data
	 * @uses do_action() Calls 'bbp_anonymous_metabox_save' with the topic/
	 *                    reply id and anonymous data
	 * @return int Topic or reply id
	 */
	function anonymous_metabox_save( $post_id ) {

		// Bail if no post_id
		if ( empty( $post_id ) )
			return $post_id;

		// Bail if doing an autosave
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE )
			return $post_id;

		// Bail if post_type is not a topic or reply
		if ( get_post_type( $post_id ) != $this->post_type )
			return;

		// Bail if user cannot edit replies or reply is not anonymous
		if ( ( !current_user_can( 'edit_reply', $post_id ) || !bbp_is_reply_anonymous( $post_id ) ) )
			return $post_id;

		$anonymous_data = bbp_filter_anonymous_post_data();

		update_post_meta( $post_id, '_bbp_anonymous_name',    $anonymous_data['bbp_anonymous_name']    );
		update_post_meta( $post_id, '_bbp_anonymous_email',   $anonymous_data['bbp_anonymous_email']   );
		update_post_meta( $post_id, '_bbp_anonymous_website', $anonymous_data['bbp_anonymous_website'] );

		do_action( 'bbp_anonymous_metabox_save', $post_id, $anonymous_data );

		return $post_id;
	}

	/**
	 * Add some general styling to the admin area
	 *
	 * @since bbPress (r2464)
	 *
	 * @uses bbp_get_forum_post_type() To get the forum post type
	 * @uses bbp_get_topic_post_type() To get the topic post type
	 * @uses bbp_get_reply_post_type() To get the reply post type
	 * @uses sanitize_html_class() To sanitize the classes
	 * @uses bbp_is_forum() To check if it is a forum page
	 * @uses bbp_is_topic() To check if it is a topic page
	 * @uses bbp_is_reply() To check if it is a reply page
	 * @uses do_action() Calls 'bbp_admin_head'
	 */
	function admin_head() {

		if ( get_post_type() == $this->post_type ) : ?>

			<style type="text/css" media="screen">
			/*<![CDATA[*/

				.column-bbp_forum_topic_count,
				.column-bbp_forum_reply_count,
				.column-bbp_topic_reply_count,
				.column-bbp_topic_voice_count {
					width: 8% !important;
				}

				.column-author,
				.column-bbp_reply_author,
				.column-bbp_topic_author {
					width: 10% !important;
				}

				.column-bbp_topic_forum,
				.column-bbp_reply_forum,
				.column-bbp_reply_topic {
					width: 10% !important;
				}

				.column-bbp_forum_freshness,
				.column-bbp_topic_freshness {
					width: 10% !important;
				}

				.column-bbp_forum_created,
				.column-bbp_topic_created,
				.column-bbp_reply_created {
					width: 15% !important;
				}

				.status-closed {
					background-color: #eaeaea;
				}

				.status-spam {
					background-color: #faeaea;
				}

			/*]]>*/
			</style>

		<?php endif;

	}

	/**
	 * Toggle reply
	 *
	 * Handles the admin-side spamming/unspamming of replies
	 *
	 * @since bbPress (r2740)
	 *
	 * @uses bbp_get_reply() To get the reply
	 * @uses current_user_can() To check if the user is capable of editing
	 *                           the reply
	 * @uses wp_die() To die if the user isn't capable or the post wasn't
	 *                 found
	 * @uses check_admin_referer() To verify the nonce and check referer
	 * @uses bbp_is_reply_spam() To check if the reply is marked as spam
	 * @uses bbp_unspam_reply() To unmark the reply as spam
	 * @uses bbp_spam_reply() To mark the reply as spam
	 * @uses do_action() Calls 'bbp_toggle_reply_admin' with success, post
	 *                    data, action and message
	 * @uses add_query_arg() To add custom args to the url
	 * @uses wp_redirect() Redirect the page to custom url
	 */
	function toggle_reply() {

		// Only proceed if GET is a reply toggle action
		if ( 'GET' == $_SERVER['REQUEST_METHOD'] && !empty( $_GET['action'] ) && in_array( $_GET['action'], array( 'bbp_toggle_reply_spam' ) ) && !empty( $_GET['reply_id'] ) ) {
			$action    = $_GET['action'];            // What action is taking place?
			$reply_id  = (int) $_GET['reply_id'];    // What's the reply id?
			$success   = false;                      // Flag
			$post_data = array( 'ID' => $reply_id ); // Prelim array

			if ( !$reply = bbp_get_reply( $reply_id ) ) // Which reply?
				wp_die( __( 'The reply was not found!', 'bbpress' ) );

			if ( !current_user_can( 'moderate', $reply->ID ) ) // What is the user doing here?
				wp_die( __( 'You do not have the permission to do that!', 'bbpress' ) );

			switch ( $action ) {
				case 'bbp_toggle_reply_spam' :
					check_admin_referer( 'spam-reply_' . $reply_id );

					$is_spam = bbp_is_reply_spam( $reply_id );
					$message = $is_spam ? 'unspammed' : 'spammed';
					$success = $is_spam ? bbp_unspam_reply( $reply_id ) : bbp_spam_reply( $reply_id );

					break;
			}

			$success = wp_update_post( $post_data );
			$message = array( 'bbp_reply_toggle_notice' => $message, 'reply_id' => $reply->ID );

			if ( false == $success || is_wp_error( $success ) )
				$message['failed'] = '1';

			// Do additional reply toggle actions (admin side)
			do_action( 'bbp_toggle_reply_admin', $success, $post_data, $action, $message );

			// Redirect back to the reply
			$redirect = add_query_arg( $message, remove_query_arg( array( 'action', 'reply_id' ) ) );
			wp_redirect( $redirect );

			// For good measure
			exit();
		}
	}

	/**
	 * Toggle reply notices
	 *
	 * Display the success/error notices from
	 * {@link BBP_Admin::toggle_reply()}
	 *
	 * @since bbPress (r2740)
	 *
	 * @uses bbp_get_reply() To get the reply
	 * @uses bbp_get_reply_title() To get the reply title of the reply
	 * @uses esc_html() To sanitize the reply title
	 * @uses apply_filters() Calls 'bbp_toggle_reply_notice_admin' with
	 *                        message, reply id, notice and is it a failure
	 */
	function toggle_reply_notice() {

		// Only proceed if GET is a reply toggle action
		if ( 'GET' == $_SERVER['REQUEST_METHOD'] && !empty( $_GET['bbp_reply_toggle_notice'] ) && in_array( $_GET['bbp_reply_toggle_notice'], array( 'spammed', 'unspammed' ) ) && !empty( $_GET['reply_id'] ) ) {
			$notice     = $_GET['bbp_reply_toggle_notice'];         // Which notice?
			$reply_id   = (int) $_GET['reply_id'];                  // What's the reply id?
			$is_failure = !empty( $_GET['failed'] ) ? true : false; // Was that a failure?

			// Empty? No reply?
			if ( empty( $notice ) || empty( $reply_id ) || !$reply = bbp_get_reply( $reply_id ) )
				return;

			$reply_title = esc_html( bbp_get_reply_title( $reply->ID ) );

			switch ( $notice ) {
				case 'spammed' :
					$message = $is_failure == true ? sprintf( __( 'There was a problem marking the reply "%1$s" as spam.', 'bbpress' ), $reply_title ) : sprintf( __( 'Reply "%1$s" successfully marked as spam.', 'bbpress' ), $reply_title );
					break;

				case 'unspammed' :
					$message = $is_failure == true ? sprintf( __( 'There was a problem unmarking the reply "%1$s" as spam.', 'bbpress' ), $reply_title ) : sprintf( __( 'Reply "%1$s" successfully unmarked as spam.', 'bbpress' ), $reply_title );
					break;
			}

			// Do additional reply toggle notice filters (admin side)
			$message = apply_filters( 'bbp_toggle_reply_notice_admin', $message, $reply->ID, $notice, $is_failure );

			?>

			<div id="message" class="<?php echo $is_failure == true ? 'error' : 'updated'; ?> fade">
				<p style="line-height: 150%"><?php echo $message; ?></p>
			</div>

			<?php
		}
	}

	/**
	 * Manage the column headers for the replies page
	 *
	 * @since bbPress (r2577)
	 *
	 * @param array $columns The columns
	 * @uses apply_filters() Calls 'bbp_admin_replies_column_headers' with
	 *                        the columns
	 * @return array $columns bbPress reply columns
	 */
	function replies_column_headers( $columns ) {
		$columns = array(
			'cb'                => '<input type="checkbox" />',
			'title'             => __( 'Title',   'bbpress' ),
			'bbp_reply_forum'   => __( 'Forum',   'bbpress' ),
			'bbp_reply_topic'   => __( 'Topic',   'bbpress' ),
			'bbp_reply_author'  => __( 'Author',  'bbpress' ),
			'bbp_reply_created' => __( 'Created', 'bbpress' ),
		);

		return apply_filters( 'bbp_admin_replies_column_headers', $columns );
	}

	/**
	 * Print extra columns for the replies page
	 *
	 * @since bbPress (r2577)
	 *
	 * @param string $column Column
	 * @param int $reply_id reply id
	 * @uses bbp_get_reply_topic_id() To get the topic id of the reply
	 * @uses bbp_topic_title() To output the reply's topic title
	 * @uses apply_filters() Calls 'reply_topic_row_actions' with an array
	 *                        of reply topic actions
	 * @uses bbp_get_topic_permalink() To get the topic permalink
	 * @uses bbp_get_topic_forum_id() To get the forum id of the topic of
	 *                                 the reply
	 * @uses bbp_get_forum_permalink() To get the forum permalink
	 * @uses admin_url() To get the admin url of post.php
	 * @uses add_query_arg() To add custom args to the url
	 * @uses apply_filters() Calls 'reply_topic_forum_row_actions' with an
	 *                        array of reply topic forum actions
	 * @uses bbp_reply_author_display_name() To output the reply author name
	 * @uses get_the_date() Get the reply creation date
	 * @uses get_the_time() Get the reply creation time
	 * @uses esc_attr() To sanitize the reply creation time
	 * @uses bbp_get_reply_last_active_time() To get the time when the reply was
	 *                                    last active
	 * @uses do_action() Calls 'bbp_admin_replies_column_data' with the
	 *                    column and reply id
	 */
	function replies_column_data( $column, $reply_id ) {

		// Get topic ID
		$topic_id = bbp_get_reply_topic_id( $reply_id );

		// Populate Column Data
		switch ( $column ) {

			// Topic
			case 'bbp_reply_topic' :

				// Output forum name
				bbp_topic_title( $topic_id );

				// Link information
				$actions = apply_filters( 'reply_topic_row_actions', array (
					'edit' => '<a href="' . add_query_arg( array( 'post' => $topic_id, 'action' => 'edit' ), admin_url( '/post.php' ) ) . '">' . __( 'Edit', 'bbpress' ) . '</a>',
					'view' => '<a href="' . bbp_get_topic_permalink( $topic_id ) . '">' . __( 'View', 'bbpress' ) . '</a>'
				) );

				// Output forum post row links
				foreach ( $actions as $action => $link )
					$formatted_actions[] = '<span class="' . $action . '">' . $link . '</span>';

				//echo '<div class="row-actions">' . implode( ' | ', $formatted_actions ) . '</div>';

				break;

			// Forum
			case 'bbp_reply_forum' :

				// Get Forum ID
				$forum_id = bbp_get_topic_forum_id( $topic_id );

				// Output forum name
				bbp_forum_title( $forum_id );

				// Link information
				$actions = apply_filters( 'reply_topic_forum_row_actions', array (
					'edit' => '<a href="' . add_query_arg( array( 'post' => $forum_id, 'action' => 'edit' ), admin_url( '/post.php' ) ) . '">' . __( 'Edit', 'bbpress' ) . '</a>',
					'view' => '<a href="' . bbp_get_forum_permalink( $forum_id ) . '">' . __( 'View', 'bbpress' ) . '</a>'
				) );

				// Output forum post row links
				foreach ( $actions as $action => $link )
					$formatted_actions[] = '<span class="' . $action . '">' . $link . '</span>';

				//echo '<div class="row-actions">' . implode( ' | ', $formatted_actions ) . '</div>';

				break;

			// Author
			case 'bbp_reply_author' :
				bbp_reply_author_display_name ( $reply_id );
				break;

			// Freshness
			case 'bbp_reply_created':

				// Output last activity time and date
				printf( __( '%1$s <br /> %2$s', 'bbpress' ),
					get_the_date(),
					esc_attr( get_the_time() )
				);

				break;

			// Do action for anything else
			default :
				do_action( 'bbp_admin_replies_column_data', $column, $reply_id );
				break;
		}
	}

	/**
	 * Reply Row actions
	 *
	 * Remove the quick-edit action link under the reply title and add the
	 * content and spam link
	 *
	 * @since bbPress (r2577)
	 *
	 * @param array $actions Actions
	 * @param array $reply Reply object
	 * @uses bbp_get_reply_post_type() To get the reply post type
	 * @uses bbp_reply_content() To output reply content
	 * @uses bbp_get_reply_permalink() To get the reply link
	 * @uses bbp_get_reply_title() To get the reply title
	 * @uses current_user_can() To check if the current user can edit or
	 *                           delete the reply
	 * @uses bbp_is_reply_spam() To check if the reply is marked as spam
	 * @uses get_post_type_object() To get the reply post type object
	 * @uses add_query_arg() To add custom args to the url
	 * @uses remove_query_arg() To remove custom args from the url
	 * @uses wp_nonce_url() To nonce the url
	 * @uses get_delete_post_link() To get the delete post link of the reply
	 * @return array $actions Actions
	 */
	function replies_row_actions( $actions, $reply ) {
		global $bbp;

		if ( bbp_get_reply_post_type() == $reply->post_type ) {
			unset( $actions['inline hide-if-no-js'] );

			// Show view link if it's not set, the reply is trashed and the user can view trashed replies
			if ( empty( $actions['view'] ) && 'trash' == $reply->post_status && current_user_can( 'view_trash' ) )
				$actions['view'] = '<a href="' . bbp_get_reply_permalink( $reply->ID ) . '" title="' . esc_attr( sprintf( __( 'View &#8220;%s&#8221;', 'bbpress' ), bbp_get_reply_title( $reply->ID ) ) ) . '" rel="permalink">' . __( 'View', 'bbpress' ) . '</a>';

			bbp_reply_content( $reply->ID );

			// Only show the actions if the user is capable of viewing them
			if ( current_user_can( 'moderate', $reply->ID ) ) {
				if ( in_array( $reply->post_status, array( 'publish', $bbp->spam_status_id ) ) ) {
					$spam_uri  = esc_url( wp_nonce_url( add_query_arg( array( 'reply_id' => $reply->ID, 'action' => 'bbp_toggle_reply_spam' ), remove_query_arg( array( 'bbp_reply_toggle_notice', 'reply_id', 'failed', 'super' ) ) ), 'spam-reply_'  . $reply->ID ) );
					if ( bbp_is_reply_spam( $reply->ID ) )
						$actions['spam'] = '<a href="' . $spam_uri . '" title="' . esc_attr__( 'Mark the reply as not spam', 'bbpress' ) . '">' . __( 'Not spam', 'bbpress' ) . '</a>';
					else
						$actions['spam'] = '<a href="' . $spam_uri . '" title="' . esc_attr__( 'Mark this reply as spam',    'bbpress' ) . '">' . __( 'Spam',     'bbpress' ) . '</a>';
				}
			}

			// Trash
			if ( current_user_can( 'delete_reply', $reply->ID ) ) {
				if ( $bbp->trash_status_id == $reply->post_status ) {
					$post_type_object = get_post_type_object( bbp_get_reply_post_type() );
					$actions['untrash'] = "<a title='" . esc_attr( __( 'Restore this item from the Trash', 'bbpress' ) ) . "' href='" . add_query_arg( array( '_wp_http_referer' => add_query_arg( array( 'post_type' => bbp_get_reply_post_type() ), admin_url( 'edit.php' ) ) ), wp_nonce_url( admin_url( sprintf( $post_type_object->_edit_link . '&amp;action=untrash', $reply->ID ) ), 'untrash-' . $reply->post_type . '_' . $reply->ID ) ) . "'>" . __( 'Restore', 'bbpress' ) . "</a>";
				} elseif ( EMPTY_TRASH_DAYS ) {
					$actions['trash'] = "<a class='submitdelete' title='" . esc_attr( __( 'Move this item to the Trash', 'bbpress' ) ) . "' href='" . add_query_arg( array( '_wp_http_referer' => add_query_arg( array( 'post_type' => bbp_get_reply_post_type() ), admin_url( 'edit.php' ) ) ), get_delete_post_link( $reply->ID ) ) . "'>" . __( 'Trash', 'bbpress' ) . "</a>";
				}

				if ( $bbp->trash_status_id == $reply->post_status || !EMPTY_TRASH_DAYS ) {
					$actions['delete'] = "<a class='submitdelete' title='" . esc_attr( __( 'Delete this item permanently', 'bbpress' ) ) . "' href='" . add_query_arg( array( '_wp_http_referer' => add_query_arg( array( 'post_type' => bbp_get_reply_post_type() ), admin_url( 'edit.php' ) ) ), get_delete_post_link( $reply->ID, '', true ) ) . "'>" . __( 'Delete Permanently', 'bbpress' ) . "</a>";
				} elseif ( $bbp->spam_status_id == $reply->post_status ) {
					unset( $actions['trash'] );
				}
			}
		}

		return $actions;
	}

	/**
	 * Add forum dropdown to topic and reply list table filters
	 *
	 * @since bbPress (r2991)
	 *
	 * @uses bbp_get_reply_post_type() To get the reply post type
	 * @uses bbp_get_topic_post_type() To get the topic post type
	 * @uses bbp_dropdown() To generate a forum dropdown
	 * @return bool False. If post type is not topic or reply
	 */
	function filter_dropdown() {

		// Bail if not viewing the topics list
		if (
				// post_type exists in _GET
				empty( $_GET['post_type'] ) ||

				// post_type is reply or topic type
				( $_GET['post_type'] != $this->post_type )
			)
			return;

		// Get which forum is selected
		$selected = !empty( $_GET['bbp_forum_id'] ) ? $_GET['bbp_forum_id'] : '';

		// Show the forums dropdown
		bbp_dropdown( array(
			'selected'  => $selected,
			'show_none' => __( 'In all forums', 'bbpress' )
		) );
	}

	/**
	 * Adjust the request query and include the forum id
	 *
	 * @since bbPress (r2991)
	 *
	 * @param array $query_vars Query variables from {@link WP_Query}
	 * @uses is_admin() To check if it's the admin section
	 * @uses bbp_get_topic_post_type() To get the topic post type
	 * @uses bbp_get_reply_post_type() To get the reply post type
	 * @return array Processed Query Vars
	 */
	function filter_post_rows( $query_vars ) {
		global $pagenow;

		// Avoid poisoning other requests
		if (
				// Only look in admin
				!is_admin()                 ||

				// Make sure the current page is for post rows
				( 'edit.php' != $pagenow  ) ||

				// Make sure we're looking for a post_type
				empty( $_GET['post_type'] ) ||

				// Make sure we're looking at bbPress topics
				( $_GET['post_type'] != $this->post_type )
			)

			// We're in no shape to filter anything, so return
			return $query_vars;

		// Add post_parent query_var if one is present
		if ( !empty( $_GET['bbp_forum_id'] ) ) {
			$query_vars['meta_key']   = '_bbp_forum_id';
			$query_vars['meta_value'] = $_GET['bbp_forum_id'];
		}

		// Return manipulated query_vars
		return $query_vars;
	}
}
endif; // class_exists check

/**
 * Setup bbPress Replies Admin
 *
 * @since bbPress (r2596)
 *
 * @uses BBP_Replies_Admin
 */
function bbp_replies_admin() {
	global $bbp;

	$bbp->admin->replies = new BBP_Replies_Admin();
}

?>
