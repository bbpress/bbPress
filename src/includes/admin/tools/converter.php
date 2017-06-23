<?php

/**
 * bbPress Converter
 *
 * Based on the hard work of Adam Ellis
 *
 * @package bbPress
 * @subpackage Administration
 */

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

/**
 * Return an array of available converters
 *
 * @since 2.6.0 bbPress (r6447)
 *
 * @return array
 */
function bbp_get_converters() {

	// Default
	$files  = array();
	$path   = bbp_setup_converter()->converters_dir;
	$curdir = opendir( $path );

	// Look for the converter file in the converters directory
	if ( false !== $curdir ) {
		while ( $file = readdir( $curdir ) ) {
			if ( stristr( $file, '.php' ) && stristr( $file, 'index' ) === false ) {
				$name = preg_replace( '/.php/', '', $file );
				if ( 'Example' !== $name ) {
					$files[ $name ] = $path . $file;
				}
			}
		}
	}

	// Close the directory
	closedir( $curdir );

	// Sort keys alphabetically, ignoring upper/lower casing
	if ( ! empty( $files ) ) {
		natcasesort( $files );
	}

	// Filter & return
	return (array) apply_filters( 'bbp_get_converters', $files );
}

/**
 * This is a function that is purposely written to look like a "new" statement.
 * It is basically a dynamic loader that will load in the platform conversion
 * of your choice.
 *
 * @since 2.0.0
 *
 * @param string $platform Name of valid platform class.
 */
function bbp_new_converter( $platform ) {

	// Default value
	$converters = bbp_get_converters();

	// Create a new converter object if it's found
	if ( isset( $converters[ $platform ] ) ) {

		// Include & create the converter
		require_once $converters[ $platform ];
		if ( class_exists( $platform ) ) {
			return new $platform;
		}
	}

	// Return null if no converter was found
	return null;
}
