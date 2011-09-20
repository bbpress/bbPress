<?php
require_once('admin.php');

// Query the users
$bb_user_search = new BB_User_Search($_GET['usersearch'], $_GET['userspage']);

bb_get_admin_header();

$bb_user_search->display( true, bb_current_user_can( 'edit_users' ) );

bb_get_admin_footer();
?>
