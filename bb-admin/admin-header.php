<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.1//EN" "http://www.w3.org/TR/xhtml11/DTD/xhtml11.dtd">
<html xmlns="http://www.w3.org/1999/xhtml"<?php bb_language_attributes( '1.1' ); ?>>
<head>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
	<title><?php bb_admin_title() ?></title>
	<link rel="stylesheet" href="<?php bb_uri('bb-admin/style.css', null, BB_URI_CONTEXT_LINK_STYLESHEET_HREF + BB_URI_CONTEXT_BB_ADMIN); ?>" type="text/css" />
<?php if ( 'rtl' == bb_get_option( 'text_direction' ) ) : ?>
	<link rel="stylesheet" href="<?php bb_uri('bb-admin/style-rtl.css', null, BB_URI_CONTEXT_LINK_STYLESHEET_HREF + BB_URI_CONTEXT_BB_ADMIN); ?>" type="text/css" />
<?php endif; do_action('bb_admin_print_scripts'); do_action( 'bb_admin_head' ); ?>
</head>

<body class="bbAdmin">
	<div id="bbWrap">
		<div id="bbContent">
			<div id="bbHead">
				<h1>
					<?php bb_option('name'); ?>
				</h1>
				<div id="bbVisitSite">
					<a href="<?php bb_uri(); ?>"><span><?php _e('Visit Site'); ?></span></a>
				</div>
			</div>
			<div id="bbUserMenu">
				<p>
					<?php printf( __('Howdy, %1$s!'), bb_get_profile_link( array( 'text' => bb_get_current_user_info( 'name' ) ) ) );?>
					| <?php bb_logout_link( array( 'redirect' => bb_get_uri( null, null, BB_URI_CONTEXT_HEADER ) ) ); ?>
					| <a href="http://bbpress.org/forums/"><?php _e('Support forums'); ?></a>
				</p>
			</div>

<?php bb_admin_menu(); ?>

<?php do_action( 'bb_admin_notices' ); ?>

			<div id="bbBody">
