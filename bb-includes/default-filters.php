<?php

add_filter('bb_pre_forum_name', 'trim');
add_filter('bb_pre_forum_name', 'strip_tags');
add_filter('bb_pre_forum_name', 'wp_specialchars');
add_filter('bb_pre_forum_desc', 'trim');
add_filter('bb_pre_forum_desc', 'bb_filter_kses');

add_filter('get_forum_topics', 'bb_number_format_i18n');
add_filter('get_forum_posts', 'bb_number_format_i18n');

add_filter('topic_time', 'bb_offset_time', 10, 2);
add_filter('topic_start_time', 'bb_offset_time', 10, 2);
add_filter('bb_post_time', 'bb_offset_time', 10, 2);
add_filter('get_topic_link', 'bb_add_replies_to_topic_link', 10, 2);

add_filter('pre_topic_title', 'wp_specialchars');
add_filter('get_forum_name', 'wp_specialchars');
add_filter('bb_topic_labels', 'bb_closed_label', 10);
add_filter('bb_topic_labels', 'bb_sticky_label', 20);
add_filter('topic_title', 'wp_specialchars');

add_filter('pre_post', 'trim');
add_filter('pre_post', 'bb_encode_bad');
add_filter('pre_post', 'bb_code_trick');
add_filter('pre_post', 'force_balance_tags');
add_filter('pre_post', 'stripslashes', 40); // KSES doesn't like escaped atributes
add_filter('pre_post', 'bb_filter_kses', 50);
add_filter('pre_post', 'addslashes', 55);
add_filter('pre_post', 'bb_autop', 60);

add_filter('post_text', 'make_clickable');

add_filter('total_posts', 'bb_number_format_i18n');
add_filter('total_users', 'bb_number_format_i18n');

add_filter('edit_text', 'bb_code_trick_reverse');
add_filter('edit_text', 'htmlspecialchars');
add_filter('edit_text', 'trim', 15);

add_filter('pre_sanitize_with_dashes', 'bb_pre_sanitize_with_dashes_utf8', 10, 3 );

add_filter('get_user_link', 'bb_fix_link');

add_action('bb_head', 'bb_print_scripts');
add_action('bb_admin_print_scripts', 'bb_print_scripts');

add_action('bb_user_has_no_caps', 'bb_give_user_default_role');

add_filter('sanitize_profile_info', 'wp_specialchars');
add_filter('sanitize_profile_admin', 'wp_specialchars');

add_filter( 'get_recent_user_replies_fields', 'get_recent_user_replies_fields' );
add_filter( 'get_recent_user_replies_group_by', 'get_recent_user_replies_group_by' );

if ( !bb_get_option( 'mod_rewrite' ) ) {
	add_filter( 'bb_stylesheet_uri', 'attribute_escape', 1, 9999 );
	add_filter( 'forum_link', 'attribute_escape', 1, 9999 );
	add_filter( 'forum_rss_link', 'attribute_escape', 1, 9999 );
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

add_filter('sort_tag_heat_map', 'bb_sort_tag_heat_map');

if ( is_bb_feed() ) {
	add_filter( 'bb_title_rss', 'ent2ncr' );
	add_filter( 'topic_title', 'ent2ncr' );
	add_filter( 'post_link', 'wp_specialchars' );
	add_filter( 'post_text', 'htmlspecialchars' ); // encode_bad should not be overruled by wp_specialchars
	add_filter( 'post_text', 'ent2ncr' );
}

bb_register_view( 'no-replies', __('Topics with no replies'), array( 'post_count' => 1 ) );
bb_register_view( 'untagged'  , __('Topics with no tags')   , array( 'tag_count'  => 0 ) );

if ( bb_get_option( 'wp_table_prefix' ) ) {
	add_action( 'bb_user_login', 'bb_apply_wp_role_map_to_user' );
}

?>
