<?php
require('./bb-load.php');

require_once( BB_PATH . BB_INC . 'registration-functions.php');

$reset = false;

if ( $_POST ) :
	$user_login = sanitize_user  ( $_POST['user_login'] );
	if ( empty( $user_login ) )
		exit;
	bb_reset_email( $user_login );
endif;

if ( isset( $_GET['key'] ) ) :
	bb_reset_password( $_GET['key'] );
	$reset = true;
endif;

bb_load_template( 'password-reset.php', array('reset', 'user_login', 'reset') );
?>
