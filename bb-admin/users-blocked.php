<?php
require_once('admin.php');

// Query the users
$bb_blocked_users = new BB_Users_By_Role( array('inactive', 'blocked'), @$_GET['userspage'] );

$bb_admin_body_class = ' bb-admin-users';

bb_get_admin_header();
?>

<div class="wrap">

<?php
$bb_blocked_users->title = __('These users have been blocked by the forum administrators');
$bb_blocked_users->display( false, bb_current_user_can( 'edit_users' ) );
?>

</div>

<?php
bb_get_admin_footer();
?>
