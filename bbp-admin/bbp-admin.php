<?php

/**
 * Main bbPress Admin Class
 *
 * @package bbPress
 * @subpackage Administration
 */

// Exit if accessed directly
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
	public $images_url = '';

	/**
	 * @var string URL to the bbPress admin styles directory
	 */
	public $styles_url = '';

	/** URLs ******************************************************************/

	/**
	 * @var bool Enable recounts in Tools area
	 */
	public $enable_recounts = false;

	/** Functions *************************************************************/

	/**
	 * The main bbPress admin loader
	 *
	 * @since bbPress (r2515)
	 *
	 * @uses BBP_Admin::setup_globals() Setup the globals needed
	 * @uses BBP_Admin::includes() Include the required files
	 * @uses BBP_Admin::setup_actions() Setup the hooks and actions
	 */
	public function __construct() {
		$this->setup_globals();
		$this->includes();
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
	 */
	private function setup_actions() {

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
		add_action( 'bbp_admin_init',     array( $this, 'register_admin_style'    ) );

		// Add the importers
		add_action( 'bbp_admin_init',     array( $this, 'register_importers'      ) );

		// Add the settings
		add_action( 'bbp_admin_init',     array( $this, 'register_admin_settings' ) );

		// Forums 'Right now' Dashboard widget
		add_action( 'wp_dashboard_setup', array( $this, 'dashboard_widget_right_now' ) );

		/** Filters ***********************************************************/

		// Add link to settings page
		add_filter( 'plugin_action_links', array( $this, 'add_settings_link' ), 10, 2 );

		// Add sample permalink filter
		add_filter( 'post_type_link',     'bbp_filter_sample_permalink',        10, 4 );
	}

	/**
	 * Include required files
	 *
	 * @since bbPress (r2646)
	 * @access private
	 */
	private function includes() {
		global $bbp;

		// Files to include
		$files = array( 'tools', 'settings', 'functions', 'metaboxes', 'forums', 'topics', 'replies' );

		// Buzz buzz
		//if ( get_option( '_bbp_first_run' ) ) $files[] = 'first-run';

		// Include the files
		foreach ( $files as $file )
			require( $bbp->plugin_dir . 'bbp-admin/bbp-' . $file . '.php' );
	}

	/**
	 * Admin globals
	 *
	 * @since bbPress (r2646)
	 * @access private
	 */
	private function setup_globals() {
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
	public function admin_menus() {

		// Recounts
		if ( is_super_admin() || !empty( $this->enable_recounts ) )
			add_management_page( __( 'Recount', 'bbpress' ), __( 'Recount', 'bbpress' ), 'manage_options', 'bbp-recount', 'bbp_admin_tools' );

		// Forums settings
		add_options_page   ( __( 'Forums',  'bbpress' ), __( 'Forums',  'bbpress' ), 'manage_options', 'bbpress', 'bbp_admin_settings' );
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
	public function register_admin_settings() {

		/** Main Section ******************************************************/

		// Add the main section
		add_settings_section( 'bbp_main',                __( 'Main Settings',           'bbpress' ), 'bbp_admin_setting_callback_main_section',  'bbpress'             );

		// Edit lock setting
		add_settings_field( '_bbp_edit_lock',            __( 'Lock post editing after', 'bbpress' ), 'bbp_admin_setting_callback_editlock',      'bbpress', 'bbp_main' );
	 	register_setting  ( 'bbpress',                   '_bbp_edit_lock',                           'intval'                                                          );

		// Throttle setting
		add_settings_field( '_bbp_throttle_time',        __( 'Throttle time',           'bbpress' ), 'bbp_admin_setting_callback_throttle',      'bbpress', 'bbp_main' );
	 	register_setting  ( 'bbpress',                   '_bbp_throttle_time',                       'intval'                                                          );

		// Allow topic and reply revisions
		add_settings_field( '_bbp_allow_revisions',      __( 'Allow Revisions',         'bbpress' ), 'bbp_admin_setting_callback_revisions',     'bbpress', 'bbp_main' );
	 	register_setting  ( 'bbpress',                   '_bbp_allow_revisions',                     'intval'                                                          );

		// Allow favorites setting
		add_settings_field( '_bbp_enable_favorites',     __( 'Allow Favorites',         'bbpress' ), 'bbp_admin_setting_callback_favorites',     'bbpress', 'bbp_main' );
	 	register_setting  ( 'bbpress',                   '_bbp_enable_favorites',                    'intval'                                                          );

		// Allow subscriptions setting
		add_settings_field( '_bbp_enable_subscriptions', __( 'Allow Subscriptions',     'bbpress' ), 'bbp_admin_setting_callback_subscriptions', 'bbpress', 'bbp_main' );
	 	register_setting  ( 'bbpress',                   '_bbp_enable_subscriptions',                'intval'                                                          );

		// Allow anonymous posting setting
		add_settings_field( '_bbp_allow_anonymous',      __( 'Allow Anonymous Posting', 'bbpress' ), 'bbp_admin_setting_callback_anonymous',     'bbpress', 'bbp_main' );
	 	register_setting  ( 'bbpress',                   '_bbp_allow_anonymous',                     'intval'                                                          );

		// Allow global access setting
		if ( is_multisite() ) {
			add_settings_field( '_bbp_allow_global_access', __( 'Allow Global Access',  'bbpress' ), 'bbp_admin_setting_callback_global_access', 'bbpress', 'bbp_main' );
		 	register_setting  ( 'bbpress',                  '_bbp_allow_global_access',              'intval'                                                          );
		}

		/** Per Page Section **************************************************/

		// Add the per page section
		add_settings_section( 'bbp_per_page',        __( 'Per Page', 'bbpress' ),          'bbp_admin_setting_callback_per_page_section', 'bbpress'                 );

		// Topics per page setting
		add_settings_field( '_bbp_topics_per_page',  __( 'Topics',   'bbpress' ),          'bbp_admin_setting_callback_topics_per_page',  'bbpress', 'bbp_per_page' );
	 	register_setting  ( 'bbpress',               '_bbp_topics_per_page',               'intval'                                                                 );

		// Replies per page setting
		add_settings_field( '_bbp_replies_per_page', __( 'Replies',  'bbpress' ),          'bbp_admin_setting_callback_replies_per_page', 'bbpress', 'bbp_per_page' );
	 	register_setting  ( 'bbpress',               '_bbp_replies_per_page',              'intval'                                                                 );

		/** Per RSS Page Section **********************************************/

		// Add the per page section
		add_settings_section( 'bbp_per_rss_page',    __( 'Per RSS Page', 'bbpress' ),      'bbp_admin_setting_callback_per_rss_page_section', 'bbpress'                     );

		// Topics per page setting
		add_settings_field( '_bbp_topics_per_page',  __( 'Topics',       'bbpress' ),      'bbp_admin_setting_callback_topics_per_rss_page',  'bbpress', 'bbp_per_rss_page' );
	 	register_setting  ( 'bbpress',               '_bbp_topics_per_rss_page',           'intval'                                                                         );

		// Replies per page setting
		add_settings_field( '_bbp_replies_per_page', __( 'Replies',      'bbpress' ),      'bbp_admin_setting_callback_replies_per_rss_page', 'bbpress', 'bbp_per_rss_page' );
	 	register_setting  ( 'bbpress',               '_bbp_replies_per_rss_page',          'intval'                                                                         );

		/** Front Slugs *******************************************************/

		// Add the per page section
		add_settings_section( 'bbp_root_slug',           __( 'Archive Slugs', 'bbpress' ), 'bbp_admin_setting_callback_root_slug_section',   'bbpress'                  );

		// Root slug setting
		add_settings_field  ( '_bbp_root_slug',          __( 'Forums base',   'bbpress' ), 'bbp_admin_setting_callback_root_slug',           'bbpress', 'bbp_root_slug' );
	 	register_setting    ( 'bbpress',                '_bbp_root_slug',                  'esc_sql'                                                                    );

		// Topic archive setting
		add_settings_field  ( '_bbp_topic_archive_slug', __( 'Topics base',   'bbpress' ), 'bbp_admin_setting_callback_topic_archive_slug',  'bbpress', 'bbp_root_slug' );
	 	register_setting    ( 'bbpress',                 '_bbp_topic_archive_slug',        'esc_sql'                                                                    );

		/** Single slugs ******************************************************/

		// Add the per page section
		add_settings_section( 'bbp_single_slugs',   __( 'Single Slugs',  'bbpress' ), 'bbp_admin_setting_callback_single_slug_section', 'bbpress'                     );

		// Include root setting
		add_settings_field( '_bbp_include_root',    __( 'Forum Prefix', 'bbpress' ),  'bbp_admin_setting_callback_include_root',        'bbpress', 'bbp_single_slugs' );
	 	register_setting  ( 'bbpress',              '_bbp_include_root',              'intval'                                                                        );

		// Forum slug setting
		add_settings_field( '_bbp_forum_slug',      __( 'Forum slug',    'bbpress' ), 'bbp_admin_setting_callback_forum_slug',          'bbpress', 'bbp_single_slugs' );
	 	register_setting  ( 'bbpress',             '_bbp_forum_slug',                 'sanitize_title'                                                                );

		// Topic slug setting
		add_settings_field( '_bbp_topic_slug',      __( 'Topic slug',    'bbpress' ), 'bbp_admin_setting_callback_topic_slug',          'bbpress', 'bbp_single_slugs' );
	 	register_setting  ( 'bbpress',             '_bbp_topic_slug',                 'sanitize_title'                                                                );

		// Topic tag slug setting
		add_settings_field( '_bbp_topic_tag_slug', __( 'Topic tag slug', 'bbpress' ), 'bbp_admin_setting_callback_topic_tag_slug',      'bbpress', 'bbp_single_slugs' );
	 	register_setting  ( 'bbpress',             '_bbp_topic_tag_slug',             'sanitize_title'                                                                );

		// Reply slug setting
		add_settings_field( '_bbp_reply_slug',      __( 'Reply slug',    'bbpress' ), 'bbp_admin_setting_callback_reply_slug',          'bbpress', 'bbp_single_slugs' );
	 	register_setting  ( 'bbpress',             '_bbp_reply_slug',                 'sanitize_title'                                                                );

		/** Other slugs *******************************************************/

		// User slug setting
		add_settings_field( '_bbp_user_slug',       __( 'User base',     'bbpress' ), 'bbp_admin_setting_callback_user_slug',           'bbpress', 'bbp_single_slugs' );
	 	register_setting  ( 'bbpress',              '_bbp_user_slug',                 'sanitize_title'                                                                );

		// View slug setting
		add_settings_field( '_bbp_view_slug',       __( 'View base',     'bbpress' ), 'bbp_admin_setting_callback_view_slug',           'bbpress', 'bbp_single_slugs' );
	 	register_setting  ( 'bbpress',              '_bbp_view_slug',                 'sanitize_title'                                                                );

		do_action( 'bbp_register_admin_settings' );
	}

	/**
	 * Register the importers
	 *
	 * @since bbPress (r2737)
	 *
	 * @uses do_action() Calls 'bbp_register_importers'
	 */
	public function register_importers() {

		// Leave if we're not in the import section
		if ( !defined( 'WP_LOAD_IMPORTERS' ) )
			return;

		global $bbp;

		// Load Importer API
		require_once( ABSPATH . 'wp-admin/includes/import.php' );

		// Load our importers
		$importers = apply_filters( 'bbp_importers', array( 'bbpress' ) );

		// Loop through included importers
		foreach ( $importers as $importer ) {

			// Compile the importer path
			$import_file = $bbp->plugin_dir . 'bbp-admin/importers/' . $importer . '.php';

			// If the file exists, include it
			if ( file_exists( $import_file ) ) {
				require( $import_file );
			}
		}

		// Don't do anything we wouldn't do
		do_action( 'bbp_register_importers' );
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
	public function activation_notice() {
		global $bbp, $pagenow;

		// Bail if not on admin theme page
		if ( 'themes.php' != $pagenow )
			return;

		// Bail if user cannot change the theme
		if ( !current_user_can( 'switch_themes' ) )
			return;

		// Set $bbp->theme_compat to true to bypass nag
		if ( !empty( $bbp->theme_compat->theme ) && !current_theme_supports( 'bbpress' ) ) : ?>

			<div id="message" class="updated fade">
				<p style="line-height: 150%"><?php printf( __( "Your active theme does not include bbPress template files. Your forums are using the default styling included with bbPress.", 'bbpress' ), admin_url( 'themes.php' ), admin_url( 'theme-install.php?type=tag&s=bbpress&tab=search' ) ) ?></p>
			</div>

		<?php endif;
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
	public function add_settings_link( $links, $file ) {
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
	public function init() {
		do_action( 'bbp_admin_init' );
	}

	/**
	 * Add the 'Right now in Forums' dashboard widget
	 *
	 * @since bbPress (r2770)
	 *
	 * @uses wp_add_dashboard_widget() To add the dashboard widget
	 */
	public function dashboard_widget_right_now() {
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
	 * @uses do_action() Calls 'bbp_admin_head'
	 */
	public function admin_head() {

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
				padding: 5px 0 15px; 
				color: #8f8f8f; 
				font-size: 14px; 
				position: absolute; 
				top: -17px; 

				<?php if ( is_rtl() ) : ?>

					right: 15px;

				<?php else : ?>

					left: 15px;

				<?php endif; ?>

			}

			#bbp-dashboard-right-now .table {
				margin: 0;
				padding: 0;
				position: relative;
			}

			#bbp-dashboard-right-now .table_content {

				<?php if ( is_rtl() ) : ?>

					float: right;

				<?php else : ?>

					float: left;

				<?php endif; ?>

				border-top: #ececec 1px solid;
				width: 45%;
			}

			#bbp-dashboard-right-now .table_discussion {

				<?php if ( is_rtl() ) : ?>

					float: left;

				<?php else : ?>

					float: right;

				<?php endif; ?>

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

				<?php if ( is_rtl() ) : ?>

					padding-left: 6px;

				<?php else : ?>

					padding-right: 6px;

				<?php endif; ?>

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

				<?php if ( is_rtl() ) : ?>

					padding-left: 12px;

				<?php else : ?>

					padding-right: 12px;

				<?php endif; ?>

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

				<?php if ( is_rtl() ) : ?>

					float: left;
					clear: left;

				<?php else : ?>

					float: right;
					clear: right;

				<?php endif; ?>

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
	public function register_admin_style () {
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
