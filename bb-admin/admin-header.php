<?php
require('../bb-config.php');

if ( !$current_user ) {
	header('Location: ' . bb_get_option('uri') );
	exit();
}

nocache_headers();

?>
