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
		$this->reply_create = 'bbp_reply_create';
		$this->reply_edit   = 'bbp_reply_edit';
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
		add_action( 'bp_register_activity_actions',      array( $this, 'register_activity_actions' )        );

		// Hook into topic creation
		add_action( 'bbp_new_topic',                     array( $this, 'topic_create'              ), 10, 4 );

		// Hook into reply creation
		add_action( 'bbp_new_reply',                     array( $this, 'reply_create'              ), 10, 5 );

		// Append forum filters in site wide activity streams
		add_action( 'bp_activity_filter_options',        array( $this, 'activity_filter_options'   ), 10    );

		// Append forum filters in single member activity streams
		add_action( 'bp_member_activity_filter_options', array( $this, 'activity_filter_options'   ), 10    );

		// Append forum filters in single group activity streams
		add_action( 'bp_group_activity_filter_options',  array( $this, 'activity_filter_options'   ), 10    );

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
		add_filter( 'bbp_pre_get_user_profile_url',    array( $this, 'user_profile_url'            )        );
		add_filter( 'bbp_get_favorites_permalink',     array( $this, 'get_favorites_permalink'     ), 10, 2 );
		add_filter( 'bbp_get_subscriptions_permalink', array( $this, 'get_subscriptions_permalink' ), 10, 2 );

		/** Mentions **********************************************************/

		// Only link mentions if activity component is active
		if ( bp_is_active( 'activity' ) ) {

			// Convert mentions into links on create
			add_filter( 'bbp_new_topic_pre_content',  'bp_activity_at_name_filter' );
			add_filter( 'bbp_new_reply_pre_content',  'bp_activity_at_name_filter' );

			// Convert mentions into links on edit
			add_filter( 'bbp_edit_topic_pre_content', 'bp_activity_at_name_filter' );
			add_filter( 'bbp_edit_reply_pre_content', 'bp_activity_at_name_filter' );
		}

		// Revert links into text on edit
		add_filter( 'bbp_get_form_topic_content', array( $this, 'strip_mentions_on_edit' ) );
		add_filter( 'bbp_get_form_reply_content', array( $this, 'strip_mentions_on_edit' ) );
	}

	/**
	 * Strip out BuddyPress activity at-name HTML on topic/reply edit
	 *
	 * Copied from bp_forums_strip_mentions_on_post_edit() in case forums
	 * component is not active or is not loaded in yet.
	 *
	 * @since bbPress (r3475)
	 *
	 * @param type $content Optional
	 * @uses bp_get_root_domain()
	 * @uses bp_get_members_root_slug()
	 * @return string
	 */
	public function strip_mentions_on_edit( $content = '' ) {

		// Backwards compat for members root slug
		if ( function_exists( 'bp_get_members_root_slug' ) )
			$members_root = bp_get_members_root_slug();
		elseif ( defined( 'BP_MEMBERS_SLUG' ) )
			$members_root = BP_MEMBERS_SLUG;
		else
			$members_root = 'members';

		$content = htmlspecialchars_decode( $content );
		$pattern = "|<a href=&#039;" . bp_get_root_domain() . "/" . $members_root . "/[A-Za-z0-9-_\.]+/&#039; rel=&#039;nofollow&#039;>(@[A-Za-z0-9-_\.@]+)</a>|";
		$content = preg_replace( $pattern, "$1", $content );

		return $content;
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
	 * @param boolean $can_comment
	 * @uses bp_get_activity_action_name()
	 * @return boolean
	 */
	public function activity_can_comment( $can_comment = true ) {
		global $activities_template;

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
	 * Append forum options to activity filter select box
	 *
	 * @since bbPress (r3653)
	 */
	function activity_filter_options() {
	?>

		<option value="<?php echo $this->topic_create; ?>"><?php _e( 'Topics',  'bbpress' ); ?></option>
		<option value="<?php echo $this->reply_create; ?>"><?php _e( 'Replies', 'bbpress' ); ?></option>

	<?php
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
	 *
	 * @param string $url
	 * @param int $user_id
	 *
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
	 *
	 * @param string $url
	 * @param int $user_id
	 *
	 * @return string
	 */
	public function get_subscriptions_permalink( $url, $user_id ) {
		$url = trailingslashit( bp_core_get_user_domain( $user_id ) . 'forums/subscriptions' );
		return $url;
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

		// Bail if user is not active
		if ( bbp_is_user_inactive( $user_id ) )
			return;

		// Bail if topic is not published
		if ( !bbp_is_topic_published( $topic_id ) )
			return;

		// Bail if forum is not public
		if ( !bbp_is_forum_public( $forum_id, false ) )
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
		$activity_id = $this->record_activity( $activity );

		// Add the activity entry ID as a meta value to the topic
		if ( !empty( $activity_id ) ) {
			update_post_meta( $topic_id, '_bbp_activity_id', $activity_id );
		}
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

		// Bail if user is not active
		if ( bbp_is_user_inactive( $user_id ) )
			return;

		// Bail if forum is not public
		if ( !bbp_is_forum_public( $forum_id, false ) )
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
		$activity_id = $this->record_activity( $activity );

		// Add the activity entry ID as a meta value to the reply
		if ( !empty( $activity_id ) ) {
			update_post_meta( $reply_id, '_bbp_activity_id', $activity_id );
		}
	}
}
endif;


if ( !class_exists( 'BBP_Forums_Component' ) ) :
/**
 * Loads Forums Component
 *
 * @since bbPress (r3552)
 *
 * @package bbPress
 * @subpackage BuddyPress
 */
class BBP_Forums_Component extends BP_Component {

	/**
	 * Start the forums component creation process
	 *
	 * @since bbPress (r3552)
	 */
	function __construct() {
		parent::start(
			'forums',
			__( 'Forums', 'bbpress' ),
			BP_PLUGIN_DIR
		);
		$this->setup_globals();
		$this->setup_nav();
	}

	/**
	 * Setup globals
	 *
	 * The BP_FORUMS_SLUG constant is deprecated, and only used here for
	 * backwards compatibility.
	 *
	 * @since bbPress (r3552)
	 * @global obj $bp
	 */
	function setup_globals() {
		global $bp;

		// Define the parent forum ID
		if ( !defined( 'BP_FORUMS_PARENT_FORUM_ID' ) )
			define( 'BP_FORUMS_PARENT_FORUM_ID', 1 );

		// Define a slug, if necessary
		if ( !defined( 'BP_FORUMS_SLUG' ) )
			define( 'BP_FORUMS_SLUG', $this->id );

		// All globals for messaging component.
		$globals = array(
			'path'                  => BP_PLUGIN_DIR,
			'slug'                  => BP_FORUMS_SLUG,
			'root_slug'             => isset( $bp->pages->forums->slug ) ? $bp->pages->forums->slug : BP_FORUMS_SLUG,
			'has_directory'         => false,
			'notification_callback' => 'messages_format_notifications',
			'search_string'         => __( 'Search Forums...', 'bbpress' ),
		);

		parent::setup_globals( $globals );
	}

	/**
	 * Setup BuddyBar navigation
	 *
	 * @since bbPress (r3552)
	 *
	 * @global obj $bp
	 */
	function setup_nav() {
		global $bp;

		// Stop if there is no user displayed or logged in
		if ( !is_user_logged_in() && !isset( $bp->displayed_user->id ) )
			return;

		// Define local variable(s)
		$sub_nav     = array();
		$user_domain = '';

		// Add 'Forums' to the main navigation
		$main_nav = array(
			'name'                => __( 'Forums', 'bbpress' ),
			'slug'                => $this->slug,
			'position'            => 80,
			'screen_function'     => 'bbp_member_forums_screen_topics',
			'default_subnav_slug' => 'topics',
			'item_css_id'         => $this->id
		);

		// Determine user to use
		if ( isset( $bp->displayed_user->domain ) )
			$user_domain = $bp->displayed_user->domain;
		elseif ( isset( $bp->loggedin_user->domain ) )
			$user_domain = $bp->loggedin_user->domain;
		else
			return;

		// User link
		$forums_link = trailingslashit( $user_domain . $this->slug );

		// Topics started
		$sub_nav[] = array(
			'name'            => __( 'Topics Started', 'bbpress' ),
			'slug'            => 'topics',
			'parent_url'      => $forums_link,
			'parent_slug'     => $this->slug,
			'screen_function' => 'bbp_member_forums_screen_topics',
			'position'        => 20,
			'item_css_id'     => 'topics'
		);

		// Replies to topics
		$sub_nav[] = array(
			'name'            => __( 'Topics Replied To', 'bbpress' ),
			'slug'            => 'replies',
			'parent_url'      => $forums_link,
			'parent_slug'     => $this->slug,
			'screen_function' => 'bbp_member_forums_screen_replies',
			'position'        => 40,
			'item_css_id'     => 'replies'
		);

		// Favorite topics
		$sub_nav[] = array(
			'name'            => __( 'Favorites', 'bbpress' ),
			'slug'            => 'favorites',
			'parent_url'      => $forums_link,
			'parent_slug'     => $this->slug,
			'screen_function' => 'bbp_member_forums_screen_favorites',
			'position'        => 60,
			'item_css_id'     => 'favorites'
		);

		// Subscribed topics (my profile only)
		if ( bp_is_my_profile() ) {
			$sub_nav[] = array(
				'name'            => __( 'Subscriptions', 'bbpress' ),
				'slug'            => 'subscriptions',
				'parent_url'      => $forums_link,
				'parent_slug'     => $this->slug,
				'screen_function' => 'bbp_member_forums_screen_subscriptions',
				'position'        => 60,
				'item_css_id'     => 'subscriptions'
			);
		}

		parent::setup_nav( $main_nav, $sub_nav );
	}

	/**
	 * Set up the admin bar
	 *
	 * @since bbPress (r3552)
	 *
	 * @global obj $bp
	 */
	function setup_admin_bar() {
		global $bp;

		// Prevent debug notices
		$wp_admin_nav = array();

		// Menus for logged in user
		if ( is_user_logged_in() ) {

			// Setup the logged in user variables
			$user_domain = $bp->loggedin_user->domain;
			$forums_link = trailingslashit( $user_domain . $this->slug );

			// Add the "My Account" sub menus
			$wp_admin_nav[] = array(
				'parent' => $bp->my_account_menu_id,
				'id'     => 'my-account-' . $this->id,
				'title'  => __( 'Forums', 'bbpress' ),
				'href'   => trailingslashit( $forums_link )
			);

			// Topics
			$wp_admin_nav[] = array(
				'parent' => 'my-account-' . $this->id,
				'id'     => 'my-account-' . $this->id . '-topics',
				'title'  => __( 'Topics Started', 'bbpress' ),
				'href'   => trailingslashit( $forums_link . 'topics' )
			);

			// Replies
			$wp_admin_nav[] = array(
				'parent' => 'my-account-' . $this->id,
				'id'     => 'my-account-' . $this->id . '-replies',
				'title'  => __( 'Topics Replied To', 'bbpress' ),
				'href'   => trailingslashit( $forums_link . 'replies' )
			);

			// Favorites
			$wp_admin_nav[] = array(
				'parent' => 'my-account-' . $this->id,
				'id'     => 'my-account-' . $this->id . '-favorites',
				'title'  => __( 'Favorite Topics', 'bbpress' ),
				'href'   => trailingslashit( $forums_link . 'favorites' )
			);

			// Subscriptions
			$wp_admin_nav[] = array(
				'parent' => 'my-account-' . $this->id,
				'id'     => 'my-account-' . $this->id . '-subscriptions',
				'title'  => __( 'Subscribed Topics', 'bbpress' ),
				'href'   => trailingslashit( $forums_link . 'subscriptions' )
			);
		}

		parent::setup_admin_bar( $wp_admin_nav );
	}

	/**
	 * Sets up the title for pages and <title>
	 *
	 * @since bbPress (r3552)
	 *
	 * @global obj $bp
	 */
	function setup_title() {
		global $bp;

		// Adjust title based on view
		if ( bp_is_forums_component() ) {
			if ( bp_is_my_profile() ) {
				$bp->bp_options_title = __( 'Forums', 'buddypress' );
			} else {
				$bp->bp_options_avatar = bp_core_fetch_avatar( array(
					'item_id' => $bp->displayed_user->id,
					'type'    => 'thumb'
				) );
				$bp->bp_options_title = $bp->displayed_user->fullname;
			}
		}

		parent::setup_title();
	}
}
endif;

if ( !class_exists( 'BBP_Forums_Group_Extension' ) && class_exists( 'BP_Group_Extension' ) ) :
/**
 * Loads Group Extension for Forums Component
 *
 * @since bbPress (r3552)
 *
 * @package bbPress
 * @subpackage BuddyPress
 * @todo Everything
 */
class BBP_Forums_Group_Extension extends BP_Group_Extension {

	/**
	 * Setup bbPress group extension variables
	 *
	 * @since bbPress (r3552)
	 *
	 */
	function __construct() {

		// Name and slug
		$this->name          = bbp_get_forum_archive_title();
		$this->nav_item_name = bbp_get_forum_archive_title();
		$this->slug          = 'forums';
		$this->topic_slug    = 'topic';
		$this->reply_slug    = 'reply';

		// Forum component is visible @todo configure?
		$this->visibility = 'public';

		// Set positions towards end
		$this->create_step_position = 15;
		$this->nav_item_position    = 10;

		// Allow create step and show in nav
		$this->enable_create_step   = true;
		$this->enable_nav_item      = true;
		$this->enable_edit_item     = true;

		// I forget what these do
		$this->display_hook         = 'bp_template_content';
		$this->template_file        = 'groups/single/plugins';

		// Add handlers to bp_actions
		add_action( 'bp_actions', 'bbp_new_forum_handler'  );
		add_action( 'bp_actions', 'bbp_new_topic_handler'  );
		add_action( 'bp_actions', 'bbp_new_reply_handler'  );
		add_action( 'bp_actions', 'bbp_edit_forum_handler' );
		add_action( 'bp_actions', 'bbp_edit_topic_handler' );
		add_action( 'bp_actions', 'bbp_edit_reply_handler' );

		// Tweak the redirect field
		add_filter( 'bbp_new_topic_redirect_to', array( $this, 'new_topic_redirect_to' ), 10, 3 );
		add_filter( 'bbp_new_reply_redirect_to', array( $this, 'new_reply_redirect_to' ), 10, 2 );
	}

	function display() {

		// Map forum permalinks to current group
		add_filter( 'bbp_get_forum_permalink', array( $this, 'map_forum_permalink_to_group' ), 10, 2 );
		add_filter( 'bbp_get_topic_permalink', array( $this, 'map_topic_permalink_to_group' ), 10, 2 );
		add_filter( 'bbp_get_reply_permalink', array( $this, 'map_reply_permalink_to_group' ), 10, 2 );

		// Prevent Topic Parent from appearing
		add_action( 'bbp_theme_before_topic_form_forum', array( $this, 'ob_start'     ) );
		add_action( 'bbp_theme_after_topic_form_forum',  array( $this, 'ob_end_clean' ) );
		add_action( 'bbp_theme_after_topic_form_forum',  array( $this, 'topic_parent' ) );

		// Prevent Forum Parent from appearing
		add_action( 'bbp_theme_before_forum_form_parent', array( $this, 'ob_start'     ) );
		add_action( 'bbp_theme_after_forum_form_parent',  array( $this, 'ob_end_clean' ) );
		add_action( 'bbp_theme_after_forum_form_parent',  array( $this, 'forum_parent' ) );

		// Hide breadcrumb
		add_filter( 'bbp_no_breadcrumb', '__return_true' );

		$this->display_forums( 0 );
	}

	/**
	 * Used to start an output buffer
	 */
	public function ob_start() {
		ob_start();
	}

	/**
	 * Used to end an output buffer
	 */
	public function ob_end_clean() {
		ob_end_clean();
	}

	/**
	 * Map a forum permalink to the current group
	 *
	 * @sunce bbPress (rxxxx)
	 *
	 * @param string $url
	 * @param int $forum_id
	 * @return string
	 */
	public function map_forum_permalink_to_group( $url, $forum_id ) {
		$slug  = get_post_field( 'post_name', $forum_id );
		$group = groups_get_group( array( 'group_id' => bp_get_current_group_id() ) );

		if ( bp_is_group_admin_screen( $this->slug ) ) {
			$group_permalink = bp_get_group_admin_permalink( $group );
		} else {
			$group_permalink = bp_get_group_permalink( $group );
		}

		$url = trailingslashit( $group_permalink . $this->slug . '/' . $slug );

		return $url;
	}

	/**
	 * Map a topic permalink to the current group forum
	 *
	 * @sunce bbPress (rxxxx)
	 *
	 * @param string $url
	 * @param int $forum_id
	 * @return string
	 */
	public function map_topic_permalink_to_group( $url, $topic_id ) {
		$slug  = get_post_field( 'post_name', $topic_id );
		$group = groups_get_group( array( 'group_id' => bp_get_current_group_id() ) );

		if ( bp_is_group_admin_screen( $this->slug ) ) {
			$group_permalink = bp_get_group_admin_permalink( $group );
		} else {
			$group_permalink = bp_get_group_permalink( $group );
		}

		$url = trailingslashit( $group_permalink . $this->slug . '/' . $this->topic_slug . '/' . $slug );

		return $url;
	}

	/**
	 * Map a topic permalink to the current group forum
	 *
	 * @sunce bbPress (rxxxx)
	 *
	 * @param string $url
	 * @param int $forum_id
	 * @return string
	 */
	public function map_reply_permalink_to_group( $url, $topic_id ) {
		$slug  = get_post_field( 'post_name', $topic_id );
		$group = groups_get_group( array( 'group_id' => bp_get_current_group_id() ) );

		if ( bp_is_group_admin_screen( $this->slug ) ) {
			$group_permalink = bp_get_group_admin_permalink( $group );
		} else {
			$group_permalink = bp_get_group_permalink( $group );
		}

		$url = trailingslashit( $group_permalink . $this->slug . '/' . $this->reply_slug . '/' . $slug );

		return $url;
	}

	/** Edit ******************************************************************/

	/**
	 * Show forums and new forum form when editing a group
	 *
	 * @since bbPress (r3563)
	 *
	 * @uses bbp_get_template_part()
	 */
	function edit_screen() {

		// Map forum permalinks to current group
		add_filter( 'bbp_get_forum_permalink', array( $this, 'map_forum_permalink_to_group' ), 10, 2 );
		add_filter( 'bbp_get_topic_permalink', array( $this, 'map_topic_permalink_to_group' ), 10, 2 );
		add_filter( 'bbp_get_reply_permalink', array( $this, 'map_reply_permalink_to_group' ), 10, 2 );

		// Add group admin actions to forum row actions
		add_action( 'bbp_forum_row_actions', array( $this, 'forum_row_actions' ) );
		add_action( 'bbp_topic_row_actions', array( $this, 'topic_row_actions' ) );

		// Prevent Topic Parent from appearing
		add_action( 'bbp_theme_before_topic_form_forum', array( $this, 'ob_start'     ) );
		add_action( 'bbp_theme_after_topic_form_forum',  array( $this, 'ob_end_clean' ) );
		add_action( 'bbp_theme_after_topic_form_forum',  array( $this, 'topic_parent' ) );

		// Prevent Forum Parent from appearing
		add_action( 'bbp_theme_before_forum_form_parent', array( $this, 'ob_start'     ) );
		add_action( 'bbp_theme_after_forum_form_parent',  array( $this, 'ob_end_clean' ) );
		add_action( 'bbp_theme_after_forum_form_parent',  array( $this, 'forum_parent' ) );

		// Do not show the new topic form in the moderation area
		add_filter( 'bbp_current_user_can_access_create_topic_form', '__return_false' );

		// Hide breadcrumb
		add_filter( 'bbp_no_breadcrumb', '__return_true' );

		$this->display_forums( 1 );
	}

	/**
	 * Save the Group Forum data on edit
	 *
	 * @since bbPress (r3465)
	 *
	 * @uses bbp_new_forum_handler() To check for forum creation
	 * @uses bbp_edit_forum_handler() To check for forum edit
	 */
	function edit_screen_save() {

		// Bail if not a POST action
		if ( 'POST' !== strtoupper( $_SERVER['REQUEST_METHOD'] ) )
			return;

		// Bail if action is empty
		if ( empty( $_POST['action'] ) )
			return;

		// Handle the different actions that can happen here
		switch ( $_POST['action'] ) {

			// New forum
			case 'bbp-new-forum' :

				// Redirect back here, not to the new forum
				add_filter( 'bbp_new_forum_redirect_to',   array( $this, 'edit_redirect_to' )        );

				// Add actions to bbp_new_forum
				add_action( 'bbp_new_forum',               array( $this, 'new_forum'        ), 10, 4 );

				// Handle the new forum
				bbp_new_forum_handler();

				break;

			// Edit existing forum
			case 'bbp-edit-forum' :

				// Redirect back here, not to the new forum
				add_filter( 'bbp_edit_forum_redirect_to',  array( $this, 'edit_redirect_to' )        );

				// Handle the forum edit
				bbp_edit_forum_handler();

				break;

			// Trash a forum
			case 'bbp-trash-forum' :
				//bbp_trash_forum_handler();
				break;

			// Permanently delet a forum
			case 'bbp-delete-forum' :
				//bbp_delete_forum_handler();
				break;
		}
	}

	/** Create ****************************************************************/

	/**
	 * Show forums and new forum form when creating a group
	 *
	 * @since bbPress (r3465)
	 *
	 * @todo Everything
	 */
	function create_screen() {

		// Bail if not looking at this screen
		if ( !bp_is_group_creation_step( $this->slug ) )
			return false;

		$checked = bp_get_new_group_enable_forum() || groups_get_groupmeta( bp_get_new_group_id(), 'forum_id' ); ?>

		<h4><?php _e( 'Group Forums', 'buddypress' ); ?></h4>

		<p><?php _e( 'Create a discussion forum to allow members of this group to communicate in a structured, bulletin-board style fashion.', 'buddypress' ); ?></p>

		<div class="checkbox">
			<label><input type="checkbox" name="bbp-create-group-forum" id="bbp-create-group-forum" value="1"<?php checked( $checked ); ?> /> <?php _e( 'Yes. I want this group to have a forum.', 'buddypress' ); ?></label>
		</div>

		<?php

		// Verify intent
		wp_nonce_field( 'groups_create_save_' . $this->slug );
	}

	/**
	 * Save the Group Forum data on create
	 *
	 * @since bbPress (r3465)
	 *
	 * @todo Everything
	 */
	function create_screen_save() {

		check_admin_referer( 'groups_create_save_' . $this->slug );

		$create_forum = !empty( $_POST['bbp-create-group-forum'] ) ? true : false;
		$forum_id     = 0;
		$forum_ids    = bbp_get_group_forum_ids( bp_get_new_group_id() );
		if ( !empty( $forum_ids ) )
			$forum_id = (int) is_array( $forum_ids ) ? $forum_ids[0] : $forum_ids;

		// Create a forum, or not
		switch ( $create_forum ) {
			case true  :

				// Bail if initial content was already created
				if ( !empty( $forum_id ) )
					return;

				// Set the default forum status
				switch ( bp_get_new_group_status() ) {
					case 'hidden'  :
						$status = bbp_get_hidden_status_id();
						break;
					case 'private' :
						$status = bbp_get_private_status_id();
						break;
					case 'public'  :
					default        :
						$status = bbp_get_public_status_id();
						break;
				}

				// Create the initial forum
				$forum_id = bbp_insert_forum( array(
					'post_parent'  => bbp_get_group_forums_root_id(),
					'post_title'   => bp_get_new_group_name(),
					'post_content' => bp_get_new_group_description(),
					'post_status'  => $status
				) );

				// Run the BP-specific functions for new groups 
				$this->new_forum( array( 'forum_id' => $forum_id ) ); 

				break;
			case false :

				// Forum was created but is now being undone
				if ( !empty( $forum_id ) ) {
					wp_delete_post( $forum_id, true );
					groups_delete_groupmeta( bp_get_new_group_id(), 'forum_id' );
				}

				break;
		}
	}

	/**
	 * Creating a group forum or category (including root for group)
	 *
	 * @since bbPress (r3653)
	 * @param type $forum_args
	 * @uses bbp_get_forum_id()
	 * @uses bp_get_current_group_id()
	 * @uses bbp_add_forum_id_to_group()
	 * @uses bbp_add_group_id_to_forum()
	 * @return if no forum_id is available
	 */
	public function new_forum( $forum_args = array() ) {

		// Bail if no forum_id was passed
		if ( empty( $forum_args['forum_id'] ) )
			return;

		// Validate forum_id
		$forum_id = bbp_get_forum_id( $forum_args['forum_id'] );
		$group_id = bp_get_current_group_id();

		bbp_add_forum_id_to_group( $group_id, $forum_id );
		bbp_add_group_id_to_forum( $forum_id, $group_id );
	}

	/**
	 * Removing a group forum or category (including root for group)
	 *
	 * @since bbPress (r3653)
	 * @param type $forum_args
	 * @uses bbp_get_forum_id()
	 * @uses bp_get_current_group_id()
	 * @uses bbp_add_forum_id_to_group()
	 * @uses bbp_add_group_id_to_forum()
	 * @return if no forum_id is available
	 */
	public function remove_forum( $forum_args = array() ) {

		// Bail if no forum_id was passed
		if ( empty( $forum_args['forum_id'] ) )
			return;

		// Validate forum_id
		$forum_id = bbp_get_forum_id( $forum_args['forum_id'] );
		$group_id = bp_get_current_group_id();

		bbp_remove_forum_id_from_group( $group_id, $forum_id );
		bbp_remove_group_id_from_forum( $forum_id, $group_id );
	}

	/** Display Methods *******************************************************/

	/**
	 * Output the forums for a group in the edit screens
	 *
	 * @since bbPress (r3653)
	 * @uses bp_get_current_group_id()
	 * @uses bbp_get_group_forum_ids()
	 * @uses bbp_has_forums()
	 * @uses bbp_get_template_part()
	 */
	public function display_forums( $offset = 0 ) {
		global $wpdb;

		$bbp = bbpress();

		// Forum data
		$forum_ids  = bbp_get_group_forum_ids( bp_get_current_group_id() );
		$forum_args = array( 'post__in' => $forum_ids, 'post_parent' => null ); ?>

		<div id="bbpress-forums">

			<?php

			// Looking at the group forum root
			if ( ! bp_action_variable( $offset ) ) :

				// Query forums and show them if
				if ( !empty( $forum_ids ) && bbp_has_forums( $forum_args ) ) :

					bbp_the_forum();

					// Only one forum found
					if ( $bbp->forum_query->post_count == 1 ) :?>

						<h3><?php _e( 'Forum', 'bbpress' ); ?></h3>

						<?php bbp_set_query_name( 'bbp_single_forum' ); ?>

						<?php if ( bbp_has_topics( array( 'post_parent' => bbp_get_forum_id() ) ) ) : ?>

							<?php bbp_get_template_part( 'pagination', 'topics'    ); ?>

							<?php bbp_get_template_part( 'loop',       'topics'    ); ?>

							<?php bbp_get_template_part( 'pagination', 'topics'    ); ?>

							<?php bbp_get_template_part( 'form',       'topic'     ); ?>

						<?php else : ?>

							<?php bbp_get_template_part( 'feedback',   'no-topics' ); ?>

							<?php bbp_get_template_part( 'form',       'topic'     ); ?>

						<?php endif;

					// More than 1 forum found
					elseif ( $bbp->forum_query->post_count > 1 ) : ?>

						<h3><?php _e( 'Forums', 'bbpress' ); ?></h3>

						<?php bbp_get_template_part( 'loop', 'forums' ); ?>

						<h3><?php _e( 'Topics', 'bbpress' ); ?></h3>

						<?php if ( bbp_has_topics( array( 'post_parent__in' => $forum_ids ) ) ) : ?>

							<?php bbp_get_template_part( 'pagination', 'topics'    ); ?>

							<?php bbp_get_template_part( 'loop',       'topics'    ); ?>

							<?php bbp_get_template_part( 'pagination', 'topics'    ); ?>

							<?php bbp_get_template_part( 'form',       'topic'     ); ?>

						<?php else : ?>

							<?php bbp_get_template_part( 'feedback',   'no-topics' ); ?>

							<?php bbp_get_template_part( 'form',       'topic'     ); ?>

						<?php endif;

					// No forums found
					else : ?>

						<div id="message" class="info">
							<p><?php _e( 'This group does not currently have any forums.', 'bbpress' ); ?></p>
						</div>

						<?php if ( bp_is_group_admin_screen( $this->slug ) ) :
							bbp_get_template_part( 'form', 'forum' );
						endif;
					endif;

				// No forums found
				else : ?>

					<div id="message" class="info">
						<p><?php _e( 'This group does not currently have any forums.', 'bbpress' ); ?></p>
					</div>

					<?php if ( bp_is_group_admin_screen( $this->slug ) ) :
						bbp_get_template_part( 'form', 'forum' );
					endif;
				endif;

			// Single forum
			elseif ( ( bp_action_variable( $offset ) != $this->slug ) && ( bp_action_variable( $offset ) != $this->topic_slug ) ) :

				// Get the forum
				$forum_post_type = bbp_get_forum_post_type();
				$forum_slug      = bp_action_variable( $offset );
				$forums          = $wpdb->get_row( "SELECT ID FROM {$wpdb->posts} WHERE post_name = '{$forum_slug}' AND post_type = '{$forum_post_type}'", ARRAY_N );

				// Forum exists
				if ( !empty( $forums ) ) :
					$forum_id              = $forums[0];
					$bbp->current_forum_id = $forum_id;
					bbp_set_query_name( 'bbp_single_forum' ); ?>

					<h3><?php bbp_forum_title(); ?></h3>

					<?php bbp_get_template_part( 'content', 'single-forum' ); ?>

				<?php else : ?>

					<?php bbp_get_template_part( 'feedback', 'no-topics'   ); ?>

					<?php bbp_get_template_part( 'form',     'topic'       ); ?>

				<?php endif;

			// Single topic
			elseif ( ( bp_action_variable( $offset ) != $this->slug ) && ( bp_action_variable( $offset ) == $this->topic_slug ) ) :

				// Get the topic
				$topic_post_type = bbp_get_topic_post_type();
				$topic_slug      = bp_action_variable( $offset + 1 );
				$topics          = $wpdb->get_row( "SELECT ID FROM {$wpdb->posts} WHERE post_name = '{$topic_slug}' AND post_type = '{$topic_post_type}'", ARRAY_N );

				// Topic exists
				if ( !empty( $topics ) ) :
					$topic_id              = $topics[0];
					$bbp->current_topic_id = $topic_id;
					bbp_set_query_name( 'bbp_single_topic' ); ?>

					<h3><?php bbp_topic_title(); ?></h3>

					<?php bbp_get_template_part( 'content', 'single-topic' ); ?>

				<?php else : ?>

					<?php bbp_get_template_part( 'feedback', 'no-topics'   ); ?>

					<?php bbp_get_template_part( 'form',     'topic'       ); ?>

				<?php endif;

			endif; ?>

		</div>

		<?php
	}

	/**
	 * Add forum row action HTML when viewing group forum admin
	 *
	 * @since bbPress (r3653)
	 *
	 * @uses bp_is_item_admin()
	 * @uses bbp_get_forum_id()
	 */
	public function forum_row_actions() {

		// Only admins can take actions on forums
		if ( is_super_admin() || current_user_can( 'moderate' ) || bp_is_item_admin() || bp_is_item_mod() ) : ?>

		<div class="row-actions">

			<?php echo 'Edit | View | Trash'; ?>

		</div>

		<?php endif;
	}

	/**
	 * Add topic row action HTML when viewing group forum admin
	 *
	 * @since bbPress (r3653)
	 *
	 * @uses bp_is_item_admin()
	 * @uses bbp_get_forum_id()
	 */
	public function topic_row_actions() {

		// Only admins can take actions on forums
		if ( is_super_admin() || current_user_can( 'moderate' ) || bp_is_item_admin() || bp_is_item_mod() ) : ?>

		<div class="row-actions">

			<?php echo 'Edit | View | Trash | Close | Stick (To Front) | Spam'; ?>

		</div>

		<?php endif;
	}

	/** Redirect Helpers ******************************************************/

	/**
	 * Redirect to the group forum screen
	 *
	 * @since bbPress (r3653)
	 * @param str $redirect_url
	 * @param str $redirect_to
	 */
	public function new_topic_redirect_to( $redirect_url = '', $redirect_to = '', $topic_id = 0 ) {
		if ( bp_is_group() ) {
			$topic        = bbp_get_topic( $topic_id );
			$topic_hash   = '#post-' . $topic_id;
			$redirect_url = trailingslashit( bp_get_group_permalink( groups_get_current_group() ) ) . trailingslashit( $this->slug ) . trailingslashit( $this->topic_slug ) . trailingslashit( $topic->post_name ) . $topic_hash;
		}

		return $redirect_url;
	}

	/**
	 * Redirect to the group forum screen
	 *
	 * @since bbPress (r3653)
	 */
	public function new_reply_redirect_to( $redirect_url = '', $redirect_to = '', $reply_id = 0 ) {
		global $wp_rewrite;

		if ( bp_is_group() ) {
			$topic_id       = bbp_get_reply_topic_id( $reply_id );
			$topic          = bbp_get_topic( $topic_id );
			$reply_position = bbp_get_reply_position( $reply_id, $topic_id );
			$reply_page     = ceil( (int) $reply_position / (int) bbp_get_replies_per_page() );
			$reply_hash     = '#post-' . $reply_id;
			$topic_url      = trailingslashit( bp_get_group_permalink( groups_get_current_group() ) ) . trailingslashit( $this->slug ) . trailingslashit( $this->topic_slug ) . trailingslashit( $topic->post_name );

			// Don't include pagination if on first page
			if ( 1 >= $reply_page ) {
				$redirect_url = trailingslashit( $topic_url ) . $reply_hash;

			// Include pagination
			} else {
				$redirect_url = trailingslashit( $topic_url ) . trailingslashit( $wp_rewrite->pagination_base ) . trailingslashit( $reply_page ) . $reply_hash;
			}

			// Add topic view query arg back to end if it is set
			if ( bbp_get_view_all() ) {
				$redirect_url = bbp_add_view_all( $redirect_url );
			}
		}

		return $redirect_url;
	}

	/**
	 * Redirect to the group admin forum edit screen
	 *
	 * @since bbPress (r3653)
	 *
	 * @uses groups_get_current_group()
	 * @uses bp_is_group_admin_screen()
	 * @uses trailingslashit()
	 * @uses bp_get_root_domain()
	 * @uses bp_get_groups_root_slug()
	 */
	public function edit_redirect_to( $redirect_url = '' ) {

		// Get the current group, if there is one
		$group = groups_get_current_group();

		// If this is a group of any kind, empty out the redirect URL
		if ( bp_is_group_admin_screen( $this->slug ) )
			$redirect_url = trailingslashit( bp_get_root_domain() . '/' . bp_get_groups_root_slug() . '/' . $group->slug . '/admin/' . $this->slug );

		return $redirect_url;
	}

	public function forum_parent() {
	?>

		<input type="hidden" name="bbp_forum_parent_id" id="bbp_forum_parent_id" value="<?php bbp_group_forums_root_id(); ?>" />

	<?php
	}

	public function topic_parent() {

		$forum_ids = bbp_get_group_forum_ids( bp_get_current_group_id() );
	?>

		<p>
			<label for="bbp_forum_id"><?php _e( 'Forum:', 'bbpress' ); ?></label><br />
			<?php bbp_dropdown( array( 'include' => $forum_ids, 'selected' => bbp_get_form_topic_forum() ) ); ?>
		</p>

	<?php
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

/** BuddyPress Helpers ********************************************************/

/**
 * Filter the current bbPress user ID with the current BuddyPress user ID
 *
 * @since bbPress (r3552)
 *
 * @global BuddyPress $bp
 * @param int $user_id
 * @param bool $displayed_user_fallback
 * @param bool $current_user_fallback
 * @return int User ID
 */
function bbp_filter_user_id( $user_id = 0, $displayed_user_fallback = true, $current_user_fallback = false ) {

	// Define local variable
	$bbp_user_id = 0;

	// Get possible user ID's
	$did = bp_displayed_user_id();
	$lid = bp_loggedin_user_id();

	// Easy empty checking
	if ( !empty( $user_id ) && is_numeric( $user_id ) )
		$bbp_user_id = $user_id;

	// Currently viewing or editing a user
	elseif ( ( true == $displayed_user_fallback ) && !empty( $did ) )
		$bbp_user_id = $did;

	// Maybe fallback on the current_user ID
	elseif ( ( true == $current_user_fallback ) && !empty( $lid ) )
		$bbp_user_id = $lid;

	return $bbp_user_id;
}
add_filter( 'bbp_get_user_id', 'bbp_filter_user_id', 10, 3 );

/**
 * Filter the bbPress is_single_user function with BuddyPress eqivalent
 *
 * @since bbPress (r3552)
 *
 * @param bool $is Optional. Default false
 * @return bool True if viewing single user, false if not
 */
function bbp_filter_is_single_user( $is = false ) {
	if ( !empty( $is ) )
		return $is;

	return bp_is_user();
}
add_filter( 'bbp_is_single_user', 'bbp_filter_is_single_user', 10, 1 );

/**
 * Filter the bbPress is_user_home function with BuddyPress eqivalent
 *
 * @since bbPress (r3552)
 *
 * @param bool $is Optional. Default false
 * @return bool True if viewing single user, false if not
 */
function bbp_filter_is_user_home( $is = false ) {
	if ( !empty( $is ) )
		return $is;

	return bp_is_my_profile();
}
add_filter( 'bbp_is_user_home', 'bbp_filter_is_user_home', 10, 1 );

/** BuddyPress Screens ********************************************************/

/**
 * Hook bbPress topics template into plugins template
 *
 * @since bbPress (r3552)
 *
 * @uses add_action() To add the content hook
 * @uses bp_core_load_template() To load the plugins template
 */
function bbp_member_forums_screen_topics() {
	add_action( 'bp_template_content', 'bbp_member_forums_topics_content' );
	bp_core_load_template( apply_filters( 'bbp_member_forums_screen_topics', 'members/single/plugins' ) );
}

/**
 * Hook bbPress replies template into plugins template
 *
 * @since bbPress (r3552)
 *
 * @uses add_action() To add the content hook
 * @uses bp_core_load_template() To load the plugins template
 */
function bbp_member_forums_screen_replies() {
	add_action( 'bp_template_content', 'bbp_member_forums_replies_content' );
	bp_core_load_template( apply_filters( 'bbp_member_forums_screen_replies', 'members/single/plugins' ) );
}

/**
 * Hook bbPress favorites template into plugins template
 *
 * @since bbPress (r3552)
 *
 * @uses add_action() To add the content hook
 * @uses bp_core_load_template() To load the plugins template
 */
function bbp_member_forums_screen_favorites() {
	add_action( 'bp_template_content', 'bbp_member_forums_favorites_content' );
	bp_core_load_template( apply_filters( 'bbp_member_forums_screen_favorites', 'members/single/plugins' ) );
}

/**
 * Hook bbPress subscriptions template into plugins template
 *
 * @since bbPress (r3552)
 *
 * @uses add_action() To add the content hook
 * @uses bp_core_load_template() To load the plugins template
 */
function bbp_member_forums_screen_subscriptions() {
	add_action( 'bp_template_content', 'bbp_member_forums_subscriptions_content' );
	bp_core_load_template( apply_filters( 'bbp_member_forums_screen_subscriptions', 'members/single/plugins' ) );
}

/** BuddyPress Templates ******************************************************/

/**
 * Get the topics created template part
 *
 * @since bbPress (r3552)
 *
 * @uses bbp_get_template_part()s
 */
function bbp_member_forums_topics_content() {
?>

	<div id="bbpress-forums">

		<?php bbp_get_template_part( 'user', 'topics-created' ); ?>

	</div>

<?php
}

/**
 * Get the topics replied to template part
 *
 * @since bbPress (r3552)
 *
 * @uses bbp_get_template_part()
 */
function bbp_member_forums_replies_content() {
?>

	<div id="bbpress-forums">

		<?php bbp_get_template_part( 'user', 'topics-replied-to' ); ?>

	</div>

<?php
}

/**
 * Get the topics favorited template part
 *
 * @since bbPress (r3552)
 *
 * @uses bbp_get_template_part()
 */
function bbp_member_forums_favorites_content() {
?>

	<div id="bbpress-forums">

		<?php bbp_get_template_part( 'user', 'favorites' ); ?>

	</div>

<?php
}

/**
 * Get the topics subscribed template part
 *
 * @since bbPress (r3552)
 *
 * @uses bbp_get_template_part()
 */
function bbp_member_forums_subscriptions_content() {
?>

	<div id="bbpress-forums">

		<?php bbp_get_template_part( 'user', 'subscriptions' ); ?>

	</div>

<?php
}

/** Forum/Group Sync **********************************************************/

/**
 * These functions are used to keep the many-to-many relationships between
 * groups and forums synchronized. Each forum and group stores ponters to each
 * other in their respective meta. This way if a group or forum is deleted
 * their associattions can be updated without much effort.
 */

/**
 * Get forum ID's for a group
 *
 * @param type $group_id
 * @since bbPress (r3653)
 */
function bbp_get_group_forum_ids( $group_id = 0 ) {

	// Assume no forums
	$forum_ids = array();

	// Use current group if none is set
	if ( empty( $group_id ) )
		$group_id = bp_get_current_group_id();

	// Get the forums
	if ( !empty( $group_id ) )
		$forum_ids = groups_get_groupmeta( $group_id, 'forum_id' );

	// Make sure result is an array
	if ( !is_array( $forum_ids ) )
		$forum_ids = (array) $forum_ids;

	// Trim out any empty array items
	$forum_ids = array_filter( $forum_ids );

	return (array) apply_filters( 'bbp_get_group_forum_ids', $forum_ids, $group_id );
}

/**
 * Get group ID's for a forum
 *
 * @param type $forum_id
 * @since bbPress (r3653)
 */
function bbp_get_forum_group_ids( $forum_id = 0 ) {

	// Assume no forums
	$group_ids = array();

	// Use current group if none is set
	if ( empty( $forum_id ) )
		$forum_id = bbp_get_forum_id();

	// Get the forums
	if ( !empty( $forum_id ) )
		$group_ids = get_post_meta( $forum_id, '_bbp_group_ids', true );

	// Make sure result is an array
	if ( !is_array( $group_ids ) )
		$group_ids = (array) $group_ids;

	// Trim out any empty array items
	$group_ids = array_filter( $group_ids );

	return (array) apply_filters( 'bbp_get_forum_group_ids', $group_ids, $forum_id );
}

/**
 * Get forum ID's for a group
 *
 * @param type $group_id
 * @since bbPress (r3653)
 */
function bbp_update_group_forum_ids( $group_id = 0, $forum_ids = array() ) {

	// Use current group if none is set
	if ( empty( $group_id ) )
		$group_id = bp_get_current_group_id();

	// Trim out any empties
	$forum_ids = array_filter( $forum_ids );

	// Get the forums
	return groups_update_groupmeta( $group_id, 'forum_id', $forum_ids );
}

/**
 * Update group ID's for a forum
 *
 * @param type $forum_id
 * @since bbPress (r3653)
 */
function bbp_update_forum_group_ids( $forum_id = 0, $group_ids = array() ) {
	$forum_id = bbp_get_forum_id( $forum_id );

	// Trim out any empties
	$group_ids = array_filter( $group_ids );

	// Get the forums
	return update_post_meta( $forum_id, '_bbp_group_ids', $group_ids );
}

/**
 * Add a group to a forum
 *
 * @param type $group_id
 * @since bbPress (r3653)
 */
function bbp_add_group_id_to_forum( $forum_id = 0, $group_id = 0 ) {

	// Validate forum_id
	$forum_id = bbp_get_forum_id( $forum_id );

	// Use current group if none is set
	if ( empty( $group_id ) )
		$group_id = bp_get_current_group_id();

	// Get current group IDs
	$group_ids = bbp_get_forum_group_ids( $forum_id );

	// Maybe update the groups forums
	if ( !in_array( $group_id, $group_ids ) ) {
		$group_ids[] = $group_id;
		return bbp_update_forum_group_ids( $forum_id, $group_ids );
	}
}

/**
 * Remove a forum from a group
 *
 * @param type $group_id
 * @since bbPress (r3653)
 */
function bbp_add_forum_id_to_group( $group_id = 0, $forum_id = 0 ) {

	// Validate forum_id
	$forum_id = bbp_get_forum_id( $forum_id );

	// Use current group if none is set
	if ( empty( $group_id ) )
		$group_id = bp_get_current_group_id();

	// Get current group IDs
	$forum_ids = bbp_get_group_forum_ids( $group_id );

	// Maybe update the groups forums
	if ( !in_array( $forum_id, $forum_ids ) ) {
		$forum_ids[] = $forum_id;
		return bbp_update_group_forum_ids( $group_id, $forum_ids );
	}
}

/**
 * Remove a group from a forum
 *
 * @param type $group_id
 * @since bbPress (r3653)
 */
function bbp_remove_group_id_from_forum( $forum_id = 0, $group_id = 0 ) {

	// Validate forum_id
	$forum_id = bbp_get_forum_id( $forum_id );

	// Use current group if none is set
	if ( empty( $group_id ) )
		$group_id = bp_get_current_group_id();

	// Get current group IDs
	$group_ids = bbp_get_forum_group_ids( $forum_id );

	// Maybe update the groups forums
	if ( in_array( $group_id, $group_ids ) ) {
		unset( $group_ids[$group_id] );
		return bbp_update_forum_group_ids( $forum_id, $group_ids );
	}
}

/**
 * Remove a forum from a group
 *
 * @param type $group_id
 * @since bbPress (r3653)
 */
function bbp_remove_forum_id_from_group( $group_id = 0, $forum_id = 0 ) {

	// Validate forum_id
	$forum_id = bbp_get_forum_id( $forum_id );

	// Use current group if none is set
	if ( empty( $group_id ) )
		$group_id = bp_get_current_group_id();

	// Get current group IDs
	$forum_ids = bbp_get_group_forum_ids( $group_id );

	// Maybe update the groups forums
	if ( in_array( $forum_id, $forum_ids ) ) {
		unset( $forum_ids[$forum_id] );
		return bbp_update_group_forum_ids( $group_id, $forum_ids );
	}
}

/**
 * Remove a group from aall forums
 *
 * @param type $group_id
 * @since bbPress (r3653)
 */
function bbp_remove_group_id_from_all_forums( $group_id = 0 ) {

	// Use current group if none is set
	if ( empty( $group_id ) )
		$group_id = bp_get_current_group_id();

	// Get current group IDs
	$forum_ids = bbp_get_group_forum_ids( $group_id );

	// Loop through forums and remove this group from each one
	foreach( (array) $forum_ids as $forum_id ) {
		bbp_remove_group_id_from_forum( $group_id, $forum_id );
	}
}

/**
 * Remove a forum from all groups
 *
 * @param type $forum_id
 * @since bbPress (r3653)
 */
function bbp_remove_forum_id_from_all_groups( $forum_id = 0 ) {

	// Validate
	$forum_id  = bbp_get_forum_id( $forum_id );
	$group_ids = bbp_get_forum_group_ids( $forum_id );

	// Loop through groups and remove this forum from each one
	foreach( (array) $group_ids as $group_id ) {
		bbp_remove_forum_id_from_group( $forum_id, $group_id );
	}
}

?>
