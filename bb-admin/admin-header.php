<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.1//EN" "http://www.w3.org/TR/xhtml11/DTD/xhtml11.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" <?php language_attributes(); ?>>
<head>
	<title><?php bb_admin_title() ?></title>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
	<link rel="stylesheet" href="<?php bb_option('uri'); ?>bb-admin/style.css" type="text/css" />
<?php if ( 'rtl' == bb_get_option( 'text_direction' ) ) : ?>
	<link rel="stylesheet" href="<?php bb_option('uri'); ?>bb-admin/style-rtl.css" type="text/css" />
<?php endif; do_action('bb_admin_print_scripts'); do_action( 'bb_admin_head' ); ?>
</head>

<body>

<div id="top"><h1>bbPress &#8212; <a href="<?php bb_option('uri'); ?>"><?php bb_option('name'); ?></a></h1>
<?php login_form(); ?>
</div>
<?php bb_admin_menu(); ?>
<?php do_action( 'bb_admin_notices' ); ?>
<div class="wrap">

