<?php
define( 'BB_IS_ADMIN', true );

require_once('../bb-load.php');

bb_auth();

if ( bb_get_option( 'bb_db_version' ) > bb_get_option_from_db( 'bb_db_version' ) ) {
	bb_safe_redirect( 'upgrade.php' );
	die();
}

require('admin-functions.php');

if ( isset($_GET['plugin']) )
	$bb_admin_page = $_GET['plugin'];
else	$bb_admin_page = bb_find_filename($_SERVER['PHP_SELF']);

bb_admin_menu_generator();
bb_get_current_admin_menu();
?>
