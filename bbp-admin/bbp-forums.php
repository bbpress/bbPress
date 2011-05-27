<?php

/**
 * bbPress Forum Admin Class
 *
 * @package bbPress
 * @subpackage Administration
 */

// Exit if accessed directly
if ( !defined( 'ABSPATH' ) ) exit;

if ( !class_exists( 'BBP_Forums_Admin' ) ) :
/**
 * Loads bbPress forums admin area
 *
 * @package bbPress
 * @subpackage Administration
 * @since bbPress (r2464)
 */
class BBP_Forums_Admin {

	/** Variables *************************************************************/

	/**
	 * @var The post type of this admin component
	 */
	var $post_type = '';

	/** Functions *************************************************************/

	/**
	 * The main bbPress forums admin loader (PHP4 compat)
	 *
	 * @since bbPress (r2515)
	 *
	 * @uses BBP_Forums_Admin::_setup_globals() Setup the globals needed
	 * @uses BBP_Forums_Admin::_setup_actions() Setup the hooks and actions
	 */
	function BBP_Forums_Admin() {
		$this->__construct();
	}

	/**
	 * The main bbPress forums admin loader
	 *
	 * @since bbPress (r2515)
	 *
	 * @uses BBP_Forums_Admin::_setup_globals() Setup the globals needed
	 * @uses BBP_Forums_Admin::_setup_actions() Setup the hooks and actions
	 */
	function __construct() {
		$this->_setup_globals();
		$this->_setup_actions();
		$this->_setup_help();
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
		add_action( 'admin_head',            array( $this, 'admin_head'       ) );

		// Messages
		add_filter( 'post_updated_messages', array( $this, 'updated_messages' ) );

		// Metabox actions
		add_action( 'add_meta_boxes',        array( $this, 'attributes_metabox'      ) );
		add_action( 'save_post',             array( $this, 'attributes_metabox_save' ) );

		// Column headers.
		add_filter( 'manage_' . $this->post_type . '_posts_columns',        array( $this, 'column_headers' )        );

		// Columns (in page row)
		add_action( 'manage_' . $this->post_type . '_posts_custom_column',  array( $this, 'column_data'    ), 10, 2 );
		add_filter( 'page_row_actions',                                     array( $this, 'row_actions'    ), 10, 2 );
	}

	/**
	 * Admin globals
	 *
	 * @since bbPress (r2646)
	 * @access private
	 */
	function _setup_globals() {

		// Setup the post type for this admin component
		$this->post_type = bbp_get_forum_post_type();
	}

	/**
	 * Contextual help for forums
	 *
	 * @since bbPress (r3119)
	 * @access private
	 */
	function _setup_help() {

		// Prevent debug notices
		$contextual_help = '';

		/** New/Edit **********************************************************/

		$bbp_contextual_help[] = __( 'The forum title field and the big forum editing area are fixed in place, but you can reposition all the other boxes using drag and drop, and can minimize or expand them by clicking the title bar of the box. Use the Screen Options tab to unhide more boxes (like Slug) or to choose a 1- or 2-column layout for this screen.', 'bbpress' );
		$bbp_contextual_help[] = __( '<strong>Title</strong> - Enter a title for your forum. After you enter a title, you will see the permalink appear below it, which is fully editable.', 'bbpress' );
		$bbp_contextual_help[] = __( '<strong>Post editor</strong> - Enter the description for your forum. There are two modes of editing: Visual and HTML. Choose the mode by clicking on the appropriate tab. Visual mode gives you a WYSIWYG editor. Click the last icon in the row to get a second row of controls. The screen icon just before that allows you to expand the edit box to full screen. The HTML mode allows you to enter raw HTML along with your forum text. You can insert media files by clicking the icons above the post editor and following the directions.', 'bbpress' );
		$bbp_contextual_help[] = __( '<strong>Forum Attributes</strong> - Select the various attributes that your forum should have:', 'bbpress' );
		$bbp_contextual_help[] =
			'<ul>' .
				'<li>' . __( 'Forum Type determines whether it is a Forum (by default) or a Category, which means no new topics (only other forums) can be created within it.', 'bbpress' ) . '</li>' .
				'<li>' . __( 'Forum Status controls whether it is open (and thus can be posted to) or closed (thus not able to be posted to).',                                 'bbpress' ) . '</li>' .
				'<li>' . __( 'Visibility can be set to either Public (by default, seen by everyone), Private (seen only by chosen users), and Hidden (hidden from all users).', 'bbpress' ) . '</li>' .
				'<li>' . __( 'Parent turns the forum into a child forum of the selected forum/category in the dropdown.',                                                       'bbpress' ) . '</li>' .
				'<li>' . __( 'Order determines the order that forums in the given hierarchy are displayed (lower numbers first, higher numbers last).',                         'bbpress' ) . '</li>' .
			'</ul>';

		$bbp_contextual_help[] = __( '<strong>Publish</strong> - The Publish box will allow you to Preview your forum before it is published, Publish your forum to your site, or Move to Trash will move your forum to the trash.', 'bbpress' );
		$bbp_contextual_help[] = __( '<strong>Revisions</strong> - Revisions show past versions of the saved forum. Each revision can be compared to the current version, or another revision. Revisions can also be restored to the current version.', 'bbpress' );
		$bbp_contextual_help[] = __( '<strong>For more information:</strong>', 'bbpress' );
		$bbp_contextual_help[] =
			'<ul>' .
				'<li>' . __( '<a href="http://bbpress.org/documentation/">bbPress Documentation</a>', 'bbpress' ) . '</li>' .
				'<li>' . __( '<a href="http://bbpress.org/forums/">bbPress Support Forums</a>',       'bbpress' ) . '</li>' .
			'</ul>';

		// Wrap each help item in paragraph tags
		foreach( $bbp_contextual_help as $paragraph )
			$contextual_help .= '<p>' . $paragraph . '</p>';

		// Add help
		add_contextual_help( bbp_get_forum_post_type(), $contextual_help );

		// Reset
		$contextual_help = $bbp_contextual_help = '';

		/** Post Rows *********************************************************/

		$bbp_contextual_help[] = __( 'This screen displays the forums available on your site.',           'bbpress' );
		$bbp_contextual_help[] = __( 'You can customize the display of this screen in a number of ways:', 'bbpress' );
		$bbp_contextual_help[] =
			'<ul>' .
				'<li>' . __( 'You can hide/display columns based on your needs and decide how many forums to list per screen using the Screen Options tab.',                                                                                                                                'bbpress' ) . '</li>' .
				'<li>' . __( 'You can filter the list of forums by forum status using the text links in the upper left to show All, Published, or Trashed forums. The default view is to show all forums.',                                                                                 'bbpress' ) . '</li>' .
				'<li>' . __( 'You can refine the list to show only forums from a specific month by using the dropdown menus above the forums list. Click the Filter button after making your selection. You also can refine the list by clicking on the forum creator in the forums list.', 'bbpress' ) . '</li>' .
			'</ul>';

		$bbp_contextual_help[] = __( 'Hovering over a row in the forums list will display action links that allow you to manage your forum. You can perform the following actions:', 'bbpress' );
		$bbp_contextual_help[] =
			'<ul>' .
				'<li>' . __( 'Edit takes you to the editing screen for that forum. You can also reach that screen by clicking on the forum title.', 'bbpress' ) . '</li>' .
				'<li>' . __( 'Trash removes your forum from this list and places it in the trash, from which you can permanently delete it.',       'bbpress' ) . '</li>' .
				'<li>' . __( 'View will take you to your live forum to view the forum.',                                                            'bbpress' ) . '</li>' .
			'</ul>';

		$bbp_contextual_help[] = __( 'You can also edit multiple forums at once. Select the forums you want to edit using the checkboxes, select Edit from the Bulk Actions menu and click Apply. You will be able to change the metadata for all selected forums at once. To remove a forum from the grouping, just click the x next to its name in the Bulk Edit area that appears.', 'bbpress' );
		$bbp_contextual_help[] = __( 'The Bulk Actions menu may also be used to delete multiple forums at once. Select Delete from the dropdown after making your selection.', 'bbpress' );
		$bbp_contextual_help[] = __( '<strong>For more information:</strong>', 'bbpress' );
		$bbp_contextual_help[] =
			'<ul>' .
				'<li>' . __( '<a href="http://bbpress.org/documentation/">bbPress Documentation</a>', 'bbpress' ) . '</li>' .
				'<li>' . __( '<a href="http://bbpress.org/forums/">bbPress Support Forums</a>',       'bbpress' ) . '</li>' .
			'</ul>';

		// Wrap each help item in paragraph tags
		foreach( $bbp_contextual_help as $paragraph )
			$contextual_help .= '<p>' . $paragraph . '</p>';

		// Add help
		add_contextual_help( 'edit-' . bbp_get_forum_post_type(), $contextual_help );
	}

	/**
	 * Add the forum attributes metabox
	 *
	 * @since bbPress (r2746)
	 *
	 * @uses bbp_get_forum_post_type() To get the forum post type
	 * @uses add_meta_box() To add the metabox
	 * @uses do_action() Calls 'bbp_forum_attributes_metabox'
	 */
	function attributes_metabox() {
		add_meta_box (
			'bbp_forum_attributes',
			__( 'Forum Attributes', 'bbpress' ),
			'bbp_forum_metabox',
			$this->post_type,
			'side',
			'high'
		);

		do_action( 'bbp_forum_attributes_metabox' );
	}

	/**
	 * Pass the forum attributes for processing
	 *
	 * @since bbPress (r2746)
	 *
	 * @param int $forum_id Forum id
	 * @uses current_user_can() To check if the current user is capable of
	 *                           editing the forum
	 * @uses bbp_get_forum() To get the forum
	 * @uses bbp_is_forum_closed() To check if the forum is closed
	 * @uses bbp_is_forum_category() To check if the forum is a category
	 * @uses bbp_is_forum_private() To check if the forum is private
	 * @uses bbp_close_forum() To close the forum
	 * @uses bbp_open_forum() To open the forum
	 * @uses bbp_categorize_forum() To make the forum a category
	 * @uses bbp_normalize_forum() To make the forum normal (not category)
	 * @uses bbp_privatize_forum() To mark the forum as private
	 * @uses bbp_publicize_forum() To mark the forum as public
	 * @uses do_action() Calls 'bbp_forum_attributes_metabox_save' with the
	 *                    forum id
	 * @return int Forum id
	 */
	function attributes_metabox_save( $forum_id ) {
		global $bbp;

		// Bail if doing an autosave
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE )
			return $forum_id;

		// Bail if current user cannot edit this forum
		if ( !current_user_can( 'edit_forum', $forum_id ) )
			return $forum_id;

		// Load the forum
		if ( !$forum = bbp_get_forum( $forum_id ) )
			return $forum_id;

		// Closed?
		if ( !empty( $_POST['bbp_forum_status'] ) && in_array( $_POST['bbp_forum_status'], array( 'open', 'closed' ) ) ) {
			if ( 'closed' == $_POST['bbp_forum_status'] && !bbp_is_forum_closed( $forum_id, false ) )
				bbp_close_forum( $forum_id );
			elseif ( 'open' == $_POST['bbp_forum_status'] && bbp_is_forum_closed( $forum_id, false ) )
				bbp_open_forum( $forum_id );
		}

		// Category?
		if ( !empty( $_POST['bbp_forum_type'] ) && in_array( $_POST['bbp_forum_type'], array( 'forum', 'category' ) ) ) {
			if ( 'category' == $_POST['bbp_forum_type'] && !bbp_is_forum_category( $forum_id ) )
				bbp_categorize_forum( $forum_id );
			elseif ( 'forum' == $_POST['bbp_forum_type'] && bbp_is_forum_category( $forum_id ) )
				bbp_normalize_forum( $forum_id );
		}

		// Visibility
		if ( !empty( $_POST['bbp_forum_visibility'] ) && in_array( $_POST['bbp_forum_visibility'], array( 'publish', 'private', 'hidden' ) ) ) {

			// Get forums current visibility
			$visibility = bbp_get_forum_visibility( $forum_id );

			// If new visibility is different, change it
			if ( $visibility != $_POST['bbp_forum_visibility'] ) {

				// What is the new forum visibility setting?
				switch ( $_POST['bbp_forum_visibility'] ) {

					// Hidden
					case 'hidden'  :
						bbp_hide_forum( $forum_id, $visibility );
						break;

					// Private
					case 'private' :
						bbp_privatize_forum( $forum_id, $visibility );
						break;

					// Publish (default)
					case 'publish'  :
					default        :
						bbp_publicize_forum( $forum_id, $visibility );
						break;
				}
			}
		}

		do_action( 'bbp_forum_attributes_metabox_save', $forum_id );

		return $forum_id;
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

				#misc-publishing-actions,
				#save-post {
					display: none;
				}

				strong.label {
					display: inline-block;
					width: 60px;
				}

				#bbp_forum_attributes hr {
					border-style: solid;
					border-width: 1px;
					border-color: #ccc #fff #fff #ccc;
				}

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

		<?php endif; ?>

		<?php
	}

	/**
	 * Manage the column headers for the forums page
	 *
	 * @since bbPress (r2485)
	 *
	 * @param array $columns The columns
	 * @uses apply_filters() Calls 'bbp_admin_forums_column_headers' with
	 *                        the columns
	 * @return array $columns bbPress forum columns
	 */
	function column_headers( $columns ) {
		$columns = array (
			'cb'                    => '<input type="checkbox" />',
			'title'                 => __( 'Forum',     'bbpress' ),
			'bbp_forum_topic_count' => __( 'Topics',    'bbpress' ),
			'bbp_forum_reply_count' => __( 'Replies',   'bbpress' ),
			'author'                => __( 'Creator',   'bbpress' ),
			'bbp_forum_created'     => __( 'Created' ,  'bbpress' ),
			'bbp_forum_freshness'   => __( 'Freshness', 'bbpress' )
		);

		return apply_filters( 'bbp_admin_forums_column_headers', $columns );
	}

	/**
	 * Print extra columns for the forums page
	 *
	 * @since bbPress (r2485)
	 *
	 * @param string $column Column
	 * @param int $forum_id Forum id
	 * @uses bbp_forum_topic_count() To output the forum topic count
	 * @uses bbp_forum_reply_count() To output the forum reply count
	 * @uses get_the_date() Get the forum creation date
	 * @uses get_the_time() Get the forum creation time
	 * @uses esc_attr() To sanitize the forum creation time
	 * @uses bbp_get_forum_last_active_time() To get the time when the forum was
	 *                                    last active
	 * @uses do_action() Calls 'bbp_admin_forums_column_data' with the
	 *                    column and forum id
	 */
	function column_data( $column, $forum_id ) {
		switch ( $column ) {
			case 'bbp_forum_topic_count' :
				bbp_forum_topic_count( $forum_id );
				break;

			case 'bbp_forum_reply_count' :
				bbp_forum_reply_count( $forum_id );
				break;

			case 'bbp_forum_created':
				printf( __( '%1$s <br /> %2$s', 'bbpress' ),
					get_the_date(),
					esc_attr( get_the_time() )
				);

				break;

			case 'bbp_forum_freshness' :
				if ( $last_active = bbp_get_forum_last_active_time( $forum_id, false ) )
					printf( __( '%s ago', 'bbpress' ), $last_active );
				else
					_e( 'No Topics', 'bbpress' );

				break;

			default:
				do_action( 'bbp_admin_forums_column_data', $column, $forum_id );
				break;
		}
	}

	/**
	 * Forum Row actions
	 *
	 * Remove the quick-edit action link and display the description under
	 * the forum title
	 *
	 * @since bbPress (r2577)
	 *
	 * @param array $actions Actions
	 * @param array $forum Forum object
	 * @uses the_content() To output forum description
	 * @return array $actions Actions
	 */
	function row_actions( $actions, $forum ) {
		if ( $forum->post_type == $this->post_type ) {
			unset( $actions['inline hide-if-no-js'] );

			// simple hack to show the forum description under the title
			bbp_forum_content( $forum->ID );
		}

		return $actions;
	}

	/**
	 * Custom user feedback messages for forum post type
	 *
	 * @since bbPress (r3080)
	 *
	 * @global WP_Query $post
	 * @global int $post_ID
	 * @uses get_post_type()
	 * @uses bbp_get_forum_permalink()
	 * @uses wp_post_revision_title()
	 * @uses esc_url()
	 * @uses add_query_arg()
	 *
	 * @param array $messages
	 *
	 * @return array
	 */
	function updated_messages( $messages ) {
		global $post, $post_ID;

		if ( get_post_type( $post_ID ) != $this->post_type )
			return $messages;

		// URL for the current forum
		$forum_url = bbp_get_forum_permalink( $post_ID );

		// Messages array
		$messages[$this->post_type] = array(
			0 =>  '', // Left empty on purpose

			// Updated
			1 =>  sprintf( __( 'Forum updated. <a href="%s">View forum</a>' ), $forum_url ),

			// Custom field updated
			2 => __( 'Custom field updated.', 'bbpress' ),

			// Custom field deleted
			3 => __( 'Custom field deleted.', 'bbpress' ),

			// Forum updated
			4 => __( 'Forum updated.', 'bbpress' ),

			// Restored from revision
			// translators: %s: date and time of the revision
			5 => isset( $_GET['revision'] )
					? sprintf( __( 'Forum restored to revision from %s', 'bbpress' ), wp_post_revision_title( (int) $_GET['revision'], false ) )
					: false,

			// Forum created
			6 => sprintf( __( 'Forum created. <a href="%s">View forum</a>', 'bbpress' ), $forum_url ),

			// Forum saved
			7 => __( 'Forum saved.', 'bbpress' ),

			// Forum submitted
			8 => sprintf( __( 'Forum submitted. <a target="_blank" href="%s">Preview forum</a>', 'bbpress' ), esc_url( add_query_arg( 'preview', 'true', $forum_url ) ) ),

			// Forum scheduled
			9 => sprintf( __( 'Forum scheduled for: <strong>%1$s</strong>. <a target="_blank" href="%2$s">Preview forum</a>', 'bbpress' ),
					// translators: Publish box date format, see http://php.net/date
					date_i18n( __( 'M j, Y @ G:i' ),
					strtotime( $post->post_date ) ),
					$forum_url ),

			// Forum draft updated
			10 => sprintf( __( 'Forum draft updated. <a target="_blank" href="%s">Preview forum</a>', 'bbpress' ), esc_url( add_query_arg( 'preview', 'true', $forum_url ) ) ),
		);

		return $messages;
	}
}
endif; // class_exists check

/**
 * Setup bbPress Forums Admin
 *
 * @since bbPress (r2596)
 *
 * @uses BBP_Forums_Admin
 */
function bbp_forums_admin() {
	global $bbp;

	$bbp->admin->forums = new BBP_Forums_Admin();
}

?>
