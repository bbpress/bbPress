<?php
require_once('admin.php');

// Query the users
$bb_moderators = new BB_Users_By_Role( bb_trusted_roles(), $_GET['userspage'] );

bb_get_admin_header();

$bb_moderators->title = __('Forum Administrators');
$bb_moderators->display( false, bb_current_user_can( 'edit_users' ) );

bb_get_admin_footer();
?>
