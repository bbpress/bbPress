<?php
define( 'BB_IS_ADMIN', true );

require_once('../bb-load.php');

bb_auth();

if ( bb_get_option( 'bb_db_version' ) > bb_get_option_from_db( 'bb_db_version' ) ) {
	bb_safe_redirect( 'upgrade.php' );
	die();
}

require('admin-functions.php');

$bb_admin_page = bb_find_filename( $_SERVER['PHP_SELF'] );

$_check_callback = false;
if ( $bb_admin_page == 'admin-base.php' ) {
	$bb_admin_page = (string) @$_GET['plugin'];
	$_check_callback = true;
}

bb_admin_menu_generator();
bb_get_current_admin_menu();

if ( $_check_callback ) {
	if ( empty( $bb_registered_plugin_callbacks ) || empty( $bb_admin_page ) || !in_array( $bb_admin_page, $bb_registered_plugin_callbacks ) ) {
		unset( $bb_admin_page );
	}
}
?>
