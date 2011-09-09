<?php

/**
 * bbPress Filters & Actions
 *
 * @package bbPress
 * @subpackage Hooks
 *
 * This file contains the actions and filters that are used through-out bbPress.
 * They are consolidated here to make searching for them easier, and to help
 * developers understand at a glance the order in which things occur.
 *
 * There are a few common places that additional actions can currently be found
 *
 *  - bbPress: In {@link bbPress::setup_actions()} in bbpress.php
 *  - Component: In {@link BBP_Component::setup_actions()} in
 *                bbp-includes/bbp-classes.php
 *  - Admin: More in {@link BBP_Admin::setup_actions()} in
 *            bbp-admin/bbp-admin.php
 */

// Exit if accessed directly
if ( !defined( 'ABSPATH' ) ) exit;

/** ACTIONS *******************************************************************/

/**
 * Attach bbPress to WordPress
 *
 * bbPress uses its own internal actions to help aid in additional plugin
 * development, and to limit the amount of potential future code changes when
 * updates to WordPress occur.
 */
add_action( 'plugins_loaded',         'bbp_loaded',                 10 );
add_action( 'init',                   'bbp_init',                   10 );
add_action( 'widgets_init',           'bbp_widgets_init',           10 );
add_action( 'generate_rewrite_rules', 'bbp_generate_rewrite_rules', 10 );
add_action( 'wp_enqueue_scripts',     'bbp_enqueue_scripts',        10 );
add_filter( 'template_include',       'bbp_template_include',       10 );

/**
 * bbp_loaded - Attached to 'plugins_loaded' above
 *
 * Attach various loader actions to the bbp_loaded action.
 * The load order helps to execute code at the correct time.
 *                                                        v---Load order
 */
add_action( 'bbp_loaded', 'bbp_constants',                2  );
add_action( 'bbp_loaded', 'bbp_boot_strap_globals',       4  );
add_action( 'bbp_loaded', 'bbp_includes',                 6  );
add_action( 'bbp_loaded', 'bbp_setup_globals',            8  );
add_action( 'bbp_loaded', 'bbp_register_theme_directory', 10 );

/**
 * bbp_init - Attached to 'init' above
 *
 * Attach various initialization actions to the init action.
 * The load order helps to execute code at the correct time.
 *                                                    v---Load order
 */
add_action( 'bbp_init', 'bbp_load_textdomain',        2   );
add_action( 'bbp_init', 'bbp_setup_option_filters',   4   );
add_action( 'bbp_init', 'bbp_setup_current_user',     6   );
add_action( 'bbp_init', 'bbp_setup_theme_compat',     8   );
add_action( 'bbp_init', 'bbp_register_post_types',    10  );
add_action( 'bbp_init', 'bbp_register_post_statuses', 12  );
add_action( 'bbp_init', 'bbp_register_taxonomies',    14  );
add_action( 'bbp_init', 'bbp_register_views',         16  );
add_action( 'bbp_init', 'bbp_register_shortcodes',    18  );
add_action( 'bbp_init', 'bbp_add_rewrite_tags',       20  );
add_action( 'bbp_init', 'bbp_ready',                  999 );

/**
 * bbp_ready - attached to end 'bbp_init' above
 *
 * Attach actions to the ready action after bbPress has fully initialized.
 * The load order helps to execute code at the correct time.
 *                                               v---Load order
 */
add_action( 'bbp_ready', 'bbp_setup_akismet',    2 ); // Spam prevention for topics and replies
add_action( 'bbp_ready', 'bbp_setup_buddypress', 4 ); // Social network integration
add_action( 'bbp_ready', 'bbp_setup_genesis',    6 ); // Popular theme framework

// Multisite Global Forum Access
add_action( 'bbp_setup_current_user', 'bbp_global_access_role_mask',  10 );

// Theme Compat
add_action( 'bbp_enqueue_scripts',    'bbp_theme_compat_enqueue_css', 10 );

// Widgets
add_action( 'bbp_widgets_init', array( 'BBP_Login_Widget',   'register_widget' ), 10 );
add_action( 'bbp_widgets_init', array( 'BBP_Views_Widget',   'register_widget' ), 10 );
add_action( 'bbp_widgets_init', array( 'BBP_Forums_Widget',  'register_widget' ), 10 );
add_action( 'bbp_widgets_init', array( 'BBP_Topics_Widget',  'register_widget' ), 10 );
add_action( 'bbp_widgets_init', array( 'BBP_Replies_Widget', 'register_widget' ), 10 );

// Template - Head, foot, errors and messages
add_action( 'wp_head',              'bbp_head'                    );
add_filter( 'wp_title',             'bbp_title',            10, 3 );
add_action( 'wp_footer',            'bbp_footer'                  );
add_action( 'bbp_loaded',           'bbp_login_notices'           );
add_action( 'bbp_head',             'bbp_topic_notices'           );
add_action( 'bbp_template_notices', 'bbp_template_notices'        );

// Add to body class
add_filter( 'body_class', 'bbp_body_class', 10, 2 );

// Caps & Roles
add_filter( 'map_meta_cap',     'bbp_map_meta_caps', 10, 4 );
add_action( 'bbp_activation',   'bbp_add_roles',     1     );
add_action( 'bbp_activation',   'bbp_add_caps',      2     );
add_action( 'bbp_deactivation', 'bbp_remove_caps',   1     );
add_action( 'bbp_deactivation', 'bbp_remove_roles',  2     );

// Options & Settings
add_action( 'bbp_activation',   'bbp_add_options',   1     );

// Multisite
add_action( 'bbp_new_site', 'bbp_add_roles',   2 );
add_action( 'bbp_new_site', 'bbp_add_caps',    4 );
add_action( 'bbp_new_site', 'bbp_add_options', 6 );

// Topic Tag Page
add_action( 'template_redirect', 'bbp_manage_topic_tag_handler', 1 );

// Before and After the Query
add_action( 'pre_get_posts',     'bbp_pre_get_posts',                2 );
add_action( 'pre_get_posts',     'bbp_pre_get_posts_exclude_forums', 4 );

// Restrict forum access
add_action( 'template_redirect', 'bbp_forum_enforce_hidden',        -1 );
add_action( 'template_redirect', 'bbp_forum_enforce_private',       -1 );

// Profile Edit
add_action( 'template_redirect', 'bbp_edit_user_handler', 1 );

// Profile Page Messages
add_action( 'bbp_template_notices', 'bbp_notice_edit_user_success'           );
add_action( 'bbp_template_notices', 'bbp_notice_edit_user_is_super_admin', 2 );

// Update forum branch
add_action( 'bbp_trashed_forum',   'bbp_update_forum_walker' );
add_action( 'bbp_untrashed_forum', 'bbp_update_forum_walker' );
add_action( 'bbp_deleted_forum',   'bbp_update_forum_walker' );
add_action( 'bbp_spammed_forum',   'bbp_update_forum_walker' );
add_action( 'bbp_unspammed_forum', 'bbp_update_forum_walker' );

// New/Edit Reply
add_action( 'template_redirect', 'bbp_new_reply_handler'         );
add_action( 'template_redirect', 'bbp_edit_reply_handler', 1     );
add_action( 'bbp_new_reply',     'bbp_update_reply',       10, 6 );
add_action( 'bbp_edit_reply',    'bbp_update_reply',       10, 6 );

// Before Delete/Trash/Untrash Reply
add_action( 'trash_post',   'bbp_trash_reply'   );
add_action( 'untrash_post', 'bbp_untrash_reply' );
add_action( 'delete_post',  'bbp_delete_reply'  );

// After Deleted/Trashed/Untrashed Reply
add_action( 'trashed_post',   'bbp_trashed_reply'   );
add_action( 'untrashed_post', 'bbp_untrashed_reply' );
add_action( 'deleted_post',   'bbp_deleted_reply'   );

// New/Edit Topic
add_action( 'template_redirect', 'bbp_new_topic_handler'         );
add_action( 'template_redirect', 'bbp_edit_topic_handler', 1     );
add_action( 'bbp_new_topic',     'bbp_update_topic',       10, 5 );
add_action( 'bbp_edit_topic',    'bbp_update_topic',       10, 5 );

// Split/Merge Topic
add_action( 'template_redirect',    'bbp_merge_topic_handler', 1    );
add_action( 'template_redirect',    'bbp_split_topic_handler', 1    );
add_action( 'bbp_merged_topic',     'bbp_merge_topic_count',   1, 3 );
add_action( 'bbp_post_split_topic', 'bbp_split_topic_count',   1, 3 );

// Before Delete/Trash/Untrash Topic
add_action( 'trash_post',   'bbp_trash_topic'   );
add_action( 'untrash_post', 'bbp_untrash_topic' );
add_action( 'delete_post',  'bbp_delete_topic'  );

// After Deleted/Trashed/Untrashed Topic
add_action( 'trashed_post',   'bbp_trashed_topic'   );
add_action( 'untrashed_post', 'bbp_untrashed_topic' );
add_action( 'deleted_post',   'bbp_deleted_topic'   );

// Topic/Reply Actions
add_action( 'template_redirect', 'bbp_toggle_topic_handler', 1 );
add_action( 'template_redirect', 'bbp_toggle_reply_handler', 1 );

// Favorites
add_action( 'template_redirect', 'bbp_favorites_handler',              1 );
add_action( 'bbp_trash_topic',   'bbp_remove_topic_from_all_favorites'   );
add_action( 'bbp_delete_topic',  'bbp_remove_topic_from_all_favorites'   );

// Subscriptions
add_action( 'template_redirect', 'bbp_subscriptions_handler',              1    );
add_action( 'bbp_trash_topic',   'bbp_remove_topic_from_all_subscriptions'      );
add_action( 'bbp_delete_topic',  'bbp_remove_topic_from_all_subscriptions'      );
add_action( 'bbp_new_reply',     'bbp_notify_subscribers',                 1, 1 );

// Sticky
add_action( 'bbp_trash_topic',  'bbp_unstick_topic' );
add_action( 'bbp_delete_topic', 'bbp_unstick_topic' );

// Update topic branch
add_action( 'bbp_trashed_topic',   'bbp_update_topic_walker' );
add_action( 'bbp_untrashed_topic', 'bbp_update_topic_walker' );
add_action( 'bbp_deleted_topic',   'bbp_update_topic_walker' );
add_action( 'bbp_spammed_topic',   'bbp_update_topic_walker' );
add_action( 'bbp_unspammed_topic', 'bbp_update_topic_walker' );

// Update reply branch
add_action( 'bbp_trashed_reply',   'bbp_update_reply_walker' );
add_action( 'bbp_untrashed_reply', 'bbp_update_reply_walker' );
add_action( 'bbp_deleted_reply',   'bbp_update_reply_walker' );
add_action( 'bbp_spammed_reply',   'bbp_update_reply_walker' );
add_action( 'bbp_unspammed_reply', 'bbp_update_reply_walker' );

// User status
add_action( 'make_ham_user',  'bbp_make_ham_user'  );
add_action( 'make_spam_user', 'bbp_make_spam_user' );

// User role
add_action( 'bbp_new_topic', 'bbp_global_access_auto_role' );
add_action( 'bbp_new_reply', 'bbp_global_access_auto_role' );

// Flush rewrite rules
add_action( 'bbp_activation',   'flush_rewrite_rules' );
add_action( 'bbp_deactivation', 'flush_rewrite_rules' );

/**
 * When a new site is created in a multisite installation, run the activation
 * routine on that site
 *
 * @since bbPress (r3283)
 *
 * @param int $blog_id
 * @param int $user_id
 * @param string $domain
 * @param string $path
 * @param int $site_id
 * @param array() $meta
 */
function bbp_new_site( $blog_id, $user_id, $domain, $path, $site_id, $meta ) {

	// Switch to the new blog
	switch_to_blog( $blog_id );

	// Do the bbPress activation routine
	do_action( 'bbp_new_site' );

	// restore original blog
	restore_current_blog();
}
add_action( 'wpmu_new_blog', 'bbp_new_site', 10, 6 );

/** FILTERS *******************************************************************/

/**
 * Template Compatibility
 *
 * If you want to completely bypass this and manage your own custom bbPress
 * template hierarchy, start here by removing this filter, then look at how
 * bbp_template_include() works and do something similar. :)
 */
add_filter( 'bbp_template_include', 'bbp_template_include_theme_supports', 2, 1 );
add_filter( 'bbp_template_include', 'bbp_template_include_theme_compat',   4, 2 );

/**
 * Feeds
 *
 * bbPress comes with a number of custom RSS2 feeds that get handled outside
 * the normal scope of feeds that WordPress would normally serve. To do this,
 * we filter every page request, listen for a feed request, and trap it.
 */
add_filter( 'request', 'bbp_request_feed_trap' );

// Links
add_filter( 'paginate_links',          'bbp_add_view_all' );
add_filter( 'bbp_get_topic_permalink', 'bbp_add_view_all' );
add_filter( 'bbp_get_reply_permalink', 'bbp_add_view_all' );
add_filter( 'bbp_get_forum_permalink', 'bbp_add_view_all' );

// wp_filter_kses on new/edit topic/reply title
add_filter( 'bbp_new_reply_pre_title',  'wp_filter_kses' );
add_filter( 'bbp_new_topic_pre_title',  'wp_filter_kses' );
add_filter( 'bbp_edit_reply_pre_title', 'wp_filter_kses' );
add_filter( 'bbp_edit_topic_pre_title', 'wp_filter_kses' );

// balanceTags, wp_filter_kses and wp_rel_nofollow on new/edit topic/reply text
add_filter( 'bbp_new_reply_pre_content',  'balanceTags'     );
add_filter( 'bbp_new_reply_pre_content',  'wp_rel_nofollow' );
add_filter( 'bbp_new_reply_pre_content',  'wp_filter_kses'  );
add_filter( 'bbp_new_topic_pre_content',  'balanceTags'     );
add_filter( 'bbp_new_topic_pre_content',  'wp_rel_nofollow' );
add_filter( 'bbp_new_topic_pre_content',  'wp_filter_kses'  );
add_filter( 'bbp_edit_reply_pre_content', 'balanceTags'     );
add_filter( 'bbp_edit_reply_pre_content', 'wp_rel_nofollow' );
add_filter( 'bbp_edit_reply_pre_content', 'wp_filter_kses'  );
add_filter( 'bbp_edit_topic_pre_content', 'balanceTags'     );
add_filter( 'bbp_edit_topic_pre_content', 'wp_rel_nofollow' );
add_filter( 'bbp_edit_topic_pre_content', 'wp_filter_kses'  );

// Add number format filter to functions requiring numeric output
add_filter( 'bbp_get_forum_topic_count',       'bbp_number_format' );
add_filter( 'bbp_get_forum_topic_reply_count', 'bbp_number_format' );

// No follow and stripslashes on user profile links
add_filter( 'bbp_get_reply_author_link',      'wp_rel_nofollow' );
add_filter( 'bbp_get_reply_author_link',      'stripslashes'    );
add_filter( 'bbp_get_topic_author_link',      'wp_rel_nofollow' );
add_filter( 'bbp_get_topic_author_link',      'stripslashes'    );
add_filter( 'bbp_get_user_favorites_link',    'wp_rel_nofollow' );
add_filter( 'bbp_get_user_favorites_link',    'stripslashes'    );
add_filter( 'bbp_get_user_subscribe_link',    'wp_rel_nofollow' );
add_filter( 'bbp_get_user_subscribe_link',    'stripslashes'    );
add_filter( 'bbp_get_user_profile_link',      'wp_rel_nofollow' );
add_filter( 'bbp_get_user_profile_link',      'stripslashes'    );
add_filter( 'bbp_get_user_profile_edit_link', 'wp_rel_nofollow' );
add_filter( 'bbp_get_user_profile_edit_link', 'stripslashes'    );

// Run filters on reply content
add_filter( 'bbp_get_reply_content', 'capital_P_dangit'         );
add_filter( 'bbp_get_reply_content', 'wptexturize',        3    );
add_filter( 'bbp_get_reply_content', 'convert_chars',      5    );
add_filter( 'bbp_get_reply_content', 'make_clickable',     9    );
add_filter( 'bbp_get_reply_content', 'force_balance_tags', 25   );
add_filter( 'bbp_get_reply_content', 'convert_smilies',    20   );
add_filter( 'bbp_get_reply_content', 'wpautop',            30   );

// Run filters on topic content
add_filter( 'bbp_get_topic_content', 'capital_P_dangit'         );
add_filter( 'bbp_get_topic_content', 'wptexturize',        3    );
add_filter( 'bbp_get_topic_content', 'convert_chars',      5    );
add_filter( 'bbp_get_topic_content', 'make_clickable',     9    );
add_filter( 'bbp_get_topic_content', 'force_balance_tags', 25   );
add_filter( 'bbp_get_topic_content', 'convert_smilies',    20   );
add_filter( 'bbp_get_topic_content', 'wpautop',            30   );

// Revisions
add_filter( 'bbp_get_reply_content', 'bbp_reply_content_append_revisions',  1, 2 );
add_filter( 'bbp_get_topic_content', 'bbp_topic_content_append_revisions',  1, 2 );

// Canonical
add_filter( 'redirect_canonical',    'bbp_redirect_canonical' );

// Login/Register/Lost Password
add_filter( 'login_redirect', 'bbp_redirect_login', 2, 3 );
add_filter( 'logout_url',     'bbp_logout_url',     2, 2 );

// Fix post author id for anonymous posts (set it back to 0) when the post status is changed
add_filter( 'wp_insert_post_data', 'bbp_fix_post_author', 30, 2 );

// Suppress private forum details
add_filter( 'bbp_get_forum_topic_count',    'bbp_suppress_private_forum_meta',  10, 2 );
add_filter( 'bbp_get_forum_reply_count',    'bbp_suppress_private_forum_meta',  10, 2 );
add_filter( 'bbp_get_forum_post_count',     'bbp_suppress_private_forum_meta',  10, 2 );
add_filter( 'bbp_get_forum_freshness_link', 'bbp_suppress_private_forum_meta',  10, 2 );
add_filter( 'bbp_get_author_link',          'bbp_suppress_private_author_link', 10, 2 );
add_filter( 'bbp_get_topic_author_link',    'bbp_suppress_private_author_link', 10, 2 );
add_filter( 'bbp_get_reply_author_link',    'bbp_suppress_private_author_link', 10, 2 );

/**
 * Add filters to anonymous post author data
 *
 * This is used to clean-up any anonymous user data that is submitted via the
 * new topic and new reply forms.
 *
 * @uses add_filter() To add filters
 */
function bbp_pre_anonymous_filters () {

	// Post author name
	$filters = array(
		'trim'                => 10,
		'sanitize_text_field' => 10,
		'wp_filter_kses'      => 10,
		'_wp_specialchars'    => 30
	);
	foreach ( $filters as $filter => $priority )
		add_filter( 'bbp_pre_anonymous_post_author_name', $filter, $priority );

	// Email saves
	foreach ( array( 'trim', 'sanitize_email', 'wp_filter_kses' ) as $filter )
		add_filter( 'bbp_pre_anonymous_post_author_email', $filter );

	// Save URL
	foreach ( array( 'trim', 'wp_strip_all_tags', 'esc_url_raw', 'wp_filter_kses' ) as $filter )
		add_filter( 'bbp_pre_anonymous_post_author_website', $filter );
}
bbp_pre_anonymous_filters();

/**
 * On multisite installations you must first allow themes to be activated and
 * show up on the theme selection screen. This function will let the bbPress
 * bundled themes show up and bypass this step.
 *
 * @since bbPress (r2944)
 *
 * @uses apply_filters() Calls 'bbp_allowed_themes' with the allowed themes list
 */
function bbp_allowed_themes( $themes ) {
	$themes['bbp-twentyten'] = 1;

	return apply_filters( 'bbp_allowed_themes', $themes );
}
add_filter( 'allowed_themes', 'bbp_allowed_themes' );

/** Admin *********************************************************************/

if ( is_admin() ) {

	/** Actions ***************************************************************/

	add_action( 'bbp_init',          'bbp_admin'                   );
	add_action( 'bbp_admin_init',    'bbp_admin_forums',         9 );
	add_action( 'bbp_admin_init',    'bbp_admin_topics',         9 );
	add_action( 'bbp_admin_init',    'bbp_admin_replies',        9 );
	add_action( 'bbp_admin_init',    'bbp_admin_settings_help'     );
	add_action( 'admin_menu',        'bbp_admin_separator'         );
	add_action( 'custom_menu_order', 'bbp_admin_custom_menu_order' );
	add_action( 'menu_order',        'bbp_admin_menu_order'        );

	/**
	 * Run the updater late on 'bbp_admin_init' to ensure that all alterations
	 * to the permalink structure have taken place. This fixes the issue of
	 * permalinks not being flushed properly when a bbPress update occurs.
	 */
	add_action( 'bbp_admin_init',    'bbp_setup_updater', 999 );

	/** Filters ***************************************************************/

	// Run wp_kses_data on topic/reply content in admin section
	add_filter( 'bbp_get_reply_content', 'wp_kses_data' );
	add_filter( 'bbp_get_topic_content', 'wp_kses_data' );
}

/**
 * Plugin Dependency
 *
 * The purpose of the following actions is to mimic the behavior of something
 * called 'plugin dependency' which enables a plugin to have plugins of their
 * own in a safe and reliable way.
 *
 * We do this in bbPress by mirroring existing WordPress actions in many places
 * allowing dependant plugins to hook into the bbPress specific ones, thus
 * guaranteeing proper code execution only when bbPress is active.
 *
 * The following functions are wrappers for their actions, allowing them to be
 * manually called and/or piggy-backed on top of other actions if needed.
 */

/** Activation Actions ********************************************************/

/**
 * Runs on bbPress activation
 *
 * @since bbPress (r2509)
 *
 * @uses register_uninstall_hook() To register our own uninstall hook
 * @uses do_action() Calls 'bbp_activation' hook
 */
function bbp_activation() {
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

/** Main Actions **************************************************************/

/**
 * Main action responsible for constants, globals, and includes
 *
 * @since bbPress (r2599)
 *
 * @uses do_action() Calls 'bbp_loaded'
 */
function bbp_loaded() {
	do_action( 'bbp_loaded' );
}

/**
 * Setup constants
 *
 * @since bbPress (r2599)
 *
 * @uses do_action() Calls 'bbp_constants'
 */
function bbp_constants() {
	do_action( 'bbp_constants' );
}

/**
 * Setup globals BEFORE includes
 *
 * @since bbPress (r2599)
 *
 * @uses do_action() Calls 'bbp_boot_strap_globals'
 */
function bbp_boot_strap_globals() {
	do_action( 'bbp_boot_strap_globals' );
}

/**
 * Include files
 *
 * @since bbPress (r2599)
 *
 * @uses do_action() Calls 'bbp_includes'
 */
function bbp_includes() {
	do_action( 'bbp_includes' );
}

/**
 * Setup globals AFTER includes
 *
 * @since bbPress (r2599)
 *
 * @uses do_action() Calls 'bbp_setup_globals'
 */
function bbp_setup_globals() {
	do_action( 'bbp_setup_globals' );
}

/**
 * Initialize any code after everything has been loaded
 *
 * @since bbPress (r2599)
 *
 * @uses do_action() Calls 'bbp_init'
 */
function bbp_init() {
	do_action ( 'bbp_init' );
}

/**
 * Initialize widgets
 *
 * @since bbPress (r3389)
 *
 * @uses do_action() Calls 'bbp_widgets_init'
 */
function bbp_widgets_init() {
	do_action ( 'bbp_widgets_init' );
}

/** Supplemental Actions ******************************************************/

/**
 * Setup the currently logged-in user
 *
 * @since bbPress (r2695)
 *
 * @uses do_action() Calls 'bbp_setup_current_user'
 */
function bbp_setup_current_user() {
	do_action ( 'bbp_setup_current_user' );
}

/**
 * Load translations for current language
 *
 * @since bbPress (r2599)
 *
 * @uses do_action() Calls 'bbp_load_textdomain'
 */
function bbp_load_textdomain() {
	do_action( 'bbp_load_textdomain' );
}

/**
 * Sets up the theme directory
 *
 * @since bbPress (r2507)
 *
 * @uses do_action() Calls 'bbp_register_theme_directory'
 */
function bbp_register_theme_directory() {
	do_action( 'bbp_register_theme_directory' );
}

/**
 * Setup the post types
 *
 * @since bbPress (r2464)
 *
 * @uses do_action() Calls 'bbp_register_post_type'
 */
function bbp_register_post_types() {
	do_action ( 'bbp_register_post_types' );
}

/**
 * Setup the post statuses
 *
 * @since bbPress (r2727)
 *
 * @uses do_action() Calls 'bbp_register_post_statuses'
 */
function bbp_register_post_statuses() {
	do_action ( 'bbp_register_post_statuses' );
}

/**
 * Register the built in bbPress taxonomies
 *
 * @since bbPress (r2464)
 *
 * @uses do_action() Calls 'bbp_register_taxonomies'
 */
function bbp_register_taxonomies() {
	do_action ( 'bbp_register_taxonomies' );
}

/**
 * Register the default bbPress views
 *
 * @since bbPress (r2789)
 *
 * @uses do_action() Calls 'bbp_register_views'
 */
function bbp_register_views() {
	do_action ( 'bbp_register_views' );
}

/**
 * Enqueue bbPress specific CSS and JS
 *
 * @since bbPress (r3373)
 *
 * @uses do_action() Calls 'bbp_enqueue_scripts'
 */
function bbp_enqueue_scripts() {
	do_action ( 'bbp_enqueue_scripts' );
}

/**
 * Add the bbPress-specific rewrite tags
 *
 * @since bbPress (r2753)
 *
 * @uses do_action() Calls 'bbp_add_rewrite_tags'
 */
function bbp_add_rewrite_tags() {
	do_action ( 'bbp_add_rewrite_tags' );
}

/**
 * Generate bbPress-specific rewrite rules
 *
 * @since bbPress (r2688)
 *
 * @param WP_Rewrite $wp_rewrite
 *
 * @uses do_action() Calls 'bbp_generate_rewrite_rules' with {@link WP_Rewrite}
 */
function bbp_generate_rewrite_rules( $wp_rewrite ) {
	do_action_ref_array( 'bbp_generate_rewrite_rules', array( &$wp_rewrite ) );
}

/** Final Action **************************************************************/

/**
 * bbPress has loaded and initialized everything, and is okay to go
 *
 * @since bbPress (r2618)
 *
 * @uses do_action() Calls 'bbp_ready'
 */
function bbp_ready() {
	do_action( 'bbp_ready' );
}

/** Theme Compatibility Filter ************************************************/

/**
 * The main filter used for theme compatibility and displaying custom bbPress
 * theme files.
 *
 * @since bbPress (r3311)
 *
 * @uses apply_filters()
 *
 * @param string $template
 * @return string Template file to use
 */
function bbp_template_include( $template = '' ) {
	return apply_filters( 'bbp_template_include', $template );
}

?>
