<?php get_header(); ?>

<h3><a href="<?php option('uri'); ?>"><?php option('name'); ?></a> &raquo; Register</h3>

<h2>Great!</h2>

<p>Your registration as <strong><?php echo $username; ?></strong> was successful. Within a few minutes you should receive an email with your password.</p>

<?php get_footer(); ?>