<?php

bb_add_filter('forum_topics', 'number_format');
bb_add_filter('forum_posts', 'number_format');

bb_add_filter('topic_time', 'strtotime');
bb_add_filter('topic_time', 'bb_since');

bb_add_filter('pre_topic_title', 'bb_specialchars');
bb_add_filter('get_forum_name', 'bb_specialchars');
bb_add_filter('topic_title', 'topic_noreply');

bb_add_filter('pre_post', 'trim');
bb_add_filter('pre_post', 'encode_bad');
bb_add_filter('pre_post', 'stripslashes', 40); // KSES doesn't like escaped atributes
bb_add_filter('pre_post', 'bb_filter_kses', 50);
bb_add_filter('pre_post', 'addslashes', 55);
bb_add_filter('pre_post', 'bb_autop', 60);

bb_add_filter('post_text', 'bb_make_clickable');

bb_add_filter('total_posts', 'number_format');
bb_add_filter('total_users', 'number_format');

bb_add_filter('edit_text', 'code_trick_reverse');
bb_add_filter('edit_text', 'htmlspecialchars');

bb_add_filter('get_user_link', 'bb_fix_link');

?>