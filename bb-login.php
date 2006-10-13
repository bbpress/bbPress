<?php
require('./bb-load.php');

if ( 0 === strpos(@$_SERVER['HTTP_REFERER'], bb_get_option( 'uri' )) ) {
	$re = $_POST['re'] ? $_POST['re'] : $_GET['re'];
	if ( 0 !== strpos($re, bb_get_option( 'uri' )) )
		$re = $_SERVER['HTTP_REFERER'] . $re;
} else
	$re = bb_get_option('uri');

nocache_headers();

if ( isset( $_REQUEST['logout'] ) ) {
	bb_logout();
	wp_redirect( $re );
	exit;
}

if ( !bb_is_user_logged_in() && !$user = bb_login( @$_POST['user_login'], @$_POST['password'] ) ) {
	$user_exists = bb_user_exists( @$_POST['user_login'] );
	$user_login  = user_sanitize ( @$_POST['user_login'] );
	$redirect_to = wp_specialchars( $re, 1 );
	include('bb-templates/login.php');
	exit;
}

wp_redirect( $re );
?>
