<?php
require_once('../config.php');

if ( !bb_current_user_can('moderate') ) {
	header('Location: ' . bb_get_option('uri'));
	exit; //Simple protection.
}

require('admin-functions.php');

if ( isset($_GET['plugin']) )
	$bb_admin_page = $_GET['plugin'];
else	$bb_admin_page = bb_find_filename($_SERVER['PHP_SELF']);

bb_admin_menu_generator();
bb_get_current_admin_menu();

?>
