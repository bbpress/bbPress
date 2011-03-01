<?php

if ( !class_exists( 'BBP_Admin' ) ) :
/**
 * Loads bbPress plugin admin area
 *
 * @package bbPress
 * @subpackage Administration
 * @since bbPress (r2464)
 */
class BBP_Admin {

	/**
	 * The main bbPress admin loader
	 *
	 * @since bbPress (r2515)
	 *
	 * @uses BBP_Admin::_setup_globals() Setup the globals needed
	 * @uses BBP_Admin::_includes() Include the required files
	 * @uses BBP_Admin::_setup_actions() Setup the hooks and actions
	 */
	function BBP_Admin() {
		$this->_setup_globals();
		$this->_includes();
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
	 */
	function _setup_actions() {

		/** General Actions *******************************************/

		// Add notice if not using a bbPress theme
		add_action( 'admin_notices',               array( $this, 'activation_notice'          )        );

		// Add link to settings page
		add_filter( 'plugin_action_links',         array( $this, 'add_settings_link'          ), 10, 2 );

		// Add menu item to settings menu
		add_action( 'admin_menu',                  array( $this, 'admin_menus'                )        );

		// Add the settings
		add_action( 'admin_init',                  array( $this, 'register_admin_settings'    )        );

		// Attach the bbPress admin init action to the WordPress admin init action.
		add_action( 'admin_init',                  array( $this, 'init'                       )        );

		// Add some general styling to the admin area
		add_action( 'admin_head',                  array( $this, 'admin_head'                 )        );

		// Register bbPress admin style
		add_action( 'admin_init',                  array( $this, 'register_admin_style'       )        );

		// Forums 'Right now' Dashboard widget
		add_action( 'wp_dashboard_setup',          array( $this, 'dashboard_widget_right_now' )        );

		/** User Actions **********************************************/

		// User profile edit/display actions
		add_action( 'edit_user_profile',           array( $this, 'user_profile_forums' ) );
		add_action( 'show_user_profile',           array( $this, 'user_profile_forums' ) );

		// User profile save actions
		add_action( 'personal_options_update',     array( $this, 'user_profile_update' ) );
		add_action( 'edit_user_profile_update',    array( $this, 'user_profile_update' ) );

		/** Forums ****************************************************/

		// Forum metabox actions
		add_action( 'add_meta_boxes',              array( $this, 'forum_attributes_metabox'      ) );
		add_action( 'save_post',                   array( $this, 'forum_attributes_metabox_save' ) );

		// Forum column headers.
		add_filter( 'manage_' . bbp_get_forum_post_type() . '_posts_columns',        array( $this, 'forums_column_headers' ) );

		// Forum columns (in page row)
		add_action( 'manage_' . bbp_get_forum_post_type() . '_posts_custom_column',  array( $this, 'forums_column_data' ), 10, 2 );
		add_filter( 'page_row_actions',                                              array( $this, 'forums_row_actions' ), 10, 2 );

		/** Topics ****************************************************/

		// Topic column headers.
		add_filter( 'manage_' . bbp_get_topic_post_type() . '_posts_columns',        array( $this, 'topics_column_headers' ) );

		// Topic columns (in post row)
		add_action( 'manage_' . bbp_get_topic_post_type() . '_posts_custom_column',  array( $this, 'topics_column_data' ), 10, 2 );
		add_filter( 'post_row_actions',                                              array( $this, 'topics_row_actions' ), 10, 2 );

		// Topic metabox actions
		add_action( 'add_meta_boxes',              array( $this, 'topic_attributes_metabox'      ) );
		add_action( 'save_post',                   array( $this, 'topic_attributes_metabox_save' ) );

		// Check if there are any bbp_toggle_topic_* requests on admin_init, also have a message displayed
		add_action( 'bbp_admin_init',              array( $this, 'toggle_topic'        ) );
		add_action( 'admin_notices',               array( $this, 'toggle_topic_notice' ) );

		/** Replies ***************************************************/

		// Reply column headers.
		add_filter( 'manage_' . bbp_get_reply_post_type() . '_posts_columns',  array( $this, 'replies_column_headers' ) );

		// Reply columns (in post row)
		add_action( 'manage_' . bbp_get_reply_post_type() . '_posts_custom_column',  array( $this, 'replies_column_data' ), 10, 2 );
		add_filter( 'post_row_actions',                                   array( $this, 'replies_row_actions' ), 10, 2 );

		// Reply metabox actions
		add_action( 'add_meta_boxes',              array( $this, 'reply_attributes_metabox'      ) );
		add_action( 'save_post',                   array( $this, 'reply_attributes_metabox_save' ) );

		// Check if there are any bbp_toggle_reply_* requests on admin_init, also have a message displayed
		add_action( 'bbp_admin_init',              array( $this, 'toggle_reply'        ) );
		add_action( 'admin_notices',               array( $this, 'toggle_reply_notice' ) );

		// Anonymous metabox actions
		add_action( 'add_meta_boxes',              array( $this, 'anonymous_metabox'      ) );
		add_action( 'save_post',                   array( $this, 'anonymous_metabox_save' ) );
	}

	/**
	 * Include required files
	 *
	 * @since bbPress (r2646)
	 * @access private
	 */
	function _includes() {
		require_once( 'bbp-tools.php'     );
		require_once( 'bbp-settings.php'  );
		require_once( 'bbp-functions.php' );
	}

	/**
	 * Admin globals
	 *
	 * @since bbPress (r2646)
	 * @access private
	 */
	function _setup_globals() {
		// Nothing to do here yet
	}

	/**
	 * Add the navigational menu elements
	 *
	 * @since bbPress (r2646)
	 *
	 * @uses add_management_page() To add the Recount page in Tools section
	 * @uses add_options_page() To add the Forums settings page in Settings
	 *                           section
	 */
	function admin_menus() {
		add_management_page( __( 'Recount', 'bbpress' ), __( 'Recount', 'bbpress' ), 'manage_options', 'bbp-recount', 'bbp_admin_tools'    );
		add_options_page   ( __( 'Forums',  'bbpress' ), __( 'Forums',  'bbpress' ), 'manage_options', 'bbpress',     'bbp_admin_settings' );
	}

	/**
	 * Register the settings
	 *
	 * @since bbPress (r2737)
	 *
	 * @uses add_settings_section() To add our own settings section
	 * @uses add_settings_field() To add various settings fields
	 * @uses register_setting() To register various settings
	 * @uses do_action() Calls 'bbp_register_admin_settings'
	 */
	function register_admin_settings() {

		/** Main Section **********************************************/

		// Add the main section
		add_settings_section( 'bbp_main',                __( 'Main Settings',           'bbpress' ), 'bbp_admin_setting_callback_main_section',  'bbpress'             );

		// Edit lock setting
		add_settings_field( '_bbp_edit_lock',            __( 'Lock post editing after', 'bbpress' ), 'bbp_admin_setting_callback_editlock',      'bbpress', 'bbp_main' );
	 	register_setting  ( 'bbpress',                   '_bbp_edit_lock',                           'intval'                                                          );

		// Throttle setting
		add_settings_field( '_bbp_throttle_time',        __( 'Throttle time',           'bbpress' ), 'bbp_admin_setting_callback_throttle',      'bbpress', 'bbp_main' );
	 	register_setting  ( 'bbpress',                   '_bbp_throttle_time',                       'intval'                                                          );

		// Allow favorites setting
		add_settings_field( '_bbp_enable_favorites',     __( 'Allow Favorites',         'bbpress' ), 'bbp_admin_setting_callback_favorites',     'bbpress', 'bbp_main' );
	 	register_setting  ( 'bbpress',                   '_bbp_enable_favorites',                    'intval'                                                          );

		// Allow subscriptions setting
		add_settings_field( '_bbp_enable_subscriptions', __( 'Allow Subscriptions',     'bbpress' ), 'bbp_admin_setting_callback_subscriptions', 'bbpress', 'bbp_main' );
	 	register_setting  ( 'bbpress',                   '_bbp_enable_subscriptions',                'intval'                                                          );

		// Allow anonymous posting setting
		add_settings_field( '_bbp_allow_anonymous',      __( 'Allow Anonymous Posting', 'bbpress' ), 'bbp_admin_setting_callback_anonymous',     'bbpress', 'bbp_main' );
	 	register_setting  ( 'bbpress',                   '_bbp_allow_anonymous',                     'intval'                                                          );

		/** Per Page Section ******************************************/

		// Add the per page section
		add_settings_section( 'bbp_per_page',        __( 'Per Page',          'bbpress' ), 'bbp_admin_setting_callback_per_page_section', 'bbpress'                 );

		// Topics per page setting
		add_settings_field( '_bbp_topics_per_page',  __( 'Topics Per Page',   'bbpress' ), 'bbp_admin_setting_callback_topics_per_page',  'bbpress', 'bbp_per_page' );
	 	register_setting  ( 'bbpress',               '_bbp_topics_per_page',               'intval'                                                                 );

		// Replies per page setting
		add_settings_field( '_bbp_replies_per_page', __( 'Replies Per Page',  'bbpress' ), 'bbp_admin_setting_callback_replies_per_page', 'bbpress', 'bbp_per_page' );
	 	register_setting  ( 'bbpress',               '_bbp_replies_per_page',              'intval'                                                                 );

		/** Slug Section **********************************************/

		// Add the per page section
		add_settings_section( 'bbp_slugs',          __( 'Forums',        'bbpress' ), 'bbp_admin_setting_callback_slugs_section',   'bbpress'              );

		// Root slug setting
		add_settings_field( '_bbp_root_slug',       __( 'Forum base',    'bbpress' ), 'bbp_admin_setting_callback_root_slug',       'bbpress', 'bbp_slugs' );
	 	register_setting  ( 'bbpress',              '_bbp_root_slug',                 'sanitize_title'                                                     );

		// Include root setting
		add_settings_field( '_bbp_include_root',    __( 'Include base?', 'bbpress' ), 'bbp_admin_setting_callback_include_root',    'bbpress', 'bbp_slugs' );
	 	register_setting  ( 'bbpress',              '_bbp_include_root',              'intval'                                                             );

		// User slug setting
		add_settings_field( '_bbp_user_slug',       __( 'User base',     'bbpress' ), 'bbp_admin_setting_callback_user_slug',       'bbpress', 'bbp_slugs' );
	 	register_setting  ( 'bbpress',              '_bbp_user_slug',                 'sanitize_title'                                                     );

		// View slug setting
		add_settings_field( '_bbp_view_slug',       __( 'View base',     'bbpress' ), 'bbp_admin_setting_callback_view_slug',       'bbpress', 'bbp_slugs' );
	 	register_setting  ( 'bbpress',              '_bbp_view_slug',                 'sanitize_title'                                                     );

		// Forum slug setting
		add_settings_field( '_bbp_forum_slug',      __( 'Forum slug',    'bbpress' ), 'bbp_admin_setting_callback_forum_slug',      'bbpress', 'bbp_slugs' );
	 	register_setting  ( 'bbpress',             '_bbp_forum_slug',                 'sanitize_title'                                                     );

		// Topic slug setting
		add_settings_field( '_bbp_topic_slug',      __( 'Topic slug',    'bbpress' ), 'bbp_admin_setting_callback_topic_slug',      'bbpress', 'bbp_slugs' );
	 	register_setting  ( 'bbpress',             '_bbp_topic_slug',                 'sanitize_title'                                                     );

		// Reply slug setting
		add_settings_field( '_bbp_reply_slug',      __( 'Reply slug',    'bbpress' ), 'bbp_admin_setting_callback_reply_slug',      'bbpress', 'bbp_slugs' );
	 	register_setting  ( 'bbpress',             '_bbp_reply_slug',                 'sanitize_title'                                                     );

		// Topic tag slug setting
		add_settings_field( '_bbp_topic_tag_slug', __( 'Topic tag slug', 'bbpress' ), 'bbp_admin_setting_callback_topic_tag_slug',  'bbpress', 'bbp_slugs' );
	 	register_setting  ( 'bbpress',             '_bbp_topic_tag_slug',             'sanitize_title'                                                     );

		do_action( 'bbp_register_admin_settings' );
	}

	/**
	 * Admin area activation notice
	 *
	 * Shows the message of activating a bbPress-compatible theme to
	 * capable users.
	 *
	 * @since bbPress (r2743)
	 *
	 * @uses current_user_can() To check if we need to show the message to
	 *                           the current user.
	 * @uses current_theme_info() To get the current theme info for checking
	 *                             if it's bbPress-compatible or not
	 */
	function activation_notice() {
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
	 * Add Settings link to plugins area
	 *
	 * @since bbPress (r2737)
	 *
	 * @param array $links Links array in which we would prepend our link
	 * @param string $file Current plugin basename
	 * @return array Processed links
	 */
	function add_settings_link( $links, $file ) {
		global $bbp;

		if ( plugin_basename( $bbp->file ) == $file ) {
			$settings_link = '<a href="' . add_query_arg( array( 'page' => 'bbpress' ), admin_url( 'options-general.php' ) ) . '">' . __( 'Settings', 'bbpress' ) . '</a>';
			array_unshift( $links, $settings_link );
		}

		return $links;
	}

	/**
	 * bbPress's dedicated admin init action
	 *
	 * @since bbPress (r2464)
	 *
	 * @uses do_action() Calls 'bbp_admin_init'
	 */
	function init() {
		do_action( 'bbp_admin_init' );
	}

	/**
	 * Add the 'Right now in Forums' dashboard widget
	 *
	 * @since bbPress (r2770)
	 *
	 * @uses wp_add_dashboard_widget() To add the dashboard widget
	 */
	function dashboard_widget_right_now() {
		wp_add_dashboard_widget( 'bbp_dashboard_right_now', __( 'Right Now in Forums', 'bbpress' ), 'bbp_dashboard_widget_right_now' );
	}

	/**
	 * Add the forum attributes metabox
	 *
	 * @since bbPress (r2746)
	 *
	 * @uses add_meta_box() To add the metabox
	 * @uses do_action() Calls 'bbp_forum_attributes_metabox'
	 */
	function forum_attributes_metabox() {
		add_meta_box (
			'bbp_forum_attributes',
			__( 'Forum Attributes', 'bbpress' ),
			'bbp_forum_metabox',
			bbp_get_forum_post_type(),
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
	function forum_attributes_metabox_save( $forum_id ) {
		global $bbp;

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE )
			return $forum_id;

		if ( !$forum = bbp_get_forum( $forum_id ) )
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

		do_action( 'bbp_forum_attributes_metabox_save', $forum_id );

		return $forum_id;
	}

	/**
	 * Add the topic attributes metabox
	 *
	 * @since bbPress (r2744)
	 *
	 * @uses add_meta_box() To add the metabox
	 * @uses do_action() Calls 'bbp_topic_attributes_metabox'
	 */
	function topic_attributes_metabox() {
		add_meta_box (
			'bbp_topic_attributes',
			__( 'Topic Attributes', 'bbpress' ),
			'bbp_topic_metabox',
			bbp_get_topic_post_type(),
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
	function topic_attributes_metabox_save( $topic_id ) {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE )
			return $topic_id;

		if ( !current_user_can( 'edit_topic', $topic_id ) )
			return $topic_id;

		// OK, we're authenticated: we need to find and save the data
		$parent_id = isset( $topic['parent_id'] ) ? $topic['parent_id'] : 0;

		do_action( 'bbp_topic_attributes_metabox_save', $topic_id, $parent_id );

		return $parent_id;
	}

	/**
	 * Add the reply attributes metabox
	 *
	 * @since bbPress (r2746)
	 *
	 * @uses add_meta_box() To add the metabox
	 * @uses do_action() Calls 'bbp_reply_attributes_metabox'
	 */
	function reply_attributes_metabox() {
		add_meta_box (
			'bbp_reply_attributes',
			__( 'Reply Attributes', 'bbpress' ),
			'bbp_reply_metabox',
			bbp_get_reply_post_type(),
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
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE )
			return $reply_id;

		if ( !current_user_can( 'edit_reply', $reply_id ) )
			return $reply_id;

		// OK, we're authenticated: we need to find and save the data
		$parent_id = isset( $reply['parent_id'] ) ? $reply['parent_id'] : 0;

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
	 * @uses add_meta_box() To add the metabox
	 * @uses do_action() Calls 'bbp_anonymous_metabox' with the topic/reply
	 *                    id
	 */
	function anonymous_metabox() {
		global $bbp;

		if ( !empty( $_GET['post'] ) )
			$post_id = (int) $_GET['post'];
		else
			$post_id = 0;

		if ( $topic = bbp_get_topic( $post_id ) )
			$topic_id = $topic->ID;
		elseif ( $reply = bbp_get_reply( $post_id ) )
			$reply_id = $reply->ID;
		else
			return;

		if ( !empty( $topic_id ) && !bbp_is_topic_anonymous( $topic_id ) )
			return;

		if ( !empty( $reply_id ) && !bbp_is_reply_anonymous( $reply_id ) )
			return;

		add_meta_box(
			'bbp_anonymous_metabox',
			__( 'Anonymous User Information', 'bbpress' ),
			'bbp_anonymous_metabox',
			!empty( $topic_id ) ? bbp_get_topic_post_type() : bbp_get_reply_post_type(),
			'side',
			'high'
		);

		do_action( 'bbp_anonymous_metabox', $post_id );
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
	 * @uses bbp_filter_anonymous_post_data() To filter the anonymous user
	 *                                         data
	 * @uses update_post_meta() To update the anonymous user data
	 * @uses do_action() Calls 'bbp_anonymous_metabox_save' with the topic/
	 *                    reply id and anonymous data
	 * @return int Topic or reply id
	 */
	function anonymous_metabox_save( $post_id ) {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE )
			return $post_id;

		if ( $topic = bbp_get_topic( $post_id ) )
			$topic_id = $topic->ID;
		elseif ( $reply = bbp_get_reply( $post_id ) )
			$reply_id = $reply->ID;
		else
			return $post_id;

		if ( !empty( $topic_id ) && ( !current_user_can( 'edit_topic', $topic_id ) || !bbp_is_topic_anonymous( $topic_id ) ) )
			return $topic_id;

		if ( !empty( $reply_id ) && ( !current_user_can( 'edit_reply', $reply_id ) || !bbp_is_reply_anonymous( $reply_id ) ) )
			return $reply_id;

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
	 * @uses sanitize_html_class() To sanitize the classes
	 * @uses bbp_is_forum() To check if it is a forum page
	 * @uses bbp_is_topic() To check if it is a topic page
	 * @uses bbp_is_reply() To check if it is a reply page
	 * @uses do_action() Calls 'bbp_admin_head'
	 */
	function admin_head() {
		global $bbp, $post;

		// Icons for top level admin menus
		$menu_icon_url = $bbp->images_url . '/menu.png';
		$icon32_url    = $bbp->images_url . '/icons32.png';

		// Top level menu classes
		$forum_class   = sanitize_html_class( bbp_get_forum_post_type() );
		$topic_class   = sanitize_html_class( bbp_get_topic_post_type() );
		$reply_class   = sanitize_html_class( bbp_get_reply_post_type() ); ?>

		<style type="text/css" media="screen">
		/*<![CDATA[*/

			/* =bbPress 'Right Now in Forums' Dashboard Widget
			-------------------------------------------------------------- */

			#bbp_dashboard_right_now p.sub,
			#bbp_dashboard_right_now .table,
			#bbp_dashboard_right_now .versions {
				margin: -12px;
			}

			#bbp_dashboard_right_now .inside {
				font-size: 12px;
				padding-top: 20px;
			}

			#bbp_dashboard_right_now p.sub {
				font-style: italic;
				font-family: Georgia, "Times New Roman", "Bitstream Charter", Times, serif;
				padding: 5px 10px 15px;
				color: #777;
				font-size: 13px;
				position: absolute;
				top: -17px;
				left: 15px;
			}

			#bbp_dashboard_right_now .table {
				margin: 0 -9px;
				padding: 0 10px;
				position: relative;
			}

			#bbp_dashboard_right_now .table_content {
				float: left;
				border-top: #ececec 1px solid;
				width: 45%;
			}

			#bbp_dashboard_right_now .table_discussion {
				float: right;
				border-top: #ececec 1px solid;
				width: 45%;
			}

			#bbp_dashboard_right_now table td {
				padding: 3px 0;
				white-space: nowrap;
			}

			#bbp_dashboard_right_now table tr.first td {
				border-top: none;
			}

			#bbp_dashboard_right_now td.b {
				padding-right: 6px;
				text-align: right;
				font-family: Georgia, "Times New Roman", "Bitstream Charter", Times, serif;
				font-size: 14px;
				width: 1%;
			}

			#bbp_dashboard_right_now td.b a {
				font-size: 18px;
			}

			#bbp_dashboard_right_now td.b a:hover {
				color: #d54e21;
			}

			#bbp_dashboard_right_now .t {
				font-size: 12px;
				padding-right: 12px;
				padding-top: 6px;
				color: #777;
			}

			#bbp_dashboard_right_now .t a {
				white-space: nowrap;
			}

			#bbp_dashboard_right_now .spam {
				color: red;
			}

			#bbp_dashboard_right_now .waiting {
				color: #e66f00;
			}

			#bbp_dashboard_right_now .approved {
				color: green;
			}

			#bbp_dashboard_right_now .versions {
				padding: 6px 10px 12px;
				clear: both;
			}

			#bbp_dashboard_right_now .versions .b {
				font-weight: bold;
			}

			#bbp_dashboard_right_now a.button {
				float: right;
				clear: right;
				position: relative;
				top: -5px;
			}

			/* =bbPress Menus
			-------------------------------------------------------------- */

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

<?php if ( isset( $post ) && $post->post_type == bbp_get_forum_post_type() ) : ?>

			/* =bbPress Post Form
			-------------------------------------------------------------- */

			#misc-publishing-actions, #save-post { display: none; }
			strong.label { display: inline-block; width: 60px; }
			#bbp_forum_attributes hr { border-style: solid; border-width: 1px; border-color: #ccc #fff #fff #ccc; }

<?php endif; ?>

<?php if ( bbp_is_forum() || bbp_is_topic() || bbp_is_reply() ) : ?>

			/* =bbPress Custom columns
			-------------------------------------------------------------- */

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
	 * Responsible for saving additional profile options and settings
	 *
	 * @todo Everything
	 *
	 * @since bbPress (r2464)
	 *
	 * @param $user_id The user id
	 * @uses do_action() Calls 'bbp_user_profile_update'
	 * @return bool Always false
	 */
	function user_profile_update( $user_id ) {
		// Add extra actions to bbPress profile update
		do_action( 'bbp_user_profile_update' );

		return false;
	}

	/**
	 * Responsible for saving additional profile options and settings
	 *
	 * @todo Everything
	 *
	 * @since bbPress (r2464)
	 *
	 * @param WP_User $profileuser User data
	 * @uses do_action() Calls 'bbp_user_profile_forums'
	 * @return bool Always false
	 */
	function user_profile_forums( $profileuser ) {
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
	 * Manage the column headers for the forums page
	 *
	 * @since bbPress (r2485)
	 *
	 * @param array $columns The columns
	 * @uses apply_filters() Calls 'bbp_admin_forums_column_headers' with
	 *                        the columns
	 * @return array $columns bbPress forum columns
	 */
	function forums_column_headers( $columns ) {
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
	function forums_column_data( $column, $forum_id ) {
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
	function forums_row_actions( $actions, $forum ) {
		if ( $forum->post_type == bbp_get_forum_post_type() ) {
			unset( $actions['inline hide-if-no-js'] );

			// simple hack to show the forum description under the title
			bbp_forum_content( $forum->ID );
		}

		return $actions;
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
	 * @uses wp_redirect() Redirect the page to custom url
	 */
	function toggle_topic() {
		// Only proceed if GET is a topic toggle action
		if ( 'GET' == $_SERVER['REQUEST_METHOD'] && !empty( $_GET['action'] ) && in_array( $_GET['action'], array( 'bbp_toggle_topic_close', 'bbp_toggle_topic_stick', 'bbp_toggle_topic_spam' ) ) && !empty( $_GET['topic_id'] ) ) {
			$action    = $_GET['action'];            // What action is taking place?
			$topic_id  = (int) $_GET['topic_id'];    // What's the topic id?
			$success   = false;                      // Flag
			$post_data = array( 'ID' => $topic_id ); // Prelim array

			if ( !$topic = bbp_get_topic( $topic_id ) ) // Which topic?
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
			wp_redirect( $redirect );

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
	function toggle_topic_notice() {
		// Only proceed if GET is a topic toggle action
		if ( 'GET' == $_SERVER['REQUEST_METHOD'] && !empty( $_GET['bbp_topic_toggle_notice'] ) && in_array( $_GET['bbp_topic_toggle_notice'], array( 'opened', 'closed', 'super_sticked', 'sticked', 'unsticked', 'spammed', 'unspammed' ) ) && !empty( $_GET['topic_id'] ) ) {
			$notice     = $_GET['bbp_topic_toggle_notice'];         // Which notice?
			$topic_id   = (int) $_GET['topic_id'];                  // What's the topic id?
			$is_failure = !empty( $_GET['failed'] ) ? true : false; // Was that a failure?

			// Empty? No topic?
			if ( empty( $notice ) || empty( $topic_id ) || !$topic = bbp_get_topic( $topic_id ) )
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
	function topics_column_headers( $columns ) {
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

		// Get topic forum ID
		$forum_id = bbp_get_topic_forum_id( $topic_id );

		// Populate column data
		switch ( $column ) {
			// Forum
			case 'bbp_topic_forum' :
				// Output forum name
				if ( !empty( $forum_id ) ) {
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
				if ( $last_active = bbp_get_topic_last_active_time( $topic_id, false ) )
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
	 * Topic Row actions
	 *
	 * Remove the quick-edit action link under the topic title and add the
	 * content and close/stick/spam links
	 *
	 * @since bbPress (r2485)
	 *
	 * @param array $actions Actions
	 * @param array $topic Topic object
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
	function topics_row_actions( $actions, $topic ) {
		global $bbp;

		if ( $topic->post_type == bbp_get_topic_post_type() ) {
			unset( $actions['inline hide-if-no-js'] );

			bbp_topic_content( $topic->ID );

			// Show view link if it's not set, the topic is trashed and the user can view trashed topics
			if ( empty( $actions['view'] ) && 'trash' == $topic->post_status && current_user_can( 'view_trash' ) )
				$actions['view'] = '<a href="' . bbp_get_topic_permalink( $topic->ID ) . '" title="' . esc_attr( sprintf( __( 'View &#8220;%s&#8221;', 'bbpress' ), bbp_get_topic_title( $topic->ID ) ) ) . '" rel="permalink">' . __( 'View', 'bbpress' ) . '</a>';

			// Only show the actions if the user is capable of viewing them :)
			if ( current_user_can( 'moderate', $topic->ID ) ) {

				// Close
				// Show the 'close' and 'open' link on published and closed posts only
				if ( in_array( $topic->post_status, array( 'publish', $bbp->closed_status_id ) ) ) {
					$close_uri = esc_url( wp_nonce_url( add_query_arg( array( 'topic_id' => $topic->ID, 'action' => 'bbp_toggle_topic_close' ), remove_query_arg( array( 'bbp_topic_toggle_notice', 'topic_id', 'failed', 'super' ) ) ), 'close-topic_' . $topic->ID ) );
					if ( bbp_is_topic_open( $topic->ID ) )
						$actions['closed'] = '<a href="' . $close_uri . '" title="' . esc_attr__( 'Close this topic', 'bbpress' ) . '">' . __( 'Close', 'bbpress' ) . '</a>';
					else
						$actions['closed'] = '<a href="' . $close_uri . '" title="' . esc_attr__( 'Open this topic',  'bbpress' ) . '">' . __( 'Open',  'bbpress' ) . '</a>';
				}

				// Sticky
				$stick_uri  = esc_url( wp_nonce_url( add_query_arg( array( 'topic_id' => $topic->ID, 'action' => 'bbp_toggle_topic_stick' ), remove_query_arg( array( 'bbp_topic_toggle_notice', 'topic_id', 'failed', 'super' ) ) ), 'stick-topic_'  . $topic->ID ) );
				if ( bbp_is_topic_sticky( $topic->ID ) ) {
					$actions['stick'] = '<a href="' . $stick_uri . '" title="' . esc_attr__( 'Unstick this topic', 'bbpress' ) . '">' . __( 'Unstick', 'bbpress' ) . '</a>';
				} else {
					$super_uri        = esc_url( wp_nonce_url( add_query_arg( array( 'topic_id' => $topic->ID, 'action' => 'bbp_toggle_topic_stick', 'super' => '1' ), remove_query_arg( array( 'bbp_topic_toggle_notice', 'topic_id', 'failed', 'super' ) ) ), 'stick-topic_'  . $topic->ID ) );
					$actions['stick'] = '<a href="' . $stick_uri . '" title="' . esc_attr__( 'Stick this topic to its forum', 'bbpress' ) . '">' . __( 'Stick', 'bbpress' ) . '</a> (<a href="' . $super_uri . '" title="' . esc_attr__( 'Stick this topic to front', 'bbpress' ) . '">' . __( 'to front', 'bbpress' ) . '</a>)';
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
				if ( $bbp->trash_status_id == $topic->post_status ) {
					$post_type_object   = get_post_type_object( bbp_get_topic_post_type() );
					$actions['untrash'] = "<a title='" . esc_attr( __( 'Restore this item from the Trash', 'bbpress' ) ) . "' href='" . wp_nonce_url( add_query_arg( array( '_wp_http_referer' => add_query_arg( array( 'post_type' => bbp_get_topic_post_type() ), admin_url( 'edit.php' ) ) ), admin_url( sprintf( $post_type_object->_edit_link . '&amp;action=untrash', $topic->ID ) ) ), 'untrash-' . $topic->post_type . '_' . $topic->ID ) . "'>" . __( 'Restore', 'bbpress' ) . "</a>";
				} elseif ( EMPTY_TRASH_DAYS ) {
					$actions['trash'] = "<a class='submitdelete' title='" . esc_attr( __( 'Move this item to the Trash', 'bbpress' ) ) . "' href='" . add_query_arg( array( '_wp_http_referer' => add_query_arg( array( 'post_type' => bbp_get_topic_post_type() ), admin_url( 'edit.php' ) ) ), get_delete_post_link( $topic->ID ) ) . "'>" . __( 'Trash', 'bbpress' ) . "</a>";
				}

				if ( $bbp->trash_status_id == $topic->post_status || !EMPTY_TRASH_DAYS ) {
					$actions['delete'] = "<a class='submitdelete' title='" . esc_attr( __( 'Delete this item permanently', 'bbpress' ) ) . "' href='" . add_query_arg( array( '_wp_http_referer' => add_query_arg( array( 'post_type' => bbp_get_topic_post_type() ), admin_url( 'edit.php' ) ) ), get_delete_post_link( $topic->ID, '', true ) ) . "'>" . __( 'Delete Permanently', 'bbpress' ) . "</a>";
				} elseif ( $bbp->spam_status_id == $topic->post_status ) {
					unset( $actions['trash'] );
				}
			}
		}

		return $actions;
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
			'cb'                    => '<input type="checkbox" />',
			'title'                 => __( 'Title',   'bbpress' ),
			'bbp_reply_forum'       => __( 'Forum',   'bbpress' ),
			'bbp_reply_topic'       => __( 'Topic',   'bbpress' ),
			'bbp_reply_author'      => __( 'Author',  'bbpress' ),
			'bbp_reply_created'     => __( 'Created', 'bbpress' ),
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
	 * Registers the bbPress admin color scheme
	 *
	 * @since bbPress (r2521)
	 *
	 * @uses wp_admin_css_color() To register the color scheme
	 */
	function register_admin_style () {
		global $bbp;

		wp_admin_css_color( 'bbpress', __( 'Green', 'bbpress' ), $bbp->plugin_url . 'bbp-css/admin.css', array( '#222222', '#006600', '#deece1', '#6eb469' ) );
	}
}
endif; // class_exists check

/**
 * Forces a separator between bbPress top level menus & WordPress content menus
 *
 * @todo A better job at rearranging and separating top level menus
 *
 * @since bbPress (r2464)
 */
function bbp_admin_separator () {
	global $menu;

	$menu[24] = $menu[25];
	$menu[25] = array( '', 'read', 'separator1', '', 'wp-menu-separator' );
}


/**
 * bbPress Dashboard Right Now Widget
 *
 * Adds a dashboard widget with forum statistics
 *
 * @todo Check for updates and show notice
 *
 * @since bbPress (r2770)
 *
 * @uses bbp_get_statistics() To get the forum statistics
 * @uses current_user_can() To check if the user is capable of doing things
 * @uses get_admin_url() To get the administration url
 * @uses add_query_arg() To add custom args to the url
 * @uses do_action() Calls 'bbp_dashboard_widget_right_now_content_table_end'
 *                    below the content table
 * @uses do_action() Calls 'bbp_dashboard_widget_right_now_table_end'
 *                    below the discussion table
 * @uses do_action() Calls 'bbp_dashboard_widget_right_now_discussion_table_end'
 *                    below the discussion table
 * @uses do_action() Calls 'bbp_dashboard_widget_right_now_end' below the widget
 */
function bbp_dashboard_widget_right_now() {
	global $bbp;

	// Get the statistics and extract them
	extract( bbp_get_statistics(), EXTR_SKIP ); ?>

	<div class="table table_content">

		<p class="sub"><?php _e( 'Content', 'bbpress' ); ?></p>

		<table>

			<tr class="first">

				<?php
					$num  = $forum_count;
					$text = _n( 'Forum', 'Forums', $forum_count, 'bbpress' );
					if ( current_user_can( 'publish_forums' ) ) {
						$link = add_query_arg( array( 'post_type' => bbp_get_forum_post_type() ), get_admin_url( null, 'edit.php' ) );
						$num  = '<a href="' . $link . '">' . $num  . '</a>';
						$text = '<a href="' . $link . '">' . $text . '</a>';
					}
				?>

				<td class="first b b-forums"><?php echo $num; ?></td>
				<td class="t forums"><?php echo $text; ?></td>

			</tr>

			<tr>

				<?php
					$num  = $topic_count;
					$text = _n( 'Topic', 'Topics', $topic_count, 'bbpress' );
					if ( current_user_can( 'publish_topics' ) ) {
						$link = add_query_arg( array( 'post_type' => bbp_get_topic_post_type() ), get_admin_url( null, 'edit.php' ) );
						$num  = '<a href="' . $link . '">' . $num  . '</a>';
						$text = '<a href="' . $link . '">' . $text . '</a>';
					}
				?>

				<td class="first b b-topics"><?php echo $num; ?></td>
				<td class="t topics"><?php echo $text; ?></td>

			</tr>

			<tr>

				<?php
					$num  = $reply_count;
					$text = _n( 'Reply', 'Replies', $reply_count, 'bbpress' );
					if ( current_user_can( 'publish_replies' ) ) {
						$link = add_query_arg( array( 'post_type' => bbp_get_reply_post_type() ), get_admin_url( null, 'edit.php' ) );
						$num  = '<a href="' . $link . '">' . $num  . '</a>';
						$text = '<a href="' . $link . '">' . $text . '</a>';
					}
				?>

				<td class="first b b-replies"><?php echo $num; ?></td>
				<td class="t replies"><?php echo $text; ?></td>

			</tr>

			<tr>

				<?php
					$num  = $topic_tag_count;
					$text = _n( 'Topic Tag', 'Topic Tags', $topic_tag_count, 'bbpress' );
					if ( current_user_can( 'manage_topic_tags' ) ) {
						$link = add_query_arg( array( 'taxonomy' => $bbp->topic_tag_id, 'post_type' => bbp_get_topic_post_type() ), get_admin_url( null, 'edit-tags.php' ) );
						$num  = '<a href="' . $link . '">' . $num  . '</a>';
						$text = '<a href="' . $link . '">' . $text . '</a>';
					}
				?>

				<td class="first b b-topic_tags"><span class="total-count"><?php echo $num; ?></span></td>
				<td class="t topic_tags"><?php echo $text; ?></td>

			</tr>

			<?php do_action( 'bbp_dashboard_widget_right_now_content_table_end' ); ?>

		</table>

	</div>


	<div class="table table_discussion">

		<p class="sub"><?php _e( 'Discussion', 'bbpress' ); ?></p>

		<table>

			<tr class="first">

				<?php
					$num  = $user_count;
					$text = _n( 'User', 'Users', $user_count, 'bbpress' );
					if ( current_user_can( 'edit_users' ) ) {
						$link = get_admin_url( null, 'users.php' );
						$num  = '<a href="' . $link . '">' . $num  . '</a>';
						$text = '<a href="' . $link . '">' . $text . '</a>';
					}
				?>

				<td class="b b-users"><span class="total-count"><?php echo $num; ?></span></td>
				<td class="last t users"><?php echo $text; ?></td>

			</tr>

			<?php if ( isset( $hidden_topic_count ) ) : ?>

				<tr>

					<?php
						$num  = $hidden_topic_count;
						$text = _n( 'Hidden Topic', 'Hidden Topics', $hidden_topic_count, 'bbpress' );
						$link = add_query_arg( array( 'post_type' => bbp_get_topic_post_type() ), get_admin_url( null, 'edit.php' ) );
						$num  = '<a href="' . $link . '" title="' . esc_attr( $hidden_topic_title ) . '">' . $num  . '</a>';
						$text = '<a class="waiting" href="' . $link . '" title="' . esc_attr( $hidden_topic_title ) . '">' . $text . '</a>';
					?>

					<td class="b b-hidden-topics"><?php echo $num; ?></td>
					<td class="last t hidden-replies"><?php echo $text; ?></td>

				</tr>

			<?php endif; ?>

			<?php if ( isset( $hidden_reply_count ) ) : ?>

				<tr>

					<?php
						$num  = $hidden_reply_count;
						$text = _n( 'Hidden Reply', 'Hidden Replies', $hidden_reply_count, 'bbpress' );
						$link = add_query_arg( array( 'post_type' => bbp_get_reply_post_type() ), get_admin_url( null, 'edit.php' ) );
						$num  = '<a href="' . $link . '" title="' . esc_attr( $hidden_reply_title ) . '">' . $num  . '</a>';
						$text = '<a class="waiting" href="' . $link . '" title="' . esc_attr( $hidden_reply_title ) . '">' . $text . '</a>';
					?>

					<td class="b b-hidden-replies"><?php echo $num; ?></td>
					<td class="last t hidden-replies"><?php echo $text; ?></td>

				</tr>

			<?php endif; ?>

			<?php if ( isset( $empty_topic_tag_count ) ) : ?>

				<tr>

					<?php
						$num  = $empty_topic_tag_count;
						$text = _n( 'Empty Topic Tag', 'Empty Topic Tags', $empty_topic_tag_count, 'bbpress' );
						$link = add_query_arg( array( 'taxonomy' => $bbp->topic_tag_id, 'post_type' => bbp_get_topic_post_type() ), get_admin_url( null, 'edit-tags.php' ) );
						$num  = '<a href="' . $link . '">' . $num  . '</a>';
						$text = '<a class="waiting" href="' . $link . '">' . $text . '</a>';
					?>

					<td class="b b-hidden-topic-tags"><?php echo $num; ?></td>
					<td class="last t hidden-topic-tags"><?php echo $text; ?></td>

				</tr>

			<?php endif; ?>

			<?php

			do_action( 'bbp_dashboard_widget_right_now_table_end'            );
			do_action( 'bbp_dashboard_widget_right_now_discussion_table_end' );

			?>

		</table>

	</div>

	<?php if ( current_user_can( 'update_plugins' ) ) : ?>

		<div class="versions">

			<p>

				<?php printf( __( 'You are using <span class="b">bbPress %s</span>.', 'bbpress' ), BBP_VERSION ); ?>

			</p>

			<br class="clear" />

		</div>

	<?php endif; ?>

	<?php

	do_action( 'bbp_dashboard_widget_right_now_end' );
}

/**
 * Forum metabox
 *
 * The metabox that holds all of the additional forum information
 *
 * @since bbPress (r2744)
 *
 * @uses bbp_is_forum_closed() To check if a forum is closed or not
 * @uses bbp_is_forum_category() To check if a forum is a category or not
 * @uses bbp_is_forum_private() To check if a forum is private or not
 * @uses bbp_dropdown() To show a dropdown of the forums for forum parent
 * @uses do_action() Calls 'bbp_forum_metabox'
 */
function bbp_forum_metabox() {
	global $post;

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
 * Topic metabox
 *
 * The metabox that holds all of the additional topic information
 *
 * @since bbPress (r2464)
 *
 * @uses bbp_dropdown() To show a dropdown of the forums for topic parent
 * @uses do_action() Calls 'bbp_topic_metabox'
 */
function bbp_topic_metabox() {
	global $post;

	$args = array(
		'selected'  => bbp_get_topic_forum_id( $post->ID ),
		'select_id' => 'parent_id',
		'show_none' => __( '(No Forum)', 'bbpress' )
	);

	?>

		<p>
			<strong><?php _e( 'Forum', 'bbpress' ); ?></strong>
		</p>

		<p>
			<label class="screen-reader-text" for="parent_id"><?php _e( 'Forum', 'bbpress' ); ?></label>
			<?php bbp_dropdown( $args ); ?>
		</p>

<?php

	do_action( 'bbp_topic_metabox' );
}

/**
 * Reply metabox
 *
 * The metabox that holds all of the additional reply information
 *
 * @since bbPress (r2464)
 *
 * @uses bbp_dropdown() To show a dropdown of the topics for reply parent
 * @uses do_action() Calls 'bbp_reply_metabox'
 */
function bbp_reply_metabox() {
	global $post;

	$args = array(
		'post_type'   => bbp_get_topic_post_type(),
		'selected'    => $post->post_parent,
		'select_id'   => 'parent_id',
		'orderby'     => 'post_date',
		'numberposts' => '50'
	);

	?>

	<p>
		<strong><?php _e( 'Parent Topic', 'bbpress' ); ?></strong>
	</p>

	<p>
		<label class="screen-reader-text" for="parent_id"><?php _e( 'Topic', 'bbpress' ); ?></label>
		<?php bbp_dropdown( $args ); ?>
	</p>

<?php

	do_action( 'bbp_reply_metabox' );
}

/**
 * Anonymous user information metabox
 *
 * @since bbPress (r)
 *
 * @uses get_post_meta() To get the anonymous user information
 */
function bbp_anonymous_metabox () {
	global $post; ?>

	<p>
		<strong><?php _e( 'Name', 'bbpress' ); ?></strong>
	</p>

	<p>
		<label class="screen-reader-text" for="bbp_anonymous_name"><?php _e( 'Name', 'bbpress' ); ?></label>
		<input type="text" id="bbp_anonymous_name" name="bbp_anonymous_name" value="<?php echo get_post_meta( $post->ID, '_bbp_anonymous_name', true ); ?>" size="38" />
	</p>

	<p>
		<strong><?php _e( 'Email', 'bbpress' ); ?></strong>
	</p>

	<p>
		<label class="screen-reader-text" for="bbp_anonymous_email"><?php _e( 'Email', 'bbpress' ); ?></label>
		<input type="text" id="bbp_anonymous_email" name="bbp_anonymous_email" value="<?php echo get_post_meta( $post->ID, '_bbp_anonymous_email', true ); ?>" size="38" />
	</p>

	<p>
		<strong><?php _e( 'Website', 'bbpress' ); ?></strong>
	</p>

	<p>
		<label class="screen-reader-text" for="bbp_anonymous_website"><?php _e( 'Website', 'bbpress' ); ?></label>
		<input type="text" id="bbp_anonymous_website" name="bbp_anonymous_website" value="<?php echo get_post_meta( $post->ID, '_bbp_anonymous_website', true ); ?>" size="38" />
	</p>

	<p>
		<strong><?php _e( 'IP Address', 'bbpress' ); ?></strong>
	</p>

	<p>
		<label class="screen-reader-text" for="bbp_anonymous_ip_address"><?php _e( 'IP Address', 'bbpress' ); ?></label>
		<input type="text" id="bbp_anonymous_ip_address" name="bbp_anonymous_ip_address" value="<?php echo get_post_meta( $post->ID, '_bbp_anonymous_ip', true ); ?>" size="38" disabled="disabled" />
	</p>

	<?php
}

/**
 * Setup bbPress Admin
 *
 * @since bbPress (r2596)
 *
 * @uses BBP_Admin
 */
function bbp_admin() {
	global $bbp;

	$bbp->admin = new BBP_Admin();
}

?>
