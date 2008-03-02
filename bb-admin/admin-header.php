<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.1//EN" "http://www.w3.org/TR/xhtml11/DTD/xhtml11.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" <?php bb_language_attributes( '1.1' ); ?>>
<head>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
	<title><?php bb_admin_title() ?></title>
	<link rel="stylesheet" href="<?php bb_option('uri'); ?>bb-admin/style.css" type="text/css" />
<?php if ( 'rtl' == bb_get_option( 'text_direction' ) ) : ?>
	<link rel="stylesheet" href="<?php bb_option('uri'); ?>bb-admin/style-rtl.css" type="text/css" />
<?php endif; do_action('bb_admin_print_scripts'); do_action( 'bb_admin_head' ); ?>
</head>

<body>

<div id="top">
	<h1>bbPress &#8212; <a href="<?php bb_option('uri'); ?>"><?php bb_option('name'); ?></a></h1>
	<p class="login">
		<?php printf(__('Welcome, %1$s!'), bb_get_current_user_info( 'name' ));?>
		<?php bb_profile_link(); ?> | <?php bb_logout_link(); ?>
	</p>
</div>
<?php bb_admin_menu(); ?>
<?php do_action( 'bb_admin_notices' ); ?>
<div class="wrap">

