<?php

/**
 * bbPress Topics Admin Class
 *
 * @package bbPress
 * @subpackage Administration
 */

// Exit if accessed directly
if ( !defined( 'ABSPATH' ) ) exit;

if ( !class_exists( 'BBP_Topics_Admin' ) ) :
/**
 * Loads bbPress topics admin area
 *
 * @package bbPress
 * @subpackage Administration
 * @since bbPress (r2464)
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
	 * @since bbPress (r2515)
	 *
	 * @uses BBP_Topics_Admin::setup_globals() Setup the globals needed
	 * @uses BBP_Topics_Admin::setup_actions() Setup the hooks and actions
	 * @uses BBP_Topics_Admin::setup_help() Setup the help text
	 */
	public function __construct() {
		$this->setup_globals();
		$this->setup_actions();
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
	private function setup_actions() {

		// Add some general styling to the admin area
		add_action( 'bbp_admin_head',        array( $this, 'admin_head'       ) );

		// Messages
		add_filter( 'post_updated_messages', array( $this, 'updated_messages' ) );

		// Topic column headers.
		add_filter( 'manage_' . $this->post_type . '_posts_columns',        array( $this, 'topics_column_headers' ) );

		// Topic columns (in post row)
		add_action( 'manage_' . $this->post_type . '_posts_custom_column',  array( $this, 'topics_column_data' ), 10, 2 );
		add_filter( 'post_row_actions',                                     array( $this, 'topics_row_actions' ), 10, 2 );

		// Topic metabox actions
		add_action( 'add_meta_boxes', array( $this, 'attributes_metabox'      ) );
		add_action( 'save_post',      array( $this, 'attributes_metabox_save' ) );

		// Check if there are any bbp_toggle_topic_* requests on admin_init, also have a message displayed
		add_action( 'load-edit.php',  array( $this, 'toggle_topic'        ) );
		add_action( 'admin_notices',  array( $this, 'toggle_topic_notice' ) );

		// Anonymous metabox actions
		add_action( 'add_meta_boxes', array( $this, 'author_metabox'      ) );
		add_action( 'save_post',      array( $this, 'author_metabox_save' ) );

		// Add ability to filter topics and replies per forum
		add_filter( 'restrict_manage_posts', array( $this, 'filter_dropdown'  ) );
		add_filter( 'bbp_request',           array( $this, 'filter_post_rows' ) );

		// Contextual Help
		add_action( 'load-edit.php',     array( $this, 'edit_help' ) );
		add_action( 'load-post-new.php', array( $this, 'new_help'  ) );
	}

	/**
	 * Should we bail out of this method?
	 *
	 * @since bbPress (r4067)
	 * @return boolean
	 */
	private function bail() {
		if ( !isset( get_current_screen()->post_type ) || ( $this->post_type != get_current_screen()->post_type ) )
			return true;

		return false;
	}

	/**
	 * Admin globals
	 *
	 * @since bbPress (r2646)
	 * @access private
	 */
	private function setup_globals() {
		$this->post_type = bbp_get_topic_post_type();
	}

	/** Contextual Help *******************************************************/

	/**
	 * Contextual help for bbPress topic edit page
	 *
	 * @since bbPress (r3119)
	 * @uses get_current_screen()
	 */
	public function edit_help() {

		if ( $this->bail() ) return;

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
					'<li>' . __( 'You can hide/display columns based on your needs and decide how many replies to list per screen using the Screen Options tab.',                                                                                                                                                                          'bbpress' ) . '</li>' .
					'<li>' . __( 'You can filter the list of replies by topic status using the text links in the upper left to show All, Published, Draft, or Trashed replies. The default view is to show all replies.',                                                                                                                   'bbpress' ) . '</li>' .
					'<li>' . __( 'You can view replies in a simple title list or with an excerpt. Choose the view you prefer by clicking on the icons at the top of the list on the right.',                                                                                                                                             'bbpress' ) . '</li>' .
					'<li>' . __( 'You can refine the list to show only replies in a specific category or from a specific month by using the dropdown menus above the replies list. Click the Filter button after making your selection. You also can refine the list by clicking on the topic author, category or tag in the replies list.', 'bbpress' ) . '</li>' .
				'</ul>'
		) );

		// Available Actions
		get_current_screen()->add_help_tab( array(
			'id'		=> 'action-links',
			'title'		=> __( 'Available Actions', 'bbpress' ),
			'content'	=>
				'<p>' . __( 'Hovering over a row in the replies list will display action links that allow you to manage your topic. You can perform the following actions:', 'bbpress' ) . '</p>' .
				'<ul>' .
					'<li>' . __( '<strong>Edit</strong> takes you to the editing screen for that topic. You can also reach that screen by clicking on the topic title.',                                                                                 'bbpress' ) . '</li>' .
					//'<li>' . __( '<strong>Quick Edit</strong> provides inline access to the metadata of your topic, allowing you to update topic details without leaving this screen.',                                                                  'bbpress' ) . '</li>' .
					'<li>' . __( '<strong>Trash</strong> removes your topic from this list and places it in the trash, from which you can permanently delete it.',                                                                                       'bbpress' ) . '</li>' .
					'<li>' . __( '<strong>Spam</strong> removes your topic from this list and places it in the spam queue, from which you can permanently delete it.',                                                                                   'bbpress' ) . '</li>' .
					'<li>' . __( '<strong>Preview</strong> will show you what your draft topic will look like if you publish it. View will take you to your live site to view the topic. Which link is available depends on your topic&#8217;s status.', 'bbpress' ) . '</li>' .
				'</ul>'
		) );

		// Bulk Actions
		get_current_screen()->add_help_tab( array(
			'id'		=> 'bulk-actions',
			'title'		=> __( 'Bulk Actions', 'bbpress' ),
			'content'	=>
				'<p>' . __( 'You can also edit or move multiple replies to the trash at once. Select the replies you want to act on using the checkboxes, then select the action you want to take from the Bulk Actions menu and click Apply.',           'bbpress' ) . '</p>' .
				'<p>' . __( 'When using Bulk Edit, you can change the metadata (categories, author, etc.) for all selected replies at once. To remove a topic from the grouping, just click the x next to its name in the Bulk Edit area that appears.', 'bbpress' ) . '</p>'
		) );

		// Help Sidebar
		get_current_screen()->set_help_sidebar(
			'<p><strong>' . __( 'For more information:', 'bbpress' ) . '</strong></p>' .
			'<p>' . __( '<a href="http://bbpress.org/documentation/" target="_blank">bbPress Documentation</a>', 'bbpress' ) . '</p>' .
			'<p>' . __( '<a href="http://bbpress.org/forums/" target="_blank">bbPress Support Forums</a>',       'bbpress' ) . '</p>'
		);
	}

	/**
	 * Contextual help for bbPress topic edit page
	 *
	 * @since bbPress (r3119)
	 * @uses get_current_screen()
	 */
	public function new_help() {

		if ( $this->bail() ) return;

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

		if ( current_theme_supports( 'topic-formats' ) && topic_type_supports( 'topic', 'topic-formats' ) ) {
			$publish_box .= '<p>' . __( '<strong>topic Format</strong> - This designates how your theme will display a specific topic. For example, you could have a <em>standard</em> blog topic with a title and paragraphs, or a short <em>aside</em> that omits the title and contains a short text blurb. Please refer to the Codex for <a href="http://codex.wordpress.org/Post_Formats#Supported_Formats">descriptions of each topic format</a>. Your theme could enable all or some of 10 possible formats.', 'bbpress' ) . '</p>';
		}

		if ( current_theme_supports( 'topic-thumbnails' ) && topic_type_supports( 'topic', 'thumbnail' ) ) {
			$publish_box .= '<p>' . __( '<strong>Featured Image</strong> - This allows you to associate an image with your topic without inserting it. This is usually useful only if your theme makes use of the featured image as a topic thumbnail on the home page, a custom header, etc.', 'bbpress' ) . '</p>';
		}

		get_current_screen()->add_help_tab( array(
			'id'      => 'topic-attributes',
			'title'   => __( 'Topic Attributes', 'bbpress' ),
			'content' =>
				'<p>' . __( 'Select the attributes that your topic should have:', 'bbpress' ) . '</p>' .
				'<ul>' .
					'<li>' . __( '<strong>Forum</strong> dropdown determines the parent forum that the topic belongs to. Select the forum or category from the dropdown, or leave the default (No Forum) to post the topic without an assigned forum.', 'bbpress' ) . '</li>' .
					'<li>' . __( '<strong>Topic Type</strong> dropdown indicates the sticky status of the topic. Selecting the super sticky option would stick the topic to the front of your forums, i.e. the topic index, sticky option would stick the topic to its respective forum. Selecting normal would not stick the topic anywhere.', 'bbpress' ) . '</li>' .
				'</ul>'
		) );

		get_current_screen()->add_help_tab( array(
			'id'      => 'publish-box',
			'title'   => __( 'Publish Box', 'bbpress' ),
			'content' => $publish_box,
		) );

		get_current_screen()->add_help_tab( array(
			'id'      => 'discussion-settings',
			'title'   => __( 'Discussion Settings', 'bbpress' ),
			'content' =>
				'<p>' . __( '<strong>Send Trackbacks</strong> - Trackbacks are a way to notify legacy blog systems that you&#8217;ve linked to them. Enter the URL(s) you want to send trackbacks. If you link to other WordPress sites they&#8217;ll be notified automatically using pingbacks, and this field is unnecessary.', 'bbpress' ) . '</p>' .
				'<p>' . __( '<strong>Discussion</strong> - You can turn comments and pings on or off, and if there are comments on the topic, you can see them here and moderate them.', 'bbpress' ) . '</p>'
		) );

		get_current_screen()->set_help_sidebar(
			'<p><strong>' . __( 'For more information:', 'bbpress' ) . '</strong></p>' .
			'<p>' . __( '<a href="http://bbpress.org/documentation/" target="_blank">bbPress Documentation</a>', 'bbpress' ) . '</p>' .
			'<p>' . __( '<a href="http://bbpress.org/forums/" target="_blank">bbPress Support Forums</a>',       'bbpress' ) . '</p>'
		);
	}

	/**
	 * Add the topic attributes metabox
	 *
	 * @since bbPress (r2744)
	 *
	 * @uses bbp_get_topic_post_type() To get the topic post type
	 * @uses add_meta_box() To add the metabox
	 * @uses do_action() Calls 'bbp_topic_attributes_metabox'
	 */
	public function attributes_metabox() {

		if ( $this->bail() ) return;

		add_meta_box (
			'bbp_topic_attributes',
			__( 'Topic Attributes', 'bbpress' ),
			'bbp_topic_metabox',
			$this->post_type,
			'side',
			'high'
		);

		do_action( 'bbp_topic_attributes_metabox' );
	}

	/**
	 * Pass the topic attributes for processing
	 *
	 * @since bbPress (r2746)
	 *
	 * @param int $topic_id Topic id
	 * @uses current_user_can() To check if the current user is capable of
	 *                           editing the topic
	 * @uses do_action() Calls 'bbp_topic_attributes_metabox_save' with the
	 *                    topic id and parent id
	 * @return int Parent id
	 */
	public function attributes_metabox_save( $topic_id ) {
		
		if ( $this->bail() ) return $topic_id;

		// Bail if doing an autosave
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE )
			return $topic_id;

		// Bail if not a post request
		if ( 'POST' != strtoupper( $_SERVER['REQUEST_METHOD'] ) )
			return $topic_id;

		// Nonce check
		if ( empty( $_POST['bbp_topic_metabox'] ) || !wp_verify_nonce( $_POST['bbp_topic_metabox'], 'bbp_topic_metabox_save' ) )
			return $topic_id;

		// Bail if current user cannot edit this topic
		if ( !current_user_can( 'edit_topic', $topic_id ) )
			return $topic_id;

		// Get the forum ID
		$forum_id = !empty( $_POST['parent_id'] ) ? (int) $_POST['parent_id'] : 0;

		// Formally update the topic
		bbp_update_topic( $topic_id, $forum_id );

		// Stickies
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

				// Normal
				case 'unstick' :
				default        :
					bbp_unstick_topic( $topic_id );
					break;
			}
		}

		// Allow other fun things to happen
		do_action( 'bbp_topic_attributes_metabox_save', $topic_id, $forum_id );

		return $topic_id;
	}

	/**
	 * Add the author info metabox
	 *
	 * @since bbPress (r2828)
	 *
	 * @uses bbp_get_topic() To get the topic
	 * @uses bbp_get_reply() To get the reply
	 * @uses bbp_get_topic_post_type() To get the topic post type
	 * @uses bbp_get_reply_post_type() To get the reply post type
	 * @uses add_meta_box() To add the metabox
	 * @uses do_action() Calls 'bbp_author_metabox' with the topic/reply
	 *                    id
	 */
	public function author_metabox() {

		if ( $this->bail() ) return;

		// Bail if post_type is not a topic
		if ( empty( $_GET['action'] ) || ( 'edit' != $_GET['action'] ) )
			return;

		// Add the metabox
		add_meta_box(
			'bbp_author_metabox',
			__( 'Author Information', 'bbpress' ),
			'bbp_author_metabox',
			$this->post_type,
			'side',
			'high'
		);

		do_action( 'bbp_author_metabox', get_the_ID() );
	}

	/**
	 * Save the author information for the topic
	 *
	 * @since bbPress (r2828)
	 *
	 * @param int $post_id Topic or reply id
	 * @uses bbp_get_topic() To get the topic
	 * @uses bbp_get_reply() To get the reply
	 * @uses current_user_can() To check if the current user can edit the
	 *                           topic or reply
	 * @uses bbp_filter_author_post_data() To filter the author data
	 * @uses update_post_meta() To update the anonymous user data
	 * @uses do_action() Calls 'bbp_author_metabox_save' with the topic id and
	 *                    anonymous data
	 * @return int Topic or reply id
	 */
	public function author_metabox_save( $post_id ) {

		if ( $this->bail() ) return $post_id;

		// Bail if no post_id
		if ( empty( $post_id ) )
			return $post_id;

		// Bail if doing an autosave
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE )
			return $post_id;

		// Bail if not a post request
		if ( 'POST' != strtoupper( $_SERVER['REQUEST_METHOD'] ) )
			return $post_id;

		// Bail if user cannot edit replies
		if ( !current_user_can( 'edit_topic', $post_id ) )
			return $post_id;

		$anonymous_data = bbp_filter_anonymous_post_data();

		update_post_meta( $post_id, '_bbp_anonymous_name',    $anonymous_data['bbp_anonymous_name']    );
		update_post_meta( $post_id, '_bbp_anonymous_email',   $anonymous_data['bbp_anonymous_email']   );
		update_post_meta( $post_id, '_bbp_anonymous_website', $anonymous_data['bbp_anonymous_website'] );

		do_action( 'bbp_author_metabox_save', $post_id, $anonymous_data );

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
	 * @uses do_action() Calls 'bbp_admin_head'
	 */
	public function admin_head() {

		if ( $this->bail() ) return;

		?>

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

		<?php
	}

	/**
	 * Toggle topic
	 *
	 * Handles the admin-side opening/closing, sticking/unsticking and
	 * spamming/unspamming of topics
	 *
	 * @since bbPress (r2727)
	 *
	 * @uses bbp_get_topic() To get the topic
	 * @uses current_user_can() To check if the user is capable of editing
	 *                           the topic
	 * @uses wp_die() To die if the user isn't capable or the post wasn't
	 *                 found
	 * @uses check_admin_referer() To verify the nonce and check referer
	 * @uses bbp_is_topic_open() To check if the topic is open
	 * @uses bbp_close_topic() To close the topic
	 * @uses bbp_open_topic() To open the topic
	 * @uses bbp_is_topic_sticky() To check if the topic is a sticky or
	 *                              super sticky
	 * @uses bbp_unstick_topic() To unstick the topic
	 * @uses bbp_stick_topic() To stick the topic
	 * @uses bbp_is_topic_spam() To check if the topic is marked as spam
	 * @uses bbp_unspam_topic() To unmark the topic as spam
	 * @uses bbp_spam_topic() To mark the topic as spam
	 * @uses do_action() Calls 'bbp_toggle_topic_admin' with success, post
	 *                    data, action and message
	 * @uses add_query_arg() To add custom args to the url
	 * @uses wp_safe_redirect() Redirect the page to custom url
	 */
	public function toggle_topic() {

		if ( $this->bail() ) return;

		// Only proceed if GET is a topic toggle action
		if ( 'GET' == $_SERVER['REQUEST_METHOD'] && !empty( $_GET['action'] ) && in_array( $_GET['action'], array( 'bbp_toggle_topic_close', 'bbp_toggle_topic_stick', 'bbp_toggle_topic_spam' ) ) && !empty( $_GET['topic_id'] ) ) {
			$action    = $_GET['action'];            // What action is taking place?
			$topic_id  = (int) $_GET['topic_id'];    // What's the topic id?
			$success   = false;                      // Flag
			$post_data = array( 'ID' => $topic_id ); // Prelim array
			$topic     = bbp_get_topic( $topic_id );

			// Bail if topic is missing
			if ( empty( $topic ) )
				wp_die( __( 'The topic was not found!', 'bbpress' ) );

			if ( !current_user_can( 'moderate', $topic->ID ) ) // What is the user doing here?
				wp_die( __( 'You do not have the permission to do that!', 'bbpress' ) );

			switch ( $action ) {
				case 'bbp_toggle_topic_close' :
					check_admin_referer( 'close-topic_' . $topic_id );

					$is_open = bbp_is_topic_open( $topic_id );
					$message = true == $is_open ? 'closed' : 'opened';
					$success = true == $is_open ? bbp_close_topic( $topic_id ) : bbp_open_topic( $topic_id );

					break;

				case 'bbp_toggle_topic_stick' :
					check_admin_referer( 'stick-topic_' . $topic_id );

					$is_sticky = bbp_is_topic_sticky( $topic_id );
					$is_super  = ( empty( $is_sticky ) && !empty( $_GET['super'] ) && 1 == (int) $_GET['super'] ) ? true : false;
					$message   = true == $is_sticky ? 'unsticked'     : 'sticked';
					$message   = true == $is_super  ? 'super_sticked' : $message;
					$success   = true == $is_sticky ? bbp_unstick_topic( $topic_id ) : bbp_stick_topic( $topic_id, $is_super );

					break;

				case 'bbp_toggle_topic_spam'  :
					check_admin_referer( 'spam-topic_' . $topic_id );

					$is_spam = bbp_is_topic_spam( $topic_id );
					$message = true == $is_spam ? 'unspammed' : 'spammed';
					$success = true == $is_spam ? bbp_unspam_topic( $topic_id ) : bbp_spam_topic( $topic_id );

					break;
			}

			$message = array( 'bbp_topic_toggle_notice' => $message, 'topic_id' => $topic->ID );

			if ( false == $success || is_wp_error( $success ) )
				$message['failed'] = '1';

			// Do additional topic toggle actions (admin side)
			do_action( 'bbp_toggle_topic_admin', $success, $post_data, $action, $message );

			// Redirect back to the topic
			$redirect = add_query_arg( $message, remove_query_arg( array( 'action', 'topic_id' ) ) );
			wp_safe_redirect( $redirect );

			// For good measure
			exit();
		}
	}

	/**
	 * Toggle topic notices
	 *
	 * Display the success/error notices from
	 * {@link BBP_Admin::toggle_topic()}
	 *
	 * @since bbPress (r2727)
	 *
	 * @uses bbp_get_topic() To get the topic
	 * @uses bbp_get_topic_title() To get the topic title of the topic
	 * @uses esc_html() To sanitize the topic title
	 * @uses apply_filters() Calls 'bbp_toggle_topic_notice_admin' with
	 *                        message, topic id, notice and is it a failure
	 */
	public function toggle_topic_notice() {

		if ( $this->bail() ) return;

		// Only proceed if GET is a topic toggle action
		if ( 'GET' == $_SERVER['REQUEST_METHOD'] && !empty( $_GET['bbp_topic_toggle_notice'] ) && in_array( $_GET['bbp_topic_toggle_notice'], array( 'opened', 'closed', 'super_sticked', 'sticked', 'unsticked', 'spammed', 'unspammed' ) ) && !empty( $_GET['topic_id'] ) ) {
			$notice     = $_GET['bbp_topic_toggle_notice'];         // Which notice?
			$topic_id   = (int) $_GET['topic_id'];                  // What's the topic id?
			$is_failure = !empty( $_GET['failed'] ) ? true : false; // Was that a failure?

			// Bais if no topic_id or notice
			if ( empty( $notice ) || empty( $topic_id ) )
				return;

			// Bail if topic is missing
			$topic = bbp_get_topic( $topic_id );
			if ( empty( $topic ) )
				return;

			$topic_title = esc_html( bbp_get_topic_title( $topic->ID ) );

			switch ( $notice ) {
				case 'opened'    :
					$message = $is_failure == true ? sprintf( __( 'There was a problem opening the topic "%1$s".',           'bbpress' ), $topic_title ) : sprintf( __( 'Topic "%1$s" successfully opened.',           'bbpress' ), $topic_title );
					break;

				case 'closed'    :
					$message = $is_failure == true ? sprintf( __( 'There was a problem closing the topic "%1$s".',           'bbpress' ), $topic_title ) : sprintf( __( 'Topic "%1$s" successfully closed.',           'bbpress' ), $topic_title );
					break;

				case 'super_sticked' :
					$message = $is_failure == true ? sprintf( __( 'There was a problem sticking the topic "%1$s" to front.', 'bbpress' ), $topic_title ) : sprintf( __( 'Topic "%1$s" successfully sticked to front.', 'bbpress' ), $topic_title );
					break;

				case 'sticked'   :
					$message = $is_failure == true ? sprintf( __( 'There was a problem sticking the topic "%1$s".',          'bbpress' ), $topic_title ) : sprintf( __( 'Topic "%1$s" successfully sticked.',          'bbpress' ), $topic_title );
					break;

				case 'unsticked' :
					$message = $is_failure == true ? sprintf( __( 'There was a problem unsticking the topic "%1$s".',        'bbpress' ), $topic_title ) : sprintf( __( 'Topic "%1$s" successfully unsticked.',        'bbpress' ), $topic_title );
					break;

				case 'spammed'   :
					$message = $is_failure == true ? sprintf( __( 'There was a problem marking the topic "%1$s" as spam.',   'bbpress' ), $topic_title ) : sprintf( __( 'Topic "%1$s" successfully marked as spam.',   'bbpress' ), $topic_title );
					break;

				case 'unspammed' :
					$message = $is_failure == true ? sprintf( __( 'There was a problem unmarking the topic "%1$s" as spam.', 'bbpress' ), $topic_title ) : sprintf( __( 'Topic "%1$s" successfully unmarked as spam.', 'bbpress' ), $topic_title );
					break;
			}

			// Do additional topic toggle notice filters (admin side)
			$message = apply_filters( 'bbp_toggle_topic_notice_admin', $message, $topic->ID, $notice, $is_failure );

			?>

			<div id="message" class="<?php echo $is_failure == true ? 'error' : 'updated'; ?> fade">
				<p style="line-height: 150%"><?php echo $message; ?></p>
			</div>

			<?php
		}
	}

	/**
	 * Manage the column headers for the topics page
	 *
	 * @since bbPress (r2485)
	 *
	 * @param array $columns The columns
	 * @uses apply_filters() Calls 'bbp_admin_topics_column_headers' with
	 *                        the columns
	 * @return array $columns bbPress topic columns
	 */
	public function topics_column_headers( $columns ) {

		if ( $this->bail() ) return $columns;

		$columns = array(
			'cb'                    => '<input type="checkbox" />',
			'title'                 => __( 'Topics',    'bbpress' ),
			'bbp_topic_forum'       => __( 'Forum',     'bbpress' ),
			'bbp_topic_reply_count' => __( 'Replies',   'bbpress' ),
			'bbp_topic_voice_count' => __( 'Voices',    'bbpress' ),
			'bbp_topic_author'      => __( 'Author',    'bbpress' ),
			'bbp_topic_created'     => __( 'Created',   'bbpress' ),
			'bbp_topic_freshness'   => __( 'Freshness', 'bbpress' )
		);

		return apply_filters( 'bbp_admin_topics_column_headers', $columns );
	}

	/**
	 * Print extra columns for the topics page
	 *
	 * @since bbPress (r2485)
	 *
	 * @param string $column Column
	 * @param int $topic_id Topic id
	 * @uses bbp_get_topic_forum_id() To get the forum id of the topic
	 * @uses bbp_forum_title() To output the topic's forum title
	 * @uses apply_filters() Calls 'topic_forum_row_actions' with an array
	 *                        of topic forum actions
	 * @uses bbp_get_forum_permalink() To get the forum permalink
	 * @uses admin_url() To get the admin url of post.php
	 * @uses add_query_arg() To add custom args to the url
	 * @uses bbp_topic_reply_count() To output the topic reply count
	 * @uses bbp_topic_voice_count() To output the topic voice count
	 * @uses bbp_topic_author_display_name() To output the topic author name
	 * @uses get_the_date() Get the topic creation date
	 * @uses get_the_time() Get the topic creation time
	 * @uses esc_attr() To sanitize the topic creation time
	 * @uses bbp_get_topic_last_active_time() To get the time when the topic was
	 *                                    last active
	 * @uses do_action() Calls 'bbp_admin_topics_column_data' with the
	 *                    column and topic id
	 */
	function topics_column_data( $column, $topic_id ) {

		if ( $this->bail() ) return;

		// Get topic forum ID
		$forum_id = bbp_get_topic_forum_id( $topic_id );

		// Populate column data
		switch ( $column ) {

			// Forum
			case 'bbp_topic_forum' :

				// Output forum name
				if ( !empty( $forum_id ) ) {
					
					// Forum Title
					$forum_title = bbp_get_forum_title( $forum_id );
					if ( empty( $forum_title ) ) {
						$forum_title = __( 'No Forum', 'bbpress' );
					}

					// Output the title
					echo $forum_title;

				} else {
					_e( '(No Forum)', 'bbpress' );
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
				printf( __( '%1$s <br /> %2$s', 'bbpress' ),
					get_the_date(),
					esc_attr( get_the_time() )
				);

				break;

			// Freshness
			case 'bbp_topic_freshness' :
				$last_active = bbp_get_topic_last_active_time( $topic_id, false );
				if ( !empty( $last_active ) ) {
					echo $last_active;
				} else {
					_e( 'No Replies', 'bbpress' ); // This should never happen
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
	 * @since bbPress (r2485)
	 *
	 * @param array $actions Actions
	 * @param array $topic Topic object
	 * @uses bbp_get_topic_post_type() To get the topic post type
	 * @uses bbp_topic_content() To output topic content
	 * @uses bbp_get_topic_permalink() To get the topic link
	 * @uses bbp_get_topic_title() To get the topic title
	 * @uses current_user_can() To check if the current user can edit or
	 *                           delete the topic
	 * @uses bbp_is_topic_open() To check if the topic is open
	 * @uses bbp_is_topic_spam() To check if the topic is marked as spam
	 * @uses bbp_is_topic_sticky() To check if the topic is a sticky or a
	 *                              super sticky
	 * @uses get_post_type_object() To get the topic post type object
	 * @uses add_query_arg() To add custom args to the url
	 * @uses remove_query_arg() To remove custom args from the url
	 * @uses wp_nonce_url() To nonce the url
	 * @uses get_delete_post_link() To get the delete post link of the topic
	 * @return array $actions Actions
	 */
	public function topics_row_actions( $actions, $topic ) {

		if ( $this->bail() ) return $actions;

		unset( $actions['inline hide-if-no-js'] );

		// Show view link if it's not set, the topic is trashed and the user can view trashed topics
		if ( empty( $actions['view'] ) && ( bbp_get_trash_status_id() == $topic->post_status ) && current_user_can( 'view_trash' ) )
			$actions['view'] = '<a href="' . bbp_get_topic_permalink( $topic->ID ) . '" title="' . esc_attr( sprintf( __( 'View &#8220;%s&#8221;', 'bbpress' ), bbp_get_topic_title( $topic->ID ) ) ) . '" rel="permalink">' . __( 'View', 'bbpress' ) . '</a>';

		// Only show the actions if the user is capable of viewing them :)
		if ( current_user_can( 'moderate', $topic->ID ) ) {

			// Close
			// Show the 'close' and 'open' link on published and closed posts only
			if ( in_array( $topic->post_status, array( bbp_get_public_status_id(), bbp_get_closed_status_id() ) ) ) {
				$close_uri = esc_url( wp_nonce_url( add_query_arg( array( 'topic_id' => $topic->ID, 'action' => 'bbp_toggle_topic_close' ), remove_query_arg( array( 'bbp_topic_toggle_notice', 'topic_id', 'failed', 'super' ) ) ), 'close-topic_' . $topic->ID ) );
				if ( bbp_is_topic_open( $topic->ID ) )
					$actions['closed'] = '<a href="' . $close_uri . '" title="' . esc_attr__( 'Close this topic', 'bbpress' ) . '">' . _x( 'Close', 'Close a Topic', 'bbpress' ) . '</a>';
				else
					$actions['closed'] = '<a href="' . $close_uri . '" title="' . esc_attr__( 'Open this topic',  'bbpress' ) . '">' . _x( 'Open',  'Open a Topic',  'bbpress' ) . '</a>';
			}

			// Dont show sticky if topic links is spam or trash
			if ( !bbp_is_topic_spam( $topic->ID ) && !bbp_is_topic_trash( $topic->ID ) ) {

				// Sticky
				$stick_uri  = esc_url( wp_nonce_url( add_query_arg( array( 'topic_id' => $topic->ID, 'action' => 'bbp_toggle_topic_stick' ), remove_query_arg( array( 'bbp_topic_toggle_notice', 'topic_id', 'failed', 'super' ) ) ), 'stick-topic_'  . $topic->ID ) );
				if ( bbp_is_topic_sticky( $topic->ID ) ) {
					$actions['stick'] = '<a href="' . $stick_uri . '" title="' . esc_attr__( 'Unstick this topic', 'bbpress' ) . '">' . __( 'Unstick', 'bbpress' ) . '</a>';
				} else {
					$super_uri        = esc_url( wp_nonce_url( add_query_arg( array( 'topic_id' => $topic->ID, 'action' => 'bbp_toggle_topic_stick', 'super' => '1' ), remove_query_arg( array( 'bbp_topic_toggle_notice', 'topic_id', 'failed', 'super' ) ) ), 'stick-topic_'  . $topic->ID ) );
					$actions['stick'] = '<a href="' . $stick_uri . '" title="' . esc_attr__( 'Stick this topic to its forum', 'bbpress' ) . '">' . __( 'Stick', 'bbpress' ) . '</a> (<a href="' . $super_uri . '" title="' . esc_attr__( 'Stick this topic to front', 'bbpress' ) . '">' . __( 'to front', 'bbpress' ) . '</a>)';
				}
			}

			// Spam
			$spam_uri  = esc_url( wp_nonce_url( add_query_arg( array( 'topic_id' => $topic->ID, 'action' => 'bbp_toggle_topic_spam' ), remove_query_arg( array( 'bbp_topic_toggle_notice', 'topic_id', 'failed', 'super' ) ) ), 'spam-topic_'  . $topic->ID ) );
			if ( bbp_is_topic_spam( $topic->ID ) )
				$actions['spam'] = '<a href="' . $spam_uri . '" title="' . esc_attr__( 'Mark the topic as not spam', 'bbpress' ) . '">' . __( 'Not spam', 'bbpress' ) . '</a>';
			else
				$actions['spam'] = '<a href="' . $spam_uri . '" title="' . esc_attr__( 'Mark this topic as spam',    'bbpress' ) . '">' . __( 'Spam',     'bbpress' ) . '</a>';

		}

		// Do not show trash links for spam topics, or spam links for trashed topics
		if ( current_user_can( 'delete_topic', $topic->ID ) ) {
			if ( bbp_get_trash_status_id() == $topic->post_status ) {
				$post_type_object   = get_post_type_object( bbp_get_topic_post_type() );
				$actions['untrash'] = "<a title='" . esc_attr__( 'Restore this item from the Trash', 'bbpress' ) . "' href='" . wp_nonce_url( add_query_arg( array( '_wp_http_referer' => add_query_arg( array( 'post_type' => bbp_get_topic_post_type() ), admin_url( 'edit.php' ) ) ), admin_url( sprintf( $post_type_object->_edit_link . '&amp;action=untrash', $topic->ID ) ) ), 'untrash-' . $topic->post_type . '_' . $topic->ID ) . "'>" . __( 'Restore', 'bbpress' ) . "</a>";
			} elseif ( EMPTY_TRASH_DAYS ) {
				$actions['trash'] = "<a class='submitdelete' title='" . esc_attr__( 'Move this item to the Trash', 'bbpress' ) . "' href='" . add_query_arg( array( '_wp_http_referer' => add_query_arg( array( 'post_type' => bbp_get_topic_post_type() ), admin_url( 'edit.php' ) ) ), get_delete_post_link( $topic->ID ) ) . "'>" . __( 'Trash', 'bbpress' ) . "</a>";
			}

			if ( bbp_get_trash_status_id() == $topic->post_status || !EMPTY_TRASH_DAYS ) {
				$actions['delete'] = "<a class='submitdelete' title='" . esc_attr__( 'Delete this item permanently', 'bbpress' ) . "' href='" . add_query_arg( array( '_wp_http_referer' => add_query_arg( array( 'post_type' => bbp_get_topic_post_type() ), admin_url( 'edit.php' ) ) ), get_delete_post_link( $topic->ID, '', true ) ) . "'>" . __( 'Delete Permanently', 'bbpress' ) . "</a>";
			} elseif ( bbp_get_spam_status_id() == $topic->post_status ) {
				unset( $actions['trash'] );
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
	public function filter_dropdown() {

		if ( $this->bail() ) return;

		// Add Empty Spam button
		if ( !empty( $_GET['post_status'] ) && ( bbp_get_spam_status_id() == $_GET['post_status'] ) && current_user_can( 'moderate' ) ) {
			wp_nonce_field( 'bulk-destroy', '_destroy_nonce' );
			$title = esc_attr__( 'Empty Spam', 'bbpress' );
			submit_button( $title, 'button-secondary apply', 'delete_all', false );
		}

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

		if ( $this->bail() ) return $query_vars;

		// Add post_parent query_var if one is present
		if ( !empty( $_GET['bbp_forum_id'] ) ) {
			$query_vars['meta_key']   = '_bbp_forum_id';
			$query_vars['meta_value'] = $_GET['bbp_forum_id'];
		}

		// Return manipulated query_vars
		return $query_vars;
	}

	/**
	 * Custom user feedback messages for topic post type
	 *
	 * @since bbPress (r3080)
	 *
	 * @global int $post_ID
	 * @uses bbp_get_topic_permalink()
	 * @uses wp_post_revision_title()
	 * @uses esc_url()
	 * @uses add_query_arg()
	 *
	 * @param array $messages
	 *
	 * @return array
	 */
	public function updated_messages( $messages ) {
		global $post_ID;

		if ( $this->bail() ) return $messages;

		// URL for the current topic
		$topic_url = bbp_get_topic_permalink( $post_ID );

		// Current topic's post_date
		$post_date = bbp_get_global_post_field( 'post_date', 'raw' );

		// Messages array
		$messages[$this->post_type] = array(
			0 =>  '', // Left empty on purpose

			// Updated
			1 =>  sprintf( __( 'Topic updated. <a href="%s">View topic</a>', 'bbpress' ), $topic_url ),

			// Custom field updated
			2 => __( 'Custom field updated.', 'bbpress' ),

			// Custom field deleted
			3 => __( 'Custom field deleted.', 'bbpress' ),

			// Topic updated
			4 => __( 'Topic updated.', 'bbpress' ),

			// Restored from revision
			// translators: %s: date and time of the revision
			5 => isset( $_GET['revision'] )
					? sprintf( __( 'Topic restored to revision from %s', 'bbpress' ), wp_post_revision_title( (int) $_GET['revision'], false ) )
					: false,

			// Topic created
			6 => sprintf( __( 'Topic created. <a href="%s">View topic</a>', 'bbpress' ), $topic_url ),

			// Topic saved
			7 => __( 'Topic saved.', 'bbpress' ),

			// Topic submitted
			8 => sprintf( __( 'Topic submitted. <a target="_blank" href="%s">Preview topic</a>', 'bbpress' ), esc_url( add_query_arg( 'preview', 'true', $topic_url ) ) ),

			// Topic scheduled
			9 => sprintf( __( 'Topic scheduled for: <strong>%1$s</strong>. <a target="_blank" href="%2$s">Preview topic</a>', 'bbpress' ),
					// translators: Publish box date format, see http://php.net/date
					date_i18n( __( 'M j, Y @ G:i', 'bbpress' ),
					strtotime( $post_date ) ),
					$topic_url ),

			// Topic draft updated
			10 => sprintf( __( 'Topic draft updated. <a target="_blank" href="%s">Preview topic</a>', 'bbpress' ), esc_url( add_query_arg( 'preview', 'true', $topic_url ) ) ),
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
 * @since bbPress (r2596)
 *
 * @uses BBP_Forums_Admin
 */
function bbp_admin_topics() {
	bbpress()->admin->topics = new BBP_Topics_Admin();
}
