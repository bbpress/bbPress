<?php

/**
 * bbPress logging level constants - same as constants from BP_Log class
 */
define( 'BB_LOG_NONE',    0 );
define( 'BB_LOG_FAIL',    1 );
define( 'BB_LOG_ERROR',   2 );
define( 'BB_LOG_WARNING', 4 );
define( 'BB_LOG_NOTICE',  8 );
define( 'BB_LOG_DEBUG',   16 );

/**
 * Combination of all errors (excluding none and debug)
 */
define( 'BB_LOG_ALL', BB_LOG_FAIL + BB_LOG_ERROR + BB_LOG_WARNING + BB_LOG_NOTICE );

/**
 * Define BB_PATH as this files directory
 */
define( 'BB_PATH', dirname(__FILE__) . '/' );

/**
 * The bbPress includes path relative to BB_PATH
 */
define('BB_INC', 'bb-includes/');

// Initialise $bb object
$bb = new StdClass();

if ( file_exists( BB_PATH . 'bb-config.php') ) {
	
	// The config file resides in BB_PATH
	require_once( BB_PATH . 'bb-config.php');
	
	// Load bb-settings.php
	require_once( BB_PATH . 'bb-settings.php' );
	
} elseif ( file_exists( dirname(BB_PATH) . '/bb-config.php') ) {
	
	// The config file resides one level below BB_PATH
	require_once( dirname(BB_PATH) . '/bb-config.php' );
	
	// Load bb-settings.php
	require_once( BB_PATH . 'bb-settings.php' );
	
} elseif ( !defined('BB_INSTALLING') || !BB_INSTALLING ) {
	
	// The config file doesn't exist and we aren't on the installation page
	
	// Cut to the chase, go to the installer and use it to deal with errors
	$install_uri = preg_replace('|(/bb-admin)?/[^/]+?$|', '/', $_SERVER['PHP_SELF']) . 'bb-admin/install.php';
	header('Location: ' . $install_uri);
	die();
	
}
