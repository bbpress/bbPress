<?php get_header(); ?>
<h2><a href="<?php option('uri'); ?>"><?php option('name'); ?></a> &raquo; Edit Post</h2>

<?php edit_form( $post->post_content, $topic_title); ?>

<?php get_footer(); ?>