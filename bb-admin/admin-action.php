<?php
require('../bb-load.php');

if ( !$bb_current_user ) {
	header('Location: ' . bb_get_option('uri') );
	exit();
}

nocache_headers();

?>
