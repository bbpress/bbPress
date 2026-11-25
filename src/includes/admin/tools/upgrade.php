<?php

/**
 * bbPress Admin Upgrade Functions.
 *
 * @package bbPress
 * @subpackage Administration
 */

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

/**
 * Admin repair page.
 *
 * @since 2.6.0 bbPress (r6279)
 * @return void
 */
function bbp_admin_upgrade_page() {
	bbp_admin_tools_repair_upgrade_page( 'upgrade' );
}

/**
 * Upgrade historical mention relationships for bbPress 2.7 and higher.
 *
 * @since 2.7.0 bbPress (r8264)
 *
 * @return array{0:int, 1:string|WP_Error, success?:bool, complete?:bool, batches?:int, error?:string} Upgrade status, feedback, and completion state.
 */
function bbp_admin_upgrade_user_mentions() {
	return bbp_admin_upgrade_user_mentions_process();
}

/**
 * Resume the historical mention upgrade during a small-site version update.
 *
 * @since 2.7.0 bbPress (r8264)
 *
 * @return array{0:int, 1:string|WP_Error, success?:bool, complete?:bool, batches?:int, error?:string} Upgrade status, feedback, and completion state.
 */
function bbp_admin_upgrade_user_mentions_automatically() {
	$completed = get_option( '_bbp_mention_backfill', false );
	if ( is_array( $completed ) && ! empty( $completed['complete'] ) ) {
		return array(
			0,
			'',
			'complete' => true
		);
	}

	return bbp_admin_upgrade_user_mentions_process( false, true );
}

/**
 * Preview the historical mention upgrade without changing saved progress.
 *
 * @since 2.7.0 bbPress (r8264)
 *
 * @return array{0:int, 1:string|WP_Error, success?:bool, complete?:bool, batches?:int, error?:string} Upgrade status, feedback, and completion state.
 */
function bbp_admin_upgrade_user_mentions_dry_run() {
	return bbp_admin_upgrade_user_mentions_process( true );
}

/**
 * Format one mention upgrade batch for the shared tools runner.
 *
 * @since 2.7.0 bbPress (r8411)
 *
 * @param bool $dry_run   Preview without changing mention data.
 * @param bool $automatic Called by the authorized version updater.
 * @return array{0:int, 1:string|WP_Error, success?:bool, complete?:bool, batches?:int, error?:string} Upgrade status, feedback, and completion state.
 */
function bbp_admin_upgrade_user_mentions_process( $dry_run = false, $automatic = false ) {
	if ( ! $automatic && ! current_user_can( 'bbp_tools_upgrade_page' ) ) {
		return array(
			BBP_Admin_Batch_Worker::RESULT_FAILED,
			new WP_Error( 'bbp_mention_backfill_permission', __( 'You do not have permission to upgrade forums.', 'bbpress' ) ),
			'success'  => false,
			'complete' => false
		);
	}

	$batch = bbp_admin_upgrade_user_mentions_batch( $dry_run );

	$added_count     = bbp_admin_repair_tool_batch_count( $batch, 'added' );
	$baselines_count = bbp_admin_repair_tool_batch_count( $batch, 'baselines' );
	$posts_count     = bbp_admin_repair_tool_batch_count( $batch, 'posts' );
	$removed_count   = bbp_admin_repair_tool_batch_count( $batch, 'removed' );
	if ( $batch['code'] ) {
		$message = $batch['message'] ? $batch['message'] : new WP_Error( 'bbp_mention_backfill_checkpoint', __( 'Could not save mention backfill progress. Run the tool again to resume.', 'bbpress' ) );
		return array(
			$batch['code'],
			$message,
			'success'  => false,
			'complete' => false,
			'batches'  => $batch['batches'],
			'error'    => $batch['error']
		);
	}

	if ( $dry_run ) {
		$lines = array(
			sprintf(
				/* translators: %s: Number of topics and replies checked */
				_n( '%s topic or reply checked.', '%s topics and replies checked.', $posts_count, 'bbpress' ),
				number_format_i18n( $posts_count )
			),
			sprintf(
				/* translators: %s: Number of mentions to add */
				_n( '%s mention added.', '%s mentions added.', $added_count, 'bbpress' ),
				number_format_i18n( $added_count )
			),
			sprintf(
				/* translators: %s: Number of mentions to remove */
				_n( '%s mention removed.', '%s mentions removed.', $removed_count, 'bbpress' ),
				number_format_i18n( $removed_count )
			),
			sprintf(
				/* translators: %s: Number of posts whose email history needs updating */
				_n( '%s post email history updated to prevent zombie notifications.', '%s post email histories updated to prevent zombie notifications.', $baselines_count, 'bbpress' ),
				number_format_i18n( $baselines_count )
			)
		);
		return array(
			0,
			esc_html( implode( "\n", $lines ) ),
			'complete' => (bool) $batch['complete'],
			'batches'  => $batch['batches']
		);
	}

	$message = $batch['complete']
		/* translators: %s: Number of topics and replies processed */
		? __( 'Mention scan complete: %s topics and replies processed. No historical mention emails were sent.', 'bbpress' )
		/* translators: %s: Number of topics and replies processed */
		: __( 'Mention scan in progress: %s topics and replies processed. No historical mention emails are sent.', 'bbpress' );

	return array(
		0,
		esc_html( sprintf( $message, number_format_i18n( $batch['processed'] ) ) ),
		'complete' => (bool) $batch['complete']
	);
}

/**
 * Plan one historical post's mention relationships and notification baseline.
 *
 * Run and Dry Run share this planner. Only Run applies and verifies its plan.
 *
 * @since 2.7.0 bbPress (r8331)
 *
 * @param int $post_id Topic or reply ID.
 * @return array{changes:array{post:WP_Post|false, current:array<array-key, int>, expected:list<int>, added:list<int>, removed:list<int>}, handled:array<int, int>, baseline:bool}|WP_Error Planned changes, or an invalid notification record.
 */
function bbp_get_mention_backfill_plan( $post_id = 0 ) {
	$changes = bbp_get_post_mention_changes( $post_id );
	$plan    = array(
		'changes'  => $changes,
		'handled'  => array(),
		'baseline' => false
	);
	if ( ! $changes['post'] ) {
		return $plan;
	}

	$handled = wp_parse_id_list( array_merge( $changes['current'], $changes['expected'] ) );
	if ( empty( $handled ) ) {
		return $plan;
	}

	$exists = metadata_exists( 'post', $post_id, '_bbp_mention_notification' );
	$record = $exists ? get_post_meta( $post_id, '_bbp_mention_notification', true ) : false;
	if ( $exists && ( ! is_array( $record ) || ! isset( $record['handled'] ) || ! is_array( $record['handled'] ) ) ) {
		/* translators: %d: Post ID */
		return new WP_Error( 'bbp_mention_backfill_post', sprintf( __( 'The mention notification record for post %d is invalid.', 'bbpress' ), $post_id ) );
	}

	// Existing unpublished records must retain their approval-time recipients.
	$statuses = ( bbp_get_reply_post_type() === $changes['post']->post_type ) ? bbp_get_public_reply_statuses() : bbp_get_public_topic_statuses();
	if ( $exists && ! in_array( $changes['post']->post_status, $statuses, true ) ) {
		$handled = array();
	}
	$plan['handled']  = $handled;
	$plan['baseline'] = ! $exists || ! empty( array_diff( $handled, bbp_parse_id_list( $record['handled'] ) ) );

	return $plan;
}

/**
 * Index one historical post and consume its existing mention recipients.
 *
 * This does not save the post, run publication hooks, or touch the mail queue.
 * Retrying is safe after a partial write. Verify relationships and the durable
 * notification baseline before allowing the batch to advance past this post.
 *
 * @since 2.7.0 bbPress (r8264)
 *
 * @param int $post_id Topic or reply ID.
 * @return bool Whether indexing and the silent baseline were saved.
 */
function bbp_backfill_post_mentions( $post_id = 0 ) {

	// Long-running commands must not reuse content cached by an earlier batch.
	wp_cache_delete( $post_id, 'posts' );
	wp_cache_delete( $post_id, 'post_meta' );
	$plan = bbp_get_mention_backfill_plan( $post_id );
	if ( is_wp_error( $plan ) ) {
		return false;
	}
	$post = $plan['changes']['post'];
	if ( ! $post ) {
		return true;
	}

	$user_ids = bbp_apply_post_mention_changes( $post_id, $plan['changes'] );
	$indexed  = bbp_get_users_for_object( $post_id, '_bbp_mention' );
	if ( array_diff( $user_ids, $indexed ) || array_diff( $indexed, $user_ids ) ) {
		return false;
	}

	if ( $plan['changes']['current'] || $user_ids ) {
		bbp_claim_mention_notification_recipients( $post_id, $plan['handled'], $plan['handled'] );
		wp_cache_delete( $post_id, 'post_meta' );
		$state = get_post_meta( $post_id, '_bbp_mention_notification', true );
		if ( ! is_array( $state ) || ! isset( $state['handled'] ) || ! is_array( $state['handled'] ) || array_diff( $plan['handled'], $state['handled'] ) ) {
			return false;
		}
	}

	// An edit during indexing needs another pass through this post.
	wp_cache_delete( $post_id, 'posts' );
	$current = get_post( $post_id );

	return ! $current || ( $post->post_content === $current->post_content );
}

/**
 * Validate the shared checkpoint for the historical mention upgrade.
 *
 * @since 2.7.0 bbPress (r8411)
 *
 * @param mixed $state Saved checkpoint.
 * @return bool Whether the checkpoint can be resumed.
 */
function bbp_admin_upgrade_user_mentions_is_valid_checkpoint( $state ) {
	return bbp_admin_repair_tool_is_valid_checkpoint( $state, array( 'processed' ) )
		&& bbp_admin_repair_tool_is_valid_preview_state( $state, array( 'posts', 'scanned', 'added', 'removed', 'baselines', 'batches' ) );
}

/**
 * Process one resumable batch of historical mentions.
 *
 * The shared tools runner owns the request lock and checkpoint. Save after
 * each post so a failed write retries that post. The old backfill option is
 * only a completion marker after this worker finishes.
 *
 * @since 2.7.0 bbPress (r8411)
 *
 * @param bool $dry_run Preview without changing mention data.
 * @return array<string, mixed> Shared batch status and mention counts.
 */
function bbp_admin_upgrade_user_mentions_batch( $dry_run = false ) {
	$tool_id = 'bbp-user-mentions';
	$batch   = bbp_admin_repair_tool_batch_status(
		array(
			'processed' => 0,
			'posts'     => 0,
			'scanned'   => 0,
			'added'     => 0,
			'removed'   => 0,
			'baselines' => 0,
			'message'   => false,
		)
	);
	$state = false;
	if ( ! bbp_admin_repair_tool_batch_start( $tool_id, $state, $batch, 'bbp_admin_upgrade_user_mentions_is_valid_checkpoint', array(), $dry_run ) ) {
		return $batch;
	}
	$context   = bbp_admin_repair_tool_execution_context();
	$completed = get_option( '_bbp_mention_backfill', false );
	if ( false === $state && ! $dry_run && ! empty( $context['run_id'] ) && ! empty( $context['started'] )
		&& is_array( $completed ) && ! empty( $completed['complete'] ) ) {
		$batch['processed'] = isset( $completed['processed'] ) ? (int) $completed['processed'] : 0;
		$batch['complete']  = true;
		return $batch;
	}

	$db = bbp_db();
	if ( false === $state ) {
		$upper = $db->get_var( "SELECT MAX(ID) FROM {$db->posts}" );
		if ( ! empty( $db->last_error ) ) {
			$batch['message'] = new WP_Error( 'bbp_mention_backfill_query', __( 'Could not read posts for the mention backfill.', 'bbpress' ) );
			bbp_admin_repair_tool_batch_fail( $batch, 'source-read' );
			return $batch;
		}
		$state = array(
			'cursor'    => 0,
			'upper_id'  => (int) $upper,
			'processed' => 0,
		);

		// Continue a checkpoint written by the earlier Mentions worker.
		$legacy = $dry_run ? false : get_option( '_bbp_mention_backfill', false );
		if ( is_array( $legacy ) && empty( $legacy['complete'] )
			&& isset( $legacy['cursor'], $legacy['upper'], $legacy['processed'] )
			&& is_int( $legacy['cursor'] ) && is_int( $legacy['upper'] ) && is_int( $legacy['processed'] )
			&& $legacy['cursor'] >= 0 && $legacy['cursor'] <= $legacy['upper'] && $legacy['processed'] >= 0 ) {
			if ( ! empty( $legacy['expires'] ) && $legacy['expires'] > time() ) {
				bbp_admin_repair_tool_batch_fail( $batch, 'progress' );
				return $batch;
			}
			$state['cursor']    = $legacy['cursor'];
			$state['upper_id']  = $legacy['upper'];
			$state['processed'] = $legacy['processed'];
		}

		if ( ! bbp_admin_repair_tool_batch_save( $tool_id, $state, $batch, false, $dry_run ) ) {
			return $batch;
		}
	}

	$size = bbp_admin_repair_tool_batch_size( $tool_id );
	if ( $dry_run ) {
		$fields = array( 'posts', 'scanned', 'added', 'removed', 'baselines', 'batches' );
		bbp_admin_repair_tool_restore_preview( $state, $batch, $fields );
		do {
			$preview = bbp_preview_mention_backfill(
				array(
					'cursor'   => $state['cursor'],
					'upper'    => $state['upper_id'],
					'complete' => false
				),
				$size
			);
			if ( is_wp_error( $preview ) ) {
				$batch['message'] = $preview;
				$reason = ( 'bbp_mention_backfill_post' === $preview->get_error_code() ) ? 'source-invalid' : 'source-read';
				bbp_admin_repair_tool_batch_fail( $batch, $reason );
				return $batch;
			}
			$state['cursor'] = $preview['cursor'];
			foreach ( array( 'posts', 'scanned', 'added', 'removed', 'baselines' ) as $field ) {
				$batch[ $field ] += $preview[ $field ];
			}
			++$batch['batches'];
			if ( ! bbp_admin_repair_tool_batch_save( $tool_id, $state, $batch, ! $preview['more'], true, $fields ) ) {
				return $batch;
			}
		} while ( bbp_admin_repair_tool_batch_continue( $batch, true ) );

		return $batch;
	}

	$batch['processed'] = $state['processed'];
	$posts = bbp_get_mention_backfill_posts(
		array(
			'cursor' => $state['cursor'],
			'upper'  => $state['upper_id']
		),
		$size
	);
	if ( is_wp_error( $posts ) ) {
		$batch['message'] = $posts;
		bbp_admin_repair_tool_batch_fail( $batch, 'source-read' );
		return $batch;
	}

	$started   = microtime( true );
	$exhausted = true;
	foreach ( $posts as $post ) {
		$next = $state;
		if ( in_array( $post->post_type, array( bbp_get_topic_post_type(), bbp_get_reply_post_type() ), true ) ) {
			if ( ! bbp_backfill_post_mentions( (int) $post->ID ) ) {
				/* translators: %d: Post ID */
				$batch['message'] = new WP_Error( 'bbp_mention_backfill_post', sprintf( __( 'Could not finish indexing mentions in post %d. Run the tool again to retry this post.', 'bbpress' ), $post->ID ) );
				bbp_admin_repair_tool_batch_fail( $batch, 'verify' );
				return $batch;
			}
			++$next['processed'];
		}

		$next['cursor'] = (int) $post->ID;
		if ( ! bbp_admin_repair_tool_batch_save( $tool_id, $next, $batch, false ) ) {
			return $batch;
		}
		$state = $next;
		$batch['processed'] = $state['processed'];

		if ( ( microtime( true ) - $started ) >= 5 ) {
			$exhausted = false;
			break;
		}
	}

	$complete = $exhausted && ( count( $posts ) < $size || $state['cursor'] >= $state['upper_id'] );
	++$batch['batches'];
	if ( ! bbp_admin_repair_tool_batch_save( $tool_id, $state, $batch, $complete ) ) {
		return $batch;
	}
	if ( $complete && ! bbp_admin_repair_tool_save_option(
		'_bbp_mention_backfill',
		array(
			'cursor'    => $state['upper_id'],
			'upper'     => $state['upper_id'],
			'processed' => $state['processed'],
			'complete'  => true
		)
	) ) {
		bbp_admin_repair_tool_batch_fail( $batch, 'progress' );
	}

	return $batch;
}

/**
 * Read the same bounded post-ID range for Run and Dry Run.
 *
 * @since 2.7.0 bbPress (r8264)
 *
 * @param array{cursor:int, upper:int, complete?:bool} $state      Validated backfill progress.
 * @param int   $batch_size Maximum post rows to examine.
 * @return list<stdClass>|WP_Error Post IDs and types, or a query error.
 */
function bbp_get_mention_backfill_posts( $state, $batch_size ) {
	$bbp_db = bbp_db();
	$posts  = $bbp_db->get_results( $bbp_db->prepare( "SELECT ID, post_type FROM {$bbp_db->posts} WHERE ID > %d AND ID <= %d ORDER BY ID ASC LIMIT %d", $state['cursor'], $state['upper'], $batch_size ) );

	if ( ! is_array( $posts ) || $bbp_db->last_error ) {
		return new WP_Error( 'bbp_mention_backfill_query', __( 'Could not read posts for the mention backfill.', 'bbpress' ) );
	}

	return $posts;
}

/**
 * Preview one batch without claiming a lease or changing saved progress.
 *
 * Counts describe this batch only, not the entire site. Content and mention
 * relationships may change before Run, so this is a preview, not a saved plan.
 *
 * @since 2.7.0 bbPress (r8264)
 *
 * @param array{cursor:int, upper:int, complete:bool} $state      Validated backfill progress.
 * @param int   $batch_size Maximum post rows to examine.
 * @return array{start:int, cursor:int, upper:int, scanned:int, posts:int, added:int, removed:int, baselines:int, more:bool}|WP_Error Batch preview or an error.
 */
function bbp_preview_mention_backfill( $state, $batch_size ) {
	$preview = array(
		'start'     => (int) $state['cursor'],
		'cursor'    => (int) $state['cursor'],
		'upper'     => (int) $state['upper'],
		'scanned'   => 0,
		'posts'     => 0,
		'added'     => 0,
		'removed'   => 0,
		'baselines' => 0,
		'more'      => false
	);
	$posts = $state['complete'] ? array() : bbp_get_mention_backfill_posts( $state, $batch_size );
	if ( is_wp_error( $posts ) ) {
		return $posts;
	}

	$types = array( bbp_get_topic_post_type(), bbp_get_reply_post_type() );
	$ids   = array();
	foreach ( $posts as $row ) {
		if ( in_array( $row->post_type, $types, true ) ) {
			$ids[] = (int) $row->ID;
		}
	}
	wp_cache_delete_multiple( $ids, 'posts' );
	wp_cache_delete_multiple( $ids, 'post_meta' );
	try {
		_prime_post_caches( $ids, false, true );
		if ( ! empty( bbp_db()->last_error ) ) {
			return new WP_Error( 'bbp_mention_backfill_query', __( 'Could not read posts for the mention backfill.', 'bbpress' ) );
		}
		$started = microtime( true );
		foreach ( $posts as $row ) {
			$post_id = (int) $row->ID;
			++$preview['scanned'];
			$preview['cursor'] = $post_id;

			if ( in_array( $row->post_type, $types, true ) ) {
				$plan = bbp_get_mention_backfill_plan( $post_id );
				if ( is_wp_error( $plan ) ) {
					return $plan;
				}
				if ( $plan['changes']['post'] ) {
					++$preview['posts'];
					$preview['added']     += count( $plan['changes']['added'] );
					$preview['removed']   += count( $plan['changes']['removed'] );
					$preview['baselines'] += (int) $plan['baseline'];
				}
			}

			// Leave time for the repair page to render on slower installations.
			if ( ( microtime( true ) - $started ) >= 5 ) {
				break;
			}
		}
	} finally {
		// Release only the posts inspected by this preview window.
		wp_cache_delete_multiple( $ids, 'posts' );
		wp_cache_delete_multiple( $ids, 'post_meta' );
	}

	// Check for another row without counting or loading the remaining history.
	if ( ! $state['complete'] && ( $preview['cursor'] < $preview['upper'] ) ) {
		$remaining = bbp_get_mention_backfill_posts( $preview, 1 );
		if ( is_wp_error( $remaining ) ) {
			return $remaining;
		}
		$preview['more'] = ! empty( $remaining );
	}

	return $preview;
}

/**
 * Upgrade user engagements for bbPress 2.6 and higher.
 *
 * @since 2.6.0 bbPress (r6320)
 * @since 2.7.0 bbPress (r8230) Added Dry Run support.
 * @since 2.7.0 bbPress (r8259) Process one resumable batch per call.
 *
 * @return array{0:int, 1:string|WP_Error, success?:bool, complete?:bool, batches?:int, error?:string} An array of the status code and the message.
 */
function bbp_admin_upgrade_user_engagements() {
	return bbp_admin_upgrade_user_engagements_process();
}

/**
 * Preview upgrading user engagements for bbPress 2.6 and higher.
 *
 * @since 2.7.0 bbPress (r8230)
 *
 * @return array{0:int, 1:string|WP_Error, success?:bool, complete?:bool, batches?:int, error?:string} An array of the status code and the message.
 */
function bbp_admin_upgrade_user_engagements_dry_run() {
	return bbp_admin_upgrade_user_engagements_process( true );
}

/**
 * Validate an engagement upgrade checkpoint and its aggregate preview state.
 *
 * @since 2.7.0 bbPress (r8346)
 * @access private
 *
 * @param mixed $state Saved worker checkpoint.
 * @return bool Whether the checkpoint can be resumed safely.
 */
function bbp_admin_upgrade_user_engagements_is_valid_checkpoint( $state ) {
	return bbp_admin_repair_tool_is_valid_checkpoint( $state )
		&& bbp_admin_repair_tool_has_valid_scratch_token( $state )
		&& isset( $state['phase'] ) && in_array( $state['phase'], array( 'prepare', 'scan', 'cleanup' ), true )
		&& bbp_admin_repair_tool_is_valid_preview_state( $state, array( 'added', 'removed', 'batches' ), array( 'found' ) );
}

/**
 * Reconcile one bounded batch of engagements with topic and reply authors.
 *
 * Scan source relationships and existing engagements together by metadata ID.
 * Preserve valid engagements, add missing ones, and remove only stale or duplicate
 * rows. Newly added rows need no further work and fall outside the fixed upper ID.
 * Failed batches can be retried without clearing or duplicating valid data.
 *
 * @since 2.7.0 bbPress (r8259)
 *
 * @param bool $dry_run Whether to preview without writes.
 * @return array<string, mixed> Batch status, additions, removals, and completion flag.
 */
function bbp_admin_upgrade_user_engagements_batch( $dry_run = false ) {
	$bbp_db  = bbp_db();
	$tool_id = 'bbp-user-topic-engagements-move';
	$batch   = bbp_admin_repair_tool_batch_status(
		array(
			'added'   => 0,
			'removed' => 0,
			'found'   => false,
		)
	);
	$state = false;
	if ( ! bbp_admin_repair_tool_batch_start( $tool_id, $state, $batch, 'bbp_admin_upgrade_user_engagements_is_valid_checkpoint', array(), $dry_run ) ) {
		return $batch;
	}

	if ( false === $state ) {
		$upper_id = $bbp_db->get_var( "SELECT meta_id FROM {$bbp_db->postmeta} WHERE meta_key IN ('_bbp_topic_id', '_bbp_engagement') ORDER BY meta_id DESC LIMIT 1" );
		if ( ! empty( $bbp_db->last_error ) ) {
			bbp_admin_repair_tool_batch_fail( $batch, 'metadata-read' );
			return $batch;
		}
		$state = array(
			'cursor'   => 0,
			'upper_id' => (int) $upper_id,
			'phase'    => $dry_run && bbp_admin_repair_tool_is_async_dry_run() ? 'prepare' : 'scan',
			'token'    => bbp_admin_repair_tool_new_token(),
		);
		if ( ! bbp_admin_repair_tool_batch_save( $tool_id, $state, $batch, false, $dry_run ) ) {
			if ( ! $dry_run ) {
				bbp_add_pending_upgrade( $tool_id );
			}
			return $batch;
		}
	}

	$size    = bbp_admin_repair_tool_batch_size( $tool_id );
	$fields  = array( 'added', 'removed', 'found', 'batches' );
	bbp_admin_repair_tool_restore_preview( $state, $batch, $fields );
	$planned = array();
	do {
		if ( 'prepare' === $state['phase'] ) {
			list( $success, $work, $complete ) = bbp_admin_repair_tool_cleanup_scratch( $tool_id, $size );
			if ( ! $success ) {
				bbp_admin_repair_tool_batch_fail( $batch, 'progress' );
				return $batch;
			}
			if ( $complete ) {
				$state['phase'] = 'scan';
			}
			if ( ! bbp_admin_repair_tool_batch_save( $tool_id, $state, $batch, false, $dry_run, $fields ) ) {
				return $batch;
			}
			continue;
		}
		if ( 'cleanup' === $state['phase'] ) {
			list( $success, $work, $complete ) = bbp_admin_repair_tool_cleanup_scratch( $tool_id, $size );
			if ( ! $success ) {
				bbp_admin_repair_tool_batch_fail( $batch, 'progress' );
				return $batch;
			}
			$batch['complete'] = $complete;
			if ( ! bbp_admin_repair_tool_batch_save( $tool_id, $state, $batch, $complete, $dry_run, $fields ) ) {
				return $batch;
			}
			continue;
		}
		$rows = $bbp_db->get_results( $bbp_db->prepare( "SELECT meta_id, post_id, meta_value, (meta_key = '_bbp_topic_id') AS is_source FROM {$bbp_db->postmeta} WHERE meta_key IN ('_bbp_topic_id', '_bbp_engagement') AND meta_id > %d AND meta_id <= %d ORDER BY meta_id LIMIT %d", $state['cursor'], $state['upper_id'], $size + 1 ) );
		if ( ! is_array( $rows ) || ! empty( $bbp_db->last_error ) ) {
			bbp_admin_repair_tool_batch_fail( $batch, 'metadata-read' );
			return $batch;
		}
		$complete         = count( $rows ) <= $size;
		$rows             = array_slice( $rows, 0, $size );
		$batch['found']   = $batch['found'] || ! empty( $rows );
		$statuses         = array( bbp_get_public_status_id(), bbp_get_closed_status_id() );
		$source           = bbp_admin_repair_get_engagements_query( $statuses, $statuses, true );
		if ( null === $source ) {
			bbp_admin_repair_tool_batch_fail( $batch, 'source-read' );
			return $batch;
		}
		$claim            = $dry_run && bbp_admin_repair_tool_is_async_dry_run()
			? static function ( $topic_id, $user_id ) use ( $tool_id, $state ) {
				return bbp_admin_repair_tool_scratch_claim( $tool_id, $state['token'], 'relationships', array( $topic_id, $user_id ) );
			}
			: null;
		$repair           = bbp_admin_repair_engagement_rows( $rows, $source, $dry_run, $planned, $claim );
		$batch['added']  += $repair['added'];
		$batch['removed'] += $repair['removed'];
		if ( ! empty( $repair['code'] ) ) {
			bbp_admin_repair_tool_batch_fail( $batch, $repair['error'] );
			return $batch;
		}
		if ( ! empty( $rows ) ) {
			$state['cursor'] = $repair['cursor'];
		}

		++$batch['batches'];
		if ( $complete && $dry_run && bbp_admin_repair_tool_is_async_dry_run() ) {
			$state['phase'] = 'cleanup';
			$complete       = false;
		}
		$batch['complete'] = $complete;
		$data = ( $dry_run && ! bbp_admin_repair_tool_is_async_dry_run() ) ? array( 'planned' => $planned ) : array();
		if ( ! bbp_admin_repair_tool_batch_save( $tool_id, $state, $batch, $complete, $dry_run, $fields, $data ) ) {
			return $batch;
		}
	} while ( bbp_admin_repair_tool_batch_continue( $batch, $dry_run ) );

	return $batch;
}

/**
 * Upgrade or preview upgrading user engagements.
 *
 * @since 2.7.0 bbPress (r8230)
 *
 * @param bool $dry_run Whether to preview changes without updating data.
 * @return array{0:int, 1:string|WP_Error, success?:bool, complete?:bool, batches?:int, error?:string} An array of the status code and the message.
 */
function bbp_admin_upgrade_user_engagements_process( $dry_run = false ) {

	// Define variables
	$no_engagements = esc_html__( '0 engagements to upgrade.', 'bbpress' );

	if ( $dry_run ) {
		/* translators: %s: Status of the dry-run process */
		$statement = esc_html__( 'Checking user engagements&hellip; %s', 'bbpress' );
	} else {
		/* translators: %s: Status of the upgrade process */
		$statement = esc_html__( 'Upgrading user engagements&hellip; %s', 'bbpress' );
	}

	$batch     = bbp_admin_upgrade_user_engagements_batch( $dry_run );

	$added_count   = bbp_admin_repair_tool_batch_count( $batch, 'added' );
	$removed_count = bbp_admin_repair_tool_batch_count( $batch, 'removed' );
	if ( $dry_run ) {
		/* translators: %s: Number of missing engagements that would be added */
		$added = sprintf( _n( '%s missing engagement would be added from topic and reply authors.', '%s missing engagements would be added from topic and reply authors.', $added_count, 'bbpress' ), bbp_number_format( $added_count ) );
		/* translators: %s: Number of stale or duplicate engagement rows that would be removed */
		$removed = sprintf( _n( '%s stale or duplicate engagement row would be cleaned up.', '%s stale or duplicate engagement rows would be cleaned up.', $removed_count, 'bbpress' ), bbp_number_format( $removed_count ) );
		$result  = $added . "\n" . $removed;
	} else {
		/* translators: %s: Number of missing engagements added */
		$added = sprintf( _n( '%s missing engagement added from topic and reply authors.', '%s missing engagements added from topic and reply authors.', $added_count, 'bbpress' ), bbp_number_format( $added_count ) );
		/* translators: %s: Number of stale or duplicate engagement rows removed */
		$removed = sprintf( _n( '%s stale or duplicate engagement row cleaned up.', '%s stale or duplicate engagement rows cleaned up.', $removed_count, 'bbpress' ), bbp_number_format( $removed_count ) );
		$result  = $added . "\n" . $removed;
	}

	return bbp_admin_repair_tool_present_batch( $batch, $statement, $result, $dry_run, ! $batch['found'], $no_engagements );
}

/**
 * Upgrade group forum ID mappings after a bbPress 1.x to bbPress 2.x conversion.
 *
 * Previously named: bbp_admin_repair_group_forum_relationships().
 *
 * @since 2.6.0 bbPress (r6496)
 * @since 2.7.0 bbPress (r8237) Added Dry Run support.
 * @since 2.7.0 bbPress (r8315) Process one resumable batch per call.
 *
 * @return array{0:int, 1:string|WP_Error, success?:bool, complete?:bool, batches?:int, error?:string} An array of the status code and the message.
 */
function bbp_admin_upgrade_group_forum_relationships() {
	return bbp_admin_upgrade_group_forum_relationships_process();
}

/**
 * Preview upgrading group forum ID mappings after a bbPress 1.x conversion.
 *
 * @since 2.7.0 bbPress (r8237)
 * @since 2.7.0 bbPress (r8315) Added resumable batching.
 *
 * @return array{0:int, 1:string|WP_Error, success?:bool, complete?:bool, batches?:int, error?:string} An array of the status code and the message.
 */
function bbp_admin_upgrade_group_forum_relationships_dry_run() {
	return bbp_admin_upgrade_group_forum_relationships_process( true );
}

/**
 * Upgrade or preview upgrading converted group forum ID mappings.
 *
 * @since 2.7.0 bbPress (r8237)
 * @since 2.7.0 bbPress (r8315) Stage a bounded preflight before updating relationships.
 *
 * @param bool $dry_run Whether to preview changes without updating data.
 * @return array{0:int, 1:string|WP_Error, success?:bool, complete?:bool, batches?:int, error?:string} An array of the status code and the message.
 */
function bbp_admin_upgrade_group_forum_relationships_process( $dry_run = false ) {
	$worker = new BBP_Admin_Group_Forum_Upgrade( $dry_run );
	$batch  = $worker->run();
	return bbp_admin_upgrade_group_forum_relationships_results( $batch, $dry_run );
}

/**
 * Describe one group-forum upgrade batch, including partial failure progress.
 *
 * @since 2.7.0 bbPress (r8315)
 *
 * @param array<string, mixed> $batch   Batch result.
 * @param bool  $dry_run Whether this was a preview.
 * @return array{0:int, 1:string|WP_Error, success?:bool, complete?:bool, batches?:int, error?:string} Status, message, success and completion.
 */
function bbp_admin_upgrade_group_forum_relationships_results( $batch, $dry_run = false ) {
	$groups       = bbp_admin_repair_tool_batch_count( $batch, 'groups' );
	$forums       = bbp_admin_repair_tool_batch_count( $batch, 'forums' );
	$statuses     = bbp_admin_repair_tool_batch_count( $batch, 'statuses' );
	$root_updates = bbp_admin_repair_tool_batch_count( $batch, 'root' );
	$renames      = bbp_admin_repair_tool_batch_count( $batch, 'rename' );
	$legacy_roles = $batch['roles'];
	$statement    = $dry_run
		/* translators: %s: Status of the dry-run process */
		? esc_html__( 'Checking BuddyPress group-forum relationships&hellip; %s', 'bbpress' )
		/* translators: %s: Status of the upgrade process */
		: esc_html__( 'Upgrading BuddyPress group-forum relationships&hellip; %s', 'bbpress' );
	$statement = bbp_admin_repair_tool_restart_statement( $statement, $batch['restarted'], $dry_run );

	// Complete results
	if ( $dry_run ) {
		$role_list = ! empty( $legacy_roles )
			? implode( ', ', $legacy_roles )
			: esc_html__( 'none', 'bbpress' );
		$results   = array(
			/* translators: %s: Number of groups that would be updated */
			sprintf( _n( '%s group would be updated', '%s groups would be updated', $groups, 'bbpress' ), bbp_number_format( $groups ) ),
			/* translators: %s: Number of forums that would be updated */
			sprintf( _n( '%s forum would be updated', '%s forums would be updated', $forums, 'bbpress' ), bbp_number_format( $forums ) ),
			/* translators: %s: Number of forum statuses that would be synced */
			sprintf( _n( '%s forum status would be synced', '%s forum statuses would be synced', $statuses, 'bbpress' ), bbp_number_format( $statuses ) ),
			/* translators: %s: Number of group root settings that would be updated */
			sprintf( _n( '%s group root setting would be updated', '%s group root settings would be updated', $root_updates, 'bbpress' ), bbp_number_format( $root_updates ) ),
			/* translators: %s: Number of forums that would be renamed */
			sprintf( _n( '%s forum would be renamed', '%s forums would be renamed', $renames, 'bbpress' ), bbp_number_format( $renames ) ),
			/* translators: %1$s: Number of legacy roles that would be removed; %2$s: Legacy role IDs */
			sprintf( _n( '%1$s legacy role would be removed (%2$s)', '%1$s legacy roles would be removed (%2$s)', count( $legacy_roles ), 'bbpress' ), bbp_number_format( count( $legacy_roles ) ), $role_list )
		);
		$result    = implode( ".\n", $results ) . '.';
	} else {
		$results = array(
			/* translators: %s: Number of groups updated */
			sprintf( _n( '%s group updated', '%s groups updated', $groups, 'bbpress' ), bbp_number_format( $groups ) ),
			/* translators: %s: Number of forums updated */
			sprintf( _n( '%s forum updated', '%s forums updated', $forums, 'bbpress' ), bbp_number_format( $forums ) ),
			/* translators: %s: Number of forum statuses synced */
			sprintf( _n( '%s forum status synced', '%s forum statuses synced', $statuses, 'bbpress' ), bbp_number_format( $statuses ) )
		);
		$result  = ( $batch['code'] ? esc_html__( 'Progress:', 'bbpress' ) : ( $batch['complete'] ? esc_html__( 'Complete!', 'bbpress' ) : esc_html__( 'Batch complete!', 'bbpress' ) ) ) . "\n" . implode( ".\n", $results ) . '.';
	}

	$errors = array(
		'forums-read'       => esc_html__( 'Failed! Converted forums could not be read.', 'bbpress' ),
		'groupmeta-read'     => esc_html__( 'Failed! BuddyPress group metadata could not be read.', 'bbpress' ),
		'groups-read'        => esc_html__( 'Failed! BuddyPress groups could not be read.', 'bbpress' ),
		'groupmeta-write'    => esc_html__( 'Failed! BuddyPress group metadata could not be updated.', 'bbpress' ),
		'root-rename'        => esc_html__( 'Failed! The group forums root could not be renamed.', 'bbpress' ),
		'ambiguous-mapping'  => $batch['mapping_error'] ? esc_html__( 'Failed! Ambiguous converted forum ID mappings were found.', 'bbpress' ) : esc_html__( 'Failed! Ambiguous BuddyPress group-forum relationships were found.', 'bbpress' ),
		'forum-meta-write'   => esc_html__( 'Failed! Forum group metadata could not be updated.', 'bbpress' ),
		'visibility'         => esc_html__( 'Failed! Forum visibility could not be updated.', 'bbpress' ),
		'root-option'        => esc_html__( 'Failed! The group forums root setting could not be updated.', 'bbpress' ),
		'progress'           => esc_html__( 'Failed! Upgrade progress could not be saved or read.', 'bbpress' ),
		'verify'             => esc_html__( 'Failed! Group-forum mappings or saved progress could not be verified. Check the mappings and run the upgrade again.', 'bbpress' ),
		'roles'              => esc_html__( 'Failed! Legacy forum roles could not be removed.', 'bbpress' ),
	);
	if ( $batch['code'] ) {
		$error  = isset( $errors[ $batch['error'] ] ) ? $errors[ $batch['error'] ] : esc_html__( 'Failed!', 'bbpress' );
		$result = $error . ( $dry_run ? '' : "\n" . $result );
	} elseif ( $batch['no_data'] ) {
		$result = esc_html__( '0 converted group forums to upgrade.', 'bbpress' );
	} elseif ( in_array( $batch['phase'], array( 'prepare', 'index', 'plan', 'verify' ), true ) ) {
		$result = esc_html__( 'Checking converted group-forum mappings. Changes are counted after all mappings have been checked.', 'bbpress' );
	}
	return array(
		$batch['code'] ? $batch['code'] : ( $batch['no_data'] ? 1 : 0 ),
		sprintf( $statement, $result ),
		'success'  => ! $batch['code'],
		'complete' => $batch['complete'],
		'batches'  => isset( $batch['batches'] ) ? $batch['batches'] : 0,
		'error'    => $batch['error'],
	);
}

/**
 * Check the stored fields of a legacy user-meta checkpoint.
 *
 * @since 2.7.0 bbPress (r8254)
 * @since 2.7.0 bbPress (r8347) Validate shared source and preview state.
 *
 * @param mixed $state Saved checkpoint.
 * @return bool Whether the saved upgrade checkpoint is valid.
 */
function bbp_admin_upgrade_user_meta_is_valid_checkpoint( $state ) {
	return bbp_admin_repair_tool_is_valid_user_meta_checkpoint( $state )
		&& bbp_admin_repair_tool_has_valid_scratch_token( $state )
		&& isset( $state['phase'] ) && in_array( $state['phase'], array( 'prepare', 'scan', 'cleanup' ), true )
		&& bbp_admin_repair_tool_is_valid_preview_state( $state, array( 'added', 'failed', 'batches' ), array( 'found' ) )
		&& ( 'scan' === $state['phase'] || ( 0 === $state['offset'] && '' === $state['row_hash'] ) );
}

/**
 * Upgrade one bounded batch of legacy user-meta relationships.
 *
 * The checkpoint includes a fixed upper user-meta ID and a position within a
 * single user's list, so each request processes a bounded set of relationships.
 * Failed writes leave the checkpoint unchanged; existing relationships make retries
 * safe. Dry Run scans the complete source without saving any progress.
 *
 * @since 2.7.0 bbPress (r8253)
 * @since 2.7.0 bbPress (r8254) Safely restart malformed checkpoints.
 * @since 2.7.0 bbPress (r8347) Share source and worker lifecycle helpers.
 *
 * @param string $tool_id Repair or upgrade tool ID.
 * @param string $old_key Legacy user-meta key.
 * @param string $new_key Destination post-meta key.
 * @param bool   $dry_run Whether to preview without writes.
 * @return array<string, mixed> Batch status, additions, failures, and completion flag.
 */
function bbp_admin_upgrade_user_meta_batch( $tool_id, $old_key, $new_key, $dry_run = false ) {
	$bbp_db = bbp_db();
	$batch  = bbp_admin_repair_tool_batch_status(
		array(
			'added'  => 0,
			'failed' => 0,
			'found'  => false,
		)
	);
	$fields = array( 'added', 'failed', 'found', 'batches' );
	$state  = false;
	if ( ! bbp_admin_repair_tool_batch_start( $tool_id, $state, $batch, 'bbp_admin_upgrade_user_meta_is_valid_checkpoint', array(), $dry_run ) ) {
		return $batch;
	}

	// Changed or deleted rows are normal; an impossible offset in an unchanged row is not
	if ( false !== $state && 'scan' === $state['phase'] && ! empty( $state['offset'] ) ) {
		$meta = bbp_admin_repair_user_meta_row( $old_key, $state['cursor'], $state['cursor'], $batch );
		if ( false === $meta ) {
			return $batch;
		}
		if ( ! empty( $meta ) && bbp_admin_repair_user_meta_row_hash( $meta ) === $state['row_hash']
			&& $state['offset'] >= strlen( (string) $meta->meta_value ) ) {
			$state              = false;
			$batch['restarted'] = true;
		}
	}

	if ( false === $state ) {
		$upper_id = bbp_admin_repair_user_meta_upper_id( $old_key, $batch );
		if ( false === $upper_id ) {
			return $batch;
		}

		$state = array(
			'cursor'   => 0,
			'offset'   => 0,
			'upper_id' => (int) $upper_id,
			'row_hash' => '',
			'phase'    => $dry_run && bbp_admin_repair_tool_is_async_dry_run() ? 'prepare' : 'scan',
			'token'    => bbp_admin_repair_tool_new_token(),
		);

		// Preserve the source even if the first batch fails or is interrupted
		if ( ! bbp_admin_repair_tool_batch_save( $tool_id, $state, $batch, false, $dry_run, $fields ) ) {
			if ( ! $dry_run ) {
				bbp_add_pending_upgrade( $tool_id );
			}
			return $batch;
		}
	}

	$size    = bbp_admin_repair_tool_batch_size( $tool_id );
	bbp_admin_repair_tool_restore_preview( $state, $batch, $fields );
	$planned = array();
	do {
		$work = 0;
		if ( 'prepare' === $state['phase'] ) {
			list( $success, $cleanup_work, $complete ) = bbp_admin_repair_tool_cleanup_scratch( $tool_id, $size );
			if ( ! $success ) {
				bbp_admin_repair_tool_batch_fail( $batch, 'progress' );
				return $batch;
			}
			if ( $complete ) {
				$state['phase'] = 'scan';
			}
			if ( ! bbp_admin_repair_tool_batch_save( $tool_id, $state, $batch, false, $dry_run, $fields ) ) {
				return $batch;
			}
			continue;
		}
		if ( 'cleanup' === $state['phase'] ) {
			list( $success, $cleanup_work, $complete ) = bbp_admin_repair_tool_cleanup_scratch( $tool_id, $size );
			if ( ! $success ) {
				bbp_admin_repair_tool_batch_fail( $batch, 'progress' );
				return $batch;
			}
			$batch['complete'] = $complete;
			if ( ! bbp_admin_repair_tool_batch_save( $tool_id, $state, $batch, $complete, $dry_run, $fields ) ) {
				return $batch;
			}
			continue;
		}

		while ( ( $work < $size ) && ( $state['cursor'] <= $state['upper_id'] ) ) {
			$meta = bbp_admin_repair_user_meta_row( $old_key, $state['cursor'], $state['upper_id'], $batch );
			if ( false === $meta ) {
				return $batch;
			}
			if ( empty( $meta ) ) {
				break;
			}
			$batch['found'] = true;

			$row_id   = (int) $meta->umeta_id;
			$user_id  = (int) $meta->user_id;
			$row_hash = bbp_admin_repair_user_meta_row_hash( $meta );
			$offset   = ( $row_id === (int) $state['cursor'] ) && ( $row_hash === $state['row_hash'] )
				? (int) $state['offset']
				: 0;
			$parsed = bbp_admin_repair_user_meta_id_batch( $meta->meta_value, $offset, $size - $work );

			// Empty lists still consume work, keeping every source scan bounded.
			if ( empty( $parsed['work'] ) ) {
				++$work;
				$state['cursor']   = $row_id + 1;
				$state['offset']   = 0;
				$state['row_hash'] = '';
				continue;
			}

			$work  += $parsed['work'];
			$offset = $parsed['offset'];
			foreach ( $parsed['ids'] as $post_id ) {
				if ( $dry_run && bbp_admin_repair_tool_is_async_dry_run() ) {
					list( $saved, $first ) = bbp_admin_repair_tool_scratch_claim( $tool_id, $state['token'], 'relationships', array( $user_id, $post_id ) );
					if ( ! $saved ) {
						bbp_admin_repair_tool_batch_fail( $batch, 'progress' );
						return $batch;
					}
					if ( ! $first ) {
						continue;
					}
				} else {
					if ( isset( $planned[ $user_id ][ $post_id ] ) ) {
						continue;
					}
					$planned[ $user_id ][ $post_id ] = true;
				}

				$exists = $bbp_db->get_var( $bbp_db->prepare( "SELECT meta_id FROM {$bbp_db->postmeta} WHERE post_id = %d AND meta_key = %s AND meta_value = %d LIMIT 1", $post_id, $new_key, $user_id ) );
				if ( ! empty( $bbp_db->last_error ) ) {
					bbp_admin_repair_tool_batch_fail( $batch, 'metadata-read' );
					return $batch;
				}
				if ( ! empty( $exists ) ) {
					continue;
				}

				if ( ! $dry_run && bbp_admin_repair_metadata_add( 'post', $post_id, $new_key, $user_id )['code'] ) {
					++$batch['failed'];
				} else {
					++$batch['added'];
				}
			}

			if ( ! $parsed['complete'] ) {
				$state['cursor']   = $row_id;
				$state['offset']   = $offset;
				$state['row_hash'] = $row_hash;
			} else {
				$state['cursor']   = $row_id + 1;
				$state['offset']   = 0;
				$state['row_hash'] = '';
			}
		}

		if ( ! empty( $batch['failed'] ) ) {
			bbp_admin_repair_tool_batch_fail( $batch, 'metadata-write' );
			return $batch;
		}

		$remaining = bbp_admin_repair_user_meta_row( $old_key, $state['cursor'], $state['upper_id'], $batch );
		if ( false === $remaining ) {
			return $batch;
		}
		$complete = empty( $remaining );
		if ( $complete && $dry_run && bbp_admin_repair_tool_is_async_dry_run() ) {
			$state['phase']    = 'cleanup';
			$state['cursor']   = $state['upper_id'];
			$state['offset']   = 0;
			$state['row_hash'] = '';
			$complete          = false;
		}

		$batch['batches'] += max( 1, (int) ceil( $work / $size ) );
		$data = ( $dry_run && ! bbp_admin_repair_tool_is_async_dry_run() ) ? array( 'planned' => $planned ) : array();
		if ( ! bbp_admin_repair_tool_batch_save( $tool_id, $state, $batch, $complete, $dry_run, $fields, $data ) ) {
			return $batch;
		}
	} while ( bbp_admin_repair_tool_batch_continue( $batch, $dry_run ) );

	return $batch;
}

/**
 * Present the three legacy user-meta migrations through their shared worker.
 *
 * Keep literal plural strings together so translation extraction remains native.
 *
 * @since 2.7.0 bbPress (r8315)
 * @since 2.7.0 bbPress (r8347) Use shared failure results.
 * @access private
 *
 * @param string $type    Favorites, topic-subscriptions, or forum-subscriptions.
 * @param bool   $dry_run Whether to preview without writes.
 * @return array{0:int, 1:string|WP_Error, success?:bool, complete?:bool, batches?:int, error?:string} Tool status, impact text and completion.
 */
function bbp_admin_upgrade_user_meta_process( $type, $dry_run = false ) {
	$config = bbp_admin_get_user_relationship_config( $type );
	if ( false === $config || ! in_array( $type, array( 'favorites', 'topic-subscriptions', 'forum-subscriptions' ), true ) ) {
		return array(
			1,
			esc_html__( 'Failed!', 'bbpress' ),
			'success'  => false,
			'complete' => false,
		);
	}
	$tool_id      = $config['move_tool_id'];
	$old_key      = $config['source_key'];
	$new_key      = $config['destination_key'];
	$batch        = bbp_admin_upgrade_user_meta_batch( $tool_id, $old_key, $new_key, $dry_run );
	$total        = bbp_admin_repair_tool_batch_count( $batch, 'added' );
	$failed_total = bbp_admin_repair_tool_batch_count( $batch, 'failed' );

	switch ( $type ) {
		case 'favorites':
			$none      = esc_html__( '0 favorite relationships to upgrade.', 'bbpress' );
			$statement = $dry_run
				/* translators: %s: Status of the dry-run process */
				? esc_html__( 'Checking user favorites&hellip; %s', 'bbpress' )
				/* translators: %s: Status of the upgrade process */
				: esc_html__( 'Upgrading user favorites&hellip; %s', 'bbpress' );
			break;
		case 'topic-subscriptions':
			$none      = esc_html__( '0 topic subscription relationships to upgrade.', 'bbpress' );
			$statement = $dry_run
				/* translators: %s: Status of the dry-run process */
				? esc_html__( 'Checking user topic subscriptions&hellip; %s', 'bbpress' )
				/* translators: %s: Status of the upgrade process */
				: esc_html__( 'Upgrading user topic subscriptions&hellip; %s', 'bbpress' );
			break;
		case 'forum-subscriptions':
			$none      = esc_html__( '0 forum subscription relationships to upgrade.', 'bbpress' );
			$statement = $dry_run
				/* translators: %s: Status of the dry-run process */
				? esc_html__( 'Checking user forum subscriptions&hellip; %s', 'bbpress' )
				/* translators: %s: Status of the upgrade process */
				: esc_html__( 'Upgrading user forum subscriptions&hellip; %s', 'bbpress' );
			break;
	}
	$statement = bbp_admin_repair_tool_restart_statement( $statement, $batch['restarted'], $dry_run );
	if ( $batch['code'] && 'metadata-write' !== $batch['error'] ) {
		return bbp_admin_repair_tool_batch_result( $batch, $statement );
	}
	if ( $batch['complete'] && ( ! $batch['found'] || ( $dry_run && ! $total ) ) ) {
		return array(
			1,
			sprintf( $statement, $none ),
			'complete' => true,
			'batches'  => $batch['batches'],
			'error'    => '',
		);
	}

	$added    = '';
	$failures = '';
	switch ( $type ) {
		case 'favorites':
			if ( $dry_run ) {
				/* translators: %s: Number of relationships that would be added */
				$result = sprintf( _n( '%s favorite relationship would be added to topic meta from user meta.', '%s favorite relationships would be added to topic meta from user meta.', $total, 'bbpress' ), bbp_number_format( $total ) );
			} elseif ( $failed_total ) {
				/* translators: %s: Number of relationships added */
				$added = sprintf( _n( '%s favorite relationship added', '%s favorite relationships added', $total, 'bbpress' ), bbp_number_format( $total ) );
				/* translators: %s: Number of relationships that could not be added */
				$failures = sprintf( _n( '%s favorite relationship could not be added', '%s favorite relationships could not be added', $failed_total, 'bbpress' ), bbp_number_format( $failed_total ) );
			} else {
				/* translators: %s: Number of relationships added */
				$result = sprintf( _n( '%s favorite relationship added to topic meta.', '%s favorite relationships added to topic meta.', $total, 'bbpress' ), bbp_number_format( $total ) );
			}
			break;
		case 'topic-subscriptions':
			if ( $dry_run ) {
				/* translators: %s: Number of relationships that would be added */
				$result = sprintf( _n( '%s topic subscription relationship would be added to topic meta from user meta.', '%s topic subscription relationships would be added to topic meta from user meta.', $total, 'bbpress' ), bbp_number_format( $total ) );
			} elseif ( $failed_total ) {
				/* translators: %s: Number of relationships added */
				$added = sprintf( _n( '%s topic subscription relationship added', '%s topic subscription relationships added', $total, 'bbpress' ), bbp_number_format( $total ) );
				/* translators: %s: Number of relationships that could not be added */
				$failures = sprintf( _n( '%s topic subscription relationship could not be added', '%s topic subscription relationships could not be added', $failed_total, 'bbpress' ), bbp_number_format( $failed_total ) );
			} else {
				/* translators: %s: Number of relationships added */
				$result = sprintf( _n( '%s topic subscription relationship added to topic meta.', '%s topic subscription relationships added to topic meta.', $total, 'bbpress' ), bbp_number_format( $total ) );
			}
			break;
		case 'forum-subscriptions':
			if ( $dry_run ) {
				/* translators: %s: Number of relationships that would be added */
				$result = sprintf( _n( '%s forum subscription relationship would be added to forum meta from user meta.', '%s forum subscription relationships would be added to forum meta from user meta.', $total, 'bbpress' ), bbp_number_format( $total ) );
			} elseif ( $failed_total ) {
				/* translators: %s: Number of relationships added */
				$added = sprintf( _n( '%s forum subscription relationship added', '%s forum subscription relationships added', $total, 'bbpress' ), bbp_number_format( $total ) );
				/* translators: %s: Number of relationships that could not be added */
				$failures = sprintf( _n( '%s forum subscription relationship could not be added', '%s forum subscription relationships could not be added', $failed_total, 'bbpress' ), bbp_number_format( $failed_total ) );
			} else {
				/* translators: %s: Number of relationships added */
				$result = sprintf( _n( '%s forum subscription relationship added to forum meta.', '%s forum subscription relationships added to forum meta.', $total, 'bbpress' ), bbp_number_format( $total ) );
			}
			break;
	}
	if ( ! $dry_run && $failed_total ) {
		return array(
			$batch['code'],
			sprintf( $statement, esc_html__( 'Failed!', 'bbpress' ) . "\n" . $added . ".\n" . $failures . '.' ),
			'success'  => false,
			'complete' => false,
			'error'    => $batch['error'],
		);
	}
	if ( ! $dry_run ) {
		$result = bbp_admin_repair_tool_batch_message( $result, $batch['complete'] );
	}
	return array(
		0,
		sprintf( $statement, $result ),
		'complete' => $batch['complete'],
		'batches'  => $batch['batches'],
		'error'    => '',
	);
}

/**
 * Upgrade user favorites for bbPress 2.6 and higher.
 *
 * @since 2.6.0 bbPress (r6176)
 * @since 2.7.0 bbPress (r8231) Added Dry Run support.
 * @since 2.7.0 bbPress (r8253) Process one resumable batch per call.
 *
 * @return array{0:int, 1:string|WP_Error, success?:bool, complete?:bool, batches?:int, error?:string} An array of the status code and the message.
 */
function bbp_admin_upgrade_user_favorites() {
	return bbp_admin_upgrade_user_favorites_process();
}

/**
 * Preview upgrading user favorites for bbPress 2.6 and higher.
 *
 * @since 2.7.0 bbPress (r8231)
 *
 * @return array{0:int, 1:string|WP_Error, success?:bool, complete?:bool, batches?:int, error?:string} An array of the status code and the message.
 */
function bbp_admin_upgrade_user_favorites_dry_run() {
	return bbp_admin_upgrade_user_favorites_process( true );
}

/**
 * Upgrade or preview upgrading user favorites.
 *
 * @since 2.7.0 bbPress (r8231)
 *
 * @param bool $dry_run Whether to preview changes without updating data.
 * @return array{0:int, 1:string|WP_Error, success?:bool, complete?:bool, batches?:int, error?:string} An array of the status code and the message.
 */
function bbp_admin_upgrade_user_favorites_process( $dry_run = false ) {
	return bbp_admin_upgrade_user_meta_process( 'favorites', $dry_run );
}

/**
 * Upgrade user topic subscriptions for bbPress 2.6 and higher.
 *
 * @since 2.6.0 bbPress (r6193)
 * @since 2.7.0 bbPress (r8232) Added Dry Run support.
 * @since 2.7.0 bbPress (r8253) Process one resumable batch per call.
 *
 * @return array{0:int, 1:string|WP_Error, success?:bool, complete?:bool, batches?:int, error?:string} An array of the status code and the message.
 */
function bbp_admin_upgrade_user_topic_subscriptions() {
	return bbp_admin_upgrade_user_topic_subscriptions_process();
}

/**
 * Preview upgrading user topic subscriptions for bbPress 2.6 and higher.
 *
 * @since 2.7.0 bbPress (r8232)
 *
 * @return array{0:int, 1:string|WP_Error, success?:bool, complete?:bool, batches?:int, error?:string} An array of the status code and the message.
 */
function bbp_admin_upgrade_user_topic_subscriptions_dry_run() {
	return bbp_admin_upgrade_user_topic_subscriptions_process( true );
}

/**
 * Upgrade or preview upgrading user topic subscriptions.
 *
 * @since 2.7.0 bbPress (r8232)
 *
 * @param bool $dry_run Whether to preview changes without updating data.
 * @return array{0:int, 1:string|WP_Error, success?:bool, complete?:bool, batches?:int, error?:string} An array of the status code and the message.
 */
function bbp_admin_upgrade_user_topic_subscriptions_process( $dry_run = false ) {
	return bbp_admin_upgrade_user_meta_process( 'topic-subscriptions', $dry_run );
}

/**
 * Upgrade user forum subscriptions for bbPress 2.6 and higher.
 *
 * @since 2.6.0 bbPress (r6193)
 * @since 2.7.0 bbPress (r8233) Added Dry Run support.
 * @since 2.7.0 bbPress (r8253) Process one resumable batch per call.
 *
 * @return array{0:int, 1:string|WP_Error, success?:bool, complete?:bool, batches?:int, error?:string} An array of the status code and the message.
 */
function bbp_admin_upgrade_user_forum_subscriptions() {
	return bbp_admin_upgrade_user_forum_subscriptions_process();
}

/**
 * Preview upgrading user forum subscriptions for bbPress 2.6 and higher.
 *
 * @since 2.7.0 bbPress (r8233)
 *
 * @return array{0:int, 1:string|WP_Error, success?:bool, complete?:bool, batches?:int, error?:string} An array of the status code and the message.
 */
function bbp_admin_upgrade_user_forum_subscriptions_dry_run() {
	return bbp_admin_upgrade_user_forum_subscriptions_process( true );
}

/**
 * Upgrade or preview upgrading user forum subscriptions.
 *
 * @since 2.7.0 bbPress (r8233)
 *
 * @param bool $dry_run Whether to preview changes without updating data.
 * @return array{0:int, 1:string|WP_Error, success?:bool, complete?:bool, batches?:int, error?:string} An array of the status code and the message.
 */
function bbp_admin_upgrade_user_forum_subscriptions_process( $dry_run = false ) {
	return bbp_admin_upgrade_user_meta_process( 'forum-subscriptions', $dry_run );
}

/**
 * Check migration eligibility and hold its lock while cleaning up legacy data.
 *
 * Dry Run checks eligibility without changing options, caches, or locks. Run
 * rechecks current migration state after acquiring the migration's lock and
 * holds it until the supplied cleanup callback finishes.
 *
 * @since 2.7.0 bbPress (r8286)
 *
 * @param string   $tool_id  Migration tool ID.
 * @param callable $callback Cleanup callback, or a read-only preview for Dry Run.
 * @param bool     $dry_run Whether to check eligibility without writes or locks.
 * @return mixed|WP_Error Callback result, or an error if the legacy data is still needed.
 */
function bbp_admin_upgrade_user_meta_cleanup( $tool_id, $callback, $dry_run = false ) {
	$blocked = new WP_Error( 'bbp_upgrade_incomplete', esc_html__( 'Finish the upgrade before removing its legacy user data.', 'bbpress' ) );
	$lock    = false;

	if ( ! $dry_run ) {
		$lock = bbp_admin_repair_tool_lock( $tool_id );
		if ( false === $lock ) {
			return $blocked;
		}

		// Recheck current options after acquiring the migration lock
		wp_cache_delete( '_bbp_db_pending_upgrades', 'options' );
		wp_cache_delete( '_bbp_engagements_strategy', 'options' );
		wp_cache_delete( 'alloptions', 'options' );
		wp_cache_delete( 'notoptions', 'options' );
	}

	try {
		list( $read, $checkpoint ) = bbp_admin_repair_tool_get_checkpoint( $tool_id );
		if ( ! $read || false !== $checkpoint || in_array( $tool_id, bbp_get_pending_upgrades(), true ) ) {
			return $blocked;
		}

		// These keys are live storage when the pre-2.6 strategy is still in use
		if ( 'user' === bbp_engagements_strategy() ) {
			return new WP_Error( 'bbp_upgrade_user_meta_in_use', esc_html__( 'This site still stores favorites and subscriptions in user data. Cleanup is unavailable while that data is in use.', 'bbpress' ) );
		}

		return call_user_func( $callback );
	} finally {
		if ( false !== $lock ) {
			bbp_admin_repair_tool_unlock( $lock );
		}
	}
}

/**
 * Validate a legacy user-meta cleanup checkpoint.
 *
 * @since 2.7.0 bbPress (r8348)
 * @access private
 *
 * @param mixed $state   Saved checkpoint.
 * @param array{key:string} $context Current legacy user-meta key.
 * @return bool Whether the cleanup checkpoint can be resumed.
 */
function bbp_admin_upgrade_remove_user_meta_is_valid_checkpoint( $state, $context ) {
	return bbp_admin_repair_tool_is_matching_checkpoint( $state, $context )
		&& bbp_admin_repair_tool_is_valid_preview_state( $state, array( 'removed', 'batches' ), array( 'initial' ) );
}

/**
 * Remove one bounded batch of migrated legacy user-meta rows.
 *
 * Capture a fixed upper metadata ID, delete individual rows through WordPress,
 * and verify each deletion before saving progress. Replaying a batch after a
 * failed checkpoint only revisits surviving rows. Later rows wait for a new run.
 *
 * @since 2.7.0 bbPress (r8286)
 * @since 2.7.0 bbPress (r8348) Share source and worker lifecycle helpers.
 *
 * @param string $type    Favorites, topic-subscriptions, or forum-subscriptions.
 * @param bool   $dry_run Whether to preview without writes.
 * @return array<string, mixed> Status code, removed rows, and progress flags.
 */
function bbp_admin_upgrade_remove_user_meta_batch( $type = 'favorites', $dry_run = false ) {
	$db     = bbp_db();
	$config = bbp_admin_get_user_relationship_config( $type );
	$batch  = bbp_admin_repair_tool_batch_status(
		array(
			'removed' => 0,
			'initial' => false,
			'message' => '',
		)
	);
	if ( false === $config ) {
		bbp_admin_repair_tool_batch_fail( $batch, 'callback' );
		return $batch;
	}
	$tool_id  = $config['delete_tool_id'];
	$key      = $config['source_key'];
	$callback = static function () use ( $db, $tool_id, $key, $dry_run, $batch ) {
		$fields = array( 'removed', 'initial', 'batches' );
		$state  = false;
		if ( ! bbp_admin_repair_tool_batch_start( $tool_id, $state, $batch, 'bbp_admin_upgrade_remove_user_meta_is_valid_checkpoint', array( 'key' => $key ), $dry_run ) ) {
			return $batch;
		}
		$batch['initial'] = false === $state;
		if ( false === $state ) {
			$upper = bbp_admin_repair_user_meta_upper_id( $key, $batch );
			if ( false === $upper ) {
				return $batch;
			}
			$state = array(
				'cursor'   => 0,
				'upper_id' => $upper,
				'key'      => $key
			);
			if ( ! bbp_admin_repair_tool_batch_save( $tool_id, $state, $batch, false, $dry_run, $fields ) ) {
				return $batch;
			}
		}
		$size = bbp_admin_repair_tool_batch_size( $tool_id );
		bbp_admin_repair_tool_restore_preview( $state, $batch, $fields );
		do {
			$rows = $db->get_results( $db->prepare( "SELECT umeta_id, user_id FROM {$db->usermeta} WHERE meta_key = %s AND umeta_id > %d AND umeta_id <= %d ORDER BY umeta_id LIMIT %d", $key, $state['cursor'], $state['upper_id'], $size + 1 ) );
			if ( ! is_array( $rows ) || ! empty( $db->last_error ) ) {
				bbp_admin_repair_tool_batch_fail( $batch, 'metadata-read' );
				return $batch;
			}
			$complete = count( $rows ) <= $size;
			foreach ( array_slice( $rows, 0, $size ) as $row ) {
				if ( ! $dry_run ) {
					$code = bbp_admin_repair_metadata_row( 'user', $row, $key, null );
					if ( $code ) {
						bbp_admin_repair_tool_batch_fail( $batch, 1 === $code ? 'metadata-read' : 'metadata-write' );
						return $batch;
					}
				}
				++$batch['removed'];
				$state['cursor'] = (int) $row->umeta_id;
			}
			++$batch['batches'];
			if ( ! bbp_admin_repair_tool_batch_save( $tool_id, $state, $batch, $complete, $dry_run, $fields ) ) {
				return $batch;
			}
		} while ( bbp_admin_repair_tool_batch_continue( $batch, $dry_run ) );

		return $batch;
	};
	$result = bbp_admin_upgrade_user_meta_cleanup( $config['move_tool_id'], $callback, $dry_run );
	if ( is_wp_error( $result ) ) {
		$batch['message'] = $result->get_error_message();
		bbp_admin_repair_tool_batch_fail( $batch, $result->get_error_code() );
		return $batch;
	}
	return $result;
}

/**
 * Format a bounded legacy user-meta cleanup or its read-only preview.
 *
 * @since 2.7.0 bbPress (r8286)
 *
 * @param string $type    Favorites, topic-subscriptions, or forum-subscriptions.
 * @param bool   $dry_run Whether to preview without writes.
 * @return array{0:int, 1:string|WP_Error, success?:bool, complete?:bool, batches?:int, error?:string} Status code, feedback, and completion flag.
 */
function bbp_admin_upgrade_remove_user_meta_process( $type = 'favorites', $dry_run = false ) {
	$batch = bbp_admin_upgrade_remove_user_meta_batch( $type, $dry_run );
	if ( ! empty( $batch['code'] ) ) {
		if ( ! empty( $batch['message'] ) ) {
			$result = $batch['message'];
		} elseif ( 'progress' === $batch['error'] ) {
			$result = esc_html__( 'Failed! Progress could not be saved. Run the tool again to retry.', 'bbpress' );
		} else {
			$result = esc_html__( 'Failed!', 'bbpress' );
		}
		return array(
			$batch['code'],
			$result,
			'error'    => $batch['error'],
			'success'  => false,
			'complete' => false
		);
	}

	$total = bbp_admin_repair_tool_batch_count( $batch, 'removed' );
	switch ( $type ) {
		case 'topic-subscriptions':
			if ( $dry_run ) {
				/* translators: %s: Number of legacy topic subscription user-meta rows */
				$result = _n( '%s topic subscription user-meta row would be cleaned up.', '%s topic subscription user-meta rows would be cleaned up.', $total, 'bbpress' );
			} else {
				/* translators: %s: Number of legacy topic subscription user-meta rows */
				$result = _n( '%s topic subscription user-meta row cleaned up.', '%s topic subscription user-meta rows cleaned up.', $total, 'bbpress' );
			}
			break;
		case 'forum-subscriptions':
			if ( $dry_run ) {
				/* translators: %s: Number of legacy forum subscription user-meta rows */
				$result = _n( '%s forum subscription user-meta row would be cleaned up.', '%s forum subscription user-meta rows would be cleaned up.', $total, 'bbpress' );
			} else {
				/* translators: %s: Number of legacy forum subscription user-meta rows */
				$result = _n( '%s forum subscription user-meta row cleaned up.', '%s forum subscription user-meta rows cleaned up.', $total, 'bbpress' );
			}
			break;
		default:
			if ( $dry_run ) {
				/* translators: %s: Number of legacy favorite user-meta rows */
				$result = _n( '%s favorite user-meta row would be cleaned up.', '%s favorite user-meta rows would be cleaned up.', $total, 'bbpress' );
			} else {
				/* translators: %s: Number of legacy favorite user-meta rows */
				$result = _n( '%s favorite user-meta row cleaned up.', '%s favorite user-meta rows cleaned up.', $total, 'bbpress' );
			}
	}
	$result    = bbp_admin_repair_tool_batch_message( sprintf( $result, bbp_number_format( $total ) ), $batch['complete'], $dry_run );
	$statement = bbp_admin_repair_tool_restart_statement( '%s', $batch['restarted'], $dry_run );
	$code      = $batch['initial'] && ! $total ? 1 : 0;
	return array(
		$code,
		sprintf( $statement, $result ),
		'complete' => $batch['complete'],
		'batches'  => $batch['batches'],
	);
}

/**
 * Remove favorites data from user meta for bbPress 2.6 and higher.
 *
 * @since 2.6.0 bbPress (r6281)
 * @since 2.7.0 bbPress (r8234) Added Dry Run support.
 * @since 2.7.0 bbPress (r8253) Preserve source data for unfinished migrations.
 * @since 2.7.0 bbPress (r8286) Process one resumable cleanup batch per call.
 *
 * @return array{0:int, 1:string|WP_Error, success?:bool, complete?:bool, batches?:int, error?:string} Status code, feedback, and completion flag.
 */
function bbp_admin_upgrade_remove_favorites_from_usermeta() {
	return bbp_admin_upgrade_remove_favorites_from_usermeta_process();
}

/**
 * Preview removing favorites data from user meta for bbPress 2.6 and higher.
 *
 * @since 2.7.0 bbPress (r8234)
 *
 * @return array{0:int, 1:string|WP_Error, success?:bool, complete?:bool, batches?:int, error?:string} An array of the status code and the message.
 */
function bbp_admin_upgrade_remove_favorites_from_usermeta_dry_run() {
	return bbp_admin_upgrade_remove_favorites_from_usermeta_process( true );
}

/**
 * Remove or preview removing favorites data from user meta.
 *
 * @since 2.7.0 bbPress (r8234)
 *
 * @param bool $dry_run Whether to preview changes without updating data.
 * @return array{0:int, 1:string|WP_Error, success?:bool, complete?:bool, batches?:int, error?:string} Status code, feedback, and completion flag.
 */
function bbp_admin_upgrade_remove_favorites_from_usermeta_process( $dry_run = false ) {
	return bbp_admin_upgrade_remove_user_meta_process( 'favorites', $dry_run );
}

/**
 * Remove topic subscriptions data from user meta for bbPress 2.6 and higher.
 *
 * @since 2.6.0 bbPress (r6281)
 * @since 2.7.0 bbPress (r8235) Added Dry Run support.
 * @since 2.7.0 bbPress (r8253) Preserve source data for unfinished migrations.
 * @since 2.7.0 bbPress (r8286) Process one resumable cleanup batch per call.
 *
 * @return array{0:int, 1:string|WP_Error, success?:bool, complete?:bool, batches?:int, error?:string} Status code, feedback, and completion flag.
 */
function bbp_admin_upgrade_remove_topic_subscriptions_from_usermeta() {
	return bbp_admin_upgrade_remove_topic_subscriptions_from_usermeta_process();
}

/**
 * Preview removing topic subscriptions data from user meta for bbPress 2.6 and higher.
 *
 * @since 2.7.0 bbPress (r8235)
 *
 * @return array{0:int, 1:string|WP_Error, success?:bool, complete?:bool, batches?:int, error?:string} An array of the status code and the message.
 */
function bbp_admin_upgrade_remove_topic_subscriptions_from_usermeta_dry_run() {
	return bbp_admin_upgrade_remove_topic_subscriptions_from_usermeta_process( true );
}

/**
 * Remove or preview removing topic subscriptions data from user meta.
 *
 * @since 2.7.0 bbPress (r8235)
 *
 * @param bool $dry_run Whether to preview changes without updating data.
 * @return array{0:int, 1:string|WP_Error, success?:bool, complete?:bool, batches?:int, error?:string} Status code, feedback, and completion flag.
 */
function bbp_admin_upgrade_remove_topic_subscriptions_from_usermeta_process( $dry_run = false ) {
	return bbp_admin_upgrade_remove_user_meta_process( 'topic-subscriptions', $dry_run );
}

/**
 * Remove forum subscriptions data from user meta for bbPress 2.6 and higher.
 *
 * @since 2.6.0 bbPress (r6281)
 * @since 2.7.0 bbPress (r8236) Added Dry Run support.
 * @since 2.7.0 bbPress (r8253) Preserve source data for unfinished migrations.
 * @since 2.7.0 bbPress (r8286) Process one resumable cleanup batch per call.
 *
 * @return array{0:int, 1:string|WP_Error, success?:bool, complete?:bool, batches?:int, error?:string} Status code, feedback, and completion flag.
 */
function bbp_admin_upgrade_remove_forum_subscriptions_from_usermeta() {
	return bbp_admin_upgrade_remove_forum_subscriptions_from_usermeta_process();
}

/**
 * Preview removing forum subscriptions data from user meta for bbPress 2.6 and higher.
 *
 * @since 2.7.0 bbPress (r8236)
 *
 * @return array{0:int, 1:string|WP_Error, success?:bool, complete?:bool, batches?:int, error?:string} An array of the status code and the message.
 */
function bbp_admin_upgrade_remove_forum_subscriptions_from_usermeta_dry_run() {
	return bbp_admin_upgrade_remove_forum_subscriptions_from_usermeta_process( true );
}

/**
 * Remove or preview removing forum subscriptions data from user meta.
 *
 * @since 2.7.0 bbPress (r8236)
 *
 * @param bool $dry_run Whether to preview changes without updating data.
 * @return array{0:int, 1:string|WP_Error, success?:bool, complete?:bool, batches?:int, error?:string} Status code, feedback, and completion flag.
 */
function bbp_admin_upgrade_remove_forum_subscriptions_from_usermeta_process( $dry_run = false ) {
	return bbp_admin_upgrade_remove_user_meta_process( 'forum-subscriptions', $dry_run );
}
