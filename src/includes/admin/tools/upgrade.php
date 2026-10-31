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
						<th scope="col" id="version" class="manage-column column-version sortable <?php echo ( 'version' === $orderby ) ? esc_attr( $order ) : 'asc'; ?>">
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
						<th scope="col" id="components" class="manage-column column-components">
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

						<?php foreach ( $tools as $item ) : ?>

							<tr id="bbp-repair-tools" class="inactive">
								<th scope="row" class="check-column">
									<label class="screen-reader-text" for="<?php echo esc_attr( str_replace( '_', '-', $item['id'] ) ); ?>"></label>
									<input type="checkbox" name="checked[]" value="<?php echo esc_attr( $item['id'] ); ?>" id="<?php echo esc_attr( str_replace( '_', '-', $item['id'] ) ); ?>">
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
 * Upgrade or preview upgrading user engagements.
 *
 * @since 2.7.0 bbPress (r8230)
 *
 * @param bool $dry_run Whether to preview changes without updating data.
 * @return array An array of the status code and the message.
 */
function bbp_admin_upgrade_user_engagements_process( $dry_run = false ) {

	// Define variables
	$bbp_db         = bbp_db();
	$failed         = esc_html__( 'Failed!', 'bbpress' );
	$no_engagements = esc_html__( 'No engagements to upgrade.', 'bbpress' );

	if ( $dry_run ) {
		/* translators: %s: Status of the dry-run process */
		$statement = esc_html__( 'Checking user engagements&hellip; %s', 'bbpress' );
	} else {
		/* translators: %s: Status of the upgrade process */
		$statement = esc_html__( 'Upgrading user engagements&hellip; %s', 'bbpress' );
	}

	// Post types and statuses
	$tpt = bbp_get_topic_post_type();
	$rpt = bbp_get_reply_post_type();
	$pps = bbp_get_public_status_id();
	$cps = bbp_get_closed_status_id();
	$sql_select = "SELECT topics.ID AS post_id, '_bbp_engagement' AS meta_key, posts.post_author AS meta_value
				FROM {$bbp_db->posts} AS posts
				INNER JOIN {$bbp_db->postmeta} AS postmeta
					ON posts.ID = postmeta.post_id
					AND postmeta.meta_key = '_bbp_topic_id'
				INNER JOIN {$bbp_db->posts} AS topics
					ON topics.ID = postmeta.meta_value
					AND topics.post_type = %s
				WHERE posts.post_type IN (%s, %s)
					AND posts.post_status IN (%s, %s)
				GROUP BY topics.ID, posts.post_author";
	$prepare    = $bbp_db->prepare( $sql_select, $tpt, $tpt, $rpt, $pps, $cps );

	// Count the rows that Run would replace and insert
	$current_count = (int) $bbp_db->get_var( "SELECT COUNT(*) FROM {$bbp_db->postmeta} WHERE meta_key = '_bbp_engagement'" );
	if ( ! empty( $bbp_db->last_error ) ) {
		return array(
			2,
			sprintf( $statement, $failed ),
			'success' => false,
		);
	}

	$expected_count = (int) $bbp_db->get_var( "SELECT COUNT(*) FROM ({$prepare}) AS engagements" );
	if ( ! empty( $bbp_db->last_error ) ) {
		return array(
			3,
			sprintf( $statement, $failed ),
			'success' => false,
		);
	}

	if ( $dry_run ) {
		if ( empty( $current_count ) && empty( $expected_count ) ) {
			return array( 1, sprintf( $statement, $no_engagements ) );
		}

		/* translators: %1$s: Number of current engagement rows; %2$s: Number of upgraded engagements */
		$result = sprintf( esc_html__( 'Dry run complete! %1$s current engagement rows would be replaced with %2$s upgraded engagements.', 'bbpress' ), bbp_number_format( $current_count ), bbp_number_format( $expected_count ) );

		return array( 0, sprintf( $statement, $result ) );
	}

	// Delete previous engagements
	$sql_delete = "DELETE FROM {$bbp_db->postmeta} WHERE meta_key = '_bbp_engagement'";
	if ( false === $bbp_db->query( $sql_delete ) ) {
		return array(
			4,
			sprintf( $statement, $failed ),
			'success' => false,
		);
	}

	// Run the big query
	$sql_insert  = "INSERT INTO {$bbp_db->postmeta} (post_id, meta_key, meta_value) ({$prepare})";
	$engagements = $bbp_db->query( $sql_insert );
	bbp_admin_repair_clean_post_meta_cache( $tpt );

	// Bail if the insert failed
	if ( false === $engagements ) {
		return array(
			5,
			sprintf( $statement, $failed ),
			'success' => false,
		);
	}

	// Bail if no engagements were found
	if ( empty( $engagements ) ) {
		return array( 1, sprintf( $statement, $no_engagements ) );
	}

	// Complete results
	/* translators: %d: Number of upgraded engagements */
	$result = sprintf( _n( 'Complete! %d engagement upgraded.', 'Complete! %d engagements upgraded.', $engagements, 'bbpress' ), bbp_number_format( $engagements ) );

	return array( 0, sprintf( $statement, $result ) );
}

/**
 * Upgrade group forum ID mappings after a bbPress 1.x to bbPress 2.x conversion.
 *
 * Previously named: bbp_admin_repair_group_forum_relationships().
 *
 * @since 2.6.0 bbPress (r6496)
 *
 * @return If a wp_error() occurs and no converted forums are found.
 */
function bbp_admin_upgrade_group_forum_relationships() {

	// Define variables
	$bbp_db    = bbp_db();
	/* translators: %s: Status of the upgrade process */
	$statement = esc_html__( 'Upgrading BuddyPress group-forum relationships&hellip; %s', 'bbpress' );
	$g_count   = 0;
	$f_count   = 0;
	$s_count   = 0;

	// Copy the BuddyPress filter here, incase BuddyPress is not active
	$prefix            = apply_filters( 'bp_core_get_table_prefix', $bbp_db->base_prefix );
	$groups_table      = $prefix . 'bp_groups';
	$groups_meta_table = $prefix . 'bp_groups_groupmeta';

	// Get the converted forum IDs
	$forum_ids = $bbp_db->query(
		"SELECT `forum`.`ID`, `forummeta`.`meta_value`
			FROM `{$bbp_db->posts}` AS `forum`
				LEFT JOIN `{$bbp_db->postmeta}` AS `forummeta`
					ON `forum`.`ID` = `forummeta`.`post_id`
					AND `forummeta`.`meta_key` = '_bbp_old_forum_id'
			WHERE `forum`.`post_type` = '" . bbp_get_forum_post_type() . "'
			GROUP BY `forum`.`ID`"
	);

	// Bail if forum IDs returned an error
	if ( is_wp_error( $forum_ids ) || empty( $bbp_db->last_result ) ) {
		return array( 2, sprintf( $statement, esc_html__( 'Failed!', 'bbpress' ) ) );
	}

	// Stash the last results
	$results = $bbp_db->last_result;

	// Update each group forum
	foreach ( $results as $group_forums ) {

		// Only update if is a converted forum
		if ( empty( $group_forums->meta_value ) ) {
			continue;
		}

		// Attempt to update group meta
		$updated = $bbp_db->query( $bbp_db->prepare( "UPDATE `{$groups_meta_table}` SET `meta_value` = %d WHERE `meta_key` = %s AND `meta_value` = %s", $group_forums->ID, 'forum_id', $group_forums->meta_value ) );

		// Bump the count
		if ( ! empty( $updated ) && ! is_wp_error( $updated ) ) {
			++$g_count;
		}

		// Update group to forum relationship data
		$group_id = (int) $bbp_db->get_var( "SELECT `group_id` FROM `{$groups_meta_table}` WHERE `meta_key` = 'forum_id' AND `meta_value` = '{$group_forums->ID}'" );
		if ( ! empty( $group_id ) ) {

			// Update the group to forum meta connection in forums
			update_post_meta( $group_forums->ID, '_bbp_group_ids', array( $group_id ) );

			// Get the group status
			$group_status = $bbp_db->get_var( "SELECT `status` FROM `{$groups_table}` WHERE `id` = '{$group_id}'" );

			// Sync up forum visibility based on group status
			switch ( $group_status ) {

				// Public groups have public forums
				case 'public' :
					bbp_publicize_forum( $group_forums->ID );

					// Bump the count for output later
					++$s_count;
					break;

				// Private/hidden groups have hidden forums
				case 'private' :
				case 'hidden'  :
					bbp_hide_forum( $group_forums->ID );

					// Bump the count for output later
					++$s_count;
					break;
			}

			// Bump the count for output later
			++$f_count;
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

	// Found the group root forum
	if ( ! empty( $posts ) ) {

		// Rename 'Default Forum'  since it's now visible in sitewide forums
		if ( 'Default Forum' === $posts[0]->post_title ) {
			wp_update_post(
				array(
					'ID'         => $posts[0]->ID,
					'post_title' => esc_html__( 'Group Forums', 'bbpress' ),
					'post_name'  => esc_html__( 'group-forums', 'bbpress' ),
				)
			);
		}

		// Update the group forums root metadata
		update_option( '_bbp_group_forums_root_id', $posts[0]->ID );
	}

	// Remove old bbPress 1.1 roles (BuddyPress)
	remove_role( 'member'    );
	remove_role( 'inactive'  );
	remove_role( 'blocked'   );
	remove_role( 'moderator' );
	remove_role( 'keymaster' );

	// Complete results
	$result = sprintf(
		/* translators: %1$s: Number of groups updated; %2$s: Number of forums updated; %3$s: Number of forum statuses synced */
		esc_html__( 'Complete! %1$s groups updated; %2$s forums updated; %3$s forum statuses synced.', 'bbpress' ),
		bbp_number_format( $g_count ),
		bbp_number_format( $f_count ),
		bbp_number_format( $s_count )
	);

	// Return
	return array( 0, sprintf( $statement, $result ) );
}

/**
 * Upgrade user favorites for bbPress 2.6 and higher.
 *
 * @since 2.6.0 bbPress (r6176)
 * @since 2.7.0 bbPress (r8231) Added Dry Run support.
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
	$bbp_db          = bbp_db();
	$failed          = esc_html__( 'Failed!', 'bbpress' );
	$result          = esc_html__( 'No favorites to upgrade.', 'bbpress' );
	$total           = 0;
	$failed_total    = 0;
	$old_key         = $bbp_db->prefix . '_bbp_favorites';
	$new_key         = '_bbp_favorite';
	$planned_user_id = null;
	$planned         = array();

	if ( $dry_run ) {
		/* translators: %s: Status of the dry-run process */
		$statement = esc_html__( 'Checking user favorites&hellip; %s', 'bbpress' );
	} else {
		/* translators: %s: Status of the upgrade process */
		$statement = esc_html__( 'Upgrading user favorites&hellip; %s', 'bbpress' );
	}

	// Results
	$query     = "SELECT user_id, meta_value FROM {$bbp_db->usermeta} WHERE meta_key = %s ORDER BY user_id, umeta_id";
	$prepare   = $bbp_db->prepare( $query, $old_key );
	$favs      = $bbp_db->get_results( $prepare );

	// Bail if favorites could not be read
	if ( ! empty( $bbp_db->last_error ) ) {
		return array(
			2,
			sprintf( $statement, $failed ),
			'success' => false,
		);
	}

	// Bail if no favorites were found
	if ( empty( $favs ) ) {
		return array( 1, sprintf( $statement, $result ) );
	}

	// Loop through each user's favorites
	foreach ( $favs as $meta ) {
		$user_id = (int) $meta->user_id;

		// Bound relationship state to one user at a time
		if ( $planned_user_id !== $user_id ) {
			$planned_user_id = $user_id;
			$planned         = array();
		}

		// Get post IDs
		$post_ids = array_filter( wp_parse_id_list( $meta->meta_value ) );

		// Add user ID to all favorited posts
		foreach ( $post_ids as $post_id ) {

			// Skip relationships already processed for this user
			if ( isset( $planned[ $post_id ] ) ) {
				continue;
			}

			// Skip relationships that already exist
			$exists = $bbp_db->get_var( $bbp_db->prepare( "SELECT COUNT(*) FROM {$bbp_db->postmeta} WHERE post_id = %d AND meta_key = %s AND meta_value = %d", $post_id, $new_key, $user_id ) );
			if ( ! empty( $bbp_db->last_error ) ) {
				return array(
					3,
					sprintf( $statement, $failed ),
					'success' => false,
				);
			}

			if ( ! empty( $exists ) ) {
				$planned[ $post_id ] = true;
				continue;
			}

			$planned[ $post_id ] = true;

			if ( $dry_run ) {
				++$total;
				continue;
			}

			// Add the post meta
			if ( false === add_post_meta( $post_id, $new_key, $user_id, false ) ) {
				++$failed_total;
				continue;
			}

			++$total;
		}
	}

	// Cleanup
	unset( $favs, $post_ids, $planned );

	if ( $dry_run ) {
		if ( empty( $total ) ) {
			return array( 1, sprintf( $statement, $result ) );
		}

		/* translators: %s: Number of favorites that would be upgraded */
		$result = sprintf( _n( 'Dry run complete! %s favorite would be upgraded.', 'Dry run complete! %s favorites would be upgraded.', $total, 'bbpress' ), bbp_number_format( $total ) );

		return array( 0, sprintf( $statement, $result ) );
	}

	if ( ! empty( $failed_total ) ) {
		/* translators: %s: Number of upgraded favorites */
		$upgraded = sprintf( _n( '%s favorite upgraded', '%s favorites upgraded', $total, 'bbpress' ), bbp_number_format( $total ) );
		/* translators: %s: Number of favorites that failed */
		$failures = sprintf( _n( '%s favorite failed', '%s favorites failed', $failed_total, 'bbpress' ), bbp_number_format( $failed_total ) );
		/* translators: %1$s: Upgraded favorites; %2$s: Favorites that failed */
		$result = sprintf( esc_html__( 'Failed! %1$s; %2$s.', 'bbpress' ), $upgraded, $failures );

		return array(
			4,
			sprintf( $statement, $result ),
			'success' => false,
		);
	}

	// Complete results
	/* translators: %s: Number of upgraded favorites */
	$result = sprintf( _n( 'Complete! %s favorite upgraded.', 'Complete! %s favorites upgraded.', $total, 'bbpress' ), bbp_number_format( $total ) );

	return array( 0, sprintf( $statement, $result ) );
}

/**
 * Upgrade user topic subscriptions for bbPress 2.6 and higher.
 *
 * @since 2.6.0 bbPress (r6193)
 *
 * @return array An array of the status code and the message.
 */
function bbp_admin_upgrade_user_topic_subscriptions() {

	// Define variables
	$bbp_db    = bbp_db();
	/* translators: %s: Status of the upgrade process */
	$statement = esc_html__( 'Upgrading user topic subscriptions&hellip; %s', 'bbpress' );
	$result    = esc_html__( 'No topic subscriptions to upgrade.',            'bbpress' );
	$total     = 0;
	$old_key   = $bbp_db->prefix . '_bbp_subscriptions';
	$new_key   = '_bbp_subscription';

	// Results
	$query     = "SELECT * FROM {$bbp_db->usermeta} WHERE meta_key = %s ORDER BY user_id";
	$prepare   = $bbp_db->prepare( $query, $old_key );
	$subs      = $bbp_db->get_results( $prepare );

	// Bail if no topic subscriptions found
	if ( empty( $subs ) || is_wp_error( $subs ) ) {
		return array( 1, sprintf( $statement, $result ) );
	}

	// Loop through each user's topic subscriptions
	foreach ( $subs as $meta ) {

		// Get post IDs
		$post_ids = explode( ',', $meta->meta_value );

		// Add user ID to all subscribed topics
		foreach ( $post_ids as $post_id ) {

			// Skip if already exists
			if ( $bbp_db->get_var( $bbp_db->prepare( "SELECT COUNT(*) FROM {$bbp_db->postmeta} WHERE post_id = %d AND meta_key = %s AND meta_value = %s", $post_id, $new_key, $meta->user_id ) ) ) {
				continue;
			}

			// Add the post meta
			$added = add_post_meta( $post_id, $new_key, $meta->user_id, false );

			// Bump counts if successfully added
			if ( ! empty( $added ) ) {
				++$total;
			}
		}
	}

	// Cleanup
	unset( $subs, $added, $post_ids );

	// Complete results
	/* translators: %d: Number of upgraded topic subscriptions */
	$result = sprintf( _n( 'Complete! %d topic subscription upgraded.', 'Complete! %d topic subscriptions upgraded.', $total, 'bbpress' ), bbp_number_format( $total ) );

	return array( 0, sprintf( $statement, $result ) );
}

/**
 * Upgrade user forum subscriptions for bbPress 2.6 and higher.
 *
 * @since 2.6.0 bbPress (r6193)
 *
 * @return array An array of the status code and the message.
 */
function bbp_admin_upgrade_user_forum_subscriptions() {

	// Define variables
	$bbp_db    = bbp_db();
	/* translators: %s: Status of the upgrade process */
	$statement = esc_html__( 'Upgrading user forum subscriptions&hellip; %s', 'bbpress' );
	$result    = esc_html__( 'No forum subscriptions to upgrade.',            'bbpress' );
	$total     = 0;
	$old_key   = $bbp_db->prefix . '_bbp_forum_subscriptions';
	$new_key   = '_bbp_subscription';

	// Results
	$query     = "SELECT * FROM {$bbp_db->usermeta} WHERE meta_key = %s ORDER BY user_id";
	$prepare   = $bbp_db->prepare( $query, $old_key );
	$subs      = $bbp_db->get_results( $prepare );

	// Bail if no forum subscriptions found
	if ( empty( $subs ) || is_wp_error( $subs ) ) {
		return array( 1, sprintf( $statement, $result ) );
	}

	// Loop through each user's forum subscriptions
	foreach ( $subs as $meta ) {

		// Get post IDs
		$post_ids = explode( ',', $meta->meta_value );

		// Add user ID to all subscribed forums
		foreach ( $post_ids as $post_id ) {

			// Skip if already exists
			if ( $bbp_db->get_var( $bbp_db->prepare( "SELECT COUNT(*) FROM {$bbp_db->postmeta} WHERE post_id = %d AND meta_key = %s AND meta_value = %s", $post_id, $new_key, $meta->user_id ) ) ) {
				continue;
			}

			// Add the post meta
			$added = add_post_meta( $post_id, $new_key, $meta->user_id, false );

			// Bump counts if successfully added
			if ( ! empty( $added ) ) {
				++$total;
			}
		}
	}

	// Cleanup
	unset( $subs, $added, $post_ids );

	// Complete results
	/* translators: %d: Number of upgraded forum subscriptions */
	$result = sprintf( _n( 'Complete! %d forum subscription upgraded.', 'Complete! %d forum subscriptions upgraded.', $total, 'bbpress' ), bbp_number_format( $total ) );

	return array( 0, sprintf( $statement, $result ) );
}

/**
 * Remove favorites data from user meta for bbPress 2.6 and higher.
 *
 * @since 2.6.0 bbPress (r6281)
 *
 * @return array An array of the status code and the message.
 */
function bbp_admin_upgrade_remove_favorites_from_usermeta() {

	// Define variables
	$bbp_db    = bbp_db();
	/* translators: %s: Status of the upgrade process */
	$statement = esc_html__( 'Remove favorites from usermeta&hellip; %s', 'bbpress' );
	$result    = esc_html__( 'No favorites to remove.',                   'bbpress' );
	$total     = 0;
	$key       = $bbp_db->prefix . '_bbp_favorites';

	// Results
	$query     = "SELECT * FROM {$bbp_db->usermeta} WHERE meta_key = %s ORDER BY user_id";
	$prepare   = $bbp_db->prepare( $query, $key );
	$favs      = $bbp_db->get_results( $prepare );

	// Bail if no favorites found
	if ( empty( $favs ) || is_wp_error( $favs ) ) {
		return array( 1, sprintf( $statement, $result ) );
	}

	// Delete all user-meta with this key
	delete_metadata( 'user', false, $key, false, true );
	$total = count( $favs );

	// Complete results
	/* translators: %d: Number of deleted favorites */
	$result = sprintf( _n( 'Complete! %d favorite deleted.', 'Complete! %d favorites deleted.', $total, 'bbpress' ), bbp_number_format( $total ) );

	return array( 0, sprintf( $statement, $result ) );
}

/**
 * Remove topic subscriptions data from user meta for bbPress 2.6 and higher.
 *
 * @since 2.6.0 bbPress (r6281)
 *
 * @return array An array of the status code and the message.
 */
function bbp_admin_upgrade_remove_topic_subscriptions_from_usermeta() {

	// Define variables
	$bbp_db    = bbp_db();
	/* translators: %s: Status of the upgrade process */
	$statement = esc_html__( 'Remove topic subscriptions from usermeta&hellip; %s', 'bbpress' );
	$result    = esc_html__( 'No topic subscriptions to remove.',                   'bbpress' );
	$total     = 0;
	$key       = $bbp_db->prefix . '_bbp_subscriptions';

	// Results
	$query     = "SELECT * FROM {$bbp_db->usermeta} WHERE meta_key = %s ORDER BY user_id";
	$prepare   = $bbp_db->prepare( $query, $key );
	$subs      = $bbp_db->get_results( $prepare );

	// Bail if no forum favorites found
	if ( empty( $subs ) || is_wp_error( $subs ) ) {
		return array( 1, sprintf( $statement, $result ) );
	}

	// Delete all user-meta with this key
	delete_metadata( 'user', false, $key, false, true );
	$total = count( $subs );

	// Complete results
	/* translators: %d: Number of deleted topic subscriptions */
	$result = sprintf( _n( 'Complete! %d topic subscription deleted.', 'Complete! %d topic subscriptions deleted.', $total, 'bbpress' ), bbp_number_format( $total ) );

	return array( 0, sprintf( $statement, $result ) );
}

/**
 * Remove topic subscriptions data from user meta for bbPress 2.6 and higher.
 *
 * @since 2.6.0 bbPress (r6281)
 *
 * @return array An array of the status code and the message.
 */
function bbp_admin_upgrade_remove_forum_subscriptions_from_usermeta() {

	// Define variables
	$bbp_db    = bbp_db();
	/* translators: %s: Status of the upgrade process */
	$statement = esc_html__( 'Remove forum subscriptions from usermeta&hellip; %s', 'bbpress' );
	$result    = esc_html__( 'No forum subscriptions to remove.',                   'bbpress' );
	$total     = 0;
	$key       = $bbp_db->prefix . '_bbp_forum_subscriptions';

	// Query
	$query     = "SELECT * FROM {$bbp_db->usermeta} WHERE meta_key = %s ORDER BY user_id";
	$prepare   = $bbp_db->prepare( $query, $key );
	$subs      = $bbp_db->get_results( $prepare );

	// Bail if no forum favorites found
	if ( empty( $subs ) || is_wp_error( $subs ) ) {
		return array( 1, sprintf( $statement, $result ) );
	}

	// Delete all user-meta with this key
	delete_metadata( 'user', false, $key, false, true );
	$total = count( $subs );

	// Complete results
	/* translators: %d: Number of deleted forum subscriptions */
	$result = sprintf( _n( 'Complete! %d forum subscription deleted.', 'Complete! %d forum subscriptions deleted.', $total, 'bbpress' ), bbp_number_format( $total ) );

	return array( 0, sprintf( $statement, $result ) );
}
