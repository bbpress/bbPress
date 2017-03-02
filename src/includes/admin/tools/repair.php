<?php

/**
 * bbPress Admin Repairs Page
 *
 * @package bbPress
 * @subpackage Administration
 */

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

/**
 * Admin repair page
 *
 * @since 2.0.0 bbPress (r2613) Converted from bbPress 1.2
 * @since 2.6.0 bbPress (r5885) Upgraded to list-table UI
 *
 * @todo Use a real list table
 *
 * @uses bbp_admin_repair_list() To get the recount list
 * @uses check_admin_referer() To verify the nonce and the referer
 * @uses wp_cache_flush() To flush the cache
 * @uses do_action() Calls 'admin_notices' to display the notices
 * @uses wp_nonce_field() To add a hidden nonce field
 */
function bbp_admin_repair_page() {

	// Get the registered repair tools
	$tools = bbp_admin_repair_list(); ?>

	<div class="wrap">
		<h1><?php esc_html_e( 'Forum Tools', 'bbpress' ); ?></h1>
		<h2 class="nav-tab-wrapper"><?php bbp_tools_admin_tabs( __( 'Repair Forums', 'bbpress' ) ); ?></h2>

		<p><?php esc_html_e( 'bbPress keeps track of relationships between forums, topics, replies, topic-tags, favorites, subscriptions, and users. Occasionally these relationships become out of sync, most often after an import or migration. Use the tools below to manually recalculate these relationships.', 'bbpress' ); ?></p>
		<p class="description"><?php esc_html_e( 'Some of these tools create substantial database overhead. Use caution when running more than 1 repair at a time.', 'bbpress' ); ?></p>

		<?php bbp_admin_repair_tool_overhead_filters(); ?>

		<form class="settings" method="get" action="">

			<?php bbp_admin_repair_list_search_form(); ?>

			<input type="hidden" name="page" value="bbp-repair" />
			<?php wp_nonce_field( 'bbpress-do-counts' ); ?>

			<div class="tablenav top">
				<div class="alignleft actions bulkactions">
					<label for="bulk-action-selector-top" class="screen-reader-text"><?php esc_html_e( 'Select bulk action', 'bbpress' ); ?></label>
					<select name="action" id="bulk-action-selector-top">
						<option value="" selected="selected"><?php esc_html_e( 'Bulk Actions', 'bbpress' ); ?></option>
						<option value="run" class="hide-if-no-js"><?php esc_html_e( 'Run', 'bbpress' ); ?></option>
					</select>
					<input type="submit" id="doaction" class="button action" value="<?php esc_attr_e( 'Apply', 'bbpress' ); ?>">
				</div>
				<div class="alignleft actions">

					<?php bbp_admin_repair_list_components_filter(); ?>

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
						<th scope="col" id="description" class="manage-column column-primary column-description"><?php esc_html_e( 'Description', 'bbpress' ); ?></th>
						<th scope="col" id="components" class="manage-column column-components"><?php esc_html_e( 'Components', 'bbpress' ); ?></th>
						<th scope="col" id="overhead" class="manage-column column-overhead"><?php esc_html_e( 'Overhead', 'bbpress' ); ?></th>
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
									<strong><?php echo esc_html( $item['description'] ); ?></strong>
									<div class="row-actions hide-if-no-js">
										<span class="run">
											<a href="<?php bbp_admin_repair_tool_run_url( $item ); ?>" aria-label="<?php printf( esc_html__( 'Run %s', 'bbpress' ), $item['description'] ); ?>" id="<?php echo esc_attr( $item['id'] ); ?>" ><?php esc_html_e( 'Run', 'bbpress' ); ?></a>
										</span>
									</div>
									<button type="button" class="toggle-row">
										<span class="screen-reader-text"><?php esc_html_e( 'Show more details', 'bbpress' ); ?></span>
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
						<option value="run" class="hide-if-no-js"><?php esc_html_e( 'Run', 'bbpress' ); ?></option>
					</select>
					<input type="submit" id="doaction2" class="button action" value="<?php esc_attr_e( 'Apply', 'bbpress' ); ?>">
				</div>
			</div>
		</form>
	</div>

<?php
}

/**
 * Recount topic replies
 *
 * @since 2.0.0 bbPress (r2613)
 *
 * @uses wpdb::query() To run our recount sql queries
 * @uses is_wp_error() To check if the executed query returned {@link WP_Error}
 * @uses bbp_get_topic_post_type() To get the topic post type
 * @uses bbp_get_reply_post_type() To get the reply post type
 * @uses bbp_get_public_status_id() To get the public status id
 * @uses bbp_get_closed_status_id() To get the closed status id
 * @return array An array of the status code and the message
 */
function bbp_admin_repair_topic_reply_count() {

	// Define variables
	$bbp_db    = bbp_db();
	$statement = __( 'Counting the number of replies in each topic&hellip; %s', 'bbpress' );
	$result    = __( 'Failed!', 'bbpress' );

	// Post types and status
	$tpt = bbp_get_topic_post_type();
	$rpt = bbp_get_reply_post_type();
	$pps = bbp_get_public_status_id();
	$cps = bbp_get_closed_status_id();

	// Delete the meta key _bbp_reply_count for each topic
	$sql_delete = "DELETE `postmeta` FROM `{$bbp_db->postmeta}` AS `postmeta`
						LEFT JOIN `{$bbp_db->posts}` AS `posts` ON `posts`.`ID` = `postmeta`.`post_id`
						WHERE `posts`.`post_type` = '{$tpt}'
						AND `postmeta`.`meta_key` = '_bbp_reply_count'";

	if ( is_wp_error( $bbp_db->query( $sql_delete ) ) ) {
		return array( 1, sprintf( $statement, $result ) );
	}

	// Recalculate the meta key _bbp_reply_count for each topic
	$sql = "INSERT INTO `{$bbp_db->postmeta}` (`post_id`, `meta_key`, `meta_value`) (
			SELECT `topics`.`ID` AS `post_id`, '_bbp_reply_count' AS `meta_key`, COUNT(`replies`.`ID`) As `meta_value`
				FROM `{$bbp_db->posts}` AS `topics`
					LEFT JOIN `{$bbp_db->posts}` as `replies`
						ON  `replies`.`post_parent` = `topics`.`ID`
						AND `replies`.`post_status` = '{$pps}'
						AND `replies`.`post_type`   = '{$rpt}'
				WHERE `topics`.`post_type` = '{$tpt}'
					AND `topics`.`post_status` IN ( '{$pps}', '{$cps}' )
				GROUP BY `topics`.`ID`)";

	if ( is_wp_error( $bbp_db->query( $sql ) ) ) {
		return array( 2, sprintf( $statement, $result ) );
	}

	return array( 0, sprintf( $statement, __( 'Complete!', 'bbpress' ) ) );
}

/**
 * Recount topic voices
 *
 * @since 2.0.0 bbPress (r2613)
 *
 * @uses wpdb::query() To run our recount sql queries
 * @uses is_wp_error() To check if the executed query returned {@link WP_Error}
 * @uses bbp_get_topic_post_type() To get the topic post type
 * @uses bbp_get_reply_post_type() To get the reply post type
 * @uses bbp_get_public_status_id() To get the public status id
 * @uses bbp_get_closed_status_id() To get the closed status id
 * @return array An array of the status code and the message
 */
function bbp_admin_repair_topic_voice_count() {

	// Define variables
	$bbp_db    = bbp_db();
	$statement = __( 'Counting the number of voices in each topic&hellip; %s', 'bbpress' );
	$result    = __( 'Failed!', 'bbpress' );

	$sql_delete = "DELETE FROM {$bbp_db->postmeta} WHERE meta_key IN ('_bbp_voice_count', '_bbp_engagement')";
	if ( is_wp_error( $bbp_db->query( $sql_delete ) ) ) {
		return array( 1, sprintf( $statement, $result ) );
	}

	// Post types and status
	$tpt = bbp_get_topic_post_type();
	$rpt = bbp_get_reply_post_type();
	$pps = bbp_get_public_status_id();
	$cps = bbp_get_closed_status_id();

	$engagements_sql = $bbp_db->prepare( "INSERT INTO {$bbp_db->postmeta} (post_id, meta_key, meta_value) (
		SELECT postmeta.meta_value, '_bbp_engagement', posts.post_author
			FROM {$bbp_db->posts} AS posts
			LEFT JOIN {$bbp_db->postmeta} AS postmeta
				ON posts.ID = postmeta.post_id
				AND postmeta.meta_key = '_bbp_topic_id'
			WHERE posts.post_type IN (%s, %s)
				AND posts.post_status IN (%s, %s)
			GROUP BY postmeta.meta_value, posts.post_author)", $tpt, $rpt, $pps, $cps );

	if ( is_wp_error( $bbp_db->query( $engagements_sql ) ) ) {
		return array( 2, sprintf( $statement, $result ) );
	}

	$voice_count_sql = "INSERT INTO {$bbp_db->postmeta} (post_id, meta_key, meta_value) (
		SELECT post_id, '_bbp_voice_count', COUNT(DISTINCT meta_value)
			FROM {$bbp_db->postmeta}
			WHERE meta_key = '_bbp_engagement'
			GROUP BY post_id)";

	if ( is_wp_error( $bbp_db->query( $voice_count_sql ) ) ) {
		return array( 3, sprintf( $statement, $result ) );
	}

	return array( 0, sprintf( $statement, __( 'Complete!', 'bbpress' ) ) );
}

/**
 * Recount topic hidden replies (spammed/trashed)
 *
 * @since 2.0.0 bbPress (r2747)
 *
 * @uses wpdb::query() To run our recount sql queries
 * @uses is_wp_error() To check if the executed query returned {@link WP_Error}
 * @uses bbp_get_reply_post_type() To get the reply post type
 * @uses bbp_get_trash_status_id() To get the trash status id
 * @uses bbp_get_spam_status_id() To get the spam status id
 * @uses bbp_get_pending_status_id() To get the pending status id
 * @return array An array of the status code and the message
 */
function bbp_admin_repair_topic_hidden_reply_count() {

	// Define variables
	$bbp_db    = bbp_db();
	$statement = __( 'Counting the number of pending, spammed, and trashed replies in each topic&hellip; %s', 'bbpress' );
	$result    = __( 'Failed!', 'bbpress' );

	$sql_delete = "DELETE FROM `{$bbp_db->postmeta}` WHERE `meta_key` = '_bbp_reply_count_hidden'";
	if ( is_wp_error( $bbp_db->query( $sql_delete ) ) ) {
		return array( 1, sprintf( $statement, $result ) );
	}

	// Post types and status
	$rpt = bbp_get_reply_post_type();
	$tps = bbp_get_trash_status_id();
	$sps = bbp_get_spam_status_id();
	$pps = bbp_get_pending_status_id();

	$sql = "INSERT INTO `{$bbp_db->postmeta}` (`post_id`, `meta_key`, `meta_value`) (SELECT `post_parent`, '_bbp_reply_count_hidden', COUNT(`post_status`) as `meta_value` FROM `{$bbp_db->posts}` WHERE `post_type` = '{$rpt}' AND `post_status` IN ( '{$tps}', '{$sps}', '{$pps}' ) GROUP BY `post_parent`)";
	if ( is_wp_error( $bbp_db->query( $sql ) ) ) {
		return array( 2, sprintf( $statement, $result ) );
	}

	return array( 0, sprintf( $statement, __( 'Complete!', 'bbpress' ) ) );
}

/**
 * Repair group forum ID mappings after a bbPress 1.1 to bbPress 2.2 conversion
 *
 * @since 2.2.0 bbPress (r4395)
 *
 * @uses bbp_get_forum_post_type() To get the forum post type
 * @return If a wp_error() occurs and no converted forums are found
 */
function bbp_admin_repair_group_forum_relationship() {

	// Define variables
	$bbp_db    = bbp_db();
	$statement = __( 'Repairing BuddyPress group-forum relationships&hellip; %s', 'bbpress' );
	$g_count   = 0;
	$f_count   = 0;
	$s_count   = 0;

	// Copy the BuddyPress filter here, incase BuddyPress is not active
	$prefix            = apply_filters( 'bp_core_get_table_prefix', $bbp_db->base_prefix );
	$groups_table      = $prefix . 'bp_groups';
	$groups_meta_table = $prefix . 'bp_groups_groupmeta';

	// Get the converted forum IDs
	$forum_ids = $bbp_db->query( "SELECT `forum`.`ID`, `forummeta`.`meta_value`
								FROM `{$bbp_db->posts}` AS `forum`
									LEFT JOIN `{$bbp_db->postmeta}` AS `forummeta`
										ON `forum`.`ID` = `forummeta`.`post_id`
										AND `forummeta`.`meta_key` = '_bbp_old_forum_id'
								WHERE `forum`.`post_type` = '" . bbp_get_forum_post_type() . "'
								GROUP BY `forum`.`ID`" );

	// Bail if forum IDs returned an error
	if ( is_wp_error( $forum_ids ) || empty( $bbp_db->last_result ) ) {
		return array( 2, sprintf( $statement, __( 'Failed!', 'bbpress' ) ) );
	}

	// Stash the last results
	$results = $bbp_db->last_result;

	// Update each group forum
	foreach ( $results as $group_forums ) {

		// Only update if is a converted forum
		if ( ! isset( $group_forums->meta_value ) ) {
			continue;
		}

		// Attempt to update group meta
		$updated = $bbp_db->query( "UPDATE `{$groups_meta_table}` SET `meta_value` = '{$group_forums->ID}' WHERE `meta_key` = 'forum_id' AND `meta_value` = '{$group_forums->meta_value}'" );

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
	$posts = get_posts( array(
		'post_type'   => bbp_get_forum_post_type(),
		'meta_key'    => '_bbp_old_forum_id',
		'meta_type'   => 'NUMERIC',
		'meta_value'  => $old_default_forum_id,
		'numberposts' => 1
	) );

	// Found the group root forum
	if ( ! empty( $posts ) ) {

		// Rename 'Default Forum'  since it's now visible in sitewide forums
		if ( 'Default Forum' === $posts[0]->post_title ) {
			wp_update_post( array(
				'ID'         => $posts[0]->ID,
				'post_title' => __( 'Group Forums', 'bbpress' ),
				'post_name'  => __( 'group-forums', 'bbpress' ),
			) );
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
	$result = sprintf( __( 'Complete! %s groups updated; %s forums updated; %s forum statuses synced.', 'bbpress' ), bbp_number_format( $g_count ), bbp_number_format( $f_count ), bbp_number_format( $s_count ) );
	return array( 0, sprintf( $statement, $result ) );
}

/**
 * Recount forum topics
 *
 * @since 2.0.0 bbPress (r2613)
 *
 * @uses wpdb::query() To run our recount sql queries
 * @uses is_wp_error() To check if the executed query returned {@link WP_Error}
 * @uses bbp_get_forum_post_type() To get the forum post type
 * @uses get_posts() To get the forums
 * @uses bbp_update_forum_topic_count() To update the forum topic count
 * @return array An array of the status code and the message
 */
function bbp_admin_repair_forum_topic_count() {

	// Define variables
	$bbp_db    = bbp_db();
	$statement = __( 'Counting the number of topics in each forum&hellip; %s', 'bbpress' );
	$result    = __( 'Failed!', 'bbpress' );

	$sql_delete = "DELETE FROM {$bbp_db->postmeta} WHERE meta_key IN ( '_bbp_topic_count', '_bbp_total_topic_count', '_bbp_topic_count_hidden' )";
	if ( is_wp_error( $bbp_db->query( $sql_delete ) ) ) {
		return array( 1, sprintf( $statement, $result ) );
	}

	$forums = get_posts( array( 'post_type' => bbp_get_forum_post_type(), 'numberposts' => -1 ) );
	if ( ! empty( $forums ) ) {
		foreach ( $forums as $forum ) {
			bbp_update_forum_topic_count( $forum->ID );
			bbp_update_forum_topic_count_hidden( $forum->ID );
		}
	} else {
		return array( 2, sprintf( $statement, $result ) );
	}

	return array( 0, sprintf( $statement, __( 'Complete!', 'bbpress' ) ) );
}

/**
 * Recount topic in each topic-tag
 *
 * @since 2.6.0 bbPress (r6256)
 *
 * @uses bbp_get_topic_tag_tax_id() To get the topic-tag taxonomy
 * @uses get_terms() To get the terms
 * @uses wp_list_pluck() To get term taxonomy IDs
 * @uses get_taxonomy() To get term taxonomy object
 * @uses _update_post_term_count() To update generic counts
 * @uses bbp_update_topic_tag_count() To update topic-tag counts
 * @uses clean_term_cache() To bust the terms cache
 * @return array An array of the status code and the message
 */
function bbp_admin_repair_topic_tag_count() {

	// Define variables
	$statement = __( 'Counting the number of topics in each topic-tag&hellip; %s', 'bbpress' );
	$result    = __( 'Failed!', 'bbpress' );
	$tax_id    = bbp_get_topic_tag_tax_id();
	$terms     = get_terms( $tax_id, array( 'hide_empty' => false ) );
	$tt_ids    = wp_list_pluck( $terms, 'term_taxonomy_id' );
	$ints      = array_map( 'intval', $tt_ids );
	$taxonomy  = get_taxonomy( $tax_id );

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

	return array( 0, sprintf( $statement, __( 'Complete!', 'bbpress' ) ) );
}

/**
 * Recount forum replies
 *
 * @since 2.0.0 bbPress (r2613)
 *
 * @uses wpdb::query() To run our recount sql queries
 * @uses is_wp_error() To check if the executed query returned {@link WP_Error}
 * @uses bbp_get_forum_post_type() To get the forum post type
 * @uses get_posts() To get the forums
 * @uses bbp_update_forum_reply_count() To update the forum reply count
 * @return array An array of the status code and the message
 */
function bbp_admin_repair_forum_reply_count() {

	// Define variables
	$bbp_db    = bbp_db();
	$statement = __( 'Counting the number of replies in each forum&hellip; %s', 'bbpress' );
	$result    = __( 'Failed!', 'bbpress' );

	// Post type
	$fpt = bbp_get_forum_post_type();

	// Delete the meta keys _bbp_reply_count and _bbp_total_reply_count for each forum
	$sql_delete = "DELETE `postmeta` FROM `{$bbp_db->postmeta}` AS `postmeta`
						LEFT JOIN `{$bbp_db->posts}` AS `posts` ON `posts`.`ID` = `postmeta`.`post_id`
						WHERE `posts`.`post_type` = '{$fpt}'
						AND `postmeta`.`meta_key` = '_bbp_reply_count'
						OR `postmeta`.`meta_key` = '_bbp_total_reply_count'";

	if ( is_wp_error( $bbp_db->query( $sql_delete ) ) ) {
 		return array( 1, sprintf( $statement, $result ) );
	}

	// Recalculate the metas key _bbp_reply_count and _bbp_total_reply_count for each forum
	$forums = get_posts( array( 'post_type' => bbp_get_forum_post_type(), 'numberposts' => -1 ) );
	if ( ! empty( $forums ) ) {
		foreach ( $forums as $forum ) {
			bbp_update_forum_reply_count( $forum->ID );
		}
	} else {
		return array( 2, sprintf( $statement, $result ) );
	}

	return array( 0, sprintf( $statement, __( 'Complete!', 'bbpress' ) ) );
}

/**
 * Recount topics by the users
 *
 * @since 2.1.0 bbPress (r3889)
 *
 * @uses bbp_get_topic_post_type() To get the topic post type
 * @uses bbp_get_public_status_id() To get the public status id
 * @uses wpdb::query() To run our recount sql queries
 * @uses is_wp_error() To check if the executed query returned {@link WP_Error}
 * @return array An array of the status code and the message
 */
function bbp_admin_repair_user_topic_count() {

	// Define variables
	$bbp_db      = bbp_db();
	$statement   = __( 'Counting the number of topics each user has created&hellip; %s', 'bbpress' );
	$result      = __( 'Failed!', 'bbpress' );

	$sql_select  = "SELECT `post_author`, COUNT(DISTINCT `ID`) as `_count` FROM `{$bbp_db->posts}` WHERE `post_type` = '" . bbp_get_topic_post_type() . "' AND `post_status` = '" . bbp_get_public_status_id() . "' GROUP BY `post_author`";
	$insert_rows = $bbp_db->get_results( $sql_select );

	if ( is_wp_error( $insert_rows ) ) {
		return array( 1, sprintf( $statement, $result ) );
	}

	$key           = $bbp_db->prefix . '_bbp_topic_count';
	$insert_values = array();
	foreach ( $insert_rows as $insert_row ) {
		$insert_values[] = "('{$insert_row->post_author}', '{$key}', '{$insert_row->_count}')";
	}

	if ( !count( $insert_values ) ) {
		return array( 2, sprintf( $statement, $result ) );
	}

	$sql_delete = "DELETE FROM `{$bbp_db->usermeta}` WHERE `meta_key` = '{$key}'";
	if ( is_wp_error( $bbp_db->query( $sql_delete ) ) ) {
		return array( 3, sprintf( $statement, $result ) );
	}

	foreach ( array_chunk( $insert_values, 10000 ) as $chunk ) {
		$chunk = "\n" . implode( ",\n", $chunk );
		$sql_insert = "INSERT INTO `{$bbp_db->usermeta}` (`user_id`, `meta_key`, `meta_value`) VALUES {$chunk}";

		if ( is_wp_error( $bbp_db->query( $sql_insert ) ) ) {
			return array( 4, sprintf( $statement, $result ) );
		}
	}

	return array( 0, sprintf( $statement, __( 'Complete!', 'bbpress' ) ) );
}

/**
 * Recount topic replied by the users
 *
 * @since 2.0.0 bbPress (r2613)
 *
 * @uses bbp_get_reply_post_type() To get the reply post type
 * @uses bbp_get_public_status_id() To get the public status id
 * @uses wpdb::query() To run our recount sql queries
 * @uses is_wp_error() To check if the executed query returned {@link WP_Error}
 * @return array An array of the status code and the message
 */
function bbp_admin_repair_user_reply_count() {

	// Define variables
	$bbp_db    = bbp_db();
	$statement   = __( 'Counting the number of topics to which each user has replied&hellip; %s', 'bbpress' );
	$result      = __( 'Failed!', 'bbpress' );

	$sql_select  = "SELECT `post_author`, COUNT(DISTINCT `ID`) as `_count` FROM `{$bbp_db->posts}` WHERE `post_type` = '" . bbp_get_reply_post_type() . "' AND `post_status` = '" . bbp_get_public_status_id() . "' GROUP BY `post_author`";
	$insert_rows = $bbp_db->get_results( $sql_select );

	if ( is_wp_error( $insert_rows ) ) {
		return array( 1, sprintf( $statement, $result ) );
	}

	$key           = $bbp_db->prefix . '_bbp_reply_count';
	$insert_values = array();
	foreach ( $insert_rows as $insert_row ) {
		$insert_values[] = "('{$insert_row->post_author}', '{$key}', '{$insert_row->_count}')";
	}

	if ( !count( $insert_values ) ) {
		return array( 2, sprintf( $statement, $result ) );
	}

	$sql_delete = "DELETE FROM `{$bbp_db->usermeta}` WHERE `meta_key` = '{$key}'";
	if ( is_wp_error( $bbp_db->query( $sql_delete ) ) ) {
		return array( 3, sprintf( $statement, $result ) );
	}

	foreach ( array_chunk( $insert_values, 10000 ) as $chunk ) {
		$chunk = "\n" . implode( ",\n", $chunk );
		$sql_insert = "INSERT INTO `{$bbp_db->usermeta}` (`user_id`, `meta_key`, `meta_value`) VALUES {$chunk}";

		if ( is_wp_error( $bbp_db->query( $sql_insert ) ) ) {
			return array( 4, sprintf( $statement, $result ) );
		}
	}

	return array( 0, sprintf( $statement, __( 'Complete!', 'bbpress' ) ) );
}

/**
 * Repair user favorites
 *
 * @since 2.0.0 bbPress (r2613)
 *
 * @uses wpdb::query() To run our recount sql queries
 * @uses is_wp_error() To check if the executed query returned {@link WP_Error}
 * @uses bbp_get_topic_post_type() To get the topic post type
 * @uses bbp_get_public_status_id() To get the public status id
 * @return array An array of the status code and the message
 */
function bbp_admin_repair_user_favorites() {

	// Define variables
	$bbp_db    = bbp_db();
	$statement = __( 'Removing unpublished topics from user favorites&hellip; %s', 'bbpress' );
	$result    = __( 'Failed!', 'bbpress' );

	// Query for users with favorites
	$key       = $bbp_db->prefix . '_bbp_favorites';
	$users     = $bbp_db->get_results( "SELECT `user_id`, `meta_value` AS `favorites` FROM `{$bbp_db->usermeta}` WHERE `meta_key` = '{$key}'" );

	if ( is_wp_error( $users ) ) {
		return array( 1, sprintf( $statement, $result ) );
	}

	$topics = $bbp_db->get_col( "SELECT `ID` FROM `{$bbp_db->posts}` WHERE `post_type` = '" . bbp_get_topic_post_type() . "' AND `post_status` = '" . bbp_get_public_status_id() . "'" );

	if ( is_wp_error( $topics ) ) {
		return array( 2, sprintf( $statement, $result ) );
	}

	$values = array();
	foreach ( $users as $user ) {
		if ( empty( $user->favorites ) || ! is_string( $user->favorites ) ) {
			continue;
		}

		$favorites = array_intersect( $topics, explode( ',', $user->favorites ) );
		if ( empty( $favorites ) || ! is_array( $favorites ) ) {
			continue;
		}

		$favorites_joined = implode( ',', $favorites );
		$values[]         = "('{$user->user_id}', '{$key}, '{$favorites_joined}')";

		// Cleanup
		unset( $favorites, $favorites_joined );
	}

	if ( !count( $values ) ) {
		$result = __( 'Nothing to remove!', 'bbpress' );
		return array( 0, sprintf( $statement, $result ) );
	}

	$sql_delete = "DELETE FROM `{$bbp_db->usermeta}` WHERE `meta_key` = '{$key}'";
	if ( is_wp_error( $bbp_db->query( $sql_delete ) ) ) {
		return array( 4, sprintf( $statement, $result ) );
	}

	foreach ( array_chunk( $values, 10000 ) as $chunk ) {
		$chunk = "\n" . implode( ",\n", $chunk );
		$sql_insert = "INSERT INTO `{$bbp_db->usermeta}` (`user_id`, `meta_key`, `meta_value`) VALUES {$chunk}";
		if ( is_wp_error( $bbp_db->query( $sql_insert ) ) ) {
			return array( 5, sprintf( $statement, $result ) );
		}
	}

	return array( 0, sprintf( $statement, __( 'Complete!', 'bbpress' ) ) );
}

/**
 * Clean the users' topic subscriptions
 *
 * @since 2.0.0 bbPress (r2668)
 *
 * @uses wpdb::query() To run our recount sql queries
 * @uses is_wp_error() To check if the executed query returned {@link WP_Error}
 * @uses bbp_get_topic_post_type() To get the topic post type
 * @uses bbp_get_public_status_id() To get the public status id
 * @return array An array of the status code and the message
 */
function bbp_admin_repair_user_topic_subscriptions() {

	// Define variables
	$bbp_db    = bbp_db();
	$statement = __( 'Removing trashed topics from user subscriptions&hellip; %s', 'bbpress' );
	$result    = __( 'Failed!', 'bbpress' );

	$key       = $bbp_db->prefix . '_bbp_subscriptions';
	$users     = $bbp_db->get_results( "SELECT `user_id`, `meta_value` AS `subscriptions` FROM `{$bbp_db->usermeta}` WHERE `meta_key` = '{$key}'" );

	if ( is_wp_error( $users ) ) {
		return array( 1, sprintf( $statement, $result ) );
	}

	$topics = $bbp_db->get_col( "SELECT `ID` FROM `{$bbp_db->posts}` WHERE `post_type` = '" . bbp_get_topic_post_type() . "' AND `post_status` = '" . bbp_get_public_status_id() . "'" );
	if ( is_wp_error( $topics ) ) {
		return array( 2, sprintf( $statement, $result ) );
	}

	$values = array();
	foreach ( $users as $user ) {
		if ( empty( $user->subscriptions ) || ! is_string( $user->subscriptions ) ) {
			continue;
		}

		$subscriptions = array_intersect( $topics, explode( ',', $user->subscriptions ) );
		if ( empty( $subscriptions ) || ! is_array( $subscriptions ) ) {
			continue;
		}

		$subscriptions_joined = implode( ',', $subscriptions );
		$values[]             = "('{$user->user_id}', '{$key}', '{$subscriptions_joined}')";

		// Cleanup
		unset( $subscriptions, $subscriptions_joined );
	}

	if ( !count( $values ) ) {
		$result = __( 'Nothing to remove!', 'bbpress' );
		return array( 0, sprintf( $statement, $result ) );
	}

	$sql_delete = "DELETE FROM `{$bbp_db->usermeta}` WHERE `meta_key` = '{$key}'";
	if ( is_wp_error( $bbp_db->query( $sql_delete ) ) ) {
		return array( 4, sprintf( $statement, $result ) );
	}

	foreach ( array_chunk( $values, 10000 ) as $chunk ) {
		$chunk = "\n" . implode( ",\n", $chunk );
		$sql_insert = "INSERT INTO `{$bbp_db->usermeta}` (`user_id`, `meta_key`, `meta_value`) VALUES {$chunk}";
		if ( is_wp_error( $bbp_db->query( $sql_insert ) ) ) {
			return array( 5, sprintf( $statement, $result ) );
		}
	}

	return array( 0, sprintf( $statement, __( 'Complete!', 'bbpress' ) ) );
}

/**
 * Clean the users' forum subscriptions
 *
 * @since 2.5.0 bbPress (r5155)
 *
 * @uses wpdb::query() To run our recount sql queries
 * @uses is_wp_error() To check if the executed query returned {@link WP_Error}
 * @uses bbp_get_forum_post_type() To get the forum post type
 * @uses bbp_get_public_status_id() To get the public status id
 * @return array An array of the status code and the message
 */
function bbp_admin_repair_user_forum_subscriptions() {

	// Define variables
	$bbp_db    = bbp_db();
	$statement = __( 'Removing trashed forums from user subscriptions&hellip; %s', 'bbpress' );
	$result    = __( 'Failed!', 'bbpress' );

	$key       = $bbp_db->prefix . '_bbp_forum_subscriptions';
	$users     = $bbp_db->get_results( "SELECT `user_id`, `meta_value` AS `subscriptions` FROM `{$bbp_db->usermeta}` WHERE `meta_key` = '{$key}'" );

	if ( is_wp_error( $users ) ) {
		return array( 1, sprintf( $statement, $result ) );
	}

	$forums = $bbp_db->get_col( "SELECT `ID` FROM `{$bbp_db->posts}` WHERE `post_type` = '" . bbp_get_forum_post_type() . "' AND `post_status` = '" . bbp_get_public_status_id() . "'" );
	if ( is_wp_error( $forums ) ) {
		return array( 2, sprintf( $statement, $result ) );
	}

	$values = array();
	foreach ( $users as $user ) {
		if ( empty( $user->subscriptions ) || ! is_string( $user->subscriptions ) ) {
			continue;
		}

		$subscriptions = array_intersect( $forums, explode( ',', $user->subscriptions ) );
		if ( empty( $subscriptions ) || ! is_array( $subscriptions ) ) {
			continue;
		}

		$subscriptions_joined = implode( ',', $subscriptions );
		$values[]             = "('{$user->user_id}', '{$key}', '{$subscriptions_joined}')";

		// Cleanup
		unset( $subscriptions, $subscriptions_joined );
	}

	if ( !count( $values ) ) {
		$result = __( 'Nothing to remove!', 'bbpress' );
		return array( 0, sprintf( $statement, $result ) );
	}

	$sql_delete = "DELETE FROM `{$bbp_db->usermeta}` WHERE `meta_key` = '{$key}'";
	if ( is_wp_error( $bbp_db->query( $sql_delete ) ) ) {
		return array( 4, sprintf( $statement, $result ) );
	}

	foreach ( array_chunk( $values, 10000 ) as $chunk ) {
		$chunk = "\n" . implode( ",\n", $chunk );
		$sql_insert = "INSERT INTO `{$bbp_db->usermeta}` (`user_id`, `meta_key`, `meta_value`) VALUES {$chunk}";
		if ( is_wp_error( $bbp_db->query( $sql_insert ) ) ) {
			return array( 5, sprintf( $statement, $result ) );
		}
	}

	return array( 0, sprintf( $statement, __( 'Complete!', 'bbpress' ) ) );
}

/**
 * This repair tool will map each user of the current site to their respective
 * forums role. By default, Admins will be Key Masters, and every other role
 * will be the default role defined in Settings > Forums (Participant).
 *
 * @since 2.2.0 bbPress (r4340)
 *
 * @uses bbp_get_user_role_map() To get the map of user roles
 * @uses bbp_get_default_role() To get the default bbPress user role
 * @uses bbp_get_blog_roles() To get the current WordPress roles
 * @uses get_users() To get the users of each role (limited to ID field)
 * @uses bbp_set_user_role() To set each user's forums role
 */
function bbp_admin_repair_user_roles() {

	$statement    = __( 'Remapping forum role for each user on this site&hellip; %s', 'bbpress' );
	$changed      = 0;
	$role_map     = bbp_get_user_role_map();
	$default_role = bbp_get_default_role();

	// Bail if no role map exists
	if ( empty( $role_map ) ) {
		return array( 1, sprintf( $statement, __( 'Failed!', 'bbpress' ) ) );
	}

	// Iterate through each role...
	foreach ( array_keys( bbp_get_blog_roles() ) as $role ) {

		// Reset the offset
		$offset = 0;

		// If no role map exists, give the default forum role (bbp-participant)
		$new_role = isset( $role_map[ $role ] ) ? $role_map[ $role ] : $default_role;

		// Get users of this site, limited to 1000
		while ( $users = get_users( array(
			'role'   => $role,
			'fields' => 'ID',
			'number' => 1000,
			'offset' => $offset
		) ) ) {

			// Iterate through each user of $role and try to set it
			foreach ( (array) $users as $user_id ) {
				if ( bbp_set_user_role( $user_id, $new_role ) ) {
					++$changed; // Keep a count to display at the end
				}
			}

			// Bump the offset for the next query iteration
			$offset = $offset + 1000;
		}
	}

	$result = sprintf( __( 'Complete! %s users updated.', 'bbpress' ), bbp_number_format( $changed ) );

	return array( 0, sprintf( $statement, $result ) );
}

/**
 * Recaches the last post in every topic and forum
 *
 * @since 2.0.0 bbPress (r3040)
 *
 * @uses wpdb::query() To run our recount sql queries
 * @uses is_wp_error() To check if the executed query returned {@link WP_Error}
 * @uses bbp_get_forum_post_type() To get the forum post type
 * @uses bbp_get_topic_post_type() To get the topic post type
 * @uses bbp_get_reply_post_type() To get the reply post type
 * @uses bbp_get_public_status_id() To get the public status id
 * @uses bbp_is_forum_category() To check if the forum is a ategory
 * @uses bbp_update_forum() To update the forums forum id
 * @return array An array of the status code and the message
 */
function bbp_admin_repair_freshness() {

	// Define variables
	$bbp_db    = bbp_db();
	$statement = __( 'Recomputing latest post in every topic and forum&hellip; %s', 'bbpress' );
	$result    = __( 'Failed!', 'bbpress' );

	// First, delete everything.
	if ( is_wp_error( $bbp_db->query( "DELETE FROM `{$bbp_db->postmeta}` WHERE `meta_key` IN ( '_bbp_last_reply_id', '_bbp_last_topic_id', '_bbp_last_active_id', '_bbp_last_active_time' )" ) ) ) {
		return array( 1, sprintf( $statement, $result ) );
	}

	// Post types and status
	$fpt = bbp_get_forum_post_type();
	$tpt = bbp_get_topic_post_type();
	$rpt = bbp_get_reply_post_type();
	$pps = bbp_get_public_status_id();

	// Next, give all the topics with replies the ID their last reply.
	if ( is_wp_error( $bbp_db->query( "INSERT INTO `{$bbp_db->postmeta}` (`post_id`, `meta_key`, `meta_value`)
			( SELECT `topic`.`ID`, '_bbp_last_reply_id', MAX( `reply`.`ID` )
			FROM `{$bbp_db->posts}` AS `topic` INNER JOIN `{$bbp_db->posts}` AS `reply` ON `topic`.`ID` = `reply`.`post_parent`
			WHERE `reply`.`post_status` = '{$pps}' AND `topic`.`post_type` = '{$tpt}' AND `reply`.`post_type` = '{$rpt}'
			GROUP BY `topic`.`ID` )" ) ) ) {
		return array( 2, sprintf( $statement, $result ) );
	}

	// For any remaining topics, give a reply ID of 0.
	if ( is_wp_error( $bbp_db->query( "INSERT INTO `{$bbp_db->postmeta}` (`post_id`, `meta_key`, `meta_value`)
			( SELECT `ID`, '_bbp_last_reply_id', 0
			FROM `{$bbp_db->posts}` AS `topic` LEFT JOIN `{$bbp_db->postmeta}` AS `reply`
			ON `topic`.`ID` = `reply`.`post_id` AND `reply`.`meta_key` = '_bbp_last_reply_id'
			WHERE `reply`.`meta_id` IS NULL AND `topic`.`post_type` = '{$tpt}' )" ) ) ) {
		return array( 3, sprintf( $statement, $result ) );
	}

	// Now we give all the forums with topics the ID their last topic.
	if ( is_wp_error( $bbp_db->query( "INSERT INTO `{$bbp_db->postmeta}` (`post_id`, `meta_key`, `meta_value`)
			( SELECT `forum`.`ID`, '_bbp_last_topic_id', `topic`.`ID`
			FROM `{$bbp_db->posts}` AS `forum` INNER JOIN `{$bbp_db->posts}` AS `topic` ON `forum`.`ID` = `topic`.`post_parent`
			WHERE `topic`.`post_status` = '{$pps}' AND `forum`.`post_type` = '{$fpt}' AND `topic`.`post_type` = '{$tpt}'
			GROUP BY `forum`.`ID` )" ) ) ) {
		return array( 4, sprintf( $statement, $result ) );
	}

	// For any remaining forums, give a topic ID of 0.
	if ( is_wp_error( $bbp_db->query( "INSERT INTO `{$bbp_db->postmeta}` (`post_id`, `meta_key`, `meta_value`)
			( SELECT `ID`, '_bbp_last_topic_id', 0
			FROM `{$bbp_db->posts}` AS `forum` LEFT JOIN `{$bbp_db->postmeta}` AS `topic`
			ON `forum`.`ID` = `topic`.`post_id` AND `topic`.`meta_key` = '_bbp_last_topic_id'
			WHERE `topic`.`meta_id` IS NULL AND `forum`.`post_type` = '{$fpt}' )" ) ) ) {
		return array( 5, sprintf( $statement, $result ) );
	}

	// After that, we give all the topics with replies the ID their last reply (again, this time for a different reason).
	if ( is_wp_error( $bbp_db->query( "INSERT INTO `{$bbp_db->postmeta}` (`post_id`, `meta_key`, `meta_value`)
			( SELECT `topic`.`ID`, '_bbp_last_active_id', MAX( `reply`.`ID` )
			FROM `{$bbp_db->posts}` AS `topic` INNER JOIN `{$bbp_db->posts}` AS `reply` ON `topic`.`ID` = `reply`.`post_parent`
			WHERE `reply`.`post_status` = '{$pps}' AND `topic`.`post_type` = '{$tpt}' AND `reply`.`post_type` = '{$rpt}'
			GROUP BY `topic`.`ID` )" ) ) ) {
		return array( 6, sprintf( $statement, $result ) );
	}

	// For any remaining topics, give a reply ID of themself.
	if ( is_wp_error( $bbp_db->query( "INSERT INTO `{$bbp_db->postmeta}` (`post_id`, `meta_key`, `meta_value`)
			( SELECT `ID`, '_bbp_last_active_id', `ID`
			FROM `{$bbp_db->posts}` AS `topic` LEFT JOIN `{$bbp_db->postmeta}` AS `reply`
			ON `topic`.`ID` = `reply`.`post_id` AND `reply`.`meta_key` = '_bbp_last_active_id'
			WHERE `reply`.`meta_id` IS NULL AND `topic`.`post_type` = '{$tpt}' )" ) ) ) {
		return array( 7, sprintf( $statement, $result ) );
	}

	// Give topics with replies their last update time.
	if ( is_wp_error( $bbp_db->query( "INSERT INTO `{$bbp_db->postmeta}` (`post_id`, `meta_key`, `meta_value`)
			( SELECT `topic`.`ID`, '_bbp_last_active_time', MAX( `reply`.`post_date` )
			FROM `{$bbp_db->posts}` AS `topic` INNER JOIN `{$bbp_db->posts}` AS `reply` ON `topic`.`ID` = `reply`.`post_parent`
			WHERE `reply`.`post_status` = '{$pps}' AND `topic`.`post_type` = '{$tpt}' AND `reply`.`post_type` = '{$rpt}'
			GROUP BY `topic`.`ID` )" ) ) ) {
		return array( 8, sprintf( $statement, $result ) );
	}

	// Give topics without replies their last update time.
	if ( is_wp_error( $bbp_db->query( "INSERT INTO `{$bbp_db->postmeta}` (`post_id`, `meta_key`, `meta_value`)
			( SELECT `ID`, '_bbp_last_active_time', `post_date`
			FROM `{$bbp_db->posts}` AS `topic` LEFT JOIN `{$bbp_db->postmeta}` AS `reply`
			ON `topic`.`ID` = `reply`.`post_id` AND `reply`.`meta_key` = '_bbp_last_active_time'
			WHERE `reply`.`meta_id` IS NULL AND `topic`.`post_type` = '{$tpt}' )" ) ) ) {
		return array( 9, sprintf( $statement, $result ) );
	}

	// Forums need to know what their last active item is as well. Now it gets a bit more complex to do in the database.
	$forums = $bbp_db->get_col( "SELECT `ID` FROM `{$bbp_db->posts}` WHERE `post_type` = '{$fpt}' and `post_status` != 'auto-draft'" );
	if ( is_wp_error( $forums ) ) {
		return array( 10, sprintf( $statement, $result ) );
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

	// Complete results
	return array( 0, sprintf( $statement, __( 'Complete!', 'bbpress' ) ) );
}

/**
 * Repairs the relationship of sticky topics to the actual parent forum
 *
 * @since 2.3.0 bbPress (r4695)
 *
 * @uses wpdb::get_col() To run our recount sql queries
 * @uses is_wp_error() To check if the executed query returned {@link WP_Error}
 * @uses bbp_get_forum_post_type() To get the forum post type
 * @uses get_post_meta() To get the sticky topics
 * @uses bbp_is_topic_super_sticky() To check if the topic is super sticky
 * @uses bbp_get_topic_forum_id() To get the topics forum id
 * @uses update_post_meta To update the topics sticky post meta
 * @return array An array of the status code and the message
 */
function bbp_admin_repair_sticky() {

	// Define variables
	$bbp_db    = bbp_db();
	$statement = __( 'Repairing the sticky topic to the parent forum relationships&hellip; %s', 'bbpress' );
	$result    = __( 'Failed!', 'bbpress' );

	$forums    = $bbp_db->get_col( "SELECT ID FROM `{$bbp_db->posts}` WHERE `post_type` = '" . bbp_get_forum_post_type() . "'" );

	// Bail if no forums found
	if ( empty( $forums ) || is_wp_error( $forums ) ) {
		return array( 1, sprintf( $statement, $result ) );
	}

	// Loop through forums and get their sticky topics
	foreach ( $forums as $forum ) {
		$forum_stickies[ $forum ] = get_post_meta( $forum, '_bbp_sticky_topics', true );
	}

	// Cleanup
	unset( $forums, $forum );

	// Loop through each forum with sticky topics
	foreach ( $forum_stickies as $forum_id => $stickies ) {

		// Skip if no stickies
		if ( empty( $stickies ) ) {
			continue;
		}

		// Loop through each sticky topic
		foreach ( $stickies as $id => $topic_id ) {

			// If the topic is not a super sticky, and the forum ID does not
			// match the topic's forum ID, unset the forum's sticky meta.
			if ( ! bbp_is_topic_super_sticky( $topic_id ) && $forum_id !== bbp_get_topic_forum_id( $topic_id ) ) {
				unset( $forum_stickies[ $forum_id ][ $id ] );
			}
		}

		// Get sticky topic ID's, or use empty string
		$stickers = empty( $forum_stickies[ $forum_id ] ) ? '' : array_values( $forum_stickies[ $forum_id ] );

		// Update the forum's sticky topics meta
		update_post_meta( $forum_id, '_bbp_sticky_topics', $stickers );
	}

	// Complete results
	return array( 0, sprintf( $statement, __( 'Complete!', 'bbpress' ) ) );
}

/**
 * Repair closed topics
 *
 * Closed topics that are missing the postmeta "_bbp_status" key value "publish"
 * result in unexpected behaviour, primarily this would have only occured if you
 * had imported forums from another forum package previous to bbPress v2.6,
 * https://bbpress.trac.wordpress.org/ticket/2577
 *
 * @since 2.6.0 bbPress (r5668)
 *
 * @uses wpdb::get_col() To run our recount sql queries
 * @uses is_wp_error() To check if the executed query returned {@link WP_Error}
 * @uses bbp_get_topic_post_type() To get the topic post type
 * @uses get_post_meta() To get the closed topic status meta
 * @uses update_post_meta To update the topics closed status post meta
 * @return array An array of the status code and the message
 */
function bbp_admin_repair_closed_topics() {

	// Define variables
	$bbp_db        = bbp_db();
	$statement     = __( 'Repairing closed topics&hellip; %s', 'bbpress' );
	$result        = __( 'No closed topics to repair.', 'bbpress' );
	$changed       = 0;

	$closed_topics = $bbp_db->get_col( "SELECT ID FROM `{$bbp_db->posts}` WHERE `post_type` = '" . bbp_get_topic_post_type() . "' AND `post_status` = 'closed'" );

	// Bail if no closed topics found
	if ( empty( $closed_topics ) || is_wp_error( $closed_topics ) ) {
		return array( 1, sprintf( $statement, $result ) );
	}

	// Loop through each closed topic
	foreach ( $closed_topics as $closed_topic ) {

		// Check if the closed topic already has a postmeta _bbp_status value
		$topic_status = get_post_meta( $closed_topic, '_bbp_status', true );

		// If we don't have a postmeta _bbp_status value
		if( empty( $topic_status ) ) {
			update_post_meta( $closed_topic, '_bbp_status', 'publish' );
			++$changed; // Keep a count to display at the end
		}
	}

	// Cleanup
	unset( $closed_topics, $closed_topic, $topic_status );

	// Complete results
	$result = sprintf( _n( 'Complete! %d closed topic repaired.', 'Complete! %d closed topics repaired.', $changed, 'bbpress' ), $changed );

	return array( 0, sprintf( $statement, $result ) );
}

/**
 * Recaches the private and hidden forums
 *
 * @since 2.2.0 bbPress (r4104)
 *
 * @uses bbp_repair_forum_visibility() To update private and hidden forum ids
 * @return array An array of the status code and the message
 */
function bbp_admin_repair_forum_visibility() {
	$statement = __( 'Recalculating forum visibility &hellip; %s', 'bbpress' );

	// Bail if queries returned errors
	if ( ! bbp_repair_forum_visibility() ) {
		return array( 2, sprintf( $statement, __( 'Failed!',   'bbpress' ) ) );

	// Complete results
	} else {
		return array( 0, sprintf( $statement, __( 'Complete!', 'bbpress' ) ) );
	}
}

/**
 * Recaches the parent forum meta for each topic and reply
 *
 * @since 2.1.0 bbPress (r3876)
 *
 * @uses wpdb::query() To run our recount sql queries
 * @uses is_wp_error() To check if the executed query returned {@link WP_Error}
 * @uses bbp_get_topic_post_type() To get the topic post type
 * @uses bbp_get_reply_post_type() To get the reply post type
 * @return array An array of the status code and the message
 */
function bbp_admin_repair_forum_meta() {

	// Define variables
	$bbp_db    = bbp_db();
	$statement = __( 'Recalculating the forum for each post &hellip; %s', 'bbpress' );
	$result    = __( 'Failed!', 'bbpress' );

	// First, delete everything.
	if ( is_wp_error( $bbp_db->query( "DELETE FROM `{$bbp_db->postmeta}` WHERE `meta_key` = '_bbp_forum_id'" ) ) ) {
		return array( 1, sprintf( $statement, $result ) );
	}

	// Post types and status
	$tpt = bbp_get_topic_post_type();
	$rpt = bbp_get_reply_post_type();

	// Next, give all the topics their parent forum id.
	if ( is_wp_error( $bbp_db->query( "INSERT INTO `{$bbp_db->postmeta}` (`post_id`, `meta_key`, `meta_value`)
			( SELECT `topic`.`ID`, '_bbp_forum_id', `topic`.`post_parent`
			FROM `$bbp_db->posts`
				AS `topic`
			WHERE `topic`.`post_type` = '{$tpt}'
			GROUP BY `topic`.`ID` )" ) ) ) {
		return array( 2, sprintf( $statement, $result ) );
	}

	// Next, give all the replies their parent forum id.
	if ( is_wp_error( $bbp_db->query( "INSERT INTO `{$bbp_db->postmeta}` (`post_id`, `meta_key`, `meta_value`)
			( SELECT `reply`.`ID`, '_bbp_forum_id', `topic`.`post_parent`
			FROM `$bbp_db->posts`
				AS `reply`
			INNER JOIN `$bbp_db->posts`
				AS `topic`
				ON `reply`.`post_parent` = `topic`.`ID`
			WHERE `topic`.`post_type` = '{$tpt}'
				AND `reply`.`post_type` = '{$rpt}'
			GROUP BY `reply`.`ID` )" ) ) ) {
		return array( 3, sprintf( $statement, $result ) );
	}

	// Complete results
	return array( 0, sprintf( $statement, __( 'Complete!', 'bbpress' ) ) );
}

/**
 * Recaches the topic for each post
 *
 * @since 2.1.0 bbPress (r3876)
 *
 * @uses wpdb::query() To run our recount sql queries
 * @uses is_wp_error() To check if the executed query returned {@link WP_Error}
 * @uses bbp_get_topic_post_type() To get the topic post type
 * @uses bbp_get_reply_post_type() To get the reply post type
 * @return array An array of the status code and the message
 */
function bbp_admin_repair_topic_meta() {

	// Define variables
	$bbp_db    = bbp_db();
	$statement = __( 'Recalculating the topic for each post &hellip; %s', 'bbpress' );
	$result    = __( 'Failed!', 'bbpress' );

	// First, delete everything.
	if ( is_wp_error( $bbp_db->query( "DELETE FROM `{$bbp_db->postmeta}` WHERE `meta_key` = '_bbp_topic_id'" ) ) ) {
		return array( 1, sprintf( $statement, $result ) );
	}

	// Post types and status
	$tpt = bbp_get_topic_post_type();
	$rpt = bbp_get_reply_post_type();

	// Next, give all the topics with replies the ID their last reply.
	if ( is_wp_error( $bbp_db->query( "INSERT INTO `{$bbp_db->postmeta}` (`post_id`, `meta_key`, `meta_value`)
			( SELECT `topic`.`ID`, '_bbp_topic_id', `topic`.`ID`
			FROM `$bbp_db->posts`
				AS `topic`
			WHERE `topic`.`post_type` = '{$tpt}'
			GROUP BY `topic`.`ID` )" ) ) ) {
		return array( 3, sprintf( $statement, $result ) );
	}

	// Next, give all the topics with replies the ID their last reply.
	if ( is_wp_error( $bbp_db->query( "INSERT INTO `{$bbp_db->postmeta}` (`post_id`, `meta_key`, `meta_value`)
			( SELECT `reply`.`ID`, '_bbp_topic_id', `topic`.`ID`
			FROM `$bbp_db->posts`
				AS `reply`
			INNER JOIN `$bbp_db->posts`
				AS `topic`
				ON `reply`.`post_parent` = `topic`.`ID`
			WHERE `topic`.`post_type` = '{$tpt}'
				AND `reply`.`post_type` = '{$rpt}'
			GROUP BY `reply`.`ID` )" ) ) ) {
		return array( 4, sprintf( $statement, $result ) );
	}

	// Complete results
	return array( 0, sprintf( $statement, __( 'Complete!', 'bbpress' ) ) );
}

/**
 * Recalculate reply menu order
 *
 * @since 2.5.4 bbPress (r5367)
 *
 * @uses wpdb::query() To run our recount sql queries
 * @uses is_wp_error() To check if the executed query returned {@link WP_Error}
 * @uses bbp_get_reply_post_type() To get the reply post type
 * @uses bbp_update_reply_position() To update the reply position
 * @return array An array of the status code and the message
 */
function bbp_admin_repair_reply_menu_order() {

	// Define variables
	$bbp_db    = bbp_db();
	$statement = __( 'Recalculating reply menu order &hellip; %s', 'bbpress' );
	$result    = __( 'No reply positions to recalculate.',         'bbpress' );

	// Delete cases where `_bbp_reply_to` was accidentally set to itself
	if ( is_wp_error( $bbp_db->query( "DELETE FROM `{$bbp_db->postmeta}` WHERE `meta_key` = '_bbp_reply_to' AND `post_id` = `meta_value`" ) ) ) {
		return array( 1, sprintf( $statement, $result ) );
	}

	// Post type
	$rpt = bbp_get_reply_post_type();

	// Get an array of reply id's to update the menu oder for each reply
	$replies = $bbp_db->get_results( "SELECT `a`.`ID` FROM `{$bbp_db->posts}` AS `a`
										INNER JOIN (
											SELECT `menu_order`, `post_parent`
											FROM `{$bbp_db->posts}`
											GROUP BY `menu_order`, `post_parent`
											HAVING COUNT( * ) >1
										)`b`
										ON `a`.`menu_order` = `b`.`menu_order`
										AND `a`.`post_parent` = `b`.`post_parent`
										WHERE `post_type` = '{$rpt}'", OBJECT_K );

	// Bail if no replies returned
	if ( empty( $replies ) ) {
		return array( 1, sprintf( $statement, $result ) );
	}

	// Recalculate the menu order position for each reply
	foreach ( $replies as $reply ) {
		bbp_update_reply_position( $reply->ID );
	}

	// Cleanup
	unset( $replies, $reply );

	// Flush the cache; things are about to get ugly.
	wp_cache_flush();

	return array( 0, sprintf( $statement, __( 'Complete!', 'bbpress' ) ) );
}
