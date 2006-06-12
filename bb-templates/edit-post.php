<?php bb_get_header(); ?>
<h2><a href="<?php option('uri'); ?>"><?php option('name'); ?></a> &raquo; <?php _e('Edit Post'); ?></h2>

<?php edit_form( $bb_post->post_content, $topic_title ); ?>

<?php bb_get_footer(); ?>
