<?php
require('./bb-load.php');

$ref = wp_get_referer();

$re = bb_get_option('uri');

if ( 0 === strpos($ref, bb_get_option( 'uri' )) ) {
	$re = $_POST['re'] ? $_POST['re'] : $_GET['re'];
	if ( 0 !== strpos($re, bb_get_option( 'uri' )) )
		$re = $ref . $re;
}

if ( 0 === strpos($re, bb_get_option( 'uri' ) . 'register.php') )
	$re = bb_get_option( 'uri' );

$re = clean_url( $re );

nocache_headers();

if ( isset( $_REQUEST['logout'] ) ) {
	bb_logout();
	wp_redirect( $re );
	exit;
}

if ( !bb_is_user_logged_in() && !$user = bb_login( @$_POST['user_login'], @$_POST['password'] ) ) {
	$user_exists = bb_user_exists( @$_POST['user_login'] );
	$user_login  = attribute_escape( bb_user_sanitize( @$_POST['user_login'] ) );
	$re = $redirect_to = attribute_escape( $re );
	bb_load_template( 'login.php', array('user_exists', 'user_login', 'redirect_to', 're') );
	exit;
}

// We already know it's safe from the above, but we might as well use this anyway.
bb_safe_redirect( $re );

?>
