<?php
require('bb-config.php');

if ( $_SERVER['HTTP_REFERER'] == bb_get_option('uri') . 'bb-login.php' && isset( $_POST['re'] ) )
	$re = $_POST['re'];
elseif ( isset( $_SERVER['HTTP_REFERER'] ) )
	$re = $_SERVER['HTTP_REFERER'];
else
	$re = bb_get_option('uri');

// Never cache
header('Expires: Wed, 11 Jan 1984 05:00:00 GMT');
header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
header('Cache-Control: no-cache, must-revalidate');
header('Pragma: no-cache');

if ( isset( $_REQUEST['logout'] ) ) {
	bb_cookie( $bb->passcookie , $user->user_password, time() - 31536000 );
	header('Location: ' . $re);
	bb_do_action('bb_user_logout', '');
	exit;
}

if ( $user = bb_check_login( $_POST['username'], $_POST['password'] ) ) {
	bb_cookie( $bb->usercookie, $user->username, time() + 6048000 );
	bb_cookie( $bb->passcookie, md5( $user->user_password ) );
	bb_do_action('bb_user_login', '');
} else {
	$user_exists = bb_user_exists( $_POST['username'] );
	$username    = user_sanitize ( $_POST['username'] );
	$redirect_to = bb_specialchars( $re, 1 );
	include('bb-templates/login-failed.php');
	exit;
}

header('Location: ' . $re);
?>