<?php

if ( !function_exists('bb_check_login') ) :
function bb_check_login($user, $pass, $already_md5 = false) {
	global $bbdb;
	$user = user_sanitize( $user );
	if ( !$already_md5 ) {
		$pass = user_sanitize( md5( $pass ) );
		return $bbdb->get_row("SELECT * FROM $bbdb->users WHERE user_login = '$user' AND user_pass = '$pass'");
	} else {
		return $bbdb->get_row("SELECT * FROM $bbdb->users WHERE user_login = '$user' AND MD5( user_pass ) = '$pass'");
	}
}
endif;

if ( !function_exists('bb_cookie') ) :
function bb_cookie( $name, $value, $expires = 0 ) {
	global $bb;
	if ( !$expires )
		$expires = time() + 604800;
	if ( isset( $bb->cookiedomain ) )
		setcookie( $name, $value, $expires, $bb->cookiepath, $bb->cookiedomain );
	else
		setcookie( $name, $value, $expires, $bb->cookiepath );
}
endif;

if ( !function_exists('bb_current_user') ) :
//This is only used at initialization.  Use global $bb_current_user to grab user info.
function bb_current_user() {
	if ( defined( 'BB_INSTALLING' ) )
		return false;

	global $bbdb, $bb, $bb_cache, $bb_user_cache;
	$userpass = bb_get_cookie_login();
	if ( empty($userpass) )
		return false;
	$user = user_sanitize( $userpass['login'] );
	$pass = user_sanitize( $userpass['password'] );
	if ( $bb_current_user = $bbdb->get_row("SELECT * FROM $bbdb->users WHERE user_login = '$user' AND MD5( user_pass ) = '$pass' AND user_status % 2 = 0") ) {
		$bb_current_user = $bb_cache->append_current_user_meta( $bb_current_user );
		return new BB_User($bb_current_user->ID);
	} else 	$bb_user_cache[$bb_current_user->ID] = false;
	return false;
}
endif;

if ( !function_exists('bb_get_cookie_login') ) :
function bb_get_cookie_login() {
	global $bb;

	if ( empty($_COOKIE[$bb->usercookie]) || empty($_COOKIE[$bb->passcookie]) )
		return false;

	return array('login' => $_COOKIE[$bb->usercookie],	'password' => $_COOKIE[$bb->passcookie]);
}
endif;

if ( !function_exists('bb_is_user_authorized') ) :
function bb_is_user_authorized() {
	return bb_is_user_logged_in();
}
endif;

if ( !function_exists('bb_is_user_logged_in') ) :
function bb_is_user_logged_in() {
	global $bb_current_user;

	if ( empty($bb_current_user) )
		return false;

	return true;
}
endif;

if ( !function_exists('bb_login') ) :
function bb_login($login, $password) {
	global $bb;

	if ( $user = bb_check_login( $login, $password ) ) {
		bb_cookie( $bb->usercookie, $user->user_login, time() + 6048000 );
		bb_cookie( $bb->passcookie, md5( $user->user_pass ) );
		bb_do_action('bb_user_login', '');
	}

	return $user;
}
endif;

if ( !function_exists('bb_logout') ) :
function bb_logout() {
	global $bb;

	bb_cookie( $bb->passcookie , ' ', time() - 31536000 );
	bb_cookie( $bb->usercookie , ' ', time() - 31536000 );
	bb_do_action('bb_user_logout', '');
}
endif;

?>
