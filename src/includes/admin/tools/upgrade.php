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

	// Get the registered upgrade tools
	$tools = bbp_admin_repair_list( 'upgrade' );

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
		<h2 class="nav-tab-wrapper"><?php bbp_tools_admin_tabs( 'bbp-upgrade' ); ?></h2>

		<p><?php esc_html_e( 'As bbPress improves, occasionally database upgrades are required but some forums are too large to upgrade automatically. Use the tools below to manually run upgrade routines.', 'bbpress' ); ?></p>
		<p class="description"><?php esc_html_e( 'Some of these tools create substantial database overhead. Use caution when running more than 1 upgrade at a time.', 'bbpress' ); ?></p>

		<?php bbp_admin_repair_tool_status_filters(); ?>

		<form class="settings" method="get" action="">

			<?php bbp_admin_repair_list_search_form(); ?>

			<input type="hidden" name="page" value="bbp-upgrade" />
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

					<?php bbp_admin_repair_list_versions_filter(); ?>

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
							<a href="
								<?php
								echo esc_url(
									bbp_get_admin_repair_tool_page_url(
										array(
											'orderby' => 'priority',
											'order'   => $new_order
										)
									)
								);
								?>">
								<span><?php esc_html_e( 'Description', 'bbpress' ); ?></span>
								<span class="sorting-indicator"></span>
							</a>
						</th>
						<th scope="col" id="bbp-tool-version" class="manage-column column-version sortable <?php echo ( 'version' === $orderby ) ? esc_attr( $order ) : 'asc'; ?>">
							<a href="
								<?php
								echo esc_url(
									bbp_get_admin_repair_tool_page_url(
										array(
											'orderby' => 'version',
											'order'   => $new_order
										)
									)
								);
								?>">
								<span><?php esc_html_e( 'Version', 'bbpress' ); ?></span>
								<span class="sorting-indicator"></span>
							</a>
						</th>
						<th scope="col" id="bbp-tool-components" class="manage-column column-components">
							<?php esc_html_e( 'Components', 'bbpress' ); ?>
						</th>
						<th scope="col" id="overhead" class="manage-column column-overhead sortable <?php echo ( 'overhead' === $orderby ) ? esc_attr( $order ) : 'asc'; ?>">
							<a href="
								<?php
								echo esc_url(
									bbp_get_admin_repair_tool_page_url(
										array(
											'orderby' => 'overhead',
											'order'   => $new_order
										)
									)
								);
								?>">
								<span><?php esc_html_e( 'Overhead', 'bbpress' ); ?></span>
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
											/* translators: %s: Upgrade tool title */
											esc_html__( 'Select %s', 'bbpress' ),
											esc_html( $item['title'] )
										);
										?>
									</label>
									<input type="checkbox" name="checked[]" value="<?php echo esc_attr( $item['id'] ); ?>" id="<?php echo esc_attr( $select_id ); ?>">
								</th>
								<td class="bbp-tool-title column-primary column-description" data-colname="<?php esc_html_e( 'Description', 'bbpress' ); ?>">
									<strong><?php echo esc_html( $item['title'] ); ?></strong><?php

										// Optional description
										if ( ! empty( $item['description'] ) ) :
											echo '<p class="description">' . esc_html( $item['description'] ) . '</p>';
										endif;

									?><div class="row-actions hide-if-no-js">
										<?php bbp_admin_repair_tool_row_actions( $item ); ?>
									</div>
									<button type="button" class="toggle-row">
										<span class="screen-reader-text"><?php esc_html_e( 'Show more details', 'bbpress' ); ?></span>
									</button>
								</td>
								<td class="column-version desc" data-colname="<?php esc_html_e( 'Version', 'bbpress' ); ?>">
									<div class="bbp-tool-version">

										<?php echo implode( ', ', bbp_get_admin_repair_tool_version( $item ) ); ?>

									</div>
								</td>
								<td class="column-components desc" data-colname="<?php esc_html_e( 'Components', 'bbpress' ); ?>">
									<div class="bbp-tool-components">

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
						<th scope="col" class="manage-column column-version"><?php esc_html_e( 'Version', 'bbpress' ); ?></th>
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
	$state   = get_option( bbp_admin_repair_tool_batch_option_name( $tool_id ), false );
	$batch   = array(
		'code'      => 0,
		'added'     => 0,
		'removed'   => 0,
		'complete'  => false,
		'found'     => false,
		'restarted' => false
	);

	if ( ( false !== $state ) && ! bbp_admin_repair_tool_is_valid_checkpoint( $state ) ) {
		$state              = false;
		$batch['restarted'] = true;
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
		if ( ! $dry_run && ! bbp_admin_repair_tool_save_checkpoint( $tool_id, $state ) ) {
			bbp_add_pending_upgrade( $tool_id );
			$batch['code'] = 6;
			return $batch;
		}
	}

	$size = bbp_admin_repair_tool_batch_size( $tool_id );
	$rows = $bbp_db->get_results( $bbp_db->prepare( "SELECT meta_id, post_id, meta_value, (meta_key = '_bbp_topic_id') AS is_source FROM {$bbp_db->postmeta} WHERE meta_key IN ('_bbp_topic_id', '_bbp_engagement') AND meta_id > %d AND meta_id <= %d ORDER BY meta_id LIMIT %d", $state['cursor'], $state['upper_id'], $size + 1 ) );
	if ( ! empty( $bbp_db->last_error ) ) {
		$batch['code'] = 2;
		return $batch;
	}
	$complete       = count( $rows ) <= $size;
	$rows           = array_slice( $rows, 0, $size );
	$batch['found'] = ! empty( $rows );
	$planned        = array();

	// Use the same source rules for adding missing and identifying stale engagements
	$source = $bbp_db->prepare(
		"SELECT topics.ID AS post_id, posts.post_author AS user_id
		FROM {$bbp_db->posts} AS posts
		INNER JOIN {$bbp_db->postmeta} AS postmeta
			ON posts.ID = postmeta.post_id AND postmeta.meta_key = '_bbp_topic_id'
		INNER JOIN {$bbp_db->posts} AS topics
			ON topics.ID = postmeta.meta_value AND topics.post_type = %s
		WHERE posts.post_type IN (%s, %s) AND posts.post_status IN (%s, %s)",
		bbp_get_topic_post_type(),
		bbp_get_topic_post_type(),
		bbp_get_reply_post_type(),
		bbp_get_public_status_id(),
		bbp_get_closed_status_id()
	);

	foreach ( $rows as $row ) {
		// Classify keys with the same database collation used by the source query
		$is_source = (bool) $row->is_source;
		$where     = $is_source
			? $bbp_db->prepare( ' AND postmeta.meta_id = %d', $row->meta_id )
			: $bbp_db->prepare( ' AND topics.ID = %d AND posts.post_author = %d', $row->post_id, $row->meta_value );
		$relation  = $bbp_db->get_row( $source . $where . ' LIMIT 1' );
		if ( ! empty( $bbp_db->last_error ) ) {
			$batch['code'] = 3;
			return $batch;
		}

		$exists = null;
		if ( ! empty( $relation ) ) {
			$exists = $bbp_db->get_var( $bbp_db->prepare( "SELECT meta_id FROM {$bbp_db->postmeta} WHERE post_id = %d AND BINARY meta_key = '_bbp_engagement' AND BINARY meta_value = %s ORDER BY meta_id LIMIT 1", $relation->post_id, $relation->user_id ) );
			if ( ! empty( $bbp_db->last_error ) ) {
				$batch['code'] = 3;
				return $batch;
			}
		}

		if ( $is_source ) {
			if ( ! empty( $relation ) && empty( $exists ) && ! isset( $planned[ $relation->post_id ][ $relation->user_id ] ) ) {
				if ( ! $dry_run && ! add_post_meta( $relation->post_id, '_bbp_engagement', $relation->user_id, false ) ) {
					$batch['code'] = 5;
					return $batch;
				}
				$planned[ $relation->post_id ][ $relation->user_id ] = true;
				++$batch['added'];
			}
		} elseif ( (int) $exists !== (int) $row->meta_id ) {
			if ( ! $dry_run && ! delete_metadata_by_mid( 'post', $row->meta_id ) ) {
				$batch['code'] = 4;
				return $batch;
			}
			++$batch['removed'];
		}
		$state['cursor'] = (int) $row->meta_id;
	}

	if ( ! $dry_run && ! bbp_admin_repair_tool_save_checkpoint( $tool_id, $state, $complete ) ) {
		$batch['code'] = 6;
		return $batch;
	}
	$batch['complete'] = $complete;

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
	$failed         = esc_html__( 'Failed!', 'bbpress' );
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
		return array(
			$batch['code'],
			sprintf( $statement, $failed ),
			'success'  => false,
			'complete' => false
		);
	}
	if ( ! $batch['found'] && $batch['complete'] ) {
		return array( 1, sprintf( $statement, $no_engagements ) );
	}

	if ( $dry_run ) {
		/* translators: %s: Number of missing engagements that would be added */
		$added = sprintf( _n( '%s missing engagement would be added from topic and reply authors in this batch.', '%s missing engagements would be added from topic and reply authors in this batch.', $batch['added'], 'bbpress' ), bbp_number_format( $batch['added'] ) );
		/* translators: %s: Number of stale or duplicate engagement rows that would be removed */
		$removed = sprintf( _n( '%s stale or duplicate engagement row would be cleaned up in this batch.', '%s stale or duplicate engagement rows would be cleaned up in this batch.', $batch['removed'], 'bbpress' ), bbp_number_format( $batch['removed'] ) );
		$result  = $added . "\n" . $removed;
	} else {
		/* translators: %s: Number of missing engagements added */
		$added = sprintf( _n( '%s missing engagement added from topic and reply authors.', '%s missing engagements added from topic and reply authors.', $batch['added'], 'bbpress' ), bbp_number_format( $batch['added'] ) );
		/* translators: %s: Number of stale or duplicate engagement rows removed */
		$removed = sprintf( _n( '%s stale or duplicate engagement row cleaned up.', '%s stale or duplicate engagement rows cleaned up.', $batch['removed'], 'bbpress' ), bbp_number_format( $batch['removed'] ) );
		$status  = $batch['complete']
			? esc_html__( 'Complete!', 'bbpress' )
			: esc_html__( 'Batch complete!', 'bbpress' );
		$result = $status . "\n" . esc_html__( 'This batch:', 'bbpress' ) . "\n" . $added . "\n" . $removed;
	}

	return array(
		0,
		sprintf( $statement, $result ),
		'complete' => $batch['complete']
	);
}

/**
 * Upgrade group forum ID mappings after a bbPress 1.x to bbPress 2.x conversion.
 *
 * Previously named: bbp_admin_repair_group_forum_relationships().
 *
 * @since 2.6.0 bbPress (r6496)
 * @since 2.7.0 bbPress (r8237) Added Dry Run support.
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
 *
 * @param bool $dry_run Whether to preview changes without updating data.
 * @return array An array of the status code and the message.
 */
function bbp_admin_upgrade_group_forum_relationships_process( $dry_run = false ) {

	// Define variables
	$bbp_db           = bbp_db();
	$no_data          = esc_html__( '0 converted group forums to upgrade.', 'bbpress' );
	$g_count          = 0;
	$f_count          = 0;
	$s_count          = 0;
	$failure_progress = static function ( $reason, $groups, $forums, $statuses, $dry_run ) {
		if ( $dry_run ) {
			return $reason;
		}

		$results = array(
			/* translators: %s: Number of groups updated */
			sprintf( _n( '%s group updated', '%s groups updated', $groups, 'bbpress' ), bbp_number_format( $groups ) ),
			/* translators: %s: Number of forums updated */
			sprintf( _n( '%s forum updated', '%s forums updated', $forums, 'bbpress' ), bbp_number_format( $forums ) ),
			/* translators: %s: Number of forum statuses synced */
			sprintf( _n( '%s forum status synced', '%s forum statuses synced', $statuses, 'bbpress' ), bbp_number_format( $statuses ) )
		);

		return $reason . "\n" . esc_html__( 'Progress:', 'bbpress' ) . "\n" . implode( ".\n", $results ) . '.';
	};

	if ( $dry_run ) {
		/* translators: %s: Status of the dry-run process */
		$statement = esc_html__( 'Checking BuddyPress group-forum relationships&hellip; %s', 'bbpress' );
	} else {
		/* translators: %s: Status of the upgrade process */
		$statement = esc_html__( 'Upgrading BuddyPress group-forum relationships&hellip; %s', 'bbpress' );
	}

	// Copy the BuddyPress filter here, incase BuddyPress is not active
	$prefix            = apply_filters( 'bp_core_get_table_prefix', $bbp_db->base_prefix );
	$groups_table      = $prefix . 'bp_groups';
	$groups_meta_table = $prefix . 'bp_groups_groupmeta';

	// Get the converted forum IDs
	$results = $bbp_db->get_results(
		"SELECT `forum`.`ID`, `forummeta`.`meta_value`
			FROM `{$bbp_db->posts}` AS `forum`
				LEFT JOIN `{$bbp_db->postmeta}` AS `forummeta`
					ON `forum`.`ID` = `forummeta`.`post_id`
					AND `forummeta`.`meta_key` = '_bbp_old_forum_id'
			WHERE `forum`.`post_type` = '" . bbp_get_forum_post_type() . "'
			GROUP BY `forum`.`ID`
			ORDER BY `forum`.`ID`"
	);

	// Bail if converted forum IDs could not be read
	if ( ! empty( $bbp_db->last_error ) ) {
		return array(
			2,
			sprintf( $statement, $failure_progress( esc_html__( 'Failed! Converted forums could not be read.', 'bbpress' ), $g_count, $f_count, $s_count, $dry_run ) ),
			'success' => false,
		);
	}

	// Validate converted forum ID mappings before updating any data
	$converted_forum_ids = array();
	$native_forum_ids    = array();
	$forum_group_ids     = array();
	foreach ( $results as $group_forums ) {
		$new_id = (string) $group_forums->ID;

		if ( empty( $group_forums->meta_value ) ) {
			$native_forum_ids[ $new_id ] = true;
			continue;
		}

		$converted_forum_ids[ $new_id ] = (string) $group_forums->meta_value;
		$forum_group_ids[ $new_id ]     = wp_parse_id_list( get_post_meta( $group_forums->ID, '_bbp_group_ids', true ) );
	}

	// Bail if no converted forum IDs were found
	if ( empty( $converted_forum_ids ) ) {
		return array( 1, sprintf( $statement, $no_data ) );
	}

	// Validate forum-side relationship markers before planning changes
	$marked_group_forums = array();
	$skip_forum_ids      = array();
	foreach ( $converted_forum_ids as $new_id => $legacy_id ) {
		$new_id           = (string) $new_id;
		$stored_group_ids = $forum_group_ids[ $new_id ];

		foreach ( $stored_group_ids as $stored_group_id ) {
			$stored_forum_ids = $bbp_db->get_var( $bbp_db->prepare( "SELECT `meta_value` FROM `{$groups_meta_table}` WHERE `group_id` = %d AND `meta_key` = %s ORDER BY `group_id` LIMIT 1", $stored_group_id, 'forum_id' ) );
			if ( ! empty( $bbp_db->last_error ) ) {
				return array(
					3,
					sprintf( $statement, $failure_progress( esc_html__( 'Failed! BuddyPress group metadata could not be read.', 'bbpress' ), $g_count, $f_count, $s_count, $dry_run ) ),
					'success' => false,
				);
			}

			$serialized     = is_serialized( $stored_forum_ids );
			$marker_current = in_array( (int) $new_id, wp_parse_id_list( maybe_unserialize( $stored_forum_ids ) ), true );
			$marker_legacy  = ! $serialized && ( (string) $stored_forum_ids === (string) $legacy_id );

			if ( $marker_current || $marker_legacy ) {
				if ( ! isset( $marked_group_forums[ $stored_group_id ] ) ) {
					$marked_group_forums[ $stored_group_id ] = array();
				}

				$marked_group_forums[ $stored_group_id ][] = $new_id;
			}

			if ( $serialized ) {
				$skip_forum_ids[ $new_id ] = true;
			}
		}

		if ( count( $stored_group_ids ) > 1 ) {
			$skip_forum_ids[ $new_id ] = true;
		}
	}

	// Plan each group-forum relationship before updating any data
	$relationship_plans   = array();
	$planned_groups       = array();
	$planned_legacy_ids   = array();
	foreach ( $results as $group_forums ) {

		// Only update if is a converted forum
		if ( empty( $group_forums->meta_value ) ) {
			continue;
		}

		$new_id           = (string) $group_forums->ID;
		$stored_group_ids = $forum_group_ids[ $new_id ];

		// Leave modern and many-to-many relationships unchanged
		if ( isset( $skip_forum_ids[ $new_id ] ) ) {
			continue;
		}

		// Get the group connected to the legacy forum ID
		$legacy_group_ids = array_values( array_unique( array_map( 'intval', $bbp_db->get_col( $bbp_db->prepare( "SELECT `group_id` FROM `{$groups_meta_table}` WHERE `meta_key` = %s AND `meta_value` = %s ORDER BY `group_id`", 'forum_id', $group_forums->meta_value ) ) ) ) );
		if ( ! empty( $bbp_db->last_error ) ) {
			return array(
				3,
				sprintf( $statement, $failure_progress( esc_html__( 'Failed! BuddyPress group metadata could not be read.', 'bbpress' ), $g_count, $f_count, $s_count, $dry_run ) ),
				'success' => false,
			);
		}

		// Fall back to an already-converted group ID on reruns
		$current_group_ids = array_values( array_unique( array_map( 'intval', $bbp_db->get_col( $bbp_db->prepare( "SELECT `group_id` FROM `{$groups_meta_table}` WHERE `meta_key` = %s AND `meta_value` = %d ORDER BY `group_id`", 'forum_id', $group_forums->ID ) ) ) ) );
		if ( ! empty( $bbp_db->last_error ) ) {
			return array(
				3,
				sprintf( $statement, $failure_progress( esc_html__( 'Failed! BuddyPress group metadata could not be read.', 'bbpress' ), $g_count, $f_count, $s_count, $dry_run ) ),
				'success' => false,
			);
		}

		$available_legacy_group_ids = array();
		$available_current_group_ids = array();

		foreach ( $legacy_group_ids as $candidate_group_id ) {
			if ( ! isset( $marked_group_forums[ $candidate_group_id ] ) || in_array( $new_id, $marked_group_forums[ $candidate_group_id ], true ) ) {
				$available_legacy_group_ids[] = $candidate_group_id;
			}
		}

		foreach ( $current_group_ids as $candidate_group_id ) {
			if ( ! isset( $marked_group_forums[ $candidate_group_id ] ) || in_array( $new_id, $marked_group_forums[ $candidate_group_id ], true ) ) {
				$available_current_group_ids[] = $candidate_group_id;
			}
		}

		$stored_group_id = ! empty( $stored_group_ids ) ? reset( $stored_group_ids ) : 0;
		$stored_group_id = in_array( $stored_group_id, $legacy_group_ids, true ) || in_array( $stored_group_id, $current_group_ids, true )
			? $stored_group_id
			: 0;

		if ( ! empty( $stored_group_id ) ) {
			$group_id           = $stored_group_id;
			$legacy_group_match = in_array( $stored_group_id, $legacy_group_ids, true );
		} else {
			if ( ( count( $available_legacy_group_ids ) > 1 ) || ( count( $available_current_group_ids ) > 1 ) ) {
				return array(
					7,
					sprintf( $statement, $failure_progress( esc_html__( 'Failed! Ambiguous BuddyPress group-forum relationships were found.', 'bbpress' ), $g_count, $f_count, $s_count, $dry_run ) ),
					'success' => false,
				);
			}

			$legacy_group_id    = ! empty( $available_legacy_group_ids ) ? reset( $available_legacy_group_ids ) : 0;
			$current_group_id   = ! empty( $available_current_group_ids ) ? reset( $available_current_group_ids ) : 0;
			$group_id           = ! empty( $legacy_group_id ) ? $legacy_group_id : $current_group_id;
			$legacy_group_match = ! empty( $legacy_group_id );
		}

		if ( empty( $group_id ) ) {
			continue;
		}

		$needs_group_update = $legacy_group_match && ( (string) $group_forums->meta_value !== $new_id );

		if ( $needs_group_update ) {
			$legacy_id = (string) $group_forums->meta_value;

			if ( isset( $planned_legacy_ids[ $legacy_id ] ) && ( $planned_legacy_ids[ $legacy_id ] !== $new_id ) ) {
				return array(
					7,
					sprintf( $statement, esc_html__( 'Failed! Ambiguous converted forum ID mappings were found.', 'bbpress' ) ),
					'success' => false,
				);
			}

			if ( ( $legacy_id !== $new_id ) && isset( $native_forum_ids[ $legacy_id ] ) ) {
				return array(
					7,
					sprintf( $statement, esc_html__( 'Failed! Ambiguous converted forum ID mappings were found.', 'bbpress' ) ),
					'success' => false,
				);
			}

			$planned_legacy_ids[ $legacy_id ] = $new_id;
		}

		if ( isset( $planned_groups[ $group_id ] ) && ( $planned_groups[ $group_id ] !== $new_id ) ) {
			return array(
				7,
				sprintf( $statement, $failure_progress( esc_html__( 'Failed! Ambiguous BuddyPress group-forum relationships were found.', 'bbpress' ), $g_count, $f_count, $s_count, $dry_run ) ),
				'success' => false,
			);
		}

		$planned_groups[ $group_id ] = $new_id;

		// Get the group status
		$group_status = $bbp_db->get_var( $bbp_db->prepare( "SELECT `status` FROM `{$groups_table}` WHERE `id` = %d", $group_id ) );
		if ( ! empty( $bbp_db->last_error ) ) {
			return array(
				4,
				sprintf( $statement, $failure_progress( esc_html__( 'Failed! BuddyPress groups could not be read.', 'bbpress' ), $g_count, $f_count, $s_count, $dry_run ) ),
				'success' => false,
			);
		}

		// Plan forum metadata and visibility changes
		$needs_forum_update = ( array( $group_id ) !== $stored_group_ids );
		$target_visibility  = '';

		switch ( $group_status ) {
			case 'public' :
				$target_visibility = bbp_get_public_status_id();
				break;

			case 'private' :
			case 'hidden'  :
				$target_visibility = bbp_get_hidden_status_id();
				break;
		}

		$current_visibility  = bbp_get_forum_visibility( $group_forums->ID );
		$hidden_forum_ids    = wp_parse_id_list( get_option( '_bbp_hidden_forums', array() ) );
		$private_forum_ids   = wp_parse_id_list( get_option( '_bbp_private_forums', array() ) );
		$is_hidden           = in_array( (int) $group_forums->ID, $hidden_forum_ids, true );
		$is_private          = in_array( (int) $group_forums->ID, $private_forum_ids, true );
		$options_need_update = ( bbp_get_public_status_id() === $target_visibility )
			? ( $is_hidden || $is_private )
			: ( bbp_get_hidden_status_id() === $target_visibility ) && ( ! $is_hidden || $is_private );
		$needs_status_update = ! empty( $target_visibility ) && ( ( $target_visibility !== $current_visibility ) || $options_need_update );
		$relationship_plans[] = array(
			'forum_id'           => (int) $group_forums->ID,
			'legacy_id'          => (string) $group_forums->meta_value,
			'group_id'           => $group_id,
			'current_visibility' => $current_visibility,
			'target_visibility'  => $target_visibility,
			'update_group'       => $needs_group_update,
			'update_forum'       => $needs_forum_update,
			'update_status'      => $needs_status_update,
		);

		if ( $dry_run ) {
			$g_count += (int) $needs_group_update;
			$f_count += (int) $needs_forum_update;
			$s_count += (int) $needs_status_update;
		}
	}

	if ( ! $dry_run ) {
		foreach ( $relationship_plans as $relationship ) {
			$forum_id          = $relationship['forum_id'];
			$group_id          = $relationship['group_id'];
			$current_visibility = $relationship['current_visibility'];
			$target_visibility  = $relationship['target_visibility'];

			if ( $relationship['update_forum'] ) {
				$updated = update_post_meta( $forum_id, '_bbp_group_ids', array( $group_id ) );
				if ( false === $updated ) {
					return array(
						8,
						sprintf( $statement, $failure_progress( esc_html__( 'Failed! Forum group metadata could not be updated.', 'bbpress' ), $g_count, $f_count, $s_count, $dry_run ) ),
						'success' => false,
					);
				}

				++$f_count;
			}

			if ( $relationship['update_group'] ) {
				// Attempt to update group meta
				$updated = $bbp_db->query( $bbp_db->prepare( "UPDATE `{$groups_meta_table}` SET `meta_value` = %d WHERE `group_id` = %d AND `meta_key` = %s AND `meta_value` = %s", $forum_id, $group_id, 'forum_id', $relationship['legacy_id'] ) );
				if ( empty( $updated ) ) {
					return array(
						5,
						sprintf( $statement, $failure_progress( esc_html__( 'Failed! BuddyPress group metadata could not be updated.', 'bbpress' ), $g_count, $f_count, $s_count, $dry_run ) ),
						'success' => false,
					);
				}

				++$g_count;
			}

			if ( $relationship['update_status'] ) {
				if ( bbp_get_public_status_id() === $target_visibility ) {
					bbp_publicize_forum( $forum_id, $current_visibility );
				} else {
					bbp_hide_forum( $forum_id, $current_visibility );
				}

				$hidden_forum_ids  = wp_parse_id_list( get_option( '_bbp_hidden_forums', array() ) );
				$private_forum_ids = wp_parse_id_list( get_option( '_bbp_private_forums', array() ) );
				$is_hidden         = in_array( $forum_id, $hidden_forum_ids, true );
				$is_private        = in_array( $forum_id, $private_forum_ids, true );
				$options_synced    = ( bbp_get_public_status_id() === $target_visibility )
					? ( ! $is_hidden && ! $is_private )
					: ( bbp_get_hidden_status_id() === $target_visibility ) && $is_hidden && ! $is_private;

				if ( ( bbp_get_forum_visibility( $forum_id ) !== $target_visibility ) || ! $options_synced ) {
					return array(
						9,
						sprintf( $statement, $failure_progress( esc_html__( 'Failed! Forum visibility could not be updated.', 'bbpress' ), $g_count, $f_count, $s_count, $dry_run ) ),
						'success' => false,
					);
				}

				++$s_count;
			}
		}
	}

	// Make some logical guesses at the old group root forum
	if ( function_exists( 'bp_forums_parent_forum_id' ) ) {
		$old_default_forum_id = bp_forums_parent_forum_id();
	} elseif ( defined( 'BP_FORUMS_PARENT_FORUM_ID' ) ) {
		$old_default_forum_id = (int) BP_FORUMS_PARENT_FORUM_ID;
	} else {
		$old_default_forum_id = 1;
	}

	// Try to get the group root forum
	$posts = get_posts(
		array(
			'post_type'   => bbp_get_forum_post_type(),
			'meta_key'    => '_bbp_old_forum_id',
			'meta_type'   => 'NUMERIC',
			'meta_value'  => $old_default_forum_id,
			'numberposts' => 1
		)
	);
	$root_forum_id    = ! empty( $posts ) ? (int) $posts[0]->ID : 0;
	$rename_root      = ! empty( $root_forum_id ) && ( 'Default Forum' === $posts[0]->post_title );
	$update_root      = ! empty( $root_forum_id ) && ( (int) get_option( '_bbp_group_forums_root_id' ) !== $root_forum_id );
	$legacy_role_ids  = array( 'member', 'inactive', 'blocked', 'moderator', 'keymaster' );
	$legacy_roles     = array();

	foreach ( $legacy_role_ids as $role_id ) {
		if ( ! empty( get_role( $role_id ) ) ) {
			$legacy_roles[] = $role_id;
		}
	}

	// Found the group root forum
	if ( ! empty( $root_forum_id ) && ! $dry_run ) {

		// Rename 'Default Forum'  since it's now visible in sitewide forums
		if ( $rename_root ) {
			$updated = wp_update_post(
				array(
					'ID'         => $posts[0]->ID,
					'post_title' => esc_html__( 'Group Forums', 'bbpress' ),
					'post_name'  => esc_html__( 'group-forums', 'bbpress' ),
				),
				true
			);

			if ( is_wp_error( $updated ) ) {
				return array(
					6,
					sprintf( $statement, $failure_progress( esc_html__( 'Failed! The group forums root could not be renamed.', 'bbpress' ), $g_count, $f_count, $s_count, $dry_run ) ),
					'success' => false,
				);
			}
		}

		// Update the group forums root metadata
		if ( $update_root ) {
			update_option( '_bbp_group_forums_root_id', $root_forum_id );
			if ( (int) get_option( '_bbp_group_forums_root_id' ) !== $root_forum_id ) {
				return array(
					10,
					sprintf( $statement, $failure_progress( esc_html__( 'Failed! The group forums root setting could not be updated.', 'bbpress' ), $g_count, $f_count, $s_count, $dry_run ) ),
					'success' => false,
				);
			}
		}
	}

	// Remove old bbPress 1.1 roles (BuddyPress)
	if ( ! $dry_run ) {
		foreach ( $legacy_roles as $role_id ) {
			remove_role( $role_id );
		}
	}

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
		$result  = esc_html__( 'Complete!', 'bbpress' ) . "\n" . implode( ".\n", $results ) . '.';
	}

	// Return
	return array( 0, sprintf( $statement, $result ) );
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
 * safe. Dry Run reads the same next batch without saving any progress.
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
	$option = bbp_admin_repair_tool_batch_option_name( $tool_id );
	$state  = get_option( $option, false );
	$batch  = array(
		'code'      => 0,
		'added'     => 0,
		'failed'    => 0,
		'complete'  => false,
		'found'     => false,
		'restarted' => false
	);

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
		if ( ! $dry_run && ! bbp_admin_repair_tool_save_checkpoint( $tool_id, $state ) ) {
			bbp_add_pending_upgrade( $tool_id );
			$batch['code'] = 5;
			return $batch;
		}
	}

	$size    = bbp_admin_repair_tool_batch_size( $tool_id );
	$work    = 0;
	$planned = array();

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

			if ( ! $dry_run && ( false === add_post_meta( $post_id, $new_key, $user_id, false ) ) ) {
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

	if ( ! $dry_run && ! bbp_admin_repair_tool_save_checkpoint( $tool_id, $state, $batch['complete'] ) ) {
		$batch['code']     = 5;
		$batch['complete'] = false;
	}

	return $batch;
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

	// Define variables
	$bbp_db  = bbp_db();
	$failed  = esc_html__( 'Failed!', 'bbpress' );
	$result  = esc_html__( '0 favorite relationships to upgrade.', 'bbpress' );
	$old_key = $bbp_db->prefix . '_bbp_favorites';
	$new_key = '_bbp_favorite';

	if ( $dry_run ) {
		/* translators: %s: Status of the dry-run process */
		$statement = esc_html__( 'Checking user favorites&hellip; %s', 'bbpress' );
	} else {
		/* translators: %s: Status of the upgrade process */
		$statement = esc_html__( 'Upgrading user favorites&hellip; %s', 'bbpress' );
	}

	$batch        = bbp_admin_upgrade_user_meta_batch( 'bbp-user-favorites-move', $old_key, $new_key, $dry_run );
	$total        = $batch['added'];
	$failed_total = $batch['failed'];

	$statement = bbp_admin_repair_tool_restart_statement( $statement, $batch['restarted'], $dry_run );

	if ( ! empty( $batch['code'] ) && ( 4 !== $batch['code'] ) ) {
		return array(
			$batch['code'],
			sprintf( $statement, $failed ),
			'success'  => false,
			'complete' => false
		);
	}
	if ( ! $batch['found'] && $batch['complete'] ) {
		return array( 1, sprintf( $statement, $result ) );
	}

	if ( $dry_run ) {
		if ( empty( $total ) && $batch['complete'] ) {
			return array( 1, sprintf( $statement, $result ) );
		}

		/* translators: %s: Number of favorite relationships that would be added to topic meta */
		$result = sprintf( _n( '%s favorite relationship would be added to topic meta from user meta in this batch.', '%s favorite relationships would be added to topic meta from user meta in this batch.', $total, 'bbpress' ), bbp_number_format( $total ) );

		return array(
			0,
			sprintf( $statement, $result ),
			'complete' => $batch['complete']
		);
	}

	if ( ! empty( $failed_total ) ) {
		/* translators: %s: Number of favorite relationships added to topic meta */
		$added    = sprintf( _n( '%s favorite relationship added', '%s favorite relationships added', $total, 'bbpress' ), bbp_number_format( $total ) );
		/* translators: %s: Number of favorite relationships that could not be added */
		$failures = sprintf( _n( '%s favorite relationship could not be added', '%s favorite relationships could not be added', $failed_total, 'bbpress' ), bbp_number_format( $failed_total ) );
		$result = esc_html__( 'Failed!', 'bbpress' ) . "\n" . $added . ".\n" . $failures . '.';

		return array(
			4,
			sprintf( $statement, $result ),
			'success' => false,
		);
	}

	// Complete results
	/* translators: %s: Number of favorite relationships added to topic meta */
	$result = sprintf( _n( '%s favorite relationship added to topic meta.', '%s favorite relationships added to topic meta.', $total, 'bbpress' ), bbp_number_format( $total ) );

	$status = $batch['complete']
		? esc_html__( 'Complete!', 'bbpress' )
		: esc_html__( 'Batch complete!', 'bbpress' );
	$result = $status . "\n" . esc_html__( 'This batch:', 'bbpress' ) . "\n" . $result;

	return array(
		0,
		sprintf( $statement, $result ),
		'complete' => $batch['complete']
	);
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

	// Define variables
	$bbp_db  = bbp_db();
	$failed  = esc_html__( 'Failed!', 'bbpress' );
	$old_key = $bbp_db->prefix . '_bbp_subscriptions';
	$new_key = '_bbp_subscription';
	$result  = esc_html__( '0 topic subscription relationships to upgrade.', 'bbpress' );

	if ( $dry_run ) {
		/* translators: %s: Status of the dry-run process */
		$statement = esc_html__( 'Checking user topic subscriptions&hellip; %s', 'bbpress' );
	} else {
		/* translators: %s: Status of the upgrade process */
		$statement = esc_html__( 'Upgrading user topic subscriptions&hellip; %s', 'bbpress' );
	}

	$batch        = bbp_admin_upgrade_user_meta_batch( 'bbp-user-topic-subscriptions-move', $old_key, $new_key, $dry_run );
	$total        = $batch['added'];
	$failed_total = $batch['failed'];

	$statement = bbp_admin_repair_tool_restart_statement( $statement, $batch['restarted'], $dry_run );

	if ( ! empty( $batch['code'] ) && ( 4 !== $batch['code'] ) ) {
		return array(
			$batch['code'],
			sprintf( $statement, $failed ),
			'success'  => false,
			'complete' => false
		);
	}
	if ( ! $batch['found'] && $batch['complete'] ) {
		return array( 1, sprintf( $statement, $result ) );
	}

	if ( $dry_run ) {
		if ( empty( $total ) && $batch['complete'] ) {
			return array( 1, sprintf( $statement, $result ) );
		}

		/* translators: %s: Number of topic subscription relationships that would be added to topic meta */
		$result = sprintf( _n( '%s topic subscription relationship would be added to topic meta from user meta in this batch.', '%s topic subscription relationships would be added to topic meta from user meta in this batch.', $total, 'bbpress' ), bbp_number_format( $total ) );

		return array(
			0,
			sprintf( $statement, $result ),
			'complete' => $batch['complete']
		);
	}

	if ( ! empty( $failed_total ) ) {
		/* translators: %s: Number of topic subscription relationships added to topic meta */
		$added    = sprintf( _n( '%s topic subscription relationship added', '%s topic subscription relationships added', $total, 'bbpress' ), bbp_number_format( $total ) );
		/* translators: %s: Number of topic subscription relationships that could not be added */
		$failures = sprintf( _n( '%s topic subscription relationship could not be added', '%s topic subscription relationships could not be added', $failed_total, 'bbpress' ), bbp_number_format( $failed_total ) );
		$result = esc_html__( 'Failed!', 'bbpress' ) . "\n" . $added . ".\n" . $failures . '.';

		return array(
			4,
			sprintf( $statement, $result ),
			'success' => false,
		);
	}

	// Complete results
	/* translators: %s: Number of topic subscription relationships added to topic meta */
	$result = sprintf( _n( '%s topic subscription relationship added to topic meta.', '%s topic subscription relationships added to topic meta.', $total, 'bbpress' ), bbp_number_format( $total ) );

	$status = $batch['complete']
		? esc_html__( 'Complete!', 'bbpress' )
		: esc_html__( 'Batch complete!', 'bbpress' );
	$result = $status . "\n" . esc_html__( 'This batch:', 'bbpress' ) . "\n" . $result;

	return array(
		0,
		sprintf( $statement, $result ),
		'complete' => $batch['complete']
	);
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

	// Define variables
	$bbp_db  = bbp_db();
	$failed  = esc_html__( 'Failed!', 'bbpress' );
	$result  = esc_html__( '0 forum subscription relationships to upgrade.', 'bbpress' );
	$old_key = $bbp_db->prefix . '_bbp_forum_subscriptions';
	$new_key = '_bbp_subscription';

	if ( $dry_run ) {
		/* translators: %s: Status of the dry-run process */
		$statement = esc_html__( 'Checking user forum subscriptions&hellip; %s', 'bbpress' );
	} else {
		/* translators: %s: Status of the upgrade process */
		$statement = esc_html__( 'Upgrading user forum subscriptions&hellip; %s', 'bbpress' );
	}

	$batch        = bbp_admin_upgrade_user_meta_batch( 'bbp-user-forum-subscriptions-move', $old_key, $new_key, $dry_run );
	$total        = $batch['added'];
	$failed_total = $batch['failed'];

	$statement = bbp_admin_repair_tool_restart_statement( $statement, $batch['restarted'], $dry_run );

	if ( ! empty( $batch['code'] ) && ( 4 !== $batch['code'] ) ) {
		return array(
			$batch['code'],
			sprintf( $statement, $failed ),
			'success'  => false,
			'complete' => false
		);
	}
	if ( ! $batch['found'] && $batch['complete'] ) {
		return array( 1, sprintf( $statement, $result ) );
	}

	if ( $dry_run ) {
		if ( empty( $total ) && $batch['complete'] ) {
			return array( 1, sprintf( $statement, $result ) );
		}

		/* translators: %s: Number of forum subscription relationships that would be added to forum meta */
		$result = sprintf( _n( '%s forum subscription relationship would be added to forum meta from user meta in this batch.', '%s forum subscription relationships would be added to forum meta from user meta in this batch.', $total, 'bbpress' ), bbp_number_format( $total ) );

		return array(
			0,
			sprintf( $statement, $result ),
			'complete' => $batch['complete']
		);
	}

	if ( ! empty( $failed_total ) ) {
		/* translators: %s: Number of forum subscription relationships added to forum meta */
		$added    = sprintf( _n( '%s forum subscription relationship added', '%s forum subscription relationships added', $total, 'bbpress' ), bbp_number_format( $total ) );
		/* translators: %s: Number of forum subscription relationships that could not be added */
		$failures = sprintf( _n( '%s forum subscription relationship could not be added', '%s forum subscription relationships could not be added', $failed_total, 'bbpress' ), bbp_number_format( $failed_total ) );
		$result = esc_html__( 'Failed!', 'bbpress' ) . "\n" . $added . ".\n" . $failures . '.';

		return array(
			4,
			sprintf( $statement, $result ),
			'success' => false,
		);
	}

	// Complete results
	/* translators: %s: Number of forum subscription relationships added to forum meta */
	$result = sprintf( _n( '%s forum subscription relationship added to forum meta.', '%s forum subscription relationships added to forum meta.', $total, 'bbpress' ), bbp_number_format( $total ) );

	$status = $batch['complete']
		? esc_html__( 'Complete!', 'bbpress' )
		: esc_html__( 'Batch complete!', 'bbpress' );
	$result = $status . "\n" . esc_html__( 'This batch:', 'bbpress' ) . "\n" . $result;

	return array(
		0,
		sprintf( $statement, $result ),
		'complete' => $batch['complete']
	);
}

/**
 * Remove legacy user meta only when its migration is neither pending nor active.
 *
 * Dry Run only checks eligibility. Run holds the migration's lock while checking
 * its checkpoint and pending status again and deleting its source data.
 *
 * @since 2.7.0 bbPress (r8253)
 *
 * @param string $tool_id Migration tool ID.
 * @param string $key     Legacy user-meta key.
 * @param bool   $dry_run Whether to check eligibility without writes or locks.
 * @return bool|WP_Error True if allowed or deleted, false on deletion failure,
 *                       or an error if the migration is unfinished or active.
 */
function bbp_admin_upgrade_remove_user_meta( $tool_id, $key, $dry_run = false ) {
	$option  = bbp_admin_repair_tool_batch_option_name( $tool_id );
	$blocked = new WP_Error( 'bbp_upgrade_incomplete', esc_html__( 'Finish the upgrade before removing its legacy user data.', 'bbpress' ) );
	$lock    = false;

	if ( ! $dry_run ) {
		$lock = bbp_admin_repair_tool_lock( $tool_id );
		if ( false === $lock ) {
			return $blocked;
		}

		// Recheck current options after acquiring the migration lock
		wp_cache_delete( $option, 'options' );
		wp_cache_delete( '_bbp_db_pending_upgrades', 'options' );
		wp_cache_delete( 'alloptions', 'options' );
		wp_cache_delete( 'notoptions', 'options' );
	}

	try {
		if ( ( false !== get_option( $option, false ) ) || in_array( $tool_id, bbp_get_pending_upgrades(), true ) ) {
			return $blocked;
		}

		return $dry_run ? true : delete_metadata( 'user', false, $key, false, true );
	} finally {
		if ( false !== $lock ) {
			bbp_admin_repair_tool_unlock( $lock );
		}
	}
}

/**
 * Remove favorites data from user meta for bbPress 2.6 and higher.
 *
 * @since 2.6.0 bbPress (r6281)
 * @since 2.7.0 bbPress (r8234) Added Dry Run support.
 * @since 2.7.0 bbPress (r8253) Preserve source data for unfinished migrations.
 *
 * @return array An array of the status code and the message.
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
 * @return array An array of the status code and the message.
 */
function bbp_admin_upgrade_remove_favorites_from_usermeta_process( $dry_run = false ) {

	// Define variables
	$bbp_db = bbp_db();
	$failed = esc_html__( 'Failed!', 'bbpress' );
	$result = esc_html__( '0 favorite user-meta rows to remove.', 'bbpress' );
	$key    = $bbp_db->prefix . '_bbp_favorites';

	if ( $dry_run ) {
		/* translators: %s: Status of the dry-run process */
		$statement = esc_html__( 'Checking favorites in usermeta&hellip; %s', 'bbpress' );
	} else {
		/* translators: %s: Status of the upgrade process */
		$statement = esc_html__( 'Remove favorites from usermeta&hellip; %s', 'bbpress' );
	}

	// Check eligibility without changing stored data or acquiring a lock
	$cleanup = bbp_admin_upgrade_remove_user_meta( 'bbp-user-favorites-move', $key, true );
	if ( is_wp_error( $cleanup ) ) {
		return array(
			4,
			sprintf( $statement, $cleanup->get_error_message() ),
			'success' => false
		);
	}

	// Results
	$query   = "SELECT COUNT(*) FROM {$bbp_db->usermeta} WHERE meta_key = %s";
	$prepare = $bbp_db->prepare( $query, $key );
	$total   = (int) $bbp_db->get_var( $prepare );

	// Bail if favorites could not be read
	if ( ! empty( $bbp_db->last_error ) ) {
		return array(
			2,
			sprintf( $statement, $failed ),
			'success' => false,
		);
	}

	// Bail if no favorites were found
	if ( empty( $total ) ) {
		return array( 1, sprintf( $statement, $result ) );
	}

	if ( $dry_run ) {
		/* translators: %s: Number of favorite user-meta rows that would be deleted */
		$result = sprintf( _n( '%s favorite user-meta row would be deleted.', '%s favorite user-meta rows would be deleted.', $total, 'bbpress' ), bbp_number_format( $total ) );

		return array( 0, sprintf( $statement, $result ) );
	}

	// Recheck eligibility and delete while holding the migration lock
	$cleanup = bbp_admin_upgrade_remove_user_meta( 'bbp-user-favorites-move', $key );
	if ( is_wp_error( $cleanup ) ) {
		return array(
			4,
			sprintf( $statement, $cleanup->get_error_message() ),
			'success' => false
		);
	}
	if ( false === $cleanup ) {
		/* translators: %s: Number of favorite user-meta rows that could not be deleted */
		$result = sprintf( _n( 'Failed! %s favorite user-meta row could not be deleted.', 'Failed! %s favorite user-meta rows could not be deleted.', $total, 'bbpress' ), bbp_number_format( $total ) );

		return array(
			3,
			sprintf( $statement, $result ),
			'success' => false,
		);
	}

	// Complete results
	/* translators: %s: Number of deleted favorite user-meta rows */
	$result = sprintf( _n( 'Complete! %s favorite user-meta row deleted.', 'Complete! %s favorite user-meta rows deleted.', $total, 'bbpress' ), bbp_number_format( $total ) );

	return array( 0, sprintf( $statement, $result ) );
}

/**
 * Remove topic subscriptions data from user meta for bbPress 2.6 and higher.
 *
 * @since 2.6.0 bbPress (r6281)
 * @since 2.7.0 bbPress (r8235) Added Dry Run support.
 * @since 2.7.0 bbPress (r8253) Preserve source data for unfinished migrations.
 *
 * @return array An array of the status code and the message.
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
 * @return array An array of the status code and the message.
 */
function bbp_admin_upgrade_remove_topic_subscriptions_from_usermeta_process( $dry_run = false ) {

	// Define variables
	$bbp_db = bbp_db();
	$failed = esc_html__( 'Failed!', 'bbpress' );
	$result = esc_html__( '0 topic subscription user-meta rows to remove.', 'bbpress' );
	$key    = $bbp_db->prefix . '_bbp_subscriptions';

	if ( $dry_run ) {
		/* translators: %s: Status of the dry-run process */
		$statement = esc_html__( 'Checking topic subscriptions in usermeta&hellip; %s', 'bbpress' );
	} else {
		/* translators: %s: Status of the upgrade process */
		$statement = esc_html__( 'Remove topic subscriptions from usermeta&hellip; %s', 'bbpress' );
	}

	// Check eligibility without changing stored data or acquiring a lock
	$cleanup = bbp_admin_upgrade_remove_user_meta( 'bbp-user-topic-subscriptions-move', $key, true );
	if ( is_wp_error( $cleanup ) ) {
		return array(
			4,
			sprintf( $statement, $cleanup->get_error_message() ),
			'success' => false
		);
	}

	// Results
	$query   = "SELECT COUNT(*) FROM {$bbp_db->usermeta} WHERE meta_key = %s";
	$prepare = $bbp_db->prepare( $query, $key );
	$total   = (int) $bbp_db->get_var( $prepare );

	// Bail if topic subscriptions could not be read
	if ( ! empty( $bbp_db->last_error ) ) {
		return array(
			2,
			sprintf( $statement, $failed ),
			'success' => false,
		);
	}

	// Bail if no topic subscriptions were found
	if ( empty( $total ) ) {
		return array( 1, sprintf( $statement, $result ) );
	}

	if ( $dry_run ) {
		/* translators: %s: Number of topic subscription user-meta rows that would be deleted */
		$result = sprintf( _n( '%s topic subscription user-meta row would be deleted.', '%s topic subscription user-meta rows would be deleted.', $total, 'bbpress' ), bbp_number_format( $total ) );

		return array( 0, sprintf( $statement, $result ) );
	}

	// Recheck eligibility and delete while holding the migration lock
	$cleanup = bbp_admin_upgrade_remove_user_meta( 'bbp-user-topic-subscriptions-move', $key );
	if ( is_wp_error( $cleanup ) ) {
		return array(
			4,
			sprintf( $statement, $cleanup->get_error_message() ),
			'success' => false
		);
	}
	if ( false === $cleanup ) {
		/* translators: %s: Number of topic subscription user-meta rows that could not be deleted */
		$result = sprintf( _n( 'Failed! %s topic subscription user-meta row could not be deleted.', 'Failed! %s topic subscription user-meta rows could not be deleted.', $total, 'bbpress' ), bbp_number_format( $total ) );

		return array(
			3,
			sprintf( $statement, $result ),
			'success' => false,
		);
	}

	// Complete results
	/* translators: %s: Number of deleted topic subscription user-meta rows */
	$result = sprintf( _n( 'Complete! %s topic subscription user-meta row deleted.', 'Complete! %s topic subscription user-meta rows deleted.', $total, 'bbpress' ), bbp_number_format( $total ) );

	return array( 0, sprintf( $statement, $result ) );
}

/**
 * Remove forum subscriptions data from user meta for bbPress 2.6 and higher.
 *
 * @since 2.6.0 bbPress (r6281)
 * @since 2.7.0 bbPress (r8236) Added Dry Run support.
 * @since 2.7.0 bbPress (r8253) Preserve source data for unfinished migrations.
 *
 * @return array An array of the status code and the message.
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
 * @return array An array of the status code and the message.
 */
function bbp_admin_upgrade_remove_forum_subscriptions_from_usermeta_process( $dry_run = false ) {

	// Define variables
	$bbp_db = bbp_db();
	$failed = esc_html__( 'Failed!', 'bbpress' );
	$result = esc_html__( '0 forum subscription user-meta rows to remove.', 'bbpress' );
	$key    = $bbp_db->prefix . '_bbp_forum_subscriptions';

	if ( $dry_run ) {
		/* translators: %s: Status of the dry-run process */
		$statement = esc_html__( 'Checking forum subscriptions in usermeta&hellip; %s', 'bbpress' );
	} else {
		/* translators: %s: Status of the upgrade process */
		$statement = esc_html__( 'Remove forum subscriptions from usermeta&hellip; %s', 'bbpress' );
	}

	// Check eligibility without changing stored data or acquiring a lock
	$cleanup = bbp_admin_upgrade_remove_user_meta( 'bbp-user-forum-subscriptions-move', $key, true );
	if ( is_wp_error( $cleanup ) ) {
		return array(
			4,
			sprintf( $statement, $cleanup->get_error_message() ),
			'success' => false
		);
	}

	// Query
	$query   = "SELECT COUNT(*) FROM {$bbp_db->usermeta} WHERE meta_key = %s";
	$prepare = $bbp_db->prepare( $query, $key );
	$total   = (int) $bbp_db->get_var( $prepare );

	// Bail if forum subscriptions could not be read
	if ( ! empty( $bbp_db->last_error ) ) {
		return array(
			2,
			sprintf( $statement, $failed ),
			'success' => false,
		);
	}

	// Bail if no forum subscriptions were found
	if ( empty( $total ) ) {
		return array( 1, sprintf( $statement, $result ) );
	}

	if ( $dry_run ) {
		/* translators: %s: Number of forum subscription user-meta rows that would be deleted */
		$result = sprintf( _n( '%s forum subscription user-meta row would be deleted.', '%s forum subscription user-meta rows would be deleted.', $total, 'bbpress' ), bbp_number_format( $total ) );

		return array( 0, sprintf( $statement, $result ) );
	}

	// Recheck eligibility and delete while holding the migration lock
	$cleanup = bbp_admin_upgrade_remove_user_meta( 'bbp-user-forum-subscriptions-move', $key );
	if ( is_wp_error( $cleanup ) ) {
		return array(
			4,
			sprintf( $statement, $cleanup->get_error_message() ),
			'success' => false
		);
	}
	if ( false === $cleanup ) {
		/* translators: %s: Number of forum subscription user-meta rows that could not be deleted */
		$result = sprintf( _n( 'Failed! %s forum subscription user-meta row could not be deleted.', 'Failed! %s forum subscription user-meta rows could not be deleted.', $total, 'bbpress' ), bbp_number_format( $total ) );

		return array(
			3,
			sprintf( $statement, $result ),
			'success' => false,
		);
	}

	// Complete results
	/* translators: %s: Number of deleted forum subscription user-meta rows */
	$result = sprintf( _n( 'Complete! %s forum subscription user-meta row deleted.', 'Complete! %s forum subscription user-meta rows deleted.', $total, 'bbpress' ), bbp_number_format( $total ) );

	return array( 0, sprintf( $statement, $result ) );
}
