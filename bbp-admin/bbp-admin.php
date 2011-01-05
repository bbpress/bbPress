<?php

if ( !class_exists( 'BBP_Admin' ) ) :
/**
 * BBP_Admin
 *
 * Loads plugin admin area
 *
 * @package bbPress
 * @subpackage Admin
 * @since bbPress (r2464)
 */
class BBP_Admin {

	/**
	 * The main bbPress admin loader
	 */
	function BBP_Admin () {
		$this->_setup_globals();
		$this->_includes();
		$this->_setup_actions();
	}

	/**
	 * _setup_actions ()
	 *
	 * Setup the admin hooks and actions
	 */
	function _setup_actions () {
		global $bbp;

		/** General Actions ***************************************************/

		// Add notice if not using a bbPress theme
		add_action( 'admin_notices',               array( $this, 'activation_notice'       )        );

		// Add link to settings page
		add_filter( 'plugin_action_links',         array( $this, 'add_settings_link'       ), 10, 2 );

		// Add menu item to settings menu
		add_action( 'admin_menu',                  array( $this, 'admin_menus'             )        );

		// Add the settings
		add_action( 'admin_init',                  array( $this, 'register_admin_settings' )        );

		// Attach the bbPress admin init action to the WordPress admin init action.
		add_action( 'admin_init',                  array( $this, 'init'                    )        );

		// Add some general styling to the admin area
		add_action( 'admin_head',                  array( $this, 'admin_head'              )        );

		/** User Actions ******************************************************/

		// User profile edit/display actions
		add_action( 'edit_user_profile',           array( $this, 'user_profile_forums' ) );
		add_action( 'show_user_profile',           array( $this, 'user_profile_forums' ) );

		// User profile save actions
		add_action( 'personal_options_update',     array( $this, 'user_profile_update' ) );
		add_action( 'edit_user_profile_update',    array( $this, 'user_profile_update' ) );

		/** Forums ************************************************************/

		// Forum column headers.
		add_filter( 'manage_' . $bbp->forum_id . '_posts_columns',  array( $this, 'forums_column_headers' ) );

		// Forum metabox actions
		add_action( 'add_meta_boxes',              array( $this, 'forum_attributes_metabox'      ) );
		add_action( 'save_post',                   array( $this, 'forum_attributes_metabox_save' ) );

		// Forum columns (in page row)
		add_action( 'manage_pages_custom_column',  array( $this, 'forums_column_data' ), 10, 2 );
		add_filter( 'page_row_actions',            array( $this, 'forums_row_actions' ), 10, 2 );

		/** Topics ************************************************************/

		// Topic column headers.
		add_filter( 'manage_' . $bbp->topic_id . '_posts_columns',  array( $this, 'topics_column_headers' ) );

		// Topic columns (in post row)
		add_action( 'manage_posts_custom_column',  array( $this, 'topics_column_data' ), 10, 2 );
		add_filter( 'post_row_actions',            array( $this, 'topics_row_actions' ), 10, 2 );

		// Topic metabox actions
		add_action( 'add_meta_boxes',              array( $this, 'topic_attributes_metabox'      ) );
		add_action( 'save_post',                   array( $this, 'topic_attributes_metabox_save' ) );

		// Check if there are any bbp_toggle_topic_* requests on admin_init, also have a message displayed
		add_action( 'bbp_admin_init',              array( $this, 'toggle_topic'        ) );
		add_action( 'admin_notices',               array( $this, 'toggle_topic_notice' ) );

		/** Replies ***********************************************************/

		// Reply column headers.
		add_filter( 'manage_' . $bbp->reply_id . '_posts_columns',  array( $this, 'replies_column_headers' ) );

		// Reply columns (in post row)
		add_action( 'manage_posts_custom_column',  array( $this, 'replies_column_data' ), 10, 2 );
		add_filter( 'post_row_actions',            array( $this, 'replies_row_actions' ), 10, 2 );

		// Reply metabox actions
		add_action( 'add_meta_boxes',              array( $this, 'reply_attributes_metabox'      ) );
		add_action( 'save_post',                   array( $this, 'reply_attributes_metabox_save' ) );

		// Register bbPress admin style
		add_action( 'admin_init',                  array( $this, 'register_admin_style' ) );

		// Check if there are any bbp_toggle_reply_* requests on admin_init, also have a message displayed
		add_action( 'bbp_admin_init',              array( $this, 'toggle_reply'        ) );
		add_action( 'admin_notices',               array( $this, 'toggle_reply_notice' ) );
	}

	/**
	 * _includes ()
	 *
	 * Include required files
	 */
	function _includes () {
		require_once( 'bbp-tools.php'     );
		require_once( 'bbp-settings.php'  );
		require_once( 'bbp-functions.php' );
	}

	/**
	 * _setup_globals ()
	 *
	 * Admin globals
	 */
	function _setup_globals () {
		// Nothing to do here yet
	}

	/**
	 * admin_menus ()
	 *
	 * Add the navigational menu elements
	 */
	function admin_menus () {
		add_management_page( __( 'Recount', 'bbpress' ), __( 'Recount', 'bbpress' ), 'manage_options', 'bbp-recount', 'bbp_admin_tools'    );
		add_options_page   ( __( 'Forums',  'bbpress' ), __( 'Forums',  'bbpress' ), 'manage_options', 'bbpress',     'bbp_admin_settings' );
	}

	/**
	 * register_admin_settings ()
	 *
	 * Register the settings
	 */
	function register_admin_settings () {

		// Add the main section
		add_settings_section( 'bbp_main', __( 'Main Settings', 'bbpress' ), 'bbp_admin_setting_callback_section', 'bbpress' );

		// Edit lock setting
		add_settings_field( '_bbp_edit_lock',            __( 'Lock post editing after', 'bbpress' ), 'bbp_admin_setting_callback_editlock',      'bbpress', 'bbp_main' );
	 	register_setting  ( 'bbpress',                   '_bbp_edit_lock',                           'intval'                                                          );

		// Throttle setting
		add_settings_field( '_bbp_throttle_time',        __( 'Throttle time',           'bbpress' ), 'bbp_admin_setting_callback_throttle',      'bbpress', 'bbp_main' );
	 	register_setting  ( 'bbpress',                   '_bbp_throttle_time',                       'intval'                                                          );

		// Allow subscriptions setting
		add_settings_field( '_bbp_enable_subscriptions', __( 'Allow Subscriptions',     'bbpress' ), 'bbp_admin_setting_callback_subscriptions', 'bbpress', 'bbp_main' );
	 	register_setting  ( 'bbpress',                   '_bbp_enable_subscriptions',                'intval'                                                          );

		// Allow anonymous posting setting
		add_settings_field( '_bbp_allow_anonymous',      __( 'Allow Anonymous Posting', 'bbpress' ), 'bbp_admin_setting_callback_anonymous',     'bbpress', 'bbp_main' );
	 	register_setting  ( 'bbpress',                   '_bbp_allow_anonymous',                     'intval'                                                          );

		do_action( 'bbp_register_admin_settings' );
	}

	/**
	 * activation_notice ()
	 *
	 * Admin area ctivation notice. Only appears when there are no addresses.
	 */
	function activation_notice () {
		if ( !current_user_can( 'switch_themes' ) )
			return;

		$current_theme = current_theme_info();

		if ( !in_array( 'bbpress', (array) $current_theme->tags ) ) { ?>

			<div id="message" class="updated fade">
				<p style="line-height: 150%"><?php printf( __( "<strong>bbPress is almost ready</strong>. First you'll need to <a href='%s'>activate a bbPress compatible theme</a>. We've bundled a child theme of Twenty Ten to get you started.", 'bbpress' ), admin_url( 'themes.php' ), admin_url( 'theme-install.php?type=tag&s=bbpress&tab=search' ) ) ?></p>
			</div>

		<?php }
	}

	/**
	 * add_settings_link ()
	 *
	 * Add Settings link to plugins area
	 *
	 * @return string Links
	 */
	function add_settings_link ( $links, $file ) {
		global $bbp;

		if ( plugin_basename( $bbp->file ) == $file ) {
			$settings_link = '<a href="' . add_query_arg( array( 'page' => 'bbpress' ), admin_url( 'options-general.php' ) ) . '">' . __( 'Settings', 'bbpress' ) . '</a>';
			array_unshift( $links, $settings_link );
		}
		return $links;
	}

	/**
	 * init ()
	 *
	 * bbPress's dedicated admin init action
	 *
	 * @uses do_action
	 */
	function init () {
		do_action( 'bbp_admin_init' );
	}

	/**
	 * forum_attributes_metabox ()
	 *
	 * Add the forum attributes metabox
	 *
	 * @uses add_meta_box
	 */
	function forum_attributes_metabox () {
		global $bbp;

		add_meta_box (
			'bbp_forum_attributes',
			__( 'Forum Attributes', 'bbpress' ),
			'bbp_forum_metabox',
			$bbp->forum_id,
			'side',
			'high'
		);

		do_action( 'bbp_forum_attributes_metabox' );
	}

	/**
	 * forum_attributes_metabox_save ()
	 *
	 * Pass the forum attributes for processing
	 *
	 * @param int $forum_id
	 * @return int
	 */
	function forum_attributes_metabox_save ( $forum_id ) {
		global $bbp;

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE )
			return $forum_id;

		if ( $bbp->forum_id != get_post_field( 'post_type', $forum_id ) )
			return $forum_id;

		if ( !current_user_can( 'edit_forum', $forum_id ) )
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

		// Private?
		if ( !empty( $_POST['bbp_forum_visibility'] ) && in_array( $_POST['bbp_forum_visibility'], array( 'public', 'private' ) ) ) {
			if ( 'private' == $_POST['bbp_forum_visibility'] && !bbp_is_forum_private( $forum_id, false ) )
				bbp_privatize_forum( $forum_id );
			elseif ( 'public' == $_POST['bbp_forum_visibility'] )
				bbp_publicize_forum( $forum_id );
		}

		do_action( 'bbp_forum_attributes_metabox_save' );

		return $forum_id;
	}

	/**
	 * topic_attributes_metabox ()
	 *
	 * Add the topic attributes metabox
	 *
	 * @uses add_meta_box
	 */
	function topic_attributes_metabox () {
		global $bbp;

		add_meta_box (
			'bbp_topic_attributes',
			__( 'Topic Attributes', 'bbpress' ),
			'bbp_topic_metabox',
			$bbp->topic_id,
			'side',
			'high'
		);

		do_action( 'bbp_topic_attributes_metabox' );
	}

	/**
	 * topic_attributes_metabox_save ()
	 *
	 * Pass the topic attributes for processing
	 *
	 * @param int $topic_id
	 * @return int
	 */
	function topic_attributes_metabox_save ( $topic_id ) {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE )
			return $topic_id;

		if ( !current_user_can( 'edit_topic', $topic_id ) )
			return $topic_id;

		// OK, we're authenticated: we need to find and save the data
		$parent_id = isset( $_topic['parent_id'] ) ? $_topic['parent_id'] : 0;

		do_action( 'bbp_topic_attributes_metabox_save' );

		return $parent_id;
	}

	/**
	 * reply_attributes_metabox ()
	 *
	 * Add the reply attributes metabox
	 */
	function reply_attributes_metabox () {
		global $bbp;

		add_meta_box (
			'bbp_reply_attributes',
			__( 'Reply Attributes', 'bbpress' ),
			'bbp_reply_metabox',
			$bbp->reply_id,
			'side',
			'high'
		);

		do_action( 'bbp_reply_attributes_metabox' );
	}

	/**
	 * reply_attributes_metabox_save ()
	 *
	 * Pass the reply attributes for processing
	 *
	 * @param int $reply_id
	 * @return int
	 */
	function reply_attributes_metabox_save ( $reply_id ) {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE )
			return $reply_id;

		if ( !current_user_can( 'edit_reply', $reply_id ) )
			return $reply_id;

		// OK, we're authenticated: we need to find and save the data
		$parent_id = isset( $_reply['parent_id'] ) ? $_reply['parent_id'] : 0;

		do_action( 'bbp_reply_attributes_metabox_save' );

		return $parent_id;
	}

	/**
	 * admin_head ()
	 *
	 * Add some general styling to the admin area
	 */
	function admin_head () {
		global $bbp, $post;

		// Icons for top level admin menus
		$menu_icon_url = $bbp->images_url . '/menu.png';
		$icon32_url    = $bbp->images_url . '/icons32.png';

		// Top level menu classes
		$forum_class   = sanitize_html_class( $bbp->forum_id );
		$topic_class   = sanitize_html_class( $bbp->topic_id );
		$reply_class   = sanitize_html_class( $bbp->reply_id ); ?>

		<style type="text/css" media="screen">
		/*<![CDATA[*/
			#menu-posts-<?php echo $forum_class; ?> .wp-menu-image {
				background: url(<?php echo $menu_icon_url; ?>) no-repeat 0px -32px;
			}
			#menu-posts-<?php echo $forum_class; ?>:hover .wp-menu-image,
			#menu-posts-<?php echo $forum_class; ?>.wp-has-current-submenu .wp-menu-image {
				background: url(<?php echo $menu_icon_url; ?>) no-repeat 0px 0px;
			}
			#icon-edit.icon32-posts-<?php echo $forum_class; ?> {
				background: url(<?php echo $icon32_url; ?>) no-repeat -4px 0px;
			}

			#menu-posts-<?php echo $topic_class; ?> .wp-menu-image {
				background: url(<?php echo $menu_icon_url; ?>) no-repeat -70px -32px;
			}
			#menu-posts-<?php echo $topic_class; ?>:hover .wp-menu-image,
			#menu-posts-<?php echo $topic_class; ?>.wp-has-current-submenu .wp-menu-image {
				background: url(<?php echo $menu_icon_url; ?>) no-repeat -70px 0px;
			}
			#icon-edit.icon32-posts-<?php echo $topic_class; ?> {
				background: url(<?php echo $icon32_url; ?>) no-repeat -4px -90px;
			}

			#menu-posts-<?php echo $reply_class; ?> .wp-menu-image {
				background: url(<?php echo $menu_icon_url; ?>) no-repeat -35px -32px;
			}
			#menu-posts-<?php echo $reply_class; ?>:hover .wp-menu-image,
			#menu-posts-<?php echo $reply_class; ?>.wp-has-current-submenu .wp-menu-image {
				background: url(<?php echo $menu_icon_url; ?>) no-repeat -35px 0px;
			}
			#icon-edit.icon32-posts-<?php echo $reply_class; ?> {
				background: url(<?php echo $icon32_url; ?>) no-repeat -4px -180px;
			}

<?php if ( $post->post_type == $bbp->forum_id ) : ?>

			#misc-publishing-actions, #save-post { display: none; }
			strong.label { display: inline-block; width: 60px; }
			#bbp_forum_attributes hr { border-style: solid; border-width: 1px; border-color: #ccc #fff #fff #ccc; }

<?php endif; ?>

<?php if ( bbp_is_forum() || bbp_is_topic() || bbp_is_reply() ) : ?>

			.column-bbp_forum_topic_count, .column-bbp_forum_reply_count, .column-bbp_topic_reply_count, .column-bbp_topic_voice_count { width: 8% !important; }
			.column-author,  .column-bbp_reply_author, .column-bbp_topic_author { width: 10% !important; }
			.column-bbp_topic_forum, .column-bbp_reply_forum, .column-bbp_reply_topic { width: 10% !important; }
			.column-bbp_forum_freshness, .column-bbp_topic_freshness { width: 10% !important; }
			.column-bbp_forum_created, .column-bbp_topic_created, .column-bbp_reply_created { width: 15% !important; }

			.status-closed { background-color: #eaeaea; }
			.status-spam { background-color: #faeaea; }
<?php endif; ?>

		/*]]>*/
		</style>
<?php
		// Add extra actions to bbPress admin header area
		do_action( 'bbp_admin_head' );
	}

	/**
	 * user_profile_update ()
	 *
	 * Responsible for showing additional profile options and settings
	 *
	 * @todo Everything
	 */
	function user_profile_update ( $user_id ) {
		// Add extra actions to bbPress profile update
		do_action( 'bbp_user_profile_update' );

		return false;
	}

	/**
	 * user_profile_forums ()
	 *
	 * Responsible for saving additional profile options and settings
	 *
	 * @todo Everything
	 */
	function user_profile_forums ( $profileuser ) {
		return false;

?>
		<h3><?php _e( 'Forums', 'bbpress' ); ?></h3>
		<table class="form-table">
			<tr valign="top">
				<th scope="row"><?php _e( 'Forums', 'bbpress' ); ?></th>
				<td>

				</td>
			</tr>
		</table>
<?php

		// Add extra actions to bbPress profile update
		do_action( 'bbp_user_profile_forums' );
	}

	/**
	 * forums_column_headers ()
	 *
	 * Manage the column headers for the forums page
	 *
	 * @param array $columns
	 * @return array $columns
	 */
	function forums_column_headers ( $columns ) {
		$columns = array (
			'cb'                    => '<input type="checkbox" />',
			'title'                 => __( 'Forum', 'bbpress' ),
			'bbp_forum_topic_count' => __( 'Topics', 'bbpress' ),
			'bbp_forum_reply_count' => __( 'Replies', 'bbpress' ),
			'author'                => __( 'Creator', 'bbpress' ),
			'bbp_forum_created'     => __( 'Created' , 'bbpress' ),
			'bbp_forum_freshness'   => __( 'Freshness', 'bbpress' )
		);

		return apply_filters( 'bbp_admin_forums_column_headers', $columns );
	}

	/**
	 * forums_column_data ( $column, $post_id )
	 *
	 * Print extra columns for the forums page
	 *
	 * @param string $column
	 * @param int $forum_id
	 */
	function forums_column_data ( $column, $forum_id ) {
		global $bbp, $typenow;

		if ( $typenow !== $bbp->forum_id )
			return $column;

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
				if ( $last_active = bbp_get_forum_last_active( $forum_id, false ) )
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
	 * forums_row_actions ( $actions, $post )
	 *
	 * Remove the quick-edit action link and display the description under the forum title
	 *
	 * @param array $actions
	 * @param array $forum
	 * @return array $actions
	 */
	function forums_row_actions ( $actions, $forum ) {
		global $bbp, $typenow;

		if ( $bbp->forum_id == $typenow ) {
			unset( $actions['inline'] );

			// simple hack to show the forum description under the title
			the_content();
		}

		return $actions;
	}

	/**
	 * toggle_topic ()
	 *
	 * Handles the admin-side opening/closing and spamming/unspamming of topics
	 *
	 * @since bbPress (r2727)
	 */
	function toggle_topic () {
		// Only proceed if GET is a topic toggle action
		if ( 'GET' == $_SERVER['REQUEST_METHOD'] && !empty( $_GET['action'] ) && in_array( $_GET['action'], array( 'bbp_toggle_topic_close', 'bbp_toggle_topic_spam' ) ) && !empty( $_GET['topic_id'] ) ) {
			global $bbp;

			$action    = $_GET['action'];            // What action is taking place?
			$topic_id  = (int) $_GET['topic_id'];    // What's the topic id?
			$success   = false;                      // Flag
			$post_data = array( 'ID' => $topic_id ); // Prelim array

			if ( !$topic = get_post( $topic_id ) ) // Which topic?
				wp_die( __( 'The topic was not found!', 'bbpress' ) );

			if ( !current_user_can( 'edit_topic', $topic->ID ) ) // What is the user doing here?
				wp_die( __( 'You do not have the permission to do that!', 'bbpress' ) );

			switch ( $action ) {
				case 'bbp_toggle_topic_close' :
					check_admin_referer( 'close-topic_' . $topic_id );

					$is_open = bbp_is_topic_open( $topic_id );
					$message = $is_open ? 'closed' : 'opened';
					$success = $is_open ? bbp_close_topic( $topic_id ) : bbp_open_topic( $topic_id );

					break;

				case 'bbp_toggle_topic_spam' :
					check_admin_referer( 'spam-topic_' . $topic_id );

					$is_spam = bbp_is_topic_spam( $topic_id );
					$message = $is_spam ? 'unspammed' : 'spammed';
					$success = $is_spam ? bbp_unspam_topic( $topic_id ) : bbp_spam_topic( $topic_id );

					break;
			}

			$message = array( 'bbp_topic_toggle_notice' => $message, 'topic_id' => $topic->ID );

			if ( false == $success || is_wp_error( $success ) )
				$message['failed'] = '1';

			// Do additional topic toggle actions (admin side)
			do_action( 'bbp_toggle_topic_admin', $success, $post_data, $action, $message );

			// Redirect back to the topic
			$redirect = add_query_arg( $message, remove_query_arg( array( 'action', 'topic_id' ) ) );
			wp_redirect( $redirect );

			// For good measure
			exit();

		}
	}

	/**
	 * toggle_topic_notice ()
	 *
	 * Display the success/error notices from toggle_topic()
	 *
	 * @since bbPress (r2727)
	 */
	function toggle_topic_notice () {
		// Only proceed if GET is a topic toggle action
		if ( 'GET' == $_SERVER['REQUEST_METHOD'] && !empty( $_GET['bbp_topic_toggle_notice'] ) && in_array( $_GET['bbp_topic_toggle_notice'], array( 'opened', 'closed', 'spammed', 'unspammed' ) ) && !empty( $_GET['topic_id'] ) ) {
			global $bbp;

			$notice     = $_GET['bbp_topic_toggle_notice'];         // Which notice?
			$topic_id   = (int) $_GET['topic_id'];                  // What's the topic id?
			$is_failure = !empty( $_GET['failed'] ) ? true : false; // Was that a failure?

			// Empty? No topic?
			if ( empty( $notice ) || empty( $topic_id ) || !$topic = get_post( $topic_id ) )
				return;

			$topic_title = esc_html( bbp_get_topic_title( $topic->ID ) );

			switch ( $notice ) {
				case 'opened' :
					$message = $is_failure == true ? sprintf( __( 'There was a problem opening the topic "%1$s".', 'bbpress' ), $topic_title ) : sprintf( __( 'Topic "%1$s" successfully opened.', 'bbpress' ), $topic_title );
					break;

				case 'closed' :
					$message = $is_failure == true ? sprintf( __( 'There was a problem closing the topic "%1$s".', 'bbpress' ), $topic_title ) : sprintf( __( 'Topic "%1$s" successfully closed.', 'bbpress' ), $topic_title );
					break;

				case 'spammed' :
					$message = $is_failure == true ? sprintf( __( 'There was a problem marking the topic "%1$s" as spam.', 'bbpress' ), $topic_title ) : sprintf( __( 'Topic "%1$s" successfully marked as spam.', 'bbpress' ), $topic_title );
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
	 * topics_column_headers ()
	 *
	 * Manage the column headers for the topics page
	 *
	 * @param array $columns
	 * @return array $columns
	 */
	function topics_column_headers ( $columns ) {
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
	 * topics_column_data ( $column, $topic_id )
	 *
	 * Print extra columns for the topics page
	 *
	 * @param string $column
	 * @param int $post_id
	 */
	function topics_column_data ( $column, $topic_id ) {
		global $bbp, $typenow;

		if ( $typenow !== $bbp->topic_id )
			return $column;

		// Get topic forum ID
		$forum_id = bbp_get_topic_forum_id( $topic_id );

		// Populate column data
		switch ( $column ) {
			// Forum
			case 'bbp_topic_forum' :
				// Output forum name
				bbp_topic_forum_title( $topic_id );

				// Link information
				$actions = apply_filters( 'topic_forum_row_actions', array (
					'edit' => '<a href="' . add_query_arg( array( 'post' => $forum_id, 'action' => 'edit' ), admin_url( '/post.php' ) ) . '">' . __( 'Edit', 'bbpress' ) . '</a>',
					'view' => '<a href="' . bbp_get_forum_permalink( $forum_id ) . '">' . __( 'View', 'bbpress' ) . '</a>'
				) );

				// Output forum post row links
				foreach ( $actions as $action => $link )
					$formatted_actions[] = '<span class="' . $action . '">' . $link . '</span>';

				//echo '<div class="row-actions">' . implode( ' | ', $formatted_actions ) . '</div>';

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
				bbp_topic_author_display_name ( $topic_id );
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
				if ( $last_active = bbp_get_topic_last_active( $topic_id, false ) )
					printf( __( '%s ago', 'bbpress' ), $last_active );
				else
					_e( 'No Replies', 'bbpress' ); // This should never happen

				break;

			// Do an action for anything else
			default :
				do_action( 'bbp_admin_topics_column_data', $column, $topic_id );
				break;
		}
	}

	/**
	 * topics_row_actions ( $actions, $topic )
	 *
	 * Remove the quick-edit action link under the topic title and add the
	 * spam/close links
	 *
	 * @param array $actions
	 * @param array $topic
	 * @return array $actions
	 */
	function topics_row_actions ( $actions, $topic ) {
		global $bbp;

		if ( $bbp->topic_id == $topic->post_type ) {
			unset( $actions['inline hide-if-no-js'] );

			the_content();

			// Show view link if it's not set, the topic is trashed and the user can view trashed topics
			if ( empty( $actions['view'] ) && 'trash' == $topic->post_status && current_user_can( 'view_trash' ) )
				$actions['view'] = '<a href="' . bbp_get_topic_permalink( $topic->ID ) . '" title="' . esc_attr( sprintf( __( 'View &#8220;%s&#8221;', 'bbpress' ), bbp_get_topic_title( $topic->ID ) ) ) . '" rel="permalink">' . __( 'View', 'bbpress' ) . '</a>';

			// Show the 'close' and 'open' link on published and closed posts only
			if ( in_array( $topic->post_status, array( 'publish', $bbp->closed_status_id ) ) ) {
				$close_uri = esc_url( wp_nonce_url( add_query_arg( array( 'topic_id' => $topic->ID, 'action' => 'bbp_toggle_topic_close' ), remove_query_arg( array( 'bbp_topic_toggle_notice', 'topic_id', 'failed' ) ) ), 'close-topic_' . $topic->ID ) );
				if ( bbp_is_topic_open( $topic->ID ) )
					$actions['closed'] = '<a href="' . $close_uri . '" title="' . esc_attr__( 'Close this topic', 'bbpress' ) . '">' . __( 'Close', 'bbpress' ) . '</a>';
				else
					$actions['closed'] = '<a href="' . $close_uri . '" title="' . esc_attr__( 'Open this topic', 'bbpress'  ) . '">' . __( 'Open',  'bbpress' ) . '</a>';

				$spam_uri  = esc_url( wp_nonce_url( add_query_arg( array( 'topic_id' => $topic->ID, 'action' => 'bbp_toggle_topic_spam'  ), remove_query_arg( array( 'bbp_topic_toggle_notice', 'topic_id', 'failed' ) ) ), 'spam-topic_'  . $topic->ID ) );
				if ( bbp_is_topic_spam( $topic->ID ) )
					$actions['spam'] = '<a href="' . $spam_uri . '" title="' . esc_attr__( 'Mark the topic as not spam', 'bbpress' ) . '">' . __( 'Not spam', 'bbpress' ) . '</a>';
				else
					$actions['spam'] = '<a href="' . $spam_uri . '" title="' . esc_attr__( 'Mark this topic as spam',    'bbpress' ) . '">' . __( 'Spam',     'bbpress' ) . '</a>';
			}

			// Do not show trash links for spam topics, or spam links for trashed topics
			if ( current_user_can( 'delete_topic', $topic->ID ) ) {
				if ( $bbp->trash_status_id == $topic->post_status ) {
					$post_type_object   = get_post_type_object( $topic->post_type );
					$actions['untrash'] = "<a title='" . esc_attr( __( 'Restore this item from the Trash', 'bbpress' ) ) . "' href='" . wp_nonce_url( add_query_arg( array( '_wp_http_referer' => add_query_arg( array( 'post_type' => $bbp->topic_id ), admin_url( 'edit.php' ) ) ), admin_url( sprintf( $post_type_object->_edit_link . '&amp;action=untrash', $topic->ID ) ) ), 'untrash-' . $topic->post_type . '_' . $topic->ID ) . "'>" . __( 'Restore', 'bbpress' ) . "</a>";
				} elseif ( EMPTY_TRASH_DAYS ) {
					$actions['trash'] = "<a class='submitdelete' title='" . esc_attr( __( 'Move this item to the Trash', 'bbpress' ) ) . "' href='" . add_query_arg( array( '_wp_http_referer' => add_query_arg( array( 'post_type' => $bbp->topic_id ), admin_url( 'edit.php' ) ) ), get_delete_post_link( $topic->ID ) ) . "'>" . __( 'Trash', 'bbpress' ) . "</a>";
				}

				if ( $bbp->trash_status_id == $topic->post_status || !EMPTY_TRASH_DAYS ) {
					$actions['delete'] = "<a class='submitdelete' title='" . esc_attr( __( 'Delete this item permanently', 'bbpress' ) ) . "' href='" . add_query_arg( array( '_wp_http_referer' => add_query_arg( array( 'post_type' => $bbp->topic_id ), admin_url( 'edit.php' ) ) ), get_delete_post_link( $topic->ID, '', true ) ) . "'>" . __( 'Delete Permanently', 'bbpress' ) . "</a>";
				} elseif ( $bbp->spam_status_id == $topic->post_status ) {
					unset( $actions['trash'] );
				}
			}
		}

		return $actions;
	}

	/**
	 * toggle_reply ()
	 *
	 * Handles the admin-side opening/closing and spamming/unspamming of replies
	 *
	 * @since bbPress (r2740)
	 */
	function toggle_reply () {
		// Only proceed if GET is a reply toggle action
		if ( 'GET' == $_SERVER['REQUEST_METHOD'] && !empty( $_GET['action'] ) && in_array( $_GET['action'], array( 'bbp_toggle_reply_spam' ) ) && !empty( $_GET['reply_id'] ) ) {
			global $bbp;

			$action    = $_GET['action'];            // What action is taking place?
			$reply_id  = (int) $_GET['reply_id'];    // What's the reply id?
			$success   = false;                      // Flag
			$post_data = array( 'ID' => $reply_id ); // Prelim array

			if ( !$reply = get_post( $reply_id ) ) // Which reply?
				wp_die( __( 'The reply was not found!', 'bbpress' ) );

			if ( !current_user_can( 'edit_reply', $reply->ID ) ) // What is the user doing here?
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
	 * toggle_reply_notice ()
	 *
	 * Display the success/error notices from toggle_reply()
	 *
	 * @since bbPress (r2740)
	 */
	function toggle_reply_notice () {
		// Only proceed if GET is a reply toggle action
		if ( 'GET' == $_SERVER['REQUEST_METHOD'] && !empty( $_GET['bbp_reply_toggle_notice'] ) && in_array( $_GET['bbp_reply_toggle_notice'], array( 'spammed', 'unspammed' ) ) && !empty( $_GET['reply_id'] ) ) {
			global $bbp;

			$notice     = $_GET['bbp_reply_toggle_notice'];         // Which notice?
			$reply_id   = (int) $_GET['reply_id'];                  // What's the reply id?
			$is_failure = !empty( $_GET['failed'] ) ? true : false; // Was that a failure?

			// Empty? No reply?
			if ( empty( $notice ) || empty( $reply_id ) || !$reply = get_post( $reply_id ) )
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
	 * replies_column_headers ()
	 *
	 * Manage the column headers for the replies page
	 *
	 * @param array $columns
	 * @return array $columns
	 */
	function replies_column_headers ( $columns ) {
		$columns = array(
			'cb'                    => '<input type="checkbox" />',
			'title'                 => __( 'Title',   'bbpress' ),
			'bbp_reply_forum'       => __( 'Forum',   'bbpress' ),
			'bbp_reply_topic'       => __( 'Topic',   'bbpress' ),
			'bbp_reply_author'      => __( 'Author',  'bbpress' ),
			'bbp_reply_created'     => __( 'Created', 'bbpress' ),
		);

		return apply_filters( 'bbp_admin_topics_column_headers', $columns );
	}

	/**
	 * replies_column_data ( $column, $post_id )
	 *
	 * Print extra columns for the topics page
	 *
	 * @param string $column
	 * @param int $post_id
	 */
	function replies_column_data ( $column, $reply_id ) {
		global $bbp, $typenow;

		if ( $typenow !== $bbp->reply_id )
			return $column;

		// Get topic ID
		$topic_id = bbp_get_reply_topic_id( $reply_id );

		// Populate Column Data
		switch ( $column ) {
			// Topic
			case 'bbp_reply_topic' :
				// Output forum name
				bbp_topic_title( $topic_id );

				// Link information
				$actions = apply_filters( 'topic_forum_row_actions', array (
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
				$actions = apply_filters( 'topic_forum_row_actions', array (
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
				do_action( 'bbp_admin_replies_column_data', $column, $post_id );
				break;
		}
	}

	/**
	 * replies_row_actions ()
	 *
	 * Remove the quick-edit action link under the reply title and add the
	 * spam link
	 *
	 * @param array $actions
	 * @param array $reply
	 * @return array $actions
	 */
	function replies_row_actions ( $actions, $reply ) {
		global $bbp;

		if ( $bbp->reply_id == $reply->post_type ) {
			unset( $actions['inline hide-if-no-js'] );

			// Show view link if it's not set, the reply is trashed and the user can view trashed replies
			if ( empty( $actions['view'] ) && 'trash' == $reply->post_status && current_user_can( 'view_trash' ) )
				$actions['view'] = '<a href="' . bbp_get_reply_permalink( $reply->ID ) . '" title="' . esc_attr( sprintf( __( 'View &#8220;%s&#8221;', 'bbpress' ), bbp_get_reply_title( $reply->ID ) ) ) . '" rel="permalink">' . __( 'View', 'bbpress' ) . '</a>';

			the_content();

			$spam_uri  = esc_url( wp_nonce_url( add_query_arg( array( 'reply_id' => $reply->ID, 'action' => 'bbp_toggle_reply_spam'  ), remove_query_arg( array( 'bbp_reply_toggle_notice', 'reply_id', 'failed' ) ) ), 'spam-reply_'  . $reply->ID ) );

			if ( in_array( $reply->post_status, array( 'publish', $bbp->spam_status_id ) ) ) {
				if ( bbp_is_reply_spam( $reply->ID ) )
					$actions['spam'] = '<a href="' . $spam_uri . '" title="' . esc_attr__( 'Mark the reply as not spam', 'bbpress' ) . '">' . __( 'Not spam', 'bbpress' ) . '</a>';
				else
					$actions['spam'] = '<a href="' . $spam_uri . '" title="' . esc_attr__( 'Mark this reply as spam',    'bbpress' ) . '">' . __( 'Spam',     'bbpress' ) . '</a>';
			}

			if ( current_user_can( 'delete_reply', $reply->ID ) ) {
				if ( $bbp->trash_status_id == $reply->post_status ) {
					$post_type_object = get_post_type_object( $reply->post_type );
					$actions['untrash'] = "<a title='" . esc_attr( __( 'Restore this item from the Trash', 'bbpress' ) ) . "' href='" . add_query_arg( array( '_wp_http_referer' => add_query_arg( array( 'post_type' => $bbp->reply_id ), admin_url( 'edit.php' ) ) ), wp_nonce_url( admin_url( sprintf( $post_type_object->_edit_link . '&amp;action=untrash', $reply->ID ) ), 'untrash-' . $reply->post_type . '_' . $reply->ID ) ) . "'>" . __( 'Restore', 'bbpress' ) . "</a>";
				} elseif ( EMPTY_TRASH_DAYS ) {
					$actions['trash'] = "<a class='submitdelete' title='" . esc_attr( __( 'Move this item to the Trash', 'bbpress' ) ) . "' href='" . add_query_arg( array( '_wp_http_referer' => add_query_arg( array( 'post_type' => $bbp->reply_id ), admin_url( 'edit.php' ) ) ), get_delete_post_link( $reply->ID ) ) . "'>" . __( 'Trash', 'bbpress' ) . "</a>";
				}

				if ( $bbp->trash_status_id == $reply->post_status || !EMPTY_TRASH_DAYS ) {
					$actions['delete'] = "<a class='submitdelete' title='" . esc_attr( __( 'Delete this item permanently', 'bbpress' ) ) . "' href='" . add_query_arg( array( '_wp_http_referer' => add_query_arg( array( 'post_type' => $bbp->reply_id ), admin_url( 'edit.php' ) ) ), get_delete_post_link( $reply->ID, '', true ) ) . "'>" . __( 'Delete Permanently', 'bbpress' ) . "</a>";
				} elseif ( $bbp->spam_status_id == $reply->post_status ) {
					unset( $actions['trash'] );
				}
			}
		}

		return $actions;
	}

	/**
	 * register_admin_style ()
	 *
	 * Registers the bbPress admin color scheme
	 */
	function register_admin_style () {
		global $bbp;

		wp_admin_css_color( 'bbpress', __( 'Green', 'bbpress' ), $bbp->plugin_url . 'bbp-css/admin.css', array( '#222222', '#006600', '#deece1', '#6eb469' ) );
	}
}
endif; // class_exists check

/**
 * bbp_admin_separator ()
 *
 * Forces a separator between bbPress top level menus, and WordPress content menus
 *
 * @package bbPress
 * @subpackage Admin
 * @since bbPress (r2464)
 *
 * @todo A better job at rearranging and separating top level menus
 * @global array $menu
 */
function bbp_admin_separator () {
	global $menu;

	$menu[24] = $menu[25];
	$menu[25] = array( '', 'read', 'separator1', '', 'wp-menu-separator' );
}
add_action( 'admin_menu', 'bbp_admin_separator' );

/**
 * bbp_forum_metabox ()
 *
 * The metabox that holds all of the additional forum information
 *
 * @package bbPress
 * @subpackage Admin
 * @since bbPress (r2744)
 */
function bbp_forum_metabox () {
	global $bbp, $post;

	/** TYPE ******************************************************************/
	$forum['type'] = array(
		'forum'    => __( 'Forum',    'bbpress' ),
		'category' => __( 'Category', 'bbpress' )
	);
	$type_output = '<select name="bbp_forum_type" id="bbp_forum_type_select">' . "\n";

	foreach( $forum['type'] as $value => $label )
		$type_output .= "\t" . '<option value="' . $value . '"' . selected( bbp_is_forum_category( $post->ID ) ? 'category' : 'forum', $value, false ) . '>' . esc_html( $label ) . '</option>' . "\n";

	$type_output .= '</select>';

	/** STATUS ****************************************************************/
	$forum['status']   = array(
		'open'   => __( 'Open',   'bbpress' ),
		'closed' => __( 'Closed', 'bbpress' )
	);
	$status_output = '<select name="bbp_forum_status" id="bbp_forum_status_select">' . "\n";

	foreach( $forum['status'] as $value => $label )
		$status_output .= "\t" . '<option value="' . $value . '"' . selected( bbp_is_forum_closed( $post->ID, false ) ? 'closed' : 'open', $value, false ) . '>' . esc_html( $label ) . '</option>' . "\n";

	$status_output .= '</select>';

	/** VISIBILITY ************************************************************/
	$forum['visibility']  = array(
		'public'  => __( 'Public',  'bbpress' ),
		'private' => __( 'Private', 'bbpress' )
	);
	$visibility_output = '<select name="bbp_forum_visibility" id="bbp_forum_visibility_select">' . "\n";

	foreach( $forum['visibility'] as $value => $label )
		$visibility_output .= "\t" . '<option value="' . $value . '"' . selected( bbp_is_forum_private( $post->ID, false ) ? 'private' : 'public', $value, false ) . '>' . esc_html( $label ) . '</option>' . "\n";

	$visibility_output .= '</select>';

	/** OUTPUT ****************************************************************/ ?>

		<p>
			<strong class="label"><?php _e( 'Type:', 'bbpress' ); ?></strong>
			<label class="screen-reader-text" for="bbp_forum_type_select"><?php _e( 'Type:', 'bbpress' ) ?></label>
			<?php echo $type_output; ?>
		</p>

		<p>
			<strong class="label"><?php _e( 'Status:', 'bbpress' ); ?></strong>
			<label class="screen-reader-text" for="bbp_forum_status_select"><?php _e( 'Status:', 'bbpress' ) ?></label>
			<?php echo $status_output; ?>
		</p>

		<p>
			<strong class="label"><?php _e( 'Visibility:', 'bbpress' ); ?></strong>
			<label class="screen-reader-text" for="bbp_forum_visibility_select"><?php _e( 'Visibility:', 'bbpress' ) ?></label>
			<?php echo $visibility_output; ?>
		</p>

		<hr />
		
		<p>
			<strong class="label"><?php _e( 'Parent:', 'bbpress' ); ?></strong>
			<label class="screen-reader-text" for="parent_id"><?php _e( 'Forum Parent', 'bbpress' ); ?></label>

			<?php
				bbp_dropdown( array(
					'exclude'            => $post->ID,
					'selected'           => $post->post_parent,
					'show_none'          => __( '(No Parent)', 'bbpress' ),
					'select_id'          => 'parent_id',
					'disable_categories' => false
				) );
			?>

		</p>

		<p>
			<strong class="label"><?php _e( 'Order:', 'bbpress' ); ?></strong>
			<label class="screen-reader-text" for="menu_order"><?php _e( 'Forum Order', 'bbpress' ); ?></label>
			<input name="menu_order" type="text" size="4" id="menu_order" value="<?php echo esc_attr( $post->menu_order ); ?>" />
		</p>
<?php

	do_action( 'bbp_forum_metabox' );
}

/**
 * bbp_topic_metabox ()
 *
 * The metabox that holds all of the additional topic information
 *
 * @package bbPress
 * @subpackage Admin
 * @since bbPress (r2464)
 *
 * @global object $post
 */
function bbp_topic_metabox () {
	global $post, $bbp;

	$args = array(
		'selected'  => $post->post_parent,
		'select_id' => 'parent_id'
	);

	?>

		<p>
			<strong><?php _e( 'Forum', 'bbpress' ); ?></strong>
		</p>

		<p>
			<label class="screen-reader-text" for="parent_id"><?php _e( 'Forum', 'bbpress' ); ?></label>
			<?php bbp_dropdown( $args ); ?>
		</p>

		<p>
			<strong><?php _e( 'Topic Order', 'bbpress' ); ?></strong>
		</p>

		<p>
			<label class="screen-reader-text" for="menu_order"><?php _e( 'Topic Order', 'bbpress' ); ?></label>
			<input name="menu_order" type="text" size="4" id="menu_order" value="<?php echo esc_attr( $post->menu_order ); ?>" />
		</p>
<?php

	do_action( 'bbp_topic_metabox' );
}

/**
 * bbp_reply_metabox ()
 *
 * The metabox that holds all of the additional reply information
 *
 * @package bbPress
 * @subpackage Admin
 * @since bbPress (r2464)
 *
 * @global object $post
 */
function bbp_reply_metabox () {
	global $post, $bbp;

	$args = array(
		'post_type' => $bbp->topic_id,
		'selected'  => $post->post_parent,
		'select_id' => 'parent_id'
	);

	?>

	<p>
		<strong><?php _e( 'Topic', 'bbpress' ); ?></strong>
	</p>

	<p>
		<label class="screen-reader-text" for="parent_id"><?php _e( 'Topic', 'bbpress' ); ?></label>
		<?php bbp_dropdown( $args ); ?>
	</p>

	<?php

	do_action( 'bbp_reply_metabox' );
}

/**
 * bbp_admin ()
 *
 * Setup bbPress Admin
 *
 * @global object $bbp
 */
function bbp_admin() {
	global $bbp;

	$bbp->admin = new BBP_Admin();
}
add_action( 'bbp_init', 'bbp_admin' );

?>
