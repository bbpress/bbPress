<?php

/**
 * bbPress User Mention Backfill.
 *
 * @package bbPress
 * @subpackage Users
 */

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

/**
 * Return this site's saved mention backfill progress.
 *
 * @since 2.7.0 bbPress (r8264)
 *
 * @return array Empty before the first run.
 */
function bbp_get_mention_backfill_status() {

	// Another request or CLI process may have advanced the checkpoint.
	wp_cache_delete( '_bbp_mention_backfill', 'options' );

	return get_option( '_bbp_mention_backfill', array() );
}

/**
 * Save progress only if this worker still owns the previous checkpoint.
 *
 * The option's unique name serializes initialization. Comparing its previous
 * value prevents an expired worker from overwriting a replacement worker.
 * Progress is not autoloaded on ordinary front-end requests.
 *
 * @since 2.7.0 bbPress (r8264)
 *
 * @param array $state    New progress and lease.
 * @param array $previous Previously read progress, or empty to initialize.
 * @return bool Whether the checkpoint was saved.
 */
function bbp_save_mention_backfill_status( $state, $previous = array() ) {

	$bbp_db = bbp_db();
	if ( empty( $previous ) ) {
		// add_option() can overwrite another initializer through its upsert.
		$saved = $bbp_db->query(
			$bbp_db->prepare(
				"INSERT IGNORE INTO {$bbp_db->options} (option_name, option_value, autoload) VALUES (%s, %s, %s)",
				'_bbp_mention_backfill',
				maybe_serialize( $state ),
				'no'
			)
		);
		wp_cache_delete( '_bbp_mention_backfill', 'options' );
		wp_cache_delete( 'notoptions', 'options' );

		return ( 1 === $saved );
	}

	$saved = $bbp_db->update(
		$bbp_db->options,
		array( 'option_value' => maybe_serialize( $state ) ),
		array(
			'option_name'  => '_bbp_mention_backfill',
			'option_value' => maybe_serialize( $previous )
		),
		array( '%s' ),
		array( '%s', '%s' )
	);

	wp_cache_delete( '_bbp_mention_backfill', 'options' );

	return ( 1 === $saved );
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
	$post = get_post( $post_id );
	if ( ! $post || ! in_array( $post->post_type, array( bbp_get_topic_post_type(), bbp_get_reply_post_type() ), true ) ) {
		return true;
	}

	$old_ids  = bbp_get_users_for_object( $post_id, '_bbp_mention' );
	$user_ids = bbp_update_post_mentions( $post_id );
	$indexed  = bbp_get_users_for_object( $post_id, '_bbp_mention' );
	if ( array_diff( $user_ids, $indexed ) || array_diff( $indexed, $user_ids ) ) {
		return false;
	}

	$handled = wp_parse_id_list( array_merge( $old_ids, $user_ids ) );
	if ( ! empty( $handled ) ) {
		// Existing unpublished records must retain their approval-time recipients.
		$statuses = ( bbp_get_reply_post_type() === $post->post_type ) ? bbp_get_public_reply_statuses() : bbp_get_public_topic_statuses();
		if ( ! in_array( $post->post_status, $statuses, true ) && metadata_exists( 'post', $post_id, '_bbp_mention_notification' ) ) {
			$handled = array();
		}
		bbp_claim_mention_notification_recipients( $post_id, $handled, $handled );
		wp_cache_delete( $post_id, 'post_meta' );
		$state = get_post_meta( $post_id, '_bbp_mention_notification', true );
		if ( ! is_array( $state ) || ! isset( $state['handled'] ) || ! is_array( $state['handled'] ) || array_diff( $handled, $state['handled'] ) ) {
			return false;
		}
	}

	// An edit during indexing needs another pass through this post.
	wp_cache_delete( $post_id, 'posts' );
	$current = get_post( $post_id );

	return ! $current || ( $post->post_content === $current->post_content );
}

/**
 * Run or resume a bounded batch of historical mention indexing.
 *
 * Scan primary-key ranges, including non-forum posts, so each batch reads at
 * most the requested number of rows regardless of post types or ID gaps.
 * Only topics and replies are indexed. A fixed upper ID keeps a live site's
 * run finite. Each successful post is checkpointed, without scanning users or
 * counting the entire history. A five-minute renewable lease excludes another
 * repair/CLI worker and permits recovery after an interrupted request.
 *
 * @since 2.7.0 bbPress (r8264)
 *
 * @param int  $batch_size Maximum post rows to examine, from 1 to 1000.
 * @param bool $restart    Begin a fresh pass, preserving notification history.
 * @param bool $dry_run    Preview the next batch without saving any changes.
 * @return array|WP_Error Saved progress, a batch preview, or an error.
 */
function bbp_backfill_mentions( $batch_size = 100, $restart = false, $dry_run = false ) {

	$batch_size = (int) $batch_size;
	if ( ( $batch_size < 1 ) || ( $batch_size > 1000 ) ) {
		return new WP_Error( 'bbp_mention_batch_size', __( 'Choose a mention batch size between 1 and 1000.', 'bbpress' ) );
	}

	$bbp_db   = bbp_db();
	$previous = bbp_get_mention_backfill_status();
	if ( ! empty( $previous ) && ( ! is_array( $previous ) || ! isset( $previous['cursor'], $previous['upper'], $previous['processed'], $previous['complete'] ) ) ) {
		return new WP_Error( 'bbp_mention_backfill_state', __( 'The saved mention backfill progress is invalid.', 'bbpress' ) );
	}

	if ( ! empty( $previous['expires'] ) && ( $previous['expires'] > time() ) ) {
		return new WP_Error( 'bbp_mention_backfill_busy', __( 'Another mention backfill batch is running. Try again after it finishes.', 'bbpress' ) );
	}

	$state = $previous;
	if ( $restart || empty( $state ) ) {
		$upper = $bbp_db->get_var( "SELECT MAX(ID) FROM {$bbp_db->posts}" );
		if ( $bbp_db->last_error ) {
			return new WP_Error( 'bbp_mention_backfill_query', __( 'Could not read posts for the mention backfill.', 'bbpress' ) );
		}
		$state = array(
			'cursor'    => 0,
			'upper'     => (int) $upper,
			'processed' => 0,
			'complete'  => false
		);
	} elseif ( $state['complete'] && ! $dry_run ) {
		bbp_remove_pending_upgrade( 'bbp-user-mentions' );
		return $state;
	}

	if ( $dry_run ) {
		return bbp_preview_mention_backfill( $state, $batch_size );
	}

	$state['token']   = wp_generate_uuid4();
	$state['expires'] = time() + ( 5 * MINUTE_IN_SECONDS );
	if ( ! bbp_save_mention_backfill_status( $state, $previous ) ) {
		return new WP_Error( 'bbp_mention_backfill_checkpoint', __( 'Could not save mention backfill progress. Run the tool again to resume.', 'bbpress' ) );
	}

	$error    = false;
	$complete = false;
	$started  = microtime( true );
	try {
		$posts = bbp_get_mention_backfill_posts( $state, $batch_size );
		if ( is_wp_error( $posts ) ) {
			$error = $posts;
		} else {
			foreach ( $posts as $post ) {
				if ( $state['expires'] <= time() ) {
					$error = new WP_Error( 'bbp_mention_backfill_busy', __( 'The mention backfill batch expired. Run the tool again to resume.', 'bbpress' ) );
					break;
				}
				$next = $state;
				if ( in_array( $post->post_type, array( bbp_get_topic_post_type(), bbp_get_reply_post_type() ), true ) ) {
					if ( ! bbp_backfill_post_mentions( (int) $post->ID ) ) {
						/* translators: %d: Post ID */
						$error = new WP_Error( 'bbp_mention_backfill_post', sprintf( __( 'Could not finish indexing mentions in post %d. Run the tool again to retry this post.', 'bbpress' ), $post->ID ) );
						break;
					}
					++$next['processed'];
				}

				$next['cursor']  = (int) $post->ID;
				$next['expires'] = time() + ( 5 * MINUTE_IN_SECONDS );
				if ( ! bbp_save_mention_backfill_status( $next, $state ) ) {
					$error = new WP_Error( 'bbp_mention_backfill_checkpoint', __( 'Could not save mention backfill progress. Run the tool again to resume.', 'bbpress' ) );
					break;
				}
				$state = $next;

				// Leave time for the repair page to render on slower installations.
				if ( ( microtime( true ) - $started ) >= 5 ) {
					break;
				}
			}
		}

		$complete = ! $error && ( empty( $posts ) || ( $state['cursor'] === $state['upper'] ) );
	} finally {
		// Release only our own lease, including after an exception.
		$released = $state;
		if ( $complete ) {
			$released['cursor']   = $state['upper'];
			$released['complete'] = true;
		}
		unset( $released['token'], $released['expires'] );
		$saved = bbp_save_mention_backfill_status( $released, $state );
	}

	if ( $error ) {
		return $error;
	}
	if ( ! $saved ) {
		return new WP_Error( 'bbp_mention_backfill_checkpoint', __( 'Could not save mention backfill progress. Run the tool again to resume.', 'bbpress' ) );
	}

	// All entry points, including WP-CLI, finish the same pending upgrade.
	if ( $released['complete'] ) {
		bbp_remove_pending_upgrade( 'bbp-user-mentions' );
	}

	return $released;
}

/**
 * Read the same bounded post-ID range for Run and Dry Run.
 *
 * @since 2.7.0 bbPress (r8264)
 *
 * @param array $state      Validated backfill progress.
 * @param int   $batch_size Maximum post rows to examine.
 * @return array|WP_Error Post IDs and types, or a query error.
 */
function bbp_get_mention_backfill_posts( $state, $batch_size ) {
	$bbp_db = bbp_db();
	$posts  = $bbp_db->get_results( $bbp_db->prepare( "SELECT ID, post_type FROM {$bbp_db->posts} WHERE ID > %d AND ID <= %d ORDER BY ID ASC LIMIT %d", $state['cursor'], $state['upper'], $batch_size ) );

	if ( $bbp_db->last_error ) {
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
 * @param array $state      Validated backfill progress.
 * @param int   $batch_size Maximum post rows to examine.
 * @return array|WP_Error Batch preview or an error.
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

	$started = microtime( true );
	foreach ( $posts as $row ) {
		$post_id = (int) $row->ID;
		++$preview['scanned'];
		$preview['cursor'] = $post_id;

		if ( in_array( $row->post_type, array( bbp_get_topic_post_type(), bbp_get_reply_post_type() ), true ) ) {
			wp_cache_delete( $post_id, 'posts' );
			wp_cache_delete( $post_id, 'post_meta' );
			$post = get_post( $post_id );
			if ( $post && in_array( $post->post_type, array( bbp_get_topic_post_type(), bbp_get_reply_post_type() ), true ) ) {
				$old_ids = bbp_get_users_for_object( $post_id, '_bbp_mention' );
				$new_ids = bbp_get_mentioned_user_ids( $post->post_content );
				$handled = wp_parse_id_list( array_merge( $old_ids, $new_ids ) );
				++$preview['posts'];
				$preview['added']   += count( array_diff( $new_ids, $old_ids ) );
				$preview['removed'] += count( array_diff( $old_ids, $new_ids ) );

				if ( ! empty( $handled ) ) {
					$exists = metadata_exists( 'post', $post_id, '_bbp_mention_notification' );
					$record = get_post_meta( $post_id, '_bbp_mention_notification', true );
					if ( $exists && ( ! is_array( $record ) || ! isset( $record['handled'] ) || ! is_array( $record['handled'] ) ) ) {
						/* translators: %d: Post ID */
						return new WP_Error( 'bbp_mention_backfill_post', sprintf( __( 'The mention notification record for post %d is invalid.', 'bbpress' ), $post_id ) );
					}
					$statuses = ( bbp_get_reply_post_type() === $post->post_type ) ? bbp_get_public_reply_statuses() : bbp_get_public_topic_statuses();
					if ( ! $exists || ( in_array( $post->post_status, $statuses, true ) && array_diff( $handled, $record['handled'] ) ) ) {
						++$preview['baselines'];
					}
				}
			}
		}

		// Leave time for the repair page to render on slower installations.
		if ( ( microtime( true ) - $started ) >= 5 ) {
			break;
		}
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
