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
 * If there is no raw DB version, this is the first installation
 *
 * @since bbPress (r3764)
 *
 * @uses get_option()
 * @uses bbp_get_db_version() To get bbPress's database version
 * @return bool True if update, False if not
 */
function bbp_is_install() {
	return ! bbp_get_db_version_raw();
}

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
	return (bool) ( (int) bbp_get_db_version_raw() < (int) bbp_get_db_version() );
}

/**
 * Determine if bbPress is being activated
 *
 * Note that this function currently is not used in bbPress core and is here
 * for third party plugins to use to check for bbPress activation.
 *
 * @since bbPress (r3421)
 *
 * @return bool True if activating bbPress, false if not
 */
function bbp_is_activation( $basename = '' ) {
	$bbp = bbpress();

	$action = false;
	if ( ! empty( $_REQUEST['action'] ) && ( '-1' != $_REQUEST['action'] ) )
		$action = $_REQUEST['action'];
	elseif ( ! empty( $_REQUEST['action2'] ) && ( '-1' != $_REQUEST['action2'] ) )
		$action = $_REQUEST['action2'];

	// Bail if not activating
	if ( empty( $action ) || !in_array( $action, array( 'activate', 'activate-selected' ) ) )
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
 * @return bool True if deactivating bbPress, false if not
 */
function bbp_is_deactivation( $basename = '' ) {
	$bbp = bbpress();

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

/**
 * Create a default forum, topic, and reply
 *
 * @since bbPress (r3767)
 */
function bbp_create_initial_content() {

	// Create the initial forum
	$forum_id = bbp_insert_forum( array(
		'post_title'     => __( 'General', 'bbpress' ),
		'post_content'   => __( 'General chit-chat', 'bbpress' )
	) );

	// Create the initial topic
	$topic_id = bbp_insert_topic(
		array(
			'post_parent'    => $forum_id,
			'post_title'     => __( 'Hello World!', 'bbpress' ),
			'post_content'   => __( 'I am the first topic in your new forums. You can keep me, edit me, trash me, or delete me.', 'bbpress' )
		),
		array( 'forum_id'    => $forum_id )
	);

	// Create the initial topic
	bbp_insert_reply(
		array(
			'post_parent'    => $topic_id,
			'post_title'     => __( 'Re: Hello World!', 'bbpress' ),
			'post_content'   => __( 'Oh, and this is what a reply looks like.', 'bbpress' )
		),
		array(
			'forum_id'           => $forum_id,
			'topic_id'           => $topic_id
		)
	);
}

?>
