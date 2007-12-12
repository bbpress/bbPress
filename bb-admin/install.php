<?php
// Modify error reporting levels
error_reporting(E_ALL ^ E_NOTICE);

// Let everyone know we are installing
define('BB_INSTALLING', true);

// Load bbPress
require_once('../bb-load.php');

// Define the path to the include files
if (!defined('BBINC')) {
	define('BBINC', 'bb-includes/');
}

require_once(BBPATH . BBINC . 'wp-functions.php');
require_once(BBPATH . BBINC . 'functions.php');

// Set the requested step
$step = $_GET['step'] ? (integer) $_GET['step'] : 0;

// Check for a config file
if (!$config_exists = file_exists(BBPATH . 'bb-config.php')) {
	$config_exists = file_exists(dirname(BBPATH) . 'bb-config.php');
}

// Check if bbPress is already installed
if ($config_exists) {
	if (bb_is_installed()) {
		// The database is installed
		if (bb_get_option('bb_db_version') > bb_get_option_from_db('bb_db_version')) {
			// The database needs upgrading
			// Step -1 is just a message directing the user to the upgrade page
			$step = -1;
		} else {
			// Redirect to the index
			wp_redirect($bb->uri);
			die();
		}
	}
} elseif ( $step > 1 ) {
	$step = 0;
}

// Check for an old config file
if (!$old_config_exists = file_exists(BBPATH . 'config.php')) {
	$old_config_exists = file_exists(dirname(BBPATH) . 'config.php');
}

if ($old_config_exists && $step > -1) {
	// There is an old school config file
	// Step -2 is just a message telling the user to remove it
	$step = -2;
}

// Includes for each step can be different
// Change the step if required based on what state the installation is in
switch ($step) {
	case -2:
		break;
	
	case -1:
		break;
	
	case 0:
		require_once(BBPATH . BBINC . 'l10n.php');
		break;
	
	case 1:
		if ($config_exists) {
			// The configuration file exists
			$step_status = 'complete';
			$step_message = __('A configuration file was found at <code>bb-config.php</code><br />You may continue to the next step.');
		} else {
			require_once(BBPATH . BBINC . 'compat.php');
			require_once(BBPATH . BBINC . 'l10n.php');
			require_once(BBPATH . BBINC . 'pluggable.php');
			require_once(BBPATH . BBINC . 'wp-classes.php');
			require_once(BBPATH . BBINC . 'db-base.php' );
			if ( extension_loaded('mysql') ) {
				require_once(BBPATH . BBINC . 'db.php');
			} elseif ( extension_loaded('mysqli') ) {
				require_once(BBPATH . BBINC . 'db-mysqli.php');
			} else {
				die('Your PHP installation appears to be missing the MySQL which is required for bbPress.');
			}
		}
		break;
	
	case 2:
		break;
	
	case 3:
		if ($_POST['install_s2_back']) {
			$step = 2;
			$step_back = true;
		} else {
			if ($_POST['install_s2_next']) {
				$post_from_last_step = true;
			}
			$step2_status = __('&laquo; skipped');
			if ($_POST['install_s2_integrate_toggle']) {
				$step2_status = __('&laquo; completed');
			}
		}
		break;
	
	case 4:
		$step2_status = __('&laquo; skipped');
		if ($_POST['install_s2_integrate_toggle']) {
			$step2_status = __('&laquo; completed');
		}
		
		$step3_status = __('&laquo; incomplete');
		if ($_POST['install_s3_back']) {
			$step = 3;
			$step_back = true;
		} elseif ($_POST['install_s3_keymaster_user_login']) {
			$step3_status = __('&laquo; completed');
		} else {
			$step = 3;
		}
		break;
}

// Do things based on the step
switch ($step) {
	case -2:
		break;
	
	case -1:
		break;
	
	case 0:
		break;
	
	case 1:
		
		// Database configuration
		
		// Initialise form variables
		$install_s1_bbdb_name               = '';
		$install_s1_bbdb_user               = '';
		$install_s1_bbdb_password           = '';
		$install_s1_bbdb_host               = 'localhost';
		$install_s1_bbdb_charset            = 'utf8';
		$install_s1_bbdb_collate            = '';
		$install_s1_bb_table_prefix         = 'bb_';
		$install_s1_advanced_toggle         = 0;
		$install_s1_advanced_toggle_checked = '';
		$install_s1_advanced_display        = 'none';
		
		// Check if the config files path is writable
		if ( is_writable(BBPATH) ) {
			$config_writable = true;
		}
		
		// If the form is posted and there is no config file already
		if ( strtolower($_SERVER['REQUEST_METHOD']) == 'post' && !$config_exists ) {
			
			// Process the form
			
			// Check the referer
			bb_check_admin_referer( 'bbpress-installer' );
			
			// Retrieve and trim the database information
			$install_s1_bbdb_name               = trim($_POST['install_s1_bbdb_name']);
			$install_s1_bbdb_user               = trim($_POST['install_s1_bbdb_user']);
			$install_s1_bbdb_password           = trim($_POST['install_s1_bbdb_password']);
			$install_s1_bbdb_host               = trim($_POST['install_s1_bbdb_host']);
			$install_s1_bbdb_charset            = trim($_POST['install_s1_bbdb_charset']);
			$install_s1_bbdb_collate            = trim($_POST['install_s1_bbdb_collate']);
			$install_s1_bb_table_prefix         = preg_replace('/[^0-9a-zA-Z_]/', '', trim($_POST['install_s1_bb_table_prefix']));
			$install_s1_advanced_toggle         = $_POST['install_s1_advanced_toggle'];
			$install_s1_advanced_toggle_checked = $install_s1_advanced_toggle ? ' checked="checked"' : '';
			$install_s1_advanced_display        = $install_s1_advanced_toggle ? 'block' : 'none';
			
			// Make sure there is a prefix
			if (empty($install_s1_bb_table_prefix)) {
				$install_s1_bb_table_prefix = 'bb_';
			}
			
			// If we are returning from the step completed screen
			if ($step_back) {
				break;
			}
			
			// Read the contents of the sample config
			if ( file_exists( BBPATH . 'bb-config-sample.php' ) ) {
				$config_sample = file( BBPATH . 'bb-config-sample.php' );
			} else {
				$step_status = 'error';
				$step_message = __('I could not find the file <code>bb-config-sample.php</code><br />Please upload it to the root directory of your bbPress installation.');
				break;
			}
			
			// Test the db connection.
			define('BBDB_NAME',     $install_s1_bbdb_name);
			define('BBDB_USER',     $install_s1_bbdb_user);
			define('BBDB_PASSWORD', $install_s1_bbdb_password);
			define('BBDB_HOST',     $install_s1_bbdb_host);
			define('BBDB_CHARSET',  $install_s1_bbdb_charset);
			define('BBDB_COLLATE',  $install_s1_bbdb_collate);
			
			// We'll fail here if the values are no good.
			$bbdb = new bbdb(BBDB_USER, BBDB_PASSWORD, BBDB_NAME, BBDB_HOST);
			
			if (!$bbdb->db_connect('SET NAMES ' . BBDB_CHARSET)) {
				$step_status = 'error';
				$step_message = __('There was a problem connecting to the database you specified.<br />Please check the settings, then try again.');
				break;
			}
			
			// Initialise an array to store th config lines
			$config_lines = array();
			
			// Loop through the sample config and write lines to the new config file
			foreach ($config_sample as $line_num => $line) {
				switch (substr($line,0,18)) {
					case "define('BBDB_NAME'":
						$config_lines[] = str_replace("bbpress", $install_s1_bbdb_name, $line);
						break;
					case "define('BBDB_USER'":
						$config_lines[] = str_replace("'username'", "'$install_s1_bbdb_user'", $line);
						break;
					case "define('BBDB_PASSW":
						$config_lines[] = str_replace("'password'", "'$install_s1_bbdb_password'", $line);
						break;
					case "define('BBDB_HOST'":
						$config_lines[] = str_replace("localhost", $install_s1_bbdb_host, $line);
						break;
					case "define('BBDB_CHARS":
						$config_lines[] = str_replace("utf8", $install_s1_bbdb_charset, $line);
						break;
					case "define('BBDB_COLLA":
						$config_lines[] = str_replace("''", "'$install_s1_bbdb_collate'", $line);
						break;
					case '$bb_table_prefix =':
						$config_lines[] = str_replace("'bb_'", "'$install_s1_bb_table_prefix'", $line);
						break;
					default:
						$config_lines[] = $line;
				}
			}
			
			// If we can write the file
			if ($config_writable) {
				
				// Create the new config file and open it for writing
				$config_handle = fopen('../bb-config.php', 'w');
				
				// Write lines one by one to avoid OS specific newline hassles
				foreach ($config_lines as $config_line) {
					fwrite($config_handle, $config_line);
				}
				
				// Close the new config file
				fclose($config_handle);
				
				// Make the file slightly more secure than world readable
				chmod('../bb-config.php', 0666);
				
				$step_status = 'complete';
				$step_message = __('Your settings have been saved to the file <code>bb-config.php</code><br />You can now continue to the next step.');
				
			} else {
				
				// Just write the contents to screen
				$config_text = join(null, $config_lines);
				
				$step_status = 'complete';
				$step_message = __('Your settings could not be saved to a configuration file. You will need to save the text shown below into a file named <code>bb-config.php</code> in the root directory of your bbPress installation before you can continue to the next step.');
				
			}
			
		}
		
		break;
	
	case 2:
		
		// WordPress integration
		
		// Initialise form variables
		$install_s2_integrate_toggle                  = 0;
		$install_s2_integrate_toggle_checked          = '';
		$install_s2_integrate_display                 = 'none';
		$install_s2_submit                            = __('Skip WordPress integration &raquo;');
		$install_s2_wp_siteurl                        = '';
		$install_s2_wp_home                           = '';
		$install_s2_integrate_database_toggle         = 0;
		$install_s2_integrate_database_toggle_checked = '';
		$install_s2_integrate_database_display        = 'none';
		$install_s2_wp_table_prefix                   = 'wp_';
		$install_s2_advanced_toggle                   = 0;
		$install_s2_advanced_toggle_checked           = '';
		$install_s2_advanced_display                  = 'none';
		$install_s2_user_bbdb_name                    = '';
		$install_s2_user_bbdb_user                    = '';
		$install_s2_user_bbdb_password                = '';
		$install_s2_user_bbdb_host                    = '';
		$install_s2_user_bbdb_charset                 = '';
		$install_s2_custom_user_table                 = '';
		$install_s2_custom_user_meta_table            = '';
		
		$install_s2_input_class = array(
			'install_s2_wp_siteurl'         => '',
			'install_s2_wp_home'            => '',
			'install_s2_wp_table_prefix'    => '',
			'install_s2_user_bbdb_name'     => '',
			'install_s2_user_bbdb_user'     => '',
			'install_s2_user_bbdb_password' => '',
			'install_s2_user_bbdb_host'     => ''
		);
		$install_s2_input_error = $install_s2_error_class;
		
		// If the form is posted
		if ( strtolower($_SERVER['REQUEST_METHOD']) == 'post' ) {
			
			// Check the referer
			bb_check_admin_referer( 'bbpress-installer' );
			
			// Make sure there is no prefix at this stage
			$install_s2_wp_table_prefix = '';
			
			// Retrieve, trim and validate the integration information
			$install_s2_integrate_toggle = $_POST['install_s2_integrate_toggle'];
			
			// If there are no settings then goto step 3
			if (!$install_s2_integrate_toggle && !$step_back) {
				$step_status = 'complete';
				$step_message = __('You have chosen to skip the WordPress integration step. You can always integrate WordPress later from within the admin area of bbPress.');
				break;
			}
			
			
			$install_s2_integrate_toggle_checked = $install_s2_integrate_toggle ? ' checked="checked"' : '';
			$install_s2_integrate_display        = $install_s2_integrate_toggle ? 'block' : 'none';
			$install_s2_submit                   = $install_s2_integrate_toggle ? __('Save WordPress integration settings &raquo;') : __('Skip WordPress integration &raquo;');
			
			if ($install_s2_integrate_toggle) {
				
				// Initialise an array to contain input errors
				$install_s2_errors = array();
				
				$install_s2_wp_siteurl = trim($_POST['install_s2_wp_siteurl']) ? rtrim(trim($_POST['install_s2_wp_siteurl']), '/') . '/' : '';
				$install_s2_errors['install_s2_wp_siteurl'][] = empty($install_s2_wp_siteurl) ? 'empty' : false;
				if ($parsed = parse_url($install_s2_wp_siteurl)) {
					$install_s2_errors['install_s2_wp_siteurl'][] = preg_match('/https?/i', $parsed['scheme']) ? false : 'urlscheme';
					$install_s2_errors['install_s2_wp_siteurl'][] = empty($parsed['host']) ? 'urlhost' : false;
				} else {
					$install_s2_errors['install_s2_wp_siteurl'][] = 'urlparse';
				}
				
				$install_s2_wp_home = trim($_POST['install_s2_wp_home']) ? rtrim(trim($_POST['install_s2_wp_home']), '/') . '/' : '';
				$install_s2_errors['install_s2_wp_home'][] = empty($install_s2_wp_home) ? 'empty' : false;
				if ($parsed = parse_url($install_s2_wp_home)) {
					$install_s2_errors['install_s2_wp_home'][] = preg_match('/https?/i', $parsed['scheme']) ? false : 'urlscheme';
					$install_s2_errors['install_s2_wp_home'][] = empty($parsed['host']) ? 'urlhost' : false;
				} else {
					$install_s2_errors['install_s2_wp_home'][] = 'urlparse';
				}
			
				$install_s2_integrate_database_toggle         = $_POST['install_s2_integrate_database_toggle'];
				$install_s2_integrate_database_toggle_checked = $install_s2_integrate_database_toggle ? ' checked="checked"' : '';
				$install_s2_integrate_database_display        = $install_s2_integrate_database_toggle ? 'block' : 'none';
				
				// Check if database settings are specified
				if ($install_s2_integrate_database_toggle) {
					$install_s2_wp_table_prefix         = preg_replace('/[^0-9a-zA-Z_]/', '', trim($_POST['install_s2_wp_table_prefix']));
					$install_s2_advanced_toggle         = $_POST['install_s2_advanced_toggle'];
					$install_s2_advanced_toggle_checked = $install_s2_advanced_toggle ? ' checked="checked"' : '';
					$install_s2_advanced_display        = $install_s2_advanced_toggle ? 'block' : 'none';
					
					// Check if advanced database settings are specified
					if ($install_s2_advanced_toggle) {
						$install_s2_user_bbdb_name     = trim($_POST['install_s2_user_bbdb_name']);
						$install_s2_user_bbdb_user     = trim($_POST['install_s2_user_bbdb_user']);
						$install_s2_user_bbdb_password = trim($_POST['install_s2_user_bbdb_password']);
						$install_s2_user_bbdb_host     = trim($_POST['install_s2_user_bbdb_host']);
						if (
							!empty($install_s2_user_bbdb_name) ||
							!empty($install_s2_user_bbdb_user) ||
							!empty($install_s2_user_bbdb_password) ||
							!empty($install_s2_user_bbdb_host)
						) {
							$install_s2_errors['install_s2_user_bbdb_name'][]     = empty($install_s2_user_bbdb_name) ? 'empty' : false;
							$install_s2_errors['install_s2_user_bbdb_user'][]     = empty($install_s2_user_bbdb_user) ? 'empty' : false;
							$install_s2_errors['install_s2_user_bbdb_password'][] = empty($install_s2_user_bbdb_password) ? 'empty' : false;
							$install_s2_errors['install_s2_user_bbdb_host'][]     = empty($install_s2_user_bbdb_host) ? 'empty' : false;
							$install_s2_user_bbdb_charset                         = trim($_POST['install_s2_user_bbdb_charset']);
						}
						$install_s2_custom_user_table      = trim($_POST['install_s2_custom_user_table']);
						$install_s2_custom_user_meta_table = trim($_POST['install_s2_custom_user_meta_table']);
					}
					
					// Make sure there is a prefix
					if (empty($install_s2_wp_table_prefix)) {
						$install_s2_wp_table_prefix = 'wp_';
					}
				}
			
				// Remove empty values from the error array
				foreach ($install_s2_errors as $input => $types) {
					$types = array_filter($types);
					if (!count($types)) {
						unset($install_s2_errors[$input]);
					}
				}
			
				// Check for errors and build error messages
				if ( count($install_s2_errors) ) {
					$step_status = 'error';
					$step_message = __('Your integration settings have not been processed due to errors with the items marked below.');
					foreach ($install_s2_errors as $input => $types) {
						$install_s2_input_class[$input] = 'error';
						foreach ($types as $type) {
							switch ($type) {
								case 'empty':
									$install_s2_input_error[$input] .= '<span class="error">&bull; ' . __('This value is required to continue.') . '</span>';
									break(2);
								case 'urlparse':
									$install_s2_input_error[$input] .= '<span class="error">&bull; ' . __('This does not appear to be a valid URL.') . '</span>';
									break;
								case 'urlscheme':
									$install_s2_input_error[$input] .= '<span class="error">&bull; ' . __('The URL must begin with "http" or "https".') . '</span>';
									break;
								case 'urlhost':
									$install_s2_input_error[$input] .= '<span class="error">&bull; ' . __('The URL does not contain a host name.') . '</span>';
									break;
							}
						}
					}
					
					// Reset the default prefix if it is empty
					if (empty($install_s2_wp_table_prefix)) {
						$install_s2_wp_table_prefix = 'wp_';
					}
					break;
				}
			
				// If there are database settings
				if ($install_s2_integrate_database_toggle) {
				
					// Test the db connection.
				
					// Setup variables and constants if available
					if ( !empty($install_s2_wp_table_prefix) )        $bb->wp_table_prefix = $install_s2_wp_table_prefix;
					if ( !empty($install_s2_user_bbdb_name) )         define('USER_BBDB_NAME',         $install_s2_user_bbdb_name);
					if ( !empty($install_s2_user_bbdb_user) )         define('USER_BBDB_USER',         $install_s2_user_bbdb_user);
					if ( !empty($install_s2_user_bbdb_password) )     define('USER_BBDB_PASSWORD',     $install_s2_user_bbdb_password);
					if ( !empty($install_s2_user_bbdb_host) )         define('USER_BBDB_HOST',         $install_s2_user_bbdb_host);
					if ( !empty($install_s2_user_bbdb_charset) )      define('USER_BBDB_CHARSET',      $install_s2_user_bbdb_charset);
					if ( !empty($install_s2_custom_user_table) )      define('CUSTOM_USER_TABLE',      $install_s2_custom_user_table);
					if ( !empty($install_s2_custom_user_meta_table) ) define('CUSTOM_USER_META_TABLE', $install_s2_custom_user_meta_table);
				
					// Set the new prefix for user tables
					$bbdb->set_user_prefix();
				
					// We'll fail here if the values are no good.
					// Hide errors for the test
					$bbdb->hide_errors();
					// Select from the user table (may fail if there are no records in the table)
					if (!$bbdb->query('SELECT ID FROM ' . $bbdb->users)) {
					
						// Bad database settings
					
						// Turn errors back on
						$bbdb->show_errors();
					
						// Set the status to error
						$step_status = 'error';
					
						if ($install_s2_advanced_toggle) {
						
							// Advanced settings error
							$step_message = __('There was a problem connecting to the WordPress user database you specified. Please check the settings, then try again.');
						
						} else {
						
							// A different error for shared databases (only the table prefix is a problem here - and it is usually PEBKAC)
							$step_message = __('Existing WordPress user tables could not be found in the bbPress database you specified in step 1.<br /><br />This is probably because the database does not already contain working WordPress tables. You may need to specify advanced database settings or leave integration until after installation.');
							$install_s2_input_class['install_s2_wp_table_prefix'] = 'error';
						
						}
						break;
					}
					// Turn errors back on
					$bbdb->show_errors();
				
				}
			}
			
			if (!$step_back) {
				// If we aren't returning from the step completed screen
				
				// Set the status to complete
				$step_status = 'complete';
				$step_message = 'Your WordPress integration cookie and database settings have been successfully validated. They will be saved after the next step.<br /><br />Once you have finished installing, you should visit the WordPress integration section of the bbPress admin area for further options and integration instructions, including user mapping and the correct cookie settings to add to your WordPress configuration file.';
			}
		}
		
		break;
	
	case 3:
		
		// Check the referer
		bb_check_admin_referer( 'bbpress-installer' );
		
		// Integration settings passed from step 2
		// These are already validated provided that the referer checks out
		$install_s2_integrate_toggle = $_POST['install_s2_integrate_toggle'];
		if ($install_s2_integrate_toggle) {
			$install_s2_wp_siteurl                = $_POST['install_s2_wp_siteurl'];
			$install_s2_wp_home                   = $_POST['install_s2_wp_home'];
			$install_s2_integrate_database_toggle = $_POST['install_s2_integrate_database_toggle'];
			if ($install_s2_integrate_database_toggle) {
				$install_s2_wp_table_prefix = $_POST['install_s2_wp_table_prefix'];
				$install_s2_advanced_toggle = $_POST['install_s2_advanced_toggle'];
				if ($install_s2_advanced_toggle) {
					$install_s2_user_bbdb_name         = $_POST['install_s2_user_bbdb_name'];
					$install_s2_user_bbdb_user         = $_POST['install_s2_user_bbdb_user'];
					$install_s2_user_bbdb_password     = $_POST['install_s2_user_bbdb_password'];
					$install_s2_user_bbdb_host         = $_POST['install_s2_user_bbdb_host'];
					$install_s2_user_bbdb_charset      = $_POST['install_s2_user_bbdb_charset'];
					$install_s2_custom_user_table      = $_POST['install_s2_custom_user_table'];
					$install_s2_custom_user_meta_table = $_POST['install_s2_custom_user_meta_table'];
				}
			}
		}
		
		$install_s3_name                 = '';
		
		$install_s3_schema = ( isset($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) == 'on' ) ? 'https://' : 'http://';
		if ( $bb->uri ) {
			$install_s3_uri = $bb->uri;
		} else {
			$install_s3_uri = preg_replace('|/bb-admin/.*|i', '/', $install_s3_schema . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']);
		}
		$install_s3_uri = rtrim($install_s3_uri, '/') . '/';
		
		$install_s3_keymaster_user_login = '';
		$install_s3_keymaster_user_email = '';
		$install_s3_forum_name           = '';
		
		$install_s3_keymaster_user_login_selected = array();
		
		$install_s3_input_class = array(
			'install_s3_name'                 => '',
			'install_s3_uri'                  => '',
			'install_s3_keymaster_user_login' => '',
			'install_s3_keymaster_user_email' => '',
			'install_s3_forum_name'           => ''
		);
		$install_s3_input_error = $install_s3_error_class;
		
		// If the form is posted
		if ( strtolower($_SERVER['REQUEST_METHOD']) == 'post' && !$post_from_last_step ) {
			
			// Initialise an error array
			$install_s3_errors = array();
			
			// Retrieve the site settings form data, trim and validate it
			$install_s3_name                        = trim($_POST['install_s3_name']);
			$install_s3_errors['install_s3_name'][] = empty($install_s3_name) ? 'empty' : false;
			$install_s3_uri                         = trim($_POST['install_s3_uri']);
			$install_s3_errors['install_s3_uri'][]  = empty($install_s3_uri) ? 'empty' : false;
			if ($parsed = parse_url($install_s3_uri)) {
				$install_s3_errors['install_s3_uri'][] = preg_match('/https?/i', $parsed['scheme']) ? false : 'urlscheme';
				$install_s3_errors['install_s3_uri'][] = empty($parsed['host']) ? 'urlhost' : false;
			} else {
				$install_s3_errors['install_s3_uri'][] = 'urlparse';
			}
			
			$install_s3_keymaster_user_login                        = trim($_POST['install_s3_keymaster_user_login']);
			$install_s3_errors['install_s3_keymaster_user_login'][] = empty($install_s3_keymaster_user_login) ? 'empty' : false;
			$install_s3_keymaster_user_login_clean                  = sanitize_user($install_s3_keymaster_user_login, true);
			if ($install_s3_keymaster_user_login != $install_s3_keymaster_user_login_clean) {
				$install_s3_errors['install_s3_keymaster_user_login'][] = 'userlogin';
			}
			$install_s3_keymaster_user_login = $install_s3_keymaster_user_login_clean;
			$install_s3_keymaster_user_login_selected[$install_s3_keymaster_user_login] = ' selected="selected"';
			
			// bb_verify_email() needs this
			require_once(BBPATH . BBINC . 'registration-functions.php');
			$install_s3_keymaster_user_email                        = trim($_POST['install_s3_keymaster_user_email']);
			$install_s3_errors['install_s3_keymaster_user_email'][] = empty($install_s3_keymaster_user_email) ? 'empty' : false;
			$install_s3_errors['install_s3_keymaster_user_email'][] = !bb_verify_email($install_s3_keymaster_user_email) ? 'email' : false;
			
			$install_s3_forum_name                        = trim($_POST['install_s3_forum_name']);
			$install_s3_errors['install_s3_forum_name'][] = empty($install_s3_forum_name) ? 'empty' : false;
			
			// Remove empty values from the error array
			foreach ($install_s3_errors as $input => $types) {
				$types = array_filter($types);
				if (!count($types)) {
					unset($install_s3_errors[$input]);
				}
			}
			
			// Check for errors and build error messages
			if ( count($install_s3_errors) ) {
				$step_status = 'error';
				$step_message = __('Your site settings have not been processed due to errors with the items marked below.');
				foreach ($install_s3_errors as $input => $types) {
					$install_s3_input_class[$input] = 'error';
					foreach ($types as $type) {
						switch ($type) {
							case 'empty':
								$install_s3_input_error[$input] .= '<span class="error">&bull; ' . __('This value is required to continue.') . '</span>';
								break(2);
							case 'urlparse':
								$install_s3_input_error[$input] .= '<span class="error">&bull; ' . __('This does not appear to be a valid URL.') . '</span>';
								break;
							case 'urlscheme':
								$install_s3_input_error[$input] .= '<span class="error">&bull; ' . __('The URL must begin with "http" or "https".') . '</span>';
								break;
							case 'urlhost':
								$install_s3_input_error[$input] .= '<span class="error">&bull; ' . __('The URL does not contain a host name.') . '</span>';
								break;
							case 'userlogin':
								$install_s3_input_error[$input] .= '<span class="error">&bull; ' . __('Contains disallowed characters which have been removed.') . '</span>';
								break;
							case 'email':
								$install_s3_input_error[$input] .= '<span class="error">&bull; ' . __('The user email address appears to be invalid.') . '</span>';
								break;
						}
					}
				}
				break;
			}
			// If we are not returning from the step completed screen
			if (!$step_back) {
				$step_status = 'complete';
				$step_message = __('Your site settings have been saved and we are now ready to complete the installation. So what are you waiting for?');
			}
		}
		
		break;
	
	case 4:
		
		$install_complete = false;
		
		// If the form is posted
		if ( strtolower($_SERVER['REQUEST_METHOD']) == 'post') {
			
			require_once(BBPATH . 'bb-admin/upgrade-functions.php');
			require_once(BBPATH . BBINC . 'registration-functions.php');
			require_once(BBPATH . 'bb-admin/admin-functions.php');
			
			$install_item_errors = array();
			$install_item_messages = array();
			
			// Check the referer
			bb_check_admin_referer( 'bbpress-installer' );
			$install_item_messages[] = __('Referrer is OK, beginning installation...');
			
			// Setup user table variables and constants if available
			if ($_POST['install_s2_integrate_database_toggle']) {
				
				$install_item_messages[] = '>>> ' . __('Setting up custom user table constants');
				
				if ( !empty($_POST['install_s2_wp_table_prefix']) )        $bb->wp_table_prefix =           $_POST['install_s2_wp_table_prefix'];
				if ( !empty($_POST['install_s2_user_bbdb_name']) )         define('USER_BBDB_NAME',         $_POST['install_s2_user_bbdb_name']);
				if ( !empty($_POST['install_s2_user_bbdb_user']) )         define('USER_BBDB_USER',         $_POST['install_s2_user_bbdb_user']);
				if ( !empty($_POST['install_s2_user_bbdb_password']) )     define('USER_BBDB_PASSWORD',     $_POST['install_s2_user_bbdb_password']);
				if ( !empty($_POST['install_s2_user_bbdb_host']) )         define('USER_BBDB_HOST',         $_POST['install_s2_user_bbdb_host']);
				if ( !empty($_POST['install_s2_user_bbdb_charset']) )      define('USER_BBDB_CHARSET',      $_POST['install_s2_user_bbdb_charset']);
				if ( !empty($_POST['install_s2_custom_user_table']) )      define('CUSTOM_USER_TABLE',      $_POST['install_s2_custom_user_table']);
				if ( !empty($_POST['install_s2_custom_user_meta_table']) ) define('CUSTOM_USER_META_TABLE', $_POST['install_s2_custom_user_meta_table']);
				
				// Set the new prefix for user tables
				$bbdb->set_user_prefix();
			}
			
			// Create the database
			$install_item_messages[] = "\n" . __('Step 1 - Creating database tables');
			// Return db errors
			$bbdb->return_errors();
			// Install the database
			$install_database_alterations = bb_install();
			// Show db errors
			$bbdb->show_errors();
			// If the database installed
			if ($install_database_alterations && count($install_database_alterations)) {
				// Loop through it to check for errors on each table
				foreach ($install_database_alterations as $install_database_alteration) {
					if (is_array($install_database_alteration)) {
						$install_item_messages[] = '>>> ' . $install_database_alteration['original']['message'];
						$install_item_messages[] = '>>>>>> ' . $install_database_alteration['error']['message'];
						$install_item_errors[] = $install_database_alteration['error']['message'];
					} else {
						$install_item_messages[] = '>>> ' . $install_database_alteration;
					}
				}
			} else {
				$install_item_messages[] = '>>> ' . __('Database installation failed!!!');
				$install_item_messages[] = '>>>>>> ' . __('Halting installation!');
				$install_item_errors[] = __('Database installation failed!!!');
				
				$step_status = 'error';
				$step_heading = __('Installation failed!');
				$step_message = __('The database failed to install. You may need to replace bbPress with a fresh copy and start again.');
				
				break;
			}
			
			// Integration settings passed from step 2
			// These are already validated provided that the referer checks out
			$install_item_messages[] = "\n" . __('Step 2 - WordPress integration (optional)');
			if ($_POST['install_s2_integrate_toggle']) {
				bb_update_option('wp_siteurl', $_POST['install_s2_wp_siteurl']);
				$install_item_messages[] = '>>> ' . __('WordPress address (URL):') . ' ' . $_POST['install_s2_wp_siteurl'];
				bb_update_option('wp_home', $_POST['install_s2_wp_home']);
				$install_item_messages[] = '>>> ' . __('Blog address (URL):') . ' ' . $_POST['install_s2_wp_home'];
				
				if ($_POST['install_s2_integrate_database_toggle']) {
					if ( !empty($_POST['install_s2_wp_table_prefix']) ) {
						bb_update_option('wp_table_prefix', $_POST['install_s2_wp_table_prefix']);
						$install_item_messages[] = '>>> ' . __('User database table prefix:') . ' ' . $_POST['install_s2_wp_table_prefix'];
					}
					
					if ($_POST['install_s2_advanced_toggle']) {
						if ( !empty($_POST['install_s2_user_bbdb_name']) ) {
							bb_update_option('user_bbdb_name', $_POST['install_s2_user_bbdb_name']);
							$install_item_messages[] = '>>> ' . __('User database name:') . ' ' . $_POST['install_s2_user_bbdb_name'];
						}
						if ( !empty($_POST['install_s2_user_bbdb_user']) ) {
							bb_update_option('user_bbdb_user', $_POST['install_s2_user_bbdb_user']);
							$install_item_messages[] = '>>> ' . __('User database user:') . ' ' . $_POST['install_s2_user_bbdb_user'];
						}
						if ( !empty($_POST['install_s2_user_bbdb_password']) ) {
							bb_update_option('user_bbdb_password', $_POST['install_s2_user_bbdb_password']);
							$install_item_messages[] = '>>> ' . __('User database password:') . ' ' . $_POST['install_s2_user_bbdb_password'];
						}
						if ( !empty($_POST['install_s2_user_bbdb_host']) ) {
							bb_update_option('user_bbdb_host', $_POST['install_s2_user_bbdb_host']);
							$install_item_messages[] = '>>> ' . __('User database host:') . ' ' . $_POST['install_s2_user_bbdb_host'];
						}
						if ( !empty($_POST['install_s2_user_bbdb_charset']) ) {
							bb_update_option('user_bbdb_charset',      $_POST['install_s2_user_bbdb_charset']);
							$install_item_messages[] = '>>> ' . __('User database character set:') . ' ' . $_POST['install_s2_user_bbdb_charset'];
						}
						if ( !empty($_POST['install_s2_custom_user_table']) ) {
							bb_update_option('custom_user_table',      $_POST['install_s2_custom_user_table']);
							$install_item_messages[] = '>>> ' . __('User database "user" table:') . ' ' . $_POST['install_s2_custom_user_table'];
						}
						if ( !empty($_POST['install_s2_custom_user_meta_table']) ) {
							bb_update_option('custom_user_meta_table', $_POST['install_s2_custom_user_meta_table']);
							$install_item_messages[] = '>>> ' . __('User database "user meta" table:') . ' ' . $_POST['install_s2_custom_user_meta_table'];
						}
					}
				}
			} else {
				$install_item_messages[] = '>>> ' . __('Integration not enabled');
			}
			
			// Site settings passed from step 3
			// These are already validated provided that the referer checks out
			$install_item_messages[] = "\n" . __('Step 3 - Site settings');
			bb_update_option('name', $_POST['install_s3_name']);
			$install_item_messages[] = '>>> ' . __('Site name:') . ' ' . $_POST['install_s3_name'];
			bb_update_option('uri', $_POST['install_s3_uri']);
			$install_item_messages[] = '>>> ' . __('Site address (URL):') . ' ' . $_POST['install_s3_uri'];
			bb_update_option('admin_email', $_POST['install_s3_keymaster_user_email']);
			$install_item_messages[] = '>>> ' . __('Admin email address:') . ' ' . $_POST['install_s3_keymaster_user_email'];
			
			// Create the key master
			if ( $_POST['install_s2_integrate_database_toggle'] ) {
				
				if ( $keymaster_user = bb_get_user_by_name($_POST['install_s3_keymaster_user_login']) ) {
					
					// The keymaster is an existing WordPress user
					
					$bb_current_user = bb_set_current_user($keymaster_user->ID);
					$bb_current_user->set_role('keymaster');
					$keymaster_type = 'wp';
					$keymaster_user_login = $_POST['install_s3_keymaster_user_login'];
					$keymaster_password = __('Your WordPress password');
					$install_item_messages[] = '>>> ' . __('Key master role assigned to WordPress user');
					$install_item_messages[] = '>>>>>> ' . __('Username:') . ' ' . $keymaster_user_login;
					$install_item_messages[] = '>>>>>> ' . __('Email address:') . ' ' . $_POST['install_s3_keymaster_user_email'];
					
				} else {
					$install_item_messages[] = '>>> ' . __('Key master role could not be assigned to WordPress user!');
					$install_item_messages[] = '>>>>>> ' . __('Halting installation!');
					$install_item_errors[] = __('Key master could not be created!');
					
					$step_status = 'error';
					$step_heading = __('Installation failed!');
					$step_message = __('The key master could not be assigned. You may need to replace bbPress with a fresh copy and start again.');
					
					break;
				}
				
				
			} else {
				
				// The keymaster is brand new
				
				// Helper function to let us know the password that was created
				function bb_get_keymaster_password($user_id, $pass) {
					global $keymaster_password;
					$keymaster_password = $pass;
				}
				add_action('bb_new_user', 'bb_get_keymaster_password', 10, 2);
				
				// Create the new user (automattically given key master role when BB_INSTALLING is true)
				if ($keymaster_user_id = bb_new_user($_POST['install_s3_keymaster_user_login'], $_POST['install_s3_keymaster_user_email'], '')) {
					$bb_current_user = bb_set_current_user( $keymaster_user_id );
					$keymaster_type = 'bb';
					$keymaster_user_login = $_POST['install_s3_keymaster_user_login'];
					$install_item_messages[] = '>>> ' . __('Key master created');
					$install_item_messages[] = '>>>>>> ' . __('Username:') . ' ' . $keymaster_user_login;
					$install_item_messages[] = '>>>>>> ' . __('Email address:') . ' ' . $_POST['install_s3_keymaster_user_email'];
					$install_item_messages[] = '>>>>>> ' . __('Password:') . ' ' . $keymaster_password;
				} else {
					$install_item_messages[] = '>>> ' . __('Key master could not be created!');
					$install_item_messages[] = '>>>>>> ' . __('Halting installation!');
					$install_item_errors[] = __('Key master could not be created!');
					
					$step_status = 'error';
					$step_heading = __('Installation failed!');
					$step_message = __('The key master could not be created. You may need to replace bbPress with a fresh copy and start again.');
					
					break;
				}
				
			}
			
			if ( bb_new_forum( array('forum_name' => $_POST['install_s3_forum_name']) ) ) {
				$install_item_messages[] = '>>> ' . __('Forum name:') . ' ' . $_POST['install_s3_forum_name'];
				bb_new_topic(__('Your first topic'), 1, 'bbPress');
				$install_item_messages[] = '>>>>>> ' . __('Topic:') . ' ' . __('Your first topic');
				bb_new_post(1, __('First Post!  w00t.'));
				$install_item_messages[] = '>>>>>>>>> ' . __('Post:') . ' ' . __('First Post!  w00t.');
			} else {
				$install_item_messages[] = '>>> ' . __('Forum could not be created!');
				$install_item_errors[] = __('Forum could not be created!');
			}
			
			if ($keymaster_user_login) {
				
				$keymaster_email_message = <<<EOF
Your new bbPress site has been successfully set up at:

%1\$s

You can log in to the key master account with the following information:

Username: %2\$s
Password: %3\$s

We hope you enjoy your new forums. Thanks!

--The bbPress Team
http://bbpress.org/

EOF;
				$keymaster_email_message = sprintf(__($keymaster_email_message), bb_get_option( 'uri' ), $keymaster_user_login, $keymaster_password);
				
				if ( bb_mail($_POST['install_s3_keymaster_user_email'], 'Subject', $keymaster_email_message) ) {
					$install_item_messages[] = '>>> ' . __('Key master email sent');
				} else {
					$install_item_messages[] = '>>> ' . __('Key master email not sent!');
					$install_item_errors[] = __('Key master email not sent!');
				}
				
			}
			
			if (count($install_item_errors)) {
				$step_status = 'error';
				$install_complete = true;
				$step_heading = __('Installation completed with some errors!');
				$step_message = __('Your installation completed with some minor errors. This is usually due to some database tables already existing, which is common for installations that are integrated with WordPress.');
				$install_item_messages[] = "\n" . __('There were some errors encountered during installation!');
			} else {
				$step_status = 'complete';
				$install_complete = true;
				$step_heading = __('Installation complete!');
				$step_message = __('Your installation completed successfully.<br />Check below for login details.');
				$install_item_messages[] = "\n" . __('Installation complete!');
			}
		}
		
		break;
}

// Set the right steps class to open
$step_class = array_fill(1, 4, 'closed');
$step_class[$step] = 'open';


if ($step < 1) {
	$title_step = __('Welcome');
} elseif ($step === 4) {
	$title_step = __('Finished');
} else {
	$title_step = sprintf( __('Step %s'), $step );
}

nocache_headers();
bb_install_header( sprintf( __('bbPress installation &rsaquo; %s'), $title_step), __('Welcome to bbPress installation') );
?>
		<script type="text/javascript" charset="utf-8">
			function toggleAdvanced(toggle, target) {
				var targetObj = document.getElementById(target);
				if (toggle.checked) {
					targetObj.style.display = 'block';
				} else {
					targetObj.style.display = 'none';
				}
			}
			function toggleSubmit(toggle, target, offText, onText) {
				var targetObj = document.getElementById(target);
				if (toggle.checked) {
					targetObj.value = onText;
				} else {
					targetObj.value = offText;
				}
			}
		</script>
<?php
switch ($step) {
	case -2:
?>
		<p>
			<?php _e('The installer has detected an old <code>config.php</code> file in your installation.'); ?>
		</p>
		<p class="last">
			<?php _e('You can either rename it to <code>bb-config.php</code> or remove it and run the <a href="install.php">installer</a> again.'); ?>
		</p>
<?php
		break;
	
	case -1:
?>
		<p>
			<?php _e('bbPress is already installed, but appears to require an upgrade.'); ?>
		</p>
		<p class="last">
			<?php printf(__('Perhaps you meant to run the <a href="%s">upgrade script</a> instead?'), $bb->uri . 'bb-admin/upgrade.php'); ?>
		</p>
<?php
		break;
	
	case 0:
		if (!$config_exists) {
?>
		<p>
			<?php _e('There doesn\'t seem to be a <code>bb-config.php</code> file. This usually means that you want to install bbPress.'); ?>
		</p>
<?php
		}
?>
		<p>
			<?php _e('We\'re now going to go through a few steps to get you up and running.'); ?>
		</p>
		<p class="last">
			<?php _e('Ready? Then <a href="install.php?step=1">let\'s get started!</a>'); ?>
		</p>
<?php
		break;
	
	default:
?>
		<div id="step1" class="<?php echo($step_class[1]); ?>">
			<div>
				<h2><?php _e('Step 1 - Database configuration'); ?></h2>
<?php
		if ($step > 1) {
?>
				<p class="status"><?php _e('&laquo; completed'); ?></p>
<?php
		}
		
		if ($step === 1) {
?>
				<p>
					<?php _e('Here you need to enter your database connection details. The installer will attempt to create a file called <code>bb-config.php</code> in the root directory of your bbPress installation.'); ?>
				</p>
				<p>
					<?php _e('If you\'re not sure what to put here, contact your web hosting provider.'); ?>
				</p>
<?php
			if (isset($step_message)) {
				$step_status_class = $config_text ? 'error' : $step_status;
				$step_status_title = $step_status_class == 'error' ? __('Warning') : __('Step completed');
?>
				<p class="<?php echo $step_status_class; ?>">
					<span class="first" title="<?php echo $step_status_title; ?>">!</span> <?php echo $step_message; ?>
				</p>
<?php
			}
			
			if ($step_status != 'complete') {
?>
				<form action="install.php?step=1" method="post">
					<fieldset>
						<?php bb_nonce_field( 'bbpress-installer' ); ?>
						<label for="install_s1_bbdb_name">
							<?php _e('Database name:'); ?>
							<input class="text" name="install_s1_bbdb_name" id="install_s1_bbdb_name" value="<?php echo attribute_escape($install_s1_bbdb_name); ?>" />
						</label>
						<p class="note">
							<?php _e('The name of the database in which you want to run bbPress.'); ?>
						</p>
						<label for="install_s1_bbdb_user">
							<?php _e('Database user:'); ?>
							<input class="text" name="install_s1_bbdb_user" id="install_s1_bbdb_user" value="<?php echo attribute_escape($install_s1_bbdb_user); ?>" />
						</label>
						<p class="note">
							<?php _e('The database user that has access to that database.'); ?>
						</p>
						<label for="install_s1_bbdb_password">
							<?php _e('Database password:'); ?>
							<input type="password" class="text" name="install_s1_bbdb_password" id="install_s1_bbdb_password" value="<?php echo attribute_escape($install_s1_bbdb_password); ?>" />
						</label>
						<p class="note">
							<?php _e('That database user\'s password.'); ?>
						</p>
						<label for="install_s1_advanced_toggle">
							<?php _e('Show advanced settings:')?>
							<input class="checkbox" type="checkbox" name="install_s1_advanced_toggle" id="install_s1_advanced_toggle" value="1" onclick="toggleAdvanced(this, 'install_s1_advanced');"<?php echo $install_s1_advanced_toggle_checked; ?> />
						</label>
						<p class="note">
							<?php _e('99% of the time these settings will not have to be changed.'); ?>
						</p>
						<div class="advanced" id="install_s1_advanced" style="display:<?php echo $install_s1_advanced_display; ?>;">
							<label for="install_s1_bbdb_host">
								<?php _e('Database host:'); ?>
								<input class="text" name="install_s1_bbdb_host" id="install_s1_bbdb_host" value="<?php echo attribute_escape($install_s1_bbdb_host); ?>" />
							</label>
							<p class="note">
								<?php _e('The domain name or IP address of the server where the database is located. If the database is on the same server as the web site, then this probably should remain <strong>localhost</strong>.'); ?>
							</p>
							<label for="install_s1_bbdb_charset">
								<?php _e('Database character set:'); ?>
								<input class="text" name="install_s1_bbdb_charset" id="install_s1_bbdb_charset" value="<?php echo attribute_escape($install_s1_bbdb_charset); ?>" />
							</label>
							<p class="note">
								<?php _e('The best choice is <strong>utf8</strong>, but you will need to match the character set which you created the database with.'); ?>
							</p>
							<label for="install_s1_bbdb_collate">
								<?php _e('Database character collation:'); ?>
								<input class="text" name="install_s1_bbdb_collate" id="install_s1_bbdb_collate" value="<?php echo attribute_escape($install_s1_bbdb_collate); ?>" />
							</label>
							<p class="note">
								<?php _e('The character collation value set when the database was created.'); ?>
							</p>
							<label for="install_s1_bb_table_prefix">
								<?php _e('Table name prefix:'); ?>
								<input class="text" name="install_s1_bb_table_prefix" id="install_s1_bb_table_prefix" value="<?php echo attribute_escape($install_s1_bb_table_prefix); ?>" />
							</label>
							<p class="note">
								<?php _e('If you are running multiple bbPress installations in a single database, you will probably want to change this.'); ?>
							</p>
						</div>
					</fieldset>
					<fieldset class="buttons">
						<label for="install_s1_submit" class="forward">
							<input class="button" type="submit" id="install_s1_submit" value="<?php _e('Save database configuration file &raquo;'); ?>" />
						</label>
					</fieldset>
				</form>
<?php
			} elseif ($config_text) {
?>
				<form action="install.php?step=1" method="post">
					<fieldset>
						<?php bb_nonce_field( 'bbpress-installer' ); ?>
						<input type="hidden" name="install_s1_bbdb_name" id="install_s1_bbdb_name" value="<?php echo attribute_escape($install_s1_bbdb_name); ?>" />
						<input type="hidden" name="install_s1_bbdb_user" id="install_s1_bbdb_user" value="<?php echo attribute_escape($install_s1_bbdb_user); ?>" />
						<input type="hidden" name="install_s1_bbdb_password" id="install_s1_bbdb_password" value="<?php echo attribute_escape($install_s1_bbdb_password); ?>" />
						<input type="hidden" name="install_s1_advanced_toggle" id="install_s1_advanced_toggle" value="<?php echo attribute_escape($install_s1_advanced_toggle); ?>" />
						<input type="hidden" name="install_s1_bbdb_host" id="install_s1_bbdb_host" value="<?php echo attribute_escape($install_s1_bbdb_host); ?>" />
						<input type="hidden" name="install_s1_bbdb_charset" id="install_s1_bbdb_charset" value="<?php echo attribute_escape($install_s1_bbdb_charset); ?>" />
						<input type="hidden" name="install_s1_bbdb_collate" id="install_s1_bbdb_collate" value="<?php echo attribute_escape($install_s1_bbdb_collate); ?>" />
						<input type="hidden" name="install_s1_bb_table_prefix" id="install_s1_bb_table_prefix" value="<?php echo attribute_escape($install_s1_bb_table_prefix); ?>" />
						<label for="install_s1_config_text">
							<?php _e('Contents for <code>bb-config.php</code>:'); ?>
							<textarea id="install_s1_config_text"><?php echo attribute_escape($config_text); ?></textarea>
						</label>
						<p class="note">
							<?php _e('Once you have created the configuration file, you can check for it below.'); ?>
						</p>
					</fieldset>
					<fieldset class="buttons">
						<label for="install_s1_back" class="back">
							<input class="button" type="submit" name="install_s1_back" id="install_s1_back" value="<?php _e('&laquo; Go back'); ?>" />
						</label>
						<label for="install_s1_forward" class="forward">
							<input class="button" type="submit" id="install_s1_forward" value="<?php _e('Check for configuration file &raquo;'); ?>" />
						</label>
					</fieldset>
				</form>
<?php
			} else {
?>
				<form action="install.php" method="get">
					<fieldset class="buttons">
						<?php bb_nonce_field( 'bbpress-installer' ); ?>
						<input type="hidden" name="step" value="2" />
						<label for="install_s1_next" class="forward">
							<input class="button" type="submit" id="install_s1_next" value="<?php _e('Go to step 2 &raquo;'); ?>" />
						</label>
					</fieldset>
				</form>
<?php
			}
		}
?>
			</div>
		</div>
		<div id="step2" class="<?php echo($step_class[2]); ?>">
			<div>
				<h2><?php _e('Step 2 - WordPress integration (optional)'); ?></h2>
<?php
		if ($step > 2) {
?>
				<p class="status"><?php echo $step2_status; ?></p>
<?php
		}
		
		if ($step === 2) {
?>
				<p>
					<?php _e('bbPress can integrate login and user data seamlessly with WordPress. You can safely skip this section if you do not wish to integrate with an existing WordPress install.'); ?>
				</p>
<?php
			if (isset($step_message)) {
				$step_status_class = $config_text ? 'error' : $step_status;
				$step_status_title = $step_status_class == 'error' ? __('Warning') : __('Step completed');
?>
				<p class="<?php echo $step_status_class; ?>">
					<span class="first" title="<?php echo $step_status_title; ?>">!</span> <?php echo $step_message; ?>
				</p>
<?php
			}
			
			if ($step_status != 'complete') {
?>
				<form action="install.php?step=2" method="post">
					<fieldset>
						<?php bb_nonce_field( 'bbpress-installer' ); ?>
						<label for="install_s2_integrate_toggle">
							<?php _e('Add integration settings:')?>
							<input class="checkbox" type="checkbox" name="install_s2_integrate_toggle" id="install_s2_integrate_toggle" value="1" onclick="toggleAdvanced(this, 'install_s2_integrate'); toggleSubmit(this, 'install_s2_submit', '<?php echo(addslashes(__('Skip WordPress integration &raquo;'))); ?>', '<?php echo(addslashes(__('Save WordPress integration settings &raquo;'))); ?>');"<?php echo $install_s2_integrate_toggle_checked; ?> />
						</label>
						<p class="note">
							<?php _e('If you want to integrate bbPress with an existing WordPress installation.'); ?>
						</p>
					</fieldset>
					<div class="advanced" id="install_s2_integrate" style="display:<?php echo $install_s2_integrate_display; ?>;">
						<fieldset>
							<legend><?php _e('Cookies'); ?></legend>
							<label for="install_s2_wp_siteurl" class="<?php echo($install_s2_input_class['install_s2_wp_siteurl']); ?>">
								<?php _e('WordPress address (URL):'); ?>
								<?php echo($install_s2_input_error['install_s2_wp_siteurl']); ?>
								<input class="text" name="install_s2_wp_siteurl" id="install_s2_wp_siteurl" value="<?php echo attribute_escape($install_s2_wp_siteurl); ?>" />
							</label>
							<p class="note">
								<?php _e('This value should exactly match the <strong>WordPress address (URL)</strong> setting in your WordPress general options.'); ?>
							</p>
							<label for="install_s2_wp_home" class="<?php echo($install_s2_input_class['install_s2_wp_home']); ?>">
								<?php _e('Blog address (URL):'); ?>
								<?php echo($install_s2_input_error['install_s2_wp_home']); ?>
								<input class="text" name="install_s2_wp_home" id="install_s2_wp_home" value="<?php echo attribute_escape($install_s2_wp_home); ?>" />
							</label>
							<p class="note">
								<?php _e('This value should exactly match the <strong>Blog address (URL)</strong> setting in your WordPress general options.'); ?>
							</p>
						</fieldset>
						<fieldset>
							<label for="install_s2_integrate_database_toggle">
								<?php _e('Add user database integration settings:')?>
								<input class="checkbox" type="checkbox" name="install_s2_integrate_database_toggle" id="install_s2_integrate_database_toggle" value="1" onclick="toggleAdvanced(this, 'install_s2_integrate_database');"<?php echo $install_s2_integrate_database_toggle_checked; ?> />
							</label>
							<p class="note">
								<?php _e('If you want to share user tables with an existing WordPress installation.'); ?>
							</p>
						</fieldset>
						<div class="advanced" id="install_s2_integrate_database" style="display:<?php echo $install_s2_integrate_database_display; ?>;">
							<fieldset>
								<legend><?php _e('User database'); ?></legend>
								<label for="install_s2_wp_table_prefix" class="<?php echo($install_s2_input_class['install_s2_wp_table_prefix']); ?>">
									<?php _e('User database table prefix:'); ?>
									<input class="text" name="install_s2_wp_table_prefix" id="install_s2_wp_table_prefix" value="<?php echo attribute_escape($install_s2_wp_table_prefix); ?>" />
								</label>
								<p class="note">
									<?php _e('If your bbPress and WordPress installations share the same database, then this is the same value as <code>$wp_table_prefix</code> in your WordPress <code>wp-config.php</code> file.'); ?>
								</p>
								<p class="note">
									<?php _e('In any case, it is usually <strong>wp_</strong>'); ?>
								</p>
								<label for="install_s2_advanced_toggle">
									<?php _e('Show advanced database settings:'); ?>
									<input class="checkbox" type="checkbox" name="install_s2_advanced_toggle" id="install_s2_advanced_toggle" value="1" onclick="toggleAdvanced(this, 'install_s2_advanced');"<?php echo $install_s2_advanced_toggle_checked; ?> />
								</label>
								<p class="note">
									<?php _e('If your bbPress and WordPress installation do not share the same database, then you will need to add advanced settings.'); ?>
								</p>
							</fieldset>
							<div class="advanced" id="install_s2_advanced" style="display:<?php echo $install_s2_advanced_display; ?>;">
								<fieldset>
									<legend><?php _e('Separate user database settings'); ?></legend>
									<p><?php _e('Most of the time these settings are <em>not</em> required. Look before you leap!'); ?></p>
									<p><?php _e('All settings except for the character set must be specified.'); ?></p>
									<label for="install_s2_user_bbdb_name" class="<?php echo($install_s2_input_class['install_s2_user_bbdb_name']); ?>">
										<?php _e('User database name:'); ?>
										<?php echo($install_s2_input_error['install_s2_user_bbdb_name']); ?>
										<input class="text" name="install_s2_user_bbdb_name" id="install_s2_user_bbdb_name" value="<?php echo attribute_escape($install_s2_user_bbdb_name); ?>" />
									</label>
									<p class="note">
										<?php _e('The name of the database in which your user tables reside.'); ?>
									</p>
									<label for="install_s2_user_bbdb_user" class="<?php echo($install_s2_input_class['install_s2_user_bbdb_user']); ?>">
										<?php _e('User database user:'); ?>
										<?php echo($install_s2_input_error['install_s2_user_bbdb_user']); ?>
										<input class="text" name="install_s2_user_bbdb_user" id="install_s2_user_bbdb_user" value="<?php echo attribute_escape($install_s2_user_bbdb_user); ?>" />
									</label>
									<p class="note">
										<?php _e('The database user that has access to that database.'); ?>
									</p>
									<label for="install_s2_user_bbdb_password" class="<?php echo($install_s2_input_class['install_s2_user_bbdb_password']); ?>">
										<?php _e('User database password:'); ?>
										<?php echo($install_s2_input_error['install_s2_user_bbdb_password']); ?>
										<input type="password" class="text" name="install_s2_user_bbdb_password" id="install_s2_user_bbdb_password" value="<?php echo attribute_escape($install_s2_user_bbdb_password); ?>" />
									</label>
									<p class="note">
										<?php _e('That database user\'s password.'); ?>
									</p>
									<label for="install_s2_user_bbdb_host" class="<?php echo($install_s2_input_class['install_s2_user_bbdb_host']); ?>">
										<?php _e('User database host:'); ?>
										<?php echo($install_s2_input_error['install_s2_user_bbdb_host']); ?>
										<input class="text" name="install_s2_user_bbdb_host" id="install_s2_user_bbdb_host" value="<?php echo attribute_escape($install_s2_user_bbdb_host); ?>" />
									</label>
									<p class="note">
										<?php _e('The domain name or IP address of the server where the database is located. If the database is on the same server as the web site, then this probably should remain <strong>localhost</strong>.'); ?>
									</p>
									<label for="install_s2_user_bbdb_charset">
										<?php _e('User database character set:'); ?>
										<input class="text" name="install_s2_user_bbdb_charset" id="install_s2_user_bbdb_charset" value="<?php echo attribute_escape($install_s2_user_bbdb_charset); ?>" />
									</label>
									<p class="note">
										<?php _e('The best choice is <strong>utf8</strong>, but you will need to match the character set which you created the database with.'); ?>
									</p>
								</fieldset>
								<fieldset>
									<legend><?php _e('Custom user tables'); ?></legend>
									<p><?php _e('Only set these options if your integrated user tables do not fit the usual mould of <em>wp_user</em> and <em>wp_usermeta</em>.'); ?></p>
									<label for="install_s2_custom_user_table">
										<?php _e('User database "user" table:'); ?>
										<input class="text" name="install_s2_custom_user_table" id="install_s2_custom_user_table" value="<?php echo attribute_escape($install_s2_custom_user_table); ?>" />
									</label>
									<p class="note"><?php _e('The complete table name, including any prefix.'); ?></p>
									<label for="install_s2_custom_user_meta_table">
										<?php _e('User database "user meta" table:'); ?>
										<input class="text" name="install_s2_custom_user_meta_table" id="install_s2_custom_user_meta_table" value="<?php echo attribute_escape($install_s2_custom_user_meta_table); ?>" />
									</label>
									<p class="note"><?php _e('The complete table name, including any prefix.'); ?></p>
								</fieldset>
							</div>
						</div>
					</div>
					<fieldset class="buttons">
						<label for="install_s2_submit" class="forward">
							<input class="button" type="submit" id="install_s2_submit" value="<?php echo $install_s2_submit; ?>" />
						</label>
					</fieldset>
				</form>
<?php
			} else {
?>
				<form action="install.php?step=3" method="post">
					<fieldset>
						<?php bb_nonce_field( 'bbpress-installer' ); ?>
						<input type="hidden" name="install_s2_integrate_toggle" id="install_s2_integrate_toggle" value="<?php echo attribute_escape($install_s2_integrate_toggle); ?>" />
						<input type="hidden" name="install_s2_wp_siteurl" id="install_s2_wp_siteurl" value="<?php echo attribute_escape($install_s2_wp_siteurl); ?>" />
						<input type="hidden" name="install_s2_wp_home" id="install_s2_wp_home" value="<?php echo attribute_escape($install_s2_wp_home); ?>" />
						<input type="hidden" name="install_s2_integrate_database_toggle" id="install_s2_integrate_toggle" value="<?php echo attribute_escape($install_s2_integrate_database_toggle); ?>" />
						<input type="hidden" name="install_s2_wp_table_prefix" id="install_s2_wp_table_prefix" value="<?php echo attribute_escape($install_s2_wp_table_prefix); ?>" />
						<input type="hidden" name="install_s2_advanced_toggle" id="install_s2_advanced_toggle" value="<?php echo attribute_escape($install_s2_advanced_toggle); ?>" />
						<input type="hidden" name="install_s2_user_bbdb_name" id="install_s2_user_bbdb_name" value="<?php echo attribute_escape($install_s2_user_bbdb_name); ?>" />
						<input type="hidden" name="install_s2_user_bbdb_user" id="install_s2_user_bbdb_user" value="<?php echo attribute_escape($install_s2_user_bbdb_user); ?>" />
						<input type="hidden" name="install_s2_user_bbdb_password" id="install_s2_user_bbdb_password" value="<?php echo attribute_escape($install_s2_user_bbdb_password); ?>" />
						<input type="hidden" name="install_s2_user_bbdb_host" id="install_s2_user_bbdb_host" value="<?php echo attribute_escape($install_s2_user_bbdb_host); ?>" />
						<input type="hidden" name="install_s2_user_bbdb_charset" id="install_s2_user_bbdb_charset" value="<?php echo attribute_escape($install_s2_user_bbdb_charset); ?>" />
						<input type="hidden" name="install_s2_custom_user_table" id="install_s2_custom_user_table" value="<?php echo attribute_escape($install_s2_custom_user_table); ?>" />
						<input type="hidden" name="install_s2_custom_user_meta_table" id="install_s2_custom_user_meta_table" value="<?php echo attribute_escape($install_s2_custom_user_meta_table); ?>" />
					</fieldset>
					<fieldset class="buttons">
						<label for="install_s1_back" class="back">
							<input class="button" type="submit" name="install_s2_back" id="install_s2_back" value="<?php _e('&laquo; Go back'); ?>" />
						</label>
						<label for="install_s2_next" class="forward">
							<input class="button" type="submit" name="install_s2_next" id="install_s2_next" value="<?php _e('Go to step 3 &raquo;'); ?>" />
						</label>
					</fieldset>
				</form>
<?php
			}
		}
?>
			</div>
		</div>
		<div id="step3" class="<?php echo($step_class[3]); ?>">
			<div>
				<h2><?php _e('Step 3 - Site settings'); ?></h2>
<?php
		if ($step > 3) {
?>
				<p class="status"><?php echo $step3_status; ?></p>
<?php
		}
		
		if ($step === 3) {
?>
				<p>
					<?php _e('Finalize your installation by adding a name, your first user and your first forum.'); ?>
				</p>
<?php
			if (isset($step_message)) {
				$step_status_class = $config_text ? 'error' : $step_status;
				$step_status_title = $step_status_class == 'error' ? __('Warning') : __('Step completed');
?>
				<p class="<?php echo $step_status_class; ?>">
					<span class="first" title="<?php echo $step_status_title; ?>">!</span> <?php echo $step_message; ?>
				</p>
<?php
			}
			
			if ($step_status != 'complete') {
?>
				<form action="install.php?step=3" method="post">
					<fieldset>
						<?php bb_nonce_field( 'bbpress-installer' ); ?>
						
						<!-- step 2 settings -->
						<input type="hidden" name="install_s2_integrate_toggle" id="install_s2_integrate_toggle" value="<?php echo attribute_escape($install_s2_integrate_toggle); ?>" />
						<input type="hidden" name="install_s2_wp_siteurl" id="install_s2_wp_siteurl" value="<?php echo attribute_escape($install_s2_wp_siteurl); ?>" />
						<input type="hidden" name="install_s2_wp_home" id="install_s2_wp_home" value="<?php echo attribute_escape($install_s2_wp_home); ?>" />
						<input type="hidden" name="install_s2_integrate_database_toggle" id="install_s2_integrate_toggle" value="<?php echo attribute_escape($install_s2_integrate_database_toggle); ?>" />
						<input type="hidden" name="install_s2_wp_table_prefix" id="install_s2_wp_table_prefix" value="<?php echo attribute_escape($install_s2_wp_table_prefix); ?>" />
						<input type="hidden" name="install_s2_advanced_toggle" id="install_s2_advanced_toggle" value="<?php echo attribute_escape($install_s2_advanced_toggle); ?>" />
						<input type="hidden" name="install_s2_user_bbdb_name" id="install_s2_user_bbdb_name" value="<?php echo attribute_escape($install_s2_user_bbdb_name); ?>" />
						<input type="hidden" name="install_s2_user_bbdb_user" id="install_s2_user_bbdb_user" value="<?php echo attribute_escape($install_s2_user_bbdb_user); ?>" />
						<input type="hidden" name="install_s2_user_bbdb_password" id="install_s2_user_bbdb_password" value="<?php echo attribute_escape($install_s2_user_bbdb_password); ?>" />
						<input type="hidden" name="install_s2_user_bbdb_host" id="install_s2_user_bbdb_host" value="<?php echo attribute_escape($install_s2_user_bbdb_host); ?>" />
						<input type="hidden" name="install_s2_user_bbdb_charset" id="install_s2_user_bbdb_charset" value="<?php echo attribute_escape($install_s2_user_bbdb_charset); ?>" />
						<input type="hidden" name="install_s2_custom_user_table" id="install_s2_custom_user_table" value="<?php echo attribute_escape($install_s2_custom_user_table); ?>" />
						<input type="hidden" name="install_s2_custom_user_meta_table" id="install_s2_custom_user_meta_table" value="<?php echo attribute_escape($install_s2_custom_user_meta_table); ?>" />
						
						<label for="install_s3_name" class="<?php echo($install_s3_input_class['install_s3_name']); ?>">
							<?php _e('Site name:')?>
							<?php echo($install_s3_input_error['install_s3_name']); ?>
							<input class="text" name="install_s3_name" id="install_s3_name" value="<?php echo attribute_escape($install_s3_name); ?>" />
						</label>
						<p class="note">
							<?php _e('This is what you are going to call your bbPress installation.'); ?>
						</p>
						<label for="install_s3_uri" class="<?php echo($install_s3_input_class['install_s3_uri']); ?>">
							<?php _e('Site address (URL):')?>
							<?php echo($install_s3_input_error['install_s3_uri']); ?>
							<input class="text" name="install_s3_uri" id="install_s3_uri" value="<?php echo attribute_escape($install_s3_uri); ?>" />
						</label>
						<p class="note">
							<?php _e('We have attempted to guess this, it\'s usually correct, but change it here if you wish.'); ?>
						</p>
					</fieldset>
					<fieldset>
						<legend><?php _e('"Key master" account'); ?></legend>
<?php
				$wp_administrators_dropdown = false;
				
				// If there are WordPress integration database settings
				if ($install_s2_integrate_database_toggle) {
					
					// Get the existing WordPress admin users
					
					// Setup variables and constants if available
					if ( !empty($install_s2_wp_table_prefix) )        $bb->wp_table_prefix = $install_s2_wp_table_prefix;
					if ( !empty($install_s2_user_bbdb_name) )         define('USER_BBDB_NAME',         $install_s2_user_bbdb_name);
					if ( !empty($install_s2_user_bbdb_user) )         define('USER_BBDB_USER',         $install_s2_user_bbdb_user);
					if ( !empty($install_s2_user_bbdb_password) )     define('USER_BBDB_PASSWORD',     $install_s2_user_bbdb_password);
					if ( !empty($install_s2_user_bbdb_host) )         define('USER_BBDB_HOST',         $install_s2_user_bbdb_host);
					if ( !empty($install_s2_user_bbdb_charset) )      define('USER_BBDB_CHARSET',      $install_s2_user_bbdb_charset);
					if ( !empty($install_s2_custom_user_table) )      define('CUSTOM_USER_TABLE',      $install_s2_custom_user_table);
					if ( !empty($install_s2_custom_user_meta_table) ) define('CUSTOM_USER_META_TABLE', $install_s2_custom_user_meta_table);
					
					// Set the new prefix for user tables
					$bbdb->set_user_prefix();
					
					$wp_administrator_meta_key = $bb->wp_table_prefix . 'capabilities';
					$wp_administrator_query = <<<EOQ
						SELECT
							user_login, user_email, display_name
						FROM
							$bbdb->users
						LEFT JOIN
							$bbdb->usermeta ON
							$bbdb->users.ID = $bbdb->usermeta.user_id
						WHERE
							meta_key = '$wp_administrator_meta_key' AND
							meta_value LIKE '%administrator%' AND
							user_email IS NOT NULL AND
							user_email != ''
						ORDER BY
							user_login;
EOQ;
					if ( $wp_administrators = (array) $bbdb->get_results( $wp_administrator_query ) ) {
						if ( count($wp_administrators) ) {
							$wp_administrators_emails = array();
							$wp_administrators_dropdown = '<select name="install_s3_keymaster_user_login" id="install_s3_keymaster_user_login" onchange="changeAdminEmail(this, \'install_s3_keymaster_user_email\');">' . "\n";
							$wp_administrators_dropdown .= '<option value=""></option>' . "\n";
							foreach ($wp_administrators as $wp_administrator) {
								$wp_administrators_emails[$wp_administrator->user_login] = $wp_administrator->user_email;
								$wp_administrators_dropdown .= '<option value="' . $wp_administrator->user_login . '"';
								$wp_administrators_dropdown .= $install_s3_keymaster_user_login_selected[$wp_administrator->user_login] . '>';
								$wp_administrators_dropdown .= $wp_administrator->display_name . ' (' . $wp_administrator->user_login . ')';
								$wp_administrators_dropdown .= '</option>' . "\n";
							}
							$wp_administrators_dropdown .= '</select>' . "\n";
						}
					}
				}
				
				if ($wp_administrators_dropdown) {
?>
						<script type="text/javascript" charset="utf-8">
							function changeAdminEmail(selectObj, target) {
								var emailMap = new Array;
<?php
					foreach ($wp_administrators_emails as $user_login => $user_email) {
?>
								emailMap['<?php echo $user_login; ?>'] = '<?php echo $user_email; ?>';
<?php
					}
?>
								var targetObj = document.getElementById(target);
								var selectedAdmin = selectObj.options[selectObj.selectedIndex].value;
								targetObj.value = emailMap[selectedAdmin];
							}
						</script>
						<label for="install_s3_keymaster_user_login" class="<?php echo($install_s3_input_class['install_s3_keymaster_user_login']); ?>">
							<?php _e('Integrated WordPress user name:')?>
							<?php echo($install_s3_input_error['install_s3_keymaster_user_login']); ?>
							<?php echo $wp_administrators_dropdown; ?>
						</label>
						<p class="note">
							<?php _e('This is the user login for the initial bbPress administrator (known as a "key master").'); ?>
						</p>
						<p class="note">
							<?php _e('Select from the list of existing administrators in your integrated WordPress installation. The login details will be emailed to this user.'); ?>
						</p>
						<input type="hidden" name="install_s3_keymaster_user_email" id="install_s3_keymaster_user_email" value="<?php echo attribute_escape($install_s3_keymaster_user_email); ?>" />
<?php
				} else {
?>
						<label for="install_s3_keymaster_user_login" class="<?php echo($install_s3_input_class['install_s3_keymaster_user_login']); ?>">
							<?php _e('Username:')?>
							<?php echo($install_s3_input_error['install_s3_keymaster_user_login']); ?>
							<input class="text" name="install_s3_keymaster_user_login" id="install_s3_keymaster_user_login" value="<?php echo attribute_escape($install_s3_keymaster_user_login); ?>" />
						</label>
						<p class="note">
							<?php _e('This is the user login for the initial bbPress administrator (known as a "key master").'); ?>
						</p>
						<label for="install_s3_keymaster_user_email" class="<?php echo($install_s3_input_class['install_s3_keymaster_user_email']); ?>">
							<?php _e('Email address:')?>
							<?php echo($install_s3_input_error['install_s3_keymaster_user_email']); ?>
							<input class="text" name="install_s3_keymaster_user_email" id="install_s3_keymaster_user_email" value="<?php echo attribute_escape($install_s3_keymaster_user_email); ?>" />
						</label>
						<p class="note">
							<?php _e('The login details will be emailed to this address.'); ?>
						</p>
<?php
				}
?>
					</fieldset>
					<fieldset>
						<legend><?php _e('First forum'); ?></legend>
						<label for="install_s3_forum_name" class="<?php echo($install_s3_input_class['install_s3_forum_name']); ?>">
							<?php _e('Forum name:')?>
							<?php echo($install_s3_input_error['install_s3_forum_name']); ?>
							<input class="text" name="install_s3_forum_name" id="install_s3_forum_name" value="<?php echo attribute_escape($install_s3_forum_name); ?>" />
						</label>
						<p class="note">
							<?php _e('This can be changed after installation, so don\'t worry about it too much.'); ?>
						</p>
					</fieldset>
					<fieldset class="buttons">
						<label for="install_s3_submit" class="forward">
							<input class="button" type="submit" id="install_s3_submit" value="<?php _e('Save site settings &raquo;'); ?>" />
						</label>
					</fieldset>
				</form>
<?php
			} else {
?>
				<form action="install.php?step=4" method="post">
					<fieldset>
						<?php bb_nonce_field( 'bbpress-installer' ); ?>
						
						<!-- step 2 settings -->
						<input type="hidden" name="install_s2_integrate_toggle" id="install_s2_integrate_toggle" value="<?php echo attribute_escape($install_s2_integrate_toggle); ?>" />
						<input type="hidden" name="install_s2_wp_siteurl" id="install_s2_wp_siteurl" value="<?php echo attribute_escape($install_s2_wp_siteurl); ?>" />
						<input type="hidden" name="install_s2_wp_home" id="install_s2_wp_home" value="<?php echo attribute_escape($install_s2_wp_home); ?>" />
						<input type="hidden" name="install_s2_integrate_database_toggle" id="install_s2_integrate_toggle" value="<?php echo attribute_escape($install_s2_integrate_database_toggle); ?>" />
						<input type="hidden" name="install_s2_wp_table_prefix" id="install_s2_wp_table_prefix" value="<?php echo attribute_escape($install_s2_wp_table_prefix); ?>" />
						<input type="hidden" name="install_s2_advanced_toggle" id="install_s2_advanced_toggle" value="<?php echo attribute_escape($install_s2_advanced_toggle); ?>" />
						<input type="hidden" name="install_s2_user_bbdb_name" id="install_s2_user_bbdb_name" value="<?php echo attribute_escape($install_s2_user_bbdb_name); ?>" />
						<input type="hidden" name="install_s2_user_bbdb_user" id="install_s2_user_bbdb_user" value="<?php echo attribute_escape($install_s2_user_bbdb_user); ?>" />
						<input type="hidden" name="install_s2_user_bbdb_password" id="install_s2_user_bbdb_password" value="<?php echo attribute_escape($install_s2_user_bbdb_password); ?>" />
						<input type="hidden" name="install_s2_user_bbdb_host" id="install_s2_user_bbdb_host" value="<?php echo attribute_escape($install_s2_user_bbdb_host); ?>" />
						<input type="hidden" name="install_s2_user_bbdb_charset" id="install_s2_user_bbdb_charset" value="<?php echo attribute_escape($install_s2_user_bbdb_charset); ?>" />
						<input type="hidden" name="install_s2_custom_user_table" id="install_s2_custom_user_table" value="<?php echo attribute_escape($install_s2_custom_user_table); ?>" />
						<input type="hidden" name="install_s2_custom_user_meta_table" id="install_s2_custom_user_meta_table" value="<?php echo attribute_escape($install_s2_custom_user_meta_table); ?>" />
						
						<!-- step 3 settings -->
						<input type="hidden" name="install_s3_name" id="install_s3_name" value="<?php echo attribute_escape($install_s3_name); ?>" />
						<input type="hidden" name="install_s3_uri" id="install_s3_uri" value="<?php echo attribute_escape($install_s3_uri); ?>" />
						<input type="hidden" name="install_s3_keymaster_user_login" id="install_s3_keymaster_user_login" value="<?php echo attribute_escape($install_s3_keymaster_user_login); ?>" />
						<input type="hidden" name="install_s3_keymaster_user_email" id="install_s3_keymaster_user_email" value="<?php echo attribute_escape($install_s3_keymaster_user_email); ?>" />
						<input type="hidden" name="install_s3_forum_name" id="install_s3_forum_name" value="<?php echo attribute_escape($install_s3_forum_name); ?>" />
					</fieldset>
					<fieldset class="buttons">
						<label for="install_s3_back" class="back">
							<input class="button" type="submit" name="install_s3_back" id="install_s3_back" value="<?php _e('&laquo; Go back'); ?>" />
						</label>
						<label for="install_s3_next" class="forward">
							<input class="button" type="submit" id="install_s3_next" value="<?php _e('Complete the installation &raquo;'); ?>" />
						</label>
					</fieldset>
				</form>
<?php
			}
		}
?>
			</div>
		</div>
		<div id="result" class="<?php echo($step_class[4]); ?>">
<?php
		if ($step === 4) {
?>
			<div>
				<h2><?php echo $step_heading; ?></h2>
<?php
			if (isset($step_message)) {
				$step_status_class = $config_text ? 'error' : $step_status;
				$step_status_title = $step_status_class == 'error' ? __('Warning') : __('Step completed');
?>
				<p class="<?php echo $step_status_class; ?>">
					<span class="first" title="<?php echo $step_status_title; ?>">!</span> <?php echo $step_message; ?>
				</p>
<?php
			}
			
			if ($install_complete) {
?>
				<p><?php _e('Now you can log in with the following details:'); ?></p>
				<dl>
					<dt><?php _e('Username:'); ?></dt>
					<dd><code><?php echo $keymaster_user_login ?></code></dd>
					<dt><?php _e('Password:'); ?></dt>
					<dd><code><?php echo $keymaster_password; ?></code></dd>
					<dt><?php _e('Site address:'); ?></dt>
					<dd><a href="<?php bb_option( 'uri' ); ?>"><?php bb_option( 'uri' ); ?></a></dd>
				</dl>
<?php
				if ( $keymaster_type == 'bb' ) {
?>
				<p><?php _e('<strong><em>Note that password</em></strong> carefully! It is a <em>random</em> password that was generated just for you. If you lose it, you will have to delete the tables from the database yourself, and re-install bbPress.'); ?></p>
<?php
				}
			}
?>
				<form>
					<fieldset>
						<label for="install_s4_advanced_toggle">
							<?php _e('Show installation messages:'); ?>
							<input class="checkbox" type="checkbox" name="install_s4_advanced_toggle" id="install_s4_advanced_toggle" value="1" onclick="toggleAdvanced(this, 'install_s4_advanced');" />
						</label>
						<div class="advanced" id="install_s4_advanced" style="display:none;">
<?php
			if ( $step_status == 'error' ) {
?>
							<label for="install_s4_errors">
								<?php _e('Installation errors:'); ?>
								<textarea class="short error" id="install_s4_errors"><?php echo join("\n", $install_item_errors); ?></textarea>
							</label>
<?php
			}
?>
							<label for="install_s4_progress">
								<?php _e('Installation log:'); ?>
								<textarea id="install_s4_progress"><?php echo join("\n", $install_item_messages); ?></textarea>
							</label>
						</div>
					</fieldset>
				</form>
			</div>
<?php
		}
?>
		</div>
<?php
}

bb_install_footer();
?>
