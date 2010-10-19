<?php
/*
Plugin Name: bbPress
Plugin URI: http://bbpress.org
Description: bbPress is forum software with a twist from the creators of WordPress.
Author: The bbPress Community
Version: 1.2-bleeding
*/

/**
 * Set the version early so other plugins have an inexpensive
 * way to check if bbPress is already loaded.
 *
 * Note: Loaded does NOT mean initialized
 */
define( 'BBP_VERSION', '1.2-bleeding' );

/** And now for something so unbelievable it's.... UNBELIEVABLE! */

if ( !class_exists( 'BBP_Loader' ) ) :
/**
 * BBP_Loader
 *
 * tap tap tap... Is this thing on?
 *
 * @package bbPress
 * @subpackage Loader
 * @since bbPress (1.2-r2464)
 *
 */
class BBP_Loader {

	/**
	 * The main bbPress loader
	 */
	function bbp_loader () {
		// Attach the bbp_loaded action to the WordPress plugins_loaded action.
		add_action( 'plugins_loaded',  array ( $this, 'loaded' ) );

		// Attach the bbp_init to the WordPress init action.
		add_action( 'init',            array ( $this, 'init' ) );

		// Attach constants to bbp_loaded.
		add_action( 'bbp_loaded',      array ( $this, 'constants' ) );

		// Attach includes to bbp_loaded.
		add_action( 'bbp_loaded',      array ( $this, 'includes' ) );

		// Attach theme directory bbp_loaded.
		add_action( 'bbp_loaded',      array ( $this, 'register_theme_directory' ) );

		// Attach textdomain to bbp_init.
		add_action( 'bbp_init',        array ( $this, 'textdomain' ) );

		// Attach post type registration to bbp_init.
		add_action( 'bbp_init',        array ( $this, 'register_post_types' ) );

		// Attach topic tag registration bbp_init.
		add_action( 'bbp_init',        array ( $this, 'register_taxonomies' ) );

		// Register bbPress activation sequence
		register_activation_hook( __FILE__, array( $this, 'activation' ) );

		// Register bbPress deactivation sequence
		register_deactivation_hook( __FILE__, array( $this, 'deactivation' ) );
	}

	/**
	 * constants ()
	 *
	 * Default component constants that can be overridden or filtered
	 */
	function constants () {

		// Let plugins sneak in and predefine constants
		do_action( 'bbp_constants_pre' );

		// Turn debugging on/off
		if ( !defined( 'BBP_DEBUG' ) )
			define( 'BBP_DEBUG', WP_DEBUG );

		// The default forum post type ID
		if ( !defined( 'BBP_FORUM_POST_TYPE_ID' ) )
			define( 'BBP_FORUM_POST_TYPE_ID', apply_filters( 'bbp_forum_post_type_id', 'bbp_forum' ) );

		// The default topic post type ID
		if ( !defined( 'BBP_TOPIC_POST_TYPE_ID' ) )
			define( 'BBP_TOPIC_POST_TYPE_ID', apply_filters( 'bbp_topic_post_type_id', 'bbp_topic' ) );

		// The default topic post type ID
		if ( !defined( 'BBP_REPLY_POST_TYPE_ID' ) )
			define( 'BBP_REPLY_POST_TYPE_ID', apply_filters( 'bbp_reply_post_type_id', 'bbp_reply' ) );

		// The default topic post type ID
		if ( !defined( 'BBP_TOPIC_TAG_ID' ) )
			define( 'BBP_TOPIC_TAG_ID', apply_filters( 'bbp_topic_tag_id', 'bbp_topic_tag' ) );

		// Default slug for root component
		if ( !defined( 'BBP_ROOT_SLUG' ) )
			define( 'BBP_ROOT_SLUG', apply_filters( 'bbp_root_slug', 'forums' ) );

		// Default slug for topics post type
		if ( !defined( 'BBP_FORUM_SLUG' ) )
			define( 'BBP_FORUM_SLUG', apply_filters( 'bbp_forum_slug', 'forum' ) );

		// Default slug for topics post type
		if ( !defined( 'BBP_TOPIC_SLUG' ) )
			define( 'BBP_TOPIC_SLUG', apply_filters( 'bbp_topic_slug', 'topic' ) );

		// Default slug for topic reply post type
		if ( !defined( 'BBP_REPLY_SLUG' ) )
			define( 'BBP_REPLY_SLUG', apply_filters( 'bbp_reply_slug', 'reply' ) );

		// bbPress root directory
		define( 'BBP_DIR', plugin_dir_path( __FILE__ ) );
		define( 'BBP_URL', plugin_dir_url( __FILE__ ) );

		// Images URL
		define( 'BBP_IMAGES_URL', BBP_URL . '/bbp-images' );

		// Themes directory and url
		define( 'BBP_THEMES_DIR', BBP_DIR . '/bbp-themes' );
		define( 'BBP_THEMES_URL', BBP_URL . '/bbp-themes' );

		/**
		 * Constants have been defined
		 */
		do_action( 'bbp_constants' );
	}

	/**
	 * includes ()
	 *
	 * Include required files
	 *
	 * @uses is_admin If in WordPress admin, load additional file
	 */
	function includes () {

		// Let plugins sneak in and include code ahead of bbPress
		do_action( 'bbp_includes_pre' );

		// Load the files
		require_once ( BBP_DIR . '/bbp-filters.php' );
		require_once ( BBP_DIR . '/bbp-classes.php' );
		require_once ( BBP_DIR . '/bbp-functions.php' );
		require_once ( BBP_DIR . '/bbp-templatetags.php' );

		// Are we going back to 1985 to fight Biff?
		if ( defined( 'BBP_LOAD_LEGACY' ) )
			require_once ( BBP_DIR . '/bbp-legacy.php' );

		// Quick admin check and load if needed
		if ( is_admin() )
			require_once ( BBP_DIR . '/bbp-admin.php' );

		/**
		 * Everything has been included
		 */
		do_action( 'bbp_includes' );
	}

	/**
	 * loaded ()
	 *
	 * A bbPress specific action to say that it has started its
	 * boot strapping sequence. It's attached to the existing WordPress
	 * action 'plugins_loaded' because that's when all plugins have loaded. Duh. :P
	 *
	 * @uses do_action()
	 */
	function loaded () {
		do_action( 'bbp_loaded' );
	}

	/**
	 * init ()
	 *
	 * Initialize bbPress as part of the WordPress initilization process
	 *
	 * @uses do_action Calls custom action to allow external enhancement
	 */
	function init () {
		do_action ( 'bbp_init' );
	}

	/**
	 * textdomain ()
	 *
	 * Load the translation file for current language
	 */
	function textdomain () {
		$locale = apply_filters( 'bbp_textdomain', get_locale() );

		$mofile = BBP_DIR . "/bbp-languages/bbpress-$locale.mo";

		load_textdomain( 'bbpress', $mofile );

		/**
		 * Text domain has been loaded
		 */
		do_action( 'bbp_load_textdomain' );
	}

	/**
	 * register_theme_directory ()
	 *
	 * Sets up the bbPress theme directory to use in WordPress
	 *
	 * @since bbPress (1.2-r2507)
	 * @uses register_theme_directory
	 */
	function register_theme_directory () {
		register_theme_directory( BBP_THEMES_DIR );

		/**
		 * Theme directory has been registered
		 */
		do_action( 'bbp_register_theme_directory' );
	}

	/**
	 * register_post_types ()
	 *
	 * Setup the post types and taxonomy for forums
	 *
	 * @todo Finish up the post type admin area with messages, columns, etc...*
	 */
	function register_post_types () {

		// Forum post type labels
		$forum_labels = array (
			'name'                  => __( 'Forums', 'bbpress' ),
			'singular_name'         => __( 'Forum', 'bbpress' ),
			'add_new'               => __( 'New Forum', 'bbpress' ),
			'add_new_item'          => __( 'Create New Forum', 'bbpress' ),
			'edit'                  => __( 'Edit', 'bbpress' ),
			'edit_item'             => __( 'Edit Forum', 'bbpress' ),
			'new_item'              => __( 'New Forum', 'bbpress' ),
			'view'                  => __( 'View Forum', 'bbpress' ),
			'view_item'             => __( 'View Forum', 'bbpress' ),
			'search_items'          => __( 'Search Forums', 'bbpress' ),
			'not_found'             => __( 'No forums found', 'bbpress' ),
			'not_found_in_trash'    => __( 'No forums found in Trash', 'bbpress' ),
			'parent_item_colon'     => __( 'Parent Forum:', 'bbpress' )
		);

		// Forum post type rewrite
		$forum_rewrite = array (
			'slug'              => BBP_FORUM_SLUG,
			'with_front'        => false
		);

		// Forum post type supports
		$forum_supports = array (
			'title',
			'editor',
			'thumbnail',
			'excerpt',
			'page-attributes'
		);

		// Register forum post type
		register_post_type (
			BBP_FORUM_POST_TYPE_ID,
			apply_filters( 'bbp_register_forum_post_type',
				array (
					'labels'            => $forum_labels,
					'rewrite'           => $forum_rewrite,
					'supports'          => $forum_supports,
					'menu_position'     => '100',
					'public'            => true,
					'show_ui'           => true,
					'can_export'        => true,
					'capability_type'   => 'post',
					'hierarchical'      => true,
					'query_var'         => true,
					'menu_icon'         => ''
				)
			)
		);

		// Topic post type labels
		$topic_labels = array (
			'name'                  => __( 'Topics', 'bbpress' ),
			'singular_name'         => __( 'Topic', 'bbpress' ),
			'add_new'               => __( 'New Topic', 'bbpress' ),
			'add_new_item'          => __( 'Create New Topic', 'bbpress' ),
			'edit'                  => __( 'Edit', 'bbpress' ),
			'edit_item'             => __( 'Edit Topic', 'bbpress' ),
			'new_item'              => __( 'New Topic', 'bbpress' ),
			'view'                  => __( 'View Topic', 'bbpress' ),
			'view_item'             => __( 'View Topic', 'bbpress' ),
			'search_items'          => __( 'Search Topics', 'bbpress' ),
			'not_found'             => __( 'No topics found', 'bbpress' ),
			'not_found_in_trash'    => __( 'No topics found in Trash', 'bbpress' ),
			'parent_item_colon'     => __( 'Forum:', 'bbpress' )
		);

		// Topic post type rewrite
		$topic_rewrite = array (
			'slug'          => BBP_TOPIC_SLUG,
			'with_front'    => false
		);

		// Topic post type supports
		$topic_supports = array (
			'title',
			'editor',
			'thumbnail',
			'excerpt'
		);

		// Register topic post type
		register_post_type (
			BBP_TOPIC_POST_TYPE_ID,
			apply_filters( 'bbp_register_topic_post_type',
				array (
					'labels'            => $topic_labels,
					'rewrite'           => $topic_rewrite,
					'supports'          => $topic_supports,
					'menu_position'     => '100',
					'public'            => true,
					'show_ui'           => true,
					'can_export'        => true,
					'capability_type'   => 'post',
					'hierarchical'      => false,
					'query_var'         => true,
					'menu_icon'         => ''
				)
			)
		);

		// Topic reply post type labels
		$reply_labels = array (
			'name'                  => __( 'Replies', 'bbpress' ),
			'singular_name'         => __( 'Reply', 'bbpress' ),
			'add_new'               => __( 'New Reply', 'bbpress' ),
			'add_new_item'          => __( 'Create New Reply', 'bbpress' ),
			'edit'                  => __( 'Edit', 'bbpress' ),
			'edit_item'             => __( 'Edit Reply', 'bbpress' ),
			'new_item'              => __( 'New Reply', 'bbpress' ),
			'view'                  => __( 'View Reply', 'bbpress' ),
			'view_item'             => __( 'View Reply', 'bbpress' ),
			'search_items'          => __( 'Search Replies', 'bbpress' ),
			'not_found'             => __( 'No replies found', 'bbpress' ),
			'not_found_in_trash'    => __( 'No replies found in Trash', 'bbpress' ),
			'parent_item_colon'     => __( 'Topic:', 'bbpress' ),
		);

		// Topic post type rewrite
		$reply_rewrite = array (
			'slug'        => BBP_REPLY_SLUG,
			'with_front'  => false
		);

		// Topic post type supports
		$reply_supports = array (
			'title',
			'editor',
			'thumbnail',
			'excerpt'
		);

		// Register topic reply post type
		register_post_type (
			BBP_REPLY_POST_TYPE_ID,
			apply_filters( 'bbp_register_topic_reply_post_type',
				array (
					'labels'            => $reply_labels,
					'rewrite'           => $reply_rewrite,
					'supports'          => $reply_supports,
					'menu_position'     => '100',
					'public'            => true,
					'show_ui'           => true,
					'can_export'        => true,
					'capability_type'   => 'post',
					'hierarchical'      => false,
					'query_var'         => true,
					'menu_icon'         => ''
				)
			)
		);

		/**
		 * Post types have been registered
		 */
		do_action ( 'bbp_register_post_types' );
	}

	/**
	 * register_taxonomies ()
	 *
	 * Register the built in bbPress taxonomies
	 *
	 * @since bbPress (1.2-r2464)
	 *
	 * @uses register_taxonomy()
	 * @uses apply_filters(0
	 */
	function register_taxonomies () {

		// Topic tag labels
		$topic_tag_labels = array (
			'name'              => __( 'Topic Tags', 'bbpress' ),
			'singular_name'     => __( 'Topic Tag', 'bbpress' ),
			'search_items'      => __( 'Search Tags', 'bbpress' ),
			'popular_items'     => __( 'Popular Tags', 'bbpress' ),
			'all_items'         => __( 'All Tags', 'bbpress' ),
			'edit_item'         => __( 'Edit Tag', 'bbpress' ),
			'update_item'       => __( 'Update Tag', 'bbpress' ),
			'add_new_item'      => __( 'Add New Tag', 'bbpress' ),
			'new_item_name'     => __( 'New Tag Name', 'bbpress' ),
		);

		// Topic tag rewrite
		$topic_tag_rewrite = array (
			'slug' => 'tag'
		);

		// Register the topic tag taxonomy
		register_taxonomy (
			BBP_TOPIC_TAG_ID,               // The topic tag ID
			BBP_TOPIC_POST_TYPE_ID,         // The topic post type ID
			apply_filters( 'bbp_register_topic_tag',
				array (
					'labels'                => $topic_tag_labels,
					'rewrite'               => $topic_tag_rewrite,
					'update_count_callback' => '_update_post_term_count',
					'query_var'             => 'topic-tag',
					'hierarchical'          => false,
					'public'                => true,
					'show_ui'               => true,
				)
			)
		);

		/**
		 * Topic tag taxonomy has been registered
		 */
		do_action ( 'bbp_register_taxonomies' );
	}

	/**
	 * activation ()
	 *
	 * Runs on bbPress activation
	 *
	 * @since bbPress (1.2-r2509)
	 */
	function activation () {
		register_uninstall_hook( __FILE__, array( $this, 'uninstall' ) );
		/**
		 * bbPress has been activated
		 */
		do_action( 'bbp_activation' );
	}

	/**
	 * deactivation ()
	 *
	 * Runs on bbPress deactivation
	 *
	 * @since bbPress (1.2-r2509)
	 */
	function deactivation () {
		/**
		 * bbPress has been deactivated
		 */
		do_action( 'bbp_deactivation' );
	}

	/**
	 * uninstall ()
	 *
	 * Runs when uninstalling bbPress
	 *
	 * @since bbPress (1.2-r2509)
	 */
	function uninstall () {
		/**
		 * Uninstall bbPress
		 */
		do_action( 'bbp_uninstall' );
	}
}

endif; // class_exists check

$bbp_loader = new BBP_Loader();

?>
