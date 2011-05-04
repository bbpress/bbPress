<?php

/**
 * Main bbPress Admin Class
 *
 * @package bbPress
 * @subpackage Administration
 */

// Redirect if accessed directly
if ( !defined( 'ABSPATH' ) ) exit;

if ( !class_exists( 'BBP_Admin' ) ) :
/**
 * Loads bbPress plugin admin area
 *
 * @package bbPress
 * @subpackage Administration
 * @since bbPress (r2464)
 */
class BBP_Admin {

	/** URLs ******************************************************************/

	/**
	 * @var string URL to the bbPress images directory
	 */
	var $images_url;

	/**
	 * @var string URL to the bbPress admin styles directory
	 */
	var $styles_url;

	/** Functions *************************************************************/

	/**
	 * The main bbPress admin loader (PHP4 compat)
	 *
	 * @since bbPress (r2515)
	 *
	 * @uses BBP_Admin::_setup_globals() Setup the globals needed
	 * @uses BBP_Admin::_includes() Include the required files
	 * @uses BBP_Admin::_setup_actions() Setup the hooks and actions
	 */
	function BBP_Admin() {
		$this->__construct();
	}

	/**
	 * The main bbPress admin loader
	 *
	 * @since bbPress (r2515)
	 *
	 * @uses BBP_Admin::_setup_globals() Setup the globals needed
	 * @uses BBP_Admin::_includes() Include the required files
	 * @uses BBP_Admin::_setup_actions() Setup the hooks and actions
	 */
	function __construct() {
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

		/** General Actions ***************************************************/

		// Attach the bbPress admin init action to the WordPress admin init action.
		add_action( 'admin_init',    array( $this, 'init'                    ) );

		// Add some general styling to the admin area
		add_action( 'admin_head',    array( $this, 'admin_head'              ) );

		// Add menu item to settings menu
		add_action( 'admin_menu',    array( $this, 'admin_menus'             ) );

		// Add notice if not using a bbPress theme
		add_action( 'admin_notices', array( $this, 'activation_notice'       ) );

		// Register bbPress admin style
		add_action( 'admin_init',    array( $this, 'register_admin_style'    ) );

		// Add the settings
		add_action( 'admin_init',    array( $this, 'register_admin_settings' ) );

		// Forums 'Right now' Dashboard widget
		add_action( 'wp_dashboard_setup', array( $this, 'dashboard_widget_right_now' ) );

		/** Filters ***********************************************************/

		// Add link to settings page
		add_filter( 'plugin_action_links', array( $this, 'add_settings_link' ), 10, 2 );
	}

	/**
	 * Include required files
	 *
	 * @since bbPress (r2646)
	 * @access private
	 */
	function _includes() {
		global $bbp;

		$files = array( 'tools', 'settings', 'functions', 'metaboxes', 'forums', 'topics', 'replies' );
		foreach ( $files as $file )
			require_once( $bbp->plugin_dir . 'bbp-admin/bbp-' . $file . '.php' );
	}

	/**
	 * Admin globals
	 *
	 * @since bbPress (r2646)
	 * @access private
	 */
	function _setup_globals() {
		global $bbp;

		// Admin url
		$this->admin_url  = trailingslashit( $bbp->plugin_url . 'bbp-admin' );

		// Admin images URL
		$this->images_url = trailingslashit( $this->admin_url . 'images' );

		// Admin images URL
		$this->styles_url = trailingslashit( $this->admin_url . 'styles' );
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

		/** Main Section ******************************************************/

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

		/** Per Page Section **************************************************/

		// Add the per page section
		add_settings_section( 'bbp_per_page',        __( 'Per Page',          'bbpress' ), 'bbp_admin_setting_callback_per_page_section', 'bbpress'                 );

		// Topics per page setting
		add_settings_field( '_bbp_topics_per_page',  __( 'Topics Per Page',   'bbpress' ), 'bbp_admin_setting_callback_topics_per_page',  'bbpress', 'bbp_per_page' );
	 	register_setting  ( 'bbpress',               '_bbp_topics_per_page',               'intval'                                                                 );

		// Replies per page setting
		add_settings_field( '_bbp_replies_per_page', __( 'Replies Per Page',  'bbpress' ), 'bbp_admin_setting_callback_replies_per_page', 'bbpress', 'bbp_per_page' );
	 	register_setting  ( 'bbpress',               '_bbp_replies_per_page',              'intval'                                                                 );

		/** Slug Section ******************************************************/

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
	 * Shows a nag message in admin area about the theme not supporting bbPress
	 *
	 * @since bbPress (r2743)
	 *
	 * @global bbPress $bbp
	 *
	 * @uses current_user_can() To check notice should be displayed.
	 * @uses current_theme_supports() To check theme for bbPress support
	 */
	function activation_notice() {
		global $bbp, $pagenow;

		// Bail if not on admin theme page
		if ( 'themes.php' != $pagenow )
			return;

		// Bail if user cannot change the theme
		if ( !current_user_can( 'switch_themes' ) )
			return;

		// Set $bbp->theme_compat to true to bypass nag
		if ( !empty( $bbp->theme_compat ) && !current_theme_supports( 'bbpress' ) ) { ?>

			<div id="message" class="updated fade">
				<p style="line-height: 150%"><?php printf( __( "<strong>bbPress is in Theme Compatability Mode</strong>. Your forums are using default styling.", 'bbpress' ), admin_url( 'themes.php' ), admin_url( 'theme-install.php?type=tag&s=bbpress&tab=search' ) ) ?></p>
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
		wp_add_dashboard_widget( 'bbp-dashboard-right-now', __( 'Right Now in Forums', 'bbpress' ), 'bbp_dashboard_widget_right_now' );
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

		// Icons for top level admin menus
		$menu_icon_url = $this->images_url . 'menu.png';
		$icon32_url    = $this->images_url . 'icons32.png';

		// Top level menu classes
		$forum_class = sanitize_html_class( bbp_get_forum_post_type() );
		$topic_class = sanitize_html_class( bbp_get_topic_post_type() );
		$reply_class = sanitize_html_class( bbp_get_reply_post_type() ); ?>

		<style type="text/css" media="screen">
		/*<![CDATA[*/

			#bbp-dashboard-right-now p.sub,
			#bbp-dashboard-right-now .table,
			#bbp-dashboard-right-now .versions {
				margin: -12px;
			}

			#bbp-dashboard-right-now .inside {
				font-size: 12px;
				padding-top: 20px;
				margin-bottom: 0;
			}

			#bbp-dashboard-right-now p.sub {
				font-style: italic;
				font-family: Georgia, "Times New Roman", "Bitstream Charter", Times, serif;
				padding: 5px 10px 15px;
				color: #777;
				font-size: 13px;
				position: absolute;
				top: -17px;
				left: 15px;
			}

			#bbp-dashboard-right-now .table {
				margin: 0 -9px;
				padding: 0 10px;
				position: relative;
			}

			#bbp-dashboard-right-now .table_content {
				float: left;
				border-top: #ececec 1px solid;
				width: 45%;
			}

			#bbp-dashboard-right-now .table_discussion {
				float: right;
				border-top: #ececec 1px solid;
				width: 45%;
			}

			#bbp-dashboard-right-now table td {
				padding: 3px 0;
				white-space: nowrap;
			}

			#bbp-dashboard-right-now table tr.first td {
				border-top: none;
			}

			#bbp-dashboard-right-now td.b {
				padding-right: 6px;
				text-align: right;
				font-family: Georgia, "Times New Roman", "Bitstream Charter", Times, serif;
				font-size: 14px;
				width: 1%;
			}

			#bbp-dashboard-right-now td.b a {
				font-size: 18px;
			}

			#bbp-dashboard-right-now td.b a:hover {
				color: #d54e21;
			}

			#bbp-dashboard-right-now .t {
				font-size: 12px;
				padding-right: 12px;
				padding-top: 6px;
				color: #777;
			}

			#bbp-dashboard-right-now .t a {
				white-space: nowrap;
			}

			#bbp-dashboard-right-now .spam {
				color: red;
			}

			#bbp-dashboard-right-now .waiting {
				color: #e66f00;
			}

			#bbp-dashboard-right-now .approved {
				color: green;
			}

			#bbp-dashboard-right-now .versions {
				padding: 6px 10px 12px;
				clear: both;
			}

			#bbp-dashboard-right-now .versions .b {
				font-weight: bold;
			}

			#bbp-dashboard-right-now a.button {
				float: right;
				clear: right;
				position: relative;
				top: -5px;
			}

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

		/*]]>*/
		</style>

		<?php

		// Add extra actions to bbPress admin header area
		do_action( 'bbp_admin_head' );
	}

	/**
	 * Registers the bbPress admin color scheme
	 *
	 * @since bbPress (r2521)
	 *
	 * @uses wp_admin_css_color() To register the color scheme
	 */
	function register_admin_style () {
		wp_admin_css_color( 'bbpress', __( 'Green', 'bbpress' ), $this->styles_url . 'admin.css', array( '#222222', '#006600', '#deece1', '#6eb469' ) );
	}
}
endif; // class_exists check

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
