<?php

add_filter('forum_topics', 'number_format');
add_filter('forum_posts', 'number_format');

add_filter('topic_time', 'strtotime');
add_filter('topic_time', 'since');

add_filter('pre_topic_title', 'bb_specialchars');
add_filter('get_forum_name', 'bb_specialchars');

add_filter('pre_post', 'trim');
add_filter('pre_post', 'encode_bad');
add_filter('pre_post', 'stripslashes', 40); // KSES doesn't like escaped atributes
add_filter('pre_post', 'bb_filter_kses', 50);
add_filter('pre_post', 'addslashes', 55);
add_filter('pre_post', 'bb_autop', 60);

add_filter('total_posts', 'number_format');
add_filter('total_users', 'number_format');

add_filter('edit_text', 'code_trick_reverse');
add_filter('edit_text', 'htmlspecialchars');

?>