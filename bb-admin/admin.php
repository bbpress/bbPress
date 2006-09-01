<?php
require_once('../bb-load.php');

bb_auth();

require('admin-functions.php');

nocache_headers();

if ( isset($_GET['plugin']) )
	$bb_admin_page = $_GET['plugin'];
else	$bb_admin_page = bb_find_filename($_SERVER['PHP_SELF']);

bb_admin_menu_generator();
bb_get_current_admin_menu();
?>
