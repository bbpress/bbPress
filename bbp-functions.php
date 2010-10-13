<?php

/**
 * bbp_has_access()
 *
 * Make sure user can perform special tasks
 *
 * @package bbPress
 * @subpackage Functions
 * @since bbPress (1.2-r2464)
 *
 * @uses is_super_admin ()
 * @uses apply_filters
 *
 * @todo bbPress port of existing roles/caps
 * @return bool $has_access
 */
function bbp_has_access () {

	if ( is_super_admin () )
		$has_access = true;
	else
		$has_access = false;

	return apply_filters( 'bbp_has_access', $has_access );
}

/**
 * bbp_number_format ( $number, $decimals optional )
 *
 * A bbPress specific method of formatting numeric values
 *
 * @package bbPress
 * @subpackage Functions
 * @since bbPress (1.2-r2485)
 *
 * @param string $number Number to format
 * @param string $decimals optional Display decimals
 * @return string Formatted string
 */
function bbp_number_format ( $number, $decimals = false ) {
	// If empty, set $number to '0'
	if ( empty( $number ) || !is_numeric( $number ) )
		$number = '0';

	return apply_filters( 'bbp_number_format', number_format( $number, $decimals ), $number, $decimals );
}

/**
 * bbp_time_since( $time )
 *
 * Output formatted time to display human readable time difference.
 *
 * @package bbPress
 * @subpackage Functions
 * @since bbPress (1.2-r2454)
 *
 * @param $time
 */
function bbp_time_since( $time ) {
	echo bbp_get_time_since( $time );
}
	/**
	 * bbp_get_time_since( $time )
	 *
	 * Return formatted time to display human readable time difference.
	 *
	 * @package bbPress
	 * @subpackage Functions
	 * @since bbPress (1.2-r2454)
	 *
	 * @param $time
	 */
	function bbp_get_time_since ( $time ) {
		return apply_filters( 'bbp_get_time_since', human_time_diff( mysql2date( 'U', $time ), current_time( 'timestamp' ) ) );
	}

?>
