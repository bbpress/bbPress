<?php
require('bb-config.php');

require_once( BBPATH . 'bb-includes/registration-functions.php');

$username = $user_safe = $email = $website = $location = $interests = true;

if ($_POST) :
	$username = user_sanitize  ( $_POST['username'] );
	$email    = bb_verify_email( $_POST['email']    );
	
	$website   = bb_fix_link( $_POST['website'] );
	$website   = bb_specialchars( $website            , 1);
	$location  = bb_specialchars( $_POST['location']  , 1);
	$interests = bb_specialchars( $_POST['interests'] , 1);
	
	if ( empty($username) || bb_user_exists($username) )
		$user_safe = false;
	
	if ( $username && $user_safe && $email ) {
		bb_new_user( $username, $email, $website, $location, $interests );
		require( BBPATH . 'bb-templates/register-success.php');
		exit();	
	}
endif;

if ( isset( $_GET['user'] ) )
	$username = user_sanitize( $_GET['user'] ) ;
else
	$username = '';

require( BBPATH . 'bb-templates/register.php');
?>