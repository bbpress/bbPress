<?php

/**
 * bbPress Admin Upgrade Functions
 *
 * @package bbPress
 * @subpackage Administration
 */

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

/**
 * Admin repair page
 *
 * @since 2.6.0 bbPress (r6278)
 *
 * @uses bbp_admin_repair_list() To get the recount list
 * @uses check_admin_referer() To verify the nonce and the referer
 * @uses wp_cache_flush() To flush the cache
 * @uses do_action() Calls 'admin_notices' to display the notices
 * @uses wp_nonce_field() To add a hidden nonce field
 */
function bbp_admin_upgrade_page() {

	// Get the registered upgrade tools
	$tools = bbp_admin_repair_list( 'upgrade' ); ?>

	<div class="wrap">
		<h1><?php esc_html_e( 'Forum Tools', 'bbpress' ); ?></h1>
		<h2 class="nav-tab-wrapper"><?php bbp_tools_admin_tabs( __( 'Upgrade Forums', 'bbpress' ) ); ?></h2>

		<p><?php esc_html_e( 'As bbPress improves, occasionally database upgrades are required but some forums are too large to upgrade automatically. Use the tools below to manually run upgrade routines.', 'bbpress' ); ?></p>
		<p class="description"><?php esc_html_e( 'Some of these tools create substantial database overhead. Use caution when running more than 1 upgrade at a time.', 'bbpress' ); ?></p>

		<?php bbp_admin_repair_tool_overhead_filters(); ?>

		<form class="settings" method="get" action="">

			<?php bbp_admin_repair_list_search_form(); ?>

			<input type="hidden" name="page" value="bbp-upgrade" />
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
 * Upgrade user favorites for bbPress 2.6 and higher
 *
 * @since 2.6.0 bbPress (r6174)
 *
 * @return array An array of the status code and the message
 */
function bbp_admin_upgrade_user_favorites() {

	// Define variables
	$bbp_db    = bbp_db();
	$statement = __( 'Upgrading user favorites &hellip; %s', 'bbpress' );
	$result    = __( 'No favorites to upgrade.',             'bbpress' );
	$total     = 0;
	$key       = $bbp_db->prefix . '_bbp_favorites';
	$favs      = $bbp_db->get_results( $bbp_db->prepare( "SELECT * FROM {$bbp_db->usermeta} WHERE meta_key = %s", $key ) );

	// Bail if no closed topics found
	if ( empty( $favs ) || is_wp_error( $favs ) ) {
		return array( 1, sprintf( $statement, $result ) );
	}

	// Loop through each user's favorites
	foreach ( $favs as $meta ) {

		// Get post IDs
		$post_ids = explode( ',', $meta->meta_value );

		// Add user ID to all favorited posts
		foreach ( $post_ids as $post_id ) {

			// Skip if already exists
			if ( $bbp_db->get_var( $bbp_db->prepare( "SELECT COUNT(*) FROM {$bbp_db->postmeta} WHERE post_id = %d AND meta_key = %s AND meta_value = %d", $post_id, '_bbp_favorite', $meta->user_id ) ) ) {
				continue;
			}

			// Add the post meta
			$added = add_post_meta( $post_id, '_bbp_favorite', $meta->user_id, false );

			// Bump counts if successfully added
			if ( ! empty( $added ) ) {
				++$total;
			}
		}
	}

	// Cleanup
	unset( $favs, $added, $post_ids );

	// Complete results
	$result = sprintf( _n( 'Complete! %d favorite upgraded.', 'Complete! %d favorites upgraded.', $total, 'bbpress' ), $total );

	return array( 0, sprintf( $statement, $result ) );
}

/**
 * Upgrade user topic subscriptions for bbPress 2.6 and higher
 *
 * @since 2.6.0 bbPress (r6174)
 *
 * @return array An array of the status code and the message
 */
function bbp_admin_upgrade_user_topic_subscriptions() {

	// Define variables
	$bbp_db    = bbp_db();
	$statement = __( 'Upgrading user topic subscriptions &hellip; %s', 'bbpress' );
	$result    = __( 'No topic subscriptions to upgrade.',             'bbpress' );
	$total     = 0;
	$key       = $bbp_db->prefix . '_bbp_subscriptions';
	$subs      = $bbp_db->get_results( $bbp_db->prepare( "SELECT * FROM {$bbp_db->usermeta} WHERE meta_key = %s ORDER BY user_id", $key ) );

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
			if ( $bbp_db->get_var( $bbp_db->prepare( "SELECT COUNT(*) FROM {$bbp_db->postmeta} WHERE post_id = %d AND meta_key = %s AND meta_value = %d", $post_id, '_bbp_subscription', $meta->user_id ) ) ) {
				continue;
			}

			// Add the post meta
			$added = add_post_meta( $post_id, '_bbp_subscription', $meta->user_id, false );

			// Bump counts if successfully added
			if ( ! empty( $added ) ) {
				++$total;
			}
		}
	}

	// Cleanup
	unset( $subs, $added, $post_ids );

	// Complete results
	$result = sprintf( _n( 'Complete! %d topic subscription upgraded.', 'Complete! %d topic subscriptions upgraded.', $total, 'bbpress' ), $total );

	return array( 0, sprintf( $statement, $result ) );
}

/**
 * Upgrade user forum subscriptions for bbPress 2.6 and higher
 *
 * @since 2.6.0 bbPress (r6193)
 *
 * @return array An array of the status code and the message
 */
function bbp_admin_upgrade_user_forum_subscriptions() {

	// Define variables
	$bbp_db    = bbp_db();
	$statement = __( 'Upgrading user forum subscriptions &hellip; %s', 'bbpress' );
	$result    = __( 'No forum subscriptions to upgrade.',             'bbpress' );
	$total     = 0;
	$key       = $bbp_db->prefix . '_bbp_forum_subscriptions';
	$subs      = $bbp_db->get_results( $bbp_db->prepare( "SELECT * FROM {$bbp_db->usermeta} WHERE meta_key = %s ORDER BY user_id", $key ) );

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
			if ( $bbp_db->get_var( $bbp_db->prepare( "SELECT COUNT(*) FROM {$bbp_db->postmeta} WHERE post_id = %d AND meta_key = %s AND meta_value = %d", $post_id, '_bbp_forum_subscription', $meta->user_id ) ) ) {
				continue;
			}

			// Add the post meta
			$added = add_post_meta( $post_id, '_bbp_subscription', $meta->user_id, false );

			// Bump counts if successfully added
			if ( ! empty( $added ) ) {
				++$total;
			}
		}
	}

	// Cleanup
	unset( $subs, $added, $post_ids );

	// Complete results
	$result = sprintf( _n( 'Complete! %d forum subscription upgraded.', 'Complete! %d forum subscriptions upgraded.', $total, 'bbpress' ), $total );

	return array( 0, sprintf( $statement, $result ) );
}

/**
 * Remove favorites data from wp_usermeta for bbPress 2.6 and higher
 *
 * @since 2.6.0 bbPress (r6281)
 *
 * @return array An array of the status code and the message
 */
function bbp_admin_upgrade_remove_favorites_from_usermeta() {

	// Define variables
	$bbp_db    = bbp_db();
	$statement = __( 'Remove favorites from usermeta &hellip; %s', 'bbpress' );
	$result    = __( 'No favorites to remove.',                    'bbpress' );
	$total     = 0;
	$key       = $bbp_db->prefix . '_bbp_favorites';
	$favs      = $bbp_db->get_results( $bbp_db->prepare( "SELECT * FROM {$bbp_db->usermeta} WHERE meta_key = %s ORDER BY user_id", $key ) );

	// Bail if no favorites found
	if ( empty( $favs ) || is_wp_error( $favs ) ) {
		return array( 1, sprintf( $statement, $result ) );
	}

	// Loop through each user's favorites
	foreach ( $favs as $meta ) {

		// Get post IDs
		$post_ids  = explode( ',', $meta->meta_value );
		$total     = $total + count( $post_ids );

		delete_metadata_by_mid( 'user', $meta->umeta_id );
	}

	// Cleanup
	unset( $favs, $post_ids );

	// Complete results
	$result = sprintf( _n( 'Complete! %d favorites upgraded.', 'Complete! %d favorites upgraded.', $total, 'bbpress' ), $total );

	return array( 0, sprintf( $statement, $result ) );
}

/**
 * Remove topic subscriptions data from wp_usermeta for bbPress 2.6 and higher
 *
 * @since 2.6.0 bbPress (r6281)
 *
 * @return array An array of the status code and the message
 */
function bbp_admin_upgrade_remove_topic_subscriptions_from_usermeta() {

	// Define variables
	$bbp_db    = bbp_db();
	$statement = __( 'Remove topic subscriptions from usermeta &hellip; %s', 'bbpress' );
	$result    = __( 'No topic subscriptions to remove.',                    'bbpress' );
	$total     = 0;
	$key       = $bbp_db->prefix . '_bbp_subscriptions';
	$subs      = $bbp_db->get_results( $bbp_db->prepare( "SELECT * FROM {$bbp_db->usermeta} WHERE meta_key = %s ORDER BY user_id", $key ) );

	// Bail if no forum favorites found
	if ( empty( $subs ) || is_wp_error( $subs ) ) {
		return array( 1, sprintf( $statement, $result ) );
	}

	// Loop through each user's favorites
	foreach ( $subs as $meta ) {

		// Get post IDs
		$post_ids  = explode( ',', $meta->meta_value );
		$total     = $total + count( $post_ids );

		delete_metadata_by_mid( 'user', $meta->umeta_id );
	}

	// Cleanup
	unset( $subs, $post_ids );

	// Complete results
	$result = sprintf( _n( 'Complete! %d topic subscription upgraded.', 'Complete! %d topic subscriptions upgraded.', $total, 'bbpress' ), $total );

	return array( 0, sprintf( $statement, $result ) );
}

/**
 * Remove topic subscriptions data from wp_usermeta for bbPress 2.6 and higher
 *
 * @since 2.6.0 bbPress (r6281)
 *
 * @return array An array of the status code and the message
 */
function bbp_admin_upgrade_remove_forum_subscriptions_from_usermeta() {

	// Define variables
	$bbp_db    = bbp_db();
	$statement = __( 'Remove forum subscriptions from usermeta &hellip; %s', 'bbpress' );
	$result    = __( 'No forum subscriptions to remove.',                    'bbpress' );
	$total     = 0;
	$key       = $bbp_db->prefix . '_bbp_forum_subscriptions';
	$subs      = $bbp_db->get_results( $bbp_db->prepare( "SELECT * FROM {$bbp_db->usermeta} WHERE meta_key = %s ORDER BY user_id", $key ) );

	// Bail if no forum favorites found
	if ( empty( $subs ) || is_wp_error( $subs ) ) {
		return array( 1, sprintf( $statement, $result ) );
	}

	// Loop through each user's favorites
	foreach ( $subs as $meta ) {

		// Get post IDs
		$post_ids  = explode( ',', $meta->meta_value );
		$total     = $total + count( $post_ids );

		delete_metadata_by_mid( 'user', $meta->umeta_id );
	}

	// Cleanup
	unset( $subs, $post_ids );

	// Complete results
	$result = sprintf( _n( 'Complete! %d forum subscription upgraded.', 'Complete! %d forum subscriptions upgraded.', $total, 'bbpress' ), $total );

	return array( 0, sprintf( $statement, $result ) );
}
