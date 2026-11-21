<?php

/**
 * bbPress Repair and Upgrade WP-CLI Commands.
 *
 * @package bbPress
 * @subpackage Administration
 */

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

/**
 * Manage the registered forum repair and upgrade tools.
 *
 * @since 2.7.0 bbPress (r8411)
 */
class BBP_Tools_CLI_Command {

	/**
	 * List registered repair and upgrade tools.
	 *
	 * ## OPTIONS
	 *
	 * [--type=<type>]
	 * : Limit results to repair or upgrade tools.
	 *
	 * [--format=<format>]
	 * : Output format: table, json, csv, yaml, or ids. Defaults to table.
	 *
	 * @subcommand list
	 *
	 * @since 2.7.0 bbPress (r8411)
	 *
	 * @param array<int, string>   $args       Positional arguments.
	 * @param array<string, mixed> $assoc_args Named arguments.
	 * @return void
	 */
	public function list_tools( $args, $assoc_args ) {
		$this->load_tools();
		$type   = $this->get_type( $assoc_args );
		$this->require_permission( $type );
		$format = isset( $assoc_args['format'] ) ? $assoc_args['format'] : 'table';
		$rows   = array();

		foreach ( bbp_get_admin_repair_tools( $type ) as $id => $tool ) {
			if ( ! current_user_can( 'bbp_tools_' . $tool['type'] . '_page' ) ) {
				continue;
			}

			$rows[] = array(
				'id'          => $id,
				'type'        => $tool['type'],
				'title'       => wp_specialchars_decode( bbp_admin_repair_tool_result_message( $tool['title'] ), ENT_QUOTES ),
				'overhead'    => $tool['overhead'],
				'dry_run'     => is_callable( bbp_get_admin_repair_tool_callback( $tool, 'dry-run' ) ) ? 'yes' : 'no'
			);
		}

		if ( 'ids' === $format ) {
			WP_CLI::line( implode( ' ', wp_list_pluck( $rows, 'id' ) ) );
			return;
		}

		\WP_CLI\Utils\format_items( $format, $rows, array( 'id', 'type', 'title', 'overhead', 'dry_run' ) );
	}

	/**
	 * Run one registered repair or upgrade tool in resumable batches.
	 *
	 * The command shares saved progress with the browser tools. Repeat it after
	 * interruption, or use --max-batches to stop deliberately. Use --url for one
	 * site on multisite and --user for a forum administrator or keymaster.
	 *
	 * ## OPTIONS
	 *
	 * <tool-id>
	 * : ID reported by `wp bbp tool list`.
	 *
	 * [--dry-run]
	 * : Preview the tool without changing forum data.
	 *
	 * [--batch-size=<number>]
	 * : Work items per batch, from 1 to 1000. Defaults to the registered tool limit.
	 *
	 * [--max-batches=<number>]
	 * : Stop after this many batches. Defaults to zero, which runs until complete.
	 *
	 * ## EXAMPLES
	 *
	 *     wp bbp tool run bbp-topic-replies --user=admin --dry-run
	 *     wp bbp tool run bbp-topic-replies --user=admin --batch-size=100 --max-batches=10
	 *
	 * @since 2.7.0 bbPress (r8411)
	 *
	 * @param array<int, string>   $args       Positional arguments.
	 * @param array<string, mixed> $assoc_args Named arguments.
	 * @return void
	 */
	public function run( $args, $assoc_args ) {
		$this->load_tools();
		$tool_id = $args[0];
		$tools   = bbp_get_admin_repair_tools();
		if ( sanitize_key( $tool_id ) !== $tool_id || ! isset( $tools[ $tool_id ] ) ) {
			WP_CLI::error( __( 'The requested forum tool is not registered.', 'bbpress' ) );
		}

		$tool   = $tools[ $tool_id ];
		$type   = $tool['type'];
		$action = \WP_CLI\Utils\get_flag_value( $assoc_args, 'dry-run', false ) ? 'dry-run' : 'run';
		if ( ! in_array( $type, array( 'repair', 'upgrade' ), true ) || ! current_user_can( 'bbp_tools_' . $type . '_page' ) ) {
			WP_CLI::error( __( 'You are not allowed to run this forum tool. Use --user with a permitted account.', 'bbpress' ) );
		}
		if ( ! is_callable( bbp_get_admin_repair_tool_callback( $tool, $action ) ) ) {
			WP_CLI::error( __( 'This forum tool does not support the requested action.', 'bbpress' ) );
		}

		$batch_size  = isset( $assoc_args['batch-size'] ) ? $assoc_args['batch-size'] : null;
		$max_batches = isset( $assoc_args['max-batches'] ) ? $assoc_args['max-batches'] : 0;
		if ( ( null !== $batch_size && ( ! ctype_digit( (string) $batch_size ) || (int) $batch_size < 1 || (int) $batch_size > 1000 ) )
			|| ! ctype_digit( (string) $max_batches ) ) {
			WP_CLI::error( __( 'Choose a batch size from 1 to 1000 and a whole-number batch limit.', 'bbpress' ) );
		}

		$flush_runtime = function_exists( 'wp_cache_flush_runtime' ) && function_exists( 'wp_cache_supports' ) && wp_cache_supports( 'flush_runtime' );
		if ( wp_using_ext_object_cache() && ! $flush_runtime && 1 !== (int) $max_batches ) {
			WP_CLI::error( __( 'This object cache cannot clear process-local data. Use --max-batches=1 and repeat the command to keep memory bounded.', 'bbpress' ) );
		}

		list( $read, $state ) = bbp_admin_repair_tool_read_run_state( $type, false, $action );
		if ( ! $read ) {
			WP_CLI::error( __( 'Saved forum tool progress could not be read.', 'bbpress' ) );
		}
		if ( $state ) {
			if ( array( $tool_id ) !== $state['tools'] || bbp_admin_repair_tool_run_is_active( $state ) || 'discarding' === $state['status'] ) {
				WP_CLI::error( __( 'Another forum tool run is active or unfinished. Check `wp bbp tool status` before continuing.', 'bbpress' ) );
			}
			$state = bbp_admin_repair_tool_rotate_run_state( $type, array( $tool_id ), $state['id'], $action );
		} else {
			$state = bbp_admin_repair_tool_save_run_state( $type, array( $tool_id ), '', 'running', false, $action );
		}
		if ( ! $state ) {
			WP_CLI::error( __( 'The forum tool run could not be started or resumed.', 'bbpress' ) );
		}

		$limit = null;
		if ( null !== $batch_size ) {
			$limit = static function ( $size, $id ) use ( $tool_id, $batch_size ) {
				return $tool_id === $id ? (int) $batch_size : $size;
			};
			add_filter( 'bbp_admin_repair_tool_batch_size', $limit, PHP_INT_MAX, 2 );
		}

		try {
			for ( $batch = 0; ; ++$batch ) {
				$response = bbp_admin_repair_tool_process_batch( $type, $action, $tool_id, $state['id'] );
				if ( ! $response['success'] ) {
					$message = isset( $response['data']['message'] ) ? $response['data']['message'] : __( 'The forum tool could not continue.', 'bbpress' );
					WP_CLI::error( $message );
				}

				$data = $response['data'];
				WP_CLI::log( $data['title'] . ': ' . $data['message'] );

				// Bound process-local cache growth without flushing shared caches.
				if ( $flush_runtime ) {
					wp_cache_flush_runtime();
				} elseif ( ! wp_using_ext_object_cache() ) {
					wp_cache_flush();
				}

				if ( $data['complete'] ) {
					WP_CLI::success( __( 'Forum tool complete.', 'bbpress' ) );
					break;
				}
				if ( $max_batches && ( $batch + 1 ) >= (int) $max_batches ) {
					if ( ! bbp_admin_repair_tool_update_run_status( $type, $state['id'], 'paused', $action ) ) {
						WP_CLI::error( __( 'The forum tool progress could not be paused.', 'bbpress' ) );
					}
					WP_CLI::success( __( 'Forum tool progress saved. Run the same command to resume.', 'bbpress' ) );
					break;
				}
			}
		} finally {
			if ( null !== $limit ) {
				remove_filter( 'bbp_admin_repair_tool_batch_size', $limit, PHP_INT_MAX );
			}
		}
	}

	/**
	 * Show saved browser or CLI run progress for this site.
	 *
	 * ## OPTIONS
	 *
	 * [--type=<type>]
	 * : Limit results to repair or upgrade tools.
	 *
	 * [--format=<format>]
	 * : Output format: table, json, csv, or yaml. Defaults to table.
	 *
	 * @since 2.7.0 bbPress (r8411)
	 *
	 * @param array<int, string>   $args       Positional arguments.
	 * @param array<string, mixed> $assoc_args Named arguments.
	 * @return void
	 */
	public function status( $args, $assoc_args ) {
		$this->load_tools();
		$type   = $this->get_type( $assoc_args );
		$this->require_permission( $type );
		$format = isset( $assoc_args['format'] ) ? $assoc_args['format'] : 'table';
		$types  = $type ? array( $type ) : array( 'repair', 'upgrade' );
		$rows   = array();

		foreach ( $types as $current_type ) {
			if ( ! current_user_can( 'bbp_tools_' . $current_type . '_page' ) ) {
				continue;
			}
			foreach ( array( 'run', 'dry-run' ) as $action ) {
				list( $read, $state ) = bbp_admin_repair_tool_read_run_state( $current_type, false, $action );
				if ( ! $read ) {
					WP_CLI::error( __( 'Saved forum tool progress could not be read.', 'bbpress' ) );
				}
				if ( $state ) {
					$rows[] = array(
						'type'    => $current_type,
						'action'  => $action,
						'tool'    => $state['tools'][0],
						'status'  => $state['status'],
						'updated' => gmdate( 'Y-m-d H:i:s', $state['updated'] )
					);
				}
			}
		}

		\WP_CLI\Utils\format_items( $format, $rows, array( 'type', 'action', 'tool', 'status', 'updated' ) );
	}

	/**
	 * Load the admin tool registry for a WP-CLI request.
	 *
	 * @since 2.7.0 bbPress (r8411)
	 * @access private
	 * @return void
	 */
	private function load_tools() {
		require_once bbpress()->includes_dir . 'admin/actions.php';
		bbp_setup_admin();
		bbp_register_repair_tools();
	}

	/**
	 * Validate an optional tool type.
	 *
	 * @since 2.7.0 bbPress (r8411)
	 * @access private
	 *
	 * @param array<string, mixed> $assoc_args Named arguments.
	 * @return string Repair, upgrade, or an empty string.
	 */
	private function get_type( $assoc_args ) {
		$type = isset( $assoc_args['type'] ) ? $assoc_args['type'] : '';
		if ( '' !== $type && ! in_array( $type, array( 'repair', 'upgrade' ), true ) ) {
			WP_CLI::error( __( 'Choose repair or upgrade for --type.', 'bbpress' ) );
		}
		return $type;
	}

	/**
	 * Require access to the requested tool type.
	 *
	 * @since 2.7.0 bbPress (r8411)
	 * @access private
	 *
	 * @param string $type Repair, upgrade, or an empty string for both.
	 * @return void
	 */
	private function require_permission( $type ) {
		$allowed = $type
			? current_user_can( 'bbp_tools_' . $type . '_page' )
			: ( current_user_can( 'bbp_tools_repair_page' ) || current_user_can( 'bbp_tools_upgrade_page' ) );
		if ( ! $allowed ) {
			WP_CLI::error( __( 'You are not allowed to access forum tools. Use --user with a permitted account.', 'bbpress' ) );
		}
	}
}
