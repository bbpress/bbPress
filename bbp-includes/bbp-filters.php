<?php

// Add number format filter to functions requiring numeric output
add_filter( 'bbp_get_forum_topic_count',       'bbp_number_format' );
add_filter( 'bbp_get_forum_topic_reply_count', 'bbp_number_format' );

// Add hooks to insert and delete post functions to update our voice counts
add_action( 'wp_insert_post', 'bbp_update_topic_voice_count' );
add_action( 'wp_delete_post', 'bbp_update_topic_voice_count' );

?>