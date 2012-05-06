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

	/** Directory *************************************************************/

	/**
	 * @var string Path to the bbPress admin directory
	 */
	public $admin_dir = '';

	/** URLs ******************************************************************/

	/**
	 * @var string URL to the bbPress admin directory
	 */
	public $admin_url = '';

	/**
	 * @var string URL to the bbPress images directory
	 */
	public $images_url = '';

	/**
	 * @var string URL to the bbPress admin styles directory
	 */
	public $styles_url = '';

	/** Tools *****************************************************************/

	/**
	 * @var bool Enable screens in Tools area
	 */
	public $enable_tools = false;

	/** Admin Scheme **********************************************************/

	/**
	 * @var int Depth of custom WP_CONTENT_DIR difference
	 */
	public $content_depth = 0;

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

		// Add menu item to settings menu
		add_action( 'bbp_admin_menu',              array( $this, 'admin_menus'             ) );

		// Add some general styling to the admin area
		add_action( 'bbp_admin_head',              array( $this, 'admin_head'              ) );

		// Add notice if not using a bbPress theme
		add_action( 'bbp_admin_notices',           array( $this, 'activation_notice'       ) );

		// Add green admin style
		add_action( 'bbp_register_admin_style',    array( $this, 'register_admin_style'    ) );

		// Add settings
		add_action( 'bbp_register_admin_settings', array( $this, 'register_admin_settings' ) );

		// Add menu item to settings menu
		add_action( 'bbp_activation',              array( $this, 'new_install'             ) );

		// Forums 'Right now' Dashboard widget
		add_action( 'wp_dashboard_setup',  array( $this, 'dashboard_widget_right_now' ) );

		/** Filters ***********************************************************/

		// Add link to settings page
		add_filter( 'plugin_action_links', array( $this, 'add_settings_link' ), 10, 2 );

		/** Network Admin *****************************************************/

		// Add menu item to settings menu
		add_action( 'network_admin_menu',  array( $this, 'network_admin_menus' ) );

		/** Dependencies ******************************************************/

		// Allow plugins to modify these actions
		do_action_ref_array( 'bbp_admin_loaded', array( &$this ) );
	}

	/**
	 * Include required files
	 *
	 * @since bbPress (r2646)
	 * @access private
	 */
	private function includes() {
		require( $this->admin_dir . 'bbp-tools.php'     );
		require( $this->admin_dir . 'bbp-converter.php' );
		require( $this->admin_dir . 'bbp-settings.php'  );
		require( $this->admin_dir . 'bbp-functions.php' );
		require( $this->admin_dir . 'bbp-metaboxes.php' );
		require( $this->admin_dir . 'bbp-forums.php'    );
		require( $this->admin_dir . 'bbp-topics.php'    );
		require( $this->admin_dir . 'bbp-replies.php'   );
	}

	/**
	 * Admin globals
	 *
	 * @since bbPress (r2646)
	 * @access private
	 */
	private function setup_globals() {
		$bbp = bbpress();
		$this->admin_dir  = trailingslashit( $bbp->plugin_dir . 'bbp-admin' ); // Admin url
		$this->admin_url  = trailingslashit( $bbp->plugin_url . 'bbp-admin' ); // Admin url
		$this->images_url = trailingslashit( $this->admin_url . 'images'    ); // Admin images URL
		$this->styles_url = trailingslashit( $this->admin_url . 'styles'    ); // Admin styles URL
	}

	/**
	 * Add the admin menus
	 *
	 * @since bbPress (r2646)
	 *
	 * @uses add_management_page() To add the Recount page in Tools section
	 * @uses add_options_page() To add the Forums settings page in Settings
	 *                           section
	 */
	public function admin_menus() {

		// Are tools enabled
		if ( is_super_admin() || !empty( $this->enable_tools ) ) {

			// These are later removed in admin_head
			add_management_page(
				__( 'Repair Forums', 'bbpress' ),
				__( 'Forum Repair', 'bbpress' ),
				'manage_options',
				'bbp-repair',
				'bbp_admin_repair'
			);
			add_management_page(
				__( 'Import Forums', 'bbpress' ),
				__( 'Forum Import', 'bbpress' ),
				'manage_options',
				'bbp-converter',
				'bbp_converter_settings'
			);
			add_management_page(
				__( 'Reset Forums', 'bbpress' ),
				__( 'Forum Reset', 'bbpress' ),
				'manage_options',
				'bbp-reset',
				'bbp_admin_reset'
			);

			// Forums Tools Root
			add_management_page(
				__( 'Forums', 'bbpress' ),
				__( 'Forums', 'bbpress' ),
				'manage_options',
				'bbp-repair',
				'bbp_admin_repair'
			);
		}

		// Forums settings
		add_options_page(
			__( 'Forums',  'bbpress' ),
			__( 'Forums',  'bbpress' ),
			'manage_options',
			'bbpress',
			'bbp_admin_settings'
		);
	}

	/**
	 * Add the network admin menus
	 *
	 * @since bbPress (r3689)
	 * @uses add_submenu_page() To add the Update Forums page in Updates
	 */
	public function network_admin_menus() {
		add_submenu_page(
			'upgrade.php',
			__( 'Update Forums', 'bbpress' ),
			__( 'Update Forums', 'bbpress' ),
			'manage_network',
			'bbpress-update',
			array( $this, 'network_update_screen' )
		);
	}

	/**
	 * If this is a new installation, create some initial forum content.
	 *
	 * @since bbPress (r3767)
	 * @return type 
	 */
	public function new_install() {
		if ( !bbp_is_install() )
			return;

		bbp_create_initial_content();
	}

	/**
	 * Register the settings
	 *
	 * @since bbPress (r2737)
	 *
	 * @uses add_settings_section() To add our own settings section
	 * @uses add_settings_field() To add various settings fields
	 * @uses register_setting() To register various settings
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

		// Allow fancy editor setting
		add_settings_field( '_bbp_use_wp_editor', __( 'Fancy Editor',     'bbpress' ), 'bbp_admin_setting_callback_use_wp_editor', 'bbpress', 'bbp_main' );
		register_setting  ( 'bbpress',            '_bbp_use_wp_editor',                'intval'                                                          );
		
		// Allow auto embedding setting
		add_settings_field( '_bbp_use_autoembed', __( 'Auto-embed Links', 'bbpress' ), 'bbp_admin_setting_callback_use_autoembed', 'bbpress', 'bbp_main' );
		register_setting  ( 'bbpress',           '_bbp_use_autoembed',                 'intval'                                                          );

		/** Theme Packages ****************************************************/

		// Add the per page section
		add_settings_section( 'bbp_theme_compat',    __( 'Theme Packages',  'bbpress' ), 'bbp_admin_setting_callback_subtheme_section', 'bbpress'                     );

		// Replies per page setting
		add_settings_field( '_bbp_theme_package_id', __( 'Current Package', 'bbpress' ), 'bbp_admin_setting_callback_subtheme_id',      'bbpress', 'bbp_theme_compat' );
		register_setting  ( 'bbpress',               '_bbp_theme_package_id',            ''                                                                           );

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

		/** BuddyPress ********************************************************/

		if ( is_plugin_active( 'buddypress/bp-loader.php' ) && defined( 'BP_VERSION' ) ) {

			// Add the per page section
			add_settings_section( 'bbp_buddypress',          __( 'BuddyPress', 'bbpress' ),          'bbp_admin_setting_callback_buddypress_section',   'bbpress'                   );

			// Topics per page setting
			add_settings_field( '_bbp_enable_group_forums',  __( 'Enable Group Forums', 'bbpress' ), 'bbp_admin_setting_callback_group_forums',         'bbpress', 'bbp_buddypress' );
			register_setting  ( 'bbpress',                  '_bbp_enable_group_forums',              'intval'                                                                       );

			// Topics per page setting
			add_settings_field( '_bbp_group_forums_root_id', __( 'Group Forums Parent', 'bbpress' ), 'bbp_admin_setting_callback_group_forums_root_id', 'bbpress', 'bbp_buddypress' );
			register_setting  ( 'bbpress',                  '_bbp_group_forums_root_id',             'intval'                                                                       );
		}

		/** Akismet ***********************************************************/

		if ( is_plugin_active( 'akismet/akismet.php' ) && defined( 'AKISMET_VERSION' ) ) {

			// Add the per page section
			add_settings_section( 'bbp_akismet',       __( 'Akismet', 'bbpress' ),      'bbp_admin_setting_callback_akismet_section', 'bbpress'                );

			// Replies per page setting
			add_settings_field( '_bbp_enable_akismet', __( 'Use Akismet',  'bbpress' ), 'bbp_admin_setting_callback_akismet',         'bbpress', 'bbp_akismet' );
			register_setting  ( 'bbpress',            '_bbp_enable_akismet',            'intval'                                                               );
		}
	}

	/**
	 * Register the importers
	 *
	 * @since bbPress (r2737)
	 *
	 * @uses apply_filters() Calls 'bbp_importer_path' filter to allow plugins
	 *                        to customize the importer script locations.
	 */
	public function register_importers() {

		// Leave if we're not in the import section
		if ( !defined( 'WP_LOAD_IMPORTERS' ) )
			return;

		// Load Importer API
		require_once( ABSPATH . 'wp-admin/includes/import.php' );

		// Load our importers
		$importers = apply_filters( 'bbp_importers', array( 'bbpress' ) );

		// Loop through included importers
		foreach ( $importers as $importer ) {

			// Allow custom importer directory
			$import_dir  = apply_filters( 'bbp_importer_path', $this->admin_dir . 'importers', $importer );

			// Compile the importer path
			$import_file = trailingslashit( $import_dir ) . $importer . '.php';

			// If the file exists, include it
			if ( file_exists( $import_file ) ) {
				require( $import_file );
			}
		}
	}

	/**
	 * Admin area activation notice
	 *
	 * Shows a nag message in admin area about the theme not supporting bbPress
	 *
	 * @since bbPress (r2743)
	 *
	 * @uses current_user_can() To check notice should be displayed.
	 */
	public function activation_notice() {
		// @todo - something fun
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

		if ( plugin_basename( bbpress()->file ) == $file ) {
			$settings_link = '<a href="' . add_query_arg( array( 'page' => 'bbpress' ), admin_url( 'options-general.php' ) ) . '">' . __( 'Settings', 'bbpress' ) . '</a>';
			array_unshift( $links, $settings_link );
		}

		return $links;
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
	 */
	public function admin_head() {

		// Remove the individual recount and converter menus.
		// They are grouped together by h2 tabs
		remove_submenu_page( 'tools.php', 'bbp-repair'    );
		remove_submenu_page( 'tools.php', 'bbp-converter' );
		remove_submenu_page( 'tools.php', 'bbp-reset'     );

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
				left: 15px;
			}
				body.rtl #bbp-dashboard-right-now p.sub {
					right: 15px;
					left: 0;
				}

			#bbp-dashboard-right-now .table {
				margin: 0;
				padding: 0;
				position: relative;
			}

			#bbp-dashboard-right-now .table_content {
				float: left;
				border-top: #ececec 1px solid;
				width: 45%;
			}
				body.rtl #bbp-dashboard-right-now .table_content {
					float: right;
				}

			#bbp-dashboard-right-now .table_discussion {
				float: right;
				border-top: #ececec 1px solid;
				width: 45%;
			}
				body.rtl #bbp-dashboard-right-now .table_discussion {
					float: left;
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
				body.rtl #bbp-dashboard-right-now td.b {
					padding-left: 6px;
					padding-right: 0;
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
				body.rtl #bbp-dashboard-right-now .t {
					padding-left: 12px;
					padding-right: 0;
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
				body.rtl #bbp-dashboard-right-now a.button {
					float: left;
					clear: left;
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
	}

	/**
	 * Registers the bbPress admin color scheme
	 *
	 * Because wp-content can exist outside of the WordPress root there is no
	 * way to be certain what the relative path of the admin images is.
	 * We are including the two most common configurations here, just in case.
	 *
	 * @since bbPress (r2521)
	 *
	 * @uses wp_admin_css_color() To register the color scheme
	 */
	public function register_admin_style () {

		// Normal wp-content dir
		if ( 0 === $this->content_depth )
			$css_file = $this->styles_url . 'admin.css';

		// Custom wp-content dir is 1 level away
		elseif ( 1 === $this->content_depth )
			$css_file = $this->styles_url . 'admin-1.css';

		// Custom wp-content dir is 1 level away
		elseif ( 2 === $this->content_depth )
			$css_file = $this->styles_url . 'admin-2.css';

		// Load the admin CSS styling
		wp_admin_css_color( 'bbpress', __( 'Green', 'bbpress' ), $css_file, array( '#222222', '#006600', '#deece1', '#6eb469' ) );
	}

	/**
	 * Update all bbPress forums across all sites
	 *
	 * @since bbPress (r3689)
	 *
	 * @global WPDB $wpdb
	 * @uses get_blog_option()
	 * @uses wp_remote_get()
	 */
	public static function update_screen() {

		// Get action
		$action = isset( $_GET['action'] ) ? $_GET['action'] : ''; ?>

		<div class="wrap">
			<div id="icon-edit" class="icon32 icon32-posts-topic"><br /></div>
			<h2><?php _e( 'Update Forum', 'bbpress' ); ?></h2>

		<?php

		// Taking action
		switch ( $action ) {
			case 'bbpress-update' :

				// @todo more update stuff here, evaluate performance

				// Remove roles and caps
				bbp_remove_roles();
				bbp_remove_caps();

				// Make sure roles, capabilities, and options exist
				bbp_add_roles();
				bbp_add_caps();
				bbp_add_options();

				// Ensure any new permalinks are created
				flush_rewrite_rules();

				// Ensure proper version in the DB
				bbp_version_bump();

				?>
			
				<p><?php _e( 'All done!', 'bbpress' ); ?></p>
				<a class="button" href="index.php?page=bbpress-update"><?php _e( 'Go Back', 'bbpress' ); ?></a>

				<?php

				break;

			case 'show' :
			default : ?>

				<p><?php _e( 'You can update your forum through this page. Hit the link below to update.', 'bbpress' ); ?></p>
				<p><a class="button" href="index.php?page=bbpress-update&amp;action=bbpress-update"><?php _e( 'Update Forum', 'bbpress' ); ?></a></p>

			<?php break;

		} ?>

		</div><?php
	}

	/**
	 * Update all bbPress forums across all sites
	 *
	 * @since bbPress (r3689)
	 *
	 * @global WPDB $wpdb
	 * @uses get_blog_option()
	 * @uses wp_remote_get()
	 */
	public static function network_update_screen() {
		global $wpdb;

		// Get action
		$action = isset( $_GET['action'] ) ? $_GET['action'] : ''; ?>

		<div class="wrap">
			<div id="icon-edit" class="icon32 icon32-posts-topic"><br /></div>
			<h2><?php _e( 'Update Forums', 'bbpress' ); ?></h2>

		<?php

		// Taking action
		switch ( $action ) {
			case 'bbpress-update' :

				// Site counter
				$n = isset( $_GET['n'] ) ? intval( $_GET['n'] ) : 0;

				// Get blogs 5 at a time
				$blogs = $wpdb->get_results( "SELECT * FROM {$wpdb->blogs} WHERE site_id = '{$wpdb->siteid}' AND spam = '0' AND deleted = '0' AND archived = '0' ORDER BY registered DESC LIMIT {$n}, 5", ARRAY_A );

				// No blogs so all done!
				if ( empty( $blogs ) ) : ?>

					<p><?php _e( 'All done!', 'bbpress' ); ?></p>
					<a class="button" href="update-core.php?page=bbpress-update"><?php _e( 'Go Back', 'bbpress' ); ?></a>

					<?php break; ?>

				<?php

				// Still have sites to loop through
				else : ?>

					<ul>

						<?php foreach ( (array) $blogs as $details ) :

							$siteurl = get_blog_option( $details['blog_id'], 'siteurl' ); ?>

							<li><?php echo $siteurl; ?></li>

							<?php

							// Get the response of the bbPress update on this site
							$response = wp_remote_get(
								trailingslashit( $siteurl ) . 'wp-admin/index.php?page=bbpress-update&step=bbpress-update',
								array( 'timeout' => 120, 'httpversion' => '1.1' )
							);

							// Site errored out, no response?
							if ( is_wp_error( $response ) )
								wp_die( sprintf( __( 'Warning! Problem updating %1$s. Your server may not be able to connect to sites running on it. Error message: <em>%2$s</em>', 'bbpress' ), $siteurl, $response->get_error_message() ) );

							// Do some actions to allow plugins to do things too
							do_action( 'after_bbpress_upgrade', $response             );
							do_action( 'bbp_upgrade_site',      $details[ 'blog_id' ] );

						endforeach; ?>

					</ul>

					<p>
						<?php _e( 'If your browser doesn&#8217;t start loading the next page automatically, click this link:', 'bbpress' ); ?>
						<a class="button" href="update-core.php?page=bbpress-update&amp;action=bbpress-update&amp;n=<?php echo ( $n + 5 ); ?>"><?php _e( 'Next Forums', 'bbpress' ); ?></a>
					</p>
					<script type='text/javascript'>
						<!--
						function nextpage() {
							location.href = 'update-core.php?page=bbpress-update&action=bbpress-update&n=<?php echo ( $n + 5 ) ?>';
						}
						setTimeout( 'nextpage()', 250 );
						//-->
					</script><?php

				endif;

				break;

			case 'show' :
			default : ?>

				<p><?php _e( 'You can update all the forums on your network through this page. It works by calling the update script of each site automatically. Hit the link below to update.', 'bbpress' ); ?></p>
				<p><a class="button" href="update-core.php-update&amp;action=bbpress-update"><?php _e( 'Update Forums', 'bbpress' ); ?></a></p>

			<?php break;

		} ?>

		</div><?php
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
	bbpress()->admin = new BBP_Admin();

	bbpress()->admin->converter = new BBP_Converter();
}

?>
