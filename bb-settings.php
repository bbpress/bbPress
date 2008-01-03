<?php

if ( phpversion() < '4.2' )
	die(sprintf('Your server is running PHP version %s but bbPress requires at least 4.2', phpversion()) );

if ( !$bb_table_prefix )
	die('You must specify a table prefix in your <code>config.php</code> file.');

if ( !defined('BBPATH') )
	die('This file cannot be called directly.');

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
		if ( !in_array($k, $noUnset) && isset($GLOBALS[$k]) ) {
			$GLOBALS[$k] = NULL;
			unset($GLOBALS[$k]);
		}
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

if ( isset($bb->uri) ) {
	$bb->domain = preg_replace('|(?<!/)/(?!/).*$|', '', $bb->uri);
	$bb->path = substr($bb->uri, strlen($bb->domain));
} elseif ( isset($bb->path) && isset($bb->domain) ) {
	$bb->domain = rtrim($bb->domain, '/');
	$bb->path = '/' . ltrim($bb->path, '/');
} else {
	bb_die( '<code>$bb->uri</cade> must be set in your <code>config.php</code> file.' );
}

foreach ( array('wp_site_url', 'wp_home', 'path') as $p )
	if ( isset($bb->$p) && $bb->$p )
		$bb->$p = rtrim($bb->$p, '/');
unset($p);

$bb->path = "$bb->path/";
$bb->uri = $bb->domain . $bb->path;

define('BBINC', 'bb-includes/');
if ( !defined('BBLANGDIR') )
	define('BBLANGDIR', BBPATH . BBINC . 'languages/'); // absolute path with trailing slash
if ( !defined('BBPLUGINDIR') )
	define('BBPLUGINDIR', BBPATH . 'my-plugins/');
if ( !defined('BBPLUGINURL') )
	define('BBPLUGINURL', $bb->uri . 'my-plugins/');
if ( !defined('BBTHEMEDIR') )
	define('BBTHEMEDIR', BBPATH . 'my-templates/');
if ( !defined('BBTHEMEURL') )
	define('BBTHEMEURL', $bb->uri . 'my-templates/');
if ( !defined('BBDEFAULTTHEMEDIR') )
	define('BBDEFAULTTHEMEDIR', BBPATH . 'bb-templates/kakumei/');
if ( !defined('BBDEFAULTTHEMEURL') )
	define('BBDEFAULTTHEMEURL', $bb->uri . 'bb-templates/kakumei/');

require( BBPATH . BBINC . 'db-base.php');
if ( extension_loaded('mysql') ) {
	require( BBPATH . BBINC . 'db.php');
} elseif ( extension_loaded('mysqli') ) {
	require( BBPATH . BBINC . 'db-mysqli.php');
} else {
	die('Your PHP installation appears to be missing the MySQL which is required for bbPress.');
}

require( BBPATH . BBINC . 'compat.php');
require( BBPATH . BBINC . 'wp-functions.php');
require( BBPATH . BBINC . 'functions.php');
require( BBPATH . BBINC . 'wp-classes.php');
require( BBPATH . BBINC . 'classes.php');

if ( is_wp_error( $bbdb->set_prefix( $bb_table_prefix ) ) )
	die('Your table prefix may only contain letters, numbers and underscores.');

foreach ( array('use_cache', 'secret', 'debug', 'wp_table_prefix', 'wp_home', 'wp_siteurl', 'cookiedomain', 'static_title', 'load_options', 'akismet_key') as $o )
	if ( !isset($bb->$o) )
		$bb->$o = false;
unset($o);

if ( defined('BB_INSTALLING') && BB_INSTALLING )
foreach ( array('active_plugins') as $i )
	$bb->$i = false;
unset($i);

require( BBPATH . BBINC . 'formatting-functions.php');
require( BBPATH . BBINC . 'template-functions.php');
require( BBPATH . BBINC . 'capabilities.php');
require( BBPATH . BBINC . 'cache.php');
require( BBPATH . BBINC . 'deprecated.php');
if ( defined('BBLANG') && '' != constant('BBLANG') ) {
	include_once(BBPATH . BBINC . 'streams.php');
	include_once(BBPATH . BBINC . 'gettext.php');
}
if ( !( defined('DB_NAME') || defined('WP_BB') && WP_BB ) ) {  // Don't include these when WP is running.
	require( BBPATH . BBINC . 'kses.php');
	require( BBPATH . BBINC . 'l10n.php');
}
require( BBPATH . BBINC . 'bozo.php');
require( BBPATH . BBINC . 'akismet.php');
require( BBPATH . BBINC . 'default-filters.php');
require( BBPATH . BBINC . 'script-loader.php');

if ( !bb_is_installed() && false === strpos($_SERVER['PHP_SELF'], 'install.php') && !defined('BB_INSTALLING') )
	die(sprintf(__('Doesn&#8217;t look like you&#8217;ve installed bbPress yet, <a href="%s">go here</a>.'), 'bb-admin/install.php'));

$bb_cache = new BB_Cache();

if ( $bb->load_options ) {
	$bbdb->hide_errors();
	bb_cache_all_options();
	$bbdb->show_errors();
}

$_GET    = bb_global_sanitize($_GET   );
$_POST   = bb_global_sanitize($_POST  );
$_COOKIE = bb_global_sanitize($_COOKIE, false);
$_SERVER = bb_global_sanitize($_SERVER);

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
	$bb->cookiepath = $bb->wp_home ? preg_replace('|https?://[^/]+|i', '', $bb->wp_home . '/' ) : $bb->path;
if ( !isset( $bb->sitecookiepath ) )
	$bb->sitecookiepath = $bb->wp_siteurl ? preg_replace('|https?://[^/]+|i', '', $bb->wp_siteurl . '/' ) : $bb->path;
if ( !isset( $bb->tagpath ) )
	$bb->tagpath = $bb->path;

if ( is_callable( 'glob' ) )
	foreach ( glob(BBPLUGINDIR . '_*.php') as $_plugin )
		require($_plugin);
unset($_plugin);
do_action( 'bb_underscore_plugins_loaded' );

if ( $plugins = bb_get_option( 'active_plugins' ) )
	foreach ( (array) $plugins as $plugin )
		if ( file_exists(BBPLUGINDIR . $plugin) )
			require( BBPLUGINDIR . $plugin );
do_action( 'bb_plugins_loaded' );
unset($plugins, $plugin);

require( BBPATH . BBINC . 'pluggable.php');

// Load the default text localization domain.
load_default_textdomain();

// Pull in locale data after loading text domain.
require_once(BBPATH . BBINC . 'locale.php');
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

$page = bb_get_uri_page();

bb_send_headers();

?>
