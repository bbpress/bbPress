<?php

/**
 * bbPress Admin Tools Help
 *
 * @package bbPress
 * @subpackage Administration
 */

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

/**
 * Contextual help for Repair Forums tools page
 *
 * @since 2.6.0 bbPress (r5314)
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
 * @since 2.6.0 bbPress (r5314)
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
 * @since 2.6.0 bbPress (r5314)
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
