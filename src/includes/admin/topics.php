<?php

/**
 * bbPress Topics Admin Class
 *
 * @package bbPress
 * @subpackage Administration
 */

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'BBP_Topics_Admin' ) ) :
/**
 * Loads bbPress topics admin area
 *
 * @package bbPress
 * @subpackage Administration
 * @since 2.0.0 bbPress (r2464)
 */
class BBP_Topics_Admin {

	/** Variables *************************************************************/

	/**
	 * @var The post type of this admin component
	 */
	private $post_type = '';

	/** Functions *************************************************************/

	/**
	 * The main bbPress topics admin loader
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

		// Topic bulk actions, added in WordPress 4.7, see #WP16031.
		if ( bbp_get_major_wp_version() >= 4.7 ) {
			add_filter( 'bulk_actions-edit-topic',        array( $this, 'bulk_actions' ) );
			add_filter( 'handle_bulk_actions-edit-topic', array( $this, 'handle_bulk_actions' ), 10, 3 );
			add_filter( 'bulk_post_updated_messages',     array( $this, 'bulk_post_updated_messages' ), 10, 2 );
		}

		// Topic column headers.
		add_filter( 'manage_' . $this->post_type . '_posts_columns',        array( $this, 'column_headers' ) );

		// Topic columns (in post row)
		add_action( 'manage_' . $this->post_type . '_posts_custom_column',  array( $this, 'column_data' ), 10, 2 );
		add_filter( 'post_row_actions',                                     array( $this, 'row_actions' ), 10, 2 );

		// Topic meta-box actions
		add_action( 'add_meta_boxes', array( $this, 'attributes_metabox'    ) );
		add_action( 'add_meta_boxes', array( $this, 'author_metabox'        ) );
		add_action( 'add_meta_boxes', array( $this, 'replies_metabox'       ) );
		add_action( 'add_meta_boxes', array( $this, 'engagements_metabox'   ) );
		add_action( 'add_meta_boxes', array( $this, 'favorites_metabox'     ) );
		add_action( 'add_meta_boxes', array( $this, 'subscriptions_metabox' ) );
		add_action( 'add_meta_boxes', array( $this, 'comments_metabox'      ) );
		add_action( 'save_post',      array( $this, 'save_meta_boxes'       ) );

		// Check if there are any bbp_toggle_topic_* requests on admin_init, also have a message displayed
		add_action( 'load-edit.php',  array( $this, 'toggle_topic'        ) );
		add_action( 'load-edit.php',  array( $this, 'toggle_topic_notice' ) );

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
		$this->post_type = bbp_get_topic_post_type();
	}

	/** Contextual Help *******************************************************/

	/**
	 * Contextual help for bbPress topic edit page
	 *
	 * @since 2.0.0 bbPress (r3119)
	 */
	public function edit_help() {

		// Overview
		get_current_screen()->add_help_tab( array(
			'id'		=> 'overview',
			'title'		=> __( 'Overview', 'bbpress' ),
			'content'	=>
				'<p>' . __( 'This screen displays the individual topics on your site. You can customize the display of this screen to suit your workflow.', 'bbpress' ) . '</p>'
		) );

		// Screen Content
		get_current_screen()->add_help_tab( array(
			'id'		=> 'screen-content',
			'title'		=> __( 'Screen Content', 'bbpress' ),
			'content'	=>
				'<p>' . __( 'You can customize the display of this screen&#8217;s contents in a number of ways:', 'bbpress' ) . '</p>' .
				'<ul>' .
					'<li>' . __( 'You can hide/display columns based on your needs and decide how many topics to list per screen using the Screen Options tab.',                                                                               'bbpress' ) . '</li>' .
					'<li>' . __( 'You can filter the list of topics by topic status using the text links in the upper left to show All, Published, Draft, Pending, Trashed, Closed, or Spam  topics. The default view is to show all topics.', 'bbpress' ) . '</li>' .
					'<li>' . __( 'You can view topics in a simple title list or with an excerpt. Choose the view you prefer by clicking on the icons at the top of the list on the right.',                                                    'bbpress' ) . '</li>' .
					'<li>' . __( 'You can refine the list to show only topics in a specific forum or from a specific month by using the dropdown menus above the topics list. Click the Filter button after making your selection.',                 'bbpress' ) . '</li>' .
				'</ul>'
		) );

		// Available Actions
		get_current_screen()->add_help_tab( array(
			'id'		=> 'action-links',
			'title'		=> __( 'Available Actions', 'bbpress' ),
			'content'	=>
				'<p>' . __( 'Hovering over a row in the topics list will display action links that allow you to manage your topic. You can perform the following actions:', 'bbpress' ) . '</p>' .
				'<ul>' .
					'<li>' . __( '<strong>Edit</strong> takes you to the editing screen for that topic. You can also reach that screen by clicking on the topic title.',                            'bbpress' ) . '</li>' .
					'<li>' . __( '<strong>Stick</strong> will keep the selected topic &#8217;pinned&#8217; to the top the parent forum topic list.',                                                'bbpress' ) . '</li>' .
					'<li>' . __( '<strong>Stick <em>(to front)</em></strong> will keep the selected topic &#8217;pinned&#8217; to the top of ALL forums and be visable in any forums topics list.', 'bbpress' ) . '</li>' .
					'<li>' . __( '<strong>Approve</strong> will change the status from pending to publish.',                                                                                        'bbpress' ) . '</li>' .
					'<li>' . __( '<strong>Close</strong> will mark the selected topic as &#8217;closed&#8217; and disable the ability to post new replies to it.',                                  'bbpress' ) . '</li>' .
					'<li>' . __( '<strong>Spam</strong> removes your topic from this list and places it in the spam queue, from which you can permanently delete it.',                              'bbpress' ) . '</li>' .
					'<li>' . __( '<strong>Trash</strong> removes your topic from this list and places it in the trash, from which you can permanently delete it.',                                  'bbpress' ) . '</li>' .
					'<li>' . __( '<strong>View</strong> will take you to your live site to view the topic.',                                                                                        'bbpress' ) . '</li>' .
				'</ul>'
		) );

		// Bulk Actions
		get_current_screen()->add_help_tab( array(
			'id'		=> 'bulk-actions',
			'title'		=> __( 'Bulk Actions', 'bbpress' ),
			'content'	=>
				'<p>' . __( 'You can also edit, spam, or move multiple topics to the trash at once. Select the topics you want to act on using the checkboxes, then select the action you want to take from the Bulk Actions menu and click Apply.',           'bbpress' ) . '</p>' .
				'<p>' . __( 'When using Bulk Edit, you can change the metadata (categories, author, etc.) for all selected topics at once. To remove a topic from the grouping, just click the x next to its name in the Bulk Edit area that appears.', 'bbpress' ) . '</p>'
		) );

		// Help Sidebar
		get_current_screen()->set_help_sidebar(
			'<p><strong>' . __( 'For more information:', 'bbpress' ) . '</strong></p>' .
			'<p>' . __( '<a href="https://codex.bbpress.org" target="_blank">bbPress Documentation</a>',     'bbpress' ) . '</p>' .
			'<p>' . __( '<a href="https://bbpress.org/forums/" target="_blank">bbPress Support Forums</a>',  'bbpress' ) . '</p>'
		);
	}

	/**
	 * Contextual help for bbPress topic edit page
	 *
	 * @since 2.0.0 bbPress (r3119)
	 */
	public function new_help() {

		$customize_display = '<p>' . __( 'The title field and the big topic editing Area are fixed in place, but you can reposition all the other boxes using drag and drop, and can minimize or expand them by clicking the title bar of each box. Use the Screen Options tab to unhide more boxes (Excerpt, Send Trackbacks, Custom Fields, Discussion, Slug, Author) or to choose a 1- or 2-column layout for this screen.', 'bbpress' ) . '</p>';

		get_current_screen()->add_help_tab( array(
			'id'      => 'customize-display',
			'title'   => __( 'Customizing This Display', 'bbpress' ),
			'content' => $customize_display,
		) );

		get_current_screen()->add_help_tab( array(
			'id'      => 'title-topic-editor',
			'title'   => __( 'Title and Topic Editor', 'bbpress' ),
			'content' =>
				'<p>' . __( '<strong>Title</strong> - Enter a title for your topic. After you enter a title, you&#8217;ll see the permalink below, which you can edit.', 'bbpress' ) . '</p>' .
				'<p>' . __( '<strong>Topic Editor</strong> - Enter the text for your topic. There are two modes of editing: Visual and HTML. Choose the mode by clicking on the appropriate tab. Visual mode gives you a WYSIWYG editor. Click the last icon in the row to get a second row of controls. The HTML mode allows you to enter raw HTML along with your topic text. You can insert media files by clicking the icons above the topic editor and following the directions. You can go to the distraction-free writing screen via the Fullscreen icon in Visual mode (second to last in the top row) or the Fullscreen button in HTML mode (last in the row). Once there, you can make buttons visible by hovering over the top area. Exit Fullscreen back to the regular topic editor.', 'bbpress' ) . '</p>'
		) );

		$publish_box = '<p>' . __( '<strong>Publish</strong> - You can set the terms of publishing your topic in the Publish box. For Status, Visibility, and Publish (immediately), click on the Edit link to reveal more options. Visibility includes options for password-protecting a topic or making it stay at the top of your blog indefinitely (sticky). Publish (immediately) allows you to set a future or past date and time, so you can schedule a topic to be published in the future or backdate a topic.', 'bbpress' ) . '</p>';

		if ( current_theme_supports( 'topic-thumbnails' ) && post_type_supports( bbp_get_topic_post_type(), 'thumbnail' ) ) {
			$publish_box .= '<p>' . __( '<strong>Featured Image</strong> - This allows you to associate an image with your topic without inserting it. This is usually useful only if your theme makes use of the featured image as a topic thumbnail on the home page, a custom header, etc.', 'bbpress' ) . '</p>';
		}

		get_current_screen()->add_help_tab( array(
			'id'      => 'topic-attributes',
			'title'   => __( 'Topic Attributes', 'bbpress' ),
			'content' =>
				'<p>' . __( 'Select the attributes that your topic should have:', 'bbpress' ) . '</p>' .
				'<ul>' .
					'<li>' . __( '<strong>Forum</strong> dropdown determines the parent forum that the topic belongs to. Select the forum or category from the dropdown, or leave the default "No forum" to post the topic without an assigned forum.', 'bbpress' ) . '</li>' .
					'<li>' . __( '<strong>Topic Type</strong> dropdown indicates the sticky status of the topic. Selecting the super sticky option would stick the topic to the front of your forums, i.e. the topic index, sticky option would stick the topic to its respective forum. Selecting normal would not stick the topic anywhere.', 'bbpress' ) . '</li>' .
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
	 * Add custom bulk action updated messages for topics.
	 *
	 * @since 2.6.0 bbPress (r6101)
	 *
	 * @param array $bulk_messages Arrays of messages, each keyed by the corresponding post type.
	 * @param array $bulk_counts   Array of item counts for each message, used to build internationalized strings.
	 */
	public function bulk_post_updated_messages( $bulk_messages, $bulk_counts ) {
		$bulk_messages['topic']['updated'] = _n( '%s topic updated.', '%s topics updated.', $bulk_counts['updated'], 'bbpress' );
		$bulk_messages['topic']['locked']  = ( 1 === $bulk_counts['locked'] )
			? __( '1 topic not updated, somebody is editing it.', 'bbpress' )
			: _n( '%s topic not updated, somebody is editing it.', '%s topics not updated, somebody is editing them.', $bulk_counts['locked'], 'bbpress' );

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

				if ( ! bbp_spam_topic( $post_id ) ) {
					wp_die( esc_html__( 'Error in spamming topic.', 'bbpress' ) );
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
					wp_die( esc_html__( 'Sorry, you are not allowed to unspam this topic.', 'bbpress' ) );
				}

				if ( wp_check_post_lock( $post_id ) ) {
					$locked++;
					continue;
				}

				if ( ! bbp_unspam_topic( $post_id ) ) {
					wp_die( esc_html__( 'Error in unspamming topic.', 'bbpress' ) );
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
	 * Add the topic attributes meta-box
	 *
	 * @since 2.0.0 bbPress (r2744)
	 */
	public function attributes_metabox() {
		add_meta_box(
			'bbp_topic_attributes',
			esc_html__( 'Topic Attributes', 'bbpress' ),
			'bbp_topic_metabox',
			$this->post_type,
			'side',
			'high'
		);
	}

	/**
	 * Add the author info meta-box
	 *
	 * @since 2.0.0 bbPress (r2828)
	 */
	public function author_metabox() {

		// Bail if post_type is not a topic
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
	 * Add the replies meta-box
	 *
	 * Allows viewing & moderating of replies to a topic, based on the way
	 * comments are visible on a blog post.
	 *
	 * @since 2.6.0 bbPress (r5886)
	 */
	public function replies_metabox() {

		// Bail if post_type is not a reply
		if ( empty( $_GET['action'] ) || ( 'edit' !== $_GET['action'] ) ) {
			return;
		}

		// Add the meta-box
		add_meta_box(
			'bbp_topic_replies_metabox',
			esc_html__( 'Replies', 'bbpress' ),
			'bbp_topic_replies_metabox',
			$this->post_type,
			'normal',
			'high'
		);
	}

	/**
	 * Add the engagements meta-box
	 *
	 * Allows viewing of users who have engaged in a topic.
	 *
	 * @since 2.6.0 bbPress (r6333)
	 */
	public function engagements_metabox() {

		// Bail when creating a new topic
		if ( empty( $_GET['action'] ) || ( 'edit' !== $_GET['action'] ) ) {
			return;
		}

		// Bail if no engagements
		if ( ! bbp_is_engagements_active() ) {
			return;
		}

		// Add the meta-box
		add_meta_box(
			'bbp_topic_engagements_metabox',
			esc_html__( 'Engagements', 'bbpress' ),
			'bbp_topic_engagements_metabox',
			$this->post_type,
			'side',
			'low'
		);
	}

	/**
	 * Add the favorites meta-box
	 *
	 * Allows viewing of users who have favorited a topic.
	 *
	 * @since 2.6.0 bbPress (r6197)
	 */
	public function favorites_metabox() {

		// Bail if post_type is not a reply
		if ( empty( $_GET['action'] ) || ( 'edit' !== $_GET['action'] ) ) {
			return;
		}

		// Bail if no favorites
		if ( ! bbp_is_favorites_active() ) {
			return;
		}

		// Add the meta-box
		add_meta_box(
			'bbp_topic_favorites_metabox',
			esc_html__( 'Favorites', 'bbpress' ),
			'bbp_topic_favorites_metabox',
			$this->post_type,
			'normal',
			'high'
		);
	}

	/**
	 * Add the subscriptions meta-box
	 *
	 * Allows viewing of users who have subscribed to a topic.
	 *
	 * @since 2.6.0 bbPress (r6197)
	 */
	public function subscriptions_metabox() {

		// Bail if post_type is not a reply
		if ( empty( $_GET['action'] ) || ( 'edit' !== $_GET['action'] ) ) {
			return;
		}

		// Bail if no subscriptions
		if ( ! bbp_is_subscriptions_active() ) {
			return;
		}

		// Add the meta-box
		add_meta_box(
			'bbp_topic_subscriptions_metabox',
			esc_html__( 'Subscriptions', 'bbpress' ),
			'bbp_topic_subscriptions_metabox',
			$this->post_type,
			'normal',
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
	 * Pass the topic attributes for processing
	 *
	 * @since 2.0.0 bbPress (r2746)
	 *
	 * @param int $topic_id Topic id
	 * @return int Parent id
	 */
	public function save_meta_boxes( $topic_id ) {

		// Bail if doing an autosave
		if ( bbp_doing_autosave() ) {
			return $topic_id;
		}

		// Bail if not a post request
		if ( ! bbp_is_post_request() ) {
			return $topic_id;
		}

		// Check action exists
		if ( empty( $_POST['action'] ) ) {
			return $topic_id;
		}

		// Nonce check
		if ( empty( $_POST['bbp_topic_metabox'] ) || ! wp_verify_nonce( $_POST['bbp_topic_metabox'], 'bbp_topic_metabox_save' ) ) {
			return $topic_id;
		}

		// Bail if current user cannot edit this topic
		if ( ! current_user_can( 'edit_topic', $topic_id ) ) {
			return $topic_id;
		}

		// Get the forum ID
		$forum_id = ! empty( $_POST['parent_id'] ) ? (int) $_POST['parent_id'] : 0;

		// Get topic author data
		$anonymous_data = bbp_filter_anonymous_post_data();
		$author_id      = bbp_get_topic_author_id( $topic_id );
		$is_edit        = ( isset( $_POST['hidden_post_status'] ) && ( $_POST['hidden_post_status'] !== 'draft' ) );

		// Formally update the topic
		bbp_update_topic( $topic_id, $forum_id, $anonymous_data, $author_id, $is_edit );

		// Allow other fun things to happen
		do_action( 'bbp_topic_attributes_metabox_save', $topic_id, $forum_id       );
		do_action( 'bbp_author_metabox_save',           $topic_id, $anonymous_data );

		return $topic_id;
	}

	/**
	 * Toggle topic
	 *
	 * Handles the admin-side opening/closing, sticking/unsticking and
	 * spamming/unspamming of topics
	 *
	 * @since 2.0.0 bbPress (r2727)
	 */
	public function toggle_topic() {

		// Bail if not a topic toggle action
		if ( ! bbp_is_get_request() || empty( $_GET['action'] ) || empty( $_GET['topic_id'] ) ) {
			return;
		}

		// Bail if not an allowed action
		$action = sanitize_key( $_GET['action'] );
		if ( empty( $action ) || ! in_array( $action, $this->get_allowed_action_toggles(), true ) ) {
			return;
		}

		// Bail if topic is missing
		$topic_id = bbp_get_topic_id( $_GET['topic_id'] );
		if ( ! bbp_get_topic( $topic_id ) ) {
			wp_die( esc_html__( 'The topic was not found.', 'bbpress' ) );
		}

		// What is the user doing here?
		if ( ! current_user_can( 'moderate', $topic_id ) ) {
			wp_die( esc_html__( 'You do not have permission to do that.', 'bbpress' ) );
		}

		// Defaults
		$post_data = array( 'ID' => $topic_id );
		$message   = '';
		$success   = false;

		switch ( $action ) {
			case 'bbp_toggle_topic_approve' :
				check_admin_referer( 'approve-topic_' . $topic_id );

				$is_approve = bbp_is_topic_public( $topic_id );
				$message    = ( true === $is_approve )
					? 'unapproved'
					: 'approved';
				$success    = ( true === $is_approve )
					? bbp_unapprove_topic( $topic_id )
					: bbp_approve_topic( $topic_id );

				break;

			case 'bbp_toggle_topic_close' :
				check_admin_referer( 'close-topic_' . $topic_id );

				$is_open = bbp_is_topic_open( $topic_id );
				$message = ( true === $is_open )
					? 'closed'
					: 'opened';
				$success = ( true === $is_open )
					? bbp_close_topic( $topic_id )
					: bbp_open_topic( $topic_id );

				break;

			case 'bbp_toggle_topic_stick' :
				check_admin_referer( 'stick-topic_' . $topic_id );

				$is_sticky = bbp_is_topic_sticky( $topic_id );
				$is_super  = ( false === $is_sticky ) && ! empty( $_GET['super'] ) && ( "1" === $_GET['super'] )
					? true
					: false;
				$message   = ( true  === $is_sticky )
					? 'unstuck'
					: 'stuck';
				$message   = ( true  === $is_super )
					? 'super_sticky'
					: $message;
				$success   = ( true  === $is_sticky )
					? bbp_unstick_topic( $topic_id )
					: bbp_stick_topic( $topic_id, $is_super );

				break;

			case 'bbp_toggle_topic_spam'  :
				check_admin_referer( 'spam-topic_' . $topic_id );

				$is_spam = bbp_is_topic_spam( $topic_id );
				$message = ( true === $is_spam )
					? 'unspammed'
					: 'spammed';
				$success = ( true === $is_spam )
					? bbp_unspam_topic( $topic_id )
					: bbp_spam_topic( $topic_id );

				break;
		}

		// Setup the message
		$retval = array(
			'bbp_topic_toggle_notice' => $message,
			'topic_id'                => $topic_id
		);

		// Prepare for failure
		if ( ( false === $success ) || is_wp_error( $success ) ) {
			$retval['failed'] = '1';
		}

		// Filter all message args
		$retval = apply_filters( 'bbp_toggle_topic_action_admin', $retval, $topic_id, $action );

		// Do additional topic toggle actions (admin side)
		do_action( 'bbp_toggle_topic_admin', $success, $post_data, $action, $retval );

		// Redirect back to the topic
		$redirect = add_query_arg( $retval, remove_query_arg( array( 'action', 'topic_id' ) ) );
		bbp_redirect( $redirect );
	}

	/**
	 * Toggle topic notices
	 *
	 * Display the success/error notices from
	 * {@link BBP_Admin::toggle_topic()}
	 *
	 * @since 2.0.0 bbPress (r2727)
	 */
	public function toggle_topic_notice() {

		// Bail if missing topic toggle action
		if ( ! bbp_is_get_request() || empty( $_GET['topic_id'] ) || empty( $_GET['bbp_topic_toggle_notice'] ) ) {
			return;
		}

		// Bail if not an allowed notice
		$notice = sanitize_key( $_GET['bbp_topic_toggle_notice'] );
		if ( empty( $notice ) || ! in_array( $notice, $this->get_allowed_notice_toggles(), true ) ) {
			return;
		}

		// Bail if no topic_id or notice
		$topic_id = bbp_get_topic_id( $_GET['topic_id'] );
		if (  empty( $topic_id ) ) {
			return;
		}

		// Bail if topic is missing
		if ( ! bbp_get_topic( $topic_id ) ) {
			return;
		}

		// Use the title in the responses
		$topic_title = bbp_get_topic_title( $topic_id );
		$is_failure  = ! empty( $_GET['failed'] );
		$message     = '';

		// Which notice?
		switch ( $notice ) {
			case 'opened'    :
				$message = ( $is_failure === true )
					? sprintf( esc_html__( 'There was a problem opening the topic "%1$s".', 'bbpress' ), $topic_title )
					: sprintf( esc_html__( 'Topic "%1$s" successfully opened.',             'bbpress' ), $topic_title );
				break;

			case 'closed'    :
				$message = ( $is_failure === true )
					? sprintf( esc_html__( 'There was a problem closing the topic "%1$s".', 'bbpress' ), $topic_title )
					: sprintf( esc_html__( 'Topic "%1$s" successfully closed.',             'bbpress' ), $topic_title );
				break;

			case 'super_sticky' :
				$message = ( $is_failure === true )
					? sprintf( esc_html__( 'There was a problem sticking the topic "%1$s" to front.', 'bbpress' ), $topic_title )
					: sprintf( esc_html__( 'Topic "%1$s" successfully stuck to front.',               'bbpress' ), $topic_title );
				break;

			case 'stuck'   :
				$message = ( $is_failure === true )
					? sprintf( esc_html__( 'There was a problem sticking the topic "%1$s".', 'bbpress' ), $topic_title )
					: sprintf( esc_html__( 'Topic "%1$s" successfully stuck.',               'bbpress' ), $topic_title );
				break;

			case 'unstuck' :
				$message = ( $is_failure === true )
					? sprintf( esc_html__( 'There was a problem unsticking the topic "%1$s".', 'bbpress' ), $topic_title )
					: sprintf( esc_html__( 'Topic "%1$s" successfully unstuck.',               'bbpress' ), $topic_title );
				break;

			case 'spammed'   :
				$message = ( $is_failure === true )
					? sprintf( esc_html__( 'There was a problem marking the topic "%1$s" as spam.', 'bbpress' ), $topic_title )
					: sprintf( esc_html__( 'Topic "%1$s" successfully marked as spam.',             'bbpress' ), $topic_title );
				break;

			case 'unspammed' :
				$message = ( $is_failure === true )
					? sprintf( esc_html__( 'There was a problem unmarking the topic "%1$s" as spam.', 'bbpress' ), $topic_title )
					: sprintf( esc_html__( 'Topic "%1$s" successfully unmarked as spam.',             'bbpress' ), $topic_title );
				break;

			case 'approved'   :
				$message = ( $is_failure === true )
					? sprintf( esc_html__( 'There was a problem approving the topic "%1$s".', 'bbpress' ), $topic_title )
					: sprintf( esc_html__( 'Topic "%1$s" successfully approved.',             'bbpress' ), $topic_title );
				break;

			case 'unapproved' :
				$message = ( $is_failure === true )
					? sprintf( esc_html__( 'There was a problem unapproving the topic "%1$s".', 'bbpress' ), $topic_title )
					: sprintf( esc_html__( 'Topic "%1$s" successfully unapproved.',             'bbpress' ), $topic_title );
				break;
		}

		// Do additional topic toggle notice filters (admin side)
		$message = apply_filters( 'bbp_toggle_topic_notice_admin', $message, $topic_id, $notice, $is_failure );
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
		return (array) apply_filters( 'bbp_admin_topics_row_action_sort_order', array(
			'edit',
			'stick',
			'approved',
			'unapproved',
			'closed',
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
		return (array) apply_filters( 'bbp_admin_topics_allowed_notice_toggles', array(
			'opened',
			'closed',
			'super_sticky',
			'stuck',
			'unstuck',
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
		return (array) apply_filters( 'bbp_admin_topics_allowed_action_toggles', array(
			'bbp_toggle_topic_close',
			'bbp_toggle_topic_stick',
			'bbp_toggle_topic_spam',
			'bbp_toggle_topic_approve'
		) );
	}

	/**
	 * Manage the column headers for the topics page
	 *
	 * @since 2.0.0 bbPress (r2485)
	 *
	 * @param array $columns The columns
	 *
	 * @return array $columns bbPress topic columns
	 */
	public function column_headers( $columns ) {
		$columns = array(
			'cb'                    => '<input type="checkbox" />',
			'title'                 => esc_html__( 'Topics',    'bbpress' ),
			'bbp_topic_forum'       => esc_html__( 'Forum',     'bbpress' ),
			'bbp_topic_reply_count' => esc_html__( 'Replies',   'bbpress' ),
			'bbp_topic_voice_count' => esc_html__( 'Voices',    'bbpress' ),
			'bbp_topic_author'      => esc_html__( 'Author',    'bbpress' ),
			'bbp_topic_created'     => esc_html__( 'Created',   'bbpress' ),
			'bbp_topic_freshness'   => esc_html__( 'Last Post', 'bbpress' )
		);

		// Filter & return
		return (array) apply_filters( 'bbp_admin_topics_column_headers', $columns );
	}

	/**
	 * Print extra columns for the topics page
	 *
	 * @since 2.0.0 bbPress (r2485)
	 *
	 * @param string $column Column
	 * @param int $topic_id Topic id
	 */
	public function column_data( $column, $topic_id ) {

		// Get topic forum ID
		$forum_id = bbp_get_topic_forum_id( $topic_id );

		// Populate column data
		switch ( $column ) {

			// Forum
			case 'bbp_topic_forum' :

				// Get title
				$forum_title = ! empty( $forum_id )
					? bbp_get_forum_title( $forum_id )
					: '';

				// Output forum name
				if ( ! empty( $forum_title ) ) {
					echo $forum_title;

				// Output dash
				} else {
					?>
					<span aria-hidden="true">&mdash;</span>
					<span class="screen-reader-text"><?php esc_html_e( 'No forum', 'bbpress' ); ?></span>
					<?php
				}

				break;

			// Reply Count
			case 'bbp_topic_reply_count' :
				bbp_topic_reply_count( $topic_id );
				break;

			// Reply Count
			case 'bbp_topic_voice_count' :
				bbp_topic_voice_count( $topic_id );
				break;

			// Author
			case 'bbp_topic_author' :
				bbp_topic_author_display_name( $topic_id );
				break;

			// Freshness
			case 'bbp_topic_created':
				printf( '%1$s <br /> %2$s',
					get_the_date(),
					esc_attr( get_the_time() )
				);

				break;

			// Freshness
			case 'bbp_topic_freshness' :
				$last_active = bbp_get_topic_last_active_time( $topic_id, false );
				if ( ! empty( $last_active ) ) {
					echo esc_html( $last_active );
				} else {
					esc_html_e( 'No Replies', 'bbpress' ); // This should never happen
				}

				break;

			// Do an action for anything else
			default :
				do_action( 'bbp_admin_topics_column_data', $column, $topic_id );
				break;
		}
	}

	/**
	 * Topic Row actions
	 *
	 * Remove the quick-edit action link under the topic title and add the
	 * content and close/stick/spam links
	 *
	 * @since 2.0.0 bbPress (r2485)
	 *
	 * @param array  $actions Actions
	 * @param object $topic   Topic object
	 *
	 * @return array $actions Actions
	 */
	public function row_actions( $actions = array(), $topic = false ) {

		// Disable quick edit (too much to do here)
		unset( $actions['inline hide-if-no-js'] );

		// View link
		$view_link = bbp_get_topic_permalink( $topic->ID );

		// Maybe add view=all
		if ( ! in_array( $topic->post_status, array( bbp_get_closed_status_id(), bbp_get_public_status_id() ), true ) ) {
			$view_link = bbp_add_view_all( $view_link, true );
		}

		// Show view link if it's not set, the topic is trashed and the user can view trashed topics
		if ( empty( $actions['view'] ) && ( bbp_get_trash_status_id() === $topic->post_status ) && current_user_can( 'view_trash' ) ) {
			$actions['view'] = '<a href="' . esc_url( $view_link ) . '" title="' . esc_attr( sprintf( __( 'View &#8220;%s&#8221;', 'bbpress' ), bbp_get_topic_title( $topic->ID ) ) ) . '" rel="permalink">' . esc_html__( 'View', 'bbpress' ) . '</a>';
		}

		// Only show the actions if the user is capable of viewing them :)
		if ( current_user_can( 'moderate', $topic->ID ) ) {

			// Pending
			// Show the 'approve' and 'view' link on pending posts only and 'unapprove' on published posts only
			$approve_uri = wp_nonce_url( add_query_arg( array( 'topic_id' => $topic->ID, 'action' => 'bbp_toggle_topic_approve' ), remove_query_arg( array( 'bbp_topic_toggle_notice', 'topic_id', 'failed', 'super' ) ) ), 'approve-topic_' . $topic->ID );
			if ( bbp_is_topic_public( $topic->ID ) ) {
				$actions['unapproved'] = '<a href="' . esc_url( $approve_uri ) . '" title="' . esc_attr__( 'Unapprove this topic', 'bbpress' ) . '">' . _x( 'Unapprove', 'Unapprove Topic', 'bbpress' ) . '</a>';
			} else {

				// Do not show 'approve' if already public
				if ( ! bbp_is_topic_public( $topic->ID ) ) {
					$actions['approved'] = '<a href="' . esc_url( $approve_uri ) . '" title="' . esc_attr__( 'Approve this topic',   'bbpress' ) . '">' . _x( 'Approve',   'Approve Topic',   'bbpress' ) . '</a>';
				}

				// Modify the view link
				$actions['view'] = '<a href="' . esc_url( $view_link   ) . '" title="' . esc_attr( sprintf( __( 'View &#8220;%s&#8221;', 'bbpress' ), bbp_get_topic_title( $topic->ID ) ) ) . '" rel="permalink">' . esc_html__( 'View', 'bbpress' ) . '</a>';
			}

			// Close
			// Show the 'close' and 'open' link on published and closed posts only
			if ( bbp_is_topic_public( $topic->ID ) ) {
				$close_uri = wp_nonce_url( add_query_arg( array( 'topic_id' => $topic->ID, 'action' => 'bbp_toggle_topic_close' ), remove_query_arg( array( 'bbp_topic_toggle_notice', 'topic_id', 'failed', 'super' ) ) ), 'close-topic_' . $topic->ID );
				if ( bbp_is_topic_open( $topic->ID ) ) {
					$actions['closed'] = '<a href="' . esc_url( $close_uri ) . '" title="' . esc_attr__( 'Close this topic', 'bbpress' ) . '">' . _x( 'Close', 'Close a Topic', 'bbpress' ) . '</a>';
				} else {
					$actions['closed'] = '<a href="' . esc_url( $close_uri ) . '" title="' . esc_attr__( 'Open this topic',  'bbpress' ) . '">' . _x( 'Open',  'Open a Topic',  'bbpress' ) . '</a>';
				}
			}

			// Sticky
			// Dont show sticky if topic is spam, trash or pending
			if ( ! bbp_is_topic_spam( $topic->ID ) && ! bbp_is_topic_trash( $topic->ID ) && ! bbp_is_topic_pending( $topic->ID ) ) {
				$stick_uri = wp_nonce_url( add_query_arg( array( 'topic_id' => $topic->ID, 'action' => 'bbp_toggle_topic_stick' ), remove_query_arg( array( 'bbp_topic_toggle_notice', 'topic_id', 'failed', 'super' ) ) ), 'stick-topic_'  . $topic->ID );
				if ( bbp_is_topic_sticky( $topic->ID ) ) {
					$actions['stick'] = '<a href="' . esc_url( $stick_uri ) . '" title="' . esc_attr__( 'Unstick this topic', 'bbpress' ) . '">' . esc_html__( 'Unstick', 'bbpress' ) . '</a>';
				} else {
					$super_uri        = wp_nonce_url( add_query_arg( array( 'topic_id' => $topic->ID, 'action' => 'bbp_toggle_topic_stick', 'super' => '1' ), remove_query_arg( array( 'bbp_topic_toggle_notice', 'topic_id', 'failed', 'super' ) ) ), 'stick-topic_'  . $topic->ID );
					$actions['stick'] = '<a href="' . esc_url( $stick_uri ) . '" title="' . esc_attr__( 'Stick this topic to its forum', 'bbpress' ) . '">' . esc_html__( 'Stick', 'bbpress' ) . '</a> <a href="' . esc_url( $super_uri ) . '" title="' . esc_attr__( 'Stick this topic to front', 'bbpress' ) . '">' . esc_html__( '(to front)', 'bbpress' ) . '</a>';
				}
			}

			// Spam
			$spam_uri = wp_nonce_url( add_query_arg( array( 'topic_id' => $topic->ID, 'action' => 'bbp_toggle_topic_spam' ), remove_query_arg( array( 'bbp_topic_toggle_notice', 'topic_id', 'failed', 'super' ) ) ), 'spam-topic_'  . $topic->ID );
			if ( ! bbp_is_topic_spam( $topic->ID ) ) {
				$actions['spam'] = '<a href="' . esc_url( $spam_uri ) . '" title="' . esc_attr__( 'Mark this topic as spam',    'bbpress' ) . '">' . esc_html__( 'Spam',     'bbpress' ) . '</a>';
			} else {
				$actions['unspam'] = '<a href="' . esc_url( $spam_uri ) . '" title="' . esc_attr__( 'Mark the topic as not spam', 'bbpress' ) . '">' . esc_html__( 'Not Spam', 'bbpress' ) . '</a>';
			}
		}

		// Do not show trash links for spam topics, or spam links for trashed topics
		if ( current_user_can( 'delete_topic', $topic->ID ) ) {
			$trash_days = bbp_get_trash_days( bbp_get_topic_post_type() );

			if ( bbp_get_trash_status_id() === $topic->post_status ) {
				$post_type_object   = get_post_type_object( bbp_get_topic_post_type() );
				$actions['untrash'] = "<a title='" . esc_attr__( 'Restore this item from the Trash', 'bbpress' ) . "' href='" . esc_url( wp_nonce_url( admin_url( sprintf( $post_type_object->_edit_link . '&amp;action=untrash', $topic->ID ) ), 'untrash-post_' . $topic->ID ) ) . "'>" . esc_html__( 'Restore', 'bbpress' ) . "</a>";
			} elseif ( ! empty( $trash_days ) ) {
				$actions['trash'] = "<a class='submitdelete' title='" . esc_attr__( 'Move this item to the Trash', 'bbpress' ) . "' href='" . esc_url( get_delete_post_link( $topic->ID ) ) . "'>" . esc_html__( 'Trash', 'bbpress' ) . "</a>";
			}

			if ( ( bbp_get_trash_status_id() === $topic->post_status ) || empty( $trash_days ) ) {
				$actions['delete'] = "<a class='submitdelete' title='" . esc_attr__( 'Delete this item permanently', 'bbpress' ) . "' href='" . esc_url( get_delete_post_link( $topic->ID, '', true ) ) . "'>" . esc_html__( 'Delete Permanently', 'bbpress' ) . "</a>";
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
	function filter_post_rows( $query_vars ) {

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
	 * Custom user feedback messages for topic post type
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
		$topic_url = bbp_get_topic_permalink( $post_ID );

		// Current topic's post_date
		$post_date = bbp_get_global_post_field( 'post_date', 'raw' );

		// Messages array
		$messages[ $this->post_type ] = array(
			0 =>  '', // Left empty on purpose

			// Updated
			1 =>  sprintf(
				'%1$s <a href="%2$s">%3$s</a>',
				esc_html__( 'Topic updated.', 'bbpress' ),
				$topic_url,
				esc_html__( 'View topic', 'bbpress' )
			),

			// Custom field updated
			2 => esc_html__( 'Custom field updated.', 'bbpress' ),

			// Custom field deleted
			3 => esc_html__( 'Custom field deleted.', 'bbpress' ),

			// Topic updated
			4 => esc_html__( 'Topic updated.', 'bbpress' ),

			// Restored from revision
			// translators: %s: date and time of the revision
			5 => isset( $_GET['revision'] )
					? sprintf( esc_html__( 'Topic restored to revision from %s', 'bbpress' ), wp_post_revision_title( (int) $_GET['revision'], false ) )
					: false,

			// Topic created
			6 => sprintf(
				'%1$s <a href="%2$s">%3$s</a>',
				esc_html__( 'Topic created.', 'bbpress' ),
				$topic_url,
				esc_html__( 'View topic', 'bbpress' )
			),

			// Topic saved
			7 => esc_html__( 'Topic saved.', 'bbpress' ),

			// Topic submitted
			8 => sprintf(
				'%1$s <a href="%2$s" target="_blank">%3$s</a>',
				esc_html__( 'Topic submitted.', 'bbpress' ),
				esc_url( add_query_arg( 'preview', 'true', $topic_url ) ),
				esc_html__( 'Preview topic', 'bbpress' )
			),

			// Topic scheduled
			9 => sprintf(
				'%1$s <a target="_blank" href="%2$s">%3$s</a>',
				sprintf(
					esc_html__( 'Topic scheduled for: %s.', 'bbpress' ),
					// translators: Publish box date format, see http://php.net/date
					'<strong>' . date_i18n( __( 'M j, Y @ G:i', 'bbpress' ), strtotime( $post_date ) ) . '</strong>'
				),
				$topic_url,
				esc_html__( 'Preview topic', 'bbpress' )
			),

			// Topic draft updated
			10 => sprintf(
				'%1$s <a href="%2$s" target="_blank">%3$s</a>',
				esc_html__( 'Topic draft updated.', 'bbpress' ),
				esc_url( add_query_arg( 'preview', 'true', $topic_url ) ),
				esc_html__( 'Preview topic', 'bbpress' )
			),
		);

		return $messages;
	}
}
endif; // class_exists check

/**
 * Setup bbPress Topics Admin
 *
 * This is currently here to make hooking and unhooking of the admin UI easy.
 * It could use dependency injection in the future, but for now this is easier.
 *
 * @since 2.0.0 bbPress (r2596)
 *
 * @param WP_Screen $current_screen Current screen object
 */
function bbp_admin_topics( $current_screen ) {

	// Bail if not a forum screen
	if ( empty( $current_screen->post_type ) || ( bbp_get_topic_post_type() !== $current_screen->post_type ) ) {
		return;
	}

	// Init the topics admin
	bbp_admin()->topics = new BBP_Topics_Admin();
}
