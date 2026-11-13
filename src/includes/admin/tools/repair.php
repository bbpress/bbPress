<?php

/**
 * bbPress Admin Repair Page.
 *
 * @package bbPress
 * @subpackage Administration
 */

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

/**
 * Run one mention-indexing batch or preview the complete operation.
 *
 * The shared tools runner checks its nonce before invoking this callback.
 * Completed passes restart on the next Run. Continuation requests acknowledge
 * the saved completion, including when WP-CLI finishes the shared checkpoint.
 *
 * @since 2.7.0 bbPress (r8264)
 *
 * @param string $type    Tool type: repair or upgrade.
 * @param bool   $dry_run Preview changes without saving them.
 * @return array Status, feedback, and a complete flag for the tools handler.
 */
function bbp_admin_repair_user_mentions( $type = 'repair', $dry_run = false ) {
	$type = ( 'upgrade' === $type ) ? 'upgrade' : 'repair';
	if ( ! current_user_can( 'bbp_tools_' . $type . '_page' ) ) {
		$message = ( 'upgrade' === $type )
			? __( 'You do not have permission to upgrade forums.', 'bbpress' )
			: __( 'You do not have permission to repair forums.', 'bbpress' );

		return array(
			1,
			new WP_Error( 'bbp_mention_backfill_permission', $message ),
			'success'  => false,
			'complete' => false
		);
	}

	$previous = bbp_get_mention_backfill_status();
	$restart  = ! empty( $previous['complete'] )
		&& ( bbp_admin_repair_tool_is_new_browser_run() || ( ! wp_doing_ajax() && empty( $_GET['bbp-tools-run'] ) ) );
	$tool_id  = ( 'upgrade' === $type ) ? 'bbp-user-mentions' : 'bbp-mentions';
	$size     = bbp_admin_repair_tool_batch_size( $tool_id );

	return bbp_admin_repair_user_mentions_process( $size, $restart, $dry_run, $tool_id );
}

/**
 * Validate isolated browser Dry Run progress for the mention backfill.
 *
 * @since 2.7.0 bbPress (r8331)
 * @since 2.7.0 bbPress (r8348) Validate aggregate preview totals.
 * @access private
 *
 * @param mixed $state Saved preview state.
 * @return bool
 */
function bbp_admin_repair_user_mentions_is_valid_preview( $state ) {
	return is_array( $state )
		&& isset( $state['cursor'], $state['upper'], $state['complete'] )
		&& is_int( $state['cursor'] ) && is_int( $state['upper'] )
		&& $state['cursor'] >= 0 && $state['cursor'] <= $state['upper']
		&& is_bool( $state['complete'] )
		&& bbp_admin_repair_tool_is_valid_preview_state( $state, array( 'posts', 'scanned', 'added', 'removed', 'baselines', 'batches' ) );
}

/**
 * Preview one bounded batch of historical mention indexing.
 *
 * @since 2.7.0 bbPress (r8348)
 * @access private
 *
 * @param int    $batch_size Maximum post rows to examine.
 * @param string $tool_id    Repair or upgrade tool ID.
 * @return array Aggregate preview counts and lifecycle status.
 */
function bbp_admin_repair_user_mentions_preview_batch( $batch_size, $tool_id ) {
	$fields = array( 'posts', 'scanned', 'added', 'removed', 'baselines', 'batches' );
	$batch  = bbp_admin_repair_tool_batch_status(
		array(
			'posts'     => 0,
			'scanned'   => 0,
			'added'     => 0,
			'removed'   => 0,
			'baselines' => 0,
			'message'   => false,
		)
	);
	if ( $batch_size < 1 || $batch_size > 1000 ) {
		$batch['message'] = new WP_Error( 'bbp_mention_batch_size', __( 'Choose a mention batch size between 1 and 1000.', 'bbpress' ) );
		bbp_admin_repair_tool_batch_fail( $batch, 'callback' );
		return $batch;
	}

	$tool_id = sanitize_key( $tool_id );
	$state   = false;
	if ( ! bbp_admin_repair_tool_batch_start( $tool_id, $state, $batch, 'bbp_admin_repair_user_mentions_is_valid_preview', array(), true ) ) {
		$batch['message'] = new WP_Error( 'bbp_mention_backfill_checkpoint', __( 'Could not save mention backfill progress. Run the tool again to resume.', 'bbpress' ) );
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
			'cursor'   => 0,
			'upper'    => (int) $upper,
			'complete' => false,
		);
		if ( ! bbp_admin_repair_tool_batch_save( $tool_id, $state, $batch, false, true, $fields ) ) {
			$batch['message'] = new WP_Error( 'bbp_mention_backfill_checkpoint', __( 'Could not save mention backfill progress. Run the tool again to resume.', 'bbpress' ) );
			return $batch;
		}
	}

	bbp_admin_repair_tool_restore_preview( $state, $batch, $fields );
	do {
		$preview = bbp_preview_mention_backfill( $state, $batch_size );
		if ( is_wp_error( $preview ) ) {
			$batch['message'] = $preview;
			$reason = ( 'bbp_mention_backfill_post' === $preview->get_error_code() ) ? 'source-invalid' : 'source-read';
			bbp_admin_repair_tool_batch_fail( $batch, $reason );
			break;
		}

		$state['cursor']   = $preview['cursor'];
		$state['complete'] = ! $preview['more'];
		foreach ( array( 'posts', 'scanned', 'added', 'removed', 'baselines' ) as $field ) {
			$batch[ $field ] += $preview[ $field ];
		}
		++$batch['batches'];
		if ( ! bbp_admin_repair_tool_batch_save( $tool_id, $state, $batch, $state['complete'], true, $fields ) ) {
			$batch['message'] = new WP_Error( 'bbp_mention_backfill_checkpoint', __( 'Could not save mention backfill progress. Run the tool again to resume.', 'bbpress' ) );
			break;
		}
	} while ( bbp_admin_repair_tool_batch_continue( $batch, true ) );

	return $batch;
}

/**
 * Process one mention batch for the interactive tools or automatic updater.
 *
 * Callers control access and whether a completed scan should restart.
 *
 * @since 2.7.0 bbPress (r8264)
 * @since 2.7.0 bbPress (r8348) Share the procedural Dry Run lifecycle.
 *
 * @param int    $batch_size Maximum post rows to examine.
 * @param bool   $restart    Start a new scan instead of resuming saved progress.
 * @param bool   $dry_run    Preview changes without saving them.
 * @param string $tool_id    Repair or upgrade tool ID.
 * @return array Status, feedback, and a complete flag for the tools handler.
 */
function bbp_admin_repair_user_mentions_process( $batch_size = 100, $restart = false, $dry_run = false, $tool_id = 'bbp-mentions' ) {
	$batch_size = (int) $batch_size;
	$batches    = 0;
	if ( $dry_run ) {
		$state   = bbp_admin_repair_user_mentions_preview_batch( $batch_size, $tool_id );
		$batches = $state['batches'];
		if ( $state['code'] ) {
			return array(
				$state['code'],
				$state['message'],
				'success'  => false,
				'complete' => false,
				'batches'  => $batches,
				'error'    => $state['error'],
			);
		}
	} else {
		$state = bbp_backfill_mentions( $batch_size, $restart );
	}
	if ( is_wp_error( $state ) ) {
		return array(
			1,
			$state,
			'success'  => false,
			'complete' => true,
			'batches'  => $batches
		);
	}

	if ( $dry_run ) {
		$message = sprintf(
			/* translators: 1: Number of topics and replies checked, 2: Mentions to add, 3: Mentions to remove */
			__( 'Topics and replies checked: %1$s. Mentions to add: %2$s. Mentions to remove: %3$s.', 'bbpress' ),
			number_format_i18n( $state['posts'] ),
			number_format_i18n( $state['added'] ),
			number_format_i18n( $state['removed'] )
		);
		$history = sprintf(
			/* translators: %s: Number of posts whose historical mentions need notification records */
			_n( 'Email history would be updated for %s post to prevent notifications for old mentions.', 'Email history would be updated for %s posts to prevent notifications for old mentions.', $state['baselines'], 'bbpress' ),
			number_format_i18n( $state['baselines'] )
		);

		return array(
			0,
			esc_html( $message . "\n" . $history ),
			'complete' => (bool) $state['complete'],
			'batches'  => $batches
		);
	}

	return array(
		0,
		esc_html( bbp_admin_mention_backfill_message( $state ) ),
		'complete' => (bool) $state['complete']
	);
}

/**
 * Preview the complete mention repair without changing saved progress.
 *
 * @since 2.7.0 bbPress (r8264)
 *
 * @return array Status and feedback for the tools handler.
 */
function bbp_admin_repair_user_mentions_dry_run() {
	return bbp_admin_repair_user_mentions( 'repair', true );
}

/**
 * Describe saved mention indexing progress.
 *
 * @since 2.7.0 bbPress (r8264)
 *
 * @param array $state Saved backfill progress.
 * @return string Progress or completion message.
 */
function bbp_admin_mention_backfill_message( $state ) {
	if ( $state['complete'] ) {
		/* translators: %s: Number of topics and replies processed */
		$message = __( 'Mention scan complete: %s topics and replies processed. No historical mention emails were sent.', 'bbpress' );
	} else {
		/* translators: %s: Number of topics and replies processed */
		$message = __( 'Mention scan in progress: %s topics and replies processed. No historical mention emails are sent.', 'bbpress' );
	}

	return sprintf( $message, number_format_i18n( $state['processed'] ) );
}

/** Helpers *******************************************************************/

/**
 * Repair one bounded batch of topic or parent-forum references.
 *
 * Include orphaned metadata in the same cursor and write budget. Preserve
 * canonical rows, verify completed objects, and retain unfinished checkpoints.
 * Parent-forum references may legitimately be zero for top-level posts.
 *
 * @since 2.7.0 bbPress (r8280)
 *
 * @param string $reference Reference type: 'topic' or 'forum'.
 * @param bool   $dry_run   Whether to preview without writes.
 * @return array Status code, repair counts, and progress flags.
 */
function bbp_admin_repair_post_reference_batch( $reference = 'topic', $dry_run = false ) {
	$batch = bbp_admin_repair_tool_batch_status(
		array(
			'topics'  => 0,
			'replies' => 0,
			'stale'   => 0,
			'initial' => false,
		)
	);
	if ( ! in_array( $reference, array( 'topic', 'forum' ), true ) ) {
		bbp_admin_repair_tool_batch_fail( $batch, 'callback' );
		return $batch;
	}

	$is_forum = ( 'forum' === $reference );
	$bbp_db   = bbp_db();
	$tool_id  = $is_forum ? 'bbp-sync-forum-meta' : 'bbp-sync-topic-meta';
	$key      = $is_forum ? '_bbp_forum_id' : '_bbp_topic_id';
	$types    = array( bbp_get_topic_post_type(), bbp_get_reply_post_type() );
	if ( $is_forum ) {
		$types[]         = bbp_get_forum_post_type();
		$batch['forums'] = 0;
	}
	$state = false;
	if ( ! bbp_admin_repair_tool_batch_start( $tool_id, $state, $batch, 'bbp_admin_repair_tool_is_matching_checkpoint', array( 'post_types' => $types ), $dry_run ) ) {
		return $batch;
	}

	$type_slots = implode( ', ', array_fill( 0, count( $types ), '%s' ) );
	$batch['initial'] = ( false === $state );
	if ( $batch['initial'] ) {
		$upper_id = (int) $bbp_db->get_var( $bbp_db->prepare( "SELECT MAX(ID) FROM {$bbp_db->posts} WHERE post_type IN ({$type_slots})", $types ) );
		if ( ! empty( $bbp_db->last_error ) ) {
			bbp_admin_repair_tool_batch_fail( $batch, 'source-read' );
			return $batch;
		}
		$upper_meta = (int) $bbp_db->get_var( $bbp_db->prepare( "SELECT MAX(post_id) FROM {$bbp_db->postmeta} WHERE meta_key = %s", $key ) );
		if ( ! empty( $bbp_db->last_error ) ) {
			bbp_admin_repair_tool_batch_fail( $batch, 'metadata-read' );
			return $batch;
		}
		$state = array(
			'cursor'     => 0,
			'upper_id'   => max( $upper_id, $upper_meta ),
			'post_types' => $types
		);
	}
	$fields = array( 'topics', 'replies', 'stale', 'initial', 'batches' );
	if ( $is_forum ) {
		$fields[] = 'forums';
	}
	$size          = bbp_admin_repair_tool_batch_size( $tool_id );
	$preview_state = bbp_admin_repair_tool_restore_preview( $state, $batch, $fields );
	$metadata      = isset( $preview_state['metadata'] ) ? $preview_state['metadata'] : null;
	do {
		// Bound each side before combining source posts and stored references
		$ids = $bbp_db->get_col(
			$bbp_db->prepare(
				"(SELECT ID FROM {$bbp_db->posts} WHERE post_type IN ({$type_slots}) AND ID >= %d AND ID <= %d ORDER BY ID LIMIT %d)
				UNION
				(SELECT post_id AS ID FROM {$bbp_db->postmeta} WHERE meta_key = %s AND post_id >= %d AND post_id <= %d GROUP BY post_id ORDER BY post_id LIMIT %d)
				ORDER BY ID LIMIT %d",
				array_merge( $types, array( $state['cursor'], $state['upper_id'], $size + 1, $key, $state['cursor'], $state['upper_id'], $size + 1, $size + 1 ) )
			)
		);
		if ( ! empty( $bbp_db->last_error ) ) {
			bbp_admin_repair_tool_batch_fail( $batch, 'source-read' );
			return $batch;
		}
		$has_more = count( $ids ) > $size;
		$ids      = array_slice( $ids, 0, $size );
		$expected = array();
		if ( ! empty( $ids ) ) {
			// Preserve SQL collation and the existing hierarchy rules for each reference
			if ( $is_forum ) {
				$kind_sql   = "CASE WHEN posts.post_type = %s THEN 'forums' WHEN posts.post_type = %s THEN 'topics' ELSE 'replies' END";
				$kind_args  = array( $types[2], $types[0] );
				$value_sql  = 'CASE WHEN posts.post_type IN (%s, %s) THEN posts.post_parent WHEN posts.post_type = %s AND parent.post_type = %s THEN parent.post_parent ELSE NULL END';
				$value_args = array( $types[2], $types[0], $types[1], $types[0] );
			} else {
				$kind_sql   = "CASE WHEN posts.post_type = %s THEN 'topics' ELSE 'replies' END";
				$kind_args  = array( $types[0] );
				$value_sql  = 'CASE WHEN posts.post_type = %s THEN posts.ID WHEN posts.post_type = %s AND parent.post_type = %s THEN parent.ID ELSE NULL END';
				$value_args = array( $types[0], $types[1], $types[0] );
			}
			$slots = implode( ', ', array_fill( 0, count( $ids ), '%d' ) );
			$rows  = $bbp_db->get_results(
				$bbp_db->prepare(
					"SELECT posts.ID, {$kind_sql} AS kind, {$value_sql} AS reference_id
					FROM {$bbp_db->posts} AS posts
					LEFT JOIN {$bbp_db->posts} AS parent ON posts.post_parent = parent.ID
					WHERE posts.ID IN ({$slots})",
					array_merge( $kind_args, $value_args, $ids )
				)
			);
			if ( ! empty( $bbp_db->last_error ) ) {
				bbp_admin_repair_tool_batch_fail( $batch, 'source-read' );
				return $batch;
			}
			foreach ( $rows as $row ) {
				$expected[ (int) $row->ID ] = $row;
			}
		}
		if ( $batch['initial'] && ! bbp_admin_repair_tool_batch_save( $tool_id, $state, $batch, false, $dry_run ) ) {
			return $batch;
		}
		$work     = 0;
		$finished = 0;
		$verified = array();
		foreach ( $ids as $id ) {
			if ( $work >= $size ) {
				break;
			}
			$id     = (int) $id;
			$row    = isset( $expected[ $id ] ) ? $expected[ $id ] : null;
			$value  = isset( $row->reference_id ) ? (int) $row->reference_id : null;
			$kind   = ( null === $value ) ? 'stale' : $row->kind;
			$repair = bbp_admin_repair_single_metadata( 'post', $id, $key, $value, $size - $work, $dry_run, $metadata );
			if ( ! empty( $repair['code'] ) ) {
				bbp_admin_repair_tool_batch_fail( $batch, ( 2 === $repair['code'] ) ? 'metadata-read' : 'metadata-write' );
				return $batch;
			}
			$work += $repair['work'];
			if ( 'stale' === $kind ) {
				$batch['stale'] += $repair['operations'];
			} elseif ( $repair['changed'] ) {
				++$batch[ $kind ];
			}
			if ( ! $repair['complete'] ) {
				$state['cursor'] = $id;
				break;
			}

			$verified[ $id ] = $value;
			$state['cursor'] = $id + 1;
			++$finished;
		}
		if ( ! $dry_run ) {
			$mismatches = bbp_admin_repair_metadata_mismatches( 'post', $key, $verified );
			if ( false === $mismatches || ! empty( $mismatches ) ) {
				bbp_admin_repair_tool_batch_fail( $batch, false === $mismatches ? 'metadata-read' : 'verify' );
				return $batch;
			}
		}
		$complete = ! $has_more && ( count( $ids ) === $finished );
		$batch['batches'] += max( 1, (int) ceil( $work / $size ) );
		if ( ! bbp_admin_repair_tool_batch_save( $tool_id, $state, $batch, $complete, $dry_run, $fields, array( 'metadata' => $metadata ) ) ) {
			return $batch;
		}
	} while ( bbp_admin_repair_tool_batch_continue( $batch, $dry_run ) );

	return $batch;
}

/**
 * Clear cached metadata for a repaired bbPress post type.
 *
 * Repair tools update metadata directly in the database, bypassing the cache
 * invalidation performed by the WordPress metadata API.
 *
 * @since 2.6.17 bbPress (r7453)
 *
 * @param string $post_type Post type name.
 */
function bbp_admin_repair_clean_post_meta_cache( $post_type = '' ) {
	$bbp_db   = bbp_db();
	$query    = $bbp_db->prepare( "SELECT ID FROM {$bbp_db->posts} WHERE post_type = %s", $post_type );
	$post_ids = wp_parse_id_list( $bbp_db->get_col( $query ) );

	if ( ! empty( $post_ids ) ) {
		wp_cache_delete_multiple( $post_ids, 'post_meta' );
	}
}

/**
 * Admin repair page.
 *
 * @since 2.6.0 bbPress (r6277) Converted from bbPress 1.2
 * @since 2.6.0 bbPress (r5885) Upgraded to list-table UI
 *
 * @todo Use a real list table
 */
function bbp_admin_repair_page() {
	bbp_admin_tools_repair_upgrade_page( 'repair' );
}

/**
 * Recount topic replies.
 *
 * @since 2.1.0 bbPress (r3884)
 * @since 2.7.0 bbPress (r8222) Added Dry Run support.
 * @since 2.7.0 bbPress (r8265) Processes one resumable batch per call.
 *
 * @return array Tool result and completion flag.
 */
function bbp_admin_repair_topic_reply_count() {
	return bbp_admin_repair_topic_reply_count_process();
}

/**
 * Preview recounting topic replies.
 *
 * @since 2.7.0 bbPress (r8222)
 * @since 2.7.0 bbPress (r8265) Added resumable batching.
 * @since 2.7.0 bbPress (r8331) Previews the complete operation without changing saved progress.
 *
 * @return array Tool result and completion flag.
 */
function bbp_admin_repair_topic_reply_count_dry_run() {
	return bbp_admin_repair_topic_reply_count_process( true );
}

/**
 * Recount one batch of public or non-public replies per topic.
 *
 * @since 2.7.0 bbPress (r8265)
 *
 * @param bool $hidden  Whether to count non-public replies.
 * @param bool $dry_run Whether to preview without writes.
 * @return array Status code, repaired topic count, and completion flag.
 */
function bbp_admin_repair_topic_reply_count_batch( $hidden = false, $dry_run = false ) {
	$bbp_db  = bbp_db();
	$tool_id = $hidden ? 'bbp-topic-hidden-replies' : 'bbp-topic-replies';
	$key     = $hidden ? '_bbp_reply_count_hidden' : '_bbp_reply_count';
	$batch   = bbp_admin_repair_tool_batch_status( array( 'repaired' => 0 ) );
	$state   = false;
	if ( ! bbp_admin_repair_tool_batch_start( $tool_id, $state, $batch, 'bbp_admin_repair_tool_is_valid_checkpoint', array(), $dry_run ) ) {
		return $batch;
	}

	$tpt = bbp_get_topic_post_type();
	$rpt = bbp_get_reply_post_type();
	if ( false === $state ) {
		$upper_id = (int) $bbp_db->get_var( $bbp_db->prepare( "SELECT MAX(ID) FROM {$bbp_db->posts} WHERE post_type = %s", $tpt ) );
		if ( ! empty( $bbp_db->last_error ) ) {
			bbp_admin_repair_tool_batch_fail( $batch, 'source-read' );
			return $batch;
		}
		$state = array(
			'cursor'   => 0,
			'upper_id' => $upper_id
		);
		if ( ! bbp_admin_repair_tool_batch_save( $tool_id, $state, $batch, false, $dry_run ) ) {
			return $batch;
		}
	}

	// Preserve SQL status comparison, including the historical empty-list case
	$topic_statuses = $hidden ? array( '' ) : bbp_get_public_topic_statuses();
	$reply_statuses = $hidden ? bbp_get_non_public_reply_statuses() : bbp_get_public_reply_statuses();
	$topic_statuses = ! empty( $topic_statuses ) ? (array) $topic_statuses : array( '' );
	$reply_statuses = ! empty( $reply_statuses ) ? (array) $reply_statuses : array( '' );
	$topic_slots    = implode( ', ', array_fill( 0, count( $topic_statuses ), '%s' ) );
	$reply_slots    = implode( ', ', array_fill( 0, count( $reply_statuses ), '%s' ) );
	$size           = bbp_admin_repair_tool_batch_size( $tool_id );
	$preview_state  = bbp_admin_repair_tool_restore_preview( $state, $batch, array( 'repaired', 'batches' ) );
	$metadata       = isset( $preview_state['metadata'] ) ? $preview_state['metadata'] : null;
	do {
		$topics         = $bbp_db->get_results(
			$bbp_db->prepare(
				"SELECT ID, post_status IN ({$topic_slots}) AS is_public FROM {$bbp_db->posts}
				WHERE post_type = %s AND ID >= %d AND ID <= %d ORDER BY ID LIMIT %d",
				array_merge( $topic_statuses, array( $tpt, $state['cursor'], $state['upper_id'], $size + 1 ) )
			),
			ARRAY_A
		);
		if ( ! empty( $bbp_db->last_error ) ) {
			bbp_admin_repair_tool_batch_fail( $batch, 'source-read' );
			return $batch;
		}

		$work     = 0;
		$finished = 0;
		foreach ( $topics as $topic ) {
			if ( $work >= $size ) {
				break;
			}
			$topic_id = (int) $topic['ID'];
			$count    = null;
			if ( $hidden || ! empty( $topic['is_public'] ) ) {
				$count = (int) $bbp_db->get_var(
					$bbp_db->prepare(
						"SELECT COUNT(*) FROM {$bbp_db->posts} WHERE post_type = %s AND post_status IN ({$reply_slots}) AND post_parent = %d",
						array_merge( array( $rpt ), $reply_statuses, array( $topic_id ) )
					)
				);
				if ( ! empty( $bbp_db->last_error ) ) {
					bbp_admin_repair_tool_batch_fail( $batch, 'source-read' );
					return $batch;
				}
				// Hidden zero counts have no row; public zero counts retain one
				if ( $hidden && empty( $count ) ) {
					$count = null;
				}
			}

			$repair = bbp_admin_repair_single_metadata( 'post', $topic_id, $key, $count, $size - $work, $dry_run, $metadata );
			if ( ! empty( $repair['code'] ) ) {
				bbp_admin_repair_tool_batch_fail( $batch, ( 2 === $repair['code'] ) ? 'metadata-read' : 'metadata-write' );
				return $batch;
			}
			$work += $repair['work'];
			if ( $repair['changed'] ) {
				++$batch['repaired'];
			}
			if ( ! $repair['complete'] ) {
				$state['cursor'] = $topic_id;
				break;
			}
			$state['cursor'] = $topic_id + 1;
			++$finished;
		}

		$complete = count( $topics ) === $finished;
		$batch['batches'] += max( 1, (int) ceil( $work / $size ) );
		if ( ! bbp_admin_repair_tool_batch_save( $tool_id, $state, $batch, $complete, $dry_run, array( 'repaired', 'batches' ), array( 'metadata' => $metadata ) ) ) {
			return $batch;
		}
	} while ( bbp_admin_repair_tool_batch_continue( $batch, $dry_run ) );

	return $batch;
}

/**
 * Recount one topic-reply batch or preview the complete operation.
 *
 * @since 2.7.0 bbPress (r8222)
 * @since 2.7.0 bbPress (r8265) Added non-public counts and resumable batching.
 *
 * @param bool $dry_run Whether to preview changes without updating data.
 * @param bool $hidden  Whether to count non-public replies.
 * @return array Tool result and completion flag.
 */
function bbp_admin_repair_topic_reply_count_process( $dry_run = false, $hidden = false ) {
	if ( $hidden ) {
		$no_counts = esc_html__( '0 topic hidden reply counts to repair.', 'bbpress' );
		if ( $dry_run ) {
			/* translators: %s: Status of the dry-run process */
			$statement = esc_html__( 'Checking the number of pending, spammed, and trashed replies in each topic&hellip; %s', 'bbpress' );
		} else {
			/* translators: %s: Status of the counting process */
			$statement = esc_html__( 'Counting the number of pending, spammed, and trashed replies in each topic&hellip; %s', 'bbpress' );
		}
	} else {
		$no_counts = esc_html__( '0 topic reply counts to repair.', 'bbpress' );
		if ( $dry_run ) {
			/* translators: %s: Status of the dry-run process */
			$statement = esc_html__( 'Checking the number of replies in each topic&hellip; %s', 'bbpress' );
		} else {
			/* translators: %s: Status of the counting process */
			$statement = esc_html__( 'Counting the number of replies in each topic&hellip; %s', 'bbpress' );
		}
	}

	$batch     = bbp_admin_repair_topic_reply_count_batch( $hidden, $dry_run );

	if ( $hidden ) {
		if ( $dry_run ) {
			/* translators: %d: Number of topics whose hidden reply counts would be repaired */
			$result = sprintf( _n( 'Hidden reply counts would be repaired in %d topic.', 'Hidden reply counts would be repaired in %d topics.', $batch['repaired'], 'bbpress' ), $batch['repaired'] );
		} else {
			/* translators: %d: Number of topics whose hidden reply counts were repaired */
			$result = sprintf( _n( 'Hidden reply counts repaired in %d topic.', 'Hidden reply counts repaired in %d topics.', $batch['repaired'], 'bbpress' ), $batch['repaired'] );
		}
	} elseif ( $dry_run ) {
		/* translators: %d: Number of topics whose reply counts would be repaired */
		$result = sprintf( _n( 'Reply counts would be repaired in %d topic.', 'Reply counts would be repaired in %d topics.', $batch['repaired'], 'bbpress' ), $batch['repaired'] );
	} else {
		/* translators: %d: Number of topics whose reply counts were repaired */
		$result = sprintf( _n( 'Reply counts repaired in %d topic.', 'Reply counts repaired in %d topics.', $batch['repaired'], 'bbpress' ), $batch['repaired'] );
	}
	return bbp_admin_repair_tool_present_batch( $batch, $statement, $result, $dry_run, $dry_run && empty( $batch['repaired'] ), $no_counts );
}

/**
 * Recount topic voices.
 *
 * @since 2.1.0 bbPress (r3884)
 * @since 2.7.0 bbPress (r8221) Added Dry Run support.
 * @since 2.7.0 bbPress (r8281) Processes one resumable batch per call.
 *
 * @return array Tool result and completion flag.
 */
function bbp_admin_repair_topic_voice_count() {
	return bbp_admin_repair_topic_voice_count_process();
}

/**
 * Preview recounting topic voices.
 *
 * @since 2.7.0 bbPress (r8221)
 * @since 2.7.0 bbPress (r8281) Added resumable batching.
 * @since 2.7.0 bbPress (r8331) Previews the complete operation without changing saved progress.
 *
 * @return array Tool result and completion flag.
 */
function bbp_admin_repair_topic_voice_count_dry_run() {
	return bbp_admin_repair_topic_voice_count_process( true );
}

/**
 * Validate the phase and source context of a voice-count checkpoint.
 *
 * @since 2.7.0 bbPress (r8281)
 *
 * @param mixed $state   Saved checkpoint.
 * @param array $context Current post types and countable statuses.
 * @return bool Whether progress can resume.
 */
function bbp_admin_repair_topic_voice_count_is_valid_checkpoint( $state, $context ) {
	if ( ! bbp_admin_repair_tool_is_valid_checkpoint( $state, array( 'topic_upper' ) ) ) {
		return false;
	}
	if ( ! isset( $state['phase'], $state['context'] ) || ( $context !== $state['context'] ) || ! in_array( $state['phase'], array( 'engagements', 'voices' ), true ) ) {
		return false;
	}
	return ( 'voices' !== $state['phase'] ) || ( $state['upper_id'] === $state['topic_upper'] );
}

/**
 * Repair engagements and voice counts within one shared work budget.
 *
 * Reconcile source relationships first, then recount topics. Each phase keeps
 * fixed bounds and saved progress so retries never clear valid relationships.
 *
 * @since 2.7.0 bbPress (r8281)
 *
 * @param bool $dry_run Whether to preview without writes.
 * @return array Status code, repair counts, and progress flags.
 */
function bbp_admin_repair_topic_voice_count_batch( $dry_run = false ) {
	$bbp_db  = bbp_db();
	$tool_id = 'bbp-topic-engagements';
	$tpt     = bbp_get_topic_post_type();
	$context = array( $tpt, bbp_get_reply_post_type(), array_values( (array) bbp_get_public_topic_statuses() ), array_values( (array) bbp_get_public_reply_statuses() ) );
	$batch   = bbp_admin_repair_tool_batch_status(
		array(
			'engagements' => 0,
			'voices'      => 0,
		)
	);
	$state   = false;
	if ( ! bbp_admin_repair_tool_batch_start( $tool_id, $state, $batch, 'bbp_admin_repair_topic_voice_count_is_valid_checkpoint', $context, $dry_run ) ) {
		return $batch;
	}

	if ( false === $state ) {
		$upper_id = (int) $bbp_db->get_var( "SELECT MAX(meta_id) FROM {$bbp_db->postmeta} WHERE meta_key IN ('_bbp_topic_id', '_bbp_engagement')" );
		if ( ! empty( $bbp_db->last_error ) ) {
			bbp_admin_repair_tool_batch_fail( $batch, 'source-read' );
			return $batch;
		}
		$topic_upper = (int) $bbp_db->get_var( $bbp_db->prepare( "SELECT MAX(ID) FROM {$bbp_db->posts} WHERE post_type = %s", $tpt ) );
		if ( ! empty( $bbp_db->last_error ) ) {
			bbp_admin_repair_tool_batch_fail( $batch, 'source-read' );
			return $batch;
		}
		$state = array(
			'phase'       => 'engagements',
			'cursor'      => 0,
			'upper_id'    => $upper_id,
			'topic_upper' => $topic_upper,
			'context'     => $context
		);
		if ( ! bbp_admin_repair_tool_batch_save( $tool_id, $state, $batch, false, $dry_run ) ) {
			return $batch;
		}
	}
	$size          = bbp_admin_repair_tool_batch_size( $tool_id );
	$preview_state = bbp_admin_repair_tool_restore_preview( $state, $batch, array( 'engagements', 'voices', 'batches' ) );
	$planned       = isset( $preview_state['planned'] ) ? $preview_state['planned'] : array();
	$topics        = isset( $preview_state['topics'] ) ? $preview_state['topics'] : array();
	$metadata      = isset( $preview_state['metadata'] ) ? $preview_state['metadata'] : null;
	do {
		$work   = 0;
		$source = bbp_admin_repair_get_engagements_query( $context[2], $context[3] );
		if ( 'engagements' === $state['phase'] ) {
			$rows = $bbp_db->get_results(
				$bbp_db->prepare(
					"SELECT postmeta.meta_id, postmeta.post_id, postmeta.meta_value, (postmeta.meta_key = '_bbp_topic_id') AS is_source
					FROM {$bbp_db->postmeta} AS postmeta
					WHERE postmeta.meta_id > %d AND postmeta.meta_id <= %d
						AND (postmeta.meta_key = '_bbp_topic_id' OR (postmeta.meta_key = '_bbp_engagement'
							AND EXISTS (SELECT 1 FROM {$bbp_db->posts} AS topics WHERE topics.ID = postmeta.post_id AND topics.post_type = %s)))
					ORDER BY postmeta.meta_id LIMIT %d",
					$state['cursor'],
					$state['upper_id'],
					$tpt,
					$size + 1
				)
			);
			if ( ! empty( $bbp_db->last_error ) ) {
				bbp_admin_repair_tool_batch_fail( $batch, 'source-read' );
				return $batch;
			}
			$has_more = count( $rows ) > $size;
			$rows     = array_slice( $rows, 0, $size );
			$repair   = bbp_admin_repair_engagement_rows( $rows, $source, $dry_run, $planned );
			if ( ! empty( $repair['code'] ) ) {
				bbp_admin_repair_tool_batch_fail( $batch, $repair['error'] );
				return $batch;
			}
			$topics += $repair['topics'];
			$batch['engagements'] = count( $topics );
			$work                 = count( $rows );
			if ( ! empty( $rows ) ) {
				$state['cursor'] = $repair['cursor'];
			}
			if ( ! $has_more ) {
				$state['phase']    = 'voices';
				$state['cursor']   = 0;
				$state['upper_id'] = $state['topic_upper'];
			}
		}
		if ( ( 'voices' === $state['phase'] ) && ( $work < $size ) ) {
			$remaining = $size - $work;
			$ids       = $bbp_db->get_col( $bbp_db->prepare( "SELECT ID FROM {$bbp_db->posts} WHERE post_type = %s AND ID >= %d AND ID <= %d ORDER BY ID LIMIT %d", $tpt, $state['cursor'], $state['upper_id'], $remaining + 1 ) );
			if ( ! empty( $bbp_db->last_error ) ) {
				bbp_admin_repair_tool_batch_fail( $batch, 'source-read' );
				return $batch;
			}
			$has_more = count( $ids ) > $remaining;
			$ids      = array_slice( $ids, 0, $remaining );
			$counts   = array();
			if ( ! empty( $ids ) ) {
				$slots = implode( ', ', array_fill( 0, count( $ids ), '%d' ) );
				$rows  = $bbp_db->get_results(
					'SELECT relations.post_id, COUNT(DISTINCT relations.user_id) AS voice_count FROM (' .
					$source . $bbp_db->prepare( " AND topics.ID IN ({$slots})", $ids ) .
					') AS relations GROUP BY relations.post_id'
				);
				if ( ! empty( $bbp_db->last_error ) ) {
					bbp_admin_repair_tool_batch_fail( $batch, 'source-read' );
					return $batch;
				}
				$counts = wp_list_pluck( $rows, 'voice_count', 'post_id' );
			}
			$finished = 0;
			$verified = array();
			foreach ( $ids as $id ) {
				if ( $work >= $size ) {
					break;
				}
				$id    = (int) $id;
				$count = isset( $counts[ $id ] ) ? (int) $counts[ $id ] : 0;
				$value  = $count ? $count : null;
				$repair = bbp_admin_repair_single_metadata( 'post', $id, '_bbp_voice_count', $value, $size - $work, $dry_run, $metadata );
				if ( ! empty( $repair['code'] ) ) {
					bbp_admin_repair_tool_batch_fail( $batch, ( 2 === $repair['code'] ) ? 'metadata-read' : 'metadata-write' );
					return $batch;
				}
				$work += $repair['work'];
				if ( $repair['changed'] ) {
					++$batch['voices'];
				}
				if ( ! $repair['complete'] ) {
					$state['cursor'] = $id;
					break;
				}
				$verified[ $id ] = $value;
				$state['cursor'] = $id + 1;
				++$finished;
			}
			if ( ! $dry_run ) {
				$mismatches = bbp_admin_repair_metadata_mismatches( 'post', '_bbp_voice_count', $verified );
				if ( false === $mismatches || ! empty( $mismatches ) ) {
					bbp_admin_repair_tool_batch_fail( $batch, false === $mismatches ? 'metadata-read' : 'verify' );
					return $batch;
				}
			}
			$batch['complete'] = ! $has_more && ( count( $ids ) === $finished );
		}
		$batch['batches'] += max( 1, (int) ceil( $work / $size ) );
		if ( ! bbp_admin_repair_tool_batch_save(
			$tool_id,
			$state,
			$batch,
			$batch['complete'],
			$dry_run,
			array( 'engagements', 'voices', 'batches' ),
			array(
				'planned'  => $planned,
				'topics'   => $topics,
				'metadata' => $metadata,
			)
		) ) {
			return $batch;
		}
	} while ( bbp_admin_repair_tool_batch_continue( $batch, $dry_run ) );

	return $batch;
}

/**
 * Recount or preview topic voices.
 *
 * @since 2.7.0 bbPress (r8221)
 * @since 2.7.0 bbPress (r8281) Processes one resumable batch per call.
 *
 * @param bool $dry_run Whether to preview changes without updating data.
 * @return array Tool result and completion flag.
 */
function bbp_admin_repair_topic_voice_count_process( $dry_run = false ) {
	if ( $dry_run ) {
		/* translators: %s: Status of the dry-run process */
		$statement = esc_html__( 'Checking the number of voices in each topic&hellip; %s', 'bbpress' );
	} else {
		/* translators: %s: Status of the counting process */
		$statement = esc_html__( 'Counting the number of voices in each topic&hellip; %s', 'bbpress' );
	}
	$batch     = bbp_admin_repair_topic_voice_count_batch( $dry_run );
	if ( $dry_run ) {
		/* translators: %d: Number of topics whose engagements would be repaired */
		$engagements = sprintf( _n( 'Engagements would be repaired in %d topic.', 'Engagements would be repaired in %d topics.', $batch['engagements'], 'bbpress' ), $batch['engagements'] );
		/* translators: %d: Number of topics whose voice counts would be repaired */
		$voices = sprintf( _n( 'Voice counts would be repaired in %d topic.', 'Voice counts would be repaired in %d topics.', $batch['voices'], 'bbpress' ), $batch['voices'] );
	} else {
		/* translators: %d: Number of topics whose engagements were repaired */
		$engagements = sprintf( _n( 'Engagements repaired in %d topic.', 'Engagements repaired in %d topics.', $batch['engagements'], 'bbpress' ), $batch['engagements'] );
		/* translators: %d: Number of topics whose voice counts were repaired */
		$voices = sprintf( _n( 'Voice counts repaired in %d topic.', 'Voice counts repaired in %d topics.', $batch['voices'], 'bbpress' ), $batch['voices'] );
	}
	return bbp_admin_repair_tool_present_batch(
		$batch,
		$statement,
		$engagements . "\n" . $voices,
		$dry_run,
		$dry_run && ! $batch['engagements'] && ! $batch['voices'],
		esc_html__( '0 topic engagements or voice counts to repair.', 'bbpress' )
	);
}

/**
 * Recount non-public replies per topic (pending/spammed/trashed).
 *
 * @since 2.1.0 bbPress (r3884)
 * @since 2.7.0 bbPress (r8223) Added Dry Run support.
 * @since 2.7.0 bbPress (r8265) Processes one resumable batch per call.
 *
 * @return array Tool result and completion flag.
 */
function bbp_admin_repair_topic_hidden_reply_count() {
	return bbp_admin_repair_topic_hidden_reply_count_process();
}

/**
 * Preview recounting non-public replies per topic.
 *
 * @since 2.7.0 bbPress (r8223)
 * @since 2.7.0 bbPress (r8265) Added resumable batching.
 * @since 2.7.0 bbPress (r8331) Previews the complete operation without changing saved progress.
 *
 * @return array Tool result and completion flag.
 */
function bbp_admin_repair_topic_hidden_reply_count_dry_run() {
	return bbp_admin_repair_topic_hidden_reply_count_process( true );
}

/**
 * Recount or preview non-public replies per topic.
 *
 * @since 2.7.0 bbPress (r8223)
 * @since 2.7.0 bbPress (r8265) Processes one resumable batch per call.
 *
 * @param bool $dry_run Whether to preview changes without updating data.
 * @return array Tool result and completion flag.
 */
function bbp_admin_repair_topic_hidden_reply_count_process( $dry_run = false ) {
	return bbp_admin_repair_topic_reply_count_process( $dry_run, true );
}

/**
 * Recount one bounded batch of forum topics or replies.
 *
 * @since 2.7.0 bbPress (r8284)
 *
 * @param string $type    Count type: topics, replies, or hidden-replies.
 * @param bool   $dry_run Whether to preview without writes.
 * @return array Status code, repaired forum count, and progress flags.
 */
function bbp_admin_repair_forum_count_batch( $type = 'topics', $dry_run = false ) {
	$worker = new BBP_Admin_Forum_Counts( $type, $dry_run );
	return $worker->run();
}

/**
 * Format a shared forum recount batch result.
 *
 * @since 2.7.0 bbPress (r8284)
 *
 * @param string $type    Count type.
 * @param bool   $dry_run Whether to preview without writes.
 * @return array Tool result and completion flag.
 */
function bbp_admin_repair_forum_count_process( $type = 'topics', $dry_run = false ) {
	if ( 'topics' === $type ) {
		$none = esc_html__( '0 forum topic counts to repair.', 'bbpress' );
		if ( $dry_run ) {
			/* translators: %s: Status of the dry-run process */
			$statement = esc_html__( 'Checking the number of topics in each forum&hellip; %s', 'bbpress' );
		} else {
			/* translators: %s: Status of the counting process */
			$statement = esc_html__( 'Counting the number of topics in each forum&hellip; %s', 'bbpress' );
		}
	} elseif ( 'hidden-replies' === $type ) {
		$none = esc_html__( '0 forum hidden reply counts to repair.', 'bbpress' );
		if ( $dry_run ) {
			/* translators: %s: Status of the dry-run process */
			$statement = esc_html__( 'Checking the number of pending, spammed, and trashed replies in each forum&hellip; %s', 'bbpress' );
		} else {
			/* translators: %s: Status of the counting process */
			$statement = esc_html__( 'Counting the number of pending, spammed, and trashed replies in each forum&hellip; %s', 'bbpress' );
		}
	} else {
		$none = esc_html__( '0 forum reply counts to repair.', 'bbpress' );
		if ( $dry_run ) {
			/* translators: %s: Status of the dry-run process */
			$statement = esc_html__( 'Checking the number of replies in each forum&hellip; %s', 'bbpress' );
		} else {
			/* translators: %s: Status of the counting process */
			$statement = esc_html__( 'Counting the number of replies in each forum&hellip; %s', 'bbpress' );
		}
	}
	$batch     = bbp_admin_repair_forum_count_batch( $type, $dry_run );
	if ( 'topics' === $type ) {
		$result = $dry_run
			/* translators: %d: Number of forums whose topic counts would be repaired */
			? sprintf( _n( 'Topic counts would be repaired in %d forum.', 'Topic counts would be repaired in %d forums.', $batch['repaired'], 'bbpress' ), $batch['repaired'] )
			/* translators: %d: Number of forums whose topic counts were repaired */
			: sprintf( _n( 'Topic counts repaired in %d forum.', 'Topic counts repaired in %d forums.', $batch['repaired'], 'bbpress' ), $batch['repaired'] );
	} elseif ( 'hidden-replies' === $type ) {
		$result = $dry_run
			/* translators: %d: Number of forums whose hidden reply counts would be repaired */
			? sprintf( _n( 'Hidden reply counts would be repaired in %d forum.', 'Hidden reply counts would be repaired in %d forums.', $batch['repaired'], 'bbpress' ), $batch['repaired'] )
			/* translators: %d: Number of forums whose hidden reply counts were repaired */
			: sprintf( _n( 'Hidden reply counts repaired in %d forum.', 'Hidden reply counts repaired in %d forums.', $batch['repaired'], 'bbpress' ), $batch['repaired'] );
	} else {
		$result = $dry_run
			/* translators: %d: Number of forums whose reply counts would be repaired */
			? sprintf( _n( 'Reply counts would be repaired in %d forum.', 'Reply counts would be repaired in %d forums.', $batch['repaired'], 'bbpress' ), $batch['repaired'] )
			/* translators: %d: Number of forums whose reply counts were repaired */
			: sprintf( _n( 'Reply counts repaired in %d forum.', 'Reply counts repaired in %d forums.', $batch['repaired'], 'bbpress' ), $batch['repaired'] );
	}
	return bbp_admin_repair_tool_present_batch( $batch, $statement, $result, $dry_run, ! $batch['repaired'] && ( $dry_run || $batch['initial'] ), $none );
}

/**
 * Recount forum topics.
 *
 * @since 2.1.0 bbPress (r3884)
 * @since 2.7.0 bbPress (r8242) Added Dry Run support.
 * @since 2.7.0 bbPress (r8284) Shares resumable forum recounts.
 *
 * @return array Tool result and completion flag.
 */
function bbp_admin_repair_forum_topic_count() {
	return bbp_admin_repair_forum_topic_count_process();
}

/**
 * Preview recounting forum topics.
 *
 * @since 2.7.0 bbPress (r8242)
 * @since 2.7.0 bbPress (r8284) Shares resumable forum recounts.
 *
 * @return array Tool result and completion flag.
 */
function bbp_admin_repair_forum_topic_count_dry_run() {
	return bbp_admin_repair_forum_topic_count_process( true );
}

/**
 * Repair or preview repairing forum topic counts.
 *
 * @since 2.7.0 bbPress (r8242)
 * @since 2.7.0 bbPress (r8284) Processes one resumable batch per call.
 *
 * @param bool $dry_run Whether to preview the repair without updating data.
 * @return array Tool result and completion flag.
 */
function bbp_admin_repair_forum_topic_count_process( $dry_run = false ) {
	return bbp_admin_repair_forum_count_process( 'topics', $dry_run );
}

/**
 * Recount forum subforums.
 *
 * @since 2.7.0 bbPress (r7971)
 * @since 2.7.0 bbPress (r8243) Added Dry Run support.
 * @since 2.7.0 bbPress (r8274) Processes one resumable batch per call.
 *
 * @return array Tool result and completion flag.
 */
function bbp_admin_repair_forum_subforum_count() {
	return bbp_admin_repair_forum_subforum_count_process();
}

/**
 * Preview recounting forum subforums.
 *
 * @since 2.7.0 bbPress (r8243)
 * @since 2.7.0 bbPress (r8274) Added resumable batching.
 * @since 2.7.0 bbPress (r8331) Previews the complete operation without changing saved progress.
 *
 * @return array Tool result and completion flag.
 */
function bbp_admin_repair_forum_subforum_count_dry_run() {
	return bbp_admin_repair_forum_subforum_count_process( true );
}

/**
 * Read direct countable child-forum counts without populating caches.
 *
 * @since 2.7.0 bbPress (r8274)
 *
 * @param array|null $forum_ids Optional IDs to inspect, or null for all.
 * @return array Status code and expected counts indexed by forum ID.
 */
function bbp_admin_repair_forum_subforum_counts( $forum_ids = null ) {
	$bbp_db    = bbp_db();
	$fpt       = bbp_get_forum_post_type();
	$eligible  = array_keys( get_post_stati() );
	$countable = bbp_get_unique_array_values( bbp_get_countable_forum_statuses() );
	$statuses  = implode( ', ', array_fill( 0, count( $eligible ), '%s' ) );
	$children  = ! empty( $countable )
		? 'AND `children`.`post_status` IN (' . implode( ', ', array_fill( 0, count( $countable ), '%s' ) ) . ')'
		: 'AND 1 = 0';
	$scope     = '';
	$args      = array_merge( array( $fpt ), $countable, array( $fpt ), $eligible );
	if ( null !== $forum_ids ) {
		$forum_ids = wp_parse_id_list( $forum_ids );
		$scope     = 'AND 1 = 0';
		if ( ! empty( $forum_ids ) ) {
			$scope = 'AND `forums`.`ID` IN (' . implode( ', ', array_fill( 0, count( $forum_ids ), '%d' ) ) . ')';
			$args  = array_merge( $args, $forum_ids );
		}
	}
	$rows = $bbp_db->get_results(
		$bbp_db->prepare(
			"SELECT `forums`.`ID`, COUNT( `children`.`ID` ) AS `subforum_count`
			FROM `{$bbp_db->posts}` AS `forums`
			LEFT JOIN `{$bbp_db->posts}` AS `children`
				ON `children`.`post_parent` = `forums`.`ID` AND `children`.`post_type` = %s
				{$children}
			WHERE `forums`.`post_type` = %s AND `forums`.`post_status` IN ({$statuses})
				{$scope}
			GROUP BY `forums`.`ID` ORDER BY `forums`.`ID`",
			$args
		),
		ARRAY_A
	);
	if ( ! empty( $bbp_db->last_error ) ) {
		return array( 'code' => 3 );
	}
	$counts = array();
	foreach ( $rows as $row ) {
		$counts[ (int) $row['ID'] ] = (int) $row['subforum_count'];
	}
	return array(
		'code'   => 0,
		'counts' => $counts
	);
}

/**
 * Compare calculated forum subforum counts with their stored metadata.
 *
 * @since 2.7.0 bbPress (r8243)
 * @since 2.7.0 bbPress (r8274) Accepts a bounded list of forum IDs.
 *
 * @param array|null $forum_ids Optional IDs to inspect, or null for all.
 * @return array Status code, forum IDs, and number of forums to repair.
 */
function bbp_admin_repair_forum_subforum_count_get_status( $forum_ids = null ) {
	$result = bbp_admin_repair_forum_subforum_counts( $forum_ids );
	if ( ! empty( $result['code'] ) ) {
		return $result;
	}
	$mismatches = bbp_admin_repair_metadata_mismatches( 'post', '_bbp_forum_subforum_count', $result['counts'] );
	if ( false === $mismatches ) {
		return array( 'code' => 4 );
	}
	return array(
		'code'         => 0,
		'forum_ids'    => array_keys( $result['counts'] ),
		'repair_count' => count( $mismatches )
	);
}

/**
 * Recount one bounded batch of direct subforum counts.
 *
 * @since 2.7.0 bbPress (r8274)
 *
 * @param bool $dry_run Whether to preview without writes.
 * @return array Status code, repaired forum count, and progress flags.
 */
function bbp_admin_repair_forum_subforum_count_batch( $dry_run = false ) {
	$bbp_db  = bbp_db();
	$tool_id = 'bbp-forum-subforums';
	$fpt     = bbp_get_forum_post_type();
	$batch   = bbp_admin_repair_tool_batch_status(
		array(
			'repaired' => 0,
			'initial'  => false,
		)
	);
	$state   = false;
	if ( ! bbp_admin_repair_tool_batch_start( $tool_id, $state, $batch, 'bbp_admin_repair_tool_is_matching_checkpoint', array( 'post_type' => $fpt ), $dry_run ) ) {
		return $batch;
	}

	$batch['initial'] = ( false === $state );
	if ( $batch['initial'] ) {
		$upper_id = (int) $bbp_db->get_var( $bbp_db->prepare( "SELECT MAX(ID) FROM {$bbp_db->posts} WHERE post_type = %s", $fpt ) );
		if ( ! empty( $bbp_db->last_error ) ) {
			bbp_admin_repair_tool_batch_fail( $batch, 'source-read' );
			return $batch;
		}
		$state = array(
			'cursor'    => 0,
			'upper_id'  => $upper_id,
			'post_type' => $fpt
		);
	}
	$eligible = array_keys( get_post_stati() );
	$slots    = implode( ', ', array_fill( 0, count( $eligible ), '%s' ) );
	$size     = bbp_admin_repair_tool_batch_size( $tool_id );
	$preview_state = bbp_admin_repair_tool_restore_preview( $state, $batch, array( 'repaired', 'initial', 'batches' ) );
	$metadata = isset( $preview_state['metadata'] ) ? $preview_state['metadata'] : null;
	do {
		$ids      = $bbp_db->get_col(
			$bbp_db->prepare(
				"SELECT ID FROM {$bbp_db->posts} WHERE post_type = %s AND post_status IN ({$slots})
				AND ID >= %d AND ID <= %d ORDER BY ID LIMIT %d",
				array_merge( array( $fpt ), $eligible, array( $state['cursor'], $state['upper_id'], $size + 1 ) )
			)
		);
		if ( ! empty( $bbp_db->last_error ) ) {
			bbp_admin_repair_tool_batch_fail( $batch, 'source-read' );
			return $batch;
		}
		$has_more = count( $ids ) > $size;
		$ids      = array_slice( $ids, 0, $size );
		$counts   = bbp_admin_repair_forum_subforum_counts( $ids );
		if ( ! empty( $counts['code'] ) ) {
			bbp_admin_repair_tool_batch_fail( $batch, 'source-read' );
			return $batch;
		}
		if ( $batch['initial'] && ! bbp_admin_repair_tool_batch_save( $tool_id, $state, $batch, false, $dry_run ) ) {
			return $batch;
		}
		$work      = 0;
		$finished  = 0;
		$completed = array();
		foreach ( $counts['counts'] as $id => $count ) {
			if ( $work >= $size ) {
				break;
			}
			$repair = bbp_admin_repair_single_metadata( 'post', $id, '_bbp_forum_subforum_count', $count, $size - $work, $dry_run, $metadata );
			if ( ! empty( $repair['code'] ) ) {
				bbp_admin_repair_tool_batch_fail( $batch, ( 2 === $repair['code'] ) ? 'metadata-read' : 'metadata-write' );
				return $batch;
			}
			$work += $repair['work'];
			if ( $repair['changed'] ) {
				++$batch['repaired'];
			}
			if ( ! $repair['complete'] ) {
				$state['cursor'] = $id;
				break;
			}
			if ( ! $dry_run && $repair['changed'] ) {
				// Preserve the count updater's return filter for completed repairs
				apply_filters( 'bbp_update_forum_subforum_count', $count, $id );
			}
			$completed[]     = $id;
			$state['cursor'] = $id + 1;
			++$finished;
		}
		if ( ! $dry_run && ! empty( $completed ) ) {
			$status = bbp_admin_repair_forum_subforum_count_get_status( $completed );
			if ( ! empty( $status['code'] ) || ! empty( $status['repair_count'] ) ) {
				bbp_admin_repair_tool_batch_fail( $batch, ! empty( $status['code'] ) ? ( ( 3 === $status['code'] ) ? 'source-read' : 'metadata-read' ) : 'verify' );
				return $batch;
			}
		}
		$complete = ! $has_more && ( count( $counts['counts'] ) === $finished );
		$batch['batches'] += max( 1, (int) ceil( $work / $size ) );
		if ( ! bbp_admin_repair_tool_batch_save( $tool_id, $state, $batch, $complete, $dry_run, array( 'repaired', 'initial', 'batches' ), array( 'metadata' => $metadata ) ) ) {
			return $batch;
		}
	} while ( bbp_admin_repair_tool_batch_continue( $batch, $dry_run ) );

	return $batch;
}

/**
 * Repair one subforum-count batch or preview the complete operation.
 *
 * @since 2.7.0 bbPress (r8243)
 * @since 2.7.0 bbPress (r8274) Processes one resumable batch per call.
 *
 * @param bool $dry_run Whether to preview without updating data.
 * @return array Tool result and completion flag.
 */
function bbp_admin_repair_forum_subforum_count_process( $dry_run = false ) {
	if ( $dry_run ) {
		/* translators: %s: Status of the dry-run process */
		$statement = esc_html__( 'Checking the number of subforums in each forum&hellip; %s', 'bbpress' );
	} else {
		/* translators: %s: Status of the counting process */
		$statement = esc_html__( 'Counting the number of subforums in each forum&hellip; %s', 'bbpress' );
	}
	$batch     = bbp_admin_repair_forum_subforum_count_batch( $dry_run );
	if ( $dry_run ) {
		/* translators: %d: Number of forums whose subforum counts would be repaired */
		$result = sprintf( _n( 'Subforum counts would be repaired in %d forum.', 'Subforum counts would be repaired in %d forums.', $batch['repaired'], 'bbpress' ), $batch['repaired'] );
	} else {
		/* translators: %d: Number of forums whose subforum counts were repaired */
		$result = sprintf( _n( 'Subforum counts repaired in %d forum.', 'Subforum counts repaired in %d forums.', $batch['repaired'], 'bbpress' ), $batch['repaired'] );
	}
	return bbp_admin_repair_tool_present_batch(
		$batch,
		$statement,
		$result,
		$dry_run,
		empty( $batch['repaired'] ) && ( $dry_run || $batch['initial'] ),
		esc_html__( '0 forum subforum counts to repair.', 'bbpress' )
	);
}

/**
 * Recount topic in each topic-tag.
 *
 * @since 2.6.0 bbPress (r6256)
 * @since 2.7.0 bbPress (r8244) Added Dry Run support.
 * @since 2.7.0 bbPress (r8267) Processes one resumable batch per call.
 *
 * @return array Tool result and completion flag.
 */
function bbp_admin_repair_topic_tag_count() {
	return bbp_admin_repair_topic_tag_count_process();
}

/**
 * Preview recounting topics in each topic-tag.
 *
 * @since 2.7.0 bbPress (r8244)
 * @since 2.7.0 bbPress (r8267) Added resumable batching.
 * @since 2.7.0 bbPress (r8331) Previews the complete operation without changing saved progress.
 *
 * @return array Tool result and completion flag.
 */
function bbp_admin_repair_topic_tag_count_dry_run() {
	return bbp_admin_repair_topic_tag_count_process( true );
}

/**
 * Compare calculated topic-tag counts with their stored taxonomy counts.
 *
 * @since 2.7.0 bbPress (r8244)
 * @since 2.7.0 bbPress (r8267) Accepts a bounded list of term taxonomy IDs.
 *
 * @param object     $taxonomy          Topic-tag taxonomy object.
 * @param array|null $term_taxonomy_ids Optional IDs to inspect, or null for all.
 * @return array Status code, term taxonomy IDs, and number of tags to repair.
 */
function bbp_admin_repair_topic_tag_count_get_status( $taxonomy, $term_taxonomy_ids = null ) {
	$bbp_db          = bbp_db();
	$tax_id          = $taxonomy->name;
	$object_types    = (array) $taxonomy->object_type;
	$object_statuses = bbp_get_unique_array_values( bbp_get_public_topic_statuses() );

	foreach ( $object_types as &$object_type ) {
		list( $object_type ) = explode( ':', $object_type );
	}
	unset( $object_type );

	$object_types = bbp_get_unique_array_values( array_filter( $object_types, 'post_type_exists' ) );
	$type_sql     = 'AND 1 = 0';
	$type_args    = array();

	if ( ! empty( $object_types ) && ! empty( $object_statuses ) ) {
		$status_placeholders = implode( ', ', array_fill( 0, count( $object_statuses ), '%s' ) );
		$type_placeholders   = implode( ', ', array_fill( 0, count( $object_types ), '%s' ) );
		$type_sql            = "AND `content`.`post_status` IN ( {$status_placeholders} )
			AND `content`.`post_type` IN ( {$type_placeholders} )";
		$type_args           = array_merge( $object_statuses, $object_types );
	}

	$id_sql     = '';
	$query_args = array_merge( $type_args, array( $tax_id ) );
	if ( null !== $term_taxonomy_ids ) {
		$term_taxonomy_ids = wp_parse_id_list( $term_taxonomy_ids );
		$id_sql            = 'AND 1 = 0';
		if ( ! empty( $term_taxonomy_ids ) ) {
			$id_slots   = implode( ', ', array_fill( 0, count( $term_taxonomy_ids ), '%d' ) );
			$id_sql     = "AND `termtaxonomy`.`term_taxonomy_id` IN ({$id_slots})";
			$query_args = array_merge( $query_args, $term_taxonomy_ids );
		}
	}
	$rows       = $bbp_db->get_results(
		$bbp_db->prepare(
			"SELECT `termtaxonomy`.`term_taxonomy_id`, `termtaxonomy`.`count` AS `stored_count`, COUNT( `content`.`ID` ) AS `expected_count`
			FROM `{$bbp_db->term_taxonomy}` AS `termtaxonomy`
			INNER JOIN `{$bbp_db->terms}` AS `terms`
				ON `terms`.`term_id` = `termtaxonomy`.`term_id`
			LEFT JOIN `{$bbp_db->term_relationships}` AS `relationships`
				ON `relationships`.`term_taxonomy_id` = `termtaxonomy`.`term_taxonomy_id`
			LEFT JOIN `{$bbp_db->posts}` AS `content`
				ON `content`.`ID` = `relationships`.`object_id`
				{$type_sql}
			WHERE `termtaxonomy`.`taxonomy` = %s
				{$id_sql}
			GROUP BY `termtaxonomy`.`term_taxonomy_id`, `termtaxonomy`.`count`
			ORDER BY `termtaxonomy`.`term_taxonomy_id`",
			$query_args
		),
		ARRAY_A
	);

	if ( ! empty( $bbp_db->last_error ) ) {
		return array( 'code' => 3 );
	}

	$term_taxonomy_ids = array();
	$repair_count      = 0;
	foreach ( $rows as $row ) {
		$term_taxonomy_ids[] = (int) $row['term_taxonomy_id'];
		if ( (int) $row['stored_count'] !== (int) $row['expected_count'] ) {
			++$repair_count;
		}
	}

	return array(
		'code'              => 0,
		'term_taxonomy_ids' => $term_taxonomy_ids,
		'repair_count'      => $repair_count,
	);
}

/**
 * Recount one bounded batch of topic-tags.
 *
 * @since 2.7.0 bbPress (r8267)
 *
 * @param object   $taxonomy Topic-tag taxonomy object.
 * @param callable $callback Configured term count callback.
 * @param bool     $dry_run  Whether to preview without writes.
 * @return array Status code, tag counts, and initial/completion flags.
 */
function bbp_admin_repair_topic_tag_count_batch( $taxonomy, $callback, $dry_run = false ) {
	$bbp_db   = bbp_db();
	$tool_id  = 'bbp-topic-tags';
	$standard = ( 'bbp_update_topic_tag_count' === $callback );
	$batch    = bbp_admin_repair_tool_batch_status(
		array(
			'repaired'  => 0,
			'processed' => 0,
			'initial'   => false,
		)
	);
	$state    = false;
	if ( ! bbp_admin_repair_tool_batch_start( $tool_id, $state, $batch, 'bbp_admin_repair_tool_is_matching_checkpoint', array( 'taxonomy' => $taxonomy->name ), $dry_run ) ) {
		return $batch;
	}

	if ( $dry_run && ! $standard ) {
		bbp_admin_repair_tool_batch_fail( $batch, 'callback' );
		return $batch;
	}
	$batch['initial'] = ( false === $state );
	if ( $batch['initial'] ) {
		$upper_id = (int) $bbp_db->get_var( $bbp_db->prepare( "SELECT MAX(term_taxonomy_id) FROM {$bbp_db->term_taxonomy} WHERE taxonomy = %s", $taxonomy->name ) );
		if ( ! empty( $bbp_db->last_error ) ) {
			bbp_admin_repair_tool_batch_fail( $batch, 'source-read' );
			return $batch;
		}
		$state = array(
			'cursor'   => 0,
			'upper_id' => $upper_id,
			'taxonomy' => $taxonomy->name
		);
	}

	$size = bbp_admin_repair_tool_batch_size( $tool_id );
	bbp_admin_repair_tool_restore_preview( $state, $batch, array( 'repaired', 'processed', 'initial', 'batches' ) );
	do {
		$rows = $bbp_db->get_results(
			$bbp_db->prepare(
				"SELECT taxonomy.term_taxonomy_id, taxonomy.term_id
				FROM {$bbp_db->term_taxonomy} AS taxonomy
				INNER JOIN {$bbp_db->terms} AS terms ON terms.term_id = taxonomy.term_id
				WHERE taxonomy.taxonomy = %s AND taxonomy.term_taxonomy_id > %d AND taxonomy.term_taxonomy_id <= %d
				ORDER BY taxonomy.term_taxonomy_id LIMIT %d",
				$taxonomy->name,
				$state['cursor'],
				$state['upper_id'],
				$size + 1
			),
			ARRAY_A
		);
		if ( ! empty( $bbp_db->last_error ) ) {
			bbp_admin_repair_tool_batch_fail( $batch, 'source-read' );
			return $batch;
		}
		$complete = count( $rows ) <= $size;
		$rows     = array_slice( $rows, 0, $size );
		$ids      = array_map( 'intval', wp_list_pluck( $rows, 'term_taxonomy_id' ) );
		$term_ids = array_map( 'intval', wp_list_pluck( $rows, 'term_id' ) );

		if ( $standard ) {
			$status = bbp_admin_repair_topic_tag_count_get_status( $taxonomy, $ids );
			if ( ! empty( $status['code'] ) ) {
				bbp_admin_repair_tool_batch_fail( $batch, 'source-read' );
				return $batch;
			}
			$batch['repaired'] += $status['repair_count'];
		} elseif ( ! empty( $ids ) ) {
			// Keep the existing term-query filters for custom count callbacks
			$terms = get_terms(
				array(
					'taxonomy'               => $taxonomy->name,
					'hide_empty'             => false,
					'include'                => $term_ids,
					'update_term_meta_cache' => false
				)
			);
			if ( is_wp_error( $terms ) || ! empty( $bbp_db->last_error ) ) {
				bbp_admin_repair_tool_batch_fail( $batch, 'source-read' );
				return $batch;
			}
			$ids = array_values( array_intersect( $ids, wp_parse_id_list( wp_list_pluck( $terms, 'term_taxonomy_id' ) ) ) );
		}
		$batch['processed'] += count( $ids );

		if ( ! $dry_run ) {
			if ( $batch['initial'] && ! bbp_admin_repair_tool_batch_save( $tool_id, $state, $batch, false, $dry_run ) ) {
				return $batch;
			}
			if ( ! empty( $ids ) && ( ! $standard || ! empty( $batch['repaired'] ) ) ) {
				$callback_result = call_user_func( $callback, $ids, $taxonomy );
				$failed          = ( false === $callback_result ) || is_wp_error( $callback_result ) || ! empty( $bbp_db->last_error );
			} else {
				$failed = false;
			}
			if ( ! empty( $term_ids ) ) {
				clean_term_cache( $term_ids, $taxonomy->name, false );
			}
			if ( $failed ) {
				bbp_admin_repair_tool_batch_fail( $batch, 'callback' );
				return $batch;
			}
			if ( $standard && ! empty( $batch['repaired'] ) ) {
				$status = bbp_admin_repair_topic_tag_count_get_status( $taxonomy, $ids );
				if ( ! empty( $status['code'] ) || ! empty( $status['repair_count'] ) ) {
					bbp_admin_repair_tool_batch_fail( $batch, ! empty( $status['code'] ) ? 'source-read' : 'verify' );
					return $batch;
				}
			}
		}
		if ( ! empty( $rows ) ) {
			$state['cursor'] = (int) end( $rows )['term_taxonomy_id'];
		}
		++$batch['batches'];
		if ( ! bbp_admin_repair_tool_batch_save( $tool_id, $state, $batch, $complete, $dry_run, array( 'repaired', 'processed', 'initial', 'batches' ) ) ) {
			return $batch;
		}
	} while ( bbp_admin_repair_tool_batch_continue( $batch, $dry_run ) );

	return $batch;
}

/**
 * Repair one topic-tag-count batch or preview the complete operation.
 *
 * @since 2.7.0 bbPress (r8244)
 * @since 2.7.0 bbPress (r8267) Processes one resumable batch per call.
 *
 * @param bool $dry_run Whether to preview the repair without updating data.
 * @return array Tool result and completion flag.
 */
function bbp_admin_repair_topic_tag_count_process( $dry_run = false ) {
	$result   = esc_html__( 'Failed!', 'bbpress' );
	$taxonomy = get_taxonomy( bbp_get_topic_tag_tax_id() );
	if ( $dry_run ) {
		/* translators: %s: Status of the dry-run process */
		$statement = esc_html__( 'Checking the number of topics in each topic-tag&hellip; %s', 'bbpress' );
	} else {
		/* translators: %s: Status of the counting process */
		$statement = esc_html__( 'Counting the number of topics in each topic-tag&hellip; %s', 'bbpress' );
	}

	$callback = ! empty( $taxonomy->update_count_callback ) ? $taxonomy->update_count_callback : '_update_post_term_count';
	$standard = ( 'bbp_update_topic_tag_count' === $callback );
	$code     = 0;
	if ( empty( $taxonomy ) ) {
		$code = 1;
	} elseif ( ! is_callable( $callback ) ) {
		$code = 2;
	} elseif ( $standard && empty( $taxonomy->object_type ) ) {
		$code   = 2;
		$result = esc_html__( 'The topic-tag taxonomy has no object types to count.', 'bbpress' );
	} elseif ( $dry_run && ! $standard ) {
		$code   = 2;
		$result = esc_html__( 'Dry Run is unavailable for the configured topic-tag count callback.', 'bbpress' );
	}
	if ( $code ) {
		return array(
			$code,
			sprintf( $statement, $result ),
			'success'  => false,
			'complete' => false
		);
	}

	$batch     = bbp_admin_repair_topic_tag_count_batch( $taxonomy, $callback, $dry_run );
	if ( $dry_run ) {
		/* translators: %d: Number of topic-tags whose topic counts would be repaired */
		$result = sprintf( _n( 'Topic counts would be repaired in %d topic-tag.', 'Topic counts would be repaired in %d topic-tags.', $batch['repaired'], 'bbpress' ), $batch['repaired'] );
	} elseif ( $standard ) {
		/* translators: %d: Number of topic-tags whose topic counts were repaired */
		$result = sprintf( _n( 'Topic counts repaired in %d topic-tag.', 'Topic counts repaired in %d topic-tags.', $batch['repaired'], 'bbpress' ), $batch['repaired'] );
	} else {
		/* translators: %d: Number of topic-tags passed to the configured count callback */
		$result = sprintf( _n( 'Topic counts recounted in %d topic-tag.', 'Topic counts recounted in %d topic-tags.', $batch['processed'], 'bbpress' ), $batch['processed'] );
	}
	return bbp_admin_repair_tool_present_batch(
		$batch,
		$statement,
		$result,
		$dry_run,
		$standard && empty( $batch['repaired'] ) && ( $dry_run || $batch['initial'] ),
		esc_html__( '0 topic-tag counts to repair.', 'bbpress' )
	);
}

/**
 * Recount forum replies.
 *
 * @since 2.1.0 bbPress (r3884)
 * @since 2.7.0 bbPress (r8245) Added Dry Run support.
 * @since 2.7.0 bbPress (r8284) Shares resumable forum recounts.
 *
 * @return array Tool result and completion flag.
 */
function bbp_admin_repair_forum_reply_count() {
	return bbp_admin_repair_forum_reply_count_process();
}

/**
 * Preview recounting forum replies.
 *
 * @since 2.7.0 bbPress (r8245)
 * @since 2.7.0 bbPress (r8284) Shares resumable forum recounts.
 *
 * @return array Tool result and completion flag.
 */
function bbp_admin_repair_forum_reply_count_dry_run() {
	return bbp_admin_repair_forum_reply_count_process( true );
}

/**
 * Repair or preview repairing forum reply counts.
 *
 * @since 2.7.0 bbPress (r8245)
 * @since 2.7.0 bbPress (r8284) Processes one resumable batch per call.
 *
 * @param bool $dry_run Whether to preview the repair without updating data.
 * @return array Tool result and completion flag.
 */
function bbp_admin_repair_forum_reply_count_process( $dry_run = false ) {
	return bbp_admin_repair_forum_count_process( 'replies', $dry_run );
}

/**
 * Recount non-public forum replies.
 *
 * @since 2.6.0 bbPress (r6933)
 * @since 2.6.0 bbPress (r6933) Rename to match the topic reply recount function
 * @since 2.7.0 bbPress (r8246) Added Dry Run support.
 * @since 2.7.0 bbPress (r8284) Shares resumable forum recounts.
 *
 * @return array Tool result and completion flag.
 */
function bbp_admin_repair_forum_hidden_reply_count() {
	return bbp_admin_repair_forum_hidden_reply_count_process();
}

/**
 * Preview recounting non-public forum replies.
 *
 * @since 2.7.0 bbPress (r8246)
 * @since 2.7.0 bbPress (r8284) Shares resumable forum recounts.
 *
 * @return array Tool result and completion flag.
 */
function bbp_admin_repair_forum_hidden_reply_count_dry_run() {
	return bbp_admin_repair_forum_hidden_reply_count_process( true );
}

/**
 * Repair or preview recounting non-public forum replies.
 *
 * @since 2.7.0 bbPress (r8246)
 * @since 2.7.0 bbPress (r8284) Processes one resumable batch per call.
 *
 * @param bool $dry_run Whether to preview the repair without updating data.
 * @return array Tool result and completion flag.
 */
function bbp_admin_repair_forum_hidden_reply_count_process( $dry_run = false ) {
	return bbp_admin_repair_forum_count_process( 'hidden-replies', $dry_run );
}

/**
 * Recount one bounded batch of user post counts.
 *
 * Scan public post authors and stored counts together, including orphaned user
 * IDs. Each inspected user or metadata write consumes work. Large duplicate
 * sets resume at the same user, and failed batches can be safely repeated.
 *
 * @since 2.7.0 bbPress (r8265)
 *
 * @param string $tool_id   Repair tool ID.
 * @param string $post_type Post type to count.
 * @param array  $statuses  Public post statuses.
 * @param string $key       Site-specific user-meta key.
 * @param bool   $dry_run   Whether to preview without writes.
 * @return array Status code, repaired user count, and completion flag.
 */
function bbp_admin_repair_user_post_count_batch( $tool_id, $post_type, $statuses, $key, $dry_run = false ) {
	$bbp_db = bbp_db();
	$batch  = bbp_admin_repair_tool_batch_status( array( 'repaired' => 0 ) );
	$state  = false;
	if ( ! bbp_admin_repair_tool_batch_start( $tool_id, $state, $batch, 'bbp_admin_repair_tool_is_valid_checkpoint', array(), $dry_run ) ) {
		return $batch;
	}

	// An empty status list historically matches only an empty stored status
	$statuses     = ! empty( $statuses ) ? (array) $statuses : array( '' );
	$placeholders = implode( ', ', array_fill( 0, count( $statuses ), '%s' ) );
	$source       = "FROM {$bbp_db->posts} WHERE post_type = %s AND post_status IN ({$placeholders}) AND post_author > 0";
	$source_args  = array_merge( array( $post_type ), $statuses );

	if ( false === $state ) {
		$author_id = $bbp_db->get_var( $bbp_db->prepare( "SELECT post_author {$source} ORDER BY post_author DESC LIMIT 1", $source_args ) );
		if ( ! empty( $bbp_db->last_error ) ) {
			bbp_admin_repair_tool_batch_fail( $batch, 'source-read' );
			return $batch;
		}
		$stored_id = $bbp_db->get_var( $bbp_db->prepare( "SELECT user_id FROM {$bbp_db->usermeta} WHERE meta_key = %s ORDER BY user_id DESC LIMIT 1", $key ) );
		if ( ! empty( $bbp_db->last_error ) ) {
			bbp_admin_repair_tool_batch_fail( $batch, 'metadata-read' );
			return $batch;
		}
		$state = array(
			'cursor'   => 0,
			'upper_id' => max( (int) $author_id, (int) $stored_id )
		);
		if ( ! bbp_admin_repair_tool_batch_save( $tool_id, $state, $batch, false, $dry_run ) ) {
			return $batch;
		}
	}

	$size          = bbp_admin_repair_tool_batch_size( $tool_id );
	$preview_state = bbp_admin_repair_tool_restore_preview( $state, $batch, array( 'repaired', 'batches' ) );
	$metadata      = isset( $preview_state['metadata'] ) ? $preview_state['metadata'] : null;
	do {
		$user_ids = $bbp_db->get_col(
			$bbp_db->prepare(
				"(SELECT post_author AS user_id {$source} AND post_author >= %d AND post_author <= %d
				GROUP BY post_author ORDER BY post_author LIMIT %d)
				UNION
				(SELECT user_id FROM {$bbp_db->usermeta} WHERE meta_key = %s AND user_id >= %d AND user_id <= %d
				GROUP BY user_id ORDER BY user_id LIMIT %d)
				ORDER BY user_id LIMIT %d",
				array_merge( $source_args, array( $state['cursor'], $state['upper_id'], $size + 1, $key, $state['cursor'], $state['upper_id'], $size + 1, $size + 1 ) )
			)
		);
		if ( ! empty( $bbp_db->last_error ) ) {
			bbp_admin_repair_tool_batch_fail( $batch, 'metadata-read' );
			return $batch;
		}

		$work     = 0;
		$finished = 0;
		foreach ( $user_ids as $user_id ) {
			if ( $work >= $size ) {
				break;
			}
			$user_id = (int) $user_id;
			$count   = (int) $bbp_db->get_var( $bbp_db->prepare( "SELECT COUNT(*) {$source} AND post_author = %d", array_merge( $source_args, array( $user_id ) ) ) );
			if ( ! empty( $bbp_db->last_error ) ) {
				bbp_admin_repair_tool_batch_fail( $batch, 'source-read' );
				return $batch;
			}

			$repair = bbp_admin_repair_single_metadata( 'user', $user_id, $key, $count > 0 ? $count : null, $size - $work, $dry_run, $metadata );
			if ( ! empty( $repair['code'] ) ) {
				bbp_admin_repair_tool_batch_fail( $batch, ( 2 === $repair['code'] ) ? 'metadata-read' : 'metadata-write' );
				return $batch;
			}
			$work += $repair['work'];
			if ( $repair['changed'] ) {
				++$batch['repaired'];
			}
			if ( ! $repair['complete'] ) {
				$state['cursor'] = $user_id;
				break;
			}
			$state['cursor'] = $user_id + 1;
			++$finished;
		}

		$complete = count( $user_ids ) === $finished;
		$batch['batches'] += max( 1, (int) ceil( $work / $size ) );
		if ( ! bbp_admin_repair_tool_batch_save( $tool_id, $state, $batch, $complete, $dry_run, array( 'repaired', 'batches' ), array( 'metadata' => $metadata ) ) ) {
			return $batch;
		}
	} while ( bbp_admin_repair_tool_batch_continue( $batch, $dry_run ) );

	return $batch;
}

/**
 * Format a user topic or reply recount using the shared batch worker.
 *
 * @since 2.7.0 bbPress (r8265)
 *
 * @param string $type    Whether to recount 'topic' or 'reply' posts.
 * @param bool   $dry_run Whether to preview without writes.
 * @return array Tool result and completion flag.
 */
function bbp_admin_repair_user_post_count_process( $type, $dry_run = false ) {
	if ( 'topic' === $type ) {
		$tool_id   = 'bbp-user-topics';
		$post_type = bbp_get_topic_post_type();
		$statuses  = bbp_get_public_topic_statuses();
		$key       = bbp_db()->prefix . '_bbp_topic_count';
		$no_counts = esc_html__( '0 user topic counts to repair.', 'bbpress' );
		if ( $dry_run ) {
			/* translators: %s: Status of the dry-run process */
			$statement = esc_html__( 'Checking the number of topics each user has created&hellip; %s', 'bbpress' );
		} else {
			/* translators: %s: Status of the counting process */
			$statement = esc_html__( 'Counting the number of topics each user has created&hellip; %s', 'bbpress' );
		}
	} elseif ( 'reply' === $type ) {
		$tool_id   = 'bbp-user-replies';
		$post_type = bbp_get_reply_post_type();
		$statuses  = bbp_get_public_reply_statuses();
		$key       = bbp_db()->prefix . '_bbp_reply_count';
		$no_counts = esc_html__( '0 user reply counts to repair.', 'bbpress' );
		if ( $dry_run ) {
			/* translators: %s: Status of the dry-run process */
			$statement = esc_html__( 'Checking the number of replies each user has created&hellip; %s', 'bbpress' );
		} else {
			/* translators: %s: Status of the counting process */
			$statement = esc_html__( 'Counting the number of replies each user has created&hellip; %s', 'bbpress' );
		}
	} else {
		return array(
			1,
			esc_html__( 'Failed!', 'bbpress' ),
			'success'  => false,
			'complete' => false
		);
	}

	$batch     = bbp_admin_repair_user_post_count_batch( $tool_id, $post_type, $statuses, $key, $dry_run );

	if ( $dry_run ) {
		if ( 'topic' === $type ) {
			/* translators: %d: Number of users whose topic counts would be repaired */
			$result = sprintf( _n( 'Topic counts would be repaired for %d user.', 'Topic counts would be repaired for %d users.', $batch['repaired'], 'bbpress' ), $batch['repaired'] );
		} else {
			/* translators: %d: Number of users whose reply counts would be repaired */
			$result = sprintf( _n( 'Reply counts would be repaired for %d user.', 'Reply counts would be repaired for %d users.', $batch['repaired'], 'bbpress' ), $batch['repaired'] );
		}
	} elseif ( 'topic' === $type ) {
		/* translators: %d: Number of users whose topic counts were repaired */
		$result = sprintf( _n( 'Topic counts repaired for %d user.', 'Topic counts repaired for %d users.', $batch['repaired'], 'bbpress' ), $batch['repaired'] );
	} else {
		/* translators: %d: Number of users whose reply counts were repaired */
		$result = sprintf( _n( 'Reply counts repaired for %d user.', 'Reply counts repaired for %d users.', $batch['repaired'], 'bbpress' ), $batch['repaired'] );
	}
	return bbp_admin_repair_tool_present_batch( $batch, $statement, $result, $dry_run, $dry_run && empty( $batch['repaired'] ), $no_counts );
}

/**
 * Recount topics by the users.
 *
 * @since 2.1.0 bbPress (r3890)
 * @since 2.7.0 bbPress (r8224) Added Dry Run support.
 * @since 2.7.0 bbPress (r8265) Process one resumable batch per call.
 *
 * @return array An array of the status code and the message.
 */
function bbp_admin_repair_user_topic_count() {
	return bbp_admin_repair_user_topic_count_process();
}

/**
 * Preview recounting topics by the users.
 *
 * @since 2.7.0 bbPress (r8224)
 *
 * @return array An array of the status code and the message.
 */
function bbp_admin_repair_user_topic_count_dry_run() {
	return bbp_admin_repair_user_topic_count_process( true );
}

/**
 * Recount or preview topics by the users.
 *
 * @since 2.7.0 bbPress (r8224)
 *
 * @param bool $dry_run Whether to preview changes without updating data.
 * @return array An array of the status code and the message.
 */
function bbp_admin_repair_user_topic_count_process( $dry_run = false ) {
	return bbp_admin_repair_user_post_count_process( 'topic', $dry_run );
}

/**
 * Recount replies by the users.
 *
 * @since 2.1.0 bbPress (r3884)
 * @since 2.7.0 bbPress (r8225) Added Dry Run support.
 * @since 2.7.0 bbPress (r8265) Process one resumable batch per call.
 *
 * @return array An array of the status code and the message.
 */
function bbp_admin_repair_user_reply_count() {
	return bbp_admin_repair_user_reply_count_process();
}

/**
 * Preview recounting replies by the users.
 *
 * @since 2.7.0 bbPress (r8225)
 *
 * @return array An array of the status code and the message.
 */
function bbp_admin_repair_user_reply_count_dry_run() {
	return bbp_admin_repair_user_reply_count_process( true );
}

/**
 * Recount or preview replies by the users.
 *
 * @since 2.7.0 bbPress (r8225)
 *
 * @param bool $dry_run Whether to preview changes without updating data.
 * @return array An array of the status code and the message.
 */
function bbp_admin_repair_user_reply_count_process( $dry_run = false ) {
	return bbp_admin_repair_user_post_count_process( 'reply', $dry_run );
}

/**
 * Check the saved position and context of a legacy user-list repair.
 *
 * @since 2.7.0 bbPress (r8286)
 * @since 2.7.0 bbPress (r8347) Validate shared source and preview state.
 *
 * @param mixed $state   Saved progress.
 * @param array $context Current key, post type, and public statuses.
 * @return bool Whether the saved state can be resumed.
 */
function bbp_admin_repair_user_meta_is_valid_checkpoint( $state, $context ) {
	if ( ! bbp_admin_repair_tool_is_valid_user_meta_checkpoint( $state )
		|| ! bbp_admin_repair_tool_is_valid_preview_state( $state, array( 'repaired', 'batches' ), array( 'initial' ) ) ) {
		return false;
	}
	if ( ! isset( $state['phase'], $state['context'], $state['row_hash'], $state['kept'], $state['ready'] )
		|| ! in_array( $state['phase'], array( 'probe', 'repair' ), true )
		|| ( $context !== $state['context'] ) || ! is_string( $state['row_hash'] )
		|| ! is_string( $state['kept'] ) || ! is_bool( $state['ready'] ) ) {
		return false;
	}
	if ( '' === $state['row_hash'] ) {
		return ( 0 === $state['offset'] ) && ( '' === $state['kept'] ) && ! $state['ready'];
	}
	if ( ! $state['cursor'] || ( 1 !== preg_match( '/^[a-f0-9]{32}$/D', $state['row_hash'] ) ) ) {
		return false;
	}
	$kept = bbp_admin_repair_user_meta_ids( $state['kept'] );
	return ( implode( ',', $kept ) === $state['kept'] ) && ( count( $kept ) <= $state['offset'] )
		&& ( 'repair' === $state['phase'] || ( '' === $state['kept'] && ! $state['ready'] ) );
}

/**
 * Repair one bounded batch of legacy favorites or subscription lists.
 *
 * Probe for a surviving relationship before changing any rows, preserving the
 * historical all-empty no-op. Scan referenced IDs within the shared budget and
 * update each completed row in place. One legacy CSV cell must still be read
 * and parsed as a unit; saved kept IDs never exceed that single source list.
 *
 * @since 2.7.0 bbPress (r8286)
 * @since 2.7.0 bbPress (r8347) Share source and worker lifecycle helpers.
 *
 * @param string $type    Favorites, topic-subscriptions, or forum-subscriptions.
 * @param bool   $dry_run Whether to preview without writes.
 * @return array Status code, repaired stored lists, and progress flags.
 */
function bbp_admin_repair_user_meta_batch( $type = 'favorites', $dry_run = false ) {
	$db     = bbp_db();
	$config = bbp_admin_get_user_relationship_config( $type );
	$batch  = bbp_admin_repair_tool_batch_status(
		array(
			'repaired' => 0,
			'initial'  => false,
			'empty'    => false,
		)
	);
	if ( false === $config ) {
		bbp_admin_repair_tool_batch_fail( $batch, 'callback' );
		return $batch;
	}
	$tool_id = $config['repair_tool_id'];
	$forum   = 'forum' === $config['object_type'];
	$context = array(
		'key'       => $config['source_key'],
		'post_type' => $forum ? bbp_get_forum_post_type() : bbp_get_topic_post_type(),
		'statuses'  => array_values( array_unique( $forum ? bbp_get_public_forum_statuses() : bbp_get_public_topic_statuses() ) )
	);
	$key      = $context['key'];
	$statuses = $context['statuses'] ? $context['statuses'] : array( '' );
	$slots    = implode( ', ', array_fill( 0, count( $statuses ), '%s' ) );
	$args     = array_merge( array( $context['post_type'] ), $statuses );
	$source   = "SELECT ID FROM {$db->posts} WHERE post_type = %s AND post_status IN ({$slots})";
	$fields   = array( 'repaired', 'initial', 'batches' );
	$state    = false;
	if ( ! bbp_admin_repair_tool_batch_start( $tool_id, $state, $batch, 'bbp_admin_repair_user_meta_is_valid_checkpoint', $context, $dry_run ) ) {
		return $batch;
	}
	$batch['initial'] = false === $state;
	if ( $batch['initial'] ) {
		$upper = bbp_admin_repair_user_meta_upper_id( $key, $batch );
		if ( false === $upper ) {
			return $batch;
		}
		$live = $db->get_var( $db->prepare( $source . ' LIMIT 1', $args ) );
		if ( ! empty( $db->last_error ) ) {
			bbp_admin_repair_tool_batch_fail( $batch, 'source-read' );
			return $batch;
		}
		$state = array(
			'cursor'   => 0,
			'upper_id' => $upper,
			'offset'   => 0,
			'row_hash' => '',
			'kept'     => '',
			'ready'    => false,
			'phase'    => 'probe',
			'context'  => $context
		);
		if ( empty( $live ) ) {
			$batch['empty'] = true;
			bbp_admin_repair_tool_batch_save( $tool_id, $state, $batch, true, $dry_run, $fields );
			return $batch;
		}
		if ( ! bbp_admin_repair_tool_batch_save( $tool_id, $state, $batch, false, $dry_run, $fields ) ) {
			return $batch;
		}
	}
	$size = bbp_admin_repair_tool_batch_size( $tool_id );
	bbp_admin_repair_tool_restore_preview( $state, $batch, $fields );
	do {
		$work = 0;
		while ( $work < $size && $state['cursor'] <= $state['upper_id'] ) {
			$row = bbp_admin_repair_user_meta_row( $key, $state['cursor'], $state['upper_id'], $batch );
			if ( false === $row ) {
				return $batch;
			}
			if ( empty( $row ) ) {
				$batch['complete'] = true;
				break;
			}
			$id   = (int) $row->umeta_id;
			$hash = bbp_admin_repair_user_meta_row_hash( $row, true );
			$ids  = bbp_admin_repair_user_meta_ids( $row->meta_value );
			$same = $id === $state['cursor'] && $hash === $state['row_hash'];
			$bad  = $same && ( $state['offset'] > count( $ids ) || ( $state['ready'] && count( $ids ) !== $state['offset'] )
				|| array_diff( bbp_admin_repair_user_meta_ids( $state['kept'] ), array_slice( $ids, 0, $state['offset'] ) ) );
			if ( ! $same || $bad ) {
				$state['cursor']    = $id;
				$state['offset']    = 0;
				$state['row_hash']  = $hash;
				$state['kept']      = '';
				$state['ready']     = false;
				$batch['restarted'] = $batch['restarted'] || (bool) $bad;
			}
			if ( ! $state['ready'] ) {
				$next  = array_slice( $ids, $state['offset'], $size - $work );
				$valid = array();
				if ( ! empty( $next ) ) {
					$id_slots = implode( ', ', array_fill( 0, count( $next ), '%d' ) );
					$valid    = $db->get_col( $db->prepare( $source . " AND ID IN ({$id_slots}) ORDER BY ID", array_merge( $args, $next ) ) );
					if ( ! empty( $db->last_error ) ) {
						bbp_admin_repair_tool_batch_fail( $batch, 'source-read' );
						return $batch;
					}
				}
				$work += count( $next );
				$state['offset'] += count( $next );
				if ( 'probe' === $state['phase'] ) {
					if ( ! empty( $valid ) ) {
						$state['phase']    = 'repair';
						$state['cursor']   = $state['offset'] = 0;
						$state['row_hash'] = '';
						if ( ! bbp_admin_repair_tool_batch_save( $tool_id, $state, $batch, false, $dry_run, $fields ) ) {
							return $batch;
						}
					} elseif ( $state['offset'] >= count( $ids ) ) {
						$work += empty( $next ) ? 1 : 0;
						$state['cursor']   = $id + 1;
						$state['offset']   = 0;
						$state['row_hash'] = '';
					}
					continue;
				}
				if ( ! empty( $valid ) ) {
					$state['kept'] .= ( '' === $state['kept'] ? '' : ',' ) . implode( ',', $valid );
				}
				$state['ready'] = count( $ids ) === $state['offset'];
				if ( ! $state['ready'] || $work >= $size ) {
					continue;
				}
			}
			++$work;
			$value = $state['kept'];
			if ( ! $dry_run ) {
				$code = bbp_admin_repair_metadata_row( 'user', $row, $key, '' === $value ? null : $value );
				if ( $code ) {
					bbp_admin_repair_tool_batch_fail( $batch, 1 === $code ? 'metadata-read' : 'metadata-write' );
					return $batch;
				}
			}
			if ( '' === $value || $row->meta_value !== $value || $row->meta_key !== $key ) {
				++$batch['repaired'];
			}
			$state['cursor']   = $id + 1;
			$state['offset']   = 0;
			$state['row_hash'] = $state['kept'] = '';
			$state['ready']    = false;
		}
		if ( ! $batch['complete'] ) {
			$remaining = bbp_admin_repair_user_meta_row( $key, $state['cursor'], $state['upper_id'], $batch );
			if ( false === $remaining ) {
				return $batch;
			}
			$batch['complete'] = empty( $remaining );
		}
		$batch['empty']    = $batch['complete'] && 'probe' === $state['phase'];
		$batch['batches'] += max( 1, (int) ceil( $work / $size ) );
		if ( ! bbp_admin_repair_tool_batch_save( $tool_id, $state, $batch, $batch['complete'], $dry_run, $fields ) ) {
			return $batch;
		}
	} while ( bbp_admin_repair_tool_batch_continue( $batch, $dry_run ) );

	return $batch;
}

/**
 * Format one shared legacy user-list repair batch.
 *
 * @since 2.7.0 bbPress (r8286)
 *
 * @param string $type    Favorites, topic-subscriptions, or forum-subscriptions.
 * @param bool   $dry_run Whether to preview without writes.
 * @return array Tool result and completion flag.
 */
function bbp_admin_repair_user_meta_process( $type = 'favorites', $dry_run = false ) {
	if ( 'favorites' === $type ) {
		$none = esc_html__( '0 stored favorite lists to repair.', 'bbpress' );
		if ( $dry_run ) {
			/* translators: %s: Status of the dry-run process */
			$statement = esc_html__( 'Checking unpublished topics in user favorites&hellip; %s', 'bbpress' );
		} else {
			/* translators: %s: Status of the repair process */
			$statement = esc_html__( 'Removing unpublished topics from user favorites&hellip; %s', 'bbpress' );
		}
	} elseif ( 'forum-subscriptions' === $type ) {
		$none = esc_html__( '0 stored forum subscription lists to repair.', 'bbpress' );
		if ( $dry_run ) {
			/* translators: %s: Status of the dry-run process */
			$statement = esc_html__( 'Checking trashed forums in user subscriptions&hellip; %s', 'bbpress' );
		} else {
			/* translators: %s: Status of the repair process */
			$statement = esc_html__( 'Removing trashed forums from user subscriptions&hellip; %s', 'bbpress' );
		}
	} else {
		$none = esc_html__( '0 stored topic subscription lists to repair.', 'bbpress' );
		if ( $dry_run ) {
			/* translators: %s: Status of the dry-run process */
			$statement = esc_html__( 'Checking trashed topics in user subscriptions&hellip; %s', 'bbpress' );
		} else {
			/* translators: %s: Status of the repair process */
			$statement = esc_html__( 'Removing trashed topics from user subscriptions&hellip; %s', 'bbpress' );
		}
	}
	$batch     = bbp_admin_repair_user_meta_batch( $type, $dry_run );
	$statement = bbp_admin_repair_tool_restart_statement( $statement, $batch['restarted'], $dry_run );
	if ( $batch['code'] ) {
		return bbp_admin_repair_tool_batch_result( $batch, $statement );
	}
	if ( $batch['empty'] || ( $batch['complete'] && ! $batch['repaired'] && ( $dry_run || $batch['initial'] ) ) ) {
		return array(
			$dry_run ? 1 : 0,
			sprintf( $statement, $dry_run ? $none : esc_html__( 'Nothing to remove!', 'bbpress' ) ),
			'complete' => true,
			'batches'  => $batch['batches'],
		);
	}
	if ( 'favorites' === $type ) {
		$result = $dry_run
			/* translators: %d: Number of stored favorite lists that would be repaired */
			? sprintf( _n( '%d stored favorite list would be repaired.', '%d stored favorite lists would be repaired.', $batch['repaired'], 'bbpress' ), $batch['repaired'] )
			/* translators: %d: Number of stored favorite lists repaired */
			: sprintf( _n( '%d stored favorite list repaired.', '%d stored favorite lists repaired.', $batch['repaired'], 'bbpress' ), $batch['repaired'] );
	} elseif ( 'forum-subscriptions' === $type ) {
		$result = $dry_run
			/* translators: %d: Number of stored forum subscription lists that would be repaired */
			? sprintf( _n( '%d stored forum subscription list would be repaired.', '%d stored forum subscription lists would be repaired.', $batch['repaired'], 'bbpress' ), $batch['repaired'] )
			/* translators: %d: Number of stored forum subscription lists repaired */
			: sprintf( _n( '%d stored forum subscription list repaired.', '%d stored forum subscription lists repaired.', $batch['repaired'], 'bbpress' ), $batch['repaired'] );
	} else {
		$result = $dry_run
			/* translators: %d: Number of stored topic subscription lists that would be repaired */
			? sprintf( _n( '%d stored topic subscription list would be repaired.', '%d stored topic subscription lists would be repaired.', $batch['repaired'], 'bbpress' ), $batch['repaired'] )
			/* translators: %d: Number of stored topic subscription lists repaired */
			: sprintf( _n( '%d stored topic subscription list repaired.', '%d stored topic subscription lists repaired.', $batch['repaired'], 'bbpress' ), $batch['repaired'] );
	}
	return bbp_admin_repair_tool_batch_result( $batch, $statement, $result, $dry_run );
}

/**
 * Repair user favorites.
 *
 * @since 2.1.0 bbPress (r3884)
 * @since 2.7.0 bbPress (r8226) Added Dry Run support.
 * @since 2.7.0 bbPress (r8286) Processes one resumable batch per call.
 *
 * @return array Tool result and completion flag.
 */
function bbp_admin_repair_user_favorites() {
	return bbp_admin_repair_user_favorites_process();
}

/**
 * Preview repairing user favorites.
 *
 * @since 2.7.0 bbPress (r8226)
 * @since 2.7.0 bbPress (r8286) Processes one resumable batch per call.
 *
 * @return array Tool result and completion flag.
 */
function bbp_admin_repair_user_favorites_dry_run() {
	return bbp_admin_repair_user_favorites_process( true );
}

/**
 * Repair or preview user favorites.
 *
 * @since 2.7.0 bbPress (r8226)
 * @since 2.7.0 bbPress (r8286) Shares legacy user-list repair batching.
 *
 * @param bool $dry_run Whether to preview changes without updating data.
 * @return array Tool result and completion flag.
 */
function bbp_admin_repair_user_favorites_process( $dry_run = false ) {
	return bbp_admin_repair_user_meta_process( 'favorites', $dry_run );
}

/**
 * Clean the user topic subscriptions.
 *
 * @since 2.5.0 bbPress (r5157)
 * @since 2.7.0 bbPress (r8227) Added Dry Run support.
 * @since 2.7.0 bbPress (r8286) Processes one resumable batch per call.
 *
 * @return array Tool result and completion flag.
 */
function bbp_admin_repair_user_topic_subscriptions() {
	return bbp_admin_repair_user_topic_subscriptions_process();
}

/**
 * Preview cleaning the user topic subscriptions.
 *
 * @since 2.7.0 bbPress (r8227)
 * @since 2.7.0 bbPress (r8286) Processes one resumable batch per call.
 *
 * @return array Tool result and completion flag.
 */
function bbp_admin_repair_user_topic_subscriptions_dry_run() {
	return bbp_admin_repair_user_topic_subscriptions_process( true );
}

/**
 * Clean or preview the user topic subscriptions.
 *
 * @since 2.7.0 bbPress (r8227)
 * @since 2.7.0 bbPress (r8286) Shares legacy user-list repair batching.
 *
 * @param bool $dry_run Whether to preview changes without updating data.
 * @return array Tool result and completion flag.
 */
function bbp_admin_repair_user_topic_subscriptions_process( $dry_run = false ) {
	return bbp_admin_repair_user_meta_process( 'topic-subscriptions', $dry_run );
}

/**
 * Clean the user forum subscriptions.
 *
 * @since 2.5.0 bbPress (r5157)
 * @since 2.7.0 bbPress (r8228) Added Dry Run support.
 * @since 2.7.0 bbPress (r8286) Processes one resumable batch per call.
 *
 * @return array Tool result and completion flag.
 */
function bbp_admin_repair_user_forum_subscriptions() {
	return bbp_admin_repair_user_forum_subscriptions_process();
}

/**
 * Preview cleaning the user forum subscriptions.
 *
 * @since 2.7.0 bbPress (r8228)
 * @since 2.7.0 bbPress (r8286) Processes one resumable batch per call.
 *
 * @return array Tool result and completion flag.
 */
function bbp_admin_repair_user_forum_subscriptions_dry_run() {
	return bbp_admin_repair_user_forum_subscriptions_process( true );
}

/**
 * Clean or preview the user forum subscriptions.
 *
 * @since 2.7.0 bbPress (r8228)
 * @since 2.7.0 bbPress (r8286) Shares legacy user-list repair batching.
 *
 * @param bool $dry_run Whether to preview changes without updating data.
 * @return array Tool result and completion flag.
 */
function bbp_admin_repair_user_forum_subscriptions_process( $dry_run = false ) {
	return bbp_admin_repair_user_meta_process( 'forum-subscriptions', $dry_run );
}

/**
 * This repair tool will map each user of the current site to their respective
 * forums role. By default, Admins will be Keymasters, and every other role
 * will be the default role defined in Settings > Forums (Participant).
 *
 * @since 2.2.0 bbPress (r4341)
 * @since 2.7.0 bbPress (r8229) Added Dry Run support.
 * @since 2.7.0 bbPress (r8288) Process one resumable batch per call.
 *
 * @return array Status code, feedback, and completion flag.
 */
function bbp_admin_repair_user_roles() {
	return bbp_admin_repair_user_roles_process();
}

/**
 * Preview all forum role mappings on the current site.
 *
 * @since 2.7.0 bbPress (r8229)
 *
 * @return array Status code, feedback, and completion flag.
 */
function bbp_admin_repair_user_roles_dry_run() {
	return bbp_admin_repair_user_roles_process( true );
}

/**
 * Plan and verify one forum role assignment through the existing role API.
 *
 * Keep multiple-role ordering and Blocked exclusivity consistent with Run.
 * Dry Run carries a local plan when the same user appears in another role pass.
 * One assignment is a work item; WordPress owns the capability writes it needs.
 *
 * @since 2.7.0 bbPress (r8288)
 *
 * @param int        $user_id User ID.
 * @param string     $role    Target forum role.
 * @param bool       $dry_run Whether to preview without writes.
 * @param array|null $planned Earlier preview of this user's forum roles.
 * @return array Status, changed assignments, and planned forum roles.
 */
function bbp_admin_repair_user_role( $user_id, $role, $dry_run = false, $planned = null ) {
	$db     = bbp_db();
	$result = array(
		'code'    => 0,
		'changed' => 0,
		'roles'   => array()
	);
	if ( ! $dry_run ) {
		wp_cache_delete( $user_id, 'user_meta' );
	}
	$user = get_userdata( $user_id );
	if ( ! empty( $db->last_error ) ) {
		wp_cache_delete( $user_id, 'user_meta' );
		$result['code'] = 2;
		return $result;
	}
	if ( empty( $user ) || ! bbp_is_valid_role( $role ) ) {
		return $result;
	}
	$dynamic = array_keys( bbp_get_dynamic_roles() );
	$roles   = is_array( $planned ) ? $planned : array_values( array_intersect( $user->roles, $dynamic ) );
	$current = is_array( $planned ) ? ( empty( $roles ) ? false : reset( $roles ) ) : bbp_get_user_role( $user_id );
	$blocked = bbp_get_blocked_role();
	$changed = $current !== $role || ( $blocked === $role && array( $blocked ) !== $roles );
	if ( $blocked === $role ) {
		$roles = array( $blocked );
	} elseif ( $current !== $role ) {
		$index = array_search( $current, $roles, true );
		if ( false !== $index ) {
			unset( $roles[ $index ] );
		}
		if ( ! in_array( $role, $roles, true ) ) {
			$roles[] = $role;
		}
	}
	$result['roles'] = array_values( $roles );
	if ( ! $dry_run ) {
		// Preserve the public setter and its filter, including unchanged assignments
		bbp_set_user_role( $user_id, $role );
		wp_cache_delete( $user_id, 'user_meta' );
		if ( $changed ) {
			$key  = $db->get_blog_prefix() . 'capabilities';
			$caps = $db->get_var( $db->prepare( "SELECT meta_value FROM {$db->usermeta} WHERE user_id = %d AND meta_key = %s ORDER BY umeta_id LIMIT 1", $user_id, $key ) );
			if ( ! empty( $db->last_error ) ) {
				$result['code'] = 2;
				return $result;
			}
			$caps = maybe_unserialize( $caps );
			if ( ! is_array( $caps ) || array_values( array_intersect( array_keys( $caps ), $dynamic ) ) !== $result['roles'] ) {
				$result['code'] = 3;
				return $result;
			}
		}
	}
	$result['changed'] = (int) $changed;
	return $result;
}

/**
 * Validate a forum-role mapping checkpoint.
 *
 * @since 2.7.0 bbPress (r8348)
 * @access private
 *
 * @param mixed $state   Saved checkpoint.
 * @param array $context Current role map and site context.
 * @return bool Whether the mapping checkpoint can be resumed.
 */
function bbp_admin_repair_user_roles_is_valid_checkpoint( $state, $context ) {
	$valid = bbp_admin_repair_tool_is_valid_checkpoint( $state, array( 'phase' ) )
		&& isset( $state['context'] ) && $context === $state['context']
		&& $state['phase'] <= count( $context['roles'] )
		&& bbp_admin_repair_tool_is_valid_preview_state( $state, array( 'changed', 'batches' ), array(), array( 'planned' ) );
	if ( ! $valid || ! isset( $state['_preview_state'] ) ) {
		return $valid;
	}

	foreach ( $state['_preview_state']['data']['planned'] as $user_id => $roles ) {
		if ( ! is_int( $user_id ) || $user_id < 1 || ! is_array( $roles ) || array_values( $roles ) !== $roles || count( $roles ) !== count( array_unique( $roles ) ) ) {
			return false;
		}
		foreach ( $roles as $role ) {
			if ( ! is_string( $role ) || ! bbp_is_valid_role( $role ) ) {
				return false;
			}
		}
	}

	return true;
}

/**
 * Map forum roles in bounded, resumable WordPress-role passes.
 *
 * Preserve WordPress-role order and roleless-user rules. Save a fixed upper user ID
 * and a cursor for each pass, so shrinking selections and retries cannot skip
 * later users. Empty passes also consume work to keep selection queries bounded.
 *
 * @since 2.7.0 bbPress (r8288)
 * @since 2.7.0 bbPress (r8348) Share the procedural worker lifecycle.
 *
 * @param bool $dry_run Whether to preview without writes.
 * @return array Status, changed assignments, and completion flags.
 */
function bbp_admin_repair_user_roles_batch( $dry_run = false ) {
	$db      = bbp_db();
	$tool_id = 'bbp-user-role-map';
	$batch   = bbp_admin_repair_tool_batch_status( array( 'changed' => 0 ) );
	$map     = bbp_get_user_role_map();
	if ( empty( $map ) ) {
		bbp_admin_repair_tool_batch_fail( $batch, 'source-invalid' );
		return $batch;
	}
	$default = bbp_get_default_role();
	$roles   = array();
	foreach ( array_keys( bbp_get_blog_roles() ) as $role ) {
		$target = isset( $map[ $role ] ) ? $map[ $role ] : $default;
		if ( bbp_is_valid_role( $target ) ) {
			$roles[ $role ] = $target;
		}
	}
	$phase_count = count( $roles );
	$context     = array(
		'roles'     => $roles,
		'default'   => bbp_is_valid_role( $default ) ? $default : false,
		'blocked'   => bbp_get_blocked_role(),
		'cap_key'   => $db->get_blog_prefix() . 'capabilities',
		'blog_id'   => get_current_blog_id(),
		'multisite' => is_multisite()
	);
	$state       = false;
	$fields      = array( 'changed', 'batches' );
	if ( ! bbp_admin_repair_tool_batch_start( $tool_id, $state, $batch, 'bbp_admin_repair_user_roles_is_valid_checkpoint', $context, $dry_run ) ) {
		return $batch;
	}
	if ( false === $state ) {
		$upper = (int) $db->get_var( "SELECT MAX(ID) FROM {$db->users}" );
		if ( ! empty( $db->last_error ) ) {
			bbp_admin_repair_tool_batch_fail( $batch, 'source-read' );
			return $batch;
		}
		$state = array(
			'cursor'   => 0,
			'upper_id' => $upper,
			'phase'    => 0,
			'context'  => $context
		);
		if ( ! bbp_admin_repair_tool_batch_save( $tool_id, $state, $batch, false, $dry_run, $fields, array( 'planned' => array() ) ) ) {
			return $batch;
		}
	}
	$size          = bbp_admin_repair_tool_batch_size( $tool_id );
	$preview_state = bbp_admin_repair_tool_restore_preview( $state, $batch, $fields );
	$planned       = isset( $preview_state['planned'] ) ? $preview_state['planned'] : array();
	$keys          = array_keys( $roles );
	do {
		$work    = 0;
		while ( $work < $size && $state['phase'] <= $phase_count ) {
			if ( isset( $keys[ $state['phase'] ] ) ) {
				$role   = $keys[ $state['phase'] ];
				$target = $roles[ $role ];
				$args   = array( 'role' => $role );
			} else {
				if ( ! $context['default'] ) {
					++$state['phase'];
					break;
				}
				$target = $context['default'];
				$args   = array( 'meta_key' => $context['cap_key'] );
				if ( $context['multisite'] ) {
					$args['meta_value'] = 'a:0:{}';
				} else {
					$args['meta_compare'] = 'NOT EXISTS';
				}
			}
			$remaining = $size - $work;
			$matched   = 0;
			$ids       = bbp_admin_repair_get_users_batch( $args, $state['cursor'], $state['upper_id'], $remaining, $matched );
			if ( false === $ids ) {
				bbp_admin_repair_tool_batch_fail( $batch, 'source-read' );
				return $batch;
			}
			$more = $matched > $remaining;
			$ids  = array_slice( $ids, 0, $remaining );
			if ( empty( $ids ) ) {
				++$work;
			}
			foreach ( $ids as $user_id ) {
				$previous = isset( $planned[ $user_id ] ) ? $planned[ $user_id ] : null;
				$result   = bbp_admin_repair_user_role( $user_id, $target, $dry_run, $previous );
				if ( $result['code'] ) {
					bbp_admin_repair_tool_batch_fail( $batch, 2 === $result['code'] ? 'metadata-read' : 'verify' );
					return $batch;
				}
				if ( $dry_run ) {
					$planned[ $user_id ] = $result['roles'];
				}
				$batch['changed'] += $result['changed'];
				$state['cursor']   = $user_id;
				++$work;
			}
			// Repeated metadata matches consume work without hiding the lookahead
			$work += max( 0, min( $matched, $remaining ) - count( $ids ) );
			if ( ! $more ) {
				++$state['phase'];
				$state['cursor'] = 0;
			}
		}
		$complete = $state['phase'] > $phase_count;
		$batch['batches'] += max( 1, (int) ceil( $work / $size ) );
		if ( ! bbp_admin_repair_tool_batch_save( $tool_id, $state, $batch, $complete, $dry_run, $fields, array( 'planned' => $planned ) ) ) {
			return $batch;
		}
	} while ( bbp_admin_repair_tool_batch_continue( $batch, $dry_run ) );

	return $batch;
}

/**
 * Map or preview mapping each user to their respective forum role.
 *
 * @since 2.7.0 bbPress (r8229)
 *
 * @param bool $dry_run Whether to preview changes without updating data.
 * @return array Status code, feedback, and completion flag.
 */
function bbp_admin_repair_user_roles_process( $dry_run = false ) {
	if ( $dry_run ) {
		/* translators: %s: Status of the dry-run process */
		$statement = esc_html__( 'Checking forum role mappings for each user on this site&hellip; %s', 'bbpress' );
	} else {
		/* translators: %s: Status of the repair process */
		$statement = esc_html__( 'Remapping forum role for each user on this site&hellip; %s', 'bbpress' );
	}
	$batch     = bbp_admin_repair_user_roles_batch( $dry_run );
	$total = $batch['changed'];
	if ( $dry_run ) {
		/* translators: %s: Number of forum role assignments that would change */
		$result = sprintf( _n( '%s forum role assignment would be repaired.', '%s forum role assignments would be repaired.', $total, 'bbpress' ), bbp_number_format( $total ) );
	} else {
		/* translators: %s: Number of repaired forum role assignments */
		$result = sprintf( _n( '%s forum role assignment repaired.', '%s forum role assignments repaired.', $total, 'bbpress' ), bbp_number_format( $total ) );
	}
	return bbp_admin_repair_tool_present_batch( $batch, $statement, $result, $dry_run, $dry_run && ! $total, $result );
}

/**
 * Repair the last post in every topic and forum.
 *
 * @since 2.1.0 bbPress (r3884)
 * @since 2.7.0 bbPress (r8241) Added Dry Run support.
 * @since 2.7.0 bbPress (r8299) Processes one resumable batch per call.
 *
 * @return array An array of the status code and the message.
 */
function bbp_admin_repair_freshness() {
	return bbp_admin_repair_freshness_process();
}

/**
 * Preview repairing the last post in every topic and forum.
 *
 * @since 2.7.0 bbPress (r8241)
 * @since 2.7.0 bbPress (r8299) Processes one resumable batch per call.
 *
 * @return array An array of the status code and the message.
 */
function bbp_admin_repair_freshness_dry_run() {
	return bbp_admin_repair_freshness_process( true );
}

/**
 * Repair one activity batch or preview the complete operation.
 *
 * @since 2.7.0 bbPress (r8299)
 *
 * @param bool $dry_run Whether to preview without updating metadata or progress.
 * @return array Status, totals, and progress flags.
 */
function bbp_admin_repair_freshness_batch( $dry_run = false ) {
	$repair = new BBP_Admin_Forum_Freshness( $dry_run );
	return $repair->run();
}

/**
 * Repair or preview repairing the last post in every topic and forum.
 *
 * @since 2.7.0 bbPress (r8241)
 *
 * @param bool $dry_run Whether to preview the rebuild without updating data.
 * @return array An array of the status code and the message.
 */
function bbp_admin_repair_freshness_process( $dry_run = false ) {
	$statement = $dry_run
		/* translators: %s: Repair result */
		? esc_html__( 'Checking latest post in every topic and forum&hellip; %s', 'bbpress' )
		/* translators: %s: Repair result */
		: esc_html__( 'Recomputing latest post in every topic and forum&hellip; %s', 'bbpress' );
	$batch     = bbp_admin_repair_freshness_batch( $dry_run );
	if ( $dry_run ) {
		/* translators: %s: Number of topics */
		$topics = sprintf( esc_html( _n( '%s topic would be recalculated.', '%s topics would be recalculated.', $batch['topics'], 'bbpress' ) ), bbp_number_format( $batch['topics'] ) );
		/* translators: %s: Number of forums */
		$forums = sprintf( esc_html( _n( '%s forum would be recalculated.', '%s forums would be recalculated.', $batch['forums'], 'bbpress' ) ), bbp_number_format( $batch['forums'] ) );
		/* translators: %s: Number of orphaned freshness rows */
		$stale = sprintf( esc_html( _n( '%s orphaned freshness row would be removed.', '%s orphaned freshness rows would be removed.', $batch['stale'], 'bbpress' ) ), bbp_number_format( $batch['stale'] ) );
	} else {
		/* translators: %s: Number of topics */
		$topics = sprintf( esc_html( _n( '%s topic recalculated.', '%s topics recalculated.', $batch['topics'], 'bbpress' ) ), bbp_number_format( $batch['topics'] ) );
		/* translators: %s: Number of forums */
		$forums = sprintf( esc_html( _n( '%s forum recalculated.', '%s forums recalculated.', $batch['forums'], 'bbpress' ) ), bbp_number_format( $batch['forums'] ) );
		/* translators: %s: Number of orphaned freshness rows */
		$stale = sprintf( esc_html( _n( '%s orphaned freshness row removed.', '%s orphaned freshness rows removed.', $batch['stale'], 'bbpress' ) ), bbp_number_format( $batch['stale'] ) );
	}
	return bbp_admin_repair_tool_present_batch(
		$batch,
		$statement,
		$topics . "\n" . $forums . "\n" . $stale,
		$dry_run,
		$batch['initial'] && ! $batch['topics'] && ! $batch['forums'] && ! $batch['stale'],
		esc_html__( '0 topic or forum freshness records to recalculate.', 'bbpress' )
	);
}

/**
 * Repair sticky topic assignments to their actual parent forums.
 *
 * @since 2.3.0 bbPress (r4695)
 * @since 2.7.0 bbPress (r8219) Added Dry Run support.
 *
 * @return array An array of the status code and the message.
 */
function bbp_admin_repair_sticky() {
	return bbp_admin_repair_sticky_process();
}

/**
 * Preview the repair of sticky topic assignments.
 *
 * @since 2.7.0 bbPress (r8219)
 *
 * @return array An array of the status code and the message.
 */
function bbp_admin_repair_sticky_dry_run() {
	return bbp_admin_repair_sticky_process( true );
}

/**
 * Validate a sticky-list checkpoint before resuming it.
 *
 * @since 2.7.0 bbPress (r8303)
 * @since 2.7.0 bbPress (r8348) Validate aggregate preview state.
 *
 * @param mixed $state   Saved checkpoint.
 * @param array $context Current post type and super-sticky configuration.
 * @return bool
 */
function bbp_admin_repair_sticky_is_valid_checkpoint( $state, $context ) {
	if ( ! bbp_admin_repair_tool_is_valid_checkpoint( $state, array( 'current', 'offset', 'length', 'changed' ) )
		|| ! isset( $state['context'], $state['source_hash'], $state['kept'], $state['ready'] )
		|| $state['context'] !== $context || ! is_string( $state['source_hash'] )
		|| ! is_array( $state['kept'] ) || array_values( $state['kept'] ) !== $state['kept'] || ! is_bool( $state['ready'] )
		|| ! bbp_admin_repair_tool_is_valid_preview_state( $state, array( 'repaired', 'batches' ), array( 'initial' ), array( 'metadata' ) )
		|| count( $state['kept'] ) + $state['changed'] !== $state['offset']
		|| $state['offset'] > $state['length'] || ( $state['ready'] && $state['offset'] !== $state['length'] )
		|| $state['current'] > $state['upper_id'] ) {
		return false;
	}
	return $state['current'] ? $state['current'] > $state['cursor'] && 32 === strlen( $state['source_hash'] )
		: 0 === $state['length'] && 0 === $state['offset'] && 0 === $state['changed'] && '' === $state['source_hash'] && empty( $state['kept'] ) && ! $state['ready'];
}

/**
 * Repair a bounded selection of sticky assignments and metadata rows.
 *
 * Keep only the current forum's surviving list in the checkpoint. A changed
 * source list restarts that forum, and verified writes can safely be replayed.
 * Serialized lists must be read as a unit, but topic checks and duplicate-row
 * cleanup share the request's work budget.
 *
 * @since 2.7.0 bbPress (r8303)
 * @since 2.7.0 bbPress (r8348) Share the procedural worker lifecycle.
 *
 * @param bool $dry_run Whether to preview without writes.
 * @return array Status code, repaired assignments, and progress flags.
 */
function bbp_admin_repair_sticky_batch( $dry_run = false ) {
	$db      = bbp_db();
	$tool_id = 'bbp-sync-all-topics-sticky';
	$batch   = bbp_admin_repair_tool_batch_status(
		array(
			'repaired' => 0,
			'initial'  => false,
		)
	);
	list( $read, $super ) = bbp_admin_repair_get_option( '_bbp_super_sticky_topics', array() );
	if ( ! $read ) {
		bbp_admin_repair_tool_batch_fail( $batch, 'source-read' );
		return $batch;
	}
	$super   = (array) apply_filters( 'bbp_get_super_stickies', is_array( $super ) ? wp_parse_id_list( $super ) : array() );
	$context = array(
		'forum' => bbp_get_forum_post_type(),
		'super' => md5( serialize( $super ) )
	);
	$state  = false;
	$fields = array( 'repaired', 'initial', 'batches' );
	if ( ! bbp_admin_repair_tool_batch_start( $tool_id, $state, $batch, 'bbp_admin_repair_sticky_is_valid_checkpoint', $context, $dry_run ) ) {
		return $batch;
	}
	$batch['initial'] = false === $state;
	$reset = array(
		'current'     => 0,
		'length'      => 0,
		'offset'      => 0,
		'changed'     => 0,
		'source_hash' => '',
		'kept'        => array(),
		'ready'       => false
	);
	if ( $batch['initial'] ) {
		$upper = (int) $db->get_var( "SELECT MAX(ID) FROM {$db->posts}" );
		if ( ! empty( $db->last_error ) ) {
			bbp_admin_repair_tool_batch_fail( $batch, 'source-read' );
			return $batch;
		}
		$state = array_merge(
			$reset,
			array(
				'cursor'   => 0,
				'upper_id' => $upper,
				'context'  => $context
			)
		);
		if ( ! bbp_admin_repair_tool_batch_save( $tool_id, $state, $batch, false, $dry_run, $fields, array( 'metadata' => array() ) ) ) {
			return $batch;
		}
	}
	$size          = bbp_admin_repair_tool_batch_size( $tool_id );
	$preview_state = bbp_admin_repair_tool_restore_preview( $state, $batch, $fields );
	$metadata      = isset( $preview_state['metadata'] ) ? $preview_state['metadata'] : array();
	do {
		$work = 0;
		while ( $work < $size ) {
			$id = $state['current'];
			if ( ! $id ) {
				$id = (int) $db->get_var( $db->prepare( "SELECT ID FROM {$db->posts} WHERE post_type = %s AND ID > %d AND ID <= %d ORDER BY ID LIMIT 1", $context['forum'], $state['cursor'], $state['upper_id'] ) );
				if ( ! empty( $db->last_error ) ) {
					bbp_admin_repair_tool_batch_fail( $batch, 'source-read' );
					break;
				}
				if ( ! $id ) {
					$batch['complete'] = true;
					break;
				}
			}
			$live = $db->get_var( $db->prepare( "SELECT ID FROM {$db->posts} WHERE ID = %d AND post_type = %s", $id, $context['forum'] ) );
			if ( ! empty( $db->last_error ) ) {
				bbp_admin_repair_tool_batch_fail( $batch, 'source-read' );
				break;
			}
			wp_cache_delete( $id, 'post_meta' );
			$stickies = $live ? get_post_meta( $id, '_bbp_sticky_topics', true ) : array();
			if ( ! empty( $db->last_error ) ) {
				bbp_admin_repair_tool_batch_fail( $batch, 'metadata-read' );
				break;
			}
			$hash     = md5( serialize( $stickies ) );
			$expected = $state['kept'] ? array_values( $state['kept'] ) : '';
			$length   = is_array( $stickies ) ? count( $stickies ) : ( empty( $stickies ) ? 0 : 1 );
			$same     = $id === $state['current'] && ( ( $hash === $state['source_hash'] && $length === $state['length'] ) || ( $state['ready'] && $stickies === $expected ) );
			if ( ! $same ) {
				$batch['restarted'] = $batch['restarted'] || (bool) $state['current'];
				$state = array_merge(
					$state,
					$reset,
					array(
						'current'     => $id,
						'length'      => $length,
						'source_hash' => $hash
					)
				);
			}
			if ( ! $live || ( ! $state['ready'] && empty( $stickies ) ) ) {
				$state           = array_merge( $state, $reset );
				$state['cursor'] = $id;
				++$work;
				continue;
			}
			if ( ! $state['ready'] ) {
				if ( ! is_array( $stickies ) ) {
					$state['changed'] = 1;
					$state['offset']  = 1;
					$state['ready']   = true;
					++$work;
				} else {
					$items = array_values( $stickies );
					foreach ( array_slice( $items, $state['offset'], $size - $work ) as $item ) {
						$topic = bbp_get_topic_id( $item );
						wp_cache_delete( $topic, 'posts' );
						wp_cache_delete( $topic, 'post_meta' );
						$is_super = (bool) apply_filters( 'bbp_is_topic_super_sticky', in_array( $topic, $super, true ), $topic );
						$parent   = $is_super ? $id : bbp_get_topic_forum_id( $topic );
						if ( ! empty( $db->last_error ) ) {
							bbp_admin_repair_tool_batch_fail( $batch, 'metadata-read' );
							break 2;
						}
						if ( $parent === $id ) {
							$state['kept'][] = $item;
						} else {
							++$state['changed'];
						}
						++$state['offset'];
						++$work;
					}
					$state['ready'] = count( $items ) === $state['offset'];
				}
				if ( ! $state['ready'] || $work >= $size ) {
					continue;
				}
			}
			$value  = $state['kept'] ? array_values( $state['kept'] ) : '';
			$repair = bbp_admin_repair_single_metadata( 'post', $id, '_bbp_sticky_topics', $value, $size - $work, $dry_run, $metadata );
			$work  += $repair['work'];
			if ( $repair['code'] ) {
				bbp_admin_repair_tool_batch_fail( $batch, 2 === $repair['code'] ? 'metadata-read' : 'metadata-write' );
				break;
			}
			if ( $repair['complete'] ) {
				if ( ! $dry_run ) {
					$mismatches = bbp_admin_repair_metadata_mismatches( 'post', '_bbp_sticky_topics', array( $id => $value ) );
					if ( false === $mismatches || ! empty( $mismatches ) ) {
						bbp_admin_repair_tool_batch_fail( $batch, false === $mismatches ? 'metadata-read' : 'verify' );
						break;
					}
				}
				$batch['repaired'] += $state['changed'];
				$state             = array_merge( $state, $reset );
				$state['cursor']   = $id;
			}
		}
		if ( ! $batch['code'] && ! $state['current'] && ! $batch['complete'] ) {
			$next = $db->get_var( $db->prepare( "SELECT ID FROM {$db->posts} WHERE post_type = %s AND ID > %d AND ID <= %d ORDER BY ID LIMIT 1", $context['forum'], $state['cursor'], $state['upper_id'] ) );
			if ( ! empty( $db->last_error ) ) {
				bbp_admin_repair_tool_batch_fail( $batch, 'source-read' );
			}
			$batch['complete'] = ! $batch['code'] && ! $next;
		}
		$batch['batches'] += max( 1, (int) ceil( $work / $size ) );
		if ( ! bbp_admin_repair_tool_batch_save( $tool_id, $state, $batch, $batch['complete'], $dry_run, $fields, array( 'metadata' => $metadata ) ) ) {
			return $batch;
		}
	} while ( bbp_admin_repair_tool_batch_continue( $batch, $dry_run ) );

	return $batch;
}

/**
 * Repair sticky topic assignments in batches or preview the complete operation.
 *
 * @since 2.7.0 bbPress (r8219)
 * @since 2.7.0 bbPress (r8303) Process one resumable batch per call.
 *
 * @param bool $dry_run Whether to preview changes without updating metadata.
 * @return array Status code, message, and completion flag.
 */
function bbp_admin_repair_sticky_process( $dry_run = false ) {
	$batch = bbp_admin_repair_sticky_batch( $dry_run );
	if ( $dry_run ) {
		/* translators: %s: Status of the dry-run process */
		$statement = esc_html__( 'Checking sticky topic assignments&hellip; %s', 'bbpress' );
	} else {
		/* translators: %s: Status of the repair process */
		$statement = esc_html__( 'Recalculating sticky topic assignments&hellip; %s', 'bbpress' );
	}
	if ( $dry_run ) {
		/* translators: %d: Number of sticky topic assignments that would be repaired */
		$result = sprintf( _n( '%d sticky topic assignment would be repaired.', '%d sticky topic assignments would be repaired.', $batch['repaired'], 'bbpress' ), $batch['repaired'] );
	} else {
		/* translators: %d: Number of repaired sticky topic assignments */
		$result = sprintf( _n( '%d sticky topic assignment repaired.', '%d sticky topic assignments repaired.', $batch['repaired'], 'bbpress' ), $batch['repaired'] );
	}
	return bbp_admin_repair_tool_present_batch( $batch, $statement, $result, $dry_run );
}

/**
 * Repair closed topics.
 *
 * Closed topics that are missing the post-meta "_bbp_status" key value "publish"
 * result in unexpected behavior, primarily this would have only occurred if you
 * had imported forums from another forum package previous to bbPress v2.6,
 * https://bbpress.trac.wordpress.org/ticket/2577
 *
 * @since 2.6.0 bbPress (r5668)
 *
 * @since 2.7.0 bbPress (r8218) Added Dry Run support.
 * @since 2.7.0 bbPress (r8253) Process one resumable batch per call.
 *
 * @return array An array of the status code and the message.
 */
function bbp_admin_repair_closed_topics() {
	return bbp_admin_repair_closed_topics_process();
}

/**
 * Preview the repair of closed topics.
 *
 * @since 2.7.0 bbPress (r8218)
 *
 * @return array An array of the status code and the message.
 */
function bbp_admin_repair_closed_topics_dry_run() {
	return bbp_admin_repair_closed_topics_process( true );
}

/**
 * Validate a closed-topic repair checkpoint.
 *
 * @since 2.7.0 bbPress (r8348)
 * @access private
 *
 * @param mixed $state Saved checkpoint.
 * @return bool Whether the repair checkpoint can be resumed.
 */
function bbp_admin_repair_closed_topics_is_valid_checkpoint( $state ) {
	return bbp_admin_repair_tool_is_valid_checkpoint( $state )
		&& bbp_admin_repair_tool_is_valid_preview_state( $state, array( 'changed', 'processed', 'batches' ) );
}

/**
 * Repair one bounded batch of closed-topic metadata.
 *
 * @since 2.7.0 bbPress (r8348)
 *
 * @param bool $dry_run Whether to preview changes without updating metadata.
 * @return array Status, repair count, and progress flags.
 */
function bbp_admin_repair_closed_topics_batch( $dry_run = false ) {
	$db      = bbp_db();
	$tool_id = 'bbp-sync-closed-topics';
	$fields  = array( 'changed', 'processed', 'batches' );
	$batch   = bbp_admin_repair_tool_batch_status(
		array(
			'changed'   => 0,
			'processed' => 0,
		)
	);
	$state   = false;
	if ( ! bbp_admin_repair_tool_batch_start( $tool_id, $state, $batch, 'bbp_admin_repair_closed_topics_is_valid_checkpoint', array(), $dry_run ) ) {
		return $batch;
	}
	if ( false === $state ) {
		$upper_id = $db->get_var( $db->prepare( "SELECT ID FROM {$db->posts} WHERE post_type = %s AND post_status = %s ORDER BY ID DESC LIMIT 1", bbp_get_topic_post_type(), bbp_get_closed_status_id() ) );
		if ( ! empty( $db->last_error ) ) {
			bbp_admin_repair_tool_batch_fail( $batch, 'source-read' );
			return $batch;
		}
		$state = array(
			'cursor'   => 0,
			'upper_id' => (int) $upper_id,
		);
		if ( ! bbp_admin_repair_tool_batch_save( $tool_id, $state, $batch, false, $dry_run, $fields ) ) {
			return $batch;
		}
	}
	bbp_admin_repair_tool_restore_preview( $state, $batch, $fields );
	$size = bbp_admin_repair_tool_batch_size( $tool_id );
	do {
		$closed_topics = $db->get_col( $db->prepare( "SELECT ID FROM {$db->posts} WHERE post_type = %s AND post_status = %s AND ID > %d AND ID <= %d ORDER BY ID LIMIT %d", bbp_get_topic_post_type(), bbp_get_closed_status_id(), $state['cursor'], $state['upper_id'], $size + 1 ) );
		if ( ! empty( $db->last_error ) ) {
			bbp_admin_repair_tool_batch_fail( $batch, 'source-read' );
			return $batch;
		}
		$complete      = count( $closed_topics ) <= $size;
		$closed_topics = array_slice( $closed_topics, 0, $size );
		$batch['processed'] += count( $closed_topics );

		// Ensure Run and Dry Run inspect current database values.
		wp_cache_delete_multiple( wp_parse_id_list( $closed_topics ), 'post_meta' );
		foreach ( $closed_topics as $closed_topic ) {
			$topic_status = get_post_meta( $closed_topic, '_bbp_status', true );
			if ( ! empty( $db->last_error ) ) {
				bbp_admin_repair_tool_batch_fail( $batch, 'metadata-read' );
				return $batch;
			}
			if ( empty( $topic_status ) ) {
				if ( ! $dry_run && ! bbp_admin_repair_metadata_update( 'post', $closed_topic, '_bbp_status', 'publish' ) ) {
					bbp_admin_repair_tool_batch_fail( $batch, 'metadata-write' );
					return $batch;
				}
				++$batch['changed'];
			}
		}
		if ( ! $complete ) {
			$state['cursor'] = (int) end( $closed_topics );
		}
		++$batch['batches'];
		if ( ! bbp_admin_repair_tool_batch_save( $tool_id, $state, $batch, $complete, $dry_run, $fields ) ) {
			return $batch;
		}
	} while ( bbp_admin_repair_tool_batch_continue( $batch, $dry_run ) );

	return $batch;
}

/**
 * Repair or preview closed topics.
 *
 * @since 2.7.0 bbPress (r8218)
 * @since 2.7.0 bbPress (r8254) Safely restart malformed checkpoints.
 * @since 2.7.0 bbPress (r8348) Share the procedural worker lifecycle.
 *
 * @param bool $dry_run Whether to preview changes without updating metadata.
 * @return array An array of the status code and the message.
 */
function bbp_admin_repair_closed_topics_process( $dry_run = false ) {
	if ( $dry_run ) {
		/* translators: %s: Status of the dry-run process */
		$statement = esc_html__( 'Checking closed topics&hellip; %s', 'bbpress' );
	} else {
		/* translators: %s: Status of the repair process */
		$statement = esc_html__( 'Repairing closed topics&hellip; %s', 'bbpress' );
	}
	$batch     = bbp_admin_repair_closed_topics_batch( $dry_run );
	if ( $dry_run ) {
		/* translators: %d: Number of closed topics that would be repaired */
		$result = sprintf( _n( '%d closed topic would be repaired.', '%d closed topics would be repaired.', $batch['changed'], 'bbpress' ), $batch['changed'] );
	} else {
		/* translators: %d: Number of repaired topics */
		$result = sprintf( _n( '%d closed topic repaired.', '%d closed topics repaired.', $batch['changed'], 'bbpress' ), $batch['changed'] );
	}
	return bbp_admin_repair_tool_present_batch( $batch, $statement, $result, $dry_run, ! $batch['processed'], esc_html__( '0 closed topics to repair.', 'bbpress' ) );
}

/**
 * Repair the private and hidden forums.
 *
 * @since 2.2.0 bbPress (r4105)
 * @since 2.7.0 bbPress (r8240) Added Dry Run support.
 *
 * @return array An array of the status code and the message.
 */
function bbp_admin_repair_forum_visibility() {
	return bbp_admin_repair_forum_visibility_process();
}

/**
 * Preview repairing the private and hidden forum options.
 *
 * @since 2.7.0 bbPress (r8240)
 *
 * @return array An array of the status code and the message.
 */
function bbp_admin_repair_forum_visibility_dry_run() {
	return bbp_admin_repair_forum_visibility_process( true );
}

/**
 * Count changed IDs or malformed values in one visibility option.
 *
 * @since 2.7.0 bbPress (r8304)
 *
 * @param mixed $stored   Existing option value.
 * @param array $expected Expected forum IDs.
 * @return int
 */
function bbp_admin_repair_forum_visibility_changes( $stored, $expected ) {
	$is_default = '' === $stored;
	$valid      = $is_default || ( is_array( $stored ) && array_values( $stored ) === $stored );
	$ids        = is_array( $stored ) ? bbp_get_unique_array_values( wp_parse_id_list( $stored ) ) : array();
	$count      = count( array_unique( array_merge( array_diff( $ids, $expected ), array_diff( $expected, $ids ) ) ) );
	if ( $valid && ! $is_default ) {
		foreach ( $stored as $id ) {
			if ( ! is_int( $id ) || $id < 1 ) {
				$valid = false;
				break;
			}
		}
		$valid = $valid && count( $stored ) === count( $ids );
	}
	return ! $count && ! $valid ? 1 : $count;
}

/**
 * Validate staged visibility lists before resuming their scan or publication.
 *
 * @since 2.7.0 bbPress (r8304)
 * @since 2.7.0 bbPress (r8348) Validate aggregate preview state.
 *
 * @param mixed $state   Saved checkpoint.
 * @param array $context Current forum post type and visibility statuses.
 * @return bool
 */
function bbp_admin_repair_forum_visibility_is_valid_checkpoint( $state, $context ) {
	if ( ! bbp_admin_repair_tool_is_valid_checkpoint( $state, array( 'phase' ) )
		|| $state['phase'] > 4 || ! isset( $state['context'], $state['values'], $state['sources'], $state['changed'] )
		|| $state['context'] !== $context || ! is_bool( $state['changed'] )
		|| ! bbp_admin_repair_tool_is_valid_preview_state( $state, array( 'private', 'hidden', 'checked', 'batches' ), array( 'changed' ), array( 'evaluated' ) )
		|| ! is_array( $state['values'] ) || ! is_array( $state['sources'] )
		|| array_keys( $state['values'] ) !== array( 'private', 'hidden' )
		|| array_keys( $state['sources'] ) !== array( 'private', 'hidden' ) ) {
		return false;
	}
	foreach ( $state['values'] as $type => $ids ) {
		if ( ! is_array( $ids ) || array_values( $ids ) !== $ids
			|| ! is_string( $state['sources'][ $type ] ) || 32 !== strlen( $state['sources'][ $type ] ) ) {
			return false;
		}
		$previous = 0;
		foreach ( $ids as $id ) {
			if ( ! is_int( $id ) || $id <= $previous || $id > $state['upper_id'] ) {
				return false;
			}
			$previous = $id;
		}
	}
	if ( isset( $state['_preview_state'] ) ) {
		$evaluated = $state['_preview_state']['data']['evaluated'];
		if ( bbp_get_unique_array_values( $evaluated ) !== $evaluated || array_diff( $evaluated, array( 'private', 'hidden' ) ) ) {
			return false;
		}
	}
	if ( $state['phase'] >= 2 ) {
		return 0 === $state['cursor'];
	}
	$type   = 0 === $state['phase'] ? 'private' : 'hidden';
	$ids    = $state['values'][ $type ];
	$cursor = empty( $ids ) ? 0 : end( $ids );
	return ( 1 === $state['phase'] || empty( $state['values']['hidden'] ) ) && $cursor === $state['cursor'];
}

/**
 * Build visibility lists in bounded scans, then verify each published option.
 *
 * Keep live options intact until both scans finish. Serialized option lists and
 * their staged replacements are still read as whole values. Source-option or
 * configuration changes restart the scan; verified writes can be replayed.
 *
 * @since 2.7.0 bbPress (r8304)
 * @since 2.7.0 bbPress (r8348) Share the procedural worker lifecycle.
 *
 * @param bool $dry_run Whether to preview without writes or cache invalidation.
 * @return array Status, counts and completion flags.
 */
function bbp_admin_repair_forum_visibility_batch( $dry_run = false ) {
	$db      = bbp_db();
	$tool_id = 'bbp-sync-forum-visibility';
	$types   = array( 'private', 'hidden' );
	$context = array(
		'forum'    => bbp_get_forum_post_type(),
		'statuses' => array( bbp_get_private_status_id(), bbp_get_hidden_status_id() ),
	);
	$batch   = bbp_admin_repair_tool_batch_status(
		array(
			'private'   => 0,
			'hidden'    => 0,
			'checked'   => 0,
			'changed'   => false,
			'evaluated' => array(),
		)
	);
	$fields  = array( 'private', 'hidden', 'checked', 'changed', 'batches' );
	// Read the non-autoloaded stamp directly, bypassing the options preloader
	list( $read, $version ) = bbp_admin_repair_get_option( '_bbp_forum_visibility_version', '' );
	if ( ! $read || ! is_string( $version ) ) {
		bbp_admin_repair_tool_batch_fail( $batch, 'source-read' );
		return $batch;
	}
	if ( '' === $version && ! $dry_run ) {
		if ( ! bbp_update_forum_visibility_version() ) {
			bbp_admin_repair_tool_batch_fail( $batch, 'metadata-write' );
			return $batch;
		}
		list( $read, $version ) = bbp_admin_repair_get_option( '_bbp_forum_visibility_version', '' );
		if ( ! $read || ! is_string( $version ) || '' === $version ) {
			bbp_admin_repair_tool_batch_fail( $batch, 'source-read' );
			return $batch;
		}
	}
	$context['version'] = $version;
	$state = false;
	if ( ! bbp_admin_repair_tool_batch_start( $tool_id, $state, $batch, 'bbp_admin_repair_forum_visibility_is_valid_checkpoint', $context, $dry_run ) ) {
		return $batch;
	}
	$stored = array();
	foreach ( $types as $index => $type ) {
		$option = '_bbp_' . $type . '_forums';

		list( $read, $stored[ $type ] ) = bbp_admin_repair_get_option( $option, array() );
		if ( ! $read ) {
			bbp_admin_repair_tool_batch_fail( $batch, 'source-read' );
			return $batch;
		}
		if ( false !== $state && md5( serialize( $stored[ $type ] ) ) !== $state['sources'][ $type ] ) {
			// A completed scan may have published this option before a failed save
			$same = $state['phase'] >= 2 && bbp_admin_repair_option_matches( $option, $state['values'][ $type ] );
			if ( ! empty( $db->last_error ) ) {
				bbp_admin_repair_tool_batch_fail( $batch, 'source-read' );
				return $batch;
			}
			if ( ! $same ) {
				$state              = false;
				$batch['restarted'] = true;
			}
		}
	}
	if ( false === $state ) {
		$upper = (int) $db->get_var( "SELECT MAX(ID) FROM `{$db->posts}`" );
		if ( ! empty( $db->last_error ) ) {
			bbp_admin_repair_tool_batch_fail( $batch, 'source-read' );
			return $batch;
		}
		$state = array(
			'cursor'   => 0,
			'upper_id' => $upper,
			'phase'    => 0,
			'context'  => $context,
			'values'   => array(
				'private' => array(),
				'hidden'  => array(),
			),
			'sources'  => array(
				'private' => md5( serialize( $stored['private'] ) ),
				'hidden'  => md5( serialize( $stored['hidden'] ) ),
			),
			'changed'  => false,
		);
		if ( ! bbp_admin_repair_tool_batch_save( $tool_id, $state, $batch, false, $dry_run, $fields, array( 'evaluated' => array() ) ) ) {
			return $batch;
		}
	}
	foreach ( $types as $index => $type ) {
		if ( $state['phase'] > 2 + $index ) {
			$batch['evaluated'][] = $type;
		}
	}
	$size          = bbp_admin_repair_tool_batch_size( $tool_id );
	$preview_state = bbp_admin_repair_tool_restore_preview( $state, $batch, $fields );
	if ( isset( $preview_state['evaluated'] ) ) {
		$batch['evaluated'] = bbp_get_unique_array_values( array_merge( $batch['evaluated'], $preview_state['evaluated'] ) );
	}
	do {
		$work = 0;
		while ( $work < $size && $state['phase'] < 4 ) {
			$index  = $state['phase'] % 2;
			$type   = $types[ $index ];
			$option = '_bbp_' . $type . '_forums';
			if ( $state['phase'] < 2 ) {
				$remaining = $size - $work;
				$ids       = $db->get_col( $db->prepare( "SELECT ID FROM `{$db->posts}` WHERE `post_type` = %s AND `post_status` = %s AND ID > %d AND ID <= %d ORDER BY ID LIMIT %d", $context['forum'], $context['statuses'][ $index ], $state['cursor'], $state['upper_id'], $remaining + 1 ) );
				if ( ! empty( $db->last_error ) ) {
					bbp_admin_repair_tool_batch_fail( $batch, 'source-read' );
					break;
				}
				$selected = array_slice( $ids, 0, $remaining );
				foreach ( $selected as $id ) {
					$state['values'][ $type ][] = (int) $id;
					$state['cursor']            = (int) $id;
				}
				$batch['checked'] += count( $selected );
				$work             += max( 1, count( $selected ) );
				if ( count( $ids ) <= $remaining ) {
					++$state['phase'];
					$state['cursor'] = 0;
				}
				continue;
			}
			list( $read, $current_version ) = bbp_admin_repair_get_option( '_bbp_forum_visibility_version', '' );
			if ( ! $read || $current_version !== $version ) {
				if ( ! $read ) {
					bbp_admin_repair_tool_batch_fail( $batch, 'source-read' );
				}
				break;
			}
			$changes = bbp_admin_repair_forum_visibility_changes( $stored[ $type ], $state['values'][ $type ] );
			if ( ! $dry_run ) {
				if ( $changes && ! bbp_admin_repair_update_option( $option, $state['values'][ $type ] ) ) {
					bbp_admin_repair_tool_batch_fail( $batch, 'metadata-write' );
					break;
				}
				if ( ! $changes ) {
					wp_cache_delete_multiple( array( $option, 'alloptions', 'notoptions' ), 'options' );
				}
			}
			$batch[ $type ]       = $changes;
			$batch['evaluated'][] = $type;
			$state['changed']     = $state['changed'] || (bool) $changes;
			++$state['phase'];
			++$work;
		}
		$invalidated = false;
		if ( ! $batch['code'] ) {
			list( $read, $current_version ) = bbp_admin_repair_get_option( '_bbp_forum_visibility_version', '' );
			if ( ! $read ) {
				bbp_admin_repair_tool_batch_fail( $batch, 'source-read' );
			} else {
				$invalidated = $current_version !== $version;
			}
		}
		$batch['changed']   = $state['changed'];
		$batch['restarted'] = $batch['restarted'] || $invalidated;
		if ( $invalidated ) {
			$batch['evaluated'] = array();
		}
		$batch['complete'] = ! $batch['code'] && ! $invalidated && 4 === $state['phase'];
		$batch['batches'] += max( 1, (int) ceil( $work / $size ) );
		if ( ! bbp_admin_repair_tool_batch_save( $tool_id, $state, $batch, $batch['complete'] || $invalidated, $dry_run, $fields, array( 'evaluated' => $batch['evaluated'] ) ) ) {
			return $batch;
		}
		if ( $invalidated ) {
			$batch['complete'] = false;
		}
	} while ( bbp_admin_repair_tool_batch_continue( $batch, $dry_run ) && ! $invalidated );

	return $batch;
}

/**
 * Repair forum visibility in batches or preview the complete operation.
 *
 * @since 2.7.0 bbPress (r8240)
 * @since 2.7.0 bbPress (r8304) Processes one resumable batch per call.
 *
 * @param bool $dry_run Whether to preview without writes.
 * @return array Tool result and completion flag.
 */
function bbp_admin_repair_forum_visibility_process( $dry_run = false ) {
	$batch = bbp_admin_repair_forum_visibility_batch( $dry_run );
	if ( $dry_run ) {
		/* translators: %s: Status of the dry-run process */
		$statement = esc_html__( 'Checking forum visibility&hellip; %s', 'bbpress' );
	} else {
		/* translators: %s: Status of the repair process */
		$statement = esc_html__( 'Recalculating forum visibility&hellip; %s', 'bbpress' );
	}
	/* translators: %s: Number of non-public forums checked in this batch */
	$checked = sprintf( esc_html( _n( '%s non-public forum checked.', '%s non-public forums checked.', $batch['checked'], 'bbpress' ) ), bbp_number_format( $batch['checked'] ) );
	$details = array( $checked );
	if ( $dry_run ) {
		/* translators: %s: Number of private forum visibility entries */
		$private = _n( '%s private forum visibility entry would be repaired.', '%s private forum visibility entries would be repaired.', $batch['private'], 'bbpress' );
		/* translators: %s: Number of hidden forum visibility entries */
		$hidden = _n( '%s hidden forum visibility entry would be repaired.', '%s hidden forum visibility entries would be repaired.', $batch['hidden'], 'bbpress' );
	} else {
		/* translators: %s: Number of private forum visibility entries */
		$private = _n( '%s private forum visibility entry repaired.', '%s private forum visibility entries repaired.', $batch['private'], 'bbpress' );
		/* translators: %s: Number of hidden forum visibility entries */
		$hidden = _n( '%s hidden forum visibility entry repaired.', '%s hidden forum visibility entries repaired.', $batch['hidden'], 'bbpress' );
	}
	$messages = array(
		'private' => $private,
		'hidden'  => $hidden,
	);
	foreach ( $batch['evaluated'] as $type ) {
		$details[] = sprintf( esc_html( $messages[ $type ] ), bbp_number_format( $batch[ $type ] ) );
	}
	if ( count( $batch['evaluated'] ) < 2 ) {
		$details[] = esc_html__( 'Visibility counts are not complete yet.', 'bbpress' );
	}
	return bbp_admin_repair_tool_present_batch(
		$batch,
		$statement,
		implode( "\n", $details ),
		$dry_run,
		! $batch['changed'],
		esc_html__( '0 forum visibility entries to repair.', 'bbpress' )
	);
}

/**
 * Repair the parent forum reference for each forum, topic, and reply.
 *
 * @since 2.1.0 bbPress (r3884)
 * @since 2.7.0 bbPress (r8239) Added Dry Run support.
 * @since 2.7.0 bbPress (r8280) Processes one resumable batch per call.
 *
 * @return array Tool result and completion flag.
 */
function bbp_admin_repair_forum_meta() {
	return bbp_admin_repair_forum_meta_process();
}

/**
 * Preview repairing the parent forum reference for each forum, topic, and reply.
 *
 * @since 2.7.0 bbPress (r8239)
 * @since 2.7.0 bbPress (r8280) Added resumable batching.
 * @since 2.7.0 bbPress (r8331) Previews the complete operation without changing saved progress.
 *
 * @return array Tool result and completion flag.
 */
function bbp_admin_repair_forum_meta_dry_run() {
	return bbp_admin_repair_forum_meta_process( true );
}

/**
 * Repair one batch of parent-forum references.
 *
 * @since 2.7.0 bbPress (r8280)
 *
 * @param bool $dry_run Whether to preview without writes.
 * @return array Status code, repair counts, and progress flags.
 */
function bbp_admin_repair_forum_meta_batch( $dry_run = false ) {
	return bbp_admin_repair_post_reference_batch( 'forum', $dry_run );
}

/**
 * Repair or preview repairing the parent forum reference for each post.
 *
 * @since 2.7.0 bbPress (r8239)
 * @since 2.7.0 bbPress (r8280) Processes one resumable batch per call.
 *
 * @param bool $dry_run Whether to preview changes without updating data.
 * @return array Tool result and completion flag.
 */
function bbp_admin_repair_forum_meta_process( $dry_run = false ) {
	if ( $dry_run ) {
		/* translators: %s: Status of the dry-run process */
		$statement = esc_html__( 'Checking parent forum references&hellip; %s', 'bbpress' );
	} else {
		/* translators: %s: Status of the repair process */
		$statement = esc_html__( 'Recalculating parent forum references&hellip; %s', 'bbpress' );
	}

	$batch     = bbp_admin_repair_forum_meta_batch( $dry_run );
	$repaired = $batch['forums'] + $batch['topics'] + $batch['replies'] + $batch['stale'];
	if ( $dry_run ) {
		$forum_result = sprintf(
			/* translators: %s: Number of forum parent references */
			esc_html( _n( '%s forum parent reference would be repaired.', '%s forum parent references would be repaired.', $batch['forums'], 'bbpress' ) ),
			bbp_number_format( $batch['forums'] )
		);
		$topic_result = sprintf(
			/* translators: %s: Number of topic parent forum references */
			esc_html( _n( '%s topic parent forum reference would be repaired.', '%s topic parent forum references would be repaired.', $batch['topics'], 'bbpress' ) ),
			bbp_number_format( $batch['topics'] )
		);
		$reply_result = sprintf(
			/* translators: %s: Number of reply parent forum references */
			esc_html( _n( '%s reply parent forum reference would be repaired.', '%s reply parent forum references would be repaired.', $batch['replies'], 'bbpress' ) ),
			bbp_number_format( $batch['replies'] )
		);
		$stale_result = sprintf(
			/* translators: %s: Number of orphaned parent forum references */
			esc_html( _n( '%s orphaned parent forum reference would be removed.', '%s orphaned parent forum references would be removed.', $batch['stale'], 'bbpress' ) ),
			bbp_number_format( $batch['stale'] )
		);
	} else {
		$forum_result = sprintf(
			/* translators: %s: Number of forum parent references */
			esc_html( _n( '%s forum parent reference repaired.', '%s forum parent references repaired.', $batch['forums'], 'bbpress' ) ),
			bbp_number_format( $batch['forums'] )
		);
		$topic_result = sprintf(
			/* translators: %s: Number of topic parent forum references */
			esc_html( _n( '%s topic parent forum reference repaired.', '%s topic parent forum references repaired.', $batch['topics'], 'bbpress' ) ),
			bbp_number_format( $batch['topics'] )
		);
		$reply_result = sprintf(
			/* translators: %s: Number of reply parent forum references */
			esc_html( _n( '%s reply parent forum reference repaired.', '%s reply parent forum references repaired.', $batch['replies'], 'bbpress' ) ),
			bbp_number_format( $batch['replies'] )
		);
		$stale_result = sprintf(
			/* translators: %s: Number of orphaned parent forum references */
			esc_html( _n( '%s orphaned parent forum reference removed.', '%s orphaned parent forum references removed.', $batch['stale'], 'bbpress' ) ),
			bbp_number_format( $batch['stale'] )
		);
	}
	return bbp_admin_repair_tool_present_batch(
		$batch,
		$statement,
		$forum_result . "\n" . $topic_result . "\n" . $reply_result . "\n" . $stale_result,
		$dry_run,
		! $repaired && ( $dry_run || $batch['initial'] ),
		esc_html__( '0 parent forum references to repair.', 'bbpress' )
	);
}

/**
 * Repair the topic reference for each topic and reply.
 *
 * @since 2.1.0 bbPress (r3884)
 * @since 2.7.0 bbPress (r8238) Added Dry Run support.
 * @since 2.7.0 bbPress (r8277) Processes one resumable batch per call.
 *
 * @return array Tool result and completion flag.
 */
function bbp_admin_repair_topic_meta() {
	return bbp_admin_repair_topic_meta_process();
}

/**
 * Preview repairing the topic reference for each topic and reply.
 *
 * @since 2.7.0 bbPress (r8238)
 * @since 2.7.0 bbPress (r8277) Added resumable batching.
 * @since 2.7.0 bbPress (r8331) Previews the complete operation without changing saved progress.
 *
 * @return array Tool result and completion flag.
 */
function bbp_admin_repair_topic_meta_dry_run() {
	return bbp_admin_repair_topic_meta_process( true );
}

/**
 * Repair one bounded batch of topic references.
 *
 * Include stored references on unrelated or missing posts so orphan cleanup
 * shares the same cursor and write budget as valid topic and reply references.
 *
 * @since 2.7.0 bbPress (r8277)
 * @since 2.7.0 bbPress (r8280) Shares the post-reference worker.
 *
 * @param bool $dry_run Whether to preview without writes.
 * @return array Status code, repair counts, and progress flags.
 */
function bbp_admin_repair_topic_meta_batch( $dry_run = false ) {
	return bbp_admin_repair_post_reference_batch( 'topic', $dry_run );
}

/**
 * Repair one topic-reference batch or preview the complete operation.
 *
 * @since 2.7.0 bbPress (r8238)
 * @since 2.7.0 bbPress (r8277) Processes one resumable batch per call.
 *
 * @param bool $dry_run Whether to preview changes without updating data.
 * @return array Tool result and completion flag.
 */
function bbp_admin_repair_topic_meta_process( $dry_run = false ) {
	if ( $dry_run ) {
		/* translators: %s: Status of the dry-run process */
		$statement = esc_html__( 'Checking topic references&hellip; %s', 'bbpress' );
	} else {
		/* translators: %s: Status of the repair process */
		$statement = esc_html__( 'Recalculating topic references&hellip; %s', 'bbpress' );
	}
	$batch     = bbp_admin_repair_topic_meta_batch( $dry_run );
	if ( $dry_run ) {
		$topics = sprintf(
			/* translators: %s: Number of topic self-references */
			esc_html( _n( '%s topic self-reference would be repaired.', '%s topic self-references would be repaired.', $batch['topics'], 'bbpress' ) ),
			bbp_number_format( $batch['topics'] )
		);
		$replies = sprintf(
			/* translators: %s: Number of reply parent topic references */
			esc_html( _n( '%s reply parent topic reference would be repaired.', '%s reply parent topic references would be repaired.', $batch['replies'], 'bbpress' ) ),
			bbp_number_format( $batch['replies'] )
		);
		$stale = sprintf(
			/* translators: %s: Number of orphaned topic references */
			esc_html( _n( '%s orphaned topic reference would be removed.', '%s orphaned topic references would be removed.', $batch['stale'], 'bbpress' ) ),
			bbp_number_format( $batch['stale'] )
		);
	} else {
		$topics = sprintf(
			/* translators: %s: Number of topic self-references */
			esc_html( _n( '%s topic self-reference repaired.', '%s topic self-references repaired.', $batch['topics'], 'bbpress' ) ),
			bbp_number_format( $batch['topics'] )
		);
		$replies = sprintf(
			/* translators: %s: Number of reply parent topic references */
			esc_html( _n( '%s reply parent topic reference repaired.', '%s reply parent topic references repaired.', $batch['replies'], 'bbpress' ) ),
			bbp_number_format( $batch['replies'] )
		);
		$stale = sprintf(
			/* translators: %s: Number of orphaned topic references */
			esc_html( _n( '%s orphaned topic reference removed.', '%s orphaned topic references removed.', $batch['stale'], 'bbpress' ) ),
			bbp_number_format( $batch['stale'] )
		);
	}
	return bbp_admin_repair_tool_present_batch(
		$batch,
		$statement,
		$topics . "\n" . $replies . "\n" . $stale,
		$dry_run,
		! $batch['topics'] && ! $batch['replies'] && ! $batch['stale'] && ( $dry_run || $batch['initial'] ),
		esc_html__( '0 topic references to repair.', 'bbpress' )
	);
}

/**
 * Recalculate reply menu order.
 *
 * @since 2.6.0 bbPress (r5368)
 * @since 2.7.0 bbPress (r8220) Added Dry Run support.
 *
 * @return array An array of the status code and the message.
 */
function bbp_admin_repair_reply_menu_order() {
	return bbp_admin_repair_reply_menu_order_process();
}

/**
 * Preview the recalculation of reply menu order.
 *
 * @since 2.7.0 bbPress (r8220)
 *
 * @return array An array of the status code and the message.
 */
function bbp_admin_repair_reply_menu_order_dry_run() {
	return bbp_admin_repair_reply_menu_order_process( true );
}

/**
 * Get a reply's native position without loading an unfiltered child list.
 *
 * Preserve the complete child-list filter contract when a callback is present.
 * Default ordering is ascending ID, excluding drafts and scheduled replies.
 * Reuse counted prefixes within this batch until native source caches change.
 *
 * @since 2.7.0 bbPress (r8305)
 *
 * @param object $reply Current reply row.
 * @param array  $ranks Ranks shared within this batch, keyed by topic ID.
 * @param array|null $preview Read-only window context, or null for Run.
 * @return int|false Position, or false after a read failure.
 */
function bbp_admin_repair_reply_menu_order_position( $reply, &$ranks = array(), &$preview = null ) {
	$db      = bbp_db();
	$dry_run = is_array( $preview );
	if ( ! $dry_run ) {
		wp_cache_delete( $reply->ID, 'posts' );
		wp_cache_delete( $reply->ID, 'post_meta' );
	}
	$topic_id = bbp_get_reply_topic_id( $reply->ID );
	if ( $dry_run ) {
		if ( ! isset( $preview['cached'][ $topic_id ] ) ) {
			wp_cache_delete( $topic_id, 'posts' );
			wp_cache_delete( $topic_id, 'post_meta' );
			$preview['cached'][ $topic_id ] = true;
		}
	} else {
		wp_cache_delete( $topic_id, 'post_meta' );
	}
	if ( has_filter( 'bbp_get_all_child_ids' ) ) {
		$position = bbp_get_reply_position_raw( $reply->ID, $topic_id );
		return empty( $db->last_error ) ? $position : false;
	}
	$topic_id = ! empty( $topic_id ) ? bbp_get_topic_id( $topic_id ) : bbp_get_reply_topic_id( $reply->ID );
	$count    = bbp_get_topic_reply_count( $topic_id, false );
	if ( ! empty( $db->last_error ) ) {
		return false;
	}
	if ( empty( $topic_id ) || (int) $reply->ID === $topic_id || empty( $count ) || (int) $reply->post_parent !== $topic_id ) {
		return 0;
	}
	$last_changed = wp_cache_get_last_changed( 'bbpress_posts' );
	$cursor = 0;
	$prefix = 0;
	if ( isset( $ranks[ $topic_id ] ) && $ranks[ $topic_id ]['last_changed'] === $last_changed
		&& $ranks[ $topic_id ]['cursor'] < (int) $reply->ID ) {
		$cursor = $ranks[ $topic_id ]['cursor'];
		$prefix = $ranks[ $topic_id ]['position'];
	}
	$position = $db->get_row( bbp_admin_repair_reply_menu_order_query( $topic_id, $cursor, $reply->ID, $reply->ID ) );
	if ( ! empty( $db->last_error ) || ! $position || wp_cache_get_last_changed( 'bbpress_posts' ) !== $last_changed ) {
		unset( $ranks[ $topic_id ] );
		return false;
	}
	$prefix += (int) $position->reply_position;
	$ranks[ $topic_id ] = array(
		'cursor'       => (int) $reply->ID,
		'position'     => $prefix,
		'last_changed' => $last_changed,
	);
	return $position->included ? $prefix : 0;
}

/**
 * Build the native, bounded reply-position count query.
 *
 * @since 2.7.0 bbPress (r8331)
 * @access private
 *
 * @param int $topic_id Topic ID.
 * @param int $cursor Lower reply ID, excluded.
 * @param int $upper_id Upper reply ID, included.
 * @param int $reply_id Reply whose inclusion should be checked.
 * @return string Prepared query.
 */
function bbp_admin_repair_reply_menu_order_query( $topic_id, $cursor, $upper_id, $reply_id = 0 ) {
	$db = bbp_db();
	return $db->prepare( "SELECT COUNT(*) AS reply_position, MAX(ID = %d) AS included FROM {$db->posts} WHERE post_parent = %d AND post_type = %s AND post_status NOT IN ('draft', 'future') AND ID > %d AND ID <= %d", $reply_id, $topic_id, bbp_get_reply_post_type(), $cursor, $upper_id );
}

/**
 * Prepare a bounded, read-only source and cache window for reply positions.
 *
 * Retain ranks only for this window's parents. Every selected reply still runs
 * through the same native position helper as Run. The window only bounds source
 * rows and WordPress object-cache memory during a complete Dry Run.
 *
 * @since 2.7.0 bbPress (r8331)
 * @access private
 *
 * @param array $state Validated scan bounds and phase.
 * @param int $size Maximum source rows to read.
 * @param array $ranks Native source prefixes shared with the position helper.
 * @param array $preview Source rows, positions, and cache IDs for this window.
 * @return void
 */
function bbp_admin_repair_reply_menu_order_preview_window( $state, $size, &$ranks, &$preview ) {
	$db      = bbp_db();
	$preview = array(
		'error'  => '',
		'rows'   => array(),
		'cached' => array(),
	);
	if ( 0 === $state['phase'] ) {
		$preview['rows'] = $db->get_results( $db->prepare( "SELECT meta_id, post_id, (post_id = meta_value) AS self_reference FROM {$db->postmeta} WHERE meta_key = '_bbp_reply_to' AND meta_id > %d AND meta_id <= %d ORDER BY meta_id LIMIT %d", $state['meta_cursor'], $state['meta_upper'], $size ) );
		$preview['error'] = empty( $db->last_error ) ? '' : 'metadata-read';
		return;
	}
	$preview['rows'] = $db->get_results( $db->prepare( "SELECT ID, post_parent, post_status, menu_order FROM {$db->posts} WHERE post_type = %s AND ID > %d AND ID <= %d ORDER BY ID LIMIT %d", $state['context'], $state['cursor'], $state['upper_id'], $size ) );
	if ( ! empty( $db->last_error ) ) {
		$preview['error'] = 'source-read';
		return;
	}
	if ( ! $preview['rows'] ) {
		return;
	}
	$ids               = wp_list_pluck( $preview['rows'], 'ID' );
	$parents           = wp_list_pluck( $preview['rows'], 'post_parent' );
	$ranks             = array_intersect_key( $ranks, array_fill_keys( wp_parse_id_list( $parents ), true ) );
	$ids               = wp_parse_id_list( array_merge( $ids, $parents ) );
	$preview['cached'] = array_fill_keys( $ids, true );
	wp_cache_delete_multiple( $ids, 'posts' );
	wp_cache_delete_multiple( $ids, 'post_meta' );
	_prime_post_caches( $ids, false, true );
	if ( ! empty( $db->last_error ) ) {
		$preview['error'] = 'source-read';
	}
}

/**
 * Validate the reply and metadata scan cursors.
 *
 * @since 2.7.0 bbPress (r8305)
 *
 * @param mixed $state Saved checkpoint.
 * @return bool
 */
function bbp_admin_repair_reply_menu_order_is_valid_checkpoint( $state ) {
	$valid = bbp_admin_repair_tool_is_valid_checkpoint( $state, array( 'phase', 'meta_cursor', 'meta_upper' ) )
		&& $state['phase'] <= 2 && $state['meta_cursor'] <= $state['meta_upper']
		&& isset( $state['context'], $state['changed'] )
		&& bbp_get_reply_post_type() === $state['context'] && is_bool( $state['changed'] );
	if ( ! $valid || ! isset( $state['_preview_state'] ) ) {
		return $valid && ! isset( $state['_preview'] );
	}

	if ( ! bbp_admin_repair_tool_is_valid_preview_state( $state, array( 'relationships', 'positions', 'batches' ), array(), array( 'ranks' ) ) ) {
		return false;
	}

	$preview = $state['_preview_state'];
	foreach ( $preview['data']['ranks'] as $topic_id => $rank ) {
		if ( ! is_int( $topic_id ) || $topic_id < 1 || ! is_array( $rank )
			|| ! isset( $rank['cursor'], $rank['position'], $rank['last_changed'] )
			|| ! is_int( $rank['cursor'] ) || $rank['cursor'] < 0
			|| ! is_int( $rank['position'] ) || $rank['position'] < 0
			|| ! is_string( $rank['last_changed'] ) ) {
			return false;
		}
	}

	return true;
}

/**
 * Repair self references and reply positions within one work budget.
 *
 * Inspect every reply rather than selecting duplicates that earlier writes can
 * remove from the result set. Both cursors have fixed bounds, and advance only
 * after verified work. No complete reply or metadata list is stored.
 *
 * @since 2.7.0 bbPress (r8305)
 *
 * @param bool $dry_run Whether to preview without writes.
 * @return array Status, counts and completion flags.
 */
function bbp_admin_repair_reply_menu_order_batch( $dry_run = false ) {
	$db      = bbp_db();
	$tool_id = 'bbp-sync-all-reply-positions';
	$batch   = bbp_admin_repair_tool_batch_status(
		array(
			'relationships' => 0,
			'positions'     => 0,
			'changed'       => false,
		)
	);
	$state = false;
	if ( ! bbp_admin_repair_tool_batch_start( $tool_id, $state, $batch, 'bbp_admin_repair_reply_menu_order_is_valid_checkpoint', array(), $dry_run ) ) {
		return $batch;
	}
	if ( false === $state ) {
		$bounds = $db->get_row( "SELECT (SELECT MAX(ID) FROM {$db->posts}) AS upper_id, (SELECT MAX(meta_id) FROM {$db->postmeta}) AS meta_upper" );
		if ( ! empty( $db->last_error ) || ! $bounds ) {
			bbp_admin_repair_tool_batch_fail( $batch, 'source-read' );
			return $batch;
		}
		$state = array(
			'cursor'      => 0,
			'upper_id'    => (int) $bounds->upper_id,
			'meta_cursor' => 0,
			'meta_upper'  => (int) $bounds->meta_upper,
			'phase'       => 0,
			'context'     => bbp_get_reply_post_type(),
			'changed'     => false,
		);
	}
	$size          = bbp_admin_repair_tool_batch_size( $tool_id );
	$preview_state = bbp_admin_repair_tool_restore_preview( $state, $batch, array( 'relationships', 'positions', 'batches' ) );
	$ranks         = isset( $preview_state['ranks'] ) ? $preview_state['ranks'] : array();
	do {
		$preview = null;
		$work    = 0;
		try {
			if ( $dry_run ) {
				bbp_admin_repair_reply_menu_order_preview_window( $state, $size, $ranks, $preview );
				if ( $preview['error'] ) {
					bbp_admin_repair_tool_batch_fail( $batch, $preview['error'] );
					break;
				}
			}
			for ( $work = 0; $work < $size && $state['phase'] < 2; ++$work ) {
				if ( 0 === $state['phase'] ) {
					$row = $dry_run ? array_shift( $preview['rows'] ) : $db->get_row( $db->prepare( "SELECT meta_id, post_id, (post_id = meta_value) AS self_reference FROM {$db->postmeta} WHERE meta_key = '_bbp_reply_to' AND meta_id > %d AND meta_id <= %d ORDER BY meta_id LIMIT 1", $state['meta_cursor'], $state['meta_upper'] ) );
					if ( ! empty( $db->last_error ) ) {
						bbp_admin_repair_tool_batch_fail( $batch, 'metadata-read' );
						break;
					}
					if ( ! $row ) {
						$state['phase'] = 1;
						if ( $dry_run && $work + 1 < $size ) {
							bbp_admin_repair_reply_menu_order_preview_window( $state, $size - $work - 1, $ranks, $preview );
							if ( $preview['error'] ) {
								bbp_admin_repair_tool_batch_fail( $batch, $preview['error'] );
								break;
							}
						}
						continue;
					}
					if ( $row->self_reference ) {
						if ( ! $dry_run ) {
							wp_cache_delete( $row->post_id, 'post_meta' );
						}
						if ( ! $dry_run && bbp_admin_repair_metadata_row( 'post', $row, '_bbp_reply_to', null ) ) {
							bbp_admin_repair_tool_batch_fail( $batch, 'metadata-write' );
							break;
						}
						++$batch['relationships'];
						$state['changed'] = true;
					}
					$state['meta_cursor'] = (int) $row->meta_id;
					continue;
				}
				$reply = $dry_run ? array_shift( $preview['rows'] ) : $db->get_row( $db->prepare( "SELECT ID, post_parent, post_status, menu_order FROM {$db->posts} WHERE post_type = %s AND ID > %d AND ID <= %d ORDER BY ID LIMIT 1", $state['context'], $state['cursor'], $state['upper_id'] ) );
				if ( ! empty( $db->last_error ) ) {
					bbp_admin_repair_tool_batch_fail( $batch, 'source-read' );
					break;
				}
				if ( ! $reply ) {
					$state['phase'] = 2;
					continue;
				}
				$position = bbp_admin_repair_reply_menu_order_position( $reply, $ranks, $preview );
				if ( false === $position ) {
					bbp_admin_repair_tool_batch_fail( $batch, 'source-read' );
					break;
				}
				// A getter callback may have moved, converted or deleted the selected reply
				$source = $db->get_row( $db->prepare( "SELECT post_parent, post_status FROM {$db->posts} WHERE ID = %d AND post_type = %s", $reply->ID, $state['context'] ) );
				if ( ! empty( $db->last_error ) ) {
					bbp_admin_repair_tool_batch_fail( $batch, 'source-read' );
					break;
				}
				if ( ! $source || $source->post_parent !== $reply->post_parent
					|| $source->post_status !== $reply->post_status ) {
					$ranks           = array();
					$state['cursor'] = (int) $reply->ID;
					continue;
				}
				if ( $position !== (int) $reply->menu_order ) {
					if ( ! $dry_run ) {
						bbp_update_reply_position( $reply->ID, $position );
						$stored = $db->get_var( $db->prepare( "SELECT menu_order FROM {$db->posts} WHERE ID = %d", $reply->ID ) );
						if ( empty( $db->last_error ) && null === $stored ) {
							$ranks           = array();
							$state['cursor'] = (int) $reply->ID;
							continue;
						}
						if ( ! empty( $db->last_error ) || (int) $stored !== $position ) {
							bbp_admin_repair_tool_batch_fail( $batch, 'verify' );
							break;
						}
					}
					++$batch['positions'];
					$state['changed'] = true;
				}
				$state['cursor'] = (int) $reply->ID;
			}
		} finally {
			if ( $dry_run && ! empty( $preview['cached'] ) ) {
				$ids = array_keys( $preview['cached'] );
				wp_cache_delete_multiple( $ids, 'posts' );
				wp_cache_delete_multiple( $ids, 'post_meta' );
			}
		}

		$batch['changed']  = $state['changed'];
		$complete          = ! $batch['code'] && 2 === $state['phase'];
		$batch['batches'] += max( 1, (int) ceil( $work / $size ) );
		bbp_admin_repair_tool_batch_save( $tool_id, $state, $batch, $complete, $dry_run, array( 'relationships', 'positions', 'batches' ), array( 'ranks' => $ranks ) );
	} while ( bbp_admin_repair_tool_batch_continue( $batch, $dry_run ) );

	return $batch;
}

/**
 * Repair reply positions in batches or preview the complete operation.
 *
 * @since 2.7.0 bbPress (r8220)
 * @since 2.7.0 bbPress (r8305) Processes one resumable batch per call.
 *
 * @param bool $dry_run Whether to preview without writes.
 * @return array Tool result and completion flag.
 */
function bbp_admin_repair_reply_menu_order_process( $dry_run = false ) {
	$batch = bbp_admin_repair_reply_menu_order_batch( $dry_run );
	if ( $dry_run ) {
		/* translators: %s: Status of the dry-run process */
		$statement = esc_html__( 'Checking reply menu order&hellip; %s', 'bbpress' );
	} else {
		/* translators: %s: Status of the repair process */
		$statement = esc_html__( 'Recalculating reply menu order&hellip; %s', 'bbpress' );
	}
	if ( $dry_run ) {
		/* translators: %s: Number of self-referential reply-to references */
		$relationships = _n( '%s self-referential reply-to reference would be removed.', '%s self-referential reply-to references would be removed.', $batch['relationships'], 'bbpress' );
		/* translators: %s: Number of reply positions */
		$positions = _n( '%s reply position would be recalculated.', '%s reply positions would be recalculated.', $batch['positions'], 'bbpress' );
	} else {
		/* translators: %s: Number of removed self-referential reply-to references */
		$relationships = _n( '%s self-referential reply-to reference was removed.', '%s self-referential reply-to references were removed.', $batch['relationships'], 'bbpress' );
		/* translators: %s: Number of recalculated reply positions */
		$positions = _n( '%s reply position was recalculated.', '%s reply positions were recalculated.', $batch['positions'], 'bbpress' );
	}
	$details = sprintf( esc_html( $relationships ), bbp_number_format( $batch['relationships'] ) ) . "\n"
		. sprintf( esc_html( $positions ), bbp_number_format( $batch['positions'] ) );
	return bbp_admin_repair_tool_present_batch(
		$batch,
		$statement,
		$details,
		$dry_run,
		! $batch['changed'],
		esc_html__( '0 reply positions or reply-to references to repair.', 'bbpress' )
	);
}
