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
 * Run or preview one resumable batch of historical mention indexing.
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
	$restart  = ! empty( $previous['complete'] ) && ! wp_doing_ajax() && empty( $_GET['bbp-tools-run'] );
	$tool_id  = ( 'upgrade' === $type ) ? 'bbp-user-mentions' : 'bbp-mentions';
	$size     = bbp_admin_repair_tool_batch_size( $tool_id );

	return bbp_admin_repair_user_mentions_process( $size, $restart, $dry_run );
}

/**
 * Process one mention batch for the interactive tools or automatic updater.
 *
 * Callers control access and whether a completed scan should restart.
 *
 * @since 2.7.0 bbPress (r8264)
 *
 * @param int  $batch_size Maximum post rows to examine.
 * @param bool $restart    Start a new scan instead of resuming saved progress.
 * @param bool $dry_run    Preview changes without saving them.
 * @return array Status, feedback, and a complete flag for the tools handler.
 */
function bbp_admin_repair_user_mentions_process( $batch_size = 100, $restart = false, $dry_run = false ) {
	$state = bbp_backfill_mentions( $batch_size, $restart, $dry_run );
	if ( is_wp_error( $state ) ) {
		return array(
			1,
			$state,
			'success'  => false,
			'complete' => false
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
		$progress = $state['more']
			? __( 'More posts remain. Run continues automatically until finished. Keep this page open while it runs.', 'bbpress' )
			: __( 'This preview reaches the end of the scan.', 'bbpress' );
		if ( ! $state['scanned'] ) {
			$progress = __( 'No posts remain to scan.', 'bbpress' );
		}

		return array(
			0,
			esc_html( $message . "\n" . $history . "\n" . $progress ),
			'complete' => false
		);
	}

	return array(
		0,
		esc_html( bbp_admin_mention_backfill_message( $state ) ),
		'complete' => (bool) $state['complete']
	);
}

/**
 * Preview the next mention repair batch without changing saved progress.
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
 * Reconcile one stored count with bounded metadata writes.
 *
 * Keep an existing canonical row, or add it before cleaning up other rows.
 * A null count removes all matching rows. Each inspected object costs at least
 * one work item, even when its stored count is already correct.
 *
 * @since 2.7.0 bbPress (r8265)
 *
 * @param string   $meta_type Metadata type: 'post' or 'user'.
 * @param int      $object_id Object ID.
 * @param string   $key       Count metadata key.
 * @param int|null $count     Expected count, or null when no row should remain.
 * @param int      $limit     Maximum metadata writes.
 * @param bool     $dry_run   Whether to preview without writes.
 * @return array Status code, work used, changed flag, and completion flag.
 */
function bbp_admin_repair_count_metadata( $meta_type, $object_id, $key, $count, $limit, $dry_run = false ) {
	return bbp_admin_repair_single_metadata( $meta_type, $object_id, $key, $count, $limit, $dry_run );
}

/**
 * Reconcile one integer metadata value with bounded writes.
 *
 * Preserve a canonical row, or add it before cleaning up other rows. A null
 * value removes all matching rows. Counts and object references share this
 * cleanup, and each inspected object costs at least one work item.
 *
 * @since 2.7.0 bbPress (r8277)
 *
 * @param string   $meta_type Metadata type: 'post' or 'user'.
 * @param int      $object_id Object ID.
 * @param string   $key       Metadata key.
 * @param int|null $value     Expected value, or null when no row should remain.
 * @param int      $limit     Maximum metadata writes.
 * @param bool     $dry_run   Whether to preview without writes.
 * @return array Status code, work used, changed flag, and completion flag.
 */
function bbp_admin_repair_single_metadata( $meta_type, $object_id, $key, $value, $limit, $dry_run = false ) {
	$bbp_db     = bbp_db();
	$table      = $bbp_db->{$meta_type . 'meta'};
	$id_column  = ( 'user' === $meta_type ) ? 'umeta_id' : 'meta_id';
	$object_key = $meta_type . '_id';
	$result     = array(
		'code'     => 0,
		'work'     => 0,
		'changed'  => false,
		'complete' => false
	);
	$keep_id = 0;
	if ( null !== $value ) {
		$keep_id = (int) $bbp_db->get_var( $bbp_db->prepare( "SELECT {$id_column} FROM {$table} WHERE {$object_key} = %d AND BINARY meta_key = %s AND BINARY meta_value = %s ORDER BY {$id_column} LIMIT 1", $object_id, $key, (string) $value ) );
		if ( ! empty( $bbp_db->last_error ) ) {
			$result['code'] = 2;
			return $result;
		}
	}

	if ( ! $dry_run ) {
		wp_cache_delete( $object_id, $meta_type . '_meta' );
	}
	if ( ( null !== $value ) && empty( $keep_id ) ) {
		if ( ! $dry_run ) {
			$keep_id = add_metadata( $meta_type, $object_id, $key, $value, false );
			if ( false === $keep_id ) {
				$result['code'] = 4;
				return $result;
			}
		}
		++$result['work'];
		$result['changed'] = true;
	}

	$remaining = $limit - $result['work'];
	$extra_ids = $bbp_db->get_col( $bbp_db->prepare( "SELECT {$id_column} FROM {$table} WHERE {$object_key} = %d AND meta_key = %s AND {$id_column} <> %d ORDER BY {$id_column} LIMIT %d", $object_id, $key, $keep_id, $remaining + 1 ) );
	if ( ! empty( $bbp_db->last_error ) ) {
		$result['code'] = 2;
		return $result;
	}
	foreach ( array_slice( $extra_ids, 0, $remaining ) as $meta_id ) {
		if ( ! $dry_run && ! delete_metadata_by_mid( $meta_type, $meta_id ) ) {
			$result['code'] = 3;
			return $result;
		}
		++$result['work'];
		$result['changed'] = true;
	}
	$result['work']     = max( 1, $result['work'] );
	$result['complete'] = count( $extra_ids ) <= $remaining;

	return $result;
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
 * Clear cached metadata for repaired bbPress posts in bounded batches.
 *
 * @since 2.7.0 bbPress (r8239)
 *
 * @param array  $post_types Post type names to clear.
 * @param string $meta_key   Optional metadata key whose existing posts should also be cleared.
 * @return bool True on success, false on a database error.
 */
function bbp_admin_repair_clean_post_meta_cache_batched( $post_types = array(), $meta_key = '' ) {
	$bbp_db     = bbp_db();
	$post_types = array_values( array_unique( array_filter( (array) $post_types ) ) );

	if ( empty( $post_types ) ) {
		return true;
	}

	$placeholders = implode( ', ', array_fill( 0, count( $post_types ), '%s' ) );
	$last_post_id = 0;

	do {
		$query_args = array_merge( $post_types, array( $last_post_id ) );
		$post_ids   = $bbp_db->get_col(
			$bbp_db->prepare(
				"SELECT `ID` FROM `{$bbp_db->posts}`
				WHERE `post_type` IN ( {$placeholders} )
					AND `ID` > %d
				ORDER BY `ID`
				LIMIT 1000",
				$query_args
			)
		);

		if ( ! empty( $bbp_db->last_error ) ) {
			return false;
		}

		$post_ids   = wp_parse_id_list( $post_ids );
		$post_count = count( $post_ids );
		if ( ! empty( $post_ids ) ) {
			wp_cache_delete_multiple( $post_ids, 'post_meta' );
			$last_post_id = (int) end( $post_ids );
		}
	} while ( 1000 === $post_count );

	if ( empty( $meta_key ) ) {
		return true;
	}

	$last_post_id = 0;

	do {
		$post_ids   = $bbp_db->get_col(
			$bbp_db->prepare(
				"SELECT DISTINCT `post_id` FROM `{$bbp_db->postmeta}`
				WHERE `meta_key` = %s
					AND `post_id` > %d
				ORDER BY `post_id`
				LIMIT 1000",
				$meta_key,
				$last_post_id
			)
		);

		if ( ! empty( $bbp_db->last_error ) ) {
			return false;
		}

		$post_ids   = wp_parse_id_list( $post_ids );
		$post_count = count( $post_ids );
		if ( ! empty( $post_ids ) ) {
			wp_cache_delete_multiple( $post_ids, 'post_meta' );
			$last_post_id = (int) end( $post_ids );
		}
	} while ( 1000 === $post_count );

	return true;
}

/**
 * Delete orphaned freshness metadata in bounded batches.
 *
 * Cache entries are cleared before and after each delete so another request
 * cannot leave deleted metadata in a persistent object cache.
 *
 * @since 2.7.0 bbPress (r8241)
 *
 * @param array $post_types Post types whose freshness metadata is valid.
 * @param array $meta_keys  Freshness metadata keys to delete.
 * @return int Zero on success, 1 on a delete error, or 14 on a read error.
 */
function bbp_admin_repair_delete_orphaned_freshness_batched( $post_types = array(), $meta_keys = array() ) {
	$bbp_db     = bbp_db();
	$post_types = array_values( array_unique( array_filter( (array) $post_types ) ) );
	$meta_keys  = array_values( array_unique( array_filter( (array) $meta_keys ) ) );

	if ( empty( $post_types ) || empty( $meta_keys ) ) {
		return 0;
	}

	$post_type_placeholders = implode( ', ', array_fill( 0, count( $post_types ), '%s' ) );
	$meta_key_placeholders  = implode( ', ', array_fill( 0, count( $meta_keys ), '%s' ) );
	$last_post_id           = -1;

	do {
		$query_args = array_merge( $post_types, $meta_keys, array( $last_post_id ) );
		$post_ids   = $bbp_db->get_col(
			$bbp_db->prepare(
				"SELECT DISTINCT `freshness`.`post_id`
				FROM `{$bbp_db->postmeta}` AS `freshness`
				LEFT JOIN `{$bbp_db->posts}` AS `content`
					ON `freshness`.`post_id` = `content`.`ID`
					AND `content`.`post_type` IN ( {$post_type_placeholders} )
				WHERE `freshness`.`meta_key` IN ( {$meta_key_placeholders} )
					AND `content`.`ID` IS NULL
					AND `freshness`.`post_id` > %d
				ORDER BY `freshness`.`post_id`
				LIMIT 1000",
				$query_args
			)
		);

		if ( ! empty( $bbp_db->last_error ) ) {
			return 14;
		}

		$post_ids   = array_values( array_unique( array_map( 'intval', $post_ids ) ) );
		$post_count = count( $post_ids );

		if ( ! empty( $post_ids ) ) {
			wp_cache_delete_multiple( $post_ids, 'post_meta' );

			$post_id_placeholders = implode( ', ', array_fill( 0, $post_count, '%d' ) );
			$delete_args          = array_merge( $post_ids, $meta_keys );
			$deleted              = $bbp_db->query(
				$bbp_db->prepare(
					"DELETE FROM `{$bbp_db->postmeta}`
					WHERE `post_id` IN ( {$post_id_placeholders} )
						AND `meta_key` IN ( {$meta_key_placeholders} )",
					$delete_args
				)
			);

			wp_cache_delete_multiple( $post_ids, 'post_meta' );

			if ( false === $deleted ) {
				return 1;
			}

			$last_post_id = (int) end( $post_ids );
		}
	} while ( 1000 === $post_count );

	return 0;
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

	// Get the registered repair tools
	$tools = bbp_admin_repair_list();

	// Orderby
	$orderby = ! empty( $_GET['orderby'] )
		? sanitize_key( $_GET['orderby'] )
		: 'priority';

	// Order
	$order = ! empty( $_GET['order'] ) && in_array( strtolower( $_GET['order'] ), array( 'asc', 'desc' ), true )
		? strtolower( $_GET['order'] )
		: 'asc';

	// New order
	$new_order = ( 'desc' === $order )
		? 'asc'
		: 'desc'; ?>

	<div class="wrap">
		<h1 class="wp-heading-inline"><?php esc_html_e( 'Forum Tools', 'bbpress' ); ?></h1>
		<hr class="wp-header-end">
		<h2 class="nav-tab-wrapper"><?php bbp_tools_admin_tabs( 'bbp-repair' ); ?></h2>

		<p><?php esc_html_e( 'bbPress keeps track of relationships between forums, topics, replies, topic-tags, favorites, subscriptions, and users. Occasionally these relationships become out of sync, most often after an import or migration. Use the tools below to manually recalculate these relationships.', 'bbpress' ); ?></p>
		<p class="description"><?php esc_html_e( 'Some of these tools create substantial database overhead. Use caution when running more than 1 repair at a time.', 'bbpress' ); ?></p>

		<?php bbp_admin_repair_tool_status_filters(); ?>

		<form class="settings" method="get" action="">

			<?php bbp_admin_repair_list_search_form(); ?>

			<input type="hidden" name="page" value="bbp-repair" />
			<?php wp_nonce_field( 'bbpress-do-counts' ); ?>

			<div class="tablenav top">
				<div class="alignleft actions bulkactions">
					<label for="bulk-action-selector-top" class="screen-reader-text"><?php esc_html_e( 'Select bulk action', 'bbpress' ); ?></label>
					<select name="action" id="bulk-action-selector-top">
						<option value="" selected="selected"><?php esc_html_e( 'Bulk Actions', 'bbpress' ); ?></option>
						<?php bbp_admin_repair_tool_bulk_actions( $tools ); ?>
					</select>
					<input type="submit" name="doaction" id="doaction" class="button action" value="<?php esc_attr_e( 'Apply', 'bbpress' ); ?>">
				</div>
				<div class="alignleft actions">

					<?php bbp_admin_repair_list_components_filter(); ?>

					<input type="submit" name="filter_action" id="components-submit" class="button" value="<?php esc_html_e( 'Filter', 'bbpress' ); ?>">
				</div>
				<br class="clear">
			</div>
			<table class="wp-list-table widefat striped posts">
				<thead>
					<tr>
						<td id="cb" class="manage-column column-cb check-column">
							<label class="screen-reader-text" for="cb-select-all-1">
								<?php esc_html_e( 'Select All', 'bbpress' ); ?>
							</label>
							<input id="cb-select-all-1" type="checkbox">
						</td>
						<th scope="col" id="description" class="manage-column column-primary column-description sortable <?php echo ( 'priority' === $orderby ) ? esc_attr( $order ) : 'asc'; ?>">
							<a href="<?php

								echo esc_url(
									bbp_get_admin_repair_tool_page_url(
										array(
											'orderby' => 'priority',
											'order'   => $new_order
										)
									)
								);

								?>">
								<span>
									<?php esc_html_e( 'Description', 'bbpress' ); ?>
								</span>
								<span class="sorting-indicator"></span>
							</a>
						</th>
						<th scope="col" id="bbp-tool-components" class="manage-column column-components">
							<span><?php esc_html_e( 'Components', 'bbpress' ); ?></span>
						</th>
						<th scope="col" id="overhead" class="manage-column column-overhead sortable <?php echo ( 'overhead' === $orderby ) ? esc_attr( $order ) : 'asc'; ?>">
							<a href="<?php

								echo esc_url(
									bbp_get_admin_repair_tool_page_url(
										array(
											'orderby' => 'overhead',
											'order'   => $new_order
										)
									)
								);

								?>">
								<span>
									<?php esc_html_e( 'Overhead', 'bbpress' ); ?>
								</span>
								<span class="sorting-indicator"></span>
							</a>
						</th>
					</tr>
				</thead>

				<tbody id="the-list">

					<?php if ( ! empty( $tools ) ) : ?>

						<?php foreach ( $tools as $item ) :

							$item_html_id = str_replace( '_', '-', $item['id'] );
							$select_id    = 'cb-select-' . $item_html_id;

							?>

							<tr id="bbp-repair-tool-<?php echo esc_attr( $item_html_id ); ?>" class="bbp-repair-tools inactive">
								<th scope="row" class="check-column">
									<label class="screen-reader-text" for="<?php echo esc_attr( $select_id ); ?>">
										<?php
										printf(
											/* translators: %s: Repair tool title */
											esc_html__( 'Select %s', 'bbpress' ),
											esc_html( $item['title'] )
										);
										?>
									</label>
									<input type="checkbox" name="checked[]" value="<?php echo esc_attr( $item['id'] ); ?>" id="<?php echo esc_attr( $select_id ); ?>">
								</th>
								<td class="bbp-tool-title column-primary column-description"
									data-colname="<?php esc_html_e( 'Description', 'bbpress' ); ?>">
									<strong><?php echo esc_html( $item['title'] ); ?></strong>

									<?php if ( ! empty( $item['description'] ) ) : ?>
										<p class="description"><?php echo esc_html( $item['description'] ); ?></p>
									<?php endif; ?>

									<div class="row-actions hide-if-no-js">
										<?php bbp_admin_repair_tool_row_actions( $item ); ?>
									</div>

									<button type="button" class="toggle-row">
										<span class="screen-reader-text">
											<?php esc_html_e( 'Show more details', 'bbpress' ); ?>
										</span>
									</button>
								</td>
								<td class="column-components desc" data-colname="<?php esc_html_e( 'Components', 'bbpress' ); ?>">
									<div class="bbp-tool-overhead">

										<?php echo implode( ', ', bbp_get_admin_repair_tool_components( $item ) ); ?>

									</div>
								</td>
								<td class="column-overhead desc" data-colname="<?php esc_html_e( 'Overhead', 'bbpress' ); ?>">
									<div class="bbp-tool-overhead">

										<?php echo implode( ', ', bbp_get_admin_repair_tool_overhead( $item ) ); ?>

									</div>
								</td>
							</tr>

						<?php endforeach; ?>

					<?php else : ?>

						<tr>
							<td colspan="4">
								<?php esc_html_e( 'No repair tools match this criteria.', 'bbpress' ); ?>
							</td>
						</tr>

					<?php endif; ?>

				</tbody>
				<tfoot>
					<tr>
						<td class="manage-column column-cb check-column">
							<label class="screen-reader-text" for="cb-select-all-2">
								<?php esc_html_e( 'Select All', 'bbpress' ); ?>
							</label>
							<input id="cb-select-all-2" type="checkbox">
						</td>
						<th scope="col" class="manage-column column-primary column-description"><?php esc_html_e( 'Description', 'bbpress' ); ?></th>
						<th scope="col" class="manage-column column-components"><?php esc_html_e( 'Components', 'bbpress' ); ?></th>
						<th scope="col" class="manage-column column-overhead"><?php esc_html_e( 'Overhead', 'bbpress' ); ?></th>
					</tr>
				</tfoot>
			</table>
			<div class="tablenav bottom">
				<div class="alignleft actions bulkactions">
					<label for="bulk-action-selector-bottom" class="screen-reader-text"><?php esc_html_e( 'Select bulk action', 'bbpress' ); ?></label>
					<select name="action2" id="bulk-action-selector-bottom">
						<option value="" selected="selected"><?php esc_html_e( 'Bulk Actions', 'bbpress' ); ?></option>
						<?php bbp_admin_repair_tool_bulk_actions( $tools ); ?>
					</select>
					<input type="submit" name="doaction2" id="doaction2" class="button action" value="<?php esc_attr_e( 'Apply', 'bbpress' ); ?>">
				</div>
			</div>
		</form>
	</div>

<?php
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
 * @since 2.7.0 bbPress (r8265) Previews one resumable batch per call.
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
	$state   = get_option( bbp_admin_repair_tool_batch_option_name( $tool_id ), false );
	$batch   = array(
		'code'      => 0,
		'repaired'  => 0,
		'complete'  => false,
		'restarted' => false
	);

	if ( ( false !== $state ) && ! bbp_admin_repair_tool_is_valid_checkpoint( $state ) ) {
		$state              = false;
		$batch['restarted'] = true;
	}

	$tpt = bbp_get_topic_post_type();
	$rpt = bbp_get_reply_post_type();
	if ( false === $state ) {
		$upper_id = (int) $bbp_db->get_var( $bbp_db->prepare( "SELECT MAX(ID) FROM {$bbp_db->posts} WHERE post_type = %s", $tpt ) );
		if ( ! empty( $bbp_db->last_error ) ) {
			$batch['code'] = 1;
			return $batch;
		}
		$state = array(
			'cursor'   => 0,
			'upper_id' => $upper_id
		);
		if ( ! $dry_run && ! bbp_admin_repair_tool_save_checkpoint( $tool_id, $state ) ) {
			$batch['code'] = 6;
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
	$topics         = $bbp_db->get_results(
		$bbp_db->prepare(
			"SELECT ID, post_status IN ({$topic_slots}) AS is_public FROM {$bbp_db->posts}
			WHERE post_type = %s AND ID >= %d AND ID <= %d ORDER BY ID LIMIT %d",
			array_merge( $topic_statuses, array( $tpt, $state['cursor'], $state['upper_id'], $size + 1 ) )
		),
		ARRAY_A
	);
	if ( ! empty( $bbp_db->last_error ) ) {
		$batch['code'] = 1;
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
				$batch['code'] = 1;
				return $batch;
			}
			// Hidden zero counts have no row; public zero counts retain one
			if ( $hidden && empty( $count ) ) {
				$count = null;
			}
		}

		$repair = bbp_admin_repair_count_metadata( 'post', $topic_id, $key, $count, $size - $work, $dry_run );
		if ( ! empty( $repair['code'] ) ) {
			$batch['code'] = $repair['code'];
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
	if ( ! $dry_run && ! bbp_admin_repair_tool_save_checkpoint( $tool_id, $state, $complete ) ) {
		$batch['code'] = 6;
		return $batch;
	}
	$batch['complete'] = $complete;

	return $batch;
}

/**
 * Recount or preview one batch of public or non-public topic replies.
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
	$statement = bbp_admin_repair_tool_restart_statement( $statement, $batch['restarted'], $dry_run );
	if ( ! empty( $batch['code'] ) ) {
		return array(
			$batch['code'],
			sprintf( $statement, esc_html__( 'Failed!', 'bbpress' ) ),
			'success'  => false,
			'complete' => false
		);
	}
	if ( $dry_run && empty( $batch['repaired'] ) && $batch['complete'] ) {
		return array(
			1,
			sprintf( $statement, $no_counts ),
			'complete' => true
		);
	}

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
	$result = esc_html__( 'This batch:', 'bbpress' ) . "\n" . $result;
	if ( ! $dry_run ) {
		$status = $batch['complete'] ? esc_html__( 'Complete!', 'bbpress' ) : esc_html__( 'Batch complete!', 'bbpress' );
		$result = $status . "\n" . $result;
	}
	return array(
		0,
		sprintf( $statement, $result ),
		'complete' => $batch['complete']
	);
}

/**
 * Recount topic voices.
 *
 * @since 2.1.0 bbPress (r3884)
 * @since 2.7.0 bbPress (r8221) Added Dry Run support.
 *
 * @return array An array of the status code and the message.
 */
function bbp_admin_repair_topic_voice_count() {
	return bbp_admin_repair_topic_voice_count_process();
}

/**
 * Preview recounting topic voices.
 *
 * @since 2.7.0 bbPress (r8221)
 *
 * @return array An array of the status code and the message.
 */
function bbp_admin_repair_topic_voice_count_dry_run() {
	return bbp_admin_repair_topic_voice_count_process( true );
}

/**
 * Recount or preview topic voices.
 *
 * @since 2.7.0 bbPress (r8221)
 *
 * @param bool $dry_run Whether to preview changes without updating data.
 * @return array An array of the status code and the message.
 */
function bbp_admin_repair_topic_voice_count_process( $dry_run = false ) {

	// Define variables
	$bbp_db = bbp_db();
	$result = esc_html__( 'Failed!', 'bbpress' );

	if ( $dry_run ) {
		/* translators: %s: Status of the dry-run process */
		$statement = esc_html__( 'Checking the number of voices in each topic&hellip; %s', 'bbpress' );
	} else {
		/* translators: %s: Status of the counting process */
		$statement = esc_html__( 'Counting the number of voices in each topic&hellip; %s', 'bbpress' );
	}

	$tpt = bbp_get_topic_post_type();

	// Post types and status
	$rpt                = bbp_get_reply_post_type();
	$sql_topic_statuses = "'" . implode( "','", bbp_get_public_topic_statuses() ) . "'";
	$sql_reply_statuses = "'" . implode( "','", bbp_get_public_reply_statuses() ) . "'";

	$engagements_from_sql = $bbp_db->prepare( "FROM {$bbp_db->posts} AS posts
			INNER JOIN {$bbp_db->postmeta} AS postmeta
				ON posts.ID = postmeta.post_id
				AND postmeta.meta_key = '_bbp_topic_id'
			INNER JOIN {$bbp_db->posts} AS topics
				ON topics.ID = postmeta.meta_value
				AND topics.post_type = %s
			WHERE (
					( posts.post_type = %s AND posts.post_status IN ({$sql_topic_statuses}) )
					OR ( posts.post_type = %s AND posts.post_status IN ({$sql_reply_statuses}) )
				)
				AND posts.post_author > 0",
		$tpt,
		$tpt,
		$rpt
	);

	if ( ! $dry_run ) {
		$sql_delete = "DELETE `postmeta` FROM `{$bbp_db->postmeta}` AS `postmeta`
							LEFT JOIN `{$bbp_db->posts}` AS `posts` ON `posts`.`ID` = `postmeta`.`post_id`
							WHERE `posts`.`post_type` = '{$tpt}'
							AND `postmeta`.`meta_key` IN ( '_bbp_voice_count', '_bbp_engagement' )";
		if ( false === $bbp_db->query( $sql_delete ) ) {
			return array( 1, sprintf( $statement, $result ) );
		}

		$engagements_sql = "INSERT INTO {$bbp_db->postmeta} (post_id, meta_key, meta_value) (
			SELECT topics.ID, '_bbp_engagement', posts.post_author
			{$engagements_from_sql}
			GROUP BY topics.ID, posts.post_author)";
		if ( false === $bbp_db->query( $engagements_sql ) ) {
			return array( 2, sprintf( $statement, $result ) );
		}

		$voice_count_sql = $bbp_db->prepare( "INSERT INTO {$bbp_db->postmeta} (post_id, meta_key, meta_value) (
			SELECT engagements.post_id, '_bbp_voice_count', COUNT(DISTINCT engagements.meta_value)
				FROM {$bbp_db->postmeta} AS engagements
				INNER JOIN {$bbp_db->posts} AS topics
					ON topics.ID = engagements.post_id
					AND topics.post_type = %s
				WHERE engagements.meta_key = '_bbp_engagement'
				GROUP BY engagements.post_id)",
			$tpt
		);

		if ( false === $bbp_db->query( $voice_count_sql ) ) {
			return array( 3, sprintf( $statement, $result ) );
		}

		bbp_admin_repair_clean_post_meta_cache( $tpt );

		return array( 0, sprintf( $statement, esc_html__( 'Complete!', 'bbpress' ) ) );
	}

	$expected_engagements_sql = "SELECT topics.ID AS topic_id, posts.post_author AS user_id
		{$engagements_from_sql}
		GROUP BY topics.ID, posts.post_author";
	$stored_engagements_sql   = $bbp_db->prepare( "SELECT postmeta.post_id AS topic_id, postmeta.meta_value AS user_id, COUNT(*) AS row_count
		FROM {$bbp_db->postmeta} AS postmeta
		INNER JOIN {$bbp_db->posts} AS topics
			ON topics.ID = postmeta.post_id
			AND topics.post_type = %s
		WHERE postmeta.meta_key = '_bbp_engagement'
		GROUP BY postmeta.post_id, postmeta.meta_value",
		$tpt
	);

	/*
	 * Compare expected and stored engagement rows in both directions. Grouping
	 * stored rows also identifies duplicate relationships that Run will remove.
	 */
	$engagement_repair_sql = "SELECT COUNT(DISTINCT repairs.topic_id)
		FROM (
			SELECT expected.topic_id
			FROM ( {$expected_engagements_sql} ) AS expected
			LEFT JOIN ( {$stored_engagements_sql} ) AS stored
				ON stored.topic_id = expected.topic_id
				AND CAST(stored.user_id AS BINARY) = CAST(expected.user_id AS BINARY)
			WHERE stored.topic_id IS NULL
				OR stored.row_count <> 1
			UNION ALL
			SELECT stored.topic_id
			FROM ( {$stored_engagements_sql} ) AS stored
			LEFT JOIN ( {$expected_engagements_sql} ) AS expected
				ON expected.topic_id = stored.topic_id
				AND CAST(expected.user_id AS BINARY) = CAST(stored.user_id AS BINARY)
			WHERE expected.topic_id IS NULL
		) AS repairs";
	$engagement_repair_count = (int) $bbp_db->get_var( $engagement_repair_sql );

	if ( ! empty( $bbp_db->last_error ) ) {
		return array( 4, sprintf( $statement, $result ) );
	}

	$expected_voice_counts_sql = "SELECT expected.topic_id, COUNT(*) AS voice_count
		FROM ( {$expected_engagements_sql} ) AS expected
		GROUP BY expected.topic_id";
	$stored_voice_counts_sql   = $bbp_db->prepare( "SELECT postmeta.post_id AS topic_id, COUNT(*) AS row_count, MIN(postmeta.meta_value) AS voice_count
		FROM {$bbp_db->postmeta} AS postmeta
		INNER JOIN {$bbp_db->posts} AS topics
			ON topics.ID = postmeta.post_id
			AND topics.post_type = %s
		WHERE postmeta.meta_key = '_bbp_voice_count'
		GROUP BY postmeta.post_id",
		$tpt
	);

	/*
	 * Compare expected and stored voice-count rows in both directions. A stored
	 * topic must have exactly one row containing the calculated count.
	 */
	$voice_repair_sql = "SELECT COUNT(DISTINCT repairs.topic_id)
		FROM (
			SELECT expected.topic_id
			FROM ( {$expected_voice_counts_sql} ) AS expected
			LEFT JOIN ( {$stored_voice_counts_sql} ) AS stored
				ON stored.topic_id = expected.topic_id
			WHERE stored.topic_id IS NULL
				OR stored.row_count <> 1
				OR NOT ( CAST(stored.voice_count AS BINARY) <=> CAST(expected.voice_count AS BINARY) )
			UNION ALL
			SELECT stored.topic_id
			FROM ( {$stored_voice_counts_sql} ) AS stored
			LEFT JOIN ( {$expected_voice_counts_sql} ) AS expected
				ON expected.topic_id = stored.topic_id
			WHERE expected.topic_id IS NULL
		) AS repairs";
	$voice_repair_count = (int) $bbp_db->get_var( $voice_repair_sql );

	if ( ! empty( $bbp_db->last_error ) ) {
		return array( 5, sprintf( $statement, $result ) );
	}

	if ( empty( $engagement_repair_count ) && empty( $voice_repair_count ) ) {
		$result = esc_html__( '0 topic engagements or voice counts to repair.', 'bbpress' );
		return array( 1, sprintf( $statement, $result ) );
	}

	/* translators: %d: Number of topics whose engagements would be repaired */
	$engagements = sprintf( _n( 'Engagements would be repaired in %d topic.', 'Engagements would be repaired in %d topics.', $engagement_repair_count, 'bbpress' ), $engagement_repair_count );
	/* translators: %d: Number of topics whose voice counts would be repaired */
	$voice_counts = sprintf( _n( 'Voice counts would be repaired in %d topic.', 'Voice counts would be repaired in %d topics.', $voice_repair_count, 'bbpress' ), $voice_repair_count );

	return array( 0, sprintf( $statement, $engagements . "\n" . $voice_counts ) );
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
 * @since 2.7.0 bbPress (r8265) Previews one resumable batch per call.
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
 * Recount forum topics.
 *
 * @since 2.1.0 bbPress (r3884)
 * @since 2.7.0 bbPress (r8242) Added Dry Run support.
 *
 * @return array An array of the status code and the message.
 */
function bbp_admin_repair_forum_topic_count() {
	return bbp_admin_repair_forum_topic_count_process();
}

/**
 * Preview recounting forum topics.
 *
 * @since 2.7.0 bbPress (r8242)
 *
 * @return array An array of the status code and the message.
 */
function bbp_admin_repair_forum_topic_count_dry_run() {
	return bbp_admin_repair_forum_topic_count_process( true );
}

/**
 * Compare calculated forum topic counts with their stored metadata.
 *
 * This helper reads directly from the database so Dry Run does not populate or
 * invalidate persistent object caches.
 *
 * @since 2.7.0 bbPress (r8242)
 *
 * @return array Status code, countable forum IDs, and number of forums to repair.
 */
function bbp_admin_repair_forum_topic_count_get_status() {
	$bbp_db             = bbp_db();
	$fpt                = bbp_get_forum_post_type();
	$tpt                = bbp_get_topic_post_type();
	$rpt                = bbp_get_reply_post_type();
	$countable_statuses = bbp_get_string_array_values( bbp_get_countable_forum_statuses() );
	$public_statuses    = array_values( array_unique( array_filter( bbp_get_public_topic_statuses() ) ) );
	$hidden_statuses    = array_values( array_unique( array_filter( bbp_get_non_public_topic_statuses() ) ) );
	$meta_keys          = array( '_bbp_topic_count', '_bbp_total_topic_count', '_bbp_topic_count_hidden', '_bbp_total_topic_count_hidden' );

	$countable_sql = ! empty( $countable_statuses )
		? '`post_status` IN ( ' . implode( ', ', array_fill( 0, count( $countable_statuses ), '%s' ) ) . ' )'
		: '0';
	$forum_rows   = $bbp_db->get_results(
		$bbp_db->prepare(
			"SELECT `ID`, `post_parent`, `post_status`, {$countable_sql} AS `is_countable`
			FROM `{$bbp_db->posts}`
			WHERE `post_type` = %s
			ORDER BY `ID`",
			array_merge( $countable_statuses, array( $fpt ) )
		),
		ARRAY_A
	);

	if ( ! empty( $bbp_db->last_error ) ) {
		return array( 'code' => 3 );
	}

	$topic_rows = $bbp_db->get_results(
		$bbp_db->prepare(
			"SELECT `post_parent` AS `forum_id`, `post_status`, COUNT(*) AS `topic_count`
			FROM `{$bbp_db->posts}`
			WHERE `post_type` = %s
			GROUP BY `post_parent`, `post_status`",
			$tpt
		),
		ARRAY_A
	);

	if ( ! empty( $bbp_db->last_error ) ) {
		return array( 'code' => 4 );
	}

	$key_placeholders = implode( ', ', array_fill( 0, count( $meta_keys ), '%s' ) );
	$meta_rows        = $bbp_db->get_results(
		$bbp_db->prepare(
			"SELECT `forummeta`.`post_id`, `forummeta`.`meta_key`, `forummeta`.`meta_value`
			FROM `{$bbp_db->postmeta}` AS `forummeta`
			INNER JOIN `{$bbp_db->posts}` AS `forums`
				ON `forums`.`ID` = `forummeta`.`post_id`
			WHERE `forums`.`post_type` = %s
				AND `forummeta`.`meta_key` IN ( {$key_placeholders} )
			ORDER BY `forummeta`.`post_id`, `forummeta`.`meta_id`",
			array_merge( array( $fpt ), $meta_keys )
		),
		ARRAY_A
	);

	if ( ! empty( $bbp_db->last_error ) ) {
		return array( 'code' => 5 );
	}

	$forums              = array();
	$countable_forum_ids = array();
	$children            = array();
	$direct_counts       = array();
	$stored              = array();
	$hidden_lookup       = array_fill_keys( $hidden_statuses, true );

	foreach ( $forum_rows as $forum ) {
		$forum_id            = (int) $forum['ID'];
		$forums[ $forum_id ] = array(
			'parent_id' => (int) $forum['post_parent'],
			'status'    => $forum['post_status'],
		);

		if ( ! empty( $forum['is_countable'] ) ) {
			$countable_forum_ids[] = $forum_id;
			$children[ (int) $forum['post_parent'] ][] = $forum_id;
		}
	}

	foreach ( $topic_rows as $topic ) {
		$forum_id = (int) $topic['forum_id'];
		$status   = $topic['post_status'];
		$count    = (int) $topic['topic_count'];

		if ( isset( $hidden_lookup[ $status ] ) ) {
			$direct_counts[ $forum_id ]['hidden'] = isset( $direct_counts[ $forum_id ]['hidden'] )
				? $direct_counts[ $forum_id ]['hidden'] + $count
				: $count;
		}
	}

	foreach ( $meta_rows as $meta ) {
		$stored[ (int) $meta['post_id'] ][] = array(
			'key'   => $meta['meta_key'],
			'value' => $meta['meta_value'],
		);
	}

	$expected         = array();
	$visiting         = array();
	$error_code       = 0;
	$get_public_count = static function ( $forum_id ) use ( $bbp_db, $fpt, $tpt, $rpt, $public_statuses ) {
		$sql = "SELECT
						p.post_type AS type,
						p.post_status AS status,
						COUNT( * ) AS count
					FROM {$bbp_db->posts} AS p
					WHERE p.post_parent = %d
						AND p.post_type IN ( %s, %s )
					GROUP BY p.post_status, p.post_type
					UNION ALL
					SELECT
						r.post_type AS type,
						r.post_status AS status,
						COUNT( * ) AS count
					FROM {$bbp_db->posts} AS r
						INNER JOIN {$bbp_db->posts} AS t
							ON r.post_parent = t.ID
					WHERE t.post_parent = %d
						AND t.post_type = %s
						AND r.post_type = %s
					GROUP BY r.post_status, r.post_type";
		$query = $bbp_db->prepare( $sql, $forum_id, $fpt, $tpt, $forum_id, $tpt, $rpt );

		/* This filter is documented in bbp_get_child_counts(). */
		$query   = apply_filters( 'bbp_get_child_counts_query', $query, $forum_id, $fpt );
		$results = $bbp_db->get_results( $query, ARRAY_A );

		if ( ! empty( $bbp_db->last_error ) ) {
			return false;
		}

		$counts   = wp_list_pluck( $results, 'type', 'type' );
		$statuses = get_post_stati();
		foreach ( $results as $row ) {
			if ( ! is_array( $counts[ $row['type'] ] ) ) {
				$counts[ $row['type'] ] = array_fill_keys( $statuses, 0 );
			}

			$counts[ $row['type'] ][ $row['status'] ] = bbp_number_not_negative( $row['count'] );
		}

		$counts   = (array) apply_filters( 'bbp_get_child_counts', $counts, $forum_id );
		$types    = array_flip( array( $tpt ) );
		$statuses = array_flip( $public_statuses );
		$filtered = array();

		foreach ( $counts as $type => $type_counts ) {
			if ( ! isset( $types[ $type ] ) ) {
				continue;
			}

			if ( ! empty( $statuses ) ) {
				$type_counts = array_intersect_key( $type_counts, $statuses );
			}

			$filtered[ $type ] = $type_counts;
		}

		$filtered = (array) apply_filters( 'bbp_filter_child_counts_list', $filtered, $forum_id, $types, $statuses );
		$count    = isset( $filtered[ $tpt ] )
			? bbp_number_not_negative( array_sum( array_values( $filtered[ $tpt ] ) ) )
			: 0;

		return (int) apply_filters( 'bbp_get_public_child_count', $count, $forum_id, $tpt );
	};
	$calculate = static function ( $forum_id ) use ( &$calculate, &$expected, &$visiting, &$error_code, $children, $direct_counts, $forums, $get_public_count ) {
		$forum_id = (int) $forum_id;

		if ( isset( $expected[ $forum_id ] ) ) {
			return $expected[ $forum_id ];
		}

		if ( isset( $visiting[ $forum_id ] ) || ! isset( $forums[ $forum_id ] ) ) {
			$error_code = 6;
			return false;
		}

		$visiting[ $forum_id ] = true;
		$public                = $get_public_count( $forum_id );
		if ( false === $public ) {
			$error_code = 9;
			return false;
		}

		$hidden                = isset( $direct_counts[ $forum_id ]['hidden'] ) ? (int) $direct_counts[ $forum_id ]['hidden'] : 0;
		$total_public          = $public;
		$total_hidden          = $hidden;
		$subforum_ids          = isset( $children[ $forum_id ] ) ? $children[ $forum_id ] : array();
		$subforum_ids          = array_map( 'absint', (array) apply_filters( 'bbp_forum_query_subforum_ids', $subforum_ids, $forum_id ) );

		foreach ( $subforum_ids as $subforum_id ) {
			$subforum = $calculate( $subforum_id );
			if ( false === $subforum ) {
				return false;
			}

			$total_public += (int) apply_filters( 'bbp_update_forum_topic_count', $subforum['total_public'], $subforum_id );
			$total_hidden += (int) apply_filters( 'bbp_get_forum_topic_count_hidden_int', $subforum['total_hidden'], $subforum_id );
		}

		unset( $visiting[ $forum_id ] );

		$expected[ $forum_id ] = array(
			'_bbp_topic_count'              => (string) $public,
			'_bbp_total_topic_count'        => (string) $total_public,
			'_bbp_topic_count_hidden'       => (string) $hidden,
			'_bbp_total_topic_count_hidden' => (string) $total_hidden,
			'total_public'                  => $total_public,
			'total_hidden'                  => $total_hidden,
		);

		return $expected[ $forum_id ];
	};

	foreach ( $countable_forum_ids as $forum_id ) {
		if ( false === $calculate( $forum_id ) ) {
			return array( 'code' => $error_code );
		}
	}

	$repair_count = 0;
	foreach ( array_keys( $forums ) as $forum_id ) {
		$rows = isset( $stored[ $forum_id ] ) ? $stored[ $forum_id ] : array();

		if ( ! isset( $expected[ $forum_id ] ) ) {
			if ( ! empty( $rows ) ) {
				++$repair_count;
			}
			continue;
		}

		$needs_repair = ( count( $meta_keys ) !== count( $rows ) );
		foreach ( $meta_keys as $meta_key ) {
			$matches = array();
			foreach ( $rows as $row ) {
				if ( $meta_key === $row['key'] ) {
					$matches[] = $row['value'];
				}
			}

			if ( ( 1 !== count( $matches ) ) || ( reset( $matches ) !== $expected[ $forum_id ][ $meta_key ] ) ) {
				$needs_repair = true;
			}
		}

		if ( $needs_repair ) {
			++$repair_count;
		}
	}

	return array(
		'code'         => 0,
		'forum_ids'    => $countable_forum_ids,
		'repair_count' => $repair_count,
	);
}

/**
 * Repair or preview repairing forum topic counts.
 *
 * @since 2.7.0 bbPress (r8242)
 *
 * @param bool $dry_run Whether to preview the repair without updating data.
 * @return array An array of the status code and the message.
 */
function bbp_admin_repair_forum_topic_count_process( $dry_run = false ) {

	// Define variables
	$bbp_db    = bbp_db();
	$result    = esc_html__( 'Failed!', 'bbpress' );
	$fpt       = bbp_get_forum_post_type();
	$meta_keys = array( '_bbp_topic_count', '_bbp_total_topic_count', '_bbp_topic_count_hidden', '_bbp_total_topic_count_hidden' );

	if ( $dry_run ) {
		/* translators: %s: Status of the dry-run process */
		$statement = esc_html__( 'Checking the number of topics in each forum&hellip; %s', 'bbpress' );
	} else {
		/* translators: %s: Status of the counting process */
		$statement = esc_html__( 'Counting the number of topics in each forum&hellip; %s', 'bbpress' );
	}

	$status = bbp_admin_repair_forum_topic_count_get_status();
	if ( ! empty( $status['code'] ) ) {
		return array(
			$status['code'],
			sprintf( $statement, $result ),
			'success' => false,
		);
	}

	if ( empty( $status['repair_count'] ) ) {
		$result = esc_html__( '0 forum topic counts to repair.', 'bbpress' );
		return array( 1, sprintf( $statement, $result ) );
	}

	if ( $dry_run ) {
		/* translators: %d: Number of forums whose topic counts would be repaired */
		$result = sprintf( _n( 'Topic counts would be repaired in %d forum.', 'Topic counts would be repaired in %d forums.', $status['repair_count'], 'bbpress' ), $status['repair_count'] );

		return array( 0, sprintf( $statement, $result ) );
	}

	$clean_cache = static function () use ( $fpt ) {
		return bbp_admin_repair_clean_post_meta_cache_batched( array( $fpt ) );
	};

	if ( ! $clean_cache() ) {
		return array(
			7,
			sprintf( $statement, $result ),
			'success' => false,
		);
	}

	$key_placeholders = implode( ', ', array_fill( 0, count( $meta_keys ), '%s' ) );
	$sql_delete       = $bbp_db->prepare(
		"DELETE `postmeta` FROM `{$bbp_db->postmeta}` AS `postmeta`
		INNER JOIN `{$bbp_db->posts}` AS `posts`
			ON `posts`.`ID` = `postmeta`.`post_id`
		WHERE `posts`.`post_type` = %s
			AND `postmeta`.`meta_key` IN ( {$key_placeholders} )",
		array_merge( array( $fpt ), $meta_keys )
	);
	if ( false === $bbp_db->query( $sql_delete ) ) {
		$clean_cache();

		return array(
			1,
			sprintf( $statement, $result ),
			'success' => false,
		);
	}

	wp_cache_set( 'last_changed', microtime(), 'bbpress_posts' );

	foreach ( $status['forum_ids'] as $forum_id ) {
		bbp_update_forum_topic_count( $forum_id );
		bbp_update_forum_topic_count_hidden( $forum_id );
	}

	if ( ! $clean_cache() ) {
		return array(
			7,
			sprintf( $statement, $result ),
			'success' => false,
		);
	}

	$status = bbp_admin_repair_forum_topic_count_get_status();
	if ( ! empty( $status['code'] ) || ! empty( $status['repair_count'] ) ) {
		return array(
			empty( $status['code'] ) ? 8 : $status['code'],
			sprintf( $statement, $result ),
			'success' => false,
		);
	}

	return array( 0, sprintf( $statement, esc_html__( 'Complete!', 'bbpress' ) ) );
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
 * @since 2.7.0 bbPress (r8274) Previews one resumable batch per call.
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
	$countable = array_values( array_unique( array_filter( bbp_get_countable_forum_statuses() ) ) );
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
	$bbp_db   = bbp_db();
	$key      = '_bbp_forum_subforum_count';
	$eligible = array_keys( get_post_stati() );
	$slots    = implode( ', ', array_fill( 0, count( $eligible ), '%s' ) );
	$scope    = '';
	$args     = array_merge( array( bbp_get_forum_post_type() ), $eligible, array( $key ) );
	if ( null !== $forum_ids ) {
		$forum_ids = wp_parse_id_list( $forum_ids );
		$scope     = 'AND 1 = 0';
		if ( ! empty( $forum_ids ) ) {
			$scope = 'AND `forums`.`ID` IN (' . implode( ', ', array_fill( 0, count( $forum_ids ), '%d' ) ) . ')';
			$args  = array_merge( $args, $forum_ids );
		}
	}
	// Aggregate duplicate rows instead of loading every stored value into PHP
	$rows = $bbp_db->get_results(
		$bbp_db->prepare(
			"SELECT `forummeta`.`post_id`, COUNT(*) AS `row_count`,
				MIN(`forummeta`.`meta_key`) AS `meta_key`, MIN(`forummeta`.`meta_value`) AS `meta_value`
			FROM `{$bbp_db->postmeta}` AS `forummeta`
			INNER JOIN `{$bbp_db->posts}` AS `forums` ON `forums`.`ID` = `forummeta`.`post_id`
			WHERE `forums`.`post_type` = %s AND `forums`.`post_status` IN ({$slots})
				AND `forummeta`.`meta_key` = %s {$scope}
			GROUP BY `forummeta`.`post_id` ORDER BY `forummeta`.`post_id`",
			$args
		),
		ARRAY_A
	);
	if ( ! empty( $bbp_db->last_error ) ) {
		return array( 'code' => 4 );
	}
	$stored = array();
	foreach ( $rows as $row ) {
		$stored[ (int) $row['post_id'] ] = $row;
	}
	$repairs = 0;
	foreach ( $result['counts'] as $id => $count ) {
		if ( ! isset( $stored[ $id ] ) || ( 1 !== (int) $stored[ $id ]['row_count'] ) || ( $key !== $stored[ $id ]['meta_key'] ) || ( (string) $count !== $stored[ $id ]['meta_value'] ) ) {
			++$repairs;
		}
	}
	return array(
		'code'         => 0,
		'forum_ids'    => array_keys( $result['counts'] ),
		'repair_count' => $repairs
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
	$state   = get_option( bbp_admin_repair_tool_batch_option_name( $tool_id ), false );
	$batch   = array(
		'code'      => 0,
		'repaired'  => 0,
		'initial'   => false,
		'complete'  => false,
		'restarted' => false
	);
	if ( ( false !== $state ) && ( ! bbp_admin_repair_tool_is_valid_checkpoint( $state ) || ! isset( $state['post_type'] ) || ( $fpt !== $state['post_type'] ) ) ) {
		$state              = false;
		$batch['restarted'] = true;
	}
	$batch['initial'] = ( false === $state );
	if ( $batch['initial'] ) {
		$upper_id = (int) $bbp_db->get_var( $bbp_db->prepare( "SELECT MAX(ID) FROM {$bbp_db->posts} WHERE post_type = %s", $fpt ) );
		if ( ! empty( $bbp_db->last_error ) ) {
			$batch['code'] = 3;
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
	$ids      = $bbp_db->get_col(
		$bbp_db->prepare(
			"SELECT ID FROM {$bbp_db->posts} WHERE post_type = %s AND post_status IN ({$slots})
			AND ID >= %d AND ID <= %d ORDER BY ID LIMIT %d",
			array_merge( array( $fpt ), $eligible, array( $state['cursor'], $state['upper_id'], $size + 1 ) )
		)
	);
	if ( ! empty( $bbp_db->last_error ) ) {
		$batch['code'] = 5;
		return $batch;
	}
	$has_more = count( $ids ) > $size;
	$ids      = array_slice( $ids, 0, $size );
	$counts   = bbp_admin_repair_forum_subforum_counts( $ids );
	if ( ! empty( $counts['code'] ) ) {
		$batch['code'] = $counts['code'];
		return $batch;
	}
	if ( ! $dry_run && $batch['initial'] && ! bbp_admin_repair_tool_save_checkpoint( $tool_id, $state ) ) {
		$batch['code'] = 6;
		return $batch;
	}
	$work      = 0;
	$finished  = 0;
	$completed = array();
	foreach ( $counts['counts'] as $id => $count ) {
		if ( $work >= $size ) {
			break;
		}
		$repair = bbp_admin_repair_count_metadata( 'post', $id, '_bbp_forum_subforum_count', $count, $size - $work, $dry_run );
		if ( ! empty( $repair['code'] ) ) {
			$codes = array(
				2 => 4,
				3 => 1,
				4 => 6
			);
			$batch['code'] = $codes[ $repair['code'] ];
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
			$batch['code'] = ! empty( $status['code'] ) ? $status['code'] : 6;
			return $batch;
		}
	}
	$complete = ! $has_more && ( count( $counts['counts'] ) === $finished );
	if ( ! $dry_run && ! bbp_admin_repair_tool_save_checkpoint( $tool_id, $state, $complete ) ) {
		$batch['code'] = 6;
		return $batch;
	}
	$batch['complete'] = $complete;
	return $batch;
}

/**
 * Repair or preview one batch of direct subforum counts.
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
	$statement = bbp_admin_repair_tool_restart_statement( $statement, $batch['restarted'], $dry_run );
	if ( ! empty( $batch['code'] ) ) {
		return array(
			$batch['code'],
			sprintf( $statement, esc_html__( 'Failed!', 'bbpress' ) ),
			'success'  => false,
			'complete' => false
		);
	}
	if ( empty( $batch['repaired'] ) && $batch['complete'] && ( $dry_run || $batch['initial'] ) ) {
		return array(
			1,
			sprintf( $statement, esc_html__( '0 forum subforum counts to repair.', 'bbpress' ) ),
			'complete' => true
		);
	}
	if ( $dry_run ) {
		/* translators: %d: Number of forums whose subforum counts would be repaired */
		$result = sprintf( _n( 'Subforum counts would be repaired in %d forum.', 'Subforum counts would be repaired in %d forums.', $batch['repaired'], 'bbpress' ), $batch['repaired'] );
	} else {
		/* translators: %d: Number of forums whose subforum counts were repaired */
		$result = sprintf( _n( 'Subforum counts repaired in %d forum.', 'Subforum counts repaired in %d forums.', $batch['repaired'], 'bbpress' ), $batch['repaired'] );
	}
	$result = esc_html__( 'This batch:', 'bbpress' ) . "\n" . $result;
	if ( ! $dry_run ) {
		$status = $batch['complete'] ? esc_html__( 'Complete!', 'bbpress' ) : esc_html__( 'Batch complete!', 'bbpress' );
		$result = $status . "\n" . $result;
	}
	return array(
		0,
		sprintf( $statement, $result ),
		'complete' => $batch['complete']
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
 * @since 2.7.0 bbPress (r8267) Previews one resumable batch per call.
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
	$object_statuses = array_values( array_unique( array_filter( bbp_get_public_topic_statuses() ) ) );

	foreach ( $object_types as &$object_type ) {
		list( $object_type ) = explode( ':', $object_type );
	}
	unset( $object_type );

	$object_types = array_values( array_unique( array_filter( $object_types, 'post_type_exists' ) ) );
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
	$state    = get_option( bbp_admin_repair_tool_batch_option_name( $tool_id ), false );
	$batch    = array(
		'code'      => 0,
		'repaired'  => 0,
		'processed' => 0,
		'initial'   => false,
		'complete'  => false,
		'restarted' => false
	);

	if ( $dry_run && ! $standard ) {
		$batch['code'] = 2;
		return $batch;
	}
	if ( ( false !== $state ) && ( ! bbp_admin_repair_tool_is_valid_checkpoint( $state ) || ! isset( $state['taxonomy'] ) || ( $taxonomy->name !== $state['taxonomy'] ) ) ) {
		$state              = false;
		$batch['restarted'] = true;
	}
	$batch['initial'] = ( false === $state );
	if ( $batch['initial'] ) {
		$upper_id = (int) $bbp_db->get_var( $bbp_db->prepare( "SELECT MAX(term_taxonomy_id) FROM {$bbp_db->term_taxonomy} WHERE taxonomy = %s", $taxonomy->name ) );
		if ( ! empty( $bbp_db->last_error ) ) {
			$batch['code'] = 3;
			return $batch;
		}
		$state = array(
			'cursor'   => 0,
			'upper_id' => $upper_id,
			'taxonomy' => $taxonomy->name
		);
	}

	$size = bbp_admin_repair_tool_batch_size( $tool_id );
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
		$batch['code'] = 3;
		return $batch;
	}
	$complete = count( $rows ) <= $size;
	$rows     = array_slice( $rows, 0, $size );
	$ids      = array_map( 'intval', wp_list_pluck( $rows, 'term_taxonomy_id' ) );
	$term_ids = array_map( 'intval', wp_list_pluck( $rows, 'term_id' ) );

	if ( $standard ) {
		$status = bbp_admin_repair_topic_tag_count_get_status( $taxonomy, $ids );
		if ( ! empty( $status['code'] ) ) {
			$batch['code'] = $status['code'];
			return $batch;
		}
		$batch['repaired'] = $status['repair_count'];
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
			$batch['code'] = 3;
			return $batch;
		}
		$ids = array_values( array_intersect( $ids, wp_parse_id_list( wp_list_pluck( $terms, 'term_taxonomy_id' ) ) ) );
	}
	$batch['processed'] = count( $ids );

	if ( ! $dry_run ) {
		if ( $batch['initial'] && ! bbp_admin_repair_tool_save_checkpoint( $tool_id, $state ) ) {
			$batch['code'] = 6;
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
			$batch['code'] = 4;
			return $batch;
		}
		if ( $standard && ! empty( $batch['repaired'] ) ) {
			$status = bbp_admin_repair_topic_tag_count_get_status( $taxonomy, $ids );
			if ( ! empty( $status['code'] ) || ! empty( $status['repair_count'] ) ) {
				$batch['code'] = ! empty( $status['code'] ) ? $status['code'] : 4;
				return $batch;
			}
		}
		if ( ! empty( $rows ) ) {
			$state['cursor'] = (int) end( $rows )['term_taxonomy_id'];
		}
		if ( ! bbp_admin_repair_tool_save_checkpoint( $tool_id, $state, $complete ) ) {
			$batch['code'] = 6;
			return $batch;
		}
	}
	$batch['complete'] = $complete;

	return $batch;
}

/**
 * Repair or preview one batch of topic-tag counts.
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
	$statement = bbp_admin_repair_tool_restart_statement( $statement, $batch['restarted'], $dry_run );
	if ( ! empty( $batch['code'] ) ) {
		return array(
			$batch['code'],
			sprintf( $statement, $result ),
			'success'  => false,
			'complete' => false
		);
	}
	if ( $standard && empty( $batch['repaired'] ) && $batch['complete'] && ( $dry_run || $batch['initial'] ) ) {
		return array(
			1,
			sprintf( $statement, esc_html__( '0 topic-tag counts to repair.', 'bbpress' ) ),
			'complete' => true
		);
	}
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
	$result = esc_html__( 'This batch:', 'bbpress' ) . "\n" . $result;
	if ( ! $dry_run ) {
		$status = $batch['complete'] ? esc_html__( 'Complete!', 'bbpress' ) : esc_html__( 'Batch complete!', 'bbpress' );
		$result = $status . "\n" . $result;
	}
	return array(
		0,
		sprintf( $statement, $result ),
		'complete' => $batch['complete']
	);
}

/**
 * Recount forum replies.
 *
 * @since 2.1.0 bbPress (r3884)
 * @since 2.7.0 bbPress (r8245) Added Dry Run support.
 *
 * @return array An array of the status code and the message.
 */
function bbp_admin_repair_forum_reply_count() {
	return bbp_admin_repair_forum_reply_count_process();
}

/**
 * Preview recounting forum replies.
 *
 * @since 2.7.0 bbPress (r8245)
 *
 * @return array An array of the status code and the message.
 */
function bbp_admin_repair_forum_reply_count_dry_run() {
	return bbp_admin_repair_forum_reply_count_process( true );
}

/**
 * Compare calculated forum reply counts with their stored metadata.
 *
 * This helper reads directly from the database so Dry Run does not populate or
 * invalidate persistent object caches.
 *
 * @since 2.7.0 bbPress (r8245)
 *
 * @return array Status code, countable forum IDs, and number of forums to repair.
 */
function bbp_admin_repair_forum_reply_count_get_status() {
	$bbp_db             = bbp_db();
	$fpt                = bbp_get_forum_post_type();
	$tpt                = bbp_get_topic_post_type();
	$rpt                = bbp_get_reply_post_type();
	$countable_statuses = bbp_get_string_array_values( bbp_get_countable_forum_statuses() );
	$topic_statuses     = array_values( array_unique( array_filter( bbp_get_public_topic_statuses() ) ) );
	$reply_statuses     = array_values( array_unique( array_filter( bbp_get_public_reply_statuses() ) ) );
	$meta_keys          = array( '_bbp_reply_count', '_bbp_total_reply_count' );
	$type_key           = '_bbp_forum_type';

	$countable_sql = ! empty( $countable_statuses )
		? '`post_status` IN ( ' . implode( ', ', array_fill( 0, count( $countable_statuses ), '%s' ) ) . ' )'
		: '0';
	$forum_rows   = $bbp_db->get_results(
		$bbp_db->prepare(
			"SELECT `ID`, `post_parent`, `post_status`, {$countable_sql} AS `is_countable`
			FROM `{$bbp_db->posts}`
			WHERE `post_type` = %s
			ORDER BY `ID`",
			array_merge( $countable_statuses, array( $fpt ) )
		),
		ARRAY_A
	);

	if ( ! empty( $bbp_db->last_error ) ) {
		return array( 'code' => 3 );
	}

	$topic_status_sql = ! empty( $topic_statuses )
		? 'AND `topics`.`post_status` IN ( ' . implode( ', ', array_fill( 0, count( $topic_statuses ), '%s' ) ) . ' )'
		: 'AND 1 = 0';
	$reply_status_sql = ! empty( $reply_statuses )
		? 'AND `replies`.`post_status` IN ( ' . implode( ', ', array_fill( 0, count( $reply_statuses ), '%s' ) ) . ' )'
		: 'AND 1 = 0';
	$reply_args       = array_merge( array( $tpt ), $topic_statuses, array( $rpt ), $reply_statuses );
	$reply_rows       = $bbp_db->get_results(
		$bbp_db->prepare(
			"SELECT `topics`.`post_parent` AS `forum_id`, COUNT(*) AS `reply_count`
			FROM `{$bbp_db->posts}` AS `replies`
			INNER JOIN `{$bbp_db->posts}` AS `topics`
				ON `replies`.`post_parent` = `topics`.`ID`
			WHERE `topics`.`post_type` = %s
				{$topic_status_sql}
				AND `replies`.`post_type` = %s
				{$reply_status_sql}
			GROUP BY `topics`.`post_parent`",
			$reply_args
		),
		ARRAY_A
	);

	if ( ! empty( $bbp_db->last_error ) ) {
		return array( 'code' => 4 );
	}

	$key_placeholders = implode( ', ', array_fill( 0, count( $meta_keys ), '%s' ) );
	$meta_rows        = $bbp_db->get_results(
		$bbp_db->prepare(
			"SELECT `forummeta`.`post_id`, `forummeta`.`meta_key`, `forummeta`.`meta_value`
			FROM `{$bbp_db->postmeta}` AS `forummeta`
			INNER JOIN `{$bbp_db->posts}` AS `forums`
				ON `forums`.`ID` = `forummeta`.`post_id`
			WHERE `forums`.`post_type` = %s
				AND (
					BINARY `forummeta`.`meta_key` = BINARY %s
					OR `forummeta`.`meta_key` IN ( {$key_placeholders} )
				)
			ORDER BY `forummeta`.`post_id`, `forummeta`.`meta_id`",
			array_merge( array( $fpt, $type_key ), $meta_keys )
		),
		ARRAY_A
	);

	if ( ! empty( $bbp_db->last_error ) ) {
		return array( 'code' => 5 );
	}

	$forums              = array();
	$countable_forum_ids = array();
	$children            = array();
	$direct_counts       = array();
	$forum_types         = array();
	$stored              = array();

	foreach ( $forum_rows as $forum ) {
		$forum_id            = (int) $forum['ID'];
		$forums[ $forum_id ] = array(
			'parent_id' => (int) $forum['post_parent'],
			'status'    => $forum['post_status'],
		);

		if ( ! empty( $forum['is_countable'] ) ) {
			$countable_forum_ids[] = $forum_id;
			$children[ (int) $forum['post_parent'] ][] = $forum_id;
		}
	}

	foreach ( $reply_rows as $reply ) {
		$direct_counts[ (int) $reply['forum_id'] ] = (int) $reply['reply_count'];
	}

	foreach ( $meta_rows as $meta ) {
		$forum_id = (int) $meta['post_id'];

		if ( $type_key === $meta['meta_key'] ) {
			if ( ! isset( $forum_types[ $forum_id ] ) ) {
				$forum_types[ $forum_id ] = maybe_unserialize( $meta['meta_value'] );
			}

			continue;
		}

		$stored[ $forum_id ][] = array(
			'key'   => $meta['meta_key'],
			'value' => $meta['meta_value'],
		);
	}

	$expected   = array();
	$visiting   = array();
	$error_code = 0;
	$calculate  = static function ( $forum_id ) use ( &$calculate, &$expected, &$visiting, &$error_code, $children, $direct_counts, $forums, $forum_types, $topic_statuses, $reply_statuses ) {
		$forum_id = (int) apply_filters( 'bbp_get_forum_id', (int) $forum_id, $forum_id );

		if ( isset( $expected[ $forum_id ] ) ) {
			return $expected[ $forum_id ];
		}

		if ( isset( $visiting[ $forum_id ] ) || ! isset( $forums[ $forum_id ] ) ) {
			$error_code = 6;
			return false;
		}

		$visiting[ $forum_id ] = true;
		$forum_type             = isset( $forum_types[ $forum_id ] ) ? $forum_types[ $forum_id ] : 'forum';

		if ( empty( $forum_type ) ) {
			$forum_type = 'forum';
		}

		$forum_type  = apply_filters( 'bbp_get_forum_type', $forum_type, $forum_id );
		$is_category = ( ! empty( $forum_type ) && ( 'category' === $forum_type ) );
		$is_category = (bool) apply_filters( 'bbp_is_forum_category', $is_category, $forum_id );
		$direct      = $is_category || empty( $topic_statuses ) || empty( $reply_statuses )
			? 0
			: bbp_number_not_negative( isset( $direct_counts[ $forum_id ] ) ? $direct_counts[ $forum_id ] : 0 );
		$total       = $direct;
		$subforum_ids = isset( $children[ $forum_id ] ) ? $children[ $forum_id ] : array();
		rsort( $subforum_ids, SORT_NUMERIC );
		$subforum_ids = array_map( 'absint', (array) apply_filters( 'bbp_forum_query_subforum_ids', $subforum_ids, $forum_id ) );

		foreach ( $subforum_ids as $subforum_id ) {
			$subforum = $calculate( $subforum_id );
			if ( false === $subforum ) {
				return false;
			}

			$total += (int) apply_filters( 'bbp_update_forum_reply_count', $subforum['total'], $subforum['forum_id'] );
		}

		unset( $visiting[ $forum_id ] );

		$expected[ $forum_id ] = array(
			'_bbp_reply_count'       => (string) $direct,
			'_bbp_total_reply_count' => (string) $total,
			'forum_id'               => $forum_id,
			'total'                  => $total,
		);

		return $expected[ $forum_id ];
	};

	foreach ( $countable_forum_ids as $forum_id ) {
		if ( false === $calculate( $forum_id ) ) {
			return array( 'code' => $error_code );
		}
	}

	$repair_count = 0;
	foreach ( array_keys( $forums ) as $forum_id ) {
		$rows = isset( $stored[ $forum_id ] ) ? $stored[ $forum_id ] : array();

		if ( ! isset( $expected[ $forum_id ] ) ) {
			if ( ! empty( $rows ) ) {
				++$repair_count;
			}
			continue;
		}

		$needs_repair = ( count( $meta_keys ) !== count( $rows ) );
		foreach ( $meta_keys as $meta_key ) {
			$matches = array();
			foreach ( $rows as $row ) {
				if ( $meta_key === $row['key'] ) {
					$matches[] = $row['value'];
				}
			}

			if ( ( 1 !== count( $matches ) ) || ( reset( $matches ) !== $expected[ $forum_id ][ $meta_key ] ) ) {
				$needs_repair = true;
			}
		}

		if ( $needs_repair ) {
			++$repair_count;
		}
	}

	return array(
		'code'         => 0,
		'forum_ids'    => $countable_forum_ids,
		'repair_count' => $repair_count,
	);
}

/**
 * Repair or preview repairing forum reply counts.
 *
 * @since 2.7.0 bbPress (r8245)
 *
 * @param bool $dry_run Whether to preview the repair without updating data.
 * @return array An array of the status code and the message.
 */
function bbp_admin_repair_forum_reply_count_process( $dry_run = false ) {

	// Define variables
	$bbp_db    = bbp_db();
	$result    = esc_html__( 'Failed!', 'bbpress' );
	$fpt       = bbp_get_forum_post_type();
	$meta_keys = array( '_bbp_reply_count', '_bbp_total_reply_count' );

	if ( $dry_run ) {
		/* translators: %s: Status of the dry-run process */
		$statement = esc_html__( 'Checking the number of replies in each forum&hellip; %s', 'bbpress' );
	} else {
		/* translators: %s: Status of the counting process */
		$statement = esc_html__( 'Counting the number of replies in each forum&hellip; %s', 'bbpress' );
	}

	$status = bbp_admin_repair_forum_reply_count_get_status();
	if ( ! empty( $status['code'] ) ) {
		return array(
			$status['code'],
			sprintf( $statement, $result ),
			'success' => false,
		);
	}

	$clean_cache = static function () use ( $fpt ) {
		return bbp_admin_repair_clean_post_meta_cache_batched( array( $fpt ) );
	};

	if ( empty( $status['repair_count'] ) ) {
		$result = esc_html__( '0 forum reply counts to repair.', 'bbpress' );

		if ( ! $dry_run && ! $clean_cache() ) {
			return array(
				7,
				sprintf( $statement, esc_html__( 'Failed!', 'bbpress' ) ),
				'success' => false,
			);
		}

		return array( 1, sprintf( $statement, $result ) );
	}

	if ( $dry_run ) {
		/* translators: %d: Number of forums whose reply counts would be repaired */
		$result = sprintf( _n( 'Reply counts would be repaired in %d forum.', 'Reply counts would be repaired in %d forums.', $status['repair_count'], 'bbpress' ), $status['repair_count'] );

		return array( 0, sprintf( $statement, $result ) );
	}

	if ( ! $clean_cache() ) {
		return array(
			7,
			sprintf( $statement, $result ),
			'success' => false,
		);
	}

	// Delete the meta keys _bbp_reply_count and _bbp_total_reply_count for each forum
	$key_placeholders = implode( ', ', array_fill( 0, count( $meta_keys ), '%s' ) );
	$sql_delete       = $bbp_db->prepare(
		"DELETE `postmeta` FROM `{$bbp_db->postmeta}` AS `postmeta`
		INNER JOIN `{$bbp_db->posts}` AS `posts`
			ON `posts`.`ID` = `postmeta`.`post_id`
		WHERE `posts`.`post_type` = %s
			AND `postmeta`.`meta_key` IN ( {$key_placeholders} )",
		array_merge( array( $fpt ), $meta_keys )
	);

	if ( false === $bbp_db->query( $sql_delete ) ) {
		$clean_cache();

		return array(
			1,
			sprintf( $statement, $result ),
			'success' => false,
		);
	}

	wp_cache_set( 'last_changed', microtime(), 'bbpress_posts' );

	foreach ( $status['forum_ids'] as $forum_id ) {
		bbp_update_forum_reply_count( $forum_id );
	}

	if ( ! $clean_cache() ) {
		return array(
			7,
			sprintf( $statement, $result ),
			'success' => false,
		);
	}

	$status = bbp_admin_repair_forum_reply_count_get_status();
	if ( ! empty( $status['code'] ) || ! empty( $status['repair_count'] ) ) {
		return array(
			empty( $status['code'] ) ? 8 : $status['code'],
			sprintf( $statement, $result ),
			'success' => false,
		);
	}

	return array( 0, sprintf( $statement, esc_html__( 'Complete!', 'bbpress' ) ) );
}

/**
 * Recount non-public forum replies.
 *
 * @since 2.6.0 bbPress (r6933)
 * @since 2.6.0 bbPress (r6933) Rename to match the topic reply recount function
 * @since 2.7.0 bbPress (r8246) Added Dry Run support.
 *
 * @return array An array of the status code and the message.
 */
function bbp_admin_repair_forum_hidden_reply_count() {
	return bbp_admin_repair_forum_hidden_reply_count_process();
}

/**
 * Preview recounting non-public forum replies.
 *
 * @since 2.7.0 bbPress (r8246)
 *
 * @return array An array of the status code and the message.
 */
function bbp_admin_repair_forum_hidden_reply_count_dry_run() {
	return bbp_admin_repair_forum_hidden_reply_count_process( true );
}

/**
 * Compare calculated non-public forum reply counts with stored metadata.
 *
 * This helper reads directly from the database so Dry Run does not populate or
 * invalidate persistent object caches.
 *
 * @since 2.7.0 bbPress (r8246)
 *
 * @return array Status code, countable forum IDs, and number of forums to repair.
 */
function bbp_admin_repair_forum_hidden_reply_count_get_status() {
	$bbp_db             = bbp_db();
	$fpt                = bbp_get_forum_post_type();
	$tpt                = bbp_get_topic_post_type();
	$rpt                = bbp_get_reply_post_type();
	$countable_statuses = bbp_get_string_array_values( bbp_get_countable_forum_statuses() );
	$reply_statuses     = array_values( array_unique( bbp_get_non_public_reply_statuses() ) );
	$meta_keys          = array( '_bbp_reply_count_hidden', '_bbp_total_reply_count_hidden' );
	$type_key           = '_bbp_forum_type';

	$countable_sql = ! empty( $countable_statuses )
		? '`post_status` IN ( ' . implode( ', ', array_fill( 0, count( $countable_statuses ), '%s' ) ) . ' )'
		: '0';
	$forum_rows   = $bbp_db->get_results(
		$bbp_db->prepare(
			"SELECT `ID`, `post_parent`, `post_status`, {$countable_sql} AS `is_countable`
			FROM `{$bbp_db->posts}`
			WHERE `post_type` = %s
			ORDER BY `ID`",
			array_merge( $countable_statuses, array( $fpt ) )
		),
		ARRAY_A
	);

	if ( ! empty( $bbp_db->last_error ) ) {
		return array( 'code' => 3 );
	}

	$key_placeholders = implode( ', ', array_fill( 0, count( $meta_keys ), '%s' ) );
	$meta_rows        = $bbp_db->get_results(
		$bbp_db->prepare(
			"SELECT `forummeta`.`post_id`, `forummeta`.`meta_key`, `forummeta`.`meta_value`
			FROM `{$bbp_db->postmeta}` AS `forummeta`
			INNER JOIN `{$bbp_db->posts}` AS `forums`
				ON `forums`.`ID` = `forummeta`.`post_id`
			WHERE `forums`.`post_type` = %s
				AND (
					BINARY `forummeta`.`meta_key` = BINARY %s
					OR `forummeta`.`meta_key` IN ( {$key_placeholders} )
				)
			ORDER BY `forummeta`.`post_id`, `forummeta`.`meta_id`",
			array_merge( array( $fpt, $type_key ), $meta_keys )
		),
		ARRAY_A
	);

	if ( ! empty( $bbp_db->last_error ) ) {
		return array( 'code' => 4 );
	}

	$forums              = array();
	$countable_forum_ids = array();
	$children            = array();
	$forum_types         = array();
	$stored              = array();
	foreach ( $forum_rows as $forum ) {
		$forum_id            = (int) $forum['ID'];
		$forums[ $forum_id ] = array(
			'parent_id' => (int) $forum['post_parent'],
			'status'    => $forum['post_status'],
		);

		if ( ! empty( $forum['is_countable'] ) ) {
			$countable_forum_ids[] = $forum_id;
			$children[ (int) $forum['post_parent'] ][] = $forum_id;
		}
	}

	foreach ( $meta_rows as $meta ) {
		$forum_id = (int) $meta['post_id'];

		if ( $type_key === $meta['meta_key'] ) {
			if ( ! isset( $forum_types[ $forum_id ] ) ) {
				$forum_types[ $forum_id ] = maybe_unserialize( $meta['meta_value'] );
			}

			continue;
		}

		$stored[ $forum_id ][] = array(
			'key'   => $meta['meta_key'],
			'value' => $meta['meta_value'],
		);
	}

	$expected   = array();
	$visiting   = array();
	$error_code = 0;
	$get_direct = static function ( $forum_id ) use ( $bbp_db, $fpt, $tpt, $rpt, $reply_statuses, &$error_code ) {
		$sql = "SELECT
				p.post_type AS type,
				p.post_status AS status,
				COUNT( * ) AS count
			FROM {$bbp_db->posts} AS p
			WHERE p.post_parent = %d
				AND p.post_type IN ( %s, %s )
			GROUP BY p.post_status, p.post_type
			UNION ALL
			SELECT
				r.post_type AS type,
				r.post_status AS status,
				COUNT( * ) AS count
			FROM {$bbp_db->posts} AS r
				INNER JOIN {$bbp_db->posts} AS t
					ON r.post_parent = t.ID
			WHERE t.post_parent = %d
				AND t.post_type = %s
				AND r.post_type = %s
			GROUP BY r.post_status, r.post_type";
		$query = $bbp_db->prepare( $sql, $forum_id, $fpt, $tpt, $forum_id, $tpt, $rpt );
		$query = apply_filters( 'bbp_get_child_counts_query', $query, $forum_id, $fpt );
		$rows  = $bbp_db->get_results( $query, ARRAY_A );

		if ( ! empty( $bbp_db->last_error ) ) {
			$error_code = 5;
			return false;
		}

		$counts   = wp_list_pluck( $rows, 'type', 'type' );
		$statuses = get_post_stati();

		foreach ( $rows as $row ) {
			if ( ! is_array( $counts[ $row['type'] ] ) ) {
				$counts[ $row['type'] ] = array_fill_keys( $statuses, 0 );
			}

			$counts[ $row['type'] ][ $row['status'] ] = bbp_number_not_negative( $row['count'] );
		}

		$counts       = (array) apply_filters( 'bbp_get_child_counts', $counts, $forum_id );
		$types        = array_flip( (array) $rpt );
		$status_lookup = array_flip( (array) $reply_statuses );
		$filtered     = array();

		foreach ( $counts as $type => $type_counts ) {
			if ( ! isset( $types[ $type ] ) ) {
				continue;
			}

			if ( ! empty( $status_lookup ) ) {
				$type_counts = array_intersect_key( $type_counts, $status_lookup );
			}

			$filtered[ $type ] = $type_counts;
		}

		$filtered = (array) apply_filters( 'bbp_filter_child_counts_list', $filtered, $forum_id, $types, $status_lookup );
		$count    = isset( $filtered[ $rpt ] )
			? bbp_number_not_negative( array_sum( array_values( $filtered[ $rpt ] ) ) )
			: 0;

		return (int) apply_filters( 'bbp_get_non_public_child_count', $count, $forum_id, $rpt );
	};
	$calculate = static function ( $forum_id ) use ( &$calculate, &$expected, &$visiting, &$error_code, $children, $forums, $forum_types, $get_direct ) {
		$forum_id = (int) apply_filters( 'bbp_get_forum_id', (int) $forum_id, $forum_id );

		if ( isset( $expected[ $forum_id ] ) ) {
			return $expected[ $forum_id ];
		}

		if ( isset( $visiting[ $forum_id ] ) || ! isset( $forums[ $forum_id ] ) ) {
			$error_code = 6;
			return false;
		}

		$visiting[ $forum_id ] = true;
		$forum_type             = isset( $forum_types[ $forum_id ] ) ? $forum_types[ $forum_id ] : 'forum';

		if ( empty( $forum_type ) ) {
			$forum_type = 'forum';
		}

		$forum_type  = apply_filters( 'bbp_get_forum_type', $forum_type, $forum_id );
		$is_category = ( ! empty( $forum_type ) && ( 'category' === $forum_type ) );
		$is_category = (bool) apply_filters( 'bbp_is_forum_category', $is_category, $forum_id );
		$direct      = $is_category ? 0 : $get_direct( $forum_id );

		if ( false === $direct ) {
			return false;
		}

		$total        = $direct;
		$subforum_ids = isset( $children[ $forum_id ] ) ? $children[ $forum_id ] : array();
		rsort( $subforum_ids, SORT_NUMERIC );
		$subforum_ids = array_map( 'absint', (array) apply_filters( 'bbp_forum_query_subforum_ids', $subforum_ids, $forum_id ) );

		foreach ( $subforum_ids as $subforum_id ) {
			$subforum = $calculate( $subforum_id );
			if ( false === $subforum ) {
				return false;
			}

			$total += (int) apply_filters( 'bbp_update_forum_reply_count_hidden', $subforum['total'], $subforum['forum_id'] );
		}

		unset( $visiting[ $forum_id ] );

		$expected[ $forum_id ] = array(
			'_bbp_reply_count_hidden'       => (string) $direct,
			'_bbp_total_reply_count_hidden' => (string) $total,
			'forum_id'                      => $forum_id,
			'total'                         => $total,
		);

		return $expected[ $forum_id ];
	};

	foreach ( $countable_forum_ids as $forum_id ) {
		if ( false === $calculate( $forum_id ) ) {
			return array( 'code' => $error_code );
		}
	}

	$repair_count = 0;
	foreach ( array_keys( $forums ) as $forum_id ) {
		$rows = isset( $stored[ $forum_id ] ) ? $stored[ $forum_id ] : array();

		if ( ! isset( $expected[ $forum_id ] ) ) {
			if ( ! empty( $rows ) ) {
				++$repair_count;
			}
			continue;
		}

		$needs_repair = ( count( $meta_keys ) !== count( $rows ) );
		foreach ( $meta_keys as $meta_key ) {
			$matches = array();
			foreach ( $rows as $row ) {
				if ( $meta_key === $row['key'] ) {
					$matches[] = $row['value'];
				}
			}

			if ( ( 1 !== count( $matches ) ) || ( reset( $matches ) !== $expected[ $forum_id ][ $meta_key ] ) ) {
				$needs_repair = true;
			}
		}

		if ( $needs_repair ) {
			++$repair_count;
		}
	}

	return array(
		'code'         => 0,
		'forum_ids'    => $countable_forum_ids,
		'repair_count' => $repair_count,
	);
}

/**
 * Repair or preview recounting non-public forum replies.
 *
 * @since 2.7.0 bbPress (r8246)
 *
 * @param bool $dry_run Whether to preview the repair without updating data.
 * @return array An array of the status code and the message.
 */
function bbp_admin_repair_forum_hidden_reply_count_process( $dry_run = false ) {

	// Define variables
	$bbp_db    = bbp_db();
	$result    = esc_html__( 'Failed!', 'bbpress' );
	$fpt       = bbp_get_forum_post_type();
	$meta_keys = array( '_bbp_reply_count_hidden', '_bbp_total_reply_count_hidden' );

	if ( $dry_run ) {
		/* translators: %s: Status of the dry-run process */
		$statement = esc_html__( 'Checking the number of pending, spammed, and trashed replies in each forum&hellip; %s', 'bbpress' );
	} else {
		/* translators: %s: Status of the counting process */
		$statement = esc_html__( 'Counting the number of pending, spammed, and trashed replies in each forum&hellip; %s', 'bbpress' );
	}

	$status = bbp_admin_repair_forum_hidden_reply_count_get_status();
	if ( ! empty( $status['code'] ) ) {
		return array(
			$status['code'],
			sprintf( $statement, $result ),
			'success' => false,
		);
	}

	$clean_cache = static function () use ( $fpt ) {
		return bbp_admin_repair_clean_post_meta_cache_batched( array( $fpt ) );
	};

	if ( empty( $status['repair_count'] ) ) {
		$result = esc_html__( '0 forum hidden reply counts to repair.', 'bbpress' );

		if ( ! $dry_run ) {
			if ( ! $clean_cache() ) {
				return array(
					7,
					sprintf( $statement, esc_html__( 'Failed!', 'bbpress' ) ),
					'success' => false,
				);
			}

			wp_cache_set( 'last_changed', microtime(), 'bbpress_posts' );
		}

		return array( 1, sprintf( $statement, $result ) );
	}

	if ( $dry_run ) {
		/* translators: %d: Number of forums whose hidden reply counts would be repaired */
		$result = sprintf( _n( 'Hidden reply counts would be repaired in %d forum.', 'Hidden reply counts would be repaired in %d forums.', $status['repair_count'], 'bbpress' ), $status['repair_count'] );

		return array( 0, sprintf( $statement, $result ) );
	}

	if ( ! $clean_cache() ) {
		return array(
			7,
			sprintf( $statement, $result ),
			'success' => false,
		);
	}

	// Delete the meta keys _bbp_reply_count_hidden and _bbp_total_reply_count_hidden for each forum
	$key_placeholders = implode( ', ', array_fill( 0, count( $meta_keys ), '%s' ) );
	$sql_delete       = $bbp_db->prepare(
		"DELETE `postmeta` FROM `{$bbp_db->postmeta}` AS `postmeta`
		INNER JOIN `{$bbp_db->posts}` AS `posts`
			ON `posts`.`ID` = `postmeta`.`post_id`
		WHERE `posts`.`post_type` = %s
			AND `postmeta`.`meta_key` IN ( {$key_placeholders} )",
		array_merge( array( $fpt ), $meta_keys )
	);

	if ( false === $bbp_db->query( $sql_delete ) ) {
		$clean_cache();

		return array(
			1,
			sprintf( $statement, $result ),
			'success' => false,
		);
	}

	wp_cache_set( 'last_changed', microtime(), 'bbpress_posts' );

	foreach ( $status['forum_ids'] as $forum_id ) {
		bbp_update_forum_reply_count_hidden( $forum_id );
	}

	if ( ! $clean_cache() ) {
		return array(
			7,
			sprintf( $statement, $result ),
			'success' => false,
		);
	}

	$status = bbp_admin_repair_forum_hidden_reply_count_get_status();
	if ( ! empty( $status['code'] ) || ! empty( $status['repair_count'] ) ) {
		return array(
			empty( $status['code'] ) ? 8 : $status['code'],
			sprintf( $statement, $result ),
			'success' => false,
		);
	}

	return array( 0, sprintf( $statement, esc_html__( 'Complete!', 'bbpress' ) ) );
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
	$state  = get_option( bbp_admin_repair_tool_batch_option_name( $tool_id ), false );
	$batch  = array(
		'code'      => 0,
		'repaired'  => 0,
		'complete'  => false,
		'restarted' => false
	);

	if ( ( false !== $state ) && ! bbp_admin_repair_tool_is_valid_checkpoint( $state ) ) {
		$state              = false;
		$batch['restarted'] = true;
	}

	// An empty status list historically matches only an empty stored status
	$statuses     = ! empty( $statuses ) ? (array) $statuses : array( '' );
	$placeholders = implode( ', ', array_fill( 0, count( $statuses ), '%s' ) );
	$source       = "FROM {$bbp_db->posts} WHERE post_type = %s AND post_status IN ({$placeholders}) AND post_author > 0";
	$source_args  = array_merge( array( $post_type ), $statuses );

	if ( false === $state ) {
		$author_id = $bbp_db->get_var( $bbp_db->prepare( "SELECT post_author {$source} ORDER BY post_author DESC LIMIT 1", $source_args ) );
		if ( ! empty( $bbp_db->last_error ) ) {
			$batch['code'] = 1;
			return $batch;
		}
		$stored_id = $bbp_db->get_var( $bbp_db->prepare( "SELECT user_id FROM {$bbp_db->usermeta} WHERE meta_key = %s ORDER BY user_id DESC LIMIT 1", $key ) );
		if ( ! empty( $bbp_db->last_error ) ) {
			$batch['code'] = 2;
			return $batch;
		}
		$state = array(
			'cursor'   => 0,
			'upper_id' => max( (int) $author_id, (int) $stored_id )
		);
		if ( ! $dry_run && ! bbp_admin_repair_tool_save_checkpoint( $tool_id, $state ) ) {
			$batch['code'] = 6;
			return $batch;
		}
	}

	$size     = bbp_admin_repair_tool_batch_size( $tool_id );
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
		$batch['code'] = 2;
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
			$batch['code'] = 1;
			return $batch;
		}

		$repair = bbp_admin_repair_count_metadata( 'user', $user_id, $key, $count > 0 ? $count : null, $size - $work, $dry_run );
		if ( ! empty( $repair['code'] ) ) {
			$batch['code'] = $repair['code'];
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
	if ( ! $dry_run && ! bbp_admin_repair_tool_save_checkpoint( $tool_id, $state, $complete ) ) {
		$batch['code'] = 6;
		return $batch;
	}
	$batch['complete'] = $complete;

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
	$statement = bbp_admin_repair_tool_restart_statement( $statement, $batch['restarted'], $dry_run );
	if ( ! empty( $batch['code'] ) ) {
		return array(
			$batch['code'],
			sprintf( $statement, esc_html__( 'Failed!', 'bbpress' ) ),
			'success'  => false,
			'complete' => false
		);
	}

	if ( $dry_run ) {
		if ( empty( $batch['repaired'] ) && $batch['complete'] ) {
			return array(
				1,
				sprintf( $statement, $no_counts ),
				'complete' => $batch['complete']
			);
		}
		if ( 'topic' === $type ) {
			/* translators: %d: Number of users whose topic counts would be repaired */
			$result = sprintf( _n( 'Topic counts would be repaired for %d user.', 'Topic counts would be repaired for %d users.', $batch['repaired'], 'bbpress' ), $batch['repaired'] );
		} else {
			/* translators: %d: Number of users whose reply counts would be repaired */
			$result = sprintf( _n( 'Reply counts would be repaired for %d user.', 'Reply counts would be repaired for %d users.', $batch['repaired'], 'bbpress' ), $batch['repaired'] );
		}
		$result = esc_html__( 'This batch:', 'bbpress' ) . "\n" . $result;
	} else {
		if ( 'topic' === $type ) {
			/* translators: %d: Number of users whose topic counts were repaired */
			$result = sprintf( _n( 'Topic counts repaired for %d user.', 'Topic counts repaired for %d users.', $batch['repaired'], 'bbpress' ), $batch['repaired'] );
		} else {
			/* translators: %d: Number of users whose reply counts were repaired */
			$result = sprintf( _n( 'Reply counts repaired for %d user.', 'Reply counts repaired for %d users.', $batch['repaired'], 'bbpress' ), $batch['repaired'] );
		}
		$status = $batch['complete'] ? esc_html__( 'Complete!', 'bbpress' ) : esc_html__( 'Batch complete!', 'bbpress' );
		$result = $status . "\n" . esc_html__( 'This batch:', 'bbpress' ) . "\n" . $result;
	}
	return array(
		0,
		sprintf( $statement, $result ),
		'complete' => $batch['complete']
	);
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
 * Repair user favorites.
 *
 * @since 2.1.0 bbPress (r3884)
 * @since 2.7.0 bbPress (r8226) Added Dry Run support.
 *
 * @return array An array of the status code and the message.
 */
function bbp_admin_repair_user_favorites() {
	return bbp_admin_repair_user_favorites_process();
}

/**
 * Preview repairing user favorites.
 *
 * @since 2.7.0 bbPress (r8226)
 *
 * @return array An array of the status code and the message.
 */
function bbp_admin_repair_user_favorites_dry_run() {
	return bbp_admin_repair_user_favorites_process( true );
}

/**
 * Repair or preview user favorites.
 *
 * @since 2.7.0 bbPress (r8226)
 *
 * @param bool $dry_run Whether to preview changes without updating data.
 * @return array An array of the status code and the message.
 */
function bbp_admin_repair_user_favorites_process( $dry_run = false ) {

	// Define variables
	$bbp_db = bbp_db();
	$result = esc_html__( 'Failed!', 'bbpress' );

	if ( $dry_run ) {
		/* translators: %s: Status of the dry-run process */
		$statement = esc_html__( 'Checking unpublished topics in user favorites&hellip; %s', 'bbpress' );
	} else {
		/* translators: %s: Status of the repair process */
		$statement = esc_html__( 'Removing unpublished topics from user favorites&hellip; %s', 'bbpress' );
	}

	// Query for users with favorites
	$key       = $bbp_db->prefix . '_bbp_favorites';
	$users     = $bbp_db->get_results( "SELECT `user_id`, `meta_value` AS `favorites` FROM `{$bbp_db->usermeta}` WHERE `meta_key` = '{$key}'" );

	if ( is_wp_error( $users ) || ! empty( $bbp_db->last_error ) ) {
		return array(
			1,
			sprintf( $statement, $result ),
			'success' => false,
		);
	}

	$sql_type   = bbp_get_topic_post_type();
	$sql_status = "'" . implode( "','", bbp_get_public_topic_statuses() ) . "'";
	$sql_select = "SELECT `ID` FROM `{$bbp_db->posts}` WHERE `post_type` = '{$sql_type}' AND `post_status` IN ({$sql_status})";

	$topics = $bbp_db->get_col( $sql_select );
	if ( is_wp_error( $topics ) || ! empty( $bbp_db->last_error ) ) {
		return array( 2, sprintf( $statement, $result ) );
	}

	$values             = array();
	$current_favorites  = array();
	$expected_favorites = array();
	foreach ( $users as $user ) {
		$user_id                         = (int) $user->user_id;
		$current_favorites[ $user_id ][] = (string) $user->favorites;

		if ( empty( $user->favorites ) || ! is_string( $user->favorites ) ) {
			continue;
		}

		$favorites = array_intersect( $topics, explode( ',', $user->favorites ) );
		if ( empty( $favorites ) || ! is_array( $favorites ) ) {
			continue;
		}

		$favorites_joined                 = implode( ',', $favorites );
		$values[]                         = "('{$user_id}', '{$key}', '{$favorites_joined}')";
		$expected_favorites[ $user_id ][] = $favorites_joined;

		// Cleanup
		unset( $favorites, $favorites_joined );
	}

	if ( ! count( $values ) ) {
		$result = $dry_run
			? esc_html__( '0 user favorites to repair.', 'bbpress' )
			: esc_html__( 'Nothing to remove!', 'bbpress' );

		return array( $dry_run ? 1 : 0, sprintf( $statement, $result ) );
	}

	if ( $dry_run ) {
		$repair_count = 0;
		$user_ids     = array_unique( array_merge( array_keys( $current_favorites ), array_keys( $expected_favorites ) ) );

		foreach ( $user_ids as $user_id ) {
			$current  = isset( $current_favorites[ $user_id ] ) ? $current_favorites[ $user_id ] : array();
			$expected = isset( $expected_favorites[ $user_id ] ) ? $expected_favorites[ $user_id ] : array();
			sort( $current, SORT_STRING );
			sort( $expected, SORT_STRING );

			if ( $current !== $expected ) {
				++$repair_count;
			}
		}

		if ( empty( $repair_count ) ) {
			$result = esc_html__( '0 user favorites to repair.', 'bbpress' );
			return array( 1, sprintf( $statement, $result ) );
		}

		/* translators: %d: Number of users whose favorites would be repaired */
		$result = sprintf( _n( 'Favorites would be repaired for %d user.', 'Favorites would be repaired for %d users.', $repair_count, 'bbpress' ), $repair_count );

		return array( 0, sprintf( $statement, $result ) );
	}

	$sql_delete = "DELETE FROM `{$bbp_db->usermeta}` WHERE `meta_key` = '{$key}'";
	if ( false === $bbp_db->query( $sql_delete ) ) {
		return array( 4, sprintf( $statement, $result ) );
	}

	$insert_error = false;
	foreach ( array_chunk( $values, 10000 ) as $chunk ) {
		$chunk = "\n" . implode( ",\n", $chunk );
		$sql_insert = "INSERT INTO `{$bbp_db->usermeta}` (`user_id`, `meta_key`, `meta_value`) VALUES {$chunk}";
		if ( false === $bbp_db->query( $sql_insert ) ) {
			$insert_error = true;
			break;
		}
	}

	// Clear cached favorites for users affected by the direct database update
	$user_ids = wp_parse_id_list( array_keys( $current_favorites ) );
	if ( ! empty( $user_ids ) ) {
		wp_cache_delete_multiple( $user_ids, 'user_meta' );
	}

	if ( $insert_error ) {
		return array( 5, sprintf( $statement, $result ) );
	}

	return array( 0, sprintf( $statement, esc_html__( 'Complete!', 'bbpress' ) ) );
}

/**
 * Clean the user topic subscriptions.
 *
 * @since 2.5.0 bbPress (r5157)
 * @since 2.7.0 bbPress (r8227) Added Dry Run support.
 *
 * @return array An array of the status code and the message.
 */
function bbp_admin_repair_user_topic_subscriptions() {
	return bbp_admin_repair_user_topic_subscriptions_process();
}

/**
 * Preview cleaning the user topic subscriptions.
 *
 * @since 2.7.0 bbPress (r8227)
 *
 * @return array An array of the status code and the message.
 */
function bbp_admin_repair_user_topic_subscriptions_dry_run() {
	return bbp_admin_repair_user_topic_subscriptions_process( true );
}

/**
 * Clean or preview the user topic subscriptions.
 *
 * @since 2.7.0 bbPress (r8227)
 *
 * @param bool $dry_run Whether to preview changes without updating data.
 * @return array An array of the status code and the message.
 */
function bbp_admin_repair_user_topic_subscriptions_process( $dry_run = false ) {

	// Define variables
	$bbp_db = bbp_db();
	$result = esc_html__( 'Failed!', 'bbpress' );

	if ( $dry_run ) {
		/* translators: %s: Status of the dry-run process */
		$statement = esc_html__( 'Checking trashed topics in user subscriptions&hellip; %s', 'bbpress' );
	} else {
		/* translators: %s: Status of the repair process */
		$statement = esc_html__( 'Removing trashed topics from user subscriptions&hellip; %s', 'bbpress' );
	}

	$key       = $bbp_db->prefix . '_bbp_subscriptions';
	$users     = $bbp_db->get_results( "SELECT `user_id`, `meta_value` AS `subscriptions` FROM `{$bbp_db->usermeta}` WHERE `meta_key` = '{$key}'" );

	if ( is_wp_error( $users ) || ! empty( $bbp_db->last_error ) ) {
		return array(
			1,
			sprintf( $statement, $result ),
			'success' => false,
		);
	}

	$sql_type    = bbp_get_topic_post_type();
	$sql_status  = "'" . implode( "','", bbp_get_public_topic_statuses() ) . "'";
	$sql_select  = "SELECT `ID` FROM `{$bbp_db->posts}` WHERE `post_type` = '{$sql_type}' AND `post_status` IN ({$sql_status})";

	$topics = $bbp_db->get_col( $sql_select );
	if ( is_wp_error( $topics ) || ! empty( $bbp_db->last_error ) ) {
		return array( 2, sprintf( $statement, $result ) );
	}

	$values                 = array();
	$current_subscriptions  = array();
	$expected_subscriptions = array();
	foreach ( $users as $user ) {
		$user_id                             = (int) $user->user_id;
		$current_subscriptions[ $user_id ][] = (string) $user->subscriptions;

		if ( empty( $user->subscriptions ) || ! is_string( $user->subscriptions ) ) {
			continue;
		}

		$subscriptions = array_intersect( $topics, explode( ',', $user->subscriptions ) );
		if ( empty( $subscriptions ) || ! is_array( $subscriptions ) ) {
			continue;
		}

		$subscriptions_joined                 = implode( ',', $subscriptions );
		$values[]                             = "('{$user_id}', '{$key}', '{$subscriptions_joined}')";
		$expected_subscriptions[ $user_id ][] = $subscriptions_joined;

		// Cleanup
		unset( $subscriptions, $subscriptions_joined );
	}

	if ( ! count( $values ) ) {
		$result = $dry_run
			? esc_html__( '0 user topic subscriptions to repair.', 'bbpress' )
			: esc_html__( 'Nothing to remove!', 'bbpress' );

		return array( $dry_run ? 1 : 0, sprintf( $statement, $result ) );
	}

	if ( $dry_run ) {
		$repair_count = 0;
		$user_ids     = array_unique( array_merge( array_keys( $current_subscriptions ), array_keys( $expected_subscriptions ) ) );

		foreach ( $user_ids as $user_id ) {
			$current  = isset( $current_subscriptions[ $user_id ] ) ? $current_subscriptions[ $user_id ] : array();
			$expected = isset( $expected_subscriptions[ $user_id ] ) ? $expected_subscriptions[ $user_id ] : array();
			sort( $current, SORT_STRING );
			sort( $expected, SORT_STRING );

			if ( $current !== $expected ) {
				++$repair_count;
			}
		}

		if ( empty( $repair_count ) ) {
			$result = esc_html__( '0 user topic subscriptions to repair.', 'bbpress' );
			return array( 1, sprintf( $statement, $result ) );
		}

		/* translators: %d: Number of users whose topic subscriptions would be repaired */
		$result = sprintf( _n( 'Topic subscriptions would be repaired for %d user.', 'Topic subscriptions would be repaired for %d users.', $repair_count, 'bbpress' ), $repair_count );

		return array( 0, sprintf( $statement, $result ) );
	}

	$sql_delete = "DELETE FROM `{$bbp_db->usermeta}` WHERE `meta_key` = '{$key}'";
	if ( false === $bbp_db->query( $sql_delete ) ) {
		return array( 4, sprintf( $statement, $result ) );
	}

	$insert_error = false;
	foreach ( array_chunk( $values, 10000 ) as $chunk ) {
		$chunk = "\n" . implode( ",\n", $chunk );
		$sql_insert = "INSERT INTO `{$bbp_db->usermeta}` (`user_id`, `meta_key`, `meta_value`) VALUES {$chunk}";
		if ( false === $bbp_db->query( $sql_insert ) ) {
			$insert_error = true;
			break;
		}
	}

	// Clear cached subscriptions for users affected by the direct database update
	$user_ids = wp_parse_id_list( array_keys( $current_subscriptions ) );
	if ( ! empty( $user_ids ) ) {
		wp_cache_delete_multiple( $user_ids, 'user_meta' );
	}

	if ( $insert_error ) {
		return array( 5, sprintf( $statement, $result ) );
	}

	return array( 0, sprintf( $statement, esc_html__( 'Complete!', 'bbpress' ) ) );
}

/**
 * Clean the user forum subscriptions.
 *
 * @since 2.5.0 bbPress (r5157)
 * @since 2.7.0 bbPress (r8228) Added Dry Run support.
 *
 * @return array An array of the status code and the message.
 */
function bbp_admin_repair_user_forum_subscriptions() {
	return bbp_admin_repair_user_forum_subscriptions_process();
}

/**
 * Preview cleaning the user forum subscriptions.
 *
 * @since 2.7.0 bbPress (r8228)
 *
 * @return array An array of the status code and the message.
 */
function bbp_admin_repair_user_forum_subscriptions_dry_run() {
	return bbp_admin_repair_user_forum_subscriptions_process( true );
}

/**
 * Clean or preview the user forum subscriptions.
 *
 * @since 2.7.0 bbPress (r8228)
 *
 * @param bool $dry_run Whether to preview changes without updating data.
 * @return array An array of the status code and the message.
 */
function bbp_admin_repair_user_forum_subscriptions_process( $dry_run = false ) {

	// Define variables
	$bbp_db = bbp_db();
	$result = esc_html__( 'Failed!', 'bbpress' );

	if ( $dry_run ) {
		/* translators: %s: Status of the dry-run process */
		$statement = esc_html__( 'Checking trashed forums in user subscriptions&hellip; %s', 'bbpress' );
	} else {
		/* translators: %s: Status of the repair process */
		$statement = esc_html__( 'Removing trashed forums from user subscriptions&hellip; %s', 'bbpress' );
	}

	$key       = $bbp_db->prefix . '_bbp_forum_subscriptions';
	$users     = $bbp_db->get_results( "SELECT `user_id`, `meta_value` AS `subscriptions` FROM `{$bbp_db->usermeta}` WHERE `meta_key` = '{$key}'" );

	if ( is_wp_error( $users ) || ! empty( $bbp_db->last_error ) ) {
		return array(
			1,
			sprintf( $statement, $result ),
			'success' => false,
		);
	}

	$sql_type    = bbp_get_forum_post_type();
	$sql_status  = "'" . implode( "','", bbp_get_public_forum_statuses() ) . "'";
	$sql_select  = "SELECT `ID` FROM `{$bbp_db->posts}` WHERE `post_type` = '{$sql_type}' AND `post_status` IN ({$sql_status})";

	$forums = $bbp_db->get_col( $sql_select );
	if ( is_wp_error( $forums ) || ! empty( $bbp_db->last_error ) ) {
		return array( 2, sprintf( $statement, $result ) );
	}

	$values                 = array();
	$current_subscriptions  = array();
	$expected_subscriptions = array();
	foreach ( $users as $user ) {
		$user_id                             = (int) $user->user_id;
		$current_subscriptions[ $user_id ][] = (string) $user->subscriptions;

		if ( empty( $user->subscriptions ) || ! is_string( $user->subscriptions ) ) {
			continue;
		}

		$subscriptions = array_intersect( $forums, explode( ',', $user->subscriptions ) );
		if ( empty( $subscriptions ) || ! is_array( $subscriptions ) ) {
			continue;
		}

		$subscriptions_joined                 = implode( ',', $subscriptions );
		$values[]                             = "('{$user_id}', '{$key}', '{$subscriptions_joined}')";
		$expected_subscriptions[ $user_id ][] = $subscriptions_joined;

		// Cleanup
		unset( $subscriptions, $subscriptions_joined );
	}

	if ( ! count( $values ) ) {
		$result = $dry_run
			? esc_html__( '0 user forum subscriptions to repair.', 'bbpress' )
			: esc_html__( 'Nothing to remove!', 'bbpress' );

		return array( $dry_run ? 1 : 0, sprintf( $statement, $result ) );
	}

	if ( $dry_run ) {
		$repair_count = 0;
		$user_ids     = array_unique( array_merge( array_keys( $current_subscriptions ), array_keys( $expected_subscriptions ) ) );

		foreach ( $user_ids as $user_id ) {
			$current  = isset( $current_subscriptions[ $user_id ] ) ? $current_subscriptions[ $user_id ] : array();
			$expected = isset( $expected_subscriptions[ $user_id ] ) ? $expected_subscriptions[ $user_id ] : array();
			sort( $current, SORT_STRING );
			sort( $expected, SORT_STRING );

			if ( $current !== $expected ) {
				++$repair_count;
			}
		}

		if ( empty( $repair_count ) ) {
			$result = esc_html__( '0 user forum subscriptions to repair.', 'bbpress' );
			return array( 1, sprintf( $statement, $result ) );
		}

		/* translators: %d: Number of users whose forum subscriptions would be repaired */
		$result = sprintf( _n( 'Forum subscriptions would be repaired for %d user.', 'Forum subscriptions would be repaired for %d users.', $repair_count, 'bbpress' ), $repair_count );

		return array( 0, sprintf( $statement, $result ) );
	}

	$sql_delete = "DELETE FROM `{$bbp_db->usermeta}` WHERE `meta_key` = '{$key}'";
	if ( false === $bbp_db->query( $sql_delete ) ) {
		return array( 4, sprintf( $statement, $result ) );
	}

	$insert_error = false;
	foreach ( array_chunk( $values, 10000 ) as $chunk ) {
		$chunk = "\n" . implode( ",\n", $chunk );
		$sql_insert = "INSERT INTO `{$bbp_db->usermeta}` (`user_id`, `meta_key`, `meta_value`) VALUES {$chunk}";
		if ( false === $bbp_db->query( $sql_insert ) ) {
			$insert_error = true;
			break;
		}
	}

	// Clear cached subscriptions for users affected by the direct database update
	$user_ids = wp_parse_id_list( array_keys( $current_subscriptions ) );
	if ( ! empty( $user_ids ) ) {
		wp_cache_delete_multiple( $user_ids, 'user_meta' );
	}

	if ( $insert_error ) {
		return array( 5, sprintf( $statement, $result ) );
	}

	return array( 0, sprintf( $statement, esc_html__( 'Complete!', 'bbpress' ) ) );
}

/**
 * This repair tool will map each user of the current site to their respective
 * forums role. By default, Admins will be Keymasters, and every other role
 * will be the default role defined in Settings > Forums (Participant).
 *
 * @since 2.2.0 bbPress (r4341)
 * @since 2.7.0 bbPress (r8229) Added Dry Run support.
 */
function bbp_admin_repair_user_roles() {
	return bbp_admin_repair_user_roles_process();
}

/**
 * Preview mapping each user of the current site to their respective forum role.
 *
 * @since 2.7.0 bbPress (r8229)
 *
 * @return array An array of the status code and the message.
 */
function bbp_admin_repair_user_roles_dry_run() {
	return bbp_admin_repair_user_roles_process( true );
}

/**
 * Map or preview mapping each user to their respective forum role.
 *
 * @since 2.7.0 bbPress (r8229)
 *
 * @param bool $dry_run Whether to preview changes without updating data.
 * @return array An array of the status code and the message.
 */
function bbp_admin_repair_user_roles_process( $dry_run = false ) {
	$changed      = 0;
	$role_map     = bbp_get_user_role_map();
	$default_role = bbp_get_default_role();

	if ( $dry_run ) {
		/* translators: %s: Status of the dry-run process */
		$statement = esc_html__( 'Checking forum role mappings for each user on this site&hellip; %s', 'bbpress' );
	} else {
		/* translators: %s: Status of the repair process */
		$statement = esc_html__( 'Remapping forum role for each user on this site&hellip; %s', 'bbpress' );
	}

	// Bail if no role map exists
	if ( empty( $role_map ) ) {
		return array( 1, sprintf( $statement, esc_html__( 'Failed!', 'bbpress' ) ) );
	}

	// Get non-forum roles
	$blog_roles = array_keys( bbp_get_blog_roles() );

	// Determine whether assigning a role would change the user's forum roles
	$blocked_role      = bbp_get_blocked_role();
	$planned_roles     = array();
	$role_needs_update = static function ( $user_id, $new_role ) use ( $blocked_role, $blog_roles, $dry_run, &$planned_roles ) {
		$user = get_userdata( $user_id );
		if ( empty( $user ) || ! bbp_is_valid_role( $new_role ) ) {
			return false;
		}

		if ( $dry_run && isset( $planned_roles[ $user_id ] ) ) {
			$forum_roles  = $planned_roles[ $user_id ];
			$current_role = ! empty( $forum_roles ) ? reset( $forum_roles ) : false;
		} else {
			$current_role = bbp_get_user_role( $user_id );
			$forum_roles  = array_values( array_intersect( $user->roles, array_keys( bbp_get_dynamic_roles() ) ) );
		}

		$blocked_clean = array( $blocked_role ) === $forum_roles;
		$needs_update = ( $current_role !== $new_role ) || ( ( $blocked_role === $new_role ) && ! $blocked_clean );

		// Simulate the role after this pass so Dry Run matches later passes in Run.
		if ( $dry_run && ( 1 < count( array_intersect( $user->roles, $blog_roles ) ) ) ) {
			if ( $blocked_role === $new_role ) {
				$forum_roles = array( $blocked_role );
			} elseif ( $current_role !== $new_role ) {
				$role_key = array_search( $current_role, $forum_roles, true );
				if ( false !== $role_key ) {
					unset( $forum_roles[ $role_key ] );
				}

				if ( ! in_array( $new_role, $forum_roles, true ) ) {
					$forum_roles[] = $new_role;
				}
			}

			$planned_roles[ $user_id ] = array_values( $forum_roles );
		}

		return $needs_update;
	};

	// Iterate through each role...
	foreach ( $blog_roles as $role ) {

		// Reset the offset
		$offset = 0;

		// If no role map exists, give the default forum role (bbp-participant)
		$new_role = isset( $role_map[ $role ] )
			? $role_map[ $role ]
			: $default_role;

		// An empty filtered role must not remove existing forum roles.
		if ( ! bbp_is_valid_role( $new_role ) ) {
			continue;
		}

		// Get users of this site, limited to 1000
		// phpcs:ignore Generic.CodeAnalysis.AssignmentInCondition.FoundInWhileCondition
		while ( $users = get_users(
			array(
				'role'   => $role,
				'fields' => 'ID',
				'number' => 1000,
				'offset' => $offset
			)
		) ) {

			// Iterate through each user of $role and try to set it
			foreach ( (array) $users as $user_id ) {
				$needs_update = $role_needs_update( $user_id, $new_role );
				if ( ! $dry_run ) {
					bbp_set_user_role( $user_id, $new_role );
				}

				if ( $needs_update ) {
					++$changed;
				}
			}

			// Bump the offset for the next query iteration
			$offset = $offset + 1000;
		}
	}

	// Reset the offset
	$offset  = 0;
	$bbp_db  = bbp_db();
	$cap_key = $bbp_db->get_blog_prefix() . 'capabilities';

	// Users without roles should be granted the default role, but not on multi-
	// site installations where not all users get a role by default.
	if ( ! is_multisite() && bbp_is_valid_role( $default_role ) ) {

		// Get users with missing capabilities on this site, limited to 1000
		// phpcs:ignore Generic.CodeAnalysis.AssignmentInCondition.FoundInWhileCondition
		while ( $users = get_users(
			array(
				'meta_key'     => $cap_key,
				'meta_compare' => 'NOT EXISTS',
				'fields'       => 'ID',
				'number'       => 1000,
				'offset'       => $offset
			)
		) ) {
			$remaining = 0;

			// Iterate through each user of $role and try to set it
			foreach ( (array) $users as $user_id ) {
				$needs_update = $role_needs_update( $user_id, $default_role );
				if ( ! $dry_run ) {
					bbp_set_user_role( $user_id, $default_role );
				}

				if ( $needs_update ) {
					++$changed;
				}

				if ( ! $dry_run && ! metadata_exists( 'user', $user_id, $cap_key ) ) {
					++$remaining;
				}
			}

			// Run skips rows that remain, while Dry Run pages through all rows.
			$offset = $dry_run ? $offset + 1000 : $offset + $remaining;
		}

	// On multisite, we'll look for users with an empty capabilities array.
	// These are users who basically have malformed caps, and we can fix that.
	} elseif ( is_multisite() && bbp_is_valid_role( $default_role ) ) {

		// Get users with empty capabilities on this site, limited to 1000
		// phpcs:ignore Generic.CodeAnalysis.AssignmentInCondition.FoundInWhileCondition
		while ( $users = get_users(
			array(
				'meta_key'   => $cap_key,
				'meta_value' => 'a:0:{}',
				'fields'     => 'ID',
				'number'     => 1000,
				'offset'     => $offset
			)
		) ) {
			$remaining = 0;

			// Iterate through each user of $role and try to set it
			foreach ( (array) $users as $user_id ) {
				$needs_update = $role_needs_update( $user_id, $default_role );
				if ( ! $dry_run ) {
					bbp_set_user_role( $user_id, $default_role );
				}

				if ( $needs_update ) {
					++$changed;
				}

				if ( ! $dry_run && ( array() === get_user_meta( $user_id, $cap_key, true ) ) ) {
					++$remaining;
				}
			}

			// Run skips rows that remain, while Dry Run pages through all rows.
			$offset = $dry_run ? $offset + 1000 : $offset + $remaining;
		}
	}

	if ( $dry_run ) {
		if ( empty( $changed ) ) {
			return array( 1, sprintf( $statement, esc_html__( '0 user forum roles to repair.', 'bbpress' ) ) );
		}

		/* translators: %s: Number of users who would be assigned the default forum role */
		$result = sprintf( _n( '%s user would be assigned the default forum role.', '%s users would be assigned the default forum role.', $changed, 'bbpress' ), bbp_number_format( $changed ) );
	} else {
		/* translators: %s: Number of users assigned the default forum role */
		$result = sprintf( _n( 'Complete! %s user was assigned the default forum role.', 'Complete! %s users were assigned the default forum role.', $changed, 'bbpress' ), bbp_number_format( $changed ) );
	}

	return array( 0, sprintf( $statement, $result ) );
}

/**
 * Repair the last post in every topic and forum.
 *
 * @since 2.1.0 bbPress (r3884)
 * @since 2.7.0 bbPress (r8241) Added Dry Run support.
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
 *
 * @return array An array of the status code and the message.
 */
function bbp_admin_repair_freshness_dry_run() {
	return bbp_admin_repair_freshness_process( true );
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

	// Define variables
	$bbp_db           = bbp_db();
	$failed           = esc_html__( 'Failed!', 'bbpress' );
	$fpt              = bbp_get_forum_post_type();
	$tpt              = bbp_get_topic_post_type();
	$rpt              = bbp_get_reply_post_type();
	$pps              = bbp_get_public_status_id();
	$keys             = array( '_bbp_last_reply_id', '_bbp_last_topic_id', '_bbp_last_active_id', '_bbp_last_active_time' );
	$key_placeholders = implode( ', ', array_fill( 0, count( $keys ), '%s' ) );

	if ( $dry_run ) {
		/* translators: %s: Status of the dry-run process */
		$statement = esc_html__( 'Checking latest post in every topic and forum&hellip; %s', 'bbpress' );
	} else {
		/* translators: %s: Status of the repair process */
		$statement = esc_html__( 'Recomputing latest post in every topic and forum&hellip; %s', 'bbpress' );
	}

	$topic_count = $bbp_db->get_var( $bbp_db->prepare( "SELECT COUNT(*) FROM `{$bbp_db->posts}` WHERE `post_type` = %s", $tpt ) );
	if ( ! empty( $bbp_db->last_error ) ) {
		return array(
			11,
			sprintf( $statement, $failed ),
			'success' => false
		);
	}

	$forum_count = $bbp_db->get_var( $bbp_db->prepare( "SELECT COUNT(*) FROM `{$bbp_db->posts}` WHERE `post_type` = %s", $fpt ) );
	if ( ! empty( $bbp_db->last_error ) ) {
		return array(
			12,
			sprintf( $statement, $failed ),
			'success' => false
		);
	}

	$stale_count = $bbp_db->get_var(
		$bbp_db->prepare(
			"SELECT COUNT( `freshness`.`meta_id` )
			FROM `{$bbp_db->postmeta}` AS `freshness`
			LEFT JOIN `{$bbp_db->posts}` AS `content`
				ON `freshness`.`post_id` = `content`.`ID`
				AND `content`.`post_type` IN ( %s, %s )
			WHERE `freshness`.`meta_key` IN ( {$key_placeholders} )
				AND `content`.`ID` IS NULL",
			array_merge( array( $fpt, $tpt ), $keys )
		)
	);
	if ( ! empty( $bbp_db->last_error ) ) {
		return array(
			13,
			sprintf( $statement, $failed ),
			'success' => false
		);
	}

	$topic_count = (int) $topic_count;
	$forum_count = (int) $forum_count;
	$stale_count = (int) $stale_count;

	if ( empty( $topic_count ) && empty( $forum_count ) && empty( $stale_count ) ) {
		$result = esc_html__( '0 topic or forum freshness records to recalculate.', 'bbpress' );
		return array( 1, sprintf( $statement, $result ) );
	}

	$topic_result = sprintf(
		/* translators: %s: Number of topics */
		esc_html( _n( '%s topic would be recalculated.', '%s topics would be recalculated.', $topic_count, 'bbpress' ) ),
		bbp_number_format( $topic_count )
	);
	$forum_result = sprintf(
		/* translators: %s: Number of forums */
		esc_html( _n( '%s forum would be recalculated.', '%s forums would be recalculated.', $forum_count, 'bbpress' ) ),
		bbp_number_format( $forum_count )
	);
	$stale_result = sprintf(
		/* translators: %s: Number of orphaned freshness rows */
		esc_html( _n( '%s orphaned freshness row would be removed.', '%s orphaned freshness rows would be removed.', $stale_count, 'bbpress' ) ),
		bbp_number_format( $stale_count )
	);

	if ( $dry_run ) {
		$result = $topic_result . "\n" . $forum_result . "\n" . $stale_result;

		return array( 0, sprintf( $statement, $result ) );
	}

	$forums = $bbp_db->get_col(
		$bbp_db->prepare(
			"SELECT `ID` FROM `{$bbp_db->posts}` WHERE `post_type` = %s AND `post_status` != %s",
			$fpt,
			'auto-draft'
		)
	);
	if ( ! empty( $bbp_db->last_error ) ) {
		return array(
			10,
			sprintf( $statement, $failed ),
			'success' => false
		);
	}

	$clean_cache = static function () use ( $fpt, $tpt ) {
		return bbp_admin_repair_clean_post_meta_cache_batched( array( $fpt, $tpt ) );
	};

	if ( ! $clean_cache() ) {
		return array(
			14,
			sprintf( $statement, $failed ),
			'success' => false
		);
	}

	$orphan_result = bbp_admin_repair_delete_orphaned_freshness_batched( array( $fpt, $tpt ), $keys );
	if ( ! empty( $orphan_result ) ) {
		return array(
			$orphan_result,
			sprintf( $statement, $failed ),
			'success' => false
		);
	}

	$delete_args  = array_merge( $keys, array( $fpt, $tpt ) );
	$delete_query = $bbp_db->prepare(
		"DELETE `freshness`
		FROM `{$bbp_db->postmeta}` AS `freshness`
		INNER JOIN `{$bbp_db->posts}` AS `content`
			ON `freshness`.`post_id` = `content`.`ID`
		WHERE `freshness`.`meta_key` IN ( {$key_placeholders} )
			AND `content`.`post_type` IN ( %s, %s )",
		$delete_args
	);

	if ( false === $bbp_db->query( $delete_query ) ) {
		$clean_cache();

		return array(
			1,
			sprintf( $statement, $failed ),
			'success' => false
		);
	}

	$queries = array(
		2 => $bbp_db->prepare(
			"INSERT INTO `{$bbp_db->postmeta}` (`post_id`, `meta_key`, `meta_value`)
			( SELECT `topic`.`ID`, '_bbp_last_reply_id', MAX( `reply`.`ID` )
			FROM `{$bbp_db->posts}` AS `topic` INNER JOIN `{$bbp_db->posts}` AS `reply` ON `topic`.`ID` = `reply`.`post_parent`
			WHERE `reply`.`post_status` = %s AND `topic`.`post_type` = %s AND `reply`.`post_type` = %s
			GROUP BY `topic`.`ID` )",
			$pps,
			$tpt,
			$rpt
		),
		3 => $bbp_db->prepare(
			"INSERT INTO `{$bbp_db->postmeta}` (`post_id`, `meta_key`, `meta_value`)
			( SELECT `ID`, '_bbp_last_reply_id', 0
			FROM `{$bbp_db->posts}` AS `topic` LEFT JOIN `{$bbp_db->postmeta}` AS `reply`
			ON `topic`.`ID` = `reply`.`post_id` AND `reply`.`meta_key` = '_bbp_last_reply_id'
			WHERE `reply`.`meta_id` IS NULL AND `topic`.`post_type` = %s )",
			$tpt
		),
		4 => $bbp_db->prepare(
			"INSERT INTO `{$bbp_db->postmeta}` (`post_id`, `meta_key`, `meta_value`)
			( SELECT `forum`.`ID`, '_bbp_last_topic_id', MAX( `topic`.`ID` )
			FROM `{$bbp_db->posts}` AS `forum` INNER JOIN `{$bbp_db->posts}` AS `topic` ON `forum`.`ID` = `topic`.`post_parent`
			WHERE `topic`.`post_status` = %s AND `forum`.`post_type` = %s AND `topic`.`post_type` = %s
			GROUP BY `forum`.`ID` )",
			$pps,
			$fpt,
			$tpt
		),
		5 => $bbp_db->prepare(
			"INSERT INTO `{$bbp_db->postmeta}` (`post_id`, `meta_key`, `meta_value`)
			( SELECT `ID`, '_bbp_last_topic_id', 0
			FROM `{$bbp_db->posts}` AS `forum` LEFT JOIN `{$bbp_db->postmeta}` AS `topic`
			ON `forum`.`ID` = `topic`.`post_id` AND `topic`.`meta_key` = '_bbp_last_topic_id'
			WHERE `topic`.`meta_id` IS NULL AND `forum`.`post_type` = %s )",
			$fpt
		),
		6 => $bbp_db->prepare(
			"INSERT INTO `{$bbp_db->postmeta}` (`post_id`, `meta_key`, `meta_value`)
			( SELECT `topic`.`ID`, '_bbp_last_active_id', MAX( `reply`.`ID` )
			FROM `{$bbp_db->posts}` AS `topic` INNER JOIN `{$bbp_db->posts}` AS `reply` ON `topic`.`ID` = `reply`.`post_parent`
			WHERE `reply`.`post_status` = %s AND `topic`.`post_type` = %s AND `reply`.`post_type` = %s
			GROUP BY `topic`.`ID` )",
			$pps,
			$tpt,
			$rpt
		),
		7 => $bbp_db->prepare(
			"INSERT INTO `{$bbp_db->postmeta}` (`post_id`, `meta_key`, `meta_value`)
			( SELECT `ID`, '_bbp_last_active_id', `ID`
			FROM `{$bbp_db->posts}` AS `topic` LEFT JOIN `{$bbp_db->postmeta}` AS `reply`
			ON `topic`.`ID` = `reply`.`post_id` AND `reply`.`meta_key` = '_bbp_last_active_id'
			WHERE `reply`.`meta_id` IS NULL AND `topic`.`post_type` = %s )",
			$tpt
		),
		8 => $bbp_db->prepare(
			"INSERT INTO `{$bbp_db->postmeta}` (`post_id`, `meta_key`, `meta_value`)
			( SELECT `topic`.`ID`, '_bbp_last_active_time', MAX( `reply`.`post_date` )
			FROM `{$bbp_db->posts}` AS `topic` INNER JOIN `{$bbp_db->posts}` AS `reply` ON `topic`.`ID` = `reply`.`post_parent`
			WHERE `reply`.`post_status` = %s AND `topic`.`post_type` = %s AND `reply`.`post_type` = %s
			GROUP BY `topic`.`ID` )",
			$pps,
			$tpt,
			$rpt
		),
		9 => $bbp_db->prepare(
			"INSERT INTO `{$bbp_db->postmeta}` (`post_id`, `meta_key`, `meta_value`)
			( SELECT `ID`, '_bbp_last_active_time', `post_date`
			FROM `{$bbp_db->posts}` AS `topic` LEFT JOIN `{$bbp_db->postmeta}` AS `reply`
			ON `topic`.`ID` = `reply`.`post_id` AND `reply`.`meta_key` = '_bbp_last_active_time'
			WHERE `reply`.`meta_id` IS NULL AND `topic`.`post_type` = %s )",
			$tpt
		),
	);

	foreach ( $queries as $code => $query ) {
		if ( false === $bbp_db->query( $query ) ) {
			$clean_cache();

			return array(
				$code,
				sprintf( $statement, $failed ),
				'success' => false
			);
		}
	}

	// Loop through forums
	foreach ( $forums as $forum_id ) {
		if ( ! bbp_is_forum_category( $forum_id ) ) {
			bbp_update_forum( array( 'forum_id' => $forum_id ) );
		}
	}

	// Loop through categories when forums are done
	foreach ( $forums as $forum_id ) {
		if ( bbp_is_forum_category( $forum_id ) ) {
			bbp_update_forum( array( 'forum_id' => $forum_id ) );
		}
	}

	if ( ! $clean_cache() ) {
		return array(
			14,
			sprintf( $statement, $failed ),
			'success' => false
		);
	}

	$topic_result = sprintf(
		/* translators: %s: Number of topics */
		esc_html( _n( '%s topic recalculated.', '%s topics recalculated.', $topic_count, 'bbpress' ) ),
		bbp_number_format( $topic_count )
	);
	$forum_result = sprintf(
		/* translators: %s: Number of forums */
		esc_html( _n( '%s forum recalculated.', '%s forums recalculated.', $forum_count, 'bbpress' ) ),
		bbp_number_format( $forum_count )
	);
	$stale_result = sprintf(
		/* translators: %s: Number of orphaned freshness rows */
		esc_html( _n( '%s orphaned freshness row removed.', '%s orphaned freshness rows removed.', $stale_count, 'bbpress' ) ),
		bbp_number_format( $stale_count )
	);
	$result = esc_html__( 'Complete!', 'bbpress' ) . "\n" . $topic_result . "\n" . $forum_result . "\n" . $stale_result;

	return array( 0, sprintf( $statement, $result ) );
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
 * Repair or preview sticky topic assignments.
 *
 * @since 2.7.0 bbPress (r8219)
 *
 * @param bool $dry_run Whether to preview changes without updating metadata.
 * @return array An array of the status code and the message.
 */
function bbp_admin_repair_sticky_process( $dry_run = false ) {

	// Define variables
	$bbp_db    = bbp_db();
	$result    = esc_html__( 'Failed!', 'bbpress' );
	$changed   = 0;
	$topic_ids = array();

	if ( $dry_run ) {
		/* translators: %s: Status of the dry-run process */
		$statement = esc_html__( 'Checking sticky topic assignments&hellip; %s', 'bbpress' );
	} else {
		/* translators: %s: Status of the repair process */
		$statement = esc_html__( 'Recalculating sticky topic assignments&hellip; %s', 'bbpress' );
	}

	$forums = $bbp_db->get_col( "SELECT ID FROM `{$bbp_db->posts}` WHERE `post_type` = '" . bbp_get_forum_post_type() . "'" );

	// Bail if no forums found
	if ( empty( $forums ) || is_wp_error( $forums ) ) {
		return array( 1, sprintf( $statement, $result ) );
	}

	// Ensure Run and Dry Run inspect current forum metadata
	wp_cache_delete_multiple( wp_parse_id_list( $forums ), 'post_meta' );

	// Loop through forums and get their sticky topics
	foreach ( $forums as $forum ) {
		$forum_stickies[ $forum ] = get_post_meta( $forum, '_bbp_sticky_topics', true );

		if ( is_array( $forum_stickies[ $forum ] ) ) {
			$topic_ids = array_merge( $topic_ids, $forum_stickies[ $forum ] );
		}
	}

	// Cleanup
	unset( $forums, $forum );

	// Ensure Run and Dry Run inspect current topic data and metadata
	$topic_ids = wp_parse_id_list( $topic_ids );
	if ( ! empty( $topic_ids ) ) {
		wp_cache_delete_multiple( $topic_ids, 'posts' );
		wp_cache_delete_multiple( $topic_ids, 'post_meta' );
	}

	// Get current super-sticky topics without relying on the options cache
	$option_name    = '_bbp_super_sticky_topics';
	$option_default = array();
	$super_stickies = apply_filters( "pre_option_{$option_name}", false, $option_name, $option_default );

	// The generic pre-option filter was introduced in WordPress 6.1
	if ( version_compare( get_bloginfo( 'version' ), '6.1', '>=' ) ) {
		$super_stickies = apply_filters( 'pre_option', $super_stickies, $option_name, $option_default );
	}

	// Get the stored value when the pre-option filters do not short-circuit
	if ( false === $super_stickies ) {
		$option = $bbp_db->get_row(
			$bbp_db->prepare(
				"SELECT option_value FROM `{$bbp_db->options}` WHERE option_name = %s LIMIT 1",
				$option_name
			)
		);

		if ( ! empty( $bbp_db->last_error ) ) {
			return array( 2, sprintf( $statement, $result ) );
		}

		$super_stickies = is_object( $option )
			? apply_filters( "option_{$option_name}", maybe_unserialize( $option->option_value ), $option_name )
			: apply_filters( "default_option_{$option_name}", $option_default, $option_name, true );
	}

	$super_stickies = ( empty( $super_stickies ) || ! is_array( $super_stickies ) )
		? array()
		: wp_parse_id_list( $super_stickies );
	$super_stickies = (array) apply_filters( 'bbp_get_super_stickies', $super_stickies );

	// Loop through each forum with sticky topics
	foreach ( $forum_stickies as $forum_id => $stickies ) {

		// Skip if no stickies
		if ( empty( $stickies ) ) {
			continue;
		}

		// Normalize malformed sticky metadata
		if ( ! is_array( $stickies ) ) {
			$forum_stickies[ $forum_id ] = array();
			++$changed;
		} else {

			// Loop through each sticky topic
			foreach ( $stickies as $id => $topic_id ) {
				$topic_id        = bbp_get_topic_id( $topic_id );
				$is_super_sticky = in_array( $topic_id, $super_stickies, true );
				$is_super_sticky = (bool) apply_filters( 'bbp_is_topic_super_sticky', $is_super_sticky, $topic_id );

				// If the topic is not a super sticky, and the forum ID does not
				// match the topic's forum ID, unset the forum's sticky meta.
				if ( ! $is_super_sticky && ( bbp_get_topic_forum_id( $topic_id ) ) !== $forum_id ) {
					unset( $forum_stickies[ $forum_id ][ $id ] );
					++$changed;
				}
			}
		}

		// Get sticky topic ID's, or use empty string
		$stickers = ! empty( $forum_stickies[ $forum_id ] )
			? array_values( $forum_stickies[ $forum_id ] )
			: '';

		// Update the forum's sticky topics meta
		if ( ! $dry_run ) {
			update_post_meta( $forum_id, '_bbp_sticky_topics', $stickers );
		}
	}

	// Complete results
	if ( $dry_run ) {
		/* translators: %d: Number of sticky topic assignments that would be repaired */
		$result = sprintf( _n( '%d sticky topic assignment would be repaired.', '%d sticky topic assignments would be repaired.', $changed, 'bbpress' ), $changed );
	} else {
		/* translators: %d: Number of repaired sticky topic assignments */
		$result = sprintf( _n( 'Complete! %d sticky topic assignment repaired.', 'Complete! %d sticky topic assignments repaired.', $changed, 'bbpress' ), $changed );
	}

	return array( 0, sprintf( $statement, $result ) );
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
 * Repair or preview closed topics.
 *
 * @since 2.7.0 bbPress (r8218)
 * @since 2.7.0 bbPress (r8254) Safely restart malformed checkpoints.
 *
 * @param bool $dry_run Whether to preview changes without updating metadata.
 * @return array An array of the status code and the message.
 */
function bbp_admin_repair_closed_topics_process( $dry_run = false ) {

	// Define variables
	$bbp_db  = bbp_db();
	$tool_id = 'bbp-sync-closed-topics';
	$option  = bbp_admin_repair_tool_batch_option_name( $tool_id );
	$state   = get_option( $option, false );
	$result  = esc_html__( '0 closed topics to repair.', 'bbpress' );
	$failed  = esc_html__( 'Failed!', 'bbpress' );
	$changed = 0;

	if ( $dry_run ) {
		/* translators: %s: Status of the dry-run process */
		$statement = esc_html__( 'Checking closed topics&hellip; %s', 'bbpress' );
	} else {
		/* translators: %s: Status of the repair process */
		$statement = esc_html__( 'Repairing closed topics&hellip; %s', 'bbpress' );
	}

	$restarted = ( false !== $state ) && ! bbp_admin_repair_tool_is_valid_checkpoint( $state );
	$statement = bbp_admin_repair_tool_restart_statement( $statement, $restarted, $dry_run );
	if ( $restarted ) {
		$state = false;
	}

	if ( false === $state ) {
		$upper_id = $bbp_db->get_var( $bbp_db->prepare( "SELECT ID FROM {$bbp_db->posts} WHERE post_type = %s AND post_status = %s ORDER BY ID DESC LIMIT 1", bbp_get_topic_post_type(), bbp_get_closed_status_id() ) );
		if ( ! empty( $bbp_db->last_error ) ) {
			return array(
				2,
				sprintf( $statement, $failed ),
				'success' => false
			);
		}
		$state = array(
			'cursor'   => 0,
			'upper_id' => (int) $upper_id
		);
	}

	// Inspect one extra ID to determine whether another bounded batch remains
	$size          = bbp_admin_repair_tool_batch_size( $tool_id );
	$closed_topics = $bbp_db->get_col( $bbp_db->prepare( "SELECT ID FROM {$bbp_db->posts} WHERE post_type = %s AND post_status = %s AND ID > %d AND ID <= %d ORDER BY ID LIMIT %d", bbp_get_topic_post_type(), bbp_get_closed_status_id(), $state['cursor'], $state['upper_id'], $size + 1 ) );
	if ( ! empty( $bbp_db->last_error ) ) {
		return array(
			2,
			sprintf( $statement, $failed ),
			'success' => false
		);
	}
	$complete      = count( $closed_topics ) <= $size;
	$closed_topics = array_slice( $closed_topics, 0, $size );

	// Ensure Run and Dry Run inspect current database values
	wp_cache_delete_multiple( wp_parse_id_list( $closed_topics ), 'post_meta' );

	// Loop through each closed topic
	foreach ( $closed_topics as $closed_topic ) {

		// Check if the closed topic already has a postmeta _bbp_status value
		$topic_status = get_post_meta( $closed_topic, '_bbp_status', true );
		if ( ! empty( $bbp_db->last_error ) ) {
			return array(
				3,
				sprintf( $statement, $failed ),
				'success' => false
			);
		}

		// If we don't have a postmeta _bbp_status value
		if ( empty( $topic_status ) ) {
			if ( ! $dry_run ) {
				if ( false === update_post_meta( $closed_topic, '_bbp_status', 'publish' ) ) {
					return array(
						4,
						sprintf( $statement, $failed ),
						'success' => false
					);
				}
			}

			++$changed; // Keep a count to display at the end
		}
	}

	// Dry Run never advances or removes the saved Run checkpoint
	if ( ! $dry_run ) {
		if ( ! $complete ) {
			$state['cursor'] = (int) end( $closed_topics );
		}
		if ( ! bbp_admin_repair_tool_save_checkpoint( $tool_id, $state, $complete ) ) {
			return array(
				5,
				sprintf( $statement, $failed ),
				'success' => false
			);
		}
	}

	if ( empty( $closed_topics ) ) {
		return array( 1, sprintf( $statement, $result ) );
	}

	// Complete results
	if ( $dry_run ) {
		/* translators: %d: Number of closed topics that would be repaired */
		$result = sprintf( _n( '%d closed topic would be repaired in this batch.', '%d closed topics would be repaired in this batch.', $changed, 'bbpress' ), $changed );
	} else {
		/* translators: %d: Number of repaired topics */
		$result = sprintf( _n( '%d closed topic repaired.', '%d closed topics repaired.', $changed, 'bbpress' ), $changed );
		$status = $complete
			? esc_html__( 'Complete!', 'bbpress' )
			: esc_html__( 'Batch complete!', 'bbpress' );
		$result = $status . "\n" . esc_html__( 'This batch:', 'bbpress' ) . "\n" . $result;
	}

	return array(
		0,
		sprintf( $statement, $result ),
		'complete' => $complete
	);
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
 * Repair or preview repairing the private and hidden forum options.
 *
 * @since 2.7.0 bbPress (r8240)
 *
 * @param bool $dry_run Whether to preview changes without updating options.
 * @return array An array of the status code and the message.
 */
function bbp_admin_repair_forum_visibility_process( $dry_run = false ) {
	$bbp_db = bbp_db();
	$failed = esc_html__( 'Failed!', 'bbpress' );
	$fmt    = bbp_get_forum_post_type();

	if ( $dry_run ) {
		/* translators: %s: Status of the dry-run process */
		$statement = esc_html__( 'Checking forum visibility&hellip; %s', 'bbpress' );
	} else {
		/* translators: %s: Status of the repair process */
		$statement = esc_html__( 'Recalculating forum visibility&hellip; %s', 'bbpress' );
	}

	$private_forums = $bbp_db->get_col(
		$bbp_db->prepare(
			"SELECT `ID` FROM `{$bbp_db->posts}` WHERE `post_type` = %s AND `post_status` = %s ORDER BY `ID`",
			$fmt,
			bbp_get_private_status_id()
		)
	);
	if ( ! empty( $bbp_db->last_error ) ) {
		return array(
			2,
			sprintf( $statement, $failed ),
			'success' => false,
		);
	}

	$hidden_forums = $bbp_db->get_col(
		$bbp_db->prepare(
			"SELECT `ID` FROM `{$bbp_db->posts}` WHERE `post_type` = %s AND `post_status` = %s ORDER BY `ID`",
			$fmt,
			bbp_get_hidden_status_id()
		)
	);
	if ( ! empty( $bbp_db->last_error ) ) {
		return array(
			3,
			sprintf( $statement, $failed ),
			'success' => false,
		);
	}

	$private_forums = wp_parse_id_list( $private_forums );
	$hidden_forums  = wp_parse_id_list( $hidden_forums  );

	$read_option = static function ( $option_name, $option_default ) use ( $bbp_db ) {
		$option_value = apply_filters( "pre_option_{$option_name}", false, $option_name, $option_default );

		// The generic pre-option filter was introduced in WordPress 6.1
		if ( version_compare( get_bloginfo( 'version' ), '6.1', '>=' ) ) {
			$option_value = apply_filters( 'pre_option', $option_value, $option_name, $option_default );
		}

		if ( false !== $option_value ) {
			return array( true, $option_value );
		}

		$option = $bbp_db->get_row(
			$bbp_db->prepare(
				"SELECT `option_value` FROM `{$bbp_db->options}` WHERE `option_name` = %s LIMIT 1",
				$option_name
			)
		);

		if ( ! empty( $bbp_db->last_error ) ) {
			return array( false, $option_default );
		}

		$option_value = is_object( $option )
			? apply_filters( "option_{$option_name}", maybe_unserialize( $option->option_value ), $option_name )
			: apply_filters( "default_option_{$option_name}", $option_default, $option_name, true );

		return array( true, $option_value );
	};

	list( $private_read, $stored_private ) = $read_option( '_bbp_private_forums', array() );
	if ( ! $private_read ) {
		return array(
			4,
			sprintf( $statement, $failed ),
			'success' => false,
		);
	}

	list( $hidden_read, $stored_hidden ) = $read_option( '_bbp_hidden_forums', array() );
	if ( ! $hidden_read ) {
		return array(
			5,
			sprintf( $statement, $failed ),
			'success' => false,
		);
	}

	$clear_option_cache = static function ( $option_name ) {
		wp_cache_delete( 'alloptions', 'options' );
		wp_cache_delete( 'notoptions', 'options' );
		wp_cache_delete( $option_name, 'options' );
	};

	$write_option = static function ( $option_name, $option_value ) use ( $bbp_db, $clear_option_cache ) {
		$clear_option_cache( $option_name );
		update_option( $option_name, $option_value );

		$option = $bbp_db->get_row(
			$bbp_db->prepare(
				"SELECT `option_value` FROM `{$bbp_db->options}` WHERE `option_name` = %s LIMIT 1",
				$option_name
			)
		);

		if ( ! empty( $bbp_db->last_error ) || ! is_object( $option ) || ( maybe_unserialize( $option->option_value ) !== $option_value ) ) {
			return false;
		}

		$clear_option_cache( $option_name );
		return true;
	};

	$change_count = static function ( $stored, $expected ) {
		$is_default = ( '' === $stored );
		$valid      = $is_default || ( is_array( $stored ) && ( array_values( $stored ) === $stored ) );
		$stored_ids = is_array( $stored )
			? bbp_get_unique_array_values( wp_parse_id_list( $stored ) )
			: array();
		$changes    = array_unique( array_merge( array_diff( $stored_ids, $expected ), array_diff( $expected, $stored_ids ) ) );
		$count      = count( $changes );

		if ( $valid && ! $is_default ) {
			foreach ( $stored as $forum_id ) {
				if ( ! is_int( $forum_id ) || ( $forum_id < 1 ) ) {
					$valid = false;
					break;
				}
			}
		}

		if ( $valid && ! $is_default && ( count( $stored ) !== count( $stored_ids ) ) ) {
			$valid = false;
		}

		// Include malformed or duplicate option values, but ignore ID order
		if ( empty( $count ) && ! $valid ) {
			$count = 1;
		}

		return $count;
	};

	$private_changes = $change_count( $stored_private, $private_forums );
	$hidden_changes  = $change_count( $stored_hidden,  $hidden_forums  );

	if ( empty( $private_changes ) && empty( $hidden_changes ) ) {
		if ( ! $dry_run ) {
			$clear_option_cache( '_bbp_private_forums' );
			$clear_option_cache( '_bbp_hidden_forums'  );
		}

		$result = esc_html__( '0 forum visibility entries to repair.', 'bbpress' );
		return array( 1, sprintf( $statement, $result ) );
	}

	$private_result = sprintf(
		/* translators: %s: Number of private forum visibility entries */
		esc_html( _n( '%s private forum visibility entry would be repaired.', '%s private forum visibility entries would be repaired.', $private_changes, 'bbpress' ) ),
		bbp_number_format( $private_changes )
	);
	$hidden_result = sprintf(
		/* translators: %s: Number of hidden forum visibility entries */
		esc_html( _n( '%s hidden forum visibility entry would be repaired.', '%s hidden forum visibility entries would be repaired.', $hidden_changes, 'bbpress' ) ),
		bbp_number_format( $hidden_changes )
	);

	if ( $dry_run ) {
		$result = $private_result . "\n" . $hidden_result;

		return array( 0, sprintf( $statement, $result ) );
	}

	if ( ! empty( $private_changes ) ) {
		if ( ! $write_option( '_bbp_private_forums', $private_forums ) ) {
			return array(
				6,
				sprintf( $statement, $failed ),
				'success' => false,
			);
		}
	}

	if ( ! empty( $hidden_changes ) ) {
		if ( ! $write_option( '_bbp_hidden_forums', $hidden_forums ) ) {
			return array(
				7,
				sprintf( $statement, $failed ),
				'success' => false,
			);
		}
	}

	$private_result = sprintf(
		/* translators: %s: Number of private forum visibility entries */
		esc_html( _n( '%s private forum visibility entry repaired.', '%s private forum visibility entries repaired.', $private_changes, 'bbpress' ) ),
		bbp_number_format( $private_changes )
	);
	$hidden_result = sprintf(
		/* translators: %s: Number of hidden forum visibility entries */
		esc_html( _n( '%s hidden forum visibility entry repaired.', '%s hidden forum visibility entries repaired.', $hidden_changes, 'bbpress' ) ),
		bbp_number_format( $hidden_changes )
	);
	$result = esc_html__( 'Complete!', 'bbpress' ) . "\n" . $private_result . "\n" . $hidden_result;

	return array( 0, sprintf( $statement, $result ) );
}

/**
 * Repair the parent forum reference for each forum, topic, and reply.
 *
 * @since 2.1.0 bbPress (r3884)
 * @since 2.7.0 bbPress (r8239) Added Dry Run support.
 *
 * @return array An array of the status code and the message.
 */
function bbp_admin_repair_forum_meta() {
	return bbp_admin_repair_forum_meta_process();
}

/**
 * Preview repairing the parent forum reference for each forum, topic, and reply.
 *
 * @since 2.7.0 bbPress (r8239)
 *
 * @return array An array of the status code and the message.
 */
function bbp_admin_repair_forum_meta_dry_run() {
	return bbp_admin_repair_forum_meta_process( true );
}

/**
 * Repair or preview repairing the parent forum reference for each post.
 *
 * @since 2.7.0 bbPress (r8239)
 *
 * @param bool $dry_run Whether to preview changes without updating data.
 * @return array An array of the status code and the message.
 */
function bbp_admin_repair_forum_meta_process( $dry_run = false ) {

	// Define variables
	$bbp_db = bbp_db();
	$failed = esc_html__( 'Failed!', 'bbpress' );
	$tpt    = bbp_get_topic_post_type();
	$rpt    = bbp_get_reply_post_type();
	$fmt    = bbp_get_forum_post_type();

	if ( $dry_run ) {
		/* translators: %s: Status of the dry-run process */
		$statement = esc_html__( 'Checking parent forum references&hellip; %s', 'bbpress' );
	} else {
		/* translators: %s: Status of the repair process */
		$statement = esc_html__( 'Recalculating parent forum references&hellip; %s', 'bbpress' );
	}

	// Count forums that do not point to their parent forums exactly once
	$forum_repairs = $bbp_db->get_var(
		$bbp_db->prepare(
			"SELECT COUNT(*) FROM (
				SELECT `forum`.`ID`
				FROM `{$bbp_db->posts}` AS `forum`
				LEFT JOIN `{$bbp_db->postmeta}` AS `forummeta`
					ON `forummeta`.`post_id` = `forum`.`ID`
					AND `forummeta`.`meta_key` = %s
				WHERE `forum`.`post_type` = %s
				GROUP BY `forum`.`ID`
				HAVING COUNT( `forummeta`.`meta_id` ) <> 1
					OR SUM( CASE WHEN CAST( `forummeta`.`meta_key` AS BINARY ) = CAST( %s AS BINARY ) THEN 1 ELSE 0 END ) <> 1
					OR NOT ( CAST( MAX( `forummeta`.`meta_value` ) AS BINARY ) <=> CAST( CAST( MAX( `forum`.`post_parent` ) AS CHAR ) AS BINARY ) )
			) AS `forum_repairs`",
			'_bbp_forum_id',
			$fmt,
			'_bbp_forum_id'
		)
	);
	if ( ! empty( $bbp_db->last_error ) ) {
		return array(
			4,
			sprintf( $statement, $failed ),
			'success' => false,
		);
	}

	// Count topics that do not point to their parent forums exactly once
	$topic_repairs = $bbp_db->get_var(
		$bbp_db->prepare(
			"SELECT COUNT(*) FROM (
				SELECT `topic`.`ID`
				FROM `{$bbp_db->posts}` AS `topic`
				LEFT JOIN `{$bbp_db->postmeta}` AS `topicmeta`
					ON `topicmeta`.`post_id` = `topic`.`ID`
					AND `topicmeta`.`meta_key` = %s
				WHERE `topic`.`post_type` = %s
				GROUP BY `topic`.`ID`
				HAVING COUNT( `topicmeta`.`meta_id` ) <> 1
					OR SUM( CASE WHEN CAST( `topicmeta`.`meta_key` AS BINARY ) = CAST( %s AS BINARY ) THEN 1 ELSE 0 END ) <> 1
					OR NOT ( CAST( MAX( `topicmeta`.`meta_value` ) AS BINARY ) <=> CAST( CAST( MAX( `topic`.`post_parent` ) AS CHAR ) AS BINARY ) )
			) AS `topic_repairs`",
			'_bbp_forum_id',
			$tpt,
			'_bbp_forum_id'
		)
	);
	if ( ! empty( $bbp_db->last_error ) ) {
		return array(
			5,
			sprintf( $statement, $failed ),
			'success' => false,
		);
	}

	// Count replies that do not point to their topics' parent forums exactly once
	$reply_repairs = $bbp_db->get_var(
		$bbp_db->prepare(
			"SELECT COUNT(*) FROM (
				SELECT `reply`.`ID`
				FROM `{$bbp_db->posts}` AS `reply`
				INNER JOIN `{$bbp_db->posts}` AS `topic`
					ON `reply`.`post_parent` = `topic`.`ID`
				LEFT JOIN `{$bbp_db->postmeta}` AS `replymeta`
					ON `replymeta`.`post_id` = `reply`.`ID`
					AND `replymeta`.`meta_key` = %s
				WHERE `topic`.`post_type` = %s
					AND `reply`.`post_type` = %s
				GROUP BY `reply`.`ID`
				HAVING COUNT( `replymeta`.`meta_id` ) <> 1
					OR SUM( CASE WHEN CAST( `replymeta`.`meta_key` AS BINARY ) = CAST( %s AS BINARY ) THEN 1 ELSE 0 END ) <> 1
					OR NOT ( CAST( MAX( `replymeta`.`meta_value` ) AS BINARY ) <=> CAST( CAST( MAX( `topic`.`post_parent` ) AS CHAR ) AS BINARY ) )
			) AS `reply_repairs`",
			'_bbp_forum_id',
			$tpt,
			$rpt,
			'_bbp_forum_id'
		)
	);
	if ( ! empty( $bbp_db->last_error ) ) {
		return array(
			6,
			sprintf( $statement, $failed ),
			'success' => false,
		);
	}

	// Count relationships that do not belong to a forum, topic, or valid reply
	$stale_count = $bbp_db->get_var(
		$bbp_db->prepare(
			"SELECT COUNT( `forummeta`.`meta_id` )
			FROM `{$bbp_db->postmeta}` AS `forummeta`
			LEFT JOIN `{$bbp_db->posts}` AS `forum`
				ON `forummeta`.`post_id` = `forum`.`ID`
				AND `forum`.`post_type` = %s
			LEFT JOIN `{$bbp_db->posts}` AS `topic`
				ON `forummeta`.`post_id` = `topic`.`ID`
				AND `topic`.`post_type` = %s
			LEFT JOIN `{$bbp_db->posts}` AS `reply`
				ON `forummeta`.`post_id` = `reply`.`ID`
				AND `reply`.`post_type` = %s
			LEFT JOIN `{$bbp_db->posts}` AS `parent`
				ON `reply`.`post_parent` = `parent`.`ID`
				AND `parent`.`post_type` = %s
			WHERE `forummeta`.`meta_key` = %s
				AND `forum`.`ID` IS NULL
				AND `topic`.`ID` IS NULL
				AND `parent`.`ID` IS NULL",
			$fmt,
			$tpt,
			$rpt,
			$tpt,
			'_bbp_forum_id'
		)
	);
	if ( ! empty( $bbp_db->last_error ) ) {
		return array(
			7,
			sprintf( $statement, $failed ),
			'success' => false,
		);
	}

	$forum_repairs = (int) $forum_repairs;
	$topic_repairs = (int) $topic_repairs;
	$reply_repairs = (int) $reply_repairs;
	$stale_count   = (int) $stale_count;
	$post_types    = array( $fmt, $tpt, $rpt );

	// Bail if no repairs are needed
	if ( empty( $forum_repairs ) && empty( $topic_repairs ) && empty( $reply_repairs ) && empty( $stale_count ) ) {
		if ( ! $dry_run && ! bbp_admin_repair_clean_post_meta_cache_batched( $post_types ) ) {
			return array(
				8,
				sprintf( $statement, $failed ),
				'success' => false,
			);
		}

		$result = esc_html__( '0 parent forum references to repair.', 'bbpress' );
		return array( 1, sprintf( $statement, $result ) );
	}

	$forum_result = sprintf(
		/* translators: %s: Number of forum parent references */
		esc_html( _n( '%s forum parent reference would be repaired.', '%s forum parent references would be repaired.', $forum_repairs, 'bbpress' ) ),
		bbp_number_format( $forum_repairs )
	);
	$topic_result = sprintf(
		/* translators: %s: Number of topic parent forum references */
		esc_html( _n( '%s topic parent forum reference would be repaired.', '%s topic parent forum references would be repaired.', $topic_repairs, 'bbpress' ) ),
		bbp_number_format( $topic_repairs )
	);
	$reply_result = sprintf(
		/* translators: %s: Number of reply parent forum references */
		esc_html( _n( '%s reply parent forum reference would be repaired.', '%s reply parent forum references would be repaired.', $reply_repairs, 'bbpress' ) ),
		bbp_number_format( $reply_repairs )
	);
	$stale_result = sprintf(
		/* translators: %s: Number of orphaned parent forum references */
		esc_html( _n( '%s orphaned parent forum reference would be removed.', '%s orphaned parent forum references would be removed.', $stale_count, 'bbpress' ) ),
		bbp_number_format( $stale_count )
	);

	if ( $dry_run ) {
		$result = $forum_result . "\n" . $topic_result . "\n" . $reply_result . "\n" . $stale_result;

		return array( 0, sprintf( $statement, $result ) );
	}

	if ( ! bbp_admin_repair_clean_post_meta_cache_batched( $post_types, '_bbp_forum_id' ) ) {
		return array(
			8,
			sprintf( $statement, $failed ),
			'success' => false,
		);
	}

	// First, delete everything
	if ( false === $bbp_db->query( $bbp_db->prepare( "DELETE FROM `{$bbp_db->postmeta}` WHERE `meta_key` = %s", '_bbp_forum_id' ) ) ) {
		return array(
			1,
			sprintf( $statement, $failed ),
			'success' => false,
		);
	}

	// Give every topic its parent forum ID
	$inserted_topics = $bbp_db->query(
		$bbp_db->prepare(
			"INSERT INTO `{$bbp_db->postmeta}` (`post_id`, `meta_key`, `meta_value`)
			( SELECT `topic`.`ID`, '_bbp_forum_id', `topic`.`post_parent`
			FROM `{$bbp_db->posts}` AS `topic`
			WHERE `topic`.`post_type` = %s
			GROUP BY `topic`.`ID` )",
			$tpt
		)
	);

	if ( false === $inserted_topics ) {
		return array(
			2,
			sprintf( $statement, $failed ),
			'success' => false,
		);
	}

	// Give every forum its parent forum ID
	$inserted_forums = $bbp_db->query(
		$bbp_db->prepare(
			"INSERT INTO `{$bbp_db->postmeta}` (`post_id`, `meta_key`, `meta_value`)
			( SELECT `forum`.`ID`, '_bbp_forum_id', `forum`.`post_parent`
			FROM `{$bbp_db->posts}` AS `forum`
			WHERE `forum`.`post_type` = %s
			GROUP BY `forum`.`ID` )",
			$fmt
		)
	);

	if ( false === $inserted_forums ) {
		return array(
			2,
			sprintf( $statement, $failed ),
			'success' => false,
		);
	}

	// Give every reply its topic's parent forum ID
	$inserted_replies = $bbp_db->query(
		$bbp_db->prepare(
			"INSERT INTO `{$bbp_db->postmeta}` (`post_id`, `meta_key`, `meta_value`)
			( SELECT `reply`.`ID`, '_bbp_forum_id', `topic`.`post_parent`
			FROM `{$bbp_db->posts}` AS `reply`
			INNER JOIN `{$bbp_db->posts}` AS `topic`
				ON `reply`.`post_parent` = `topic`.`ID`
			WHERE `topic`.`post_type` = %s
				AND `reply`.`post_type` = %s
			GROUP BY `reply`.`ID` )",
			$tpt,
			$rpt
		)
	);

	if ( false === $inserted_replies ) {
		return array(
			3,
			sprintf( $statement, $failed ),
			'success' => false,
		);
	}

	if ( ! bbp_admin_repair_clean_post_meta_cache_batched( $post_types ) ) {
		return array(
			8,
			sprintf( $statement, $failed ),
			'success' => false,
		);
	}

	$forum_result = sprintf(
		/* translators: %s: Number of forum parent references */
		esc_html( _n( '%s forum parent reference repaired.', '%s forum parent references repaired.', $forum_repairs, 'bbpress' ) ),
		bbp_number_format( $forum_repairs )
	);
	$topic_result = sprintf(
		/* translators: %s: Number of topic parent forum references */
		esc_html( _n( '%s topic parent forum reference repaired.', '%s topic parent forum references repaired.', $topic_repairs, 'bbpress' ) ),
		bbp_number_format( $topic_repairs )
	);
	$reply_result = sprintf(
		/* translators: %s: Number of reply parent forum references */
		esc_html( _n( '%s reply parent forum reference repaired.', '%s reply parent forum references repaired.', $reply_repairs, 'bbpress' ) ),
		bbp_number_format( $reply_repairs )
	);
	$stale_result = sprintf(
		/* translators: %s: Number of orphaned parent forum references */
		esc_html( _n( '%s orphaned parent forum reference removed.', '%s orphaned parent forum references removed.', $stale_count, 'bbpress' ) ),
		bbp_number_format( $stale_count )
	);
	$result = esc_html__( 'Complete!', 'bbpress' ) . "\n" . $forum_result . "\n" . $topic_result . "\n" . $reply_result . "\n" . $stale_result;

	return array( 0, sprintf( $statement, $result ) );
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
 * @since 2.7.0 bbPress (r8277) Previews one resumable batch per call.
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
 *
 * @param bool $dry_run Whether to preview without writes.
 * @return array Status code, per-batch repair counts, and progress flags.
 */
function bbp_admin_repair_topic_meta_batch( $dry_run = false ) {
	$bbp_db  = bbp_db();
	$tool_id = 'bbp-sync-topic-meta';
	$key     = '_bbp_topic_id';
	$types   = array( bbp_get_topic_post_type(), bbp_get_reply_post_type() );
	$state   = get_option( bbp_admin_repair_tool_batch_option_name( $tool_id ), false );
	$batch   = array(
		'code'      => 0,
		'topics'    => 0,
		'replies'   => 0,
		'stale'     => 0,
		'initial'   => false,
		'complete'  => false,
		'restarted' => false
	);
	if ( ( false !== $state ) && ( ! bbp_admin_repair_tool_is_valid_checkpoint( $state ) || ! isset( $state['post_types'] ) || ( $types !== $state['post_types'] ) ) ) {
		$state              = false;
		$batch['restarted'] = true;
	}
	$batch['initial'] = ( false === $state );
	if ( $batch['initial'] ) {
		$upper_id = (int) $bbp_db->get_var( $bbp_db->prepare( "SELECT MAX(ID) FROM {$bbp_db->posts} WHERE post_type IN (%s, %s)", $types ) );
		if ( ! empty( $bbp_db->last_error ) ) {
			$batch['code'] = 1;
			return $batch;
		}
		$upper_meta = (int) $bbp_db->get_var( $bbp_db->prepare( "SELECT MAX(post_id) FROM {$bbp_db->postmeta} WHERE meta_key = %s", $key ) );
		if ( ! empty( $bbp_db->last_error ) ) {
			$batch['code'] = 3;
			return $batch;
		}
		$state = array(
			'cursor'     => 0,
			'upper_id'   => max( $upper_id, $upper_meta ),
			'post_types' => $types
		);
	}
	$size = bbp_admin_repair_tool_batch_size( $tool_id );
	// Bound each side before combining source posts and stored references
	$ids  = $bbp_db->get_col(
		$bbp_db->prepare(
			"(SELECT ID FROM {$bbp_db->posts} WHERE post_type IN (%s, %s) AND ID >= %d AND ID <= %d ORDER BY ID LIMIT %d)
			UNION
			(SELECT post_id AS ID FROM {$bbp_db->postmeta} WHERE meta_key = %s AND post_id >= %d AND post_id <= %d GROUP BY post_id ORDER BY post_id LIMIT %d)
			ORDER BY ID LIMIT %d",
			$types[0],
			$types[1],
			$state['cursor'],
			$state['upper_id'],
			$size + 1,
			$key,
			$state['cursor'],
			$state['upper_id'],
			$size + 1,
			$size + 1
		)
	);
	if ( ! empty( $bbp_db->last_error ) ) {
		$batch['code'] = 7;
		return $batch;
	}
	$has_more = count( $ids ) > $size;
	$ids      = array_slice( $ids, 0, $size );
	$expected = array();
	if ( ! empty( $ids ) ) {
		$slots = implode( ', ', array_fill( 0, count( $ids ), '%d' ) );
		$rows  = $bbp_db->get_results(
			$bbp_db->prepare(
				"SELECT posts.ID, (posts.post_type = %s) AS is_topic,
				CASE WHEN posts.post_type = %s THEN posts.ID
					WHEN posts.post_type = %s AND parent.post_type = %s THEN parent.ID
					ELSE NULL END AS topic_id
				FROM {$bbp_db->posts} AS posts
				LEFT JOIN {$bbp_db->posts} AS parent ON posts.post_parent = parent.ID
				WHERE posts.ID IN ({$slots})",
				array_merge( array( $types[0], $types[0], $types[1], $types[0] ), $ids )
			)
		);
		if ( ! empty( $bbp_db->last_error ) ) {
			$batch['code'] = 2;
			return $batch;
		}
		foreach ( $rows as $row ) {
			$expected[ (int) $row->ID ] = $row;
		}
	}
	if ( ! $dry_run && $batch['initial'] && ! bbp_admin_repair_tool_save_checkpoint( $tool_id, $state ) ) {
		$batch['code'] = 7;
		return $batch;
	}
	$work     = 0;
	$finished = 0;
	foreach ( $ids as $id ) {
		if ( $work >= $size ) {
			break;
		}
		$id     = (int) $id;
		$row    = isset( $expected[ $id ] ) ? $expected[ $id ] : null;
		$value  = isset( $row->topic_id ) ? (int) $row->topic_id : null;
		$kind   = ( null === $value ) ? 'stale' : ( ! empty( $row->is_topic ) ? 'topics' : 'replies' );
		$repair = bbp_admin_repair_single_metadata( 'post', $id, $key, $value, $size - $work, $dry_run );
		if ( ! empty( $repair['code'] ) ) {
			$codes = array(
				2 => 3,
				3 => 4,
				4 => ( 'topics' === $kind ) ? 5 : 6
			);
			$batch['code'] = isset( $codes[ $repair['code'] ] ) ? $codes[ $repair['code'] ] : 7;
			return $batch;
		}
		$work += $repair['work'];
		if ( $repair['changed'] ) {
			$batch[ $kind ] += ( 'stale' === $kind ) ? $repair['work'] : 1;
		}
		if ( ! $repair['complete'] ) {
			$state['cursor'] = $id;
			break;
		}

		// Verify completed references before saving progress past them
		if ( ! $dry_run ) {
			$stored = $bbp_db->get_row( $bbp_db->prepare( "SELECT COUNT(*) AS row_count, MIN(meta_key) AS meta_key, MIN(meta_value) AS meta_value FROM {$bbp_db->postmeta} WHERE post_id = %d AND meta_key = %s", $id, $key ) );
			if ( ! empty( $bbp_db->last_error ) ) {
				$batch['code'] = 3;
				return $batch;
			}
			$valid = ( null === $value )
				? ( 0 === (int) $stored->row_count )
				: ( ( 1 === (int) $stored->row_count ) && ( $key === $stored->meta_key ) && ( (string) $value === $stored->meta_value ) );
			if ( ! $valid ) {
				$batch['code'] = 7;
				return $batch;
			}
		}
		$state['cursor'] = $id + 1;
		++$finished;
	}
	$complete = ! $has_more && ( count( $ids ) === $finished );
	if ( ! $dry_run && ! bbp_admin_repair_tool_save_checkpoint( $tool_id, $state, $complete ) ) {
		$batch['code'] = 7;
		return $batch;
	}
	$batch['complete'] = $complete;
	return $batch;
}

/**
 * Repair or preview one batch of topic references.
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
	$statement = bbp_admin_repair_tool_restart_statement( $statement, $batch['restarted'], $dry_run );
	if ( ! empty( $batch['code'] ) ) {
		return array(
			$batch['code'],
			sprintf( $statement, esc_html__( 'Failed!', 'bbpress' ) ),
			'success'  => false,
			'complete' => false
		);
	}
	if ( ! $batch['topics'] && ! $batch['replies'] && ! $batch['stale'] && $batch['complete'] && ( $dry_run || $batch['initial'] ) ) {
		return array(
			1,
			sprintf( $statement, esc_html__( '0 topic references to repair.', 'bbpress' ) ),
			'complete' => true
		);
	}
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
	$result = esc_html__( 'This batch:', 'bbpress' ) . "\n" . $topics . "\n" . $replies . "\n" . $stale;
	if ( ! $dry_run ) {
		$status = $batch['complete'] ? esc_html__( 'Complete!', 'bbpress' ) : esc_html__( 'Batch complete!', 'bbpress' );
		$result = $status . "\n" . $result;
	}
	return array(
		0,
		sprintf( $statement, $result ),
		'complete' => $batch['complete']
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
 * Recalculate or preview reply menu order.
 *
 * @since 2.7.0 bbPress (r8220)
 *
 * @param bool $dry_run Whether to preview changes without updating data.
 * @return array An array of the status code and the message.
 */
function bbp_admin_repair_reply_menu_order_process( $dry_run = false ) {

	// Define variables
	$bbp_db = bbp_db();
	$result = esc_html__( 'Failed!', 'bbpress' );

	if ( $dry_run ) {
		/* translators: %s: Status of the dry-run process */
		$statement = esc_html__( 'Checking reply menu order&hellip; %s', 'bbpress' );
	} else {
		/* translators: %s: Status of the repair process */
		$statement = esc_html__( 'Recalculating reply menu order&hellip; %s', 'bbpress' );
	}

	// Get cases where `_bbp_reply_to` was accidentally set to itself
	$reply_to_posts = $bbp_db->get_col( "SELECT `post_id` FROM `{$bbp_db->postmeta}` WHERE `meta_key` = '_bbp_reply_to' AND `post_id` = `meta_value`" );
	if ( ! empty( $bbp_db->last_error ) ) {
		return array(
			1,
			sprintf( $statement, $result ),
			'success' => false,
		);
	}

	// Post type
	$rpt = bbp_get_reply_post_type();

	// Get an array of reply id's to update the menu oder for each reply
	$replies = $bbp_db->get_results(
		"SELECT `a`.`ID`, `a`.`post_parent` FROM `{$bbp_db->posts}` AS `a`
		INNER JOIN (
			SELECT `menu_order`, `post_parent`
			FROM `{$bbp_db->posts}`
			GROUP BY `menu_order`, `post_parent`
			HAVING COUNT( * ) >1
		)`b`
		ON `a`.`menu_order` = `b`.`menu_order`
		AND `a`.`post_parent` = `b`.`post_parent`
		WHERE `post_type` = '{$rpt}'",
		OBJECT_K
	);

	if ( ! empty( $bbp_db->last_error ) ) {
		return array( 2, sprintf( $statement, $result ) );
	}

	$relationship_count = count( $reply_to_posts );
	$reply_ids          = wp_parse_id_list( array_keys( $replies ) );
	$topic_ids          = wp_parse_id_list( wp_list_pluck( $replies, 'post_parent' ) );
	$post_ids           = array_merge( $reply_ids, $topic_ids );
	$position_repairs   = array();

	// Ensure current reply-to references are visible after direct database work
	$reply_to_posts = wp_parse_id_list( $reply_to_posts );
	if ( ! empty( $reply_to_posts ) ) {
		wp_cache_delete_multiple( $reply_to_posts, 'post_meta' );
	}
	if ( ! empty( $post_ids ) ) {
		wp_cache_delete_multiple( $post_ids, 'posts' );
		wp_cache_delete_multiple( $post_ids, 'post_meta' );
	}

	// Identify replies whose position would actually change
	foreach ( $replies as $reply ) {
		$reply_id       = (int) $reply->ID;
		$topic_id       = bbp_get_reply_topic_id( $reply_id );
		$new_position   = bbp_get_reply_position_raw( $reply_id, $topic_id );
		$position       = (int) get_post_field( 'menu_order', $reply_id );

		if ( $new_position !== $position ) {
			$position_repairs[ $reply_id ] = $new_position;
		}
	}

	$position_count = count( $position_repairs );

	// Bail if no repairs are needed
	if ( empty( $relationship_count ) && empty( $position_count ) ) {
		$result = esc_html__( '0 reply positions or reply-to references to repair.', 'bbpress' );
		return array( 1, sprintf( $statement, $result ) );
	}

	// Delete self-referential reply-to references
	if ( ! $dry_run && ! empty( $relationship_count ) ) {
		$relationship_count = $bbp_db->query( "DELETE FROM `{$bbp_db->postmeta}` WHERE `meta_key` = '_bbp_reply_to' AND `post_id` = `meta_value`" );
		if ( false === $relationship_count ) {
			return array( 3, sprintf( $statement, $result ) );
		}
	}

	// Recalculate changed menu order positions when not previewing
	if ( ! $dry_run ) {
		$position_count = 0;
		foreach ( $position_repairs as $reply_id => $new_position ) {
			if ( false !== bbp_update_reply_position( $reply_id, $new_position ) ) {
				++$position_count;
			}
		}
	}

	// Cleanup
	unset( $reply_to_posts, $replies, $reply, $reply_ids, $topic_ids, $post_ids, $reply_id, $topic_id, $new_position, $position, $position_repairs );

	// Complete results
	if ( $dry_run ) {
		/* translators: %d: Number of self-referential reply-to references that would be removed */
		$relationships = sprintf( _n( '%d self-referential reply-to reference would be removed.', '%d self-referential reply-to references would be removed.', $relationship_count, 'bbpress' ), $relationship_count );
		/* translators: %d: Number of reply positions that would be recalculated */
		$positions = sprintf( _n( '%d reply position would be recalculated.', '%d reply positions would be recalculated.', $position_count, 'bbpress' ), $position_count );
	} else {
		/* translators: %d: Number of removed self-referential reply-to references */
		$relationships = sprintf( _n( 'Complete! %d self-referential reply-to reference was removed.', 'Complete! %d self-referential reply-to references were removed.', $relationship_count, 'bbpress' ), $relationship_count );
		/* translators: %d: Number of recalculated reply positions */
		$positions = sprintf( _n( '%d reply position was recalculated.', '%d reply positions were recalculated.', $position_count, 'bbpress' ), $position_count );
	}

	$result = $relationships . "\n" . $positions;

	return array( 0, sprintf( $statement, $result ) );
}
