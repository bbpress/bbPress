<?php
require('bb-config.php');

require_once( BBPATH . 'bb-includes/registration-functions.php');

$user_login = $user_safe = $email = $url = $location = $interests = true;

if ($_POST) :
	$user_login = user_sanitize  ( $_POST['user_login'] );
	$email    = bb_verify_email( $_POST['email']    );
	
	$url       = bb_fix_link( $_POST['url'] );
	$url       = bb_specialchars( $url                , 1);
	$location  = bb_specialchars( $_POST['location']  , 1);
	$interests = bb_specialchars( $_POST['interests'] , 1);
	
	if ( empty($user_login) || bb_user_exists($user_login) )
		$user_safe = false;
	
	if ( $user_login && $user_safe && $email ) {
		bb_new_user( $user_login, $email, $url, $location, $interests );
		require( BBPATH . 'bb-templates/register-success.php');
		exit();	
	}
endif;

if ( isset( $_GET['user'] ) )
	$user_login = user_sanitize( $_GET['user'] ) ;
else
	$user_login = '';

require( BBPATH . 'bb-templates/register.php');
?>
