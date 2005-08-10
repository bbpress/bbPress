<?php
require('../bb-config.php');

if ( $current_user->user_type < 2 ) {
	header('Location: ' . bb_get_option('uri') );
	exit();
}

nocache_headers();

?>