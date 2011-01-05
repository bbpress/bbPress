<?php
/**
 * Plugin Name: bbPress
 * Plugin URI: http://bbpress.org
 * Description: bbPress is forum software with a twist from the creators of WordPress.
 * Author: The bbPress Community
 * Author URI: http://bbpress.org
 * Version: plugin-bleeding
 */

/**
 * Set the version early so other plugins have an inexpensive
 * way to check if bbPress is already loaded.
 *
 * Note: Loaded does NOT mean initialized.
 */
define( 'BBP_VERSION', 'plugin-bleeding' );

if ( !class_exists( 'bbPress' ) ) :
/**
 * BBP_Loader
 *
 * tap tap tap... Is this thing on?
 *
 * @package bbPress
 * @subpackage Loader
 * @since bbPress (r2464)
 */
class bbPress {

	// Post type
	var $forum_id;
	var $topic_id;
	var $reply_id;

	// Post status identifiers
	var $closed_status_id;
	var $spam_status_id;
	var $trash_status_id;

	// Taxonomy identifier
	var $topic_tag_id;

	// Slugs
	var $user_slug;
	var $forum_slug;
	var $topic_slug;
	var $reply_slug;
	var $topic_tag_slug;

	// Absolute Paths
	var $plugin_dir;
	var $themes_dir;

	// URLs
	var $plugin_url;
	var $images_url;
	var $themes_url;

	// Current identifiers
	var $current_forum_id;
	var $current_topic_id;
	var $current_reply_id;

	// User objects
	var $current_user;
	var $displayed_user;

	// Query objects
	var $forum_query;
	var $topic_query;
	var $reply_query;

	// Arrays
	var $sub_forums;

	// Errors
	var $errors;

	/**
	 * The main bbPress loader
	 */
	function bbPress () {
		$this->_setup_globals();
		$this->_includes();
		$this->_setup_actions();
	}

	/**
	 * _setup_globals ()
	 *
	 * Component global variables
	 */
	function _setup_globals () {

		/** Paths *****************************************************/

		// bbPress root directory
		$this->file              = __FILE__;
		$this->plugin_dir        = plugin_dir_path( $this->file );
		$this->plugin_url        = plugin_dir_url ( $this->file );

		// Images
		$this->images_url        = $this->plugin_url . 'bbp-images';

		// Themes
		$this->themes_dir        = WP_PLUGIN_DIR . '/' . basename( dirname( __FILE__ ) ) . '/bbp-themes';
		$this->themes_url        = $this->plugin_url . 'bbp-themes';

		/** Identifiers ***********************************************/

		// Post type identifiers
		$this->forum_id           = apply_filters( 'bbp_forum_post_type',  'bbp_forum'     );
		$this->topic_id           = apply_filters( 'bbp_topic_post_type',  'bbp_topic'     );
		$this->reply_id           = apply_filters( 'bbp_reply_post_type',  'bbp_reply'     );
		$this->topic_tag_id       = apply_filters( 'bbp_topic_tag_id',     'bbp_topic_tag' );

		// Status identifiers
		$this->spam_status_id     = apply_filters( 'bbp_spam_post_status',     'spam'      );
		$this->closed_status_id   = apply_filters( 'bbp_closed_post_status',   'closed'    );
		$this->trash_status_id    = 'trash';

		/** Slugs *****************************************************/

		// Root forum slug
		$this->root_slug          = apply_filters( 'bbp_root_slug',      get_option( '_bbp_root_slug', 'forums' ) );

		// Should we include the root slug in front of component slugs
		$prefix = !empty( $this->root_slug ) && get_option( '_bbp_include_root', true ) ? trailingslashit( $this->root_slug ) : '';

		// Component slugs
		$this->user_slug          = apply_filters( 'bbp_user_slug',      get_option( '_bbp_user_slug',      $prefix . 'user'  ) );
		$this->forum_slug         = apply_filters( 'bbp_forum_slug',     get_option( '_bbp_forum_slug',     $prefix . 'forum' ) );
		$this->topic_slug         = apply_filters( 'bbp_topic_slug',     get_option( '_bbp_topic_slug',     $prefix . 'topic' ) );
		$this->reply_slug         = apply_filters( 'bbp_reply_slug',     get_option( '_bbp_reply_slug',     $prefix . 'reply' ) );
		$this->topic_tag_slug     = apply_filters( 'bbp_topic_tag_slug', get_option( '_bbp_topic_tag_slug', $prefix . 'tag'   ) );

		/** Misc ******************************************************/

		// Errors
		$this->errors = new WP_Error();
	}

	/**
	 * _includes ()
	 *
	 * Include required files
	 *
	 * @uses is_admin If in WordPress admin, load additional file
	 */
	function _includes () {

		// Load the files
		require_once ( $this->plugin_dir . '/bbp-includes/bbp-loader.php'    );
		require_once ( $this->plugin_dir . '/bbp-includes/bbp-caps.php'      );
		require_once ( $this->plugin_dir . '/bbp-includes/bbp-filters.php'   );
		require_once ( $this->plugin_dir . '/bbp-includes/bbp-classes.php'   );
		require_once ( $this->plugin_dir . '/bbp-includes/bbp-functions.php' );
		require_once ( $this->plugin_dir . '/bbp-includes/bbp-widgets.php'   );
		require_once ( $this->plugin_dir . '/bbp-includes/bbp-users.php'     );

		// Load template files
		require_once ( $this->plugin_dir . '/bbp-includes/bbp-general-template.php' );
		require_once ( $this->plugin_dir . '/bbp-includes/bbp-forum-template.php'   );
		require_once ( $this->plugin_dir . '/bbp-includes/bbp-topic-template.php'   );
		require_once ( $this->plugin_dir . '/bbp-includes/bbp-reply-template.php'   );
		require_once ( $this->plugin_dir . '/bbp-includes/bbp-user-template.php'    );

		// Quick admin check and load if needed
		if ( is_admin() )
			require_once ( $this->plugin_dir . '/bbp-admin/bbp-admin.php' );
	}

	/**
	 * _setup_actions ()
	 *
	 * Setup the default hooks and actions
	 */
	function _setup_actions () {
		// Register bbPress activation/deactivation sequences
		register_activation_hook  ( $this->file,    'bbp_activation'   );
		register_deactivation_hook( $this->file,    'bbp_deactivation' );

		// Setup the currently logged in user
		add_action( 'bbp_setup_current_user',       array ( $this, 'setup_current_user'       ), 10, 2 );

		// Register content types
		add_action( 'bbp_register_post_types',      array ( $this, 'register_post_types'      ), 10, 2 );

		// Register post statuses
		add_action( 'bbp_register_post_statuses',   array ( $this, 'register_post_statuses'   ), 10, 2 );

		// Register taxonomies
		add_action( 'bbp_register_taxonomies',      array ( $this, 'register_taxonomies'      ), 10, 2 );

		// Register theme directory
		add_action( 'bbp_register_theme_directory', array ( $this, 'register_theme_directory' ), 10, 2 );

		// Load textdomain
		add_action( 'bbp_load_textdomain',          array ( $this, 'register_textdomain'      ), 10, 2 );

		// Add the %bbp_user% rewrite tag
		add_action( 'bbp_add_user_rewrite_tag',     array ( $this, 'add_user_rewrite_tag'     ), 10, 2 );

		// Generate rewrite rules, particularly for /user/%bbp_user%/ pages
		add_action( 'bbp_generate_rewrite_rules',   array ( $this, 'generate_rewrite_rules'   ), 10, 2 );
	}

	/**
	 * register_textdomain ()
	 *
	 * Load the translation file for current language. Checks both the
	 * languages folder inside the bbPress plugin and the default WordPress
	 * languages folder. Note that languages inside the bbPress plugin
	 * folder will be removed on bbPress updates, and using the WordPress
	 * default folder is safer.
	 */
	function register_textdomain () {
		$locale        = apply_filters( 'bbpress_locale', get_locale() );
		$mofile        = sprintf( 'bbpress-%s.mo', $locale );
		$mofile_global = WP_LANG_DIR . '/bbpress/' . $mofile;
		$mofile_local  = $this->plugin_dir . '/bbp-languages/' . $mofile;

		if ( file_exists( $mofile_global ) )
			return load_textdomain( 'bbpress', $mofile_global );
		elseif ( file_exists( $mofile_local ) )
			return load_textdomain( 'bbpress', $mofile_local );
		else
			return false;

		load_textdomain( 'bbpress', $mofile );
	}

	/**
	 * theme_directory ()
	 *
	 * Sets up the bbPress theme directory to use in WordPress
	 *
	 * @since bbPress (r2507)
	 * @uses register_theme_directory
	 */
	function register_theme_directory () {
		register_theme_directory( $this->themes_dir );
	}

	/**
	 * register_post_types ()
	 *
	 * Setup the post types
	 *
	 * @todo messages
	 */
	function register_post_types () {

		/** FORUMS ************************************************************/

		// Forum labels
		$forum['labels'] = array (
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
		$forum['rewrite'] = array (
			'slug'       => $this->forum_slug,
			'with_front' => false
		);

		// Forum supports
		$forum['supports'] = array (
			'title',
			'editor',
			'thumbnail',
			'excerpt'
		);

		// Forum filter
		$bbp_cpt['forum'] = apply_filters( 'bbp_register_forum_post_type', array (
			'labels'          => $forum['labels'],
			'rewrite'         => $forum['rewrite'],
			'supports'        => $forum['supports'],
			'capabilities'    => bbp_get_forum_caps(),
			'capability_type' => 'forum',
			'menu_position'   => '100',
			'public'          => true,
			'show_ui'         => true,
			'can_export'      => true,
			'hierarchical'    => true,
			'query_var'       => true,
			'menu_icon'       => ''
		) );

		// Register Forum content type
		register_post_type ( $this->forum_id, $bbp_cpt['forum'] );

		/** TOPICS ************************************************************/

		// Topic labels
		$topic['labels'] = array (
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
		$topic['rewrite'] = array (
			'slug'       => $this->topic_slug,
			'with_front' => false
		);

		// Topic supports
		$topic['supports'] = array (
			'title',
			'editor',
			'thumbnail',
			'excerpt'
		);

		// Topic Filter
		$bbp_cpt['topic'] = apply_filters( 'bbp_register_topic_post_type', array (
			'labels'          => $topic['labels'],
			'rewrite'         => $topic['rewrite'],
			'supports'        => $topic['supports'],
			'capabilities'    => bbp_get_topic_caps(),
			'capability_type' => 'topic',
			'menu_position'   => '100',
			'public'          => true,
			'show_ui'         => true,
			'can_export'      => true,
			'hierarchical'    => false,
			'query_var'       => true,
			'menu_icon'       => ''
		) );

		// Register Topic content type
		register_post_type ( $this->topic_id, $bbp_cpt['topic'] );

		/** REPLIES ***********************************************************/

		// Reply labels
		$reply['labels'] = array (
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
		$reply['rewrite'] = array (
			'slug'       => $this->reply_slug,
			'with_front' => false
		);

		// Reply supports
		$reply['supports'] = array (
			'title',
			'editor',
			'thumbnail',
			'excerpt'
		);

		// Reply filter
		$bbp_cpt['reply'] = apply_filters( 'bbp_register_reply_post_type', array (
			'labels'          => $reply['labels'],
			'rewrite'         => $reply['rewrite'],
			'supports'        => $reply['supports'],
			'capabilities'    => bbp_get_reply_caps(),
			'capability_type' => 'reply',
			'menu_position'   => '100',
			'public'          => true,
			'show_ui'         => true,
			'can_export'      => true,
			'hierarchical'    => false,
			'query_var'       => true,
			'menu_icon'       => ''
		) );

		// Register reply content type
		register_post_type ( $this->reply_id, $bbp_cpt['reply'] );
	}

	/**
	 * register_post_statuses ()
	 *
	 * Register the post statuses
	 *
	 * @since bbPress (r2727)
	 */
	function register_post_statuses () {
		global $wp_post_statuses;

		// Closed
		$status = apply_filters( 'bbp_register_closed_post_status', array (
			'label'             => _x( 'Closed', 'post', 'bbpress' ),
			'label_count'       => _nx_noop( 'Closed <span class="count">(%s)</span>', 'Closed <span class="count">(%s)</span>', 'bbpress' ),
			'public'            => true,
			'show_in_admin_all' => true
		) );
		register_post_status ( $this->closed_status_id, $status );

		// Spam
		$status = apply_filters( 'bbp_register_spam_post_status', array (
			'label'                     => _x( 'Spam', 'post', 'bbpress' ),
			'label_count'               => _nx_noop( 'Spam <span class="count">(%s)</span>', 'Spam <span class="count">(%s)</span>', 'bbpress' ),
			'protected'                 => true,
			'exclude_from_search'       => true,
			'show_in_admin_status_list' => true,
			'show_in_admin_all_list'    => false
		) );
		register_post_status ( $this->spam_status_id, $status );

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
	 * register_taxonomies ()
	 *
	 * Register the topic tag taxonomies
	 *
	 * @since bbPress (r2464)
	 *
	 * @uses register_taxonomy()
	 * @uses apply_filters()
	 */
	function register_taxonomies () {

		// Topic tag labels
		$topic_tag['labels'] = array (
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
		$topic_tag['rewrite'] = array (
			'slug'       => $this->topic_tag_slug,
			'with_front' => false
		);

		// Topic tag filter
		$bbp_tt = apply_filters( 'bbp_register_topic_taxonomy', array (
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
		register_taxonomy (
			$this->topic_tag_id, // The topic tag ID
			$this->topic_id,     // The topic content type
			$bbp_tt
		);
	}

	/**
	 * setup_current_user ()
	 *
	 * Setup the currently logged-in user
	 *
	 * @global WP_User $current_user
	 */
	function setup_current_user () {
		global $current_user;

		// Load current user if somehow it hasn't been set yet
		if ( !isset( $current_user ) )
			wp_die( 'Loading the user too soon!' );

		// Set bbPress current user to WordPress current user
		$this->current_user = $current_user;
	}

	/**
	 * add_user_rewrite_tag ()
	 *
	 * Add the %bbp_user% rewrite tag
	 *
	 * @since bbPress (r2688)
	 * @uses add_rewrite_tag
	 */
	function add_user_rewrite_tag () {
		add_rewrite_tag( '%bbp_user%',         '([^/]+)'  );
		add_rewrite_tag( '%bbp_edit_profile%', '([1]{1})' );
	}

	/**
	 * generate_rewrite_rules ()
	 *
	 * Generate rewrite rules for /user/%bbp_user%/ pages
	 *
	 * @since bbPress (r2688)
	 *
	 * @param object $wp_rewrite
	 */
	function generate_rewrite_rules ( $wp_rewrite ) {
		$user_rules = array(
			// @todo - feeds
			//$this->user_slug . '/([^/]+)/(feed|rdf|rss|rss2|atom)/?$'      => 'index.php?bbp_user=' . $wp_rewrite->preg_index( 1 ) . '&feed='  . $wp_rewrite->preg_index( 2 ),
			//$this->user_slug . '/([^/]+)/feed/(feed|rdf|rss|rss2|atom)/?$' => 'index.php?bbp_user=' . $wp_rewrite->preg_index( 1 ) . '&feed='  . $wp_rewrite->preg_index( 2 ),
			$this->user_slug . '/([^/]+)/edit/?$'                          => 'index.php?bbp_user=' . $wp_rewrite->preg_index( 1 ) . '&bbp_edit_profile=1',
			$this->user_slug . '/([^/]+)/page/?([0-9]{1,})/?$'             => 'index.php?bbp_user=' . $wp_rewrite->preg_index( 1 ) . '&paged=' . $wp_rewrite->preg_index( 2 ),
			$this->user_slug . '/([^/]+)/?$'                               => 'index.php?bbp_user=' . $wp_rewrite->preg_index( 1 )
		);

		$wp_rewrite->rules = array_merge( $user_rules, $wp_rewrite->rules );
	}
}

// "And now here's something we hope you'll really like!"
$bbp = new bbPress();

endif; // class_exists check

/**
 * bbp_activation ()
 *
 * Runs on bbPress activation
 *
 * @since bbPress (r2509)
 */
function bbp_activation () {
	register_uninstall_hook( __FILE__, 'bbp_uninstall' );

	do_action( 'bbp_activation' );
}

/**
 * bbp_deactivation ()
 *
 * Runs on bbPress deactivation
 *
 * @since bbPress (r2509)
 */
function bbp_deactivation () {
	do_action( 'bbp_deactivation' );
}

/**
 * bbp_uninstall ()
 *
 * Runs when uninstalling bbPress
 *
 * @since bbPress (r2509)
 */
function bbp_uninstall () {
	do_action( 'bbp_uninstall' );
}

?>
