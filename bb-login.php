<?php
require('./bb-load.php');

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

if ( !bb_is_user_logged_in() && !$user = bb_login( @$_POST['user_login'], @$_POST['password'], @$_POST['remember'] ) ) {
	$user_exists = bb_get_user( @$_POST['user_login'] );
	$user_login  = attribute_escape( sanitize_user( @$_POST['user_login'] ) );
	$remember_checked = @$_POST['remember'] ? ' checked="checked"' : '';
	$re = $redirect_to = attribute_escape( $re );
	bb_load_template( 'login.php', array('user_exists', 'user_login', 'remember_checked', 'redirect_to', 're') );
	exit;
}

bb_safe_redirect( $re );

?>
