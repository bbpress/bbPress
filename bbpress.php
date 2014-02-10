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

// Include bbPress
include( __DIR__ . '/src/bbpress.php' );