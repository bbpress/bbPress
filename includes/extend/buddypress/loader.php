<?php

/**
 * Main bbPress BuddyPress Class
 *
 * @package bbPress
 * @subpackage BuddyPress
 * @todo maybe move to BuddyPress Forums once bbPress 1.1 can be removed
 * @todo move this into the main component?
 */

// Exit if accessed directly
if ( !defined( 'ABSPATH' ) ) exit;

if ( !class_exists( 'BBP_BuddyPress' ) ) :
/**
 * Loads BuddyPress extension
 *
 * @since bbPress (r3395)
 *
 * @package bbPress
 * @subpackage BuddyPress
 */
class BBP_BuddyPress {

	/** Setup Methods *********************************************************/

	/**
	 * The main bbPress BuddyPress loader
	 *
	 * @since bbPress (r3395)
	 */
	public function __construct() {
		$this->includes();
		$this->setup_actions();
		$this->setup_filters();
		$this->fully_loaded();
	}

	/**
	 * Include BuddyPress classes and functions
	 */
	public function includes() {

		// BuddyPress Component Extension class 
		require( bbpress()->includes_dir . 'extend/buddypress/component.php' );

		// Helper BuddyPress functions
		require( bbpress()->includes_dir . 'extend/buddypress/functions.php' );

		// BuddyPress Activity Extension class 
		if ( bp_is_active( 'activity' ) ) {
			require( bbpress()->includes_dir . 'extend/buddypress/activity.php' );
		}

		// BuddyPress Group Extension class 
		if ( bbp_is_group_forums_active() && bp_is_active( 'groups' ) ) {
			require( bbpress()->includes_dir . 'extend/buddypress/group.php' );
		}
	}

	/**
	 * Instantiate classes for integration
	 */
	public function setup_components() {

		// Create the new BuddyPress Forums component
		if ( ! bp_is_active( 'forums' ) || ! bp_forums_is_installed_correctly() ) {
			buddypress()->forums = new BBP_Forums_Component();
		}

		// Create new activity class
		if ( bp_is_active( 'activity' ) ) {
			bbpress()->extend->activity = new BBP_BuddyPress_Activity;
		}

		// Register the group extension only if groups are active
		if ( bbp_is_group_forums_active() && bp_is_active( 'groups' ) ) {
			bp_register_group_extension( 'BBP_Forums_Group_Extension' );
		}
	}

	/**
	 * Setup the actions
	 *
	 * @since bbPress (r3395)
	 * @access private
	 * @uses add_filter() To add various filters
	 * @uses add_action() To add various actions
	 */
	private function setup_actions() {

		// Setup the components
		add_action( 'bp_init', array( $this, 'setup_components' ) );

		/** Favorites *********************************************************/

		// Move handler to 'bp_actions' - BuddyPress bypasses template_loader
		remove_action( 'template_redirect', 'bbp_favorites_handler', 1 );
		add_action(    'bp_actions',        'bbp_favorites_handler', 1 );

		/** Subscriptions *****************************************************/

		// Move handler to 'bp_actions' - BuddyPress bypasses template_loader
		remove_action( 'template_redirect', 'bbp_subscriptions_handler', 1 );
		add_action(    'bp_actions',        'bbp_subscriptions_handler', 1 );
	}

	/**
	 * Setup the filters
	 *
	 * @since bbPress (r3395)
	 * @access private
	 * @uses add_filter() To add various filters
	 * @uses add_action() To add various actions
	 */
	private function setup_filters() {

		// Override bbPress user profile URL with BuddyPress profile URL
		add_filter( 'bbp_pre_get_user_profile_url',    array( $this, 'user_profile_url'            )        );
		add_filter( 'bbp_get_favorites_permalink',     array( $this, 'get_favorites_permalink'     ), 10, 2 );
		add_filter( 'bbp_get_subscriptions_permalink', array( $this, 'get_subscriptions_permalink' ), 10, 2 );
	}

	/**
	 * Allow the variables, actions, and filters to be modified by third party
	 * plugins and themes.
	 *
	 * @since bbPress (r3902)
	 */
	private function fully_loaded() {
		do_action_ref_array( 'bbp_buddypress_loaded', array( $this ) );
	}
	
	/**
	 * Override bbPress profile URL with BuddyPress profile URL
	 *
	 * @since bbPress (r3401)
	 * @param string $url
	 * @param int $user_id
	 * @param string $user_nicename
	 * @return string
	 */
	public function user_profile_url( $user_id ) {

		// Define local variable(s)
		$profile_url = '';

		// Special handling for forum component
		if ( bp_is_current_component( 'forums' ) ) {

			// Empty action or 'topics' action
			if ( !bp_current_action() || bp_is_current_action( 'topics' ) ) {
				$profile_url = bp_core_get_user_domain( $user_id ) . 'forums/topics';

			// Empty action or 'topics' action
			} elseif ( bp_is_current_action( 'replies' ) ) {
				$profile_url = bp_core_get_user_domain( $user_id ) . 'forums/replies';

			// 'favorites' action
			} elseif ( bbp_is_favorites_active() && bp_is_current_action( 'favorites' ) ) {
				$profile_url = $this->get_favorites_permalink( '', $user_id );

			// 'subscriptions' action
			} elseif ( bbp_is_subscriptions_active() && bp_is_current_action( 'subscriptions' ) ) {
				$profile_url = $this->get_subscriptions_permalink( '', $user_id );
			}

		// Not in users' forums area
		} else {
			$profile_url = bp_core_get_user_domain( $user_id );
		}

		return trailingslashit( $profile_url );
	}

	/**
	 * Override bbPress favorites URL with BuddyPress profile URL
	 *
	 * @since bbPress (r3721)
	 * @param string $url
	 * @param int $user_id
	 * @return string
	 */
	public function get_favorites_permalink( $url, $user_id ) {
		$url = trailingslashit( bp_core_get_user_domain( $user_id ) . 'forums/favorites' );
		return $url;
	}

	/**
	 * Override bbPress subscriptions URL with BuddyPress profile URL
	 *
	 * @since bbPress (r3721)
	 * @param string $url
	 * @param int $user_id
	 * @return string
	 */
	public function get_subscriptions_permalink( $url, $user_id ) {
		$url = trailingslashit( bp_core_get_user_domain( $user_id ) . 'forums/subscriptions' );
		return $url;
	}
}
endif;
