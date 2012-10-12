<?php

/**
 * bbPress Filters
 *
 * @package bbPress
 * @subpackage Core
 *
 * This file contains the filters that are used through-out bbPress. They are
 * consolidated here to make searching for them easier, and to help developers
 * understand at a glance the order in which things occur.
 *
 * There are a few common places that additional filters can currently be found
 *
 *  - bbPress: In {@link bbPress::setup_actions()} in bbpress.php
 *  - Component: In {@link BBP_Component::setup_actions()} in
 *                bbp-includes/bbp-classes.php
 *  - Admin: More in {@link BBP_Admin::setup_actions()} in
 *            bbp-admin/bbp-admin.php
 *
 * @see bbp-core-actions.php
 */

// Exit if accessed directly
if ( !defined( 'ABSPATH' ) ) exit;

/**
 * Attach bbPress to WordPress
 *
 * bbPress uses its own internal actions to help aid in third-party plugin
 * development, and to limit the amount of potential future code changes when
 * updates to WordPress core occur.
 *
 * These actions exist to create the concept of 'plugin dependencies'. They
 * provide a safe way for plugins to execute code *only* when bbPress is
 * installed and activated, without needing to do complicated guesswork.
 *
 * For more information on how this works, see the 'Plugin Dependency' section
 * near the bottom of this file.
 *
 *           v--WordPress Actions       v--bbPress Sub-actions
 */
add_filter( 'request',                 'bbp_request',            10    );
add_filter( 'template_include',        'bbp_template_include',   10    );
add_filter( 'wp_title',                'bbp_title',              10, 3 );
add_filter( 'body_class',              'bbp_body_class',         10, 2 );
add_filter( 'map_meta_cap',            'bbp_map_meta_caps',      10, 4 );
add_filter( 'allowed_themes',          'bbp_allowed_themes',     10    );
add_filter( 'redirect_canonical',      'bbp_redirect_canonical', 10    );
add_filter( 'login_redirect',          'bbp_redirect_login',     2,  3 );
add_filter( 'logout_url',              'bbp_logout_url',         2,  2 );
add_filter( 'plugin_locale',           'bbp_plugin_locale',      10, 2 );

// Fix post author id for anonymous posts (set it back to 0) when the post status is changed
add_filter( 'wp_insert_post_data', 'bbp_fix_post_author', 30, 2 );

// Force comments_status on bbPress post types
add_filter( 'comments_open', 'bbp_force_comment_status' );

// Add post_parent__in to posts_where
add_filter( 'posts_where', 'bbp_query_post_parent__in', 10, 2 );

/**
 * Feeds
 *
 * bbPress comes with a number of custom RSS2 feeds that get handled outside
 * the normal scope of feeds that WordPress would normally serve. To do this,
 * we filter every page request, listen for a feed request, and trap it.
 */
add_filter( 'bbp_request', 'bbp_request_feed_trap' );

/**
 * Template Compatibility
 *
 * If you want to completely bypass this and manage your own custom bbPress
 * template hierarchy, start here by removing this filter, then look at how
 * bbp_template_include() works and do something similar. :)
 */
add_filter( 'bbp_template_include', 'bbp_template_include_theme_supports', 2, 1 );
add_filter( 'bbp_template_include', 'bbp_template_include_theme_compat',   4, 2 );

// Links
add_filter( 'paginate_links',            'bbp_add_view_all' );
add_filter( 'bbp_get_topic_permalink',   'bbp_add_view_all' );
add_filter( 'bbp_get_reply_permalink',   'bbp_add_view_all' );
add_filter( 'bbp_get_forum_permalink',   'bbp_add_view_all' );

// wp_filter_kses on new/edit topic/reply title
add_filter( 'bbp_new_reply_pre_title',     'wp_filter_kses' );
add_filter( 'bbp_new_topic_pre_title',     'wp_filter_kses' );
add_filter( 'bbp_edit_reply_pre_title',    'wp_filter_kses' );
add_filter( 'bbp_edit_topic_pre_title',    'wp_filter_kses' );

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

// Add number format filter to functions requiring numeric output
add_filter( 'bbp_get_user_topic_count',     'bbp_number_format', 10 );
add_filter( 'bbp_get_user_reply_count',     'bbp_number_format', 10 );
add_filter( 'bbp_get_user_post_count',      'bbp_number_format', 10 );
add_filter( 'bbp_get_forum_subforum_count', 'bbp_number_format', 10 );
add_filter( 'bbp_get_forum_topic_count',    'bbp_number_format', 10 );
add_filter( 'bbp_get_forum_reply_count',    'bbp_number_format', 10 );
add_filter( 'bbp_get_forum_post_count',     'bbp_number_format', 10 );
add_filter( 'bbp_get_topic_voice_count',    'bbp_number_format', 10 );
add_filter( 'bbp_get_topic_reply_count',    'bbp_number_format', 10 );
add_filter( 'bbp_get_topic_post_count',     'bbp_number_format', 10 );

// Run wp_kses_data on topic/reply content in admin section
if ( is_admin() ) {
	add_filter( 'bbp_get_reply_content', 'wp_kses_data' );
	add_filter( 'bbp_get_topic_content', 'wp_kses_data' );

// Revisions (only when not in admin)
} else {
	add_filter( 'bbp_get_reply_content', 'bbp_reply_content_append_revisions',  1,  2 );
	add_filter( 'bbp_get_topic_content', 'bbp_topic_content_append_revisions',  1,  2 );
}

// Suppress private forum details
add_filter( 'bbp_get_forum_topic_count',    'bbp_suppress_private_forum_meta',  10, 2 );
add_filter( 'bbp_get_forum_reply_count',    'bbp_suppress_private_forum_meta',  10, 2 );
add_filter( 'bbp_get_forum_post_count',     'bbp_suppress_private_forum_meta',  10, 2 );
add_filter( 'bbp_get_forum_freshness_link', 'bbp_suppress_private_forum_meta',  10, 2 );
add_filter( 'bbp_get_author_link',          'bbp_suppress_private_author_link', 10, 2 );
add_filter( 'bbp_get_topic_author_link',    'bbp_suppress_private_author_link', 10, 2 );
add_filter( 'bbp_get_reply_author_link',    'bbp_suppress_private_author_link', 10, 2 );

// Filter bbPress template locations
add_filter( 'bbp_get_template_part',         'bbp_add_template_locations' );
add_filter( 'bbp_get_profile_template',      'bbp_add_template_locations' );
add_filter( 'bbp_get_profileedit_template',  'bbp_add_template_locations' );
add_filter( 'bbp_get_singleview_template',   'bbp_add_template_locations' );
add_filter( 'bbp_get_forumedit_template',    'bbp_add_template_locations' );
add_filter( 'bbp_get_topicedit_template',    'bbp_add_template_locations' );
add_filter( 'bbp_get_topicsplit_template',   'bbp_add_template_locations' );
add_filter( 'bbp_get_topicmerge_template',   'bbp_add_template_locations' );
add_filter( 'bbp_get_topictag_template',     'bbp_add_template_locations' );
add_filter( 'bbp_get_topictagedit_template', 'bbp_add_template_locations' );

/**
 * Add filters to anonymous post author data
 */
// Post author name
add_filter( 'bbp_pre_anonymous_post_author_name',    'trim',                10 );
add_filter( 'bbp_pre_anonymous_post_author_name',    'sanitize_text_field', 10 );
add_filter( 'bbp_pre_anonymous_post_author_name',    'wp_filter_kses',      10 );
add_filter( 'bbp_pre_anonymous_post_author_name',    '_wp_specialchars',    30 );

// Save email
add_filter( 'bbp_pre_anonymous_post_author_email',   'trim',                10 );
add_filter( 'bbp_pre_anonymous_post_author_email',   'sanitize_email',      10 );
add_filter( 'bbp_pre_anonymous_post_author_email',   'wp_filter_kses',      10 );

// Save URL
add_filter( 'bbp_pre_anonymous_post_author_website', 'trim',                10 );
add_filter( 'bbp_pre_anonymous_post_author_website', 'wp_strip_all_tags',   10 );
add_filter( 'bbp_pre_anonymous_post_author_website', 'esc_url_raw',         10 );
add_filter( 'bbp_pre_anonymous_post_author_website', 'wp_filter_kses',      10 );

// Queries
add_filter( 'posts_request', '_bbp_has_replies_where', 10, 2 );

// Capabilities
add_filter( 'bbp_map_meta_caps', 'bbp_map_primary_meta_caps',   10, 4 ); // Primary caps
add_filter( 'bbp_map_meta_caps', 'bbp_map_forum_meta_caps',     10, 4 ); // Forums
add_filter( 'bbp_map_meta_caps', 'bbp_map_topic_meta_caps',     10, 4 ); // Topics
add_filter( 'bbp_map_meta_caps', 'bbp_map_reply_meta_caps',     10, 4 ); // Replies
add_filter( 'bbp_map_meta_caps', 'bbp_map_topic_tag_meta_caps', 10, 4 ); // Topic tags

/** Deprecated ****************************************************************/

/**
 * The following filters are deprecated.
 *
 * These filters were most likely replaced by bbp_parse_args(), which includes
 * both passive and aggressive filters anywhere parse_args is used to compare
 * default arguments to passed arguments, without needing to litter the
 * codebase with _before_ and _after_ filters everywhere.
 */

/**
 * Deprecated locale filter
 *
 * @since bbPress (r4213)
 *
 * @param type $locale
 * @return type
 */
function _bbp_filter_locale( $locale = '' ) {
	return apply_filters( 'bbpress_locale', $locale );
}
add_filter( 'bbp_plugin_locale', '_bbp_filter_locale', 10, 1 );

/**
 * Deprecated forums query filter
 *
 * @since bbPress (r3961)
 * @param type $args
 * @return type
 */
function _bbp_has_forums_query( $args = array() ) {
	return apply_filters( 'bbp_has_forums_query', $args );
}
add_filter( 'bbp_after_has_forums_parse_args', '_bbp_has_forums_query' );

/**
 * Deprecated topics query filter
 *
 * @since bbPress (r3961)
 * @param type $args
 * @return type
 */
function _bbp_has_topics_query( $args = array() ) {
	return apply_filters( 'bbp_has_topics_query', $args );
}
add_filter( 'bbp_after_has_topics_parse_args', '_bbp_has_topics_query' );

/**
 * Deprecated replies query filter
 *
 * @since bbPress (r3961)
 * @param type $args
 * @return type
 */
function _bbp_has_replies_query( $args = array() ) {
	return apply_filters( 'bbp_has_replies_query', $args );
}
add_filter( 'bbp_after_has_replies_parse_args', '_bbp_has_replies_query' );
