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
	 * @var int Maximum number of converter steps
	 */
	public $max_steps = 17;

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

		// Attach to the admin ajax request to process cycles
		add_action( 'wp_ajax_bbp_converter_process', array( $this, 'process_callback' ) );
	}

	/**
	 * Admin scripts
	 *
	 * @since 2.1.0 bbPress (r3813)
	 */
	public function admin_head() {

		// Variables
		$bbp        = bbpress();
		$suffix     = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';
		$repair_url = add_query_arg( array(
			'page' => 'bbp-repair'
		), admin_url() );

		// Enqueue scripts
		wp_enqueue_script( 'postbox'   );
		wp_enqueue_script( 'dashboard' );
		wp_enqueue_script( 'bbp-converter', $bbp->admin->js_url . 'converter' . $suffix . '.js', array( 'jquery', 'postbox' ), $bbp->version );

		// Localize JS
		wp_localize_script( 'bbp-converter', 'BBP_Converter', array(

			// Vars
			'ajax_nonce' => wp_create_nonce( 'bbp_converter_process' ),
			'halt'       => (bool) defined( 'WP_DEBUG_DISPLAY' ) && WP_DEBUG_DISPLAY,
			'started'    => (bool) get_option( '_bbp_converter_step',       false ),
			'delay'      => (int)  get_option( '_bbp_converter_delay_time', 1     ),
			'running'    => false,
			'complete'   => false,
			'timer'      => false,

			// Strings
			'strings'    => array(

				// Button text
				'button_start'    => esc_html__( 'Start',    'bbpress' ),
				'button_continue' => esc_html__( 'Continue', 'bbpress' ),

				// Start button clicked
				'start_start'    => esc_html__( 'Starting Import',   'bbpress' ),
				'start_continue' => esc_html__( 'Continuing Import', 'bbpress' ),

				// Import
				'import_success'      => sprintf( esc_html__( 'Repair any missing information: %s', 'bbpress' ), '<a href="' . esc_url( $repair_url ) . '">' . esc_html__( 'Continue', 'bbpress' ) . '</a>' ),
				'import_complete'     => esc_html__( 'Import Finished',            'bbpress' ),
				'import_stopped_user' => esc_html__( 'Import Stopped (by User)',   'bbpress' ),
				'import_error_db'     => esc_html__( 'Database Connection Failed', 'bbpress' ),
				'import_error_halt'   => esc_html__( 'Import Halted (Error)',      'bbpress' ),

				// Timer
				'timer_stopped'       => esc_html__( 'Timer: Stopped',    'bbpress' ),
				'timer_waiting'       => esc_html__( 'Timer: Waiting...', 'bbpress' ),
				'timer_counting'      => esc_html__( 'Timer: %s',         'bbpress' )
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

		// Maybe include last query
		$query = get_option( '_bbp_converter_query' );
		if ( ! empty( $query ) ) {
			$output = $output . '<span class="query">' . esc_attr( $query ) . '</span>';
		}

		// Maybe prepend the step
		$step = ! empty( $this->step )
			? sprintf( '<span class="step">%s:</span> ', $this->step )
			: '';

		// Output
		echo $step . $output;
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
			$this->maybe_update_options();
			$this->step  = 1;
			$this->start = 0;
		}
	}

	private function maybe_update_options() {

		// Get the default options & values
		$defaults = bbp_get_default_options();

		// Default options
		$options = array(

			// Step & Start
			'_bbp_converter_step'  => 1,
			'_bbp_converter_start' => 0,

			// Rows
			'_bbp_converter_rows' => ! empty( $_POST['_bbp_converter_rows'] )
				? (int) $_POST['_bbp_converter_rows']
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

			// Default
			$default = $defaults[ $key ];

			// Update or save
			! empty( $value ) && ( $default !== $value )
				? update_option( $key, $value )
				: delete_option( $key );
		}
	}

	/**
	 * Setup converter options
	 *
	 * @since 2.6.0 bbPress (r6460)
	 */
	private function setup_options() {

		// Get starting point & rows
		$this->step  = (int) get_option( '_bbp_converter_step',  1   );
		$this->min   = (int) get_option( '_bbp_converter_start', 0   );
		$this->rows  = (int) get_option( '_bbp_converter_rows',  100 );

		// Get boundaries
		$this->max   = ( $this->min + $this->rows ) - 1;
		$this->start = $this->min;

		// Look for platform
		$platform = get_option( '_bbp_converter_platform' );

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

			// Clean all tables.
			case 1 :
				$this->step_sync_table();
				break;

			// Convert users.
			case 2 :
				$this->step_users();
				break;

			// Clean passwords.
			case 3 :
				$this->step_passwords();
				break;

			// Convert forums.
			case 4 :
				$this->step_forums();
				break;

			// Convert forum parents.
			case 5 :
				$this->step_forum_hierarchy();
				break;

			// Convert forum subscriptions.
			case 6 :
				$this->step_forum_subscriptions();
				break;

			// Convert topics.
			case 7 :
				$this->step_topics();
				break;

			// Convert topic authors.
			case 8 :
				$this->step_topics_authors();
				break;

			// Sticky topics.
			case 9 :
				$this->step_stickies();
				break;

			// Stick to front topics (Super Sicky).
			case 10 :
				$this->step_super_stickies();
				break;

			// Closed topics.
			case 11 :
				$this->step_closed_topics();
				break;

			// Convert topic tags.
			case 12 :
				$this->step_topic_tags();
				break;

			// Convert topic subscriptions.
			case 13 :
				$this->step_topic_subscriptions();
				break;

			// Convert topic favorites.
			case 14 :
				$this->step_topic_favorites();
				break;

			// Convert replies.
			case 15 :
				$this->step_replies();
				break;

			// Convert reply authors.
			case 16 :
				$this->step_reply_authors();
				break;

			// Convert threaded reply hierarchy.
			case 17 :
				$this->step_reply_hierarchy();
				break;

			// Done
			default :
				$this->step_done();
				break;
		}
	}

	/** Steps *****************************************************************/

	/**
	 * Clean the sync table
	 *
	 * @since 2.6.0 bbPress (r6513)
	 */
	private function step_sync_table() {
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
	}

	/**
	 * Maybe convert forums
	 *
	 * @since 2.6.0 bbPress (r6513)
	 */
	private function step_forums() {
		if ( $this->converter->convert_forums( $this->start ) ) {
			$this->bump_step();

			if ( empty( $this->start ) ) {
				$this->converter_output( esc_html__( 'No forums to import', 'bbpress' ) );
			}
		} else {
			$this->bump_start();
			$this->converter_output( sprintf( esc_html__( 'Converting forums (%1$s - %2$s)', 'bbpress' ), $this->min, $this->max ) );
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

			if ( empty( $this->start ) ) {
				$this->converter_output( esc_html__( 'No forum parents to import', 'bbpress' ) );
			}
		} else {
			$this->bump_start();
			$this->converter_output( sprintf( esc_html__( 'Calculating forum hierarchy (%1$s - %2$s)', 'bbpress' ), $this->min, $this->max ) );
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

			if ( empty( $this->start ) ) {
				$this->converter_output( esc_html__( 'No forum subscriptions to import', 'bbpress' ) );
			}
		} else {
			$this->bump_start();
			$this->converter_output( sprintf( esc_html__( 'Converting forum subscriptions (%1$s - %2$s)', 'bbpress' ), $this->min, $this->max ) );
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

			if ( empty( $this->start ) ) {
				$this->converter_output( esc_html__( 'No topics to import', 'bbpress' ) );
			}
		} else {
			$this->bump_start();
			$this->converter_output( sprintf( esc_html__( 'Converting topics (%1$s - %2$s)', 'bbpress' ), $this->min, $this->max ) );
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

			if ( empty( $this->start ) ) {
				$this->converter_output( esc_html__( 'No anonymous topic authors to import', 'bbpress' ) );
			}
		} else {
			$this->bump_start();
			$this->converter_output( sprintf( esc_html__( 'Converting anonymous topic authors (%1$s - %2$s)', 'bbpress' ), $this->min, $this->max ) );
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

			if ( empty( $this->start ) ) {
				$this->converter_output( esc_html__( 'No stickies to stick', 'bbpress' ) );
			}
		} else {
			$this->bump_start();
			$this->converter_output( sprintf( esc_html__( 'Calculating topic stickies (%1$s - %2$s)', 'bbpress' ), $this->min, $this->max ) );
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

			if ( empty( $this->start ) ) {
				$this->converter_output( esc_html__( 'No super stickies to stick', 'bbpress' ) );
			}
		} else {
			$this->bump_start();
			$this->converter_output( sprintf( esc_html__( 'Calculating topic super stickies (%1$s - %2$s)', 'bbpress' ), $this->min, $this->max ) );
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

			if ( empty( $this->start ) ) {
				$this->converter_output( esc_html__( 'No closed topics to close', 'bbpress' ) );
			}
		} else {
			$this->bump_start();
			$this->converter_output( sprintf( esc_html__( 'Calculating closed topics (%1$s - %2$s)', 'bbpress' ), $this->min, $this->max ) );
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

			if ( empty( $this->start ) ) {
				$this->converter_output( esc_html__( 'No topic tags to import', 'bbpress' ) );
			}
		} else {
			$this->bump_start();
			$this->converter_output( sprintf( esc_html__( 'Converting topic tags (%1$s - %2$s)', 'bbpress' ), $this->min, $this->max ) );
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

			if ( empty( $this->start ) ) {
				$this->converter_output( esc_html__( 'No topic subscriptions to import', 'bbpress' ) );
			}
		} else {
			$this->bump_start();
			$this->converter_output( sprintf( esc_html__( 'Converting topic subscriptions (%1$s - %2$s)', 'bbpress' ), $this->min, $this->max ) );
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

			if ( empty( $this->start ) ) {
				$this->converter_output( esc_html__( 'No favorites to import', 'bbpress' ) );
			}
		} else {
			$this->bump_start();
			$this->converter_output( sprintf( esc_html__( 'Converting favorites (%1$s - %2$s)', 'bbpress' ), $this->min, $this->max ) );
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

			if ( empty( $this->start ) ) {
				$this->converter_output( esc_html__( 'No replies to import', 'bbpress' ) );
			}
		} else {
			$this->bump_start();
			$this->converter_output( sprintf( esc_html__( 'Converting replies (%1$s - %2$s)', 'bbpress' ), $this->min, $this->max ) );
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

			if ( empty( $this->start ) ) {
				$this->converter_output( esc_html__( 'No anonymous reply authors to import', 'bbpress' ) );
			}
		} else {
			$this->bump_start();
			$this->converter_output( sprintf( esc_html__( 'Converting anonymous reply authors (%1$s - %2$s)', 'bbpress' ), $this->min, $this->max ) );
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

			if ( empty( $this->start ) ) {
				$this->converter_output( esc_html__( 'No threaded replies to import', 'bbpress' ) );
			}
		} else {
			$this->bump_start();
			$this->converter_output( sprintf( esc_html__( 'Calculating threaded replies parents (%1$s - %2$s)', 'bbpress' ), $this->min, $this->max ) );
		}
	}

	/**
	 * Done!
	 *
	 * @since 2.6.0 bbPress (r6513)
	 */
	private function step_done() {
		$this->reset();
		$this->converter_output( esc_html__( 'Import Finished', 'bbpress' ) );
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
