<?php

/**
 * bbPress Converter
 *
 * Based on the hard work of Adam Ellis
 *
 * @package bbPress
 * @subpackage Administration
 */

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

/**
 * Main BBP_Converter Class
 */
class BBP_Converter {

	/**
	 * The main bbPress Converter loader
	 *
	 * @since 2.1.0 bbPress (r3813)
	 *
	 * @uses BBP_Converter::includes() Include the required files
	 * @uses BBP_Converter::setup_actions() Setup the actions
	 */
	public function __construct() {
		$this->setup_actions();
	}

	/**
	 * Setup the default actions
	 *
	 * @since 2.1.0 bbPress (r3813)
	 *
	 * @uses add_action() To add various actions
	 */
	public function setup_actions() {

		// Attach to the admin head with our ajax requests cycle and css
		add_action( 'admin_head-tools_page_bbp-converter', array( $this, 'admin_head' ) );

		// Attach the bbConverter admin settings action to the WordPress admin init action.
		add_action( 'load-tools_page_bbp-converter', array( $this, 'register_admin_settings' ) );

		// Attach to the admin ajax request to process cycles
		add_action( 'wp_ajax_bbp_converter_process', array( $this, 'process_callback' ) );
	}

	/**
	 * Register the settings
	 *
	 * @since 2.1.0 bbPress (r3813)
	 *
	 * @uses add_settings_section() To add our own settings section
	 * @uses add_settings_field() To add various settings fields
	 * @uses register_setting() To register various settings
	 */
	public function register_admin_settings() {

		// Add the main section
		add_settings_section( 'bbpress_converter_main',     esc_html__( 'Database Settings', 'bbpress' ),  'bbp_converter_setting_callback_main_section', 'bbpress_converter' );

		// System Select
		add_settings_field( '_bbp_converter_platform',      esc_html__( 'Select Platform',   'bbpress' ),  'bbp_converter_setting_callback_platform', 'bbpress_converter', 'bbpress_converter_main' );
		register_setting  ( 'bbpress_converter_main',       '_bbp_converter_platform',           'sanitize_title' );

		// Database Server
		add_settings_field( '_bbp_converter_db_server',     esc_html__( 'Database Server',   'bbpress' ),  'bbp_converter_setting_callback_dbserver', 'bbpress_converter', 'bbpress_converter_main' );
		register_setting  ( 'bbpress_converter_main',       '_bbp_converter_db_server',          'sanitize_title' );

		// Database Server Port
		add_settings_field( '_bbp_converter_db_port',       esc_html__( 'Database Port',     'bbpress' ),  'bbp_converter_setting_callback_dbport', 'bbpress_converter', 'bbpress_converter_main' );
		register_setting  ( 'bbpress_converter_main',       '_bbp_converter_db_port',            'sanitize_title' );

		// Database Name
		add_settings_field( '_bbp_converter_db_name',       esc_html__( 'Database Name',     'bbpress' ),  'bbp_converter_setting_callback_dbname', 'bbpress_converter', 'bbpress_converter_main' );
		register_setting  ( 'bbpress_converter_main',       '_bbp_converter_db_name',            'sanitize_title' );

		// Database User
		add_settings_field( '_bbp_converter_db_user',       esc_html__( 'Database User',     'bbpress' ),  'bbp_converter_setting_callback_dbuser', 'bbpress_converter', 'bbpress_converter_main' );
		register_setting  ( 'bbpress_converter_main',       '_bbp_converter_db_user',            'sanitize_title' );

		// Database Pass
		add_settings_field( '_bbp_converter_db_pass',       esc_html__( 'Database Password', 'bbpress' ),  'bbp_converter_setting_callback_dbpass', 'bbpress_converter', 'bbpress_converter_main' );
		register_setting  ( 'bbpress_converter_main',       '_bbp_converter_db_pass',            'sanitize_title' );

		// Database Prefix
		add_settings_field( '_bbp_converter_db_prefix',     esc_html__( 'Table Prefix',      'bbpress' ),  'bbp_converter_setting_callback_dbprefix', 'bbpress_converter', 'bbpress_converter_main' );
		register_setting  ( 'bbpress_converter_main',       '_bbp_converter_db_prefix',          'sanitize_title' );

		// Add the options section
		add_settings_section( 'bbpress_converter_opt',      esc_html__( 'Options',           'bbpress' ),  'bbp_converter_setting_callback_options_section', 'bbpress_converter' );

		// Rows Limit
		add_settings_field( '_bbp_converter_rows',          esc_html__( 'Rows Limit',        'bbpress' ),  'bbp_converter_setting_callback_rows', 'bbpress_converter', 'bbpress_converter_opt' );
		register_setting  ( 'bbpress_converter_opt',        '_bbp_converter_rows',               'intval' );

		// Delay Time
		add_settings_field( '_bbp_converter_delay_time',    esc_html__( 'Delay Time',        'bbpress' ), 'bbp_converter_setting_callback_delay_time', 'bbpress_converter', 'bbpress_converter_opt' );
		register_setting  ( 'bbpress_converter_opt',        '_bbp_converter_delay_time',        'intval' );

		// Convert Users ?
		add_settings_field( '_bbp_converter_convert_users', esc_html__( 'Convert Users',     'bbpress' ), 'bbp_converter_setting_callback_convert_users', 'bbpress_converter', 'bbpress_converter_opt' );
		register_setting  ( 'bbpress_converter_opt',        '_bbp_converter_convert_users',     'intval' );

		// Restart
		add_settings_field( '_bbp_converter_restart',       esc_html__( 'Start Over',        'bbpress' ), 'bbp_converter_setting_callback_restart', 'bbpress_converter', 'bbpress_converter_opt' );
		register_setting  ( 'bbpress_converter_opt',        '_bbp_converter_restart',           'intval' );

		// Clean
		add_settings_field( '_bbp_converter_clean',         esc_html__( 'Purge Previous Import', 'bbpress' ), 'bbp_converter_setting_callback_clean', 'bbpress_converter', 'bbpress_converter_opt' );
		register_setting  ( 'bbpress_converter_opt',        '_bbp_converter_clean',             'intval' );
	}

	/**
	 * Admin scripts
	 *
	 * @since 2.1.0 bbPress (r3813)
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
				font-weight: bold;
			}

			div.bbp-converter-updated p {
				margin: 0;
				padding: 2px;
				float: left;
				clear: left;
			}

			div.bbp-converter-updated p.loading {
				padding: 2px 20px 2px 2px;
				background-image: url('<?php echo admin_url(); ?>images/loading.gif');
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

				values['action']      = 'bbp_converter_process';
				values['_ajax_nonce'] = '<?php echo wp_create_nonce( 'bbp_converter_process' ); ?>';

				return values;
			}

			function bbconverter_start() {
				if( false === bbconverter_is_running ) {
					bbconverter_is_running = true;
					jQuery('#bbp-converter-start').hide();
					jQuery('#bbp-converter-stop').show();
					jQuery('#bbp-converter-progress').show();
					bbconverter_log( '<p class="loading"><?php esc_html_e( 'Starting Conversion', 'bbpress' ); ?></p>' );
					bbconverter_run();
				}
			}

			function bbconverter_run() {
				jQuery.post(ajaxurl, bbconverter_grab_data(), function(response) {
					if ( 'bbp_converter_db_connection_failed' === response ) {
						bbconverter_db_error();
						return;
					}

					var response_length = response.length - 1;
					response = response.substring(0,response_length);
					bbconverter_success(response);
				});
			}

			function bbconverter_db_error() {
				jQuery('#bbp-converter-start').show();
				jQuery('#bbp-converter-stop').hide();
				jQuery('#bbp-converter-progress').hide();
				jQuery('#bbp-converter-message p').removeClass( 'loading' );
				bbconverter_log( '<p><?php esc_html_e( 'Database Connection Failed', 'bbpress' ); ?></p>' );
				bbconverter_is_running = false;
				clearTimeout( bbconverter_run_timer );
			}

			function bbconverter_stop() {
				jQuery('#bbp-converter-start').show();
				jQuery('#bbp-converter-stop').hide();
				jQuery('#bbp-converter-progress').hide();
				jQuery('#bbp-converter-message p').removeClass( 'loading' );
				bbconverter_log( '<p><?php esc_html_e( 'Conversion Stopped', 'bbpress' ); ?></p>' );
				bbconverter_is_running = false;
				clearTimeout( bbconverter_run_timer );
			}

			function bbconverter_success(response) {
				bbconverter_log(response);

				if ( response === '<p class="loading"><?php esc_html_e( 'Conversion Complete', 'bbpress' ); ?></p>' || response.indexOf('error') > -1 ) {
					bbconverter_log('<p>Repair any missing information: <a href="<?php echo admin_url(); ?>tools.php?page=bbp-repair">Continue</a></p>');
					bbconverter_stop();
				} else if( bbconverter_is_running ) { // keep going
					jQuery('#bbp-converter-progress').show();
					clearTimeout( bbconverter_run_timer );
					bbconverter_run_timer = setTimeout( 'bbconverter_run()', bbconverter_delay_time );
				} else {
					bbconverter_stop();
				}
			}

			function bbconverter_log(text) {
				if ( jQuery('#bbp-converter-message').css('display') === 'none' ) {
					jQuery('#bbp-converter-message').show();
				}
				if ( text ) {
					jQuery('#bbp-converter-message p').removeClass( 'loading' );
					jQuery('#bbp-converter-message').prepend( text );
				}
			}

		</script>

		<?php
	}

	/**
	 * Wrap the converter output in paragraph tags, so styling can be applied
	 *
	 * @since 2.1.0 bbPress (r4052)
	 *
	 * @param string $output
	 */
	private static function converter_output( $output = '' ) {

		// Get the last query
		$before = '<p class="loading">';
		$after  = '</p>';
		$query  = get_option( '_bbp_converter_query' );

		if ( ! empty( $query ) ) {
			$output = $output . '<span class="query">' . esc_attr( $query ) . '</span>';
		}

		echo $before . $output . $after;
	}

	/**
	 * Callback processor
	 *
	 * @since 2.1.0 bbPress (r3813)
	 */
	public function process_callback() {

		// Bail if user cannot view import page
		if ( ! current_user_can( 'bbp_tools_import_page' ) ) {
			wp_die( '0' );
		}

		// Verify intent
		check_ajax_referer( 'bbp_converter_process' );

		if ( ! ini_get( 'safe_mode' ) ) {
			set_time_limit( 0 );
			ini_set( 'memory_limit',   '256M' );
			ini_set( 'implicit_flush', '1'    );
			ignore_user_abort( true );
		}

		// Save step and count so that it can be restarted.
		if ( ! get_option( '_bbp_converter_step' ) || ( ! empty( $_POST['_bbp_converter_restart'] ) ) ) {
			update_option( '_bbp_converter_step',  1 );
			update_option( '_bbp_converter_start', 0 );
		}

		$step  = (int) get_option( '_bbp_converter_step',  1 );
		$min   = (int) get_option( '_bbp_converter_start', 0 );
		$count = ! empty( $_POST['_bbp_converter_rows'] )
			? (int) $_POST['_bbp_converter_rows']
			: 100;

		$max   = ( $min + $count ) - 1;
		$start = $min;

		// Bail if platform did not get saved
		$platform = ! empty( $_POST['_bbp_converter_platform' ] )
			? sanitize_text_field( $_POST['_bbp_converter_platform' ] )
			: get_option( '_bbp_converter_platform' );

		// Bail if no platform
		if ( empty( $platform ) ) {
			return;
		}

		// Include the appropriate converter.
		$converter = bbp_new_converter( $platform );

		switch ( $step ) {

			// STEP 1. Clean all tables.
			case 1 :
				if ( ! empty( $_POST['_bbp_converter_clean'] ) ) {
					if ( $converter->clean( $start ) ) {
						update_option( '_bbp_converter_step',  $step + 1 );
						update_option( '_bbp_converter_start', 0         );
						$this->sync_table( true );
						if ( empty( $start ) ) {
							$this->converter_output( esc_html__( 'No data to clean', 'bbpress' ) );
						}
					} else {
						update_option( '_bbp_converter_start', $max + 1 );
						$this->converter_output( sprintf( esc_html__( 'Deleting previously converted data (%1$s - %2$s)', 'bbpress' ), $min, $max ) );
					}
				} else {
					update_option( '_bbp_converter_step',  $step + 1 );
					update_option( '_bbp_converter_start', 0         );
				}

				break;

			// STEP 2. Convert users.
			case 2 :
				if ( ! empty( $_POST['_bbp_converter_convert_users'] ) ) {
					if ( $converter->convert_users( $start ) ) {
						update_option( '_bbp_converter_step',  $step + 1 );
						update_option( '_bbp_converter_start', 0         );
						if ( empty( $start ) ) {
							$this->converter_output( esc_html__( 'No users to convert', 'bbpress' ) );
						}
					} else {
						update_option( '_bbp_converter_start', $max + 1 );
						$this->converter_output( sprintf(  esc_html__( 'Converting users (%1$s - %2$s)', 'bbpress' ), $min, $max ) );
					}
				} else {
					update_option( '_bbp_converter_step',  $step + 1 );
					update_option( '_bbp_converter_start', 0         );
				}

				break;

			// STEP 3. Clean passwords.
			case 3 :
				if ( ! empty( $_POST['_bbp_converter_convert_users'] ) ) {
					if ( $converter->clean_passwords( $start ) ) {
						update_option( '_bbp_converter_step',  $step + 1 );
						update_option( '_bbp_converter_start', 0         );
						if ( empty( $start ) ) {
							$this->converter_output( esc_html__( 'No passwords to clear', 'bbpress' ) );
						}
					} else {
						update_option( '_bbp_converter_start', $max + 1 );
						$this->converter_output( sprintf( esc_html__( 'Delete users WordPress default passwords (%1$s - %2$s)', 'bbpress' ), $min, $max ) );
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
						$this->converter_output( esc_html__( 'No forums to convert', 'bbpress' ) );
					}
				} else {
					update_option( '_bbp_converter_start', $max + 1 );
					$this->converter_output( sprintf( esc_html__( 'Converting forums (%1$s - %2$s)', 'bbpress' ), $min, $max ) );
				}

				break;

			// STEP 5. Convert forum parents.
			case 5 :
				if ( $converter->convert_forum_parents( $start ) ) {
					update_option( '_bbp_converter_step',  $step + 1 );
					update_option( '_bbp_converter_start', 0         );
					if ( empty( $start ) ) {
						$this->converter_output( esc_html__( 'No forum parents to convert', 'bbpress' ) );
					}
				} else {
					update_option( '_bbp_converter_start', $max + 1 );
					$this->converter_output( sprintf( esc_html__( 'Calculating forum hierarchy (%1$s - %2$s)', 'bbpress' ), $min, $max ) );
				}

				break;

			// STEP 6. Convert forum subscriptions.
			case 6 :
				if ( $converter->convert_forum_subscriptions( $start ) ) {
					update_option( '_bbp_converter_step',  $step + 1 );
					update_option( '_bbp_converter_start', 0         );
					if ( empty( $start ) ) {
						$this->converter_output( esc_html__( 'No forum subscriptions to convert', 'bbpress' ) );
					}
				} else {
					update_option( '_bbp_converter_start', $max + 1 );
					$this->converter_output( sprintf( esc_html__( 'Converting forum subscriptions (%1$s - %2$s)', 'bbpress' ), $min, $max ) );
				}

				break;

			// STEP 7. Convert topics.
			case 7 :
				if ( $converter->convert_topics( $start ) ) {
					update_option( '_bbp_converter_step',  $step + 1 );
					update_option( '_bbp_converter_start', 0         );
					if ( empty( $start ) ) {
						$this->converter_output( esc_html__( 'No topics to convert', 'bbpress' ) );
					}
				} else {
					update_option( '_bbp_converter_start', $max + 1 );
					$this->converter_output( sprintf( esc_html__( 'Converting topics (%1$s - %2$s)', 'bbpress' ), $min, $max ) );
				}

				break;

			// STEP 8. Convert anonymous topic authors.
			case 8 :
				if ( $converter->convert_anonymous_topic_authors( $start ) ) {
					update_option( '_bbp_converter_step',  $step + 1 );
					update_option( '_bbp_converter_start', 0         );
					if ( empty( $start ) ) {
						$this->converter_output( esc_html__( 'No anonymous topic authors to convert', 'bbpress' ) );
					}
				} else {
					update_option( '_bbp_converter_start', $max + 1 );
					$this->converter_output( sprintf( esc_html__( 'Converting anonymous topic authors (%1$s - %2$s)', 'bbpress' ), $min, $max ) );
				}

				break;

			// STEP 9. Stick topics.
			case 9 :
				if ( $converter->convert_topic_stickies( $start ) ) {
					update_option( '_bbp_converter_step',  $step + 1 );
					update_option( '_bbp_converter_start', 0         );
					if ( empty( $start ) ) {
						$this->converter_output( esc_html__( 'No stickies to stick', 'bbpress' ) );
					}
				} else {
					update_option( '_bbp_converter_start', $max + 1 );
					$this->converter_output( sprintf( esc_html__( 'Calculating topic stickies (%1$s - %2$s)', 'bbpress' ), $min, $max ) );
				}

				break;

			// STEP 10. Stick to front topics (Super Sicky).
			case 10 :
				if ( $converter->convert_topic_super_stickies( $start ) ) {
					update_option( '_bbp_converter_step',  $step + 1 );
					update_option( '_bbp_converter_start', 0         );
					if ( empty( $start ) ) {
						$this->converter_output( esc_html__( 'No super stickies to stick', 'bbpress' ) );
					}
				} else {
					update_option( '_bbp_converter_start', $max + 1 );
					$this->converter_output( sprintf( esc_html__( 'Calculating topic super stickies (%1$s - %2$s)', 'bbpress' ), $min, $max ) );
				}

				break;

			// STEP 11. Closed topics.
			case 11 :
				if ( $converter->convert_topic_closed_topics( $start ) ) {
					update_option( '_bbp_converter_step',  $step + 1 );
					update_option( '_bbp_converter_start', 0         );
					if ( empty( $start ) ) {
						$this->converter_output( esc_html__( 'No closed topics to close', 'bbpress' ) );
					}
				} else {
					update_option( '_bbp_converter_start', $max + 1 );
					$this->converter_output( sprintf( esc_html__( 'Calculating closed topics (%1$s - %2$s)', 'bbpress' ), $min, $max ) );
				}

				break;

			// STEP 12. Convert topic tags.
			case 12 :
				if ( $converter->convert_tags( $start ) ) {
					update_option( '_bbp_converter_step',  $step + 1 );
					update_option( '_bbp_converter_start', 0         );
					if ( empty( $start ) ) {
						$this->converter_output( esc_html__( 'No topic tags to convert', 'bbpress' ) );
					}
				} else {
					update_option( '_bbp_converter_start', $max + 1 );
					$this->converter_output( sprintf( esc_html__( 'Converting topic tags (%1$s - %2$s)', 'bbpress' ), $min, $max ) );
				}

				break;

			// STEP 13. Convert topic subscriptions.
			case 13 :
				if ( $converter->convert_topic_subscriptions( $start ) ) {
					update_option( '_bbp_converter_step',  $step + 1 );
					update_option( '_bbp_converter_start', 0         );
					if ( empty( $start ) ) {
						$this->converter_output( esc_html__( 'No topic subscriptions to convert', 'bbpress' ) );
					}
				} else {
					update_option( '_bbp_converter_start', $max + 1 );
					$this->converter_output( sprintf( esc_html__( 'Converting topic subscriptions (%1$s - %2$s)', 'bbpress' ), $min, $max ) );
				}

				break;

			// STEP 14. Convert topic favorites.
			case 14 :
				if ( $converter->convert_favorites( $start ) ) {
					update_option( '_bbp_converter_step',  $step + 1 );
					update_option( '_bbp_converter_start', 0         );
					if ( empty( $start ) ) {
						$this->converter_output( esc_html__( 'No favorites to convert', 'bbpress' ) );
					}
				} else {
					update_option( '_bbp_converter_start', $max + 1 );
					$this->converter_output( sprintf( esc_html__( 'Converting favorites (%1$s - %2$s)', 'bbpress' ), $min, $max ) );
				}

				break;

			// STEP 15. Convert replies.
			case 15 :
				if ( $converter->convert_replies( $start ) ) {
					update_option( '_bbp_converter_step',  $step + 1 );
					update_option( '_bbp_converter_start', 0         );
					if ( empty( $start ) ) {
						$this->converter_output( esc_html__( 'No replies to convert', 'bbpress' ) );
					}
				} else {
					update_option( '_bbp_converter_start', $max + 1 );
					$this->converter_output( sprintf( esc_html__( 'Converting replies (%1$s - %2$s)', 'bbpress' ), $min, $max ) );
				}

				break;

			// STEP 16. Convert anonymous reply authors.
			case 16 :
				if ( $converter->convert_anonymous_reply_authors( $start ) ) {
					update_option( '_bbp_converter_step',  $step + 1 );
					update_option( '_bbp_converter_start', 0         );
					if ( empty( $start ) ) {
						$this->converter_output( esc_html__( 'No anonymous reply authors to convert', 'bbpress' ) );
					}
				} else {
					update_option( '_bbp_converter_start', $max + 1 );
					$this->converter_output( sprintf( esc_html__( 'Converting anonymous reply authors (%1$s - %2$s)', 'bbpress' ), $min, $max ) );
				}

				break;

			// STEP 17. Convert threaded replies parents.
			case 17 :
				if ( $converter->convert_reply_to_parents( $start ) ) {
					update_option( '_bbp_converter_step',  $step + 1 );
					update_option( '_bbp_converter_start', 0         );
					if ( empty( $start ) ) {
						$this->converter_output( esc_html__( 'No threaded replies to convert', 'bbpress' ) );
					}
				} else {
					update_option( '_bbp_converter_start', $max + 1 );
					$this->converter_output( sprintf( esc_html__( 'Calculating threaded replies parents (%1$s - %2$s)', 'bbpress' ), $min, $max ) );
				}

				break;

			default :
				delete_option( '_bbp_converter_step'  );
				delete_option( '_bbp_converter_start' );
				delete_option( '_bbp_converter_query' );

				$this->converter_output( esc_html__( 'Conversion Complete', 'bbpress' ) );

				break;
		}
	}

	/**
	 * Create Tables for fast syncing
	 *
	 * @since 2.1.0 bbPress (r3813)
	 */
	public function sync_table( $drop = false ) {

		// Setup DB
		$bbp_db     = bbp_db();
		$table_name = $bbp_db->prefix . 'bbp_converter_translator';

		// Maybe drop the sync table
		if ( ( true === $drop ) && $bbp_db->get_var( "SHOW TABLES LIKE '{$table_name}'" ) === $table_name ) {
			$bbp_db->query( "DROP TABLE {$table_name}" );
		}

		// Maybe include the upgrade functions, for dbDelta()
		if ( ! function_exists( 'dbDelta' ) ) {
			require_once ABSPATH . '/wp-admin/includes/upgrade.php';
		}

		// Defaults
		$sql              = array();
		$max_index_length = 191;
		$charset_collate  = '';

		// Maybe override the character set
		if ( ! empty( $bbp_db->charset ) ) {
			$charset_collate .= "DEFAULT CHARACTER SET {$bbp_db->charset}";
		}

		// Maybe override the collation
		if ( ! empty( $bbp_db->collate ) ) {
			$charset_collate .= " COLLATE {$bbp_db->collate}";
		}

		/** Translator ********************************************************/

		$sql[] = "CREATE TABLE {$table_name} (
					meta_id mediumint(8) unsigned not null auto_increment,
					value_type varchar(25) null,
					value_id bigint(20) unsigned not null default '0',
					meta_key varchar({$max_index_length}) null,
					meta_value varchar({$max_index_length}) null,
				PRIMARY KEY (meta_id),
					KEY value_id (value_id),
					KEY meta_join (meta_key({$max_index_length}), meta_value({$max_index_length}))
				) {$charset_collate}";

		dbDelta( $sql );
	}
}
