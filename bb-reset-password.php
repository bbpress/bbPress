<?php
require('bb-config.php');

require_once( BBPATH . 'bb-includes/registration-functions.php');

$reset = false;

if ( $_POST ) :
	$username = user_sanitize  ( $_POST['username'] );
	if ( empty( $username ) )
		exit;
	bb_reset_email( $username );
endif;

if ( isset( $_GET['key'] ) ) :
	bb_reset_password( $_GET['key'] );
	$reset = true;
endif;

require( BBPATH . 'bb-templates/password-reset.php');
?>