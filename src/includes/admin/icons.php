<?php

/**
 * bbPress admin icons.
 *
 * @package bbPress
 * @subpackage Administration
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Whether WordPress can render registered SVG icons in the admin menu.
 *
 * @since 2.6.21 bbPress (r8158)
 *
 * @return bool
 */
function bbp_use_svg_admin_icons() {
	global $wp_version;

	return version_compare( $wp_version, '7.2-alpha', '>=' )
		&& function_exists( 'wp_is_valid_icon_name' )
		&& function_exists( 'wp_register_icon_collection' )
		&& function_exists( 'wp_register_icon' )
		&& function_exists( 'wp_get_icon' );
}

/**
 * Identify admin pages that can use the bbPress SVG icons.
 *
 * @since 2.6.21 bbPress (r8158)
 *
 * @param string $classes Admin body classes.
 * @return string
 */
function bbp_admin_icon_body_class( $classes ) {
	return bbp_use_svg_admin_icons() ? $classes . ' bbp-svg-icons' : $classes;
}

/**
 * Register bbPress icons for WordPress admin menus that support the Icons API.
 *
 * @since 2.6.21 bbPress (r8158)
 */
function bbp_register_admin_icons() {
	if ( ! bbp_use_svg_admin_icons() ) {
		return;
	}

	wp_register_icon_collection(
		'bbpress',
		array(
			'label'  => __( 'bbPress', 'bbpress' ),
			'public' => false,
		)
	);

	$icons = array(
		'forum' => __( 'Forum', 'bbpress' ),
		'topic' => __( 'Topic', 'bbpress' ),
		'reply' => __( 'Reply', 'bbpress' ),
		'logo'  => __( 'bbPress logo', 'bbpress' ),
	);

	// The Icons API removes SVG groups, so assets transform their shapes directly.
	foreach ( $icons as $name => $label ) {
		wp_register_icon(
			'bbpress/' . $name,
			array(
				'label'     => $label,
				'file_path' => bbpress()->includes_dir . 'admin/assets/icons/' . $name . '.svg',
			)
		);
	}
}
