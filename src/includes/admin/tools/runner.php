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
 * Acquire the lock for one action-specific run-state record.
 *
 * @since 2.7.0 bbPress (r8252)
 *
 * @param string $type   Repair tool type.
 * @param string $action Tool action.
 * @return array|false Lock owner data, or false when still locked.
 */
function bbp_admin_repair_tool_lock_run_state( $type = '', $action = 'run' ) {
	$action = sanitize_key( $action );
	if ( ! in_array( $type, array( 'repair', 'upgrade' ), true ) || ! in_array( $action, array( 'run', 'dry-run' ), true ) ) {
		return false;
	}
	$suffix = 'run' === $action ? '' : '-' . $action;

	for ( $attempt = 0; $attempt < 6; $attempt++ ) {
		$lock = bbp_admin_repair_tool_lock( 'bbp-' . $type . $suffix . '-tool-run-state' );
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
 * @param string $type   Repair tool type.
 * @param string $action Tool action.
 * @return string
 */
function bbp_admin_repair_tool_run_state_option_name( $type = '', $action = 'run' ) {
	$type   = sanitize_key( $type );
	$action = sanitize_key( $action );

	return in_array( $type, array( 'repair', 'upgrade' ), true ) && in_array( $action, array( 'run', 'dry-run' ), true )
		? '_bbp_tools_' . ( 'run' === $action ? 'run' : 'dry_run' ) . '_' . $type
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
 * @param string $type   Repair tool type.
 * @param string $action Tool action.
 * @return string
 */
function bbp_admin_repair_tool_run_status_option_name( $type = '', $action = 'run' ) {
	$type   = sanitize_key( $type );
	$action = sanitize_key( $action );

	return in_array( $type, array( 'repair', 'upgrade' ), true ) && in_array( $action, array( 'run', 'dry-run' ), true )
		? '_bbp_tools_' . ( 'run' === $action ? 'run_status' : 'dry_run_status' ) . '_' . $type
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
 * @param string $action   Tool action.
 * @return array Read success and normalized internal state, or false when absent.
 */
function bbp_admin_repair_tool_read_run_state( $type = '', $filtered = false, $action = 'run' ) {
	$action = sanitize_key( $action );
	$option = bbp_admin_repair_tool_run_state_option_name( $type, $action );
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
		list( $read, $legacy ) = bbp_admin_repair_get_option( bbp_admin_repair_tool_run_status_option_name( $type, $action ), array(), $filtered );
		if ( ! $read ) {
			return array( false, false );
		}
		if ( is_array( $legacy ) && ! empty( $legacy['id'] ) && hash_equals( sanitize_text_field( $state['id'] ), sanitize_text_field( $legacy['id'] ) ) ) {
			$state['status'] = isset( $legacy['status'] ) ? $legacy['status'] : 'error';
			$updated         = max( $updated, isset( $legacy['updated'] ) ? (int) $legacy['updated'] : 0 );
		}
	}
	$status     = isset( $state['status'] ) ? sanitize_key( $state['status'] ) : 'running';
	$started    = ! empty( $state['started'] );
	$preview_id = ( 'dry-run' === $action ) && ! empty( $state['preview_id'] )
		? sanitize_text_field( $state['preview_id'] )
		: sanitize_text_field( $state['id'] );

	return array(
		true,
		array(
			'schema'     => $schema,
			'id'         => sanitize_text_field( $state['id'] ),
			'preview_id' => $preview_id,
			'type'       => sanitize_key( $type ),
			'tools'      => $tools,
			'status'     => in_array( $status, array( 'running', 'paused', 'error' ), true ) ? $status : 'error',
			'started'    => $started,
			'user_id'    => isset( $state['user_id'] ) ? (int) $state['user_id'] : 0,
			'created'    => isset( $state['created'] ) ? (int) $state['created'] : 0,
			'updated'    => $updated,
		)
	);
}

/**
 * Return a recoverable repair tool run.
 *
 * @since 2.7.0 bbPress (r8252)
 *
 * @param string $type   Repair tool type.
 * @param string $action Tool action.
 * @return array|false Run state, or false when no valid run exists.
 */
function bbp_admin_repair_tool_get_run_state( $type = '', $action = 'run' ) {
	list( $read, $state ) = bbp_admin_repair_tool_read_run_state( $type, true, $action );
	if ( is_array( $state ) ) {
		unset( $state['schema'], $state['preview_id'], $state['started'] );
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
 * @param string $action Tool action.
 * @return string
 */
function bbp_admin_repair_tool_continue_url( $type, $tools, $run_id, $action = 'run' ) {
	return bbp_get_admin_repair_tool_page_url(
		array(
			'page'          => 'bbp-' . $type,
			'action'        => sanitize_key( $action ),
			'checked'       => $tools,
			'bbp-tools-run' => $run_id,
			'_wpnonce'      => wp_create_nonce( 'bbpress-do-counts' )
		)
	);
}

/**
 * Build a URL that discards one recoverable browser run.
 *
 * @since 2.7.0 bbPress (r8331)
 * @access private
 *
 * @param string $type   Repair tool type.
 * @param string $run_id Run owner.
 * @param string $action Tool action.
 * @return string Discard URL.
 */
function bbp_admin_repair_tool_discard_url( $type, $run_id, $action = 'run' ) {
	$action = sanitize_key( $action );
	$run_id = sanitize_text_field( $run_id );

	return add_query_arg(
		'_wpnonce',
		wp_create_nonce( bbp_admin_repair_tool_discard_nonce_action( $run_id, $action ) ),
		bbp_get_admin_repair_tool_page_url(
			array(
				'page'                     => 'bbp-' . sanitize_key( $type ),
				'bbp-tools-discard'        => $run_id,
				'bbp-tools-discard-action' => $action,
			)
		)
	);
}

/**
 * Return the nonce action for discarding one browser run.
 *
 * @since 2.7.0 bbPress (r8331)
 * @access private
 *
 * @param string $run_id Run owner.
 * @param string $action Tool action.
 * @return string Nonce action.
 */
function bbp_admin_repair_tool_discard_nonce_action( $run_id, $action = 'run' ) {
	$action = sanitize_key( $action );
	$run_id = sanitize_text_field( $run_id );

	return 'bbpress-tools-discard-' . ( 'run' === $action ? '' : $action . '-' ) . $run_id;
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
 * @param string      $action        Tool action.
 * @param bool|null   $started       Whether the current tool has processed a batch.
 * @return array|false Saved public run state, or false on failure.
 */
function bbp_admin_repair_tool_update_run_state( $type = '', $tools = null, $run_id = '', $status = null, $rotate_run_id = false, $action = 'run', $started = null ) {
	$status_only = is_null( $tools );
	$action      = sanitize_key( $action );
	$option      = bbp_admin_repair_tool_run_state_option_name( $type, $action );
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
	$lock = bbp_admin_repair_tool_lock_run_state( $type, $action );
	if ( false === $lock ) {
		return false;
	}
	try {
		list( $read, $existing ) = bbp_admin_repair_tool_read_run_state( $type, false, $action );
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
			'started' => false,
			'user_id' => 0,
			'created' => 0,
			'updated' => 0,
		);
		$state['schema']  = 1;
		$state['id']      = $run_id;
		$state['updated'] = time();
		if ( 'dry-run' === $action && empty( $state['preview_id'] ) ) {
			$state['preview_id'] = $run_id;
		}
		if ( ! $status_only ) {
			$state['tools']   = $tools;
			$state['user_id'] = ! empty( $state['user_id'] ) ? $state['user_id'] : get_current_user_id();
			$state['created'] = ! empty( $state['created'] ) ? $state['created'] : $state['updated'];
		}
		if ( ! is_null( $status ) ) {
			$state['status'] = $status;
		}
		if ( ! is_null( $started ) ) {
			$state['started'] = (bool) $started;
		}
		if ( ! bbp_admin_repair_tool_save_option( $option, $state ) ) {
			return false;
		}
		if ( empty( $existing['schema'] ) ) {
			// Best-effort legacy cleanup. The verified schema marker takes precedence.
			delete_option( bbp_admin_repair_tool_run_status_option_name( $type, $action ) );
		}
		unset( $state['schema'], $state['preview_id'], $state['started'] );
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
 * @param string      $action        Tool action.
 * @param bool|null   $started       Whether the current tool has processed a batch.
 * @return array|false Saved run state, or false on invalid input.
 */
function bbp_admin_repair_tool_save_run_state( $type = '', $tools = array(), $run_id = '', $status = 'running', $rotate_run_id = false, $action = 'run', $started = null ) {
	return bbp_admin_repair_tool_update_run_state( $type, (array) $tools, $run_id, $status, $rotate_run_id, $action, $started );
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
function bbp_admin_repair_tool_rotate_run_state( $type = '', $tools = array(), $run_id = '', $action = 'run' ) {
	return bbp_admin_repair_tool_save_run_state( $type, $tools, $run_id, 'running', true, $action );
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
function bbp_admin_repair_tool_update_run_status( $type = '', $run_id = '', $status = 'running', $action = 'run' ) {
	return bbp_admin_repair_tool_update_run_state( $type, null, $run_id, $status, false, $action );
}

/**
 * Delete a recoverable repair tool run.
 *
 * @since 2.7.0 bbPress (r8252)
 *
 * @param string $type           Repair tool type.
 * @param string $run_id         Optional run ID ownership check.
 * @param string $action         Tool action.
 * @param bool   $delete_preview Whether to delete Dry Run preview state.
 * @return bool
 */
function bbp_admin_repair_tool_delete_run_state( $type = '', $run_id = '', $action = 'run', $delete_preview = false ) {
	$action = sanitize_key( $action );
	$option = bbp_admin_repair_tool_run_state_option_name( $type, $action );
	$run_id = sanitize_text_field( $run_id );
	if ( empty( $option ) ) {
		return false;
	}
	$lock = bbp_admin_repair_tool_lock_run_state( $type, $action );
	if ( false === $lock ) {
		return false;
	}
	try {
		list( $read, $state ) = bbp_admin_repair_tool_read_run_state( $type, false, $action );
		if ( ! $read || ! $state || ( ! empty( $run_id ) && ! hash_equals( $state['id'], $run_id ) ) ) {
			return false;
		}
		if ( $delete_preview && 'dry-run' === $action && ! bbp_admin_repair_tool_delete_preview_state( $state['tools'], $state['preview_id'] ) ) {
			return false;
		}
		$deleted = bbp_admin_repair_tool_save_option( $option, null, true );
		if ( $deleted ) {
			delete_option( bbp_admin_repair_tool_run_status_option_name( $type, $action ) );
		}
		return $deleted;
	} finally {
		bbp_admin_repair_tool_unlock( $lock );
	}
}

/**
 * Discard one browser run and its private Dry Run preview state.
 *
 * @since 2.7.0 bbPress (r8331)
 * @access private
 *
 * @param string $type   Repair tool type.
 * @param string $run_id Run owner.
 * @param string $action Tool action.
 * @return bool Whether the owned run was discarded.
 */
function bbp_admin_repair_tool_discard_run( $type, $run_id, $action = 'run' ) {
	return bbp_admin_repair_tool_delete_run_state( $type, $run_id, $action, true );
}

/**
 * Delete state owned by one abandoned browser Dry Run.
 *
 * Preview namespaces include the run owner, so this cannot remove normal Run
 * checkpoints or another preview generation.
 *
 * @since 2.7.0 bbPress (r8331)
 * @access private
 *
 * @param array  $tools      Repair tool IDs.
 * @param string $preview_id Stable Dry Run preview namespace.
 * @return bool Whether every discovered preview option was removed.
 */
function bbp_admin_repair_tool_delete_preview_state( $tools, $preview_id ) {
	$db      = bbp_db();
	$success = true;

	bbp_admin_repair_tool_execution_context( 'dry-run', $preview_id );
	try {
		foreach ( (array) $tools as $tool_id ) {
			$prefix = bbp_admin_repair_tool_batch_option_name( $tool_id );
			$names  = $db->get_col( $db->prepare( "SELECT option_name FROM {$db->options} WHERE option_name LIKE %s", $db->esc_like( $prefix ) . '%' ) );
			if ( ! empty( $db->last_error ) ) {
				$success = false;
				continue;
			}
			foreach ( $names as $name ) {
				$success = bbp_admin_repair_tool_save_option( $name, null, true ) && $success;
			}
		}
	} finally {
		bbp_admin_repair_tool_execution_context( 'run', '' );
	}

	return $success;
}

/**
 * Enqueue the browser runner for an incomplete repair tool batch.
 *
 * @since 2.7.0 bbPress (r8252)
 *
 * @param string $type   Repair tool type.
 * @param array  $tools  Ordered repair tool IDs.
 * @param string $run_id Existing run ID, or empty to start a new run.
 * @param string $action Tool action.
 * @return array|false Saved run state, or false on invalid input.
 */
function bbp_admin_repair_tool_enqueue_runner( $type = '', $tools = array(), $run_id = '', $action = 'run' ) {
	$type   = sanitize_key( $type );
	$action = sanitize_key( $action );
	$tools  = array_values( bbp_get_unique_array_values( array_map( 'sanitize_key', (array) $tools ) ) );

	if ( ! in_array( $type, array( 'repair', 'upgrade' ), true ) || ! in_array( $action, array( 'run', 'dry-run' ), true ) || empty( $tools ) ) {
		return false;
	}

	$state = bbp_admin_repair_tool_save_run_state( $type, $tools, $run_id, 'running', false, $action );
	if ( empty( $state ) ) {
		return false;
	}

	$run_id       = $state['id'];
	$return_url   = bbp_get_admin_repair_tool_page_url( array( 'page' => 'bbp-' . $type ) );
	$complete     = end( $tools );
	$complete_url = add_query_arg(
		array(
			'bbp-tools-complete' => $complete,
			'_wpnonce'           => wp_create_nonce( 'bbpress-tools-complete' )
		),
		$return_url
	);

	wp_enqueue_script( 'bbp-admin-tools-js' );
	wp_localize_script(
		'bbp-admin-tools-js',
		'bbpToolsRunner',
		array(
			'url'         => admin_url( 'admin-ajax.php' ),
			'nonce'       => wp_create_nonce( 'bbpress-tools-process' ),
			'statusNonce' => wp_create_nonce( 'bbpress-tools-run-status' ),
			'type'        => $type,
			'action'      => $action,
			'tools'       => $tools,
			'run'         => $run_id,
			'returnUrl'   => $return_url,
			'completeUrl' => $complete_url,
			'stopUrl'     => bbp_admin_repair_tool_discard_url( $type, $run_id, $action ),
			'pause'       => __( 'Pause', 'bbpress' ),
			'resume'      => __( 'Resume', 'bbpress' ),
			'stop'        => __( 'Stop', 'bbpress' ),
			'stopping'    => __( 'Stopping', 'bbpress' ),
			'running'     => ( 'dry-run' === $action ) ? __( 'Checking', 'bbpress' ) : __( 'Running', 'bbpress' ),
			/* translators: 1: Current action, 2: Batch number */
			'progress'    => __( '%1$s batch %2$s', 'bbpress' ),
			'paused'      => __( 'Paused.', 'bbpress' ),
			/* translators: %s: Batch number */
			'pausedBatch' => __( 'Paused on batch %s.', 'bbpress' ),
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
	$action = isset( $_POST['toolAction'] )
		? sanitize_key( wp_unslash( $_POST['toolAction'] ) )
		: 'run';

	if ( ! in_array( $action, array( 'run', 'dry-run' ), true ) ) {
		wp_send_json_error( array( 'message' => esc_html__( 'Invalid tool action.', 'bbpress' ) ), 400 );
	}
	$status = isset( $_POST['status'] )
		? sanitize_key( wp_unslash( $_POST['status'] ) )
		: '';

	if ( ! in_array( $status, array( 'running', 'paused', 'discarded' ), true ) ) {
		wp_send_json_error( array( 'message' => esc_html__( 'Invalid tool run status.', 'bbpress' ) ), 400 );
	}

	if ( 'discarded' === $status ) {
		if ( bbp_admin_repair_tool_discard_run( $type, $run_id, $action ) ) {
			wp_send_json_success();
		}

		list( $read, $state ) = bbp_admin_repair_tool_read_run_state( $type, false, $action );
		if ( $read && bbp_admin_repair_tool_owns_run( $state, $run_id ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'The tool run could not be discarded. Try again shortly.', 'bbpress' ) ), 503 );
		}

		wp_send_json_error(
			bbp_admin_repair_tool_stale_run_error(),
			409
		);
	}

	if ( ! bbp_admin_repair_tool_update_run_status( $type, $run_id, $status, $action ) ) {
		$state = bbp_admin_repair_tool_get_run_state( $type, $action );
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

	$discard        = isset( $_GET['bbp-tools-discard'] )
		? sanitize_text_field( wp_unslash( $_GET['bbp-tools-discard'] ) )
		: '';
	$discard_action = isset( $_GET['bbp-tools-discard-action'] )
		? sanitize_key( wp_unslash( $_GET['bbp-tools-discard-action'] ) )
		: 'run';
	$recoveries     = array();
	foreach ( array( 'run', 'dry-run' ) as $action ) {
		list( $read, $state ) = bbp_admin_repair_tool_read_run_state( $type, false, $action );
		if ( ! $read || empty( $state ) ) {
			continue;
		}

		$discard_nonce = bbp_admin_repair_tool_discard_nonce_action( $state['id'], $action );
		if ( ! empty( $discard ) && $action === $discard_action && hash_equals( $state['id'], $discard ) && check_admin_referer( $discard_nonce ) ) {
			if ( bbp_admin_repair_tool_discard_run( $type, $state['id'], $action ) ) {
				bbp_admin_tools_feedback( esc_html__( 'The previous forum tool run was discarded.', 'bbpress' ) );
			} else {
				bbp_admin_tools_feedback( esc_html__( 'The previous forum tool run could not be discarded. Try again shortly.', 'bbpress' ), 'error' );
			}
			continue;
		}
		$is_recent = bbp_admin_repair_tool_run_is_active( $state );
		if ( $is_recent ) {
			$message = ( 'dry-run' === $action )
				? esc_html__( 'A Dry Run of the forum tools is still active.', 'bbpress' )
				: esc_html__( 'A forum tool run is still active.', 'bbpress' );
		} else {
			$url          = bbp_admin_repair_tool_continue_url( $type, $state['tools'], $state['id'], $action );
			$discard_url  = bbp_admin_repair_tool_discard_url( $type, $state['id'], $action );
			$recoveries[] = array(
				'url'    => $discard_url,
				'id'     => $state['id'],
				'action' => $action,
			);

			$message = ( 'dry-run' === $action )
				? '<strong>' . esc_html__( 'A previous Dry Run of the forum tools did not finish.', 'bbpress' ) . '</strong><br>'
				: '<strong>' . esc_html__( 'A previous forum tool run did not finish.', 'bbpress' ) . '</strong><br>';
			$message .= '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Resume', 'bbpress' ) . '</a> | ';
			$message .= '<a href="' . esc_url( $discard_url ) . '">' . esc_html__( 'Discard', 'bbpress' ) . '</a>';
		}

		bbp_admin_tools_feedback( $message, 'warning', false );
	}

	if ( $recoveries ) {
		wp_enqueue_script( 'bbp-admin-tools-js' );
		wp_localize_script(
			'bbp-admin-tools-js',
			'bbpToolsRecovery',
			array(
				'url'         => admin_url( 'admin-ajax.php' ),
				'statusNonce' => wp_create_nonce( 'bbpress-tools-run-status' ),
				'type'        => $type,
				'returnUrl'   => bbp_get_admin_repair_tool_page_url( array( 'page' => 'bbp-' . $type ) ),
				'runs'        => $recoveries,
				'discard'     => __( 'Discard', 'bbpress' ),
				'discarding'  => __( 'Discarding...', 'bbpress' ),
				'error'       => __( 'The tool run could not be discarded. Try again shortly.', 'bbpress' ),
			)
		);
	}
}

/**
 * Start or resume one browser-managed Run or Dry Run.
 *
 * Page rendering only validates and saves the queue. Tool callbacks execute
 * through the AJAX runner so a slow batch cannot block the tools screen.
 *
 * @since 2.7.0 bbPress (r8331)
 * @access private
 *
 * @param string $action            Tool action.
 * @param array  $selected_tool_ids Selected repair tool IDs.
 * @param array  $registered_tools  Registered repair tools.
 * @param string $run_id            Existing run owner, or empty for a new run.
 * @return void
 */
function bbp_admin_repair_tool_start_browser_run( $action, $selected_tool_ids, $registered_tools, $run_id = '' ) {
	$type = ! empty( $_GET['page'] ) ? bbp_get_admin_repair_tool_page_id() : '';
	if ( ! in_array( $type, array( 'repair', 'upgrade' ), true ) ) {
		foreach ( $selected_tool_ids as $tool_id ) {
			if ( isset( $registered_tools[ $tool_id ]['type'] ) ) {
				$type = sanitize_key( $registered_tools[ $tool_id ]['type'] );
				break;
			}
		}
	}
	if ( ! in_array( $type, array( 'repair', 'upgrade' ), true ) ) {
		bbp_admin_tools_feedback( esc_html__( 'The selected forum tools cannot run on this page.', 'bbpress' ), 'error' );
		return;
	}
	list( $read, $state ) = bbp_admin_repair_tool_read_run_state( $type, false, $action );
	if ( ! $read ) {
		bbp_admin_tools_feedback( esc_html__( 'Saved forum tool progress could not be read. Try again shortly.', 'bbpress' ), 'error' );
		return;
	}

	$queue = array();
	foreach ( $selected_tool_ids as $tool_id ) {
		if ( isset( $registered_tools[ $tool_id ] ) && $type === $registered_tools[ $tool_id ]['type']
			&& is_callable( bbp_get_admin_repair_tool_callback( $registered_tools[ $tool_id ], $action ) ) ) {
			$queue[] = $tool_id;
		}
	}
	$queue = array_values( bbp_get_unique_array_values( $queue ) );
	if ( empty( $queue ) ) {
		if ( ! empty( $run_id ) && bbp_admin_repair_tool_owns_run( $state, $run_id ) ) {
			bbp_admin_repair_tool_update_run_status( $type, $run_id, 'error', $action );
			$message = esc_html__( 'An unfinished forum tool is no longer available. Restore it or discard this run.', 'bbpress' );
		} else {
			$message = esc_html__( 'None of the selected forum tools support this action.', 'bbpress' );
		}
		bbp_admin_tools_feedback( $message, 'error' );
		return;
	}

	if ( ! empty( $run_id ) ) {
		if ( ! bbp_admin_repair_tool_owns_run( $state, $run_id ) || $queue !== $state['tools'] ) {
			bbp_admin_tools_feedback( esc_html__( 'This forum tool run is no longer current.', 'bbpress' ), 'error' );
			return;
		}

		$state = bbp_admin_repair_tool_rotate_run_state( $type, $queue, $run_id, $action );
		if ( empty( $state ) ) {
			bbp_admin_tools_feedback( esc_html__( 'This forum tool run could not be resumed.', 'bbpress' ), 'error' );
			return;
		}
		$run_id = $state['id'];
	} else {
		if ( bbp_admin_repair_tool_run_is_active( $state ) ) {
			$message = 'dry-run' === $action
				? esc_html__( 'A Dry Run of the forum tools is still active.', 'bbpress' )
				: esc_html__( 'A forum tool run is still active.', 'bbpress' );
			bbp_admin_tools_feedback( $message, 'error' );
			return;
		}

		if ( ! empty( $state ) ) {
			$message = 'dry-run' === $action
				? esc_html__( 'A previous Dry Run did not finish. Resume or discard it before starting another.', 'bbpress' )
				: esc_html__( 'A previous forum tool run did not finish. Resume or discard it before starting another.', 'bbpress' );
			bbp_admin_tools_feedback( $message, 'error' );
			return;
		}
	}

	$state = bbp_admin_repair_tool_enqueue_runner( $type, $queue, $run_id, $action );
	if ( empty( $state ) ) {
		bbp_admin_tools_feedback( esc_html__( 'The forum tool run could not be started. Try again shortly.', 'bbpress' ), 'error' );
		return;
	}

	$tool         = $registered_tools[ $queue[0] ];
	$continue_url = bbp_admin_repair_tool_continue_url( $type, $queue, $state['id'], $action );
	$continue     = '<a href="' . esc_url( $continue_url ) . '">' . esc_html__( 'Continue', 'bbpress' ) . '</a>';
	$message      = bbp_admin_repair_tool_notice_message( $tool );
	$message     .= ' <span class="bbp-tools-runner"></span> <span class="hide-if-js">' . $continue . '</span>';
	bbp_admin_tools_feedback( $message, 'updated', false );
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

	$run_id = isset( $_GET['bbp-tools-run'] )
		? sanitize_text_field( wp_unslash( $_GET['bbp-tools-run'] ) )
		: '';
	if ( in_array( $action, array( 'run', 'dry-run' ), true ) ) {
		bbp_admin_repair_tool_start_browser_run( $action, $selected_tool_ids, $registered_tools, $run_id );
		return;
	}

	$messages = array();
	foreach ( $selected_tool_ids as $tool_id ) {
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
			$message    = esc_html__( '%1$s does not support the %2$s action.', 'bbpress' );
			$messages[] = array( 1, sprintf( $message, $tool['title'], bbp_admin_repair_tool_action_label( $action ) ) );
			continue;
		}

		$result = call_user_func(
			$callback,
			array(
				'action' => $action,
				'tool'   => $tool,
			)
		);
		$normalized = bbp_admin_repair_tool_normalize_result( $result );
		if ( false === $normalized ) {
			/* translators: %1$s: Repair tool title; %2$s: Action label */
			$message    = esc_html__( '%1$s did not return a valid result for the %2$s action.', 'bbpress' );
			$messages[] = array( 1, sprintf( $message, $tool['title'], bbp_admin_repair_tool_action_label( $action ) ) );
			continue;
		}

		$result[1]  = bbp_admin_repair_tool_notice_message( $tool, $normalized['message'] );
		$messages[] = $result;
	}

	// Feedback
	if ( count( $messages ) ) {
		foreach ( $messages as $message ) {
			$class       = isset( $message['class'] ) ? $message['class'] : false;
			$dismissible = isset( $message['dismissible'] ) ? $message['dismissible'] : true;
			bbp_admin_tools_feedback( $message[1], $class, $dismissible );
		}
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
	$run_id  = isset( $_POST['run'] )
		? sanitize_text_field( wp_unslash( $_POST['run'] ) )
		: '';
	$action  = isset( $_POST['toolAction'] )
		? sanitize_key( wp_unslash( $_POST['toolAction'] ) )
		: 'run';

	if ( ! in_array( $action, array( 'run', 'dry-run' ), true ) ) {
		wp_send_json_error( array( 'message' => esc_html__( 'Invalid tool action.', 'bbpress' ) ), 400 );
	}

	bbp_register_repair_tools();
	$registered_tools = bbp_get_admin_repair_tools( $type );

	list( $read, $state ) = bbp_admin_repair_tool_read_run_state( $type, false, $action );
	if ( ! $read || ! bbp_admin_repair_tool_owns_run( $state, $run_id, $tool_id ) ) {
		wp_send_json_error(
			bbp_admin_repair_tool_stale_run_error(),
			409
		);
	}

	if ( ! isset( $registered_tools[ $tool_id ] ) ) {
		bbp_admin_repair_tool_update_run_status( $type, $run_id, 'error', $action );
		wp_send_json_error( array( 'message' => esc_html__( 'The requested tool is not available.', 'bbpress' ) ), 404 );
	}

	$tool              = $registered_tools[ $tool_id ];
	$callbacks         = bbp_get_admin_repair_tool_callbacks( $tool );
	$callback          = isset( $callbacks[ $action ] ) ? $callbacks[ $action ] : false;
	$tool['id']        = $tool_id;
	$tool['callback']  = isset( $callbacks['run'] ) ? $callbacks['run'] : false;
	$tool['callbacks'] = $callbacks;
	unset( $tool['_registered_callback'] );

	if ( ! is_callable( $callback ) ) {
		bbp_admin_repair_tool_update_run_status( $type, $run_id, 'error', $action );
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
	list( $read, $state ) = bbp_admin_repair_tool_read_run_state( $type, false, $action );
	if ( ! $read || ! bbp_admin_repair_tool_owns_run( $state, $run_id, $tool_id ) ) {
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
		$execution_id = 'dry-run' === $action ? $state['preview_id'] : $run_id;
		bbp_admin_repair_tool_execution_context( $action, $execution_id, $state['started'] );
		$result = 'run' === $action
			? call_user_func( $callback )
			: call_user_func(
				$callback,
				array(
					'action' => $action,
					'tool'   => $tool,
				)
			);
		$normalized = bbp_admin_repair_tool_normalize_result( $result );

		// Keep the callback lock through final cache flushing and the queue save
		if ( 'run' === $action && ( false !== $normalized ) && $normalized['success'] && $normalized['complete'] && bbp_admin_repair_tool_flushes_cache( $tool ) ) {
			wp_cache_flush();
		}

		if ( false === $normalized ) {
			bbp_admin_repair_tool_update_run_status( $type, $run_id, 'error', $action );
			$error = array( 'message' => esc_html__( 'The tool did not return a valid result.', 'bbpress' ) );
		} else {
			$title   = bbp_admin_repair_tool_result_message( $tool['title'] );
			$title   = wp_specialchars_decode( $title, ENT_QUOTES );
			$message = bbp_admin_repair_tool_result_detail( $normalized['message'] );
			$message = wp_specialchars_decode( $message, ENT_QUOTES );
			if ( 'dry-run' === $action ) {
				$status_code = isset( $result[0] ) && is_numeric( $result[0] ) ? (int) $result[0] : 0;
				$normalized['success'] = $normalized['success'] && ( $status_code < 2 );
				$message     = bbp_admin_repair_tool_dry_run_message(
					$message,
					$normalized['success'],
					$normalized['batches'],
					$normalized['complete']
				);
				$message = wp_specialchars_decode( $message, ENT_QUOTES );
			}

			if ( ! $normalized['success'] ) {
				bbp_admin_repair_tool_update_run_status( $type, $run_id, 'error', $action );
				$error = array(
					'title'   => $title,
					'message' => $message
				);
			} else {
				list( $read, $state ) = bbp_admin_repair_tool_read_run_state( $type, false, $action );
				$queue_saved = $read && bbp_admin_repair_tool_owns_run( $state, $run_id, $tool_id );

				if ( $queue_saved && $normalized['complete'] ) {
					array_shift( $state['tools'] );
					$queue_saved = empty( $state['tools'] )
						? bbp_admin_repair_tool_delete_run_state( $type, $run_id, $action )
						: ( false !== bbp_admin_repair_tool_save_run_state( $type, $state['tools'], $run_id, null, false, $action, false ) );
				} elseif ( $queue_saved ) {
					$queue_saved = false !== bbp_admin_repair_tool_save_run_state( $type, $state['tools'], $run_id, null, false, $action, true );
				}

				if ( ! $queue_saved ) {
					$state = bbp_admin_repair_tool_get_run_state( $type, $action );
					if ( bbp_admin_repair_tool_owns_run( $state, $run_id ) ) {
						$error = array( 'message' => esc_html__( 'Progress could not be saved. Resume to try this batch again.', 'bbpress' ) );
					} else {
						$error_status = 409;
						$error        = bbp_admin_repair_tool_stale_run_error();
					}
				} elseif ( $normalized['complete'] && 'run' === $action ) {
					bbp_remove_pending_upgrade( $tool_id );
				}
			}
		}
	} finally {
		bbp_admin_repair_tool_execution_context( 'run', '' );
		bbp_admin_repair_tool_unlock( $lock );
	}

	if ( false !== $error ) {
		wp_send_json_error( $error, $error_status );
	}

	wp_send_json_success(
		array(
			'complete' => $normalized['complete'],
			'batches'  => $normalized['batches'],
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
