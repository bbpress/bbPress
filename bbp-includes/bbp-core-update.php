<?php

/**
 * bbPress Updater
 *
 * @package bbPress
 * @subpackage Updater
 */

// Exit if accessed directly
if ( !defined( 'ABSPATH' ) ) exit;

/**
 * Compare the bbPress version to the DB version to determine if updating
 * 
 * @since bbPress (r3421)
 * @global bbPress $bbp
 * @uses get_option()
 * @return bool True if update, False if not
 */
function bbp_is_update() {
	global $bbp;

	// Current DB version of this site (per site in a multisite network)
	$current_db = get_option( '_bbp_db_version' );

	// Compare versions (cast as int and bool to be safe)
	$is_update = (bool) ( (int) $current_db < (int) $bbp->db_version );

	// Return the product of version comparison
	return $is_update;
}

/**
 * Determine if bbPress is being activated
 *
 * @since bbPress (r3421)
 * @global bbPress $bbp
 * @return bool True if activating bbPress, false if not
 */
function bbp_is_activation( $basename = '' ) {
	global $bbp;

	// Baif if action or plugin are empty
	if ( empty( $_GET['action'] ) || empty( $_GET['plugin'] ) )
		return false;

	// Bail if not activating
	if ( 'activate' !== $_GET['action'] )
		return false;

	// The plugin being activated
	$plugin = isset( $_GET['plugin'] ) ? $_GET['plugin'] : '';

	// Set basename if empty
	if ( empty( $basename ) && !empty( $bbp->basename ) )
		$basename = $bbp->basename;
	
	// Bail if no basename 
	if ( empty( $basename ) )
		return false;

	// Bail if plugin is not bbPress
	if ( $basename !== $_GET['plugin'] )
		return false;

	return true;
}

/**
 * Determine if bbPress is being deactivated
 *
 * @since bbPress (r3421)
 * @global bbPress $bbp
 * @return bool True if deactivating bbPress, false if not
 */
function bbp_is_deactivation( $basename = '' ) {
	global $bbp;

	// Baif if action or plugin are empty
	if ( empty( $_GET['action'] ) || empty( $_GET['plugin'] ) )
		return false;

	// Bail if not deactivating
	if ( 'deactivate' !== $_GET['action'] )
		return false;

	// The plugin being deactivated
	$plugin = isset( $_GET['plugin'] ) ? $_GET['plugin'] : '';

	// Set basename if empty
	if ( empty( $basename ) && !empty( $bbp->basename ) )
		$basename = $bbp->basename;
	
	// Bail if no basename 
	if ( empty( $basename ) )
		return false;

	// Bail if plugin is not bbPress
	if ( $basename !== $plugin )
		return false;

	return true;
}

/**
 * Update the DB to the latest version
 * 
 * @since bbPress (r3421)
 * @uses update_option()
 */
function bbp_version_bump() {
	global $bbp;

	update_option( '_bbp_db_version', $bbp->db_version );
}

/**
 * Setup the bbPress updater
 *
 * @since bbPress (r3419)
 *
 * @global bbPress $bbp
 * @uses BBP_Updater
 */
function bbp_setup_updater() {

	// Are we running an outdated version of bbPress?
	if ( bbp_is_update() ) {

		// Bump the version
		bbp_version_bump();

		// Run the deactivation function to wipe roles, caps, and rewrite rules
		bbp_deactivation();

		// Run the activation function to reset roles, caps, and rewrite rules
		bbp_activation();
	}
}

?>
