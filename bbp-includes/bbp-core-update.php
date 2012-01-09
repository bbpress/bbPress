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
 *
 * @uses get_option()
 * @uses bbp_get_db_version() To get bbPress's database version
 * @return bool True if update, False if not
 */
function bbp_is_update() {

	// Current DB version of this site (per site in a multisite network)
	$current_db   = get_option( '_bbp_db_version' );
	$current_live = bbp_get_db_version();

	// Compare versions (cast as int and bool to be safe)
	$is_update = (bool) ( (int) $current_db < (int) $current_live );

	// Return the product of version comparison
	return $is_update;
}

/**
 * Determine if bbPress is being activated
 *
 * Note that this function currently is not used in bbPress core and is here
 * for third party plugins to use to check for bbPress activation.
 *
 * @since bbPress (r3421)
 *
 * @global bbPress $bbp
 * @return bool True if activating bbPress, false if not
 */
function bbp_is_activation( $basename = '' ) {
	global $bbp;

	$action = false;
	if ( ! empty( $_REQUEST['action'] ) && ( '-1' != $_REQUEST['action'] ) )
		$action = $_REQUEST['action'];
	elseif ( ! empty( $_REQUEST['action2'] ) && ( '-1' != $_REQUEST['action2'] ) )
		$action = $_REQUEST['action2'];

	// Bail if not activating
	if ( empty( $action ) || !in_array( $action, array( 'activate', 'deactivate-selected' ) ) )
		return false;

	// The plugin(s) being activated
	if ( $action == 'activate' )
		$plugins = isset( $_GET['plugin'] ) ? array( $_GET['plugin'] ) : array();
	else
		$plugins = isset( $_POST['checked'] ) ? (array) $_POST['checked'] : array();

	// Set basename if empty
	if ( empty( $basename ) && !empty( $bbp->basename ) )
		$basename = $bbp->basename;

	// Bail if no basename
	if ( empty( $basename ) )
		return false;

	// Is bbPress being deactivated?
	return in_array( $basename, $plugins );
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

	$action = false;
	if ( ! empty( $_REQUEST['action'] ) && ( '-1' != $_REQUEST['action'] ) )
		$action = $_REQUEST['action'];
	elseif ( ! empty( $_REQUEST['action2'] ) && ( '-1' != $_REQUEST['action2'] ) )
		$action = $_REQUEST['action2'];

	// Bail if not deactivating
	if ( empty( $action ) || !in_array( $action, array( 'deactivate', 'deactivate-selected' ) ) )
		return false;

	// The plugin(s) being deactivated
	if ( $action == 'deactivate' )
		$plugins = isset( $_GET['plugin'] ) ? array( $_GET['plugin'] ) : array();
	else
		$plugins = isset( $_POST['checked'] ) ? (array) $_POST['checked'] : array();

	// Set basename if empty
	if ( empty( $basename ) && !empty( $bbp->basename ) )
		$basename = $bbp->basename;

	// Bail if no basename
	if ( empty( $basename ) )
		return false;

	// Is bbPress being deactivated?
	return in_array( $basename, $plugins );
}

/**
 * Update the DB to the latest version
 *
 * @since bbPress (r3421)
 * @uses update_option()
 * @uses bbp_get_db_version() To get bbPress's database version
 */
function bbp_version_bump() {
	$db_version = bbp_get_db_version();
	update_option( '_bbp_db_version', $db_version );
}

/**
 * Setup the bbPress updater
 *
 * @since bbPress (r3419)
 *
 * @uses bbp_version_bump()
 * @uses bbp_deactivation()
 * @uses bbp_activation()
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
