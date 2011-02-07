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
 *  - bbPress: In {@link bbPress::_setup_actions()} in bbpress.php
 *  - Component: In {@link BBP_Component::_setup_actions()} in
 *                bbp-includes/bbp-classes.php
 *  - Admin: More in {@link BBP_Admin::_setup_actions()} in
 *            bbp-admin/bbp-admin.php
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
add_action( 'bbp_init', 'bbp_register_views',         14  );
add_action( 'bbp_init', 'bbp_ready',                  999 );

// Admin
if ( is_admin() ) {
	add_action( 'bbp_init',   'bbp_admin'           );
	add_action( 'admin_menu', 'bbp_admin_separator' );
}

// Widgets
add_action( 'widgets_init', create_function( '', 'return register_widget("BBP_Login_Widget");'   ) );
add_action( 'widgets_init', create_function( '', 'return register_widget("BBP_Forums_Widget");'  ) );
add_action( 'widgets_init', create_function( '', 'return register_widget("BBP_Topics_Widget");'  ) );
add_action( 'widgets_init', create_function( '', 'return register_widget("BBP_Replies_Widget");' ) );

// Template - Head, foot, errors and notices
add_action( 'wp_head',              'bbp_head'                 );
add_filter( 'wp_title',             'bbp_title',         10, 3 );
add_action( 'wp_footer',            'bbp_footer'               );
add_action( 'bbp_template_notices', 'bbp_error_messages'       );
add_action( 'bbp_template_notices', 'bbp_topic_notices'        );

// Caps & Roles
add_filter( 'map_meta_cap',     'bbp_map_meta_caps', 10, 4 );
add_action( 'bbp_activation',   'bbp_add_roles',     1     );
add_action( 'bbp_activation',   'bbp_add_caps',      2     );
add_action( 'bbp_deactivation', 'bbp_remove_caps',   1     );
add_action( 'bbp_deactivation', 'bbp_remove_roles',  2     );

// Options & Settings
add_action( 'bbp_activation',   'bbp_add_options',   1     );

// Topic Tag Page
add_action( 'template_redirect', 'bbp_manage_topic_tag_handler', 1 );

// Profile Page
add_action( 'pre_get_posts',     'bbp_pre_get_posts',     1 );
add_action( 'template_redirect', 'bbp_edit_user_handler', 1 );

// Profile Page Messages
add_action( 'bbp_template_notices', 'bbp_notice_edit_user_success'           );
add_action( 'bbp_template_notices', 'bbp_notice_edit_user_is_super_admin', 2 );

// New/Edit Reply
add_action( 'template_redirect', 'bbp_new_reply_handler'         );
add_action( 'template_redirect', 'bbp_edit_reply_handler', 1     );
add_action( 'bbp_new_reply',     'bbp_reply_updater',      10, 6 );
add_action( 'bbp_edit_reply',    'bbp_reply_updater',      10, 6 );

// New/Edit Topic
add_action( 'template_redirect', 'bbp_new_topic_handler'         );
add_action( 'template_redirect', 'bbp_edit_topic_handler', 1     );
add_action( 'bbp_new_topic',     'bbp_topic_updater',      10, 5 );
add_action( 'bbp_edit_topic',    'bbp_topic_updater',      10, 5 );

// Split/Merge Topic
add_action( 'template_redirect',    'bbp_merge_topic_handler', 1    );
add_action( 'template_redirect',    'bbp_split_topic_handler', 1    );
add_action( 'bbp_merged_topic',     'bbp_merge_topic_count',   1, 3 );
add_action( 'bbp_post_split_topic', 'bbp_split_topic_count',   1, 3 );

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

// Sticky
add_action( 'trash_post',  'bbp_unstick_topic' );
add_action( 'delete_post', 'bbp_unstick_topic' );

// Update forum last active
//add_action( 'trashed_post',        'bbp_update_forum_last_active' );
//add_action( 'untrashed_post',      'bbp_update_forum_last_active' );
//add_action( 'deleted_post',        'bbp_update_forum_last_active' );

// Update forum topic counts
//add_action( 'trashed_post',        'bbp_update_forum_topic_count' );
//add_action( 'untrashed_post',      'bbp_update_forum_topic_count' );
//add_action( 'deleted_post',        'bbp_update_forum_topic_count' );
//add_action( 'bbp_new_topic',       'bbp_update_forum_topic_count' );
//add_action( 'bbp_edit_topic',      'bbp_update_forum_topic_count' );
//add_action( 'bbp_move_topic',      'bbp_update_forum_topic_count' );
//add_action( 'bbp_spammed_topic',   'bbp_update_forum_topic_count' );
//add_action( 'bbp_unspammed_topic', 'bbp_update_forum_topic_count' );

// Update forum reply counts
//add_action( 'trashed_post',        'bbp_update_forum_reply_count' );
//add_action( 'untrashed_post',      'bbp_update_forum_reply_count' );
//add_action( 'deleted_post',        'bbp_update_forum_reply_count' );
//add_action( 'bbp_new_reply',       'bbp_update_forum_reply_count' );
//add_action( 'bbp_edit_topic',      'bbp_update_forum_reply_count' );
//add_action( 'bbp_move_topic',      'bbp_update_forum_reply_count' );
//add_action( 'bbp_spammed_reply',   'bbp_update_forum_reply_count' );
//add_action( 'bbp_unspammed_reply', 'bbp_update_forum_reply_count' );

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
//add_action( 'bbp_new_reply',       'bbp_update_topic_reply_count' );
//add_action( 'bbp_edit_reply',      'bbp_update_topic_reply_count' );
//add_action( 'trashed_post',        'bbp_update_topic_reply_count' );
//add_action( 'untrashed_post',      'bbp_update_topic_reply_count' );
//add_action( 'deleted_post',        'bbp_update_topic_reply_count' );
//add_action( 'bbp_spammed_reply',   'bbp_update_topic_reply_count' );
//add_action( 'bbp_unspammed_reply', 'bbp_update_topic_reply_count' );

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

// Run wp_kses_data on topic/reply content in admin section
if ( is_admin() ) {
	add_filter( 'bbp_get_reply_content', 'wp_kses_data' );
	add_filter( 'bbp_get_topic_content', 'wp_kses_data' );
}

// Run filters on reply content
if ( !is_admin() )
	add_filter( 'bbp_get_reply_content', 'bbp_reply_content_append_revisions',  1, 2 );
add_filter( 'bbp_get_reply_content', 'capital_P_dangit'         );
add_filter( 'bbp_get_reply_content', 'wptexturize',        3    );
add_filter( 'bbp_get_reply_content', 'convert_chars',      5    );
add_filter( 'bbp_get_reply_content', 'make_clickable',     9    );
add_filter( 'bbp_get_reply_content', 'force_balance_tags', 25   );
add_filter( 'bbp_get_reply_content', 'convert_smilies',    20   );
add_filter( 'bbp_get_reply_content', 'wpautop',            30   );

// Run filters on topic content
if ( !is_admin() )
	add_filter( 'bbp_get_topic_content', 'bbp_topic_content_append_revisions',  1, 2 );
add_filter( 'bbp_get_topic_content', 'capital_P_dangit'         );
add_filter( 'bbp_get_topic_content', 'wptexturize',        3    );
add_filter( 'bbp_get_topic_content', 'convert_chars',      5    );
add_filter( 'bbp_get_topic_content', 'make_clickable',     9    );
add_filter( 'bbp_get_topic_content', 'force_balance_tags', 25   );
add_filter( 'bbp_get_topic_content', 'convert_smilies',    20   );
add_filter( 'bbp_get_topic_content', 'wpautop',            30   );

// Canonical
add_filter( 'redirect_canonical',    'bbp_redirect_canonical' );

// Login/Register/Lost Password
add_filter( 'login_redirect', 'bbp_redirect_login', 2, 3 );
add_filter( 'login_url',      'bbp_login_url',      2, 2 );
add_filter( 'logout_url',     'bbp_logout_url',     2, 2 );

// Fix post author id for anonymous posts (set it back to 0) when the post status is changed
add_filter( 'wp_insert_post_data', 'bbp_fix_post_author', 30, 2 );

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

?>
