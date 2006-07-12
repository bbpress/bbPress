<?php bb_get_header(); ?>
<?php profile_menu(); ?>

<h3><a href="<?php option('uri'); ?>"><?php option('name'); ?></a> &raquo; <?php echo $profile_page_title; ?></h3>
<h2><?php echo $user->user_login; ?></h2>

error_log("SELF: $self", 0);
<?php $self(); ?>

<?php bb_get_footer(); ?>
