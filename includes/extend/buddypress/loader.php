<?php

/**
 * Main bbPress BuddyPress Class
 *
 * @package bbPress
 * @subpackage BuddyPress
 * @todo maybe move to BuddyPress Forums once bbPress 1.1 can be removed
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

	/** Slugs *****************************************************************/

	/**
	 * Forums slug
	 *
	 * @var string
	 */
	private $forums_slug = '';

	/**
	 * Topic slug
	 *
	 * @var string
	 */
	private $topic_slug = '';

	/**
	 * Reply slug
	 *
	 * @var string
	 */
	private $reply_slug = '';

	/** Setup Methods *********************************************************/

	/**
	 * The main bbPress BuddyPress loader
	 *
	 * @since bbPress (r3395)
	 */
	public function __construct() {
		$this->includes();
		$this->setup_components();
		$this->setup_globals();
		$this->setup_actions();
		$this->setup_filters();
		$this->fully_loaded();
	}

	/**
	 * Include BuddyPress classes and functions
	 */
	public function includes() {
		require( bbpress()->includes_dir . 'extend/buddypress/activity.php'  ); // BuddyPress Activity Extension class 
		require( bbpress()->includes_dir . 'extend/buddypress/group.php'     ); // BuddyPress Group Extension class 
		require( bbpress()->includes_dir . 'extend/buddypress/component.php' ); // BuddyPress Component Extension class 
		require( bbpress()->includes_dir . 'extend/buddypress/functions.php' ); // Helper BuddyPress functions
	}

	/**
	 * Instantiate classes for integration
	 */
	public function setup_components() {
		bbpress()->extend->activity = new BBP_BuddyPress_Activity;
	}

	/**
	 * Extension variables
	 *
	 * @since bbPress (r3395)
	 * @access private
	 * @uses apply_filters() Calls various filters
	 */
	private function setup_globals() {
		$this->forums_slug = 'forums';
		$this->topic_slug  = 'topic';
		$this->reply_slug  = 'reply';
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
		add_action( 'template_redirect', array( $this, 'redirect_canonical' ) );
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

		// Map forum/topic/replys permalinks to their groups
		add_filter( 'bbp_get_forum_permalink', array( $this, 'map_forum_permalink_to_group' ), 10, 2 );
		add_filter( 'bbp_get_topic_permalink', array( $this, 'map_topic_permalink_to_group' ), 10, 2 );
		add_filter( 'bbp_get_reply_permalink', array( $this, 'map_reply_permalink_to_group' ), 10, 2 );

		// Map reply edit links to their groups
		add_filter( 'bbp_get_reply_edit_url',  array( $this, 'map_reply_edit_url_to_group'  ), 10, 2 );

		// Map assorted template function permalinks
		add_filter( 'post_link',               array( $this, 'post_link'                    ), 10, 2 );
		add_filter( 'page_link',               array( $this, 'page_link'                    ), 10, 2 );
		add_filter( 'post_type_link',          array( $this, 'post_type_link'               ), 10, 2 );

		// Group forum pagination
		add_filter( 'bbp_topic_pagination',   array( $this, 'topic_pagination'   ) );
		add_filter( 'bbp_replies_pagination', array( $this, 'replies_pagination' ) );
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

	/** Permalink Mappers *****************************************************/

	/**
	 * Maybe map a bbPress forum/topic/reply permalink to the corresponding group
	 *
	 * @param int $post_id
	 * @uses get_post()
	 * @uses bbp_is_reply()
	 * @uses bbp_get_reply_topic_id()
	 * @uses bbp_get_reply_forum_id()
	 * @uses bbp_is_topic()
	 * @uses bbp_get_topic_forum_id()
	 * @uses bbp_is_forum()
	 * @uses get_post_field()
	 * @uses bbp_get_forum_group_ids()
	 * @uses groups_get_group()
	 * @uses bp_get_group_admin_permalink()
	 * @uses bp_get_group_permalink()
	 * @return Bail early if not a group forum post
	 * @return string
	 */
	private function maybe_map_permalink_to_group( $post_id = 0, $url = false ) {

		switch ( get_post_type( $post_id ) ) {

			// Reply
			case bbp_get_reply_post_type() :
				$topic_id = bbp_get_reply_topic_id( $post_id );
				$forum_id = bbp_get_reply_forum_id( $post_id );
				$url_end  = trailingslashit( $this->reply_slug ) . get_post_field( 'post_name', $post_id );
				break;

			// Topic
			case bbp_get_topic_post_type() :
				$topic_id = $post_id;
				$forum_id = bbp_get_topic_forum_id( $post_id );
				$url_end  = trailingslashit( $this->topic_slug ) . get_post_field( 'post_name', $post_id );
				break;

			// Forum
			case bbp_get_forum_post_type() :
				$forum_id = $post_id;
				$url_end  = get_post_field( 'post_name', $post_id );
				break;

			// Unknown
			default :
				return $url;
				break;
		}
		
		// Get group ID's for this forum
		$group_ids = bbp_get_forum_group_ids( $forum_id );

		// Bail if the post isn't associated with a group
		if ( empty( $group_ids ) )
			return $url;

		// @todo Multiple group forums/forum groups
		$group_id = $group_ids[0];
		$group    = groups_get_group( array( 'group_id' => $group_id ) );

		if ( bp_is_group_admin_screen( $this->forums_slug ) ) {
			$group_permalink = trailingslashit( bp_get_group_admin_permalink( $group ) );
		} else {
			$group_permalink = trailingslashit( bp_get_group_permalink( $group ) );
		}

		return trailingslashit( trailingslashit( $group_permalink . $this->forums_slug ) . $url_end );
	}

	/**
	 * Map a forum permalink to its corresponding group
	 *
	 * @since bbPress (r3802)
	 * @param string $url
	 * @param int $forum_id
	 * @uses maybe_map_permalink_to_group()
	 * @return string
	 */
	public function map_forum_permalink_to_group( $url, $forum_id ) {
		return $this->maybe_map_permalink_to_group( $forum_id, $url );
	}

	/**
	 * Map a topic permalink to its group forum
	 *
	 * @since bbPress (r3802)
	 * @param string $url
	 * @param int $topic_id
	 * @uses maybe_map_permalink_to_group()
	 * @return string
	 */
	public function map_topic_permalink_to_group( $url, $topic_id ) {
		return $this->maybe_map_permalink_to_group( $topic_id, $url );
	}

	/**
	 * Map a reply permalink to its group forum
	 *
	 * @since bbPress (r3802)
	 * @param string $url
	 * @param int $reply_id
	 * @uses maybe_map_permalink_to_group()
	 * @return string
	 */
	public function map_reply_permalink_to_group( $url, $reply_id ) {
		return $this->maybe_map_permalink_to_group( bbp_get_reply_topic_id( $reply_id ), $url );
	}

	/**
	 * Map a reply edit link to its group forum
	 *
	 * @param string $url
	 * @param int $reply_id
	 * @uses maybe_map_permalink_to_group()
	 * @return string
	 */
	public function map_reply_edit_url_to_group( $url, $reply_id ) {
		$new = $this->maybe_map_permalink_to_group( $reply_id );

		if ( empty( $new ) )
			return $url;

		return trailingslashit( $new ) . bbpress()->edit_id  . '/';
	}

	/**
	 * Map a post link to its group forum
	 *
	 * @param string $url
	 * @param obj $post
	 * @param boolean $leavename
	 * @uses maybe_map_permalink_to_group()
	 * @return string
	 */
	public function post_link( $url, $post ) {
		return $this->maybe_map_permalink_to_group( $post->ID, $url );
	}

	/**
	 * Map a page link to its group forum
	 *
	 * @param string $url
	 * @param int $post_id
	 * @param $sample
	 * @uses maybe_map_permalink_to_group()
	 * @return string
	 */
	public function page_link( $url, $post_id ) {
		return $this->maybe_map_permalink_to_group( $post_id, $url );
	}

	/**
	 * Map a custom post type link to its group forum
	 *
	 * @param string $url
	 * @param obj $post
	 * @param $leavename
	 * @param $sample
	 * @uses maybe_map_permalink_to_group()
	 * @return string
	 */
	public function post_type_link( $url, $post ) {
		return $this->maybe_map_permalink_to_group( $post->ID, $url );
	}

	/**
	 * Fix pagination of topics on forum view
	 *
	 * @param array $args
	 * @global $wp_rewrite
	 * @uses bbp_get_forum_id()
	 * @uses maybe_map_permalink_to_group
	 * @return array
 	 */
	public function topic_pagination( $args ) {
		$new = $this->maybe_map_permalink_to_group( bbp_get_forum_id() );

		if ( empty( $new ) )
			return $args;

		global $wp_rewrite;

		$args['base'] = trailingslashit( $new ) . $wp_rewrite->pagination_base . '/%#%/';

		return $args;
	}

	/**
	 * Fix pagination of replies on topic view
	 *
	 * @param array $args
	 * @global $wp_rewrite
	 * @uses bbp_get_topic_id()
	 * @uses maybe_map_permalink_to_group
	 * @return array
	 */
	public function replies_pagination( $args ) {
		$new = $this->maybe_map_permalink_to_group( bbp_get_topic_id() );
		if ( empty( $new ) )
			return $args;

		global $wp_rewrite;

		$args['base'] = trailingslashit( $new ) . $wp_rewrite->pagination_base . '/%#%/';

		return $args;
	}

	/**
	 * Ensure that forum content associated with a BuddyPress group can only be
	 * viewed via the group URL.
	 *
	 * @since bbPress (r3802)
	 */
	function redirect_canonical() {

		// Viewing a single forum
		if ( bbp_is_single_forum() ) {
			$forum_id  = get_the_ID();
			$group_ids = bbp_get_forum_group_ids( $forum_id );

		// Viewing a single topic
		} elseif ( bbp_is_single_topic() ) {
			$topic_id  = get_the_ID();
			$slug      = get_post_field( 'post_name', $topic_id );
			$forum_id  = bbp_get_topic_forum_id( $topic_id );
			$group_ids = bbp_get_forum_group_ids( $forum_id );

		// Not a forum or topic
		} else {
			return;
		}

		// Bail if not a group forum
		if ( empty( $group_ids ) )
			return;

		// Use the first group ID
		$group_id 	 = $group_ids[0];
		$group    	 = groups_get_group( array( 'group_id' => $group_id ) );
		$group_link  = trailingslashit( bp_get_group_permalink( $group ) );
		$redirect_to = trailingslashit( $group_link . $this->forums_slug );

		// Add topic slug to URL
		if ( bbp_is_single_topic() ) {
			$redirect_to  = trailingslashit( $redirect_to . $this->topic_slug . '/' . $slug );
		}

		bp_core_redirect( $redirect_to );
	}
}
endif;

/**
 * Creates the Forums component in BuddyPress
 *
 * @since bbPress (r3653)
 *
 * @global type $bp
 * @return If bbPress is not active
 */
function bbp_setup_buddypress_component() {
	global $bp;

	// Bail if no BuddyPress
	if ( !empty( $bp->maintenance_mode ) || !defined( 'BP_VERSION' ) ) return;

	// Bail if BuddyPress Forums are already active
	if ( bp_is_active( 'forums' ) && bp_forums_is_installed_correctly() ) return;

	// Create the new BuddyPress Forums component
	$bp->forums = new BBP_Forums_Component();

	// Register the group extension only if groups are active
	if ( bbp_is_group_forums_active() && bp_is_active( 'groups' ) ) {
		bp_register_group_extension( 'BBP_Forums_Group_Extension' );
	}
}
