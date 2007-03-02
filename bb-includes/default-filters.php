<?php

add_filter('get_forum_topics', 'number_format');
add_filter('get_forum_posts', 'number_format');

add_filter('topic_time', 'bb_offset_time', 10, 2);
add_filter('topic_start_time', 'bb_offset_time', 10, 2);
add_filter('bb_post_time', 'bb_offset_time', 10, 2);

add_filter('pre_topic_title', 'wp_specialchars');
add_filter('get_forum_name', 'wp_specialchars');
add_filter('topic_title', 'closed_title', 30);
add_filter('topic_title', 'wp_specialchars');

add_filter('pre_post', 'trim');
add_filter('pre_post', 'encode_bad');
add_filter('pre_post', 'balanceTags');
add_filter('pre_post', 'stripslashes', 40); // KSES doesn't like escaped atributes
add_filter('pre_post', 'bb_filter_kses', 50);
add_filter('pre_post', 'addslashes', 55);
add_filter('pre_post', 'bb_autop', 60);

add_filter('post_text', 'make_clickable');

add_filter('total_posts', 'number_format');
add_filter('total_users', 'number_format');

add_filter('edit_text', 'code_trick_reverse');
add_filter('edit_text', 'htmlspecialchars');
add_filter('edit_text', 'trim', 15);

add_filter('pre_create_tag', 'bb_pre_create_tag_utf8' );

add_filter('pre_sanitize_with_dashes', 'bb_pre_sanitize_with_dashes_utf8' );

add_filter('get_user_link', 'bb_fix_link');

add_action('bb_head', 'bb_print_scripts');
add_action('bb_admin_print_scripts', 'bb_print_scripts');

add_action('bb_user_has_no_caps', 'bb_give_user_default_role');

add_filter('sanitize_profile_info', 'wp_specialchars');
add_filter('sanitize_profile_admin', 'wp_specialchars');

if ( !bb_get_option( 'mod_rewrite' ) ) {
	add_filter( 'bb_stylesheet_uri', 'attribute_escape', 1, 9999 );
	add_filter( 'forum_link', 'attribute_escape', 1, 9999 );
	add_filter( 'forum_rss_link', 'attribute_escape', 1, 9999 );
	add_filter( 'tag_link', 'attribute_escape', 1, 9999 );
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

add_filter('sort_tag_heat_map', 'bb_sort_tag_heat_map')

?>
