<?php

// Define BBPATH as this files directory
define( 'BBPATH', dirname(__FILE__) . '/' );

// Initialise $bb object
$bb = new StdClass();

if ( file_exists( BBPATH . 'bb-config.php') ) {
	
	// The config file resides in BBPATH
	require_once( BBPATH . 'bb-config.php');
	
} elseif ( file_exists( dirname(BBPATH) . '/bb-config.php') ) {
	
	// The config file resides one level below BBPATH
	require_once( dirname(BBPATH) . '/bb-config.php' );
	
} elseif ( strpos($_SERVER['PHP_SELF'], 'install.php') === false ) {
	
	// The config file doesn't exist and we aren't on the installation page
	
	// We only need all these to make wp_redirect work
	require_once(BBPATH . 'bb-includes/wp-functions.php' );
	require_once(BBPATH . 'bb-includes/pluggable.php' );
	require_once(BBPATH . 'bb-includes/kses.php' );
	
	// Go to the installer
	$install_uri = preg_replace('|(/bb-admin)?/[^/]+?$|', '/', $_SERVER['PHP_SELF']) . 'bb-admin/install.php';
	wp_redirect($install_uri);
	
}
?>
