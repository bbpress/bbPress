<?php

// Add number format filter to functions requiring numeric output
add_filter( 'bbp_get_forum_topic_count',       'bbp_number_format' );
add_filter( 'bbp_get_forum_topic_reply_count', 'bbp_number_format' );

// Add hooks to insert and delete post functions to update forum topic counts
add_action( 'wp_insert_post', 'bbp_update_forum_topic_count' );
add_action( 'wp_delete_post', 'bbp_update_forum_topic_count' );

// Add hooks to insert and delete post functions to update topic reply counts
add_action( 'wp_insert_post', 'bbp_update_forum_reply_count' );
add_action( 'wp_delete_post', 'bbp_update_forum_reply_count' );

// Add hooks to insert and delete post functions to update topic reply counts
add_action( 'wp_insert_post', 'bbp_update_forum_voice_count' );
add_action( 'wp_delete_post', 'bbp_update_forum_voice_count' );

// Add hooks to insert and delete post functions to update topic reply counts
add_action( 'wp_insert_post', 'bbp_update_topic_reply_count' );
add_action( 'wp_delete_post', 'bbp_update_topic_reply_count' );

// Add hooks to insert and delete post functions to update voice counts
add_action( 'wp_insert_post', 'bbp_update_topic_voice_count' );
add_action( 'wp_delete_post', 'bbp_update_topic_voice_count' );

?>