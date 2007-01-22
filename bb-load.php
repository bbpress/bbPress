<?php

define( 'BBPATH', dirname(__FILE__) . '/' );

if ( !file_exists( BBPATH . 'config.php') ) {
	if ( !file_exists( dirname(BBPATH) . '/config.php') )
		die("There doesn't seem to be a <code>config.php</code> file. I need this before we can get started. Open up <code>config-sample.php</code>, fill in your details, and save it as <code>config.php</code>.");
	require_once( dirname(BBPATH) . '/config.php' );
} else {
	require_once( BBPATH . 'config.php');
}

?>
