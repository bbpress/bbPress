<?php get_header(); ?>

<h3><a href="<?php option('uri'); ?>"><?php option('name'); ?></a> &raquo; Login</h3>

<h2>Password Reset</h2>

<?php if ( $reset ) : ?>
<p>Your password has been reset and a new one has been mailed to you.</p>
<?php else : ?>
<p>An email has been sent to the address we have on file for you. If you don't get anything with a few minutes, or your email has changed, you may want to get in touch with the webmaster or forum administrator here.</p>
<?php endif; ?>

<?php get_footer(); ?>