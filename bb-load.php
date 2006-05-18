<?php

if ( !file_exists( dirname(__FILE__) . '/config.php') ) {
	if ( strstr( $_SERVER['PHP_SELF'], 'bb-admin') ) $path = '';
	else $path = 'bb-admin/';
    die("There doesn't seem to be a <code>config.php</code> file. I need this before we can get started. Open up <code>bb-config-sample.php</code>, fill in your details, and save it as <code>config.php</code>.");
}

require( dirname(__FILE__) . '/config.php');

?>