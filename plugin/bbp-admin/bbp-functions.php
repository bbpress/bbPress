<?php

/**
 * bbPress Admin Functions
 *
 * @package bbPress
 * @subpackage Administration
 */

// Exit if accessed directly
if ( !defined( 'ABSPATH' ) ) exit;

/** Admin Menus ***************************************************************/

/**
 * Add a separator to the WordPress admin menus
 *
 * @since bbPress (r2957)
 */
function bbp_admin_separator() {

	// Prevent duplicate separators when no new menu items exist
	if ( !current_user_can( 'edit_replies' ) )
		return;

	// Prevent duplicate separators when no core menu items exist
	if ( !current_user_can( 'manage_options' ) )
		return;

	global $menu;

	$menu[] = array( '', 'read', 'separator-bbpress', '', 'wp-menu-separator bbpress' );
}

/**
 * Tell WordPress we have a custom menu order
 *
 * @since bbPress (r2957)
 *
 * @param bool $menu_order Menu order
 * @return bool Always true
 */
function bbp_admin_custom_menu_order( $menu_order ) {
	if ( !current_user_can( 'edit_replies' ) )
		return $menu_order;

	return true;
}

/**
 * Move our custom separator above our custom post types
 *
 * @since bbPress (r2957)
 *
 * @param array $menu_order Menu Order
 * @uses bbp_get_forum_post_type() To get the forum post type
 * @return array Modified menu order
 */
function bbp_admin_menu_order( $menu_order ) {

	// Initialize our custom order array
	$bbp_menu_order = array();

	// Get the index of our custom separator
	$bbp_separator = array_search( 'separator-bbpress', $menu_order );

	// Forums
	if ( current_user_can( 'edit_forums' ) ) {
		$top_menu_type = bbp_get_forum_post_type();

	// Topics
	} elseif ( current_user_can( 'edit_topics' ) ) {
		$top_menu_type = bbp_get_topic_post_type();

	// Replies
	} elseif ( current_user_can( 'edit_replies' ) ) {
		$top_menu_type = bbp_get_reply_post_type();

	// Bail if there are no bbPress menus present
	} else {
		return;
	}

	// Loop through menu order and do some rearranging
	foreach ( $menu_order as $index => $item ) {

		// Current item is ours, so set our separator here
		if ( ( ( 'edit.php?post_type=' . $top_menu_type ) == $item ) ) {
			$bbp_menu_order[] = 'separator-bbpress';
			unset( $menu_order[$bbp_separator] );
		}

		// Skip our separator
		if ( !in_array( $item, array( 'separator-bbpress' ) ) ) {
			$bbp_menu_order[] = $item;
		}
	}

	// Return our custom order
	return $bbp_menu_order;
}

/**
 * Filter sample permalinks so that certain languages display properly.
 *
 * @since bbPress (r3336)
 *
 * @param string $post_link Custom post type permalink
 * @param object $_post Post data object
 * @param bool $leavename Optional, defaults to false. Whether to keep post name or page name.
 * @param bool $sample Optional, defaults to false. Is it a sample permalink.
 *
 * @uses is_admin() To make sure we're on an admin page
 * @uses bbp_is_custom_post_type() To get the forum post type
 *
 * @return string The custom post type permalink
 */
function bbp_filter_sample_permalink( $post_link, $_post, $leavename = false, $sample = false ) {

	// Bail if not on an admin page and not getting a sample permalink
	if ( !empty( $sample ) && is_admin() && bbp_is_custom_post_type() )
		return urldecode( $post_link );

	// Return post link
	return $post_link;
}

/**
 * Uninstall all bbPress options and capabilities from a specific site.
 *
 * @since bbPress (r3765)
 * @param type $site_id 
 */
function bbp_do_uninstall( $site_id = 0 ) {
	if ( empty( $site_id ) )
		$site_id = get_current_blog_id();

	switch_to_blog( $site_id );
	bbp_delete_options();
	bbp_remove_caps();
	flush_rewrite_rules();
	restore_current_blog();
}

/**
 * This tells WP to highlight the Tools > Forums menu item,
 * regardless of which actual bbPress Tools screen we are on.
 *
 * The conditional prevents the override when the user is viewing settings or
 * any third-party plugins.
 *
 * @since bbPress (r3888)
 * @global string $plugin_page
 * @global array $submenu_file
 */
function bbp_tools_modify_menu_highlight() {
	global $plugin_page, $submenu_file;

	// This tweaks the Tools subnav menu to only show one bbPress menu item
	if ( ! in_array( $plugin_page, array( 'bbp-settings' ) ) )
		$submenu_file = 'bbp-repair';
}

/**
 * Output the tabs in the admin area
 *
 * @since bbPress (r3872)
 * @param string $active_tab Name of the tab that is active
 */
function bbp_tools_admin_tabs( $active_tab = '' ) {
	echo bbp_get_tools_admin_tabs( $active_tab );
}

	/**
	 * Output the tabs in the admin area
	 *
	 * @since bbPress (r3872)
	 * @param string $active_tab Name of the tab that is active
	 */
	function bbp_get_tools_admin_tabs( $active_tab = '' ) {

		// Declare local variables
		$tabs_html    = '';
		$idle_class   = 'nav-tab';
		$active_class = 'nav-tab nav-tab-active';

		// Setup core admin tabs
		$tabs = apply_filters( 'bbp_tools_admin_tabs', array(
			'0' => array(
				'href' => get_admin_url( '', add_query_arg( array( 'page' => 'bbp-repair'    ), 'tools.php' ) ),
				'name' => __( 'Repair Forums', 'bbpress' )
			),
			'1' => array(
				'href' => get_admin_url( '', add_query_arg( array( 'page' => 'bbp-converter' ), 'tools.php' ) ),
				'name' => __( 'Import Forums', 'bbpress' )
			),
			'2' => array(
				'href' => get_admin_url( '', add_query_arg( array( 'page' => 'bbp-reset'     ), 'tools.php' ) ),
				'name' => __( 'Reset Forums', 'bbpress' )
			)
		) );

		// Loop through tabs and build navigation
		foreach( $tabs as $tab_id => $tab_data ) {
			$is_current = (bool) ( $tab_data['name'] == $active_tab );
			$tab_class  = $is_current ? $active_class : $idle_class;
			$tabs_html .= '<a href="' . $tab_data['href'] . '" class="' . $tab_class . '">' . $tab_data['name'] . '</a>';
		}

		// Output the tabs
		return $tabs_html;
	}
