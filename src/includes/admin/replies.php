<?php

/**
 * bbPress Replies Admin Class
 *
 * @package bbPress
 * @subpackage Administration
 */

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'BBP_Replies_Admin' ) ) :
/**
 * Loads bbPress replies admin area
 *
 * @package bbPress
 * @subpackage Administration
 * @since 2.0.0 bbPress (r2464)
 */
class BBP_Replies_Admin {

	/** Variables *************************************************************/

	/**
	 * @var The post type of this admin component
	 */
	private $post_type = '';

	/** Functions *************************************************************/

	/**
	 * The main bbPress admin loader
	 *
	 * @since 2.0.0 bbPress (r2515)
	 */
	public function __construct() {
		$this->setup_globals();
		$this->setup_actions();
	}

	/**
	 * Setup the admin hooks, actions and filters
	 *
	 * @since 2.0.0 bbPress (r2646)
	 * @since 2.6.0 bbPress (r6101) Added bulk actions
	 *
	 * @access private
	 */
	private function setup_actions() {

		// Messages
		add_filter( 'post_updated_messages', array( $this, 'updated_messages' ) );

		// Reply bulk actions, added in WordPress 4.7, see #WP16031
		if ( bbp_get_major_wp_version() >= 4.7 ) {
			add_filter( 'bulk_actions-edit-reply',        array( $this, 'bulk_actions' ) );
			add_filter( 'handle_bulk_actions-edit-reply', array( $this, 'handle_bulk_actions' ), 10, 3 );
			add_filter( 'bulk_post_updated_messages',     array( $this, 'bulk_post_updated_messages' ), 10, 2 );
		}

		// Reply column headers.
		add_filter( 'manage_' . $this->post_type . '_posts_columns',  array( $this, 'column_headers' ) );

		// Reply columns (in post row)
		add_action( 'manage_' . $this->post_type . '_posts_custom_column',  array( $this, 'column_data' ), 10, 2 );
		add_filter( 'post_row_actions',                                     array( $this, 'row_actions' ), 10, 2 );

		// Reply meta-box actions
		add_action( 'add_meta_boxes', array( $this, 'attributes_metabox' ) );
		add_action( 'add_meta_boxes', array( $this, 'author_metabox'     ) );
		add_action( 'add_meta_boxes', array( $this, 'comments_metabox'   ) );
		add_action( 'save_post',      array( $this, 'save_meta_boxes'    ) );

		// Check if there are any bbp_toggle_reply_* requests on admin_init, also have a message displayed
		add_action( 'load-edit.php', array( $this, 'toggle_reply'        ) );
		add_action( 'load-edit.php', array( $this, 'toggle_reply_notice' ) );

		// Add ability to filter topics and replies per forum
		add_filter( 'restrict_manage_posts', array( $this, 'filter_dropdown'  ) );
		add_filter( 'bbp_request',           array( $this, 'filter_post_rows' ) );

		// Empty spam
		add_filter( 'manage_posts_extra_tablenav', array( $this, 'filter_empty_spam' ) );

		// Contextual Help
		add_action( 'load-edit.php',     array( $this, 'edit_help' ) );
		add_action( 'load-post.php',     array( $this, 'new_help'  ) );
		add_action( 'load-post-new.php', array( $this, 'new_help'  ) );
	}

	/**
	 * Admin globals
	 *
	 * @since 2.0.0 bbPress (r2646)
	 *
	 * @access private
	 */
	private function setup_globals() {
		$this->post_type = bbp_get_reply_post_type();
	}

	/** Contextual Help *******************************************************/

	/**
	 * Contextual help for bbPress reply edit page
	 *
	 * @since 2.0.0 bbPress (r3119)
	 */
	public function edit_help() {

		// Overview
		get_current_screen()->add_help_tab( array(
			'id'		=> 'overview',
			'title'		=> __( 'Overview', 'bbpress' ),
			'content'	=>
				'<p>' . __( 'This screen provides access to all of your replies. You can customize the display of this screen to suit your workflow.', 'bbpress' ) . '</p>'
		) );

		// Screen Content
		get_current_screen()->add_help_tab( array(
			'id'		=> 'screen-content',
			'title'		=> __( 'Screen Content', 'bbpress' ),
			'content'	=>
				'<p>' . __( 'You can customize the display of this screen&#8217;s contents in a number of ways:', 'bbpress' ) . '</p>' .
				'<ul>' .
					'<li>' . __( 'You can hide/display columns based on your needs and decide how many replies to list per screen using the Screen Options tab.',                                                                         'bbpress' ) . '</li>' .
					'<li>' . __( 'You can filter the list of replies by reply status using the text links in the upper left to show All, Published, Draft, Pending, Trashed, or Spam replies. The default view is to show all replies.',  'bbpress' ) . '</li>' .
					'<li>' . __( 'You can view replies in a simple title list or with an excerpt. Choose the view you prefer by clicking on the icons at the top of the list on the right.',                                              'bbpress' ) . '</li>' .
					'<li>' . __( 'You can refine the list to show only replies in a specific forum or from a specific month by using the dropdown menus above the replies list. Click the Filter button after making your selection.', 'bbpress' ) . '</li>' .
				'</ul>'
		) );

		// Available Actions
		get_current_screen()->add_help_tab( array(
			'id'		=> 'action-links',
			'title'		=> __( 'Available Actions', 'bbpress' ),
			'content'	=>
				'<p>' . __( 'Hovering over a row in the replies list will display action links that allow you to manage your reply. You can perform the following actions:', 'bbpress' ) . '</p>' .
				'<ul>' .
					'<li>' . __( '<strong>Edit</strong> takes you to the editing screen for that reply. You can also reach that screen by clicking on the reply title.',                  'bbpress' ) . '</li>' .
					//'<li>' . __( '<strong>Quick Edit</strong> provides inline access to the metadata of your reply, allowing you to update reply details without leaving this screen.', 'bbpress' ) . '</li>' .
					'<li>' . __( '<strong>Trash</strong> removes your reply from this list and places it in the trash, from which you can permanently delete it.',                        'bbpress' ) . '</li>' .
					'<li>' . __( '<strong>Spam</strong> removes your reply from this list and places it in the spam queue, from which you can permanently delete it.',                    'bbpress' ) . '</li>' .
					'<li>' . __( '<strong>View</strong> will take you to your live site to view the reply.',                                                                              'bbpress' ) . '</li>' .
					'<li>' . __( '<strong>Approve</strong> will change the status from pending to publish.',                                                                              'bbpress' ) . '</li>' .
				'</ul>'
		) );

		// Bulk Actions
		get_current_screen()->add_help_tab( array(
			'id'		=> 'bulk-actions',
			'title'		=> __( 'Bulk Actions', 'bbpress' ),
			'content'	=>
				'<p>' . __( 'You can also edit, spam, or move multiple replies to the trash at once. Select the replies you want to act on using the checkboxes, then select the action you want to take from the Bulk Actions menu and click Apply.',           'bbpress' ) . '</p>' .
				'<p>' . __( 'When using Bulk Edit, you can change the metadata (categories, author, etc.) for all selected replies at once. To remove a reply from the grouping, just click the x next to its name in the Bulk Edit area that appears.', 'bbpress' ) . '</p>'
		) );

		// Help Sidebar
		get_current_screen()->set_help_sidebar(
			'<p><strong>' . __( 'For more information:', 'bbpress' ) . '</strong></p>' .
			'<p>' . __( '<a href="https://codex.bbpress.org" target="_blank">bbPress Documentation</a>',    'bbpress' ) . '</p>' .
			'<p>' . __( '<a href="https://bbpress.org/forums/" target="_blank">bbPress Support Forums</a>', 'bbpress' ) . '</p>'
		);
	}

	/**
	 * Contextual help for bbPress reply edit page
	 *
	 * @since 2.0.0 bbPress (r3119)
	 */
	public function new_help() {

		$customize_display = '<p>' . __( 'The title field and the big reply editing Area are fixed in place, but you can reposition all the other boxes using drag and drop, and can minimize or expand them by clicking the title bar of each box. Use the Screen Options tab to unhide more boxes (Excerpt, Send Trackbacks, Custom Fields, Discussion, Slug, Author) or to choose a 1- or 2-column layout for this screen.', 'bbpress' ) . '</p>';

		get_current_screen()->add_help_tab( array(
			'id'      => 'customize-display',
			'title'   => __( 'Customizing This Display', 'bbpress' ),
			'content' => $customize_display,
		) );

		get_current_screen()->add_help_tab( array(
			'id'      => 'title-reply-editor',
			'title'   => __( 'Title and Reply Editor', 'bbpress' ),
			'content' =>
				'<p>' . __( '<strong>Title</strong> - Enter a title for your reply. After you enter a title, you&#8217;ll see the permalink below, which you can edit.', 'bbpress' ) . '</p>' .
				'<p>' . __( '<strong>Reply Editor</strong> - Enter the text for your reply. There are two modes of editing: Visual and HTML. Choose the mode by clicking on the appropriate tab. Visual mode gives you a WYSIWYG editor. Click the last icon in the row to get a second row of controls. The HTML mode allows you to enter raw HTML along with your reply text. You can insert media files by clicking the icons above the reply editor and following the directions. You can go to the distraction-free writing screen via the Fullscreen icon in Visual mode (second to last in the top row) or the Fullscreen button in HTML mode (last in the row). Once there, you can make buttons visible by hovering over the top area. Exit Fullscreen back to the regular reply editor.', 'bbpress' ) . '</p>'
		) );

		$publish_box = '<p>' . __( '<strong>Publish</strong> - You can set the terms of publishing your reply in the Publish box. For Status, Visibility, and Publish (immediately), click on the Edit link to reveal more options. Visibility includes options for password-protecting a reply or making it stay at the top of your blog indefinitely (sticky). Publish (immediately) allows you to set a future or past date and time, so you can schedule a reply to be published in the future or backdate a reply.', 'bbpress' ) . '</p>';

		if ( current_theme_supports( 'reply-thumbnails' ) && post_type_supports( bbp_get_reply_post_type(), 'thumbnail' ) ) {
			$publish_box .= '<p>' . __( '<strong>Featured Image</strong> - This allows you to associate an image with your reply without inserting it. This is usually useful only if your theme makes use of the featured image as a reply thumbnail on the home page, a custom header, etc.', 'bbpress' ) . '</p>';
		}

		get_current_screen()->add_help_tab( array(
			'id'      => 'reply-attributes',
			'title'   => __( 'Reply Attributes', 'bbpress' ),
			'content' =>
				'<p>' . __( 'Select the attributes that your reply should have:', 'bbpress' ) . '</p>' .
				'<ul>' .
					'<li>' . __( '<strong>Forum</strong> dropdown determines the parent forum that the reply belongs to. Select the forum, or leave the default (Use Forum of Topic) to post the reply in forum of the topic.', 'bbpress' ) . '</li>' .
					'<li>' . __( '<strong>Topic</strong> determines the parent topic that the reply belongs to.', 'bbpress' ) . '</li>' .
					'<li>' . __( '<strong>Reply To</strong> determines the threading of the reply.', 'bbpress' ) . '</li>' .
				'</ul>'
		) );

		get_current_screen()->add_help_tab( array(
			'id'      => 'publish-box',
			'title'   => __( 'Publish Box', 'bbpress' ),
			'content' => $publish_box,
		) );

		get_current_screen()->set_help_sidebar(
			'<p><strong>' . __( 'For more information:', 'bbpress' ) . '</strong></p>' .
			'<p>' . __( '<a href="https://codex.bbpress.org" target="_blank">bbPress Documentation</a>',    'bbpress' ) . '</p>' .
			'<p>' . __( '<a href="https://bbpress.org/forums/" target="_blank">bbPress Support Forums</a>', 'bbpress' ) . '</p>'
		);
	}

	/**
	 * Add spam/unspam bulk actions to the bulk action dropdown.
	 *
	 * @since 2.6.0 bbPress (r6101)
	 *
	 * @param array $actions The list of bulk actions.
	 * @return array The filtered list of bulk actions.
	 */
	public function bulk_actions( $actions ) {

		if ( current_user_can( 'moderate' ) ) {
			if ( bbp_get_spam_status_id() === get_query_var( 'post_status' ) ) {
				$actions['unspam'] = esc_html__( 'Unspam', 'bbpress' );
			} else {
				$actions['spam'] = esc_html__( 'Spam', 'bbpress' );
			}
		}

		return $actions;
	}

	/**
	 * Add custom bulk action updated messages for replies.
	 *
	 * @since 2.6.0 bbPress (r6101)
	 *
	 * @param array $bulk_messages Arrays of messages, each keyed by the corresponding post type.
	 * @param array $bulk_counts   Array of item counts for each message, used to build internationalized strings.
	 */
	public function bulk_post_updated_messages( $bulk_messages, $bulk_counts ) {
		$bulk_messages['reply']['updated'] = _n( '%s reply updated.', '%s replies updated.', $bulk_counts['updated'], 'bbpress');
		$bulk_messages['reply']['locked']  = ( 1 === $bulk_counts['locked'] )
			? __( '1 reply not updated, somebody is editing it.', 'bbpress' )
			: _n( '%s reply not updated, somebody is editing it.', '%s replies not updated, somebody is editing them.', $bulk_counts['locked'], 'bbpress' );

		return $bulk_messages;
	}

	/**
	 * Handle spam/unspam bulk actions.
	 *
	 * @since 2.6.0 bbPress (r6101)
	 *
	 * @param string $sendback The sendback URL.
	 * @param string $doaction The action to be taken.
	 * @param array  $post_ids The post IDS to take the action on.
	 * @return string The sendback URL.
	 */
	public function handle_bulk_actions( $sendback, $doaction, $post_ids ) {

		$sendback = remove_query_arg( array( 'spam', 'unspam' ), $sendback );
		$updated = $locked = 0;

		if ( 'spam' === $doaction ) {

			foreach ( (array) $post_ids as $post_id ) {
				if ( ! current_user_can( 'moderate', $post_id ) ) {
					wp_die( esc_html__( 'Sorry, you are not allowed to spam this item.', 'bbpress' ) );
				}

				if ( wp_check_post_lock( $post_id ) ) {
					$locked++;
					continue;
				}

				if ( ! bbp_spam_reply( $post_id ) ) {
					wp_die( esc_html__( 'Error in spamming reply.', 'bbpress' ) );
				}

				$updated++;
			}

			$sendback = add_query_arg( array(
				'updated' => $updated,
				'ids'     => implode( ',', $post_ids ),
				'locked'  => $locked
			), $sendback );

		} elseif ( 'unspam' === $doaction ) {

			foreach ( (array) $post_ids as $post_id ) {
				if ( ! current_user_can( 'moderate', $post_id ) ) {
					wp_die( esc_html__( 'Sorry, you are not allowed to unspam this reply.', 'bbpress' ) );
				}

				if ( wp_check_post_lock( $post_id ) ) {
					$locked++;
					continue;
				}

				if ( ! bbp_unspam_reply( $post_id ) ) {
					wp_die( esc_html__( 'Error in unspamming reply.', 'bbpress' ) );
				}

				$updated++;
			}

			$sendback = add_query_arg( array(
				'updated' => $updated,
				'ids'     => implode( ',', $post_ids ),
				'locked'  => $locked
			), $sendback );
		}

		return $sendback;
	}

	/**
	 * Add the reply attributes meta-box
	 *
	 * @since 2.0.0 bbPress (r2746)
	 */
	public function attributes_metabox() {
		add_meta_box(
			'bbp_reply_attributes',
			esc_html__( 'Reply Attributes', 'bbpress' ),
			'bbp_reply_metabox',
			$this->post_type,
			'side',
			'high'
		);
	}

	/**
	 * Add the author info meta-box
	 *
	 * Allows editing of information about an author
	 *
	 * @since 2.0.0 bbPress (r2828)
	 */
	public function author_metabox() {

		// Bail if post_type is not a reply
		if ( empty( $_GET['action'] ) || ( 'edit' !== $_GET['action'] ) ) {
			return;
		}

		// Add the meta-box
		add_meta_box(
			'bbp_author_metabox',
			esc_html__( 'Author Information', 'bbpress' ),
			'bbp_author_metabox',
			$this->post_type,
			'side',
			'high'
		);
	}

	/**
	 * Remove comments & discussion meta-boxes if comments are not supported
	 *
	 * @since 2.6.0 bbPress (r6186)
	 */
	public function comments_metabox() {
		if ( ! post_type_supports( $this->post_type, 'comments' ) ) {
			remove_meta_box( 'commentstatusdiv', $this->post_type, 'normal' );
			remove_meta_box( 'commentsdiv',      $this->post_type, 'normal' );
		}
	}

	/**
	 * Pass the reply attributes for processing
	 *
	 * @since 2.0.0 bbPress (r2746)
	 *
	 * @param int $reply_id Reply id
	 * @return int Parent id
	 */
	public function save_meta_boxes( $reply_id ) {

		// Bail if doing an autosave
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return $reply_id;
		}

		// Bail if not a post request
		if ( ! bbp_is_post_request() ) {
			return $reply_id;
		}

		// Check action exists
		if ( empty( $_POST['action'] ) ) {
			return $reply_id;
		}

		// Nonce check
		if ( empty( $_POST['bbp_reply_metabox'] ) || ! wp_verify_nonce( $_POST['bbp_reply_metabox'], 'bbp_reply_metabox_save' ) ) {
			return $reply_id;
		}

		// Bail if current user cannot edit this reply
		if ( ! current_user_can( 'edit_reply', $reply_id ) ) {
			return $reply_id;
		}

		// Get the reply meta post values
		$topic_id = ! empty( $_POST['parent_id']    ) ? (int) $_POST['parent_id']    : 0;
		$forum_id = ! empty( $_POST['bbp_forum_id'] ) ? (int) $_POST['bbp_forum_id'] : bbp_get_topic_forum_id( $topic_id );
		$reply_to = ! empty( $_POST['bbp_reply_to'] ) ? (int) $_POST['bbp_reply_to'] : 0;

		// Get reply author data
		$anonymous_data = bbp_filter_anonymous_post_data();
		$author_id      = bbp_get_reply_author_id( $reply_id );
		$is_edit        = ( isset( $_POST['hidden_post_status'] ) && ( $_POST['hidden_post_status'] !== 'draft' ) );

		// Formally update the reply
		bbp_update_reply( $reply_id, $topic_id, $forum_id, $anonymous_data, $author_id, $is_edit, $reply_to );

		// Allow other fun things to happen
		do_action( 'bbp_reply_attributes_metabox_save', $reply_id, $topic_id, $forum_id, $reply_to );
		do_action( 'bbp_author_metabox_save',           $reply_id, $anonymous_data                 );

		return $reply_id;
	}

	/**
	 * Toggle reply
	 *
	 * Handles the admin-side spamming/unspamming of replies
	 *
	 * @since 2.0.0 bbPress (r2740)
	 */
	public function toggle_reply() {

		// Bail if not a topic toggle action
		if ( ! bbp_is_get_request() || empty( $_GET['action'] ) || empty( $_GET['reply_id'] ) ) {
			return;
		}

		// Bail if not an allowed action
		$action = sanitize_key( $_GET['action'] );
		if ( empty( $action ) || ! in_array( $action, $this->get_allowed_action_toggles(), true ) ) {
			return;
		}

		// Get reply and die if empty
		$reply_id = bbp_get_reply_id( $_GET['reply_id'] );
		if ( ! bbp_get_reply( $reply_id ) ) {
			wp_die( esc_html__( 'The reply was not found.', 'bbpress' ) );
		}

		// What is the user doing here?
		if ( ! current_user_can( 'moderate', $reply_id ) ) {
			wp_die( esc_html__( 'You do not have permission to do that.', 'bbpress' ) );
		}

		// Defaults
		$post_data = array( 'ID' => $reply_id );
		$message   = '';
		$success   = false;

		switch ( $action ) {
			case 'bbp_toggle_reply_approve' :
				check_admin_referer( 'approve-reply_' . $reply_id );

				$is_approve = bbp_is_reply_public( $reply_id );
				$message    = ( true === $is_approve )
					? 'unpproved'
					: 'approved';
				$success    = ( true === $is_approve )
					? bbp_unapprove_reply( $reply_id )
					: bbp_approve_reply( $reply_id );

				break;

			case 'bbp_toggle_reply_spam' :
				check_admin_referer( 'spam-reply_' . $reply_id );

				$is_spam = bbp_is_reply_spam( $reply_id );
				$message = ( true === $is_spam )
					? 'unspammed'
					: 'spammed';
				$success = ( true === $is_spam )
					? bbp_unspam_reply( $reply_id )
					: bbp_spam_reply( $reply_id );

				break;
		}

		// Setup the message
		$retval = array(
			'bbp_reply_toggle_notice' => $message,
			'reply_id'                => $reply_id
		);

		// Prepare for failure
		if ( ( false === $success ) || is_wp_error( $success ) ) {
			$retval['failed'] = '1';
		}

		// Filter all message args
		$retval = apply_filters( 'bbp_toggle_reply_action_admin', $retval, $reply_id, $action );

		// Do additional reply toggle actions (admin side)
		do_action( 'bbp_toggle_reply_admin', $success, $post_data, $action, $retval );

		// Redirect back to the reply
		$redirect = add_query_arg( $retval, remove_query_arg( array( 'action', 'reply_id' ) ) );
		bbp_redirect( $redirect );
	}

	/**
	 * Toggle reply notices
	 *
	 * Display the success/error notices from
	 * {@link BBP_Admin::toggle_reply()}
	 *
	 * @since 2.0.0 bbPress (r2740)
	 */
	public function toggle_reply_notice() {

		// Bail if missing reply toggle action
		if ( ! bbp_is_get_request() || empty( $_GET['reply_id'] ) || empty( $_GET['bbp_reply_toggle_notice'] ) ) {
			return;
		}

		// Bail if not an allowed notice
		$notice = sanitize_key( $_GET['bbp_reply_toggle_notice'] );
		if ( empty( $notice ) || ! in_array( $notice, $this->get_allowed_notice_toggles(), true ) ) {
			return;
		}

		// Bail if no reply_id or notice
		$reply_id = bbp_get_reply_id( $_GET['reply_id'] );
		if ( empty( $reply_id ) ) {
			return;
		}

		// Bail if reply is missing
		if ( ! bbp_get_reply( $reply_id ) ) {
			return;
		}

		// Use the title in the responses
		$reply_title = bbp_get_reply_title( $reply_id );
		$is_failure  = ! empty( $_GET['failed'] );
		$message     = '';

		switch ( $notice ) {
			case 'spammed' :
				$message = ( $is_failure === true )
					? sprintf( esc_html__( 'There was a problem marking the reply "%1$s" as spam.', 'bbpress' ), $reply_title )
					: sprintf( esc_html__( 'Reply "%1$s" successfully marked as spam.',             'bbpress' ), $reply_title );
				break;

			case 'unspammed' :
				$message = ( $is_failure === true )
					? sprintf( esc_html__( 'There was a problem unmarking the reply "%1$s" as spam.', 'bbpress' ), $reply_title )
					: sprintf( esc_html__( 'Reply "%1$s" successfully unmarked as spam.',             'bbpress' ), $reply_title );
				break;

			case 'approved' :
				$message = ( $is_failure === true )
					? sprintf( esc_html__( 'There was a problem approving the reply "%1$s".', 'bbpress' ), $reply_title )
					: sprintf( esc_html__( 'Reply "%1$s" successfully approved.',             'bbpress' ), $reply_title );
				break;

			case 'unapproved' :
				$message = ( $is_failure === true )
					? sprintf( esc_html__( 'There was a problem unapproving the reply "%1$s".', 'bbpress' ), $reply_title )
					: sprintf( esc_html__( 'Reply "%1$s" successfully unapproved.',             'bbpress' ), $reply_title );
				break;
		}

		// Do additional reply toggle notice filters (admin side)
		$message = apply_filters( 'bbp_toggle_reply_notice_admin', $message, $reply_id, $notice, $is_failure );
		$class   = ( $is_failure === true )
			? 'error'
			: 'updated';

		// Add the notice
		bbp_admin()->add_notice( $message, $class, true );
	}

	/**
	 * Returns an array of keys used to sort row actions
	 *
	 * @since 2.6.0 bbPress (r6771)
	 *
	 * @return array
	 */
	private function get_row_action_sort_order() {

		// Filter & return
		return (array) apply_filters( 'bbp_admin_reply_row_action_sort_order', array(
			'edit',
			'approved',
			'unapproved',
			'spam',
			'unspam',
			'trash',
			'untrash',
			'delete',
			'view'
		) );
	}

	/**
	 * Returns an array of notice toggles
	 *
	 * @since 2.6.0 bbPress (r6396)
	 *
	 * @return array
	 */
	private function get_allowed_notice_toggles() {

		// Filter & return
		return apply_filters( 'bbp_admin_replies_allowed_notice_toggles', array(
			'spammed',
			'unspammed',
			'approved',
			'unapproved'
		) );
	}

	/**
	 * Returns an array of notice toggles
	 *
	 * @since 2.6.0 bbPress (r6396)
	 *
	 * @return array
	 */
	private function get_allowed_action_toggles() {

		// Filter & return
		return apply_filters( 'bbp_admin_replies_allowed_action_toggles', array(
			'bbp_toggle_reply_spam',
			'bbp_toggle_reply_approve'
		) );
	}

	/**
	 * Manage the column headers for the replies page
	 *
	 * @since 2.0.0 bbPress (r2577)
	 *
	 * @param array $columns The columns
	 *
	 * @return array $columns bbPress reply columns
	 */
	public function column_headers( $columns ) {
		$columns = array(
			'cb'                => '<input type="checkbox" />',
			'title'             => esc_html__( 'Title',   'bbpress' ),
			'bbp_reply_forum'   => esc_html__( 'Forum',   'bbpress' ),
			'bbp_reply_topic'   => esc_html__( 'Topic',   'bbpress' ),
			'bbp_reply_author'  => esc_html__( 'Author',  'bbpress' ),
			'bbp_reply_created' => esc_html__( 'Created', 'bbpress' ),
		);

		// Filter & return
		return apply_filters( 'bbp_admin_replies_column_headers', $columns );
	}

	/**
	 * Print extra columns for the replies page
	 *
	 * @since 2.0.0 bbPress (r2577)
	 *
	 * @param string $column Column
	 * @param int $reply_id reply id
	 */
	public function column_data( $column, $reply_id ) {

		// Get topic ID
		$topic_id = bbp_get_reply_topic_id( $reply_id );

		// Populate Column Data
		switch ( $column ) {

			// Topic
			case 'bbp_reply_topic' :

				// Get title
				$topic_title = ! empty( $topic_id )
					? bbp_get_topic_title( $topic_id )
					: '';

				// Output topic name
				if ( ! empty( $topic_title ) ) {
					echo $topic_title;

				// Output dash
				} else {
					?>
					<span aria-hidden="true">&mdash;</span>
					<span class="screen-reader-text"><?php esc_html_e( 'No topic', 'bbpress' ); ?></span>
					<?php
				}

				break;

			// Forum
			case 'bbp_reply_forum' :

				// Get Forum ID's
				$reply_forum_id = bbp_get_reply_forum_id( $reply_id );
				$topic_forum_id = bbp_get_topic_forum_id( $topic_id );

				// Forum Title
				$forum_title = ! empty( $reply_forum_id )
					? bbp_get_forum_title( $reply_forum_id )
					: '';

				// Alert capable users of reply forum mismatch
				if ( $reply_forum_id !== $topic_forum_id ) {
					if ( current_user_can( 'edit_others_replies' ) || current_user_can( 'moderate', $reply_id ) ) {
						$forum_title .= '<div class="attention">' . esc_html__( '(Mismatch)', 'bbpress' ) . '</div>';
					}
				}

				// Output forum name
				if ( ! empty( $forum_title ) ) {
					echo $forum_title;

				// Reply has no forum
				} else {
					?>
					<span aria-hidden="true">&mdash;</span>
					<span class="screen-reader-text"><?php esc_html_e( 'No forum', 'bbpress' ); ?></span>
					<?php
				}

				break;

			// Author
			case 'bbp_reply_author' :
				bbp_reply_author_display_name( $reply_id );
				break;

			// Freshness
			case 'bbp_reply_created':

				// Output last activity time and date
				printf( '%1$s <br /> %2$s',
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
	 * @since 2.0.0 bbPress (r2577)
	 *
	 * @param array  $actions Actions
	 * @param object $reply   Reply object
	 *
	 * @return array $actions Actions
	 */
	public function row_actions( $actions = array(), $reply = false ) {

		// Disable quick edit (too much to do here)
		unset( $actions['inline hide-if-no-js'] );

		// View link
		$view_link = bbp_get_reply_url( $reply->ID );

		// Maybe add view=all
		if ( ! in_array( $reply->post_status, array( bbp_get_closed_status_id(), bbp_get_public_status_id() ), true ) ) {
			$view_link = bbp_add_view_all( $view_link, true );
		}

		// Reply view links to topic
		$actions['view'] = '<a href="' . esc_url( $view_link ) . '" title="' . esc_attr( sprintf( __( 'View &#8220;%s&#8221;', 'bbpress' ), bbp_get_reply_title( $reply->ID ) ) ) . '" rel="permalink">' . esc_html__( 'View', 'bbpress' ) . '</a>';

		// User cannot view replies in trash
		if ( ( bbp_get_trash_status_id() === $reply->post_status ) && ! current_user_can( 'view_trash' ) ) {
			unset( $actions['view'] );
		}

		// Only show the actions if the user is capable of viewing them
		if ( current_user_can( 'moderate', $reply->ID ) ) {

			// Show the 'approve' link on non-published posts only and 'unapprove' on published posts only
			$approve_uri = wp_nonce_url( add_query_arg( array( 'reply_id' => $reply->ID, 'action' => 'bbp_toggle_reply_approve' ), remove_query_arg( array( 'bbp_reply_toggle_notice', 'reply_id', 'failed', 'super' ) ) ), 'approve-reply_' . $reply->ID );
			if ( bbp_is_reply_public( $reply->ID ) ) {
				$actions['unapproved'] = '<a href="' . esc_url( $approve_uri ) . '" title="' . esc_attr__( 'Unapprove this reply', 'bbpress' ) . '">' . _x( 'Unapprove', 'Unapprove reply', 'bbpress' ) . '</a>';
			} else {
				$actions['approved']   = '<a href="' . esc_url( $approve_uri ) . '" title="' . esc_attr__( 'Approve this reply',   'bbpress' ) . '">' . _x( 'Approve',   'Approve reply',   'bbpress' ) . '</a>';
			}

			// Show the 'spam' link on published and pending replies and 'not spam' on spammed replies
			if ( in_array( $reply->post_status, array( bbp_get_public_status_id(), bbp_get_trash_status_id(), bbp_get_pending_status_id(), bbp_get_spam_status_id() ), true ) ) {
				$spam_uri  = wp_nonce_url( add_query_arg( array( 'reply_id' => $reply->ID, 'action' => 'bbp_toggle_reply_spam' ), remove_query_arg( array( 'bbp_reply_toggle_notice', 'reply_id', 'failed', 'super' ) ) ), 'spam-reply_'  . $reply->ID );
				if ( ! bbp_is_reply_spam( $reply->ID ) ) {
					$actions['spam'] = '<a href="' . esc_url( $spam_uri ) . '" title="' . esc_attr__( 'Mark this reply as spam',    'bbpress' ) . '">' . esc_html__( 'Spam',     'bbpress' ) . '</a>';
				} else {
					$actions['unspam'] = '<a href="' . esc_url( $spam_uri ) . '" title="' . esc_attr__( 'Mark the reply as not spam', 'bbpress' ) . '">' . esc_html__( 'Not Spam', 'bbpress' ) . '</a>';
				}
			}
		}

		// Trash
		if ( current_user_can( 'delete_reply', $reply->ID ) ) {
			$trash_days = bbp_get_trash_days( bbp_get_reply_post_type() );

			if ( bbp_get_trash_status_id() === $reply->post_status ) {
				$post_type_object   = get_post_type_object( bbp_get_reply_post_type() );
				$actions['untrash'] = "<a title='" . esc_attr__( 'Restore this item from the Trash', 'bbpress' ) . "' href='" . esc_url( wp_nonce_url( admin_url( sprintf( $post_type_object->_edit_link . '&amp;action=untrash', $reply->ID ) ), 'untrash-post_' . $reply->ID ) ) . "'>" . esc_html__( 'Restore', 'bbpress' ) . "</a>";
			} elseif ( ! empty( $trash_days ) ) {
				$actions['trash'] = "<a class='submitdelete' title='" . esc_attr__( 'Move this item to the Trash', 'bbpress' ) . "' href='" . esc_url( get_delete_post_link( $reply->ID ) ) . "'>" . esc_html__( 'Trash', 'bbpress' ) . "</a>";
			}

			if ( ( bbp_get_trash_status_id() === $reply->post_status ) || empty( $trash_days ) ) {
				$actions['delete'] = "<a class='submitdelete' title='" . esc_attr__( 'Delete this item permanently', 'bbpress' ) . "' href='" . esc_url( get_delete_post_link( $reply->ID, '', true ) ) . "'>" . esc_html__( 'Delete Permanently', 'bbpress' ) . "</a>";
			}
		}

		// Sort & return
		return $this->sort_row_actions( $actions );
	}

	/**
	 * Sort row actions by key
	 *
	 * @since 2.6.0
	 *
	 * @param array $actions
	 *
	 * @return array
	 */
	private function sort_row_actions( $actions = array() ) {

		// Return value
		$retval = array();

		// Known row actions, in sort order
		$known_actions = $this->get_row_action_sort_order();

		// Sort known actions, and keep any unknown ones
		foreach ( $known_actions as $key ) {
			if ( isset( $actions[ $key ] ) ) {
				$retval[ $key ] = $actions[ $key ];
				unset( $actions[ $key ] );
			}
		}

		// Combine & return
		return $retval + $actions;
	}

	/**
	 * Add forum dropdown to topic and reply list table filters
	 *
	 * @since 2.0.0 bbPress (r2991)
	 *
	 * @return bool False. If post type is not topic or reply
	 */
	public function filter_dropdown() {

		// Get which forum is selected
		$selected = ! empty( $_GET['bbp_forum_id'] )
			? (int) $_GET['bbp_forum_id']
			: 0;

		// Show the forums dropdown
		bbp_dropdown( array(
			'selected'  => $selected,
			'show_none' => esc_html__( 'In all forums', 'bbpress' )
		) );
	}

	/**
	 * Add "Empty Spam" button for moderators
	 *
	 * @since 2.6.0 bbPress (r6791)
	 */
	public function filter_empty_spam() {

		// Bail if not viewing spam
		if ( empty( $_GET['post_status'] ) || ( bbp_get_spam_status_id() !== $_GET['post_status'] ) && current_user_can( 'moderate' ) ) {
			return;
		}

		?>

		<div class="alignleft actions"><?php

			// Output the nonce & button
			wp_nonce_field( 'bulk-destroy', '_destroy_nonce' );
			submit_button(
				esc_attr__( 'Empty Spam', 'bbpress' ),
				'button-secondary apply',
				'delete_all',
				false
			);

		?></div><?php
	}

	/**
	 * Adjust the request query and include the forum id
	 *
	 * @since 2.0.0 bbPress (r2991)
	 *
	 * @param array $query_vars Query variables from {@link WP_Query}
	 * @return array Processed Query Vars
	 */
	public function filter_post_rows( $query_vars ) {

		// Add post_parent query_var if one is present
		if ( ! empty( $_GET['bbp_forum_id'] ) ) {
			$query_vars['meta_key']   = '_bbp_forum_id';
			$query_vars['meta_type']  = 'NUMERIC';
			$query_vars['meta_value'] = $_GET['bbp_forum_id'];
		}

		// Return manipulated query_vars
		return $query_vars;
	}

	/**
	 * Custom user feedback messages for reply post type
	 *
	 * @since 2.0.0 bbPress (r3080)
	 *
	 * @global int $post_ID
	 *
	 * @param array $messages
	 *
	 * @return array
	 */
	public function updated_messages( $messages ) {
		global $post_ID;

		// URL for the current topic
		$topic_url = bbp_get_topic_permalink( bbp_get_reply_topic_id( $post_ID ) );

		// Current reply's post_date
		$post_date = bbp_get_global_post_field( 'post_date', 'raw' );

		// Messages array
		$messages[ $this->post_type ] = array(
			0 =>  '', // Left empty on purpose

			// Updated
			1 =>  sprintf(
				'%1$s <a href="%2$s">%3$s</a>',
				esc_html__( 'Reply updated.', 'bbpress' ),
				$topic_url,
				esc_html__( 'View topic', 'bbpress' )
			),

			// Custom field updated
			2 => esc_html__( 'Custom field updated.', 'bbpress' ),

			// Custom field deleted
			3 => esc_html__( 'Custom field deleted.', 'bbpress' ),

			// Reply updated
			4 => esc_html__( 'Reply updated.', 'bbpress' ),

			// Restored from revision
			// translators: %s: date and time of the revision
			5 => isset( $_GET['revision'] )
					? sprintf( esc_html__( 'Reply restored to revision from %s', 'bbpress' ), wp_post_revision_title( (int) $_GET['revision'], false ) )
					: false,

			// Reply created
			6 => sprintf(
				'%1$s <a href="%2$s">%3$s</a>',
				esc_html__( 'Reply created.', 'bbpress' ),
				$topic_url,
				esc_html__( 'View topic', 'bbpress' )
			),

			// Reply saved
			7 => esc_html__( 'Reply saved.', 'bbpress' ),

			// Reply submitted
			8 => sprintf(
				'%1$s <a href="%2$s" target="_blank">%3$s</a>',
				esc_html__( 'Reply submitted.', 'bbpress' ),
				esc_url( add_query_arg( 'preview', 'true', $topic_url ) ),
				esc_html__( 'Preview topic', 'bbpress' )
			),

			// Reply scheduled
			9 => sprintf(
				'%1$s <a target="_blank" href="%2$s">%3$s</a>',
				sprintf(
					esc_html__( 'Reply scheduled for: %s.', 'bbpress' ),
					// translators: Publish box date format, see http://php.net/date
					'<strong>' . date_i18n( __( 'M j, Y @ G:i', 'bbpress' ), strtotime( $post_date ) ) . '</strong>'
				),
				$topic_url,
				esc_html__( 'Preview topic', 'bbpress' )
			),

			// Reply draft updated
			10 => sprintf(
				'%1$s <a href="%2$s" target="_blank">%3$s</a>',
				esc_html__( 'Reply draft updated.', 'bbpress' ),
				esc_url( add_query_arg( 'preview', 'true', $topic_url ) ),
				esc_html__( 'Preview topic', 'bbpress' )
			),
		);

		return $messages;
	}
}
endif; // class_exists check

/**
 * Setup bbPress Replies Admin
 *
 * This is currently here to make hooking and unhooking of the admin UI easy.
 * It could use dependency injection in the future, but for now this is easier.
 *
 * @since 2.0.0 bbPress (r2596)
 *
 * @param WP_Screen $current_screen Current screen object
 */
function bbp_admin_replies( $current_screen ) {

	// Bail if not a forum screen
	if ( empty( $current_screen->post_type ) || ( bbp_get_reply_post_type() !== $current_screen->post_type ) ) {
		return;
	}

	// Init the replies admin
	bbp_admin()->replies = new BBP_Replies_Admin();
}
