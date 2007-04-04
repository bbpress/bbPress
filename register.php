<?php
require('./bb-load.php');

require_once( BBPATH . BBINC . 'registration-functions.php');

$profile_info_keys = get_profile_info_keys();

$user_login = $user_safe = true;

if ($_POST) :
	$user_login = bb_user_sanitize  ( $_POST['user_login'], true );
	$user_email = bb_verify_email( $_POST['user_email'] );
	$user_url   = bb_fix_link( $_POST['user_url'] );

	foreach ( $profile_info_keys as $key => $label ) :
		if ( is_string($$key) ) :
			$$key = attribute_escape( $$key );
		elseif ( is_null($$key) ) :
			$$key = attribute_escape( $_POST[$key] );
		endif;
		if ( !$$key && $label[0] == 1 ) :
			$bad_input = true;
			$$key = false;
		endif;
	endforeach;

	if ( empty($user_login) || bb_user_exists($user_login) )
		$user_safe = false;
	
	if ( $user_login && $user_safe && $user_email && !$bad_input) :
		$user_id = bb_new_user( $user_login, $user_email, $user_url );
		foreach( $profile_info_keys as $key => $label )
			if ( strpos($key, 'user_') !== 0 && $$key !== '' )
				bb_update_usermeta( $user_id, $key, $$key );
		do_action('register_user', $user_id);

		bb_load_template( 'register-success.php' );
		exit();	
	endif;
endif;

if ( isset( $_GET['user'] ) )
	$user_login = bb_user_sanitize( $_GET['user'], true ) ;
elseif ( isset( $_POST['user_login'] ) && !is_string($user_login) )
	$user_login = '';

$_globals = array('profile_info_keys', 'user_safe', 'user_login', 'user_email', 'user_url', 'bad_input');
$_globals = array_merge($_globals, array_keys($profile_info_keys));

bb_load_template( 'register.php', $_globals );

?>
