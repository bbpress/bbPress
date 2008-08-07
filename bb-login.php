<?php
require('./bb-load.php');

bb_ssl_redirect();

$ref = wp_get_referer();
if ( !$re = $_POST['re'] ? $_POST['re'] : $_GET['re'] )
	$re = $ref;

$home_url = parse_url( bb_get_uri(null, null, BB_URI_CONTEXT_TEXT) );
$home_path = $home_url['path'];

if ( !$re || false !== strpos($re, $home_path . 'register.php') || false !== strpos($re, $home_path . 'bb-reset-password.php') )
	$re = bb_get_uri(null, null, BB_URI_CONTEXT_HEADER);

$re = clean_url( $re );

nocache_headers();

if ( isset( $_REQUEST['logout'] ) ) {
	bb_logout();
	bb_safe_redirect( $re );
	exit;
}

if ( bb_is_user_logged_in() ) {
	bb_safe_redirect( $re );
	exit;
}

$user = bb_login( @$_POST['user_login'], @$_POST['password'], @$_POST['remember'] );

if ( $user && !is_wp_error( $user ) ) {
	bb_safe_redirect( $re );
	exit;
}

if ( is_wp_error( $user ) ) {
	$bb_login_error =& $user;
} else {
	$bb_login_error = new WP_Error;
}


$error_data = $bb_login_error->get_error_data();
if ( isset($error_data['unique']) && false === $error_data['unique'] )
	$user_exists = true;
else
	$user_exists = isset($_POST['user_login']) && $_POST['user_login'] && (bool) bb_get_user( $_POST['user_login'] );
unset($error_data);

if ( !$user_exists ) {
	if ( isset($_POST['user_login']) && $_POST['user_login'] )
		$bb_login_error->add( 'user_login', __( 'User does not exist.' ) );
	else
		$bb_login_error->add( 'user_login', __( 'Enter a username or email address.' ) );
}

if ( !$bb_login_error->get_error_code() )
	$bb_login_error->add( 'password', __( 'Incorrect password.' ) );

// If trying to log in with email address, don't leak whether or not email address exists in the db
// strpos @ is not perfect, usernames can have @
if ( bb_get_option( 'email_login' ) && $bb_login_error->get_error_codes() && false !== strpos( $_POST['user_login'], '@' ) )
	$bb_login_error = new WP_Error( 'user_login', __( 'Username and Password do not match.' ) );

$user_login  = attribute_escape( sanitize_user( @$_POST['user_login'] ) );
$remember_checked = @$_POST['remember'] ? ' checked="checked"' : '';
$re = $redirect_to = attribute_escape( $re );

bb_load_template( 'login.php', array('user_exists', 'user_login', 'remember_checked', 'redirect_to', 're', 'bb_login_error') );
exit;

?>
