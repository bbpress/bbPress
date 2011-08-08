<?php

/**
 * Main bbPress BuddyPress Class
 *
 * @package bbPress
 * @subpackage BuddyPress
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

	/** Variables *************************************************************/

	/**
	 * The name of the BuddyPress component, used in activity streams
	 *
	 * @var string
	 */
	private $component = '';

	/**
	 * Forum Create Activty Action
	 *
	 * @var string
	 */
	private $forum_create = '';

	/**
	 * Topic Create Activty Action
	 *
	 * @var string
	 */
	private $topic_create = '';

	/**
	 * Topic Close Activty Action
	 *
	 * @var string
	 */
	private $topic_close = '';

	/**
	 * Topic Edit Activty Action
	 *
	 * @var string
	 */
	private $topic_edit = '';

	/**
	 * Topic Open Activty Action
	 *
	 * @var string
	 */
	private $topic_open = '';

	/**
	 * Reply Create Activty Action
	 *
	 * @var string
	 */
	private $reply_create = '';

	/**
	 * Reply Edit Activty Action
	 *
	 * @var string
	 */
	private $reply_edit = '';

	/** Functions *************************************************************/

	/**
	 * The main bbPress BuddyPress loader
	 *
	 * @since bbPress (r3395)
	 */
	function __construct() {
		$this->setup_globals();
		$this->setup_actions();
		$this->setup_filters();
	}

	/**
	 * Extension variables
	 *
	 * @since bbPress (r3395)
	 * @access private
	 *
	 * @uses apply_filters() Calls various filters
	 */
	private function setup_globals() {

		// The name of the BuddyPress component, used in activity streams
		$this->component = 'bbpress';

		// Forums
		$this->forum_create = 'bbp_forum_create';

		// Topics
		$this->topic_create = 'bbp_topic_create';
		$this->topic_edit   = 'bbp_topic_edit';
		$this->topic_close  = 'bbp_topic_close';
		$this->topic_open   = 'bbp_topic_open';

		// Replies
		$this->reply_create = 'bbp_topic_create';
		$this->reply_edit   = 'bbp_topic_edit';
	}

	/**
	 * Setup the actions
	 *
	 * @since bbPress (r3395)
	 * @access private
	 *
	 * @uses add_filter() To add various filters
	 * @uses add_action() To add various actions
	 */
	private function setup_actions() {

		/** Activity **********************************************************/

		// Register the activity stream actions
		add_action( 'bp_register_activity_actions', array( $this, 'register_activity_actions' )        );

		// Hook into topic creation
		add_action( 'bbp_new_topic',                array( $this, 'topic_create'              ), 10, 4 );

		// Hook into reply creation
		add_action( 'bbp_new_reply',                array( $this, 'reply_create'              ), 10, 5 );
	}
	
	/**
	 * Setup the filters
	 *
	 * @since bbPress (r3395)
	 * @access private
	 *
	 * @uses add_filter() To add various filters
	 * @uses add_action() To add various actions
	 */
	private function setup_filters() {

		/** Activity **********************************************************/

		// Obey BuddyPress commenting rules
		add_filter( 'bp_activity_can_comment',   array( $this, 'activity_can_comment'   )        );

		// Link directly to the topic or reply
		add_filter( 'bp_activity_get_permalink', array( $this, 'activity_get_permalink' ), 10, 2 );

		/** Profiles **********************************************************/

		// Override bbPress user profile URL with BuddyPress profile URL
		add_filter( 'bbp_pre_get_user_profile_url', array( $this, 'user_profile_url' ) );
	}
	
	/**
	 * Register our activity actions with BuddyPress
	 *
	 * @since bbPress (r3395)
	 *
	 * @uses bp_activity_set_action()
	 */
	public function register_activity_actions() {

		// Topics
		bp_activity_set_action( $this->component, $this->topic_create, __( 'New topic created', 'bbpress' ) );

		// Replies
		bp_activity_set_action( $this->component, $this->reply_create, __( 'New reply created', 'bbpress' ) );
	}

	/**
	 * Wrapper for recoding bbPress actions to the BuddyPress activity stream
	 *
	 * @since bbPress (r3395)
	 *
	 * @param type $args Array of arguments for bp_activity_add()
	 * @uses bbp_get_current_user_id()
	 * @uses bp_core_current_time()
	 * @uses wp_parse_args()
	 * @uses aplly_filters()
	 * @uses bp_activity_add()
	 *
	 * @return type Activity ID if successful, false if not
	 */
	private function record_activity( $args = '' ) {

		// Bail if activity is not active
		if ( !bp_is_active( 'activity' ) )
			return false;

		// Default activity args
		$defaults = array (
			'user_id'           => bbp_get_current_user_id(),
			'type'              => '',
			'action'            => '',
			'item_id'           => '',
			'secondary_item_id' => '',
			'content'           => '',
			'primary_link'      => '',
			'component'         => $this->component,
			'recorded_time'     => bp_core_current_time(),
			'hide_sitewide'     => false
		);

		// Parse the difference
		$activity = wp_parse_args( $args, $defaults );

		// Just in-time filtering of activity stream contents
		$activity = apply_filters( 'bbp_record_activity', $activity );

		// Add the activity
		return bp_activity_add( $activity );
	}

	/**
	 * Wrapper for deleting bbPress actions from BuddyPress activity stream
	 *
	 * @since bbPress (r3395)
	 *
	 * @param type $args Array of arguments for bp_activity_add()
	 * @uses bbp_get_current_user_id()
	 * @uses bp_core_current_time()
	 * @uses wp_parse_args()
	 * @uses aplly_filters()
	 * @uses bp_activity_add()
	 *
	 * @return type Activity ID if successful, false if not
	 */
	public function delete_activity( $args = '' ) {

		// Bail if activity is not active
		if ( !bp_is_active( 'activity' ) )
			return;
		
		// Default activity args
		$defaults = array(
			'item_id'           => false,
			'component'         => $this->component,
			'type'              => false,
			'user_id'           => false,
			'secondary_item_id' => false
		);

		// Parse the differenc
		$activity = wp_parse_args( $args, $defaults );

		// Just in-time filtering of activity stream contents
		$activity = apply_filters( 'bbp_delete_activity', $activity );

		// Delete the activity
		bp_activity_delete_by_item_id( $activity );
	}
	
	/**
	 * Maybe disable activity stream comments on select actions
	 *
	 * @since bbPress (r3399)
	 *
	 * @global BP_Activity_Template $activities_template
	 * @global BuddyPress $bp
	 * @param boolean $can_comment
	 * @uses bp_get_activity_action_name()
	 * @return boolean 
	 */
	public function activity_can_comment( $can_comment = true ) {
		global $activities_template, $bp;

		// Already forced off, so comply
		if ( false === $can_comment )
			return $can_comment;

		// Check if blog & forum activity stream commenting is off
		if ( ( false === $activities_template->disable_blogforum_replies ) || (int) $activities_template->disable_blogforum_replies ) {
			
			// Get the current action name
			$action_name = bp_get_activity_action_name();

			// Setup the array of possibly disabled actions
			$disabled_actions = array(
				$this->topic_create,
				$this->reply_create
			);

			// Check if this activity stream action is disabled
			if ( in_array( $action_name, $disabled_actions ) ) {
				$can_comment = false;
			}
		}

		return $can_comment;
	}

	/**
	 * Maybe link directly to topics and replies in activity stream entries
	 *
	 * @since bbPress (r3399)
	 *
	 * @param string $link
	 * @param mixed $activity_object
	 *
	 * @return string The link to the activity stream item
	 */
	public function activity_get_permalink( $link = '', $activity_object = false ) {

		// Setup the array of actions to link directly to
		$disabled_actions = array(
			$this->topic_create,
			$this->reply_create
		);

		// Check if this activity stream action is directly linked
		if ( in_array( $activity_object->type, $disabled_actions ) ) {
			$link = $activity_object->primary_link;
		}

		return $link;
	}

	/**
	 * Override bbPress profile URL with BuddyPress profile URL
	 * 
	 * @since bbPress (r3401)
	 *
	 * @param string $url
	 * @param int $user_id
	 * @param string $user_nicename
	 *
	 * @return string
	 */
	public function user_profile_url( $user_id ) {
		$profile_url = bp_core_get_user_domain( $user_id );

		return $profile_url;
	}

	/** Topics ****************************************************************/

	/**
	 * Record an activity stream entry when a topic is created
	 *
	 * @since bbPress (r3395)
	 *
	 * @param int $topic_id
	 * @param int $forum_id
	 * @param array $anonymous_data
	 * @param int $topic_author_id
	 *
	 * @uses bbp_get_topic_id()
	 * @uses bbp_get_forum_id()
	 * @uses bbp_get_user_profile_link()
	 * @uses bbp_get_topic_permalink()
	 * @uses bbp_get_topic_title()
	 * @uses bbp_get_topic_content()
	 * @uses bbp_get_forum_permalink()
	 * @uses bbp_get_forum_title()
	 * @uses bp_create_excerpt()
	 * @uses apply_filters()
	 *
	 * @return Bail early if topic is by anonywous user
	 */
	public function topic_create( $topic_id, $forum_id, $anonymous_data, $topic_author_id ) {

		// Bail early if topic is by anonywous user
		if ( !empty( $anonymous_data ) )
			return;

		// Bail if site is private
		if ( !bbp_is_site_public() )
			return;

		// Validate activity data
		$user_id  = $topic_author_id;
		$topic_id = bbp_get_topic_id( $topic_id );
		$forum_id = bbp_get_forum_id( $forum_id );

		// Bail if forum is not public
		if ( !bbp_is_forum_public( $forum_id ) )
			return;

		// User link for topic author
		$user_link  = bbp_get_user_profile_link( $user_id  );

		// Topic
		$topic_permalink = bbp_get_topic_permalink( $topic_id );
		$topic_title     = bbp_get_topic_title    ( $topic_id );
		$topic_content   = bbp_get_topic_content  ( $topic_id );
		$topic_link      = '<a href="' . $topic_permalink . '" title="' . $topic_title . '">' . $topic_title . '</a>';

		// Forum
		$forum_permalink = bbp_get_forum_permalink( $forum_id );
		$forum_title     = bbp_get_forum_title    ( $forum_id );
		$forum_link      = '<a href="' . $forum_permalink . '" title="' . $forum_title . '">' . $forum_title . '</a>';

		// Activity action & text
		$activity_text    = sprintf( __( '%1$s started the topic %2$s in the forum %3$s', 'bbpress' ), $user_link, $topic_link, $forum_link    );
		$activity_action  = apply_filters( 'bbp_activity_topic_create',                $activity_text, $user_id,   $topic_id,   $forum_id      );
		$activity_content = apply_filters( 'bbp_activity_topic_create_excerpt',        bp_create_excerpt( $topic_content ),     $topic_content );

		// Compile the activity stream results
		$activity = array(
			'user_id'           => $user_id,
			'action'            => $activity_action,
			'content'           => $activity_content,
			'primary_link'      => $topic_permalink,
			'type'              => $this->topic_create,
			'item_id'           => $topic_id,
			'secondary_item_id' => $forum_id,
		);

		// Record the activity
		$this->record_activity( $activity );
	}

	/** Replies ***************************************************************/

	/**
	 * Record an activity stream entry when a reply is created
	 *
	 * @since bbPress (r3395)
	 *
	 * @param int $topic_id
	 * @param int $forum_id
	 * @param array $anonymous_data
	 * @param int $topic_author_id
	 *
	 * @uses bbp_get_reply_id()
	 * @uses bbp_get_topic_id()
	 * @uses bbp_get_forum_id()
	 * @uses bbp_get_user_profile_link()
	 * @uses bbp_get_reply_url()
	 * @uses bbp_get_reply_content()
	 * @uses bbp_get_topic_permalink()
	 * @uses bbp_get_topic_title()
	 * @uses bbp_get_forum_permalink()
	 * @uses bbp_get_forum_title()
	 * @uses bp_create_excerpt()
	 * @uses apply_filters()
	 *
	 * @return Bail early if topic is by anonywous user
	 */
	public function reply_create( $reply_id, $topic_id, $forum_id, $anonymous_data, $reply_author_id ) {

		// Do not log activity of anonymous users
		if ( !empty( $anonymous_data ) )
			return;

		// Bail if site is private
		if ( !bbp_is_site_public() )
			return;

		// Validate activity data
		$user_id  = $reply_author_id;
		$reply_id = bbp_get_reply_id( $reply_id );
		$topic_id = bbp_get_topic_id( $topic_id );
		$forum_id = bbp_get_forum_id( $forum_id );

		// Bail if forum is not public
		if ( !bbp_is_forum_public( $forum_id ) )
			return;

		// Setup links for activity stream
		$user_link  = bbp_get_user_profile_link( $user_id  );
		
		// Reply
		$reply_url     = bbp_get_reply_url    ( $reply_id );
		$reply_content = bbp_get_reply_content( $reply_id );

		// Topic
		$topic_permalink = bbp_get_topic_permalink( $topic_id );
		$topic_title     = bbp_get_topic_title    ( $topic_id );
		$topic_link      = '<a href="' . $topic_permalink . '" title="' . $topic_title . '">' . $topic_title . '</a>';

		// Forum
		$forum_permalink = bbp_get_forum_permalink( $forum_id );
		$forum_title     = bbp_get_forum_title    ( $forum_id );
		$forum_link      = '<a href="' . $forum_permalink . '" title="' . $forum_title . '">' . $forum_title . '</a>';

		// Activity action & text
		$activity_text    = sprintf( __( '%1$s replied to the topic %2$s in the forum %3$s', 'bbpress' ), $user_link, $topic_link, $forum_link    );
		$activity_action  = apply_filters( 'bbp_activity_reply_create',         $activity_text, $user_id,   $reply_id,   $topic_id      );
		$activity_content = apply_filters( 'bbp_activity_reply_create_excerpt', bp_create_excerpt( $reply_content ),     $reply_content );

		// Compile the activity stream results
		$activity = array(
			'user_id'           => $user_id,
			'action'            => $activity_action,
			'content'           => $activity_content,
			'primary_link'      => $reply_url,
			'type'              => $this->reply_create,
			'item_id'           => $reply_id,
			'secondary_item_id' => $topic_id,
		);

		// Record the activity
		$this->record_activity( $activity );
	}	
}
endif;

/**
 * Loads BuddyPress inside the bbPress global class
 *
 * @since bbPress (r3395)
 *
 * @global bbPress $bbp
 * @return If bbPress is not active
 */
function bbp_setup_buddypress() {
	global $bbp;

	// Bail if no BuddyPress
	if ( !defined( 'BP_VERSION' ) ) return;

	// Instantiate BuddyPress for bbPress
	$bbp->extend->buddypress = new BBP_BuddyPress();
}

?>
