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

	/** Capability ************************************************************/

	/**
	 * @var bool Minimum capability to access Tools and Settings
	 */
	public $minimum_capability = 'manage_options';

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

		$hooks = array();

		// These are later removed in admin_head
		if ( bbp_current_user_can_see( 'bbp_tools_page' ) ) {
			if ( bbp_current_user_can_see( 'bbp_tools_repair_page' ) ) {
				$hooks[] = add_management_page(
					__( 'Repair Forums', 'bbpress' ),
					__( 'Forum Repair',  'bbpress' ),
					$this->minimum_capability,
					'bbp-repair',
					'bbp_admin_repair'
				);
			}

			if ( bbp_current_user_can_see( 'bbp_tools_import_page' ) ) {
				$hooks[] = add_management_page(
					__( 'Import Forums', 'bbpress' ),
					__( 'Forum Import',  'bbpress' ),
					$this->minimum_capability,
					'bbp-converter',
					'bbp_converter_settings'
				);
			}

			if ( bbp_current_user_can_see( 'bbp_tools_reset_page' ) ) {
				$hooks[] = add_management_page(
					__( 'Reset Forums', 'bbpress' ),
					__( 'Forum Reset',  'bbpress' ),
					$this->minimum_capability,
					'bbp-reset',
					'bbp_admin_reset'
				);
			}

			// Fudge the highlighted subnav item when on a bbPress admin page
			foreach( $hooks as $hook ) {
				add_action( "admin_head-$hook", 'bbp_tools_modify_menu_highlight' );
			}

			// Forums Tools Root
			add_management_page(
				__( 'Forums', 'bbpress' ),
				__( 'Forums', 'bbpress' ),
				$this->minimum_capability,
				'bbp-repair',
				'bbp_admin_repair'
			);
		}

		// Are settings enabled?
		if ( bbp_current_user_can_see( 'bbp_settings_page' ) ) {
			add_options_page(
				__( 'Forums',  'bbpress' ),
				__( 'Forums',  'bbpress' ),
				$this->minimum_capability,
				'bbpress',
				'bbp_admin_settings'
			);
		}
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
	public static function new_install() {
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
	 * @todo Put fields into multidimensional array
	 */
	public static function register_admin_settings() {

		// Bail if no sections available
		if ( ! $sections = bbp_admin_get_settings_sections() )
			return false;

		// Loop through sections
		foreach ( $sections as $section_id => $section ) {

			// Only proceed if current user can see this section
			if ( ! bbp_current_user_can_see( $section_id ) )
				continue;

			// Only add section and fields if section has fields
			if ( $fields = bbp_admin_get_settings_fields_for_section( $section_id ) ) {

				// Add the section
				add_settings_section( $section_id, $section['title'], $section['callback'], $section['page'] );

				// Loop through fields for this section
				foreach ( $fields as $field_id => $field ) {

					// Add the field
					add_settings_field( $field_id, $field['title'], $field['callback'], $section['page'], $section_id, $field['args'] );

					// Register the setting
					register_setting( $section['page'], $field_id, $field['sanitize_callback'] );
				}
			}
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
	public static function add_settings_link( $links, $file ) {

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
	public static function dashboard_widget_right_now() {
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
		$version          = bbp_get_version();
		$menu_icon_url    = $this->images_url . 'menu.png?ver='       . $version;
		$icon32_url       = $this->images_url . 'icons32.png?ver='    . $version;
		$menu_icon_url_2x = $this->images_url . 'menu-2x.png?ver='    . $version;
		$icon32_url_2x    = $this->images_url . 'icons32-2x.png?ver=' . $version;

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

			/* Icon 32 */
			#icon-edit.icon32-posts-<?php echo $forum_class; ?> {
				background: url('<?php echo $icon32_url; ?>') no-repeat -4px 0px;
			}

			#icon-edit.icon32-posts-<?php echo $topic_class; ?> {
				background: url('<?php echo $icon32_url; ?>') no-repeat -4px -90px;
			}

			#icon-edit.icon32-posts-<?php echo $reply_class; ?> {
				background: url('<?php echo $icon32_url; ?>') no-repeat -4px -180px;
			}

			/* Menu */
			#menu-posts-<?php echo $forum_class; ?> .wp-menu-image,
			#menu-posts-<?php echo $topic_class; ?> .wp-menu-image,
			#menu-posts-<?php echo $reply_class; ?> .wp-menu-image,

			#menu-posts-<?php echo $forum_class; ?>:hover .wp-menu-image,
			#menu-posts-<?php echo $topic_class; ?>:hover .wp-menu-image,
			#menu-posts-<?php echo $reply_class; ?>:hover .wp-menu-image,

			#menu-posts-<?php echo $forum_class; ?>.wp-has-current-submenu .wp-menu-image,				
			#menu-posts-<?php echo $topic_class; ?>.wp-has-current-submenu .wp-menu-image,
			#menu-posts-<?php echo $reply_class; ?>.wp-has-current-submenu .wp-menu-image {
				background: url('<?php echo $menu_icon_url; ?>');
				background-repeat: no-repeat;
			}

			/* Menu Positions */
			#menu-posts-<?php echo $forum_class; ?> .wp-menu-image {
				background-position: 0px -32px;
			}
			#menu-posts-<?php echo $forum_class; ?>:hover .wp-menu-image,
			#menu-posts-<?php echo $forum_class; ?>.wp-has-current-submenu .wp-menu-image {
				background-position: 0px 0px;
			}
			#menu-posts-<?php echo $topic_class; ?> .wp-menu-image {
				background-position: -70px -32px;
			}
			#menu-posts-<?php echo $topic_class; ?>:hover .wp-menu-image,
			#menu-posts-<?php echo $topic_class; ?>.wp-has-current-submenu .wp-menu-image {
				background-position: -70px 0px;
			}
			#menu-posts-<?php echo $reply_class; ?> .wp-menu-image {
				background-position: -35px -32px;
			}
			#menu-posts-<?php echo $reply_class; ?>:hover .wp-menu-image,
			#menu-posts-<?php echo $reply_class; ?>.wp-has-current-submenu .wp-menu-image {
				background-position:  -35px 0px;
			}

			/* Menu 2x */
			@media only screen and (-webkit-min-device-pixel-ratio: 1.5) {
				#menu-posts-<?php echo $forum_class; ?> .wp-menu-image,
				#menu-posts-<?php echo $topic_class; ?> .wp-menu-image,
				#menu-posts-<?php echo $reply_class; ?> .wp-menu-image,

				#menu-posts-<?php echo $forum_class; ?>:hover .wp-menu-image,
				#menu-posts-<?php echo $topic_class; ?>:hover .wp-menu-image,
				#menu-posts-<?php echo $reply_class; ?>:hover .wp-menu-image,

				#menu-posts-<?php echo $forum_class; ?>.wp-has-current-submenu .wp-menu-image,				
				#menu-posts-<?php echo $topic_class; ?>.wp-has-current-submenu .wp-menu-image,
				#menu-posts-<?php echo $reply_class; ?>.wp-has-current-submenu .wp-menu-image {
					background-image: url('<?php echo $menu_icon_url_2x; ?>');
					background-size: 100px 64px;
				}
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
				<p><a class="button" href="update-core.php?page=bbpress-update&amp;action=bbpress-update"><?php _e( 'Update Forums', 'bbpress' ); ?></a></p>

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
