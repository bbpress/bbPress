<?php

if ( !function_exists('bb_auth') ) :
function bb_auth() {
	global $bb;
	// Checks if a user is logged in, if not redirects them to the login page
	if ( (!empty($_COOKIE[$bb->usercookie]) && 
				!bb_check_login($_COOKIE[$bb->usercookie], $_COOKIE[$bb->passcookie], true)) ||
			 (empty($_COOKIE[$bb->usercookie])) ) {
		nocache_headers();

		header('Location: ' . bb_get_option('uri'));
		exit();
	}
}
endif;

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

if ( !function_exists('bb_get_current_user') ) :
function bb_get_current_user() {
	global $bb_current_user;

	bb_current_user();

	return $bb_current_user;
}
endif;

if ( !function_exists('bb_set_current_user') ) :
function bb_set_current_user($id) {
	global $bb_current_user;

	if ( isset($bb_current_user) && ($id == $bb_current_user->ID) )
		return $bb_current_user;

	if ( empty($id) ) {
		$bb_current_user = 0;
	} else {
		$bb_current_user = new BB_User($id);
	}

	do_action('bb_set_current_user', $id);

	return $bb_current_user;
}
endif;

if ( !function_exists('bb_current_user') ) :
//This is only used at initialization.  Use global $bb_current_user to grab user info.
function bb_current_user() {
	global $bb_current_user;

	if ( defined( 'BB_INSTALLING' ) )
		return false;

	if ( ! empty($bb_current_user) )
		return $bb_current_user;

	global $bbdb, $bb, $bb_cache, $bb_user_cache;
	$userpass = bb_get_cookie_login();
	if ( empty($userpass) )
		return false;
	$user = user_sanitize( $userpass['login'] );
	$pass = user_sanitize( $userpass['password'] );
	if ( $current_user = $bbdb->get_row("SELECT * FROM $bbdb->users WHERE user_login = '$user' AND MD5( user_pass ) = '$pass' AND user_status % 2 = 0") ) {
		$current_user = $bb_cache->append_current_user_meta( $current_user );
		return bb_set_current_user($current_user->ID);
	} else {
		$bb_user_cache[$current_user->ID] = false;
		bb_set_current_user(0);
		return false;
	}
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
	$current_user = bb_get_current_user();

	if ( empty($current_user) )
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
		do_action('bb_user_login', '');
	}

	return $user;
}
endif;

if ( !function_exists('bb_logout') ) :
function bb_logout() {
	global $bb;

	bb_cookie( $bb->passcookie , ' ', time() - 31536000 );
	bb_cookie( $bb->usercookie , ' ', time() - 31536000 );
	do_action('bb_user_logout', '');
}
endif;

if ( !function_exists('bb_verify_nonce') ) :
function bb_verify_nonce($nonce, $action = -1) {
	$user = bb_get_current_user();
	$uid = $user->ID;

	$i = ceil(time() / 43200);

	//Allow for expanding range, but only do one check if we can
	if( substr(wp_hash($i . $action . $uid), -12, 10) == $nonce || substr(wp_hash(($i - 1) . $action . $uid), -12, 10) == $nonce )
		return true;
	return false;
}
endif;

if ( !function_exists('bb_create_nonce') ) :
function bb_create_nonce($action = -1) {
	$user = bb_get_current_user();
	$uid = $user->ID;

	$i = ceil(time() / 43200);
	
	return substr(wp_hash($i . $action . $uid), -12, 10);
}
endif;

if ( !function_exists('bb_check_admin_referer') ) :
function bb_check_admin_referer($action = -1) {
	if ( !bb_verify_nonce($_REQUEST['_wpnonce'], $action) ) {
		bb_nonce_ays($action);
		die();
	}
	do_action('bb_check_admin_referer', $action);
}endif;

if ( !function_exists('bb_check_ajax_referer') ) :
function bb_check_ajax_referer() {
	$cookie = explode('; ', urldecode(empty($_POST['cookie']) ? $_GET['cookie'] : $_POST['cookie'])); // AJAX scripts must pass cookie=document.cookie
	foreach ( $cookie as $tasty ) {
		if ( false !== strpos($tasty, USER_COOKIE) )
			$user = substr(strstr($tasty, '='), 1);
		if ( false !== strpos($tasty, PASS_COOKIE) )
			$pass = substr(strstr($tasty, '='), 1);
	}
	if ( !bb_check_login( $user, $pass, true ) )
		die('-1');
	do_action('bb_check_ajax_referer');
}
endif;

?>
