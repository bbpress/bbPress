<?php

/**
 * The bbPress Plugin
 *
 * bbPress is forum software with a twist from the creators of WordPress.
 *
 * $Id$
 *
 * @package bbPress
 * @subpackage Main
 */

/**
 * Plugin Name: bbPress
 * Plugin URI:  http://bbpress.org
 * Description: bbPress is forum software with a twist from the creators of WordPress.
 * Author:      The bbPress Community
 * Author URI:  http://bbpress.org
 * Version:     2.6-alpha
 * Text Domain: bbpress
 * Domain Path: /languages/
 */

// Exit if accessed directly
if ( !defined( 'ABSPATH' ) ) exit;

// Assume you want to load from build
$bbp_loader = __DIR__ . '/build/bbpress.php';

// Load from source if no build exists
if ( ! file_exists( $bbp_loader ) || defined( 'BBP_LOAD_SOURCE' ) ) {
	$bbp_loader = __DIR__ . '/src/bbpress.php';
}

// Include bbPress
include( $bbp_loader );

// Unset the loader, since it's loaded in global scope
unset( $bbp_loader );
