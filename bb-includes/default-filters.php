<?php

// Strip, trim, kses, special chars for string saves
$filters = array( 'pre_term_name', 'bb_pre_forum_name', 'pre_topic_title' );
foreach ( $filters as $filter ) {
	add_filter( $filter, 'strip_tags' );
	add_filter( $filter, 'trim' );
	add_filter( $filter, 'bb_filter_kses' );
	add_filter( $filter, 'wp_specialchars', 30 );
}

// Kses only for textarea saves
$filters = array( 'pre_term_description', 'bb_pre_forum_desc' );
foreach ( $filters as $filter ) {
	add_filter( $filter, 'bb_filter_kses' );
}

// Slugs
add_filter( 'pre_term_slug', 'bb_pre_term_slug' );

// DB truncations
add_filter( 'pre_topic_title', 'bb_trim_for_db_150', 9999 );
add_filter( 'bb_pre_forum_name', 'bb_trim_for_db_150', 9999 );
add_filter( 'pre_term_name', 'bb_trim_for_db_55', 9999 );

// Format Strings for Display
$filters = array( 'get_forum_name', 'topic_title', 'bb_title', 'bb_option_name' );
foreach ( $filters as $filter ) {
	add_filter( $filter, 'wp_specialchars' );
}

// Numbers
$filters = array( 'get_forum_topics', 'get_forum_posts', 'total_posts', 'total_users' );
foreach ( $filters as $filter ) {
	add_filter( $filter, 'bb_number_format_i18n' );
}

// Offset Times
$filters = array( 'topic_time', 'topic_start_time', 'bb_post_time' );
foreach ( $filters as $filter ) {
	add_filter( $filter, 'bb_offset_time', 10, 2 );
}

add_filter('bb_topic_labels', 'bb_closed_label', 10);
add_filter('bb_topic_labels', 'bb_sticky_label', 20);

add_filter('pre_post', 'trim');
add_filter('pre_post', 'bb_encode_bad');
add_filter('pre_post', 'bb_code_trick');
add_filter('pre_post', 'force_balance_tags');
add_filter('pre_post', 'bb_filter_kses', 50);
add_filter('pre_post', 'bb_autop', 60);

add_filter('post_text', 'make_clickable');

add_filter('edit_text', 'bb_code_trick_reverse');
add_filter('edit_text', 'htmlspecialchars');
add_filter('edit_text', 'trim', 15);

add_filter('pre_sanitize_with_dashes', 'bb_pre_sanitize_with_dashes_utf8', 10, 3 );

add_filter('get_user_link', 'bb_fix_link');

add_filter('sanitize_profile_info', 'wp_specialchars');
add_filter('sanitize_profile_admin', 'wp_specialchars');

add_filter( 'get_recent_user_replies_fields', 'get_recent_user_replies_fields' );
add_filter( 'get_recent_user_replies_group_by', 'get_recent_user_replies_group_by' );

add_filter('sort_tag_heat_map', 'bb_sort_tag_heat_map');

// URLS

if ( !bb_get_option( 'mod_rewrite' ) ) {
	add_filter( 'bb_stylesheet_uri', 'attribute_escape', 1, 9999 );
	add_filter( 'forum_link', 'attribute_escape', 1, 9999 );
	add_filter( 'bb_forum_posts_rss_link', 'attribute_escape', 1, 9999 );
	add_filter( 'bb_forum_topics_rss_link', 'attribute_escape', 1, 9999 );
	add_filter( 'bb_tag_link', 'attribute_escape', 1, 9999 );
	add_filter( 'tag_rss_link', 'attribute_escape', 1, 9999 );
	add_filter( 'topic_link', 'attribute_escape', 1, 9999 );
	add_filter( 'topic_rss_link', 'attribute_escape', 1, 9999 );
	add_filter( 'post_link', 'attribute_escape', 1, 9999 );
	add_filter( 'post_anchor_link', 'attribute_escape', 1, 9999 );
	add_filter( 'user_profile_link', 'attribute_escape', 1, 9999 );
	add_filter( 'profile_tab_link', 'attribute_escape', 1, 9999 );
	add_filter( 'favorites_link', 'attribute_escape', 1, 9999 );
	add_filter( 'view_link', 'attribute_escape', 1, 9999 );
}

// Feed Stuff

if ( is_bb_feed() ) {
	add_filter( 'bb_title_rss', 'ent2ncr' );
	add_filter( 'topic_title', 'ent2ncr' );
	add_filter( 'post_link', 'wp_specialchars' );
	add_filter( 'post_text', 'htmlspecialchars' ); // encode_bad should not be overruled by wp_specialchars
	add_filter( 'post_text', 'ent2ncr' );
}

add_filter( 'init_roles', 'bb_init_roles' );
add_filter( 'map_meta_cap', 'bb_map_meta_cap', 1, 4 );

// Actions

add_action('bb_head', 'bb_template_scripts');
add_action('bb_head', 'wp_print_scripts');
add_action('bb_admin_print_scripts', 'wp_print_scripts');

add_action('bb_user_has_no_caps', 'bb_give_user_default_role');

function bb_register_default_views() {
	// no posts (besides the first one), older than 2 hours
	bb_register_view( 'no-replies', __('Topics with no replies'), array( 'post_count' => 1, 'started' => '<' . gmdate( 'YmdH', time() - 7200 ) ) );
	bb_register_view( 'untagged'  , __('Topics with no tags')   , array( 'tag_count'  => 0 ) );
}
add_action( 'bb_init', 'bb_register_default_views' );

if ( bb_get_option( 'wp_table_prefix' ) ) {
	add_action( 'bb_user_login', 'bb_apply_wp_role_map_to_user' );
}

// Defines

if ( !defined( 'BB_MAIL_EOL' ) )
	define( 'BB_MAIL_EOL', "\n" );

unset($filters);
