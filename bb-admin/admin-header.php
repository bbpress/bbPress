<?php
require('../bb-config.php');

if ( $current_user->user_type < 1 ) {
	header('Location: ' . get_option('uri') );
	exit();
}

?>