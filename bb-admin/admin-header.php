<?php
require('../bb-config.php');

if ( $current_user->user_type < 1 ) {
	header('Location: ' . bb_get_option('uri') );
	exit();
}

// Never cache
header('Expires: Wed, 11 Jan 1984 05:00:00 GMT');
header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
header('Cache-Control: no-cache, must-revalidate');
header('Pragma: no-cache');

?>