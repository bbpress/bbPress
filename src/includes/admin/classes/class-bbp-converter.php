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

if ( ! class_exists( 'BBP_Converter' ) ) :
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
	public $start = 0;

	/**
	 * @var int Step in converter process
	 */
	public $step = 0;

	/**
	 * @var int Number of rows
	 */
	public $rows = 0;

	/**
	 * @var int Maximum number of converter steps
	 */
	public $max_steps = 17;

	/**
	 * @var int Number of rows in the current step
	 */
	public $rows_in_step = 0;

	/**
	 * @var int Percent complete of current step
	 */
	public $step_percentage = 0;

	/**
	 * @var int Percent complete of all step
	 */
	public $total_percentage = 0;

	/**
	 * @var int Name of source forum platform
	 */
	public $platform = '';

	/**
	 * @var BBP_Converter_Base Type of converter to use
	 */
	public $converter = null;

	/**
	 * @var string Path to included platforms
	 */
	public $converters_dir = '';

	/**
	 * @var array Map of steps to methods
	 */
	private $steps = array(
		1  => 'sync_table',
		2  => 'users',
		3  => 'passwords',
		4  => 'forums',
		5  => 'forum_hierarchy',
		6  => 'forum_subscriptions',
		7  => 'topics',
		8  => 'topics_authors',
		9  => 'stickies',
		10 => 'super_stickies',
		11 => 'closed_topics',
		12 => 'topic_tags',
		13 => 'topic_subscriptions',
		14 => 'topic_favorites',
		15 => 'replies',
		16 => 'reply_authors',
		17 => 'reply_hierarchy'
	);

	/**
	 * The main bbPress Converter loader
	 *
	 * @since 2.1.0 bbPress (r3813)
	 */
	public function __construct() {
		$this->setup_globals();
		$this->setup_actions();
	}

	/**
	 * Admin globals
	 *
	 * @since 2.6.0 bbPress (r6598)
	 */
	public function setup_globals() {
		$this->converters_dir = bbp_setup_admin()->admin_dir . 'converters/';
	}

	/**
	 * Setup the default actions
	 *
	 * @since 2.1.0 bbPress (r3813)
	 */
	public function setup_actions() {

		// Attach to the admin head with our ajax requests cycle and css
		add_action( 'admin_head-tools_page_bbp-converter', array( $this, 'admin_head' ) );

		// Attach to the admin ajax request to process cycles
		add_action( 'wp_ajax_bbp_converter_process', array( $this, 'process_callback' ) );
	}

	/**
	 * Admin scripts
	 *
	 * @since 2.1.0 bbPress (r3813)
	 */
	public function admin_head() {

		// Enqueue scripts
		wp_enqueue_script( 'bbp-converter' );

		// Localize JS
		wp_localize_script( 'bbp-converter', 'BBP_Converter', array(

			// Nonce
			'ajax_nonce' => wp_create_nonce( 'bbp_converter_process' ),

			// UI State
			'state' => array(
				'delay'         => (int)  get_option( '_bbp_converter_delay_time', 2 ),
				'started'       => (bool) get_option( '_bbp_converter_step',       0 ),
				'running'       => false,
				'status'        => false,
				'step_percent'  => $this->step_percentage,
				'total_percent' => $this->total_percentage
			),

			// Strings
			'strings' => array(

				// Button text
				'button_start'        => esc_html__( 'Start',    'bbpress' ),
				'button_continue'     => esc_html__( 'Continue', 'bbpress' ),

				// Start button clicked
				'start_start'         => esc_html__( 'Starting Import...',   'bbpress' ),
				'start_continue'      => esc_html__( 'Continuing Import...', 'bbpress' ),

				// Import
				'import_complete'     => esc_html__( 'Import Finished.',            'bbpress' ),
				'import_stopped_user' => esc_html__( 'Import Stopped (by User.)',   'bbpress' ),
				'import_error_halt'   => esc_html__( 'Import Halted (Error.)',      'bbpress' ),
				'import_error_db'     => esc_html__( 'Database Connection Failed.', 'bbpress' ),

				// Status
				'status_complete'     => esc_html__( 'Finished',              'bbpress' ),
				'status_stopped'      => esc_html__( 'Stopped',               'bbpress' ),
				'status_starting'     => esc_html__( 'Starting',              'bbpress' ),
				'status_up_next'      => esc_html__( 'Doing step %s...',      'bbpress' ),
				'status_counting'     => esc_html__( 'Next in %s seconds...', 'bbpress' )
			)
		) );
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
		$this->maybe_update_options();

		// Bail if no converter
		if ( ! empty( $this->converter ) ) {
			$this->do_steps();
		}
	}

	/**
	 * Wrap the converter output in HTML, so styling can be applied
	 *
	 * @since 2.1.0 bbPress (r4052)
	 *
	 * @param string $output
	 */
	private function converter_response( $output = '' ) {

		// Sanitize output
		$output = wp_kses_data( $output );

		// Maybe prepend the step
		if ( ! empty( $this->step ) ) {

			// Include percentage
			if ( ! empty( $this->rows_in_step ) ) {
				$progress = sprintf( '<span class="step">%s.</span><span class="output">%s</span><span class="mini-step">%s</span>', $this->step, $output, $this->step_percentage . '%' );

			// Don't include percentage
			} else {
				$progress = sprintf( '<span class="step">%s.</span><span class="output">%s</span>', $this->step, $output );
			}

		// Raw text
		} else {
			$progress = $output;
		}

		// Output
		wp_send_json_success( array(
			'query'         => get_option( '_bbp_converter_query', '' ),
			'current_step'  => $this->step,
			'final_step'    => $this->max_steps,
			'rows_in_step'  => $this->rows_in_step,
			'step_percent'  => $this->step_percentage,
			'total_percent' => $this->total_percentage,
			'progress'      => $progress
		) );
	}

	/**
	 * Attempt to increase memory and set other system settings
	 *
	 * @since 2.6.0 bbPress (r6460)
	 */
	private function maybe_set_memory() {

		// Filter args
		$r = apply_filters( 'bbp_converter_php_ini_overrides', array(
			'implicit_flush'     => '1',
			'memory_limit'       => '256M',
			'max_execution_time' => HOUR_IN_SECONDS * 6
		) );

		// Get disabled PHP functions (to avoid using them)
		$disabled = explode( ',', @ini_get( 'disable_functions' ) );

		// Maybe avoid terminating when the client goes away (if function is not disabled)
		if ( ! in_array( 'ignore_user_abort', $disabled, true ) ) {
			@ignore_user_abort( true );
		}

		// Maybe set memory & time limits, and flush style (if function is not disabled)
		if ( ! in_array( 'ini_set', $disabled, true ) ) {
			foreach ( $r as $key => $value ) {
				@ini_set( $key, $value );
			}
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
			$this->step             = 1;
			$this->start            = 0;
			$this->step_percentage  = 0;
			$this->total_percentage = 0;
			$this->rows_in_step     = 0;
			$this->maybe_update_options();
		}
	}

	/**
	 * Maybe update options
	 *
	 * @since 2.6.0 bbPress (r6637)
	 */
	private function maybe_update_options() {

		// Default options
		$options = array(

			// Step & Start
			'_bbp_converter_step'         => $this->step,
			'_bbp_converter_start'        => $this->start,
			'_bbp_converter_rows_in_step' => $this->rows_in_step,

			// Halt
			'_bbp_converter_halt' => ! empty( $_POST['_bbp_converter_halt'] )
				? (int) $_POST['_bbp_converter_halt']
				: 0,

			// Rows (bound between 1 and 5000)
			'_bbp_converter_rows' => ! empty( $_POST['_bbp_converter_rows'] )
				? min( max( (int) $_POST['_bbp_converter_rows'], 1 ), 5000 )
				: 0,

			// Platform
			'_bbp_converter_platform' => ! empty( $_POST['_bbp_converter_platform' ] )
				? sanitize_text_field( $_POST['_bbp_converter_platform' ] )
				: '',

			// Convert Users
			'_bbp_converter_convert_users' => ! empty( $_POST['_bbp_converter_convert_users'] )
				? (bool) $_POST['_bbp_converter_convert_users']
				: false,

			// DB User
			'_bbp_converter_db_user' => ! empty( $_POST['_bbp_converter_db_user'] )
				? sanitize_text_field( $_POST['_bbp_converter_db_user'] )
				: '',

			// DB Password
			'_bbp_converter_db_pass' => ! empty( $_POST['_bbp_converter_db_pass'] )
				? sanitize_text_field( $_POST['_bbp_converter_db_pass'] )
				: '',

			// DB Name
			'_bbp_converter_db_name' => ! empty( $_POST['_bbp_converter_db_name'] )
				? sanitize_text_field( $_POST['_bbp_converter_db_name'] )
				: '',

			// DB Server
			'_bbp_converter_db_server' => ! empty( $_POST['_bbp_converter_db_server'] )
				? sanitize_text_field( $_POST['_bbp_converter_db_server'] )
				: '',

			// DB Port
			'_bbp_converter_db_port' => ! empty( $_POST['_bbp_converter_db_port'] )
				? (int) sanitize_text_field( $_POST['_bbp_converter_db_port'] )
				: '',

			// DB Table Prefix
			'_bbp_converter_db_prefix' => ! empty( $_POST['_bbp_converter_db_prefix'] )
				? sanitize_text_field( $_POST['_bbp_converter_db_prefix'] )
				: ''
		);

		// Update/delete options
		foreach ( $options as $key => $value ) {
			update_option( $key, $value );
		}
	}

	/**
	 * Setup converter options
	 *
	 * @since 2.6.0 bbPress (r6460)
	 */
	private function setup_options() {

		// Set starting point & rows
		$this->step         = (int) get_option( '_bbp_converter_step',         1   );
		$this->start        = (int) get_option( '_bbp_converter_start',        0   );
		$this->rows         = (int) get_option( '_bbp_converter_rows',         100 );
		$this->rows_in_step = (int) get_option( '_bbp_converter_rows_in_step', 0   );

		// Set boundaries
		$this->max          = ( $this->start + $this->rows ) - 1;

		// Set platform
		$this->platform     = get_option( '_bbp_converter_platform' );

		// Total percentage
		$this->total_percentage = round( ( $this->step / $this->max_steps ) * 100, 2 );

		// Total mini steps
		if ( $this->rows_in_step > 0 ) {
			$total_mini_steps      = ceil( $this->rows_in_step / $this->rows );
			$current_mini_step     = ceil( $this->start        / $this->rows );
			$this->step_percentage = round( ( $current_mini_step / $total_mini_steps ) * 100, 2 );
		} else {
			$this->step_percentage = 0;
		}

		// Maybe include the appropriate converter.
		if ( ! empty( $this->platform ) ) {
			$this->converter = bbp_new_converter( $this->platform );
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
		update_option( '_bbp_converter_step',         0  );
		update_option( '_bbp_converter_start',        0  );
		update_option( '_bbp_converter_rows_in_step', 0  );
		update_option( '_bbp_converter_query',        '' );
	}

	/**
	 * Bump the step and reset the start
	 *
	 * @since 2.6.0 bbPress (r6460)
	 */
	private function bump_step() {

		// Next step
		$next_step = (int) ( $this->step + 1 );

		// Don't let step go over max
		$step = ( $next_step <= $this->max_steps )
			? $next_step
			: 0;

		// Update step and start at 0
		update_option( '_bbp_converter_step',         $step );
		update_option( '_bbp_converter_start',        0     );
		update_option( '_bbp_converter_rows_in_step', 0     );
	}

	/**
	 * Bump the start within the current step
	 *
	 * @since 2.6.0 bbPress (r6460)
	 */
	private function bump_start() {

		// Set rows in step from option
		$this->rows_in_step = get_option( '_bbp_converter_rows_in_step', 0 );

		// Get rows to start from
		$start = (int) ( $this->start + $this->rows );

		// Enforce maximum if exists
		if ( $this->rows_in_step > 0 ) {

			// Start cannot be larger than total rows
			if ( $start > $this->rows_in_step ) {
				$start = $this->rows_in_step;
			}

			// Max can't be greater than total rows
			if ( $this->max > $this->rows_in_step ) {
				$this->max = $this->rows_in_step;
			}
		}

		// Update the start option
		update_option( '_bbp_converter_start', $start );
	}

	/**
	 * Do the converter step
	 *
	 * @since 2.6.0 bbPress (r6460)
	 */
	private function do_steps() {

		// Step exists in map, and method exists
		if ( isset( $this->steps[ $this->step ] ) && method_exists( $this, "step_{$this->steps[ $this->step ]}" ) ) {
			return call_user_func( array( $this, "step_{$this->steps[ $this->step ]}" ) );
		}

		// Done!
		$this->step_done();
	}

	/** Steps *****************************************************************/

	/**
	 * Maybe clean the sync table
	 *
	 * @since 2.6.0 bbPress (r6513)
	 */
	private function step_sync_table() {
		if ( true === $this->converter->clean ) {
			if ( $this->converter->clean() ) {
				$this->bump_step();
				$this->sync_table( true );

				empty( $this->start )
					? $this->converter_response( esc_html__( 'Readying sync-table', 'bbpress' ) )
					: $this->converter_response( esc_html__( 'Sync-table ready',    'bbpress' ) );
			} else {
				$this->bump_start();
				$this->converter_response( sprintf( esc_html__( 'Deleting previously converted data (%1$s through %2$s)', 'bbpress' ), $this->start, $this->max ) );
			}

			$this->converter->clean = false;
		} else {
			$this->bump_step();
			$this->sync_table( false );
			$this->converter_response( esc_html__( 'Skipping sync-table clean-up', 'bbpress' ) );
		}
	}

	/**
	 * Maybe convert users
	 *
	 * @since 2.6.0 bbPress (r6513)
	 */
	private function step_users() {
		if ( true === $this->converter->convert_users ) {
			if ( $this->converter->convert_users( $this->start ) ) {
				$this->bump_step();

				empty( $this->start )
					? $this->converter_response( esc_html__( 'No users to import', 'bbpress' ) )
					: $this->converter_response( esc_html__( 'All users imported', 'bbpress' ) );
			} else {
				$this->bump_start();
				$this->converter_response( sprintf( esc_html__( 'Converting users (%1$s through %2$s of %3$s)', 'bbpress' ), $this->start, $this->max, $this->rows_in_step ) );
			}
		} else {
			$this->bump_step();
			$this->converter_response( esc_html__( 'Skipping user clean-up', 'bbpress' ) );
		}
	}

	/**
	 * Maybe clean up passwords
	 *
	 * @since 2.6.0 bbPress (r6513)
	 */
	private function step_passwords() {
		if ( true === $this->converter->convert_users ) {
			if ( $this->converter->clean_passwords( $this->start ) ) {
				$this->bump_step();

				empty( $this->start )
					? $this->converter_response( esc_html__( 'No passwords to clear', 'bbpress' ) )
					: $this->converter_response( esc_html__( 'All passwords cleared', 'bbpress' ) );
			} else {
				$this->bump_start();
				$this->converter_response( sprintf( esc_html__( 'Delete default WordPress user passwords (%1$s through %2$s)', 'bbpress' ), $this->start, $this->max ) );
			}
		} else {
			$this->bump_step();
			$this->converter_response( esc_html__( 'Skipping password clean-up', 'bbpress' ) );
		}
	}

	/**
	 * Maybe convert forums
	 *
	 * @since 2.6.0 bbPress (r6513)
	 */
	private function step_forums() {
		if ( $this->converter->convert_forums( $this->start ) ) {
			$this->bump_step();

			empty( $this->start )
				? $this->converter_response( esc_html__( 'No forums to import', 'bbpress' ) )
				: $this->converter_response( esc_html__( 'All forums imported', 'bbpress' ) );
		} else {
			$this->bump_start();
			$this->converter_response( sprintf( esc_html__( 'Converting forums (%1$s through %2$s of %3$s)', 'bbpress' ), $this->start, $this->max, $this->rows_in_step ) );
		}
	}

	/**
	 * Maybe walk the forum hierarchy
	 *
	 * @since 2.6.0 bbPress (r6513)
	 */
	private function step_forum_hierarchy() {
		if ( $this->converter->convert_forum_parents( $this->start ) ) {
			$this->bump_step();

			empty( $this->start )
				? $this->converter_response( esc_html__( 'No forum parents to import', 'bbpress' ) )
				: $this->converter_response( esc_html__( 'All forum parents imported', 'bbpress' ) );
		} else {
			$this->bump_start();
			$this->converter_response( sprintf( esc_html__( 'Calculating forum hierarchy (%1$s through %2$s of %3$s)', 'bbpress' ), $this->start, $this->max, $this->rows_in_step ) );
		}
	}

	/**
	 * Maybe convert forum subscriptions
	 *
	 * @since 2.6.0 bbPress (r6513)
	 */
	private function step_forum_subscriptions() {
		if ( $this->converter->convert_forum_subscriptions( $this->start ) ) {
			$this->bump_step();

			empty( $this->start )
				? $this->converter_response( esc_html__( 'No forum subscriptions to import', 'bbpress' ) )
				: $this->converter_response( esc_html__( 'All forum subscriptions imported', 'bbpress' ) );
		} else {
			$this->bump_start();
			$this->converter_response( sprintf( esc_html__( 'Converting forum subscriptions (%1$s through %2$s of %3$s)', 'bbpress' ), $this->start, $this->max, $this->rows_in_step ) );
		}
	}

	/**
	 * Maybe convert topics
	 *
	 * @since 2.6.0 bbPress (r6513)
	 */
	private function step_topics() {
		if ( $this->converter->convert_topics( $this->start ) ) {
			$this->bump_step();

			empty( $this->start )
				? $this->converter_response( esc_html__( 'No topics to import', 'bbpress' ) )
				: $this->converter_response( esc_html__( 'All topics imported', 'bbpress' ) );
		} else {
			$this->bump_start();
			$this->converter_response( sprintf( esc_html__( 'Converting topics (%1$s through %2$s of %3$s)', 'bbpress' ), $this->start, $this->max, $this->rows_in_step ) );
		}
	}

	/**
	 * Maybe convert topic authors (anonymous)
	 *
	 * @since 2.6.0 bbPress (r6513)
	 */
	private function step_topics_authors() {
		if ( $this->converter->convert_anonymous_topic_authors( $this->start ) ) {
			$this->bump_step();

			empty( $this->start )
				? $this->converter_response( esc_html__( 'No anonymous topic authors to import', 'bbpress' ) )
				: $this->converter_response( esc_html__( 'All anonymous topic authors imported', 'bbpress' ) );
		} else {
			$this->bump_start();
			$this->converter_response( sprintf( esc_html__( 'Converting anonymous topic authors (%1$s through %2$s of %3$s)', 'bbpress' ), $this->start, $this->max, $this->rows_in_step ) );
		}
	}

	/**
	 * Maybe convert sticky topics (not super stickies)
	 *
	 * @since 2.6.0 bbPress (r6513)
	 */
	private function step_stickies() {
		if ( $this->converter->convert_topic_stickies( $this->start ) ) {
			$this->bump_step();

			empty( $this->start )
				? $this->converter_response( esc_html__( 'No stickies to import', 'bbpress' ) )
				: $this->converter_response( esc_html__( 'All stickies imported', 'bbpress' ) );
		} else {
			$this->bump_start();
			$this->converter_response( sprintf( esc_html__( 'Calculating topic stickies (%1$s through %2$s of %3$s)', 'bbpress' ), $this->start, $this->max, $this->rows_in_step ) );
		}
	}

	/**
	 * Maybe convert super-sticky topics (not per-forum)
	 *
	 * @since 2.6.0 bbPress (r6513)
	 */
	private function step_super_stickies() {
		if ( $this->converter->convert_topic_super_stickies( $this->start ) ) {
			$this->bump_step();

			empty( $this->start )
				? $this->converter_response( esc_html__( 'No super stickies to import', 'bbpress' ) )
				: $this->converter_response( esc_html__( 'All super stickies imported', 'bbpress' ) );
		} else {
			$this->bump_start();
			$this->converter_response( sprintf( esc_html__( 'Calculating topic super stickies (%1$s through %2$s of %3$s)', 'bbpress' ), $this->start, $this->max, $this->rows_in_step ) );
		}
	}

	/**
	 * Maybe close converted topics
	 *
	 * @since 2.6.0 bbPress (r6513)
	 */
	private function step_closed_topics() {
		if ( $this->converter->convert_topic_closed_topics( $this->start ) ) {
			$this->bump_step();

			empty( $this->start )
				? $this->converter_response( esc_html__( 'No closed topics to import', 'bbpress' ) )
				: $this->converter_response( esc_html__( 'All closed topics imported', 'bbpress' ) );
		} else {
			$this->bump_start();
			$this->converter_response( sprintf( esc_html__( 'Calculating closed topics (%1$s through %2$s of %3$s)', 'bbpress' ), $this->start, $this->max, $this->rows_in_step ) );
		}
	}

	/**
	 * Maybe convert topic tags
	 *
	 * @since 2.6.0 bbPress (r6513)
	 */
	private function step_topic_tags() {
		if ( $this->converter->convert_tags( $this->start ) ) {
			$this->bump_step();

			empty( $this->start )
				? $this->converter_response( esc_html__( 'No topic tags to import', 'bbpress' ) )
				: $this->converter_response( esc_html__( 'All topic tags imported', 'bbpress' ) );
		} else {
			$this->bump_start();
			$this->converter_response( sprintf( esc_html__( 'Converting topic tags (%1$s through %2$s of %3$s)', 'bbpress' ), $this->start, $this->max, $this->rows_in_step ) );
		}
	}

	/**
	 * Maybe convert topic subscriptions
	 *
	 * @since 2.6.0 bbPress (r6513)
	 */
	private function step_topic_subscriptions() {
		if ( $this->converter->convert_topic_subscriptions( $this->start ) ) {
			$this->bump_step();

			empty( $this->start )
				? $this->converter_response( esc_html__( 'No topic subscriptions to import', 'bbpress' ) )
				: $this->converter_response( esc_html__( 'All topic subscriptions imported', 'bbpress' ) );
		} else {
			$this->bump_start();
			$this->converter_response( sprintf( esc_html__( 'Converting topic subscriptions (%1$s through %2$s of %3$s)', 'bbpress' ), $this->start, $this->max, $this->rows_in_step ) );
		}
	}

	/**
	 * Maybe convert topic favorites
	 *
	 * @since 2.6.0 bbPress (r6513)
	 */
	private function step_topic_favorites() {
		if ( $this->converter->convert_favorites( $this->start ) ) {
			$this->bump_step();

			empty( $this->start )
				? $this->converter_response( esc_html__( 'No favorites to import', 'bbpress' ) )
				: $this->converter_response( esc_html__( 'All favorites imported', 'bbpress' ) );
		} else {
			$this->bump_start();
			$this->converter_response( sprintf( esc_html__( 'Converting favorites (%1$s through %2$s of %3$s)', 'bbpress' ), $this->start, $this->max, $this->rows_in_step ) );
		}
	}

	/**
	 * Maybe convert replies
	 *
	 * @since 2.6.0 bbPress (r6513)
	 */
	private function step_replies() {
		if ( $this->converter->convert_replies( $this->start ) ) {
			$this->bump_step();

			empty( $this->start )
				? $this->converter_response( esc_html__( 'No replies to import', 'bbpress' ) )
				: $this->converter_response( esc_html__( 'All replies imported', 'bbpress' ) );
		} else {
			$this->bump_start();
			$this->converter_response( sprintf( esc_html__( 'Converting replies (%1$s through %2$s of %3$s)', 'bbpress' ), $this->start, $this->max, $this->rows_in_step ) );
		}
	}

	/**
	 * Maybe convert reply authors (anonymous)
	 *
	 * @since 2.6.0 bbPress (r6513)
	 */
	private function step_reply_authors() {
		if ( $this->converter->convert_anonymous_reply_authors( $this->start ) ) {
			$this->bump_step();

			empty( $this->start )
				? $this->converter_response( esc_html__( 'No anonymous reply authors to import', 'bbpress' ) )
				: $this->converter_response( esc_html__( 'All anonymous reply authors imported', 'bbpress' ) );
		} else {
			$this->bump_start();
			$this->converter_response( sprintf( esc_html__( 'Converting anonymous reply authors (%1$s through %2$s of %3$s)', 'bbpress' ), $this->start, $this->max, $this->rows_in_step ) );
		}
	}

	/**
	 * Maybe convert the threaded reply hierarchy
	 *
	 * @since 2.6.0 bbPress (r6513)
	 */
	private function step_reply_hierarchy() {
		if ( $this->converter->convert_reply_to_parents( $this->start ) ) {
			$this->bump_step();

			empty( $this->start )
				? $this->converter_response( esc_html__( 'No threaded replies to import', 'bbpress' ) )
				: $this->converter_response( esc_html__( 'All threaded replies imported', 'bbpress' ) );
		} else {
			$this->bump_start();
			$this->converter_response( sprintf( esc_html__( 'Calculating threaded replies parents (%1$s through %2$s of %3$s)', 'bbpress' ), $this->start, $this->max, $this->rows_in_step ) );
		}
	}

	/**
	 * Done!
	 *
	 * @since 2.6.0 bbPress (r6513)
	 */
	private function step_done() {
		$this->reset();
		$this->converter_response( esc_html__( 'Import Finished', 'bbpress' ) );
	}

	/** Helper Table **********************************************************/

	/**
	 * Create Tables for fast syncing
	 *
	 * @since 2.1.0 bbPress (r3813)
	 */
	public static function sync_table( $drop = false ) {

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
		$charset_collate  = '';

		// https://bbpress.trac.wordpress.org/ticket/3145
		$max_index_length = 75;

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
endif;
