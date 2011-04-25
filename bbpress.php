<?php

/**
 * The bbPress Plugin
 *
 * bbPress is forum software with a twist from the creators of WordPress.
 *
 * @package bbPress
 * @subpackage Main
 */

/**
 * Plugin Name: bbPress
 * Plugin URI: http://bbpress.org
 * Description: bbPress is forum software with a twist from the creators of WordPress.
 * Author: The bbPress Community
 * Author URI: http://bbpress.org
 * Version: plugin-alpha-2
 */

/**
 * bbPress version
 *
 * Set the version early so other plugins have an inexpensive way to check if
 * bbPress is already loaded.
 *
 * Note: Checking for defined( 'BBP_VERSION' ) in your code does NOT
 *       guarantee bbPress is initialized and listening.
 */
define( 'BBP_VERSION', 'plugin-alpha-2' );

if ( !class_exists( 'bbPress' ) ) :
/**
 * Main bbPress Class
 *
 * Tap tap tap... Is this thing on?
 *
 * @since bbPress (r2464)
 * @todo Use BP_Component class
 */
class bbPress {

	/** Post types ************************************************************/

	/**
	 * @var string Forum post type id
	 */
	var $forum_post_type;

	/**
	 * @var string Topic post type id
	 */
	var $topic_post_type;

	/**
	 * @var string Reply post type id
	 */
	var $reply_post_type;

	/** Post statuses *********************************************************/

	/**
	 * @var string Topic tag id
	 */
	var $topic_tag_id;

	/**
	 * @var string Closed post status id. Used by topics.
	 */
	var $closed_status_id;

	/**
	 * @var string Spam post status id. Used by topics and replies.
	 */
	var $spam_status_id;

	/**
	 * @var string Trash post status id. Used by topics and replies.
	 */
	var $trash_status_id;

	/** Slugs *****************************************************************/

	/**
	 * @var string Forum slug
	 */
	var $forum_slug;

	/**
	 * @var string Topic slug
	 */
	var $topic_slug;

	/**
	 * @var string Reply slug
	 */
	var $reply_slug;

	/**
	 * @var string Topic tag slug
	 */
	var $topic_tag_slug;

	/**
	 * @var string User slug
	 */
	var $user_slug;

	/**
	 * @var string View slug
	 */
	var $view_slug;

	/** Paths *****************************************************************/

	/**
	 * @var string Absolute path to the bbPress plugin directory
	 */
	var $plugin_dir;

	/**
	 * @var string Absolute path to the bbPress themes directory
	 */
	var $themes_dir;

	/** URLs ******************************************************************/

	/**
	 * @var string URL to the bbPress plugin directory
	 */
	var $plugin_url;

	/**
	 * @var string URL to the bbPress images directory
	 */
	var $images_url;

	/**
	 * @var string URL to the bbPress themes directory
	 */
	var $themes_url;

	/** Current ID's **********************************************************/

	/**
	 * @var string Current forum id
	 */
	var $current_forum_id = null;

	/**
	 * @var string Current topic id
	 */
	var $current_topic_id = null;

	/**
	 * @var string Current reply id
	 */
	var $current_reply_id = null;

	/** Users *****************************************************************/

	/**
	 * @var object Current user
	 */
	var $current_user;

	/**
	 * @var object Displayed user
	 */
	var $displayed_user;

	/** Queries ***************************************************************/

	/**
	 * @var WP_Query For forums
	 */
	var $forum_query;

	/**
	 * @var WP_Query For topics
	 */
	var $topic_query;

	/**
	 * @var WP_Query For replies
	 */
	var $reply_query;

	/** Arrays ****************************************************************/

	/**
	 * @var array Sub Forums
	 */
	var $sub_forums;

	/** Errors ****************************************************************/

	/**
	 * @var WP_Error Used to log and display errors
	 */
	var $errors;

	/** Views *****************************************************************/

	/**
	 * @var array An array of registered bbPress views
	 */
	var $views;

	/** Forms *****************************************************************/

	/**
	 * @var int The current tab index for form building
	 */
	var $tab_index;


	/**
	 * The main bbPress loader
	 *
	 * @since bbPress (r2464)
	 *
	 * @uses bbPress::_setup_globals() Setup the globals needed
	 * @uses bbPress::_includes() Include the required files
	 * @uses bbPress::_setup_actions() Setup the hooks and actions
	 */
	function bbPress() {
		$this->_setup_globals();
		$this->_includes();
		$this->_setup_actions();
	}

	/**
	 * Component global variables
	 *
	 * @since bbPress (r2626)
	 * @access private
	 *
	 * @uses plugin_dir_path() To generate bbPress plugin path
	 * @uses plugin_dir_url() To generate bbPress plugin url
	 * @uses apply_filters() Calls various filters
	 */
	function _setup_globals() {

		/** Paths *************************************************************/

		// bbPress root directory
		$this->file             = __FILE__;
		$this->plugin_dir       = plugin_dir_path( $this->file );
		$this->plugin_url       = plugin_dir_url ( $this->file );

		// Images
		$this->images_url       = $this->plugin_url . 'bbp-images';

		// Themes
		$this->themes_dir       = WP_PLUGIN_DIR . '/' . basename( dirname( __FILE__ ) ) . '/bbp-themes';
		$this->themes_url       = $this->plugin_url . 'bbp-themes';

		/** Identifiers *******************************************************/

		// Post type identifiers
		$this->forum_post_type  = apply_filters( 'bbp_forum_post_type', 'forum'     );
		$this->topic_post_type  = apply_filters( 'bbp_topic_post_type', 'topic'     );
		$this->reply_post_type  = apply_filters( 'bbp_reply_post_type', 'reply'     );
		$this->topic_tag_id     = apply_filters( 'bbp_topic_tag_id',    'topic-tag' );

		// Status identifiers
		$this->spam_status_id   = apply_filters( 'bbp_spam_post_status',   'spam'   );
		$this->closed_status_id = apply_filters( 'bbp_closed_post_status', 'closed' );
		$this->orphan_status_id = apply_filters( 'bbp_orphan_post_status', 'orphan' );
		$this->trash_status_id  = 'trash';

		/** Slugs *************************************************************/

		// Root forum slug
		$this->root_slug        = apply_filters( 'bbp_root_slug',      get_option( '_bbp_root_slug', 'forums' ) );

		// Should we include the root slug in front of component slugs
		$prefix = !empty( $this->root_slug ) && get_option( '_bbp_include_root', true ) ? trailingslashit( $this->root_slug ) : '';

		// Component slugs
		$this->user_slug        = apply_filters( 'bbp_user_slug',      $prefix . get_option( '_bbp_user_slug',      'user'  ) );
		$this->view_slug        = apply_filters( 'bbp_view_slug',      $prefix . get_option( '_bbp_view_slug',      'view'  ) );
		$this->forum_slug       = apply_filters( 'bbp_forum_slug',     $prefix . get_option( '_bbp_forum_slug',     'forum' ) );
		$this->topic_slug       = apply_filters( 'bbp_topic_slug',     $prefix . get_option( '_bbp_topic_slug',     'topic' ) );
		$this->reply_slug       = apply_filters( 'bbp_reply_slug',     $prefix . get_option( '_bbp_reply_slug',     'reply' ) );
		$this->topic_tag_slug   = apply_filters( 'bbp_topic_tag_slug', $prefix . get_option( '_bbp_topic_tag_slug', 'tag'   ) );

		/** Misc **************************************************************/

		// Errors
		$this->errors           = new WP_Error();

		// Views
		$this->views            = array();

		// Tab Index
		$this->tab_index        = apply_filters( 'bbp_default_tab_index', 100 );

		/** Cache *************************************************************/

		// Add bbPress to global cache groups
		wp_cache_add_global_groups( 'bbpress' );
	}

	/**
	 * Include required files
	 *
	 * @since bbPress (r2626)
	 * @access private
	 *
	 * @uses is_admin() If in WordPress admin, load additional file
	 */
	function _includes() {

		/** Individual files **************************************************/
		$files = array( 'update', 'loader', 'options', 'caps', 'hooks', 'classes', 'widgets', 'shortcodes' );

		// Load the files
		foreach ( $files as $file )
			require_once( $this->plugin_dir . '/bbp-includes/bbp-' . $file . '.php' );

		/** Components ********************************************************/
		$components = array( 'general', 'forum', 'topic', 'reply', 'user' );

		// Load the function and template files
		foreach ( $components as $file ) {
			require_once( $this->plugin_dir . '/bbp-includes/bbp-' . $file . '-functions.php' );
			require_once( $this->plugin_dir . '/bbp-includes/bbp-' . $file . '-template.php'  );
		}

		/** Admin *************************************************************/

		// Quick admin check and load if needed
		if ( is_admin() )
			require_once( $this->plugin_dir . '/bbp-admin/bbp-admin.php' );
	}

	/**
	 * Setup the default hooks and actions
	 *
	 * @since bbPress (r2644)
	 * @access private
	 *
	 * @uses register_activation_hook() To register the activation hook
	 * @uses register_deactivation_hook() To register the deactivation hook
	 * @uses add_action() To add various actions
	 */
	function _setup_actions() {
		// Register bbPress activation/deactivation sequences
		register_activation_hook  ( $this->file,    'bbp_activation'   );
		register_deactivation_hook( $this->file,    'bbp_deactivation' );

		// Setup the currently logged in user
		add_action( 'bbp_setup_current_user',       array( $this, 'setup_current_user'       ), 10, 2 );

		// Register content types
		add_action( 'bbp_register_post_types',      array( $this, 'register_post_types'      ), 10, 2 );

		// Register post statuses
		add_action( 'bbp_register_post_statuses',   array( $this, 'register_post_statuses'   ), 10, 2 );

		// Register taxonomies
		add_action( 'bbp_register_taxonomies',      array( $this, 'register_taxonomies'      ), 10, 2 );

		// Register the views
		add_action( 'bbp_register_views',           array( $this, 'register_views'           ), 10, 2 );

		// Register the theme directory
		add_action( 'bbp_register_theme_directory', array( $this, 'register_theme_directory' ), 10, 2 );

		// Load textdomain
		add_action( 'bbp_load_textdomain',          array( $this, 'register_textdomain'      ), 10, 2 );

		// Add the %bbp_user% rewrite tag
		add_action( 'bbp_add_rewrite_tags',         array( $this, 'add_rewrite_tags'         ), 10, 2 );

		// Generate rewrite rules
		add_action( 'bbp_generate_rewrite_rules',   array( $this, 'generate_rewrite_rules'   ), 10, 2 );

		// Check theme compatability
		add_action( 'bbp_setup_theme_compat',       array( $this, 'theme_compat'             ), 10, 2 );
	}

	/**
	 * Register Textdomain
	 *
	 * Load the translation file for current language. Checks both the
	 * languages folder inside the bbPress plugin and the default WordPress
	 * languages folder. Note that languages inside the bbPress plugin
	 * folder will be removed on bbPress updates, and using the WordPress
	 * default folder is safer.
	 *
	 * @since bbPress (r2596)
	 *
	 * @uses apply_filters() Calls 'bbpress_locale' with the
	 *                        {@link get_locale()} value
	 * @uses load_textdomain() To load the textdomain
	 * @return bool True on success, false on failure
	 */
	function register_textdomain() {
		$locale        = apply_filters( 'bbpress_locale', get_locale() );
		$mofile        = sprintf( 'bbpress-%s.mo', $locale );
		$mofile_global = WP_LANG_DIR . '/bbpress/' . $mofile;
		$mofile_local  = $this->plugin_dir . '/bbp-languages/' . $mofile;

		if ( file_exists( $mofile_global ) )
			return load_textdomain( 'bbpress', $mofile_global );
		elseif ( file_exists( $mofile_local ) )
			return load_textdomain( 'bbpress', $mofile_local );

		return false;
	}

	/**
	 * Sets up the bbPress theme directory to use in WordPress
	 *
	 * @since bbPress (r2507)
	 *
	 * @uses register_theme_directory() To register the theme directory
	 * @return bool True on success, false on failure
	 */
	function register_theme_directory() {
		return register_theme_directory( $this->themes_dir );
	}

	/**
	 * Setup the post types for forums, topics and replies
	 *
	 * @todo messages
	 *
	 * @since bbPress (r2597)
	 *
	 * @uses register_post_type() To register the post types
	 * @uses apply_filters() Calls various filters to modify the arguments
	 *                        sent to register_post_type()
	 */
	function register_post_types() {

		/** FORUMS ************************************************************/

		// Forum labels
		$forum['labels'] = array(
			'name'               => __( 'Forums',                   'bbpress' ),
			'singular_name'      => __( 'Forum',                    'bbpress' ),
			'add_new'            => __( 'New Forum',                'bbpress' ),
			'add_new_item'       => __( 'Create New Forum',         'bbpress' ),
			'edit'               => __( 'Edit',                     'bbpress' ),
			'edit_item'          => __( 'Edit Forum',               'bbpress' ),
			'new_item'           => __( 'New Forum',                'bbpress' ),
			'view'               => __( 'View Forum',               'bbpress' ),
			'view_item'          => __( 'View Forum',               'bbpress' ),
			'search_items'       => __( 'Search Forums',            'bbpress' ),
			'not_found'          => __( 'No forums found',          'bbpress' ),
			'not_found_in_trash' => __( 'No forums found in Trash', 'bbpress' ),
			'parent_item_colon'  => __( 'Parent Forum:',            'bbpress' )
		);

		// Forum rewrite
		$forum['rewrite'] = array(
			'slug'       => $this->forum_slug,
			'with_front' => false
		);

		// Forum supports
		$forum['supports'] = array(
			'title',
			'editor',
			'revisions'
		);

		// Forum filter
		$bbp_cpt['forum'] = apply_filters( 'bbp_register_forum_post_type', array(
			'labels'            => $forum['labels'],
			'rewrite'           => $forum['rewrite'],
			'supports'          => $forum['supports'],
			'description'       => __( 'bbPress Forums', 'bbpress' ),
			'capabilities'      => bbp_get_forum_caps(),
			'capability_type'   => 'forum',
			'menu_position'     => 56,
			'show_in_nav_menus' => false,
			'has_archive'       => true,
			'public'            => true,
			'show_ui'           => true,
			'can_export'        => true,
			'hierarchical'      => true,
			'query_var'         => true,
			'menu_icon'         => ''
		) );

		// Register Forum content type
		register_post_type( $this->forum_post_type, $bbp_cpt['forum'] );

		/** TOPICS ************************************************************/

		// Topic labels
		$topic['labels'] = array(
			'name'               => __( 'Topics',                   'bbpress' ),
			'singular_name'      => __( 'Topic',                    'bbpress' ),
			'add_new'            => __( 'New Topic',                'bbpress' ),
			'add_new_item'       => __( 'Create New Topic',         'bbpress' ),
			'edit'               => __( 'Edit',                     'bbpress' ),
			'edit_item'          => __( 'Edit Topic',               'bbpress' ),
			'new_item'           => __( 'New Topic',                'bbpress' ),
			'view'               => __( 'View Topic',               'bbpress' ),
			'view_item'          => __( 'View Topic',               'bbpress' ),
			'search_items'       => __( 'Search Topics',            'bbpress' ),
			'not_found'          => __( 'No topics found',          'bbpress' ),
			'not_found_in_trash' => __( 'No topics found in Trash', 'bbpress' ),
			'parent_item_colon'  => __( 'Forum:',                   'bbpress' )
		);

		// Topic rewrite
		$topic['rewrite'] = array(
			'slug'       => $this->topic_slug,
			'with_front' => false
		);

		// Topic supports
		$topic['supports'] = array(
			'title',
			'editor',
			'revisions'
		);

		// Topic Filter
		$bbp_cpt['topic'] = apply_filters( 'bbp_register_topic_post_type', array(
			'labels'            => $topic['labels'],
			'rewrite'           => $topic['rewrite'],
			'supports'          => $topic['supports'],
			'description'       => __( 'bbPress Topics', 'bbpress' ),
			'capabilities'      => bbp_get_topic_caps(),
			'capability_type'   => 'topic',
			'menu_position'     => 57,
			'show_in_nav_menus' => false,
			'has_archive'       => true,
			'public'            => true,
			'show_ui'           => true,
			'can_export'        => true,
			'hierarchical'      => false,
			'query_var'         => true,
			'menu_icon'         => ''
		) );

		// Register Topic content type
		register_post_type( $this->topic_post_type, $bbp_cpt['topic'] );

		/** REPLIES ***********************************************************/

		// Reply labels
		$reply['labels'] = array(
			'name'               => __( 'Replies',                   'bbpress' ),
			'singular_name'      => __( 'Reply',                     'bbpress' ),
			'add_new'            => __( 'New Reply',                 'bbpress' ),
			'add_new_item'       => __( 'Create New Reply',          'bbpress' ),
			'edit'               => __( 'Edit',                      'bbpress' ),
			'edit_item'          => __( 'Edit Reply',                'bbpress' ),
			'new_item'           => __( 'New Reply',                 'bbpress' ),
			'view'               => __( 'View Reply',                'bbpress' ),
			'view_item'          => __( 'View Reply',                'bbpress' ),
			'search_items'       => __( 'Search Replies',            'bbpress' ),
			'not_found'          => __( 'No replies found',          'bbpress' ),
			'not_found_in_trash' => __( 'No replies found in Trash', 'bbpress' ),
			'parent_item_colon'  => __( 'Topic:',                    'bbpress' )
		);

		// Reply rewrite
		$reply['rewrite'] = array(
			'slug'       => $this->reply_slug,
			'with_front' => false
		);

		// Reply supports
		$reply['supports'] = array(
			'title',
			'editor',
			'revisions'
		);

		// Reply filter
		$bbp_cpt['reply'] = apply_filters( 'bbp_register_reply_post_type', array(
			'labels'            => $reply['labels'],
			'rewrite'           => $reply['rewrite'],
			'supports'          => $reply['supports'],
			'description'       => __( 'bbPress Replies', 'bbpress' ),
			'capabilities'      => bbp_get_reply_caps(),
			'capability_type'   => 'reply',
			'menu_position'     => 58,
			'has_archive'       => true,
			'show_in_nav_menus' => false,
			'public'            => true,
			'show_ui'           => true,
			'can_export'        => true,
			'hierarchical'      => false,
			'query_var'         => true,
			'menu_icon'         => ''
		) );

		// Register reply content type
		register_post_type( $this->reply_post_type, $bbp_cpt['reply'] );
	}

	/**
	 * Register the post statuses
	 *
	 * @since bbPress (r2727)
	 *
	 * @uses register_post_status() To register post statuses
	 * @uses $wp_post_statuses To modify trash and private statuses
	 * @uses current_user_can() To check if the current user is capable &
	 *                           modify $wp_post_statuses accordingly
	 */
	function register_post_statuses() {
		global $wp_post_statuses;

		// Closed
		$status = apply_filters( 'bbp_register_closed_post_status', array(
			'label'             => _x( 'Closed', 'post', 'bbpress' ),
			'label_count'       => _nx_noop( 'Closed <span class="count">(%s)</span>', 'Closed <span class="count">(%s)</span>', 'bbpress' ),
			'public'            => true,
			'show_in_admin_all' => true
		) );
		register_post_status( $this->closed_status_id, $status );

		// Spam
		$status = apply_filters( 'bbp_register_spam_post_status', array(
			'label'                     => _x( 'Spam', 'post', 'bbpress' ),
			'label_count'               => _nx_noop( 'Spam <span class="count">(%s)</span>', 'Spam <span class="count">(%s)</span>', 'bbpress' ),
			'protected'                 => true,
			'exclude_from_search'       => true,
			'show_in_admin_status_list' => true,
			'show_in_admin_all_list'    => false
		) );
		register_post_status( $this->spam_status_id, $status );

		// Orphan
		$status = apply_filters( 'bbp_register_orphan_post_status', array(
			'label'                     => _x( 'Orphan', 'post', 'bbpress' ),
			'label_count'               => _nx_noop( 'Orphan <span class="count">(%s)</span>', 'Orphans <span class="count">(%s)</span>', 'bbpress' ),
			'protected'                 => true,
			'exclude_from_search'       => true,
			'show_in_admin_status_list' => true,
			'show_in_admin_all_list'    => false
		) );
		register_post_status( $this->orphan_status_id, $status );

		/**
		 * Trash fix
		 *
		 * We need to remove the internal arg and change that to
		 * protected so that the users with 'view_trash' cap can view
		 * single trashed topics/replies in the front-end as wp_query
		 * doesn't allow any hack for the trashed topics to be viewed.
		 */
		if ( !empty( $wp_post_statuses['trash'] ) && current_user_can( 'view_trash' ) ) {
			$wp_post_statuses['trash']->internal  = false; // changed to protected
			$wp_post_statuses['trash']->protected = true;
		}

	}

	/**
	 * Register the topic tag taxonomy
	 *
	 * @since bbPress (r2464)
	 *
	 * @uses register_taxonomy() To register the taxonomy
	 */
	function register_taxonomies() {

		// Topic tag labels
		$topic_tag['labels'] = array(
			'name'          => __( 'Topic Tags',   'bbpress' ),
			'singular_name' => __( 'Topic Tag',    'bbpress' ),
			'search_items'  => __( 'Search Tags',  'bbpress' ),
			'popular_items' => __( 'Popular Tags', 'bbpress' ),
			'all_items'     => __( 'All Tags',     'bbpress' ),
			'edit_item'     => __( 'Edit Tag',     'bbpress' ),
			'update_item'   => __( 'Update Tag',   'bbpress' ),
			'add_new_item'  => __( 'Add New Tag',  'bbpress' ),
			'new_item_name' => __( 'New Tag Name', 'bbpress' )
		);

		// Topic tag rewrite
		$topic_tag['rewrite'] = array(
			'slug'       => $this->topic_tag_slug,
			'with_front' => false
		);

		// Topic tag filter
		$bbp_tt = apply_filters( 'bbp_register_topic_taxonomy', array(
			'labels'                => $topic_tag['labels'],
			'rewrite'               => $topic_tag['rewrite'],
			'capabilities'          => bbp_get_topic_tag_caps(),
			'update_count_callback' => '_update_post_term_count',
			'query_var'             => true,
			'show_tagcloud'         => true,
			'hierarchical'          => false,
			'public'                => true,
			'show_ui'               => true
		) );

		// Register the topic tag taxonomy
		register_taxonomy(
			$this->topic_tag_id,    // The topic tag id
			$this->topic_post_type, // The topic post type
			$bbp_tt
		);
	}

	/**
	 * Register the bbPress views
	 *
	 * @since bbPress (r2789)
	 *
	 * @uses bbp_register_view() To register the views
	 */
	function register_views() {

		// Topics with no replies
		$no_replies = apply_filters( 'bbp_register_view_no_replies', array(
			'meta_key'     => '_bbp_reply_count',
			'meta_value'   => 1,
			'meta_compare' => '<',
			'orderby'      => ''
		) );

		bbp_register_view( 'no-replies', __( 'Topics with no replies', 'bbpress' ), $no_replies );

	}

	/**
	 * Setup the currently logged-in user
	 *
	 * Do not to call this prematurely, I.E. before the 'init' action has
	 * started. This function is naturally hooked into 'init' to ensure proper
	 * execution. get_currentuserinfo() is used to check for XMLRPC_REQUEST to
	 * avoid xmlrpc errors.
	 *
	 * @since bbPress (r2697)
	 *
	 * @uses get_currentuserinfo()
	 * @global WP_User Current user object
	 */
	function setup_current_user() {
		global $current_user;

		if ( !isset( $current_user ) )
			$current_user = get_currentuserinfo();

		// Set the current user in the bbPress global
		$this->current_user = $current_user;
	}

	/**
	 * Add the bbPress-specific rewrite tags
	 *
	 * @since bbPress (r2753)
	 *
	 * @uses add_rewrite_tag() To add the rewrite tags
	 */
	function add_rewrite_tags() {
		// User Profile tag
		add_rewrite_tag( '%bbp_user%', '([^/]+)'   );

		// View Page tag
		add_rewrite_tag( '%bbp_view%', '([^/]+)'   );

		// Edit Page tag
		add_rewrite_tag( '%edit%',     '([1]{1,})' );
	}

	/**
	 * Register bbPress-specific rewrite rules
	 *
	 * @since bbPress (r2688)
	 *
	 * @param WP_Rewrite $wp_rewrite bbPress-sepecific rules are appended in
	 *                                $wp_rewrite->rules
	 */
	function generate_rewrite_rules( $wp_rewrite ) {

		// New rules to merge with existing
		$bbp_rules = array(

			// Edit Pages
			$this->topic_slug . '/([^/]+)/edit/?$' => 'index.php?' . $this->topic_post_type . '=' . $wp_rewrite->preg_index( 1 ) . '&edit=1',
			$this->reply_slug . '/([^/]+)/edit/?$' => 'index.php?' . $this->reply_post_type . '=' . $wp_rewrite->preg_index( 1 ) . '&edit=1',
			$this->user_slug  . '/([^/]+)/edit/?$' => 'index.php?bbp_user='                       . $wp_rewrite->preg_index( 1 ) . '&edit=1',

			// @todo - favorites feeds
			//$this->user_slug . '/([^/]+)/(feed|rdf|rss|rss2|atom)/?$'      => 'index.php?bbp_user=' . $wp_rewrite->preg_index( 1 ) . '&feed='  . $wp_rewrite->preg_index( 2 ),
			//$this->user_slug . '/([^/]+)/feed/(feed|rdf|rss|rss2|atom)/?$' => 'index.php?bbp_user=' . $wp_rewrite->preg_index( 1 ) . '&feed='  . $wp_rewrite->preg_index( 2 ),

			// Profile Page
			$this->user_slug . '/([^/]+)/page/?([0-9]{1,})/?$' => 'index.php?bbp_user=' . $wp_rewrite->preg_index( 1 ) . '&paged=' . $wp_rewrite->preg_index( 2 ),
			$this->user_slug . '/([^/]+)/?$'                   => 'index.php?bbp_user=' . $wp_rewrite->preg_index( 1 ),

			// @todo - view feeds
			//$this->view_slug . '/([^/]+)/feed/(feed|rdf|rss|rss2|atom)/?$' => 'index.php?bbp_view=' . $wp_rewrite->preg_index( 1 ) . '&feed='  . $wp_rewrite->preg_index( 2 ),

			// View Page
			$this->view_slug . '/([^/]+)/page/?([0-9]{1,})/?$' => 'index.php?bbp_view=' . $wp_rewrite->preg_index( 1 ) . '&paged=' . $wp_rewrite->preg_index( 2 ),
			$this->view_slug . '/([^/]+)/?$'                   => 'index.php?bbp_view=' . $wp_rewrite->preg_index( 1 )
		);

		// Merge bbPress rules with existing
		$wp_rewrite->rules = array_merge( $bbp_rules, $wp_rewrite->rules );

		// Return merged rules
		return $wp_rewrite;
	}

	/**
	 * If not using a bbPress compatable theme, enqueue some basic styling and js
	 *
	 * @since bbPress (r3029)
	 *
	 * @global bbPress $bbp
	 * @uses bbp_set_theme_compat() Set the compatable theme to bbp-twentyten
	 * @uses current_theme_supports() Check bbPress theme support
	 * @uses wp_enqueue_style() Enqueue the bbp-twentyten default CSS
	 * @uses wp_enqueue_script() Enqueue the bbp-twentyten default topic JS
	 */
	function theme_compat() {
		global $bbp;

		// Check if current theme supports bbPress
		if ( !current_theme_supports( 'bbpress' ) ) {

			// Set the compat_theme global for help with loading template parts
			bbp_set_theme_compat( $bbp->themes_dir . '/bbp-twentyten' );

			// Load up the default bbPress CSS from bbp-twentyten
			wp_enqueue_style ( 'bbpress-style', $bbp->themes_url . '/bbp-twentyten/css/bbpress.css'       );

			// Load up the default bbPress JS from bbp-twentyten
			wp_enqueue_script( 'bbpress-topic', $bbp->themes_url . '/bbp-twentyten/js/topic.js', 'jquery' );
		}
	}
}

// "And now here's something we hope you'll really like!"
$bbp = new bbPress();

endif; // class_exists check

/**
 * Runs on bbPress activation
 *
 * @since bbPress (r2509)
 *
 * @uses register_uninstall_hook() To register our own uninstall hook
 * @uses do_action() Calls 'bbp_activation' hook
 */
function bbp_activation() {
	register_uninstall_hook( __FILE__, 'bbp_uninstall' );

	do_action( 'bbp_activation' );
}

/**
 * Runs on bbPress deactivation
 *
 * @since bbPress (r2509)
 *
 * @uses do_action() Calls 'bbp_deactivation' hook
 */
function bbp_deactivation() {
	do_action( 'bbp_deactivation' );
}

/**
 * Runs when uninstalling bbPress
 *
 * @since bbPress (r2509)
 *
 * @uses do_action() Calls 'bbp_uninstall' hook
 */
function bbp_uninstall() {
	do_action( 'bbp_uninstall' );
}

?>
