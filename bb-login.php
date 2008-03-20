<?php
require('./bb-load.php');

$ref = wp_get_referer();
if ( !$re = $_POST['re'] ? $_POST['re'] : $_GET['re'] )
	$re = $ref;

$home_url = bb_get_option( 'uri' );

// Check if the redirect is not to a directory within our bbPress URI
if ( 0 !== strpos($re, $home_url) ) {
	// Check if the common domain is the same as the bbPress domain (yes, this excludes sub-domains)
	if (!bb_match_domains($re, $home_url)) {
		// Get the path and querystring of the redirect URI
		$re_path = preg_replace('@^https?://[^/]+(.*)$@i', '$1', $re);
		// Append it to the bbPress URI to create a new redirect location - (why? I don't know)
		$re = $home_url . ltrim( $re_path, '/' );
	}
}

if ( 0 === strpos($re, $home_url . 'register.php') )
	$re = $home_url;

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

// We already know it's safe from the above, but we might as well use this anyway.
bb_safe_redirect( $re );

?>
