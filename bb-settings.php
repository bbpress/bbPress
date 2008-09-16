<?php
/**
 * Used to setup and fix common variables and include
 * the bbPress and BackPress procedural and class libraries.
 *
 * You should not have to change this file, some configuration
 * is possible in bb-config.php
 *
 * @package bbPress
 */



/**
 * Low level reasons to die
 */

// Die if PHP is not new enough
if ( version_compare(PHP_VERSION, '4.3', '<') )
	die(sprintf('Your server is running PHP version %s but bbPress requires at least 4.3', PHP_VERSION) );

// Die if called directly
if ( !defined('BB_PATH') )
	die('This file cannot be called directly.');



// Modify error reporting levels to exclude PHP notices
error_reporting(E_ALL ^ E_NOTICE);

/**
 * bb_unregister_GLOBALS() - Turn register globals off
 *
 * @access private
 * @return null Will return null if register_globals PHP directive was disabled
 */
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



/**
 * bb_timer_start() - PHP 4 standard microtime start capture
 *
 * @access private
 * @global int $bb_timestart Seconds and Microseconds added together from when function is called
 * @return bool Always returns true
 */
function bb_timer_start() {
	global $bb_timestart;
	$mtime = explode(' ', microtime() );
	$bb_timestart = $mtime[1] + $mtime[0];
	return true;
}
bb_timer_start();



/**
 * Whether the server software is IIS or something else
 * @global bool $is_IIS
 */
$is_IIS = strstr($_SERVER['SERVER_SOFTWARE'], 'Microsoft-IIS') ? 1 : 0;



/**
 * Stabilise $_SERVER variables in various PHP environments
 */

// Fix for IIS, which doesn't set REQUEST_URI
if ( empty( $_SERVER['REQUEST_URI'] ) ) {

	// IIS Mod-Rewrite
	if (isset($_SERVER['HTTP_X_ORIGINAL_URL'])) {
		$_SERVER['REQUEST_URI'] = $_SERVER['HTTP_X_ORIGINAL_URL'];
	}
	// IIS Isapi_Rewrite
	else if (isset($_SERVER['HTTP_X_REWRITE_URL'])) {
		$_SERVER['REQUEST_URI'] = $_SERVER['HTTP_X_REWRITE_URL'];
	}
	else
	{
		// Use ORIG_PATH_INFO if there is no PATH_INFO
		if ( !isset($_SERVER['PATH_INFO']) && isset($_SERVER['ORIG_PATH_INFO']) )
			$_SERVER['PATH_INFO'] = $_SERVER['ORIG_PATH_INFO'];

		// Some IIS + PHP configurations puts the script-name in the path-info (No need to append it twice)
		if ( isset($_SERVER['PATH_INFO']) ) {
			if ( $_SERVER['PATH_INFO'] == $_SERVER['SCRIPT_NAME'] )
				$_SERVER['REQUEST_URI'] = $_SERVER['PATH_INFO'];
			else
				$_SERVER['REQUEST_URI'] = $_SERVER['SCRIPT_NAME'] . $_SERVER['PATH_INFO'];
		}

		// Append the query string if it exists and isn't null
		if (isset($_SERVER['QUERY_STRING']) && !empty($_SERVER['QUERY_STRING'])) {
			$_SERVER['REQUEST_URI'] .= '?' . $_SERVER['QUERY_STRING'];
		}
	}
}

// Fix for PHP as CGI hosts that set SCRIPT_FILENAME to something ending in php.cgi for all requests
if ( isset($_SERVER['SCRIPT_FILENAME']) && ( strpos($_SERVER['SCRIPT_FILENAME'], 'php.cgi') == strlen($_SERVER['SCRIPT_FILENAME']) - 7 ) )
	$_SERVER['SCRIPT_FILENAME'] = $_SERVER['PATH_TRANSLATED'];

// Fix for Dreamhost and other PHP as CGI hosts
if (strpos($_SERVER['SCRIPT_NAME'], 'php.cgi') !== false)
	unset($_SERVER['PATH_INFO']);

// Fix empty PHP_SELF
$PHP_SELF = $_SERVER['PHP_SELF'];
if ( empty($PHP_SELF) )
	$_SERVER['PHP_SELF'] = $PHP_SELF = preg_replace("/(\?.*)?$/",'',$_SERVER["REQUEST_URI"]);



/**
 * Let bbPress know what we are up to at the moment
 */

/**
 * Whether the current script is in the admin area or not
 */
if ( !defined( 'BB_IS_ADMIN' ) )
	define( 'BB_IS_ADMIN', false );

/**
 * Whether the current script is part of the installation process or not
 * @since 1.0-beta
 */
if ( !defined( 'BB_INSTALLING' ) )
	define( 'BB_INSTALLING', false );



/**
 * Define include paths and load core BackPress libraries
 */

/**
 * The bbPress includes path relative to BB_PATH
 */
define('BB_INC', 'bb-includes/');

/**
 * The full path to the BackPress libraries
 */
if ( !defined( 'BACKPRESS_PATH' ) )
	define( 'BACKPRESS_PATH', BB_PATH . BB_INC . 'backpress/' );

// Load core BackPress functions
require( BACKPRESS_PATH . 'functions.core.php' );
require( BACKPRESS_PATH . 'functions.compat.php' );

// WP_Error
if ( !class_exists( 'WP_Error' ) )
	require( BACKPRESS_PATH . 'class.wp-error.php' );



/**
 * Set up database parameters based on config and initialise
 */

/**
 * Define the full path to the database class
 */
if ( !defined('BB_DATABASE_CLASS_INCLUDE') )
	define('BB_DATABASE_CLASS_INCLUDE', BACKPRESS_PATH . 'class.bpdb-multi.php' );

// Load the database class
if ( BB_DATABASE_CLASS_INCLUDE )
	require( BB_DATABASE_CLASS_INCLUDE );

/**
 * Define the name of the database class
 */
if ( !defined( 'BB_DATABASE_CLASS' ) )
	define( 'BB_DATABASE_CLASS', 'BPDB_Multi' );

// Die if there is no database table prefix
if ( !$bb_table_prefix )
	die('You must specify a table prefix in your <code>bb-config.php</code> file.');

// Setup the global database connection
$bbdb_class = BB_DATABASE_CLASS;
$bbdb =& new $bbdb_class( array(
	'name' => BBDB_NAME,
	'user' => BBDB_USER,
	'password' => BBDB_PASSWORD,
	'host' => BBDB_HOST,
	'charset' => defined( 'BBDB_CHARSET' ) ? BBDB_CHARSET : false,
	'collate' => defined( 'BBDB_COLLATE' ) ? BBDB_COLLATE : false
) );
unset($bbdb_class);

/**
 * bbPress tables
 */
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

/**
 * Define BackPress Database errors if not already done - no internationalisation at this stage
 */
if (!defined('BPDB__CONNECT_ERROR_MESSAGE'))
	define(BPDB__CONNECT_ERROR_MESSAGE, 'ERROR: Error establishing a database connection');
if (!defined('BPDB__CONNECT_ERROR_MESSAGE'))
	define(BPDB__SELECT_ERROR_MESSAGE, 'ERROR: Can\'t select database.');
if (!defined('BPDB__ERROR_STRING'))
	define(BPDB__ERROR_STRING, 'ERROR: bbPress database error - "%s" for query "%s" via caller "%s"');
if (!defined('BPDB__ERROR_HTML'))
	define(BPDB__ERROR_HTML, '<div id="error"><p class="bpdberror"><strong>Database error:</strong> [%s]<br /><code>%s</code><br />Caller: %s</p></div>');
if (!defined('BPDB__DB_VERSION_ERROR'))
	define(BPDB__DB_VERSION_ERROR, 'ERROR: bbPress requires MySQL 4.0.0 or higher');

// Set the prefix on the tables
if ( is_wp_error( $bbdb->set_prefix( $bb_table_prefix ) ) )
	die('Your table prefix may only contain letters, numbers and underscores.');



/**
 * Load core bbPress libraries
 */

require( BB_PATH . BB_INC . 'wp-functions.php');
require( BB_PATH . BB_INC . 'functions.php');
require( BB_PATH . BB_INC . 'classes.php');



/**
 * Load API and object handling BackPress libraries
 */

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



/**
 * Load mapping class for BackPress to store options
 */
require( BACKPRESS_PATH . 'interface.bp-options.php' );
require( BB_PATH . BB_INC . 'class.bp-options.php' );



/**
 * Load WP_Http class
 */
if ( !class_exists( 'WP_Http' ) )
	require( BACKPRESS_PATH . 'class.wp-http.php' );



/**
 * Determine language settings and load i10n libraries as required
 */

/**
 * The full path to the directory containing language files
 */
if ( !defined('BB_LANG_DIR') )
	if ( defined('BBLANGDIR') ) // User has set old constant
		// TODO: Completely remove old constants on version 1.0
		define('BB_LANG_DIR', BBLANGDIR);
	else
		define('BB_LANG_DIR', BB_PATH . BB_INC . 'languages/'); // absolute path with trailing slash

/**
 * The language in which to display bbPress
 */
if ( !defined('BB_LANG') && defined('BBLANG') && '' != BBLANG ) { // User has set old constant
	// TODO: Completely remove old constants on version 1.0
	define('BB_LANG', BBLANG);
}
if ( defined('BB_LANG') && '' != BB_LANG ) {
	if ( !class_exists( 'gettext_reader' ) )
		require( BACKPRESS_PATH . 'class.gettext-reader.php' );
	if ( !class_exists( 'StreamReader' ) )
		require( BACKPRESS_PATH . 'class.streamreader.php' );
}

// Is WordPress loaded
if ( !defined('BB_IS_WP_LOADED') )
	define('BB_IS_WP_LOADED', defined('DB_NAME'));

// Only load these if WordPress isn't loaded
if ( !BB_IS_WP_LOADED ) {
	require( BACKPRESS_PATH . 'functions.kses.php');
	require( BB_PATH . BB_INC . 'l10n.php');
}



/**
 * Routines related to installation
 */

// Load BB_CHANNELS_INCLUDE if it exists, must be done before the install is completed
if ( defined( 'BB_CHANNELS_INCLUDE' ) && file_exists( BB_CHANNELS_INCLUDE ) && !is_dir( BB_CHANNELS_INCLUDE ) )
	require( BB_CHANNELS_INCLUDE );

// If there is no forum table in the database then redirect to the installer
if ( !BB_INSTALLING && !bb_is_installed() ) {
	$link = preg_replace('|(/bb-admin)?/[^/]+?$|', '/', $_SERVER['PHP_SELF']) . 'bb-admin/install.php';
	require( BB_PATH . BB_INC . 'pluggable.php');
	wp_redirect($link);
	die();
}

// Make sure the new meta table exists - very ugly
// TODO: consider seperating into external upgrade script for 1.0
$bbdb->suppress_errors();
if ( !BB_INSTALLING && !bb_get_option_from_db( 'bb_db_version' ) ) {
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
$bbdb->suppress_errors(false);

// Setup some variables in the $bb class if they don't exist - some of these are deprecated
foreach ( array('use_cache' => false, 'debug' => false, 'static_title' => false, 'load_options' => true, 'email_login' => false) as $o => $oo)
	if ( !isset($bb->$o) )
		$bb->$o = $oo;
unset($o, $oo);

// Disable plugins during installation
if ( BB_INSTALLING ) {
	foreach ( array('active_plugins') as $i )
		$bb->$i = false;
	unset($i);
}



/**
 * Load additional bbPress libraries
 */

require( BB_PATH . BB_INC . 'formatting-functions.php');
require( BB_PATH . BB_INC . 'template-functions.php');
require( BB_PATH . BB_INC . 'capabilities.php');
require( BB_PATH . BB_INC . 'cache.php'); // Deprecating
require( BB_PATH . BB_INC . 'deprecated.php');

/**
 * Old cache global object for backwards compatibility
 */
$bb_cache = new BB_Cache();

// Cache options from the database
if ( $bb->load_options ) {
	$bbdb->suppress_errors();
	bb_cache_all_options();
	$bbdb->suppress_errors(false);
}

require( BB_PATH . BB_INC . 'default-filters.php');
require( BB_PATH . BB_INC . 'script-loader.php');

// Sanitise external input
$_GET    = bb_global_sanitize($_GET);
$_POST   = bb_global_sanitize($_POST);
$_COOKIE = bb_global_sanitize($_COOKIE, false);
$_SERVER = bb_global_sanitize($_SERVER);

/**
 * Set the URI and derivitaves
 */
if ( $bb->uri = bb_get_option('uri') ) {
	$bb->uri = rtrim($bb->uri, '/') . '/';
	
	if ( preg_match( '@^(https?://[^/]+)((?:/.*)*/{1,1})$@i', $bb->uri, $matches ) ) {
		// Used when setting up cookie domain
		$bb->domain = $matches[1];
		// Used when setting up cookie paths
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
if ( !BB_INSTALLING && !$bb->uri ) {
	bb_die( __('Could not determine site URI') );
}

/**
 * BB_FORCE_SSL_USER_FORMS - Whether to force use of ssl on user forms like login, registration and profile editing
 **/
if ( !defined('BB_FORCE_SSL_USER_FORMS') ) {
	define('BB_FORCE_SSL_USER_FORMS', false);
}
bb_force_ssl_user_forms(BB_FORCE_SSL_USER_FORMS);

/**
 * BB_FORCE_SSL_ADMIN - Whether to force use of ssl in the admin area
 **/
if ( !defined('BB_FORCE_SSL_ADMIN') ) {
	define('BB_FORCE_SSL_ADMIN', false);
}
bb_force_ssl_admin(BB_FORCE_SSL_ADMIN);



/**
 * Define theme and plugin constants
 */

/**
 * Full path to the location of the core plugins directory
 */
define('BB_CORE_PLUGIN_DIR', BB_PATH . 'bb-plugins/');

/**
 * Full URL of the core plugins directory
 */
define('BB_CORE_PLUGIN_URL', $bb->uri . 'bb-plugins/');

/**
 * Full path to the location of the core themes directory
 */
define('BB_CORE_THEME_DIR', BB_PATH . 'bb-templates/');

/**
 * Full URL of the core themes directory
 */
define('BB_CORE_THEME_URL', $bb->uri . 'bb-templates/');

/**
 * The default theme
 */
define('BB_DEFAULT_THEME', 'core#kakumei');

/**
 * Full path to the location of the default theme directory
 */
define('BB_DEFAULT_THEME_DIR', BB_CORE_THEME_DIR . 'kakumei/');

/**
 * Full URL of the default theme directory
 */
define('BB_DEFAULT_THEME_URL', BB_CORE_THEME_URL . 'kakumei/');

/**
 * Full path to the location of the user plugins directory
 */
if ( !defined('BB_PLUGIN_DIR') )
	if ( defined('BBPLUGINDIR') ) // User has set old constant
		// TODO: Completely remove old constants on version 1.0
		define('BB_PLUGIN_DIR', BBPLUGINDIR);
	else
		define('BB_PLUGIN_DIR', BB_PATH . 'my-plugins/');

/**
 * Full URL of the user plugins directory
 */
if ( !defined('BB_PLUGIN_URL') )
	if ( defined('BBPLUGINURL') ) // User has set old constant
		// TODO: Completely remove old constants on version 1.0
		define('BB_PLUGIN_URL', BBPLUGINURL);
	else
		define('BB_PLUGIN_URL', $bb->uri . 'my-plugins/');

/**
 * Full path to the location of the user themes directory
 */
if ( !defined('BB_THEME_DIR') )
	if ( defined('BBTHEMEDIR') ) // User has set old constant
		// TODO: Completely remove old constants on version 1.0
		define('BB_THEME_DIR', BBTHEMEDIR);
	else
		define('BB_THEME_DIR', BB_PATH . 'my-templates/');

/**
 * Full URL of the user themes directory
 */
if ( !defined('BB_THEME_URL') )
	if ( defined('BBTHEMEURL') ) // User has set old constant
		// TODO: Completely remove old constants on version 1.0
		define('BB_THEME_URL', BBTHEMEURL);
	else
		define('BB_THEME_URL', $bb->uri . 'my-templates/');



/**
 * Add custom tables if present
 */

// Resolve the various ways custom user tables might be setup
bb_set_custom_user_tables();

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



/**
 * Sort out cookies so they work with WordPress (if required)
 * Note that database integration is no longer a pre-requisite for cookie integration
 */

$bb->wp_siteurl = bb_get_option('wp_siteurl');
if ( $bb->wp_siteurl ) {
	$bb->wp_siteurl = rtrim($bb->wp_siteurl, '/');
}

$bb->wp_home = bb_get_option('wp_home');
if ( $bb->wp_home ) {
	$bb->wp_home = rtrim($bb->wp_home, '/');
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

define('BB_HASH', $bb->wp_cookies_integrated ? md5($bb->wp_siteurl) : md5($bb->uri));
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

$bb->secure_auth_cookie = bb_get_option('secure_auth_cookie');
if ( !$bb->secure_auth_cookie ) {
	$bb->secure_auth_cookie = ($bb->wp_cookies_integrated ? 'wordpress_sec_' : 'bbpress_sec_') . BB_HASH;
}

$bb->logged_in_cookie = bb_get_option('logged_in_cookie');
if ( !$bb->logged_in_cookie ) {
	$bb->logged_in_cookie = ($bb->wp_cookies_integrated ? 'wordpress_logged_in_' : 'bbpress_logged_in_') . BB_HASH;
}

$bb->cookiepath = bb_get_option('cookiepath');
if ( !$bb->cookiepath ) {
	$bb->cookiepath = $bb->wp_cookies_integrated ? preg_replace('|https?://[^/]+|i', '', $bb->wp_home ) : $bb->path;
}
$bb->cookiepath = rtrim($bb->cookiepath, '/');

$bb->admin_cookie_path = bb_get_option('admin_cookie_path');
if ( !$bb->admin_cookie_path ) {
	$bb->admin_cookie_path = $bb->path . 'bb-admin';
}
$bb->admin_cookie_path = rtrim($bb->admin_cookie_path, '/');

$bb->core_plugins_cookie_path = bb_get_option('core_plugins_cookie_path');
if ( !$bb->core_plugins_cookie_path ) {
	$bb->core_plugins_cookie_path = preg_replace('|https?://[^/]+|i', '', BB_CORE_PLUGIN_URL);
}
$bb->core_plugins_cookie_path = rtrim($bb->core_plugins_cookie_path, '/');

$bb->user_plugins_cookie_path = bb_get_option('user_plugins_cookie_path');
if ( !$bb->user_plugins_cookie_path ) {
	$bb->user_plugins_cookie_path = preg_replace('|https?://[^/]+|i', '', BB_PLUGIN_URL);
}
$bb->user_plugins_cookie_path = rtrim($bb->user_plugins_cookie_path, '/');

$bb->sitecookiepath = bb_get_option('sitecookiepath');
$_bb_sitecookiepath = $bb->sitecookiepath;
if ( !$bb->sitecookiepath && $bb->wp_cookies_integrated ) {
	$bb->sitecookiepath = preg_replace('|https?://[^/]+|i', '', $bb->wp_siteurl );
	$_bb_sitecookiepath = $bb->sitecookiepath;
	if (bb_get_common_paths($bb->sitecookiepath, $bb->cookiepath) == $bb->cookiepath) {
		$bb->sitecookiepath = $bb->cookiepath;
	}
}
$bb->sitecookiepath = rtrim($bb->sitecookiepath, '/');

$bb->wp_admin_cookie_path = bb_get_option('wp_admin_cookie_path');
if ( !$bb->wp_admin_cookie_path && $bb->wp_cookies_integrated ) {
	$bb->wp_admin_cookie_path = $_bb_sitecookiepath . '/wp-admin';
}
$bb->wp_admin_cookie_path = rtrim($bb->wp_admin_cookie_path, '/');

$bb->wp_plugins_cookie_path = bb_get_option('wp_plugins_cookie_path');
if ( !$bb->wp_plugins_cookie_path && $bb->wp_cookies_integrated ) {
	// This is a best guess only, should be manually set to match WP_PLUGIN_URL
	$bb->wp_plugins_cookie_path = $_bb_sitecookiepath . '/wp-content/plugins';
}
$bb->wp_plugins_cookie_path = rtrim($bb->wp_plugins_cookie_path, '/');
unset($_bb_sitecookiepath);

/**
 * Should be exactly the same as the default value of the KEYS in bb-config-sample.php
 * @since 1.0-beta
 */
$bb_default_secret_key = 'put your unique phrase here';



/**
 * Remaining BackPress
 */

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

/**
 * BP_Roles object
 */
$wp_roles = new BP_Roles( $bbdb );

// WP_User
if ( !class_exists( 'WP_User' ) )
	require( BACKPRESS_PATH . 'class.wp-user.php' );

// WP_Auth
if ( !class_exists( 'WP_Auth' ) ) {
	require( BACKPRESS_PATH . 'class.wp-auth.php' );
	
	$cookies = array();
	
	$cookies['logged_in'][] = array(
		'domain' => $bb->cookiedomain,
		'path' => $bb->cookiepath,
		'name' => $bb->logged_in_cookie
	);
	
	if ($bb->sitecookiepath && $bb->cookiepath != $bb->sitecookiepath) {
		$cookies['logged_in'][] = array(
			'domain' => $bb->cookiedomain,
			'path' => $bb->sitecookiepath,
			'name' => $bb->logged_in_cookie
		);
	}
	
	$cookies['auth'][] = array(
		'domain' => $bb->cookiedomain,
		'path' => $bb->admin_cookie_path,
		'name' => $bb->authcookie
	);
	
	$cookies['secure_auth'][] = array(
		'domain' => $bb->cookiedomain,
		'path' => $bb->admin_cookie_path,
		'name' => $bb->secure_auth_cookie,
		'secure' => true
	);
	
	$cookies['auth'][] = array(
		'domain' => $bb->cookiedomain,
		'path' => $bb->core_plugins_cookie_path,
		'name' => $bb->authcookie
	);
	
	$cookies['secure_auth'][] = array(
		'domain' => $bb->cookiedomain,
		'path' => $bb->core_plugins_cookie_path,
		'name' => $bb->secure_auth_cookie,
		'secure' => true
	);
	
	$cookies['auth'][] = array(
		'domain' => $bb->cookiedomain,
		'path' => $bb->user_plugins_cookie_path,
		'name' => $bb->authcookie
	);
	
	$cookies['secure_auth'][] = array(
		'domain' => $bb->cookiedomain,
		'path' => $bb->user_plugins_cookie_path,
		'name' => $bb->secure_auth_cookie,
		'secure' => true
	);
	
	if ($bb->wp_admin_cookie_path) {
		$cookies['auth'][] = array(
			'domain' => $bb->cookiedomain,
			'path' => $bb->wp_admin_cookie_path,
			'name' => $bb->authcookie
		);
	
		$cookies['secure_auth'][] = array(
			'domain' => $bb->cookiedomain,
			'path' => $bb->wp_admin_cookie_path,
			'name' => $bb->secure_auth_cookie,
			'secure' => true
		);
	}
	
	if ($bb->wp_plugins_cookie_path) {
		$cookies['auth'][] = array(
			'domain' => $bb->cookiedomain,
			'path' => $bb->wp_plugins_cookie_path,
			'name' => $bb->authcookie
		);
	
		$cookies['secure_auth'][] = array(
			'domain' => $bb->cookiedomain,
			'path' => $bb->wp_plugins_cookie_path,
			'name' => $bb->secure_auth_cookie,
			'secure' => true
		);
	}
	
	/**
	 * WP_Auth object
	 */
	$wp_auth_object = new WP_Auth(
		$bbdb,
		$wp_users_object,
		$cookies
	);
	
	unset($cookies);
}

/**
 * Current user object
 */
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



/**
 * Define deprecated constants for plugin compatibility
 * TODO: Completely remove old constants on version 1.0
 * $deprecated_constants below is a complete array of old constants and their replacements
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



/**
 * Load Plugins
 */

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
		if ( strpos($plugin, 'core#') === 0 || strpos($plugin, 'user#') === 0 ) {
			if ( validate_file($plugin) ) // $plugin has .., :, etc.
				continue;

			$plugin = str_replace(
				array('core#', 'user#'),
				array(BB_CORE_PLUGIN_DIR, BB_PLUGIN_DIR),
				$plugin
			);
			if (
				BB_CORE_PLUGIN_DIR != $plugin &&
				BB_PLUGIN_DIR != $plugin &&
				file_exists( $plugin )
			) {
				require( $plugin );
			}
		}
	}
}
do_action( 'bb_plugins_loaded' );
unset($plugins, $plugin);

require( BB_PATH . BB_INC . 'pluggable.php');



/**
 * Initialise localisation
 */

// Load the default text localization domain.
load_default_textdomain();

// Pull in locale data after loading text domain.
require_once(BB_PATH . BB_INC . 'locale.php');

/**
 * Localisation object
 */
$bb_locale = new BB_Locale();



/**
 * Reference to $wp_roles
 */
$bb_roles =& $wp_roles;
do_action('bb_got_roles');



/**
 * Load active template functions.php file
 */
$template_functions_include = bb_get_active_theme_directory() . 'functions.php';
if ( file_exists($template_functions_include) )
	include($template_functions_include);
unset($template_functions_include);



/**
 * Create an API hook to run on shutdown
 */

function bb_shutdown_action_hook() {
	do_action('bb_shutdown');
}
register_shutdown_function('bb_shutdown_action_hook');


/**
 * Get details of the current user
 */

bb_current_user();



/**
 * Initialise CRON
 */

if ( !function_exists('wp_schedule_single_event') )
	require( BACKPRESS_PATH . 'functions.wp-cron.php' );
if ((!defined('DOING_CRON') || !DOING_CRON))
	wp_cron();



/**
 * Initialisation complete API hook
 */

do_action('bb_init');



/**
 * Block user if they deserve it
 */

if ( bb_is_user_logged_in() && bb_has_broken_pass() )
	bb_block_current_user();



/**
 * The currently viewed page number
 */
$page = bb_get_uri_page();



/**
 * Send HTTP headers
 */

bb_send_headers();
?>