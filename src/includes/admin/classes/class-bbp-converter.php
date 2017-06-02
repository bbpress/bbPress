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
	 * @var int Number of rows
	 */
	public $max = 0;

	/**
	 * @var int Start
	 */
	public $min = 0;

	/**
	 * @var int Step in converter process
	 */
	public $step = 0;

	/**
	 * @var int Number of rows
	 */
	public $rows = 0;

	/**
	 * @var BBP_Converter_Base Type of converter to use
	 */
	public $converter = null;

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
	public function admin_head() {

		// For Converter Status
		wp_enqueue_script( 'postbox' );
		wp_enqueue_script( 'dashboard' );

		// Was a conversion started?
		$started = (bool) get_option( '_bbp_converter_step', false );
		$halt    = defined( 'WP_DEBUG_DISPLAY' ) && WP_DEBUG_DISPLAY; ?>

		<style type="text/css" media="screen">
			/*<![CDATA[*/

			div.bbp-converter-monitor .inside {
				margin-bottom: 0;
			}

			div.bbp-converter-updated,
			div.bbp-converter-warning {
				padding: 5px 0 5px 5px;
			}

			div.bbp-converter-updated.started {
				height: 300px;
				overflow: auto;
			}

			div.bbp-converter-updated p {
				margin: 0;
				padding: 2px;
				float: left;
				clear: left;
			}

			div.bbp-converter-updated p:only-child {
				float: none;
				margin-bottom: 0;
			}

			div.bbp-converter-updated p.loading {
				padding: 2px 25px 2px 2px;
				background-image: url('<?php echo admin_url(); ?>images/spinner.gif');
				background-repeat: no-repeat;
				background-position: center right;
			}

			#bbp-converter-stop {
				display:none;
			}

			/*]]>*/
		</style>

		<script language="javascript">

			var bbpconverter_halt_on_error = <?php echo ( true === $halt    ) ? 'true' : 'false'; ?>,
				bbconverter_started        = <?php echo ( true === $started ) ? 'true' : 'false'; ?>,
				bbconverter_is_running     = false,
				bbconverter_delay_time     = 0,
				bbconverter_run_timer;

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
				if ( false === bbconverter_is_running ) {
					bbconverter_is_running = true;

					jQuery('#bbp-converter-message').addClass('started');
					jQuery('#bbp-converter-start').hide();
					jQuery('#bbp-converter-stop').show();

					if ( true === bbconverter_started ) {
						bbconverter_log( '<p class="loading"><?php esc_html_e( 'Continuing Import', 'bbpress' ); ?></p>' );
					} else {
						bbconverter_log( '<p class="loading"><?php esc_html_e( 'Starting Import', 'bbpress' ); ?></p>' );
					}

					bbconverter_run();
				}

				bbconverter_started = true;
			}

			function bbconverter_run() {
				jQuery.post(ajaxurl, bbconverter_grab_data(), function(response) {
					if ( 'bbp_converter_db_connection_failed' === response ) {
						bbconverter_error_db();
						return;
					}

					var response_length = response.length - 1;
					response = response.substring(0,response_length);
					bbconverter_success(response);
				});
			}

			function bbconverter_stop() {
				jQuery('#bbp-converter-start')
					.val( '<?php esc_html_e( 'Continue', 'bbpress' ); ?>' )
					.show();

				jQuery('#bbp-converter-stop').hide();

				bbconverter_log( '<p><?php esc_html_e( 'Import Stopped (by User)', 'bbpress' ); ?></p>' );
				bbconverter_is_running = false;

				clearTimeout( bbconverter_run_timer );
			}

			function bbconverter_error_db() {
				jQuery('#bbp-converter-start')
					.val( '<?php esc_html_e( 'Start', 'bbpress' ); ?>' )
					.show();

				jQuery('#bbp-converter-stop').hide();

				bbconverter_log( '<p><?php esc_html_e( 'Database Connection Failed', 'bbpress' ); ?></p>' );
				bbconverter_is_running = false;

				clearTimeout( bbconverter_run_timer );
			}

			function bbconverter_error_halt() {
				jQuery('#bbp-converter-start')
					.val( '<?php esc_html_e( 'Continue', 'bbpress' ); ?>' )
					.show();

				jQuery('#bbp-converter-stop').hide();

				bbconverter_log( '<p><?php esc_html_e( 'Import Halted (Error)', 'bbpress' ); ?></p>' );
				bbconverter_is_running = false;

				clearTimeout( bbconverter_run_timer );
			}

			function bbconverter_success(response) {
				bbconverter_log(response);

				if ( response === '<p class="loading"><?php esc_html_e( 'Import Complete', 'bbpress' ); ?></p>' ) {
					bbconverter_success_complete();

				} else if ( bbconverter_is_running ) {
					clearTimeout( bbconverter_run_timer );
					bbconverter_run_timer = setTimeout( 'bbconverter_run()', bbconverter_delay_time );

				} else if ( true === bbpconverter_halt_on_error ) {
					if ( response.indexOf('error') > -1 ) {
						bbconverter_error_halt();
					} else {
						bbconverter_error_halt();
					}
				}
			}

			function bbconverter_success_complete() {
				jQuery('#bbp-converter-start')
					.val( '<?php esc_html_e( 'Start', 'bbpress' ); ?>' )
					.show();

				jQuery('#bbp-converter-stop').hide();

				bbconverter_log('<p><?php printf( esc_html__( 'Repair any missing information: %s', 'bbpress' ), '<a href="' . admin_url() . 'tools.php?page=bbp-repair">' . esc_html__( 'Continue', 'bbpress' ) . '</a>' ); ?></p>' );

				bbconverter_is_running = false;
				bbconverter_started    = false;

				clearTimeout( bbconverter_run_timer );
			}

			function bbconverter_log(text) {
				jQuery('#bbp-converter-message p').removeClass( 'loading' );
				jQuery('#bbp-converter-message').prepend( text );
			}

		</script>

		<?php
	}

	/**
	 * Callback processor
	 *
	 * @since 2.1.0 bbPress (r3813)
	 */
	public function process_callback() {

		// Ready the converter
		$this->check_access();
		$this->maybe_set_memory();
		$this->maybe_restart();
		$this->setup_options();

		// Bail if no converter
		if ( ! empty( $this->converter ) ) {
			$this->do_steps();
		}
	}

	/**
	 * Wrap the converter output in paragraph tags, so styling can be applied
	 *
	 * @since 2.1.0 bbPress (r4052)
	 *
	 * @param string $output
	 */
	private function converter_output( $output = '' ) {

		// Get the last query
		$before = '<p class="loading">';
		$after  = '</p>';

		// Maybe include last query
		$query  = get_option( '_bbp_converter_query' );
		if ( ! empty( $query ) ) {
			$output = $output . '<span class="query">' . esc_attr( $query ) . '</span>';
		}

		// Maybe prepend the step
		$step = ! empty( $this->step )
			? sprintf( '%s: ', $this->step )
			: '';

		// Output
		echo $before . $step . $output . $after;
	}

	/**
	 * Attempt to increase memory and set other system settings
	 *
	 * @since 2.6.0 bbPress (r6460)
	 */
	private function maybe_set_memory() {
		if ( ! ini_get( 'safe_mode' ) ) {
			set_time_limit( 0 );
			ini_set( 'memory_limit',   '256M' );
			ini_set( 'implicit_flush', '1'    );
			ignore_user_abort( true );
		}
	}

	/**
	 * Maybe restart the converter
	 *
	 * @since 2.6.0 bbPress (r6460)
	 */
	private function maybe_restart() {

		// Save step and count so that it can be restarted.
		if ( ! get_option( '_bbp_converter_step' ) || ! empty( $_POST['_bbp_converter_restart'] ) ) {
			update_option( '_bbp_converter_step',  1 );
			update_option( '_bbp_converter_start', 0 );

			$this->step  = 0;
			$this->start = 0;
		}
	}

	/**
	 * Setup converter options
	 *
	 * @since 2.6.0 bbPress (r6460)
	 */
	private function setup_options() {

		// Get starting point
		$this->step  = (int) get_option( '_bbp_converter_step',  1 );
		$this->min   = (int) get_option( '_bbp_converter_start', 0 );

		// Number of rows
		$this->rows = ! empty( $_POST['_bbp_converter_rows'] )
			? (int) $_POST['_bbp_converter_rows']
			: 100;

		// Get boundaries
		$this->max   = ( $this->min + $this->rows ) - 1;
		$this->start = $this->min;

		// Look for platform
		$platform = ! empty( $_POST['_bbp_converter_platform' ] )
			? sanitize_text_field( $_POST['_bbp_converter_platform' ] )
			: get_option( '_bbp_converter_platform' );

		// Maybe include the appropriate converter.
		if ( ! empty( $platform ) ) {
			$this->converter = bbp_new_converter( $platform );
		}
	}

	/**
	 * Check that user can access the converter
	 *
	 * @since 2.6.0 bbPress (r6460)
	 */
	private function check_access() {

		// Bail if user cannot view import page
		if ( ! current_user_can( 'bbp_tools_import_page' ) ) {
			wp_die( '0' );
		}

		// Verify intent
		check_ajax_referer( 'bbp_converter_process' );
	}

	/**
	 * Reset the converter
	 *
	 * @since 2.6.0 bbPress (r6460)
	 */
	private function reset() {
		delete_option( '_bbp_converter_step'  );
		delete_option( '_bbp_converter_start' );
		delete_option( '_bbp_converter_query' );

		$this->start = 0;
		$this->step  = 0;
	}

	/**
	 * Bump the step and reset the start
	 *
	 * @since 2.6.0 bbPress (r6460)
	 */
	private function bump_step() {
		update_option( '_bbp_converter_step',  $this->step + 1 );
		update_option( '_bbp_converter_start', 0               );
	}

	/**
	 * Bump the start within the current step
	 *
	 * @since 2.6.0 bbPress (r6460)
	 */
	private function bump_start() {
		update_option( '_bbp_converter_start', $this->max + 1 );
	}

	/**
	 * Do the converter step
	 *
	 * @since 2.6.0 bbPress (r6460)
	 */
	private function do_steps() {

		switch ( $this->step ) {

			// STEP 1. Clean all tables.
			case 1 :
				if ( ! empty( $_POST['_bbp_converter_clean'] ) ) {
					if ( $this->converter->clean( $this->start ) ) {
						$this->sync_table( true );
						$this->bump_step();

						if ( empty( $this->start ) ) {
							$this->converter_output( esc_html__( 'Recreating sync-table', 'bbpress' ) );
						}
					} else {
						$this->converter_output( sprintf( esc_html__( 'Deleting previously converted data (%1$s - %2$s)', 'bbpress' ), $this->min, $this->max ) );
						$this->bump_start();
					}
				} else {
					$this->converter_output( esc_html__( 'Skipping sync-table clean-up', 'bbpress' ) );
					$this->sync_table( false );
					$this->bump_step();
				}

				break;

			// STEP 2. Convert users.
			case 2 :
				if ( true === $this->converter->convert_users ) {
					if ( $this->converter->convert_users( $this->start ) ) {
						$this->bump_step();

						if ( empty( $this->start ) ) {
							$this->converter_output( esc_html__( 'No users to import', 'bbpress' ) );
						}
					} else {
						$this->bump_start();
						$this->converter_output( sprintf(  esc_html__( 'Converting users (%1$s - %2$s)', 'bbpress' ), $this->min, $this->max ) );
					}
				} else {
					$this->bump_step();
					$this->converter_output( esc_html__( 'Skipping user clean-up', 'bbpress' ) );
				}

				break;

			// STEP 3. Clean passwords.
			case 3 :
				if ( true === $this->converter->convert_users ) {
					if ( $this->converter->clean_passwords( $this->start ) ) {
						$this->bump_step();

						if ( empty( $this->start ) ) {
							$this->converter_output( esc_html__( 'No passwords to clear', 'bbpress' ) );
						}
					} else {
						$this->bump_start();
						$this->converter_output( sprintf( esc_html__( 'Delete users WordPress default passwords (%1$s - %2$s)', 'bbpress' ), $this->min, $this->max ) );
					}
				} else {
					$this->bump_step();
					$this->converter_output( esc_html__( 'Skipping password clean-up', 'bbpress' ) );
				}

				break;

			// STEP 4. Convert forums.
			case 4 :
				if ( $this->converter->convert_forums( $this->start ) ) {
					$this->bump_step();

					if ( empty( $this->start ) ) {
						$this->converter_output( esc_html__( 'No forums to import', 'bbpress' ) );
					}
				} else {
					$this->bump_start();
					$this->converter_output( sprintf( esc_html__( 'Converting forums (%1$s - %2$s)', 'bbpress' ), $this->min, $this->max ) );
				}

				break;

			// STEP 5. Convert forum parents.
			case 5 :
				if ( $this->converter->convert_forum_parents( $this->start ) ) {
					$this->bump_step();

					if ( empty( $this->start ) ) {
						$this->converter_output( esc_html__( 'No forum parents to import', 'bbpress' ) );
					}
				} else {
					$this->bump_start();
					$this->converter_output( sprintf( esc_html__( 'Calculating forum hierarchy (%1$s - %2$s)', 'bbpress' ), $this->min, $this->max ) );
				}

				break;

			// STEP 6. Convert forum subscriptions.
			case 6 :
				if ( $this->converter->convert_forum_subscriptions( $this->start ) ) {
					$this->bump_step();

					if ( empty( $this->start ) ) {
						$this->converter_output( esc_html__( 'No forum subscriptions to import', 'bbpress' ) );
					}
				} else {
					$this->bump_start();
					$this->converter_output( sprintf( esc_html__( 'Converting forum subscriptions (%1$s - %2$s)', 'bbpress' ), $this->min, $this->max ) );
				}

				break;

			// STEP 7. Convert topics.
			case 7 :
				if ( $this->converter->convert_topics( $this->start ) ) {
					$this->bump_step();

					if ( empty( $this->start ) ) {
						$this->converter_output( esc_html__( 'No topics to import', 'bbpress' ) );
					}
				} else {
					$this->bump_start();
					$this->converter_output( sprintf( esc_html__( 'Converting topics (%1$s - %2$s)', 'bbpress' ), $this->min, $this->max ) );
				}

				break;

			// STEP 8. Convert anonymous topic authors.
			case 8 :
				if ( $this->converter->convert_anonymous_topic_authors( $this->start ) ) {
					$this->bump_step();

					if ( empty( $this->start ) ) {
						$this->converter_output( esc_html__( 'No anonymous topic authors to import', 'bbpress' ) );
					}
				} else {
					$this->bump_start();
					$this->converter_output( sprintf( esc_html__( 'Converting anonymous topic authors (%1$s - %2$s)', 'bbpress' ), $this->min, $this->max ) );
				}

				break;

			// STEP 9. Stick topics.
			case 9 :
				if ( $this->converter->convert_topic_stickies( $this->start ) ) {
					$this->bump_step();

					if ( empty( $this->start ) ) {
						$this->converter_output( esc_html__( 'No stickies to stick', 'bbpress' ) );
					}
				} else {
					$this->bump_start();
					$this->converter_output( sprintf( esc_html__( 'Calculating topic stickies (%1$s - %2$s)', 'bbpress' ), $this->min, $this->max ) );
				}

				break;

			// STEP 10. Stick to front topics (Super Sicky).
			case 10 :
				if ( $this->converter->convert_topic_super_stickies( $this->start ) ) {
					$this->bump_step();

					if ( empty( $this->start ) ) {
						$this->converter_output( esc_html__( 'No super stickies to stick', 'bbpress' ) );
					}
				} else {
					$this->bump_start();
					$this->converter_output( sprintf( esc_html__( 'Calculating topic super stickies (%1$s - %2$s)', 'bbpress' ), $this->min, $this->max ) );
				}

				break;

			// STEP 11. Closed topics.
			case 11 :
				if ( $this->converter->convert_topic_closed_topics( $this->start ) ) {
					$this->bump_step();

					if ( empty( $this->start ) ) {
						$this->converter_output( esc_html__( 'No closed topics to close', 'bbpress' ) );
					}
				} else {
					$this->bump_start();
					$this->converter_output( sprintf( esc_html__( 'Calculating closed topics (%1$s - %2$s)', 'bbpress' ), $this->min, $this->max ) );
				}

				break;

			// STEP 12. Convert topic tags.
			case 12 :
				if ( $this->converter->convert_tags( $this->start ) ) {
					$this->bump_step();

					if ( empty( $this->start ) ) {
						$this->converter_output( esc_html__( 'No topic tags to import', 'bbpress' ) );
					}
				} else {
					$this->bump_start();
					$this->converter_output( sprintf( esc_html__( 'Converting topic tags (%1$s - %2$s)', 'bbpress' ), $this->min, $this->max ) );
				}

				break;

			// STEP 13. Convert topic subscriptions.
			case 13 :
				if ( $this->converter->convert_topic_subscriptions( $this->start ) ) {
					$this->bump_step();

					if ( empty( $this->start ) ) {
						$this->converter_output( esc_html__( 'No topic subscriptions to import', 'bbpress' ) );
					}
				} else {
					$this->bump_start();
					$this->converter_output( sprintf( esc_html__( 'Converting topic subscriptions (%1$s - %2$s)', 'bbpress' ), $this->min, $this->max ) );
				}

				break;

			// STEP 14. Convert topic favorites.
			case 14 :
				if ( $this->converter->convert_favorites( $this->start ) ) {
					$this->bump_step();

					if ( empty( $this->start ) ) {
						$this->converter_output( esc_html__( 'No favorites to import', 'bbpress' ) );
					}
				} else {
					$this->bump_start();
					$this->converter_output( sprintf( esc_html__( 'Converting favorites (%1$s - %2$s)', 'bbpress' ), $this->min, $this->max ) );
				}

				break;

			// STEP 15. Convert replies.
			case 15 :
				if ( $this->converter->convert_replies( $this->start ) ) {
					$this->bump_step();

					if ( empty( $this->start ) ) {
						$this->converter_output( esc_html__( 'No replies to import', 'bbpress' ) );
					}
				} else {
					$this->bump_start();
					$this->converter_output( sprintf( esc_html__( 'Converting replies (%1$s - %2$s)', 'bbpress' ), $this->min, $this->max ) );
				}

				break;

			// STEP 16. Convert anonymous reply authors.
			case 16 :
				if ( $this->converter->convert_anonymous_reply_authors( $this->start ) ) {
					$this->bump_step();

					if ( empty( $this->start ) ) {
						$this->converter_output( esc_html__( 'No anonymous reply authors to import', 'bbpress' ) );
					}
				} else {
					$this->bump_start();
					$this->converter_output( sprintf( esc_html__( 'Converting anonymous reply authors (%1$s - %2$s)', 'bbpress' ), $this->min, $this->max ) );
				}

				break;

			// STEP 17. Convert threaded replies parents.
			case 17 :
				if ( $this->converter->convert_reply_to_parents( $this->start ) ) {
					$this->bump_step();

					if ( empty( $this->start ) ) {
						$this->converter_output( esc_html__( 'No threaded replies to import', 'bbpress' ) );
					}
				} else {
					$this->bump_start();
					$this->converter_output( sprintf( esc_html__( 'Calculating threaded replies parents (%1$s - %2$s)', 'bbpress' ), $this->min, $this->max ) );
				}

				break;

			default :
				$this->reset();
				$this->converter_output( esc_html__( 'Import Complete', 'bbpress' ) );

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
		$bbp_db       = bbp_db();
		$table_name   = $bbp_db->prefix . 'bbp_converter_translator';
		$table_exists = $bbp_db->get_var( "SHOW TABLES LIKE '{$table_name}'" ) === $table_name;

		// Maybe drop the sync table
		if ( ( true === $drop ) && ( true === $table_exists ) ) {
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
