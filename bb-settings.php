<?php

if ( version_compare(PHP_VERSION, '4.3', '<') )
	die(sprintf('Your server is running PHP version %s but bbPress requires at least 4.3', PHP_VERSION) );

if ( !$bb_table_prefix )
	die('You must specify a table prefix in your <code>bb-config.php</code> file.');

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

// Modify error reporting levels
error_reporting(E_ALL ^ E_NOTICE);

if ( !defined( 'BB_IS_ADMIN' ) )
	define( 'BB_IS_ADMIN', false );

// Define the include path
define('BBINC', 'bb-includes/');

// Load the database class
require( BBPATH . BBINC . 'db.php' );

// Define the language file directory
if ( !defined('BBLANGDIR') )
	define('BBLANGDIR', BBPATH . BBINC . 'languages/'); // absolute path with trailing slash

if ( !defined( 'BACKPRESS_PATH' ) )
	define( 'BACKPRESS_PATH', BBPATH . BBINC . 'backpress/' );

// Include functions
require( BACKPRESS_PATH . 'functions.core.php' );
require( BACKPRESS_PATH . 'functions.compat.php' );
require( BBPATH . BBINC . 'wp-functions.php');
require( BBPATH . BBINC . 'functions.php');
require( BBPATH . BBINC . 'classes.php');

// Plugin API
if ( !function_exists( 'add_filter' ) )
	require( BACKPRESS_PATH . 'functions.plugin-api.php' );

// Object Cache
if ( !class_exists( 'WP_Object_Cache' ) ) {
	require( BACKPRESS_PATH . 'class.wp-object-cache.php' );
	require( BACKPRESS_PATH . 'functions.wp-object-cache.php' );
}
if ( !isset($wp_object_cache) )
	$wp_object_cache = new WP_Object_Cache();

// Gettext
if ( defined('BBLANG') && '' != constant('BBLANG') ) {
	if ( !class_exists( 'gettext_reader' ) )
		require( BACKPRESS_PATH . 'class.gettext-reader.php' );
	if ( !class_exists( 'StreamReader' ) )
		require( BACKPRESS_PATH . 'class.streamreader.php' );
}

// WP_Error
if ( !class_exists( 'WP_Error' ) )
	require( BACKPRESS_PATH . 'class.wp-error.php' );

if ( !( defined('DB_NAME') || defined('WP_BB') && WP_BB ) ) {  // Don't include these when WP is running.
	require( BBPATH . BBINC . 'kses.php');
	require( BBPATH . BBINC . 'l10n.php');
}

if ( is_wp_error( $bbdb->set_prefix( $bb_table_prefix ) ) )
	die(__('Your table prefix may only contain letters, numbers and underscores.'));

if ( defined( 'BB_AWESOME_INCLUDE' ) && file_exists( BB_AWESOME_INCLUDE ) )
	require( BB_AWESOME_INCLUDE );

if ( !bb_is_installed() && ( !defined('BB_INSTALLING') || !BB_INSTALLING ) ) {
	$link = preg_replace('|(/bb-admin)?/[^/]+?$|', '/', $_SERVER['PHP_SELF']) . 'bb-admin/install.php';
	require( BBPATH . BBINC . 'pluggable.php');
	wp_redirect($link);
	die();
}

foreach ( array('use_cache', 'debug', 'static_title', 'load_options') as $o )
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

if ( defined('BB_DISABLE_BOZO_CHECKS') && !BB_DISABLE_BOZO_CHECKS )
	require( BBPATH . BBINC . 'bozo.php');

require( BBPATH . BBINC . 'default-filters.php');
require( BBPATH . BBINC . 'script-loader.php');

$bb_cache = new BB_Cache();

if ( $bb->load_options ) {
	$bbdb->hide_errors();
	bb_cache_all_options();
	$bbdb->show_errors();
}

if ( bb_get_option( 'akismet_key' ) )
	require( BBPATH . BBINC . 'akismet.php');

$_GET    = bb_global_sanitize($_GET   );
$_POST   = bb_global_sanitize($_POST  );
$_COOKIE = bb_global_sanitize($_COOKIE, false);
$_SERVER = bb_global_sanitize($_SERVER);

// Set the URI and derivitaves
if ( $bb->uri = bb_get_option('uri') ) {
	$bb->uri = rtrim($bb->uri, '/') . '/';
	
	// Not used in core anymore, only set here for plugin compatibility
	if ( preg_match( '@^(https?://[^/]+)((?:/.*)*/{1,1})$@i', $bb->uri, $matches ) ) {
		$bb->domain = $matches[1];
		$bb->path = $matches[2];
	}
} else {
	// Backwards compatibility
	// These were never set in the database
	if ( isset($bb->domain) ) {
		$bb->domain = rtrim( trim( $bb->domain ), '/' );
	}
	if ( isset($bb->path) ) {
		$bb->path = trim($bb->path);
		if ( $bb->path != '/' ) $bb->path = '/' . trim($bb->path, '/') . '/';
	}
	// We need both to build a uri
	if ( $bb->domain && $bb->path ) {
		$bb->uri = $bb->domain . $bb->path;
	}
}
// Die if no URI
if ( !$bb->uri && ( !defined('BB_INSTALLING') || !BB_INSTALLING ) ) {
	bb_die( __('Could not determine site URI') );
}

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

// Check for defined custom user tables
// Constants are taken before $bb before database settings
$bb->wp_table_prefix = bb_get_option('wp_table_prefix');
if ( defined('USER_BBDB_NAME') ) {
	$bb->user_bbdb_name = USER_BBDB_NAME;
} elseif ($bb->user_bbdb_name = bb_get_option('user_bbdb_name')) {
	define('USER_BBDB_NAME', $bb->user_bbdb_name);
}
if ( defined('USER_BBDB_USER') ) {
	$bb->user_bbdb_user = USER_BBDB_USER;
} elseif ($bb->user_bbdb_user = bb_get_option('user_bbdb_user')) {
	define('USER_BBDB_USER', $bb->user_bbdb_user);
}
if ( defined('USER_BBDB_PASSWORD') ) {
	$bb->user_bbdb_password = USER_BBDB_PASSWORD;
} elseif ($bb->user_bbdb_password = bb_get_option('user_bbdb_password')) {
	define('USER_BBDB_PASSWORD', $bb->user_bbdb_password);
}
if ( defined('USER_BBDB_HOST') ) {
	$bb->user_bbdb_host = USER_BBDB_HOST;
} elseif ($bb->user_bbdb_host = bb_get_option('user_bbdb_host')) {
	define('USER_BBDB_HOST', $bb->user_bbdb_host);
}
if ( defined('USER_BBDB_CHARSET') ) {
	$bb->user_bbdb_charset = USER_BBDB_CHARSET;
} elseif ($bb->user_bbdb_charset = bb_get_option('user_bbdb_charset')) {
	define('USER_BBDB_CHARSET', $bb->user_bbdb_charset);
}
if ( defined('CUSTOM_USER_TABLE') ) {
	$bb->custom_user_table = CUSTOM_USER_TABLE;
} elseif ($bb->custom_user_table = bb_get_option('custom_user_table')) {
	define('CUSTOM_USER_TABLE', $bb->custom_user_table);
}
if ( defined('CUSTOM_USER_META_TABLE') ) {
	$bb->custom_user_meta_table = CUSTOM_USER_META_TABLE;
} elseif ($bb->custom_user_meta_table = bb_get_option('custom_user_meta_table')) {
	define('CUSTOM_USER_META_TABLE', $bb->custom_user_meta_table);
}

if ( is_wp_error( $bbdb->set_prefix( $bb->wp_table_prefix, array('users', 'usermeta') ) ) )
	die(__('Your user table prefix may only contain letters, numbers and underscores.'));

// Set the user table's character set if defined
if ( defined('USER_BBDB_CHARSET') )
	$bbdb->user_charset = constant('USER_BBDB_CHARSET');

// Set the user table's custom name if defined
if ( defined('CUSTOM_USER_TABLE') )
	$bbdb->users = constant('CUSTOM_USER_TABLE');

// Set the usermeta table's custom name if defined
if ( defined('CUSTOM_USER_META_TABLE') )
	$bbdb->usermeta = constant('CUSTOM_USER_META_TABLE');

// Sort out cookies so they work with WordPress (if required)
// Note that database integration is no longer a pre-requisite for cookie integration
$bb->wp_siteurl = bb_get_option('wp_siteurl');
if ( $bb->wp_siteurl ) {
	$bb->wp_siteurl = rtrim($bb->wp_siteurl, '/') . '/';
}

$bb->wp_home = bb_get_option('wp_home');
if ( $bb->wp_home ) {
	$bb->wp_home = rtrim($bb->wp_home, '/') . '/';
}

$bb->wp_cookies_integrated = false;
$bb->cookiedomain = bb_get_option('cookiedomain');
if ( $bb->wp_siteurl && $bb->wp_home ) {
	if ( $bb->cookiedomain ) {
		$bb->wp_cookies_integrated = true;
	} else {
		$cookiedomain = bb_get_common_domains($bb->uri, $bb->wp_home);
		if ( bb_match_domains($bb->uri, $bb->wp_home) ) {
			$bb->cookiepath = bb_get_common_paths($bb->uri, $bb->wp_home);
			$bb->wp_cookies_integrated = true;
		} elseif ($cookiedomain && strpos($cookiedomain, '.') !== false) {
			$bb->cookiedomain = '.' . $cookiedomain;
			$bb->cookiepath = bb_get_common_paths($bb->uri, $bb->wp_home);
			$bb->wp_cookies_integrated = true;
		}
		unset($cookiedomain);
	}
}

define('BBHASH', $bb->wp_cookies_integrated ? md5(rtrim($bb->wp_siteurl, '/')) : md5(rtrim($bb->uri, '/')) );

// Deprecated setting
$bb->usercookie = bb_get_option('usercookie');
if ( !$bb->usercookie ) {
	$bb->usercookie = ( $bb->wp_cookies_integrated ? 'wordpressuser_' : 'bb_user_' ) . BBHASH;
}

// Deprecated setting
$bb->passcookie = bb_get_option('passcookie');
if ( !$bb->passcookie ) {
	$bb->passcookie = ( $bb->wp_cookies_integrated ? 'wordpresspass_' : 'bb_pass_' ) . BBHASH;
}

$bb->authcookie = bb_get_option('authcookie');
if ( !$bb->authcookie ) {
	$bb->authcookie = ($bb->wp_cookies_integrated ? 'wordpress_' : 'bbpress_') . BBHASH;
}

$bb->cookiepath = bb_get_option('cookiepath');
if ( !isset( $bb->cookiepath ) ) {
	$bb->cookiepath = $bb->wp_cookies_integrated ? preg_replace('|https?://[^/]+|i', '', $bb->wp_home ) : $bb->path;
}

$bb->sitecookiepath = bb_get_option('sitecookiepath');
if ( !isset( $bb->sitecookiepath ) ) {
	$bb->sitecookiepath = $bb->wp_cookies_integrated ? preg_replace('|https?://[^/]+|i', '', $bb->wp_siteurl ) : $bb->path;
}


/* BackPress */

// WP_Users
if ( !class_exists( 'WP_Users' ) ) {
	require( BACKPRESS_PATH . 'class.wp-users.php' );
	$wp_users_object = new WP_Users( &$bbdb );
}

if ( !class_exists( 'BP_Roles' ) )
	require( BACKPRESS_PATH . 'class.bp-roles.php' );

$wp_roles = new BP_Roles( &$bbdb );

// WP_User
if ( !class_exists( 'WP_User' ) )
	require( BACKPRESS_PATH . 'class.wp-user.php' );

// WP_Auth
if ( !class_exists( 'WP_Auth' ) ) {
	require( BACKPRESS_PATH . 'class.wp-auth.php' );
	$wp_auth_object = new WP_Auth( $bbdb, array(
		'domain' => $bb->cookiedomain,
		'path' => array( $bb->cookiepath, $bb->sitecookiepath ),
		'name' => $bb->authcookie
	) );
}
$bb_current_user =& $wp_auth_object->current;

// WP_Scripts
if ( !isset($wp_scripts) ) {
	if ( !class_exists( 'WP_Scripts' ) ) {
		require( BACKPRESS_PATH . 'class.wp-scripts.php' );
		require( BACKPRESS_PATH . 'functions.wp-scripts.php' );
	}
	$wp_scripts = new WP_Scripts( $bb->uri, bb_get_option( 'version' ) );
} else {
	bb_default_scripts( &$wp_scripts );
}

// WP_Taxonomy
if ( !class_exists( 'WP_Taxonomy' ) )
	require( BACKPRESS_PATH . 'class.wp-taxonomy.php' );
if ( !class_exists( 'BB_Taxonomy' ) )
	require( BBPATH . BBINC . 'class-bb-taxonomy.php' );
if ( !isset($wp_taxonomy_object) ) { // Clean slate
	$wp_taxonomy_object = new BB_Taxonomy( $bbdb );
} elseif ( !is_a($wp_taxonomy_object, 'BB_Taxonomy') ) { // exists, but it's not good enough, translate it
	$tax =& $wp_taxonomy_object->taxonomies; // preserve the references
	$wp_taxonomy_object = new BB_Taxonomy( $bbdb );
	$wp_taxonomy_object->taxonomies =& $tax;
	unset($tax);
}
$wp_taxonomy_object->register_taxonomy( 'bb_topic_tag', 'bb_topic', array( 'hierarchical' => false ) );

// Set the path to the tag pages
if ( !isset( $bb->tagpath ) )
	$bb->tagpath = $bb->path;

do_action( 'bb_options_loaded' );

// Load Plugins
if ( function_exists( 'glob' ) && is_callable( 'glob' ) && $_plugins_glob = glob(BBPLUGINDIR . '_*.php') )
	foreach ( $_plugins_glob as $_plugin )
		require($_plugin);
unset($_plugins_glob, $_plugin);
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

$bb_roles =& $wp_roles;
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
