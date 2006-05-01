<?php
require('config.php');

require_once( BBPATH . 'bb-includes/registration-functions.php');

$reset = false;

if ( $_POST ) :
	$user_login = user_sanitize  ( $_POST['user_login'] );
	if ( empty( $user_login ) )
		exit;
	bb_reset_email( $user_login );
endif;

if ( isset( $_GET['key'] ) ) :
	bb_reset_password( $_GET['key'] );
	$reset = true;
endif;

require( BBPATH . 'bb-templates/password-reset.php');
?>
