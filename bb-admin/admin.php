<?php
require_once('../bb-load.php');

bb_auth();

if ( bb_get_option( 'bb_db_version' ) != bb_get_option_from_db( 'bb_db_version' ) )
	bb_die( sprintf(__("Your database is out-of-date.  Please <a href='%s'>upgrade</a>."), bb_get_option( 'uri' ) . 'bb-admin/upgrade.php') );

require('admin-functions.php');

nocache_headers();

if ( isset($_GET['plugin']) )
	$bb_admin_page = $_GET['plugin'];
else	$bb_admin_page = bb_find_filename($_SERVER['PHP_SELF']);

bb_admin_menu_generator();
bb_get_current_admin_menu();
?>
