<?php get_header(); ?>
<?php profile_menu(); ?>

<h3><a href="<?php option('uri'); ?>"><?php option('name'); ?></a> &raquo; <?php echo $profile_page_title; ?></h3>
<h2><?php echo $user->user_login; ?></h2>

<?php call_user_func('profile_page_' . $tab); ?>

<?php get_footer(); ?>
