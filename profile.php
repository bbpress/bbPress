<?php
require_once('bb-config.php');

$user_id = (int) $_GET['id'];

$user = bb_get_user( $user_id );

if ( !$user )
	die('User not found.');

$ts = strtotime( $user->user_regdate );

require('bb-templates/profile.php');

?>