<?php

/**
 * bbPress Admin Functions.
 *
 * @package bbPress
 * @subpackage Administration
 */

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

/** Admin Menus ***************************************************************/

/**
 * Add a separator to the WordPress admin menus.
 *
 * @since 2.0.0 bbPress (r2957)
 *
 * @global array $menu WordPress admin menu entries.
 */
function bbp_admin_separator() {

	// Caps necessary where a separator is necessary
	$caps = array(
		'bbp_forums_admin',
		'bbp_topics_admin',
		'bbp_replies_admin',
	);

	// Loop through caps, and look for a reason to show the separator
	foreach ( $caps as $cap ) {
		if ( current_user_can( $cap ) ) {
			bbp_admin()->show_separator = true;
			break;
		}
	}

	// Bail if no separator
	if ( false === bbp_admin()->show_separator ) {
		return;
	}

	global $menu;

	// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
	$menu[] = array( '', 'read', 'separator-bbpress', '', 'wp-menu-separator bbpress' );
}

/**
 * Tell WordPress we have a custom menu order.
 *
 * @since 2.0.0 bbPress (r2957)
 *
 * @param bool $menu_order Menu order.
 * @return bool True when the bbPress separator is shown; otherwise the original value.
 */
function bbp_admin_custom_menu_order( $menu_order = false ) {
	if ( false === bbp_admin()->show_separator ) {
		return $menu_order;
	}

	return true;
}

/**
 * Move our custom separator above our custom post types.
 *
 * @since 2.0.0 bbPress (r2957)
 *
 * @param array $menu_order Menu Order.
 * @return array Modified menu order.
 */
function bbp_admin_menu_order( $menu_order ) {

	// Bail if user cannot see any top level bbPress menus
	if ( empty( $menu_order ) || ( false === bbp_admin()->show_separator ) ) {
		return $menu_order;
	}

	// Initialize our custom order array
	$bbp_menu_order = array();

	// Menu values
	$second_sep   = 'separator2';
	$custom_menus = array(
		'separator-bbpress',                               // Separator
		'edit.php?post_type=' . bbp_get_forum_post_type(), // Forums
		'edit.php?post_type=' . bbp_get_topic_post_type(), // Topics
		'edit.php?post_type=' . bbp_get_reply_post_type()  // Replies
	);

	// Loop through menu order and do some rearranging
	foreach ( $menu_order as $item ) {

		// Position bbPress menus above appearance
		if ( $second_sep === $item ) {

			// Add our custom menus
			foreach ( $custom_menus as $custom_menu ) {
				if ( array_search( $custom_menu, $menu_order, true ) ) {
					$bbp_menu_order[] = $custom_menu;
				}
			}

			// Add the appearance separator
			$bbp_menu_order[] = $second_sep;

		// Skip our menu items
		} elseif ( ! in_array( $item, $custom_menus, true ) ) {
			$bbp_menu_order[] = $item;
		}
	}

	// Return our custom order
	return $bbp_menu_order;
}

/**
 * Sanitize permalink slugs when saving the settings page.
 *
 * @since 2.6.0 bbPress (r5364)
 *
 * @param string $slug Optional. Permalink slug to sanitize. Default empty.
 * @return string Sanitized slug without leading or trailing slashes.
 */
function bbp_sanitize_slug( $slug = '' ) {

	// Don't allow multiple slashes in a row
	$value = preg_replace( '#/+#', '/', str_replace( '#', '', $slug ) );

	// Strip out unsafe or unusable chars
	$value = esc_url_raw( $value );

	// esc_url_raw() adds a scheme via esc_url(), so let's remove it
	$value = str_replace( 'http://', '', $value );

	// Trim off first and last slashes.
	//
	// We already prevent double slashing elsewhere, but let's prevent
	// accidental poisoning of options values where we can.
	$value = ltrim( $value, '/' );
	$value = rtrim( $value, '/' );

	/**
	 * Filters the sanitized slug value.
	 *
	 * @since 2.6.0 bbPress (r5364)
	 *
	 * @param string $value The sanitized slug.
	 * @param string $slug  The original slug value.
	 */
	return apply_filters( 'bbp_sanitize_slug', $value, $slug );
}

/**
 * Uninstall all bbPress options and capabilities from a specific site.
 *
 * @since 2.1.0 bbPress (r3765)
 *
 * @param int $site_id Optional. Site ID to uninstall. Defaults to the current site.
 */
function bbp_do_uninstall( $site_id = 0 ) {
	if ( empty( $site_id ) ) {
		$site_id = get_current_blog_id();
	}

	bbp_switch_to_site( $site_id );
	bbp_delete_options();
	bbp_remove_roles();
	bbp_remove_caps();
	flush_rewrite_rules();
	bbp_restore_current_site();
}

/**
 * This tells WP to highlight the Tools > Forums menu item,
 * regardless of which actual bbPress Tools screen we are on.
 *
 * The conditional prevents the override when the user is viewing settings or
 * any third-party plugins.
 *
 * @since 2.1.0 bbPress (r3888)
 *
 * @global string $plugin_page  Current plugin admin page.
 * @global string $submenu_file Submenu item to highlight.
 */
function bbp_tools_modify_menu_highlight() {
	global $plugin_page, $submenu_file;

	// This tweaks the Tools subnav menu to only show one bbPress menu item
	if ( ! in_array( $plugin_page, array( 'bbp-settings' ), true ) ) {

		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		$submenu_file = 'bbp-repair';
	}
}
