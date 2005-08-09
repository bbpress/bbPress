<?php

if ( !(phpversion() >= '4.2') )
	die( 'Your server is running PHP version ' . phpversion() . ' but bbPress requires at least 4.2' );

if ( !extension_loaded('mysql') && !extension_loaded('mysqli') )
	die( 'Your PHP installation appears to be missing the MySQL which is required for bbPress.' );

// Turn register globals off
if ( ini_get('register_globals') ) {
	$superglobals = array($_SERVER, $_ENV, $_FILES, $_COOKIE, $_POST, $_GET);
	if ( isset($_SESSION) )
		array_unshift($superglobals, $_SESSION);

	foreach ( $superglobals as $superglobal ) {
		unset($superglobal['table_prefix'], $superglobal['bb']);
		foreach ( $superglobal as $global => $value )
			unset($GLOBALS[$global]);
	}
	unset($value, $global, $superglobal, $superglobals);
}

function bb_timer_start() {
	global $bb_timestart;
	$mtime = explode(' ', microtime() );
	$bb_timestart = $mtime[1] + $mtime[0];
	return true;
}
bb_timer_start();

error_reporting(E_ALL ^ E_NOTICE);

if ( extension_loaded('mysqli') )
	require( BBPATH . 'bb-includes/db-mysqli.php');
else
	require( BBPATH . 'bb-includes/db.php');

require( BBPATH . 'bb-includes/functions.php');
require( BBPATH . 'bb-includes/formatting-functions.php');
require( BBPATH . 'bb-includes/template-functions.php');
require( BBPATH . 'bb-includes/default-filters.php');

$bbdb->forums    = $table_prefix . 'forums';
$bbdb->posts     = $table_prefix . 'posts';
$bbdb->topics    = $table_prefix . 'topics';
$bbdb->topicmeta = $table_prefix . 'topicmeta';
$bbdb->users     = $table_prefix . 'users';
$bbdb->usermeta  = $table_prefix . 'usermeta';
$bbdb->tags      = $table_prefix . 'tags';
$bbdb->tagged    = $table_prefix . 'tagged';

$plugins = glob( BBPATH . 'bb-plugins/*.php');
if ( $plugins ) : foreach ( $plugins as $plugin ) :
	require($plugin);
endforeach; endif;

if ( defined('CUSTOM_USER_TABLE') )
	$bbdb->users = CUSTOM_USER_TABLE;
if ( defined('CUSTOM_USER_META_TABLE') )
	$bbdb->usermeta = CUSTOM_USER_META_TABLE;


define('BBHASH', md5($table_prefix) );

if ( !isset( $bb->usercookie ) )
	$bb->usercookie = 'bb_user_' . BBHASH;
if ( !isset( $bb->passcookie ) )
	$bb->passcookie = 'bb_pass_' . BBHASH;
if ( !isset( $bb->cookiepath ) )
	$bb->cookiepath = bb_get_option('path');
if ( !isset( $bb->tagpath ) )
	$bb->tagpath = $bb->path;

$static_title = '';

$_GET    = bb_global_sanitize($_GET   );
$_POST   = bb_global_sanitize($_POST  );
$_COOKIE = bb_global_sanitize($_COOKIE);
$_SERVER = bb_global_sanitize($_SERVER);

function bb_shutdown_action_hook() {
	bb_do_action('bb_shutdown', '');
}
register_shutdown_function('bb_shutdown_action_hook');

$current_user = bb_current_user();

?>
