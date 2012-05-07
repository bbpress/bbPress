<?php

/**
 * bbPress Converter
 *
 * Based on the hard work of Adam Ellis at http://bbconverter.com
 *
 * @package bbPress
 * @subpackage Administration
 */

// Exit if accessed directly
if ( !defined( 'ABSPATH' ) ) exit;

/**
 * Main BBP_Converter Class
 */
class BBP_Converter {

	/**
	 * The main bbPress Converter loader
	 *
	 * @since bbPress (r3813)
	 * @uses BBP_Converter::includes() Include the required files
	 * @uses BBP_Converter::setup_actions() Setup the actions
	 */
	public function __construct() {
		$this->setup_actions();
	}

	/**
	 * Setup the default actions
	 *
	 * @since bbPress (r3813)
	 * @uses add_action() To add various actions
	 */
	private function setup_actions() {

		// Attach to the admin head with our ajax requests cycle and css
		add_action( 'bbp_admin_head',              array( $this, 'admin_head'              ) );

		// Attach the bbConverter admin settings action to the WordPress admin init action.
		add_action( 'bbp_register_admin_settings', array( $this, 'register_admin_settings' ) );

		// Attach to the login process to aid in converting passwords to wordpress.
		add_action( 'login_form_login',            array( $this, 'convert_pass'            ) );

		// Attach to the admin ajax request to process cycles
		add_action( 'wp_ajax_bbconverter_process', array( $this, 'process_callback'        ) );
	}

	/**
	 * Register the settings
	 *
	 * @since bbPress (r3813)
	 * @uses add_settings_section() To add our own settings section
	 * @uses add_settings_field() To add various settings fields
	 * @uses register_setting() To register various settings
	 */
	public function register_admin_settings() {

		// Add the main section
		add_settings_section( 'bbpress_converter_main',     __( 'Main Settings',     'bbpress' ),  'bbp_converter_setting_callback_main_section', 'bbpress_converter' );

		// System Select
		add_settings_field( '_bbp_converter_platform',      __( 'Select Platform',   'bbpress' ),  'bbp_converter_setting_callback_platform', 'bbpress_converter', 'bbpress_converter_main' );
		register_setting  ( 'bbpress_converter_main',       '_bbp_converter_platform',           'sanitize_title' );

		// Database Server
		add_settings_field( '_bbp_converter_db_server',     __( 'Database Server',   'bbpress' ),  'bbp_converter_setting_callback_dbserver', 'bbpress_converter', 'bbpress_converter_main' );
		register_setting  ( 'bbpress_converter_main',       '_bbp_converter_db_server',          'sanitize_title' );

		// Database Server Port
		add_settings_field( '_bbp_converter_db_port',       __( 'Database Port',     'bbpress' ),  'bbp_converter_setting_callback_dbport', 'bbpress_converter', 'bbpress_converter_main' );
		register_setting  ( 'bbpress_converter_main',       '_bbp_converter_db_port',            'sanitize_title' );

		// Database Name
		add_settings_field( '_bbp_converter_db_name',       __( 'Database Name',     'bbpress' ),  'bbp_converter_setting_callback_dbname', 'bbpress_converter', 'bbpress_converter_main' );
		register_setting  ( 'bbpress_converter_main',       '_bbp_converter_db_name',            'sanitize_title' );

		// Database User
		add_settings_field( '_bbp_converter_db_user',       __( 'Database User',     'bbpress' ),  'bbp_converter_setting_callback_dbuser', 'bbpress_converter', 'bbpress_converter_main' );
		register_setting  ( 'bbpress_converter_main',       '_bbp_converter_db_user',            'sanitize_title' );

		// Database Pass
		add_settings_field( '_bbp_converter_db_pass',       __( 'Database Password', 'bbpress' ),  'bbp_converter_setting_callback_dbpass', 'bbpress_converter', 'bbpress_converter_main' );
		register_setting  ( 'bbpress_converter_main',       '_bbp_converter_db_pass',            'sanitize_title' );

		// Database Prefix
		add_settings_field( '_bbp_converter_db_prefix',     __( 'Table Prefix',      'bbpress' ),  'bbp_converter_setting_callback_dbprefix', 'bbpress_converter', 'bbpress_converter_main' );
		register_setting  ( 'bbpress_converter_main',       '_bbp_converter_db_prefix',          'sanitize_title' );

		// Add the options section
		add_settings_section( 'bbpress_converter_opt',      __( 'Options',           'bbpress' ),  'bbp_converter_setting_callback_options_section', 'bbpress_converter' );

		// Rows Limit
		add_settings_field( '_bbp_converter_rows',          __( 'Rows Limit',        'bbpress' ),  'bbp_converter_setting_callback_rows', 'bbpress_converter', 'bbpress_converter_opt' );
		register_setting  ( 'bbpress_converter_opt',        '_bbp_converter_rows',               'intval' );

		// Delay Time
		add_settings_field( '_bbp_converter_delay_time',    __( 'Delay Time',        'bbpress' ), 'bbp_converter_setting_callback_delay_time', 'bbpress_converter', 'bbpress_converter_opt' );
		register_setting  ( 'bbpress_converter_opt',        '_bbp_converter_delay_time',        'intval' );

		// Convert Users ?
		add_settings_field( '_bbp_converter_convert_users', __( 'Convert Users',     'bbpress' ), 'bbp_converter_setting_callback_convert_users', 'bbpress_converter', 'bbpress_converter_opt' );
		register_setting  ( 'bbpress_converter_opt',        '_bbp_converter_convert_users',     'intval' );

		// Clean
		add_settings_field( '_bbp_converter_clean',         __( 'Clean',             'bbpress' ), 'bbp_converter_setting_callback_clean', 'bbpress_converter', 'bbpress_converter_opt' );
		register_setting  ( 'bbpress_converter_opt',        '_bbp_converter_clean',             'intval' );

		// Restart
		add_settings_field( '_bbp_converter_restart',       __( 'Restart',           'bbpress' ), 'bbp_converter_setting_callback_restart', 'bbpress_converter', 'bbpress_converter_opt' );
		register_setting  ( 'bbpress_converter_opt',        '_bbp_converter_restart',           'intval' );
	}

	/**
	 * Admin scripts
	 *
	 * @since bbPress (r3813)
	 */
	public function admin_head() { ?>

		<style type="text/css" media="screen">
			/*<![CDATA[*/

			div.bbp-converter-updated,
			div.bbp-converter-warning {
				border-radius: 3px 3px 3px 3px;
				border-style: solid;
				border-width: 1px;
				padding: 5px 5px 5px 5px;
			}

			div.bbp-converter-updated {
				height: 300px;
				overflow: auto;
				display: none;
				background-color: #FFFFE0;
				border-color: #E6DB55;
				font-family: monospace;
			}

			div.bbp-converter-updated p {
				margin: 0.5em 0;
				padding: 2px;
			}
			
			div.bbp-converter-updated p strong.loading {
				padding: 2px 20px 2px 0;
				background-image: url('<?php echo admin_url(); ?>images/wpspin_light.gif');
				background-repeat: no-repeat;
				background-position: center right;
			}

			#bbp-converter-stop {
				display:none;
			}

			#bbp-converter-progress {
				display:none;
			}

			/*]]>*/
		</style>

		<script language="javascript">

			var bbconverter_is_running = false;
			var bbconverter_run_timer;
			var bbconverter_delay_time = 0;

			function bbconverter_grab_data() {
				var values = {};
				jQuery.each(jQuery('#bbp-converter-settings').serializeArray(), function(i, field) {
					values[field.name] = field.value;
				});
				
				if( values['_bbp_converter_restart'] ) {
					jQuery('#_bbp_converter_restart').removeAttr("checked");
				}

				if( values['_bbp_converter_delay_time'] ) {
					bbconverter_delay_time = values['_bbp_converter_delay_time'] * 1000;
				}

				values['action'] = 'bbconverter_process';
				return values;
			}

			function bbconverter_start() {
				if( false == bbconverter_is_running ) {
					bbconverter_is_running = true;
					jQuery('#bbp-converter-start').hide();
					jQuery('#bbp-converter-stop').show();
					jQuery('#bbp-converter-progress').show();
					bbconverter_log( "Starting Conversion..." );
					jQuery.post(ajaxurl, bbconverter_grab_data(), function(response) {
						var response_length = response.length - 1;
						response = response.substring(0,response_length);
						bbconverter_success(response);
					});
				}
			}

			function bbconverter_run() {
				jQuery.post(ajaxurl, bbconverter_grab_data(), function(response) {
					var response_length = response.length - 1;
					response = response.substring(0,response_length);
					bbconverter_success(response);
				});
			}

			function bbconverter_stop() {
				bbconverter_is_running = false;
				jQuery('#bbp-converter-message strong').removeClass( 'loading' );
			}

			function bbconverter_success(response) {
				bbconverter_log(response);
				
				if ( response == 'Conversion Complete' || response.indexOf('error') > -1 ) {
					bbconverter_log('<b>Repair any missing information: <a href="<?php echo admin_url(); ?>tools.php?page=bbp-repair">Continue</a></b>');
					jQuery('#bbp-converter-start').show();
					jQuery('#bbp-converter-stop').hide();
					jQuery('#bbp-converter-progress').hide();
					bbconverter_stop();
					clearTimeout( bbconverter_run_timer );
				} else if( bbconverter_is_running ) { // keep going
					jQuery('#bbp-converter-progress').show();
					clearTimeout( bbconverter_run_timer );
					bbconverter_run_timer = setTimeout( 'bbconverter_run()', bbconverter_delay_time );
				} else {
					jQuery('#bbp-converter-start').show();
					jQuery('#bbp-converter-stop').hide();
					jQuery('#bbp-converter-progress').hide();
					clearTimeout( bbconverter_run_timer );
				}
			}

			function bbconverter_log(text) {
				if ( jQuery('#bbp-converter-message').css('display') == 'none' ) {
					jQuery('#bbp-converter-message').show();
				}
				if ( text ) {
					jQuery('#bbp-converter-message strong').removeClass( 'loading' );
					jQuery('#bbp-converter-message').prepend('<p><strong class="loading">' + text + '</strong></p>');
				}
			}

		</script>

		<?php
	}

	/**
	 * Callback processor
	 *
	 * @since bbPress (r3813)
	 */
	public function process_callback() {

		if ( ! ini_get( 'safe_mode' ) ) {
			set_time_limit( 0 );
			ini_set( 'memory_limit',   '256M' );
			ini_set( 'implicit_flush', '1'    );
			ignore_user_abort( true );
		}

		// Save step and count so that it can be restarted.
		if ( ! get_option( '_bbp_converter_step' ) || ( !empty( $_POST['_bbp_converter_restart'] ) ) ) {
			update_option( '_bbp_converter_step',  1 );
			update_option( '_bbp_converter_start', 0 );
		}

		$step  = (int) get_option( '_bbp_converter_step',  1 );
		$min   = (int) get_option( '_bbp_converter_start', 0 );
		$max   = $min + (int) get_option( '_bbp_converter_rows', !empty( $_POST['_bbp_converter_rows'] ) ? (int) $_POST['_bbp_converter_rows'] : 100 ) - 1;
		$start = $min;

		// Bail if platform did not get saved
		$platform = !empty( $_POST['_bbp_converter_platform' ] ) ? $_POST['_bbp_converter_platform' ] : get_option( '_bbp_converter_platform' );
		if ( empty( $platform ) )
			return;

		// Include the appropriate converter.
		$converter = bbp_new_converter( $platform );

		switch ( $step ) {

			// STEP 1. Clean all tables.
			case 1 :
				if ( !empty( $_POST['_bbp_converter_clean'] ) ) {
					if ( $converter->clean( $start ) ) {
						update_option( '_bbp_converter_step',  $step + 1 );
						update_option( '_bbp_converter_start', 0         );
						$this->sync_table();

						if ( empty( $start ) ) {
							_e( 'No data to clean', 'bbpress' );
						}
					} else {
						update_option( '_bbp_converter_start', $max + 1 );

						_e( 'Deleting previously converted data (' . $min . ' - ' . $max . ')', 'bbpress' );
					}
				} else {
					update_option( '_bbp_converter_step',  $step + 1 );
					update_option( '_bbp_converter_start', 0         );
				}
				
				break;

			// STEP 2. Convert users.
			case 2 :
				if ( !empty( $_POST['_bbp_converter_convert_users'] ) ) {
					if ( $converter->convert_users( $start ) ) {
						update_option( '_bbp_converter_step',  $step + 1 );
						update_option( '_bbp_converter_start', 0         );

						if ( empty( $start ) ) {
							_e( 'No users to convert', 'bbpress' );
						}
					} else {
						update_option( '_bbp_converter_start', $max + 1 );

						_e( 'Converting users (' . $min . ' - ' . $max . ')', 'bbpress' );
					}
				} else {
					update_option( '_bbp_converter_step',  $step + 1 );
					update_option( '_bbp_converter_start', 0         );
				}

				break;

			// STEP 3. Clean passwords.
			case 3 :
				if ( !empty( $_POST['_bbp_converter_convert_users'] ) ) {
					if ( $converter->clean_passwords( $start ) ) {
						update_option( '_bbp_converter_step',  $step + 1 );
						update_option( '_bbp_converter_start', 0         );

						if ( empty( $start ) ) {
							_e( 'No passwords to clear', 'bbpress' );
						}
					} else {
						update_option( '_bbp_converter_start', $max + 1 );

						_e( 'Delete users wordpress default passwords (' . $min . ' - ' . $max . ')', 'bbpress' );
					}
				} else {
					update_option( '_bbp_converter_step',  $step + 1 );
					update_option( '_bbp_converter_start', 0         );
				}

				break;

			// STEP 4. Convert forums.
			case 4 :
				if ( $converter->convert_forums( $start ) ) {
					update_option( '_bbp_converter_step',  $step + 1 );
					update_option( '_bbp_converter_start', 0         );

					if ( empty( $start ) ) {
						_e( 'No forums to convert', 'bbpress' );
					}
				} else {
					update_option( '_bbp_converter_start', $max + 1 );

					_e( 'Converting forums (' . $min . ' - ' . $max . ')', 'bbpress' );
				}

				break;

			// STEP 5. Convert forum parents.
			case 5 :
				
				if ( $converter->convert_forum_parents( $start ) ) {
					update_option( '_bbp_converter_step',  $step + 1 );
					update_option( '_bbp_converter_start', 0         );

					if ( empty( $start ) ) {
						_e( 'No forum parents to convert', 'bbpress' );
					}
				} else {
					update_option( '_bbp_converter_start', $max + 1 );

					_e( 'Converting forum parents (' . $min . ' - ' . $max . ')', 'bbpress' );
				}

				break;

			// STEP 6. Convert topics.
			case 6 :

				if ( $converter->convert_topics( $start ) ) {
					update_option( '_bbp_converter_step',  $step + 1 );
					update_option( '_bbp_converter_start', 0         );

					if ( !$start ) {
						_e( 'No topics to convert', 'bbpress' );
					}
				} else {
					update_option( '_bbp_converter_start', $max + 1 );

					_e( 'Converting topics (' . $min . ' - ' . $max . ')', 'bbpress' );
				}

				break;

			// STEP 7. Convert tags.
			case 7 :
				
				if ( $converter->convert_tags( $start ) ) {
					update_option( '_bbp_converter_step',  $step + 1 );
					update_option( '_bbp_converter_start', 0         );

					if ( empty( $start ) ) {
						_e( 'No tags to convert', 'bbpress' );
					}
				} else {
					update_option( '_bbp_converter_start', $max + 1 );

					_e( 'Converting tags (' . $min . ' - ' . $max . ')', 'bbpress' );
				}

				break;

			// STEP 8. Convert replies.
			case 8 :
				if ( $converter->convert_replies( $start ) ) {
					update_option( '_bbp_converter_step',  $step + 1 );
					update_option( '_bbp_converter_start', 0         );
					if ( empty( $start ) ) {
						_e( 'No replies to convert', 'bbpress' );
					}
				} else {
					update_option( '_bbp_converter_start', $max + 1 );

					_e( 'Converting replies (' . $min . ' - ' . $max . ')', 'bbpress' );
				}

				break;
			
			default :
				delete_option( '_bbp_converter_step' );
				delete_option( '_bbp_converter_start' );

				_e( 'Conversion Complete', 'bbpress' );

				break;
			
		}
	}

	/**
	 * Convert passwords from previous forum to wordpress.
	 * 
	 * @since bbPress (r3813)
	 * @global WPDB $wpdb
	 */
	public function convert_pass() {

		$username = !empty( $_POST['log'] ) ? $_POST['log'] : '';

		if ( !empty( $username ) ) {

			global $wpdb;

			$row = $wpdb->get_row( "SELECT * FROM {$wpdb->users} INNER JOIN {$wpdb->usermeta} ON user_id = ID WHERE meta_key = '_bbp_converter_class' AND user_login = '{$username}' LIMIT 1" );

			if ( !empty( $row ) ) {
				$converter = bbp_new_converter( $row->meta_value );
				$converter->callback_pass( $username, $_POST['pwd'] );
			}
		}
	}

	/**
	 * Create Tables for fast syncing
	 * 
	 * @since bbPress (r3813)
	 */
	public function sync_table( $drop = false ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'bbp_converter_translator';
		if ( $wpdb->get_var( "SHOW TABLES LIKE '{$table_name}'" ) == $table_name ) {
			$wpdb->query( "DROP TABLE {$table_name}" );
		}

		if ( empty( $drop ) ) {
			require_once( ABSPATH . '/wp-admin/includes/upgrade.php' );

			if ( !empty( $wpdb->charset ) ) {
				$charset_collate = "DEFAULT CHARACTER SET $wpdb->charset";
			}

			if ( !empty( $wpdb->collate ) ) {
				$charset_collate .= " COLLATE $wpdb->collate";
			}

			/** Translator ****************************************************/

			$sql = "CREATE TABLE {$table_name} (
						meta_id mediumint(8) unsigned not null auto_increment,
						value_type varchar(25) null,
						value_id bigint(20) unsigned not null default '0',
						meta_key varchar(25) null,
						meta_value varchar(25) null,
					PRIMARY KEY  (meta_id),
						KEY value_id (value_id),
						KEY meta_join (meta_key, meta_value) ) {$charset_collate};";

			dbDelta( $sql );
		}
	}
}

/**
 * Base class to be extended by specific individual importers
 *
 * @since bbPress (r3813)
 */
abstract class BBP_Converter_Base {

	/**
	 * @var array() This is the field mapping array to process.
	 */
	protected $field_map = array();

	/**
	 * @var object This is the connection to the wordpress datbase.
	 */
	protected $wpdb;

	/**
	 * @var object This is the connection to the other platforms database.
	 */
	protected $opdb;

	/**
	 * @var int This is the max rows to process at a time.
	 */
	public $max_rows;

	/**
	 * @var array() Map of topic to forum.  It is for optimization.
	 */
	private $map_topicid_to_forumid = array();

	/**
	 * @var array() Map of from old forum ids to new forum ids.  It is for optimization.
	 */
	private $map_forumid = array();

	/**
	 * @var array() Map of from old topic ids to new topic ids.  It is for optimization.
	 */
	private $map_topicid = array();

	/**
	 * @var array() Map of from old user ids to new user ids.  It is for optimization.
	 */
	private $map_userid = array();

	/**
	 * @var str This is the charset for your wp database.
	 */
	public $charset;

	/**
	 * @var boolean Sync table available.
	 */
	public $sync_table = false;

	/**
	 * @var str Sync table name.
	 */
	public $sync_table_name;

	/** Methods ***************************************************************/

	/**
	 * This is the constructor and it connects to the platform databases.
	 */
	public function __construct() {
		$this->setup_globals();
	}

	private function setup_globals() {
		global $wpdb;

		/** Get database connections ******************************************/

		$this->wpdb         = $wpdb;
		$this->max_rows     = $_POST['_bbp_converter_rows'];
		$this->opdb         = new wpdb( $_POST['_bbp_converter_db_user'], $_POST['_bbp_converter_db_pass'], $_POST['_bbp_converter_db_name'], $_POST['_bbp_converter_db_server'] );
		$this->opdb->prefix = $_POST['_bbp_converter_db_prefix'];

		/**
		 * Error Reporting
		 */
		$this->wpdb->show_errors();
		$this->opdb->show_errors();

		/**
		 * Syncing
		 */
		$this->sync_table_name = $this->wpdb->prefix . 'bbp_converter_translator';
		if ( $this->wpdb->get_var( "SHOW TABLES LIKE '" . $this->sync_table_name . "'" ) == $this->sync_table_name ) {
			$this->sync_table = true;
		} else {
			$this->sync_table = false;
		}

		/**
		 * Charset
		 */
		if ( empty( $this->wpdb->charset ) ) {
			$this->charset = "UTF8";
		} else {
			$this->charset = $this->wpdb->charset;
		}

		/**
		 * Default mapping.
		 */

		/** Forum Section *****************************************************/

		$this->field_map[] = array(
			'to_type'      => 'forum',
			'to_fieldname' => 'post_status',
			'default'      => 'publish'
		);
		$this->field_map[] = array(
			'to_type'      => 'forum',
			'to_fieldname' => 'comment_status',
			'default'      => 'closed'
		);
		$this->field_map[] = array(
			'to_type'      => 'forum',
			'to_fieldname' => 'ping_status',
			'default'      => 'closed'
		);
		$this->field_map[] = array(
			'to_type'      => 'forum',
			'to_fieldname' => 'post_type',
			'default'      => 'forum'
		);

		/** Topic Section *****************************************************/

		$this->field_map[] = array(
			'to_type'      => 'topic',
			'to_fieldname' => 'post_status',
			'default'      => 'publish'
		);
		$this->field_map[] = array(
			'to_type'      => 'topic',
			'to_fieldname' => 'comment_status',
			'default'      => 'closed'
		);
		$this->field_map[] = array(
			'to_type'      => 'topic',
			'to_fieldname' => 'ping_status',
			'default'      => 'closed'
		);
		$this->field_map[] = array(
			'to_type'      => 'topic',
			'to_fieldname' => 'post_type',
			'default'      => 'topic'
		);

		/** Post Section ******************************************************/

		$this->field_map[] = array(
			'to_type'      => 'reply',
			'to_fieldname' => 'post_status',
			'default'      => 'publish'
		);
		$this->field_map[] = array(
			'to_type'      => 'reply',
			'to_fieldname' => 'comment_status',
			'default'      => 'closed'
		);
		$this->field_map[] = array(
			'to_type'      => 'reply',
			'to_fieldname' => 'ping_status',
			'default'      => 'closed'
		);
		$this->field_map[] = array(
			'to_type'      => 'reply',
			'to_fieldname' => 'post_type',
			'default'      => 'reply'
		);

		/** User Section ******************************************************/

		$default_role = ( is_multisite() && get_option( '_bbp_allow_global_access' ) ) ? bbp_get_participant_role() : get_option( 'default_role' );
		$this->field_map[] = array(
			'to_type'      => 'user',
			'to_fieldname' => 'role',
			'default'      => $default_role
		);
	}

	/**
	 * Convert Forums
	 */
	public function convert_forums( $start = 1 ) {
		return $this->convert_table( 'forum', $start );
	}

	/**
	 * Convert Topics / Threads
	 */
	public function convert_topics( $start = 1 ) {
		return $this->convert_table( 'topic', $start );
	}

	/**
	 * Convert Posts
	 */
	public function convert_replies( $start = 1 ) {
		return $this->convert_table( 'reply', $start );
	}

	/**
	 * Convert Users
	 */
	public function convert_users( $start = 1 ) {
		return $this->convert_table( 'user', $start );
	}

	/**
	 * Convert Tags
	 */
	public function convert_tags( $start = 1 ) {
		return $this->convert_table( 'tags', $start );
	}

	/**
	 * Convert Table
	 *
	 * @param string to type
	 * @param int Start row
	 */
	public function convert_table( $to_type, $start ) {

		// Are we usig a sync table, or postmeta?
		if ( $this->wpdb->get_var( "SHOW TABLES LIKE '" . $this->sync_table_name . "'" ) == $this->sync_table_name ) {
			$this->sync_table = true;
		} else {
			$this->sync_table = false;
		}

		// Set some defaults
		$has_insert     = false;
		$from_tablename = '';
		$field_list     = $from_tables = $tablefield_array = array();

		// Toggle Table Name based on $to_type (destination)
		switch ( $to_type ) {
			case 'user' :
				$tablename = $this->wpdb->users;
				break;

			case 'tags' :
				$tablename = '';
				break;

			default :
				$tablename = $this->wpdb->posts;
		}

		// Get the fields from the destination table
		if ( !empty( $tablename ) ) {
			$tablefield_array = $this->get_fields( $tablename );
		}

		/** Step 1 ************************************************************/

		// Loop through the field maps, and look for to_type matches
		foreach ( $this->field_map as $item ) {

			// Yay a match, and we have a from table, too
			if ( ( $item['to_type'] == $to_type ) && !empty( $item['from_tablename'] ) ) {

				// $from_tablename was set from a previous loop iteration
				if ( ! empty( $from_tablename ) ) {

					// Doing some joining
					if ( !in_array( $item['from_tablename'], $from_tables ) && in_array( $item['join_tablename'], $from_tables ) ) {
						$from_tablename .= ' ' . $item['join_type'] . ' JOIN ' . $this->opdb->prefix . $item['from_tablename'] . ' AS ' . $item['from_tablename'] . ' ' . $item['join_expression'];
					}

				// $from_tablename needs to be set
				} else {
					$from_tablename = $item['from_tablename'] . ' AS ' . $item['from_tablename'];
				}

				// Specific FROM expression data used
				if ( !empty( $item['from_expression'] ) ) {

					// No 'WHERE' in expression
					if ( stripos( $from_tablename, "WHERE" ) === false ) {
						$from_tablename .= ' ' . $item['from_expression'];

					// 'WHERE' in expression, so replace with 'AND'
					} else {
						$from_tablename .= ' ' . str_replace( "WHERE", "AND", $item['from_expression'] );
					}
				}

				// Add tablename and fieldname to arrays, formatted for querying
				$from_tables[] = $item['from_tablename'];
				$field_list[]  = 'convert(' . $item['from_tablename'] . '.' . $item['from_fieldname'] . ' USING "' . $this->charset . '") AS ' . $item['from_fieldname'];
			}
		}

		/** Step 2 ************************************************************/

		// We have a $from_tablename, so we want to get some data to convert
		if ( !empty( $from_tablename ) ) {

			// Get some data from the old forums
			$forum_array = $this->opdb->get_results( 'SELECT ' . implode( ',', $field_list ) . ' FROM ' . $this->opdb->prefix . $from_tablename . ' LIMIT ' . $start . ', ' . $this->max_rows, ARRAY_A );

			// Query returned some results
			if ( !empty( $forum_array ) ) {

				// Loop through results
				foreach ( (array) $forum_array as $forum ) {

					// Reset some defaults
					$insert_post = $insert_postmeta = $insert_data = array();

					// Loop through field map, again...
					foreach ( $this->field_map as $row ) {

						// Types matchand to_fieldname is present. This means
						// we have some work to do here.
						if ( ( $row['to_type'] == $to_type ) && ! is_null( $row['to_fieldname'] ) ) {

							// This row has a destination that matches one of the
							// columns in this table.
							if ( in_array( $row['to_fieldname'], $tablefield_array ) ) {
								
								// Allows us to set default fields.
								if ( isset( $row['default'] ) ) {
									$insert_post[$row['to_fieldname']] = $row['default'];

								// Translates a field from the old forum.
								} elseif ( isset( $row['callback_method'] ) ) {
									if ( ( 'callback_userid' == $row['callback_method'] ) && empty( $_POST['_bbp_converter_convert_users'] ) ) {
										$insert_post[$row['to_fieldname']] = $forum[$row['from_fieldname']];
									} else {
										$insert_post[$row['to_fieldname']] = call_user_func_array( array( $this, $row['callback_method'] ), array( $forum[$row['from_fieldname']], $forum ) );
									}
									
								// Maps the field from the old forum.
								} else {
									$insert_post[$row['to_fieldname']] = $forum[$row['from_fieldname']];
								}

							// Destination field is not empty, so we might need
							// to do some extra work or set a default.
							} elseif ( !empty( $row['to_fieldname'] ) ) {
								
								// Allows us to set default fields.
								if ( isset( $row['default'] ) ) {
									$insert_postmeta[$row['to_fieldname']] = $row['default'];

								// Translates a field from the old forum.
								} elseif ( isset( $row['callback_method'] ) ) {
									if ( ( $row['callback_method'] == 'callback_userid' ) && ( 0 == $_POST['_bbp_converter_convert_users'] ) ) {
										$insert_postmeta[$row['to_fieldname']] = $forum[$row['from_fieldname']];
									} else {
										$insert_postmeta[$row['to_fieldname']] = call_user_func_array( array( $this, $row['callback_method'] ), array( $forum[$row['from_fieldname']], $forum ) );
									}

								// Maps the field from the old forum.
								} else {
									$insert_postmeta[$row['to_fieldname']] = $forum[$row['from_fieldname']];
								}
							}
						}
					}

					/** Step 3 ************************************************/

					// Something to insert into the destination field
					if ( count( $insert_post ) > 0 || ( $to_type == 'tags' && count( $insert_postmeta ) > 0 ) ) {

						switch ( $to_type ) {
							
							/** New user **************************************/

							case 'user':
								if ( username_exists( $insert_post['user_login'] ) ) {
									$insert_post['user_login'] = 'imported_' . $insert_post['user_login'];
								}

								if ( email_exists( $insert_post['user_email'] ) ) {
									$insert_post['user_email'] = 'imported_' . $insert_post['user_email'];
								}

								$post_id = wp_insert_user( $insert_post );

								if ( is_numeric( $post_id ) ) {

									foreach ( $insert_postmeta as $key => $value ) {

										add_user_meta( $post_id, $key, $value, true );

										if ( '_id' == substr( $key, -3 ) && ( true === $this->sync_table ) ) {
											$this->wpdb->insert( $this->sync_table_name, array( 'value_type' => 'user', 'value_id' => $post_id, 'meta_key' => $key, 'meta_value' => $value ) );
										}
									}
								}
								break;

							/** New Topic-Tag *********************************/

							case 'tags':
								$post_id = wp_set_object_terms( $insert_postmeta['objectid'], $insert_postmeta['name'], 'topic-tag', true );
								break;

							/** Forum, Topic, Reply ***************************/

							default:
								$post_id = wp_insert_post( $insert_post );

								if ( is_numeric( $post_id ) ) {

									foreach ( $insert_postmeta as $key => $value ) {

										add_post_meta( $post_id, $key, $value, true );

										// Topics need an extra bit of metadata
										// to be keyed to the new post_id
										if ( ( 'topic' == $to_type ) && ( '_bbp_topic_id' == $key ) ) {

											// Update the live topic ID
											update_post_meta( $post_id, $key, $post_id );

											// Save the old topic ID
											add_post_meta( $post_id, '_bbp_old_topic_id', $value );
											if ( '_id' == substr( $key, -3 ) && ( true === $this->sync_table ) ) {
												$this->wpdb->insert( $this->sync_table_name, array( 'value_type' => 'post', 'value_id' => $post_id, 'meta_key' => '_bbp_topic_id',     'meta_value' => $post_id ) );
												$this->wpdb->insert( $this->sync_table_name, array( 'value_type' => 'post', 'value_id' => $post_id, 'meta_key' => '_bbp_old_topic_id', 'meta_value' => $value   ) );
											}

										} elseif ( '_id' == substr( $key, -3 ) && ( true === $this->sync_table ) ) {
											$this->wpdb->insert( $this->sync_table_name, array( 'value_type' => 'post', 'value_id' => $post_id, 'meta_key' => $key, 'meta_value' => $value ) );
										}
									}
								}
								break;
						}
						$has_insert = true;
					}
				}
			}
		}

		return ! $has_insert;
	}

	public function convert_forum_parents( $start ) {
		$has_update = false;

		if ( !empty( $this->sync_table ) ) {
			$forum_array = $this->wpdb->get_results( 'SELECT value_id, meta_value FROM ' . $this->sync_table_name . ' WHERE meta_key = "_bbp_forum_parent_id" AND meta_value > 0 LIMIT ' . $start . ', ' . $this->max_rows );
		} else {
			$forum_array = $this->wpdb->get_results( 'SELECT post_id AS value_id, meta_value FROM ' . $this->wpdb->postmeta . ' WHERE meta_key = "_bbp_forum_parent_id" AND meta_value > 0 LIMIT ' . $start . ', ' . $this->max_rows );
		}
		foreach ( (array) $forum_array as $row ) {
			$parent_id = $this->callback_forumid( $row->meta_value );
			$this->wpdb->query( 'UPDATE ' . $this->wpdb->posts . ' SET post_parent = "' . $parent_id . '" WHERE ID = "' . $row->value_id . '" LIMIT 1' );
			$has_update = true;
		}

		return ! $has_update;
	}

	/**
	 * This method deletes data from the wp database.
	 */
	public function clean( $start ) {
		$start      = 0;
		$has_delete = false;

		/** Delete bbconverter topics/forums/posts ****************************/

		if ( true === $this->sync_table ) {
			$bbconverter = $this->wpdb->get_results( 'SELECT value_id FROM ' . $this->sync_table_name . ' INNER JOIN ' . $this->wpdb->posts . ' ON(value_id = ID) WHERE meta_key LIKE "_bbp_%" AND value_type = "post" GROUP BY value_id ORDER BY value_id DESC LIMIT ' . $this->max_rows, ARRAY_A );
		} else {
			$bbconverter = $this->wpdb->get_results( 'SELECT post_id AS value_id FROM ' . $this->wpdb->postmeta . ' WHERE meta_key LIKE "_bbp_%" GROUP BY post_id ORDER BY post_id DESC LIMIT ' . $this->max_rows, ARRAY_A );
		}

		if ( !empty( $bbconverter ) ) {
			foreach ( (array) $bbconverter as $value ) {
				wp_delete_post( $value['value_id'], true );
			}
			$has_delete = true;
		}

		/** Delete bbconverter users ******************************************/

		if ( true === $this->sync_table ) {
			$bbconverter = $this->wpdb->get_results( 'SELECT value_id FROM ' . $this->sync_table_name . ' INNER JOIN ' . $this->wpdb->users . ' ON(value_id = ID) WHERE meta_key = "_bbp_user_id" AND value_type = "user" LIMIT ' . $this->max_rows, ARRAY_A );
		} else {
			$bbconverter = $this->wpdb->get_results( 'SELECT user_id AS value_id FROM ' . $this->wpdb->usermeta . ' WHERE meta_key = "_bbp_user_id" LIMIT ' . $this->max_rows, ARRAY_A );
		}

		if ( !empty( $bbconverter ) ) {
			foreach ( $bbconverter as $value ) {
				wp_delete_user( $value['value_id'] );
			}
			$has_delete = true;
		}

		return ! $has_delete;
	}

	/**
	 * This method deletes passwords from the wp database.
	 *
	 * @param int Start row
	 */
	public function clean_passwords( $start ) {
		$has_delete = false;

		/** Delete bbconverter passwords **************************************/

		$bbconverter = $this->wpdb->get_results( 'SELECT user_id, meta_value FROM ' . $this->wpdb->usermeta . ' WHERE meta_key = "_bbp_password" LIMIT ' . $start . ', ' . $this->max_rows, ARRAY_A );
		if ( !empty( $bbconverter ) ) {

			foreach ( $bbconverter as $value ) {
				if ( is_serialized( $value['meta_value'] ) ) {
					$this->wpdb->query( 'UPDATE ' . $this->wpdb->users . ' ' . 'SET user_pass = "" ' . 'WHERE ID = "' . $value['user_id'] . '"' );
				} else {
					$this->wpdb->query( 'UPDATE ' . $this->wpdb->users . ' ' . 'SET user_pass = "' . $value['meta_value'] . '" ' . 'WHERE ID = "' . $value['user_id'] . '"' );
					$this->wpdb->query( 'DELETE FROM ' . $this->wpdb->usermeta . ' WHERE meta_key = "_bbp_password" AND user_id = "' . $value['user_id'] . '"' );
				}
			}
			$has_delete = true;
		}
		return ! $has_delete;
	}

	/**
	 * This method implements the authentication for the different forums.
	 *
	 * @param string Unencoded password.
	 */
	abstract protected function authenticate_pass( $password, $hash );

	/**
	 * Info
	 */
	abstract protected function info();

	/**
	 * This method grabs appropriate fields from the table specified
	 *
	 * @param string The table name to grab fields from
	 */
	private function get_fields( $tablename ) {
		$rval        = array();
		$field_array = $this->wpdb->get_results( 'DESCRIBE ' . $tablename, ARRAY_A );

		foreach ( $field_array as $field ) {
			$rval[] = $field['Field'];
		}

		if ( $tablename == $this->wpdb->users ) {
			$rval[] = 'role';
			$rval[] = 'yim';
			$rval[] = 'aim';
			$rval[] = 'jabber';
		}
		return $rval;
	}
	
	/** Callbacks *************************************************************/

	/**
	 * Run password through wp_hash_password()
	 *
	 * @param string $username
	 * @param string $password 
	 */
	public function callback_pass( $username, $password ) {
		$user = $this->wpdb->get_row( 'SELECT * FROM ' . $this->wpdb->users . ' WHERE user_login = "' . $username . '" AND user_pass = "" LIMIT 1' );
		if ( !empty( $user ) ) {
			$usermeta = $this->wpdb->get_row( 'SELECT * FROM ' . $this->wpdb->usermeta . ' WHERE meta_key = "_bbp_password" AND user_id = "' . $user->ID . '" LIMIT 1' );

			if ( !empty( $usermeta ) ) {
				if ( $this->authenticate_pass( $password, $usermeta->meta_value ) ) {
					$this->wpdb->query( 'UPDATE ' . $this->wpdb->users . ' ' . 'SET user_pass = "' . wp_hash_password( $password ) . '" ' . 'WHERE ID = "' . $user->ID . '"' );
					$this->wpdb->query( 'DELETE FROM ' . $this->wpdb->usermeta . ' WHERE meta_key = "_bbp_password" AND user_id = "' . $user->ID . '"' );
				}
			}
		}
	}

	/**
	 * A mini cache system to reduce database calls to forum ID's
	 *
	 * @param string $field
	 * @return string 
	 */
	private function callback_forumid( $field ) {
		if ( !isset( $this->map_forumid[$field] ) ) {
			if ( !empty( $this->sync_table ) ) {
				$row = $this->wpdb->get_row( 'SELECT value_id, meta_value FROM ' . $this->sync_table_name . ' WHERE meta_key = "_bbp_forum_id" AND meta_value = "' . $field . '" LIMIT 1' );
			} else {
				$row = $this->wpdb->get_row( 'SELECT post_id AS value_id FROM ' . $this->wpdb->postmeta . ' WHERE meta_key = "_bbp_forum_id" AND meta_value = "' . $field . '" LIMIT 1' );
			}

			if ( !is_null( $row ) ) {
				$this->map_forumid[$field] = $row->value_id;
			} else {
				$this->map_forumid[$field] = 0;
			}
		}
		return $this->map_forumid[$field];
	}

	/**
	 * A mini cache system to reduce database calls to topic ID's
	 *
	 * @param string $field
	 * @return string 
	 */
	private function callback_topicid( $field ) {
		if ( !isset( $this->map_topicid[$field] ) ) {
			if ( !empty( $this->sync_table ) ) {
				$row = $this->wpdb->get_row( 'SELECT value_id, meta_value FROM ' . $this->sync_table_name . ' WHERE meta_key = "_bbp_old_topic_id" AND meta_value = "' . $field . '" LIMIT 1' );
			} else {
				$row = $this->wpdb->get_row( 'SELECT post_id AS value_id FROM ' . $this->wpdb->postmeta . ' WHERE meta_key = "_bbp_old_topic_id" AND meta_value = "' . $field . '" LIMIT 1' );
			}

			if ( !is_null( $row ) ) {
				$this->map_topicid[$field] = $row->value_id;
			} else {
				$this->map_topicid[$field] = 0;
			}
		}
		return $this->map_topicid[$field];
	}

	/**
	 * A mini cache system to reduce database calls to user ID's
	 *
	 * @param string $field
	 * @return string 
	 */
	private function callback_userid( $field ) {
		if ( !isset( $this->map_userid[$field] ) ) {
			if ( !empty( $this->sync_table ) ) {
				$row = $this->wpdb->get_row( 'SELECT value_id, meta_value FROM ' . $this->sync_table_name . ' WHERE meta_key = "_bbp_user_id" AND meta_value = "' . $field . '" LIMIT 1' );
			} else {
				$row = $this->wpdb->get_row( 'SELECT user_id AS value_id FROM ' . $this->wpdb->usermeta . ' WHERE meta_key = "_bbp_user_id" AND meta_value = "' . $field . '" LIMIT 1' );
			}

			if ( !is_null( $row ) ) {
				$this->map_userid[$field] = $row->value_id;
			} else {
				if ( !empty( $_POST['_bbp_converter_convert_users'] ) && ( $_POST['_bbp_converter_convert_users'] == 1 ) ) {
					$this->map_userid[$field] = 0;
				} else {
					$this->map_userid[$field] = $field;
				}
			}
		}
		return $this->map_userid[$field];
	}

	/**
	 * A mini cache system to reduce database calls map topics ID's to forum ID's
	 *
	 * @param string $field
	 * @return string 
	 */
	private function callback_topicid_to_forumid( $field ) {
		$topicid = $this->callback_topicid( $field );
		if ( empty( $topicid ) ) {
			$this->map_topicid_to_forumid[$topicid] = 0;
		} elseif ( ! isset( $this->map_topicid_to_forumid[$topicid] ) ) {
			$row = $this->wpdb->get_row( 'SELECT post_parent FROM ' . $this->wpdb->posts . ' WHERE ID = "' . $topicid . '" LIMIT 1' );

			if ( !is_null( $row ) ) {
				$this->map_topicid_to_forumid[$topicid] = $row->post_parent;
			} else {
				$this->map_topicid_to_forumid[$topicid] = 0;
			}
		}

		return $this->map_topicid_to_forumid[$topicid];
	}

	protected function callback_slug( $field ) {
		return sanitize_title_with_dashes( $field );
	}

	protected function callback_negative( $field ) {
		if ( $field < 0 ) {
			return 0;
		} else {
			return $field;
		}
	}

	protected function callback_html( $field ) {
		require_once( bbpress()->admin->admin_dir . 'bbp-parser.php' );
		$bbcode = BBCode::getInstance();
		return html_entity_decode( $bbcode->Parse( $field ) );
	}

	protected function callback_null( $field ) {
		if ( is_null( $field ) ) {
			return '';
		} else {
			return $field;
		}
	}

	protected function callback_datetime( $field ) {
		if ( is_numeric( $field ) ) {
			return date( 'Y-m-d H:i:s', $field );
		} else {
			return date( 'Y-m-d H:i:s', strtotime( $field ) );
		}
	}
}

/**
 * This is a function that is purposely written to look like a "new" statement.
 * It is basically a dynamic loader that will load in the platform conversion
 * of your choice.
 *
 * @param string $platform Name of valid platform class.
 */
function bbp_new_converter( $platform ) {
	$found = false;

	if ( $curdir = opendir( bbpress()->admin->admin_dir . 'converters/' ) ) {
		while ( $file = readdir( $curdir ) ) {
			if ( stristr( $file, '.php' ) && stristr( $file, 'index' ) === FALSE ) {
				$file = preg_replace( '/.php/', '', $file );
				if ( $platform == $file ) {
					$found = true;
					continue;
				}
			}
		}
		closedir( $curdir );
	}

	if ( true === $found ) {
		require_once( bbpress()->admin->admin_dir . 'converters/' . $platform . '.php' );
		eval( '$obj = new ' . $platform . '();' );
		return $obj;
	} else {
		return null;
	}
}

?>
