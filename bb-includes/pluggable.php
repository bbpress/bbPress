<?php

if ( !function_exists('bb_auth') ) :
function bb_auth() { // Checks if a user has a valid cookie, if not redirects them to the main page
	if ( !wp_validate_auth_cookie() ) {
		nocache_headers();
		header('Location: ' . bb_get_option('uri'));
		exit();
	}
}
endif;

// $already_md5 variable is deprecated
if ( !function_exists('bb_check_login') ) :
function bb_check_login($user, $pass, $already_md5 = false) {
	global $wp_users_object;

	if ( !$user = sanitize_user( $user ) )
		return false;

	if ( !$user = bb_get_user( $user ) )
		return false;
	
	if ( !wp_check_password($pass, $user->user_pass, $user->ID) )
		return false;
	
	return $user;
}
endif;

if ( !function_exists('bb_get_current_user') ) :
function bb_get_current_user() {
	global $wp_auth_object;
	return $wp_auth_object->get_current_user();
}
endif;

if ( !function_exists('bb_set_current_user') ) :
function bb_set_current_user( $id ) {
	global $wp_auth_object;
	$current_user = $wp_auth_object->set_current_user( $id );
	
	do_action('bb_set_current_user', isset($current_user->ID) ? $current_user->ID : 0 );
	
	return $current_user;
}
endif;

if ( !function_exists('bb_current_user') ) :
//This is only used at initialization.  Use bb_get_current_user_info() (or $bb_current_user global if really needed) to grab user info.
function bb_current_user() {
	if ( defined( 'BB_INSTALLING' ) )
		return false;

	return bb_get_current_user();
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
function bb_login( $login, $password, $remember = false ) {
	if ( $user = bb_check_login( $login, $password ) ) {
		wp_set_auth_cookie( $user->ID, $remember );
		do_action('bb_user_login', (int) $user->ID );
	}
	
	return $user;
}
endif;

if ( !function_exists('bb_logout') ) :
function bb_logout() {
	wp_clear_auth_cookie();
	
	do_action('bb_user_logout', '');
}
endif;

if ( !function_exists('wp_validate_auth_cookie') ) :
function wp_validate_auth_cookie($cookie = '') {
	global $wp_auth_object;
	return $wp_auth_object->validate_auth_cookie( $cookie );
}
endif;

if ( !function_exists('wp_set_auth_cookie') ) :
function wp_set_auth_cookie($user_id, $remember = false) {
	global $wp_auth_object;

	if ( $remember )
		$expiration = time() + 1209600;
	else
		$expiration = 0;

	$wp_auth_object->set_auth_cookie( $user_id, $expiration );
}
endif;

if ( !function_exists('wp_clear_auth_cookie') ) :
function wp_clear_auth_cookie() {
	global $bb, $wp_auth_object;

	$wp_auth_object->clear_auth_cookie();
	
	// Old cookies
	setcookie($bb->usercookie, ' ', time() - 31536000, $bb->cookiepath, $bb->cookiedomain);
	setcookie($bb->usercookie, ' ', time() - 31536000, $bb->sitecookiepath, $bb->cookiedomain);
	setcookie($bb->passcookie, ' ', time() - 31536000, $bb->cookiepath, $bb->cookiedomain);
	setcookie($bb->passcookie, ' ', time() - 31536000, $bb->sitecookiepath, $bb->cookiedomain);
}
endif;

// Cookie safe redirect.  Works around IIS Set-Cookie bug.
// http://support.microsoft.com/kb/q176113/
if ( !function_exists('wp_redirect') ) : // [WP6134]
function wp_redirect($location, $status = 302) {
	global $is_IIS;

	$location = apply_filters('wp_redirect', $location, $status);

	$status = apply_filters('wp_redirect_status', $status, $location);

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

	$secret_key = '';
	if ( defined('BB_SECRET_KEY') && ('' != BB_SECRET_KEY) && ('put your unique phrase here' != BB_SECRET_KEY) )
		$secret_key = BB_SECRET_KEY;

	if ( defined('BB_SECRET_SALT') ) {
		$salt = BB_SECRET_SALT;
	} else {
		if (!defined('BB_INSTALLING')) {
			$salt = bb_get_option('secret');
			if ( empty($salt) ) {
				$salt = wp_generate_password();
				bb_update_option('secret', $salt);
			}
		}
	}

	return apply_filters('salt', $secret_key . $salt);
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

if ( !function_exists('wp_hash_password') ) : // [WP6350]
function wp_hash_password($password) {
	return WP_Pass::hash_password( $password );
}
endif;

if ( !function_exists('wp_check_password') ) : // [WP6350]
function wp_check_password($password, $hash, $user_id = '') {
	return WP_Pass::check_password( $password, $hash, $user_id );
}
endif;

if ( !function_exists('wp_generate_password') ) :
/**
 * Generates a random password drawn from the defined set of characters
 * @return string the password
 **/
function wp_generate_password( $length = 7 ) {
	return WP_Pass::generate_password( $length );
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
	if ( !$current_id = bb_get_current_user_info( 'ID' ) )
		die('-1');
	
	$cookie = explode('; ', urldecode(empty($_POST['cookie']) ? $_GET['cookie'] : $_POST['cookie'])); // AJAX scripts must pass cookie=document.cookie
	foreach ( $cookie as $tasty ) {
		if ( false !== strpos($tasty, bb_get_option( 'authcookie' )) )
			$auth_cookie = substr(strstr($tasty, '='), 1);
	}
	
	if ( empty($auth_cookie) )
		die('-1');
	
	if ( ! $user_id = wp_validate_auth_cookie( $auth_cookie ) )
	    die('-1');
	
	if ( $current_id != $user_id )
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
		return $bbdb->query( $bbdb->prepare(
			"UPDATE $bbdb->users SET user_pass = CONCAT(user_pass, '---', %s) WHERE ID = %d",
			$secret, $user_id
		) );
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
		return $bbdb->query( $bbdb->prepare(
			"UPDATE $bbdb->users SET user_pass = SUBSTRING_INDEX(user_pass, '---', 1) WHERE ID = %d",
			$user_id
		) );
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
function bb_new_user( $user_login, $user_email, $user_url = '' ) {
	global $wp_users_object, $bbdb;

	// is_email check + dns
	if ( !$user_email = bb_verify_email( $user_email ) )
		return false;

	if ( !$user_login = sanitize_user( $user_login, true ) )
		return false;
	
	$user_nicename = $_user_nicename = bb_user_nicename_sanitize( $user_login );
	if ( strlen( $_user_nicename ) < 1 )
		return false;

	while ( is_numeric($user_nicename) || $existing_user = bb_get_user_by_nicename( $user_nicename ) )
		$user_nicename = bb_slug_increment($_user_nicename, $existing_user->user_nicename, 50);
	
	$user_url = $user_url ? bb_fix_link( $user_url ) : '';

	$user = $wp_users_object->new_user( compact( 'user_login', 'user_email', 'user_url', 'user_nicename' ) );
	if ( is_wp_error($user) )
		return false;

	if ( defined( 'BB_INSTALLING' ) ) {
		bb_update_usermeta( $user['ID'], $bbdb->prefix . 'capabilities', array('keymaster' => true) );
	} else {		
		bb_update_usermeta( $user['ID'], $bbdb->prefix . 'capabilities', array('member' => true) );
		bb_send_pass( $user['ID'], $user['plain_pass'] );
	}

	do_action('bb_new_user', $user['ID'], $user['plain_pass']);
	return $user['ID'];
}
endif;

if ( !function_exists( 'bb_mail' ) ) :
function bb_mail( $to, $subject, $message, $headers = '' ) {
	if (!is_array($headers)) {
		$headers = trim($headers);
		$headers = preg_split('@\r(?:\n{0,1})|\n@', $headers, -1, PREG_SPLIT_NO_EMPTY);
	}
	
	if (!count($headers) || !count(preg_grep('/^from:\s/im', $headers))) {
		if (!$from = bb_get_option('from_email'))
			if ($uri_parsed = parse_url(bb_get_option('uri')))
				if ($uri_parsed['host'])
					$from = 'bbpress@' . trim(preg_replace('/^www./i', '', $uri_parsed['host']));
		
		if ($from)
			$headers[] = 'From: "' . bb_get_option('name') . '" <' . $from . '>';
	}
	$headers = trim(join(defined('BB_MAIL_EOL') ? BB_MAIL_EOL : "\n", $headers));
	
	return @mail($to, $subject, $message, $headers);
}
endif;

if ( !function_exists( 'bb_get_avatar' ) ) :
/**
 * bb_get_avatar() - Get avatar for a user
 *
 * Retrieve the avatar for a user provided a user ID or email address
 *
 * @since 0.9
 * @param int|string $id_or_email A user ID or email address
 * @param int $size Size of the avatar image
 * @param string $default URL to a default image to use if no avatar is available
 * @return string <img> tag for the user's avatar
*/
function bb_get_avatar( $id_or_email, $size = 80, $default = '' ) {
	if ( !bb_get_option('avatars_show') )
		return false;

	if ( !is_numeric($size) )
		$size = 80;

	if ( !$email = bb_get_user_email($id_or_email) )
		$email = $id_or_email;

	if ( !$email )
		$email = '';

	if ( empty($default) )
		$default = 'http://www.gravatar.com/avatar/ad516503a11cd5ca435acc9bb6523536?s=' . $size;
		// ad516503a11cd5ca435acc9bb6523536 == md5('unknown@gravatar.com')
	$default = urlencode( $default );

	if ( !empty($email) ) {
		$src = 'http://www.gravatar.com/avatar/';
		$src .= md5( $email );
		$src .= '?s=' . $size;
		$src .= '&amp;d=' . $default;

		$rating = bb_get_option('avatars_rating');
		if ( !empty( $rating ) )
			$src .= '&amp;r=' . $rating;

		$class = 'avatar avatar-' . $size;
	} else {
		$src = $default;
		$class = 'avatar avatar-' . $size . ' avatar-default';
	}

	$avatar = '<img alt="" src="' . $src . '" class="' . $class . '" style="height:' . $size . 'px; width:' . $size . 'px;" />';

	return apply_filters('bb_get_avatar', $avatar, $id_or_email, $size, $default);
}
endif;
?>
