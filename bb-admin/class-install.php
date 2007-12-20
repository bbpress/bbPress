<?php
/**
 * bbPress Installation class
 *
 * @since 0.8.4
 **/
class BB_Install
{
	/**
	 * The file where the class was instantiated
	 *
	 * @var string
	 **/
	var $caller;
	
	/**
	 * The correct database library file to use
	 *
	 * @var string
	 **/
	var $db_library = 'db.php';
	
	/**
	 * Whether or not we need to load some of the includes normally loaded by bb-settings.php
	 *
	 * @var boolean
	 **/
	var $load_includes = false;
	
	/**
	 * The current step in the install process
	 *
	 * @var integer
	 **/
	var $step;
	
	/**
	 * Info about config files and their locations
	 *
	 * @var array
	 **/
	var $configs = array(
		'writable' => false,
		'bb-config.php' => false,
		'config.php' => false
	);
	
	/**
	 * An array of the current status of each step
	 *
	 * @var array
	 **/
	var $step_status = array(
		1 => 'incomplete',
		2 => 'incomplete',
		3 => 'incomplete',
		4 => 'incomplete'
	);
	
	/**
	 * An array of most strings in use, including errors
	 *
	 * @var array
	 **/
	var $strings = array();
	
	/**
	 * The data being manipulated as we go through the forms
	 *
	 * @var array
	 **/
	var $data = array();
	
	/**
	 * A boolean that can get flagged to stop posting of a form getting processed
	 *
	 * @var boolean
	 **/
	var $stop_process = false;
	
	/**
	 * BB_Install() - Constructor
	 *
	 * Just sets up a few initial values
	 *
	 * @param string $caller The full path of the file that instantiated the class
	 * @return boolean
	 **/
	function BB_Install($caller)
	{
		$this->caller = $caller;
		
		$this->set_initial_step();
		$this->define_paths();
		
		return true;
	}
	
	/**
	 * set_initial_step() - Set initial step
	 *
	 * Sets the step from the querystring and keeps it within range
	 *
	 * @return integer The calculated step
	 **/
	function set_initial_step()
	{
		// Set the step based on the $_GET value or 0
		$this->step = $_GET['step'] ? (integer) $_GET['step'] : 0;
		
		// Make sure the requested step is from 0-4
		if ($this->step < 0 || $this->step > 4) {
			$this->step = 0;
		}
		return $this->step;
	}
	
	/**
	 * prepare_strings() - Prepare text strings
	 *
	 * Sets up many of the strings to be used by the class that may
	 * be later subject to change due to processing of the forms
	 **/
	function prepare_strings()
	{
		$this->strings = array(
			-1 => array(
				'title'       => __('bbPress &rsaquo; Error'),
				'h1'          => __('Oh dear!'),
				'messages'    => array()
			),
			0 => array(
				'title'       => sprintf(__('%1$s &rsaquo; %2$s'), __('bbPress installer'), __('Welcome')),
				'h1'          => __('Welcome to the bbPress installer'),
				'messages'    => array(),
				'intro'       => array(
					__('We\'re now going to go through a few steps to get you up and running.'),
					sprintf(__('Ready? Then <a href="%s">let\'s get started!</a>'), 'install.php?step=1')
				)
			),
			1 => array(
				'title'       => sprintf(__('%1$s &rsaquo; %2$s'), __('bbPress installer'), __('Step 1')),
				'h1'          => __('Welcome to the bbPress installer'),
				'h2'          => sprintf(__('%1$s - %2$s'), __('Step 1'), __('Database configuration')),
				'status'      => '',
				'intro'       => array(
					__('Here you need to enter your database connection details. The installer will attempt to create a file called <code>bb-config.php</code> in the root directory of your bbPress installation.'),
					__('If you\'re not sure what to put here, contact your web hosting provider.')
				),
				'messages'    => array()
			),
			2 => array(
				'title'       => sprintf(__('%1$s &rsaquo; %2$s'), __('bbPress installer'), __('Step 2')),
				'h1'          => __('Welcome to the bbPress installer'),
				'h2'          => sprintf(__('%1$s - %2$s'), __('Step 2'), __('WordPress integration (optional)')),
				'status'      => __('&laquo; skipped'),
				'intro'       => array(
					__('bbPress can integrate login and user data seamlessly with WordPress. You can safely skip this section if you do not wish to integrate with an existing WordPress install.')
				),
				'messages'    => array(),
				'form_errors' => array()
			),
			3 => array(
				'title'       => sprintf(__('%1$s &rsaquo; %2$s'), __('bbPress installer'), __('Step 3')),
				'h1'          => __('Welcome to the bbPress installer'),
				'h2'          => sprintf(__('%1$s - %2$s'), __('Step 3'), __('Site settings')),
				'status'      => '',
				'intro'       => array(
					__('Finalize your installation by adding a name, your first user and your first forum.')
				),
				'messages'    => array(),
				'form_errors' => array(),
				'scripts'     => array()
			),
			4 => array(
				'title'       => sprintf(__('%1$s &rsaquo; %2$s'), __('bbPress installer'), __('Finished')),
				'h1'          => __('Welcome to the bbPress installer'),
				'h2'          => __('Installation complete!'),
				'messages'    => array()
			)
		);
	}
	
	/**
	 * check_prerequisites() - Check installation pre-requisites
	 *
	 * Checks for appropriate PHP version and MySQL extensions, also
	 * sets the db_library variable along the way
	 *
	 * @return boolean False if any pre-requisites are not met, otherwise true
	 **/
	function check_prerequisites()
	{
		if (phpversion() < '4.2') {
			$this->strings[-1]['messages']['error'][] = sprintf(__('Your server is running PHP version %s but bbPress requires at least 4.2'), phpversion());
			$this->step = -1;
		}
		
		if (!extension_loaded('mysql')) {
			if (!extension_loaded('mysqli')) {
				$this->strings[-1]['messages']['error'][] = __('Your PHP installation appears to be missing the MySQL extension which is required for bbPress');
				$this->step = -1;
			} else {
				$this->db_library = 'db-mysqli.php';
			}
		}
		
		if ($this->step === -1) {
			return false;
		}
		
		return true;
	}
	
	/**
	 * define_paths() - Define path constants
	 *
	 * Sets some bbPress constants if they are not otherwise available based
	 * on the classes initiating file path.
	 *
	 * @return boolean False if no path was supplied, otherwise always true
	 **/
	function define_paths()
	{
		if (!$this->caller) {
			return false;
		}
		
		if (!defined('BBPATH')) {
			// Determine the base path of the installation
			// The caller must be in bb-admin or the base path of the installation
			$bbpath = preg_replace('|(/bb-admin)?/[^/]+?$|', '/', $this->caller);
			
			if (!$bbpath) {
				echo 'Could not determine base path.';
				die();
			}
			
			define('BBPATH', $bbpath);
		}
		
		if (!defined('BBINC')) {
			// Define BBINC
			// Tell us to load includes because bb-settings.php was not loaded
			// bb-settings.php is generally not loaded on steps -1, 0 and 1 but
			// there are exceptions, so this is safer than just reading the step
			$this->load_includes = true;
			define('BBINC', 'bb-includes/');
		}
		
		return true;
	}
	
	/**
	 * database_tables_are_installed() - Tests whether database tables exist
	 *
	 * Checks for the existence of the forum table in the database that is
	 * currently configured.
	 *
	 * @return boolean False if the table isn't found, otherwise true
	 **/
	function database_tables_are_installed()
	{
		if ($this->load_includes) {
			require_once(BBPATH . BBINC . 'db-base.php');
			require_once(BBPATH . BBINC . $this->db_library);
		} else {
			global $bbdb;
		}
		
		$bbdb->hide_errors();
		$installed = (boolean) $bbdb->get_var("SELECT forum_id FROM $bbdb->forums LIMIT 1");
		$bbdb->show_errors();
		
		return $installed;
	}
	
	function bb_options_are_set()
	{
		if (!$this->load_includes) {
			if (bb_get_option('uri')) {
				return true;
			}
		}
		return false;
	}
	
	function is_installed()
	{
		if ($this->database_tables_are_installed()) {
			if ($this->bb_options_are_set()) {
				return true;
			}
		}
		return false;
	}
	
	function check_configs()
	{
		// Check for a config file
		if (file_exists(BBPATH . 'bb-config.php')) {
			$this->configs['bb-config.php'] = BBPATH . 'bb-config.php';
		} elseif (file_exists(dirname(BBPATH) . 'bb-config.php')) {
			$this->configs['bb-config.php'] = dirname(BBPATH) . 'bb-config.php';
		}
		
		// Check for an old config file
		if (file_exists(BBPATH . 'config.php')) {
			$this->configs['config.php'] = BBPATH . 'config.php';
		} elseif (file_exists(dirname(BBPATH) . 'config.php')) {
			$this->configs['config.php'] = dirname(BBPATH) . 'config.php';
		}
		
		if ($this->configs['config.php']) {
			// There is an old school config file
			// Step -1 is where we send fatal errors from any screen
			$this->strings[-1]['messages']['error'][] = __('An old <code>config.php</code> file has been detected in your installation. You can either rename it to <code>bb-config.php</code> or remove it and run the <a href="install.php">installer</a> again.');
			$this->step = -1;
			return $this->step;
		}
		
		// Check if bbPress is already installed
		// Is there a current config file
		if ($this->configs['bb-config.php']) {
			
			// Is it valid
			if ($this->validate_current_config()) {
				// Step 1 is complete
				$this->step_status[1] = 'complete';
				$this->strings[1]['status'] = __('&laquo; completed');
				
				// On step 1 we want to report that the file is good and allow the user to continue
				if ($this->step === 1) {
					// Stop form processing if it is posted
					$this->stop_process = true;
					
					// Display a nice message saying the config file exists
					$this->strings[1]['messages']['message'][] = __('A valid configuration file was found at <code>bb-config.php</code><br />You may continue to the next step.');
					return $this->step;
				}
			} else {
				// Invalid config files on any step cause us to exit to step 0
				$this->strings[-1]['messages']['error'][] = __('An invalid configuration file was found at <code>bb-config.php</code><br />The installation cannot continue.');
				$this->strings[-1]['messages'][0][] = __('Usually this is caused by one of the database connection settings being incorrect. Make sure that the specified user has appropriate permission to access the database.');
				$this->step = -1;
			}
			
			// If we have made it this far, then we can check if the database tables exist and have content
			if ($this->is_installed()) {
				// The database is installed
				// The following standard functions should be available
				if (bb_get_option('bb_db_version') > bb_get_option_from_db('bb_db_version')) {
					// The database needs upgrading
					$this->strings[-1]['messages'][0][] = __('bbPress is already installed, but appears to require an upgrade.');
					$this->strings[-1]['messages'][0][] = sprintf(__('Perhaps you meant to run the <a href="%s">upgrade script</a> instead?'), bb_get_option('uri') . 'bb-admin/upgrade.php');
					$this->step = -1;
				} else {
					// Redirect to the base url
					bb_safe_redirect(bb_get_option('uri'));
					die();
				}
			}
			
		} else {
			
			if ( $this->step !== 1 ) {
				// There is no config file, go back to the beginning
				$this->strings[0]['messages']['error'][] = __('There doesn\'t seem to be a <code>bb-config.php</code> file. This usually means that you want to install bbPress.');
				$this->step = 0;
			}
			
		}
		
		// Check if the config file path is writable
		if ( is_writable(BBPATH) ) {
			$this->configs['writable'] = true;
		}
		
		return $this->step;
	}
	
	function validate_current_config()
	{
		// If we are validating then the config file has already been included
		// So we can just check for valid constants
		
		// The required constants for a valid config file
		$required_constants = array(
			'BBDB_NAME',
			'BBDB_USER',
			'BBDB_PASSWORD',
			'BBDB_HOST'
		);
		
		// Check the required constants are defined
		foreach ($required_constants as $required_constant) {
			if (!defined($required_constant)) {
				return false;
			}
		}
		
		global $bb_table_prefix;
		
		if (!isset($bb_table_prefix)) {
			return false;
		}
		
		// Everthing is OK so far, validate the connection as well
		return $this->validate_current_database();
	}
	
	function validate_current_database()
	{
		if ($this->load_includes) {
			require_once(BBPATH . BBINC . 'db-base.php');
			require_once(BBPATH . BBINC . $this->db_library);
		} else {
			global $bbdb;
		}
		
		if (!is_resource($bbdb->dbh_local)) {
			return false;
		}
		
		return true;
	}
	
	function prepare_data()
	{
		$this->data = array(
			1 => array(
				'form' => array(
					'bbdb_name' => array(
						'value' => '',
						'label' => __('Database name:'),
						'note'  => __('The name of the database in which you want to run bbPress.')
					),
					'bbdb_user' => array(
						'value' => '',
						'label' => __('Database user:'),
						'note'  => __('The database user that has access to that database.')
					),
					'bbdb_password' => array(
						'type'  => 'password',
						'value' => '',
						'label' => __('Database password:'),
						'note'  => __('That database user\'s password.')
					),
					'toggle_1' => array(
						'value'   => 0,
						'label'   => __('Show advanced settings:'),
						'note'    => __('99% of the time these settings will not have to be changed.'),
						'checked' => '',
						'display' => 'none'
					),
					'bbdb_host'        => array(
						'value'        => 'localhost',
						'label'        => __('Database host:'),
						'note'         => __('The domain name or IP address of the server where the database is located. If the database is on the same server as the web site, then this probably should remain <strong>localhost</strong>.'),
						'prerequisite' => 'toggle_1'
					),
					'bbdb_charset' => array(
						'value'        => 'utf8',
						'label'        => __('Database character set:'),
						'note'         => __('The best choice is <strong>utf8</strong>, but you will need to match the character set which you created the database with.'),
						'prerequisite' => 'toggle_1'
					),
					'bbdb_collate' => array(
						'value'        => '',
						'label'        => __('Database character collation:'),
						'note'         => __('The character collation value set when the database was created.'),
						'prerequisite' => 'toggle_1'
					),
					'bb_secret_key' => array(
						'value'        => '',
						'label'        => __('Cookie secret key:'),
						'note'         => __('This should be a unique and secret phrase which will be used to protect authentication details. If you are integrating with WordPress this value must match the value of the constant named "SECRET_KEY" in your WordPress <code>wp-config.php</code> file.'),
						'prerequisite' => 'toggle_1'
					),
					'bb_table_prefix' => array(
						'value'        => 'bb_',
						'label'        => __('Table name prefix:'),
						'note'         => __('If you are running multiple bbPress installations in a single database, you will probably want to change this.'),
						'prerequisite' => 'toggle_1'
					),
					'config' => array(
						'value' => '',
						'label' => __('Contents for <code>bb-config.php</code>:'),
						'note'  => __('Once you have created the configuration file, you can check for it below.')
					),
					'forward_1_0' => array(
						'value' => __('Save database configuration file &raquo;')
					),
					'back_1_1' => array(
						'value' => __('&laquo; Go back')
					),
					'forward_1_1' => array(
						'value' => __('Check for configuration file &raquo;')
					),
					'forward_1_2' => array(
						'value' => __('Go to step 2 &raquo;')
					)
				)
			),
			
			2 => array(
				'form' => array(
					'toggle_2_0' => array(
						'value'        => 0,
						'label'        => __('Add integration settings:'),
						'note'         => __('If you want to integrate bbPress with an existing WordPress installation.'),
						'checked'      => '',
						'display'      => 'none',
						'toggle_value' => array(
							'target'    => 'forward_2_0',
							'off_value' => __('Skip WordPress integration &raquo;'),
							'on_value'  => __('Save WordPress integration settings &raquo;')
						)
					),
					'wp_siteurl' => array(
						'value' => '',
						'label' => __('WordPress address (URL):'),
						'note'  => __('This value should exactly match the <strong>WordPress address (URL)</strong> setting in your WordPress general options.'),
						'prerequisite' => 'toggle_2_0'
					),
					'wp_home' => array(
						'value' => '',
						'label' => __('Blog address (URL):'),
						'note'  => __('This value should exactly match the <strong>Blog address (URL)</strong> setting in your WordPress general options.'),
						'prerequisite' => 'toggle_2_0'
					),
					'secret' => array(
						'value' => '',
						'label' => __('Secret:'),
						'note'  => __('This value should exactly match the <strong>secret</strong> option set in your WordPress database. The only way to access this value is to retrieve it directly from the "options" database table.'),
						'prerequisite' => 'toggle_2_0'
					),
					'toggle_2_1' => array(
						'value'   => 0,
						'label'   => __('Add user database integration settings:'),
						'note'    => __('If you want to share user tables with an existing WordPress installation.'),
						'checked' => '',
						'display' => 'none',
						'prerequisite' => 'toggle_2_0'
					),
					'wp_table_prefix' => array(
						'value' => 'wp_',
						'label' => __('User database table prefix:'),
						'note'  => __('If your bbPress and WordPress installations share the same database, then this is the same value as <code>$wp_table_prefix</code> in your WordPress <code>wp-config.php</code> file. It is usually <strong>wp_</strong>.'),
						'prerequisite' => 'toggle_2_1'
					),
					'toggle_2_2' => array(
						'value'   => 0,
						'label'   => __('Show advanced database settings:'),
						'note'    => __('If your bbPress and WordPress installation do not share the same database, then you will need to add advanced settings.'),
						'checked' => '',
						'display' => 'none',
						'prerequisite' => 'toggle_2_1'
					),
					'user_bbdb_name' => array(
						'value' => '',
						'label' => __('User database name:'),
						'note'  => __('The name of the database in which your user tables reside.'),
						'prerequisite' => 'toggle_2_2'
					),
					'user_bbdb_user' => array(
						'value' => '',
						'label' => __('User database user:'),
						'note'  => __('The database user that has access to that database.'),
						'prerequisite' => 'toggle_2_2'
					),
					'user_bbdb_password' => array(
						'type'  => 'password',
						'value' => '',
						'label' => __('User database password:'),
						'note'  => __('That database user\'s password.'),
						'prerequisite' => 'toggle_2_2'
					),
					'user_bbdb_host' => array(
						'value' => '',
						'label' => __('User database host:'),
						'note'  => __('The domain name or IP address of the server where the database is located. If the database is on the same server as the web site, then this probably should be <strong>localhost</strong>.'),
						'prerequisite' => 'toggle_2_2'
					),
					'user_bbdb_charset' => array(
						'value' => '',
						'label' => __('User database character set:'),
						'note'  => __('The best choice is <strong>utf8</strong>, but you will need to match the character set which you created the database with.'),
						'prerequisite' => 'toggle_2_2'
					),
					'custom_user_table' => array(
						'value' => '',
						'label' => __('User database "user" table:'),
						'note'  => __('The complete table name, including any prefix.'),
						'prerequisite' => 'toggle_2_2'
					),
					'custom_user_meta_table' => array(
						'value' => '',
						'label' => __('User database "user meta" table:'),
						'note'  => __('The complete table name, including any prefix.'),
						'prerequisite' => 'toggle_2_2'
					),
					'forward_2_0' => array(
						'value' => __('Skip WordPress integration &raquo;')
					),
					'back_2_1' => array(
						'value' => __('&laquo; Go back')
					),
					'forward_2_1' => array(
						'value' => __('Go to step 3 &raquo;')
					)
				)
			),
			
			3 => array(
				'form' => array(
					'name' => array(
						'value' => '',
						'label' => __('Site name:'),
						'note'  => __('This is what you are going to call your bbPress installation.')
					),
					'uri' => array(
						'value' => $this->guess_uri(),
						'label' => __('Site address (URL):'),
						'note'  => __('We have attempted to guess this, it\'s usually correct, but change it here if you wish.')
					),
					'keymaster_user_login' => array(
						'value'     => '',
						'maxlength' => 60,
						'label'     => __('Username:'),
						'note'      => __('This is the user login for the initial bbPress administrator (known as a "key master").')
					),
					'keymaster_user_email' => array(
						'value'     => '',
						'maxlength' => 100,
						'label'     => __('Email address:'),
						'note'      => __('The login details will be emailed to this address.')
					),
					'keymaster_user_type' => array(
						'value' => 'bbPress'
					),
					'forum_name' => array(
						'value'     => '',
						'maxlength' => 150,
						'label'     => __('Forum name:'),
						'note'      => __('This can be changed after installation, so don\'t worry about it too much.')
					),
					'forward_3_0' => array(
						'value' => __('Save site settings &raquo;')
					),
					'back_3_1' => array(
						'value' => __('&laquo; Go back')
					),
					'forward_3_1' => array(
						'value' => __('Complete the installation &raquo;')
					)
				)
			),
			
			4 => array(
				'form' => array(
					'toggle_4' => array(
						'value' => 0,
						'label' => __('Show installation messages:')
					),
					'error_log' => array(
						'value' => '',
						'label' => __('Installation errors:')
					),
					'installation_log' => array(
						'value' => '',
						'label' => __('Installation log:')
					)
				)
			)
		);
	}
	
	function guess_uri()
	{
		global $bb;
		
		if ($bb->uri) {
			$uri = $bb->uri;
		} else {
			$schema = 'http://';
			if (isset($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) == 'on') {
				$schema = 'https://';
			}
			$uri = preg_replace('|/bb-admin/.*|i', '/', $schema . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']);
		}
		
		return rtrim($uri, '/') . '/';
	}
	
	function is_posted()
	{
		if (strtolower($_SERVER['REQUEST_METHOD']) == 'post') {
			return true;
		}
		return false;
	}
	
	function set_step()
	{
		if ($this->is_posted()) {
			switch ($this->step) {
				case 2:
					if ($_POST['forward_1_2']) {
						$this->stop_process = 1;
					}
					break;
				
				case 3:
					// If this is actually a request to go back to step 2
					if ($_POST['back_2_1']) {
						$this->step = 2;
						break;
					}
					
					// If we have come forward from step 2 then don't process form 3
					if ($_POST['forward_2_1']) {
						$this->stop_process = true;
					}
					
					// Determine what the status of the previous step was based on input
					if ($_POST['toggle_2_0']) {
						$this->strings[2]['status'] = __('&laquo; completed');
						$this->step_status[2] = 'complete';
					}
					break;
				
				case 4:
					// Determine what the status of the previous step was based on input
					if ($_POST['toggle_2_0']) {
						$this->strings[2]['status'] = __('&laquo; completed');
						$this->step_status[2] = 'complete';
					}
					
					// If this is actually a request to go back to step 3
					if ($_POST['back_3_1']) {
						$this->step = 3;
						break;
					}
					
					// We have to have come forward from step 3
					if ($_POST['forward_3_1']) {
						$this->strings[3]['status'] = __('&laquo; completed');
						$this->step_status[3] = 'complete';
					} else {
						$this->step = 2;
					}
					break;
			}
		}
	}
	
	function sanitize_form_data()
	{
		foreach ($this->data as $step => $data) {
			if (isset($data['form']) && is_array($data['form'])) {
				foreach ($data['form'] as $key => $value) {
					$this->data[$step]['form'][$key]['value'] = attribute_escape($value['value']);
				}
			}
		}
		return true;
	}
	
	function process_form()
	{
		if ($this->is_posted() && !$this->stop_process) {
			switch ($this->step) {
				case 1:
					$this->process_form_config_file();
					break;
				
				case 2:
					$this->process_form_wordpress_integration();
					break;
				
				case 3:
					$this->process_form_site_options();
					break;
				
				case 4:
					$this->process_form_finalise_installation();
					break;
			}
		}
	}
	
	function inject_form_values_into_data($step)
	{
		$data =& $this->data[$step]['form'];
		
		foreach ($data as $key => $value) {
			if (substr($key, 0, 8) !== 'forward_' && substr($key, 0, 5) !== 'back_') {
				if (isset($data[$key]['prerequisite'])) {
					if ($_POST[$data[$key]['prerequisite']]) {
						$data[$key]['value'] = trim($_POST[$key]);
					} else {
						$data[$key]['value'] = '';
					}
				} else {
					$data[$key]['value'] = trim($_POST[$key]);
				}
			}
		}
		
		return true;
	}
	
	function process_form_config_file()
	{
		$this->inject_form_values_into_data(1);
		
		$data =& $this->data[1]['form'];
		
		$data['bb_table_prefix']['value'] = preg_replace('/[^0-9a-zA-Z_]/', '', $data['bb_table_prefix']['value']);
		if (empty($data['bb_table_prefix']['value'])) {
			$data['bb_table_prefix']['value'] = 'bb_';
		}
		
		if ($data['toggle_1']['value']) {
			$data['toggle_1']['checked'] = 'checked="checked"';
			$data['toggle_1']['display'] = 'block';
			
			// Remove dangerous characters from the secret key
			$data['bb_secret_key']['value'] = str_replace(array("'", "\\"), '', $data['bb_secret_key']['value']);
		}
		
		// Stop here if we are going backwards
		if ($_POST['back_1_1']) {
			print_r($_POST);
			$this->step_status[1] = 'incomplete';
			return 'incomplete';
		}
		
		// Read the contents of the sample config
		if (file_exists(BBPATH . 'bb-config-sample.php')) {
			$sample_config = file(BBPATH . 'bb-config-sample.php');
		} else {
			$this->step_status[1] = 'error';
			$this->strings[1]['messages']['error'][] = __('I could not find the file <code>bb-config-sample.php</code><br />Please upload it to the root directory of your bbPress installation.');
			return 'error';
		}
		
		// Test the db connection.
		define('BBDB_NAME',     $data['bbdb_name']['value']);
		define('BBDB_USER',     $data['bbdb_user']['value']);
		define('BBDB_PASSWORD', $data['bbdb_password']['value']);
		define('BBDB_HOST',     $data['bbdb_host']['value']);
		define('BBDB_CHARSET',  $data['bbdb_charset']['value']);
		
		// We'll fail here if the values are no good.
		require_once(BBPATH . BBINC . 'db-base.php');
		require_once(BBPATH . BBINC . $this->db_library);
		
		if (!$bbdb->db_connect('SHOW TABLES;')) {
			$this->step_status[1] = 'incomplete';
			$this->strings[1]['messages']['error'][] = __('There was a problem connecting to the database you specified.<br />Please check the settings, then try again.');
			return 'error';
		}
		
		// Initialise an array to store th config lines
		$config_lines = array();
		
		// Loop through the sample config and write lines to the new config file
		foreach ($sample_config as $line_num => $line) {
			switch (substr($line,0,18)) {
				case "define('BBDB_NAME'":
					$config_lines[] = str_replace('bbpress', $data['bbdb_name']['value'], $line);
					break;
				case "define('BBDB_USER'":
					$config_lines[] = str_replace('username', $data['bbdb_user']['value'], $line);
					break;
				case "define('BBDB_PASSW":
					$config_lines[] = str_replace('password', $data['bbdb_password']['value'], $line);
					break;
				case "define('BBDB_HOST'":
					$config_lines[] = str_replace('localhost', $data['bbdb_host']['value'], $line);
					break;
				case "define('BBDB_CHARS":
					$config_lines[] = str_replace('utf8', $data['bbdb_charset']['value'], $line);
					break;
				case "define('BBDB_COLLA":
					$config_lines[] = str_replace("''", "'" . $data['bbdb_collate']['value'] . "'", $line);
					break;
				case "define('BB_SECRET_":
					$config_lines[] = str_replace("''", "'" . $data['bb_secret_key']['value'] . "'", $line);
					break;
				case '$bb_table_prefix =':
					$config_lines[] = str_replace('bb_', $data['bb_table_prefix']['value'], $line);
					break;
				default:
					$config_lines[] = $line;
			}
		}
		
		// If we can write the file
		if ($this->configs['writable']) {
			
			// Create the new config file and open it for writing
			$config_handle = fopen(BBPATH . 'bb-config.php', 'w');
			
			// Write lines one by one to avoid OS specific newline hassles
			foreach ($config_lines as $config_line) {
				fwrite($config_handle, $config_line);
			}
			
			// Close the new config file
			fclose($config_handle);
			
			// Make the file slightly more secure than world readable
			chmod(BBPATH . 'bb-config.php', 0666);
			
			if (file_exists(BBPATH . 'bb-config.php')) {
				$this->configs['bb-config.php'] = BBPATH . 'bb-config.php';
				$this->step_status[1] = 'complete';
				$this->strings[1]['messages']['message'][] = __('Your settings have been saved to the file <code>bb-config.php</code><br />You can now continue to the next step.');
			}
			
		}
			
		if (!$this->configs['bb-config.php']) {
			
			// Just write the contents to screen
			$this->data[1]['form']['config']['value'] = join(null, $config_lines);
			
			$this->step_status[1] = 'manual';
			$this->strings[1]['messages']['error'][] = __('Your settings could not be saved to a configuration file. You will need to save the text shown below into a file named <code>bb-config.php</code> in the root directory of your bbPress installation before you can continue.');
			
		}
	}
	
	function process_form_wordpress_integration()
	{
		// Check the referer
		bb_check_admin_referer('bbpress-installer');
		
		$this->inject_form_values_into_data(2);
		
		$data =& $this->data[2]['form'];
		
		// If there are no settings then goto step 3
		if (!$data['toggle_2_0']['value'] && !$_POST['back_2_1']) {
			$this->step_status[2] = 'complete';
			$this->strings[2]['messages']['message'][] = __('You have chosen to skip the WordPress integration step. You can always integrate WordPress later from within the admin area of bbPress.');
			return 'complete';
		}
		
		// If integration is selected
		if ($data['toggle_2_0']['value']) {
			$data['toggle_2_0']['checked'] = 'checked="checked"';
			$data['toggle_2_0']['display'] = 'block';
			$data['forward_2_0']['value'] = $data['toggle_2_0']['toggle_value']['on_value'];
			
			// Check the wp_siteurl URL for errors
			$data['wp_siteurl']['value'] = $data['wp_siteurl']['value'] ? rtrim($data['wp_siteurl']['value'], '/') . '/' : '';
			$this->strings[2]['form_errors']['wp_siteurl'][] = empty($data['wp_siteurl']['value']) ? 'empty' : false;
			if ($parsed = parse_url($data['wp_siteurl']['value'])) {
				$this->strings[2]['form_errors']['wp_siteurl'][] = preg_match('/https?/i', $parsed['scheme']) ? false : 'urlscheme';
				$this->strings[2]['form_errors']['wp_siteurl'][] = empty($parsed['host']) ? 'urlhost' : false;
			} else {
				$this->strings[2]['form_errors']['wp_siteurl'][] = 'urlparse';
			}
			
			// Check the wp_home URL for errors
			$data['wp_home']['value'] = $data['wp_home']['value'] ? rtrim($data['wp_home']['value'], '/') . '/' : '';
			$this->strings[2]['form_errors']['wp_home'][] = empty($data['wp_home']['value']) ? 'empty' : false;
			if ($parsed = parse_url($data['wp_home']['value'])) {
				$this->strings[2]['form_errors']['wp_home'][] = preg_match('/https?/i', $parsed['scheme']) ? false : 'urlscheme';
				$this->strings[2]['form_errors']['wp_home'][] = empty($parsed['host']) ? 'urlhost' : false;
			} else {
				$this->strings[2]['form_errors']['wp_home'][] = 'urlparse';
			}
			
			// Check the secret for errors
			$this->strings[2]['form_errors']['secret'][] = empty($data['secret']['value']) ? 'empty' : false;
			
			// If database integration is selected
			if ($data['toggle_2_1']['value']) {
				$data['toggle_2_1']['checked'] = 'checked="checked"';
				$data['toggle_2_1']['display'] = 'block';
				
				// Make the wp_table_prefix valid
				$data['wp_table_prefix']['value'] = preg_replace('/[^0-9a-zA-Z_]/', '', $data['wp_table_prefix']['value']);
				$data['wp_table_prefix']['value'] = empty($data['wp_table_prefix']['value']) ? 'wp_' : $data['wp_table_prefix']['value'];
				
				// If advanced database integration is selected
				if ($data['toggle_2_2']['value']) {
					$data['toggle_2_2']['checked'] = 'checked="checked"';
					$data['toggle_2_2']['display'] = 'block';
					
					// If any of these database values are set
					if (
						!empty($data['user_bbdb_name']['value']) ||
						!empty($data['user_bbdb_user']['value']) ||
						!empty($data['user_bbdb_password']['value']) ||
						!empty($data['user_bbdb_host']['value'])
					) {
						// Then error if any of these data base values are not set
						$this->strings[2]['form_errors']['user_bbdb_name'][]     = empty($data['user_bbdb_name']['value'])     ? 'empty' : false;
						$this->strings[2]['form_errors']['user_bbdb_user'][]     = empty($data['user_bbdb_user']['value'])     ? 'empty' : false;
						$this->strings[2]['form_errors']['user_bbdb_password'][] = empty($data['user_bbdb_password']['value']) ? 'empty' : false;
						$this->strings[2]['form_errors']['user_bbdb_host'][]     = empty($data['user_bbdb_host']['value'])     ? 'empty' : false;
					}
				}
			}
			
			// Remove empty values from the error array
			foreach ($this->strings[2]['form_errors'] as $input => $types) {
				$types = array_filter($types);
				if (!count($types)) {
					unset($this->strings[2]['form_errors'][$input]);
				}
			}
			
			// Check for errors and build error messages
			if ( count($this->strings[2]['form_errors']) ) {
				
				$this->step_status[2] = 'incomplete';
				$this->strings[2]['messages']['error'][] = __('Your integration settings have not been processed due to errors with the items marked below.');
				
				foreach ($this->strings[2]['form_errors'] as $input => $types) {
					$errors = array();
					
					foreach ($types as $type) {
						switch ($type) {
							case 'empty':
								// Only return this error when empty
								$errors = array(__('&bull; This value is required to continue.'));
								break(2);
							case 'urlparse':
								$errors[] = __('&bull; This does not appear to be a valid URL.');
								break;
							case 'urlscheme':
								$errors[] = __('&bull; The URL must begin with "http" or "https".');
								break;
							case 'urlhost':
								$errors[] = __('&bull; The URL does not contain a host name.');
								break;
						}
					}
					
					$this->strings[2]['form_errors'][$input] = $errors;
				}
				
				return 'incomplete';
			}
			
			// If database integration is selected
			if ($data['toggle_2_1']['value']) {
				
				// Test the db connection.
				
				// Setup variables and constants if available
				global $bb;
				$bb->wp_table_prefix = $data['wp_table_prefix']['value'];
				define('USER_BBDB_NAME',     $data['user_bbdb_name']['value']);
				define('USER_BBDB_USER',     $data['user_bbdb_user']['value']);
				define('USER_BBDB_PASSWORD', $data['user_bbdb_password']['value']);
				define('USER_BBDB_HOST',     $data['user_bbdb_host']['value']);
				// These may be empty at this particular stage
				if ( !empty($data['user_bbdb_charset']['value']) )      define('USER_BBDB_CHARSET',      $data['user_bbdb_charset']['value']);
				if ( !empty($data['custom_user_table']['value']) )      define('CUSTOM_USER_TABLE',      $data['custom_user_table']['value']);
				if ( !empty($data['custom_user_meta_table']['value']) ) define('CUSTOM_USER_META_TABLE', $data['custom_user_meta_table']['value']);
				
				// Bring in the database object
				global $bbdb;
				
				// Set the new prefix for user tables
				$bbdb->set_user_prefix();
				
				// Hide errors for the test
				$bbdb->return_errors();
				
				//$this->debug(__function__, '$bbdb', (array) $bbdb);
				
				// Select from the user table (may fail if there are no records in the table)
				if (!$result = $bbdb->query('DESCRIBE ' . $bbdb->users . ';')) {
					
					// We couldn't connect to the database at all
					
					// Turn errors back on
					$bbdb->show_errors();
					
					// Set the status
					$this->step_status[2] = 'incomplete';
					$this->strings[2]['messages']['error'][] = __('There was a problem connecting to the WordPress user database you specified. Please check the settings, then try again.');
					return 'incomplete';
					
				}
				
				if (is_array($result)) {
					
					// The result is an error, presumably telling us the table doesn't exist
					
					// Turn errors back on
					$bbdb->show_errors();
					
					// Set the status
					$this->step_status[2] = 'incomplete';
					
					if ($data['toggle_2_2']['value']) {
						$this->strings[2]['messages']['error'][] = __('Existing WordPress user tables could not be found in the WordPress database you specified.');
					} else {
						$this->strings[2]['messages']['error'][] = __('Existing WordPress user tables could not be found in the bbPress database you specified in step 1.<br /><br />This is probably because the database does not already contain working WordPress tables. You may need to specify advanced database settings or leave integration until after installation.');
					}
					$this->strings[2]['form_errors']['wp_table_prefix'][] = __('&bull; This does not appear to be a valid table prefix.');
					return 'incomplete';
					
				}
				
				// Turn errors back on
				$bbdb->show_errors();
			}
		}
		
		// Stop here if we are going backwards
		if ($_POST['back_2_1']) {
			$this->step_status[2] = 'incomplete';
			return 'incomplete';
		}
		
		// If we make it this may we are complete, so set the status to complete
		$this->step_status[2] = 'complete';
		$this->strings[2]['messages']['message'][] = __('Your WordPress integration cookie and database settings have been successfully validated. They will be saved after the next step.<br /><br />Once you have finished installing, you should visit the WordPress integration section of the bbPress admin area for further options and integration instructions, including user mapping and the correct cookie settings to add to your WordPress configuration file.');
		return 'complete';
	}
	
	function process_form_site_options()
	{
		// Check the referer
		bb_check_admin_referer('bbpress-installer');
		
		$this->inject_form_values_into_data(2);
		$this->inject_form_values_into_data(3);
		
		$data =& $this->data[3]['form'];
		
		$this->strings[3]['form_errors']['name'][] = empty($data['name']['value']) ? 'empty' : false;
		
		$data['uri']['value'] = $data['uri']['value'] ? rtrim($data['uri']['value'], '/') . '/' : '';
		$this->strings[3]['form_errors']['uri'][] = empty($data['uri']['value']) ? 'empty' : false;
		if ($parsed = parse_url($data['uri']['value'])) {
			$this->strings[3]['form_errors']['uri'][] = preg_match('/https?/i', $parsed['scheme']) ? false : 'urlscheme';
			$this->strings[3]['form_errors']['uri'][] = empty($parsed['host']) ? 'urlhost' : false;
		} else {
			$this->strings[3]['form_errors']['uri'][] = 'urlparse';
		}
		
		$this->strings[3]['form_errors']['keymaster_user_login'][] = empty($data['keymaster_user_login']['value']) ? 'empty' : false;
		if ($data['keymaster_user_login']['value'] != sanitize_user($data['keymaster_user_login']['value'])) {
			$this->strings[3]['form_errors']['keymaster_user_login'][] = 'userlogin';
		}
		$data['keymaster_user_login']['value'] == sanitize_user($data['keymaster_user_login']['value']);
		
		// bb_verify_email() needs this
		require_once(BBPATH . BBINC . 'registration-functions.php');
		
		// Check for a valid email
		$this->strings[3]['form_errors']['keymaster_user_email'][] = empty($data['keymaster_user_email']['value']) ? 'empty' : false;
		$this->strings[3]['form_errors']['keymaster_user_email'][] = !bb_verify_email($data['keymaster_user_email']['value']) ? 'email' : false;
		
		// Check for a forum name
		$this->strings[3]['form_errors']['forum_name'][] = empty($data['forum_name']['value']) ? 'empty' : false;
		
		
		// Remove empty values from the error array
		foreach ($this->strings[3]['form_errors'] as $input => $types) {
			$types = array_filter($types);
			if (!count($types)) {
				unset($this->strings[3]['form_errors'][$input]);
			}
		}
		
		// Check for errors and build error messages
		if ( count($this->strings[3]['form_errors']) ) {
			
			$this->step_status[3] = 'incomplete';
			$this->strings[3]['messages']['error'][] = __('Your site settings have not been processed due to errors with the items marked below.');
			
			foreach ($this->strings[3]['form_errors'] as $input => $types) {
				$errors = array();
				
				foreach ($types as $type) {
					switch ($type) {
						case 'empty':
							// Only return this error when empty
							$errors = array(__('&bull; This value is required to continue.'));
							break(2);
						case 'urlparse':
							$errors[] = __('&bull; This does not appear to be a valid URL.');
							break;
						case 'urlscheme':
							$errors[] = __('&bull; The URL must begin with "http" or "https".');
							break;
						case 'urlhost':
							$errors[] = __('&bull; The URL does not contain a host name.');
							break;
						case 'userlogin':
							$errors[] = __('&bull; Contains disallowed characters which have been removed.');
							break;
						case 'email':
							$errors[] = __('&bull; The user email address appears to be invalid.');
							break;
					}
				}
				
				$this->strings[3]['form_errors'][$input] = $errors;
			}
			
			return 'incomplete';
		}
		
		// Stop here if we are going backwards
		if ($_POST['back_3_1']) {
			$this->step_status[3] = 'incomplete';
			return 'incomplete';
		}
		
		// If we make it this far we are good to go
		$this->step_status[3] = 'complete';
		$this->strings[3]['messages']['message'][] = __('Your site settings have been saved and we are now ready to complete the installation. So what are you waiting for?');
		return 'complete';
	}
	
	function process_form_finalise_installation()
	{
		require_once(BBPATH . 'bb-admin/upgrade-functions.php');
		require_once(BBPATH . BBINC . 'registration-functions.php');
		require_once(BBPATH . 'bb-admin/admin-functions.php');
		
		$this->inject_form_values_into_data(2);
		$this->inject_form_values_into_data(3);
		
		$data2 =& $this->data[2]['form'];
		$data3 =& $this->data[3]['form'];
		$data4 =& $this->data[4]['form'];
		
		$error_log = array();
		$installation_log = array();
		
		// Check the referer
		bb_check_admin_referer('bbpress-installer');
		$installation_log[] = __('Referrer is OK, beginning installation...');
		
		global $bbdb;
		
		// Setup user table variables and constants if available
		if ($data2['toggle_2_1']['value']) {
			
			$installation_log[] = '>>> ' . __('Setting up custom user table constants');
			
			global $bb;
			
			if (!empty($data2['wp_table_prefix']['value']))        $bb->wp_table_prefix =           $data2['wp_table_prefix']['value'];
			if (!empty($data2['user_bbdb_name']['value']))         define('USER_BBDB_NAME',         $data2['user_bbdb_name']['value']);
			if (!empty($data2['user_bbdb_user']['value']))         define('USER_BBDB_USER',         $data2['user_bbdb_user']['value']);
			if (!empty($data2['user_bbdb_password']['value']))     define('USER_BBDB_PASSWORD',     $data2['user_bbdb_password']['value']);
			if (!empty($data2['user_bbdb_host']['value']))         define('USER_BBDB_HOST',         $data2['user_bbdb_host']['value']);
			if (!empty($data2['user_bbdb_charset']['value']))      define('USER_BBDB_CHARSET',      $data2['user_bbdb_charset']['value']);
			if (!empty($data2['custom_user_table']['value']))      define('CUSTOM_USER_TABLE',      $data2['custom_user_table']['value']);
			if (!empty($data2['custom_user_meta_table']['value'])) define('CUSTOM_USER_META_TABLE', $data2['custom_user_meta_table']['value']);
			
			// Set the new prefix for user tables
			$bbdb->set_user_prefix();
		}
		
		// Create the database
		$installation_log[] = "\n" . __('Step 1 - Creating database tables');
		// Return db errors
		$bbdb->return_errors();
		// Install the database
		$alterations = bb_install();
		// Show db errors
		$bbdb->show_errors();
		
		// If the database installed
		if ($alterations && count($alterations)) {
			// Loop through it to check for errors on each table
			foreach ($alterations as $alteration) {
				if (is_array($alteration)) {
					$installation_log[] = '>>> ' . $alteration['original']['message'];
					$installation_log[] = '>>>>>> ' . $alteration['error']['message'];
					$error_log[] = $alteration['error']['message'];
				} else {
					$installation_log[] = '>>> ' . $alteration;
				}
			}
		} else {
			$installation_log[] = '>>> ' . __('Database installation failed!!!');
			$installation_log[] = '>>>>>> ' . __('Halting installation!');
			$error_log[] = __('Database installation failed!!!');
			
			$this->step_status[4] = 'incomplete';
			$this->strings[4]['h2'] = __('Installation failed!');
			$this->strings[4]['messages']['error'][] = __('The database failed to install. You may need to replace bbPress with a fresh copy and start again.');
			
			$data4['installation_log']['value'] = join("\n", $installation_log);
			$data4['error_log']['value'] = join("\n", $error_log);
			
			return 'incomplete';
		}
		
		
		// Integration settings passed from step 2
		// These are already validated provided that the referer checks out
		$installation_log[] = "\n" . __('Step 2 - WordPress integration (optional)');
		if ($data2['toggle_2_0']['value']) {
			bb_update_option('wp_siteurl', $data2['wp_siteurl']['value']);
			$installation_log[] = '>>> ' . __('WordPress address (URL):') . ' ' . $data2['wp_siteurl']['value'];
			bb_update_option('wp_home', $data2['wp_home']['value']);
			$installation_log[] = '>>> ' . __('Blog address (URL):') . ' ' . $data2['wp_home']['value'];
			bb_update_option('secret', $data2['secret']['value']);
			$installation_log[] = '>>> ' . __('Secret:') . ' ' . $data2['secret']['value'];
			
			if ($data2['toggle_2_1']['value']) {
				if ( !empty($data2['wp_table_prefix']['value']) ) {
					bb_update_option('wp_table_prefix', $data2['wp_table_prefix']['value']);
					$installation_log[] = '>>> ' . __('User database table prefix:') . ' ' . $data2['wp_table_prefix']['value'];
				}
				
				if ($data2['toggle_2_2']['value']) {
					if ( !empty($data2['user_bbdb_name']['value']) ) {
						bb_update_option('user_bbdb_name', $data2['user_bbdb_name']['value']);
						$installation_log[] = '>>> ' . __('User database name:') . ' ' . $data2['user_bbdb_name']['value'];
					}
					if ( !empty($data2['user_bbdb_user']['value']) ) {
						bb_update_option('user_bbdb_user', $data2['user_bbdb_user']['value']);
						$installation_log[] = '>>> ' . __('User database user:') . ' ' . $data2['user_bbdb_user']['value'];
					}
					if ( !empty($data2['user_bbdb_password']['value']) ) {
						bb_update_option('user_bbdb_password', $data2['user_bbdb_password']['value']);
						$installation_log[] = '>>> ' . __('User database password:') . ' ' . $data2['user_bbdb_password']['value'];
					}
					if ( !empty($data2['user_bbdb_host']['value']) ) {
						bb_update_option('user_bbdb_host', $data2['user_bbdb_host']['value']);
						$installation_log[] = '>>> ' . __('User database host:') . ' ' . $data2['user_bbdb_host']['value'];
					}
					if ( !empty($data2['user_bbdb_charset']['value']) ) {
						bb_update_option('user_bbdb_charset', $data2['user_bbdb_charset']['value']);
						$installation_log[] = '>>> ' . __('User database character set:') . ' ' . $data2['user_bbdb_charset']['value'];
					}
					if ( !empty($data2['custom_user_table']['value']) ) {
						bb_update_option('custom_user_table', $data2['custom_user_table']['value']);
						$installation_log[] = '>>> ' . __('User database "user" table:') . ' ' . $data2['custom_user_table']['value'];
					}
					if ( !empty($data2['custom_user_meta_table']['value']) ) {
						bb_update_option('custom_user_meta_table', $data2['custom_user_meta_table']['value']);
						$installation_log[] = '>>> ' . __('User database "user meta" table:') . ' ' . $data2['custom_user_meta_table']['value'];
					}
				}
			}
		} else {
			$installation_log[] = '>>> ' . __('Integration not enabled');
		}
		
		// Site settings passed from step 3
		// These are already validated provided that the referer checks out
		$installation_log[] = "\n" . __('Step 3 - Site settings');
		bb_update_option('name', $data3['name']['value']);
		$installation_log[] = '>>> ' . __('Site name:') . ' ' . $data3['name']['value'];
		bb_update_option('uri', $data3['uri']['value']);
		$installation_log[] = '>>> ' . __('Site address (URL):') . ' ' . $data3['uri']['value'];
		bb_update_option('admin_email', $data3['keymaster_user_email']['value']);
		$installation_log[] = '>>> ' . __('Admin email address:') . ' ' . $data3['keymaster_user_email']['value'];
		
		// Create the key master
		$keymaster_created = false;
		
		switch ($data3['keymaster_user_type']['value']) {
			case 'bbPress':
				// The keymaster is brand new
				
				// Helper function to let us know the password that was created
				global $keymaster_password;
				function bb_get_keymaster_password($user_id, $pass) {
					global $keymaster_password;
					$keymaster_password = $pass;
				}
				add_action('bb_new_user', 'bb_get_keymaster_password', 10, 2);
				
				// Create the new user (automattically given key master role when BB_INSTALLING is true)
				if ($keymaster_user_id = bb_new_user($data3['keymaster_user_login']['value'], $data3['keymaster_user_email']['value'], '')) {
					$bb_current_user = bb_set_current_user( $keymaster_user_id );
					$data4['keymaster_user_password']['value'] = $keymaster_password;
					$installation_log[] = '>>> ' . __('Key master created');
					$installation_log[] = '>>>>>> ' . __('Username:') . ' ' . $data3['keymaster_user_login']['value'];
					$installation_log[] = '>>>>>> ' . __('Email address:') . ' ' . $data3['keymaster_user_email']['value'];
					$installation_log[] = '>>>>>> ' . __('Password:') . ' ' . $data4['keymaster_user_password']['value'];
					$keymaster_created = true;
				} else {
					$installation_log[] = '>>> ' . __('Key master could not be created!');
					$installation_log[] = '>>>>>> ' . __('Halting installation!');
					$error_log[] = __('Key master could not be created!');
					$this->step_status[4] = 'incomplete';
					$this->strings[4]['h2'] = __('Installation failed!');
					$this->strings[4]['messages']['error'][] = __('The key master could not be created. You may need to replace bbPress with a fresh copy and start again.');
					return 'incomplete';
				}
				break;
			
			case 'WordPress':
				if ($keymaster_user = bb_get_user_by_name($data3['keymaster_user_login']['value'])) {
					// The keymaster is an existing WordPress user
					$bb_current_user = bb_set_current_user($keymaster_user->ID);
					$bb_current_user->set_role('keymaster');
					$data4['keymaster_user_password']['value'] = __('Your WordPress password');
					$installation_log[] = '>>> ' . __('Key master role assigned to WordPress user');
					$installation_log[] = '>>>>>> ' . __('Username:') . ' ' . $data3['keymaster_user_login']['value'];
					$installation_log[] = '>>>>>> ' . __('Email address:') . ' ' . $data3['keymaster_user_email']['value'];
					$installation_log[] = '>>>>>> ' . __('Password:') . ' ' . $data4['keymaster_user_password']['value'];
					$keymaster_created = true;
				} else {
					$installation_log[] = '>>> ' . __('Key master role could not be assigned to WordPress user!');
					$installation_log[] = '>>>>>> ' . __('Halting installation!');
					$error_log[] = __('Key master could not be created!');
					$this->step_status[4] = 'incomplete';
					$this->strings[4]['h2'] = __('Installation failed!');
					$this->strings[4]['messages']['error'][] = __('The key master could not be assigned. You may need to replace bbPress with a fresh copy and start again.');
					return 'incomplete';
				}
				break;
		}
		
		if (bb_new_forum(array('forum_name' => $data3['forum_name']['value']))) {
			$installation_log[] = '>>> ' . __('Forum name:') . ' ' . $data3['forum_name']['value'];
			bb_new_topic(__('Your first topic'), 1, 'bbPress');
			$installation_log[] = '>>>>>> ' . __('Topic:') . ' ' . __('Your first topic');
			bb_new_post(1, __('First Post!  w00t.'));
			$installation_log[] = '>>>>>>>>> ' . __('Post:') . ' ' . __('First Post!  w00t.');
		} else {
			$installation_log[] = '>>> ' . __('Forum could not be created!');
			$error_log[] = __('Forum could not be created!');
		}
		
		if ($keymaster_created) {
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
			$keymaster_email_message = sprintf(
				__($keymaster_email_message),
				bb_get_option( 'uri' ),
				$data3['keymaster_user_login']['value'],
				$data4['keymaster_user_password']['value']
			);
			
			if (bb_mail($data3['keymaster_user_email']['value'], __('New bbPress installation'), $keymaster_email_message)) {
				$installation_log[] = '>>> ' . __('Key master email sent');
			} else {
				$installation_log[] = '>>> ' . __('Key master email not sent!');
				$error_log[] = __('Key master email not sent!');
			}
		}
		
		if (count($error_log)) {
			$this->strings[4]['h2'] = __('Installation completed with some errors!');
			$this->strings[4]['messages']['error'][] = __('Your installation completed with some minor errors. This is usually due to some database tables already existing, which is common for installations that are integrated with WordPress.');
			$installation_log[] = "\n" . __('There were some errors encountered during installation!');
		} else {
			$this->strings[4]['messages']['message'][] = __('Your installation completed successfully.<br />Check below for login details.');
			$installation_log[] = "\n" . __('Installation complete!');
		}
		
		$this->step_status[4] = 'complete';
		
		$data4['installation_log']['value'] = join("\n", $installation_log);
		$data4['error_log']['value'] = join("\n", $error_log);
		
		return 'complete';
	}
	
	function input_text($key)
	{
		$data = $this->data[$this->step]['form'][$key];
		
		if (isset($this->data[$this->step]['form'][$key]['type'])) {
			$type = $this->data[$this->step]['form'][$key]['type'];
		} else {
			$type = 'text';
		}
		
		if (isset($this->strings[$this->step]['form_errors'][$key])) {
			$class = ' class="error"';
		}
		
		$r = '<label for="' . $key . '"' . $class . '>' . "\n";
		
		if (isset($data['label'])) {
			$r .= $data['label'] . "\n";
		}
		
		if (isset($this->strings[$this->step]['form_errors'][$key])) {
			foreach ($this->strings[$this->step]['form_errors'][$key] as $error) {
				$r .= '<span class="error">' . $error . '</span>' . "\n";
			}
		}
		
		if (isset($data['maxlength']) && is_integer($data['maxlength'])) {
			$maxlength = ' maxlength="' . $data['maxlength'] . '"';
		}
		
		$r .= '<input type="' . $type . '" id="' . $key . '" name="' . $key . '" class="text" value="' . $data['value'] . '"' . $maxlength . ' />' . "\n";
		$r .= '</label>' . "\n";
		
		if (isset($data['note'])) {
			$r .= '<p class="note">' . $data['note'] . '</p>' . "\n";
		}
		
		echo $r;
	}
	
	function input_hidden($key)
	{
		$r = '<input type="hidden" name="' . $key . '" value="' . $this->data[$this->step]['form'][$key]['value'] . '" />' . "\n";
		
		echo $r;
	}
	
	function textarea($key)
	{
		$data = $this->data[$this->step]['form'][$key];
		
		$r = '<label for="' . $key . '">' . "\n";
		
		if (isset($data['label'])) {
			$r .= $data['label'] . "\n";
		}
		
		$r .= '<textarea id="' . $key . '">' . $data['value'] . '</textarea>' . "\n";
		$r .= '</label>' . "\n";
		
		if (isset($data['note'])) {
			$r .= '<p class="note">' . $data['note'] . '</p>' . "\n";
		}
		
		echo $r;
	}
	
	function select($key)
	{
		$data = $this->data[$this->step]['form'][$key];
		
		$r = '<label for="' . $key . '">' . "\n";
		
		if (isset($data['label'])) {
			$r .= $data['label'] . "\n";
		}
		
		if (isset($data['options'])) {
			$r .= '<select id="' . $key . '" name="' . $key . '"';
			
			if (isset($data['onchange'])) {
				$r .= ' onchange="' . $data['onchange'] . '"';
			}
			
			$r .= '>' . "\n";
			
			foreach ($data['options'] as $value => $display) {
				if ($data['value'] == $value) {
					$selected = ' selected="selected"';
				} else {
					$selected = '';
				}
				
				$r .= '<option value="' . $value . '"' . $selected . '>' . $display . '</option>' . "\n";
			}
			
			$r .= '</select>' . "\n";
		}
		
		$r .= '</label>' . "\n";
		
		if (isset($data['note'])) {
			$r .= '<p class="note">' . $data['note'] . '</p>' . "\n";
		}
		
		echo $r;
	}
	
	function input_toggle($key)
	{
		$data = $this->data[$this->step]['form'][$key];
		
		$onclick = 'toggleBlock(this, \'' . $key . '_target\');';
		if (isset($data['toggle_value'])) {
			$onclick .= ' toggleValue(this, \'' . $data['toggle_value']['target'] . '\', \'' . $data['toggle_value']['off_value'] . '\', \'' . $data['toggle_value']['on_value'] . '\');';
		}
		
		$checked = $data['checked'] ? ' ' . trim($data['checked']) : '';
		
		$r = '<label for="' . $key . '">' . "\n";
		
		if (isset($data['label'])) {
			$r .= $data['label'] . "\n";
		}
		
		$r .= '<input type="checkbox" id="' . $key . '" name="' . $key . '" class="checkbox" onclick="' . $onclick . '"' . $checked . ' value="1" />' . "\n";
		$r .= '</label>' . "\n";
		
		if (isset($data['note'])) {
			$r .= '<p class="note">' . $data['note'] . '</p>' . "\n";
		}
		
		echo $r;
	}
	
	function input_buttons($forward, $back = false)
	{
		$data_back = $back ? $this->data[$this->step]['form'][$back] : false;
		$data_forward = $this->data[$this->step]['form'][$forward];
		
		$r = '<fieldset class="buttons">' . "\n";
		
		if ($back) {
			$r .= '<label for="' . $back . '" class="back">' . "\n";
			$r .= '<input type="submit" id="' . $back . '" name="' . $back . '" class="button" value="' . $data_back['value'] . '" />' . "\n";
			$r .= '</label>' . "\n";
		}
		
		$r .= '<label for="' . $forward . '" class="forward">' . "\n";
		$r .= '<input type="submit" id="' . $forward . '" name="' . $forward . '" class="button" value="' . $data_forward['value'] . '" />' . "\n";
		$r .= '</label>' . "\n";
		
		$r .= '</fieldset>' . "\n";
		
		echo $r;
	}
	
	function hidden_step_inputs($step = false)
	{
		if (!$step) {
			$step = $this->step;
		} elseif ($step !== $this->step) {
			$this->inject_form_values_into_data($step);
		}
		
		$data = $this->data[$step]['form'];
		
		$r = '<fieldset>' . "\n";
		
		foreach ($data as $key => $value) {
			if (substr($key, 0, 8) !== 'forward_' && substr($key, 0, 5) !== 'back_') {
				$r .= '<input type="hidden" name="' . $key . '" value="' . $value['value'] . '" />' . "\n";
			}
		}
		
		$r .= '</fieldset>' . "\n";
		
		echo $r;
	}
	
	function populate_keymaster_user_login_from_user_tables()
	{
		$data =& $this->data[3]['form']['keymaster_user_login'];
		
		// Get the existing WordPress admin users
		
		// Setup variables and constants if available
		global $bb;
		if ( !empty($this->data[2]['form']['wp_table_prefix']['value']) )        $bb->wp_table_prefix = $this->data[2]['form']['wp_table_prefix']['value'];
		if ( !empty($this->data[2]['form']['user_bbdb_name']['value']) )         define('USER_BBDB_NAME',         $this->data[2]['form']['user_bbdb_name']['value']);
		if ( !empty($this->data[2]['form']['user_bbdb_user']['value']) )         define('USER_BBDB_USER',         $this->data[2]['form']['user_bbdb_user']['value']);
		if ( !empty($this->data[2]['form']['user_bbdb_password']['value']) )     define('USER_BBDB_PASSWORD',     $this->data[2]['form']['user_bbdb_password']['value']);
		if ( !empty($this->data[2]['form']['user_bbdb_host']['value']) )         define('USER_BBDB_HOST',         $this->data[2]['form']['user_bbdb_host']['value']);
		if ( !empty($this->data[2]['form']['user_bbdb_charset']['value']) )      define('USER_BBDB_CHARSET',      $this->data[2]['form']['user_bbdb_charset']['value']);
		if ( !empty($this->data[2]['form']['custom_user_table']['value']) )      define('CUSTOM_USER_TABLE',      $this->data[2]['form']['custom_user_table']['value']);
		if ( !empty($this->data[2]['form']['custom_user_meta_table']['value']) ) define('CUSTOM_USER_META_TABLE', $this->data[2]['form']['custom_user_meta_table']['value']);
		
		global $bbdb;
		
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
		$bbdb->hide_errors();
		
		if ( $wp_administrators = $bbdb->get_results( $wp_administrator_query, ARRAY_A ) ) {
			
			$bbdb->show_errors();
			
			if ( count($wp_administrators) ) {
				$email_maps = '';
				$data['options'] = array();
				$data['onchange'] = 'changeKeymasterEmail(this, \'keymaster_user_email\');';
				
				$data['options'][''] = '';
				foreach ($wp_administrators as $wp_administrator) {
					$email_maps .= 'emailMap[\'' . $wp_administrator->user_login . '\'] = \'' . $wp_administrator->user_email . '\';' . "\n\t\t\t\t\t\t\t\t";
					$data['options'][$wp_administrator->user_login] = $wp_administrator->display_name;
				}
				
				$this->strings[3]['scripts']['changeKeymasterEmail'] = <<<EOS
						<script type="text/javascript" charset="utf-8">
							function changeKeymasterEmail(selectObj, target) {
								var emailMap = new Array;
								emailMap[''] = '';
								$email_maps
								var targetObj = document.getElementById(target);
								var selectedAdmin = selectObj.options[selectObj.selectedIndex].value;
								targetObj.value = emailMap[selectedAdmin];
							}
						</script>
EOS;
				
				$this->data[3]['form']['keymaster_user_type']['value'] = 'WordPress';
				
				return true;
			}
		}
		
		$bbdb->show_errors();
		
		return false;
	}
	
	function header()
	{
		nocache_headers();
		
		bb_install_header($this->strings[$this->step]['title'], $this->strings[$this->step]['h1']);
	}
	
	function footer()
	{
		bb_install_footer();
	}
	
	function messages()
	{
		if (isset($this->strings[$this->step]['messages'])) {
			$messages = $this->strings[$this->step]['messages'];
			
			// This count works as long as $messages is only two-dimensional
			$count = (count($messages, COUNT_RECURSIVE) - count($messages));
			$i = 0;
			$r = '';
			foreach ($messages as $type => $paragraphs) {
				$class = $type ? $type : '';
				$title = ($type == 'error') ? __('Warning') : __('Message');
				
				foreach ($paragraphs as $paragraph) {
					$i++;
					$class = ($i === $count) ? ($class . ' last') : $class;
					
					$r .= '<p class="' . $class . '">' . "\n";
					if ($type) {
						$r .= '<span class="first" title="' . $title . '">!</span>' . "\n";
					}
					$r .= $paragraph . "\n";
					$r .= '</p>' . "\n";
				}
			}
			echo $r;
		}
	}
	
	function intro()
	{
		if (isset($this->strings[$this->step]['intro'])) {
			$messages = $this->strings[$this->step]['intro'];
			$count = count($messages);
			$i = 0;
			$r = '';
			foreach ($messages as $paragraph) {
				$i++;
				$class = ($i === $count) ? 'intro last' : 'intro';
				$r .= '<p class="' . $class . '">' . $paragraph . '</p>' . "\n";
			}
			echo $r;
		}
	}
	
	function step_header($step)
	{
		$class = ($step == $this->step) ? 'open' : 'closed';
		
		$r = '<div id="step' . $step . '" class="' . $class . '"><div>' . "\n";
		$r .= '<h2>' . $this->strings[$step]['h2'] . '</h2>' . "\n";
		
		if ($step < $this->step && $this->strings[$step]['status']) {
			$r .= '<p class="status">' . $this->strings[$step]['status'] . '</p>' . "\n";
		}
		
		echo $r;
		
		if ($step == $this->step) {
			$this->intro();
			$this->messages();
		}
	}
	
	function step_footer()
	{
		$r = '</div></div>' . "\n";
		
		echo $r;
	}
} // END class BB_Install
?>
