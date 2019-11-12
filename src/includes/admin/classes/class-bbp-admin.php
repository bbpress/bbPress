<?php

/**
 * Main bbPress Admin Class
 *
 * @package bbPress
 * @subpackage Administration
 */

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'BBP_Admin' ) ) :
/**
 * Loads bbPress plugin admin area
 *
 * @package bbPress
 * @subpackage Administration
 * @since 2.0.0 bbPress (r2464)
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

	/**
	 * @var string URL to the bbPress admin css directory
	 */
	public $css_url = '';

	/**
	 * @var string URL to the bbPress admin js directory
	 */
	public $js_url = '';

	/** Capability ************************************************************/

	/**
	 * @var bool Minimum capability to access Tools and Settings
	 */
	public $minimum_capability = 'keep_gate';

	/** Separator *************************************************************/

	/**
	 * @var bool Whether or not to add an extra top level menu separator
	 */
	public $show_separator = false;

	/** Tools *****************************************************************/

	/**
	 * @var array Array of available repair tools
	 */
	public $tools = array();

	/** Notices ***************************************************************/

	/**
	 * @var array Array of notices to output to the current user
	 */
	public $notices = array();

	/** Functions *************************************************************/

	/**
	 * The main bbPress admin loader
	 *
	 * @since 2.0.0 bbPress (r2515)
	 */
	public function __construct() {
		$this->setup_globals();
		$this->includes();
		$this->setup_actions();
	}

	/**
	 * Admin globals
	 *
	 * @since 2.0.0 bbPress (r2646)
	 *
	 * @access private
	 */
	private function setup_globals() {
		$bbp              = bbpress();
		$this->admin_dir  = trailingslashit( $bbp->includes_dir . 'admin'      ); // Admin path
		$this->admin_url  = trailingslashit( $bbp->includes_url . 'admin'      ); // Admin url

		// Assets
		$this->css_url    = trailingslashit( $this->admin_url   . 'assets/css' ); // Admin css URL
		$this->js_url     = trailingslashit( $this->admin_url   . 'assets/js'  ); // Admin js URL
		$this->styles_url = trailingslashit( $this->admin_url   . 'styles'     ); // Admin styles URL

		// Deprecated
		$this->images_url = trailingslashit( $this->admin_url   . 'images'     ); // Admin images URL
	}

	/**
	 * Include required files
	 *
	 * @since 2.0.0 bbPress (r2646)
	 *
	 * @access private
	 */
	private function includes() {

		// Tools
		require $this->admin_dir . 'tools.php';
		require $this->admin_dir . 'tools/common.php';
		require $this->admin_dir . 'tools/converter.php';
		require $this->admin_dir . 'tools/repair.php';
		require $this->admin_dir . 'tools/upgrade.php';
		require $this->admin_dir . 'tools/reset.php';
		require $this->admin_dir . 'tools/help.php';

		// Components
		require $this->admin_dir . 'settings.php';
		require $this->admin_dir . 'common.php';
		require $this->admin_dir . 'metaboxes.php';
		require $this->admin_dir . 'forums.php';
		require $this->admin_dir . 'topics.php';
		require $this->admin_dir . 'replies.php';
		require $this->admin_dir . 'users.php';
	}

	/**
	 * Setup the admin hooks, actions and filters
	 *
	 * @since 2.0.0 bbPress (r2646)
	 *
	 * @access private
	 */
	private function setup_actions() {

		// Bail to prevent interfering with the deactivation process
		if ( bbp_is_deactivation() ) {
			return;
		}

		/** General Actions ***************************************************/

		add_action( 'bbp_admin_menu',              array( $this, 'admin_menus'             ) );
		add_action( 'bbp_admin_head',              array( $this, 'admin_head'              ) );
		add_action( 'bbp_register_admin_styles',   array( $this, 'register_admin_styles'   ) );
		add_action( 'bbp_register_admin_scripts',  array( $this, 'register_admin_scripts'  ) );
		add_action( 'bbp_register_admin_settings', array( $this, 'register_admin_settings' ) );

		// Enqueue styles & scripts
		add_action( 'admin_enqueue_scripts',       array( $this, 'enqueue_styles'  ) );
		add_action( 'admin_enqueue_scripts',       array( $this, 'enqueue_scripts' ) );

		/** Notices ***********************************************************/

		add_action( 'bbp_admin_init',    array( $this, 'setup_notices'  ) );
		add_action( 'bbp_admin_init',    array( $this, 'hide_notices'   ) );
		add_action( 'bbp_admin_notices', array( $this, 'output_notices' ) );

		/** Upgrades **********************************************************/

		add_action( 'bbp_admin_init', array( $this, 'add_upgrade_count' ) );

		/** Ajax **************************************************************/

		// No _nopriv_ equivalent - users must be logged in
		add_action( 'wp_ajax_bbp_suggest_topic', array( $this, 'suggest_topic' ) );
		add_action( 'wp_ajax_bbp_suggest_user',  array( $this, 'suggest_user'  ) );

		/** Filters ***********************************************************/

		// Modify admin links
		add_filter( 'plugin_action_links', array( $this, 'modify_plugin_action_links' ), 10, 2 );

		// Map settings capabilities
		add_filter( 'bbp_map_meta_caps',   array( $this, 'map_settings_meta_caps' ), 10, 4 );

		// Allow keymasters to save forums settings
		add_filter( 'option_page_capability_bbpress',  array( $this, 'option_page_capability_bbpress' ) );

		/** Network Admin *****************************************************/

		// Add menu item to settings menu
		add_action( 'network_admin_menu',  array( $this, 'network_admin_menus' ) );

		/** Dependencies ******************************************************/

		// Allow plugins to modify these actions
		do_action_ref_array( 'bbp_admin_loaded', array( &$this ) );
	}

	/**
	 * Setup general admin area notices.
	 *
	 * @since 2.6.0 bbPress (r6701)
	 */
	public function setup_notices() {

		// Avoid malformed notices variable
		if ( ! is_array( $this->notices ) ) {
			$this->notices = array();
		}

		// Get page
		$page = ! empty( $_GET['page'] )
			? sanitize_key( $_GET['page'] )
			: false;

		// Pending database upgrades!
		if ( ( 'bbp-upgrade' !== $page ) && bbp_get_pending_upgrades() && current_user_can( 'bbp_tools_upgrade_page' ) ) {

			// Link to upgrade page
			$upgrade_url  = add_query_arg( array( 'page' => 'bbp-upgrade', 'status' => 'pending' ), admin_url( 'tools.php' ) );
			$dismiss_url  = wp_nonce_url( add_query_arg( array( 'bbp-hide-notice' => 'bbp-skip-upgrades' ) ), 'bbp-hide-notice' );
			$upgrade_link = '<a href="' . esc_url( $upgrade_url ) . '">' . esc_html__( 'Learn More',   'bbpress' ) . '</a>';
			$dismiss_link = '<a href="' . esc_url( $dismiss_url ) . '">' . esc_html__( 'Hide For Now', 'bbpress' ) . '</a>';
			$bbp_dashicon = '<span class="bbpress-logo-icon"></span>';
			$message      = $bbp_dashicon . sprintf(
				esc_html__( 'bbPress requires a manual database upgrade. %s or %s.', 'bbpress' ),
				$upgrade_link,
				$dismiss_link
			);

			// Add tools feedback
			$this->add_notice( $message, 'notice-bbpress', false );
		}
	}

	/**
	 * Handle hiding of general admin area notices.
	 *
	 * @since 2.6.0 bbPress (r6701)
	 */
	public function hide_notices() {

		// Hiding a notice?
		$hiding_notice = ! empty( $_GET['bbp-hide-notice'] )
			? sanitize_key( $_GET['bbp-hide-notice'] )
			: false;

		// Bail if not hiding a notice
		if ( empty( $hiding_notice ) ) {
			return;
		}

		// Bail if user cannot visit upgrade page (cannot clear notice either!)
		if ( ! current_user_can( 'bbp_tools_upgrade_page' ) ) {
			return;
		}

		// Check the admin referer
		check_admin_referer( 'bbp-hide-notice' );

		// Maybe delete notices
		switch ( $hiding_notice ) {

			// Skipped upgrade notice
			case 'bbp-skip-upgrades' :
				bbp_clear_pending_upgrades();
				break;
		}
	}

	/**
	 * Output all admin area notices
	 *
	 * @since 2.6.0 bbPress (r6771)
	 */
	public function output_notices() {

		// Bail if no notices
		if ( empty( $this->notices ) || ! is_array( $this->notices ) ) {
			return;
		}

		// Start an output buffer
		ob_start();

		// Loop through notices, and add them to buffer
		foreach ( $this->notices as $notice ) {
			echo $notice;
		}

		// Output the current buffer
		echo ob_get_clean();
	}

	/**
	 * Add a notice to the notices array
	 *
	 * @since 2.6.0 bbPress (r6771)
	 *
	 * @param string|WP_Error $message        A message to be displayed or {@link WP_Error}
	 * @param string          $class          Optional. A class to be added to the message div
	 * @param bool            $is_dismissible Optional. True to dismiss, false to persist
	 *
	 * @return void
	 */
	public function add_notice( $message, $class = false, $is_dismissible = true ) {

		// One message as string
		if ( is_string( $message ) ) {
			$message       = '<p>' . $this->esc_notice( $message ) . '</p>';
			$default_class ='updated';

		// Messages as objects
		} elseif ( is_wp_error( $message ) ) {
			$errors  = $message->get_error_messages();

			switch ( count( $errors ) ) {
				case 0:
					return false;

				case 1:
					$message = '<p>' . $this->esc_notice( $errors[0] ) . '</p>';
					break;

				default:
					$escaped = array_map( array( $this, 'esc_notice' ), $errors );
					$message = '<ul>' . "\n\t" . '<li>' . implode( '</li>' . "\n\t" . '<li>', $escaped ) . '</li>' . "\n" . '</ul>';
					break;
			}

			$default_class = 'is-error';

		// Message is an unknown format, so bail
		} else {
			return false;
		}

		// CSS Classes
		$classes = ! empty( $class )
			? array( $class )
			: array( $default_class );

		// Add dismissible class
		if ( ! empty( $is_dismissible ) ) {
			array_push( $classes, 'is-dismissible' );
		}

		// Assemble the message
		$message = '<div id="message" class="notice ' . implode( ' ', array_map( 'sanitize_html_class', $classes ) ) . '">' . $message . '</div>';
		$message = str_replace( "'", "\'", $message );

		// Avoid malformed notices variable
		if ( ! is_array( $this->notices ) ) {
			$this->notices = array();
		}

		// Add notice to notices array
		$this->notices[] = $message;
	}

	/**
	 * Escape message string output
	 *
	 * @since 2.6.0 bbPress (r6775)
	 *
	 * @param string $message
	 *
	 * @return string
	 */
	private function esc_notice( $message = '' ) {

		// Get allowed HTML
		$tags = wp_kses_allowed_html();

		// Allow spans with classes in notices
		$tags['span'] = array(
			'class' => 1
		);

		// Parse the message and remove unsafe tags
		$text = wp_kses( $message, $tags );

		// Return the message text
		return $text;
	}

	/**
	 * Maybe append the pending upgrade count to the "Tools" menu.
	 *
	 * @since 2.6.0 bbPress (r6896)
	 *
	 * @global menu $menu
	 */
	public function add_upgrade_count() {
		global $menu;

		// Skip if no menu (AJAX, shortinit, etc...)
		if ( empty( $menu ) ) {
			return;
		}

		// Loop through menus, and maybe add the upgrade count
		foreach ( $menu as $menu_index => $menu_item ) {
			$found = array_search( 'tools.php', $menu_item, true );

			if ( false !== $found ) {
				$menu[ $menu_index ][ 0 ] = bbp_maybe_append_pending_upgrade_count( $menu[ $menu_index ][ 0 ] );
				continue;
			}
		}
	}

	/**
	 * Add the admin menus
	 *
	 * @since 2.0.0 bbPress (r2646)
	 */
	public function admin_menus() {

		// Default hooks array
		$hooks = array();

		// Get the tools pages
		$tools = bbp_get_tools_admin_pages();

		// Loop through tools and check
		if ( ! empty( $tools ) ) {
			foreach ( $tools as $tool ) {

				// Try to add the admin page
				$page = add_management_page(
					$tool['name'],
					$tool['name'],
					$tool['cap'],
					$tool['page'],
					$tool['func']
				);

				// Add page to hook if user can view it
				if ( false !== $page ) {
					$hooks[] = $page;
				}
			}

			// Fudge the highlighted subnav item when on a bbPress admin page
			if ( ! empty( $hooks ) ) {
				foreach ( $hooks as $hook ) {
					add_action( "admin_head-{$hook}", 'bbp_tools_modify_menu_highlight' );
				}
			}
		}

		// Forums Tools Root
		add_management_page(
			esc_html__( 'Forums', 'bbpress' ),
			bbp_maybe_append_pending_upgrade_count( esc_html__( 'Forums', 'bbpress' ) ),
			'bbp_tools_page',
			'bbp-repair',
			'bbp_admin_repair_page'
		);

		// Are settings enabled?
		if ( 'basic' === bbp_settings_integration() ) {
			add_options_page(
				esc_html__( 'Forums',  'bbpress' ),
				esc_html__( 'Forums',  'bbpress' ),
				'bbp_settings_page',
				'bbpress',
				'bbp_admin_settings'
			);
		}

		// These are later removed in admin_head
		if ( current_user_can( 'bbp_about_page' ) ) {

			// About
			add_dashboard_page(
				esc_html__( 'Welcome to bbPress',  'bbpress' ),
				esc_html__( 'Welcome to bbPress',  'bbpress' ),
				'bbp_about_page',
				'bbp-about',
				array( $this, 'about_screen' )
			);

			// Credits
			add_dashboard_page(
				esc_html__( 'Welcome to bbPress',  'bbpress' ),
				esc_html__( 'Welcome to bbPress',  'bbpress' ),
				'bbp_about_page',
				'bbp-credits',
				array( $this, 'credits_screen' )
			);
		}

		// Bail if plugin is not network activated
		if ( ! is_plugin_active_for_network( bbpress()->basename ) ) {
			return;
		}

		add_submenu_page(
			'index.php',
			esc_html__( 'Update Forums', 'bbpress' ),
			esc_html__( 'Update Forums', 'bbpress' ),
			'manage_network',
			'bbp-update',
			array( $this, 'update_screen' )
		);
	}

	/**
	 * Add the network admin menus
	 *
	 * @since 2.1.0 bbPress (r3689)
	 */
	public function network_admin_menus() {

		// Bail if plugin is not network activated
		if ( ! is_plugin_active_for_network( bbpress()->basename ) ) {
			return;
		}

		add_submenu_page(
			'upgrade.php',
			esc_html__( 'Update Forums', 'bbpress' ),
			esc_html__( 'Update Forums', 'bbpress' ),
			'manage_network',
			'bbpress-update',
			array( $this, 'network_update_screen' )
		);
	}

	/**
	 * Register the settings
	 *
	 * @since 2.0.0 bbPress (r2737)
	 *
	 * @todo Put fields into multidimensional array
	 */
	public static function register_admin_settings() {

		// Bail if no sections available
		$sections = bbp_admin_get_settings_sections();
		if ( empty( $sections ) ) {
			return false;
		}

		// Are we using settings integration?
		$settings_integration = bbp_settings_integration();

		// Loop through sections
		foreach ( (array) $sections as $section_id => $section ) {

			// Only proceed if current user can see this section
			if ( ! current_user_can( $section_id ) ) {
				continue;
			}

			// Only add section and fields if section has fields
			$fields = bbp_admin_get_settings_fields_for_section( $section_id );
			if ( empty( $fields ) ) {
				continue;
			}

			// Overload the converter page
			if ( ! empty( $section['page'] ) && ( ( 'converter' === $section['page'] ) || ( 'deep' === $settings_integration ) ) ) {
				$page = $section['page'];
			} else {
				$page = 'bbpress';
			}

			// Add the section
			add_settings_section( $section_id, $section['title'], $section['callback'], $page );

			// Loop through fields for this section
			foreach ( (array) $fields as $field_id => $field ) {

				// Add the field
				if ( ! empty( $field['callback'] ) && ! empty( $field['title'] ) ) {
					add_settings_field( $field_id, $field['title'], $field['callback'], $page, $section_id, $field['args'] );
				}

				// Register the setting
				register_setting( $page, $field_id, $field['sanitize_callback'] );
			}
		}
	}

	/**
	 * Maps settings capabilities
	 *
	 * @since 2.2.0 bbPress (r4242)
	 *
	 * @param array $caps Capabilities for meta capability
	 * @param string $cap Capability name
	 * @param int $user_id User id
	 * @param array $args Arguments
	 *
	 * @return array Actual capabilities for meta capability
	 */
	public static function map_settings_meta_caps( $caps = array(), $cap = '', $user_id = 0, $args = array() ) {

		// What capability is being checked?
		switch ( $cap ) {

			// Pages
			case 'bbp_about_page'            : // About and Credits
			case 'bbp_tools_page'            : // Tools Page
			case 'bbp_tools_repair_page'     : // Tools - Repair Page
			case 'bbp_tools_upgrade_page'    : // Tools - Upgrade Page
			case 'bbp_tools_import_page'     : // Tools - Import Page
			case 'bbp_tools_reset_page'      : // Tools - Reset Page
			case 'bbp_settings_page'         : // Settings Page

			// Converter Sections
			case 'bbp_converter_connection'  : // Converter - Connection
			case 'bbp_converter_options'     : // Converter - Options

			// Settings Sections
			case 'bbp_settings_users'        : // Settings - Users
			case 'bbp_settings_features'     : // Settings - Features
			case 'bbp_settings_theme_compat' : // Settings - Theme compat
			case 'bbp_settings_root_slugs'   : // Settings - Root slugs
			case 'bbp_settings_single_slugs' : // Settings - Single slugs
			case 'bbp_settings_user_slugs'   : // Settings - User slugs
			case 'bbp_settings_per_page'     : // Settings - Per page
			case 'bbp_settings_per_rss_page' : // Settings - Per RSS page
				$caps = array( bbp_admin()->minimum_capability );
				break;

			// Extend - BuddyPress
			case 'bbp_settings_buddypress' :
				if ( ( is_plugin_active( 'buddypress/bp-loader.php' ) && defined( 'BP_VERSION' ) && bp_is_root_blog() ) && is_super_admin() ) {
					$caps = array( bbp_admin()->minimum_capability );
				} else {
					$caps = array( 'do_not_allow' );
				}

				break;

			// Extend - Akismet
			case 'bbp_settings_akismet' :
				if ( ( is_plugin_active( 'akismet/akismet.php' ) && defined( 'AKISMET_VERSION' ) ) && is_super_admin() ) {
					$caps = array( bbp_admin()->minimum_capability );
				} else {
					$caps = array( 'do_not_allow' );
				}

				break;
		}

		// Filter & return
		return (array) apply_filters( 'bbp_map_settings_meta_caps', $caps, $cap, $user_id, $args );
	}

	/**
	 * Register the importers
	 *
	 * @since 2.0.0 bbPress (r2737)
	 */
	public function register_importers() {

		// Leave if we're not in the import section
		if ( ! defined( 'WP_LOAD_IMPORTERS' ) ) {
			return;
		}

		// Load Importer API
		require_once ABSPATH . 'wp-admin/includes/import.php';

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
				require $import_file;
			}
		}
	}

	/**
	 * Add Settings link to plugins area
	 *
	 * @since 2.0.0 bbPress (r2737)
	 *
	 * @param array $links Links array in which we would prepend our link
	 * @param string $file Current plugin basename
	 * @return array Processed links
	 */
	public static function modify_plugin_action_links( $links, $file ) {

		// Return normal links if not bbPress
		if ( plugin_basename( bbpress()->basename ) !== $file ) {
			return $links;
		}

		// New links to merge into existing links
		$new_links = array();

		// Settings page link
		if ( current_user_can( 'bbp_settings_page' ) ) {
			$new_links['settings'] = '<a href="' . esc_url( add_query_arg( array( 'page' => 'bbpress'   ), admin_url( 'options-general.php' ) ) ) . '">' . esc_html__( 'Settings', 'bbpress' ) . '</a>';
		}

		// About page link
		if ( current_user_can( 'bbp_about_page' ) ) {
			$new_links['about']    = '<a href="' . esc_url( add_query_arg( array( 'page' => 'bbp-about' ), admin_url( 'index.php'           ) ) ) . '">' . esc_html__( 'About',    'bbpress' ) . '</a>';
		}

		// Add a few links to the existing links array
		return array_merge( $links, $new_links );
	}

	/**
	 * Enqueue any admin scripts we might need
	 *
	 * @since 2.2.0 bbPress (r4260)
	 */
	public function enqueue_scripts() {

		// Get the current screen
		$current_screen = get_current_screen();

		// Enqueue suggest for forum/topic/reply autocompletes
		wp_enqueue_script( 'suggest' );

		// Post type checker (only topics and replies)
		if ( 'post' === $current_screen->base ) {
			switch ( $current_screen->post_type ) {
				case bbp_get_reply_post_type() :
				case bbp_get_topic_post_type() :

					// Enqueue the common JS
					wp_enqueue_script( 'bbp-admin-common-js' );

					// Topics admin
					if ( bbp_get_topic_post_type() === $current_screen->post_type ) {
						wp_enqueue_script( 'bbp-admin-topics-js' );

					// Replies admin
					} elseif ( bbp_get_reply_post_type() === $current_screen->post_type ) {
						wp_enqueue_script( 'bbp-admin-replies-js' );
					}

					break;
			}

		// Enqueue the badge JS
		} elseif ( in_array( $current_screen->id, array( 'dashboard_page_bbp-about', 'dashboard_page_bbp-credits' ), true ) ) {
			wp_enqueue_script( 'bbp-admin-badge-js' );
		}
	}

	/**
	 * Enqueue any admin scripts we might need
	 *
	 * @since 2.6.0 bbPress (r5224)
	 */
	public function enqueue_styles() {
		wp_enqueue_style( 'bbp-admin-css' );
	}

	/**
	 * Remove the individual recount and converter menus.
	 * They are grouped together by h2 tabs
	 *
	 * @since 2.0.0 bbPress (r2464)
	 */
	public function admin_head() {

		// Tools
		foreach ( bbp_get_tools_admin_pages() as $tool ) {
			remove_submenu_page( 'tools.php', $tool['page'] );
		}

		// About
		remove_submenu_page( 'index.php', 'bbp-about'   );
		remove_submenu_page( 'index.php', 'bbp-credits' );
	}

	/**
	 * Registers the bbPress admin styling and color schemes
	 *
	 * Because wp-content can exist outside of the WordPress root, there is no
	 * way to be certain what the relative path of admin images is.
	 *
	 * @since 2.6.0 bbPress (r2521)
	 */
	public function register_admin_styles() {

		// RTL and/or minified
		$suffix  = is_rtl() ? '-rtl' : '';
		$suffix .= defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';

		// Get the version to use for JS
		$version = bbp_get_version();

		// Register admin CSS with dashicons dependency
		wp_register_style( 'bbp-admin-css', $this->css_url . 'admin' . $suffix . '.css', array( 'dashicons' ), $version );

		// Color schemes are not available when running out of src
		if ( false !== strpos( plugin_basename( bbpress()->file ), 'src' ) ) {
			return;
		}

		// Mint
		wp_admin_css_color(
			'bbp-mint',
			esc_html_x( 'Mint', 'admin color scheme', 'bbpress' ),
			$this->styles_url . 'mint/colors' . $suffix . '.css',
			array( '#4f6d59', '#33834e', '#5FB37C', '#81c498' ),
			array( 'base' => '#f1f3f2', 'focus' => '#fff', 'current' => '#fff' )
		);

		// Evergreen
		wp_admin_css_color(
			'bbp-evergreen',
			esc_html_x( 'Evergreen', 'admin color scheme', 'bbpress' ),
			$this->styles_url . 'evergreen/colors' . $suffix . '.css',
			array( '#324d3a', '#446950', '#56b274', '#324d3a' ),
			array( 'base' => '#f1f3f2', 'focus' => '#fff', 'current' => '#fff' )
		);
	}

	/**
	 * Registers the bbPress admin color schemes
	 *
	 * Because wp-content can exist outside of the WordPress root there is no
	 * way to be certain what the relative path of the admin images is.
	 * We are including the two most common configurations here, just in case.
	 *
	 * @since 2.6.0 bbPress (r2521)
	 */
	public function register_admin_scripts() {

		// Minified
		$suffix  = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';

		// Get the version to use for JS
		$version = bbp_get_version();

		// Header JS
		wp_register_script( 'bbp-admin-common-js',  $this->js_url . 'common'    . $suffix . '.js', array( 'jquery', 'suggest'              ), $version );
		wp_register_script( 'bbp-admin-topics-js',  $this->js_url . 'topics'    . $suffix . '.js', array( 'jquery'                         ), $version );
		wp_register_script( 'bbp-admin-replies-js', $this->js_url . 'replies'   . $suffix . '.js', array( 'jquery', 'suggest'              ), $version );
		wp_register_script( 'bbp-converter',        $this->js_url . 'converter' . $suffix . '.js', array( 'jquery', 'postbox', 'dashboard' ), $version );

		// Footer JS
		wp_register_script( 'bbp-admin-badge-js',   $this->js_url . 'badge' . $suffix . '.js', array(), $version, true );
	}

	/**
	 * Allow keymaster role to save Forums settings
	 *
	 * @since 2.3.0 bbPress (r4678)
	 *
	 * @param string $capability
	 * @return string Return minimum capability
	 */
	public function option_page_capability_bbpress( $capability = 'manage_options' ) {
		$capability = $this->minimum_capability;
		return $capability;
	}

	/** Ajax ******************************************************************/

	/**
	 * Ajax action for facilitating the forum auto-suggest
	 *
	 * @since 2.2.0 bbPress (r4261)
	 */
	public function suggest_topic() {

		// Bail early if no request
		if ( empty( $_REQUEST['q'] ) ) {
			wp_die( '0' );
		}

		// Bail if user cannot moderate - only moderators can change hierarchy
		if ( ! current_user_can( 'moderate' ) ) {
			wp_die( '0' );
		}

		// Check the ajax nonce
		check_ajax_referer( 'bbp_suggest_topic_nonce' );

		// Try to get some topics
		$topics = get_posts( array(
			's'         => bbp_db()->esc_like( $_REQUEST['q'] ),
			'post_type' => bbp_get_topic_post_type()
		) );

		// If we found some topics, loop through and display them
		if ( ! empty( $topics ) ) {
			foreach ( (array) $topics as $post ) {
				printf( esc_html__( '%s - %s', 'bbpress' ), bbp_get_topic_id( $post->ID ), bbp_get_topic_title( $post->ID ) . "\n" );
			}
		}
		die();
	}

	/**
	 * Ajax action for facilitating the topic and reply author auto-suggest
	 *
	 * @since 2.4.0 bbPress (r5014)
	 */
	public function suggest_user() {

		// Bail early if no request
		if ( empty( $_REQUEST['q'] ) ) {
			wp_die( '0' );
		}

		// Bail if user cannot moderate - only moderators can change authorship
		if ( ! current_user_can( 'moderate' ) ) {
			wp_die( '0' );
		}

		// Check the ajax nonce
		check_ajax_referer( 'bbp_suggest_user_nonce' );

		// Try to get some users
		$users_query = new WP_User_Query( array(
			'search'         => '*' . bbp_db()->esc_like( $_REQUEST['q'] ) . '*',
			'fields'         => array( 'ID', 'user_nicename' ),
			'search_columns' => array( 'ID', 'user_nicename', 'user_email' ),
			'orderby'        => 'ID'
		) );

		// If we found some users, loop through and display them
		if ( ! empty( $users_query->results ) ) {
			foreach ( (array) $users_query->results as $user ) {
				printf( esc_html__( '%s - %s', 'bbpress' ), bbp_get_user_id( $user->ID ), bbp_get_user_nicename( $user->ID, array( 'force' => $user->user_nicename ) ) . "\n" );
			}
		}
		die();
	}

	/** About *****************************************************************/

	/**
	 * Output the shared screen header for about_screen() & credits_screen()
	 *
	 * Contains title, subtitle, and badge area
	 *
	 * @since 2.6.0 bbPress (r6604)
	 */
	private function screen_header() {
		list( $display_version ) = explode( '-', bbp_get_version() ); ?>

		<h1 class="wp-heading-inline"><?php printf( esc_html__( 'Welcome to bbPress %s', 'bbpress' ), $display_version ); ?></h1>
		<hr class="wp-header-end">
		<div class="about-text"><?php printf( esc_html__( 'bbPress is fun to use, contains no artificial colors or preservatives, and is absolutely wonderful in every environment. Your community is going to love using it.', 'bbpress' ), $display_version ); ?></div>

		<span class="bbp-hive" id="bbp-hive"></span>
		<div class="bbp-badge" id="bbp-badge">
			<span class="bbp-bee" id="bbp-bee"></span>
		</div>

		<?php
	}

	/**
	 * Output the about screen
	 *
	 * @since 2.2.0 bbPress (r4159)
	 *
	 * @todo Host this remotely.
	 */
	public function about_screen() {
		?>

		<div class="wrap about-wrap">

			<?php $this->screen_header(); ?>

			<h2 class="nav-tab-wrapper">
				<a class="nav-tab nav-tab-active" href="<?php echo esc_url( add_query_arg( array( 'page' => 'bbp-about' ), admin_url( 'index.php' ) ) ); ?>">
					<?php esc_html_e( 'What&#8217;s New', 'bbpress' ); ?>
				</a><a class="nav-tab" href="<?php echo esc_url( add_query_arg( array( 'page' => 'bbp-credits' ), admin_url( 'index.php' ) ) ); ?>">
					<?php esc_html_e( 'Credits', 'bbpress' ); ?>
				</a>
			</h2>

			<div class="changelog">
				<h3><?php esc_html_e( 'Forum Subscriptions', 'bbpress' ); ?></h3>

				<div class="feature-section col two-col">
					<div class="last-feature">
						<h4><?php esc_html_e( 'Subscribe to Forums', 'bbpress' ); ?></h4>
						<p><?php esc_html_e( 'Now your users can subscribe to new topics in specific forums.', 'bbpress' ); ?></p>
					</div>

					<div>
						<h4><?php esc_html_e( 'Manage Subscriptions', 'bbpress' ); ?></h4>
						<p><?php esc_html_e( 'Your users can manage all of their subscriptions in one convenient location.', 'bbpress' ); ?></p>
					</div>
				</div>
			</div>

			<div class="changelog">
				<h3><?php esc_html_e( 'Converters', 'bbpress' ); ?></h3>

				<div class="feature-section col one-col">
					<div class="last-feature">
						<p><?php esc_html_e( 'We&#8217;re all abuzz about the hive of new importers, AEF, Drupal, FluxBB, Kunena Forums for Joomla, MyBB, Phorum, PHPFox, PHPWind, PunBB, SMF, Xenforo and XMB. Existing importers are now sweeter than honey with improved importing stickies, topic tags, forum categories and the sting is now gone if you need to remove imported users.', 'bbpress' ); ?></p>
					</div>
				</div>

				<div class="feature-section col three-col">
					<div>
						<h4><?php esc_html_e( 'Theme Compatibility', 'bbpress' ); ?></h4>
						<p><?php esc_html_e( 'Better handling of styles and scripts in the template stack.', 'bbpress' ); ?></p>
					</div>

					<div>
						<h4><?php esc_html_e( 'Polyglot support', 'bbpress' ); ?></h4>
						<p><?php esc_html_e( 'bbPress fully supports automatic translation updates.', 'bbpress' ); ?></p>
					</div>

					<div class="last-feature">
						<h4><?php esc_html_e( 'User capabilities', 'bbpress' ); ?></h4>
						<p><?php esc_html_e( 'Roles and capabilities have been swept through, cleaned up, and simplified.', 'bbpress' ); ?></p>
					</div>
				</div>
			</div>

			<div class="return-to-dashboard">
				<a href="<?php echo esc_url( add_query_arg( array( 'page' => 'bbpress' ), admin_url( 'options-general.php' ) ) ); ?>"><?php esc_html_e( 'Go to Forum Settings', 'bbpress' ); ?></a>
			</div>

		</div>

		<?php
	}

	/**
	 * Output the credits screen
	 *
	 * @since 2.2.0 bbPress (r4159)
	 *
	 * @todo Host this remotely.
	 */
	public function credits_screen() {
		?>

		<div class="wrap about-wrap">

			<?php $this->screen_header(); ?>

			<h2 class="nav-tab-wrapper">
				<a href="<?php echo esc_url( add_query_arg( array( 'page' => 'bbp-about' ), admin_url( 'index.php' ) ) ); ?>" class="nav-tab">
					<?php esc_html_e( 'What&#8217;s New', 'bbpress' ); ?>
				</a><a href="<?php echo esc_url( add_query_arg( array( 'page' => 'bbp-credits' ), admin_url( 'index.php' ) ) ); ?>" class="nav-tab nav-tab-active">
					<?php esc_html_e( 'Credits', 'bbpress' ); ?>
				</a>
			</h2>

			<p class="about-description"><?php esc_html_e( 'bbPress is created by a worldwide swarm of busy, busy bees.', 'bbpress' ); ?></p>

			<h3 class="wp-people-group"><?php esc_html_e( 'Project Leaders', 'bbpress' ); ?></h3>
			<ul class="wp-people-group " id="wp-people-group-project-leaders">
				<li class="wp-person" id="wp-person-matt">
					<a href="https://profiles.wordpress.org/matt" class="web"><img src="http://0.gravatar.com/avatar/767fc9c115a1b989744c755db47feb60?s=120" class="gravatar" alt="" />Matt Mullenweg</a>
					<span class="title"><?php esc_html_e( 'Founding Developer', 'bbpress' ); ?></span>
				</li>
				<li class="wp-person" id="wp-person-johnjamesjacoby">
					<a href="https://profiles.wordpress.org/johnjamesjacoby" class="web"><img src="http://0.gravatar.com/avatar/7a2644fb53ae2f7bfd7143b504af396c?s=120" class="gravatar" alt="" />John James Jacoby</a>
					<span class="title"><?php esc_html_e( 'Lead Developer', 'bbpress' ); ?></span>
				</li>
				<li class="wp-person" id="wp-person-jmdodd">
					<a href="https://profiles.wordpress.org/jmdodd" class="web"><img src="http://0.gravatar.com/avatar/6a7c997edea340616bcc6d0fe03f65dd?s=120" class="gravatar" alt="" />Jennifer M. Dodd</a>
					<span class="title"><?php esc_html_e( 'Feature Virtuoso', 'bbpress' ); ?></span>
				</li>
				<li class="wp-person" id="wp-person-netweb">
					<a href="https://profiles.wordpress.org/netweb" class="web"><img src="http://0.gravatar.com/avatar/97e1620b501da675315ba7cfb740e80f?s=120" class="gravatar" alt="" />Stephen Edgar</a>
					<span class="title"><?php esc_html_e( 'Tool Maven', 'bbpress' ); ?></span>
				</li>
			</ul>

			<h3 class="wp-people-group"><?php esc_html_e( 'Contributing Developers', 'bbpress' ); ?></h3>
			<ul class="wp-people-group " id="wp-people-group-contributing-developers">
				<li class="wp-person" id="wp-person-sergeybiryukov">
					<a href="https://profiles.wordpress.org/SergeyBiryukov" class="web"><img src="http://0.gravatar.com/avatar/750b7b0fcd855389264c2b1294d61bd6?s=120" class="gravatar" alt="" />Sergey Biryukov</a>
					<span class="title"><?php esc_html_e( 'Core Developer', 'bbpress' ); ?></span>
				</li>
				<li class="wp-person" id="wp-person-thebrandonallen">
					<a href="https://profiles.wordpress.org/thebrandonallen" class="web"><img src="http://0.gravatar.com/avatar/6d3f77bf3c9ca94c406dea401b566950?s?s=120" class="gravatar" alt="" />Brandon Allen</a>
					<span class="title"><?php esc_html_e( 'Core Developer', 'bbpress' ); ?></span>
				</li>
			</ul>

			<h3 class="wp-people-group"><?php esc_html_e( 'Project Emeriti', 'bbpress' ); ?></h3>
			<ul class="wp-people-group " id="wp-people-group-project-emeriti">
				<li class="wp-person" id="wp-person-gautamgupta">
					<a href="https://profiles.wordpress.org/gautamgupta" class="web"><img src="http://0.gravatar.com/avatar/b0810422cbe6e4eead4def5ae7a90b34?s=120" class="gravatar" alt="" />Gautam Gupta</a>
					<span class="title"><?php esc_html_e( 'Feature Developer', 'bbpress' ); ?></span>
				</li>
				<li class="wp-person" id="wp-person-jaredatch">
					<a href="https://profiles.wordpress.org/jaredatch" class="web"><img src="http://0.gravatar.com/avatar/e341eca9e1a85dcae7127044301b4363?s=120" class="gravatar" alt="" />Jared Atchison</a>
					<span class="title"><?php esc_html_e( 'Integration Testing', 'bbpress' ); ?></span>
				</li>
			</ul>

			<h3 class="wp-people-group"><?php esc_html_e( 'Contributors to bbPress 2.6', 'bbpress' ); ?></h3>
			<p class="wp-credits-list">
				<a href="https://profiles.wordpress.org/alex-ye">alex-ye</a>,
				<a href="https://profiles.wordpress.org/ankit-k-gupta">ankit-k-gupta</a>,
				<a href="https://profiles.wordpress.org/barryhughes-1">barryhughes-1</a>,
				<a href="https://profiles.wordpress.org/boonebgorges">boonebgorges</a>,
				<a href="https://profiles.wordpress.org/casiepa">casiepa</a>,
				<a href="https://profiles.wordpress.org/cfinke">cfinke</a>,
				<a href="https://profiles.wordpress.org/danielbachhuber">danielbachhuber</a>,
				<a href="https://profiles.wordpress.org/dimitrovadrian">dimitrov.adrian</a>,
				<a href="https://profiles.wordpress.org/DJPaul">DJPaul</a>,
				<a href="https://profiles.wordpress.org/DrPepper75">DrPepper75</a>,
				<a href="https://profiles.wordpress.org/eoigal">eoigal</a>,
				<a href="https://profiles.wordpress.org/ericlewis">ericlewis</a>,
				<a href="https://profiles.wordpress.org/extendwings">extendwings</a>,
				<a href="https://profiles.wordpress.org/Faison">Faison</a>,
				<a href="https://profiles.wordpress.org/gautamgupta">gautamgupta</a>,
				<a href="https://profiles.wordpress.org/glynwintle">glynwintle</a>,
				<a href="https://profiles.wordpress.org/gusrb84">gusrb84</a>,
				<a href="https://profiles.wordpress.org/hellofromTonya">hellofromTonya</a>,
				<a href="https://profiles.wordpress.org/icu0755">icu0755</a>,
				<a href="https://profiles.wordpress.org/imath">imath</a>,
				<a href="https://profiles.wordpress.org/jbrinley">jbrinley</a>,
				<a href="https://profiles.wordpress.org/jdgrimes">jdgrimes</a>,
				<a href="https://profiles.wordpress.org/jmdodd">jmdodd</a>,
				<a href="https://profiles.wordpress.org/joedolson">joedolson</a>,
				<a href="https://profiles.wordpress.org/johnbillion">johnbillion</a>,
				<a href="https://profiles.wordpress.org/johnjamesjacoby">johnjamesjacoby</a>,
				<a href="https://profiles.wordpress.org/jorbin">jorbin</a>,
				<a href="https://profiles.wordpress.org/jreeve">jreeve</a>,
				<a href="https://profiles.wordpress.org/kadamwhite ">kadamwhite</a>,
				<a href="https://profiles.wordpress.org/karlgroves">karlgroves</a>,
				<a href="https://profiles.wordpress.org/mat-lipe">mat-lipe</a>,
				<a href="https://profiles.wordpress.org/mazengamal">mazengamal</a>,
				<a href="https://profiles.wordpress.org/melchoyce">melchoyce</a>,
				<a href="https://profiles.wordpress.org/mercime">mercime</a>,
				<a href="https://profiles.wordpress.org/michaelbeil">michaelbeil</a>,
				<a href="https://profiles.wordpress.org/mikelopez">mikelopez</a>,
				<a href="https://profiles.wordpress.org/mordauk">mordauk</a>,
				<a href="https://profiles.wordpress.org/mspecht">mspecht</a>,
				<a href="https://profiles.wordpress.org/MZAWeb">MZAWeb</a>,
				<a href="https://profiles.wordpress.org/netweb">netweb</a>,
				<a href="https://profiles.wordpress.org/ocean90">ocean90</a>,
				<a href="https://profiles.wordpress.org/offereins">offereins</a>,
				<a href="https://profiles.wordpress.org/pareshradadiya">pareshradadiya</a>,
				<a href="https://profiles.wordpress.org/r-a-y">r-a-y</a>,
				<a href="https://profiles.wordpress.org/ramiy">ramiy</a>,
				<a href="https://profiles.wordpress.org/robin-w">robin-w</a>,
				<a href="https://profiles.wordpress.org/robkk">robkk</a>,
				<a href="https://profiles.wordpress.org/ryelle">ryelle</a>,
				<a href="https://profiles.wordpress.org/satollo">satollo</a>,
				<a href="https://profiles.wordpress.org/SergeyBiryukov">Sergey Biryukov</a>,
				<a href="https://profiles.wordpress.org/SGr33n">SGr33n</a>,
				<a href="https://profiles.wordpress.org/stephdau">stephdau</a>,
				<a href="https://profiles.wordpress.org/tharsheblows">tharsheblows</a>,
				<a href="https://profiles.wordpress.org/thebrandonallen">thebrandonallen</a>,
				<a href="https://profiles.wordpress.org/tobyhawkins">tobyhawkins</a>,
				<a href="https://profiles.wordpress.org/tonyrix">tonyrix</a>,
				<a href="https://profiles.wordpress.org/treyhunner">treyhunner</a>,
				<a href="https://profiles.wordpress.org/tw2113">tw2113</a>,
				<a href="https://profiles.wordpress.org/xknown">xknown</a>
			</p>

			<div class="return-to-dashboard">
				<a href="<?php echo esc_url( add_query_arg( array( 'page' => 'bbpress' ), admin_url( 'options-general.php' ) ) ); ?>"><?php esc_html_e( 'Go to Forum Settings', 'bbpress' ); ?></a>
			</div>

		</div>

		<?php
	}

	/** Updaters **************************************************************/

	/**
	 * Update all bbPress forums across all sites
	 *
	 * @since 2.1.0 bbPress (r3689)
	 */
	public static function update_screen() {

		// Get action
		$action = isset( $_GET['action'] ) ? $_GET['action'] : ''; ?>

		<div class="wrap">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'Update Forum', 'bbpress' ); ?></h1>
			<hr class="wp-header-end">

		<?php

		// Taking action
		switch ( $action ) {
			case 'bbp-update' :

				// Run the full updater
				bbp_version_updater(); ?>

				<p><?php esc_html_e( 'All done!', 'bbpress' ); ?></p>
				<a class="button" href="index.php?page=bbp-update"><?php esc_html_e( 'Go Back', 'bbpress' ); ?></a>

				<?php

				break;

			case 'show' :
			default : ?>

				<p><?php esc_html_e( 'You can update your forum through this page. Hit the link below to update.', 'bbpress' ); ?></p>
				<p><a class="button" href="index.php?page=bbp-update&amp;action=bbp-update"><?php esc_html_e( 'Update Forum', 'bbpress' ); ?></a></p>

			<?php break;

		} ?>

		</div><?php
	}

	/**
	 * Update all bbPress forums across all sites
	 *
	 * @since 2.1.0 bbPress (r3689)
	 */
	public static function network_update_screen() {
		$bbp_db = bbp_db();

		// Get action
		$action = isset( $_GET['action'] ) ? $_GET['action'] : ''; ?>

		<div class="wrap">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'Update Forums', 'bbpress' ); ?></h1>
			<hr class="wp-header-end">

		<?php

		// Taking action
		switch ( $action ) {
			case 'bbpress-update' :

				// Site counter
				$n = isset( $_GET['n'] ) ? intval( $_GET['n'] ) : 0;

				// Get blogs 5 at a time
				$blogs = $bbp_db->get_results( "SELECT * FROM {$bbp_db->blogs} WHERE site_id = '{$bbp_db->siteid}' AND spam = '0' AND deleted = '0' AND archived = '0' ORDER BY registered DESC LIMIT {$n}, 5", ARRAY_A );

				// No blogs so all done!
				if ( empty( $blogs ) ) : ?>

					<p><?php esc_html_e( 'All done!', 'bbpress' ); ?></p>
					<a class="button" href="update-core.php?page=bbpress-update"><?php esc_html_e( 'Go Back', 'bbpress' ); ?></a>

				<?php

				// Still have sites to loop through
				else : ?>

					<ul>

						<?php foreach ( (array) $blogs as $details ) :

							// Get site URLs
							$site_url   = get_site_url( $details['blog_id'] );
							$admin_url  = get_site_url( $details['blog_id'], 'wp-admin.php', 'admin' );
							$remote_url = add_query_arg( array(
								'page'   => 'bbp-update',
								'action' => 'bbp-update'
							), $admin_url ); ?>

							<li><?php echo esc_html( $site_url ); ?></li>

							<?php

							// Get the response of the bbPress update on this site
							$response = wp_remote_get(
								$remote_url,
								array(
									'timeout'     => 30,
									'httpversion' => '1.1'
								)
							);

							// Site errored out, no response?
							if ( is_wp_error( $response ) ) {
								wp_die( sprintf( esc_html__( 'Warning! Problem updating %1$s. Your server may not be able to connect to sites running on it. Error message: %2$s', 'bbpress' ), $site_url, '<em>' . $response->get_error_message() . '</em>' ) );
							}

							// Switch to the new site
							bbp_switch_to_site( $details[ 'blog_id' ] );

							$basename = bbpress()->basename;

							// Run the updater on this site
							if ( is_plugin_active_for_network( $basename ) || is_plugin_active( $basename ) ) {
								bbp_version_updater();
							}

							// Restore original site
							bbp_restore_current_site();

							// Do some actions to allow plugins to do things too
							do_action( 'after_bbpress_upgrade', $response             );
							do_action( 'bbp_upgrade_site',      $details[ 'blog_id' ] );

						endforeach; ?>

					</ul>

					<p>
						<?php esc_html_e( 'If your browser doesn&#8217;t start loading the next page automatically, click this link:', 'bbpress' ); ?>
						<a class="button" href="update-core.php?page=bbpress-update&amp;action=bbpress-update&amp;n=<?php echo ( $n + 5 ); ?>"><?php esc_html_e( 'Next Forums', 'bbpress' ); ?></a>
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

				<p><?php esc_html_e( 'You can update all the forums on your network through this page. It works by calling the update script of each site automatically. Hit the link below to update.', 'bbpress' ); ?></p>
				<p><a class="button" href="update-core.php?page=bbpress-update&amp;action=bbpress-update"><?php esc_html_e( 'Update Forums', 'bbpress' ); ?></a></p>

			<?php break;

		} ?>

		</div><?php
	}
}
endif; // class_exists check
