<?php

/**
 * bbPress Admin Repair and Upgrade Screens.
 *
 * @package bbPress
 * @subpackage Administration
 */

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

/**
 * Render the shared repair or upgrade tools screen.
 *
 * @since 2.7.0 bbPress (r8315)
 *
 * @param string $type Repair tool type: repair or upgrade.
 */
function bbp_admin_tools_repair_upgrade_page( $type = 'repair' ) {
	if ( ! in_array( $type, array( 'repair', 'upgrade' ), true ) ) {
		return;
	}
	$is_upgrade = 'upgrade' === $type;

	// Get the registered tools for this screen
	$tools = bbp_admin_repair_list( $type );

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
		<h2 class="nav-tab-wrapper"><?php bbp_tools_admin_tabs( 'bbp-' . $type ); ?></h2>

		<?php if ( $is_upgrade ) : ?>
		<p><?php esc_html_e( 'As bbPress improves, occasionally database upgrades are required but some forums are too large to upgrade automatically. Use the tools below to manually run upgrade routines.', 'bbpress' ); ?></p>
		<p class="description"><?php esc_html_e( 'Some of these tools create substantial database overhead. Use caution when running more than 1 upgrade at a time.', 'bbpress' ); ?></p>
		<?php else : ?>
			<p><?php esc_html_e( 'bbPress keeps track of relationships between forums, topics, replies, topic-tags, favorites, subscriptions, and users. Occasionally these relationships become out of sync, most often after an import or migration. Use the tools below to manually recalculate these relationships.', 'bbpress' ); ?></p>
			<p class="description"><?php esc_html_e( 'Some of these tools create substantial database overhead. Use caution when running more than 1 repair at a time.', 'bbpress' ); ?></p>
		<?php endif; ?>

		<?php bbp_admin_repair_tool_status_filters(); ?>

		<form class="settings" method="get" action="">

			<?php bbp_admin_repair_list_search_form(); ?>

			<input type="hidden" name="page" value="<?php echo esc_attr( 'bbp-' . $type ); ?>" />
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

					<?php if ( $is_upgrade ) : ?>
						<?php bbp_admin_repair_list_versions_filter(); ?>
					<?php endif; ?>

					<input type="submit" name="filter_action" id="components-submit" class="button" value="<?php esc_html_e( 'Filter', 'bbpress' ); ?>">
				</div>
				<br class="clear">
			</div>
			<table class="wp-list-table widefat striped posts<?php echo $is_upgrade ? ' bbp-upgrade-tools' : ''; ?>">
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
						<?php if ( $is_upgrade ) : ?>
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
						<?php endif; ?>
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

							<tr id="bbp-repair-tool-<?php echo esc_attr( $item_html_id ); ?>" class="bbp-repair-tools inactive<?php echo $is_upgrade && ! empty( $item['pending'] ) ? ' status-pending' : ''; ?>">
								<th scope="row" class="check-column">
									<label class="screen-reader-text" for="<?php echo esc_attr( $select_id ); ?>">
										<?php
										printf(
											/* translators: %s: Repair or upgrade tool title */
											esc_html__( 'Select %s', 'bbpress' ),
											esc_html( $item['title'] )
										);
										?>
									</label>
									<input type="checkbox" name="checked[]" value="<?php echo esc_attr( $item['id'] ); ?>" id="<?php echo esc_attr( $select_id ); ?>">
								</th>
								<td class="bbp-tool-title column-primary column-description" data-colname="<?php esc_html_e( 'Description', 'bbpress' ); ?>">
									<strong>
										<?php echo esc_html( $item['title'] ); ?>
										<?php if ( $is_upgrade && ! empty( $item['pending'] ) ) : ?>
											<span class="post-state">&mdash; <?php esc_html_e( 'Pending', 'bbpress' ); ?></span>
										<?php endif; ?>
									</strong><?php

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
								<?php if ( $is_upgrade ) : ?>
								<td class="column-version desc" data-colname="<?php esc_html_e( 'Version', 'bbpress' ); ?>">
									<div class="bbp-tool-version">

										<?php echo implode( ', ', bbp_get_admin_repair_tool_version( $item ) ); ?>

									</div>
								</td>
								<?php endif; ?>
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
							<td colspan="<?php echo $is_upgrade ? 5 : 4; ?>">
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
						<?php if ( $is_upgrade ) : ?>
						<th scope="col" class="manage-column column-version"><?php esc_html_e( 'Version', 'bbpress' ); ?></th>
						<?php endif; ?>
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

