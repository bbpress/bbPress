<?php bb_get_header(); ?>

<h3><a href="<?php option('uri'); ?>"><?php option('name'); ?></a> &raquo; <?php _e('Login'); ?></h3>

<h2><?php _e('Password Reset'); ?></h2>

<?php if ( $reset ) : ?>
<p><?php _e('Your password has been reset and a new one has been mailed to you.'); ?></p>
<?php else : ?>
<p><?php _e('An email has been sent to the address we have on file for you. If you don&#8217;t get anything with a few minutes, or your email has changed, you may want to get in touch with the webmaster or forum administrator here.'); ?></p>
<?php endif; ?>

<?php bb_get_footer(); ?>
