<?php

add_filter('forum_topics', 'number_format');
add_filter('forum_posts', 'number_format');

add_filter('topic_time', 'strtotime');
add_filter('topic_time', 'since');

add_filter('topic_title', 'bb_specialchars');
add_filter('get_forum_name', 'bb_specialchars');

add_filter('post_text', 'code_trick');
add_filter('post_text', 'encode_bad');
add_filter('post_text', 'bb_autop');

?>