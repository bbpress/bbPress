<?php

/**
 * bbPress Admin Tools Common.
 *
 * @package bbPress
 * @subpackage Administration
 */

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

/**
 * Return the current admin repair tool page.
 *
 * @since 2.6.0 bbPress (r6894)
 *
 * @return string
 */
function bbp_get_admin_repair_tool_page() {
	return sanitize_key( $_GET['page'] );
}

/**
 * Return the current admin repair tool page ID.
 *
 * @since 2.6.0 bbPress (r6894)
 *
 * @return string
 */
function bbp_get_admin_repair_tool_page_id() {

	// Get the page
	$page = bbp_get_admin_repair_tool_page();

	// Maybe trim prefix off of page
	if ( ! empty( $page ) && ( 0 === strpos( $page, 'bbp-' ) ) ) {
		$page = str_replace( 'bbp-', '', $page );
	} else {
		$page = '';
	}

	return $page;
}

/**
 * Return a URL to the repair tool page.
 *
 * @since 2.6.0 bbPress (r6894)
 *
 * @param array $args
 *
 * @return string
 */
function bbp_get_admin_repair_tool_page_url( $args = array() ) {

	// Parse arguments
	$r = wp_parse_args(
		$args,
		array(
			'page' => bbp_get_admin_repair_tool_page()
		)
	);

	return add_query_arg( $r, admin_url( 'tools.php' ) );
}

/**
 * Output the URL to run a specific repair tool.
 *
 * @since 2.6.0 bbPress (r5885)
 *
 * @param string $component
 */
function bbp_admin_repair_tool_run_url( $component = array() ) {
	echo esc_url( bbp_get_admin_repair_tool_run_url( $component ) );
}

/**
 * Return the URL to run a specific repair tool.
 *
 * @since 2.6.0 bbPress (r5885)
 *
 * @param string $component
 */
function bbp_get_admin_repair_tool_run_url( $component = array() ) {
	return bbp_get_admin_repair_tool_action_url( $component, 'run' );
}

/**
 * Output the URL for a specific repair tool action.
 *
 * @since 2.7.0 bbPress (r8218)
 *
 * @param array  $component Repair tool registration.
 * @param string $action    Repair tool action.
 */
function bbp_admin_repair_tool_action_url( $component = array(), $action = 'run' ) {
	echo esc_url( bbp_get_admin_repair_tool_action_url( $component, $action ) );
}

/**
 * Return the URL for a specific repair tool action.
 *
 * @since 2.7.0 bbPress (r8218)
 *
 * @param array  $component Repair tool registration.
 * @param string $action    Repair tool action.
 * @return string
 */
function bbp_get_admin_repair_tool_action_url( $component = array(), $action = 'run' ) {

	// Page
	$page = ( 'repair' === $component['type'] )
		? 'bbp-repair'
		: 'bbp-upgrade';

	// Arguments
	$args = array(
		'page'    => $page,
		'action'  => sanitize_key( $action ),
		'checked' => array( $component['id'] )
	);

	// Url
	$nonced = wp_nonce_url( bbp_get_admin_repair_tool_page_url( $args ), 'bbpress-do-counts' );

	/**
	 * Filters an admin repair tool action URL.
	 *
	 * @since 2.7.0 bbPress (r8218)
	 *
	 * @param string $nonced   Nonced action URL.
	 * @param array  $component Repair tool registration.
	 * @param string $action    Repair tool action.
	 */
	$url = apply_filters( 'bbp_get_admin_repair_tool_action_url', $nonced, $component, $action );

	// Preserve the original filter for Run actions
	if ( 'run' === $action ) {
		$url = apply_filters( 'bbp_get_admin_repair_tool_run_url', $url, $component );
	}

	return $url;
}

/**
 * Return the action-keyed callbacks for an admin repair tool.
 *
 * The legacy callback value is treated as the run callback when an explicit
 * action-keyed run callback is not available.
 *
 * @since 2.7.0 bbPress (r8218)
 *
 * @param array $tool Repair tool registration.
 * @return array
 */
function bbp_get_admin_repair_tool_callbacks( $tool = array() ) {
	$callbacks = ! empty( $tool['callbacks'] )
		? (array) $tool['callbacks']
		: array();
	$registered_callback = isset( $tool['_registered_callback'] )
		? $tool['_registered_callback']
		: false;
	$legacy_override = ( false !== $registered_callback )
		&& array_key_exists( 'callback', $tool )
		&& ( $registered_callback !== $tool['callback'] );
	$callbacks_override = ( false !== $registered_callback )
		&& array_key_exists( 'callbacks', $tool )
		&& ( ! array_key_exists( 'run', $callbacks ) || ( $registered_callback !== $callbacks['run'] ) );

	// A legacy override cannot safely inherit action-specific callbacks
	if ( $legacy_override && ! $callbacks_override ) {
		$callbacks = ! empty( $tool['callback'] )
			? array( 'run' => $tool['callback'] )
			: array();

	// Explicit action-keyed callbacks win when both public forms are changed
	} elseif ( empty( $callbacks['run'] ) && ! $callbacks_override && ! empty( $tool['callback'] ) ) {
		$callbacks['run'] = $tool['callback'];
	}

	$retval = array();
	foreach ( $callbacks as $action => $callback ) {
		$action = sanitize_key( $action );

		if ( ! empty( $action ) && ! empty( $callback ) ) {
			$retval[ $action ] = $callback;
		}
	}

	return $retval;
}

/**
 * Return the callback for a specific admin repair tool action.
 *
 * @since 2.7.0 bbPress (r8218)
 *
 * @param array  $tool   Repair tool registration.
 * @param string $action Repair tool action.
 * @return callable|false
 */
function bbp_get_admin_repair_tool_callback( $tool = array(), $action = 'run' ) {
	$callbacks = bbp_get_admin_repair_tool_callbacks( $tool );
	$action    = sanitize_key( $action );

	return isset( $callbacks[ $action ] )
		? $callbacks[ $action ]
		: false;
}

/**
 * Return the actions registered for one or more admin repair tools.
 *
 * @since 2.7.0 bbPress (r8218)
 *
 * @param array $tools Repair tool registrations.
 * @return array
 */
function bbp_get_admin_repair_tool_actions( $tools = array() ) {
	$actions = array();

	foreach ( $tools as $tool ) {
		$actions = array_merge( $actions, array_keys( bbp_get_admin_repair_tool_callbacks( $tool ) ) );
	}

	return array_values( array_unique( $actions ) );
}

/**
 * Return a human-readable admin repair tool action label.
 *
 * @since 2.7.0 bbPress (r8218)
 *
 * @param string $action Repair tool action.
 * @return string
 */
function bbp_admin_repair_tool_action_label( $action = '' ) {
	switch ( $action ) {
		case 'run':
			$label = esc_html__( 'Run', 'bbpress' );
			break;

		case 'dry-run':
			$label = esc_html__( 'Dry Run', 'bbpress' );
			break;

		default:
			$label = ucwords( str_replace( '-', ' ', sanitize_key( $action ) ) );
			break;
	}

	/**
	 * Filters a repair tool action label.
	 *
	 * @since 2.7.0 bbPress (r8218)
	 *
	 * @param string $label  Human-readable action label.
	 * @param string $action Repair tool action.
	 */
	return apply_filters( 'bbp_admin_repair_tool_action_label', $label, $action );
}

/**
 * Output bulk actions registered by the available admin repair tools.
 *
 * @since 2.7.0 bbPress (r8218)
 *
 * @param array $tools Repair tool registrations.
 */
function bbp_admin_repair_tool_bulk_actions( $tools = array() ) {
	foreach ( bbp_get_admin_repair_tool_actions( $tools ) as $action ) {
		printf(
			'<option value="%1$s" class="hide-if-no-js">%2$s</option>',
			esc_attr( $action ),
			esc_html( bbp_admin_repair_tool_action_label( $action ) )
		);
	}
}

/**
 * Output the actions registered by an admin repair tool.
 *
 * @since 2.7.0 bbPress (r8218)
 *
 * @param array $tool Repair tool registration.
 */
function bbp_admin_repair_tool_row_actions( $tool = array() ) {
	$links = array();

	foreach ( array_keys( bbp_get_admin_repair_tool_callbacks( $tool ) ) as $action ) {
		$label = bbp_admin_repair_tool_action_label( $action );

		switch ( $action ) {
			case 'run':
				/* translators: %s: Repair tool title */
				$aria_label = sprintf( esc_attr__( 'Run %s', 'bbpress' ), $tool['title'] );
				$link_id    = $tool['id'];
				break;

			case 'dry-run':
				/* translators: %s: Repair tool title */
				$aria_label = sprintf( esc_attr__( 'Dry Run %s', 'bbpress' ), $tool['title'] );
				$link_id    = $tool['id'] . '-dry-run';
				break;

			default:
				/* translators: %1$s: Action label; %2$s: Repair tool title */
				$aria_label = sprintf( esc_attr__( '%1$s %2$s', 'bbpress' ), $label, $tool['title'] );
				$link_id    = $tool['id'] . '-' . $action;
				break;
		}

		$links[] = sprintf(
			'<span class="%1$s"><a href="%2$s" id="%3$s" aria-label="%4$s">%5$s</a></span>',
			esc_attr( sanitize_html_class( $action ) ),
			esc_url( bbp_get_admin_repair_tool_action_url( $tool, $action ) ),
			esc_attr( $link_id ),
			esc_attr( $aria_label ),
			esc_html( $label )
		);
	}

	echo implode( ' | ', $links );
}

/**
 * Assemble the admin notices.
 *
 * @since 2.1.0 bbPress (r3884)
 *
 * @param string|WP_Error $message        A message to be displayed or {@link WP_Error}
 * @param string          $class          Optional. A class to be added to the message div
 * @param bool            $is_dismissible Optional. True to dismiss, false to persist
 *
 * @return string The message HTML
 */
function bbp_admin_tools_feedback( $message, $class = false, $is_dismissible = true ) {
	return bbp_admin()->add_notice( $message, $class, $is_dismissible );
}

/**
 * Normalize the result returned by a repair tool callback.
 *
 * Legacy callbacks are complete after one call. Batched callbacks opt in by
 * returning a false complete value until their final batch. Callbacks must
 * save their own progress, tolerate repeated batches, and invalidate the cache
 * entries they change between batches. Only an explicit false success value
 * marks a Run failure; legacy numeric status codes are not standardized.
 *
 * @since 2.7.0 bbPress (r8252)
 *
 * @param array $result Repair tool callback result.
 * @return array|false Normalized result, or false for an invalid result.
 */
function bbp_admin_repair_tool_normalize_result( $result = array() ) {
	if ( ! is_array( $result ) || ! isset( $result[1] ) || ( ! is_string( $result[1] ) && ! is_wp_error( $result[1] ) ) ) {
		return false;
	}

	$success = ! array_key_exists( 'success', $result ) || ( false !== $result['success'] );
	$complete = array_key_exists( 'complete', $result )
		? (bool) $result['complete']
		: true;

	return array(
		'success'  => $success,
		'complete' => $complete,
		'message'  => $result[1]
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
				bbp_remove_pending_upgrade( $tool_id );
				return true;
			}
		}
	} finally {
		bbp_admin_repair_tool_unlock( $lock );
	}

	bbp_add_pending_upgrade( $tool_id );
	return false;
}

/**
 * Return a plain-text repair tool result message.
 *
 * @since 2.7.0 bbPress (r8252)
 *
 * @param string|WP_Error $message Repair tool result message.
 * @return string
 */
function bbp_admin_repair_tool_result_message( $message = '' ) {
	if ( is_wp_error( $message ) ) {
		$message = $message->get_error_message();
	}

	return wp_strip_all_tags( (string) $message );
}

/**
 * Return the useful detail from a repair tool result message.
 *
 * @since 2.7.0 bbPress (r8252)
 *
 * @param string|WP_Error $message Repair tool result message.
 * @return string
 */
function bbp_admin_repair_tool_result_detail( $message = '' ) {
	$message = bbp_admin_repair_tool_result_message( $message );
	$divider = strpos( $message, '&hellip;' );

	// The registered title is the canonical operation label in admin notices
	if ( false !== $divider ) {
		$message = ltrim( substr( $message, $divider + strlen( '&hellip;' ) ) );
	}

	return $message;
}

/**
 * Prefix a dry-run result with its completion status.
 *
 * @since 2.7.0 bbPress (r8252)
 *
 * @param string $message Dry-run impact message.
 * @param bool   $success Whether the dry run succeeded.
 * @return string
 */
function bbp_admin_repair_tool_dry_run_message( $message = '', $success = true ) {
	if ( ! $success ) {
		$status = esc_html__( 'Dry run failed! No changes were made.', 'bbpress' );
	} else {
		$status = esc_html__( 'Dry run complete! No changes were made.', 'bbpress' );
	}

	$lines = array( $status );
	if ( ! empty( $message ) ) {
		$lines[] = $message;
	}

	return implode( "\n", $lines );
}

/**
 * Format a repair tool notice using its registered title and result detail.
 *
 * @since 2.7.0 bbPress (r8252)
 *
 * @param array           $tool    Repair tool registration.
 * @param string|WP_Error $message Repair tool result message.
 * @return string
 */
function bbp_admin_repair_tool_notice_message( $tool = array(), $message = '' ) {
	$title   = isset( $tool['title'] )
		? bbp_admin_repair_tool_result_message( $tool['title'] )
		: '';
	$message = nl2br( bbp_admin_repair_tool_result_detail( $message ), false );

	if ( empty( $title ) ) {
		return $message;
	}

	if ( empty( $message ) ) {
		return '<strong>' . $title . '</strong>';
	}

	return '<strong>' . $title . '</strong><br>' . $message;
}

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
	global $wpdb;

	$tool_id = sanitize_key( $tool_id );
	if ( empty( $tool_id ) ) {
		return false;
	}

	$name  = '_bbp_tool_lock_' . md5( $tool_id );
	$now   = time();
	$value = $now . '|' . wp_generate_uuid4();

	for ( $attempt = 0; $attempt < 2; $attempt++ ) {
		/*
		 * Match WordPress Core locks. Transients cannot atomically claim a key
		 * and may use a non-database object-cache backend between requests.
		 */
		$created = $wpdb->query(
			$wpdb->prepare(
				"INSERT IGNORE INTO `{$wpdb->options}` (`option_name`, `option_value`, `autoload`) VALUES (%s, %s, 'no') /* LOCK */",
				$name,
				$value
			)
		);

		if ( 1 === $created ) {
			wp_cache_delete( $name, 'options' );
			wp_cache_delete( 'notoptions', 'options' );

			return array(
				'name'  => $name,
				'value' => $value
			);
		}

		$stored = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT `option_value` FROM `{$wpdb->options}` WHERE `option_name` = %s LIMIT 1",
				$name
			)
		);

		// Only the first attempt may replace the exact stale value it observed
		if ( ( 0 !== $attempt ) || is_null( $stored ) || ( (int) $stored >= ( $now - ( 5 * MINUTE_IN_SECONDS ) ) ) ) {
			break;
		}

		bbp_admin_repair_tool_unlock(
			array(
				'name'  => $name,
				'value' => $stored
			)
		);
	}

	return false;
}

/**
 * Release a repair tool callback lock.
 *
 * @since 2.7.0 bbPress (r8252)
 *
 * @param array $lock Lock owner data.
 */
function bbp_admin_repair_tool_unlock( $lock = array() ) {
	global $wpdb;

	if ( ! empty( $lock['name'] ) && ! empty( $lock['value'] ) ) {
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM `{$wpdb->options}` WHERE `option_name` = %s AND `option_value` = %s",
				$lock['name'],
				$lock['value']
			)
		);
		wp_cache_delete( $lock['name'], 'options' );
		wp_cache_delete( 'notoptions', 'options' );
	}
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
 * The status is stored separately from the ordered queue so a late Pause or
 * Resume request cannot restore an older copy of that queue.
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
 * Return a recoverable repair tool run.
 *
 * @since 2.7.0 bbPress (r8252)
 *
 * @param string $type Repair tool type.
 * @return array|false Run state, or false when no valid run exists.
 */
function bbp_admin_repair_tool_get_run_state( $type = '' ) {
	$option        = bbp_admin_repair_tool_run_state_option_name( $type );
	$status_option = bbp_admin_repair_tool_run_status_option_name( $type );

	// Other requests may have changed these non-autoloaded options already
	if ( ! empty( $option ) && ! empty( $status_option ) ) {
		wp_cache_delete( $option, 'options' );
		wp_cache_delete( $status_option, 'options' );
		wp_cache_delete( 'notoptions', 'options' );
	}

	$state = ! empty( $option )
		? get_option( $option, array() )
		: array();

	if ( ! is_array( $state ) || empty( $state['id'] ) || empty( $state['tools'] ) ) {
		return false;
	}

	$tools = array_values( bbp_get_unique_array_values( array_map( 'sanitize_key', (array) $state['tools'] ) ) );
	if ( empty( $tools ) ) {
		return false;
	}

	$status = isset( $state['status'] )
		? sanitize_key( $state['status'] )
		: 'running';

	if ( ! in_array( $status, array( 'running', 'paused', 'error' ), true ) ) {
		$status = 'error';
	}

	$updated      = isset( $state['updated'] ) ? (int) $state['updated'] : 0;
	$status_state = ! empty( $status_option )
		? get_option( $status_option, array() )
		: array();

	// A matching status option is authoritative for this exact run owner
	if ( is_array( $status_state ) && ! empty( $status_state['id'] ) && hash_equals( sanitize_text_field( $state['id'] ), sanitize_text_field( $status_state['id'] ) ) ) {
		$status = isset( $status_state['status'] )
			? sanitize_key( $status_state['status'] )
			: 'error';

		if ( ! in_array( $status, array( 'running', 'paused', 'error' ), true ) ) {
			$status = 'error';
		}

		$updated = max( $updated, isset( $status_state['updated'] ) ? (int) $status_state['updated'] : 0 );
	}

	return array(
		'id'      => sanitize_text_field( $state['id'] ),
		'type'    => sanitize_key( $type ),
		'tools'   => $tools,
		'status'  => $status,
		'user_id' => isset( $state['user_id'] ) ? (int) $state['user_id'] : 0,
		'created' => isset( $state['created'] ) ? (int) $state['created'] : 0,
		'updated' => $updated
	);
}

/**
 * Save a recoverable repair tool run.
 *
 * @since 2.7.0 bbPress (r8252)
 *
 * @param string $type   Repair tool type.
 * @param array  $tools  Ordered repair tool IDs.
 * @param string $run_id Existing run ID, or empty to start a new run.
 * @param string|null $status running|paused|error, or null to preserve the current status.
 * @param bool $rotate_run_id Whether to replace the exact existing run with a new owner ID.
 * @return array|false Saved run state, or false on invalid input.
 */
function bbp_admin_repair_tool_save_run_state( $type = '', $tools = array(), $run_id = '', $status = 'running', $rotate_run_id = false ) {
	$option        = bbp_admin_repair_tool_run_state_option_name( $type );
	$status_option = bbp_admin_repair_tool_run_status_option_name( $type );
	$tools         = array_values( bbp_get_unique_array_values( array_map( 'sanitize_key', (array) $tools ) ) );
	$update_status = ! is_null( $status );
	$status        = is_null( $status )
		? null
		: sanitize_key( $status );

	if ( empty( $option ) || empty( $status_option ) || empty( $tools ) || ( ! is_null( $status ) && ! in_array( $status, array( 'running', 'paused', 'error' ), true ) ) ) {
		return false;
	}

	$state_lock = bbp_admin_repair_tool_lock_run_state( $type );
	if ( false === $state_lock ) {
		return false;
	}

	try {
		$existing = bbp_admin_repair_tool_get_run_state( $type );
		$run_id   = sanitize_text_field( $run_id );
		$now      = time();

		if ( $rotate_run_id ) {
			if ( empty( $run_id ) || empty( $existing ) || ! hash_equals( $existing['id'], $run_id ) || ( $existing['tools'] !== $tools ) ) {
				return false;
			}

			$run_id = wp_generate_uuid4();
		} elseif ( ! empty( $run_id ) ) {
			if ( empty( $existing ) || ! hash_equals( $existing['id'], $run_id ) ) {
				return false;
			}
		} elseif ( ! empty( $existing ) ) {
			return false;
		} else {
			$run_id   = wp_generate_uuid4();
			$existing = false;
		}
		if ( is_null( $status ) ) {
			$status = ! empty( $existing['status'] )
				? $existing['status']
				: 'running';
		}

		$state = array(
			'id'      => $run_id,
			'type'    => sanitize_key( $type ),
			'tools'   => $tools,
			'status'  => $status,
			'user_id' => ! empty( $existing['user_id'] ) ? $existing['user_id'] : get_current_user_id(),
			'created' => ! empty( $existing['created'] ) ? $existing['created'] : $now,
			'updated' => $now
		);

		update_option( $option, $state, false );
		if ( $update_status ) {
			update_option(
				$status_option,
				array(
					'id'      => $run_id,
					'status'  => $status,
					'updated' => $now
				),
				false
			);
		}

		$saved = bbp_admin_repair_tool_get_run_state( $type );
		if ( empty( $saved ) || ! hash_equals( $saved['id'], $run_id ) || ( $saved['tools'] !== $tools ) || ( $saved['status'] !== $status ) ) {
			return false;
		}

		return $saved;
	} finally {
		bbp_admin_repair_tool_unlock( $state_lock );
	}
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
	$status_option = bbp_admin_repair_tool_run_status_option_name( $type );
	$run_id        = sanitize_text_field( $run_id );
	$status        = sanitize_key( $status );

	if ( empty( $status_option ) || empty( $run_id ) || ! in_array( $status, array( 'running', 'paused', 'error' ), true ) ) {
		return false;
	}

	$state_lock = bbp_admin_repair_tool_lock_run_state( $type );
	if ( false === $state_lock ) {
		return false;
	}

	try {
		$state = bbp_admin_repair_tool_get_run_state( $type );
		if ( empty( $state ) || ! hash_equals( $state['id'], $run_id ) ) {
			return false;
		}

		update_option(
			$status_option,
			array(
				'id'      => $run_id,
				'status'  => $status,
				'updated' => time()
			),
			false
		);

		$saved = bbp_admin_repair_tool_get_run_state( $type );

		return ! empty( $saved ) && hash_equals( $saved['id'], $run_id ) && ( $saved['status'] === $status )
			? $saved
			: false;
	} finally {
		bbp_admin_repair_tool_unlock( $state_lock );
	}
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
	$option        = bbp_admin_repair_tool_run_state_option_name( $type );
	$status_option = bbp_admin_repair_tool_run_status_option_name( $type );
	$run_id        = sanitize_text_field( $run_id );

	if ( empty( $option ) || empty( $status_option ) ) {
		return false;
	}

	$state_lock = bbp_admin_repair_tool_lock_run_state( $type );
	if ( false === $state_lock ) {
		return false;
	}

	try {
		$state = bbp_admin_repair_tool_get_run_state( $type );
		if ( empty( $state ) || ( ! empty( $run_id ) && ! hash_equals( $state['id'], $run_id ) ) ) {
			return false;
		}

		$deleted      = delete_option( $option );
		$status_state = get_option( $status_option, array() );
		if ( is_array( $status_state ) && ! empty( $status_state['id'] ) && hash_equals( $state['id'], sanitize_text_field( $status_state['id'] ) ) ) {
			delete_option( $status_option );
		}

		return $deleted;
	} finally {
		bbp_admin_repair_tool_unlock( $state_lock );
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
		$complete_url = bbp_get_admin_repair_tool_page_url(
			array(
				'page'          => 'bbp-' . $type,
				'action'        => 'run',
				'checked'       => $tools,
				'bbp-tools-run' => $run_id,
				'_wpnonce'      => wp_create_nonce( 'bbpress-do-counts' )
			)
		);
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
		if ( ! empty( $state ) && hash_equals( $state['id'], $run_id ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'The tool run status could not be saved. Try again shortly.', 'bbpress' ) ), 503 );
		}

		wp_send_json_error(
			array(
				'code'    => 'stale_run',
				'message' => esc_html__( 'This tool run is no longer current.', 'bbpress' )
			),
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

	$is_recent = ( 'running' === $state['status'] ) && ( $state['updated'] >= ( time() - ( 2 * MINUTE_IN_SECONDS ) ) );
	if ( $is_recent ) {
		$message = esc_html__( 'A forum tool run appears to be in progress.', 'bbpress' );
	} else {
		$url = wp_nonce_url(
			bbp_get_admin_repair_tool_page_url(
				array(
					'page'          => 'bbp-' . $type,
					'action'        => 'run',
					'checked'       => $state['tools'],
					'bbp-tools-run' => $state['id']
				)
			),
			'bbpress-do-counts'
		);
		$discard_url = wp_nonce_url(
			bbp_get_admin_repair_tool_page_url(
				array(
					'page'              => 'bbp-' . $type,
					'bbp-tools-discard' => $state['id']
				)
			),
			'bbpress-tools-discard-' . $state['id']
		);

		$message  = '<strong>' . esc_html__( 'A previous forum tool run did not finish.', 'bbpress' ) . '</strong><br>';
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
	$checked = array_values( array_map( 'sanitize_key', (array) $_GET['checked'] ) );

	// Get the list
	$list = bbp_get_admin_repair_tools();

	// Stores messages
	$messages           = array();
	$retry              = array();
	$flush              = false;
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
		foreach ( $checked as $item_id ) {
			if ( isset( $list[ $item_id ] ) && ! empty( $list[ $item_id ]['type'] ) ) {
				$types[] = sanitize_key( $list[ $item_id ]['type'] );
			}
		}
		$types    = array_values( array_unique( $types ) );
		$run_type = ! empty( $_GET['page'] )
			? bbp_get_admin_repair_tool_page_id()
			: ( isset( $types[0] ) ? $types[0] : '' );

		// A continuation must still own the exact saved queue
		if ( ! empty( $run_id ) ) {
			$state = bbp_admin_repair_tool_get_run_state( $run_type );
			if ( empty( $state ) || ! hash_equals( $state['id'], $run_id ) || ( $state['tools'] !== $checked ) ) {
				bbp_admin_tools_feedback( esc_html__( 'This tool run is no longer current.', 'bbpress' ), 'error' );
				return;
			}

			foreach ( $checked as $item_id ) {
				if ( ! isset( $list[ $item_id ] ) || ( $run_type !== $list[ $item_id ]['type'] ) ) {
					bbp_admin_repair_tool_update_run_status( $run_type, $run_id, 'error' );
					bbp_admin_tools_feedback( esc_html__( 'An unfinished forum tool is no longer available. Restore it or discard this run.', 'bbpress' ), 'error' );
					return;
				}
			}

			// Rotate ownership so older requests cannot continue the run
			$state = bbp_admin_repair_tool_rotate_run_state( $run_type, $checked, $run_id );
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
				$is_active = ! empty( $state ) && ( 'running' === $state['status'] ) && ( $state['updated'] >= ( time() - ( 2 * MINUTE_IN_SECONDS ) ) );
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

		foreach ( $checked as $item_id ) {
			if ( isset( $list[ $item_id ] ) && is_callable( bbp_get_admin_repair_tool_callback( $list[ $item_id ], 'run' ) ) && bbp_admin_repair_tool_flushes_cache( $list[ $item_id ] ) ) {
				$flush = true;
				break;
			}
		}

		if ( $flush ) {
			wp_cache_flush();
		}
	}

	// Run through checked repair tools
	if ( count( $checked ) ) {
		foreach ( $checked as $item_index => $item_id ) {
			if ( ! isset( $list[ $item_id ] ) ) {
				continue;
			}

			$tool              = $list[ $item_id ];
			$callbacks         = bbp_get_admin_repair_tool_callbacks( $tool );
			$tool['id']        = $item_id;
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

			if ( 'run' === $action ) {
				$lock = bbp_admin_repair_tool_lock( $item_id );
				if ( false === $lock ) {
					/* translators: %s: Repair tool title */
					$message       = esc_html__( '%s is temporarily unavailable. Try again shortly.', 'bbpress' );
					$messages[]    = array( 1, sprintf( $message, $tool['title'] ) );
					$retry         = array_values( array_unique( array_merge( $retry, array_slice( $checked, $item_index ) ) ) );
					$lock_conflict = true;
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
				$message    = esc_html__( '%1$s did not return a valid result for the %2$s action.', 'bbpress' );
				$messages[] = array( 1, sprintf( $message, $tool['title'], bbp_admin_repair_tool_action_label( $action ) ) );
				if ( 'run' === $action ) {
					$retry[] = $item_id;
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

			$result[1]  = bbp_admin_repair_tool_notice_message( $tool, $normalized['message'] );
			$messages[] = $result;

			// Other actions never alter pending upgrades or start the browser runner
			if ( 'run' !== $action ) {
				continue;
			}

			// Failed tools stay pending, while the remaining selected tools continue
			if ( ! $normalized['success'] ) {
				$retry[] = $item_id;
				continue;
			}

			// Successful legacy and final batches are complete
			if ( $normalized['complete'] ) {
				bbp_remove_pending_upgrade( $item_id );
				continue;
			}

			// Continue this tool and the remaining compatible Run tools in the browser
			$queue = array();
			foreach ( array_slice( $checked, $item_index ) as $queue_id ) {
				if ( isset( $list[ $queue_id ] ) && ( $tool['type'] === $list[ $queue_id ]['type'] ) && is_callable( bbp_get_admin_repair_tool_callback( $list[ $queue_id ], 'run' ) ) ) {
					$queue[] = $queue_id;
				}
			}

			$state = bbp_admin_repair_tool_enqueue_runner( $tool['type'], $queue, $run_id );
			if ( empty( $state ) ) {
				$runner_save_failed = true;
				$retry              = array_values( array_unique( array_merge( $retry, $queue ) ) );
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

			$continue_url = wp_nonce_url(
				bbp_get_admin_repair_tool_page_url(
					array(
						'page'          => 'bbp-' . $tool['type'],
						'action'        => 'run',
						'checked'       => $queue,
						'bbp-tools-run' => $run_id
					)
				),
				'bbpress-do-counts'
			);
			$continue = '<a href="' . esc_url( $continue_url ) . '">' . esc_html__( 'Continue', 'bbpress' ) . '</a>';
			$messages[ count( $messages ) - 1 ][1] .= ' <span class="bbp-tools-runner"></span> <span class="hide-if-js">' . $continue . '</span>';
			$messages[ count( $messages ) - 1 ]['class'] = 'updated';
			$messages[ count( $messages ) - 1 ]['dismissible'] = false;
			break;
		}
	}

	// Clear completed runs, or retain only tools that need another attempt
	if ( ( 'run' === $action ) && ! $runner_started ) {
		$recover_retry = ! empty( $run_id ) || $runner_save_failed || ( $lock_conflict && ( count( $checked ) > 1 ) );
		if ( ! empty( $retry ) && $recover_retry ) {
			$retry_type = isset( $list[ $retry[0] ]['type'] )
				? $list[ $retry[0] ]['type']
				: $run_type;
			$state = bbp_admin_repair_tool_save_run_state( $retry_type, $retry, $run_id, 'error' );
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
	if ( $flush ) {
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

	$item_id = isset( $_POST['tool'] )
		? sanitize_key( wp_unslash( $_POST['tool'] ) )
		: '';
	$run_id  = isset( $_POST['run'] )
		? sanitize_text_field( wp_unslash( $_POST['run'] ) )
		: '';

	bbp_register_repair_tools();
	$list = bbp_get_admin_repair_tools( $type );

	$state = bbp_admin_repair_tool_get_run_state( $type );
	if ( empty( $state ) || empty( $run_id ) || ! hash_equals( $state['id'], $run_id ) || ( $item_id !== $state['tools'][0] ) ) {
		wp_send_json_error(
			array(
				'code'    => 'stale_run',
				'message' => esc_html__( 'This tool run is no longer current.', 'bbpress' )
			),
			409
		);
	}

	if ( ! isset( $list[ $item_id ] ) ) {
		bbp_admin_repair_tool_update_run_status( $type, $run_id, 'error' );
		wp_send_json_error( array( 'message' => esc_html__( 'The requested tool is not available.', 'bbpress' ) ), 404 );
	}

	$tool     = $list[ $item_id ];
	$callback = bbp_get_admin_repair_tool_callback( $tool, 'run' );

	if ( ! is_callable( $callback ) ) {
		bbp_admin_repair_tool_update_run_status( $type, $run_id, 'error' );
		wp_send_json_error( array( 'message' => esc_html__( 'The requested tool cannot be run.', 'bbpress' ) ), 400 );
	}

	$lock = bbp_admin_repair_tool_lock( $item_id );
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
	if ( empty( $state ) || ! hash_equals( $state['id'], $run_id ) || ( $item_id !== $state['tools'][0] ) ) {
		$error = array(
			'code'    => 'stale_run',
			'message' => esc_html__( 'This tool run is no longer current.', 'bbpress' )
		);
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
		if ( ( false !== $normalized ) && $normalized['complete'] && bbp_admin_repair_tool_flushes_cache( $tool ) ) {
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
				$state       = bbp_admin_repair_tool_get_run_state( $type );
				$queue_saved = ! empty( $state )
					&& hash_equals( $state['id'], $run_id )
					&& ( $item_id === $state['tools'][0] );

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
					if ( ! empty( $state ) && hash_equals( $state['id'], $run_id ) ) {
						$error = array( 'message' => esc_html__( 'Progress could not be saved. Resume to try this batch again.', 'bbpress' ) );
					} else {
						$error_status = 409;
						$error        = array(
							'code'    => 'stale_run',
							'message' => esc_html__( 'This tool run is no longer current.', 'bbpress' )
						);
					}
				} elseif ( $normalized['complete'] ) {
					bbp_remove_pending_upgrade( $item_id );
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
			'tool'     => $item_id
		)
	);
}

/**
 * Get the array of available repair tools.
 *
 * @since 2.6.0 bbPress (r5885)
 *
 * @param string $type repair|upgrade The type of tools to get. Default empty for all tools.
 * @return array
 */
function bbp_get_admin_repair_tools( $type = '' ) {

	// Get tools array
	$tools = ! empty( bbp_admin()->tools )
		? bbp_admin()->tools
		: array();

	// Maybe limit to type (otherwise return all tools)
	if ( ! empty( $type ) ) {
		$tools = wp_list_filter( bbp_admin()->tools, array( 'type' => $type ) );
	}

	// Filter & return
	return (array) apply_filters( 'bbp_get_admin_repair_tools', $tools, $type );
}

/**
 * Return array of components from the array of registered tools.
 *
 * @since 2.6.0 bbPress (r5885)
 *
 * @return array
 */
function bbp_get_admin_repair_tool_registered_components() {

	// Default return value
	$retval = array();

	// Get tools
	$tools = bbp_get_admin_repair_tools( bbp_get_admin_repair_tool_page_id() );

	// Loop through tools
	if ( ! empty( $tools ) ) {
		$plucked = wp_list_pluck( $tools, 'components' );

		// Loop through components
		if ( count( $plucked ) ) {
			foreach ( $plucked as $components ) {
				foreach ( $components as $component ) {

					// Skip if already in array
					if ( in_array( $component, $retval, true ) ) {
						continue;
					}

					// Add component to the array
					$retval[] = $component;
				}
			}
		}
	}

	// Filter & return
	return (array) apply_filters( 'bbp_get_admin_repair_tool_registered_components', $retval );
}

/**
 * Output the repair list search form.
 *
 * @since 2.6.0 bbPress (r5885)
 */
function bbp_admin_repair_list_search_form() {
?>

	<p class="search-box">
		<label class="screen-reader-text" for="bbp-repair-search-input"><?php esc_html_e( 'Search Tools:', 'bbpress' ); ?></label>
		<input type="search" id="bbp-repair-search-input" name="s" value="<?php _admin_search_query(); ?>">
		<input type="submit" name="search_action" id="search-submit" class="button" value="<?php esc_html_e( 'Search Tools', 'bbpress' ); ?>">
	</p>

	<?php
}

/**
 * Output a select drop-down of components to filter by.
 *
 * @since 2.6.0 bbPress (r5885)
 */
function bbp_admin_repair_list_components_filter() {

	// Sanitize component value, if exists
	$selected = ! empty( $_GET['components'] )
		? sanitize_key( $_GET['components'] )
		: '';

	// Get registered components
	$components = bbp_get_admin_repair_tool_registered_components();

	// Bail if no components
	if ( empty( $components ) ) {
		return;
	} ?>

	<label class="screen-reader-text" for="components"><?php esc_html_e( 'Filter by Component', 'bbpress' ); ?></label>
	<select name="components" id="components" class="postform">
		<option value="" <?php selected( $selected, false ); ?>><?php esc_html_e( 'All Components', 'bbpress' ); ?></option>

		<?php foreach ( $components as $component ) : ?>

			<option class="level-0" value="<?php echo esc_attr( $component ); ?>" <?php selected( $selected, $component ); ?>><?php echo esc_html( bbp_admin_repair_tool_translate_component( $component ) ); ?></option>

		<?php endforeach; ?>

	</select>

	<?php
}

/**
 * Return array of versions from the array of registered tools.
 *
 * @since 2.6.0 bbPress (r6894)
 *
 * @return array
 */
function bbp_get_admin_repair_tool_registered_versions() {

	// Default return value
	$retval = array();

	// Get tools
	$tools = bbp_get_admin_repair_tools( bbp_get_admin_repair_tool_page_id() );

	// Loop through tools
	if ( ! empty( $tools ) ) {
		$plucked = wp_list_pluck( $tools, 'version' );

		// Loop through components
		if ( count( $plucked ) ) {
			foreach ( $plucked as $versions ) {

				// Skip if empty
				if ( empty( $versions ) ) {
					continue;

				// Cast to array if string
				} elseif ( is_string( $versions ) ) {
					$versions = (array) $versions;
				}

				// Loop through versions
				foreach ( $versions as $version ) {

					// Skip if already in array
					if ( in_array( $version, $retval, true ) ) {
						continue;
					}

					// Add component to the array
					$retval[] = $version;
				}
			}
		}
	}

	// Filter & return
	return (array) apply_filters( 'bbp_get_admin_repair_tool_registered_versions', $retval );
}

/**
 * Output a select drop-down of versions to filter by.
 *
 * @since 2.6.0 bbPress (r6894)
 */
function bbp_admin_repair_list_versions_filter() {

	// Sanitize component value, if exists
	$selected = ! empty( $_GET['version'] )
		? sanitize_text_field( $_GET['version'] )
		: '';

	// Get registered components
	$versions = bbp_get_admin_repair_tool_registered_versions();

	// Bail if no components
	if ( empty( $versions ) ) {
		return;
	} ?>

	<label class="screen-reader-text" for="version"><?php esc_html_e( 'Filter by Version', 'bbpress' ); ?></label>
	<select name="version" id="version" class="postform">
		<option value="" <?php selected( $selected, false ); ?>><?php esc_html_e( 'All Versions', 'bbpress' ); ?></option>

		<?php foreach ( $versions as $version ) : ?>

			<option class="level-0" value="<?php echo esc_attr( $version ); ?>" <?php selected( $selected, $version ); ?>><?php echo esc_html( bbp_admin_repair_tool_translate_version( $version ) ); ?></option>

		<?php endforeach; ?>

	</select>

	<?php
}

/** Translations **************************************************************/

/**
 * Maybe translate a repair tool overhead name.
 *
 * @since 2.6.0 bbPress (r6176)
 *
 * @param string $overhead
 * @return string
 */
function bbp_admin_repair_tool_translate_overhead( $overhead = '' ) {

	// Get the name of the component
	switch ( $overhead ) {
		case 'low' :
			$name = esc_html__( 'Low', 'bbpress' );
			break;
		case 'medium' :
			$name = esc_html__( 'Medium', 'bbpress' );
			break;
		case 'high' :
			$name = esc_html__( 'High', 'bbpress' );
			break;
		default :
			$name = ucwords( $overhead );
			break;
	}

	return $name;
}

/**
 * Maybe translate a repair tool component name.
 *
 * @since 2.6.0 bbPress (r5885)
 *
 * @param string $component
 * @return string
 */
function bbp_admin_repair_tool_translate_component( $component = '' ) {

	// Get the name of the component
	switch ( $component ) {
		case 'bbp_user' :
			$name = esc_html__( 'Users', 'bbpress' );
			break;
		case bbp_get_forum_post_type() :
			$name = esc_html__( 'Forums', 'bbpress' );
			break;
		case bbp_get_topic_post_type() :
			$name = esc_html__( 'Topics', 'bbpress' );
			break;
		case bbp_get_reply_post_type() :
			$name = esc_html__( 'Replies', 'bbpress' );
			break;
		case bbp_get_topic_tag_tax_id() :
			$name = esc_html__( 'Topic Tags', 'bbpress' );
			break;
		case bbp_get_user_rewrite_id() :
			$name = esc_html__( 'Users', 'bbpress' );
			break;
		case bbp_get_user_favorites_rewrite_id() :
			$name = esc_html__( 'Favorites', 'bbpress' );
			break;
		case bbp_get_user_subscriptions_rewrite_id() :
			$name = esc_html__( 'Subscriptions', 'bbpress' );
			break;
		case bbp_get_user_engagements_rewrite_id() :
			$name = esc_html__( 'Engagements', 'bbpress' );
			break;
		default :
			$name = ucwords( $component );
			break;
	}

	return $name;
}

/**
 * Maybe translate a repair tool overhead name.
 *
 * @since 2.6.0 bbPress (r6894)
 *
 * @param string $version
 * @return string
 */
function bbp_admin_repair_tool_translate_version( $version = '' ) {

	// Get the version
	switch ( $version ) {
		case '2.5' :
		case '2.5.0' :
			$name = esc_html__( '2.5.0', 'bbpress' );
			break;
		case '2.6' :
		case '2.6.0' :
			$name = esc_html__( '2.6.0', 'bbpress' );
			break;
		default :
			$name = sanitize_text_field( $version );
			break;
	}

	return $name;
}

/** Lists *********************************************************************/

/**
 * Get the array of the repairs to show in a list table.
 *
 * Uses known filters to reduce the registered results down to the most finite
 * set of tools.
 *
 * @since 2.1.0 bbPress (r3884)
 *
 * @return array Repair list of options.
 */
function bbp_admin_repair_list( $type = 'repair' ) {

	// Define empty array
	$repair_list = array();

	// Get the available tools
	$list = bbp_get_admin_repair_tools( $type );

	// Get pending upgrades
	$pending = bbp_get_pending_upgrades();

	// Search
	$search = ! empty( $_GET['s'] )
		? stripslashes( $_GET['s'] )
		: '';

	// Status
	$status = ! empty( $_GET['status'] )
		? sanitize_key( $_GET['status'] )
		: '';

	// Overhead
	$overhead = ! empty( $_GET['overhead'] )
		? sanitize_key( $_GET['overhead'] )
		: '';

	// Component
	$component = ! empty( $_GET['components'] )
		? sanitize_key( $_GET['components'] )
		: '';

	// Version
	$version = ! empty( $_GET['version'] )
		? sanitize_text_field( $_GET['version'] )
		: '';

	// Orderby
	$orderby = ! empty( $_GET['orderby'] )
		? sanitize_key( $_GET['orderby'] )
		: 'priority';

	// Order
	$order = ! empty( $_GET['order'] ) && in_array( strtolower( $_GET['order'] ), array( 'asc', 'desc' ), true )
		? strtolower( $_GET['order'] )
		: 'asc';

	// Overhead filter
	if ( ! empty( $overhead ) ) {
		$list = wp_list_filter( $list, array( 'overhead' => $overhead ) );
	}

	if ( count( $list ) ) {

		// Loop through and key by priority for sorting
		foreach ( $list as $id => $tool ) {

			// Status filter
			if ( ! empty( $status ) && ( 'pending' === $status ) ) {
				if ( ! in_array( $id, (array) $pending, true ) ) {
					continue;
				}
			}

			// Component filter
			if ( ! empty( $component ) ) {
				if ( ! in_array( $component, (array) $tool['components'], true ) ) {
					continue;
				}
			}

			// Version filter
			if ( ! empty( $version ) ) {
				if ( ! in_array( $version, (array) $tool['version'], true ) ) {
					continue;
				}
			}

			// Search
			if ( ! empty( $search ) ) {
				if ( ! strstr( strtolower( $tool['title'] ), strtolower( $search ) ) ) {
					continue;
				}
			}

			// Add to repair list
			$callbacks = bbp_get_admin_repair_tool_callbacks( $tool );
			$repair_list[ $tool['priority'] ] = array(
				'id'          => sanitize_key( $id ),
				'type'        => $tool['type'],
				'title'       => $tool['title'],
				'priority'    => $tool['priority'],
				'description' => $tool['description'],
				'callback'    => isset( $callbacks['run'] ) ? $callbacks['run'] : '',
				'callbacks'   => $callbacks,
				'overhead'    => $tool['overhead'],
				'version'     => $tool['version'],
				'components'  => $tool['components']
			);
		}
	}

	// Sort
	$retval = wp_list_sort( $repair_list, $orderby, $order, true );

	// Filter & return
	return (array) apply_filters( 'bbp_repair_list', $retval );
}

/**
 * Get filter links for components for a specific admin repair tool.
 *
 * @since 2.6.0 bbPress (r5885)
 *
 * @param array $item
 * @return array
 */
function bbp_get_admin_repair_tool_components( $item = array() ) {

	// Get the tools URL
	$tools_url = bbp_get_admin_repair_tool_page_url();

	// Define links array
	$links      = array();
	$components = ! empty( $item['components'] )
		? (array) $item['components']
		: array();

	// Loop through tool components and build links
	if ( count( $components ) ) {
		foreach ( $components as $component ) {
			$args       = array( 'components' => $component );
			$filter_url = add_query_arg( $args, $tools_url );
			$name       = bbp_admin_repair_tool_translate_component( $component );
			$links[]    = '<a href="' . esc_url( $filter_url ) . '">' . esc_html( $name ) . '</a>';
		}

	// No components, so return a dash
	} else {
		$links[] = '&mdash;';
	}

	// Filter & return
	return (array) apply_filters( 'bbp_get_admin_repair_tool_components', $links, $item );
}

/**
 * Get filter links for versions for a specific admin repair tool.
 *
 * @since 2.6.0 bbPress (r6894)
 *
 * @param array $item
 * @return array
 */
function bbp_get_admin_repair_tool_version( $item = array() ) {

	// Get the tools URL
	$tools_url = bbp_get_admin_repair_tool_page_url();

	// Define links array
	$links    = array();
	$versions = ! empty( $item['version'] )
		? (array) $item['version']
		: array();

	// Loop through tool versions and build links
	if ( count( $versions ) ) {
		foreach ( $versions as $version ) {
			$args       = array( 'version' => $version );
			$filter_url = add_query_arg( $args, $tools_url );
			$name       = bbp_admin_repair_tool_translate_version( $version );
			$links[]    = '<a href="' . esc_url( $filter_url ) . '">' . esc_html( $name ) . '</a>';
		}

	// No versions, so return a dash
	} else {
		$links[] = '&mdash;';
	}

	// Filter & return
	return (array) apply_filters( 'bbp_get_admin_repair_tool_version', $links, $item );
}

/**
 * Get filter links for overhead for a specific admin repair tool.
 *
 * @since 2.6.0 bbPress (r6176)
 *
 * @param array $item
 * @return array
 */
function bbp_get_admin_repair_tool_overhead( $item = array() ) {

	// Get the tools URL
	$tools_url = bbp_get_admin_repair_tool_page_url();

	// Define links array
	$links     = array();
	$overheads = ! empty( $item['overhead'] )
		? (array) $item['overhead']
		: array();

	// Loop through tool overhead and build links
	if ( count( $overheads ) ) {
		foreach ( $overheads as $overhead ) {
			$args       = array( 'overhead' => $overhead );
			$filter_url = add_query_arg( $args, $tools_url );
			$name       = bbp_admin_repair_tool_translate_overhead( $overhead );
			$links[]    = '<a href="' . esc_url( $filter_url ) . '">' . esc_html( $name ) . '</a>';
		}

	// No overhead, so return a single dash
	} else {
		$links[] = '&mdash;';
	}

	// Filter & return
	return (array) apply_filters( 'bbp_get_admin_repair_tool_overhead', $links, $item );
}

/** Overhead ******************************************************************/

/**
 * Output filter links for overheads for a specific admin repair tool.
 *
 * @since 2.6.0 bbPress (r5885)
 *
 * @param array $args
 */
function bbp_admin_repair_tool_overhead_filters( $args = array() ) {
	echo bbp_get_admin_repair_tool_overhead_filters( $args );
}

/**
 * Get filter links for overheads for a specific admin repair tool.
 *
 * @since 2.6.0 bbPress (r5885)
 *
 * @param array $args
 * @return array
 */
function bbp_get_admin_repair_tool_overhead_filters( $args = array() ) {

	// Parse args
	$r = bbp_parse_args(
		$args,
		array(
			'before'       => '<ul class="subsubsub">',
			'after'        => '</ul>',
			'link_before'  => '<li>',
			'link_after'   => '</li>',
			'count_before' => ' <span class="count">(',
			'count_after'  => ')</span>',
			'sep'          => ' | ',

			// Retired, use 'sep' instead
			'separator'    => false
		),
		'get_admin_repair_tool_overhead_filters'
	);

	/**
	 * Necessary for backwards compatibility
	 * @see https://bbpress.trac.wordpress.org/ticket/2900
	 */
	if ( ! empty( $r['separator'] ) ) {
		$r['sep'] = $r['separator'];
	}

	// Count the tools
	$tools = bbp_get_admin_repair_tools( bbp_get_admin_repair_tool_page_id() );

	// Get the tools URL
	$tools_url = bbp_get_admin_repair_tool_page_url();

	// Define arrays
	$overheads = $links = array();

	// Loop through tools and count overheads
	if ( count( $tools ) ) {
		foreach ( $tools as $tool ) {

			// Get the overhead level
			$overhead = $tool['overhead'];

			// Set an empty count
			if ( empty( $overheads[ $overhead ] ) ) {
				$overheads[ $overhead ] = 0;
			}

			// Bump the overhead count
			++$overheads[ $overhead ];
		}
	}

	// Get the current overhead, if any
	$selected = ! empty( $_GET['overhead'] )
		? sanitize_key( $_GET['overhead'] )
		: '';

	// Create the "All" link
	$current = empty( $selected ) ? 'current' : '';

	$all_text = sprintf(
		/* translators: %s: Number of items */
		esc_html__( 'All %s', 'bbpress' ),
		$r['count_before'] . count( $tools ) . $r['count_after']
	);

	$links[] = sprintf(
		'%1$s<a href="%2$s" class="%3$s">%4$s</a>%5$s',
		$r['link_before'],
		esc_url( $tools_url ),
		esc_attr( $current ),
		$all_text,
		$r['link_after']
	);

	// Loop through overheads and created links
	if ( count( $overheads ) ) {

		// Sort
		ksort( $overheads );

		// Loop through overheads and build filter
		foreach ( $overheads as $overhead => $count ) {

			// Build the filter URL
			$key        = sanitize_key( $overhead );
			$args       = array( 'overhead' => $key );
			$filter_url = add_query_arg( $args, $tools_url );

			// Figure out separator and active class
			$current  = ( $selected === $key )
				? 'current'
				: '';

			// Counts to show
			if ( ! empty( $count ) ) {
				$overhead_count = $r['count_before'] . $count . $r['count_after'];
			}

			// Build the link
			$links[] = $r['link_before'] . '<a href="' . esc_url( $filter_url ) . '" class="' . esc_attr( $current ) . '">' . bbp_admin_repair_tool_translate_overhead( $overhead ) . $overhead_count . '</a>' . $r['link_after'];
		}
	}

	// Surround output with before & after strings
	$output = $r['before'] . implode( $r['sep'], $links ) . $r['after'];

	// Filter & return
	return apply_filters( 'bbp_get_admin_repair_tool_overhead_filters', $output, $r, $args );
}

/** Pending ******************************************************************/

/**
 * Output filter links for statuses.
 *
 * @since 2.6.0 bbPress (r6926)
 *
 * @param array $args
 */
function bbp_admin_repair_tool_status_filters( $args = array() ) {
	echo bbp_get_admin_repair_tool_status_filters( $args );
}

/**
 * Get filter links for statuses.
 *
 * @since 2.6.0 bbPress (r6926)
 *
 * @param array $args
 * @return array
 */
function bbp_get_admin_repair_tool_status_filters( $args = array() ) {

	// Parse args
	$r = bbp_parse_args(
		$args,
		array(
			'before'       => '<ul class="subsubsub">',
			'after'        => '</ul>',
			'link_before'  => '<li>',
			'link_after'   => '</li>',
			'count_before' => ' <span class="count">(',
			'count_after'  => ')</span>',
			'sep'          => ' | ',

			// Retired, use 'sep' instead
			'separator'    => false
		),
		'get_admin_repair_tool_status_filters'
	);

	/**
	 * Necessary for backwards compatibility
	 * @see https://bbpress.trac.wordpress.org/ticket/2900
	 */
	if ( ! empty( $r['separator'] ) ) {
		$r['sep'] = $r['separator'];
	}

	// Get the type of tool
	$type = bbp_get_admin_repair_tool_page_id();

	// Count the tools
	$tools = bbp_get_admin_repair_tools( $type );

	// Get the tools URL
	$tools_url = bbp_get_admin_repair_tool_page_url();

	// Get pending upgrades
	$pending = bbp_get_pending_upgrades( $type );

	// Get the current status, if any
	$selected = ! empty( $_GET['status'] )
		? sanitize_key( $_GET['status'] )
		: '';

	// Nothing is current?
	$all_current = empty( $selected )
		? 'current'
		: '';

	// Pending is current?
	$pending_current = ( 'pending' === $selected )
		? 'current'
		: '';

	// Sort
	ksort( $pending );

	// Build the filter URL
	$filter_url = add_query_arg(
		array(
			'status' => 'pending'
		),
		$tools_url
	);

	// Count HTML
	$all_count     = $r['count_before'] . count( $tools   ) . $r['count_after'];
	$pending_count = $r['count_before'] . count( $pending ) . $r['count_after'];

	// Define links
	$links = array(
		$r['link_before'] . '<a href="' . esc_url( $tools_url  ) . '" class="' . esc_attr( $all_current     ) . '">' .
			sprintf(
				/* translators: %s: Number of items */
				esc_html__( 'All %s', 'bbpress' ),
				$all_count
			) .
		'</a>' . $r['link_after'],

		$r['link_before'] . '<a href="' . esc_url( $filter_url ) . '" class="' . esc_attr( $pending_current ) . '">' .
			sprintf(
				/* translators: %s: Number of pending items */
				esc_html__( 'Pending %s', 'bbpress' ),
				$pending_count
			) .
		'</a>' . $r['link_after']
	);

	// Surround output with before & after strings
	$output = $r['before'] . implode( $r['sep'], $links ) . $r['after'];

	// Filter & return
	return apply_filters( 'bbp_get_admin_repair_tool_status_filters', $output, $r, $args );
}
