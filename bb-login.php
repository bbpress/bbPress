<?php
// Load bbPress.
require('./bb-load.php');

// Redirect to an SSL page if required.
bb_ssl_redirect();

// Get the referer.
if ( isset( $_POST['redirect_to'] ) ) {
	$re = $_POST['redirect_to'];
}
if ( empty( $re ) && isset( $_GET['redirect_to'] ) ) {
	$re = $_GET['redirect_to'];
}
if ( empty( $re ) && isset( $_POST['re'] ) ) {
	$re = $_POST['re'];
}
if ( empty( $re ) && isset( $_GET['re'] ) ) {
	$re = $_GET['re'];
}
if ( empty( $re ) ) {
	$re = wp_get_referer();
}

// Grab the URL for comparison.
$home_url = parse_url( bb_get_uri( null, null, BB_URI_CONTEXT_TEXT ) );
$home_path = $home_url['path'];

// Don't ever redirect to the register page or the password reset page.
if ( !$re || false !== strpos( $re, $home_path . 'register.php' ) || false !== strpos( $re, $home_path . 'bb-reset-password.php' ) ) {
	$re = bb_get_uri( null, null, BB_URI_CONTEXT_HEADER );
}

// Don't cache this page at all.
nocache_headers();

// If this page was accessed using SSL, make sure the redirect is a full URL
// so that we don't end up on an SSL page again (unless the whole site is
// under SSL).
if ( is_ssl() && 0 === strpos( $re, '/' ) ) {
	$re = bb_get_uri( $re , null, BB_URI_CONTEXT_HEADER );
}

// Logout requested.
if ( isset( $_GET['logout'] ) ) {
	$_GET['action'] = 'logout';
}
if ( isset( $_GET['action'] ) && 'logout' === $_GET['action'] ) {
	bb_logout();
	bb_safe_redirect( $re );
	exit;
}

// User is already logged in.
if ( bb_is_user_logged_in() ) {
	bb_safe_redirect( $re );
	exit;
}

// Get the user from the login details.
if ( !empty( $_POST['user_login'] ) ) {
	$_POST['log'] = $_POST['user_login'];
}
if ( !empty( $_POST['password'] ) ) {
	$_POST['pwd'] = $_POST['password'];
}
if ( !empty( $_POST['remember'] ) ) {
	$_POST['rememberme'] = 1;
}
$user = bb_login( @$_POST['log'], @$_POST['pwd'], @$_POST['rememberme'] );

// User logged in successfully.
if ( $user && !is_wp_error( $user ) ) {
	bb_safe_redirect( $re );
	exit;
}

// Grab the error returned if there is one.
if ( is_wp_error( $user ) ) {
	$bb_login_error =& $user;
} else {
	$bb_login_error = new WP_Error;
}

// Whether we allow login by email address or not.
$email_login = bb_get_option( 'email_login' );

// Find out if the user actually exists.
$error_data = $bb_login_error->get_error_data();
if ( isset( $error_data['unique'] ) && false === $error_data['unique'] ) {
	$user_exists = true;
} else {
	$user_exists = !empty( $_POST['log'] ) && (bool) bb_get_user( $_POST['log'], array( 'by' => 'login' ) );
}
unset( $error_data );

if ( 'post' == strtolower( $_SERVER['REQUEST_METHOD'] ) ) {
	// If the user doesn't exist then add that error.
	if ( !$user_exists ) {
		if ( !empty( $_POST['log'] ) ) {
			$bb_login_error->add( 'user_login', __( 'User does not exist.' ) );
		} else {
			$bb_login_error->add( 'user_login', $email_login ? __( 'Enter a username or email address.' ) : __( 'Enter a username.' ) );
		}
	}

	// If the password was wrong then add that error.
	if ( !$bb_login_error->get_error_code() ) {
		$bb_login_error->add( 'password', __( 'Incorrect password.' ) );
	}
}

// If trying to log in with email address, don't leak whether or not email address exists in the db.
// is_email() is not perfect, usernames can be valid email addresses potentially.
if ( $email_login && $bb_login_error->get_error_codes() && false !== is_email( @$_POST['log'] ) ) {
	$bb_login_error = new WP_Error( 'user_login', __( 'Username and Password do not match.' ) );
}

// Sanitze variables for display.
$user_login = esc_attr( sanitize_user( @$_POST['log'], true ) );
$remember_checked = @$_POST['rememberme'] ? ' checked="checked"' : '';
$re = esc_url( $re );
$re = $redirect_to = esc_attr( $re );

// Load the template.
bb_load_template( 'login.php', array( 'user_exists', 'user_login', 'remember_checked', 'redirect_to', 're', 'bb_login_error' ) );
exit;
