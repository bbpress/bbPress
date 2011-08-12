<?php

/**
 * bbPress Updater
 *
 * @package bbPress
 * @subpackage Updater
 */

// Exit if accessed directly
if ( !defined( 'ABSPATH' ) ) exit;

if ( !class_exists( 'BBP_Updater' ) ) :
/**
 * bbPress Updater Class
 *
 * @since bbPress (r3419)
 */
class BBP_Updater {

	/**
	 * Create the BBP_Updater class and call the updater
	 *
	 * @since bbPress (r3419)
	 *
	 * @uses BBP_Updater::update()
	 */
	function __construct() {
		$this->update();
	}

	/**
	 * The bbPress DB updater
	 *
	 * @since bbPress (r3419)
	 *
	 * @global bbPress $bbp
	 *
	 * @uses do_action()
	 * @uses flush_rewrite_rules()
	 * @uses update_option()
	 */
	function update() {
		global $bbp;

		// Fire the activation hook on update
		do_action( 'bbp_activation' );

		// Flush the rewrite rules
		flush_rewrite_rules();

		// Update to the latest version
		update_option( '_bbp_db_version', $bbp->db_version );
	}
}
endif;

/**
 * Setup the bbPress updater
 *
 * @since bbPress (r3419)
 *
 * @global bbPress $bbp
 * @uses BBP_Updater
 */
function bbp_setup_updater() {
	global $bbp;

	// Bail if not a super admin in the admin area
	if ( !is_admin() || !is_super_admin() )
		return;

	// Current DB version of this site
	$current_db = get_option( '_bbp_db_version' );

	// Compare versions
	if ( (int) $current_db >= (int) $bbp->db_version )
		return;

	$bbp->updater = new BBP_Updater();
}

?>
