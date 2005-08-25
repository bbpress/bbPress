<?php

if ( !(phpversion() >= '4.2') )
	die( 'Your server is running PHP version ' . phpversion() . ' but bbPress requires at least 4.2' );

if ( !extension_loaded('mysql') && !extension_loaded('mysqli') )
	die( 'Your PHP installation appears to be missing the MySQL which is required for bbPress.' );

// Turn register globals off
function bb_unregister_GLOBALS() {
	if ( !ini_get('register_globals') )
		return;

	if ( isset($_REQUEST['GLOBALS']) )
		die('GLOBALS overwrite attempt detected');

	// Variables that shouldn't be unset
	$noUnset = array('GLOBALS', '_GET', '_POST', '_COOKIE', '_REQUEST', '_SERVER', '_ENV', '_FILES', 'bb_table_prefix', 'bb');

	$input = array_merge($_GET, $_POST, $_COOKIE, $_SERVER, $_ENV, $_FILES, isset($_SESSION) && is_array($_SESSION) ? $_SESSION : array());
	foreach ( $input as $k => $v )
		if ( !in_array($k, $noUnset) && isset($GLOBALS[$k]) )
			unset($GLOBALS[$k]);
}

bb_unregister_GLOBALS();

function bb_timer_start() {
	global $bb_timestart;
	$mtime = explode(' ', microtime() );
	$bb_timestart = $mtime[1] + $mtime[0];
	return true;
}
bb_timer_start();

// Fix for IIS, which doesn't set REQUEST_URI
if ( empty( $_SERVER['REQUEST_URI'] ) ) {
	$_SERVER['REQUEST_URI'] = $_SERVER['SCRIPT_NAME']; // Does this work under CGI?

	// Append the query string if it exists and isn't null
	if (isset($_SERVER['QUERY_STRING']) && !empty($_SERVER['QUERY_STRING']))
		$_SERVER['REQUEST_URI'] .= '?' . $_SERVER['QUERY_STRING'];
}


error_reporting(E_ALL ^ E_NOTICE);

if ( extension_loaded('mysqli') )
	require( BBPATH . 'bb-includes/db-mysqli.php');
else
	require( BBPATH . 'bb-includes/db.php');

require( BBPATH . 'bb-includes/functions.php');
require( BBPATH . 'bb-includes/formatting-functions.php');
require( BBPATH . 'bb-includes/template-functions.php');
require( BBPATH . 'bb-includes/capabilities.php');
require( BBPATH . 'bb-includes/default-filters.php');

$bbdb->forums    = $bb_table_prefix . 'forums';
$bbdb->posts     = $bb_table_prefix . 'posts';
$bbdb->topics    = $bb_table_prefix . 'topics';
$bbdb->topicmeta = $bb_table_prefix . 'topicmeta';
$bbdb->users     = $bb_table_prefix . 'users';
$bbdb->usermeta  = $bb_table_prefix . 'usermeta';
$bbdb->tags      = $bb_table_prefix . 'tags';
$bbdb->tagged    = $bb_table_prefix . 'tagged';

$static_title = '';

$_GET    = bb_global_sanitize($_GET   );
$_POST   = bb_global_sanitize($_POST  );
$_COOKIE = bb_global_sanitize($_COOKIE);
$_SERVER = bb_global_sanitize($_SERVER);

$plugins = glob( BBPATH . 'bb-plugins/*.php');
if ( $plugins ) : foreach ( $plugins as $plugin ) :
	require($plugin);
endforeach; endif;
bb_do_action('bb_plugins_loaded', '');

if ( defined('CUSTOM_USER_TABLE') )
	$bbdb->users = CUSTOM_USER_TABLE;
if ( defined('CUSTOM_USER_META_TABLE') )
	$bbdb->usermeta = CUSTOM_USER_META_TABLE;

define('BBHASH', md5($bb_table_prefix) );

if ( !isset( $bb->usercookie ) )
	$bb->usercookie = 'bb_user_' . BBHASH;
if ( !isset( $bb->passcookie ) )
	$bb->passcookie = 'bb_pass_' . BBHASH;
if ( !isset( $bb->cookiepath ) )
	$bb->cookiepath = bb_get_option('path');
if ( !isset( $bb->tagpath ) )
	$bb->tagpath = $bb->path;

$bb_roles = new BB_Roles();
bb_do_action('bb_got_roles', '');

function bb_shutdown_action_hook() {
	bb_do_action('bb_shutdown', '');
}
register_shutdown_function('bb_shutdown_action_hook');

$bb_current_user = bb_current_user();

if ( $bb_current_user && !bb_current_user_can('read') )
	die("You've been blocked.  If you think a mistake has been made, contact this site's administrator.");

$page = bb_get_uri_page();

?>
