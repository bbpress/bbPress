<?php

/**
 * bbPress Admin Actions
 *
 * This file contains the actions that are used through-out bbPress Admin. They
 * are consolidated here to make searching for them easier, and to help developers
 * understand at a glance the order in which things occur.
 *
 * There are a few common places that additional actions can currently be found
 *
 *  - bbPress: In {@link bbPress::setup_actions()} in bbpress.php
 *  - Admin: More in {@link BBP_Admin::setup_actions()} in class-bbp-admin.php
 *
 * @package bbPress
 * @subpackage Administration
 *
 * @see bbp-core-actions.php
 * @see bbp-core-filters.php
 */

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

/**
 * Attach bbPress to WordPress
 *
 * bbPress uses its own internal actions to help aid in third-party plugin
 * development, and to limit the amount of potential future code changes when
 * updates to WordPress core occur.
 *
 * These actions exist to create the concept of 'plugin dependencies'. They
 * provide a safe way for plugins to execute code *only* when bbPress is
 * installed and activated, without needing to do complicated guesswork.
 *
 * For more information on how this works, see the 'Plugin Dependency' section
 * near the bottom of this file.
 *
 *           v--WordPress Actions       v--bbPress Sub-actions
 */
add_action( 'wpmu_new_blog',           'bbp_new_site',               10, 6 );
add_action( 'current_screen',          'bbp_current_screen'                );
add_action( 'tool_box',                'bbp_admin_tool_box'                );
add_action( 'admin_menu',              'bbp_admin_menu'                    );
add_action( 'admin_init',              'bbp_admin_init'                    );
add_action( 'admin_head',              'bbp_admin_head'                    );
add_action( 'admin_notices',           'bbp_admin_notices'                 );
add_action( 'menu_order',              'bbp_admin_menu_order'              );
add_filter( 'custom_menu_order',       'bbp_admin_custom_menu_order'       );

// Hook on to admin_init
add_action( 'bbp_admin_init', 'bbp_setup_updater',          999 );
add_action( 'bbp_admin_init', 'bbp_register_importers'          );
add_action( 'bbp_admin_init', 'bbp_register_admin_styles'       );
add_action( 'bbp_admin_init', 'bbp_register_admin_scripts'      );
add_action( 'bbp_admin_init', 'bbp_register_admin_settings'     );

// Hook on to current_screen
add_action( 'bbp_current_screen', 'bbp_admin_forums'  );
add_action( 'bbp_current_screen', 'bbp_admin_topics'  );
add_action( 'bbp_current_screen', 'bbp_admin_replies' );

// Initialize the admin area
add_action( 'bbp_init', 'bbp_setup_admin' );

// Reset the menu order
add_action( 'bbp_admin_menu', 'bbp_admin_separator' );

// Activation
add_action( 'bbp_activation',   'bbp_setup_new_site'              );
add_action( 'bbp_activation',   'bbp_add_activation_redirect'     );
add_action( 'bbp_activation',   'bbp_delete_rewrite_rules'        );
add_action( 'bbp_activation',   'bbp_make_current_user_keymaster' );
add_action( 'load-plugins.php', 'bbp_do_activation_redirect'      );

// Deactivation
add_action( 'bbp_deactivation', 'bbp_remove_caps'          );
add_action( 'bbp_deactivation', 'bbp_delete_rewrite_rules' );

// New Site
add_action( 'bbp_new_site', 'bbp_setup_new_site', 8 );

// Load the default repair tools
add_action( 'load-tools_page_bbp-repair',  'bbp_register_default_repair_tools' );
add_action( 'load-tools_page_bbp-upgrade', 'bbp_register_default_repair_tools' );

// Contextual Helpers
add_action( 'load-settings_page_bbpress',    'bbp_admin_settings_help'        );
add_action( 'load-tools_page_bbp-repair',    'bbp_admin_tools_repair_help'    );
add_action( 'load-tools_page_bbp-upgrade',   'bbp_admin_tools_repair_help'    );
add_action( 'load-tools_page_bbp-converter', 'bbp_admin_tools_converter_help' );
add_action( 'load-tools_page_bbp-reset',     'bbp_admin_tools_reset_help'     );

// Handle submission of Tools pages
add_action( 'load-tools_page_bbp-repair',  'bbp_admin_repair_handler' );
add_action( 'load-tools_page_bbp-upgrade', 'bbp_admin_repair_handler' );
add_action( 'load-tools_page_bbp-reset',   'bbp_admin_reset_handler'  );
add_action( 'bbp_admin_tool_box',          'bbp_admin_tools_box'      );

// Add sample permalink filter
add_filter( 'post_type_link', 'bbp_filter_sample_permalink', 10, 4 );

// Add quick stats to dashboard glance elements
add_filter( 'dashboard_glance_items', 'bbp_filter_dashboard_glance_items', -99 );

// Maybe use icons for column headers
add_filter( 'bbp_admin_forums_column_headers',  'bbp_filter_column_headers' );
add_filter( 'bbp_admin_topics_column_headers',  'bbp_filter_column_headers' );
add_filter( 'bbp_admin_replies_column_headers', 'bbp_filter_column_headers' );

// Load the converter early (page and AJAX)
add_action( 'load-tools_page_bbp-converter', 'bbp_setup_converter', 2 );
add_action( 'wp_ajax_bbp_converter_process', 'bbp_setup_converter', 2 );

// Add New User
add_action( 'user_new_form', 'bbp_add_user_form_role_field', 10, 1 );

/**
 * Setup bbPress admin
 *
 * @since 2.0.0 bbPress (r1000)
 * @since 2.6.0 bbPress (r6598) Moved to actions.php
 */
function bbp_admin() {
	return bbp_setup_admin();
}

/**
 * When a new site is created in a multisite installation, run the activation
 * routine on that site
 *
 * @since 2.0.0 bbPress (r3283)
 *
 * @param int $blog_id
 * @param int $user_id
 * @param string $domain
 * @param string $path
 * @param int $site_id
 * @param array() $meta
 */
function bbp_new_site( $blog_id, $user_id, $domain, $path, $site_id, $meta ) {

	// Bail if plugin is not network activated
	if ( ! is_plugin_active_for_network( bbpress()->basename ) ) {
		return;
	}

	// Switch to the new site
	bbp_switch_to_site( $blog_id );

	// Do the bbPress activation routine
	do_action( 'bbp_new_site', $blog_id, $user_id, $domain, $path, $site_id, $meta );

	// Restore original site
	bbp_restore_current_site();
}

/**
 * Show icons in list-table column headers instead of strings
 *
 * @since 2.6.0 bbPress (r5833)
 *
 * @param  array $columns Column headers fed into list-table objects
 *
 * @return array Possibly altered column headers
 */
function bbp_filter_column_headers( $columns = array() ) {

	// Do not filter column headers by default - maybe we'll turn it on later
	if ( ! apply_filters( 'bbp_filter_column_headers', false ) ) {
		return $columns;
	}

	/** Forums ****************************************************************/

	// Forum topic count
	if ( isset( $columns[ 'bbp_forum_topic_count' ] ) ) {
		$columns[ 'bbp_forum_topic_count' ] = '<span class="vers bbp_topics_column"  title="' . esc_attr__( 'Topics', 'bbpress' ) . '"><span class="screen-reader-text">' . esc_html__( 'Topics', 'bbpress' ) . '</span></span>';
	}

	// Forum reply count
	if ( isset( $columns[ 'bbp_forum_reply_count' ] ) ) {
		$columns[ 'bbp_forum_reply_count' ] = '<span class="vers bbp_replies_column"  title="' . esc_attr__( 'Replies', 'bbpress' ) . '"><span class="screen-reader-text">' . esc_html__( 'Replies', 'bbpress' ) . '</span></span>';
	}

	/** Topics ****************************************************************/

	// Topic forum
	if ( isset( $columns[ 'bbp_topic_forum' ] ) ) {
		$columns[ 'bbp_topic_forum' ] = '<span class="vers bbp_forums_column"  title="' . esc_attr__( 'Forum', 'bbpress' ) . '"><span class="screen-reader-text">' . esc_html__( 'Forum', 'bbpress' ) . '</span></span>';
	}

	// Topic reply count
	if ( isset( $columns[ 'bbp_topic_reply_count' ] ) ) {
		$columns[ 'bbp_topic_reply_count' ] = '<span class="vers bbp_replies_column"  title="' . esc_attr__( 'Replies', 'bbpress' ) . '"><span class="screen-reader-text">' . esc_html__( 'Replies', 'bbpress' ) . '</span></span>';
	}

	/** Replies ***************************************************************/

	// Reply forum
	if ( isset( $columns[ 'bbp_reply_forum' ] ) ) {
		$columns[ 'bbp_reply_forum' ] = '<span class="vers bbp_forums_column"  title="' . esc_attr__( 'Forum', 'bbpress' ) . '"><span class="screen-reader-text">' . esc_html__( 'Forum', 'bbpress' ) . '</span></span>';
	}

	// Reply topic
	if ( isset( $columns[ 'bbp_reply_topic' ] ) ) {
		$columns[ 'bbp_reply_topic' ] = '<span class="vers bbp_topics_column"  title="' . esc_attr__( 'Topic', 'bbpress' ) . '"><span class="screen-reader-text">' . esc_html__( 'Topic', 'bbpress' ) . '</span></span>';
	}

	return $columns;
}

/**
 * Filter sample permalinks so that certain languages display properly.
 *
 * @since 2.0.0 bbPress (r3336)
 *
 * @param string $post_link Custom post type permalink
 * @param object $_post Post data object
 * @param bool $leavename Optional, defaults to false. Whether to keep post name or page name.
 * @param bool $sample Optional, defaults to false. Is it a sample permalink.
 *
 * @return string The custom post type permalink
 */
function bbp_filter_sample_permalink( $post_link, $_post, $leavename = false, $sample = false ) {

	// Bail if not on an admin page and not getting a sample permalink
	if ( ! empty( $sample ) && is_admin() && bbp_is_custom_post_type() ) {
		return urldecode( $post_link );
	}

	// Return post link
	return $post_link;
}

/** Sub-Actions ***************************************************************/

/**
 * Piggy back admin_init action
 *
 * @since 2.1.0 bbPress (r3766)
 */
function bbp_admin_init() {
	do_action( 'bbp_admin_init' );
}

/**
 * Piggy back admin_menu action
 *
 * @since 2.1.0 bbPress (r3766)
 */
function bbp_admin_menu() {
	do_action( 'bbp_admin_menu' );
}

/**
 * Piggy back admin_head action
 *
 * @since 2.1.0 bbPress (r3766)
 */
function bbp_admin_head() {
	do_action( 'bbp_admin_head' );
}

/**
 * Piggy back admin_notices action
 *
 * @since 2.1.0 bbPress (r3766)
 */
function bbp_admin_notices() {
	do_action( 'bbp_admin_notices' );
}

/**
 * Dedicated action to register bbPress importers
 *
 * @since 2.1.0 bbPress (r3766)
 */
function bbp_register_importers() {
	do_action( 'bbp_register_importers' );
}

/**
 * Dedicated action to register admin styles
 *
 * @since 2.6.0 bbPress (r6912)
 */
function bbp_register_admin_styles() {

	/**
	 * Action used to register the admin styling
	 *
	 * @since 2.1.0
	 * @deprecated 2.6.0
	 */
	do_action( 'bbp_register_admin_style' );

	/**
	 * Action used to register all admin styling
	 *
	 * @since 2.6.0
	 */
	do_action( 'bbp_register_admin_styles' );
}

/**
 * Dedicated action to register admin scripts
 *
 * @since 2.6.0 bbPress (r6912)
 */
function bbp_register_admin_scripts() {
	do_action( 'bbp_register_admin_scripts' );
}

/**
 * Dedicated action to register admin settings
 *
 * @since 2.1.0 bbPress (r3766)
 */
function bbp_register_admin_settings() {
	do_action( 'bbp_register_admin_settings' );
}

/**
 * Dedicated action to output admin tools.php sections
 *
 * @since 2.6.0 bbPress (r6273)
 */
function bbp_admin_tool_box() {
	do_action( 'bbp_admin_tool_box' );
}

/**
 * Dedicated action to hook into the current screen
 *
 * @since 2.6.0 bbPress (r6185)
 *
 * @param WP_Screen $current_screen
 */
function bbp_current_screen( $current_screen = '' ) {
	do_action( 'bbp_current_screen', $current_screen );
}
