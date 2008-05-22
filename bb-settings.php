<?php
if ( version_compare(PHP_VERSION, '4.3', '<') )
	die(sprintf('Your server is running PHP version %s but bbPress requires at least 4.3', PHP_VERSION) );

if ( !$bb_table_prefix )
	die('You must specify a table prefix in your <code>bb-config.php</code> file.');

if ( !defined('BB_PATH') )
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
define('BB_INC', 'bb-includes/');

if ( !defined( 'BACKPRESS_PATH' ) )
	define( 'BACKPRESS_PATH', BB_PATH . BB_INC . 'backpress/' );
require( BACKPRESS_PATH . 'functions.core.php' );
require( BACKPRESS_PATH . 'functions.compat.php' );

// Define the full path to the database class
if ( !defined('BB_DATABASE_CLASS_INCLUDE') )
	define('BB_DATABASE_CLASS_INCLUDE', BACKPRESS_PATH . 'class.bpdb-multi.php' );
// Load the database class
require( BB_DATABASE_CLASS_INCLUDE );

if ( !defined( 'BB_DATABASE_CLASS' ) )
	define( 'BB_DATABASE_CLASS', 'BPDB_Multi' );
$bbdb_class = BB_DATABASE_CLASS;

$bbdb =& new $bbdb_class( array(
	'name' => BBDB_NAME,
	'user' => BBDB_USER,
	'password' => BBDB_PASSWORD,
	'host' => BBDB_HOST,
	'charset' => defined( 'BBDB_CHARSET' ) ? BBDB_CHARSET : false,
	'collate' => defined( 'BBDB_COLLATE' ) ? BBDB_COLLATE : false
) );
$bbdb->tables = array(
	'forums'             => false,
	'meta'               => false,
	'posts'              => false,
	'tagged'             => false, // Deprecated
	'tags'               => false, // Deprecated
	'terms'              => false,
	'term_relationships' => false,
	'term_taxonomy'      => false,
	'topics'             => false,
	'topicmeta'          => false, // Deprecated
	'users'              => false,
	'usermeta'           => false
);
unset($bbdb_class);

// Define the language file directory
if ( !defined('BB_LANG_DIR') )
	if ( defined('BBLANGDIR') ) // User has set old constant
		// TODO: Completely remove old constants on version 1.0
		define('BB_LANG_DIR', BBLANGDIR);
	else
		define('BB_LANG_DIR', BB_PATH . BB_INC . 'languages/'); // absolute path with trailing slash

// Include functions
require( BB_PATH . BB_INC . 'wp-functions.php');
require( BB_PATH . BB_INC . 'functions.php');
require( BB_PATH . BB_INC . 'classes.php');

// Plugin API
if ( !function_exists( 'add_filter' ) )
	require( BACKPRESS_PATH . 'functions.plugin-api.php' );

// Object Cache
if ( !class_exists( 'WP_Object_Cache' ) ) {
	require( BACKPRESS_PATH . 'class.wp-object-cache.php' );
	require( BACKPRESS_PATH . 'functions.wp-object-cache.php' );
}
if ( !isset($wp_object_cache) )
	wp_cache_init();

// Gettext
if ( !defined('BB_LANG') && defined('BBLANG') && '' != BBLANG ) // User has set old constant
	// TODO: Completely remove old constants on version 1.0
	define('BB_LANG', BBLANG);
if ( defined('BB_LANG') && '' != BB_LANG ) {
	if ( !class_exists( 'gettext_reader' ) )
		require( BACKPRESS_PATH . 'class.gettext-reader.php' );
	if ( !class_exists( 'StreamReader' ) )
		require( BACKPRESS_PATH . 'class.streamreader.php' );
}

// WP_Error
if ( !class_exists( 'WP_Error' ) )
	require( BACKPRESS_PATH . 'class.wp-error.php' );

// Is WordPress loaded
if ( !defined('BB_IS_WP_LOADED') )
	define('BB_IS_WP_LOADED', defined('DB_NAME'));

// Only load these if WordPress isn't loaded
if ( !BB_IS_WP_LOADED ) {
	require( BACKPRESS_PATH . 'functions.kses.php');
	require( BB_PATH . BB_INC . 'l10n.php');
}

if ( is_wp_error( $bbdb->set_prefix( $bb_table_prefix ) ) )
	die(__('Your table prefix may only contain letters, numbers and underscores.'));

if ( defined( 'BB_AWESOME_INCLUDE' ) && file_exists( BB_AWESOME_INCLUDE ) )
	require( BB_AWESOME_INCLUDE );

if ( !bb_is_installed() && ( !defined('BB_INSTALLING') || !BB_INSTALLING ) ) {
	$link = preg_replace('|(/bb-admin)?/[^/]+?$|', '/', $_SERVER['PHP_SELF']) . 'bb-admin/install.php';
	require( BB_PATH . BB_INC . 'pluggable.php');
	wp_redirect($link);
	die();
}

// Make sure the new meta table exists - very ugly
// TODO: consider seperating into external upgrade script for 1.0
$bbdb->hide_errors();
if ( !bb_get_option_from_db( 'bb_db_version' ) ) {
	$meta_exists = $bbdb->query("SELECT * FROM $bbdb->meta LIMIT 1");
	if (!$meta_exists) {
		$topicmeta_exists = $bbdb->query("SELECT * FROM $bbdb->topicmeta LIMIT 1");
		if ($topicmeta_exists) {
			require('bb-admin/upgrade-schema.php');
			// Create the meta table
			$bbdb->query($bb_queries['meta']);
			// Copy options
			$bbdb->query("INSERT INTO `$bbdb->meta` (`meta_key`, `meta_value`) SELECT `meta_key`, `meta_value` FROM `$bbdb->topicmeta` WHERE `topic_id` = 0;");
			// Copy topic meta
			$bbdb->query("INSERT INTO `$bbdb->meta` (`object_id`, `meta_key`, `meta_value`) SELECT `topic_id`, `meta_key`, `meta_value` FROM `$bbdb->topicmeta` WHERE `topic_id` != 0;");
			// Entries with an object_id are topic meta at this stage
			$bbdb->query("UPDATE `$bbdb->meta` SET `object_type` = 'bb_topic' WHERE `object_id` != 0");
		}
		unset($topicmeta_exists);
	}
	unset($meta_exists);
}
$bbdb->show_errors();

foreach ( array('use_cache' => false, 'debug' => false, 'static_title' => false, 'load_options' => true) as $o => $oo)
	if ( !isset($bb->$o) )
		$bb->$o = $oo;
unset($o, $oo);

if ( defined('BB_INSTALLING') && BB_INSTALLING )
foreach ( array('active_plugins') as $i )
	$bb->$i = false;
unset($i);

require( BB_PATH . BB_INC . 'formatting-functions.php');
require( BB_PATH . BB_INC . 'template-functions.php');
require( BB_PATH . BB_INC . 'capabilities.php');
require( BB_PATH . BB_INC . 'cache.php');
require( BB_PATH . BB_INC . 'deprecated.php');

$bb_cache = new BB_Cache();

if ( $bb->load_options ) {
	$bbdb->hide_errors();
	bb_cache_all_options();
	$bbdb->show_errors();
}

require( BB_PATH . BB_INC . 'default-filters.php');
require( BB_PATH . BB_INC . 'script-loader.php');

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
	unset($matches);
} else {
	// Backwards compatibility
	// These were never set in the database
	// TODO: Completely remove old constants on version 1.0
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

define('BB_CORE_PLUGIN_DIR', BB_PATH . 'bb-plugins/');
define('BB_CORE_PLUGIN_URL', $bb->uri . 'bb-plugins/');
define('BB_CORE_THEME_DIR', BB_PATH . 'bb-templates/');
define('BB_CORE_THEME_URL', $bb->uri . 'bb-templates/');
define('BB_DEFAULT_THEME', 'core#kakumei');
define('BB_DEFAULT_THEME_DIR', BB_CORE_THEME_DIR . 'kakumei/');
define('BB_DEFAULT_THEME_URL', BB_CORE_THEME_URL . 'kakumei/');

if ( !defined('BB_PLUGIN_DIR') )
	if ( defined('BBPLUGINDIR') ) // User has set old constant
		// TODO: Completely remove old constants on version 1.0
		define('BB_PLUGIN_DIR', BBPLUGINDIR);
	else
		define('BB_PLUGIN_DIR', BB_PATH . 'my-plugins/');

if ( !defined('BB_PLUGIN_URL') )
	if ( defined('BBPLUGINURL') ) // User has set old constant
		// TODO: Completely remove old constants on version 1.0
		define('BB_PLUGIN_URL', BBPLUGINURL);
	else
		define('BB_PLUGIN_URL', $bb->uri . 'my-plugins/');

if ( !defined('BB_THEME_DIR') )
	if ( defined('BBTHEMEDIR') ) // User has set old constant
		// TODO: Completely remove old constants on version 1.0
		define('BB_THEME_DIR', BBTHEMEDIR);
	else
		define('BB_THEME_DIR', BB_PATH . 'my-templates/');

if ( !defined('BB_THEME_URL') )
	if ( defined('BBTHEMEURL') ) // User has set old constant
		// TODO: Completely remove old constants on version 1.0
		define('BB_THEME_URL', BBTHEMEURL);
	else
		define('BB_THEME_URL', $bb->uri . 'my-templates/');


// Check for older style custom user table
// TODO: Completely remove old constants on version 1.0
if ( !isset($bb->custom_tables['users']) ) { // Don't stomp new setting style
	if ( !$bb->custom_user_table = bb_get_option('custom_user_table') ) // Maybe get from database or old config setting
		if ( defined('CUSTOM_USER_TABLE') ) // Maybe user has set old constant
			$bb->custom_user_table = CUSTOM_USER_TABLE;
	if ( $bb->custom_user_table ) {
		if ( !isset($bb->custom_tables) )
			$bb->custom_tables = array();
		$bb->custom_tables['users'] = $bb->custom_user_table;
	}
}

// Check for older style custom user meta table
// TODO: Completely remove old constants on version 1.0
if ( !isset($bb->custom_tables['usermeta']) ) { // Don't stomp new setting style
	if ( !$bb->custom_user_meta_table = bb_get_option('custom_user_meta_table') ) // Maybe get from database or old config setting
		if ( defined('CUSTOM_USER_META_TABLE') ) // Maybe user has set old constant
			$bb->custom_user_meta_table = CUSTOM_USER_META_TABLE;
	if ( $bb->custom_user_meta_table ) {
		if ( !isset($bb->custom_tables) )
			$bb->custom_tables = array();
		$bb->custom_tables['usermeta'] = $bb->custom_user_meta_table;
	}
}

// Check for older style wp_table_prefix
// TODO: Completely remove old constants on version 1.0
if ( $bb->wp_table_prefix = bb_get_option('wp_table_prefix') ) { // User has set old constant
	if ( !isset($bb->custom_tables) ) {
		$bb->custom_tables = array(
			'users'    => $bb->wp_table_prefix . 'users',
			'usermeta' => $bb->wp_table_prefix . 'usermeta'
		);
	} else {
		if ( !isset($bb->custom_tables['users']) ) // Don't stomp new setting style
			$bb->custom_tables['users'] = $bb->wp_table_prefix . 'users';
		if ( !isset($bb->custom_tables['usermeta']) )
			$bb->custom_tables['usermeta'] = $bb->wp_table_prefix . 'usermeta';
	}
}

// Check for older style user database
// TODO: Completely remove old constants on version 1.0
if ( !isset($bb->custom_databases) )
	$bb->custom_databases = array();
if ( !isset($bb->custom_databases) || ( isset($bb->custom_databases) && !isset($bb->custom_databases['user']) ) ) {
	if ( !$bb->user_bbdb_name = bb_get_option('user_bbdb_name') )
		if ( defined('USER_BBDB_NAME') ) // User has set old constant
			$bb->user_bbdb_name = USER_BBDB_NAME;
	if ( $bb->user_bbdb_name )
		$bb->custom_databases['user']['name'] = $bb->user_bbdb_name;

	if ( !$bb->user_bbdb_user = bb_get_option('user_bbdb_user') )
		if ( defined('USER_BBDB_USER') ) // User has set old constant
			$bb->user_bbdb_user = USER_BBDB_USER;
	if ( $bb->user_bbdb_user )
		$bb->custom_databases['user']['user'] = $bb->user_bbdb_user;

	if ( !$bb->user_bbdb_password = bb_get_option('user_bbdb_password') )
		if ( defined('USER_BBDB_PASSWORD') ) // User has set old constant
			$bb->user_bbdb_password = USER_BBDB_PASSWORD;
	if ( $bb->user_bbdb_password )
		$bb->custom_databases['user']['password'] = $bb->user_bbdb_password;

	if ( !$bb->user_bbdb_host = bb_get_option('user_bbdb_host') )
		if ( defined('USER_BBDB_HOST') ) // User has set old constant
			$bb->user_bbdb_host = USER_BBDB_HOST;
	if ( $bb->user_bbdb_host )
		$bb->custom_databases['user']['host'] = $bb->user_bbdb_host;

	if ( !$bb->user_bbdb_charset = bb_get_option('user_bbdb_charset') )
		if ( defined('USER_BBDB_CHARSET') ) // User has set old constant
			$bb->user_bbdb_charset = USER_BBDB_CHARSET;
	if ( $bb->user_bbdb_charset )
		$bb->custom_databases['user']['charset'] = $bb->user_bbdb_charset;

	if ( !$bb->user_bbdb_collate = bb_get_option('user_bbdb_collate') )
		if ( defined('USER_BBDB_COLLATE') ) // User has set old constant
			$bb->user_bbdb_collate = USER_BBDB_COLLATE;
	if ( $bb->user_bbdb_collate )
		$bb->custom_databases['user']['collate'] = $bb->user_bbdb_collate;
}

// Add custom databases if required
if (isset($bb->custom_databases))
	foreach ($bb->custom_databases as $connection => $database)
		$bbdb->add_db_server($connection, $database);
unset($connection, $database);

// Add custom tables if required
if (isset($bb->custom_tables)) {
	$bbdb->tables = array_merge($bbdb->tables, $bb->custom_tables);
	if ( is_wp_error( $bbdb->set_prefix( $bb_table_prefix ) ) )
		die(__('Your user table prefix may only contain letters, numbers and underscores.'));
}


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

define('BB_HASH', $bb->wp_cookies_integrated ? md5(rtrim($bb->wp_siteurl, '/')) : md5(rtrim($bb->uri, '/')));
// Deprecated setting
// TODO: Completely remove old constants on version 1.0
$bb->usercookie = bb_get_option('usercookie');
if ( !$bb->usercookie ) {
	$bb->usercookie = ( $bb->wp_cookies_integrated ? 'wordpressuser_' : 'bb_user_' ) . BB_HASH;
}

// Deprecated setting
// TODO: Completely remove old constants on version 1.0
$bb->passcookie = bb_get_option('passcookie');
if ( !$bb->passcookie ) {
	$bb->passcookie = ( $bb->wp_cookies_integrated ? 'wordpresspass_' : 'bb_pass_' ) . BB_HASH;
}

$bb->authcookie = bb_get_option('authcookie');
if ( !$bb->authcookie ) {
	$bb->authcookie = ($bb->wp_cookies_integrated ? 'wordpress_' : 'bbpress_') . BB_HASH;
}

$bb->cookiepath = bb_get_option('cookiepath');
if ( !$bb->cookiepath ) {
	$bb->cookiepath = $bb->wp_cookies_integrated ? preg_replace('|https?://[^/]+|i', '', $bb->wp_home ) : $bb->path;
}

$bb->sitecookiepath = bb_get_option('sitecookiepath');
if ( !$bb->sitecookiepath ) {
	$bb->sitecookiepath = $bb->wp_cookies_integrated ? preg_replace('|https?://[^/]+|i', '', $bb->wp_siteurl ) : $bb->path;
}


/* BackPress */

// WP_Pass
if ( !class_exists( 'WP_Pass' ) )
	require( BACKPRESS_PATH . 'class.wp-pass.php' );

// WP_Users
if ( !class_exists( 'WP_Users' ) ) {
	require( BACKPRESS_PATH . 'class.wp-users.php' );
	$wp_users_object = new WP_Users( $bbdb );
}

if ( !class_exists( 'BP_Roles' ) )
	require( BACKPRESS_PATH . 'class.bp-roles.php' );

$wp_roles = new BP_Roles( $bbdb );

// WP_User
if ( !class_exists( 'WP_User' ) )
	require( BACKPRESS_PATH . 'class.wp-user.php' );

// WP_Auth
if ( !class_exists( 'WP_Auth' ) ) {
	require( BACKPRESS_PATH . 'class.wp-auth.php' );
	$wp_auth_object = new WP_Auth( $bbdb, $wp_users_object, array(
		'domain' => $bb->cookiedomain,
		'path' => array( $bb->cookiepath, $bb->sitecookiepath ),
		'name' => $bb->authcookie
	) );
}
$bb_current_user =& $wp_auth_object->current;

// WP_Scripts/WP_Styles
if ( !class_exists( 'WP_Dependencies' ) )
	require( BACKPRESS_PATH . 'class.wp-dependencies.php' );
if ( !class_exists( 'WP_Scripts' ) ) {
	require( BACKPRESS_PATH . 'class.wp-scripts.php' );
	require( BACKPRESS_PATH . 'functions.wp-scripts.php' );
}
if ( !class_exists( 'WP_Styles' ) ) {
	require( BACKPRESS_PATH . 'class.wp-styles.php' );
	require( BACKPRESS_PATH . 'functions.wp-styles.php' );
}

// WP_Taxonomy
if ( !class_exists( 'WP_Taxonomy' ) )
	require( BACKPRESS_PATH . 'class.wp-taxonomy.php' );
if ( !class_exists( 'BB_Taxonomy' ) )
	require( BB_PATH . BB_INC . 'class-bb-taxonomy.php' );
if ( !isset($wp_taxonomy_object) ) { // Clean slate
	$wp_taxonomy_object = new BB_Taxonomy( $bbdb );
} elseif ( !is_a($wp_taxonomy_object, 'BB_Taxonomy') ) { // exists, but it's not good enough, translate it
	$tax =& $wp_taxonomy_object->taxonomies; // preserve the references
	$wp_taxonomy_object = new BB_Taxonomy( $bbdb );
	$wp_taxonomy_object->taxonomies =& $tax;
	unset($tax);
}
$wp_taxonomy_object->register_taxonomy( 'bb_topic_tag', 'bb_topic' );

// Set the path to the tag pages
if ( !isset( $bb->tagpath ) )
	$bb->tagpath = $bb->path;

do_action( 'bb_options_loaded' );

/*
Define deprecated constants for plugin compatibility
TODO: Completely remove old constants on version 1.0
$deprecated_constants below is a complete array of old constants and their replacements
*/
$deprecated_constants = array(
	'BBPATH'                 => 'BB_PATH',
	'BBINC'                  => 'BB_INC',
	'BBLANG'                 => 'BB_LANG',
	'BBLANGDIR'              => 'BB_LANG_DIR',
	'BBPLUGINDIR'            => 'BB_PLUGIN_DIR',
	'BBPLUGINURL'            => 'BB_PLUGIN_URL',
	'BBTHEMEDIR'             => 'BB_THEME_DIR',
	'BBTHEMEURL'             => 'BB_THEME_URL',
	'BBHASH'                 => 'BB_HASH'
);
foreach ( $deprecated_constants as $old => $new )
	if ( !defined($old) && defined($new)) // only define if new one is defined
		define($old, constant($new));

$deprecated_constants = array(
	'USER_BBDB_NAME'         => $bb->user_bbdb_name,
	'USER_BBDB_USER'         => $bb->user_bbdb_user,
	'USER_BBDB_PASSWORD'     => $bb->user_bbdb_password,
	'USER_BBDB_HOST'         => $bb->user_bbdb_host,
	'USER_BBDB_CHARSET'      => $bb->user_bbdb_charset,
	'CUSTOM_USER_TABLE'      => $bb->custom_user_table,
	'CUSTOM_USER_META_TABLE' => $bb->custom_user_meta_table,
);
foreach ( $deprecated_constants as $old => $new )
	if ( !defined($old) )
		define($old, $new);
unset($deprecated_constants, $old, $new);

// Load Plugins

// Autoloaded "underscore" plugins
// First BB_CORE_PLUGIN_DIR
foreach ( bb_glob(BB_CORE_PLUGIN_DIR . '_*.php') as $_plugin )
	require( $_plugin );
unset( $_plugin );
// Second BB_PLUGIN_DIR, with no name clash testing
foreach ( bb_glob(BB_PLUGIN_DIR . '_*.php') as $_plugin )
	require( $_plugin );
unset( $_plugin );
do_action( 'bb_underscore_plugins_loaded' );

// Normal plugins
if ( $plugins = bb_get_option( 'active_plugins' ) ) {
	foreach ( (array) $plugins as $plugin ) {
		$plugin = str_replace(
			array('core#', 'user#'),
			array(BB_CORE_PLUGIN_DIR, BB_PLUGIN_DIR),
			$plugin
		);
		if ( file_exists( $plugin ) ) {
			require( $plugin );
		}
	}
}
do_action( 'bb_plugins_loaded' );
unset($plugins, $plugin);

require( BB_PATH . BB_INC . 'pluggable.php');

// Load the default text localization domain.
load_default_textdomain();

// Pull in locale data after loading text domain.
require_once(BB_PATH . BB_INC . 'locale.php');
$bb_locale = new BB_Locale();

$bb_roles =& $wp_roles;
do_action('bb_got_roles', '');

// Load active template functions.php file
$template_functions_include = bb_get_active_theme_directory() . 'functions.php';
if ( file_exists($template_functions_include) )
	include($template_functions_include);
unset($template_functions_include);

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
