<?php

// Add number format filter to functions requiring numeric output
add_filter( 'bbp_get_forum_topic_count',       'bbp_number_format' );
add_filter( 'bbp_get_forum_topic_reply_count', 'bbp_number_format' );

// Update forum topic counts
add_action( 'bbp_new_topic',       'bbp_update_forum_topic_count' );
add_action( 'trashed_post',        'bbp_update_forum_topic_count' );
add_action( 'untrashed_post',      'bbp_update_forum_topic_count' );
add_action( 'deleted_post',        'bbp_update_forum_topic_count' );
add_action( 'bbp_spammed_topic',   'bbp_update_forum_topic_count' );
add_action( 'bbp_unspammed_topic', 'bbp_update_forum_topic_count' );

// Update forum reply counts
add_action( 'bbp_new_reply',       'bbp_update_forum_reply_count' );
add_action( 'trashed_post',        'bbp_update_forum_reply_count' );
add_action( 'untrashed_post',      'bbp_update_forum_reply_count' );
add_action( 'deleted_post',        'bbp_update_forum_reply_count' );
add_action( 'bbp_spammed_reply',   'bbp_update_forum_reply_count' );
add_action( 'bbp_unspammed_reply', 'bbp_update_forum_reply_count' );

// Update forum voice counts
add_action( 'bbp_new_topic',       'bbp_update_forum_voice_count' );
add_action( 'bbp_new_reply',       'bbp_update_forum_voice_count' );
add_action( 'trashed_post',        'bbp_update_forum_voice_count' );
add_action( 'untrashed_post',      'bbp_update_forum_voice_count' );
add_action( 'deleted_post',        'bbp_update_forum_voice_count' );
add_action( 'bbp_spammed_topic',   'bbp_update_forum_voice_count' );
add_action( 'bbp_unspammed_topic', 'bbp_update_forum_voice_count' );
add_action( 'bbp_spammed_reply',   'bbp_update_forum_voice_count' );
add_action( 'bbp_unspammed_reply', 'bbp_update_forum_voice_count' );

// Update topic reply counts
add_action( 'bbp_new_reply',       'bbp_update_topic_reply_count' );
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
add_action( 'trashed_post',        'bbp_update_topic_voice_count' );
add_action( 'untrashed_post',      'bbp_update_topic_voice_count' );
add_action( 'deleted_post',        'bbp_update_topic_voice_count' );
add_action( 'bbp_spammed_reply',   'bbp_update_topic_voice_count' );
add_action( 'bbp_unspammed_reply', 'bbp_update_topic_voice_count' );

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
