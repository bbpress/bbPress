<?php

/**
 * Verify that development and package version metadata agree.
 *
 * @package bbPress
 */

$root = dirname( dirname( __DIR__ ) );

/**
 * Read a required regular-expression capture from a file.
 *
 * @param string $path    File path.
 * @param string $pattern Regular expression with one capture group.
 * @return string
 */
function bbp_ci_read_version( $path, $pattern ) {
	$contents = file_get_contents( $path );

	if ( ( false === $contents ) || ! preg_match( $pattern, $contents, $matches ) ) {
		fwrite( STDERR, "Unable to read version metadata from {$path}.\n" );
		exit( 1 );
	}

	return $matches[1];
}

$package      = json_decode( file_get_contents( $root . '/package.json' ), true );
$package_lock = json_decode( file_get_contents( $root . '/package-lock.json' ), true );

if ( ! is_array( $package ) || ! is_array( $package_lock ) ) {
	fwrite( STDERR, "Unable to parse package version metadata.\n" );
	exit( 1 );
}

$versions = array(
	'bbpress.php plugin header'     => bbp_ci_read_version( $root . '/bbpress.php', '/^ \* Version:\s+(\S+)/m' ),
	'src/bbpress.php plugin header' => bbp_ci_read_version( $root . '/src/bbpress.php', '/^ \* Version:\s+(\S+)/m' ),
	'src/bbpress.php runtime'       => bbp_ci_read_version( $root . '/src/bbpress.php', "/\\\$this->version\\s*=\\s*'([^']+)'/" ),
	'package.json'                  => $package['version'] ?? '',
	'package-lock.json'             => $package_lock['version'] ?? '',
);

if ( 1 !== count( array_unique( $versions ) ) || in_array( '', $versions, true ) ) {
	fwrite( STDERR, "Version metadata does not agree:\n" );

	foreach ( $versions as $label => $version ) {
		fwrite( STDERR, "- {$label}: {$version}\n" );
	}

	exit( 1 );
}

echo 'Version metadata agrees: ' . reset( $versions ) . "\n";
