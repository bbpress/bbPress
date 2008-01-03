<?php

if ( !function_exists('bb_auth') ) :
function bb_auth() {
	// Checks if a user is logged in, if not redirects them to the login page
	if ( (!empty($_COOKIE[bb_get_option( 'usercookie' )]) && 
				!bb_check_login($_COOKIE[bb_get_option( 'usercookie' )], $_COOKIE[bb_get_option( 'passcookie' )], true)) ||
			 (empty($_COOKIE[bb_get_option( 'usercookie' )])) ) {
		nocache_headers();

		header('Location: ' . bb_get_option('uri'));
		exit();
	}
}
endif;

if ( !function_exists('bb_check_login') ) :
function bb_check_login($user, $pass, $already_md5 = false) {
	global $bbdb;
	$user = bb_user_sanitize( $user );
	if ( !$already_md5 ) {
		$pass = bb_user_sanitize( md5( $pass ) );
		return $bbdb->get_row("SELECT * FROM $bbdb->users WHERE user_login = '$user' AND SUBSTRING_INDEX( user_pass, '---', 1 ) = '$pass'");
	} else {
		return $bbdb->get_row("SELECT * FROM $bbdb->users WHERE user_login = '$user' AND MD5( user_pass ) = '$pass'");
	}
}
endif;

if ( !function_exists('bb_cookie') ) :
function bb_cookie( $name, $value, $expires = 0 ) {
	if ( !$expires )
		$expires = time() + 604800;
	if ( bb_get_option( 'cookiedomain' ) )
		setcookie( $name, $value, $expires, bb_get_option( 'cookiepath' ), bb_get_option( 'cookiedomain' ) );
	else
		setcookie( $name, $value, $expires, bb_get_option( 'cookiepath' ) );
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
		if ( !$bb_current_user->ID )
			$bb_current_user = 0;
	}

	do_action('bb_set_current_user', $id);

	return $bb_current_user;
}
endif;

if ( !function_exists('bb_current_user') ) :
//This is only used at initialization.  Use bb_get_current_user_info() (or $bb_current_user global if really needed) to grab user info.
function bb_current_user() {
	global $bb_current_user;

	if ( defined( 'BB_INSTALLING' ) )
		return false;

	if ( ! empty($bb_current_user) )
		return $bb_current_user;

	global $bbdb, $bb_cache, $bb_user_cache;
	$userpass = bb_get_cookie_login();
	if ( empty($userpass) )
		return false;
	$user = bb_user_sanitize( $userpass['login'] );
	$pass = bb_user_sanitize( $userpass['password'] );
	if ( $current_user = $bbdb->get_row("SELECT * FROM $bbdb->users WHERE user_login = '$user' AND MD5( user_pass ) = '$pass'") ) {
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
	if ( empty($_COOKIE[bb_get_option( 'usercookie' )]) || empty($_COOKIE[bb_get_option( 'passcookie' )]) )
		return false;

	return array('login' => $_COOKIE[bb_get_option( 'usercookie' )],	'password' => $_COOKIE[bb_get_option( 'passcookie' )]);
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
	if ( $user = bb_check_login( $login, $password ) ) {
		bb_cookie( bb_get_option( 'usercookie' ), $user->user_login, time() + 6048000 );
		bb_cookie( bb_get_option( 'passcookie' ), md5( $user->user_pass ) );
		do_action('bb_user_login', (int) $user->ID );
	}

	return $user;
}
endif;

if ( !function_exists('bb_logout') ) :
function bb_logout() {
	bb_cookie( bb_get_option( 'passcookie' ) , ' ', time() - 31536000 );
	bb_cookie( bb_get_option( 'usercookie' ) , ' ', time() - 31536000 );
	do_action('bb_user_logout', '');
}
endif;

// Cookie safe redirect.  Works around IIS Set-Cookie bug.
// http://support.microsoft.com/kb/q176113/
if ( !function_exists('wp_redirect') ) : // [WP6134]
function wp_redirect($location, $status = 302) {
	global $is_IIS;

	$location = apply_filters('wp_redirect', $location, $status);

	if ( !$location ) // allows the wp_redirect filter to cancel a redirect
		return false;

	$location = wp_sanitize_redirect($location);

	if ( $is_IIS ) {
		header("Refresh: 0;url=$location");
	} else {
		if ( php_sapi_name() != 'cgi-fcgi' )
			status_header($status); // This causes problems on IIS and some FastCGI setups
		header("Location: $location");
	}
}
endif;

if ( !function_exists('wp_sanitize_redirect') ) : // [WP6134]
/**
 * sanitizes a URL for use in a redirect
 * @return string redirect-sanitized URL
 **/
function wp_sanitize_redirect($location) {
	$location = preg_replace('|[^a-z0-9-~+_.?#=&;,/:%]|i', '', $location);
	$location = wp_kses_no_null($location);

	// remove %0d and %0a from location
	$strip = array('%0d', '%0a');
	$found = true;
	while($found) {
		$found = false;
		foreach($strip as $val) {
			while(strpos($location, $val) !== false) {
				$found = true;
				$location = str_replace($val, '', $location);
			}
		}
	}
	return $location;
}
endif;

if ( !function_exists('bb_safe_redirect') ) : // based on [WP6145] (home is different)
/**
 * performs a safe (local) redirect, using wp_redirect()
 * @return void
 **/
function bb_safe_redirect($location, $status = 302) {

	// Need to look at the URL the way it will end up in wp_redirect()
	$location = wp_sanitize_redirect($location);

	// browsers will assume 'http' is your protocol, and will obey a redirect to a URL starting with '//'
	if ( substr($location, 0, 2) == '//' )
		$location = 'http:' . $location;

	$lp  = parse_url($location);
	$wpp = parse_url(bb_get_option('uri'));

	$allowed_hosts = (array) apply_filters('allowed_redirect_hosts', array($wpp['host']), $lp['host']);

	if ( isset($lp['host']) && !in_array($lp['host'], $allowed_hosts) )
		$location = bb_get_option('uri');

	wp_redirect($location, $status);
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

// Not verbatim WP,  bb has no options table and constants have different names.
if ( !function_exists('wp_salt') ) :
function wp_salt() {
	$salt = bb_get_option( 'secret' );
	if ( empty($salt) )
		$salt = BBDB_PASSWORD . BBDB_USER . BBDB_NAME . BBDB_HOST . BBPATH;

	return $salt;
}
endif;

if ( !function_exists('wp_hash') ) :
function wp_hash($data) { 
	$salt = wp_salt();

	if ( function_exists('hash_hmac') ) {
		return hash_hmac('md5', $data, $salt);
	} else {
		return md5($data . $salt);
	}
}
endif;

if ( !function_exists('bb_check_admin_referer') ) :
function bb_check_admin_referer( $action = -1 ) {
	if ( !bb_verify_nonce($_REQUEST['_wpnonce'], $action) ) {
		bb_nonce_ays($action);
		die();
	}
	do_action('bb_check_admin_referer', $action);
}
endif;

if ( !function_exists('bb_check_ajax_referer') ) :
function bb_check_ajax_referer() {
	if ( !$current_name = bb_get_current_user_info( 'name' ) )
		die('-1');

	$cookie = explode('; ', urldecode(empty($_POST['cookie']) ? $_GET['cookie'] : $_POST['cookie'])); // AJAX scripts must pass cookie=document.cookie
	foreach ( $cookie as $tasty ) {
		if ( false !== strpos($tasty, bb_get_option( 'usercookie' )) )
			$user = substr(strstr($tasty, '='), 1);
		if ( false !== strpos($tasty, bb_get_option( 'passcookie' )) )
			$pass = substr(strstr($tasty, '='), 1);
	}

	if ( $current_name != $user || !bb_check_login( $user, $pass, true ) )
		die('-1');
	do_action('bb_check_ajax_referer');
}
endif;

if ( !function_exists('bb_break_password') ) :
function bb_break_password( $user_id ) {
	global $bbdb;
	$user_id = (int) $user_id;
	if ( !$user = bb_get_user( $user_id ) )
		return false;
	$secret = substr(wp_hash( 'bb_break_password' ), 0, 13);
	if ( false === strpos( $user->user_pass, '---' ) )
		return $bbdb->query("UPDATE $bbdb->users SET user_pass = CONCAT(user_pass, '---', '$secret') WHERE ID = '$user_id'");
	else
		return true;
}
endif;

if ( !function_exists('bb_fix_password') ) :
function bb_fix_password( $user_id ) {
	global $bbdb;
	$user_id = (int) $user_id;
	if ( !$user = bb_get_user( $user_id ) )
		return false;
	if ( false === strpos( $user->user_pass, '---' ) )
		return true;
	else
		return $bbdb->query("UPDATE $bbdb->users SET user_pass = SUBSTRING_INDEX(user_pass, '---', 1) WHERE ID = '$user_id'");
}
endif;

if ( !function_exists('bb_has_broken_pass') ) :
function bb_has_broken_pass( $user_id = 0 ) {
	global $bb_current_user;
	if ( !$user_id )
		$user =& $bb_current_user->data;
	else
		$user = bb_get_user( $user_id );

	return ( false !== strpos($user->user_pass, '---' ) );
}
endif;

if ( !function_exists('bb_new_user') ) :
function bb_new_user( $user_login, $email, $url ) {
	global $bbdb, $bb_table_prefix;
	$user_login = bb_user_sanitize( $user_login, true );
	$email      = bb_verify_email( $email );
	$url        = bb_fix_link( $url );
	$now        = bb_current_time('mysql');
	$password   = bb_random_pass();
	$passcrypt  = md5( $password );

	if ( !$user_login || !$email )
		return false;

	$email = $bbdb->escape( $email );

	$bbdb->query("INSERT INTO $bbdb->users
	(user_login,     user_pass, user_email,  user_url, user_registered)
	VALUES
	('$user_login', '$passcrypt', '$email', '$url',   '$now')");
	
	$user_id = $bbdb->insert_id;

	if ( defined( 'BB_INSTALLING' ) ) {
		bb_update_usermeta( $user_id, $bb_table_prefix . 'capabilities', array('keymaster' => true) );
	} else {		
		bb_update_usermeta( $user_id, $bb_table_prefix . 'capabilities', array('member' => true) );
		bb_send_pass( $user_id, $password );
	}

	do_action('bb_new_user', $user_id, $password);
	return $user_id;

}
endif;

if ( !function_exists( 'bb_mail' ) ) :
function bb_mail( $to, $subject, $message, $headers = '' ) {
	$headers = trim($headers);

	if ( !preg_match( '/^from:\s/im', $headers ) ) {
		$from = parse_url( bb_get_option( 'domain' ) );
		if ( !$from || !$from['host'] ) {
			$from = '';
		} else {
			$from_host = $from['host'];
		        if ( substr( $from_host, 0, 4 ) == 'www.' )
		                $from_host = substr( $from_host, 4 );
			$from = 'From: "' . bb_get_option( 'name' ) . '" <bbpress@' . $from_host . '>';
		}
		$headers .= "\n$from";
		$headers = trim($headers);
	}

	return @mail( $to, $subject, $message, $headers );
}
endif;

?>
