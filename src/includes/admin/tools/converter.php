<?php

/**
 * bbPress Converter.
 *
 * Based on the hard work of Adam Ellis.
 *
 * @package bbPress
 * @subpackage Administration
 */

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

/**
 * Return an array of available converters.
 *
 * @since 2.6.0 bbPress (r6447)
 *
 * @return array
 */
function bbp_get_converters() {
	static $files = array();

	// Only hit the file system one time per page load
	if ( empty( $files ) ) {

		// Open the converter directory
		$path   = bbp_setup_converter()->converters_dir;
		$curdir = opendir( $path );

		// Look for the converter file in the converters directory
		if ( false !== $curdir ) {

			// phpcs:ignore Generic.CodeAnalysis.AssignmentInCondition.FoundInWhileCondition
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
	}

	/**
	 * Filters the array of available converters.
	 *
	 * @since 2.6.0 bbPress (r6447)
	 *
	 * @param array $files Array of available converters with names as keys and file paths as values.
	 */
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
 *
 * @return mixed Object if converter exists, null if not
 */
function bbp_new_converter( $platform = '' ) {

	// Default converter
	$converter = null;

	// Bail if no platform
	if ( empty( $platform ) ) {
		return $converter;
	}

	// Get the available converters
	$converters = bbp_get_converters();

	// Get the converter file form converters array
	$converter_file = isset( $converters[ $platform ] )
		? $converters[ $platform ]
		: '';

	// Try to create a new converter object
	if ( ! empty( $converter_file ) ) {

		// Try to include the converter
		// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		@include_once $converter_file;

		// Try to instantiate the converter object
		if ( class_exists( $platform ) ) {
			$converter = new $platform();
		}
	}

	/**
	 * Filters the newly created converter object.
	 *
	 * @since 2.0.0
	 *
	 * @param mixed  $converter The converter object if it exists, null otherwise.
	 * @param string $platform  Name of the platform class used for conversion.
	 */
	return apply_filters( 'bbp_new_converter', $converter, $platform );
}
