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
		add_action( 'admin_menu',                  array( $this, 'topic_parent_metabox'      ) );
		add_action( 'save_post',                   array( $this, 'topic_parent_metabox_save' ) );

		// Check if there are any bbp_toggle_topic_* requests on admin_init, also have a message displayed
		add_action( 'bbp_admin_init',              array( $this, 'toggle_topic' ) );
		add_action( 'admin_notices',               array( $this, 'toggle_topic_notice' ) );

		/** Replies ***********************************************************/

		// Reply column headers.
		add_filter( 'manage_' . $bbp->reply_id . '_posts_columns',  array( $this, 'replies_column_headers' ) );

		// Reply columns (in post row)
		add_action( 'manage_posts_custom_column',  array( $this, 'replies_column_data' ), 10, 2 );
		add_filter( 'post_row_actions',            array( $this, 'replies_row_actions' ), 10, 2 );

		// Topic reply metabox actions
		add_action( 'admin_menu',                  array( $this, 'reply_parent_metabox'      ) );
		add_action( 'save_post',                   array( $this, 'reply_parent_metabox_save' ) );

		// Register bbPress admin style
		add_action( 'admin_init',                  array( $this, 'register_admin_style' ) );
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
	 * topic_parent_metabox ()
	 *
	 * Add the topic parent metabox
	 *
	 * @uses add_meta_box
	 */
	function topic_parent_metabox () {
		global $bbp;

		add_meta_box (
			'bbp_topic_parent_id',
			__( 'Forum', 'bbpress' ),
			'bbp_topic_metabox',
			$bbp->topic_id,
			'normal'
		);

		do_action( 'bbp_topic_parent_metabox' );
	}

	/**
	 * topic_parent_metabox_save ()
	 *
	 * Pass the topic post parent id for processing
	 *
	 * @param int $post_id
	 * @return int
	 */
	function topic_parent_metabox_save ( $post_id ) {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE )
			return $post_id;

		if ( !current_user_can( 'edit_post', $post_id ) )
			return $post_id;

		// OK, we're authenticated: we need to find and save the data
		$parent_id = isset( $_POST['parent_id'] ) ? $_POST['parent_id'] : 0;

		do_action( 'bbp_topic_parent_metabox_save' );

		return $parent_id;
	}

	/**
	 * reply_parent_metabox ()
	 *
	 * Add the topic reply parent metabox
	 */
	function reply_parent_metabox () {
		global $bbp;

		add_meta_box (
			'bbp_reply_parent_id',
			__( 'Topic', 'bbpress' ),
			'bbp_reply_metabox',
			$bbp->reply_id,
			'normal'
		);

		do_action( 'bbp_reply_parent_metabox' );
	}

	/**
	 * reply_parent_metabox_save ()
	 *
	 * Pass the topic reply post parent id for processing
	 *
	 * @param int $post_id
	 * @return int
	 */
	function reply_parent_metabox_save ( $post_id ) {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE )
			return $post_id;

		if ( !current_user_can( 'edit_post', $post_id ) )
			return $post_id;

		// OK, we're authenticated: we need to find and save the data
		$parent_id = isset( $_POST['parent_id'] ) ? $_POST['parent_id'] : 0;

		do_action( 'bbp_reply_parent_metabox_save' );

		return $parent_id;
	}

	/**
	 * admin_head ()
	 *
	 * Add some general styling to the admin area
	 */
	function admin_head () {
		global $bbp;

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

<?php if ( bbp_is_forum() || bbp_is_topic() || bbp_is_reply() ) : ?>

			.column-author, .column-bbp_forum_topic_count, .column-bbp_forum_reply_count, .column-bbp_topic_forum, .column-bbp_topic_reply_count, .column-bbp_topic_voice_count, .column-bbp_reply_forum, .column-bbp_forum_freshness, .column-bbp_topic_freshness { width: 10% !important; }
			.column-bbp_forum_created, .column-bbp_topic_created, .column-bbp_reply_created, .column-bbp_topic_author, .column-bbp_reply_author, .column-bbp_reply_topic { width: 15% !important; }

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
		return false;

		// Add extra actions to bbPress profile update
		do_action( 'bbp_user_profile_update' );
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

			if ( !$topic = get_post( $topic_id ) ) // Which topic dude?
				wp_die( __( 'The topic was not found!', 'bbpress' ) );

			if ( !current_user_can( 'edit_topic', $topic->ID ) ) // What is the user doing here?
				wp_die( __( 'You don\'t have the permission to do that!', 'bbpress' ) );

			switch ( $action ) {
				case 'bbp_toggle_topic_close' :
					check_admin_referer( 'close-topic_' . $topic_id ); // Trying to bypass security, huh?

					$is_open                  = bbp_is_topic_open( $topic_id );
					$post_data['post_status'] = $is_open ? $bbp->closed_status_id : 'publish';
					$message                  = $is_open ? 'closed' : 'opened';

					break;

				case 'bbp_toggle_topic_spam' :
					check_admin_referer( 'spam-topic_' . $topic_id ); // Trying to bypass security, huh?

					$is_spam                  = bbp_is_topic_spam( $topic_id );
					$post_data['post_status'] = $is_spam ? 'publish' : $bbp->spam_status_id;
					$message                  = $is_spam ? 'unspammed' : 'spammed';

					break;
			}

			$success = wp_update_post( $post_data );
			$message = array( 'bbp_topic_toggle_notice' => $message, 'topic_id' => $topic->ID );

			if ( true != $success )
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

			if ( !$topic = get_post( $topic_id ) ) // Which topic dude?
				return;

			$topic_title = esc_html( bbp_get_topic_title( $topic->ID ) );

			switch ( $notice ) {
				case 'opened' :
					$message = $is_failure ? sprintf( __( 'There was a problem opening the topic "%1$s".', 'bbpress' ), $topic_title ) : sprintf( __( 'Topic "%1$s" successfully opened.', 'bbpress' ), $topic_title );
					break;

				case 'closed' :
					$message = $is_failure ? sprintf( __( 'There was a problem closing the topic "%1$s".', 'bbpress' ), $topic_title ) : sprintf( __( 'Topic "%1$s" successfully closed.', 'bbpress' ), $topic_title );
					break;

				case 'spammed' :
					$message = $is_failure ? sprintf( __( 'There was a problem marking the topic "%1$s" as spam.', 'bbpress' ), $topic_title ) : sprintf( __( 'Topic "%1$s" successfully marked as spam.', 'bbpress' ), $topic_title );
					break;

				case 'unspammed' :
					$message = $is_failure ? sprintf( __( 'There was a problem unmarking the topic "%1$s" as spam.', 'bbpress' ), $topic_title ) : sprintf( __( 'Topic "%1$s" successfully unmarking as spam.', 'bbpress' ), $topic_title );
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
	 * Remove the quick-edit action link under the topic/reply title and
	 * add the spam/close links
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

			/**
			 * Spamming/closing/etc trashed topics will remove the trash post_status from them.
			 * Same type of complexities can be there with other post statuses too.
			 * Hence, these actions are only shown on all, published, closed and spam post status pages.
			 */
			if ( ( empty( $_GET['post_status'] ) || in_array( $_GET['post_status'], array( 'publish', $bbp->spam_status_id, $bbp->closed_status_id ) ) ) && current_user_can( 'edit_topic', $topic->ID ) ) {
				$close_uri = esc_url( wp_nonce_url( add_query_arg( array( 'topic_id' => $topic->ID, 'action' => 'bbp_toggle_topic_close' ), remove_query_arg( array( 'bbp_topic_toggle_notice', 'topic_id', 'failed' ) ) ), 'close-topic_' . $topic->ID ) );
				$spam_uri  = esc_url( wp_nonce_url( add_query_arg( array( 'topic_id' => $topic->ID, 'action' => 'bbp_toggle_topic_spam'  ), remove_query_arg( array( 'bbp_topic_toggle_notice', 'topic_id', 'failed' ) ) ), 'spam-topic_'  . $topic->ID ) );

				if ( bbp_is_topic_open( $topic->ID ) )
					$actions['closed'] = '<a href="' . $close_uri . '" title="' . esc_attr__( 'Close this topic', 'bbpress' ) . '">' . __( 'Close', 'bbpress' ) . '</a>';
				else
					$actions['closed'] = '<a href="' . $close_uri . '" title="' . esc_attr__( 'Open this topic', 'bbpress'  ) . '">' . __( 'Open',  'bbpress' ) . '</a>';

				if ( bbp_is_topic_spam( $topic->ID ) )
					$actions['spam'] = '<a href="' . $spam_uri . '" title="' . esc_attr__( 'Mark the topic as not spam', 'bbpress' ) . '">' . __( 'Not spam', 'bbpress' ) . '</a>';
				else
					$actions['spam'] = '<a href="' . $spam_uri . '" title="' . esc_attr__( 'Mark this topic as spam',    'bbpress' ) . '">' . __( 'Spam',     'bbpress' ) . '</a>';
			}
		}

		return $actions;
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
	 * Remove the quick-edit action link under the topic/reply title
	 *
	 * @param array $actions
	 * @param array $reply
	 * @return array $actions
	 */
	function replies_row_actions ( $actions, $reply ) {
		global $bbp;

		if ( $bbp->reply_id == $reply->post_type ) {
			unset( $actions['inline hide-if-no-js'] );

			the_content();
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
 * @subpackage Template Tags
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
 * bbp_topic_metabox ()
 *
 * The metabox that holds all of the additional topic information
 *
 * @package bbPress
 * @subpackage Template Tags
 * @since bbPress (r2464)
 *
 * @todo Alot ;)
 * @global object $post
 */
function bbp_topic_metabox () {
	global $post, $bbp;

	$args = array(
		'post_type'        => $bbp->forum_id,
		'exclude_tree'     => $post->ID,
		'selected'         => $post->post_parent,
		'show_option_none' => __( '(No Forum)', 'bbpress' ),
		'sort_column'      => 'menu_order, post_title',
		'child_of'         => '0',
	);

	$posts = bbp_admin_dropdown (
		__( 'Forum', 'bbpress' ),
		__( 'Forum', 'bbpress' ),
		__( 'There are no forums to reply to.', 'bbpress' ),
		$args
	);

	echo $posts;
?>
		<p><strong><?php _e( 'Topic Order', 'bbpress' ); ?></strong></p>
		<p><label class="screen-reader-text" for="menu_order"><?php _e( 'Topic Order', 'bbpress' ) ?></label><input name="menu_order" type="text" size="4" id="menu_order" value="<?php echo esc_attr( $post->menu_order ); ?>" /></p>
		<p><?php if ( 'page' == $post->post_type ) _e( 'Need help? Use the Help tab in the upper right of your screen.' ); ?></p>
<?php

	do_action( 'bbp_topic_metabox' );
}

/**
 * bbp_reply_metabox ()
 *
 * The metabox that holds all of the additional topic information
 *
 * @package bbPress
 * @subpackage Template Tags
 * @since bbPress (r2464)
 *
 * @todo Alot ;)
 * @global object $post
 */
function bbp_reply_metabox () {
	global $post, $bbp;

	$args = array(
		'post_type'        => $bbp->topic_id,
		'exclude_tree'     => $post->ID,
		'selected'         => $post->post_parent,
		'show_option_none' => __( '(No Topic)', 'bbpress' ),
		'sort_column'      => 'menu_order, post_title',
		'child_of'         => '0',
	);

	$posts = bbp_admin_dropdown(
		__( 'Topic', 'bbpress' ),
		__( 'Topic', 'bbpress' ),
		__( 'There are no topics to reply to.', 'bbpress' ),
		$args
	);

	echo $posts;

	do_action( 'bbp_topic_reply_metabox' );
}

/**
 * bbp_admin_dropdown ()
 *
 * General wrapper for creating a drop down of selectable parents
 *
 * @package bbPress
 * @subpackage Template Tags
 * @since bbPress (r2464)
 *
 * @param string $title
 * @param string $sub_title
 * @param mixed $error
 * @param array $args
 */
function bbp_admin_dropdown ( $title, $sub_title, $error, $args = '' ) {

	// The actual fields for data entry
	$posts = get_posts( $args );

	if ( !empty( $posts ) ) {
		$output  = '<select name="parent_id" id="parent_id">';
		$output .= '<option value="">' . __( '(No Parent)', 'bbpress' ) . '</option>';
		$output .= walk_page_dropdown_tree( $posts, 0, $args );
		$output .= '</select>';
	}

	$output = apply_filters( 'wp_dropdown_pages', $output );

	if ( !empty( $output ) ) : ?>
		<p><strong><?php echo $title; ?></strong></p>
		<label class="screen-reader-text" for="parent_id"><?php echo $sub_title; ?></label>
<?php
		echo $output;
	else :
?>
		<p><strong><?php echo $error; ?></strong></p>
<?php
	endif;
}

/**
 * bbp_admin ()
 *
 * Setup bbPress Admin
 *
 * @global <type> $bbp
 */
function bbp_admin() {
	global $bbp;

	$bbp->admin = new BBP_Admin();
}
add_action( 'bbp_init', 'bbp_admin' );

?>
