<?php

/**
 * bbPress Admin Tools Page
 *
 * @package bbPress
 * @subpackage Administration
 */

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

/** Repair ********************************************************************/

/**
 * Admin repair page
 *
 * @since bbPress (r2613) Converted from bbPress 1.2
 * @since bbPress (r5885) Upgraded to list-table UI
 *
 * @todo Use a real list table
 *
 * @uses bbp_admin_repair_list() To get the recount list
 * @uses check_admin_referer() To verify the nonce and the referer
 * @uses wp_cache_flush() To flush the cache
 * @uses do_action() Calls 'admin_notices' to display the notices
 * @uses wp_nonce_field() To add a hidden nonce field
 */
function bbp_admin_repair() {

	// Get the registered repair tools
	$tools = bbp_admin_repair_list(); ?>

	<div class="wrap">
		<h1><?php esc_html_e( 'Forum Tools', 'bbpress' ); ?></h1>
		<h2 class="nav-tab-wrapper"><?php bbp_tools_admin_tabs( __( 'Repair Forums', 'bbpress' ) ); ?></h2>

		<p><?php esc_html_e( 'bbPress keeps track of relationships between forums, topics, replies, and topic tags, and users. Occasionally these relationships become out of sync, most often after an import or migration. Use the tools below to manually recalculate these relationships.', 'bbpress' ); ?></p>
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
						<th scope="col" id="description" class="manage-column column-description"><?php esc_html_e( 'Description', 'bbpress' ); ?></th>
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
								<td class="bbp-tool-title column-primary">
									<strong><?php echo esc_html( $item['description'] ); ?></strong>
									<div class="row-actions hide-if-no-js">
										<span class="run">
											<a href="<?php bbp_admin_repair_tool_run_url( $item['id'] ); ?>" aria-label="<?php printf( esc_html__( 'Run %s', 'bbpress' ), $item['description'] ); ?>" id="<?php echo esc_attr( $item['id'] ); ?>" ><?php esc_html_e( 'Run', 'bbpress' ); ?></a>
										</span>
									</div>
									<button type="button" class="toggle-row">
										<span class="screen-reader-text"><?php esc_html_e( 'Show more details', 'bbpress' ); ?></span>
									</button>
								</td>
								<td class="column-components desc">
									<div class="bbp-tool-overhead">

										<?php echo implode( ', ', bbp_get_admin_repair_tool_components( $item ) ); ?>

									</div>
								</td>
								<td class="column-overhead desc">
									<div class="bbp-tool-overhead">

										<?php echo esc_html( $item['overhead'] ); ?>

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
						<th scope="col" class="manage-column column-description"><?php esc_html_e( 'Description', 'bbpress' ); ?></th>
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
 * Handle the processing and feedback of the admin tools page
 *
 * @since bbPress (r2613)
 *
 * @uses bbp_admin_repair_list() To get the recount list
 * @uses check_admin_referer() To verify the nonce and the referer
 * @uses wp_cache_flush() To flush the cache
 * @uses do_action() Calls 'admin_notices' to display the notices
 */
function bbp_admin_repair_handler() {

	if ( ! bbp_is_get_request() ) {
		return;
	}

	// Get the current action or bail
	if ( ! empty( $_GET['action'] ) ) {
		$action = sanitize_key( $_GET['action'] );
	} elseif ( ! empty( $_GET['action2'] ) ) {
		$action = sanitize_key( $_GET['action2'] );
	} else {
		return;
	}

	// Bail if not running an action
	if ( 'run' !== $action ) {
		return;
	}

	check_admin_referer( 'bbpress-do-counts' );

	// Stores messages
	$messages = array();

	// Kill all the caches, because we don't know what's where anymore
	wp_cache_flush();

	// Get the list
	$list = bbp_get_admin_repair_tools();

	// Run through checked repair tools
	if ( ! empty( $_GET['checked'] ) ) {
		foreach ( $_GET['checked'] as $item_id ) {
			if ( isset( $list[ $item_id ] ) && is_callable( $list[ $item_id ]['callback'] ) ) {
				$messages[] = call_user_func( $list[ $item_id ]['callback'] );
			}
		}
	}

	// Feedback
	if ( count( $messages ) ) {
		foreach ( $messages as $message ) {
			bbp_admin_tools_feedback( $message[1] );
		}
	}

	// @todo Redirect away from here
}

/**
 * Output the URL to run a specific repair tool
 *
 * @since bbPress (r5885)
 *
 * @param string $component
 */
function bbp_admin_repair_tool_run_url( $component = '' ) {
	echo esc_url( bbp_get_admin_repair_tool_run_url( $component ) );
}

/**
 * Return the URL to run a specific repair tool
 *
 * @since bbPress (r5885)
 *
 * @param string $component
 */
function bbp_get_admin_repair_tool_run_url( $component = '' ) {
	$tools  = admin_url( 'tools.php' );
	$args   = array( 'page' => 'bbp-repair', 'action' => 'run', 'checked' => array( $component ) );
	$url    = add_query_arg( $args, $tools );
	$nonced = wp_nonce_url( $url, 'bbpress-do-counts' );

	return apply_filters( 'bbp_get_admin_repair_tool_run_url', $nonced, $component );
}

/**
 * Contextual help for Repair Forums tools page
 *
 * @since bbPress (r5314)
 * @uses get_current_screen()
 */

function bbp_admin_tools_repair_help() {

	$current_screen = get_current_screen();

	// Bail if current screen could not be found
	if ( empty( $current_screen ) ) {
		return;
	}

	// Repair Forums
	$current_screen->add_help_tab( array(
		'id'      => 'repair_forums',
		'title'   => __( 'Repair Forums', 'bbpress' ),
		'content' => '<p>' . __( 'There is more detailed information available on the bbPress and BuddyPress codex for the following:', 'bbpress' ) . '</p>' .
					 '<p>' .
						'<ul>' .
							'<li>' . __( 'BuddyPress Group Forums: <a href="https://codex.buddypress.org/getting-started/installing-group-and-sitewide-forums/">Installing Group and Sitewide Forums</a> and <a href="https://codex.buddypress.org/getting-started/guides/migrating-from-old-forums-to-bbpress-2/">Migrating from old forums to bbPress 2.2+</a>.', 'bbpress' ) . '</li>' .
							'<li>' . __( 'bbPress roles: <a href="https://codex.bbpress.org/bbpress-user-roles-and-capabilities/" target="_blank">bbPress User Roles and Capabilities</a>',                                                                                                                                                                        'bbpress' ) . '</li>' .
						'</ul>' .
					'</p>' .
					'<p>' . __( 'Also see <a href="https://codex.bbpress.org/repair-forums/">bbPress: Repair Forums</a>.', 'bbpress' ) . '</p>'
	) );

	// Help Sidebar
	$current_screen->set_help_sidebar(
		'<p><strong>' . __( 'For more information:', 'bbpress' ) . '</strong></p>' .
		'<p>' . __( '<a href="https://codex.bbpress.org" target="_blank">bbPress Documentation</a>',    'bbpress' ) . '</p>' .
		'<p>' . __( '<a href="https://bbpress.org/forums/" target="_blank">bbPress Support Forums</a>', 'bbpress' ) . '</p>'
	);
}

/**
 * Contextual help for Reset Forums tools page
 *
 * @since bbPress (r5314)
 * @uses get_current_screen()
 */

function bbp_admin_tools_reset_help() {

	$current_screen = get_current_screen();

	// Bail if current screen could not be found
	if ( empty( $current_screen ) ) {
		return;
	}

	// Reset Forums
	$current_screen->add_help_tab( array(
		'id'      => 'reset_forums',
		'title'   => __( 'Reset Forums', 'bbpress' ),
		'content' => '<p>' . __( 'Also see <a href="https://codex.bbpress.org/reset-forums/">bbPress: Reset Forums</a>.', 'bbpress' ) . '</p>'
	) );

	// Help Sidebar
	$current_screen->set_help_sidebar(
		'<p><strong>' . __( 'For more information:', 'bbpress' ) . '</strong></p>' .
		'<p>' . __( '<a href="https://codex.bbpress.org" target="_blank">bbPress Documentation</a>',    'bbpress' ) . '</p>' .
		'<p>' . __( '<a href="https://bbpress.org/forums/" target="_blank">bbPress Support Forums</a>', 'bbpress' ) . '</p>'
	);
}

/**
 * Contextual help for Import Forums tools page
 *
 * @since bbPress (r5314)
 * @uses get_current_screen()
 */

function bbp_admin_tools_converter_help() {

	$current_screen = get_current_screen();

	// Bail if current screen could not be found
	if ( empty( $current_screen ) ) {
		return;
	}

	// Overview
	$current_screen->add_help_tab( array(
		'id'      => 'overview',
		'title'   => __( 'Overview', 'bbpress' ),
		'content' => '<p>' . __( 'This screen provides access to all of the bbPress Import Forums settings and resources.',                                      'bbpress' ) . '</p>' .
					 '<p>' . __( 'Please see the additional help tabs for more information on each individual section.',                                         'bbpress' ) . '</p>' .
					 '<p>' . __( 'Also see the main article on the bbPress codex <a href="https://codex.bbpress.org/import-forums/">bbPress: Import Forums</a>.', 'bbpress' ) . '</p>'
	) );

	// Database Settings
	$current_screen->add_help_tab( array(
		'id'      => 'database_settings',
		'title'   => __( 'Database Settings', 'bbpress' ),
		'content' => '<p>' . __( 'In the Database Settings you have a number of options:', 'bbpress' ) . '</p>' .
					 '<p>' .
						'<ul>' .
							'<li>' . __( 'The settings in this section refer to the database connection strings used by your old forum software. The best way to determine the exact settings you need is to copy them from your legacy forums configuration file or contact your web hosting provider.', 'bbpress' ) . '</li>' .
						'</ul>' .
					'</p>'
	) );

	// Importer Options
	$current_screen->add_help_tab( array(
		'id'      => 'importer_options',
		'title'   => __( 'Importer Options', 'bbpress' ),
		'content' => '<p>' . __( 'In the Options you have a number of options:', 'bbpress' ) . '</p>' .
					 '<p>' .
						'<ul>' .
							'<li>' . __( 'Depending on your MySQL configuration you can tweak the "Rows Limit" and "Delay Time" that may help to improve the overall time it takes to perform a complete forum import.', 'bbpress' ) . '</li>' .
							'<li>' . __( '"Convert Users" will import your legacy forum members as WordPress Users.',                                                                                                    'bbpress' ) . '</li>' .
							'<li>' . __( '"Start Over" will start the importer fresh, if your import failed for any reason leaving this setting unchecked the importer will begin from where it left off.',              'bbpress' ) . '</li>' .
							'<li>' . __( '"Purge Previous Import" will remove data imported from a failed import without removing your existing forum data.',                                                            'bbpress' ) . '</li>' .
						'</ul>' .
					'</p>'
	) );
	// Help Sidebar
	$current_screen->set_help_sidebar(
		'<p><strong>' . __( 'For more information:', 'bbpress' ) . '</strong></p>' .
		'<p>' . __( '<a href="https://codex.bbpress.org" target="_blank">bbPress Documentation</a>',    'bbpress' ) . '</p>' .
		'<p>' . __( '<a href="https://bbpress.org/forums/" target="_blank">bbPress Support Forums</a>', 'bbpress' ) . '</p>'
	);
}

/**
 * Assemble the admin notices
 *
 * @since bbPress (r2613)
 *
 * @param string|WP_Error $message A message to be displayed or {@link WP_Error}
 * @param string $class Optional. A class to be added to the message div
 * @uses WP_Error::get_error_messages() To get the error messages of $message
 * @uses add_action() Adds the admin notice action with the message HTML
 * @return string The message HTML
 */
function bbp_admin_tools_feedback( $message, $class = false ) {

	// Dismiss button
	$dismiss = '<button type="button" class="notice-dismiss"><span class="screen-reader-text">' . __( 'Dismiss this notice.', 'bbpress' ) . '</span></button>';

	// One message as string
	if ( is_string( $message ) ) {
		$message = '<p>' . $message . '</p>';
		$class   = $class ? $class : 'updated';

	// Messages as objects
	} elseif ( is_wp_error( $message ) ) {
		$errors  = $message->get_error_messages();

		switch ( count( $errors ) ) {
			case 0:
				return false;

			case 1:
				$message = '<p>' . $errors[0] . '</p>';
				break;

			default:
				$message = '<ul>' . "\n\t" . '<li>' . implode( '</li>' . "\n\t" . '<li>', $errors ) . '</li>' . "\n" . '</ul>';
				break;
		}

		$class = $class ? $class : 'is-error';
	} else {
		return false;
	}

	// Assemble the message
	$message = '<div id="message" class="is-dismissible notice ' . esc_attr( $class ) . '">' . $message . $dismiss . '</div>';
	$message = str_replace( "'", "\'", $message );

	// Ugh
	$lambda  = create_function( '', "echo '$message';" );
	add_action( 'admin_notices', $lambda );

	return $lambda;
}

/**
 * Register an admin area repair tool
 *
 * @since bbPress (r5885)
 *
 * @param array $args
 * @return
 */
function bbp_register_repair_tool( $args = array() ) {

	// Parse arguments
	$r = bbp_parse_args( $args, array(
		'id'          => '',
		'description' => '',
		'callback'    => '',
		'priority'    => 0,
		'overhead'    => esc_html__( 'Low', 'bbpress' ),
		'components'  => array(),

		// @todo
		'success'     => esc_html__( 'The repair was completed successfully', 'bbpress' ),
		'failure'     => esc_html__( 'The repair was not successful',         'bbpress' )
	), 'register_repair_tool' );

	// Bail if missing required values
	if ( empty( $r['id'] ) || empty( $r['priority'] ) || empty( $r['description'] ) || empty( $r['callback'] ) ) {
		return;
	}

	// Add tool to the registered tools array
	bbpress()->admin->tools[ $r['id'] ] = array(
		'description' => $r['description'],
		'priority'    => $r['priority'],
		'callback'    => $r['callback'],
		'overhead'    => $r['overhead'],
		'components'  => $r['components'],

		// @todo
		'success'     => $r['success'],
		'failure'     => $r['failure'],
	);
}

/**
 * Register the default repair tools
 *
 * @since bbPress (r5885)
 */
function bbp_register_default_repair_tools() {

	// Topic meta
	bbp_register_repair_tool( array(
		'id'          => 'bbp-sync-topic-meta',
		'description' => __( 'Recalculate parent topic for each reply', 'bbpress' ),
		'callback'    => 'bbp_admin_repair_topic_meta',
		'priority'    => 0,
		'overhead'    => esc_html__( 'Low', 'bbpress' ),
		'components'  => array( bbp_get_reply_post_type() )
	) );

	// Forum meta
	bbp_register_repair_tool( array(
		'id'          => 'bbp-sync-forum-meta',
		'description' => __( 'Recalculate parent forum for each topic and reply', 'bbpress' ),
		'callback'    => 'bbp_admin_repair_forum_meta',
		'priority'    => 5,
		'overhead'    => esc_html__( 'Low', 'bbpress' ),
		'components'  => array( bbp_get_topic_post_type(), bbp_get_reply_post_type() )
	) );

	// Forum visibility
	bbp_register_repair_tool( array(
		'id'          => 'bbp-sync-forum-visibility',
		'description' => __( 'Recalculate private and hidden forums', 'bbpress' ),
		'callback'    => 'bbp_admin_repair_forum_visibility',
		'priority'    => 10,
		'overhead'    => esc_html__( 'Low', 'bbpress' ),
		'components'  => array( bbp_get_forum_post_type() )
	) );

	// Sync all topics in all forums
	bbp_register_repair_tool( array(
		'id'          => 'bbp-sync-all-topics-forums',
		'description' => __( 'Recalculate last activity in each topic and forum', 'bbpress' ),
		'callback'    => 'bbp_admin_repair_freshness',
		'priority'    => 15,
		'overhead'    => esc_html__( 'High', 'bbpress' ),
		'components'  => array( bbp_get_forum_post_type(), bbp_get_topic_post_type(), bbp_get_reply_post_type() )
	) );

	// Sync all sticky topics in all forums
	bbp_register_repair_tool( array(
		'id'          => 'bbp-sync-all-topics-sticky',
		'description' => __( 'Recalculate sticky relationship of each topic', 'bbpress' ),
		'callback'    => 'bbp_admin_repair_sticky',
		'priority'    => 20,
		'overhead'    => esc_html__( 'Low', 'bbpress' ),
		'components'  => array( bbp_get_topic_post_type() )
	) );

	// Sync all hierarchical reply positions
	bbp_register_repair_tool( array(
		'id'          => 'bbp-sync-all-reply-positions',
		'description' => __( 'Recalculate the position of each reply', 'bbpress' ),
		'callback'    => 'bbp_admin_repair_reply_menu_order',
		'priority'    => 25,
		'overhead'    => esc_html__( 'High', 'bbpress' ),
		'components'  => array( bbp_get_reply_post_type() )
	) );

	// Sync all BuddyPress group forum relationships
	bbp_register_repair_tool( array(
		'id'          => 'bbp-group-forums',
		'description' => __( 'Repair BuddyPress Group Forum relationships', 'bbpress' ),
		'callback'    => 'bbp_admin_repair_group_forum_relationship',
		'priority'    => 30,
		'overhead'    => esc_html__( 'Low', 'bbpress' ),
		'components'  => array( bbp_get_forum_post_type() )
	) );

	// Update closed topic counts
	bbp_register_repair_tool( array(
		'id'          => 'bbp-sync-closed-topics',
		'description' => __( 'Repair closed topics', 'bbpress' ),
		'callback'    => 'bbp_admin_repair_closed_topics',
		'priority'    => 35,
		'overhead'    => esc_html__( 'Medium', 'bbpress' ),
		'components'  => array( bbp_get_topic_post_type() )
	) );

	// Count topics
	bbp_register_repair_tool( array(
		'id'          => 'bbp-forum-topics',
		'description' => __( 'Count topics in each forum', 'bbpress' ),
		'callback'    => 'bbp_admin_repair_forum_topic_count',
		'priority'    => 40,
		'overhead'    => esc_html__( 'Medium', 'bbpress' ),
		'components'  => array( bbp_get_forum_post_type(), bbp_get_topic_post_type() )
	) );

	// Count forum replies
	bbp_register_repair_tool( array(
		'id'          => 'bbp-forum-replies',
		'description' => __( 'Count replies in each forum', 'bbpress' ),
		'callback'    => 'bbp_admin_repair_forum_reply_count',
		'priority'    => 45,
		'overhead'    => esc_html__( 'High', 'bbpress' ),
		'components'  => array( bbp_get_forum_post_type(), bbp_get_reply_post_type() )
	) );

	// Count topic replies
	bbp_register_repair_tool( array(
		'id'          => 'bbp-topic-replies',
		'description' => __( 'Count replies in each topic', 'bbpress' ),
		'callback'    => 'bbp_admin_repair_topic_reply_count',
		'priority'    => 50,
		'overhead'    => esc_html__( 'High', 'bbpress' ),
		'components'  => array( bbp_get_topic_post_type(), bbp_get_reply_post_type() )
	) );

	// Count topic voices
	bbp_register_repair_tool( array(
		'id'          => 'bbp-topic-voices',
		'description' => __( 'Count voices in each topic', 'bbpress' ),
		'callback'    => 'bbp_admin_repair_topic_voice_count',
		'priority'    => 55,
		'overhead'    => esc_html__( 'Medium', 'bbpress' ),
		'components'  => array( bbp_get_topic_post_type(), bbp_get_user_rewrite_id() )
	) );

	// Count non-published replies to each topic
	bbp_register_repair_tool( array(
		'id'          => 'bbp-topic-hidden-replies',
		'description' => __( 'Count pending, spammed, & trashed replies in each topic', 'bbpress' ),
		'callback'    => 'bbp_admin_repair_topic_hidden_reply_count',
		'priority'    => 60,
		'overhead'    => esc_html__( 'High', 'bbpress' ),
		'components'  => array( bbp_get_topic_post_type(), bbp_get_reply_post_type() )
	) );

	// Recount topics for each user
	bbp_register_repair_tool( array(
		'id'          => 'bbp-user-topics',
		'description' => __( 'Recount topics for each user', 'bbpress' ),
		'callback'    => 'bbp_admin_repair_user_topic_count',
		'priority'    => 65,
		'overhead'    => esc_html__( 'Medium', 'bbpress' ),
		'components'  => array( bbp_get_topic_post_type(), bbp_get_user_rewrite_id() )
	) );

	// Recount topics for each user
	bbp_register_repair_tool( array(
		'id'          => 'bbp-user-replies',
		'description' => __( 'Recount replies for each user', 'bbpress' ),
		'callback'    => 'bbp_admin_repair_user_reply_count',
		'priority'    => 70,
		'overhead'    => esc_html__( 'Medium', 'bbpress' ),
		'components'  => array( bbp_get_reply_post_type(), bbp_get_user_rewrite_id() )
	) );

	// Remove unpublished topics from user favorites
	bbp_register_repair_tool( array(
		'id'          => 'bbp-user-favorites',
		'description' => __( 'Remove unpublished topics from user favorites', 'bbpress' ),
		'callback'    => 'bbp_admin_repair_user_favorites',
		'priority'    => 75,
		'overhead'    => esc_html__( 'Medium', 'bbpress' ),
		'components'  => array( bbp_get_topic_post_type(), bbp_get_user_rewrite_id() )
	) );

	// Remove unpublished topics from user subscriptions
	bbp_register_repair_tool( array(
		'id'          => 'bbp-user-topic-subscriptions',
		'description' => __( 'Remove unpublished topics from user subscriptions', 'bbpress' ),
		'callback'    => 'bbp_admin_repair_user_topic_subscriptions',
		'priority'    => 80,
		'overhead'    => esc_html__( 'Medium', 'bbpress' ),
		'components'  => array( bbp_get_topic_post_type(), bbp_get_user_rewrite_id() )
	) );

	// Remove unpublished forums from user subscriptions
	bbp_register_repair_tool( array(
		'id'          => 'bbp-user-forum-subscriptions',
		'description' => __( 'Remove unpublished forums from user subscriptions', 'bbpress' ),
		'callback'    => 'bbp_admin_repair_user_forum_subscriptions',
		'priority'    => 85,
		'overhead'    => esc_html__( 'Medium', 'bbpress' ),
		'components'  => array( bbp_get_forum_post_type(), bbp_get_user_rewrite_id() )
	) );

	// Remove unpublished forums from user subscriptions
	bbp_register_repair_tool( array(
		'id'          => 'bbp-user-role-map',
		'description' => __( 'Remap existing users to default forum roles', 'bbpress' ),
		'callback'    => 'bbp_admin_repair_user_roles',
		'priority'    => 90,
		'overhead'    => esc_html__( 'Low', 'bbpress' ),
		'components'  => array( bbp_get_user_rewrite_id() )
	) );
}

/**
 * Get the array of available repair tools
 *
 * @since bbPress (r5885)
 *
 * @return array
 */
function bbp_get_admin_repair_tools() {

	// Get tools array
	$tools = ! empty( bbpress()->admin->tools )
		? bbpress()->admin->tools
		: array();

	return apply_filters( 'bbp_get_admin_repair_tools', $tools );
}

function bbp_get_admin_repair_tool_registered_components() {
	$tools   = bbp_get_admin_repair_tools();
	$plucked = wp_list_pluck( $tools, 'components' );
	$retval  = array();

	foreach ( $plucked as $components ) {
		foreach ( $components as $component ) {
			if ( in_array( $component, $retval ) ) {
				continue;
			}
			$retval[] = $component;
		}
	}

	return apply_filters( 'bbp_get_admin_repair_tool_registered_components', $retval );
}

/**
 * Output the repair list search form
 *
 * @since bbPress (r5885)
 */
function bbp_admin_repair_list_search_form() {
	?>

	<p class="search-box">
		<label class="screen-reader-text" for="bbp-repair-search-input"><?php esc_html_e( 'Search Tools:', 'bbpress' ); ?></label>
		<input type="search" id="bbp-repair-search-input" name="s" value="<?php _admin_search_query(); ?>">
		<input type="submit" id="search-submit" class="button" value="<?php esc_html_e( 'Search Tools', 'bbpress' ); ?>">
	</p>

	<?php
}

function bbp_admin_repair_list_components_filter() {

	$selected = ! empty( $_GET['components'] )
		? sanitize_key( $_GET['components'] )
		: '';

	$components = bbp_get_admin_repair_tool_registered_components(); ?>

	<label class="screen-reader-text" for="cat"><?php esc_html_e( 'Filter by Component', 'bbpress' ); ?></label>
	<select name="components" id="components" class="postform">
		<option value="" <?php selected( $selected, false ); ?>><?php esc_html_e( 'All Components', 'bbpress' ); ?></option>

		<?php foreach ( $components as $component ) : ?>

			<option class="level-0" value="<?php echo esc_attr( $component ); ?>" <?php selected( $selected, $component ); ?>><?php echo esc_html( bbp_admin_repair_tool_translate_component( $component ) ); ?></option>

		<?php endforeach; ?>

	</select>
	<input type="submit" name="filter_action" id="components-submit" class="button" value="<?php esc_html_e( 'Filter', 'bbpress' ); ?>">

	<?php
}

/**
 * Maybe translate a repair tool component name
 *
 * @since bbPress (r5885)
 *
 * @param string $component
 * @return string
 */
function bbp_admin_repair_tool_translate_component( $component = '' ) {

	// Get the name of the component
	switch ( $component ) {
		case 'bbp_user' :
			$name = esc_html__( 'Users', 'bbpress' );
			break;
		case bbp_get_forum_post_type() :
			$name = esc_html__( 'Forums', 'bbpress' );
			break;
		case bbp_get_topic_post_type() :
			$name = esc_html__( 'Topics', 'bbpress' );
			break;
		case bbp_get_reply_post_type() :
			$name = esc_html__( 'Replies', 'bbpress' );
			break;
		default;
			$name = ucwords( $component );
			break;
	}

	return $name;
}

/**
 * Get the array of the repair list
 *
 * @since bbPress (r2613)
 *
 * @uses apply_filters() Calls 'bbp_repair_list' with the list array
 * @return array Repair list of options
 */
function bbp_admin_repair_list() {

	// Define empty array
	$repair_list = array();

	// Get the available tools
	$list      = bbp_get_admin_repair_tools();
	$search    = ! empty( $_GET['s']          ) ? stripslashes( $_GET['s']          ) : '';
	$overhead  = ! empty( $_GET['overhead']   ) ? sanitize_key( $_GET['overhead']   ) : '';
	$component = ! empty( $_GET['components'] ) ? sanitize_key( $_GET['components'] ) : '';

	// Overhead filter
	if ( ! empty( $overhead ) ) {
		$list = wp_list_filter( $list, array( 'overhead' => ucwords( $overhead ) ) );
	}

	// Loop through and key by priority for sorting
	foreach ( $list as $id => $tool ) {

		// Component filter
		if ( ! empty( $component ) ) {
			if ( ! in_array( $component, $tool['components'] ) ) {
				continue;
			}
		}

		// Search
		if ( ! empty( $search ) ) {
			if ( ! strstr( strtolower( $tool['description'] ), strtolower( $search ) ) ) {
				continue;
			}
		}

		// Add to repair list
		$repair_list[ $tool['priority'] ] = array(
			'id'          => sanitize_key( $id ),
			'description' => $tool['description'],
			'callback'    => $tool['callback'],
			'overhead'    => $tool['overhead'],
			'components'  => $tool['components'],
		);
	}

	// Sort
	ksort( $repair_list );

	return (array) apply_filters( 'bbp_repair_list', $repair_list );
}

/**
 * Get filter links for components for a specific admir repair tool
 *
 * @since bbPress (r5885)
 *
 * @param array $item
 * @return array
 */
function bbp_get_admin_repair_tool_components( $item = array() ) {

	// Get the tools URL
	$tools_url = add_query_arg( array( 'page' => 'bbp-repair' ), admin_url( 'tools.php' ) );

	// Define links array
	$links = array();

	// Loop through tool components and build links
	foreach ( $item['components'] as $component ) {
		$args       = array( 'components' => $component );
		$filter_url = add_query_arg( $args, $tools_url );
		$name       = bbp_admin_repair_tool_translate_component( $component );
		$links[]    = '<a href="' . esc_url( $filter_url ) . '">' . esc_html( $name ) . '</a>';
	}

	// Filter & return
	return apply_filters( 'bbp_get_admin_repair_tool_components', $links, $item );
}
//
function bbp_admin_repair_tool_overhead_filters( $args = array() ) {
	echo bbp_get_admin_repair_tool_overhead_filters( $args );
}

/**
 * Get filter links for components for a specific admir repair tool
 *
 * @since bbPress (r5885)
 *
 * @param array $args
 * @return array
 */
function bbp_get_admin_repair_tool_overhead_filters( $args = array() ) {

	// Parse args
	$r = bbp_parse_args( $args, array(
		'before'            => '<ul class="subsubsub">',
		'after'             => '</ul>',
		'link_before'       => '<li>',
		'link_after'        => '</li>',
		'count_before'      => ' <span class="count">(',
		'count_after'       => ')</span>',
		'separator'         => ' | ',
	), 'get_admin_repair_tool_overhead_filters' );

	// Count the tools
	$tools = bbp_get_admin_repair_tools();

	// Get the tools URL
	$tools_url = add_query_arg( array( 'page' => 'bbp-repair' ), admin_url( 'tools.php' ) );

	// Define arrays
	$overheads = array();

	// Loop through tools and count overheads
	foreach ( $tools as $id => $tool ) {

		// Get the overhead level
		$overhead = $tool['overhead'];

		// Set an empty count
		if ( empty( $overheads[ $overhead ] ) ) {
			$overheads[ $overhead ] = 0;
		}

		// Bump the overhead count
		$overheads[ $overhead ]++;
	}

	// Create the "All" link
	$current = empty( $_GET['overhead'] ) ? 'current' : '';
	$output  = $r['link_before']. '<a href="' . esc_url( $tools_url ) . '" class="' . esc_attr( $current ) . '">' . sprintf( esc_html__( 'All %s', 'bbpress' ), $r['count_before'] . count( $tools ) . $r['count_after'] ) . '</a>' . $r['separator'] . $r['link_after'];

	// Default ticker
	$i = 0;

	// Loop through overheads and build filter
	foreach ( $overheads as $overhead => $count ) {

		// Separator count
		$i++;

		// Build the filter URL
		$key        = sanitize_key( $overhead );
		$args       = array( 'overhead' => $key );
		$filter_url = add_query_arg( $args, $tools_url );

		// Figure out separator and active class
		$show_sep = count( $overheads ) > $i ? $r['separator'] : '';
		$current  = ! empty( $_GET['overhead'] ) && ( sanitize_key( $_GET['overhead'] ) === $key ) ? 'current' : '';

		// Counts to show
		if ( ! empty( $count ) ) {
			$overhead_count = $r['count_before'] . $count . $r['count_after'];
		}

		// Build the link
		$output .= $r['link_before'] . '<a href="' . esc_url( $filter_url ) . '" class="' . esc_attr( $current ) . '">' . $overhead . $overhead_count . '</a>' . $show_sep . $r['link_after'];
	}

	// Surround output with before & after strings
	$output = $r['before'] . $output . $r['after'];

	// Filter & return
	return apply_filters( 'bbp_get_admin_repair_tool_components', $output, $r, $args );
}

/**
 * Recount topic replies
 *
 * @since bbPress (r2613)
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
				GROUP BY `topics`.`ID`);";

	if ( is_wp_error( $bbp_db->query( $sql ) ) ) {
		return array( 2, sprintf( $statement, $result ) );
	}

	return array( 0, sprintf( $statement, __( 'Complete!', 'bbpress' ) ) );
}

/**
 * Recount topic voices
 *
 * @since bbPress (r2613)
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

	$sql_delete = "DELETE FROM `{$bbp_db->postmeta}` WHERE `meta_key` = '_bbp_voice_count';";
	if ( is_wp_error( $bbp_db->query( $sql_delete ) ) ) {
		return array( 1, sprintf( $statement, $result ) );
	}

	// Post types and status
	$tpt = bbp_get_topic_post_type();
	$rpt = bbp_get_reply_post_type();
	$pps = bbp_get_public_status_id();
	$cps = bbp_get_closed_status_id();

	$sql = "INSERT INTO `{$bbp_db->postmeta}` (`post_id`, `meta_key`, `meta_value`) (
			SELECT `postmeta`.`meta_value`, '_bbp_voice_count', COUNT(DISTINCT `post_author`) as `meta_value`
				FROM `{$bbp_db->posts}` AS `posts`
				LEFT JOIN `{$bbp_db->postmeta}` AS `postmeta`
					ON `posts`.`ID` = `postmeta`.`post_id`
					AND `postmeta`.`meta_key` = '_bbp_topic_id'
				WHERE `posts`.`post_type` IN ( '{$tpt}', '{$rpt}' )
					AND `posts`.`post_status` IN ( '{$pps}', '{$cps}' )
					AND `posts`.`post_author` != '0'
				GROUP BY `postmeta`.`meta_value`);";

	if ( is_wp_error( $bbp_db->query( $sql ) ) ) {
		return array( 2, sprintf( $statement, $result ) );
	}

	return array( 0, sprintf( $statement, __( 'Complete!', 'bbpress' ) ) );
}

/**
 * Recount topic hidden replies (spammed/trashed)
 *
 * @since bbPress (r2747)
 *
 * @uses wpdb::query() To run our recount sql queries
 * @uses is_wp_error() To check if the executed query returned {@link WP_Error}
 * @uses bbp_get_reply_post_type() To get the reply post type
 * @uses bbp_get_trash_status_id() To get the trash status id
 * @uses bbp_get_spam_status_id() To get the spam status id
 * @return array An array of the status code and the message
 */
function bbp_admin_repair_topic_hidden_reply_count() {

	// Define variables
	$bbp_db    = bbp_db();
	$statement = __( 'Counting the number of spammed and trashed replies in each topic&hellip; %s', 'bbpress' );
	$result    = __( 'Failed!', 'bbpress' );

	$sql_delete = "DELETE FROM `{$bbp_db->postmeta}` WHERE `meta_key` = '_bbp_reply_count_hidden';";
	if ( is_wp_error( $bbp_db->query( $sql_delete ) ) ) {
		return array( 1, sprintf( $statement, $result ) );
	}

	// Post types and status
	$rpt = bbp_get_reply_post_type();
	$tps = bbp_get_trash_status_id();
	$sps = bbp_get_spam_status_id();

	$sql = "INSERT INTO `{$bbp_db->postmeta}` (`post_id`, `meta_key`, `meta_value`) (SELECT `post_parent`, '_bbp_reply_count_hidden', COUNT(`post_status`) as `meta_value` FROM `{$bbp_db->posts}` WHERE `post_type` = '{$rpt}' AND `post_status` IN ( '{$tps}', '{$sps}' ) GROUP BY `post_parent`);";
	if ( is_wp_error( $bbp_db->query( $sql ) ) ) {
		return array( 2, sprintf( $statement, $result ) );
	}

	return array( 0, sprintf( $statement, __( 'Complete!', 'bbpress' ) ) );
}

/**
 * Repair group forum ID mappings after a bbPress 1.1 to bbPress 2.2 conversion
 *
 * @since bbPress (r4395)
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
								GROUP BY `forum`.`ID`;" );

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
		$updated = $bbp_db->query( "UPDATE `{$groups_meta_table}` SET `meta_value` = '{$group_forums->ID}' WHERE `meta_key` = 'forum_id' AND `meta_value` = '{$group_forums->meta_value}';" );

		// Bump the count
		if ( ! empty( $updated ) && ! is_wp_error( $updated ) ) {
			++$g_count;
		}

		// Update group to forum relationship data
		$group_id = (int) $bbp_db->get_var( "SELECT `group_id` FROM `{$groups_meta_table}` WHERE `meta_key` = 'forum_id' AND `meta_value` = '{$group_forums->ID}';" );
		if ( ! empty( $group_id ) ) {

			// Update the group to forum meta connection in forums
			update_post_meta( $group_forums->ID, '_bbp_group_ids', array( $group_id ) );

			// Get the group status
			$group_status = $bbp_db->get_var( "SELECT `status` FROM `{$groups_table}` WHERE `id` = '{$group_id}';" );

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
 * @since bbPress (r2613)
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

	$sql_delete = "DELETE FROM {$bbp_db->postmeta} WHERE meta_key IN ( '_bbp_topic_count', '_bbp_total_topic_count', '_bbp_topic_count_hidden' );";
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
 * Recount forum replies
 *
 * @since bbPress (r2613)
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
 * @since bbPress (r3889)
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

	$sql_select  = "SELECT `post_author`, COUNT(DISTINCT `ID`) as `_count` FROM `{$bbp_db->posts}` WHERE `post_type` = '" . bbp_get_topic_post_type() . "' AND `post_status` = '" . bbp_get_public_status_id() . "' GROUP BY `post_author`;";
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

	$sql_delete = "DELETE FROM `{$bbp_db->usermeta}` WHERE `meta_key` = '{$key}';";
	if ( is_wp_error( $bbp_db->query( $sql_delete ) ) ) {
		return array( 3, sprintf( $statement, $result ) );
	}

	foreach ( array_chunk( $insert_values, 10000 ) as $chunk ) {
		$chunk = "\n" . implode( ",\n", $chunk );
		$sql_insert = "INSERT INTO `{$bbp_db->usermeta}` (`user_id`, `meta_key`, `meta_value`) VALUES {$chunk};";

		if ( is_wp_error( $bbp_db->query( $sql_insert ) ) ) {
			return array( 4, sprintf( $statement, $result ) );
		}
	}

	return array( 0, sprintf( $statement, __( 'Complete!', 'bbpress' ) ) );
}

/**
 * Recount topic replied by the users
 *
 * @since bbPress (r2613)
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

	$sql_select  = "SELECT `post_author`, COUNT(DISTINCT `ID`) as `_count` FROM `{$bbp_db->posts}` WHERE `post_type` = '" . bbp_get_reply_post_type() . "' AND `post_status` = '" . bbp_get_public_status_id() . "' GROUP BY `post_author`;";
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

	$sql_delete = "DELETE FROM `{$bbp_db->usermeta}` WHERE `meta_key` = '{$key}';";
	if ( is_wp_error( $bbp_db->query( $sql_delete ) ) ) {
		return array( 3, sprintf( $statement, $result ) );
	}

	foreach ( array_chunk( $insert_values, 10000 ) as $chunk ) {
		$chunk = "\n" . implode( ",\n", $chunk );
		$sql_insert = "INSERT INTO `{$bbp_db->usermeta}` (`user_id`, `meta_key`, `meta_value`) VALUES {$chunk};";

		if ( is_wp_error( $bbp_db->query( $sql_insert ) ) ) {
			return array( 4, sprintf( $statement, $result ) );
		}
	}

	return array( 0, sprintf( $statement, __( 'Complete!', 'bbpress' ) ) );
}

/**
 * Clean the users' favorites
 *
 * @since bbPress (r2613)
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
	$statement = __( 'Removing trashed topics from user favorites&hellip; %s', 'bbpress' );
	$result    = __( 'Failed!', 'bbpress' );

	$key       = $bbp_db->prefix . '_bbp_favorites';
	$users     = $bbp_db->get_results( "SELECT `user_id`, `meta_value` AS `favorites` FROM `{$bbp_db->usermeta}` WHERE `meta_key` = '{$key}';" );

	if ( is_wp_error( $users ) ) {
		return array( 1, sprintf( $statement, $result ) );
	}

	$topics = $bbp_db->get_col( "SELECT `ID` FROM `{$bbp_db->posts}` WHERE `post_type` = '" . bbp_get_topic_post_type() . "' AND `post_status` = '" . bbp_get_public_status_id() . "';" );

	if ( is_wp_error( $topics ) ) {
		return array( 2, sprintf( $statement, $result ) );
	}

	$values = array();
	foreach ( $users as $user ) {
		if ( empty( $user->favorites ) || !is_string( $user->favorites ) ) {
			continue;
		}

		$favorites = array_intersect( $topics, explode( ',', $user->favorites ) );
		if ( empty( $favorites ) || !is_array( $favorites ) ) {
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

	$sql_delete = "DELETE FROM `{$bbp_db->usermeta}` WHERE `meta_key` = '{$key}';";
	if ( is_wp_error( $bbp_db->query( $sql_delete ) ) ) {
		return array( 4, sprintf( $statement, $result ) );
	}

	foreach ( array_chunk( $values, 10000 ) as $chunk ) {
		$chunk = "\n" . implode( ",\n", $chunk );
		$sql_insert = "INSERT INTO `{$bbp_db->usermeta}` (`user_id`, `meta_key`, `meta_value`) VALUES {$chunk};";
		if ( is_wp_error( $bbp_db->query( $sql_insert ) ) ) {
			return array( 5, sprintf( $statement, $result ) );
		}
	}

	return array( 0, sprintf( $statement, __( 'Complete!', 'bbpress' ) ) );
}

/**
 * Clean the users' topic subscriptions
 *
 * @since bbPress (r2668)
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
	$users     = $bbp_db->get_results( "SELECT `user_id`, `meta_value` AS `subscriptions` FROM `{$bbp_db->usermeta}` WHERE `meta_key` = '{$key}';" );

	if ( is_wp_error( $users ) ) {
		return array( 1, sprintf( $statement, $result ) );
	}

	$topics = $bbp_db->get_col( "SELECT `ID` FROM `{$bbp_db->posts}` WHERE `post_type` = '" . bbp_get_topic_post_type() . "' AND `post_status` = '" . bbp_get_public_status_id() . "';" );
	if ( is_wp_error( $topics ) ) {
		return array( 2, sprintf( $statement, $result ) );
	}

	$values = array();
	foreach ( $users as $user ) {
		if ( empty( $user->subscriptions ) || !is_string( $user->subscriptions ) ) {
			continue;
		}

		$subscriptions = array_intersect( $topics, explode( ',', $user->subscriptions ) );
		if ( empty( $subscriptions ) || !is_array( $subscriptions ) ) {
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

	$sql_delete = "DELETE FROM `{$bbp_db->usermeta}` WHERE `meta_key` = '{$key}';";
	if ( is_wp_error( $bbp_db->query( $sql_delete ) ) ) {
		return array( 4, sprintf( $statement, $result ) );
	}

	foreach ( array_chunk( $values, 10000 ) as $chunk ) {
		$chunk = "\n" . implode( ",\n", $chunk );
		$sql_insert = "INSERT INTO `{$bbp_db->usermeta}` (`user_id`, `meta_key`, `meta_value`) VALUES {$chunk};";
		if ( is_wp_error( $bbp_db->query( $sql_insert ) ) ) {
			return array( 5, sprintf( $statement, $result ) );
		}
	}

	return array( 0, sprintf( $statement, __( 'Complete!', 'bbpress' ) ) );
}

/**
 * Clean the users' forum subscriptions
 *
 * @since bbPress (r5155)
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
	$users     = $bbp_db->get_results( "SELECT `user_id`, `meta_value` AS `subscriptions` FROM `{$bbp_db->usermeta}` WHERE `meta_key` = '{$key}';" );

	if ( is_wp_error( $users ) ) {
		return array( 1, sprintf( $statement, $result ) );
	}

	$forums = $bbp_db->get_col( "SELECT `ID` FROM `{$bbp_db->posts}` WHERE `post_type` = '" . bbp_get_forum_post_type() . "' AND `post_status` = '" . bbp_get_public_status_id() . "';" );
	if ( is_wp_error( $forums ) ) {
		return array( 2, sprintf( $statement, $result ) );
	}

	$values = array();
	foreach ( $users as $user ) {
		if ( empty( $user->subscriptions ) || !is_string( $user->subscriptions ) ) {
			continue;
		}

		$subscriptions = array_intersect( $forums, explode( ',', $user->subscriptions ) );
		if ( empty( $subscriptions ) || !is_array( $subscriptions ) ) {
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

	$sql_delete = "DELETE FROM `{$bbp_db->usermeta}` WHERE `meta_key` = '{$key}';";
	if ( is_wp_error( $bbp_db->query( $sql_delete ) ) ) {
		return array( 4, sprintf( $statement, $result ) );
	}

	foreach ( array_chunk( $values, 10000 ) as $chunk ) {
		$chunk = "\n" . implode( ",\n", $chunk );
		$sql_insert = "INSERT INTO `{$bbp_db->usermeta}` (`user_id`, `meta_key`, `meta_value`) VALUES {$chunk};";
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
 * @since bbPress (r4340)
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
 * @since bbPress (r3040)
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
	if ( is_wp_error( $bbp_db->query( "DELETE FROM `{$bbp_db->postmeta}` WHERE `meta_key` IN ( '_bbp_last_reply_id', '_bbp_last_topic_id', '_bbp_last_active_id', '_bbp_last_active_time' );" ) ) ) {
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
			GROUP BY `topic`.`ID` );" ) ) ) {
		return array( 2, sprintf( $statement, $result ) );
	}

	// For any remaining topics, give a reply ID of 0.
	if ( is_wp_error( $bbp_db->query( "INSERT INTO `{$bbp_db->postmeta}` (`post_id`, `meta_key`, `meta_value`)
			( SELECT `ID`, '_bbp_last_reply_id', 0
			FROM `{$bbp_db->posts}` AS `topic` LEFT JOIN `{$bbp_db->postmeta}` AS `reply`
			ON `topic`.`ID` = `reply`.`post_id` AND `reply`.`meta_key` = '_bbp_last_reply_id'
			WHERE `reply`.`meta_id` IS NULL AND `topic`.`post_type` = '{$tpt}' );" ) ) ) {
		return array( 3, sprintf( $statement, $result ) );
	}

	// Now we give all the forums with topics the ID their last topic.
	if ( is_wp_error( $bbp_db->query( "INSERT INTO `{$bbp_db->postmeta}` (`post_id`, `meta_key`, `meta_value`)
			( SELECT `forum`.`ID`, '_bbp_last_topic_id', `topic`.`ID`
			FROM `{$bbp_db->posts}` AS `forum` INNER JOIN `{$bbp_db->posts}` AS `topic` ON `forum`.`ID` = `topic`.`post_parent`
			WHERE `topic`.`post_status` = '{$pps}' AND `forum`.`post_type` = '{$fpt}' AND `topic`.`post_type` = '{$tpt}'
			GROUP BY `forum`.`ID` );" ) ) ) {
		return array( 4, sprintf( $statement, $result ) );
	}

	// For any remaining forums, give a topic ID of 0.
	if ( is_wp_error( $bbp_db->query( "INSERT INTO `{$bbp_db->postmeta}` (`post_id`, `meta_key`, `meta_value`)
			( SELECT `ID`, '_bbp_last_topic_id', 0
			FROM `{$bbp_db->posts}` AS `forum` LEFT JOIN `{$bbp_db->postmeta}` AS `topic`
			ON `forum`.`ID` = `topic`.`post_id` AND `topic`.`meta_key` = '_bbp_last_topic_id'
			WHERE `topic`.`meta_id` IS NULL AND `forum`.`post_type` = '{$fpt}' );" ) ) ) {
		return array( 5, sprintf( $statement, $result ) );
	}

	// After that, we give all the topics with replies the ID their last reply (again, this time for a different reason).
	if ( is_wp_error( $bbp_db->query( "INSERT INTO `{$bbp_db->postmeta}` (`post_id`, `meta_key`, `meta_value`)
			( SELECT `topic`.`ID`, '_bbp_last_active_id', MAX( `reply`.`ID` )
			FROM `{$bbp_db->posts}` AS `topic` INNER JOIN `{$bbp_db->posts}` AS `reply` ON `topic`.`ID` = `reply`.`post_parent`
			WHERE `reply`.`post_status` = '{$pps}' AND `topic`.`post_type` = '{$tpt}' AND `reply`.`post_type` = '{$rpt}'
			GROUP BY `topic`.`ID` );" ) ) ) {
		return array( 6, sprintf( $statement, $result ) );
	}

	// For any remaining topics, give a reply ID of themself.
	if ( is_wp_error( $bbp_db->query( "INSERT INTO `{$bbp_db->postmeta}` (`post_id`, `meta_key`, `meta_value`)
			( SELECT `ID`, '_bbp_last_active_id', `ID`
			FROM `{$bbp_db->posts}` AS `topic` LEFT JOIN `{$bbp_db->postmeta}` AS `reply`
			ON `topic`.`ID` = `reply`.`post_id` AND `reply`.`meta_key` = '_bbp_last_active_id'
			WHERE `reply`.`meta_id` IS NULL AND `topic`.`post_type` = '{$tpt}' );" ) ) ) {
		return array( 7, sprintf( $statement, $result ) );
	}

	// Give topics with replies their last update time.
	if ( is_wp_error( $bbp_db->query( "INSERT INTO `{$bbp_db->postmeta}` (`post_id`, `meta_key`, `meta_value`)
			( SELECT `topic`.`ID`, '_bbp_last_active_time', MAX( `reply`.`post_date` )
			FROM `{$bbp_db->posts}` AS `topic` INNER JOIN `{$bbp_db->posts}` AS `reply` ON `topic`.`ID` = `reply`.`post_parent`
			WHERE `reply`.`post_status` = '{$pps}' AND `topic`.`post_type` = '{$tpt}' AND `reply`.`post_type` = '{$rpt}'
			GROUP BY `topic`.`ID` );" ) ) ) {
		return array( 8, sprintf( $statement, $result ) );
	}

	// Give topics without replies their last update time.
	if ( is_wp_error( $bbp_db->query( "INSERT INTO `{$bbp_db->postmeta}` (`post_id`, `meta_key`, `meta_value`)
			( SELECT `ID`, '_bbp_last_active_time', `post_date`
			FROM `{$bbp_db->posts}` AS `topic` LEFT JOIN `{$bbp_db->postmeta}` AS `reply`
			ON `topic`.`ID` = `reply`.`post_id` AND `reply`.`meta_key` = '_bbp_last_active_time'
			WHERE `reply`.`meta_id` IS NULL AND `topic`.`post_type` = '{$tpt}' );" ) ) ) {
		return array( 9, sprintf( $statement, $result ) );
	}

	// Forums need to know what their last active item is as well. Now it gets a bit more complex to do in the database.
	$forums = $bbp_db->get_col( "SELECT `ID` FROM `{$bbp_db->posts}` WHERE `post_type` = '{$fpt}' and `post_status` != 'auto-draft';" );
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
 * @since bbPress (r4695)
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

	$forums    = $bbp_db->get_col( "SELECT ID FROM `{$bbp_db->posts}` WHERE `post_type` = '" . bbp_get_forum_post_type() . "';" );

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
		$stickers = empty( $forum_stickies[$forum_id] ) ? '' : array_values( $forum_stickies[ $forum_id ] );

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
 * @since bbPress (r5668)
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

	$closed_topics = $bbp_db->get_col( "SELECT ID FROM `{$bbp_db->posts}` WHERE `post_type` = '" . bbp_get_topic_post_type() . "' AND `post_status` = 'closed';" );

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
 * @since bbPress (r4104)
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
 * @since bbPress (r3876)
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
	if ( is_wp_error( $bbp_db->query( "DELETE FROM `{$bbp_db->postmeta}` WHERE `meta_key` = '_bbp_forum_id';" ) ) ) {
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
			GROUP BY `topic`.`ID` );" ) ) ) {
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
			GROUP BY `reply`.`ID` );" ) ) ) {
		return array( 3, sprintf( $statement, $result ) );
	}

	// Complete results
	return array( 0, sprintf( $statement, __( 'Complete!', 'bbpress' ) ) );
}

/**
 * Recaches the topic for each post
 *
 * @since bbPress (r3876)
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
	if ( is_wp_error( $bbp_db->query( "DELETE FROM `{$bbp_db->postmeta}` WHERE `meta_key` = '_bbp_topic_id';" ) ) ) {
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
			GROUP BY `topic`.`ID` );" ) ) ) {
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
			GROUP BY `reply`.`ID` );" ) ) ) {
		return array( 4, sprintf( $statement, $result ) );
	}

	// Complete results
	return array( 0, sprintf( $statement, __( 'Complete!', 'bbpress' ) ) );
}

/**
 * Recalculate reply menu order
 *
 * @since bbPress (r5367)
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
	$result    = __( 'No reply positions to recalculate!',         'bbpress' );

	// Delete cases where `_bbp_reply_to` was accidentally set to itself
	if ( is_wp_error( $bbp_db->query( "DELETE FROM `{$bbp_db->postmeta}` WHERE `meta_key` = '_bbp_reply_to' AND `post_id` = `meta_value`;" ) ) ) {
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
										WHERE `post_type` = '{$rpt}';", OBJECT_K );

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

/** Reset ********************************************************************/

/**
 * Admin reset page
 *
 * @since bbPress (r2613)
 *
 * @uses check_admin_referer() To verify the nonce and the referer
 * @uses do_action() Calls 'admin_notices' to display the notices
 * @uses wp_nonce_field() To add a hidden nonce field
 */
function bbp_admin_reset() {
?>

	<div class="wrap">
		<h1><?php esc_html_e( 'Forum Tools', 'bbpress' ); ?></h1>
		<h2 class="nav-tab-wrapper"><?php bbp_tools_admin_tabs( __( 'Reset Forums', 'bbpress' ) ); ?></h2>
		<p><?php esc_html_e( 'Revert your forums back to a brand new installation. This process cannot be undone.', 'bbpress' ); ?></p>
		<p><strong><?php esc_html_e( 'Backup your database before proceeding.', 'bbpress' ); ?></strong></p>

		<form class="settings" method="post" action="">
			<table class="form-table">
				<tbody>
					<tr valign="top">
						<th scope="row"><?php esc_html_e( 'The following data will be removed:', 'bbpress' ) ?></th>
						<td>
							<?php esc_html_e( 'All Forums',           'bbpress' ); ?><br />
							<?php esc_html_e( 'All Topics',           'bbpress' ); ?><br />
							<?php esc_html_e( 'All Replies',          'bbpress' ); ?><br />
							<?php esc_html_e( 'All Topic Tags',       'bbpress' ); ?><br />
							<?php esc_html_e( 'Related Meta Data',    'bbpress' ); ?><br />
							<?php esc_html_e( 'Forum Settings',       'bbpress' ); ?><br />
							<?php esc_html_e( 'Forum Activity',       'bbpress' ); ?><br />
							<?php esc_html_e( 'Forum User Roles',     'bbpress' ); ?><br />
							<?php esc_html_e( 'Importer Helper Data', 'bbpress' ); ?><br />
						</td>
					</tr>
					<tr valign="top">
						<th scope="row"><?php esc_html_e( 'Delete imported users?', 'bbpress' ); ?></th>
						<td>
							<fieldset>
								<legend class="screen-reader-text"><span><?php esc_html_e( "Say it ain't so!", 'bbpress' ); ?></span></legend>
								<label><input type="checkbox" class="checkbox" name="bbpress-delete-imported-users" id="bbpress-delete-imported-users" value="1" /> <?php esc_html_e( 'This option will delete all previously imported users, and cannot be undone.', 'bbpress' ); ?></label>
								<p class="description"><?php esc_html_e( 'Note: Resetting without this checked will delete the meta-data necessary to delete these users.', 'bbpress' ); ?></p>
							</fieldset>
						</td>
					</tr>
					<tr valign="top">
						<th scope="row"><?php esc_html_e( 'Do you really want to do this?', 'bbpress' ); ?></th>
						<td>
							<fieldset>
								<legend class="screen-reader-text"><span><?php esc_html_e( "Say it ain't so!", 'bbpress' ); ?></span></legend>
								<label><input type="checkbox" class="checkbox" name="bbpress-are-you-sure" id="bbpress-are-you-sure" value="1" /> <?php esc_html_e( 'This process cannot be undone.', 'bbpress' ); ?></label>
							</fieldset>
						</td>
					</tr>
				</tbody>
			</table>

			<fieldset class="submit">
				<input class="button-primary" type="submit" name="submit" value="<?php esc_attr_e( 'Reset bbPress', 'bbpress' ); ?>" />
				<?php wp_nonce_field( 'bbpress-reset' ); ?>
			</fieldset>
		</form>
	</div>

<?php
}

/**
 * Handle the processing and feedback of the admin tools page
 *
 * @since bbPress (r2613)
 *
 * @uses check_admin_referer() To verify the nonce and the referer
 * @uses wp_cache_flush() To flush the cache
 * @uses bbp_get_forum_post_type() To get the forum post type
 * @uses bbp_get_topic_post_type() To get the topic post type
 * @uses bbp_get_reply_post_type() To get the reply post type
 */
function bbp_admin_reset_handler() {

	// Bail if not resetting
	if ( ! bbp_is_post_request() || empty( $_POST['bbpress-are-you-sure'] ) ) {
		return;
	}

	// Only keymasters can proceed
	if ( ! bbp_is_user_keymaster() ) {
		return;
	}

	check_admin_referer( 'bbpress-reset' );

	// Stores messages
	$messages = array();
	$failed   = __( 'Failed',   'bbpress' );
	$success  = __( 'Success!', 'bbpress' );

	// Flush the cache; things are about to get ugly.
	wp_cache_flush();

	/** Posts *****************************************************************/

	// Post types and status
	$fpt = bbp_get_forum_post_type();
	$tpt = bbp_get_topic_post_type();
	$rpt = bbp_get_reply_post_type();

	// Define variables
	$bbp_db    = bbp_db();
	$statement  = __( 'Deleting Posts&hellip; %s', 'bbpress' );

	$sql_posts  = $bbp_db->get_results( "SELECT `ID` FROM `{$bbp_db->posts}` WHERE `post_type` IN ('{$fpt}', '{$tpt}', '{$rpt}')", OBJECT_K );
	$sql_delete = "DELETE FROM `{$bbp_db->posts}` WHERE `post_type` IN ('{$fpt}', '{$tpt}', '{$rpt}')";
	$result     = is_wp_error( $bbp_db->query( $sql_delete ) ) ? $failed : $success;
	$messages[] = sprintf( $statement, $result );

	/** Post Meta *************************************************************/

	if ( ! empty( $sql_posts ) ) {
		$sql_meta = array();
		foreach ( $sql_posts as $key => $value ) {
			$sql_meta[] = $key;
		}
		$statement  = __( 'Deleting Post Meta&hellip; %s', 'bbpress' );
		$sql_meta   = implode( "', '", $sql_meta );
		$sql_delete = "DELETE FROM `{$bbp_db->postmeta}` WHERE `post_id` IN ('{$sql_meta}');";
		$result     = is_wp_error( $bbp_db->query( $sql_delete ) ) ? $failed : $success;
		$messages[] = sprintf( $statement, $result );
	}

	/** Topic Tags ************************************************************/

	$statement  = __( 'Deleting Topic Tags&hellip; %s', 'bbpress' );
	$sql_delete = "DELETE a,b,c FROM `{$bbp_db->terms}` AS a LEFT JOIN `{$bbp_db->term_taxonomy}` AS c ON a.term_id = c.term_id LEFT JOIN `{$bbp_db->term_relationships}` AS b ON b.term_taxonomy_id = c.term_taxonomy_id WHERE c.taxonomy = 'topic-tag';";
	$result     = is_wp_error( $bbp_db->query( $sql_delete ) ) ? $failed : $success;
	$messages[] = sprintf( $statement, $result );

	/** User ******************************************************************/

	// First, if we're deleting previously imported users, delete them now
	if ( ! empty( $_POST['bbpress-delete-imported-users'] ) ) {
		$sql_users  = $bbp_db->get_results( "SELECT `user_id` FROM `{$bbp_db->usermeta}` WHERE `meta_key` = '_bbp_user_id'", OBJECT_K );
		if ( ! empty( $sql_users ) ) {
			$sql_meta = array();
			foreach ( $sql_users as $key => $value ) {
				$sql_meta[] = $key;
			}
			$statement  = __( 'Deleting User&hellip; %s', 'bbpress' );
			$sql_meta   = implode( "', '", $sql_meta );
			$sql_delete = "DELETE FROM `{$bbp_db->users}` WHERE `ID` IN ('{$sql_meta}');";
			$result     = is_wp_error( $bbp_db->query( $sql_delete ) ) ? $failed : $success;
			$messages[] = sprintf( $statement, $result );
			$statement  = __( 'Deleting User Meta&hellip; %s', 'bbpress' );
			$sql_delete = "DELETE FROM `{$bbp_db->usermeta}` WHERE `user_id` IN ('{$sql_meta}');";
			$result     = is_wp_error( $bbp_db->query( $sql_delete ) ) ? $failed : $success;
			$messages[] = sprintf( $statement, $result );
		}
	}

	// Next, if we still have users that were not imported delete that meta data
	$statement  = __( 'Deleting User Meta&hellip; %s', 'bbpress' );
	$sql_delete = "DELETE FROM `{$bbp_db->usermeta}` WHERE `meta_key` LIKE '%%_bbp_%%';";
	$result     = is_wp_error( $bbp_db->query( $sql_delete ) ) ? $failed : $success;
	$messages[] = sprintf( $statement, $result );

	/** Converter *************************************************************/

	$statement  = __( 'Deleting Conversion Table&hellip; %s', 'bbpress' );
	$table_name = $bbp_db->prefix . 'bbp_converter_translator';
	if ( $bbp_db->get_var( "SHOW TABLES LIKE '{$table_name}'" ) === $table_name ) {
		$bbp_db->query( "DROP TABLE {$table_name}" );
		$result = $success;
	} else {
		$result = $failed;
	}
	$messages[] = sprintf( $statement, $result );

	/** Options ***************************************************************/

	$statement  = __( 'Deleting Settings&hellip; %s', 'bbpress' );
	bbp_delete_options();
	$messages[] = sprintf( $statement, $success );

	/** Roles *****************************************************************/

	$statement  = __( 'Deleting Roles and Capabilities&hellip; %s', 'bbpress' );
	bbp_remove_roles();
	bbp_remove_caps();
	$messages[] = sprintf( $statement, $success );

	/** Output ****************************************************************/

	if ( count( $messages ) ) {
		foreach ( $messages as $message ) {
			bbp_admin_tools_feedback( $message );
		}
	}
}
