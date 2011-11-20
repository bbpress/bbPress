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
		add_filter( 'bbp_pre_get_user_profile_url', array( $this, 'user_profile_url' ) );

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
	 * Append forum options to activity filter select box
	 *
	 * @since bbPress (r????)
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
				$profile_url = bp_core_get_user_domain( $user_id ) . 'forums/favorites';

			// 'subscriptions' action
			} elseif ( bbp_is_subscriptions_active() && bp_is_current_action( 'subscriptions' ) ) {
				$profile_url = bp_core_get_user_domain( $user_id ) . 'forums/subscriptions';
			}

		// Not in users' forums area
		} else {
			$profile_url = bp_core_get_user_domain( $user_id );
		}

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
	 * @global bbPress $bbp
	 */
	function __construct() {
		global $bbp;

		// Name and slug
		$this->name          = __( 'Forums', 'bbpress' );
		$this->nav_item_name = __( 'Forums', 'bbpress' );
		$this->slug          = $bbp->forum_slug;

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
	}

	function display() {

	}

	function widget_display() {

	}

	/** Edit ******************************************************************/

	/**
	 * Show forums and new forum form when editing a group
	 *
	 * @since bbPress (r3563)
	 *
	 * @uses groups_get_groupmeta()
	 * @uses bp_get_current_group_id()
	 * @uses bbp_has_forums()
	 * @uses bbp_get_template_part()
	 */
	function edit_screen() {

		// Forum data
		$group_id   = bp_get_current_group_id();
		$forum_ids  = groups_get_groupmeta( $group_id, 'forum_id' );
		$forum_args = array(
			'post__in' => $forum_ids
		);

		// Query forums and show them if
		if ( !empty( $forum_ids ) && bbp_has_forums( $forum_args ) ) {
			bbp_get_template_part( 'bbpress/loop', 'forums' );
		} else { ?>

			<div id="message" class="info">
				<p><?php _e( 'This group does not have any forums.', 'bbpress' ); ?></p>
			</div>

		<?php };

		// Output the forum form
		bbp_get_template_part( 'bbpress/form', 'forum'  );

		// Verify intent
		wp_nonce_field( 'bbp_group_edit_save_' . $this->slug );
	}

	/**
	 * Save the Group Forum data on edit
	 *
	 * @since bbPress (r3465)
	 *
	 * @todo Everything
	 */
	function edit_screen_save() {
		check_admin_referer( 'bbp_group_edit_save_' . $this->slug );
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

		// Forum data
		$group_id   = bp_get_current_group_id();
		$forum_ids  = groups_get_groupmeta( $group_id, 'forum_id' );
		$forum_args = array(
			'post__in' => $forum_ids
		);

		// Query forums and show them if
		if ( !empty( $forum_ids ) && bbp_has_forums( $forum_args ) ) {
			bbp_get_template_part( 'bbpress/loop', 'forums' );
		} else { ?>

			<div id="message" class="info">
				<p><?php _e( 'This group does not have any forums. Create some or continue to the next step. You can modify this groups forums later.', 'bbpress' ); ?></p>
			</div>

		<?php };

		// Output the forum form
		bbp_get_template_part( 'bbpress/form', 'forum' );

		// Verify intent
		wp_nonce_field( 'bbp_group_create_save_' . $this->slug );
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
	}
}
endif;

/**
 * Creates the Forums component in BuddyPress
 *
 * @since bbPress (r????)
 *
 * @global bbPress $bbp
 * @global type $bp
 * @return If bbPress is not active
 */
function bbp_setup_buddypress_component() {
	global $bbp, $bp;

	// Bail if no BuddyPress
	if ( !empty( $bp->maintenance_mode ) || !defined( 'BP_VERSION' ) ) return;

	// Bail if bbPress is not loaded
	if ( !is_a( $bbp, 'bbPress' ) ) return;

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
 * @uses bbp_get_template_part()
 */
function bbp_member_forums_topics_content() {
	bbp_get_template_part( 'bbpress/user', 'topics-created' );
}

/**
 * Get the topics replied to template part
 *
 * @since bbPress (r3552)
 *
 * @uses bbp_get_template_part()
 */
function bbp_member_forums_replies_content() {
	bbp_get_template_part( 'bbpress/user', 'topics-replied-to' );
}

/**
 * Get the topics favorited template part
 *
 * @since bbPress (r3552)
 *
 * @uses bbp_get_template_part()
 */
function bbp_member_forums_favorites_content() {
	bbp_get_template_part( 'bbpress/user', 'favorites' );
}

/**
 * Get the topics subscribed template part
 *
 * @since bbPress (r3552)
 *
 * @uses bbp_get_template_part()
 */
function bbp_member_forums_subscriptions_content() {
	bbp_get_template_part( 'bbpress/user', 'subscriptions' );
}

?>
