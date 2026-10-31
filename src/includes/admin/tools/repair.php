<?php

/**
 * bbPress Admin Repair Page.
 *
 * @package bbPress
 * @subpackage Administration
 */

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

/** Helpers *******************************************************************/

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
						<th scope="col" id="components" class="manage-column column-components">
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

						<?php foreach ( $tools as $item ) : ?>

							<tr id="bbp-repair-tools" class="inactive">
								<th scope="row" class="check-column">
									<label class="screen-reader-text" for="<?php echo esc_attr( str_replace( '_', '-', $item['id'] ) ); ?>"></label>
									<input type="checkbox" name="checked[]" value="<?php echo esc_attr( $item['id'] ); ?>" id="<?php echo esc_attr( str_replace( '_', '-', $item['id'] ) ); ?>">
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
 *
 * @return array An array of the status code and the message.
 */
function bbp_admin_repair_topic_reply_count() {
	return bbp_admin_repair_topic_reply_count_process();
}

/**
 * Preview recounting topic replies.
 *
 * @since 2.7.0 bbPress (r8222)
 *
 * @return array An array of the status code and the message.
 */
function bbp_admin_repair_topic_reply_count_dry_run() {
	return bbp_admin_repair_topic_reply_count_process( true );
}

/**
 * Recount or preview topic replies.
 *
 * @since 2.7.0 bbPress (r8222)
 *
 * @param bool $dry_run Whether to preview changes without updating data.
 * @return array An array of the status code and the message.
 */
function bbp_admin_repair_topic_reply_count_process( $dry_run = false ) {

	// Define variables
	$bbp_db = bbp_db();
	$result = esc_html__( 'Failed!', 'bbpress' );

	if ( $dry_run ) {
		/* translators: %s: Status of the dry-run process */
		$statement = esc_html__( 'Checking the number of replies in each topic&hellip; %s', 'bbpress' );
	} else {
		/* translators: %s: Status of the counting process */
		$statement = esc_html__( 'Counting the number of replies in each topic&hellip; %s', 'bbpress' );
	}

	// Post types and status
	$tpt                = bbp_get_topic_post_type();
	$rpt                = bbp_get_reply_post_type();
	$sql_topic_statuses = "'" . implode( "','", bbp_get_public_topic_statuses() ) . "'";
	$sql_reply_statuses = "'" . implode( "','", bbp_get_public_reply_statuses() ) . "'";

	$counts_from_sql = "FROM `{$bbp_db->posts}` AS `topics`
		LEFT JOIN `{$bbp_db->posts}` AS `replies`
			ON `replies`.`post_parent` = `topics`.`ID`
			AND `replies`.`post_status` IN ( {$sql_reply_statuses} )
			AND `replies`.`post_type` = '{$rpt}'
		WHERE `topics`.`post_type` = '{$tpt}'
			AND `topics`.`post_status` IN ( {$sql_topic_statuses} )
		GROUP BY `topics`.`ID`";
	$expected_counts_sql = "SELECT `topics`.`ID` AS `topic_id`, COUNT(`replies`.`ID`) AS `reply_count`
		{$counts_from_sql}";

	if ( ! $dry_run ) {

		// Delete the meta key _bbp_reply_count for each topic
		$sql_delete = "DELETE `postmeta` FROM `{$bbp_db->postmeta}` AS `postmeta`
							LEFT JOIN `{$bbp_db->posts}` AS `posts` ON `posts`.`ID` = `postmeta`.`post_id`
							WHERE `posts`.`post_type` = '{$tpt}'
							AND `postmeta`.`meta_key` = '_bbp_reply_count'";

		if ( false === $bbp_db->query( $sql_delete ) ) {
			return array( 1, sprintf( $statement, $result ) );
		}

		// Recalculate the meta key _bbp_reply_count for each topic
		$sql = "INSERT INTO `{$bbp_db->postmeta}` (`post_id`, `meta_key`, `meta_value`) (
			SELECT `topics`.`ID` AS `post_id`, '_bbp_reply_count' AS `meta_key`, COUNT(`replies`.`ID`) AS `meta_value`
			{$counts_from_sql})";

		if ( false === $bbp_db->query( $sql ) ) {
			return array( 2, sprintf( $statement, $result ) );
		}

		bbp_admin_repair_clean_post_meta_cache( $tpt );

		return array( 0, sprintf( $statement, esc_html__( 'Complete!', 'bbpress' ) ) );
	}

	$stored_counts_sql = "SELECT `postmeta`.`post_id` AS `topic_id`, COUNT(*) AS `row_count`, MIN(`postmeta`.`meta_value`) AS `reply_count`
		FROM `{$bbp_db->postmeta}` AS `postmeta`
		INNER JOIN `{$bbp_db->posts}` AS `topics`
			ON `topics`.`ID` = `postmeta`.`post_id`
			AND `topics`.`post_type` = '{$tpt}'
		WHERE `postmeta`.`meta_key` = '_bbp_reply_count'
		GROUP BY `postmeta`.`post_id`";

	/*
	 * Compare expected and stored topic reply counts in both directions. A topic
	 * must have exactly one stored row containing the calculated count.
	 */
	$repair_sql = "SELECT COUNT(DISTINCT repairs.topic_id)
		FROM (
			SELECT expected.topic_id
			FROM ( {$expected_counts_sql} ) AS expected
			LEFT JOIN ( {$stored_counts_sql} ) AS stored
				ON stored.topic_id = expected.topic_id
			WHERE stored.topic_id IS NULL
				OR stored.row_count <> 1
				OR NOT ( CAST(stored.reply_count AS BINARY) <=> CAST(expected.reply_count AS BINARY) )
			UNION ALL
			SELECT stored.topic_id
			FROM ( {$stored_counts_sql} ) AS stored
			LEFT JOIN ( {$expected_counts_sql} ) AS expected
				ON expected.topic_id = stored.topic_id
			WHERE expected.topic_id IS NULL
		) AS repairs";
	$repair_count = (int) $bbp_db->get_var( $repair_sql );

	if ( ! empty( $bbp_db->last_error ) ) {
		return array( 3, sprintf( $statement, $result ) );
	}

	if ( empty( $repair_count ) ) {
		$result = esc_html__( 'No topic reply counts to repair.', 'bbpress' );
		return array( 1, sprintf( $statement, $result ) );
	}

	/* translators: %d: Number of topics whose reply counts would be repaired */
	$result = sprintf( _n( 'Dry run complete! Reply counts would be repaired in %d topic.', 'Dry run complete! Reply counts would be repaired in %d topics.', $repair_count, 'bbpress' ), $repair_count );

	return array( 0, sprintf( $statement, $result ) );
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
		$result = esc_html__( 'No topic engagements or voice counts to repair.', 'bbpress' );
		return array( 1, sprintf( $statement, $result ) );
	}

	/* translators: %d: Number of topics whose engagements would be repaired */
	$engagements = sprintf( _n( 'Dry run complete! Engagements would be repaired in %d topic.', 'Dry run complete! Engagements would be repaired in %d topics.', $engagement_repair_count, 'bbpress' ), $engagement_repair_count );
	/* translators: %d: Number of topics whose voice counts would be repaired */
	$voice_counts = sprintf( _n( 'Voice counts would be repaired in %d topic.', 'Voice counts would be repaired in %d topics.', $voice_repair_count, 'bbpress' ), $voice_repair_count );

	return array( 0, sprintf( $statement, $engagements . ' ' . $voice_counts ) );
}

/**
 * Recount non-public replies per topic (pending/spammed/trashed).
 *
 * @since 2.1.0 bbPress (r3884)
 * @since 2.7.0 bbPress (r8223) Added Dry Run support.
 *
 * @return array An array of the status code and the message.
 */
function bbp_admin_repair_topic_hidden_reply_count() {
	return bbp_admin_repair_topic_hidden_reply_count_process();
}

/**
 * Preview recounting non-public replies per topic.
 *
 * @since 2.7.0 bbPress (r8223)
 *
 * @return array An array of the status code and the message.
 */
function bbp_admin_repair_topic_hidden_reply_count_dry_run() {
	return bbp_admin_repair_topic_hidden_reply_count_process( true );
}

/**
 * Recount or preview non-public replies per topic.
 *
 * @since 2.7.0 bbPress (r8223)
 *
 * @param bool $dry_run Whether to preview changes without updating data.
 * @return array An array of the status code and the message.
 */
function bbp_admin_repair_topic_hidden_reply_count_process( $dry_run = false ) {

	// Define variables
	$bbp_db = bbp_db();
	$result = esc_html__( 'Failed!', 'bbpress' );

	if ( $dry_run ) {
		/* translators: %s: Status of the dry-run process */
		$statement = esc_html__( 'Checking the number of pending, spammed, and trashed replies in each topic&hellip; %s', 'bbpress' );
	} else {
		/* translators: %s: Status of the counting process */
		$statement = esc_html__( 'Counting the number of pending, spammed, and trashed replies in each topic&hellip; %s', 'bbpress' );
	}

	$tpt = bbp_get_topic_post_type();
	$rpt = bbp_get_reply_post_type();

	// Reply statuses
	$sta = bbp_get_non_public_reply_statuses();

	// SQL
	$sql_status = "'" . implode( "','", $sta ) . "'";
	$counts_from_sql = "FROM `{$bbp_db->posts}` AS `replies`
		INNER JOIN `{$bbp_db->posts}` AS `topics`
			ON `topics`.`ID` = `replies`.`post_parent`
			AND `topics`.`post_type` = '{$tpt}'
		WHERE `replies`.`post_type` = '{$rpt}'
			AND `replies`.`post_status` IN ({$sql_status})
		GROUP BY `topics`.`ID`";
	$expected_counts_sql = "SELECT `topics`.`ID` AS `topic_id`, COUNT(`replies`.`ID`) AS `hidden_reply_count`
		{$counts_from_sql}";

	if ( ! $dry_run ) {
		$sql_delete = "DELETE `postmeta` FROM `{$bbp_db->postmeta}` AS `postmeta`
							LEFT JOIN `{$bbp_db->posts}` AS `posts` ON `posts`.`ID` = `postmeta`.`post_id`
							WHERE `posts`.`post_type` = '{$tpt}'
							AND `postmeta`.`meta_key` = '_bbp_reply_count_hidden'";
		if ( false === $bbp_db->query( $sql_delete ) ) {
			return array( 1, sprintf( $statement, $result ) );
		}

		$sql = "INSERT INTO `{$bbp_db->postmeta}` (`post_id`, `meta_key`, `meta_value`) (
			SELECT `topics`.`ID`, '_bbp_reply_count_hidden', COUNT(`replies`.`ID`) AS `meta_value`
			{$counts_from_sql})";
		if ( false === $bbp_db->query( $sql ) ) {
			return array( 2, sprintf( $statement, $result ) );
		}

		bbp_admin_repair_clean_post_meta_cache( $tpt );

		return array( 0, sprintf( $statement, esc_html__( 'Complete!', 'bbpress' ) ) );
	}

	$stored_counts_sql = "SELECT `postmeta`.`post_id` AS `topic_id`, COUNT(*) AS `row_count`, MIN(`postmeta`.`meta_value`) AS `hidden_reply_count`
		FROM `{$bbp_db->postmeta}` AS `postmeta`
		INNER JOIN `{$bbp_db->posts}` AS `topics`
			ON `topics`.`ID` = `postmeta`.`post_id`
			AND `topics`.`post_type` = '{$tpt}'
		WHERE `postmeta`.`meta_key` = '_bbp_reply_count_hidden'
		GROUP BY `postmeta`.`post_id`";

	/*
	 * Compare expected and stored hidden reply counts in both directions. A topic
	 * with hidden replies must have exactly one row containing the calculated count.
	 * Topics without hidden replies have no expected row because Run removes their
	 * stored meta, including zero-valued rows.
	 */
	$repair_sql = "SELECT COUNT(DISTINCT repairs.topic_id)
		FROM (
			SELECT expected.topic_id
			FROM ( {$expected_counts_sql} ) AS expected
			LEFT JOIN ( {$stored_counts_sql} ) AS stored
				ON stored.topic_id = expected.topic_id
			WHERE stored.topic_id IS NULL
				OR stored.row_count <> 1
				OR NOT ( CAST(stored.hidden_reply_count AS BINARY) <=> CAST(expected.hidden_reply_count AS BINARY) )
			UNION ALL
			SELECT stored.topic_id
			FROM ( {$stored_counts_sql} ) AS stored
			LEFT JOIN ( {$expected_counts_sql} ) AS expected
				ON expected.topic_id = stored.topic_id
			WHERE expected.topic_id IS NULL
		) AS repairs";
	$repair_count = (int) $bbp_db->get_var( $repair_sql );

	if ( ! empty( $bbp_db->last_error ) ) {
		return array( 3, sprintf( $statement, $result ) );
	}

	if ( empty( $repair_count ) ) {
		$result = esc_html__( 'No hidden reply counts to repair.', 'bbpress' );
		return array( 1, sprintf( $statement, $result ) );
	}

	/* translators: %d: Number of topics whose hidden reply counts would be repaired */
	$result = sprintf( _n( 'Dry run complete! Hidden reply counts would be repaired in %d topic.', 'Dry run complete! Hidden reply counts would be repaired in %d topics.', $repair_count, 'bbpress' ), $repair_count );

	return array( 0, sprintf( $statement, $result ) );
}

/**
 * Recount forum topics.
 *
 * @since 2.1.0 bbPress (r3884)
 *
 * @return array An array of the status code and the message.
 */
function bbp_admin_repair_forum_topic_count() {

	// Define variables
	$bbp_db    = bbp_db();
	/* translators: %s: Status of the counting process */
	$statement = esc_html__( 'Counting the number of topics in each forum&hellip; %s', 'bbpress' );
	$result    = esc_html__( 'Failed!', 'bbpress' );

	$fpt = bbp_get_forum_post_type();

	$sql_delete = "DELETE `postmeta` FROM `{$bbp_db->postmeta}` AS `postmeta`
						LEFT JOIN `{$bbp_db->posts}` AS `posts` ON `posts`.`ID` = `postmeta`.`post_id`
						WHERE `posts`.`post_type` = '{$fpt}'
						AND `postmeta`.`meta_key` IN ( '_bbp_topic_count', '_bbp_total_topic_count', '_bbp_topic_count_hidden', '_bbp_total_topic_count_hidden' )";
	if ( false === $bbp_db->query( $sql_delete ) ) {
		return array( 1, sprintf( $statement, $result ) );
	}

	$forums = get_posts(
		array(
			'fields'      => 'ids',
			'post_type'   => $fpt,
			'post_status' => bbp_get_countable_forum_statuses(),
			'numberposts' => -1
		)
	);

	if ( ! empty( $forums ) ) {
		foreach ( $forums as $forum_id ) {
			bbp_update_forum_topic_count( $forum_id );
			bbp_update_forum_topic_count_hidden( $forum_id );
		}
	} else {
		return array( 2, sprintf( $statement, $result ) );
	}

	return array( 0, sprintf( $statement, esc_html__( 'Complete!', 'bbpress' ) ) );
}

/**
 * Recount forum subforums.
 *
 * @since 2.7.0 bbPress (r7971)
 *
 * @return array An array of the status code and the message.
 */
function bbp_admin_repair_forum_subforum_count() {

	// Define variables
	/* translators: %s: Status of the counting process */
	$statement = esc_html__( 'Counting the number of subforums in each forum&hellip; %s', 'bbpress' );
	$result    = esc_html__( 'Failed!', 'bbpress' );
	$forums    = get_posts(
		array(
			'fields'      => 'ids',
			'post_type'   => bbp_get_forum_post_type(),
			'post_status' => array_keys( get_post_stati() ),
			'numberposts' => -1
		)
	);

	if ( empty( $forums ) ) {
		return array( 1, sprintf( $statement, $result ) );
	}

	foreach ( $forums as $forum_id ) {
		if ( false === bbp_update_forum_subforum_count( $forum_id ) ) {
			return array( 2, sprintf( $statement, $result ) );
		}
	}

	return array( 0, sprintf( $statement, esc_html__( 'Complete!', 'bbpress' ) ) );
}

/**
 * Recount topic in each topic-tag.
 *
 * @since 2.6.0 bbPress (r6256)
 *
 * @return array An array of the status code and the message.
 */
function bbp_admin_repair_topic_tag_count() {

	// Define variables
	/* translators: %s: Status of the counting process */
	$statement = esc_html__( 'Counting the number of topics in each topic-tag&hellip; %s', 'bbpress' );
	$result    = esc_html__( 'Failed!', 'bbpress' );
	$tax_id    = bbp_get_topic_tag_tax_id();
	$terms     = get_terms(
		array(
			'taxonomy'   => $tax_id,
			'hide_empty' => false
		)
	);

	// Get the term IDs
	$tt_ids   = wp_list_pluck( $terms, 'term_taxonomy_id' );
	$ints     = array_map( 'intval', $tt_ids );
	$taxonomy = get_taxonomy( $tax_id );

	// Bail if taxonomy does not exist
	if ( empty( $taxonomy ) ) {
		return array( 1, sprintf( $statement, $result ) );
	}

	// Custom callback
	if ( ! empty( $taxonomy->update_count_callback ) ) {

		// Bail if callback is not callable
		if ( ! is_callable( $taxonomy->update_count_callback ) ) {
			return array( 1, sprintf( $statement, $result ) );
		}

		call_user_func( $taxonomy->update_count_callback, $ints, $taxonomy );

	// Generic callback fallback
	} else {
		_update_post_term_count( $ints, $taxonomy );
	}

	// Bust the cache
	clean_term_cache( $ints, '', false );

	return array( 0, sprintf( $statement, esc_html__( 'Complete!', 'bbpress' ) ) );
}

/**
 * Recount forum replies.
 *
 * @since 2.1.0 bbPress (r3884)
 *
 * @return array An array of the status code and the message.
 */
function bbp_admin_repair_forum_reply_count() {

	// Define variables
	$bbp_db    = bbp_db();
	/* translators: %s: Status of the counting process */
	$statement = esc_html__( 'Counting the number of replies in each forum&hellip; %s', 'bbpress' );
	$result    = esc_html__( 'Failed!', 'bbpress' );

	// Post type
	$fpt = bbp_get_forum_post_type();

	// Delete the meta keys _bbp_reply_count and _bbp_total_reply_count for each forum
	$sql_delete = "DELETE `postmeta` FROM `{$bbp_db->postmeta}` AS `postmeta`
						LEFT JOIN `{$bbp_db->posts}` AS `posts` ON `posts`.`ID` = `postmeta`.`post_id`
						WHERE `posts`.`post_type` = '{$fpt}'
						AND `postmeta`.`meta_key` IN ( '_bbp_reply_count', '_bbp_total_reply_count' )";

	if ( false === $bbp_db->query( $sql_delete ) ) {
		return array( 1, sprintf( $statement, $result ) );
	}

	// Recalculate the metas key _bbp_reply_count and _bbp_total_reply_count for each forum
	$forums = get_posts(
		array(
			'fields'      => 'ids',
			'post_type'   => $fpt,
			'post_status' => bbp_get_countable_forum_statuses(),
			'numberposts' => -1
		)
	);

	if ( ! empty( $forums ) ) {
		foreach ( $forums as $forum_id ) {
			bbp_update_forum_reply_count( $forum_id );
		}
	} else {
		return array( 2, sprintf( $statement, $result ) );
	}

	return array( 0, sprintf( $statement, esc_html__( 'Complete!', 'bbpress' ) ) );
}

/**
 * Recount non-public forum replies.
 *
 * @since 2.6.0 bbPress (r6933)
 * @since 2.6.0 bbPress (r6933) Rename to match the topic reply recount function
 *
 * @return array An array of the status code and the message.
 */
function bbp_admin_repair_forum_hidden_reply_count() {

	// Define variables
	$bbp_db    = bbp_db();
	/* translators: %s: Status of the counting process */
	$statement = esc_html__( 'Counting the number of pending, spammed, and trashed replies in each forum&hellip; %s', 'bbpress' );
	$result    = esc_html__( 'Failed!', 'bbpress' );

	// Post type
	$fpt = bbp_get_forum_post_type();

	// Delete the meta keys _bbp_reply_count and _bbp_total_reply_count for each forum
	$sql_delete = "DELETE `postmeta` FROM `{$bbp_db->postmeta}` AS `postmeta`
						LEFT JOIN `{$bbp_db->posts}` AS `posts` ON `posts`.`ID` = `postmeta`.`post_id`
						WHERE `posts`.`post_type` = '{$fpt}'
						AND `postmeta`.`meta_key` IN ( '_bbp_reply_count_hidden', '_bbp_total_reply_count_hidden' )";

	if ( false === $bbp_db->query( $sql_delete ) ) {
		return array( 1, sprintf( $statement, $result ) );
	}

	// Recalculate the metas key _bbp_reply_count and _bbp_total_reply_count for each forum
	$forums = get_posts(
		array(
			'fields'      => 'ids',
			'post_type'   => $fpt,
			'post_status' => bbp_get_countable_forum_statuses(),
			'numberposts' => -1
		)
	);

	if ( ! empty( $forums ) ) {
		foreach ( $forums as $forum_id ) {
			bbp_update_forum_reply_count_hidden( $forum_id );
		}
	} else {
		return array( 2, sprintf( $statement, $result ) );
	}

	return array( 0, sprintf( $statement, esc_html__( 'Complete!', 'bbpress' ) ) );
}

/**
 * Recount topics by the users.
 *
 * @since 2.1.0 bbPress (r3890)
 * @since 2.7.0 bbPress (r8224) Added Dry Run support.
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

	// Define variables
	$bbp_db = bbp_db();
	$result = esc_html__( 'Failed!', 'bbpress' );

	if ( $dry_run ) {
		/* translators: %s: Status of the dry-run process */
		$statement = esc_html__( 'Checking the number of topics each user has created&hellip; %s', 'bbpress' );
	} else {
		/* translators: %s: Status of the counting process */
		$statement = esc_html__( 'Counting the number of topics each user has created&hellip; %s', 'bbpress' );
	}

	$sql_type   = bbp_get_topic_post_type();
	$sql_status = "'" . implode( "','", bbp_get_public_topic_statuses() ) . "'";
	$key        = $bbp_db->prefix . '_bbp_topic_count';
	$counts_from_sql = "FROM `{$bbp_db->posts}`
		WHERE `post_type` = '{$sql_type}'
			AND `post_status` IN ({$sql_status})
			AND `post_author` > 0
		GROUP BY `post_author`";

	if ( $dry_run ) {
		$expected_counts_sql = "SELECT `post_author` AS `user_id`, COUNT(*) AS `topic_count`
			{$counts_from_sql}";
		$stored_counts_sql = "SELECT `user_id`, COUNT(*) AS `row_count`, MIN(`meta_value`) AS `topic_count`
			FROM `{$bbp_db->usermeta}`
			WHERE `meta_key` = '{$key}'
			GROUP BY `user_id`";

		/*
		 * Compare expected and stored user topic counts in both directions. A user
		 * with public topics must have exactly one row containing the calculated count.
		 */
		$repair_sql = "SELECT COUNT(DISTINCT repairs.user_id)
			FROM (
				SELECT expected.user_id
				FROM ( {$expected_counts_sql} ) AS expected
				LEFT JOIN ( {$stored_counts_sql} ) AS stored
					ON stored.user_id = expected.user_id
				WHERE stored.user_id IS NULL
					OR stored.row_count <> 1
					OR NOT ( CAST(stored.topic_count AS BINARY) <=> CAST(expected.topic_count AS BINARY) )
				UNION ALL
				SELECT stored.user_id
				FROM ( {$stored_counts_sql} ) AS stored
				LEFT JOIN ( {$expected_counts_sql} ) AS expected
					ON expected.user_id = stored.user_id
				WHERE expected.user_id IS NULL
			) AS repairs";
		$repair_count = (int) $bbp_db->get_var( $repair_sql );

		if ( ! empty( $bbp_db->last_error ) ) {
			return array( 5, sprintf( $statement, $result ) );
		}

		if ( empty( $repair_count ) ) {
			$result = esc_html__( 'No user topic counts to repair.', 'bbpress' );
			return array( 1, sprintf( $statement, $result ) );
		}

		/* translators: %d: Number of users whose topic counts would be repaired */
		$result = sprintf( _n( 'Dry run complete! Topic counts would be repaired for %d user.', 'Dry run complete! Topic counts would be repaired for %d users.', $repair_count, 'bbpress' ), $repair_count );

		return array( 0, sprintf( $statement, $result ) );
	}

	$sql_select  = "SELECT `post_author`, COUNT(*) AS `_count` {$counts_from_sql}";
	$insert_rows = $bbp_db->get_results( $sql_select );

	if ( ! empty( $bbp_db->last_error ) ) {
		return array( 1, sprintf( $statement, $result ) );
	}

	$user_ids      = (array) $bbp_db->get_col( "SELECT `user_id` FROM `{$bbp_db->usermeta}` WHERE `meta_key` = '{$key}'" );
	$insert_values = array();

	if ( ! empty( $bbp_db->last_error ) ) {
		return array( 2, sprintf( $statement, $result ) );
	}

	foreach ( $insert_rows as $insert_row ) {
		$user_id         = (int) $insert_row->post_author;
		$user_ids[]      = $user_id;
		$insert_values[] = $bbp_db->prepare( '(%d, %s, %d)', $user_id, $key, (int) $insert_row->_count );
	}

	$sql_delete = "DELETE FROM `{$bbp_db->usermeta}` WHERE `meta_key` = '{$key}'";
	if ( false === $bbp_db->query( $sql_delete ) ) {
		return array( 3, sprintf( $statement, $result ) );
	}

	$insert_error = false;
	foreach ( array_chunk( $insert_values, 10000 ) as $chunk ) {
		$chunk = "\n" . implode( ",\n", $chunk );
		$sql_insert = "INSERT INTO `{$bbp_db->usermeta}` (`user_id`, `meta_key`, `meta_value`) VALUES {$chunk}";

		if ( false === $bbp_db->query( $sql_insert ) ) {
			$insert_error = true;
			break;
		}
	}

	// Clear cached counts for users affected by the direct database update
	$user_ids = wp_parse_id_list( $user_ids );
	if ( ! empty( $user_ids ) ) {
		wp_cache_delete_multiple( $user_ids, 'user_meta' );
	}

	if ( $insert_error ) {
		return array( 4, sprintf( $statement, $result ) );
	}

	return array( 0, sprintf( $statement, esc_html__( 'Complete!', 'bbpress' ) ) );
}

/**
 * Recount topic replied by the users.
 *
 * @since 2.1.0 bbPress (r3884)
 * @since 2.7.0 bbPress (r8225) Added Dry Run support.
 *
 * @return array An array of the status code and the message.
 */
function bbp_admin_repair_user_reply_count() {
	return bbp_admin_repair_user_reply_count_process();
}

/**
 * Preview recounting topics replied to by the users.
 *
 * @since 2.7.0 bbPress (r8225)
 *
 * @return array An array of the status code and the message.
 */
function bbp_admin_repair_user_reply_count_dry_run() {
	return bbp_admin_repair_user_reply_count_process( true );
}

/**
 * Recount or preview topics replied to by the users.
 *
 * @since 2.7.0 bbPress (r8225)
 *
 * @param bool $dry_run Whether to preview changes without updating data.
 * @return array An array of the status code and the message.
 */
function bbp_admin_repair_user_reply_count_process( $dry_run = false ) {

	// Define variables
	$bbp_db = bbp_db();
	$result = esc_html__( 'Failed!', 'bbpress' );

	if ( $dry_run ) {
		/* translators: %s: Status of the dry-run process */
		$statement = esc_html__( 'Checking the number of topics to which each user has replied&hellip; %s', 'bbpress' );
	} else {
		/* translators: %s: Status of the counting process */
		$statement = esc_html__( 'Counting the number of topics to which each user has replied&hellip; %s', 'bbpress' );
	}

	$sql_type   = bbp_get_reply_post_type();
	$sql_status = "'" . implode( "','", bbp_get_public_reply_statuses() ) . "'";
	$key        = $bbp_db->prefix . '_bbp_reply_count';
	$counts_from_sql = "FROM `{$bbp_db->posts}`
		WHERE `post_type` = '{$sql_type}'
			AND `post_status` IN ({$sql_status})
			AND `post_author` > 0
		GROUP BY `post_author`";

	if ( $dry_run ) {
		$expected_counts_sql = "SELECT `post_author` AS `user_id`, COUNT(*) AS `reply_count`
			{$counts_from_sql}";
		$stored_counts_sql = "SELECT `user_id`, COUNT(*) AS `row_count`, MIN(`meta_value`) AS `reply_count`
			FROM `{$bbp_db->usermeta}`
			WHERE `meta_key` = '{$key}'
			GROUP BY `user_id`";

		/*
		 * Compare expected and stored user reply counts in both directions. A user
		 * with public replies must have exactly one row containing the calculated count.
		 */
		$repair_sql = "SELECT COUNT(DISTINCT repairs.user_id)
			FROM (
				SELECT expected.user_id
				FROM ( {$expected_counts_sql} ) AS expected
				LEFT JOIN ( {$stored_counts_sql} ) AS stored
					ON stored.user_id = expected.user_id
				WHERE stored.user_id IS NULL
					OR stored.row_count <> 1
					OR NOT ( CAST(stored.reply_count AS BINARY) <=> CAST(expected.reply_count AS BINARY) )
				UNION ALL
				SELECT stored.user_id
				FROM ( {$stored_counts_sql} ) AS stored
				LEFT JOIN ( {$expected_counts_sql} ) AS expected
					ON expected.user_id = stored.user_id
				WHERE expected.user_id IS NULL
			) AS repairs";
		$repair_count = (int) $bbp_db->get_var( $repair_sql );

		if ( ! empty( $bbp_db->last_error ) ) {
			return array( 5, sprintf( $statement, $result ) );
		}

		if ( empty( $repair_count ) ) {
			$result = esc_html__( 'No user reply counts to repair.', 'bbpress' );
			return array( 1, sprintf( $statement, $result ) );
		}

		/* translators: %d: Number of users whose reply counts would be repaired */
		$result = sprintf( _n( 'Dry run complete! Reply counts would be repaired for %d user.', 'Dry run complete! Reply counts would be repaired for %d users.', $repair_count, 'bbpress' ), $repair_count );

		return array( 0, sprintf( $statement, $result ) );
	}

	$sql_select  = "SELECT `post_author`, COUNT(*) AS `_count` {$counts_from_sql}";
	$insert_rows = $bbp_db->get_results( $sql_select );

	if ( ! empty( $bbp_db->last_error ) ) {
		return array( 1, sprintf( $statement, $result ) );
	}

	$user_ids      = (array) $bbp_db->get_col( "SELECT `user_id` FROM `{$bbp_db->usermeta}` WHERE `meta_key` = '{$key}'" );
	$insert_values = array();

	if ( ! empty( $bbp_db->last_error ) ) {
		return array( 2, sprintf( $statement, $result ) );
	}

	foreach ( $insert_rows as $insert_row ) {
		$user_id         = (int) $insert_row->post_author;
		$user_ids[]      = $user_id;
		$insert_values[] = $bbp_db->prepare( '(%d, %s, %d)', $user_id, $key, (int) $insert_row->_count );
	}

	$sql_delete = "DELETE FROM `{$bbp_db->usermeta}` WHERE `meta_key` = '{$key}'";
	if ( false === $bbp_db->query( $sql_delete ) ) {
		return array( 3, sprintf( $statement, $result ) );
	}

	$insert_error = false;
	foreach ( array_chunk( $insert_values, 10000 ) as $chunk ) {
		$chunk = "\n" . implode( ",\n", $chunk );
		$sql_insert = "INSERT INTO `{$bbp_db->usermeta}` (`user_id`, `meta_key`, `meta_value`) VALUES {$chunk}";

		if ( false === $bbp_db->query( $sql_insert ) ) {
			$insert_error = true;
			break;
		}
	}

	// Clear cached counts for users affected by the direct database update
	$user_ids = wp_parse_id_list( $user_ids );
	if ( ! empty( $user_ids ) ) {
		wp_cache_delete_multiple( $user_ids, 'user_meta' );
	}

	if ( $insert_error ) {
		return array( 4, sprintf( $statement, $result ) );
	}

	return array( 0, sprintf( $statement, esc_html__( 'Complete!', 'bbpress' ) ) );
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
		return array( 1, sprintf( $statement, $result ) );
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
			? esc_html__( 'No user favorites to repair.', 'bbpress' )
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
			$result = esc_html__( 'No user favorites to repair.', 'bbpress' );
			return array( 1, sprintf( $statement, $result ) );
		}

		/* translators: %d: Number of users whose favorites would be repaired */
		$result = sprintf( _n( 'Dry run complete! Favorites would be repaired for %d user.', 'Dry run complete! Favorites would be repaired for %d users.', $repair_count, 'bbpress' ), $repair_count );

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
		return array( 1, sprintf( $statement, $result ) );
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
			? esc_html__( 'No user topic subscriptions to repair.', 'bbpress' )
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
			$result = esc_html__( 'No user topic subscriptions to repair.', 'bbpress' );
			return array( 1, sprintf( $statement, $result ) );
		}

		/* translators: %d: Number of users whose topic subscriptions would be repaired */
		$result = sprintf( _n( 'Dry run complete! Topic subscriptions would be repaired for %d user.', 'Dry run complete! Topic subscriptions would be repaired for %d users.', $repair_count, 'bbpress' ), $repair_count );

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
		return array( 1, sprintf( $statement, $result ) );
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
			? esc_html__( 'No user forum subscriptions to repair.', 'bbpress' )
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
			$result = esc_html__( 'No user forum subscriptions to repair.', 'bbpress' );
			return array( 1, sprintf( $statement, $result ) );
		}

		/* translators: %d: Number of users whose forum subscriptions would be repaired */
		$result = sprintf( _n( 'Dry run complete! Forum subscriptions would be repaired for %d user.', 'Dry run complete! Forum subscriptions would be repaired for %d users.', $repair_count, 'bbpress' ), $repair_count );

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
			return array( 1, sprintf( $statement, esc_html__( 'No user forum roles to repair.', 'bbpress' ) ) );
		}

		/* translators: %s: Number of users whose forum role would be updated */
		$result = sprintf( _n( 'Dry run complete! %s user would be updated.', 'Dry run complete! %s users would be updated.', $changed, 'bbpress' ), bbp_number_format( $changed ) );
	} else {
		/* translators: %s: Number of updated users */
		$result = sprintf( esc_html__( 'Complete! %s users updated.', 'bbpress' ), bbp_number_format( $changed ) );
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
		$result = esc_html__( 'No topic or forum freshness data to recalculate.', 'bbpress' );
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
		$result = esc_html__( 'Dry run complete!', 'bbpress' ) . ' ' . $topic_result . ' ' . $forum_result . ' ' . $stale_result;

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
	$result = esc_html__( 'Complete!', 'bbpress' ) . ' ' . $topic_result . ' ' . $forum_result . ' ' . $stale_result;

	return array( 0, sprintf( $statement, $result ) );
}

/**
 * Repair the relationship of sticky topics to the actual parent forum.
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
 * Preview the repair of sticky topic relationships.
 *
 * @since 2.7.0 bbPress (r8219)
 *
 * @return array An array of the status code and the message.
 */
function bbp_admin_repair_sticky_dry_run() {
	return bbp_admin_repair_sticky_process( true );
}

/**
 * Repair or preview sticky topic relationships.
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
		$statement = esc_html__( 'Checking sticky topic relationships&hellip; %s', 'bbpress' );
	} else {
		/* translators: %s: Status of the repair process */
		$statement = esc_html__( 'Repairing the sticky topic to the parent forum relationships&hellip; %s', 'bbpress' );
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
		/* translators: %d: Number of sticky topic relationships that would be repaired */
		$result = sprintf( _n( 'Dry run complete! %d sticky topic relationship would be repaired.', 'Dry run complete! %d sticky topic relationships would be repaired.', $changed, 'bbpress' ), $changed );
	} else {
		/* translators: %d: Number of repaired sticky topic relationships */
		$result = sprintf( _n( 'Complete! %d sticky topic relationship repaired.', 'Complete! %d sticky topic relationships repaired.', $changed, 'bbpress' ), $changed );
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
 *
 * @param bool $dry_run Whether to preview changes without updating metadata.
 * @return array An array of the status code and the message.
 */
function bbp_admin_repair_closed_topics_process( $dry_run = false ) {

	// Define variables
	$bbp_db        = bbp_db();
	$result        = esc_html__( 'No closed topics to repair.',        'bbpress' );
	$changed       = 0;

	if ( $dry_run ) {
		/* translators: %s: Status of the dry-run process */
		$statement = esc_html__( 'Checking closed topics&hellip; %s', 'bbpress' );
	} else {
		/* translators: %s: Status of the repair process */
		$statement = esc_html__( 'Repairing closed topics&hellip; %s', 'bbpress' );
	}

	// Results
	$query         = "SELECT ID FROM `{$bbp_db->posts}` WHERE `post_type` = %s AND `post_status` = %s";
	$prepare       = $bbp_db->prepare( $query, bbp_get_topic_post_type(), bbp_get_closed_status_id() );
	$closed_topics = $bbp_db->get_col( $prepare );

	// Bail if no closed topics found
	if ( empty( $closed_topics ) || is_wp_error( $closed_topics ) ) {
		return array( 1, sprintf( $statement, $result ) );
	}

	// Ensure Run and Dry Run inspect current database values
	wp_cache_delete_multiple( wp_parse_id_list( $closed_topics ), 'post_meta' );

	// Loop through each closed topic
	foreach ( $closed_topics as $closed_topic ) {

		// Check if the closed topic already has a postmeta _bbp_status value
		$topic_status = get_post_meta( $closed_topic, '_bbp_status', true );

		// If we don't have a postmeta _bbp_status value
		if ( empty( $topic_status ) ) {
			if ( ! $dry_run ) {
				update_post_meta( $closed_topic, '_bbp_status', 'publish' );
			}

			++$changed; // Keep a count to display at the end
		}
	}

	// Cleanup
	unset( $closed_topics, $closed_topic, $topic_status );

	// Complete results
	if ( $dry_run ) {
		/* translators: %d: Number of closed topics that would be repaired */
		$result = sprintf( _n( 'Dry run complete! %d closed topic would be repaired.', 'Dry run complete! %d closed topics would be repaired.', $changed, 'bbpress' ), $changed );
	} else {
		/* translators: %d: Number of repaired topics */
		$result = sprintf( _n( 'Complete! %d closed topic repaired.', 'Complete! %d closed topics repaired.', $changed, 'bbpress' ), $changed );
	}

	return array( 0, sprintf( $statement, $result ) );
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

		$result = esc_html__( 'No forum visibility entries to repair.', 'bbpress' );
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
		$result = esc_html__( 'Dry run complete!', 'bbpress' ) . ' ' . $private_result . ' ' . $hidden_result;

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
	$result = esc_html__( 'Complete!', 'bbpress' ) . ' ' . $private_result . ' ' . $hidden_result;

	return array( 0, sprintf( $statement, $result ) );
}

/**
 * Repair the parent forum meta for each topic and reply.
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
 * Preview repairing the parent forum meta for each forum, topic, and reply.
 *
 * @since 2.7.0 bbPress (r8239)
 *
 * @return array An array of the status code and the message.
 */
function bbp_admin_repair_forum_meta_dry_run() {
	return bbp_admin_repair_forum_meta_process( true );
}

/**
 * Repair or preview repairing the parent forum meta for each post.
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
		$statement = esc_html__( 'Checking the forum for each post&hellip; %s', 'bbpress' );
	} else {
		/* translators: %s: Status of the repair process */
		$statement = esc_html__( 'Recalculating the forum for each post&hellip; %s', 'bbpress' );
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

		$result = esc_html__( 'No forum relationships to repair.', 'bbpress' );
		return array( 1, sprintf( $statement, $result ) );
	}

	$forum_result = sprintf(
		/* translators: %s: Number of forum relationships */
		esc_html( _n( '%s forum relationship would be repaired.', '%s forum relationships would be repaired.', $forum_repairs, 'bbpress' ) ),
		bbp_number_format( $forum_repairs )
	);
	$topic_result = sprintf(
		/* translators: %s: Number of topic relationships */
		esc_html( _n( '%s topic relationship would be repaired.', '%s topic relationships would be repaired.', $topic_repairs, 'bbpress' ) ),
		bbp_number_format( $topic_repairs )
	);
	$reply_result = sprintf(
		/* translators: %s: Number of reply relationships */
		esc_html( _n( '%s reply relationship would be repaired.', '%s reply relationships would be repaired.', $reply_repairs, 'bbpress' ) ),
		bbp_number_format( $reply_repairs )
	);
	$stale_result = sprintf(
		/* translators: %s: Number of orphaned relationships */
		esc_html( _n( '%s orphaned relationship would be removed.', '%s orphaned relationships would be removed.', $stale_count, 'bbpress' ) ),
		bbp_number_format( $stale_count )
	);

	if ( $dry_run ) {
		$result = esc_html__( 'Dry run complete!', 'bbpress' ) . ' ' . $forum_result . ' ' . $topic_result . ' ' . $reply_result . ' ' . $stale_result;

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
		/* translators: %s: Number of forum relationships */
		esc_html( _n( '%s forum relationship repaired.', '%s forum relationships repaired.', $forum_repairs, 'bbpress' ) ),
		bbp_number_format( $forum_repairs )
	);
	$topic_result = sprintf(
		/* translators: %s: Number of topic relationships */
		esc_html( _n( '%s topic relationship repaired.', '%s topic relationships repaired.', $topic_repairs, 'bbpress' ) ),
		bbp_number_format( $topic_repairs )
	);
	$reply_result = sprintf(
		/* translators: %s: Number of reply relationships */
		esc_html( _n( '%s reply relationship repaired.', '%s reply relationships repaired.', $reply_repairs, 'bbpress' ) ),
		bbp_number_format( $reply_repairs )
	);
	$stale_result = sprintf(
		/* translators: %s: Number of orphaned relationships */
		esc_html( _n( '%s orphaned relationship removed.', '%s orphaned relationships removed.', $stale_count, 'bbpress' ) ),
		bbp_number_format( $stale_count )
	);
	$result = esc_html__( 'Complete!', 'bbpress' ) . ' ' . $forum_result . ' ' . $topic_result . ' ' . $reply_result . ' ' . $stale_result;

	return array( 0, sprintf( $statement, $result ) );
}

/**
 * Repair the topic for each post.
 *
 * @since 2.1.0 bbPress (r3884)
 * @since 2.7.0 bbPress (r8238) Added Dry Run support.
 *
 * @return array An array of the status code and the message.
 */
function bbp_admin_repair_topic_meta() {
	return bbp_admin_repair_topic_meta_process();
}

/**
 * Preview repairing the topic for each post.
 *
 * @since 2.7.0 bbPress (r8238)
 *
 * @return array An array of the status code and the message.
 */
function bbp_admin_repair_topic_meta_dry_run() {
	return bbp_admin_repair_topic_meta_process( true );
}

/**
 * Repair or preview repairing the topic for each post.
 *
 * @since 2.7.0 bbPress (r8238)
 *
 * @param bool $dry_run Whether to preview changes without updating data.
 * @return array An array of the status code and the message.
 */
function bbp_admin_repair_topic_meta_process( $dry_run = false ) {

	// Define variables
	$bbp_db = bbp_db();
	$failed = esc_html__( 'Failed!', 'bbpress' );
	$tpt    = bbp_get_topic_post_type();
	$rpt    = bbp_get_reply_post_type();

	if ( $dry_run ) {
		/* translators: %s: Status of the dry-run process */
		$statement = esc_html__( 'Checking the topic for each post&hellip; %s', 'bbpress' );
	} else {
		/* translators: %s: Status of the repair process */
		$statement = esc_html__( 'Recalculating the topic for each post&hellip; %s', 'bbpress' );
	}

	// Count topics that do not point to themselves exactly once
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
					OR NOT ( CAST( MAX( `topicmeta`.`meta_value` ) AS BINARY ) <=> CAST( CAST( `topic`.`ID` AS CHAR ) AS BINARY ) )
			) AS `topic_repairs`",
			'_bbp_topic_id',
			$tpt,
			'_bbp_topic_id'
		)
	);
	if ( ! empty( $bbp_db->last_error ) ) {
		return array(
			1,
			sprintf( $statement, $failed ),
			'success' => false,
		);
	}

	// Count replies that do not point to their parent topics exactly once
	$reply_repairs = $bbp_db->get_var(
		$bbp_db->prepare(
			"SELECT COUNT(*) FROM (
				SELECT `reply`.`ID`, `topic`.`ID` AS `topic_id`
				FROM `{$bbp_db->posts}` AS `reply`
				INNER JOIN `{$bbp_db->posts}` AS `topic`
					ON `reply`.`post_parent` = `topic`.`ID`
				LEFT JOIN `{$bbp_db->postmeta}` AS `replymeta`
					ON `replymeta`.`post_id` = `reply`.`ID`
					AND `replymeta`.`meta_key` = %s
				WHERE `topic`.`post_type` = %s
					AND `reply`.`post_type` = %s
				GROUP BY `reply`.`ID`, `topic`.`ID`
				HAVING COUNT( `replymeta`.`meta_id` ) <> 1
					OR SUM( CASE WHEN CAST( `replymeta`.`meta_key` AS BINARY ) = CAST( %s AS BINARY ) THEN 1 ELSE 0 END ) <> 1
					OR NOT ( CAST( MAX( `replymeta`.`meta_value` ) AS BINARY ) <=> CAST( CAST( `topic`.`ID` AS CHAR ) AS BINARY ) )
			) AS `reply_repairs`",
			'_bbp_topic_id',
			$tpt,
			$rpt,
			'_bbp_topic_id'
		)
	);
	if ( ! empty( $bbp_db->last_error ) ) {
		return array(
			2,
			sprintf( $statement, $failed ),
			'success' => false,
		);
	}

	// Count relationships that do not belong to a topic or a valid reply
	$stale_count = $bbp_db->get_var(
		$bbp_db->prepare(
			"SELECT COUNT( `topicmeta`.`meta_id` )
			FROM `{$bbp_db->postmeta}` AS `topicmeta`
			LEFT JOIN `{$bbp_db->posts}` AS `topic`
				ON `topicmeta`.`post_id` = `topic`.`ID`
				AND `topic`.`post_type` = %s
			LEFT JOIN `{$bbp_db->posts}` AS `reply`
				ON `topicmeta`.`post_id` = `reply`.`ID`
				AND `reply`.`post_type` = %s
			LEFT JOIN `{$bbp_db->posts}` AS `parent`
				ON `reply`.`post_parent` = `parent`.`ID`
				AND `parent`.`post_type` = %s
			WHERE `topicmeta`.`meta_key` = %s
				AND `topic`.`ID` IS NULL
				AND `parent`.`ID` IS NULL",
			$tpt,
			$rpt,
			$tpt,
			'_bbp_topic_id'
		)
	);
	if ( ! empty( $bbp_db->last_error ) ) {
		return array(
			3,
			sprintf( $statement, $failed ),
			'success' => false,
		);
	}

	$topic_repairs = (int) $topic_repairs;
	$reply_repairs = (int) $reply_repairs;
	$stale_count   = (int) $stale_count;

	// Bail if no repairs are needed
	if ( empty( $topic_repairs ) && empty( $reply_repairs ) && empty( $stale_count ) ) {
		if ( ! $dry_run && ! bbp_admin_repair_clean_post_meta_cache_batched( array( $tpt, $rpt ) ) ) {
			return array(
				7,
				sprintf( $statement, $failed ),
				'success' => false,
			);
		}

		$result = esc_html__( 'No topic relationships to repair.', 'bbpress' );
		return array( 1, sprintf( $statement, $result ) );
	}

	$topic_result = sprintf(
		/* translators: %s: Number of topic relationships */
		esc_html( _n( '%s topic relationship would be repaired.', '%s topic relationships would be repaired.', $topic_repairs, 'bbpress' ) ),
		bbp_number_format( $topic_repairs )
	);
	$reply_result = sprintf(
		/* translators: %s: Number of reply relationships */
		esc_html( _n( '%s reply relationship would be repaired.', '%s reply relationships would be repaired.', $reply_repairs, 'bbpress' ) ),
		bbp_number_format( $reply_repairs )
	);
	$stale_result = sprintf(
		/* translators: %s: Number of orphaned relationships */
		esc_html( _n( '%s orphaned relationship would be removed.', '%s orphaned relationships would be removed.', $stale_count, 'bbpress' ) ),
		bbp_number_format( $stale_count )
	);

	if ( $dry_run ) {
		$result = esc_html__( 'Dry run complete!', 'bbpress' ) . ' ' . $topic_result . ' ' . $reply_result . ' ' . $stale_result;

		return array( 0, sprintf( $statement, $result ) );
	}

	if ( ! bbp_admin_repair_clean_post_meta_cache_batched( array( $tpt, $rpt ), '_bbp_topic_id' ) ) {
		return array(
			7,
			sprintf( $statement, $failed ),
			'success' => false,
		);
	}

	// First, delete everything
	if ( false === $bbp_db->query( $bbp_db->prepare( "DELETE FROM `{$bbp_db->postmeta}` WHERE `meta_key` = %s", '_bbp_topic_id' ) ) ) {
		return array(
			4,
			sprintf( $statement, $failed ),
			'success' => false,
		);
	}

	// Give every topic its own topic ID
	$inserted_topics = $bbp_db->query(
		$bbp_db->prepare(
			"INSERT INTO `{$bbp_db->postmeta}` (`post_id`, `meta_key`, `meta_value`)
			( SELECT `topic`.`ID`, '_bbp_topic_id', `topic`.`ID`
			FROM `{$bbp_db->posts}` AS `topic`
			WHERE `topic`.`post_type` = %s
			GROUP BY `topic`.`ID` )",
			$tpt
		)
	);

	if ( false === $inserted_topics ) {
		return array(
			5,
			sprintf( $statement, $failed ),
			'success' => false,
		);
	}

	// Give every reply its parent topic ID
	$inserted_replies = $bbp_db->query(
		$bbp_db->prepare(
			"INSERT INTO `{$bbp_db->postmeta}` (`post_id`, `meta_key`, `meta_value`)
			( SELECT `reply`.`ID`, '_bbp_topic_id', `topic`.`ID`
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
			6,
			sprintf( $statement, $failed ),
			'success' => false,
		);
	}

	if ( ! bbp_admin_repair_clean_post_meta_cache_batched( array( $tpt, $rpt ) ) ) {
		return array(
			7,
			sprintf( $statement, $failed ),
			'success' => false,
		);
	}

	$topic_result = sprintf(
		/* translators: %s: Number of topic relationships */
		esc_html( _n( '%s topic relationship repaired.', '%s topic relationships repaired.', $topic_repairs, 'bbpress' ) ),
		bbp_number_format( $topic_repairs )
	);
	$reply_result = sprintf(
		/* translators: %s: Number of reply relationships */
		esc_html( _n( '%s reply relationship repaired.', '%s reply relationships repaired.', $reply_repairs, 'bbpress' ) ),
		bbp_number_format( $reply_repairs )
	);
	$stale_result = sprintf(
		/* translators: %s: Number of orphaned relationships */
		esc_html( _n( '%s orphaned relationship removed.', '%s orphaned relationships removed.', $stale_count, 'bbpress' ) ),
		bbp_number_format( $stale_count )
	);
	$result = esc_html__( 'Complete!', 'bbpress' ) . ' ' . $topic_result . ' ' . $reply_result . ' ' . $stale_result;

	return array( 0, sprintf( $statement, $result ) );
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
		return array( 1, sprintf( $statement, $result ) );
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

	// Ensure current reply relationships are visible after direct database work
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
		$result = esc_html__( 'No reply positions or relationships to repair.', 'bbpress' );
		return array( 1, sprintf( $statement, $result ) );
	}

	// Delete self-referential reply relationships
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
		/* translators: %d: Number of self-referential reply relationships that would be removed */
		$relationships = sprintf( _n( 'Dry run complete! %d self-referential reply relationship would be removed.', 'Dry run complete! %d self-referential reply relationships would be removed.', $relationship_count, 'bbpress' ), $relationship_count );
		/* translators: %d: Number of reply positions that would be recalculated */
		$positions = sprintf( _n( '%d reply position would be recalculated.', '%d reply positions would be recalculated.', $position_count, 'bbpress' ), $position_count );
	} else {
		/* translators: %d: Number of removed self-referential reply relationships */
		$relationships = sprintf( _n( 'Complete! %d self-referential reply relationship was removed.', 'Complete! %d self-referential reply relationships were removed.', $relationship_count, 'bbpress' ), $relationship_count );
		/* translators: %d: Number of recalculated reply positions */
		$positions = sprintf( _n( '%d reply position was recalculated.', '%d reply positions were recalculated.', $position_count, 'bbpress' ), $position_count );
	}

	$result = $relationships . ' ' . $positions;

	return array( 0, sprintf( $statement, $result ) );
}
