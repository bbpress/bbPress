<?php
require('bb-config.php');

if ( isset($_SERVER['HTTP_REFERER']) )
	$re = $_SERVER['HTTP_REFERER'];
else
	$re = get_option('uri');

// Never cache
header('Expires: Wed, 11 Jan 1984 05:00:00 GMT');
header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
header('Cache-Control: no-cache, must-revalidate');
header('Pragma: no-cache');

if ( isset( $_REQUEST['logout'] ) ) {
	setcookie('bb_pass_'. BBHASH, $user->user_password, time() - 31536000, get_option('path') );
	header('Location: ' . $re);
	do_action('bb_user_logout', '');
	return;
}

if ( $user = bb_check_login( $_POST['username'], $_POST['password'] ) ) {
	setcookie('bb_user_'. BBHASH, $user->username, time() + 6048000, get_option('path') );
	setcookie('bb_pass_'. BBHASH, $user->user_password, time() + 604800, get_option('path') ); // One week
	do_action('bb_user_login', '');
}

header('Location: ' . $re);
?>