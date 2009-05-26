<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.1//EN" "http://www.w3.org/TR/xhtml11/DTD/xhtml11.dtd">
<html xmlns="http://www.w3.org/1999/xhtml"<?php bb_language_attributes( '1.1' ); ?>>
<head>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
	<title><?php bb_admin_title() ?></title>
	<link rel="stylesheet" href="<?php bb_uri('bb-admin/style.css', null, BB_URI_CONTEXT_LINK_STYLESHEET_HREF + BB_URI_CONTEXT_BB_ADMIN); ?>" type="text/css" />
<?php if ( 'rtl' == bb_get_option( 'text_direction' ) ) : ?>
	<link rel="stylesheet" href="<?php bb_uri('bb-admin/style-rtl.css', null, BB_URI_CONTEXT_LINK_STYLESHEET_HREF + BB_URI_CONTEXT_BB_ADMIN); ?>" type="text/css" />
<?php endif; do_action('bb_admin_print_scripts'); do_action( 'bb_admin_head' ); ?>
	<script type="text/javascript">
		//<![CDATA[
		var userSettings = {'url':'<?php echo $bb->cookie_path; ?>','uid':'<?php if ( ! isset($bb_current_user) ) $bb_current_user = bb_get_current_user(); echo $bb_current_user->ID; ?>','time':'<?php echo time(); ?>'};
		//]]>
	</script>
</head>

<?php
global $bb_admin_body_class;
if ( bb_get_user_setting('mfold') == 'f' ) {
	$bb_admin_body_class .= ' bb-menu-folded';
}
?>

<body class="bb-admin<?php echo $bb_admin_body_class ?>">
	<div id="bbWrap">
		<div id="bbContent">
			<div id="bbHead">
				<h1><a href="<?php bb_uri(); ?>"><span><?php bb_option('name'); ?></span> <em><?php _e('Visit Site'); ?></em></a></h1>
				<div id="bbUserInfo">
					<p>
						<?php printf( __('Howdy, %1$s'), bb_get_profile_link( array( 'text' => bb_get_current_user_info( 'name' ) ) ) );?>
						| <?php bb_logout_link( array( 'redirect' => bb_get_uri( null, null, BB_URI_CONTEXT_HEADER ) ) ); ?>
					</p>
				</div>
			</div>

			<div id="bbBody">

<?php bb_admin_menu(); ?>