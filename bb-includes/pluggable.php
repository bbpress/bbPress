<?php

if ( !function_exists('bb_auth') ) :
function bb_auth() {
	// Checks if a user has a valid cookie, if not redirects them to the login page
	if (!wp_validate_auth_cookie()) {
		nocache_headers();
		header('Location: ' . bb_get_option('uri'));
		exit();
	}
}
endif;

// $already_md5 variable is deprecated
if ( !function_exists('bb_check_login') ) :
function bb_check_login($user, $pass, $already_md5 = false) {
	global $bbdb;
	$user = sanitize_user( $user );
	if ($user == '') {
		return false;
	}
	$user = bb_get_user_by_name( $user );
	
	if ( !wp_check_password($pass, $user->user_pass, $user->ID) ) {
		return false;
	}
	
	return $user;
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
	
	if ($user_id = wp_validate_auth_cookie()) {
		return bb_set_current_user($user_id);
	} else {
		global $bb_user_cache;
		$bb_user_cache[$user_id] = false;
		bb_set_current_user(0);
		return false;
	}
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
function bb_login($login, $password, $remember = false) {
	if ( $user = bb_check_login( $login, $password ) ) {
		wp_set_auth_cookie($user->ID, $remember);
		
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
	if ( empty($cookie) ) {
		global $bb;
		if ( empty($_COOKIE[$bb->authcookie]) )
			return false;
		$cookie = $_COOKIE[$bb->authcookie];
	}

	$cookie_elements = explode('|', $cookie);
	if ( count($cookie_elements) != 3 )
		return false;

	list($username, $expiration, $hmac) = $cookie_elements;

	$expired = $expiration;

	// Allow a grace period for POST and AJAX requests
	if ( defined('DOING_AJAX') || 'POST' == $_SERVER['REQUEST_METHOD'] )
		$expired += 3600;

	if ( $expired < time() )
		return false;

	$key = wp_hash($username . '|' . $expiration);
	$hash = hash_hmac('md5', $username . '|' . $expiration, $key);
	
	if ( $hmac != $hash )
		return false;

	$user = bb_get_user_by_name($username);
	if ( ! $user )
		return false;

	return $user->ID;
}
endif;

if ( !function_exists('wp_generate_auth_cookie') ) :
function wp_generate_auth_cookie($user_id, $expiration) {
	$user = bb_get_user($user_id);
	
	$key = wp_hash($user->user_login . '|' . $expiration);
	$hash = hash_hmac('md5', $user->user_login . '|' . $expiration, $key);
	
	$cookie = $user->user_login . '|' . $expiration . '|' . $hash;
	
	return apply_filters('auth_cookie', $cookie, $user_id, $expiration);
}
endif;

if ( !function_exists('wp_set_auth_cookie') ) :
function wp_set_auth_cookie($user_id, $remember = false) {
	global $bb;
	
	if ( $remember ) {
		$expiration = $expire = time() + 1209600;
	} else {
		$expiration = time() + 172800;
		$expire = 0;
	}
	
	$cookie = wp_generate_auth_cookie($user_id, $expiration);
	
	do_action('set_auth_cookie', $cookie, $expire);
	
	setcookie($bb->authcookie, $cookie, $expire, $bb->cookiepath, $bb->cookiedomain);
	if ( $bb->cookiepath != $bb->sitecookiepath )
		setcookie($bb->authcookie, $cookie, $expire, $bb->sitecookiepath, $bb->cookiedomain);
}
endif;

if ( !function_exists('wp_clear_auth_cookie') ) :
function wp_clear_auth_cookie() {
	global $bb;
	setcookie($bb->authcookie, ' ', time() - 31536000, $bb->cookiepath, $bb->cookiedomain);
	setcookie($bb->authcookie, ' ', time() - 31536000, $bb->sitecookiepath, $bb->cookiedomain);
	
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

	$allowed_hosts = (array) apply_filters('allowed_redirect_hosts', array($wpp['host']), isset($lp['host']) ? $lp['host'] : '');

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

	// Nonce generated 0-12 hours ago
	if ( substr(wp_hash($i . $action . $uid), -12, 10) == $nonce )
		return 1;
	// Nonce generated 12-24 hours ago
	if ( substr(wp_hash(($i - 1) . $action . $uid), -12, 10) == $nonce )
		return 2;
	// Invalid nonce
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
				$salt = wp_generate_password(64);
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

	return hash_hmac('md5', $data, $salt);
}
endif;

if ( !function_exists('wp_hash_password') ) : // [WP6350]
function wp_hash_password($password) {
	global $wp_hasher;

	if ( empty($wp_hasher) ) { 
		require_once( BB_PATH . BB_INC . 'class-phpass.php');
		// By default, use the portable hash from phpass
		$wp_hasher = new PasswordHash(8, TRUE);
	}
	
	return $wp_hasher->HashPassword($password);
}
endif;

if ( !function_exists('wp_check_password') ) : // [WP6350]
function wp_check_password($password, $hash, $user_id = '') {
	global $wp_hasher;

	// If the hash is still md5...
	if ( strlen($hash) <= 32 ) {
		$check = ( $hash == md5($password) );
		if ( $check && $user_id ) {
			// Rehash using new hash.
			wp_set_password($password, $user_id);
			$hash = wp_hash_password($password);
		}

		return apply_filters('check_password', $check, $password, $hash, $user_id);
	}

	if ( strlen($hash) <= 32 )
		return ( $hash == md5($password) );

	// If the stored hash is longer than an MD5, presume the
	// new style phpass portable hash.
	if ( empty($wp_hasher) ) {
		require_once( BB_PATH . BB_INC . 'class-phpass.php');
		// By default, use the portable hash from phpass
		$wp_hasher = new PasswordHash(8, TRUE);
	}

	$check = $wp_hasher->CheckPassword($password, $hash);

	return apply_filters('check_password', $check, $password, $hash, $user_id);
}
endif;

if ( !function_exists('wp_generate_password') ) :
/**
 * wp_generate_password() - Generates a random password drawn from the defined set of characters
 *
 * @since WP 2.5
 *
 * @return string The random password
 **/
function wp_generate_password($length = 12) {
	$chars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*()";
	$password = '';
	for ( $i = 0; $i < $length; $i++ )
		$password .= substr($chars, mt_rand(0, strlen($chars) - 1), 1);
	return $password;
}
endif;

if ( !function_exists('bb_check_admin_referer') ) :
function bb_check_admin_referer( $action = -1, $query_arg = '_wpnonce' ) {
	if ( !bb_verify_nonce($_REQUEST[$query_arg], $action) ) {
		bb_nonce_ays($action);
		die();
	}
	do_action('bb_check_admin_referer', $action);
}
endif;

if ( !function_exists('bb_check_ajax_referer') ) :
function bb_check_ajax_referer( $action = -1, $query_arg = false, $die = true ) {
	if ( $query_arg )
		$nonce = $_REQUEST[$query_arg];
	else
		$nonce = $_REQUEST['_ajax_nonce'] ? $_REQUEST['_ajax_nonce'] : $_REQUEST['_wpnonce'];

	$result = bb_verify_nonce( $nonce, $action );

	if ( $die && false == $result )
		die('-1');

	do_action('bb_check_ajax_referer', $action, $result);
	return $result;
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
function bb_new_user( $user_login, $user_email, $user_url ) {
	global $bbdb;
	$user_login = sanitize_user( $user_login, true );
	$user_email = bb_verify_email( $user_email );
	
	if ( !$user_login || !$user_email )
		return false;
	
	$user_nicename = $_user_nicename = bb_user_nicename_sanitize( $user_login );
	if ( strlen( $_user_nicename ) < 1 )
		return false;

	while ( is_numeric($user_nicename) || $existing_user = bb_get_user_by_nicename( $user_nicename ) )
		$user_nicename = bb_slug_increment($_user_nicename, $existing_user->user_nicename, 50);
	
	$user_url = bb_fix_link( $user_url );
	$user_registered = bb_current_time('mysql');
	$password = wp_generate_password();
	$user_pass = wp_hash_password( $password );

	$bbdb->insert( $bbdb->users,
		compact( 'user_login', 'user_pass', 'user_nicename', 'user_email', 'user_url', 'user_registered' )
	);
	
	$user_id = $bbdb->insert_id;

	if ( defined( 'BB_INSTALLING' ) ) {
		bb_update_usermeta( $user_id, $bbdb->prefix . 'capabilities', array('keymaster' => true) );
	} else {		
		bb_update_usermeta( $user_id, $bbdb->prefix . 'capabilities', array('member' => true) );
		bb_send_pass( $user_id, $password );
	}

	do_action('bb_new_user', $user_id, $password);
	return $user_id;
}
endif;

if ( !function_exists( 'bb_mail' ) ) :
function bb_mail( $to, $subject, $message, $headers = '' ) {
	if (!is_array($headers)) {
		$headers = trim($headers);
		$headers = preg_split('@\r(?:\n{0,1})|\n@', $headers, -1, PREG_SPLIT_NO_EMPTY);
	}
	
	if (!count($headers) || !count(preg_grep('/^mime-version:\s/im', $headers)))
		$headers[] = "MIME-Version: 1.0";
	
	if (!count(preg_grep('/^content-type:\s/im', $headers)))
		$headers[] = "Content-Type: text/plain; Charset=UTF-8";
	
	if (!count(preg_grep('/^content-transfer-encoding:\s/im', $headers)))
		$headers[] = "Content-Transfer-Encoding: 8bit";
	
	if (!count(preg_grep('/^from:\s/im', $headers))) {
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

if ( !function_exists('wp_set_password') ) :
function wp_set_password( $password, $user_id ) {
	global $bbdb, $bb_cache;

	$hash = wp_hash_password($password);
	$query = $bbdb->prepare("UPDATE $bbdb->users SET user_pass = %s WHERE ID = %d", $hash, $user_id);
	$bbdb->query($query);
	$bb_cache->flush_one( 'user', $user_id );
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

	if ( !empty($email) ) {
		$src = 'http://www.gravatar.com/avatar/';
		$src .= md5( strtolower( $email ) );
		$src .= '?s=' . $size;
		$src .= '&amp;d=' . urlencode( $default );

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
