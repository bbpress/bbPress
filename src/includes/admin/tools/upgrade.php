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
 */
function bbp_admin_upgrade_page() {
	bbp_admin_tools_repair_upgrade_page( 'upgrade' );
}

/**
 * Upgrade historical mention relationships for bbPress 2.7 and higher.
 *
 * @since 2.7.0 bbPress (r8264)
 *
 * @return array Upgrade status, feedback, and completion state.
 */
function bbp_admin_upgrade_user_mentions() {
	return bbp_admin_repair_user_mentions( 'upgrade' );
}

/**
 * Resume one mention batch during an authorized small-site version update.
 *
 * Automatic calls must not restart a scan that another worker has completed.
 * The shared automatic runner controls locking and request safety limits.
 *
 * @since 2.7.0 bbPress (r8264)
 *
 * @return array Upgrade status, feedback, and completion state.
 */
function bbp_admin_upgrade_user_mentions_automatically() {
	$size = bbp_admin_repair_tool_batch_size( 'bbp-user-mentions' );

	return bbp_admin_repair_user_mentions_process( $size );
}

/**
 * Preview the complete mention upgrade without changing saved progress.
 *
 * @since 2.7.0 bbPress (r8264)
 *
 * @return array Status and feedback for the tools handler.
 */
function bbp_admin_upgrade_user_mentions_dry_run() {
	return bbp_admin_repair_user_mentions( 'upgrade', true );
}

/**
 * Upgrade user engagements for bbPress 2.6 and higher.
 *
 * @since 2.6.0 bbPress (r6320)
 * @since 2.7.0 bbPress (r8230) Added Dry Run support.
 * @since 2.7.0 bbPress (r8259) Process one resumable batch per call.
 *
 * @return array An array of the status code and the message.
 */
function bbp_admin_upgrade_user_engagements() {
	return bbp_admin_upgrade_user_engagements_process();
}

/**
 * Preview upgrading user engagements for bbPress 2.6 and higher.
 *
 * @since 2.7.0 bbPress (r8230)
 *
 * @return array An array of the status code and the message.
 */
function bbp_admin_upgrade_user_engagements_dry_run() {
	return bbp_admin_upgrade_user_engagements_process( true );
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
 * @return array Batch status, additions, removals, and completion flag.
 */
function bbp_admin_upgrade_user_engagements_batch( $dry_run = false ) {
	$bbp_db  = bbp_db();
	$tool_id = 'bbp-user-topic-engagements-move';
	$batch   = array(
		'code'      => 0,
		'added'     => 0,
		'removed'   => 0,
		'complete'  => false,
		'found'     => false,
		'restarted' => false,
		'batches'   => 0
	);
	list( $read, $state, $batch['restarted'] ) = bbp_admin_repair_tool_load_checkpoint( $tool_id, 'bbp_admin_repair_tool_is_valid_checkpoint', array(), $dry_run );
	if ( ! $read ) {
		$batch['code'] = 6;
		return $batch;
	}

	if ( false === $state ) {
		$upper_id = $bbp_db->get_var( "SELECT meta_id FROM {$bbp_db->postmeta} WHERE meta_key IN ('_bbp_topic_id', '_bbp_engagement') ORDER BY meta_id DESC LIMIT 1" );
		if ( ! empty( $bbp_db->last_error ) ) {
			$batch['code'] = 2;
			return $batch;
		}
		$state = array(
			'cursor'   => 0,
			'upper_id' => (int) $upper_id
		);
		if ( ! bbp_admin_repair_tool_save_checkpoint( $tool_id, $state, false, $dry_run ) ) {
			bbp_add_pending_upgrade( $tool_id );
			$batch['code'] = 6;
			return $batch;
		}
	}

	$size          = bbp_admin_repair_tool_batch_size( $tool_id );
	$preview_state = bbp_admin_repair_tool_restore_preview( $state, $batch, array( 'added', 'removed', 'found', 'batches' ) );
	$planned       = isset( $preview_state['planned'] ) ? $preview_state['planned'] : array();
	do {
		$rows = $bbp_db->get_results( $bbp_db->prepare( "SELECT meta_id, post_id, meta_value, (meta_key = '_bbp_topic_id') AS is_source FROM {$bbp_db->postmeta} WHERE meta_key IN ('_bbp_topic_id', '_bbp_engagement') AND meta_id > %d AND meta_id <= %d ORDER BY meta_id LIMIT %d", $state['cursor'], $state['upper_id'], $size + 1 ) );
		if ( ! empty( $bbp_db->last_error ) ) {
			$batch['code'] = 2;
			return $batch;
		}
		$complete         = count( $rows ) <= $size;
		$rows             = array_slice( $rows, 0, $size );
		$batch['found']   = $batch['found'] || ! empty( $rows );
		$statuses         = array( bbp_get_public_status_id(), bbp_get_closed_status_id() );
		$source           = bbp_admin_repair_get_engagements_query( $statuses, $statuses, true );
		$repair           = bbp_admin_repair_engagement_rows( $rows, $source, $dry_run, $planned );
		$batch['code']    = $repair['code'];
		$batch['added']  += $repair['added'];
		$batch['removed'] += $repair['removed'];
		if ( ! empty( $batch['code'] ) ) {
			return $batch;
		}
		if ( ! empty( $rows ) ) {
			$state['cursor'] = $repair['cursor'];
		}

		++$batch['batches'];
		bbp_admin_repair_tool_store_preview( $state, $batch, array( 'added', 'removed', 'found', 'batches' ), array( 'planned' => $planned ) );
		if ( ! bbp_admin_repair_tool_save_checkpoint( $tool_id, $state, $complete, $dry_run ) ) {
			$batch['code'] = 6;
			return $batch;
		}
		$batch['complete'] = $complete;
	} while ( bbp_admin_repair_tool_continue_preview( $dry_run ) && ! $batch['code'] && ! $batch['complete'] );

	return $batch;
}

/**
 * Upgrade or preview upgrading user engagements.
 *
 * @since 2.7.0 bbPress (r8230)
 *
 * @param bool $dry_run Whether to preview changes without updating data.
 * @return array An array of the status code and the message.
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
	$statement = bbp_admin_repair_tool_restart_statement( $statement, $batch['restarted'], $dry_run );
	if ( ! empty( $batch['code'] ) ) {
		return bbp_admin_repair_tool_batch_result( $batch, $statement );
	}
	if ( ! $batch['found'] && $batch['complete'] ) {
		return array( 1, sprintf( $statement, $no_engagements ) );
	}

	if ( $dry_run ) {
		/* translators: %s: Number of missing engagements that would be added */
		$added = sprintf( _n( '%s missing engagement would be added from topic and reply authors.', '%s missing engagements would be added from topic and reply authors.', $batch['added'], 'bbpress' ), bbp_number_format( $batch['added'] ) );
		/* translators: %s: Number of stale or duplicate engagement rows that would be removed */
		$removed = sprintf( _n( '%s stale or duplicate engagement row would be cleaned up.', '%s stale or duplicate engagement rows would be cleaned up.', $batch['removed'], 'bbpress' ), bbp_number_format( $batch['removed'] ) );
		$result  = $added . "\n" . $removed;
	} else {
		/* translators: %s: Number of missing engagements added */
		$added = sprintf( _n( '%s missing engagement added from topic and reply authors.', '%s missing engagements added from topic and reply authors.', $batch['added'], 'bbpress' ), bbp_number_format( $batch['added'] ) );
		/* translators: %s: Number of stale or duplicate engagement rows removed */
		$removed = sprintf( _n( '%s stale or duplicate engagement row cleaned up.', '%s stale or duplicate engagement rows cleaned up.', $batch['removed'], 'bbpress' ), bbp_number_format( $batch['removed'] ) );
		$result  = bbp_admin_repair_tool_batch_message( $added . "\n" . $removed, $batch['complete'] );
	}

	return array(
		0,
		sprintf( $statement, $result ),
		'complete' => $batch['complete'],
		'batches'  => $batch['batches'],
	);
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
 * @return array An array of the status code and the message.
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
 * @return array An array of the status code and the message.
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
 * @return array An array of the status code and the message.
 */
function bbp_admin_upgrade_group_forum_relationships_process( $dry_run = false ) {
	$worker = new BBP_Admin_Group_Forums( $dry_run );
	$batch  = $worker->run();
	return bbp_admin_upgrade_group_forum_relationships_results( $batch, $dry_run );
}

/**
 * Describe one group-forum upgrade batch, including partial failure progress.
 *
 * @since 2.7.0 bbPress (r8315)
 *
 * @param array $batch   Batch result.
 * @param bool  $dry_run Whether this was a preview.
 * @return array Status, message, success and completion.
 */
function bbp_admin_upgrade_group_forum_relationships_results( $batch, $dry_run = false ) {
	$g_count      = $batch['groups'];
	$f_count      = $batch['forums'];
	$s_count      = $batch['statuses'];
	$update_root  = $batch['root'];
	$rename_root  = $batch['rename'];
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
			sprintf( _n( '%s group would be updated', '%s groups would be updated', $g_count, 'bbpress' ), bbp_number_format( $g_count ) ),
			/* translators: %s: Number of forums that would be updated */
			sprintf( _n( '%s forum would be updated', '%s forums would be updated', $f_count, 'bbpress' ), bbp_number_format( $f_count ) ),
			/* translators: %s: Number of forum statuses that would be synced */
			sprintf( _n( '%s forum status would be synced', '%s forum statuses would be synced', $s_count, 'bbpress' ), bbp_number_format( $s_count ) ),
			/* translators: %s: Number of group root settings that would be updated */
			sprintf( _n( '%s group root setting would be updated', '%s group root settings would be updated', (int) $update_root, 'bbpress' ), bbp_number_format( (int) $update_root ) ),
			/* translators: %s: Number of forums that would be renamed */
			sprintf( _n( '%s forum would be renamed', '%s forums would be renamed', (int) $rename_root, 'bbpress' ), bbp_number_format( (int) $rename_root ) ),
			/* translators: %1$s: Number of legacy roles that would be removed; %2$s: Legacy role IDs */
			sprintf( _n( '%1$s legacy role would be removed (%2$s)', '%1$s legacy roles would be removed (%2$s)', count( $legacy_roles ), 'bbpress' ), bbp_number_format( count( $legacy_roles ) ), $role_list )
		);
		$result    = implode( ".\n", $results ) . '.';
	} else {
		$results = array(
			/* translators: %s: Number of groups updated */
			sprintf( _n( '%s group updated', '%s groups updated', $g_count, 'bbpress' ), bbp_number_format( $g_count ) ),
			/* translators: %s: Number of forums updated */
			sprintf( _n( '%s forum updated', '%s forums updated', $f_count, 'bbpress' ), bbp_number_format( $f_count ) ),
			/* translators: %s: Number of forum statuses synced */
			sprintf( _n( '%s forum status synced', '%s forum statuses synced', $s_count, 'bbpress' ), bbp_number_format( $s_count ) )
		);
		$result  = ( $batch['code'] ? esc_html__( 'Progress:', 'bbpress' ) : ( $batch['complete'] ? esc_html__( 'Complete!', 'bbpress' ) : esc_html__( 'Batch complete!', 'bbpress' ) ) ) . "\n" . implode( ".\n", $results ) . '.';
	}

	$errors = array(
		2  => esc_html__( 'Failed! Converted forums could not be read.', 'bbpress' ),
		3  => esc_html__( 'Failed! BuddyPress group metadata could not be read.', 'bbpress' ),
		4  => esc_html__( 'Failed! BuddyPress groups could not be read.', 'bbpress' ),
		5  => esc_html__( 'Failed! BuddyPress group metadata could not be updated.', 'bbpress' ),
		6  => esc_html__( 'Failed! The group forums root could not be renamed.', 'bbpress' ),
		7  => $batch['mapping_error'] ? esc_html__( 'Failed! Ambiguous converted forum ID mappings were found.', 'bbpress' ) : esc_html__( 'Failed! Ambiguous BuddyPress group-forum relationships were found.', 'bbpress' ),
		8  => esc_html__( 'Failed! Forum group metadata could not be updated.', 'bbpress' ),
		9  => esc_html__( 'Failed! Forum visibility could not be updated.', 'bbpress' ),
		10 => esc_html__( 'Failed! The group forums root setting could not be updated.', 'bbpress' ),
		11 => esc_html__( 'Failed! Upgrade progress could not be saved or read.', 'bbpress' ),
		13 => esc_html__( 'Failed! Legacy forum roles could not be removed.', 'bbpress' ),
		12 => esc_html__( 'Failed! Group-forum mappings or saved progress could not be verified. Check the mappings and run the upgrade again.', 'bbpress' ),
	);
	if ( $batch['code'] ) {
		$result = $errors[ $batch['code'] ] . ( $dry_run ? '' : "\n" . $result );
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
	);
}

/**
 * Check the stored fields of a legacy user-meta checkpoint.
 *
 * @since 2.7.0 bbPress (r8254)
 *
 * @param mixed $state Saved checkpoint.
 * @return bool
 */
function bbp_admin_upgrade_user_meta_is_valid_checkpoint( $state ) {
	if ( ! bbp_admin_repair_tool_is_valid_checkpoint( $state, array( 'offset' ) ) ) {
		return false;
	}

	if ( ! isset( $state['row_hash'] ) || ! is_string( $state['row_hash'] ) ) {
		return false;
	}

	if ( 0 === $state['offset'] ) {
		return '' === $state['row_hash'];
	}

	if ( 0 === $state['cursor'] ) {
		return false;
	}

	return 1 === preg_match( '/^[a-f0-9]{32}$/D', $state['row_hash'] );
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
 *
 * @param string $tool_id Repair or upgrade tool ID.
 * @param string $old_key Legacy user-meta key.
 * @param string $new_key Destination post-meta key.
 * @param bool   $dry_run Whether to preview without writes.
 * @return array Batch status, additions, failures, and completion flag.
 */
function bbp_admin_upgrade_user_meta_batch( $tool_id, $old_key, $new_key, $dry_run = false ) {
	$bbp_db = bbp_db();
	$batch  = array(
		'code'      => 0,
		'added'     => 0,
		'failed'    => 0,
		'complete'  => false,
		'found'     => false,
		'restarted' => false,
		'batches'   => 0
	);
	list( $read, $state ) = bbp_admin_repair_tool_get_checkpoint( $tool_id, $dry_run );
	if ( ! $read ) {
		$batch['code'] = 5;
		return $batch;
	}

	$valid = ( false === $state ) || bbp_admin_upgrade_user_meta_is_valid_checkpoint( $state );

	// Changed or deleted rows are normal; an impossible offset in an unchanged row is not
	if ( $valid && ! empty( $state['offset'] ) ) {
		$meta = $bbp_db->get_row( $bbp_db->prepare( "SELECT user_id, meta_value FROM {$bbp_db->usermeta} WHERE umeta_id = %d AND meta_key = %s LIMIT 1", $state['cursor'], $old_key ) );
		if ( ! empty( $bbp_db->last_error ) ) {
			$batch['code'] = 2;
			return $batch;
		}
		if ( ! empty( $meta ) && ( md5( (int) $meta->user_id . ':' . $meta->meta_value ) === $state['row_hash'] ) ) {
			$valid = $state['offset'] < count( array_filter( wp_parse_id_list( $meta->meta_value ) ) );
		}
	}

	if ( ! $valid ) {
		$state              = false;
		$batch['restarted'] = true;
	}

	if ( false === $state ) {
		$upper_id = $bbp_db->get_var( $bbp_db->prepare( "SELECT umeta_id FROM {$bbp_db->usermeta} WHERE meta_key = %s ORDER BY umeta_id DESC LIMIT 1", $old_key ) );
		if ( ! empty( $bbp_db->last_error ) ) {
			$batch['code'] = 2;
			return $batch;
		}

		$state = array(
			'cursor'   => 0,
			'offset'   => 0,
			'upper_id' => (int) $upper_id,
			'row_hash' => ''
		);

		// Preserve the source even if the first batch fails or is interrupted
		if ( ! bbp_admin_repair_tool_save_checkpoint( $tool_id, $state, false, $dry_run ) ) {
			bbp_add_pending_upgrade( $tool_id );
			$batch['code'] = 5;
			return $batch;
		}
	}

	$size          = bbp_admin_repair_tool_batch_size( $tool_id );
	$preview_state = bbp_admin_repair_tool_restore_preview( $state, $batch, array( 'added', 'failed', 'found', 'batches' ) );
	$planned       = isset( $preview_state['planned'] ) ? $preview_state['planned'] : array();
	do {
		$work    = 0;

		while ( ( $work < $size ) && ( $state['cursor'] <= $state['upper_id'] ) ) {
			$meta = $bbp_db->get_row(
				$bbp_db->prepare(
					"SELECT umeta_id, user_id, meta_value FROM {$bbp_db->usermeta}
					WHERE meta_key = %s AND umeta_id >= %d AND umeta_id <= %d
					ORDER BY umeta_id LIMIT 1",
					$old_key,
					$state['cursor'],
					$state['upper_id']
				)
			);
			if ( ! empty( $bbp_db->last_error ) ) {
				$batch['code'] = 2;
				return $batch;
			}
			if ( empty( $meta ) ) {
				break;
			}
			$batch['found'] = true;

			$row_id   = (int) $meta->umeta_id;
			$user_id  = (int) $meta->user_id;
			$row_hash = md5( $user_id . ':' . $meta->meta_value );
			$post_ids = array_values( array_filter( wp_parse_id_list( $meta->meta_value ) ) );
			$offset   = ( $row_id === (int) $state['cursor'] ) && ( $row_hash === $state['row_hash'] )
				? (int) $state['offset']
				: 0;

			// Empty lists still consume work, keeping every source scan bounded
			if ( empty( $post_ids ) || ( $offset >= count( $post_ids ) ) ) {
				++$work;
				$state['cursor']   = $row_id + 1;
				$state['offset']   = 0;
				$state['row_hash'] = '';
				continue;
			}

			foreach ( array_slice( $post_ids, $offset, $size - $work ) as $post_id ) {
				++$work;
				++$offset;
				if ( isset( $planned[ $user_id ][ $post_id ] ) ) {
					continue;
				}
				$planned[ $user_id ][ $post_id ] = true;

				$exists = $bbp_db->get_var( $bbp_db->prepare( "SELECT meta_id FROM {$bbp_db->postmeta} WHERE post_id = %d AND meta_key = %s AND meta_value = %d LIMIT 1", $post_id, $new_key, $user_id ) );
				if ( ! empty( $bbp_db->last_error ) ) {
					$batch['code'] = 3;
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

			if ( $offset < count( $post_ids ) ) {
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
			$batch['code'] = 4;
			return $batch;
		}

		$remaining = $bbp_db->get_var( $bbp_db->prepare( "SELECT umeta_id FROM {$bbp_db->usermeta} WHERE meta_key = %s AND umeta_id >= %d AND umeta_id <= %d ORDER BY umeta_id LIMIT 1", $old_key, $state['cursor'], $state['upper_id'] ) );
		if ( ! empty( $bbp_db->last_error ) ) {
			$batch['code'] = 2;
			return $batch;
		}
		$batch['complete'] = empty( $remaining );

		$batch['batches'] += max( 1, (int) ceil( $work / $size ) );
		bbp_admin_repair_tool_store_preview( $state, $batch, array( 'added', 'failed', 'found', 'batches' ), array( 'planned' => $planned ) );
		if ( ! bbp_admin_repair_tool_save_checkpoint( $tool_id, $state, $batch['complete'], $dry_run ) ) {
			$batch['code']     = 5;
			$batch['complete'] = false;
		}
	} while ( bbp_admin_repair_tool_continue_preview( $dry_run ) && ! $batch['code'] && ! $batch['complete'] );

	return $batch;
}

/**
 * Present the three legacy user-meta migrations through their shared worker.
 *
 * Keep literal plural strings together so translation extraction remains native.
 *
 * @since 2.7.0 bbPress (r8315)
 * @access private
 *
 * @param string $type    Favorites, topic-subscriptions, or forum-subscriptions.
 * @param bool   $dry_run Whether to preview without writes.
 * @return array Tool status, impact text and completion.
 */
function bbp_admin_upgrade_user_meta_process( $type, $dry_run = false ) {
	$config = bbp_admin_get_user_relationship_config( $type );
	if ( false === $config ) {
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
	$total        = $batch['added'];
	$failed_total = $batch['failed'];

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
	if ( $batch['code'] && 4 !== $batch['code'] ) {
		return array(
			$batch['code'],
			sprintf( $statement, esc_html__( 'Failed!', 'bbpress' ) ),
			'success'  => false,
			'complete' => false,
		);
	}
	if ( $batch['complete'] && ( ! $batch['found'] || ( $dry_run && ! $total ) ) ) {
		return array( 1, sprintf( $statement, $none ) );
	}

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
			4,
			sprintf( $statement, esc_html__( 'Failed!', 'bbpress' ) . "\n" . $added . ".\n" . $failures . '.' ),
			'success'  => false,
			'complete' => false,
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
	);
}

/**
 * Upgrade user favorites for bbPress 2.6 and higher.
 *
 * @since 2.6.0 bbPress (r6176)
 * @since 2.7.0 bbPress (r8231) Added Dry Run support.
 * @since 2.7.0 bbPress (r8253) Process one resumable batch per call.
 *
 * @return array An array of the status code and the message.
 */
function bbp_admin_upgrade_user_favorites() {
	return bbp_admin_upgrade_user_favorites_process();
}

/**
 * Preview upgrading user favorites for bbPress 2.6 and higher.
 *
 * @since 2.7.0 bbPress (r8231)
 *
 * @return array An array of the status code and the message.
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
 * @return array An array of the status code and the message.
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
 * @return array An array of the status code and the message.
 */
function bbp_admin_upgrade_user_topic_subscriptions() {
	return bbp_admin_upgrade_user_topic_subscriptions_process();
}

/**
 * Preview upgrading user topic subscriptions for bbPress 2.6 and higher.
 *
 * @since 2.7.0 bbPress (r8232)
 *
 * @return array An array of the status code and the message.
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
 * @return array An array of the status code and the message.
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
 * @return array An array of the status code and the message.
 */
function bbp_admin_upgrade_user_forum_subscriptions() {
	return bbp_admin_upgrade_user_forum_subscriptions_process();
}

/**
 * Preview upgrading user forum subscriptions for bbPress 2.6 and higher.
 *
 * @since 2.7.0 bbPress (r8233)
 *
 * @return array An array of the status code and the message.
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
 * @return array An array of the status code and the message.
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
 * Remove one bounded batch of migrated legacy user-meta rows.
 *
 * Capture a fixed upper metadata ID, delete individual rows through WordPress,
 * and verify each deletion before saving progress. Replaying a batch after a
 * failed checkpoint only revisits surviving rows. Later rows wait for a new run.
 *
 * @since 2.7.0 bbPress (r8286)
 *
 * @param string $type    Favorites, topic-subscriptions, or forum-subscriptions.
 * @param bool   $dry_run Whether to preview without writes.
 * @return array Status code, removed rows, and progress flags.
 */
function bbp_admin_upgrade_remove_user_meta_batch( $type = 'favorites', $dry_run = false ) {
	$db     = bbp_db();
	$config = bbp_admin_get_user_relationship_config( $type );
	$batch  = array(
		'code'      => 0,
		'removed'   => 0,
		'initial'   => false,
		'complete'  => false,
		'restarted' => false,
		'message'   => '',
		'batches'   => 0
	);
	if ( false === $config ) {
		$batch['code'] = 6;
		return $batch;
	}
	$tool_id  = $config['delete_tool_id'];
	$key      = $config['source_key'];
	$callback = static function () use ( $db, $tool_id, $key, $dry_run, $batch ) {
		list( $read, $state, $batch['restarted'] ) = bbp_admin_repair_tool_load_checkpoint( $tool_id, 'bbp_admin_repair_tool_is_matching_checkpoint', array( 'key' => $key ), $dry_run );
		if ( ! $read ) {
			$batch['code'] = 6;
			return $batch;
		}
		$batch['initial'] = false === $state;
		if ( $batch['initial'] ) {
			$upper = (int) $db->get_var( $db->prepare( "SELECT MAX(umeta_id) FROM {$db->usermeta} WHERE meta_key = %s", $key ) );
			if ( ! empty( $db->last_error ) ) {
				$batch['code'] = 2;
				return $batch;
			}
			$state = array(
				'cursor'   => 0,
				'upper_id' => $upper,
				'key'      => $key
			);
			if ( ! bbp_admin_repair_tool_save_checkpoint( $tool_id, $state, false, $dry_run ) ) {
				$batch['code'] = 6;
				return $batch;
			}
		}
		$size = bbp_admin_repair_tool_batch_size( $tool_id );
		bbp_admin_repair_tool_restore_preview( $state, $batch, array( 'removed', 'initial', 'batches' ) );
		do {
			$rows = $db->get_results( $db->prepare( "SELECT umeta_id, user_id FROM {$db->usermeta} WHERE meta_key = %s AND umeta_id > %d AND umeta_id <= %d ORDER BY umeta_id LIMIT %d", $key, $state['cursor'], $state['upper_id'], $size + 1 ) );
			if ( ! empty( $db->last_error ) ) {
				$batch['code'] = 2;
				return $batch;
			}
			$complete = count( $rows ) <= $size;
			foreach ( array_slice( $rows, 0, $size ) as $row ) {
				if ( ! $dry_run ) {
					$code = bbp_admin_repair_metadata_row( 'user', $row, $key, null );
					if ( $code ) {
						$batch['code'] = ( 1 === $code ) ? 2 : 3;
						return $batch;
					}
				}
				++$batch['removed'];
				$state['cursor'] = (int) $row->umeta_id;
			}
			++$batch['batches'];
			bbp_admin_repair_tool_store_preview( $state, $batch, array( 'removed', 'initial', 'batches' ) );
			if ( ! bbp_admin_repair_tool_save_checkpoint( $tool_id, $state, $complete, $dry_run ) ) {
				$batch['code'] = 6;
				return $batch;
			}
			$batch['complete'] = $complete;
		} while ( bbp_admin_repair_tool_continue_preview( $dry_run ) && ! $batch['complete'] );

		return $batch;
	};
	$result = bbp_admin_upgrade_user_meta_cleanup( $config['move_tool_id'], $callback, $dry_run );
	if ( is_wp_error( $result ) ) {
		$batch['code']    = 4;
		$batch['message'] = $result->get_error_message();
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
 * @return array Status code, feedback, and completion flag.
 */
function bbp_admin_upgrade_remove_user_meta_process( $type = 'favorites', $dry_run = false ) {
	$batch = bbp_admin_upgrade_remove_user_meta_batch( $type, $dry_run );
	if ( ! empty( $batch['code'] ) ) {
		if ( ! empty( $batch['message'] ) ) {
			$result = $batch['message'];
		} elseif ( 6 === $batch['code'] ) {
			$result = esc_html__( 'Failed! Progress could not be saved. Run the tool again to retry.', 'bbpress' );
		} else {
			$result = esc_html__( 'Failed!', 'bbpress' );
		}
		return array(
			$batch['code'],
			$result,
			'success'  => false,
			'complete' => false
		);
	}

	$total = $batch['removed'];
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
 * @return array Status code, feedback, and completion flag.
 */
function bbp_admin_upgrade_remove_favorites_from_usermeta() {
	return bbp_admin_upgrade_remove_favorites_from_usermeta_process();
}

/**
 * Preview removing favorites data from user meta for bbPress 2.6 and higher.
 *
 * @since 2.7.0 bbPress (r8234)
 *
 * @return array An array of the status code and the message.
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
 * @return array Status code, feedback, and completion flag.
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
 * @return array Status code, feedback, and completion flag.
 */
function bbp_admin_upgrade_remove_topic_subscriptions_from_usermeta() {
	return bbp_admin_upgrade_remove_topic_subscriptions_from_usermeta_process();
}

/**
 * Preview removing topic subscriptions data from user meta for bbPress 2.6 and higher.
 *
 * @since 2.7.0 bbPress (r8235)
 *
 * @return array An array of the status code and the message.
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
 * @return array Status code, feedback, and completion flag.
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
 * @return array Status code, feedback, and completion flag.
 */
function bbp_admin_upgrade_remove_forum_subscriptions_from_usermeta() {
	return bbp_admin_upgrade_remove_forum_subscriptions_from_usermeta_process();
}

/**
 * Preview removing forum subscriptions data from user meta for bbPress 2.6 and higher.
 *
 * @since 2.7.0 bbPress (r8236)
 *
 * @return array An array of the status code and the message.
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
 * @return array Status code, feedback, and completion flag.
 */
function bbp_admin_upgrade_remove_forum_subscriptions_from_usermeta_process( $dry_run = false ) {
	return bbp_admin_upgrade_remove_user_meta_process( 'forum-subscriptions', $dry_run );
}
