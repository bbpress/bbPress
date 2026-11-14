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
	return isset( $_GET['page'] )
		? sanitize_key( wp_unslash( $_GET['page'] ) )
		: '';
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
 * Invoke an admin repair tool callback with its action-specific arguments.
 *
 * Run retains the historical no-argument callback contract. Every other
 * action receives its normalized action and complete tool registration.
 *
 * @since 2.7.0 bbPress (r8350)
 * @access private
 *
 * @param callable $callback Repair tool callback.
 * @param string   $action   Repair tool action.
 * @param array    $tool     Complete repair tool registration.
 * @return mixed Callback result.
 */
function bbp_admin_repair_tool_invoke_callback( $callback, $action, $tool ) {
	$action = sanitize_key( $action );

	return 'run' === $action
		? call_user_func( $callback )
		: call_user_func(
			$callback,
			array(
				'action' => $action,
				'tool'   => $tool,
			)
		);
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

	return bbp_get_unique_array_values( $actions );
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
		'message'  => $result[1],
		'batches'  => isset( $result['batches'] ) ? absint( $result['batches'] ) : 0
	);
}

/**
 * Return the maximum work items in one built-in tool batch.
 *
 * @since 2.7.0 bbPress (r8253)
 *
 * @param string $tool_id Repair or upgrade tool ID.
 * @return int Batch size between 1 and 1000.
 */
function bbp_admin_repair_tool_batch_size( $tool_id = '' ) {
	/**
	 * Filters the work limit for one built-in repair or upgrade batch.
	 *
	 * @since 2.7.0 bbPress (r8253)
	 *
	 * @param int    $size    Maximum work items, limited to between 1 and 1000.
	 * @param string $tool_id Repair or upgrade tool ID.
	 */
	return max( 1, min( 1000, (int) apply_filters( 'bbp_admin_repair_tool_batch_size', 100, $tool_id ) ) );
}

/**
 * Return the maximum complete list passed through a legacy source filter.
 *
 * These filters predate resumable tools and require one complete list. Keep
 * their compatibility path explicitly bounded; larger sources fail safely
 * instead of defeating the request budget or exhausting memory.
 *
 * @since 2.7.0 bbPress (r8359)
 * @access private
 *
 * @param string $hook      Legacy filter name.
 * @param int    $object_id Parent object ID.
 * @param string $tool_id   Repair tool ID.
 * @return int Compatibility-list limit between 1 and 100000.
 */
function bbp_admin_repair_tool_filter_limit( $hook, $object_id, $tool_id ) {
	/**
	 * Filters the maximum complete list used for a legacy repair-tool filter.
	 *
	 * The default preserves complete-list compatibility for established large
	 * forums and topics. The hard maximum remains a rational memory bound.
	 *
	 * @since 2.7.0 bbPress (r8359)
	 *
	 * @param int    $limit     Maximum list length.
	 * @param string $hook      Legacy filter name.
	 * @param int    $object_id Parent object ID.
	 * @param string $tool_id   Repair tool ID.
	 */
	$limit = apply_filters( 'bbp_admin_repair_tool_filter_limit', 10000, $hook, $object_id, $tool_id );

	return max( 1, min( 100000, (int) $limit ) );
}

/**
 * Format batch progress for Run and complete details for Dry Run notices.
 *
 * @since 2.7.0 bbPress (r8315)
 *
 * @param string $details  Tool-specific, already escaped details.
 * @param bool   $complete Whether this was the final batch.
 * @param bool   $dry_run  Whether this was a preview.
 * @return string Plain-text notice with one statement per line.
 */
function bbp_admin_repair_tool_batch_message( $details, $complete, $dry_run = false ) {
	if ( $dry_run ) {
		return $details;
	}
	$status = $complete ? esc_html__( 'Complete!', 'bbpress' ) : esc_html__( 'Batch complete!', 'bbpress' );
	return $status . "\n" . esc_html__( 'This batch:', 'bbpress' ) . "\n" . $details;
}

/**
 * Format a worker's successful batch or failure using the shared result contract.
 *
 * Callers retain their translated details and no-data cases. Numeric codes are
 * worker errors here, not legacy callback results where 1 may mean no work.
 *
 * @since 2.7.0 bbPress (r8315)
 * @access private
 *
 * @param array  $batch     Worker status, including code, error, and complete.
 * @param string $statement Translated status statement.
 * @param string $detail    Translated success detail.
 * @param bool   $dry_run   Whether to omit Run progress text.
 * @return array Tool result and completion flag.
 */
function bbp_admin_repair_tool_batch_result( $batch, $statement, $detail = '', $dry_run = false ) {
	if ( $batch['code'] ) {
		$error  = isset( $batch['error'] ) ? $batch['error'] : '';
		if ( 'progress' === $error ) {
			$result = esc_html__( 'Failed! Progress could not be saved. Run the tool again to retry.', 'bbpress' );
		} elseif ( 'source-limit' === $error ) {
			$result = esc_html__( 'Failed! A legacy filter returned more data than this tool can process safely. Adjust the bbp_admin_repair_tool_filter_limit filter or remove the legacy callback before trying again.', 'bbpress' );
		} elseif ( 'source-invalid' === $error ) {
			$result = esc_html__( 'Failed! Source data changed or could not be processed safely. Run the tool again to retry.', 'bbpress' );
		} else {
			$result = esc_html__( 'Failed!', 'bbpress' );
		}
		return array(
			$batch['code'],
			sprintf( $statement, $result ),
			'success'  => false,
			'complete' => false,
			'error'    => $error,
		);
	}
	return array(
		0,
		sprintf( $statement, bbp_admin_repair_tool_batch_message( $detail, $batch['complete'], $dry_run ) ),
		'complete' => $batch['complete'],
		'batches'  => isset( $batch['batches'] ) ? $batch['batches'] : 0,
		'error'    => '',
	);
}

/**
 * Present one worker batch through the shared tools notice contract.
 *
 * Tool callbacks retain their translated status and detail strings. This
 * helper centralizes restart notices, semantic failures, completed no-op
 * results, and successful batch results so every presenter follows the same
 * lifecycle.
 *
 * @since 2.7.0 bbPress (r8350)
 * @access private
 *
 * @param array  $batch          Worker result.
 * @param string $statement      Translated status statement with one placeholder.
 * @param string $detail         Translated success detail.
 * @param bool   $dry_run        Whether this is a read-only preview.
 * @param bool   $no_changes     Whether the completed operation found no work.
 * @param string $no_change_text Translated completed no-op detail.
 * @return array Tool callback result.
 */
function bbp_admin_repair_tool_present_batch( $batch, $statement, $detail = '', $dry_run = false, $no_changes = false, $no_change_text = '' ) {
	$statement = bbp_admin_repair_tool_restart_statement( $statement, ! empty( $batch['restarted'] ), $dry_run );
	if ( ! empty( $batch['code'] ) ) {
		return bbp_admin_repair_tool_batch_result( $batch, $statement );
	}
	if ( $no_changes && ! empty( $batch['complete'] ) ) {
		return array(
			1,
			sprintf( $statement, $no_change_text ),
			'complete' => true,
			'batches'  => isset( $batch['batches'] ) ? $batch['batches'] : 0,
		);
	}

	return bbp_admin_repair_tool_batch_result( $batch, $statement, $detail, $dry_run );
}

/**
 * Select a bounded user batch through the normal WordPress query filters.
 *
 * Apply ID bounds and fixed paging to this query alone, preserving role and site
 * selection while avoiding offsets into a result set that repairs may change.
 *
 * @since 2.7.0 bbPress (r8288)
 *
 * @param array $args     User query arguments.
 * @param int   $cursor   Last completed user ID.
 * @param int   $upper_id Fixed upper user ID.
 * @param int   $size     Work budget, with one additional lookahead row.
 * @param int   $matched  Optional output of raw matching rows, including lookahead.
 * @return array|false User IDs, or false on a read failure or invalid results.
 */
function bbp_admin_repair_get_users_batch( $args, $cursor, $upper_id, $size, &$matched = null ) {
	$db         = bbp_db();
	$matched    = 0;
	$batch_args = array(
		'fields'        => 'ID',
		'number'        => max( 1, min( 1000, (int) $size ) ) + 1,
		'offset'        => 0,
		'paged'         => 1,
		'orderby'       => 'ID',
		'order'         => 'ASC',
		'count_total'   => false,
		'cache_results' => false
	);
	$args  = array_merge( $args, $batch_args );
	$users = new WP_User_Query();
	$bound = static function ( $query ) use ( $users, $db, $cursor, $upper_id, $batch_args ) {
		if ( $query === $users ) {
			// Keep ID fields and paging consistent for SQL and short-circuited results
			$query->query_vars    = array_merge( $query->query_vars, $batch_args );
			$query->query_fields  = "{$db->users}.ID";
			$query->query_orderby = "ORDER BY {$db->users}.ID ASC";
			$query->query_limit   = $db->prepare( 'LIMIT %d', $batch_args['number'] );
			$query->query_where  .= $db->prepare( " AND {$db->users}.ID > %d AND {$db->users}.ID <= %d", $cursor, $upper_id );
		}
	};
	add_action( 'pre_user_query', $bound );
	try {
		$users->prepare_query( $args );
		$users->query();
		$ids = $users->get_results();
		if ( ! empty( $db->last_error ) || ! is_array( $ids ) ) {
			return false;
		}
		// Short-circuited queries must preserve the ordered, bounded result window
		$matched  = count( $ids );
		$previous = $cursor;
		foreach ( $ids as $id ) {
			$id = absint( $id );
			if ( $id <= $cursor || $id < $previous || $id > $upper_id ) {
				return false;
			}
			$previous = $id;
		}
		return array_slice( wp_parse_id_list( $ids ), 0, $args['number'] );
	} finally {
		remove_action( 'pre_user_query', $bound );
	}
}

/**
 * Build the source query shared by engagement repairs and upgrades.
 *
 * @since 2.7.0 bbPress (r8281)
 *
 * @param array $topic_statuses Countable topic statuses.
 * @param array $reply_statuses Countable reply statuses.
 * @param bool  $anonymous      Whether to retain anonymous authors.
 * @return string Prepared relationship query.
 */
function bbp_admin_repair_get_engagements_query( $topic_statuses = array(), $reply_statuses = array(), $anonymous = false ) {
	$bbp_db         = bbp_db();
	$topic_statuses = ! empty( $topic_statuses ) ? (array) $topic_statuses : array( '' );
	$reply_statuses = ! empty( $reply_statuses ) ? (array) $reply_statuses : array( '' );
	$topic_slots    = implode( ', ', array_fill( 0, count( $topic_statuses ), '%s' ) );
	$reply_slots    = implode( ', ', array_fill( 0, count( $reply_statuses ), '%s' ) );
	$author_sql     = $anonymous ? '' : ' AND posts.post_author > 0';
	$tpt            = bbp_get_topic_post_type();

	return $bbp_db->prepare(
		"SELECT topics.ID AS post_id, posts.post_author AS user_id
		FROM {$bbp_db->posts} AS posts
		INNER JOIN {$bbp_db->postmeta} AS postmeta
			ON posts.ID = postmeta.post_id AND postmeta.meta_key = '_bbp_topic_id'
		INNER JOIN {$bbp_db->posts} AS topics
			ON topics.ID = postmeta.meta_value AND topics.post_type = %s
		WHERE ((posts.post_type = %s AND posts.post_status IN ({$topic_slots}))
			OR (posts.post_type = %s AND posts.post_status IN ({$reply_slots}))){$author_sql}",
		array_merge( array( $tpt, $tpt ), $topic_statuses, array( bbp_get_reply_post_type() ), $reply_statuses )
	);
}

/**
 * Update or remove one metadata row and verify the uncached stored result.
 *
 * @since 2.7.0 bbPress (r8299)
 * @since 2.7.0 bbPress (r8303) Verify serialized values and bounded deletions.
 *
 * @param string               $meta_type Metadata type: 'post' or 'user'.
 * @param object               $row       Source metadata row.
 * @param string               $key       Canonical metadata key.
 * @param int|string|array|null $value     Repaired value, or null to remove the row.
 * @return int Zero on success, or a read, delete, or update error code.
 */
function bbp_admin_repair_metadata_row( $meta_type, $row, $key, $value ) {
	if ( ! in_array( $meta_type, array( 'post', 'user' ), true ) ) {
		return 1;
	}
	$db         = bbp_db();
	$table      = $db->{$meta_type . 'meta'};
	$id_column  = 'user' === $meta_type ? 'umeta_id' : 'meta_id';
	$object_key = $meta_type . '_id';
	$object_id  = (int) $row->$object_key;
	$meta_id    = (int) $row->$id_column;
	$expected   = is_array( $value ) ? maybe_serialize( $value ) : (string) $value;
	wp_cache_delete( $object_id, $meta_type . '_meta' );
	if ( null === $value ) {
		delete_metadata_by_mid( $meta_type, $meta_id );
	} elseif ( $expected !== $row->meta_value || $key !== $row->meta_key ) {
		update_metadata_by_mid( $meta_type, $meta_id, $value, $key );
	}
	wp_cache_delete( $object_id, $meta_type . '_meta' );
	$columns = null === $value ? $id_column : "{$object_key}, meta_key, meta_value";
	$stored  = $db->get_row( $db->prepare( "SELECT {$columns} FROM {$table} WHERE {$id_column} = %d", $meta_id ) );
	if ( ! empty( $db->last_error ) ) {
		return 1;
	}
	if ( null === $value ) {
		return empty( $stored ) ? 0 : 4;
	}
	return ! empty( $stored ) && $object_id === (int) $stored->$object_key && $key === $stored->meta_key && $expected === $stored->meta_value ? 0 : 5;
}

/**
 * Find fields that differ from one canonical metadata value, or expected absence.
 *
 * Match keys with WordPress database collation, then require one exact key and
 * serialized value. Aggregate duplicates in the database. A worker can check
 * its completed window at once; workers saving partial progress check each key
 * before advancing. This reads storage without changing metadata or caches.
 *
 * @since 2.7.0 bbPress (r8315)
 * @access private
 *
 * @param string $meta_type Metadata type: 'post' or 'user'.
 * @param string $key       Canonical metadata key.
 * @param array  $expected  Object IDs mapped to values, or null for absence.
 * @return array|false Mismatched object IDs, or false on a read error.
 */
function bbp_admin_repair_metadata_mismatches( $meta_type, $key, $expected ) {
	$status = bbp_admin_repair_metadata_status( $meta_type, $key, $expected );
	return false === $status ? false : array_keys( wp_list_filter( $status, array( 'changed' => true ) ) );
}

/**
 * Inspect a window of complete metadata fields without loading duplicate rows.
 *
 * Repair workers use this only to verify completed writes; Dry Run executes the
 * same bounded mutation planner as Run.
 *
 * @since 2.7.0 bbPress (r8331)
 * @access private
 *
 * @param string $meta_type Metadata type: 'post' or 'user'.
 * @param string $key       Canonical metadata key.
 * @param array  $expected  Object IDs mapped to values, or null for absence.
 * @return array|false Change status by object ID, or a read error.
 */
function bbp_admin_repair_metadata_status( $meta_type, $key, $expected ) {
	if ( ! in_array( $meta_type, array( 'post', 'user' ), true ) ) {
		return false;
	}
	if ( empty( $expected ) ) {
		return array();
	}
	$db     = bbp_db();
	$table  = $db->{$meta_type . 'meta'};
	$column = $meta_type . '_id';
	$slots  = implode( ', ', array_fill( 0, count( $expected ), '%d' ) );
	$cases  = array();
	$args   = array( $key );
	foreach ( $expected as $id => $value ) {
		$cases[] = null === $value ? 'WHEN %d THEN NULL' : 'WHEN %d THEN %s';
		$args[]  = $id;
		if ( null !== $value ) {
			$args[] = is_array( $value ) ? maybe_serialize( $value ) : (string) $value;
		}
	}
	$values = 'CASE ' . $column . ' ' . implode( ' ', $cases ) . ' END';
	$rows   = $db->get_results(
		$db->prepare(
			"SELECT {$column} AS object_id, COUNT(*) AS row_count,
				SUM(BINARY meta_key = BINARY %s AND BINARY meta_value = BINARY ({$values})) AS correct_values
			FROM {$table} WHERE meta_key = %s AND {$column} IN ({$slots}) GROUP BY {$column}",
			array_merge( $args, array( $key ), array_keys( $expected ) )
		),
		OBJECT_K
	);
	if ( ! empty( $db->last_error ) ) {
		return false;
	}
	$status = array();
	foreach ( $expected as $id => $value ) {
		$row     = isset( $rows[ $id ] ) ? $rows[ $id ] : null;
		$count   = $row ? (int) $row->row_count : 0;
		$correct = $row && $row->correct_values > 0;
		$status[ $id ] = array(
			'changed' => null === $value ? $count > 0 : ( ! $correct || 1 !== $count )
		);
	}
	return $status;
}

/**
 * Update a complete metadata field through WordPress and verify every stored row.
 *
 * Preserve native update_metadata filters and actions. A successful API return or
 * a filtered/cached read alone cannot establish that the requested value stuck.
 *
 * @since 2.7.0 bbPress (r8315)
 *
 * @param string $meta_type Metadata type: 'post' or 'user'.
 * @param int    $object_id Object ID.
 * @param string $key       Metadata key.
 * @param mixed  $value     Expected field value.
 * @return bool Whether all stored values match and at least one row exists.
 */
function bbp_admin_repair_metadata_update( $meta_type, $object_id, $key, $value ) {
	if ( ! in_array( $meta_type, array( 'post', 'user' ), true ) ) {
		return false;
	}
	$db    = bbp_db();
	$table = $db->{$meta_type . 'meta'};
	wp_cache_delete( $object_id, $meta_type . '_meta' );
	update_metadata( $meta_type, $object_id, $key, $value );
	wp_cache_delete( $object_id, $meta_type . '_meta' );
	$matches = $db->get_var( $db->prepare( "SELECT MIN(BINARY meta_key = BINARY %s AND BINARY meta_value = BINARY %s) FROM {$table} WHERE {$meta_type}_id = %d AND meta_key = %s", $key, maybe_serialize( $value ), $object_id, $key ) );
	return empty( $db->last_error ) && '1' === $matches;
}

/**
 * Insert and verify a replacement before allowing source-row cleanup.
 *
 * Combine the pre-insert ID bound with the native added-meta action to identify
 * a new row owned by this insertion. A filter returning an existing row ID must
 * never make that row eligible for rollback. Failed verification can use one
 * compensating delete.
 *
 * @since 2.7.0 bbPress (r8303)
 *
 * @param string           $meta_type Metadata type: 'post' or 'user'.
 * @param int              $object_id Object ID.
 * @param string           $key       Canonical metadata key.
 * @param int|string|array $value     Replacement value.
 * @return array Status code and verified metadata ID.
 */
function bbp_admin_repair_metadata_add( $meta_type, $object_id, $key, $value ) {
	$result = array(
		'code'    => 4,
		'meta_id' => 0
	);
	if ( ! in_array( $meta_type, array( 'post', 'user' ), true ) ) {
		return $result;
	}
	$db         = bbp_db();
	$table      = $db->{$meta_type . 'meta'};
	$id_column  = 'user' === $meta_type ? 'umeta_id' : 'meta_id';
	$object_key = $meta_type . '_id';
	$upper_id   = (int) $db->get_var( "SELECT MAX({$id_column}) FROM {$table}" );
	if ( ! empty( $db->last_error ) ) {
		$result['code'] = 2;
		return $result;
	}
	$created = array();
	$capture = static function ( $id, $object, $meta_key ) use ( $object_id, $key, &$created ) {
		if ( (int) $object === (int) $object_id && $meta_key === $key ) {
			$created[ (int) $id ] = true;
		}
	};
	$action = 'added_' . $meta_type . '_meta';
	add_action( $action, $capture, 10, 3 );
	try {
		$id = add_metadata( $meta_type, $object_id, $key, $value, false );
	} finally {
		remove_action( $action, $capture, 10 );
		wp_cache_delete( $object_id, $meta_type . '_meta' );
	}
	if ( empty( $id ) ) {
		return $result;
	}
	$id     = (int) $id;
	$stored = $db->get_row( $db->prepare( "SELECT {$id_column}, {$object_key}, meta_key, meta_value FROM {$table} WHERE {$id_column} = %d", $id ) );
	if ( ! empty( $db->last_error ) ) {
		$result['code'] = 2;
		return $result;
	}
	$identity = ! empty( $stored ) && (int) $stored->$id_column === $id
		&& (int) $stored->$object_key === (int) $object_id && $stored->meta_key === $key;
	$expected = is_array( $value ) ? maybe_serialize( $value ) : (string) $value;
	if ( $identity && $stored->meta_value === $expected ) {
		$result['code']    = 0;
		$result['meta_id'] = $id;
		return $result;
	}
	$result['code'] = 5;
	if ( $identity && $id > $upper_id && isset( $created[ $id ] ) ) {
		$deleted = bbp_admin_repair_metadata_row( $meta_type, $stored, $key, null );
		if ( $deleted ) {
			$result['code'] = 1 === $deleted ? 2 : 3;
		}
	}
	return $result;
}

/**
 * Reconcile one metadata value with bounded writes.
 *
 * Preserve a canonical row, or add it before cleaning up other rows. A null
 * value removes all matching rows. Counts, references, activity, and lists
 * share this cleanup, and each inspected object costs at least one work item.
 * Keep the first source row readable until the other duplicates are removed.
 * Failed insertions can use one extra compensating delete before returning an
 * error. Reuse a spare row if that rollback fails, keeping retries bounded.
 *
 * @since 2.7.0 bbPress (r8277)
 * @since 2.7.0 bbPress (r8303) Support serialized lists and verify inserted rows.
 * @since 2.7.0 bbPress (r8331) Share bounded mutation planning with full Dry Runs.
 *
 * @param string               $meta_type Metadata type: 'post' or 'user'.
 * @param int                  $object_id Object ID.
 * @param string               $key       Metadata key.
 * @param int|string|array|null $value     Expected value, or null when no row should remain.
 * @param int                  $limit     Maximum metadata writes to plan or perform.
 * @param bool                 $dry_run   Whether to preview without writes.
 * @param array|null           $preview   Request-local Dry Run progress for this field.
 * @return array Status code, work used, changed flag, and completion flag.
 */
function bbp_admin_repair_single_metadata( $meta_type, $object_id, $key, $value, $limit, $dry_run = false, &$preview = null ) {
	$bbp_db     = bbp_db();
	$table      = $bbp_db->{$meta_type . 'meta'};
	$id_column  = ( 'user' === $meta_type ) ? 'umeta_id' : 'meta_id';
	$object_key = $meta_type . '_id';
	$limit      = max( 1, (int) $limit );
	$result     = array(
		'code'       => 0,
		'work'       => 0,
		'operations' => 0,
		'changed'    => false,
		'complete'   => false
	);
	$stored_value = is_array( $value ) ? maybe_serialize( $value ) : (string) $value;
	$signature    = md5( maybe_serialize( array( $meta_type, $object_id, $key, $value ) ) );
	$resuming     = $dry_run && is_array( $preview ) && isset( $preview['signature'] ) && $signature === $preview['signature'];
	$keep_id      = $resuming ? (int) $preview['keep_id'] : 0;
	$replace      = $resuming ? ! empty( $preview['replace'] ) : false;
	if ( ! $resuming && null !== $value ) {
		$keep_id = (int) $bbp_db->get_var( $bbp_db->prepare( "SELECT {$id_column} FROM {$table} WHERE {$object_key} = %d AND BINARY meta_key = %s AND BINARY meta_value = %s ORDER BY {$id_column} LIMIT 1", $object_id, $key, $stored_value ) );
		if ( ! empty( $bbp_db->last_error ) ) {
			$result['code'] = 2;
			return $result;
		}
		$replace = empty( $keep_id );
	}
	if ( $dry_run && ! $resuming ) {
		$preview = array(
			'signature'     => $signature,
			'keep_id'       => $keep_id,
			'replace'       => $replace,
			'replaced'      => false,
			'delete_cursor' => 0,
			'changed'       => false
		);
	}

	if ( ! $dry_run ) {
		wp_cache_delete( $object_id, $meta_type . '_meta' );
	}
	if ( ( null !== $value ) && $replace && ( ! $dry_run || empty( $preview['replaced'] ) ) ) {
		// Keep the oldest exact-key source intact; reuse only a later exact-key row
		$ids = $bbp_db->get_col( $bbp_db->prepare( "SELECT {$id_column} FROM {$table} WHERE {$object_key} = %d AND BINARY meta_key = %s ORDER BY {$id_column} LIMIT 2", $object_id, $key ) );
		if ( ! empty( $bbp_db->last_error ) ) {
			$result['code'] = 2;
			return $result;
		}
		$keep_id = count( $ids ) > 1 ? (int) $ids[1] : 0;
		if ( $dry_run ) {
			$preview['keep_id']  = $keep_id;
			$preview['replaced'] = true;
		}
		if ( ! $dry_run ) {
			if ( $keep_id ) {
				$row = (object) array(
					$id_column   => $keep_id,
					$object_key  => $object_id,
					'meta_key'   => $key,
					'meta_value' => null
				);
				$code = bbp_admin_repair_metadata_row( $meta_type, $row, $key, $value );
				$result['code'] = 1 === $code ? 2 : $code;
			} else {
				$added          = bbp_admin_repair_metadata_add( $meta_type, $object_id, $key, $value );
				$result['code'] = $added['code'];
				$keep_id        = $added['meta_id'];
			}
			if ( $result['code'] ) {
				return $result;
			}
		}
		++$result['work'];
		++$result['operations'];
		$result['changed'] = true;
	}

	$where = $bbp_db->prepare( "WHERE {$object_key} = %d AND meta_key = %s AND {$id_column} <> %d", $object_id, $key, $keep_id );
	if ( $dry_run && ! empty( $preview['delete_cursor'] ) ) {
		$where .= $bbp_db->prepare( " AND {$id_column} < %d", $preview['delete_cursor'] );
	}
	$remaining = max( 0, $limit - $result['work'] );
	$extra_ids = $bbp_db->get_col( "SELECT {$id_column} FROM {$table} {$where}" . $bbp_db->prepare( " ORDER BY {$id_column} DESC LIMIT %d", $remaining + 1 ) );
	if ( ! empty( $bbp_db->last_error ) ) {
		$result['code'] = 2;
		return $result;
	}
	foreach ( array_slice( $extra_ids, 0, $remaining ) as $meta_id ) {
		if ( ! $dry_run ) {
			$row = (object) array(
				$id_column  => (int) $meta_id,
				$object_key => $object_id
			);
			$code = bbp_admin_repair_metadata_row( $meta_type, $row, $key, null );
			if ( $code ) {
				$result['code'] = 1 === $code ? 2 : 3;
				return $result;
			}
		}
		++$result['work'];
		++$result['operations'];
		$result['changed'] = true;
		if ( $dry_run ) {
			$preview['delete_cursor'] = (int) $meta_id;
		}
	}
	$result['work']     = max( 1, $result['work'] );
	$result['complete'] = count( $extra_ids ) <= $remaining;
	if ( $dry_run ) {
		$preview['changed'] = $preview['changed'] || $result['changed'];
		$result['changed']  = $result['complete'] && $preview['changed'];
	} else {
		$result['changed'] = $result['complete'] && $result['changed'];
	}

	return $result;
}

/**
 * Reconcile a bounded selection of source and stored engagement rows.
 *
 * Keep the earliest canonical relationship and share duplicate suppression with
 * previews. Each selected row can add or remove at most one metadata row.
 * A failed insertion can also use one compensating delete before returning.
 *
 * @since 2.7.0 bbPress (r8281)
 *
 * @param array  $rows    Selected metadata rows, including is_source flags.
 * @param string $source Prepared source relationship query.
 * @param bool   $dry_run Whether to preview without writes or cache invalidation.
 * @param array         $planned Relationships already counted by a synchronous preview.
 * @param callable|null $claim   Optional bounded cross-request relationship claim.
 * @return array Status, failure reason, additions, removals, changed topic IDs, and cursor.
 */
function bbp_admin_repair_engagement_rows( $rows, $source, $dry_run = false, &$planned = array(), $claim = null ) {
	$bbp_db = bbp_db();
	$result = array(
		'code'    => 0,
		'error'   => '',
		'added'   => 0,
		'removed' => 0,
		'topics'  => array(),
		'cursor'  => 0
	);
	foreach ( $rows as $row ) {
		$is_source = (bool) $row->is_source;
		$where     = $is_source
			? $bbp_db->prepare( ' AND postmeta.meta_id = %d', $row->meta_id )
			: $bbp_db->prepare( ' AND topics.ID = %d AND posts.post_author = %d', $row->post_id, $row->meta_value );
		$relation = $bbp_db->get_row( $source . $where . ' LIMIT 1' );
		if ( ! empty( $bbp_db->last_error ) ) {
			$result['code'] = 3;
			$result['error'] = 'source-read';
			return $result;
		}
		$exists = null;
		if ( ! empty( $relation ) ) {
			$exists = $bbp_db->get_var( $bbp_db->prepare( "SELECT meta_id FROM {$bbp_db->postmeta} WHERE post_id = %d AND BINARY meta_key = '_bbp_engagement' AND BINARY meta_value = %s ORDER BY meta_id LIMIT 1", $relation->post_id, $relation->user_id ) );
			if ( ! empty( $bbp_db->last_error ) ) {
				$result['code'] = 3;
				$result['error'] = 'metadata-read';
				return $result;
			}
		}
		if ( ! $dry_run ) {
			$cache_id = $is_source && ! empty( $relation ) ? $relation->post_id : $row->post_id;
			wp_cache_delete( $cache_id, 'post_meta' );
		}
		if ( $is_source ) {
			$first = ! empty( $relation ) && empty( $exists ) && ! isset( $planned[ $relation->post_id ][ $relation->user_id ] );
			if ( $first && is_callable( $claim ) ) {
				list( $saved, $first ) = call_user_func( $claim, (int) $relation->post_id, (int) $relation->user_id );
				if ( ! $saved ) {
					$result['code']  = 6;
					$result['error'] = 'progress';
					return $result;
				}
			}
			if ( $first ) {
				if ( ! $dry_run ) {
					$added = bbp_admin_repair_metadata_add( 'post', $relation->post_id, '_bbp_engagement', $relation->user_id );
					if ( $added['code'] ) {
						$result['code'] = 5;
						$result['error'] = 'metadata-write';
						return $result;
					}
				}
				if ( ! is_callable( $claim ) ) {
					$planned[ $relation->post_id ][ $relation->user_id ] = true;
				}
				$result['topics'][ (int) $relation->post_id ]        = true;
				++$result['added'];
			}
		} elseif ( (int) $exists !== (int) $row->meta_id ) {
			if ( ! $dry_run ) {
				if ( bbp_admin_repair_metadata_row( 'post', $row, '_bbp_engagement', null ) ) {
					$result['code'] = 4;
					$result['error'] = 'metadata-write';
					return $result;
				}
			}
			$result['topics'][ (int) $row->post_id ] = true;
			++$result['removed'];
		}
		$result['cursor'] = (int) $row->meta_id;
	}
	return $result;
}

/**
 * Read current repair option data, optionally preserving WordPress option filters.
 *
 * @since 2.7.0 bbPress (r8303)
 *
 * @param string $option_name    Option name.
 * @param mixed  $option_default Default value.
 * @param bool   $filtered       Whether to apply the native option filters.
 * @return array Read success and option value.
 */
function bbp_admin_repair_get_option( $option_name, $option_default = false, $filtered = true ) {
	if ( $filtered ) {
		$value = apply_filters( "pre_option_{$option_name}", false, $option_name, $option_default );
		if ( version_compare( get_bloginfo( 'version' ), '6.1', '>=' ) ) {
			$value = apply_filters( 'pre_option', $value, $option_name, $option_default );
		}
		if ( false !== $value ) {
			return array( true, $value );
		}
	}
	list( $read, $value, $exists ) = bbp_get_raw_option( $option_name, $option_default );
	if ( ! $read || ! $filtered ) {
		return array( $read, $value );
	}
	$value = $exists
		? apply_filters( "option_{$option_name}", $value, $option_name )
		: apply_filters( "default_option_{$option_name}", $option_default, $option_name, true );
	return array( true, $value );
}

/**
 * Verify an option against its unfiltered, uncached stored value.
 *
 * @since 2.7.0 bbPress (r8304)
 *
 * @param string $option Option name.
 * @param mixed  $value  Expected value, or null when no row should remain.
 * @return bool
 */
function bbp_admin_repair_option_matches( $option, $value ) {
	$db     = bbp_db();
	$column = null === $value ? 'option_id' : 'option_value';
	$row    = $db->get_row( $db->prepare( "SELECT `{$column}` FROM `{$db->options}` WHERE `option_name` = %s LIMIT 1", $option ) );
	if ( ! empty( $db->last_error ) ) {
		return false;
	}
	return null === $value ? ! $row : is_object( $row ) && (string) maybe_serialize( $value ) === $row->option_value;
}

/**
 * Update a repair option through WordPress and verify the stored result.
 *
 * Preserve existing autoload settings and option hooks.
 *
 * @since 2.7.0 bbPress (r8304)
 *
 * @param string $option Option name.
 * @param mixed  $value  Expected value.
 * @return bool
 */
function bbp_admin_repair_update_option( $option, $value ) {
	wp_cache_delete_multiple( array( $option, 'alloptions', 'notoptions' ), 'options' );
	update_option( $option, $value );
	$saved = bbp_admin_repair_option_matches( $option, $value );
	if ( ! $saved ) {
		wp_cache_delete_multiple( array( $option, 'alloptions', 'notoptions' ), 'options' );
	}
	return $saved;
}

/**
 * Return the option name for a built-in tool's next batch checkpoint.
 *
 * @since 2.7.0 bbPress (r8253)
 *
 * @param string $tool_id Repair or upgrade tool ID.
 * @return string
 */
function bbp_admin_repair_tool_batch_option_name( $tool_id = '' ) {
	$option  = '_bbp_tool_batch_' . md5( sanitize_key( $tool_id ) );
	$context = bbp_admin_repair_tool_execution_context();

	if ( 'dry-run' === $context['action'] && ! empty( $context['run_id'] ) ) {
		$option .= '_preview_' . md5( $context['run_id'] );
	}

	return $option;
}

/**
 * Create an opaque generation token for bounded tool scratch state.
 *
 * @since 2.7.0 bbPress (r8359)
 * @access private
 *
 * @return string Thirty-two-character hexadecimal token.
 */
function bbp_admin_repair_tool_new_token() {
	return str_replace( '-', '', wp_generate_uuid4() );
}

/**
 * Check one checkpoint for a valid scratch generation token.
 *
 * @since 2.7.0 bbPress (r8359)
 * @access private
 *
 * @param mixed $state Tool checkpoint.
 * @return bool Whether the checkpoint owns a valid scratch generation.
 */
function bbp_admin_repair_tool_has_valid_scratch_token( $state ) {
	return is_array( $state ) && isset( $state['token'] ) && is_string( $state['token'] )
		&& 32 === strlen( $state['token'] ) && ctype_xdigit( $state['token'] );
}

/**
 * Return the fixed-size namespace for one tool's scratch state.
 *
 * Hash the complete checkpoint option name so browser Dry Runs remain isolated
 * without approaching WordPress's 191-character option-name limit.
 *
 * @since 2.7.0 bbPress (r8359)
 * @access private
 *
 * @param string $tool_id Repair or upgrade tool ID.
 * @return string Scratch option namespace.
 */
function bbp_admin_repair_tool_scratch_namespace( $tool_id ) {
	$tool_id = sanitize_key( $tool_id );
	if ( empty( $tool_id ) ) {
		return '';
	}

	return '_bbp_tool_scratch_' . md5( bbp_admin_repair_tool_batch_option_name( $tool_id ) ) . '_';
}

/**
 * Return the option prefix for one tool scratch generation.
 *
 * Scratch entries keep large cross-batch sets out of the main checkpoint.
 * Every entry remains independently readable, writable, and cleanable within
 * the normal batch budget.
 *
 * @since 2.7.0 bbPress (r8359)
 * @access private
 *
 * @param string $tool_id   Repair or upgrade tool ID.
 * @param string $token     Scratch generation token.
 * @param string $collection Logical scratch collection.
 * @return string Scratch option prefix, or an empty string for invalid input.
 */
function bbp_admin_repair_tool_scratch_prefix( $tool_id, $token, $collection ) {
	$tool_id   = sanitize_key( $tool_id );
	$token     = sanitize_key( $token );
	$collection = (string) $collection;
	if ( empty( $tool_id ) || 32 !== strlen( $token ) || ! ctype_xdigit( $token ) || '' === $collection ) {
		return '';
	}
	return bbp_admin_repair_tool_scratch_namespace( $tool_id ) . $token . '_' . md5( $collection ) . '_';
}

/**
 * Return the option name for one keyed scratch value.
 *
 * @since 2.7.0 bbPress (r8359)
 * @access private
 *
 * @param string $tool_id   Repair or upgrade tool ID.
 * @param string $token     Scratch generation token.
 * @param string $collection Logical scratch collection.
 * @param mixed  $key       Stable value key.
 * @return string Scratch option name, or an empty string for invalid input.
 */
function bbp_admin_repair_tool_scratch_option_name( $tool_id, $token, $collection, $key ) {
	$prefix = bbp_admin_repair_tool_scratch_prefix( $tool_id, $token, $collection );
	return $prefix ? $prefix . md5( maybe_serialize( $key ) ) : '';
}

/**
 * Read one keyed scratch value without option view filters.
 *
 * @since 2.7.0 bbPress (r8359)
 * @access private
 *
 * @param string $tool_id   Repair or upgrade tool ID.
 * @param string $token     Scratch generation token.
 * @param string $collection Logical scratch collection.
 * @param mixed  $key       Stable value key.
 * @return array Read success, existence, and stored value.
 */
function bbp_admin_repair_tool_scratch_get( $tool_id, $token, $collection, $key ) {
	$option = bbp_admin_repair_tool_scratch_option_name( $tool_id, $token, $collection, $key );
	if ( empty( $option ) ) {
		return array( false, false, null );
	}
	list( $read, $value ) = bbp_admin_repair_get_option( $option, null, false );
	return array( $read, $read && null !== $value, $value );
}

/**
 * Save one keyed scratch value and verify it through the shared option writer.
 *
 * @since 2.7.0 bbPress (r8359)
 * @access private
 *
 * @param string $tool_id   Repair or upgrade tool ID.
 * @param string $token     Scratch generation token.
 * @param string $collection Logical scratch collection.
 * @param mixed  $key       Stable value key.
 * @param mixed  $value     Non-null value to store.
 * @return bool Whether the exact value was saved.
 */
function bbp_admin_repair_tool_scratch_set( $tool_id, $token, $collection, $key, $value ) {
	$option = bbp_admin_repair_tool_scratch_option_name( $tool_id, $token, $collection, $key );
	return ! empty( $option ) && null !== $value && bbp_admin_repair_tool_save_option( $option, $value );
}

/**
 * Claim one keyed scratch marker.
 *
 * @since 2.7.0 bbPress (r8359)
 * @access private
 *
 * @param string $tool_id   Repair or upgrade tool ID.
 * @param string $token     Scratch generation token.
 * @param string $collection Logical scratch collection.
 * @param mixed  $key       Stable value key.
 * @return array Save success and whether this was the first claim.
 */
function bbp_admin_repair_tool_scratch_claim( $tool_id, $token, $collection, $key ) {
	// Individual option names provide indexed, cross-batch deduplication without
	// growing the main checkpoint into one unbounded serialized value.
	list( $read, $exists ) = bbp_admin_repair_tool_scratch_get( $tool_id, $token, $collection, $key );
	if ( ! $read ) {
		return array( false, false );
	}
	if ( $exists ) {
		return array( true, false );
	}
	return array( bbp_admin_repair_tool_scratch_set( $tool_id, $token, $collection, $key, true ), true );
}

/**
 * Remove one bounded page of a tool's scratch generation.
 *
 * @since 2.7.0 bbPress (r8359)
 * @access private
 *
 * @param string $tool_id Repair or upgrade tool ID.
 * @param int    $limit   Maximum options to inspect.
 * @return array Cleanup success, work used, and completion flag.
 */
function bbp_admin_repair_tool_cleanup_scratch( $tool_id, $limit ) {
	$created   = array();
	$inspected = array();
	$prefix    = bbp_admin_repair_tool_scratch_namespace( $tool_id );
	if ( empty( $prefix ) ) {
		return array( false, 1, false );
	}
	return bbp_admin_repair_tool_cleanup_options( $prefix, $limit, false, $created, $inspected );
}

/**
 * Check whether the current tool namespace contains scratch state.
 *
 * @since 2.7.0 bbPress (r8359)
 * @access private
 *
 * @param string $tool_id Repair or upgrade tool ID.
 * @return bool|null Whether scratch state exists, or null after a read failure.
 */
function bbp_admin_repair_tool_has_scratch( $tool_id ) {
	$db     = bbp_db();
	$prefix = bbp_admin_repair_tool_scratch_namespace( $tool_id );
	if ( empty( $prefix ) ) {
		return null;
	}
	$name   = $db->get_var( $db->prepare( "SELECT option_name FROM {$db->options} WHERE option_name LIKE %s LIMIT 1", $db->esc_like( $prefix ) . '%' ) );

	return empty( $db->last_error ) ? null !== $name : null;
}

/**
 * Read every bounded chunk in one scratch collection for final publication.
 *
 * Workers keep each chunk within their normal batch limit. Materializing the
 * collection is reserved for the point where the established destination API
 * itself requires one complete value.
 *
 * @since 2.7.0 bbPress (r8359)
 * @access private
 *
 * @param string $tool_id    Repair or upgrade tool ID.
 * @param string $token      Scratch generation token.
 * @param string $collection Logical scratch collection.
 * @return array|false Stored chunks, or false after a read failure.
 */
function bbp_admin_repair_tool_scratch_values( $tool_id, $token, $collection ) {
	$db     = bbp_db();
	$prefix = bbp_admin_repair_tool_scratch_prefix( $tool_id, $token, $collection );
	if ( empty( $prefix ) ) {
		return false;
	}
	$values = $db->get_col( $db->prepare( "SELECT option_value FROM {$db->options} WHERE option_name LIKE %s ORDER BY option_name", $db->esc_like( $prefix ) . '%' ) );
	if ( ! empty( $db->last_error ) ) {
		return false;
	}

	return array_map( 'maybe_unserialize', $values );
}

/**
 * Materialize ordered arrays from one bounded scratch-chunk collection.
 *
 * @since 2.7.0 bbPress (r8359)
 * @access private
 *
 * @param string $tool_id    Repair or upgrade tool ID.
 * @param string $token      Scratch generation token.
 * @param string $collection Logical scratch collection.
 * @param string $field      Chunk field containing ordered values.
 * @param int    $end        Expected exclusive end offset.
 * @return array|false Ordered values, or false on invalid scratch data.
 */
function bbp_admin_repair_tool_scratch_chunks( $tool_id, $token, $collection, $field, $end ) {
	$chunks = bbp_admin_repair_tool_scratch_values( $tool_id, $token, $collection );
	$end    = (int) $end;
	if ( false === $chunks || $end < 0 ) {
		return false;
	}
	foreach ( $chunks as $chunk ) {
		if ( ! is_array( $chunk ) || array_keys( $chunk ) !== array( 'offset', 'next', $field )
			|| ! is_int( $chunk['offset'] ) || ! is_int( $chunk['next'] ) || $chunk['offset'] < 0
			|| $chunk['next'] <= $chunk['offset'] || ! is_array( $chunk[ $field ] ) ) {
			return false;
		}
	}
	usort(
		$chunks,
		static function ( $left, $right ) {
			return $left['offset'] <=> $right['offset'];
		}
	);
	$values = array();
	$next   = 0;
	foreach ( $chunks as $chunk ) {
		if ( $chunk['offset'] !== $next ) {
			return false;
		}
		foreach ( $chunk[ $field ] as $value ) {
			$values[] = $value;
		}
		$next = $chunk['next'];
	}

	return $next === $end ? $values : false;
}

/**
 * Restart one invalid scratch collection without losing later source rows.
 *
 * One restart repairs a missing or malformed chunk. A repeated invalid
 * collection reports a progress failure instead of looping indefinitely.
 *
 * @since 2.7.0 bbPress (r8359)
 * @access private
 *
 * @param array $state Saved worker checkpoint, passed by reference.
 * @param array $batch Batch status, passed by reference.
 * @param array $reset Worker fields to reset for the current source row.
 * @return bool Whether the collection was restarted.
 */
function bbp_admin_repair_tool_restart_scratch( &$state, &$batch, $reset ) {
	if ( ! empty( bbp_db()->last_error ) || ! empty( $state['scratch_retry'] ) ) {
		bbp_admin_repair_tool_batch_fail( $batch, 'progress' );
		return false;
	}

	$state                  = array_merge( $state, $reset );
	$state['token']         = bbp_admin_repair_tool_new_token();
	$state['scratch_retry'] = true;
	$batch['restarted']     = true;
	return true;
}

/**
 * Get or set the current repair-tool execution context.
 *
 * Direct callback calls retain the historical synchronous behavior. The
 * browser runner sets a run owner so Dry Run checkpoints use an isolated
 * namespace across its bounded requests.
 *
 * @since 2.7.0 bbPress (r8331)
 * @access private
 *
 * @param string|null $action  Tool action, or null to read the context.
 * @param string      $run_id  Browser-run owner.
 * @param bool        $started Whether the current tool has processed a batch.
 * @return array Current action, run owner, and tool state.
 */
function bbp_admin_repair_tool_execution_context( $action = null, $run_id = '', $started = false ) {
	static $context = array(
		'action'  => 'run',
		'run_id'  => '',
		'started' => false,
	);

	if ( null !== $action ) {
		$action  = sanitize_key( $action );
		$context = array(
			'action'  => in_array( $action, array( 'run', 'dry-run' ), true ) ? $action : 'run',
			'run_id'  => sanitize_text_field( $run_id ),
			'started' => (bool) $started,
		);
	}

	return $context;
}

/**
 * Return whether this callback belongs to a newly started browser Run.
 *
 * @since 2.7.0 bbPress (r8331)
 * @access private
 *
 * @return bool
 */
function bbp_admin_repair_tool_is_new_browser_run() {
	$context = bbp_admin_repair_tool_execution_context();

	return 'run' === $context['action'] && ! empty( $context['run_id'] ) && empty( $context['started'] );
}

/**
 * Return whether this request is one bounded browser Dry Run batch.
 *
 * @since 2.7.0 bbPress (r8331)
 * @access private
 *
 * @return bool
 */
function bbp_admin_repair_tool_is_async_dry_run() {
	$context = bbp_admin_repair_tool_execution_context();

	return 'dry-run' === $context['action'] && ! empty( $context['run_id'] );
}

/**
 * Create the common result fields for one procedural worker batch.
 *
 * @since 2.7.0 bbPress (r8339)
 * @access private
 *
 * @param array $fields Worker-specific result fields.
 * @return array Complete initial batch result.
 */
function bbp_admin_repair_tool_batch_status( $fields = array() ) {
	return array_merge(
		BBP_Admin_Batch_Worker::result_fields(),
		array(
			'restarted' => false,
			'batches'   => 0,
		),
		$fields
	);
}

/**
 * Record the first failure encountered by a procedural worker.
 *
 * @since 2.7.0 bbPress (r8339)
 * @access private
 *
 * @param array  $batch  Current worker result.
 * @param string $reason Semantic failure reason.
 * @return false
 */
function bbp_admin_repair_tool_batch_fail( &$batch, $reason ) {
	$error = isset( $batch['error'] ) ? $batch['error'] : '';
	BBP_Admin_Batch_Worker::record_failure( $error, $reason );
	$batch = array_merge( $batch, BBP_Admin_Batch_Worker::result_fields( $error, false ) );

	return false;
}

/**
 * Load one procedural worker checkpoint into its shared batch result.
 *
 * @since 2.7.0 bbPress (r8339)
 * @access private
 *
 * @param string   $tool_id   Repair or upgrade tool ID.
 * @param array    $state     Loaded checkpoint, or false for a new operation.
 * @param array    $batch     Current worker result.
 * @param callable $validator Checkpoint validator.
 * @param mixed    $context   Validator context.
 * @param bool     $dry_run   Whether this is a read-only preview.
 * @return bool Whether the checkpoint was read successfully.
 */
function bbp_admin_repair_tool_batch_start( $tool_id, &$state, &$batch, $validator = 'bbp_admin_repair_tool_is_valid_checkpoint', $context = array(), $dry_run = false ) {
	list( $read, $state, $batch['restarted'] ) = bbp_admin_repair_tool_load_checkpoint( $tool_id, $validator, $context, $dry_run );
	if ( ! $read ) {
		return bbp_admin_repair_tool_batch_fail( $batch, 'progress' );
	}

	return true;
}

/**
 * Save a procedural worker checkpoint and its aggregate Dry Run state.
 *
 * @since 2.7.0 bbPress (r8339)
 * @access private
 *
 * @param string $tool_id Repair or upgrade tool ID.
 * @param array  $state   Current worker checkpoint.
 * @param array  $batch   Current worker result.
 * @param bool   $complete Whether this operation completed.
 * @param bool   $dry_run Whether this is a read-only preview.
 * @param array  $fields  Aggregate result fields to save.
 * @param array  $data    Worker-local simulation data.
 * @return bool Whether the checkpoint was saved or removed.
 */
function bbp_admin_repair_tool_batch_save( $tool_id, &$state, &$batch, $complete, $dry_run = false, $fields = array(), $data = array() ) {
	if ( ! bbp_admin_repair_tool_refresh_lock() ) {
		return bbp_admin_repair_tool_batch_fail( $batch, 'progress' );
	}
	if ( ! empty( $fields ) || ! empty( $data ) ) {
		bbp_admin_repair_tool_store_preview( $state, $batch, $fields, $data );
	}
	if ( ! bbp_admin_repair_tool_save_checkpoint( $tool_id, $state, $complete, $dry_run ) ) {
		return bbp_admin_repair_tool_batch_fail( $batch, 'progress' );
	}
	$batch['complete'] = (bool) $complete;

	return true;
}

/**
 * Return whether a procedural Dry Run should process another virtual batch.
 *
 * @since 2.7.0 bbPress (r8339)
 * @access private
 *
 * @param array $batch   Current worker result.
 * @param bool  $dry_run Whether this is a read-only preview.
 * @return bool
 */
function bbp_admin_repair_tool_batch_continue( $batch, $dry_run = false ) {
	return $dry_run && ! bbp_admin_repair_tool_is_async_dry_run() && empty( $batch['code'] ) && empty( $batch['complete'] );
}

/**
 * Restore aggregate counters and worker-local simulation data for a browser Dry Run.
 *
 * @since 2.7.0 bbPress (r8331)
 * @access private
 *
 * @param array $state  Saved worker checkpoint.
 * @param array $batch  Current worker result.
 * @param array $fields Aggregate result fields to restore.
 * @return array Worker-local simulation data.
 */
function bbp_admin_repair_tool_restore_preview( $state, &$batch, $fields = array() ) {
	if ( ! bbp_admin_repair_tool_is_async_dry_run() || empty( $state['_preview_state'] )
		|| ! is_array( $state['_preview_state'] )
		|| ! isset( $state['_preview_state']['totals'], $state['_preview_state']['data'] )
		|| ! is_array( $state['_preview_state']['totals'] ) || ! is_array( $state['_preview_state']['data'] ) ) {
		return array();
	}

	$preview = $state['_preview_state'];
	foreach ( $fields as $field ) {
		if ( array_key_exists( $field, $preview['totals'] ) && array_key_exists( $field, $batch ) ) {
			$batch[ $field ] = $preview['totals'][ $field ];
		}
	}

	return $preview['data'];
}

/**
 * Add aggregate counters and worker-local simulation data to a browser Dry Run checkpoint.
 *
 * @since 2.7.0 bbPress (r8331)
 * @access private
 *
 * @param array $state  Worker checkpoint to update.
 * @param array $batch  Current worker result.
 * @param array $fields Aggregate result fields to save.
 * @param array $data   Worker-local simulation data.
 */
function bbp_admin_repair_tool_store_preview( &$state, $batch, $fields = array(), $data = array() ) {
	if ( ! bbp_admin_repair_tool_is_async_dry_run() ) {
		return;
	}

	$totals = array();
	foreach ( $fields as $field ) {
		if ( array_key_exists( $field, $batch ) ) {
			$totals[ $field ] = $batch[ $field ];
		}
	}

	$state['_preview_state'] = array(
		'totals' => $totals,
		'data'   => $data,
	);
}

/**
 * Read a checkpoint without mistaking cached absence or a query failure for a new run.
 *
 * @since 2.7.0 bbPress (r8315)
 *
 * @param string $tool_id Repair or upgrade tool ID.
 * @param bool   $dry_run  Whether to start a fresh, read-only preview.
 * @return array Read success and checkpoint, or false when no checkpoint exists.
 */
function bbp_admin_repair_tool_get_checkpoint( $tool_id, $dry_run = false ) {
	if ( $dry_run && ! bbp_admin_repair_tool_is_async_dry_run() ) {
		return array( true, false );
	}
	return bbp_admin_repair_get_option( bbp_admin_repair_tool_batch_option_name( $tool_id ) );
}

/**
 * Load one tool's checkpoint and identify progress that needs restarting.
 *
 * Each tool keeps its own namespace. Workers supply their existing validator
 * and context, then choose their own upper bounds and work. This helper neither
 * acquires a lock nor writes state. A full preview starts independently of saved Run progress.
 *
 * @since 2.7.0 bbPress (r8315)
 *
 * @param string   $tool_id   Repair or upgrade tool ID.
 * @param callable $validator Checkpoint validator, accepting state and context.
 * @param mixed    $context   Validator context or extra integer field names.
 * @param bool     $dry_run   Whether to start a fresh, read-only preview.
 * @return array Read success, usable checkpoint or false, and restart flag.
 */
function bbp_admin_repair_tool_load_checkpoint( $tool_id, $validator = 'bbp_admin_repair_tool_is_valid_checkpoint', $context = array(), $dry_run = false ) {
	if ( ! is_callable( $validator ) ) {
		return array( false, false, false );
	}
	list( $read, $state ) = bbp_admin_repair_tool_get_checkpoint( $tool_id, $dry_run );
	$preview_valid = bbp_admin_repair_tool_is_valid_preview_state( $state );
	$restarted = $read && false !== $state && ( ! $preview_valid || ! call_user_func( $validator, $state, $context ) );
	return array( $read, $restarted ? false : $state, $restarted );
}

/**
 * Validate the typed aggregate fields saved by a browser Dry Run.
 *
 * Workers name the integer, boolean, and array fields that belong to their
 * preview contract. Direct Runs and checkpoints created before a preview have
 * no aggregate state and remain valid.
 *
 * @since 2.7.0 bbPress (r8347)
 * @access private
 *
 * @param mixed $state          Saved worker checkpoint.
 * @param array $integer_fields Nonnegative integer total fields.
 * @param array $boolean_fields Boolean total fields.
 * @param array $array_fields   Array data fields.
 * @return bool Whether the aggregate preview state is valid.
 */
function bbp_admin_repair_tool_is_valid_preview_state( $state, $integer_fields = array(), $boolean_fields = array(), $array_fields = array() ) {
	if ( ! isset( $state['_preview_state'] ) ) {
		return true;
	}

	$preview = $state['_preview_state'];
	if ( ! is_array( $preview ) || ! isset( $preview['totals'], $preview['data'] )
		|| ! is_array( $preview['totals'] ) || ! is_array( $preview['data'] ) ) {
		return false;
	}

	foreach ( $integer_fields as $field ) {
		if ( ! isset( $preview['totals'][ $field ] ) || ! is_int( $preview['totals'][ $field ] ) || $preview['totals'][ $field ] < 0 ) {
			return false;
		}
	}
	foreach ( $boolean_fields as $field ) {
		if ( ! isset( $preview['totals'][ $field ] ) || ! is_bool( $preview['totals'][ $field ] ) ) {
			return false;
		}
	}
	foreach ( $array_fields as $field ) {
		if ( ! isset( $preview['data'][ $field ] ) || ! is_array( $preview['data'][ $field ] ) ) {
			return false;
		}
	}

	return true;
}

/**
 * Validate simple checkpoint bounds and the worker's existing identity fields.
 *
 * @since 2.7.0 bbPress (r8315)
 * @access private
 *
 * @param mixed $state  Saved checkpoint.
 * @param array $fields Expected identity fields, without changing their layout.
 * @return bool
 */
function bbp_admin_repair_tool_is_matching_checkpoint( $state, $fields ) {
	if ( ! bbp_admin_repair_tool_is_valid_checkpoint( $state ) ) {
		return false;
	}
	foreach ( $fields as $key => $value ) {
		if ( ! isset( $state[ $key ] ) || $value !== $state[ $key ] ) {
			return false;
		}
	}
	return true;
}

/**
 * Check the integer fields and bounds of a saved tool checkpoint.
 *
 * @since 2.7.0 bbPress (r8254)
 *
 * @param mixed $state        Saved checkpoint.
 * @param array $extra_fields Additional nonnegative integer fields to check.
 * @return bool
 */
function bbp_admin_repair_tool_is_valid_checkpoint( $state, $extra_fields = array() ) {
	if ( ! is_array( $state ) ) {
		return false;
	}

	foreach ( array_merge( array( 'cursor', 'upper_id' ), $extra_fields ) as $field ) {
		if ( ! isset( $state[ $field ] ) || ! is_int( $state[ $field ] ) || ( $state[ $field ] < 0 ) ) {
			return false;
		}
	}

	return $state['cursor'] <= $state['upper_id'];
}

/**
 * Validate the shared cursor within one legacy user-meta list.
 *
 * @since 2.7.0 bbPress (r8347)
 * @access private
 *
 * @param mixed $state           Saved worker checkpoint.
 * @param bool  $allow_row_start Whether a parsed row may wait at byte zero for publication.
 * @return bool Whether the source row and list offset can be resumed.
 */
function bbp_admin_repair_tool_is_valid_user_meta_checkpoint( $state, $allow_row_start = false ) {
	if ( ! bbp_admin_repair_tool_is_valid_checkpoint( $state, array( 'offset' ) )
		|| ! isset( $state['row_hash'] ) || ! is_string( $state['row_hash'] ) ) {
		return false;
	}

	if ( 0 === $state['offset'] ) {
		return '' === $state['row_hash'] || ( $allow_row_start && 0 !== $state['cursor'] && 1 === preg_match( '/^[a-f0-9]{32}$/D', $state['row_hash'] ) );
	}

	return 0 !== $state['cursor'] && 1 === preg_match( '/^[a-f0-9]{32}$/D', $state['row_hash'] );
}

/**
 * Parse one bounded token window from a legacy user-meta ID list.
 *
 * Migration values historically accept commas or whitespace and normalize
 * tokens with absint(). The byte cursor avoids materializing the complete list
 * before the worker can apply its batch limit. Duplicate and zero IDs remain
 * visible to the caller so every source token consumes bounded work.
 *
 * @since 2.7.0 bbPress (r8359)
 * @access private
 *
 * @param string|null $value  Stored list.
 * @param int         $offset Inclusive byte offset.
 * @param int         $limit  Maximum source tokens to inspect.
 * @return array Parsed positive IDs, next byte offset, work used, and completion flag.
 */
function bbp_admin_repair_user_meta_id_batch( $value, $offset, $limit ) {
	$value      = (string) $value;
	$length     = strlen( $value );
	$position   = max( 0, min( (int) $offset, $length ) );
	$limit      = max( 1, (int) $limit );
	$delimiters = " \t\n\r\f\0\x0B,";
	$ids        = array();
	$work       = 0;

	while ( $position < $length && $work < $limit ) {
		$position += strspn( $value, $delimiters, $position );
		if ( $position >= $length ) {
			break;
		}
		$token_length = strcspn( $value, $delimiters, $position );
		$id           = absint( substr( $value, $position, $token_length ) );
		$position    += $token_length;
		++$work;
		if ( $id ) {
			$ids[] = $id;
		}
	}
	if ( $position < $length ) {
		$position += strspn( $value, $delimiters, $position );
	}

	return array(
		'ids'      => $ids,
		'offset'   => $position,
		'work'     => $work,
		'complete' => $position >= $length,
	);
}

/**
 * Parse one bounded token window from a canonical legacy user-meta ID list.
 *
 * Repair values historically retain only comma-delimited positive decimal
 * tokens without whitespace, signs, or leading zeroes. Duplicate tokens stay
 * visible so each source entry consumes bounded work; callers de-duplicate the
 * surviving IDs before publication.
 *
 * @since 2.7.0 bbPress (r8359)
 * @access private
 *
 * @param string|null $value  Stored list.
 * @param int         $offset Inclusive byte offset.
 * @param int         $limit  Maximum source tokens to inspect.
 * @return array Parsed positive IDs, next byte offset, work used, and completion flag.
 */
function bbp_admin_repair_user_meta_canonical_id_batch( $value, $offset, $limit ) {
	$value    = (string) $value;
	$length   = strlen( $value );
	$position = max( 0, min( (int) $offset, $length ) );
	$limit    = max( 1, (int) $limit );
	$ids      = array();
	$work     = 0;

	while ( $position < $length && $work < $limit ) {
		$token_length = strcspn( $value, ',', $position );
		$token        = substr( $value, $position, $token_length );
		$id           = absint( $token );
		$position    += $token_length;
		if ( $position < $length ) {
			++$position;
		}
		++$work;
		if ( $id && (string) $id === $token ) {
			$ids[] = $id;
		}
	}

	return array(
		'ids'      => $ids,
		'offset'   => $position,
		'work'     => $work,
		'complete' => $position >= $length,
	);
}

/**
 * Create the stable fingerprint for one legacy user-meta source row.
 *
 * Repairs include the stored key because they canonicalize key variants.
 * Migrations retain their historical user-and-value fingerprint.
 *
 * @since 2.7.0 bbPress (r8347)
 * @access private
 *
 * @param object $row         User-meta source row.
 * @param bool   $include_key Whether to include the stored metadata key.
 * @return string MD5 row fingerprint.
 */
function bbp_admin_repair_user_meta_row_hash( $row, $include_key = false ) {
	$value = (int) $row->user_id . ':';
	if ( $include_key ) {
		$value .= $row->meta_key . ':';
	}

	return md5( $value . $row->meta_value );
}

/**
 * Read the fixed upper source-row bound for one legacy user-meta key.
 *
 * @since 2.7.0 bbPress (r8347)
 * @access private
 *
 * @param string $key   Legacy user-meta key.
 * @param array  $batch Current worker result.
 * @return int|false Upper metadata ID, or false on a read failure.
 */
function bbp_admin_repair_user_meta_upper_id( $key, &$batch ) {
	$db       = bbp_db();
	$upper_id = $db->get_var( $db->prepare( "SELECT MAX(umeta_id) FROM {$db->usermeta} WHERE meta_key = %s", $key ) );
	if ( ! empty( $db->last_error ) ) {
		bbp_admin_repair_tool_batch_fail( $batch, 'metadata-read' );
		return false;
	}

	return (int) $upper_id;
}

/**
 * Read the next legacy user-meta source row within fixed bounds.
 *
 * A false return is a read failure recorded in the batch. A null return means
 * no source row remains. Supplying the same cursor and bound reads one exact
 * metadata row for checkpoint validation.
 *
 * @since 2.7.0 bbPress (r8347)
 * @access private
 *
 * @param string $key      Legacy user-meta key.
 * @param int    $cursor   Inclusive metadata ID cursor.
 * @param int    $upper_id Inclusive fixed upper metadata ID.
 * @param array  $batch    Current worker result.
 * @return object|null|false Source row, no row, or a read failure.
 */
function bbp_admin_repair_user_meta_row( $key, $cursor, $upper_id, &$batch ) {
	$db  = bbp_db();
	$row = $db->get_row( $db->prepare( "SELECT umeta_id, user_id, meta_key, meta_value FROM {$db->usermeta} WHERE meta_key = %s AND umeta_id >= %d AND umeta_id <= %d ORDER BY umeta_id LIMIT 1", $key, $cursor, $upper_id ) );
	if ( ! empty( $db->last_error ) ) {
		bbp_admin_repair_tool_batch_fail( $batch, 'metadata-read' );
		return false;
	}

	return $row;
}

/**
 * Save a tool checkpoint, or remove it after the final batch.
 *
 * @since 2.7.0 bbPress (r8259)
 * @since 2.7.0 bbPress (r8315) Share read-only Dry Run persistence policy.
 *
 * @param string $tool_id  Repair or upgrade tool ID.
 * @param array  $state    Next batch checkpoint.
 * @param bool   $complete Whether the tool has completed.
 * @param bool   $dry_run  Whether to skip storage and cache changes.
 * @return bool Whether the stored checkpoint matches the requested state.
 */
function bbp_admin_repair_tool_save_checkpoint( $tool_id, $state, $complete = false, $dry_run = false ) {
	if ( $dry_run && ! bbp_admin_repair_tool_is_async_dry_run() ) {
		return true;
	}

	return bbp_admin_repair_tool_save_option( bbp_admin_repair_tool_batch_option_name( $tool_id ), $state, $complete );
}

/**
 * Save or remove durable tool state and verify the stored result.
 *
 * @since 2.7.0 bbPress (r8284)
 * @since 2.7.0 bbPress (r8304) Verify deletion without trusting cached absence.
 * @since 2.7.0 bbPress (r8305) Verify saved state against uncached option data.
 *
 * @param string $option Option name.
 * @param mixed  $value  Saved state.
 * @param bool   $delete Whether to remove the option.
 * @return bool Whether stored state matches the request.
 */
function bbp_admin_repair_tool_save_option( $option, $value, $delete = false ) {
	if ( $delete ) {
		$deleted = delete_option( $option );
		$saved   = bbp_admin_repair_option_matches( $option, null );
		if ( ! $deleted || ! $saved ) {
			// Recover stale cache entries after a failed or already-missing deletion
			wp_cache_delete_multiple( array( $option, 'alloptions', 'notoptions' ), 'options' );
		}
		return $saved;
	}

	update_option( $option, $value, false );
	$saved = bbp_admin_repair_option_matches( $option, $value );
	if ( ! $saved ) {
		wp_cache_delete_multiple( array( $option, 'alloptions', 'notoptions' ), 'options' );
	}
	return $saved;
}

/**
 * Remove a bounded set of temporary tool options, including preview-only rows.
 *
 * Generation names are never reused. Remove only their negative-cache entries
 * after deletion, without discarding the shared WordPress option caches.
 *
 * @since 2.7.0 bbPress (r8315)
 *
 * @param string $prefix   Exact temporary-option namespace.
 * @param int    $limit    Maximum deletions.
 * @param bool   $dry_run  Whether to preview without writes.
 * @param array  $created  Names created by this preview.
 * @param array  $cleaned  Names already removed by this preview.
 * @return array Success, work performed, and completion.
 */
function bbp_admin_repair_tool_cleanup_options( $prefix, $limit, $dry_run, &$created, &$cleaned ) {
	$db    = bbp_db();
	$names = $db->get_col( $db->prepare( "SELECT option_name FROM {$db->options} WHERE option_name LIKE %s ORDER BY option_name LIMIT %d", $db->esc_like( $prefix ) . '%', $limit + count( $cleaned ) + 1 ) );
	if ( ! empty( $db->last_error ) ) {
		return array( false, 1, false );
	}
	$names = array_diff( array_unique( array_merge( $names, array_keys( $created ) ) ), array_keys( $cleaned ) );
	sort( $names, SORT_STRING );
	$complete = count( $names ) <= $limit;
	$deleted  = array();
	$success  = true;
	foreach ( array_slice( $names, 0, $limit ) as $name ) {
		if ( ! $dry_run && ! bbp_admin_repair_tool_save_option( $name, false, true ) ) {
			$success = $complete = false;
			break;
		}
		unset( $created[ $name ] );
		if ( $dry_run ) {
			$cleaned[ $name ] = true;
		} else {
			$deleted[ $name ] = true;
		}
	}
	if ( $deleted ) {
		$notoptions = wp_cache_get( 'notoptions', 'options' );
		if ( is_array( $notoptions ) ) {
			wp_cache_set( 'notoptions', array_diff_key( $notoptions, $deleted ), 'options' );
		}
	}
	return array( $success, max( 1, min( $limit, count( $names ) ) ), $complete );
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
 * Prefix a dry-run result with its shared status.
 *
 * @since 2.7.0 bbPress (r8252)
 *
 * @param string $message Dry-run impact message.
 * @param bool   $success Whether the dry run succeeded.
 * @param int    $batches Estimated batches for a full Run.
 * @param bool   $complete Whether the dry run is complete.
 * @return string
 */
function bbp_admin_repair_tool_dry_run_message( $message = '', $success = true, $batches = 0, $complete = true ) {
	if ( ! $success ) {
		$status = esc_html__( 'Dry run failed! No changes were made.', 'bbpress' );
	} elseif ( ! $complete ) {
		$status = esc_html__( 'Dry run in progress. No changes have been made.', 'bbpress' );
	} else {
		$status = esc_html__( 'Dry run complete! No changes were made.', 'bbpress' );
	}

	$lines = array( $status );
	if ( ! empty( $message ) ) {
		$lines[] = $message;
	}

	if ( $success && $complete && $batches > 0 ) {
		/* translators: %s: Estimated number of batches for a full Run */
		$lines[] = sprintf( esc_html__( 'Estimated batches for a full Run: %s.', 'bbpress' ), number_format_i18n( $batches ) );
	}

	return implode( "\n", $lines );
}

/**
 * Add a restart explanation to a tool's result statement when needed.
 *
 * @since 2.7.0 bbPress (r8254)
 *
 * @param string $statement Tool result statement containing a %s placeholder.
 * @param bool   $restarted Whether invalid saved progress requires a restart.
 * @param bool   $dry_run   Whether only the restart is being previewed.
 * @return string
 */
function bbp_admin_repair_tool_restart_statement( $statement, $restarted = false, $dry_run = false ) {
	if ( ! $restarted ) {
		return $statement;
	}

	$message = $dry_run
		? esc_html__( 'Saved progress is invalid. Previewing from the beginning.', 'bbpress' )
		: esc_html__( 'Saved progress was invalid. Restarting from the beginning.', 'bbpress' );

	return sprintf( $statement, str_replace( '%', '%%', $message ) . "\n%s" );
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
				'pending'     => in_array( $id, $pending, true ),
				'description' => $tool['description'],
				'callback'    => isset( $callbacks['run'] ) ? $callbacks['run'] : '',
				'callbacks'   => $callbacks,
				'overhead'    => $tool['overhead'],
				'version'     => $tool['version'],
				'components'  => $tool['components']
			);
		}
	}

	// Show pending upgrades first unless a specific order was requested.
	if ( ( 'upgrade' === $type ) && empty( $_GET['orderby'] ) && empty( $_GET['order'] ) ) {
		$orderby = array(
			'pending'  => 'DESC',
			'priority' => 'ASC'
		);
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
