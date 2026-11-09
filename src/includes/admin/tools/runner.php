<?php

/**
 * bbPress Admin Repair and Upgrade Runner.
 *
 * @package bbPress
 * @subpackage Administration
 */

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

/**
 * Return whether an admin repair tool should flush caches when it runs.
 *
 * @since 2.7.0 bbPress (r8252)
 *
 * @param array $tool Repair tool registration.
 * @return bool
 */
function bbp_admin_repair_tool_flushes_cache( $tool = array() ) {
	return ! array_key_exists( 'flush_cache', $tool ) || ! empty( $tool['flush_cache'] );
}

/**
 * Acquire a short-lived lock for one repair tool callback.
 *
 * @since 2.7.0 bbPress (r8252)
 *
 * @param string $tool_id Repair tool ID.
 * @return array|false Lock owner data, or false when already locked.
 */
function bbp_admin_repair_tool_lock( $tool_id = '' ) {
	$tool_id = sanitize_key( $tool_id );
	return empty( $tool_id ) ? false : bbp_acquire_option_lock( '_bbp_tool_lock_' . md5( $tool_id ) );
}

/**
 * Release a repair tool callback lock.
 *
 * @since 2.7.0 bbPress (r8252)
 *
 * @param array $lock Lock owner data.
 */
function bbp_admin_repair_tool_unlock( $lock = array() ) {
	bbp_release_option_lock( $lock );
}

/**
 * Acquire a run-state lock, allowing short queue or status updates to finish.
 *
 * @since 2.7.0 bbPress (r8252)
 *
 * @param string $type Repair tool type.
 * @return array|false Lock owner data, or false when still locked.
 */
function bbp_admin_repair_tool_lock_run_state( $type = '' ) {
	if ( ! in_array( $type, array( 'repair', 'upgrade' ), true ) ) {
		return false;
	}

	for ( $attempt = 0; $attempt < 6; $attempt++ ) {
		$lock = bbp_admin_repair_tool_lock( 'bbp-' . $type . '-tool-run-state' );
		if ( false !== $lock ) {
			return $lock;
		}

		if ( $attempt < 5 ) {
			usleep( 100000 );
		}
	}

	return false;
}

/**
 * Return the option name for a recoverable repair tool run.
 *
 * @since 2.7.0 bbPress (r8252)
 *
 * @param string $type Repair tool type.
 * @return string
 */
function bbp_admin_repair_tool_run_state_option_name( $type = '' ) {
	$type = sanitize_key( $type );

	return in_array( $type, array( 'repair', 'upgrade' ), true )
		? '_bbp_tools_run_' . $type
		: '';
}

/**
 * Return the option name for a recoverable repair tool run status.
 *
 * Retained for recognizing and cleaning up older development checkpoints.
 * Current queue and status changes share one owner-checked run-state record.
 *
 * @since 2.7.0 bbPress (r8252)
 *
 * @param string $type Repair tool type.
 * @return string
 */
function bbp_admin_repair_tool_run_status_option_name( $type = '' ) {
	$type = sanitize_key( $type );

	return in_array( $type, array( 'repair', 'upgrade' ), true )
		? '_bbp_tools_run_status_' . $type
		: '';
}

/**
 * Read and normalize the stored run, including older separate status records.
 *
 * The internal schema marker makes a unified write authoritative even if an
 * old companion option cannot be removed. Public callers receive only the
 * original seven run fields.
 *
 * @since 2.7.0 bbPress (r8315)
 * @access private
 *
 * @param string $type     Repair tool type.
 * @param bool   $filtered Whether to apply native option view filters.
 * @return array Read success and normalized internal state, or false when absent.
 */
function bbp_admin_repair_tool_read_run_state( $type = '', $filtered = false ) {
	$option = bbp_admin_repair_tool_run_state_option_name( $type );
	if ( empty( $option ) ) {
		return array( false, false );
	}
	list( $read, $state ) = bbp_admin_repair_get_option( $option, array(), $filtered );
	if ( ! $read || ! is_array( $state ) || empty( $state['id'] ) || empty( $state['tools'] ) ) {
		return array( $read, false );
	}
	$tools = array_values( bbp_get_unique_array_values( array_map( 'sanitize_key', (array) $state['tools'] ) ) );
	if ( empty( $tools ) ) {
		return array( true, false );
	}
	$schema  = isset( $state['schema'] ) && 1 === $state['schema'] ? 1 : 0;
	$updated = isset( $state['updated'] ) ? (int) $state['updated'] : 0;
	if ( $filtered && ! $schema ) {
		// View filters may return only public fields. Storage owns the format.
		list( $read, $stored ) = bbp_admin_repair_get_option( $option, array(), false );
		if ( ! $read ) {
			return array( false, false );
		}
		if ( is_array( $stored ) && isset( $stored['schema'], $stored['id'] ) && 1 === $stored['schema']
			&& hash_equals( sanitize_text_field( $stored['id'] ), sanitize_text_field( $state['id'] ) ) ) {
			$schema = 1;
		}
	}
	if ( ! $schema ) {
		list( $read, $legacy ) = bbp_admin_repair_get_option( bbp_admin_repair_tool_run_status_option_name( $type ), array(), $filtered );
		if ( ! $read ) {
			return array( false, false );
		}
		if ( is_array( $legacy ) && ! empty( $legacy['id'] ) && hash_equals( sanitize_text_field( $state['id'] ), sanitize_text_field( $legacy['id'] ) ) ) {
			$state['status'] = isset( $legacy['status'] ) ? $legacy['status'] : 'error';
			$updated         = max( $updated, isset( $legacy['updated'] ) ? (int) $legacy['updated'] : 0 );
		}
	}
	$status = isset( $state['status'] ) ? sanitize_key( $state['status'] ) : 'running';
	return array(
		true,
		array(
			'schema'  => $schema,
			'id'      => sanitize_text_field( $state['id'] ),
			'type'    => sanitize_key( $type ),
			'tools'   => $tools,
			'status'  => in_array( $status, array( 'running', 'paused', 'error' ), true ) ? $status : 'error',
			'user_id' => isset( $state['user_id'] ) ? (int) $state['user_id'] : 0,
			'created' => isset( $state['created'] ) ? (int) $state['created'] : 0,
			'updated' => $updated,
		)
	);
}

/**
 * Return a recoverable repair tool run.
 *
 * @since 2.7.0 bbPress (r8252)
 *
 * @param string $type Repair tool type.
 * @return array|false Run state, or false when no valid run exists.
 */
function bbp_admin_repair_tool_get_run_state( $type = '' ) {
	list( $read, $state ) = bbp_admin_repair_tool_read_run_state( $type, true );
	if ( is_array( $state ) ) {
		unset( $state['schema'] );
	}
	return $state;
}

/**
 * Check ownership and, optionally, the current tool of a normalized run.
 *
 * @since 2.7.0 bbPress (r8315)
 * @access private
 *
 * @param array|false $state   Normalized run state.
 * @param string      $run_id  Expected owner.
 * @param string      $tool_id Optional expected first tool.
 * @return bool
 */
function bbp_admin_repair_tool_owns_run( $state, $run_id, $tool_id = '' ) {
	return ! empty( $state ) && ! empty( $run_id ) && hash_equals( $state['id'], $run_id )
		&& ( '' === $tool_id || $tool_id === $state['tools'][0] );
}

/**
 * Check whether a normalized run was recently active.
 *
 * @since 2.7.0 bbPress (r8315)
 * @access private
 *
 * @param array|false $state Normalized run state.
 * @return bool
 */
function bbp_admin_repair_tool_run_is_active( $state ) {
	return ! empty( $state ) && 'running' === $state['status']
		&& $state['updated'] >= ( time() - ( 2 * MINUTE_IN_SECONDS ) );
}

/**
 * Return the shared AJAX error for an outdated run owner.
 *
 * @since 2.7.0 bbPress (r8315)
 * @access private
 *
 * @return array
 */
function bbp_admin_repair_tool_stale_run_error() {
	return array(
		'code'    => 'stale_run',
		'message' => esc_html__( 'This tool run is no longer current.', 'bbpress' )
	);
}

/**
 * Build a raw continuation URL for the selected tools and run owner.
 *
 * Escape this URL where it is printed, keeping JavaScript URLs unencoded.
 *
 * @since 2.7.0 bbPress (r8315)
 * @access private
 *
 * @param string $type   Repair tool type.
 * @param array  $tools  Ordered repair tool IDs.
 * @param string $run_id Run owner.
 * @return string
 */
function bbp_admin_repair_tool_continue_url( $type, $tools, $run_id ) {
	return bbp_get_admin_repair_tool_page_url(
		array(
			'page'          => 'bbp-' . $type,
			'action'        => 'run',
			'checked'       => $tools,
			'bbp-tools-run' => $run_id,
			'_wpnonce'      => wp_create_nonce( 'bbpress-do-counts' )
		)
	);
}

/**
 * Mutate only the requested fields after reading the current owner under lock.
 *
 * Queue progress with a null status preserves an earlier Pause. A status-only
 * update reads the latest queue instead of accepting a caller's older copy.
 * Mutations use persisted state, while public reads retain native option filters.
 * WordPress stores the one record; the shared tool writer verifies it.
 *
 * @since 2.7.0 bbPress (r8315)
 * @access private
 *
 * @param string      $type          Repair tool type.
 * @param array|null  $tools         Ordered IDs, or null for status-only changes.
 * @param string      $run_id        Current owner, or empty to create a run.
 * @param string|null $status        New status, or null to preserve it.
 * @param bool        $rotate_run_id Whether to replace the exact owner and queue.
 * @return array|false Saved public run state, or false on failure.
 */
function bbp_admin_repair_tool_update_run_state( $type = '', $tools = null, $run_id = '', $status = null, $rotate_run_id = false ) {
	$status_only = is_null( $tools );
	$option      = bbp_admin_repair_tool_run_state_option_name( $type );
	$run_id      = sanitize_text_field( $run_id );
	$status      = is_null( $status ) ? null : sanitize_key( $status );
	if ( ! $status_only ) {
		$tools = array_values( bbp_get_unique_array_values( array_map( 'sanitize_key', (array) $tools ) ) );
	}
	if ( empty( $option ) || array() === $tools || ( $rotate_run_id && empty( $run_id ) )
		|| ( ! is_null( $status ) && ! in_array( $status, array( 'running', 'paused', 'error' ), true ) ) ) {
		return false;
	}
	if ( $status_only && ( empty( $run_id ) || is_null( $status ) || $rotate_run_id ) ) {
		return false;
	}
	$lock = bbp_admin_repair_tool_lock_run_state( $type );
	if ( false === $lock ) {
		return false;
	}
	try {
		list( $read, $existing ) = bbp_admin_repair_tool_read_run_state( $type );
		if ( ! $read ) {
			return false;
		}
		if ( empty( $run_id ) ) {
			if ( $existing ) {
				return false;
			}
			$run_id = wp_generate_uuid4();
		} elseif ( ! bbp_admin_repair_tool_owns_run( $existing, $run_id ) ) {
			return false;
		} elseif ( $rotate_run_id ) {
			if ( $existing['tools'] !== $tools ) {
				return false;
			}
			$run_id = wp_generate_uuid4();
		}
		$state = $existing ? $existing : array(
			'schema'  => 0,
			'id'      => $run_id,
			'type'    => sanitize_key( $type ),
			'tools'   => array(),
			'status'  => 'running',
			'user_id' => 0,
			'created' => 0,
			'updated' => 0,
		);
		$state['schema']  = 1;
		$state['id']      = $run_id;
		$state['updated'] = time();
		if ( ! $status_only ) {
			$state['tools']   = $tools;
			$state['user_id'] = ! empty( $state['user_id'] ) ? $state['user_id'] : get_current_user_id();
			$state['created'] = ! empty( $state['created'] ) ? $state['created'] : $state['updated'];
		}
		if ( ! is_null( $status ) ) {
			$state['status'] = $status;
		}
		if ( ! bbp_admin_repair_tool_save_option( $option, $state ) ) {
			return false;
		}
		if ( empty( $existing['schema'] ) ) {
			// Best-effort legacy cleanup. The verified schema marker takes precedence.
			delete_option( bbp_admin_repair_tool_run_status_option_name( $type ) );
		}
		unset( $state['schema'] );
		return $state;
	} finally {
		bbp_admin_repair_tool_unlock( $lock );
	}
}

/**
 * Save a recoverable repair tool run.
 *
 * @since 2.7.0 bbPress (r8252)
 *
 * @param string      $type          Repair tool type.
 * @param array       $tools         Ordered repair tool IDs.
 * @param string      $run_id        Existing run ID, or empty to start a new run.
 * @param string|null $status        running|paused|error, or null to preserve status.
 * @param bool        $rotate_run_id Whether to replace the exact owner and queue.
 * @return array|false Saved run state, or false on invalid input.
 */
function bbp_admin_repair_tool_save_run_state( $type = '', $tools = array(), $run_id = '', $status = 'running', $rotate_run_id = false ) {
	return bbp_admin_repair_tool_update_run_state( $type, (array) $tools, $run_id, $status, $rotate_run_id );
}

/**
 * Rotate ownership of the exact saved queue before resuming it.
 *
 * @since 2.7.0 bbPress (r8252)
 *
 * @param string $type   Repair tool type.
 * @param array  $tools  Expected ordered repair tool IDs.
 * @param string $run_id Current run ID.
 * @return array|false Resumed run state, or false on a mismatch.
 */
function bbp_admin_repair_tool_rotate_run_state( $type = '', $tools = array(), $run_id = '' ) {
	return bbp_admin_repair_tool_save_run_state( $type, $tools, $run_id, 'running', true );
}

/**
 * Update the status of a recoverable repair tool run.
 *
 * @since 2.7.0 bbPress (r8252)
 *
 * @param string $type   Repair tool type.
 * @param string $run_id Run ID.
 * @param string $status running|paused|error.
 * @return array|false Updated run state, or false on a mismatch.
 */
function bbp_admin_repair_tool_update_run_status( $type = '', $run_id = '', $status = 'running' ) {
	return bbp_admin_repair_tool_update_run_state( $type, null, $run_id, $status );
}

/**
 * Delete a recoverable repair tool run.
 *
 * @since 2.7.0 bbPress (r8252)
 *
 * @param string $type   Repair tool type.
 * @param string $run_id Optional run ID ownership check.
 * @return bool
 */
function bbp_admin_repair_tool_delete_run_state( $type = '', $run_id = '' ) {
	$option = bbp_admin_repair_tool_run_state_option_name( $type );
	$run_id = sanitize_text_field( $run_id );
	if ( empty( $option ) ) {
		return false;
	}
	$lock = bbp_admin_repair_tool_lock_run_state( $type );
	if ( false === $lock ) {
		return false;
	}
	try {
		list( $read, $state ) = bbp_admin_repair_tool_read_run_state( $type );
		if ( ! $read || ! $state || ( ! empty( $run_id ) && ! hash_equals( $state['id'], $run_id ) ) ) {
			return false;
		}
		$deleted = bbp_admin_repair_tool_save_option( $option, null, true );
		if ( $deleted ) {
			delete_option( bbp_admin_repair_tool_run_status_option_name( $type ) );
		}
		return $deleted;
	} finally {
		bbp_admin_repair_tool_unlock( $lock );
	}
}

/**
 * Enqueue the browser runner for an incomplete repair tool batch.
 *
 * @since 2.7.0 bbPress (r8252)
 *
 * @param string $type   Repair tool type.
 * @param array  $tools  Ordered repair tool IDs.
 * @param string $run_id Existing run ID, or empty to start a new run.
 * @return array|false Saved run state, or false on invalid input.
 */
function bbp_admin_repair_tool_enqueue_runner( $type = '', $tools = array(), $run_id = '' ) {
	$type  = sanitize_key( $type );
	$tools = array_values( array_unique( array_map( 'sanitize_key', (array) $tools ) ) );

	if ( ! in_array( $type, array( 'repair', 'upgrade' ), true ) || empty( $tools ) ) {
		return false;
	}

	$state = bbp_admin_repair_tool_save_run_state( $type, $tools, $run_id );
	if ( empty( $state ) ) {
		return false;
	}

	$run_id     = $state['id'];
	$return_url = bbp_get_admin_repair_tool_page_url( array( 'page' => 'bbp-' . $type ) );
	$current    = array_shift( $tools );

	if ( empty( $tools ) ) {
		$complete_url = add_query_arg(
			array(
				'bbp-tools-complete' => $current,
				'_wpnonce'           => wp_create_nonce( 'bbpress-tools-complete' )
			),
			$return_url
		);
	} else {
		$complete_url = bbp_admin_repair_tool_continue_url( $type, $tools, $run_id );
	}

	wp_enqueue_script( 'bbp-admin-tools-js' );
	wp_localize_script(
		'bbp-admin-tools-js',
		'bbpToolsRunner',
		array(
			'url'         => admin_url( 'admin-ajax.php' ),
			'nonce'       => wp_create_nonce( 'bbpress-tools-process' ),
			'statusNonce' => wp_create_nonce( 'bbpress-tools-run-status' ),
			'type'        => $type,
			'tools'       => array( $current ),
			'run'         => $run_id,
			'returnUrl'   => $return_url,
			'completeUrl' => $complete_url,
			'pause'       => __( 'Pause', 'bbpress' ),
			'resume'      => __( 'Resume', 'bbpress' ),
			'running'     => __( 'Running...', 'bbpress' ),
			'paused'      => __( 'Paused.', 'bbpress' ),
			'error'       => __( 'The tool could not continue. Resume to try this batch again.', 'bbpress' )
		)
	);

	return $state;
}

/**
 * Persist the paused state of a browser repair tool runner.
 *
 * @since 2.7.0 bbPress (r8252)
 */
function bbp_admin_repair_tool_run_status_ajax_handler() {
	if ( ! bbp_is_post_request() ) {
		wp_send_json_error( array( 'message' => esc_html__( 'Invalid request method.', 'bbpress' ) ), 405 );
	}

	$type = isset( $_POST['type'] )
		? sanitize_key( wp_unslash( $_POST['type'] ) )
		: '';

	if ( ! in_array( $type, array( 'repair', 'upgrade' ), true ) || ! current_user_can( 'bbp_tools_' . $type . '_page' ) ) {
		wp_send_json_error( array( 'message' => esc_html__( 'You are not allowed to update this tool run.', 'bbpress' ) ), 403 );
	}

	$nonce = isset( $_POST['nonce'] )
		? sanitize_text_field( wp_unslash( $_POST['nonce'] ) )
		: '';

	if ( ! wp_verify_nonce( $nonce, 'bbpress-tools-run-status' ) ) {
		wp_send_json_error( array( 'message' => esc_html__( 'The request could not be verified.', 'bbpress' ) ), 403 );
	}

	$run_id = isset( $_POST['run'] )
		? sanitize_text_field( wp_unslash( $_POST['run'] ) )
		: '';
	$status = isset( $_POST['status'] )
		? sanitize_key( wp_unslash( $_POST['status'] ) )
		: '';

	if ( ! in_array( $status, array( 'running', 'paused' ), true ) ) {
		wp_send_json_error( array( 'message' => esc_html__( 'Invalid tool run status.', 'bbpress' ) ), 400 );
	}

	if ( ! bbp_admin_repair_tool_update_run_status( $type, $run_id, $status ) ) {
		$state = bbp_admin_repair_tool_get_run_state( $type );
		if ( bbp_admin_repair_tool_owns_run( $state, $run_id ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'The tool run status could not be saved. Try again shortly.', 'bbpress' ) ), 503 );
		}

		wp_send_json_error(
			bbp_admin_repair_tool_stale_run_error(),
			409
		);
	}

	wp_send_json_success();
}

/**
 * Add completion or recovery feedback on a repair or upgrade tools page.
 *
 * @since 2.7.0 bbPress (r8252)
 */
function bbp_admin_tools_repair_upgrade_notices() {
	$type = bbp_get_admin_repair_tool_page_id();

	if ( ! in_array( $type, array( 'repair', 'upgrade' ), true ) || ! current_user_can( 'bbp_tools_' . $type . '_page' ) ) {
		return;
	}

	// A completion request and an unfinished-run notice are mutually exclusive.
	if ( ! empty( $_GET['bbp-tools-complete'] ) ) {
		if ( empty( $_GET['_wpnonce'] ) ) {
			return;
		}

		$tool_id = sanitize_key( wp_unslash( $_GET['bbp-tools-complete'] ) );
		$nonce   = sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) );

		if ( ! wp_verify_nonce( $nonce, 'bbpress-tools-complete' ) ) {
			return;
		}

		$tools = bbp_get_admin_repair_tools( $type );

		if ( isset( $tools[ $tool_id ] ) ) {
			$message = bbp_admin_repair_tool_notice_message(
				$tools[ $tool_id ],
				esc_html__( 'Completed successfully.', 'bbpress' )
			);
		} else {
			$message = esc_html__( 'The selected tools completed successfully.', 'bbpress' );
		}

		bbp_admin_tools_feedback( $message );
		return;
	}

	if ( ! bbp_is_get_request() || ! empty( $_GET['action'] ) || ! empty( $_GET['action2'] ) ) {
		return;
	}

	$state = bbp_admin_repair_tool_get_run_state( $type );
	if ( empty( $state ) ) {
		return;
	}

	$discard = isset( $_GET['bbp-tools-discard'] )
		? sanitize_text_field( wp_unslash( $_GET['bbp-tools-discard'] ) )
		: '';
	if ( ! empty( $discard ) && hash_equals( $state['id'], $discard ) && check_admin_referer( 'bbpress-tools-discard-' . $state['id'] ) ) {
		bbp_admin_repair_tool_delete_run_state( $type, $state['id'] );
		bbp_admin_tools_feedback( esc_html__( 'The previous forum tool run was discarded.', 'bbpress' ) );
		return;
	}

	$is_recent = bbp_admin_repair_tool_run_is_active( $state );
	if ( $is_recent ) {
		$message = esc_html__( 'A forum tool run appears to be in progress.', 'bbpress' );
	} else {
		$url = bbp_admin_repair_tool_continue_url( $type, $state['tools'], $state['id'] );
		$discard_url = wp_nonce_url(
			bbp_get_admin_repair_tool_page_url(
				array(
					'page'              => 'bbp-' . $type,
					'bbp-tools-discard' => $state['id']
				)
			),
			'bbpress-tools-discard-' . $state['id']
		);

		$message = '<strong>' . esc_html__( 'A previous forum tool run did not finish.', 'bbpress' ) . '</strong><br>';
		$message .= '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Resume', 'bbpress' ) . '</a> | ';
		$message .= '<a href="' . esc_url( $discard_url ) . '">' . esc_html__( 'Discard', 'bbpress' ) . '</a>';
	}

	bbp_admin_tools_feedback( $message, 'warning', false );
}

/**
 * Handle the processing and feedback of the admin tools page.
 *
 * @since 2.1.0 bbPress (r3884)
 *
 */
function bbp_admin_repair_handler() {

	// Bail if not an actionable request
	if ( ! bbp_is_get_request() ) {
		return;
	}

	// Filtering and searching must never run a selected tool action
	if ( ! empty( $_GET['filter_action'] ) || ! empty( $_GET['search_action'] ) ) {
		return;
	}

	// Bail on an ambiguous bulk-action request
	if ( ! empty( $_GET['doaction'] ) && ! empty( $_GET['doaction2'] ) ) {
		return;
	}

	// Get the action belonging to the clicked button, or a row-action URL
	if ( ! empty( $_GET['doaction2'] ) && ! empty( $_GET['action2'] ) ) {
		$action = sanitize_key( $_GET['action2'] );
	} elseif ( ! empty( $_GET['doaction'] ) && ! empty( $_GET['action'] ) ) {
		$action = sanitize_key( $_GET['action'] );
	} elseif ( empty( $_GET['doaction'] ) && empty( $_GET['doaction2'] ) && ! empty( $_GET['action'] ) ) {
		$action = sanitize_key( $_GET['action'] );
	} elseif ( empty( $_GET['doaction'] ) && empty( $_GET['doaction2'] ) && ! empty( $_GET['action2'] ) ) {
		$action = sanitize_key( $_GET['action2'] );
	} else {
		return;
	}

	// Bail if no tools were selected
	if ( empty( $_GET['checked'] ) ) {
		return;
	}

	check_admin_referer( 'bbpress-do-counts' );

	// Parse list of checked repairs
	$selected_tool_ids = array_values( array_map( 'sanitize_key', (array) $_GET['checked'] ) );

	// Get the list
	$registered_tools = bbp_get_admin_repair_tools();

	// Stores messages
	$messages           = array();
	$retry_tool_ids     = array();
	$flush_cache        = false;
	$runner_started     = false;
	$runner_save_failed = false;
	$lock_conflict      = false;
	$run_type           = '';
	$run_id             = isset( $_GET['bbp-tools-run'] )
		? sanitize_text_field( wp_unslash( $_GET['bbp-tools-run'] ) )
		: '';

	// Flush once around this group of selected Run callbacks
	if ( 'run' === $action ) {
		$types = array();
		foreach ( $selected_tool_ids as $tool_id ) {
			if ( isset( $registered_tools[ $tool_id ] ) && ! empty( $registered_tools[ $tool_id ]['type'] ) ) {
				$types[] = sanitize_key( $registered_tools[ $tool_id ]['type'] );
			}
		}
		$types    = array_values( array_unique( $types ) );
		$run_type = ! empty( $_GET['page'] )
			? bbp_get_admin_repair_tool_page_id()
			: ( isset( $types[0] ) ? $types[0] : '' );

		// A continuation must still own the exact saved queue
		if ( ! empty( $run_id ) ) {
			$state = bbp_admin_repair_tool_get_run_state( $run_type );
			if ( ! bbp_admin_repair_tool_owns_run( $state, $run_id ) || ( $state['tools'] !== $selected_tool_ids ) ) {
				bbp_admin_tools_feedback( esc_html__( 'This tool run is no longer current.', 'bbpress' ), 'error' );
				return;
			}

			foreach ( $selected_tool_ids as $tool_id ) {
				if ( ! isset( $registered_tools[ $tool_id ] ) || ( $run_type !== $registered_tools[ $tool_id ]['type'] ) ) {
					bbp_admin_repair_tool_update_run_status( $run_type, $run_id, 'error' );
					bbp_admin_tools_feedback( esc_html__( 'An unfinished forum tool is no longer available. Restore it or discard this run.', 'bbpress' ), 'error' );
					return;
				}
			}

			// Rotate ownership so older requests cannot continue the run
			$state = bbp_admin_repair_tool_rotate_run_state( $run_type, $selected_tool_ids, $run_id );
			if ( empty( $state ) ) {
				bbp_admin_tools_feedback( esc_html__( 'This tool run could not be resumed.', 'bbpress' ), 'error' );
				return;
			}

			$run_id = $state['id'];
		} else {
			// Do not let a new selection replace a recently active run
			$inactive_runs = array();
			foreach ( $types as $type ) {
				$state     = bbp_admin_repair_tool_get_run_state( $type );
				$is_active = bbp_admin_repair_tool_run_is_active( $state );
				if ( $is_active ) {
					bbp_admin_tools_feedback( esc_html__( 'A forum tool run appears to be in progress.', 'bbpress' ), 'error' );
					return;
				}

				if ( ! empty( $state ) ) {
					$inactive_runs[ $type ] = $state['id'];
				}
			}

			// Starting a new selection intentionally replaces inactive recovery state
			foreach ( $inactive_runs as $type => $inactive_run_id ) {
				bbp_admin_repair_tool_delete_run_state( $type, $inactive_run_id );
			}
		}

		foreach ( $selected_tool_ids as $tool_id ) {
			if ( isset( $registered_tools[ $tool_id ] ) && is_callable( bbp_get_admin_repair_tool_callback( $registered_tools[ $tool_id ], 'run' ) ) && bbp_admin_repair_tool_flushes_cache( $registered_tools[ $tool_id ] ) ) {
				$flush_cache = true;
				break;
			}
		}

		if ( $flush_cache ) {
			wp_cache_flush();
		}
	}

	// Run through checked repair tools
	if ( count( $selected_tool_ids ) ) {
		foreach ( $selected_tool_ids as $tool_index => $tool_id ) {
			if ( ! isset( $registered_tools[ $tool_id ] ) ) {
				continue;
			}

			$tool              = $registered_tools[ $tool_id ];
			$callbacks         = bbp_get_admin_repair_tool_callbacks( $tool );
			$tool['id']        = $tool_id;
			$tool['callback']  = isset( $callbacks['run'] ) ? $callbacks['run'] : false;
			$tool['callbacks'] = $callbacks;
			$callback          = isset( $callbacks[ $action ] ) ? $callbacks[ $action ] : false;
			unset( $tool['_registered_callback'] );

			if ( ! is_callable( $callback ) ) {
				/* translators: %1$s: Repair tool title; %2$s: Action label */
				$message = esc_html__( '%1$s does not support the %2$s action.', 'bbpress' );
				$messages[] = array( 1, sprintf( $message, $tool['title'], bbp_admin_repair_tool_action_label( $action ) ) );
				continue;
			}

			if ( 'run' === $action ) {
				$lock = bbp_admin_repair_tool_lock( $tool_id );
				if ( false === $lock ) {
					/* translators: %s: Repair tool title */
					$message = esc_html__( '%s is temporarily unavailable. Try again shortly.', 'bbpress' );
					$messages[]    = array( 1, sprintf( $message, $tool['title'] ) );
					$retry_tool_ids = array_values( array_unique( array_merge( $retry_tool_ids, array_slice( $selected_tool_ids, $tool_index ) ) ) );
					$lock_conflict  = true;
					break;
				}

				try {
					$result = call_user_func( $callback );
				} finally {
					bbp_admin_repair_tool_unlock( $lock );
				}
			} else {
				$result = call_user_func(
					$callback,
					array(
						'action' => $action,
						'tool'   => $tool
					)
				);
			}

			$normalized = bbp_admin_repair_tool_normalize_result( $result );
			if ( false === $normalized ) {
				/* translators: %1$s: Repair tool title; %2$s: Action label */
				$message = esc_html__( '%1$s did not return a valid result for the %2$s action.', 'bbpress' );
				$messages[] = array( 1, sprintf( $message, $tool['title'], bbp_admin_repair_tool_action_label( $action ) ) );
				if ( 'run' === $action ) {
					$retry_tool_ids[] = $tool_id;
				}

				continue;
			}

			if ( 'dry-run' === $action ) {
				$status_code = isset( $result[0] ) && is_numeric( $result[0] )
					? (int) $result[0]
					: 0;
				$dry_run_success       = $normalized['success'] && ( $status_code < 2 );
				$normalized['message'] = bbp_admin_repair_tool_dry_run_message(
					bbp_admin_repair_tool_result_detail( $normalized['message'] ),
					$dry_run_success
				);

				if ( ! $dry_run_success ) {
					$result['class'] = 'error';
				}
			}

			$result[1] = bbp_admin_repair_tool_notice_message( $tool, $normalized['message'] );
			$messages[] = $result;

			// Other actions never alter pending upgrades or start the browser runner
			if ( 'run' !== $action ) {
				continue;
			}

			// Failed tools stay pending, while the remaining selected tools continue
			if ( ! $normalized['success'] ) {
				$retry_tool_ids[] = $tool_id;
				continue;
			}

			// Successful legacy and final batches are complete
			if ( $normalized['complete'] ) {
				bbp_remove_pending_upgrade( $tool_id );
				continue;
			}

			// Continue this tool and the remaining compatible Run tools in the browser
			$queue = array();
			foreach ( array_slice( $selected_tool_ids, $tool_index ) as $queue_id ) {
				if ( isset( $registered_tools[ $queue_id ] ) && ( $tool['type'] === $registered_tools[ $queue_id ]['type'] ) && is_callable( bbp_get_admin_repair_tool_callback( $registered_tools[ $queue_id ], 'run' ) ) ) {
					$queue[] = $queue_id;
				}
			}

			$state = bbp_admin_repair_tool_enqueue_runner( $tool['type'], $queue, $run_id );
			if ( empty( $state ) ) {
				$runner_save_failed = true;
				$retry_tool_ids     = array_values( array_unique( array_merge( $retry_tool_ids, $queue ) ) );
				$messages[]         = array(
					1,
					esc_html__( 'The tool run could not be saved. Resume saved progress or run the selected tools again.', 'bbpress' ),
					'class' => 'error'
				);
				break;
			}

			$run_id         = $state['id'];
			$run_type       = $tool['type'];
			$runner_started = true;

			$continue_url = bbp_admin_repair_tool_continue_url( $tool['type'], $queue, $run_id );
			$continue = '<a href="' . esc_url( $continue_url ) . '">' . esc_html__( 'Continue', 'bbpress' ) . '</a>';
			$messages[ count( $messages ) - 1 ][1] .= ' <span class="bbp-tools-runner"></span> <span class="hide-if-js">' . $continue . '</span>';
			$messages[ count( $messages ) - 1 ]['class']       = 'updated';
			$messages[ count( $messages ) - 1 ]['dismissible'] = false;
			break;
		}
	}

	// Clear completed runs, or retain only tools that need another attempt
	if ( ( 'run' === $action ) && ! $runner_started ) {
		$recover_retry = ! empty( $run_id ) || $runner_save_failed || ( $lock_conflict && ( count( $selected_tool_ids ) > 1 ) );
		if ( ! empty( $retry_tool_ids ) && $recover_retry ) {
			$retry_type = isset( $registered_tools[ $retry_tool_ids[0] ]['type'] )
				? $registered_tools[ $retry_tool_ids[0] ]['type']
				: $run_type;
			$state = bbp_admin_repair_tool_save_run_state( $retry_type, $retry_tool_ids, $run_id, 'error' );
			if ( ! empty( $state ) ) {
				$run_id = $state['id'];
			}
		} elseif ( ! empty( $run_id ) ) {
			bbp_admin_repair_tool_delete_run_state( $run_type, $run_id );
		}
	}

	// Feedback
	if ( count( $messages ) ) {
		foreach ( $messages as $message ) {
			$class       = isset( $message['class'] ) ? $message['class'] : false;
			$dismissible = isset( $message['dismissible'] ) ? $message['dismissible'] : true;
			bbp_admin_tools_feedback( $message[1], $class, $dismissible );
		}
	}

	// Flush once after the selected Run callbacks and their latest checkpoint
	if ( $flush_cache ) {
		wp_cache_flush();
	}
}

/**
 * Process one repair or upgrade tool batch over AJAX.
 *
 * @since 2.7.0 bbPress (r8252)
 */
function bbp_admin_repair_ajax_handler() {
	if ( ! bbp_is_post_request() ) {
		wp_send_json_error( array( 'message' => esc_html__( 'Invalid request method.', 'bbpress' ) ), 405 );
	}

	$type = isset( $_POST['type'] )
		? sanitize_key( wp_unslash( $_POST['type'] ) )
		: '';

	if ( ! in_array( $type, array( 'repair', 'upgrade' ), true ) ) {
		wp_send_json_error( array( 'message' => esc_html__( 'Invalid tool type.', 'bbpress' ) ), 400 );
	}

	if ( ! current_user_can( 'bbp_tools_' . $type . '_page' ) ) {
		wp_send_json_error( array( 'message' => esc_html__( 'You are not allowed to run this tool.', 'bbpress' ) ), 403 );
	}

	$nonce = isset( $_POST['nonce'] )
		? sanitize_text_field( wp_unslash( $_POST['nonce'] ) )
		: '';

	if ( ! wp_verify_nonce( $nonce, 'bbpress-tools-process' ) ) {
		wp_send_json_error( array( 'message' => esc_html__( 'The request could not be verified.', 'bbpress' ) ), 403 );
	}

	$tool_id = isset( $_POST['tool'] )
		? sanitize_key( wp_unslash( $_POST['tool'] ) )
		: '';
	$run_id = isset( $_POST['run'] )
		? sanitize_text_field( wp_unslash( $_POST['run'] ) )
		: '';

	bbp_register_repair_tools();
	$registered_tools = bbp_get_admin_repair_tools( $type );

	$state = bbp_admin_repair_tool_get_run_state( $type );
	if ( ! bbp_admin_repair_tool_owns_run( $state, $run_id, $tool_id ) ) {
		wp_send_json_error(
			bbp_admin_repair_tool_stale_run_error(),
			409
		);
	}

	if ( ! isset( $registered_tools[ $tool_id ] ) ) {
		bbp_admin_repair_tool_update_run_status( $type, $run_id, 'error' );
		wp_send_json_error( array( 'message' => esc_html__( 'The requested tool is not available.', 'bbpress' ) ), 404 );
	}

	$tool     = $registered_tools[ $tool_id ];
	$callback = bbp_get_admin_repair_tool_callback( $tool, 'run' );

	if ( ! is_callable( $callback ) ) {
		bbp_admin_repair_tool_update_run_status( $type, $run_id, 'error' );
		wp_send_json_error( array( 'message' => esc_html__( 'The requested tool cannot be run.', 'bbpress' ) ), 400 );
	}

	$lock = bbp_admin_repair_tool_lock( $tool_id );
	if ( false === $lock ) {
		wp_send_json_error(
			array(
				'code'    => 'locked',
				'message' => esc_html__( 'This tool is temporarily unavailable. Waiting to try again.', 'bbpress' )
			),
			409
		);
	}

	$error        = false;
	$error_status = 500;
	$title        = '';
	$message      = '';

	// Recheck ownership and Pause after acquiring the callback lock
	$state = bbp_admin_repair_tool_get_run_state( $type );
	if ( ! bbp_admin_repair_tool_owns_run( $state, $run_id, $tool_id ) ) {
		$error = bbp_admin_repair_tool_stale_run_error();
	} elseif ( 'running' !== $state['status'] ) {
		$error = array(
			'code'    => 'paused',
			'message' => esc_html__( 'This tool run is paused. Resume to continue.', 'bbpress' )
		);
	}

	if ( false !== $error ) {
		bbp_admin_repair_tool_unlock( $lock );
		wp_send_json_error( $error, 409 );
	}

	try {
		$result     = call_user_func( $callback );
		$normalized = bbp_admin_repair_tool_normalize_result( $result );

		// Keep the callback lock through final cache flushing and the queue save
		if ( ( false !== $normalized ) && $normalized['success'] && $normalized['complete'] && bbp_admin_repair_tool_flushes_cache( $tool ) ) {
			wp_cache_flush();
		}

		if ( false === $normalized ) {
			bbp_admin_repair_tool_update_run_status( $type, $run_id, 'error' );
			$error = array( 'message' => esc_html__( 'The tool did not return a valid result.', 'bbpress' ) );
		} else {
			$title   = bbp_admin_repair_tool_result_message( $tool['title'] );
			$title   = wp_specialchars_decode( $title, ENT_QUOTES );
			$message = bbp_admin_repair_tool_result_detail( $normalized['message'] );
			$message = wp_specialchars_decode( $message, ENT_QUOTES );

			if ( ! $normalized['success'] ) {
				bbp_admin_repair_tool_update_run_status( $type, $run_id, 'error' );
				$error = array(
					'title'   => $title,
					'message' => $message
				);
			} else {
				list( $read, $state ) = bbp_admin_repair_tool_read_run_state( $type );
				$queue_saved = $read && bbp_admin_repair_tool_owns_run( $state, $run_id, $tool_id );

				if ( $queue_saved && $normalized['complete'] ) {
					array_shift( $state['tools'] );
					$queue_saved = empty( $state['tools'] )
						? bbp_admin_repair_tool_delete_run_state( $type, $run_id )
						: ( false !== bbp_admin_repair_tool_save_run_state( $type, $state['tools'], $run_id, null ) );
				} elseif ( $queue_saved ) {
					$queue_saved = false !== bbp_admin_repair_tool_save_run_state( $type, $state['tools'], $run_id, null );
				}

				if ( ! $queue_saved ) {
					$state = bbp_admin_repair_tool_get_run_state( $type );
					if ( bbp_admin_repair_tool_owns_run( $state, $run_id ) ) {
						$error = array( 'message' => esc_html__( 'Progress could not be saved. Resume to try this batch again.', 'bbpress' ) );
					} else {
						$error_status = 409;
						$error        = bbp_admin_repair_tool_stale_run_error();
					}
				} elseif ( $normalized['complete'] ) {
					bbp_remove_pending_upgrade( $tool_id );
				}
			}
		}
	} finally {
		bbp_admin_repair_tool_unlock( $lock );
	}

	if ( false !== $error ) {
		wp_send_json_error( $error, $error_status );
	}

	wp_send_json_success(
		array(
			'complete' => $normalized['complete'],
			'title'    => $title,
			'message'  => $message,
			'tool'     => $tool_id
		)
	);
}

/**
 * Run every batch of a repair or upgrade during a small-site update.
 *
 * Legacy callbacks complete after one call. Batched callbacks continue until
 * they report completion, fail, or reach the safety limit. An unfinished tool
 * remains available on the administration upgrade screen.
 *
 * @since 2.7.0 bbPress (r8252)
 *
 * @param string   $tool_id  Repair tool ID.
 * @param callable $callback Repair tool callback.
 * @return bool True when the tool completed, false when manual work remains.
 */
function bbp_admin_repair_tool_run_automatically( $tool_id = '', $callback = '' ) {
	$tool_id = sanitize_key( $tool_id );
	$started = microtime( true );

	/**
	 * Filters the maximum number of automatic batches for one small-site tool.
	 *
	 * @since 2.7.0 bbPress (r8252)
	 *
	 * @param int      $batch_limit Maximum number of batches.
	 * @param string   $tool_id     Repair tool ID.
	 * @param callable $callback    Repair tool callback.
	 */
	$batch_limit = (int) apply_filters( 'bbp_admin_repair_tool_automatic_batch_limit', 1000, $tool_id, $callback );

	/**
	 * Filters the time budget for one tool's automatic batches.
	 *
	 * A zero value disables the time budget. Individual callbacks are allowed to
	 * finish, and the budget is checked before starting the next batch.
	 *
	 * @since 2.7.0 bbPress (r8252)
	 *
	 * @param float    $seconds  Maximum elapsed seconds.
	 * @param string   $tool_id  Repair tool ID.
	 * @param callable $callback Repair tool callback.
	 */
	$time_limit = (float) apply_filters( 'bbp_admin_repair_tool_automatic_time_limit', 20, $tool_id, $callback );

	if ( empty( $tool_id ) ) {
		return false;
	}

	if ( ! is_callable( $callback ) || ( $batch_limit < 1 ) ) {
		bbp_add_pending_upgrade( $tool_id );
		return false;
	}

	$lock = bbp_admin_repair_tool_lock( $tool_id );
	if ( false === $lock ) {
		bbp_add_pending_upgrade( $tool_id );
		return false;
	}

	try {
		for ( $batch = 0; $batch < $batch_limit; $batch++ ) {
			if ( ( $batch > 0 ) && ( $time_limit > 0 ) && ( ( microtime( true ) - $started ) >= $time_limit ) ) {
				break;
			}

			$result     = call_user_func( $callback );
			$normalized = bbp_admin_repair_tool_normalize_result( $result );

			if ( ( false === $normalized ) || ! $normalized['success'] ) {
				bbp_add_pending_upgrade( $tool_id );
				return false;
			}

			if ( $normalized['complete'] ) {
				$pending = bbp_update_pending_upgrade( $tool_id, 'remove' );
				return $pending['saved'];
			}
		}
	} finally {
		bbp_admin_repair_tool_unlock( $lock );
	}

	bbp_add_pending_upgrade( $tool_id );
	return false;
}
