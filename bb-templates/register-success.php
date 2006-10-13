<?php bb_get_header(); ?>

<h3 class="bbcrumb"><a href="<?php option('uri'); ?>"><?php option('name'); ?></a> &raquo; <?php _e('Register'); ?></h3>

<h2 id="register"><?php _e('Great!'); ?></h2>

<p><?php printf(__('Your registration as <strong>%s</strong> was successful. Within a few minutes you should receive an email with your password.'), $user_login) ?></p>

<?php bb_get_footer(); ?>
