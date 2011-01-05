<?php

/**
 * bbPress Filters & Actions
 *
 * @package bbPress
 * @subpackage Filters
 *
 * This file contains the actions and filters that are used through-out bbPress.
 * They are consolidated here to make searching for them easier, and to help
 * developers understand at a glance the order in which things occur.
 *
 * There are a few common places that additional actions can currently be found
 *
 * bbPress - In bbPress::_setup_actions() in bbpress.php
 * Component - In BBP_Component::_setup_actions() in bbp-includes/bbp-classes.php
 * Admin - More in BBP_Admin::_setup_actions() in bbp-admin/bbp-admin.php
 */

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
add_action( 'generate_rewrite_rules', 'bbp_generate_rewrite_rules', 12 );

/**
 * bbp_loaded - Attached to 'plugins_loaded' above
 *
 * Attach various loader actionss to the bbp_loaded action.
 * The load order helps to load code at the correct time.
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
 * Attach various initialization actionss to the init action.
 * The load order helps to load code at the correct time.
 *                                                    v---Load order
 */
add_action( 'bbp_init', 'bbp_setup_current_user',     2   );
add_action( 'bbp_init', 'bbp_register_post_types',    4   );
add_action( 'bbp_init', 'bbp_register_post_statuses', 6   );
add_action( 'bbp_init', 'bbp_register_taxonomies',    8   );
add_action( 'bbp_init', 'bbp_register_textdomain',    10  );
add_action( 'bbp_init', 'bbp_add_rewrite_tags',       12  );
add_action( 'bbp_init', 'bbp_ready',                  999 );

// Admin
if ( is_admin() ) {
	add_action( 'bbp_init',   'bbp_admin'           );
	add_action( 'admin_menu', 'bbp_admin_separator' );
}

// Template - Head, foot, errors and notices
add_action( 'wp_head',              'bbp_head'           );
add_action( 'wp_footer',            'bbp_footer'         );
add_action( 'bbp_template_notices', 'bbp_error_messages' );
add_action( 'bbp_template_notices', 'bbp_topic_notices' );

// Caps & Roles
add_filter( 'map_meta_cap',     'bbp_map_meta_caps', 10, 4 );
add_action( 'bbp_activation',   'bbp_add_roles',     1     );
add_action( 'bbp_activation',   'bbp_add_caps',      2     );
add_action( 'bbp_deactivation', 'bbp_remove_caps',   1     );
add_action( 'bbp_deactivation', 'bbp_remove_roles',  2     );

// Profile Page
add_filter( 'wp_title',          'bbp_profile_page_title',     10, 3 );
add_action( 'pre_get_posts',     'bbp_pre_get_posts',          1     );
add_action( 'template_redirect', 'bbp_edit_user_handler',      1     );

// Profile Page Messages
add_action( 'bbp_template_notices', 'bbp_notice_edit_user_success'           );
add_action( 'bbp_template_notices', 'bbp_notice_edit_user_is_super_admin', 2 );

// New/Edit Reply
add_action( 'template_redirect', 'bbp_new_reply_handler'             );
add_action( 'template_redirect', 'bbp_edit_reply_handler',     1     );
add_action( 'bbp_new_reply',     'bbp_new_reply_update_reply', 10, 6 );
add_action( 'bbp_edit_reply',    'bbp_new_reply_update_reply', 10, 6 );

// New/Edit Topic
add_action( 'template_redirect', 'bbp_new_topic_handler'             );
add_action( 'template_redirect', 'bbp_edit_topic_handler',     1     );
add_action( 'bbp_new_topic',     'bbp_new_topic_update_topic', 10, 5 );
add_action( 'bbp_edit_topic',    'bbp_new_topic_update_topic', 10, 5 );

// Topic/Reply Actions
add_action( 'template_redirect', 'bbp_toggle_topic_handler', 1 );
add_action( 'template_redirect', 'bbp_toggle_reply_handler', 1 );

// Favorites
add_action( 'template_redirect', 'bbp_favorites_handler',              1 );
add_action( 'trash_post',        'bbp_remove_topic_from_all_favorites'   );
add_action( 'delete_post',       'bbp_remove_topic_from_all_favorites'   );

// Subscriptions
add_action( 'template_redirect', 'bbp_subscriptions_handler',              1    );
add_action( 'trash_post',        'bbp_remove_topic_from_all_subscriptions'      );
add_action( 'delete_post',       'bbp_remove_topic_from_all_subscriptions'      );
add_action( 'bbp_new_reply',     'bbp_notify_subscribers',                 1, 1 );

// Update forum topic counts
add_action( 'trashed_post',        'bbp_update_forum_topic_count' );
add_action( 'untrashed_post',      'bbp_update_forum_topic_count' );
add_action( 'deleted_post',        'bbp_update_forum_topic_count' );
add_action( 'bbp_new_topic',       'bbp_update_forum_topic_count' );
add_action( 'bbp_edit_topic',      'bbp_update_forum_topic_count' );
add_action( 'bbp_move_topic',      'bbp_update_forum_topic_count' );
add_action( 'bbp_spammed_topic',   'bbp_update_forum_topic_count' );
add_action( 'bbp_unspammed_topic', 'bbp_update_forum_topic_count' );

// Update forum reply counts
add_action( 'trashed_post',        'bbp_update_forum_reply_count' );
add_action( 'untrashed_post',      'bbp_update_forum_reply_count' );
add_action( 'deleted_post',        'bbp_update_forum_reply_count' );
add_action( 'bbp_new_reply',       'bbp_update_forum_reply_count' );
add_action( 'bbp_edit_relpy',      'bbp_update_forum_reply_count' );
add_action( 'bbp_move_topic',      'bbp_update_forum_reply_count' );
add_action( 'bbp_spammed_reply',   'bbp_update_forum_reply_count' );
add_action( 'bbp_unspammed_reply', 'bbp_update_forum_reply_count' );

// Update forum voice counts
add_action( 'trashed_post',        'bbp_update_forum_voice_count' );
add_action( 'untrashed_post',      'bbp_update_forum_voice_count' );
add_action( 'deleted_post',        'bbp_update_forum_voice_count' );
add_action( 'bbp_new_topic',       'bbp_update_forum_voice_count' );
add_action( 'bbp_new_reply',       'bbp_update_forum_voice_count' );
add_action( 'bbp_edit_topic',      'bbp_update_forum_voice_count' );
add_action( 'bbp_move_topic',      'bbp_update_forum_voice_count' );
add_action( 'bbp_edit_reply',      'bbp_update_forum_voice_count' );
add_action( 'bbp_spammed_topic',   'bbp_update_forum_voice_count' );
add_action( 'bbp_unspammed_topic', 'bbp_update_forum_voice_count' );
add_action( 'bbp_spammed_reply',   'bbp_update_forum_voice_count' );
add_action( 'bbp_unspammed_reply', 'bbp_update_forum_voice_count' );

// Update topic reply counts
add_action( 'bbp_new_reply',       'bbp_update_topic_reply_count' );
add_action( 'bbp_edit_reply',      'bbp_update_topic_reply_count' );
add_action( 'trashed_post',        'bbp_update_topic_reply_count' );
add_action( 'untrashed_post',      'bbp_update_topic_reply_count' );
add_action( 'deleted_post',        'bbp_update_topic_reply_count' );
add_action( 'bbp_spammed_reply',   'bbp_update_topic_reply_count' );
add_action( 'bbp_unspammed_reply', 'bbp_update_topic_reply_count' );

// Update topic hidden reply counts
add_action( 'trashed_post',        'bbp_update_topic_hidden_reply_count' );
add_action( 'untrashed_post',      'bbp_update_topic_hidden_reply_count' );
add_action( 'deleted_post',        'bbp_update_topic_hidden_reply_count' );
add_action( 'bbp_spammed_reply',   'bbp_update_topic_hidden_reply_count' );
add_action( 'bbp_unspammed_reply', 'bbp_update_topic_hidden_reply_count' );

// Update topic voice counts
add_action( 'bbp_new_reply',       'bbp_update_topic_voice_count' );
add_action( 'bbp_edit_reply',      'bbp_update_topic_voice_count' );
add_action( 'trashed_post',        'bbp_update_topic_voice_count' );
add_action( 'untrashed_post',      'bbp_update_topic_voice_count' );
add_action( 'deleted_post',        'bbp_update_topic_voice_count' );
add_action( 'bbp_spammed_reply',   'bbp_update_topic_voice_count' );
add_action( 'bbp_unspammed_reply', 'bbp_update_topic_voice_count' );

// Custom Template - should be called at the end
add_action( 'template_redirect', 'bbp_custom_template', 999 );

/** FILTERS *******************************************************************/

// Add number format filter to functions requiring numeric output
add_filter( 'bbp_get_forum_topic_count',       'bbp_number_format' );
add_filter( 'bbp_get_forum_topic_reply_count', 'bbp_number_format' );

// Canonical
add_filter( 'redirect_canonical', 'bbp_redirect_canonical' );

// Fix post author id for anonymous posts (set it back to 0) when the post status is changed
add_filter( 'wp_insert_post_data', 'bbp_fix_post_author', 30, 2 );

/**
 * Add filters to anonymous post author data
 *
 * This is used to clean-up any anonymous user data that is submitted via the
 * new topic and new reply forms.
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

?>
