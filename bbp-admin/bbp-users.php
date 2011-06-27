<?php

/**
 * bbPress Users Admin Class
 *
 * @package bbPress
 * @subpackage Administration
 */

// Exit if accessed directly
if ( !defined( 'ABSPATH' ) ) exit;

if ( !class_exists( 'BBP_Users_Admin' ) ) :
/**
 * Loads bbPress users admin area
 *
 * @package bbPress
 * @subpackage Administration
 * @since bbPress (r2464)
 */
class BBP_Users_Admin {

	/** Variables *************************************************************/

	/** Functions *************************************************************/

	/**
	 * The bbPress users admin loader (PHP4 compat)
	 *
	 * @since bbPress (r2515)
	 *
	 * @uses BBP_Users_Admin::_setup_globals() Setup the globals needed
	 * @uses BBP_Users_Admin::_setup_actions() Setup the hooks and actions
	 */
	function BBP_Users_Admin() {
		$this->__construct();
	}

	/**
	 * The bbPress users admin loader
	 *
	 * @since bbPress (r2515)
	 *
	 * @uses BBP_Users_Admin::_setup_globals() Setup the globals needed
	 * @uses BBP_Users_Admin::_setup_actions() Setup the hooks and actions
	 */
	function __construct() {
		$this->_setup_globals();
		$this->_setup_actions();
	}

	/**
	 * Setup the admin hooks, actions and filters
	 *
	 * @since bbPress (r2646)
	 * @access private
	 *
	 * @uses add_action() To add various actions
	 */
	function _setup_actions() {

		// User profile edit/display actions
		add_action( 'edit_user_profile',        array( $this, 'user_profile_forums' ) );
		add_action( 'show_user_profile',        array( $this, 'user_profile_forums' ) );

		// User profile save actions
		add_action( 'personal_options_update',  array( $this, 'user_profile_update' ) );
		add_action( 'edit_user_profile_update', array( $this, 'user_profile_update' ) );
	}

	/**
	 * Admin globals
	 *
	 * @since bbPress (r2646)
	 * @access private
	 */
	function _setup_globals() { }

	/**
	 * Add some general styling to the admin area
	 *
	 * @since bbPress (r2464)
	 *
	 * @uses bbp_get_forum_post_type() To get the forum post type
	 * @uses bbp_get_topic_post_type() To get the topic post type
	 * @uses bbp_get_reply_post_type() To get the reply post type
	 * @uses sanitize_html_class() To sanitize the classes
	 */
	function admin_head() { }

	/**
	 * Responsible for saving additional profile options and settings
	 *
	 * @since bbPress (r2464)
	 *
	 * @param $user_id The user id
	 * @uses do_action() Calls 'bbp_user_profile_update'
	 * @return bool Always false
	 */
	function user_profile_update( $user_id ) { }

	/**
	 * Responsible for saving additional profile options and settings
	 *
	 * @since bbPress (r2464)
	 *
	 * @param WP_User $profileuser User data
	 * @uses do_action() Calls 'bbp_user_profile_forums'
	 * @return bool Always false
	 */
	function user_profile_forums( $profileuser ) { }
}
endif; // class exists

/**
 * Setup bbPress Users Admin
 *
 * @since bbPress (r2596)
 *
 * @uses BBP_Replies_Admin
 */
function bbp_users_admin() {
	global $bbp;

	$bbp->admin->users = new BBP_Users_Admin();
}

?>
