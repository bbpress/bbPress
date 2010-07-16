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

// Attach the bbPress loaded action to the WordPress plugins_loaded action.
add_action( 'plugins_loaded',  array( 'BBP_Loader', 'loaded' ) );

// Attach the bbPress initilization to the WordPress init action.
add_action( 'init',            array( 'BBP_Loader', 'init' ) );

// Attach the bbPress constants to our own trusted action.
add_action( 'bbp_loaded',      array( 'BBP_Loader', 'constants' ) );

// Attach the bbPress includes to our own trusted action.
add_action( 'bbp_loaded',      array( 'BBP_Loader', 'includes' ) );

// Attach the bbPress textdomain loader to our own trusted action
add_action( 'bbp_init',        array( 'BBP_Loader', 'textdomain' ) );

// Attach the bbPress post type registration to our own trusted action.
add_action( 'bbp_init',        array( 'BBP_Loader', 'register_post_types' ) );

// Attach the bbPress topic tag registration to our own trusted action.
add_action( 'bbp_init',        array( 'BBP_Loader', 'register_taxonomies' ) );

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
		if ( !defined( 'BBP_TOPIC_REPLY_POST_TYPE_ID' ) )
			define( 'BBP_TOPIC_REPLY_POST_TYPE_ID', apply_filters( 'bbp_topic_reply_post_type_id', 'bbp_topic_reply' ) );

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
			
		define( 'BBP_DIR', WP_PLUGIN_DIR . '/bbpress' );
		define( 'BBP_URL', plugins_url( $path = '/bbpress' ) );

		// All done, but you can add your own stuff here
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
		require_once ( BBP_DIR . '/bb-classes.php' );
		require_once ( BBP_DIR . '/bb-templatetags.php' );

		// Are we going back to 1985 to fight Biff?
		if ( defined( 'BBP_LOAD_LEGACY' ) )
			require_once ( BBP_DIR . '/bb-legacy.php' );

		// Quick admin check and load if needed
		if ( is_admin() )
			require_once ( BBP_DIR . '/bb-admin.php' );

		// All done, but you can add your own stuff here
		do_action( 'bbp_includes' );
	}

	/**
	 * loaded()
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
	 * textdomain()
	 *
	 * Load the translation file for current language
	 */
	function textdomain () {
		$locale = apply_filters( 'bbp_textdomain', get_locale() );

		$mofile = BBP_DIR . "/bbp-languages/bbpress-$locale.mo";

		load_textdomain( 'bbpress', $mofile );
	}

	/**
	 * register_post_types()
	 *
	 * Setup the post types and taxonomy for forums
	 *
	 * @todo Finish up the post type admin area with messages, columns, etc...*
	 */
	function register_post_types() {

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

		// Register forum post type
		register_post_type (
			BBP_FORUM_POST_TYPE_ID,
			apply_filters( 'bbp_register_forum_post_type',
				array (
					'labels'            => $forum_labels,
					'menu_position'     => '100',
					'public'            => true,
					'show_ui'           => true,
					'can_export'        => true,
					'capability_type'   => 'post',
					'hierarchical'      => true,
					'rewrite'           => array (
						'slug'              => BBP_FORUM_SLUG,
						'with_front'        => false
					),
					'query_var'     => true,
					'menu_icon'     => '',
					'supports'      => array (
						'title',
						'editor',
						'thumbnail',
						'excerpt',
						'revisions',
						'page-attributes'
					)
				)
			)
		);

		// Forum post type labels
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

		// Register topic post type
		register_post_type (
			BBP_TOPIC_POST_TYPE_ID,
			apply_filters( 'bbp_register_topic_post_type',
				array (
					'labels'            => $topic_labels,
					'menu_position'     => '100',
					'public'            => true,
					'show_ui'           => true,
					'can_export'        => true,
					'capability_type'   => 'post',
					'hierarchical'      => false,
					'rewrite'           => array (
						'slug'              => BBP_TOPIC_SLUG,
						'with_front'        => false
					),
					'query_var'         => true,
					'menu_icon'         => '',
					'supports'          => array (
						'title',
						'editor',
						'thumbnail',
						'excerpt',
						'revisions',
						'comments'
					)
				)
			)
		);

		// Topic reply labels
		$topic_reply_labels = array (
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

		// Register topic reply post type
		register_post_type (
			BBP_TOPIC_REPLY_POST_TYPE_ID,
			apply_filters( 'bbp_register_topic_reply_post_type',
				array (
					'labels'            => $topic_reply_labels,
					'menu_position'     => '100',
					'public'            => true,
					'show_ui'           => true,
					'can_export'        => true,
					'capability_type'   => 'post',
					'hierarchical'      => false,
					'rewrite'           => array (
						'slug'              => BBP_REPLY_SLUG,
						'with_front'        => false
					),
					'query_var'         => true,
					'menu_icon'         => '',
					'supports'          => array (
						'title',
						'editor',
						'thumbnail',
						'excerpt',
						'revisions',
						'comments'
					)
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
	 * @package bbPress
	 * @subpackage Loader
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

		// Register the topic tag taxonomy
		register_taxonomy (
			BBP_TOPIC_TAG_ID,               // The topic tag ID
			BBP_TOPIC_POST_TYPE_ID,         // The topic post type ID
			apply_filters( 'bbp_register_topic_tag',
				array (
					'labels'                => $topic_tag_labels,
					'hierarchical'          => false,
					'update_count_callback' => '_update_post_term_count',
					'query_var'             => 'topic-tag',
					'rewrite'               => array (
						'slug'                  => 'tag'
					),
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
}

/**
 * bbp_activation ()
 *
 * Placeholder for plugin activation sequence
 */
function bbp_activation () { }
register_activation_hook   ( __FILE__, 'bbp_activation' );

/**
 * bbp_deactivation ()
 *
 * Placeholder for plugin deactivation sequence
 */
function bbp_deactivation () { }
register_deactivation_hook ( __FILE__, 'bbp_deactivation' );


?>