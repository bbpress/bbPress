<?php

if ( !(phpversion() >= '4.2') )
	die(sprintf(__('Your server is running PHP version %s but bbPress requires at least 4.2'), phpversion()) );

if ( !extension_loaded('mysql') && !extension_loaded('mysqli') )
	die(__('Your PHP installation appears to be missing the MySQL which is required for bbPress.' ));

// Turn register globals off
function bb_unregister_GLOBALS() {
	if ( !ini_get('register_globals') )
		return;

	if ( isset($_REQUEST['GLOBALS']) )
		die(__('GLOBALS overwrite attempt detected'));

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

$is_IIS = strstr($_SERVER['SERVER_SOFTWARE'], 'Microsoft-IIS') ? 1 : 0;
// Fix for IIS, which doesn't set REQUEST_URI
if ( empty( $_SERVER['REQUEST_URI'] ) ) {
	$_SERVER['REQUEST_URI'] = $_SERVER['SCRIPT_NAME']; // Does this work under CGI?

	// Append the query string if it exists and isn't null
	if (isset($_SERVER['QUERY_STRING']) && !empty($_SERVER['QUERY_STRING']))
		$_SERVER['REQUEST_URI'] .= '?' . $_SERVER['QUERY_STRING'];
}


error_reporting(E_ALL ^ E_NOTICE);

define('BBINC', 'bb-includes');
if ( !defined('BBLANGDIR') )
	define('BBLANGDIR', BBINC . '/languages'); // no leading slash, no trailing slash
if ( !defined('BBPLUGINDIR') )
	define('BBPLUGINDIR', 'my-plugins');       // no leading slash, no trailing slash

if ( extension_loaded('mysqli') ) {
	require( BBPATH . BBINC . '/db-mysqli.php');
} else {
	require( BBPATH . BBINC . '/db.php');
}

$bbdb->forums    = $bb_table_prefix . 'forums';
$bbdb->posts     = $bb_table_prefix . 'posts';
$bbdb->topics    = $bb_table_prefix . 'topics';
$bbdb->topicmeta = $bb_table_prefix . 'topicmeta';
$bbdb->users     = ( $bb->wp_table_prefix ? $bb->wp_table_prefix : $bb_table_prefix ) . 'users';
$bbdb->usermeta  = ( $bb->wp_table_prefix ? $bb->wp_table_prefix : $bb_table_prefix ) . 'usermeta';
$bbdb->tags      = $bb_table_prefix . 'tags';
$bbdb->tagged    = $bb_table_prefix . 'tagged';

require( BBPATH . BBINC . '/functions.php');
require( BBPATH . BBINC . '/formatting-functions.php');
require( BBPATH . BBINC . '/template-functions.php');
require( BBPATH . BBINC . '/capabilities.php');
require( BBPATH . BBINC . '/cache.php');
require( BBPATH . BBINC . '/deprecated.php');
require( BBPATH . BBINC . '/wp-functions.php');
if ( defined('BBLANG') && '' != constant('BBLANG') ) {
	include_once(BBPATH . BBINC . '/streams.php');
	include_once(BBPATH . BBINC . '/gettext.php');
}
if ( !( defined('DB_NAME') || defined('WP_BB') && WP_BB ) ) {  // Don't include these when WP is running.
	require( BBPATH . BBINC . '/kses.php');
	require( BBPATH . BBINC . '/l10n.php');
}
require( BBPATH . BBINC . '/bozo.php');
require( BBPATH . BBINC . '/akismet.php');
require( BBPATH . BBINC . '/default-filters.php');
require( BBPATH . BBINC . '/script-loader.php');
require( BBPATH . BBINC . '/compat.php');

$bbdb->hide_errors();
if ( !$bbdb->query("SELECT * FROM $bbdb->forums LIMIT 1") && !strstr( $_SERVER['PHP_SELF'], 'install.php' ) )
	die(sprintf(__('Does&#8217;t look like you&#8217;ve installed bbPress yet, <a href="%s">go here</a>.'), 'bb-admin/install.php'));
$bbdb->show_errors();

$static_title = '';

$_GET    = bb_global_sanitize($_GET   );
$_POST   = bb_global_sanitize($_POST  );
$_COOKIE = bb_global_sanitize($_COOKIE, false);
$_SERVER = bb_global_sanitize($_SERVER);

$plugins = glob( BBPATH . BBPLUGINDIR . '/*.php');
if ( $plugins ) : foreach ( $plugins as $plugin ) :
	require($plugin);
endforeach; endif;
do_action('bb_plugins_loaded', '');

require( BBPATH . BBINC . '/pluggable.php');

if ( defined('CUSTOM_USER_TABLE') )
	$bbdb->users = CUSTOM_USER_TABLE;
if ( defined('CUSTOM_USER_META_TABLE') )
	$bbdb->usermeta = CUSTOM_USER_META_TABLE;

define('BBHASH', $bb->wp_siteurl ? md5($bb->wp_siteurl) : md5($bb_table_prefix) );

if ( !isset( $bb->usercookie ) )
	$bb->usercookie = ( $bb->wp_table_prefix ? 'wordpressuser_' : 'bb_user_' ) . BBHASH;
if ( !isset( $bb->passcookie ) )
	$bb->passcookie = ( $bb->wp_table_prefix ? 'wordpresspass_' : 'bb_pass_' ) . BBHASH;
if ( !isset( $bb->cookiepath ) )
	$bb->cookiepath = $bb->wp_home ? preg_replace('|https?://[^/]+|i', '', "$bb->wp_home/" ) : bb_get_option('path');
if ( !isset( $bb->sitecookiepath ) )
	$bb->sitecookiepath = $bb->wp_siteurl ? preg_replace('|https?://[^/]+|i', '', "$bb->wp_site/" ) : bb_get_option('path');
if ( !isset( $bb->tagpath ) )
	$bb->tagpath = $bb->path;

$bb_cache = new BB_Cache();

// Load the default text localization domain.
load_default_textdomain();

// Pull in locale data after loading text domain.
require_once(BBPATH . BBINC . '/locale.php');
$bb_locale = new BB_Locale();

$bb_roles  = new BB_Roles();
do_action('bb_got_roles', '');

function bb_shutdown_action_hook() {
	do_action('bb_shutdown', '');
}
register_shutdown_function('bb_shutdown_action_hook');

bb_current_user();

do_action('bb_init', '');

if ( bb_is_user_logged_in() && bb_has_broken_pass() )
	bb_block_current_user();
if ( bb_is_user_logged_in() && !bb_current_user_can('read') )
	bb_log_current_nocaps();

$page = bb_get_uri_page();
?>
