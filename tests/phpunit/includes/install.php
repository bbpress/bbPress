<?php

/**
 * Installs bbPress for the purpose of the unit-tests
 */

echo "Setting up WordPress test environment...\n";

error_reporting( E_ALL & ~E_DEPRECATED & ~E_STRICT );

$config_file_path = $argv[1];
$tests_dir_path   = $argv[2];
$multisite        = ! empty( $argv[3] );

// Pull in the WordPress core test suite
require_once( $config_file_path );
require_once( $tests_dir_path . '/includes/functions.php' );

/**
 * Include bbPress via `plugins_loaded` event
 *
 * This could maybe me earlier on `muplugins_loaded` but time will tell what
 * works best for us.
 */
function _load_bbpress() {
	echo "Loading bbPress via `/src/bbpress.php`...\n";
	require dirname( dirname( dirname( dirname( __FILE__ ) ) ) ) . '/src/bbpress.php';
}
tests_add_filter( 'plugins_loaded', '_load_bbpress' );

// Override some fussy global values
$_SERVER['SERVER_PROTOCOL'] = 'HTTP/1.1';
$_SERVER['HTTP_HOST']       = WP_TESTS_DOMAIN;
$_SERVER['REMOTE_ADDR']     = '0.0.0.0';

// Fix PHP identity crisis
$PHP_SELF = $GLOBALS['PHP_SELF'] = $_SERVER['PHP_SELF'] = '/index.php';

// Include WordPress
echo "Loading WordPress via `wp-settings.php`...\n";
require_once( ABSPATH . '/wp-settings.php' );

// Fix fussy database settings
$wpdb->query( 'SET storage_engine = INNODB' );
$wpdb->select( DB_NAME, $wpdb->dbh );

// Install bbPress
echo "Installing bbPress...\n";
bbp_version_bump();

echo "Creating initial bbPress content...\n";
bbp_create_initial_content();
